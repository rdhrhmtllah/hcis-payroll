<?php

namespace Tests\Feature\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\PengirimanDataService;
use App\Services\ESS\Employee\DataCollection\PengumpulanDataExportService;
use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PengumpulanDataEksporTest extends TestCase
{
    use DatabaseTransactions;

    private function ekspor(): PengumpulanDataExportService
    {
        return app(PengumpulanDataExportService::class);
    }

    private function kodeAktif(): string
    {
        $kode = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->orderBy('Kode_Karyawan')
            ->value('Kode_Karyawan');

        DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', $kode)->delete();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $kode)->delete();
        app(StatusPengumpulanService::class)->bersihkanCache($kode);

        return $kode;
    }

    private function dataLengkap(): array
    {
        return [
            'nama_lengkap' => 'Budi Santoso',
            // NIK berawalan nol: inilah yang rusak kalau ditulis sebagai angka.
            'nik_ktp' => '0201234567890123',
            'tanggal_lahir' => '1995-04-17',
            'jenis_kelamin' => 'L',
            'email' => 'budi@evonusabersaudara.co.id',
            'no_hp' => '6281234567890',
            'departemen' => 'IT',
            'pendidikan_terakhir' => 'S1',
            'status_pernikahan' => 'Menikah',
            // Wajib menyertai 'Menikah' sejak versi skema 3.
            'jumlah_tanggungan' => '2',
            'darurat_nama' => 'Siti Aminah',
            'darurat_hubungan' => 'Pasangan',
            'darurat_no_hp' => '6281298765432',
        ];
    }

    public function test_ekspor_csv_memuat_seluruh_karyawan_aktif_termasuk_yang_belum_mengisi(): void
    {
        Storage::fake(AbstractExportService::DISK);

        $kode = $this->kodeAktif();
        app(PengirimanDataService::class)->kirim($kode, $this->dataLengkap());

        $ekspor = $this->ekspor();
        $exportId = $ekspor->makeExportId();
        $target = $ekspor->hitungTarget([]);

        $ekspor->startJob($exportId, 'csv', [], $target, 'UJI');
        // QUEUE_CONNECTION=sync di phpunit.xml -> job berjalan seketika.
        $ekspor->process($exportId);

        $job = $ekspor->jobUntukUnduh($exportId);

        $this->assertSame(AbstractExportService::STATUS_READY, $job->Status);
        Storage::disk(AbstractExportService::DISK)->assertExists($job->File_Path);

        $isi = Storage::disk(AbstractExportService::DISK)->get($job->File_Path);
        $barisIsi = substr_count(trim($isi), "\n");

        $this->assertSame($target, $barisIsi, 'Jumlah baris data harus sama dengan jumlah karyawan aktif.');
        $this->assertStringContainsString('Status Pengisian', $isi);
        $this->assertStringContainsString('0201234567890123', $isi, 'NIK berawalan nol harus utuh.');
    }

    public function test_saringan_status_mempersempit_isi_ekspor(): void
    {
        Storage::fake(AbstractExportService::DISK);

        $kode = $this->kodeAktif();
        app(PengirimanDataService::class)->kirim($kode, $this->dataLengkap());

        $ekspor = $this->ekspor();

        $semua = $ekspor->hitungTarget([]);
        $hanyaSelesai = $ekspor->hitungTarget(['status' => 'SELESAI']);
        $hanyaBelum = $ekspor->hitungTarget(['status' => 'BELUM']);

        $this->assertGreaterThanOrEqual(1, $hanyaSelesai);
        $this->assertSame($semua, $hanyaSelesai + $hanyaBelum);
    }

    public function test_berkas_xlsx_terbentuk_dan_tidak_kosong(): void
    {
        Storage::fake(AbstractExportService::DISK);

        $this->kodeAktif();

        $ekspor = $this->ekspor();
        $exportId = $ekspor->makeExportId();

        $ekspor->startJob($exportId, 'xlsx', [], $ekspor->hitungTarget([]), 'UJI');
        $ekspor->process($exportId);

        $job = $ekspor->jobUntukUnduh($exportId);

        $this->assertSame(AbstractExportService::STATUS_READY, $job->Status);
        $this->assertStringEndsWith('.xlsx', $job->File_Path);
        $this->assertGreaterThan(0, (int) $job->File_Size);
    }
}
