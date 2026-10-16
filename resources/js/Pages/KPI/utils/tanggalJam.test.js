import { describe, expect, it } from 'vitest';
import { keTeksTanggalJam } from './tanggalJam';

/**
 * Satu pengurai untuk Due Date PICA dan jadwal KPI.
 *
 * Laporan user 6 Okt 2026: sesudah PICA disimpan, lanjut ke langkah 3, lalu
 * kembali dan menekan Edit, date picker rusak. Server mengirim Due Date
 * dalam format TAMPILAN ("12 Okt 2026 08:00", FormatTanggalHelper), dan
 * HcisDatePicker hanya membaca "YYYY-MM-DD HH:mm:ss". Teks tampilan itu
 * juga tak terbaca Carbon bila dikirim balik (bulan Indonesia).
 */
describe('keTeksTanggalJam', () => {
    it.each([
        ['2026-10-12 08:30:00', '2026-10-12 08:30:00'],
        ['2026-10-12 08:30:00.000', '2026-10-12 08:30:00'],
        ['2026-10-12T08:30', '2026-10-12 08:30:00'],
        ['2026-10-12', '2026-10-12 00:00:00'],
        ['12 Okt 2026 08:00', '2026-10-12 08:00:00'],
        ['5 Mei 2026 14:15', '2026-05-05 14:15:00'],
        ['1 Agu 2026', '2026-08-01 00:00:00'],
        ['31 Des 2026 23:59', '2026-12-31 23:59:00'],
        ['12 Oct 2026 08:00', '2026-10-12 08:00:00'],
    ])('%s -> %s', (masuk, keluar) => {
        expect(keTeksTanggalJam(masuk)).toBe(keluar);
    });

    it('objek Date', () => {
        expect(keTeksTanggalJam(new Date(2026, 9, 12, 8, 30, 5))).toBe('2026-10-12 08:30:05');
    });

    it.each([[''], [null], [undefined], ['bukan tanggal'], [new Date('x')]])('tak terbaca -> kosong (%s)', (v) => {
        expect(keTeksTanggalJam(v)).toBe('');
    });
});
