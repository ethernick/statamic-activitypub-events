<?php

declare(strict_types=1);

namespace Ethernick\ActivityPubEvents\Http\Handlers;

use Ethernick\ActivityPubCore\Contracts\StoreHandlerInterface;
use Illuminate\Http\Request;
use Statamic\Contracts\Entries\Entry as EntryContract;

class EventStoreHandler implements StoreHandlerInterface
{
    public function store(Request $request): EntryContract
    {
        throw new \Exception('Event storage not yet implemented');
    }
}
