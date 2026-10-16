import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { kompresGambar, SISI_MAKS_PX } from './kompresGambar';

function berkas(nama, type, ukuran) {
    const f = new File(['x'], nama, { type });
    Object.defineProperty(f, 'size', { value: ukuran });
    return f;
}

/** Kanvas tiruan: jsdom tidak punya canvas; ukuran blob hasil bisa diatur. */
function pasangKanvas(ukuranHasil) {
    const dicatat = { lebar: 0, tinggi: 0, tipe: null, kualitas: null };
    const asli = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation((tag) => {
        if (tag !== 'canvas') return asli(tag);
        const k = {
            set width(v) { dicatat.lebar = v; },
            set height(v) { dicatat.tinggi = v; },
            get width() { return dicatat.lebar; },
            get height() { return dicatat.tinggi; },
            getContext: () => ({ fillRect: () => {}, drawImage: () => {}, set fillStyle(_) {} }),
            toBlob: (cb, tipe, kualitas) => {
                dicatat.tipe = tipe;
                dicatat.kualitas = kualitas;
                const b = new Blob(['y'], { type: tipe });
                Object.defineProperty(b, 'size', { value: ukuranHasil });
                cb(b);
            },
        };
        return k;
    });
    return dicatat;
}

describe('kompresGambar', () => {
    beforeEach(() => {
        vi.stubGlobal('createImageBitmap', vi.fn(async () => ({ width: 4000, height: 3000, close: () => {} })));
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('foto kamera besar diperkecil: sisi terpanjang 1600 px, JPEG, nama .jpg', async () => {
        const kanvas = pasangKanvas(450 * 1024);
        const asli = berkas('IMG_1234.HEIC.jpeg', 'image/jpeg', 4.2 * 1024 * 1024);
        const hasil = await kompresGambar(asli);

        expect(kanvas.lebar).toBe(SISI_MAKS_PX);
        expect(kanvas.tinggi).toBe(1200);
        expect(kanvas.tipe).toBe('image/jpeg');
        expect(hasil.type).toBe('image/jpeg');
        expect(hasil.name).toBe('IMG_1234.HEIC.jpg');
        expect(hasil).not.toBe(asli);
        expect(kanvas.kualitas).toBe(0.8);
    });

    it('PDF dan GIF dikembalikan apa adanya', async () => {
        const pdf = berkas('stnk.pdf', 'application/pdf', 3 * 1024 * 1024);
        const gif = berkas('a.gif', 'image/gif', 2 * 1024 * 1024);
        expect(await kompresGambar(pdf)).toBe(pdf);
        expect(await kompresGambar(gif)).toBe(gif);
    });

    it('gambar yang sudah kecil tidak disentuh', async () => {
        createImageBitmap.mockResolvedValue({ width: 1200, height: 900, close: () => {} });
        const kecil = berkas('kecil.jpg', 'image/jpeg', 300 * 1024);
        expect(await kompresGambar(kecil)).toBe(kecil);
    });

    it('hasil yang justru lebih besar, atau gambar yang tak terbaca, mengembalikan berkas asli', async () => {
        pasangKanvas(6 * 1024 * 1024);
        const foto = berkas('foto.jpg', 'image/jpeg', 5 * 1024 * 1024);
        expect(await kompresGambar(foto)).toBe(foto);

        createImageBitmap.mockRejectedValue(new Error('tidak didukung'));
        vi.stubGlobal('Image', class { set src(_) { setTimeout(() => this.onerror?.(), 0); } });
        URL.createObjectURL = URL.createObjectURL || (() => 'blob:x');
        URL.revokeObjectURL = URL.revokeObjectURL || (() => {});
        expect(await kompresGambar(foto)).toBe(foto);
    });
});
