/**
 * Kapan petunjuk panduan di tombol Bantuan perlu muncul lagi.
 *
 * Keputusan user 29 Sep 2026 — "sampai dibuka, lalu 7 hari":
 *  1. Belum pernah membuka panduan halaman itu → muncul tiap SESI LOGIN
 *     ("Mengerti" hanya menunda sampai login berikutnya).
 *  2. Sudah pernah membuka → diingatkan lagi HARI_ULANG hari setelah terakhir
 *     ditanggapi.
 *  3. Panduan diperbarui / ditambah (tanda tangan berubah) → langsung muncul.
 *  4. "Jangan ingatkan lagi" → diam sampai panduannya diperbarui.
 * Diabaikan (hilang sendiri) tidak dihitung sebagai ditanggapi.
 *
 * Disimpan di localStorage per akun per halaman — kenyamanan per perangkat.
 * Setiap akses dibungkus try/catch: penyimpanan bisa diblokir.
 */

export const KUNCI_PENYIMPANAN = 'hcis.panduan.petunjuk.v2';
export const HARI_ULANG = 7;
const SEHARI_MS = 24 * 60 * 60 * 1000;

function baca() {
    try {
        const isi = JSON.parse(localStorage.getItem(KUNCI_PENYIMPANAN) || '{}');
        return isi && typeof isi === 'object' ? isi : {};
    } catch {
        return {};
    }
}

function tulis(semua) {
    try {
        localStorage.setItem(KUNCI_PENYIMPANAN, JSON.stringify(semua));
    } catch {
        // Diblokir: pengingat sekadar muncul lagi nanti.
    }
}

/** Sidik pendek (bukan token-nya) — cukup untuk membedakan sesi login. */
function sidik(teks) {
    let h = 5381;
    for (let i = 0; i < teks.length; i += 1) h = ((h << 5) + h + teks.charCodeAt(i)) | 0;
    return (h >>> 0).toString(36);
}

/**
 * Penanda sesi login: token CSRF diganti Laravel setiap login
 * (session()->regenerate()). Cadangan: penanda per tab.
 */
export function sesiLogin() {
    const token = typeof document !== 'undefined'
        ? document.head?.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        : '';
    if (token) return sidik(token);

    try {
        let id = sessionStorage.getItem('hcis.panduan.sesi');
        if (!id) {
            id = Math.random().toString(36).slice(2);
            sessionStorage.setItem('hcis.panduan.sesi', id);
        }
        return id;
    } catch {
        return 'tanpa-sesi';
    }
}

/** Tanda tangan isi panduan halaman: berubah bila panduan ditambah atau diperbarui. */
export function tandaTangan(panduan) {
    return (panduan || []).map((p) => `${p.id}:${p.diperbarui || ''}`).sort().join('|');
}

function catatan(pengguna, halaman) {
    return baca()[pengguna]?.[halaman] || {};
}

/**
 * @returns {{ muncul: boolean, pengingatUlang: boolean }} pengingatUlang = pernah dibuka sebelumnya
 */
export function perluDiingatkan({ pengguna, halaman, tanda, sesi = sesiLogin(), sekarang = Date.now() }) {
    if (!pengguna || !halaman || !tanda) return { muncul: false, pengingatUlang: false };

    const c = catatan(pengguna, halaman);
    if (c.tanda !== tanda) return { muncul: true, pengingatUlang: false }; // baru / diperbarui
    if (c.jangan) return { muncul: false, pengingatUlang: false };

    if (!c.dibuka) return { muncul: c.sesi !== sesi, pengingatUlang: false };

    const lewat = sekarang - (c.terakhir || 0) >= HARI_ULANG * SEHARI_MS;
    return { muncul: lewat, pengingatUlang: lewat };
}

/**
 * Catat tanggapan pengguna.
 * @param {'mengerti'|'dibuka'|'jangan'} jenis
 */
export function catatTanggapan({ pengguna, halaman, tanda, jenis, sesi = sesiLogin(), sekarang = Date.now() }) {
    if (!pengguna || !halaman || !tanda) return;

    const semua = baca();
    const lama = semua[pengguna]?.[halaman] || {};
    // Tanda tangan berganti = panduan berubah: tanggapan lama tidak berlaku lagi.
    const dasar = lama.tanda === tanda ? lama : {};

    const baru = { ...dasar, tanda, sesi, terakhir: sekarang };
    if (jenis === 'dibuka') baru.dibuka = sekarang;
    if (jenis === 'jangan') baru.jangan = true;

    semua[pengguna] = { ...(semua[pengguna] || {}), [halaman]: baru };
    tulis(semua);
}
