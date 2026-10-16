<?php

namespace App\Http\Controllers\ESS\Organization\Team;

use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuDelegationPolicyService;
use App\Services\SYSTEM\Governance\Authorization\Scope\ScopeApplicationEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class karyawanTeamController extends Controller
{
    public function __construct(
        private readonly MenuDelegationPolicyService $delegationPolicyService,
    ) {}

    public function index()
    {
        $namaUser = Auth::user()->karyawan->Nama;

        // dd($PageAccess);

        return inertia('ESS/Organization/Team/addKaryawanTeam', [
            'namaKaryawan' => $namaUser,
        ]);
    }

    public function getDataAll()
    {
        $baseKaryawanQuery = DB::table('Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as b', 'Karyawan.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('KPI_Users as c', 'c.Id_Users', '=', 'Karyawan.UserID_Web')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as d',
                'd.ID_Level_Jabatan',
                '=',
                'Karyawan.ID_Level_Jabatan',
            )
            ->select(
                'Karyawan.Nama',
                'Karyawan.Kode_Karyawan',
                'Karyawan.Status_Kepegawaian',
                'Karyawan.Status_Kontrak',
                'b.nama_sub_divisi as nama_divisi',
                'Karyawan.HP',
                'c.Username',
                'Karyawan.UserID_Web',
                'd.nama_level as nama_jabatan',
            );

        $allKaryawan = (clone $baseKaryawanQuery)->get();
        $assignableKaryawan = ScopeApplicationEngine::apply(clone $baseKaryawanQuery, 'Karyawan')->get();
        $permanentKaryawan = (clone $baseKaryawanQuery)->where('Karyawan.Aktif', 'Y')->get();

        $teamData = DB::table('HRIS_Karyawan_Team as team')
            ->join('Karyawan as k', 'team.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->select('team.Kode_Karyawan_Team as LeaderTeam', 'team.Kode_Karyawan')
            ->whereColumn('team.Kode_Karyawan', '<>', 'team.Kode_Karyawan_Team')
            ->where('k.Aktif', 'Y')
            ->get()
            ->groupBy('LeaderTeam');

        $karyawanTeam = $teamData
            ->map(function ($items, $leader) {
                return [
                    'LeaderTeam' => $leader,
                    'Team' => $items->pluck('Kode_Karyawan')->values()->all(),
                ];
            })
            ->values();

        $Flow = DB::table('HRIS_Approval_Flow')
            ->select('Kode_Karyawan', 'Kode_Karyawan_Requester as Requester', 'order_flow')
            ->whereNull('Status')
            ->orderBy('order_flow')
            ->get()
            ->groupBy('Requester');

        $mengapprove = DB::table('HRIS_Approval_Flow')
            ->select('Kode_Karyawan', 'Kode_Karyawan_Requester as Requester', 'order_flow')
            ->whereNull('Status')
            ->get()
            ->groupBy('Kode_Karyawan');

        // Flow: siapa yang punya approver
        $approvalFlow = $Flow
            ->map(function ($items, $requester) {
                return [
                    'Requester' => $requester,
                    'Approver' => $items
                        ->map(function ($item) {
                            return [
                                'Kode_Karyawan' => $item->Kode_Karyawan,
                                'order_flow' => $item->order_flow,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        // MengapproveFlow: siapa yang meng-approve siapa
        $mengapproveFlow = $mengapprove
            ->map(function ($items, $approver) {
                return [
                    'Approver' => $approver,
                    'Requester' => $items
                        ->map(function ($item) {
                            return [
                                'Kode_Karyawan_Requester' => $item->Requester,
                                'order_flow' => $item->order_flow,
                            ];
                        })
                        ->values()
                        ->all(),
                ];
            })
            ->values();

        // dd($mengapproveFlow);

        $page = DB::table('HRIS_Page_Access')
            ->select('Jenis_page', 'UserID_Web')
            ->where('Flag_Aktif', 'Y')
            ->get()
            ->groupBy('UserID_Web');
        $PageAccess = $page
            ->map(function ($items, $UserID_Web) {
                return [
                    'UserID_Web' => $UserID_Web,
                    'Jenis_page' => $items->pluck('Jenis_page')->values()->all(),
                ];
            })
            ->values();

        // Scope menu access list to ESS group only, respecting delegation policies.
        $essGrup = DB::table('N_HRIS_Master_Grup_Fitur')
            ->where('Flag_Aktif', 'Y')
            ->whereRaw('LOWER(Kode_Grup_Fitur) = ?', ['ess'])
            ->select('Id_Grup_Fitur')
            ->first();

        $essGrupId = $essGrup ? (int) $essGrup->Id_Grup_Fitur : null;

        if ($essGrupId) {
            $menuAccessListQuery = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
                ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
                ->where('gfm.Id_Grup_Fitur', $essGrupId)
                ->where('gfm.Flag_Aktif', 'Y')
                ->select(
                    'm.Id_Menu',
                    'm.Jenis_page',
                    'm.Nama_Menu',
                    'm.Icon_Menu',
                    'm.Header_Code',
                    'm.Header_Label',
                    'm.Header_Icon',
                    'm.Is_Global_Menu',
                    'm.Sort_Header',
                    'm.Sort_Menu',
                )
                ->whereNotNull('m.Jenis_page')
                // Exclude global menu from assignable access matrix.
                ->whereRaw("UPPER(LTRIM(RTRIM(COALESCE(m.Is_Global_Menu, '')))) <> 'Y'")
                // Exclude administrator section from assignable access matrix.
                ->whereRaw("LOWER(COALESCE(m.Header_Label, '')) NOT LIKE ?", ['%administrator%'])
                ->whereRaw("LOWER(COALESCE(m.Nama_Menu, '')) NOT LIKE ?", ['%administrator%'])
                ->whereRaw("LOWER(COALESCE(m.Jenis_page, '')) NOT LIKE ?", ['%administrator%']);

            if (SkemaTersedia::kolom('N_HRIS_Menus', 'Flag_Aktif')) {
                $menuAccessListQuery->where('m.Flag_Aktif', 'Y');
            } elseif (SkemaTersedia::kolom('N_HRIS_Menus', 'Flag_Active')) {
                $menuAccessListQuery->where('m.Flag_Active', 'Y');
            }

            $menuAccessList = $menuAccessListQuery
                ->orderByRaw('COALESCE(NULLIF(m.Sort_Header, 0), 999) ASC')
                ->orderByRaw('CASE WHEN m.Sort_Menu IS NULL THEN 1 ELSE 0 END ASC')
                ->orderBy('m.Sort_Menu')
                ->orderBy('m.Id_Menu')
                ->get();

            // Attach delegation policy info (can_delegate, policy_mode, lock_reason)
            $policies = $this->delegationPolicyService->policiesForGroup(Auth::user(), $essGrupId);
            $policiesByPage = $policies->keyBy('Jenis_page');

            $menuAccessList = $menuAccessList->map(function ($menu) use ($policiesByPage) {
                $policy = $policiesByPage->get($menu->Jenis_page);
                $menu->policy_mode = $policy->policy_mode ?? 'OPEN';
                $menu->can_delegate = $policy->can_delegate ?? true;
                $menu->lock_reason = $policy->lock_reason ?? null;

                return $menu;
            });
        } else {
            $menuAccessList = collect();
        }

        return response()->json([
            'allKaryawan' => $allKaryawan,
            'assignableKaryawan' => $assignableKaryawan,
            'permanentKaryawan' => $permanentKaryawan,
            'karyawanTeam' => $karyawanTeam,
            'mengapprove' => $mengapproveFlow,
            'ApprovalFlow' => $approvalFlow,
            'PageAccess' => $PageAccess,
            'menuAccessList' => $menuAccessList,
        ]);
    }

    public function getData(Request $request)
    {
        try {
            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'page' => ['nullable', 'integer', 'min:1'],
            ]);

            $search = strip_tags($validated['search'] ?? '');
            $perPage = 10;
            $page = $validated['page'] ?? 1;
            $query = DB::table('Karyawan')
                ->join('View_Divisi_Sub_Divisi as b', 'Karyawan.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as c',
                    'Karyawan.ID_Level_Jabatan',
                    '=',
                    'c.ID_Level_Jabatan',
                )
                ->selectRaw(
                    "
                    Karyawan.Nama as namaUser,
                    Karyawan.Kode_Karyawan,
                    b.nama_divisi,
                    c.nama_jabatan,
                    ISNULL(
                        (
                            select STRING_AGG(yk.Nama, ' | ')
                            from HRIS_Approval_Flow y
                            join Karyawan yk on y.Kode_Karyawan = yk.Kode_Karyawan
                            where Karyawan.Kode_Karyawan = y.Kode_Karyawan_Requester
                            and y.Status is null
                        ),
                        'TIDAK ADA'
                    ) as Approvers,
                    ISNULL(
                        (
                            select count(distinct z.Kode_Karyawan)
                            from HRIS_Karyawan_Team z
                            join Karyawan zk on z.Kode_Karyawan = zk.Kode_Karyawan
                            where Karyawan.Kode_Karyawan = z.Kode_Karyawan_Team
                            and z.Kode_Karyawan <> z.Kode_Karyawan_Team
                            and zk.Aktif = 'Y'
                            group by z.Kode_Karyawan_Team
                        ),
                        0
                    ) as Team,
                    ISNULL((Karyawan.UserID_Web), 0) as UserID_Web
                    ",
                );

            $query = ScopeApplicationEngine::apply($query, 'Karyawan');

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('Karyawan.Nama', 'like', '%'.$search.'%')
                        ->orWhere('b.nama_divisi', 'like', '%'.$search.'%')
                        ->orWhere('c.nama_jabatan', 'like', '%'.$search.'%');
                });
            }

            $final_page = $query->orderByRaw('Team DESC')->paginate($perPage, ['*'], 'page', (int) $page);

            // dd($result);

            return response()->json([
                'status' => 200,
                'data' => $final_page,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::channel('KaryawanTeamLog')->error('Input Tidak Valid'.$e->errors());

            return response()->json(
                [
                    'message' => 'Input tidak valid',
                    'errors' => $e->errors(),
                ],
                422,
            );
        } catch (\Exception $e) {
            Log::channel('KaryawanTeamLog')->error('Gagal Mengambil data Karyawan Team '.$e->getMessage());

            return response()->json(
                [
                    'message' => 'Terjadi kesalahan saat mengambil data',
                    // 'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    private function applyEmployeeScopeFilter($query)
    {
        return ScopeApplicationEngine::apply($query, 'Karyawan');
    }

    private function safeRollback(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }

    private function generateNoTransaksi()
    {
        // Delegasi ke strategi tunggal (applock + baca MAX sargable), konsisten dengan
        // AuditAdminTransaction agar tak ada duplikat silang pada tabel yang sama.
        // Semua pemanggil di controller ini berada di dalam DB::beginTransaction().
        return app(\App\Services\SYSTEM\Audit\Numbering\AdminLogNumberService::class)->generate();
    }

    public function simpanUser(Request $request)
    {
        // dd($request->all());
        try {
            DB::beginTransaction();
            $isNew = false;
            $dataAkun = [];

            $Kode_Karyawan = $request->Kode_Karyawan;
            $Karyawan = Karyawan::where('Kode_Karyawan', $Kode_Karyawan)->first();
            $HPKaryawan = $Karyawan->HP;
            $userId = Auth::user()->karyawan->Kode_Karyawan;

            if (
                empty($Karyawan->HP) ||
                $Karyawan->HP == '-' ||
                ! Str::startsWith($Karyawan->HP, '628') ||
                strlen($Karyawan->HP) < 11
            ) {
                Log::channel('CreateUserLog')->error(
                    "Tidak ada/salah NO HP untuk {$Karyawan->Nama}, silahkan ubah NO HP di HCIS desktop",
                );
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'message' => "Tidak ada/salah NO HP untuk {$Karyawan->Nama}, silahkan ubah NO HP di HCIS desktop!",
                    ],
                    500,
                );
            }

            $username = $request->Username;
            $divisi = $Karyawan->division->ID_Divisi;
            $level = $Karyawan->level->ID_Level;

            // Generate No_Transaksi
            $noTransaksi = $this->generateNoTransaksi();
            DB::table('HRIS_Transaksi_Log_Admin')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => Carbon::now(),
                'Jam' => Carbon::now()->format('H:i'),
                'UserID' => $userId,
            ]);

            if ($username && (! $Karyawan->UserID_Web || $Karyawan->UserID_Web == ' ' || $Karyawan->UserID_Web == '-')) {
                $cleanPasswordPart = $username;
                $randomNumber = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
                $saltFront = env('SALT_FRONT');
                $saltBack = env('SALT_BACK');
                $rawPassword = $this->generateRandomPassword(14);
                $passwordWithSalt = $saltFront.$rawPassword.$saltBack;
                $encryptedPassword = Hash::make($passwordWithSalt);

                $createUser = User::create([
                    'Username' => $username,
                    'Password' => $encryptedPassword,
                    'Email' => $Karyawan->Email,
                    'Role' => 'user',
                    'Flag_Active' => 'Y',
                    'Kode_Users' => $Kode_Karyawan,
                    'Address' => $Karyawan->Alamat,
                    'Division_Id' => $divisi,
                    'Level_Id' => $level,
                    'Nama' => $Karyawan->Nama,
                    'No_Hp' => $Karyawan->HP,
                ]);

                if ($createUser) {
                    $updateKaryawan = Karyawan::where('Kode_Karyawan', $Kode_Karyawan)->update([
                        'UserID_Web' => $createUser->Id_Users,
                    ]);
                    if ($updateKaryawan < 1) {
                        Log::channel('CreateUserLog')->error('Tidak ada UserID_Web');
                        $this->safeRollback();

                        return response()->json(
                            [
                                'status' => 500,
                                'message' => 'Tidak dapat mendapat id web karyawan!',
                            ],
                            500,
                        );
                    }

                    // dd($createUser);
                    // Log detail
                    DB::table('HRIS_Transaksi_Log_Admin_Detail')->insert([
                        'Kode_Perusahaan' => '001',
                        'No_Transaksi' => $noTransaksi,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'Jenis' => 'User',
                        'Deskripsi' => 'User created with username '.$rawPassword,
                    ]);

                    $isNew = true;

                    $dataAkun = [
                        'nama' => $Karyawan->Nama,
                        'divisi' => $Karyawan->division->nama_sub_divisi,
                        'username' => $username,
                        'password' => $rawPassword,
                        'HP' => $Karyawan->HP,
                    ];

                    // Kirim user
                    $pesan = [
                        'messaging_product' => 'whatsapp',
                        'to' => $HPKaryawan,
                        'type' => 'template',
                        'template' => [
                            'name' => 'hcis_daftar_baru_part_1',
                            'language' => [
                                'code' => 'id',
                                'policy' => 'deterministic',
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => $Karyawan->Nama,
                                        ],
                                        ['type' => 'text', 'text' => $username],
                                        // ["type" => "text", "text" => $rawPassword],
                                    ],
                                ],
                            ],
                        ],
                    ];
                    $waResult = WhatsappLogHelper::sendAndLog(
                        $Karyawan->Kode_Karyawan,
                        $Karyawan->Nama,
                        $Karyawan->HP,
                        $noTransaksi,
                        $pesan,
                        function ($response) use ($Karyawan) {
                            Log::channel('CreateUserLog')->info(
                                "WA sukses dikirim ke User {$Karyawan->Kode_Karyawan}",
                                [
                                    'requester' => $Karyawan->Kode_Karyawan,
                                    'response' => $response,
                                ],
                            );
                        },
                        function ($reason, $response) use ($Karyawan, $noTransaksi, $pesan) {
                            Log::channel('CreateUserLog')->warning(
                                "Gagal kirim WA ke User {$Karyawan->Kode_Karyawan}",
                                [
                                    'reason' => $reason,
                                    'response' => $response,
                                ],
                            );

                            DB::table('Whatsapp_Log')->insert([
                                'Kode_Karyawan_Destination' => $Karyawan->Kode_Karyawan,
                                'Nama' => $Karyawan->Nama,
                                'HP' => $Karyawan->HP,
                                'No_Transaksi' => $noTransaksi,
                                'Error_Message' => is_array($response)
                                    ? json_encode($response, JSON_UNESCAPED_UNICODE)
                                    : $reason ?? 'Unknown error',
                                'RawMessage' => print_r($pesan, true),
                                'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                                'Created_At' => now(),
                                'Is_Retried' => 0,
                            ]);
                        },
                    );

                    // Kirim Password
                    $pesanPassword = [
                        'messaging_product' => 'whatsapp',
                        'to' => $HPKaryawan,
                        'type' => 'template',
                        'template' => [
                            'name' => 'hcis_daftar_baru_part_2',
                            'language' => [
                                'code' => 'id',
                                'policy' => 'deterministic',
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => $rawPassword,
                                        ],
                                    ],
                                ],
                                [
                                    'type' => 'button',
                                    'sub_type' => 'url',
                                    'index' => '0',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => $rawPassword,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $waResult = WhatsappLogHelper::sendAndLog(
                        $Karyawan->Kode_Karyawan,
                        $Karyawan->Nama,
                        $Karyawan->HP,
                        $noTransaksi,
                        $pesanPassword,
                        function ($response) use ($Karyawan) {
                            Log::channel('CreateUserLog')->info(
                                "WA sukses dikirim ke User {$Karyawan->Kode_Karyawan}",
                                [
                                    'requester' => $Karyawan->Kode_Karyawan,
                                    'response' => $response,
                                ],
                            );
                        },
                        function ($reason, $response) use ($Karyawan, $noTransaksi, $pesanPassword) {
                            Log::channel('CreateUserLog')->warning(
                                "Gagal kirim WA ke User {$Karyawan->Kode_Karyawan}",
                                [
                                    'reason' => $reason,
                                    'response' => $response,
                                ],
                            );

                            DB::table('Whatsapp_Log')->insert([
                                'Kode_Karyawan_Destination' => $Karyawan->Kode_Karyawan,
                                'Nama' => $Karyawan->Nama,
                                'HP' => $Karyawan->HP,
                                'No_Transaksi' => $noTransaksi,
                                'Error_Message' => is_array($response)
                                    ? json_encode($response, JSON_UNESCAPED_UNICODE)
                                    : $reason ?? 'Unknown error',
                                'RawMessage' => print_r($pesanPassword, true),
                                'RawMessageJson' => json_encode(
                                    $pesanPassword,
                                    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
                                ),
                                'Created_At' => now(),
                                'Is_Retried' => 0,
                            ]);
                        },
                    );
                }
            }

            $UserID_Web = Karyawan::where('Kode_Karyawan', $Kode_Karyawan)->select('UserID_Web')->first()->UserID_Web;
            if (! $UserID_Web) {
                Log::channel('CreateUserLog')->error('Tidak ada UserID_Web');
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'message' => 'Tidak dapat mendapat id web karyawan!',
                    ],
                    500,
                );
            }

            // Normalisasi & dedup daftar page agar tidak menyimpan baris akses aktif ganda.
            $Pages = collect(is_array($request->Pages) ? $request->Pages : [])
                ->map(fn ($p) => trim((string) $p))
                ->filter()
                ->unique()
                ->values()
                ->all();

            // Validasi delegation policy: pastikan admin hanya meng-assign page yang dia berwenang.
            if (! empty($Pages)) {
                $essGrup = DB::table('N_HRIS_Master_Grup_Fitur')
                    ->where('Flag_Aktif', 'Y')
                    ->whereRaw('LOWER(Kode_Grup_Fitur) = ?', ['ess'])
                    ->first(['Id_Grup_Fitur']);

                if ($essGrup) {
                    $essGrupId = (int) $essGrup->Id_Grup_Fitur;
                    try {
                        $this->delegationPolicyService->assertPagesAllowed(
                            Auth::user(),
                            $essGrupId,
                            $Pages,
                        );
                    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                        Log::channel('CreateUserLog')->error(
                            'Delegasi page ditolak: '.$e->getMessage().' untuk '.$Kode_Karyawan,
                        );
                        $this->safeRollback();

                        return response()->json(
                            [
                                'status' => 403,
                                'message' => $e->getMessage(),
                            ],
                            403,
                        );
                    }
                }
            }

            $adaUser = DB::table('HRIS_Page_Access')
                ->where('Flag_Aktif', 'Y')
                ->where('UserID_Web', $UserID_Web)
                ->exists();
            if ($adaUser) {
                DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $UserID_Web)
                    ->update([
                        'Flag_Aktif' => 'T',
                        'Updated_At' => Carbon::now()->format('Y-m-d H:i:s'),
                    ]);
            }

            // Log page access changes
            $idDetail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan' => $Kode_Karyawan,
                'Jenis' => 'PageAccess',
                'Deskripsi' => 'HC-Absensi | Page access added',
            ]);
            // $idDetail = DB::getPdo()->lastInsertId();

            foreach ($Pages as $Page) {
                DB::table('HRIS_Page_Access')->insert([
                    'Kode_Perusahaan' => '001',
                    'ID_Level' => $level,
                    'ID_Divisi' => $divisi,
                    'Jenis_page' => $Page,
                    'UserID_Web' => $UserID_Web,
                    'Created_At' => Carbon::now()->format('Y-m-d H:i:s'),
                    'Updated_At' => Carbon::now()->format('Y-m-d H:i:s'),
                    'Flag_Aktif' => 'Y',
                ]);

                DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $noTransaksi,
                    'Field_Diubah' => 'Jenis_page.HRIS_Page_Access',
                    'Deskripsi' => 'HC-Absensi | Added page access',
                    'Nilai_Awal' => null,
                    'Nilai_Setelah' => $Page,
                    'Urut_Detail' => $idDetail,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil menyimpan User',
                'dataAkun' => $dataAkun,
                // 'isNewUser' => $isNew //Aktifkan untuk melihat popup detail akun yang di berikan | manual send user account
                'isNewUser' => false,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->safeRollback();
            Log::channel('CreateUserLog')->error('Input Tidak Valid'.$e->errors());

            return response()->json(['message' => 'Input tidak valid', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $this->safeRollback();
            Log::channel('CreateUserLog')->error('Gagal menyimpan data Karyawan '.$e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data'], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }

    public function simpanApproval(Request $request)
    {
        $batasHariPengajuan = config('services.hcis.batas_approve_izin');
        $batasApproveCuti = config('services.hcis.batas_approve_cuti');
        try {
            DB::beginTransaction();
            $requester = collect($request->Requester);
            $approver = $request->Approver;
            $userId = Auth::user()->karyawan->Kode_Karyawan;

            // Generate No_Transaksi
            $noTransaksi = $this->generateNoTransaksi();
            DB::table('HRIS_Transaksi_Log_Admin')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => Carbon::now(),
                'Jam' => Carbon::now()->format('H:i'),
                'UserID' => $userId,
            ]);

            $adaApprover = DB::table('HRIS_Approval_Flow')
                ->where('Kode_Karyawan_Requester', $requester['Kode_Karyawan'])
                ->whereNull('Status')
                ->exists();

            if ($adaApprover) {
                $txSource = (new \App\Services\SYSTEM\Application\Transactions\AllTransactionsReadService)
                    ->forRequester($requester['Kode_Karyawan']);

                $e = DB::query()
                    ->fromSub($txSource, 'tx')
                    ->where('Dashboard_Status', 'PENDING')
                    ->when((int) $batasHariPengajuan > 0, function ($q) use ($batasHariPengajuan) {
                        $q->whereRaw('DATEDIFF(DAY, Source_Created_At, GETDATE()) < ?', [(int) $batasHariPengajuan]);
                    })
                    ->select('No_Transaksi')
                    ->distinct()
                    ->get();

                if ($e->isNotEmpty()) {
                    $this->safeRollback();
                    Log::channel('KaryawanTeamLog')->error(
                        'Gagal mengupdate approver karyawan karena karyawan masih ada pengajuan yang belum di acc ',
                    );

                    DB::rollBack();

                    return response()->json(
                        [
                            'message' => 'Gagal mengupdate approver karyawan karena karyawan masih ada pengajuan yang belum di acc ',
                        ],
                        500,
                    );
                }

                DB::table('HRIS_Approval_Flow')
                    ->where('Kode_Karyawan_Requester', $requester['Kode_Karyawan'])
                    ->whereNull('Status')
                    ->update(['Status' => 'Y']);

                // Log new approver
                $idDetail2 = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan' => $requester['Kode_Karyawan'],
                    'Jenis' => 'Approval',
                    'Deskripsi' => 'HC-Absensi | Remove recent approver',
                ]);
            }

            // Log new approver
            $idDetail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan' => $requester['Kode_Karyawan'],
                'Jenis' => 'Approval',
                'Deskripsi' => 'HC-Absensi | New approver added',
            ]);

            /*
             * Tiga kueri per approver, bukan satu: selain pencarian Karyawan-nya,
             * ->division dan ->level adalah relasi yang dimuat MALAS, jadi
             * masing-masing menembak kuerinya sendiri saat diakses. Semuanya di
             * dalam transaksi penyimpanan approval, jadi kuncinya ditahan selama
             * itu.
             *
             * Sekarang ketiganya diambil sekali dengan eager loading. Kunci
             * petanya Kode_Karyawan -- kueri lamanya pun hanya menyaring kolom
             * itu, jadi asumsi keunikannya diwarisi, bukan ditambah.
             */
            $approverPerKode = Karyawan::with(['division', 'level'])
                ->whereIn('Kode_Karyawan', array_column($approver, 'Kode_Karyawan'))
                ->get()
                ->keyBy('Kode_Karyawan');

            foreach ($approver as $item) {
                $approverSelect = $approverPerKode[$item['Kode_Karyawan']] ?? null;
                $divisi = $approverSelect->division->ID_Divisi;
                $level = $approverSelect->level->ID_Level;
                DB::table('HRIS_Approval_Flow')->insert([
                    'Kode_Karyawan' => $item['Kode_Karyawan'],
                    'Division_Id' => $divisi,
                    'level_Id' => $level,
                    'order_flow' => $item['order_flow'],
                    'Kode_Karyawan_Requester' => $requester['Kode_Karyawan'],
                ]);

                if ($idDetail) {
                    DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                        'Kode_Perusahaan' => '001',
                        'No_Transaksi' => $noTransaksi,
                        'Field_Diubah' => 'Kode_Karyawan.HRIS_Approval_Flow',
                        'Deskripsi' => 'HC-Absensi | New approver',
                        'Nilai_Awal' => null,
                        'Nilai_Setelah' => $item['Kode_Karyawan'],
                        'Urut_Detail' => $idDetail,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil menyimpan approver',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->safeRollback();
            Log::channel('KaryawanTeamLog')->error('Input Tidak Valid'.$e->errors());

            return response()->json(['message' => 'Input tidak valid', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $this->safeRollback();
            Log::channel('KaryawanTeamLog')->error('Gagal Mengambil data Karyawan Team '.$e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan saat mengambil data'], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }

    private function generateRandomPassword($length = 14)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        $max = strlen($chars) - 1;

        $password .= $chars[mt_rand(26, 51)]; // Huruf Besar
        $password .= $chars[mt_rand(52, $max)]; // Angka

        for ($i = 0; $i < $length - 2; $i++) {
            $password .= $chars[mt_rand(0, $max)];
        }

        return str_shuffle($password);
    }

    public function simpanTeam(Request $request)
    {
        try {
            DB::beginTransaction();

            $Kode_Karyawan = $request->Kode_Karyawan;
            $newMembers = $request->newMembers ?? [];
            $removedMembers = $request->removedMembers ?? [];
            $userId = Auth::user()->karyawan->Kode_Karyawan;

            // Generate No_Transaksi
            $noTransaksi = $this->generateNoTransaksi();
            DB::table('HRIS_Transaksi_Log_Admin')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => Carbon::now(),
                'Jam' => Carbon::now()->format('H:i'),
                'UserID' => $userId,
            ]);

            // Hapus member
            if (! empty($removedMembers)) {
                $removeCodes = array_column($removedMembers, 'Kode_Karyawan');
                $deleted = DB::table('HRIS_Karyawan_Team')
                    ->where('Kode_Karyawan_Team', $Kode_Karyawan)
                    ->whereIn('Kode_Karyawan', $removeCodes)
                    ->delete();

                if ($deleted < count($removeCodes)) {
                    $notDeleted = array_filter($removedMembers, function ($emp) use ($Kode_Karyawan) {
                        return DB::table('HRIS_Karyawan_Team')
                            ->where('Kode_Karyawan_Team', $Kode_Karyawan)
                            ->where('Kode_Karyawan', $emp['Kode_Karyawan'])
                            ->exists();
                    });

                    foreach ($notDeleted as $emp) {
                        Log::channel('addTeam')->error(
                            "Terjadi error saat menghapus {$emp['Nama']} dari team {$Kode_Karyawan}",
                        );
                    }
                }

                // Log removals
                $idDetail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan' => $Kode_Karyawan,
                    'Jenis' => 'Team',
                    'Deskripsi' => 'HC-Absensi | Removed from team',
                ]);

                foreach ($removedMembers as $emp) {
                    DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                        'Kode_Perusahaan' => '001',
                        'No_Transaksi' => $noTransaksi,
                        'Field_Diubah' => 'Kode_Karyawan.HRIS_Karyawan_Team',
                        'Deskripsi' => 'HC-Absensi | Removed member',
                        'Nilai_Awal' => $emp['Kode_Karyawan'],
                        'Nilai_Setelah' => null,
                        'Urut_Detail' => $idDetail,
                    ]);
                }
            }

            // Tambah member
            if (! empty($newMembers)) {
                $existingCodes = DB::table('HRIS_Karyawan_Team')
                    ->where('Kode_Karyawan_Team', $Kode_Karyawan)
                    ->pluck('Kode_Karyawan')
                    ->toArray();
                $insertData = [];
                foreach ($newMembers as $emp) {
                    if (! in_array($emp['Kode_Karyawan'], $existingCodes)) {
                        $insertData[] = [
                            'Kode_Karyawan_Team' => $Kode_Karyawan,
                            'Kode_Karyawan' => $emp['Kode_Karyawan'],
                        ];
                    }
                }
                if (! empty($insertData)) {
                    $inserted = DB::table('HRIS_Karyawan_Team')->insert($insertData);
                    if (! $inserted) {
                        foreach ($insertData as $emp) {
                            Log::channel('addTeam')->error(
                                "Terjadi error saat menambahkan {$emp['Kode_Karyawan']} ke team {$Kode_Karyawan}",
                            );
                        }
                    }

                    // Log additions
                    $idDetail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                        'Kode_Perusahaan' => '001',
                        'No_Transaksi' => $noTransaksi,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'Jenis' => 'Team',
                        'Deskripsi' => 'HC-Absensi | Added to team',
                    ]);
                    foreach ($newMembers as $emp) {
                        DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                            'Kode_Perusahaan' => '001',
                            'No_Transaksi' => $noTransaksi,
                            'Field_Diubah' => 'Kode_Karyawan.HRIS_Karyawan_Team',
                            'Deskripsi' => 'HC-Absensi | Added member',
                            'Nilai_Awal' => null,
                            'Nilai_Setelah' => $emp['Kode_Karyawan'],
                            'Urut_Detail' => $idDetail,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil menyimpan teams',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->safeRollback();
            Log::channel('addTeam')->error('Input Tidak Valid: '.json_encode($e->errors()));

            return response()->json(['message' => 'Input tidak valid', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $this->safeRollback();
            Log::channel('addTeam')->error('Gagal menyimpan data Karyawan Team: '.$e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data'], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }

    public function kirimUlangUser(Request $request)
    {
        try {
            DB::beginTransaction();

            $isNew = false;
            $dataAkun = [];
            $Kode_Karyawan = $request->Kode_Karyawan;
            $karyawan = Karyawan::where('Kode_Karyawan', $Kode_Karyawan)->first();
            if (! $karyawan) {
                Log::channel('CreateUserLog')->error("Tidak ada karyawan dengan Kode_Karyawan {$Kode_Karyawan}");
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'message' => "Tidak ada karyawan dengan Kode_Karyawan {$Kode_Karyawan}",
                    ],
                    500,
                );
            }
            if (! $karyawan->UserID_Web) {
                Log::channel('CreateUserLog')->error("Karyawan {$Kode_Karyawan} belum memiliki akun");
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'message' => "Karyawan {$Kode_Karyawan} belum memiliki akun",
                    ],
                    500,
                );
            }
            if ($karyawan->HP == '-' || ! $karyawan->HP) {
                Log::channel('CreateUserLog')->error("Karyawan {$Kode_Karyawan} tidak memiliki nomor HP yang valid");
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'message' => "Karyawan {$Kode_Karyawan} tidak memiliki nomor HP yang valid",
                    ],
                    500,
                );
            }
            $userId = Auth::user()->karyawan->Kode_Karyawan;

            if (
                empty($karyawan->HP) ||
                $karyawan->HP == '-' ||
                ! Str::startsWith($karyawan->HP, '628') ||
                strlen($karyawan->HP) < 11
            ) {
                Log::channel('CreateUserLog')->error("NO HP {$Kode_Karyawan} Tidak ada/Salah");
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'message' => "NO HP {$Kode_Karyawan} Tidak ada/Salah",
                    ],
                    500,
                );
            }
            // Generate No_Transaksi
            $noTransaksi = $this->generateNoTransaksi();
            DB::table('HRIS_Transaksi_Log_Admin')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => Carbon::now(),
                'Jam' => Carbon::now()->format('H:i'),
                'UserID' => $userId,
            ]);

            $user = User::where('Id_Users', $karyawan->UserID_Web)->first();
            if (! $user) {
                Log::channel('CreateUserLog')->error("Tidak ada user {$Kode_Karyawan} untuk diwa ulang");
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'message' => "Tidak ada user {$Kode_Karyawan} untuk diwa ulang",
                    ],
                    500,
                );
            }

            $cleanPasswordPart = $user->Username;
            $randomNumber = str_pad(mt_rand(0, 99999), 5, '0', STR_PAD_LEFT);
            $saltFront = env('SALT_FRONT');
            $saltBack = env('SALT_BACK');
            $rawPassword = $this->generateRandomPassword(11);
            $passwordWithSalt = $saltFront.$rawPassword.$saltBack;
            $encryptedPassword = Hash::make($passwordWithSalt);

            $updateUser = User::where('Id_Users', $karyawan->UserID_Web)->update([
                'Password' => $encryptedPassword,
            ]);

            if ($updateUser < 1) {
                Log::channel('CreateUserLog')->error('Tidak ada update user');
                $this->safeRollback();

                return response()->json(['status' => 500, 'message' => 'Tidak ada update user'], 500);
            }

            // Log password reset
            $idDetail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan' => $Kode_Karyawan,
                'Jenis' => 'User',
                'Deskripsi' => 'HC-Absensi | Password reset and resent',
            ]);

            DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Field_Diubah' => 'Password.KPI_Users',
                'Deskripsi' => 'Reset password',
                'Nilai_Awal' => 'Old password',
                'Nilai_Setelah' => 'HC-Absensi | New Password',
                'Urut_Detail' => $idDetail,
            ]);

            if ($updateUser) {
                $isNew = true;

                $dataAkun = [
                    'nama' => $karyawan->Nama,
                    'divisi' => $karyawan->division->nama_sub_divisi,
                    'username' => $user->Username,
                    'password' => $rawPassword,
                    'HP' => $karyawan->HP,
                ];
            }

            // Kirim user
            $pesan = [
                'messaging_product' => 'whatsapp',
                'to' => $karyawan->HP,
                'type' => 'template',
                'template' => [
                    'name' => 'hcis_daftar_baru_part_1',
                    'language' => ['code' => 'id', 'policy' => 'deterministic'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $karyawan->Nama],
                                ['type' => 'text', 'text' => $user->Username],
                                // ["type" => "text", "text" => $rawPassword],
                            ],
                        ],
                    ],
                ],
            ];

            $waResult = WhatsappLogHelper::sendAndLog(
                $karyawan->Kode_Karyawan,
                $karyawan->Nama,
                $karyawan->HP,
                $noTransaksi,
                $pesan,
                function ($response) use ($karyawan) {
                    Log::channel('CreateUserLog')->info("WA sukses dikirim ke User {$karyawan->Kode_Karyawan}", [
                        'requester' => $karyawan->Kode_Karyawan,
                        'response' => $response,
                    ]);
                },
                function ($reason, $response) use ($karyawan, $noTransaksi, $pesan) {
                    Log::channel('CreateUserLog')->warning("Gagal kirim WA ke User {$karyawan->Kode_Karyawan}", [
                        'reason' => $reason,
                        'response' => $response,
                    ]);

                    DB::table('Whatsapp_Log')->insert([
                        'Kode_Karyawan_Destination' => $karyawan->Kode_Karyawan,
                        'Nama' => $karyawan->Nama,
                        'HP' => $karyawan->HP,
                        'No_Transaksi' => $noTransaksi,
                        'Error_Message' => is_array($response)
                            ? json_encode($response, JSON_UNESCAPED_UNICODE)
                            : $reason ?? 'Unknown error',
                        'RawMessage' => print_r($pesan, true),
                        'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        'Created_At' => now(),
                        'Is_Retried' => 0,
                    ]);
                },
            );

            // Kirim Password
            $pesanPassword = [
                'messaging_product' => 'whatsapp',
                'to' => $karyawan->HP,
                'type' => 'template',
                'template' => [
                    'name' => 'hcis_daftar_baru_part_2',
                    'language' => ['code' => 'id', 'policy' => 'deterministic'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [['type' => 'text', 'text' => $rawPassword]],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => '0',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $rawPassword,
                                ],
                            ],
                        ],
                    ],
                ],
            ];
            $waResult = WhatsappLogHelper::sendAndLog(
                $karyawan->Kode_Karyawan,
                $karyawan->Nama,
                $karyawan->HP,
                $noTransaksi,
                $pesanPassword,
                function ($response) use ($karyawan) {
                    Log::channel('CreateUserLog')->info("WA sukses dikirim ke User {$karyawan->Kode_Karyawan}", [
                        'requester' => $karyawan->Kode_Karyawan,
                        'response' => $response,
                    ]);
                },
                function ($reason, $response) use ($karyawan, $noTransaksi, $pesanPassword) {
                    Log::channel('CreateUserLog')->warning("Gagal kirim WA ke User {$karyawan->Kode_Karyawan}", [
                        'reason' => $reason,
                        'response' => $response,
                    ]);

                    DB::table('Whatsapp_Log')->insert([
                        'Kode_Karyawan_Destination' => $karyawan->Kode_Karyawan,
                        'Nama' => $karyawan->Nama,
                        'HP' => $karyawan->HP,
                        'No_Transaksi' => $noTransaksi,
                        'Error_Message' => is_array($response)
                            ? json_encode($response, JSON_UNESCAPED_UNICODE)
                            : $reason ?? 'Unknown error',
                        'RawMessage' => print_r($pesanPassword, true),
                        'RawMessageJson' => json_encode($pesanPassword, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        'Created_At' => now(),
                        'Is_Retried' => 0,
                    ]);
                },
            );

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil menyimpan teams',
                'dataAkun' => $dataAkun,
                // 'isNewUser' => $isNew //Unlock untuk munculkan popup dateail akun di font end
                'isNewUser' => false,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->safeRollback();
            Log::channel('CreateUserLog')->error('Input Tidak Valid: '.json_encode($e->errors()));

            return response()->json(['message' => 'Input tidak valid', 'errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            $this->safeRollback();
            Log::channel('CreateUserLog')->error('Gagal menyimpan data Karyawan Team: '.$e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data'], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }

    public function ubahApproval(Request $request)
    {
        $validated = $request->validate([
            'oldApprover' => 'required',
            'newApprover' => 'required',
        ]);

        try {
            DB::beginTransaction();
            $userId = Auth::user()->karyawan->Kode_Karyawan;

            // Generate No_Transaksi
            $noTransaksi = $this->generateNoTransaksi();
            DB::table('HRIS_Transaksi_Log_Admin')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => Carbon::now(),
                'Jam' => Carbon::now()->format('H:i'),
                'UserID' => $userId,
            ]);

            $checkApproval = DB::table('HRIS_Approval_Flow')
                ->where('Kode_Karyawan', $validated['oldApprover'])
                ->whereNull('Status')
                ->get();
            $checkNewApprover = DB::table('Karyawan')
                ->where('Kode_Karyawan', $validated['newApprover'])
                ->where('Aktif', 'Y')
                ->exists();

            if (! $checkNewApprover) {
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 404,
                        'eror' => 'Tidak menemukan approver baru, Karyawan dengan Kode_Karyawan '.$validated['newApprover'],
                        'message' => 'Tidak menemukan approver baru, Karyawan dengan Kode_Karyawan '.$validated['newApprover'],
                    ],
                    404,
                );
            }

            if ($checkApproval->isEmpty()) {
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 404,
                        'eror' => 'Tidak menemukan data approval flow '.$validated['oldApprover'],
                        'message' => 'Tidak menemukan data approval flow '.$validated['oldApprover'],
                    ],
                    404,
                );
            }

            $approverChoosed = $checkApproval->first();

            $e = DB::table('HRIS_Approval_Request as a')
                ->join('HRIS_Approval_Flow as b', 'a.Flow_Id', '=', 'b.id')
                ->whereNull('a.Flag_Approval')
                ->where('b.Kode_Karyawan', $approverChoosed->Kode_Karyawan)
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from(
                            DB::raw("
                            (
                                SELECT a.No_Transaksi FROM Transaksi_Terlambat_Pulang_Cepat a JOIN Transaksi_Terlambat_Pulang_Cepat_Detail b ON a.No_Transaksi = b.No_Transaksi WHERE (a.Status = 'Y' OR b.Flag_Approval is not null)
                                UNION ALL
                                SELECT a.No_Transaksi FROM Transaksi_Sakit_Izin a JOIN Transaksi_Sakit_Izin_Detail b ON a.No_Transaksi = b.No_Transaksi WHERE (a.Status = 'Y' OR b.Flag_Approval is not null)
                                UNION ALL
                                SELECT a.No_Transaksi FROM Transaksi_Cuti a JOIN Transaksi_Cuti_Detail b ON a.No_Transaksi = b.No_Transaksi WHERE (a.Status = 'Y' OR b.Flag_Approval is not null)
                            ) as t_check
                        "),
                        )
                        ->whereColumn('t_check.No_Transaksi', 'a.No_Transaksi');
                })
                ->select('a.*', 'b.order_flow')
                ->orderBy('a.No_Transaksi')
                ->get();
            // dd($e);

            if (count($e) != 0) {
                $kumpulanTransaksi = collect($e)->pluck('No_Transaksi')->implode(', ');
                $this->safeRollback();
                Log::channel('KaryawanTeamLog')->error(
                    "Gagal mengupdate approver karena approver masih ada pengajuan yang belum di acc, {$kumpulanTransaksi} ",
                );

                return response()->json(
                    [
                        'message' => "Gagal mengupdate approver karena approver masih ada pengajuan yang belum di acc, {$kumpulanTransaksi} ",
                    ],
                    500,
                );
            }

            $updatePindah = DB::table('HRIS_Approval_Flow')
                ->where('Kode_Karyawan', $validated['oldApprover'])
                ->whereNull('Status')
                ->update(['Status' => 'Y']);

            if ($updatePindah === 0) {
                $this->safeRollback();

                return response()->json(
                    [
                        'status' => 500,
                        'error' => 'Tidak ada data approval lama yang diperbarui untuk '.$validated['oldApprover'],
                        'message' => 'Gagal memperbarui approval lama '.$validated['oldApprover'],
                    ],
                    500,
                );
            }

            $karyawan = Karyawan::where('Kode_Karyawan', $validated['newApprover'])->first();
            $levelNewApprover = $karyawan->level->ID_Level ?? null;
            $divisiNewApprover = $karyawan->division->ID_Divisi ?? null;

            $dataInsert = $checkApproval
                ->filter(function ($e) use ($validated) {
                    return $validated['newApprover'] != $e->Kode_Karyawan_Requester;
                })
                ->map(function ($e) use ($validated, $levelNewApprover, $divisiNewApprover) {
                    $e->Kode_Karyawan = $validated['newApprover'];
                    $e->Division_Id = $divisiNewApprover;
                    $e->level_Id = $levelNewApprover;
                    unset($e->id);
                    unset($e->Status);

                    return (array) $e;
                })
                ->values()
                ->toArray();

            $insertData = DB::table('HRIS_Approval_Flow')->insert($dataInsert);

            $idDetail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan' => $validated['oldApprover'],
                'Jenis' => 'Approval',
                'Deskripsi' => 'HC-Absensi | change approver',
            ]);

            DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Field_Diubah' => 'Kode_Karyawan.HRIS_Approval_Flow',
                'Deskripsi' => 'HC-Absensi | Deactivated approver',
                'Nilai_Awal' => $validated['oldApprover'],
                'Nilai_Setelah' => $validated['newApprover'],
                'Urut_Detail' => $idDetail,
            ]);

            DB::commit();

            return response()->json([
                'status' => 200,
                'message' => 'Berhasil mengalihkan approval!',
            ]);
        } catch (\Exception $e) {
            $this->safeRollback();
            Log::channel('ubahApproval')->error('Gagal memindahkan approval: '.$e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan saat menyimpan data'], 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }
}
