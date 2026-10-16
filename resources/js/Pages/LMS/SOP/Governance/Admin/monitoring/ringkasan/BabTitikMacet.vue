<template>
    <div class="sop-stack bab-macet">
        <PanelPeringkatUnit :saringan="saringan" @saring="$emit('saring', $event)" />
    </div>
</template>

<script>
import PanelPeringkatUnit from './panel/PanelPeringkatUnit.vue';

/**
 * Bab 3 — "Di mana macetnya?"
 *
 * Menampilkan Peringkat Unit berdasarkan tingkat keterlambatan & penyelesaian.
 */
export default {
    name: 'BabTitikMacet',
    components: { PanelPeringkatUnit },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    emits: ['buka-drawer', 'buka-tab', 'saring'],
};
</script>

<style scoped>
.bab-macet {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4);
}
</style>
