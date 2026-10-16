import { describe, expect, it } from 'vitest';
import { dataDonut, dataHeatmap, dataSebaran, opsiTren, peringkat } from './grafikMonitoring.js';

const sel = (kelompok, tanggal, ganti = {}) => ({ kelompok, tanggal, terjadwal: 10, hadir: 10, terlambat_kali: 0, alpha: 0, ...ganti });

describe('pembentuk data grafik Monitoring Kehadiran', () => {
    it('heatmap: unit terburuk di atas, persen tidak hadir per sel', () => {
        const h = dataHeatmap([
            sel('BAIK', '2026-10-01'),
            sel('BURUK', '2026-10-01', { hadir: 5 }),
            sel('BURUK', '2026-10-02', { hadir: 8 }),
            sel('SEDANG', '2026-10-02', { hadir: 9 }),
        ], 'tidak_hadir');

        expect(h.tanggal).toEqual(['2026-10-01', '2026-10-02']);
        // ECharts menggambar kategori Y dari bawah: yang terburuk paling akhir = paling atas.
        expect(h.unit).toEqual(['BAIK', 'SEDANG', 'BURUK']);
        expect(h.data).toContainEqual([0, 2, 50]);
        expect(h.data).toContainEqual([1, 2, 20]);
        expect(h.sisa).toBe(0);
    });

    it('heatmap: dibatasi jumlah unit, sel tanpa jadwal tak digambar', () => {
        const banyak = Array.from({ length: 30 }, (_, i) => sel(`U${String(i).padStart(2, '0')}`, '2026-10-01', { alpha: i }));
        banyak.push(sel('LIBUR', '2026-10-01', { terjadwal: 0, hadir: 0 }));
        const h = dataHeatmap(banyak, 'alpha', 25);

        expect(h.unit).toHaveLength(25);
        expect(h.unit.at(-1)).toBe('U29');
        expect(h.sisa).toBe(6);
        expect(h.data.every((d) => d[2] >= 5)).toBe(true);
    });

    it('peringkat: n terbesar di atas, nilai nol dibuang', () => {
        const kelompok = [
            { kelompok: 'A', terjadwal: 100, hadir: 90, terlambat_menit: 30, lembur_jam: 0 },
            { kelompok: 'B', terjadwal: 100, hadir: 70, terlambat_menit: 0, lembur_jam: 4 },
            { kelompok: 'C', terjadwal: 0, hadir: 0, terlambat_menit: 5, lembur_jam: 1 },
        ];

        expect(peringkat(kelompok, 'tidak_hadir')).toEqual({ kategori: ['A', 'B'], nilai: [10, 30] });
        expect(peringkat(kelompok, 'terlambat_menit', 1)).toEqual({ kategori: ['A'], nilai: [30] });
    });

    it('sebaran check-in: celah 15 menit diisi nol', () => {
        expect(dataSebaran([{ menit: 450, jumlah: 3 }, { menit: 495, jumlah: 1 }])).toEqual({
            kategori: ['07:30', '07:45', '08:00', '08:15'],
            nilai: [3, 0, 0, 1],
        });
        expect(dataSebaran([])).toEqual({ kategori: [], nilai: [] });
    });

    it('tren: hadir dipecah tepat waktu dan terlambat', () => {
        const opsi = opsiTren([{ tanggal: '2026-10-13', terjadwal: 10, hadir: 8, hadir_terlambat: 3, sakit: 1, izin: 0, cuti: 1, alpha: 0 }], () => undefined);
        const nilai = Object.fromEntries(opsi.series.map((s) => [s.name, s.data[0]]));

        expect(nilai).toEqual({ 'Tepat waktu': 5, Terlambat: 3, 'Sakit / Izin': 1, Cuti: 1, Alpha: 0 });
        expect(opsi.series.every((s) => s.stack === 'kehadiran')).toBe(true);
    });

    it('donut: kategori nol dibuang, warna dari token', () => {
        const d = dataDonut({ tepat_waktu: 4, cuti: 0, belum_absen: 2 }, (k) => k.toUpperCase(), (t) => `var(${t})`);

        expect(d.map((x) => x.kunci)).toEqual(['tepat_waktu', 'belum_absen']);
        expect(d[0]).toEqual(expect.objectContaining({ name: 'TEPAT_WAKTU', value: 4, itemStyle: { color: 'var(--hcis-success-500)' } }));
    });
});
