# ActivityPubEvents Task List & Roadmap

> **Legend**
> - [ ] To Do
> - [/] In Progress
> - [x] Done

---

## 1. Core Architecture, Data Modeling & Federation

### 1.1 Core Event Architecture & Remote Handling
- [x] Scaffold `ActivityPubEvents` addon structure.
- [x] Define `Event` ActivityPub type and corresponding local Statamic entry structures.
- [x] Implement Dependency checks for `ActivityPubCore` and `ActivityPubPlaces`.
- [x] Configure nested routing (`/events/{slug}`).
- [x] Handle sending `Create(Event)` activities to outbox.
- [x] Handle receiving remote `Event` activities (`Create`, `Update`, `Delete`) in Inbox.
- [x] Implement "Mastodon-compatible note" fallback generation with location and ticket links.
- [x] Implement Price and Payment Link extensions (FEP-0ea0 & Schema.org `Offer`).
- [x] Integrate venue location resolution with `ActivityPubPlaces`.

### 1.2 Federated RSVPs & Attendee Backend
- [x] Add `Accept:Event` and `Reject:Event` activity handling in `EventController` & `EventInboxHandler`.
- [x] Implement `attendees` array storage on `Event` entry model (actor URI, status: accepted/rejected, timestamp).
- [x] Add manual RSVP toggle ("Going" / "Not Going") for local Statamic actors with federated outbox dispatch.
- [x] Dispatch `EventRsvpReceived` Laravel event for third-party CRM and notification hooks.

### 1.3 Automated Testing & Sandbox Isolation
- [x] Refactor `ActivityPubEventsInstall.php` to use `ActivityPubUtils::blueprintPath()` for sandbox safety.
- [x] Create comprehensive `FreshInstallationTest.php` to test zero-config installation in isolation.
- [x] Verify full PHPUnit test suite passes with 100% success (8/8 tests, 92 assertions).

---

## 2. Control Panel & Frontend Presentation

### 2.1 CP Calendar, Event Stack & Dual-Build Pipeline
- [x] Add "Events" CP navigation item under ActivityPub section.
- [x] Build Calendar View for CP list with responsive month grid (`EventsDashboard.vue`).
- [x] Build slide-over event creation stack and venue selector (`EventStack.vue`).
- [x] Fix timezone mismatch when saving events from CP inbox drawer (browser ISO conversion).
- [x] Compile dual Vue 2 / Vue 3 production CP bundles.

### 2.2 Secondary Action Bar (`EventActionBar.vue`)
- [x] Add `inbox-secondary-actions` hook point in `ActivityPubCore` (`InboxNote.vue`, `InboxNote.v6.vue`, `InboxActivity.vue`, and `InboxActivity.v6.vue`) directly below author header.
- [x] Ensure hook loader forwards note/activity data, permissions, and event buses cleanly to extension components.
- [x] Build `EventActionBar.vue` component registered to `inbox-secondary-actions` in `ActivityPubEvents`.
- [x] Design specification: **Borderless & background-free** flat action items matching the exact typography and stroke styling of the core action bar (`text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors gap-1.5`, zero pills/badges).
- [x] Implement **Calendar Item**: Minimal outline calendar icon + formatted date/time. Click opens `EventDetailsStack`.
- [x] Implement **Location Item**: Minimal outline pin icon + simplified city/venue label (`city` or `street, city` without full multi-line dumping). Click opens `EventDetailsStack` with map view.
- [x] Implement **Ticket Item**: Minimal outline ticket icon + `Free` or formatted price (`$25.00 CAD`).
  - If event has `ticket_url`: Opens external purchase page directly in new tab with subtle `↗` external icon.
  - If event is free / no external link: Opens `EventDetailsStack`.
- [x] Remove inline Leaflet map preview from inbox stream (`EventSummary.vue`) to eliminate stream clutter and respect `ActivityPubPlaces` boundaries.

### 2.3 Interactive Event Details Stack (`EventDetailsStack.vue`)
- [x] Build `EventDetailsStack.vue` slide-over modal:
  - **Header**: Event Title, prominent Date/Time badge, and Cost tag in top-right.
  - **Body - Location & Map**: Embedded Leaflet map preview with location pin + unified "Open Map ▾" pill dropdown (Google Maps, Apple Maps, OpenStreetMap via `ActivityPubPlaces`). Clean location name & address strip below map without redundant footer buttons.
  - **Body - Content**: Full event body/markdown content.
  - **Body - Attendees**: Audited list of RSVP attendees (avatars, handles, and attendance status).
  - **Footer Actions**:
    - "RSVP Going" toggle (switches to "Not Going" to revoke).
    - "Buy Tickets" button (if paid with ticket URL).
    - "Close" button.
