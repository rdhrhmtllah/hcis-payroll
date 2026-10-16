/**
 * Format nomor versi SOP: dua digit, mulai dari nol.
 *
 * Nomornya disimpan sebagai INT di basis data, dan itu disengaja. Menyimpannya
 * sebagai teks agar bisa "00" akan merusak urutan riwayat: secara leksikal
 * "10" lebih kecil daripada "9", sehingga MAX(), ORDER BY, dan unique index
 * (Id_SOP_Dokumen, Versi_Ke) semuanya ikut salah. Dua digit adalah urusan
 * tampilan, bukan urusan penyimpanan — dan karena itu tinggal di satu berkas
 * ini, bukan disalin ke tiap layar yang menampilkannya.
 *
 * NOL ITU SAH. Sejak penomoran dimulai dari nol, cek falsy (`v ? … : '—'`)
 * mengubah versi pertama menjadi "tanpa versi". Karena itu yang diperiksa
 * null/undefined, bukan kebenaran nilainya.
 */
function padat(v, lebar) {
    if (v === null || v === undefined || v === '') return '—';

    const n = Number(v);
    if (!Number.isFinite(n)) return String(v);

    // Lebih lebar dibiarkan apa adanya — memotongnya akan membuat v100 tampil
    // sebagai v00, yaitu nomor lain yang juga benar-benar ada.
    return String(Math.trunc(n)).padStart(lebar, '0');
}

/** Lebar segmen No. Dokumen; kembaran SopNomorDokumen::LEBAR_NOMOR. */
export const LEBAR_NOMOR_DOKUMEN = 3;

export function nomorVersi(v) {
    return padat(v, 2);
}

/**
 * Nomor urut dokumen pada masternya — BUKAN nomor versi.
 *
 * TIGA digit mulai dari 001, sedangkan revisi dua digit mulai dari 00 — sesuai
 * register dokumen terkendali perusahaan (keputusan pemilik 2026-09-17). Lebarnya
 * wajib sama dengan SopNomorDokumen::LEBAR_NOMOR di server, yang merakit nomor
 * resmi; di sini hanya untuk angka yang ditampilkan sendirian.
 *
 * Bedanya ada pada maknanya. Nomor versi bergerak sepanjang siklus revisi di
 * N_SOP_Item_Versi; nomor dokumen ditetapkan sekali di master dan tidak ikut
 * naik saat versi baru terbit.
 */
export function nomorDokumen(v) {
    return padat(v, LEBAR_NOMOR_DOKUMEN);
}

/** Bentuk siap tampil, mis. "v03". */
export function labelVersi(v) {
    const n = nomorVersi(v);

    return n === '—' ? n : `v${n}`;
}

/**
 * Nomor dokumen yang layak ditampilkan, dipilih dari yang dikirim server.
 *
 * TIDAK MERAKIT APA PUN. Rumus tujuh segmennya tinggal di SopNomorDokumen di
 * sisi server dan sengaja tak disalin ke sini; yang ada di berkas ini hanyalah
 * URUTAN PILIH di antara tiga nilai yang sudah jadi. Urutan itu pun tinggal di
 * satu tempat karena ia dipakai dua layar sekaligus — kepala wizard dan kartu
 * ringkasan — dan aturan yang cuma dipasang di satu dari dua pintu setara
 * adalah cacat yang sudah berulang di modul ini.
 *
 * KENAPA Nomor_SOP_Resmi PALING BELAKANG. Ia salinan tersimpan dari rakitan
 * yang sama; begitu identitas dokumen berubah sesudah tersimpan, salinan itulah
 * yang basi. Rakitan segar dari server yang menang, dan yang tersimpan hanya
 * jaring pengaman bagi dokumen lama yang segmennya tak lagi bisa ditelusuri.
 *
 * KENAPA BUKAN No_Dokumen. No_Dokumen adalah SATU segmen dari tujuh — dokumen
 * bernomor SK/HOL/IA/EHG/02/01/?? akan tampil sebagai "1", angka yang tak
 * pernah ada di dokumen mana pun. Lubang yang terlihat lebih menolong daripada
 * penggal yang menyesatkan.
 */
export function nomorDokumenTampil(dokumen) {
    const d = dokumen || {};

    return d.Nomor_Lengkap || d.Nomor_Pratinjau || d.Nomor_SOP_Resmi || null;
}
