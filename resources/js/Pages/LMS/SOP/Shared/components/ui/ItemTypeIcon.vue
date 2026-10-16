<template>
    <span class="sop-tipe" :class="targetType ? 'type-ico-' + targetType : ''" :title="meta.label">
        <i class="bi" :class="meta.icon"></i>
        <span v-if="showLabel" class="ms-1">{{ meta.label }}</span>
    </span>
</template>

<script>
import { TIPE_ITEM } from '../../support/constants.js';

/**
 * ItemTypeIcon — ikon untuk 7 tipe item dokumen dengan badge warna.
 * Pakai: <ItemTypeIcon type="DOKUMEN" showLabel /> atau <ItemTypeIcon tipe="DOKUMEN" />
 */
export default {
    name: 'ItemTypeIcon',
    props: {
        tipe: { type: String, default: '' },
        type: { type: String, default: '' },
        showLabel: { type: Boolean, default: false },
    },
    computed: {
        targetType() {
            return (this.type || this.tipe || 'PARAGRAF').toUpperCase();
        },
        meta() {
            const icons = {
                DOKUMEN: { label: 'Dokumen', icon: 'bi-file-earmark-text-fill' },
                FORM: { label: 'Form', icon: 'bi-file-earmark-check-fill' },
                VIDEO: { label: 'Video', icon: 'bi-play-btn-fill' },
                KUIS: { label: 'Kuis', icon: 'bi-patch-question-fill' },
                TAUTAN: { label: 'Tautan', icon: 'bi-link-45deg' },
                PARAGRAF: { label: 'Paragraf', icon: 'bi-text-paragraph' },
                ABSENSI: { label: 'Absensi', icon: 'bi-camera-fill' },
            };
            return (
                icons[this.targetType] ||
                TIPE_ITEM[this.targetType] || { label: this.targetType, icon: 'bi-file-earmark' }
            );
        },
    },
};
</script>

<style scoped>
.sop-tipe {
    display: inline-flex;
    align-items: center;
    width: 38px;
    height: 38px;
    justify-content: center;
    border-radius: 12px;
    font-size: 1.1rem;
    flex-shrink: 0;
    transition: transform 0.2s ease;
}
.sop-tipe:has(span) {
    width: auto;
    padding: 0 0.65rem;
}
.sop-tipe.type-ico-PARAGRAF {
    background: rgba(99, 102, 241, 0.12);
    color: var(--hcis-indigo-500);
}
.sop-tipe.type-ico-DOKUMEN {
    background: rgba(16, 185, 129, 0.14);
    color: var(--hcis-emerald-600);
}
.sop-tipe.type-ico-KUIS {
    background: rgba(245, 158, 11, 0.14);
    color: var(--hcis-amber-600);
}
.sop-tipe.type-ico-FORM {
    background: rgba(6, 182, 212, 0.14);
    color: var(--hcis-cyan-600);
}
.sop-tipe.type-ico-VIDEO {
    background: rgba(139, 92, 246, 0.14);
    color: var(--hcis-violet-600);
}
.sop-tipe.type-ico-TAUTAN {
    background: rgba(236, 72, 153, 0.14);
    color: #db2777;
}
.sop-tipe.type-ico-ABSENSI {
    background: rgba(59, 130, 246, 0.14);
    color: var(--hcis-blue-600);
}
</style>
