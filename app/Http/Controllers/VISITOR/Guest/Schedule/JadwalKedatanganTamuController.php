<?php

namespace App\Http\Controllers\VISITOR\Guest\Schedule;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\VISITOR\Guest\Visit\FotoKendaraanTamuService;
use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Jadwal Kedatangan Tamu untuk departemen (DDL-2026-122) — BACA SAJA.
 *
 * Isinya disaring matriks departemen × jenis tamu DI SERVER: QC Incoming hanya
 * menerima baris B2B Procurement walau memanggil endpointnya dengan kategori
 * lain. Halamannya dihibahkan ke seluruh role grup VISITOR; yang tidak punya
 * satu centang pun di matriks melihat keadaan kosong, bukan data.
 */
class JadwalKedatanganTamuController extends Controller
{
    private const T_HEADER = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_DETAIL = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    /** Rentang maksimum satu permintaan; agenda, bukan arsip. */
    private const RENTANG_MAKS_HARI = 31;

    public function __construct(
        protected MatriksTamuService $matriks,
        protected VisitorKehadiranQueryService $kehadiran,
        protected VisitorPermitQueryService $permitQuery,
        protected FotoKendaraanTamuService $fotoKendaraan,
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
    ) {}

    public function index(Request $request)
    {
        $kategori = $this->matriks->kategoriBolehLihat($this->userIdWeb($request));

        return Inertia::render('VISITOR/Guest/Schedule/JadwalKedatanganPage', [
            'user' => $request->user(),
            'serverDate' => Carbon::today()->toDateString(),
            'kategori' => array_map(fn (string $k) => [
                'kode' => $k,
                'label' => MatriksTamuService::LABEL_KATEGORI[$k],
            ], $kategori),
            // Departemen (role) pengguna & haknya — ditampilkan supaya jelas
            // kenapa ia melihat jenis tamu yang ia lihat.
            'departemen' => array_map(fn (array $d) => [
                'nama' => $d['nama'],
                'lihat' => array_map(fn ($k) => MatriksTamuService::LABEL_KATEGORI[$k] ?? $k, $d['lihat']),
                'ajukan' => array_map(fn ($k) => MatriksTamuService::LABEL_KATEGORI[$k] ?? $k, $d['ajukan']),
            ], $this->matriks->departemen($this->userIdWeb($request))),
        ]);
    }

    public function getList(Request $request)
    {
        try {
            $page = max((int) $request->input('page', 1), 1);
            $limit = min(max((int) $request->input('limit', 15), 1), 100);
            $search = trim((string) $request->input('search', ''));
            $status = strtoupper(trim((string) $request->input('status', '')));
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');
            $kendaraan = strtoupper(trim((string) $request->input('kendaraan', '')));

            $boleh = $this->matriks->kategoriBolehLihat($this->userIdWeb($request));
            $diminta = strtoupper(trim((string) $request->input('kategori', '')));
            $kategori = $diminta !== '' ? array_values(array_intersect($boleh, [$diminta])) : $boleh;

            $query = $this->baseTamuQuery();
            MatriksTamuService::saring($query, $kategori);

            if ($search !== '') {
                $suka = '%'.$search.'%';
                $query->where(function ($q) use ($suka) {
                    $q->where('d.Nama_Lengkap_Tamu', 'LIKE', $suka)
                        ->orWhere('d.Instansi_Atau_Asal', 'LIKE', $suka)
                        ->orWhere('h.No_Transaksi', 'LIKE', $suka)
                        ->orWhere('k.Nama', 'LIKE', $suka)
                        ->orWhere('d.Keterangan_Tujuan_Detail', 'LIKE', $suka)
                        ->orWhereRaw($this->permitQuery->platNomorSql('d').' LIKE ?', [$suka]);
                });
            }

            if ($kendaraan === 'BERKENDARA') {
                $query->whereRaw("LTRIM(RTRIM(CONCAT(ISNULL(d.Plat_Nomor_Prefix, ''), ' ', ISNULL(d.Plat_Nomor_Angka, ''), ' ', ISNULL(d.Plat_Nomor_Suffix, '')))) <> ''");
            } elseif ($kendaraan === 'TANPA_KENDARAAN') {
                $query->whereRaw("LTRIM(RTRIM(CONCAT(ISNULL(d.Plat_Nomor_Prefix, ''), ' ', ISNULL(d.Plat_Nomor_Angka, ''), ' ', ISNULL(d.Plat_Nomor_Suffix, '')))) = ''");
            }

            if ($status === 'BATAL') {
                // Satu chip untuk semua kunjungan yang berakhir tanpa terjadi.
                $query->whereRaw($this->kehadiran->siklusKunjunganSql().' IN ('.StatusKunjunganTamu::sqlDaftar([
                    StatusKunjunganTamu::REJECTED,
                    StatusKunjunganTamu::WITHDRAWN,
                    StatusKunjunganTamu::REVOKED,
                    StatusKunjunganTamu::CANCELLED,
                ]).')');
            } elseif ($status && in_array($status, StatusKunjunganTamu::TAMPIL, true)) {
                $siklus = $this->kehadiran->siklusKunjunganSql();

                if ($status === StatusKunjunganTamu::PENDING_APPROVAL) {
                    $query->where(function ($q) use ($siklus) {
                        $q->whereRaw($siklus.' = ?', [StatusKunjunganTamu::PENDING_APPROVAL])
                            ->orWhere(function ($sub) {
                                $sub->where('h.Needs_Approval', 'Y')->whereNull('h.Flag_Approval');
                            })
                            ->orWhereExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('N_EMI_VISITOR_Transaksi_Permit as p')
                                    ->whereColumn('p.No_Transaksi_Tamu', 'h.No_Transaksi')
                                    ->whereNull('p.Flag_Approval')
                                    ->where('p.Flag_Aktif', 'Y');
                            });
                    });
                } elseif ($status === StatusKunjunganTamu::SCHEDULED) {
                    $query->whereRaw($siklus.' = ?', [StatusKunjunganTamu::SCHEDULED])
                        ->whereNotExists(function ($sub) {
                            $sub->select(DB::raw(1))
                                ->from('N_EMI_VISITOR_Transaksi_Permit as p')
                                ->whereColumn('p.No_Transaksi_Tamu', 'h.No_Transaksi')
                                ->whereNull('p.Flag_Approval')
                                ->where('p.Flag_Aktif', 'Y');
                        });
                } else {
                    $query->whereRaw($siklus.' = ?', [$status]);
                }
            }

