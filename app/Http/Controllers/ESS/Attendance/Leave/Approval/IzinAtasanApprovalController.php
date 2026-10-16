<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Approval;

use App\Helpers\ResponseHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Leave\Shared\TanggalTransaksiIzin;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use App\Services\SYSTEM\Governance\Approval\Shared\AturanKedaluwarsaApproval;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class IzinAtasanApprovalController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    public function index()
    {
        return inertia('ESS/Attendance/Leave/Izin/IzinAtasanApproval');
    }

    // public function getData(Request $request)
    // {
    //     //  {
    //     //             id: 1,
    //     //             nama_karyawan: "Budi Gunawan",
    //     //             divisi: "Teknologi",
    //     //             jenis_izin: "Cuti",
    //     //             tanggal_mulai: "2025-11-20",
    //     //             tanggal_selesai: "2025-11-21",
    //     //             jumlah_hari: 2,
    //     //             alasan: "Acara keluarga di luar kotaasdaasdasdsadasdasdasdasdasdsdasdasdasdasdasdasdasdasdklklklklklklklklklklkllklklklklklklklklklklklklklklkklkkklklklklkllkl.",
    //     //             status: "pending",
    //     //             created_at: "2025-11-15T10:00:00",
    //     //             file_pendukung: null,
    //     //             approval_flow: [
    //     //                 {
    //     //                     name: "Ahmad Subarjo",
    //     //                     status: "Y",
    //     //                     date: "2025-11-15T11:00:00",
    //     //                 },
    //     //                 {
    //     //                     name: "Anda",
    //     //                     status: "N",
    //     //                     date: null,
    //     //                 },
    //     //                 {
    //     //                     name: "HC Department",
    //     //                     status: "N",
    //     //                     date: null,
    //     //                 },
    //     //             ],
    //     //         },
    //     try {
    //         $validated = $request->validate([
    //             'search' => ['nullable', 'string', 'max:100'],
    //             'tipeFilter' => ['nullable', 'string', 'max:100'],
    //             'tanggalFilter' => ['nullable', 'string', 'max:100'],
    //             'page' => ['nullable', 'integer', 'min:1'],
    //         ]);

    //         $search = strip_tags($validated['search'] ?? '');
    //         $perPage = 10;
    //         $page = $validated['page'] ?? 1;
    //         $offset = ($page - 1) * $perPage;
    //         $searchColoms = [
    //             'Nama',
    //             'No_Transaksi',
    //             'Alasan',
    //             'Tipe_Izin'
    //         ];
    //         $tipeFilter = $validated['tipeFilter'] ?? null;
    //         $tanggalFilter = $validated['tanggalFilter'] ?? null;
    //         $karyawan_Auth = Auth::user()->karyawan->Kode_Karyawan;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
    //         // dd($tipeFilter, $tanggalFilter);
    //         $baseQuery = "
    //            WITH
    //                     cte AS (
    //                       SELECT
    //                         CAST(CONCAT(CAST(cti.Tanggal AS DATE),' ', cti.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         ct.No_Transaksi,
    //                         ct.Jenis as Tipe_Izin,
    //                         ct.Tanggal_Cuti_Dari AS Tanggal_Mulai,
    //                         ct.Tanggal_Cuti_Sampai AS Tanggal_Selesai,
    //                         ct.Alasan,
    //                         ct.filePath as Lampiran,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi
    //                                 AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = ct.No_Transaksi
    //                                 and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is NULL
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN ct.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN ct.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN ct.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN ct.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN ct.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN ct.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         NULL AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = ct.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                             --and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Cuti_Detail AS ct ON k.Kode_Karyawan = ct.Kode_Karyawan
    //                         INNER JOIN Transaksi_Cuti AS cti ON ct.No_Transaksi = cti.No_Transaksi
    //                         WHERE
    //                         k.Kode_Karyawan = ?
    //                         and cti.Status is null
    //                         UNION ALL
    //                         -- Query untuk Transaksi_Sakit_Izin_Detail
    //                         SELECT
    //                         CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         tsi.No_Transaksi,
    //                         tsi.Jenis as Tipe_Izin,
    //                         tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
    //                         tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
    //                         tsi.Alasan,
    //                         tsi.filePath as Lampiran,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND af.Status is null
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = tsi.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is Null
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN tsi.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         NULL AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = tsi.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                            -- and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
    //                         INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
    //                         WHERE
    //                         k.Kode_Karyawan = ?
    //                         and tsip.Status is null
    //                         UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
    //                         SELECT
    //                         CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         ttp.No_Transaksi,
    //                         ttp.Jenis as Tipe_Izin,
    //                         ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
    //                         NULL AS Tanggal_Selesai,
    //                         ttp.Alasan,
    //                         ttp.filePath as Lampiran,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = ttp.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is NULL
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN ttp.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 ---AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         ttp.Jam AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = ttp.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                             --and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
    //                         WHERE
    //                         k.Kode_Karyawan = ? and ttpp.Status is null
    //                     )
    //                     SELECT
    //                     *
    //                     FROM
    //                     cte
    //                     where
    //                     MaxFlow <> 0
    //         ";

    //         $queryCount = "
    //              WITH
    //                     cte AS (
    //                       SELECT
    //                         CAST(CONCAT(CAST(cti.Tanggal AS DATE),' ', cti.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         ct.No_Transaksi,
    //                         ct.Jenis as Tipe_Izin,
    //                         ct.Tanggal_Cuti_Dari AS Tanggal_Mulai,
    //                         ct.Tanggal_Cuti_Sampai AS Tanggal_Selesai,
    //                         ct.Alasan,
    //                         ct.filePath as Lampiran,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi
    //                                 AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = ct.No_Transaksi
    //                                 and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is NULL
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN ct.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN ct.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN ct.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN ct.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN ct.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN ct.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = ct.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         NULL AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = ct.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                             --and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Cuti_Detail AS ct ON k.Kode_Karyawan = ct.Kode_Karyawan
    //                         INNER JOIN Transaksi_Cuti AS cti ON ct.No_Transaksi = cti.No_Transaksi
    //                         WHERE
    //                         k.Kode_Karyawan = ?
    //                         and cti.Status is null
    //                         UNION ALL
    //                         -- Query untuk Transaksi_Sakit_Izin_Detail
    //                         SELECT
    //                         CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         tsi.No_Transaksi,
    //                         tsi.Jenis as Tipe_Izin,
    //                         tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
    //                         tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
    //                         tsi.Alasan,
    //                         tsi.filePath as Lampiran,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND af.Status is null
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = tsi.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is Null
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN tsi.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN tsi.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = tsi.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         NULL AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = tsi.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                            -- and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
    //                         INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
    //                         WHERE
    //                         k.Kode_Karyawan = ?
    //                         and tsip.Status is null
    //                         UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
    //                         SELECT
    //                         CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
    //                         k.Kode_Karyawan,
    //                         k.Nama,
    //                         ttp.No_Transaksi,
    //                         ttp.Jenis as Tipe_Izin,
    //                         ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
    //                         NULL AS Tanggal_Selesai,
    //                         ttp.Alasan,
    //                         ttp.filePath as Lampiran,
    //                         ISNULL(
    //                             (
    //                             SELECT
    //                                 TOP 1 af.order_flow
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NOT NULL
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow DESC
    //                             ),
    //                             0
    //                         ) as currentFlow,
    //                         ISNULL(
    //                             (
    //                             select
    //                                 top 1 order_flow
    //                             from
    //                                 HRIS_Approval_Flow a,
    //                                 HRIS_Approval_Request b
    //                             where
    //                                 a.id = b.Flow_Id
    //                                 and b.No_Transaksi = ttp.No_Transaksi
    // 								and a.Kode_Karyawan_Requester = b.Kode_Karyawan
    //                                 --and a.Status is NULL
    //                             order by
    //                                 a.order_flow desc
    //                             ),
    //                             0
    //                         ) as MaxFlow,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
    //                             WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Ditolak'
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 'Diproses'
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    //                                 AND ar.Kode_Karyawan = k.Kode_Karyawan
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 --AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             'NO'
    //                             )
    //                         END as status,
    //                         CASE
    //                             WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                             WHEN ttp.Flag_Approval = 'P' THEN 'Sistem'
    //                             WHEN ttp.Flag_Approval = 'T' THEN (
    //                             select
    //                                 top 1 af.Kode_Karyawan
    //                             FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                             WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi

    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval = 'T'
    //                                 --AND af.Status is NULL
    //                             ORDER BY
    //                                 af.order_flow
    //                             )
    //                             ELSE ISNULL(
    //                             (
    //                                 SELECT
    //                                 TOP 1 ka.Nama
    //                                 FROM
    //                                 HRIS_Approval_Request ar
    //                                 JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 JOIN
    //                                     Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                 WHERE
    //                                 ar.No_Transaksi = ttp.No_Transaksi
    // 								AND ar.Kode_Karyawan = af.Kode_Karyawan_Requester
    //                                 AND ar.Flag_Approval IS NULL
    //                                 ---AND af.Status is NULL
    //                                 ORDER BY
    //                                 af.order_flow
    //                             ),
    //                             null
    //                             )
    //                         END AS Approver,
    //                         ttp.Jam AS Jam_Mulai,
    //                         (
    //                             select
    //                             STRING_AGG(
    //                                 CONCAT(
    //                                 b.Kode_Karyawan,
    //                                 ': ',
    //                                 CASE
    //                                     WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                     ELSE 'N'
    //                                 END
    //                                 ),
    //                                 ', '
    //                             ) WITHIN GROUP (
    //                                 ORDER BY
    //                                 b.order_flow
    //                             ) AS StatusList
    //                             from
    //                             HRIS_Approval_Request a
    //                             JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                             where
    //                             a.No_Transaksi = ttp.No_Transaksi
    // 							and a.Kode_Karyawan = b.Kode_Karyawan_Requester
    //                             --and b.Status is NULL
    //                         ) AS STATUSLIST
    //                         FROM
    //                         Karyawan AS k
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
    //                         INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
    //                         WHERE
    //                         k.Kode_Karyawan = ? and ttpp.Status is null
    //                     )
    //                     SELECT
    //                     count(No_Transaksi) as total
    //                     FROM
    //                     cte
    //                     where
    //                     MaxFlow <> 0
    //         ";

    //         $params = [];
    //         $params[] = $karyawan_Auth ;
    //         $params[] = $karyawan_Auth ;
    //         $params[] = $karyawan_Auth ;
    //         if($search){
    //             $searchParam = '%' . $search .'%';
    //             $likeParts = [];
    //             foreach($searchColoms as $col){
    //                 $likeParts[]= "$col Like ?";
    //                 $params[] = $searchParam;
    //             }

    //             $likeQuery = implode(' OR ', $likeParts);
    //             $queryCount .= " AND ($likeQuery) ";
    //             $baseQuery .= " AND ($likeQuery) ";

    //         }

    //         if($tipeFilter){
    //             $queryCount .= " AND Tipe_Izin = ?";
    //             $baseQuery .= " AND Tipe_Izin = ?";
    //             $params[] = $tipeFilter;
    //         }

    //         if ($tanggalFilter) {
    //             $queryCount .= " AND (
    //                 cast(? as date) between cast(Tanggal_Mulai as date)
    //                 and cast(coalesce(Tanggal_Selesai, Tanggal_Mulai) as date)
    //             )";
    //             $baseQuery .= " AND (
    //                 cast(? as date) between cast(Tanggal_Mulai as date)
    //                 and cast(coalesce(Tanggal_Selesai, Tanggal_Mulai) as date)
    //             )";

    //             $params[] = $tanggalFilter;
    //         }

    //         $total = DB::selectOne($queryCount, $params)->total;

    //         $queryData = $baseQuery . " ORDER BY tanggal_create DESC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

    //         $params[]=$offset;
    //         $params[]=$perPage;

    //         $result = DB::select($queryData, $params);

    //         $dataFinal = collect($result)->map(function ($item) {
    //             if (!empty($item->Kode_Karyawan)) {
    //                 $item->Kode_Karyawan = Crypt::encryptString($item->Kode_Karyawan);
    //             }
    //             return $item;
    //         });

    //         return ResponseHelper::successWithPaginationV2(
    //             $dataFinal,
    //             $page,
    //             $perPage,
    //             $total,
    //             "Riwayat cuti berhasil diambil.",
    //             200
    //         );
    //     }catch (\Exception $e) {
    //         Log::channel('izinAtasanApprovalLog')->error('ERROR GETDATA: ' . $e->getMessage());
    //         return ResponseHelper::error("Terjadi Kesalahan", 500);
    //     }
    // }

    // public function getData(Request $request)
    // {
    //  {
    //             id: 1,
    //             nama_karyawan: "Budi Gunawan",
    //             divisi: "Teknologi",
    //             jenis_izin: "Cuti",
    //             tanggal_mulai: "2025-11-20",
    //             tanggal_selesai: "2025-11-21",
    //             jumlah_hari: 2,
    //             alasan: "Acara keluarga di luar kotaasdaasdasdsadasdasdasdasdasdsdasdasdasdasdasdasdasdasdklklklklklklklklklklkllklklklklklklklklklklklklklklkklkkklklklklkllkl.",
    //             status: "pending",
    //             created_at: "2025-11-15T10:00:00",
    //             file_pendukung: null,
    //             approval_flow: [
    //                 {
    //                     name: "Ahmad Subarjo",
    //                     status: "Y",
    //                     date: "2025-11-15T11:00:00",
    //                 },
    //                 {
    //                     name: "Anda",
    //                     status: "N",
    //                     date: null,
    //                 },
    //                 {
    //                     name: "HC Department",
    //                     status: "N",
    //                     date: null,
    //                 },
    //             ],
    //         },
    // try {
    //     $pengguna = Auth::user()->karyawan;
    //     $validated = $request->validate([
    //         'search' => ['nullable', 'string', 'max:100'],
    //         'status' => ['nullable', 'string', 'max:100'],
    //         'jenis_cuti' => ['nullable', 'string', 'max:100'],
    //         'page' => ['nullable', 'integer', 'min:1'],
    //     ]);

    //     $search = strip_tags($validated['search'] ?? '');
    //     $status = $validated['status'] ?? '';
    //     $jenis_cuti = $validated['jenis_cuti'] ?? '';
    //     $limit = 10;
    //     $page = $validated['page'] ?? 1;
    //     $offset = ($page - 1) * $limit;
    //     $searchColoms = [
    //         'nama_karyawan',
    //         'No_Transaksi',
    //         'jenis_cuti'
    //     ];

    // CTE untuk menemukan transaksi yang relevan untuk HC Ops
    // $eligibleTransactionsCte = "
    //     WITH RelevantTransactions AS (
    //         SELECT
    //             ar.No_Transaksi,
    //             ar.Request_Order_Flow AS OrderFlow
    //         FROM HRIS_Approval_Request ar
    //         WHERE ar.Flag_Group = 'Y'
    //           AND ar.Approver_Karyawan_Kode = ?
    //           AND ar.Flag_Approval IS NULL -- Yang masih pending di HC Ops
    //     ),
    //     ApprovalStatus AS (
    //         SELECT
    //             No_Transaksi,
    //             -- Cek apakah semua flow SEBELUM HC Ops sudah di-approve
    //             CASE
    //                 WHEN NOT EXISTS (
    //                     SELECT 1
    //                     FROM HRIS_Approval_Request ar_prev
    //                     WHERE ar_prev.No_Transaksi = rt.No_Transaksi
    //                       AND ar_prev.Request_Order_Flow < rt.OrderFlow
    //                       AND (ar_prev.Flag_Approval IS NULL OR ar_prev.Flag_Approval = 'T')
    //                 ) THEN 1
    //                 ELSE 0
    //             END AS IsHcOpsTurn
    //         FROM RelevantTransactions rt
    //     )
    //     SELECT ast.No_Transaksi
    //     FROM ApprovalStatus ast
    //     WHERE ast.IsHcOpsTurn = 1
    // ";

    // $eligibleTransactionsCte = "
    // WITH RelevantTransactions AS (
    //     SELECT
    //         req.id,
    //         req.No_Transaksi,
    //         --req.Source_Type,
    //         req.Flag_Approval,
    //         COALESCE(
    //             -- Prioritas 1: Cek jika Direct Approval (Kode Karyawan langsung di tabel request)
    //             karyawan_direct.Nama,
    //             -- Prioritas 2: Cek jika Legacy Flow (Ambil dari tabel Flow lama ->Join Karyawan)
    //             karyawan_legacy.Nama
    //         ) AS Nama_Approver_Saat_Ini,
    //         req.Request_Order_Flow AS OrderFlow
    //     FROM
    //         HRIS_Approval_Requests req
    //         LEFT JOIN HRIS_Approval_Flow flow_lama ON req.Flow_Id = flow_lama.id
    //         LEFT JOIN Karyawan karyawan_legacy ON flow_lama.Kode_Karyawan = karyawan_legacy.Kode_Karyawan
    //         -- 2.JOIN UNTUK DATA BARU (DIRECT)
    //         LEFT JOIN Karyawan karyawan_direct ON req.Approver_Karyawan_Kode = karyawan_direct.Kode_Karyawan
    //     WHERE
    //         -- Filter untuk menampilkan request milik user yang sedang login
    //         (
    //             -- User ada di Legacy Flow
    //             flow_lama.Kode_Karyawan = ? -- ATAU User adalah Direct Approver
    //             OR req.Approver_Karyawan_Kode = ?
    //         )
    // ),
    // ApprovalStatus AS (
    //     SELECT
    //         No_Transaksi,
    //         -- Cek apakah semua flow SEBELUM sudah di - approve
    //         CASE
    //             WHEN NOT EXISTS (
    //                 SELECT
    //                     1
    //                 FROM
    //                     HRIS_Approval_Request ar_prev
    //                 WHERE
    //                     ar_prev.No_Transaksi = rt.No_Transaksi
    //                     AND ar_prev.Request_Order_Flow < rt.OrderFlow
    //                     AND (
    //                         ar_prev.Flag_Approval IS NULL
    //                         OR ar_prev.Flag_Approval = 'T'
    //                     )
    //             ) THEN 1
    //             ELSE 0
    //         END AS IsTurn
    //     FROM
    //         RelevantTransactions rt
    // )
    // SELECT
    //     ast.No_Transaksi
    // FROM
    //     ApprovalStatus ast
    // WHERE
    //     ast.IsTurn = 1
    // ";

    // $eligibleTransactions = collect(DB::select($eligibleTransactionsCte, [$pengguna->Kode_Karyawan, $pengguna->Kode_Karyawan]))->pluck('No_Transaksi');

    //         $approvers = DB::table('N_HRIS_VW_Approval_Request')
    //                                 ->whereNull('Flag_Approval')
    //                                 ->where('current_Approver_Kode', $pengguna->Kode_Karyawan)
    //                                 ->get();
    //         $eligibleTransactions = $approvers->pluck('No_Transaksi');
    //         if ($eligibleTransactions->isEmpty()) {
    //             return ResponseHelper::error(null, "Tidak ada pengajuan yang memerlukan persetujuan.", 200);
    //         }
    //         // dd($eligibleTransactions);
    //         // CTE untuk mengambil semua data yang dibutuhkan sesuai dengan no transaksi yang relevan
    //         $dataCte = DB::table('N_HRIS_VW_All_Transactions')
    //                     ->whereIn('No_Transaksi', $eligibleTransactions);

    //         if ($search) {
    //             $searchParam = '%' . $search . '%';

    //             $dataCte->where(function($query) use ($searchColoms, $searchParam) {
    //                 foreach ($searchColoms as $col) {
    //                     $query->orWhere($col, 'like', $searchParam);
    //                 }
    //             });
    //         }

    //         if ($status) {
    //             $dataCte->where('status', $status);
    //         }

    //         if ($jenis_cuti) {
    //             $dataCte->where('jenis_cuti', $jenis_cuti);
    //         }

    //         $countQuery = clone $dataCte;
    //         $total = $countQuery->count();

    //         $dataQuery = $dataCte
    //         ->orderBy('Created_At', 'DESC')
    //         ->offset($offset)
    //         ->limit($limit);

    //         $pengajuanList = $dataQuery
    //         ->get()
    //         ->map(function ($item) use ($approvers) {

    //         // Ambil semua approval berdasarkan transaksi
    //         $approverRows = $approvers
    //             ->where('No_Transaksi', $item->No_Transaksi)
    //             ->sortBy('Request_Order_Flow'); // kalau ingin urut

    //         // Bentuk approval_flow seperti frontend
    //         $approvalFlow = $approverRows->map(function ($row) {
    //             $namaApprover = DB::table('Karyawan')
    //                 ->where('Kode_Karyawan', $row->Current_Approver_Kode)
    //                 ->first();
    //             return [
    //                 'name'   => $namaApprover ? $namaApprover->Nama : 'Tidak diketahui..',
    //                 'status' => $this->parseStatusRequest($row->Flag_Approval),
    //                 'date'   => $row->Tanggal_Approved ?? null,
    //             ];
    //         })->toArray();

    //         // Bentuk struktur output akhir
    //         return [
    //             'id'               => $item->No_Transaksi,
    //             'nama_karyawan'    => $item->Nama_Requester ?? '-',
    //             'divisi'           => $item->Nama_Divisi_Requester ?? '-',
    //             'jenis_izin'       => $this->parseSubTipe($item->Sub_Tipe),
    //             'tanggal_mulai'    => $item->Tanggal_Mulai,
    //             'tanggal_selesai'  => $item->Tanggal_Selesai,
    //             'jumlah_hari'      => isset($item->Tanggal_Mulai) && isset($item->Tanggal_Selesai)
    //                                     ? Carbon::parse($item->Tanggal_Mulai)->diffInDays($item->Tanggal_Selesai) + 1
    //                                     : null,
    //             'alasan'           => $item->Deskripsi,
    //             'status'           => $this->parseStatusRequest($item->Flag_Approval),
    //             'created_at'       => $item->Created_At,
    //             'file_pendukung'   => $item->File_Path,
    //             'approval_flow'    => $approvalFlow,
    //         ];

    //         });

    //         // dd($pengajuanList);
    //         return ResponseHelper::successWithPaginationV2(
    //             $pengajuanList,
    //             $page,
    //             $limit,
    //             $total,
    //             "Data pengajuan berhasil diambil.",
    //             200
    //         );
    //     }catch (\Exception $e) {
    //         Log::channel('izinAtasanApprovalLog')->error('ERROR GETDATA: ' . $e->getMessage());
    //         return ResponseHelper::error("Terjadi Kesalahan", 500);
    //     }
    // }

    // public function getData2(Request $request)
    // {
    //     try {
    //         $pengguna = Auth::user()->karyawan;
    //         $validated = $request->validate([
    //             'search' => ['nullable', 'string', 'max:100'],
    //             'status' => ['nullable', 'string', 'max:100'],
    //             'jenis_cuti' => ['nullable', 'string', 'max:100'],
    //             'page' => ['nullable', 'integer', 'min:1'],
    //         ]);

    //         $search = strip_tags($validated['search'] ?? '');
    //         $status = $validated['status'] ?? '';
    //         $jenis_cuti = $validated['jenis_cuti'] ?? '';
    //         $limit = 10;
    //         $page = $validated['page'] ?? 1;
    //         $offset = ($page - 1) * $limit;
    //         $searchColoms = [
    //             'nama_karyawan',
    //             'No_Transaksi',
    //             'jenis_cuti'
    //         ];

    //         $approvers = DB::table('N_HRIS_VW_Approval_Request')
    //                                 // ->whereNull('Flag_Approval')
    //                                 ->where('current_Approver_Kode', $pengguna->Kode_Karyawan)
    //                                 ;
    //         $eligibleTransactions = $approvers->get()->pluck('No_Transaksi');
    //         if ($eligibleTransactions->isEmpty()) {
    //             return ResponseHelper::error( "Tidak ada pengajuan yang memerlukan persetujuan.", 200);
    //         }

    //         $dataCte = DB::table('N_HRIS_VW_All_Transactions as t')
    //         ->joinSub($approvers, 'tasks', function ($join) {
    //             $join->on('t.No_Transaksi', '=', 'tasks.No_Transaksi');
    //         });

    //         if ($search) {
    //             $searchParam = '%' . $search . '%';

    //             $dataCte->where(function($query) use ($searchColoms, $searchParam) {
    //                 foreach ($searchColoms as $col) {
    //                     $query->orWhere($col, 'like', $searchParam);
    //                 }
    //             });
    //         }

    //         if ($status) {
    //             $dataCte->where('status', $status);
    //         }

    //         if ($jenis_cuti) {
    //             $dataCte->where('jenis_cuti', $jenis_cuti);
    //         }

    //         $countQuery = clone $dataCte;
    //         $total = $countQuery->count();

    //         $dataQuery = $dataCte
    //         ->orderBy('Created_At', 'DESC')
    //         ->offset($offset)
    //         ->limit($limit);
    //         // dd($dataQuery->get());
    //         $pengajuanList = $dataQuery
    //         ->where('Flow_State', '<>', 'FUTURE')
    //         ->where('Flow_State', '<>', 'VOID')
    //         ->get()
    //         ->map(function ($item) use ($approvers, $pengguna) {
    //             // dd($item);
    //             // Ambil semua approval berdasarkan transaksi
    //             $approverRows = DB::table('N_HRIS_VW_Approval_Request')
    //             ->where('No_Transaksi', $item->No_Transaksi)
    //             ->orderBy('Request_Order_Flow', 'ASC')
    //             ->get();

    //             $approvalFlow = collect($approverRows)->map(function($h) {
    //             // Cari Nama Approver (Bisa di-join di View biar ga query berulang)
    //                 $nama = DB::table('Karyawan')->where('Kode_Karyawan', $h->Current_Approver_Kode)->value('Nama');

    //                 return [
    //                     'name'   => $nama ?? $h->Current_Approver_Kode,
    //                     'status' => $this->parseStatusRequest($h->Flag_Approval),
    //                     'date'   => $h->Tanggal_Approved,
    //                     'step'   => $h->Request_Order_Flow
    //                 ];
    //             })->toArray();
    //             // dd($approvalFlow);
    //             $approverPlaceholder = $approverRows->map(function ($row) {
    //                 return "{$row->Current_Approver_Kode} : " . ($row->Flag_Approval ?? 'N');
    //             })->implode(', ');

    //             $previousApprover = null;

    //             // dd($approverPlaceholder);
    //             $priority = ['T','P',null, 'Y'];
    //             $currentFlow = null;

    //             foreach ($priority as $flag) {
    //                 $filtered = $approverRows->filter(fn($row) => $row->Flag_Approval === $flag);
    //                 if ($filtered->isNotEmpty()) {
    //                     $currentFlow = $filtered->sortByDesc('Request_Order_Flow')->first();
    //                     break;
    //                 }
    //             }
    //             $angkaCurrentFlow = $currentFlow->Request_Order_Flow;

    //             $previousApprover = $approverRows
    //             ->where('Request_Order_Flow', $currentFlow->Request_Order_Flow - 1)
    //             ->first();

    //             $maxFlow = $approverRows
    //                 ->max('Request_Order_Flow');

    //             // dd($item);
    //             return [
    //                 'tanggal_create'    => $item->Created_At,
    //                 'No_Transaksi'      => $item->No_Transaksi,
    //                 'Approver_Kode_Karyawan'       =>  Crypt::encryptString($currentFlow->Current_Approver_Kode),
    //                 'requester_Nama'              => $item->Nama_Requester,
    //                 'Requester_Kode_Karyawan'     => Crypt::encryptString($item->Kode_Karyawan_Requester),
    //                 'Tipe_Izin2'         => $this->parseSubTipe($item->Sub_Tipe),
    //                 'Tipe_Izin'         => $item->Sub_Tipe,
    //                 'Alasan'            => $item->Deskripsi,
    //                 'TanggalIzin'     => $item->Tanggal_Mulai,
    //                 'Tanggal_Selesai'   => $item->Tanggal_Selesai,
    //                 'Lampiran'          => $item->File_Path,
    //                 'MaxFlow'           => $maxFlow,
    //                 'currentFlow'       => $angkaCurrentFlow ?? 0,
    //                 'status'            => $this->parseStatusRequest($item->Flag_Approval),
    //                 'Approver'          => $currentFlow ? (DB::table('Karyawan')->where('Kode_Karyawan', $currentFlow->Current_Approver_Kode)->first()->Nama ?? null) : null,
    //                 'Status_Approval_Saat_Ini' => $currentFlow ? $currentFlow->Flag_Approval : null,
    //                 'Jam_Mulai'         => $item->Jam_Diajukan,
    //                 'Status_Approval_Langkah_Sebelumnya' => $previousApprover->Flag_Approval ?? null,
    //                 'order_flow'        => $currentFlow ? $currentFlow->Request_Order_Flow : 0,
    //                 'STATUSLIST'        => $approverPlaceholder,
    //             ];
    //         });

    //          $final_page = new LengthAwarePaginator(
    //             $pengajuanList,
    //             $total,
    //             $limit,
    //             $page,
    //             [
    //                 'path' => request()->url(),
    //                 'query' => request()->query(),
    //             ]
    //         );

    //         return ResponseHelper::success($final_page, "Data pengajuan berhasil diambil.", 200);

    //     }catch (\Exception $e) {
    //         Log::channel('izinAtasanApprovalLog')->error('ERROR GETDATA: ' . $e->getMessage());
    //         return ResponseHelper::error();
    //     }
    // }
    public function getData(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'tipeFilter' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $pengguna = Auth::user()->karyawan;
            $limit = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            $search = strip_tags($validated['search'] ?? '');
            $search = strip_tags($validated['search'] ?? '');
            $status = $validated['status'] ?? '';
            $tanggal = $validated['tanggalFilter'] ?? '';
            $jenis_cuti = $validated['tipeFilter'] ?? '';

            $searchColoms = [
                'Nama_Requester',
                'No_Transaksi',
                'Tipe_Transaksi',
                // 'Sub_Tipe',
                'Tanggal_Mulai',
                'Tanggal_Selesai',
                'Deskripsi',
                'Nama_Divisi_Requester',
                'Nama_Sub_Divisi_Requester',
                'Nama_Jabatan_Requester',
                'Nama_Level_Requester',
            ];

            $myPendingTasks = DB::table('N_HRIS_VW_Approval_Request')
                ->where('Current_Approver_Kode', $pengguna->Kode_Karyawan)
                ->whereIN('Flow_State', ['CURRENT', 'HISTORY'])
                ->select('No_Transaksi');

            $dataQuery = DB::table('N_HRIS_VW_All_Transactions as t')->joinSub($myPendingTasks, 'tasks', function (
                $join,
            ) {
                $join->on('t.No_Transaksi', '=', 'tasks.No_Transaksi');
            });
            // dd($dataQuery->get());
            if ($search) {
                $searchParam = '%'.$search.'%';
                $dataQuery->where(function ($query) use ($searchColoms, $searchParam) {
                    foreach ($searchColoms as $col) {
                        $query->orWhere('t.'.$col, 'like', $searchParam);
                    }
                });
            }

            if ($status) {
                $dataQuery->where('t.Status_Detail', $status);
            }

            if ($jenis_cuti) {
                $dataQuery->where('t.Tipe_Transaksi', $jenis_cuti);
            }

            if ($tanggal) {
                $filterDate = Carbon::parse($tanggal)->toDateString();

                $dataQuery->where(function ($q) use ($filterDate) {
                    $q->whereDate('t.Tanggal_Mulai', '<=', $filterDate)->whereDate(
                        't.Tanggal_Selesai',
                        '>=',
                        $filterDate,
                    );
                });
                // dd($dataQuery->get());
            }

            $total = $dataQuery->count();
            // dd($total);

            $paginatedData = $dataQuery->orderBy('Created_At', 'DESC')->offset($offset)->limit($limit);

            $items = collect($paginatedData->get());
            // dd($paginatedData->get());
            $transactionIds = $items->pluck('No_Transaksi')->toArray();

            if (empty($transactionIds)) {
                return ResponseHelper::success([], 'Data pengajuan berhasil diambil.', 200);
            }

            $allApprovals = DB::table('N_HRIS_VW_Approval_Request as ar')
                ->leftJoin('Karyawan as k', 'ar.Current_Approver_Kode', '=', 'k.Kode_Karyawan')
                ->whereIn('ar.No_Transaksi', $transactionIds)
                ->select('ar.*', 'k.Nama as Nama_Approver')
                ->orderBy('ar.Request_Order_Flow', 'ASC')
                ->get()
                ->groupBy('No_Transaksi');

            // Batch lookup Expired_At dari HRIS_Approval_Request (sumber otoritatif)
            // N_HRIS_VW_Approval_Request view belum tentu punya kolom Expired_At
            $expiredAtMap = DB::table('HRIS_Approval_Request')
                ->whereIn('No_Transaksi', $transactionIds)
                ->whereNotNull('Expired_At')
                ->get(['No_Transaksi', 'Expired_At'])
                ->groupBy('No_Transaksi')
                ->map(fn ($rows) => $rows->first()->Expired_At)
                ->all();

            // Status final per-transaksi dari SUMBER KEBENARAN TUNGGAL (Dashboard_Status).
            $dashboardStatusMap = $this->buildDashboardStatusMap($transactionIds);

            $formattedData = $items->map(function ($item) use ($allApprovals, $pengguna, $expiredAtMap, $dashboardStatusMap) {
                // dd($item);

                $tanggalDet = TanggalTransaksiIzin::teks($item->No_Transaksi);
                // dd($tanggalDet);
                $approverRows = $allApprovals->get($item->No_Transaksi, collect());

                $currentFlow = $approverRows
                    ->where('No_Transaksi', $item->No_Transaksi)
                    ->where('Current_Approver_Kode', $pengguna->Kode_Karyawan)
                    ->first();

                if (! $currentFlow) {
                    $currentFlow = $approverRows->last();
                }

                $previousApprover = $approverRows
                    ->where('Request_Order_Flow', ($currentFlow->Request_Order_Flow ?? 0) - 1)
                    ->first();

                $maxFlow = $approverRows->max('Request_Order_Flow');

                $approverPlaceholder = $approverRows
                    ->map(function ($row) {
                        return "{$row->Current_Approver_Kode} : ".($row->Flag_Approval ?? 'N');
                    })
                    ->implode(', ');

                // dd($currentFlowite);
                return [
                    'tanggal_create' => $item->Created_At,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Approver_Kode_Karyawan' => Crypt::encryptString($currentFlow->Current_Approver_Kode ?? ''),
                    'requester_Nama' => $item->Nama_Requester,
                    'Requester_Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),
                    'Tipe_Izin2' => $this->parseSubTipe($item->Sub_Tipe),
                    'Tipe_Izin' => $item->Sub_Tipe,
                    'Alasan' => $item->Deskripsi,
                    'TanggalIzin' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,
                    'Lampiran' => $item->File_Path ?? null,
                    'MaxFlow' => $maxFlow,
                    'currentFlow' => $currentFlow->Request_Order_Flow ?? 0,
                    'status' => $this->parseStatusRequest(
                        $dashboardStatusMap[$item->No_Transaksi] ?? ($item->Flag_Approval ?? 'N'),
                    ),
                    'Approver' => $currentFlow->Nama_Approver ?? ($currentFlow->Current_Approver_Kode ?? null),
                    'Status_Approval_Saat_Ini' => $currentFlow->Flag_Approval ?? null,
                    'Jam_Mulai' => $item->Jam_Diajukan ?? null,
                    'Status_Approval_Langkah_Sebelumnya' => $previousApprover->Flag_Approval ?? null,
                    'order_flow' => $currentFlow->Request_Order_Flow ?? 0,
                    'STATUSLIST' => $approverPlaceholder,
                    'Keterangan_Penolakan' => optional(
                        $approverRows->firstWhere('Flag_Approval', 'T'),
                    )->Catatan,
                    'tanggalReal' => $tanggalDet,
                    'expired_at' => $expiredAtMap[$item->No_Transaksi] ?? null,
                    'tanggal_approval' => $currentFlow->Tanggal_Approval ?? null,
                ];
            });
            //    $final_page = new LengthAwarePaginator(
            //     $formattedData,
            //     $total,
            //     $limit,
            //     $page,
            //     [
            //         'path' => request()->url(),
            //         'query' => request()->query(),
            //     ]
            // );

            return ResponseHelper::successWithPaginationV2(
                $formattedData,
                $page,
                $limit,
                $total,
                'Riwayat cuti berhasil diambil.',
                200,
            );

            // return response

            // return ResponseHelper::success($final_page, "Data pengajuan berhasil diambil.", 200);
        } catch (\Exception $e) {
            Log::channel('izinAtasanApprovalLog')->error('ERROR GETDATA: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getData2(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'tipeFilter' => ['nullable', 'string', 'max:100'],
            'tanggalFilter' => ['nullable', 'string', 'max:100'],
            'statusFilter' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $pengguna = Auth::user()->karyawan;
            $limit = 10;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $limit;

            $search = strip_tags($validated['search'] ?? '');
            // $search = strip_tags($validated['search'] ?? '');
            $status = $validated['status'] ?? '';
            $tanggal = $validated['tanggalFilter'] ?? '';
            $jenis_cuti = $validated['tipeFilter'] ?? '';
            $statusFilter = $validated['statusFilter'] ?? '';
            // dd($jenis_cuti);
            $searchColoms = [
                'Nama_Requester',
                'No_Transaksi',
                'Tipe_Transaksi',
                'Sub_Tipe',
                'Tanggal_Mulai',
                'Tanggal_Selesai',
                'Deskripsi',
                'Nama_Divisi_Requester',
                'Nama_Sub_Divisi_Requester',
                'Nama_Jabatan_Requester',
                'Nama_Level_Requester',
            ];

            $myPendingTasks = DB::table('N_HRIS_VW_Approval_Request')
                ->where('Current_Approver_Kode', $pengguna->Kode_Karyawan)
                ->whereIN('Flow_State', ['CURRENT', 'HISTORY'])
                ->select('No_Transaksi', 'Flag_Approval', 'Tanggal_Request');
            // dd($myPendingTasks->get());
            $dataQuery = DB::table('N_HRIS_VW_All_Transactions as t')
                ->joinSub($myPendingTasks, 'tasks', function ($join) {
                    $join->on('t.No_Transaksi', '=', 'tasks.No_Transaksi');
                })
                ->select('t.*');
            // dd($dataQuery->get());
            if ($search) {
                $searchParam = '%'.$search.'%';
                $dataQuery->where(function ($query) use ($searchColoms, $searchParam) {
                    foreach ($searchColoms as $col) {
                        $query->orWhere('t.'.$col, 'like', $searchParam);
                    }
                });
            }

            if ($status) {
                $dataQuery->where('t.Status_Detail', $status);
            }

            if ($jenis_cuti) {
                $dataQuery->where(function ($query) use ($jenis_cuti) {
                    $query->where('t.Sub_Tipe', $jenis_cuti)->orWhere('t.Tipe_Transaksi', $jenis_cuti);
                });
            }

            if ($tanggal) {
                $filterDate = Carbon::parse($tanggal)->toDateString();

                $dataQuery->where(function ($q) use ($filterDate) {
                    $q->whereDate('t.Tanggal_Mulai', '<=', $filterDate)->whereDate(
                        't.Tanggal_Selesai',
                        '>=',
                        $filterDate,
                    );
                });
                // dd($dataQuery->get());
            }

            if ($statusFilter) {
                $batasHariPengajuan = config('services.hcis.batas_approve_izin');
                $batasApproveCuti = config('services.hcis.batas_approve_cuti');
                // Flag dari approval request (tasks)
                $reqFlagSql = "UPPER(LTRIM(RTRIM(COALESCE(tasks.Flag_Approval, ''))))";
                // Flag dari header transaksi (t)
                $txFlagSql = "UPPER(LTRIM(RTRIM(COALESCE(t.Flag_Approval, ''))))";
                $pendingSql = "({$reqFlagSql} = '' OR {$reqFlagSql} = 'N')";
                $transactionTypeSql = "LOWER(LTRIM(RTRIM(COALESCE(t.Tipe_Transaksi, ''))))";
                $expiredParts = ["{$reqFlagSql} = 'P'", "{$txFlagSql} = 'P'"];
                $expiredParams = [];

                // Cek Expired_At dari HRIS_Approval_Request via EXISTS
                $expiredAtExistsSql = 'EXISTS (
                    SELECT 1 FROM HRIS_Approval_Request ar
                    WHERE ar.No_Transaksi = tasks.No_Transaksi
                    AND ar.Expired_At IS NOT NULL
                    AND ar.Expired_At < GETDATE()
                )';
                $expiredParts[] = "({$pendingSql} AND {$expiredAtExistsSql})";

                // Cek Flag_Approval = 'P' di tabel detail transaksi (ExpiredIzin cron)
                // untuk data lama yang Expired_At-nya belum terisi
                $detailExpiredSql = "EXISTS (
                    SELECT 1 FROM Transaksi_Terlambat_Pulang_Cepat_Detail d
                    WHERE d.No_Transaksi = tasks.No_Transaksi AND d.Flag_Approval = 'P'
                ) OR EXISTS (
                    SELECT 1 FROM Transaksi_Sakit_Izin_Detail d
                    WHERE d.No_Transaksi = tasks.No_Transaksi AND d.Flag_Approval = 'P'
                ) OR EXISTS (
                    SELECT 1 FROM Transaksi_Cuti_Detail d
                    WHERE d.No_Transaksi = tasks.No_Transaksi AND d.Flag_Approval = 'P'
                )";
                $expiredParts[] = "({$pendingSql} AND ({$detailExpiredSql}))";

                if ($batasApproveCuti !== null) {
                    $expiredParts[] = "(
                        {$transactionTypeSql} = 'cuti'
                        AND {$pendingSql}
                        AND tasks.Tanggal_Request IS NOT NULL
                        AND NOT {$expiredAtExistsSql}
                        AND DATEDIFF(DAY, tasks.Tanggal_Request, GETDATE()) >= ?
                    )";
                    $expiredParams[] = (int) $batasApproveCuti;
                }

                if ($batasHariPengajuan !== null) {
                    $expiredParts[] = "(
                        {$transactionTypeSql} <> 'cuti'
                        AND {$pendingSql}
                        AND tasks.Tanggal_Request IS NOT NULL
                        AND NOT {$expiredAtExistsSql}
                        AND DATEDIFF(DAY, tasks.Tanggal_Request, GETDATE()) >= ?
                    )";
                    $expiredParams[] = (int) $batasHariPengajuan;
                }

                $expiredSql = '('.implode(' OR ', $expiredParts).')';

                match ($statusFilter) {
                    'belumApprove' => $dataQuery->whereRaw($pendingSql)->whereRaw("NOT {$expiredSql}", $expiredParams),
                    'approved' => $dataQuery->whereRaw("{$reqFlagSql} = 'Y'"),
                    'rejected' => $dataQuery->whereRaw("{$reqFlagSql} = 'T'"),
                    'expired' => $dataQuery->whereRaw($expiredSql, $expiredParams),
                    default => null,
                };
            }

            $total = $dataQuery->count();
            // dd($total);

            $paginatedData = $dataQuery->orderBy('Created_At', 'DESC')->offset($offset)->limit($limit);
            // dd($paginatedData->get());
            $items = collect($paginatedData->get());
            // dd($paginatedData->get());
            $transactionIds = $items->pluck('No_Transaksi')->toArray();

            if (empty($transactionIds)) {
                return ResponseHelper::error('Tidak ada data.', 200);
            }

            $allApprovals = DB::table('N_HRIS_VW_Approval_Request as ar')
                ->leftJoin('Karyawan as k', 'ar.Current_Approver_Kode', '=', 'k.Kode_Karyawan')
                ->whereIn('ar.No_Transaksi', $transactionIds)
                ->select('ar.*', 'k.Nama as Nama_Approver')
                ->orderBy('ar.Request_Order_Flow', 'ASC')
                ->get()
                ->groupBy('No_Transaksi');

            // Batch lookup Expired_At dari HRIS_Approval_Request (sumber otoritatif)
            // N_HRIS_VW_Approval_Request view belum tentu punya kolom Expired_At
            $expiredAtMap = DB::table('HRIS_Approval_Request')
                ->whereIn('No_Transaksi', $transactionIds)
                ->whereNotNull('Expired_At')
                ->get(['No_Transaksi', 'Expired_At'])
                ->groupBy('No_Transaksi')
                ->map(fn ($rows) => $rows->first()->Expired_At)
                ->all();

            // Status final per-transaksi dari SUMBER KEBENARAN TUNGGAL (Dashboard_Status).
            $dashboardStatusMap = $this->buildDashboardStatusMap($transactionIds);

            $formattedData = $items->map(function ($item) use ($allApprovals, $pengguna, $expiredAtMap, $dashboardStatusMap) {
                // dd($item);

                $tanggalDet = TanggalTransaksiIzin::teks($item->No_Transaksi);
                // dd($tanggalDet);
                $approverRows = $allApprovals->get($item->No_Transaksi, collect());

                // dd($approverRows);

                $currentFlow = $approverRows
                    ->where('No_Transaksi', $item->No_Transaksi)
                    ->where('Current_Approver_Kode', $pengguna->Kode_Karyawan)
                    ->first();

                // $routeFlowCurrent = $approverRows
                // ->where('No_Transaksi', $item->No_Transaksi)
                // ->filter(function($e){
                //     return $e->Flow_State === 'CURRENT' || $e->Flow_State === 'History';
                // })
                // ->sortByDesc('Request_Order_Flow')
                // ->first();

                if (! $currentFlow) {
                    $currentFlow = $approverRows->last();
                }

                $angkaPengajuanHari = Carbon::parse($item->Created_At)->diffInDays(Carbon::now()) + 1;

                $previousApprover = $approverRows
                    ->where('Request_Order_Flow', ($currentFlow->Request_Order_Flow ?? 0) - 1)
                    ->first();

                $maxFlow = $approverRows->max('Request_Order_Flow');
                $batasHariPengajuan = config('services.hcis.batas_approve_izin');
                $batasApproveCuti = config('services.hcis.batas_approve_cuti');

                $approverPlaceholder = $approverRows
                    ->map(function ($row) use ($item) {
                        return "{$row->Current_Approver_Kode} : ".
                            $this->parseFlagApproval($row->Flag_Approval, $row->Tanggal_Request, $item->Tipe_Transaksi);
                    })
                    ->implode(', ');

                // dd($angkaPengajuanHari);
                // dd($routeFlowCurrent);
                $statusPengajuan = 'N';

                if ($item->Flag_Approval === 'Y') {
                    $statusPengajuan = 'Y';
                } elseif (empty($item->Flag_Approval) && $angkaPengajuanHari >= 3) {
                    $statusPengajuan = 'P';
                } elseif (! empty($item->Flag_Approval)) {
                    $statusPengajuan = $item->Flag_Approval;
                }

                $statusApprovalBerlangsung = null;

                if ($currentFlow->Flag_Approval === 'Y') {
                    $statusApprovalBerlangsung = 'Y';
                } elseif (empty($currentFlow->Flag_Approval) && $angkaPengajuanHari >= 3) {
                    $statusApprovalBerlangsung = 'P';
                } elseif (! empty($currentFlow->Flag_Approval)) {
                    $statusApprovalBerlangsung = $currentFlow->Flag_Approval;
                }

                return [
                    'tanggal_create' => $item->Created_At,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Approver_Kode_Karyawan' => Crypt::encryptString($currentFlow->Current_Approver_Kode ?? ''),
                    'requester_Nama' => $item->Nama_Requester,
                    'Requester_Kode_Karyawan' => Crypt::encryptString($item->Kode_Karyawan_Requester),
                    'Tipe_Izin2' => $this->parseSubTipe($item->Sub_Tipe),
                    'Tipe_Izin' => $item->Sub_Tipe,
                    'Alasan' => $item->Deskripsi,
                    'TanggalIzin' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,
                    'Lampiran' => $item->File_Path ?? null,
                    'MaxFlow' => $maxFlow,
                    'currentFlow' => $currentFlow->Request_Order_Flow ?? 0,
                    'status' => $this->parseStatusRequest(
                        $dashboardStatusMap[$item->No_Transaksi]
                            ?? ($this->parseFlagApproval($item->Flag_Approval, $item->Created_At, $item->Tipe_Transaksi) ?? 'N'),
                    ),
                    'Approver' => $currentFlow->Nama_Approver ?? ($currentFlow->Current_Approver_Kode ?? null),
                    'Status_Approval_Saat_Ini' => $this->parseFlagApproval(
                        $currentFlow->Flag_Approval,
                        $currentFlow->Tanggal_Request,
                        $item->Tipe_Transaksi,
                    ) ?? null,
                    'Jam_Mulai' => $item->Jam_Diajukan ?? null,
                    'Status_Approval_Langkah_Sebelumnya' => $previousApprover->Flag_Approval ?? null,
                    'order_flow' => $currentFlow->Request_Order_Flow ?? 0,
                    'STATUSLIST' => $approverPlaceholder,
                    'Keterangan_Penolakan' => optional(
                        $approverRows->firstWhere('Flag_Approval', 'T'),
                    )->Catatan,
                    'tanggalReal' => $tanggalDet,
                    'expired_at' => $expiredAtMap[$item->No_Transaksi] ?? null,
                    'tanggal_approval' => $currentFlow->Tanggal_Approval ?? null,
                ];
            });

            // dd($formattedData);
            $final_page = new LengthAwarePaginator($formattedData, $total, $limit, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            return ResponseHelper::success($final_page, 'Data pengajuan berhasil diambil.', 200);
        } catch (\Exception $e) {
            Log::channel('izinAtasanApprovalLog')->error('ERROR GETDATA: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Map No_Transaksi => flag status final ('Y'|'T'|'P'|'N') yang diturunkan dari
     * Dashboard_Status (sumber kebenaran tunggal, AllTransactionsReadService).
     *
     * Menghindari salah-tandai "Ditolak sistem" hanya karena umur transaksi padahal
     * flow approval sudah selesai. Satu No_Transaksi bisa punya banyak baris (multi-langkah).
     *
     * @param  array<int,string>  $transactionIds
     * @return array<string,string>
     */
    private function buildDashboardStatusMap(array $transactionIds): array
    {
        if (empty($transactionIds)) {
            return [];
        }

        return (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
            ->statusByNoTransaksi($transactionIds)
            ->groupBy('No_Transaksi')
            ->map(function ($rows) {
                $statuses = $rows->map(fn ($r) => strtoupper((string) ($r->Dashboard_Status ?? '')));

                if ($statuses->contains('REJECTED') || $statuses->contains('VOID')) {
                    return 'T'; // ditolak
                }
                if ($statuses->contains('EXPIRED')) {
                    return 'P'; // ditolak sistem / expired
                }
                if ($statuses->contains('PENDING') || $statuses->contains('FUTURE')) {
                    return 'N'; // masih diproses
                }
                if ($statuses->contains('APPROVED')) {
                    return 'Y'; // disetujui
                }

                return 'N';
            })
            ->all();
    }

    private function parseStatusRequest($status)
    {
        if ($status == 'Y') {
            $statusText = 'Disetujui';
        } elseif ($status == 'T') {
            $statusText = 'Ditolak';
        } elseif ($status == 'P') {
            $statusText = 'Ditolak sistem';
        } else {
            $statusText = 'Diproses';
        }

        return $statusText;
    }

    /**
     * Terjemahan Flag_Approval mentah menjadi status tampilan.
     *
     * Isinya dipindahkan ke AturanKedaluwarsaApproval. Sampai perubahan ini,
     * metode ini ADA DUA — di sini dan di AbsensiController — identik
     * 1.209 byte sama persis, sementara jalur yang MENULIS Expired_At membaca
     * konfigurasi yang sama dengan arti yang berlawanan.
     */
    private function parseFlagApproval($flag, $tanggal, $jenis)
    {
        return AturanKedaluwarsaApproval::statusFlag($flag, $tanggal, $jenis);
    }

    private function parseSubTipe($tipe)
    {
        $subTipe = '';
        switch ($tipe) {
            case 'cuti':
                $subTipe = 'Cuti';
                break;
            case 'izinFull':
                $subTipe = 'Izin Pribadi';
                break;
            case 'sakit':
                $subTipe = 'Izin Sakit';
                break;
            case 'terlambat':
                $subTipe = 'Izin Terlambat';
                break;
            case 'pulangCepat':
                $subTipe = 'Izin Pulang Cepat';
                break;
            default:
                $subTipe = $tipe;
                break;
        }

        return $subTipe;
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'No_Transaksi' => 'required|string',
            'tipeConfirm' => 'required',
            'catatan' => 'nullable|string',
        ]);

        try {
            $userKode = Auth::user()->karyawan->Kode_Karyawan;
            // dd($userKode);

            if ($request->tipeConfirm == 'Terima') {
                $this->approvalService->approve($request->No_Transaksi, $userKode, $request->catatan);
                $msg = 'Pengajuan berhasil disetujui.';
            } else {
                $forceRejectHalt = $request->is('api/v1/izin-atasan-approval/approveDH');

                $this->approvalService->reject(
                    $request->No_Transaksi,
                    $userKode,
                    $request->catatan ?? 'Ditolak tanpa catatan',
                    $forceRejectHalt,
                );
                $msg = 'Pengajuan berhasil ditolak.';
            }

            return ResponseHelper::success(null, $msg);
        } catch (\Exception $e) {
            // Guard state (bukan giliran approver / sudah diproses / expired) adalah
            // kondisi bisnis yang wajar, bukan kegagalan sistem. Balas 409 dengan pesan
            // aslinya supaya UI bisa menampilkan alasan yang benar dan menyegarkan
            // barisnya yang basi, bukan memunculkan "Terjadi kesalahan sistem".
            if ($this->isApprovalStateConflict($e->getMessage())) {
                Log::channel('izinAtasanApprovalLog')->warning(
                    'Approval ditolak guard state | No_Transaksi: '.
                        $request->No_Transaksi.
                        ' | Approver: '.
                        ($userKode ?? '-').
                        ' | Aksi: '.
                        $request->tipeConfirm.
                        ' | Message: '.
                        $e->getMessage(),
                );

                return ResponseHelper::error($e->getMessage(), 409);
            }

            $errorMsg = config('app.debug') ? $e->getMessage() : 'Terjadi kesalahan sistem saat memproses approval.';
            Log::channel('izinAtasanApprovalLog')->error(
                $errorMsg.
                    ' | '.
                    'Message: '.
                    $e->getMessage().
                    ' | '.
                    'File: '.
                    $e->getFile().
                    ' | '.
                    'Line: '.
                    $e->getLine().
                    ' | '.
                    'Trace: '.
                    $e->getTraceAsString(),
            );

            return ResponseHelper::error($errorMsg, 500);
        }
    }

    /**
     * Pesan guard state dari ApprovalService/ApprovalRequestService.
     *
     * Muncul saat approver menekan aksi pada baris yang datanya sudah basi
     * (stepnya sudah dia proses, sudah didahului orang lain, atau expired).
     * Bedakan dari error sistem agar tidak dilaporkan sebagai HTTP 500.
     */
    private function isApprovalStateConflict(string $message): bool
    {
        $penanda = [
            'bukan approver untuk step ini',
            'bukan anggota dari group approver',
            'bukan anggota role approver',
            'baru saja diproses oleh orang lain',
            'Approval sudah expired',
            'Tidak ada approval yang pending',
            'Data approval request tidak ditemukan',
        ];

        foreach ($penanda as $needle) {
            if (stripos($message, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    public function approve(Request $request)
    {
        $request->validate([
            'id' => 'required|string',
            'status' => 'required|in:Y,N', // Y for Approve, N for Reject
            'keterangan' => 'nullable|string',
        ]);

        try {
            $decodedId = Hashids::connection('custom')->decode($request->id)[0];
            $approverKodeKaryawan = Auth::user()->karyawan->Kode_Karyawan;

            DB::beginTransaction();

            $approvalRequest = DB::table('HRIS_Approval_Request')->where('id', $decodedId)->first();

            if (! $approvalRequest) {
                DB::rollBack();

                return ResponseHelper::error('Permintaan approval tidak ditemukan.', 404);
            }

            // Update HRIS_Approval_Request
            DB::table('HRIS_Approval_Request')
                ->where('id', $decodedId)
                ->update([
                    'Flag_Approval' => $request->status,
                    'Tanggal_Approval' => now(),
                    // Kolom yang benar pada HRIS_Approval_Request adalah 'Catatan'
                    // (bukan 'Keterangan_Approval' yang tidak ada → menyebabkan 500).
                    'Catatan' => $request->keterangan,
                ]);

            // Get the flow details for the current approval
            $currentFlow = DB::table('HRIS_Approval_Flow')->where('id', $approvalRequest->Flow_Id)->first();

            if (! $currentFlow) {
                DB::rollBack();

                return ResponseHelper::error('Flow approval tidak ditemukan.', 404);
            }

            // If approved, check for the next approver
            if ($request->status === 'Y') {
                $nextApprovalRequest = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $approvalRequest->No_Transaksi)
                    ->where('Request_Order_Flow', '>', $approvalRequest->Request_Order_Flow)
                    ->whereNull('Flag_Approval')
                    ->orderBy('Request_Order_Flow')
                    ->first();

                if ($nextApprovalRequest) {
                    if (($nextApprovalRequest->Flag_Group ?? null) !== 'Y') {
                        $nextFlow = $nextApprovalRequest->Flow_Id
                            ? DB::table('HRIS_Approval_Flow')->where('id', $nextApprovalRequest->Flow_Id)->first()
                            : null;

                        if (! $nextFlow) {
                            $nextFlow = (object) [
                                'id' => null,
                                'Kode_Karyawan' => $nextApprovalRequest->Approver_Karyawan_Kode,
                            ];
                        }

                        $this->sendNextApproverNotification($approvalRequest, $nextFlow);
                    }
                } else {
                    // This is the last approver, update the main transaction status
                    $this->updateMainTransactionStatus(
                        $approvalRequest->Jenis_Transaksi,
                        $approvalRequest->No_Transaksi,
                        'Y',
                    );
                }
            } else {
                // If rejected, update the main transaction status to 'N'
                $this->updateMainTransactionStatus(
                    $approvalRequest->Jenis_Transaksi,
                    $approvalRequest->No_Transaksi,
                    'N',
                );
            }

            DB::commit();

            if (
                $request->status === 'Y' &&
                strtoupper(substr((string) $approvalRequest->No_Transaksi, 0, 2)) === 'CT'
            ) {
                try {
                    app(\App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService::class)->notifyRolePoolForCurrentStep(
                        (string) $approvalRequest->No_Transaksi,
                    );
                } catch (\Throwable $notifEx) {
                    Log::channel('izinAtasanApprovalLog')->warning(
                        'Gagal kirim notif role pool cuti: '.$notifEx->getMessage(),
                        ['no_transaksi' => $approvalRequest->No_Transaksi],
                    );
                }
            }

            return ResponseHelper::success(
                null,
                'Permintaan berhasil di'.($request->status === 'Y' ? 'setujui' : 'tolak'),
                200,
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('izinAtasanApprovalLog')->error('ERROR APPROVE/REJECT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    private function sendNextApproverNotification(object $approvalRequest, object $nextFlow): void
    {
        $noTransaksi = (string) ($approvalRequest->No_Transaksi ?? '');
        $nextApproverKode = trim((string) ($nextFlow->Kode_Karyawan ?? ''));

        if ($noTransaksi === '' || $nextApproverKode === '') {
            Log::channel('izinAtasanApprovalLog')->warning('Skip WA next approver: data flow tidak lengkap.', [
                'no_transaksi' => $noTransaksi,
                'next_flow' => $nextFlow,
            ]);

            return;
        }

        $nextApprover = DB::table('Karyawan')->where('Kode_Karyawan', $nextApproverKode)->first();

        if (
            ! $nextApprover ||
            trim((string) ($nextApprover->HP ?? '')) === '' ||
            trim((string) $nextApprover->HP) === '-'
        ) {
            Log::channel('izinAtasanApprovalLog')->warning(
                'Skip WA next approver: data approver tidak valid / HP kosong.',
                [
                    'no_transaksi' => $noTransaksi,
                    'approver_kode' => $nextApproverKode,
                    'has_approver_data' => (bool) $nextApprover,
                ],
            );

            return;
        }

        $requester = DB::table('Karyawan')->where('Kode_Karyawan', $approvalRequest->Kode_Karyawan)->first();

        $jenisLabel = $this->resolveJenisTransaksiLabel(
            $noTransaksi,
            (string) ($approvalRequest->Jenis_Transaksi ?? 'Pengajuan'),
        );
        $tanggalLabel = TanggalTransaksiIzin::teks($noTransaksi) ?: '-';

        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $nextApprover->HP,
            'type' => 'template',
            'template' => [
                'name' => 'notif_approver_hcis',
                'language' => [
                    'code' => 'id',
                    'policy' => 'deterministic',
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $nextApprover->Nama],
                            ['type' => 'text', 'text' => $noTransaksi],
                            ['type' => 'text', 'text' => $jenisLabel],
                            ['type' => 'text', 'text' => $tanggalLabel],
                            ['type' => 'text', 'text' => $requester->Nama ?? 'Karyawan'],
                        ],
                    ],
                ],
            ],
        ];

        WhatsappLogHelper::sendAndLog(
            $nextApprover->Kode_Karyawan,
            $nextApprover->Nama,
            $nextApprover->HP,
            $noTransaksi,
            $pesan,
            function ($response) use ($noTransaksi, $nextFlow, $nextApprover) {
                $query = DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $noTransaksi)
                    ->whereNull('Flag_Approval');

                if ($nextFlow->id ?? null) {
                    $query->where('Flow_Id', $nextFlow->id);
                } else {
                    $query->where('Approver_Karyawan_Kode', $nextApprover->Kode_Karyawan);
                }

                $query->update([
                    'angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1'),
                ]);

                Log::channel('izinAtasanApprovalLog')->info('WA next approver berhasil dikirim.', [
                    'no_transaksi' => $noTransaksi,
                    'approver_kode' => $nextApprover->Kode_Karyawan,
                    'response' => $response,
                ]);
            },
            function ($reason, $response) use ($noTransaksi, $nextApprover) {
                Log::channel('izinAtasanApprovalLog')->warning('WA next approver gagal dikirim.', [
                    'no_transaksi' => $noTransaksi,
                    'approver_kode' => $nextApprover->Kode_Karyawan,
                    'reason' => $reason,
                    'response' => $response,
                ]);
            },
        );
    }

    private function resolveJenisTransaksiLabel(string $noTransaksi, string $fallback): string
    {
        $prefix = strtoupper(substr($noTransaksi, 0, 2));

        $row = match (true) {
            $prefix === 'CT' => DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $noTransaksi)->value('Jenis'),
            in_array($prefix, ['IS', 'IP'], true) => DB::table('Transaksi_Sakit_Izin_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->value('Jenis'),
            in_array($prefix, ['TP', 'TL', 'PC'], true) => DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->value('Jenis'),
            default => null,
        };

        return trim((string) $row) !== '' ? (string) $row : $fallback;
    }

    private function updateMainTransactionStatus($jenisTransaksi, $noTransaksi, $status)
    {
        switch ($jenisTransaksi) {
            case 'Izin':
                DB::table('HRIS_Izin')
                    ->where('No_Izin', $noTransaksi)
                    ->update(['Flag_Approve' => $status]);
                break;
            case 'Cuti':
                DB::table('HRIS_Cuti')
                    ->where('No_Cuti', $noTransaksi)
                    ->update(['Flag_Approve' => $status]);
                break;
            case 'Lembur':
                DB::table('HRIS_Lembur')
                    ->where('No_Lembur', $noTransaksi)
                    ->update(['Flag_Approve' => $status]);
                break;
            case 'Perjalanan Dinas':
                DB::table('HRIS_Perjalanan_Dinas')
                    ->where('No_Perjalanan_Dinas', $noTransaksi)
                    ->update(['Flag_Approve' => $status]);
                break;
            default:
                // Log an error or handle unknown transaction types
                Log::channel('izinAtasanApprovalLog')->warning('Unknown transaction type: '.$jenisTransaksi);
                break;
        }
    }

    public function getApprovalHistory(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);
            $status = $request->query('status', null); // 'Y' for approved, 'N' for rejected

            $query = DB::table('HRIS_Approval_Request as ar')
                ->join('HRIS_Approval_Flow as af', 'ar.Flow_Id', '=', 'af.id')
                ->join('Karyawan as k', 'ar.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->select(
                    'ar.id',
                    'ar.No_Transaksi',
                    'ar.Kode_Karyawan',
                    'k.Nama as Nama_Karyawan',
                    // HRIS_Approval_Request tidak punya kolom Jenis_Transaksi/Tanggal_Pengajuan/
                    // Keterangan_Approval. Petakan ke kolom yang benar (prefix No_Transaksi,
                    // created_at, Catatan) dengan alias agar output key tetap sama.
                    DB::raw(
                        "CASE UPPER(LEFT(ar.No_Transaksi,2)) WHEN 'CT' THEN 'Cuti' WHEN 'IS' THEN 'Sakit/Izin' WHEN 'SH' THEN 'Shift' WHEN 'LM' THEN 'Lembur' ELSE 'Pengajuan' END as Jenis_Transaksi",
                    ),
                    'ar.created_at as Tanggal_Pengajuan',
                    'ar.Flag_Approval',
                    'ar.Tanggal_Approval',
                    'ar.Catatan as Keterangan_Approval',
                    'af.order_flow',
                    'af.Kode_Karyawan as Approver_Kode_Karyawan',
                )
                ->where('af.Kode_Karyawan', Auth::user()->karyawan->Kode_Karyawan)
                ->whereNotNull('ar.Flag_Approval'); // Hanya menampilkan yang sudah diapprove/ditolak

            if (! empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('ar.No_Transaksi', 'LIKE', "%{$search}%")->orWhere('k.Nama', 'LIKE', "%{$search}%");
                });
            }

            if (! empty($status)) {
                $query->where('ar.Flag_Approval', $status);
            }

            $total = $query->count();

            $getData = $query->offset($offset)->limit($limit)->orderBy('ar.created_at', 'DESC')->get();

            if ($getData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $getData->map(function ($item) {
                $item->id = Hashids::connection('custom')->encode($item->id);
                $item->Kode_Karyawan = Hashids::connection('custom')->encode($item->Kode_Karyawan);

                return $item;
            });

            return ResponseHelper::successWithPagination($encodedData, $page, $limit, $total, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('izinAtasanApprovalLog')->error('ERROR GETAPPROVALHISTORY: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }
}
