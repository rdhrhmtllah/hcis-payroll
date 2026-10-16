/**
 * Satu-satunya tempat modul Performance Appraisal bicara HTTP.
 *
 * KENAPA HALAMAN TIDAK BOLEH MEMANGGIL axios LANGSUNG
 *
 * Kalau tiap halaman memanggil axios sendiri, satu perubahan bentuk respons berarti
 * mencari dan menyunting belasan tempat — dan yang terlewat baru ketahuan saat dibuka
 * pengguna. Dengan satu pintu, perubahan bentuk hanya menyentuh berkas ini.
 *
 * SELURUHNYA DARI BACKEND — MODE BONEKA SUDAH DICABUT (3 September 2026)
 *
 * Dulu berkas ini punya mode `dummy` yang mengembalikan fixture tanpa menyentuh jaringan,
 * supaya frontend bisa mengerjakan seluruh layar sebelum satu pun siklus ada di database.
 * Mode itu sudah selesai tugasnya, dan mempertahankannya justru berbahaya: layar yang
 * hijau di mode boneka tidak membuktikan apa pun tentang integrasinya, sehingga
 * ketidakcocokan baru ketahuan pada hari peralihan — biasanya di depan orang.
 *
 * Kini setiap fungsi memanggil backend, selalu. Tidak ada saklar, tidak ada jalan kedua.
 *
 * FIXTURE-NYA TETAP ADA, DAN PERANNYA BERUBAH
 *
 * Berkas di `./fixtures` TIDAK dihapus. Ia berhenti menjadi "yang ditampilkan layar" dan
 * tetap menjadi **kontrak bentuk respons**: `KontrakResponsTest` dan `KontrakLaporanTest`
 * memanggil endpoint sungguhan lalu membandingkan susunan kuncinya dengan fixture itu.
 * Kalau backend menggeser satu nama field, uji itu merah hari itu juga.
 *
 * Menghapus fixture-nya berarti mematikan penjaga yang justru menjaga integrasi ini tetap
 * benar. Jangan.
 */

import axios from 'axios';


const PREFIKS = '/api/v1/performance-appraisal';

/**
 * Slug master yang dikenal server. Nama di sini = potongan alamat.
 *
 * Dulu diturunkan dari peta fixture; peta itu ikut tercabut bersama mode boneka.
 *
 * PERHATIAN: daftar ini TIDAK menyaring menurut hak. Layar master seharusnya memakai prop
 * `daftar_master` yang dikirim server — ia sudah disaring menurut hak orang yang membuka,
 * sehingga master yang tak boleh ia sentuh tidak ditawarkan sama sekali. Daftar di sini
 * hanya untuk hal yang tidak bergantung pada siapa pembukanya.
 */
export const DAFTAR_MASTER = [
    'jenis-kompetensi',
    'kompetensi',
    'kategori-penilai',
    'aturan-penilai',
    'bobot-pilar',
    'pita-nilai',
    'kuadran-hav',
    'paket-soal',
    'butir-soal',
];


export class PaApiError extends Error {
    constructor(message, status, errorCode = '', requestId = '') {
        super(message);
        this.name = 'PaApiError';
        this.status = status;
        /* Nama snake_case disengaja: usePaSaya dan komposabel lain sudah membaca
           `error.error_code` / `error.request_id`. Sebelum ini keduanya TIDAK PERNAH diisi,
           sehingga kode galat PA tidak pernah sampai ke layar mana pun. */
        this.error_code = errorCode;
        this.request_id = requestId;
    }
}

/**
 * Membuka amplop `ResponseHelper`.
 *
 * Isi ada di `result`, bukan `data`. Ini sudah pernah salah sekali; menaruh
 * pembukanya di satu tempat membuatnya tidak bisa salah dua kali.
 */
function isi(respons) {
    return respons?.result ?? null;
}

async function panggil(metode, alamat, badan = undefined) {
    try {
        const r = await axios({ method: metode, url: `${PREFIKS}${alamat}`, data: badan });

        return isi(r.data);
    } catch (e) {
        const status = e?.response?.status ?? 0;

        /*
         * Pesan 422 dari backend ditulis untuk dibaca manusia dan diteruskan apa adanya.
         * Kalimat seperti "Kategori PEER tidak mengizinkan penilai manual menurut aturan
         * levelnya" adalah satu-satunya keterangan yang menjelaskan kenapa tombolnya
         * ditolak; menggantinya dengan "terjadi kesalahan" membuang informasinya.
         */
        /* Galat validasi Laravel membawa SEMUA sebab di `errors`, sedangkan `message` hanya yang
           pertama ditambah "(and N more errors)". Semuanya diteruskan supaya admin tahu
           seluruh isian yang ditolak sekaligus. */
        const daftarGalat = Object.values(e?.response?.data?.errors || {}).flat().filter(Boolean);
        const pesan =
            (daftarGalat.length ? daftarGalat.join(' ') : '') ||
            e?.response?.data?.message ||
            (status === 403
                ? 'Anda tidak punya akses ke halaman Performance Appraisal.'
                : 'Permintaan tidak bisa diselesaikan.');

        throw new PaApiError(
            pesan,
            status,
            e?.response?.data?.error_code || '',
            e?.response?.data?.request_id || '',
        );
    }
}

/* ------------------------------------------------------------------ *
 * Lembar penilaian simulasi                                           *
 * ------------------------------------------------------------------ */

/**
 * Lembar penilaian satu penugasan bertoken simulasi.
 *
 * Tokennya ada di alamat, tetapi ia BUKAN izin: kepemilikannya diperiksa server terhadap
 * penilai yang tercatat pada penugasan. Lihat PaSimulasiPengisianService::tugasMilikSaya().
 */
export function lembarPenilaian(token) {
    return panggil('get', `/nilai/${encodeURIComponent(token)}`);
}

/**
 * Mengirim jawaban. `jawaban` adalah peta `kode soal => skor angka atau teks`.
 *
 * Masuk lewat kontrak callback yang SAMA dengan yang akan dipakai HC Learn, sehingga yang
 * teruji sekarang adalah jalur yang tetap dipakai nanti.
 */
export function kirimPenilaian(token, jawaban) {
    return panggil('post', `/nilai/${encodeURIComponent(token)}`, { jawaban });
}

/* ------------------------------------------------------------------ *
 * Siklus                                                              *
 * ------------------------------------------------------------------ */

/**
 * @returns {Promise<{siklus: Array<object>}>}
 */
export function daftarSiklus() {
    return panggil('get', '/cycles');
}

/**
 * Membentuk peserta dan baris hasil. Aman dijalankan berulang.
 */
export function siapkanSiklus(cycleId) {
    return panggil('post', `/cycles/${cycleId}/siapkan`);
}

/**
 * Ditolak selama masih ada peserta TERTUNDA, atau bila tak seorang pun DIIKUTKAN.
 */
/*
 | Apa yang akan dibekukan, dan apa yang masih janggal — dibaca SEBELUM penguncian.
 |
 | Mengunci itu satu arah: tidak ada jalur kembali dari LOCKED ke PREPARED. Satu-satunya
 | pengaman untuk tindakan yang tak bisa dibatalkan adalah melihat akibatnya lebih dulu.
 */
export function ringkasanPraKunci(cycleId) {
    return panggil('get', `/cycles/${cycleId}/pra-kunci`);
}

export function kunciSiklus(cycleId) {
    return panggil('post', `/cycles/${cycleId}/kunci`);
}

/**
 * Membuka jendela pengisian: `LOCKED` atau `CLOSED` -> `OPEN`.
 *
 * Tombolnya dihidupkan dari `operasi_boleh` yang memuat **`BUKA_PENGISIAN`** — jangan
 * disimpulkan dari `status_cycle`, karena status yang membolehkannya ada DUA dan salah
 * satunya (`CLOSED`) juga membolehkan hal-hal lain.
 *
 * `dibuka_ulang: true` berarti siklus yang sudah ditutup dibuka lagi. Bedakan di layar:
 * hasil yang sudah dihitung menjadi basi dan perlu dihitung ulang. Membuka ulang memang
 * DIIZINKAN — menutup terlalu cepat adalah kekeliruan yang wajar.
 *
 * `tenggat_diperbarui` (pada `perbaruiSiklus`) adalah berapa penugasan yang tenggatnya
 * ikut bergeser karena jendela siklus diubah. Nol berarti jendelanya tidak berubah, atau
 * seluruh penugasannya sudah selesai/dibatalkan — keduanya sengaja dilewati, sebab tenggat
 * mereka sudah menjadi bagian dari catatan apakah orang itu terlambat.
 *
 * `tenggat_diturunkan` adalah BERAPA PENUGASAN yang baru saja menerima tenggatnya dari
 * jendela siklus — pada siklus penuh angkanya ribuan. Dilaporkan karena perubahan data
 * sebesar itu tidak boleh terjadi diam-diam; layar sebaiknya menyebutnya pada pesan
 * sukses, supaya admin tahu apa yang barusan tersentuh selain status siklusnya.
 *
 * Ditolak 422 bila jendela pengisian siklus (`Pengisian_Dari`/`Sampai`) masih kosong:
 * tanpa jendela, penugasan lahir tanpa tenggat dan penilai yang tidak mengisi tidak akan
 * pernah tercatat terlambat.
 */
/*
 | Berapa penilai yang sudah punya pintu HC Learn, dan berapa yang belum.
 |
 | Tanpa angka ini satu-satunya cara mengetahui ada yang bolong adalah menunggu penilainya
 | mengeluh — dan pada 894 orang, yang mengeluh hanya sebagian kecil dari yang bermasalah.
 */
/*
 | Membentuk penugasan penilai untuk banyak peserta sekaligus, DICICIL.
 |
 | Relasi organisasi yang lengkap hanya menyediakan CALON; penugasannya masih harus dibuat.
 | Sebelum ini satu-satunya jalannya perintah terminal, sehingga Langkah 5 mustahil dituntaskan
 | dari layar. Dipanggil berulang sampai `belum_berpenugasan` nol.
 */
/*
 | Kemajuan pembentukan penugasan. Sengaja ringan: dipanggil berulang selama prosesnya jalan,
 | dan sumber progres yang mahal akan membebani database justru ketika ia sedang sibuk.
 */
export function progresPenugasan(cycleId) {
    return panggil('get', `/cycles/${cycleId}/penugasan-progres`);
}

/** `mulaiDariId`: kursor peserta (`terakhir_id` jawaban sebelumnya) untuk jalur cepat. */
export function bentukPenugasanMassal(cycleId, batas = 10, penuh = false, mulaiDariId = null) {
    return panggil('post', `/cycles/${cycleId}/penugasan-massal`, {
        batas,
        penuh,
        ...(mulaiDariId != null ? { mulai_dari_id: mulaiDariId } : {}),
    });
}

export function ringkasanPenjadwalan(cycleId) {
    return panggil('get', `/cycles/${cycleId}/penjadwalan`);
}

/* Idempoten: menjalankannya ulang hanya mengerjakan yang belum. Itulah cara memperbaiki
   penjadwalan yang gagal sebagian. */
export function jadwalkanPenilaian(cycleId, batas = 0) {
    return panggil('post', `/cycles/${cycleId}/penjadwalan`, batas > 0 ? { batas } : {});
}

/**
 * Menarik hasil penjadwalan yang masih ANTRE di HC Learn.
 *
 * TERPISAH dari `jadwalkanPenilaian` dengan sengaja: `store` milik mereka menjawab 202 lalu
 * menggarap antreannya belakangan, jadi tokennya belum ada saat penjadwalan selesai.
 * Menyatukan keduanya berarti tiap penarikan ikut mengirim kiriman baru — dan kiriman baru
 * atas penugasan yang sudah dititipkan menghasilkan pintu kedua.
 *
 * Boleh dipanggil berkali-kali; memanggilnya tidak mengubah apa pun di sisi mereka.
 */
export function tarikHasilPenjadwalan(cycleId) {
    return panggil('post', `/cycles/${cycleId}/penjadwalan/tarik`);
}

/**
 * Pembentukan penugasan rater sebagai TUGAS LATAR.
 *
 * Servernya yang mengerjakan potongan demi potongan; layar boleh ditutup, dan tugasnya tampil
 * di panel Tugas & Aktivitas. Siklus yang tugasnya masih berjalan mendapat tugas itu kembali
 * (`baru: false`).
 *
 * @returns {{ baru: boolean, tugas: object }}
 */
export function mulaiPembentukanLatar(cycleId) {
    return panggil('post', `/cycles/${cycleId}/penugasan-massal/latar`);
}

/** Keadaan tugas pembentukan terakhir siklus ini: `{ tugas: object|null }`. */
export function statusPembentukanLatar(cycleId) {
    return panggil('get', `/cycles/${cycleId}/penugasan-massal/latar`);
}

/** Menjalankan lagi tugas pembentukan yang gagal atau dihentikan (idempoten). */
export function ulangiPembentukanLatar(cycleId) {
    return panggil('post', `/cycles/${cycleId}/penugasan-massal/latar/ulangi`);
}

/** Menghentikan tugas pembentukan yang sedang berjalan; yang sudah terbentuk tetap berlaku. */
export function hentikanPembentukanLatar(cycleId) {
    return panggil('post', `/cycles/${cycleId}/penugasan-massal/latar/hentikan`);
}

/**
 * Kunci (bila belum) lalu jadwalkan rater ke HC Learn sebagai TUGAS LATAR.
 *
 * Servernya yang mengirim batch demi batch dan menunggu HC Learn; layar boleh ditutup, dan
 * tugasnya tampil di panel Tugas & Aktivitas. Siklus yang sudah punya tugas berjalan
 * mendapat tugas itu kembali (`baru: false`), bukan tugas kedua.
 *
 * @returns {{ baru: boolean, tugas: object, ringkasan: object }}
 */
export function mulaiPenjadwalanLatar(cycleId) {
    return panggil('post', `/cycles/${cycleId}/penjadwalan/latar`);
}

/** Keadaan tugas penjadwalan terakhir siklus ini: `{ tugas: object|null, ringkasan }`. */
export function statusPenjadwalanLatar(cycleId) {
    return panggil('get', `/cycles/${cycleId}/penjadwalan/latar`);
}

/**
 * Revisi peserta SESUDAH dikunci — keadaan satu karyawan, tindakan yang boleh beserta sebab bila
 * tidak, dan dampaknya: `{ peserta, karyawan, dampak, tindakan: { KODE: { boleh, sebab } } }`.
 */
