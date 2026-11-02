<template>
    <a href="#" @click.prevent="open" class="block px-4 py-2 text-sm">
        New Event
    </a>
</template>

<script>
export default {
    methods: {
        open() {
            if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
                Statamic.$activitypub.bus.emit('activitypub:inbox:create-event');
            }
        }
    }
}
</script>
