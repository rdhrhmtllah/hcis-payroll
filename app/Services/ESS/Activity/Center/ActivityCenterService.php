<?php

namespace App\Services\ESS\Activity\Center;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * ActivityCenterService
 *
 * Agregator "Activity Center" global: menggabungkan semua tugas async lintas
 * sumber ke bentuk SERAGAM untuk dropdown topbar. Sumber saat ini:
 *   1. Export (N_HRIS_Async_Tasks, Task_Type='export') — semua modul.
 *   2. Render PDF (N_LMS_Master_Konten_Berkas.Status_Render) — via adapter.
 *
 * Item seragam: { export_id|id, type, module, label, percent, status,
 * download_url, retry_url, dismiss_url, is_ready, requested_at }.
 *
 * Polling pintar di frontend: panel hanya menampilkan item BERJALAN
 * (queued/processing) + READY yang belum diunduh, dibatasi $limit.
 */
class ActivityCenterService
{
    /** Status seragam lintas sumber. */
    private const ACTIVE = ['queued', 'processing'];

    /** Keempat Task_Type yang ditarik panel ini dalam satu kueri. */
    private const TIPE_TUGAS = [
        'export',
        'upload',
        \App\Services\LMS\SOP\SopBulkAssignTaskService::TASK_TYPE,
        \App\Services\LMS\SOP\SopReconcileTaskService::TASK_TYPE,
        \App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService::TASK_TYPE,
        \App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService::TASK_TYPE,
        \App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService::TASK_TYPE,
        \App\Services\KPI\PerformanceAppraisal\PaHitungTugasService::TASK_TYPE,
        \App\Services\KPI\KpiEnforcementService::ASYNC_TASK_TYPE,
        \App\Services\KPI\KpiCapParameterBackfillService::TASK_TYPE,
    ];

    public function __construct(private string $requestedBy = '') {}

    public function forUser(?string $requestedBy): self
    {
        $this->requestedBy = (string) $requestedBy;

        return $this;
    }

    /**
     * Daftar aktivitas untuk panel (gabungan, diurut terbaru, dibatasi $limit).
     * Default hanya yang relevan: sedang berjalan + ready belum diunduh.
     */
    /** Hasil satu kueri berjendela, dikelompokkan per Task_Type. Null = belum diambil. */
    private ?array $petaTugas = null;

    /**
     * Baris N_HRIS_Async_Tasks untuk satu Task_Type - dari SATU kueri untuk
     * SEMUA tipe, bukan satu kueri per tipe.
     *
     * KENAPA INI BERARTI. Empat sumber panel ini (export, upload, bulk-assign,
     * reconcile) memukul TABEL YANG SAMA dengan saringan yang sama persis,
     * berbeda hanya pada Task_Type. Kuerinya sendiri sepele - DMV mencatatnya
     * 0,7 ms - tetapi satu bolak-balik ke basis data di lingkungan ini TERUKUR
     * 54 ms (SELECT 1 kosong, p50 dari 30 percobaan). Jadi yang dibayar
     * pengguna adalah perjalanannya: empat perjalanan untuk satu tabel
     * berisi 129 baris.
     *
     * Bentuk lama itu juga yang membuat kueri ini tercatat 44.452 eksekusi di
     * dm_exec_query_stats - salah satu yang paling sering dijalankan di seluruh
     * server. Panel ini dipanggil dua kali (list untuk isinya, summary untuk
     * badge topbar), dan masing-masing membayar empat kali.
     *
     * ROW_NUMBER dipakai supaya batas "20 teratas" berlaku PER TIPE, persis
     * seperti empat kueri terpisah tadi. Mengganti dengan whereIn + limit 80
     * akan tampak setara padahal tidak: satu tipe yang ramai bisa mendorong
     * tipe lain keluar dari hasil.
     *
     * Dua kueri lain ke tabel yang sama SENGAJA dibiarkan sendiri
     * (exportQueuePositions dan berkasIdsTrackedByUpload): saringannya berbeda -
     * keduanya tidak dibatasi Requested_By - jadi menyatukannya menuntut
     * perubahan makna, bukan sekadar perubahan bentuk.
     */
    private function tugasAsync(string $tipe): \Illuminate\Support\Collection
    {
        if ($this->petaTugas === null) {
            try {
                $dalam = DB::table(AbstractExportService::TABLE)
                    ->selectRaw('*, ROW_NUMBER() OVER (PARTITION BY Task_Type ORDER BY Id_Export DESC) AS _urut')
                    ->whereIn('Task_Type', self::TIPE_TUGAS)
                    ->whereNull('Deleted_At')
                    ->when($this->requestedBy !== '', fn ($q) => $q->where('Requested_By', $this->requestedBy));

                $this->petaTugas = DB::query()
                    ->fromSub($dalam, 't')
                    ->where('_urut', '<=', 20)
                    ->get()
                    ->groupBy('Task_Type')
                    ->all();
            } catch (\Throwable $e) {
                /* Tabel belum dibuat / error transien -> jangan jatuhkan seluruh
                   panel. Sama seperti perilaku empat try/catch yang digantikan. */
                $this->petaTugas = [];
            }
        }

        return collect($this->petaTugas[$tipe] ?? []);
    }

