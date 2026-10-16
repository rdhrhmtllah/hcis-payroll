import { describe, expect, it } from 'vitest';
import { areaKuadran, batasSumbu } from './havScatter.js';

/*
 | KENAPA UJI INI ADA.
 |
 | Sampai 26 September 2026 grafik sebar HAV Map menggambar area kotak dengan batas tetap
 | 0 / 33,3 / 66,6 / 100, sedangkan server menempatkan orang memakai pita siklus (bawaan
 | 70 / 85). Orang dengan KPI 60 dan Kompetensi 60 tampil di area Box 4, padahal kotaknya
 | Box 9. Sumbunya juga mentok di 100, dan ECharts membuang titik di luar sumbu, sehingga
 | KPI di atas 100 (yang dibatasi sampai 125) hilang dari grafik.
 */

const pitaBawaan = [
    { Kode_Range: 'RENDAH', Nilai_Dari: 0, Nilai_Sampai: 70 },
    { Kode_Range: 'SEDANG', Nilai_Dari: 70, Nilai_Sampai: 85 },
    { Kode_Range: 'TINGGI', Nilai_Dari: 85, Nilai_Sampai: 999.99 },
];

const tingkat = ['RENDAH', 'SEDANG', 'TINGGI'];
const nomor = { 'RENDAH|RENDAH': 9, 'SEDANG|RENDAH': 6, 'TINGGI|RENDAH': 5,
    'RENDAH|SEDANG': 8, 'SEDANG|SEDANG': 4, 'TINGGI|SEDANG': 2,
    'RENDAH|TINGGI': 7, 'SEDANG|TINGGI': 3, 'TINGGI|TINGGI': 1 };
const kuadran = tingkat.flatMap((kom) => tingkat.map((kpi) => ({
    Nomor_Box: String(nomor[`${kpi}|${kom}`]),
    Nama_Kuadran: `Kotak ${nomor[`${kpi}|${kom}`]}`,
    Tingkat_Performa: kpi,
    Tingkat_Kompetensi: kom,
})));

const warna = () => '#6366f1';

function kotakUntuk(area, x, y) {
    const cocok = area.filter(([a, b]) =>
        x >= a.coord[0] && x < b.coord[0] && y >= a.coord[1] && y < b.coord[1]);
    return cocok.map(([a]) => a.name);
}

describe('areaKuadran', () => {
    it('menaruh KPI 60 / Kompetensi 60 di area Box 9, sama dengan server', () => {
        const area = areaKuadran({ pitaKpi: pitaBawaan, pitaKompetensi: pitaBawaan, kuadran, maxX: 100, maxY: 100, warna });

        expect(kotakUntuk(area, 60, 60)).toEqual(['Box 9: Kotak 9']);
    });

    it('memakai batas bawah inklusif seperti server: 85 masuk Tinggi, 84,99 masuk Sedang', () => {
        const area = areaKuadran({ pitaKpi: pitaBawaan, pitaKompetensi: pitaBawaan, kuadran, maxX: 130, maxY: 100, warna });

        expect(kotakUntuk(area, 85, 78)).toEqual(['Box 2: Kotak 2']);
        expect(kotakUntuk(area, 84.99, 78)).toEqual(['Box 4: Kotak 4']);
    });

    it('mengikuti pita siklus, bukan angka bawaan', () => {
        const pitaLain = [
            { Kode_Range: 'RENDAH', Nilai_Dari: 0, Nilai_Sampai: 60 },
            { Kode_Range: 'SEDANG', Nilai_Dari: 60, Nilai_Sampai: 80 },
            { Kode_Range: 'TINGGI', Nilai_Dari: 80, Nilai_Sampai: 999.99 },
        ];
        const area = areaKuadran({ pitaKpi: pitaLain, pitaKompetensi: pitaBawaan, kuadran, maxX: 100, maxY: 100, warna });

        expect(kotakUntuk(area, 65, 50)).toEqual(['Box 6: Kotak 6']);
    });

    it('memotong pita teratas di ujung sumbu, bukan di 999,99', () => {
        const area = areaKuadran({ pitaKpi: pitaBawaan, pitaKompetensi: pitaBawaan, kuadran, maxX: 130, maxY: 100, warna });
        const box1 = area.find(([a]) => a.name.startsWith('Box 1:'));

        expect(box1[0].coord).toEqual([85, 85]);
        expect(box1[1].coord).toEqual([130, 100]);
    });

    it('tidak menggambar area sama sekali bila pita salah satu sumbu tidak ada', () => {
        expect(areaKuadran({ pitaKpi: [], pitaKompetensi: pitaBawaan, kuadran, maxX: 100, maxY: 100, warna })).toEqual([]);
        expect(areaKuadran({ pitaKpi: pitaBawaan, pitaKompetensi: undefined, kuadran, maxX: 100, maxY: 100, warna })).toEqual([]);
    });

    it('mewarnai area dari warna kotak dengan latar tipis', () => {
        const area = areaKuadran({ pitaKpi: pitaBawaan, pitaKompetensi: pitaBawaan, kuadran: kuadran.slice(0, 1), maxX: 100, maxY: 100, warna: () => '#e11d48' });

        expect(area[0][0].itemStyle.color).toBe('rgba(225, 29, 72, 0.07)');
        expect(area[0][0].label.color).toBe('#e11d48');
    });
});

describe('batasSumbu', () => {
    it('bawaannya 100', () => {
        expect(batasSumbu([50, 99.5])).toBe(100);
    });

    it('melebar sampai memuat KPI di atas 100, dibulatkan ke puluhan', () => {
        expect(batasSumbu([80, 125])).toBe(130);
        expect(batasSumbu([110])).toBe(110);
    });

    it('mengabaikan nilai yang bukan angka', () => {
        expect(batasSumbu([null, undefined, 'x', 90])).toBe(100);
    });
});
