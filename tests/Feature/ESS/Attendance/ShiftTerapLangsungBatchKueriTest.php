<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penerapan shift langsung: biaya per KARYAWAN, bukan per (karyawan x tanggal).
 *
 * ── Skalanya, dari production ────────────────────────────────────────────────
 *
 * Satu permintaan `leader-shift/submit` pada 9 September 2026 menjalankan
 * 3.289 kueri dalam 114 detik, dengan 108.849 ms waktu DB — yaitu ~33 ms per
 * kueri. Angka itu yang menentukan diagnosisnya: SQL-nya TIDAK lambat, yang
 * mahal adalah 3.289 kali bolak-balik jaringan. Semuanya berada di dalam satu
 * transaksi, dan karena RCSI mati di basis data ini, selama ~108 detik itu
 * pembaca tabel shift ikut mengantre di belakangnya.
 *
 * ── Bentuk yang dijaga ───────────────────────────────────────────────────────
 *
 * Dua kueri di dalam applyShiftDirectForExistingTransaction TIDAK bergantung
 * pada tanggal: mencari Id_Detail (hanya butuh No_Transaksi + Kode_Karyawan)
 * dan menandai baris Det. Dipanggil dari perulangan bersarang anggota x
 * tanggal, keduanya terulang untuk setiap tanggal tanpa alasan.
 *
 * Uji ini memanggil versi batch dengan BANYAK tanggal dan menegaskan kedua
 * kueri itu tetap satu kali. Penerapan ke tabel master memang per tanggal —
 * itu kerja nyata dan sengaja tidak dihitung di sini.
 *
 * ── Kenapa dihitung, bukan diukur waktunya ───────────────────────────────────
 *
 * Waktu berubah menurut mesin dan isi cache; bentuk tidak. Yang harus tetap
 * benar adalah: berapa pun tanggalnya, pencarian Id_Detail sekali dan
 * penandaan Det sekali.
 */
class ShiftTerapLangsungBatchKueriTest extends TestCase
{
    use DatabaseTransactions;

    public function test_pencarian_detail_dan_penandaan_tidak_tumbuh_per_tanggal(): void
    {
        $kode = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('UserID_Absen')
            ->orderBy('Kode_Karyawan')
            ->value('Kode_Karyawan');

        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan aktif ber-UserID_Absen di data ini.');
        }

        $kode = trim((string) $kode);
        $noTransaksi = 'UJI-BATCH-'.bin2hex(random_bytes(4));

        // Satu baris Detail supaya cabang penandaan Det benar-benar dilewati.
        // Tanpa ini detailIds kosong, blok UPDATE dilompati, dan penjaga ini
        // akan hijau tanpa pernah menyentuh yang dijaganya.
        $idDetail = DB::table('N_HRIS_Transaksi_Shift_Detail')->insertGetId([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan' => $kode,
            'Target_Table' => 'SEMENTARA',
        ]);
        $this->assertNotNull($idDetail, 'Gagal menyemai baris Detail; uji ini tak menguji apa pun.');

        $idShift = DB::table('HRIS_Shift_Kerja')->value('ID_Shift');
        if (! $idShift) {
            $this->markTestSkipped('Tidak ada master shift di data ini.');
        }

        // Enam tanggal jauh di depan supaya tidak menyentuh data absensi nyata.
        $dateItems = [];
        $statusHari = [];
        for ($i = 1; $i <= 6; $i++) {
            $tgl = now()->addYears(3)->addDays($i)->format('Y-m-d');
            $dateItems[] = ['tanggal' => $tgl, 'id_shift' => (int) $idShift];
            $statusHari[$tgl] = 'KERJA'; // pra-hitung -> UDF status hari tidak dipanggil
        }

        $cariDetail = 0;
        $tandaiDet = 0;
        DB::listen(function ($q) use (&$cariDetail, &$tandaiDet) {
            $s = strtolower(preg_replace('/\s+/', ' ', $q->sql));
            if (str_starts_with($s, 'select') && str_contains($s, 'n_hris_transaksi_shift_detail')) {
                $cariDetail++;
            }
            if (str_starts_with($s, 'update') && str_contains($s, 'n_hris_transaksi_shift_det]')) {
                $tandaiDet++;
            }
        });

        app(ShiftRequestService::class)->applyShiftDirectBatchForExistingTransaction(
            $noTransaksi,
            $kode,
            $dateItems,
            'SEMENTARA',
            null,
            true,
            $statusHari,
        );

        $this->assertSame(
            1,
            $cariDetail,
            "Pencarian Id_Detail dijalankan {$cariDetail} kali untuk 6 tanggal, seharusnya 1. ".
            'Kueri itu hanya bergantung pada (No_Transaksi, Kode_Karyawan) — mengulangnya per '.
            'tanggal menambah bolak-balik jaringan DI DALAM transaksi yang sedang menahan lock.',
        );

        $this->assertSame(
            1,
            $tandaiDet,
            "Penandaan Det dijalankan {$tandaiDet} kali untuk 6 tanggal, seharusnya 1 ".
            'UPDATE ber-whereIn tanggal.',
        );
    }
}
