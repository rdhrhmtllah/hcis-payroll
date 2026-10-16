<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Leave\Shared\KueriExportIzin;
use Tests\TestCase;

/**
 * SQL export izin punya SATU sumber, dan hasilnya sama dengan sebelum dipusatkan.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * `getExport()` dan `getExportAdmin()` di AbsensiController adalah dua metode
 * 466 dan 469 baris yang isinya sama kecuali enam titik — 431 dan 433 baris SQL
 * dengan tujuh belas baris yang benar-benar berbeda. Keenam titik itu semuanya
 * turunan dari satu perbedaan: siapa yang boleh dilihat.
 *
 * ── Kenapa penjaganya berupa perbandingan teks penuh ─────────────────────────
 *
 * Yang dipertaruhkan di sini bukan gaya kode melainkan ISI EKSPOR yang dipakai
 * orang. Penjaga yang cuma memeriksa "ada kata nama_requester" akan hijau pada
 * SQL yang salah kolomnya, salah join-nya, atau kehilangan satu cabang CTE.
 * Maka yang dibandingkan teks utuhnya, terhadap salinan SQL SEBELUM pemusatan
 * yang disimpan sebagai fixture.
 *
 * Varian pribadi dituntut IDENTIK BYTE PER BYTE. Varian admin dituntut setara
 * modulo spasi: kedua teks lama memang berbeda indentasi di enam baris itu,
 * jadi satu templat tak mungkin mereproduksi keduanya sekaligus, dan versi
 * pribadi yang dijadikan kanon karena itulah jalur yang dilewati setiap
 * karyawan.
 *
 * Uji ini tidak menyentuh basis data sama sekali — yang diperiksa string.
 */
class KueriExportIzinSatuSumberTest extends TestCase
{
    private const FIXTURE = 'tests/Support/Fixtures/ExportIzin/';

    private const CONTROLLER = 'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php';

    private function fixture(string $nama): string
    {
        $p = base_path(self::FIXTURE.$nama);
        $this->assertFileExists($p, 'Fixture SQL sebelum pemusatan hilang — tanpa itu penjaga ini tak membuktikan apa pun.');

        return (string) file_get_contents($p);
    }

