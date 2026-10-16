/**
 * Sumbu pengelompokan & saringan untuk SOP Builder (daftar paket).
 *
 * Modul: LMS · Submodul: SOP · Fitur: Governance/Admin · Lapisan: Support (frontend).
 *
 * CERMIN DARI `Shared/components/listing/sumbu.js`, BUKAN SALINANNYA. Keduanya
 * menjawab pertanyaan yang sama bentuknya — "kelompokkan dan saring daftar ini"
 * — tetapi atas dua benda yang berbeda. Satu baris di SOP Saya adalah TUGAS
 * milik seorang karyawan; satu baris di Builder adalah PAKET milik organisasi.
 * Karena itu:
 *
 *   • Tidak ada sumbu jamak di sini. Satu paket punya tepat satu
 *     prioritas, satu status, satu mode distribusi. Seluruh persoalan
 *     "penempatan tidak menjumlah ke jumlah baris" yang harus dijelaskan di
 *     sumbu.js tidak berlaku, dan tidak ditiru.
 *
 *   • Kepemilikan dokumen (Departemen, Jenis Dokumen, APQC) SENGAJA TIDAK ADA.
 *     Ketiganya melekat pada dokumen, bukan pada paket. Menyediakannya di sini
 *     berarti menjawab "paket yang BERISI dokumen milik departemen X" — sebuah
 *     pertanyaan yang berbeda dari yang ditanyakan orang saat melihat kata
 *     "Departemen" di sebelah kata "Prioritas", dan yang harganya subquery
 *     EXISTS ke item pada setiap permintaan daftar.
 *
 *   • Penyaringan terjadi di SERVER. Modul ini karena itu tidak memfilter apa
 *     pun; ia merakit PARAMETER (`paramsPaket`) dan menerjemahkan hitungan
 *     kelompok dari server jadi bentuk yang dimengerti RailSumbu
 *     (`bangunGrupPaket`). Aturan tanggal & rentang estimasi tinggal di
 *     SopPaketService — satu salinan, bukan dua yang akan menyimpang.
 */

import { temaAksen } from '../../Shared/support/constants.js';
import { metaPrioritas } from '../../Shared/support/prioritas.js';

/** Nilai pengganti untuk paket yang kosong pada sumbu aktif. */
export const KOSONG = '__kosong__';

/** Nama kelompok untuk paket yang tak punya nilai pada sumbu tertentu. */
export const TANPA = {
    departemen: 'Tanpa Pemilik',
    jenis: 'Tanpa Jenis',
    apqc: 'Tanpa Proses',
    prioritas: 'Tanpa Prioritas',
    status: 'Tanpa Status',
    distribusi: 'Tanpa Mode',
};

/**
 * Sumbu yang ditawarkan sebagai pengelompokan rail, berurut tampil.
 *
 * `kunciSaringan` adalah kunci di `listFilters` yang ikut terisi saat sebuah
 * kelompok dipilih di rail. Rail dan dropdown karena itu BUKAN dua keadaan yang
 * berbeda: keduanya menulis ke ladang yang sama. Dua sumber kebenaran untuk
 * pertanyaan yang sama adalah cara paling murah untuk membuat layar
 * menampilkan angka yang tak cocok dengan daftarnya sendiri.
 */
export const SUMBU_PAKET = [
    { key: 'departemen', label: 'Departemen', ikon: 'diagram-3-fill', kunciSaringan: 'departemen' },
    { key: 'jenis', label: 'Jenis Dokumen', ikon: 'journals', kunciSaringan: 'jenis' },
    { key: 'apqc', label: 'Proses APQC', ikon: 'diagram-2', kunciSaringan: 'apqc' },
    { key: 'prioritas', label: 'Prioritas', ikon: 'flag-fill', kunciSaringan: 'prioritas' },
    { key: 'status', label: 'Status Rilis', ikon: 'send-check-fill', kunciSaringan: 'status' },
    { key: 'distribusi', label: 'Distribusi', ikon: 'people-fill', kunciSaringan: 'distribusi' },
];