            if ($dateFrom) {
                $query->whereDate('h.Tanggal_Kunjungan', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('h.Tanggal_Kunjungan', '<=', $dateTo);
            }

            $total = $query->count();
            $masuk = $this->kehadiran->estimasiMasukSql();

            $data = $query
                ->orderByRaw("{$masuk} ASC")
                ->orderBy('h.Id_Transaksi_Tamu', 'ASC')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(function ($row) {
                    $row->Kategori = MatriksTamuService::kategori($row->Tipe_Tamu, $row->B2B_Unit);
                    $row->Kategori_Label = MatriksTamuService::LABEL_KATEGORI[$row->Kategori] ?? $row->Kategori;
                    $row = $this->appendApprovalStatus($row);
                    $row->Foto_Kendaraan_Url = $this->fotoKendaraan->url($row->Path_Foto_Kendaraan ?? null);

                    return $row;
                });

            return ResponseHelper::successWithPaginationV2($data, $page, $limit, $total);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_schedule_list', 'VISITOR_GUEST_SCHEDULE_LIST_FAILED', 'Jadwal kedatangan tamu gagal dimuat.');
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $idTransaksi = VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, $id);
            if ($idTransaksi === null && is_numeric($id)) {
                $idTransaksi = (int) $id;
            }

            if ($idTransaksi === null) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $boleh = $this->matriks->kategoriBolehLihat($this->userIdWeb($request));
            $query = $this->baseTamuQuery()->where('h.Id_Transaksi_Tamu', $idTransaksi);
            MatriksTamuService::saring($query, $boleh);

            $data = $query->first();
            if (! $data) {
                return ResponseHelper::error('Data tamu tidak ditemukan atau di luar wewenang matriks departemen Anda.', 404);
            }

            $data->Kategori = MatriksTamuService::kategori($data->Tipe_Tamu, $data->B2B_Unit);
            $data->Kategori_Label = MatriksTamuService::LABEL_KATEGORI[$data->Kategori] ?? $data->Kategori;
            $data = $this->appendApprovalStatus($data);
            $data->Foto_Kendaraan_Url = $this->fotoKendaraan->url($data->Path_Foto_Kendaraan ?? null);

            if ($this->needsApproval($data)) {
                $data->Approvals = $this->getApprovals($data->No_Transaksi);
            }

