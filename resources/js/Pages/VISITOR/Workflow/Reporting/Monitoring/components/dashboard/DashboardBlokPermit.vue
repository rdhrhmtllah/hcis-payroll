<template>
    <div class="blok-permit">
        <div class="blok-permit__duo">
            <div class="blok-permit__item">
                <HcisChart
                    type="line"
                    title="Tren permit"
                    description="Pengajuan dan keputusan permit per hari pada rentang terpilih."
                    :series="seriTren"
                    :categories="tanggalTren"
                    :height="chartHeight"
                    sentiment="purple"
                    :metric-value="totalDiajukan ? `${totalDiajukan.toLocaleString('id-ID')}` : '0'"
                    metric-unit="Pengajuan"
                    metric-label="Total Diajukan Periode Ini"
                    :metric-trend="totalDisetujui ? `${totalDisetujui} Disetujui` : undefined"
                    :loading="loading"
                    empty-text="Belum ada pergerakan permit pada rentang ini"
                />
            </div>

            <div class="blok-permit__item">
                <HcisChart
                    type="bar-horizontal"
                    title="Permit aktif per kategori"
                    description="Permit yang masih berlaku hari ini."
                    :series="seriKategori"
                    :categories="kategori.map(labelKategori)"
                    :height="chartHeight"
                    sentiment="multi"
                    :metric-value="totalPermitAktif ? `${totalPermitAktif.toLocaleString('id-ID')}` : '0'"
                    metric-unit="Permit"
                    metric-label="Total Permit Aktif Hari Ini"
                    :tooltip-formatter="formatTooltipKategori"
                    :loading="loading"
                    empty-text="Belum ada permit aktif"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { HcisChart } from '@/components/ui';

defineOptions({ name: 'DashboardBlokPermit' });

