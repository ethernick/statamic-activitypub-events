<template>
    <div class="px-6 py-6 pb-0">
        <h2 class="text-xl font-medium mb-4">Event Configuration</h2>
        <p class="text-sm text-gray-600 mb-6 w-3/4">
            Configure how events behave and federate across the wider Fediverse.
        </p>

        <h2 class="text-xl font-medium mt-6 mb-4">Compatibility Configuration</h2>
        <div class="mb-4 w-full md:w-3/4">
            <div class="flex items-start">
                <div class="flex items-center h-5">
                    <input type="checkbox" v-model="form.compatibility_mode" class="checkbox" />
                </div>
                <div class="ml-3 text-sm">
                    <label class="font-medium text-gray-700 dark:text-gray-300">ActivityPub Compatibility Mode</label>
                    <p class="text-xs text-gray-500">When enabled, creating an event will also generate a standard <strong>Note</strong> activity. This ensures that events appear on platforms that do not support custom Event activity types (like Mastodon).</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    props: {
        form: {
            type: Object,
            required: true
        }
    }
}
</script>
