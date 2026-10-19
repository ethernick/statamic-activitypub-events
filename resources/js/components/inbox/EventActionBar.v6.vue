<template>
    <div v-if="eventData" class="flex items-center flex-wrap gap-4 text-xs text-gray-500 my-1 font-normal">
        <!-- Date / Time -->
        <button 
            type="button" 
            class="group inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors cursor-pointer select-none"
            :title="formattedDateTime"
            @click="openDetails('time')"
        >
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
            <span>{{ formattedDateTime }}</span>
        </button>

        <!-- Location (City / Venue) -->
        <button 
            v-if="locationLabel" 
            type="button" 
            class="group inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors cursor-pointer select-none truncate max-w-[200px]"
            :title="fullLocationAddress"
            @click="openDetails('location')"
        >
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="truncate">{{ locationLabel }}</span>
        </button>

        <!-- Tickets / Pricing -->
        <a 
            v-if="eventData.ticket_url" 
            :href="eventData.ticket_url" 
            target="_blank" 
            rel="noopener noreferrer" 
            class="group inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors cursor-pointer select-none no-underline"
            :title="'Buy Tickets - ' + priceDisplay"
            @click.stop
        >
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
            </svg>
            <span>{{ priceDisplay }}</span>
            <svg class="w-3 h-3 opacity-70 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
            </svg>
        </a>

        <button 
            v-else 
            type="button" 
            class="group inline-flex items-center gap-1.5 text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors cursor-pointer select-none"
            :title="priceDisplay"
            @click="openDetails('tickets')"
        >
            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
            </svg>
            <span>{{ priceDisplay }}</span>
        </button>
    </div>
</template>

<script>
export default {
    name: 'EventActionBar',
    props: {
        note: {
            type: Object,
            default: () => ({})
        },
        activity: {
            type: Object,
            default: null
        },
        permissions: {
            type: Object,
            default: () => ({})
        },
        actors: {
            type: Array,
            default: () => []
        }
    },
    computed: {
        eventData() {
            const source = this.activity || this.note;
            if (!source) return null;
            if (source.type === 'events' || source.collection === 'events') {
                return source;
            }
            return source.event || null;
        },
        location() {
            return this.eventData ? (this.eventData.location || null) : null;
        },
        locationLabel() {
            if (!this.location) return '';
            
            // Prefer title or osm_name
            const name = this.location.title || this.location.osm_name;
            const address = this.location.address || '';
            
            if (name) {
                // If address has a city, try to extract it: "Venue, City"
                const parts = address.split(',').map(p => p.trim());
                if (parts.length > 1 && !name.toLowerCase().includes(parts[1].toLowerCase())) {
                    return `${name}, ${parts[1]}`;
                }
                return name;
            }

            // Fallback: simplified first 2 parts of address
            const addrParts = address.split(',').map(p => p.trim());
            return addrParts.slice(0, 2).join(', ');
        },
        fullLocationAddress() {
            if (!this.location) return '';
            return this.location.address || this.location.title || this.location.osm_name || '';
        },
        priceDisplay() {
            if (!this.eventData) return 'Free';
            const price = this.eventData.ticket_price;
            if (price === null || price === undefined || price === '' || parseFloat(price) === 0) {
                return 'Free';
            }
            return parseFloat(price).toFixed(2) + ' ' + (this.eventData.ticket_currency || 'CAD');
        },
        formattedDateTime() {
            if (!this.eventData || !this.eventData.start_time) return '';
            
            const start = new Date(this.eventData.start_time);
            const startStr = start.toLocaleDateString(undefined, { 
                month: 'short', 
                day: 'numeric' 
            }) + ', ' + start.toLocaleTimeString(undefined, { 
                hour: 'numeric', 
                minute: '2-digit' 
            });

            if (this.eventData.end_time) {
                const end = new Date(this.eventData.end_time);
                const isSameDay = start.toDateString() === end.toDateString();
                const endStr = isSameDay 
                    ? end.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' })
                    : end.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ', ' + end.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
                return `${startStr} – ${endStr}`;
            }

            return startStr;
        }
    },
    methods: {
        openDetails(focus = 'overview') {
            if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
                Statamic.$activitypub.bus.emit('activitypub:events:view-details', {
                    event: this.eventData,
                    note: this.note,
                    focus
                });
            }
        }
    }
}
</script>
