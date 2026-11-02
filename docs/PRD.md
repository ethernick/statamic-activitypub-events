# ActivityPubEvents - Product Requirements Document

**Document Owner**: Staff Engineer / PM
**Last Updated**: 2026-03-22
**Status**: Draft

---

## 1. Executive Summary
The ActivityPubEvents addon enables Statamic site owners to federate, collect, and manage event objects across the Fediverse. It empowers single event creators or promoters to share events, manage invites (RSVPs), and present beautiful, customizable event pages on their own sites, while interacting seamlessly with the wider ActivityPub network.

## 2. Problem Statement
**Current State**: Statamic users lack a native, standardized way to broadcast events to the Fediverse and collect external event objects natively into their CMS. 
**User Impact**: Event creators and promoters must rely on third-party centralized platforms or separate Fediverse server instances to manage events and RSVPs, fracturing their audience and data ownership.
**Opportunity**: By integrating ActivityPub event types, users can host events directly on their Statamic site, send invites to followers across the Fediverse, collect "Going/Not Going" responses, and provide a single source of truth for their event data.

## 3. Goals & Success Metrics
### User Goals  
- Create and manage events entirely within the Statamic CP.
- Broadcast event announcements and updates to Fediverse followers.
- Receive and tally RSVPs (Going/Not Going) from both internal users and external federated actors.
- Customize the frontend presentation of events without being locked into a rigid UI.

## 4. Target Users
**Primary Users**: Single event creators and promoters (e.g., local meetup organizers, musicians, community leaders) who want to share cool events and manage RSVPs from their own domain.

## 5. Solution Overview
The solution is a modular Statamic addon (`ActivityPubEvents`) that depends on `ActivityPubCore` and `ActivityPubPlaces`.
It will introduce an "Events" section in the CP for managing events, a calendar view, and handling RSVPs. 
It will empower frontend developers by providing fallback templates that can be easily overridden using standard Antlers tags.

## 6. Requirements

### Must Have (P0 - V1)
- **CP Event Management**: An "Events" menu item in the CP with a calendar view of internal and external events.
- **RSVP Tracking**: Filter by "Going/Not Going". Allow manually setting "Going/Not Going" even if not initiated by a federated invite activity.
- **Frontend Developer Experience**: Fallback web templates to show events to the public, designed to be easily overridden by the Statamic developer using Antlers tags.
- **Web Integrations**: Google/ICS download links on the page, inline maps/directions (via `ActivityPubPlaces`), and oEmbed/Social card views.
- **Price/Payment Information**: Include pricing and payment links (integrating with FEP-d767 / FEP-0ea0 considerations).
- **Settings Tab**: An "Events" tab in the ActivityPub Settings, including a formatting template for the "Mastodon-compatible note" fallback when creating an event.

### Phase 4 UI Requirements (Milestone v0.4.0)
- **Inbox Stream Simplification**: Streamline the inbox stream by eliminating tall inline Leaflet maps from event cards. Let the core stream focus on clean social reading.
- **Secondary Action Bar (`inbox-secondary-actions` Hook)**: Introduce a dedicated extensible hook directly below the author name/handle. For events, display 3 flat, borderless, background-free action items (matching the exact typography and stroke styling of the core action counter items):
  - **Calendar Item**: Minimal outline calendar icon + formatted date/time. Clicking opens `EventDetailsStack`.
  - **Location Item**: Minimal outline pin icon + compact city/venue name (stripping full street address noise). Clicking opens `EventDetailsStack` with map view.
  - **Ticket Item**: Minimal outline ticket icon + price. Direct external linkout (`target="_blank"`) if ticket URL exists, otherwise opens `EventDetailsStack`.
- **Event Details Stack (`EventDetailsStack.vue`)**: Full slide-over modal containing:
  - Header: Event Title, Date/Time & Cost indicators.
  - Body: Interactive Leaflet map (with launcher buttons for Apple/Google/OSM maps), full event description, and audited RSVP attendee roster.
  - Footer: "RSVP Going" (toggles to "Not Going" to revoke), "Buy Tickets" (if paid), and "Close".

### Should Have (P1 - V2)
- **CalDAV / Subscription**: A private, tokenized `subscribe-to-cal` URL for syncing with Google Calendar/Apple Calendar.

### Explicitly Out of Scope (For Now)
- Complex multi-ticket tier management or native payment processing within Statamic (relying on external payment links instead).

