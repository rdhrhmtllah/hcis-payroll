/**
 * Menyalin teks ke papan klip, dengan jalan mundur yang benar-benar bekerja.
 *
 * `navigator.clipboard` TIDAK ADA pada konteks tak aman — http biasa, termasuk
 * vhost pengembangan seperti `http://hcis-w-3.test`. Pola yang tersebar di
 * seluruh proyek menulis:
 *
 *     navigator.clipboard?.writeText(teks).then(...)
 *
 * dan di konteks itu rantai opsionalnya memulangkan `undefined`, lalu `.then()`
 * dipanggil pada `undefined` — TypeError yang jatuh ke konsol, tanpa satu pun
 * pesan ke pemakainya. Tombolnya tampak hidup, ditekan, dan tidak terjadi
 * apa-apa. Itulah kenapa tombol salin "tidak bekerja" di sebagian lingkungan
 * dan baik-baik saja di lingkungan lain: bedanya protokol, bukan kodenya.
 *
 * Fungsi ini karena itu TIDAK pernah melempar. Ia memulangkan boolean, dan
 * pemanggilnya wajib menanganinya — kegagalan menyalin yang diam sama saja
 * dengan bug yang barusan diperbaiki.
 *
 * @param {string} teks
 * @returns {Promise<boolean>} true bila benar-benar tersalin
 */
export async function salinTeks(teks) {
    const isi = String(teks ?? '').trim();

    if (isi === '') {
        return false;
    }

    // Jalur modern. Selain konteks aman, ia juga menuntut dokumen yang sedang
    // fokus — penolakannya berupa Promise yang reject, bukan pengecualian.
    if (typeof navigator !== 'undefined' && navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(isi);

            return true;
        } catch {
            // Jatuh ke jalan mundur di bawah, bukan menyerah.
        }
    }

    return salinLewatTextarea(isi);
}

/**
 * Jalan mundur untuk konteks tak aman: textarea tersembunyi + execCommand.
 *
 * `document.execCommand` memang usang, tetapi ia SATU-SATUNYA cara menyalin di
 * halaman http — dan lingkungan pengembangan proyek ini berjalan di sana.
 * Menghapusnya berarti tombol salin mati sepanjang pengembangan lalu hidup
 * kembali di production, yaitu jenis perbedaan yang paling lama tidak ketahuan.
 */
function salinLewatTextarea(isi) {
    if (typeof document === 'undefined') {
        return false;
    }

    const kotak = document.createElement('textarea');
    kotak.value = isi;

    // Di luar layar, bukan display:none — elemen yang tidak dirender tidak bisa
    // dipilih, dan execCommand hanya menyalin yang benar-benar terpilih.
    kotak.setAttribute('readonly', '');
    kotak.style.position = 'fixed';
    kotak.style.top = '-9999px';
    kotak.style.opacity = '0';

    // Dialog HCIS di-teleport ke <body> dan mengunci fokus di dalamnya; menaruh
    // textarea di body membuatnya berada pada pohon yang sama, sehingga
    // pemilihannya tidak dibatalkan jebakan fokus modal.
    document.body.appendChild(kotak);

    const pilihanSebelumnya = document.activeElement;

    try {
        kotak.select();
        kotak.setSelectionRange(0, isi.length);

        return document.execCommand('copy');
    } catch {
        return false;
    } finally {
        document.body.removeChild(kotak);

        // Fokus dikembalikan supaya jebakan fokus modal tidak kehilangan
        // jangkarnya dan tombol Esc tetap bekerja sesudah menyalin.
        if (pilihanSebelumnya && typeof pilihanSebelumnya.focus === 'function') {
            pilihanSebelumnya.focus();
        }
    }
}
