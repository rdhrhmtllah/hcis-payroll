<?php

namespace Tests\Unit\VISITOR\Guest\Visit;

use PHPUnit\Framework\TestCase;

/**
 * Registrasi tamu hanya boleh punya SATU penulis.
 *
 * Ada dua pintu yang membuat kunjungan tamu — layar HCIS dan API mitra B2B — dan
 * repo ini sudah berulang kali kehilangan aturan karena ia hanya terpasang di
 * salah satu jalur yang setara. Penjaga ini menagih bahwa insert baris tamu
 * tetap hidup di RegistrasiTamuService saja, sehingga setiap aturan baru otomatis
 * berlaku bagi kedua pintu.
 *
 * Cakupannya sengaja dibatasi ke lapisan Http dan Services: perintah konsol demo
 * memang memalsukan data historis dan tidak melewati aturan pengajuan.
 */
class RegistrasiTamuSatuPenulisTest extends TestCase
{
    private const TABEL = [
        'N_EMI_VISITOR_Transaksi_Tamu',
        'N_EMI_VISITOR_Transaksi_Tamu_Detail',
    ];

    private const PENULIS_SAH = 'app/Services/VISITOR/Guest/Visit/RegistrasiTamuService.php';

    public function test_insert_baris_tamu_hanya_ada_di_satu_berkas(): void
    {
        $pelanggar = [];

        foreach ($this->berkasProduksi() as $berkas) {
            $isi = (string) file_get_contents($berkas);
            $relatif = $this->relatif($berkas);

            if ($relatif === self::PENULIS_SAH) {
                continue;
            }

            foreach (self::TABEL as $tabel) {
                // Menyasar penulisan baris baru saja. Pembacaan dan pembaruan
                // status (check-in, pembatalan) memang tersebar dan bukan sasaran
                // penjaga ini.
                if (preg_match('/DB::table\(\s*[\'"]'.preg_quote($tabel, '/').'[\'"]\s*\)\s*->\s*insert(GetId)?\(/', $isi)) {
                    $pelanggar[] = $relatif.' → '.$tabel;
                }
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Baris tamu ditulis di luar RegistrasiTamuService:\n".implode("\n", $pelanggar),
        );
    }

    /**
     * DDL-2026-121: Tamu_Kategori (VISITOR/MITRA) dihitung sistem dari rentang
     * estimasi, dan yang berbahaya adalah PENGHITUNG kedua — ia akan memakai
     * ambang 24 jamnya sendiri, lalu dua jalur yang setara mulai berselisih.
     *
     * Yang ditagih karena itu adalah tempat nilainya LAHIR, bukan setiap
     * penyebutan namanya. DDL-2026-126 menyalin kategori apa adanya dari header
     * ke N_VISITOR_Tamu_Efektif supaya palang tahu MITRA berlaku lebih dari
     * sehari; salinan tidak bisa berselisih dengan sumbernya, dan melarangnya
     * hanya memaksa orang berikutnya mematikan penjaga ini.
     */
    public function test_kategori_tamu_hanya_dihitung_di_satu_berkas(): void
    {
        $penghitung = [];

        foreach ($this->berkasProduksi() as $berkas) {
            $isi = (string) file_get_contents($berkas);

            // Hanya pemanggil penghitungnya. Konstanta KATEGORI_* sengaja TIDAK
            // ikut: laporan dan export membacanya untuk menyaring dan memberi
            // label, dan itu tidak bisa berselisih dengan sumbernya.
            if (preg_match('/TipeTamu::kategori\s*\(/', $isi)) {
                $penghitung[] = $this->relatif($berkas);
            }
        }

        $this->assertSame(
            [self::PENULIS_SAH],
            $penghitung,
            "Kategori tamu dihitung di luar RegistrasiTamuService:\n".implode("\n", $penghitung),
        );
    }

    /**
     * Ambang MITRA hanya boleh hidup di TipeTamu.
     *
     * Penulis kedua yang paling mungkin tidak memanggil apa pun — ia menyalin
     * rumusnya ("lebih dari 24 jam") dan sejak itu dua tempat harus diubah
     * bersamaan. Menagih ambangnya membuat salinan seperti itu ketahuan.
     */
    public function test_ambang_mitra_hanya_hidup_di_tipe_tamu(): void
    {
        $pemakai = [];

        foreach ($this->berkasProduksi() as $berkas) {
            $isi = (string) file_get_contents($berkas);

            // Hanya konstanta bernamanya. Angka 86400 mentah sengaja tidak
            // dicari: ia dipakai sebagai TTL cache di banyak modul lain, dan
            // penjaga yang berderau akan dimatikan orang berikutnya.
            if (preg_match('/BATAS_MITRA_DETIK/', $isi)) {
                $pemakai[] = $this->relatif($berkas);
            }
        }

        $this->assertSame(
            ['app/Services/VISITOR/Guest/Visit/TipeTamu.php'],
            $pemakai,
            "Ambang MITRA disalin di luar TipeTamu:\n".implode("\n", $pemakai),
        );
    }

    /**
     * Sisi lain dari penjaga di atas: berkas selain RegistrasiTamuService boleh
     * menulis kolomnya, tetapi hanya dengan MENYALIN dari header — bukan
     * menyusun nilainya sendiri.
     */
    public function test_penulis_lain_hanya_menyalin_kategori_dari_header(): void
    {
        $diperiksa = 0;

        foreach ($this->berkasProduksi() as $berkas) {
            $relatif = $this->relatif($berkas);

            if ($relatif === self::PENULIS_SAH) {
                continue;
            }

            if (! preg_match_all('/[\'"]Tamu_Kategori[\'"]\s*=>\s*([^,\n]+)/', (string) file_get_contents($berkas), $cocok)) {
                continue;
            }

            foreach ($cocok[1] as $nilai) {
                $diperiksa++;
                $this->assertMatchesRegularExpression(
                    '/^\$[A-Za-z_][A-Za-z0-9_]*->Tamu_Kategori/',
                    trim($nilai),
                    $relatif.' menyusun Tamu_Kategori sendiri, bukan menyalin dari header: '.trim($nilai),
                );
            }
        }

        // Penjaga yang tidak pernah memeriksa apa pun akan tetap hijau saat
        // proyeksinya dihapus. Pastikan ia benar-benar menemukan salinannya.
        $this->assertGreaterThan(0, $diperiksa, 'Tidak ada penyalin Tamu_Kategori yang ditemukan; penjaga ini jadi hampa.');
    }

    public function test_kedua_pintu_memakai_penulis_yang_sama(): void
    {
        $pintu = [
            'app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php',
            'app/Http/Controllers/VISITOR/Guest/Visit/TamuB2BApiController.php',
        ];

        foreach ($pintu as $berkas) {
            $penuh = $this->akar().'/'.$berkas;

            $this->assertFileExists($penuh);
            $this->assertStringContainsString(
                'RegistrasiTamuService',
                (string) file_get_contents($penuh),
                $berkas.' tidak memakai RegistrasiTamuService.',
            );
        }
    }

    /** @return list<string> */
    private function berkasProduksi(): array
    {
        $hasil = [];

        foreach (['app/Http', 'app/Services'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->akar().'/'.$dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $berkas) {
                if ($berkas->isFile() && $berkas->getExtension() === 'php') {
                    $hasil[] = $berkas->getPathname();
                }
            }
        }

        return $hasil;
    }

    private function akar(): string
    {
        return dirname(__DIR__, 5);
    }

    private function relatif(string $berkas): string
    {
        return str_replace('\\', '/', substr($berkas, strlen($this->akar()) + 1));
    }
}
