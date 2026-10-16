<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Leave\Shared\TanggalTransaksiIzin;
use Tests\TestCase;

/**
 * Tanggal detail transaksi izin dijawab di satu rumah.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * getTanggal() tersalin di LIMA controller, dan kelimanya sudah menyimpang
 * bertiga arah: dua bentuk peta prefiks (3 versus 6) dan tiga bentuk balikan
 * (array terformat, teks saja, array bertanggal mentah). Satu nama, tiga
 * kontrak.
 *
 * Salinan dengan peta paling lengkap justru yang balikannya paling miskin, jadi
 * tak ada satu pun yang bisa disebut "yang benar" — itulah tanda bahwa
 * perbedaannya tumbuh dari penyalinan, bukan dari keputusan.
 *
 * ── Cacat kecil yang ikut ditutup ────────────────────────────────────────────
 *
 * Di keempat salinan yang mengembalikan array, $tanggal diinisialisasi null
 * tetapi $jumlahHari TIDAK. Pada prefiks di luar peta, barisnya membaca variabel
 * yang tak pernah ditulis. Laravel mengubah peringatan PHP menjadi
 * ErrorException, jadi yang terjadi bukan null melainkan lemparan yang ditelan
 * catch pemanggil — dan seluruh daftarnya gagal, bukan satu barisnya.
 *
 * Tidak sedang menyala: view N_HRIS_VW_All_Transactions, sumber pemanggil
 * terbanyak, hanya memuat CT, IS, dan TP. Ia menyala pada jenis izin keempat
 * yang ditambahkan tanpa menyentuh kelima peta itu.
 */
class TanggalTransaksiIzinSatuRumahTest extends TestCase
{
    /** Kelima berkas yang dulu memegang salinannya, dan bentuk yang dipakainya. */
    private const BEKAS_PEMILIK_SALINAN = [
        'app/Http/Controllers/ESS/Attendance/Leave/Admin/AdminCutiController.php' => 'TanggalTransaksiIzin::ringkas(',
        'app/Http/Controllers/ESS/Attendance/Leave/Personal/CutiController.php' => 'TanggalTransaksiIzin::ringkas(',
        'app/Http/Controllers/ESS/Attendance/Leave/Approval/IzinAtasanApprovalController.php' => 'TanggalTransaksiIzin::teks(',
        'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php' => 'TanggalTransaksiIzin::teks(',
        'app/Http/Controllers/ESS/Dashboard/Personal/uDashController.php' => 'TanggalTransaksiIzin::ringkas($tx->No_Transaksi, false)',
    ];

    /**
     * Pelanggaran yang SUDAH ADA sebelum PR ini, dicatat supaya penjaga di bawah
     * bisa hijau tanpa menyapunya di bawah karpet.
     *
     * parseSubTipe() hanya didefinisikan di IzinAtasanApprovalController. Di
     * AdminCutiController (tiga titik) dan CutiController, syarat
     * `method_exists($this, 'parseSubTipe')` karena itu SELALU salah, dan
     * 'Tipe_Izin2' berisi kode mentah ('izinFull', 'pulangCepat') alih-alih
     * labelnya ('Izin Pribadi', 'Izin Pulang Cepat').
     *
     * Sebagian besar layar menyelamatkan diri lewat pemeta label di sisi
     * front-end (getJenisIzinText), tetapi ada dua tempat di CutiAdmin.vue yang
     * menampilkan nilainya apa adanya. Memperbaikinya berarti mengubah apa yang
     * terbaca pengguna, dan itu keputusan tersendiri — bukan bagian dari
     * pemusatan getTanggal(). Dicatat di sini, bukan dihapus, supaya ia tetap
     * terlihat.
     *
     * Daftar ini hanya boleh MENYUSUT.
     */
    private const TERCATAT_BELUM_DIPERBAIKI = [
        'app/Http/Controllers/ESS/Attendance/Leave/Admin/AdminCutiController.php::parseSubTipe',
        'app/Http/Controllers/ESS/Attendance/Leave/Personal/CutiController.php::parseSubTipe',
    ];

    public function test_prefiks_tak_dikenal_tetap_mengisi_jumlah_hari(): void
    {
        /*
         * INTI PERBAIKANNYA, dan bisa diuji tanpa menyentuh basis data: prefiks
         * di luar peta pulang lebih awal, sebelum kueri apa pun.
         *
         * Yang ditegaskan bukan cuma nilainya melainkan KEBERADAAN kuncinya.
         * Versi lama membaca $jumlahHari yang tak pernah ditulis; di bawah
         * penanganan galat Laravel itu berarti lemparan, bukan null.
         */
        foreach (['ZZ-0001', 'AK2026-1', 'PK-9', '', 'X'] as $noTransaksi) {
            $hasil = TanggalTransaksiIzin::ringkas($noTransaksi);

            $this->assertArrayHasKey('jumlahHari', $hasil, "jumlahHari hilang untuk '{$noTransaksi}'.");
            $this->assertArrayHasKey('tanggal', $hasil);
            $this->assertSame(0, $hasil['jumlahHari']);
            $this->assertNull($hasil['tanggal']);
        }
    }

    public function test_prefiks_tak_dikenal_sama_saja_pada_mode_mentah(): void
    {
        $hasil = TanggalTransaksiIzin::ringkas('ZZ-0001', false);

        $this->assertSame(0, $hasil['jumlahHari']);
        $this->assertNull($hasil['tanggal']);
    }

    public function test_teks_untuk_prefiks_tak_dikenal_bernilai_null(): void
    {
        /*
         * IzinAtasanApproval memakai bentuk ini sebagai `… ?: '-'`, jadi null
         * harus tetap null — bukan larik kosong, yang juga falsy tetapi
         * mengubah tipe yang diterima pemanggil.
         */
        $this->assertNull(TanggalTransaksiIzin::teks('ZZ-0001'));
    }

