<template>
    <div>
        <event-stack
            :open="isCreatingEvent"
            @close="isCreatingEvent = false"
            @submit-success="$emit('submit-success')"
        />

        <event-details-stack
            :open="isDetailsOpen"
            :event="activeEvent"
            :note="activeNote"
            :focus="activeFocus"
            @close="closeDetails"
        />
    </div>
</template>

<script>
import EventStack from './EventStack.vue';
import EventDetailsStack from './EventDetailsStack.vue';

export default {
    components: {
        EventStack,
        EventDetailsStack
    },
    data() {
        return {
            isCreatingEvent: false,
            isDetailsOpen: false,
            activeEvent: null,
            activeNote: null,
            activeFocus: 'overview'
        }
    },
    mounted() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
            this._onCreateEvent = () => {
                this.isCreatingEvent = true;
            };

            this._onViewDetails = (payload) => {
                this.activeEvent = (payload && payload.event) ? payload.event : null;
                this.activeNote = (payload && payload.note) ? payload.note : null;
                this.activeFocus = (payload && payload.focus) ? payload.focus : 'overview';
                this.isDetailsOpen = true;
            };

            // Standardized events
            Statamic.$activitypub.bus.on('activitypub:events:open-create', this._onCreateEvent);
            Statamic.$activitypub.bus.on('activitypub:events:open-details', this._onViewDetails);

            // Legacy backward-compatible events
            Statamic.$activitypub.bus.on('activitypub:inbox:create-event', this._onCreateEvent);
            Statamic.$activitypub.bus.on('activitypub:events:view-details', this._onViewDetails);
        }
    },
    beforeDestroy() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
            Statamic.$activitypub.bus.off('activitypub:events:open-create', this._onCreateEvent);
            Statamic.$activitypub.bus.off('activitypub:events:open-details', this._onViewDetails);

            Statamic.$activitypub.bus.off('activitypub:inbox:create-event', this._onCreateEvent);
            Statamic.$activitypub.bus.off('activitypub:events:view-details', this._onViewDetails);
        }
    },
    methods: {
        closeDetails() {
            this.isDetailsOpen = false;
            this.activeEvent = null;
            this.activeNote = null;
        }
    }
}
</script>
