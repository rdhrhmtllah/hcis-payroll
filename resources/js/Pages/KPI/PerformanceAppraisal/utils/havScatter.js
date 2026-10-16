/**
 * Geometri grafik sebar HAV Map: area sembilan kotak dan panjang sumbu.
 *
 * ----------------------------------------------------------------------------------------
 * KENAPA AREA DIGAMBAR DARI PITA SIKLUS
 *
 * Kotak seseorang ditentukan server dari pita `KPI|HAVMAP` dan `KOMPETENSI|HAVMAP` salinan
 * siklus (bawaan 70 / 85). Grafik ini dulu menggambar batas tetap 33,3 / 66,6, sehingga
 * titik jatuh di area kotak yang berbeda dari kotak orangnya. Area kini dibangun dari pita
 * yang sama dengan penempatan kotak. Bila pita tidak ada, area TIDAK digambar: batas
 * tebakan lebih menyesatkan daripada grafik tanpa latar.
 *
 * Titik yang digeser forum kalibrasi tetap diletakkan menurut nilainya, jadi bisa berada di
 * luar area kotak barunya. Warna titik mengikuti kotak yang berlaku.
 */

const LANGKAH_SUMBU = 10;
const SUMBU_MINIMUM = 100;

function cariPita(pita, kode) {
    return (pita || []).find((p) => String(p.Kode_Range) === String(kode)) || null;
}

function latarTipis(hex, alfa = 0.07) {
    const m = /^#?([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(String(hex || ''));
    if (!m) return `rgba(99, 102, 241, ${alfa})`;
    const [r, g, b] = m.slice(1).map((h) => parseInt(h, 16));
    return `rgba(${r}, ${g}, ${b}, ${alfa})`;
}

/**
 * Pasangan titik markArea ECharts untuk tiap kotak.
 *
 * @param {object} opsi
 * @param {Array} opsi.pitaKpi         pita KPI|HAVMAP siklus
 * @param {Array} opsi.pitaKompetensi  pita KOMPETENSI|HAVMAP siklus
 * @param {Array} opsi.kuadran         kamus kotak (Nomor_Box, Nama_Kuadran, Tingkat_*)
 * @param {number} opsi.maxX           ujung sumbu KPI
 * @param {number} opsi.maxY           ujung sumbu Kompetensi
 * @param {(kotak: object) => string} opsi.warna  warna hex kotak
 * @returns {Array<[object, object]>}
 */
export function areaKuadran({ pitaKpi, pitaKompetensi, kuadran, maxX, maxY, warna }) {
    if (!Array.isArray(pitaKpi) || !pitaKpi.length) return [];
    if (!Array.isArray(pitaKompetensi) || !pitaKompetensi.length) return [];

    return (kuadran || []).flatMap((q) => {
        const x = cariPita(pitaKpi, q.Tingkat_Performa);
        const y = cariPita(pitaKompetensi, q.Tingkat_Kompetensi);
        if (!x || !y) return [];

        const hex = warna(q);
        return [[
            {
                name: `Box ${q.Nomor_Box}: ${q.Nama_Kuadran}`,
                coord: [Number(x.Nilai_Dari), Number(y.Nilai_Dari)],
                itemStyle: { color: latarTipis(hex) },
                label: { show: true, position: 'insideTopLeft', color: hex, fontSize: 11, fontWeight: 700 },
            },
            { coord: [Math.min(Number(x.Nilai_Sampai), maxX), Math.min(Number(y.Nilai_Sampai), maxY)] },
        ]];
    });
}

/**
 * Ujung sumbu: minimal 100, melebar ke kelipatan 10 berikutnya bila ada nilai di atasnya.
 * ECharts membuang titik di luar sumbu, dan Nilai KPI bisa sampai 125.
 *
 * @param {Array<unknown>} nilai
 * @returns {number}
 */
export function batasSumbu(nilai) {
    const angka = (nilai || []).map(Number).filter((n) => Number.isFinite(n));
    const tertinggi = Math.max(SUMBU_MINIMUM, ...angka);
    return Math.ceil(tertinggi / LANGKAH_SUMBU) * LANGKAH_SUMBU;
}