/**
 * MELEMPAR pada kunci tak dikenal, tidak jatuh ke sumbu pertama.
 *
 * Alasannya sama dengan `sumbuDari` di sumbu.js: cadangan yang diam-diam
 * mengembalikan Prioritas membuat salah ketik menghasilkan pengelompokan yang
 * sama sekali lain, dengan tampilan yang masuk akal.
 */
export function sumbuPaketDari(key) {
    const s = SUMBU_PAKET.find((x) => x.key === key);
    if (!s) throw new Error(`Sumbu paket tidak dikenal: ${key}`);

    return s;
}

/* ══════════════════════════ EMBER & OPSI ══════════════════════════ */

/**
 * Ember tanggal terbit. Nilainya keadaan, bukan tanggal.
 *
 * Hidup berdampingan dengan rentang tanggal bebas, tidak menggantikannya:
 * "yang terbit bulan ini" dan "yang terbit antara 3 dan 17 Maret" adalah dua
 * pertanyaan yang dua-duanya nyata. Keduanya di-AND-kan di server.
 *
 * `belum` tak bisa dinyatakan sebagai rentang tanggal mana pun — ia
 * Tanggal_Publish yang NULL — dan justru itu sebabnya ember ini dikirim ke
 * server sebagai keadaan, bukan diterjemahkan jadi dua tanggal di sini.
 */
export const EMBER_TERBIT = [
    { value: '7hari', label: 'Terbit dalam 7 hari' },
    { value: '30hari', label: 'Terbit dalam 30 hari' },
    { value: 'kuartal', label: 'Kuartal berjalan' },
    { value: 'tahun', label: 'Tahun berjalan' },
    { value: 'belum', label: 'Belum pernah terbit' },
];

/**
 * Ember perkiraan waktu baca, dari `Estimasi_Waktu_Baca_Menit` pada paket.
 *
 * PUNYA EMBER "TANPA", tidak seperti EMBER_ESTIMASI di sumbu.js. Di sana
 * paket tanpa estimasi lenyap begitu ember mana pun dipilih — hilang tanpa
 * pesan. Di Builder justru paket tanpa estimasi itulah yang sering dicari
 * (yang belum dirapikan), jadi ia diberi tempat sendiri. EMBER_TENGGAT di
 * halaman sebelah sudah memakai pola yang sama dengan "Tanpa tenggat"; yang
 * satunya yang jadi pengecualian.
 */
export const EMBER_ESTIMASI = [
    { value: 'pendek', label: '≤ 10 menit' },
    { value: 'sedang', label: '11–30 menit' },
    { value: 'panjang', label: '> 30 menit' },
    { value: 'tanpa', label: 'Tanpa estimasi' },
];

/** Mode distribusi paket. Nilainya sama dengan yang tersimpan di kolom. */
export const OPSI_DISTRIBUSI = [
    { value: 'PUBLIK', label: 'Publik' },
    { value: 'TERBATAS', label: 'Terbatas' },
];

/** Status rilis paket. */
export const OPSI_STATUS = [
    { value: 'DRAFT', label: 'Draft' },
    { value: 'PUBLISHED', label: 'Terbit' },
    { value: 'ARCHIVED', label: 'Arsip' },
];

/** Urutan daftar. `newest` adalah bawaan dan karena itu tak pernah dikirim. */
export const OPSI_SORT = [
    { value: 'newest', label: 'Terbaru' },
    { value: 'oldest', label: 'Terlama' },
    { value: 'nama', label: 'Nama A–Z' },
    { value: 'prioritas', label: 'Prioritas' },
    { value: 'estimasi_pendek', label: 'Estimasi terpendek' },
    { value: 'estimasi_panjang', label: 'Estimasi terpanjang' },
];

/** Bentuk awal seluruh ladang saringan. Dipakai data() dan tombol reset. */
export function saringanKosong() {
    return {
        search: '',
        departemen: [],
        jenis: [],
        apqc: [],
        prioritas: [],
        status: [],
        distribusi: [],
        estimasi: '',
        terbit: '',
        tgl_publish: [],
        absensi: false,
        sort: 'newest',
    };
}

/* ══════════════════════════ PARAMETER ══════════════════════════ */

