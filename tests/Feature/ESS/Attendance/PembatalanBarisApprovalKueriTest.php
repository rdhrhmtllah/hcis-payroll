<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pemeriksaan "baris sudah dibatalkan": satu kueri untuk satu keputusan.
 *
 * ── Dua cacat yang ditutup ───────────────────────────────────────────────────
 *
 * 1. Log yang menyesatkan. isRowCancelled() dulu mencatat peringatan
 *    "Detail record not found for cancellation check" setiap kali tak menemukan
 *    baris. Kuerinya menyaring `Status IS NOT NULL`, jadi "tidak ketemu" adalah
 *    jawaban NORMAL dan benar — barisnya belum dibatalkan. Terekam di production
 *    9 September 2026 untuk detail_id 124340 yang sehat. Peringatan palsu
 *    seperti ini mengubur peringatan yang sungguhan.
 *
 * 2. Satu kueri per baris. Ia dipanggil dari dalam reject() atas daftar detail,
 *    jadi approval massal menghasilkan satu perjalanan ke basis data per
 *    tanggal — di jalur TULIS, yang sedang memegang lock, pada basis data yang
 *    RCSI-nya mati.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Jawabannya benar (hanya baris ber-Status yang dianggap dibatalkan) DAN
 * biayanya tetap satu kueri berapa pun jumlah barisnya.
 */
class PembatalanBarisApprovalKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const TABEL = 'N_HRIS_Transaksi_Shift_Det';

    public function test_satu_kueri_untuk_banyak_baris_dan_jawabannya_benar(): void
    {
        $karyawan = DB::table('Karyawan')->first(['Kode_Karyawan', 'Kode_Perusahaan']);
        if (! $karyawan) {
            $this->markTestSkipped('Tabel Karyawan kosong.');
        }

        $perusahaan = trim((string) ($karyawan->Kode_Perusahaan ?: '001'));
        $no = 'UJI-CANCEL-'.Str::upper(Str::random(6));

        $idDetail = DB::table('N_HRIS_Transaksi_Shift_Detail')->insertGetId([
            'Kode_Perusahaan' => $perusahaan,
            'No_Transaksi' => $no,
            'Kode_Karyawan' => trim((string) $karyawan->Kode_Karyawan),
            'Target_Table' => 'SEMENTARA',
        ]);

        // Tiga baris dibatalkan (Status terisi), tiga masih hidup (Status NULL).
        $dibatalkan = [];
        $hidup = [];
        for ($i = 0; $i < 6; $i++) {
            $id = DB::table(self::TABEL)->insertGetId([
                'Kode_Perusahaan' => $perusahaan,
                'No_Transaksi' => $no,
                'Id_Detail' => $idDetail,
                'Tanggal_Shift' => now()->addYears(4)->addDays($i)->format('Y-m-d'),
                'Id_Shift_Diminta' => (int) (DB::table('HRIS_Shift_Kerja')->value('ID_Shift') ?? 1),
                'Jenis_Pengajuan' => 'LEADER_SHIFT',
                'Status' => $i < 3 ? 'Y' : null,
            ]);
            $i < 3 ? $dibatalkan[] = (int) $id : $hidup[] = (int) $id;
        }

        $n = 0;
        DB::listen(function ($q) use (&$n) {
            $s = strtolower(preg_replace('/\s+/', ' ', $q->sql));
            if (str_starts_with($s, 'select') && str_contains($s, strtolower(self::TABEL))) {
                $n++;
            }
        });

        $hasil = app(ShiftRequestService::class)
            ->barisDibatalkan($no, array_merge($dibatalkan, $hidup), self::TABEL);

        sort($hasil);
        $harapan = $dibatalkan;
        sort($harapan);

        $this->assertSame(
            $harapan,
            $hasil,
            'Hanya baris ber-Status yang boleh dianggap dibatalkan; baris hidup tidak.',
        );

        $this->assertSame(
            1,
            $n,
            "Pemeriksaan pembatalan memakai {$n} kueri untuk 6 baris, seharusnya 1. ".
            'Bentuk per-baris ini berjalan di jalur tulis yang sedang memegang lock.',
        );
    }
}
