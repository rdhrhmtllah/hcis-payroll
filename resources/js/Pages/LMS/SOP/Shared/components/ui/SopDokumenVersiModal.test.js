import { describe, expect, it, vi, beforeEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

/* router.visit kini menerima argumen kedua ({ onFinish }) untuk melepas keadaan
   "memuat" pada tombolnya. Yang dijaga uji ini adalah ALAMATNYA yang dijaga,
   bukan bentuk opsinya — menuntut pemanggilan berargumen tunggal membuat uji
   merah setiap kali tombolnya diberi indikator. */

vi.mock('axios');
vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn(), replace: vi.fn() },
}));

import SopDokumenVersiModal from './SopDokumenVersiModal.vue';

/**
 * Modal Versi & Pengesahan — SESUDAH formulirnya pindah (2026-08-26).
 *
 * Berkas ini dulu menguji formulir versi di dalam modal: keadaan tombol
 * simpan, penanda field berubah, batal sunting. Seluruh perilaku itu pindah ke
 * halaman wizard `/sop/admin/dokumen/{dokumen}/versi`, dan ujinya ikut pindah
 * ke SopVersiWizard.test.js beserta uji server di SopWizardVersiTest.php.
 *
 * Yang tersisa untuk diuji di sini adalah SAMBUNGANNYA: modal ini masih pintu
 * masuk dari daftar dokumen, dan pintu yang diam-diam berhenti mengantar ke
 * mana pun adalah cacat yang tak menimbulkan satu galat pun.
 */

const DRAFT = {
    Id_SOP_Item_Versi: 'hash-draft',
    Versi_Ke: 2,
    Status: 'DRAFT',
    Punya_Berkas: true,
    Bisa_Pratinjau: true,
    Id_Master_Konten: '90polDKyPKyQv3eadnRA',
    Konten_Nama: 'SK Istirahat Tahunan',
    Ringkasan_Perubahan: 'perbaikan kop',
    Tanggal_Berlaku: '2026-08-01',
    Pengesah: [{ Peran: 'Disahkan oleh', Nama: 'Budi', Jabatan: 'Manager' }],
};

async function pasang(versi = [DRAFT], tipe = 'DOKUMEN') {
    axios.get = vi.fn(() =>
        /* Modal ini beralih dari /detail (riwayat_versi) ke /{id}/versi (rows)
             pada 2026-08-26; mock-nya tak pernah ikut. Keduanya dikirim supaya
             uji ini tak lagi bergantung pada endpoint mana yang dipanggil. */
        Promise.resolve({
            data: { success: true, result: { rows: versi, riwayat_versi: versi, total: versi.length } },
        }),
    );

    /* DIBUKA LEWAT PERUBAHAN PROP, bukan dipasang dalam keadaan terbuka.
       Riwayatnya dimuat oleh watcher `show` yang TIDAK immediate, jadi modal
       yang lahir sudah terbuka tak pernah memuat apa pun — daftarnya kosong
       selamanya, dan uji ini merah tanpa ada yang salah pada komponennya. */
    const w = mount(SopDokumenVersiModal, {
        props: {
            show: false,
            dokumen: { Id_SOP_Dokumen: 'hash-dok', Judul: 'SOP Uji', Tipe: tipe },
        },
        global: {
            stubs: {
                SopModal: { template: '<div><slot /><slot name="footer" /></div>' },
                SopTerbitPratinjau: true,
                SopKontenViewer: true,
            },
        },
    });

    await w.setProps({ show: true });
    await flushPromises();

    return w;
}

const tombol = (w, teks) => w.findAll('button').find((b) => teks.test(b.text()));

