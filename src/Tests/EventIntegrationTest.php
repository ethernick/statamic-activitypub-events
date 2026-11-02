<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Tests;

use Ethernick\ActivityPubCore\Services\ActivityPubTypes;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;
use Ethernick\ActivityPubCore\Transformers\ActivityPubObjectTransformer;
use Statamic\Facades\Entry;
use Statamic\Facades\File;
use Statamic\Facades\YAML;

class EventIntegrationTest extends EventsTestCase
{
    public function test_event_type_is_registered(): void
    {
        $types = app(ActivityPubTypes::class)->all();

        $this->assertArrayHasKey('Event', $types);
        $this->assertEquals('Event', $types['Event']['label']);
        $this->assertEquals('Ethernick\ActivityPubEvents\Http\Controllers\EventController', $types['Event']['controller']);
        $this->assertEquals('Ethernick\ActivityPubEvents\Http\Handlers\EventOutboxHandler', $types['Event']['outbox_handler']);
    }

    public function test_install_command_works(): void
    {
        // Delete collection if it exists in sandbox to test creation
        if ($col = \Statamic\Facades\Collection::findByHandle('events')) {
            $col->delete();
        }

        $this->artisan('activitypub:events:install')
            ->assertExitCode(0);

        // Verify collection
        $this->assertNotNull(\Statamic\Facades\Collection::findByHandle('events'));

        // Verify settings updated
        $settingsPath = ActivityPubUtils::settingsPath();
        $this->assertFileExists($settingsPath);
        $settings = YAML::parse(File::get($settingsPath));
        $this->assertTrue($settings['events']['enabled'] ?? false);
        $this->assertEquals('Event', $settings['events']['type'] ?? '');

        // Verify blueprint copied
        $blueprintPath = ActivityPubUtils::blueprintPath('collections/events/event.yaml');
        $this->assertFileExists($blueprintPath);
    }


