<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Siklus hidup TUGAS LATAR milik satu siklus PA — satu baris N_HRIS_Async_Tasks per pekerjaan.
 *
 * Dipakai dua pekerjaan panjang di Langkah 5 penyiapan: pembentukan penugasan dan penjadwalan
 * rater ke HC Learn. Keduanya dulu digerakkan browser, putaran demi putaran, dan untuk siklus
 * produksi (10.000+) admin harus menjaga satu tab tetap terbuka belasan menit. Sekarang job
 * berantai di server yang mengerjakannya, dan barisnya tampil di panel Tugas & Aktivitas.
 *
 * Yang SAMA di keduanya ada di sini: satu tugas berjalan per siklus (`Reference_Id`), klaim,
 * hentikan, ulangi, sembunyikan. Yang BERBEDA — isi payload, cara menghitung persen, apa yang
 * di-reset saat diulang — ditentukan turunannya.
 *
 * Mengikuti pola `SopBulkAssignTaskService`: memakai tabel dan konstanta AbstractExportService
 * TANPA meng-extend-nya, karena tugas ini tidak menghasilkan berkas.
 */
abstract class PaTugasLatarSiklusService
{
    public const TASK_TYPE = '';

    public const MODULE = '';

    protected const AWALAN_ID = 'pa-tgs';

    protected const FORMAT = '';

    protected const AKTIF = [
        AbstractExportService::STATUS_QUEUED,
        AbstractExportService::STATUS_PROCESSING,
    ];

    /** Persen 0..100 dari ringkasan milik pekerjaan ini. */
    abstract public static function persen(array $ringkasan): int;

    /** Berapa yang sudah beres — mengisi `Processed_Records`. */
    abstract protected function diproses(array $ringkasan): int;

    /** Payload awal khusus pekerjaan ini (di luar cycle_id, nama_siklus, user_id, ringkasan). */
    abstract protected function payloadAwal(): array;

    /** Field payload yang dikembalikan ke keadaan awal saat tugas yang gagal diulang. */
    protected function payloadUlang(array $payload): array
    {
        return array_merge($payload, $this->payloadAwal());
    }

    /**
     * Mencatat tugas baru, ATAU mengembalikan tugas siklus ini yang masih berjalan.
     *
     * Dua tugas untuk satu siklus berarti dua rantai job yang memperebutkan baris yang sama.
     * Satu siklus, satu tugas per jenis pekerjaan.
     *
     * @return array{id:string, baru:bool}
     */
    public function mulai(int $cycleId, array $ringkasan, ?int $userId, ?string $requestedBy): array
    {
        $berjalan = $this->berjalanUntukSiklus($cycleId);

        if ($berjalan) {
            return ['id' => $berjalan->Export_Id, 'baru' => false];
        }

        $id = static::AWALAN_ID.'-'.now()->format('Ymd-His').'-'.strtoupper(Str::random(6));
        $now = now();

        DB::table(AbstractExportService::TABLE)->insert([
            'Export_Id' => $id,
            'Task_Type' => static::TASK_TYPE,
            'Module' => static::MODULE,
            'Format' => static::FORMAT,
            'Payload_Json' => json_encode([
                'cycle_id' => $cycleId,
                'nama_siklus' => (string) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Nama_Cycle'),
                'user_id' => $userId,
                'ringkasan' => $ringkasan,
                ...$this->payloadAwal(),
            ]),
            'Status' => AbstractExportService::STATUS_QUEUED,
            'Total_Records' => max(1, (int) ($ringkasan['total'] ?? 0)),
            'Processed_Records' => $this->diproses($ringkasan),
            'Percent' => static::persen($ringkasan),
            'Requested_By' => $requestedBy,
            // Siklusnya: kolom bertipe, bukan pencarian di dalam Payload_Json.
            'Reference_Id' => $cycleId,
            'Created_At' => $now,
            'Updated_At' => $now,
        ]);

        return ['id' => $id, 'baru' => true];
    }

