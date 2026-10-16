<?php

namespace App\Services\KPI;

use App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController;
use App\Jobs\KPI\Export\GenerateKpiExportJob;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * KpiExportService
 *
 * Membangun dataset DETAIL BERLAPIS (header karyawan + atribut + alur approval +
 * rincian parameter) untuk monitoring KPI Parameter (pengajuan) maupun Update
 * Performance, lalu merender ke Excel atau PDF. Dijalankan headless dari job queue.
 *
 * Lifecycle job (progress, list, cleanup) ditangani AbstractExportService;
 * status disimpan di tabel sentral N_HRIS_Export_Jobs (Module='kpi-export').
 * File disimpan di disk GCS (kpi-exports/). Pembersihan via command export:cleanup.
 */
class KpiExportService extends AbstractExportService
{
    /** Ukuran chunk pembangunan dataset (jaga memory rata utk dataset besar). */
    private const BUILD_CHUNK = 50;

    protected function moduleKey(): string
    {
        return 'kpi-export';
    }

    protected function exportIdPrefix(): string
    {
        return 'kpi-exp';
    }

    protected function storageDir(): string
    {
        return 'kpi-exports';
    }

    public function dispatchJob(string $exportId): void
    {
        GenerateKpiExportJob::dispatch($exportId);
    }

    /**
     * Bangun dataset dari payload job: pilih builder parameter / performance.
     */
    protected function buildDataset(object $job): array
    {
        $payload = $this->payload($job);
        $jenis = $payload['jenis'] ?? 'parameter';
        $scopeValue = $payload['scope_value'] ?? null;

        // Dua mode seleksi:
        //  - manual : payload['id_users'] = daftar Id_User eksplisit.
        //  - semua  : payload['select_all'] = true + payload['filters'] = kriteria
        //             (divisi/dept/level/jabatan/lokasi). Worker resolve sendiri
        //             himpunan karyawannya dari scope + filter (bukan dari client).
        $selectAll = (bool) ($payload['select_all'] ?? false);
        $idUsers = $selectAll ? null : array_map('intval', $payload['id_users'] ?? []);
        $filters = $selectAll ? (array) ($payload['filters'] ?? []) : [];

        // Meta penjadwalan dalam scope (di-resolve controller saat dispatch, sama
        // dgn monitoring): schedule_ids + has_global + perorangan_ids + batas_kepegawaian
        // + penanda jadwal berjalan (global_berjalan / perorangan_berjalan_ids).
        // Dataset dibangun dari UNIVERSE karyawan terjadwal (LEFT JOIN transaksi)
        // sehingga yang BELUM/Tidak mengisi pun ikut, diberi status.
        $meta = (array) ($payload['schedule_meta'] ?? []);
        $meta['schedule_ids'] = array_values(array_filter(array_map('intval', $meta['schedule_ids'] ?? ($payload['schedule_ids'] ?? []))));
        $meta['perorangan_ids'] = array_values(array_filter(array_map('intval', $meta['perorangan_ids'] ?? [])));
        $meta['has_global'] = (bool) ($meta['has_global'] ?? false);
        $meta['expired'] = (bool) ($meta['expired'] ?? false);

        if ($jenis !== 'performance') {
            return $this->buildParameterDataset($scopeValue, $meta, $idUsers, $filters, $job->Export_Id);
        }

        // Mode YTD punya bentuk baris yang berbeda dan memang harus begitu: satu
        // akumulasi tidak punya satu No. Transaksi, satu tanggal, atau satu alur
        // persetujuan. Memaksakannya ke dalam bentuk bulanan hanya akan
        // menghasilkan kolom yang isinya karangan.
        if (($payload['mode'] ?? 'bulan') === 'ytd') {
            return $this->buildPerformanceYtdDataset(
                $scopeValue,
                array_values((array) ($payload['periods'] ?? [])),
                $meta,
                $idUsers,
                $filters,
                $job->Export_Id,
            );
        }

        return $this->buildPerformanceDataset($scopeValue, $meta, $idUsers, $filters, $job->Export_Id);
    }

    /**
     * Label "belum/tidak" untuk satu karyawan, dihitung PER ORANG sama seperti
     * layar monitoring. Berkas dan layar tidak boleh berselisih: admin memilih
     * baris dari tabel lalu menerima berkas yang menyebut status berbeda.
     *
     * `$meta['expired']` hanya cadangan untuk job yang sudah terlanjur antre
     * sebelum rilis yang memperkenalkan penanda per orang.
     *
     * @param  array<string,mixed>  $meta
     */
    private function labelBelumAtauTidak(array $meta, ?string $kodeKaryawan, string $belum, string $tidak): string
    {
        $berjalan = KpiJadwalBerjalan::dariMeta($meta);

        if ($berjalan === null) {
            return ! empty($meta['expired']) ? $tidak : $belum;
        }

        return $berjalan->membebani($kodeKaryawan) ? $belum : $tidak;
    }

    /**
     * Batas kepegawaian untuk universe export.
     *
     * Diambil dari meta yang dibuat layar (exportScheduleMeta) supaya berkas dan
     * layar memakai himpunan orang yang sama. `$scopeValue` hanya cadangan untuk
     * job lama yang payload-nya dibuat sebelum kunci 'batas_kepegawaian' ada — tanpa
     * cadangan itu, antrean yang sedang berjalan saat rilis akan menghasilkan
     * berkas dengan universe yang melebar diam-diam ke 1900.
     *
     * @param  string|array<int,string>|null  $scopeValue
     */
    private function batasKepegawaianUniverse(array $meta, string|array|null $scopeValue): Carbon
    {
        $dariMeta = $meta['batas_kepegawaian'] ?? null;

        if (is_string($dariMeta) && $dariMeta !== '') {
            return Carbon::parse($dariMeta)->startOfDay();
        }

        return KpiKepegawaianPeriode::batasDariScope($scopeValue ?? '');
    }

    /**
     * Builder UNIVERSE karyawan terjadwal (tanpa join transaksi), identik dgn
     * KpiEnforcementController::scheduledEmployeesInScope. Global -> semua karyawan
     * aktif PADA PERIODE; perorangan -> hanya yg terdaftar.
     *
     * @param  string|array<int,string>|null  $scopeValue
     */
    private function universeQuery(array $meta, string|array|null $scopeValue = null)
    {
        $q = KpiKepegawaianPeriode::terapkan(
            DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan'),
            $this->batasKepegawaianUniverse($meta, $scopeValue),
        );

        if (empty($meta['has_global'])) {
            $q->whereIn('k.Kode_Karyawan', function ($sub) use ($meta) {
                $sub->from('N_KPI_Master_Penjadwalan_Perorangan')
                    ->whereIn('Id_Master_Penjadwalan', $meta['perorangan_ids'] ?: [-1])
                    ->select('Kode_Karyawan');
            });
        }

        return $q;
    }

    /**
     * Terapkan filter monitoring (divisi/dept/level/jabatan/lokasi/search) ke builder.
     * TIDAK menerapkan whereIn Id_User — caller bertanggung jawab untuk chunking
     * whereIn user ID bila diperlukan (hindari SQL Server 2100 parameter limit).
     */
    private function applyFilters($query, array $filters)
    {
        $whereInIf = function ($q, string $col, $values) {
            $values = array_values(array_filter((array) $values, fn ($v) => $v !== null && $v !== ''));

            return ! empty($values) ? $q->whereIn($col, $values) : $q;
        };

        $query = $whereInIf($query, 'k.Lokasi', $filters['lokasi'] ?? []);
        $query = $whereInIf($query, 'vd.nama_divisi', $filters['divisi'] ?? []);
        $query = $whereInIf($query, 'vd.nama_sub_divisi', $filters['departemen'] ?? []);
        $query = $whereInIf($query, 'vg.nama_level', $filters['level'] ?? []);
        $query = $whereInIf($query, 'vg.nama_jabatan', $filters['jabatan'] ?? []);
        $query = KpiFilterTglMasuk::terapkan($query, KpiFilterTglMasuk::rentang($filters['tgl_masuk'] ?? null));

        // Pencarian nama / kode karyawan (mode "semua").
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(fn ($w) => $w->where('k.Nama', 'like', $like)->orWhere('k.Kode_Karyawan', 'like', $like));
        }

