import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LangkahTinjauan from './LangkahTinjauan.vue';

/**
 * Rekap sebelum terbit.
 *
 * Penerbitan tak bisa ditarik, jadi langkah ini harus menampilkan apa yang
 * BENAR-BENAR tersimpan — termasuk yang kosong. Rekap yang diam-diam
 * menyembunyikan field kosong justru meyakinkan admin bahwa semuanya beres.
 */
const stub = (n) => ({ name: n, template: '<div />' });

const isian = (ubah = {}) => ({
    Versi_Ke: 3,
    Ringkasan_Perubahan: 'Menyesuaikan alur persetujuan',
    Tanggal_Permintaan: '2026-03-01',
    Tanggal_Respon: '2026-03-05',
    Tanggal_Akhir_Approval: '2026-03-10',
    Tanggal_Berlaku: '2026-04-01',
    Tanggal_Kadaluarsa: '2028-04-01',
    Tanggal_Review_Berikutnya: '2027-04-01',
    Konten_Nama: 'SOP-Perdin-v3.pdf',
    Pengesah: [{ Peran: 'Disahkan oleh', Nama: 'Rina', Jabatan: 'Direktur' }],
    ...ubah,
});

const pasang = (props = {}) => mount(LangkahTinjauan, {
    props: { nilai: isian(), review: [], ...props },
    global: {
        stubs: {
            HcisCard: {
                props: ['title'],
                template: '<section><h3>{{ title }}</h3><slot name="actions" /><slot /></section>',
            },
            /* Bannernya memakai `title` + slot, bukan prop `description`. Stub lama
               menuntut prop yang tak pernah dikirim, jadi SELURUH kalimat keadaan
               hilang dari teks yang diuji — dan empat uji di bawah merah karena
               stubnya, bukan karena komponennya. */
            HcisAlert: {
                props: ['title', 'badge'],
                template: '<p><b>{{ title }}</b><slot /></p>',
            },
            HcisButton: { props: ['label'], template: '<button @click="$emit(\'click\')">{{ label }}</button>' },
        },
    },
});

describe('LangkahTinjauan — rekap', () => {
    it('menampilkan isian dari keempat langkah', () => {
        const t = pasang().text();

        expect(t).toContain('v03');
        expect(t).toContain('Menyesuaikan alur persetujuan');
        expect(t).toContain('Rina');
        expect(t).toContain('SOP-Perdin-v3.pdf');
    });

    it('merapikan tanggal jadi bentuk yang dibaca manusia', () => {
        const t = pasang().text();

        expect(t).toContain('1 April 2026');
        expect(t).toContain('1 April 2028');
    });

    it('menyebut field yang kosong, bukan menyembunyikannya', () => {
        /* Rekap yang menyembunyikan yang kosong meyakinkan admin bahwa semuanya
           beres — tepat sebelum tindakan yang tak bisa ditarik. */
        const w = pasang({ nilai: isian({ Konten_Nama: '', Tanggal_Kadaluarsa: '' }) });

        expect(w.text()).toContain('belum diisi');
    });

    it('menyatakan review kosong sebagai keadaan yang sah', () => {
        expect(pasang().text()).toContain('Tanpa catatan temuan');
    });

    it('meratakan hasil review yang bergaya jadi teks', () => {
        const w = pasang({
            review: [{ Review_Ke: 1, Tanggal_Review: '2026-03-02', Hasil_Review: '<p><b>Perbaiki</b> bagian 3</p>', Lampiran: [] }],
        });

        expect(w.text()).toContain('Perbaiki bagian 3');
        expect(w.text()).not.toContain('<b>');
    });

    it('menghitung lampiran review', () => {
        const w = pasang({
            review: [{ Review_Ke: 1, Hasil_Review: 'ada', Lampiran: [{}, {}] }],
        });

        expect(w.text()).toContain('2 lampiran');
    });

    it('menyembunyikan blok pengesahan untuk tipe yang tak mengenalnya', () => {
        const w = pasang({ pakaiPengesahan: false });

        expect(w.text()).not.toContain('Pengesahan');
    });

    it('menyebut paket ujian alih-alih berkas untuk kuis', () => {
        const w = pasang({ isKuis: true, nilai: isian({ Nama_Ujian: 'Ujian K3 Dasar' }) });

        expect(w.text()).toContain('Ujian K3 Dasar');
        expect(w.text()).not.toContain('Berkas final');
    });
});

describe('LangkahTinjauan — keadaan', () => {
    /**
     * Banner ini dulu menjanjikan bahwa penerbitan TAK BISA DITARIK. Sejak
     * DML-2026-064 janji itu keliru: versi terbit boleh dibatalkan selama belum
     * ada yang menerimanya. Yang dijaga sekarang adalah syarat sebenarnya —
     * peringatan yang salah membuat admin menerbitkan dengan takut, lalu
     * menemukan sendiri bahwa aturannya lain.
     */
    it('menyebut syarat sebenarnya untuk membatalkan yang sudah terbit', () => {
        const t = pasang().text();

        expect(t).toContain('hanya bisa dibatalkan selama belum ada karyawan yang menerimanya');
        expect(t).not.toContain('tak bisa ditarik');
    });

    it('menyebut apa yang masih kurang', () => {
        const w = pasang({ kurang: ['Berkas final'] });

        expect(w.text()).toContain('belum siap diterbitkan');
        expect(w.text()).toContain('Berkas final');
    });

    it('berganti nada setelah versinya terbit', () => {
        const w = pasang({ sudahTerbit: true });

        expect(w.text()).toContain('sudah resmi terbit');
        expect(w.text()).toContain('mengikat seluruh karyawan');
    });

    it('tombol sunting melompat ke langkah pemiliknya', async () => {
        const w = pasang();
        await w.findAll('button')[0].trigger('click');

        expect(w.emitted('sunting')[0]).toEqual([1]);
    });
});