    public function baris(string $id): ?object
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)
            ->where('Task_Type', static::TASK_TYPE)
            ->whereNull('Deleted_At')
            ->first();
    }

    public function payload(object $baris): array
    {
        return json_decode($baris->Payload_Json ?: '{}', true) ?: [];
    }

    /** Tugas siklus ini yang masih antre atau berjalan. */
    public function berjalanUntukSiklus(int $cycleId): ?object
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Task_Type', static::TASK_TYPE)
            ->whereNull('Deleted_At')
            ->where('Reference_Id', $cycleId)
            ->whereIn('Status', static::AKTIF)
            ->orderByDesc('Id_Export')
            ->first();
    }

    /**
     * Tugas TERAKHIR siklus ini, apa pun statusnya — termasuk yang sudah disembunyikan dari
     * panel. Menyembunyikan dari panel tidak boleh membuat Langkah 5 lupa bahwa tugasnya gagal.
     */
    public function terakhirUntukSiklus(int $cycleId): ?object
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Task_Type', static::TASK_TYPE)
            ->where('Reference_Id', $cycleId)
            ->orderByDesc('Id_Export')
            ->first();
    }

    /** Hanya queued/processing/failed yang boleh diklaim — yang ready tak dijalankan ulang. */
    public function klaim(string $id): bool
    {
        return DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)
            ->where('Task_Type', static::TASK_TYPE)
            ->whereIn('Status', [...static::AKTIF, AbstractExportService::STATUS_FAILED])
            ->whereNull('Deleted_At')
            ->update([
                'Status' => AbstractExportService::STATUS_PROCESSING,
                'Started_At' => DB::raw('COALESCE(Started_At, GETDATE())'),
                'Updated_At' => now(),
            ]) > 0;
    }

    /** Menulis keadaan terbaru ke payload, berikut angka kemajuannya. */
    public function catat(string $id, array $ubah): void
    {
        $baris = $this->baris($id);
        if (! $baris) {
            return;
        }

        $payload = array_merge($this->payload($baris), $ubah);
        $ringkasan = (array) ($payload['ringkasan'] ?? []);

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', static::TASK_TYPE)
            ->update([
                'Payload_Json' => json_encode($payload),
                'Total_Records' => max(1, (int) ($ringkasan['total'] ?? 0)),
                'Processed_Records' => $this->diproses($ringkasan),
                'Percent' => min(99, static::persen($ringkasan)),
                'Updated_At' => now(),
            ]);
    }

    public function selesai(string $id, ?string $catatan = null): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', static::TASK_TYPE)
            ->update([
                'Status' => AbstractExportService::STATUS_READY,
                'Percent' => 100,
                'Processed_Records' => DB::raw('Total_Records'),
                'Error_Message' => $catatan === null ? null : mb_substr($catatan, 0, 255),
                'Completed_At' => now(),
                'Updated_At' => now(),
            ]);
    }

    public function gagal(string $id, string $pesan): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', static::TASK_TYPE)
            ->update([
                'Status' => AbstractExportService::STATUS_FAILED,
                // Kolomnya varchar(255).
                'Error_Message' => mb_substr(static::pesanTerbaca($pesan), 0, 255),
                'Updated_At' => now(),
            ]);
    }

    /**
     * Galat basis data diterjemahkan ke kalimat yang bisa ditindaklanjuti.
     *
     * Terjadi 13 Okt 2026: disk server SQL dev penuh, dan pembentukan "berhenti di 9%" dengan
     * satu-satunya keterangan berupa teks SQL mentah di footer. Admin tidak bisa
     * membereskannya, tetapi ia harus tahu bahwa itu bukan kesalahannya dan siapa yang dihubungi.
     */
    public static function pesanTerbaca(string $pesan): string
    {
        if (str_contains($pesan, 'filegroup is full') || str_contains($pesan, 'Could not allocate space')) {
            return 'Database penuh — penyimpanan ditolak server SQL. Hubungi admin server untuk '
                .'mengosongkan ruang, lalu Coba Lagi. Yang sudah tersimpan tetap berlaku.';
        }

        return $pesan;
    }

    /**
     * Menyiapkan tugas yang gagal untuk dijalankan lagi.
     *
     * Aman karena kedua pekerjaannya idempoten — yang sudah terbentuk atau terkirim dilewati.
     */
    public function siapkanUlang(string $id): bool
    {
        $baris = $this->baris($id);
        if (! $baris || $baris->Status !== AbstractExportService::STATUS_FAILED) {
            return false;
        }

        $payload = $this->payload($baris);
        unset($payload['aborted']);
        $payload = $this->payloadUlang($payload);

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', static::TASK_TYPE)
            ->update([
                'Payload_Json' => json_encode($payload),
                'Status' => AbstractExportService::STATUS_QUEUED,
                'Error_Message' => null,
                'Completed_At' => null,
                'Updated_At' => now(),
            ]);

        return true;
    }

    /**
     * Menandai berhenti. Job yang sedang jalan menyelesaikan batch yang sudah dimulai, lalu
     * berhenti sebelum batch berikutnya — yang sudah tersimpan tetap sah.
     */
    public function hentikan(string $id): bool
    {
        $baris = $this->baris($id);
        if (! $baris || ! in_array($baris->Status, static::AKTIF, true)) {
            return false;
        }

        $payload = $this->payload($baris);
        $payload['aborted'] = true;

        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', static::TASK_TYPE)
            ->update([
                'Payload_Json' => json_encode($payload),
                'Status' => AbstractExportService::STATUS_FAILED,
                'Error_Message' => 'Dihentikan admin. Yang sudah tersimpan tetap berlaku; Coba Lagi untuk melanjutkan.',
                'Updated_At' => now(),
            ]);

        return true;
    }

    public function dihentikan(string $id): bool
    {
        $baris = $this->baris($id);

        return ! $baris || ! empty($this->payload($baris)['aborted']);
    }

    /** Sembunyikan dari panel (soft delete). */
    public function dismiss(string $id): void
    {
        DB::table(AbstractExportService::TABLE)
            ->where('Export_Id', $id)->where('Task_Type', static::TASK_TYPE)
            ->update(['Deleted_At' => now(), 'Updated_At' => now()]);
    }

    /**
     * Bentuk yang dibaca Langkah 5.
     *
     * @return array<string, mixed>|null
     */
    public function bentukLayar(?object $baris): ?array
    {
        if (! $baris) {
            return null;
        }

        $p = $this->payload($baris);

        return [
            'id' => $baris->Export_Id,
            'status' => $baris->Status,
            'batch' => (int) ($p['batch'] ?? 0),
            'persen' => (int) $baris->Percent,
            'ringkasan' => $p['ringkasan'] ?? null,
            'galat' => $baris->Error_Message,
            'contoh_galat' => $p['contoh_galat'] ?? [],
            'dimulai' => $baris->Created_At,
            'diperbarui' => $baris->Updated_At,
            ...$this->layarTambahan($p),
        ];
    }

    /** Field layar khusus pekerjaan ini. */
    protected function layarTambahan(array $payload): array
    {
        return [];
    }
}
