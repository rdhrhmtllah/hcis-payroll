<?php

namespace App\Services\VISITOR\Governance\MasterData;

use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use RuntimeException;

class VisitorMasterDataService
{
    private array $usageCache = [];

    public function catalog(?string $domain = null): array
    {
        return array_values(VisitorMasterDataCatalog::forFrontend($domain));
    }

    public function list(string $master, array $filters = []): array
    {
        $definition = VisitorMasterDataCatalog::get($master);
        $query = $this->tabel($definition);
        $this->applyListFilters($query, $master, $definition, $filters);

        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = (int) ($filters['limit'] ?? 10);
        $total = (clone $query)->count();

        $this->applySorting($query, $master, $definition, $filters);

        $items = $query
            ->forPage($page, $limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
        $relations = $this->relationOptions($definition);
        $items = $this->appendRelationLabels($definition, $items, $relations);
        $items = $this->appendUsageStatus($master, $definition, $items);
        $summary = $this->summary($definition);

        return [
            'items' => $items,
            'relations' => $relations,
            'summary' => $summary,
            'stats' => $this->stats($master, $definition, $summary),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ];
    }

    /**
     * Seluruh baris satu master, tanpa halaman, untuk export.
     *
     * Saringannya menumpang `applyListFilters` yang dipakai layar — BUKAN
     * salinan baru. Saringan yang ditulis dua kali akan menyimpang diam-diam,
     * dan berkas yang terunduh lalu berisi baris yang barusan disaring hilang
     * dari tabel di layar; tidak ada galat, hanya angka yang tidak cocok.
     *
     * Tanpa `sort_by` urutannya mengikuti `order` katalog (urutan bisnis, mis.
     * abjad nama), bukan id terbaru: berkas master dibaca manusia.
     *
     * @return list<array<string, mixed>>
     */
    public function rowsForExport(string $master, array $filters = []): array
    {
        $definition = VisitorMasterDataCatalog::get($master);
        $query = $this->tabel($definition);
        $this->applyListFilters($query, $master, $definition, $filters);

        if (! empty($filters['sort_by'])) {
            $this->applySorting($query, $master, $definition, $filters);
        } else {
            foreach ($definition['order'] as $column) {
                $query->orderBy($column);
            }
        }

        $items = $query->get()->map(fn ($row) => (array) $row)->all();

        return $this->appendRelationLabels($definition, $items, $this->relationOptions($definition));
    }

    /** Jumlah baris satu master tanpa saringan apa pun; pembanding pada pratinjau export. */
    public function countAll(string $master): int
    {
        return $this->tabel(VisitorMasterDataCatalog::get($master))->count();
    }

    private function applyListFilters($query, string $master, array $definition, array $filters): void
    {
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $columns = collect($definition['fields'])
                ->filter(fn (array $field) => in_array($field['type'], ['text', 'textarea', 'color', 'select'], true))
                ->pluck('key')
                ->all();

            $query->where(function ($nested) use ($columns, $search) {
                foreach ($columns as $column) {
                    $nested->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        // Bawaannya AKTIF SAJA.
        //
        // Master data dibaca untuk menjawab "apa yang sedang berlaku", bukan
        // "apa yang pernah ada". Baris nonaktif yang ikut tampil membuat daftar
        // memanjang oleh pilihan yang justru sudah dihentikan, dan admin harus
        // memeriksa lencana status setiap kali sebelum percaya pada barisnya.
        //
        // Ia tidak disembunyikan selamanya: saringan Status punya pilihan
        // 'Nonaktif' dan 'Semua', sehingga baris yang dinonaktifkan tetap bisa
        // ditemukan dan diaktifkan kembali. Menyembunyikan tanpa jalan kembali
        // akan mengubur barisnya - itu penghapusan yang menyamar.
        if ($definition['status']) {
            $status = $filters['Flag_Aktif'] ?? null;

            if ($status === null || $status === '') {
                $query->where($definition['status'], 'Y');
            } elseif ($status !== 'ALL') {
                $query->where($definition['status'], $status);
            }
        }

        if (! empty($filters['Usage_Status'])) {
            $this->applyUsageFilter($query, $master, $definition, $filters['Usage_Status']);
        }

        if ($master === 'vehicle-type' && ! empty($filters['Akses'])) {
            $query->where('Akses', $filters['Akses']);
        }

        if ($master === 'vehicle-model' && ! empty($filters['Id_Master_Tipe'])) {
            $query->where('Id_Master_Tipe', (int) $filters['Id_Master_Tipe']);
        }

        if ($master !== 'approval-question') {
            return;
        }

        if (! empty($filters['Wajib_Diisi'])) {
            $query->where('Wajib_Diisi', $filters['Wajib_Diisi']);
        }
        if (($filters['Target_Scope'] ?? null) === 'GLOBAL') {
            $query->whereNull('Target_Id_Kategori_Permit')->whereNull('Target_Id_Tipe_Kendaraan');
        } elseif (($filters['Target_Scope'] ?? null) === 'TARGETED') {
            $query->where(function ($nested) {
                $nested->whereNotNull('Target_Id_Kategori_Permit')->orWhereNotNull('Target_Id_Tipe_Kendaraan');
            });
        }
        if (! empty($filters['Target_Id_Kategori_Permit'])) {
            $query->where('Target_Id_Kategori_Permit', (int) $filters['Target_Id_Kategori_Permit']);
        }
        if (! empty($filters['Target_Id_Tipe_Kendaraan'])) {
            $query->where('Target_Id_Tipe_Kendaraan', (int) $filters['Target_Id_Tipe_Kendaraan']);
        }
    }

    private function applySorting($query, string $master, array $definition, array $filters): void
    {
        $sortBy = (string) ($filters['sort_by'] ?? $definition['id']);
        $direction = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowed = array_column($definition['columns'], 'key');
        $allowed[] = $definition['id'];
        if ($definition['status']) {
            $allowed[] = $definition['status'];
        }

        if (! in_array($sortBy, $allowed, true)) {
            $sortBy = $definition['id'];
            $direction = 'desc';
        }

        $expression = $this->virtualSortExpression($master, $sortBy, $definition['table']);
        if ($expression !== null) {
            $query->orderByRaw("({$expression}) {$direction}");
        } else {
            $query->orderBy($sortBy, $direction);
        }

        if ($sortBy !== $definition['id']) {
            $query->orderByDesc($definition['id']);
        }
    }

    private function virtualSortExpression(string $master, string $sortBy, string $table): ?string
    {
        if ($sortBy === 'Usage_Count') {
            return match ($master) {
                'kategori-permit' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Permit_Detail usage_row WHERE usage_row.Id_Master_Kategori_Permit = {$table}.Id_Kategori_Permit",
                'plat-kendaraan' => "SELECT (SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Permit_Detail permit_row WHERE permit_row.Plat_Nomor_Prefix = {$table}.Kode_Plat) + (SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Tamu_Detail guest_row WHERE guest_row.Plat_Nomor_Prefix = {$table}.Kode_Plat)",
                'vehicle-model' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Permit_Detail usage_row WHERE usage_row.Id_Master_Model = {$table}.Id_Vehicle_Model",
                'vehicle-color' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Permit_Detail usage_row WHERE usage_row.Id_Master_Warna = {$table}.Id_Master_Color",
                'durasi-permit' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Permit_Detail usage_row WHERE usage_row.Id_Master_Durasi = {$table}.Id_Durasi_Permit",
                'approval-question' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Permit_Checklist usage_row WHERE usage_row.Id_Master_Pertanyaan = {$table}.Id_Approval_Question",
                'purpose' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Transaksi_Tamu_Detail usage_row WHERE usage_row.Deksripsi_Keperluan = {$table}.Nama_Keperluan",
                default => null,
            };
        }

        return match ($master.'|'.$sortBy) {
            'approval-question|Target_Id_Kategori_Permit_label' => "SELECT TOP 1 category_row.Nama_Kategori FROM N_EMI_VISITOR_Master_Kategori_Permit category_row WHERE category_row.Id_Kategori_Permit = {$table}.Target_Id_Kategori_Permit",
            'approval-question|Target_Id_Tipe_Kendaraan_label' => "SELECT TOP 1 type_row.Nama_Tipe_Kendaraan FROM N_EMI_VISITOR_Master_Vehicle_Type type_row WHERE type_row.Id_Vehicle_Type = {$table}.Target_Id_Tipe_Kendaraan",
            'vehicle-model|Id_Master_Tipe_label' => "SELECT TOP 1 type_row.Nama_Tipe_Kendaraan FROM N_EMI_VISITOR_Master_Vehicle_Type type_row WHERE type_row.Id_Vehicle_Type = {$table}.Id_Master_Tipe",
            'kategori-permit|Jumlah_Pertanyaan' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Approval_Question question_row WHERE question_row.Target_Id_Kategori_Permit = {$table}.Id_Kategori_Permit",
            'vehicle-type|Jumlah_Model' => "SELECT COUNT(*) FROM N_EMI_VISITOR_Master_Vehicle_Model model_row WHERE model_row.Id_Master_Tipe = {$table}.Id_Vehicle_Type",
            default => null,
        };
    }

    private function applyUsageFilter($query, string $master, array $definition, string $status): void
    {
        $usage = $this->usageDefinition($master, $definition);
        if ($usage === null) {
            return;
        }

        [$itemColumn, $usedValues] = $usage;
        if ($status === 'USED') {
            $usedValues === [] ? $query->whereRaw('1 = 0') : $query->whereIn($itemColumn, $usedValues);

            return;
        }

        if ($usedValues !== []) {
            $query->whereNotIn($itemColumn, $usedValues);
        }
    }

    public function create(string $master, array $validated): array
    {
        $definition = VisitorMasterDataCatalog::get($master);
        $payload = $this->normalizePayload($definition, $validated, true);
        $payload = $this->lingkup($definition) + $payload + $this->jejak($definition, true);

        $id = DB::transaction(function () use ($definition, $payload) {
            $this->assertUnique($definition, $payload);
            $this->assertRelationsActive($definition, $payload);

            return $this->tabel($definition)->insertGetId($payload, $definition['id']);
        });

        $this->logMutation('create', $master, $id);

        return $this->find($definition, $id);
    }

    public function update(string $master, int $id, array $validated): array
    {
        $definition = VisitorMasterDataCatalog::get($master);
        $this->find($definition, $id);
        $payload = $this->normalizePayload($definition, $validated, false);
        $payload += $this->jejak($definition, false);

        DB::transaction(function () use ($definition, $payload, $id) {
            // Ambil application lock sebelum row lock agar dua update yang
            // saling menukar natural key tidak membentuk urutan lock terbalik.
            $this->acquireUniqueMutationLock($definition);

            $lockedExisting = $this->tabel($definition)
                ->where($definition['id'], $id)
                ->lockForUpdate()
                ->first();

            if (! $lockedExisting) {
                throw new DomainException('Data master tidak ditemukan atau baru saja dihapus.');
            }

            $this->assertRelationsActive($definition, $payload, (array) $lockedExisting);
            $this->assertUnique($definition, $payload, $id, true);

            $affected = $this->tabel($definition)
                ->where($definition['id'], $id)
                ->update($payload);

            if ($affected > 1) {
                throw new RuntimeException('Master update affected an unexpected number of rows.');
            }
        });

        $this->logMutation('update', $master, $id);

        return $this->find($definition, $id);
    }

    public function changeStatus(string $master, int $id, string $status): array
    {
        $definition = VisitorMasterDataCatalog::get($master);
        if (! $definition['status']) {
            throw new LogicException('Master ini tidak mendukung perubahan status.');
        }

        $this->find($definition, $id);
        $affected = $this->tabel($definition)
            ->where($definition['id'], $id)
            ->update([$definition['status'] => $status] + $this->jejak($definition, false));

        if ($affected > 1) {
            throw new RuntimeException('Master status update affected an unexpected number of rows.');
        }

        $this->logMutation($status === 'Y' ? 'activate' : 'deactivate', $master, $id);

        return $this->find($definition, $id);
    }

    private function normalizePayload(array $definition, array $validated, bool $creating): array
    {
        $payload = [];

        foreach ($definition['fields'] as $field) {
            $key = $field['key'];
            $value = array_key_exists($key, $validated) ? $validated[$key] : ($field['default'] ?? null);

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' && ! $field['required'] ? null : $value;
            }

            if (in_array($key, $definition['uppercase'] ?? [], true) && is_string($value)) {
                $value = strtoupper($value);
            }

            $payload[$key] = $value;
        }

        if ($creating && $definition['status']) {
            $payload[$definition['status']] = 'Y';
        }

        return $payload;
    }

    private function assertUnique(
        array $definition,
        array $payload,
        ?int $exceptId = null,
        bool $lockAcquired = false,
    ): void {
        if (! $lockAcquired) {
            $this->acquireUniqueMutationLock($definition);
        }

        $query = $this->tabel($definition);

        foreach ($definition['unique_by'] as $column) {
            $value = $payload[$column] ?? null;

            if ($value === null) {
                $query->whereNull($column);
            } elseif (is_string($value)) {
                $query->whereRaw("LOWER(LTRIM(RTRIM({$column}))) = LOWER(LTRIM(RTRIM(?)))", [$value]);
            } else {
                $query->where($column, $value);
            }
        }

        if ($exceptId !== null) {
            $query->where($definition['id'], '!=', $exceptId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw new DomainException('Data master dengan kombinasi nilai yang sama sudah tersedia.');
        }
    }

    private function acquireUniqueMutationLock(array $definition): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Visitor master unique lock requires an active transaction.');
        }

        // Lock per tabel, bukan hash nilai PHP. Dengan demikian aturan case dan
        // accent mengikuti query/collation SQL Server dan dua nilai yang dianggap
        // sama oleh database tidak dapat lolos lewat resource lock berbeda.
        $resource = 'VISITOR:MASTER:UNIQUE:'.hash('sha256', $definition['table']);

        $lock = DB::selectOne(
            "DECLARE @result int;
             EXEC @result = sys.sp_getapplock
                 @Resource = ?,
                 @LockMode = 'Exclusive',
                 @LockOwner = 'Transaction',
                 @LockTimeout = 5000;
             SELECT @result AS result;",
            [$resource],
        );

        if ((int) ($lock->result ?? -999) < 0) {
            throw new RuntimeException('Master data sedang diperbarui oleh proses lain. Silakan coba lagi.');
        }
    }

    private function assertRelationsActive(array $definition, array $payload, array $existing = []): void
    {
        foreach ($definition['fields'] as $field) {
            if ($field['type'] !== 'relation' || empty($payload[$field['key']])) {
                continue;
            }

            $relation = VisitorMasterDataCatalog::get($field['relation']);
            $related = DB::table($relation['table'])
                ->where($relation['id'], $payload[$field['key']])
                ->sharedLock()
                ->first();

            if (! $related) {
                throw new DomainException("{$field['label']} tidak ditemukan.");
            }

            $unchanged = (string) ($existing[$field['key']] ?? '') === (string) $payload[$field['key']];
            if ($relation['status'] && ($related->{$relation['status']} ?? 'T') !== 'Y' && ! $unchanged) {
                throw new DomainException("{$field['label']} yang dipilih sudah nonaktif.");
            }
        }
    }

    private function find(array $definition, int $id): array
    {
        $row = $this->tabel($definition)->where($definition['id'], $id)->first();
        if (! $row) {
            throw new DomainException('Data master tidak ditemukan.');
        }

        return (array) $row;
    }

    private function appendRelationLabels(array $definition, array $items, array $options): array
    {
        return array_map(function (array $item) use ($definition, $options) {
            foreach ($definition['fields'] as $field) {
                if ($field['type'] !== 'relation') {
                    continue;
                }

                $selected = collect($options[$field['key']] ?? [])->firstWhere('value', $item[$field['key']] ?? null);
                $item[$field['key'].'_label'] = $selected['label'] ?? 'Semua';
            }

            return $item;
        }, $items);
    }

    private function relationOptions(array $definition): array
    {
        $result = [];

        foreach ($definition['fields'] as $field) {
            if ($field['type'] !== 'relation') {
                continue;
            }

            $relation = VisitorMasterDataCatalog::get($field['relation']);
            $labelField = $relation['fields'][0]['key'];

            $result[$field['key']] = DB::table($relation['table'])
                ->orderBy($labelField)
                ->get()
                ->map(fn ($row) => [
                    'value' => $row->{$relation['id']},
                    'label' => (string) $row->{$labelField},
                    'active' => ! $relation['status'] || ($row->{$relation['status']} ?? 'T') === 'Y',
                ])->values()->all();
        }

        return $result;
    }

    private function summary(array $definition): array
    {
        $query = $this->tabel($definition)->selectRaw('COUNT(*) as total');
        if ($definition['status']) {
            $query->selectRaw(
                "SUM(CASE WHEN {$definition['status']} = 'Y' THEN 1 ELSE 0 END) as active",
            );
        }

        $aggregate = $query->first();
        $total = (int) ($aggregate->total ?? 0);
        $active = $definition['status'] ? (int) ($aggregate->active ?? 0) : $total;

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $definition['status'] ? $total - $active : null,
        ];
    }

    /** @param array{total:int,active:int,inactive:int|null} $summary */
    private function stats(string $master, array $definition, array $summary): array
    {
        $activeQuery = $this->tabel($definition);
        if ($definition['status']) {
            $activeQuery->where($definition['status'], 'Y');
        }

        $total = $summary['total'];
        $active = $summary['active'];
        $used = $this->usageCount($master, $definition);

        return match ($master) {
            'kategori-permit' => [
                $this->metric('Total kategori', $total, 'tags-fill', 'purple', 'Seluruh klasifikasi akses'),
                $this->metric('Kategori aktif', $active, 'check-circle-fill', 'success', 'Tersedia untuk permit baru'),
                $this->metric(
                    'Sudah digunakan',
                    $used,
                    'diagram-3-fill',
                    'info',
                    'Kategori yang punya riwayat permit',
                ),
            ],
            'plat-kendaraan' => [
                $this->metric('Total kode plat', $total, '123', 'purple', 'Prefix wilayah terdaftar'),
                $this->metric('Kode aktif', $active, 'check-circle-fill', 'success', 'Tersedia di form Visitor'),
                $this->metric(
                    'Sudah digunakan',
                    $used,
                    'car-front-fill',
                    'info',
                    'Prefix pada riwayat Tamu/Permit',
                ),
            ],
            'vehicle-type' => [
                $this->metric('Tipe aktif', $active, 'truck-front-fill', 'purple', 'Seluruh tipe yang tersedia'),
                $this->metric('Pilihan Permit', (clone $activeQuery)->whereIn('Akses', ['ALL', 'PERMIT'])->count(), 'shield-check', 'success', 'ALL atau khusus Permit'),
                $this->metric('Pilihan Tamu', (clone $activeQuery)->whereIn('Akses', ['ALL', 'TAMU'])->count(), 'person-vcard-fill', 'info', 'ALL atau khusus Tamu'),
            ],
            'vehicle-model' => [
                $this->metric('Total model', $total, 'car-front-fill', 'purple', 'Seluruh model kendaraan'),
                $this->metric('Model aktif', $active, 'check-circle-fill', 'success', 'Tersedia untuk permit baru'),
                $this->metric(
                    'Sudah digunakan',
                    $used,
                    'diagram-3-fill',
                    'info',
                    'Model yang punya riwayat permit',
                ),
            ],
            'vehicle-color' => $this->statusUsageStats(
                'warna',
                $total,
                $active,
                $used,
                'palette-fill',
                'Pilihan warna kendaraan',
            ),
            'durasi-permit' => $this->durationStats($activeQuery),
            'approval-question' => [
                $this->metric('Pertanyaan aktif', $active, 'patch-question-fill', 'purple', 'Checklist yang dapat ditampilkan'),
                $this->metric('Wajib diisi', (clone $activeQuery)->where('Wajib_Diisi', 'Y')->count(), 'check2-square', 'success', 'Harus dijawab sebelum approval'),
                $this->metric(
                    'Pertanyaan tertarget',
                    (clone $activeQuery)->where(function ($query) {
                        $query->whereNotNull('Target_Id_Kategori_Permit')->orWhereNotNull('Target_Id_Tipe_Kendaraan');
                    })->count(),
                    'bullseye',
                    'info',
                    'Khusus kategori atau tipe tertentu',
                ),
            ],
            'purpose' => $this->statusUsageStats(
                'keperluan',
                $total,
                $active,
                $used,
                'chat-left-text-fill',
                'Pilihan standar pendaftaran tamu',
            ),
            default => [
                $this->metric('Total data', $total, 'collection-fill', 'purple'),
                $this->metric('Aktif', $active, 'check-circle-fill', 'success'),
                $this->metric('Nonaktif', $definition['status'] ? $total - $active : 0, 'pause-circle-fill', 'neutral'),
            ],
        };
    }

    private function durationStats($activeRows): array
    {
        $values = $activeRows->pluck('Jumlah_Angka')->map(fn ($value) => (int) $value);

        return [
            $this->metric('Opsi durasi aktif', $activeRows->count(), 'calendar-range-fill', 'purple', 'Tersedia saat final approval'),
            $this->metric('Durasi terpendek', $values->isEmpty() ? '—' : $values->min().' bulan', 'calendar-minus', 'info', 'Batas minimum pilihan'),
            $this->metric('Durasi terpanjang', $values->isEmpty() ? '—' : $values->max().' bulan', 'calendar-plus', 'success', 'Batas maksimum pilihan'),
        ];
    }

    private function statusUsageStats(
        string $entity,
        int $total,
        int $active,
        int $used,
        string $icon,
        string $caption,
    ): array {
        return [
            $this->metric('Total '.$entity, $total, $icon, 'purple', $caption),
            $this->metric(ucfirst($entity).' aktif', $active, 'check-circle-fill', 'success', 'Tersedia untuk transaksi baru'),
            $this->metric('Sudah digunakan', $used, 'diagram-3-fill', 'info', 'Memiliki riwayat transaksi'),
        ];
    }

    private function appendUsageStatus(string $master, array $definition, array $items): array
    {
        $usage = $this->usageDefinition($master, $definition);

        if ($usage === null) {
            return $this->appendRelatedCounts($master, $items);
        }

        [$itemColumn, $usedValues] = $usage;
        $lookup = collect($usedValues)
            ->mapWithKeys(fn ($value) => [$this->usageKey($value) => true]);
        $usageCounts = $usage[2] ?? [];

        $items = array_map(function (array $item) use ($itemColumn, $lookup, $usageCounts) {
            $key = $this->usageKey($item[$itemColumn] ?? null);
            $item['Usage_Status'] = $lookup->has($key) ? 'USED' : 'UNUSED';
            $item['Usage_Count'] = (int) ($usageCounts[$key] ?? 0);

            return $item;
        }, $items);

        return $this->appendRelatedCounts($master, $items);
    }

    private function usageDefinition(string $master, array $definition): ?array
    {
        if (array_key_exists($master, $this->usageCache)) {
            return $this->usageCache[$master];
        }

        return $this->usageCache[$master] = match ($master) {
            'kategori-permit' => $this->usageTuple($definition['id'], $this->groupedUsage('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Id_Master_Kategori_Permit')),
            'plat-kendaraan' => $this->usageTuple('Kode_Plat', $this->groupedPlateUsage()),
            'vehicle-model' => $this->usageTuple($definition['id'], $this->groupedUsage('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Id_Master_Model')),
            'vehicle-color' => $this->usageTuple($definition['id'], $this->groupedUsage('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Id_Master_Warna')),
            'durasi-permit' => $this->usageTuple($definition['id'], $this->groupedUsage('N_EMI_VISITOR_Transaksi_Permit_Detail', 'Id_Master_Durasi')),
            'approval-question' => $this->usageTuple($definition['id'], $this->groupedUsage('N_EMI_VISITOR_Transaksi_Permit_Checklist', 'Id_Master_Pertanyaan')),
            'purpose' => $this->usageTuple('Nama_Keperluan', $this->groupedUsage('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'Deksripsi_Keperluan')),
            default => null,
        };
    }

    private function usageCount(string $master, array $definition): int
    {
        $usage = $this->usageDefinition($master, $definition);
        if ($usage === null) {
            return 0;
        }

        [$itemColumn, $usedValues] = $usage;
        if ($usedValues === []) {
            return 0;
        }

        return $this->tabel($definition)->whereIn($itemColumn, $usedValues)->count();
    }

    private function usageTuple(string $itemColumn, array $rows): array
    {
        $values = [];
        $counts = [];

        foreach ($rows as $row) {
            $values[] = $row['value'];
            $counts[$this->usageKey($row['value'])] = (int) $row['count'];
        }

        return [$itemColumn, $values, $counts];
    }

    private function groupedUsage(string $table, string $column): array
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->select($column.' as Usage_Value')
            ->selectRaw('COUNT(*) as Usage_Count')
            ->groupBy($column)
            ->get()
            ->map(fn ($row) => ['value' => $row->Usage_Value, 'count' => $row->Usage_Count])
            ->all();
    }

    private function groupedPlateUsage(): array
    {
        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->selectRaw('Plat_Nomor_Prefix as Usage_Value')
            ->whereNotNull('Plat_Nomor_Prefix');

        $guest = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->selectRaw('Plat_Nomor_Prefix as Usage_Value')
            ->whereNotNull('Plat_Nomor_Prefix');

        return DB::query()
            ->fromSub($permit->unionAll($guest), 'plate_usage')
            ->select('Usage_Value')
            ->selectRaw('COUNT(*) as Usage_Count')
            ->groupBy('Usage_Value')
            ->get()
            ->map(fn ($row) => ['value' => $row->Usage_Value, 'count' => $row->Usage_Count])
            ->all();
    }

    private function appendRelatedCounts(string $master, array $items): array
    {
        if ($master === 'kategori-permit') {
            $counts = $this->groupedUsage('N_EMI_VISITOR_Master_Approval_Question', 'Target_Id_Kategori_Permit');
            $lookup = collect($counts)->mapWithKeys(fn ($row) => [$this->usageKey($row['value']) => (int) $row['count']]);

            return array_map(function (array $item) use ($lookup) {
                $item['Jumlah_Pertanyaan'] = $lookup->get($this->usageKey($item['Id_Kategori_Permit'] ?? null), 0);

                return $item;
            }, $items);
        }

        if ($master === 'vehicle-type') {
            $counts = $this->groupedUsage('N_EMI_VISITOR_Master_Vehicle_Model', 'Id_Master_Tipe');
            $lookup = collect($counts)->mapWithKeys(fn ($row) => [$this->usageKey($row['value']) => (int) $row['count']]);

            return array_map(function (array $item) use ($lookup) {
                $item['Jumlah_Model'] = $lookup->get($this->usageKey($item['Id_Vehicle_Type'] ?? null), 0);

                return $item;
            }, $items);
        }

        return $items;
    }

    private function usageKey(mixed $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    private function metric(string $label, int|string $value, string $icon, string $sentiment, string $caption = ''): array
    {
        return compact('label', 'value', 'icon', 'sentiment', 'caption');
    }

    /**
     * Kueri tabel master, sudah dibatasi LINGKUP TETAP-nya bila ada.
     *
     * Master bertabel bersama (mis. kontak bantuan global milik SYSTEM) hanya
     * boleh melihat dan menyentuh barisnya sendiri: halaman Master Data Permit
     * tidak boleh membaca atau mengubah kontak modul lain. Seluruh akses tabel
     * di service ini lewat sini — satu akses langsung yang tertinggal berarti
     * baris modul lain ikut tersunting.
     */
    private function tabel(array $definition)
    {
        $query = DB::table($definition['table']);

        foreach ($this->lingkup($definition) as $kolom => $nilai) {
            $query->where($kolom, $nilai);
        }

        return $query;
    }

    /** @return array<string, mixed> kolom => nilai lingkup tetap (kosong bila tidak ada). */
    private function lingkup(array $definition): array
    {
        return $definition['scope'] ?? [];
    }

    /** Kolom jejak pelaku untuk master yang memilikinya ('jejak' => true). */
    private function jejak(array $definition, bool $membuat): array
    {
        if (! ($definition['jejak'] ?? false)) {
            return [];
        }

        $user = Auth::user();
        $pelaku = (string) ($user?->Kode_Karyawan ?? $user?->karyawan?->Kode_Karyawan ?? Auth::id() ?? 'SISTEM');

        return $membuat
            ? ['Created_By' => $pelaku]
            : ['Updated_At' => now(), 'Updated_By' => $pelaku];
    }

    private function logMutation(string $operation, string $master, int $id): void
    {
        Log::info('VISITOR_MASTER_DATA_MUTATED', [
            'module' => 'VISITOR',
            'feature' => 'Governance/MasterData',
            'operation' => $operation,
            'master' => $master,
            'entity_id' => $id,
            'actor_user_id' => Auth::id(),
        ]);
    }
}
