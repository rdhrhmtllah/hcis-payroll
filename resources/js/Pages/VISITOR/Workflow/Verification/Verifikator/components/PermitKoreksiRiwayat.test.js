import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import PermitKoreksiRiwayat from './PermitKoreksiRiwayat.vue';

const RIWAYAT = [
    {
        kelompok: 'b',
        waktu: '2026-09-23 10:15:53.1060000',
        oleh: 'A1',
        nama: 'Budi Santoso',
        alasan: 'Kendaraan hanya parkir',
        perubahan: [
            { field: 'KATEGORI_PERMIT', lama: 'MERAH', baru: 'HIJAU' },
            { field: 'KETERANGAN', lama: 'Masuk boiler', baru: null },
        ],
    },
];

describe('PermitKoreksiRiwayat', () => {
    it('menampilkan pelaku, alasan, dan perubahan lama → baru dengan label manusia', () => {
        const teks = mount(PermitKoreksiRiwayat, { props: { riwayat: RIWAYAT } }).text();
        expect(teks).toContain('Budi Santoso');
        expect(teks).toContain('Kendaraan hanya parkir');
        expect(teks).toContain('Jenis permit');
        expect(teks).toContain('MERAH');
        expect(teks).toContain('HIJAU');
        expect(teks).toContain('(kosong)');
    });

    it('tidak merender apa pun saat riwayat kosong', () => {
        expect(mount(PermitKoreksiRiwayat, { props: { riwayat: [] } }).html()).toBe('<!--v-if-->');
    });
});

describe('PermitKoreksiRiwayat — format waktu', () => {
    it('waktu tampil ringkas, bukan cap datetime2 mentah', () => {
        const teks = mount(PermitKoreksiRiwayat, { props: { riwayat: RIWAYAT } }).text();
        expect(teks).toContain('23 Sep 2026, 10:15');
        expect(teks).not.toContain('.1060000');
    });
});
