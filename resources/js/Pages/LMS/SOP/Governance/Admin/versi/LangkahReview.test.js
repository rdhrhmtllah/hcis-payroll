import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import LangkahReview from './LangkahReview.vue';

/**
 * Catatan review pada versi yang TERKUNCI.
 *
 * Langkah ini meneruskan `terkunci` ke tanggal, tombol tambah, unggah, dan
 * hapus — tapi SEMPAT TIDAK ke editor teksnya, sebab SopRichText memang belum
 * punya prop kunci. Akibatnya isian yang paling berisi kalimat audit justru
 * yang paling terbuka: pada versi arsip dan riwayat, catatannya tetap bisa
 * diketik dan diubah.
 *
 * Editornya di-stub. Yang dijaga di sini adalah PENERUSAN kuncinya — bukan
 * perilaku Quill, yang punya rumahnya sendiri.
 */
const BARIS = [{
    Review_Ke: 1,
    Tanggal_Review: '2026-08-01',
    Hasil_Review: '<p>Temuan lama</p>',
    Lampiran: [{ Nama_Berkas: 'bukti.png', Path_Berkas: '/x.png' }],
}];

const stubRichText = {
    name: 'SopRichText',
    props: ['modelValue', 'terkunci', 'placeholder'],
    template: '<div class="stub-rte" :data-terkunci="String(terkunci)"></div>',
};

const pasang = (terkunci) => mount(LangkahReview, {
    props: { baris: JSON.parse(JSON.stringify(BARIS)), idVersi: 'hash-versi', terkunci },
    global: {
        stubs: {
            SopRichText: stubRichText,
            HcisFileViewer: true,
        },
    },
});

describe('LangkahReview — penerusan kunci', () => {
    /**
     * TERKUNCI = TEKSNYA SAJA, editornya tak dirender sama sekali.
     *
     * Percobaan pertama mematikan Quill di tempat, dan itu keliru: editor yang
     * dilumpuhkan tetap membawa toolbar dan lapisan penangkap kliknya, dan
     * lapisan itu menelan klik pada apa pun di sekitarnya. Yang dibutuhkan pada
     * arsip memang bukan editor mati, melainkan bacaan.
     */
    it('tidak merender editor sama sekali saat terkunci', () => {
        const w = pasang(true);

        expect(w.find('.stub-rte').exists()).toBe(false);
        expect(w.find('.sop-review-baca').exists()).toBe(true);
        expect(w.find('.sop-review-baca').text()).toContain('Temuan lama');
    });

    it('merender editor saat tidak terkunci', () => {
        const w = pasang(false);

        expect(w.find('.stub-rte').exists()).toBe(true);
        expect(w.find('.sop-review-baca').exists()).toBe(false);
    });

    /** Catatan kosong tetap punya kalimatnya sendiri, bukan kotak hampa. */
    it('menyebut putaran yang tak bercatatan', () => {
        const w = mount(LangkahReview, {
            props: {
                baris: [{ Review_Ke: 1, Tanggal_Review: '2026-08-01', Hasil_Review: '', Lampiran: [] }],
                idVersi: 'hash-versi',
                terkunci: true,
            },
            global: { stubs: { SopRichText: stubRichText, HcisFileViewer: true } },
        });

        expect(w.find('.sop-review-baca').text()).toContain('Tak ada catatan');
    });

    /**
     * Lampiran tak boleh bisa dibuang pada versi terkunci. Servernya memang
     * menolak simpanannya, tapi tombol yang bisa ditekan lalu ditolak di ujung
     * adalah cara admin kehilangan kepercayaan pada layar.
     */
    it('menyembunyikan tombol hapus lampiran saat terkunci', () => {
        /* DISEMBUNYIKAN, bukan sekadar dimatikan: tombol ini tak punya gaya
           :disabled sama sekali, jadi saat mati pun ia tetap merah dan tetap
           menggelap saat di-hover — menjanjikan tindakan yang tak ada. */
        expect(pasang(true).find('.sop-lampiran-del').exists()).toBe(false);
    });

    it('menampilkan tombol hapus lampiran saat tidak terkunci', () => {
        expect(pasang(false).find('.sop-lampiran-del').exists()).toBe(true);
    });
});
