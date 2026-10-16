import { describe, expect, it, vi } from 'vitest';
import SelfShift from '@/Pages/ESS/Attendance/Shift/Personal/SelfShift.vue';
import LeaderShift from '@/Pages/ESS/Attendance/Shift/Leadership/LeaderShift.vue';

describe('Sambungan laci jejak di halaman shift', () => {
    it('Self Shift: tanggal yang sudah tak bisa diubah tetap bisa dilihat riwayat jadwalnya', async () => {
        const ctx = {
            canEditDate: () => false,
            bukaJejakHari: vi.fn(),
            showToastMessage: vi.fn(),
            formatDate: (d) => d,
            config: { backdate_max_days: 3, future_max_days: 30 },
            showQuickModal: false,
            loadingState: { quickModal: false },
        };

        await SelfShift.methods.openQuickModal.call(ctx, { date: '2026-01-05' });

        expect(ctx.bukaJejakHari).toHaveBeenCalledWith('2026-01-05');
        expect(ctx.showQuickModal).toBe(false);
        expect(ctx.showToastMessage).toHaveBeenCalledWith('info', expect.stringContaining('riwayat jadwal'));
    });

    it('Self Shift: membuka laci menutup modal hari dan memotong tanggal ke Y-m-d', () => {
        const ctx = { showQuickModal: true, jejakHari: { buka: false, tanggal: '' } };

        SelfShift.methods.bukaJejakHari.call(ctx, '2026-09-09T00:00:00');

        expect(ctx.showQuickModal).toBe(false);
        expect(ctx.jejakHari).toEqual({ buka: true, tanggal: '2026-09-09' });
    });

    it('Leader Shift: laci memakai kode anggota yang diklik', () => {
        const ctx = { showQuickModal: true, jejakHari: { buka: false, kode: '', tanggal: '' } };

        LeaderShift.methods.bukaJejakHari.call(ctx, { Kode_Karyawan: 'H45', Nama: 'Hana' }, '2026-10-13');

        expect(ctx.showQuickModal).toBe(false);
        expect(ctx.jejakHari).toEqual({ buka: true, kode: 'H45', tanggal: '2026-10-13' });
    });

    it('Leader Shift: pintunya mengikuti mode halaman (leader/admin)', () => {
        expect(LeaderShift.computed.routePrefix.call({ isAdmin: false })).toBe('leader-shift');
        expect(LeaderShift.computed.routePrefix.call({ isAdmin: true })).toBe('leader-shift/admin');
    });
});
