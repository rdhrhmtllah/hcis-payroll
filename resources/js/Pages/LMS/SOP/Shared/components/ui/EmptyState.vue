<template>
    <div class="sop-empty text-center" :class="{ compact }">
        <div class="sop-empty-orb">
            <i class="bi" :class="icon"></i>
        </div>
        <p class="sop-empty-title">{{ title }}</p>
        <p class="sop-empty-sub" v-if="subtitle">{{ subtitle }}</p>
        <div class="sop-empty-action" v-if="$slots.default"><slot /></div>
    </div>
</template>

<script>
/**
 * EmptyState — tampilan data kosong (premium).
 * Pakai: <EmptyState title="Belum ada dokumen" subtitle="..."><button>...</button></EmptyState>
 */
export default {
    name: 'EmptyState',
    props: {
        title: { type: String, default: 'Tidak ada data' },
        subtitle: { type: String, default: '' },
        icon: { type: String, default: 'bi-inbox' },
        compact: { type: Boolean, default: false },
    },
};
</script>

<style scoped>
.sop-empty {
    padding: 3rem 1.5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.sop-empty.compact {
    padding: 1.6rem 1rem;
}
.sop-empty.compact .sop-empty-orb {
    width: 52px;
    height: 52px;
    border-radius: 15px;
    font-size: 1.35rem;
    margin-bottom: 0.7rem;
}
.sop-empty.compact .sop-empty-title {
    font-size: 0.9rem;
}
.sop-empty.compact .sop-empty-sub {
    font-size: 0.8rem;
}
.sop-empty-orb {
    width: 76px;
    height: 76px;
    border-radius: 22px;
    display: grid;
    place-items: center;
    font-size: 2rem;
    color: var(--hcis-indigo-500);
    background: radial-gradient(circle at 30% 25%, rgba(99, 102, 241, 0.16), rgba(99, 102, 241, 0.06));
    border: 1px solid rgba(99, 102, 241, 0.18);
    margin-bottom: 1rem;
    box-shadow: 0 12px 26px -14px rgba(99, 102, 241, 0.5);
}
.sop-empty-title {
    font-weight: 800;
    font-size: 1rem;
    color: var(--hcis-slate-800);
    margin: 0 0 0.25rem;
}
.sop-empty-sub {
    color: var(--hcis-slate-500);
    font-size: 0.85rem;
    max-width: 340px;
    margin: 0;
}
.sop-empty-action {
    margin-top: 1.1rem;
}
</style>
