<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Layar kosong Form KPI Tim harus menyebut SEBABNYA.
 *
 * ── Keluhan yang memulai ini ─────────────────────────────────────────────────
 *
 * Admin membuat beberapa penjadwalan PERORANGAN, memasukkan seluruh anggota
 * timnya, lalu halaman Form KPI Tim tetap berkata "Tidak Ada Penjadwalan Aktif
 * — belum ada periode KPI yang aktif saat ini". Kesimpulan wajarnya: fiturnya
 * rusak. Membuka satu jadwal GLOBAL membuatnya jalan, yang memperkuat dugaan itu.
 *
 * Pemeriksaan menunjukkan payungTimBerlaku() sebenarnya BENAR — jalur cadangan
 * ke jadwal perorangan berfungsi. Yang salah adalah layarnya menyangkal
 * keberadaan jadwal yang baru saja dibuat, alih-alih menyebut syarat yang tak
 * terpenuhi.
 *
 * ── Syarat yang tak terlihat ─────────────────────────────────────────────────
 *
 * Dua, dan keduanya tak tampak dari layar: Flag_Aktif='Y' DAN hari ini berada
 * di antara Jadwal_Mulai dan Jadwal_Akhir. Yang kedua paling sering terlewat,
 * karena orang memikirkan PERIODE (bulan yang dinilai) sementara gerbangnya
 * memakai JENDELA PENGISIAN — dua kolom berbeda yang bisa berjauhan.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Saat tak ada payung, sebabnya SPESIFIK per penjadwalan dan menyebut tanggal
 * yang menutup pintunya — bukan kalimat umum yang sama untuk semua keadaan.
 */
class FormKpiTimSebabKosongTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array<int,array<string,mixed>> */
    private function sebab(): array
    {
        $metode = new ReflectionMethod(FormKpiController::class, 'sebabTanpaPayungTim');
        $metode->setAccessible(true);

        return $metode->invoke(app(FormKpiController::class), date('Y-m-d'));
    }

    public function test_jendela_yang_sudah_tertutup_disebut_beserta_tanggalnya(): void
    {
        $nama = 'Uji jendela tertutup '.bin2hex(random_bytes(3));
        $akhir = now()->subDays(9)->format('Y-m-d');

        DB::table('KPI_Master_Penjadwalan')->insert([
            'Nama_Kegiatan' => $nama,
            'Tahun' => date('Y'),
            'Periode' => date('Y-m'),
            'Flag_Form_KPI' => 'Y',
            'Flag_Penilaian_KPI' => 'T',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDays(20)->format('Y-m-d'),
            'Jadwal_Akhir' => $akhir,
        ]);

        $baris = collect($this->sebab())->firstWhere('nama', $nama);

        $this->assertNotNull($baris, 'Penjadwalan yang ada tidak disebut sama sekali di layar kosong.');
        $this->assertTrue($baris['perorangan'], 'Penjadwalan perorangan tidak ditandai sebagai perorangan.');
        $this->assertSame(
            "Jendela pengisian sudah ditutup {$akhir}",
            $baris['sebab'],
            'Sebabnya harus menyebut TANGGAL yang menutup pintunya. Tanpa tanggal itu, admin '.
            'tetap tak tahu apa yang harus diperbaiki dan menyimpulkan fiturnya rusak.',
        );
    }

    public function test_penjadwalan_nonaktif_dibedakan_dari_jendela(): void
    {
        $nama = 'Uji nonaktif '.bin2hex(random_bytes(3));

        // Jendelanya SEDANG berjalan — jadi satu-satunya sebab yang tersisa
        // adalah nonaktifnya. Kalau keduanya tidak dibedakan, admin akan
        // mengubah tanggal padahal yang perlu diubah sakelarnya.
        DB::table('KPI_Master_Penjadwalan')->insert([
            'Nama_Kegiatan' => $nama,
            'Tahun' => date('Y'),
            'Periode' => date('Y-m'),
            'Flag_Form_KPI' => 'Y',
            'Flag_Penilaian_KPI' => 'T',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'T',
            'Jadwal_Mulai' => now()->subDay()->format('Y-m-d'),
            'Jadwal_Akhir' => now()->addDay()->format('Y-m-d'),
        ]);

        $baris = collect($this->sebab())->firstWhere('nama', $nama);

        $this->assertNotNull($baris, 'Penjadwalan nonaktif tidak disebut sama sekali.');
        $this->assertSame('Penjadwalan dinonaktifkan', $baris['sebab']);
    }
}
