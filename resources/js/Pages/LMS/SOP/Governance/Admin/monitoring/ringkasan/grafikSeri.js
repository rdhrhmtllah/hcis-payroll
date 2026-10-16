/**
 * Pembentuk seri grafik Ringkasan Monitoring — SATU PINTU.
 *
 * KENAPA ADA. Sampai 31 Agu 2026 setiap panel membentuk serinya sendiri di
 * dalam `computed`-nya, dan laporan PDF menggambar ulang grafik yang sama pakai
 * `<div>` bar CSS. Hasilnya dua penggambaran untuk satu pertanyaan: yang di
 * layar ECharts, yang di kertas HTML tangan — mirip, tak pernah sama, dan
 * setiap kali yang satu diperbaiki yang lain diam-diam tertinggal.
 *
 * Sejak berkas ini ada, keduanya membaca seri dari sini. Laporan PDF me-mount
 * `HcisChart` yang SAMA di luar layar lalu memanen gambarnya, jadi grafik di
 * kertas bukan tiruan melainkan komponen yang sama pada ukuran halaman.
 *
 * Semuanya fungsi murni atas muatan API — tanpa `this`, tanpa keadaan komponen,
 * sehingga bisa dipanggil dari panel maupun dari perakit laporan.
 */

/* ── 1. TREN KEPATUHAN ────────────────────────────────────────────────────── */

export function kategoriTren(tren) {
    return (tren?.titik || []).map((x) => x.tanggal);
}

export function seriTren(tren) {
    const t = tren?.titik || [];

    return [
        {
            name: 'Tingkat Kepatuhan (%)',
            type: 'line',
            smooth: true,
            yAxisIndex: 0,
            data: t.map((x) => x.coverage),
            showSymbol: t.length <= 20,
            symbol: 'circle',
            symbolSize: 6,
            z: 3,
            lineStyle: { width: 3.5, color: '#6366f1' },
            itemStyle: { color: '#ffffff', borderColor: '#6366f1', borderWidth: 2.5 },
        },
        {
            name: 'Penugasan Selesai',
            type: 'bar',
            yAxisIndex: 1,
            data: t.map((x) => x.selesai),
            barMaxWidth: 14,
            barGap: '15%',
            z: 2,
            itemStyle: { color: '#10b981', borderRadius: [4, 4, 0, 0] },
        },
        {
            name: 'Dibuka Ulang',
            type: 'bar',
            yAxisIndex: 1,
            data: t.map((x) => x.dibuka_lagi),
            barMaxWidth: 14,
            barGap: '15%',
            z: 2,
            itemStyle: { color: '#f59e0b', borderRadius: [4, 4, 0, 0] },
        },
    ];
}

export function opsiTren(tren) {
    return {
        grid: { top: 52, right: 48, bottom: 26, left: 20, containLabel: true },
        legend: {
            show: true,
            top: 2,
            left: 'center',
            itemGap: 18,
            textStyle: { color: '#475569', fontSize: 11, fontWeight: 600 },
        },
        xAxis: {
            type: 'category',
            data: kategoriTren(tren),
            axisTick: { show: false },
            axisLine: { lineStyle: { color: 'rgba(226, 232, 240, 0.9)' } },
            axisLabel: {
                hideOverlap: true,
                interval: 'auto',
                showMinLabel: true,
                showMaxLabel: true,
                color: '#64748b',
                fontSize: 11,
                fontWeight: 500,
                margin: 12,
                formatter: (val) => {
                    if (!val) return '';
                    const d = new Date(val);
                    if (Number.isNaN(d.getTime())) return val;

                    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
                },
            },
        },
        yAxis: [
            {
                type: 'value',
                name: 'Kepatuhan (%)',
                nameTextStyle: {
                    color: '#6366f1', fontSize: 11, fontWeight: 700, align: 'left', padding: [0, 0, 6, -4],
                },
                min: 0,
                max: 100,
                axisLabel: { formatter: '{value}%', color: '#64748b', fontSize: 11 },
                splitLine: { show: true, lineStyle: { type: 'dashed', color: 'rgba(226, 232, 240, 0.65)' } },
            },
            {
                type: 'value',
                name: 'Peristiwa (Log)',
                nameTextStyle: {
                    color: '#10b981', fontSize: 11, fontWeight: 700, align: 'right', padding: [0, -4, 6, 0],
                },
                min: 0,
                minInterval: 1,
                splitLine: { show: false },
                axisLabel: { formatter: '{value}', color: '#64748b', fontSize: 11 },
            },
        ],
    };
}

/* ── 2. DISTRIBUSI WAKTU SELESAI ──────────────────────────────────────────── */

export function kategoriWaktu(waktu) {
    return (waktu?.kelompok || []).map((k) => k.label);
}

export function seriWaktu(waktu) {
    return [
        {
            name: 'Jumlah Penugasan',
            /* Warna per batang, bukan satu warna seri: kelompok "kilat"
               (< 1 jam) ditandai merah karena ia bukan prestasi melainkan
               kecurigaan — dokumen dikonfirmasi lebih cepat daripada mungkin
               dibaca. */
            data: (waktu?.kelompok || []).map((k) => ({
                value: k.jumlah,
                itemStyle: { color: k.kilat ? '#f43f5e' : '#8b5cf6' },
            })),
        },
    ];
}

/* ── 3. MOMENTUM: MASUK VS TUNTAS ─────────────────────────────────────────── */

export function kategoriMomentum(momentum) {
    return (momentum?.deret || []).map((d) => d.label);
}

export function seriMomentum(momentum) {
    const d = momentum?.deret || [];

    return [
        { name: 'Masuk', data: d.map((x) => x.masuk), color: '#6366f1' },
        { name: 'Selesai', data: d.map((x) => x.selesai), color: '#10b981' },
    ];
}

/* ── 4. KUALITAS BACA ─────────────────────────────────────────────────────── */

export function kategoriBaca(pemahaman) {
    return (pemahaman?.baca?.kelompok || []).map((k) => k.label);
}

export function dataBaca(pemahaman) {
    return (pemahaman?.baca?.kelompok || []).map((k) => k.jumlah);
}

/* ── 5. HEATMAP DEPARTEMEN × KATEGORI ─────────────────────────────────────── */

/**
 * Titik heatmap dalam bentuk [x, y, nilai, target].
 *
 * `depts` diserahkan pemanggil, bukan diambil dari muatan: layar memakainya
 * berhalaman (delapan departemen sekali tampil) sedangkan laporan memakai
 * seluruhnya. Sumbunya harus sama persis dengan daftar yang dikirim, kalau
 * tidak barisnya bergeser satu tanpa satu pun galat.
 */
export function dataHeatmap(heat, depts) {
    const data = [];
    const kats = heat?.kategori || [];

    (heat?.seri || []).forEach((deptObj) => {
        const yIdx = depts.indexOf(deptObj.name);
        if (yIdx === -1) return;

        (deptObj.data || []).forEach((item) => {
            const xIdx = kats.indexOf(item.x);
            if (xIdx === -1) return;

            const val = item.y !== null && item.y !== undefined ? Number(item.y) : null;
            data.push([xIdx, yIdx, val !== null ? val : '-', item.target || 0]);
        });
    });

    return data;
}

/** Tinggi yang menampung seluruh baris tanpa memampatkannya jadi garis. */
export function tinggiHeatmap(jumlahBaris) {
    if (!jumlahBaris) return 240;

    return Math.min(580, Math.max(220, jumlahBaris * 44 + 90));
}
