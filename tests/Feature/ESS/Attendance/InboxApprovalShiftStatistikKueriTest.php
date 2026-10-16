<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Statistik inbox approval shift: tiga angka, bukan dua belas kueri.
 *
 * ── Skalanya, dari production ────────────────────────────────────────────────
 *
 * `shift-approval/inbox` pada 9 September 2026: rata-rata 36 kueri per
 * permintaan dengan 5.552 ms waktu DB — **100% waktu permintaannya habis di
 * basis data** — sementara kueri terlambat hanya 18% dari waktu itu. Bebannya
 * tersebar di banyak kueri kecil; tidak ada satu kueri berat yang bisa
 * disalahkan.
 *
 * ── Sumbernya ────────────────────────────────────────────────────────────────
 *
 * Panel statistik meminta tiga angka (total / pending / approved) atas empat
 * jenis transaksi, dan mengambilnya lewat DUA BELAS COUNT() terpisah — tiap
 * angka satu perjalanan ke basis data, semuanya atas kumpulan baris yang sama.
 * Agregasi bersyarat menghitung ketiganya sekali jalan: empat kueri.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Jumlah kueri agregat ke HRIS_Approval_Request, DAN nilai ketiga angkanya —
 * karena mengurangi kueri tak ada gunanya kalau angkanya jadi salah. Uji ini
 * menegaskan keduanya: total = pending + approved, dan total > 0 supaya blok
 * statistiknya benar-benar dilewati (angka nol akan membuat kesamaan itu benar
 * secara hampa).
 */
class InboxApprovalShiftStatistikKueriTest extends TestCase
{
    use DatabaseTransactions;

    /** 4 jenis transaksi; satu kueri agregat per jenis. Dulu 12. */
    private const AMBANG_KUERI_AGREGAT = 4;

    public function test_statistik_inbox_tidak_dihitung_dua_belas_kali(): void
    {
        // Approver diambil dari data NYATA, bukan dikarang: daftar inbox
        // bersumber dari view N_HRIS_VW_Approval_Request_V2 (Flow_State =
        // 'CURRENT'), yang tak bisa disemai langsung — ia diturunkan dari
        // konfigurasi alur approval. Approver tanpa baris CURRENT membuat
        // getApproverInbox() pulang lebih awal, dan penjaga ini akan hijau tanpa
        // pernah menyentuh blok statistik yang dijaganya.
        $kode = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('Flow_State', 'CURRENT')
            ->whereNotNull('Current_Approver')
            ->groupBy('Current_Approver')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(1)
            ->value('Current_Approver');

        if (! $kode) {
            $this->markTestSkipped('Tak ada approver dengan transaksi CURRENT di data ini.');
        }

        $kode = trim((string) $kode);

        $agregat = 0;
        DB::listen(function ($q) use (&$agregat) {
            $s = strtolower(preg_replace('/\s+/', ' ', $q->sql));
            if (! str_contains($s, 'hris_approval_request')) {
                return;
            }
            if (str_contains($s, 'count(*)') || str_contains($s, 'count(*) as total')) {
                $agregat++;
            }
        });

        $hasil = app(ShiftRequestService::class)
            ->getApproverInbox($kode, ['page' => 1, 'limit' => 10, 'filters' => []]);

        $stats = $hasil['stats'] ?? [];

        $this->assertGreaterThan(
            0,
            (int) ($stats['total'] ?? 0),
            'Statistik pulang nol — blok yang dijaga tidak pernah dijalankan.',
        );

        $this->assertSame(
            (int) ($stats['pending'] ?? -1) + (int) ($stats['approved'] ?? -1),
            (int) ($stats['total'] ?? -2),
            'total harus sama dengan pending + approved; agregasi bersyarat salah hitung.',
        );

        $this->assertLessThanOrEqual(
            self::AMBANG_KUERI_AGREGAT,
            $agregat,
            "Statistik inbox memakai {$agregat} kueri agregat, seharusnya paling banyak ".
            self::AMBANG_KUERI_AGREGAT.' (satu per jenis transaksi). Bentuk dua-belas-COUNT '.
            'inilah yang membuat halaman ini 36 kueri dan 5,5 detik di production.',
        );
    }
}
