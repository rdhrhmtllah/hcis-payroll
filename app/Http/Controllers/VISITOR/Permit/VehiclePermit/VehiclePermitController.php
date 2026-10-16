<?php

namespace App\Http\Controllers\VISITOR\Permit\VehiclePermit;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use App\Services\VISITOR\Governance\MasterData\VisitorMasterDataCatalog;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\KartuNfcPemohon;
use App\Services\VISITOR\Permit\VehiclePermit\KartuPermitQr;
use App\Services\VISITOR\Permit\VehiclePermit\KategoriPermitPengajuan;
use App\Services\VISITOR\Permit\VehiclePermit\PlatNomorKendaraan;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitMasterService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaExceeded;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQuotaService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Idempotency\VisitorIdempotencyService;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VehiclePermitController extends Controller
{
    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected VisitorEfektifService $visitorEfektif,
        protected VisitorIdempotencyService $visitorIdempotency,
        protected VisitorPermitQuotaService $visitorPermitQuota,
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorPermitChecklistService $permitChecklist,
        protected PlatNomorKendaraan $platNomor,
        protected JadwalInspeksiService $jadwalInspeksi,
        protected KartuPermitQr $kartuQr,
        protected KartuNfcPemohon $kartuNfc,
        protected KontakBantuanService $kontakBantuan,
    ) {}

    public function index(Request $request)
    {
        return Inertia::render(
            'VISITOR/Permit/VehiclePermit/VehiclePermitPage',
            array_merge(
                [
                    'user' => $request->user(),
                    'serverDate' => Carbon::today()->toDateString(),
                    // Form permit terkunci sampai kartu NFC pemohon terpasang;
                    // store() memeriksa ulang, layar hanya memberi tahu lebih awal.
                    'kartuNfc' => $this->kartuNfc->periksa($this->visitorApprovalPolicy->requesterKode($request->user())),
                    // Kontak resmi untuk konsultasi permit (Master Data Permit).
                    'kontakBantuan' => $this->kontakBantuan->daftar('VISITOR', VisitorMasterDataCatalog::FITUR_KONTAK_PERMIT),
                    'aturanInspeksi' => [
                        'hari_ke_depan' => JadwalInspeksiService::HARI_KE_DEPAN,
                        // Lewat pembaca config, bukan konstantanya: angka di layar
                        // harus berasal dari sumber yang sama dengan penjaganya.
                        'maks_jadwal_ulang' => JadwalInspeksiService::maksJadwalUlang(),
                        'maks_tidak_hadir' => JadwalInspeksiService::maksTidakHadir(),
                    ],
                ],
                $this->visitorApprovalPolicy->pageProps('permit', $request->user()),
            ),
        );
    }

    public function getList(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 15);
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $tipe = $request->input('tipe', '');

            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());

            if (! $kodeKaryawan) {
                return ResponseHelper::errorWithCode(
                    'Identitas karyawan tidak ditemukan.',
                    'VISITOR_REQUESTER_NOT_FOUND',
                    403,
                );
            }

            // Daftar Riwayat SENGAJA memuat permit tamu yang ditanggung
            // karyawan ini. Ia memang bukan izin miliknya — karena itu tidak
            // ikut ke Pass Digital dan tidak bisa dibatalkan dari sini — tetapi
            // menghilangkannya dari riwayat membuat PIC kehilangan satu-satunya
            // jejak izin yang terbit atas namanya. Barisnya ditandai di layar
            // lewat No_Transaksi_Tamu / Nama_Tamu yang ikut dibalas.
            $query = $this->basePermitQuery()
                ->where('h.Kode_Karyawan_Pemohon', $kodeKaryawan);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'LIKE', "%{$search}%")
                        ->orWhere('h.No_Transaksi', 'LIKE', "%{$search}%")
                        ->orWhere(
                            DB::raw(
                                "CONCAT(ISNULL(d.Plat_Nomor_Prefix, ''), ' ', ISNULL(d.Plat_Nomor_Angka, ''), ' ', ISNULL(d.Plat_Nomor_Suffix, ''))",
                            ),
                            'LIKE',
                            "%{$search}%",
                        )
                        ->orWhere('vm.Nama_Model_Kendaraan', 'LIKE', "%{$search}%");
                });
            }

            if ($status) {
                $query->whereRaw($this->statusCaseSql().' = ?', [$status]);
            }

            if ($tipe) {
                $query->where('vt.Nama_Tipe_Kendaraan', 'LIKE', "%{$tipe}%");
            }

            $total = $query->count();
            $data = $query
                ->orderByDesc('h.Created_At')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            return ResponseHelper::successWithPaginationV2($data, $page, $limit, $total);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'permit_page_data', 'VISITOR_PERMIT_PAGE_DATA_FAILED', 'Data permit gagal dimuat.');
        }
    }

    public function store(Request $request)
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            return ResponseHelper::errorWithCode('Header Idempotency-Key wajib diisi dan maksimal 100 karakter.', 'VISITOR_IDEMPOTENCY_KEY_REQUIRED', 422);
        }

        $request->validate([
            'no_plat' => 'nullable|string|max:30',
            'plat_prefix' => 'nullable|string|max:5',
            'plat_angka' => 'nullable|string|max:10',
            'plat_suffix' => 'nullable|string|max:10',
            'id_vehicle_type' => 'required|integer',
            'id_vehicle_model' => 'required|integer',
            'id_vehicle_color' => 'required|integer',
            'tahun_kendaraan' => 'required|integer',
            'id_kategori_permit' => 'required|integer',
            'keterangan_pengajuan' => 'nullable|string|max:500',
            'foto_kendaraan' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            'foto_stnk' => 'required|mimes:jpg,jpeg,png,pdf|max:5120',
            'foto_sim' => 'required|image|mimes:jpg,jpeg,png|max:5120',
            // Jadwal inspeksi fisik kendaraan (DDL-2026-123): dipilih pemohon
            // dari slot yang dibuka SHE, dipesan bersama pengajuannya.
            'inspeksi_id_pos' => 'required|integer',
            'inspeksi_mulai' => 'required|date_format:Y-m-d H:i',
        ], [], [
            'inspeksi_id_pos' => 'pos inspeksi',
            'inspeksi_mulai' => 'jadwal inspeksi',
        ]);

        $config = $this->visitorApprovalPolicy->config('permit');
        if (! (bool) $config['enabled']) {
            return ResponseHelper::error('Fitur permit sedang dinonaktifkan.', 403);
        }

        $uploadedDocuments = [];
        $transactionStarted = false;
        $kodeKaryawan = null;
        try {
            $noTransaksi = $this->generateNoTransaksi('PMT');
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());

            if (! $kodeKaryawan) {
                throw new \Exception('Kode karyawan pemohon tidak ditemukan.');
            }

            $existing = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('Kode_Karyawan_Pemohon', $kodeKaryawan)
                ->where('Idempotency_Key', $idempotencyKey)
                ->first();
            if ($existing) {
                return ResponseHelper::success([
                    'no_transaksi' => $existing->No_Transaksi,
                    'id' => $existing->Id_Transaksi_Permit,
                    'idempotent_replay' => true,
                ], 'Pengajuan permit sudah pernah diterima.');
            }

            if ($this->permitQuery->pemohonSudahResign($kodeKaryawan)) {
                return ResponseHelper::errorWithCode(
                    'Anda tercatat sudah resign; permit kendaraan tidak bisa diajukan.',
                    'VISITOR_PERMIT_PEMOHON_RESIGN',
                    403,
                );
            }

            // Syarat kartu NFC: sesudah replay (kiriman ulang yang sah tetap
            // dijawab), sebelum kuota dan unggahan berkas.
            $kartu = $this->kartuNfc->periksa($kodeKaryawan);
            if (! $kartu['boleh']) {
                return ResponseHelper::errorWithCode(
                    (string) $kartu['pesan'],
                    'VISITOR_PERMIT_KARTU_NFC_'.$kartu['status'],
                    403,
                );
            }

            $terpakai = $this->visitorPermitQuota->terpakai($kodeKaryawan);
            if ($this->visitorPermitQuota->melampauiBatas($terpakai)) {
                return ResponseHelper::errorWithCode(
                    'Jatah permit Anda sudah penuh: '.$terpakai.' dari maksimal '
                        .$this->visitorPermitQuota->batas()
                        .'. Jatah kembali setelah permit ditolak atau kedaluwarsa.',
                    'VISITOR_PERMIT_LIMIT_REACHED',
                    422,
                );
            }

            // Kuota diperiksa SESUDAH pemeriksaan replay dan SEBELUM unggah
            // berkas. Urutannya bukan selera: di atas replay, pengiriman ulang
            // yang sah ikut ditolak padahal tak menambah apa pun; di bawah
            // unggahan, tiga berkas terlanjur naik untuk pengajuan yang ditolak.

            $plat = $this->resolvePlatNomor($request);
            if (! $plat['prefix'] || ! $plat['angka'] || ! $plat['suffix']) {
                throw new \Exception('Nomor plat kendaraan wajib diisi lengkap.');
            }

            // Kolom permit varchar(10). Diperiksa SEBELUM insert supaya yang
            // sampai ke pemakainya adalah nama bagian yang kepanjangan, bukan
            // galat truncate SQL Server yang tak menyebut field mana pun.
            if ($bagian = $this->platNomor->muat($plat, PlatNomorKendaraan::LEBAR_PERMIT)) {
                throw new \Exception(
                    "Bagian plat nomor '{$bagian}' melebihi ".PlatNomorKendaraan::LEBAR_PERMIT.' karakter.'
                );
            }

            $platPrefix = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')
                ->where('Kode_Plat', $plat['prefix'])
                ->where('Flag_Aktif', 'Y')
                ->first();
            $vehicleType = DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
                ->where('Id_Vehicle_Type', $request->input('id_vehicle_type'))
                ->where('Flag_Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNull('Akses')->orWhereIn('Akses', ['PERMIT', 'ALL']);
                })
                ->first();
            $model = DB::table('N_EMI_VISITOR_Master_Vehicle_Model')
                ->where('Id_Vehicle_Model', $request->input('id_vehicle_model'))
                ->where('Id_Master_Tipe', $request->input('id_vehicle_type'))
                ->where('Flag_Aktif', 'Y')
                ->first();
            $color = DB::table('N_EMI_VISITOR_Master_Vehicle_Color')
                ->where('Id_Master_Color', $request->input('id_vehicle_color'))
                ->where('Flag_Aktif', 'Y')
                ->first();
            // Penyaring yang SAMA dengan yang dipakai dropdown-nya. Kalau
            // hanya dropdown yang disaring, siapa pun bisa mengirim
            // id_kategori_permit milik kategori tamu lewat request dan lolos
            // tanpa satu pun galat — permit COKLAT pun terbit dari form
            // karyawan, melewati seluruh mesin izin tamu.
            $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
                ->where('Id_Kategori_Permit', $request->input('id_kategori_permit'))
                ->where('Flag_Aktif', 'Y')
                ->where(fn ($query) => $this->terapkanKategoriPengajuan($query))
                ->first();

            if (! $platPrefix || ! $vehicleType || ! $model || ! $color || ! $kategori) {
                return ResponseHelper::errorWithCode(
                    'Master data permit tidak valid atau sudah nonaktif.',
                    'VISITOR_PERMIT_MASTER_INACTIVE',
                    422,
                );
            }

            $isAutoApproved = ! (bool) $config['needs_approval'];

            // Pemeriksaan awal TANPA kunci, sebelum berkas diunggah: slot yang
            // sudah penuh tidak boleh menghabiskan tiga unggahan. Kuncinya
            // dipasang lagi di dalam transaksi (JadwalInspeksiService::pesan).
            $slotDiminta = collect($this->jadwalInspeksi->slotTersedia(
                Carbon::parse($request->input('inspeksi_mulai')),
                Carbon::parse($request->input('inspeksi_mulai')),
            ))->first(fn ($slot) => $slot['id_pos'] === (int) $request->input('inspeksi_id_pos')
                && $slot['mulai'] === $request->input('inspeksi_mulai'));

            if (! $slotDiminta) {
                return ResponseHelper::errorWithCode(
                    'Slot inspeksi yang dipilih sudah penuh atau tidak tersedia. Pilih slot lain.',
                    'VISITOR_PERMIT_SLOT_INVALID',
                    422,
                );
            }

            $uploadedDocuments = $this->uploadDocumentFiles($request, $noTransaksi);

            DB::beginTransaction();
            $transactionStarted = true;

            // Pemeriksaan awal di atas menghindari upload yang sia-sia. Guard
            // kedua ini adalah sumber kebenaran: lock satu row pemohon membuat
            // dua submit bersamaan tidak dapat sama-sama memakai slot terakhir.
            $this->visitorPermitQuota->assertAvailableWithinTransaction($kodeKaryawan);

            $headerId = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan_Pemohon' => $kodeKaryawan,
                // Pasangan Sumber_Pengajuan='API' pada pengajuan mitra. Ditulis
                // eksplisit di setiap pengajuan baru supaya penyaring tidak
                // bergantung pada backfill yang hanya jalan sekali.
                'Sumber_Pengajuan' => 'HCIS',
                'Flag_Aktif' => 'Y',
                'Flag_Approval' => $isAutoApproved ? 'Y' : null,
                'Approved_At' => $isAutoApproved ? now() : null,
                'Approved_By' => $isAutoApproved ? 'SYSTEM/AUTO' : null,
                'Created_At' => now(),
                'Created_By' => $kodeKaryawan,
                'Updated_At' => now(),
                'Updated_By' => $kodeKaryawan,
                'Idempotency_Key' => $idempotencyKey,
            ]);

            $detailId = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insertGetId([
                'Id_Transaksi_Permit' => $headerId,
                'No_Transaksi' => $noTransaksi,
                'Plat_Nomor_Prefix' => $plat['prefix'],
                'Plat_Nomor_Angka' => $plat['angka'],
                'Plat_Nomor_Suffix' => $plat['suffix'],
                'Id_Master_Model' => $request->input('id_vehicle_model'),
                'Id_Master_Warna' => $request->input('id_vehicle_color'),
                'Tahun_Kendaraan' => $request->input('tahun_kendaraan'),
                'Id_Master_Kategori_Permit' => $kategori->Id_Kategori_Permit,
                'Snapshot_Kategori_Permit' => $kategori->Nama_Kategori,
                'Id_Master_Durasi' => null,
                'Snapshot_Nama_Durasi' => null,
                'Keterangan_Pengajuan' => $request->input('keterangan_pengajuan'),
                'Tanggal_Mulai_Berlaku' => null,
                'Tanggal_Kadaluarsa' => null,
            ]);

            $this->persistDocumentMetadata($uploadedDocuments, $noTransaksi, $detailId);
            $this->permitChecklist->buatSnapshot(
                $noTransaksi,
                $detailId,
                (int) $kategori->Id_Kategori_Permit,
                (int) $request->input('id_vehicle_type'),
            );

            if ((bool) $config['needs_approval']) {
                $this->visitorApprovalPolicy->createFlow('permit', $noTransaksi, $kodeKaryawan);
            }

            $this->jadwalInspeksi->pesan(
                $noTransaksi,
                (int) $request->input('inspeksi_id_pos'),
                (string) $request->input('inspeksi_mulai'),
                $kodeKaryawan,
            );

            DB::commit();

            if ($isAutoApproved) {
                try {
                    $this->visitorEfektif->syncPermitEfektif($noTransaksi);
                } catch (\Exception $e) {
                    Log::error('Gagal sync Efektif permit', [
                        'no_transaksi' => $noTransaksi,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Sesudah commit, bukan di dalam transaksi: menulis notifikasi sambil
            // memegang transaksi pengajuan akan menahan lock N_HRIS_Notification,
            // tabel yang di-poll top-bar setiap pengguna.
            if (! $isAutoApproved) {
                try {
                    PublishVisitorNotificationsJob::dispatch([[
                        'kind' => 'submission',
                        'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                        'no_transaksi' => $noTransaksi,
                        'context' => [
                            'nama_pemohon' => $request->user()?->karyawan?->Nama,
                            'kategori' => $kategori->Nama_Kategori,
                            'plat_nomor' => trim($plat['prefix'].' '.$plat['angka'].' '.$plat['suffix']),
                        ],
                    ]]);
                } catch (\Throwable $e) {
                    // Pengajuan sudah ter-commit; kegagalan notifikasi tidak boleh membatalkannya.
                    Log::error('Gagal mengirim notifikasi pengajuan permit', [
                        'no_transaksi' => $noTransaksi,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $message = $isAutoApproved
                ? 'Permit berhasil dibuat dan disetujui otomatis.'
                : 'Pengajuan permit berhasil dikirim dan menunggu approval.';

            return ResponseHelper::success(['no_transaksi' => $noTransaksi, 'id' => $headerId], $message, 201);
        } catch (\Throwable $e) {
            if ($transactionStarted && DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $this->cleanupUploadedDocuments($uploadedDocuments);

            // Yang KHUSUS lebih dulu. VisitorPermitQuotaExceeded adalah turunan
            // DomainException, jadi menaruh cabang umum di atas membuat cabang
            // ini kode mati - pemohon yang kehabisan jatah menerima kode galat
            // 'SLOT_INVALID' yang tidak ada hubungannya, dan layar tidak bisa
            // membedakan "slot direbut" dari "jatah habis".
            if ($e instanceof VisitorPermitQuotaExceeded) {
                return ResponseHelper::errorWithCode($e->getMessage(), $e->kode, 422);
            }

            // Slot direbut pengaju lain di antara pemeriksaan awal dan kunci.
            if ($e instanceof \DomainException) {
                return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_PERMIT_SLOT_INVALID', 422);
            }

            if ($kodeKaryawan && $this->visitorIdempotency->isUniqueKeyConflict(
                $e,
                'UX_VISITOR_Permit_Requester_Idempotency',
            )) {
                $existing = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                    ->where('Kode_Karyawan_Pemohon', $kodeKaryawan)
                    ->where('Idempotency_Key', $idempotencyKey)
                    ->first();

                if ($existing) {
                    return ResponseHelper::success([
                        'no_transaksi' => $existing->No_Transaksi,
                        'id' => $existing->Id_Transaksi_Permit,
                        'idempotent_replay' => true,
                    ], 'Pengajuan permit sudah pernah diterima.');
                }
            }

            Log::error('Gagal mengajukan permit', [
                'requester' => $kodeKaryawan,
                'error' => $e->getMessage(),
            ]);

            return ResponseHelper::errorWithCode(
                'Pengajuan permit gagal disimpan.',
                'VISITOR_PERMIT_SUBMISSION_FAILED',
                500,
            );
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            // Tautan notifikasi membawa token, bukan Id. Nilai yang tak terbaca
            // harus berhenti di sini: mengalirkannya ke kolom int melempar
            // QueryException dan berbalas 500, bukan 404.
            $idTransaksi = VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_PERMIT, $id);
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());

            $query = $this->basePermitQuery();

            if ($idTransaksi !== null) {
                $query->where('h.Id_Transaksi_Permit', $idTransaksi);
            } else {
                $query->where(function ($q) use ($id) {
                    $q->where('h.No_Transaksi', $id)
                        ->orWhere('h.No_Transaksi_Tamu', $id);
                });
            }

            // Tanpa kode karyawan tidak ada pemilik yang bisa dicocokkan: dulu
            // penyaringnya dilewati dan detail permit SIAPA PUN terbaca (audit M1).
            if (! $kodeKaryawan) {
                return ResponseHelper::errorWithCode('Identitas karyawan tidak ditemukan.', 'VISITOR_REQUESTER_NOT_FOUND', 403);
            }

            $query->where('h.Kode_Karyawan_Pemohon', $kodeKaryawan);

            $header = $query->first();

            if (! $header) {
                return ResponseHelper::error('Data tidak ditemukan.', 404);
            }

            return ResponseHelper::success([
                'permit' => $header,
                'dokumens' => $this->permitQuery->getDokumens($header->No_Transaksi),
                'checklists' => $this->permitQuery->getPermitChecklists($header->No_Transaksi),
                'approvals' => $this->permitQuery->getApprovals($header->No_Transaksi),
                'inspeksi' => $this->jadwalInspeksi->ringkasan($header->No_Transaksi),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'permit_detail', 'VISITOR_PERMIT_DETAIL_FAILED', 'Detail permit gagal dimuat.');
        }
    }

    public function cancel(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());
            $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('Id_Transaksi_Permit', $id)
                ->where('Kode_Karyawan_Pemohon', $kodeKaryawan)
                // Izin tamu TIDAK boleh dibatalkan dari layar permit karyawan.
                // Ia terikat pada kunjungannya; membatalkannya sendirian
                // meninggalkan kunjungan yang tetap terjadwal tanpa izin
                // gerbang — dua dokumen yang bercerai tanpa satu pun galat.
                // Pembatalannya ikut pembatalan kunjungan, lewat
                // PermitCoklatTamuService::batalkan().
                ->whereNull('No_Transaksi_Tamu')
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header) {
                DB::rollBack();

                return ResponseHelper::errorWithCode('Data permit tidak ditemukan.', 'VISITOR_PERMIT_NOT_FOUND', 404);
            }

            if ($header->Flag_Approval !== null) {
                DB::rollBack();

                return ResponseHelper::errorWithCode(
                    'Permit yang sudah diproses tidak dapat dibatalkan sendiri.',
                    'VISITOR_PERMIT_CANCEL_CONFLICT',
                    409,
                );
            }

            $hasProcessedApproval = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $header->No_Transaksi)
                ->whereNotNull('Flag_Approval')
                ->exists();

            if ($hasProcessedApproval) {
                DB::rollBack();

                return ResponseHelper::errorWithCode(
                    'Permit sudah diproses approver dan tidak dapat dibatalkan sendiri.',
                    'VISITOR_PERMIT_CANCEL_CONFLICT',
                    409,
                );
            }

            // Pengajuan yang batas approval-nya lewat tinggal arsip.
            if ($this->jadwalInspeksi->approvalKedaluwarsa($header->No_Transaksi)) {
                DB::rollBack();

                return ResponseHelper::errorWithCode(
                    'Batas waktu pengajuan ini sudah lewat; pengajuannya tidak bisa diubah lagi.',
                    'VISITOR_PERMIT_EXPIRED',
                    409,
                );
            }

            if (! $this->jadwalInspeksi->bolehDibatalkanPemohon($header->No_Transaksi)) {
                DB::rollBack();

                return ResponseHelper::errorWithCode(
                    'Pengajuan permit hanya bisa dibatalkan paling lambat H-1 sebelum jadwal inspeksi.',
                    'VISITOR_PERMIT_CANCEL_LOCKED',
                    409,
                );
            }

            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('Id_Transaksi_Permit', $id)
                ->whereNull('Flag_Approval')
                ->update(['Flag_Aktif' => 'N', 'Updated_At' => now(), 'Updated_By' => $kodeKaryawan]);

            // Slot inspeksinya dilepas untuk pengaju lain.
            $this->jadwalInspeksi->batalkan($header->No_Transaksi, 'Dibatalkan oleh pemohon.', $kodeKaryawan);
            // Akses palang sementaranya ikut mati. Ditandai EXPIRED, bukan
            // DIBATALKAN: cabang 'REVOKED' pada permitStatusSql diperiksa
            // paling awal, dan memakainya membuat pengajuan yang dibatalkan
            // sendiri terbaca "Dicabut" di seluruh layar.
            $this->visitorEfektif->akhiriAksesInspeksi($header->No_Transaksi);

            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $header->No_Transaksi)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'T',
                    'Catatan' => 'Dibatalkan oleh pemohon.',
                    'Tanggal_Approval' => now(),
                    'update_at' => now(),
                ]);

            DB::commit();

            return ResponseHelper::success(null, 'Pengajuan permit berhasil dibatalkan.');
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Gagal membatalkan permit', ['id' => $id, 'error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode(
                'Pengajuan permit gagal dibatalkan.',
                'VISITOR_PERMIT_CANCEL_FAILED',
                500,
            );
        }
    }

    /** Slot inspeksi yang masih bisa dipilih, HARI_KE_DEPAN hari ke depan. */
    public function slotInspeksi(Request $request)
    {
        try {
            /* `semua=1` memulangkan slot penuh dan yang jamnya sudah lewat juga,
               masing-masing bertanda 'keadaan'. Layar pemohon memakainya supaya
               jadwal yang habis terbaca sebagai HABIS, bukan sebagai jadwal yang
               tak pernah dibuka. Bawaannya tetap hanya yang bisa dipesan, supaya
               pemanggil lama tidak berubah artinya. */
            $semua = $request->boolean('semua');
            $dari = Carbon::today();
            $sampai = Carbon::today()->addDays(JadwalInspeksiService::HARI_KE_DEPAN);

            return ResponseHelper::success([
                'slot' => $semua
                    ? $this->jadwalInspeksi->slotBerkeadaan($dari, $sampai)
                    : $this->jadwalInspeksi->slotTersedia($dari, $sampai),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'permit_inspection_slots', 'VISITOR_PERMIT_SLOT_LIST_FAILED', 'Slot inspeksi gagal dimuat.');
        }
    }

    /** Pemohon memindah jadwal inspeksi permitnya sendiri yang masih menunggu. */
    public function pindahInspeksi(Request $request, string $id)
    {
        $request->validate([
            'inspeksi_id_pos' => 'required|integer',
            'inspeksi_mulai' => 'required|date_format:Y-m-d H:i',
        ]);

        $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());
        $header = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Id_Transaksi_Permit', $id)
            ->where('Kode_Karyawan_Pemohon', $kodeKaryawan)
            ->whereNull('No_Transaksi_Tamu')
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $header) {
            return ResponseHelper::errorWithCode('Data permit tidak ditemukan.', 'VISITOR_PERMIT_NOT_FOUND', 404);
        }

        if ($header->Flag_Approval !== null) {
            return ResponseHelper::errorWithCode('Permit sudah diputus; jadwal inspeksinya tidak bisa diubah.', 'VISITOR_PERMIT_SLOT_LOCKED', 409);
        }

        if ($this->jadwalInspeksi->approvalKedaluwarsa($header->No_Transaksi)) {
            return ResponseHelper::errorWithCode('Batas waktu pengajuan ini sudah lewat; jadwal inspeksinya tidak bisa diubah lagi.', 'VISITOR_PERMIT_EXPIRED', 409);
        }

        try {
            $this->jadwalInspeksi->pindah(
                $header->No_Transaksi,
                (int) $request->input('inspeksi_id_pos'),
                (string) $request->input('inspeksi_mulai'),
                (string) $kodeKaryawan,
            );

            return ResponseHelper::success(
                ['inspeksi' => $this->jadwalInspeksi->ringkasan($header->No_Transaksi)],
                'Jadwal inspeksi berhasil dipindah.',
            );
        } catch (\DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_PERMIT_SLOT_INVALID', 422);
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'permit_inspection_reschedule', 'VISITOR_PERMIT_SLOT_RESCHEDULE_FAILED', 'Jadwal inspeksi gagal dipindah.');
        }
    }

    /**
     * Kartu tempel dashboard sebagai PDF setengah A4 (A5 mendatar).
     *
     * Dirender server supaya yang dilihat di layar, yang diunduh, dan yang
     * dicetak adalah BERKAS YANG SAMA. Ditampilkan inline agar bisa dibuka
     * HcisFileViewer; unduhannya memakai URL yang sama.
     *
     * Hanya permit AKTIF milik pemohonnya sendiri: kartu yang dicabut atau
     * kedaluwarsa tidak boleh punya wujud cetak yang tampak sah.
     */
    public function kartuPdf(Request $request, string $id)
    {
        $kodeKaryawan = (string) $this->visitorApprovalPolicy->requesterKode($request->user());

        $permit = $this->permitMilikKaryawan($kodeKaryawan)
            ->where('h.Id_Transaksi_Permit', (int) $id)
            ->first();

        return $this->balasKartuPdf($request, $permit, (int) $id);
    }

    /** Satu penyusun PDF untuk dua pintu: pemegang kartu dan admin. */
    private function balasKartuPdf(Request $request, ?object $permit, int $id)
    {
        if (! $permit) {
            return ResponseHelper::errorWithCode('Data permit tidak ditemukan.', 'VISITOR_PERMIT_NOT_FOUND', 404);
        }

        if (($permit->Status_Permit ?? null) !== 'APPROVED') {
            return ResponseHelper::errorWithCode(
                'Kartu hanya bisa dicetak untuk permit yang masih aktif.',
                'VISITOR_PERMIT_CARD_NOT_ACTIVE',
                422,
            );
        }

        try {
            // Dua tata letak kartu hidup berdampingan; keduanya memakai kunci
            // data yang sama, jadi menambah/mengubah template tidak menyentuh
            // isiKartuPermit(). Bawaan t2 (badge zona kolom 3 + plat kolom 9);
            // ?template=1 untuk varian bar jenis permit selebar kartu.
            $template = $request->query('template') === '1' ? 't1' : 't2';

            // Penyubsetan font: 860 KB -> 22 KB per berkas.
            //
            // laravel-dompdf mematikannya secara bawaan, sehingga SELURUH
            // berkas DejaVu Sans ikut tertanam di tiap PDF — ukurannya nyaris
            // tak bergantung pada jumlah kartu, dan itulah tandanya bahwa yang
            // berat fontnya, bukan isinya. Kartu ini diunduh di ponsel di
            // gerbang, dan empat puluh kali lipat adalah selisih antara
            // "terbuka" dan "menyerah".
            //
            // Fontnya TETAP DejaVu, bukan font inti dompdf: yang inti
            // ber-encoding WinAnsi dan akan merusak nama atau instansi yang
            // memuat huruf di luar Latin-1, diam-diam, di kertas yang dibaca
            // petugas.
            $pdf = Pdf::loadView('exports.visitor-permit-kartu-'.$template, ['kartu' => $this->isiKartuPermit($permit, $id)])
                ->setOption('isFontSubsettingEnabled', true)
                ->setPaper('a4', 'portrait');

            return $pdf->stream('kartu-permit-'.preg_replace('/[^A-Za-z0-9]/', '', (string) $permit->No_Plat_Kendaraan).'.pdf');
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'permit_kartu_pdf', 'VISITOR_PERMIT_CARD_FAILED', 'Kartu permit gagal dibuat.');
        }
    }

    /**
     * Kartu dicetak ADMIN dari Monitoring / meja verifikator untuk permit milik
     * siapa pun. Kunci halamannya ada di rute; di sini yang dijaga tinggal
     * syarat yang sama dengan kartu milik pemegangnya sendiri — hanya permit
     * AKTIF yang punya wujud cetak, supaya tidak ada kartu yang tampak sah
     * padahal sudah dicabut atau kedaluwarsa.
     */
    public function kartuPdfAdmin(Request $request, string $id)
    {
        $permit = $this->basePermitQuery()
            ->where('h.Id_Transaksi_Permit', (int) $id)
            ->first();

        return $this->balasKartuPdf($request, $permit, (int) $id);
    }

    /**
     * Cetak massal kartu dari Monitoring: satu PDF berisi banyak kartu, DUA
     * per lembar A4 dengan garis potong.
     *
     * Yang tidak aktif disaring diam-diam, bukan menggagalkan seluruh berkas:
     * admin mencentang sepuluh baris, satu di antaranya baru dicabut, dan yang
     * dibutuhkannya tetap sembilan kartu — bukan pesan galat.
     */
    public function kartuPdfBatch(Request $request)
    {
        // Pintu KARYAWAN (menu global): hanya kartu milik sendiri. Tanpa saringan
        // ini Id berurutan cukup untuk mengunduh kartu ber-QR sah milik orang
        // lain (audit 27 Sep 2026, H1). Id milik orang lain disaring diam-diam,
        // sama seperti permit yang tidak aktif.
        $kodeKaryawan = (string) $this->visitorApprovalPolicy->requesterKode($request->user());

        return $this->balasKartuPdfBatch($request, $this->permitMilikKaryawan($kodeKaryawan));
    }

    /** Pintu ADMIN (Monitoring Permit): kartu siapa pun, dijaga kunci halamannya. */
    public function kartuPdfBatchAdmin(Request $request)
    {
        return $this->balasKartuPdfBatch($request, $this->basePermitQuery());
    }

    private function balasKartuPdfBatch(Request $request, $query)
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:60',
            'ids.*' => 'integer',
        ], [], ['ids' => 'daftar permit']);

        $permits = $query
            ->whereIn('h.Id_Transaksi_Permit', $data['ids'])
            ->get()
            ->filter(fn ($p) => ($p->Status_Permit ?? null) === 'APPROVED')
            ->values();

        if ($permits->isEmpty()) {
            return ResponseHelper::errorWithCode(
                'Tidak ada permit aktif di antara yang dipilih. Kartu hanya bisa dicetak untuk permit yang masih berlaku.',
                'VISITOR_PERMIT_CARD_NOT_ACTIVE',
                422,
            );
        }

        try {
            $daftar = $permits
                ->map(fn ($p) => $this->isiKartuPermit($p, (int) $p->Id_Transaksi_Permit))
                ->all();

            // Lihat catatan penyubsetan font di balasKartuPdf(). Pada cetakan
            // massal selisihnya makin terasa: 865 KB -> 25 KB untuk empat kartu.
            $pdf = Pdf::loadView('exports.visitor-permit-kartu-batch', ['daftar' => $daftar])
                ->setOption('isFontSubsettingEnabled', true)
                ->setPaper('a4', 'portrait');

            return $pdf->stream('kartu-permit-'.count($daftar).'-kartu.pdf');
        } catch (\Throwable $e) {
            return VisitorApiFailure::respond($e, 'permit_kartu_pdf_batch', 'VISITOR_PERMIT_CARD_BATCH_FAILED', 'Kartu permit gagal dibuat.');
        }
    }

    /** @return array<string, string> */
    private function isiKartuPermit(object $permit, int $idTransaksiPermit): array
    {
        $kategori = mb_strtoupper((string) ($permit->Nama_Kategori ?: 'PERMIT AKSES'));
        $kategoriLower = mb_strtolower($kategori);

        $aksen = match (true) {
            str_contains($kategoriLower, 'merah') => '#dc2626',
            str_contains($kategoriLower, 'hijau') => '#059669',
            str_contains($kategoriLower, 'coklat'), str_contains($kategoriLower, 'cokelat') => '#d97706',
            default => '#2563eb',
        };

        $bgAksen = match (true) {
            str_contains($kategoriLower, 'merah') => '#fef2f2',
            str_contains($kategoriLower, 'hijau') => '#ecfdf5',
            str_contains($kategoriLower, 'coklat'), str_contains($kategoriLower, 'cokelat') => '#fffbeb',
            default => '#eff6ff',
        };

        $zonaBadge = match (true) {
            str_contains($kategoriLower, 'merah') => 'ZONA MERAH · AREA OPERASIONAL & PABRIK',
            str_contains($kategoriLower, 'hijau') => 'ZONA HIJAU · AREA KANTOR & PARKIR UMUM',
            str_contains($kategoriLower, 'coklat'), str_contains($kategoriLower, 'cokelat') => 'ZONA COKLAT · TAMU & MITRA B2B',
            default => 'PERMIT AKSES KENDARAAN RESMI',
        };

        $tanggal = function ($nilai): string {
            if (blank($nilai)) {
                return '-';
            }

            try {
                return Carbon::parse($nilai)->translatedFormat('d M Y');
            } catch (\Throwable) {
                return (string) $nilai;
            }
        };

        $kendaraan = array_filter([
            $permit->Nama_Vehicle_Type ?? null,
            $permit->Nama_Model_Manual ?? ($permit->Nama_Model_Kendaraan ?? null),
            $permit->Warna_Manual ?? ($permit->Nama_Warna ?? null),
            $permit->Tahun_Kendaraan ?? null,
        ]);

        $zonaInitial = match (true) {
            str_contains($kategoriLower, 'merah') => 'M',
            str_contains($kategoriLower, 'hijau') => 'H',
            str_contains($kategoriLower, 'coklat'), str_contains($kategoriLower, 'cokelat') => 'C',
            default => 'A',
        };

        return [
            'judul_pass' => 'OFFICIAL VEHICLE ACCESS PASS',
            'sub_perusahaan' => 'Dept. SAFETY, HEALTH & ENVIRONMENT (SHE)',
            'kategori' => $kategori,
            'zona_initial' => $zonaInitial,
            'zona_badge' => $zonaBadge,
            'aksen' => $aksen,
            'bg_aksen' => $bgAksen,
            'plat' => (string) ($permit->No_Plat_Kendaraan ?: '-'),
            'tipe_kendaraan' => (string) ($permit->Nama_Vehicle_Type ?: 'Kendaraan'),
            // Pemisah ASCII: font inti PDF tidak menjamin glif di luar WinAnsi,
            // dan kartu yang hurufnya jadi kotak tidak ada gunanya.
            'kendaraan' => $kendaraan ? implode(' - ', $kendaraan) : '-',
            'pemegang' => (string) ($permit->Nama_Pemohon ?: '-'),
            'nik' => (string) ($permit->NIK ?: '-'),
            'departemen' => (string) ($permit->Departemen ?: '-'),
            'jabatan' => (string) ($permit->Jabatan ?: '-'),
            'sampai' => $tanggal($permit->Tanggal_Kadaluarsa ?? null),
            'sejak' => $tanggal($permit->Tanggal_Berlaku ?? null),
            'no_transaksi' => (string) ($permit->No_Transaksi ?: '-'),
            'dicetak' => $tanggal(now()),
            'seri' => 'HCIS-'.preg_replace('/[^A-Za-z0-9]/', '', (string) $permit->No_Transaksi),
            // QR keaslian: isinya tautan BERTANDA TANGAN ke halaman verifikasi.
            // Kartu karangan tidak bisa dibuatkan QR yang lolos halaman itu.
            'qr' => $this->kartuQr->dataUri($idTransaksiPermit),
        ];
    }

    public function getMyPermits(Request $request)
    {
        try {
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());
            $permits = $this->permitMilikKaryawan((string) $kodeKaryawan)
                ->orderByDesc('h.Created_At')
                ->get();

            $permits->transform(function ($permit) {
                $permit->inspeksi = $this->jadwalInspeksi->ringkasan($permit->No_Transaksi);

                return $permit;
            });

            return ResponseHelper::success($permits);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'permit_my_list', 'VISITOR_PERMIT_MY_LIST_FAILED', 'Daftar permit Anda gagal dimuat.');
        }
    }

    public function getStats(Request $request)
    {
        try {
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());
            $base = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
                ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                ->where('h.Kode_Karyawan_Pemohon', $kodeKaryawan)
                ->where('h.Flag_Aktif', 'Y')
                /*
                 * POPULASI YANG SAMA dengan daftar di bawahnya.
                 *
                 * Kedua tab layar ini — Riwayat dan Pass Digital — dimuat dari
                 * /transaksipermit-my, yang memakai permitMilikKaryawan() dan
                 * MEMBUANG izin turunan kunjungan tamu. Izin itu terbit ATAS
                 * NAMA PIC karena seseorang harus bertanggung jawab atas
                 * tamunya, bukan karena PIC-nya yang mengajukan.
                 *
                 * Tanpa penyaring ini kartunya menghitung permit yang tak
                 * pernah muncul di daftar mana pun: "Menunggu Review 1" di atas
                 * daftar yang tidak memuat satu pun baris menunggu, tanpa cara
                 * bagi pemohon untuk menemukan permit yang dimaksud. Persis itu
                 * yang terbaca pada 25 Sep 2026 — permit coklat turunan
                 * kunjungan tamu yang masih menunggu approval permitnya.
                 */
                ->whereNull('h.No_Transaksi_Tamu');

            // Dihitung dari EKSPRESI STATUS yang sama dengan daftarnya, bukan
            // dari Flag_Approval mentah.
            //
            // Pencabutan tidak menyentuh Flag_Approval, sehingga menghitung
            // `Flag_Approval='Y'` membuat permit yang sudah dicabut tetap
            // masuk kartu "Permit Aktif" — angka dan daftarnya berselisih di
            // layar yang sama. Hal serupa terjadi pada `Flag_Approval IS NULL`
            // yang ikut menghitung pengajuan yang approval-nya sudah
            // kedaluwarsa sebagai "menunggu review".
            // statusCaseSql(), BUKAN permitStatusSql(): kartu dan daftar berada
            // di layar yang sama, jadi keduanya wajib memakai kosakata yang
            // sama. permitStatusSql() punya cabang VALIDITY_REQUIRED yang tidak
            // dikenal layar pemohon — memakainya di sini membuat permit yang
            // daftarnya bertuliskan "Aktif" tidak terhitung di kartu "Permit
            // Aktif", dan tidak ada error yang menyalakannya.
            /*
             * SATU perjalanan, bukan empat.
             *
             * Bentuk sebelumnya menembakkan satu COUNT per kartu — empat kueri
             * yang memindai populasi yang persis sama, berbeda hanya pada nilai
             * status yang dibandingkan. Pada basis data jarak jauh, biayanya
             * bukan pemindaiannya melainkan PERJALANANNYA: latensi terukur ±65
             * ms per kueri, jadi tiga kueri tambahan adalah ~200 ms yang
             * dihabiskan menunggu, bukan menghitung.
             *
             * COUNT(DISTINCT CASE ...) menjaga arti yang sama persis dengan
             * `->distinct()->count('h.No_Transaksi')` sebelumnya: baris detail
             * yang berlipat tidak menggandakan hitungannya.
             *
             * Setiap kartu melompat ke tab tertentu: "Permit Aktif" ke Pass
             * Digital, sisanya ke Riwayat. Keduanya kini dimuat dari sumber yang
             * sama (/transaksipermit-my), jadi keempat angkanya dihitung atas
             * populasi itu juga — lihat penyaring No_Transaksi_Tamu di $base.
             */
            /*
             * Statusnya dihitung di TURUNAN, agregatnya di luar.
             *
             * SQL Server menolak agregat di atas ekspresi yang memuat sub-kueri
             * ("Cannot perform an aggregate function on an expression containing
             * an aggregate or a subquery"), dan statusCaseSql() memuat dua
             * EXISTS untuk membaca alur approval. Membungkusnya langsung dengan
             * COUNT(...) gagal di basis data, bukan di PHP — jadi ia hanya
             * ketahuan ketika endpointnya benar-benar dipanggil.
             */
            $angka = DB::query()
                ->fromSub(
                    (clone $base)->selectRaw(
                        'h.No_Transaksi, '.$this->statusCaseSql().' AS status_permit'
                    ),
                    'x'
                )
                ->selectRaw(
                    "COUNT(DISTINCT CASE WHEN x.status_permit = 'APPROVED' THEN x.No_Transaksi END) AS permit_aktif,
                     COUNT(DISTINCT CASE WHEN x.status_permit = 'PENDING'  THEN x.No_Transaksi END) AS menunggu_approve,
                     COUNT(DISTINCT CASE WHEN x.status_permit = 'REJECTED' THEN x.No_Transaksi END) AS ditolak,
                     COUNT(DISTINCT CASE WHEN x.status_permit = 'REVOKED'  THEN x.No_Transaksi END) AS dicabut"
                )
                ->first();

            return ResponseHelper::success([
                'permit_aktif' => (int) ($angka->permit_aktif ?? 0),
                'menunggu_approve' => (int) ($angka->menunggu_approve ?? 0),
                'ditolak' => (int) ($angka->ditolak ?? 0),
                'dicabut' => (int) ($angka->dicabut ?? 0),
                // Dari sumber yang sama dengan penjaga di store(). Menghitung
                // ulang di sini akan membuat layar dan penjaganya berselisih.
                'kuota_terpakai' => $this->visitorPermitQuota->terpakai((string) $kodeKaryawan),
                'kuota_maks' => $this->visitorPermitQuota->batas(),
                /*
                 * Dua batas lain yang juga bisa menolak pengajuan. Dulu keduanya
                 * hanya diketahui SESUDAH orangnya mengisi tiga langkah formulir
                 * dan menekan kirim — tiga unggahan dan satu slot inspeksi
                 * terbuang untuk mendapat jawaban yang sudah bisa dipastikan
                 * sejak layarnya dibuka. Formulir memakai angka ini untuk
                 * memblokir di depan, dengan sebab yang sama persis seperti yang
                 * akan diucapkan penjaganya.
                 */
                'pending_terpakai' => $this->visitorPermitQuota->pendingTerpakai((string) $kodeKaryawan),
                'pending_maks' => $this->visitorPermitQuota->batasPending(),
                'akses_terpakai' => $this->visitorPermitQuota->aksesInspeksiHariIni((string) $kodeKaryawan),
                'akses_maks' => $this->visitorPermitQuota->batasAksesInspeksiHarian(),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'permit_stats', 'VISITOR_PERMIT_STATS_FAILED', 'Statistik permit gagal dimuat.');
        }
    }

    public function getMasterData()
    {
        try {
            return ResponseHelper::success(app(VisitorPermitMasterService::class)->untukPengajuan());
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'permit_master_data', 'VISITOR_PERMIT_MASTER_DATA_FAILED', 'Master data permit gagal dimuat.');
        }
    }

    public function uploadDokumen(Request $request)
    {
        $request->validate([
            'no_transaksi' => 'required|string',
            'file' => 'required|file|max:5120',
            'jenis_dokumen' => 'required|string|max:50',
        ]);

        $path = null;
        try {
            $file = $request->file('file');
            $noTransaksi = $request->input('no_transaksi');
            $fileName = $this->buildDocumentFileName($file, $noTransaksi, $request->input('jenis_dokumen'));
            $path = $this->storeUploadedFile($file, "visitor/permits/{$noTransaksi}/{$fileName}");

            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->insert([
                'No_Transaksi' => $noTransaksi,
                'Jenis_Dokumen' => $request->input('jenis_dokumen'),
                'Path_File_URL' => $path,
            ]);

            return ResponseHelper::success(['path' => $path], 'Upload berhasil.');
        } catch (\Throwable $e) {
            if ($path !== null) {
                $this->cleanupUploadedDocuments([['path' => $path]]);
            }

            return VisitorApiFailure::respond($e, 'permit_document_upload', 'VISITOR_PERMIT_DOCUMENT_UPLOAD_FAILED', 'Dokumen permit gagal diunggah.');
        }
    }

    /**
     * Permit yang benar-benar MILIK karyawan ini — bahan Pass Digital.
     *
     * Permit COKLAT turunan kunjungan diajukan ATAS NAMA PIC karena seseorang
     * harus bertanggung jawab atas tamunya, bukan karena PIC-nya yang mau lewat
     * gerbang. Tanpa penyaring ini ia ikut jadi KARTU: pass gerbang atas nama
     * karyawan untuk kendaraan yang bukan miliknya, yang bisa ia tunjukkan di
     * palang. Di development hari ini ada 8 permit seperti itu pada dua orang,
     * tiga di antaranya sudah APPROVED — tiga kartu hidup yang salah pemilik.
     *
     * Ruang lingkupnya SENGAJA sempit: hanya Pass Digital dan pembatalan. Daftar
     * Riwayat tetap memuatnya — bertanda — karena di sanalah PIC menelusuri izin
     * yang terbit atas namanya. Membuang dari kartu adalah soal apa yang boleh
     * ia BAWA; membuang dari riwayat menghapus jejak yang ia butuhkan.
     *
     * Penyaringnya KEPEMILIKAN (`No_Transaksi_Tamu`), bukan kategori permitnya.
     * Kategori COKLAT juga boleh dipakai permit karyawan biasa, dan
     * `Flag_Muncul_Pengajuan` memang hanya mengatur apa yang tampil di formulir
     * pengajuan — memakainya sebagai penentu kepemilikan membuat izin karyawan
     * ikut hilang dari kartunya sendiri begitu ada yang menggeser flag itu.
     *
     * Aturan yang sama sudah lebih dulu berlaku pada kuota
     * (VisitorPermitQuotaService::terpakai). Ia dulu satu-satunya tempat yang
     * memilikinya, dan itulah kenapa jatahnya benar sementara kartunya tidak.
     */
    protected function permitMilikKaryawan(string $kodeKaryawan)
    {
        return $this->basePermitQuery()
            ->where('h.Kode_Karyawan_Pemohon', $kodeKaryawan)
            ->whereNull('h.No_Transaksi_Tamu');
    }

    protected function basePermitQuery()
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('Karyawan as k', 'h.Kode_Karyawan_Pemohon', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_Divisi_Sub_Divisi')
            // Lewat ID_Level_Jabatan; tabel 'Jabatan' tidak ada. Dibutuhkan detail
            // permit karena Bukti Transaksi mencetak jabatan pemohon.
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as jb', 'k.ID_Level_Jabatan', '=', 'jb.ID_Level_Jabatan')
            ->leftJoin(
                'N_EMI_VISITOR_Master_Kategori_Permit as mk',
                'd.Id_Master_Kategori_Permit',
                '=',
                'mk.Id_Kategori_Permit',
            )
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Model as vm', 'd.Id_Master_Model', '=', 'vm.Id_Vehicle_Model')
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Type as vt', 'vm.Id_Master_Tipe', '=', 'vt.Id_Vehicle_Type')
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Color as vc', 'd.Id_Master_Warna', '=', 'vc.Id_Master_Color')
            ->leftJoin('N_EMI_VISITOR_Master_Durasi_Permit as md', 'd.Id_Master_Durasi', '=', 'md.Id_Durasi_Permit')
            // Akses efektif. Pencabutan oleh verifikator menulis ke tabel INI
            // dan tidak menyentuh Flag_Approval, jadi tanpa join ini permit yang
            // sudah dicabut tetap terbaca 'APPROVED' oleh pemohonnya — kartu
            // digitalnya pun masih tampil sah. Satu baris per No_Transaksi,
            // jadi join ini tidak menggandakan baris.
            ->leftJoin('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->select(
                'h.Id_Transaksi_Permit as Id',
                'h.Id_Transaksi_Permit',
                'h.No_Transaksi',
                'h.Kode_Karyawan_Pemohon',
                'h.Flag_Approval',
                // Asal-usul pengajuan. Tanpa ketiganya layar tidak punya cara
                // membedakan izin milik karyawan dari izin tamu yang kebetulan
                // terdaftar atas namanya — dan keduanya tampil identik di
                // riwayat, persis kebingungan yang hendak dihindari.
                'h.No_Transaksi_Tamu',
                'h.Sumber_Pengajuan',
                'h.Referensi_Mitra',
                'd.Nama_Tamu',
                'd.Instansi_Tamu',
                'd.No_Identitas_Tamu',
                'd.Jenis_Identitas_Tamu',
                DB::raw($this->statusCaseSql().' as Status_Permit'),
                'h.Created_At as Tanggal_Pengajuan',
                'h.Approved_At',
                'h.Approved_By',
                'k.Nama as Nama_Pemohon',
                // Nomor induk karyawan, bukan NIK KTP. Salinan kueri ini berdiri
                // sendiri dari VisitorPermitQueryService::basePermitQuery(); kolom
                // yang ditambahkan di sana TIDAK ikut ke sini dengan sendirinya.
                'k.No_Induk_Karyawan as NIK',
                'vd.nama_divisi as Departemen',
                'jb.nama_jabatan as Jabatan',
                'd.Id_Permit_Detail',
                'd.Tahun_Kendaraan',
                'd.Tanggal_Mulai_Berlaku as Tanggal_Berlaku',
                'd.Tanggal_Mulai_Berlaku',
                'd.Tanggal_Kadaluarsa',
                'd.Snapshot_Kategori_Permit',
                'd.Snapshot_Nama_Durasi',
                'd.Keterangan_Pengajuan',
                'mk.Nama_Kategori',
                'mk.Deskripsi_Akses as Deskripsi_Kategori',
                'mk.Kode_Warna_UI as Kode_Warna',
                'vt.Id_Vehicle_Type',
                'vt.Nama_Tipe_Kendaraan as Nama_Vehicle_Type',
                'vm.Nama_Model_Kendaraan as Nama_Model_Manual',
                'vc.Nama_Warna as Warna_Manual',
                DB::raw(
                    "LTRIM(RTRIM(CONCAT(ISNULL(d.Plat_Nomor_Prefix, ''), ' ', ISNULL(d.Plat_Nomor_Angka, ''), ' ', ISNULL(d.Plat_Nomor_Suffix, '')))) as No_Plat_Kendaraan",
                ),
                DB::raw('DATEDIFF(day, GETDATE(), d.Tanggal_Kadaluarsa) as Sisa_Hari'),
                'd.Keterangan_Pengajuan as Keterangan',
                // Sebab pencabutan ikut dikirim ke pemohon. Wajib diisi
                // verifikator saat mencabut, jadi tidak akan pernah kosong
                // untuk baris yang benar-benar dicabut.
                'pe.Status_Efektif',
                'pe.Alasan_Status as Alasan_Pencabutan',
                'pe.Dibatalkan_Oleh_Nama as Dicabut_Oleh',
                'pe.Dibatalkan_At as Dicabut_Pada',
                DB::raw('NULL as Alasan_Tolak'),
            );
    }

    /**
     * Status permit dari sudut pandang PEMOHON.
     *
     * 'REVOKED' harus diperiksa SEBELUM 'APPROVED': pencabutan tidak mengubah
     * Flag_Approval sama sekali, sehingga permit yang sudah dicabut tetap
     * memenuhi syarat cabang APPROVED. Kalau urutannya terbalik, pemohon
     * membaca "Aktif" untuk akses yang sudah tidak berlaku — dan baru tahu di
     * gerbang.
     *
     * Deteksi "dicabut" dipinjam dari VisitorPermitQueryService supaya ada SATU
     * definisi: dua salinan aturan yang sama pasti berselisih suatu hari, dan
     * selisihnya berupa layar yang saling bertentangan, bukan galat.
     */
    /**
     * Kategori permit yang boleh muncul di form pengajuan karyawan.
     *
     * NULL dibaca sebagai boleh tampil. Baris yang lahir dari jalur lain -
     * impor, penyisipan manual, kategori yang dibuat sebelum penanda ini ada -
     * tidak boleh hilang diam-diam dari form hanya karena kolomnya terlewat
     * diisi; pemohon akan menyimpulkan permitnya tidak bisa diajukan dan tidak
     * ada galat yang menjelaskan sebabnya.
     *
     * Dipakai DUA pemanggil: daftar pilihan di layar dan penjaga saat menyimpan.
     * Selama keduanya menyusun penyaringnya sendiri, dropdown bisa menyembunyikan
     * sesuatu yang tetap diterima server.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    protected function terapkanKategoriPengajuan($query)
    {
        return KategoriPermitPengajuan::saring($query);
    }

    protected function statusCaseSql(string $h = 'h', string $d = 'd'): string
    {
        $hariIni = $this->permitQuery->tanggalHariIniSql();
        $sekarang = $this->permitQuery->waktuSekarangSql();

        return 'CASE WHEN '.$this->permitQuery->dicabutSql($h)." THEN 'REVOKED'"
            ." WHEN ISNULL({$h}.Flag_Approval, '') <> 'T' AND ".$this->permitQuery->pemohonResignSql($h)." THEN 'EXPIRED'"
            ." WHEN {$h}.Flag_Approval = 'Y' AND {$d}.Waktu_Berakhir IS NOT NULL AND {$d}.Waktu_Berakhir < {$sekarang} THEN 'EXPIRED'"
            ." WHEN {$h}.Flag_Approval = 'Y' AND {$d}.Waktu_Berakhir IS NULL AND {$d}.Tanggal_Kadaluarsa < {$hariIni} THEN 'EXPIRED'"
            ." WHEN {$h}.Flag_Approval = 'Y' THEN 'APPROVED'"
            ." WHEN {$h}.Flag_Approval = 'T' THEN 'REJECTED'"
            ." WHEN NOT EXISTS (SELECT 1 FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi = {$h}.No_Transaksi) THEN 'NO_FLOW'"
            ." WHEN EXISTS (SELECT 1 FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi = {$h}.No_Transaksi AND ar.Flag_Approval IS NULL AND ar.Expired_At IS NOT NULL AND ar.Expired_At < {$sekarang}) THEN 'EXPIRED'"
            ." ELSE 'PENDING' END";
    }

    protected function uploadDocumentFiles(Request $request, string $noTransaksi): array
    {
        $documents = [
            'foto_kendaraan' => ['jenis' => 'FOTO_KENDARAAN'],
            'foto_stnk' => ['jenis' => 'STNK'],
            'foto_sim' => ['jenis' => 'SIM'],
        ];
        $uploaded = [];

        try {
            foreach ($documents as $field => $info) {
                if (! $request->hasFile($field)) {
                    continue;
                }
                $file = $request->file($field);
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $folder = "visitor/permits/{$noTransaksi}";
                $fileName = $this->buildDocumentFileName($file, $noTransaksi, $info['jenis']);
                $path = "{$folder}/{$fileName}";
                $this->storeUploadedFile($file, $path);
                $uploaded[] = ['jenis' => $info['jenis'], 'path' => $path];
            }
        } catch (\Throwable $e) {
            $this->cleanupUploadedDocuments($uploaded);
            throw $e;
        }

        return $uploaded;
    }

    protected function persistDocumentMetadata(array $documents, string $noTransaksi, int $detailId): void
    {
        $rows = array_map(static fn (array $document): array => [
            'Id_Detail_Permit' => $detailId,
            'No_Transaksi' => $noTransaksi,
            'Jenis_Dokumen' => $document['jenis'],
            'Path_File_URL' => $document['path'],
        ], $documents);

        if ($rows !== []) {
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->insert($rows);
        }
    }

    protected function cleanupUploadedDocuments(array $documents): void
    {
        foreach ($documents as $document) {
            try {
                Storage::disk('gcs')->delete($document['path']);
            } catch (\Throwable $cleanupError) {
                Log::error('Gagal cleanup dokumen permit setelah kegagalan transaksi.', [
                    'path' => $document['path'],
                    'error' => $cleanupError->getMessage(),
                ]);
            }
        }
    }

    protected function buildDocumentFileName($file, string $noTransaksi, string $jenisDokumen): string
    {
        $extension = strtolower($file->extension() ?: 'bin'); // dari ISI berkas (MIME), bukan nama kiriman klien
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $safeJenis = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $jenisDokumen));

        return "{$noTransaksi}_{$safeJenis}_".Str::uuid()->toString().".{$extension}";
    }

    protected function storeUploadedFile($file, string $path): string
    {
        $temporaryPath = $file->getRealPath() ?: $file->getPathname();

        if (! is_string($temporaryPath) || trim($temporaryPath) === '' || ! is_file($temporaryPath)) {
            throw new \Exception('File upload tidak memiliki temporary path yang valid.');
        }

        $stream = fopen($temporaryPath, 'rb');
        if ($stream === false) {
            throw new \Exception('Gagal membaca file upload.');
        }

        try {
            if (! Storage::disk('gcs')->put($path, $stream)) {
                throw new \Exception('Gagal menyimpan file dokumen permit ke GCS.');
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    /**
     * Plat dari permintaan, ditafsirkan oleh SATU penafsir milik modul.
     *
     * Sebelumnya metode ini punya aturannya sendiri — memecah atas spasi tanpa
     * memeriksa bentuk — sehingga "bg1234mt" yang dibaca pintu mitra sebagai
     * [BG|1234|MT] di sini menjadi prefix delapan karakter, dan kendaraan yang
     * sama tercatat dua rupa. Angka mentah tidak pernah dibesarkan hurufnya,
     * padahal suffix iya, jadi bahkan satu jalur pun tidak konsisten.
     *
     * @return array{prefix: ?string, angka: ?string, suffix: ?string, kanonis: bool}
     */
    protected function resolvePlatNomor(Request $request): array
    {
        return $this->platNomor->normalkan(
            $request->input('no_plat'),
            $request->input('plat_prefix'),
            $request->input('plat_angka'),
            $request->input('plat_suffix'),
        );
    }

    /**
     * Isinya pindah ke PermitNomorTransaksi ketika endpoint permit mitra
     * membutuhkan pembangkit yang sama. Metode ini dipertahankan sebagai
     * pembungkus tipis supaya pemanggil di kelas ini tidak perlu berubah;
     * yang penting hanya satu: tidak boleh ada penomoran kedua yang berdiri
     * sendiri, karena nomornya akan bertabrakan dengan pengajuan HCIS.
     */
    private function generateNoTransaksi(string $prefix): string
    {
        return app(\App\Services\VISITOR\Permit\VehiclePermit\PermitNomorTransaksi::class)->buat($prefix);
    }
}