/** Daftar bersih dari nilai yang bukan daftar, kosong, atau null. */
function daftar(v) {
    if (Array.isArray(v)) return v.filter((x) => x !== '' && x !== null && x !== undefined);
    if (v === '' || v === null || v === undefined) return [];

    return [v];
}

/**
 * Tanggal jadi `YYYY-MM-DD` dengan komponen LOKAL, bukan `toISOString()`.
 *
 * toISOString() memindahkan tanggal ke UTC lebih dulu. Di WIB (UTC+7) sebuah
 * tanggal yang dipilih orang pukul berapa pun pada tanggal 1 dikirim sebagai
 * tanggal 1 juga — tapi picker yang mengembalikan tengah malam lokal
 * menghasilkan 17:00 UTC hari SEBELUMNYA, dan saringan "sejak 1 Maret" diam-diam
 * ikut memuat 28 Februari.
 */
function ymd(v) {
    if (!v) return '';
    if (typeof v === 'string') return v.slice(0, 10);
    if (v instanceof Date && !Number.isNaN(v.getTime())) {
        const b = String(v.getMonth() + 1).padStart(2, '0');
        const h = String(v.getDate()).padStart(2, '0');

        return `${v.getFullYear()}-${b}-${h}`;
    }

    return '';
}

/**
 * Rakit parameter kueri dari keadaan saringan.
 *
 * Nilai bawaan TIDAK dikirim (sort 'newest', sakelar mati, daftar kosong).
 * Bukan demi URL yang pendek: `filterParams()` yang sama dipakai untuk
 * memanggil endpoint hitungan, dan parameter yang terkirim padahal tak
 * menyaring apa pun membuat cache di hilir bercabang tanpa alasan.
 */
export function paramsPaket(f) {
    const p = {};
    const s = f || {};

    if (s.search) p.q = s.search;

    const departemen = daftar(s.departemen);
    const jenis = daftar(s.jenis);
    const apqc = daftar(s.apqc);
    const prioritas = daftar(s.prioritas);
    const status = daftar(s.status);
    const distribusi = daftar(s.distribusi);

    if (departemen.length) p.departemen = departemen;
    if (jenis.length) p.jenis = jenis;
    if (apqc.length) p.apqc = apqc;
    if (prioritas.length) p.prioritas = prioritas;
    if (status.length) p.status = status;
    if (distribusi.length) p.distribusi = distribusi;

    if (s.estimasi) p.estimasi = s.estimasi;
    if (s.terbit) p.terbit = s.terbit;

    const dari = ymd(daftar(s.tgl_publish)[0]);
    const sampai = ymd(daftar(s.tgl_publish)[1]);
    if (dari) p.tgl_dari = dari;
    if (sampai) p.tgl_sampai = sampai;

    // Sakelar, bukan pilihan tiga keadaan: "sembunyikan paket berabsensi"
    // bukan pertanyaan yang pernah diajukan siapa pun.
    if (s.absensi) p.absensi = 'Y';

    if (s.sort && s.sort !== 'newest') p.sort = s.sort;

    return p;
}

/** Apakah ada saringan yang benar-benar mempersempit daftar. */
export function adaSaringanAktif(f) {
    return Object.keys(paramsPaket(f)).length > 0;
}

/* ══════════════════════════ RAIL ══════════════════════════ */

/** Warna & ikon satu kelompok, sesuai sumbunya. */
function temaGrup(sumbu, nilai, nama) {
    if (sumbu === 'departemen') {
        return { warna: '#3b82f6', ikon: 'bi-diagram-3-fill' };
    }
    if (sumbu === 'jenis') {
        return { warna: '#8b5cf6', ikon: 'bi-journals' };
    }
    if (sumbu === 'apqc') {
        return { warna: '#10b981', ikon: 'bi-diagram-2' };
    }
    if (sumbu === 'prioritas') {
        return { warna: metaPrioritas(nilai).warna || '#94a3b8', ikon: 'bi-flag-fill' };
    }
    if (sumbu === 'status') {
        const peta = {
            DRAFT: { warna: '#f59e0b', ikon: 'bi-pencil-square' },
            PUBLISHED: { warna: '#10b981', ikon: 'bi-check-circle-fill' },
            ARCHIVED: { warna: '#94a3b8', ikon: 'bi-archive-fill' },
        };

        return peta[nilai] || { warna: '#94a3b8', ikon: 'bi-circle' };
    }

    const peta = {
        PUBLIK: { warna: '#0ea5e9', ikon: 'bi-globe2' },
        TERBATAS: { warna: '#8b5cf6', ikon: 'bi-lock-fill' },
    };

    return peta[nilai] || { warna: '#94a3b8', ikon: 'bi-circle' };
}

