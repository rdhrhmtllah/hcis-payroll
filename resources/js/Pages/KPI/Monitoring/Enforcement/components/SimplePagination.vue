<template>
    <div v-if="meta && meta.total_page > 1" class="sp">
        <span class="sp-info">Halaman {{ meta.page }} dari {{ meta.total_page }} · {{ meta.total_data }} data</span>
        <div class="sp-actions">
            <button class="sp-btn" :disabled="meta.page <= 1" @click="$emit('page', meta.page - 1)">
                <i class="bi bi-chevron-left"></i> Sebelumnya
            </button>
            <button class="sp-btn" :disabled="meta.page >= meta.total_page" @click="$emit('page', meta.page + 1)">
                Berikutnya <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'SimplePagination',
    props: {
        meta: { type: Object, default: () => ({ page: 1, total_page: 1, total_data: 0 }) },
    },
    emits: ['page'],
};
</script>

<style scoped>
.sp {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding: 0.85rem 1rem;
    border-top: 1px solid var(--hcis-slate-100);
    flex-wrap: wrap;
}
.sp-info {
    font-size: 0.8rem;
    color: var(--hcis-slate-500);
}
.sp-actions {
    display: flex;
    gap: 0.5rem;
}
.sp-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.4rem 0.85rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 8px;
    background: #fff;
    font-size: 0.82rem;
    color: var(--hcis-slate-600);
    cursor: pointer;
}
.sp-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>
