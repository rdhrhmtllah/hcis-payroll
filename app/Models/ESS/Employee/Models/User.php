<?php

namespace App\Models\ESS\Employee\Models;

use App\Models\ESS\Organization\Division\Models\Division;
use App\Models\ESS\Organization\Division\Models\View_Divisi_Sub_Divisi;
use App\Models\ESS\Organization\Level\Models\Level;
use App\Models\ESS\Organization\Level\Models\View_Golongan_Sub_Golongan_Level_Jabatan;
use App\Models\ESS\Workflow\Review\Models\GlobalReviewDetail;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\UserFactory::new();
    }

    protected $table = 'KPI_Users';

    protected $primaryKey = 'Id_Users';

    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | Static Cache — Menu Access (hidup HANYA selama 1 HTTP request)
    |
    | Mencegah N+1 query saat sidebar memanggil memilikiJabatan() 30+ kali.
    | Satu query batch-load SEMUA page yang diizinkan, simpan di static cache.
    | Setiap pengecekan berikutnya hanya in_array lookup.
    |
    | Jika admin ubah akses di DB → request berikutnya langsung pakai data baru.
    |--------------------------------------------------------------------------
    */
    protected static array $menuAccessCache = []; // Sistem BARU

    protected static array $menuAccessLegacyCache = []; // Sistem LEGACY

    protected $fillable = ['Username', 'Password', 'Role', 'Email', 'Kode_Users', 'Address', 'Division_Id', 'Level_Id'];

    protected $hidden = ['Password', 'remember_token'];

    protected static function normalizePageKey(?string $page): string
    {
        return strtolower(str_replace(' ', '', trim((string) $page)));
    }

    public function getAuthPassword()
    {
        return $this->Password;
    }

    public function divisions(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'Division_Id', 'Id_Division');
    }

    public function levels(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'Level_Id', 'Id_Level');
    }

    public function reviewDetail(): BelongsTo
    {
        return $this->belongsTo(GlobalReviewDetail::class, 'Employee_Id', 'Id_Users');
    }

    public function karyawan()
    {
        // dd($this->memilikiAksesBaru('LMSAdminMonitoringPage'));
        return $this->belongsTo(Karyawan::class, 'Id_Users', 'UserID_Web');
    }

    /*
    |--------------------------------------------------------------------------
    | HYBRID MENU ACCESS CHECK
    |
    | memilikiJabatan() dipanggil oleh Gate 'akses-spesial' di AuthServiceProvider.
    | Digunakan oleh @can('akses-spesial', 'IzinPage') di seluruh sidebar.
    |
    | Sekarang HYBRID:
    | 1) Cek di sistem BARU dulu (N_HRIS_Role_User → N_HRIS_Grup_Fitur_Map)
    | 2) Jika tidak ditemukan → fallback ke LEGACY (HRIS_Page_Access)
    |
    | Kedua pengecekan menggunakan batch-load + static cache:
    | Satu query mengambil SEMUA page yang diizinkan user, disimpan statis.
    | Pengecekan berikutnya hanya in_array lookup — sangat cepat.
    |
    | SEBELUM refactor: 30+ sidebar items = 30+ query DB per page load
    | SESUDAH refactor: 30+ sidebar items = maks 2 query DB per page load
    |--------------------------------------------------------------------------
    */

    /**
     * HYBRID: Cek akses menu — sistem baru dulu, fallback ke legacy.
     * Dipanggil oleh Gate::define('akses-spesial') di AuthServiceProvider.
     * Method signature TIDAK berubah → zero breaking change.
     */
    public function memilikiJabatan(string $page): bool
    {
        // 1. Cek di sistem BARU
        if ($this->memilikiAksesBaru($page)) {
            return true;
        }

        // 2. Fallback ke LEGACY
        return $this->memilikiJabatanLegacy($page);
    }

    /**
     * CEK SISTEM BARU: Apakah user punya role di modul yang mengandung menu ini?
     *
     * Alur query (batch, 1x per request):
     * N_HRIS_Role_User (UserId_Web, Flag_Aktif=Y)
     *   → JOIN N_HRIS_Role_Functional (Flag_Aktif=Y)
     *     → JOIN N_HRIS_Grup_Fitur_Map (mengambil semua Jenis_page)
     *
     * Hasilnya: array semua Jenis_page yang user bisa akses via role baru.
     */
    // protected function memilikiAksesBaru(string $page): bool
    // {
    //     $userId = $this->Id_Users;

    //     if (!isset(static::$menuAccessCache[$userId])) {
    //         $roleMappedPages = DB::table('N_HRIS_Role_User as ru')
    //             ->join('N_HRIS_Role_Functional as rf', function ($join) {
    //                 $join->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
    //             })
    //             ->join('N_HRIS_Grup_Fitur_Map as gfm', 'gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
    //             ->where('ru.UserId_Web', $userId)
    //             ->where('ru.Flag_Aktif', 'Y')
    //             ->pluck('gfm.Jenis_page')
    //             ->map(fn($v) => self::normalizePageKey((string) $v))
    //             ->filter()
    //             ->unique()
    //             ->values()
    //             ->all();

    //         $hasAnyExplicitPageAccess = DB::table('HRIS_Page_Access as pa')->where('pa.UserID_Web', $userId)->exists();

    //         $explicitPageAccess = DB::table('HRIS_Page_Access as pa')
    //             ->where('pa.UserID_Web', $userId)
    //             ->where('pa.Flag_Aktif', 'Y')
    //             ->pluck('pa.Jenis_page')
    //             ->map(fn($v) => self::normalizePageKey((string) $v))
    //             ->filter()
    //             ->unique()
    //             ->values()
    //             ->all();

    //         // RULE:
    //         // 1) If user has explicit HRIS_Page_Access records, use them directly
    //         //    (they have been granted directly and should be respected)
    //         // 2) If user has NO explicit HRIS_Page_Access records, fall back to
    //         //    role-based access from N_HRIS_Role_User
    //         static::$menuAccessCache[$userId] = $hasAnyExplicitPageAccess ? $explicitPageAccess : $roleMappedPages;
    //     }

    //     return in_array(self::normalizePageKey($page), static::$menuAccessCache[$userId], true);
    // }

    protected function memilikiAksesBaru(string $page): bool
    {
        $userId = $this->Id_Users;

        if (! isset(static::$menuAccessCache[$userId])) {
            /*
             * SATU PERJALANAN, BUKAN TIGA — dan server yang membuang duplikatnya.
             *
             * Ketiga daftar ini digabung lalu di-`unique()` di PHP, sehingga setiap
             * baris kembar yang sempat melintasi jaringan dibayar dua kali (server
             * mengirim, klien menyerap) lalu dibuang. Terukur pada lima pengguna
             * nyata: 760 baris dikirim, 393 dipakai — 48% terbuang. Sumbernya bukan
             * baris kembar N_HRIS_Role_User (nol pasangan kembar di sana), melainkan
             * tumpang tindih ANTAR-daftar dan banyak role yang menunjuk halaman sama.
             *
             * Kenapa ini yang dipilih dan bukan sekadar `->distinct()` per daftar:
             * Fase 15 menempatkan ASYNC_NETWORK_IO — server sudah selesai, menunggu
             * klien menyerap hasil — sebagai biaya TERBESAR (33,5%). Jadi yang paling
             * menolong adalah mengurangi PERJALANAN sekaligus BARIS, bukan salah satu.
             *
             * UNION (bukan UNION ALL) mendedup di server. Normalisasi tetap dikerjakan
             * di PHP sesudahnya, jadi hasil akhirnya identik: dua nilai mentah berbeda
             * yang menormalkan ke kunci sama tetap dilipat oleh ->unique() di bawah.
             * Dijaga UserPageAccessSatuPerjalananTest yang membandingkan hasil bentuk
             * ini dengan bentuk tiga-kueri pada data nyata.
             */
            $roleMappedPages = DB::table('N_HRIS_Role_User as ru')
                ->join('N_HRIS_Role_Functional as rf', function ($join) {
                    $join->on('rf.Id_Role', '=', 'ru.Id_Role')->where('rf.Flag_Aktif', 'Y');
                })
                // Hanya baris yang MEMBERI HIBAH (DDL-2026-110).
                //
                // N_HRIS_Grup_Fitur_Map mengerjakan dua tugas: menyusun sidebar
                // DAN memberi hibah role. Selama keduanya satu baris, pemisahan
                // hak DI DALAM satu modul mustahil diungkapkan — ADMIN_PERMIT
                // dan ADMIN_TAMU menunjuk grup yang sama, sehingga keduanya
                // melihat seluruh halaman Visitor. Penyaring ini memisahkannya;
                // penyusun sidebar tetap membaca SELURUH baris.
                //
                // Penyaring kembarannya ada di AuthorizationScopeResolver::
                // getUsablePageTypes (penentu sidebar) dan wajib berubah bersamaan.
                ->join('N_HRIS_Grup_Fitur_Map as gfm', function ($join) {
                    $join->on('gfm.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
                        ->where('gfm.Flag_Hibah_Role', '=', 'Y');
                })
                ->where('ru.UserId_Web', $userId)
                ->where('ru.Flag_Aktif', 'Y')
                // RBAC Fase 3b: penugasan bertanggal yang sudah lewat tak memberi hak.
                ->when(\App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::punya('kedaluwarsa_penugasan'), fn ($q) => $q->where(fn ($w) => $w->whereNull('ru.Berlaku_Sampai')->orWhere('ru.Berlaku_Sampai', '>', now())))
                // RBAC Fase 0: halaman tata kelola hanya lewat role admin sah.
                // Kembarannya di AuthorizationScopeResolver::getUsablePageTypes.
                ->tap(fn ($q) => HibahTataKelola::saringHibah($q, 'gfm.Jenis_page', 'rf.Role_Code'))
                ->select('gfm.Jenis_page as Jenis_page');

            $explicitPageAccess = DB::table('HRIS_Page_Access as pa')
                ->where('pa.UserID_Web', $userId)
                ->where('pa.Flag_Aktif', 'Y')
                ->when(\App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::punya('kedaluwarsa_grant'), fn ($q) => $q->where(fn ($w) => $w->whereNull('pa.Berlaku_Sampai')->orWhere('pa.Berlaku_Sampai', '>', now())))
                ->select('pa.Jenis_page as Jenis_page');

            $globalPages = DB::table('N_HRIS_Menus')
                ->where('Is_Global_Menu', 'Y')
                ->select('Jenis_page as Jenis_page');

            $semuaHalaman = $roleMappedPages
                ->union($explicitPageAccess)
                ->union($globalPages)
                ->pluck('Jenis_page');

            static::$menuAccessCache[$userId] = $semuaHalaman
                ->map(fn ($v) => self::normalizePageKey((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return in_array(self::normalizePageKey($page), static::$menuAccessCache[$userId], true);
    }

    /**
     * CEK LEGACY: Cek via HRIS_Page_Access (tabel lama).
     *
     * Alur query (batch, 1x per request):
     * Karyawan → View_Divisi_Sub_Divisi (ID_Divisi)
     *          → View_Golongan (ID_Level)
     *            → HRIS_Page_Access (ID_Divisi + ID_Level + Flag_Aktif=Y)
     *
     * Hasilnya: array semua Jenis_page yang user bisa akses via legacy.
     */
    protected function memilikiJabatanLegacy(string $page): bool
    {
        $userId = $this->Id_Users;

        // if (!isset(static::$menuAccessLegacyCache[$userId])) {
        //     static::$menuAccessLegacyCache[$userId] = DB::table('karyawan as a')
        //         ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
        //         ->join('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
        //         ->join('HRIS_Page_Access as d', function ($join) {
        //             $join
        //                 ->on('b.ID_Divisi', '=', 'd.ID_Divisi')
        //                 ->on('c.ID_Level', '=', 'd.ID_Level')
        //                 ->where('d.Flag_Aktif', 'Y');
        //         })
        //         ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'd.Jenis_page')
        //         ->where(function ($query) use ($userId) {
        //             $query->where('a.UserID_Web', $userId)->orWhere('m.Is_Global_Menu', 'Y'); // Include records where Is_Global_Menu is 'Y' (general access)
        //         })
        //         ->pluck('d.Jenis_page')
        //         ->map(fn($v) => self::normalizePageKey((string) $v))
        //         ->filter()
        //         ->unique()
        //         ->values()
        //         ->all();
        // }

        if (! isset(static::$menuAccessLegacyCache[$userId])) {
            // 1. akses berdasarkan user
            $userAccess = DB::table('HRIS_Page_Access')
                ->where('UserID_Web', $userId)
                ->where('Flag_Aktif', 'Y')
                ->when(\App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::punya('kedaluwarsa_grant'), fn ($q) => $q->where(fn ($w) => $w->whereNull('Berlaku_Sampai')->orWhere('Berlaku_Sampai', '>', now())))
                ->pluck('Jenis_page');

            // dd($userAccess->toSql(), $userAccess->getBindings());
            // 2. global menu (optional)
            $globalAccess = DB::table('N_HRIS_Menus')->where('Is_Global_Menu', 'Y')->pluck('Jenis_page');

            static::$menuAccessLegacyCache[$userId] = $userAccess
                ->merge($globalAccess) // ✅ aman, tidak merusak query utama
                ->map(fn ($v) => self::normalizePageKey((string) $v))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return in_array(self::normalizePageKey($page), static::$menuAccessLegacyCache[$userId], true);
    }

    public function isSupervisor(): bool
    {
        // RBAC Fase 4: keputusan yang sama dengan gerbang route (ikut mode modul).
        return app(\App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses::class)->boleh($this, 'ShiftManagement');
    }

    public function openKPI(): bool
    {
        return false;
    }

    public function divisionKaryawan()
    {
        return $this->hasOneThrough(
            View_Divisi_Sub_Divisi::class,
            Karyawan::class,
            'UserID_Web',
            'ID_Divisi_Sub_Divisi',
            'Id_Users',
            'ID_DIVISI_SUB_DIVISI',
        );
    }

    public function JabatanKaryawan()
    {
        return $this->hasOneThrough(
            View_Golongan_Sub_Golongan_Level_Jabatan::class,
            Karyawan::class,
            'UserID_Web',
            'ID_Level_Jabatan',
            'Id_Users',
            'ID_Level_Jabatan',
        );
    }

    /**
     * Memeriksa wewenang kemampuan fitur bisnis pengguna (DDL-2026-194).
     * Contoh penggunaan: $user->bisa('ESS.CUTI.AJUKAN')
     */
    public function bisa(string $kodeKemampuan): bool
    {
        return app(\App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses::class)->bisa($this, $kodeKemampuan);
    }
}
