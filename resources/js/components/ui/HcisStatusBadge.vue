<template>
    <HcisBadge v-bind="$props">
        <template v-if="$slots.default" #default>
            <slot />
        </template>
    </HcisBadge>
</template>

<script setup>
import HcisBadge from './HcisBadge.vue';

defineOptions({ name: 'HcisStatusBadge' });

defineProps({
    status: { type: String, default: '' },
    label: { type: String, default: '' },
    variant: { type: String, default: '' },
    size: { type: String, default: 'sm' },
    icon: { type: [String, Boolean], default: '' },
    dot: { type: Boolean, default: false },
    pulse: { type: Boolean, default: false },
});
</script>
