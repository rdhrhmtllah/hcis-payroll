<?php

namespace App\Http\Controllers\VISITOR\Permit\Inspection;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\Inspection\KontakPemohonInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Agenda Inspeksi SHE (DDL-2026-123).
 *
 * Satu agenda kerja di pos: booking inspeksi permit karyawan (HIJAU/MERAH)
 * dan kedatangan tamu B2B berpermit COKLAT pada hari yang sama. Inspeksinya
 * sendiri tetap dikerjakan di meja review permit yang sudah ada — halaman ini
 * menautkannya, tidak menduplikasi formnya.
 */
class AgendaInspeksiController extends Controller
{
    /** Status kunjungan yang berarti tamunya sudah tercatat melewati palang. */
    private const SUDAH_LEWAT_GERBANG = [
        StatusKunjunganTamu::CHECKED_IN,
        StatusKunjunganTamu::CHECKED_OUT,
        StatusKunjunganTamu::KELUAR_TAK_TERCATAT,
    ];

    public function __construct(
        protected JadwalInspeksiService $jadwal,
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorKehadiranQueryService $kehadiran,
        protected VisitorApprovalPolicyService $approvalPolicy,
        protected KontakPemohonInspeksiService $kontak,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('VISITOR/Permit/Inspection/AgendaInspeksiPage', [
            'user' => $request->user(),
            'serverDate' => Carbon::today()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        try {
            $tanggal = Carbon::parse($request->input('tanggal', Carbon::today()->toDateString()))->startOfDay();
            $besok = $tanggal->copy()->addDay();
            $plat = $this->permitQuery->platNomorSql('d');

            $permit = DB::table('N_VISITOR_Inspeksi_Booking as b')
                ->join('N_EMI_VISITOR_Transaksi_Permit as h', 'h.No_Transaksi', '=', 'b.No_Transaksi_Permit')
                ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_Pemohon')
                ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Model as vm', 'vm.Id_Vehicle_Model', '=', 'd.Id_Master_Model')
                ->leftJoin(JadwalInspeksiService::T_POS.' as mp', 'mp.Id_Pos_Inspeksi', '=', 'b.Id_Pos_Inspeksi')
                ->leftJoin(JadwalInspeksiService::T_SHIFT.' as ms', 'ms.Id_Shift_Inspeksi', '=', 'b.Id_Shift_Inspeksi')
                ->whereIn('b.Status', [JadwalInspeksiService::TERJADWAL, JadwalInspeksiService::SELESAI, JadwalInspeksiService::TIDAK_HADIR])
                // Permitnya harus masih hidup.
                //
                // Status booking saja tidak cukup: jendela approval yang lewat
                // tanpa diputus mematikan permitnya, tetapi tidak menyentuh
                // booking-nya. Tanpa penyaring ini SHE menunggu orang yang
                // sudah tidak mungkin disetujui, dan meja verifikator berkata
                // 'EXPIRED' untuk pengajuan yang di agenda masih 'TERJADWAL'.
                //
                // Disaring saat MEMBACA, bukan hanya menunggu penyapu: penyapu
                // berjalan tiap lima menit dan agenda harus benar di antaranya.
                ->whereRaw($this->permitQuery->masihPantasBerjadwalSql())
                ->where(function ($q) use ($tanggal, $besok) {
                    if ($tanggal->isToday()) {
                        $q->where(function ($sub) use ($tanggal, $besok) {
                            $sub->where('b.Waktu_Mulai', '>=', $tanggal)
                                ->where('b.Waktu_Mulai', '<', $besok);
                        })->orWhere(function ($sub) use ($tanggal) {
                            $sub->where('b.Waktu_Mulai', '<', $tanggal)
                                ->where('b.Status', JadwalInspeksiService::TERJADWAL)
                                ->whereNull('h.Flag_Approval');
                        });
                    } else {
                        $q->where('b.Waktu_Mulai', '>=', $tanggal)
                            ->where('b.Waktu_Mulai', '<', $besok);
                    }
                })
                ->select(
                    'b.Id_Inspeksi_Booking',
                    'b.Status as Status_Inspeksi',
                    'b.Id_Pos_Inspeksi',
                    'b.Snapshot_Nama_Pos as Pos_Inspeksi',
                    'mp.Keterangan_Lokasi as Lokasi_Pos',
                    'ms.Nama_Shift as Shift',
                    DB::raw('CONVERT(varchar(16), b.Waktu_Mulai, 120) as Mulai'),
                    DB::raw('CONVERT(varchar(16), b.Waktu_Selesai, 120) as Selesai'),
                    'h.Id_Transaksi_Permit',
                    'h.No_Transaksi',
                    'h.Flag_Approval',
                    // Booking TIDAK_HADIR lama boleh dipindah hanya bila pemohon
                    // belum memesan slot baru; yang TERJADWAL selalu yang berlaku.
                    DB::raw("CASE WHEN EXISTS (SELECT 1 FROM N_VISITOR_Inspeksi_Booking b2 WHERE b2.No_Transaksi_Permit = h.No_Transaksi AND b2.Status = '".JadwalInspeksiService::TERJADWAL."') THEN 'Y' ELSE 'T' END as Ada_Terjadwal"),
                    'd.Snapshot_Kategori_Permit as Kategori',
                    DB::raw('ISNULL(k.Nama, h.Kode_Karyawan_Pemohon) as Nama'),
                    'k.HP as No_HP',
                    DB::raw("ISNULL(vm.Nama_Model_Kendaraan, '-') as Kendaraan"),
                    DB::raw("{$plat} as No_Plat"),
                )
                ->orderBy('b.Waktu_Mulai')
                ->orderBy('b.Id_Inspeksi_Booking')
                ->get()
                ->map(function ($r) {
                    $r->Jenis = 'PERMIT';
                    // Pemohon yang terlambat/tidak datang bisa langsung dihubungi SHE.
                    $r->Kontak = $this->kontak->susun(
                        ['nama' => $r->Nama, 'no_hp' => $r->No_HP],
                        jadwal: ['plat' => $r->No_Plat, 'pos' => $r->Pos_Inspeksi, 'mulai' => $r->Mulai],
                    );
                    unset($r->No_HP);
                    $r->Tautan_Review = '/verifikator-visitor/permit?detail='
                        .VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, (int) $r->Id_Transaksi_Permit);
                    $r->Boleh_Tidak_Hadir = $r->Status_Inspeksi === JadwalInspeksiService::TERJADWAL
                        && Carbon::parse($r->Mulai)->lte(now());
                    $r->Boleh_Pindah = $r->Flag_Approval === null
                        && ($r->Status_Inspeksi === JadwalInspeksiService::TERJADWAL
                            || ($r->Status_Inspeksi === JadwalInspeksiService::TIDAK_HADIR && $r->Ada_Terjadwal === 'T'));

                    return $r;
                });

            $masuk = $this->kehadiran->estimasiMasukSql();
            $keluar = $this->kehadiran->estimasiKeluarSql();

            $coklat = $this->kehadiran->join(
                DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
                    ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                    ->join('N_EMI_VISITOR_Transaksi_Permit as p', function ($j) {
                        $j->on('p.No_Transaksi_Tamu', '=', 'h.No_Transaksi')->where('p.Flag_Aktif', '=', 'Y');
                    })
                    // Karyawan PIC tamu; cadangannya karyawan pengaju permitnya.
                    ->leftJoin('Karyawan as kpic', 'kpic.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
                    ->leftJoin('Karyawan as kpem', 'kpem.Kode_Karyawan', '=', 'p.Kode_Karyawan_Pemohon')
                    ->where('h.Flag_Aktif', 'Y')
                    ->where('d.Tipe_Tamu', 'B2B')
                    ->whereNotIn('h.Status_Kunjungan', [
                        StatusKunjunganTamu::REJECTED,
                        StatusKunjunganTamu::WITHDRAWN,
                        StatusKunjunganTamu::REVOKED,
                    ])
                    ->whereRaw("{$masuk} < ?", [$besok])
                    ->whereRaw("{$keluar} > ?", [$tanggal])
            )
                ->select(
                    'p.Id_Transaksi_Permit',
                    'p.No_Transaksi',
                    'p.Flag_Approval',
                    'h.No_Transaksi as No_Transaksi_Tamu',
                    'd.Nama_Lengkap_Tamu as Nama',
                    'd.Instansi_Atau_Asal as Instansi',
                    'd.B2B_Unit',
                    'd.Nomor_Handphone as No_HP_Tamu',
                    DB::raw('COALESCE(kpic.Nama, kpem.Nama) as Nama_PIC'),
                    DB::raw('COALESCE(kpic.HP, kpem.HP) as No_HP_PIC'),
                    DB::raw("ISNULL(d.Jenis_Kendaraan, '-') as Kendaraan"),
                    DB::raw("{$plat} as No_Plat"),
                    DB::raw("CONVERT(varchar(16), {$masuk}, 120) as Mulai"),
                    DB::raw("CONVERT(varchar(16), {$keluar}, 120) as Selesai"),
                    DB::raw($this->kehadiran->siklusKunjunganSql().' as Status_Kunjungan'),
                )
                ->orderByRaw($masuk)
                ->get();

            /*
             * Hasil inspeksi kedatangan tamu B2B, bila sudah pernah dicatat.
             *
             * Diambil terpisah, bukan lewat JOIN pada kueri di atas: kueri itu
             * sudah menumpuk tiga tabel plus dua ekspresi waktu, dan barisnya
             * sedikit (satu hari kedatangan). Yang penting posnya TIDAK
             * diturunkan dari booking melainkan dari setelan, supaya baris yang
             * belum dicatat pun tetap memberitahu SHE harus berjaga di mana.
             */
            $hasilTamu = $coklat->isEmpty()
                ? collect()
                : DB::table('N_VISITOR_Inspeksi_Booking')
                    ->whereIn('No_Transaksi_Permit', $coklat->pluck('No_Transaksi')->all())
                    ->whereIn('Status', [JadwalInspeksiService::SELESAI, JadwalInspeksiService::TIDAK_HADIR])
                    ->orderBy('Id_Inspeksi_Booking')
                    ->get()
                    ->keyBy('No_Transaksi_Permit');

            $posTamu = DB::table(JadwalInspeksiService::T_POS)
                ->where('Id_Pos_Inspeksi', JadwalInspeksiService::posTamuB2b())
                ->where('Flag_Aktif', 'Y')
                ->first();

            $coklat = $coklat->map(function ($r) use ($hasilTamu, $posTamu) {
                $r->Jenis = 'COKLAT';
                $r->Kategori = 'COKLAT';
                $r->Tautan_Review = '/verifikator-visitor/permit?detail='
                    .VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, (int) $r->Id_Transaksi_Permit);

                $hasil = $hasilTamu[$r->No_Transaksi] ?? null;
                $r->Status_Inspeksi = $hasil->Status ?? null;

                // Persetujuan permit MENUTUP inspeksi tamu (approvePermit →
                // tandaiSelesaiKedatanganTamu). Permit yang disetujui tanpa
                // baris hasil — disetujui sebelum pos tamu disetel, atau lewat
                // persetujuan otomatis — bukan pekerjaan SHE yang menggantung:
                // tanpa ini barisnya terus menawarkan "Review & Inspeksi" dan
                // "Tamu Tidak Hadir" untuk tamu yang sudah keluar (30 Sep 2026).
                if ($r->Status_Inspeksi === null && $r->Flag_Approval === 'Y') {
                    $r->Status_Inspeksi = JadwalInspeksiService::SELESAI;
                }

                // Tamu yang sudah tercatat lewat palang jelas hadir.
                $sudahLewatGerbang = in_array($r->Status_Kunjungan, self::SUDAH_LEWAT_GERBANG, true);
                $r->Catatan_Inspeksi = $hasil->Alasan ?? null;
                $r->Pos_Inspeksi = $posTamu->Nama_Pos ?? null;
                $r->Id_Pos_Inspeksi = $posTamu ? (int) $posTamu->Id_Pos_Inspeksi : null;
                $r->Kontak = $this->kontak->susun(
                    tamu: ['nama' => $r->Nama, 'no_hp' => $r->No_HP_Tamu],
                    pic: ['nama' => $r->Nama_PIC, 'no_hp' => $r->No_HP_PIC],
                    jadwal: ['plat' => $r->No_Plat, 'pos' => $r->Pos_Inspeksi, 'mulai' => $r->Mulai],
                );
                unset($r->No_HP_Tamu, $r->Nama_PIC, $r->No_HP_PIC);

                /*
                 * Hanya SATU izin yang dikirim, dan itu untuk ketidakhadiran.
                 *
                 * "Selesai" bukan aksi agenda: inspeksinya dikerjakan di
                 * halaman review, dan KEPUTUSAN permit di sanalah yang
                 * menutupnya (VisitorApprovalDecisionService::approvePermit →
                 * tandaiSelesaiKedatanganTamu). Menaruh tombolnya di sini
                 * berarti menyediakan jalan menutup pekerjaan SHE di atas
                 * pengajuan yang masih menggantung di meja verifikator.
                 *
                 * Ketidakhadiran tetap boleh dicatat kapan pun selagi permitnya
                 * belum ditolak — itu satu-satunya cara menutup baris tamu yang
                 * orangnya tidak pernah datang.
                 */
                $r->Boleh_Tidak_Hadir_Tamu = $posTamu !== null
                    && $r->Flag_Approval !== 'T'
                    && $r->Status_Inspeksi !== JadwalInspeksiService::SELESAI
                    && $r->Status_Inspeksi !== JadwalInspeksiService::TIDAK_HADIR
                    && ! $sudahLewatGerbang;

                return $r;
            });

            return ResponseHelper::success([
                'tanggal' => $tanggal->toDateString(),
                'permit' => $permit,
                'coklat' => $coklat,
                'slot' => $this->jadwal->slotRentang($tanggal, $tanggal),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'inspection_agenda', 'VISITOR_INSPECTION_AGENDA_FAILED', 'Agenda inspeksi gagal dimuat.');
        }
    }

    /**
     * Booking yang jamnya sudah lewat tetapi hasilnya belum dicatat siapa pun.
     *
     * Agenda bekerja per tanggal, jadi yang terlewat kemarin lenyap dari
     * pandangan persis ketika ia paling perlu ditindak. Daftar ini lintas
     * tanggal, dan SENGAJA tidak mengubah apa pun: keputusan tetap manusia —
     * SHE mencatat hasilnya, verifikator memutus permitnya.
     */
    public function belumDicatat()
    {
        try {
            $booking = $this->jadwal->belumDicatat();
            $tamu = $this->kedatanganTamuBelumDicatat();

            if ($booking->isEmpty()) {
                return ResponseHelper::success(['daftar' => $tamu->values()]);
            }

            $plat = $this->permitQuery->platNomorSql('d');
            $rinci = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
                ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_Pemohon')
                ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Model as vm', 'vm.Id_Vehicle_Model', '=', 'd.Id_Master_Model')
                ->whereIn('h.No_Transaksi', $booking->pluck('No_Transaksi_Permit'))
                ->select(
                    'h.No_Transaksi',
                    'h.Id_Transaksi_Permit',
                    'd.Snapshot_Kategori_Permit as Kategori',
                    DB::raw('ISNULL(k.Nama, h.Kode_Karyawan_Pemohon) as Nama'),
                    'k.HP as No_HP',
                    DB::raw("ISNULL(vm.Nama_Model_Kendaraan, '-') as Kendaraan"),
                    DB::raw("{$plat} as No_Plat"),
                )
                ->get()
                ->keyBy('No_Transaksi');

            $daftar = $booking->map(function ($b) use ($rinci) {
                $p = $rinci->get($b->No_Transaksi_Permit);

                return [
                    'Jenis' => 'PERMIT',
                    'No_Transaksi' => $b->No_Transaksi_Permit,
                    'Pos_Inspeksi' => $b->Snapshot_Nama_Pos,
                    'Mulai' => substr((string) $b->Waktu_Mulai, 0, 16),
                    'Selesai' => substr((string) $b->Waktu_Selesai, 0, 16),
                    'Terlambat_Hari' => Carbon::parse($b->Waktu_Mulai)->startOfDay()->diffInDays(Carbon::today()),
                    'Nama' => $p->Nama ?? '-',
                    'Kategori' => $p->Kategori ?? '-',
                    'Kendaraan' => $p->Kendaraan ?? '-',
                    'No_Plat' => $p->No_Plat ?? '-',
                    'Kontak' => $p ? $this->kontak->susun(
                        ['nama' => $p->Nama, 'no_hp' => $p->No_HP],
                        jadwal: ['plat' => $p->No_Plat, 'pos' => $b->Snapshot_Nama_Pos, 'mulai' => substr((string) $b->Waktu_Mulai, 0, 16)],
                    ) : [],
                    'Tautan_Review' => $p
                        ? '/verifikator-visitor/permit?detail='.VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, (int) $p->Id_Transaksi_Permit)
                        : null,
                ];
            });

            return ResponseHelper::success([
                'daftar' => $daftar->concat($tamu)->sortBy('Mulai')->values(),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'inspection_overdue', 'VISITOR_INSPECTION_OVERDUE_FAILED', 'Daftar inspeksi lewat jadwal gagal dimuat.');
        }
    }

    /**
     * Kedatangan tamu B2B yang jendela kunjungannya sudah lewat tanpa hasil
     * inspeksi. Tamu tidak pernah punya booking TERJADWAL, sehingga daftar
     * booking di atas buta terhadap mereka.
     *
     * Hanya permit yang MASIH MENUNGGU: yang ditolak tak butuh apa pun, dan
     * approve sendiri menandai kedatangannya SELESAI — baris disetujui tanpa
     * hasil hanyalah sisa sebelum aturan itu ada, bukan pekerjaan SHE.
     */
    private function kedatanganTamuBelumDicatat(): \Illuminate\Support\Collection
    {
        $posTamu = DB::table(JadwalInspeksiService::T_POS)
            ->where('Id_Pos_Inspeksi', JadwalInspeksiService::posTamuB2b())
            ->where('Flag_Aktif', 'Y')
            ->first();

        $masuk = $this->kehadiran->estimasiMasukSql();
        $keluar = $this->kehadiran->estimasiKeluarSql();
        // Sama dengan baris tamu di agenda harian (detail tamu berkolom plat yang sama).
        $plat = $this->permitQuery->platNomorSql('d');

        return $this->kehadiran->join(DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->join('N_EMI_VISITOR_Transaksi_Permit as p', function ($j) {
                $j->on('p.No_Transaksi_Tamu', '=', 'h.No_Transaksi')->where('p.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('Karyawan as kpic', 'kpic.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
            ->leftJoin('Karyawan as kpem', 'kpem.Kode_Karyawan', '=', 'p.Kode_Karyawan_Pemohon'))
            ->where('h.Flag_Aktif', 'Y')
            ->where('d.Tipe_Tamu', 'B2B')
            ->whereNull('p.Flag_Approval')
            ->whereNotIn('h.Status_Kunjungan', [
                StatusKunjunganTamu::REJECTED,
                StatusKunjunganTamu::WITHDRAWN,
                StatusKunjunganTamu::REVOKED,
            ])
            // Jam aplikasi, bukan SYSDATETIME(): satu sumber waktu dengan sisa agenda.
            ->whereRaw("{$keluar} < ?", [now()])
            ->whereNotExists(fn ($q) => $q->from('N_VISITOR_Inspeksi_Booking as b')
                ->whereColumn('b.No_Transaksi_Permit', 'p.No_Transaksi')
                ->whereIn('b.Status', [JadwalInspeksiService::SELESAI, JadwalInspeksiService::TIDAK_HADIR]))
            ->select(
                'p.Id_Transaksi_Permit',
                'p.No_Transaksi',
                'd.Nama_Lengkap_Tamu as Nama',
                'd.Instansi_Atau_Asal as Instansi',
                'd.B2B_Unit',
                'd.Nomor_Handphone as No_HP_Tamu',
                DB::raw('COALESCE(kpic.Nama, kpem.Nama) as Nama_PIC'),
                DB::raw('COALESCE(kpic.HP, kpem.HP) as No_HP_PIC'),
                DB::raw("ISNULL(d.Jenis_Kendaraan, '-') as Kendaraan"),
                DB::raw("{$plat} as No_Plat"),
                DB::raw("CONVERT(varchar(16), {$masuk}, 120) as Mulai"),
                DB::raw("CONVERT(varchar(16), {$keluar}, 120) as Selesai"),
                DB::raw($this->kehadiran->masukSql().' as Waktu_Masuk'),
            )
            ->orderByRaw($keluar)
            ->get()
            ->map(fn ($r) => [
                'Jenis' => 'COKLAT',
                'No_Transaksi' => $r->No_Transaksi,
                'Pos_Inspeksi' => $posTamu->Nama_Pos ?? null,
                'Mulai' => $r->Mulai,
                'Selesai' => $r->Selesai,
                'Terlambat_Hari' => Carbon::parse($r->Selesai)->startOfDay()->diffInDays(Carbon::today()),
                'Nama' => $r->Nama ?? '-',
                'Instansi' => $r->Instansi,
                'B2B_Unit' => $r->B2B_Unit,
                'Kategori' => 'COKLAT',
                'Kendaraan' => $r->Kendaraan,
                'No_Plat' => $r->No_Plat ?: '-',
                'Kontak' => $this->kontak->susun(
                    tamu: ['nama' => $r->Nama, 'no_hp' => $r->No_HP_Tamu],
                    pic: ['nama' => $r->Nama_PIC, 'no_hp' => $r->No_HP_PIC],
                    jadwal: ['plat' => $r->No_Plat, 'pos' => $posTamu->Nama_Pos ?? null, 'mulai' => $r->Mulai],
                ),
                'Tautan_Review' => '/verifikator-visitor/permit?detail='
                    .VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, (int) $r->Id_Transaksi_Permit),
                // Sudah lewat palang = hadir; yang tersisa hanya reviewnya.
                'Boleh_Tidak_Hadir_Tamu' => $posTamu !== null && $r->Waktu_Masuk === null,
            ]);
    }

    /** Slot kosong untuk dialog pindah jadwal — sumber yang sama dengan form pemohon. */
    public function slot()
    {
        try {
            /* Lihat catatan pada VehiclePermitController::slotInspeksi. SHE
               justru paling butuh yang penuh: itulah jam yang perlu ditambah
               kuotanya. */
            $semua = request()->boolean('semua');
            $dari = Carbon::today();
            $sampai = Carbon::today()->addDays(JadwalInspeksiService::HARI_KE_DEPAN);

            return ResponseHelper::success([
                'slot' => $semua
                    ? $this->jadwal->slotBerkeadaan($dari, $sampai)
                    : $this->jadwal->slotTersedia($dari, $sampai),
            ]);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'inspection_slots', 'VISITOR_INSPECTION_SLOT_LIST_FAILED', 'Slot inspeksi gagal dimuat.');
        }
    }

    /** Petugas memindah jadwal inspeksi permit yang belum diputus. */
    public function pindah(Request $request, string $noTransaksi)
    {
        $request->validate([
            'inspeksi_id_pos' => 'required|integer',
            'inspeksi_mulai' => 'required|date_format:Y-m-d H:i',
            'alasan' => 'required|string|max:'.JadwalInspeksiService::MAKS_ALASAN,
        ], [], [
            'inspeksi_id_pos' => 'pos inspeksi',
            'inspeksi_mulai' => 'jadwal inspeksi',
            'alasan' => 'alasan pemindahan',
        ]);

        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');

        try {
            $booking = $this->jadwal->pindahOlehPetugas(
                $noTransaksi,
                (int) $request->input('inspeksi_id_pos'),
                (string) $request->input('inspeksi_mulai'),
                $oleh,
                (string) $request->input('alasan'),
            );
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_INSPECTION_RESCHEDULE_INVALID', 422);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'inspection_reschedule', 'VISITOR_INSPECTION_RESCHEDULE_FAILED', 'Jadwal inspeksi gagal dipindah.');
        }

        // Sesudah commit: notifikasi tidak boleh menahan transaksi.
        try {
            $mulai = Carbon::parse($booking->Waktu_Mulai);
            PublishVisitorNotificationsJob::dispatch([[
                'kind' => 'decision',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $noTransaksi,
                'decision' => VisitorNotificationService::KEPUTUSAN_JADWAL_DIPINDAH,
                'context' => [
                    'jadwal_baru' => $mulai->locale('id')->translatedFormat('l, d M Y H:i').' di '.$booking->Snapshot_Nama_Pos,
                    'alasan' => (string) $request->input('alasan'),
                    'id_booking' => (int) $booking->Id_Inspeksi_Booking,
                ],
            ]]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi pindah jadwal inspeksi', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);
        }

        return ResponseHelper::success(
            ['inspeksi' => $this->jadwal->ringkasan($noTransaksi)],
            'Jadwal inspeksi dipindah dan pemohon diberi tahu.',
        );
    }

    /**
     * Catat hasil inspeksi kedatangan tamu B2B di pos gerbang.
     *
     * Terpisah dari `tidakHadir()` milik permit karyawan, dan itu disengaja:
     * jalur itu menghitung ketidakhadiran dan MENGGUGURKAN permit pada hitungan
     * kedua. Yang tidak datang di sini adalah tamunya, bukan pemegang
     * permitnya, jadi memakai ulang jalur itu akan menggugurkan permit orang
     * atas kelalaian pihak lain.
     *
     * Nomor transaksi dibaca lewat NAMA parameter rutenya, bukan lewat urutan
     * argumen — lihat Tests\Feature\SYSTEM\Routing\ArgumenAksiTidakTertukarTest.
     */
    public function catatKedatanganTamu(Request $request)
    {
        $noTransaksi = (string) $request->route('noTransaksi');

        $data = $request->validate([
            'status' => 'required|in:'.JadwalInspeksiService::SELESAI.','.JadwalInspeksiService::TIDAK_HADIR,
            'catatan' => 'nullable|string|max:'.JadwalInspeksiService::MAKS_ALASAN,
        ]);

        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');

        $masuk = $this->kehadiran->estimasiMasukSql();
        $keluar = $this->kehadiran->estimasiKeluarSql();

        /* Jendela waktunya diambil dari KUNJUNGANNYA, bukan dari jam petugas
           menekan tombol: yang dicatat adalah kedatangan tamu itu, dan agenda
           hari berikutnya harus tetap menemukannya pada tanggal kunjungannya. */
        $jadwal = DB::table('N_EMI_VISITOR_Transaksi_Permit as p')
            ->join('N_EMI_VISITOR_Transaksi_Tamu as h', 'h.No_Transaksi', '=', 'p.No_Transaksi_Tamu')
            ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('p.No_Transaksi', $noTransaksi)
            ->where('p.Flag_Aktif', 'Y')
            ->selectRaw("{$masuk} as Mulai, {$keluar} as Selesai")
            ->first();

        if (! $jadwal) {
            return ResponseHelper::errorWithCode(
                'Permit tamu B2B tidak ditemukan atau tidak aktif.',
                'VISITOR_INSPECTION_GUEST_NOT_FOUND',
                404,
            );
        }

        try {
            $booking = $this->jadwal->catatKedatanganTamuB2b(
                $noTransaksi,
                $data['status'],
                Carbon::parse($jadwal->Mulai),
                Carbon::parse($jadwal->Selesai),
                $oleh,
                $data['catatan'] ?? null,
            );
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_INSPECTION_GUEST_CONFLICT', 409);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'inspection_guest_result', 'VISITOR_INSPECTION_GUEST_FAILED', 'Hasil inspeksi tamu gagal dicatat.');
        }

        // Tamu tidak datang: kabari PIC kunjungannya. Sesudah commit, dan
        // kegagalan notifikasi tidak boleh menggagalkan pencatatan.
        if ($data['status'] === JadwalInspeksiService::TIDAK_HADIR) {
            try {
                $noTamu = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $noTransaksi)->value('No_Transaksi_Tamu');
                if ($noTamu) {
                    PublishVisitorNotificationsJob::dispatch([[
                        'kind' => 'decision',
                        'domain' => VisitorNotificationService::DOMAIN_TAMU_B2B,
                        'no_transaksi' => (string) $noTamu,
                        'decision' => VisitorNotificationService::KEPUTUSAN_TAMU_TIDAK_HADIR,
                        'context' => ['alasan' => (string) ($data['catatan'] ?? '')],
                    ]]);
                }
            } catch (\Throwable $e) {
                Log::error('Gagal mengirim notifikasi tamu B2B tidak hadir', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);
            }
        }

        return ResponseHelper::success(
            ['booking' => $booking],
            $data['status'] === JadwalInspeksiService::SELESAI
                ? 'Inspeksi kedatangan tamu dicatat selesai.'
                : 'Tamu dicatat tidak hadir pada jadwal kunjungannya.',
        );
    }

    public function tidakHadir(Request $request, string $noTransaksi)
    {
        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');

        try {
            $hasil = $this->jadwal->tidakHadir($noTransaksi, $oleh);
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_INSPECTION_NO_SHOW_CONFLICT', 409);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'inspection_no_show', 'VISITOR_INSPECTION_NO_SHOW_FAILED', 'Ketidakhadiran gagal dicatat.');
        }

        // Sesudah commit: notifikasi tidak boleh menahan transaksi.
        try {
            PublishVisitorNotificationsJob::dispatch([[
                'kind' => 'decision',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $noTransaksi,
                'decision' => VisitorNotificationService::KEPUTUSAN_DITOLAK,
                'context' => ['alasan' => $hasil['gugur']
                    ? 'Pengajuan gugur: '.$hasil['jumlah'].' kali tidak hadir inspeksi kendaraan.'
                    : 'Anda tidak hadir pada jadwal inspeksi. Pilih jadwal inspeksi baru di halaman permit.'],
            ]]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi tidak hadir inspeksi', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);
        }

        return ResponseHelper::success($hasil, $hasil['gugur']
            ? 'Pemohon tidak hadir untuk kedua kalinya; pengajuan permit digugurkan.'
            : 'Ketidakhadiran dicatat; pemohon wajib memilih jadwal baru.');
    }

    /** Master pos, pola mingguan, dan pengecualian mendatang untuk layar Pengaturan Slot. */
    public function pola()
    {
        $pos = DB::table(JadwalInspeksiService::T_POS)
            ->orderByDesc('Flag_Aktif')->orderBy('Urutan')->orderBy('Nama_Pos')
            ->get();
        $namaPos = $pos->pluck('Nama_Pos', 'Id_Pos_Inspeksi');

        $shift = DB::table(JadwalInspeksiService::T_SHIFT)
            ->orderBy('Urutan')->orderBy('Nama_Shift')
            ->get();
        $mati = DB::table(JadwalInspeksiService::T_HARI_SHIFT)
            ->where('Flag_Aktif', 'T')
            ->get()
            ->map(fn ($r) => $r->Hari_Minggu.'|'.($r->Id_Shift_Inspeksi ?? ''))
            ->flip();

        return ResponseHelper::success([
            // Tiap hari memuat SELURUH shift; sakelarnya per (hari, shift) dan
            // per hari penuh. Tidak ada baris di tabel sakelar = aktif.
            'hari' => collect(range(1, 7))->map(fn ($h) => [
                'hari' => $h,
                'aktif' => ! $mati->has($h.'|'),
                'shift' => $shift->map(fn ($sh) => [
                    'id' => (int) $sh->Id_Shift_Inspeksi,
                    'aktif' => ! $mati->has($h.'|'.$sh->Id_Shift_Inspeksi),
                ])->values(),
            ])->values(),
            'shift' => DB::table(JadwalInspeksiService::T_SHIFT)
                ->orderBy('Urutan')->orderBy('Nama_Shift')
                ->get()
                ->map(fn ($sh) => [
                    'id' => (int) $sh->Id_Shift_Inspeksi,
                    'nama' => $sh->Nama_Shift,
                    'jam_mulai' => substr((string) $sh->Jam_Mulai, 0, 5),
                    'jam_selesai' => substr((string) $sh->Jam_Selesai, 0, 5),
                    'urutan' => (int) $sh->Urutan,
                    'aktif' => $sh->Flag_Aktif === 'Y',
                ])->values(),
            'pos' => $pos->map(fn ($p) => [
                'id' => (int) $p->Id_Pos_Inspeksi,
                'nama' => $p->Nama_Pos,
                'lokasi' => $p->Keterangan_Lokasi,
                'urutan' => (int) $p->Urutan,
                'aktif' => $p->Flag_Aktif === 'Y',
            ])->values(),
            'pola' => DB::table('N_VISITOR_Inspeksi_Pola')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Shift_Inspeksi')->orderBy('Id_Pos_Inspeksi')->orderBy('Hari_Minggu')->orderBy('Jam_Mulai')
                ->get()
                ->map(fn ($p) => [
                    'id' => (int) $p->Id_Inspeksi_Pola,
                    'id_pos' => (int) $p->Id_Pos_Inspeksi,
                    'id_shift' => (int) $p->Id_Shift_Inspeksi,
                    'hari' => (int) $p->Hari_Minggu,
                    'jam_mulai' => substr((string) $p->Jam_Mulai, 0, 5),
                    'jam_selesai' => substr((string) $p->Jam_Selesai, 0, 5),
                    'durasi' => (int) $p->Durasi_Menit,
                    'kuota' => (int) $p->Kuota_Per_Slot,
                ]),
            'pengecualian' => DB::table('N_VISITOR_Inspeksi_Pengecualian')
                ->where('Flag_Aktif', 'Y')
                ->where('Tanggal', '>=', Carbon::today()->toDateString())
                ->orderBy('Tanggal')
                ->get()
                ->map(fn ($p) => [
                    'id' => (int) $p->Id_Inspeksi_Pengecualian,
                    'tanggal' => substr((string) $p->Tanggal, 0, 10),
                    'id_shift' => $p->Id_Shift_Inspeksi !== null ? (int) $p->Id_Shift_Inspeksi : null,
                    'id_pos' => $p->Id_Pos_Inspeksi !== null ? (int) $p->Id_Pos_Inspeksi : null,
                    'pos' => $p->Id_Pos_Inspeksi !== null ? $namaPos->get($p->Id_Pos_Inspeksi) : null,
                    'jenis' => $p->Jenis,
                    'jam_mulai' => $p->Jam_Mulai ? substr((string) $p->Jam_Mulai, 0, 5) : null,
                    'jam_selesai' => $p->Jam_Selesai ? substr((string) $p->Jam_Selesai, 0, 5) : null,
                    'durasi' => $p->Durasi_Menit !== null ? (int) $p->Durasi_Menit : null,
                    'kuota' => $p->Kuota_Per_Slot !== null ? (int) $p->Kuota_Per_Slot : null,
                    'keterangan' => $p->Keterangan,
                ]),
        ]);
    }

    /**
     * Tambah atau ubah satu pos inspeksi. Nama unik (juga dijaga indeks unik):
     * dua pos bernama sama membuat pemohon tidak tahu harus ke mana.
     */
    public function simpanPos(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:'.JadwalInspeksiService::T_POS.',Id_Pos_Inspeksi',
            'nama' => [
                'required', 'string', 'max:100',
                Rule::unique(JadwalInspeksiService::T_POS, 'Nama_Pos')->ignore($request->input('id'), 'Id_Pos_Inspeksi'),
            ],
            'lokasi' => 'nullable|string|max:250',
            'urutan' => 'nullable|integer|between:0,999',
        ], [], ['nama' => 'nama pos', 'lokasi' => 'keterangan lokasi']);

        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');
        $isi = [
            'Nama_Pos' => trim($data['nama']),
            'Keterangan_Lokasi' => filled($data['lokasi'] ?? null) ? trim($data['lokasi']) : null,
            'Urutan' => (int) ($data['urutan'] ?? 0),
        ];

        if (! empty($data['id'])) {
            DB::table(JadwalInspeksiService::T_POS)
                ->where('Id_Pos_Inspeksi', (int) $data['id'])
                ->update($isi + ['Updated_At' => now(), 'Updated_By' => $oleh]);

            // Booking menyimpan salinan nama saat dipesan; yang masih akan
            // datang ikut nama baru supaya pemohon tidak mencari pos lama.
            DB::table('N_VISITOR_Inspeksi_Booking')
                ->where('Id_Pos_Inspeksi', (int) $data['id'])
                ->where('Status', JadwalInspeksiService::TERJADWAL)
                ->update(['Snapshot_Nama_Pos' => $isi['Nama_Pos']]);

            return ResponseHelper::success(null, 'Pos inspeksi diperbarui.');
        }

        DB::table(JadwalInspeksiService::T_POS)->insert($isi + ['Flag_Aktif' => 'Y', 'Created_By' => $oleh]);

        return ResponseHelper::success(null, 'Pos inspeksi ditambahkan.');
    }

    /**
     * Aktifkan / nonaktifkan pos. Pos nonaktif tidak membuka slot baru; booking
     * yang sudah terjadwal di sana TIDAK dibatalkan otomatis — jumlahnya
     * dilaporkan supaya SHE bisa menghubungi pemohonnya.
     */
    /**
     * Hapus pos inspeksi - hanya bila BELUM PERNAH dipakai.
     *
     * Pos yang sudah menjadi tempat pemeriksaan tidak boleh lenyap: tiga tabel
     * menunjuknya lewat foreign key, dan salah satunya riwayat booking yang
     * mencatat siapa diperiksa di mana. Menghapusnya berarti menghapus jawaban
     * atas pertanyaan yang baru akan ditanyakan berbulan-bulan kemudian.
     *
     * Jadi yang dilakukan di sini bukan memilih antara hapus dan tolak,
     * melainkan: bersih -> hapus, pernah dipakai -> nonaktifkan sambil
     * MENGATAKAN sebabnya. User tetap mendapat hasil yang dimintanya (pos itu
     * hilang dari pilihan), tanpa kehilangan riwayat yang tidak bisa dibuat ulang.
     */
    public function hapusPos(Request $request, string $id)
    {
        $idPos = (int) $id;

        $pos = DB::table(JadwalInspeksiService::T_POS)->where('Id_Pos_Inspeksi', $idPos)->first();

        if (! $pos) {
            return ResponseHelper::error('Pos inspeksi tidak ditemukan.', 404);
        }

        $booking = DB::table('N_VISITOR_Inspeksi_Booking')->where('Id_Pos_Inspeksi', $idPos)->count();
        $pola = DB::table('N_VISITOR_Inspeksi_Pola')->where('Id_Pos_Inspeksi', $idPos)->count();
        $pengecualian = DB::table('N_VISITOR_Inspeksi_Pengecualian')->where('Id_Pos_Inspeksi', $idPos)->count();

        if ($booking === 0 && $pola === 0 && $pengecualian === 0) {
            DB::table(JadwalInspeksiService::T_POS)->where('Id_Pos_Inspeksi', $idPos)->delete();

            return ResponseHelper::success(['dihapus' => true], "Pos \"{$pos->Nama_Pos}\" dihapus.");
        }

        $this->ubahStatusPos($request->merge(['aktif' => 0]), $id);

        $sebab = $booking
            ? "{$booking} booking inspeksi pernah memakainya"
            : ($pola ? "{$pola} baris pola jadwal menunjuknya" : "{$pengecualian} pengecualian tanggal menunjuknya");

        return ResponseHelper::success(
            ['dihapus' => false, 'booking' => $booking, 'pola' => $pola, 'pengecualian' => $pengecualian],
            "Pos \"{$pos->Nama_Pos}\" tidak bisa dihapus karena {$sebab}; riwayatnya perlu tetap terbaca. "
                .'Pos dinonaktifkan sehingga tidak muncul lagi sebagai pilihan.',
        );
    }

    /** Sama seperti hapusPos(); lihat alasannya di sana. */
    public function hapusShift(Request $request, string $id)
    {
        $idShift = (int) $id;

        $shift = DB::table(JadwalInspeksiService::T_SHIFT)->where('Id_Shift_Inspeksi', $idShift)->first();

        if (! $shift) {
            return ResponseHelper::error('Shift tidak ditemukan.', 404);
        }

        $booking = DB::table('N_VISITOR_Inspeksi_Booking')->where('Id_Shift_Inspeksi', $idShift)->count();
        $pola = DB::table('N_VISITOR_Inspeksi_Pola')->where('Id_Shift_Inspeksi', $idShift)->count();
        $hariShift = DB::table('N_VISITOR_Inspeksi_Hari_Shift')->where('Id_Shift_Inspeksi', $idShift)->count();

        if ($booking === 0 && $pola === 0 && $hariShift === 0) {
            DB::table(JadwalInspeksiService::T_SHIFT)->where('Id_Shift_Inspeksi', $idShift)->delete();

            return ResponseHelper::success(['dihapus' => true], "Shift \"{$shift->Nama_Shift}\" dihapus.");
        }

        $this->ubahStatusShift($request->merge(['aktif' => 0]), $id);

        $sebab = $booking
            ? "{$booking} booking inspeksi pernah memakainya"
            : ($pola ? "{$pola} baris pola jadwal menunjuknya" : "{$hariShift} pengaturan hari menunjuknya");

        return ResponseHelper::success(
            ['dihapus' => false, 'booking' => $booking, 'pola' => $pola],
            "Shift \"{$shift->Nama_Shift}\" tidak bisa dihapus karena {$sebab}; riwayatnya perlu tetap terbaca. "
                .'Shift dinonaktifkan sehingga tidak membuka slot baru.',
        );
    }

    public function ubahStatusPos(Request $request, string $id)
    {
        $data = $request->validate(['aktif' => 'required|boolean']);
        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');

        $diubah = DB::table(JadwalInspeksiService::T_POS)
            ->where('Id_Pos_Inspeksi', (int) $id)
            ->update(['Flag_Aktif' => $data['aktif'] ? 'Y' : 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);

        if (! $diubah) {
            return ResponseHelper::error('Pos inspeksi tidak ditemukan.', 404);
        }

        if ($data['aktif']) {
            return ResponseHelper::success(['booking_terdampak' => 0], 'Pos inspeksi diaktifkan.');
        }

        // Pola yang menunjuk pos ini ikut dipensiunkan.
        //
        // slotRentang() hanya membangun slot dari pos AKTIF, jadi pola yang
        // tertinggal tidak menghasilkan apa-apa - tetapi ia tetap muncul di
        // layar pengaturan dan ikut divalidasi saat Simpan, dan aturan
        // "pos harus aktif" menolaknya. Akibatnya SELURUH pola gagal disimpan
        // dengan pesan "pos inspeksi tidak sah" yang menunjuk baris yang bahkan
        // tidak sedang disunting - buntu yang tidak bisa dibereskan dari layar.
        $polaDipensiunkan = DB::table('N_VISITOR_Inspeksi_Pola')
            ->where('Id_Pos_Inspeksi', (int) $id)
            ->where('Flag_Aktif', 'Y')
            ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);

        $terdampak = DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('Id_Pos_Inspeksi', (int) $id)
            ->where('Status', JadwalInspeksiService::TERJADWAL)
            ->where('Waktu_Mulai', '>=', now())
            ->count();

        $pesan = 'Pos dinonaktifkan.';
        if ($polaDipensiunkan) {
            $pesan .= " {$polaDipensiunkan} baris pola jadwal di pos ini ikut dinonaktifkan.";
        }
        if ($terdampak) {
            $pesan .= " {$terdampak} booking mendatang di pos ini tetap terjadwal — hubungi pemohonnya.";
        }

        return ResponseHelper::success(
            ['booking_terdampak' => $terdampak, 'pola_dipensiunkan' => $polaDipensiunkan],
            $pesan,
        );
    }

    /**
     * Simpan seluruh pola mingguan sekaligus. Baris yang tidak dikirim ulang
     * dinonaktifkan, bukan dihapus: booking lama tetap bisa ditelusuri asalnya.
     */
    public function simpanPola(Request $request)
    {
        $data = $request->validate([
            'pola' => 'present|array|max:100',
            'pola.*.id' => 'nullable|integer',
            'pola.*.id_pos' => ['required', 'integer', $this->posAktifRule()],
            'pola.*.id_shift' => ['required', 'integer', $this->shiftAktifRule()],
            'pola.*.hari' => 'required|integer|between:1,7',
            'pola.*.jam_mulai' => 'required|date_format:H:i',
            'pola.*.jam_selesai' => 'required|date_format:H:i|after:pola.*.jam_mulai',
            'pola.*.durasi' => 'required|integer|between:5,240',
            'pola.*.kuota' => 'required|integer|between:1,50',
        ], [], ['pola.*.id_pos' => 'pos inspeksi', 'pola.*.id_shift' => 'shift']);

        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');

        DB::transaction(function () use ($data, $oleh) {
            $dipertahankan = collect($data['pola'])->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();

            DB::table('N_VISITOR_Inspeksi_Pola')
                ->where('Flag_Aktif', 'Y')
                ->when($dipertahankan !== [], fn ($q) => $q->whereNotIn('Id_Inspeksi_Pola', $dipertahankan))
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);

            foreach ($data['pola'] as $p) {
                $isi = [
                    'Id_Pos_Inspeksi' => (int) $p['id_pos'],
                    'Id_Shift_Inspeksi' => (int) $p['id_shift'],
                    'Hari_Minggu' => (int) $p['hari'],
                    'Jam_Mulai' => $p['jam_mulai'],
                    'Jam_Selesai' => $p['jam_selesai'],
                    'Durasi_Menit' => (int) $p['durasi'],
                    'Kuota_Per_Slot' => (int) $p['kuota'],
                ];

                if (! empty($p['id'])) {
                    DB::table('N_VISITOR_Inspeksi_Pola')
                        ->where('Id_Inspeksi_Pola', (int) $p['id'])
                        ->update($isi + ['Flag_Aktif' => 'Y', 'Updated_At' => now(), 'Updated_By' => $oleh]);
                } else {
                    DB::table('N_VISITOR_Inspeksi_Pola')->insert($isi + ['Flag_Aktif' => 'Y', 'Created_By' => $oleh]);
                }
            }
        });

        return ResponseHelper::success(null, 'Pola slot inspeksi tersimpan. Booking yang sudah ada tidak berubah.');
    }

    public function simpanPengecualian(Request $request)
    {
        $data = $request->validate([
            'tanggal' => 'required|date_format:Y-m-d|after_or_equal:today',
            // Kosong = semua pos (hanya untuk TUTUP); slot TAMBAH wajib menyebut posnya.
            'id_pos' => ['required_if:jenis,'.JadwalInspeksiService::TAMBAH, 'nullable', 'integer', $this->posAktifRule()],
            'id_shift' => ['nullable', 'integer', $this->shiftAktifRule()],
            'jenis' => 'required|in:'.JadwalInspeksiService::TUTUP.','.JadwalInspeksiService::TAMBAH,
            'jam_mulai' => 'required_if:jenis,'.JadwalInspeksiService::TAMBAH.'|nullable|date_format:H:i',
            'jam_selesai' => 'required_if:jenis,'.JadwalInspeksiService::TAMBAH.'|nullable|date_format:H:i|after:jam_mulai',
            'durasi' => 'required_if:jenis,'.JadwalInspeksiService::TAMBAH.'|nullable|integer|between:5,240',
            'kuota' => 'required_if:jenis,'.JadwalInspeksiService::TAMBAH.'|nullable|integer|between:1,50',
            'keterangan' => 'nullable|string|max:250',
        ], [], ['id_pos' => 'pos inspeksi']);

        $tambah = $data['jenis'] === JadwalInspeksiService::TAMBAH;

        DB::table('N_VISITOR_Inspeksi_Pengecualian')->insert([
            'Tanggal' => $data['tanggal'],
            'Id_Pos_Inspeksi' => filled($data['id_pos'] ?? null) ? (int) $data['id_pos'] : null,
            'Id_Shift_Inspeksi' => filled($data['id_shift'] ?? null) ? (int) $data['id_shift'] : null,
            'Jenis' => $data['jenis'],
            'Jam_Mulai' => $tambah ? $data['jam_mulai'] : null,
            'Jam_Selesai' => $tambah ? $data['jam_selesai'] : null,
            'Durasi_Menit' => $tambah ? (int) $data['durasi'] : null,
            'Kuota_Per_Slot' => $tambah ? (int) $data['kuota'] : null,
            'Keterangan' => $data['keterangan'] ?? null,
            'Flag_Aktif' => 'Y',
            'Created_By' => (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE'),
        ]);

        return ResponseHelper::success(null, $tambah
            ? 'Slot tambahan dibuka.'
            : 'Tanggal ditutup. Booking yang sudah ada pada tanggal itu tidak otomatis dibatalkan.');
    }

    public function hapusPengecualian(Request $request, string $id)
    {
        $diubah = DB::table('N_VISITOR_Inspeksi_Pengecualian')
            ->where('Id_Inspeksi_Pengecualian', (int) $id)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Flag_Aktif' => 'T',
                'Updated_At' => now(),
                'Updated_By' => (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE'),
            ]);

        return $diubah
            ? ResponseHelper::success(null, 'Pengecualian dihapus.')
            : ResponseHelper::error('Pengecualian tidak ditemukan.', 404);
    }

    /**
     * Shift kerja karyawan: jamnya boleh diubah kapan saja, termasuk yang
     * menyeberang tengah malam (Jam_Selesai <= Jam_Mulai, mis. 23:00-07:00).
     * Jam jadwal inspeksi TIDAK dibatasi jam shift — SHE memang membukanya di
     * sela-sela jam kerja.
     */
    public function simpanShift(Request $request)
    {
        $data = $request->validate([
            'id' => 'nullable|integer|exists:'.JadwalInspeksiService::T_SHIFT.',Id_Shift_Inspeksi',
            'nama' => [
                'required', 'string', 'max:50',
                Rule::unique(JadwalInspeksiService::T_SHIFT, 'Nama_Shift')->ignore($request->input('id'), 'Id_Shift_Inspeksi'),
            ],
            'jam_mulai' => 'required|date_format:H:i',
            'jam_selesai' => 'required|date_format:H:i',
            'urutan' => 'nullable|integer|between:0,999',
        ], [], ['nama' => 'nama shift']);

        if ($data['jam_mulai'] === $data['jam_selesai']) {
            return ResponseHelper::errorWithCode('Jam mulai dan jam selesai shift tidak boleh sama.', 'VISITOR_INSPECTION_SHIFT_INVALID', 422);
        }

        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');
        $isi = [
            'Nama_Shift' => trim($data['nama']),
            'Jam_Mulai' => $data['jam_mulai'],
            'Jam_Selesai' => $data['jam_selesai'],
            'Urutan' => (int) ($data['urutan'] ?? 0),
        ];

        if (! empty($data['id'])) {
            DB::table(JadwalInspeksiService::T_SHIFT)
                ->where('Id_Shift_Inspeksi', (int) $data['id'])
                ->update($isi + ['Updated_At' => now(), 'Updated_By' => $oleh]);

            return ResponseHelper::success(null, 'Shift diperbarui. Jam jadwal inspeksi yang sudah ada tidak berubah.');
        }

        DB::table(JadwalInspeksiService::T_SHIFT)->insert($isi + ['Flag_Aktif' => 'Y', 'Created_By' => $oleh]);

        return ResponseHelper::success(null, 'Shift ditambahkan.');
    }

    /**
     * Aktifkan / nonaktifkan shift. Shift nonaktif menghentikan SELURUH jadwal
     * miliknya; booking yang sudah terjadwal tidak dibatalkan otomatis.
     */
    public function ubahStatusShift(Request $request, string $id)
    {
        $data = $request->validate(['aktif' => 'required|boolean']);
        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');

        $diubah = DB::table(JadwalInspeksiService::T_SHIFT)
            ->where('Id_Shift_Inspeksi', (int) $id)
            ->update(['Flag_Aktif' => $data['aktif'] ? 'Y' : 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);

        if (! $diubah) {
            return ResponseHelper::error('Shift tidak ditemukan.', 404);
        }

        if ($data['aktif']) {
            return ResponseHelper::success(['booking_terdampak' => 0], 'Shift diaktifkan.');
        }

        // Lihat catatan di ubahStatusPos(): pola yang tertinggal menunjuk shift
        // nonaktif tidak menghasilkan slot apa pun, tetapi membuat SELURUH
        // pengaturan pola gagal disimpan.
        $polaDipensiunkan = DB::table('N_VISITOR_Inspeksi_Pola')
            ->where('Id_Shift_Inspeksi', (int) $id)
            ->where('Flag_Aktif', 'Y')
            ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);

        $terdampak = DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('Id_Shift_Inspeksi', (int) $id)
            ->where('Status', JadwalInspeksiService::TERJADWAL)
            ->where('Waktu_Mulai', '>=', now())
            ->count();

        $pesan = 'Shift dinonaktifkan. Jadwal miliknya berhenti membuka slot baru.';
        if ($polaDipensiunkan) {
            $pesan .= " {$polaDipensiunkan} baris pola jadwal pada shift ini ikut dinonaktifkan.";
        }
        if ($terdampak) {
            $pesan .= " {$terdampak} booking mendatang dari shift ini tetap terjadwal — hubungi pemohonnya.";
        }

        return ResponseHelper::success(
            ['booking_terdampak' => $terdampak, 'pola_dipensiunkan' => $polaDipensiunkan],
            $pesan,
        );
    }

    /**
     * Sakelar hari / (hari, shift). Tanpa `id_shift` berarti SELURUH hari itu.
     * Jadwalnya tidak dihapus — dimatikan saja, supaya bisa dihidupkan lagi
     * tanpa menyusun ulang. Booking yang sudah ada tidak dibatalkan otomatis;
     * jumlahnya dilaporkan seperti pada pos & shift.
     */
    public function ubahStatusHari(Request $request)
    {
        $data = $request->validate([
            'hari' => 'required|integer|between:1,7',
            'id_shift' => ['nullable', 'integer', $this->shiftAktifRule()],
            'aktif' => 'required|boolean',
        ], [], ['id_shift' => 'shift']);

        $oleh = (string) ($this->approvalPolicy->requesterKode($request->user()) ?? 'SHE');
        $idShift = filled($data['id_shift'] ?? null) ? (int) $data['id_shift'] : null;

        $baris = DB::table(JadwalInspeksiService::T_HARI_SHIFT)
            ->where('Hari_Minggu', (int) $data['hari'])
            ->when($idShift === null, fn ($q) => $q->whereNull('Id_Shift_Inspeksi'))
            ->when($idShift !== null, fn ($q) => $q->where('Id_Shift_Inspeksi', $idShift))
            ->first();

        $flag = $data['aktif'] ? 'Y' : 'T';

        if ($baris) {
            DB::table(JadwalInspeksiService::T_HARI_SHIFT)
                ->where('Id_Inspeksi_Hari_Shift', $baris->Id_Inspeksi_Hari_Shift)
                ->update(['Flag_Aktif' => $flag, 'Updated_At' => now(), 'Updated_By' => $oleh]);
        } else {
            DB::table(JadwalInspeksiService::T_HARI_SHIFT)->insert([
                'Hari_Minggu' => (int) $data['hari'],
                'Id_Shift_Inspeksi' => $idShift,
                'Flag_Aktif' => $flag,
                'Created_By' => $oleh,
            ]);
        }

        if ($data['aktif']) {
            return ResponseHelper::success(['booking_terdampak' => 0], $idShift === null
                ? 'Hari diaktifkan.'
                : 'Shift pada hari itu diaktifkan.');
        }

        $terdampak = DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('Status', JadwalInspeksiService::TERJADWAL)
            ->where('Waktu_Mulai', '>=', now())
            // Hari ISO (1=Senin) tanpa bergantung pada SET DATEFIRST sesi.
            ->whereRaw('((DATEPART(dw, Waktu_Mulai) + @@DATEFIRST - 2) % 7) + 1 = ?', [(int) $data['hari']])
            ->when($idShift !== null, fn ($q) => $q->where('Id_Shift_Inspeksi', $idShift))
            ->count();

        $apa = $idShift === null ? 'Hari dinonaktifkan' : 'Shift pada hari itu dinonaktifkan';

        return ResponseHelper::success(['booking_terdampak' => $terdampak], $terdampak
            ? "{$apa}. {$terdampak} booking mendatang tetap terjadwal — hubungi pemohonnya."
            : "{$apa}. Jadwalnya tersimpan dan bisa dihidupkan lagi kapan saja.");
    }

    /** Jadwal hanya boleh menunjuk shift yang aktif. */
    private function shiftAktifRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists(JadwalInspeksiService::T_SHIFT, 'Id_Shift_Inspeksi')->where('Flag_Aktif', 'Y');
    }

    /** Pola & slot tambahan hanya boleh menunjuk pos yang aktif. */
    private function posAktifRule(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists(JadwalInspeksiService::T_POS, 'Id_Pos_Inspeksi')->where('Flag_Aktif', 'Y');
    }
}
