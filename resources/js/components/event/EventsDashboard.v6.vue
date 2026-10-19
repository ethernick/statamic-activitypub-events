<template>
    <div class="w-full min-w-0 mx-auto max-w-page max-[1220px]:mb-18 events-dashboard">
        <!-- Page Header -->
        <header class="relative flex flex-wrap items-center justify-between gap-4 px-2 sm:px-0 py-6 max-md:pb-8 md:py-8">
            <h1 class="text-[25px] leading-[1.25] st-text-legibility font-medium antialiased flex items-center gap-2.5 md:flex-1">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Events Explorer</span>
            </h1>
            <div class="flex items-center gap-2">
                <button @click="createNewEvent" class="btn btn-primary flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Event
                </button>
            </div>
        </header>

        <!-- Main Grid Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
            
            <!-- Left: Calendar Panel (1/2 width) -->
            <div class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 [&:has(>[data-ui-panel-header])]:pt-0 focus-none starting-style-transition">
                <header data-ui-panel-header class="px-4.5 py-3 flex items-center justify-between">
                    <button @click="prevMonth" class="btn btn-default px-2.5 py-1 text-xs font-semibold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Prev
                    </button>
                    <h2 class="font-bold text-gray-900 dark:text-gray-100 text-base">{{ monthName }} {{ currentYear }}</h2>
                    <button @click="nextMonth" class="btn btn-default px-2.5 py-1 text-xs font-semibold flex items-center gap-1">
                        Next
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </header>

                <div class="events-card bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md p-6 flex flex-col justify-between">
                    <!-- Days of Week Header -->
                    <div class="grid grid-cols-7 gap-2 text-center text-xs font-black text-gray-400 dark:text-dark-500 uppercase tracking-widest mb-3">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>

                    <!-- Calendar Days Grid -->
                    <div class="grid grid-cols-7 gap-2">
                        <!-- Empty cells before first day of month -->
                        <div v-for="empty in firstDayIndex" :key="'empty-' + empty" class="calendar-empty-cell h-16 rounded-md opacity-40"></div>

                        <!-- Days of Month -->
                        <div 
                            v-for="day in totalDays" 
                            :key="'day-' + day"
                            @click="selectDate(day)"
                            :class="getDayCellClass(day)"
                        >
                            <span :class="getDayNumberClass(day)">{{ day }}</span>
                            <div v-if="hasEvents(day)" class="flex gap-1 items-center justify-end">
                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500 dark:bg-blue-400"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Agenda Panel (1/2 width) -->
            <div class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 [&:has(>[data-ui-panel-header])]:pt-0 focus-none starting-style-transition">
                <header data-ui-panel-header class="px-4.5 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <h2 class="font-bold text-gray-900 dark:text-gray-100">{{ agendaTitle }}</h2>
                        <span v-if="filteredEvents.length > 0" class="text-xs text-gray-500 font-medium bg-gray-100 dark:bg-gray-900 px-2.5 py-0.5 rounded-full border border-gray-200 dark:border-gray-800">
                            {{ filteredEvents.length }}
                        </span>
                    </div>
                    <button v-if="selectedDateStr" @click="clearFilter" class="btn btn-default text-xs py-1 px-2.5">Show All</button>
                </header>

                <div class="events-card bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md p-0 border-0 flex flex-col h-full overflow-hidden">
                    <!-- Scrollable Agenda Events Feed -->
                    <div class="flex-grow overflow-y-auto max-h-[560px]">
                    <div v-if="filteredEvents.length === 0" class="text-center py-16 text-gray-400 dark:text-dark-500">
                        <svg class="w-12 h-12 mx-auto mb-4 opacity-20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <p class="text-sm font-semibold">No events scheduled</p>
                    </div>

                    <!-- Feed List: borderless items with divider lines & generous breathing room -->
                    <!-- Date-filtered view (Single day selected) -->
                    <div v-else-if="selectedDateStr" class="flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
                        <activitypub-post-card
                            v-for="event in sortedFilteredEvents"
                            :key="event.id"
                            :item="event"
                            :readonly="true"
                            :actors="actors"
                        >
                            <template #secondary-actions></template>
                            <template #title="{ item }">
                                <div class="flex items-start justify-between gap-2 mt-1">
                                    <h4 class="font-bold leading-snug text-base min-w-0">
                                        <button 
                                            type="button" 
                                            @click="openDetails(item, 'overview')"
                                            class="agenda-title text-left font-bold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors cursor-pointer"
                                        >
                                            {{ item.title }}
                                        </button>
                                    </h4>
                                    <a 
                                        :href="item.edit_url" 
                                        class="shrink-0 p-1 text-gray-400 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 rounded transition-colors"
                                        title="Edit Entry"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </a>
                                </div>
                            </template>

                            <template #meta="{ item }">
                                <div class="mt-2 flex items-center flex-wrap gap-x-5 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                                    <!-- Time -->
                                    <button 
                                        type="button"
                                        @click="openDetails(item, 'time')"
                                        class="agenda-meta inline-flex items-center gap-1.5 transition-colors cursor-pointer select-none hover:text-gray-900 dark:hover:text-gray-200"
                                        :title="'Event Time: ' + formatEventTime(item.start_time)"
                                    >
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span>{{ formatEventTime(item.start_time) }}</span>
                                    </button>

                                    <!-- Location -->
                                    <button 
                                        v-if="item.location"
                                        type="button"
                                        @click="openDetails(item, 'location')"
                                        class="agenda-meta inline-flex items-center gap-1.5 transition-colors cursor-pointer select-none truncate max-w-[240px] hover:text-gray-900 dark:hover:text-gray-200"
                                        :title="getLocationLabel(item.location)"
                                    >
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span class="truncate">{{ getLocationLabel(item.location) }}</span>
                                    </button>

                                    <!-- Admission -->
                                    <div class="inline-flex items-center gap-1.5 font-medium">
                                        <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                        </svg>
                                        <span :class="isEventPaid(item) ? 'text-gray-800 dark:text-gray-200' : 'text-gray-500 dark:text-gray-400'">
                                            {{ item.price_display }}
                                        </span>
                                    </div>

                                    <!-- Attendees Going count -->
                                    <span v-if="item.attendee_count > 0" class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                                        <svg class="w-3 h-3 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                        </svg>
                                        <span>{{ item.attendee_count }} going</span>
                                    </span>
                                </div>
                            </template>

                            <template #actions="{ item }">
                                <div class="mt-4 pt-1.5 flex items-center justify-between gap-3 text-xs">
                                    <!-- 1-Click Fast RSVP Action -->
                                    <button 
                                        type="button" 
                                        @click="toggleRsvp(item)"
                                        :disabled="item._isUpdatingRsvp"
                                        class="inline-flex items-center gap-1.5 font-semibold transition-colors cursor-pointer disabled:opacity-50"
                                        :class="item.user_rsvp
                                            ? 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300'
                                            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'"
                                        :title="item.user_rsvp ? 'Cancel your RSVP' : 'RSVP Going to this event'"
                                    >
                                        <svg v-if="item.user_rsvp" class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                        <svg v-else class="w-4 h-4 stroke-current opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        <span>{{ item.user_rsvp ? 'Going' : 'RSVP' }}</span>
                                    </button>

                                    <div class="flex items-center gap-4">
                                        <!-- Buy Tickets Direct Linkout -->
                                        <a 
                                            v-if="item.ticket_url" 
                                            :href="item.ticket_url" 
                                            target="_blank" 
                                            rel="noopener noreferrer" 
                                            class="inline-flex items-center gap-1 font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors no-underline"
                                        >
                                            <span>Buy Tickets</span>
                                            <svg class="w-3 h-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>

                                        <!-- Open Details Stack -->
                                        <button 
                                            type="button" 
                                            @click="openDetails(item, 'overview')"
                                            class="inline-flex items-center gap-1 font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors cursor-pointer"
                                        >
                                            <span>Details</span>
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </activitypub-post-card>
                    </div>

                    <!-- Grouped by Date (Upcoming Events - Show All mode) -->
                    <div v-else class="events-grouped-agenda">
                        <div v-for="group in groupedFilteredEvents" :key="group.dateStr">
                            <!-- Sticky Date Header / Subtitle -->
                            <div class="px-5 py-2 bg-gray-50/90 dark:bg-gray-800/80 border-y border-gray-100 dark:border-gray-700/60 text-xs font-bold uppercase tracking-wider text-gray-600 dark:text-gray-300 flex items-center justify-between">
                                <span>{{ group.dateLabel }}</span>
                                <span class="text-[11px] font-semibold text-gray-400 dark:text-gray-500">{{ group.events.length }} {{ group.events.length === 1 ? 'event' : 'events' }}</span>
                            </div>

                            <div class="flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
                                <activitypub-post-card
                                    v-for="event in group.events"
                                    :key="event.id"
                                    :item="event"
                                    :readonly="true"
                                    :actors="actors"
                                >
                                    <template #secondary-actions></template>
                                    <template #title="{ item }">
                                        <div class="flex items-start justify-between gap-2 mt-1">
                                            <h4 class="font-bold leading-snug text-base min-w-0">
                                                <button 
                                                    type="button" 
                                                    @click="openDetails(item, 'overview')"
                                                    class="agenda-title text-left font-bold text-gray-900 dark:text-gray-100 hover:text-blue-600 dark:hover:text-blue-400 transition-colors cursor-pointer"
                                                >
                                                    {{ item.title }}
                                                </button>
                                            </h4>
                                            <a 
                                                :href="item.edit_url" 
                                                class="shrink-0 p-1 text-gray-400 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 rounded transition-colors"
                                                title="Edit Entry"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                </svg>
                                            </a>
                                        </div>
                                    </template>

                                    <template #meta="{ item }">
                                        <div class="mt-2 flex items-center flex-wrap gap-x-5 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                                            <button 
                                                type="button"
                                                @click="openDetails(item, 'time')"
                                                class="agenda-meta inline-flex items-center gap-1.5 transition-colors cursor-pointer select-none hover:text-gray-900 dark:hover:text-gray-200"
                                                :title="'Event Time: ' + formatEventTime(item.start_time)"
                                            >
                                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span>{{ formatEventTime(item.start_time) }}</span>
                                            </button>

                                            <button 
                                                v-if="item.location"
                                                type="button"
                                                @click="openDetails(item, 'location')"
                                                class="agenda-meta inline-flex items-center gap-1.5 transition-colors cursor-pointer select-none truncate max-w-[240px] hover:text-gray-900 dark:hover:text-gray-200"
                                                :title="getLocationLabel(item.location)"
                                            >
                                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                </svg>
                                                <span class="truncate">{{ getLocationLabel(item.location) }}</span>
                                            </button>

                                            <div class="inline-flex items-center gap-1.5 font-medium">
                                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                                </svg>
                                                <span :class="isEventPaid(item) ? 'text-gray-800 dark:text-gray-200' : 'text-gray-500 dark:text-gray-400'">
                                                    {{ item.price_display }}
                                                </span>
                                            </div>

                                            <span v-if="item.attendee_count > 0" class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                                                <svg class="w-3 h-3 text-gray-400 dark:text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                                </svg>
                                                <span>{{ item.attendee_count }} going</span>
                                            </span>
                                        </div>
                                    </template>

                                    <template #actions="{ item }">
                                        <div class="mt-4 pt-1.5 flex items-center justify-between gap-3 text-xs">
                                            <button 
                                                type="button" 
                                                @click="toggleRsvp(item)"
                                                :disabled="item._isUpdatingRsvp"
                                                class="inline-flex items-center gap-1.5 font-semibold transition-colors cursor-pointer disabled:opacity-50"
                                                :class="item.user_rsvp
                                                    ? 'text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300'
                                                    : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100'"
                                                :title="item.user_rsvp ? 'Cancel your RSVP' : 'RSVP Going to this event'"
                                            >
                                                <svg v-if="item.user_rsvp" class="w-4 h-4 stroke-current" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <svg v-else class="w-4 h-4 stroke-current opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                                </svg>
                                                <span>{{ item.user_rsvp ? 'Going' : 'RSVP' }}</span>
                                            </button>

                                            <div class="flex items-center gap-4">
                                                <a 
                                                    v-if="item.ticket_url" 
                                                    :href="item.ticket_url" 
                                                    target="_blank" 
                                                    rel="noopener noreferrer" 
                                                    class="inline-flex items-center gap-1 font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 transition-colors no-underline"
                                                >
                                                    <span>Buy Tickets</span>
                                                    <svg class="w-3 h-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>

                                                <button 
                                                    type="button" 
                                                    @click="openDetails(item, 'overview')"
                                                    class="inline-flex items-center gap-1 font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 transition-colors cursor-pointer"
                                                >
                                                    <span>Details</span>
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </activitypub-post-card>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <activitypub-modals :actors="actors" @submit-success="fetchEvents" />
    </div>
