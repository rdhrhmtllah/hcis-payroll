<?php

namespace App\Services\LMS\SOP\Export;

use App\Services\LMS\SOP\Support\SopDokumenKelompok;
use App\Services\LMS\SOP\Support\SopVersiTanggal;

/**
 * Template resmi SOP — didefinisikan di KODE, bukan baris seed.
 *
 * Alasannya tiga: tidak ada drift antara development dan production, isinya
 * ikut code review seperti kode lain, dan "Duplikat & ubah" jadi sekadar salin
 * kode ke baris milik pengguna.
 *
 * KOLOM `distribusi` DAN `sumber` ADA DI SETIAP TEMPLATE RINCI.
 *
 * Tanpa keduanya, satu berkas memuat dua hal yang artinya berbeda tanpa cara
 * membedakannya: SOP tertutup yang memang ditugaskan, dan SOP publik yang
 * barisnya baru lahir ketika karyawan mulai membacanya. Dijumlahkan bersama,
 * angkanya benar secara aritmetika dan menyesatkan secara bisnis — dan tak
 * seorang pun yang membuka berkasnya bisa tahu.
 *
 * `sumber` ikut karena distribusi saja belum cukup: paket PUBLIK pun bisa
 * DITUGASKAN admin, dan itu tetap kewajiban. Yang benar-benar sukarela hanya
 * baris ber-Sumber 'Pendaftaran Mandiri'.
 */
class SopTemplateSistem
{
    /** Kolom rincian baku; dipakai bersama beberapa template resmi. */
    private const KOLOM_RINCI = [
        'kode', 'nama', 'no_induk', 'lokasi', 'divisi', 'departemen', 'jabatan',
        'paket', 'kode_paket', 'distribusi', 'sumber', 'prioritas', 'status_paket',
        'tgl_assign', 'tenggat',
        'section', 'urutan', 'item', 'nomor_sop', 'tipe_item', 'wajib',
        'status_item', 'persen_item', 'waktu_buka', 'waktu_konfirmasi',
        'skor_kuis', 'percobaan', 'lulus',
    ];

    /**
     * Rincian kepatuhan BESERTA lapis dokumen dan versi.
     *
     * Berdiri sendiri, tidak menggantikan KOLOM_RINCI. Melebarkan kolom baku
     * berarti tiga berkas resmi yang sudah dikenal orang tiba-tiba berubah
     * bentuk tanpa ada yang memintanya — dan berkas yang bentuknya berubah
     * diam-diam mematahkan setiap pivot Excel yang menunjuk kolom lama.
     */
    private const KOLOM_TATA_KELOLA = [
        'kode', 'nama', 'divisi', 'departemen', 'jabatan',
        'paket', 'distribusi', 'sumber', 'status_paket',
        'item', 'nomor_sop', 'no_dokumen', 'jenis_dokumen', 'apqc', 'dept_pemilik',
        'versi_ke', 'status_versi', 'tahap_versi',
        'tgl_berlaku', 'tgl_kadaluarsa', 'status_masa_berlaku',
        'tgl_review_berikutnya', 'status_review',
        'nomor_pengesahan', 'pengesah', 'tgl_pengesahan', 'baca_ulang',
        'status_item', 'waktu_konfirmasi',
    ];

    /** Kolom register dokumen; dipakai dua template berbutir dokumen. */
    private const KOLOM_DOKUMEN = [
        'nomor_sop', 'no_dokumen', 'judul', 'tipe',
        'jenis_dokumen', 'kode_jenis', 'level_hirarki', 'apqc', 'kode_apqc',
        'dept_pemilik', 'entitas_pemilik', 'entitas_lingkup',
        'kelompok', 'paket_perujuk', 'versi_menggantung',
        'versi_ke', 'status_versi', 'tahap_versi',
        'tgl_berlaku', 'tgl_kadaluarsa', 'status_masa_berlaku',
        'tgl_review_berikutnya', 'status_review',
        'nomor_pengesahan', 'pengesah', 'jabatan_pengesah', 'tgl_pengesahan',
    ];