    public function test_event_outbox_serialization(): void
    {
        // 1. Set settings
        $settingsPath = ActivityPubUtils::settingsPath();
        File::put($settingsPath, YAML::dump([
            'events' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Event',
                'compatibility_mode' => false,
            ],
            'places' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Place',
            ]
        ]));

        // 2. Create Actor
        $actor = Entry::make()
            ->collection('actors')
            ->slug('test-actor')
            ->data(['title' => 'Test Actor'])
            ->id('actor-1');
        $actor->save();

        // 3. Create Place
        $place = Entry::make()
            ->collection('places')
            ->slug('osm/12345')
            ->data([
                'title' => 'Langley Library',
                'osm_name' => 'Langley Public Library',
                'latitude' => 49.1044,
                'longitude' => -122.6582,
                'address' => 'Langley, BC',
            ])
            ->id('place-1');
        $place->save();

        // 4. Create Event
        $event = Entry::make()
            ->collection('events')
            ->slug('fediverse-meetup')
            ->data([
                'title' => 'Fedi Meetup',
                'content' => 'Let us talk ActivityPub.',
                'start_time' => '2026-07-15 18:00',
                'end_time' => '2026-07-15 21:00',
                'location' => ['place-1'],
                'ticket_price' => 10.00,
                'ticket_currency' => 'CAD',
                'ticket_url' => 'https://example.com/tickets/buy',
                'actor' => ['actor-1'],
                'is_internal' => true,
            ])
            ->id('event-1');
        $event->save();

        // 5. Transform Event to ActivityPub JSON
        $transformer = app(ActivityPubObjectTransformer::class);
        $json = $transformer->transform($event, null, 'Event');

        // 6. Assertions
        $this->assertEquals('Event', $json['type']);
        $this->assertEquals('Fedi Meetup', $json['name']);
        
        // Assert dates formatted as ISO 8601
        $this->assertEquals(\Carbon\Carbon::parse('2026-07-15 18:00')->toIso8601String(), $json['startTime']);
        $this->assertEquals(\Carbon\Carbon::parse('2026-07-15 21:00')->toIso8601String(), $json['endTime']);

        // Assert nested location
        $this->assertArrayHasKey('location', $json);
        $this->assertEquals('Place', $json['location']['type']);
        $this->assertEquals('Langley Public Library', $json['location']['name']);
        $this->assertEquals(49.1044, $json['location']['latitude']);
        $this->assertEquals(-122.6582, $json['location']['longitude']);
        $this->assertEquals('Langley, BC', $json['location']['address']);

        // Assert Primary FEP-0ea0 Payment Link
        $this->assertArrayHasKey('attachment', $json);
        $paymentAttachment = collect($json['attachment'])->firstWhere('rel', 'payment');
        $this->assertNotNull($paymentAttachment);
        $this->assertEquals('Link', $paymentAttachment['type']);
        $this->assertEquals('https://example.com/tickets/buy', $paymentAttachment['href']);
        $this->assertEquals('Buy', $paymentAttachment['name']);

        // Assert Secondary Schema.org offers
        $this->assertArrayHasKey('offers', $json);
        $this->assertEquals('Offer', $json['offers']['type']);
        $this->assertEquals('10.00', $json['offers']['price']);
        $this->assertEquals('CAD', $json['offers']['priceCurrency']);
        $this->assertEquals('https://example.com/tickets/buy', $json['offers']['url']);

        // Assert Schema.org context definition
        $context = $json['@context'];
        $hasSchema = false;
        foreach ($context as $ctx) {
            if (is_array($ctx) && isset($ctx['schema']) && $ctx['schema'] === 'https://schema.org/') {
                $hasSchema = true;
                break;
            }
        }
        $this->assertTrue($hasSchema, 'Schema.org not found in @context');
    }

    public function test_legacy_note_generation(): void
    {
        // 1. Enable compatibility mode
        $settingsPath = ActivityPubUtils::settingsPath();
        File::put($settingsPath, YAML::dump([
            'events' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Event',
                'compatibility_mode' => true,
            ],
            'places' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Place',
            ]
        ]));

        // 2. Create Actor
        $actor = Entry::make()
            ->collection('actors')
            ->slug('test-actor')
            ->data(['title' => 'Test Actor'])
            ->id('actor-1');
        $actor->save();

        // 3. Create Place
        $place = Entry::make()
            ->collection('places')
            ->slug('osm/12345')
            ->data([
                'title' => 'Langley Library',
                'osm_name' => 'Langley Public Library',
                'latitude' => 49.1044,
                'longitude' => -122.6582,
                'address' => 'Langley, BC',
            ])
            ->id('place-1');
        $place->save();

        // 4. Create Event
        $event = Entry::make()
            ->collection('events')
            ->slug('fediverse-meetup-legacy')
            ->data([
                'title' => 'Fedi Meetup',
                'content' => 'Let us talk ActivityPub.',
                'start_time' => '2026-07-15 18:00',
                'end_time' => '2026-07-15 21:00',
                'location' => ['place-1'],
                'ticket_price' => 15.00,
                'ticket_currency' => 'CAD',
                'ticket_url' => 'https://example.com/tickets/buy',
                'actor' => ['actor-1'],
                'is_internal' => true,
            ])
            ->id('event-legacy-1');
        $event->save();

        // 5. Verify Note entry was automatically generated
        $note = Entry::query()
            ->where('collection', 'notes')
            ->where('event_id', 'event-legacy-1')
            ->first();

        $this->assertNotNull($note);
        
        // Assert Markdown content formatting
        $content = $note->get('content');
        $this->assertStringContainsString('📅 **Fedi Meetup**', $content);
        $this->assertStringContainsString('Let us talk ActivityPub.', $content);
        $this->assertStringContainsString('⏰ Time:', $content);
        $this->assertStringContainsString('📍 Location: [Langley Library]', $content);
        $this->assertStringContainsString('💵 Price: 15.00 CAD', $content);
        $this->assertStringContainsString('🎟️ Buy Tickets: [Buy](https://example.com/tickets/buy)', $content);

        // 6. Transform Note to ActivityPub JSON and verify outbox hooks mapped ticketing info
        $transformer = app(ActivityPubObjectTransformer::class);
        $json = $transformer->transform($note, null, 'Note');

        // Assert Note type
        $this->assertEquals('Note', $json['type']);

        // Assert Primary FEP-0ea0 Payment Link is attached to the Note
        $this->assertArrayHasKey('attachment', $json);
        $paymentAttachment = collect($json['attachment'])->firstWhere('rel', 'payment');
        $this->assertNotNull($paymentAttachment);
        $this->assertEquals('Link', $paymentAttachment['type']);
        $this->assertEquals('https://example.com/tickets/buy', $paymentAttachment['href']);
        $this->assertEquals('Buy', $paymentAttachment['name']);

        // Assert Secondary Schema.org offers is attached to the Note
        $this->assertArrayHasKey('offers', $json);
        $this->assertEquals('Offer', $json['offers']['type']);
        $this->assertEquals('15.00', $json['offers']['price']);
        $this->assertEquals('CAD', $json['offers']['priceCurrency']);

        // 7. Verify deletion of Event deletes the corresponding Note
        $event->delete();
        $deletedNote = Entry::query()
            ->where('collection', 'notes')
            ->where('event_id', 'event-legacy-1')
            ->first();
        $this->assertNull($deletedNote);
    }

    public function test_cp_dashboard_route_is_accessible(): void
    {
        $this->actingAsSuperUser();

        $this->get(route('statamic.cp.activitypub.events.index'))
            ->assertStatus(200)
            ->assertSee('events-dashboard');
    }

    public function test_event_inbox_handler_resolves_and_creates_event_and_place(): void
    {
        // 1. Enable federation
        $settingsPath = ActivityPubUtils::settingsPath();
        File::put($settingsPath, YAML::dump([
            'events' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Event',
            ],
            'places' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Place',
            ]
        ]));

        // 2. Mock Local and External Actors
        $localActor = Entry::make()
            ->collection('actors')
            ->slug('local-user')
            ->data([
                'title' => 'Local User',
                'activitypub_id' => 'http://localhost/actor/local-user',
                'following_actors' => ['external-actor-id'],
            ])
            ->id('local-actor-id');
        $localActor->save();

        $externalActor = Entry::make()
            ->collection('actors')
            ->slug('sender')
            ->data([
                'title' => 'External Sender',
                'activitypub_id' => 'http://external.com/actor/sender',
            ])
            ->id('external-actor-id');
        $externalActor->save();

        // 3. Prepare Incoming Payload
        $payload = [
            'type' => 'Create',
            'actor' => 'http://external.com/actor/sender',
            'object' => [
                'id' => 'https://external.com/events/123',
                'type' => 'Event',
                'name' => 'Ottawa Fedi Meetup',
                'content' => 'Join us for dinner.',
                'startTime' => '2026-08-01T12:00:00Z',
                'endTime' => '2026-08-01T15:00:00Z',
                'location' => [
                    'type' => 'Place',
                    'name' => 'Ottawa Public Library',
                    'latitude' => 45.4215,
                    'longitude' => -75.6972,
                    'address' => 'Ottawa, ON',
                    'osm_type' => 'node',
                    'osm_id' => '9999123'
                ],
                'offers' => [
                    'type' => 'Offer',
                    'price' => '5.00',
                    'priceCurrency' => 'USD',
                    'url' => 'https://external.com/buy-tickets'
                ]
            ]
        ];

        // 4. Handle Incoming Event
        $handler = new \Ethernick\ActivityPubEvents\Jobs\EventInboxHandler();
        $result = $handler->handle($payload, $payload['object'], $localActor, $externalActor);

        // 5. Verify Successful Handle
        $this->assertTrue($result);

        // 6. Verify nested Place creation
        $place = Entry::query()
            ->where('collection', 'places')
            ->where('osm_type', 'node')
            ->where('osm_id', '9999123')
            ->first();

        $this->assertNotNull($place);
        $this->assertEquals('Ottawa Public Library', $place->get('title'));
        $this->assertEquals(45.4215, $place->get('latitude'));
        $this->assertEquals(-75.6972, $place->get('longitude'));
        $this->assertFalse($place->get('is_internal'));

        // 7. Verify Event creation and reference linkage
        $event = Entry::query()
            ->where('collection', 'events')
            ->where('activitypub_id', 'https://external.com/events/123')
            ->first();

        $this->assertNotNull($event);
        $this->assertEquals('Ottawa Fedi Meetup', $event->get('title'));
        $this->assertEquals('Join us for dinner.', $event->get('content'));
        $this->assertEquals('external-actor-id', $event->get('actor'));
        $this->assertEquals([$place->id()], $event->get('location'));
        $this->assertEquals(5.00, $event->get('ticket_price'));
        $this->assertEquals('USD', $event->get('ticket_currency'));
        $this->assertEquals('https://external.com/buy-tickets', $event->get('ticket_url'));
        $this->assertFalse($event->get('is_internal'));
    }

    public function test_activity_dispatcher_routes_event_activities(): void
    {
        // 1. Enable federation
        $settingsPath = ActivityPubUtils::settingsPath();
        File::put($settingsPath, YAML::dump([
            'events' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Event',
            ],
            'places' => [
                'enabled' => true,
                'federated' => true,
                'type' => 'Place',
            ]
        ]));

        // 2. Mock Local and External Actors
        $localActor = Entry::make()
            ->collection('actors')
            ->slug('local-user-dispatch')
            ->data([
                'title' => 'Local User Dispatch',
                'activitypub_id' => 'http://localhost/actor/local-user-dispatch',
                'following_actors' => ['external-actor-dispatch-id'],
            ])
            ->id('local-actor-dispatch-id');
        $localActor->save();

        $externalActor = Entry::make()
            ->collection('actors')
            ->slug('sender-dispatch')
            ->data([
                'title' => 'External Sender Dispatch',
                'activitypub_id' => 'http://external.com/actor/sender-dispatch',
            ])
            ->id('external-actor-dispatch-id');
        $externalActor->save();

        // 3. Prepare Incoming Payload (Create:Event)
        $createPayload = [
            'type' => 'Create',
            'actor' => 'http://external.com/actor/sender-dispatch',
            'object' => [
                'id' => 'https://external.com/events/dispatch-123',
                'type' => 'Event',
                'name' => 'Montreal Fedi Meetup',
                'content' => 'Join us for coffee.',
                'startTime' => '2026-09-01T12:00:00Z',
                'endTime' => '2026-09-01T15:00:00Z',
                'location' => [
                    'type' => 'Place',
                    'name' => 'Montreal Coffee Shop',
                    'latitude' => 45.5017,
                    'longitude' => -73.5673,
                    'address' => 'Montreal, QC',
                    'osm_type' => 'node',
                    'osm_id' => '8888123'
                ],
                'offers' => [
                    'type' => 'Offer',
                    'price' => '3.00',
                    'priceCurrency' => 'CAD',
                    'url' => 'https://external.com/buy-coffee-tickets'
                ]
            ]
        ];

        // 4. Force registration in dispatcher (just in case boot wasn't fully triggered in test context)
        \Ethernick\ActivityPubCore\Services\ActivityDispatcher::registerController(\Ethernick\ActivityPubEvents\Http\Controllers\EventController::class);

        // 5. Dispatch via the central dispatcher
        $result = \Ethernick\ActivityPubCore\Services\ActivityDispatcher::dispatch($createPayload, $localActor, $externalActor);

        // 6. Verify Successful dispatch result
        $this->assertTrue($result);

        // Verify Place creation
        $place = Entry::query()
            ->where('collection', 'places')
            ->where('osm_type', 'node')
            ->where('osm_id', '8888123')
            ->first();
        $this->assertNotNull($place);

        // Verify Event creation
        $event = Entry::query()
            ->where('collection', 'events')
            ->where('activitypub_id', 'https://external.com/events/dispatch-123')
            ->first();
        $this->assertNotNull($event);
        $this->assertEquals('Montreal Fedi Meetup', $event->get('title'));
        $this->assertEquals('Join us for coffee.', $event->get('content'));
        $this->assertEquals([$place->id()], $event->get('location'));
        $this->assertEquals(3.00, $event->get('ticket_price'));

        // 7. Dispatch Update activity via dispatcher
        $updatePayload = [
            'type' => 'Update',
            'actor' => 'http://external.com/actor/sender-dispatch',
            'object' => [
                'id' => 'https://external.com/events/dispatch-123',
                'type' => 'Event',
                'name' => 'Montreal Fedi Meetup - UPDATED',
                'content' => 'Join us for coffee and pastries.',
                'startTime' => '2026-09-01T13:00:00Z',
                'endTime' => '2026-09-01T16:00:00Z',
                'location' => [
                    'type' => 'Place',
                    'name' => 'Montreal Coffee Shop',
                    'latitude' => 45.5017,
                    'longitude' => -73.5673,
                    'address' => 'Montreal, QC',
                    'osm_type' => 'node',
                    'osm_id' => '8888123'
                ],
                'offers' => [
                    'type' => 'Offer',
                    'price' => '4.50',
                    'priceCurrency' => 'CAD',
                    'url' => 'https://external.com/buy-coffee-tickets-new'
                ]
            ]
        ];

        $updateResult = \Ethernick\ActivityPubCore\Services\ActivityDispatcher::dispatch($updatePayload, $localActor, $externalActor);
        $this->assertTrue($updateResult);

        // Verify Event was updated
        $event = Entry::find($event->id());
        $this->assertEquals('Montreal Fedi Meetup - UPDATED', $event->get('title'));
        $this->assertEquals('Join us for coffee and pastries.', $event->get('content'));
        $this->assertEquals(4.50, $event->get('ticket_price'));
        $this->assertEquals('https://external.com/buy-coffee-tickets-new', $event->get('ticket_url'));

        // 8. Dispatch Delete activity via dispatcher
        $deletePayload = [
            'type' => 'Delete',
            'actor' => 'http://external.com/actor/sender-dispatch',
            'object' => [
                'id' => 'https://external.com/events/dispatch-123',
                'type' => 'Event'
            ],
        ];

        $deleteResult = \Ethernick\ActivityPubCore\Services\ActivityDispatcher::dispatch($deletePayload, $localActor, $externalActor);
        $this->assertTrue($deleteResult);

        // Verify Event was deleted
        $eventDeleted = Entry::query()
            ->where('collection', 'events')
            ->where('activitypub_id', 'https://external.com/events/dispatch-123')
            ->first();
        $this->assertNull($eventDeleted);
    }

    public function test_event_transform_hook_populates_event_data_for_action_bar(): void
    {
        // 1. Create a Place
        $place = Entry::make()
            ->collection('places')
            ->slug('test-venue-langley')
            ->data([
                'title' => 'Fort Langley Community Hall',
                'osm_name' => 'Community Hall',
                'address' => '9167 Glover Rd, Langley Township, BC',
                'latitude' => 49.1706,
                'longitude' => -122.5801,
            ]);
        $place->save();

        // 2. Create an Event
        $event = Entry::make()
            ->collection('events')
            ->slug('test-action-bar-event')
            ->data([
                'title' => 'Langley Summer Market',
                'start_time' => '2026-07-15T18:00:00Z',
                'end_time' => '2026-07-15T22:00:00Z',
                'ticket_price' => 15.00,
                'ticket_currency' => 'CAD',
                'ticket_url' => 'https://example.com/tickets/langley-summer-market',
                'location' => [$place->id()],
            ]);
        $event->save();

        // 3. Test direct Event transform
        $eventData = [];
        \Ethernick\ActivityPubCore\Services\ActivityPubTypes::executeTransformHooks($event, $eventData);

        $this->assertEquals('Langley Summer Market', $eventData['title']);
        $this->assertEquals('2026-07-15T18:00:00Z', $eventData['start_time']);
        $this->assertEquals(15.00, $eventData['ticket_price']);
        $this->assertEquals('CAD', $eventData['ticket_currency']);
        $this->assertEquals('https://example.com/tickets/langley-summer-market', $eventData['ticket_url']);
        $this->assertNotNull($eventData['location']);
        $this->assertEquals('Fort Langley Community Hall', $eventData['location']['title']);
        $this->assertEquals(49.1706, $eventData['location']['latitude']);

        // 4. Test Note representing event transform
        $note = Entry::make()
            ->collection('notes')
            ->slug('test-action-bar-note')
            ->data([
                'content' => 'Join us at the market!',
                'event_id' => $event->id(),
            ]);
        $note->save();

        $noteData = [];
        \Ethernick\ActivityPubCore\Services\ActivityPubTypes::executeTransformHooks($note, $noteData);

        $this->assertArrayHasKey('event', $noteData);
        $this->assertEquals('Langley Summer Market', $noteData['event']['title']);
        $this->assertEquals(15.00, $noteData['event']['ticket_price']);
        $this->assertEquals('https://example.com/tickets/langley-summer-market', $noteData['event']['ticket_url']);
        $this->assertEquals('Fort Langley Community Hall', $noteData['event']['location']['title']);
    }

    public function test_inbound_accept_and_reject_activities_update_attendees(): void
    {
        \Illuminate\Support\Facades\Event::fake([
            \Ethernick\ActivityPubEvents\Events\EventRsvpReceived::class
        ]);

        // 1. Create an Event entry
        $event = Entry::make()
            ->collection('events')
            ->slug('test-rsvp-event')
            ->data([
                'title' => 'Fediverse Conference 2026',
                'activitypub_id' => 'https://example.com/events/fedi-conf-2026',
                'start_time' => '2026-10-10T10:00:00Z',
            ]);
        $event->save();

        // 2. Mock Local and External Actor
        $localActor = Entry::make()
            ->collection('actors')
            ->slug('local-rsvp-host')
            ->data(['title' => 'Local Host'])
            ->id('local-host-id');
        $localActor->save();

        $externalActor = Entry::make()
            ->collection('actors')
            ->slug('alice')
            ->data([
                'title' => 'Alice Wonder',
                'name' => 'Alice Wonder',
                'handle' => '@alice@mastodon.social',
                'avatar' => 'https://mastodon.social/avatars/alice.png',
                'activitypub_id' => 'https://mastodon.social/users/alice',
            ])
            ->id('alice-actor-id');
        $externalActor->save();

        // 3. Test Accept (RSVP Going) via ActivityDispatcher
        \Ethernick\ActivityPubCore\Services\ActivityDispatcher::registerController(\Ethernick\ActivityPubEvents\Http\Controllers\EventController::class);

        $acceptPayload = [
            'type' => 'Accept',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => 'https://example.com/events/fedi-conf-2026',
        ];

        $handledAccept = \Ethernick\ActivityPubCore\Services\ActivityDispatcher::dispatch($acceptPayload, $localActor, $externalActor);
        $this->assertTrue($handledAccept);

        // Verify event updated
        $freshEvent = Entry::find($event->id());
        $attendees = $freshEvent->get('attendees', []);
        $this->assertCount(1, $attendees);
        $this->assertEquals('https://mastodon.social/users/alice', $attendees[0]['actor']);
        $this->assertEquals('accepted', $attendees[0]['status']);
        $this->assertEquals('Alice Wonder', $attendees[0]['name']);
        $this->assertEquals('@alice@mastodon.social', $attendees[0]['handle']);

        // Verify EventRsvpReceived was dispatched
        \Illuminate\Support\Facades\Event::assertDispatched(\Ethernick\ActivityPubEvents\Events\EventRsvpReceived::class, function ($e) use ($event) {
            return $e->event->id() === $event->id() && $e->status === 'accepted' && $e->attendee['handle'] === '@alice@mastodon.social';
        });

        // 4. Test Reject (RSVP Cancel / Not Going)
        $rejectPayload = [
            'type' => 'Reject',
            'actor' => 'https://mastodon.social/users/alice',
            'object' => [
                'id' => 'https://example.com/events/fedi-conf-2026',
                'type' => 'Event'
            ],
        ];

        $handledReject = \Ethernick\ActivityPubCore\Services\ActivityDispatcher::dispatch($rejectPayload, $localActor, $externalActor);
        $this->assertTrue($handledReject);

        $freshEvent = Entry::find($event->id());
        $attendees = $freshEvent->get('attendees', []);
        $this->assertCount(1, $attendees);
        $this->assertEquals('rejected', $attendees[0]['status']);

        \Illuminate\Support\Facades\Event::assertDispatched(\Ethernick\ActivityPubEvents\Events\EventRsvpReceived::class, function ($e) use ($event) {
            return $e->event->id() === $event->id() && $e->status === 'rejected';
        });
    }

    public function test_cp_rsvp_endpoint_persists_roster_and_creates_outbox_activity(): void
    {
        // 1. Create Actor for the test user
        $actor = Entry::make()
            ->collection('actors')
            ->slug('john-doe')
            ->data([
                'title' => 'John Doe',
                'name' => 'John Doe',
                'handle' => '@john@testsite.local',
                'activitypub_id' => 'https://testsite.local/@john',
            ])
            ->id('actor-john');
        $actor->save();

        // 2. Create User and associate actor
        $user = \Statamic\Facades\User::make()
            ->email('john@testsite.local')
            ->data(['actors' => ['actor-john']])
            ->makeSuper();
        $user->save();
        $this->actingAs($user);

        // 3. Create Event entry
        $event = Entry::make()
            ->collection('events')
            ->slug('summer-picnic-2026')
            ->data([
                'title' => 'Summer Picnic 2026',
                'start_time' => '2026-08-01T12:00:00Z',
                'activitypub_id' => 'https://remote-instance.social/events/summer-picnic',
                'is_internal' => false,
            ]);
        $event->save();

        // 4. Hit CP RSVP endpoint to RSVP Going
        $response = $this->postJson(route('statamic.cp.activitypub.events.rsvp', ['id' => $event->id()]), [
            'status' => 'accepted',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'status' => 'accepted',
            'user_rsvp' => true,
            'attendee_count' => 1,
        ]);

        // 5. Verify Event entry has the attendee
        $freshEvent = Entry::find($event->id());
        $attendees = $freshEvent->get('attendees', []);
        $this->assertCount(1, $attendees);
        $this->assertEquals('actor-john', $attendees[0]['actor']);
        $this->assertEquals('accepted', $attendees[0]['status']);
        $this->assertEquals('John Doe', $attendees[0]['name']);

        // 6. Verify Outbox activity created in activities collection
        $outboxActivity = Entry::query()
            ->where('collection', 'activities')
            ->where('type', 'Accept')
            ->first();

        $this->assertNotNull($outboxActivity);
        $this->assertEquals(['actor-john'], $outboxActivity->get('actor'));
        $this->assertEquals('https://remote-instance.social/events/summer-picnic', $outboxActivity->get('object'));

        // 7. Toggle RSVP to Not Going
        $cancelResponse = $this->postJson(route('statamic.cp.activitypub.events.rsvp', ['id' => $event->id()]), [
            'status' => 'rejected',
        ]);

        $cancelResponse->assertOk();
        $cancelResponse->assertJson([
            'success' => true,
            'status' => 'rejected',
            'user_rsvp' => false,
            'attendee_count' => 0,
        ]);

        $freshEvent2 = Entry::find($event->id());
        $attendees2 = $freshEvent2->get('attendees', []);
        $this->assertCount(1, $attendees2);
        $this->assertEquals('rejected', $attendees2[0]['status']);

        // Verify Reject activity created in outbox
        $rejectActivity = Entry::query()
            ->where('collection', 'activities')
            ->where('type', 'Reject')
            ->first();

        $this->assertNotNull($rejectActivity);
        $this->assertEquals('https://remote-instance.social/events/summer-picnic', $rejectActivity->get('object'));
    }
}

