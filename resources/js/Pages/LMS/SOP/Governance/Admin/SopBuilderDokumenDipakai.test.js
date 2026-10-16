import { describe, expect, it } from 'vitest';
import SopBuilder from './SopBuilder.vue';

/**
 * Daftar dokumen yang sudah ditempatkan di paket ini.
 *
 * Rantainya panjang — server menghitung, Builder menyusun daftarnya, ItemFields
 * meneruskan, pemilih mematikan barisnya — dan tiap sambungan sudah punya
 * ujinya sendiri KECUALI yang ini. Sambungan yang tak diuji adalah sambungan
 * yang diam-diam putus: seluruh rantai terlihat benar dan hasilnya tetap nihil.
 *
 * Diuji sebagai fungsi murni, tanpa merakit halaman Builder: nilainya hanya
 * bergantung pada items + item yang sedang dibuka.
 */

const hitung = (konteks) => SopBuilder.computed.dokumenDipakaiPaket.call(konteks);

const ITEMS = [
    { Id_SOP_Paket_Item: 'i1', Id_SOP_Dokumen: 'dok-a' },
    { Id_SOP_Paket_Item: 'i2', Id_SOP_Dokumen: 'dok-b' },
    { Id_SOP_Paket_Item: 'i3', Id_SOP_Dokumen: null }, // PARAGRAF/TAUTAN
];

describe('SopBuilder — dokumen yang sudah dipakai di paket', () => {
    it('mengumpulkan dokumen semua item saat menambah item baru', () => {
        // Item baru: belum ada yang dikecualikan, semuanya terpakai.
        expect(hitung({ items: ITEMS, itemForm: {}, openItemId: null })).toEqual(['dok-a', 'dok-b']);
    });

    it('mengecualikan item yang sedang dibuka', () => {
        /* Dokumen milik item yang sedang disunting tentu "sudah dipakai" —
           olehnya. Mematikannya di pemilih akan membuat admin mengira dokumen
           yang sedang ia gunakan bermasalah. */
        expect(hitung({ items: ITEMS, itemForm: { id: 'i1' }, openItemId: 'i1' })).toEqual(['dok-b']);
    });

    it('mengabaikan item tanpa dokumen', () => {
        // PARAGRAF/TAUTAN tak punya dokumen; null yang ikut masuk daftar akan
        // mematikan baris yang Id_SOP_Dokumen-nya kebetulan juga kosong.
        expect(hitung({ items: ITEMS, itemForm: {}, openItemId: null })).not.toContain(null);
    });

    it('aman saat daftar itemnya masih kosong', () => {
        expect(hitung({ items: [], itemForm: {}, openItemId: null })).toEqual([]);
    });
});
