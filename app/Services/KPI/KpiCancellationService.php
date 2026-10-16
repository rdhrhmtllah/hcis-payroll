<?php

namespace App\Services\KPI;

use App\Http\Controllers\KPI\Approval\Performance\PerformanceApprovalController;
use App\Http\Controllers\KPI\Approval\Verification\KpiVerifikasiController;
use App\Http\Controllers\KPI\Approval\Workflow\KpiApprovalController;
use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Services\KPI\Approval\KpiApprovalService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * KpiCancellationService
 *
 * Pembatalan transaksi KPI dari dashboard admin, untuk dua domain:
 *   parameter   -> KPI_Pengajuan
 *   performance -> KPI_Update_Performance
 *
 * KONTRAK PEMBATALAN (sudah dipatuhi seluruh codebase, kita hanya menulisnya):
 * header dengan Flag_Aktif='T' DAN Status='Y' = transaksi batal. Seluruh inbox
 * approver (KpiApprovalController, PerformanceApprovalController,
 * KpiVerifikasiController) dan seluruh query monitoring/enforcement sudah
 * memfilter "Flag_Aktif='Y' AND Status IS NULL", sehingga transaksi yang
 * dibatalkan otomatis lenyap dari antrian approval dan karyawannya kembali
 * berstatus "Belum Submit" tanpa perlu menyentuh engine approval.
 *
 * Cascade: membatalkan pengajuan parameter otomatis membatalkan SEMUA update
 * performance turunannya (Id_Pengajuan sama) lintas periode, plus draft yang
 * belum difinalisasi -- kalau tidak, user bisa memfinalisasi draft di atas
 * parameter yang sudah tidak ada.
 */
class KpiCancellationService
{
    /** Batas item utama per satu aksi pembatalan (cascade tidak dihitung). */
    public const MAX_ITEMS = 100;

    public const MIN_REASON = 10;

    /** Batas id per statement (SQL Server: maks 2100 parameter/statement). */
    private const CHUNK = 500;

    public function __construct(protected KpiApprovalService $kpiApproval) {}

    // =====================================================================
    // PREVIEW — dampak pembatalan (dipakai FE sebelum konfirmasi)
    // =====================================================================

    /**
     * Rakit preview dampak untuk sekumpulan id transaksi.
     *
     * Setiap item membawa:
     *   - identitas karyawan + status approval saat ini,
     *   - cascade (performance + draft) bila domain = parameter,
     *   - fingerprint state (anti race dengan approver),
     *   - blocked=true bila tidak layak dibatalkan (sudah batal / tidak ditemukan).
     *
     * @param  array<int>  $ids
     */
    public function buildPreview(string $domain, array $ids): array
    {
        $ids = collect($ids)->map(fn ($v) => (int) $v)->filter()->unique()->values();

        $headers = $this->fetchHeaders($domain, $ids->all());

        $items = [];
        foreach ($ids as $id) {
            $row = $headers->get($id);

            if (! $row) {
                $items[] = $this->blockedItem($id, 'Transaksi tidak ditemukan.');

                continue;
            }

            $blockReason = $this->blockReason($row, $domain);
            if ($blockReason !== null) {
                $items[] = $this->blockedItem($id, $blockReason, $row, $domain);

                continue;
            }

            // Draft belum punya alur approval; statusnya selalu "Draft".
            $derived = $domain === 'draft'
                ? ['status' => 'draft', 'label' => 'Draft']
                : $this->deriveApprovalStatus($row, $domain);
            $cascade = $domain === 'parameter' ? $this->cascadeFor($id) : ['performance' => [], 'draft' => []];

            $items[] = [
                'id' => $id,
                'domain' => $domain,
                'blocked' => false,
                'block_reason' => null,
                'no_transaksi' => $row->No_Transaksi,
                'id_user' => (int) $row->Id_User,
                'kode_karyawan' => $row->Kode_Karyawan,
                'nama' => $row->Nama,
                'divisi' => $row->Divisi,
                'departemen' => $row->Departemen,
                'jabatan' => $row->Jabatan,
                'periode' => $row->Periode ?? null,
                'jadwal_nama' => $row->Jadwal_Nama ?? null,
                'total_score' => isset($row->Total_Score) ? (float) $row->Total_Score : null,
                'status' => $derived['status'],
                'status_label' => $derived['label'],
                'is_approved' => $derived['status'] === 'approved',
                'fingerprint' => $this->fingerprint($row),
                'cascade_performance' => $cascade['performance'],
                'cascade_draft' => $cascade['draft'],
            ];
        }

        $active = collect($items)->where('blocked', false);

        return [
            'domain' => $domain,
            'items' => $items,
            'summary' => [
                'total' => $active->count(),
                'blocked' => count($items) - $active->count(),
                'approved' => $active->where('is_approved', true)->count(),
                'cascade_performance' => $active->sum(fn ($i) => count($i['cascade_performance'])),
                'cascade_draft' => $active->sum(fn ($i) => count($i['cascade_draft'])),
                'max_items' => self::MAX_ITEMS,
            ],
        ];
    }

    // =====================================================================
    // EXECUTE — eksekusi pembatalan
    // =====================================================================

