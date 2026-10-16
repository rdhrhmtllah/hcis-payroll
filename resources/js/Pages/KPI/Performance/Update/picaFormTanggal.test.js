import { describe, expect, it } from 'vitest';
import UpdatePerformance from './updatePerformance.vue';

/**
 * Edit PICA sesudah kembali dari langkah 3 (laporan user 6 Okt 2026).
 *
 * item.pica.targetDate yang dimuat ulang dari server berformat tampilan
 * "12 Okt 2026 08:00". Form PICA harus menerimanya sebagai teks yang dibaca
 * date picker DAN yang bisa diurai server saat disimpan ulang.
 */
const konteks = () => ({ formatCurrency: (v) => String(v), form: {}, showModal: false });
const item = {
    id: 1,
    parameter: 'cdd',
    actual: 5,
    targetUnit: 'Rp',
    targetDescription: '20',
    pica: { problem: 'masalah', action: 'aksi', targetDate: '12 Okt 2026 08:00' },
};

describe('updatePerformance — tanggal di form PICA', () => {
    it('openPicaModal menormalkan Due Date', () => {
        const ctx = konteks();
        UpdatePerformance.methods.openPicaModal.call(ctx, item);
        expect(ctx.form.targetDate).toBe('2026-10-12 08:00:00');
    });
});
