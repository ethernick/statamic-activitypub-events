<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Http\Handlers;

use Ethernick\ActivityPubCore\Contracts\OutboxHandlerInterface;
use Statamic\Entries\Entry;

class EventOutboxHandler implements OutboxHandlerInterface
{
    /**
     * Enrich the ActivityPub data array with Event-specific fields.
     */
    public function format(array $data, Entry $entry): array
    {
        // 1. Map Start Time and End Time
        if ($startTime = $entry->get('start_time')) {
            $data['startTime'] = \Carbon\Carbon::parse($startTime)->toIso8601String();
        }
        if ($endTime = $entry->get('end_time')) {
            $data['endTime'] = \Carbon\Carbon::parse($endTime)->toIso8601String();
        }

        // 2. Map Nested Location (Place)
        $placeId = $entry->get('location');
        if (is_array($placeId)) {
            $placeId = $placeId[0] ?? null;
        }

        if ($placeId) {
            $placeEntry = \Statamic\Facades\Entry::find($placeId);
            if ($placeEntry && $placeEntry->collection()->handle() === 'places') {
                $data['location'] = [
                    'type' => 'Place',
                    'name' => $placeEntry->get('osm_name') ?? $placeEntry->get('title'),
                    'latitude' => (float) ($placeEntry->get('latitude') ?? $placeEntry->get('location.latitude')),
                    'longitude' => (float) ($placeEntry->get('longitude') ?? $placeEntry->get('location.longitude')),
                ];
                if ($placeEntry->get('address')) {
                    $data['location']['address'] = $placeEntry->get('address');
                }
            }
        }

        // 3. Map Ticketing & Pricing
        $ticketUrl = $entry->get('ticket_url');
        if ($ticketUrl) {
            $ticketPrice = $entry->get('ticket_price');
            $ticketCurrency = $entry->get('ticket_currency') ?? 'CAD';

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

        return $data;
    }
}
