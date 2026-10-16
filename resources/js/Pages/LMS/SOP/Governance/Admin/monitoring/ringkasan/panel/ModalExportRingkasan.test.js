import { afterEach, describe, expect, it, vi } from 'vitest';
import { shallowMount } from '@vue/test-utils';
import ModalExportRingkasan from './ModalExportRingkasan.vue';

vi.mock('axios', () => ({
    default: { get: vi.fn().mockResolvedValue({ data: { success: true, result: {} } }) },
}));

/**
 * LAPORAN MONITORING DMS DITULIS KE JENDELA BARU — IA TAK MEWARISI CSS APA PUN.
 *
 * Ekspor PDF pada tab Ringkasan merakit satu dokumen HTML utuh lalu menuliskannya
 * lewat `document.write` ke jendela baru. Dokumen itu berdiri sendiri: tak satu
 * baris pun design-system.css ikut, termasuk blok `:root` yang mendefinisikan
 * token warna.
 *
 * Padahal gaya laporannya memakai token itu 97 kali pada 33 nama. Deklarasi
 * yang tokennya tak terdefinisi menjadi BATAL — dan latar yang batal berarti
 * transparan, sehingga laporan tercetak nyaris tanpa warna sementara teksnya
 * tetap muncul. Gejalanya terbaca seperti "style-nya hilang", padahal yang
 * hilang tokennya.
 *
 * Berkas ini mengunci penyuntik token itu: nama dipanen dari CSS laporan
 * sendiri (bukan diketik ulang), nilainya dibaca dari dokumen hidup (bukan
 * disalin), dan bloknya mendahului aturan yang memakainya.
 */

const pasang = () =>
    shallowMount(ModalExportRingkasan, {
        props: { show: false, saringan: {}, propOrang: 0 },
    });

/** Definisikan token pada dokumen uji, seperti design-system.css di aplikasi. */
function pasangToken(css) {
    const el = document.createElement('style');
    el.id = 'token-uji';
    el.textContent = css;
    document.head.appendChild(el);

    return el;
}

afterEach(() => {
    document.getElementById('token-uji')?.remove();
});

describe('ModalExportRingkasan — token desain ikut ke dokumen laporan', () => {
    it('menyuntikkan nilai token yang benar-benar terbaca dari dokumen hidup', () => {
        pasangToken(':root { --hcis-white: #ffffff; --hcis-slate-900: #0f172a; }');

        const html = '<html><head></head><body><div style="background: var(--hcis-white); '
            + 'color: var(--hcis-slate-900)">x</div></body></html>';

        const hasil = pasang().vm.denganTokenDesain(html);

        expect(hasil).toContain('--hcis-white: #ffffff;');
        expect(hasil).toContain('--hcis-slate-900: #0f172a;');
    });

    it('blok token mendahului aturan yang memakainya', () => {
        pasangToken(':root { --hcis-white: #ffffff; }');

        const html = '<html><head><style>.a{background:var(--hcis-white)}</style></head><body></body></html>';
        const hasil = pasang().vm.denganTokenDesain(html);

        /* Token harus sudah ada sebelum aturan yang membacanya — kalau tidak,
           urutannya benar secara kebetulan saja. */
        expect(hasil.indexOf('hcis-token-laporan')).toBeLessThan(hasil.indexOf('.a{background'));
    });

    /* UJI PENOLAKAN. Tanpa ini, penyuntik yang menuliskan `--x: ;` untuk token
       yang tak terbaca pun lulus — dan deklarasi kosong tetap BATAL, yaitu
       persis cacat yang berkas ini ada untuk menangkapnya. */
    it('token yang tak terdefinisi DILEWATI, bukan ditulis kosong', () => {
        pasangToken(':root { --hcis-white: #ffffff; }');

        const html = '<html><head></head><body style="color: var(--hcis-tidak-ada); '
            + 'background: var(--hcis-white)"></body></html>';
        const hasil = pasang().vm.denganTokenDesain(html);

        expect(hasil).toContain('--hcis-white: #ffffff;');
        expect(hasil).not.toContain('--hcis-tidak-ada:');
    });

    it('dokumen tanpa token dikembalikan apa adanya', () => {
        const html = '<html><head></head><body>polos</body></html>';

        expect(pasang().vm.denganTokenDesain(html)).toBe(html);
    });

    /* Nama token TIDAK diketik ulang di penyuntik — ia dipanen dari CSS laporan.
       Uji ini mengunci sifat itu: token yang belum pernah disebut di mana pun
       tetap ikut terbawa asal dipakai templatnya. */
    it('memanen nama token dari CSS laporan, bukan dari daftar yang dihafal', () => {
        pasangToken(':root { --hcis-token-baru-sekali: #123456; }');

        const html = '<html><head></head><body style="border-color: var(--hcis-token-baru-sekali)"></body></html>';

        expect(pasang().vm.denganTokenDesain(html)).toContain('--hcis-token-baru-sekali: #123456;');
    });
});
