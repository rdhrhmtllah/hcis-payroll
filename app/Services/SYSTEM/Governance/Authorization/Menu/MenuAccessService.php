<?php

namespace App\Services\SYSTEM\Governance\Authorization\Menu;

use App\Http\Controllers\SYSTEM\Governance\Authorization\PageAccess\PageAccessController;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalMatrixService;
use Illuminate\Support\Facades\DB;

/**
 * Akses menu per KARYAWAN, di-scope per grup fitur (module).
 *
 * Admin module meng-assign menu ke karyawan, terbatas HANYA pada menu yang
 * memang milik grup fitur tersebut (N_HRIS_Grup_Fitur_Map). Grant/revoke
 * ditulis ke HRIS_Page_Access mengikuti pola PageAccessController:
 * - grant  : upsert baris (UserID_Web, Jenis_page) dengan Flag_Aktif='Y'
 *            + ID_Divisi / ID_Level snapshot dari Karyawan
 * - revoke : soft, Flag_Aktif='T'
 *
 * Save berbasis diff dan TIDAK PERNAH menyentuh grant menu milik module lain.
 */
class MenuAccessService
{
    public function __construct(private readonly ApprovalMatrixService $matrixService) {}

    /**
     * Menu milik satu grup fitur (kandidat yang bisa di-assign ke karyawan).
     */
    public function getGroupMenus(User $user, int $grupId): array
    {
        $this->matrixService->assertCanManageGrup($user, $grupId);

        $menus = $this->groupMenusQuery($grupId)
            ->select('m.Jenis_page', 'm.Nama_Menu', 'm.Header_Label')
            ->orderBy('m.Header_Label')
            ->orderBy('m.Nama_Menu')
            ->get();

        return ['menus' => $menus];
    }

    /**
     * Daftar karyawan aktif + jumlah menu grup ini yang sudah di-grant
     * (paginated + search nama/kode/NIK).
     */
    public function getEmployees(User $user, int $grupId, array $filters = []): array
    {
        $this->matrixService->assertCanManageGrup($user, $grupId);

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 15)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $search = trim((string) ($filters['search'] ?? ''));
        $groupPages = $this->groupMenuPages($grupId);

        $grantedCounts = DB::table('HRIS_Page_Access')
            ->select('UserID_Web', DB::raw('COUNT(*) as granted_count'))
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Jenis_page', empty($groupPages) ? ['__none__'] : $groupPages)
            ->groupBy('UserID_Web');

