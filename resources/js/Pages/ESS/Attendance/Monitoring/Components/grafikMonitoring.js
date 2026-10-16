// Pembentuk data grafik Monitoring Kehadiran — fungsi murni, diuji Vitest.
// Warna tidak pernah ditulis mentah: dibaca dari token CSS design system saat jalan.

/** Nilai token CSS (mis. '--hcis-success-500'); undefined bila tak terbaca (ECharts memakai palet bawaan). */
export function warnaToken(nama) {
    try {
        const v = getComputedStyle(document.documentElement).getPropertyValue(nama).trim();
        return v || undefined;
    } catch {
        return undefined;
    }
}

/** Token warna per kategori kehadiran, selaras dengan sentiment kartu. */
export const TOKEN_KATEGORI = {
    tepat_waktu: '--hcis-success-500',
    terlambat: '--hcis-warning-500',
    belum_absen: '--hcis-danger-500',
    belum_jam: '--hcis-neutral-500',
    sakit_izin: '--hcis-info-500',
    cuti: '--hcis-purple-500',
    libur: '--hcis-slate-500',
    tanpa_jadwal: '--hcis-warning-500',
};

export const WARNA_FALLBACK = {
    '--hcis-success-500': '#10b981',
    '--hcis-warning-500': '#f59e0b',
    '--hcis-danger-500': '#ef4444',
    '--hcis-neutral-500': '#94a3b8',
    '--hcis-info-500': '#06b6d4',
    '--hcis-purple-500': '#8b5cf6',
    '--hcis-slate-500': '#64748b',
};

export const SEGMEN_TREN = [
    { kunci: 'tepat', label: 'Tepat waktu', token: '--hcis-success-500', nilai: (t) => Math.max(0, t.hadir - t.hadir_terlambat) },
    { kunci: 'terlambat', label: 'Terlambat', token: '--hcis-warning-500', nilai: (t) => t.hadir_terlambat },
    { kunci: 'sakit_izin', label: 'Sakit / Izin', token: '--hcis-info-500', nilai: (t) => t.sakit + t.izin },
    { kunci: 'cuti', label: 'Cuti', token: '--hcis-purple-500', nilai: (t) => t.cuti },
    { kunci: 'alpha', label: 'Alpha', token: '--hcis-danger-500', nilai: (t) => t.alpha },
];

const HARI = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/** '2026-10-13' -> 'Sen 13' (pendek, untuk sumbu). */
export function labelTanggalPendek(tgl) {
    const d = new Date(`${tgl}T00:00:00`);
    return Number.isNaN(d.getTime()) ? tgl : `${HARI[d.getDay()]} ${d.getDate()}`;
}

export function labelTanggalPanjang(tgl) {
    const d = new Date(`${tgl}T00:00:00`);
    return Number.isNaN(d.getTime()) ? tgl : `${HARI[d.getDay()]}, ${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`;
}