export function pratinjauRevisiPeserta(cycleId, kodePerusahaan, kodeKaryawan) {
    const q = new URLSearchParams({ kode_perusahaan: kodePerusahaan, kode_karyawan: kodeKaryawan });
    return panggil('get', `/cycles/${cycleId}/revisi-peserta/pratinjau?${q.toString()}`);
}

/**
 * Mencari orang untuk revisi, berfilter: `{ sumber: 'peserta'|'karyawan'|'penilai', q, status,
 * id_level[], divisi[] }` → `{ baris }` (paling banyak 1000). Sumber `penilai` dengan status
 * `TIDAK_AKTIF` = penilai yang sudah tidak aktif di HRIS tetapi masih punya tugas menilai.
 */
export function cariRevisiPeserta(cycleId, saringan = {}) {
    const q = new URLSearchParams();
    for (const [k, v] of Object.entries(saringan)) {
        if (Array.isArray(v)) v.forEach((x, i) => (x !== '' && x != null) && q.append(`${k}[${i}]`, x));
        else if (v !== '' && v != null) q.append(k, v);
    }
    return panggil('get', `/cycles/${cycleId}/revisi-peserta/cari?${q.toString()}`);
}

/** Pratinjau satu tindakan untuk banyak orang: `{ baris: [{ boleh, sebab, dampak, … }], ringkasan }`. */
export function pratinjauRevisiMassal(cycleId, badan) {
    return panggil('post', `/cycles/${cycleId}/revisi-peserta/pratinjau-massal`, badan);
}

/** Memulai revisi banyak orang sebagai tugas latar: `{ tugas, diproses, dilewati }`. */
export function mulaiRevisiMassal(cycleId, badan) {
    return panggil('post', `/cycles/${cycleId}/revisi-peserta/massal`, badan);
}

/** Keadaan tugas revisi terakhir siklus ini: `{ tugas }`. */
export function statusRevisiMassal(cycleId) {
    return panggil('get', `/cycles/${cycleId}/revisi-peserta/massal`);
}

/**
 * Menjalankan satu revisi: `{ tindakan: TERAPKAN_LEVEL|IKUTKAN_KEMBALI|TAMBAH_SUSULAN|KECUALIKAN|
 * SELARASKAN_RELASI|LEPAS_PENILAI, kode_perusahaan, kode_karyawan, alasan, lepas_penilai? }` → `{ dibatalkan, dibentuk, penjadwalan, catatan }`.
 */
export function jalankanRevisiPeserta(cycleId, badan) {
    return panggil('post', `/cycles/${cycleId}/revisi-peserta`, badan);
}

/**
 * Menghapus (lagi) jadwal HC Learn milik penugasan yang dibatalkan, di antrean.
 * Jawaban: `{ jadwal_dibatalkan: { jumlah, gagal, contoh } }`.
 */
export function hapusJadwalDibatalkan(cycleId) {
    return panggil('post', `/cycles/${cycleId}/penjadwalan/hapus-dibatalkan`);
}

/** Menjalankan lagi tugas yang gagal atau dihentikan (idempoten). */
export function ulangiPenjadwalanLatar(cycleId) {
    return panggil('post', `/cycles/${cycleId}/penjadwalan/latar/ulangi`);
}

/**
 * Kiriman HC Learn yang MASUK tetapi tidak terpetakan penuh.
 *
 * Dihitung ulang dari yang tersimpan, bukan dibaca dari catatan: begitu ikatannya
 * dibetulkan dan kirimannya dipetakan ulang, jawabannya ikut benar dengan sendirinya.
 */
export function kirimanPerluDitinjau(cycleId) {
    return panggil('get', `/cycles/${cycleId}/kiriman-perlu-ditinjau`);
}

/**
 * Memetakan ULANG satu kiriman yang sudah tersimpan.
 *
 * Tidak meminta apa pun ke HC Learn — bukti mentahnya sudah ada di sini, yang salah hanya
 * penerjemahannya. Aman diulang.
 */
export function petakanUlangKiriman(cycleId, payloadId) {
    return panggil('post', `/cycles/${cycleId}/kiriman/${payloadId}/petakan-ulang`);
}

/**
 * Mencetak ULANG pintu satu penugasan.
 *
 * Beralamat pada PENUGASAN, bukan siklus: yang diterbitkan ulang memang satu pintu. Alasan
 * WAJIB dan tidak boleh berbentuk pilihan tertutup — penilai menerima tautan baru sementara
 * tautan lamanya berhenti berlaku, dan enam bulan kemudian satu-satunya penjelasan kenapa
 * ia sempat memegang dua tautan adalah kalimat yang ditulis di sini.
 */
export function cetakUlangPintu(assignmentId, alasan) {
    return panggil('post', `/penugasan/${assignmentId}/cetak-ulang-pintu`, { alasan });
}

/**
 * Jadwalkan ulang: jadwal lama DIHAPUS di HC Learn lalu dijadwalkan baru. Jawaban yang sudah
 * diisi ikut dihapus. → `{ assignment_id, jawaban_dihapus, jadwal, catatan }`.
 */
export function jadwalUlangPintu(assignmentId, alasan) {
    return panggil('post', `/penugasan/${assignmentId}/jadwal-ulang-pintu`, { alasan });
}

/**
 * Membuka jendela pengisian, sekaligus MENETAPKANNYA bila belum ada.
 *
 * Jendelanya syarat tindakan ini, jadi ia ikut dalam permintaan yang sama. Memisahkannya
 * menjadi "sunting siklus dulu, baru buka" berarti dua kali muat ulang untuk satu keputusan,
 * dan meninggalkan celah tempat jendela tersimpan tanpa siklusnya pernah dibuka.
 *
 * @param {number|string} cycleId
 * @param {{pengisian_dari?: string, pengisian_sampai?: string}} [jendela] kosongkan bila
 *        siklusnya sudah punya jendela
 */
export function bukaPengisian(cycleId, jendela = {}) {
    const isi = {};

    if (jendela?.pengisian_dari) {
        isi.pengisian_dari = jendela.pengisian_dari;
    }

    if (jendela?.pengisian_sampai) {
        isi.pengisian_sampai = jendela.pengisian_sampai;
    }

    return panggil('post', `/cycles/${cycleId}/buka-pengisian`, isi);
}

/**
 * Menutup jendela pengisian: `OPEN` -> `CLOSED`.
 *
 * Tombolnya dari `operasi_boleh` yang memuat **`TUTUP_PENGISIAN`**.
 *
 * `penugasan_belum_selesai` DILAPORKAN, bukan menghalangi. Menutup siklus dengan 200
 * penilaian belum masuk itu sah — tetapi tidak boleh terjadi tanpa disadari, jadi
 * tampilkan angkanya pada konfirmasi sebelum menutup.
 */
export function tutupPengisian(cycleId) {
    return panggil('post', `/cycles/${cycleId}/tutup-pengisian`);
}

/**
 * Daftar kosong berarti keranjangnya BERSIH, bukan gagal memuat. Bedakan keduanya
 * di layar — keduanya terlihat sama kalau tidak sengaja dibedakan.
 */
/**
 * Alur berlangkah satu siklus — ada di langkah mana, apa berikutnya, apa penghalangnya.
 *
 * **Layar hanya MENGGAMBAR. Jangan menyimpulkan apa pun dari sini.**
 *
 * - `langkah_aktif` sudah diturunkan server dari POSISI siklus (`Status_Cycle`). Jangan
 *   menghitungnya sendiri dari "langkah pertama yang isinya belum lengkap" — modul SOP
 *   pernah melakukannya dan layarnya melompat setiap kali menyimpan, yang dilaporkan
 *   penggunanya sebagai "tersimpan tapi selalu balik ke Review".
 * - `keadaan` per langkah bernilai `SELESAI` | `BERJALAN` | `TERKUNCI`. Bedakan ketiganya:
 *   yang SELESAI boleh dibuka untuk ditinjau, yang TERKUNCI belum boleh disentuh.
 * - `penghalang` hanya terisi pada langkah berjalan, dan kalimatnya memang untuk
 *   ditampilkan apa adanya — ia menjelaskan kenapa tombolnya belum bisa ditekan, jauh
 *   sebelum orang menekannya dan menerima 422.
 * - `aksi_utama.operasi` dijamin ada di `operasi_boleh`. Pakai itu untuk menghidupkan
 *   tombolnya, bukan menyimpulkan dari status.
 *
 * Muat ulang alur ini sesudah setiap aksi yang mengubah keadaan siklus.
 */
export function alurLangkah(cycleId) {
    return panggil('get', `/cycles/${cycleId}/langkah`);
}

/**
 * Pratinjau penyiapan — apa yang AKAN terjadi bila "Siapkan" ditekan.
 *
 * **Tidak menulis apa pun.** Panggil ini SEBELUM `siapkanSiklus()` dan tampilkan hasilnya
 * sebagai konfirmasi — bukan sesudahnya sebagai laporan.
 *
 * Yang paling perlu ditonjolkan di layar: `level_tanpa_aturan`. Level yang ada di situ
 * berarti orang-orangnya akan mendarat di keranjang TERTUNDA, dan siklus TIDAK BISA
 * DIKUNCI selama keranjang itu terisi. Memperbaikinya sebelum menyiapkan jauh lebih murah
 * daripada sesudah 958 baris terlanjur terbentuk.
 *
 * `sebab_dikecualikan` sudah DIKELOMPOKKAN per sebab, bukan daftar per orang — 99 baris
 * "Level FREELANCE dikecualikan" menenggelamkan satu baris yang benar-benar perlu dilihat.
 * `contoh` memuat paling banyak 10 nama per kelompok, sekadar supaya angkanya punya wajah.
 */
/**
 * Keadaan SELURUH langkah penyiapan satu siklus, dalam satu panggilan.
 *
 * Memulangkan `langkah[]` (status, boleh_disunting, alasan_terkunci, ringkasan),
 * `langkah_aktif` (POSISI admin — dibaca dari server, bukan disimpulkan layar dari
 * kelengkapan isi), `siap_dijalankan`, dan `penghalang[]` berupa kalimat siap tampil.
 *
 * Jangan menyusun ulang aturan "kapan boleh menjalankan penyiapan" di layar. Kalau layar
 * dan server memutuskannya sendiri-sendiri, keduanya akan berbeda tepat pada kasus yang
 * jarang — dan yang terlihat admin adalah tombol menyala yang selalu ditolak.
 */
export function keadaanSetup(cycleId) {
    return panggil('get', `/cycles/${cycleId}/setup`);
}

/**
 * Memindahkan posisi admin ke satu langkah, dan menyimpannya.
 *
 * Dipanggil saat admin menekan langkah di penunjuk posisi. Posisinya disimpan supaya ia
 * kembali ke tempat yang sama ketika halamannya dibuka lagi — bukan dilempar ke langkah
 * kosong paling depan.
 *
 * Memulangkan keadaan setup yang sudah diperbarui, jadi tidak perlu memuat ulang.
 */
export function pindahLangkahSetup(cycleId, langkah) {
    return panggil('post', `/cycles/${cycleId}/setup/pindah`, { langkah });
}

/**
 * Menandai satu langkah sebagai draft — "saya berhenti di sini, belum selesai".
 *
 * Langkah yang isinya benar-benar tersimpan (bobot, paket soal, aturan) menandai dirinya
 * sendiri; ini untuk admin yang ingin meninggalkan catatan bahwa ia harus kembali.
 */
export function draftLangkahSetup(cycleId, langkah, catatan = null) {
    return panggil('post', `/cycles/${cycleId}/setup/draft`, { langkah, catatan });
}

/**
 * Aturan penilai MILIK SIKLUS ini — siapa menilai siapa, per level.
 *
 * `perlu_disalin: true` berarti siklus ini belum punya salinannya sama sekali; panggil
 * `salinAturanPenilai()` lebih dahulu. Sengaja tidak disalin otomatis saat dibaca: satu
 * kunjungan halaman tidak boleh mengubah data.
 */
/**
 * Level yang dipakai karyawan aktif, beserta cakupannya di siklus ini.
 *
 * Memulangkan `level[]` dengan `punya_bobot` / `punya_paket` / `punya_aturan`, plus
 * `tanpa_bobot[]` / `tanpa_paket[]` / `tanpa_aturan[]` yang sudah tersaring.
 *
 * PAKAI INI, bukan `daftarAturanPenilai()`, untuk menyusun daftar level di langkah 1 dan 2.
 * Aturan penilai baru ada sesudah disalin di langkah 3; menurunkan daftar level darinya
 * membuat langkah 1 dan 2 kosong sampai langkah 3 selesai — padahal nomornya mengatakan
 * sebaliknya, dan admin yang mengikuti urutan justru melihat layar kosong.
 */
/**
 * Katalog paket soal HC Learn yang bisa dipilih untuk siklus ini.
 *
 * Memulangkan `{ sumber, paket[] }`. Tiap ujian membawa `Id_Master_Ujian`, `Nama_Ujian`,
 * `Id_Master_Paket_Ujian`, `Nama_Paket`, `Kode_Ujian`, `jumlah_bagian`, dan `Bagian[]`
 * hasil perataan empat tingkat HC Learn (ujian → paket → alat tes → bagian).
 *
 * BAGIANNYA TIDAK BERLABEL KOMPETENSI, dan itu bukan kekurangan data. Sejak kontrak
 * 9 September 2026 kompetensi bukan lagi milik HC Learn: admin yang memetakannya di
 * halaman Ikatan Paket Soal, satu bagian ke tepat satu kompetensi. Layar yang mencoba
 * membaca kompetensi dari sini akan selalu menemukan nol.
 *
 * Identitas satu bagian adalah PASANGAN (`id_jenis_indikator`, `id_jenis_soal`) — bukan
 * `id_jenis_soal` saja: satu bank soal boleh dipakai di dua alat tes berbeda, dan itu dua
 * bagian yang berbeda.
 *
 * Ujian bisa membawa `bagian_tersedia: false` ketika seluruh alat tesnya bertipe khusus
 * (Kraeplin, PAPI, DISC) yang memang tidak berbagian. Ujian seperti itu tetap ditampilkan
 * supaya admin tahu ia ada, tetapi TIDAK boleh diikat.
 */
