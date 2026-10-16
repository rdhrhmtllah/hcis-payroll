import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SopKontenViewer from './SopKontenViewer.vue';

/**
 * Percabangan penampil konten: mana yang masuk pembaca aman, mana yang cukup
 * ditampilkan peramban apa adanya.
 *
 * Bahayanya halus. Berkas formulir PDF punya berkas_id DAN total_halaman —
 * hasil render halaman demi halaman yang sama persis dengan dokumen SOP — jadi
 * ia lolos ke pembaca aman tanpa ada yang keliatan salah: layarnya terbuka,
 * halamannya benar. Yang salah adalah gerbang dwell dan pelacakan halaman ikut
 * terpasang pada item yang memang tak dilacak.
 */

/** Konten PDF yang sudah ter-render — persis bentuk yang bikin cabangnya rancu. */
const PDF_TERRENDER = {
    type: 'document',
    judul: 'Formulir Klaim',
    url: 'https://storage.example/signed.pdf',
    berkas_id: 'abc123',
    total_halaman: 11,
    mime: 'application/pdf',
};

const XLSX = {
    type: 'document',
    judul: 'Formulir Klaim',
    url: 'https://storage.example/signed.xlsx',
    berkas_id: null,
    total_halaman: null,
    mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
};

function pasang(props = {}) {
    return mount(SopKontenViewer, {
        props: { show: true, loading: false, ...props },
        global: {
            stubs: {
                // props dideklarasikan supaya penerusannya bisa DIPERIKSA, bukan
                // sekadar tertelan attrs stub dan lolos tanpa terlihat.
                SopPdfReader: {
                    name: 'SopPdfReader',
                    props: ['admin', 'bolehUnduh', 'mengunduh', 'readonly', 'tanpaLacak'],
                    emits: ['unduh', 'unduh-lampiran', 'unduh-semua-lampiran'],
                    template: '<div class="stub-reader" />',
                },
            },
        },
    });
}

const adaPembacaAman = (w) => w.find('.stub-reader').exists();

describe('SopKontenViewer — pemilihan jalur tampil', () => {
    it('tidak menyeret formulir PDF ke pembaca terlacak', () => {
        const w = pasang({ tipe: 'FORM', konten: PDF_TERRENDER });

        expect(adaPembacaAman(w)).toBe(false);
        expect(w.find('iframe').attributes('src')).toBe(PDF_TERRENDER.url);
    });

    it('tetap mengirim dokumen ke pembaca terlacak', () => {
        // Sisi lain dari pengecualian di atas. Kalau ini ikut lolos ke iframe,
        // tanda air dan pelacakan halaman hilang tanpa jejak.
        const w = pasang({ tipe: 'DOKUMEN', konten: PDF_TERRENDER });

        expect(adaPembacaAman(w)).toBe(true);
    });

    it('menawarkan unduh pada formulir, apa pun jenis berkasnya', async () => {
        for (const konten of [PDF_TERRENDER, XLSX]) {
            const w = pasang({ tipe: 'FORM', konten });
            const tombol = w.findAll('button').filter((b) => b.text().includes('Unduh'));

            expect(tombol.length, `berkas ${konten.mime} tak punya tombol unduh`).toBe(1);

            await tombol[0].trigger('click');
            expect(w.emitted('unduh')).toHaveLength(1);
        }
    });

    it('tidak menawarkan unduh pada dokumen yang hanya boleh dilihat', () => {
        // Item FORM dilonggarkan; DOKUMEN VIEW_ONLY tidak boleh ikut longgar.
        const w = pasang({ tipe: 'VIDEO', modeAkses: 'VIEW_ONLY', konten: { type: 'video', url: 'https://x/embed' } });

        expect(w.findAll('button').filter((b) => b.text().includes('Unduh'))).toHaveLength(0);
    });

    it('meneruskan penanda admin ke pembaca aman', () => {
        // Penanda ini yang memilih pintu handshake. Admin membuka draft dan
        // versi cabutan — dua keadaan yang ditolak lensa karyawan — jadi kalau
        // penandanya tersangkut di komponen tengah ini, pratinjau Master
        // Dokumen kembali berbalas 403 tanpa satu pun kesalahan data.
        const w = pasang({ tipe: 'DOKUMEN', konten: PDF_TERRENDER, admin: true });

        expect(w.findComponent({ name: 'SopPdfReader' }).props('admin')).toBe(true);
    });

    it('mengunci tombol unduh selagi berkasnya disiapkan', () => {
        // Tanpa ini, klik beruntun menandatangani beberapa URL sekaligus.
        const w = pasang({ tipe: 'FORM', konten: XLSX, mengunduh: true });
        const tombol = w.findAll('button').filter((b) => b.text().includes('Menyiapkan'))[0];

        expect(tombol.attributes('disabled')).toBeDefined();
    });
    /* ── Izin unduh harus SAMPAI ke pembaca aman ──────────────────────────
       Dokumen dibuka lewat SopPdfReader, dan kaki-penampil yang memuat tombol
       unduh ada di cabang v-else — cabang yang tak pernah dirender untuk
       dokumen. Selama izinnya tidak diteruskan ke pembaca, admin bisa
       menyalakan "boleh unduh", lencana halaman menulis "Izin Unduh", dan
       tetap tak ada apa pun yang bisa diklik. Persis begitu cacatnya berjalan
       sampai 2026-09-01. */

    it('meneruskan izin unduh ke pembaca aman', () => {
        const w = pasang({ tipe: 'DOKUMEN', modeAkses: 'VIEW_UNDUH', konten: PDF_TERRENDER });

        expect(w.findComponent({ name: 'SopPdfReader' }).props('bolehUnduh')).toBe(true);
    });

    it('tidak meneruskan izin unduh pada dokumen yang hanya boleh dilihat', () => {
        const w = pasang({ tipe: 'DOKUMEN', modeAkses: 'VIEW_ONLY', konten: PDF_TERRENDER });

        expect(w.findComponent({ name: 'SopPdfReader' }).props('bolehUnduh')).toBe(false);
    });

    it('meneruskan permintaan unduh dari pembaca aman ke atas', () => {
        // Tombolnya hidup di dalam pembaca; tanpa penerusan ini kliknya berhenti
        // di komponen tengah dan tak pernah sampai ke pemanggil yang benar-benar
        // meminta URL bertanda tangan.
        const w = pasang({ tipe: 'DOKUMEN', modeAkses: 'VIEW_UNDUH', konten: PDF_TERRENDER });

        w.findComponent({ name: 'SopPdfReader' }).vm.$emit('unduh');

        expect(w.emitted('unduh')).toHaveLength(1);
    });
});