const props = defineProps({
    kategori: { type: Array, default: () => [] },
    tren: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const chartHeight = computed(() => {
    if (typeof window === 'undefined') return 300;
    if (window.innerWidth < 640) return 250;
    if (window.innerWidth < 1024) return 275;
    return 300;
});

const totalDiajukan = computed(() => props.tren.reduce((acc, t) => acc + (Number(t.diajukan) || 0), 0));
const totalDisetujui = computed(() => props.tren.reduce((acc, t) => acc + (Number(t.disetujui) || 0), 0));
const totalPermitAktif = computed(() => props.kategori.reduce((acc, k) => acc + (Number(k.jumlah) || 0), 0));

// Permit akses tamu (COKLAT) jumlahnya satu per tamu yang didaftarkan,
// sehingga batangnya jauh lebih panjang daripada permit kendaraan karyawan.
// Tanpa penanda sumber, pembaca menyimpulkan permit karyawan sedang melonjak
// padahal populasinya memang dua ranah berbeda dalam satu tabel.
const labelKategori = (k) => (k?.sumber === 'TAMU' ? `${k.kategori} (tamu)` : k?.kategori ?? '-');

// Tanggalnya dikirim server lengkap termasuk hari bernilai nol; memampatkan
// hari kosong di sini akan membuat dua pengajuan berjarak sepekan terbaca
// seolah terjadi berturut-turut.
const tanggalTren = computed(() => props.tren.map((t) => t.tanggal));

const seriTren = computed(() => {
    if (!props.tren.length) return [];

    return [
        { name: 'Diajukan', data: props.tren.map((t) => t.diajukan ?? 0) },
        { name: 'Disetujui', data: props.tren.map((t) => t.disetujui ?? 0) },
        { name: 'Ditolak', data: props.tren.map((t) => t.ditolak ?? 0) },
    ];
});

function resolveKategoriColor(k) {
    const raw = String(k?.kategori || (typeof k === 'string' ? k : '') || '').toLowerCase();
    const hex = String(k?.kode_warna || '').toLowerCase();

    // 1. Prioritas kode hex eksplisit
    if (hex.includes('dc2626') || hex.includes('ef4444') || hex.includes('red')) {
        return { main: '#ef4444', dark: '#dc2626', base: '#ef4444', shadow: 'rgba(239, 68, 68, 0.4)' };
    }
    if (hex.includes('16a34a') || hex.includes('10b981') || hex.includes('green') || hex.includes('emerald')) {
        return { main: '#10b981', dark: '#059669', base: '#10b981', shadow: 'rgba(16, 185, 129, 0.4)' };
    }
    if (hex.includes('d97706') || hex.includes('eab308') || hex.includes('f59e0b') || hex.includes('yellow') || hex.includes('amber')) {
        return { main: '#f59e0b', dark: '#d97706', base: '#f59e0b', shadow: 'rgba(245, 158, 11, 0.4)' };
    }
    if (hex.includes('2563eb') || hex.includes('3b82f6') || hex.includes('blue') || hex.includes('indigo') || hex.includes('4f46e5')) {
        return { main: '#3b82f6', dark: '#2563eb', base: '#3b82f6', shadow: 'rgba(59, 130, 246, 0.4)' };
    }
    if (hex.includes('92400e') || hex.includes('a16207') || hex.includes('b45309') || hex.includes('78350f') || hex.includes('brown')) {
        return { main: '#b45309', dark: '#78350f', base: '#b45309', shadow: 'rgba(180, 83, 9, 0.4)' };
    }
    if (hex.includes('f97316') || hex.includes('ea580c') || hex.includes('orange')) {
        return { main: '#f97316', dark: '#ea580c', base: '#f97316', shadow: 'rgba(249, 115, 22, 0.4)' };
    }
    if (hex.includes('7c3aed') || hex.includes('8b5cf6') || hex.includes('purple') || hex.includes('violet')) {
        return { main: '#8b5cf6', dark: '#7c3aed', base: '#8b5cf6', shadow: 'rgba(139, 92, 246, 0.4)' };
    }
    if (hex.includes('334155') || hex.includes('1e293b') || hex.includes('black') || hex.includes('slate')) {
        return { main: '#475569', dark: '#1e293b', base: '#475569', shadow: 'rgba(51, 65, 85, 0.4)' };
    }

    // 2. Pemetaan berdasarkan nama kategori
    if (raw.includes('merah') || raw.includes('red') || raw.includes('vip') || raw.includes('direksi')) {
        return { main: '#ef4444', dark: '#dc2626', base: '#ef4444', shadow: 'rgba(239, 68, 68, 0.4)' };
    }
    if (raw.includes('kuning') || raw.includes('yellow') || raw.includes('amber') || raw.includes('vendor') || raw.includes('kontraktor')) {
        return { main: '#f59e0b', dark: '#d97706', base: '#f59e0b', shadow: 'rgba(245, 158, 11, 0.4)' };
    }
    if (raw.includes('hijau') || raw.includes('green') || raw.includes('emerald')) {
        return { main: '#10b981', dark: '#059669', base: '#10b981', shadow: 'rgba(16, 185, 129, 0.4)' };
    }
    if (raw.includes('biru') || raw.includes('blue') || raw.includes('karyawan') || raw.includes('staff')) {
        return { main: '#3b82f6', dark: '#2563eb', base: '#3b82f6', shadow: 'rgba(59, 130, 246, 0.4)' };
    }
    if (raw.includes('coklat') || raw.includes('brown') || raw.includes('tamu')) {
        return { main: '#b45309', dark: '#78350f', base: '#b45309', shadow: 'rgba(180, 83, 9, 0.4)' };
    }
    if (raw.includes('oranye') || raw.includes('orange')) {
        return { main: '#f97316', dark: '#ea580c', base: '#f97316', shadow: 'rgba(249, 115, 22, 0.4)' };
    }
    if (raw.includes('ungu') || raw.includes('purple') || raw.includes('violet')) {
        return { main: '#8b5cf6', dark: '#7c3aed', base: '#8b5cf6', shadow: 'rgba(139, 92, 246, 0.4)' };
    }
    if (raw.includes('hitam') || raw.includes('black')) {
        return { main: '#475569', dark: '#1e293b', base: '#475569', shadow: 'rgba(51, 65, 85, 0.4)' };
    }
    if (raw.includes('putih') || raw.includes('white') || raw.includes('silver') || raw.includes('abu')) {
        return { main: '#94a3b8', dark: '#64748b', base: '#94a3b8', shadow: 'rgba(100, 116, 139, 0.4)' };
    }

    // Default fallback: teal
    return { main: '#14b8a6', dark: '#0d9488', base: '#14b8a6', shadow: 'rgba(20, 184, 166, 0.4)' };
}

const seriKategori = computed(() => {
    if (!props.kategori.length) return [];

    const data = props.kategori.map((k) => {
        const palet = resolveKategoriColor(k);
        return {
            value: Number(k.jumlah) || 0,
            name: labelKategori(k),
            itemStyle: {
                color: {
                    type: 'linear',
                    x: 0,
                    y: 0,
                    x2: 1,
                    y2: 0,
                    colorStops: [
                        { offset: 0, color: `${palet.base}55` },
                        { offset: 0.85, color: palet.main },
                        { offset: 1, color: palet.dark },
                    ],
                },
                borderRadius: [0, 999, 999, 0],
                shadowColor: palet.shadow,
                shadowBlur: 8,
            },
            emphasis: {
                itemStyle: {
                    color: palet.main,
                    shadowBlur: 14,
                    shadowColor: palet.shadow,
                },
            },
        };
    });

    return [
        {
            name: 'Permit',
            data,
        },
    ];
});

const formatTooltipKategori = (params) => {
    if (!Array.isArray(params) || params.length === 0) return '';
    const item = params[0];
    const palet = resolveKategoriColor(item.name);
    const dot = `<span style="display:inline-block;margin-right:6px;border-radius:50%;width:8px;height:8px;background-color:${palet.main};box-shadow:0 0 6px ${palet.shadow};"></span>`;
    return `<div style="font-weight: 750; margin-bottom: 4px; color: #0f172a;">${item.name}</div>
    <div style="font-size: 11.5px; display: flex; align-items: center; justify-content: space-between; gap: 14px;">
        <span>${dot}Permit Aktif</span>
        <strong style="color: #0f172a; font-family: monospace; font-size: 12px;">${item.value} Permit</strong>
    </div>`;
};

defineExpose({
    seriTren,
    tanggalTren,
    seriKategori,
    labelKategori,
    chartHeight,
    totalDiajukan,
    totalDisetujui,
    totalPermitAktif,
});
</script>

<style scoped>
.blok-permit {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.blok-permit__duo {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
    width: 100%;
    min-width: 0;
    align-items: stretch;
}

.blok-permit__item {
    min-width: 0;
    width: 100%;
    display: flex;
    flex-direction: column;
}

.blok-permit__item :deep(.hcis-chart-wrapper) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.blok-permit__item :deep(.hcis-card) {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.blok-permit__item :deep(.hcis-card__body) {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

@media (max-width: 1024px) {
    .blok-permit__duo {
        grid-template-columns: 1fr;
        gap: 0.85rem;
    }
}
</style>
