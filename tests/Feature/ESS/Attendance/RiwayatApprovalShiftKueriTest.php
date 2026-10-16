<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Riwayat approval shift: baris tracking diambil SEKALI untuk satu halaman.
 *
 * ── Skalanya, dari production ────────────────────────────────────────────────
 *
 * `shift-approval/history` pada 9 September 2026: rata-rata 52 kueri per
 * permintaan dengan 11.394 ms waktu DB, dan **100% waktu permintaannya habis di
 * basis data**. Median 11,6 detik — terburuk dari seluruh endpoint; 39 dari 53
 * permintaannya di atas 10 detik.
 *
 * Yang penting: kueri terlambat hanya 14% dari total waktu DB, jadi tidak ada
 * satu kueri berat yang bisa disalahkan. Bebannya MERATA di 52 kueri — bentuk
 * khas N+1, bukan kueri buruk.
 *
 * ── Sumbernya ────────────────────────────────────────────────────────────────
 *
 * Tiap transaksi yang ditampilkan memanggil ApprovalDetailService::
 * getApproverHistory() sendiri, dan panggilan itu menjalankan DUA kueri: satu
 * count() untuk paginasi dan satu get() untuk datanya — sementara pemanggilnya
 * membuang nilai `total`. Jadi separuh kueri itu dikerjakan untuk jawaban yang
 * tak pernah dibaca. `count(*)` atas N_HRIS_VW_Approval_Tracking_Generic
 * tercatat 32 kali di production dengan puncak 2,5 detik.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Jumlah kueri ke view tracking TIDAK tumbuh mengikuti jumlah transaksi di
 * halaman, dan tak satu pun di antaranya `count(*)`. Dihitung, bukan diukur
 * waktunya: waktu berubah menurut mesin, bentuk tidak.
 */
class RiwayatApprovalShiftKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const JUMLAH_TRANSAKSI = 4;

    public function test_kueri_tracking_tidak_tumbuh_per_transaksi(): void
    {
        $karyawan = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'Kode_Perusahaan']);

        if (! $karyawan) {
            $this->markTestSkipped('Tidak ada karyawan aktif di data ini.');
        }

        $kode = trim((string) $karyawan->Kode_Karyawan);
        $approver = $kode;

        for ($i = 0; $i < self::JUMLAH_TRANSAKSI; $i++) {
            $no = 'UJI-HIST-'.Str::upper(Str::random(8));

            DB::table('N_HRIS_Transaksi_Shift')->insert([
                'No_Transaksi' => $no,
                'Kode_Perusahaan' => trim((string) ($karyawan->Kode_Perusahaan ?: '001')),
                'Jenis_Transaksi' => 'LEADER_SHIFT',
            ]);

            DB::table('N_HRIS_Transaksi_Shift_Detail')->insert([
                'Kode_Perusahaan' => trim((string) ($karyawan->Kode_Perusahaan ?: '001')),
                'No_Transaksi' => $no,
                'Kode_Karyawan' => $kode,
                'Target_Table' => 'SEMENTARA',
            ]);

            DB::table('HRIS_Approval_Request')->insert([
                'Kode_Karyawan' => $kode,
                'No_Transaksi' => $no,
                'Approved_By_Kode' => $approver,
                'Flag_Approval' => 'Y',
                'update_at' => now(),
            ]);
        }

        $tracking = 0;
        $count = 0;
        DB::listen(function ($q) use (&$tracking, &$count) {
            $s = strtolower(preg_replace('/\s+/', ' ', $q->sql));
            if (! str_contains($s, 'n_hris_vw_approval_tracking_generic')) {
                return;
            }
            $tracking++;
            if (str_contains($s, 'count(')) {
                $count++;
            }
        });

        $hasil = app(ShiftRequestService::class)
            ->getApproverHistory($approver, ['page' => 1, 'limit' => 10, 'filters' => []]);

        $this->assertGreaterThanOrEqual(
            self::JUMLAH_TRANSAKSI,
            $hasil['total'] ?? 0,
            'Fixture tidak terbaca oleh getApproverHistory — penjaga ini tak menguji apa pun.',
        );

        $this->assertLessThanOrEqual(
            1,
            $tracking,
            "View tracking dikueri {$tracking} kali untuk ".self::JUMLAH_TRANSAKSI.' transaksi; '.
            'seharusnya cukup sekali untuk seluruh halaman. Bentuk per-transaksi inilah yang '.
            'membuat halaman ini 52 kueri dan 11,4 detik di production.',
        );

        $this->assertSame(
            0,
            $count,
            "Masih ada {$count} kueri count(*) ke view tracking. Nilai total-nya dibuang oleh ".
            'pemanggil, jadi setiap count() itu kerja untuk jawaban yang tak pernah dibaca.',
        );
    }
}
