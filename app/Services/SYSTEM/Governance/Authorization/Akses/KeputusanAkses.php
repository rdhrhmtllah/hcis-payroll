<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Satu titik keputusan akses halaman (RBAC Fase 1; mode per modul sejak Fase 3a).
 *
 * Dipakai gerbang route (CheckJabatan) DAN sidebar (InertiaShellService) —
 * keduanya wajib bertanya ke sini supaya menu yang tampil = halaman yang bisa
 * dibuka. Aturan: System Admin, atau User::memilikiJabatan (grant langsung,
 * hibah role tersaring Fase 0, menu global). Argumen $aksi belum dievaluasi di
 * mode LAMA; izin per aksi datang di Fase 2.
 */
class KeputusanAkses
{
    public function apakahSystemAdmin(User $u): bool
    {
        return ($u->Is_System_Admin ?? null) === 'Y';
    }

    /**
     * Keputusan yang berlaku (RBAC Fase 3a), menurut mode modul pemilik halaman:
     * LAMA = aturan lama; BAYANGAN = aturan lama + selisih dengan aturan baru
     * dicatat; BARU = aturan baru. System Admin selalu boleh.
     */
    public function boleh(User $u, string $halaman, string $aksi = 'VIEW'): bool
    {
        if ($this->apakahSystemAdmin($u)) {
            return true;
        }
        $mode = app(ModeAkses::class)->modeHalaman($halaman);
        if ($mode === ModeAkses::LAMA || ! $this->modelBaruTersedia()) {
            return $this->bolehLama($u, $halaman);
        }
        if ($mode === ModeAkses::BARU) {
            return $this->hakBaru($u, $halaman, $aksi);
        }

        $lama = $this->bolehLama($u, $halaman);
        $baru = $this->hakBaru($u, $halaman, $aksi);
        if ($lama !== $baru) {
            $this->catatSelisih($u, $halaman, $aksi, $lama, $baru);
        }

        return $lama;
    }

    /**
     * Aksi tulis (request non-GET) pada halaman — RBAC Fase 5. LAMA: sama
     * dengan boleh buka halaman (persis seperti sebelumnya). BARU: butuh salah
     * satu aksi non-VIEW yang dikenal katalog halaman itu (grant & menu global
     * selalu penuh); halaman tanpa aksi tulis di katalog cukup VIEW. BAYANGAN:
     * aturan lama memutuskan, beda dicatat dengan aksi TULIS.
     */
    public function bolehTulis(User $u, string $halaman): bool
    {
        if ($this->apakahSystemAdmin($u)) {
            return true;
        }
        $mode = app(ModeAkses::class)->modeHalaman($halaman);
        if ($mode === ModeAkses::LAMA || ! $this->modelBaruTersedia()) {
            return $this->bolehLama($u, $halaman);
        }
        $baru = $this->hakTulisBaru($u, $halaman);
        if ($mode === ModeAkses::BARU) {
            return $baru;
        }
        $lama = $this->bolehLama($u, $halaman);
        if ($lama !== $baru) {
            $this->catatSelisih($u, $halaman, 'TULIS', $lama, $baru);
        }
        // Halaman yang hanya dipakai lewat POST tetap menyumbang selisih VIEW-nya.
        $baruView = $this->hakBaru($u, $halaman, 'VIEW');
        if ($lama !== $baruView) {
            $this->catatSelisih($u, $halaman, 'VIEW', $lama, $baruView);
        }

        return $lama;
    }