## 7. Architecture & Technical Considerations
- **Dependencies**: Requires `ActivityPubCore` for base federation and `ActivityPubPlaces` for location management.
- **Routing**: Provide default addon routing for event pages with customizable views, similar to `ActivityPubPlaces` or `ActivityPubLongFormat` approaches.
- **FEP Standards**: Track and potentially implement FEP-d767 (Valueflows) or FEP-0ea0 for payment links in the Activity JSON. 

## 8. Open Questions
- **Payment Link Standardization**: How strictly should we adhere to early-stage FEPs (like FEP-d767) versus creating a custom `statamic:price` namespace for the initial rollout?

---

## 9. Commercial Strategy & "Labs" Versioning Policy

### Pricing Tier: Early Adopter "Labs" Price ($10)
- **Marketplace Listing Price**: **$10** (Early Adopter "Labs" pricing).
- **Price Escalation Expectations**: Emerging Fediverse event standards (W3C AS2 `Event`, Mobilizon RSVP synchronization, FEP-d767/FEP-0ea0 valueflows/payment links) are in active development across the fediverse. As external client adoption standardizes, pricing will adjust to standard commercial rates ($29–$49). Early adopters retain perpetual license and update rights.
- **Semantic Versioning Strategy**: To clearly signal that event federation tracks evolving experimental specifications, releases will remain in the **`v0.3.x`** series (e.g., `v0.3.0`). v1.0.0 will mark the freezing of core event protocols.
- **Multi-Actor Federation**: Inherits multi-user limits from `ActivityPubCore` (Single Actor free / Multi-Actor unlocked with Core Pro $79).

---

## 10. Session History & Rollups


### Session: 2026-09-24 - Phase 4 UI Planning: Inbox Stream Simplification & Secondary Actions
- **Core Hook `inbox-secondary-actions`**: Planned a new extensible hook point in `ActivityPubCore` located directly below the author header. This provides addons with a subtle, non-intrusive container for specialized metadata and interaction items without cluttering the primary fediverse social action bar (Reply, Boost, Quote, Like).
- **Secondary Action Bar Design**: Specified 3 flat, borderless, background-free action items matching the exact typography and stroke styling of the core action counters (`text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white` with 14x14px outline icons, zero pills/badges):
  - **Calendar Item**: Minimal outline calendar icon + formatted date/time. Clicking opens `EventDetailsStack`.
  - **Location Item**: Minimal outline location pin + simplified city/venue string (removing large inline map previews from the stream while respecting `ActivityPubPlaces` boundaries). Clicking opens `EventDetailsStack` with map view.
  - **Ticket Item**: Minimal outline ticket icon + price display. Clicking an external ticket link routes directly to checkout (`target="_blank"`); clicking free events opens `EventDetailsStack`.
- **Event Details Stack Architecture**: Designed `EventDetailsStack.vue` slide-over modal containing:
  - Header: Event Title, Date/Time & Cost indicators.
  - Body: Interactive Leaflet map (with launcher buttons for Apple/Google/OSM maps), full event description, and audited RSVP attendee roster.
  - Footer: "RSVP Going" (toggles to "Not Going" to revoke), "Buy Tickets" (if paid), and "Close".
- **Roadmap Alignment**: Updated `docs/TASKS.md` Phase 4 to break out hook additions, `EventActionBar`, `EventDetailsStack`, and backend attendee federation.

### Session: 2026-09-25 - Task 4.2 Implementation: Secondary Action Bar (`EventActionBar`)
- **Created `EventActionBar.vue` & `EventActionBar.v6.vue`**: Built and registered the secondary action bar into the `inbox-secondary-actions` hook point with dual Vue 2 / Vue 3 compatibility.
- **Minimalist Styling**: Enforced flat, borderless, background-free action counter styling (`text-xs text-gray-500 hover:text-gray-900 dark:hover:text-white transition-colors gap-1.5` with 14x14px outline SVG icons).
- **Interactive Triggers**:
  - Date & Time trigger emits `activitypub:events:view-details` with `{ focus: 'time' }`.
  - Location trigger extracts a clean city or venue string (`name, city`) from address data and emits `activitypub:events:view-details` with `{ focus: 'location' }`.
  - Ticket trigger directs immediately to checkout in a new tab (`target="_blank"`) when `ticket_url` is present; opens `EventDetailsStack` when free or internal.
- **Inbox Stream Cleanup**: De-registered the tall inline Leaflet map `EventSummary.vue` from `inbox-note-content`, reducing stream height by ~250px per event card and eliminating layout bloat.
- **Build & Tests**: Dual bundle compiled cleanly (`dist/v5/` & `dist/v6/`). All 8 PHPUnit integration tests passing (92 assertions, 100%).

