<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Http\Controllers;

use Ethernick\ActivityPubCore\Contracts\ActivityHandlerInterface;
use Ethernick\ActivityPubCore\Http\Controllers\BaseObjectController;
use Statamic\Facades\Entry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class EventController extends BaseObjectController implements ActivityHandlerInterface
{
    public static function getHandledActivityTypes(): array
    {
        return [
            'Create:Event',
            'Update:Event',
            'Delete:Event',
            'Accept:Event',
            'Reject:Event',
            'Accept:Reference',
            'Reject:Reference',
        ];
    }

    protected function getCollectionSlug(): ?string
    {
        return 'events';
    }

    protected function returnIndexView(\Statamic\Contracts\Entries\Entry $actor): mixed
    {
        return null;
    }

    protected function returnShowView(\Statamic\Contracts\Entries\Entry $actor, \Statamic\Contracts\Entries\Entry $item): mixed
    {
        return null;
    }

    public function handleCreate(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        Log::info("EventController: Handling Create Event");
        $object = $payload['object'] ?? null;
        if (!$object) {
            return false;
        }

        if ($externalActor && !$externalActor->id()) {
            $externalActor->save();
        }

        // Stray Check
        $following = $localActor->get('following_actors', []) ?: [];
        $to = $object['to'] ?? [];
        $cc = $object['cc'] ?? [];
        $addressed = array_merge((array)$to, (array)$cc);
        $myApId = $localActor->get('activitypub_id') ?: $localActor->absoluteUrl();
        $isMentioned = in_array($myApId, $addressed);

        $inReplyTo = $object['inReplyTo'] ?? null;
        $isReplyToKnown = false;
        if ($inReplyTo) {
            if (is_array($inReplyTo)) {
                $inReplyTo = $inReplyTo['id'] ?? $inReplyTo['url'] ?? $inReplyTo[0] ?? null;
            }
            if (is_string($inReplyTo)) {
                $isReplyToKnown = Entry::query()
                    ->whereIn('collection', ['notes', 'polls', 'events'])
                    ->where('activitypub_id', $inReplyTo)
                    ->exists()
                    || \Ethernick\ActivityPubCore\Services\ActivityPubUtils::findLocalEntryByUrl($inReplyTo);
            }
        }

        if (in_array($externalActor->id(), $following) || $isMentioned || $isReplyToKnown) {
            $this->createEventEntry($object, $externalActor);
            return true;
        }

        Log::info("EventController: Ignoring Event from non-followed/irrelevant actor");
        return false;
    }

    public function handleUpdate(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        Log::info("EventController: Handling Update Event");
        $object = $payload['object'] ?? null;
        if (!$object) {
            return false;
        }

        $id = $object['id'] ?? null;
        if (!$id) {
            return false;
        }

        $following = $localActor->get('following_actors', []) ?: [];
        $followedBy = $localActor->get('followed_by_actors', []) ?: [];
        $isConnected = in_array($externalActor->id(), $following) || in_array($externalActor->id(), $followedBy);

        $existing = Entry::query()->where('collection', 'events')->where('activitypub_id', $id)->first();

        if ($isConnected || $existing) {
            if ($existing) {
                // Update basic fields
                if (isset($object['name'])) {
                    $existing->set('title', $object['name']);
                }
                if (isset($object['content'])) {
                    $existing->set('content', $object['content']);
                }
                if (isset($object['startTime'])) {
                    $existing->set('start_time', Carbon::parse($object['startTime'])->toIso8601String());
                }
                if (isset($object['endTime'])) {
                    $existing->set('end_time', Carbon::parse($object['endTime'])->toIso8601String());
                }

                // Offers / Ticketing Update
                $offers = $object['offers'] ?? null;
                if (is_array($offers)) {
                    if (isset($offers['price'])) {
                        $existing->set('ticket_price', (float)$offers['price']);
                    }
                    if (isset($offers['priceCurrency'])) {
                        $existing->set('ticket_currency', $offers['priceCurrency']);
                    }
                    if (isset($offers['url'])) {
                        $existing->set('ticket_url', $offers['url']);
                    }
                }

                // Nested Place update
                $location = $object['location'] ?? null;
                if (is_array($location) && ($location['type'] ?? '') === 'Place') {
                    $placeName = $location['name'] ?? 'Federated Location';
                    $lat = isset($location['latitude']) ? (float)$location['latitude'] : null;
                    $lon = isset($location['longitude']) ? (float)$location['longitude'] : null;
                    $osmType = $location['osm_type'] ?? null;
                    $osmId = $location['osm_id'] ?? null;

                    $placeEntry = null;
                    if ($osmType && $osmId) {
                        $placeEntry = Entry::query()
                            ->where('collection', 'places')
                            ->where('osm_type', (string) $osmType)
                            ->where('osm_id', (string) $osmId)
                            ->first();
                    }
                    if (!$placeEntry && $lat && $lon) {
                        $placeEntry = Entry::query()
                            ->where('collection', 'places')
                            ->where('latitude', $lat)
                            ->where('longitude', $lon)
                            ->first();
                    }

                    if (!$placeEntry) {
                        $placeUuid = (string) Str::uuid();
                        $placeSlug = $osmType && $osmId ? "{$osmType}/{$osmId}" : $placeUuid;

                        $placeEntry = Entry::make()
                            ->collection('places')
                            ->id($placeUuid)
                            ->slug($placeSlug);

                        $placeEntry->data([
                            'title' => $placeName,
                            'osm_name' => $placeName,
                            'latitude' => $lat,
                            'longitude' => $lon,
                            'address' => $location['address'] ?? '',
                            'osm_type' => $osmType ? (string)$osmType : null,
                            'osm_id' => $osmId ? (string)$osmId : null,
                            'is_internal' => false,
                        ]);
                        $placeEntry->save();
                    }

                    if ($placeEntry) {
                        $existing->set('location', [$placeEntry->id()]);
                    }
                }

                $existing->set('activitypub_json', json_encode($object));
                $existing->save();
            }
            return true;
        }

        return false;
    }

    public function handleDelete(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        Log::info("EventController: Handling Delete Event");
        $object = $payload['object'] ?? null;
        $objectId = is_string($object) ? $object : ($object['id'] ?? null);
        if (!$objectId) {
            return false;
        }

        $existing = Entry::query()->where('collection', 'events')->where('activitypub_id', $objectId)->first();
        if ($existing) {
            $existingActor = $existing->get('actor');
            if (is_array($existingActor)) {
                $existingActor = $existingActor[0] ?? null;
            }

            if ($existingActor === $externalActor->id()) {
                $existing->delete();
                return true;
            }
        }
        return false;
    }

    public function handleAccept(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        Log::info("EventController: Handling Accept Event (RSVP Going)");
        return $this->processRsvp($payload, $externalActor, 'accepted');
    }

    public function handleReject(array $payload, mixed $localActor, mixed $externalActor): bool
    {
        Log::info("EventController: Handling Reject Event (RSVP Not Going)");
        return $this->processRsvp($payload, $externalActor, 'rejected');
    }

    protected function processRsvp(array $payload, mixed $externalActor, string $status): bool
    {
        $object = $payload['object'] ?? null;
        $eventId = is_string($object) ? $object : ($object['id'] ?? null);

        if (!$eventId) {
            Log::warning("EventController: RSVP payload missing object Event ID");
            return false;
        }

        // Find the event entry
        $event = Entry::query()
            ->where('collection', 'events')
            ->where(function ($q) use ($eventId) {
                $q->where('activitypub_id', $eventId)
                    ->orWhere('id', $eventId);
            })
            ->first();

        if (!$event) {
            // Check local URL match
            $local = \Ethernick\ActivityPubCore\Services\ActivityPubUtils::findLocalEntryByUrl($eventId);
            if ($local && $local->collection()->handle() === 'events') {
                $event = $local;
            }
        }

        if (!$event) {
            Log::info("EventController: Event not found for RSVP: $eventId");
            return false;
        }

        $actorUri = $externalActor->get('activitypub_id') ?: $payload['actor'] ?? $externalActor->id();
        $actorName = $externalActor->get('name') ?: $externalActor->get('title') ?: 'Federated Guest';
        $actorHandle = $externalActor->get('handle') ?: '@' . $externalActor->slug();
        $actorAvatar = $externalActor->get('avatar') ?: null;

        $attendeeData = [
            'actor' => (string) $actorUri,
            'name' => (string) $actorName,
            'handle' => (string) $actorHandle,
            'avatar' => $actorAvatar,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ];

        $attendees = $event->get('attendees', []) ?: [];
        if (!is_array($attendees)) {
            $attendees = [];
        }

        // Update or insert attendee
        $found = false;
        foreach ($attendees as $idx => $att) {
            $existingActor = $att['actor'] ?? null;
            if ($existingActor === $actorUri || $existingActor === $externalActor->id()) {
                $attendees[$idx] = array_merge($att, $attendeeData);
                $found = true;
                break;
            }
        }

        if (!$found) {
            $attendees[] = $attendeeData;
        }

        $event->set('attendees', array_values($attendees));
        $event->save();

        // Dispatch EventRsvpReceived event for third-party and notification hooks
        \Ethernick\ActivityPubEvents\Events\EventRsvpReceived::dispatch(
            $event,
            $attendeeData,
            $status,
            $externalActor
        );

        Log::info("EventController: Successfully processed RSVP {$status} for event {$event->id()} from {$actorHandle}");
        return true;
    }

    protected function createEventEntry(array $object, mixed $authorActor): mixed
    {
        $id = $object['id'] ?? null;
        if ($id) {
            $existing = Entry::query()->where('collection', 'events')->where('activitypub_id', $id)->first();
            if ($existing) {
                return $existing;
            }
        }

        $uuid = (string) Str::uuid();
        $content = $object['content'] ?? '';
        $title = $object['name'] ?? strip_tags($content) ?: $uuid;

        $dateStr = $object['published'] ?? $object['updated'] ?? null;
        $date = $dateStr ? Carbon::parse($dateStr) : now();
        $published = $date->toIso8601String();

        $startTimeStr = $object['startTime'] ?? null;
        $startTime = $startTimeStr ? Carbon::parse($startTimeStr)->toIso8601String() : null;

        $endTimeStr = $object['endTime'] ?? null;
        $endTime = $endTimeStr ? Carbon::parse($endTimeStr)->toIso8601String() : null;

        // 1. Resolve or Create Nesting Location (Place)
        $placeEntry = null;
        $location = $object['location'] ?? null;

        if (is_array($location) && ($location['type'] ?? '') === 'Place') {
            $placeName = $location['name'] ?? 'Federated Location';
            $lat = isset($location['latitude']) ? (float)$location['latitude'] : null;
            $lon = isset($location['longitude']) ? (float)$location['longitude'] : null;

            $osmType = $location['osm_type'] ?? null;
            $osmId = $location['osm_id'] ?? null;

            // Search by OSM type and ID if present
            if ($osmType && $osmId) {
                $placeEntry = Entry::query()
                    ->where('collection', 'places')
                    ->where('osm_type', (string) $osmType)
                    ->where('osm_id', (string) $osmId)
                    ->first();
            }

            // Fallback: Search by coordinates matching
            if (!$placeEntry && $lat && $lon) {
                $placeEntry = Entry::query()
                    ->where('collection', 'places')
                    ->where('latitude', $lat)
                    ->where('longitude', $lon)
                    ->first();
            }

            // If Place is still not resolved, dynamically create it!
            if (!$placeEntry) {
                $placeUuid = (string) Str::uuid();
                $placeSlug = $osmType && $osmId ? "{$osmType}/{$osmId}" : $placeUuid;

                $placeEntry = Entry::make()
                    ->collection('places')
                    ->id($placeUuid)
                    ->slug($placeSlug);

                $placeEntry->data([
                    'title' => $placeName,
                    'osm_name' => $placeName,
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'address' => $location['address'] ?? '',
                    'osm_type' => $osmType ? (string)$osmType : null,
                    'osm_id' => $osmId ? (string)$osmId : null,
                    'is_internal' => false,
                ]);

                $placeEntry->save();
            }
        }

        // 2. Parse Ticketing and Offers
        $ticketPrice = null;
        $ticketCurrency = 'CAD';
        $ticketUrl = null;

        // Try standard Schema.org offers
        $offers = $object['offers'] ?? null;
        if (is_array($offers)) {
            $ticketPrice = isset($offers['price']) ? (float)$offers['price'] : null;
            $ticketCurrency = $offers['priceCurrency'] ?? 'CAD';
            $ticketUrl = $offers['url'] ?? null;
        }

        // Fallback or verify: FEP-0ea0 Payment Links inside attachment array
        if (!$ticketUrl && isset($object['attachment']) && is_array($object['attachment'])) {
            foreach ($object['attachment'] as $attach) {
                if (is_array($attach) && ($attach['type'] ?? '') === 'Link' && ($attach['rel'] ?? '') === 'payment' && isset($attach['href'])) {
                    $ticketUrl = $attach['href'];
                    break;
                }
            }
        }

        // 3. Create Event entry
        $event = Entry::make()
            ->collection('events')
            ->id($uuid)
            ->slug($uuid)
            ->date($date)
            ->data([
                'title' => $title,
                'content' => $content,
                'actor' => $authorActor->id(),
                'date' => $published,
                'activitypub_id' => $id,
                'activitypub_json' => json_encode($object),
                'is_internal' => false,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'location' => $placeEntry ? [$placeEntry->id()] : null,
                'ticket_price' => $ticketPrice,
                'ticket_currency' => $ticketCurrency,
                'ticket_url' => $ticketUrl,
            ]);

        $event->save();
        return $event;
    }
}