/** Label yang dibaca orang untuk satu nilai sumbu. */
function namaGrup(sumbu, nilai, namaServer) {
    if (namaServer) return namaServer;
    if (!nilai || nilai === KOSONG) return TANPA[sumbu] || 'Tanpa Nilai';
    if (sumbu === 'departemen') return nilai;
    if (sumbu === 'jenis') return nilai;
    if (sumbu === 'apqc') return nilai;
    if (sumbu === 'prioritas') return metaPrioritas(nilai).label || nilai;
    if (sumbu === 'status') return (OPSI_STATUS.find((o) => o.value === nilai) || {}).label || nilai;
    if (sumbu === 'distribusi') return (OPSI_DISTRIBUSI.find((o) => o.value === nilai) || {}).label || nilai;

    return String(nilai);
}

/**
 * Terjemahkan hitungan kelompok dari server jadi bentuk yang dipahami RailSumbu.
 *
 * Angkanya datang dari SERVER, bukan dari baris yang sedang tampil. Bentuk lama
 * menghitungnya dari `paketList` — sepuluh baris halaman berjalan — sehingga
 * kelompok berisi empat puluh paket bisa tertulis "3", dan di halaman kedua
 * hilang sama sekali. Angka di rail dipakai orang untuk memutuskan ke mana
 * mengklik; angka yang berubah karena paginasi menjawab pertanyaan yang tak
 * seorang pun ajukan.
 *
 * @param {Array} rows [{ nilai, nama, total }] dari GET /paket/counts?sumbu=…
 * @param {string} sumbu kunci sumbu aktif
 */
export function bangunGrupPaket(rows, sumbu) {
    const s = sumbuPaketDari(sumbu);

    const grup = (rows || [])
        .map((r) => {
            const nilai = r.nilai === null || r.nilai === undefined || r.nilai === '' ? KOSONG : String(r.nilai);
            const nama = namaGrup(s.key, nilai === KOSONG ? '' : nilai, r.nama);
            const t = temaGrup(s.key, nilai === KOSONG ? '' : nilai, nama);

            return {
                nilai,
                nama,
                warna: t.warna,
                ikon: t.ikon,
                kosong: nilai === KOSONG,
                total: Number(r.total) || 0,
                // Paket tak punya dimensi kemajuan; bar di rail dimatikan
                // pemanggil, dan angka-angka ini ada hanya agar bentuknya
                // sepadan dengan yang diharapkan RailSumbu.
                persen: 0,
                terlambat: 0,
            };
        })
        /* Kelompok "tanpa …" selalu di bawah, berapa pun angkanya — ia bukan
           unit organisasi melainkan paket yang belum dirapikan. */
        .sort((a, b) => a.kosong - b.kosong || b.total - a.total || a.nama.localeCompare(b.nama));

    return { grup, total: grup.reduce((n, g) => n + g.total, 0) };
}

/**
 * Nilai rail yang sedang menyala untuk sumbu tertentu.
 *
 * Mengembalikan '' ketika sumbu itu tidak disaring ATAU disaring dengan lebih
 * dari satu nilai. Yang kedua penting: rail hanya bisa menyalakan satu entri,
 * dan menyalakan yang pertama dari tiga pilihan akan membacakan "sedang melihat
 * Prioritas A" pada daftar yang sebetulnya berisi tiga tingkat.
 */
export function railAktif(f, sumbu) {
    const nilai = daftar((f || {})[sumbuPaketDari(sumbu).kunciSaringan]);

    return nilai.length === 1 ? String(nilai[0]) : '';
}
