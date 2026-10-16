import { describe, expect, it } from 'vitest';
import IzinPage from './izinPage.vue';

const m = IzinPage.methods;

// Konteks minimal: hanya data yang dibaca pengecek tanggal.
const konteks = (dayOffDates = []) => {
    const ctx = {
        dayOffDates,
        specialDates: [],
        TanggalPulangTerlambat: [],
        TanggalSakitIzin: [],
        TanggalCuti: [],
        konversiPendingDates: [],
        assignIzinType2: null,
    };
    for (const [nama, fn] of Object.entries(m)) ctx[nama] = fn.bind(ctx);
    return ctx;
};

// 11 Okt 2026 = Minggu.
const MINGGU = new Date('2026-10-11T00:00:00');

describe('Izin di hari Minggu', () => {
    it('Minggu yang menurut jadwal MASUK boleh dipilih (bukan tanggal terlarang)', () => {
        const ctx = konteks([]);

        expect(m.isSpecialDate.call(ctx, MINGGU)).toBe(false);
        expect(m.isSpecialDateSingle.call(ctx, MINGGU)).toBe(false);
        expect(m.getSpecialDateTooltip.call(ctx, MINGGU)).toBe('');
    });

    it('Minggu yang memang libur (dari status hari server) tetap terkunci', () => {
        const ctx = konteks([{ date: '2026-10-11', status_hari: 'OFF-Default_Shift', description: 'Tanggal ini OFF-Default_Shift.' }]);

        expect(m.isSpecialDate.call(ctx, MINGGU)).toBe(true);
        expect(m.isSpecialDateSingle.call(ctx, MINGGU)).toBe(true);
        expect(m.getSpecialDateTooltip.call(ctx, MINGGU)).toContain('Day Off');
    });
});
