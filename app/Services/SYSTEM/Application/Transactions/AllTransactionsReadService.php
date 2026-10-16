<?php

namespace App\Services\SYSTEM\Application\Transactions;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AllTransactionsReadService
 *
 * Pengganti efisien untuk view `N_HRIS_VW_All_Transactions2`.
 *
 * MASALAH view lama: `SELECT *` + UNION ALL 9 view sumber + LEFT JOIN
 * `N_HRIS_VW_Approval_Request_V2` (yang punya 3-4 EXISTS berkorelasi per baris).
 * Setiap pemanggilan memateralisasi SEMUA baris 9 sumber dulu sebelum filter
 * `Kode_Karyawan`/`Current_Approver` bisa diterapkan -> ratusan juta logical reads.
 *
 * PRINSIP service ini:
 *  1. FILTER DI HULU. Setiap akses WAJIB menyaring `Kode_Karyawan`,
 *     `Current_Approver`, atau daftar `No_Transaksi` sebelum union/join,
 *     sehingga index existing (No_Transaksi, Kode_Karyawan, dst) terpakai.
 *  2. OUTPUT IDENTIK. Struktur kolom & logika `Dashboard_Status`/`Approval_Method`
 *     direplikasi PERSIS dari definisi view production. Status approval tetap
 *     bersumber dari `N_HRIS_VW_Approval_Request_V2` + `HRIS_Approval_Request_Detail`
 *     (1 sumber kebenaran), bukan dihitung ulang di PHP.
 *
 * Catatan kesetaraan dengan view production:
 *   FROM (UNION ALL 9 N_HRIS_VW_SRC_* + Karyawan/divisi/jabatan) src
 *   LEFT JOIN N_HRIS_VW_Approval_Request_V2 flow ON flow.No_Transaksi = src.No_Transaksi
 *   LEFT JOIN HRIS_Approval_Request_Detail det
 *          ON det.Id_Approval_Request = flow.Id_Approval_Request
 *         AND det.Source_Row_Key = src.Source_Row_Key
 */
class AllTransactionsReadService
{
    /**
     * Daftar view sumber. Urutan & nama identik dengan UNION ALL di view production.
     *
     * @var array<int,string>
     */
    private const SOURCE_VIEWS = [
        'N_HRIS_VW_SRC_SHIFT',
        'N_HRIS_VW_SRC_STATUS_HARI',
        'N_HRIS_VW_SRC_TUKAR_HARI',
        'N_HRIS_VW_SRC_CUTI',
        'N_HRIS_VW_SRC_SAKIT_IZIN',
        'N_HRIS_VW_SRC_TERLAMBAT_PULANG',
        'N_HRIS_VW_KPI_Parameter',
        'N_HRIS_VW_KPI_Performance',
        'N_HRIS_VW_SRC_KONVERSI_LEMBUR',
    ];

    /**
     * HANYA jenis ini yang gugur karena umur (`services.hcis.batas_approve_izin`):
     * sakit & izin (satu literal `SAKIT_IZIN`), terlambat, dan pulang cepat.
     * Jenis lain (CUTI, SHIFT, STATUS_HARI, TUKAR_HARI, KONVERSI_LEMBUR, KPI_*)
     * tidak pernah gugur karena umur pengajuan. Cocokkan case-insensitive.
     *
     * @var array<int,string>
     */
    public const JENIS_DIBATASI_UMUR = ['SAKIT_IZIN', 'terlambat', 'pulangCepat'];