/**
 * Matriks dimensi penilaian x level jabatan — "Matrix Dimensi Penilaian Kompetensi" di deck.
 *
 * Memulangkan `{ dimensi[], level[], level_berisi_tanpa_dimensi[] }`. Tiap level punya
 * `sel` berkunci kode dimensi (`{ id, aktif, wajib }`), `jumlah_karyawan`, dan
 * `dikecualikan_pa` untuk level yang memang tidak pernah dinilai (FREELANCE).
 *
 * `level_berisi_tanpa_dimensi` adalah yang WAJIB ditonjolkan: orang di level itu akan
 * membuka formulir penilaian kosong — tanpa galat, tanpa pesan, hanya layar tanpa
 * pertanyaan.
 */
/**
 * Matriks pilihan paket soal BAKU per (level × kategori penilai).
 *
 * Bedanya dengan `matriksPaketSoal(cycleId)`: yang ini pilihan bakunya, dipakai siklus baru
 * sebagai titik mulai. Yang itu pengikatan yang benar-benar berlaku pada satu siklus.
 *
 * Tiap sel yang terisi membawa hasil PEMERIKSAAN terhadap katalog HC Learn — dijalankan
 * setiap kali dibaca, bukan hanya saat disimpan, karena paket bisa berubah di sisi mereka
 * kapan saja:
 *   `sahih`   — lolos ketiga pemeriksaan.
 *   `masalah` — daftar `{ jenis, pesan }`, jenisnya salah satu dari:
 *       HILANG   paketnya tidak ada lagi di katalog HC Learn.
 *       KURANG   tidak lagi mencakup semua kompetensi wajib level itu.
 *       LEBIH    memuat kompetensi yang tidak berlaku di level itu.
 *       BERGESER susunannya berubah sejak terakhir diverifikasi.
 */
/**
 * Katalog paket HC Learn tanpa menyebut siklus.
 *
 * Isinya sama dengan `katalogPaketSoal(cycleId)` — katalognya milik HC Learn, bukan milik
 * siklus. Dipakai halaman master, yang tidak punya siklus untuk disebut.
 */
export function katalogPaketSoalMaster() {
    return panggil('get', '/master/katalog-paket-soal');
}

export function matriksIkatanBaku() {
    return panggil('get', '/master/ikatan-paket-soal/matriks');
}

/**
 * Bagian satu ujian beserta kompetensi wajib level yang dituju — SATU permintaan.
 *
 * Keduanya diminta bersama supaya layar bisa menolak ujian yang cacah bagiannya tidak
 * sepadan SEBELUM menggambar barisan pelabelan yang ternyata mustahil diisi. Balasannya
 * juga memuat `kembar[]`: pasangan identitas yang muncul dua kali di ujian itu, yang harus
 * menghalangi pelabelan sama sekali.
 */
export function ambilBagianUjian(idMasterUjian, idLevel) {
    const q = new URLSearchParams({ id_master_ujian: idMasterUjian, id_level: String(idLevel) });

    return panggil('get', `/master/ikatan-paket-soal/bagian?${q.toString()}`);
}

/** Label satu sel, untuk disalin ke sel lain yang memakai ujian dan level yang sama. */
export function ambilLabelIkatan(id) {
    return panggil('get', `/master/ikatan-paket-soal/${id}/label`);
}

/** Memeriksa ulang SATU sel terhadap katalog HC Learn dan menyimpan hasilnya. */
export function periksaSelIkatan(id) {
    return panggil('post', `/master/ikatan-paket-soal/${id}/periksa`);
}

/**
 * Menetapkan pilihan baku satu sel beserta pemetaan bagian→kompetensinya.
 *
 * `label` berkunci `"{id_jenis_indikator}|{id_jenis_soal}"` — pasangan, bukan satu field.
 * HC Learn tidak memberi id per-instance untuk bagian, dan `id_jenis_soal` sendirian adalah
 * pengenal BANK soal yang bisa muncul di beberapa alat tes sekaligus.
 *
 * `raterCategoryId` null menjadikannya baku level itu.
 */
export function simpanIkatanBaku(idLevel, raterCategoryId, idMasterUjian, label) {
    return panggil('post', '/master/ikatan-paket-soal', {
        id_level: idLevel,
        rater_category_id: raterCategoryId,
        id_master_ujian: idMasterUjian,
        label,
    });
}

/** Mencabut pilihan baku satu sel. Dinonaktifkan, bukan dihapus. */
export function cabutIkatanBaku(id) {
    return panggil('post', `/master/ikatan-paket-soal/${id}/cabut`);
}

/**
 * Memeriksa ulang seluruh sel dan menyegarkan sidik susunan yang masih sahih.
 *
 * Sel bermasalah SENGAJA tidak disegarkan — menyegarkannya menghapus bukti masalahnya, dan
 * pemeriksaan berikutnya melaporkannya bersih padahal tak ada yang diperbaiki. Balasannya
 * memuat `bermasalah[]` yang harus ditampilkan apa adanya.
 */
export function verifikasiIkatanBaku() {
    return panggil('post', '/master/ikatan-paket-soal/verifikasi');
}

/**
 * Menyalin seluruh pilihan baku ke satu siklus.
 *
 * Butirnya diambil dari HC Learn SEKARANG, bukan dari yang tercatat master. Sel yang sudah
 * terisi di siklus TIDAK ditimpa, dan sel bermasalah dilewati — keduanya dilaporkan di
 * `dilewati[]` beserta alasannya.
 */
/**
 * Menerima usulan master untuk SATU sel (level x kategori penilai).
 *
 * Dipakai tombol pada sel kosong yang menampilkan usulan master — satu klik, tanpa membuka
 * dialog pemilih. Untuk mengisi seluruh sel kosong sekaligus, pakai
 * `salinPaketSoalDariMaster()`.
 *
 * Ditolak dengan 422 bila master belum punya pilihan untuk sel itu; pesannya menunjuk ke
 * Master Data > Paket Soal Baku.
 */
export function terimaUsulanPaketSoal(cycleId, idLevel, raterCategoryId = null) {
    return panggil('post', `/cycles/${cycleId}/paket-soal/terima-usulan`, {
        id_level: idLevel,
        rater_category_id: raterCategoryId,
    });
}

export function salinPaketSoalDariMaster(cycleId) {
    return panggil('post', `/cycles/${cycleId}/paket-soal/salin-master`);
}

export function matriksSyaratKompetensi() {
    return panggil('get', '/master/syarat-kompetensi/matriks');
}

/**
 * Matriks LEVEL JABATAN x JENIS PENILAI.
 *
 * Memulangkan `{ kategori[], level[], perlu_perhatian[], total_dikecualikan }`. Tiap level
 * membawa `sel` berkunci kode kategori (`{ bobot, min, maks, min_selesai, izinkan_manual }`
 * atau `null` bila kategori itu tidak berlaku), `policy` yang sedang aktif, `total_bobot`,
 * serta `dikecualikan_pa` + `alasan_dikecualikan`.
 *
 * Bobot dan kuotanya HANYA DIBACA di sini — yang menyuntingnya layar Komposisi Penilai,
 * tempat versi dan masa berlaku kebijakannya hidup. Satu penulis, banyak pembaca.
 *
 * `perlu_perhatian` sudah menyaring level yang dikecualikan: nol kategori padanya bukan
 * kekeliruan, dan penjaga yang berteriak atas keadaan yang disengaja berhenti dibaca.
 */
export function matriksJenisPenilai() {
    return panggil('get', '/master/jenis-penilai/matriks');
}

/**
 * Menandai atau mencabut satu sel matriks: kategori ini menilai level itu, atau tidak.
 *
 * TIDAK mengirim bobot — ini langkah pertama. Sel yang baru ditandai lahir tanpa bobot dan
 * tanpa kuota, dan Komposisi Penilai yang mengisinya menyusul. Responsnya membawa
 * `berbobot: false` supaya layar bisa menandainya "menunggu bobot", bukan "bobot 0%".
 */
export function setelSelJenisPenilai(levelId, kategoriId, berlaku) {
    return panggil('post', `/master/jenis-penilai/${levelId}/sel`, {
        pa_rater_category_id: kategoriId,
        berlaku: berlaku ? 1 : 0,
    });
}

/**
 * Mengecualikan satu level dari Performance Appraisal. Alasannya WAJIB.
 *
 * Tidak mencabut peserta dari siklus yang SEDANG berjalan — mereka membawa levelnya sebagai
 * salinan, jadi pengecualian ini berlaku pada penyiapan siklus berikutnya. Respons membawa
 * `peserta_siklus_berjalan` supaya layar bisa mengatakan angkanya apa adanya.
 */
export function kecualikanLevelPa(levelId, alasan) {
    return panggil('post', `/master/jenis-penilai/${levelId}/kecualikan`, { alasan });
}

/** Mencabut pengecualian satu level. Barisnya dinonaktifkan, bukan dihapus. */
export function cabutPengecualianLevelPa(levelId) {
    return panggil('post', `/master/jenis-penilai/${levelId}/cabut-pengecualian`);
}

/**
 * Minimal masa kerja peserta di master (DDL-2026-191): nilai awal setiap siklus BARU.
 *
 * @returns {{ min_masa_kerja_bulan: number, maks_bulan: number, tersimpan: boolean, updated_at: ?string }}
 */
export function kelayakanMasaKerjaMaster() {
    return panggil('get', '/master/jenis-penilai/kelayakan-masa-kerja');
}

export function simpanKelayakanMasaKerjaMaster(bulan) {
    return panggil('put', '/master/jenis-penilai/kelayakan-masa-kerja', { min_masa_kerja_bulan: bulan });
}

/** Mengecualikan banyak level sekaligus dari Performance Appraisal. */
export function kecualikanBanyakLevelPa(levelIds, alasan) {
    return panggil('post', '/master/jenis-penilai/bulk/kecualikan', {
        level_ids: levelIds,
        alasan,
    });
}

/** Mencabut pengecualian banyak level sekaligus. */
export function cabutBanyakPengecualianLevelPa(levelIds) {
    return panggil('post', '/master/jenis-penilai/bulk/cabut-pengecualian', {
        level_ids: levelIds,
    });
}

/**
 * Menyimpan rincian komposisi penilai (bobot & kuota) untuk satu level jabatan.
 * Total bobot wajib tepat 100%.
 */
export function simpanKomposisiLevel(levelId, rincian) {
    return panggil('put', `/master/jenis-penilai/${levelId}/komposisi`, { rincian });
}

/**
 * Menyimpan satu baris matriks.
 *
 * `dimensi` adalah daftar KODE dimensi yang dicentang, dan harus memuat SELURUHNYA — yang
 * tidak disebut akan dinonaktifkan. Larik kosong sah (level yang tidak dinilai) dan
 * dijawab dengan `peringatan`, bukan penolakan.
 */
export function simpanSyaratKompetensi(levelId, dimensi) {
    return panggil('put', `/master/syarat-kompetensi/${levelId}`, { dimensi });
}

/* Koreksi Level PA per karyawan (DDL-2026-177). */
export function daftarLevelPa(saringan = {}) {
    const q = new URLSearchParams(Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== '')).toString();
    return panggil('get', `/master/level-pa${q ? `?${q}` : ''}`);
}
export function cariKaryawanLevelPa(kata) {
    return panggil('get', `/master/level-pa/karyawan?q=${encodeURIComponent(kata)}`);
}
export function calonLevelPa(q = '') {
    return panggil('get', `/master/level-pa/calon${q ? `?q=${encodeURIComponent(q)}` : ''}`);
}
export function simpanLevelPa(isi) {
    return panggil('post', '/master/level-pa', isi);
}
export function ubahLevelPa(id, isi) {
    return panggil('put', `/master/level-pa/${id}`, isi);
}
export function cabutLevelPa(id, alasan) {
    return panggil('post', `/master/level-pa/${id}/cabut`, { alasan });
}
/* Master Level PA (DDL-2026-183): level HRIS (hanya-baca) + level khusus PA. */
export function daftarLevelMaster(semua = false) {
    return panggil('get', `/master/level-master${semua ? '?semua=1' : ''}`);
}
/** Keadaan Master Level PA dibanding HRIS: `{ sinkron, jumlah_hris, tersalin, belum[], beda[], hilang[] }`. */
export function periksaLevelMaster() {
    return panggil('get', '/master/level-master/periksa');
}
/** Sinkron sekarang → `{ hasil: { ditambah, diperbarui }, periksa }`. */
export function sinkronLevelMaster() {
    return panggil('post', '/master/level-master/sinkron');
}
export function tambahLevelMaster(isi) {
    return panggil('post', '/master/level-master', isi);
}
export function ubahLevelMaster(id, isi) {
    return panggil('put', `/master/level-master/${id}`, isi);
}
export function aturAktifLevelMaster(id, aktif) {
    return panggil('post', `/master/level-master/${id}/aktif`, { aktif });
}
export function imporLevelPa(berkas) {
    const form = new FormData();
    form.append('berkas', berkas);
    return panggil('post', '/master/level-pa/impor', form);
}
/** Alamat unduh (GET biasa, sesi peramban ikut terkirim). */
export function alamatEksporLevelPa(templat = false) {
    return `${PREFIKS}/master/level-pa/ekspor${templat ? '?templat=1' : ''}`;
}

/** Matriks Kompetensi per KOMPETENSI: level, kompetensi (+ pilihan tingkat), dan isi sel. */
export function matriksKompetensi() {
    return panggil('get', '/master/matriks-kompetensi');
}

/** Satu baris level: `isi` = { [kompetensiId]: tingkatId | null }. Yang tidak disebut = tidak dinilai. */
export function simpanMatriksKompetensi(levelId, isi) {
    return panggil('put', `/master/matriks-kompetensi/${levelId}`, { isi });
}

export function tingkatKompetensi(kompetensiId) {
    return panggil('get', `/master/kompetensi/${kompetensiId}/tingkat`);
}

/** Seluruh tingkat satu kompetensi: [{ id?, tingkat, nama, expected_behaviour }]. */
export function simpanTingkatKompetensi(kompetensiId, tingkat) {
    return panggil('put', `/master/kompetensi/${kompetensiId}/tingkat`, { tingkat });
}

