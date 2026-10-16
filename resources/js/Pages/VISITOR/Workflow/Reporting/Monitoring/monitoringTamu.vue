<template>
    <VisitorWorkspace
        :title="tipeTamu === 'B2B' ? 'Monitoring Tamu B2B' : 'Monitoring Tamu Regular'"
        subtitle="Pantau keadaan kunjungan tamu harian dari sumber lifecycle yang konsisten."
        kicker="Visitor · Monitoring"
        :icon="tipeTamu === 'B2B' ? 'truck' : 'people-fill'"
        sentiment="purple"
        section-title="Keadaan kunjungan tamu"
        section-description="Ringkasan hari ini dan daftar lengkapnya. Perakitan berkas laporan ada di halaman Export Studio."
        section-icon="file-earmark-bar-graph-fill"
    >
        <TamuMonitoringPanel :needs-approval="needsApproval" :boleh-buka-permit="bolehBukaPermit" />
    </VisitorWorkspace>
</template>

<script>
import VisitorWorkspace from '@/components/VISITOR/VisitorWorkspace.vue';
import TamuMonitoringPanel from './components/TamuMonitoringPanel.vue';

/**
 * Monitoring Tamu — halamannya sendiri.
 *
 * Dipisah dari halaman gabungan (DML-2026-105) supaya hak aksesnya bisa
 * diberikan terpisah: admin yang mengurus permit tidak perlu — dan tidak lagi
 * bisa — melihat antrean tamu, termasuk lewat endpointnya langsung.
 *
 * `tabs` sengaja tidak diisi: shell-nya menyembunyikan bilah tab ketika kosong
 * dan merender slot bawaannya, sehingga tidak ada satu tab tunggal yang
 * menganggur di atas panel.
 */
export default {
    name: 'MonitoringTamuPage',
    components: {
        VisitorWorkspace,
        TamuMonitoringPanel,
    },
    props: {
        user: { type: Object, default: () => ({}) },
        // Hak kategori pemakai, dari matriks. Dikirim server supaya layar tidak
        // menyimpulkannya sendiri: daftar yang kosong karena izin dan daftar
        // yang kosong karena memang tak ada tamu terlihat sama persis, dan
        // hanya server yang tahu bedanya.
        kategoriBolehLihat: { type: Array, default: () => [] },
        departemen: { type: Array, default: () => [] },
        needsApproval: { type: Boolean, default: false },
        // Dari server, bukan disimpulkan layar: admin tamu murni tidak punya
        // kunci halaman permit, dan tombol yang tetap tampil untuknya berujung
        // 403 SETELAH ia menekannya.
        bolehBukaPermit: { type: Boolean, default: false },
        // Ditetapkan rute (DDL-2026-121): Regular dan B2B berbagi komponen,
        // endpoint-nya berbeda dan masing-masing dijaga kunci halamannya.
        tipeTamu: { type: String, default: 'REGULAR' },
        endpoints: { type: Object, default: null },
    },
    provide() {
        return this.endpoints ? { visitorTamuEndpoints: this.endpoints } : {};
    },
};
</script>
