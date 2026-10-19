<template>
    <div v-if="eventData" class="mt-4 event-summary">
        <div class="border border-gray-200 dark:border-gray-800 rounded-2xl overflow-hidden bg-white dark:bg-gray-900 shadow-sm hover:shadow-md transition-all flex flex-col">
            <!-- Leaflet Map Preview -->
            <div v-if="hasCoordinates" class="relative">
                <div ref="mapContainer" class="h-48 w-full z-0 bg-gray-50 dark:bg-gray-950"></div>
                <!-- Overlay Location Info -->
                <div class="absolute bottom-0 inset-x-0 p-4 bg-gradient-to-t from-black/80 via-black/40 to-transparent pointer-events-none transition-all">
                    <div class="flex items-end justify-between">
                        <div class="min-w-0 flex-1 mr-4">
                            <h4 class="text-white font-bold truncate leading-tight">{{ location.title || location.osm_name }}</h4>
                            <p class="text-white/60 text-[11px] truncate">{{ location.address }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center text-white border border-white/20 shadow-xl transition-all duration-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        </div>
                    </div>
                </div>
                <!-- Open Map Dropdown Header -->
                <div class="absolute top-3 right-3">
                    <map-directions-dropdown 
                        :location="location" 
                        label="Open Map" 
                        placement="bottom" 
                        variant="pill" 
                    />
                </div>
            </div>

            <!-- Event Information Details -->
            <div class="p-5 flex flex-col space-y-4">
                <div>
                    <span class="text-[10px] bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 font-black px-2 py-0.5 rounded-full uppercase tracking-wider">
                        📅 Event
                    </span>
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mt-2 mb-1 leading-tight">
                        {{ eventData.title }}
                    </h3>
                    <!-- Time Display -->
                    <div class="text-xs text-gray-500 dark:text-gray-400 flex items-center gap-1.5 mt-1 font-medium">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>{{ formattedDateTime }}</span>
                    </div>
                </div>

                <!-- Location Address text if no map coordinates -->
                <div v-if="location && !hasCoordinates" class="text-xs text-gray-500 dark:text-gray-400 flex items-start gap-1.5">
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <div class="min-w-0">
                        <div class="font-bold text-gray-700 dark:text-gray-300 leading-none">{{ location.title || location.osm_name }}</div>
                        <div class="mt-0.5 truncate">{{ location.address }}</div>
                    </div>
                </div>

                <!-- Ticketing pricing & URL -->
                <div class="flex items-center justify-between pt-3 border-t border-gray-100 dark:border-gray-800">
                    <div class="flex items-center gap-1.5 text-sm font-bold text-gray-800 dark:text-gray-200">
                        <span>💵 {{ priceDisplay }}</span>
                    </div>
                    <a v-if="eventData.ticket_url" 
                       :href="eventData.ticket_url" 
                       target="_blank" 
                       rel="noopener" 
                       class="btn btn-default py-1 px-4 text-xs bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 font-bold border border-blue-100 dark:border-blue-900 hover:bg-blue-100 dark:hover:bg-blue-900 flex items-center gap-1.5"
                    >
                        <span>Buy Tickets</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import L from 'leaflet';

export default {
    props: {
        note: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            map: null
        }
    },
    computed: {
        eventData() {
            if (!this.note) return null;
            if (this.note.type === 'events') {
                return this.note;
            }
            return this.note.event || null;
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
            return this.eventData ? (this.eventData.location || null) : null;
        },
        hasCoordinates() {
            return this.location && this.location.latitude && this.location.longitude;
        },
        priceDisplay() {
            if (!this.eventData) return 'Free';
            const price = this.eventData.ticket_price;
            if (price === null || price === undefined || price === '') return 'Free';
            return parseFloat(price).toFixed(2) + ' ' + (this.eventData.ticket_currency || 'CAD');
        },
        formattedDateTime() {
            if (!this.eventData || !this.eventData.start_time) return '';
            
            const start = new Date(this.eventData.start_time);
            const startStr = start.toLocaleDateString(undefined, { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            }) + ' ' + start.toLocaleTimeString(undefined, { 
                hour: '2-digit', 
                minute: '2-digit' 
            });

            if (this.eventData.end_time) {
                const end = new Date(this.eventData.end_time);
                // Check if same day
                const isSameDay = start.toDateString() === end.toDateString();
                const endStr = isSameDay 
                    ? end.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
                    : end.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' ' + end.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
                return `${startStr} – ${endStr}`;
            }

            return startStr;
        }
    },
    mounted() {
        if (this.hasCoordinates) {
            this.$nextTick(() => {
                this.initMap();
            });
        }
    },
    beforeDestroy() {
        if (this.map) {
            this.map.remove();
        }
    },
    methods: {
        initMap() {
            if (!this.$refs.mapContainer) return;

            const lat = parseFloat(this.location.latitude);
            const lng = parseFloat(this.location.longitude);

            if (isNaN(lat) || isNaN(lng)) return;

            this.map = L.map(this.$refs.mapContainer, {
                center: [lat, lng],
                zoom: 14,
                dragging: false,
                touchZoom: false,
                doubleClickZoom: false,
                scrollWheelZoom: false,
                boxZoom: false,
                keyboard: false,
                zoomControl: false,
                attributionControl: false
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(this.map);

            const icon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });

            L.marker([lat, lng], { icon }).addTo(this.map);
        },
        openMapSelector() {
            if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
                // Emit event to select map service using the places addon selector
                Statamic.$activitypub.bus.emit('activitypub:inbox:select-map-service', this.location);
            }
        }
    }
}
</script>

<style scoped>
.event-summary >>> .leaflet-container {
    background: #f8f9fa !important;
}
.event-summary >>> .leaflet-tile-pane {
    filter: grayscale(0.2) contrast(1.1);
}
html.dark .event-summary >>> .leaflet-tile-pane,
html.is-dark .event-summary >>> .leaflet-tile-pane {
    filter: invert(100%) hue-rotate(180deg) brightness(95%) contrast(90%);
}
</style>