describe('SopDokumenVersiModal — pintu masuk wizard', () => {
    beforeEach(() => vi.clearAllMocks());

    it('membawa admin ke halaman wizard saat membuat versi baru', async () => {
        const w = await pasang();

        await tombol(w, /Tambah Versi Baru|Buat Versi Pertama/i).trigger('click');

        expect(router.visit.mock.calls.at(-1)[0]).toBe('/sop/admin/dokumen/hash-dok/versi');
    });

    /*
     * PINDAHAN dari SopDokumenVersiDuaLayar.test.js.
     *
     * Berkas itu menguji rancangan dua-layar yang sudah dibongkar — formulir
     * versi kini halaman wizard sendiri, dan modal ini tinggal riwayat + pintu
     * masuk. Sepuluh dari sebelas ujinya sudah tergantikan di sini dan di
     * SopVersiWizard.test.js; SATU ini tidak, jadi ia dipindahkan sebelum
     * berkas lamanya dihapus.
     *
     * Aturannya kecil tapi nyata: "Tambah Versi Baru" pada dokumen yang belum
     * punya versi apa pun adalah kalimat yang salah — tak ada yang ditambahkan
     * ke apa pun. Regex pemilih tombol di uji-uji lain menerima KEDUA label,
     * jadi tanpa penjaga ini keduanya bisa tertukar tanpa ada yang merah.
     */
    it('dokumen tanpa versi diajak membuat versi PERTAMA, bukan versi baru', async () => {
        const kosong = await pasang([]);
        const labelKosong = tombol(kosong, /Versi/i).text();

        expect(labelKosong).toMatch(/Buat Versi Pertama/i);
        expect(labelKosong).not.toMatch(/Tambah Versi Baru/i);

        const adaIsi = await pasang();
        const labelAdaIsi = tombol(adaIsi, /Versi/i).text();

        expect(labelAdaIsi).toMatch(/Tambah Versi Baru/i);
        expect(labelAdaIsi).not.toMatch(/Buat Versi Pertama/i);
    });

    it('menyunting versi membawa id versinya ke URL', async () => {
        /* Tanpa id di URL, halaman wizard membuka formulir versi BARU — dan
           admin yang menekan "sunting" justru melahirkan versi kedua. */
        const w = await pasang();

        w.vm.suntingVersi(DRAFT);

        expect(router.visit.mock.calls.at(-1)[0]).toBe('/sop/admin/dokumen/hash-dok/versi/hash-draft');
    });

    it('"Lengkapi Versi" memakai jalur yang sama dengan sunting', async () => {
        /* Langkah yang dibuka dihitung SERVER dari posisi tahap. Layar tak
           boleh menebak "langsung ke langkah 4" — draft yang masih di Review
           harus mendarat di Review. */
        const w = await pasang();

        w.vm.lengkapiVersi(DRAFT);

        expect(router.visit.mock.calls.at(-1)[0]).toBe('/sop/admin/dokumen/hash-dok/versi/hash-draft');
    });

    it('permintaan suntingAwal diteruskan ke wizard, tidak ditelan', async () => {
        /* Pemanggil menyetel suntingAwal saat modal sudah terbuka; kalau
           watcher-nya tak menyala, tombol "Lengkapi Versi" di daftar dokumen
           tampak tak melakukan apa pun. */
        const w = await pasang();

        await w.setProps({ suntingAwal: 'hash-draft' });
        await flushPromises();

        expect(router.visit.mock.calls.at(-1)[0]).toBe('/sop/admin/dokumen/hash-dok/versi/hash-draft');
    });

    it('tetap menampilkan daftar riwayat versi', async () => {
        const w = await pasang();

        expect(w.text()).toContain('Riwayat Versi Dokumen');
        expect(w.text()).toContain('1 Versi');
    });

    it('tak lagi merender formulir versi apa pun', async () => {
        /* Penjaga atas pemindahan itu sendiri: kalau blok formulir tanpa
           sengaja hidup lagi di sini, akan ada dua tempat menyunting versi
           yang harus dijaga sejalan. */
        const w = await pasang();

        expect(w.text()).not.toContain('Identitas Pengajuan');
        expect(w.text()).not.toContain('Ringkasan Perubahan');
        expect(w.text()).not.toContain('Catatan Review');
    });
});