    public static function semua(): array
    {
        return [
            'sop-sistem-rinci' => self::rinci(
                'sop-sistem-rinci',
                'Rincian Kepatuhan per Item',
                'Satu baris per karyawan × paket × item, lengkap dengan kolom Tipe Distribusi dan Sumber Penugasan. Berkas terlebar modul DMS, dibuat untuk di-pivot di Excel.',
                []
            ),

            'sop-sistem-rinci-tertutup' => self::rinci(
                'sop-sistem-rinci-tertutup',
                'Rincian Kepatuhan — Dokumen Tertutup',
                'Hanya paket berdistribusi Tertutup (Ditugaskan). Inilah kewajiban yang ditugaskan langsung, termasuk yang belum disentuh sama sekali — dipakai saat menagih tunggakan.',
                [['field' => 'distribusi', 'op' => 'sama_dengan', 'nilai' => 'TERBATAS']]
            ),

            'sop-sistem-rinci-publik' => self::rinci(
                'sop-sistem-rinci-publik',
                'Rincian Aktivitas — Dokumen Publik',
                'Hanya paket berdistribusi Publik. Perhatikan: karyawan yang bisa melihat paket publik tapi belum menyentuhnya sama sekali TIDAK punya baris di sini — barisnya baru lahir saat ia mulai membaca. Jadi berkas ini membaca minat & aktivitas, bukan tunggakan.',
                [['field' => 'distribusi', 'op' => 'sama_dengan', 'nilai' => 'PUBLIK']]
            ),

            'sop-sistem-ringkas-distribusi' => [
                'kode' => 'sop-sistem-ringkas-distribusi',
                'nama' => 'Rekap per Tipe Distribusi',
                'deskripsi' => 'Satu baris untuk Tertutup dan satu untuk Publik: berapa orang, berapa penugasan, berapa item, dan berapa persen yang selesai pada masing-masing. Jawaban paling ringkas untuk "berapa yang wajib, berapa yang publik".',
                'dataset' => 'kepatuhan',
                'mode' => 'ringkasan',
                'definisi' => [
                    'versi' => 1,
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['distribusi'],
                    'ukuran' => ['jml_karyawan', 'jml_penugasan', 'jml_paket', 'jml_item', 'jml_selesai', 'jml_belum', 'persen_selesai'],
                    'kolom' => [],
                    'saringan' => [],
                    'urut' => [['key' => 'distribusi', 'arah' => 'asc']],
                    // Grand total sengaja MATI. Enam dari tujuh ukuran di sini
                    // tidak sah dijumlahkan antar kelompok (hitungan unik,
                    // persentase), sehingga barisnya hanya akan berisi '-'.
                    'opsi' => [],
                    'bagan' => null,
                    'format_bawaan' => 'xlsx',
                ],
            ],

            'sop-sistem-ringkas-distribusi-unit' => [
                'kode' => 'sop-sistem-ringkas-distribusi-unit',
                'nama' => 'Rekap Distribusi per Unit',
                'deskripsi' => 'Tertutup vs Publik dipecah per divisi dan departemen. Dipakai untuk melihat unit mana yang tunggakan wajibnya menumpuk, terpisah dari aktivitas dokumen publik yang sifatnya sukarela.',
                'dataset' => 'kepatuhan',
                'mode' => 'ringkasan',
                'definisi' => [
                    'versi' => 1,
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['divisi', 'departemen', 'distribusi'],
                    'ukuran' => ['jml_karyawan', 'jml_item', 'jml_selesai', 'jml_belum', 'persen_selesai'],
                    'kolom' => [],
                    'saringan' => [],
                    'urut' => [
                        ['key' => 'divisi', 'arah' => 'asc'],
                        ['key' => 'departemen', 'arah' => 'asc'],
                        ['key' => 'distribusi', 'arah' => 'asc'],
                    ],
                    'opsi' => [],
                    'bagan' => null,
                    'format_bawaan' => 'xlsx',
                ],
            ],

            'sop-sistem-ringkas-sumber' => [
                'kode' => 'sop-sistem-ringkas-sumber',
                'nama' => 'Rekap per Sumber Penugasan',
                'deskripsi' => 'Memisahkan yang DITUGASKAN (admin / aturan otomatis) dari yang mendaftar sendiri. Distribusi saja belum cukup membedakan wajib dari sukarela: paket publik pun bisa ditugaskan admin, dan itu tetap kewajiban.',
                'dataset' => 'kepatuhan',
                'mode' => 'ringkasan',
                'definisi' => [
                    'versi' => 1,
                    'dataset' => 'kepatuhan',
                    'mode' => 'ringkasan',
                    'dimensi' => ['sumber', 'distribusi'],
                    'ukuran' => ['jml_karyawan', 'jml_penugasan', 'jml_item', 'jml_selesai', 'jml_belum', 'persen_selesai'],
                    'kolom' => [],
                    'saringan' => [],
                    'urut' => [
                        ['key' => 'sumber', 'arah' => 'asc'],
                        ['key' => 'distribusi', 'arah' => 'asc'],
                    ],
                    'opsi' => [],
                    'bagan' => null,
                    'format_bawaan' => 'xlsx',
                ],
            ],

            // ── Siap pakai: menagih tunggakan ─────────────────────────────
            'sop-sistem-tunggakan-rinci' => self::rinci(
                'sop-sistem-tunggakan-rinci',
                'Daftar Tunggakan — Belum Dimulai',
                'Penugasan yang sama sekali belum disentuh karyawan (Status Paket = BELUM), tertutup maupun publik. Inilah berkas yang dibawa saat menagih: satu baris per item yang menunggu dibaca.',
                [['field' => 'status_paket', 'op' => 'sama_dengan', 'nilai' => 'BELUM']]
            ),

            'sop-sistem-publik-ditugaskan' => self::rinci(
                'sop-sistem-publik-ditugaskan',
                'Dokumen Publik yang Ditugaskan',
                'Paket berdistribusi Publik yang TETAP ditugaskan admin atau aturan otomatis — bukan pendaftaran mandiri. Publik menentukan siapa boleh melihat; penugasan menentukan siapa wajib baca, jadi baris di sini adalah kewajiban meski paketnya terbuka untuk umum.',
                [
                    ['field' => 'distribusi', 'op' => 'sama_dengan', 'nilai' => 'PUBLIK'],
                    ['field' => 'sumber', 'op' => 'tidak_sama', 'nilai' => 'MANDIRI'],
                ]
            ),

            'sop-sistem-jejak-konfirmasi' => [
                'kode' => 'sop-sistem-jejak-konfirmasi',
                'nama' => 'Jejak Konfirmasi',
                'deskripsi' => 'Siapa mengonfirmasi apa, kapan, dari IP dan perangkat mana. Kolom IP dan Perangkat otomatis tersamar bagi yang tidak berhak melihat data terbatas — kolomnya tetap ada supaya bentuk berkas sama untuk semua orang.',
                'dataset' => 'kepatuhan',
                'mode' => 'rinci',
                'definisi' => [
                    'versi' => 1,
                    'dataset' => 'kepatuhan',
                    'mode' => 'rinci',
                    'dimensi' => [],
                    'ukuran' => [],
                    'kolom' => [
                        'kode', 'nama', 'divisi', 'departemen',
                        'paket', 'distribusi', 'sumber', 'item', 'nomor_sop',
                        'status_item', 'waktu_buka', 'waktu_konfirmasi', 'ip', 'perangkat',
                    ],
                    'saringan' => [],
                    'urut' => [['key' => 'waktu_konfirmasi', 'arah' => 'desc']],
                    'opsi' => [],
                    'bagan' => null,
                    'format_bawaan' => 'xlsx',
                ],
            ],

            // ── Siap pakai: rekap ─────────────────────────────────────────
            'sop-sistem-kepatuhan-karyawan' => self::ringkas(
                'sop-sistem-kepatuhan-karyawan',
                'Kepatuhan per Karyawan',
                'Satu baris per orang, diurutkan dari yang paling tertinggal. Rekap yang paling sering diminta atasan.',
                ['kode', 'nama', 'divisi', 'departemen'],
                ['jml_penugasan', 'jml_item', 'jml_selesai', 'jml_belum', 'persen_selesai'],
                [['key' => 'persen_selesai', 'arah' => 'asc']]
            ),

            'sop-sistem-kepatuhan-paket' => self::ringkas(
                'sop-sistem-kepatuhan-paket',
                'Kepatuhan per Paket Dokumen',
                'Paket mana yang paling banyak menunggak, lengkap dengan tipe distribusi dan prioritasnya.',
                ['paket', 'kode_paket', 'distribusi', 'prioritas'],
                ['jml_karyawan', 'jml_item', 'jml_selesai', 'jml_belum', 'persen_selesai'],
                [['key' => 'persen_selesai', 'arah' => 'asc']]
            ),

            'sop-sistem-kepatuhan-prioritas' => self::ringkas(
                'sop-sistem-kepatuhan-prioritas',
                'Kepatuhan per Prioritas',
                'Menjawab pertanyaan yang berbeda dari "berapa persen": apakah yang tertinggal itu dokumen kritis atau dokumen biasa.',
                ['prioritas', 'distribusi'],
                ['jml_karyawan', 'jml_item', 'jml_selesai', 'jml_belum', 'persen_selesai'],
                [['key' => 'prioritas', 'arah' => 'asc']]
            ),

            'sop-sistem-penyelesaian-bulanan' => self::ringkas(
                'sop-sistem-penyelesaian-bulanan',
                'Penyelesaian per Bulan',
                'Berapa banyak item dikonfirmasi tiap bulan, dipisah tertutup dan publik. Baris tanpa bulan adalah item yang belum pernah dikonfirmasi sama sekali.',
                ['bulan_konfirmasi', 'distribusi'],
                ['jml_karyawan', 'jml_item', 'jml_selesai'],
                [['key' => 'bulan_konfirmasi', 'arah' => 'desc']]
            ),

            'sop-sistem-kuis' => self::ringkas(
                'sop-sistem-kuis',
                'Rekap Kuis Dokumen',
                'Skor rata-rata per item berkuis. Item tanpa kuis tampil kosong, bukan nol — nol berarti mengerjakan lalu gagal.',
                ['paket', 'item'],
                ['jml_karyawan', 'skor_kuis_rata', 'jml_selesai'],
                [['key' => 'paket', 'arah' => 'asc'], ['key' => 'item', 'arah' => 'asc']]
            ),

            // ── Kepatuhan × tata kelola dokumen ───────────────────────────
            'sop-sistem-rinci-tatakelola' => [
                'kode' => 'sop-sistem-rinci-tatakelola',
                'nama' => 'Rincian Kepatuhan + Tata Kelola Dokumen',
                'deskripsi' => 'Rincian kepatuhan yang membawa serta identitas resmi dokumen (nomor, jenis, proses APQC, departemen pemilik) dan keadaan versi yang sedang dibaca karyawan: versi ke berapa, tahapnya, kapan berlaku, kapan kedaluwarsa, siapa mengesahkan. Dipakai saat audit menanyakan "yang dibaca orang ini versi mana, dan apakah masih berlaku".',
                'dataset' => 'kepatuhan',
                'mode' => 'rinci',
                'definisi' => [
                    'versi' => 1,
                    'dataset' => 'kepatuhan',
                    'mode' => 'rinci',
                    'dimensi' => [],
                    'ukuran' => [],
                    'kolom' => self::KOLOM_TATA_KELOLA,
                    'saringan' => [],
                    'urut' => [],
                    'opsi' => [],
                    'bagan' => null,
                    'format_bawaan' => 'xlsx',
                ],
            ],

            'sop-sistem-kepatuhan-jenis-apqc' => self::ringkas(
                'sop-sistem-kepatuhan-jenis-apqc',
                'Kepatuhan per Jenis Dokumen & APQC',
                'Menjawab pertanyaan yang tak terjawab dari rekap per paket: apakah yang tertinggal itu Kebijakan, Prosedur, atau Instruksi Kerja — dan pada proses bisnis yang mana. Baris tanpa jenis adalah item yang belum ditautkan ke dokumen mana pun.',
                ['jenis_dokumen', 'apqc', 'distribusi'],
                ['jml_dokumen', 'jml_karyawan', 'jml_item', 'jml_selesai', 'jml_belum', 'persen_selesai'],
                [['key' => 'jenis_dokumen', 'arah' => 'asc'], ['key' => 'apqc', 'arah' => 'asc']]
            ),

            'sop-sistem-item-tanpa-versi' => [
                'kode' => 'sop-sistem-item-tanpa-versi',
                'nama' => 'Item Wajib Tanpa Versi Aktif',
                'deskripsi' => 'Item yang ditugaskan ke karyawan padahal tidak berdiri di atas versi sah mana pun. Pointer versi aktif hanya boleh menunjuk versi yang sudah terbit, jadi setiap baris di sini adalah bacaan wajib yang isinya tak bisa dipertanggungjawabkan. Berkas terpendek yang paling layak dikosongkan.',
                'dataset' => 'kepatuhan',
                'mode' => 'rinci',
                'definisi' => [
                    'versi' => 1,
                    'dataset' => 'kepatuhan',
                    'mode' => 'rinci',
                    'dimensi' => [],
                    'ukuran' => [],
                    'kolom' => [
                        'kode', 'nama', 'divisi', 'departemen',
                        'paket', 'distribusi', 'sumber',
                        'item', 'nomor_sop', 'tipe_item', 'wajib', 'status_item',
                    ],
                    'saringan' => [
                        ['field' => 'status_masa_berlaku', 'op' => 'sama_dengan', 'nilai' => SopVersiTanggal::TANPA_VERSI],
                    ],
                    'urut' => [['key' => 'paket', 'arah' => 'asc']],
                    'opsi' => [],
                    'bagan' => null,
                    'format_bawaan' => 'xlsx',
                ],
            ],

            // ── Register dokumen (butiran: satu dokumen) ──────────────────
            'sop-sistem-register-dokumen' => self::rinciDokumen(
                'sop-sistem-register-dokumen',
                'Register Dokumen',
                'Seluruh dokumen aktif, satu baris masing-masing — termasuk yang belum masuk paket mana pun dan karena itu tak pernah muncul di laporan kepatuhan. Membawa identitas resmi, klasifikasi, kepemilikan, dan keadaan versi aktifnya. Inilah berkas yang dibawa ke audit dokumen.',
                []
            ),

            'sop-sistem-dokumen-belum-beredar' => self::rinciDokumen(
                'sop-sistem-dokumen-belum-beredar',
                'Dokumen Belum Beredar',
                'Dokumen yang sudah terdaftar dan bernomor tetapi belum sampai ke siapa pun: tidak ada di paket terbit mana pun, dan tidak menjadi kewajiban siapa pun. Bukan angka nol di sebuah kartu melainkan daftar pekerjaan yang menunggu.',
                [['field' => 'kelompok', 'op' => 'sama_dengan', 'nilai' => SopDokumenKelompok::BELUM_BEREDAR]]
            ),

            'sop-sistem-dokumen-piramid' => self::ringkasDokumen(
                'sop-sistem-dokumen-piramid',
                'Piramid Jenis × Peredaran',
                'Dari sekian Prosedur, berapa yang benar-benar sampai ke orang. Jenis dokumen dipecah per kelompok peredaran, diurutkan mengikuti level hirarkinya — tingkat piramid yang kosong justru temuan yang dicari.',
                ['level_hirarki', 'jenis_dokumen', 'kelompok'],
                ['jml_dokumen', 'jml_ada_versi', 'jml_tanpa_versi', 'persen_beredar'],
                [['key' => 'level_hirarki', 'arah' => 'asc'], ['key' => 'jenis_dokumen', 'arah' => 'asc']]
            ),

            'sop-sistem-dokumen-masa-berlaku' => self::ringkasDokumen(
                'sop-sistem-dokumen-masa-berlaku',
                'Keadaan Masa Berlaku & Review',
                'Berapa dokumen yang sudah kedaluwarsa, berapa yang akan kedaluwarsa dalam tiga bulan, dan berapa yang jadwal reviewnya sudah terlewat — dipecah per jenis. Baris "Tanpa versi aktif" berdiri sendiri dan tidak dilebur dengan yang tanpa tanggal: artinya sama sekali berbeda.',
                ['status_masa_berlaku', 'jenis_dokumen'],
                ['jml_dokumen', 'jml_kadaluarsa', 'jml_review_jatuh_tempo', 'jml_tanpa_versi'],
                [['key' => 'status_masa_berlaku', 'arah' => 'asc'], ['key' => 'jenis_dokumen', 'arah' => 'asc']]
            ),

            'sop-sistem-dokumen-kepemilikan' => self::ringkasDokumen(
                'sop-sistem-dokumen-kepemilikan',
                'Kepemilikan Dokumen per Departemen',
                'Siapa memiliki apa, dan dalam keadaan bagaimana. Baris tanpa departemen adalah dokumen tak bertuan — tak ada seorang pun yang bertanggung jawab merevisinya ketika masa berlakunya habis, dan itu keadaan yang menuntut tindakan, bukan sekadar sel kosong.',
                ['dept_pemilik', 'kelompok'],
                ['jml_dokumen', 'jml_tanpa_versi', 'jml_kadaluarsa', 'jml_versi_menggantung', 'persen_beredar'],
                [['key' => 'dept_pemilik', 'arah' => 'asc']]
            ),

            'sop-sistem-dokumen-lubang-tatakelola' => self::ringkasDokumen(
                'sop-sistem-dokumen-lubang-tatakelola',
                'Lubang Tata Kelola per Proses APQC',
                'Sebaran dokumen atas proses bisnis, beserta yang belum terklasifikasi sama sekali. Proses yang tak punya satu pun dokumen tidak akan muncul sebagai baris di sini — untuk itu pakai saringan Proses APQC di Studio, yang memuat seluruh master termasuk yang kosong.',
                ['apqc', 'kelompok'],
                ['jml_dokumen', 'jml_tanpa_jenis', 'jml_tanpa_apqc', 'jml_tanpa_pemilik', 'jml_tanpa_nomor'],
                [['key' => 'apqc', 'arah' => 'asc']]
            ),
        ];
    }

