import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { HARI_ULANG, catatTanggapan, perluDiingatkan, sesiLogin, tandaTangan } from './panduanPengingat.js';

function penyimpananTiruan() {
    let isi = {};
    return {
        getItem: (k) => (k in isi ? isi[k] : null),
        setItem: (k, v) => {
            isi[k] = String(v);
        },
        removeItem: (k) => {
            delete isi[k];
        },
        clear: () => {
            isi = {};
        },
    };
}

const HARI = 24 * 60 * 60 * 1000;
const T0 = Date.UTC(2026, 8, 29, 1, 0, 0);
const dasar = { pengguna: 'K001', halaman: 'vehiclePermitPage', tanda: '9:2026-09-01' };

/** "Sampai dibuka, lalu 7 hari" — keputusan user 29 Sep 2026. */
describe('panduanPengingat', () => {
    beforeEach(() => {
        vi.stubGlobal('localStorage', penyimpananTiruan());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    const cek = (o = {}) => perluDiingatkan({ ...dasar, sesi: 'A', sekarang: T0, ...o });
    const catat = (jenis, o = {}) => catatTanggapan({ ...dasar, jenis, sesi: 'A', sekarang: T0, ...o });

    it('pertama kali: muncul', () => {
        expect(cek()).toEqual({ muncul: true, pengingatUlang: false });
    });

    it('belum pernah dibuka: "Mengerti" menunda sampai sesi login berikutnya saja', () => {
        catat('mengerti');
        expect(cek().muncul).toBe(false);
        expect(cek({ sekarang: T0 + 30 * HARI }).muncul).toBe(false); // sesi sama
        expect(cek({ sesi: 'B' }).muncul).toBe(true); // login lagi
    });

    it('sudah dibuka: diingatkan lagi setelah HARI_ULANG hari, bukan tiap login', () => {
        catat('dibuka');
        expect(cek({ sesi: 'B' }).muncul).toBe(false);
        expect(cek({ sesi: 'B', sekarang: T0 + (HARI_ULANG - 1) * HARI }).muncul).toBe(false);
        expect(cek({ sesi: 'B', sekarang: T0 + HARI_ULANG * HARI })).toEqual({ muncul: true, pengingatUlang: true });

        // Ditanggapi lagi: hitungan 7 hari mulai ulang, status "pernah dibuka" tetap.
        catat('mengerti', { sesi: 'B', sekarang: T0 + HARI_ULANG * HARI });
        expect(cek({ sesi: 'C', sekarang: T0 + (HARI_ULANG + 1) * HARI }).muncul).toBe(false);
        expect(cek({ sesi: 'C', sekarang: T0 + 2 * HARI_ULANG * HARI }).muncul).toBe(true);
    });

    it('panduan diperbarui: langsung muncul lagi, termasuk setelah "Jangan ingatkan lagi"', () => {
        catat('jangan');
        expect(cek({ sesi: 'Z', sekarang: T0 + 365 * HARI }).muncul).toBe(false);

        const diperbarui = cek({ tanda: '9:2026-09-29' });
        expect(diperbarui.muncul).toBe(true);

        // Tanggapan atas versi baru tidak mewarisi "dibuka"/"jangan" versi lama.
        catat('mengerti', { tanda: '9:2026-09-29' });
        expect(cek({ tanda: '9:2026-09-29', sesi: 'B' }).muncul).toBe(true);
    });

    it('terpisah per halaman dan per akun', () => {
        catat('dibuka');
        expect(cek({ halaman: 'permitStatusPage' }).muncul).toBe(true);
        expect(cek({ pengguna: 'K002' }).muncul).toBe(true);
    });

    it('tanpa akun/halaman/panduan tidak pernah muncul; penyimpanan rusak dianggap kosong', () => {
        expect(cek({ pengguna: '' }).muncul).toBe(false);
        expect(cek({ tanda: '' }).muncul).toBe(false);
        localStorage.setItem('hcis.panduan.petunjuk.v2', '{rusak');
        expect(cek().muncul).toBe(true);
        expect(() => catat('mengerti')).not.toThrow();
    });

    it('tanda tangan tidak bergantung urutan; sesi dari token tanpa menyimpan tokennya', () => {
        expect(tandaTangan([{ id: 2, diperbarui: 'b' }, { id: 1, diperbarui: 'a' }])).toBe(tandaTangan([{ id: 1, diperbarui: 'a' }, { id: 2, diperbarui: 'b' }]));

        const meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        meta.setAttribute('content', 'token-rahasia-123');
        document.head.appendChild(meta);
        try {
            const sesi = sesiLogin();
            expect(sesi).toBeTruthy();
            expect(sesi).not.toContain('token-rahasia');
            meta.setAttribute('content', 'token-lain-456');
            expect(sesiLogin()).not.toBe(sesi);
        } finally {
            meta.remove();
        }
    });
});
