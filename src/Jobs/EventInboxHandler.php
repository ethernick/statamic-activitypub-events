<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Jobs;

use Ethernick\ActivityPubCore\Contracts\InboxActivityHandlerInterface;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;
use Statamic\Facades\Entry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EventInboxHandler implements InboxActivityHandlerInterface
{
    public function handle(array $payload, array $object, mixed $localActor, mixed $externalActor): bool
    {
        $actorId = $payload['actor'] ?? 'Unknown';
        $type = $payload['type'] ?? 'Create';

        // Handle Accept / Reject activities for Events
        if (in_array($type, ['Accept', 'Reject'])) {
            $controller = app(\Ethernick\ActivityPubEvents\Http\Controllers\EventController::class);
            $method = "handle{$type}";
            if (method_exists($controller, $method)) {
                return $controller->$method($payload, $localActor, $externalActor);
            }
        }
        
        // 1. Federated Check
        if (!ActivityPubUtils::isFederated('events')) {
            Log::info("EventInboxHandler: Dropping Event because events collection is not federated.");
            return false;
        }

        // 2. Resolve Relationships
        $following = $localActor->get('following_actors', []) ?: [];
        $to = $object['to'] ?? [];
        $cc = $object['cc'] ?? [];
        $addressed = array_merge((array)$to, (array)$cc);
        $myApId = $localActor->get('activitypub_id') ?: $localActor->absoluteUrl();
        $isMentioned = in_array($myApId, $addressed);

        $inReplyTo = $object['inReplyTo'] ?? null;
        $isReplyToKnown = false;
        if ($inReplyTo) {
            $isReplyToKnown = Entry::query()
                ->whereIn('collection', ['notes', 'polls', 'events'])
                ->where('activitypub_id', $inReplyTo)
                ->exists()
                || ActivityPubUtils::findLocalEntryByUrl($inReplyTo);
        }

        // 3. Authorization Check
        if (in_array($externalActor->id(), $following) || $isMentioned || $isReplyToKnown) {
            $this->createEventEntry($object, $externalActor);
            return true;
        }

        Log::info("EventInboxHandler: Ignoring Event from non-followed/irrelevant actor $actorId");
        return false;
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
