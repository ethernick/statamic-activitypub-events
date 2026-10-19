<template>
    <inbox-stack 
        :open="open" 
        title="New Event" 
        @closed="handleClosed"
    >
        <div class="px-1">
            <!-- Phase 1: Search Location -->
            <div v-show="!selected">
                <p class="text-xs text-gray-500 mb-6">Search for a place to host your event. You can search existing places or find new ones on OpenStreetMap.</p>
                <location-search 
                    :search-url="findUrl"
                    @change="provisionalLocation = $event"
                    @selected="handleSelected" 
                />
            </div>

            <!-- Phase 2: Event Details Form -->
            <transition name="slide-up">
                <div v-if="selected" class="mt-4 space-y-6">
                    <!-- Location Summary (Click to change location) -->
                    <div 
                        @click="changeLocation" 
                        class="group cursor-pointer py-1.5 -mx-1 px-1 rounded-lg hover:bg-gray-100/70 dark:hover:bg-gray-800/60 transition-colors"
                        title="Click to change location"
                    >
                        <div class="flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-gray-400 group-hover:text-primary shrink-0 mt-0.5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-gray-900 dark:text-gray-100 group-hover:text-primary transition-colors">
                                        {{ selected.name }}
                                    </span>
                                    <span v-if="selected.is_internal" class="text-[10px] bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 px-1.5 py-0.5 rounded font-mono uppercase tracking-wider">Internal</span>
                                    <button type="button" @click.stop="changeLocation" class="text-gray-400 hover:text-gray-900 dark:hover:text-white group-hover:text-primary transition-colors ml-auto p-0.5 shrink-0" title="Change Location">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 leading-relaxed break-words">
                                    {{ selected.display_name || selected.address }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actor Selection -->
                    <div v-if="actors.length > 0">
                        <label class="block text-sm font-bold mb-2">Post As</label>
                        <select v-model="form.actor" class="input-text w-full rounded-lg">
                            <option v-for="actor in actors" :key="actor.id" :value="actor.id">{{ actor.name }} ({{ actor.handle }})</option>
                        </select>
                    </div>

                    <!-- Event Title -->
                    <div>
                        <label class="font-bold text-xs mb-1 block">Event Title</label>
                        <input v-model="form.title" type="text" class="input-text w-full rounded-lg" placeholder="e.g. Fediverse Meetup Langley" />
                    </div>

                    <!-- Start & End Times -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="font-bold text-xs mb-1 block">Start Time</label>
                            <input v-model="form.startTime" type="datetime-local" class="input-text w-full rounded-lg" />
                        </div>
                        <div>
                            <label class="font-bold text-xs mb-1 block">End Time (Optional)</label>
                            <input v-model="form.endTime" type="datetime-local" class="input-text w-full rounded-lg" />
                        </div>
                    </div>

                    <!-- Pricing & Ticket URL -->
                    <div class="p-4 bg-gray-50/50 dark:bg-gray-950/20 border border-gray-200 dark:border-gray-800 rounded-xl space-y-4">
                        <h4 class="font-black text-xs uppercase tracking-widest text-gray-400">Tickets & Pricing</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="font-bold text-xs mb-1 block">Price</label>
                                <input v-model.number="form.ticketPrice" type="number" step="0.01" min="0" class="input-text w-full rounded-lg" placeholder="Free" />
                            </div>
                            <div>
                                <label class="font-bold text-xs mb-1 block">Currency</label>
                                <select v-model="form.ticketCurrency" class="input-text w-full rounded-lg">
                                    <option value="CAD">CAD</option>
                                    <option value="USD">USD</option>
                                    <option value="EUR">EUR</option>
                                    <option value="GBP">GBP</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="font-bold text-xs mb-1 block">Ticket / Buy URL</label>
                            <input v-model="form.ticketUrl" type="url" class="input-text w-full rounded-lg" placeholder="https://example.com/tickets" />
                        </div>
                    </div>

                    <!-- Description Content -->
                    <div class="flex flex-col">
                        <label class="block font-bold text-xs mb-1">Event Description</label>
                        <div class="min-h-[200px]">
                            <markdown-fieldtype
                                :value="form.content"
                                :model-value="form.content"
                                :config="{
                                    cheatsheet: true,
                                    container: 'assets',
                                    buttons: ['bold', 'italic', 'unorderedlist', 'orderedlist', 'quote', 'link', 'image', 'table'],
                                    type: 'markdown'
                                }"
                                :meta="{ previewUrl: null }"
                                @input="val => form.content = val"
                                @update:modelValue="val => form.content = val"
                                @update:value="val => form.content = val"
                            />
                        </div>
                    </div>
                </div>
            </transition>
        </div>

        <template #footer-end>
            <button @click="handleClosed" class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline disabled:[&_svg]:opacity-30 disabled:cursor-not-allowed [&_svg]:shrink-0 dark:[&_svg]:text-white bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 shadow-sm px-4 h-10 text-sm rounded-lg dark:bg-gray-800 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-700">Cancel</button>

            <!-- Phase 1: Confirm Location -->
            <button 
                v-if="!selected"
                @click="confirmLocation" 
                class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline disabled:[&_svg]:opacity-30 disabled:cursor-not-allowed [&_svg]:shrink-0 dark:[&_svg]:text-white bg-linear-to-b from-primary/90 to-primary hover:bg-primary-hover text-white disabled:opacity-60 disabled:text-white dark:disabled:text-white border border-primary-border shadow-ui-md inset-shadow-2xs inset-shadow-white/25 dark:inset-shadow-black/20 disabled:inset-shadow-none dark:disabled:inset-shadow-none [&_svg]:text-white [&_svg]:opacity-60 px-4 h-10 text-sm gap-2 rounded-lg ml-2" 
                :disabled="!provisionalLocation"
            >
                Confirm Location
            </button>

            <!-- Phase 2: Post Event -->
            <button 
                v-else
                @click="save" 
                class="relative inline-flex items-center justify-center whitespace-nowrap shrink-0 font-medium antialiased cursor-pointer no-underline disabled:[&_svg]:opacity-30 disabled:cursor-not-allowed [&_svg]:shrink-0 dark:[&_svg]:text-white bg-linear-to-b from-primary/90 to-primary hover:bg-primary-hover text-white disabled:opacity-60 disabled:text-white dark:disabled:text-white border border-primary-border shadow-ui-md inset-shadow-2xs inset-shadow-white/25 dark:inset-shadow-black/20 disabled:inset-shadow-none dark:disabled:inset-shadow-none [&_svg]:text-white [&_svg]:opacity-60 px-4 h-10 text-sm gap-2 rounded-lg ml-2" 
                :disabled="!form.title || !form.startTime || saving"
            >
                <span v-if="saving" class="loading loading-xs mr-2"></span>
                Post Event
            </button>
        </template>
    </inbox-stack>
