import { describe, expect, it } from 'vitest';
import UpdatePerformanceTim from './updatePerformance.vue';
import UpdatePerformanceByPass from './updatePerformanceByPass.vue';

/**
 * Due Date PICA tim tak boleh hilang saat PICA dibuka ulang.
 *
 * Sejak kolomnya memakai HcisDatePicker (5 Okt 2026), nilai yang dipilih
 * berbentuk teks "YYYY-MM-DD HH:mm:ss". formatDateForInput dulu hanya
 * mengenal objek Date dan teks tampilan "16 Okt 2025 11:02"; teks lain
 * dianggap tak sah dan dikosongkan — Due Date yang sudah diisi hilang begitu
 * admin menekan Edit.
 */
describe.each([
    ['tim', UpdatePerformanceTim],
    ['bypass', UpdatePerformanceByPass],
])('formatDateForInput (%s)', (_, Komponen) => {
    const fmt = (v) => Komponen.methods.formatDateForInput.call({}, v);

    it('teks dari date picker dipertahankan', () => {
        expect(fmt('2026-10-12 08:30:00')).toBe('2026-10-12 08:30:00');
    });

    it('teks dari SQL Server dipertahankan', () => {
        expect(fmt('2026-10-12 08:30:00.000')).toBe('2026-10-12 08:30:00');
    });

    it('teks tampilan lama tetap dikenali', () => {
        expect(fmt('16 Okt 2025 11:02')).toBe('2025-10-16 11:02:00');
    });

    it('objek Date tetap dikenali', () => {
        expect(fmt(new Date(2026, 9, 12, 8, 30))).toBe('2026-10-12 08:30:00');
    });
});