/* ── Contoh pengisian FORM: izin unduh per paket (10 Okt 2026) ─────────────
   Server memutuskan lewat Boleh_Unduh di item paket. Yang hanya boleh dilihat
   harus masuk pembaca aman (tanda air, tanpa URL mentah) TANPA pelacakan
   halaman — tetapi penyelesaian di akhir tetap ada, dan form kosongnya
   ditawarkan di sana. */
describe('SopKontenViewer — contoh pengisian FORM', () => {
    it('membuka contoh pengisian hanya-lihat di pembaca aman, tanpa pelacakan, tanpa unduh', () => {
        const w = pasang({ tipe: 'FORM', konten: PDF_TERRENDER, dokumen: { Boleh_Unduh: false } });
        const reader = w.findComponent({ name: 'SopPdfReader' });

        expect(reader.exists()).toBe(true);
        expect(reader.props('bolehUnduh')).toBe(false);
        expect(reader.props('tanpaLacak')).toBe(true);
        expect(w.find('iframe').exists()).toBe(false);
    });

    it('tidak menutup penyelesaian: readonly hanya untuk versi lama / sudah selesai', () => {
        // Sempat readonly selalu (10 Okt 2026) — halaman terakhir contoh
        // pengisian lalu tak punya cara menyelesaikan item sama sekali.
        const w = pasang({ tipe: 'FORM', konten: PDF_TERRENDER, dokumen: { Boleh_Unduh: false } });

        expect(w.findComponent({ name: 'SopPdfReader' }).props('readonly')).toBe(false);
    });

    it('meneruskan permintaan unduh form kosong dari pembaca ke atas', () => {
        const w = pasang({ tipe: 'FORM', konten: PDF_TERRENDER, dokumen: { Boleh_Unduh: false } });
        const reader = w.findComponent({ name: 'SopPdfReader' });
        const lampiran = { Id_Lampiran: 'l1', Nama: 'Form A', Ext: 'XLSX' };

        reader.vm.$emit('unduh-lampiran', lampiran);
        reader.vm.$emit('unduh-semua-lampiran');

        expect(w.emitted('unduh-lampiran')[0]).toEqual([lampiran]);
        expect(w.emitted('unduh-semua-lampiran')).toHaveLength(1);
    });

    it('dokumen biasa tidak ikut kehilangan pelacakan', () => {
        const w = pasang({ tipe: 'DOKUMEN', modeAkses: 'VIEW_ONLY', konten: PDF_TERRENDER });

        expect(w.findComponent({ name: 'SopPdfReader' }).props('tanpaLacak')).toBe(false);
    });

    it('menampilkan contoh pengisian boleh-unduh apa adanya, dengan tombol unduh', () => {
        const w = pasang({ tipe: 'FORM', konten: PDF_TERRENDER, dokumen: { Boleh_Unduh: true } });

        expect(adaPembacaAman(w)).toBe(false);
        expect(w.find('iframe').attributes('src')).toBe(PDF_TERRENDER.url);
        expect(w.findAll('button').filter((b) => b.text().includes('Unduh'))).toHaveLength(1);
    });

    it('tidak menawarkan unduh di kaki penampil bila server menolaknya', () => {
        // Contoh PDF hanya-lihat yang belum ter-render jatuh ke cabang biasa;
        // tombol unduhnya tetap tak boleh muncul.
        const belumRender = { ...PDF_TERRENDER, total_halaman: null, url: undefined };
        const w = pasang({ tipe: 'FORM', konten: belumRender, dokumen: { Boleh_Unduh: false } });

        expect(w.findAll('button').filter((b) => b.text().includes('Unduh'))).toHaveLength(0);
    });

    it('pratinjau admin tanpa item paket tetap boleh unduh formulir', () => {
        // Izin itu milik paket; Master Dokumen tak punya paket untuk dibaca.
        const w = pasang({ tipe: 'FORM', konten: PDF_TERRENDER, admin: true });

        expect(adaPembacaAman(w)).toBe(false);
        expect(w.findAll('button').filter((b) => b.text().includes('Unduh'))).toHaveLength(1);
    });
});
