# Troubleshooting & Diagnostics: ActivityPubEvents

This guide provides targeted diagnostics, log interpretation, and resolution workflows for issues encountered while federating events, handling RSVPs, and synchronizing ticketing metadata.

---

## 1. Quick Diagnostic Flowchart

```
Issue Detected
  ├── Outbound Event Not Appearing on Mastodon/Mobilizon?
  │     ├── Inspect queue status: `php artisan queue:monitor activitypub-outbox`
  │     └── Verify `startTime` is formatted in ISO 8601 UTC in JSON payload
  ├── Timezone Skew (Event displays wrong time on remote platforms)?
  │     ├── Check `config/app.php` timezone vs local server time
  │     └── Confirm date string format in event entry YAML
  ├── Ticket Link Not Recognized as FEP-0ea0 Payment Link?
  │     └── Check that `ticket_url` begins with valid `https://`
  └── RSVPs from Mobilizon Not Registering in Statamic?
        ├── Check `activitypub-inbox` queue for incoming `Accept(Event)`
        └── Verify event `actor` URL matches remote server query
```

---

## 2. Common Issues & Root Causes

### Issue A: Event Times Off by Several Hours on Mastodon

**Symptoms:**
- An event created for 7:00 PM local time displays as 12:00 PM or next-day on remote Fediverse servers.

**Root Causes & Resolution:**
1. **Application Timezone Misalignment**:
   - ActivityPub federates times strictly in UTC (`2026-07-15T02:00:00Z`).
   - If Statamic’s `config('app.timezone')` is set to `UTC` while the editor entered times intended for `America/Vancouver`, the parser assumes the input was already UTC.
   - **Fix**: Set `'timezone' => 'America/Vancouver'` (or your local timezone) in `config/app.php`. The addon's `EventOutboxHandler` will handle the UTC conversion automatically.

---

### Issue B: Ticketing Links Not Interactive on Mastodon

**Symptoms:**
- The event's ticket price and purchase link do not show up as a clickable action on Mastodon or mobile apps.

**Root Causes & Resolution:**
1. **Mastodon Lack of Native Event UI**:
   - Mastodon does not have a native calendar or event card view. It renders events via the addon's **Fallback Note Hook**.
   - Ensure the outbox transform hook in `ActivityPubEventsServiceProvider` is registered. It appends the ticket link and price directly to the note text body and adds a Schema.org `Offer` link attachment for compliant clients.
2. **Missing FEP-0ea0 Link Rel**:
   - Verify that the serialized JSON contains:
     ```json
     "attachment": [
       {
         "type": "Link",
         "rel": "payment",
         "href": "https://tickets.example.com/checkout",
         "name": "Buy"
       }
     ]
     ```

---

### Issue C: Linked Location/Venue Missing from Outbound Event

**Symptoms:**
- The event publishes successfully, but the remote client shows no venue name or coordinates.

**Root Causes & Resolution:**
1. **ActivityPubPlaces Dependency**:
   - `ActivityPubEvents` delegates location geocoding and OSM metadata to `ActivityPubPlaces`.
   - Ensure the `location` field on the event entry points to a valid entry in the `places` collection.
2. **Location Array Dereferencing**:
   - If the relationship field saves as an array `['place-uuid']`, ensure latest `ActivityPubEvents` is installed, which safely dereferences the first element.

---

## 3. Useful Artisan CLI Commands

| Command | Purpose |
| :--- | :--- |
| `php artisan activitypub:events:install` | Re-run collection and blueprint scaffolding |
| `php artisan queue:failed` | Inspect failed outbound event federation jobs |
| `php artisan queue:retry all` | Retry failed federation jobs |

---

## 4. Verbose Logging

To trace outgoing event activities and inbound RSVP deliveries:

```bash
tail -f storage/logs/laravel.log | grep -E "EventOutboxHandler|EventInboxHandler|EventListener"
```
