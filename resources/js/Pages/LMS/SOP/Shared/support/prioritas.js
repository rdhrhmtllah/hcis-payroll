/**
 * Warna bawaan tiga tingkat lama, dalam hex.
 *
 * Nilainya sepadan dengan token kelas yang dulu dipakai (bad/warn/idle), supaya
 * tampilannya tak berubah di environment yang masternya belum terisi.
 */
const BAWAAN = {
    KRITIS: { label: 'Sangat Penting', warna: '#EF4444' },
    TINGGI: { label: 'Tinggi', warna: '#F59E0B' },
    NORMAL: { label: 'Normal', warna: '#94A3B8' },
};

/**
 * Daftar tingkat prioritas yang HIDUP, diisi server saat halaman dimuat.
 *
 * KENAPA TAK CUKUP KONSTANTA. Sejak tingkatnya jadi master data (DDL 35), admin
 * bisa menambah tingkat yang belum ada saat berkas ini ditulis — dan pencarian
 * ke konstanta akan menampilkannya sebagai kode mentah tanpa lencana, mis.
 * "SANGAT_TINGGI" berwarna abu-abu di sebelah "Sangat Penting" yang merah.
 *
 * Bawaan di atas tetap jadi NILAI AWAL, bukan dibuang: halaman yang belum sempat
 * mengisi daftar (atau environment yang masternya belum terisi) tetap
 * menampilkan tiga tingkat lama dengan benar.
 */
let daftar = { ...BAWAAN };

/**
 * Isi dari muatan server: [{ kode, nama, warna, urutan, kritis }].
 *
 * Dipanggil sekali per halaman. Daftar kosong DIABAIKAN, bukan menimpa dengan
 * kosong — halaman yang kebetulan tak mengirim prop ini tak boleh membuat
 * seluruh lencana di layar kehilangan namanya.
 */
export function setPrioritas(list) {
    if (!Array.isArray(list) || !list.length) return;

    const baru = {};
    for (const r of list) {
        if (!r || !r.kode) continue;
        baru[r.kode] = { label: r.nama || r.kode, warna: r.warna || '' };
    }
    if (Object.keys(baru).length) daftar = baru;
}

/**
 * Label + warna satu kode. Kode tak dikenal tampil apa adanya, bukan kosong.
 *
 * `warna` kosong berarti "tak ada warna dari master" — pemakainya jatuh ke
 * tampilan netral, bukan ke warna acak.
 */
export function metaPrioritas(kode) {
    if (!kode) return { label: '', warna: '' };

    return daftar[kode] || { label: String(kode), warna: '' };
}

/** Semua tingkat, urut sesuai kiriman server (paling mendesak lebih dulu). */
export function daftarPrioritas() {
    return Object.entries(daftar).map(([kode, v]) => ({ kode, ...v }));
}
