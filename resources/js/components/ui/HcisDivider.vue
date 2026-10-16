<template>
    <div
        class="hcis-divider"
        :class="[
            `hcis-divider--${direction}`,
            `hcis-divider--${spacing}`,
            `hcis-divider--variant-${variant}`,
            `hcis-divider--align-${align}`,
            { 'hcis-divider--plain': !label && !$slots.default && !icon }
        ]"
        role="separator"
        :aria-orientation="direction"
    >
        <template v-if="direction === 'horizontal'">
            <span class="hcis-divider__line hcis-divider__line--start"></span>
            <span v-if="label || $slots.default || icon" class="hcis-divider__label">
                <HcisIcon v-if="icon" :name="icon" size="sm" class="hcis-divider__icon" />
                <slot>{{ label }}</slot>
            </span>
            <span v-if="align !== 'right' || !label" class="hcis-divider__line hcis-divider__line--end"></span>
        </template>
    </div>
</template>

<script setup>
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisDivider' });

defineProps({
    label: { type: String, default: '' },
    icon: { type: String, default: '' },
    spacing: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'xs', 'sm', 'md', 'lg', 'xl'].includes(v),
    },
    direction: {
        type: String,
        default: 'horizontal',
        validator: (v) => ['horizontal', 'vertical'].includes(v),
    },
    variant: {
        type: String,
        default: 'solid',
        validator: (v) => ['solid', 'dashed', 'dotted', 'gradient'].includes(v),
    },
    align: {
        type: String,
        default: 'center',
        validator: (v) => ['center', 'left', 'right'].includes(v),
    },
});
</script>