/**
 * Matriks pengikatan paket soal — baris level, kolom kategori penilai.
 *
 * Inilah "RATER x Level" yang diminta HC: soal berbeda per (level x kategori penilai).
 *
 * Tiap sel membawa:
 *   `ikut_menilai` — kategori ini memang menilai level itu menurut aturan penilai siklus.
 *                    FALSE berarti sel sengaja kosong (tanda "x" di gambar HC), bukan lalai.
 *   `paket_id`     — paket yang berlaku, atau null.
 *   `khusus`       — true bila paketnya milik kategori ini; false bila yang berlaku adalah
 *                    paket BAWAAN level (baris ber-rater NULL). Tampilkan keduanya berbeda.
 *
 * `total_lubang` = sel yang `ikut_menilai` tetapi tanpa paket. Ini angka yang paling perlu
 * ditonjolkan: penilai di sel itu akan membuka formulir kosong tanpa satu pun pesan.
 *
 * Tiap level juga membawa `dimensi_wajib: [{ kode, nama }]` dari Master Data > Dimensi per
 * Level. PAKAI INI untuk menandai paket mana yang cocok SEBELUM admin mengklik: paket
 * cocok bila himpunan `Dimensi`-nya sama persis dengan `dimensi_wajib` level itu.
 *
 * Server menolak dua arah, dan pesannya berbeda karena penyelesaiannya berbeda:
 *   KURANG — dimensi wajib tanpa butir. Skor kompetensi dihitung dari dimensi yang tidak
 *            pernah ditanyakan, tanpa satu pun galat.
 *   LEBIH  — butir dari dimensi yang tidak berlaku di level itu. Orangnya dinilai atas hal
 *            yang bukan urusannya, dan skornya bergeser.
 */
/**
 * Kesehatan penugasan penilai — Langkah 5.
 *
 * Membandingkan aturan penilai (Langkah 3) dengan penugasan yang benar-benar terbentuk.
 * Memulangkan `baris[]` per (level x kategori) dengan `min_rater`, `peserta`,
 * `peserta_cukup`, `peserta_kurang`, `penilai_nonaktif`, plus `siap_dikunci`.
 *
 * Empat keadaan SENGAJA tidak digabung — "kurang penilai" diselesaikan dengan menambah,
 * "penilainya resign" dengan mengganti. Angka gabungan menyembunyikan mana yang dikerjakan.
 */
export function kesehatanPenugasan(cycleId) {
    return panggil('get', `/cycles/${cycleId}/kesehatan-penugasan`);
}

/**
 * Peserta yang penugasannya bermasalah, berhalaman.
 *
 * `hanya_bermasalah` menyaring pada HALAMAN ini saja — masalahnya baru diketahui sesudah
 * barisnya diambil, jadi ia tidak bisa jadi kondisi SQL. Balasannya membawa
 * `saringan_per_halaman: true` sebagai pengingat: halaman bersih tidak berarti siklusnya
 * bersih. Untuk itu baca `kesehatanPenugasan()`.
 */
export function penugasanBermasalah(cycleId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/cycles/${cycleId}/penugasan-bermasalah${q ? `?${q}` : ''}`);
}

export function matriksPaketSoal(cycleId) {
    return panggil('get', `/cycles/${cycleId}/matriks-paket-soal`);
}

/**
 * Mengikat paket yang SUDAH ADA di siklus ini ke satu sel.
 *
 * `raterCategoryId` null menjadikannya paket BAWAAN level itu — dipakai kategori mana pun
 * yang tidak punya paket sendiri. Pencarian di server mencocokkan kategori tepat lebih
 * dulu, baru jatuh ke bawaan.
 */
export function ikatPaketSoal(cycleId, paketId, raterCategoryId = null) {
    return panggil('post', `/cycles/${cycleId}/paket-soal/ikat`, {
        paket_id: paketId,
        rater_category_id: raterCategoryId,
    });
}

export function katalogPaketSoal(cycleId) {
    return panggil('get', `/cycles/${cycleId}/katalog-paket-soal`);
}

/**
 * Mengimpor satu paket HC Learn ke satu level pada siklus ini, beserta seluruh butirnya.
 *
 * Menggantikan pengetikan kode paket manual. Server menolak: paket yang tidak ada di
 * katalog, paket tanpa butir, paket yang memuat kode soal tak dikenal HCIS, dan level yang
 * sudah punya paket aktif. Tampilkan pesan 422-nya apa adanya — semuanya menyebut angka
 * dan kode yang tepat.
 */
/**
 * Bagian satu ujian + label yang sudah ada untuk sel ini — bahan dialog pelabelan di
 * Langkah 2. Alamat SIKLUS, bukan master: izin halamannya berbeda.
 */
export function bagianPaketSoalSiklus(cycleId, idLevel, idMasterUjian, raterCategoryId = null) {
    const q = new URLSearchParams({ id_level: idLevel, id_master_ujian: idMasterUjian });

    if (raterCategoryId) q.set('rater_category_id', raterCategoryId);

    return panggil('get', `/cycles/${cycleId}/paket-soal/bagian?${q.toString()}`);
}

/** Menyimpan pelabelan sel dari Langkah 2. Yang ditulis MASTER, dan layar mengatakannya. */
export function labelPaketSoalSiklus(cycleId, idLevel, idMasterUjian, label, raterCategoryId = null) {
    return panggil('post', `/cycles/${cycleId}/paket-soal/label`, {
        id_level: idLevel,
        id_master_ujian: idMasterUjian,
        rater_category_id: raterCategoryId,
        label,
    });
}

export function imporPaketSoal(cycleId, idLevel, idMasterUjian, raterCategoryId = null, ganti = false) {
    return panggil('post', `/cycles/${cycleId}/paket-soal/impor`, {
        id_level: idLevel,
        id_master_ujian: idMasterUjian,
        rater_category_id: raterCategoryId,

        /* Hanya dikirim saat BENAR. Server memperlakukan penggantian sebagai permintaan
           tersendiri, dan mengirim `false` di setiap pemanggilan cuma menambah kunci yang
           tidak berarti apa-apa. */
        ...(ganti ? { ganti: true } : {}),
    });
}

export function levelSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}/level`);
}

/**
 * Kesiapan orang-orang siklus ini di HC Learn — Langkah 4, sebelum penjadwalan.
 *
 * `status` bercabang TIGA, dan ketiganya menuntut tindakan berbeda:
 *   `SIAP`           — tidak ada yang menghalangi.
 *   `PERLU_SINKRON`  — ada yang belum terdaftar; tombol sinkron menyelesaikannya.
 *   `PERLU_TINDAKAN` — terdaftar tetapi nonaktif/resign di HC Learn. Sinkronisasi TIDAK
 *                      mengubahnya; yang perlu ditinjau keikutsertaannya.
 *
 * Tiap baris membawa `dapat_disinkron`. Pakai itu untuk menghidupkan tombolnya, bukan
 * `siap` — orang yang terdaftar-tapi-nonaktif juga tidak siap, dan menawarkan sinkronisasi
 * untuknya berarti menjanjikan perbaikan yang tidak akan terjadi.
 */
export function kesiapanKaryawanHcLearn(cycleId) {
    return panggil('get', `/cycles/${cycleId}/hclearn-karyawan`);
}

/**
 * Mendaftarkan yang belum dikenal HC Learn, lalu memeriksa ulang.
 *
 * Balasannya membawa `sinkron` (hasil pendaftaran, termasuk `gagal[]` per baris) DAN
 * `kesiapan` yang sudah diperbarui. Pakai `kesiapan` dari sini alih-alih memanggil
 * `kesiapanKaryawanHcLearn()` lagi.
 */
export function sinkronkanKaryawanHcLearn(cycleId) {
    return panggil('post', `/cycles/${cycleId}/hclearn-karyawan/sinkronkan`);
}

/**
 * Mencari karyawan untuk dikecualikan pada langkah 4.
 *
 * `q` minimal 2 huruf — di bawah itu memulangkan daftar KOSONG, bukan 958 baris pertama.
 * Tiap baris membawa `sudah_dikecualikan` supaya layar bisa meredupkan orang yang memang
 * sudah ada di daftar, bukan membiarkan admin menambahkannya lagi dan mendapat "0
 * ditambahkan" tanpa tahu sebabnya.
 */
export function cariKaryawanPraKecuali(cycleId, saringan = {}, limit = 1000) {
    const params = new URLSearchParams();

    if (typeof saringan === 'string') {
        if (saringan) params.append('q', saringan);
        params.append('limit', String(limit));
    } else if (saringan && typeof saringan === 'object') {
        if (saringan.q) params.append('q', saringan.q);
        params.append('limit', String(saringan.limit || limit));

        const appendFilter = (key, val) => {
            if (Array.isArray(val)) {
                val.forEach((item, i) => {
                    if (item !== '' && item !== null && item !== undefined) {
                        params.append(`${key}[${i}]`, item);
                    }
                });
            } else if (val !== '' && val !== null && val !== undefined) {
                params.append(key, val);
            }
        };

        appendFilter('divisi', saringan.divisi);
        appendFilter('departemen', saringan.departemen);
        appendFilter('id_level', saringan.id_level);
        appendFilter('cabang', saringan.cabang);
        appendFilter('kode_list', saringan.kode_list);
    }

    return panggil('get', `/cycles/${cycleId}/cari-karyawan?${params.toString()}`);
}

export function opsiFilterKaryawanPraKecuali(cycleId) {
    return panggil('get', `/cycles/${cycleId}/opsi-filter-karyawan`);
}

export function daftarAturanPenilai(cycleId) {
    return panggil('get', `/cycles/${cycleId}/aturan-penilai`);
}

/** Menyalin aturan dari master ke siklus ini. Aman diulang — level yang sudah ada dilewati. */
export function salinAturanPenilai(cycleId) {
    return panggil('post', `/cycles/${cycleId}/aturan-penilai/salin`);
}

/**
 * Menyimpan rincian aturan satu level.
 *
 * SELURUH kategori level itu wajib dikirim, bukan yang berubah saja: bobotnya diperiksa
 * harus berjumlah 100, dan angka itu hanya bermakna bila semuanya dilihat bersamaan.
 * Mengirim sebagian ditolak dengan 422 yang menyebut berapa yang kurang.
 */
export function simpanAturanPenilai(cycleId, levelId, rincian) {
    return panggil('put', `/cycles/${cycleId}/aturan-penilai/${levelId}`, { rincian });
}

export function pratinjauPenyiapan(cycleId) {
    return panggil('get', `/cycles/${cycleId}/pratinjau-penyiapan`);
}

/**
 * Apa yang berubah sejak peserta dan penugasannya dibentuk: karyawan baru / level yang belum
 * diterapkan, peserta belum berpenugasan, Level PA tertahan, dan peserta yang resign.
 */
export function perubahanPeserta(cycleId) {
    return panggil('get', `/cycles/${cycleId}/perubahan-peserta`);
}

/** Batalkan penugasan (yang belum diisi) peserta tertahan Level PA, lalu segarkan levelnya. */
export function segarkanLevelTertahan(cycleId, participantIds) {
    return panggil('post', `/cycles/${cycleId}/segarkan-level-tertahan`, { participant_ids: participantIds });
}

/**
 * Daftar pengecualian AWAL — siapa yang tidak akan dinilai, ditulis sebelum peserta ada.
 *
 * Bedanya dengan `ubahStatusPeserta()`: yang ini bekerja SEBELUM penyiapan, ketika baris
 * peserta belum ada sama sekali. Sesudah penyiapan, pakai yang itu.
 *
 * `karyawan_sudah_tidak_aktif: true` menandai orang yang sudah keluar sejak daftarnya
 * dibuat. Barisnya sengaja TIDAK dibuang — daftar yang berubah sendiri tanpa ada yang
 * mengubahnya membuat admin mengira ia terhapus.
 */
/**
 * Minimal masa kerja siklus. `boleh_ubah` false sesudah peserta dibentuk atau siklus dikunci;
 * `min_masa_kerja_bulan` null untuk siklus lama yang dibuat sebelum aturan ini ada.
 *
 * @returns {{ min_masa_kerja_bulan: ?number, nilai_master: number, maks_bulan: number, boleh_ubah: boolean, alasan_terkunci: ?string }}
 */
export function kelayakanMasaKerjaSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}/kelayakan-masa-kerja`);
}

export function simpanKelayakanMasaKerjaSiklus(cycleId, bulan) {
    return panggil('put', `/cycles/${cycleId}/kelayakan-masa-kerja`, { min_masa_kerja_bulan: bulan });
}

export function daftarPraKecuali(cycleId) {
    return panggil('get', `/cycles/${cycleId}/pra-kecuali`);
}

/**
 * Menambahkan orang ke daftar pengecualian awal. `alasan` WAJIB dan berlaku untuk seluruh
 * orang dalam satu panggilan — satu alasan, satu kelompok orang.
 *
 * Balasannya membedakan `ditambah` dari `sudah_ada`: mengirim ulang orang yang sama bukan
 * galat, tetapi layar perlu bisa mengatakan "3 ditambahkan, 2 memang sudah ada".
 */
export function tambahPraKecuali(cycleId, orang, alasan) {
    return panggil('post', `/cycles/${cycleId}/pra-kecuali`, { orang, alasan });
}

/** Mencabut satu baris. Dinonaktifkan, bukan dihapus — riwayatnya tetap ada. */
export function cabutPraKecuali(cycleId, id) {
    return panggil('post', `/cycles/${cycleId}/pra-kecuali/${id}/cabut`);
}

/**
 * Mencabut beberapa baris pengecualian sekaligus. Peserta yang sudah terbentuk diikutkan
 * kembali; jawabannya membawa langkah berikutnya.
 *
 * @returns {{ dicabut: number, diikutkan: number, belum_peserta: number, ada_penugasan: boolean, gagal: Array<{id:number, nama:string, sebab:string}> }}
 */
export function cabutPraKecualiBanyak(cycleId, ids) {
    return panggil('post', `/cycles/${cycleId}/pra-kecuali/cabut-massal`, { ids });
}

export function keranjangTertunda(cycleId) {
    return panggil('get', `/cycles/${cycleId}/tertunda`);
}

/**
 * Satu halaman peserta. BERHALAMAN sejak awal — satu siklus memuat seluruh karyawan
 * aktif, hampir seribu orang.
 *
 * `per_halaman` dipangkas server di 100. Ketika itu terjadi, `dipangkas` bernilai true;
 * tampilkan, jangan diam-diam diterima — pembatasan yang tidak terlihat membuat orang
 * mengira datanya memang cuma segitu.
 *
 * @param {{status_peserta?: string, status_hasil?: string, id_divisi?: number,
 *          id_level?: number, q?: string, halaman?: number, per_halaman?: number}} saringan
 */
export function daftarPeserta(cycleId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/cycles/${cycleId}/participants${q ? `?${q}` : ''}`);
}

