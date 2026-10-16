import DOMPurify from 'dompurify';

/**
 * HTML catatan dan esai yang siap digambar `v-html`.
 *
 * Lapis KEDUA: esai revisi penilai sudah disaring di server (PaTeksKaya), tetapi catatan hasil
 * juga memuat catatan admin dan teks lama yang tidak pernah lewat penyaring itu. Profil HTML
 * bawaan DOMPurify dipakai supaya warna dan judul catatan admin tetap tampil, sementara skrip,
 * `on*`, dan `javascript:` dibuang.
 *
 * @param {string|null|undefined} html
 * @returns {string}
 */
export function htmlAman(html) {
    if (!html) return '';
    return DOMPurify.sanitize(String(html), { USE_PROFILES: { html: true } });
}

/**
 * Teks yang benar-benar terbaca dari sebuah HTML — untuk memeriksa "kosong" sama seperti
 * server: `<p><br></p>` dari editor yang dikosongkan dihitung kosong.
 *
 * @param {string|null|undefined} html
 * @returns {string}
 */
export function teksDariHtml(html) {
    if (!html) return '';
    const wadah = document.createElement('div');
    wadah.innerHTML = DOMPurify.sanitize(String(html));
    return (wadah.textContent || '').replace(/ /g, ' ').trim();
}
