<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Vinkla\Hashids\Facades\Hashids;

class CheckOfficialApiKey
{
    public function handle(Request $request, Closure $next, ?string $pageName = null)
    {
        $apiKey = $request->header('X-App-ID');
        $publicKey = $request->header('X-HC-Public');
        $kodeKaryawan = $request->header('X-Kode-Karyawan');

        if (! $apiKey || ! $kodeKaryawan) {
            return ResponseHelper::error('Akses ditolak: Identitas tidak lengkap', 401);
        }

        // ========================================================================
        // 1. CEK WHITELIST ENDPOINT APLIKASI DI DATABASE
        // ========================================================================
        // Ambil pattern URI dari route, tambahkan '/' di awal agar sesuai format DB
        // Contoh output: /api/v1/penjadwalan/training/update/{Id_Ujian_Token}
        $routeUri = '/'.ltrim($request->route()->uri(), '/');
        $method = $request->method();

        $isWhitelisted = DB::table('N_HRIS_KANDIDAT_Akses_Endpoint as ae')
            ->join('N_HRIS_KANDIDAT_Api_Aplikasi as app', 'app.Id_Api_Aplikasi', '=', 'ae.Id_Api_Aplikasi')
            ->join('N_HRIS_KANDIDAT_Endpoint_Api as ep', 'ep.Id_Endpoint_Api', '=', 'ae.Id_Endpoint_Api')
            ->where('app.Api_Public', $publicKey)
            ->where('ep.Url_Route', $routeUri)
            ->where('ep.Method', $method)
            ->where('app.Flag_Aktif', 'Y')
            ->where('ep.Flag_Aktif', 'Y')
            ->where('ae.Flag_Aktif', 'Y')
            ->exists();

        // Jika endpoint atau method tidak terdaftar untuk App Key ini, langsung tolak
        if (! $isWhitelisted) {
            Log::warning('UNAUTHORIZED_APP_ENDPOINT', [
                'ip' => $request->ip(),
                'api_key' => $publicKey,
                'route' => $routeUri,
                'method' => $method,
            ]);

            return ResponseHelper::error(
                'Akses ditolak: Aplikasi tidak memiliki izin untuk mengakses endpoint ini',
                403,
            );
        }

        // ========================================================================
        // 2. CEK KREDENSIAL USER (KODE KARYAWAN)
        // ========================================================================
        $user = DB::table('HRIS_KANDIDAT_Users')
            ->where('User_Key', $apiKey)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Akun_Terintegrasi', 'Y')
            ->where('Status', 'aktif')
            ->first();

        if (! $user) {
            Log::warning('POTENTIAL_API_SPOOFING', [
                'ip' => $request->ip(),
                'api_key' => $apiKey,
                'kode_karyawan' => $kodeKaryawan,
                'target_page' => $pageName,
            ]);

            return ResponseHelper::error('Akses ditolak: Kredensial tidak valid', 401);
        }

        // ========================================================================
        // 3. AMBIL HAK AKSES PAGE & MENU USER
        // ========================================================================
        $queryAccess = DB::table('HRIS_Page_Access_2 as pa')
            ->join('HRIS_Role_Menu_Access as ra', 'ra.Id_Page_Access', '=', 'pa.Id_Page_Access')
            ->join('HRIS_Klasifikasi_Aksi as ka', 'ka.Id_Klasifikasi_Actions', '=', 'ra.Id_Aksi')
            ->leftJoin('HRIS_KANDIDAT_Menus as km', 'km.Jenis_page', '=', 'pa.Jenis_page')
            ->where('pa.UserID_Web', $user->Id_User)
            ->where('ra.Flag_Diizinkan', 'Y')
            ->select(
                'pa.Id_Page_Access',
                'pa.Jenis_page',
                'ka.Nama_Aksi',
                'km.Nama_Menu',
                'km.Header_Label',
                'km.Icon_Menu',
                'km.Url_Menu',
            )
            ->orderBy('pa.Urutan_Menu', 'ASC');

        if ($pageName) {
            $queryAccess->where('pa.Jenis_page', $pageName);
        }

        $userAccess = $queryAccess->get();

        $permissions = [];
        $permissionLabels = [];
        $pageAccessIds = [];

        foreach ($userAccess as $access) {
            $page = $access->Jenis_page;

            if (! isset($permissions[$page])) {
                $permissions[$page] = [];
            }
            if (! in_array($access->Nama_Aksi, $permissions[$page])) {
                $permissions[$page][] = $access->Nama_Aksi;
            }
            if (! isset($permissionLabels[$page])) {
                $permissionLabels[$page] = [
                    'nama_menu' => $access->Nama_Menu,
                    'Header_Label' => $access->Header_Label,
                    'icon' => $access->Icon_Menu,
                    'url' => $access->Url_Menu,
                ];
            }

            if (! in_array($access->Id_Page_Access, $pageAccessIds)) {
                $pageAccessIds[] = $access->Id_Page_Access;
            }
        }

        // ========================================================================
        // 4. AMBIL HAK AKSES KONTEN USER
        // ========================================================================
        $permissionKonten = [];

        if (! empty($pageAccessIds)) {
            $userKontenAccess = DB::table('HRIS_Role_Konten_Access as rka')
                ->join('HRIS_Page_Access_2 as pa', 'rka.Id_Page_Access', '=', 'pa.Id_Page_Access')
                ->leftJoin(
                    'HRIS_KANDIDAT_Jenis_Indikator as ji',
                    'rka.Id_Jenis_Indikator',
                    '=',
                    'ji.Id_Jenis_Indikator',
                )
                ->leftJoin('HRIS_KANDIDAT_Jenis_Soal as js', 'rka.Id_Jenis_Soal', '=', 'js.Id_Jenis_Soal')
                ->whereIn('rka.Id_Page_Access', $pageAccessIds)
                ->where('rka.Flag_Diizinkan', 'Y')
                ->select(
                    'pa.Jenis_page',
                    'rka.Id_Jenis_Indikator',
                    'rka.Id_Jenis_Soal',
                    'rka.Kategori',
                    'rka.Flag_Diizinkan',
                    'ji.Kode_Hak_Akses',
                    'ji.Nama_Indikator',
                    'js.Jenis_Soal',
                )
                ->get();

            foreach ($userKontenAccess as $konten) {
                $page = $konten->Jenis_page;

                if (! isset($permissionKonten[$page])) {
                    $permissionKonten[$page] = [];
                }
                if ($konten->Id_Jenis_Soal) {
                    $permissionKonten[$page][] = [
                        'id_jenis_soal' => $konten->Id_Jenis_Soal,
                        'jenis_soal' => $konten->Jenis_Soal,
                        'flag' => $konten->Flag_Diizinkan,
                    ];
                } elseif ($konten->Kategori) {
                    $permissionKonten[$page][] = [
                        'kategori' => $konten->Kategori,
                        'flag' => $konten->Flag_Diizinkan,
                    ];
                } else {
                    $permissionKonten[$page][] = [
                        'id_jenis_indikator' => $konten->Id_Jenis_Indikator,
                        'nama_indikator' => $konten->Nama_Indikator,
                        'kode_hak_akses' => $konten->Kode_Hak_Akses,
                        'flag' => $konten->Flag_Diizinkan,
                    ];
                }
            }
        }

        // ========================================================================
        // 5. SIMPAN KE SESSION
        // ========================================================================
        Session::put('cat-evo-admin', [
            'id' => $user->Id_User,
            'id_hash' => Hashids::connection('custom')->encode($user->Id_User),
            'username' => $user->Username,
            'nama' => $user->Nama ?? null,
            'permissions' => $permissions,
            'permission_label' => $permissionLabels,
            'permission_konten' => $permissionKonten,
        ]);

        return $next($request);
    }
}
