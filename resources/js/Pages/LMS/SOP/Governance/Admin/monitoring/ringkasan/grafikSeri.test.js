import { describe, expect, it } from 'vitest';
import {
    dataBaca,
    dataHeatmap,
    kategoriBaca,
    kategoriMomentum,
    kategoriTren,
    kategoriWaktu,
    opsiTren,
    seriMomentum,
    seriTren,
    seriWaktu,
    tinggiHeatmap,
} from './grafikSeri.js';

/**
 * Pembentuk seri grafik — SATU PINTU untuk layar dan kertas.
 *
 * Sampai 31 Agu 2026 laporan PDF menggambar ulang grafik yang sama dengan
 * `<div>` bar CSS, sementara layar memakai ECharts. Dua penggambaran untuk satu
 * pertanyaan tak pernah terjaga sejalan, dan cacatnya tak menimbulkan satu pun
 * galat — hanya kertas yang pelan-pelan menyimpang dari layar.
 *
 * Berkas ini fungsi murni, jadi bisa diuji tanpa me-mount apa pun.
 */

const TREN = {
    titik: [
        { tanggal: '2026-08-01', coverage: 40, selesai: 5, dibuka_lagi: 1 },
        { tanggal: '2026-08-02', coverage: 55, selesai: 8, dibuka_lagi: 0 },
    ],
};

describe('seri tren kepatuhan', () => {
    it('memisahkan garis kepatuhan dari batang peristiwa pada dua sumbu', () => {
        const seri = seriTren(TREN);

        expect(seri.map((s) => s.type)).toEqual(['line', 'bar', 'bar']);
        /* Kepatuhan persen dan jumlah peristiwa TIDAK boleh berbagi sumbu:
           satu berkisar 0–100, satunya bebas. Disatukan, garis kepatuhan
           menempel di dasar grafik dan tak terbaca. */
        expect(seri[0].yAxisIndex).toBe(0);
        expect(seri[1].yAxisIndex).toBe(1);
        expect(seri[2].yAxisIndex).toBe(1);
    });

    it('mengunci sumbu kepatuhan pada 0–100', () => {
        const y = opsiTren(TREN).yAxis[0];

        expect(y.min).toBe(0);
        expect(y.max).toBe(100);
    });

    /* Titik terlalu rapat membuat simbol bulatnya bertumpuk jadi garis tebal. */
    it('menyembunyikan simbol titik saat datanya padat', () => {
        const banyak = { titik: Array.from({ length: 40 }, (_, i) => ({ tanggal: `h${i}`, coverage: i })) };

        expect(seriTren(TREN)[0].showSymbol).toBe(true);
        expect(seriTren(banyak)[0].showSymbol).toBe(false);
    });

    it('tahan terhadap muatan kosong', () => {
        expect(kategoriTren(null)).toEqual([]);
        expect(seriTren(undefined)[0].data).toEqual([]);
    });
});

describe('seri distribusi waktu selesai', () => {
    /**
     * Kelompok "kilat" ditandai merah karena ia BUKAN prestasi — dokumen
     * dikonfirmasi lebih cepat daripada mungkin dibaca. Menyeragamkan warnanya
     * menghapus satu-satunya petunjuk visual bahwa ada yang salah.
     */
    it('menandai kelompok kilat dengan warna berbeda', () => {
        const seri = seriWaktu({
            kelompok: [
                { label: '< 1 jam', jumlah: 12, kilat: true },
                { label: '1–4 jam', jumlah: 30 },
            ],
        });

        expect(seri[0].data[0].itemStyle.color).not.toBe(seri[0].data[1].itemStyle.color);
    });

    it('mengambil label kelompok apa adanya', () => {
        expect(kategoriWaktu({ kelompok: [{ label: 'A' }, { label: 'B' }] })).toEqual(['A', 'B']);
        expect(kategoriWaktu(null)).toEqual([]);
    });
});

describe('seri momentum', () => {
    it('memisahkan masuk dan selesai sebagai dua seri bernama', () => {
        const seri = seriMomentum({ deret: [{ label: 'W1', masuk: 4, selesai: 3 }] });

        expect(seri.map((s) => s.name)).toEqual(['Masuk', 'Selesai']);
        expect(seri[0].data).toEqual([4]);
        expect(seri[1].data).toEqual([3]);
    });

    it('tahan terhadap muatan kosong', () => {
        expect(kategoriMomentum({})).toEqual([]);
        expect(seriMomentum({})[0].data).toEqual([]);
    });
});

describe('seri kualitas baca', () => {
    it('memisahkan label dari jumlahnya', () => {
        const p = { baca: { kelompok: [{ label: '< 1 mnt', jumlah: 9 }, { label: '1–5 mnt', jumlah: 21 }] } };

        expect(kategoriBaca(p)).toEqual(['< 1 mnt', '1–5 mnt']);
        expect(dataBaca(p)).toEqual([9, 21]);
    });

    it('tahan terhadap muatan kosong', () => {
        expect(kategoriBaca(null)).toEqual([]);
        expect(dataBaca({})).toEqual([]);
    });
});

describe('titik heatmap', () => {
    const HEAT = {
        kategori: ['K3', 'Mutu'],
        seri: [
            { name: 'Produksi', data: [{ x: 'K3', y: 80, target: 10 }, { x: 'Mutu', y: null, target: 0 }] },
            { name: 'HRD', data: [{ x: 'K3', y: 50, target: 4 }] },
        ],
    };

    it('memetakan nama ke indeks sumbu yang dikirim pemanggil', () => {
        const titik = dataHeatmap(HEAT, ['Produksi', 'HRD']);

        expect(titik).toContainEqual([0, 0, 80, 10]);
        expect(titik).toContainEqual([0, 1, 50, 4]);
    });

    /**
     * PENJAGA PALING PENTING. Layar memakai departemen BERHALAMAN, laporan
     * memakai seluruhnya. Kalau sel dipetakan ke indeks daftar yang berbeda
     * dari yang dikirim ke sumbu, seluruh barisnya bergeser — angka yang benar
     * ditempelkan pada departemen yang salah, tanpa satu pun galat.
     */
    it('membuang departemen yang tidak ada di sumbu yang dikirim', () => {
        const titik = dataHeatmap(HEAT, ['HRD']);

        expect(titik).toEqual([[0, 0, 50, 4]]);
    });

    /* Sel tanpa penugasan bukan nol persen — ia "belum ada". */
    it('membedakan sel kosong dari nilai nol', () => {
        const titik = dataHeatmap(HEAT, ['Produksi']);
        const mutu = titik.find((t) => t[0] === 1);

        expect(mutu[2]).toBe('-');
    });

    it('memberi tinggi yang menampung seluruh baris', () => {
        expect(tinggiHeatmap(0)).toBe(240);
        expect(tinggiHeatmap(3)).toBeGreaterThan(220);
        /* Dibatasi supaya tak melampaui satu halaman cetak. */
        expect(tinggiHeatmap(100)).toBe(580);
    });
});
