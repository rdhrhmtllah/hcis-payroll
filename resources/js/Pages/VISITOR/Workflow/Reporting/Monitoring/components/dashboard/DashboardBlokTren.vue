<template>
    <div class="blok-tren">
        <HcisAlert
            v-if="dataTipis"
            variant="warning"
            title="Angka kunjungan belum layak dipercaya"
            description="Banyak kunjungan tidak punya waktu keluar, sehingga tren dan jam sibuk di bawah ini belum menggambarkan keadaan sebenarnya."
            class="mb-3"
        />

        <div class="blok-tren__duo">
            <div class="blok-tren__item">
                <HcisChart
                    type="line"
                    title="Tren kunjungan"
                    description="Perbandingan volume transaksi registrasi vs total orang (rombongan)."
                    :series="seriTren"
                    :categories="kategoriTren"
                    :height="chartHeight"
                    sentiment="purple"
                    :metric-value="totalOrang ? `${totalOrang.toLocaleString('id-ID')}` : '0'"
                    metric-unit="Orang"
                    metric-label="Total Kunjungan Periode Ini"
                    :metric-trend="totalTransaksi ? `${totalTransaksi} Transaksi` : undefined"
                    :insight="trenInsight"
                    :loading="loading"
                    empty-text="Belum ada kunjungan pada rentang ini"
                />
            </div>
            <div class="blok-tren__item">
                <HcisChart
                    type="heatmap"
                    title="Jam sibuk kedatangan"
                    category-unit="jam"
                    value-label="Jumlah Kedatangan"
                    value-suffix="Tamu"
                    description="Matriks intensitas waktu check-in tamu (24 Jam x 7 Hari)."
                    :data="dataJam"
                    :categories="jamSumbuX"
                    :series="hariSumbuY"
                    :height="chartHeight"
                    sentiment="teal"
                    :metric-value="jamStat.total > 0 ? jamStat.puncakStr : '-'"
                    metric-label="Jam Puncak Check-in"
                    :metric-trend="jamStat.total > 0 ? `${jamStat.maxCount} Tamu` : undefined"
                    :insight="jamInsight"
                    :loading="loading"
                    empty-text="Belum ada waktu check-in tercatat"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisAlert, HcisChart } from '@/components/ui';

defineOptions({ name: 'DashboardBlokTren' });

const props = defineProps({
    tren: { type: Array, default: () => [] },
    sebaranJam: { type: Array, default: () => [] },
    dataTipis: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
});

const chartHeight = computed(() => {
    if (typeof window === 'undefined') return 300;
    if (window.innerWidth < 640) return 250;
    if (window.innerWidth < 1024) return 275;
    return 300;
});

const totalOrang = computed(() => props.tren.reduce((acc, t) => acc + (Number(t.orang) || 0), 0));
const totalTransaksi = computed(() => props.tren.reduce((acc, t) => acc + (Number(t.transaksi) || 0), 0));

const trenInsight = computed(() => {
    if (!totalOrang.value || !totalTransaksi.value) return null;
    const rasio = (totalOrang.value / totalTransaksi.value).toFixed(1).replace('.0', '');
    return `Rata-rata volume rombongan ${rasio} orang per transaksi registrasi.`;
});

const kategoriTren = computed(() => props.tren.map((t) => t.tanggal));

const seriTren = computed(() => [
    { name: 'Transaksi', data: props.tren.map((t) => t.transaksi) },
    { name: 'Orang', data: props.tren.map((t) => t.orang) },
]);

const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const jamSumbuX = computed(() => Array.from({ length: 24 }, (_, jam) => `${jam}`));

const hariSumbuY = computed(() => [{ categories: HARI }]);

const dataJam = computed(() => {
    const ember = new Map();

    props.sebaranJam.forEach((baris) => {
        const hari = new Date(`${baris.tanggal}T00:00:00`).getDay();
        const kunci = `${hari}|${baris.jam}`;
        ember.set(kunci, (ember.get(kunci) ?? 0) + baris.jumlah);
    });

    const titik = [];
    for (let hari = 0; hari < HARI.length; hari += 1) {
        for (let jam = 0; jam < 24; jam += 1) {
            titik.push([jam, hari, ember.get(`${hari}|${jam}`) ?? 0]);
        }
    }

    return titik;
});

const jamStat = computed(() => {
    const jamMap = new Array(24).fill(0);
    props.sebaranJam.forEach((item) => {
        const j = Number(item.jam);
        if (j >= 0 && j < 24) {
            jamMap[j] += Number(item.jumlah) || 0;
        }
    });

    let maxHour = -1;
    let maxCount = 0;
    let total = 0;

    jamMap.forEach((count, h) => {
        total += count;
        if (count > maxCount) {
            maxCount = count;
            maxHour = h;
        }
    });

    return {
        maxHour,
        maxCount,
        total,
        puncakStr: maxHour >= 0 ? `${String(maxHour).padStart(2, '0')}:00` : '-',
    };
});

const jamInsight = computed(() => {
    if (!jamStat.value.total) return null;
    return `Intensitas check-in tertinggi berada di pukul ${jamStat.value.puncakStr} WIB (${jamStat.value.maxCount} tamu).`;
});

defineExpose({ seriTren, kategoriTren, dataJam, jamSumbuX, hariSumbuY });
</script>

<style scoped>
.blok-tren {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.blok-tren__duo {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    width: 100%;
    min-width: 0;
    align-items: stretch;
}

.blok-tren__item {
    min-width: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
}

.blok-tren__item :deep(.hcis-chart-wrapper) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.blok-tren__item :deep(.hcis-card) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.blok-tren__item :deep(.hcis-card__body) {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

@media (max-width: 1024px) {
    .blok-tren__duo {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
}
</style>