    private function hakTulisBaru(User $u, string $halaman): bool
    {
        $aksi = $this->aksiTulisKatalog()[HibahTataKelola::normalisasi($halaman)] ?? [];
        if ($aksi === []) {
            return $this->hakBaru($u, $halaman, 'VIEW');
        }
        foreach ($aksi as $a) {
            if ($this->hakBaru($u, $halaman, $a)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, list<string>> dibaca hanya untuk request tulis di modul non-LAMA */
    private function aksiTulisKatalog(): array
    {
        if (self::$aksiTulisKatalog === null) {
            self::$aksiTulisKatalog = [];
            $baris = DB::table('N_HRIS_Menu_Capabilities as c')
                ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'c.Id_Aksi')
                ->where('c.Flag_Aktif', 'Y')->where('a.Nama_Aksi', '<>', 'VIEW')
                ->get(['c.Jenis_page', 'a.Nama_Aksi']);
            foreach ($baris as $b) {
                self::$aksiTulisKatalog[HibahTataKelola::normalisasi((string) $b->Jenis_page)][] = strtoupper(trim((string) $b->Nama_Aksi));
            }
        }

        return self::$aksiTulisKatalog;
    }

    /** Aturan LAMA murni: grant langsung, hibah role tersaring Fase 0, menu global. */
    public function bolehLama(User $u, string $halaman): bool
    {
        return $u->memilikiJabatan($halaman);
    }

    /** @var array<string, array{0:int,1:string,2:string,3:bool,4:bool}> selisih request ini, belum ditulis */
    private static array $antrianSelisih = [];

    private static bool $kirimTerdaftar = false;

    /**
     * Mode BAYANGAN (review Fase 3a): selisih TIDAK ditulis di jalur keputusan.
     * Ditampung di memori lalu dikirim sekali setelah respons terkirim
     * (app()->terminating) — jadi tak pernah berada di dalam transaksi pemanggil
     * dan tak menambah waktu request. Satu kombinasi user+halaman+aksi paling
     * sering ditulis sekali per 10 menit (Jumlah = banyak jendela 10 menit).
     */
    private function catatSelisih(User $u, string $halaman, string $aksi, bool $lama, bool $baru): void
    {
        $aksi = strtoupper(trim($aksi));
        self::$antrianSelisih[$u->Id_Users.'|'.HibahTataKelola::normalisasi($halaman).'|'.$aksi] = [(int) $u->Id_Users, $halaman, $aksi, $lama, $baru];

        if (! self::$kirimTerdaftar && app()->bound('app')) {
            self::$kirimTerdaftar = true;
            app()->terminating(fn () => self::kirimSelisih());
        }
    }

    /** Tulis antrian selisih (dipanggil setelah respons; uji memanggilnya langsung). */
    /** @param int $batasTransaksi level transaksi yang dianggap "luar" (0 di aplikasi; uji: level pembungkusnya) */
    public static function kirimSelisih(int $batasTransaksi = 0): void
    {
        $antrian = self::$antrianSelisih;
        self::$antrianSelisih = [];
        if ($antrian === [] || DB::transactionLevel() > $batasTransaksi) {
            return;
        }

        foreach ($antrian as $kunci => [$idUser, $halaman, $aksi, $lama, $baru]) {
            if (! Cache::add('rbac:selisih:'.$kunci, true, 600)) {
                continue;
            }
            try {
                $nilai = ['Keputusan_Lama' => $lama ? 'Y' : 'T', 'Keputusan_Baru' => $baru ? 'Y' : 'T', 'Terakhir_At' => now()];
                $kena = DB::table('N_HRIS_Akses_Selisih')
                    ->where('Id_User', $idUser)->where('Jenis_page', $halaman)->where('Aksi', $aksi)
                    ->update($nilai + ['Jumlah' => DB::raw('Jumlah + 1')]);
                if ($kena === 0) {
                    DB::table('N_HRIS_Akses_Selisih')->insert($nilai + [
                        'Id_User' => $idUser, 'Jenis_page' => mb_substr($halaman, 0, 100), 'Aksi' => mb_substr($aksi, 0, 20),
                    ]);
                }
            } catch (\Throwable $e) {
                // Pencatatan bayangan tak boleh mengganggu apa pun (tabel belum ada, balapan unik, dll.).
                Log::warning('Gagal mencatat selisih akses', ['kunci' => $kunci, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Boleh membuka layar Akses & Peran (RBAC Fase 2b): System Admin, atau
     * pemegang penugasan aktif role berjenis ADMIN. Sebelum DDL-2026-165
     * (kolom Jenis_Role belum ada), daftar role admin dari config.
     */
    public function bolehKelolaAkses(User $u): bool
    {
        return $this->apakahSystemAdmin($u) || $this->modulTerkelola($u) !== [];
    }

    /**
     * Penugasan peran ADMIN (tingkat Admin/Super Admin) aktif & belum kedaluwarsa
     * milik $u. $wajibAkses = hanya yang bertanggung jawab AKSES (boleh MENGUBAH
     * akses); admin approval/keanggotaan tidak (review Fase 3b C2).
     */
    public function kueriPenugasanAdmin(User|int $u, bool $wajibAkses): \Illuminate\Database\Query\Builder
    {
        $baru = SkemaRbac::punya('model_baru');

        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($j) {
                $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->where('ru.UserId_Web', $u instanceof User ? $u->Id_Users : $u)
            ->where('ru.Flag_Aktif', 'Y')
            ->whereNotNull('rf.Id_Grup_Fitur')
            ->when(SkemaRbac::punya('kedaluwarsa_penugasan'), fn ($q) => $q->where(fn ($w) => $w->whereNull('ru.Berlaku_Sampai')->orWhere('ru.Berlaku_Sampai', '>', now())))
            ->when(
                $baru,
                fn ($q) => $q->where('rf.Jenis_Role', 'ADMIN'),
                fn ($q) => $q->whereIn(DB::raw('UPPER(rf.Role_Code)'), HibahTataKelola::peranAdmin()),
            )
            ->when($wajibAkses, fn ($q) => $baru
                ? $q->whereExists(fn ($e) => $e->from('N_HRIS_Role_User_Tanggung_Jawab as tj')
                    ->whereColumn('tj.Id_Role_User', 'ru.Id')->where('tj.Kode', 'AKSES')->where('tj.Flag_Aktif', 'Y'))
                : $q->where(fn ($w) => $w->where('rf.Role_Code', 'like', '%MENU[_]%')->orWhere('rf.Role_Code', 'like', '%SUPER[_]ADMIN')
                    ->orWhere('rf.Role_Code', 'KPI_ADMIN_PHL')));
    }

    /**
     * Modul (Id_Grup_Fitur) yang BOLEH DILIHAT di layar Akses & Peran
     * (semua admin aktif & belum kedaluwarsa). null = semua (System Admin).
     *
     * @return list<int>|null
     */
    public function modulTerkelola(User $u): ?array
    {
        if ($this->apakahSystemAdmin($u)) {
            return null;
        }

        return array_keys($this->petaAdmin($u, false));
    }

    /**
     * Modul yang BOLEH DIUBAH aksesnya oleh $u (admin bertanggung jawab AKSES).
     *
     * @return list<int>|null
     */
    public function modulKelolaAkses(User $u): ?array
    {
        if ($this->apakahSystemAdmin($u)) {
            return null;
        }

        return array_keys($this->petaAdmin($u, true));
    }

    /** Id modul dari kodenya (ess, kpi, …) — untuk halaman Akses & Peran per modul. */
    public function idModul(string $kode): ?int
    {
        $id = DB::table('N_HRIS_Master_Grup_Fitur')->where('Flag_Aktif', 'Y')->whereRaw('UPPER(Kode_Grup_Fitur) = ?', [strtoupper(trim($kode))])
            ->value('Id_Grup_Fitur');

        return $id !== null ? (int) $id : null;
    }

    /** Boleh membuka Akses & Peran modul ini: System Admin, atau admin (aktif) di modul itu. */
    public function bolehKelolaModul(User $u, int $modul): bool
    {
        return $this->apakahSystemAdmin($u) || in_array($modul, $this->modulTerkelola($u) ?? [], true);
    }

    /**
     * Tingkat $u di sebuah modul (master tingkat peran, DDL-2026-181): System
     * Admin = SYSTEM_ADMIN; selain itu tingkat tertinggi penugasan adminnya di
     * modul itu; tanpa penugasan admin = PENGGUNA. Tiap modul berdiri sendiri —
     * Super Admin di KPI bukan apa-apa di LMS.
     */
    public function tingkatDiModul(User $u, int $modul, bool $wajibAkses = true): string
    {
        if ($this->apakahSystemAdmin($u)) {
            return TingkatPeran::SYSTEM_ADMIN;
        }

        return $this->petaAdmin($u, $wajibAkses)[$modul] ?? TingkatPeran::PENGGUNA;
    }

    /** Tingkat karyawan SASARAN di modul — penugasan admin apa pun, tanpa syarat tanggung jawab. */
    public function tingkatSasaranDiModul(int $idUser, int $modul): string
    {
        $sa = self::$sasaranSa[$idUser] ??= DB::table('KPI_Users')->where('Id_Users', $idUser)->value('Is_System_Admin') === 'Y';

        return $sa ? TingkatPeran::SYSTEM_ADMIN : ($this->petaAdmin($idUser, false)[$modul] ?? TingkatPeran::PENGGUNA);
    }

    /**
     * Penugasan admin per modul → tingkat tertinggi. Satu query; dipakai sidebar
     * (bolehKelolaAkses) di setiap muat halaman, jadi dimemo per request.
     *
     * @return array<int, string>
     */
    private function petaAdmin(User|int $u, bool $wajibAkses): array
    {
        $id = $u instanceof User ? (int) $u->Id_Users : $u;

        return self::$petaAdmin[$id.'|'.(int) $wajibAkses] ??= $this->hitungPetaAdmin($u, $wajibAkses);
    }

    private function hitungPetaAdmin(User|int $u, bool $wajibAkses): array
    {
        // Jenis_Role hanya ada sejak DDL-2026-165 — sidebar memanggil ini di setiap request.
        $kolom = ['rf.Id_Grup_Fitur', 'rf.Role_Code'];
        if (SkemaRbac::punya('model_baru')) {
            $kolom[] = 'rf.Jenis_Role';
        }
        if (SkemaRbac::punya('tingkat')) {
            $kolom[] = 'rf.Kode_Tingkat';
        }
        $tingkat = app(TingkatPeran::class);
        $peta = [];
        foreach ($this->kueriPenugasanAdmin($u, $wajibAkses)->get($kolom) as $r) {
            $g = (int) $r->Id_Grup_Fitur;
            $peta[$g] = $tingkat->tertinggi([$peta[$g] ?? TingkatPeran::PENGGUNA, $tingkat->dariPeran($r)]);
        }

        return $peta;
    }

    /** @var array<string, array<int, string>> */
    private static array $petaAdmin = [];

    /** @var array<int, bool> */
    private static array $sasaranSa = [];

    /**
     * Hak model BARU per user selama satu proses/request: [halaman][AKSI] => true.
     *
     * @var array<int, array<string, array<string, true>>>
     */
    private static array $hakBaru = [];

    /** @var array<string, list<string>>|null halaman (ternormalisasi) → aksi non-VIEW di katalog */
    private static ?array $aksiTulisKatalog = null;

    /** @var array<int, array<string, true>> */
    private static array $kemampuanUser = [];

    /** @var array<string, ?int> */
    private static array $modulKemampuan = [];

    public static function lupakanCache(): void
    {
        self::$hakBaru = [];
        self::$petaAdmin = [];
        self::$sasaranSa = [];
        self::$kemampuanUser = [];
        self::$modulKemampuan = [];
        \App\Services\SYSTEM\Governance\Authorization\Scope\CakupanKaryawan::lupakanCache();
        self::$antrianSelisih = [];
        self::$aksiTulisKatalog = null;
    }

    /**
     * Aturan BARU (RBAC Fase 2a): System Admin, ATAU penugasan role aktif &
     * belum kedaluwarsa yang punya N_HRIS_Role_Hak untuk halaman+aksi, ATAU
     * grant langsung aktif & belum kedaluwarsa, ATAU menu global. Grant dan menu
     * global memberi semua aksi halaman itu (Fase 5) — seperti hari ini, saat
     * gerbang route tak pernah membedakan aksi bagi mereka.
     *
     * Belum dipakai memutuskan apa pun — Fase 3 menghitungnya berdampingan
     * (mode bayangan). Tanpa model baru (DDL-2026-165 belum jalan) = aturan LAMA.
     */
    public function bolehBaru(User $u, string $halaman, string $aksi = 'VIEW'): bool
    {
        if ($this->apakahSystemAdmin($u)) {
            return true;
        }
        if (! $this->modelBaruTersedia()) {
            return $this->bolehLama($u, $halaman);
        }

        return $this->hakBaru($u, $halaman, $aksi);
    }

    /** Aturan BARU murni (tanpa cabang System Admin / fallback). */
    private function hakBaru(User $u, string $halaman, string $aksi): bool
    {
        if ($this->superAdminHalaman($u, $halaman)) {
            return true;
        }
        $id = (int) $u->Id_Users;
        self::$hakBaru[$id] ??= $this->muatHakBaru($id);

        $hak = self::$hakBaru[$id][HibahTataKelola::normalisasi($halaman)] ?? [];

        return isset($hak['*']) || isset($hak[strtoupper(trim($aksi))]);
    }

    /**
     * Memeriksa apakah pengguna memiliki kemampuan fitur bisnis tertentu (DDL-2026-194).
     * System Admin otomatis memiliki seluruh kemampuan.
     * Super Admin modul otomatis memiliki seluruh kemampuan pada modulnya.
     */
    public function bisa(User $u, string $kodeKemampuan): bool
    {
        $kode = strtoupper(trim($kodeKemampuan));
        if ($kode === '' || ! SkemaTersedia::tabel('N_HRIS_Fitur_Kemampuan')) {
            return false;
        }

        if (! array_key_exists($kode, self::$modulKemampuan)) {
            self::$modulKemampuan[$kode] = DB::table('N_HRIS_Fitur_Kemampuan as fk')
                ->join('N_HRIS_Fitur as f', 'f.Kode_Fitur', '=', 'fk.Kode_Fitur')
                ->where('fk.Kode_Kemampuan', $kode)
                ->where('fk.Flag_Aktif', 'Y')
                ->where('f.Flag_Aktif', 'Y')
                ->value('f.Id_Grup_Fitur');
        }

        $idGrup = self::$modulKemampuan[$kode];
        if ($idGrup === null) {
            return false; // Kemampuan tidak terdaftar atau nonaktif (fail-closed)
        }

        if ($this->apakahSystemAdmin($u)) {
            return true;
        }

        if ($this->tingkatDiModul($u, (int) $idGrup) === TingkatPeran::SUPER_ADMIN) {
            return true;
        }

        $uid = (int) $u->Id_Users;
        if (! isset(self::$kemampuanUser[$uid])) {
            self::$kemampuanUser[$uid] = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', function ($j) {
                    $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
                })
                ->join('N_HRIS_Role_Kemampuan as rk', function ($j) {
                    $j->on('rk.Id_Role', '=', 'rf.Id_Role')->where('rk.Flag_Aktif', 'Y');
                })
                ->where('ru.UserId_Web', $uid)
                ->where('ru.Flag_Aktif', 'Y')
                ->tap(fn ($q) => SkemaRbac::saringPenugasanAktif($q, 'ru.'))
                ->distinct()
                ->pluck('rk.Kode_Kemampuan')
                ->map(fn ($k) => strtoupper(trim((string) $k)))
                ->flip()
                ->map(fn () => true)
                ->all();
        }

        return isset(self::$kemampuanUser[$uid][$kode]);
    }

    /**
     * Super Admin modul berhak penuh (semua aksi) atas halaman modulnya, lepas dari
     * matriks — kecuali halaman berkebijakan SYSTEM_ONLY, yang tetap butuh hak eksplisit.
     */
    public function superAdminHalaman(User $u, string $halaman): bool
    {
        $modul = app(ModeAkses::class)->modulHalaman($halaman);
        if ($modul === null || ($this->petaAdmin($u, false)[$modul] ?? null) !== TingkatPeran::SUPER_ADMIN) {
            return false;
        }

        // Halaman tata kelola (pengatur akses / sistem) tak lewat hak peran biasa (spec §2.3).
        if (HibahTataKelola::apakahHalamanTataKelola($halaman)) {
            return false;
        }

        return ! in_array(HibahTataKelola::normalisasi($halaman), $this->halamanKhususSystemAdmin(), true);
    }

    /** @return list<string> halaman tata kelola khusus System Admin (bukan untuk Super Admin modul) */
    public function halamanKhususSystemAdmin(): array
    {
        return [
            'adminallpagesaccess',
            'superadminmanajemenperanpage',
            'systemauthmanagerpage',
        ];
    }

    /** Dibaca dari cache lintas-request: Schema::hasTable mahal di prod (dibaca ulang tiap request). */
    protected function modelBaruTersedia(): bool
    {
        return SkemaRbac::punya('model_baru');
    }

    /** @return array<string, array<string, true>> */
    private function muatHakBaru(int $idUser): array
    {
        $sekarang = now();
        $dariRole = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($j) {
                $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Role_Hak as h', function ($j) {
                $j->on('h.Id_Role', '=', 'rf.Id_Role')->where('h.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
            ->where('ru.UserId_Web', $idUser)
            ->where('ru.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('ru.Berlaku_Sampai')->orWhere('ru.Berlaku_Sampai', '>', $sekarang))
            ->select('h.Jenis_page as halaman', 'a.Nama_Aksi as aksi');
        $grant = DB::table('HRIS_Page_Access')
            ->where('UserID_Web', $idUser)
            ->where('Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('Berlaku_Sampai')->orWhere('Berlaku_Sampai', '>', $sekarang))
            ->selectRaw("Jenis_page as halaman, '*' as aksi");
        $global = DB::table('N_HRIS_Menus')->where('Is_Global_Menu', 'Y')->selectRaw("Jenis_page as halaman, '*' as aksi");

        $peta = [];
        foreach ($dariRole->union($grant)->union($global)->get() as $b) {
            $peta[HibahTataKelola::normalisasi((string) $b->halaman)][strtoupper(trim((string) $b->aksi))] = true;
        }

        return $peta;
    }

    /**
     * Jalur yang membuka $halaman bagi $u, menurut aturan yang BERLAKU di modul
     * pemiliknya (RBAC Fase 4): BARU → hak peran (N_HRIS_Role_Hak); LAMA/BAYANGAN
     * → hibah role. BAYANGAN juga membawa jalur_baru untuk dibandingkan.
     * Tidak memanggil boleh() supaya menelusuri tak mencatat selisih.
     *
     * @return array{boleh: bool, mode: string, jalur: list<string>, jalur_baru?: list<string>}
     */
    public function telusuri(User $u, string $halaman): array
    {
        $mode = $this->modelBaruTersedia() ? app(ModeAkses::class)->modeHalaman($halaman) : ModeAkses::LAMA;
        $bersama = $this->jalurBersama($u, $halaman);
        $lama = [...$bersama, ...$this->jalurHibah($u, $halaman)];
        if ($mode === ModeAkses::BARU) {
            $jalur = [...$bersama, ...$this->jalurHakPeran($u, $halaman)];

            return ['boleh' => $jalur !== [], 'mode' => $mode, 'jalur' => $jalur];
        }
        $hasil = ['boleh' => $lama !== [], 'mode' => $mode, 'jalur' => $lama];
        if ($mode === ModeAkses::BAYANGAN) {
            $hasil['jalur_baru'] = [...$bersama, ...$this->jalurHakPeran($u, $halaman)];
        }

        return $hasil;
    }

    private const NORMAL = "LOWER(REPLACE(LTRIM(RTRIM(%s)), ' ', ''))";

    /** @return list<string> jalur yang sama di kedua aturan */
    private function jalurBersama(User $u, string $halaman): array
    {
        $kunci = HibahTataKelola::normalisasi($halaman);
        $jalur = [];
        if ($this->apakahSystemAdmin($u)) {
            $jalur[] = 'SYSTEM_ADMIN';
        }
        if (DB::table('HRIS_Page_Access')->where('UserID_Web', $u->Id_Users)->where('Flag_Aktif', 'Y')
            ->when(SkemaRbac::punya('kedaluwarsa_grant'), fn ($q) => $q->where(fn ($w) => $w->whereNull('Berlaku_Sampai')->orWhere('Berlaku_Sampai', '>', now())))
            ->whereRaw(sprintf(self::NORMAL, 'Jenis_page').' = ?', [$kunci])->exists()) {
            $jalur[] = 'GRANT_LANGSUNG';
        }
        if (DB::table('N_HRIS_Menus')->where('Is_Global_Menu', 'Y')
            ->whereRaw(sprintf(self::NORMAL, 'Jenis_page').' = ?', [$kunci])->exists()) {
            $jalur[] = 'MENU_GLOBAL';
        }

        return $jalur;
    }

    /** @return list<string> aturan lama: hibah role lewat N_HRIS_Grup_Fitur_Map */
    private function jalurHibah(User $u, string $halaman): array
    {
        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($j) {
                $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Grup_Fitur_Map as gfm', function ($j) {
                $j->on('gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')->where('gfm.Flag_Hibah_Role', '=', 'Y');
            })
            ->where('ru.UserId_Web', $u->Id_Users)
            ->where('ru.Flag_Aktif', 'Y')
            ->tap(fn ($q) => SkemaRbac::saringPenugasanAktif($q, 'ru.'))
            ->whereRaw(sprintf(self::NORMAL, 'gfm.Jenis_page').' = ?', [HibahTataKelola::normalisasi($halaman)])
            ->tap(fn ($q) => HibahTataKelola::saringHibah($q, 'gfm.Jenis_page', 'rf.Role_Code'))
            ->distinct()
            ->pluck('rf.Role_Code')
            ->map(fn ($k) => 'HIBAH_ROLE:'.$k)
            ->all();
    }

    /** @return list<string> aturan baru: hak VIEW peran di N_HRIS_Role_Hak */
    private function jalurHakPeran(User $u, string $halaman): array
    {
        if (! $this->modelBaruTersedia()) {
            return [];
        }

        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($j) {
                $j->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Role_Hak as h', function ($j) {
                $j->on('h.Id_Role', '=', 'rf.Id_Role')->where('h.Flag_Aktif', 'Y');
            })
            ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
            ->where('a.Nama_Aksi', 'VIEW')
            ->where('ru.UserId_Web', $u->Id_Users)
            ->where('ru.Flag_Aktif', 'Y')
            ->tap(fn ($q) => SkemaRbac::saringPenugasanAktif($q, 'ru.'))
            ->whereRaw(sprintf(self::NORMAL, 'h.Jenis_page').' = ?', [HibahTataKelola::normalisasi($halaman)])
            ->distinct()
            ->pluck('rf.Role_Code')
            ->map(fn ($k) => 'HAK_PERAN:'.$k)
            ->all();
    }
}
