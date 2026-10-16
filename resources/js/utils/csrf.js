/**
 * Utilitas CSRF terpusat.
 *
 * Inti masalah 419 "CSRF token mismatch.": token dari <meta name="csrf-token">
 * menjadi BASI setelah session di-regenerate server (mis. setelah login). Yang
 * SELALU segar adalah cookie XSRF-TOKEN (di-set ulang server / endpoint
 * /sanctum/csrf-cookie). axios sudah otomatis membaca cookie itu per-request;
 * file ini menyediakan logika yang sama untuk `fetch()` native + satu titik
 * refresh token yang dipakai bersama oleh interceptor axios dan wrapper fetch.
 */

import axios from 'axios';

/** Ambil nilai cookie XSRF-TOKEN (sudah di-URL-decode). */
export function getXsrfTokenFromCookie() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    if (!match) return '';
    try {
        return decodeURIComponent(match[1]);
    } catch (e) {
        return match[1];
    }
}

// Pastikan hanya ada SATU permintaan refresh CSRF yang berjalan meski banyak
// request gagal 419 bersamaan; semua menunggu promise yang sama.
let csrfRefreshPromise = null;

/**
 * Endpoint CSRF menjawab sukses tetapi cookienya tidak pernah terpasang.
 *
 * Dibedakan dari galat jaringan biasa karena PEMULIHANNYA berbeda: melempar
 * orangnya ke /login tidak menolong sama sekali bila ia memang masih login.
 */
export class CsrfCookieTidakTerbit extends Error {
    constructor() {
        super('Endpoint CSRF menjawab sukses tetapi cookie XSRF-TOKEN tidak muncul.');
        this.name = 'CsrfCookieTidakTerbit';
    }
}

/**
 * Segarkan cookie XSRF-TOKEN via endpoint Sanctum yang ringan (tanpa side-effect).
 * Single-flight: panggilan beruntun berbagi satu promise. Pakai skipAuthRefresh
 * agar request ini sendiri tidak ikut tertangkap interceptor 419 (cegah loop).
 */
export function refreshCsrfToken() {
    if (!csrfRefreshPromise) {
        csrfRefreshPromise = axios
            .get('/sanctum/csrf-cookie', { withCredentials: true, skipAuthRefresh: true })
            .then(() => {
                // Balasan 204 BUKAN bukti cookienya terpasang.
                //
                // Endpoint ini menjawab sukses selama ia bisa mengirim header
                // Set-Cookie; apakah browser benar-benar menyimpannya adalah
                // urusan lain — cookie bisa ditolak karena atribut Secure pada
                // origin http, karena kuota cookie domain penuh, atau karena
                // ekstensi/pengaturan privasi membuangnya.
                //
                // Sebelum pemeriksaan ini, kegagalan itu menyamar jadi
                // keberhasilan: pemanggil mengulang permintaannya dengan tetap
                // NOL token, kena 419 untuk kedua kalinya, lalu interceptor
                // menyimpulkan "sesi mati" dan melempar orangnya ke /login —
                // yang karena ia masih login memantul ke beranda. Yang terlihat
                // pemakainya hanyalah "menekan pratinjau lalu tiba-tiba kembali
                // ke homepage", tanpa satu pun petunjuk tentang cookie.
                if (!getXsrfTokenFromCookie()) {
                    throw new CsrfCookieTidakTerbit();
                }

                // Setelah ini cookie XSRF-TOKEN sudah segar. Hapus default
                // X-CSRF-TOKEN (dari <meta>) agar tidak menimpa header XSRF
                // otomatis dengan token form lama.
                delete axios.defaults.headers.common['X-CSRF-TOKEN'];
            })
            .finally(() => {
                csrfRefreshPromise = null;
            });
    }
    return csrfRefreshPromise;
}

/**
 * Header CSRF untuk fetch() manual (bila ada yang lebih suka eksplisit).
 * Umumnya tidak perlu lagi karena installFetchCsrfGuard() menyuntik otomatis.
 */
export function csrfHeaders(extra = {}) {
    const token = getXsrfTokenFromCookie();
    if (token) {
        return { 'X-XSRF-TOKEN': token, ...extra };
    }
    const meta = document.querySelector('meta[name="csrf-token"]')?.content || '';
    return { 'X-CSRF-TOKEN': meta, ...extra };
}

/** Apakah URL menuju origin yang sama (cookie sesi berlaku). */
function isSameOrigin(url) {
    try {
        // url bisa relatif ("/api/...") atau absolut.
        const u = new URL(url, window.location.origin);
        return u.origin === window.location.origin;
    } catch (e) {
        // Relatif murni tanpa skema -> dianggap same-origin.
        return true;
    }
}

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

/**
 * Pasang sekali di bootstrap. Membungkus window.fetch sehingga SETIAP fetch()
 * same-origin yang mutating (POST/PUT/PATCH/DELETE):
 *   1) otomatis dikirim dengan credentials (cookie sesi),
 *   2) menyertakan header X-XSRF-TOKEN dari cookie segar (kecuali caller sudah
 *      menyetel token CSRF sendiri secara eksplisit),
 *   3) bila tetap kena 419: refresh cookie -> retry SEKALI secara silent.
 *
 * Dampak: semua kode fetch() lama anti-419 tanpa perlu diubah per-file, sama
 * seperti interceptor axios.
 */
export function installFetchCsrfGuard() {
    if (typeof window === 'undefined' || !window.fetch || window.__csrfFetchGuardInstalled) {
        return;
    }
    window.__csrfFetchGuardInstalled = true;

    const originalFetch = window.fetch.bind(window);

    const hasHeader = (headers, name) => {
        if (!headers) return false;
        const lower = name.toLowerCase();
        if (headers instanceof Headers) return headers.has(name);
        return Object.keys(headers).some((k) => k.toLowerCase() === lower);
    };

    const setHeader = (init, name, value) => {
        if (init.headers instanceof Headers) {
            init.headers.set(name, value);
        } else {
            init.headers = { ...(init.headers || {}), [name]: value };
        }
    };

    window.fetch = async (input, init = {}) => {
        const url = typeof input === 'string' ? input : input?.url || '';
        const method = (init.method || (typeof input !== 'string' && input?.method) || 'GET').toUpperCase();

        const guard = MUTATING_METHODS.has(method) && isSameOrigin(url);

        if (!guard) {
            return originalFetch(input, init);
        }

        // Salin init agar tidak memutasi objek milik caller.
        const finalInit = { ...init };
        if (finalInit.credentials === undefined) {
            finalInit.credentials = 'same-origin';
        }

        // Jangan timpa kalau caller sudah set token CSRF sendiri.
        const callerSetCsrf =
            hasHeader(finalInit.headers, 'X-XSRF-TOKEN') || hasHeader(finalInit.headers, 'X-CSRF-TOKEN');

        if (!callerSetCsrf) {
            const token = getXsrfTokenFromCookie();
            if (token) setHeader(finalInit, 'X-XSRF-TOKEN', token);
        }

        let response = await originalFetch(input, finalInit);

        if (response.status === 419 && !finalInit.__csrfRetried) {
            try {
                await refreshCsrfToken();
            } catch (e) {
                return response; // refresh gagal -> kembalikan 419 asli
            }
            finalInit.__csrfRetried = true;
            // Pasang token segar untuk percobaan kedua.
            const fresh = getXsrfTokenFromCookie();
            if (fresh) setHeader(finalInit, 'X-XSRF-TOKEN', fresh);
            response = await originalFetch(input, finalInit);
        }

        return response;
    };
}
