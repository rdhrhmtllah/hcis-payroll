import { describe, expect, it } from 'vitest';
import { payloadItem } from './itemPayload.js';

/**
 * Ambang kirim item Builder: SATU jalur dokumen, dan jalur itu selalu
 * "tunjuk dokumen yang sudah ada".
 *
 * Mode "buat dokumen baru" dicabut 27 Agu 2026 bersama endpoint-nya. Uji ini
 * tak lagi memilih di antara dua tab; yang dijaga sekarang adalah bahwa sisa
 * muatan mode lama TIDAK ikut berangkat. Formulir yang dibuka sebelum halaman
 * disegarkan masih bisa memegangnya, dan server menolak muatan yang memuat
 * Dokumen_Baru — penolakan yang mustahil dimengerti admin untuk field yang
 * sudah tak ada di layarnya.
 */

const LENGKAP = {
    Tipe_Item: 'DOKUMEN',
    Id_SOP_Section: 'sec-1',
    Id_SOP_Dokumen: 'dok-lama',
    Judul_Item: 'SOP Lama',
};

describe('payloadItem — satu jalur dokumen', () => {
    it('dokumen terpilih berangkat apa adanya', () => {
        const p = payloadItem(LENGKAP);

        expect(p.Id_SOP_Dokumen).toBe('dok-lama');
        expect(p.Tipe_Item).toBe('DOKUMEN');
    });

    it('sisa formulir mode lama tidak ikut berangkat', () => {
        /* Formulir basi dari tab yang belum disegarkan. Membiarkannya lewat
           berarti server menolak dengan pesan tentang "Dokumen baru" untuk
           blok yang sudah tak dirender di mana pun. */
        const p = payloadItem({
            ...LENGKAP,
            Mode_Dokumen: 'baru',
            Dokumen_Baru: { Judul: 'Dokumen Baru', Id_Master_Konten: 'kt-1', Disahkan_Oleh: 'Budi' },
        });

        expect('Dokumen_Baru' in p, 'Dokumen_Baru masih ikut terkirim').toBe(false);
        expect('Mode_Dokumen' in p, 'Mode_Dokumen masih ikut terkirim').toBe(false);
        // Dan yang benar tetap berangkat — pembuangannya tak boleh ikut menelan ini.
        expect(p.Id_SOP_Dokumen).toBe('dok-lama');
    });

    it('penanda layar dan nilai turunan tidak ikut dikirim', () => {
        /* Pointer versi aktif digeser oleh PENERBITAN versi, tak pernah oleh
           formulir item. Mengirimnya balik hanya mengundang suatu saat ada yang
           menuliskannya — dan pointer yang ditulis dari formulir bisa menunjuk
           versi yang belum sah. */
        const p = payloadItem({
            ...LENGKAP,
            Dokumen_Punya_Versi: true,
            Id_Item_Versi_Aktif: 'v-1',
            Paket_Lain_Pemakai: 3,
            Sudah_Pernah_Terbit: false,
        });

        for (const k of [
            'Mode_Dokumen',
            'Dokumen_Baru',
            'Dokumen_Punya_Versi',
            'Id_Item_Versi_Aktif',
            'Paket_Lain_Pemakai',
            'Sudah_Pernah_Terbit',
        ]) {
            expect(k in p, `${k} ikut terkirim ke server`).toBe(false);
        }
    });

    it('tidak mengubah formulir aslinya', () => {
        // Formulir yang ikut terpangkas membuat isian hilang dari layar tepat
        // saat penyimpanannya gagal — justru saat orang paling butuh isinya utuh.
        const form = { ...LENGKAP, Deskripsi_Item: 'catatan penempatan' };
        payloadItem(form);

        expect(form.Deskripsi_Item).toBe('catatan penempatan');
        expect(form.Id_SOP_Dokumen).toBe('dok-lama');
    });
});
