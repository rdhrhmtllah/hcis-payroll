<template>
    <div
        class="hcis-empty-state"
        :class="[
            `hcis-empty-state--variant-${variant}`,
            `hcis-empty-state--size-${size}`,
            `hcis-empty-state--sentiment-${sentiment}`,
            { 'hcis-empty-state--animated': animated }
        ]"
    >
        <!-- Illustration Image or Icon Box -->
        <div v-if="image || $slots.image" class="hcis-empty-state__image-wrap">
            <slot name="image">
                <img :src="image" :alt="title" class="hcis-empty-state__image" />
            </slot>
        </div>

        <div v-else-if="icon || $slots.icon" class="hcis-empty-state__icon-wrap" aria-hidden="true">
            <div class="hcis-empty-state__ambient-glow" />
            <div class="hcis-empty-state__icon-box">
                <slot name="icon">
                    <HcisIcon v-if="icon" :name="icon" size="xl" />
                    <span v-else>○</span>
                </slot>
            </div>
        </div>

        <!-- Title & Description -->
        <h3 v-if="title || $slots.title" class="hcis-empty-state__title">
            <slot name="title">{{ title }}</slot>
        </h3>

        <p v-if="description || $slots.description" class="hcis-empty-state__description">
            <slot name="description">{{ description }}</slot>
        </p>

        <!-- Actions Slot -->
        <div v-if="$slots.actions || $slots.default" class="hcis-empty-state__actions">
            <slot name="actions">
                <slot />
            </slot>
        </div>
    </div>
</template>

<script setup>
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisEmptyState' });

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'inbox' },
    image: { type: String, default: '' },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    variant: {
        type: String,
        default: 'default',
        validator: (v) => ['default', 'dashed', 'flat', 'card'].includes(v),
    },
    sentiment: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'success', 'warning', 'danger', 'info'].includes(v),
    },
    animated: { type: Boolean, default: true },
});
</script>