        $query = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
            ->leftJoinSub($grantedCounts, 'pac', 'pac.UserID_Web', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.UserID_Web');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.Kode_Karyawan', 'like', "%{$search}%")
                    ->orWhere('k.UserId_Absen', 'like', "%{$search}%");
            });
        }

        $total = (clone $query)->count();

        $employees = $query
            ->select(
                'k.UserID_Web as user_id',
                'k.Kode_Karyawan',
                'k.Nama',
                'k.UserId_Absen as NIK',
                'vd.nama_divisi',
                DB::raw('ISNULL(pac.granted_count, 0) as granted_count'),
            )
            ->orderBy('k.Nama')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'items' => $employees,
            'total_menu_grup' => count($groupPages),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Menu grup yang sudah di-grant ke satu karyawan.
     */
    public function getEmployeeAccess(User $user, int $grupId, int $userIdWeb): array
    {
        $this->matrixService->assertCanManageGrup($user, $grupId);

        $karyawan = $this->findKaryawanByUserId($userIdWeb);
        $groupPages = $this->groupMenuPages($grupId);

        $granted = empty($groupPages)
            ? collect()
            : DB::table('HRIS_Page_Access')
                ->where('UserID_Web', $userIdWeb)
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Jenis_page', $groupPages)
                ->pluck('Jenis_page');

        return [
            'karyawan' => [
                'user_id' => $userIdWeb,
                'kode' => $karyawan->Kode_Karyawan,
                'nama' => $karyawan->Nama,
            ],
            'grantedPages' => $granted,
        ];
    }

    /**
     * Simpan akses menu karyawan untuk grup ini (diff-based).
     * $pages = daftar Jenis_page yang DIINGINKAN aktif; harus subset menu grup.
     */
    public function saveEmployeeAccess(User $user, int $grupId, int $userIdWeb, array $pages): array
    {
        $this->matrixService->assertCanManageGrup($user, $grupId);

        $karyawan = $this->findKaryawanByUserId($userIdWeb);
        $groupPages = $this->groupMenuPages($grupId);

        $pages = collect($pages)
            ->map(fn ($page) => trim((string) $page))
            ->filter()
            ->unique()
            ->values();

        $outside = $pages->diff($groupPages);
        if ($outside->isNotEmpty()) {
            abort(422, 'Menu di luar grup fitur ini: '.$outside->implode(', '));
        }

        return DB::transaction(function () use ($userIdWeb, $pages, $groupPages, $karyawan) {
            $currentlyGranted = empty($groupPages)
                ? collect()
                : DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $userIdWeb)
                    ->where('Flag_Aktif', 'Y')
                    ->whereIn('Jenis_page', $groupPages)
                    ->pluck('Jenis_page');

            $toGrant = $pages->diff($currentlyGranted);
            $toRevoke = $currentlyGranted->diff($pages);

            $orgSnapshot = $this->resolveOrgSnapshot($karyawan->Kode_Karyawan);

            foreach ($toGrant as $jenisPage) {
                $exists = DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $userIdWeb)
                    ->where('Jenis_page', $jenisPage)
                    ->exists();

                if ($exists) {
                    DB::table('HRIS_Page_Access')
                        ->where('UserID_Web', $userIdWeb)
                        ->where('Jenis_page', $jenisPage)
                        ->update([
                            'ID_Divisi' => $orgSnapshot['ID_Divisi'],
                            'ID_Level' => $orgSnapshot['ID_Level'],
                            'Kode_Perusahaan' => '001',
                            'Updated_At' => now(),
                            'Flag_Aktif' => 'Y',
                        ] + (\App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::punya('kedaluwarsa_grant') ? ['Berlaku_Sampai' => null] : []));
                } else {
                    DB::table('HRIS_Page_Access')->insert([
                        'UserID_Web' => $userIdWeb,
                        'Jenis_page' => $jenisPage,
                        'ID_Divisi' => $orgSnapshot['ID_Divisi'],
                        'ID_Level' => $orgSnapshot['ID_Level'],
                        'Kode_Perusahaan' => '001',
                        'Created_At' => now(),
                        'Updated_At' => now(),
                        'Flag_Aktif' => 'Y',
                    ]);
                }
            }

            if ($toRevoke->isNotEmpty()) {
                DB::table('HRIS_Page_Access')
                    ->where('UserID_Web', $userIdWeb)
                    ->whereIn('Jenis_page', $toRevoke->all())
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);
            }

            return [
                'di_grant' => $toGrant->values()->all(),
                'di_revoke' => $toRevoke->values()->all(),
            ];
        });
    }

    // ========================================================================
    // Internal
    // ========================================================================

    private function groupMenusQuery(int $grupId)
    {
        return DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y');
    }

    /**
     * @return array<int, string>
     */
    private function groupMenuPages(int $grupId): array
    {
        return $this->groupMenusQuery($grupId)->pluck('m.Jenis_page')->unique()->values()->all();
    }

    private function findKaryawanByUserId(int $userIdWeb): object
    {
        $karyawan = DB::table('Karyawan')
            ->where('UserID_Web', $userIdWeb)
            ->where('Aktif', 'Y')
            ->first(['Kode_Karyawan', 'Nama', 'UserID_Web']);

        if (! $karyawan) {
            abort(404, 'Karyawan tidak ditemukan atau tidak aktif.');
        }

        return $karyawan;
    }

    /**
     * Snapshot ID_Divisi / ID_Level karyawan untuk baris HRIS_Page_Access
     * (mengikuti pola PageAccessController::updateAccess).
     *
     * @return array{ID_Divisi: int|null, ID_Level: int|null}
     */
    private function resolveOrgSnapshot(string $kodeKaryawan): array
    {
        $row = DB::table('Karyawan as a')
            ->leftJoin('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
            ->where('a.Kode_Karyawan', $kodeKaryawan)
            ->first(['b.ID_Divisi', 'c.ID_Level']);

        return [
            'ID_Divisi' => $row->ID_Divisi ?? null,
            'ID_Level' => $row->ID_Level ?? null,
        ];
    }
}