/** Opsi ECharts batang bertumpuk tren harian (HcisChart type="custom"). */
export function opsiTren(tren, warna = warnaToken) {
    const tanggal = (tren || []).map((t) => t.tanggal);
    const butuhZoom = tanggal.length > 14;

    return {
        grid: {
            top: 44,
            right: 16,
            bottom: butuhZoom ? 36 : 24,
            left: 12,
            containLabel: true,
        },
        legend: {
            top: 4,
            right: 12,
            icon: 'circle',
            itemWidth: 8,
            itemHeight: 8,
            itemGap: 14,
            textStyle: { fontSize: 11, fontWeight: 500, color: '#64748b' },
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: { type: 'shadow' },
            confine: true,
            backgroundColor: 'rgba(255, 255, 255, 0.97)',
            borderColor: 'rgba(226, 232, 240, 0.95)',
            borderWidth: 1,
            padding: [10, 14],
            textStyle: { color: '#0f172a', fontSize: 12, fontFamily: 'inherit' },
            extraCssText: 'box-shadow: 0 12px 28px -4px rgba(15, 23, 42, 0.18); border-radius: 12px; backdrop-filter: blur(14px); z-index: 999999;',
            formatter: (titik) => {
                const daftar = Array.isArray(titik) ? titik : [titik];
                const tgl = tanggal[daftar[0]?.dataIndex] || '';
                const t = (tren || [])[daftar[0]?.dataIndex] || {};
                const terjadwal = t.terjadwal ?? 0;
                const totalHadir = t.hadir ?? 0;
                const baris = daftar.map((p) => {
                    const val = Number(p.value) || 0;
                    const pct = terjadwal > 0 ? ` <span style="color:#64748b;font-size:11px;">(${Math.round((val * 100) / terjadwal)}%)</span>` : '';
                    return `<div style="display:flex;align-items:center;justify-content:space-between;gap:14px;font-size:11.5px;">
                        <span>${p.marker} ${p.seriesName}</span>
                        <strong style="font-family:monospace;color:#0f172a;">${val}${pct}</strong>
                    </div>`;
                }).join('');

                const pctHadir = terjadwal > 0 ? Math.round((totalHadir * 100) / terjadwal) : 0;
                return `
                    <div style="font-weight:750;font-size:12.5px;margin-bottom:6px;border-bottom:1px solid #e2e8f0;padding-bottom:5px;display:flex;justify-content:space-between;align-items:center;gap:12px;">
                        <span style="color:#0f172a;">${labelTanggalPanjang(tgl)}</span>
                        <span style="font-weight:600;color:#059669;font-size:11px;background:#ecfdf5;padding:1px 6px;border-radius:4px;">${pctHadir}% Hadir</span>
                    </div>
                    <div style="font-size:11px;color:#64748b;margin-bottom:6px;display:flex;justify-content:space-between;">
                        <span>Total Terjadwal:</span>
                        <strong style="color:#0f172a;">${terjadwal} orang</strong>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:3px;">${baris}</div>
                `;
            },
        },
        xAxis: {
            type: 'category',
            data: tanggal.map(labelTanggalPendek),
            axisTick: { show: false },
            axisLine: { lineStyle: { color: 'rgba(226, 232, 240, 0.8)' } },
            axisLabel: {
                color: '#64748b',
                fontSize: 11,
                interval: tanggal.length > 20 ? 'auto' : 0,
            },
        },
        yAxis: {
            type: 'value',
            minInterval: 1,
            axisLabel: { color: '#64748b', fontSize: 11 },
            splitLine: { lineStyle: { type: 'dashed', color: 'rgba(226, 232, 240, 0.65)' } },
        },
        dataZoom: butuhZoom ? [
            {
                type: 'inside',
                start: Math.max(0, 100 - Math.round((14 / tanggal.length) * 100)),
                end: 100,
            },
            {
                type: 'slider',
                height: 14,
                bottom: 2,
                borderColor: 'transparent',
                backgroundColor: 'rgba(241, 245, 249, 0.6)',
                fillerColor: 'rgba(79, 70, 229, 0.15)',
                handleSize: '100%',
                handleStyle: { color: '#4f46e5' },
                showDetail: false,
                start: Math.max(0, 100 - Math.round((14 / tanggal.length) * 100)),
                end: 100,
            },
        ] : undefined,
        series: SEGMEN_TREN.map((s, idx) => ({
            name: s.label,
            type: 'bar',
            stack: 'kehadiran',
            barMaxWidth: 24,
            emphasis: { focus: 'series' },
            itemStyle: {
                color: warna(s.token) || WARNA_FALLBACK[s.token] || '#64748b',
                borderRadius: idx === SEGMEN_TREN.length - 1 ? [4, 4, 0, 0] : [0, 0, 0, 0],
            },
            data: (tren || []).map((t) => s.nilai(t)),
        })),
    };
}

export const METRIK_HEATMAP = {
    tidak_hadir: {
        label: '% tidak hadir',
        suffix: '%',
        sel: (c) => (c.terjadwal ? Math.round(((c.terjadwal - c.hadir) * 100) / c.terjadwal) : 0),
        // Peringkat unit memakai total periode, bukan rata-rata persen harian.
        total: (cs) => {
            const terjadwal = cs.reduce((a, c) => a + c.terjadwal, 0);
            const hadir = cs.reduce((a, c) => a + c.hadir, 0);
            return terjadwal ? ((terjadwal - hadir) * 100) / terjadwal : 0;
        },
        sentiment: 'danger',
    },
    terlambat: {
        label: 'Terlambat',
        suffix: 'kali',
        sel: (c) => c.terlambat_kali,
        total: (cs) => cs.reduce((a, c) => a + c.terlambat_kali, 0),
        sentiment: 'warning',
    },
    alpha: {
        label: 'Alpha',
        suffix: 'hari',
        sel: (c) => c.alpha,
        total: (cs) => cs.reduce((a, c) => a + c.alpha, 0),
        sentiment: 'danger',
    },
};