    public function list(int $limit = 15, bool $onlyRelevant = true): array
    {
        $items = array_merge(
            $this->exportItems(),
            $this->uploadItems(),
            $this->pdfRenderItems(),
            $this->bulkAssignItems(),
            $this->reconcileItems(),
            $this->paPenjadwalanItems(),
            $this->paPembentukanItems(),
            $this->paRevisiItems(),
            $this->paHitungItems(),
            $this->kpiAutoCapItems(),
            $this->kpiBackfillCapItems(),
            $this->lmsOutboxItems(),
        );

        if ($onlyRelevant) {
            $items = array_filter($items, fn ($i) => $this->isRelevant($i));
        }

        // Urutan tampilan mengikuti hierarki status & kebaruan:
        //   1) Sedang diproses / diunggah (processing) paling atas, terbaru dulu.
        //   2) Selesai / siap diunduh / dilihat (ready), terbaru dulu.
        //   3) Antri di background queue (queued) sesuai nomor antrean.
        //   4) Gagal (failed) di bawah, terbaru dulu.
        usort($items, function ($a, $b) {
            $rankA = $this->displayRank($a);
            $rankB = $this->displayRank($b);
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            // Antara sesama queued: urut posisi antrian (kecil = lebih dulu jalan)
            if ($rankA === 2) {
                $posA = $a['queue_position'] ?? PHP_INT_MAX;
                $posB = $b['queue_position'] ?? PHP_INT_MAX;
                if ($posA !== $posB) {
                    return $posA <=> $posB;
                }
            }

            // Tie-break / grup lainnya (processing, ready, failed): terbaru dulu
            return ($b['_ts'] ?? 0) <=> ($a['_ts'] ?? 0);
        });
        $items = array_slice(array_values($items), 0, $limit);

        // Buang field internal.
        return array_map(function ($i) {
            unset($i['_ts']);

            return $i;
        }, $items);
    }

    /**
     * Ringkasan untuk badge topbar (ringan): jumlah berjalan + ready-belum-unduh.
     */
    public function summary(): array
    {
        // WAJIB sinkron dengan list() — adapter yang lupa di-merge di sini
        // membuat badge topbar berbohong sementara panelnya menampilkan task.
        $items = array_merge(
            $this->exportItems(),
            $this->uploadItems(),
            $this->pdfRenderItems(),
            $this->bulkAssignItems(),
            $this->reconcileItems(),
            $this->paPenjadwalanItems(),
            $this->paPembentukanItems(),
            $this->paRevisiItems(),
            $this->paHitungItems(),
            $this->kpiAutoCapItems(),
            $this->kpiBackfillCapItems(),
            $this->lmsOutboxItems(),
        );

        $active = 0;
        $ready = 0;
        foreach ($items as $i) {
            if (in_array($i['status'], self::ACTIVE, true)) {
                $active++;
            } elseif ($i['status'] === 'ready' && empty($i['downloaded'])) {
                $ready++;
            }
        }

        return ['active_count' => $active, 'ready_count' => $ready];
    }

    private function isRelevant(array $i): bool
    {
        if (in_array($i['status'], self::ACTIVE, true)) {
            return true;
        }
        if ($i['status'] === 'failed') {
            return true; // tampilkan gagal agar bisa retry
        }

        // Ready yang belum diunduh.
        return $i['status'] === 'ready' && empty($i['downloaded']);
    }

    /** Rank grup tampilan: 0 = processing, 1 = ready, 2 = queued, 3 = failed, 4 = lainnya. */
    private function displayRank(array $i): int
    {
        return match ($i['status'] ?? '') {
            'processing' => 0,
            'ready' => 1,
            'queued' => 2,
            'failed' => 3,
            default => 4,
        };
    }

    // =====================================================================
    // Sumber 1: Export (N_HRIS_Async_Tasks)
    // =====================================================================

    private function exportItems(): array
    {
        $rows = $this->tugasAsync('export');

        // Peta posisi antrian. Queue Cloud Tasks 'async-export' berjalan serial
        // (satu per satu), jadi posisi = urutan dispatch (Id_Export ASC) di antara
        // job yang masih aktif (queued/processing) lintas modul export.
        $queueMap = $this->exportQueuePositions();
        $queueTotal = count($queueMap);

        return $rows->map(function ($job) use ($queueMap, $queueTotal) {
            $mapped = AbstractExportService::mapRow($job, $this->exportLabel($job));

            $isActive = in_array($job->Status, [
                AbstractExportService::STATUS_QUEUED,
                AbstractExportService::STATUS_PROCESSING,
            ], true);

            return array_merge($mapped, [
                'id' => $job->Export_Id,
                'downloaded' => ! empty($job->Downloaded_At),
                'download_url' => $mapped['is_ready'] ? "/api/v1/activities/export/{$job->Export_Id}/download" : null,
                'retry_url' => "/api/v1/activities/export/{$job->Export_Id}/retry",
                'dismiss_url' => "/api/v1/activities/export/{$job->Export_Id}/dismiss",
                // Tombol batalkan hanya untuk job yang belum selesai (aktif).
                'abort_url' => $isActive ? "/api/v1/activities/export/{$job->Export_Id}/abort" : null,
                'queue_position' => $isActive ? ($queueMap[$job->Export_Id] ?? null) : null,
                'queue_total' => $isActive ? $queueTotal : null,
                '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
            ]);
        })->all();
    }

