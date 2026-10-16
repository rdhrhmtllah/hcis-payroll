import { describe, expect, it } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import SopLivePreview from './SopLivePreview.vue';

/**
 * Pratinjau Builder menerima DUA bentuk bendera, dan harus memperlakukan
 * keduanya sama.
 *
 * Baris dari server memakai 'Y'/'T'. Formulir yang sedang disunting memakai
 * boolean, karena kotak centang Vue memang menghasilkan boolean. Selama
 * nilainya diteruskan mentah lalu dibandingkan `=== 'Y'` di kartu pratinjau,
 * mencentang "Sorot di detail" tak mengubah apa pun di pratinjau — padahal di
 * sisi karyawan sorotannya muncul. Pratinjau yang berbohong lebih buruk
 * daripada tak ada pratinjau: admin menyimpulkan fiturnya yang rusak.
 *
 * `wajib` sudah dinormalkan sejak dulu; `Flag_Highlight` tertinggal — satu dari
 * dua bendera kembar. Uji ini menjaga keduanya sekaligus supaya yang berikutnya
 * tak bisa tertinggal lagi diam-diam.
 */

function normalisasi(src) {
    const w = shallowMount(SopLivePreview, { props: { paket: {}, sections: [], items: [] } });

    return w.vm.normalize(src, false);
}

const BENDERA = [
    { nama: 'Sorot di detail', dari: 'Flag_Highlight', ke: 'highlight' },
    { nama: 'Wajib diselesaikan', dari: 'Flag_Wajib', ke: 'wajib' },
];

describe('SopLivePreview — bendera item', () => {
    it.each(BENDERA)('$nama: bentuk server "Y"/"T" terbaca benar', ({ dari, ke }) => {
        expect(normalisasi({ [dari]: 'Y' })[ke]).toBe(true);
        expect(normalisasi({ [dari]: 'T' })[ke]).toBe(false);
    });

    it.each(BENDERA)('$nama: bentuk formulir boolean terbaca benar', ({ dari, ke }) => {
        // Inilah bentuk yang dulu diam-diam terbaca "tidak": true !== 'Y'.
        expect(normalisasi({ [dari]: true })[ke]).toBe(true);
        expect(normalisasi({ [dari]: false })[ke]).toBe(false);
    });

    it.each(BENDERA)('$nama: tidak diisi berarti tidak menyala', ({ dari, ke }) => {
        expect(normalisasi({})[ke]).toBe(false);
        expect(normalisasi({ [dari]: null })[ke]).toBe(false);
    });

    it('kedua bendera dinormalkan jadi boolean, bukan diteruskan mentah', () => {
        /* Yang dibandingkan kartu pratinjau adalah nilai INI. Kalau salah satu
           kembali diteruskan mentah, ia akan lolos uji nilai di atas untuk satu
           bentuk dan gagal senyap untuk bentuk lainnya. */
        const hasil = normalisasi({ Flag_Highlight: 'Y', Flag_Wajib: 'Y' });

        for (const { ke } of BENDERA) {
            expect(typeof hasil[ke], `${ke} bukan boolean`).toBe('boolean');
        }
    });
});
