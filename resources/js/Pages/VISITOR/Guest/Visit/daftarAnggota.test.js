import { describe, expect, it } from 'vitest';
import { bacaDaftarAnggota } from './daftarAnggota';

describe('bacaDaftarAnggota', () => {
    it('menerjemahkan JSON array menjadi daftar nama', () => {
        const daftar = bacaDaftarAnggota(
            '[{"nama":"Rudi"},{"nama":"Siti","jenis_identitas":"KTP","no_identitas":"32"}]',
        );

        expect(daftar).toHaveLength(2);
        expect(daftar[1].nama).toBe('Siti');
    });

    it.each([null, '', 'bukan json', '{"nama":"bukan array"}'])(
        'mengembalikan daftar kosong untuk nilai lama atau rusak: %s',
        (json) => expect(bacaDaftarAnggota(json)).toEqual([]),
    );
});
