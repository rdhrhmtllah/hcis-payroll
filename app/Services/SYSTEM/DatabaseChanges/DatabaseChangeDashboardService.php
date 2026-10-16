<?php

namespace App\Services\SYSTEM\DatabaseChanges;

use App\Models\SYSTEM\DatabaseChanges\Models\SystemDatabaseChange;
use Illuminate\Support\Facades\File;
use RuntimeException;

class DatabaseChangeDashboardService
{
    private const STAGES = ['development', 'sql_tracker', 'production', 'verification'];

    public function list(array $filters = []): array
    {
        $query = SystemDatabaseChange::query()->where('Flag_Aktif', 'Y');
        if (! empty($filters['type'])) {
            $query->where('Jenis_Change', $filters['type']);
        }
        if (! empty($filters['stage'])) {
            $this->applyStageFilter($query, $filters['stage']);
        }
        $changes = $query->orderByDesc('Id_Database_Change')->get()->map(fn ($change) => $this->present($change))->values();

        return ['items' => $changes, 'summary' => $this->summary($changes->all())];
    }

    public function sql(SystemDatabaseChange $change): array
    {
        $path = $this->safeArtifactPath($change->Artifact_Path);
        if (! File::exists($path)) {
            throw new RuntimeException('File SQL tidak ditemukan: '.$change->Artifact_Path);
        }

        return ['id' => $change->Id_Database_Change, 'change_id' => $change->Kode_Change, 'type' => $change->Jenis_Change, 'artifact_path' => $change->Artifact_Path, 'content' => File::get($path)];
    }

    /** Berapa berkas boleh disalin sekali jalan. */
    public const BATAS_BULK = 25;

