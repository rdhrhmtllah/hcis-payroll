<template>
    <TransitionGroup
        v-if="group"
        :name="`hcis-${name}`"
        :tag="tag"
        class="hcis-group"
        :class="[`hcis-gerak--${duration}`, `hcis-gerak--${name}`]"
    >
        <slot />
    </TransitionGroup>
    <Transition
        v-else
        :name="`hcis-${name}`"
        :mode="mode"
        :class="[`hcis-gerak--${duration}`, `hcis-gerak--${name}`]"
    >
        <slot />
    </Transition>
</template>

<script setup>
defineOptions({ name: 'HcisGerak' });

defineProps({
    name: {
        type: String,
        default: 'fade',
        validator: (v) => [
            'fade',
            'slide-up',
            'slide-down',
            'slide-left',
            'slide-right',
            'scale',
            'zoom-in',
            'flip',
            'collapse',
        ].includes(v),
    },
    mode: {
        type: String,
        default: 'out-in',
        validator: (v) => ['out-in', 'in-out', 'default', ''].includes(v),
    },
    group: {
        type: Boolean,
        default: false,
    },
    tag: {
        type: String,
        default: 'div',
    },
    duration: {
        type: String,
        default: 'normal',
        validator: (v) => ['fast', 'normal', 'slow', 'spring'].includes(v),
    },
});
</script>