    /**
     * Kolom yang diekspos, identik dengan view `N_HRIS_VW_All_Transactions2`.
     * Dipakai sebagai default select agar bentuk baris sama dengan view.
     *
     * @var array<int,string>
     */
    private const VIEW_COLUMNS = [
        'src.No_Transaksi',
        'src.Kode_Karyawan',
        'src.Tanggal_Item',
        'src.Jenis',
        'src.Deskripsi',
        'src.Status_Saat_Ini',
        'src.Source_Created_At',
        'src.Source_Created_By',
        'src.Status_Selesai',
        'src.Flag_Admin',
        'src.Json_Context',
        'src.Source_Row_Key',
        'src.Source_View',
        'flow.Id_Approval_Request',
        'flow.Flow_State',
        'flow.Request_Order_Flow',
        'flow.Id_Approval_Config',
        'flow.Id_Jenis_Approval',
        'flow.Nama_Approval',
        'flow.Source_Type',
        'flow.Source_Reference',
        'flow.Current_Approver',
        'flow.Current_Approver_Name',
        'flow.Approver_Target_Type',
        'flow.Flag_Group',
        'flow.Group_Reference_Id',
        'flow.Id_Role',
        'flow.Role_Code',
        'flow.Role_Name',
        'flow.Stop_If_Rejected',
        'flow.Is_Mandatory',
        'flow.Flag_Approval as Flow_Approval_Status',
        'flow.Tanggal_Request',
        'flow.Approved_By_Kode',
        'flow.Approved_By_Nama',
        'flow.Tanggal_Approval',
        'flow.Catatan as Flow_Catatan',
        'flow.Expired_At',
        'det.Flag_Approval as Item_Approval_Status',
        'det.Processed_At',
        'det.Notes as Detail_Notes',
        'src.Karyawan_Nama',
        'src.NIK',
        'src.UserID_Absen',
        'src.UserID_Web',
        'src.nama_divisi',
        'src.nama_jabatan',
    ];

    /**
     * Ekspresi `Approval_Method` & `Dashboard_Status` direplikasi VERBATIM dari view.
     */
    private const APPROVAL_METHOD_SQL = "CASE
        WHEN flow.Id_Approval_Request IS NULL AND src.Status_Selesai = 'Y' THEN 'BYPASS ADMIN'
        WHEN flow.Id_Approval_Request IS NULL AND src.Status_Selesai IS NULL THEN 'DRAFT/MANUAL'
        WHEN flow.Approver_Target_Type = 'ROLE_POOL' THEN 'ROLE POOL'
        ELSE 'NORMAL FLOW'
    END AS Approval_Method";

    private const DASHBOARD_STATUS_SQL = "CASE
        WHEN flow.Id_Approval_Request IS NULL AND src.Status_Selesai = 'Y' THEN 'APPROVED'
        WHEN flow.Id_Approval_Request IS NULL AND src.Status_Selesai IS NULL THEN 'DRAFT'
        WHEN flow.Flow_State = 'CURRENT' THEN 'PENDING'
        WHEN flow.Flow_State = 'HISTORY' AND ISNULL(det.Flag_Approval, flow.Flag_Approval) = 'Y' THEN 'APPROVED'
        WHEN flow.Flow_State = 'HISTORY' AND ISNULL(det.Flag_Approval, flow.Flag_Approval) = 'T' THEN 'REJECTED'
        WHEN flow.Flow_State = 'VOID' THEN 'VOID'
        WHEN flow.Flow_State = 'FUTURE' THEN 'FUTURE'
        WHEN flow.Flow_State = 'EXPIRED' THEN 'EXPIRED'
        ELSE 'UNKNOWN'
    END AS Dashboard_Status";

    /**
     * Bangun subquery `src` = UNION ALL 9 view sumber + join info karyawan,
     * identik dengan inner derived table view production.
     *
     * Filter hulu (Kode_Karyawan / No_Transaksi) diterapkan PER-sumber via $tap
     * sebelum union, supaya predikat ter-pushdown ke tiap view sumber.
     *
     * @param  callable(Builder):void|null  $tap  filter yang diterapkan ke tiap sumber
     */
    private function sourceUnion(?callable $tap = null): Builder
    {
        $union = null;

        foreach (self::SOURCE_VIEWS as $view) {
            // $view berasal dari konstanta internal (bukan input user) -> aman sebagai literal.
            // Pakai literal, bukan binding `?`, agar urutan binding union tidak bergeser.
            $q = DB::table($view)
                ->selectRaw("*, '{$view}' AS Source_View");

            if ($tap !== null) {
                $tap($q);
            }

            $union = $union === null ? $q : $union->unionAll($q);
        }

        // s = hasil union; bungkus dengan join Karyawan/divisi/jabatan (sama spt view).
        // View sumber mengeluarkan kolom `Created_At`; view production meng-alias-nya
        // jadi `Source_Created_At` di SELECT luar -> kita lakukan hal yang sama di sini.
        return DB::query()
            ->fromSub($union, 's')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 's.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->selectRaw('s.*, s.Created_At AS Source_Created_At, k.Nama AS Karyawan_Nama, k.NIK, k.UserID_Absen, k.UserID_Web, vd.nama_divisi, vg.nama_jabatan');
    }

