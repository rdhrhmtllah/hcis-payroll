import { describe, expect, it } from 'vitest';
import SelfShift from './SelfShift.vue';

const status = (item) => SelfShift.methods.statusRiwayat(item);

describe('Status riwayat Self Shift karyawan', () => {
    it('menyebut pengajuan yang dibatalkan admin, bukan "Sudah Direview"', () => {
        // Batal admin menutup Flag_Selesai juga; tanpa pengecekan Status ia terbaca selesai direview.
        expect(status({ Status: 'Y', Flag_Selesai: 'Y' })).toEqual({ kelas: 'rejected', label: 'Dibatalkan Admin' });
    });

    it('tetap membedakan sudah dan belum direview', () => {
        expect(status({ Status: null, Flag_Selesai: 'Y' }).label).toBe('Sudah Direview');
        expect(status({ Status: null, Flag_Selesai: null }).label).toBe('Menunggu Review');
    });
});
