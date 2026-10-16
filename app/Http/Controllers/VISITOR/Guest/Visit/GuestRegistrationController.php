<?php

namespace App\Http\Controllers\VISITOR\Guest\Visit;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\VISITOR\Guest\Visit\SimpanTamuRequest;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Guest\Visit\AturanFormTamu;
use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use App\Services\VISITOR\Guest\Visit\FotoKendaraanTamuService;
use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\RegistrasiTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Idempotency\VisitorIdempotencyService;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class GuestRegistrationController extends Controller
{
    private const T_HEADER = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_DETAIL = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected VisitorEfektifService $visitorEfektif,
        protected VisitorIdempotencyService $visitorIdempotency,
        protected VisitorKehadiranQueryService $visitorKehadiran,
        protected DaftarAnggotaRombongan $daftarAnggota,
        protected RegistrasiTamuService $registrasiTamu,
        protected PermitCoklatTamuService $permitCoklatTamu,
        protected FotoKendaraanTamuService $fotoKendaraan,
        protected MatriksTamuService $matriks,
    ) {}

    /** Id_Users pengguna (= N_HRIS_Role_User.UserId_Web). */
    protected function userIdWeb(Request $request): ?int
    {
        $id = $request->user()?->Id_Users ?? null;

        return $id !== null ? (int) $id : null;
    }

    public function index(Request $request)
    {
        $tipe = TipeTamu::dariPermintaan($request);

        return Inertia::render(
            'VISITOR/Guest/Visit/GuestRegistrationPage',
            array_merge(
                [
                    'user' => $request->user(),
                    'serverDate' => Carbon::today()->toDateString(),
                    'serverNow' => Carbon::now()->format('Y-m-d\TH:i'),
                    'tipeTamu' => $tipe,
                    'apiPrefix' => self::prefiksApi($tipe),
                    // Unit B2B yang boleh diajukan pengguna ini (matriks departemen).
                    'b2bUnits' => $tipe === TipeTamu::B2B
                        ? $this->matriks->unitBolehAjukan($this->userIdWeb($request))
                        : [],
                    'aturanForm' => [
                        'rentang_maks_hari' => AturanFormTamu::RENTANG_MAKS_HARI,
                        'foto_maks_kb' => AturanFormTamu::FOTO_MAKS_KB,
                        'foto_mime' => AturanFormTamu::FOTO_MIME,
                    ],
                ],
                $this->visitorApprovalPolicy->pageProps(TipeTamu::modulApproval($tipe), $request->user()),
            ),
        );
    }

    /** Prefiks URL API halaman ini; B2B memakai salinan rute yang dijaga kuncinya sendiri. */
    public static function prefiksApi(string $tipe): string
    {
        return TipeTamu::sah($tipe) === TipeTamu::B2B ? 'transaksi-tamu-b2b' : 'transaksitamu';
    }

    public function getList(Request $request)
    {
        try {
            $page = (int) $request->input('page', 1);
            $limit = (int) $request->input('limit', 15);
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $dateFrom = $request->input('date_from');
            $dateTo = $request->input('date_to');

            $query = $this->baseTamuQuery();
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());

            if (! $kodeKaryawan) {
                return ResponseHelper::errorWithCode(
                    'Identitas karyawan tidak ditemukan.',
                    'VISITOR_REQUESTER_NOT_FOUND',
                    403,
                );
            }

            $query->where('h.Kode_Karyawan_PIC', $kodeKaryawan);
            TipeTamu::saring($query, TipeTamu::dariPermintaan($request));

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('d.Nama_Lengkap_Tamu', 'LIKE', "%{$search}%")
                        ->orWhere('d.Instansi_Atau_Asal', 'LIKE', "%{$search}%")
                        ->orWhere('h.No_Transaksi', 'LIKE', "%{$search}%");
                });
            }

            if ($status === 'BATAL') {
                // Satu chip untuk semua kunjungan yang berakhir tanpa terjadi.
                $query->whereRaw($this->visitorKehadiran->siklusKunjunganSql().' IN ('.StatusKunjunganTamu::sqlDaftar([
                    StatusKunjunganTamu::REJECTED,
                    StatusKunjunganTamu::WITHDRAWN,
                    StatusKunjunganTamu::REVOKED,
                    StatusKunjunganTamu::CANCELLED,
                ]).')');
            } elseif ($status && in_array($status, StatusKunjunganTamu::TAMPIL, true)) {
                $siklus = $this->visitorKehadiran->siklusKunjunganSql();

                if ($status === StatusKunjunganTamu::PENDING_APPROVAL) {
                    // Menunggu = kunjungannya ATAU permit COKLAT-nya belum diputus.
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
            $data = $query
                ->orderByDesc('h.Created_At')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(fn ($row) => $this->appendApprovalStatus($row));

            return ResponseHelper::successWithPaginationV2($data, $page, $limit, $total);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_page_data', 'VISITOR_GUEST_PAGE_DATA_FAILED', 'Data tamu gagal dimuat.');
        }
    }

    public function store(SimpanTamuRequest $request)
    {
        $idempotencyKey = trim((string) $request->header('Idempotency-Key'));
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            return ResponseHelper::errorWithCode('Header Idempotency-Key wajib diisi dan maksimal 100 karakter.', 'VISITOR_IDEMPOTENCY_KEY_REQUIRED', 422);
        }

        $tipe = TipeTamu::dariPermintaan($request);
        $config = $this->visitorApprovalPolicy->config(TipeTamu::modulApproval($tipe));
        if (! (bool) $config['enabled']) {
            return ResponseHelper::error('Fitur registrasi tamu sedang dinonaktifkan.', 403);
        }

        // Unit pengaju B2B dikunci matriks departemen, bukan dipilih bebas:
        // satu unit → otomatis; lebih dari satu → wajib salah satunya.
        $unitB2b = null;
        if ($tipe === TipeTamu::B2B) {
            $unitBoleh = $this->matriks->unitBolehAjukan($this->userIdWeb($request));

            if ($unitBoleh === []) {
                return ResponseHelper::errorWithCode(
                    'Anda belum terdaftar sebagai departemen pengaju tamu B2B (Procurement/Logistic).',
                    'VISITOR_B2B_UNIT_TIDAK_BERHAK',
                    403,
                );
            }

            $diminta = strtoupper(trim((string) $request->input('b2b_unit')));
            $unitB2b = count($unitBoleh) === 1 ? $unitBoleh[0] : $diminta;

            if (! in_array($unitB2b, $unitBoleh, true)) {
                return ResponseHelper::errorWithCode(
                    'Pilih unit pengaju tamu B2B yang sesuai departemen Anda.',
                    'VISITOR_B2B_UNIT_TIDAK_SESUAI',
                    422,
                );
            }
        }

        $platPrefix = strtoupper(trim((string) $request->input('plat_prefix')));
        if ($platPrefix !== '' && ! DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')
            ->where('Kode_Plat', $platPrefix)
            ->where('Flag_Aktif', 'Y')
            ->exists()) {
            return ResponseHelper::errorWithCode(
                'Kode plat tidak terdaftar atau sudah nonaktif.',
                'VISITOR_GUEST_PLATE_MASTER_INACTIVE',
                422,
            );
        }

        $vehicleTypeName = trim((string) $request->input('jenis_kendaraan'));
        if (! AturanFormTamu::tanpaKendaraan($vehicleTypeName)) {
            $activeVehicleType = DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
                ->whereRaw('LOWER(LTRIM(RTRIM(Nama_Tipe_Kendaraan))) = LOWER(LTRIM(RTRIM(?)))', [$vehicleTypeName])
                ->where('Flag_Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNull('Akses')->orWhereIn('Akses', ['ALL', 'TAMU']);
                })
                ->exists();

            if (! $activeVehicleType) {
                return ResponseHelper::errorWithCode(
                    'Tipe kendaraan tidak terdaftar atau sudah nonaktif.',
                    'VISITOR_GUEST_VEHICLE_TYPE_INACTIVE',
                    422,
                );
            }
        }

        $purposeName = trim((string) $request->input('keperluan'));
        $purposeMaster = DB::table('N_EMI_VISITOR_Master_Purpose')
            ->whereRaw('LOWER(LTRIM(RTRIM(Nama_Keperluan))) = LOWER(LTRIM(RTRIM(?)))', [$purposeName])
            ->first();
        if ($purposeMaster && $purposeMaster->Flag_Aktif !== 'Y') {
            return ResponseHelper::errorWithCode(
                'Keperluan tidak tersedia karena sudah nonaktif.',
                'VISITOR_GUEST_PURPOSE_INACTIVE',
                422,
            );
        }

        try {
            $daftarAnggota = $this->daftarAnggota->susunApaAdanya(
                (array) $request->input('daftar_anggota', []),
            );
        } catch (\InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'daftar_anggota' => $e->getMessage(),
            ]);
        }

        $kodeKaryawan = null;
        try {
            $user = $request->user();
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($user);

            if (! $kodeKaryawan) {
                throw new \Exception('Kode karyawan pemohon tidak ditemukan.');
            }

            $existing = $this->registrasiTamu->cariIdempoten($kodeKaryawan, $idempotencyKey);
            if ($existing) {
                return ResponseHelper::success([
                    'no_transaksi' => $existing->No_Transaksi,
                    'id' => $existing->Id_Transaksi_Tamu,
                    'idempotent_replay' => true,
                ], 'Pengajuan tamu sudah pernah diterima.');
            }

            // Bentuk baris, pembuatan alur approval, dan penerbitan proyeksi
            // milik RegistrasiTamuService: satu penulis untuk layar HCIS dan
            // API mitra, supaya aturannya tidak bercabang antar pintu.
            $hasil = $this->registrasiTamu->simpan([
                'kode_karyawan_pic' => $kodeKaryawan,
                'idempotency_key' => $idempotencyKey,
                // Dari rute, bukan dari isian: lihat SimpanTamuRequest.
                'tipe_tamu' => $tipe,
                'b2b_unit' => $unitB2b,
                'nama_tamu' => $request->input('nama_tamu'),
                'instansi_asal' => $request->input('instansi_asal'),
                'no_hp' => $request->input('no_hp'),
                'keperluan' => $request->input('keperluan'),
                'pic_tujuan' => $request->input('pic_tujuan'),
                'keterangan' => $request->input('keterangan'),
                'tanggal_kunjungan' => $request->input('tanggal_kunjungan'),
                'waktu_mulai_kunjungan' => $request->input('waktu_mulai_kunjungan'),
                'waktu_selesai_kunjungan' => $request->input('waktu_selesai_kunjungan'),
                'jenis_kendaraan' => $request->input('jenis_kendaraan'),
                // Nilai yang SUDAH dinormalkan, bukan masukan mentah. Yang
                // diperiksa ke tabel master di atas adalah versi huruf besarnya;
                // menyimpan yang mentah membuat baris lolos pemeriksaan lalu
                // tersimpan dalam bentuk yang tak pernah cocok lagi dengannya.
                'plat_prefix' => $platPrefix !== '' ? $platPrefix : null,
                'plat_angka' => $request->input('plat_angka'),
                'plat_suffix' => $request->input('plat_suffix'),
                'no_identitas' => $request->input('no_identitas'),
                'jenis_identitas' => $request->input('jenis_identitas'),
                'daftar_anggota' => (array) $request->input('daftar_anggota', []),
                'manifes_memuat_tamu_utama' => false,
            ]);

            $noTransaksi = $hasil['no_transaksi'];
            $headerId = $hasil['id'];
            $needsApproval = $hasil['needs_approval'];

            // Foto sesudah commit dan di luar transaksi; gagal unggah tidak
            // membatalkan pengajuan, tetapi dilaporkan ke pengguna.
            $peringatan = [];
            if ($tipe === TipeTamu::B2B && $request->hasFile('foto_kendaraan')) {
                try {
                    $this->fotoKendaraan->simpan($noTransaksi, $request->file('foto_kendaraan'));
                } catch (\Throwable $e) {
                    Log::error('Foto kendaraan tamu B2B gagal diunggah', [
                        'no_transaksi' => $noTransaksi,
                        'error' => $e->getMessage(),
                    ]);
                    $peringatan[] = 'Foto kendaraan gagal diunggah; pengajuan tetap tersimpan tanpa foto.';
                }
            }

            // Sesudah commit, bukan di dalam transaksi: menulis notifikasi sambil
            // memegang transaksi pengajuan akan menahan lock N_HRIS_Notification,
            // tabel yang di-poll top-bar setiap pengguna.
            if ($needsApproval) {
                try {
                    PublishVisitorNotificationsJob::dispatch([[
                        'kind' => 'submission',
                        'domain' => $tipe === TipeTamu::B2B
                            ? VisitorNotificationService::DOMAIN_TAMU_B2B
                            : VisitorNotificationService::DOMAIN_TAMU,
                        'no_transaksi' => $noTransaksi,
                        'context' => [
                            'nama_pemohon' => $user?->karyawan?->Nama,
                            'nama_tamu' => $request->input('nama_tamu'),
                            'instansi' => $request->input('instansi_asal'),
                            'tanggal_kunjungan' => Carbon::parse($request->input('waktu_mulai_kunjungan', $request->input('tanggal_kunjungan')))->toDateString(),
                        ],
                    ]]);
                } catch (\Throwable $e) {
                    // Pengajuan sudah ter-commit; kegagalan notifikasi tidak boleh membatalkannya.
                    Log::error('Gagal mengirim notifikasi pengajuan tamu', [
                        'no_transaksi' => $noTransaksi,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Permit COKLAT-nya adalah pengajuan TERSENDIRI dengan antrean dan
            // verifikatornya sendiri. Tanpa baris ini ia masuk antrean permit
            // tanpa seorang pun diberitahu — dan baru ketahuan saat ada yang
            // membuka mejanya.
            $this->kabariAntreanPermitTamu($hasil['no_transaksi_permit'] ?? null, $request);

            $message = (bool) $config['needs_approval']
                ? 'Pengajuan tamu berhasil dikirim dan menunggu approval.'
                : 'Data tamu berhasil disimpan.';

            return ResponseHelper::success([
                'no_transaksi' => $noTransaksi,
                'id' => $headerId,
                'peringatan' => $peringatan,
            ], $message, 201);
        } catch (\InvalidArgumentException|\DomainException $e) {
            // Masukan yang ditolak aturan bisnis, bukan kegagalan sistem.
            // Sebelumnya keduanya jatuh ke cabang Throwable di bawah dan
            // terbalas 500 dengan pesan umum, sehingga pemakainya tidak pernah
            // tahu bagian mana yang salah — padahal sebabnya sudah diketahui
            // persis. Cabang ini disamakan dengan pintu mitra B2B.
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return ResponseHelper::errorWithCode(
                $e->getMessage(),
                'VISITOR_GUEST_SUBMISSION_INVALID',
                422,
            );
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($kodeKaryawan && $this->visitorIdempotency->isUniqueKeyConflict(
                $e,
                'UX_VISITOR_Tamu_Requester_Idempotency',
            )) {
                $existing = $this->registrasiTamu->cariIdempoten($kodeKaryawan, $idempotencyKey);

                if ($existing) {
                    return ResponseHelper::success([
                        'no_transaksi' => $existing->No_Transaksi,
                        'id' => $existing->Id_Transaksi_Tamu,
                        'idempotent_replay' => true,
                    ], 'Pengajuan tamu sudah pernah diterima.');
                }
            }

            Log::error('Gagal menyimpan pengajuan tamu', [
                'requester' => $kodeKaryawan,
                'error' => $e->getMessage(),
            ]);

            return ResponseHelper::errorWithCode(
                'Pengajuan tamu gagal disimpan.',
                'VISITOR_GUEST_SUBMISSION_FAILED',
                500,
            );
        }
    }

    public function cancel(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $header = DB::table(self::T_HEADER)
                ->where('Id_Transaksi_Tamu', $id)
                ->where('Kode_Karyawan_PIC', $this->visitorApprovalPolicy->requesterKode($request->user()))
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first();

            if (! $header || ! $this->milikTipeRute($request, $header->No_Transaksi)) {
                throw new \DomainException('Data tamu tidak ditemukan.');
            }

            if (! $this->needsApproval($header) || $header->Status_Kunjungan !== StatusKunjunganTamu::PENDING_APPROVAL) {
                throw new \DomainException('Pengajuan ini tidak dapat dibatalkan.');
            }

            $hasApproved = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $header->No_Transaksi)
                ->where('Flag_Approval', 'Y')
                ->exists();

            if ($hasApproved) {
                throw new \DomainException('Pengajuan tidak dapat dibatalkan karena sudah diproses approver.');
            }

            DB::table(self::T_HEADER)
                ->where('Id_Transaksi_Tamu', $id)
                ->update([
                    'Status_Kunjungan' => StatusKunjunganTamu::WITHDRAWN,
                    'Flag_Approval' => 'T',
                ]);

            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $header->No_Transaksi)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'T',
                    'Catatan' => 'Dibatalkan oleh pengaju.',
                    'Tanggal_Approval' => now(),
                    'update_at' => now(),
                ]);

            DB::commit();

            return ResponseHelper::success(null, 'Pengajuan tamu berhasil dibatalkan.');
        } catch (\DomainException $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_GUEST_CANCEL_CONFLICT', 409);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            return VisitorApiFailure::respond($e, 'guest_cancel', 'VISITOR_GUEST_CANCEL_FAILED', 'Pengajuan tamu gagal dibatalkan.');
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            // Tautan notifikasi membawa token, bukan Id. Nilai yang tak terbaca
            // harus berhenti di sini: mengalirkannya ke kolom int melempar
            // QueryException dan berbalas 500, bukan 404.
            $idTransaksi = VisitorDetailToken::bacaId(VisitorNotificationService::DOMAIN_TAMU, $id);

            if ($idTransaksi === null) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $query = $this->baseTamuQuery()
                ->where('h.Id_Transaksi_Tamu', $idTransaksi)
                ->where('h.Kode_Karyawan_PIC', $this->visitorApprovalPolicy->requesterKode($request->user()));
            $data = TipeTamu::saring($query, TipeTamu::dariPermintaan($request))->first();

            if (! $data) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $data = $this->appendApprovalStatus($data);
            $data->Foto_Kendaraan_Url = $this->fotoKendaraan->url($data->Path_Foto_Kendaraan ?? null);

            if ($this->needsApproval($data)) {
                $data->Approvals = $this->getApprovals($data->No_Transaksi);
            }

            return ResponseHelper::success($data);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_detail', 'VISITOR_GUEST_DETAIL_FAILED', 'Detail tamu gagal dimuat.');
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $request->validate([
                'tanggal_kunjungan' => 'nullable|date',
                'waktu_mulai_kunjungan' => 'nullable|date',
                'waktu_selesai_kunjungan' => 'nullable|date|after:waktu_mulai_kunjungan',
                'daftar_anggota' => 'sometimes|array',
                'daftar_anggota.*.nama' => 'required|string|max:150',
                'daftar_anggota.*.jenis_identitas' => 'nullable|string|max:20',
                'daftar_anggota.*.no_identitas' => 'nullable|string|max:100',
            ]);

            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());

            if (! $kodeKaryawan) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $header = DB::table(self::T_HEADER)
                ->where('Id_Transaksi_Tamu', $id)
                ->where('Kode_Karyawan_PIC', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $header) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $headerUpdate = array_filter(
                [
                    'Tanggal_Kunjungan' => $request->input('tanggal_kunjungan'),
                    'Status_Kunjungan' => $request->input('status_kunjungan'),
                ],
                fn ($value) => $value !== null,
            );

            $detailUpdate = array_filter(
                [
                    'Waktu_Mulai_Kunjungan' => $request->input('waktu_mulai_kunjungan'),
                    'Waktu_Selesai_Kunjungan' => $request->input('waktu_selesai_kunjungan'),
                ],
                fn ($value) => $value !== null,
            );

            if ($request->has('daftar_anggota')) {
                try {
                    $daftarBaru = $this->daftarAnggota->susunApaAdanya(
                        (array) $request->input('daftar_anggota', []),
                    );
                } catch (\InvalidArgumentException $e) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'daftar_anggota' => $e->getMessage(),
                    ]);
                }

                $detailUpdate['Daftar_Anggota_Json'] = $this->daftarAnggota->keJson($daftarBaru);
                $detailUpdate['Jumlah_Tamu'] = $this->daftarAnggota->jumlahOrang($daftarBaru, false);
            }

            if (! empty($headerUpdate)) {
                DB::table(self::T_HEADER)->where('Id_Transaksi_Tamu', $id)->update($headerUpdate);
            }

            if (! empty($detailUpdate)) {
                DB::table(self::T_DETAIL)->where('No_Transaksi', $header->No_Transaksi)->update($detailUpdate);
            }

            // Tanpa sinkron ulang, aplikasi palang terus membaca jendela dan
            // tanggal yang lama tanpa satu pun galat. Cacat ini sudah ada sejak
            // sebelum permit coklat — dulu akibatnya "hanya" data kunjungan yang
            // basi di gerbang; sekarang nilai basi itu adalah masa berlaku akses
            // fisik yang salah.
            if (! empty($headerUpdate) || ! empty($detailUpdate)) {
                $this->visitorEfektif->syncTamuEfektif($header->No_Transaksi);
                $this->permitCoklatTamu->sinkron($header->No_Transaksi);
            }

            return ResponseHelper::success(null, 'Data tamu berhasil diperbarui.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Dilempar ulang, bukan ditelan catch di bawahnya: tanpa ini jendela
            // terbalik berbalas 500 "gagal diperbarui" alih-alih 422 dengan
            // pesan field-nya, dan pemakai tidak pernah tahu apa yang salah.
            throw $e;
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_update', 'VISITOR_GUEST_UPDATE_FAILED', 'Data tamu gagal diperbarui.');
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $header = DB::table(self::T_HEADER)->where('Id_Transaksi_Tamu', $id)->first();

            DB::table(self::T_HEADER)
                ->where('Id_Transaksi_Tamu', $id)
                ->update(['Flag_Aktif' => 'N']);

            if ($header) {
                try {
                    $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user()) ?? $header->Kode_Karyawan_PIC;
                    $this->visitorEfektif->revokeTamuEfektif($header->No_Transaksi, 'Dibatalkan sendiri oleh PIC', $kodeKaryawan);
                    // Permit coklat punya transaksinya sendiri: yang dicabut
                    // bukan hanya barisnya di gerbang, tetapi juga pengajuannya
                    // kalau ia masih menunggu verifikator. No-op kalau permitnya
                    // memang tidak pernah diajukan.
                    $this->permitCoklatTamu->batalkan($header->No_Transaksi, 'Dibatalkan sendiri oleh PIC', $kodeKaryawan);
                } catch (\Exception $e) {
                    Log::error('Gagal revoke Efektif tamu', [
                        'no_transaksi' => $header->No_Transaksi,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return ResponseHelper::success(null, 'Data tamu berhasil dihapus.');
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_delete', 'VISITOR_GUEST_DELETE_FAILED', 'Data tamu gagal dihapus.');
        }
    }

    public function checkIn(Request $request, string $id)
    {
        try {
            $header = DB::table(self::T_HEADER)->where('Id_Transaksi_Tamu', $id)->where('Flag_Aktif', 'Y')->first();

            if (! $header) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $config = $this->visitorApprovalPolicy->config('tamu');
            if (
                $this->needsApproval($header) &&
                (bool) $config['check_in_requires_approval'] &&
                ! $this->visitorApprovalPolicy->isApproved($header->No_Transaksi)
            ) {
                return ResponseHelper::error('Tamu belum dapat check-in karena pengajuan belum disetujui.', 422);
            }

            // Check-in kedua akan menimpa Waktu_Aktual_Masuk: tidak ada galat,
            // tidak ada jejak, dan jam kedatangan yang sebenarnya lenyap.
            $detail = DB::table(self::T_DETAIL)->where('No_Transaksi', $header->No_Transaksi)->first();
            if ($detail && $detail->Waktu_Aktual_Masuk !== null) {
                return ResponseHelper::errorWithCode(
                    'Tamu ini sudah tercatat masuk pada '.$detail->Waktu_Aktual_Masuk.'.',
                    'VISITOR_TAMU_SUDAH_MASUK',
                    422,
                );
            }

            // Status_Kunjungan TIDAK disentuh: ia status keputusan, dan
            // CHECKED_IN adalah status tampil yang diturunkan dari jam ini.
            DB::table(self::T_DETAIL)
                ->where('No_Transaksi', $header->No_Transaksi)
                ->update(['Waktu_Aktual_Masuk' => now()]);

            return ResponseHelper::success(null, 'Check-in berhasil.');
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_check_in', 'VISITOR_GUEST_CHECK_IN_FAILED', 'Check-in tamu gagal diproses.');
        }
    }

    public function checkOut(Request $request, string $id)
    {
        try {
            $header = DB::table(self::T_HEADER)->where('Id_Transaksi_Tamu', $id)->where('Flag_Aktif', 'Y')->first();

            if (! $header) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            // Jam keluar tanpa jam masuk adalah riwayat yang mustahil, dan
            // check-out kedua menimpa jam keluar yang sudah benar.
            $detail = DB::table(self::T_DETAIL)->where('No_Transaksi', $header->No_Transaksi)->first();
            if (! $detail || $detail->Waktu_Aktual_Masuk === null) {
                return ResponseHelper::errorWithCode(
                    'Tamu ini belum tercatat masuk, jadi belum dapat dicatat keluar.',
                    'VISITOR_TAMU_BELUM_MASUK',
                    422,
                );
            }
            if ($detail->Waktu_Aktual_Keluar !== null) {
                return ResponseHelper::errorWithCode(
                    'Tamu ini sudah tercatat keluar pada '.$detail->Waktu_Aktual_Keluar.'.',
                    'VISITOR_TAMU_SUDAH_KELUAR',
                    422,
                );
            }

            // Status_Kunjungan TIDAK disentuh — lihat checkIn().
            DB::table(self::T_DETAIL)
                ->where('No_Transaksi', $header->No_Transaksi)
                ->update(['Waktu_Aktual_Keluar' => now()]);

            // Jalur manual ini adalah pintu kedua yang setara dengan palang.
            // Sapuan terjadwal juga menutup permitnya, tetapi baru dalam 5 menit;
            // di sini kita sudah tahu tamunya keluar, jadi tidak ada alasan
            // membiarkan izinnya hidup selama itu.
            $this->visitorEfektif->akhiriPermitCoklat($header->No_Transaksi, 'Tamu tercatat keluar');

            return ResponseHelper::success(null, 'Check-out berhasil.');
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_check_out', 'VISITOR_GUEST_CHECK_OUT_FAILED', 'Check-out tamu gagal diproses.');
        }
    }

    public function getStats(Request $request)
    {
        try {
            $base = DB::table(self::T_HEADER)->where('Flag_Aktif', 'Y');
            $kodeKaryawan = $this->visitorApprovalPolicy->requesterKode($request->user());

            if (! $kodeKaryawan) {
                return ResponseHelper::errorWithCode(
                    'Identitas karyawan tidak ditemukan.',
                    'VISITOR_REQUESTER_NOT_FOUND',
                    403,
                );
            }

            $tipe = TipeTamu::dariPermintaan($request);
            $base->where('Kode_Karyawan_PIC', $kodeKaryawan)
                ->whereExists(function ($query) use ($tipe) {
                    $query->select(DB::raw(1))
                        ->from(self::T_DETAIL.' as d')
                        ->whereColumn('d.No_Transaksi', self::T_HEADER.'.No_Transaksi');
                    TipeTamu::saring($query, $tipe);
                });

            $menungguPersetujuan = (clone $base)
                ->where(function ($query) {
                    $query->where('Status_Kunjungan', StatusKunjunganTamu::PENDING_APPROVAL)
                        ->orWhere(function ($subQuery) {
                            $subQuery->where('Needs_Approval', 'Y')->whereNull('Flag_Approval');
                        })
                        ->orWhereExists(function ($subQuery) {
                            $subQuery->select(DB::raw(1))
                                ->from('N_EMI_VISITOR_Transaksi_Permit as p')
                                ->whereColumn('p.No_Transaksi_Tamu', self::T_HEADER.'.No_Transaksi')
                                ->whereNull('p.Flag_Approval')
                                ->where('p.Flag_Aktif', 'Y');
                        });
                })
                ->whereNotExists(function ($query) {
                    $query
                        ->select(DB::raw(1))
                        ->from('HRIS_Approval_Request as ar')
                        ->whereColumn('ar.No_Transaksi', self::T_HEADER.'.No_Transaksi')
                        ->whereNull('ar.Flag_Approval')
                        ->whereNotNull('ar.Expired_At')
                        ->whereRaw('ar.Expired_At < GETDATE()');
                })
                ->count();

            // Kehadiran DITURUNKAN dari waktu masuk/keluar, bukan dari
            // Status_Kunjungan: kolom status hanya ditulis tombol manual HCIS,
            // sementara aplikasi palang menulis waktunya ke tabel efektif tanpa
            // menyentuh status. Menghitung dari status membuat tamu yang benar-
            // benar sudah keluar lewat gerbang tidak pernah terhitung selesai.
            $kehadiranBase = fn () => TipeTamu::saring($this->visitorKehadiran->join(
                DB::table(self::T_HEADER.' as h')
                    ->join(self::T_DETAIL.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                    ->where('h.Flag_Aktif', 'Y')
                    ->where('h.Kode_Karyawan_PIC', $kodeKaryawan)
            ), $tipe);

            $selesaiKeluar = $kehadiranBase()
                ->whereRaw($this->visitorKehadiran->adaKeluarSql())
                ->count();

            $sudahDisetujui = $kehadiranBase()
                ->where('h.Flag_Approval', 'Y')
                ->whereRaw($this->visitorKehadiran->belumKeluarSql())
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('N_EMI_VISITOR_Transaksi_Permit as p')
                        ->whereColumn('p.No_Transaksi_Tamu', 'h.No_Transaksi')
                        ->whereNull('p.Flag_Approval')
                        ->where('p.Flag_Aktif', 'Y');
                })
                ->count();

            return ResponseHelper::success([
                'menunggu_persetujuan' => $menungguPersetujuan,
                'sudah_disetujui' => $sudahDisetujui,
                'selesai_keluar' => $selesaiKeluar,
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_stats', 'VISITOR_GUEST_STATS_FAILED', 'Statistik tamu gagal dimuat.');
        }
    }

    public function getMasterData()
    {
        try {
            $platCodes = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Kode_Plat')
                ->pluck('Kode_Plat');

            $vehicleTypes = DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
                ->where('Flag_Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNull('Akses')->orWhereIn('Akses', ['ALL', 'TAMU']);
                })
                ->orderBy('Id_Vehicle_Type')
                ->get();

            $keperluan = DB::table('N_EMI_VISITOR_Master_Purpose')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Master_Purpose')
                ->get();

            // Daftar 'waktu_estimasi' dihapus bersama pemensiunan kolomnya. Form
            // kini meminta jam mulai dan jam selesai yang sebenarnya, karena
            // jendela itulah masa berlaku permit coklat.
            $jenisIdentitas = ['KTP', 'SIM', 'Paspor', 'Lain-lain'];

            return ResponseHelper::success([
                'plat_codes' => $platCodes,
                'vehicle_types' => $vehicleTypes,
                'keperluan' => $keperluan,
                'jenis_identitas' => $jenisIdentitas,
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'guest_master_data', 'VISITOR_GUEST_MASTER_DATA_FAILED', 'Master data tamu gagal dimuat.');
        }
    }

    protected function baseTamuQuery()
    {
        return DB::table(self::T_HEADER.' as h')
            ->leftJoin(self::T_DETAIL.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'h.No_Transaksi', '=', 'e.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->select(
                'h.Id_Transaksi_Tamu as Id',
                'h.Id_Transaksi_Tamu',
                'h.No_Transaksi',
                'h.Kode_Karyawan_PIC',
                'h.Tanggal_Kunjungan',
                'h.Status_Kunjungan as Status_Pengajuan',
                // Status tampil satu definisi (VisitorKehadiranQueryService).
                DB::raw($this->visitorKehadiran->siklusKunjunganSql().' as Status_Kunjungan'),
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
                // Sub-kueri, bukan JOIN. Relasinya satu-ke-paling-banyak-satu,
                // tetapi kueri ini sudah menggabung empat tabel; JOIN kelima yang
                // meleset akan menggandakan baris riwayat, dan gejalanya muncul
                // sebagai "kunjungan saya terhitung dua kali" — bukan sebagai galat.
                // Permit COKLAT kini punya transaksinya sendiri dan melewati
                // approval, jadi keadaannya dibaca dari transaksi itu — bukan
                // dari proyeksi gerbang. Proyeksi hanya tahu permit yang SUDAH
                // hidup, sehingga membacanya saja membuat permit yang masih
                // menunggu verifikator tampak seperti tidak pernah diajukan.
                DB::raw(app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)->statusPermitKunjunganSql('h.No_Transaksi', 'PROSES').' as Status_Permit_Coklat'),
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

    /**
     * Beritahu antrean verifikator permit tentang permit COKLAT tamu.
     *
     * Dipanggil SESUDAH transaksi pengajuan selesai. Kegagalannya dicatat, tidak
     * dilempar: pengajuannya sudah ter-commit, dan membatalkannya karena
     * notifikasi gagal jauh lebih merugikan daripada notifikasi yang hilang.
     */
    protected function kabariAntreanPermitTamu(?string $noPermit, Request $request): void
    {
        if (! $noPermit) {
            return;
        }

        try {
            PublishVisitorNotificationsJob::dispatch([[
                'kind' => 'submission',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $noPermit,
                'context' => [
                    'nama_pemohon' => $request->user()?->karyawan?->Nama,
                    'kategori' => 'COKLAT',
                    'plat_nomor' => trim((string) $request->input('no_plat_kendaraan')),
                ],
            ]]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi permit COKLAT tamu', [
                'no_transaksi' => $noPermit,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Baris tamu ini bertipe sama dengan tipe yang ditetapkan rute. */
    protected function milikTipeRute(Request $request, string $noTransaksi): bool
    {
        $query = DB::table(self::T_DETAIL.' as d')->where('d.No_Transaksi', $noTransaksi);

        return TipeTamu::saring($query, TipeTamu::dariPermintaan($request))->exists();
    }

    protected function appendApprovalStatus(object $row): object
    {
        // Status_Permit_Coklat adalah status PERMIT (kosakatanya sendiri),
        // bukan status kunjungan.
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
}
