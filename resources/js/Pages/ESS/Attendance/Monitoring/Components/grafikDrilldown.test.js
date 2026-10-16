import { describe, expect, it } from 'vitest';
import {
    METRIK_DRILLDOWN,
    opsiBatangDrilldown,
    opsiSunburstDrilldown,
    opsiTreemapDrilldown,
    temukanNode,
} from './grafikDrilldown.js';

const mockPohon = {
    id: 'root',
    name: 'Semua Lokasi',
    tingkat: 'root',
    karyawan_count: 5,
    terjadwal: 100,
    hadir: 85,
    persen_hadir: 85,
    terlambat_kali: 4,
    terlambat_menit: 45,
    alpha: 2,
    lembur_jam: 10,
    children: [
        {
            id: 'lokasi:EMI',
            name: 'EMI',
            tingkat: 'lokasi',
            karyawan_count: 3,
            terjadwal: 60,
            hadir: 54,
            persen_hadir: 90,
            terlambat_kali: 2,
            terlambat_menit: 20,
            alpha: 1,
            lembur_jam: 8,
            children: [
                {
                    id: 'divisi:EMI:PROD',
                    name: 'PRODUCTION',
                    tingkat: 'divisi',
                    karyawan_count: 2,
                    terjadwal: 40,
                    hadir: 36,
                    persen_hadir: 90,
                    terlambat_kali: 1,
                    terlambat_menit: 10,
                    alpha: 0,
                    lembur_jam: 6,
                    children: [
                        {
                            id: 'departemen:EMI:PROD:WET',
                            name: 'WET FOOD',
                            tingkat: 'departemen',
                            karyawan_count: 2,
                            terjadwal: 40,
                            hadir: 36,
                            persen_hadir: 90,
                            terlambat_kali: 1,
                            terlambat_menit: 10,
                            alpha: 0,
                            lembur_jam: 6,
                            karyawan: [
                                { kode: 'K1', nama: 'Budi', jabatan: 'OP' },
                                { kode: 'K2', nama: 'Siti', jabatan: 'OP' },
                            ],
                        },
                    ],
                },
            ],
        },
        {
            id: 'lokasi:HO',
            name: 'HEAD OFFICE',
            tingkat: 'lokasi',
            karyawan_count: 2,
            terjadwal: 40,
            hadir: 31,
            persen_hadir: 77.5,
            terlambat_kali: 2,
            terlambat_menit: 25,
            alpha: 1,
            lembur_jam: 2,
            children: [],
        },
    ],
};

describe('grafikDrilldown logic', () => {
    it('temukanNode menemukan root dan navigasi path anak', () => {
        expect(temukanNode(mockPohon, [])).toBe(mockPohon);
        const emi = temukanNode(mockPohon, ['EMI']);
        expect(emi.name).toBe('EMI');
        expect(emi.tingkat).toBe('lokasi');

        const wet = temukanNode(mockPohon, ['EMI', 'PRODUCTION', 'WET FOOD']);
        expect(wet.name).toBe('WET FOOD');
        expect(wet.karyawan).toHaveLength(2);

        // Path tidak valid tetap fallback ke node valid terakhir
        expect(temukanNode(mockPohon, ['EMI', 'UNKNOWN'])).toBe(emi);
    });

    it('opsiBatangDrilldown menyusun kategori dan nilai terurut untuk anak', () => {
        const opsi = opsiBatangDrilldown(mockPohon, 'persen_hadir', () => '#123456');

        expect(opsi.series[0].type).toBe('bar');
        expect(opsi.yAxis.data).toHaveLength(2);
        // ECharts bar-horizontal menggambar dari bawah ke atas:
        // EMI (90%) lebih tinggi dari HO (77.5%), jadi EMI berada di atas (indeks terakhir)
        expect(opsi.yAxis.data).toEqual(['HEAD OFFICE', 'EMI']);
        expect(opsi.series[0].data).toEqual([77.5, 90]);
        expect(opsi.series[0].itemStyle.color).toBe('#123456');
    });

    it('opsiTreemapDrilldown membentuk struktur bertingkat dengan leafDepth dan zoomToNode', () => {
        const opsi = opsiTreemapDrilldown(mockPohon, 'alpha');

        expect(opsi.series[0].type).toBe('treemap');
        expect(opsi.series[0].leafDepth).toBe(1);
        expect(opsi.series[0].nodeClick).toBe('zoomToNode');
        expect(opsi.series[0].data).toHaveLength(2);
        expect(opsi.series[0].data[0].name).toBe('EMI');
        expect(opsi.series[0].data[0].children[0].name).toBe('PRODUCTION');
    });

    it('opsiSunburstDrilldown membentuk struktur konsentris dengan rootToNode', () => {
        const opsi = opsiSunburstDrilldown(mockPohon, 'terlambat');

        expect(opsi.series[0].type).toBe('sunburst');
        expect(opsi.series[0].nodeClick).toBe('rootToNode');
        expect(opsi.series[0].levels).toHaveLength(4);
        expect(opsi.series[0].data).toHaveLength(2);
    });
});