</template>

<script>
export default {
    props: {
        events: {
            type: Array,
            required: true
        },
        actors: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            localEvents: this.events,
            currentMonth: new Date().getMonth(),
            currentYear: new Date().getFullYear(),
            selectedDateStr: null,
            today: new Date()
        }
    },
    watch: {
        events(newVal) {
            this.localEvents = newVal;
        }
    },
    computed: {
        monthName() {
            const names = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
            return names[this.currentMonth];
        },
        firstDayIndex() {
            return new Date(this.currentYear, this.currentMonth, 1).getDay();
        },
        totalDays() {
            return new Date(this.currentYear, this.currentMonth + 1, 0).getDate();
        },
        agendaTitle() {
            if (this.selectedDateStr) {
                const date = new Date(this.selectedDateStr + 'T00:00:00');
                return 'Agenda: ' + date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
            }
            return 'Upcoming Events';
        },
        filteredEvents() {
            if (this.selectedDateStr) {
                return this.localEvents.filter(e => e.start_time && this.getLocalEventDateStr(e.start_time) === this.selectedDateStr);
            }
            return this.localEvents;
        },
        sortedFilteredEvents() {
            return [...this.filteredEvents].sort((a, b) => this.parseDateTime(a.start_time) - this.parseDateTime(b.start_time));
        },
        groupedFilteredEvents() {
            const groups = {};
            this.sortedFilteredEvents.forEach(event => {
                const dateKey = event.start_time ? this.getLocalEventDateStr(event.start_time) : 'tbd';
                if (!groups[dateKey]) {
                    groups[dateKey] = {
                        dateStr: dateKey,
                        dateLabel: event.start_time ? this.formatGroupDateHeader(event.start_time) : 'Upcoming',
                        events: []
                    };
                }
                groups[dateKey].events.push(event);
            });
            return Object.values(groups);
        }
    },
    methods: {
        formatGroupDateHeader(startTime) {
            const date = this.parseDateTime(startTime);
            if (!date) return 'Upcoming';
            return date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
        },
        prevMonth() {
            if (this.currentMonth === 0) {
                this.currentMonth = 11;
                this.currentYear--;
            } else {
                this.currentMonth--;
            }
        },
        nextMonth() {
            if (this.currentMonth === 11) {
                this.currentMonth = 0;
                this.currentYear++;
            } else {
                this.currentMonth++;
            }
        },
        getDateStr(day) {
            return `${this.currentYear}-${String(this.currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        },
        parseDateTime(timeStr) {
            if (!timeStr) return null;
            if (timeStr.includes('Z') || timeStr.includes('+')) {
                return new Date(timeStr);
            }
            // Treat naive YYYY-MM-DD HH:MM as UTC since the server stores dates in UTC
            const normalized = timeStr.replace(' ', 'T') + ':00Z';
            const d = new Date(normalized);
            return isNaN(d.getTime()) ? new Date(timeStr) : d;
        },
        getLocalEventDateStr(startTime) {
            const d = this.parseDateTime(startTime);
            if (!d) return '';
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        },
        hasEvents(day) {
            const dateStr = this.getDateStr(day);
            return this.localEvents.some(e => e.start_time && this.getLocalEventDateStr(e.start_time) === dateStr);
        },
        isToday(day) {
            return this.today.getDate() === day && 
                   this.today.getMonth() === this.currentMonth && 
                   this.today.getFullYear() === this.currentYear;
        },
        selectDate(day) {
            const dateStr = this.getDateStr(day);
            if (this.selectedDateStr === dateStr) {
                this.selectedDateStr = null;
            } else {
                this.selectedDateStr = dateStr;
            }
        },
        clearFilter() {
            this.selectedDateStr = null;
        },
        getDayCellClass(day) {
            const dateStr = this.getDateStr(day);
            let classes = 'calendar-day-cell h-16 flex flex-col justify-between p-2 rounded-md cursor-pointer transition-all ';
            
            if (this.selectedDateStr === dateStr) {
                classes += 'is-selected ';
            } else if (this.hasEvents(day)) {
                classes += 'has-events ';
            }
            return classes;
        },
        getDayNumberClass(day) {
            if (this.isToday(day)) {
                return 'text-xs font-bold w-6 h-6 flex items-center justify-center rounded-full bg-blue-600 dark:bg-blue-500 text-white shadow-sm';
            }
            const dateStr = this.getDateStr(day);
            if (this.selectedDateStr === dateStr) {
                return 'text-xs font-bold w-6 h-6 flex items-center justify-center text-blue-700 dark:text-blue-300';
            }
            return 'text-xs font-semibold w-6 h-6 flex items-center justify-center text-gray-700 dark:text-dark-200';
        },
        formatEventDate(startTime) {
            const date = this.parseDateTime(startTime);
            if (!date) return 'No Date';
            return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
        },
        formatEventTime(startTime) {
            const date = this.parseDateTime(startTime);
            if (!date) return '';
            return date.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
        },
        getEventMonthShort(startTime) {
            const date = this.parseDateTime(startTime);
            if (!date) return 'TBD';
            return date.toLocaleDateString(undefined, { month: 'short' });
        },
        getEventDayNumber(startTime) {
            const date = this.parseDateTime(startTime);
            if (!date) return '--';
            return date.getDate();
        },
        getEventDayOfWeek(startTime) {
            const date = this.parseDateTime(startTime);
            if (!date) return '';
            return date.toLocaleDateString(undefined, { weekday: 'short' });
        },
        getLocationLabel(location) {
            if (!location) return '';
            if (typeof location === 'string') return location;
            const name = location.title || location.osm_name;
            const address = location.address || '';
            if (name) {
                const parts = address.split(',').map(p => p.trim());
                if (parts.length > 1 && !name.toLowerCase().includes(parts[1].toLowerCase())) {
                    return `${name}, ${parts[1]}`;
                }
                return name;
            }
            const addrParts = address.split(',').map(p => p.trim());
            return addrParts.slice(0, 2).join(', ') || 'Location';
        },
        isEventPaid(event) {
            if (!event) return false;
            const price = event.price || event.ticket_price;
            return price !== null && price !== undefined && parseFloat(price) > 0;
        },
        openDetails(event, focus = 'overview') {
            if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
                Statamic.$activitypub.bus.emit('activitypub:events:view-details', {
                    event: event,
                    focus: focus
                });
            }
        },
        toggleRsvp(event) {
            if (!event || event._isUpdatingRsvp) return;

            const targetStatus = !event.user_rsvp ? 'accepted' : 'rejected';
            const eventId = event.id || event.slug || event.activitypub_id;

            if (!eventId) {
                if (typeof Statamic !== 'undefined' && Statamic.$toast) {
                    Statamic.$toast.error('Unable to RSVP: Event ID is missing.');
                }
                return;
            }

            event._isUpdatingRsvp = true;

            const url = typeof cp_url === 'function' 
                ? cp_url(`activitypub/events/${eventId}/rsvp`) 
                : `/cp/activitypub/events/${eventId}/rsvp`;

            const axiosInstance = this.$axios || (typeof axios !== 'undefined' ? axios : null);

            if (!axiosInstance) {
                event.user_rsvp = !event.user_rsvp;
                event._isUpdatingRsvp = false;
                return;
            }

            axiosInstance.post(url, { status: targetStatus })
                .then(response => {
                    const data = response.data;
                    event.user_rsvp = Boolean(data.user_rsvp);
                    if (data.attendee_count !== undefined) {
                        event.attendee_count = data.attendee_count;
                    }
                    if (Array.isArray(data.attendees)) {
                        event.attendees = data.attendees;
                    }

                    if (typeof Statamic !== 'undefined' && Statamic.$toast) {
                        if (event.user_rsvp) {
                            Statamic.$toast.success(data.message || "RSVP'd as Going!");
                        } else {
                            Statamic.$toast.info(data.message || 'RSVP cancelled.');
                        }
                    }
                })
                .catch(err => {
                    const msg = err.response?.data?.error || err.response?.data?.message || 'Failed to update RSVP.';
                    if (typeof Statamic !== 'undefined' && Statamic.$toast) {
                        Statamic.$toast.error(msg);
                    }
                })
                .finally(() => {
                    event._isUpdatingRsvp = false;
                });
        },
        createNewEvent() {
            if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
                Statamic.$activitypub.bus.emit('activitypub:inbox:create-event');
            }
        },
        fetchEvents() {
            this.$axios.get(cp_url('activitypub/events'), { headers: { 'Accept': 'application/json' } })
                .then(response => {
                    this.localEvents = response.data.events;
                });
        }
    }
}

</script>