    /**
     * Posisi antrian (1-based) per Export_Id untuk semua job export yang masih
     * aktif (queued/processing), diurutkan menurut urutan dispatch (Id_Export ASC).
     * Lintas modul karena berbagi satu queue serial Cloud Tasks.
     *
     * @return array<string,int> [Export_Id => posisi]
     */
    private function exportQueuePositions(): array
    {
        try {
            $active = DB::table(AbstractExportService::TABLE)
                ->where('Task_Type', 'export')
                ->whereIn('Status', [
                    AbstractExportService::STATUS_QUEUED,
                    AbstractExportService::STATUS_PROCESSING,
                ])
                ->whereNull('Deleted_At')
                ->orderBy('Id_Export')
                ->pluck('Export_Id');
        } catch (\Throwable $e) {
            /* Panel tetap tidak dijatuhkan — tetapi daftar kosong karena kueri
               gagal terlihat persis sama dengan daftar kosong karena memang tak
               ada export berjalan. Perbedaan itu hanya bisa dipulihkan dari log. */
            Log::warning('Gagal membaca export yang sedang berjalan', [
                'kelas' => $e::class,
                'pesan' => $e->getMessage(),
                'sql' => $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : null,
            ]);

            return [];
        }

        $map = [];
        foreach ($active as $i => $exportId) {
            $map[$exportId] = $i + 1;
        }

        return $map;
    }

    private function exportLabel($job): string
    {
        $payload = json_decode($job->Payload_Json ?: '{}', true) ?: [];
        $nama = trim((string) ($payload['nama'] ?? ($payload['judul'] ?? '')));

        // Label utama = nama custom dari user bila ada; jika tidak, label modul.
        $base = $nama !== '' ? $nama : match ($job->Module) {
            'lms-training' => 'Export Training',
            'kpi-export' => 'Export KPI',
            'sop-compliance' => 'Export Kepatuhan Dokumen',
            'sop-studio' => 'Export Studio',
            'visitor-studio' => 'Export Studio Visitor',
            default => 'Export '.ucfirst((string) $job->Module),
        };

        $jumlahBaris = (int) ($job->Total_Records ?: $job->Processed_Records);

        if ($jumlahBaris > 0) {
            return $base.' · '.strtoupper((string) $job->Format).' · '.number_format($jumlahBaris).' baris';
        }

        if ($job->Status === AbstractExportService::STATUS_PROCESSING) {
            return $base.' · '.strtoupper((string) $job->Format).' · Memproses data…';
        }

        if ($job->Status === AbstractExportService::STATUS_QUEUED) {
            return $base.' · '.strtoupper((string) $job->Format).' · Menunggu antrean…';
        }

        return $base.' · '.strtoupper((string) $job->Format);
    }

    // =====================================================================
    // Sumber 2: Upload media LMS (N_HRIS_Async_Tasks, Task_Type='upload')
    // =====================================================================

    private function uploadItems(): array
    {
        $rows = $this->tugasAsync('upload');

        return $rows->map(function ($job) {
            $mapped = AbstractExportService::mapRow($job, $this->uploadLabel($job));
            $payload = json_decode($job->Payload_Json ?: '{}', true) ?: [];

            // URL buka folder/kategori tempat file diupload (klik dari panel/toast).
            $openUrl = null;
            if (! empty($payload['kategori_id'])) {
                $kategoriHash = Hashids::connection('custom')->encode((int) $payload['kategori_id']);
                $base = ($payload['scope'] ?? 'LMS') === 'SOP' ? '/sop/admin/konten' : '/lms/konten-kategori';
                $openUrl = $base.'?kategori='.$kategoriHash;
            }

            $status = $job->Status;
            // Deteksi upload yang ditinggal/hang di browser (processing > 2 jam tanpa update)
            if ($status === AbstractExportService::STATUS_PROCESSING && $job->Updated_At) {
                $updated = Carbon::parse($job->Updated_At);
                if ($updated->lt(now()->subHours(2))) {
                    $status = AbstractExportService::STATUS_FAILED;
                }
            }

            return array_merge($mapped, [
                'id' => $job->Export_Id,
                // mapRow() menandai 'expired' bila status ready tapi tanpa File_Path.
                // Upload TIDAK punya File_Path (file ada di konten LMS, bukan file
                // export), jadi pakai status asli row — jangan ikut logika expired.
                'status' => $status,
                'is_ready' => $status === AbstractExportService::STATUS_READY,
                // Upload tak punya unduhan; biarkan ready tampil sampai di-silang.
                'downloaded' => false,
                'download_url' => null,
                'retry_url' => null, // transfer byte tak bisa diulang dari server
                'dismiss_url' => "/api/v1/activities/upload/{$job->Export_Id}/dismiss",
                'open_url' => $openUrl, // navigasi ke folder konten
                '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
            ]);
        })->all();
    }

