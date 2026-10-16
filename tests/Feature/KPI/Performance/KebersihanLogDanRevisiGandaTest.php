<?php

namespace Tests\Feature\KPI\Performance;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua kelalaian yang sama-sama tak terlihat sampai terlambat.
 *
 * ── 1. Alur normal ditulis sebagai ERROR ────────────────────────────────────
 *
 * "KEPUTUSAN: TIDAK ada draf yang bisa diedit, masuk ke jalur INSERT" adalah
 * jalur NORMAL — pengisian pertama pada sebuah periode memang belum punya draf.
 * Ditulis sebagai ERROR, ia muncul 32 kali dalam satu hari uji dan mendorong
 * galat sungguhan keluar dari pandangan. Log yang berisik bukan sekadar tidak
 * rapi; ia membuat log berhenti dipakai.
 *
 * ── 2. Revisi terhitung dua kali ────────────────────────────────────────────
 *
 * Penggantian pengajuan hanya menyetel Flag_Aktif='T' sementara Status tetap
 * NULL. Kueri yang menyaring Status saja karena itu MASIH melihat header lama,
 * dan SUM(Nilai_Actual) menjumlahkan dua header untuk periode yang sama.
 *
 * Hari ini efeknya nol — nol baris berada dalam keadaan itu — jadi tidak ada
 * angka yang salah sekarang. Justru itu alasannya dijaga: cacat yang belum
 * menggigit tidak akan ketahuan lewat pengujian manual, dan baru muncul pada
 * revisi disetujui yang pertama.
 */
class KebersihanLogDanRevisiGandaTest extends TestCase
{
    private const CONTROLLER = 'app/Http/Controllers/KPI/Performance/Update/UpdatePerformanceController.php';

    private const DASHBOARD = 'app/Http/Controllers/KPI/Performance/Dashboard/Executive/DashboardCeoController.php';

    public function test_alur_normal_tidak_ditulis_sebagai_galat(): void
    {
        $isi = file_get_contents(base_path(self::CONTROLLER));

        foreach ([
            'KEPUTUSAN: TIDAK ada draf yang bisa diedit' => 3,
            'HASIL: TIDAK ditemukan draf yang bisa diedit' => 3,
        ] as $kalimat => $jumlahDiharapkan) {
            $posisi = [];
            $dari = 0;
            while (($p = strpos($isi, $kalimat, $dari)) !== false) {
                $posisi[] = $p;
                $dari = $p + 1;
            }

            // SETIAP tempat, bukan yang pertama saja. Kalimat ini disalin ke tiga
            // cabang; menjaga satu cabang saja meninggalkan dua yang bebas
            // menyimpang — pola yang sudah berulang di proyek ini.
            $this->assertCount(
                $jumlahDiharapkan,
                $posisi,
                "Jumlah pemanggilan '{$kalimat}' berubah; penjaga ini perlu ditinjau.",
            );

            foreach ($posisi as $p) {
                // Potongan SEBELUM kalimatnya: di situlah nama levelnya berada.
                $sebelum = substr($isi, max(0, $p - 200), min(200, $p));

                $this->assertStringNotContainsString(
                    '->error(',
                    $sebelum,
                    "'{$kalimat}' pada offset {$p} masih ditulis sebagai ERROR padahal itu alur normal.",
                );
                $this->assertStringNotContainsString(
                    '->warning(',
                    $sebelum,
                    "'{$kalimat}' pada offset {$p} masih ditulis sebagai WARNING padahal itu alur normal.",
                );
            }
        }
    }

    public function test_penjumlahan_realisasi_dashboard_menyaring_header_yang_sudah_digantikan(): void
    {
        $isi = file_get_contents(base_path(self::DASHBOARD));

        // Setiap kueri yang menjumlahkan/mengumpulkan dari KPI_Update_Performance_Detail
        // wajib menuntut headernya masih aktif.
        $jumlahVerifikasi = substr_count($isi, "->where('kup.Flag_Verifikasi', 'Y')");
        $jumlahAktif = substr_count($isi, "->where('kup.Flag_Aktif', 'Y')");

        $this->assertGreaterThan(0, $jumlahVerifikasi, 'Pola kueri berubah; penjaga ini perlu ditinjau.');
        $this->assertSame(
            $jumlahVerifikasi,
            $jumlahAktif,
            "Ada {$jumlahVerifikasi} kueri yang menuntut Flag_Verifikasi tetapi hanya {$jumlahAktif} ".
            'yang menuntut Flag_Aktif. Header lama yang sudah digantikan revisi akan ikut terjumlah, '.
            'dan angkanya membengkak tanpa satu pun galat.',
        );
    }

    public function test_keadaan_yang_memicu_hitung_ganda_memang_mungkin_terjadi(): void
    {
        // Bukan hipotesis: kolomnya memang mengizinkan kombinasi itu. Uji ini
        // menjaga agar penjaga di atas tidak dibuang kelak dengan alasan
        // "keadaannya mustahil".
        $kolom = collect(DB::select(
            'SELECT c.name, c.is_nullable FROM sys.columns c WHERE c.object_id = OBJECT_ID(?)',
            ['KPI_Update_Performance'],
        ))->keyBy('name');

        $this->assertTrue($kolom->has('Flag_Aktif'), 'Kolom Flag_Aktif hilang.');
        $this->assertTrue($kolom->has('Status'), 'Kolom Status hilang.');
        $this->assertSame(
            1,
            (int) $kolom['Status']->is_nullable,
            'Status tidak lagi nullable — kombinasi "Flag_Aktif=T tetapi Status NULL" mungkin sudah mustahil, '.
            'dan penjaga hitung-ganda perlu ditinjau ulang.',
        );
    }
}
