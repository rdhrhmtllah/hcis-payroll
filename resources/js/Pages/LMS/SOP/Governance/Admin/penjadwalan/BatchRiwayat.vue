<template>
    <div class="brw">
        <!-- Skeleton Loading -->
        <div v-if="loading" class="d-flex flex-column gap-2 p-2">
            <HcisSkeleton v-for="n in 3" :key="n" type="card" height="52px" rounded="lg" />
        </div>

        <!-- Empty State -->
        <HcisEmptyState
            v-else-if="!baris.length"
            title="Belum Ada Peristiwa Tercatat"
            description="Belum ada log aktivitas atau audit riwayat untuk batch distribusi ini."
            icon="clock-history"
            variant="dashed"
            sentiment="neutral"
            class="my-3"
        />

        <!-- Timeline Riwayat Batch -->
        <div v-else class="p-1">
            <HcisTimeline
                :items="timelineItems"
                variant="flat"
                sentiment="purple"
            >
                <!-- Marker Slot -->
                <template #marker="{ item }">
                    <i class="bi" :class="item.icon" style="font-size: 0.85rem; line-height: 1;"></i>
                </template>

                <!-- Item Content Slot -->
                <template #itemContent="{ item }">
                    <p v-if="item.keterangan" class="small text-muted mb-1">{{ item.keterangan }}</p>

                    <!-- Reason / Sebab Tag -->
                    <div v-if="item.sebab" class="mb-1">
                        <span class="badge bg-danger-light text-danger border border-danger-subtle font-sans" style="font-size: 0.72rem;">
                            <i class="bi bi-info-circle me-1"></i>Sebab: {{ item.sebab }}
                        </span>
                    </div>

                    <!-- Admin Note -->
                    <div v-if="item.catatan" class="small text-secondary fst-italic mb-1 bg-light p-1.5 rounded border">
                        "{{ item.catatan }}"
                    </div>

                    <!-- Target Karyawan Details List -->
                    <div v-if="item.karyawan && item.karyawan.length" class="brw-karyawan-box my-1.5 p-2 rounded bg-light border">
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="small fw-semibold text-dark">
                                <i class="bi bi-people me-1 text-primary"></i> Daftar Karyawan ({{ item.karyawan.length }}):
                            </span>
                            <button
                                v-if="item.karyawan.length > 4"
                                type="button"
                                class="btn btn-link btn-xs p-0 text-decoration-none"
                                @click="toggleExpand(item.id)"
                            >
                                {{ isExpanded(item.id) ? 'Sembunyikan' : `+${item.karyawan.length - 4} lainnya` }}
                            </button>
                        </div>
                        <div class="d-flex flex-wrap gap-1">
                            <span
                                v-for="(k, idx) in (isExpanded(item.id) ? item.karyawan : item.karyawan.slice(0, 4))"
                                :key="idx"
                                class="badge bg-white text-dark border shadow-xs"
                                style="font-size: 0.72rem; font-weight: 500;"
                            >
                                {{ k }}
                            </span>
                        </div>
                    </div>

                    <div class="small text-secondary mt-1">
                        <i class="bi bi-person me-1"></i> Aktor: <strong>{{ item.oleh }}</strong>
                    </div>
                </template>
            </HcisTimeline>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import { HcisEmptyState, HcisSkeleton, HcisTimeline } from '@/components/ui';

const API = '/api/v1/sop/admin/penjadwalan';

export default {
    name: 'BatchRiwayat',
    components: {
        HcisEmptyState,
        HcisSkeleton,
        HcisTimeline,
    },
    props: { idBatch: { type: String, required: true } },
    data() {
        return {
            baris: [],
            loading: false,
            expandedRows: [],
        };
    },
    computed: {
        timelineItems() {
            return this.baris.map((b, idx) => ({
                id: idx,
                title: b.judul,
                time: this.relatif(b.waktu),
                sentiment: this.sentiment(b.aksi),
                icon: this.ikon(b.aksi),
                keterangan: b.keterangan,
                oleh: b.oleh,
                sebab: b.sebab,
                catatan: b.catatan,
                karyawan: b.karyawan || [],
                jumlah: b.jumlah,
            }));
        },
    },
    mounted() {
        this.muat();
    },
    methods: {
        toggleExpand(id) {
            const idx = this.expandedRows.indexOf(id);
            if (idx > -1) {
                this.expandedRows.splice(idx, 1);
            } else {
                this.expandedRows.push(id);
            }
        },
        isExpanded(id) {
            return this.expandedRows.includes(id);
        },
        async muat() {
            this.loading = true;
            try {
                const r = await axios.get(`${API}/batch/${this.idBatch}/riwayat`);
                if (r.data.success) this.baris = r.data.result || [];
            } catch (e) {
                this.baris = [];
            } finally {
                this.loading = false;
            }
        },
        ikon(a) {
            return (
                {
                    DIBUAT: 'bi-flag-fill',
                    ASSIGN: 'bi-person-plus-fill',
                    TAMBAH_KARYAWAN: 'bi-person-plus-fill',
                    CABUT_AKSES: 'bi-person-x-fill',
                    PULIHKAN_AKSES: 'bi-person-check-fill',
                    UBAH_TENGGAT: 'bi-calendar2-check-fill',
                    UBAH_LABEL: 'bi-tag-fill',
                    UBAH_KETERANGAN: 'bi-chat-left-text-fill',
                    UBAH_REMINDER: 'bi-bell-fill',
                    INGATKAN: 'bi-send-fill',
                    KUIS_RESET: 'bi-arrow-counterclockwise',
                }[a] || 'bi-dot'
            );
        },
        sentiment(a) {
            if (a === 'CABUT_AKSES') return 'danger';
            if (a === 'DIBUAT' || a === 'ASSIGN' || a === 'TAMBAH_KARYAWAN' || a === 'PULIHKAN_AKSES') return 'success';
            if (a === 'UBAH_TENGGAT' || a === 'UBAH_REMINDER' || a === 'UBAH_KETERANGAN' || a === 'UBAH_LABEL') return 'warning';
            return 'brand';
        },
        penuh(w) {
            if (!w) return '';
            return new Date(w).toLocaleString('id-ID', { dateStyle: 'full', timeStyle: 'short' });
        },
        relatif(w) {
            if (!w) return '—';
            const t = new Date(w);
            const detik = Math.floor((Date.now() - t.getTime()) / 1000);
            if (detik < 60) return 'baru saja';
            if (detik < 3600) return `${Math.floor(detik / 60)} menit lalu`;
            if (detik < 86400) return `${Math.floor(detik / 3600)} jam lalu`;
            if (detik < 604800) return `${Math.floor(detik / 86400)} hari lalu`;

            return t.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },
    },
};
</script>