    /**
     * @param  string[]  $dimensi
     * @param  string[]  $ukuran
     * @param  array<int, array>  $urut
     * @param  array<int, array>  $saringan
     */
    private static function ringkas(string $kode, string $nama, string $deskripsi, array $dimensi, array $ukuran, array $urut, array $saringan = []): array
    {
        return [
            'kode' => $kode,
            'nama' => $nama,
            'deskripsi' => $deskripsi,
            'dataset' => 'kepatuhan',
            'mode' => 'ringkasan',
            'definisi' => [
                'versi' => 1,
                'dataset' => 'kepatuhan',
                'mode' => 'ringkasan',
                'dimensi' => $dimensi,
                'ukuran' => $ukuran,
                'kolom' => [],
                'saringan' => $saringan,
                'urut' => $urut,
                // Grand total sengaja mati di seluruh template resmi: sebagian
                // besar ukuran di sini tidak sah dijumlahkan antar kelompok.
                'opsi' => [],
                'bagan' => null,
                'format_bawaan' => 'xlsx',
            ],
        ];
    }

    /**
     * Rekap berbutir DOKUMEN.
     *
     * Kembaran ringkas() dengan satu perbedaan yang menentukan: datasetnya.
     * Keduanya sengaja tidak dilebur jadi satu helper berparameter dataset —
     * bentuk itu membuat sebuah template mudah menunjuk dataset yang salah
     * sambil memakai kunci dimensi milik dataset lain, dan galatnya baru
     * muncul saat template dijalankan orang.
     *
     * @param  string[]  $dimensi
     * @param  string[]  $ukuran
     * @param  array<int, array>  $urut
     * @param  array<int, array>  $saringan
     */
    private static function ringkasDokumen(string $kode, string $nama, string $deskripsi, array $dimensi, array $ukuran, array $urut, array $saringan = []): array
    {
        return [
            'kode' => $kode,
            'nama' => $nama,
            'deskripsi' => $deskripsi,
            'dataset' => 'dokumen',
            'mode' => 'ringkasan',
            'definisi' => [
                'versi' => 1,
                'dataset' => 'dokumen',
                'mode' => 'ringkasan',
                'dimensi' => $dimensi,
                'ukuran' => $ukuran,
                'kolom' => [],
                'saringan' => $saringan,
                'urut' => $urut,
                'opsi' => [],
                'bagan' => null,
                'format_bawaan' => 'xlsx',
            ],
        ];
    }