    private function rapat(string $sql): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }

    public function test_varian_pribadi_identik_byte_per_byte_dengan_sebelum_pemusatan(): void
    {
        $this->assertSame(
            $this->fixture('sebelum-pribadi.sql'),
            KueriExportIzin::sql(false),
            'SQL export pribadi BERUBAH. Ini jalur yang dilewati setiap karyawan dan '.
            'ia dijadikan kanon justru supaya tidak bergeser sebyte pun.',
        );
    }

    public function test_varian_admin_setara_dengan_sebelum_pemusatan(): void
    {
        $this->assertSame(
            $this->rapat($this->fixture('sebelum-admin.sql')),
            $this->rapat(KueriExportIzin::sql(true)),
            'SQL export admin berubah lebih dari sekadar spasi. Perbedaan yang '.
            'diizinkan hanya indentasi di enam baris yang memang berbeda di kedua '.
            'teks lama.',
        );
    }

    public function test_jumlah_tanda_tanya_menentukan_daftar_parameter(): void
    {
        /*
         * INI YANG PALING GAMPANG RUSAK DIAM-DIAM. Pemanggil mengikat parameter
         * secara posisional: jalur pribadi mengirim tujuh nilai
         * ([Kode_Karyawan ×3, start, end, end, start]) dan jalur admin empat
         * ([start, end, end, start]).
         *
         * Satu tanda tanya bertambah atau hilang membuat seluruh sisanya
         * bergeser — tanggal masuk ke kolom kode karyawan. SQL Server tidak
         * selalu menolaknya; ia bisa saja mengembalikan nol baris, dan ekspor
         * kosong terbaca seperti "memang tidak ada data".
         */
        $this->assertSame(7, substr_count(KueriExportIzin::sql(false), '?'),
            'Jalur pribadi harus punya tepat 7 tanda tanya: 3 Kode_Karyawan + 4 tanggal.');

        $this->assertSame(4, substr_count(KueriExportIzin::sql(true), '?'),
            'Jalur admin harus punya tepat 4 tanda tanya: hanya tanggal.');
    }

    public function test_perbedaan_kedua_jalur_persis_yang_dimaksud(): void
    {
        $pribadi = KueriExportIzin::sql(false);
        $admin = KueriExportIzin::sql(true);

        $this->assertSame(3, substr_count($pribadi, 'k.Kode_Karyawan = ?'),
            'Ketiga cabang CTE jalur pribadi wajib disaring per karyawan.');
        $this->assertSame(0, substr_count($admin, 'k.Kode_Karyawan = ?'),
            'Jalur admin justru TIDAK menyaring per karyawan — kalau saringannya muncul, '.
            'admin cuma melihat datanya sendiri.');

        $this->assertSame(3, substr_count($admin, 'as nama_requester'),
            'Jalur admin butuh nama pemohon di ketiga cabang; tanpa itu kolomnya kosong '.
            'di berkas ekspor.');
        $this->assertSame(0, substr_count($pribadi, 'as nama_requester'),
            'Jalur pribadi tidak punya kolom itu — pemohonnya dirinya sendiri.');
    }

    public function test_ketiga_cabang_cte_tetap_utuh_di_kedua_jalur(): void
    {
        /*
         * Ekspor ini menggabungkan tiga sumber izin. Kehilangan satu cabang
         * tidak menimbulkan galat apa pun — berkasnya cuma kehilangan sebagian
         * baris, dan itu jenis kerusakan yang baru ketahuan berbulan kemudian.
         */
        foreach ([false, true] as $semua) {
            $sql = KueriExportIzin::sql($semua);
            $jalur = $semua ? 'admin' : 'pribadi';

            foreach ([
                'Transaksi_Cuti_Detail',
                'Transaksi_Sakit_Izin_Detail',
                'Transaksi_Terlambat_Pulang_Cepat_Detail',
            ] as $tabel) {
                /*
                 * Berbatas kata, bukan sekadar "mengandung": tanpa \b, nama yang
                 * DITAMBAHI akhiran (Transaksi_Sakit_Izin_Detail_X) tetap membuat
                 * penjaga ini hijau — diperiksa dengan menyabotase persis begitu.
                 */
                $this->assertSame(
                    1,
                    preg_match('/\b'.$tabel.'\b/', $sql),
                    "Cabang {$tabel} hilang dari jalur {$jalur}. Ekspor tetap jalan, cuma ".
                    'kehilangan sebagian barisnya diam-diam.',
                );
            }
        }
    }

    /**
     * Badan sebuah metode, dipotong dari kurung buka sampai kurung tutupnya.
     *
     * Dipotong per metode, bukan diperiksa seluruh berkas: AbsensiController
     * masih menyimpan potongan SQL ekspor lama dalam bentuk KOMENTAR, dan
     * penjaga yang menyapu seluruh berkas akan tersandung padanya lalu dimatikan
     * orang berikutnya karena dianggap rewel.
     */
    private function badanMetode(string $isi, string $nama): string
    {
        $awal = preg_match('/\n[ \t]*public function '.preg_quote($nama, '/').'\s*\(/', $isi, $m, PREG_OFFSET_CAPTURE)
            ? $m[0][1]
            : -1;
        $this->assertGreaterThan(-1, $awal, "Metode {$nama} hilang dari controller.");

        $buka = strpos($isi, '{', $awal);
        $dalam = 0;
        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $awal, $i - $awal + 1);
                }
            }
        }

        $this->fail("Kurung metode {$nama} tidak seimbang.");
    }

    public function test_kedua_metode_ekspor_tidak_lagi_memegang_sql_nya_sendiri(): void
    {
        $isi = (string) file_get_contents(base_path(self::CONTROLLER));

        foreach (['getExport' => 'false', 'getExportAdmin' => 'true'] as $metode => $arg) {
            $badan = $this->badanMetode($isi, $metode);

            $this->assertStringContainsString(
                "KueriExportIzin::sql({$arg})",
                $badan,
                "{$metode}() tidak lagi mengambil SQL-nya dari satu sumber.",
            );

            $this->assertDoesNotMatchRegularExpression(
                '/\$query\s*=\s*["\']/',
                $badan,
                "{$metode}() kembali menulis literal SQL-nya sendiri. Salinan kedua itu ".
                'yang membuat 431 baris harus dikoreksi dua kali.',
            );

            $this->assertStringNotContainsString(
                'as nama_requester',
                $badan,
                "{$metode}() kembali memegang potongan SQL ekspor.",
            );
        }
    }

    public function test_cte_daftar_izin_tidak_lagi_tersalin_dua_kali(): void
    {
        /*
         * Kembar yang berbeda, di berkas yang sama: getDataIzin dan
         * getDataIzinDH masing-masing menulis CTE-nya DUA KALI — sekali untuk
         * kueri halaman, sekali untuk kueri hitungan (233 dan 413 baris,
         * yang kedua identik byte per byte).
         *
         * Bahayanya bukan cuma boros: begitu kueri hitungan menyimpang dari
         * kueri halaman, jumlah halaman berhenti cocok dengan isinya — halaman
         * terakhir kosong, atau ada baris yang tak pernah bisa dijangkau.
         */
        $isi = (string) file_get_contents(base_path(self::CONTROLLER));

        /*
         * Kutipnya tidak ikut ditegaskan: Pint boleh mengubah "…" menjadi '…'
         * pada potongan yang tak punya escape, dan penjaga yang mematok kutip
         * akan merah karena perapian gaya, bukan karena kembarnya kembali.
         */
        foreach (['getDataIzin', 'getDataIzinDH'] as $metode) {
            $badan = $this->badanMetode($isi, $metode);

            $this->assertSame(
                1,
                preg_match_all('/\$cte\s*=\s*["\']/', $badan),
                "{$metode}() harus menyusun CTE-nya sekali saja.",
            );

            $this->assertSame(
                1,
                preg_match_all('/\$baseQuery\s*=\s*\$cte\./', $badan),
                "Kueri halaman {$metode}() harus dirakit dari \$cte, bukan menulis ulang CTE-nya.",
            );

            $this->assertSame(
                1,
                preg_match_all('/\$queryCount\s*=\s*\$cte\./', $badan),
                "Kueri hitungan {$metode}() harus dirakit dari \$cte yang SAMA dengan kueri ".
                'halamannya — itulah yang menjamin keduanya tak bisa lagi menyimpang.',
            );

            $this->assertSame(
                0,
                preg_match_all('/\$queryCount\s*=\s*["\']/', $badan),
                "Kueri hitungan {$metode}() kembali menulis CTE-nya sendiri.",
            );
        }
    }

    public function test_fixture_dan_rumahnya_tidak_kosong(): void
    {
        /*
         * Fixture yang terlanjur terpotong membuat kedua uji perbandingan di
         * atas hijau tanpa membuktikan apa pun. Pernah terjadi di repo ini.
         */
        $this->assertGreaterThan(20000, strlen($this->fixture('sebelum-pribadi.sql')));
        $this->assertGreaterThan(20000, strlen($this->fixture('sebelum-admin.sql')));
        $this->assertGreaterThan(
            15000,
            (int) filesize(base_path('app/Services/ESS/Attendance/Leave/Shared/KueriExportIzin.php')),
        );
    }
}
