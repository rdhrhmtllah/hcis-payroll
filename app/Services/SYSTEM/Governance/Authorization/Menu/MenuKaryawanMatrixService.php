<?php

namespace App\Services\SYSTEM\Governance\Authorization\Menu;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalMatrixService;
use App\Services\SYSTEM\Governance\Authorization\Scope\EmployeeTargetScopeService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Matrix menu x karyawan untuk satu grup fitur: siapa yang BELUM menerima
 * menu apa.
 *
 * "Sudah terima" di sini sengaja hanya berarti assign manual
 * (HRIS_Page_Access aktif) — keputusan pemilik fitur, sejalan dengan tab
 * Ringkasan. Akses lewat role grup tidak dihitung. Menu global
 * (Is_Global_Menu='Y') DIKELUARKAN dari kolom: semua orang memang sudah
 * memilikinya tanpa assign, sehingga kolomnya hanya akan tampil sebagai
 * "belum" massal yang tidak berarti apa pun.
 *
 * Cakupan karyawan sama persis dengan Ringkasan: karyawan aktif yang
 * dipersempit EmployeeTargetScopeService untuk admin yang sedang melihat.
 * Layar dan berkas ekspor memanggil fungsi yang sama supaya angkanya tidak
 * pernah berselisih.
 *
 * Biaya kueri tetap berapa pun jumlah karyawannya: menu, halaman baris,
 * akses untuk baris di halaman itu, rekap per menu, dan statistik.
 */
class MenuKaryawanMatrixService
{
    public const PER_HALAMAN_MAKS = 100;

    public function __construct(
        private readonly ApprovalMatrixService $matrixService,
        private readonly EmployeeTargetScopeService $targetScopeService,
    ) {}

    public function assertCanView(User $user, int $grupId): void
    {
        $capabilities = $this->matrixService->buildMatrixCapabilities($user, $grupId);
        abort_unless($capabilities['menu']['canView'], 403, 'Anda tidak memiliki akses tab Menu.');
    }