    /** @param  array<int, array>  $saringan */
    private static function rinciDokumen(string $kode, string $nama, string $deskripsi, array $saringan): array
    {
        return [
            'kode' => $kode,
            'nama' => $nama,
            'deskripsi' => $deskripsi,
            'dataset' => 'dokumen',
            'mode' => 'rinci',
            'definisi' => [
                'versi' => 1,
                'dataset' => 'dokumen',
                'mode' => 'rinci',
                'dimensi' => [],
                'ukuran' => [],
                'kolom' => self::KOLOM_DOKUMEN,
                'saringan' => $saringan,
                'urut' => [['key' => 'nomor_sop', 'arah' => 'asc']],
                'opsi' => [],
                'bagan' => null,
                'format_bawaan' => 'xlsx',
            ],
        ];
    }

    /** @param  array<int, array>  $saringan */
    private static function rinci(string $kode, string $nama, string $deskripsi, array $saringan): array
    {
        return [
            'kode' => $kode,
            'nama' => $nama,
            'deskripsi' => $deskripsi,
            'dataset' => 'kepatuhan',
            'mode' => 'rinci',
            'definisi' => [
                'versi' => 1,
                'dataset' => 'kepatuhan',
                'mode' => 'rinci',
                'dimensi' => [],
                'ukuran' => [],
                'kolom' => self::KOLOM_RINCI,
                'saringan' => $saringan,
                'urut' => [],
                'opsi' => [],
                'bagan' => null,
                'format_bawaan' => 'xlsx',
            ],
        ];
    }
}
