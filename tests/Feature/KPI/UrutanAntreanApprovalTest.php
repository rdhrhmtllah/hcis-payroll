<?php

namespace Tests\Feature\KPI;

use Tests\TestCase;

/**
 * Dua antrean approval KPI mengurutkan barisnya dengan aturan yang sama.
 *
 * ── Aturannya, dan di mana ia tertulis ───────────────────────────────────────
 *
 * KpiApprovalController (jalur parameter) menuliskannya:
 *
 *     Queue   : tanggal & jam PENGAJUAN (create_at) terbaru di atas.
 *     History : yang TERAKHIR diproses (update_at) di atas.
 *
 * Itu aturan untuk keluarga layar yang sama, bukan khusus parameter.
 * PerformanceApprovalController dulu memakai update_at untuk KEDUANYA, sehingga
 * antrean yang belum diproses tersusun menurut kapan terakhir disentuh, bukan
 * kapan diajukan.
 *
 * ── Dua kunci yang wajib sama satu sama lain ─────────────────────────────────
 *
 * Halaman diambil SQL dengan OFFSET/FETCH menurut satu ORDER BY, lalu baris
 * CENTRAL digabung dan seluruhnya diurutkan ulang di PHP. Kalau kedua kunci itu
 * berbeda, isi halaman dan urutan yang ditampilkan berhenti sepakat.
 *
 * Versi lama jalur performance memakai `update_at ?? create_at` di PHP — dua
 * kolom berbeda dicampur dalam satu pengurutan, dan tak satu pun cocok dengan
 * ORDER BY-nya.
 *
 * ── Kenapa strtotime, bukan perbandingan teks ────────────────────────────────
 *
 * Kumpulan yang diurutkan menggabungkan DUA sumber: baris legacy dari SQL
 * mentah (terukur berbentuk '2026-04-15 15:26:33.980') dan baris CENTRAL dari
 * view N_HRIS_VW_Approval_Request_V2. Perbandingan teks hanya benar selama
 * keduanya merender waktu dengan bentuk yang persis sama — asumsi, bukan
 * jaminan.
 *
 * Uji ini tekstual karena kedua metodenya berupa endpoint besar yang menyentuh
 * basis data. Yang dijaga bukan gaya kode melainkan kesamaan kedua kunci itu.
 */
class UrutanAntreanApprovalTest extends TestCase
{
    private const CONTROLLER = [
        'parameter' => 'app/Http/Controllers/KPI/Approval/Workflow/KpiApprovalController.php',
        'performance' => 'app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php',
    ];

    private function isi(string $jalur): string
    {
        return (string) file_get_contents(base_path(self::CONTROLLER[$jalur]));
    }

    public function test_kedua_jalur_memilih_kolom_urut_dengan_aturan_yang_sama(): void
    {
        foreach (self::CONTROLLER as $jalur => $berkas) {
            $isi = $this->isi($jalur);

            $this->assertStringContainsString(
                "\$orderColumn = \$history ? 'cte2.update_at' : 'cte2.create_at';",
                $isi,
                "Jalur {$jalur} tidak lagi memakai aturan yang sama: queue diurut create_at, ".
                'history diurut update_at.',
            );

            $this->assertStringNotContainsString(
                'ORDER BY cte2.update_at DESC OFFSET',
                $isi,
                "Jalur {$jalur} kembali mematok update_at untuk KEDUA daftar. Antrean yang ".
                'belum diproses akan tersusun menurut kapan terakhir disentuh.',
            );
        }
    }

    public function test_kunci_php_sama_dengan_kunci_sql_di_kedua_jalur(): void
    {
        /*
         * INTI PERBAIKANNYA. Satu kunci untuk mengambil halaman, kunci lain
         * untuk menampilkannya, berarti halaman dan urutannya tidak sepakat.
         */
        foreach (self::CONTROLLER as $jalur => $berkas) {
            $isi = $this->isi($jalur);

            $this->assertStringContainsString(
                "\$mergedSortKey = \$history ? 'update_at' : 'create_at';",
                $isi,
                "Jalur {$jalur} memakai kunci pengurutan PHP yang berbeda dari ORDER BY-nya.",
            );

            $this->assertStringNotContainsString(
                '$r->update_at ?? $r->create_at',
                $isi,
                "Jalur {$jalur} kembali mencampur dua kolom dalam satu pengurutan.",
            );
        }
    }

    public function test_kedua_jalur_membandingkan_waktu_bukan_teks(): void
    {
        foreach (self::CONTROLLER as $jalur => $berkas) {
            $this->assertStringContainsString(
                'strtotime((string) ($r->{$mergedSortKey} ?? \'\'))',
                $this->isi($jalur),
                "Jalur {$jalur} membandingkan waktu sebagai teks. Kumpulannya menggabungkan ".
                'dua sumber yang bentuk waktunya tidak dijamin sama.',
            );
        }
    }

    public function test_baris_central_tetap_hanya_di_halaman_pertama(): void
    {
        /*
         * Keputusan pilot yang terdokumentasi di kedua berkas: baris CENTRAL
         * di-prepend HANYA pada halaman pertama, supaya paginasi legacy yang
         * sudah berjalan tidak terganggu. Menghapus penjaga halaman itu membuat
         * baris central muncul berulang di SETIAP halaman.
         */
        foreach (self::CONTROLLER as $jalur => $berkas) {
            $this->assertStringContainsString(
                'if ($page <= 1) {',
                $this->isi($jalur),
                "Jalur {$jalur} tidak lagi membatasi baris CENTRAL ke halaman pertama.",
            );
        }
    }

    public function test_aturannya_tertulis_di_kedua_berkas(): void
    {
        /*
         * Aturan urutan ini tidak bisa disimpulkan dari kodenya sendiri —
         * `create_at` versus `update_at` sama-sama masuk akal sampai seseorang
         * menuliskan yang mana untuk daftar yang mana. Dulu hanya satu berkas
         * yang menuliskannya, dan yang tidak menulis itulah yang menyimpang.
         */
        foreach (self::CONTROLLER as $jalur => $berkas) {
            $isi = $this->isi($jalur);

            $this->assertStringContainsString(
                'create_at) terbaru di atas',
                $isi,
                "Aturan urutannya tidak tertulis di jalur {$jalur}.",
            );

            $this->assertStringContainsString(
                'TERAKHIR diproses (update_at) di atas',
                $isi,
                "Aturan urutan history tidak tertulis di jalur {$jalur}.",
            );
        }
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(4000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
