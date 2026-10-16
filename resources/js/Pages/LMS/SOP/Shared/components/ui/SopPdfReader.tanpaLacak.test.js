import { describe, expect, it, vi } from 'vitest';
import SopPdfReader from './SopPdfReader.vue';

/**
 * Mode tanpaLacak — contoh pengisian FORM "hanya lihat" (10 Okt 2026).
 *
 * Pembaca aman dipakai demi tanda air, padahal FORM tak dilacak per halaman.
 * Pertama kali dipasang dengan readonly, dan readonly ternyata juga menutup
 * penyelesaian: halaman terakhir contoh pengisian tak punya cara menyelesaikan
 * item. tanpaLacak memisahkan keduanya — pelacakan mati, penyelesaian tetap.
 *
 * Diuji pada opsi komponennya langsung: me-mount pembaca berarti memalsukan
 * handshake token, manifest, dan gambar halaman, padahal yang dijaga di sini
 * hanya empat keputusan kecil.
 */
const { computed: c, methods: m } = SopPdfReader;

describe('SopPdfReader — tanpaLacak', () => {
    it('penyelesaian tetap terbuka setelah semua halaman terlihat', () => {
        expect(c.canFinish.call({ readonly: false, tanpaLacak: true, allRead: true })).toBe(true);
        expect(c.finishBannerVisible.call({ readonly: false, tanpaLacak: true, allRead: true, showSuccess: false })).toBe(
            true,
        );
    });

    it('tidak mengirim progres halaman ke server', () => {
        // Jalur kirimnya banyak (axios, fetch keepalive, sendBeacon). Yang diuji
        // adalah gerbang di depannya: fungsi harus berhenti SEBELUM membaca
        // durasi aktif — titik pertama yang dibaca setiap jalur kirim.
        const fetchPalsu = vi.fn();
        vi.stubGlobal('fetch', fetchPalsu);
        const ctx = { idItem: 'hash-item', readonly: false, tanpaLacak: true, maxPage: 2, _halamanTerkirim: 0 };
        Object.defineProperty(ctx, '_aktifDetik', {
            get() {
                throw new Error('trackPage melewati gerbang tanpaLacak');
            },
        });

        expect(() => m.trackPage.call(ctx, true)).not.toThrow();
        expect(fetchPalsu).not.toHaveBeenCalled();
        vi.unstubAllGlobals();
    });

    it('tidak memasang jeda baca: halaman langsung dihitung terlihat', () => {
        const ctx = { dwellTimer: null, dwellDone: new Set(), total: 5, readonly: false, tanpaLacak: true };

        m.startDwell.call(ctx, 2);

        expect(ctx.dwellDone.has(2)).toBe(true);
        expect(ctx.dwellOn).toBe(false);
    });

    it('menawarkan form kosong hanya dalam mode tanpaLacak', () => {
        const lampiran = [{ Id_Lampiran: 'l1', Nama: 'Form A', Ext: 'XLSX' }];

        expect(c.formKosong.call({ tanpaLacak: true, dokumen: { Lampiran: lampiran } })).toEqual(lampiran);
        expect(c.formKosong.call({ tanpaLacak: false, dokumen: { Lampiran: lampiran } })).toEqual([]);
        expect(c.formKosong.call({ tanpaLacak: true, dokumen: null })).toEqual([]);
    });
});
