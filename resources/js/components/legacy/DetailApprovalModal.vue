<template>
    <HcisDialog
        :model-value="show"
        :title="title"
        :kicker="kicker"
        icon="clipboard-check"
        sentiment="brand"
        variant="glass"
        size="2xl"
        scrollable
        sticky-footer
        custom-class="detail-approval-dialog"
        @close="$emit('close')"
    >
        <div class="detail-modal-content">
                <div class="detail-modal-grid">
                    <!-- ===== KIRI: Informasi pengaju + alur approval ===== -->
                    <div class="detail-left-column">
                        <!-- Header card pengaju -->
                        <div
                            class="detail-transaksi-card p-3 mb-3 border-0 rounded-3 text-white shadow-sm"
                            style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%)"
                        >
                            <div class="d-flex align-items-center gap-3">
                                <div
                                    class="rounded-circle bg-white text-primary d-flex align-items-center justify-content-center fw-bold fs-6 shadow-sm"
                                    style="width: 3rem; height: 3rem"
                                >
                                    {{ getInitials(header.nama) }}
                                </div>
                                <div class="detail-transaksi-type text-start">
                                    <div class="text-white-50 text-xs mb-1">Nama Pengaju</div>
                                    <div class="fw-bold mb-0 text-white text-md">{{ header.nama || '-' }}</div>
                                    <span class="text-white-50 text-xs">{{ header.kode_karyawan || '-' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Status -->
                        <div class="detail-status-section mb-3">
                            <div class="detail-status-label text-xs text-muted mb-1 text-uppercase fw-bold">
                                Status
                            </div>
                            <HcisStatusBadge
                                :label="statusText"
                                :variant="statusVariant"
                                appearance="subtle"
                                size="sm"
                            />
                        </div>

                        <!-- No transaksi + tanggal -->
                        <div class="detail-status-section mb-3">
                            <div class="detail-status-label text-xs text-muted mb-1 text-uppercase fw-bold">
                                No. Transaksi
                            </div>
                            <div
                                class="detail-status-badge pe-3 mono"
                                style="background: #fff; color: #1e293b; box-shadow: none; border: 1px solid #e2e8f0"
                            >
                                {{ header.no_transaksi || '-' }}
                            </div>
                            <div v-if="header.tanggal" class="detail-status-date mt-2 text-xs text-muted">
                                <i class="bi bi-calendar3 me-1"></i> Diajukan: {{ formatDate(header.tanggal) }}
                            </div>
                        </div>

                        <!-- Info ringkas jabatan/divisi -->
                        <div class="detail-meta-grid mb-2">
                            <div v-if="header.divisi" class="meta-item">
                                <span class="meta-k">Divisi</span><span class="meta-v">{{ header.divisi }}</span>
                            </div>
                            <div v-if="header.departemen" class="meta-item">
                                <span class="meta-k">Departemen</span
                                ><span class="meta-v">{{ header.departemen }}</span>
                            </div>
                            <div v-if="header.jabatan" class="meta-item">
                                <span class="meta-k">Jabatan</span><span class="meta-v">{{ header.jabatan }}</span>
                            </div>
                            <div v-if="header.level" class="meta-item">
                                <span class="meta-k">Level</span><span class="meta-v">{{ header.level }}</span>
                            </div>
                        </div>

                        <!-- Alur approval. Disembunyikan untuk isi yang bukan satu
                             transaksi (mis. agregat YTD), supaya tidak tampil
                             "Tidak ada alur approval" yang menyesatkan. -->
                        <div v-if="tampilkanAlur" class="detail-approval-section mt-3">
                            <div class="detail-section-title mb-3">
                                <i class="bi bi-diagram-3 text-primary"></i> Alur Persetujuan
                            </div>

                            <div v-if="flowLoading" class="detail-approval-empty">
                                <HcisSkeleton type="text" :lines="3" height="0.7rem" />
                            </div>

                            <div v-else class="detail-approval-list">
                                <div v-for="(step, idx) in flowSteps" :key="idx" class="detail-approval-item">
                                    <div
                                        class="detail-approval-indicator"
                                        :class="{
                                            approved: step.flag_approval === 'Y',
                                            rejected: step.flag_approval === 'T',
                                            pending: step.flag_approval !== 'Y' && step.flag_approval !== 'T',
                                            'is-current': step.is_current,
                                        }"
                                    >
                                        <i v-if="step.flag_approval === 'Y'" class="bi bi-check"></i>
                                        <i v-else-if="step.flag_approval === 'T'" class="bi bi-x"></i>
                                        <i v-else class="bi bi-hourglass-split"></i>
                                    </div>
                                    <div class="detail-approval-info">
                                        <div
                                            class="detail-approval-name"
                                            :class="{
                                                'text-success': step.flag_approval === 'Y',
                                                'text-danger': step.flag_approval === 'T',
                                            }"
                                        >
                                            Level {{ step.order }} ·
                                            {{ step.approver_nama || step.role_name || '-' }}
                                        </div>
                                        <div v-if="step.flag_approval === 'Y'" class="detail-approval-date">
                                            <span class="text-success fw-medium">Disetujui</span>
                                            <span v-if="step.approved_by"> oleh {{ step.approved_by }}</span>
                                            <span v-if="step.tanggal_approval">
                                                · {{ formatDate(step.tanggal_approval) }}</span
                                            >
                                        </div>
                                        <div v-else-if="step.flag_approval === 'T'" class="detail-approval-date">
                                            <span class="text-danger fw-medium">Ditolak</span>
                                            <span v-if="step.tanggal_approval">
                                                · {{ formatDate(step.tanggal_approval) }}</span
                                            >
                                        </div>
                                        <div v-else class="detail-approval-date">
                                            <span class="text-warning fw-medium">{{
                                                step.is_current ? 'Sedang ditinjau' : 'Menunggu'
                                            }}</span>
                                        </div>
                                        <div v-if="step.catatan" class="text-muted small mt-1">
                                            <i class="bi bi-chat-left-text"></i> {{ step.catatan }}
                                        </div>
                                    </div>
                                </div>

                                <HcisEmptyState
                                    v-if="!flowSteps.length"
                                    title="Tidak ada alur approval"
                                    icon="diagram-3"
                                    variant="flat"
                                    size="sm"
                                    :animated="false"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- ===== KANAN: Isi transaksi (slot) ===== -->
                    <div class="detail-right-column">
                        <slot name="content" />
                    </div>
                </div>
            </div>

        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </HcisDialog>
