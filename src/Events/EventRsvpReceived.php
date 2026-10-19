<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Events;

use Statamic\Entries\Entry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EventRsvpReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Entry $event,
        public array $attendee,
        public string $status, // 'accepted' | 'rejected'
        public mixed $actor
    ) {}
}