</template>

<script>
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

export default {
    props: {
        open: Boolean
    },
    data() {
        return {
            selected: null,
            provisionalLocation: null,
            saving: false,
            actors: [],
            form: {
                actor: null,
                title: '',
                startTime: '',
                endTime: '',
                ticketPrice: null,
                ticketCurrency: 'CAD',
                ticketUrl: '',
                content: ''
            },
            map: null,
            marker: null
        }
    },
    computed: {
        findUrl() {
            // Leverage Nominatim search endpoint from ActivityPubPlaces directly
            return cp_url('activitypub/places/find');
        }
    },
    watch: {
        open(val) {
            if (val) {
                this.fetchActors();
            } else {
                this.destroyMap();
            }
        },
        selected(val) {
            if (val) {
                this.$nextTick(() => this.initMap());
            } else {
                this.destroyMap();
            }
        }
    },
    methods: {
        fetchActors() {
            // Leverage actors endpoint from ActivityPubPlaces directly
            this.$axios.get(cp_url('activitypub/places/actors'))
                .then(response => {
                    this.actors = response.data.actors;
                    if (this.actors.length > 0 && !this.form.actor) {
                        this.form.actor = this.actors[0].id;
                    }
                });
        },
        handleSelected(result) {
            this.provisionalLocation = result;
            this.selected = result;
        },
        confirmLocation() {
            if (!this.provisionalLocation) return;
            this.selected = this.provisionalLocation;
        },
        changeLocation() {
            this.selected = null;
        },
        save() {
            if (this.saving || !this.selected || !this.form.title || !this.form.startTime) return;
            this.saving = true;

            let startTime = this.form.startTime;
            if (startTime) {
                const startD = new Date(startTime);
                if (!isNaN(startD.getTime())) {
                    startTime = startD.toISOString();
                }
            }

            let endTime = this.form.endTime;
            if (endTime) {
                const endD = new Date(endTime);
                if (!isNaN(endD.getTime())) {
                    endTime = endD.toISOString();
                }
            }

            const payload = {
                actor: this.form.actor,
                title: this.form.title,
                content: this.form.content,
                start_time: startTime,
                end_time: endTime,
                place: this.selected,
                ticket_price: this.form.ticketPrice,
                ticket_currency: this.form.ticketCurrency,
                ticket_url: this.form.ticketUrl
            };

            this.$axios.post(cp_url('activitypub/events'), payload)
            .then(response => {
                Statamic.$toast.success('Event created and posted successfully!');
                this.$emit('submit-success');
                this.$emit('close');
                this.reset();
            })
            .catch(err => {
                Statamic.$toast.error(err.response?.data?.message || 'Failed to create event.');
            })
            .finally(() => {
                this.saving = false;
            });
        },
        initMap() {
            if (this.map) this.destroyMap();
            if (!this.$refs.mapPreviewContainer) return;

            const lat = this.selected.latitude || this.selected.lat;
            const lon = this.selected.longitude || this.selected.lon;

            if (!lat || !lon) {
                console.error('[ActivityPubEvents] Invalid coordinates for map:', this.selected);
                return;
            }

            this.map = L.map(this.$refs.mapPreviewContainer, {
                zoomControl: false,
                attributionControl: false
            }).setView([lat, lon], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(this.map);
            this.marker = L.marker([lat, lon]).addTo(this.map);

            setTimeout(() => { if (this.map) this.map.invalidateSize(); }, 100);
            setTimeout(() => { if (this.map) this.map.invalidateSize(); }, 500);
        },
        destroyMap() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }
        },
        handleClosed() {
            this.$emit('close');
            this.reset();
        },
        reset() {
            this.selected = null;
            this.provisionalLocation = null;
            this.form.title = '';
            this.form.startTime = '';
            this.form.endTime = '';
            this.form.ticketPrice = null;
            this.form.ticketCurrency = 'CAD';
            this.form.ticketUrl = '';
            this.form.content = '';
        }
    }
}
</script>