    /**
     * Batalkan transaksi. Seluruhnya dalam satu DB transaction: kalau ada yang
     * gagal, tidak ada yang terbatalkan (all-or-nothing) -- lebih aman daripada
     * batal separuh dan admin tak tahu mana yang jadi.
     *
     * @param  array<int>  $ids
     * @param  array<int,string>  $fingerprints  id => fingerprint saat preview
     * @return array{ok:bool,conflicts?:array,result?:array}
     */
    public function execute(string $domain, array $ids, string $reason, array $fingerprints, ?string $adminKode): array
    {
        $ids = collect($ids)->map(fn ($v) => (int) $v)->filter()->unique()->values();

        $headers = $this->fetchHeaders($domain, $ids->all());

        // --- Validasi ulang di server: state harus persis seperti saat preview.
        // Ini pengaman utama "jangan sampai salah batalkan": bila ada approver yang
        // menyetujui/menolak (atau admin lain membatalkan) di antara preview dan
        // eksekusi, fingerprint berubah -> tolak SELURUH aksi, jangan batalkan apa pun.
        // Untuk draft, yang dijaga adalah karyawan yang menyimpan/mengirim drafnya.
        $conflicts = [];
        foreach ($ids as $id) {
            $row = $headers->get($id);
            if (! $row) {
                $conflicts[] = ['id' => $id, 'reason' => 'Transaksi tidak ditemukan.'];

                continue;
            }
            $blockReason = $this->blockReason($row, $domain);
            if ($blockReason !== null) {
                $conflicts[] = [
                    'id' => $id,
                    'no_transaksi' => $row->No_Transaksi,
                    'nama' => $row->Nama,
                    'reason' => $blockReason,
                ];

                continue;
            }
            $now = $this->fingerprint($row);
            if (($fingerprints[$id] ?? null) !== $now) {
                $conflicts[] = [
                    'id' => $id,
                    'no_transaksi' => $row->No_Transaksi,
                    'nama' => $row->Nama,
                    'reason' => $domain === 'draft'
                        ? 'Draft berubah sejak Anda membuka preview (kemungkinan baru disimpan atau dikirim karyawan).'
                        : 'Status transaksi berubah sejak Anda membuka preview (kemungkinan baru disetujui/ditolak approver).',
                ];
            }
        }

        if ($conflicts) {
            return ['ok' => false, 'conflicts' => $conflicts];
        }

        $adminKode = $adminKode ?: 'UNKNOWN';
        $adminNama = DB::table('Karyawan')->where('Kode_Karyawan', $adminKode)->value('Nama');
        $batchId = 'CNL-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        // --- Rakit SELURUH daftar sasaran DULU (di luar transaksi), lalu tulis
        // set-based. Menulis baris-per-baris akan menahan ribuan row lock selama
        // detikan dan berisiko memicu lock escalation -> tabel terkunci & proses lain
        // timeout. Dengan cara ini transaksi hanya berisi ~4 statement, berapa pun
        // jumlah itemnya, sehingga lock lepas dalam hitungan milidetik.
        $paramIds = [];
        $perfIds = [];
        $draftIds = [];
        $noTransaksis = [];

        if ($domain === 'parameter') {
            foreach ($ids as $id) {
                $paramIds[] = (int) $id;
                $noTransaksis[] = $headers->get($id)->No_Transaksi;
            }

            // Cascade untuk SEMUA pengajuan sekaligus (dua query, bukan dua per item).
            // Batch yang sama dengan induknya = penanda "ikut batal", tanpa kolom khusus.
            $perf = DB::table('KPI_Update_Performance')
                ->whereIn('Id_Pengajuan', $paramIds)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Status')
                ->get(['Id_Update_Performance', 'No_Transaksi']);
            foreach ($perf as $p) {
                $perfIds[] = (int) $p->Id_Update_Performance;
                $noTransaksis[] = $p->No_Transaksi;
            }

            // SEMUA draft milik pengajuan ini ikut mati -- termasuk yang sudah
            // dipromosikan jadi record final (Flag_Pindah='Y'), karena record final
            // hasil promosinya pun ikut dibatalkan di atas. Menyisakan draft aktif yang
            // menggantung ke parameter yang sudah tidak ada = residu yang membingungkan.
            $draftIds = DB::table('KPI_Update_Performance_Sementara')
                ->whereIn('Id_Pengajuan', $paramIds)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_Update_Performance_Sementara')
                ->map(fn ($v) => (int) $v)
                ->all();
        } elseif ($domain === 'draft') {
            // Draft belum punya No_Transaksi final maupun langkah approval,
            // jadi cukup header drafnya yang dinonaktifkan.
            foreach ($ids as $id) {
                $draftIds[] = (int) $id;
            }
        } else {
            foreach ($ids as $id) {
                $perfIds[] = (int) $id;
                $noTransaksis[] = $headers->get($id)->No_Transaksi;
            }
        }

        $paramIds = array_values(array_unique($paramIds));
        $perfIds = array_values(array_unique($perfIds));
        $draftIds = array_values(array_unique($draftIds));
        $noTransaksis = array_values(array_filter(array_unique($noTransaksis)));

        $audit = [
            'Cancel_Reason' => $reason,
            'Cancelled_By' => $adminKode,
            'Cancelled_By_Nama' => $adminNama,
            'Cancelled_At' => now(),
            'Cancel_Batch_Id' => $batchId,
        ];

        try {
            DB::transaction(function () use ($domain, $paramIds, $perfIds, $draftIds, $noTransaksis, $audit) {
                // Header dibatalkan: Flag_Aktif='T' + Status='Y' -> otomatis lenyap dari
                // monitoring & seluruh inbox approver.
                $this->bulkUpdate('KPI_Pengajuan', 'Id_Pengajuan', $paramIds, $audit + ['Flag_Aktif' => 'T', 'Status' => 'Y']);
                $this->bulkUpdate('KPI_Update_Performance', 'Id_Update_Performance', $perfIds, $audit + ['Flag_Aktif' => 'T', 'Status' => 'Y']);

                if ($domain === 'draft') {
                    // Pembatalan draft langsung bersaing dengan karyawan yang sedang
                    // mengirim drafnya. Finalisasi mengunci header draft, jadi UPDATE
                    // bersyarat ini menunggu lalu hanya mengenai draft yang MASIH
                    // hidup. Kurang satu saja -> gagalkan seluruh aksi.
                    $kena = 0;
                    foreach (array_chunk($draftIds, self::CHUNK) as $chunk) {
                        $kena += DB::table('KPI_Update_Performance_Sementara')
                            ->whereIn('Id_Update_Performance_Sementara', $chunk)
                            ->where('Flag_Aktif', 'Y')
                            ->where(fn ($q) => $q->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y'))
                            ->update($audit + ['Flag_Aktif' => 'T']);
                    }
                    if ($kena !== count($draftIds)) {
                        throw new \RuntimeException('draft-berubah');
                    }
                } else {
                    // Draft tak punya kolom Status/No_Transaksi -- cukup dinonaktifkan agar
                    // tidak bisa difinalisasi di atas parameter yang sudah batal.
                    $this->bulkUpdate('KPI_Update_Performance_Sementara', 'Id_Update_Performance_Sementara', $draftIds, $audit + ['Flag_Aktif' => 'T']);
                }

                // Void langkah approval yang masih menggantung di engine TERPUSAT.
                // Engine LEGACY (KPI_Approval_Request_*) sengaja tidak disentuh: header
                // sudah jadi sumber kebenaran di ketiga inbox approver.
                foreach (array_chunk($noTransaksis, self::CHUNK) as $chunk) {
                    DB::table('HRIS_Approval_Request')
                        ->whereIn('No_Transaksi', $chunk)
                        ->whereNull('Flag_Approval')
                        ->update(['Status' => 'Y']);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'draft-berubah') {
                throw $e;
            }

            return ['ok' => false, 'conflicts' => [[
                'id' => null,
                'reason' => 'Ada draft yang baru saja dikirim atau dibatalkan pihak lain. Tidak ada yang dibatalkan; muat ulang preview.',
            ]]];
        }

        $counter = [
            'parameter' => count($paramIds),
            'performance' => count($perfIds),
            'draft' => count($draftIds),
        ];

        Log::info('KPI transaksi dibatalkan', [
            'batch_id' => $batchId,
            'domain' => $domain,
            'ids' => $ids->all(),
            'by' => $adminKode,
            'counter' => $counter,
        ]);

        return [
            'ok' => true,
            'result' => [
                'batch_id' => $batchId,
                'cancelled' => $counter,
                'total' => array_sum($counter),
            ],
        ];
    }

    /**
     * Satu UPDATE per potongan id (bukan per baris). Dipecah per CHUNK karena SQL
     * Server membatasi jumlah parameter per statement (2100).
     *
     * @param  array<int>  $ids
     */
    private function bulkUpdate(string $table, string $pk, array $ids, array $values): void
    {
        foreach (array_chunk($ids, self::CHUNK) as $chunk) {
            DB::table($table)->whereIn($pk, $chunk)->update($values);
        }
    }

    // =====================================================================
    // Cascade & jejak
    // =====================================================================

    /**
     * Turunan sebuah pengajuan parameter: update performance final (lintas SEMUA
     * periode) + draft yang belum difinalisasi.
     */
    public function cascadeFor(int $idPengajuan): array
    {
        $perf = DB::table('KPI_Update_Performance as up')
            ->leftJoin('KPI_Master_Penjadwalan as j', 'up.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('up.Id_Pengajuan', $idPengajuan)
            ->where('up.Flag_Aktif', 'Y')
            ->whereNull('up.Status')
            ->orderBy('up.Periode')
            ->get([
                'up.Id_Update_Performance',
                'up.No_Transaksi',
                'up.Periode',
                'up.Total_Score',
                'up.Flag_Approval',
                'up.Status_Pengajuan',
                'j.Nama_Kegiatan as Jadwal_Nama',
            ])
            // Status turunan dibaca dari kolom header (Flag_Approval/Status_Pengajuan),
            // BUKAN deriveApprovalStatus() -- satu pengajuan bisa punya belasan
            // performance, dan menelusuri alur approval tiap baris akan meledak jadi
            // ratusan query hanya untuk menampilkan preview.
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Update_Performance,
                'no_transaksi' => $r->No_Transaksi,
                'periode' => $r->Periode,
                'jadwal_nama' => $r->Jadwal_Nama,
                'total_score' => (float) $r->Total_Score,
                'status' => $this->headerStatus($r),
                'status_label' => $this->headerStatusLabel($r),
                'is_approved' => $this->headerStatus($r) === 'approved',
            ])
            ->values()
            ->all();

        // Semua draft ikut batal, termasuk yang sudah dipromosikan (Flag_Pindah='Y'),
        // agar tidak ada draft aktif yang menggantung ke parameter yang sudah batal.
        $draft = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $idPengajuan)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Periode')
            ->get(['Id_Update_Performance_Sementara', 'Id_Penjadwalan', 'Periode', 'Total_Score', 'Flag_Pindah'])
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Update_Performance_Sementara,
                'id_penjadwalan' => $r->Id_Penjadwalan ? (int) $r->Id_Penjadwalan : null,
                'periode' => $r->Periode,
                'total_score' => $r->Total_Score !== null ? (float) $r->Total_Score : null,
                'is_promoted' => ($r->Flag_Pindah ?? null) === 'Y',
            ])
            ->values()
            ->all();

