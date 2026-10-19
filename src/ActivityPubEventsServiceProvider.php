<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents;

use Statamic\Providers\AddonServiceProvider;
use Ethernick\ActivityPubCore\Services\ActivityPubTypes;
use Ethernick\ActivityPubEvents\Http\Controllers\EventController;
use Ethernick\ActivityPubEvents\Http\Handlers\EventStoreHandler;
use Ethernick\ActivityPubEvents\Http\Handlers\EventOutboxHandler;
use Ethernick\ActivityPubEvents\Jobs\EventInboxHandler;
use Illuminate\Support\Facades\Event;

class ActivityPubEventsServiceProvider extends AddonServiceProvider
{
    protected $slug = 'activitypub-events';

    protected $routes = [
        'cp' => __DIR__ . '/routes/cp.php',
    ];

    protected $commands = [
        \Ethernick\ActivityPubEvents\Console\Commands\ActivityPubEventsInstall::class,
    ];


    public function boot(): void
    {
        parent::boot();

        // Register ActivityPub Type
        if (class_exists(ActivityPubTypes::class)) {
            ActivityPubTypes::register(
                'Event',
                'Event',
                null,
                EventController::class,
                ['events'],
                EventStoreHandler::class,
                EventOutboxHandler::class,
                EventInboxHandler::class,
                ['start_time', 'end_time', 'location', 'ticket_url', 'ticket_price', 'ticket_currency']
            );

            // Register outbox hook for legacy notes representing events
            ActivityPubTypes::registerOutboxHook(function ($entry, &$data) {
                if (!$entry || !$entry->collection() || $entry->collection()->handle() !== 'notes') {
                    return;
                }

                if ($eventId = $entry->get('event_id')) {
                    $event = \Statamic\Facades\Entry::find($eventId);
                    if ($event && $event->collection()->handle() === 'events') {
                        $ticketUrl = $event->get('ticket_url');
                        if ($ticketUrl) {
                            $ticketPrice = $event->get('ticket_price');
                            $ticketCurrency = $event->get('ticket_currency') ?? 'CAD';

                            // Primary: FEP-0ea0 Payment Links (added as a Link attachment with rel="payment")
                            $attachment = $data['attachment'] ?? [];
                            $attachment[] = [
                                'type' => 'Link',
                                'rel' => 'payment',
                                'href' => $ticketUrl,
                                'mediaType' => 'text/html',
                                'name' => 'Buy',
                            ];
                            $data['attachment'] = $attachment;

                            // Secondary: Schema.org offers
                            $data['offers'] = [
                                'type' => 'Offer',
                                'price' => $ticketPrice !== null ? number_format((float) $ticketPrice, 2, '.', '') : '0.00',
                                'priceCurrency' => $ticketCurrency,
                                'url' => $ticketUrl,
                            ];

                            // Add Schema.org context definition if context exists
                            if (isset($data['@context'])) {
                                if (is_string($data['@context'])) {
                                    $data['@context'] = [$data['@context']];
                                }
                                if (is_array($data['@context'])) {
                                    $hasSchema = false;
                                    foreach ($data['@context'] as $ctx) {
                                        if (is_array($ctx) && isset($ctx['schema'])) {
                                            $hasSchema = true;
                                            break;
                                        }
                                    }
                                    if (!$hasSchema) {
                                        $data['@context'][] = [
                                            'schema' => 'https://schema.org/',
                                            'offers' => 'schema:offers',
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
            });

            // Transform hook for events collection
            ActivityPubTypes::registerTransformHook(function ($entry, &$data) {
                if (!$entry || !$entry->collection() || $entry->collection()->handle() !== 'events') {
                    return;
                }

                $ticketPrice = $entry->get('ticket_price');
                $ticketCurrency = $entry->get('ticket_currency') ?? 'CAD';
                $priceDisplay = 'Free';
                if ($ticketPrice !== null && $ticketPrice !== '' && (float)$ticketPrice > 0) {
                    $priceDisplay = '$' . number_format((float)$ticketPrice, 2) . ' ' . $ticketCurrency;
                }

                $data['title'] = $entry->get('title');
                $data['slug'] = $entry->slug();
                $data['start_time'] = $entry->get('start_time');
                $data['end_time'] = $entry->get('end_time');
                $data['price'] = $ticketPrice;
                $data['ticket_price'] = $ticketPrice;
                $data['ticket_currency'] = $ticketCurrency;
                $data['price_display'] = $priceDisplay;
                $data['ticket_url'] = $entry->get('ticket_url');
                $data['edit_url'] = $entry->editUrl();
                $data['is_major'] = true;

                // Attendees & RSVP roster
                $attendees = $entry->get('attendees', []) ?: [];
                $validAttendees = array_values(array_filter($attendees, fn($a) => ($a['status'] ?? 'accepted') === 'accepted'));
                $data['attendees'] = $validAttendees;
                $data['attendee_count'] = count($validAttendees);

                // User RSVP status (check current user's local actors)
                $user = \Statamic\Facades\User::current();
                $userActorIds = $user ? ($user->get('actors', []) ?: []) : [];
                $userRsvpd = false;
                foreach ($attendees as $att) {
                    if (in_array($att['actor'] ?? null, $userActorIds) && ($att['status'] ?? '') === 'accepted') {
                        $userRsvpd = true;
                        break;
                    }
                }
                $data['user_rsvp'] = $userRsvpd;

                // Resolve location place details
                $placeId = $entry->get('location');
                if (is_array($placeId)) {
                    $placeId = $placeId[0] ?? null;
                }
                $placeName = 'TBD';
                if ($placeId) {
                    $place = \Statamic\Facades\Entry::find($placeId);
                    if ($place) {
                        $placeName = $place->get('title') ?? $place->get('osm_name') ?? 'TBD';
                        $data['location'] = [
                            'id' => $place->id(),
                            'title' => $place->get('title'),
                            'osm_name' => $place->get('osm_name'),
                            'address' => $place->get('address'),
                            'latitude' => $place->get('latitude'),
                            'longitude' => $place->get('longitude'),
                            'osm_type' => $place->get('osm_type'),
                            'osm_id' => $place->get('osm_id'),
                        ];
                    }
                }
                $data['location_name'] = $placeName;
            });

            // Transform hook for notes collection (representing events)
            ActivityPubTypes::registerTransformHook(function ($entry, &$data) {
                if ($entry->collection()->handle() !== 'notes') {
                    return;
                }

                $eventId = $entry->get('event_id');
                $eventEntry = null;

                if ($eventId) {
                    $eventEntry = \Statamic\Facades\Entry::find($eventId);
                }

                if ($eventEntry) {
                    $placeId = $eventEntry->get('location');
                    if (is_array($placeId)) {
                        $placeId = $placeId[0] ?? null;
                    }
                    $locationData = null;
                    if ($placeId) {
                        $place = \Statamic\Facades\Entry::find($placeId);
                        if ($place) {
                            $locationData = [
                                'id' => $place->id(),
                                'title' => $place->get('title'),
                                'osm_name' => $place->get('osm_name'),
                                'address' => $place->get('address'),
                                'latitude' => $place->get('latitude'),
                                'longitude' => $place->get('longitude'),
                                'osm_type' => $place->get('osm_type'),
                                'osm_id' => $place->get('osm_id'),
                            ];
                        }
                    }

                    $attendees = $eventEntry->get('attendees', []) ?: [];
                    $validAttendees = array_values(array_filter($attendees, fn($a) => ($a['status'] ?? 'accepted') === 'accepted'));

                    $user = \Statamic\Facades\User::current();
                    $userActorIds = $user ? ($user->get('actors', []) ?: []) : [];
                    $userRsvpd = false;
                    foreach ($attendees as $att) {
                        if (in_array($att['actor'] ?? null, $userActorIds) && ($att['status'] ?? '') === 'accepted') {
                            $userRsvpd = true;
                            break;
                        }
                    }

                    $data['event'] = [
                        'id' => $eventEntry->id(),
                        'title' => $eventEntry->get('title'),
                        'start_time' => $eventEntry->get('start_time'),
                        'end_time' => $eventEntry->get('end_time'),
                        'ticket_price' => $eventEntry->get('ticket_price'),
                        'ticket_currency' => $eventEntry->get('ticket_currency'),
                        'ticket_url' => $eventEntry->get('ticket_url'),
                        'location' => $locationData,
                        'attendees' => $validAttendees,
                        'attendee_count' => count($validAttendees),
                        'user_rsvp' => $userRsvpd,
                    ];
                }
            });
        }

        // Listen for entry saved and deleted to manage legacy note fallback
        Event::listen(\Statamic\Events\EntrySaved::class, \Ethernick\ActivityPubEvents\Listeners\EventListener::class);
        Event::listen(\Statamic\Events\EntryDeleted::class, \Ethernick\ActivityPubEvents\Listeners\EventListener::class);

        $this->registerNav();
        $this->registerSettings();
        $this->registerAssets();

        // Load views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'activitypub-events');
    }

    protected function registerSettings(): void
    {
        if (class_exists(\Ethernick\ActivityPubCore\Services\ActivityPubSettings::class)) {
            \Ethernick\ActivityPubCore\Services\ActivityPubSettings::registerTab(
                'events',
                'Events',
                'settings-events-tab',
                [
                    'compatibility_mode' => false,
                ]
            );
        }
    }

    protected function registerNav(): void
    {
        // Use a calendar-like icon or fallback to 'calendar' text
        $icon = @file_get_contents(__DIR__ . '/../resources/svg/events.svg') ?: 'calendar';

        if (class_exists(\Ethernick\ActivityPubCore\Services\ActivityPubNav::class)) {
            \Ethernick\ActivityPubCore\Services\ActivityPubNav::register('events', [
                'label' => 'Events',
                'route' => 'activitypub.events.index',
                'icon' => $icon,
                'order' => 16,
                'section' => 'ActivityPub',
            ]);
        }
    }

    protected function registerAssets(): void
    {
        $packageName = 'ethernick/activitypub-events';
        $version = config('statamic.system.version') ?: (class_exists(\Statamic\Statamic::class) ? \Statamic\Statamic::version() : '6.13.0');
        $isV6 = version_compare($version, '6.0.0', '>=');
        $distSubdir = $isV6 ? 'v6' : 'v5';

        $distDir = __DIR__ . "/../dist/{$distSubdir}";
        if (is_dir($distDir)) {
            $this->publishes([
                "$distDir/js/cp.js" => public_path("vendor/$packageName/js/cp.js"),
                "$distDir/css/cp.css" => public_path("vendor/$packageName/css/cp.css"),
            ], 'activitypub-events');
        }

        \Statamic\Statamic::script($packageName, 'cp.js');
        \Statamic\Statamic::style($packageName, 'cp.css');
    }
}
