# ActivityPubEvents - Tasks

## Phase 1: Setup & Data Modeling (V1)
- [ ] Scaffold `ActivityPubEvents` addon structure.
- [ ] Define `Event` ActivityPub type and corresponding local Statamic entry structures.
- [ ] Implement Dependency checks for `ActivityPubCore` and `ActivityPubPlaces`.

## Phase 2: CP Experience (V1)
- [ ] Add "Events" CP navigation item.
- [ ] Build Calendar View for CP list.
- [ ] Implement RSVP filtering (Going/Not Going).
- [ ] Add Settings tab within `ActivityPubCore` for Event-specific configurations.
- [ ] Build UI to allow manual "Going/Not Going" toggles on events.

## Phase 3: Federation & Activity JSON (V1)
- [ ] Handle sending Event activities to outbox.
- [ ] Handle receiving Event activities and RSVPs (Accept/Reject invites) in Inbox.
- [ ] Implement "Mastodon-compatible note" fallback generation.
- [ ] Implement Price and Payment Link extensions (based on FEP-d767 / FEP-0ea0 research).

## Phase 4: Frontend & Views (V1)
- [ ] Create fallback Antlers templates for Event display.
- [ ] Include oEmbed / Social Card meta tags.
- [ ] Implement ICS / Google Calendar download mechanisms.
- [ ] Integrate inline map/directions using `ActivityPubPlaces` data.

## Phase 5: Subscriptions (V2)
- [ ] Design and implement tokenized `subscribe-to-cal` URL.