    /**
     * Menu non-global milik grup, urut per header lalu nama.
     *
     * @return \Illuminate\Support\Collection<int, object{Jenis_page: string, Nama_Menu: string, Header_Label: ?string}>
     */
    public function menuGrup(int $grupId)
    {
        return DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', $grupId)
            ->where('gfm.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('m.Is_Global_Menu')->orWhere('m.Is_Global_Menu', '<>', 'Y'))
            ->select('m.Jenis_page', 'm.Nama_Menu', 'm.Header_Label')
            ->distinct()
            ->orderBy('m.Header_Label')
            ->orderBy('m.Nama_Menu')
            ->get();
    }

    /**
     * Data satu halaman layar.
     *
     * @param  array{search?: ?string, cabang?: ?string, divisi_id?: ?int, sub_divisi_id?: ?int, level_id?: ?int, jabatan_id?: ?int, hanya_belum?: bool}  $filter
     */
    public function halaman(User $user, int $grupId, array $filter, int $page, int $perPage): array
    {
        $this->assertCanView($user, $grupId);

        $perPage = max(5, min(self::PER_HALAMAN_MAKS, $perPage));
        $page = max(1, $page);

        $menu = $this->menuGrup($grupId);
        $pages = $menu->pluck('Jenis_page')->all();
        $totalMenu = count($pages);

        $baris = $this->barisKaryawan($user, $grupId, $filter, $pages);

        $stat = DB::query()->fromSub(clone $baris, 'x')
            ->selectRaw('COUNT(*) AS karyawan')
            ->selectRaw('SUM(CASE WHEN x.jumlah_terima >= ? THEN 1 ELSE 0 END) AS lengkap', [$totalMenu])
            ->selectRaw('SUM(CASE WHEN x.jumlah_terima = 0 THEN 1 ELSE 0 END) AS tanpa_akses')
            ->first();

        $isi = (clone $baris)
            ->orderBy('e.Nama')
            ->orderBy('e.Kode_Karyawan')
            ->forPage($page, $perPage)
            ->get();

        $aksesPerUser = $this->aksesUntuk($isi->pluck('UserID_Web')->all(), $pages);

        $rows = $isi->map(function ($r) use ($aksesPerUser, $totalMenu) {
            $terima = $aksesPerUser[$r->UserID_Web] ?? [];

            return [
                'UserID_Web' => (int) $r->UserID_Web,
                'Kode_Karyawan' => $r->Kode_Karyawan,
                'Nama' => $r->Nama,
                'Divisi' => $r->Divisi ?: '—',
                'SubDivisi' => $r->SubDivisi ?: '—',
                'Level' => $r->Level ?: '—',
                'Jabatan' => $r->Jabatan ?: '—',
                'Lokasi' => $r->Lokasi ?: '—',
                'pages' => array_values($terima),
                'jumlah_belum' => $totalMenu - count($terima),
            ];
        })->values()->all();

        $jumlahKaryawan = (int) ($stat->karyawan ?? 0);

        return [
            'menus' => $this->rekapMenu($menu, $user, $grupId, $filter, $jumlahKaryawan),
            'rows' => $rows,
            'total' => $jumlahKaryawan,
            'page' => $page,
            'per_page' => $perPage,
            'stats' => [
                'total_menu' => $totalMenu,
                'karyawan' => $jumlahKaryawan,
                'lengkap' => (int) ($stat->lengkap ?? 0),
                'belum_lengkap' => $jumlahKaryawan - (int) ($stat->lengkap ?? 0),
                'tanpa_akses' => (int) ($stat->tanpa_akses ?? 0),
            ],
            'target_scope' => $this->targetScopeService->metadata($user, $grupId),
        ];
    }

    /**
     * Seluruh baris tanpa paging, untuk ekspor.
     *
     * @return array{menus: array, rows: array}
     */
    public function semua(User $user, int $grupId, array $filter): array
    {
        $this->assertCanView($user, $grupId);

        $menu = $this->menuGrup($grupId);
        $pages = $menu->pluck('Jenis_page')->all();
        $totalMenu = count($pages);

        $baris = $this->barisKaryawan($user, $grupId, $filter, $pages);

        $isi = (clone $baris)->orderBy('e.Nama')->orderBy('e.Kode_Karyawan')->get();

        // Satu kueri untuk seluruh akses populasi, disaring lewat sub-kueri
        // yang sama — bukan whereIn ratusan id yang menabrak batas 2.100
        // parameter SQL Server.
        $akses = [];
        if ($pages !== []) {
            DB::table('HRIS_Page_Access as pa')
                ->joinSub((clone $baris)->select('e.UserID_Web'), 'p', 'p.UserID_Web', '=', 'pa.UserID_Web')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages)
                ->select('pa.UserID_Web', 'pa.Jenis_page')
                ->distinct()
                ->get()
                ->each(function ($r) use (&$akses) {
                    $akses[$r->UserID_Web][$r->Jenis_page] = $r->Jenis_page;
                });
        }

        $rows = $isi->map(function ($r) use ($akses, $totalMenu) {
            $terima = $akses[$r->UserID_Web] ?? [];

            return [
                'Kode_Karyawan' => (string) $r->Kode_Karyawan,
                'Nama' => (string) $r->Nama,
                'Divisi' => (string) ($r->Divisi ?? ''),
                'SubDivisi' => (string) ($r->SubDivisi ?? ''),
                'Level' => (string) ($r->Level ?? ''),
                'Jabatan' => (string) ($r->Jabatan ?? ''),
                'Lokasi' => (string) ($r->Lokasi ?? ''),
                'pages' => $terima,
                'jumlah_belum' => $totalMenu - count($terima),
            ];
        })->values()->all();

        return [
            'menus' => $this->rekapMenu($menu, $user, $grupId, $filter, count($rows)),
            'rows' => $rows,
        ];
    }

    public function hitungKaryawan(User $user, int $grupId, array $filter): int
    {
        $pages = $this->menuGrup($grupId)->pluck('Jenis_page')->all();

        return DB::query()->fromSub($this->barisKaryawan($user, $grupId, $filter, $pages), 'x')->count();
    }

    /**
     * Karyawan aktif dalam cakupan admin + saringan, masing-masing membawa
     * jumlah menu grup yang sudah di-assign manual.
     */
    private function barisKaryawan(User $user, int $grupId, array $filter, array $pages): Builder
    {
        $karyawan = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'k.ID_Level_Jabatan', '=', 'l.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.UserID_Web');
        $this->targetScopeService->applyToQuery($karyawan, $user, $grupId, 'k');

