import { describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import SopMasterDokumen from './SopMasterDokumen.vue';

vi.mock('axios', () => ({
    default: {
        get: vi.fn().mockResolvedValue({ data: { data: [], tipe: ['DOKUMEN'] } }),
        post: vi.fn().mockResolvedValue({ data: {} }),
        put: vi.fn().mockResolvedValue({ data: {} }),
    },
}));

/**
 * Pratinjau berkas di Master Dokumen.
 *
 * SopKontenViewer merender dokumen sebagai iframe telanjang — tak ada unduh,
 * cetak, zoom, putar, maupun layar penuh. Itu cukup untuk karyawan yang
 * tugasnya membaca, tapi admin di sini sedang MEMERIKSA berkas, dan memeriksa
 * berarti memperbesar, mencetak, dan menyimpan salinan.
 *
 * Yang TIDAK boleh ikut pindah adalah video: muatannya URL embed Bunny, yakni
 * iframe pemutar, bukan berkas media. Diserahkan ke viewer berkas, ia hanya
 * akan menampilkan bingkai kosong — kegagalan yang tak melempar galat apa pun.
 */
const pasang = () => shallowMount(SopMasterDokumen, { props: { awal: null } });

const DOKUMEN = {
    type: 'document',
    judul: 'CONTOH PDF',
    nama_berkas: 'CONTOH PDF.pdf',
    url: 'https://storage.example/contoh.pdf?sig=abc',
    mime: 'application/pdf',
    total_halaman: 57,
};

describe('pratinjau berkas Master Dokumen', () => {
    it('memetakan muatan konten ke bentuk yang dimengerti viewer berkas', () => {
        const w = pasang();
        w.vm.berkasVersi = DOKUMEN;

        expect(w.vm.berkasDokumen).toEqual({
            url: DOKUMEN.url,
            name: 'CONTOH PDF.pdf',
            type: 'application/pdf',
        });
    });

    /* Namanya yang membawa ekstensi, dan dari ekstensi itulah viewer tahu ia
       PDF. Jatuh ke judul tanpa ekstensi membuat berkasnya dirender sebagai
       jenis tak dikenal. */
    it('memakai nama berkas, bukan judul konten', () => {
        const w = pasang();
        w.vm.berkasVersi = { ...DOKUMEN, judul: 'Judul Lain' };

        expect(w.vm.berkasDokumen.name).toBe('CONTOH PDF.pdf');
    });

    it('menolak video — muatannya embed, bukan berkas', () => {
        const w = pasang();
        w.vm.berkasVersi = { type: 'video', judul: 'Induksi K3', url: 'https://iframe.example/embed/1' };

        expect(w.vm.berkasDokumen).toBe(null);
    });

    it('menolak muatan tanpa url', () => {
        const w = pasang();
        w.vm.berkasVersi = { ...DOKUMEN, url: null };

        expect(w.vm.berkasDokumen).toBe(null);
    });

    /* Versi yang dipilih menang atas berkas versi berlaku dokumennya — kalau
       tidak, menekan pratinjau pada versi lama membuka berkas versi terbaru. */
    it('berkas versi yang dipilih menang atas berkas dokumennya', () => {
        const w = pasang();
        w.vm.detail = { berkas: { ...DOKUMEN, nama_berkas: 'BERLAKU.pdf' } };
        w.vm.berkasVersi = { ...DOKUMEN, nama_berkas: 'VERSI LAMA.pdf' };

        expect(w.vm.berkasDokumen.name).toBe('VERSI LAMA.pdf');
    });

    it('jatuh ke berkas versi berlaku saat tak ada versi yang dipilih', () => {
        const w = pasang();
        w.vm.detail = { berkas: { ...DOKUMEN, nama_berkas: 'BERLAKU.pdf' } };
        w.vm.berkasVersi = null;

        expect(w.vm.berkasDokumen.name).toBe('BERLAKU.pdf');
    });

    /**
     * SATU PINTU PENUTUP. Dua penutup yang menulis keadaan yang sama adalah cara
     * salah satunya lupa membersihkan berkasVersi — dan pratinjau berikutnya
     * membuka berkas yang lama.
     */
    it('menutup pratinjau melepas berkas yang sedang dibuka', () => {
        const w = pasang();
        w.vm.berkasVersi = DOKUMEN;
        w.vm.berkasTerbuka = true;

        w.vm.tutupBerkas();

        expect(w.vm.berkasTerbuka).toBe(false);
        expect(w.vm.berkasVersi).toBe(null);
    });
});
