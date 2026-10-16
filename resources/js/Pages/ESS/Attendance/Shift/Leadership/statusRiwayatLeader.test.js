import { describe, expect, it } from 'vitest';
import LeaderShift from './LeaderShift.vue';

const label = (item) => LeaderShift.methods.getStatusLabel(item);

describe('Status riwayat pengajuan leader', () => {
    it('membedakan pembatalan oleh admin', () => {
        expect(label({ Status: 'Y', Flag_Selesai: 'Y', dibatalkan_admin: true })).toBe('Dibatalkan Admin');
    });

    it('label lain tidak berubah', () => {
        expect(label({ Status: 'Y', dibatalkan_admin: false })).toBe('Dibatalkan');
        expect(label({ Status: null, Flag_Selesai: 'Y' })).toBe('Selesai');
        expect(label({ Status: null, Flag_Selesai: null })).toBe('Menunggu Approval');
    });
});