        return ['performance' => $perf, 'draft' => $draft];
    }

    /**
     * Jejak pembatalan per karyawan dalam scope penjadwalan -- ditempelkan ke baris
     * monitoring yang kini berstatus "Belum Submit" agar admin paham kenapa kosong.
     * Satu query batch (bebas N+1).
     *
     * PENTING: Status='Y' saja TIDAK cukup sebagai penanda "dibatalkan admin".
     * FormKpiController juga menulis Status='Y' saat user MENGAJUKAN ULANG (pengajuan
     * lama digantikan) -- baris itu bukan pembatalan dan tidak punya Cancelled_At.
     * Jejak hanya diambil dari baris yang benar-benar dibatalkan lewat fitur ini.
     *
     * @param  array<int>  $scheduleIds
     * @return Collection<int,array> keyed by Id_User
     */
    public function cancellationTraceFor(array $scheduleIds, string $domain): Collection
    {
        if (! $scheduleIds) {
            return collect();
        }

        $table = $domain === 'parameter' ? 'KPI_Pengajuan' : 'KPI_Update_Performance';
        $pk = $domain === 'parameter' ? 'Id_Pengajuan' : 'Id_Update_Performance';

        return DB::table($table)
            ->whereIn('Id_Penjadwalan', $scheduleIds)
            ->where('Status', 'Y')
            ->whereNotNull('Cancelled_At')
            ->orderByDesc('Cancelled_At')
            ->get([
                $pk.' as Id',
                'Id_User',
                'No_Transaksi',
                'Cancel_Reason',
                'Cancelled_By_Nama',
                'Cancelled_By',
                'Cancelled_At',
                'Cancel_Batch_Id',
            ])
            ->groupBy('Id_User')
            ->map(function ($group) use ($domain) {
                $last = $group->sortByDesc(fn ($r) => $r->Cancelled_At ?? '')->first();

                return [
                    'count' => $group->count(),
                    'last' => [
                        'id' => (int) $last->Id,
                        'domain' => $domain,
                        'no_transaksi' => $last->No_Transaksi,
                        'alasan' => $last->Cancel_Reason,
                        'oleh' => $last->Cancelled_By_Nama ?: $last->Cancelled_By,
                        'pada' => $last->Cancelled_At,
                        'batch_id' => $last->Cancel_Batch_Id,
                    ],
                ];
            });
    }

    /**
     * Blok "cancellation" untuk header detail transaksi (banner jejak pembatalan).
     */
    public function cancellationMetaFor($header, string $domain): ?array
    {
        // Status='Y' tanpa Cancelled_At = pengajuan lama yang digantikan user saat
        // mengajukan ulang (FormKpiController), bukan pembatalan admin -> tak ada jejak.
        if (($header->Status ?? null) !== 'Y' || ! ($header->Cancelled_At ?? null)) {
            return null;
        }

        $batchId = $header->Cancel_Batch_Id ?? null;
        $cascadeCount = null;
        $source = 'manual';

        if ($domain === 'parameter' && isset($header->Id_Pengajuan)) {
            // Turunan yang ikut batal = performance dgn induk ini & batch yang sama.
            $cascadeCount = (int) DB::table('KPI_Update_Performance')
                ->where('Id_Pengajuan', $header->Id_Pengajuan)
                ->where('Cancel_Batch_Id', $batchId)
                ->count();
        } elseif ($domain === 'performance' && ($header->Id_Pengajuan ?? null)) {
            // Batch sama dengan pengajuan induknya -> performance ini ikut batal.
            $parentBatch = DB::table('KPI_Pengajuan')
                ->where('Id_Pengajuan', $header->Id_Pengajuan)
                ->value('Cancel_Batch_Id');
            $source = $parentBatch && $parentBatch === $batchId ? 'cascade' : 'manual';
        }

        return [
            'alasan' => $header->Cancel_Reason ?? null,
            'oleh' => $header->Cancelled_By_Nama ?: ($header->Cancelled_By ?? null),
            'pada' => $header->Cancelled_At ?? null,
            'batch_id' => $batchId,
            'source' => $source,
            'cascade_count' => $cascadeCount,
        ];
    }

    // =====================================================================
    // Riwayat pembatalan (sub-tab)
    // =====================================================================

    /**
     * Riwayat pembatalan dirakit langsung dari kolom audit di ketiga tabel header
     * (UNION), bukan dari tabel log terpisah -- tidak ada duplikasi data dan tidak
     * mungkin melenceng dari kenyataan.
     *
     * "Status sebelum dibatalkan" tetap terbaca karena Flag_Approval /
     * Status_Pengajuan tidak pernah ditimpa saat membatalkan; hanya Flag_Aktif &
     * Status yang berubah.
     */
    public function getHistory(array $f): array
    {
        $page = max(1, (int) ($f['page'] ?? 1));
        $perPage = min(100, max(5, (int) ($f['per_page'] ?? 15)));

        $union = $this->historyParameterQuery()
            ->unionAll($this->historyPerformanceQuery())
            ->unionAll($this->historyDraftQuery());

        $q = DB::query()
            ->fromSub($union, 'h')
            ->when(! empty($f['domain']), fn ($q) => $q->whereIn('h.Domain', (array) $f['domain']))
            ->when(! empty($f['source']), fn ($q) => $q->whereIn('h.Source', (array) $f['source']))
            ->when(! empty($f['batch_id']), fn ($q) => $q->where('h.Batch_Id', $f['batch_id']))
            ->when(! empty($f['dari']), fn ($q) => $q->whereDate('h.Cancelled_At', '>=', $f['dari']))
            ->when(! empty($f['sampai']), fn ($q) => $q->whereDate('h.Cancelled_At', '<=', $f['sampai']))
            ->when(! empty($f['search']), function ($q) use ($f) {
                $like = '%'.$f['search'].'%';
                $q->where(function ($w) use ($like) {
                    $w->where('h.Nama_Karyawan', 'like', $like)
                        ->orWhere('h.Kode_Karyawan', 'like', $like)
                        ->orWhere('h.No_Transaksi', 'like', $like)
                        ->orWhere('h.Batch_Id', 'like', $like);
                });
            });

        $total = (clone $q)->count();

        // Dalam satu batch, timestamp-nya identik -> tanpa tie-breaker, baris induk
        // bisa nyempil di tengah turunannya. Urutkan: aksi terbaru dulu, lalu induk
        // (parameter) di atas turunannya (performance, draft).
        $rows = $q
            ->orderByDesc('h.Cancelled_At')
            ->orderByDesc('h.Batch_Id')
            ->orderByRaw("CASE h.Domain WHEN 'parameter' THEN 0 WHEN 'performance' THEN 1 ELSE 2 END")
            ->orderBy('h.Periode')
            ->orderBy('h.Id_Transaksi')
            ->forPage($page, $perPage)
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Transaksi,
                'batch_id' => $r->Batch_Id,
                'domain' => $r->Domain,
                'source' => $r->Source,
                'id_transaksi' => (int) $r->Id_Transaksi,
                'no_transaksi' => $r->No_Transaksi,
                'parent_id_pengajuan' => $r->Parent_Id ? (int) $r->Parent_Id : null,
                'kode_karyawan' => $r->Kode_Karyawan,
                'nama' => $r->Nama_Karyawan,
                'periode' => $r->Periode,
                'status_sebelum' => $r->Status_Sebelum,
                'total_score' => $r->Total_Score !== null ? (float) $r->Total_Score : null,
                'alasan' => $r->Alasan,
                'oleh' => $r->Cancelled_By_Nama ?: $r->Cancelled_By,
                'pada' => $r->Cancelled_At,
            ])
            ->values()
            ->all();

        return ['rows' => $rows, 'page' => $page, 'per_page' => $perPage, 'total' => $total];
    }

    /**
     * Semua sub-query riwayat WAJIB menghasilkan kolom yang sama persis (nama, urutan,
     * tipe) agar UNION ALL valid di SQL Server.
     */
    private function historyParameterQuery()
    {
        return DB::table('KPI_Pengajuan as p')
            ->join('Karyawan as k', 'p.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->whereNotNull('p.Cancelled_At')
            ->selectRaw("
                'parameter'                     as Domain,
                'manual'                        as Source,
                p.Id_Pengajuan                  as Id_Transaksi,
                p.No_Transaksi                  as No_Transaksi,
                CAST(NULL AS INT)               as Parent_Id,
                k.Kode_Karyawan                 as Kode_Karyawan,
                k.Nama                          as Nama_Karyawan,
                CAST(j.Tahun AS NVARCHAR(10))   as Periode,
                CASE
                    WHEN p.Flag_Approval = 'Y' THEN 'approved'
                    WHEN p.Flag_Approval = 'T' THEN 'rejected'
                    ELSE 'waiting'
                END                             as Status_Sebelum,
                CAST(NULL AS DECIMAL(18,4))     as Total_Score,
                p.Cancel_Reason                 as Alasan,
                p.Cancel_Batch_Id               as Batch_Id,
                p.Cancelled_By                  as Cancelled_By,
                p.Cancelled_By_Nama             as Cancelled_By_Nama,
                p.Cancelled_At                  as Cancelled_At
            ");
    }

    private function historyPerformanceQuery()
    {
        // Cascade diturunkan, bukan disimpan: batch performance == batch pengajuan
        // induknya berarti keduanya batal dalam satu aksi.
        return DB::table('KPI_Update_Performance as up')
            ->join('Karyawan as k', 'up.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('KPI_Pengajuan as p', 'up.Id_Pengajuan', '=', 'p.Id_Pengajuan')
            ->whereNotNull('up.Cancelled_At')
            ->selectRaw("
                'performance'                       as Domain,
                CASE
                    WHEN p.Cancel_Batch_Id IS NOT NULL
                     AND p.Cancel_Batch_Id = up.Cancel_Batch_Id THEN 'cascade'
                    ELSE 'manual'
                END                                 as Source,
                up.Id_Update_Performance            as Id_Transaksi,
                up.No_Transaksi                     as No_Transaksi,
                up.Id_Pengajuan                     as Parent_Id,
                k.Kode_Karyawan                     as Kode_Karyawan,
                k.Nama                              as Nama_Karyawan,
                CAST(up.Periode AS NVARCHAR(10))    as Periode,
                CASE
                    WHEN up.Flag_Approval = 'Y' OR up.Status_Pengajuan = 'disetujui' THEN 'approved'
                    WHEN up.Flag_Approval = 'T' OR up.Status_Pengajuan = 'ditolak'   THEN 'rejected'
                    ELSE 'waiting'
                END                                 as Status_Sebelum,
                CAST(up.Total_Score AS DECIMAL(18,4)) as Total_Score,
                up.Cancel_Reason                    as Alasan,
                up.Cancel_Batch_Id                  as Batch_Id,
                up.Cancelled_By                     as Cancelled_By,
                up.Cancelled_By_Nama                as Cancelled_By_Nama,
                up.Cancelled_At                     as Cancelled_At
            ");
    }

    private function historyDraftQuery()
    {
        // Dulu draft hanya bisa batal lewat cascade pengajuan induknya; kini admin
        // juga bisa membatalkannya langsung. Sumbernya diturunkan seperti
        // performance: batch sama dengan induknya berarti ikut batal.
        return DB::table('KPI_Update_Performance_Sementara as s')
            ->join('Karyawan as k', 's.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('KPI_Pengajuan as p', 's.Id_Pengajuan', '=', 'p.Id_Pengajuan')
            ->whereNotNull('s.Cancelled_At')
            ->selectRaw("
                'draft'                                 as Domain,
                CASE
                    WHEN p.Cancel_Batch_Id IS NOT NULL
                     AND p.Cancel_Batch_Id = s.Cancel_Batch_Id THEN 'cascade'
                    ELSE 'manual'
                END                                     as Source,
                s.Id_Update_Performance_Sementara       as Id_Transaksi,
                CAST(NULL AS NVARCHAR(100))             as No_Transaksi,
                s.Id_Pengajuan                          as Parent_Id,
                k.Kode_Karyawan                         as Kode_Karyawan,
                k.Nama                                  as Nama_Karyawan,
                CAST(s.Periode AS NVARCHAR(10))         as Periode,
                'draft'                                 as Status_Sebelum,
                CAST(s.Total_Score AS DECIMAL(18,4))    as Total_Score,
                s.Cancel_Reason                         as Alasan,
                s.Cancel_Batch_Id                       as Batch_Id,
                s.Cancelled_By                          as Cancelled_By,
                s.Cancelled_By_Nama                     as Cancelled_By_Nama,
                s.Cancelled_At                          as Cancelled_At
            ");
    }

    // =====================================================================
    // Query header + util
    // =====================================================================

    /** @param array<int> $ids */
    private function fetchHeaders(string $domain, array $ids): Collection
    {
        return match ($domain) {
            'parameter' => $this->fetchParameterHeaders($ids),
            'draft' => $this->fetchDraftHeaders($ids),
            default => $this->fetchPerformanceHeaders($ids),
        };
    }

    /**
     * Header draft dibentuk semirip mungkin dengan header final supaya preview,
     * blockedItem, dan modal FE bisa dipakai ulang. Draft tak punya
     * No_Transaksi final; nomor sementaranya ditampilkan sebagai gantinya.
     *
     * @param  array<int>  $ids
     */
    private function fetchDraftHeaders(array $ids): Collection
    {
        if (! $ids) {
            return collect();
        }

        return DB::table('KPI_Update_Performance_Sementara as s')
            ->join('Karyawan as k', 's.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->leftJoin('KPI_Master_Penjadwalan as j', 's.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->whereIn('s.Id_Update_Performance_Sementara', $ids)
            ->get([
                's.Id_Update_Performance_Sementara',
                's.Id_Pengajuan',
                's.No_Transaksi_Sementara as No_Transaksi',
                's.Id_User',
                's.Id_Penjadwalan',
                's.Periode',
                's.Total_Score',
                's.Flag_Aktif',
                's.Flag_Pindah',
                's.Tanggal_Terakhir',
                's.Jam_Terakhir',
                's.Cancelled_At',
                's.Cancelled_By_Nama',
                'k.Kode_Karyawan',
                'k.Nama',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_jabatan as Jabatan',
                'j.Nama_Kegiatan as Jadwal_Nama',
            ])
            ->keyBy(fn ($r) => (int) $r->Id_Update_Performance_Sementara);
    }

    /**
     * Alasan sebuah header TIDAK boleh dibatalkan, atau null bila boleh.
     * Satu pemilik untuk preview dan eksekusi.
     */
    private function blockReason($row, string $domain): ?string
    {
        if ($domain === 'draft') {
            if (($row->Flag_Aktif ?? 'Y') !== 'Y') {
                $when = $row->Cancelled_At ?? null;
                $by = $row->Cancelled_By_Nama ?? null;
                $suffix = $when ? ' pada '.Carbon::parse($when)->translatedFormat('d M Y H:i') : '';
                $suffix .= $by ? ' oleh '.$by : '';

                return 'Draft sudah dibatalkan'.$suffix.'.';
            }

            if (($row->Flag_Pindah ?? null) === 'Y') {
                return 'Draft sudah dikirim menjadi transaksi Update Performance; batalkan transaksinya, bukan drafnya.';
            }

            return null;
        }

        if (($row->Status ?? null) === 'Y' || ($row->Flag_Aktif ?? 'Y') !== 'Y') {
            return $this->cancelledBlockReason($row);
        }

        return null;
    }

    /** @param array<int> $ids */
    private function fetchParameterHeaders(array $ids): Collection
    {
        if (! $ids) {
            return collect();
        }

        return DB::table('KPI_Pengajuan as p')
            ->join('Karyawan as k', 'p.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->leftJoin('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->whereIn('p.Id_Pengajuan', $ids)
            ->get([
                'p.Id_Pengajuan',
                'p.No_Transaksi',
                'p.Id_User',
                'p.Id_Penjadwalan',
                'p.Flag_Aktif',
                'p.Status',
                'p.Flag_Approval',
                'p.Cancelled_At',
                'p.Cancelled_By_Nama',
                'k.Kode_Karyawan',
                'k.Nama',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_jabatan as Jabatan',
                'j.Nama_Kegiatan as Jadwal_Nama',
                'j.Tahun',
            ])
            ->keyBy(fn ($r) => (int) $r->Id_Pengajuan);
    }

    /** @param array<int> $ids */
    private function fetchPerformanceHeaders(array $ids): Collection
    {
        if (! $ids) {
            return collect();
        }

        return DB::table('KPI_Update_Performance as up')
            ->join('Karyawan as k', 'up.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->leftJoin('KPI_Master_Penjadwalan as j', 'up.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->whereIn('up.Id_Update_Performance', $ids)
            ->get([
                'up.Id_Update_Performance',
                'up.Id_Pengajuan',
                'up.No_Transaksi',
                'up.Id_User',
                'up.Id_Penjadwalan',
                'up.Periode',
                'up.Total_Score',
                'up.Flag_Aktif',
                'up.Status',
                'up.Flag_Approval',
                'up.Status_Pengajuan',
                'up.Cancelled_At',
                'up.Cancelled_By_Nama',
                'k.Kode_Karyawan',
                'k.Nama',
                'vd.nama_divisi as Divisi',
                'vd.nama_sub_divisi as Departemen',
                'vg.nama_jabatan as Jabatan',
                'j.Nama_Kegiatan as Jadwal_Nama',
            ])
            ->keyBy(fn ($r) => (int) $r->Id_Update_Performance);
    }

    /**
     * Status ringkas dari kolom header saja (tanpa menyentuh tabel approval).
     * Dipakai untuk baris cascade yang jumlahnya bisa banyak.
     */
    private function headerStatus($row): string
    {
        if (($row->Flag_Approval ?? null) === 'Y' || ($row->Status_Pengajuan ?? null) === 'disetujui') {
            return 'approved';
        }
        if (($row->Flag_Approval ?? null) === 'T' || ($row->Status_Pengajuan ?? null) === 'ditolak') {
            return 'rejected';
        }

        return 'waiting';
    }

    private function headerStatusLabel($row): string
    {
        return [
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'waiting' => 'Menunggu Approval',
        ][$this->headerStatus($row)];
    }

    /**
     * Sidik jari state transaksi. Berubah bila ada approver yang bertindak atau
     * transaksi dibatalkan pihak lain -> eksekusi ditolak (409).
     */
    private function fingerprint($row): string
    {
        // Draft: berubah setiap kali karyawan menyimpan (Tanggal/Jam_Terakhir)
        // atau mengirim (Flag_Pindah) drafnya.
        if (isset($row->Id_Update_Performance_Sementara)) {
            return sha1(implode('|', [
                'draft',
                $row->Id_Update_Performance_Sementara,
                $row->Flag_Aktif ?? '',
                $row->Flag_Pindah ?? '',
                $row->Tanggal_Terakhir ?? '',
                $row->Jam_Terakhir ?? '',
            ]));
        }

        $id = $row->Id_Pengajuan ?? $row->Id_Update_Performance ?? '';

        return sha1(implode('|', [
            $id,
            $row->Flag_Aktif ?? '',
            $row->Status ?? '',
            $row->Flag_Approval ?? '',
            $row->No_Transaksi ?? '',
        ]));
    }

    private function blockedItem(int $id, string $reason, $row = null, ?string $domain = null): array
    {
        return [
            'id' => $id,
            'domain' => $domain,
            'blocked' => true,
            'block_reason' => $reason,
            'no_transaksi' => $row->No_Transaksi ?? null,
            'id_user' => isset($row->Id_User) ? (int) $row->Id_User : null,
            'kode_karyawan' => $row->Kode_Karyawan ?? null,
            'nama' => $row->Nama ?? '-',
            'divisi' => $row->Divisi ?? null,
            'departemen' => $row->Departemen ?? null,
            'jabatan' => $row->Jabatan ?? null,
            'periode' => $row->Periode ?? null,
            'jadwal_nama' => $row->Jadwal_Nama ?? null,
            'total_score' => null,
            'status' => 'cancelled',
            'status_label' => 'Dibatalkan',
            'is_approved' => false,
            'fingerprint' => null,
            'cascade_performance' => [],
            'cascade_draft' => [],
        ];
    }

    private function cancelledBlockReason($row): string
    {
        if (($row->Status ?? null) === 'Y') {
            $when = $row->Cancelled_At ?? null;
            $by = $row->Cancelled_By_Nama ?? null;
            $suffix = $when ? ' pada '.Carbon::parse($when)->translatedFormat('d M Y H:i') : '';
            $suffix .= $by ? ' oleh '.$by : '';

            return 'Transaksi sudah dibatalkan'.$suffix.'.';
        }

        return 'Transaksi sudah tidak aktif (kemungkinan sudah digantikan pengajuan baru).';
    }

    // =====================================================================
    // Status approval (dipakai bersama KpiEnforcementController)
    // =====================================================================

    /**
     * Turunkan status approval dari header + alur approval (CENTRAL atau LEGACY).
     *   Approved : header Flag_Approval='Y' atau semua step approved
     *   Rejected : ada step ditolak
     *   Waiting  : sudah diajukan tapi belum selesai
     *
     * TIDAK ada status "Draft" di sini -- pengajuan/performance final selalu dibuat
     * bersama alur approval dalam satu transaksi DB, jadi flow kosong = anomali yang
     * diperlakukan sebagai "Menunggu Approval". Draft asli hanya untuk Update
     * Performance yang belum difinalisasi (tabel _Sementara) dan ditangani terpisah.
     */
    public function deriveApprovalStatus($row, string $domain = 'parameter'): array
    {
        if (($row->Flag_Approval ?? null) === 'Y') {
            return ['status' => 'approved', 'label' => 'Disetujui', 'stage' => null];
        }

        if (! ($row->No_Transaksi ?? null)) {
            return ['status' => 'waiting', 'label' => 'Menunggu Approval', 'stage' => null];
        }

        if ($this->kpiApproval->isCentral($row->No_Transaksi)) {
            $steps = DB::table('N_HRIS_VW_Approval_Request_V2')
                ->where('No_Transaksi', $row->No_Transaksi)
                ->orderBy('Request_Order_Flow', 'asc')
                ->get(['Request_Order_Flow', 'Flag_Approval'])
                ->map(fn ($s) => $this->normalizeCentralStep($s));
        } else {
            $requestTable = $domain === 'performance' ? 'KPI_Approval_Request_Performance' : 'KPI_Approval_Request_Parameter';
            $flowTable = $domain === 'performance' ? 'KPI_Approval_Flow_Performance' : 'KPI_Approval_Flow_Parameter';
            $steps = DB::table($requestTable.' as r')
                ->join($flowTable.' as f', 'r.Flow_Id', '=', 'f.Id_Approval_Flow')
                ->where('r.No_Transaksi', $row->No_Transaksi)
                ->orderByRaw('TRY_CAST(f.order_flow AS INT) ASC')
                ->get(['f.order_flow', 'r.Flag_Approval as Step_Approval'])
                ->map(fn ($s) => $this->normalizeLegacyStep($s));
        }

        return $this->evaluateSteps($steps);
    }

    /**
     * Versi BATCH deriveApprovalStatus: menurunkan status untuk BANYAK baris dalam
     * jumlah query KONSTAN (bukan ~3 query/baris seperti versi tunggal).
     *
     * Dipakai pipeline monitoring yang memetakan puluhan/ratusan baris — tanpa ini,
     * satu halaman (atau "centang semua") memicu N+1 yang membengkak jadi ratusan
     * query & belasan detik saat banyak transaksi berstatus menunggu.
     *
     * @param  iterable  $rows  tiap elemen punya ->No_Transaksi & ->Flag_Approval
     * @return array<string,array{status:string,label:string,stage:?string}> keyed by No_Transaksi
     */
    public function deriveApprovalStatusMap(iterable $rows, string $domain = 'parameter'): array
    {
        // Hanya baris yang perlu ditelusuri: sudah diajukan (punya No_Transaksi) dan
        // belum final approved (yang approved di-short-circuit oleh pemanggil).
        $noList = [];
        foreach ($rows as $r) {
            $no = $r->No_Transaksi ?? null;
            if ($no && ($r->Flag_Approval ?? null) !== 'Y') {
                $noList[$no] = true;
            }
        }
        $noList = array_keys($noList);
        if (! $noList) {
            return [];
        }

        // (1 query) Mana yang pakai engine CENTRAL — sekali untuk semua, bukan per baris.
        $jenis = $this->kpiApproval->kpiJenisIds();
        $centralSet = [];
        foreach (array_chunk($noList, self::CHUNK) as $chunk) {
            DB::table('HRIS_Approval_Request')
                ->whereIn('No_Transaksi', $chunk)
                ->when($jenis, fn ($q) => $q->whereIn('Id_Jenis_Approval', $jenis))
                ->distinct()
                ->pluck('No_Transaksi')
                ->each(function ($no) use (&$centralSet) {
                    $centralSet[$no] = true;
                });
        }

        $centralNos = array_values(array_filter($noList, fn ($no) => isset($centralSet[$no])));
        $legacyNos = array_values(array_filter($noList, fn ($no) => ! isset($centralSet[$no])));

        // (1 query) Semua langkah CENTRAL sekaligus, dikelompokkan per No_Transaksi.
        $stepsByNo = [];
        foreach (array_chunk($centralNos, self::CHUNK) as $chunk) {
            DB::table('N_HRIS_VW_Approval_Request_V2')
                ->whereIn('No_Transaksi', $chunk)
                ->orderBy('Request_Order_Flow', 'asc')
                ->get(['No_Transaksi', 'Request_Order_Flow', 'Flag_Approval'])
                ->groupBy('No_Transaksi')
                ->each(function ($group, $no) use (&$stepsByNo) {
                    $stepsByNo[$no] = $group->map(fn ($s) => $this->normalizeCentralStep($s));
                });
        }

        // (1 query) Semua langkah LEGACY sekaligus.
        if ($legacyNos) {
            $requestTable = $domain === 'performance' ? 'KPI_Approval_Request_Performance' : 'KPI_Approval_Request_Parameter';
            $flowTable = $domain === 'performance' ? 'KPI_Approval_Flow_Performance' : 'KPI_Approval_Flow_Parameter';
            foreach (array_chunk($legacyNos, self::CHUNK) as $chunk) {
                DB::table($requestTable.' as r')
                    ->join($flowTable.' as f', 'r.Flow_Id', '=', 'f.Id_Approval_Flow')
                    ->whereIn('r.No_Transaksi', $chunk)
                    ->orderByRaw('TRY_CAST(f.order_flow AS INT) ASC')
                    ->get(['r.No_Transaksi', 'f.order_flow', 'r.Flag_Approval as Step_Approval'])
                    ->groupBy('No_Transaksi')
                    ->each(function ($group, $no) use (&$stepsByNo) {
                        $stepsByNo[$no] = $group->map(fn ($s) => $this->normalizeLegacyStep($s));
                    });
            }
        }

        $map = [];
        foreach ($noList as $no) {
            $map[$no] = $this->evaluateSteps($stepsByNo[$no] ?? collect());
        }

        return $map;
    }

    private function normalizeCentralStep($s): object
    {
        return (object) [
            'order' => (int) $s->Request_Order_Flow,
            'approved' => ($s->Flag_Approval ?? null) === 'Y',
            'rejected' => ($s->Flag_Approval ?? null) === 'T',
        ];
    }

    private function normalizeLegacyStep($s): object
    {
        return (object) [
            'order' => (int) $s->order_flow,
            'approved' => ($s->Step_Approval ?? null) === 'Y',
            'rejected' => ($s->Step_Approval ?? null) === 'T',
        ];
    }

    /**
     * Evaluasi langkah approval (sudah dinormalisasi) menjadi status akhir. Dipakai
     * bersama oleh jalur tunggal (deriveApprovalStatus) & batch (deriveApprovalStatusMap).
     */
    private function evaluateSteps($steps): array
    {
        if ($steps->isEmpty()) {
            return ['status' => 'waiting', 'label' => 'Menunggu Approval', 'stage' => null];
        }

        if ($steps->firstWhere('rejected', true)) {
            return ['status' => 'rejected', 'label' => 'Ditolak', 'stage' => null];
        }

        $approvedCount = $steps->where('approved', true)->count();
        $totalSteps = $steps->count();

        if ($approvedCount >= $totalSteps) {
            return ['status' => 'approved', 'label' => 'Disetujui', 'stage' => null];
        }

        $currentStep = $steps->first(fn ($s) => ! $s->approved);
        $currentLevel = $currentStep ? (int) $currentStep->order : $totalSteps;

        return [
            'status' => 'waiting',
            'label' => "Menunggu Approval (Lvl {$currentLevel}/{$totalSteps})",
            'stage' => "Level {$currentLevel} dari {$totalSteps}",
        ];
    }
}
