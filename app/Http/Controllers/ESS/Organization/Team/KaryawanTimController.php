<?php

namespace App\Http\Controllers\ESS\Organization\Team;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KaryawanTimController extends Controller
{
    /**
     * Index page untuk Manajemen Karyawan Tim KPI
     */
    public function index()
    {
        // C:\laragon\www\hcis-kpi-grouped\resources\js\Pages\Modules\Administrator\ManajemenKaryawanTimKPI.vue
        return inertia('KPI/Performance/Team/ManajemenKaryawanTimKPI');
    }

    /**
     * Mendapatkan semua data karyawan dengan informasi tim
     */
    public function getTeamMembers(Request $request)
    {
        try {
            $query = DB::table('Karyawan as k')
                ->leftJoin('KPI_Karyawan_Team as t', 'k.Kode_Karyawan', '=', 't.Kode_Karyawan_Team')
                ->leftJoin('Karyawan as leader', 't.Kode_Karyawan_Leader', '=', 'leader.Kode_Karyawan')
                ->leftJoin('KPI_Users as u', 'k.UserID_Web', '=', 'u.Id_Users')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->select([
                    'k.Kode_Karyawan as id',
                    'k.Nama as name',
                    'k.Kode_Karyawan as employeeId',
                    'lvl.nama_jabatan as position',
                    'div.nama_divisi as department',
                    'leader.Nama as leaderName',
                    'leader.Kode_Karyawan as leaderId',
                    DB::raw('CASE WHEN t.Kode_Karyawan_Leader IS NOT NULL THEN "Tim " + leader.Nama ELSE "Belum Ditugaskan" END as team'),
                    DB::raw('COALESCE(team_counts.member_count, 0) as teamCount'),
                    DB::raw('CASE WHEN k.Aktif = "Y" THEN "active" ELSE "inactive" END as status'),
                ])
                ->leftJoinSub(function ($query) {
                    $query->select('Kode_Karyawan_Leader', DB::raw('COUNT(*) as member_count'))
                        ->from('KPI_Karyawan_Team')
                        ->where('Flag_Aktif', 'Y')
                        ->groupBy('Kode_Karyawan_Leader');
                }, 'team_counts', function ($join) {
                    $join->on('k.Kode_Karyawan', '=', 'team_counts.Kode_Karyawan_Leader');
                });

            // Filter pencarian
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('k.Nama', 'like', "%{$search}%")
                        ->orWhere('k.Kode_Karyawan', 'like', "%{$search}%")
                        ->orWhere('lvl.nama_jabatan', 'like', "%{$search}%");
                });
            }

            // Filter department
            if ($request->filled('department')) {
                $query->where('div.nama_divisi', $request->department);
            }

            // Filter position
            if ($request->filled('position')) {
                $query->where('lvl.nama_jabatan', $request->position);
            }

            // Filter team
            if ($request->filled('team')) {
                if ($request->team === 'unassigned') {
                    $query->whereNull('t.Kode_Karyawan_Leader');
                } else {
                    $query->where('leader.Nama', 'like', "%{$request->team}%");
                }
            }

            $members = $query->orderBy('k.Nama')->get();

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data anggota tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan data leader tim
     */
    public function getTeamLeaders()
    {
        try {
            $leaders = DB::table('Karyawan as k')
                ->join('KPI_Karyawan_Team as t', 'k.Kode_Karyawan', '=', 't.Kode_Karyawan_Leader')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->select([
                    'k.Kode_Karyawan as id',
                    'k.Nama as name',
                    'lvl.nama_jabatan as position',
                    'div.nama_divisi as department',
                    DB::raw("('Tim ' + k.Nama) as team"),
                ])
                ->where('t.Flag_Aktif', 'Y')
                ->where('k.Aktif', 'Y')
                ->distinct()
                ->orderBy('k.Nama')
                ->get();

            /*
             * Jumlah anggota dihitung SEKALI untuk seluruh leader. Sebelumnya
             * satu COUNT per leader, tepat sesudah daftar leader-nya diambil.
             */
            $jumlahAnggota = DB::table('KPI_Karyawan_Team')
                ->whereIn('Kode_Karyawan_Leader', $leaders->pluck('id')->all())
                ->where('Flag_Aktif', 'Y')
                ->groupBy('Kode_Karyawan_Leader')
                /* Alias EKSPLISIT: lihat catatan yang sama di EntitasService --
                   pluck(DB::raw('COUNT(*)'), ...) menghasilkan
                   "Undefined property: stdClass::$COUNT(*)" pada SQL Server. */
                ->selectRaw('Kode_Karyawan_Leader, COUNT(*) AS jumlah')
                ->pluck('jumlah', 'Kode_Karyawan_Leader');

            foreach ($leaders as $leader) {
                $leader->memberCount = (int) ($jumlahAnggota[$leader->id] ?? 0);
            }

            return response()->json([
                'success' => true,
                'data' => $leaders,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data leader tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan anggota yang tersedia (belum memiliki tim)
     */
    public function getAvailableMembers()
    {
        try {
            $members = DB::table('Karyawan as k')
                ->leftJoin('KPI_Karyawan_Team as t', 'k.Kode_Karyawan', '=', 't.Kode_Karyawan_Team')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->select([
                    'k.Kode_Karyawan as id',
                    'k.Nama as name',
                    'lvl.nama_jabatan as position',
                    'div.nama_divisi as department',
                ])
                ->whereNull('t.Kode_Karyawan_Team')
                ->where('k.Aktif', 'Y')
                ->orderBy('k.Nama')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $members,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data anggota tersedia: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan detail tim berdasarkan leader
     */
    public function getTeamDetails($leaderId)
    {
        try {
            $leader = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->select([
                    'k.Kode_Karyawan as id',
                    'k.Nama as name',
                    'lvl.nama_jabatan as position',
                    'div.nama_divisi as department',
                ])
                ->where('k.Kode_Karyawan', $leaderId)
                ->first();

            if (! $leader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leader tidak ditemukan',
                ], 404);
            }

            $members = DB::table('KPI_Karyawan_Team as t')
                ->join('Karyawan as k', 't.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->select([
                    'k.Kode_Karyawan as id',
                    'k.Nama as name',
                    'k.Kode_Karyawan as employeeId',
                    'lvl.nama_jabatan as position',
                    'div.nama_divisi as department',
                    't.create_at as joinedAt',
                    DB::raw("CASE WHEN k.Aktif = 'Y' THEN 'active' ELSE 'inactive' END as status"),
                ])
                ->where('t.Kode_Karyawan_Leader', $leaderId)
                ->where('t.Flag_Aktif', 'Y')
                ->orderBy('k.Nama')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'leader' => $leader,
                    'members' => $members,
                    'totalMembers' => $members->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menambahkan anggota ke tim
     */
    public function assignMemberToTeam(Request $request)
    {
        $request->validate([
            'leaderId' => 'required|string',
            'memberIds' => 'required|array',
            'memberIds.*' => 'required|string',
        ]);

        try {
            $userId = Auth::id();
            $now = Carbon::now();
            $successCount = 0;
            $failedCount = 0;
            $failedMembers = [];

            foreach ($request->memberIds as $memberId) {
                try {
                    // Cek apakah member sudah ada di tim
                    $existingTeam = DB::table('KPI_Karyawan_Team')
                        ->where('Kode_Karyawan_Team', $memberId)
                        ->where('Flag_Aktif', 'Y')
                        ->first();

                    if ($existingTeam) {
                        // Update ke tim baru
                        DB::table('KPI_Karyawan_Team')
                            ->where('Id_Karyawan_Team', $existingTeam->Id_Karyawan_Team)
                            ->update([
                                'Kode_Karyawan_Leader' => $request->leaderId,
                                'update_at' => $now,
                                'Id_User' => $userId,
                            ]);
                    } else {
                        // Insert baru
                        DB::table('KPI_Karyawan_Team')->insert([
                            'Kode_Karyawan_Leader' => $request->leaderId,
                            'Kode_Karyawan_Team' => $memberId,
                            'create_at' => $now,
                            'update_at' => $now,
                            'Id_User' => $userId,
                            'Flag_Aktif' => 'Y',
                        ]);
                    }
                    $successCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    $failedMembers[] = $memberId;
                }
            }

            $message = $successCount > 0
                ? "Berhasil menugaskan {$successCount} anggota ke tim"
                : 'Tidak ada anggota yang berhasil ditugaskan';

            if ($failedCount > 0) {
                $message .= ". {$failedCount} anggota gagal ditugaskan.";
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'successCount' => $successCount,
                    'failedCount' => $failedCount,
                    'failedMembers' => $failedMembers,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menugaskan anggota ke tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memindahkan anggota tim secara bulk
     */
    public function bulkTransferMembers(Request $request)
    {
        $request->validate([
            'memberIds' => 'required|array',
            'memberIds.*' => 'required|string',
            'newLeaderId' => 'required|string',
        ]);

        try {
            $userId = Auth::id();
            $now = Carbon::now();
            $memberIds = $request->memberIds;
            $newLeaderId = $request->newLeaderId;

            // Get leader info for response
            $newLeader = DB::table('Karyawan')
                ->where('Kode_Karyawan', $newLeaderId)
                ->first();

            if (! $newLeader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leader tujuan tidak ditemukan',
                ], 404);
            }

            $successCount = 0;
            $failedCount = 0;
            $failedMembers = [];

            DB::beginTransaction();
            try {
                foreach ($memberIds as $memberId) {
                    try {
                        // Cek apakah member sudah ada di tim
                        $existingTeam = DB::table('KPI_Karyawan_Team')
                            ->where('Kode_Karyawan_Team', $memberId)
                            ->where('Flag_Aktif', 'Y')
                            ->first();

                        if ($existingTeam) {
                            // Update ke tim baru
                            DB::table('KPI_Karyawan_Team')
                                ->where('Id_Karyawan_Team', $existingTeam->Id_Karyawan_Team)
                                ->update([
                                    'Kode_Karyawan_Leader' => $newLeaderId,
                                    'update_at' => $now,
                                    'Id_User' => $userId,
                                ]);
                        } else {
                            // Insert baru
                            DB::table('KPI_Karyawan_Team')->insert([
                                'Kode_Karyawan_Leader' => $newLeaderId,
                                'Kode_Karyawan_Team' => $memberId,
                                'create_at' => $now,
                                'update_at' => $now,
                                'Id_User' => $userId,
                                'Flag_Aktif' => 'Y',
                            ]);
                        }
                        $successCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $failedMembers[] = $memberId;
                    }
                }

                DB::commit();

                $message = "Berhasil memindahkan {$successCount} anggota ke tim {$newLeader->Nama}";
                if ($failedCount > 0) {
                    $message .= ". {$failedCount} anggota gagal dipindahkan.";
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'successCount' => $successCount,
                        'failedCount' => $failedCount,
                        'failedMembers' => $failedMembers,
                        'newLeader' => $newLeader->Nama,
                    ],
                ]);

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            } catch (\Throwable $e) {
                /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
                   jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
                   supaya handler global tetap mencatat stack trace-nya dan responsnya
                   tak berubah sedikit pun. */
                DB::rollback();

                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memindahkan anggota tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus anggota dari tim
     */
    public function removeMemberFromTeam(Request $request)
    {
        $request->validate([
            'memberId' => 'required|string',
        ]);

        try {
            $updated = DB::table('KPI_Karyawan_Team')
                ->where('Kode_Karyawan_Team', $request->memberId)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'N',
                    'update_at' => Carbon::now(),
                    'Id_User' => Auth::id(),
                ]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'Anggota berhasil dihapus dari tim',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Anggota tidak ditemukan atau sudah tidak aktif dalam tim',
                ], 404);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota dari tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Menghapus beberapa anggota dari tim
     */
    public function bulkRemoveMembersFromTeam(Request $request)
    {
        $request->validate([
            'memberIds' => 'required|array',
            'memberIds.*' => 'required|string',
        ]);

        try {
            $memberIds = $request->memberIds;
            $now = Carbon::now();
            $userId = Auth::id();

            $successCount = DB::table('KPI_Karyawan_Team')
                ->whereIn('Kode_Karyawan_Team', $memberIds)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'N',
                    'update_at' => $now,
                    'Id_User' => $userId,
                ]);

            return response()->json([
                'success' => true,
                'message' => "Berhasil menghapus {$successCount} anggota dari tim",
                'data' => [
                    'removedCount' => $successCount,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus anggota dari tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mendapatkan statistik tim
     */
    public function getTeamStats()
    {
        try {
            $stats = [];

            // Total anggota
            $stats['totalMembers'] = DB::table('Karyawan')
                ->where('Aktif', 'Y')
                ->count();

            // Total leader
            $stats['totalLeaders'] = DB::table('KPI_Karyawan_Team')
                ->where('Flag_Aktif', 'Y')
                ->distinct('Kode_Karyawan_Leader')
                ->count('Kode_Karyawan_Leader');

            // Anggota tersedia
            $stats['availableMembers'] = DB::table('Karyawan as k')
                ->leftJoin('KPI_Karyawan_Team as t', 'k.Kode_Karyawan', '=', 't.Kode_Karyawan_Team')
                ->whereNull('t.Kode_Karyawan_Team')
                ->where('k.Aktif', 'Y')
                ->count();

            // Rata-rata ukuran tim
            $avgTeamSize = DB::table('KPI_Karyawan_Team')
                ->where('Flag_Aktif', 'Y')
                ->selectRaw('COUNT(*) / COUNT(DISTINCT Kode_Karyawan_Leader) as avg_size')
                ->value('avg_size');
            $stats['averageTeamSize'] = round($avgTeamSize ?? 0);

            // Statistik per departemen
            $departmentStats = DB::table('Karyawan as k')
                ->join('KPI_Karyawan_Team as t', 'k.Kode_Karyawan', '=', 't.Kode_Karyawan_Team')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->where('t.Flag_Aktif', 'Y')
                ->where('k.Aktif', 'Y')
                ->select([
                    'div.nama_divisi as department',
                    DB::raw('COUNT(DISTINCT k.Kode_Karyawan) as memberCount'),
                    DB::raw('COUNT(DISTINCT t.Kode_Karyawan_Leader) as leaderCount'),
                ])
                ->groupBy('div.nama_divisi')
                ->orderBy('memberCount', 'desc')
                ->get();

            $stats['departmentBreakdown'] = $departmentStats;

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil statistik tim: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search karyawan untuk tambah anggota
     */
    public function searchEmployees(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:2',
        ]);

        try {
            $employees = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->leftJoin('KPI_Karyawan_Team as t', 'k.Kode_Karyawan', '=', 't.Kode_Karyawan_Team')
                ->select([
                    'k.Kode_Karyawan as id',
                    'k.Nama as name',
                    'k.Kode_Karyawan as employeeId',
                    'lvl.nama_jabatan as position',
                    'div.nama_divisi as department',
                    DB::raw('CASE WHEN t.Kode_Karyawan_Leader IS NOT NULL THEN "assigned" ELSE "available" END as assignmentStatus'),
                ])
                ->where('k.Aktif', 'Y')
                ->where(function ($query) use ($request) {
                    $search = $request->search;
                    $query->where('k.Nama', 'like', "%{$search}%")
                        ->orWhere('k.Kode_Karyawan', 'like', "%{$search}%")
                        ->orWhere('lvl.nama_jabatan', 'like', "%{$search}%");
                })
                ->orderBy('k.Nama')
                ->limit(20)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $employees,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencari karyawan: '.$e->getMessage(),
            ], 500);
        }
    }
}
