<template>
    <div class="ap-dasbor">
        <!-- Angka utama: klik untuk membuka tab rinciannya. -->
        <HcisStatGroup
            :stats="statCards"
            :cols="5"
            gap="sm"
            appearance="crystal"
            layout="compact"
            class="mb-3"
            @item-click="klikStat"
        />

        <div class="row g-3">
            <div class="col-12 col-xl-8">
                <HcisChart
                    type="bar"
                    title="Aktivitas perubahan akses"
                    description="Jumlah perubahan tercatat di audit per hari, 30 hari terakhir."
                    icon="activity"
                    :series="[{ name: 'Perubahan', data: tren.map((t) => t.jumlah) }]"
                    :categories="tren.map((t) => labelHari(t.tanggal))"
                    :height="tinggi"
                    sentiment="brand"
                    :metric-value="totalTren.toLocaleString('id-ID')"
                    metric-unit="perubahan"
                    metric-label="30 hari terakhir"
                    empty-text="Belum ada perubahan"
                />
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <HcisChart
                    type="donut"
                    title="Penugasan per tingkat"
                    description="Sebaran penugasan aktif menurut tingkat peran."
                    icon="diagram-3"
                    :data="dataTingkat"
                    :height="tinggi"
                    sentiment="multi"
                    :options="opsiDonut"
                    metric-label="penugasan"
                    empty-text="Belum ada penugasan"
                />
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <HcisChart
                    type="bar-horizontal"
                    title="Peran terbanyak pemegang"
                    description="Delapan peran dengan pemegang terbanyak."
                    icon="person-badge"
                    :series="[{ name: 'Pemegang', data: teratas.map((p) => p.jumlah) }]"
                    :categories="teratas.map((p) => p.nama)"
                    :height="tinggi"
                    sentiment="teal"
                    empty-text="Belum ada pemegang"
                />
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <HcisChart
                    type="donut"
                    title="Masa berlaku penugasan"
                    description="Berapa lama penugasan aktif masih berlaku."
                    icon="hourglass-split"
                    :data="dataMasa"
                    :height="tinggi"
                    sentiment="multi"
                    :options="opsiDonut"
                    metric-label="penugasan"
                    empty-text="Belum ada penugasan"
                />
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <HcisChart
                    type="donut"
                    title="Jenis perubahan"
                    description="Perubahan 30 hari terakhir menurut jenisnya."
                    icon="clock-history"
                    :data="dataAksi"
                    :height="tinggi"
                    sentiment="multi"
                    :options="opsiDonut"
                    metric-label="perubahan"
                    empty-text="Belum ada perubahan"
                />
            </div>
            <div v-if="modul.length > 1" class="col-12">
                <HcisChart
                    type="bar"
                    title="Per modul"
                    description="Jumlah peran, penugasan, dan penugasan admin di tiap modul."
                    icon="grid-3x3-gap"
                    :series="seriModul"
                    :categories="modul.map((m) => m.kode)"
                    :height="tinggi"
                    sentiment="multi"
                    :options="{ xAxis: { axisLabel: { interval: 0 } } }"
                    empty-text="Belum ada data modul"
                />
            </div>
            <div v-if="kembar.length" class="col-12">
                <HcisCard
                    title="Peran berhak identik"
                    description="Kelompok peran yang halaman dan aksinya persis sama. Kandidat digabung; perbedaan unit cukup lewat cakupan."
                    icon="intersect"
                    sentiment="warning"
                    :badge="`${kembar.length} kelompok`"
                    badge-variant="warning"
                >
                    <div class="d-flex flex-column gap-3">
                        <div v-for="(k, i) in kembar" :key="i" class="ap-kembar">
                            <div class="ap-kembar__judul">
                                {{ k.peran.length }} peran · {{ k.halaman }} halaman ·
                                {{ k.peran.reduce((a, p) => a + p.pemegang, 0) }} pemegang
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <HcisBadge
                                    v-for="p in k.peran"
                                    :key="p.id"
                                    :label="`${p.nama} (${p.pemegang})`"
                                    variant="neutral"
                                    appearance="subtle"
                                    size="xs"
                                />
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <HcisButton
                            variant="ghost"
                            size="xs"
                            trailing-icon="arrow-right"
                            label="Buka Peran & Hak"
                            @click="emit('buka-tab', 'peran')"
                        />
                    </div>
                </HcisCard>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisBadge, HcisButton, HcisCard, HcisChart, HcisStatGroup } from '@/components/ui';
import { infoTingkat } from '../api/aksesPeranApi.js';
import { labelAksi } from '../api/formatAudit.js';

