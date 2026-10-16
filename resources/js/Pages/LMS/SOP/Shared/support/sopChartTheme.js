/**
 * Tema grafik SOP untuk ApexCharts.
 *
 * Paletnya diambil dari token yang SUDAH ada di sopTheme.css — deretan
 * `.ritem.type-accent-*` sudah menyediakan 7 warna kategorikal yang saling
 * dibedakan dan sudah dipakai di seluruh modul. Memakainya di grafik membuat
 * warna di chart berarti sama dengan warna di kartu, bukan palet kedua yang
 * kebetulan berdampingan.
 *
 * Tujuh warna juga jadi batas atas seri kategorikal: lebih dari itu mata tak
 * lagi bisa memasangkan legenda dengan bidangnya.
 */

export const PALET = [
    '#6366f1', // indigo  (PARAGRAF)
    '#10b981', // emerald (DOKUMEN)
    '#f59e0b', // amber   (KUIS)
    '#06b6d4', // cyan    (FORM)
    '#8b5cf6', // violet  (VIDEO)
    '#ec4899', // pink    (TAUTAN)
    '#0ea5e9', // sky     (ABSENSI)
];

export const WARNA = {
    primary: '#6366f1',
    primaryDark: '#4f46e5',
    success: '#10b981',
    warning: '#f59e0b',
    danger: '#ef4444',
    muted: '#94a3b8',
    grid: '#eef2f7',
    text: '#475569',
};

/** Warna per prioritas — dipakai grafik aging supaya sejalan dengan badge di tabel. */
export const WARNA_PRIORITAS = {
    KRITIS: '#ef4444',
    TINGGI: '#f59e0b',
    NORMAL: '#6366f1',
};

const FONT = "'Inter', sans-serif"; // font proyek; jangan diganti default Apex

/** Opsi dasar yang dipakai semua grafik SOP. */
export function dasar(extra = {}) {
    return {
        chart: {
            fontFamily: FONT,
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: true, easing: 'easeinout', speed: 420 },
            ...(extra.chart || {}),
        },
        colors: extra.colors || PALET,
        dataLabels: { enabled: false, ...(extra.dataLabels || {}) },
        grid: {
            borderColor: WARNA.grid,
            strokeDashArray: 4,
            padding: { left: 4, right: 8 },
            ...(extra.grid || {}),
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            fontWeight: 700,
            markers: { width: 9, height: 9, radius: 3 },
            itemMargin: { horizontal: 8 },
            ...(extra.legend || {}),
        },
        tooltip: {
            theme: 'light',
            style: { fontSize: '12px', fontFamily: FONT },
            ...(extra.tooltip || {}),
        },
        xaxis: {
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { colors: WARNA.muted, fontSize: '11px', fontWeight: 600 } },
            ...(extra.xaxis || {}),
        },
        yaxis: {
            labels: { style: { colors: WARNA.muted, fontSize: '11px', fontWeight: 600 } },
            ...(extra.yaxis || {}),
        },
        ...Object.fromEntries(
            Object.entries(extra).filter(
                ([k]) => !['chart', 'colors', 'dataLabels', 'grid', 'legend', 'tooltip', 'xaxis', 'yaxis'].includes(k),
            ),
        ),
    };
}