    /**
     * Beberapa changeset sekaligus, sebagai satu teks siap tempel.
     *
     * Urutannya DIPAKSA menurut Id_Database_Change — urutan pendaftaran —
     * bukan urutan id yang dikirim layar. Dengan begitu salah urut tidak bisa
     * lahir dari cara pengguna mengklik. Nomor pada Kode_Change sengaja tidak
     * dipakai mengurutkan: ia label, bukan urutan.
     *
     * Changeset yang berkasnya belum ada di worktree ini DILEWATI dan
     * dilaporkan, bukan didiamkan: melewatinya diam-diam membuat pengguna
     * mengira sudah menyalin semuanya.
     *
     * @param  array<int,int>  $ids
     * @return array<string,mixed>
     */
    public function bulkSql(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (count($ids) > self::BATAS_BULK) {
            throw new RuntimeException('Maksimal '.self::BATAS_BULK.' changeset sekali salin. Dipilih: '.count($ids).'.');
        }

        $changes = SystemDatabaseChange::query()
            ->whereIn('Id_Database_Change', $ids)
            ->orderBy('Id_Database_Change')
            ->get();

        if ($changes->isEmpty()) {
            throw new RuntimeException('Tidak ada changeset yang cocok dengan pilihan.');
        }

        $terpakai = [];
        $dilewati = [];
        $bagian = [];

        foreach ($changes as $change) {
            try {
                $path = $this->safeArtifactPath($change->Artifact_Path);
            } catch (RuntimeException $e) {
                $dilewati[] = ['change_id' => $change->Kode_Change, 'reason' => $e->getMessage()];

                continue;
            }

            $terpakai[] = $change;
            $bagian[] = ['change' => $change, 'content' => rtrim(File::get($path))];
        }

        if ($bagian === []) {
            throw new RuntimeException('Tidak ada berkas SQL yang bisa dibaca dari pilihan ini.');
        }

        $total = count($bagian);
        $teks = [$this->kepalaBulk($terpakai, $dilewati)];

        foreach ($bagian as $i => $b) {
            $teks[] = $this->kepalaBagian($b['change'], $i + 1, $total).$b['content'];
        }

        return [
            'content' => implode('

', $teks).'
',
            'included' => array_map(fn ($c) => [
                'id' => $c->Id_Database_Change,
                'change_id' => $c->Kode_Change,
                'type' => $c->Jenis_Change,
                'created_at' => optional($c->Created_At)->format('Y-m-d H:i'),
            ], $terpakai),
            'skipped' => $dilewati,
            'total' => $total,
        ];
    }

    /** @param  array<int,SystemDatabaseChange>  $terpakai */
    private function kepalaBulk(array $terpakai, array $dilewati): string
    {
        $baris = [
            '/* ============================================================',
            ' * SALINAN GABUNGAN '.count($terpakai).' CHANGESET',
            ' * Dibuat: '.now()->format('Y-m-d H:i'),
            ' *',
            ' * Urutan di bawah = urutan PENDAFTARAN (Id), yaitu urutan aman',
            ' * untuk dieksekusi. Nomor pada kode changeset BUKAN urutan.',
            ' *',
            ' * Daftar isi:',
        ];

        foreach ($terpakai as $i => $c) {
            $baris[] = ' *   '.str_pad((string) ($i + 1), 3, ' ', STR_PAD_LEFT).'. #'
                .str_pad((string) $c->Id_Database_Change, 4)
                .str_pad((string) $c->Jenis_Change, 5)
                .str_pad((string) $c->Kode_Change, 52)
                .' dibuat '.optional($c->Created_At)->format('Y-m-d H:i');
        }

        if ($dilewati !== []) {
            $baris[] = ' *';
            $baris[] = ' * DILEWATI ('.count($dilewati).') — berkasnya tidak terbaca di worktree ini:';
            foreach ($dilewati as $d) {
                $baris[] = ' *   - '.$d['change_id'].': '.$d['reason'];
            }
        }

        $baris[] = ' * ============================================================ */';

        return implode('
', $baris);
    }

    private function kepalaBagian(SystemDatabaseChange $change, int $urutan, int $total): string
    {
        return implode('
', [
            '-- ------------------------------------------------------------',
            '-- ['.$urutan.'/'.$total.']  #'.$change->Id_Database_Change.'  '.$change->Jenis_Change.'  '.$change->Kode_Change,
            '-- dibuat  : '.optional($change->Created_At)->format('Y-m-d H:i'),
            '-- artifact: '.$change->Artifact_Path,
            '-- ------------------------------------------------------------',
            '',
        ]);
    }

    public function updateStage(SystemDatabaseChange $change, string $stage, array $data = []): array
    {
        if (! in_array($stage, self::STAGES, true)) {
            throw new RuntimeException('Tahap status tidak valid.');
        }
        $field = match ($stage) {
            'development' => 'Status_Development', 'sql_tracker' => 'Status_SQL_Tracker', 'production' => 'Status_Production', 'verification' => 'Status_Verification'
        };
        $status = $data['status'] ?? 'DONE';
        $allowed = $stage === 'sql_tracker' ? ['PENDING', 'RECORDED'] : ($stage === 'verification' ? ['PENDING', 'DEV_DONE', 'DONE', 'FAILED'] : ['PENDING', 'DONE', 'FAILED']);
        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException('Status tidak valid untuk tahap '.$stage.'.');
        }
        $payload = [$field => $status, 'Updated_At' => now(), 'Updated_By' => optional(auth()->user())->UserID_Web ?? optional(auth()->user())->email ?? 'web-user'];
        if ($stage === 'sql_tracker') {
            $payload['SQL_Tracker_Reference'] = $data['reference'] ?? null;
        }
        $change->fill($payload)->save();

        return $this->present($change->refresh());
    }

    public function updateBatch(array $ids, string $stage, array $data = []): array
    {
        $changes = SystemDatabaseChange::query()->whereIn('Id_Database_Change', $ids)->where('Flag_Aktif', 'Y')->get();
        foreach ($changes as $change) {
            $this->updateStage($change, $stage, $data);
        }

        return $changes->map(fn ($change) => $this->present($change->refresh()))->values()->all();
    }

    private function present(SystemDatabaseChange $change): array
    {
        return ['id' => $change->Id_Database_Change, 'change_id' => $change->Kode_Change, 'type' => $change->Jenis_Change, 'title' => $change->Judul_Change, 'description' => $change->Deskripsi_Change, 'artifact_path' => $change->Artifact_Path, 'documentation_path' => $change->Documentation_Path, 'target_development' => $change->Target_Development, 'development' => $change->Status_Development, 'sql_tracker' => $change->Status_SQL_Tracker, 'sql_tracker_reference' => $change->SQL_Tracker_Reference, 'production' => $change->Status_Production, 'verification' => $change->Status_Verification, 'notes' => $change->Catatan,
            // Urutan pendaftaran DAN waktu dibuat ikut ke layar supaya urutan
            // eksekusi bisa diperiksa mata. Nomor pada Kode_Change TIDAK bisa
            // dipakai untuk itu: ia diambil saat changeset dirancang, sedangkan
            // pendaftarannya terjadi saat dieksekusi di dev — dua branch yang
            // jalan berbarengan mengambil nomor secara terpisah. Terukur: 47
            // dari 48 record berpindah posisi bila diurut menurut kode.
            'sequence' => $change->Id_Database_Change,
            'created_at' => optional($change->Created_At)->format('Y-m-d H:i'), 'action_required' => $change->Status_Development !== 'DONE' || $change->Status_SQL_Tracker !== 'RECORDED' || $change->Status_Production !== 'DONE' || $change->Status_Verification !== 'DONE'];
    }

