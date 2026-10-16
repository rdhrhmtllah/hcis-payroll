<template>
    <div class="container-fluid px-0 sop-scope sop-prioritas-page">
        <!-- ══ 1. EXECUTIVE HEADER ══ -->
        <HcisHeader
            title="Prioritas Dokumen"
            kicker="Tata Kelola · Master Data"
            description="Tingkat urgensi paket dokumen — menentukan urutan di papan risiko dan angka kepatuhan."
            icon="sliders"
            sentiment="brand"
            size="md"
            animated
            class="mb-4"
        >
            <template #badge>
                <HcisBadge
                    v-if="jumlah !== null"
                    :label="`${jumlah} Tingkat`"
                    variant="brand"
                    appearance="glass"
                    size="sm"
                    dot
                />
            </template>

            <template #actions>
                <div class="d-flex align-items-center gap-2">
                    <HcisButton
                        variant="glass"
                        size="md"
                        leading-icon="arrow-clockwise"
                        label="Segarkan"
                        title="Segarkan data prioritas"
                        aria-label="Segarkan data prioritas"
                        @click="segarkan"
                    />
                    <HcisButton
                        variant="primary"
                        size="md"
                        leading-icon="plus-lg"
                        label="Tambah Prioritas"
                        title="Tambah tingkat prioritas baru"
                        aria-label="Tambah tingkat prioritas baru"
                        @click="bukaTambah"
                    />
                </div>
            </template>
        </HcisHeader>

        <div class="mb-5 sop-stack">
            <SopPrioritasMaster ref="master" @berubah="catatJumlah" />
        </div>
    </div>
</template>

<script>
import SopPrioritasMaster from '../../Shared/components/ui/SopPrioritasMaster.vue';
import { HcisHeader, HcisBadge, HcisButton } from '@/components/ui';
import { setPrioritas } from '../../Shared/support/prioritas.js';
import pageMeta from '../../Shared/support/pageMeta.js';
import '../../Shared/support/sopTheme.css';

/**
 * Halaman master Prioritas Dokumen.
 *
 * KEPALANYA MEMAKAI HcisHeader, seragam dengan Jenis Dokumen dan Master APQC.
 * Bentuk sebelumnya menyusun kepalanya sendiri dari kelas `.shero-*` yang TAK
 * PERNAH didefinisikan di mana pun — halamannya karena itu tampil tanpa gaya
 * sama sekali. Tak ada yang menyadarinya karena halaman ini belum punya baris
 * menu sampai DML-2026-057; praktis tak ada yang pernah membukanya.
 *
 * Jumlah tingkat datang dari komponen masternya lewat peristiwa `berubah`,
 * bukan dari permintaan kedua: dua sumber untuk angka yang sama adalah cara
 * termurah membuat lencana di kepala berbeda dari daftar di bawahnya.
 */
export default {
    name: 'SopPrioritas',
    components: { SopPrioritasMaster, HcisHeader, HcisBadge, HcisButton },
    mixins: [pageMeta],
    props: { prioritas: { type: Array, default: () => [] } },
    data() {
        return { jumlah: null };
    },
    created() {
        setPrioritas(this.prioritas);
    },
    methods: {
        catatJumlah(n) {
            this.jumlah = n;
        },
        bukaTambah() {
            this.$refs.master?.buka();
        },
        segarkan() {
            this.$refs.master?.muat();
        },
    },
};
</script>
