/**
 * Bentuk opsi paket ujian HC Learn, untuk SEMUA layar yang menawarkannya.
 *
 * KENAPA SATU TEMPAT. Balasan HC Learn dipakai dua pintu — palet item di
 * Builder (ItemFields) dan langkah Pemberkasan di Wizard Versi — dan sampai
 * 10 Sep 2026 keduanya memetakannya sendiri-sendiri. Builder memetakannya
 * dengan benar; Wizard menyimpan baris mentah apa adanya, lalu komponennya
 * membaca `u.value`/`u.label` yang tak pernah ada. Hasilnya dropdown berisi
 * opsi-opsi KOSONG yang tak bisa diklik — bukan galat, bukan daftar kosong,
 * melainkan sesuatu yang tampak seperti data rusak. Bahkan ada nama kunci
 * KETIGA (`u.nama`) di jalur simpan Wizard, sehingga Nama_Ujian selalu
 * tersimpan null.
 *
 * Satu bentuk data yang dipetakan di dua tempat pasti menyimpang, dan
 * menyimpangnya diam-diam. Karena itu pemetaannya tinggal di sini, dan kedua
 * pintu memanggilnya.
 *
 * Bentuk baris dari HC Learn (GET api/v1/penjadwalan/training/options,
 * diverifikasi 10 Sep 2026):
 *   { Id_Master_Ujian, Nama_Ujian, Kode_Ujian, Jumlah_Soal, Nama_Paket, … }
 */

/**
 * Petakan balasan mentah menjadi opsi `{ value, label, kode }`.
 *
 * `value` adalah token HC Learn — itulah yang disimpan ke
 * N_SOP_Item_Versi.Id_Master_Ujian, dan satu-satunya kunci yang dikenali
 * kedua sisi integrasi.
 *
 * Baris tanpa token DIBUANG, bukan diloloskan dengan value kosong: opsi tak
 * berkunci akan tampil sebagai baris kosong yang bisa dipilih lalu gagal
 * belakangan, jauh dari tempat sebabnya.
 *
 * @param {unknown} rows balasan `result` apa adanya
 * @returns {{value: string, label: string, kode: string|null}[]}
 */
export function petakanOpsiUjian(rows) {
    if (!Array.isArray(rows)) return [];

    return rows
        .filter((u) => u && (u.Id_Master_Ujian ?? '') !== '')
        .map((u) => ({
            value: u.Id_Master_Ujian,
            /* Nama kosong jatuh ke tokennya. Jelek dibaca, tapi terlihat — dan
               yang terlihat bisa dilaporkan; opsi tanpa tulisan tidak. */
            label: u.Nama_Ujian || `Ujian ${u.Id_Master_Ujian}`,
            kode: u.Kode_Ujian || null,
        }));
}

/** Nama yang tersimpan bersama token, atau null bila tokennya tak dikenali. */
export function namaOpsiUjian(opsi, value) {
    if (!value) return null;

    return (Array.isArray(opsi) ? opsi : []).find((u) => u.value === value)?.label || null;
}