    /**
     * Base builder yang setara dengan SELECT dari view `N_HRIS_VW_All_Transactions2`.
     *
     * @param  callable(Builder):void|null  $tap  filter hulu per-sumber
     */
    private function baseQuery(?callable $tap = null): Builder
    {
        return DB::query()
            ->fromSub($this->sourceUnion($tap), 'src')
            ->leftJoin('N_HRIS_VW_Approval_Request_V2 as flow', 'flow.No_Transaksi', '=', 'src.No_Transaksi')
            ->leftJoin('HRIS_Approval_Request_Detail as det', function ($join) {
                $join->on('det.Id_Approval_Request', '=', 'flow.Id_Approval_Request')
                    ->on('det.Source_Row_Key', '=', 'src.Source_Row_Key');
            })
            ->select(self::VIEW_COLUMNS)
            ->selectRaw(self::APPROVAL_METHOD_SQL)
            ->selectRaw(self::DASHBOARD_STATUS_SQL);
    }

    /**
     * Bucket pembulatan jumlah elemen `whereIn('Kode_Karyawan', ...)`.
     *
     * SQL Server membuat plan TERPISAH untuk tiap jumlah parameter IN yang berbeda
     * (51 member -> plan A, 52 -> plan B, dst). Daftar member leader yang panjangnya
     * selalu berubah -> ledakan variasi plan + recompile (compile time ~350ms dari
     * 600ms total pada query daftar-besar). Membulatkan jumlah elemen ke kelipatan
     * tetap memangkas variasi plan jadi sedikit "bucket" saja.
     */
    private const KODE_LIST_BUCKET = 50;

    /**
     * Bulatkan jumlah elemen ke atas ke kelipatan KODE_LIST_BUCKET dengan MENGULANG
     * elemen terakhir. Aman secara hasil: duplikat di dalam `IN (...)` tidak mengubah
     * baris yang cocok (IN = pengujian keanggotaan). Daftar diasumsikan sudah
     * di-unique + di-filter oleh pemanggil. Daftar kosong dibiarkan apa adanya.
     *
     * @param  array<int,string>  $kodeList
     * @return array<int,string>
     */
    private function padKodeList(array $kodeList): array
    {
        $count = count($kodeList);
        if ($count === 0) {
            return $kodeList;
        }

        $target = (int) (ceil($count / self::KODE_LIST_BUCKET) * self::KODE_LIST_BUCKET);
        if ($target <= $count) {
            return $kodeList;
        }

        $last = $kodeList[$count - 1];

        return array_merge($kodeList, array_fill(0, $target - $count, $last));
    }

    /**
     * AUDIT SEMENTARA: catat tiap akses ke service (per method + rentang/filter)
     * untuk mengukur berapa kali union-9-view disentuh per homepage load. HAPUS
     * setelah analisis. Tidak menjalankan query — hanya menandai pemanggilan.
     */
    private function auditTouch(string $method, array $ctx = []): void
    {
        if (! config('homepage_audit.enabled', false)) {
            return;
        }
        \Illuminate\Support\Facades\Log::channel('homepageLog')->info('AUDIT alltrx '.$method, array_merge([
            'method' => $method,
            'endpoint' => request()?->path(),
        ], $ctx));
    }

    /**
     * Akses umum: query setara view dengan filter hulu by Kode_Karyawan.
     * Pemanggil dapat melanjutkan chaining (where Dashboard_Status, dst) lalu ->get().
     */
    public function forRequester(string $kodeKaryawan): Builder
    {
        $this->auditTouch('forRequester', ['kode' => $kodeKaryawan]);

        return $this->baseQuery(function (Builder $q) use ($kodeKaryawan) {
            $q->where('Kode_Karyawan', $kodeKaryawan);
        });
    }

    /**
     * Akses umum untuk banyak karyawan (mis. leader: daftar member).
     *
     * @param  array<int,string>  $kodeList
     */
    public function forRequesters(array $kodeList): Builder
    {
        $kodeList = $this->padKodeList(array_values(array_filter(array_unique($kodeList))));

        return $this->baseQuery(function (Builder $q) use ($kodeList) {
            $q->whereIn('Kode_Karyawan', $kodeList ?: ['__none__']);
        });
    }