/**
 * Sel heatmap ECharts: [indeksTanggal, indeksUnit, nilai]. Unit diurut dari
 * yang terburuk dan dibatasi `maks`; ECharts menggambar kategori sumbu Y dari
 * bawah, jadi urutan dibalik agar yang terburuk berada di atas.
 */
export function dataHeatmap(sel, metrik, maks = 25) {
    const m = METRIK_HEATMAP[metrik] || METRIK_HEATMAP.tidak_hadir;
    const tanggal = [...new Set((sel || []).map((c) => c.tanggal))].sort();
    const perUnit = new Map();
    (sel || []).forEach((c) => {
        if (!perUnit.has(c.kelompok)) perUnit.set(c.kelompok, []);
        perUnit.get(c.kelompok).push(c);
    });

    const urut = [...perUnit.entries()]
        .map(([unit, cs]) => ({ unit, skor: m.total(cs) }))
        .sort((a, b) => b.skor - a.skor || a.unit.localeCompare(b.unit));
    const tampil = urut.slice(0, maks).map((u) => u.unit);
    const sumbuY = [...tampil].reverse();
    const indeksY = new Map(sumbuY.map((u, i) => [u, i]));
    const indeksX = new Map(tanggal.map((t, i) => [t, i]));

    const data = (sel || [])
        .filter((c) => indeksY.has(c.kelompok) && c.terjadwal > 0)
        .map((c) => [indeksX.get(c.tanggal), indeksY.get(c.kelompok), m.sel(c)]);

    return { tanggal, unit: sumbuY, data, sisa: Math.max(0, urut.length - tampil.length) };
}

export const METRIK_PERINGKAT = {
    tidak_hadir: {
        label: '% tidak hadir',
        satuan: '%',
        nilai: (k) => (k.terjadwal ? Math.round(((k.terjadwal - k.hadir) * 1000) / k.terjadwal) / 10 : 0),
        sentiment: 'danger',
    },
    terlambat_menit: { label: 'Menit terlambat', satuan: 'mnt', nilai: (k) => k.terlambat_menit, sentiment: 'warning' },
    lembur_jam: { label: 'Jam lembur', satuan: 'jam', nilai: (k) => k.lembur_jam, sentiment: 'purple' },
};

/** n unit dengan nilai terbesar (>0), terbesar di ATAS bar horizontal. */
export function peringkat(kelompok, metrik, n = 10) {
    const m = METRIK_PERINGKAT[metrik] || METRIK_PERINGKAT.tidak_hadir;
    const atas = (kelompok || [])
        .map((k) => ({ nama: k.kelompok, nilai: m.nilai(k) }))
        .filter((x) => x.nilai > 0)
        .sort((a, b) => b.nilai - a.nilai || a.nama.localeCompare(b.nama))
        .slice(0, n)
        .reverse();
    return { kategori: atas.map((x) => x.nama), nilai: atas.map((x) => x.nilai) };
}

const jamDariMenit = (menit) => `${String(Math.floor(menit / 60)).padStart(2, '0')}:${String(menit % 60).padStart(2, '0')}`;

/** Ember 15 menit berurutan dari yang pertama sampai terakhir; celah diisi 0. */
export function dataSebaran(sebaran) {
    const daftar = (sebaran || []).filter((s) => Number.isFinite(s.menit));
    if (!daftar.length) return { kategori: [], nilai: [] };
    const peta = new Map(daftar.map((s) => [s.menit, s.jumlah]));
    const awal = Math.min(...peta.keys());
    const akhir = Math.max(...peta.keys());
    const kategori = [];
    const nilai = [];
    for (let m = awal; m <= akhir; m += 15) {
        kategori.push(jamDariMenit(m));
        nilai.push(peta.get(m) || 0);
    }
    return { kategori, nilai };
}

/** Potongan donut per kategori (yang bernilai nol dibuang). */
export function dataDonut(jumlah, label, warna = warnaToken) {
    return Object.entries(jumlah || {})
        .filter(([, n]) => n > 0)
        .map(([k, n]) => ({
            name: label(k),
            value: n,
            kunci: k,
            itemStyle: { color: warna(TOKEN_KATEGORI[k]) || WARNA_FALLBACK[TOKEN_KATEGORI[k]] || '#64748b' },
        }));
}