### Session: 2026-09-26 - Phase 4.4 Implementation: Federated RSVPs & Attendee Backend
- **Inbound Activity Handling (`EventController` & `EventInboxHandler`)**:
  - Registered `Accept:Event`, `Reject:Event`, `Accept:Reference`, and `Reject:Reference` in `EventController::getHandledActivityTypes()`.
  - Implemented `handleAccept()` and `handleReject()` in [EventController.php](file:///private/srv/statamic/addons/ethernick/ActivityPubEvents/src/Http/Controllers/EventController.php) to update event attendee records (`actor`, `name`, `handle`, `avatar`, `status`, `timestamp`) and persist changes non-destructively.
- **Backend RSVP Toggle (`EventCpController`)**:
  - Implemented `rsvp()` action and registered `POST activitypub/events/{id}/rsvp` in [routes/cp.php](file:///private/srv/statamic/addons/ethernick/ActivityPubEvents/src/routes/cp.php).
  - Handles authenticated local Statamic actor validation, saves/updates the attendee record on the event entry, and creates an `Accept` or `Reject` activity in the `activities` outbox collection for federated broadcast to remote organizers and followers.
- **Event Dispatching**:
  - Created [EventRsvpReceived.php](file:///private/srv/statamic/addons/ethernick/ActivityPubEvents/src/Events/EventRsvpReceived.php) Laravel event dispatched upon both inbound federated RSVPs and manual CP toggles, enabling CRM and notification hooks.
- **Interactive UI Wiring (`EventDetailsStack`)**:
  - Updated both [EventDetailsStack.vue](file:///private/srv/statamic/addons/ethernick/ActivityPubEvents/resources/js/components/inbox/EventDetailsStack.vue) (Vue 2) and [EventDetailsStack.v6.vue](file:///private/srv/statamic/addons/ethernick/ActivityPubEvents/resources/js/components/inbox/EventDetailsStack.v6.vue) (Vue 3) to execute asynchronous POST requests to the RSVP endpoint, updating attendee rosters, counts, and reactive state in real time with toast feedback.
- **Verification & Assets**:
  - Added full test coverage for inbound federated `Accept`/`Reject` handling and CP RSVP outbox dispatch in [EventIntegrationTest.php](file:///private/srv/statamic/addons/ethernick/ActivityPubEvents/src/Tests/EventIntegrationTest.php).
  - Test suite passes with 100% success (11/11 tests, 131 assertions).
  - Recompiled dual Vue 2 and Vue 3 frontend bundles with `npm run build` and republished vendor assets.

### Session: 2026-09-27 - Event Explorer Agenda UI Revamp: Speed of Action & Design System Harmony
- **Controller Enrichment (`EventCpController::index`)**:
  - Enhanced the event listing query to provide complete location objects (matching `ActivityPubPlaces` schema), attendee rosters, attendee count, origin flag (`is_internal`), and authenticated user RSVP status.
- **Graphic Design & Design System Alignment (`EventsDashboard.vue` & `.v6.vue`)**:
  - Replaced basic bordered rows with modern, responsive cards designed to match the inbox stream design language.
  - Added a distinct **Calendar Date Block** showing 3-letter month, bold day numeral, and day-of-week tag with interactive hover triggering `EventDetailsStack`.
  - Added origin pill badges distinguishing `Internal` vs `Federated` events and an attendee count indicator (`X going`).
  - Added an integrated **Secondary Action Meta Bar** matching `EventActionBar` typography and stroke styling (outline clock, location venue/city with clean truncation, and price).
- **Speed of Action Enhancements**:
  - Integrated a **1-Click RSVP Quick-Toggle** directly on each agenda card (firing the backend `/cp/activitypub/events/{id}/rsvp` endpoint optimistically with instant UI feedback and toast confirmation).
  - Provided a direct **Buy Tickets** external button with diagonal arrow for ticketed events.
  - Added a quick **Details** launcher opening `EventDetailsStack` (with map, attendee roster, and full markdown).
  - Added a subtle Statamic entry edit shortcut in the card header for quick CMS modifications.
- **Build & Verification**:
  - Full PHPUnit test suite passing (11/11 tests, 131 assertions).
  - Recompiled dual Vue 2 (`dist/v5/`) and Vue 3 (`dist/v6/`) bundles and republished vendor assets.

### Session: 2026-09-27 - CP Dark Mode Architecture Fix & Agenda Padding Polish
- **Dark Mode Architecture (`cp.css`)**:
  - Resolved white card background issue where Tailwind utility classes like `dark:bg-dark-800` were overridden or absent in Statamic's compiled CSS.
  - Implemented scoped stylesheet rules in `resources/css/cp.css` targeting `.events-dashboard .events-card`, `.calendar-day-cell`, `.calendar-empty-cell`, `.agenda-feed-row`, `.events-header`, and `.events-feed-divider`.
  - Leveraged Statamic theme variables (`var(--theme-color-content-bg)`, `var(--theme-color-gray-900)`, `var(--theme-color-gray-800)`) with `html.dark`, `html.is-dark`, and `.dark` scopes, ensuring dark backgrounds (`#18181b` / `#202023`) in dark mode and clean white in light mode.
- **Agenda Padding & Row Refinement**:
  - Expanded agenda event card rows to `px-6 py-5` with divider lines, eliminating internal card borders and providing generous breathing room.
  - Adjusted calendar empty day cells with `.calendar-empty-cell` opacity and dark backgrounds.
  - Refactored `getDayCellClass()` in both Vue 2 and Vue 3 to toggle `is-selected` and `has-events` classes with high-contrast text and border styling in `cp.css`.
- **Build & Verification**:
  - Compiled dual JS and CSS bundles (`dist/v5/` and `dist/v6/`) with `npm run build`.
  - Published updated vendor assets to `public/vendor/ethernick/activitypub-events/`.
  - Verified 100% pass on PHPUnit test suite (`EventIntegrationTest.php`, 10 tests, 119 assertions).

### Session: 2026-09-27 (Part 2) - Statamic 6 Native CP Design System & Panel Alignment
- **Layout Architecture (`EventsDashboard.v6.vue`)**:
  - Adopted Statamic 6 wide page container `w-full min-w-0 mx-auto max-w-page max-[1220px]:mb-18` matching core CP pages (`cp.utilities/cache`, `cp/collections`).
  - Standardized page header with `text-[25px] leading-[1.25] st-text-legibility font-medium antialiased`, outline calendar icon, and responsive `px-2 sm:px-0 py-6 max-md:pb-8 md:py-8` padding.
- **`@container/panel` Architecture**:
  - **Calendar Panel (1/2)**: Integrated month title and Prev/Next month navigation buttons into `header data-ui-panel-header`. Enclosed calendar days grid within inner card `bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md p-6`.
  - **Agenda / Upcoming Events Panel (1/2)**: Placed dynamic title (`Upcoming Events` or `Agenda: Date`), event count indicator, and `Show All` filter reset button directly into `header data-ui-panel-header`. Enclosed scrollable agenda feed within inner card with borderless styling.
- **Verification & Testing**:
  - Rebuilt dual JS and CSS bundles (`npm run build`).
  - Published vendor assets to `public/vendor/ethernick/activitypub-events/`.
  - Verified 100% test pass on PHPUnit test suite (`EventIntegrationTest.php`, 10/10 tests, 119 assertions).

### Session: 2026-09-28 - Agenda Author Byline Polish, Option 3 (Date Group Headers), & Reusable Post Card Adoption
- **Agenda Layout Refinements & Option 3**:
  - Removed "Internal" pill from event agenda cards to eliminate visual clutter.
  - Implemented **Option 3 (Date Group Headers & Subtitles)**:
    - Removed bulky square calendar date badges (`56px × 56px`) from each individual row.
    - When viewing all upcoming events, introduced `groupedFilteredEvents` displaying events chronologically under sticky date subheaders (`Friday, June 12, 2026 • 2 events`).
    - When a specific calendar day is selected, renders events cleanly directly under the panel header (`Agenda: Jun 12, 2026`) with no redundant per-item date badges.
  - Standardized color tokens to native Statamic CP `gray-*` palette, fixing dark mode font color overrides.
- **Cross-Addon Reusable Presentation Adoption**:
  - Replaced bespoke agenda HTML in `EventsDashboard.vue` and `EventsDashboard.v6.vue` with Core's globally registered `<activity-pub-post-card>` using scoped slots:
    - `#title`: Event title button with edit shortcut link.
    - `#meta`: Formatted event time, venue location launcher, admission pricing badge, and attendee count.
    - `#actions`: 1-click CP RSVP toggle button (`Going` / `RSVP`), external "Buy Tickets" link, and "Details" modal button.
- **Build & Verification**:
  - Compiled dual JS and CSS bundles (`dist/v5/` and `dist/v6/`) with `npm run build`.
  - Published updated vendor assets to `public/vendor/ethernick/activitypub-events/`.