/** Ringkasan = dasbor statistik. Tindakan ada di tab masing-masing. */
const props = defineProps({
    data: { type: Object, required: true },
    anda: { type: Object, default: null },
});
const emit = defineEmits(['buka-tab', 'berubah']);
const tinggi = 300;
// Donat sedikit lebih kecil & naik agar legenda di bawah tak menimpanya.
const opsiDonut = { title: { top: '34%' }, series: [{ radius: ['42%', '62%'], center: ['50%', '40%'] }] };

const stat = computed(() => props.data.statistik || {});
const modul = computed(() => props.data.modul || []);
const tren = computed(() => stat.value.tren_audit || []);
const teratas = computed(() => stat.value.peran_teratas || []);
const kembar = computed(() => props.data.perlu_perhatian?.peran_kembar || []);
const totalTren = computed(() => tren.value.reduce((a, t) => a + t.jumlah, 0));
const jumlahPerhatian = (k) => props.data.perlu_perhatian?.[k]?.length || 0;

const statCards = computed(() => {
    const totalPeran = Object.values(props.data.jenis_role || {}).reduce((a, b) => a + Number(b), 0);
    const perTingkat = stat.value.per_tingkat || {};
    // Termasuk peran lintas modul — sama dengan total donat "Penugasan per tingkat".
    const totalPenugasan = Object.values(perTingkat).reduce((a, n) => a + n, 0);
    const admin = (perTingkat.SUPER_ADMIN || 0) + (perTingkat.ADMIN || 0);
    const segera = props.data.penugasan_segera_kedaluwarsa || 0;
    const tanpaPemegang = jumlahPerhatian('peran_tanpa_pemegang');
    return [
        {
            key: 'peran',
            label: 'Peran aktif',
            value: totalPeran,
            icon: 'person-badge',
            sentiment: 'brand',
            interactive: true,
            tab: 'peran',
            caption: tanpaPemegang ? `${tanpaPemegang} belum ada pemegang` : 'Semua sudah ada pemegang',
        },
        {
            key: 'pemegang',
            label: 'Karyawan berperan',
            value: stat.value.pemegang_unik || 0,
            icon: 'people-fill',
            sentiment: 'info',
            interactive: true,
            tab: 'pengguna',
            caption: `${totalPenugasan} penugasan aktif`,
        },
        {
            key: 'admin',
            label: 'Penugasan admin',
            value: admin,
            icon: 'shield-check',
            sentiment: 'purple',
            interactive: true,
            tab: 'admin',
            caption: `Super Admin ${perTingkat.SUPER_ADMIN || 0} · Admin ${perTingkat.ADMIN || 0}`,
        },
        {
            key: 'segera',
            label: 'Segera berakhir',
            value: segera,
            icon: 'hourglass-split',
            sentiment: segera ? 'warning' : 'success',
            interactive: true,
            payload: { tab: 'pengguna', hanyaSegeraBerakhir: true },
            caption: 'Habis dalam ≤ 30 hari',
        },
        {
            key: 'audit',
            label: 'Perubahan 7 hari',
            value: props.data.audit_7_hari || 0,
            icon: 'clock-history',
            sentiment: 'neutral',
            interactive: true,
            tab: 'audit',
            caption: 'Lihat jejak audit',
        },
    ];
});

const dataTingkat = computed(() =>
    ['SUPER_ADMIN', 'ADMIN', 'PENGGUNA']
        .map((k) => ({ name: infoTingkat[k]?.label || k, value: stat.value.per_tingkat?.[k] || 0 }))
        .filter((d) => d.value),
);
const dataMasa = computed(() => {
    const m = stat.value.masa_berlaku || {};
    return [
        { name: 'Tanpa batas', value: m.tanpa_batas || 0 },
        { name: '> 90 hari', value: m.lebih_90 || 0 },
        { name: '31–90 hari', value: m.hari_31_90 || 0 },
        { name: '≤ 30 hari', value: m.maks_30 || 0 },
    ].filter((d) => d.value);
});
const dataAksi = computed(() =>
    Object.entries(stat.value.aksi_audit || {})
        .map(([k, v]) => ({ name: labelAksi[k] || k, value: v }))
        .sort((a, b) => b.value - a.value),
);
const seriModul = computed(() => [
    { name: 'Peran', data: modul.value.map((m) => m.jumlah_peran) },
    { name: 'Penugasan', data: modul.value.map((m) => m.jumlah_penugasan) },
    { name: 'Admin', data: modul.value.map((m) => m.jumlah_admin) },
]);

function labelHari(tanggal) {
    return new Date(`${tanggal}T00:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function klikStat({ item }) {
    emit('buka-tab', item.payload || item.tab);
}
</script>

<style scoped>
.ap-kembar__judul {
    margin-bottom: var(--hcis-space-1, 0.25rem);
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--hcis-text-strong);
}
</style>