/**
 * Sebaran sembilan kotak HAV Map dan distribusi grade.
 *
 * SEMBILAN kotak selalu datang, termasuk yang `jumlah === 0`. Jangan disaring —
 * matriks berlubang lebih menyesatkan daripada kotak kosong, dan "tidak ada seorang pun
 * di Deadwood" adalah temuan tersendiri.
 *
 * `Target_Persen` pada `grade[]` adalah PEMBANDING, bukan kuota. Tidak ada satu pun
 * bagian sistem yang memaksa sebaran mengikutinya.
 */
export function havMap(cycleId) {
    return panggil('get', `/cycles/${cycleId}/hav-map`);
}

/**
 * Seluruh isi layar hasil satu orang.
 *
 * `kompetensi[]` memuat DUA jenis baris: yang `agregat === true` adalah nilai lintas
 * kategori — itulah yang digambar radar. Sisanya rincian per kategori penilai.
 * Menggambar keduanya sekaligus menghasilkan radar berlapis yang salah.
 *
 * Layar ini harus tetap bisa dibuka untuk peserta TERTAHAN: justru di sanalah
 * `Alasan_Tertahan` dibaca.
 */
export function detailHasil(participantId) {
    return panggil('get', `/participants/${participantId}/hasil`);
}

/**
 * Menggeser grade akhir dan/atau kuadran.
 *
 * `alasan` wajib. `Kode_Grade_Sistem` TIDAK ikut berubah — hasil mesin dan hasil
 * kalibrasi disimpan berdampingan supaya "berapa hitungannya sebelum digeser" bisa
 * dijawab tanpa menghitung ulang.
 *
 * Hasil yang berstatus TERTAHAN tidak bisa dikalibrasi; ia belum punya grade.
 */
export function kalibrasi(resultId, { kodeGrade = null, kuadranId = null, alasan }) {
    return panggil('post', `/results/${resultId}/kalibrasi`, {
            kode_grade: kodeGrade,
            pa_hav_quadrant_id: kuadranId,
            alasan,
        });
}

/* ------------------------------------------------------------------ *
 * Penyimpangan per peserta (K32)                                      *
 * ------------------------------------------------------------------ */

/**
 * Menyimpan SELURUH penyimpangan penilai satu peserta sekaligus.
 *
 * Sengaja tidak per baris. Bobot antar kategori wajib berjumlah 100 **sesudah digabung
 * dengan aturan levelnya**, dan jumlah itu hanya bisa diperiksa ketika seluruh
 * penyimpangannya diketahui. Satu override yang tampak wajar sendirian bisa membuat
 * gabungannya menjadi 105.
 *
 * Karena itu layarnya harus **satu formulir berisi semua kategori dengan penjumlah
 * hidup** — bukan tabel yang tiap barisnya punya tombol simpan sendiri.
 *
 * Daftar kosong berarti mencabut seluruh penyimpangan penilai.
 *
 * Responsnya membawa `bobot_efektif` — hasil gabungan yang sebenarnya berlaku. Tampilkan
 * itu, bukan angka yang dikirim: keduanya berbeda ketika sebagian kategori tidak
 * disimpangkan.
 *
 * @param {Array<{pa_rater_category_id: number, alasan_perubahan: string,
 *                flag_dipakai?: 'Y'|'T', bobot_persen?: number, min_rater?: number,
 *                maks_rater?: number|null, min_rater_selesai?: number|null}>} penilai
 */
export function simpanOverridePenilai(participantId, penilai) {
    return panggil('put', `/participants/${participantId}/override/penilai`, { penilai });
}

/**
 * Menandai satu kategori penilai SENGAJA tidak diikutkan bagi satu peserta (Langkah 5).
 * Kategorinya dicabut, bobotnya dibagi ulang, penilai yang tersisa dibatalkan.
 */
export function tandaiKategoriTidakDiikutkan(participantId, kategoriId, alasan) {
    return panggil('post', `/participants/${participantId}/kategori-tidak-diikutkan`, { kategori_id: kategoriId, alasan });
}

/** Membatalkan tanda "tidak diikutkan": kategori, bobot, dan penilainya dipulihkan. */
export function batalkanKategoriTidakDiikutkan(participantId, kategoriId) {
    return panggil('delete', `/participants/${participantId}/kategori-tidak-diikutkan/${kategoriId}`);
}

/**
 * Menyimpan penyimpangan bobot pilar. Ditolak bila jumlahnya bukan 100, atau bila
 * angkanya **sama dengan aturan levelnya** — override yang tidak mengubah apa pun hanya
 * membuat peserta itu tampak menyimpang padahal tidak.
 */
export function simpanOverridePilar(participantId, { bobotKpi, bobotKompetensi, alasan }) {
    return panggil('put', `/participants/${participantId}/override/pilar`, {
            bobot_kpi: bobotKpi,
            bobot_kompetensi: bobotKompetensi,
            alasan_perubahan: alasan,
        });
}

/**
 * Menyimpan SELURUH penyimpangan jenis kompetensi. Daftar kosong berarti mencabutnya.
 */
/**
 * Catatan Kekuatan / Area Pengembangan yang DITULIS ADMIN.
 *
 * Seluruh daftarnya dikirim sekaligus, bukan per baris: layarnya kotak teks berisi semua,
 * jadi yang dikirim memang keadaan akhir yang diinginkan. Catatan dari jawaban penilai
 * tidak tersentuh — keduanya sengaja dibedakan di server.
 */
export function simpanCatatanHasil(participantId, catatan) {
    return panggil('put', `/participants/${participantId}/catatan`, { catatan });
}

export function simpanOverrideSyarat(participantId, syarat) {
    return panggil('put', `/participants/${participantId}/override/syarat`, { syarat });
}

/**
 * Mencabut seluruh penyimpangan — peserta kembali ke aturan levelnya.
 */
export function cabutOverride(participantId) {
    return panggil('post', `/participants/${participantId}/override/cabut`);
}

/* ------------------------------------------------------------------ *
 * Penerbitan hasil                                                    *
 * ------------------------------------------------------------------ */

/**
 * Cacah hasil per status. Pakai ini untuk memutuskan tombol mana yang berguna.
 */
export function ringkasanPenerbitan(cycleId) {
    return panggil('get', `/cycles/${cycleId}/penerbitan`);
}

/** Pengingat sebelum terbit: peserta (terpilih/seluruh siklus) yang belum punya kesimpulan kualitatif. */
export function periksaKesimpulanSebelumTerbit(cycleId, participantIds = null) {
    return panggil('post', `/cycles/${cycleId}/kesimpulan-kualitatif/periksa`, participantIds ? { participant_ids: participantIds } : {});
}

/**
 * Menerbitkan seluruh hasil yang sudah DIHITUNG. Siklusnya TETAP `CLOSED`.
 *
 * Yang `TERTAHAN` sengaja tidak ikut — sistem menolak memberinya angka, dan
 * menerbitkannya berarti menunjukkan kepada karyawan sesuatu yang tidak sah.
 * Responsnya menyebut berapa yang dilewati dan kenapa; **tampilkan angka itu**,
 * jangan cuma "berhasil".
 */
export function terbitkan(cycleId, payload = {}) {
    return panggil('post', `/cycles/${cycleId}/terbitkan`, payload);
}

/**
 * Menerbitkan hasil satu orang — untuk yang tertinggal dan baru selesai diperbaiki.
 */
export function terbitkanPeserta(participantId, payload = {}) {
    return panggil('post', `/participants/${participantId}/terbitkan`, payload);
}

/**
 * Merilis hasil satu siklus ke LEADER — halaman PA Tim, bukan ke karyawan yang dinilai.
 *
 * Dua pintu yang berbeda: `terbitkan()` membuka hasil untuk ORANGNYA, ini membuka untuk
 * ATASANNYA. Tidak ada urutan yang dipaksakan, dan hasil yang sudah terbit otomatis
 * terlihat leader tanpa perlu dirilis.
 */
/** Lima tahap penutupan siklus: keadaan, penghambat, dan aktivitas terakhir tiap tahap. */
export function penutupanSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}/penutupan`);
}

export function rilisLeader(cycleId, payload = {}) {
    return panggil('post', `/cycles/${cycleId}/rilis-leader`, payload);
}

/** Merilis hasil satu orang ke leadernya. */
export function rilisLeaderPeserta(participantId, payload = {}) {
    return panggil('post', `/participants/${participantId}/rilis-leader`, payload);
}

/**
 * Menarik kembali akses leader atas satu hasil. `alasan` wajib, 5–500 karakter.
 *
 * Ditolak server bila hasilnya sudah terbit ke karyawan: leader tetap melihatnya, jadi
 * mencabut di sana tidak menyembunyikan apa pun.
 */
export function cabutRilisLeader(participantId, alasan) {
    return panggil('post', `/participants/${participantId}/cabut-rilis-leader`, { alasan });
}

/**
 * Memindahkan SIKLUS ke `PUBLISHED` — **pintu satu arah**.
 *
 * Sesudahnya gerbang tidak mengizinkan operasi apa pun: tidak ada perhitungan ulang,
 * tidak ada penerbitan susulan, tidak ada kalibrasi. Karena itu server MENOLAK selama
 * masih ada peserta yang tertahan atau belum dihitung — kalau tidak, mereka terkunci
 * selamanya.
 *
 * Penolakan itu tidak menghalangi siapa pun: hasil yang siap sudah terbit dan sudah
 * terlihat karyawan. Yang tertunda hanya penutupan administratifnya. Layar sebaiknya
 * mengatakan itu, bukan menampilkannya sebagai kegagalan.
 */
export function tutupPenerbitan(cycleId) {
    return panggil('post', `/cycles/${cycleId}/tutup-penerbitan`);
}

/* ------------------------------------------------------------------ *
 * Penugasan penilai                                                   *
 * ------------------------------------------------------------------ */

/**
 * Usulan dikunci KODE KATEGORI dan sudah berurutan menurut aturan levelnya.
 * Tampilkan menurut urutan kunci yang datang; mengurutkannya ulang di layar akan
 * membuang urutan yang sengaja ditetapkan aturan.
 */
export function usulanPenilai(participantId) {
    return panggil('get', `/participants/${participantId}/usulan-penilai`);
}

/**
 * @param {Array<{kode_kategori: string, kode_perusahaan: string, kode_karyawan: string,
 *                sumber?: 'USULAN'|'MANUAL', organization_relationship_id?: number|null,
 *                alasan_manual?: string|null}>} penilai
 */
export function tugaskanPenilai(participantId, penilai, tambahan = {}) {
    return panggil('post', `/participants/${participantId}/penugasan`, {
        ...tambahan, penilai });
}

/**
 * `alasan` wajib berisi. Pembatalan tanpa sebab adalah penugasan yang lenyap begitu
 * saja bagi siapa pun yang membacanya kemudian.
 */
/**
 * Pembagian bobot ANTAR PENILAI dalam kategori.
 *
 * Dikirim sebagai peta `assignment_id => bobot`, bukan daftar berurutan: urutan di layar bisa
 * berbeda dari urutan di database, dan daftar berurutan akan memasangkan angka ke penilai yang
 * salah tanpa satu pun galat.
 *
 * Server menuntut SELURUH penilai satu kategori disebut sekaligus dan jumlahnya tepat 100.
 */
/**
 * Calon penilai untuk satu kategori, LENGKAP dengan alasan bila tidak bisa dipilih.
 *
 * Berbeda dari pencarian karyawan umum: jawabannya bergantung pada peserta, karena relasi yang
 * sudah ada di antara mereka menentukan boleh-tidaknya. Aturannya tinggal di server; layar hanya
 * menampilkan keputusan yang sudah dibuat.
 */
export function cariCalonPenilai(participantId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/participants/${participantId}/cari-calon-penilai${q ? `?${q}` : ''}`);
}

/**
 * Menambah penilai SEKALIGUS membentuk relasi organisasinya.
 *
 * Bukan dua panggilan terpisah: relasi tanpa penugasan meninggalkan pekerjaan setengah, dan
 * penugasan tanpa relasi adalah penilai yang tidak bisa ditelusuri. Server mengerjakan keduanya
 * atau tidak sama sekali.
 */
export function tambahPenilaiDenganRelasi(participantId, data) {
    return panggil('post', `/participants/${participantId}/penilai-baru`, data);
}

export function simpanBobotPenilai(participantId, bobot) {
    return panggil('put', `/participants/${participantId}/bobot-penilai`, { bobot });
}

/*
 | Mengembalikan satu kategori ke pembagian rata DAN melepas penanda bobot khususnya.
 |
 | Bukan sekadar mengirim angka rata lewat `simpanBobotPenilai`: jalur itu menandai apa pun yang
 | dikirimnya sebagai keputusan, sehingga kategorinya berhenti menyesuaikan diri saat penilainya
 | berubah — "kembali ke bawaan" yang ternyata tidak kembali.
 */
export function kembalikanBobotRata(participantId, kategoriId) {
    return panggil('put', `/participants/${participantId}/bobot-penilai/rata`, { kategori_id: kategoriId });
}

export function batalkanPenugasan(assignmentId, alasan) {
    return panggil('post', `/assignments/${assignmentId}/batalkan`, { alasan });
}

/* ------------------------------------------------------------------ *
 * Perhitungan                                                         *
 * ------------------------------------------------------------------ */

/**
 * Memulai perhitungan hasil siklus sebagai TUGAS LATAR. Antrean yang mengerjakannya potongan
 * demi potongan; layar boleh ditutup. Siklus yang tugasnya masih berjalan mendapat tugas itu
 * kembali (`baru: false`).
 *
 * @param {'SEMUA'|'BELUM'} [lingkup]
 * @returns {{ baru: boolean, tugas: object }}
 */
export function mulaiHitungLatar(cycleId, { lingkup = null } = {}) {
    return panggil('post', `/cycles/${cycleId}/hitung/latar`, lingkup ? { lingkup } : {});
}

