# Installation & Setup Guide: ActivityPubEvents

This guide details the complete installation, automated configuration, environment verification, and production operational practices for the **ActivityPubEvents** addon (`ethernick/statamic-activitypub-events`).

---

## 1. System Requirements

Ensure the target Statamic deployment meets the following baseline dependencies:

| Component | Requirement | Notes |
| :--- | :--- | :--- |
| **PHP** | `^8.2` | With `ext-ctype`, `ext-json`, `ext-curl`, and `ext-openssl` enabled |
| **Statamic CMS** | `^5.0 \| ^6.0` | Compatible with Statamic 5.x and 6.x |
| **ActivityPubCore** | `^1.0 \|\| dev-main` | Required foundation addon (`ethernick/activitypub-core`) |
| **ActivityPubPlaces** | `^0.3.0 \|\| dev-main` | Required for OSM venue geocoding and location fields |
| **Queue Worker** | Running (database / redis) | Required for asynchronous delivery and RSVP processing |

---

## 2. Composer Installation

Install the package via Composer into your Statamic root directory:

```bash
composer require ethernick/statamic-activitypub-events
```

Composer will detect the Statamic addon and auto-register `ActivityPubEventsServiceProvider`.

---

## 3. Automated Setup Command

Run the interactive installer to generate the `events` content collection, blueprint, and settings configuration:

```bash
php artisan activitypub:events:install
```

### What the Installer Does:
1. **Creates Collection**: Provisions `content/collections/events.yaml` configured with date sorting (`date: true`) and routing (`/events/{slug}`).
2. **Generates Blueprint**: Creates `resources/blueprints/collections/events/event.yaml` with:
   - `title` (Event title)
   - `content` (Markdown description)
   - `start_time` & `end_time` (Date & time picker)
   - `location` (Relationship to `places` collection)
   - `ticket_price`, `ticket_currency`, `ticket_url` (Ticketing & FEP-0ea0 links)
   - `actor` (Author relationship)
3. **Registers Settings**: Enables `events` under `settings/activitypub.yaml`.
4. **CP Navigation**: Adds the **Events** calendar section to the CP sidebar under `ActivityPub`.

---

## 4. Timezone & Scheduling Recommendations

ActivityPub requires dates to be exchanged in **ISO 8601 UTC** format. To ensure seamless synchronization between Statamic and remote Fediverse clients (e.g. Mobilizon, Mastodon):

1. Set your application timezone in `config/app.php`:
   ```php
   'timezone' => 'America/Vancouver', // Or your local timezone
   ```
2. The addon automatically converts dates to UTC when generating ActivityStreams 2.0 payloads (`startTime` and `endTime`).

---

## 5. Frontend Integration (Antlers)

Display event details, venue links, and ticketing buttons on your website using native Statamic Antlers tags.

### Complete Event Page Template (`resources/views/events/show.antlers.html`):

```antlers
<article class="event-entry">
    <header>
        <p class="event-badge">Community Event</p>
        <h1>{{ title }}</h1>
        <div class="event-meta">
            <time datetime="{{ start_time | format('c') }}">
                {{ start_time | format('l, F j, Y @ g:i A') }}
                {{ if end_time }}
                    &ndash; {{ end_time | format('g:i A') }}
                {{ /if }}
            </time>

            {{ if location }}
                <p class="event-location">
                    📍 {{ location:title or location:osm_name }}
                    {{ if location:address }}
                        <small>({{ location:address }})</small>
                    {{ /if }}
                </p>
            {{ /if }}
        </div>
    </header>

    <div class="event-content">
        {{ content }}
    </div>

    {{ if ticket_url }}
        <div class="event-ticketing">
            <p>
                <strong>Admission:</strong>
                {{ if ticket_price && ticket_price > 0 }}
                    {{ ticket_currency or "CAD" }} ${{ ticket_price }}
                {{ else }}
                    Free
                {{ /if }}
            </p>
            <p>
                <a href="{{ ticket_url }}" target="_blank" rel="noopener noreferrer" class="ticket-link">
                    Get Tickets &rarr;
                </a>
            </p>
        </div>
    {{ /if }}
</article>
```


---

## 6. Verification Checklist

Execute these checks after installation to ensure full operability:

- [ ] Run `php artisan activitypub:events:install` without errors.
- [ ] Confirm `content/collections/events.yaml` exists and contains `route: '/events/{slug}'`.
- [ ] Verify **Events** appears in the Statamic CP sidebar under `ActivityPub`.
- [ ] Create a test event with a start date and linked OSM venue.
- [ ] Confirm outbound `Create(Event)` activity is dispatched to queue workers.
- [ ] Verify outgoing Mastodon fallback note includes event summary and ticket link.