<style scoped>
.slide-up-enter-active, .slide-up-leave-active {
    transition: all 0.3s ease-out;
}
.slide-up-enter, .slide-up-leave-to {
    transform: translateY(20px);
    opacity: 0;
}

.loc-brief-box {
    background-color: rgba(239, 246, 255, 0.5); /* blue-50/50 */
    border-color: #bfdbfe; /* blue-200 */
}
.loc-name { color: #1e3a8a; } /* blue-900 */
.loc-address { color: #6b7280; } /* gray-500 */
.internal-tag {
    background-color: #dbeafe; /* blue-100 */
    color: #2563eb; /* blue-600 */
}

/* Dark Mode Overrides */
html.dark .loc-brief-box,
html.is-dark .loc-brief-box,
html.isdark .loc-brief-box {
    background-color: #0a0a0a; /* neutral-950 */
    border-color: #262626; /* neutral-800 */
}
html.dark .loc-name,
html.is-dark .loc-name,
html.isdark .loc-name { color: #f5f5f5; } /* neutral-100 */

html.dark .loc-address,
html.is-dark .loc-address,
html.isdark .loc-address { color: #737373; } /* neutral-500 */

html.dark .internal-tag,
html.is-dark .internal-tag,
html.isdark .internal-tag {
    background-color: #404040; /* neutral-700 */
    color: #f5f5f5; /* neutral-100 */
}
</style>