/** Keadaan tugas perhitungan terakhir siklus ini: `{ tugas: object|null }`. */
export function statusHitungLatar(cycleId) {
    return panggil('get', `/cycles/${cycleId}/hitung/latar`);
}

/** Melanjutkan tugas perhitungan yang gagal atau dihentikan dari kursornya. */
export function ulangiHitungLatar(cycleId, { lingkup = null } = {}) {
    return panggil('post', `/cycles/${cycleId}/hitung/latar/ulangi`, lingkup ? { lingkup } : {});
}

/** Menghentikan tugas perhitungan yang sedang berjalan; yang sudah dihitung tetap berlaku. */
export function hentikanHitungLatar(cycleId) {
    return panggil('post', `/cycles/${cycleId}/hitung/latar/hentikan`);
}

/**
 * `tertahan` BUKAN bagian dari `gagal`. Tertahan adalah keputusan sadar — orang tanpa
 * KPI disetujui, atau yang kategori penilainya tak memenuhi ambang, sengaja tidak
 * diberi angka. Menyatukan keduanya di satu penghitung membuat "berhasil sebagian"
 * terbaca seperti selesai.
 */
export function hitungSiklus(cycleId, { batas = null, mulaiDariId = null, lingkup = null } = {}) {
    const badan = {};

    if (batas !== null) badan.batas = batas;
    if (mulaiDariId !== null) badan.mulai_dari_id = mulaiDariId;

    /* 'SEMUA' | 'BELUM'. Tidak dikirim = server memakai bakunya (SEMUA), jadi pemanggil
       lama berperilaku persis seperti sebelumnya. */
    if (lingkup !== null) badan.lingkup = lingkup;

    return panggil('post', `/cycles/${cycleId}/hitung`, badan);
}

/**
 * Berapa banyak yang akan tersentuh kalau Hitung ditekan — SEBELUM ditekan.
 *
 * Angkanya dipakai dialog konfirmasi. Konfirmasi yang tidak menyebut angka bukan
 * konfirmasi; ia hanya pintu yang harus dibuka, dan orang belajar menekannya tanpa
 * membaca.
 *
 * @returns {Promise<{total:number, siap_hitung_pertama:number, belum_lengkap:number,
 *   sudah_dihitung:number, tertahan:number, terkalibrasi:number, terbit:number}>}
 */
export function pratinjauHitungSiklus(cycleId) {
    return panggil('post', `/cycles/${cycleId}/hitung/pratinjau`, {});
}

/**
 * Menghitung satu siklus SAMPAI TUNTAS, dengan mencicilnya sendiri.
 *
 * Perhitungan satu peserta terukur ±0,5 detik, jadi satu siklus berisi ratusan orang
 * MUSTAHIL selesai dalam satu permintaan HTTP — sebelum 3 September 2026 tombol "Hitung"
 * memang menjalankan seluruhnya sekaligus, dan pada 958 peserta itu berarti ditekan lalu
 * tidak terjadi apa-apa. Kegagalan yang paling sulit dilaporkan orang, karena tidak ada
 * yang bisa ditunjukkan.
 *
 * `onKemajuan` dipanggil sesudah tiap potongan supaya layar bisa menunjukkan bahwa
 * sesuatu memang sedang berjalan. Tanpa itu, mencicil hanya memindahkan diamnya — dan
 * orang akan menekan tombolnya dua kali.
 *
 * Menghitung SELURUH siklus produksi tetap lebih baik lewat `php artisan pa:hitung-siklus`;
 * fungsi ini untuk siklus kecil dan hitung ulang sebagian.
 */
export async function hitungSiklusSampaiTuntas(cycleId, { batas = 10, onKemajuan = null } = {}) {
    let mulaiDariId = null;
    const kumpulan = { dihitung: 0, tertahan: 0, diproses: 0, gagal: [] };

    // Batas keras pada jumlah PUTARAN, bukan hanya pada `selesai`. Server yang menjawab
    // `selesai: false` tanpa memajukan `terakhir_id` akan membuat gelung ini abadi, dan
    // gelung abadi di browser membekukan tabnya — lebih buruk daripada gagal.
    for (let putaran = 0; putaran < 500; putaran++) {
        const r = await hitungSiklus(cycleId, { batas, mulaiDariId });
        const hasil = r?.result ?? r;

        kumpulan.dihitung += hasil?.dihitung ?? 0;
        kumpulan.tertahan += hasil?.tertahan ?? 0;
        kumpulan.diproses += hasil?.diproses ?? 0;
        if (Array.isArray(hasil?.gagal)) kumpulan.gagal.push(...hasil.gagal);

        if (onKemajuan) onKemajuan({ ...kumpulan, putaran: putaran + 1 });

        if (hasil?.selesai || hasil?.terakhir_id == null || hasil.terakhir_id === mulaiDariId) {
            kumpulan.selesai = true;

            return kumpulan;
        }

        mulaiDariId = hasil.terakhir_id;
    }

    kumpulan.selesai = false;
    kumpulan.pesan = 'Berhenti setelah 500 potongan. Pakai `php artisan pa:hitung-siklus` untuk siklus sebesar ini.';

    return kumpulan;
}

/**
 * `nilai_kpi` dan `final_score` BOLEH melebihi 100 — sisi KPI berjalan sampai 125.
 * Jangan memakai 100 sebagai batas bar atau sumbu grafik.
 */
export function hitungPeserta(participantId) {
    return panggil('post', `/participants/${participantId}/hitung`);
}

/* ------------------------------------------------------------------ *
 * Admin Performance Appraisal — master data                           *
 * ------------------------------------------------------------------ */

/**
 * Seluruh daftar acuan untuk dropdown, SATU panggilan.
 *
 * Berisi: level, jenis kompetensi, kategori penilai, jenis relasi organisasi, status
 * aturan, sumbu, kegunaan, kompetensi, jenis catatan, dan siklus. Semuanya kecil dan
 * jarang berubah — ambil sekali saat layar admin dibuka, jangan per layar.
 */
export function masterReferensi() {
    return panggil('get', '/master/referensi');
}

/**
 * Satu halaman baris master. Bentuk responsnya SAMA untuk kedelapan master.
 *
 * `dapat_ditambah` dan `dapat_dinonaktifkan` datang dari server — pakai keduanya untuk
 * menghidupkan tombol, jangan dihafal di layar. Kategori penilai dan kuadran HAV adalah
 * kamus tertutup; keduanya hanya bisa disunting, tidak ditambah maupun dinonaktifkan.
 *
 * `lingkup` menyebut apa yang wajib disertakan: `master` bebas, `siklus` wajib
 * `cycle_id`, `paket` wajib `package_id`. Tanpa itu server MENOLAK, bukan mengembalikan
 * daftar bercampur.
 *
 * @param {{q?: string, flag_aktif?: 'Y'|'T', cycle_id?: number, package_id?: number,
 *          id_level?: number, sumbu?: string, kegunaan?: string,
 *          halaman?: number, per_halaman?: number}} saringan
 */
export function masterDaftar(master, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/master/${master}${q ? `?${q}` : ''}`);
}

/**
 * Satu baris master.
 *
 * Untuk `aturan-penilai` ikut membawa `detail[]` — rincian bobot per kategori.
 * Untuk `paket-soal` ikut membawa `butir[]`.
 */
export function masterDetail(master, id) {
    return panggil('get', `/master/${master}/${id}`);
}

export function masterSimpan(master, data) {
    return panggil('post', `/master/${master}`, data);
}

export function masterPerbarui(master, id, data) {
    return panggil('put', `/master/${master}/${id}`, data);
}

/**
 * MENONAKTIFKAN, bukan menghapus. Barisnya dirujuk salinan siklus dan jejak hasil yang
 * sudah tersimpan; menghapusnya membuat hasil lama menunjuk sesuatu yang tak ada lagi.
 *
 * Karena itu POST, bukan DELETE — memakai DELETE akan menjanjikan hal yang tidak
 * dilakukan.
 */
export function masterNonaktif(master, id) {
    return panggil('post', `/master/${master}/${id}/nonaktif`);
}

/** Mengaktifkan kembali baris master; balasan memuat `peringatan[]` untuk ditampilkan. */
export function masterAktif(master, id) {
    return panggil('post', `/master/${master}/${id}/aktif`);
}

/**
 * Menyimpan SELURUH rincian kategori satu aturan penilai sekaligus.
 *
 * Sengaja tidak per baris: bobot antar kategori wajib berjumlah 100, dan jumlah itu
 * hanya bisa diperiksa ketika seluruh anggotanya diketahui. Menyimpan satu baris pada
 * satu waktu berarti setiap simpan di tengah pekerjaan pasti melanggar aturannya sendiri.
 *
 * @param {Array<{pa_rater_category_id: number, bobot_persen: number, min_rater?: number,
 *                maks_rater?: number|null, min_rater_selesai?: number|null,
 *                organization_relationship_type_id?: number|null,
 *                flag_izinkan_manual?: 'Y'|'T', urutan_tampil?: number,
 *                catatan_aturan?: string|null}>} rincian
 */
export function simpanRincianAturan(policyId, rincian) {
    return panggil('put', `/aturan-penilai/${policyId}/rincian`, { rincian });
}

/**
 * Mengaktifkan satu versi aturan, dan menonaktifkan versi aktif sebelumnya pada level
 * yang sama. Ditolak bila bobot rinciannya belum berjumlah 100.
 */
export function aktifkanAturan(policyId) {
    return panggil('post', `/aturan-penilai/${policyId}/aktifkan`);
}


/* ==================================================================== *
 * Penyiapan siklus (A09)                                                *
 * ==================================================================== */

/**
 * Detail satu siklus beserta kesiapan dan operasi yang boleh dijalankan.
 *
 * Pakai `boleh_ubah_periode` dari server untuk mengunci bagian formulirnya — JANGAN
 * disimpulkan dari `Status_Cycle`. Aturannya milik server, dan yang berselisih di sini
 * menentukan apakah sebuah formulir boleh disunting. Tampilkan `alasan_periode_terkunci`
 * apa adanya bila terisi.
 */
export function detailSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}`);
}

/**
 * Membuat siklus. Selalu lahir `DRAFT` — status tidak diterima dari kiriman.
 *
 * @param {{kode_cycle: string, nama_cycle: string, tahun: number, deskripsi?: string,
 *          periode_dinilai_dari?: string, periode_dinilai_sampai?: string,
 *          pengisian_dari?: string, pengisian_sampai?: string,
 *          tanggal_acuan_organisasi?: string}} data
 */
export function buatSiklus(data) {
    return panggil('post', '/cycles', data);
}

/**
 * Menyunting siklus. Kirim hanya yang berubah.
 *
 * DUA TINGKAT: `nama_cycle`/`deskripsi` boleh kapan saja; `tahun`, `periode_dinilai_*`,
 * `pengisian_*`, dan `tanggal_acuan_organisasi` hanya saat DRAFT/PREPARED — periode
 * menentukan baris KPI yang ditarik tiap peserta, jadi mengubahnya sesudah kunci
 * menggeser nilai orang tanpa jejak.
 *
 * Tampilkan `perlu_siapkan_ulang` bila `true`: peserta yang sudah terbentuk memakai
 * salinan organisasi pada tanggal acuan LAMA, dan tak ada yang akan memberi tahu kalau
 * layar diam.
 */
export function perbaruiSiklus(cycleId, data) {
    return panggil('put', `/cycles/${cycleId}`, data);
}

/**
 * Lini masa audit satu siklus — siapa mengubah apa, kapan, dan kenapa.
 *
 * Pakai `oleh_sistem` untuk memisahkan **aturan yang menyala** dari **keputusan
 * seseorang**: satu kali penyiapan melahirkan puluhan pengecualian sekaligus, dan tanpa
 * pemisah itu keputusan manusia tenggelam di baliknya. Sediakan penyaringnya, jangan
 * menyaring diam-diam.
 *
 * `cacah` selalu memuat kedua angka meski disaring — layar yang menampilkan keputusan
 * manusia saja tetap harus bisa berkata "dan sekian lainnya lahir dari aturan".
 *
 * Tampilkan `batas.catatan`: perubahan konfigurasi hanya menyisakan waktu dan pelakunya,
 * nilai sebelumnya TIDAK disimpan.
 *
 * @param {{jenis?: string, participant_id?: number, q?: string,
 *          hanya_keputusan_manusia?: boolean, halaman?: number, per_halaman?: number}} saringan
 */
export function auditSiklus(cycleId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/cycles/${cycleId}/audit${q ? `?${q}` : ''}`);
}

/* ==================================================================== *
 * Laporan admin — seluruhnya BACA.                                      *
 *                                                                       *
 * Angka desimal dari kolom database tiba sebagai STRING; field TURUNAN   *
 * (persen, rata-rata, simpangan baku) sudah berupa angka sungguhan.      *
 * ==================================================================== */

/**
 * Dashboard admin satu siklus.
 *
 * `penyelesaian_persen` sudah dibulatkan server. Jumlah `per_divisi` selalu sama dengan
 * `penugasan.total` — peserta tanpa divisi muncul sebagai "(Tanpa divisi)", tidak dibuang.
 */
export function ringkasanSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}/ringkasan`);
}

/**
 * Peta panas per divisi dan daftar penugasan terlambat.
 *
 * `peta_panas.baris[].sel` selalu selengkap `peta_panas.status` — nol pun ditulis, jadi
 * indeksnya bisa dipakai langsung tanpa memeriksa keberadaan sel.
 *
 * `pilihan_saringan` berisi yang benar-benar ada di siklus ini, bukan seluruh master.
 * Pakai itu untuk mengisi dropdown; master akan memuat divisi yang tak seorang pun
 * menempatinya, dan orang yang menyaringnya akan menatap tabel kosong.
 *
 * @param {{id_divisi?: number, id_level?: number, pa_rater_category_id?: number,
 *          status?: string, q?: string, halaman?: number, per_halaman?: number}} saringan
 */
/**
 * Isi satu keranjang keputusan admin.
 *
 * @param {number|string} cycleId
 * @param {'siap_dihitung'|'tertahan'|'perlu_verifikasi'|'terbit'|'kiriman_susulan'} jenis
 * @param {{halaman?: number, per_halaman?: number, q?: string}} saringan
 *        `q` dicari di SERVER (nama atau NIK) atas seluruh isi keranjang, bukan halaman ini saja.
 */