    public function test_peta_prefiks_adalah_gabungan_kelima_salinan(): void
    {
        /*
         * IP, TL, dan PC hanya dikenal salinan IzinAtasan. Menggabungkannya
         * menghapus pertanyaan "kenapa satu layar mengenali dan empat lainnya
         * tidak" — dan inert pada data sekarang, karena tak ada baris berprefiks
         * itu.
         */
        $isi = (string) file_get_contents(base_path(
            'app/Services/ESS/Attendance/Leave/Shared/TanggalTransaksiIzin.php',
        ));

        foreach (['CT', 'IS', 'IP', 'TP', 'TL', 'PC'] as $prefiks) {
            $this->assertStringContainsString(
                "'{$prefiks}' => [",
                $isi,
                "Prefiks {$prefiks} hilang dari peta. Jenis izin yang prefiksnya tidak "
                .'terdaftar kehilangan tanggalnya di SELURUH layar, diam-diam.',
            );
        }
    }

    public function test_tidak_ada_lagi_salinan_di_kelima_controller(): void
    {
        foreach (self::BEKAS_PEMILIK_SALINAN as $berkas => $bentuk) {
            $isi = (string) file_get_contents(base_path($berkas));

            $this->assertDoesNotMatchRegularExpression(
                '/function getTanggal\s*\(/',
                $isi,
                "{$berkas} memegang salinannya sendiri lagi. Kelima salinan lama sudah "
                .'menyimpang bertiga arah; salinan keenam akan menambah arah baru.',
            );

            /*
             * Yang dijaga PETANYA, bukan penyebutan tabelnya: CutiController
             * memang menulis ke Transaksi_Cuti_Det saat menyimpan pengajuan,
             * dan itu sah. Penjaga yang menyasar nama tabel akan merah karena
             * hal yang benar — versi pertama uji ini begitu.
             */
            foreach (['$tableMap', '$tanggalMap'] as $penandaPeta) {
                $this->assertStringNotContainsString(
                    $penandaPeta,
                    $isi,
                    "{$berkas} kembali menulis peta prefiksnya sendiri ({$penandaPeta}).",
                );
            }

            $this->assertStringContainsString(
                $bentuk,
                $isi,
                "{$berkas} tidak lagi memakai bentuk balikan yang dulu dipakainya "
                ."({$bentuk}). Mengubah bentuknya diam-diam mengubah apa yang diterima "
                .'pemanggil.',
            );

            /*
             * Kehadiran saja tidak cukup. uDash punya DUA titik panggil, dan
             * versi pertama penjaga ini hijau ketika salah satunya diam-diam
             * beralih ke tanggal terformat — sisanya masih memakai bentuk yang
             * benar, jadi assertStringContainsString tetap puas.
             *
             * Layar itu MEMBANDINGKAN tanggal; sekali ia menerima teks
             * terformat ("1 s/d 3 Mei 2026"), Carbon::parse gagal dan barisnya
             * hilang dari kalender tanpa satu pun galat.
             */
            if (str_contains($bentuk, 'false')) {
                $this->assertSame(
                    0,
                    preg_match_all('/ringkas\(\$tx->No_Transaksi\)/', $isi),
                    "{$berkas} punya titik panggil yang memakai tanggal TERFORMAT. ".
                    'Layar itu membandingkan tanggal, bukan menampilkannya.',
                );
            }
        }
    }

    public function test_setiap_method_exists_menunjuk_metode_yang_benar_benar_ada(): void
    {
        /*
         * JEBAKAN YANG NYARIS MEMAKAN PR INI SENDIRI.
         *
         * AdminCutiController menulis:
         *
         *     $tanggalDet = method_exists($this, 'getTanggal') ? … : …;
         *
         * Syarat itu SELALU benar selama metodenya ada di kelas yang sama, jadi
         * cabang cadangannya kode mati. Begitu metodenya pindah ke rumah
         * bersama, syaratnya berbalik menjadi selalu SALAH — dan tanggalReal
         * serta jumlah_hari diam-diam menjadi null dan 0, tanpa satu pun galat.
         *
         * Pola yang sama masih dipakai untuk parseIzin dan parseSubTipe. Uji ini
         * membuat pemindahan berikutnya MERAH alih-alih senyap.
         */
        $bermasalah = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Controllers'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            /*
             * Komentar dibuang lebih dulu. Versi pertama penjaga ini merah
             * karena membaca penjelasannya sendiri — sebuah komentar yang
             * MENYEBUT method_exists() dihitung sebagai pemakaian.
             */
            $isi = (string) php_strip_whitespace($berkas->getPathname());

            if (! preg_match_all("/method_exists\(\\\$this,\s*'(\w+)'\)/", $isi, $m)) {
                continue;
            }

            $rel = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $berkas->getPathname());

            foreach (array_unique($m[1]) as $metode) {
                if (preg_match('/function '.preg_quote($metode, '/').'\s*\(/', $isi)) {
                    continue;
                }
                if (in_array("{$rel}::{$metode}", self::TERCATAT_BELUM_DIPERBAIKI, true)) {
                    continue;
                }
                $bermasalah[] = "{$rel}::{$metode}()";
            }
        }

        $this->assertSame(
            [],
            $bermasalah,
            "method_exists(\$this, …) menunjuk metode yang sudah tidak ada:\n  "
            .implode("\n  ", $bermasalah)."\n"
            .'Syarat semacam itu tidak melempar apa-apa — ia hanya berbalik menjadi '
            .'selalu salah, dan cabang cadangannya mengambil alih tanpa suara. '
            .'Buang syaratnya bersama metodenya, atau perbarui rujukannya.',
        );
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(5000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
