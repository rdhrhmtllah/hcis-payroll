import { describe, expect, it } from 'vitest';
import { daftarTahun, pohonModel } from './masterKendaraan';

describe('masterKendaraan', () => {
    it('mengelompokkan model per jenis kendaraan, urut nama', () => {
        const pohon = pohonModel(
            [
                { Id_Vehicle_Model: 3, Id_Master_Tipe: 3, Nama_Model_Kendaraan: 'Toyota Avanza' },
                { Id_Vehicle_Model: 1, Id_Master_Tipe: 2, Nama_Model_Kendaraan: 'Honda Beat' },
                { Id_Vehicle_Model: 4, Id_Master_Tipe: 3, Nama_Model_Kendaraan: 'Daihatsu Xenia' },
            ],
            [
                { Id_Vehicle_Type: 2, Nama_Tipe_Kendaraan: 'Sepeda Motor' },
                { Id_Vehicle_Type: 3, Nama_Tipe_Kendaraan: 'Mobil Penumpang' },
            ],
        );

        const mobil = pohon.find((p) => p.value === 'jenis-3');
        expect(mobil.label).toBe('Mobil Penumpang');
        expect(mobil.selectable).toBe(false);
        expect(mobil.children.map((c) => c.label)).toEqual(['Daihatsu Xenia', 'Toyota Avanza']);
        expect(mobil.children[1].value).toBe('3');
    });

    it('daftar tahun menurun dari maks ke min', () => {
        expect(daftarTahun(2027, 2024)).toEqual([2027, 2026, 2025, 2024]);
    });
});