    /**
     * Sama dengan forRequester(), tetapi rentang `Tanggal_Item` di-PUSHDOWN ke tiap
     * view sumber (sebelum union + join approval), bukan difilter di hilir.
     *
     * Filter hilir `WHERE Tanggal_Item BETWEEN` / `CAST(Tanggal_Item AS DATE)` memaksa
     * union 9 view + join N_HRIS_VW_Approval_Request_V2 (EXISTS berkorelasi) ter-materialize
     * PENUH dulu -> CPU/logical-reads meledak. Push ke hulu = baris dipangkas di tiap view
     * (pakai index), join approval hanya menyentuh sisa yang relevan.
     *
     * Catatan semantik: meniru PERSIS `whereBetween('Tanggal_Item', [from, to])` lama
     * (inklusif kedua ujung: `>= from AND <= to`), supaya hasil identik dengan perilaku
     * sebelum optimasi. Predikat tetap sargable (range, bukan CAST(... AS DATE)) sehingga
     * index pada kolom tanggal tetap terpakai. $from/$to format 'Y-m-d'.
     *
     * PERINGATAN: sebagian view sumber (KPI_Parameter/KPI_Performance) bertipe DATETIME.
     * `<= to` mempertahankan perilaku lama (baris berjam di tanggal `to` tetap terlewat).
     * Mengubah ini ke `< to+1hari` akan mengubah hasil untuk baris tsb -> JANGAN dilakukan
     * di sini; itu perubahan semantik terpisah yang harus diuji tersendiri.
     */
    public function forRequesterBetween(string $kodeKaryawan, string $from, string $to): Builder
    {
        $this->auditTouch('forRequesterBetween', ['kode' => $kodeKaryawan, 'from' => $from, 'to' => $to]);

        return $this->baseQuery(function (Builder $q) use ($kodeKaryawan, $from, $to) {
            $q->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Tanggal_Item', '>=', $from)
                ->where('Tanggal_Item', '<=', $to);
        });
    }

    /**
     * Versi banyak karyawan dari forRequesterBetween(). Semantik tanggal identik
     * dengan whereBetween lama (lihat catatan di forRequesterBetween).
     *
     * @param  array<int,string>  $kodeList
     */
    public function forRequestersBetween(array $kodeList, string $from, string $to): Builder
    {
        $kodeList = $this->padKodeList(array_values(array_filter(array_unique($kodeList))));

        return $this->baseQuery(function (Builder $q) use ($kodeList, $from, $to) {
            $q->whereIn('Kode_Karyawan', $kodeList ?: ['__none__'])
                ->where('Tanggal_Item', '>=', $from)
                ->where('Tanggal_Item', '<=', $to);
        });
    }

    /**
     * Akses per-approver. Filter `Current_Approver` di hulu lewat approval flow:
     * batasi union ke No_Transaksi yang flow-nya menunjuk approver ini.
     */
    public function forApprover(string $kodeApprover): Builder
    {
        $this->auditTouch('forApprover', ['approver' => $kodeApprover]);
        // Pushdown: batasi union ke No_Transaksi yang punya langkah flow milik approver ini
        // (cepat, pakai index). Lalu filter akhir `flow.Current_Approver` di level baris
        // supaya hasil identik dengan view (view memfilter per-baris/per-langkah, bukan
        // per-transaksi — 1 transaksi multi-step bisa punya beberapa Current_Approver).
        $noTransaksiSub = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('Current_Approver', $kodeApprover)
            ->select('No_Transaksi');

        return $this->baseQuery(function (Builder $q) use ($noTransaksiSub) {
            $q->whereIn('No_Transaksi', $noTransaksiSub);
        })->where('flow.Current_Approver', $kodeApprover);
    }