export function keranjangSiklus(cycleId, jenis, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries({ jenis, ...saringan }).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/cycles/${cycleId}/keranjang?${q}`);
}

/**
 * Menghitung ulang peserta yang TERTAHAN di satu siklus.
 *
 * Sebab tertahan hampir selalu di luar modul PA — KPI belum disetujui, akun KPI belum ada —
 * dan tidak ada yang memberi tahu PA ketika sebab itu hilang. Ini cara bertanya ulang.
 */
/** Hitung ulang bercicil: `mulaiDariId` = `terakhir_id` cicilan sebelumnya; `jenis` TERTAHAN | SIAP. */
export function sapuTertahan(cycleId, batas = 50, { mulaiDariId = null, jenis = 'TERTAHAN' } = {}) {
    return panggil('post', `/cycles/${cycleId}/sapu-tertahan`, {
        batas,
        jenis,
        ...(mulaiDariId !== null && mulaiDariId !== undefined ? { mulai_dari_id: mulaiDariId } : {}),
    });
}

/** Pemantauan per penilai: `{ baris: [{ kode_karyawan, nama, total, selesai, persen, status }], total, … }`. */
export function monitoringPenilai(cycleId, saringan = {}) {
    const q = new URLSearchParams(Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== '')).toString();
    return panggil('get', `/cycles/${cycleId}/monitoring/penilai${q ? `?${q}` : ''}`);
}

/** Orang-orang yang dinilai satu penilai: `{ baris }` (bentuk sama dengan daftar penugasan). */
export function rincianPenilaiMonitoring(cycleId, kodePerusahaan, kodeKaryawan) {
    const q = new URLSearchParams({ kode_perusahaan: kodePerusahaan, kode_karyawan: kodeKaryawan });
    return panggil('get', `/cycles/${cycleId}/monitoring/penilai/rincian?${q.toString()}`);
}

export function monitoringSiklus(cycleId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/cycles/${cycleId}/monitoring${q ? `?${q}` : ''}`);
}

/**
 * Sebaran nilai akhir: grade, kuadran, histogram, dan statistik per tingkat jabatan.
 *
 * **Jangan menggambar kurva ketika `cukup_untuk_kurva` bernilai false.** Rata-rata dan
 * simpangan bakunya tetap benar dan boleh ditampilkan sebagai angka; yang ditahan hanya
 * penggambarannya, karena kurva lonceng di atas segelintir orang terlihat meyakinkan
 * tentang sesuatu yang tidak diketahui. Tampilkan `alasan_kurva_ditahan`.
 *
 * Hasil yang belum terbit IKUT dihitung — layar ini dipakai justru sebelum penerbitan.
 * Tampilkan `asal_angka` supaya pembacanya tahu angka itu dari status mana.
 *
 * @param {{id_level?: number, id_divisi?: number}} saringan
 */
export function distribusiSiklus(cycleId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/cycles/${cycleId}/distribusi${q ? `?${q}` : ''}`);
}

/**
 * URL untuk mengunduh berkas PDF Laporan Eksekutif Distribusi Performance untuk Manajemen.
 *
 * @param {number|string} cycleId
 * @param {{id_level?: number, id_divisi?: number}} [saringan]
 * @returns {string}
 */
export function exportDistribusiPdfUrl(cycleId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return `/api/v1/performance-appraisal/cycles/${cycleId}/distribusi/export-pdf${q ? `?${q}` : ''}`;
}

/**
 * Riwayat penilaian lintas siklus — versi ADMIN.
 *
 * Bukan versi admin dari `riwayatSaya()`: yang ini memuat hasil yang belum terbit dan
 * yang tertahan. Jangan memakainya di layar karyawan.
 *
 * Peserta yang belum punya hasil sama sekali tetap muncul dengan kolom hasil `null` —
 * merekalah pekerjaan yang belum selesai.
 *
 * `tren` diurut terlama lebih dulu; tabelnya terbaru lebih dulu. Itu disengaja.
 *
 * @param {{tahun?: number, cycle_id?: number, id_divisi?: number, id_level?: number,
 *          kode_grade?: string, status_hasil?: string, q?: string,
 *          halaman?: number, per_halaman?: number}} saringan
 */
export function riwayatAdmin(saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/riwayat${q ? `?${q}` : ''}`);
}

/* ==================================================================== *
 * Bobot pilar per siklus (K23) dan penyuntingan peserta                 *
 * ==================================================================== */

/**
 * Bobot pilar seluruh level pada satu siklus, beserta dampak penyuntingannya.
 *
 * Pakai `dapat_disunting` dan `alasan_terkunci` dari server untuk menonaktifkan baris
 * SEBELUM orang menyunting. Jangan menyimpulkannya sendiri dari `hasil_terbit` — aturannya
 * milik server, dan layar yang menyimpulkannya akan berselisih begitu aturannya berubah.
 */
