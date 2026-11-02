# Statamic Marketplace Launch Package: ActivityPubEvents

This document contains the complete production launch package for the **ActivityPubEvents** addon on the Statamic Marketplace and Packagist.

---

## 1. Visual Asset Specifications

### A. Marketplace Hero Card & Social Preview

| Dimension | Aspect Ratio | Color Palette | Focal Elements |
| :--- | :--- | :--- | :--- |
| **1200 x 630 px** | ~1.91:1 | Deep Violet (`#2e1065`), Electric Rose (`#f43f5e`), Royal Blue (`#2563eb`), Clean Obsidian | Floating Statamic CP Events calendar grid, active date chicklets in soft grey-tone, connected Mobilizon and Mastodon node pulses, clean typography: *"ActivityPub Events & Federated Calendars for Statamic"* |

---

## 2. Screenshot Capture Briefs

| Filename | Capture Context / Subject | Key Details to Highlight |
| :--- | :--- | :--- |
| `01-events-calendar.png` | Statamic CP -> ActivityPub -> Events | Clean monthly calendar grid with active date markers and responsive view toggles. |
| `02-event-create-stack.png` | CP Slide-Over Stack -> "New Event" | Date/time pickers, location dropdown linking to `places` collection, and ticketing fields ($10 CAD). |
| `03-federated-event-post.png` | Remote Mastodon Timeline View | Real-world federation proof showing event title, localized date/time string, and clickable ticket links. |
| `04-public-event-view.png` | Frontend Antlers Template | Polished event landing page showing dates, venue details, and direct ticket reservation link. |


---

## 3. 15-Second Demo Video Storyboard

| Timestamp | Scene Description | On-Screen Text / Focus |
| :--- | :--- | :--- |
| **0:00 - 0:04** | Author clicks "New Event" in the CP calendar and sets the title, date, and venue. | *"Create federated events directly inside Statamic."* |
| **0:04 - 0:08** | Clicks "Publish". Show the event broadcasting to both Mastodon (as a formatted note) and Mobilizon. | *"Broadcast to Mobilizon, Mastodon, and the open web."* |
| **0:08 - 0:11** | Cut to Mastodon feed: Show the event appear in real-time with dates, venue, and ticketing links. | *"Deliver interactive event posts across the social web."* |
| **0:11 - 0:15** | Quick pan across the CP month calendar showing upcoming dates and agenda lineup. | *"Own your community calendar on the Fediverse."* |

---

## 4. Marketplace Listing Copy Pack

### Short Tagline (120 characters max):
> Publish federated events, connect venues, and broadcast community calendars across the Fediverse.

### Category:
> Social, Content & Publishing, Events

### Price:
> **$10** (Early Adopter "Labs" Pricing — Expected to scale to $29–$49 as fediverse event specs finalize).

### Version:
> **v0.3.0** (Labs)

### Long Description:

```markdown
### Bring Your Community Events to the Decentralized Social Web

The **ActivityPubEvents** addon gives your Statamic site full **W3C ActivityStreams 2.0 Event** capabilities. Host local meetups, webinars, workshops, or festivals and federate them seamlessly across Mastodon, Mobilizon, and the entire Fediverse.

---

### Key Features:

- **Native W3C Event Schema**: Federate events with precise UTC start/end times, summaries, and rich attachments.
- **Mastodon Fallback Notes**: Automatically formats notes with date stamps, location summaries, and ticket links for microblogging timelines.
- **ActivityPub Places Integration**: Connect events directly to OpenStreetMap venues curated via `ActivityPubPlaces`.
- **FEP-0ea0 Payment Links & Ticketing**: Attach ticket prices and direct checkout links without complex on-site payment gateways.
- **CP Calendar & Agenda**: Manage your event schedule in an elegant, responsive Control Panel calendar with monthly and agenda views.
- **Statamic 5 & 6 Ready**: Includes pre-compiled Vue 2.7 and Vue 3.5 Control Panel bundles out of the box.


---

### Quick Installation:

```bash
composer require ethernick/statamic-activitypub-events
php artisan activitypub:events:install
```
```
