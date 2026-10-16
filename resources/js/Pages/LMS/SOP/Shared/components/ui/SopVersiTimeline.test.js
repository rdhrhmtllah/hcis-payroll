import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopVersiTimeline from './SopVersiTimeline.vue';

/**
 * Riwayat versi di sisi karyawan.
 *
 * Sejak 12 Agt 2026 isi versi cabutan tak lagi dibagikan — tapi BARISNYA tetap,
 * karena di situlah karyawan melihat bahwa ia pernah membaca revisi sebelumnya.
 * Yang diuji di sini justru batas itu: apa yang hilang, dan apa yang wajib
 * bertahan. Menghapus barisnya sekalian akan menghapus bukti orangnya sudah
 * membaca, dan itu kerugian yang jauh lebih besar daripada tombol yang hilang.
 */

const AKTIF = {
    id: 'h6',
    versi: 6,
    aktif: true,
    baca: 'BELUM',
    canView: true,
    kontenNama: 'Report billing GCP Juli',
    tanggal: '2026-08-12',
};

const CABUTAN = {
    id: 'h5',
    versi: 5,
    aktif: false,
    baca: 'DIGANTIKAN',
    canView: false,
    kontenNama: 'Report lama',
    changelog: 'asdasdasdasd',
    tanggal: '2026-08-10',
    pengesah: 'Aji',
    tanggalSah: '2026-08-10',
    nomorSah: 'SOP-001',
};

function pasang(versions = [AKTIF, CABUTAN]) {
    return mount(SopVersiTimeline, { props: { versions } });
}

describe('SopVersiTimeline', () => {
    it('menawarkan isi hanya pada versi yang berlaku', () => {
        const w = pasang();
        const tombol = w.findAll('.vt-btn-view');

        expect(tombol).toHaveLength(1);
        expect(tombol[0].text()).toMatch(/versi ini/i);
    });

    it('menjelaskan kenapa versi cabutan tak bisa dibuka, bukan diam', () => {
        // Tombol yang lenyap tanpa keterangan terbaca sebagai kerusakan, dan
        // pertanyaannya akan mendarat di admin — bukan di layar ini.
        const w = pasang();

        expect(w.find('.vt-tutup').exists()).toBe(true);
        expect(w.find('.vt-tutup').text()).toMatch(/tak lagi dibagikan/i);
    });

    it('tak memasang tombol mati yang mengundang ditekan berulang', () => {
        const w = pasang();

        expect(w.findAll('button[disabled]')).toHaveLength(0);
    });

    it('status baca versi cabutan tetap disebut', () => {
        const w = pasang();

        expect(w.text()).toMatch(/digantikan/i);
    });

    it('metadata versi cabutan tetap disebut', () => {
        const w = pasang();
        const teks = w.text();

        expect(teks).toMatch(/asdasdasdasd/); // ringkasan perubahan
        expect(teks).toMatch(/Aji/); // pengesah
        expect(teks).toMatch(/SOP-001/); // nomor pengesahan
    });

    it('barisnya tetap ada — bukan disembunyikan', () => {
        const w = pasang();

        expect(w.findAll('.vt-node')).toHaveLength(2);
    });
});
