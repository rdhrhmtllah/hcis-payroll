<?php

namespace App\Http\Controllers\VISITOR\Workflow\Verification;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\VISITOR\Permit\VehiclePermit\SetPermitValidityRequest;
use App\Http\Requests\VISITOR\Workflow\Approval\VisitorApproveRequest;
use App\Http\Requests\VISITOR\Workflow\Approval\VisitorRejectRequest;
use App\Http\Requests\VISITOR\Workflow\Verification\PermitKoreksiRequest;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\VISITOR\Guest\Visit\FotoKendaraanTamuService;
use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\Inspection\KontakPemohonInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitMasterService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\SaringanMonitoringPermit;
use App\Services\VISITOR\Workflow\Verification\PermitKoreksiException;
use App\Services\VISITOR\Workflow\Verification\PermitKoreksiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class VerifikatorVisitorController extends Controller
{
    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected ApprovalRequestService $approvalRequestService,
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorEfektifService $visitorEfektif,
        protected VisitorApprovalDecisionService $visitorDecision,
        protected VisitorPermitChecklistService $permitChecklist,
        protected PermitCoklatTamuService $permitCoklatTamu,
        protected VisitorKehadiranQueryService $kehadiran,
        protected FotoKendaraanTamuService $fotoKendaraan,
        protected PermitKoreksiService $permitKoreksi,
        protected JadwalInspeksiService $jadwalInspeksi,
    ) {}

    /**
     * Aksi tamu hanya boleh menyentuh baris bertipe rute. Rute B2B dan Regular
     * berbagi method ini; tanpa penjaga, pemegang kunci Regular bisa
     * menyetujui kunjungan B2B dengan memanggil endpointnya langsung.
     */
    private function pastikanTipeRute(Request $request, string $noTransaksi): void
    {
        $query = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail as d')->where('d.No_Transaksi', $noTransaksi);

        if (! TipeTamu::saring($query, TipeTamu::dariPermintaan($request))->exists()) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Data tamu tidak ditemukan.');
        }
    }

    private function domainNotifTamu(Request $request): string
    {
        return TipeTamu::dariPermintaan($request) === TipeTamu::B2B
            ? VisitorNotificationService::DOMAIN_TAMU_B2B
            : VisitorNotificationService::DOMAIN_TAMU;
    }

    /**
     * URL lama meja gabungan.
     *
     * Meja verifikasi dipecah menjadi dua halaman (DML-2026-105). Notifikasi
     * yang sudah terkirim menautkan `/verifikator-visitor?detail=<token>&jenis=`,
     * jadi pengalihannya membawa serta seluruh query string dan memilih halaman
     * dari `jenis` — tanpa itu, setiap tautan notifikasi lama mendarat di
     * domain yang salah dan detailnya tidak pernah terbuka.
     */
    public function index(Request $request)
    {
        $jenis = strtolower(trim((string) $request->query('jenis')));
        $tujuan = $jenis === 'tamu' ? '/verifikator-visitor/tamu' : '/verifikator-visitor/permit';
        $query = $request->getQueryString();

        return redirect($tujuan.($query ? '?'.$query : ''));
    }

    public function indexPermit(Request $request)
    {
        return Inertia::render('VISITOR/Workflow/Verification/Verifikator/verifikatorPermit', [
            'user' => $request->user(),
        ]);
    }

    public function indexTamu(Request $request)
    {
        $tipe = TipeTamu::dariPermintaan($request);

        return Inertia::render('VISITOR/Workflow/Verification/Verifikator/verifikatorTamu', [
            'user' => $request->user(),
            'tipeTamu' => $tipe,
            // Rute API halaman ini. B2B memakai salinan rute ber-kunci sendiri,
            // jadi pemegang kunci Regular tidak bisa memanggilnya.
            'apiPrefix' => $tipe === TipeTamu::B2B ? 'verifikator-b2b' : 'verifikator-tamu',
            'statsUrl' => $tipe === TipeTamu::B2B ? '/verifikator-b2b-stats' : '/verifikator-visitor-stats',
            // Detail permit dari meja ini memanggil /verifikator-permit, yang
            // dijaga kunci meja permit; tanpanya tombolnya berujung 403.
            'bolehBukaPermit' => $request->user() !== null
                && app(KeputusanAkses::class)->boleh($request->user(), 'visitorVerifikatorPermitPage'),
        ]);
    }

    // ─── STATS ───────────────────────────────────────────────────────────────

    public function getStats(Request $request)
    {
        try {
            // Angka tamu mengikuti tipe rute: halaman Regular (dan halaman
            // Permit, yang memakai rute yang sama) melihat Regular; halaman B2B
            // memanggil salinan rute bertipe B2B yang dijaga kuncinya sendiri.
            $tipeTamu = TipeTamu::dariPermintaan($request);
            /*
             * DUA perjalanan untuk enam angka — satu per tabel.
             *
             * Bentuk sebelumnya menembakkan enam COUNT: tiga memindai tabel
             * permit, tiga memindai tabel tamu, semuanya dengan penyaring yang
             * berbeda tipis. Pada basis data jarak jauh biayanya bukan
             * pemindaian melainkan PERJALANAN — latensi terukur ±65 ms per
             * kueri, jadi empat kueri tambahan adalah ~260 ms yang dihabiskan
             * menunggu pada layar yang dimuat setiap kali meja verifikasi
             * dibuka.
             *
             * Penandanya dihitung DI TURUNAN, agregatnya di luar: SQL Server
             * menolak agregat di atas ekspresi yang memuat sub-kueri, dan
             * penyaring antrean membaca HRIS_Approval_Request lewat EXISTS.
             * Membungkusnya langsung dengan SUM(CASE ...) gagal di basis data,
             * bukan di PHP — jadi ia hanya ketahuan saat endpointnya dipanggil.
             *
             * Ekspresi EXISTS-nya DISALIN APA ADANYA dari bentuk lama, termasuk
             * pengecualian approval yang sudah kedaluwarsa. Itu bukan kerapian:
             * angka antrean yang berbeda tipis dari daftar antreannya membuat
             * verifikator mengejar pekerjaan yang tidak ada.
             */
            $adaAntrean = 'EXISTS (SELECT 1 FROM HRIS_Approval_Request ar
                                    WHERE ar.No_Transaksi = p.No_Transaksi
                                      AND ar.Flag_Approval IS NULL)';

            $antreanKedaluwarsa = 'EXISTS (SELECT 1 FROM HRIS_Approval_Request ar2
                                            WHERE ar2.No_Transaksi = p.No_Transaksi
                                              AND ar2.Flag_Approval IS NULL
                                              AND ar2.Expired_At IS NOT NULL
                                              AND ar2.Expired_At < GETDATE())';

            $permit = DB::query()
                ->fromSub(
                    DB::table('N_EMI_VISITOR_Transaksi_Permit as p')
                        ->where('p.Flag_Aktif', 'Y')
                        ->selectRaw(
                            "CASE WHEN p.Flag_Approval IS NULL AND {$adaAntrean} AND NOT {$antreanKedaluwarsa}
                                  THEN 1 ELSE 0 END AS antrean,
                             CASE WHEN p.Flag_Approval = 'Y' AND CAST(p.Approved_At AS date) = CAST(GETDATE() AS date)
                                  THEN 1 ELSE 0 END AS disetujui_hari_ini,
                             CASE WHEN p.Flag_Approval = 'T' THEN 1 ELSE 0 END AS ditolak"
                        ),
                    'x'
                )
                ->selectRaw(
                    'SUM(x.antrean) AS antrean,
                     SUM(x.disetujui_hari_ini) AS disetujui_hari_ini,
                     SUM(x.ditolak) AS ditolak'
                )
                ->first();

            /*
             * N_EMI_VISITOR_Transaksi_Tamu TIDAK punya kolom Approved_At —
             * dikonfirmasi lewat INFORMATION_SCHEMA. Persetujuan hari ini karena
             * itu dibaca dari Tanggal_Approval milik langkah approval-nya, bukan
             * dari kolom yang tidak ada.
             */
            $tamu = DB::query()
                ->fromSub(
                    DB::table('N_EMI_VISITOR_Transaksi_Tamu as t')
                        ->where('t.Flag_Aktif', 'Y')
                        ->whereExists(function ($q) use ($tipeTamu) {
                            $q->select(DB::raw(1))
                                ->from('N_EMI_VISITOR_Transaksi_Tamu_Detail as d')
                                ->whereColumn('d.No_Transaksi', 't.No_Transaksi');
                            TipeTamu::saring($q, $tipeTamu);
                        })
                        ->selectRaw(
                            "CASE WHEN t.Status_Kunjungan = '".StatusKunjunganTamu::PENDING_APPROVAL."' THEN 1 ELSE 0 END AS antrean,
                             CASE WHEN t.Flag_Approval = 'Y' AND EXISTS (
                                      SELECT 1 FROM HRIS_Approval_Request ar
                                       WHERE ar.No_Transaksi = t.No_Transaksi
                                         AND CAST(ar.Tanggal_Approval AS date) = CAST(GETDATE() AS date)
                                  ) THEN 1 ELSE 0 END AS disetujui_hari_ini,
                             CASE WHEN t.Flag_Approval = 'T' THEN 1 ELSE 0 END AS ditolak"
                        ),
                    'y'
                )
                ->selectRaw(
                    'SUM(y.antrean) AS antrean,
                     SUM(y.disetujui_hari_ini) AS disetujui_hari_ini,
                     SUM(y.ditolak) AS ditolak'
                )
                ->first();

            $antrianPermit = (int) ($permit->antrean ?? 0);
            $antrianTamu = (int) ($tamu->antrean ?? 0);
            $disetujuiHariIniPermit = (int) ($permit->disetujui_hari_ini ?? 0);
            $disetujuiHariIniTamu = (int) ($tamu->disetujui_hari_ini ?? 0);
            $disetujuiHariIni = $disetujuiHariIniPermit + $disetujuiHariIniTamu;
            $ditolakPermit = (int) ($permit->ditolak ?? 0);
            $ditolakTamu = (int) ($tamu->ditolak ?? 0);

            // Rincian per domain menyusul pemecahan meja verifikasi menjadi dua
            // halaman: halaman Permit yang menampilkan angka gabungan akan
            // memajang pekerjaan domain lain sebagai miliknya sendiri, dan tidak
            // ada yang akan menyadarinya karena angkanya tetap masuk akal.
            //
            // Kunci gabungannya DIPERTAHANKAN: ia bagian kontrak yang sudah
            // dibaca pemanggil lain, dan mencabutnya berarti merusak mereka demi
            // kerapian.
            return ResponseHelper::success([
                'antrian_permit' => $antrianPermit,
                'antrian_tamu' => $antrianTamu,
                'disetujui_hari_ini' => $disetujuiHariIni,
                'ditolak_total' => $ditolakPermit + $ditolakTamu,
                'disetujui_hari_ini_permit' => $disetujuiHariIniPermit,
                'disetujui_hari_ini_tamu' => $disetujuiHariIniTamu,
                'ditolak_permit' => $ditolakPermit,
                'ditolak_tamu' => $ditolakTamu,
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_stats', 'VISITOR_VERIFICATION_STATS_FAILED', 'Statistik Visitor gagal dimuat.');
        }
    }

    // ─── PERMIT ──────────────────────────────────────────────────────────────

    public function getPermitList(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 15);
            $mode = $request->input('mode', 'antrian'); // antrian | riwayat
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $tipe = $request->input('tipe', '');

            $query = $this->permitQuery->basePermitQuery();

            if ($mode === 'antrian') {
                $query->whereRaw($this->permitQuery->permitStatusSql().' = ?', ['PENDING']);
            } else {
                // REVOKED masuk riwayat: verifikator perlu melihat kembali apa
                // yang pernah ia cabut, lengkap dengan sebabnya.
                $query->whereRaw($this->permitQuery->permitStatusSql()." IN ('APPROVED','REJECTED','EXPIRED','NO_FLOW','VALIDITY_REQUIRED','REVOKED')");
                if ($status) {
                    $query->whereRaw($this->permitQuery->permitStatusSql().' = ?', [$status]);
                }
            }

            if ($tipe) {
                $query->where('vt.Nama_Tipe_Kendaraan', 'LIKE', "%{$tipe}%");
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', "%{$search}%")
                        ->orWhere('h.No_Transaksi', 'LIKE', "%{$search}%")
                        ->orWhere(DB::raw("CONCAT(ISNULL(d.Plat_Nomor_Prefix,''),' ',ISNULL(d.Plat_Nomor_Angka,''),' ',ISNULL(d.Plat_Nomor_Suffix,''))"), 'LIKE', "%{$search}%")
                        ->orWhere('vm.Nama_Model_Kendaraan', 'LIKE', "%{$search}%");
                });
            }

            $total = $query->count();
            if ($mode === 'riwayat') {
                $orderSql = 'COALESCE((SELECT MAX(ar.Tanggal_Approval) FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi = h.No_Transaksi AND ar.Flag_Approval IS NOT NULL), h.Updated_At, h.Created_At)';
                $data = $query->orderByRaw("{$orderSql} DESC")->skip(($page - 1) * $limit)->take($limit)->get();
            } else {
                $data = $query->orderByDesc('h.Created_At')->skip(($page - 1) * $limit)->take($limit)->get();
            }

            return ResponseHelper::successWithPaginationV2($data, $page, $limit, $total);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_list', 'VISITOR_VERIFICATION_PERMIT_LIST_FAILED', 'Daftar permit gagal dimuat.');
        }
    }

    public function showPermit(string $id)
    {
        try {
            // Tautan notifikasi membawa token, bukan Id. Nilai yang tak terbaca
            // harus berhenti di sini: mengalirkannya ke kolom int melempar
            // QueryException dan berbalas 500, bukan 404.
            $idTransaksi = VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_PERMIT, $id);

            $query = $this->permitQuery->basePermitQuery();
            if ($idTransaksi !== null) {
                $query->where('h.Id_Transaksi_Permit', $idTransaksi);
            } else {
                $query->where(function ($q) use ($id) {
                    $q->where('h.No_Transaksi', $id)
                        ->orWhere('h.No_Transaksi_Tamu', $id);
                });
            }

            $header = $query->first();

            if (! $header) {
                return ResponseHelper::error('Data permit tidak ditemukan.', 404);
            }

            $jadwal = $this->jadwalInspeksi->ringkasan($header->No_Transaksi);

            $approverKode = $this->visitorApprovalPolicy->requesterKode(request()->user()) ?? '';
            $permitTamu = ($header->Sumber_Pengajuan ?? '') === 'API'
                || ($header->No_Transaksi_Tamu ?? null) !== null;
            $isOwnPermit = (! $permitTamu && $approverKode !== '')
                ? (strcasecmp(trim((string) ($header->Kode_Karyawan_Pemohon ?? '')), trim($approverKode)) === 0)
                : false;

            return ResponseHelper::success([
                'permit' => $header,
                'dokumens' => $this->permitQuery->getDokumens($header->No_Transaksi),
                'checklists' => $this->permitQuery->getPermitChecklists($header->No_Transaksi),
                'approvals' => $this->permitQuery->getApprovals($header->No_Transaksi),
                'is_final_approval' => $this->permitQuery->isFinalPendingApproval($header->No_Transaksi),
                'is_own_permit' => $isOwnPermit,
                'boleh_koreksi' => $this->permitKoreksi->bolehKoreksi(
                    $header->No_Transaksi,
                    $this->visitorApprovalPolicy->requesterKode(request()->user()) ?? '',
                ),
                'riwayat_koreksi' => $this->permitKoreksi->riwayat($header->No_Transaksi),
                // Slot yang dipilih pemohon saat mengajukan; null untuk permit tamu/mitra.
                'jadwal_inspeksi' => $jadwal,
                // Siapa yang bisa dihubungi SHE bila pemohon/tamu terlambat atau tidak datang.
                'kontak_pemohon' => app(KontakPemohonInspeksiService::class)->untukPermit($header, $jadwal),
                // Permit tamu: estimasi masuk/keluar dari pengajuan kunjungannya,
                // supaya SHE tahu kapan berjaga menyambut tamunya.
                'kunjungan_tamu' => $this->kehadiran->ringkasanKunjungan($header->No_Transaksi_Tamu ?? null),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_detail', 'VISITOR_VERIFICATION_PERMIT_DETAIL_FAILED', 'Detail permit gagal dimuat.');
        }
    }

    public function approvePermit(VisitorApproveRequest $request, string $noTransaksi)
    {
        try {
            $approverKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';
            $checklistRequired = (bool) ($this->visitorApprovalPolicy->config('permit')['checklist_required'] ?? false);
            $isFinal = $this->visitorDecision->approvePermit(
                $noTransaksi,
                $approverKode,
                $request->validated(),
                $checklistRequired,
            );

            // Hanya approval FINAL yang berarti "disetujui dan aktif" bagi pemohon;
            // approval bertahap belum mengubah apa pun yang dia lihat.
            if ($isFinal) {
                $this->kabariPemohon(
                    VisitorNotificationService::DOMAIN_PERMIT,
                    $noTransaksi,
                    VisitorNotificationService::KEPUTUSAN_DISETUJUI,
                );
            }

            return ResponseHelper::success(
                ['final' => $isFinal],
                $isFinal ? 'Permit disetujui dan masa berlaku diaktifkan.' : 'Approval dicatat dan diteruskan.',
            );
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_PERMIT_VALIDATION_FAILED', 422);
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_APPROVAL_CONFLICT', 409);
        } catch (\Throwable $e) {
            Log::error('Gagal menyetujui permit', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Permit gagal disetujui.', 'VISITOR_PERMIT_APPROVE_FAILED', 500);
        }
    }

    public function rejectPermit(VisitorRejectRequest $request, string $noTransaksi)
    {
        try {
            $rejectorKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';
            $alasan = $request->validated('alasan');
            $this->visitorDecision->rejectPermit($noTransaksi, $rejectorKode, $alasan);

            $this->kabariPemohon(
                VisitorNotificationService::DOMAIN_PERMIT,
                $noTransaksi,
                VisitorNotificationService::KEPUTUSAN_DITOLAK,
                ['alasan' => $alasan],
            );

            return ResponseHelper::success(null, 'Permit ditolak.');
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_APPROVAL_CONFLICT', 409);
        } catch (\Throwable $e) {
            Log::error('Gagal menolak permit', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Permit gagal ditolak.', 'VISITOR_PERMIT_REJECT_FAILED', 500);
        }
    }

    public function setPermitValidity(SetPermitValidityRequest $request, string $noTransaksi)
    {
        try {
            $actorKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';
            $this->visitorDecision->setLegacyPermitValidity(
                $noTransaksi,
                $actorKode,
                (int) $request->validated('id_durasi_permit'),
            );

            return ResponseHelper::success(null, 'Masa berlaku permit berhasil ditetapkan.');
        } catch (\InvalidArgumentException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_PERMIT_VALIDATION_FAILED', 422);
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_PERMIT_VALIDITY_CONFLICT', 409);
        } catch (\Throwable $e) {
            Log::error('Gagal menetapkan masa berlaku permit', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Masa berlaku permit gagal ditetapkan.', 'VISITOR_PERMIT_VALIDITY_FAILED', 500);
        }
    }

    public function revokePermit(Request $request, string $noTransaksi)
    {
        $request->validate(['alasan' => 'required|string|max:500']);

        try {
            $olehKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';

            $affected = $this->visitorEfektif->revokePermitEfektif($noTransaksi, $request->input('alasan'), $olehKode);

            if ($affected === 0) {
                return ResponseHelper::error('Tidak ada akses efektif yang aktif untuk transaksi ini — mungkin belum pernah disetujui, atau sudah dibatalkan/kadaluarsa sebelumnya.', 404);
            }

            $this->kabariPemohon(
                VisitorNotificationService::DOMAIN_PERMIT,
                $noTransaksi,
                VisitorNotificationService::KEPUTUSAN_DIBATALKAN,
                ['alasan' => $request->input('alasan')],
            );

            return ResponseHelper::success(null, 'Permit yang sudah disetujui berhasil dibatalkan.');
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_revoke', 'VISITOR_PERMIT_REVOKE_FAILED', 'Akses permit gagal dibatalkan.');
        }
    }

    public function bulkRevokePermit(Request $request)
    {
        $request->validate([
            'alasan' => 'required|string|max:500',
            'items' => 'nullable|array',
            'items.*' => 'string',
            'select_all_matching' => 'nullable|boolean',
            'filters' => 'nullable|array',
        ]);

        $alasan = $request->input('alasan');
        $selectAll = (bool) $request->input('select_all_matching', false);
        $items = (array) $request->input('items', []);

        try {
            $olehKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';

            $targetNoTransaksi = [];

            if ($selectAll) {
                $filters = (array) $request->input('filters', []);
                $query = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
                    ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                    ->leftJoin('Karyawan as k', 'h.Kode_Karyawan_Pemohon', '=', 'k.Kode_Karyawan')
                    ->leftJoin('N_EMI_VISITOR_Master_Kategori_Permit as mk', 'd.Id_Master_Kategori_Permit', '=', 'mk.Id_Kategori_Permit')
                    ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Model as vm', 'd.Id_Master_Model', '=', 'vm.Id_Vehicle_Model')
                    ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Type as vt', 'vm.Id_Master_Tipe', '=', 'vt.Id_Vehicle_Type')
                    ->join('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
                    ->where('h.Flag_Aktif', 'Y')
                    ->where('pe.Status_Efektif', 'AKTIF')
                    // HANYA permit sungguhan (DDL-2026-132).
                    //
                    // Baris di proyeksi gerbang tidak lagi berarti "sudah
                    // disetujui": pengajuan yang baru memesan slot inspeksi pun
                    // punya baris AKTIF di sana. Tanpa penyaring ini, "pilih
                    // semua yang cocok" ikut menyeret pengajuan yang belum
                    // diputus siapa pun dan menandainya DIBATALKAN — pencabutan
                    // atas keputusan yang belum pernah diambil.
                    ->where('pe.Jenis_Akses', VisitorEfektifService::JENIS_PERMIT);

                // Saringan yang SAMA dengan daftar All Monitoring (asal, rentang
                // tanggal, masa berlaku per jam, kategori, kendaraan, cari).
                // Dulu disusun ulang di sini tanpa asal dan tanggal: memilih
                // semua "permit kunjungan tamu" ikut mencabut permit karyawan.
                app(SaringanMonitoringPermit::class)->terapkan(
                    $query,
                    array_intersect_key($filters, array_flip(SaringanMonitoringPermit::KUNCI)),
                );

                $targetNoTransaksi = $query->pluck('h.No_Transaksi')->all();
            } else {
                $targetNoTransaksi = $items;
            }

            if (empty($targetNoTransaksi)) {
                return ResponseHelper::error('Tidak ada permit aktif yang dipilih untuk dihapus aksesnya.', 422);
            }

            $revoked = $this->visitorEfektif->revokePermitEfektifMany(
                $targetNoTransaksi,
                $alasan,
                $olehKode,
            );
            $revokedCount = count($revoked);
            $notificationEvents = array_map(static fn (string $noTransaksi): array => [
                'kind' => 'decision',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $noTransaksi,
                'decision' => VisitorNotificationService::KEPUTUSAN_DIBATALKAN,
                'context' => ['alasan' => $alasan],
            ], $revoked);

            foreach (array_chunk($notificationEvents, 100) as $events) {
                PublishVisitorNotificationsJob::dispatch($events);
            }

            return ResponseHelper::success(
                ['total_revoked' => $revokedCount],
                "Akses {$revokedCount} permit berhasil dihapus."
            );
        } catch (\Throwable $e) {
            Log::error('Gagal menghapus akses permit secara massal', ['error' => $e->getMessage()]);

            return VisitorApiFailure::respond($e, 'verification_permit_bulk_revoke', 'VISITOR_PERMIT_BULK_REVOKE_FAILED', 'Akses permit terpilih gagal dibatalkan.');
        }
    }

    public function updatePermitChecklist(Request $request, string $noTransaksi)
    {
        $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer|distinct',
            'answers.*.jawaban' => 'required|string|max:20',
            'answers.*.catatan' => 'nullable|string|max:500',
        ]);

        try {
            $approverKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';
            $validation = $this->approvalRequestService->validateApproverAccess($noTransaksi, $approverKode);
            if (! $validation['valid']) {
                return ResponseHelper::errorWithCode($validation['reason'], 'VISITOR_APPROVER_ACCESS_DENIED', 403);
            }

            $this->permitChecklist->saveAnswers($noTransaksi, $request->input('answers'));

            return ResponseHelper::success(null, 'Checklist diperbarui.');
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_checklist_update', 'VISITOR_PERMIT_CHECKLIST_UPDATE_FAILED', 'Checklist permit gagal diperbarui.');
        }
    }

    public function koreksiPermit(PermitKoreksiRequest $request, string $noTransaksi)
    {
        try {
            $berubah = $this->permitKoreksi->koreksi(
                $noTransaksi,
                $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '',
                $request->safe()->except(['alasan', 'nilai_awal']),
                (string) $request->validated('alasan'),
                $request->validated('nilai_awal'),
            );

            return ResponseHelper::success(['berubah' => $berubah], 'Data permit dikoreksi.');
        } catch (PermitKoreksiException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->kode, $e->status);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_koreksi', 'VISITOR_PERMIT_KOREKSI_FAILED', 'Koreksi data permit gagal disimpan.');
        }
    }

    public function pratinjauKoreksiChecklist(Request $request, string $noTransaksi)
    {
        $request->validate([
            'id_kategori_permit' => 'required|integer',
            'id_vehicle_model' => 'required|integer',
        ]);

        try {
            return ResponseHelper::success($this->permitKoreksi->pratinjauChecklist(
                $noTransaksi,
                $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '',
                (int) $request->query('id_kategori_permit'),
                (int) $request->query('id_vehicle_model'),
            ));
        } catch (PermitKoreksiException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->kode, $e->status);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_koreksi_pratinjau', 'VISITOR_PERMIT_KOREKSI_PRATINJAU_FAILED', 'Pratinjau checklist gagal dimuat.');
        }
    }

    public function masterKoreksiPermit()
    {
        try {
            return ResponseHelper::success(app(VisitorPermitMasterService::class)->untukPengajuan());
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'verification_permit_koreksi_master', 'VISITOR_PERMIT_KOREKSI_MASTER_FAILED', 'Master data koreksi gagal dimuat.');
        }
    }

    /**
     * Lampiran dokumen permit oleh verifikator - SATU permintaan, banyak berkas.
     *
     * Sebelumnya setiap gambar dikirim sendiri-sendiri begitu dipilih: tiga foto
     * berarti tiga round-trip, tiga unggahan GCS berurutan, dan tiga kotak
     * dialog di tengah pemeriksaan. Sekarang berkasnya ditahan di layar sampai
     * verifikator menekan Setujui, lalu dikirim sekaligus lewat `dokumen[JENIS]`.
     *
     * Bentuk lama (`file` + `jenis_dokumen`) sengaja tetap diterima: ia masih
     * dipakai penggantian satu berkas, dan mencabutnya akan mematikan jalur itu
     * tanpa satu pun galat yang menyebut sebabnya.
     */
    public function uploadPermitDokumen(Request $request)
    {
        // Kosakata yang BENAR-BENAR tersimpan adalah FOTO_KENDARAAN, STNK, dan
        // SIM - itulah yang ditulis pengajuan permit karyawan. Daftar lama di
        // sini hanya mengenal ejaan FOTO_STNK/FOTO_SIM, sehingga setiap upaya
        // mengganti dokumen STNK atau SIM yang sudah ada dibalas 422 oleh
        // validasi, bukan oleh sesuatu yang bisa dibaca verifikator.
        $jenisSah = ['FOTO_KENDARAAN', 'STNK', 'SIM', 'FOTO_STNK', 'FOTO_SIM'];

        $request->validate([
            'no_transaksi' => 'required|string',
            'file' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'jenis_dokumen' => 'required_with:file|string|in:'.implode(',', $jenisSah),
            'dokumen' => 'nullable|array',
            'dokumen.*' => 'file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $noTransaksi = $request->input('no_transaksi');

        /** @var array<string, \Illuminate\Http\UploadedFile> $berkas */
        $berkas = [];

        foreach ((array) $request->file('dokumen', []) as $jenis => $file) {
            $kanonik = $this->kanonikJenisDokumen((string) $jenis);

            if (! in_array($kanonik, ['FOTO_KENDARAAN', 'STNK', 'SIM'], true)) {
                return ResponseHelper::errorWithCode(
                    "Jenis dokumen '{$jenis}' tidak dikenal.",
                    'VISITOR_PERMIT_DOCUMENT_TYPE_INVALID',
                    422,
                );
            }

            $berkas[$kanonik] = $file;
        }

        if ($request->hasFile('file')) {
            $berkas[$this->kanonikJenisDokumen((string) $request->input('jenis_dokumen'))] = $request->file('file');
        }

        if ($berkas === []) {
            return ResponseHelper::errorWithCode(
                'Tidak ada berkas yang dikirim.',
                'VISITOR_PERMIT_DOCUMENT_EMPTY',
                422,
            );
        }

        $approverKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';
        $validation = $this->approvalRequestService->validateApproverAccess($noTransaksi, $approverKode);

        if (! $validation['valid']) {
            return ResponseHelper::errorWithCode($validation['reason'], 'VISITOR_APPROVER_ACCESS_DENIED', 403);
        }

        $tersimpan = [];

        foreach ($berkas as $jenis => $file) {
            try {
                $tersimpan[$jenis] = $this->simpanDokumenPermit($noTransaksi, $jenis, $file);
            } catch (\Throwable $e) {
                Log::error('Gagal memperbarui dokumen permit', [
                    'no_transaksi' => $noTransaksi,
                    'jenis' => $jenis,
                    'error' => $e->getMessage(),
                ]);

                // Yang sudah tersimpan TIDAK ditarik kembali. Ia bukti
                // pemeriksaan yang benar-benar dilakukan; membuangnya karena
                // berkas berikutnya gagal berarti verifikator mengunggah ulang
                // semuanya tanpa tahu mana yang sebenarnya bermasalah.
                return ResponseHelper::errorWithCode(
                    "Dokumen {$jenis} gagal disimpan.".($tersimpan === [] ? '' : ' Berkas lain sudah tersimpan.'),
                    'VISITOR_PERMIT_DOCUMENT_UPLOAD_FAILED',
                    500,
                );
            }
        }

        return ResponseHelper::success(
            ['tersimpan' => array_keys($tersimpan), 'path' => reset($tersimpan) ?: null],
            count($tersimpan) > 1
                ? count($tersimpan).' dokumen berhasil disimpan.'
                : 'Dokumen berhasil diperbarui.',
        );
    }

    private function kanonikJenisDokumen(string $jenis): string
    {
        return match (strtoupper(trim($jenis))) {
            'FOTO_STNK' => 'STNK',
            'FOTO_SIM' => 'SIM',
            default => strtoupper(trim($jenis)),
        };
    }

    /**
     * Simpan satu berkas beserta metadatanya; memulangkan path barunya.
     *
     * Unggahan jaringan diselesaikan DI LUAR transaksi. Transaksi berikutnya
     * hanya mengunci satu baris metadata dan selesai cepat, sehingga dua
     * unggahan bersamaan tidak saling meninggalkan path yatim sebagai hasil
     * akhir.
     */
    private function simpanDokumenPermit(string $noTransaksi, string $jenisDokumen, \Illuminate\Http\UploadedFile $file): string
    {
        $extension = strtolower($file->extension() ?: 'bin'); // dari ISI berkas (MIME), bukan nama kiriman klien
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $safeJenis = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $jenisDokumen));
        $fileName = "{$noTransaksi}_{$safeJenis}_".\Illuminate\Support\Str::uuid()->toString().".{$extension}";

        $path = $this->storeUploadedFile($file, "visitor/permits/{$noTransaksi}/{$fileName}", 'gcs');

        try {
            $existing = DB::transaction(function () use ($noTransaksi, $jenisDokumen, $path) {
                $permitExists = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                    ->where('No_Transaksi', $noTransaksi)
                    ->lockForUpdate()
                    ->exists();

                if (! $permitExists) {
                    throw new \DomainException('Transaksi permit tidak ditemukan.');
                }

                $existing = DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Jenis_Dokumen', $jenisDokumen)
                    ->first();

                if ($existing) {
                    DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')
                        ->where('Id_Permit_Dokumen', $existing->Id_Permit_Dokumen)
                        ->update(['Path_File_URL' => $path]);
                } else {
                    DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->insert([
                        'No_Transaksi' => $noTransaksi,
                        // Diisi, bukan dibiarkan NULL: kolom ini yang menautkan
                        // dokumen ke baris detail permitnya, dan tautan yang
                        // kosong membuat dokumen tertinggal ketika detailnya
                        // dibersihkan.
                        'Id_Detail_Permit' => DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
                            ->where('No_Transaksi', $noTransaksi)
                            ->value('Id_Permit_Detail'),
                        'Jenis_Dokumen' => $jenisDokumen,
                        'Path_File_URL' => $path,
                    ]);
                }

                return $existing;
            });
        } catch (\Throwable $e) {
            try {
                \Illuminate\Support\Facades\Storage::disk('gcs')->delete($path);
            } catch (\Throwable $cleanupError) {
                Log::warning('Dokumen permit baru gagal dibersihkan setelah metadata gagal disimpan', [
                    'path' => $path,
                    'error' => $cleanupError->getMessage(),
                ]);
            }

            throw $e;
        }

        if ($existing?->Path_File_URL && $existing->Path_File_URL !== $path) {
            try {
                \Illuminate\Support\Facades\Storage::disk('gcs')->delete($existing->Path_File_URL);
            } catch (\Throwable $cleanupError) {
                Log::warning('Dokumen permit lama gagal dibersihkan dari GCS', [
                    'no_transaksi' => $noTransaksi,
                    'path' => $existing->Path_File_URL,
                ]);
            }
        }

        return $path;
    }

    protected function storeUploadedFile($file, string $path, string $disk = 'gcs'): string
    {
        $temporaryPath = $file->getRealPath() ?: $file->getPathname();
        if (! is_string($temporaryPath) || trim($temporaryPath) === '' || ! is_file($temporaryPath)) {
            throw new \Exception('File upload tidak memiliki temporary path yang valid.');
        }

        $stream = fopen($temporaryPath, 'rb');
        if ($stream === false) {
            throw new \Exception('Gagal membaca file upload.');
        }

        $stored = false;
        try {
            $stored = \Illuminate\Support\Facades\Storage::disk('gcs')->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $stored) {
            throw new \Exception('Gagal menyimpan file.');
        }

        return $path;
    }

    // ─── TAMU ────────────────────────────────────────────────────────────────

    public function getTamuList(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 15);
            $mode = $request->input('mode', 'antrian');
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $query = $this->permitQuery->baseTamuQuery();
            TipeTamu::saring($query, TipeTamu::dariPermintaan($request));

            $unit = strtoupper(trim((string) $request->input('unit', '')));
            if (in_array($unit, MatriksTamuService::UNIT, true)) {
                $query->where('d.B2B_Unit', $unit);
            }

            if ($mode === 'antrian') {
                $query->where('h.Status_Kunjungan', StatusKunjunganTamu::PENDING_APPROVAL);
            } else {
                $query->where('h.Status_Kunjungan', '<>', StatusKunjunganTamu::PENDING_APPROVAL);

                if ($status && in_array($status, StatusKunjunganTamu::TAMPIL, true)) {
                    $query->whereRaw($this->kehadiran->siklusKunjunganSql().' = ?', [$status]);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('d.Nama_Lengkap_Tamu', 'LIKE', "%{$search}%")
                        ->orWhere('d.Instansi_Atau_Asal', 'LIKE', "%{$search}%")
                        ->orWhere('h.No_Transaksi', 'LIKE', "%{$search}%")
                        ->orWhere('h.Kode_Karyawan_PIC', 'LIKE', "%{$search}%");
                });
            }

            if ($dateFrom) {
                $query->whereDate('h.Tanggal_Kunjungan', '>=', $dateFrom);
            }

            if ($dateTo) {
                $query->whereDate('h.Tanggal_Kunjungan', '<=', $dateTo);
            }

            $total = $query->count();
            if ($mode === 'riwayat') {
                // TANPA h.Updated_At: kolom itu ada di header PERMIT, tidak di
                // header TAMU. Menyalin urutan permit apa adanya membuat seluruh
                // riwayat tamu membalas 500 "Invalid column name 'Updated_At'".
                $orderSql = 'COALESCE((SELECT MAX(ar.Tanggal_Approval) FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi = h.No_Transaksi AND ar.Flag_Approval IS NOT NULL), h.Created_At)';
                $data = $query->orderByRaw("{$orderSql} DESC")->skip(($page - 1) * $limit)->take($limit)->get();
            } else {
                $data = $query->orderByDesc('h.Created_At')->skip(($page - 1) * $limit)->take($limit)->get();
            }

            return ResponseHelper::successWithPaginationV2($data, $page, $limit, $total);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_guest_list', 'VISITOR_VERIFICATION_GUEST_LIST_FAILED', 'Daftar tamu gagal dimuat.');
        }
    }

    public function showTamu(Request $request, string $id)
    {
        try {
            // Tautan notifikasi membawa token, bukan Id. Nilai yang tak terbaca
            // harus berhenti di sini: mengalirkannya ke kolom int melempar
            // QueryException dan berbalas 500, bukan 404.
            $idTransaksi = VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, $id);

            $query = $this->permitQuery->baseTamuQuery();
            if ($idTransaksi !== null) {
                $query->where('h.Id_Transaksi_Tamu', $idTransaksi);
            } else {
                $query->where('h.No_Transaksi', $id);
            }

            // Nomor tipe lain dibalas 404, bukan 403: keberadaannya pun bukan
            // urusan meja ini.
            $data = TipeTamu::saring($query, TipeTamu::dariPermintaan($request))->first();

            if (! $data) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $data->Approvals = $this->permitQuery->getApprovals($data->No_Transaksi);
            $data->Foto_Kendaraan_Url = $this->fotoKendaraan->url($data->Path_Foto_Kendaraan ?? null);

            $approverKode = $this->visitorApprovalPolicy->requesterKode(request()->user()) ?? '';
            $data->is_own_tamu = ($approverKode !== '') && (strcasecmp(trim((string) ($data->Kode_Karyawan_PIC ?? '')), trim($approverKode)) === 0);

            return ResponseHelper::success($data);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_guest_detail', 'VISITOR_VERIFICATION_GUEST_DETAIL_FAILED', 'Detail tamu gagal dimuat.');
        }
    }

    /**
     * Beritahu antrean verifikator permit tentang permit COKLAT tamu.
     *
     * Nomornya dibaca ulang dari tautannya, bukan dipulangkan approveTamu():
     * permitnya bisa sudah ada sejak sebelum persetujuan ini (kunjungan yang
     * datanya sempat disunting), dan yang menentukan bukan siapa membuatnya
     * melainkan apakah ia kini menunggu keputusan.
     */
    protected function kabariAntreanPermitTamu(string $noTransaksiTamu): void
    {
        try {
            $noPermit = $this->permitCoklatTamu->nomorPermitUntukTamu($noTransaksiTamu);

            if (! $noPermit) {
                return;
            }

            $permit = $this->permitQuery->basePermitQuery()
                ->where('h.No_Transaksi', $noPermit)
                ->first();

            if (! $permit || $permit->Flag_Approval !== null) {
                return;
            }

            PublishVisitorNotificationsJob::dispatch([[
                'kind' => 'submission',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $noPermit,
                'context' => [
                    'nama_pemohon' => $permit->Nama_Pemohon,
                    'kategori' => $permit->Nama_Kategori,
                    'plat_nomor' => trim((string) $permit->No_Plat_Kendaraan),
                ],
            ]]);
        } catch (\Throwable $e) {
            // Kunjungannya sudah disetujui; kegagalan notifikasi tidak boleh
            // membatalkannya.
            Log::error('Gagal mengirim notifikasi permit COKLAT tamu', [
                'no_transaksi_tamu' => $noTransaksiTamu,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function approveTamu(VisitorApproveRequest $request, string $noTransaksi)
    {
        $this->pastikanTipeRute($request, $noTransaksi);

        try {
            $approverKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';
            $isFinal = $this->visitorDecision->approveTamu(
                $noTransaksi,
                $approverKode,
                $request->validated('catatan'),
            );

            if ($isFinal) {
                $this->kabariPemohon(
                    $this->domainNotifTamu($request),
                    $noTransaksi,
                    VisitorNotificationService::KEPUTUSAN_DISETUJUI,
                );

                // Persetujuan kunjungan MENGAJUKAN permit COKLAT-nya — pengajuan
                // tersendiri, ke antrean dan verifikator yang berbeda. Tanpa
                // baris ini ia masuk antrean permit tanpa seorang pun tahu, dan
                // satu-satunya gejalanya adalah tamu yang tertahan di gerbang.
                //
                // SESUDAH approveTamu() selesai, bukan di dalamnya: alurnya
                // dibuat di dalam transaksi, dan menulis notifikasi di sana
                // menahan lock N_HRIS_Notification yang di-poll top-bar setiap
                // pengguna.
                $this->kabariAntreanPermitTamu($noTransaksi);
            }

            return ResponseHelper::success(
                ['final' => $isFinal],
                $isFinal ? 'Kunjungan tamu disetujui dan diaktifkan.' : 'Approval dicatat dan diteruskan.',
            );
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_APPROVAL_CONFLICT', 409);
        } catch (\Throwable $e) {
            Log::error('Gagal menyetujui tamu', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Kunjungan tamu gagal disetujui.', 'VISITOR_GUEST_APPROVE_FAILED', 500);
        }
    }

    public function rejectTamu(VisitorRejectRequest $request, string $noTransaksi)
    {
        $this->pastikanTipeRute($request, $noTransaksi);

        try {
            $rejectorKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';
            $alasan = $request->validated('alasan');
            $this->visitorDecision->rejectTamu($noTransaksi, $rejectorKode, $alasan);

            $this->kabariPemohon(
                $this->domainNotifTamu($request),
                $noTransaksi,
                VisitorNotificationService::KEPUTUSAN_DITOLAK,
                ['alasan' => $alasan],
            );

            return ResponseHelper::success(null, 'Kunjungan tamu ditolak.');
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_APPROVAL_CONFLICT', 409);
        } catch (\Throwable $e) {
            Log::error('Gagal menolak tamu', ['no_transaksi' => $noTransaksi, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Kunjungan tamu gagal ditolak.', 'VISITOR_GUEST_REJECT_FAILED', 500);
        }
    }

    public function revokeTamu(Request $request, string $noTransaksi)
    {
        $request->validate(['alasan' => 'required|string|max:500']);
        $this->pastikanTipeRute($request, $noTransaksi);

        try {
            $olehKode = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? '';

            // Kunjungan dan permit COKLAT-nya dicabut dalam satu transaksi: izin
            // kendaraan tidak boleh tertinggal hidup untuk kunjungan yang dibatalkan,
            // termasuk bila baris kunjungannya di gerbang sudah kedaluwarsa (M5).
            $hasil = $this->permitCoklatTamu->cabutKunjunganBesertaPermit($noTransaksi, $request->input('alasan'), $olehKode);

            if ($hasil['tamu'] === 0 && $hasil['permit'] === 0) {
                return ResponseHelper::error('Tidak ada akses efektif yang aktif untuk transaksi ini — mungkin belum pernah disetujui, atau sudah dibatalkan/kadaluarsa sebelumnya.', 404);
            }

            $this->kabariPemohon(
                $this->domainNotifTamu($request),
                $noTransaksi,
                VisitorNotificationService::KEPUTUSAN_DIBATALKAN,
                ['alasan' => $request->input('alasan')],
            );

            return ResponseHelper::success(null, 'Kunjungan tamu yang sudah disetujui berhasil dibatalkan.');
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'verification_guest_revoke', 'VISITOR_GUEST_REVOKE_FAILED', 'Akses kunjungan tamu gagal dibatalkan.');
        }
    }

    /**
     * Kabari pemohon atas keputusan verifikator.
     *
     * Dipanggil SESUDAH mesin keputusan selesai — setiap method di
     * VisitorApprovalDecisionService membungkus dirinya dengan DB::transaction,
     * jadi di titik ini transaksinya sudah commit. Menulis notifikasi di dalam
     * transaksi akan menahan lock N_HRIS_Notification yang di-poll top-bar
     * setiap pengguna.
     *
     * Kegagalan notifikasi tidak boleh membatalkan keputusan yang sudah tercatat.
     *
     * @param  array<string,mixed>  $ctx
     */
    private function kabariPemohon(string $domain, string $noTransaksi, string $keputusan, array $ctx = []): void
    {
        try {
            PublishVisitorNotificationsJob::dispatch([[
                'kind' => 'decision',
                'domain' => $domain,
                'no_transaksi' => $noTransaksi,
                'decision' => $keputusan,
                'context' => $ctx,
            ]]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi keputusan visitor', [
                'domain' => $domain,
                'no_transaksi' => $noTransaksi,
                'keputusan' => $keputusan,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
