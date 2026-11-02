<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Listeners;

use Statamic\Events\EntrySaved;
use Statamic\Events\EntryDeleted;
use Statamic\Facades\Entry;
use Statamic\Facades\File;
use Statamic\Facades\YAML;
use Ethernick\ActivityPubCore\Services\ActivityPubUtils;

class EventListener
{
    public function handle(mixed $event): void
    {
        if ($event instanceof EntrySaved) {
            $this->handleEntrySaved($event);
        }

        if ($event instanceof EntryDeleted) {
            $this->handleEntryDeleted($event);
        }
    }

    protected function handleEntrySaved(EntrySaved $event): void
    {
        $entry = $event->entry;
        if ($entry->collection()->handle() !== 'events') {
            return;
        }

        if ($entry->get('is_internal') === false) {
            return;
        }

        // Check compatibility/legacy mode
        $settingsPath = ActivityPubUtils::settingsPath();
        $settings = File::exists($settingsPath) ? YAML::parse(File::get($settingsPath)) : [];
        $compatibility = $settings['events']['compatibility_mode'] ?? $settings['compatibility_mode'] ?? false;

        if (!$compatibility) {
            return;
        }

        // Find or create Note
        $note = Entry::query()
            ->where('collection', 'notes')
            ->where('event_id', $entry->id())
            ->first();

        if (!$note) {
            $note = Entry::make()
                ->collection('notes')
                ->published($entry->published());
        } else {
            $note->published($entry->published());
        }

        // Format content
        $eventTitle = $entry->get('title');
        $eventContent = $entry->get('content') ?? '';
        $startTime = $entry->get('start_time');
        $endTime = $entry->get('end_time');
        $ticketPrice = $entry->get('ticket_price');
        $ticketCurrency = $entry->get('ticket_currency') ?? 'CAD';
        $ticketUrl = $entry->get('ticket_url');

        $formattedContent = "📅 **{$eventTitle}**\n\n";
        if (!empty($eventContent)) {
            $formattedContent .= "{$eventContent}\n\n";
        }

        if ($startTime) {
            $formattedContent .= "⏰ Time: " . \Carbon\Carbon::parse($startTime)->format('F j, Y, g:i a');
            if ($endTime) {
                $formattedContent .= " - " . \Carbon\Carbon::parse($endTime)->format('F j, Y, g:i a');
            }
            $formattedContent .= "\n";
        }

        // Place / Location link
        $placeId = $entry->get('location');
        if (is_array($placeId)) {
            $placeId = $placeId[0] ?? null;
        }
        if ($placeId) {
            $placeEntry = Entry::find($placeId);
            if ($placeEntry) {
                $placeName = $placeEntry->get('title');
                $placeUrl = class_exists(\Ethernick\ActivityPubPlaces\Services\ActivityPubPlacesUtils::class)
                    ? \Ethernick\ActivityPubPlaces\Services\ActivityPubPlacesUtils::osmUrl([
                        'latitude' => $placeEntry->get('latitude') ?? $placeEntry->get('location.latitude'),
                        'longitude' => $placeEntry->get('longitude') ?? $placeEntry->get('location.longitude'),
                        'osm_type' => $placeEntry->get('osm_type'),
                        'osm_id' => $placeEntry->get('osm_id'),
                    ])
                    : $placeEntry->absoluteUrl();
                $formattedContent .= "📍 Location: [{$placeName}]({$placeUrl})\n";
            }
        }

        if ($ticketUrl) {
            $priceDisplay = $ticketPrice !== null ? number_format((float) $ticketPrice, 2) . ' ' . $ticketCurrency : 'Free';
            $formattedContent .= "💵 Price: {$priceDisplay}\n";
            $formattedContent .= "🎟️ Buy Tickets: [Buy]({$ticketUrl})\n";
        }

        $note->data([
            'content' => $formattedContent,
            'actor' => $entry->get('actor'),
            'date' => $entry->get('date') ?? now()->format('Y-m-d H:i'),
            'is_internal' => true,
            'event_id' => $entry->id(),
        ]);

        $note->save();
    }

    protected function handleEntryDeleted(EntryDeleted $event): void
    {
        $entry = $event->entry;
        if ($entry->collection()->handle() !== 'events') {
            return;
        }

        // Delete the corresponding Note entry if it exists
        $notes = Entry::query()
            ->where('collection', 'notes')
            ->where('event_id', $entry->id())
            ->get();

        foreach ($notes as $note) {
            $note->delete();
        }
    }
}
