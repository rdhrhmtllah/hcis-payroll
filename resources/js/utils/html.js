/**
 * Satu rumah untuk meng-escape teks yang akan masuk ke HTML.
 *
 * Dipakai di tempat-tempat yang MEMANG membangun HTML sebagai string lalu
 * menyerahkannya ke DOM tanpa lewat interpolasi Vue: `v-html`, dan prop
 * `raw-content` milik el-tooltip. Di kedua tempat itu Vue tidak meng-escape apa
 * pun untuk kita.
 *
 * Kalau sebuah nilai bisa dirender lewat `{{ }}`, pakai itu — Vue sudah
 * meng-escape sendiri, dan tidak perlu memanggil apa pun dari berkas ini.
 */

/** Lima karakter yang berarti bagi parser HTML. Ampersand didahulukan. */
export function escapeHtml(teks) {
    return String(teks ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}
