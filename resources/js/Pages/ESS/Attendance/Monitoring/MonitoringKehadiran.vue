<template>
    <div class="mk-page">
        <HcisHeader
            title="Monitoring Kehadiran"
            kicker="Administrator HCIS · Pemantauan"
            subtitle="Analitik kehadiran, transaksi karyawan, dan tren per periode — sesuai cakupan akses Anda."
            icon="activity"
            sentiment="brand"
        >
            <template #tabs>
                <HcisTabs
                    v-model="tab"
                    :tabs="TABS"
                    variant="header"
                    aria-label="Tampilan monitoring kehadiran"
                />
            </template>
        </HcisHeader>

        <TabHariIni
            v-if="tab === 'hari-ini'"
            :opsi-saringan="opsiSaringan"
            :organisasi="organisasi"
            :hari-ini="hariIni"
            :tanggal-awal="tanggalHariIni"
            :tautan="tautan"
            :kategori="kategori"
            @update:organisasi="organisasi = $event"
        />
        <TabRekapPeriode
            v-else
            :opsi-saringan="opsiSaringan"
            :organisasi="organisasi"
            :daftar-periode="daftarPeriode"
            :maks-hari="maksHari"
            :tampilan-awal="tampilanRekap"
            @update:organisasi="organisasi = $event"
            @update:tampilan="tampilanRekap = $event"
            @buka-hari="bukaHari"
        />
    </div>
</template>

<script>
import { HcisHeader, HcisTabs } from '@/components/ui';
import TabHariIni from './Components/TabHariIni.vue';
import TabRekapPeriode from './Components/TabRekapPeriode.vue';
import { organisasiKosong } from './Components/saringanMonitoring.js';

const TABS = [
    { key: 'hari-ini', label: 'Hari Ini', icon: 'clock-history' },
    { key: 'rekap', label: 'Rekap Periode', icon: 'calendar3' },
];

export default {
    name: 'MonitoringKehadiran',
    components: { HcisHeader, HcisTabs, TabHariIni, TabRekapPeriode },
    props: {
        opsiSaringan: { type: Object, default: () => ({}) },
        daftarPeriode: { type: Array, default: () => [] },
        hariIni: { type: String, required: true },
        tautan: { type: Object, default: () => ({}) },
        kategori: { type: Array, default: () => [] },
        maksHari: { type: Number, default: 62 },
    },
    data() {
        return {
            TABS,
            tab: 'hari-ini',
            organisasi: organisasiKosong(),
            tanggalHariIni: '',
            tampilanRekap: 'grafik',
        };
    },
    watch: {
        tab(v) {
            // Tanggal hasil klik peta panas hanya berlaku untuk satu kunjungan.
            if (v === 'rekap') this.tanggalHariIni = '';
        },
    },
    methods: {
        /** Klik sel peta panas: lihat siapa saja pada tanggal & unit itu. */
        bukaHari({ tanggal, dimensi, kelompok }) {
            this.organisasi = { ...organisasiKosong(), [dimensi]: [kelompok] };
            this.tanggalHariIni = tanggal;
            this.tab = 'hari-ini';
        },
    },
};
</script>

<style scoped>
.mk-page {
    display: flex;
    flex-direction: column;
    gap: var(--hcis-space-4, 1rem);
}
</style>