        return $query;
    }

    /**
     * Ambil hasil query dengan chunking whereIn untuk ID user (mode manual).
     * Hindari SQL Server max 2100 parameter per prepared statement.
     *
     * @param  string|null  $userCol  Kolom Id_User (mis. 'p.Id_User'); null = mode "semua" (tanpa whereIn).
     */
    private function chunkedGet($baseQuery, ?string $userCol, ?array $idUsers, array $selectCols, string $orderBy = 'k.Nama'): \Illuminate\Support\Collection
    {
        $query = clone $baseQuery;

        if ($idUsers === null) {
            // Mode "semua": tanpa filter user ID.
            return $query->orderByRaw($orderBy)->get($selectCols);
        }

        if (empty($idUsers)) {
            return collect();
        }

        // Mode manual: chunk whereIn agar tidak lampaui 2100 parameter.
        $results = collect();
        foreach (array_chunk($idUsers, 2000) as $chunk) {
            $batch = (clone $baseQuery)->whereIn($userCol, $chunk)->orderByRaw($orderBy)->get($selectCols);
            $results = $results->merge($batch);
        }

        return $results;
    }

    /**
     * Chunked whereNotIn — untuk exclude list besar (mis. karyawan yg sudah submit).
     */
    private function chunkedWhereNotIn($query, string $column, array $values, array $selectCols, string $orderBy = 'k.Nama'): \Illuminate\Support\Collection
    {
        if (empty($values)) {
            return $query->orderByRaw($orderBy)->get($selectCols);
        }

        $results = collect();
        foreach (array_chunk($values, 2000) as $chunk) {
            $batch = (clone $query)->whereNotIn($column, $chunk)->orderByRaw($orderBy)->get($selectCols);
            $results = $results->merge($batch);
        }

        return $results;
    }

    /**
     * Filter baris hasil build berdasarkan STATUS (mode "semua" / select_all).
     * Status adalah nilai DERIVED (bukan kolom DB) sehingga tak bisa difilter di
     * query universe — disaring di sini agar file export PERSIS = monitoring yg
     * sedang difilter admin. Total_Records panel diselaraskan ke jumlah baris akhir.
     *
     * @param  array<int,array>  $rows
     * @param  array<int,string>  $statusKeys  key status dari filter (mis. 'approved').
     */
    private function filterRowsByStatus(array $rows, array $statusKeys, string $exportId): array
    {
        $statusKeys = array_values(array_filter(array_map('strval', $statusKeys), fn ($v) => $v !== ''));
        if (empty($statusKeys)) {
            return $rows;
        }

        $allowed = array_flip($statusKeys);
        $filtered = array_values(array_filter(
            $rows,
            fn ($r) => isset($allowed[$this->exportStatusKey((string) ($r['status'] ?? ''))]),
        ));

        // Selaraskan total panel dengan jumlah yang benar-benar di-export (countExport
        // Universe tak bisa hitung status -> diperbaiki di sini setelah status diketahui).
        DB::table(self::TABLE)->where('Export_Id', $exportId)->update([
            'Total_Records' => count($filtered),
            'Updated_At' => now(),
        ]);

        return $filtered;
    }

    /**
     * Petakan LABEL status baris export -> KEY status (selaras dgn filter monitoring
     * di KpiEnforcementController: deriveApprovalStatus / classifyParameterTargetForPeriod).
     */
    private function exportStatusKey(string $label): string
    {
        if (str_starts_with($label, 'Menunggu')) {
            return 'waiting';
        }

        return match ($label) {
            'Disetujui' => 'approved',
            'Ditolak' => 'rejected',
            'Draft' => 'draft',
            'Belum Submit', 'Belum Update' => 'belum_submit',
            'Tidak Submit', 'Tidak Update' => 'tidak_submit',
            'Tidak Ada Parameter' => 'tanpa_parameter',
            'Tidak Ada Target' => 'tanpa_target',
            default => '',
        };
    }

    protected function renderFile(object $job, array $dataset): array
    {
        return $job->Format === 'pdf'
            ? $this->renderPdf($job, $dataset)
            : $this->renderExcel($job, $dataset);
    }

    protected function listLabel(object $job): string
    {
        $payload = $this->payload($job);
        $jenis = $payload['jenis'] ?? 'parameter';
        $jenisLabel = $jenis === 'performance' ? 'Update Performance' : 'KPI Parameter';
        $scopeLabel = $this->scopeLabel($jenis, $payload['scope_value'] ?? null);

        return $jenisLabel.' · '.$scopeLabel.' · '.((int) $job->Total_Records).' karyawan';
    }

    /**
     * Tambah field spesifik KPI (jenis, scope) ke payload UI panel.
     */
    protected function mapJob(object $job): array
    {
        $base = parent::mapJob($job);
        $payload = $this->payload($job);
        $jenis = $payload['jenis'] ?? 'parameter';

        return array_merge($base, [
            'jenis' => $jenis,
            'jenis_label' => $jenis === 'performance' ? 'Update Performance' : 'KPI Parameter',
            'scope_label' => $this->scopeLabel($jenis, $payload['scope_value'] ?? null),
        ]);
    }

    // =====================================================================
    // Batch prefetch (hindari N+1 query per karyawan di loop dataset)
    // =====================================================================

    /**
     * Batch fetch approval flows untuk domain 'parameter'.
     * Gantikan approvalFlow() per-transaksi → 1-2 batch query.
     *
     * @return array<string, array> No_Transaksi → flow steps
     */
    private function prefetchParameterApprovalFlows(array $noTransaksiList): array
    {
        $result = [];
        if (empty($noTransaksiList)) {
            return $result;
        }
        foreach ($noTransaksiList as $nt) {
            $result[$nt] = [];
        }

        // 1. Deteksi transaksi central (pakai HRIS_Approval_Request + Id_Jenis_Approval
        //    domain KPI — konsisten dgn KpiApprovalService::engineFor).
        $centralSet = [];
        foreach (array_chunk($noTransaksiList, 2000) as $chunk) {
            DB::table('HRIS_Approval_Request as ar')
                ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
                ->whereIn('ja.Source_Reference', ['KPI_Approval_Flow_Parameter', 'KPI_Approval_Flow_Performance', 'ROLE_KPI_VERIF'])
                ->whereIn('ar.No_Transaksi', $chunk)
                ->select('ar.No_Transaksi')
                ->distinct()
                ->pluck('No_Transaksi')
                ->each(fn ($nt) => $centralSet[$nt] = true);
        }

        // 2. Central flows (batch).
        $centralList = array_keys($centralSet);
        if (! empty($centralList)) {
            foreach (array_chunk($centralList, 2000) as $chunk) {
                DB::table('N_HRIS_VW_Approval_Request_V2 as vw')
                    ->leftJoin('HRIS_Approval_Request as ar', function ($j) {
                        $j->on('ar.No_Transaksi', '=', 'vw.No_Transaksi')
                            ->on('ar.Request_Order_Flow', '=', 'vw.Request_Order_Flow');
                    })
                    ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
                    ->whereIn('vw.No_Transaksi', $chunk)
                    ->orderBy('vw.No_Transaksi')
                    ->orderBy('vw.Request_Order_Flow', 'asc')
                    ->get([
                        'vw.No_Transaksi', 'vw.Request_Order_Flow', 'vw.Current_Approver_Name',
                        'vw.Role_Name', 'vw.Flag_Approval', 'vw.Approved_By_Nama',
                        'vw.Tanggal_Approval', 'vw.Catatan', 'ja.Source_Reference',
                    ])
                    ->groupBy('No_Transaksi')
                    ->each(function ($steps, $nt) use (&$result) {
                        $result[$nt] = $steps->map(fn ($s) => [
                            'order' => (int) $s->Request_Order_Flow,
                            'approver' => $s->Current_Approver_Name ?: ($s->Role_Name ?: '-'),
                            'status' => $s->Flag_Approval === 'Y' ? 'Disetujui' : ($s->Flag_Approval === 'T' ? 'Ditolak' : 'Menunggu'),
                            'approved_by' => $s->Approved_By_Nama,
                            'tanggal' => $s->Tanggal_Approval,
                            'catatan' => $s->Catatan,
                            'is_verif' => ($s->Source_Reference ?? null) === 'ROLE_KPI_VERIF',
                        ])->values()->all();
                    });
            }
        }

        // 3. Legacy flows (non-central).
        $legacyList = array_values(array_diff($noTransaksiList, $centralList));
        if (! empty($legacyList)) {
            foreach (array_chunk($legacyList, 2000) as $chunk) {
                DB::table('KPI_Approval_Request_Parameter as r')
                    ->join('KPI_Approval_Flow_Parameter as f', 'r.Flow_Id', '=', 'f.Id_Approval_Flow')
                    ->leftJoin('Karyawan as k', 'f.Kode_Karyawan_Approver', '=', 'k.Kode_Karyawan')
                    ->whereIn('r.No_Transaksi', $chunk)
                    ->orderBy('r.No_Transaksi')
                    ->orderByRaw('TRY_CAST(f.order_flow AS INT) ASC')
                    ->get([
                        'r.No_Transaksi', 'f.order_flow', 'k.Nama as Approver_Nama',
                        'f.Kode_Karyawan_Approver', 'r.Flag_Approval', 'r.update_at', 'r.Alasan',
                    ])
                    ->groupBy('No_Transaksi')
                    ->each(function ($steps, $nt) use (&$result) {
                        $result[$nt] = $steps->map(fn ($r) => [
                            'order' => (int) $r->order_flow,
                            'approver' => $r->Approver_Nama ?: $r->Kode_Karyawan_Approver,
                            'status' => $r->Flag_Approval === 'Y' ? 'Disetujui' : ($r->Flag_Approval === 'T' ? 'Ditolak' : 'Menunggu'),
                            'approved_by' => $r->Flag_Approval === 'Y' ? ($r->Approver_Nama ?: $r->Kode_Karyawan_Approver) : null,
                            'tanggal' => $r->update_at,
                            'catatan' => $r->Alasan,
                            'is_verif' => false,
                        ])->values()->all();
                    });
            }
        }

        return $result;
    }

    /**
     * Batch fetch approval flows untuk domain 'performance'.
     */
    private function prefetchPerformanceApprovalFlows(array $noTransaksiList): array
    {
        $result = [];
        if (empty($noTransaksiList)) {
            return $result;
        }
        foreach ($noTransaksiList as $nt) {
            $result[$nt] = [];
        }

        // 1. Deteksi central (domain KPI — konsisten dgn KpiApprovalService::engineFor).
        $centralSet = [];
        foreach (array_chunk($noTransaksiList, 2000) as $chunk) {
            DB::table('HRIS_Approval_Request as ar')
                ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
                ->whereIn('ja.Source_Reference', ['KPI_Approval_Flow_Parameter', 'KPI_Approval_Flow_Performance', 'ROLE_KPI_VERIF'])
                ->whereIn('ar.No_Transaksi', $chunk)
                ->select('ar.No_Transaksi')
                ->distinct()
                ->pluck('No_Transaksi')
                ->each(fn ($nt) => $centralSet[$nt] = true);
        }

        // 2. Central flows.
        $centralList = array_keys($centralSet);
        if (! empty($centralList)) {
            foreach (array_chunk($centralList, 2000) as $chunk) {
                DB::table('N_HRIS_VW_Approval_Request_V2 as vw')
                    ->leftJoin('HRIS_Approval_Request as ar', function ($j) {
                        $j->on('ar.No_Transaksi', '=', 'vw.No_Transaksi')
                            ->on('ar.Request_Order_Flow', '=', 'vw.Request_Order_Flow');
                    })
                    ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
                    ->whereIn('vw.No_Transaksi', $chunk)
                    ->orderBy('vw.No_Transaksi')
                    ->orderBy('vw.Request_Order_Flow', 'asc')
                    ->get([
                        'vw.No_Transaksi', 'vw.Request_Order_Flow', 'vw.Current_Approver_Name',
                        'vw.Role_Name', 'vw.Flag_Approval', 'vw.Approved_By_Nama',
                        'vw.Tanggal_Approval', 'vw.Catatan', 'ja.Source_Reference',
                    ])
                    ->groupBy('No_Transaksi')
                    ->each(function ($steps, $nt) use (&$result) {
                        $result[$nt] = $steps->map(fn ($s) => [
                            'order' => (int) $s->Request_Order_Flow,
                            'approver' => $s->Current_Approver_Name ?: ($s->Role_Name ?: '-'),
                            'status' => $s->Flag_Approval === 'Y' ? 'Disetujui' : ($s->Flag_Approval === 'T' ? 'Ditolak' : 'Menunggu'),
                            'approved_by' => $s->Approved_By_Nama,
                            'tanggal' => $s->Tanggal_Approval,
                            'catatan' => $s->Catatan,
                            'is_verif' => ($s->Source_Reference ?? null) === 'ROLE_KPI_VERIF',
                        ])->values()->all();
                    });
            }
        }

        // 3. Legacy flows.
        $legacyList = array_values(array_diff($noTransaksiList, $centralList));
        if (! empty($legacyList)) {
            foreach (array_chunk($legacyList, 2000) as $chunk) {
                DB::table('KPI_Approval_Request_Performance as r')
                    ->join('KPI_Approval_Flow_Performance as f', 'r.Flow_Id', '=', 'f.Id_Approval_Flow')
                    ->leftJoin('Karyawan as k', 'f.Kode_Karyawan_Approver', '=', 'k.Kode_Karyawan')
                    ->whereIn('r.No_Transaksi', $chunk)
                    ->orderBy('r.No_Transaksi')
                    ->orderByRaw('TRY_CAST(f.order_flow AS INT) ASC')
                    ->get([
                        'r.No_Transaksi', 'f.order_flow', 'k.Nama as Approver_Nama',
                        'f.Kode_Karyawan_Approver', 'r.Flag_Approval', 'r.update_at', 'r.Alasan',
                    ])
                    ->groupBy('No_Transaksi')
                    ->each(function ($steps, $nt) use (&$result) {
                        $result[$nt] = $steps->map(fn ($r) => [
                            'order' => (int) $r->order_flow,
                            'approver' => $r->Approver_Nama ?: $r->Kode_Karyawan_Approver,
                            'status' => $r->Flag_Approval === 'Y' ? 'Disetujui' : ($r->Flag_Approval === 'T' ? 'Ditolak' : 'Menunggu'),
                            'approved_by' => $r->Flag_Approval === 'Y' ? ($r->Approver_Nama ?: $r->Kode_Karyawan_Approver) : null,
                            'tanggal' => $r->update_at,
                            'catatan' => $r->Alasan,
                            'is_verif' => false,
                        ])->values()->all();
                    });
            }
        }

        return $result;
    }

    /**
     * Batch fetch parameter details + targets untuk semua pengajuan sekaligus.
     *
     * @return array<int, array> Id_Pengajuan → parameter rows
     */
    private function prefetchParameterDetails(array $idPengajuanList): array
    {
        $result = [];
        if (empty($idPengajuanList)) {
            return $result;
        }

        // Fetch semua parameter untuk semua pengajuan dalam 1 batch.
        $params = collect();
        foreach (array_chunk($idPengajuanList, 2000) as $chunk) {
            $batch = DB::table('KPI_Pengajuan_Detail as pd')
                ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
                ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
                ->leftJoin('KPI_Master_Target as mt', 'mt.Id_Master_Target', '=', 'pd.Id_Master_Target')
                ->whereIn('pd.Id_Pengajuan', $chunk)
                ->get([
                    'pd.Id_Pengajuan', 'pd.Id_Pengajuan_Detail', 'pd.Nama_Parameter',
                    'pd.Deskripsi', 'pd.Bobot', 'bc.Nama_Kategori as Bsc', 'mf.Rumus',
                    'ms.Simbol_Satuan', 'mt.Nama_Target as Tipe_Target', 'mt.Kode_Target',
                ]);
            $params = $params->merge($batch);
        }

        if ($params->isEmpty()) {
            return $result;
        }

        // Fetch semua targets dalam 1 batch.
        $detailIds = $params->pluck('Id_Pengajuan_Detail')->unique()->values()->toArray();
        $targets = collect();
        foreach (array_chunk($detailIds, 2000) as $chunk) {
            $batch = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Detail', $chunk)
                ->get(['Id_Pengajuan_Detail', 'Nama_Target', 'Nilai_Target', 'Periode'])
                ->groupBy('Id_Pengajuan_Detail');
            foreach ($batch as $detId => $tRows) {
                $targets[$detId] = $tRows;
            }
        }

        // Susun per Id_Pengajuan.
        foreach ($params->groupBy('Id_Pengajuan') as $idPengajuan => $paramRows) {
            $result[(int) $idPengajuan] = $paramRows->map(function ($p) use ($targets) {
                $tRows = $targets->get($p->Id_Pengajuan_Detail, collect());

                return [
                    'nama_parameter' => $p->Nama_Parameter,
                    'deskripsi' => $p->Deskripsi,
                    'bobot' => (float) $p->Bobot,
                    'bsc' => $p->Bsc,
                    'rumus' => $this->humanizeRumus($p->Rumus),
                    'satuan' => $p->Simbol_Satuan,
                    'tipe_target' => $p->Tipe_Target,
                    'kode_target' => $p->Kode_Target,
                    'targets' => $tRows->map(fn ($t) => [
                        'periode' => $t->Periode,
                        'nama_target' => $t->Nama_Target,
                        'nilai_target' => $t->Nilai_Target !== null ? (float) $t->Nilai_Target : null,
                    ])->values()->all(),
                ];
            })->values()->all();
        }

        return $result;
    }

    /**
     * Batch fetch performance details + PICA untuk semua update performance sekaligus.
     *
     * @return array<int, array> Id_Update_Performance → parameter rows
     */
    private function prefetchPerformanceDetails(array $idUpList): array
    {
        $result = [];
        if (empty($idUpList)) {
            return $result;
        }

        // Fetch semua detail performance dalam batch.
        $details = collect();
        foreach (array_chunk($idUpList, 2000) as $chunk) {
            $batch = DB::table('KPI_Update_Performance_Detail as upd')
                ->leftJoin('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                ->leftJoin('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
                ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
                ->whereIn('upd.Id_Update_Performance', $chunk)
                ->get([
                    'upd.Id_Update_Performance', 'upd.Id_Update_Performance_Detail',
                    'pd.Nama_Parameter', 'pd.Deskripsi', 'pd.Bobot',
                    'bc.Nama_Kategori as Bsc', 'mf.Rumus', 'ms.Simbol_Satuan',
                    'pdet.Nilai_Target', 'pdet.Periode as Periode_Target',
                    'upd.Nilai_Actual', 'upd.Score', 'upd.Achievement',
                    'upd.Flag_Pica', 'upd.Alasan_Reject',
                ]);
            $details = $details->merge($batch);
        }

        if ($details->isEmpty()) {
            return $result;
        }

        // Fetch semua PICA dalam batch.
        $detailIds = $details->pluck('Id_Update_Performance_Detail')->unique()->values()->toArray();
        $pica = collect();
        foreach (array_chunk($detailIds, 2000) as $chunk) {
            $batch = DB::table('KPI_Update_Performance_Det')
                ->whereIn('Id_Update_Performance_Detail', $chunk)
                ->get(['Id_Update_Performance_Detail', 'Identifikasi', 'Koreksi_Aksi', 'Tanggal_Pelaksanaan'])
                ->groupBy('Id_Update_Performance_Detail');
            foreach ($batch as $detId => $pRows) {
                $pica[$detId] = $pRows;
            }
        }

        // Susun per Id_Update_Performance.
        foreach ($details->groupBy('Id_Update_Performance') as $idUp => $detRows) {
            $result[(int) $idUp] = $detRows->map(function ($d) use ($pica) {
                $pRows = $pica->get($d->Id_Update_Performance_Detail, collect());

                return [
                    'nama_parameter' => $d->Nama_Parameter,
                    'deskripsi' => $d->Deskripsi,
                    'bobot' => $d->Bobot !== null ? (float) $d->Bobot : null,
                    'bsc' => $d->Bsc,
                    'rumus' => $this->humanizeRumus($d->Rumus),
                    'satuan' => $d->Simbol_Satuan,
                    'periode_target' => $d->Periode_Target,
                    'nilai_target' => $d->Nilai_Target !== null ? (float) $d->Nilai_Target : null,
                    'nilai_actual' => $d->Nilai_Actual !== null ? (float) $d->Nilai_Actual : null,
                    'score' => $d->Score !== null ? (float) $d->Score : null,
                    'achievement' => $d->Achievement !== null ? (float) $d->Achievement : null,
                    'alasan_reject' => $d->Alasan_Reject,
                    'pica' => $pRows->map(fn ($p) => [
                        'identifikasi' => $p->Identifikasi,
                        'koreksi_aksi' => $p->Koreksi_Aksi,
                        'tanggal_pelaksanaan' => $p->Tanggal_Pelaksanaan,
                    ])->values()->all(),
                ];
            })->values()->all();
        }

        return $result;
    }

    /**
     * Batch fetch draft performance details.
     *
     * @return array<int, array> Id_Update_Performance_Sementara → parameter rows
     */
    private function prefetchPerformanceDraftDetails(array $idSementaraList): array
    {
        $result = [];
        if (empty($idSementaraList)) {
            return $result;
        }

        foreach (array_chunk($idSementaraList, 2000) as $chunk) {
            $batch = DB::table('KPI_Update_Performance_Detail_Sementara as upd')
                ->leftJoin('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                ->leftJoin('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
                ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
                ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
                ->whereIn('upd.Id_Update_Performance_Sementara', $chunk)
                ->where('upd.Flag_Aktif', 'Y')
                ->get([
                    'upd.Id_Update_Performance_Sementara', 'pd.Nama_Parameter', 'pd.Deskripsi',
                    'pd.Bobot', 'bc.Nama_Kategori as Bsc', 'mf.Rumus', 'ms.Simbol_Satuan',
                    'pdet.Nilai_Target', 'pdet.Periode as Periode_Target',
                    'upd.Nilai_Actual', 'upd.Score', 'upd.Achievement',
                ]);
            foreach ($batch->groupBy('Id_Update_Performance_Sementara') as $idSmt => $rows) {
                $result[(int) $idSmt] = $rows->map(fn ($d) => [
                    'nama_parameter' => $d->Nama_Parameter,
                    'deskripsi' => $d->Deskripsi,
                    'bobot' => $d->Bobot !== null ? (float) $d->Bobot : null,
                    'bsc' => $d->Bsc,
                    'rumus' => $this->humanizeRumus($d->Rumus),
                    'satuan' => $d->Simbol_Satuan,
                    'periode_target' => $d->Periode_Target,
                    'nilai_target' => $d->Nilai_Target !== null ? (float) $d->Nilai_Target : null,
                    'nilai_actual' => $d->Nilai_Actual !== null ? (float) $d->Nilai_Actual : null,
                    'score' => $d->Score !== null ? (float) $d->Score : null,
                    'achievement' => $d->Achievement !== null ? (float) $d->Achievement : null,
                    'alasan_reject' => null,
                    'pica' => [],
                ])->values()->all();
            }
        }

        return $result;
    }

    // =====================================================================
    // Dataset builders (detail berlapis)
    // =====================================================================

    /**
     * Dataset KPI Parameter (pengajuan): header karyawan + status approval + alur
     * approval + rincian parameter (atribut + target per periode).
     */
    private function buildParameterDataset(?string $tahun, array $meta, ?array $idUsers, array $filters, string $exportId): array
    {
        $scheduleIds = $meta['schedule_ids'] ?? [];
        if (empty($scheduleIds)) {
            return ['jenis' => 'parameter', 'scope_label' => 'Tahun '.$tahun, 'rows' => []];
        }

        $baseQuery = KpiKepegawaianPeriode::terapkan(
            DB::table('KPI_Pengajuan as p')
                ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
                ->join('Karyawan as k', 'p.Id_User', '=', 'k.UserID_Web')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan'),
            $this->batasKepegawaianUniverse($meta, $tahun),
        )
            ->where('p.Flag_Aktif', 'Y')
            ->whereIn('p.Id_Penjadwalan', $scheduleIds);

        $baseQuery = $this->applyFilters($baseQuery, $filters);

        $selectCols = [
            'p.Id_Pengajuan', 'p.No_Transaksi', 'p.Flag_Approval', 'p.Tanggal', 'p.Jam',
            'k.Kode_Karyawan', 'k.Nama', 'k.Tgl_Masuk', 'k.Lokasi',
            'vd.nama_divisi as Divisi', 'vd.nama_sub_divisi as Departemen',
            'vg.nama_level as Level', 'vg.nama_jabatan as Jabatan',
            'j.Nama_Kegiatan as Jadwal_Nama', 'j.Flag_Perorangan as Jadwal_Peror',
        ];

        // Chunked get (hindari 2100 param limit bila manual mode).
        $pengajuanList = $this->chunkedGet($baseQuery, $idUsers !== null ? 'p.Id_User' : null, $idUsers, $selectCols);

        // Ambil hanya pengajuan terbaru per user.
        $pengajuanList = $pengajuanList->groupBy('Kode_Karyawan')
            ->map(fn ($g) => $g->sortByDesc('Id_Pengajuan')->first())
            ->values();

        // --- Batch prefetch: approval flows + parameter details ---
        $noTransaksiList = $pengajuanList->pluck('No_Transaksi')->filter()->unique()->values()->toArray();
        $approvalFlows = $this->prefetchParameterApprovalFlows($noTransaksiList);

        $idPengajuanList = $pengajuanList->pluck('Id_Pengajuan')
            ->map(fn ($v) => (int) $v)->unique()->values()->toArray();
        $parameterData = $this->prefetchParameterDetails($idPengajuanList);

        // Karyawan terjadwal yang BELUM mengisi.
        $missing = $this->missingUniverseRows(
            $meta,
            $idUsers,
            $filters,
            $pengajuanList->pluck('Kode_Karyawan')->all(),
            $tahun,
        );

        $total = $pengajuanList->count() + $missing->count();
        $rows = [];
        $processed = 0;

        foreach ($pengajuanList as $pg) {
            $flow = $approvalFlows[$pg->No_Transaksi] ?? [];
            $status = $this->statusFromFlow($flow, $pg->Flag_Approval, $pg->No_Transaksi);

            $rows[] = [
                'kode_karyawan' => $pg->Kode_Karyawan,
                'nama' => $pg->Nama,
                'tgl_masuk' => $this->tglMasuk($pg->Tgl_Masuk ?? null),
                'lokasi' => $pg->Lokasi,
                'divisi' => $pg->Divisi,
                'departemen' => $pg->Departemen,
                'level' => $pg->Level,
                'jabatan' => $pg->Jabatan,
                'no_transaksi' => $pg->No_Transaksi,
                'tanggal' => $pg->Tanggal,
                'jadwal' => $this->jadwalLabel($pg->Jadwal_Nama, $pg->Jadwal_Peror),
                'status' => $status['label'],
                'current_stage' => $status['stage'],
                'parameters' => $parameterData[(int) $pg->Id_Pengajuan] ?? [],
                'approval_flow' => $flow,
            ];

            $this->bumpProgress($exportId, ++$processed, $total);
        }

        // Baris karyawan yang belum/tidak mengisi.
        foreach ($missing as $m) {
            $statusLabel = $this->labelBelumAtauTidak(
                $meta,
                $m->Kode_Karyawan ?? null,
                'Belum Submit',
                'Tidak Submit',
            );
            $rows[] = $this->emptyStatusRow($m, $statusLabel, false);
            $this->bumpProgress($exportId, ++$processed, $total);
        }

        return [
            'jenis' => 'parameter',
            'scope_label' => 'Tahun '.$tahun,
            'rows' => $this->filterRowsByStatus($rows, $filters['status'] ?? [], $exportId),
        ];
    }

    /**
     * Karyawan terjadwal (universe) yang TIDAK ada di $excludeKode — yakni yang
     * belum/tidak mengisi. Memakai seleksi yang sama (manual idUsers / filter).
     */
    /**
     * @param  string|array<int,string>|null  $scopeValue
     */
    private function missingUniverseRows(
        array $meta,
        ?array $idUsers,
        array $filters,
        array $excludeKode,
        string|array|null $scopeValue = null,
    ) {
        $selectCols = [
            'k.UserID_Web', 'k.Kode_Karyawan', 'k.Nama', 'k.Tgl_Masuk', 'k.Lokasi',
            'vd.nama_divisi as Divisi', 'vd.nama_sub_divisi as Departemen',
            'vg.nama_level as Level', 'vg.nama_jabatan as Jabatan',
        ];

        if (! empty($excludeKode)) {
            // Ada exclude list: perlu both whereIn (user IDs) + whereNotIn (excluded).
            $baseForExclude = $this->applyFilters($this->universeQuery($meta, $scopeValue), $filters);

            if ($idUsers !== null && ! empty($idUsers)) {
                // Manual mode: chunk whereIn, lalu whereNotIn per chunk.
                $results = collect();
                foreach (array_chunk($idUsers, 2000) as $userChunk) {
                    $q = (clone $baseForExclude)->whereIn('k.UserID_Web', $userChunk);
                    $batch = $this->chunkedWhereNotIn($q, 'k.Kode_Karyawan', $excludeKode, $selectCols);
                    $results = $results->merge($batch);
                }

                return $results;
            }

            // Mode "semua": hanya whereNotIn.
            return $this->chunkedWhereNotIn($baseForExclude, 'k.Kode_Karyawan', $excludeKode, $selectCols);
        }

        // Tanpa exclude: cukup chunkedGet (handle whereIn user bila manual mode).
        $query = $this->applyFilters($this->universeQuery($meta, $scopeValue), $filters);

        return $this->chunkedGet($query, $idUsers !== null ? 'k.UserID_Web' : null, $idUsers, $selectCols);
    }

    /** Karyawan.Tgl_Masuk sebagai teks siap cetak ("5 Jan 2021"); '-' bila kosong. */
    private function tglMasuk($nilai): string
    {
        return $nilai ? Carbon::parse($nilai)->locale('id')->translatedFormat('d M Y') : '-';
    }

    /**
     * Baris status-saja untuk karyawan yang belum mengisi (tanpa parameter/skor).
     * $isPerf menentukan field skor (performance) disertakan kosong/'-'.
     */
    private function emptyStatusRow(object $k, string $statusLabel, bool $isPerf): array
    {
        $base = [
            'kode_karyawan' => $k->Kode_Karyawan,
            'nama' => $k->Nama,
            'tgl_masuk' => $this->tglMasuk($k->Tgl_Masuk ?? null),
            'lokasi' => $k->Lokasi,
            'divisi' => $k->Divisi,
            'departemen' => $k->Departemen,
            'level' => $k->Level,
            'jabatan' => $k->Jabatan,
            'no_transaksi' => null,
            'tanggal' => null,
            'jadwal' => null,
            'status' => $statusLabel,
            'current_stage' => null,
            'parameters' => [],   // tak ada rincian -> renderer tampilkan '-'
            'approval_flow' => [],
        ];

        if ($isPerf) {
            $base = array_merge($base, [
                'periode' => null,
                'total_score' => null,
                'total_achievement' => null,
                'total_achievement_raw' => null,
                'total_bobot' => null,
                'total_score_raw' => null,
                'is_capped' => false,
                'cap_min' => null,
                'cap_max' => null,
            ]);
        }

        return $base;
    }

    /**
     * Dataset Update Performance AKUMULASI (Year-To-Date).
     *
     * Satu baris per karyawan, bukan per transaksi: yang dilaporkan adalah
     * ΣNilai_Actual dan ΣNilai_Target tiap parameter sepanjang Januari s/d bulan
     * terpilih, dijalankan lewat rumus parameter dan dijepit per parameter —
     * angka yang sama persis dengan yang dilihat admin di layar dan dengan
     * `kpi:verify-ytd`, karena ketiganya memakai KpiAkumulasiYtdService yang sama.
     *
     * Rinciannya sengaja ikut dicetak. Satu angka akhir yang tidak bisa
     * ditelusuri ke Σtarget dan Σactual-nya akan selalu berakhir jadi bantahan
     * yang tak bisa dijawab.
     *
     * Kolom alur persetujuan hilang dengan sendirinya di sini (approval_flow
     * kosong -> maxLevels 0), dan itu memang benar: sebuah akumulasi tidak
     * disetujui oleh siapa pun; yang disetujui adalah transaksi bulanannya.
     *
     * @param  array<int,string>  $periods  Daftar 'YYYY-MM' Jan..N
     */
    private function buildPerformanceYtdDataset(
        ?string $periode,
        array $periods,
        array $meta,
        ?array $idUsers,
        array $filters,
        string $exportId,
    ): array {
        $labelRentang = $this->labelRentangYtd($periode, $periods);

        if (! $periode || empty($periods) || empty($meta['schedule_ids'])) {
            return ['jenis' => 'performance', 'scope_label' => $labelRentang, 'rows' => []];
        }

        // Universe = seluruh karyawan terjadwal dalam scope (yang sudah maupun
        // belum punya realisasi). Tanpa exclude: di YTD tidak ada "sudah/belum"
        // per baris transaksi — yang membedakan hanya ada/tidaknya akumulasi.
        $universe = $this->missingUniverseRows($meta, $idUsers, $filters, [], $periods ?: $periode);

        $skor = app(KpiAkumulasiYtdService::class)->rincianPerUser(
            $universe->pluck('UserID_Web')->map(fn ($v) => (int) $v)->all(),
            (int) substr($periode, 0, 4),
            $periods,
        );

        $rows = [];
        $total = max(1, $universe->count());
        $processed = 0;

        foreach ($universe as $k) {
            $r = $skor[(string) (int) $k->UserID_Web] ?? null;

            if ($r === null) {
                // Tidak punya SATU pun realisasi terverifikasi sepanjang rentang.
                // Bukan nol dan bukan angka lantai cap — dua keadaan yang berbeda.
                $baris = $this->emptyStatusRow($k, 'Belum Ada Realisasi', true);
                $baris['periode'] = $labelRentang;
                $rows[] = $baris;
                $this->bumpProgress($exportId, ++$processed, $total);

                continue;
            }

            $rows[] = [
                'kode_karyawan' => $k->Kode_Karyawan,
                'nama' => $k->Nama,
                'tgl_masuk' => $this->tglMasuk($k->Tgl_Masuk ?? null),
                'lokasi' => $k->Lokasi,
                'divisi' => $k->Divisi,
                'departemen' => $k->Departemen,
                'level' => $k->Level,
                'jabatan' => $k->Jabatan,
                'no_transaksi' => null,
                'periode' => $labelRentang,
                'tanggal' => null,
                'jadwal' => null,
                'status' => 'Akumulasi',
                'current_stage' => null,
                'total_score' => (float) $r['sum_weighted'],
                'total_achievement' => (float) $r['skor'],
                'total_achievement_raw' => (float) $r['raw_pct'],
                'total_bobot' => (float) $r['total_bobot'],
                'total_score_raw' => null,
                'is_capped' => abs((float) $r['raw_pct'] - (float) $r['skor']) > 0.0001,
                'cap_min' => 50,
                'cap_max' => 125,
                'parameters' => array_map(fn (array $p) => [
                    'nama_parameter' => $p['nama_parameter'],
                    'deskripsi' => null,
                    'bobot' => $p['bobot'],
                    'bsc' => $p['bsc'],
                    'rumus' => $this->humanizeRumus($p['rumus']),
                    'satuan' => $p['satuan'],
                    'periode_target' => $labelRentang,
                    'nilai_target' => $p['sum_target'],
                    'nilai_actual' => $p['sum_actual'],
                    'score' => $p['score'],
                    'achievement' => $p['achievement'],
                    'alasan_reject' => null,
                    'pica' => [],
                ], $r['parameter']),
                'approval_flow' => [],
            ];

            $this->bumpProgress($exportId, ++$processed, $total);
        }

        return [
            'jenis' => 'performance',
            'scope_label' => $labelRentang,
            'rows' => $this->filterRowsByStatus($rows, $filters['status'] ?? [], $exportId),
        ];
    }

    /** "Akumulasi Januari - Mei 2026". Dipakai judul berkas maupun tiap sel periode. */
    private function labelRentangYtd(?string $periode, array $periods): string
    {
        if (! $periode) {
            return 'Akumulasi (periode tidak diketahui)';
        }

        $akhir = Carbon::parse($periode.'-01');
        $awal = Carbon::parse((reset($periods) ?: $periode).'-01');

        return $awal->isoFormat('MMMM').' - '.$akhir->isoFormat('MMMM YYYY').' (Akumulasi)';
    }

    /**
     * Dataset Update Performance: header + skor + status + alur approval + rincian
     * parameter yang diisi user (actual/score/achievement + atribut).
     */
    private function buildPerformanceDataset(?string $periode, array $meta, ?array $idUsers, array $filters, string $exportId): array
    {
        $scheduleIds = $meta['schedule_ids'] ?? [];
        if (empty($scheduleIds)) {
            return ['jenis' => 'performance', 'scope_label' => 'Periode '.$periode, 'rows' => []];
        }

        $baseQuery = KpiKepegawaianPeriode::terapkan(
            DB::table('KPI_Update_Performance as up')
                ->join('Karyawan as k', 'up.Id_User', '=', 'k.UserID_Web')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
                ->leftJoin('KPI_Master_Penjadwalan as j', 'up.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan'),
            $this->batasKepegawaianUniverse($meta, $periode),
        )
            ->where('up.Flag_Aktif', 'Y')
            ->whereNull('up.Status')
            ->whereIn('up.Id_Penjadwalan', $scheduleIds);

        $baseQuery = $this->applyFilters($baseQuery, $filters);

        $selectCols = [
            'up.Id_Update_Performance', 'up.No_Transaksi', 'up.Periode', 'up.Tanggal',
            'up.Total_Score', 'up.Total_Score_Achievement', 'up.Total_Bobot',
            'up.Total_Score_Raw', 'up.Total_Score_Achievement_Raw',
            'up.Flag_Capped', 'up.Cap_Min_Applied', 'up.Cap_Max_Applied',
            'up.Status_Pengajuan', 'up.Flag_Approval',
            'k.Kode_Karyawan', 'k.Nama', 'k.Tgl_Masuk', 'k.Lokasi',
            'vd.nama_divisi as Divisi', 'vd.nama_sub_divisi as Departemen',
            'vg.nama_level as Level', 'vg.nama_jabatan as Jabatan',
            'j.Nama_Kegiatan as Jadwal_Nama', 'j.Flag_Perorangan as Jadwal_Peror',
        ];

        // Chunked get (hindari 2100 param limit).
        $upList = $this->chunkedGet($baseQuery, $idUsers !== null ? 'up.Id_User' : null, $idUsers, $selectCols);

        $upList = $upList->groupBy('Kode_Karyawan')
            ->map(fn ($g) => $g->sortByDesc('Id_Update_Performance')->first())
            ->values();

        // --- Batch prefetch: approval flows + performance details ---
        $noTransaksiList = $upList->pluck('No_Transaksi')->filter()->unique()->values()->toArray();
        $approvalFlows = $this->prefetchPerformanceApprovalFlows($noTransaksiList);

        $idUpList = $upList->pluck('Id_Update_Performance')
            ->map(fn ($v) => (int) $v)->unique()->values()->toArray();
        $performanceData = $this->prefetchPerformanceDetails($idUpList);

        // Draft hidup (sudah diisi user, BELUM difinalisasi).
        $finalKode = $upList->pluck('Kode_Karyawan')->all();
        $draftList = $this->performanceDraftList($periode, $idUsers, $filters, $finalKode);

        // Prefetch draft details.
        $draftIds = $draftList->pluck('Id_Update_Performance_Sementara')
            ->map(fn ($v) => (int) $v)->unique()->values()->toArray();
        $draftData = $this->prefetchPerformanceDraftDetails($draftIds);

        // Karyawan terjadwal yang BELUM mengisi.
        $usedKode = array_merge($finalKode, $draftList->pluck('Kode_Karyawan')->all());
        $missing = $this->missingUniverseRows($meta, $idUsers, $filters, $usedKode, $periode);

        $total = $upList->count() + $draftList->count() + $missing->count();
        $rows = [];
        $processed = 0;

        foreach ($upList as $up) {
            $flow = $approvalFlows[$up->No_Transaksi] ?? [];
            $status = $this->statusFromFlow($flow, $up->Flag_Approval, $up->No_Transaksi);

            $rows[] = [
                'kode_karyawan' => $up->Kode_Karyawan,
                'nama' => $up->Nama,
                'tgl_masuk' => $this->tglMasuk($up->Tgl_Masuk ?? null),
                'lokasi' => $up->Lokasi,
                'divisi' => $up->Divisi,
                'departemen' => $up->Departemen,
                'level' => $up->Level,
                'jabatan' => $up->Jabatan,
                'no_transaksi' => $up->No_Transaksi,
                'periode' => $up->Periode,
                'tanggal' => $up->Tanggal,
                'jadwal' => $this->jadwalLabel($up->Jadwal_Nama, $up->Jadwal_Peror),
                'total_score' => (float) $up->Total_Score_Achievement,
                'total_achievement' => (float) $up->Total_Score,
                'total_achievement_raw' => $up->Total_Score_Raw !== null ? (float) $up->Total_Score_Raw : null,
                'total_bobot' => (float) $up->Total_Bobot,
                'total_score_raw' => $up->Total_Score_Achievement_Raw !== null ? (float) $up->Total_Score_Achievement_Raw : null,
                'is_capped' => ($up->Flag_Capped ?? 'T') === 'Y',
                'cap_min' => $up->Cap_Min_Applied !== null ? (float) $up->Cap_Min_Applied : null,
                'cap_max' => $up->Cap_Max_Applied !== null ? (float) $up->Cap_Max_Applied : null,
                'status' => $status['label'],
                'current_stage' => $status['stage'],
                'parameters' => $performanceData[(int) $up->Id_Update_Performance] ?? [],
                'approval_flow' => $flow,
            ];

            $this->bumpProgress($exportId, ++$processed, $total);
        }

        // Baris draft.
        foreach ($draftList as $dr) {
            $rows[] = [
                'kode_karyawan' => $dr->Kode_Karyawan,
                'nama' => $dr->Nama,
                'tgl_masuk' => $this->tglMasuk($dr->Tgl_Masuk ?? null),
                'lokasi' => $dr->Lokasi,
                'divisi' => $dr->Divisi,
                'departemen' => $dr->Departemen,
                'level' => $dr->Level,
                'jabatan' => $dr->Jabatan,
                'no_transaksi' => $dr->No_Transaksi_Sementara,
                'periode' => $dr->Periode,
                'tanggal' => $dr->Tanggal,
                'jadwal' => $this->jadwalLabel($dr->Jadwal_Nama, $dr->Jadwal_Peror),
                'total_score' => (float) $dr->Total_Score_Achievement,
                'total_achievement' => (float) $dr->Total_Score,
                'total_achievement_raw' => null,
                'total_bobot' => (float) $dr->Total_Bobot,
                'total_score_raw' => null,
                'is_capped' => false,
                'cap_min' => null,
                'cap_max' => null,
                'status' => 'Draft',
                'current_stage' => null,
                'parameters' => $draftData[(int) $dr->Id_Update_Performance_Sementara] ?? [],
                'approval_flow' => [],
            ];

            $this->bumpProgress($exportId, ++$processed, $total);
        }

        // Baris karyawan yang belum/tidak mengisi.
        $paramTargetClass = $periode
            ? app(KpiEnforcementService::class)->classifyParameterTargetForPeriod(
                $missing->pluck('UserID_Web')->map(fn ($v) => (int) $v)->all(),
                Carbon::parse($periode),
            )
            : [];
        foreach ($missing as $m) {
            $label = $this->labelBelumAtauTidak(
                $meta,
                $m->Kode_Karyawan ?? null,
                'Belum Update',
                'Tidak Update',
            );
            if (! empty($paramTargetClass)) {
                $cls = $paramTargetClass[(int) $m->UserID_Web] ?? 'no_parameter';
                if ($cls === 'no_parameter') {
                    $label = 'Tidak Ada Parameter';
                } elseif ($cls === 'no_target') {
                    $label = 'Tidak Ada Target';
                }
            }
            $rows[] = $this->emptyStatusRow($m, $label, true);
            $this->bumpProgress($exportId, ++$processed, $total);
        }

        return [
            'jenis' => 'performance',
            'scope_label' => 'Periode '.Carbon::parse($periode)->isoFormat('MMMM YYYY'),
            'rows' => $this->filterRowsByStatus($rows, $filters['status'] ?? [], $exportId),
        ];
    }

    /**
     * Daftar draft performance hidup (Flag_Aktif='Y', belum Flag_Pindah='Y') untuk
     * scope periode + filter/seleksi yang sama, mengecualikan karyawan yang sudah
     * punya record final. Diambil draft terbaru per karyawan.
     */
    private function performanceDraftList(?string $periode, ?array $idUsers, array $filters, array $excludeKode)
    {
        $selectCols = [
            'sm.Id_Update_Performance_Sementara', 'sm.No_Transaksi_Sementara', 'sm.Periode', 'sm.Tanggal',
            'sm.Total_Score', 'sm.Total_Score_Achievement', 'sm.Total_Bobot',
            'k.Kode_Karyawan', 'k.Nama', 'k.Tgl_Masuk', 'k.Lokasi',
            'vd.nama_divisi as Divisi', 'vd.nama_sub_divisi as Departemen',
            'vg.nama_level as Level', 'vg.nama_jabatan as Jabatan',
            'j.Nama_Kegiatan as Jadwal_Nama', 'j.Flag_Perorangan as Jadwal_Peror',
        ];

        $baseQuery = DB::table('KPI_Update_Performance_Sementara as sm')
            ->join('Karyawan as k', 'sm.Id_User', '=', 'k.UserID_Web')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
            ->leftJoin('KPI_Master_Penjadwalan as j', 'sm.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('sm.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('sm.Flag_Pindah')->orWhere('sm.Flag_Pindah', '<>', 'Y'))
            ->where('sm.Periode', $periode);

        $baseQuery = $this->applyFilters($baseQuery, $filters);

        if (! empty($excludeKode)) {
            if ($idUsers !== null && ! empty($idUsers)) {
                // Manual mode + exclude: chunk whereIn, lalu whereNotIn per chunk.
                $results = collect();
                foreach (array_chunk($idUsers, 2000) as $userChunk) {
                    $q = (clone $baseQuery)->whereIn('sm.Id_User', $userChunk);
                    $batch = $this->chunkedWhereNotIn($q, 'k.Kode_Karyawan', $excludeKode, $selectCols);
                    $results = $results->merge($batch);
                }

                return $results->groupBy('Kode_Karyawan')
                    ->map(fn ($g) => $g->sortByDesc('Id_Update_Performance_Sementara')->first())
                    ->values();
            }

            // Mode "semua" + exclude: hanya whereNotIn.
            $list = $this->chunkedWhereNotIn($baseQuery, 'k.Kode_Karyawan', $excludeKode, $selectCols);

            return $list->groupBy('Kode_Karyawan')
                ->map(fn ($g) => $g->sortByDesc('Id_Update_Performance_Sementara')->first())
                ->values();
        }

        // Tanpa exclude: chunkedGet (handle whereIn user bila manual mode).
        $list = $this->chunkedGet($baseQuery, $idUsers !== null ? 'sm.Id_User' : null, $idUsers, $selectCols);

        return $list->groupBy('Kode_Karyawan')
            ->map(fn ($g) => $g->sortByDesc('Id_Update_Performance_Sementara')->first())
            ->values();
    }

    /**
     * Rincian parameter draft (dari tabel _Sementara) — mirror performanceRows()
     * tapi membaca actual/score/achievement dari KPI_Update_Performance_Detail_Sementara.
     */
    private function performanceDraftRows(int $idSementara): array
    {
        $details = DB::table('KPI_Update_Performance_Detail_Sementara as upd')
            ->leftJoin('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->leftJoin('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
            ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            ->where('upd.Id_Update_Performance_Sementara', $idSementara)
            ->where('upd.Flag_Aktif', 'Y')
            ->get([
                'pd.Nama_Parameter', 'pd.Deskripsi', 'pd.Bobot',
                'bc.Nama_Kategori as Bsc', 'mf.Rumus', 'ms.Simbol_Satuan',
                'pdet.Nilai_Target', 'pdet.Periode as Periode_Target',
                'upd.Nilai_Actual', 'upd.Score', 'upd.Achievement',
            ]);

        return $details->map(fn ($d) => [
            'nama_parameter' => $d->Nama_Parameter,
            'deskripsi' => $d->Deskripsi,
            'bobot' => $d->Bobot !== null ? (float) $d->Bobot : null,
            'bsc' => $d->Bsc,
            'rumus' => $this->humanizeRumus($d->Rumus),
            'satuan' => $d->Simbol_Satuan,
            'periode_target' => $d->Periode_Target,
            'nilai_target' => $d->Nilai_Target !== null ? (float) $d->Nilai_Target : null,
            'nilai_actual' => $d->Nilai_Actual !== null ? (float) $d->Nilai_Actual : null,
            'score' => $d->Score !== null ? (float) $d->Score : null,
            'achievement' => $d->Achievement !== null ? (float) $d->Achievement : null,
            'alasan_reject' => null,
            'pica' => [],
        ])->values()->all();
    }

    /**
     * Rincian parameter pengajuan (atribut + target per periode).
     */
    private function parameterRows(int $idPengajuan): array
    {
        $params = DB::table('KPI_Pengajuan_Detail as pd')
            ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
            ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            ->leftJoin('KPI_Master_Target as mt', 'mt.Id_Master_Target', '=', 'pd.Id_Master_Target')
            ->where('pd.Id_Pengajuan', $idPengajuan)
            ->get([
                'pd.Id_Pengajuan_Detail', 'pd.Nama_Parameter', 'pd.Deskripsi', 'pd.Bobot',
                'bc.Nama_Kategori as Bsc', 'mf.Rumus', 'ms.Simbol_Satuan',
                'mt.Nama_Target as Tipe_Target', 'mt.Kode_Target',
            ]);

        $targets = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $params->pluck('Id_Pengajuan_Detail'))
            ->get(['Id_Pengajuan_Detail', 'Nama_Target', 'Nilai_Target', 'Periode'])
            ->groupBy('Id_Pengajuan_Detail');

        return $params->map(fn ($p) => [
            'nama_parameter' => $p->Nama_Parameter,
            'deskripsi' => $p->Deskripsi,
            'bobot' => (float) $p->Bobot,
            'bsc' => $p->Bsc,
            'rumus' => $this->humanizeRumus($p->Rumus),
            'satuan' => $p->Simbol_Satuan,
            'tipe_target' => $p->Tipe_Target,
            'kode_target' => $p->Kode_Target,
            'targets' => ($targets->get($p->Id_Pengajuan_Detail, collect()))->map(fn ($t) => [
                'periode' => $t->Periode,
                'nama_target' => $t->Nama_Target,
                'nilai_target' => $t->Nilai_Target !== null ? (float) $t->Nilai_Target : null,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Rincian parameter performance yang diisi user (actual/score/achievement + atribut + pica).
     */
    private function performanceRows(int $idUp): array
    {
        $details = DB::table('KPI_Update_Performance_Detail as upd')
            ->leftJoin('KPI_Pengajuan_Det as pdet', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
            ->leftJoin('KPI_Pengajuan_Detail as pd', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->leftJoin('KPI_Master_Bsc_Kategori as bc', 'bc.Id_Master_Bsc_Kategori', '=', 'pd.Id_Bsc_Kategori')
            ->leftJoin('KPI_Master_Satuan as ms', 'ms.Id_Master_Satuan', '=', 'pd.Id_Master_Satuan')
            ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
            ->where('upd.Id_Update_Performance', $idUp)
            ->get([
                'upd.Id_Update_Performance_Detail', 'pd.Nama_Parameter', 'pd.Deskripsi', 'pd.Bobot',
                'bc.Nama_Kategori as Bsc', 'mf.Rumus', 'ms.Simbol_Satuan',
                'pdet.Nilai_Target', 'pdet.Periode as Periode_Target',
                'upd.Nilai_Actual', 'upd.Score', 'upd.Achievement', 'upd.Flag_Pica', 'upd.Alasan_Reject',
            ]);

        $pica = DB::table('KPI_Update_Performance_Det')
            ->whereIn('Id_Update_Performance_Detail', $details->pluck('Id_Update_Performance_Detail'))
            ->get(['Id_Update_Performance_Detail', 'Identifikasi', 'Koreksi_Aksi', 'Tanggal_Pelaksanaan'])
            ->groupBy('Id_Update_Performance_Detail');

        return $details->map(fn ($d) => [
            'nama_parameter' => $d->Nama_Parameter,
            'deskripsi' => $d->Deskripsi,
            'bobot' => $d->Bobot !== null ? (float) $d->Bobot : null,
            'bsc' => $d->Bsc,
            'rumus' => $this->humanizeRumus($d->Rumus),
            'satuan' => $d->Simbol_Satuan,
            'periode_target' => $d->Periode_Target,
            'nilai_target' => $d->Nilai_Target !== null ? (float) $d->Nilai_Target : null,
            'nilai_actual' => $d->Nilai_Actual !== null ? (float) $d->Nilai_Actual : null,
            'score' => $d->Score !== null ? (float) $d->Score : null,
            'achievement' => $d->Achievement !== null ? (float) $d->Achievement : null,
            'alasan_reject' => $d->Alasan_Reject,
            'pica' => ($pica->get($d->Id_Update_Performance_Detail, collect()))->map(fn ($p) => [
                'identifikasi' => $p->Identifikasi,
                'koreksi_aksi' => $p->Koreksi_Aksi,
                'tanggal_pelaksanaan' => $p->Tanggal_Pelaksanaan,
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Alur approval (dual-engine) — sama logika dengan KpiEnforcementController.
     */
    private function approvalFlow(?string $noTransaksi, string $domain): array
    {
        if (! $noTransaksi) {
            return [];
        }

        // Deteksi engine konsisten dengan KpiEnforcementController (filter Id_Jenis_Approval
        // KPI), bukan sekadar keberadaan baris HRIS_Approval_Request mana pun.
        $isCentral = app(\App\Services\KPI\Approval\KpiApprovalService::class)->isCentral($noTransaksi);

        if ($isCentral) {
            // Join ke HRIS_Approval_Request + N_HRIS_Jenis_Approval untuk mengetahui
            // role/source step (Source_Reference). Step verifikator KPI dikenali
            // dari Source_Reference = 'ROLE_KPI_VERIF' — dipakai membedakan label
            // "Menunggu Verifikator" vs "Menunggu Approval".
            return DB::table('N_HRIS_VW_Approval_Request_V2 as vw')
                ->leftJoin('HRIS_Approval_Request as ar', function ($j) {
                    $j->on('ar.No_Transaksi', '=', 'vw.No_Transaksi')
                        ->on('ar.Request_Order_Flow', '=', 'vw.Request_Order_Flow');
                })
                ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
                ->where('vw.No_Transaksi', $noTransaksi)
                ->orderBy('vw.Request_Order_Flow', 'asc')
                ->get([
                    'vw.Request_Order_Flow', 'vw.Current_Approver_Name', 'vw.Role_Name',
                    'vw.Flag_Approval', 'vw.Approved_By_Nama', 'vw.Tanggal_Approval', 'vw.Catatan',
                    'ja.Source_Reference',
                ])
                ->map(fn ($s) => [
                    'order' => (int) $s->Request_Order_Flow,
                    'approver' => $s->Current_Approver_Name ?: ($s->Role_Name ?: '-'),
                    'status' => $s->Flag_Approval === 'Y' ? 'Disetujui' : ($s->Flag_Approval === 'T' ? 'Ditolak' : 'Menunggu'),
                    'approved_by' => $s->Approved_By_Nama,
                    'tanggal' => $s->Tanggal_Approval,
                    'catatan' => $s->Catatan,
                    'is_verif' => ($s->Source_Reference ?? null) === 'ROLE_KPI_VERIF',
                ])->values()->all();
        }

        $reqTable = $domain === 'performance' ? 'KPI_Approval_Request_Performance' : 'KPI_Approval_Request_Parameter';
        $flowTable = $domain === 'performance' ? 'KPI_Approval_Flow_Performance' : 'KPI_Approval_Flow_Parameter';

        return DB::table($reqTable.' as r')
            ->join($flowTable.' as f', 'r.Flow_Id', '=', 'f.Id_Approval_Flow')
            ->leftJoin('Karyawan as k', 'f.Kode_Karyawan_Approver', '=', 'k.Kode_Karyawan')
            ->where('r.No_Transaksi', $noTransaksi)
            ->orderByRaw('TRY_CAST(f.order_flow AS INT) ASC')
            ->get(['f.order_flow', 'k.Nama as Approver_Nama', 'f.Kode_Karyawan_Approver', 'r.Flag_Approval', 'r.update_at', 'r.Alasan'])
            ->map(fn ($r) => [
                'order' => (int) $r->order_flow,
                'approver' => $r->Approver_Nama ?: $r->Kode_Karyawan_Approver,
                'status' => $r->Flag_Approval === 'Y' ? 'Disetujui' : ($r->Flag_Approval === 'T' ? 'Ditolak' : 'Menunggu'),
                'approved_by' => $r->Flag_Approval === 'Y' ? ($r->Approver_Nama ?: $r->Kode_Karyawan_Approver) : null,
                'tanggal' => $r->update_at,
                'catatan' => $r->Alasan,
                // LEGACY tidak melalui role pool 'ROLE_KPI_VERIF'; step verifikator
                // tidak dapat dibedakan -> dianggap approval biasa.
                'is_verif' => false,
            ])->values()->all();
    }

    /**
     * Status ringkas transaksi (dual-engine) DARI alur approval yang sudah diambil
     * — tidak melakukan query approval lagi (hemat untuk dataset besar).
     */
    private function statusFromFlow(array $flow, ?string $flagApproval, ?string $noTransaksi): array
    {
        if (($flagApproval ?? null) === 'Y') {
            return ['label' => 'Disetujui', 'stage' => null];
        }
        // Tanpa transaksi / flow kosong = anomali (pengajuan final selalu punya alur).
        // Diperlakukan "Menunggu Approval", BUKAN Draft. Draft asli (performance belum
        // difinalisasi) ditangani di buildPerformanceDataset via tabel _Sementara.
        if (! $noTransaksi || empty($flow)) {
            return ['label' => 'Menunggu Approval', 'stage' => null];
        }

        $total = count($flow);
        $approved = 0;
        $rejected = false;
        $current = null;
        foreach ($flow as $s) {
            if ($s['status'] === 'Ditolak') {
                $rejected = true;
            }
            if ($s['status'] === 'Disetujui') {
                $approved++;
            } elseif ($current === null) {
                $current = $s;
            }
        }

        if ($rejected) {
            return ['label' => 'Ditolak', 'stage' => null];
        }
        if ($approved >= $total) {
            return ['label' => 'Disetujui', 'stage' => null];
        }

        // Menunggu: tampilkan tanpa nomor level. Hanya dua kategori menunggu —
        // "Menunggu Verifikator" bila step berjalan adalah verifikator KPI
        // (ROLE_KPI_VERIF), selain itu "Menunggu Approval".
        $isVerif = ! empty($current['is_verif']);

        return $isVerif
            ? ['label' => 'Menunggu Verifikator', 'stage' => 'Verifikator']
            : ['label' => 'Menunggu Approval', 'stage' => 'Approval'];
    }

    private function jadwalLabel(?string $nama, ?string $peror): ?string
    {
        if (! $nama) {
            return null;
        }

        return trim($nama).' ('.(($peror ?? 'T') === 'Y' ? 'Perorangan' : 'Global').')';
    }

    private function humanizeRumus(?string $rumus): ?string
    {
        if (! $rumus) {
            return null;
        }
        $rumus = preg_replace('/\[(.*?)\]/', '$1', $rumus);

        return str_replace(['targetKpiEvo', 'actualKpiEvo'], ['Target', 'Actual'], $rumus);
    }

    // =====================================================================
    // Renderers
    // =====================================================================

    /**
     * @return array{0:string,1:string,2:int} [path, fileName, size]
     */
    private function renderPdf($job, array $dataset): array
    {
        $view = $dataset['jenis'] === 'performance'
            ? 'exports.kpi-performance-pdf'
            : 'exports.kpi-parameter-pdf';

        $pdf = Pdf::loadView($view, ['dataset' => $dataset])
            ->setPaper('a4', 'landscape');

        $content = $pdf->output();
        $path = $this->filePathFor($job, 'pdf');
        $size = $this->putFile($path, $content);

        return [$path, $this->fileName($job, 'pdf'), $size];
    }

    /**
     * @return array{0:string,1:string,2:int} [path, fileName, size]
     */
    private function renderExcel($job, array $dataset): array
    {
        // Export besar (ratusan baris berlapis + kolom approval dinamis) menahan
        // banyak cell di memory. Beri ruang lebih & matikan precalculation rumus
        // (kita tidak menulis formula apa pun, jadi precalc hanya membuang memory).
        @ini_set('memory_limit', '1024M');
        \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance()->disableCalculationCache();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Export KPI');

        $isPerf = $dataset['jenis'] === 'performance';

        // Jumlah level approval terbanyak lintas semua karyawan -> jadi acuan jumlah
        // kolom approval dinamis (satu level = satu kolom, masing-masing di-merge).
        $maxLevels = collect($dataset['rows'])
            ->map(fn ($row) => count($row['approval_flow'] ?? []))
            ->max() ?? 0;

        // Definisi kolom: key, label, lebar, merge (identitas digabung per karyawan).
        // 'merge' = true -> sel digabung vertikal lintas baris parameter karyawan.
        $columns = $this->excelColumns($isPerf, $maxLevels);
        $colCount = count($columns);
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colCount);

        // ---- Judul (A1) + scope (A2), merge full width, fill indigo ----
        $sheet->setCellValue('A1', 'EXPORT MONITORING '.($isPerf ? 'UPDATE PERFORMANCE' : 'KPI PARAMETER'));
        $sheet->setCellValue('A2', $dataset['scope_label']);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ---- Header kolom (baris 3) ----
        $headerRow = 3;
        foreach ($columns as $i => $col) {
            $sheet->setCellValue([$i + 1, $headerRow], $col['label']);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth($col['width']);
        }
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // ---- Data: identitas di-merge per karyawan, parameter berlapis ----
        // Setiap parameter pengajuan punya beberapa target per periode. Agar SEMUA
        // target tampil (bukan hanya periode pertama), tiap parameter di-expand jadi
        // satu baris Excel per target; kolom atribut parameter (nama/bsc/bobot/…)
        // di-merge vertikal lintas baris-target parameter tersebut, sedangkan kolom
        // identitas di-merge lintas seluruh baris karyawan.
        $r = $headerRow + 1;
        $no = 0;
        foreach ($dataset['rows'] as $row) {
            $no++;
            $flow = $row['approval_flow'] ?? [];

            $params = ! empty($row['parameters']) ? $row['parameters'] : [null];
            $startRow = $r;

            foreach ($params as $param) {
                // Daftar target untuk parameter ini. Parameter pengajuan punya
                // 'targets' (multi-periode); performance / baris status tidak ->
                // satu baris saja (target = null, resolver pakai field langsung).
                $targets = (is_array($param) && ! empty($param['targets'])) ? $param['targets'] : [null];
                $paramStartRow = $r;

                foreach ($targets as $target) {
                    foreach ($columns as $i => $col) {
                        $value = $this->excelCellValue($col['key'], $row, $param, $no, $flow, $target);
                        $sheet->setCellValue([$i + 1, $r], $value);
                    }
                    $r++;
                }

                // Merge atribut parameter lintas baris-target parameter ini.
                $paramEndRow = $r - 1;
                if ($paramEndRow > $paramStartRow) {
                    foreach ($columns as $i => $col) {
                        if (! empty($col['param_merge'])) {
                            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                            $sheet->mergeCells("{$letter}{$paramStartRow}:{$letter}{$paramEndRow}");
                        }
                    }
                }
            }

            $endRow = $r - 1;
            // Merge kolom identitas (yang sama untuk semua parameter karyawan).
            if ($endRow > $startRow) {
                foreach ($columns as $i => $col) {
                    if (! empty($col['merge'])) {
                        $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                        $sheet->mergeCells("{$letter}{$startRow}:{$letter}{$endRow}");
                    }
                }
            }
        }

        $lastDataRow = $r - 1;

        // ---- Styling area data: fill biru muda, border thin, wrap, align ----
        if ($lastDataRow >= $headerRow + 1) {
            $dataRange = 'A'.($headerRow + 1).":{$lastCol}{$lastDataRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE5F2']],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);
            // Border thin seluruh tabel (judul s/d data).
            $sheet->getStyle("A1:{$lastCol}{$lastDataRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'B7C3D6']]],
            ]);
            // Kolom identitas/angka rata tengah.
            foreach ($columns as $i => $col) {
                if (! empty($col['center'])) {
                    $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->getStyle("{$letter}".($headerRow + 1).":{$letter}{$lastDataRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
            }
        }

        // Freeze header agar tetap terlihat saat scroll.
        $sheet->freezePane('A'.($headerRow + 1));

        $tmp = tempnam(sys_get_temp_dir(), 'kpiexp');
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false); // tak ada formula -> hemat memory/waktu
        $writer->save($tmp);

        // Lepas workbook dari memory sebelum upload (file besar -> hindari puncak ganda).
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $sheet, $writer);
        gc_collect_cycles();

        $path = $this->filePathFor($job, 'xlsx');
        $size = $this->putFileFromTemp($path, $tmp);

        return [$path, $this->fileName($job, 'xlsx'), $size];
    }

    /**
     * Definisi kolom Excel per jenis. merge=identitas digabung per karyawan,
     * center=rata tengah, width=lebar kolom.
     */
    private function excelColumns(bool $isPerf, int $maxLevels = 0): array
    {
        $identity = [
            ['key' => 'no', 'label' => 'No', 'width' => 5, 'merge' => true, 'center' => true],
            ['key' => 'kode_karyawan', 'label' => 'Kode', 'width' => 8, 'merge' => true, 'center' => true],
            ['key' => 'nama', 'label' => 'Nama', 'width' => 20, 'merge' => true],
            ['key' => 'tgl_masuk', 'label' => 'Tgl Masuk', 'width' => 12, 'merge' => true, 'center' => true],
            ['key' => 'lokasi', 'label' => 'Lokasi', 'width' => 14, 'merge' => true],
            ['key' => 'divisi', 'label' => 'Divisi', 'width' => 22, 'merge' => true],
            ['key' => 'departemen', 'label' => 'Departemen', 'width' => 26, 'merge' => true],
            ['key' => 'level', 'label' => 'Level', 'width' => 12, 'merge' => true, 'center' => true],
            ['key' => 'jabatan', 'label' => 'Jabatan', 'width' => 26, 'merge' => true],
            ['key' => 'no_transaksi', 'label' => 'No. Transaksi', 'width' => 18, 'merge' => true, 'center' => true],
            ['key' => 'jadwal', 'label' => 'Penjadwalan', 'width' => 22, 'merge' => true],
            ['key' => 'status', 'label' => 'Status', 'width' => 22, 'merge' => true, 'center' => true],
        ];

        // Kolom approval dinamis: satu level approval = satu kolom (di-merge per
        // karyawan). Parameter menyertakan kolom "Catatan Penolakan" per level;
        // Performance hanya nama approval (tanpa catatan penolakan).
        $approval = $this->approvalColumns($maxLevels, withCatatan: ! $isPerf);

        if ($isPerf) {
            // Total skor/achievement (identitas, sekali per karyawan) lalu rincian per parameter.
            return array_merge($identity, [
                ['key' => 'total_score', 'label' => 'Total Score', 'width' => 12, 'merge' => true, 'center' => true],
                ['key' => 'total_achievement', 'label' => 'Total Achievement', 'width' => 14, 'merge' => true, 'center' => true],
                ['key' => 'total_achievement_raw', 'label' => 'Total Achievement (Sebelum Cap)', 'width' => 16, 'merge' => true, 'center' => true],
                ['key' => 'total_bobot', 'label' => 'Total Bobot', 'width' => 11, 'merge' => true, 'center' => true],
                ['key' => 'capped', 'label' => 'Capped', 'width' => 16, 'merge' => true, 'center' => true],
                ['key' => 'p_nama', 'label' => 'Parameter', 'width' => 55],
                ['key' => 'p_bsc', 'label' => 'BSC', 'width' => 18],
                ['key' => 'p_rumus', 'label' => 'Rumus', 'width' => 25],
                ['key' => 'p_bobot', 'label' => 'Bobot', 'width' => 8, 'center' => true],
                ['key' => 'p_satuan', 'label' => 'Satuan', 'width' => 8, 'center' => true],
                ['key' => 'p_periode_target', 'label' => 'Periode Target', 'width' => 14, 'center' => true],
                ['key' => 'p_nilai_target', 'label' => 'Target', 'width' => 11, 'center' => true],
                ['key' => 'p_actual', 'label' => 'Actual', 'width' => 11, 'center' => true],
                ['key' => 'p_score', 'label' => 'Score', 'width' => 11, 'center' => true],
                ['key' => 'p_achievement', 'label' => 'Achievement', 'width' => 13, 'center' => true],
                /*
                 * PICA dipecah TIGA kolom, bukan satu sel gabungan.
                 *
                 * Excel dipakai untuk mengolah, bukan sekadar membaca: begitu
                 * identifikasi, tindakan, dan tanggal ditumpuk dalam satu sel,
                 * ketiganya berhenti bisa disaring, diurutkan, atau dihitung —
                 * dan barisnya menjadi setinggi paragraf.
                 *
                 * Aman dipecah karena satu parameter hanya punya satu PICA:
                 * diukur di production 16 Sep 2026, 2.766 parameter ber-PICA dan
                 * NOL di antaranya punya lebih dari satu baris. Kalau kelak
                 * berubah jadi banyak, ketiga kolom ini perlu dipikirkan ulang —
                 * bukan sekadar digabung kembali dengan baris baru.
                 *
                 * PDF sengaja tetap menggabungkannya: ia dibaca, bukan diolah.
                 */
                ['key' => 'p_pica_masalah', 'label' => 'PICA — Identifikasi Masalah', 'width' => 45],
                ['key' => 'p_pica_koreksi', 'label' => 'PICA — Tindakan Koreksi', 'width' => 45],
                ['key' => 'p_pica_tanggal', 'label' => 'PICA — Tanggal Pelaksanaan', 'width' => 16, 'center' => true],
            ], $approval);
        }

        // param_merge=true -> atribut parameter (sama untuk semua periode target)
        // di-merge vertikal lintas baris-target. Periode & Nilai Target TIDAK
        // di-merge agar tiap periode tampil di barisnya sendiri.
        return array_merge($identity, [
            ['key' => 'p_nama', 'label' => 'Parameter', 'width' => 40, 'param_merge' => true],
            ['key' => 'p_deskripsi', 'label' => 'Deskripsi', 'width' => 45, 'param_merge' => true],
            ['key' => 'p_bsc', 'label' => 'BSC', 'width' => 18, 'param_merge' => true],
            ['key' => 'p_bobot', 'label' => 'Bobot', 'width' => 8, 'center' => true, 'param_merge' => true],
            ['key' => 'p_satuan', 'label' => 'Satuan', 'width' => 8, 'center' => true, 'param_merge' => true],
            ['key' => 'p_rumus', 'label' => 'Rumus', 'width' => 25, 'param_merge' => true],
            ['key' => 'p_tipe_target', 'label' => 'Tipe Target', 'width' => 14, 'param_merge' => true],
            ['key' => 'p_periode_target', 'label' => 'Periode Target', 'width' => 14, 'center' => true],
            ['key' => 'p_nilai_target', 'label' => 'Nilai Target', 'width' => 11, 'center' => true],
        ], $approval);
    }

    /**
     * Bangun kolom approval dinamis. Untuk tiap level (1..$maxLevels):
     *   - "Nama Approval Lv{n}" (nama approver + status + tanggal)
     *   - "Catatan Penolakan Lv{n}" (hanya bila $withCatatan, mis. KPI Parameter)
     * Semua di-merge per karyawan (nilai sama lintas baris parameter).
     */
    private function approvalColumns(int $maxLevels, bool $withCatatan): array
    {
        $cols = [];
        for ($lvl = 1; $lvl <= $maxLevels; $lvl++) {
            $cols[] = [
                'key' => "approval_{$lvl}",
                'label' => "Nama Approval Lv{$lvl}",
                'width' => 26,
                'merge' => true,
            ];
            if ($withCatatan) {
                $cols[] = [
                    'key' => "catatan_{$lvl}",
                    'label' => "Catatan Penolakan Lv{$lvl}",
                    'width' => 28,
                    'merge' => true,
                ];
            }
        }

        return $cols;
    }

    /**
     * Sel "Nama Approval Lv{n}": nama approver + status (+ tanggal bila sudah diproses).
     *   "Aji — Disetujui (17 Jun 2026)"
     *   "Verifikator KPI — Menunggu"
     */
    private function approvalNama(array $step): string
    {
        $nama = $step['approver'] ?: '-';
        $status = $step['status'] ?? '-';
        $line = "{$nama} — {$status}";

        if (! empty($step['tanggal']) && $status !== 'Menunggu') {
            try {
                $line .= ' ('.Carbon::parse($step['tanggal'])->isoFormat('D MMM YYYY').')';
            } catch (\Throwable $e) {
                // abaikan tanggal tak valid
            }
        }

        return $line;
    }

    /**
     * Sel "Catatan Penolakan Lv{n}": catatan/alasan approver (umumnya saat ditolak).
     */
    private function approvalCatatan(array $step): string
    {
        return ! empty($step['catatan']) ? trim((string) $step['catatan']) : '';
    }

    /**
     * Baris PICA yang layak ditampilkan untuk satu parameter, atau null.
     *
     * Baris yang KEDUA sisinya kosong atau placeholder dilewati: 1.167 dari
     * 2.766 baris PICA di production berisi '-', '1', atau 'sda', dan "- -> -"
     * tidak memberi tahu pembaca apa pun sambil menutupi baris yang benar-benar
     * berisi. Yang salah satu sisinya berisi TETAP ditampilkan — membuang itu
     * berarti menyembunyikan isian orang.
     *
     * @param  array<string,mixed>|null  $param
     * @return array<string,mixed>|null
     */
    private function picaTerpakai(?array $param): ?array
    {
        $pica = $param['pica'] ?? [];

        if (! is_array($pica)) {
            return null;
        }

        $kosong = static fn ($v) => trim((string) ($v ?? '')) === '' || trim((string) $v) === '-';

        foreach ($pica as $pc) {
            if (! is_array($pc)) {
                continue;
            }
            if ($kosong($pc['identifikasi'] ?? null) && $kosong($pc['koreksi_aksi'] ?? null)) {
                continue;
            }

            return $pc;
        }

        return null;
    }

    /**
     * Resolusi nilai sel per key kolom.
     */
    private function excelCellValue(string $key, array $row, ?array $param, int $no, array $flow, ?array $target = null)
    {
        // Kolom approval dinamis: approval_{n} -> nama+status approver level n,
        // catatan_{n} -> catatan penolakan level n (kosong bila bukan ditolak).
        if (str_starts_with($key, 'approval_') || str_starts_with($key, 'catatan_')) {
            [$kind, $lvl] = explode('_', $key);
            $step = $flow[(int) $lvl - 1] ?? null;
            if (! $step) {
                return '';
            }

            return $kind === 'catatan'
                ? $this->approvalCatatan($step)
                : $this->approvalNama($step);
        }

        // Baris status-saja (karyawan belum/tidak mengisi): tak ada rincian param,
        // tandai sel parameter dgn '-' agar jelas kosong. Total skor juga '-'.
        if ($param === null && str_starts_with($key, 'p_')) {
            return '-';
        }
        if ($param === null && in_array($key, ['total_score', 'total_achievement', 'total_achievement_raw', 'total_bobot'], true)) {
            return ($row[$key] ?? null) === null ? '-' : $row[$key];
        }

        switch ($key) {
            case 'no': return $no;
            case 'total_achievement_raw': return ($row['total_achievement_raw'] ?? null) === null ? '-' : $row['total_achievement_raw'];
            case 'capped': return ! empty($row['is_capped']) ? 'Y ('.$row['cap_min'].'-'.$row['cap_max'].')' : 'T';
                // Parameter pengajuan.
            case 'p_nama': return $param['nama_parameter'] ?? '';
            case 'p_deskripsi': return $param['deskripsi'] ?? '';
            case 'p_bsc': return $param['bsc'] ?? '';
            case 'p_bobot': return $param['bobot'] ?? '';
            case 'p_satuan': return $param['satuan'] ?? '';
            case 'p_rumus': return $param['rumus'] ?? '';
            case 'p_tipe_target': return $param['tipe_target'] ?? '';
            case 'p_periode_target':
                // Parameter pengajuan: pakai target periode yg sedang dirender
                // (satu baris per periode). Performance: periode_target langsung.
                if ($target !== null) {
                    return $target['periode'] ?? '';
                }

                return $param['periode_target'] ?? '';
            case 'p_nilai_target':
                if ($target !== null) {
                    return $target['nilai_target'];
                }

                return $param['nilai_target'] ?? '';
                // Parameter performance (nilai yang diisi user).
            case 'p_actual': return $param['nilai_actual'] ?? '';
            case 'p_score': return $param['score'] ?? '';
            case 'p_achievement': return $param['achievement'] ?? '';
            case 'p_pica_masalah':
            case 'p_pica_koreksi':
            case 'p_pica_tanggal':
                $pc = $this->picaTerpakai($param);
                if ($pc === null) {
                    return '';
                }

                if ($key === 'p_pica_masalah') {
                    return trim((string) ($pc['identifikasi'] ?? ''));
                }
                if ($key === 'p_pica_koreksi') {
                    return trim((string) ($pc['koreksi_aksi'] ?? ''));
                }

                /* Tanggal_Pelaksanaan datang sebagai datetime penuh; apa adanya ia
                   terbaca "2026-09-29 17:00:00.000". Jam dan milidetik tidak pernah
                   punya arti untuk tanggal pelaksanaan, dan dalam kolom tersendiri
                   ia juga menghalangi pengurutan yang wajar. */
                $tgl = $pc['tanggal_pelaksanaan'] ?? null;
                if (empty($tgl)) {
                    return '';
                }

                try {
                    return Carbon::parse($tgl)->toDateString();
                } catch (\Throwable $e) {
                    return (string) $tgl;
                }
            default:
                return $row[$key] ?? '';
        }
    }

    /**
     * Nama file hasil. Bila user memberi nama custom (payload['nama']), pakai itu
     * sebagai nama file (di-sanitasi) + timestamp; jika tidak, pola default.
     */
    private function fileName($job, string $ext): string
    {
        $payload = $this->payload($job);
        $custom = $this->sanitizeFileName($payload['nama'] ?? null);
        if ($custom !== '') {
            return $custom.'_'.now()->format('Ymd_His').'.'.$ext;
        }

        $jenis = ($payload['jenis'] ?? 'parameter') === 'performance' ? 'UpdatePerformance' : 'KPIParameter';
        $scope = $payload['scope_value'] ?? '';

        return "Export_{$jenis}_{$scope}_".now()->format('Ymd_His').'.'.$ext;
    }

    private function scopeLabel(string $jenis, ?string $scope): string
    {
        if (! $scope) {
            return '-';
        }
        // Performance: scope = 'YYYY-MM' -> "Mei 2026". Parameter: scope = tahun.
        if ($jenis === 'performance' && preg_match('/^\d{4}-\d{2}$/', $scope)) {
            return Carbon::parse($scope.'-01')->isoFormat('MMMM YYYY');
        }

        return 'Tahun '.$scope;
    }
}
