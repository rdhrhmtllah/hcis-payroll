import { describe, it, expect } from 'vitest';
import { nomorVersi, labelVersi, nomorDokumen, nomorDokumenTampil } from './versi.js';

describe('nomorVersi — dua digit, mulai dari nol', () => {
    /**
     * INTI SELURUH PERUBAHAN INI. Nol itu falsy, dan penomoran versi kini
     * dimulai dari nol. Setiap `v ? … : '—'` di sepanjang modul mengubah versi
     * pertama menjadi "tanpa versi" — lima tempat terbukti melakukannya, satu
     * di antaranya sampai ke layar karyawan.
     */
    it('menampilkan nol sebagai 00, bukan tanda hubung', () => {
        expect(nomorVersi(0)).toBe('00');
        expect(labelVersi(0)).toBe('v00');
    });

    it('melengkapi satu digit menjadi dua', () => {
        expect(nomorVersi(1)).toBe('01');
        expect(nomorVersi(9)).toBe('09');
    });

    it('membiarkan dua digit apa adanya', () => {
        expect(nomorVersi(10)).toBe('10');
        expect(nomorVersi(99)).toBe('99');
    });

    /**
     * Memotongnya ke dua digit akan membuat v100 tampil sebagai v00 — yaitu
     * nomor lain yang juga benar-benar ada pada dokumen yang sama.
     */
    it('tidak memotong nomor yang lebih dari dua digit', () => {
        expect(nomorVersi(100)).toBe('100');
        expect(labelVersi(100)).toBe('v100');
    });

    /** Tak ada versi itu keadaan yang berbeda dari versi nol. */
    it('membedakan tidak-ada-versi dari versi nol', () => {
        expect(nomorVersi(null)).toBe('—');
        expect(nomorVersi(undefined)).toBe('—');
        expect(nomorVersi('')).toBe('—');
        expect(labelVersi(null)).toBe('—');
    });

    it('menerima angka dalam bentuk teks', () => {
        expect(nomorVersi('3')).toBe('03');
        expect(nomorVersi('0')).toBe('00');
    });
});

describe('nomorDokumen — tiga digit, mulai dari satu', () => {
    it('memadatkan nomor urut dokumen jadi tiga digit, sedangkan versi tetap dua', () => {
        expect(nomorDokumen(1)).toBe('001');
        expect(nomorDokumen('12')).toBe('012');
        expect(nomorDokumen(999)).toBe('999');
        expect(nomorVersi(1)).toBe('01');
    });

    it('tidak memotong nomor yang lebih lebar dan tetap mengenali kosong', () => {
        expect(nomorDokumen(1000)).toBe('1000');
        expect(nomorDokumen('')).toBe('—');
        expect(nomorDokumen(null)).toBe('—');
    });
});

describe('nomorDokumenTampil — nomor gabungan, bukan satu segmennya', () => {
    /**
     * CACAT YANG DIPERBAIKI. Nomor dokumen SOP adalah gabungan tujuh segmen,
     * dan No_Dokumen hanyalah segmen keenam. Tiga tempat di halaman wizard
     * jatuh ke sana saat Nomor_SOP_Resmi kosong — sehingga dokumen bernomor
     * SK/HOL/IA/EHG/02/01/?? tampil sebagai "1", angka yang tak pernah ada di
     * dokumen mana pun.
     */
    it('tidak pernah jatuh ke No_Dokumen', () => {
        expect(nomorDokumenTampil({ No_Dokumen: '1' })).toBeNull();
        expect(nomorDokumenTampil({
            No_Dokumen: '1',
            Nomor_Pratinjau: 'SK/HOL/IA/EHG/02/01/??',
        })).toBe('SK/HOL/IA/EHG/02/01/??');
    });

    it('mendahulukan nomor utuh atas pratinjau berlubang', () => {
        expect(nomorDokumenTampil({
            Nomor_Lengkap: 'SK/HOL/IA/EHG/02/01/00',
            Nomor_Pratinjau: 'SK/HOL/IA/EHG/02/01/??',
        })).toBe('SK/HOL/IA/EHG/02/01/00');
    });

    /**
     * Nomor_SOP_Resmi adalah SALINAN TERSIMPAN dari rakitan yang sama. Begitu
     * identitas dokumen berubah sesudah tersimpan, salinan itulah yang basi —
     * jadi rakitan segar dari server yang menang, bukan sebaliknya.
     */
    it('mengalahkan salinan tersimpan dengan rakitan segar', () => {
        expect(nomorDokumenTampil({
            Nomor_SOP_Resmi: 'SK/HOL/IA/EHG/02/01/00',
            Nomor_Pratinjau: 'IK/HOL/IA/EHG/02/01/??',
        })).toBe('IK/HOL/IA/EHG/02/01/??');
    });

    it('memakai salinan tersimpan bila rakitannya tak dikirim', () => {
        expect(nomorDokumenTampil({ Nomor_SOP_Resmi: 'SK/HOL/IA/EHG/02/01/00' }))
            .toBe('SK/HOL/IA/EHG/02/01/00');
    });

    it('mengembalikan null saat tak ada nomor sama sekali', () => {
        expect(nomorDokumenTampil({})).toBeNull();
        expect(nomorDokumenTampil(null)).toBeNull();
        expect(nomorDokumenTampil(undefined)).toBeNull();
    });
});
