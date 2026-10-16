<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100 d-flex flex-column align-items-center justify-content-center text-center" style="min-height:200px;">
        <div v-if="type === 'error'" class="mb-3">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;background:#FEF2F2;">
                <i class="bi bi-exclamation-triangle-fill" style="color:#EF4444;font-size:1.5rem;"></i>
            </div>
            <h6 class="fw-bold mt-3 mb-1">Gagal Memuat Data</h6>
            <p class="text-muted small mb-3">{{ message || 'Terjadi kesalahan saat mengambil data. Silakan coba lagi.' }}</p>
            <button v-if="retryable" class="btn btn-sm btn-outline-danger rounded-pill px-4" @click="$emit('retry')">
                <i class="bi bi-arrow-clockwise me-1"></i>Coba Lagi
            </button>
        </div>
        <div v-else-if="type === 'empty'">
            <div class="rounded-circle d-inline-flex align-items-center justify-content-center" style="width:56px;height:56px;background:#F1F5F9;">
                <i class="bi bi-inbox" style="color:#94A3B8;font-size:1.5rem;"></i>
            </div>
            <h6 class="fw-bold mt-3 mb-1">Belum Ada Data</h6>
            <p class="text-muted small mb-0">{{ message || 'Data akan muncul setelah ada aktivitas.' }}</p>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CardState',
    props: {
        type: { type: String, default: 'error' }, // 'error' | 'empty'
        message: { type: String, default: '' },
        retryable: { type: Boolean, default: true },
    },
    emits: ['retry'],
};
</script>
