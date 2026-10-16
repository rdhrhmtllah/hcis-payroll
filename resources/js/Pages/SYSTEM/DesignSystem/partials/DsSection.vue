<template>
    <section :id="sectionId" class="ds-section">
        <header class="ds-section__head">
            <span class="ds-section__no">{{ no }}</span>
            <div class="ds-section__titles">
                <h2 class="ds-section__title">{{ title }}</h2>
                <p v-if="description" class="ds-section__desc">{{ description }}</p>
            </div>
        </header>
        <div class="ds-section__body">
            <slot />
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    no: { type: [String, Number], required: true },
    title: { type: String, required: true },
    description: { type: String, default: '' },
    id: { type: String, default: '' },
});

const sectionId = computed(() => {
    if (props.id) return props.id;
    return `sec-${props.title.toLowerCase().replace(/\s+/g, '-')}`;
});
</script>

<style scoped>
.ds-section {
    scroll-margin-top: 5rem;
    border-top: 1px solid var(--hcis-border);
    padding-top: var(--hcis-space-6);
    margin-top: var(--hcis-space-8);
}
.ds-section__head {
    display: flex;
    align-items: flex-start;
    gap: var(--hcis-space-3);
    margin-bottom: var(--hcis-space-5);
}
.ds-section__no {
    display: grid;
    place-items: center;
    width: 1.75rem;
    height: 1.75rem;
    flex: none;
    border-radius: var(--hcis-radius-md);
    background: var(--hcis-gradient-brand);
    color: var(--hcis-white);
    font-size: var(--hcis-font-size-xs);
    font-weight: 800;
    box-shadow: 0 2px 6px rgb(var(--hcis-brand-600-rgb) / 0.3);
}
.ds-section__titles {
    flex: 1;
}
.ds-section__title {
    margin: 0;
    color: var(--hcis-text-strong);
    font-family: var(--hcis-font-heading);
    font-size: var(--hcis-font-size-lg);
    font-weight: 800;
    letter-spacing: -0.02em;
}
.ds-section__desc {
    margin: var(--hcis-space-1) 0 0;
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-sm);
    line-height: var(--hcis-line-normal);
}
</style>