    /**
     * Himpunan baris pengajuan yang MENUNGGU giliran approver ini.
     *
     * SATU-SATUNYA definisi "pending approval" — dipakai badge homepage
     * (UDashReadModelService::countPendingApprovalsV2) DAN antrian Leader Dashboard
     * (HomepageLeaderDashboardService::loadGroupedApprovalQueueRows) supaya kedua
     * angka tak pernah berbeda. Belum di-group: caller yang menentukan satuannya
     * (per No_Transaksi untuk badge/antrian, per Kode_Karyawan untuk badge anggota).
     *
     * Kolom bantu `Is_Item_Expired` (1 = item ini di-expire sistem lewat
     * Flag_Approval='P') dibawa ke luar supaya caller bisa mengagregasi expired
     * per transaksi: transaksi baru dianggap expired kalau SEMUA itemnya 'P'
     * (pakai MIN(Is_Item_Expired) saat grouping).
     */
    public function pendingForApprover(string $approverKode): Builder
    {
        $cutoff = Carbon::now()
            ->subDays((int) config('services.hcis.batas_approve_izin'))
            ->toDateTimeString();

        return DB::query()
            ->fromSub($this->forApprover($approverKode), 'tx')
            ->where('Dashboard_Status', 'PENDING')
            ->where('Flow_State', 'CURRENT')
            ->where('Current_Approver', $approverKode)
            // Jendela umur HANYA membatasi SAKIT_IZIN/terlambat/pulangCepat.
            ->where(function ($q) use ($cutoff) {
                $q->whereNotIn('Jenis', self::JENIS_DIBATASI_UMUR)
                    ->orWhere('Source_Created_At', '>=', $cutoff);
            })
            // Buang pengajuan yang OTORITATIF sudah expired via Expired_At lewat waktu.
            // Expired via Flag_Approval='P' ditandai lewat Is_Item_Expired (diagregasi caller).
            ->where(function ($q) {
                $q->whereNull('Expired_At')
                    ->orWhere('Expired_At', '>', Carbon::now()->toDateTimeString());
            })
            ->selectRaw(
                "tx.*, CASE WHEN COALESCE(Item_Approval_Status, Flow_Approval_Status, '') = 'P'
                    THEN 1 ELSE 0 END AS Is_Item_Expired",
            );
    }

    /**
     * Akses per-approver berdasarkan siapa yang sudah memproses (Approved_By_Kode).
     */
    public function forProcessedBy(string $kodeApprover): Builder
    {
        $noTransaksiSub = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('Approved_By_Kode', $kodeApprover)
            ->select('No_Transaksi');

        return $this->baseQuery(function (Builder $q) use ($noTransaksiSub) {
            $q->whereIn('No_Transaksi', $noTransaksiSub);
        })->where('flow.Approved_By_Kode', $kodeApprover);
    }

    /**
     * Akses gabungan: transaksi yang approver ini sebagai Current_Approver ATAU
     * sebagai Approved_By_Kode (mis. team summary approval). Filter di hulu via
     * daftar No_Transaksi yang flow-nya menyangkut approver ini di salah satu sisi.
     */
    public function forApproverOrProcessedBy(string $kodeApprover): Builder
    {
        $noTransaksiSub = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where(function ($q) use ($kodeApprover) {
                $q->where('Current_Approver', $kodeApprover)
                    ->orWhere('Approved_By_Kode', $kodeApprover);
            })
            ->select('No_Transaksi');

        return $this->baseQuery(function (Builder $q) use ($noTransaksiSub) {
            $q->whereIn('No_Transaksi', $noTransaksiSub);
        });
    }

    /**
     * Status per-langkah untuk daftar No_Transaksi (untuk getFullyApprovedTransactionMap dsb).
     * Mengembalikan SEMUA baris per-No_Transaksi (tidak di-collapse).
     *
     * @param  array<int,string>  $noTransaksiList
     */
    public function statusByNoTransaksi(array $noTransaksiList): Collection
    {
        $noTransaksiList = array_values(array_filter(array_unique($noTransaksiList)));

        if (empty($noTransaksiList)) {
            return collect();
        }

        $this->auditTouch('statusByNoTransaksi', ['count' => count($noTransaksiList)]);

        // Dipanggil dari banyak tempat (UDash, approval atasan, absensi) dengan daftar
        // No_Transaksi yang sering berulang -> tanpa cache, query union-9-view (WHERE
        // No_Transaksi IN ...) jalan fresh tiap kali = penyumbang CPU. Cache pendek per
        // daftar (di-hash agar key stabil). Status approval bisa berubah, jadi TTL pendek.
        sort($noTransaksiList);
        $cacheKey = 'alltrx.status-by-no.v1:'.sha1(implode(',', $noTransaksiList));

        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);
        if ($cached instanceof Collection) {
            return $cached;
        }

        $result = $this->baseQuery(function (Builder $q) use ($noTransaksiList) {
            $q->whereIn('No_Transaksi', $noTransaksiList);
        })->get();

        \Illuminate\Support\Facades\Cache::put($cacheKey, $result, self::STATUS_BY_NO_CACHE_TTL);

        return $result;
    }

    /** TTL cache statusByNoTransaksi (detik). Pendek karena status approval bisa berubah. */
    private const STATUS_BY_NO_CACHE_TTL = 45;
}
