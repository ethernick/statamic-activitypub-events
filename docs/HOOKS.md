# Developer Extension & Hooks Guide: ActivityPubEvents

This guide details how developers can extend the **ActivityPubEvents** addon via Laravel events, Core payload hooks, and Control Panel Vue extension points.

---

## 1. Backend Hooks: Outbound Event Payloads

You can customize the serialized ActivityStreams 2.0 `Event` JSON payload before it is signed and broadcast.

### Intercepting Outbound Event Payloads:

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Ethernick\ActivityPubCore\Services\ActivityPubTypes;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Example: Add live streaming URL or custom event tags
        ActivityPubTypes::registerOutboxHook(function ($entry, &$data) {
            if (!$entry || !$entry->collection() || $entry->collection()->handle() !== 'events') {
                return;
            }

            if ($streamUrl = $entry->get('stream_url')) {
                $data['attachment'][] = [
                    'type' => 'Link',
                    'rel' => 'stream',
                    'href' => $streamUrl,
                    'mediaType' => 'application/x-mpegURL',
                    'name' => 'Live Video Stream',
                ];
            }
        });
    }
}
```

---

## 2. Inbound RSVP Event Hooks

Listen for inbound attendee responses (`Accept` / `Reject`) to send notifications, manage external ticketing platforms, or export guest lists.

### Listening for RSVPs:

```php
namespace App\Listeners;

use Ethernick\ActivityPubEvents\Events\EventRsvpReceived;

class SyncAttendeeToCrm
{
    public function handle(EventRsvpReceived $event): void
    {
        $eventEntry = $event->event;   // Statamic Entry instance
        $actorUri = $event->actorUri;  // Federated Actor URI (e.g., https://mastodon.social/users/alice)
        $status = $event->status;      // 'accepted', 'rejected', or 'tentative'

        \Log::info("RSVP [{$status}] received for event: {$eventEntry->get('title')} from {$actorUri}");
    }
}
```

---

## 3. Control Panel Vue Extension Points

Developers can inject custom cards, sidebar tabs, or attendee audit tables into the Control Panel Events calendar.

### Available UI Extension Points:

| Hook ID | Placement | Purpose |
| :--- | :--- | :--- |
| `events-dashboard-header` | Top of CP Events Calendar | Inject custom calendar filters or iCal export buttons |
| `event-stack-details` | Inside Event Slide-Over Stack | Add custom ticketing widgets or check-in scanners |
| `inbox-event-card` | Inbox stream feed | Custom rendering for incoming federated event invites |

### Registering a Vue Hook Component:

```javascript
// resources/js/cp.js
Statamic.booting(() => {
    if (window.Statamic && window.Statamic.$activitypub && window.Statamic.$activitypub.hooks) {
        window.Statamic.$activitypub.hooks.register('events-dashboard-header', {
            id: 'export-calendar-btn',
            priority: 10,
            component: 'ExportCalendarButton',
        });
    }
});
```