    /**
     * Adapter bulk assign SOP (Task_Type='bulk-assign').
     * Seperti upload: tak punya File_Path, jadi status memakai nilai asli baris —
     * jangan ikut logika "ready tanpa file = expired" milik mapRow().
     */
    private function bulkAssignItems(): array
    {
        $rows = $this->tugasAsync(\App\Services\LMS\SOP\SopBulkAssignTaskService::TASK_TYPE);

        return $rows->map(function ($job) {
            $payload = json_decode($job->Payload_Json ?: '{}', true) ?: [];
            $nama = $payload['nama_paket'] ?? 'Paket Dokumen';
            $hasil = $payload['hasil'] ?? null;

            $label = "Penjadwalan Dokumen · {$nama} · {$job->Total_Records} karyawan";
            if ($hasil) {
                $label .= " — {$hasil['assigned']} di-assign"
                    .(($hasil['skipped'] ?? 0) > 0 ? ", {$hasil['skipped']} dilewati" : '');
            }

            $mapped = AbstractExportService::mapRow($job, $label);

            $openUrl = null;
            if (! empty($payload['id_penjadwalan'])) {
                $hash = Hashids::connection('custom')->encode((int) $payload['id_penjadwalan']);
                $openUrl = '/sop/admin/penjadwalan?batch='.$hash;
            }

            return array_merge($mapped, [
                'id' => $job->Export_Id,
                'status' => $job->Status,
                'is_ready' => $job->Status === AbstractExportService::STATUS_READY,
                'downloaded' => false,
                'download_url' => null,
                // retry aman: job idempoten (diff assignKeHeader men-skip yang sudah commit)
                'retry_url' => $job->Status === AbstractExportService::STATUS_FAILED
                    ? "/api/v1/activities/bulk-assign/{$job->Export_Id}/retry" : null,
                'dismiss_url' => "/api/v1/activities/bulk-assign/{$job->Export_Id}/dismiss",
                'open_url' => $openUrl,
                '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
            ]);
        })->all();
    }

    /**
     * Adapter rekonsiliasi SOP (Task_Type='reconcile-dokumen').
     * Seperti upload dan bulk assign: tak punya File_Path, jadi status memakai
     * nilai asli baris — jangan ikut logika "ready tanpa file = expired" milik
     * mapRow().
     *
     * Payload_Json ditulis SopReconcileTaskService::mulai() dan berbentuk
     * {id_dokumen, judul, paket?} (jalur dokumen) ATAU {id_paket, nama_paket}
     * (jalur paket), ditambah {hasil} setelah selesai() dijalankan.
     */
    private function reconcileItems(): array
    {
        $rows = $this->tugasAsync(\App\Services\LMS\SOP\SopReconcileTaskService::TASK_TYPE);

        return $rows->map(function ($job) {
            $payload = json_decode($job->Payload_Json ?: '{}', true) ?: [];
            $mapped = AbstractExportService::mapRow($job, $this->reconcileLabel($job, $payload));

            return array_merge($mapped, [
                'id' => $job->Export_Id,
                // Segmen URL aksi = nilai 'type' (frontend menyusun
                // /api/v1/activities/{type}/{id}/dismiss dari field ini), jadi
                // 'reconcile' — bukan Task_Type mentah 'reconcile-dokumen'.
                'type' => 'reconcile',
                'status' => $job->Status,
                'is_ready' => $job->Status === AbstractExportService::STATUS_READY,
                'downloaded' => false,
                // Rekonsiliasi tak menghasilkan berkas, dan percobaan ulang sudah
                // ditangani Cloud Tasks lewat Max_Attempts.
                'download_url' => null,
                'retry_url' => null,
                'dismiss_url' => "/api/v1/activities/reconcile/{$job->Export_Id}/dismiss",
                '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
            ]);
        })->all();
    }

    private function kpiAutoCapItems(): array
    {
        $rows = $this->tugasAsync(\App\Services\KPI\KpiEnforcementService::ASYNC_TASK_TYPE);

        return $rows->map(function ($job) {
            $payload = json_decode($job->Payload_Json ?: '{}', true) ?: [];
            $periode = $payload['periode'] ?? '';
            $total = (int) ($job->Total_Records ?: ($payload['total_records'] ?? 0));

            $label = "Auto-Cap KPI · Periode {$periode} · {$total} karyawan";
            if ($job->Status === AbstractExportService::STATUS_PROCESSING) {
                $label .= " ({$job->Percent}%)";
            } elseif ($job->Status === AbstractExportService::STATUS_READY) {
                $label .= ' · Selesai';
            } elseif ($job->Status === AbstractExportService::STATUS_FAILED) {
                $label .= ' · Gagal';
            }

            $mapped = AbstractExportService::mapRow($job, $label);

            return array_merge($mapped, [
                'id' => $job->Export_Id,
                'type' => 'kpi-auto-cap',
                'status' => $job->Status,
                'percent' => (int) $job->Percent,
                'is_ready' => $job->Status === AbstractExportService::STATUS_READY,
                'downloaded' => false,
                'download_url' => null,
                'retry_url' => null,
                'dismiss_url' => "/api/v1/activities/kpi-auto-cap/{$job->Export_Id}/dismiss",
                // Selama masih antre/berjalan, tombol panel = Batalkan (menghentikan
                // batch), bukan Sembunyikan — menyembunyikan saja membiarkannya jalan.
                'abort_url' => in_array($job->Status, self::ACTIVE, true)
                    ? "/api/v1/activities/kpi-auto-cap/{$job->Export_Id}/abort"
                    : null,
                '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
            ]);
        })->all();
    }

