<?php

namespace Tests\Feature\ESS\Employee;

use App\Jobs\ESS\Employee\DataCollection\KirimPengingatPengumpulanDataJob;
use App\Services\ESS\Employee\DataCollection\AdminPengumpulanDataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Satu klik "Kirim Pengingat" tidak boleh menyeret permintaannya sendiri.
 *
 * Sebelum penjaga ini ada, upsertByKey() dipanggil sekali per orang di dalam
 * permintaan admin — 2 kueri masing-masing. Diukur pada populasi dev:
 *
 *     952 sasaran  ->  1.904 kueri dalam SATU permintaan sinkron
 *                      ~9,5 detik pada latensi produksi
 *                      menahan satu slot PHP-FPM selama itu
 *
 * Penjaga ini berbasis EFEK — menghitung kueri yang benar-benar dijalankan —
 * bukan memeriksa apakah suatu metode dipanggil. Kalau penaburannya kelak
 * ditarik kembali ke dalam permintaan, jumlah kueri akan melonjak dan uji ini
 * menyala, tanpa perlu tahu bagaimana caranya ditarik.
 */
class PengingatTidakMenyeretPermintaanTest extends TestCase
{
    use DatabaseTransactions;

    private function layanan(): AdminPengumpulanDataService
    {
        return app(AdminPengumpulanDataService::class);
    }

    private function jumlahSasaran(): int
    {
        return DB::table('Karyawan as k')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Status as s', 's.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where(fn ($q) => $q->where('s.Is_Dikecualikan', '<>', 'Y')->orWhereNull('s.Is_Dikecualikan'))
            ->where(fn ($q) => $q->whereNull('s.Status')->orWhere('s.Status', '<>', 'SELESAI'))
            ->count();
    }

    public function test_biaya_permintaan_tidak_tumbuh_mengikuti_jumlah_penerima(): void
    {
        Bus::fake();

        $sasaran = $this->jumlahSasaran();

        if ($sasaran < 50) {
            $this->markTestSkipped("Sasaran hanya {$sasaran}; ambangnya tak membuktikan apa pun.");
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $terkirim = $this->layanan()->kirimPengingat('UJI');
        $kueri = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($sasaran, $terkirim, 'Jumlah yang dilaporkan harus sama dengan jumlah sasaran.');

        // Ambangnya sengaja longgar (10) tetapi TIDAK sebanding jumlah penerima:
        // itulah yang membedakan "beberapa kueri tetap" dari N+1.
        $this->assertLessThanOrEqual(10, $kueri,
            "Permintaan menjalankan {$kueri} kueri untuk {$sasaran} penerima — penaburan tampaknya kembali ".
            'dikerjakan di dalam permintaan, bukan di latar.');
    }

    public function test_penaburan_dipotong_supaya_tak_ada_job_yang_kepanjangan(): void
    {
        Bus::fake();

        $sasaran = $this->jumlahSasaran();

        if ($sasaran < 50) {
            $this->markTestSkipped('Sasaran terlalu sedikit untuk menguji pemotongan.');
        }

        $this->layanan()->kirimPengingat('UJI');

        $diharapkan = (int) ceil($sasaran / 100);

        Bus::assertDispatchedTimes(KirimPengingatPengumpulanDataJob::class, $diharapkan);
    }

    /**
     * Jejak audit tetap ditulis, dan angkanya angka SASARAN — bukan angka
     * yang sudah berhasil ditaburkan, karena penaburannya belum terjadi saat
     * permintaan ini selesai. Membedakannya penting supaya laporan tidak
     * mengklaim keberhasilan yang belum tentu terjadi.
     */
    public function test_audit_dicatat_dengan_jumlah_sasaran(): void
    {
        Bus::fake();

        $sasaran = $this->jumlahSasaran();
        $sebelum = DB::table('N_HRIS_Pengumpulan_Data_Audit')->where('Aksi', 'KIRIM_PENGINGAT')->count();

        $this->layanan()->kirimPengingat('UJI');

        $baris = DB::table('N_HRIS_Pengumpulan_Data_Audit')
            ->where('Aksi', 'KIRIM_PENGINGAT')
            ->orderByDesc('Id')
            ->first();

        $this->assertSame($sebelum + 1, DB::table('N_HRIS_Pengumpulan_Data_Audit')->where('Aksi', 'KIRIM_PENGINGAT')->count());
        $this->assertStringContainsString((string) $sasaran, (string) ($baris->Keterangan ?? $baris->Nilai_Sesudah ?? ''));
    }
}
