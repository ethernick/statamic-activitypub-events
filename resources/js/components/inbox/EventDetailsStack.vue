<template>
    <inbox-stack 
        :open="open" 
        :title="eventTitle" 
        @closed="handleClosed"
    >
        <div v-if="event" class="space-y-6">
            <!-- Header Meta Highlights: Prominent display without boxing or borders -->
            <div class="flex items-center flex-wrap justify-between gap-4 pb-2 border-b border-gray-100 dark:border-gray-800">
                <!-- Date / Time -->
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 stroke-current flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 leading-tight">Date & Time</div>
                        <div class="text-base font-bold text-gray-900 dark:text-white leading-snug">{{ formattedDateTime }}</div>
                    </div>
                </div>

                <!-- Price Display -->
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-gray-400 dark:text-gray-500 stroke-current flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                    </svg>
                    <div>
                        <div class="text-[10px] font-black uppercase tracking-wider text-gray-400 dark:text-gray-500 leading-tight">Admission</div>
                        <div class="text-base font-bold text-gray-900 dark:text-white leading-snug">{{ priceDisplay }}</div>
                    </div>
                </div>
            </div>

            <!-- 1. Event Description / Content (About This Event) -->
            <div class="space-y-2">
                <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">About This Event</h4>
                <div v-if="eventBody" class="prose dark:prose-invert max-w-none text-sm leading-relaxed text-gray-800 dark:text-gray-200" v-html="eventBody"></div>
                <div v-else class="text-sm italic text-gray-400">No additional description provided.</div>
            </div>

            <!-- 2. Location & Map Preview (Matching InboxPlaceMap Header Style) -->
            <div v-if="location" class="border border-gray-200 dark:border-gray-800 rounded-xl overflow-hidden bg-white dark:bg-gray-900 shadow-sm flex flex-col">
                <!-- Map Header Strip -->
                <div class="flex items-center justify-between px-3 py-2 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-2 min-w-0 mr-2">
                        <svg class="w-3.5 h-3.5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span class="text-xs font-medium text-gray-700 dark:text-gray-300 truncate">
                            {{ location.title || location.osm_name || 'Location' }}
                        </span>
                    </div>
                    <map-directions-dropdown 
                        v-if="hasCoordinates"
                        :location="location" 
                        label="Open Map" 
                        placement="bottom" 
                        variant="text" 
                    />
                </div>

                <!-- Leaflet Map Container -->
                <div v-if="hasCoordinates" class="relative">
                    <div ref="detailsMapContainer" class="h-56 w-full z-0 bg-gray-100 dark:bg-gray-950"></div>
                </div>

                <!-- Location Address Info (Below Map) -->
                <div v-if="location.address" class="px-4 py-3 bg-white dark:bg-gray-900 text-xs text-gray-500 dark:text-gray-400 border-t border-gray-100 dark:border-gray-800">
                    <div class="font-bold text-gray-800 dark:text-gray-200">{{ location.title || location.osm_name }}</div>
                    <div class="mt-0.5 leading-relaxed">{{ location.address }}</div>
                </div>
            </div>

            <!-- 3. Attendee Roster Section -->
            <div class="space-y-3 pt-4 border-t border-gray-100 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-black uppercase tracking-wider text-gray-400 dark:text-gray-500">
                        Attendees ({{ attendeeCount }})
                    </h4>
                    <span v-if="isUserRsvpd" class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        You're attending
                    </span>
                </div>

                <!-- Attendee List / Empty State -->
                <div v-if="attendees.length > 0" class="flex flex-wrap gap-2">
                    <div 
                        v-for="(attendee, idx) in attendees" 
                        :key="idx"
                        class="inline-flex items-center gap-2 p-1.5 pr-3 rounded-full bg-gray-100 dark:bg-gray-800 text-xs text-gray-700 dark:text-gray-300"
                    >
                        <div class="w-6 h-6 rounded-full bg-gray-300 dark:bg-gray-700 flex items-center justify-center text-[10px] font-bold overflow-hidden">
                            <img v-if="attendee.avatar" :src="attendee.avatar" :alt="attendee.name || attendee.handle" class="w-full h-full object-cover" />
                            <span v-else>{{ (attendee.name || attendee.handle || 'A').charAt(0).toUpperCase() }}</span>
                        </div>
                        <span class="font-medium truncate max-w-[140px]">{{ attendee.name || attendee.handle }}</span>
                    </div>
                </div>
                <div v-else class="text-xs text-gray-400 italic bg-gray-50 dark:bg-gray-900/30 rounded-lg p-3 border border-dashed border-gray-200 dark:border-gray-800">
                    Be the first to RSVP for this event!
                </div>
            </div>
        </div>

        <!-- Footer Actions Slot: Close, RSVP (transparent/outline differentiated; green once clicked), Buy Tickets (primary) -->
        <template #footer-end>
            <!-- 1. Close Button (Clean neutral CP button) -->
            <button 
                type="button" 
                @click="handleClosed"
                class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 shadow-sm px-4 h-10 text-sm rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                Close
            </button>

            <!-- 2. RSVP Button (Transparent outline style with brand accent to differentiate from Close; turns green/success when Going) -->
            <button 
                type="button" 
                @click="toggleRsvp"
                :class="[
                    'relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline px-4 h-10 text-sm gap-2 rounded-lg border transition-all',
                    isUserRsvpd 
                        ? 'bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 dark:bg-emerald-600 dark:border-emerald-600 shadow-sm' 
                        : 'bg-transparent hover:bg-blue-50/50 text-blue-600 border-blue-300 hover:border-blue-400 dark:text-blue-400 dark:border-blue-800 dark:hover:bg-blue-950/30'
                ]"
            >
                <svg v-if="isUserRsvpd" class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <svg v-else class="w-4 h-4 stroke-current opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>{{ isUserRsvpd ? 'Going' : 'RSVP' }}</span>
            </button>

            <!-- 3. Buy Tickets Primary Button (Matching Statamic primary button tokens) -->
            <a 
                v-if="event && event.ticket_url" 
                :href="event.ticket_url" 
                target="_blank" 
                rel="noopener noreferrer"
                class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline disabled:[&_svg]:opacity-30 disabled:cursor-not-allowed [&_svg]:shrink-0 dark:[&_svg]:text-white bg-linear-to-b from-primary/90 to-primary hover:bg-primary-hover text-white disabled:opacity-60 disabled:text-white dark:disabled:text-white border border-primary-border shadow-ui-md inset-shadow-2xs inset-shadow-white/25 dark:inset-shadow-black/20 disabled:inset-shadow-none dark:disabled:inset-shadow-none [&_svg]:text-white [&_svg]:opacity-60 px-4 h-10 text-sm gap-2 rounded-lg ml-1"
            >
                <span>Buy Tickets</span>
                <span v-if="priceDisplay && priceDisplay !== 'Free'" class="opacity-90 font-normal">({{ priceDisplay }})</span>
                <svg class="w-3.5 h-3.5 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                </svg>
            </a>
        </template>
    </inbox-stack>