- [x] Wire `EventInboxModals.vue` and `EventInboxModals.v6.vue` to mount `EventDetailsStack` responding to `activitypub:events:view-details`.
- [x] Support dual Vue 2 (`.vue`) and Vue 3 (`.v6.vue`) builds with proper teardown/unmount lifecycle.

### 2.4 Agenda Feed & Dashboard Layout Polish
- [x] Streamlined agenda event card: removed "Internal" pill, positioned Title prominently first, and placed the author byline (icon, name, handle) directly underneath.
- [x] Implemented Option 3 (Date Group Headers & Subtitles):
  - Removed bulky square calendar date badges from individual item rows.
  - When "Show All" / upcoming events are viewed, grouped events chronologically (`groupedFilteredEvents`) with sticky date subheaders (`Friday, June 12, 2026`).
  - When an individual date is selected on the calendar, renders the direct event list under the panel header (`Agenda: Jun 12, 2026`) without redundant date blocks.
- [x] Refactored agenda item rendering to consume Core's `<activity-pub-post-card>` with `#title`, `#meta`, and `#actions` scoped slots.
- [x] Added dedicated top spacing (`mt-4 pt-1.5`) to agenda action footer, separating RSVP/Going and ticket buttons from the datetime line.
- [x] Standardized all colors to native Statamic CP `gray-*` tokens.
- [x] Refactored `EventCpController::index()` to delegate to `ActivityPubUtils::transformEntryForFeed()`, removing ~70 lines of duplicate actor resolution and ensuring consistent Markdown parsing.
- [x] Enriched `ActivityPubEventsServiceProvider`'s `events` transform hook with `price_display`, `location_name`, `edit_url`, `slug`, and `is_major`.
- [x] Suppressed redundant secondary action bars on agenda cards via `<template #secondary-actions></template>`.
- [x] Maintained strict parity across Vue 2 (`EventsDashboard.vue`) and Vue 3 (`EventsDashboard.v6.vue`).

---

## 3. Packaging, Developer Standards & Documentation

### 3.1 Package Metadata & Standards
- [x] Standardize `composer.json` for Packagist and Statamic Marketplace (`statamic-addon`, proprietary/commercial source-available license, links, dependencies).

### 3.2 Developer Guides & Marketplace Documentation
- [x] Overhaul `README.md` with official Statamic 5/6, PHP, Fediverse, and MIT/Commercial badges, architecture diagram, and Quick Start.
- [x] Author `docs/INSTALLATION.md` covering system requirements, automated setup, and sample Antlers event templates.
- [x] Author `docs/TROUBLESHOOTING.md` covering diagnostic flowcharts, timezone UTC alignment, and FEP payment links.
- [x] Author `docs/EVENTS_GUIDE.md` detailing W3C AS2 `Event` schema and FEP-0ea0 links.
- [x] Author `docs/HOOKS.md` detailing backend outbox/transform filters and CP Vue hook points.
- [x] Author `docs/MARKETPLACE_LAUNCH.md` with 1200x630 hero banner specs, 4 screenshot briefs (featuring Mastodon Federation Proof), 15s demo video storyboard, and marketing copy pack ($10 Labs price, v0.3.0).

---

## 4. Active Release Punchlist (Pre-Launch Verification)

- [ ] **Task 4.1: Tag Release**
  - [ ] Tag release **`v0.3.0`** (Labs milestone) on GitHub.
- [ ] **Task 4.2: Packagist Registration**
  - [ ] Register repository on Packagist (`ethernick/statamic-activitypub-events`).
  - [ ] Configure GitHub webhook for automatic package sync.
- [ ] **Task 4.3: Statamic Marketplace Listing**
  - [ ] Configure Statamic Marketplace listing:
    - Price: **$10** (Early Adopter "Labs" price, marked to scale to $29–$49 as event specs mature).
    - Version: **0.3.x**.
  - [ ] Capture CP screenshots according to updated `MARKETPLACE_LAUNCH.md` specifications (01 Calendar, 02 Create Stack, 03 Mastodon Post Proof, 04 Antlers View).
  - [ ] Submit addon listing on the Statamic Marketplace portal.

---

## 5. Future Roadmap (Post-v1.0)

- [ ] **Recurring Events Engine**: RFC 5545 RRULE support for weekly, monthly, and annual federated events.
- [ ] **iCal (.ics) Ingress & Feed Generation**: Native `.ics` subscription endpoints for calendar client synchronization.
- [ ] **Multi-Tier Ticketing**: Multiple price points, early-bird limits, and promo code extensions.
