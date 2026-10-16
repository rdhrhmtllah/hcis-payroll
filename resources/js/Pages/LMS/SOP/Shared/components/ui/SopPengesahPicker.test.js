import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import SopPengesahPicker from './SopPengesahPicker.vue';

vi.mock('axios');

/**
 * Blok penanda tangan — dipakai BERSAMA Master Dokumen dan Builder.
 *
 * Yang dijaga di sini bukan tampilannya, melainkan dua aturan yang gampang
 * hilang saat komponen dipakai ulang:
 *
 *  1. Memilih orang mengisi jabatannya sekaligus DAN menyimpan rujukan
 *     Kode_Karyawan-nya. Tanpa rujukan, catatan pengesahan cuma teks bebas.
 *  2. Mengetik ulang sesudah memilih MELEPAS rujukan itu. Tanpa pelepasan,
 *     nama bisa diganti orang lain sementara kodenya masih menunjuk orang
 *     pertama — rujukan yang berbohong, lebih buruk daripada tak ada.
 */

const ORANG = [
    { Kode_Karyawan: 'E14', Nama: 'Dhea Amelia', Jabatan: 'Operator Produksi' },
    { Kode_Karyawan: 'A38', Nama: 'Dhea Kartika', Jabatan: 'Staff QA' },
];

function pasang(rows = [], props = {}) {
    axios.get = vi.fn(() => Promise.resolve({ data: { result: { rows: ORANG } } }));

    return mount(SopPengesahPicker, { props: { rows, ...props } });
}

async function ketik(w, i, teks) {
    const kotak = w.findAll('.pg-cari input')[i];
    await kotak.setValue(teks);
    await kotak.trigger('input');
    // Pencariannya ditunda 250 ms (debounce). Ditunggu apa adanya, bukan dengan
    // timer palsu: yang diuji perilakunya, dan menyulap waktunya membuat uji ini
    // tetap hijau seandainya penundaannya kelak dinaikkan jadi 10 detik.
    await new Promise((r) => setTimeout(r, 320));
    await flushPromises();
    await w.vm.$nextTick();

    return kotak;
}

