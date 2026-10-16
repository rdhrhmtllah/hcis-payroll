<?php

namespace App\Services\SYSTEM\Organization;

use App\Jobs\SYSTEM\Organization\GenerateRelasiOrganisasiExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Export Relasi Organisasi di LATAR BELAKANG — menumpang mesin ekspor terpusat
 * (N_HRIS_Async_Tasks + Activity Center), bukan jalur sendiri.
 *
 * Unduhan langsung (GET /ekspor) menahan satu permintaan HTTP selama berkasnya dibuat: 7.086 relasi
 * ±20 detik dan ±200 MB, dan tumbuh bersama datanya. Di sini datanya dibaca per batch 1.000 baris
 * dengan progres, berkasnya disimpan, dan pengguna boleh menutup layar — hasilnya menunggu di
 * Activity Center. Isi berkas SAMA dengan unduhan langsung (keduanya memakai
 * OrganizationRelationExportService).
 */
class OrganizationRelationAsyncExportService extends AbstractExportService
{
    public function __construct(private readonly OrganizationRelationExportService $ekspor) {}

    protected function moduleKey(): string
    {
        return 'relasi-organisasi-export';
    }

    protected function exportIdPrefix(): string
    {
        return 'ro-exp';
    }

    protected function storageDir(): string
    {
        return 'relasi-organisasi-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateRelasiOrganisasiExportJob::dispatch($exportId);
    }

    /**
     * Penyimpanan berkas ekspor (GCS) bisa dipakai? Tanpanya berkas latar belakang tidak akan
     * pernah bisa diunduh — layar lalu memakai unduhan langsung. Hasilnya diingat 5 menit.
     */
    public function penyimpananSiap(): bool
    {
        return Cache::remember('relasi-ekspor:penyimpanan-siap', 300, function () {
            try {
                Storage::disk(self::DISK)->fileExists($this->storageDir().'/.periksa');

                return true;
            } catch (\Throwable $e) {
                Log::warning('RELASI_EXPORT_PENYIMPANAN_TIDAK_SIAP', ['message' => $e->getMessage()]);

                return false;
            }
        });
    }

    /** Memulai ekspor: catat tugasnya, kirim ke antrean, kembalikan Export_Id. */
    public function mulai(array $saringan, ?string $diminta, ?string $olehNama): string
    {
        $total = $this->ekspor->hitung($saringan); // menolak dini bila melebihi batas
        if ($total > 50000) {
            throw new \RuntimeException('Hasil melebihi 50.000 baris. Persempit saringan atau kategori.');
        }
        $id = $this->makeExportId();
        $this->startJob($id, 'xlsx', ['saringan' => $saringan, 'oleh' => $olehNama], $total, $diminta);

        try {
            $this->dispatchJob($id);
        } catch (\Throwable $e) {
            /* Antrean (Cloud Tasks) tidak bisa dihubungi — mis. kredensial di lingkungan dev. Tugasnya
               TIDAK dibiarkan menggantung "queued" selamanya: diproses di proses ini sesudah
               respons terkirim, dengan progres yang sama di Activity Center. */
            Log::warning('RELASI_EXPORT_ANTREAN_GAGAL_PROSES_LANGSUNG', ['export_id' => $id, 'message' => $e->getMessage()]);
            app()->terminating(function () use ($id) {
                @set_time_limit(600);
                try {
                    $this->process($id);
                } catch (\Throwable $galat) {
                    $this->markFailed($id, 'Export gagal: '.$galat->getMessage());
                }
            });
        }

        return $id;
    }

    /** Status satu ekspor, hanya untuk pemintanya. */
    public function statusUntuk(string $exportId, ?string $diminta): ?array
    {
        $job = $this->findJob($exportId);
        if ($job === null || ($diminta !== null && $job->Requested_By !== null && $job->Requested_By !== $diminta)) {
            return null;
        }

        return $this->mapJob($job);
    }

    protected function buildDataset(object $job): array
    {
        $p = $this->payload($job);
        $baris = $this->ekspor->bacaBertahap(
            (array) ($p['saringan'] ?? []),
            fn (int $diproses, int $total) => $this->bumpProgress($job->Export_Id, $diproses, $total),
        );

        return ['rows' => $baris, 'saringan' => (array) ($p['saringan'] ?? []), 'oleh' => $p['oleh'] ?? null];
    }

    protected function datasetCount(array $dataset): int
    {
        return $dataset['rows']->count();
    }

    protected function renderFile(object $job, array $dataset): array
    {
        $buku = $this->ekspor->bukuExcel($dataset['rows'], $dataset['saringan'], $dataset['oleh']);
        $tmp = tempnam(sys_get_temp_dir(), 'roexp');
        $tulis = new Xlsx($buku);
        $tulis->setPreCalculateFormulas(false);
        $tulis->save($tmp);
        $buku->disconnectWorksheets();

        $path = $this->filePathFor($job, 'xlsx');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, $this->ekspor->namaBerkas(), $size];
    }

    protected function listLabel(object $job): string
    {
        $s = (array) ($this->payload($job)['saringan'] ?? []);
        $bagian = [];
        if (! empty($s['karyawan'])) {
            $bagian[] = count((array) $s['karyawan']).' karyawan';
        }
        foreach (['subjek_divisi' => 'divisi', 'subjek_departemen' => 'departemen'] as $k => $label) {
            if (! empty($s[$k])) {
                $bagian[] = $label.' '.implode(', ', (array) $s[$k]);
            }
        }
        if (! empty($s['subjek_q'])) {
            $bagian[] = '"'.$s['subjek_q'].'"';
        }
        if (! empty($s['hanya_anomali'])) {
            $bagian[] = 'anomali';
        }

        return 'Relasi Organisasi'.($bagian ? ' ('.implode(' · ', $bagian).')' : '');
    }
}
