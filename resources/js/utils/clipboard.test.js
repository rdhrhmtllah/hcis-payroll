import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { salinTeks } from './clipboard';

/**
 * Tombol salin harus bekerja di http, bukan hanya di https.
 *
 * `navigator.clipboard` tidak ada pada konteks tak aman. Pola lama —
 * `navigator.clipboard?.writeText(t).then(...)` — di sana memanggil `.then()`
 * pada `undefined` dan melempar TypeError, sehingga tombolnya sekadar mati
 * tanpa pesan apa pun. Uji ini menagih KEDUA konteks, karena yang satu hijau
 * sementara yang lain merah adalah persis keadaan yang menyembunyikan bug itu
 * selama pengembangan.
 */
describe('salinTeks', () => {
    let clipboardAsli;

    beforeEach(() => {
        clipboardAsli = Object.getOwnPropertyDescriptor(navigator, 'clipboard');
        document.execCommand = vi.fn(() => true);
    });

    afterEach(() => {
        if (clipboardAsli) {
            Object.defineProperty(navigator, 'clipboard', clipboardAsli);
        } else {
            delete navigator.clipboard;
        }
        vi.restoreAllMocks();
    });

    const pasangClipboard = (impl) => {
        Object.defineProperty(navigator, 'clipboard', {
            value: impl,
            configurable: true,
            writable: true,
        });
    };

    it('memakai Clipboard API ketika tersedia', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        pasangClipboard({ writeText });

        await expect(salinTeks('PMT0926-0001')).resolves.toBe(true);
        expect(writeText).toHaveBeenCalledWith('PMT0926-0001');
        expect(document.execCommand).not.toHaveBeenCalled();
    });

    it('tetap menyalin di konteks tak aman tanpa Clipboard API', async () => {
        // Inilah keadaan http://hcis-w-3.test. Bentuk lamanya melempar di sini.
        pasangClipboard(undefined);

        await expect(salinTeks('VSR0926-0082')).resolves.toBe(true);
        expect(document.execCommand).toHaveBeenCalledWith('copy');
    });

    it('jatuh ke jalan mundur ketika Clipboard API menolak', async () => {
        // Penolakan nyata: dokumen tidak sedang fokus, atau izin ditolak.
        pasangClipboard({ writeText: vi.fn().mockRejectedValue(new Error('not focused')) });

        await expect(salinTeks('PMT0926-0002')).resolves.toBe(true);
        expect(document.execCommand).toHaveBeenCalledWith('copy');
    });

    it('memulangkan false — bukan melempar — ketika keduanya gagal', async () => {
        // Pemanggilnya yang memutuskan apa yang ditampilkan; yang dilarang
        // adalah kegagalan yang diam.
        pasangClipboard(undefined);
        document.execCommand = vi.fn(() => false);

        await expect(salinTeks('apa pun')).resolves.toBe(false);
    });

    it('teks kosong bukan kegagalan yang perlu dilaporkan', async () => {
        pasangClipboard({ writeText: vi.fn() });

        await expect(salinTeks('')).resolves.toBe(false);
        await expect(salinTeks(null)).resolves.toBe(false);
        await expect(salinTeks('   ')).resolves.toBe(false);
    });

    it('tidak meninggalkan textarea bayangan di dokumen', async () => {
        // Elemen sisa akan menumpuk setiap kali tombol ditekan, dan jebakan
        // fokus modal bisa tersangkut padanya.
        pasangClipboard(undefined);
        const sebelum = document.body.children.length;

        await salinTeks('PMT0926-0003');

        expect(document.body.children.length).toBe(sebelum);
    });
});