</template>

<script>
import { HcisDialog, HcisEmptyState, HcisSkeleton, HcisStatusBadge } from '@/components/ui';

export default {
    name: 'DetailApprovalModal',
    components: { HcisDialog, HcisEmptyState, HcisSkeleton, HcisStatusBadge },
    props: {
        show: { type: Boolean, default: false },
        title: { type: String, default: 'Detail Transaksi' },

        // Kicker WAJIB datang dari pemanggil.
        //
        // Sebelumnya nilainya dipatri 'Visitor · Verification' — sisa salin-tempel
        // dari asal komponen ini, padahal satu-satunya pemakainya adalah halaman
        // KPI Enforcement. Modal KPI karenanya memperkenalkan dirinya sebagai
        // modul Visitor di setiap pembukaan. Bakunya dibuat netral supaya pemakai
        // berikutnya yang lupa mengisinya tidak mewarisi nama modul orang lain.
        kicker: { type: String, default: 'Detail Transaksi' },
        header: { type: Object, default: () => ({}) },
        flowSteps: { type: Array, default: () => [] },
        flowLoading: { type: Boolean, default: false },
        tampilkanAlur: { type: Boolean, default: true },
    },
    emits: ['close'],
    computed: {
        // Status badge mengikuti status sebenarnya (draft/waiting/in_progress/approved).
        statusText() {
            const s = this.header.status;
            if (s === 'approved' || this.header.approved) return 'Disetujui';
            if (s === 'draft') return 'Draft';
            if (s === 'in_progress') return 'Sedang Berjalan';
            if (s === 'waiting') return 'Menunggu Approval';
            return this.header.status_label || 'Menunggu Approval';
        },
        statusClass() {
            const s = this.header.status;
            if (s === 'approved' || this.header.approved) return 'approved';
            if (s === 'draft') return 'draft';
            return 'pending';
        },
        statusVariant() {
            if (this.header.status === 'approved' || this.header.approved) return 'success';
            if (this.header.status === 'in_progress') return 'brand';
            if (this.header.status === 'draft') return 'neutral';
            return 'warning';
        },
    },
    methods: {
        getInitials(name) {
            if (!name) return '—';
            const parts = name.trim().split(/\s+/);
            return (parts.length >= 2 ? parts[0][0] + parts[1][0] : parts[0][0]).toUpperCase();
        },
        formatDate(value) {
            if (!value) return '';
            const d = new Date(value);
            if (isNaN(d)) return value;
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
    },
};
</script>

<style scoped>
.detail-modal-content {
    padding: 0;
}
.detail-modal-grid {
    display: grid;
    grid-template-columns: 340px 1fr;
    min-height: 420px;
    gap: 1rem;
}
.detail-left-column {
    background: #f8fafc;
    padding: 1.25rem;
    border-right: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
}
.detail-right-column {
    padding: 0.25rem 0.25rem 0.25rem 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    overflow-y: auto;
    max-height: 560px;
}
.text-md {
    font-size: 0.95rem;
}
.mono {
    font-family: monospace;
    font-size: 0.78rem;
}
.detail-status-section {
    text-align: center;
}
.detail-status-badge {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    border-radius: 9999px;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.detail-status-badge.approved {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}
.detail-status-badge.pending {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
}
.detail-status-badge.draft {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}
.detail-meta-grid {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}
.meta-item {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.78rem;
    padding: 0.3rem 0;
    border-bottom: 1px dashed #e2e8f0;
}
.meta-k {
    color: #94a3b8;
    text-transform: uppercase;
    font-weight: 700;
    font-size: 0.65rem;
    letter-spacing: 0.03em;
}
.meta-v {
    color: #334155;
    font-weight: 600;
    text-align: right;
}
.detail-section-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: #1e293b;
    padding-bottom: 0.4rem;
    border-bottom: 2px solid #6366f1;
    display: inline-block;
}
.detail-section-title i {
    margin-right: 0.25rem;
}
.detail-approval-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.detail-approval-item {
    display: flex;
    align-items: flex-start;
    gap: 0.7rem;
}
.detail-approval-indicator {
    width: 26px;
    height: 26px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 0.78rem;
}
.detail-approval-indicator.approved {
    background: #10b981;
    color: #fff;
}
.detail-approval-indicator.rejected {
    background: #ef4444;
    color: #fff;
}
.detail-approval-indicator.pending {
    background: #e2e8f0;
    color: #64748b;
}
.detail-approval-indicator.is-current {
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.25);
    background: #6366f1;
    color: #fff;
}
.detail-approval-info {
    flex: 1;
    min-width: 0;
}
.detail-approval-name {
    font-size: 0.82rem;
    font-weight: 600;
    color: #1e293b;
}
.detail-approval-date {
    font-size: 0.72rem;
    color: #64748b;
}
.detail-approval-empty {
    font-size: 0.82rem;
    color: #64748b;
}
.border-dashed {
    border: 1px dashed #cbd5e1;
}
.spin {
    animation: dam-spin 1s linear infinite;
}
@keyframes dam-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 768px) {
    .detail-modal-grid {
        grid-template-columns: 1fr;
    }
    .detail-left-column {
        border-right: none;
        border-bottom: 1px solid #e2e8f0;
    }
    .detail-right-column {
        max-height: none;
    }
}
</style>
