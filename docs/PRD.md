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