export function bobotPilarSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}/bobot-pilar`);
}

/**
 * Menyunting bobot pilar satu level.
 *
 * Balasannya memuat `hasil_perlu_hitung_ulang`. Kalau lebih dari nol, tawarkan hitung
 * ulang di tempat: baris itu lahir dari bobot yang lama, dan menerbitkannya berarti
 * mengumumkan angka yang tidak sesuai bobot siklusnya.
 *
 * @param {{bobot_kpi: number, bobot_kompetensi: number, alasan?: string}} nilai
 */
export function simpanBobotPilar(cycleId, levelId, nilai) {
    return panggil('put', `/cycles/${cycleId}/bobot-pilar/${levelId}`, nilai);
}

/**
 * Mengubah status keikutsertaan satu peserta.
 *
 * Hanya `DIIKUTKAN` dan `DIKECUALIKAN`. **Jangan menaruh `TERTUNDA` di dropdown** — itu
 * keadaan, bukan pilihan, dan menetapkannya dengan tangan mengarang penghalang yang
 * kemudian menolak penguncian siklus.
 *
 * Tampilkan `penugasan_dibatalkan`: mengecualikan orang ikut membatalkan penugasan
 * penilaian atasnya, dan admin berhak tahu berapa banyak yang terhapus dari daftar orang.
 */
export function ubahStatusPeserta(participantId, status, alasan = '') {
    return panggil('put', `/participants/${participantId}/status`, { status, alasan });
}

/**
 * Mengubah status banyak peserta sekaligus.
 *
 * Yang gagal TIDAK menghentikan sisanya — tampilkan `rincian_gagal` apa adanya.
 */
export function ubahStatusPesertaBanyak(participantIds, status, alasan = '') {
    return panggil('post', '/participants/status-massal', { participant_ids: participantIds, status, alasan });
}

/* ==================================================================== *
 * Sisi peserta — dipakai SELURUH karyawan, bukan admin.                 *
 *                                                                       *
 * Tidak satu pun fungsi di bawah menerima id peserta, dan jangan        *
 * ditambahkan. Servernya menentukan lingkup dari user yang login; jika   *
 * suatu saat sebuah layar butuh melihat orang lain, itu jalur admin di   *
 * atas, yang punya gerbangnya sendiri.                                  *
 *                                                                       *
 * ANGKA DATANG SEBAGAI STRING. Kolom decimal SQL Server tiba lewat       *
 * driver sebagai "82.50", bukan 82.5. Di JavaScript "9.00" > "82.50"     *
 * bernilai TRUE, jadi Number() dulu sebelum membandingkan, mengurutkan,  *
 * atau menjumlahkan. Field turunan (progres_persen, rata_rata, terbaik,  *
 * perubahan_terakhir) sudah berupa angka sungguhan dari server.          *
 * ==================================================================== */

/**
 * Enam penghitung dashboard peserta beserta progres pengisian.
 *
 * `progres_persen` sudah dibulatkan server; jangan dihitung ulang dari
 * `sudah_selesai / total_assignment` — dua pembulatan untuk satu gagasan akan berselisih
 * dan tidak ada yang bisa menjelaskan mana yang benar.
 *
 * @param {number|null} cycleId Mempersempit ke satu siklus. Tetap hanya milik yang login.
 */
export function ringkasanSaya(cycleId = null) {
    return panggil('get', `/saya/ringkasan${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/**
 * Progres saya per KATEGORI penilai, atas seluruh tugas — bukan satu halaman.
 *
 * Terpisah dari `assignmentSaya()` dengan sengaja. Yang itu berpaginasi 25; agregat yang
 * diturunkan darinya akan melaporkan sebagian sebagai seluruhnya — kelompok "BAWAHAN 3 tugas"
 * yang sebenarnya 40, tanpa satu pun tanda bahwa angkanya sepotong.
 *
 * Persentasenya datang dari server, tidak dihitung di layar: dua pembulatan untuk satu gagasan
 * akan berselisih dan tidak ada yang bisa menjelaskan mana yang benar.
 *
 * @param {number|null} cycleId Mempersempit ke satu siklus. Tetap hanya milik yang login.
 */
export function progresKategoriSaya(cycleId = null) {
    return panggil('get', `/saya/progres-kategori${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/**
 * Penugasan tempat SAYA menjadi penilai — bukan tempat saya dinilai.
 *
 * Keduanya mudah tertukar: yang pertama adalah pekerjaannya, yang kedua hasilnya.
 * Urut tenggat terdekat lebih dulu; yang tanpa tenggat jatuh ke belakang.
 *
 * @param {{status?: string, q?: string, cycle_id?: number,
 *          halaman?: number, per_halaman?: number}} saringan
 */
export function assignmentSaya(saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/saya/assignment${q ? `?${q}` : ''}`);
}

/**
 * Hasil penilaian saya — hanya yang sudah TERBIT.
 *
 * `tersedia: false` datang sebagai **200, bukan galat**: belum terbit adalah keadaan yang
 * sah dan sering. Tampilkan `pesan` apa adanya, jangan layar galat.
 *
 * Sengaja tanpa nilai per kategori penilai, dan catatannya tanpa nama penulis. Itu bukan
 * field yang terlupa; peserta memang tidak pernah melihatnya.
 */
export function hasilSaya(cycleId = null) {
    return panggil('get', `/saya/hasil${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/**
 * Tren lintas siklus.
 *
 * `periode` urut **terlama lebih dulu** — tren dibaca kiri ke kanan. Jangan dibalik di
 * layar; itu satu langkah yang mudah terlupa di salah satu dari dua layar yang memakainya.
 *
 * `perubahan_terakhir` bernilai null bila periodenya baru satu. Itu orang baru, bukan
 * kesalahan — tampilkan sebagai tanda hubung, bukan 0.
 */
/**
 * Kemajuan pengumpulan saya per siklus — dasar tombol "Kirim semua penilaian".
 *
 * @returns {Promise<Array<{cycle_id:number, kode_cycle:string, nama_cycle:string,
 *   total:number, terkumpul:number, terkirim:number, sisa:number, persen:number,
 *   bisa_kirim:boolean, sudah_terkirim_semua:boolean}>>}
 */
export function kemajuanPengirimanSaya(cycleId = null) {
    return panggil('get', `/saya/kemajuan-pengiriman${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/**
 * Mengirim SELURUH penilaian saya di satu siklus sekaligus.
 *
 * `cycleId` wajib dan tidak punya nilai baku di sini maupun di server: pengiriman tidak
 * bisa ditarik kembali, dan siklus yang ditebak berarti satu tekanan tombol bisa mengunci
 * siklus yang bukan yang sedang dilihat orangnya.
 */
export function kirimSemuaPenilaianSaya(cycleId) {
    return panggil('post', '/saya/kirim-semua', { cycle_id: cycleId });
}

/** Kerangka wizard tugas penilaian saya. `langkah_aktif` ditentukan server. */
export function wizardPenilaianSaya(cycleId = null) {
    return panggil('get', `/saya/wizard${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/** Lembar satu langkah. Langkah terkunci datang tanpa tautan maupun kode akses. */
export function lembarLangkahSaya(kode, cycleId = null) {
    return panggil(
        'get',
        `/saya/wizard/langkah/${encodeURIComponent(kode)}/lembar${cycleId ? `?cycle_id=${cycleId}` : ''}`,
    );
}

/** Pratinjau Kalibrasi Penilai satu langkah: matriks orang × kompetensi. */
export function pratinjauLangkahSaya(kode, cycleId = null) {
    return panggil(
        'get',
        `/saya/wizard/langkah/${encodeURIComponent(kode)}/pratinjau${cycleId ? `?cycle_id=${cycleId}` : ''}`,
    );
}

/**
 * Menyimpan Kalibrasi Penilai atas satu orang — skor, esai, atau keduanya dengan satu alasan;
 * mengembalikan pratinjau kategorinya utuh.
 */
export function simpanKalibrasiPenilai({ assignmentId, perubahan = [], perubahanJawaban = [], alasan }) {
    return panggil('post', '/saya/kalibrasi', {
        assignment_id: assignmentId,
        perubahan,
        perubahan_jawaban: perubahanJawaban,
        alasan,
    });
}

/** Admin: menata urutan pengisian seluruh kategori aktif sekaligus. */
export function tataUrutanKategoriPenilai(urutan) {
    return panggil('put', '/master/jenis-penilai/urutan-kategori', { urutan });
}

/** Admin: menyalakan/mematikan Kalibrasi Penilai untuk satu kategori. */
export function setelBolehKalibrasiKategori(kategoriId, boleh) {
    return panggil('put', `/master/jenis-penilai/kategori/${kategoriId}/kalibrasi`, { boleh });
}

/* ------------------------------------------------------------------ *
 * Kurva grade (siklus bermetode KURVA)                                *
 * ------------------------------------------------------------------ */

/** Kurva grade siklus: per grup n, rata-rata, simpangan baku, pita, sebaran, peringatan. */
/** Rincian perhitungan kurva satu grup: anggota, langkah μ/σ, dan turunan batas grade. */
export function kurvaGrupDetail(cycleId, grupId) {
    return panggil('get', `/cycles/${cycleId}/kurva/grup/${grupId}`);
}

export function kurvaSiklus(cycleId) {
    return panggil('get', `/cycles/${cycleId}/kurva`);
}

/**
 * Membekukan kurva per grup level dan memberi grade seluruh hasil DIHITUNG. `alasan` wajib
 * bila server melaporkan `boleh_kunci_dengan_alasan` (masih ada hasil belum dihitung/tertahan).
 */
export function kunciKurva(cycleId, alasan) {
    return panggil('post', `/cycles/${cycleId}/kurva/kunci`, alasan ? { alasan } : undefined);
}

/** Membuka kurva terkunci; alasan wajib, ditolak server bila sudah ada hasil terbit. */
export function bukaKurva(cycleId, alasan) {
    return panggil('post', `/cycles/${cycleId}/kurva/buka`, { alasan });
}

/** Memindah metode grade siklus (PITA atau KURVA) sebelum kunci dan sebelum ada terbit. */
export function ubahMetodeGrade(cycleId, metode) {
    return panggil('put', `/cycles/${cycleId}/kurva/metode`, { metode });
}

/** Mengganti seluruh grup kurva SALINAN siklus (selama belum dikunci). */
export function aturGrupKurvaSiklus(cycleId, grup) {
    return panggil('put', `/cycles/${cycleId}/kurva/grup`, { grup });
}

/** Master Grup Level Kurva. */
export function daftarGrupKurva() {
    return panggil('get', '/master/grup-kurva');
}

/** Menyimpan SELURUH konfigurasi master grup level kurva sekaligus. */
export function simpanGrupKurva(grup) {
    return panggil('put', '/master/grup-kurva', { grup });
}

/**
 * ADMIN — melepas kunci pengiriman seorang penilai tanpa menunggu ia menekan kirim.
 */
export function lepasPaksaPenilai(cycleId, { kodePerusahaan, kodeKaryawan, alasan }) {
    return panggil('post', `/cycles/${cycleId}/lepas-paksa-penilai`, {
        kode_perusahaan: kodePerusahaan,
        kode_karyawan: kodeKaryawan,
        alasan,
    });
}

export function riwayatSaya() {
    return panggil('get', '/saya/riwayat');
}

/**
 * L01 — anggota tim saya pada satu siklus, berikut kemajuan dan penahanannya.
 *
 * Tanpa `cycleId`, server memilih siklus TERBARU YANG MEMUAT tim ini — bukan sekadar
 * siklus terbaru. Jangan menirunya di layar dengan mengambil siklus pertama dari daftar
 * siklus; keduanya sering berbeda, dan yang berbeda itu menyajikan layar kosong yang
 * terbaca seperti kesalahan.
 *
 * Tiga hal yang menentukan tampilannya:
 *
 * 1. `tanpa_tim: true` berarti orang ini memang bukan leader — bukan galat, dan bukan
 *    "belum ada data". Tampilkan keterangannya, jangan spinner yang tak pernah berhenti.
 * 2. `nilai_akhir`, `kode_grade`, dan `kode_kuadran` bernilai null selama `terbit: false`.
 *    Itu "belum boleh dilihat", bukan "belum dihitung", dan keduanya SENGAJA tidak
 *    dibedakan — membedakannya sudah membocorkan bahwa angkanya sebenarnya sudah ada.
 * 3. `alasan_tertahan` tetap terisi meski belum terbit. Itu pekerjaan yang menunggu
 *    seseorang, dan leader adalah orang yang paling mungkin menindaklanjutinya.
 */
export function timLeader(cycleId = null) {
    return panggil('get', `/leader/tim${cycleId ? `?cycle_id=${cycleId}` : ''}`);
}

/**
 * L02 — hasil satu anggota tim, sebatas yang boleh dilihat leader.
 *
 * TIDAK PERNAH ADA BARIS PER PENILAI, dan itu bukan field yang terlupa. Yang ada hanya
 * agregat dan rincian per KATEGORI penilai.
 *
 * Rincian kategori pun bisa disembunyikan: bila `disembunyikan: true`, `nilai` bernilai
 * null dan `alasan_disembunyikan` menjelaskan kenapa. **Barisnya tetap ditampilkan** —
 * jangan disaring keluar. Kategori yang lenyap tanpa keterangan membuat leader mengira
 * bagian penilaian itu tidak ada, padahal ada dan memang tidak boleh ia lihat.
 *
 * Ambangnya ada di `batas.min_penilai_untuk_tampil`; baca dari sana, jangan ditulis
 * sebagai angka tetap di layar.
 */
export function detailAnggotaLeader(participantId) {
    return panggil('get', `/leader/anggota/${participantId}`);
}

/* ------------------------------------------------------------------ *
 * Balancing (spec 2026-09-30)                                         *
 * ------------------------------------------------------------------ */

function queryBalancing(saringan = {}) {
    const q = new URLSearchParams();
    for (const [kunci, nilai] of Object.entries(saringan || {})) {
        if (Array.isArray(nilai)) nilai.forEach((v) => q.append(`${kunci}[]`, v));
        else if (nilai !== null && nilai !== undefined && nilai !== '') q.append(kunci, nilai);
    }
    const s = q.toString();
    return s ? `?${s}` : '';
}

/** Sesi Balancing satu siklus: `{ sesi[], menunggu_hitung_ulang, terbuka }`. */
export function daftarSesiBalancing(cycleId) {
    return panggil('get', `/cycles/${cycleId}/balancing/sesi`);
}

/** Kandidat peserta; yang tidak layak TETAP dikirim beserta `alasan`. */
export function kandidatBalancing(cycleId, saringan = {}) {
    return panggil('get', `/cycles/${cycleId}/balancing/kandidat${queryBalancing(saringan)}`);
}

/** Calon tim penilai: semua karyawan aktif, lengkap dengan jabatan dari master golongan. */
export function timPenilaiBalancing() {
    return panggil('get', '/balancing/tim-penilai');
}

export function buatSesiBalancing(cycleId, data) {
    return panggil('post', `/cycles/${cycleId}/balancing/sesi`, data);
}

export function detailSesiBalancing(sesiId) {
    return panggil('get', `/balancing/sesi/${sesiId}`);
}

/** `data = { versi_data, peserta[], catatan_rapat? }`; 409 `PA_BAL_VERSI_BERUBAH` bila basi. */
export function simpanSesiBalancing(sesiId, data) {
    return panggil('put', `/balancing/sesi/${sesiId}`, data);
}

export function finalkanSesiBalancing(sesiId, versiData) {
    return panggil('post', `/balancing/sesi/${sesiId}/final`, { versi_data: versiData });
}

export function batalkanSesiBalancing(sesiId, alasan) {
    return panggil('post', `/balancing/sesi/${sesiId}/batal`, { alasan });
}

/** Simulasi tanpa menyimpan; `peserta` = isian layar yang belum disimpan. */
export function simulasiBalancing(sesiId, peserta) {
    return panggil('post', `/balancing/sesi/${sesiId}/simulasi`, { peserta });
}

export function hitungUlangBalancing(cycleId) {
    return panggil('post', `/cycles/${cycleId}/balancing/hitung-ulang`);
}

/** Soal kualitatif + jawaban tiap penilai (hanya Strength & Development Area) untuk modal board. */
export function kualitatifBalancing(participantId) {
    return panggil('get', `/participants/${participantId}/balancing/kualitatif`);
}

export function riwayatBalancing(participantId) {
    return panggil('get', `/participants/${participantId}/balancing`);
}

/** Tahap Kesimpulan Kualitatif: daftar peserta berjawaban kualitatif (saringan & paginasi server). */
export function daftarKesimpulanKualitatif(cycleId, saringan = {}) {
    const q = new URLSearchParams(
        Object.entries(saringan).filter(([, v]) => v !== null && v !== undefined && v !== ''),
    ).toString();

    return panggil('get', `/cycles/${cycleId}/kesimpulan-kualitatif${q ? `?${q}` : ''}`);
}

/** Jawaban penilai per kategori kualitatif + kesimpulan yang berlaku, untuk satu peserta. */
export function ruangKerjaKualitatif(participantId) {
    return panggil('get', `/participants/${participantId}/kesimpulan-kualitatif`);
}

/** Lini masa angka hasil satu peserta: tiap hitung, kurva, kalibrasi, terbit — kapan, siapa, berapa ke berapa. */
export function riwayatHasil(participantId) {
    return panggil('get', `/participants/${participantId}/hasil/riwayat`);
}

export default {
    DAFTAR_MASTER,
    masterReferensi,
    masterDaftar,
    masterDetail,
    masterSimpan,
    masterPerbarui,
    masterNonaktif,
    masterAktif,
    simpanRincianAturan,
    aktifkanAturan,
    PaApiError,
    daftarSiklus,
    siapkanSiklus,
    ringkasanPraKunci,
    kunciSiklus,
    progresPenugasan,
    bentukPenugasanMassal,
    ringkasanPenjadwalan,
    jadwalkanPenilaian,
    tarikHasilPenjadwalan,
    mulaiPembentukanLatar,
    statusPembentukanLatar,
    ulangiPembentukanLatar,
    hentikanPembentukanLatar,
    mulaiPenjadwalanLatar,
    statusPenjadwalanLatar,
    ulangiPenjadwalanLatar,
    hapusJadwalDibatalkan,
    pratinjauRevisiPeserta,
    jalankanRevisiPeserta,
    cariRevisiPeserta,
    pratinjauRevisiMassal,
    mulaiRevisiMassal,
    statusRevisiMassal,
    kirimanPerluDitinjau,
    petakanUlangKiriman,
    cetakUlangPintu,
    jadwalUlangPintu,
    bukaPengisian,
    lembarPenilaian,
    kirimPenilaian,
    tutupPengisian,
    alurLangkah,
    pratinjauPenyiapan,
    perubahanPeserta,
    segarkanLevelTertahan,
    keadaanSetup,
    pindahLangkahSetup,
    draftLangkahSetup,
    katalogPaketSoalMaster,
    matriksIkatanBaku,
    ambilBagianUjian,
    ambilLabelIkatan,
    periksaSelIkatan,
    simpanIkatanBaku,
    cabutIkatanBaku,
    verifikasiIkatanBaku,
    terimaUsulanPaketSoal,
    salinPaketSoalDariMaster,
    matriksSyaratKompetensi,
    matriksKompetensi,
    simpanMatriksKompetensi,
    daftarLevelPa,
    daftarLevelMaster,
    tambahLevelMaster,
    periksaLevelMaster,
    sinkronLevelMaster,
    ubahLevelMaster,
    aturAktifLevelMaster,
    cariKaryawanLevelPa,
    calonLevelPa,
    simpanLevelPa,
    ubahLevelPa,
    cabutLevelPa,
    imporLevelPa,
    alamatEksporLevelPa,
    tingkatKompetensi,
    simpanTingkatKompetensi,
    matriksJenisPenilai,
    setelSelJenisPenilai,
    kecualikanLevelPa,
    kecualikanBanyakLevelPa,
    kelayakanMasaKerjaMaster,
    simpanKelayakanMasaKerjaMaster,
    cabutPengecualianLevelPa,
    cabutBanyakPengecualianLevelPa,
    simpanKomposisiLevel,
    simpanSyaratKompetensi,
    kesehatanPenugasan,
    penugasanBermasalah,
    matriksPaketSoal,
    ikatPaketSoal,
    katalogPaketSoal,
    imporPaketSoal,
    bagianPaketSoalSiklus,
    labelPaketSoalSiklus,
    levelSiklus,
    kesiapanKaryawanHcLearn,
    sinkronkanKaryawanHcLearn,
    cariKaryawanPraKecuali,
    opsiFilterKaryawanPraKecuali,
    daftarAturanPenilai,
    salinAturanPenilai,
    simpanAturanPenilai,
    daftarPraKecuali,
    kelayakanMasaKerjaSiklus,
    simpanKelayakanMasaKerjaSiklus,
    tambahPraKecuali,
    cabutPraKecuali,
    cabutPraKecualiBanyak,
    keranjangTertunda,
    daftarPeserta,
    havMap,
    simpanOverridePenilai,
    tandaiKategoriTidakDiikutkan,
    batalkanKategoriTidakDiikutkan,
    simpanOverridePilar,
    simpanCatatanHasil,
    simpanOverrideSyarat,
    cabutOverride,
    ringkasanPenerbitan,
    periksaKesimpulanSebelumTerbit,
    terbitkan,
    terbitkanPeserta,
    rilisLeader,
    rilisLeaderPeserta,
    cabutRilisLeader,
    tutupPenerbitan,
    penutupanSiklus,
    detailHasil,
    kalibrasi,
    usulanPenilai,
    tugaskanPenilai,
    batalkanPenugasan,
    simpanBobotPenilai,
    kembalikanBobotRata,
    cariCalonPenilai,
    tambahPenilaiDenganRelasi,
    hitungSiklus,
    mulaiHitungLatar,
    statusHitungLatar,
    ulangiHitungLatar,
    hentikanHitungLatar,
    pratinjauHitungSiklus,
    hitungSiklusSampaiTuntas,
    hitungPeserta,
    auditSiklus,
    detailSiklus,
    buatSiklus,
    perbaruiSiklus,
    ringkasanSiklus,
    monitoringSiklus,
    monitoringPenilai,
    rincianPenilaiMonitoring,
    keranjangSiklus,
    sapuTertahan,
    distribusiSiklus,
    exportDistribusiPdfUrl,
    riwayatAdmin,
    bobotPilarSiklus,
    simpanBobotPilar,
    ubahStatusPeserta,
    ubahStatusPesertaBanyak,
    ringkasanSaya,
    progresKategoriSaya,
    assignmentSaya,
    hasilSaya,
    kemajuanPengirimanSaya,
    kirimSemuaPenilaianSaya,
    wizardPenilaianSaya,
    lembarLangkahSaya,
    pratinjauLangkahSaya,
    simpanKalibrasiPenilai,
    tataUrutanKategoriPenilai,
    setelBolehKalibrasiKategori,
    kurvaSiklus,
    kurvaGrupDetail,
    kunciKurva,
    bukaKurva,
    ubahMetodeGrade,
    aturGrupKurvaSiklus,
    daftarGrupKurva,
    simpanGrupKurva,
    lepasPaksaPenilai,
    riwayatSaya,
    timLeader,
    detailAnggotaLeader,
    daftarSesiBalancing,
    kandidatBalancing,
    timPenilaiBalancing,
    buatSesiBalancing,
    detailSesiBalancing,
    simpanSesiBalancing,
    finalkanSesiBalancing,
    batalkanSesiBalancing,
    simulasiBalancing,
    hitungUlangBalancing,
    kualitatifBalancing,
    riwayatBalancing,
    riwayatHasil,
    daftarKesimpulanKualitatif,
    ruangKerjaKualitatif,
};