    private function summary(array $items): array
    {
        return ['total' => count($items), 'development_done' => count(array_filter($items, fn ($i) => $i['development'] === 'DONE')), 'tracker_pending' => count(array_filter($items, fn ($i) => $i['sql_tracker'] === 'PENDING')), 'production_pending' => count(array_filter($items, fn ($i) => $i['production'] === 'PENDING')), 'completed' => count(array_filter($items, fn ($i) => ! $i['action_required']))];
    }

    private function applyStageFilter($query, string $stage): void
    {
        match ($stage) {
            'action_required' => $query->where(fn ($q) => $q->where('Status_Development', '!=', 'DONE')->orWhere('Status_SQL_Tracker', '!=', 'RECORDED')->orWhere('Status_Production', '!=', 'DONE')->orWhere('Status_Verification', '!=', 'DONE')),
            'tracker_pending' => $query->where('Status_SQL_Tracker', 'PENDING'),
            'production_pending' => $query->where('Status_Production', 'PENDING'),
            'completed' => $query->where('Status_Development', 'DONE')->where('Status_SQL_Tracker', 'RECORDED')->where('Status_Production', 'DONE')->where('Status_Verification', 'DONE'),
            default => null,
        };
    }

    /**
     * Jalur artifact yang boleh dibaca: HANYA di dalam database/ddl atau
     * database/dml, sesudah realpath() meratakan '..' sehingga tidak ada jalan
     * keluar dari pohon proyek.
     *
     * Pemisah jalur DINORMALKAN lebih dulu. Di Windows realpath() mengembalikan
     * backslash sedangkan pembandingnya memakai '/', sehingga penjaga ini
     * menolak SETIAP berkas yang sah — berkasnya ada, tetapi permintaannya
     * berbalas 404 dan layar berkata "Data yang diminta tidak ditemukan".
     * Di Linux ia kebetulan lolos karena pemisahnya memang '/', jadi cacat ini
     * tidak pernah muncul di produksi dan hanya mematikan mesin pengembangan.
     */
    private function safeArtifactPath(string $relativePath): string
    {
        $normalkan = static fn (string $jalur): string => str_replace(DIRECTORY_SEPARATOR, '/', $jalur);

        $root = rtrim($normalkan(base_path()), '/');
        $relatif = $normalkan($relativePath);

        // Ditolak SEBELUM menyentuh berkas: jalur di luar dua folder itu, atau
        // yang memuat '..', tidak perlu dibaca sama sekali.
        $polaSah = str_starts_with($relatif, 'database/ddl/') || str_starts_with($relatif, 'database/dml/');

        if (! $polaSah || in_array('..', explode('/', $relatif), true)) {
            throw new RuntimeException('Path artifact tidak diizinkan.');
        }

        $path = realpath(base_path().DIRECTORY_SEPARATOR.$relativePath);

        // realpath() mengembalikan false untuk berkas yang tidak ada. Menyamakan
        // keadaan itu dengan "tidak diizinkan" membuat pesan berbohong: berkas
        // changeset yang lahir di branch lain memang belum ada di worktree ini,
        // dan itu bukan soal izin.
        if ($path === false) {
            throw new RuntimeException('File SQL belum ada di worktree ini: '.$relativePath);
        }

        $path = $normalkan($path);

        // Pemeriksaan terakhir SESUDAH realpath meratakan symlink: yang tadi
        // diperiksa baru teksnya, ini yang memeriksa hasil nyatanya.
        foreach ([$root.'/database/ddl/', $root.'/database/dml/'] as $awalan) {
            if (str_starts_with($path, $awalan)) {
                return $path;
            }
        }

        throw new RuntimeException('Path artifact tidak diizinkan.');
    }
}
