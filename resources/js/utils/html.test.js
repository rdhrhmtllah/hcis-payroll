import { describe, expect, it } from 'vitest'

import { escapeHtml } from './html'

/**
 * escapeHtml adalah lapis terakhir sebelum sebuah nilai masuk ke HTML yang
 * dirakit sebagai string -- v-html dan prop raw-content milik el-tooltip. Di
 * situ Vue tidak meng-escape apa pun untuk kita.
 */
describe('escapeHtml', () => {
    it('menutup kelima karakter yang berarti bagi parser', () => {
        expect(escapeHtml('<>&"\'')).toBe('&lt;&gt;&amp;&quot;&#39;');
    });

    it('ampersand ditangani lebih dulu agar tidak ter-escape dua kali', () => {
        expect(escapeHtml('&lt;')).toBe('&amp;lt;');
    });

    it('mematahkan tag yang berusaha masuk lewat nama orang', () => {
        expect(escapeHtml('<img src=x onerror=alert(1)>')).not.toContain('<img');
    });

    it('mematahkan usaha keluar dari atribut', () => {
        expect(escapeHtml('" onmouseover="jahat()')).not.toContain('onmouseover="jahat');
    });

    it('nilai yang tidak ada menjadi teks kosong, bukan "null"', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
    });

    it('angka tetap terbaca apa adanya', () => {
        expect(escapeHtml(1234)).toBe('1234');
    });
});