</template>

<script>
import L from 'leaflet';

export default {
    name: 'EventDetailsStack',
    props: {
        open: {
            type: Boolean,
            default: false
        },
        event: {
            type: Object,
            default: null
        },
        note: {
            type: Object,
            default: () => ({})
        },
        focus: {
            type: String,
            default: 'overview'
        }
    },
    data() {
        return {
            map: null,
            marker: null,
            isUserRsvpd: false,
            localAttendees: [],
            isSubmittingRsvp: false
        };
    },
    computed: {
        eventTitle() {
            if (!this.event) return 'Event Details';
            return this.event.title || 'Event Details';
        },
        osmUrl() {
            if (typeof Statamic !== 'undefined' && Statamic.$activitypub?.places?.getOsmUrl) {
                return Statamic.$activitypub.places.getOsmUrl(this.location);
            }
            if (!this.location) return 'https://www.openstreetmap.org';
            const lat = this.location.latitude || this.location.lat;
            const lon = this.location.longitude || this.location.lon;
            return lat && lon ? `https://www.openstreetmap.org/?mlat=${lat}&mlon=${lon}#map=17/${lat}/${lon}` : 'https://www.openstreetmap.org';
        },
        location() {
            return this.event ? (this.event.location || null) : null;
        },
        hasCoordinates() {
            if (!this.location) return false;
            const lat = this.location.latitude || this.location.lat;
            const lon = this.location.longitude || this.location.lon;
            return lat !== undefined && lat !== null && lon !== undefined && lon !== null && !isNaN(parseFloat(lat)) && !isNaN(parseFloat(lon));
        },
        priceDisplay() {
            if (!this.event) return 'Free';
            const price = this.event.ticket_price;
            if (price === null || price === undefined || price === '' || parseFloat(price) === 0) {
                return 'Free';
            }
            return parseFloat(price).toFixed(2) + ' ' + (this.event.ticket_currency || 'CAD');
        },
        formattedDateTime() {
            if (!this.event || !this.event.start_time) return '';
            
            const start = new Date(this.event.start_time);
            const startStr = start.toLocaleDateString(undefined, { 
                weekday: 'short',
                month: 'short', 
                day: 'numeric',
                year: 'numeric'
            }) + ' at ' + start.toLocaleTimeString(undefined, { 
                hour: 'numeric', 
                minute: '2-digit' 
            });

            if (this.event.end_time) {
                const end = new Date(this.event.end_time);
                const isSameDay = start.toDateString() === end.toDateString();
                const endStr = isSameDay 
                    ? end.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
                    : end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }) + ' at ' + end.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                return `${startStr} – ${endStr}`;
            }

            return startStr;
        },
        eventBody() {
            if (!this.event) return '';
            return this.event.content_html || this.event.content || (this.note && (this.note.content_html || this.note.content)) || '';
        },
        attendees() {
            if (this.localAttendees.length > 0) {
                return this.localAttendees;
            }
            if (this.event && Array.isArray(this.event.attendees)) {
                return this.event.attendees;
            }
            return [];
        },
        attendeeCount() {
            return this.attendees.length;
        }
    },
    watch: {
        open(isOpen) {
            if (isOpen) {
                this.$nextTick(() => {
                    this.initMap();
                });
            } else {
                this.destroyMap();
            }
        },
        event: {
            immediate: true,
            handler(newEvent) {
                if (newEvent && Array.isArray(newEvent.attendees)) {
                    this.localAttendees = [...newEvent.attendees];
                } else {
                    this.localAttendees = [];
                }
                this.isUserRsvpd = Boolean(newEvent && newEvent.user_rsvp);
            }
        }
    },
    beforeDestroy() {
        this.destroyMap();
    },
    methods: {
        initMap() {
            if (!this.hasCoordinates || !this.$refs.detailsMapContainer) {
                return;
            }

            this.destroyMap();

            const lat = parseFloat(this.location.latitude || this.location.lat);
            const lon = parseFloat(this.location.longitude || this.location.lon);

            this.map = L.map(this.$refs.detailsMapContainer, {
                zoomControl: true,
                attributionControl: false,
                scrollWheelZoom: false
            }).setView([lat, lon], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(this.map);

            const icon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                shadowSize: [41, 41]
            });

            this.marker = L.marker([lat, lon], { icon }).addTo(this.map);

            setTimeout(() => {
                if (this.map) this.map.invalidateSize();
            }, 150);
            setTimeout(() => {
                if (this.map) this.map.invalidateSize();
            }, 400);
        },
        destroyMap() {
            if (this.map) {
                this.map.remove();
                this.map = null;
                this.marker = null;
            }
        },
        openExternalMap() {
            if (!this.location) return;
            if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
                Statamic.$activitypub.bus.emit('activitypub:inbox:select-map-service', this.location);
            }
        },
        toggleRsvp() {
            if (!this.event || this.isSubmittingRsvp) return;

            const targetStatus = !this.isUserRsvpd ? 'accepted' : 'rejected';
            const eventId = this.event.id || this.event.slug || this.event.activitypub_id;

            if (!eventId) {
                if (typeof Statamic !== 'undefined' && Statamic.$toast) {
                    Statamic.$toast.error('Unable to RSVP: Event ID is missing.');
                }
                return;
            }

            this.isSubmittingRsvp = true;

            const url = typeof cp_url === 'function' 
                ? cp_url(`activitypub/events/${eventId}/rsvp`) 
                : `/cp/activitypub/events/${eventId}/rsvp`;

            const axiosInstance = this.$axios || (typeof axios !== 'undefined' ? axios : null);

            if (!axiosInstance) {
                // Fallback optimistic toggle
                this.isUserRsvpd = !this.isUserRsvpd;
                this.isSubmittingRsvp = false;
                return;
            }

            axiosInstance.post(url, { status: targetStatus })
                .then(response => {
                    const data = response.data;
                    this.isUserRsvpd = Boolean(data.user_rsvp);
                    if (Array.isArray(data.attendees)) {
                        this.localAttendees = [...data.attendees];
                    }

                    if (typeof Statamic !== 'undefined' && Statamic.$toast) {
                        if (this.isUserRsvpd) {
                            Statamic.$toast.success(data.message || "RSVP'd as Going!");
                        } else {
                            Statamic.$toast.info(data.message || 'RSVP cancelled.');
                        }
                    }

                    if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
                        Statamic.$activitypub.bus.emit('activitypub:events:rsvp', {
                            event: this.event,
                            status: targetStatus,
                            attendees: this.localAttendees
                        });
                    }
                })
                .catch(err => {
                    const msg = err.response?.data?.error || err.response?.data?.message || 'Failed to update RSVP.';
                    if (typeof Statamic !== 'undefined' && Statamic.$toast) {
                        Statamic.$toast.error(msg);
                    }
                })
                .finally(() => {
                    this.isSubmittingRsvp = false;
                });
        },
        handleClosed() {
            this.destroyMap();
            this.$emit('close');
        }
    }
}
</script>
