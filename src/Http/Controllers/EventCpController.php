<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Http\Controllers;

use Statamic\Http\Controllers\CP\CpController;
use Statamic\Facades\Entry;

class EventCpController extends CpController
{
    public function index(\Illuminate\Http\Request $request)
    {
        $events = Entry::query()
            ->where('collection', 'events')
            ->get()
            ->map(function ($entry) {
                return \Ethernick\ActivityPubCore\Services\ActivityPubUtils::transformEntryForFeed($entry);
            })
            ->values()
            ->toArray();

        if ($request->wantsJson()) {
            return response()->json(['events' => $events]);
        }

        return view('activitypub-events::index', [
            'title' => 'Events',
            'events' => $events,
        ]);
    }

    /**
     * Store a new event from the CP inbox or dashboard.
     */
    public function store(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'actor' => 'required|string',
            'title' => 'required|string',
            'content' => 'required|string',
            'start_time' => 'required|string',
            'end_time' => 'nullable|string',
            'place' => 'required|array',
            'ticket_price' => 'nullable|numeric',
            'ticket_currency' => 'nullable|string',
            'ticket_url' => 'nullable|url',
        ]);

        $placeData = $data['place'];
        $placeEntry = null;

        // 1. Resolve or Create Place
        if ($placeData['is_internal'] ?? false) {
            $placeEntry = Entry::find($placeData['internal_id']);
        } else {
            // Create Place from OSM data using standard places logic
            $placeEntry = \Ethernick\ActivityPubPlaces\Services\ActivityPubPlacesUtils::createPlace($placeData, $data['actor']);
        }

        if (!$placeEntry) {
            return response()->json(['error' => 'Place not found or could not be created'], 422);
        }

        // 2. Create Event entry
        $event = Entry::make()
            ->collection('events')
            ->published(true)
            ->slug((string) \Illuminate\Support\Str::uuid());

        $startTime = \Carbon\Carbon::parse($data['start_time'])->toIso8601String();
        $endTime = isset($data['end_time']) && !empty($data['end_time']) 
            ? \Carbon\Carbon::parse($data['end_time'])->toIso8601String() 
            : null;

        $event->set('title', $data['title']);
        $event->set('content', $data['content']);
        $event->set('actor', [$data['actor']]);
        $event->set('start_time', $startTime);
        $event->set('end_time', $endTime);
        $event->set('location', [$placeEntry->id()]);
        $event->set('ticket_price', isset($data['ticket_price']) ? (float) $data['ticket_price'] : null);
        $event->set('ticket_currency', $data['ticket_currency'] ?? 'CAD');
        $event->set('ticket_url', $data['ticket_url'] ?? null);
        $event->set('is_internal', true);

        $event->save();

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'entry_id' => $event->id(),
        ]);
    }

    /**
     * Handle manual RSVP toggle for local Statamic actor.
     */
    public function rsvp(\Illuminate\Http\Request $request, string $id)
    {
        $data = $request->validate([
            'status' => 'required|string|in:accepted,rejected',
            'actor' => 'nullable|string',
        ]);

        $status = $data['status'];

        // 1. Resolve User and Actor
        $user = \Statamic\Facades\User::current();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $userActors = $user->get('actors', []) ?: [];
        $actorId = $data['actor'] ?? ($userActors[0] ?? null);

        if (!$actorId || !in_array($actorId, $userActors)) {
            return response()->json(['error' => 'No authorized actor found'], 400);
        }

        $localActor = Entry::find($actorId);
        if (!$localActor) {
            return response()->json(['error' => 'Actor not found'], 404);
        }

        // 2. Resolve Event entry (by ID, slug, or activitypub_id)
        $event = Entry::query()
            ->where('collection', 'events')
            ->where(function ($q) use ($id) {
                $q->where('id', $id)
                    ->orWhere('slug', $id)
                    ->orWhere('activitypub_id', $id);
            })
            ->first();

        if (!$event) {
            $local = \Ethernick\ActivityPubCore\Services\ActivityPubUtils::findLocalEntryByUrl($id);
            if ($local && $local->collection()->handle() === 'events') {
                $event = $local;
            }
        }

        if (!$event) {
            return response()->json(['error' => 'Event not found'], 404);
        }

        // 3. Prepare attendee record
        $actorUri = $localActor->get('activitypub_id') ?: url("/@{$localActor->slug()}");
        $actorName = $localActor->get('name') ?: $localActor->get('title') ?: $localActor->slug();
        $actorHandle = $localActor->get('handle') ?: '@' . $localActor->slug();
        $actorAvatar = $localActor->get('avatar') ?: null;

        $attendeeData = [
            'actor' => (string) $actorId,
            'actor_uri' => (string) $actorUri,
            'name' => (string) $actorName,
            'handle' => (string) $actorHandle,
            'avatar' => $actorAvatar,
            'status' => $status,
            'timestamp' => now()->toIso8601String(),
        ];

        // 4. Update Event roster
        $attendees = $event->get('attendees', []) ?: [];
        if (!is_array($attendees)) {
            $attendees = [];
        }

        $found = false;
        foreach ($attendees as $idx => $att) {
            $existingActor = $att['actor'] ?? null;
            if ($existingActor === $actorId || $existingActor === $actorUri) {
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

        // 5. Dispatch EventRsvpReceived event
        \Ethernick\ActivityPubEvents\Events\EventRsvpReceived::dispatch(
            $event,
            $attendeeData,
            $status,
            $localActor
        );

        // 6. Dispatch Outbox Activity if event is federated or has external organizer
        $eventApId = $event->get('activitypub_id') ?: $event->absoluteUrl();
        $isInternalEvent = $event->get('is_internal', true);

        // Determine recipient(s) for the Accept/Reject activity
        $recipients = [];
        if (!$isInternalEvent) {
            // Send to event author/organizer if external
            $organizer = $event->get('actor');
            if (is_array($organizer)) {
                $organizer = $organizer[0] ?? null;
            }
            if ($organizer) {
                $orgEntry = Entry::find($organizer);
                if ($orgEntry) {
                    $orgApId = $orgEntry->get('activitypub_id') ?: $orgEntry->get('url');
                    if ($orgApId) {
                        $recipients[] = $orgApId;
                    }
                } elseif (filter_var($organizer, FILTER_VALIDATE_URL)) {
                    $recipients[] = $organizer;
                }
            }
        }

        // Also public addressing so followers know the user RSVP'd
        $recipients[] = 'https://www.w3.org/ns/activitystreams#Public';
        $recipients = array_values(array_unique($recipients));

        $activityType = $status === 'accepted' ? 'Accept' : 'Reject';
        $uuid = (string) \Illuminate\Support\Str::uuid();
        $activitySlug = strtolower($activityType) . '-' . $uuid;

        $activityJson = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => url("/activities/{$activitySlug}"),
            'type' => $activityType,
            'actor' => $actorUri,
            'object' => $eventApId,
            'to' => $recipients,
            'published' => now()->toIso8601String(),
        ];

        $activity = Entry::make()
            ->collection('activities')
            ->slug($activitySlug)
            ->published(true)
            ->data([
                'title' => "{$activityType} {$event->get('title')}",
                'type' => $activityType,
                'actor' => [$actorId],
                'object' => $eventApId,
                'to' => $recipients,
                'is_internal' => true,
                'activitypub_collections' => ['outbox'],
                'activitypub_json' => json_encode($activityJson),
            ]);

        $activity->save();

        // 7. Calculate valid attendees for CP UI response
        $validAttendees = array_values(array_filter($attendees, fn($a) => ($a['status'] ?? 'accepted') === 'accepted'));

        return response()->json([
            'success' => true,
            'status' => $status,
            'user_rsvp' => $status === 'accepted',
            'attendees' => $validAttendees,
            'attendee_count' => count($validAttendees),
            'message' => $status === 'accepted' ? "RSVP'd as Going!" : "RSVP cancelled.",
        ]);
    }
}

