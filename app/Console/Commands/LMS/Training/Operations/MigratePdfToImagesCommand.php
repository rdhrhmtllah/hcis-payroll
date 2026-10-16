<?php

namespace App\Console\Commands\LMS\Training\Operations;

use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class MigratePdfToImagesCommand extends Command
{
    // Nama command yang dijalankan di terminal
    protected $signature = 'lms:migrate-pdf-images';

    protected $description = 'Dispatch semua dokumen PDF PENDING ke Cloud Tasks (Pacing diatur oleh GCP Queue)';

    public function handle(CloudTaskService $cloudTask)
    {
        $this->info('--- Memulai Inisialisasi Migrasi PDF ---');

        // 1. Ambil dokumen PDF yang statusnya PENDING
        // Status PENDING ini didapat dari hasil Query ALTER TABLE yang kita buat sebelumnya
        $berkasList = DB::table('N_LMS_Master_Konten_Berkas as a')
            ->join('N_LMS_Master_Konten as b', 'a.Id_Master_Konten', '=', 'b.Id_Master_Konten')
            ->where('a.Type_File', 'like', '%pdf%')
            ->where('a.Status_Render', 'PENDING')
            ->where('b.Flag_Aktif', 'Y')
            ->where('b.Tipe_Konten', 'document')
            ->get();

        $count = $berkasList->count();

        if ($count === 0) {
            $this->info('Selesai: Tidak ada dokumen PDF berstatus PENDING.');

            return 0;
        }

        $this->info("Ditemukan {$count} dokumen. Mengirim tugas ke Cloud Tasks...");

        // Progress bar agar Admin bisa melihat proses pendaftaran tugas
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $queueName = config('services.project_config.gcp.queue_pdf', 'default');
        $webhookUrl = url('/api/v1/tasks/lms/process-pdf');

        foreach ($berkasList as $berkas) {
            try {
                // Langsung dispatch tanpa delay waktu di Laravel
                // Biarkan antrian diatur oleh "Max Concurrent Dispatches" di GCP Console
                $cloudTask->dispatch(
                    url: $webhookUrl,
                    queue: $queueName,
                    payload: ['berkas_id' => $berkas->Id_Master_Konten_Berkas],
                    taskType: 'LMS_PDF_RENDER_MIGRATION',
                    referenceId: $berkas->Id_Master_Konten_Berkas,
                    referenceNo: 'MIGRATE-KONTEN-'.$berkas->Id_Master_Konten,
                    dispatchedBy: 'SYSTEM_MIGRATION',
                );
            } catch (Throwable $e) {
                $this->error("\n Gagal mendaftarkan Berkas ID {$berkas->Id_Master_Konten_Berkas}: ".$e->getMessage());
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Sukses: Semua tugas sudah masuk antrian Google Cloud Tasks.');
        $this->comment(
            'Penting: Pastikan setting "Max Concurrent Dispatches" di GCP Console sudah diatur ke 1 atau 2.',
        );

        return 0;
    }
}
