<template>
    <div class="sop-stack bab-kesehatan">
        <!-- Keadaan Memuat, Galat, & Kosong -->
        <div v-if="memuat" class="bkd-memuat">
            <div class="spinner-border spinner-border-sm text-primary"></div>
            <span>Memuat kesehatan dokumen…</span>
        </div>

        <HcisAlert
            v-else-if="galat"
            variant="danger"
            appearance="subtle"
            :description="galat"
        />

        <HcisEmptyState
            v-else-if="!total"
            icon="folder-x"
            title="Belum Ada Dokumen Master"
            description="Master dokumen masih kosong."
            variant="flat"
            sentiment="neutral"
            size="sm"
        />

        <div v-if="!memuat && !galat && total" class="bkd-container">
            <!-- ══ GRID DETAIL CARDS ══ -->
            <div class="bkd-grid">
                <!-- 1. Antrean Kerja Dokumen (Urgent Governance Action) -->
                <HcisCard
                    variant="glass"
                    sentiment="warning"
                    size="sm"
                    icon="list-check"
                    pattern="crosshair"
                    title="Antrean Kerja Dokumen"
                    :description="`${data.Antrean.Total} temuan tata kelola yang memerlukan tindakan.`"
                    class="bkd-card-full"
                >
                    <template #actions>
                        <HcisButton
                            label="Master Dokumen"
                            leading-icon="folder2-open"
                            variant="ghost"
                            size="sm"
                            @click="keMaster"
                        />
                    </template>

                    <PanelAntreanKerja
                        :antrean="data.Antrean"
                        :tersaring="data.Tersaring_Gerbang_Publik"
                    />
                </HcisCard>

                <!-- 2. Lead Time Penerbitan Dokumen -->
                <HcisCard
                    variant="glass"
                    sentiment="brand"
                    size="sm"
                    icon="speedometer2"
                    pattern="dots"
                    title="Lead Time Penerbitan Dokumen"
                    description="Durasi penyusunan dari pengajuan awal hingga dokumen resmi terbit."
                    class="bkd-card-full"
                >
                    <PanelLeadTime :lead-time="data.Lead_Time" />
                </HcisCard>

                <!-- 3. Sebaran Dokumen Master -->
                <HcisCard
                    variant="glass"
                    sentiment="neutral"
                    size="sm"
                    icon="pie-chart-fill"
                    pattern="grid"
                    title="Sebaran Dokumen Master"
                    description="Distribusi dokumen aktif berdasarkan 5 dimensi hirarki dan pengelompokan."
                    class="bkd-card-full"
                >
                    <PanelSebaranDokumen :sebaran="data.Sebaran" />
                </HcisCard>
            </div>
        </div>
    </div>
</template>

<script>
import { router } from '@inertiajs/vue3';
import { HcisCard, HcisButton, HcisAlert, HcisEmptyState } from '@/components/ui';
import { ambil } from './agregat.js';
import PanelSebaranDokumen from './panel/PanelSebaranDokumen.vue';
import PanelAntreanKerja from './panel/PanelAntreanKerja.vue';
import PanelLeadTime from './panel/PanelLeadTime.vue';

const KOSONG = {
    Komposisi: { Total: 0, Baris: [] },
    Sebaran: { Total: 0, Sumbu: [], Data: {} },
    Versi_Menggantung: { Total: 0, Baris: [] },
    Antrean: { Baris: [], Total: 0 },
    Tersaring_Gerbang_Publik: { Penugasan: 0, Paket: 0 },
    Lead_Time: { Total_Dokumen_Terbit: 0, Rerata_Hari: 0, Tercepat_Hari: 0, Terlama_Hari: 0, Distribusi: {}, Daftar: [] },
};

export default {
    name: 'BabKesehatanDokumen',
    components: {
        HcisCard,
        HcisButton,
        HcisAlert,
        HcisEmptyState,
        PanelSebaranDokumen,
        PanelAntreanKerja,
        PanelLeadTime,
    },
    props: {
        saringan: { type: Object, default: () => ({}) },
    },
    emits: ['ringkas'],
    data() {
        return { data: { ...KOSONG }, memuat: false, galat: '' };
    },
    computed: {
        total() {
            return this.data?.Komposisi?.Total || 0;
        },
    },
    watch: {
        saringan: { immediate: true, deep: true, handler() { this.muat(); } },
    },
    methods: {
        async muat() {
            this.memuat = true;
            this.galat = '';
            try {
                this.data = { ...KOSONG, ...(await ambil('/dokumen/kesehatan', this.saringan)) };
                this.$emit('ringkas', {
                    siap: true,
                    total: this.total,
                    fungsional: this.jumlah('DITUGASKAN'),
                    publik: this.jumlah('BEREDAR'),
                    belumBeredar: this.jumlah('BELUM_BEREDAR'),
                    tersaring: this.data.Tersaring_Gerbang_Publik,
                    leadTime: {
                        rerata: this.data.Lead_Time?.Rerata_Hari || 0,
                        terbit: this.data.Lead_Time?.Total_Dokumen_Terbit || 0,
                    },
                });
            } catch (e) {
                this.data = { ...KOSONG };
                this.galat = e?.message || 'Gagal memuat kesehatan dokumen.';
                this.$emit('ringkas', { siap: true });
            } finally {
                this.memuat = false;
            }
        },

        jumlah(kelompok) {
            return this.data.Komposisi.Baris.find((b) => b.Kelompok === kelompok)?.Jumlah || 0;
        },

        keMaster() {
            router.visit('/sop/admin/dokumen');
        },
    },
};
</script>

<style scoped>
.bab-kesehatan {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.bkd-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

/* ══ GRID DETAIL CARDS ══ */
.bkd-grid {
    display: flex;
    flex-direction: column;
    gap: 1.125rem;
}

.bkd-card-full {
    width: 100%;
}

.bkd-memuat {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 0;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-500, var(--hcis-slate-500));
}
</style>
