/**
 * Bentuk token yang boleh lewat: base64url dari `VisitorDetailToken::buat()`,
 * ditambah Id polos demi notifikasi lama. Nilainya ditempel ke path permintaan
 * (`/transaksipermit/<nilai>`), jadi apa pun di luar abjad ini — '/', '.', '=',
 * spasi — memecah ruas path atau menyeret permintaan ke tempat lain.
 */
const BENTUK_RUJUKAN = /^[A-Za-z0-9_-]{1,2000}$/;

/**
 * Token Crypt selalu ratusan karakter (base64 dari JSON berisi iv, value, mac).
 * Ambang ini jauh di bawahnya, dan menyingkirkan sisa sampah yang kebetulan
 * memakai abjad yang sama — '-5', 'abc' — sebelum jadi permintaan yang pasti
 * gagal. Angka polos dinilai terpisah, jadi tidak terkena ambang ini.
 */
const PANJANG_TOKEN_MINIMUM = 32;

/**
 * Membaca rujukan transaksi dari tautan notifikasi Visitor (`?detail=<token>`).
 *
 * Sejak tautan disamarkan, nilainya token — bukan Id berurutan — jadi yang
 * dikembalikan string, bukan angka. Nilai sampah harus berhenti di sini; yang
 * menjaga kepemilikan tetap penyaringan pemohon di endpoint `show()`.
 *
 * @param {string|undefined} search - isi `window.location.search`
 * @returns {string|null} token atau Id polos, atau null bila tak ada yang dibuka
 */
export function bacaRujukanDetail(search) {
    const mentah = ambilParam(search, 'detail');

    if (mentah === null || !BENTUK_RUJUKAN.test(mentah)) {
        return null;
    }

    // Id polos dari notifikasi lama dan klik tabel tetap harus masuk akal.
    if (/^\d+$/.test(mentah)) {
        return Number(mentah) > 0 ? mentah : null;
    }

    return mentah.length >= PANJANG_TOKEN_MINIMUM ? mentah : null;
}

/**
 * Jenis transaksi yang dituju tautan (`&jenis=permit|tamu`).
 *
 * Dipakai halaman verifikator yang melayani dua domain sekaligus, untuk memilih
 * tab dan panel mana yang dibuka.
 *
 * @param {string|undefined} search - isi `window.location.search`
 * @returns {'permit'|'tamu'|null}
 */
export function bacaJenisDetail(search) {
    const mentah = ambilParam(search, 'jenis');

    if (mentah === null) {
        return null;
    }

    const jenis = mentah.toLowerCase();

    return jenis === 'permit' || jenis === 'tamu' ? jenis : null;
}

function ambilParam(search, nama) {
    if (typeof search !== 'string' || search === '') {
        return null;
    }

    return new URLSearchParams(search.startsWith('?') ? search.slice(1) : search).get(nama);
}