        if (! empty($filter['cabang'])) {
            $karyawan->where('k.Cabang_Untuk_Gaji', $filter['cabang']);
        }
        if (! empty($filter['divisi_id'])) {
            $karyawan->where('d.ID_Divisi', (int) $filter['divisi_id']);
        }
        if (! empty($filter['sub_divisi_id'])) {
            $karyawan->where('k.ID_Divisi_Sub_Divisi', (int) $filter['sub_divisi_id']);
        }
        if (! empty($filter['level_id'])) {
            $karyawan->where('l.ID_Level', (int) $filter['level_id']);
        }
        if (! empty($filter['jabatan_id'])) {
            $karyawan->where('l.ID_Jabatan', (int) $filter['jabatan_id']);
        }
        if (! empty($filter['search'])) {
            $like = '%'.trim((string) $filter['search']).'%';
            $karyawan->where(fn ($q) => $q->where('k.Nama', 'like', $like)->orWhere('k.Kode_Karyawan', 'like', $like));
        }
        if (! empty($filter['menu_page'])) {
            $targetPage = (string) $filter['menu_page'];
            $menuStatus = ($filter['menu_status'] ?? 'missing_menu') === 'has_menu' ? 'has_menu' : 'missing_menu';

            if ($menuStatus === 'has_menu') {
                $karyawan->whereExists(function ($sub) use ($targetPage) {
                    $sub->select(DB::raw(1))
                        ->from('HRIS_Page_Access as pa')
                        ->whereColumn('pa.UserID_Web', 'k.UserID_Web')
                        ->where('pa.Flag_Aktif', 'Y')
                        ->where('pa.Jenis_page', $targetPage);
                });
            } else {
                $karyawan->whereNotExists(function ($sub) use ($targetPage) {
                    $sub->select(DB::raw(1))
                        ->from('HRIS_Page_Access as pa')
                        ->whereColumn('pa.UserID_Web', 'k.UserID_Web')
                        ->where('pa.Flag_Aktif', 'Y')
                        ->where('pa.Jenis_page', $targetPage);
                });
            }
        }

        // DISTINCT: satu karyawan satu baris walau view organisasinya kembar.
        $karyawan->select(
            'k.UserID_Web',
            'k.Kode_Karyawan',
            'k.Nama',
            'd.nama_divisi as Divisi',
            'd.nama_sub_divisi as SubDivisi',
            'l.nama_level as Level',
            'l.nama_jabatan as Jabatan',
            'k.Cabang_Untuk_Gaji as Lokasi',
        )->distinct();

        $hitung = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Jenis_page', $pages === [] ? ['__tanpa_menu__'] : $pages)
            ->groupBy('UserID_Web')
            ->selectRaw('UserID_Web, COUNT(DISTINCT Jenis_page) AS n');

        $q = DB::query()
            ->fromSub($karyawan, 'e')
            ->leftJoinSub($hitung, 'g', 'g.UserID_Web', '=', 'e.UserID_Web')
            ->select('e.*')
            ->selectRaw('ISNULL(g.n, 0) AS jumlah_terima');

        if (! empty($filter['hanya_belum'])) {
            $q->whereRaw('ISNULL(g.n, 0) < ?', [count($pages)]);
        }

        return $q;
    }

    /**
     * @param  array<int>  $userIds  paling banyak satu halaman layar
     * @return array<int, array<string, string>>
     */
    private function aksesUntuk(array $userIds, array $pages): array
    {
        if ($userIds === [] || $pages === []) {
            return [];
        }

        $akses = [];
        DB::table('HRIS_Page_Access')
            ->whereIn('UserID_Web', $userIds)
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Jenis_page', $pages)
            ->select('UserID_Web', 'Jenis_page')
            ->distinct()
            ->get()
            ->each(function ($r) use (&$akses) {
                $akses[$r->UserID_Web][$r->Jenis_page] = $r->Jenis_page;
            });

        return $akses;
    }

    /**
     * Jumlah penerima per menu di seluruh populasi tersaring — bukan hanya
     * di halaman yang sedang tampil.
     */
    private function rekapMenu($menu, User $user, int $grupId, array $filter, int $jumlahKaryawan): array
    {
        $pages = $menu->pluck('Jenis_page')->all();
        $terima = [];

        if ($pages !== []) {
            // hanya_belum tidak ikut: rekap per menu harus menghitung seluruh
            // populasi, bukan hanya orang yang kebetulan belum lengkap.
            $populasi = $this->barisKaryawan($user, $grupId, array_merge($filter, ['hanya_belum' => false]), $pages)
                ->select('e.UserID_Web');

            $terima = DB::table('HRIS_Page_Access as pa')
                ->joinSub($populasi, 'p', 'p.UserID_Web', '=', 'pa.UserID_Web')
                ->where('pa.Flag_Aktif', 'Y')
                ->whereIn('pa.Jenis_page', $pages)
                ->groupBy('pa.Jenis_page')
                ->selectRaw('pa.Jenis_page, COUNT(DISTINCT pa.UserID_Web) AS n')
                ->pluck('n', 'Jenis_page')
                ->all();

            $jumlahKaryawan = ! empty($filter['hanya_belum'])
                ? DB::query()->fromSub($populasi, 'x')->count()
                : $jumlahKaryawan;
        }

        return $menu->map(fn ($m) => [
            'Jenis_page' => $m->Jenis_page,
            'Nama_Menu' => $m->Nama_Menu ?: $m->Jenis_page,
            'Header_Label' => $m->Header_Label ?: '—',
            'jumlah_terima' => (int) ($terima[$m->Jenis_page] ?? 0),
            'jumlah_belum' => $jumlahKaryawan - (int) ($terima[$m->Jenis_page] ?? 0),
        ])->values()->all();
    }
}