    /** Batch backfill cap KPI per parameter (KpiCapParameterBackfillService). */
    private function kpiBackfillCapItems(): array
    {
        return $this->tugasAsync(\App\Services\KPI\KpiCapParameterBackfillService::TASK_TYPE)->map(function ($job) {
            $mode = (json_decode($job->Payload_Json ?: '{}', true) ?: [])['mode'] ?? 'jalankan';
            $label = ($mode === 'rollback' ? 'Rollback Backfill Cap KPI' : 'Backfill Cap KPI').' · '.(int) $job->Total_Records.' header';
            if ($job->Status === AbstractExportService::STATUS_PROCESSING) {
                $label .= " ({$job->Percent}%)";
            } elseif ($job->Status === AbstractExportService::STATUS_READY) {
                $label .= ' · Selesai';
            } elseif ($job->Status === AbstractExportService::STATUS_FAILED) {
                $label .= ' · Gagal';
            }

            return array_merge(AbstractExportService::mapRow($job, $label), [
                'id' => $job->Export_Id,
                'type' => 'kpi-backfill-cap',
                'status' => $job->Status,
                'percent' => (int) $job->Percent,
                'is_ready' => $job->Status === AbstractExportService::STATUS_READY,
                'downloaded' => false,
                'download_url' => null,
                'retry_url' => null,
                'dismiss_url' => "/api/v1/activities/kpi-backfill-cap/{$job->Export_Id}/dismiss",
                '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
            ]);
        })->all();
    }

    /**
     * Adapter penjadwalan rater PA ke HC Learn (Task_Type='pa-penjadwalan').
     *
     * Satu baris per siklus, dua fase: "mengirim" lalu "menunggu HC Learn". Fasenya ikut
     * disebut di label — panel hanya menampilkan satu bilah persen, dan bilah yang diam lama
     * di fase menunggu terbaca macet bila tidak dikatakan apa yang ditunggu.
     *
     * Payload_Json ditulis PaPenjadwalanTugasService.
     */
    private function paPenjadwalanItems(): array
    {
        return $this->tugasAsync(\App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService::TASK_TYPE)->map(function ($job) {
            $p = json_decode($job->Payload_Json ?: '{}', true) ?: [];
            $r = (array) ($p['ringkasan'] ?? []);
            $total = $this->angka((int) ($r['total'] ?? $job->Total_Records));

            $label = 'Penjadwalan Rater PA · '.$this->namaSiklus($p);
            if (in_array($job->Status, self::ACTIVE, true)) {
                $label .= ($p['fase'] ?? 'kirim') === 'tunggu'
                    ? ' — menunggu HC Learn, '.$this->angka((int) ($r['antre'] ?? 0)).' antre'
                    : ' — mengirim '.$this->angka((int) ($r['total'] ?? 0) - (int) ($r['belum'] ?? 0))."/{$total}";
            } elseif ($job->Status === AbstractExportService::STATUS_READY) {
                $label .= " — {$total} rater siap mengisi";
            }

            return $this->petakanTugasPa($job, \App\Services\KPI\PerformanceAppraisal\PaPenjadwalanTugasService::TASK_TYPE, $label, 'HC LEARN');
        })->all();
    }

    /**
     * Adapter pembentukan penugasan rater PA (Task_Type='pa-pembentukan').
     *
     * Peserta yang tetap tanpa penugasan sesudah selesai DISEBUT di label: mereka bukan kegagalan
     * pembentukan (calonnya memang tidak cukup di relasi organisasi), tetapi diam tentang mereka
     * membuat "selesai" terbaca "semua beres".
     *
     * Payload_Json ditulis PaPembentukanTugasService.
     */
    private function paPembentukanItems(): array
    {
        return $this->tugasAsync(\App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService::TASK_TYPE)->map(function ($job) {
            $p = json_decode($job->Payload_Json ?: '{}', true) ?: [];
            $r = (array) ($p['ringkasan'] ?? []);
            $sudahTotal = $this->angka((int) ($r['sudah'] ?? 0)).'/'.$this->angka((int) ($r['total'] ?? $job->Total_Records));

            $label = 'Pembentukan Penugasan PA · '.$this->namaSiklus($p);
            if (in_array($job->Status, self::ACTIVE, true)) {
                $label .= " — {$sudahTotal} peserta";
            } elseif ($job->Status === AbstractExportService::STATUS_READY) {
                $label .= " — {$sudahTotal} peserta berpenugasan";
                if ((int) ($r['belum'] ?? 0) > 0) {
                    $label .= ', '.$this->angka((int) $r['belum']).' tanpa calon cukup';
                }
            }

            return $this->petakanTugasPa($job, \App\Services\KPI\PerformanceAppraisal\PaPembentukanTugasService::TASK_TYPE, $label, 'PENUGASAN');
        })->all();
    }

