<template>
    <div class="sop-stack bab-kualitas">
        <!-- Heatmap Sebaran Kepatuhan Departemen x Sumbu Terpilih -->
        <PanelHeatmap :saringan="saringan" />
    </div>
</template>

<script>
import PanelHeatmap from './panel/PanelHeatmap.vue';

/**
 * Bab Kualitas — "Apakah benar-benar terserap?"
 */
export default {
    name: 'BabKualitas',
    components: {
        PanelHeatmap,
    },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    emits: ['buka-drawer', 'buka-tab'],
};
</script>

<style scoped>
.bab-kualitas {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}

.bq-tunda {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--hcis-space-3);
    min-height: 14rem;
    border-radius: var(--hcis-radius-xl);
    background: var(--hcis-surface);
    border: 1px dashed var(--hcis-border-medium);
    color: var(--hcis-text-muted);
    font-size: var(--hcis-font-size-sm);
    font-weight: 600;
}
</style>