describe('SopPengesahPicker', () => {
    it('selalu menyediakan satu baris untuk diisi', () => {
        // Blok kosong tak bisa diisi: barisnya sendiri yang jadi formulirnya.
        const rows = [];
        pasang(rows);

        expect(rows).toHaveLength(1);
    });

    it('memilih orang mengisi jabatan dan menyimpan rujukannya', async () => {
        const rows = [];
        const w = pasang(rows);

        await ketik(w, 0, 'Dhea');
        /* Daftar saran DITELEPORTASI ke <body> (lihat catatan di komponen), jadi
           ia tak lagi berada di dalam wrapper. Uji ini sempat lama merah karena
           masih mencarinya di dalam — merah yang benar, tapi tak pernah
           dibereskan. */
        const saran = [...document.querySelectorAll('.pg-saran li')];
        expect(saran.length, 'saran tak muncul — pemilihnya tak bisa dipakai').toBe(2);

        // Jabatan terlihat SEBELUM memilih: dua "Dhea" hanya bisa dibedakan
        // dari jabatannya, dan salah pilih baru ketahuan setelah SOP disahkan.
        expect(saran[0].textContent).toContain('Operator Produksi');

        saran[1].dispatchEvent(new MouseEvent('mousedown', { bubbles: true }));
        await flushPromises();
        expect(rows[0]).toMatchObject({ Nama: 'Dhea Kartika', Jabatan: 'Staff QA', Kode_Karyawan: 'A38' });
    });

    it('mengetik ulang melepas rujukan karyawannya', async () => {
        const rows = [{ Peran: '', Nama: 'Dhea Kartika', Jabatan: 'Staff QA', Kode_Karyawan: 'A38' }];
        const w = pasang(rows);

        await ketik(w, 0, 'Dhea Kartikaa');

        expect(rows[0].Kode_Karyawan).toBe(null);
    });

    it('bisa lebih dari satu penanda tangan, dan barisnya bisa dibuang', async () => {
        // Blok di kertas hampir selalu lebih dari satu: disiapkan / diperiksa /
        // disetujui. Satu baris memaksa admin memilih siapa yang dicatat.
        const rows = [];
        const w = pasang(rows);

        await w
            .findAll('button')
            .find((b) => /Tambah penanda tangan/i.test(b.text()))
            .trigger('click');
        expect(rows).toHaveLength(2);

        /* Tombolnya tak pernah punya kelas `.pg-buang` — selektor itu usang sejak
           lama. Namanya yang stabil adalah judulnya. */
        await w.findAll('button[title="Hapus baris penanda tangan"]')[1].trigger('click');
        expect(rows).toHaveLength(1);
    });

    it('menembak gerbang API milik pemanggilnya', async () => {
        /* Basis-nya datang dari pemanggil, bukan ditulis di dalam komponen.
           Sejak 27 Agu 2026 pemanggilnya tinggal satu — wizard versi di Master;
           rute kembarannya di Builder dicabut bersama jalur "buat dokumen baru".
           Yang dijaga di sini tetap kontraknya: apa pun basis yang diberikan,
           itu yang ditembak. Salah gerbang = 403, dan pemilihnya diam tanpa
           penjelasan apa pun. */
        const w = pasang([], { basis: '/api/v1/sop/admin/dokumen' });
        await ketik(w, 0, 'Dhea');

        expect(axios.get.mock.calls[0][0]).toBe('/api/v1/sop/admin/dokumen/options/pengesah');
    });

    it('pencarian yang gagal tidak melumpuhkan pengisian manual', async () => {
        // Pemilih ini penghemat ketikan, bukan syarat: penanda tangan bisa orang
        // di luar HRIS, dan memaksa semua ada di master menghentikan pengesahan
        // yang sah.
        axios.get = vi.fn(() => Promise.reject(new Error('putus')));
        const rows = [];
        const w = mount(SopPengesahPicker, { props: { rows } });

        await ketik(w, 0, 'Orang Luar');

        expect(w.find('.pg-saran').exists()).toBe(false);
        expect(rows[0].Nama).toBe('Orang Luar');
    });
});

describe('SopPengesahPicker — terkunci', () => {
    /**
     * CACAT YANG DIJAGA DI SINI. Komponen ini sebelumnya tak punya prop kunci
     * sama sekali, dan langkah Pengesahan tak bisa meneruskan apa pun ke sini.
     * Akibatnya blok penanda tangan versi ARSIP (dibatalkan) dan RIWAYAT
     * (obsolete) tetap bisa ditambah dan diubah di layar. Server menolak
     * simpanannya, jadi datanya selamat — tapi admin mengerjakan sesuatu yang
     * baru ditolak di ujung, dan blok penanda tangan adalah tempat terakhir
     * yang boleh terasa begitu.
     */
    const baris = () => ([{ Peran: 'Disahkan oleh', Nama: 'Aji', Jabatan: 'SPV', Kode_Karyawan: 'E01' }]);

    it('mematikan seluruh isian dan menyembunyikan tombol saat terkunci', () => {
        const w = mount(SopPengesahPicker, { props: { rows: baris(), terkunci: true } });

        w.findAll('input').forEach((el) => {
            expect(el.attributes('disabled')).toBeDefined();
        });
        expect(w.find('button[title="Hapus baris penanda tangan"]').exists()).toBe(false);
        expect(w.text()).not.toContain('Tambah Penanda Tangan');
    });

    it('tetap bisa dikerjakan saat tidak terkunci', () => {
        const w = mount(SopPengesahPicker, { props: { rows: baris() } });

        expect(w.find('input').attributes('disabled')).toBeUndefined();
        expect(w.find('button[title="Hapus baris penanda tangan"]').exists()).toBe(true);
        expect(w.text()).toContain('Tambah Penanda Tangan');
    });

    /** Daftar saran nama tak boleh terbuka pada blok yang terkunci. */
    it('tidak membuka saran nama saat terkunci', async () => {
        const w = mount(SopPengesahPicker, { props: { rows: baris(), terkunci: true } });

        await w.find('input').trigger('focus');
        await flushPromises();

        expect(w.vm.saranUntuk).toBeNull();
    });
});