    /**
     * Adapter perhitungan hasil siklus PA (Task_Type='pa-hitung'). Yang TERTAHAN dan GAGAL
     * disebut angkanya: "selesai" dengan sebagian orang tertahan bukan siklus yang siap terbit.
     *
     * Payload_Json ditulis PaHitungTugasService.
     */
    private function paHitungItems(): array
    {
        return $this->tugasAsync(\App\Services\KPI\PerformanceAppraisal\PaHitungTugasService::TASK_TYPE)->map(function ($job) {
            $p = json_decode($job->Payload_Json ?: '{}', true) ?: [];
            $r = (array) ($p['ringkasan'] ?? []);
            $sudahTotal = $this->angka((int) ($r['sudah'] ?? 0)).'/'.$this->angka((int) ($r['total'] ?? $job->Total_Records));

            $label = 'Perhitungan Hasil PA · '.$this->namaSiklus($p)." — {$sudahTotal} peserta";
            if ($job->Status === AbstractExportService::STATUS_READY) {
                $label .= ': '.$this->angka((int) ($p['dihitung'] ?? 0)).' dihitung';
                if ((int) ($p['tertahan'] ?? 0) > 0) {
                    $label .= ', '.$this->angka((int) $p['tertahan']).' tertahan';
                }
                if ((int) ($p['jumlah_galat'] ?? 0) > 0) {
                    $label .= ', '.$this->angka((int) $p['jumlah_galat']).' gagal';
                }
            }

            return $this->petakanTugasPa($job, \App\Services\KPI\PerformanceAppraisal\PaHitungTugasService::TASK_TYPE, $label, 'HASIL', [
                'url' => "/performance-appraisal/pemantauan?cycle={$job->Reference_Id}",
                'label' => 'Buka Pemantauan',
            ]);
        })->all();
    }

    /**
     * Adapter revisi peserta banyak orang (Task_Type='pa-revisi'). Yang gagal DISEBUT angkanya:
     * revisi yang "selesai" dengan sebagian orang tertolak bukan revisi yang beres.
     */
    private function paRevisiItems(): array
    {
        $judul = ['TERAPKAN_LEVEL' => 'terapkan level', 'IKUTKAN_KEMBALI' => 'ikutkan kembali', 'KECUALIKAN' => 'kecualikan', 'TAMBAH_SUSULAN' => 'peserta susulan', 'SELARASKAN_RELASI' => 'selaraskan penilai', 'LEPAS_PENILAI' => 'lepas penilai'];

        return $this->tugasAsync(\App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService::TASK_TYPE)->map(function ($job) use ($judul) {
            $p = json_decode($job->Payload_Json ?: '{}', true) ?: [];
            $r = (array) ($p['ringkasan'] ?? []);
            $label = 'Revisi Peserta PA · '.$this->namaSiklus($p).' — '.($judul[$p['tindakan'] ?? ''] ?? 'revisi');

            if (in_array($job->Status, self::ACTIVE, true)) {
                $label .= ' '.$this->angka((int) ($r['sudah'] ?? 0)).'/'.$this->angka((int) ($r['total'] ?? 0)).' orang';
            } elseif ($job->Status === AbstractExportService::STATUS_READY) {
                $label .= ': '.$this->angka((int) ($r['berhasil'] ?? 0)).' berhasil';
                if ((int) ($r['gagal'] ?? 0) > 0) {
                    $label .= ', '.$this->angka((int) $r['gagal']).' gagal';
                }
            }

            return $this->petakanTugasPa($job, \App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService::TASK_TYPE, $label, 'REVISI');
        })->all();
    }

    // =====================================================================
    // Sumber: Outbox LMS (N_SYSTEM_Task_Outbox) — ajukan ulang ujian & sinkron penjadwalan
    // =====================================================================

    /** Jenis Outbox yang ditampilkan, beserta judulnya. */
    private const OUTBOX_LMS = [
        'LMS_REASSIGN_THIRD_PARTY' => 'Ajukan Ulang Ujian',
        'LMS_PENJADWALAN_SYNC_THIRD_PARTY' => 'Sinkron Penjadwalan ke HC Learn',
    ];

    /** Yang sudah selesai/gagal tetap tampil selama ini; Outbox tak punya penanda "disembunyikan". */
    private const OUTBOX_JAM_TAMPIL = 24;