            return ResponseHelper::success($data);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_schedule_detail', 'VISITOR_GUEST_SCHEDULE_DETAIL_FAILED', 'Detail tamu gagal dimuat.');
        }
    }

    public function getStats(Request $request)
    {
        try {
            $boleh = $this->matriks->kategoriBolehLihat($this->userIdWeb($request));
            $baseQuery = DB::table(self::T_HEADER.' as h')
                ->join(self::T_DETAIL.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->where('h.Flag_Aktif', 'Y');

            MatriksTamuService::saring($baseQuery, $boleh);

            $siklus = $this->kehadiran->siklusKunjunganSql();
            $stats = (clone $baseQuery)
                ->selectRaw("
                    COUNT(CASE WHEN {$siklus} = 'CHECKED_IN' THEN 1 END) as sedang_di_area,
                    COUNT(CASE WHEN CAST(h.Tanggal_Kunjungan as date) = CAST(GETDATE() as date) THEN 1 END) as hari_ini,
                    COUNT(CASE WHEN {$siklus} = 'PENDING_APPROVAL' THEN 1 END) as menunggu_persetujuan,
                    COUNT(CASE WHEN {$siklus} IN ('SCHEDULED', 'CHECKED_IN') THEN 1 END) as sudah_disetujui,
                    COUNT(CASE WHEN {$siklus} = 'CHECKED_OUT' THEN 1 END) as selesai_keluar,
                    COUNT(*) as total
                ")
                ->first();

            return ResponseHelper::success([
                'sedang_di_area' => (int) ($stats->sedang_di_area ?? 0),
                'hari_ini' => (int) ($stats->hari_ini ?? 0),
                'menunggu_persetujuan' => (int) ($stats->menunggu_persetujuan ?? 0),
                'sudah_disetujui' => (int) ($stats->sudah_disetujui ?? 0),
                'selesai_keluar' => (int) ($stats->selesai_keluar ?? 0),
                'total' => (int) ($stats->total ?? 0),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_schedule_stats', 'VISITOR_GUEST_SCHEDULE_STATS_FAILED', 'Ringkasan jadwal kedatangan gagal dimuat.');
        }
    }

    public function data(Request $request)
    {
        try {
            $boleh = $this->matriks->kategoriBolehLihat($this->userIdWeb($request));
            $diminta = strtoupper(trim((string) $request->input('kategori', '')));

            // Kategori di luar matriks tidak ditolak dengan galat — ia disaring
            // habis. Menolak dengan 403 memberi tahu bahwa kategori itu ADA.
            $kategori = $diminta !== '' ? array_values(array_intersect($boleh, [$diminta])) : $boleh;

            $dari = Carbon::parse($request->input('dari', Carbon::today()->toDateString()))->startOfDay();
            $sampai = Carbon::parse($request->input('sampai', $dari->toDateString()))->startOfDay();
            if ($sampai->lt($dari)) {
                [$dari, $sampai] = [$sampai, $dari];
            }
            if ($dari->diffInDays($sampai) > self::RENTANG_MAKS_HARI) {
                $sampai = $dari->copy()->addDays(self::RENTANG_MAKS_HARI);
            }

            $masuk = $this->kehadiran->estimasiMasukSql();
            $keluar = $this->kehadiran->estimasiKeluarSql();
            $cari = trim((string) $request->input('cari', ''));

            $query = $this->kehadiran->join(
                DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                    ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                    ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
                    ->where('h.Flag_Aktif', 'Y')
                    ->whereNotIn('h.Status_Kunjungan', [
                        StatusKunjunganTamu::REJECTED,
                        StatusKunjunganTamu::WITHDRAWN,
                        StatusKunjunganTamu::REVOKED,
                    ])
                    // Rentang estimasi beririsan dengan jendela yang diminta.
                    ->whereRaw("{$masuk} < DATEADD(day, 1, CAST(? AS date))", [$sampai->toDateString()])
                    ->whereRaw("{$keluar} > CAST(? AS date)", [$dari->toDateString()])
            );

            MatriksTamuService::saring($query, $kategori);

            if ($cari !== '') {
                $suka = '%'.$cari.'%';
                $query->where(function ($q) use ($suka) {
                    $q->where('d.Nama_Lengkap_Tamu', 'LIKE', $suka)
                        ->orWhere('d.Instansi_Atau_Asal', 'LIKE', $suka)
                        ->orWhere('h.No_Transaksi', 'LIKE', $suka)
                        ->orWhereRaw($this->permitQuery->platNomorSql('d').' LIKE ?', [$suka]);
                });
            }

            $baris = $query
                ->select(
                    'h.Id_Transaksi_Tamu',
                    'h.No_Transaksi',
                    DB::raw("CONVERT(varchar(16), {$masuk}, 120) as Estimasi_Masuk"),
                    DB::raw("CONVERT(varchar(16), {$keluar}, 120) as Estimasi_Keluar"),
                    'd.Nama_Lengkap_Tamu as Nama_Tamu',
                    'd.Instansi_Atau_Asal as Instansi',
                    'd.Jumlah_Tamu',
                    'd.Deksripsi_Keperluan as Keperluan',
                    'd.Keterangan_Tujuan_Detail as PIC_Tujuan',
                    'd.Jenis_Kendaraan',
                    'd.Tipe_Tamu',
                    'd.B2B_Unit',
                    'd.Tamu_Kategori',
                    'd.Path_Foto_Kendaraan',
                    DB::raw("ISNULL(k.Nama, '-') as Nama_PIC"),
                    DB::raw($this->permitQuery->platNomorSql('d').' as No_Plat'),
                    DB::raw($this->kehadiran->siklusKunjunganSql().' as Status_Kunjungan'),
                    DB::raw('CONVERT(varchar(16), '.$this->kehadiran->masukSql().', 120) as Waktu_Aktual_Masuk'),
                    DB::raw('CONVERT(varchar(16), '.$this->kehadiran->keluarSql().', 120) as Waktu_Aktual_Keluar'),
                    DB::raw($this->permitQuery->statusPermitKunjunganSql('h.No_Transaksi', 'IZIN').' as Status_Permit_Coklat'),
                )
                ->orderByRaw($masuk)
                ->orderBy('h.Id_Transaksi_Tamu')
                ->limit(500)
                ->get()
                ->map(function ($row) {
                    $row->Kategori = MatriksTamuService::kategori($row->Tipe_Tamu, $row->B2B_Unit);
                    $row->Foto_Kendaraan_Url = $this->fotoKendaraan->url($row->Path_Foto_Kendaraan);
                    unset($row->Path_Foto_Kendaraan);

                    return $row;
                });

            return ResponseHelper::success([
                'baris' => $baris,
                'dari' => $dari->toDateString(),
                'sampai' => $sampai->toDateString(),
                'kategori' => $kategori,
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_schedule', 'VISITOR_GUEST_SCHEDULE_FAILED', 'Jadwal kedatangan tamu gagal dimuat.');
        }
    }

    protected function baseTamuQuery()
    {
        return DB::table(self::T_HEADER.' as h')
            ->leftJoin(self::T_DETAIL.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'h.No_Transaksi', '=', 'e.No_Transaksi')
            ->leftJoin('Karyawan as k', 'h.Kode_Karyawan_PIC', '=', 'k.Kode_Karyawan')
            ->where('h.Flag_Aktif', 'Y')
            ->select(
                'h.Id_Transaksi_Tamu as Id',
                'h.Id_Transaksi_Tamu',
                'h.No_Transaksi',
                'h.Kode_Karyawan_PIC',
                'h.Tanggal_Kunjungan',
                'h.Status_Kunjungan as Status_Pengajuan',
                DB::raw($this->kehadiran->siklusKunjunganSql().' as Status_Kunjungan'),
                'h.Needs_Approval',
                'h.Created_At',
                'd.Nama_Lengkap_Tamu as Nama_Tamu',
                'd.Nama_Lengkap_Tamu',
                'd.Tipe_Tamu',
                'd.Tamu_Kategori',
                'd.B2B_Unit',
                'd.Path_Foto_Kendaraan',
                'd.Instansi_Atau_Asal as Instansi_Asal',
                'd.Instansi_Atau_Asal',
                'd.Nomor_Handphone as No_HP',
                'd.Nomor_Handphone',
                'd.Deksripsi_Keperluan',
                'd.Keterangan_Tujuan_Detail as PIC_Tujuan',
                'd.Keterangan_Tambahan',
                'd.Keterangan_Tambahan as Keterangan',
                'd.Jumlah_Tamu',
                'd.Daftar_Anggota_Json',
                DB::raw('COALESCE(e.Waktu_Aktual_Masuk, d.Waktu_Aktual_Masuk) as Waktu_Aktual_Masuk'),
                DB::raw('COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar) as Waktu_Aktual_Keluar'),
                'd.No_Identitas as No_Identitas',
                'd.Jenis_Identitas as Jenis_Identitas',
                DB::raw(
                    "LTRIM(RTRIM(CONCAT(ISNULL(d.Plat_Nomor_Prefix, ''), ' ', ISNULL(d.Plat_Nomor_Angka, ''), ' ', ISNULL(d.Plat_Nomor_Suffix, '')))) as No_Plat_Kendaraan",
                ),
                'd.Jenis_Kendaraan',
                'd.Waktu_Estimasi_Kunjungan',
                'd.Waktu_Mulai_Kunjungan',
                'd.Waktu_Selesai_Kunjungan',
                DB::raw("ISNULL(k.Nama, '-') as Nama_PIC"),
                DB::raw($this->permitQuery->statusPermitKunjunganSql('h.No_Transaksi', 'PROSES').' as Status_Permit_Coklat'),
                DB::raw(
                    "(SELECT TOP 1 p.Id_Transaksi_Permit
                        FROM N_EMI_VISITOR_Transaksi_Permit p
                       WHERE p.No_Transaksi_Tamu = h.No_Transaksi
                         AND p.Flag_Aktif = 'Y') as Id_Transaksi_Permit",
                ),
                DB::raw(
                    "(SELECT TOP 1 p.No_Transaksi
                        FROM N_EMI_VISITOR_Transaksi_Permit p
                       WHERE p.No_Transaksi_Tamu = h.No_Transaksi
                         AND p.Flag_Aktif = 'Y') as No_Transaksi_Permit",
                ),
            );
    }

    protected function appendApprovalStatus(object $row): object
    {
        $hasPendingPermit = ($row->Tipe_Tamu === TipeTamu::B2B || ! empty($row->Status_Permit_Coklat))
            && in_array($row->Status_Permit_Coklat ?? 'MENUNGGU_APPROVAL', ['MENUNGGU_APPROVAL', 'TANPA_AKSES', null], true)
            && $row->Status_Kunjungan === StatusKunjunganTamu::SCHEDULED;

        if ($this->needsApproval($row)) {
            $row->Status_Approval = $this->visitorApprovalPolicy->approvalStatus($row->No_Transaksi);
            $row->Status_Approval_Label = $this->approvalStatusLabel($row->Status_Approval);
        } elseif ($hasPendingPermit) {
            $row->Status_Approval = 'PENDING';
            $row->Status_Approval_Label = 'Menunggu Persetujuan Permit';
        } else {
            $row->Status_Approval = 'APPROVED';
            $row->Status_Approval_Label = 'Langsung Aktif (Tanpa Approval)';
        }

        return $row;
    }

    protected function needsApproval(object $row): bool
    {
        return strtoupper((string) ($row->Needs_Approval ?? 'T')) === 'Y';
    }

    protected function approvalStatusLabel(string $status): string
    {
        return match ($status) {
            'APPROVED' => 'Disetujui',
            'REJECTED' => 'Ditolak',
            'PENDING' => 'Menunggu Approval',
            'EXPIRED' => 'Expired',
            'NO_FLOW' => 'Flow Approval Tidak Tersedia',
            default => $status,
        };
    }

    protected function getApprovals(string $noTransaksi)
    {
        return DB::table('HRIS_Approval_Request as ar')
            ->leftJoin('Karyawan as k', 'ar.Approver_Karyawan_Kode', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as approved_by', 'ar.Approved_By_Kode', '=', 'approved_by.Kode_Karyawan')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
            ->leftJoin('N_HRIS_Role_Functional as rf', function ($join) {
                $join->on('rf.Id_Role', '=', 'ar.Group_Reference_Id')->where('rf.Flag_Aktif', 'Y');
            })
            ->where('ar.No_Transaksi', $noTransaksi)
            ->orderBy('ar.Request_Order_Flow')
            ->select(
                'ar.*',
                'ja.Nama_Approval',
                'ja.Source_Type',
                'ja.Source_Reference',
                'approved_by.Nama as Nama_Approved_By',
                'k.Nama as Nama_Approver_Karyawan',
                'rf.Role_Name as Nama_Role_Functional',
                DB::raw('CASE WHEN ar.Flag_Approval IS NULL AND ar.Expired_At IS NOT NULL AND ar.Expired_At < GETDATE() THEN 1 ELSE 0 END as Is_Expired'),
                DB::raw('COALESCE(approved_by.Nama, k.Nama, rf.Role_Name, ja.Nama_Approval) as Nama_Approver'),
            )
            ->get();
    }

    private function userIdWeb(Request $request): ?int
    {
        $id = $request->user()?->Id_Users ?? null;

        return $id !== null ? (int) $id : null;
    }
}
