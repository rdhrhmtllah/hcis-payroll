import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

/**
 * Balasan 204 dari /sanctum/csrf-cookie BUKAN bukti cookienya terpasang.
 *
 * Endpoint itu menjawab sukses selama ia bisa mengirim header Set-Cookie;
 * apakah browser benar-benar menyimpannya adalah urusan lain. Sebelum penjaga
 * ini, kegagalan tersebut menyamar jadi keberhasilan: pemanggil mengulang
 * permintaannya dengan tetap NOL token, kena 419 untuk kedua kalinya, lalu
 * interceptor menyimpulkan "sesi mati" dan melempar orangnya ke /login — yang
 * karena ia masih login memantul ke beranda.
 *
 * Gejala yang dilaporkan pemakainya persis itu: menekan pratinjau di Export
 * Studio lalu tiba-tiba kembali ke homepage, tanpa satu pun petunjuk tentang
 * cookie.
 */

const get = vi.fn();

vi.mock('axios', () => ({
    default: {
        get: (...args) => get(...args),
        defaults: { headers: { common: {} } },
    },
}));

let refreshCsrfToken;
let CsrfCookieTidakTerbit;

beforeEach(async () => {
    vi.resetModules();
    get.mockReset();
    ({ refreshCsrfToken, CsrfCookieTidakTerbit } = await import('./csrf.js'));
});

afterEach(() => {
    // document.cookie hanya bisa dikosongkan dengan mengedaluwarsakannya.
    document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/';
});

describe('refreshCsrfToken', () => {
    it('menolak ketika endpoint sukses tetapi cookie tidak muncul', async () => {
        get.mockResolvedValue({ status: 204 });

        await expect(refreshCsrfToken()).rejects.toBeInstanceOf(CsrfCookieTidakTerbit);
    });

    it('berhasil ketika cookie benar-benar terpasang', async () => {
        get.mockImplementation(async () => {
            document.cookie = 'XSRF-TOKEN=token-segar; path=/';

            return { status: 204 };
        });

        await expect(refreshCsrfToken()).resolves.toBeUndefined();
    });

    /**
     * Sisi PENERIMAAN dari single-flight. Tanpa ini, implementasi yang selalu
     * membuat permintaan baru tetap akan menghijaukan kedua uji di atas sambil
     * membanjiri server ketika banyak permintaan gagal 419 bersamaan.
     */
    it('menggabungkan panggilan beruntun menjadi satu permintaan', async () => {
        get.mockImplementation(async () => {
            document.cookie = 'XSRF-TOKEN=token-segar; path=/';

            return { status: 204 };
        });

        await Promise.all([refreshCsrfToken(), refreshCsrfToken(), refreshCsrfToken()]);

        expect(get).toHaveBeenCalledTimes(1);
    });

    /**
     * Promise single-flight WAJIB dilepas setelah selesai; bila tidak, satu
     * kegagalan membekukan seluruh refresh berikutnya sepanjang umur halaman.
     */
    it('melepas promise sehingga percobaan berikutnya benar-benar mencoba lagi', async () => {
        get.mockResolvedValueOnce({ status: 204 });
        await expect(refreshCsrfToken()).rejects.toBeInstanceOf(CsrfCookieTidakTerbit);

        get.mockImplementationOnce(async () => {
            document.cookie = 'XSRF-TOKEN=token-segar; path=/';

            return { status: 204 };
        });

        await expect(refreshCsrfToken()).resolves.toBeUndefined();
        expect(get).toHaveBeenCalledTimes(2);
    });
});