    /**
     * Permintaan LMS yang dikirim lewat Outbox, bukan N_HRIS_Async_Tasks. Tanpa sumber ini
     * panel diam saja tentang ajukan ulang ujian — termasuk ketika ia gagal sesudah terkirim.
     * Pemiliknya `Sender_Employee_Code` (Kode_Karyawan pengaju), sama dengan saringan sumber lain.
     */
    private function lmsOutboxItems(): array
    {
        try {
            $sejak = Carbon::now('UTC')->subHours(self::OUTBOX_JAM_TAMPIL);
            $rows = DB::table('N_SYSTEM_Task_Outbox')
                ->whereIn('Task_Type', array_keys(self::OUTBOX_LMS))
                ->where('Status', '<>', 'CANCELLED')
                ->when($this->requestedBy !== '', fn ($q) => $q->where('Sender_Employee_Code', $this->requestedBy))
                ->where(fn ($q) => $q->where('Created_At', '>=', $sejak)
                    ->orWhereIn('Status', ['QUEUED', 'DISPATCHING', 'DISPATCHED', 'PROCESSING', 'RETRY_PENDING']))
                ->orderByDesc('Id_Task_Outbox')
                ->limit(20)
                ->get(['Id_Task_Outbox', 'Task_Type', 'Status', 'Payload', 'Last_Error', 'Created_At', 'Processed_At']);
        } catch (\Throwable $e) {
            return [];
        }

        return $rows->map(function ($r) {
            $status = match ($r->Status) {
                'DISPATCHED', 'PROCESSING' => 'processing',
                'COMPLETED' => 'ready',
                'FAILED' => 'failed',
                default => 'queued',
            };
            $payload = json_decode((string) $r->Payload, true) ?: [];
            $label = self::OUTBOX_LMS[$r->Task_Type] ?? 'Tugas LMS';
            $nama = $payload['data_karyawan'][0]['Nama'] ?? null;
            if ($r->Task_Type === 'LMS_REASSIGN_THIRD_PARTY' && $nama) {
                $label .= ' · '.$nama.(isset($payload['kode_karyawan']) ? ' ('.$payload['kode_karyawan'].')' : '');
            }
            if ($r->Status === 'RETRY_PENDING') {
                $label .= ' — menunggu percobaan ulang';
            }
            /* Created_At Outbox tersimpan UTC; panel menampilkan jam lokal seperti sumber lain. */
            $dibuat = $r->Created_At ? Carbon::parse($r->Created_At, 'UTC')->setTimezone(config('app.timezone')) : null;

            return [
                'export_id' => 'outbox-'.$r->Id_Task_Outbox,
                'id' => 'outbox-'.$r->Id_Task_Outbox,
                'type' => 'lms-outbox',
                'module' => 'lms',
                'format' => null,
                'format_label' => 'LMS',
                'status' => $status,
                'percent' => $status === 'ready' ? 100 : 0,
                'total_records' => 1,
                'processed_records' => $status === 'ready' ? 1 : 0,
                'file_name' => null,
                'file_size' => null,
                'file_size_label' => null,
                'error_message' => in_array($r->Status, ['FAILED', 'RETRY_PENDING'], true) ? $r->Last_Error : null,
                'requested_at' => $dibuat?->isoFormat('D MMM YYYY HH:mm'),
                'label' => $label,
                'is_ready' => $status === 'ready',
                /* Bukan berkas; "sudah diunduh" tak bermakna. False supaya yang selesai tetap
                   terlihat selama jendela tampilnya — yang menghilangkannya waktu, bukan tombol. */
                'downloaded' => false,
                'download_url' => null,
                'retry_url' => null,
                'abort_url' => null,
                'dismiss_url' => null,
                'open_url' => '/lms/penjadwalan',
                'open_label' => 'Buka Penjadwalan',
                '_ts' => $dibuat?->timestamp ?? 0,
            ];
        })->all();
    }

    /** Bentuk seragam tugas latar PA: aksi panel dan tautan ke Langkah 5 sama untuk semuanya. */
    private function petakanTugasPa(object $job, string $tipe, string $label, string $formatLabel, ?array $buka = null): array
    {
        $aktif = in_array($job->Status, self::ACTIVE, true);
        $gagal = $job->Status === AbstractExportService::STATUS_FAILED;

        return array_merge(AbstractExportService::mapRow($job, $label), [
            'id' => $job->Export_Id,
            'type' => $tipe,
            'format_label' => $formatLabel,
            'status' => $job->Status,
            'is_ready' => $job->Status === AbstractExportService::STATUS_READY,
            'downloaded' => false,
            'download_url' => null,
            // Aman diulang: kedua pekerjaannya melewati yang sudah tersimpan.
            'retry_url' => $gagal ? "/api/v1/activities/{$tipe}/{$job->Export_Id}/retry" : null,
            'abort_url' => $aktif ? "/api/v1/activities/{$tipe}/{$job->Export_Id}/abort" : null,
            'dismiss_url' => $aktif ? null : "/api/v1/activities/{$tipe}/{$job->Export_Id}/dismiss",
            'open_url' => $job->Reference_Id
                ? ($buka['url'] ?? "/performance-appraisal/siapkan/{$job->Reference_Id}?langkah=PENUGASAN") : null,
            'open_label' => $buka['label'] ?? 'Buka Langkah 5',
            '_ts' => $job->Created_At ? Carbon::parse($job->Created_At)->timestamp : 0,
        ]);
    }

    private function namaSiklus(array $payload): string
    {
        return trim((string) ($payload['nama_siklus'] ?? '')) ?: 'Siklus PA';
    }

    private function angka(int $n): string
    {
        return number_format($n, 0, ',', '.');
    }

    /**
     * "Rekonsiliasi Dokumen · {judul} · {N} paket", ditambah ringkasan hasil bila
     * task sudah selesai. Potongan yang gagal WAJIB disebut angkanya —
     * pembatasan yang senyap adalah pembatasan yang menipu.
     */
    private function reconcileLabel($job, array $payload): string
    {
        $hasil = is_array($payload['hasil'] ?? null) ? $payload['hasil'] : null;

        // Jalur dokumen memakai 'judul', jalur paket memakai 'nama_paket'.
        $judul = trim((string) (
            $payload['judul']
            ?? $payload['nama_paket']
            ?? ($hasil['judul'] ?? ($hasil['nama_paket'] ?? ''))
        ));
        if ($judul === '') {
            $judul = 'Dokumen';
        }

        $jumlahPaket = (int) ($payload['paket'] ?? $job->Total_Records);
        $label = "Rekonsiliasi Dokumen · {$judul} · {$jumlahPaket} paket";

        if (! $hasil) {
            return $label;
        }

        $label .= ' — '.(int) ($hasil['paket'] ?? 0).' paket, '
            .(int) ($hasil['diberitahu'] ?? 0).' diberitahu';

        $gagal = $hasil['gagal'] ?? 0;
        $gagal = is_array($gagal) ? count($gagal) : (int) $gagal;
        if ($gagal > 0) {
            $label .= ", {$gagal} potongan gagal";
        }

        return $label;
    }

    private function uploadLabel($job): string
    {
        $payload = json_decode($job->Payload_Json ?: '{}', true) ?: [];
        $judul = $payload['judul'] ?? 'Media';
        $tahap = $payload['tahap'] ?? null;
        $jenis = match (strtolower((string) ($job->Format ?? ''))) {
            'video' => 'VIDEO',
            'pdf' => 'PDF',
            default => 'DOKUMEN',
        };

        $label = 'Upload · '.$judul.' · '.$jenis;
        if ($tahap && $job->Status === AbstractExportService::STATUS_PROCESSING) {
            $label .= ' · '.$tahap.'…';
        }

        return $label;
    }

    // =====================================================================
    // Sumber 3: Render PDF (N_LMS_Master_Konten_Berkas)
    // =====================================================================

    /**
     * Adapter render PDF → bentuk seragam. Activity Center bersifat admin-facing,
     * jadi menampilkan render yang sedang berjalan / gagal (PENDING/PROCESSING/
     * FAILED) secara global. DONE tidak ditampilkan (tidak ada yang perlu diunduh).
     */
    private function pdfRenderItems(): array
    {
        // Berkas yang sudah dilacak task upload (Task_Type='upload') ditangani oleh
        // uploadItems() — jangan tampilkan ganda di sini.
        $trackedBerkasIds = $this->berkasIdsTrackedByUpload();

        try {
            $rows = DB::table('N_LMS_Master_Konten_Berkas as a')
                ->join('N_LMS_Master_Konten as b', 'a.Id_Master_Konten', '=', 'b.Id_Master_Konten')
                ->where('a.Type_File', 'like', '%pdf%')
                ->where('b.Flag_Aktif', 'Y')
                ->where('b.Tipe_Konten', 'document')
                ->whereIn('a.Status_Render', ['PENDING', 'PROCESSING', 'FAILED'])
                ->when(! empty($trackedBerkasIds), fn ($q) => $q->whereNotIn('a.Id_Master_Konten_Berkas', $trackedBerkasIds))
                ->select([
                    'a.Id_Master_Konten_Berkas',
                    'a.Status_Render',
                    'a.Total_Halaman',
                    'a.Error_Message',
                    'a.Updated_At',
                    'b.Judul_Konten',
                ])
                ->orderByDesc('a.Updated_At')
                ->limit(20)
                ->get();
        } catch (\Throwable $e) {
            return [];
        }

        return $rows->map(function ($r) {
            $hashId = Hashids::connection('custom')->encode($r->Id_Master_Konten_Berkas);
            $status = $this->mapRenderStatus($r->Status_Render);

            return [
                'id' => $hashId,
                'export_id' => $hashId,
                'type' => 'pdf-render',
                'module' => 'lms-content',
                'format' => 'pdf',
                'format_label' => 'PDF',
                'label' => 'Render PDF · '.($r->Judul_Konten ?: 'Dokumen'),
                'status' => $status,
                'percent' => $status === 'processing' ? null : ($status === 'queued' ? 0 : 100),
                'total_records' => (int) ($r->Total_Halaman ?? 0),
                'processed_records' => 0,
                'file_name' => null,
                'file_size_label' => null,
                'error_message' => $r->Error_Message,
                'requested_at' => $r->Updated_At ? Carbon::parse($r->Updated_At)->isoFormat('D MMM YYYY HH:mm') : null,
                'is_ready' => false,
                'downloaded' => false,
                'download_url' => null, // render PDF bukan file unduhan
                'retry_url' => "/api/v1/activities/pdf-render/{$hashId}/retry",
                'dismiss_url' => null, // tidak bisa di-dismiss (status sumber)
                '_ts' => $r->Updated_At ? Carbon::parse($r->Updated_At)->timestamp : 0,
            ];
        })->all();
    }

    private function mapRenderStatus(string $statusRender): string
    {
        return match (strtoupper($statusRender)) {
            'PENDING' => 'queued',
            'PROCESSING' => 'processing',
            'FAILED' => 'failed',
            'DONE' => 'ready',
            default => 'queued',
        };
    }

    /**
     * Id_Master_Konten_Berkas yang sudah dilacak oleh task upload aktif (belum
     * di-silang). Diambil dari payload (berkas_id) supaya pdfRenderItems() tidak
     * menampilkan render ganda untuk berkas yang sama.
     */
    private function berkasIdsTrackedByUpload(): array
    {
        try {
            $rows = DB::table(AbstractExportService::TABLE)
                ->where('Task_Type', 'upload')
                ->whereNull('Deleted_At')
                ->whereNotNull('Payload_Json')
                ->where('Payload_Json', 'like', '%"berkas_id":%')
                ->pluck('Payload_Json');
        } catch (\Throwable $e) {
            return [];
        }

        $ids = [];
        foreach ($rows as $json) {
            $payload = json_decode($json ?: '{}', true) ?: [];
            if (! empty($payload['berkas_id'])) {
                $ids[] = (int) $payload['berkas_id'];
            }
        }

        return $ids;
    }
}
