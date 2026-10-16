<?php

namespace App\Http\Middleware;

use App\Services\SYSTEM\Audit\Numbering\AdminLogNumberService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Audit ditulis di terminate() — setelah response terkirim ke user.
     *
     * Alasan aslinya sudah tidak berlaku: dulu penomoran memakai sp_getapplock
     * per-bulan, sehingga semua write admin satu bulan antre di satu lock
     * (timeout 10 detik) dan di handle() antrean itu menahan request user
     * beserta koneksi DB-nya. Penomoran kini lewat counter atomik per-prefix
     * (AdminLogNumberService), jadi antreannya tinggal satu baris counter.
     *
     * Tetap di terminate() karena alasan yang lebih sederhana dan masih benar:
     * menulis audit — satu header ditambah dua baris per karyawan sasaran —
     * bukan pekerjaan yang pantas ditunggu pengguna, dan kegagalannya tidak
     * boleh menggagalkan operasi bisnis yang sudah berhasil.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Audit only successful write operations.
        if (! $this->shouldAudit($request, $response)) {
            return;
        }

        try {
            $this->writeAudit($request, $response);
        } catch (\Throwable $e) {
            // Never block business flow when audit logging fails.
            report($e);
        }
    }

    private function shouldAudit(Request $request, Response $response): bool
    {
        if (! Auth::check()) {
            return false;
        }

        $method = strtoupper($request->method());
        if (! in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return false;
        }

        if (! $this->isMutationEndpoint($request, $method)) {
            return false;
        }

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            return false;
        }

        return true;
    }

    private function isMutationEndpoint(Request $request, string $method): bool
    {
        $path = '/'.ltrim(strtolower($request->path()), '/');

        // Explicitly exclude non-mutating endpoints that still use POST.
        $nonMutationPostPaths = ['/api/v1/system-auth/distribution/preview'];

        if ($method === 'POST' && in_array($path, $nonMutationPostPaths, true)) {
            return false;
        }

        $mutationRules = [
            ['methods' => ['POST'], 'path' => '/api/v1/admin/role-manager/permissions/'],
            ['methods' => ['POST'], 'path' => '/api/v1/admin/role-manager/user-roles/'],
            ['methods' => ['POST'], 'path' => '/api/v1/admin/role-manager/roles'],
            ['methods' => ['PUT', 'PATCH', 'DELETE'], 'path' => '/api/v1/admin/role-manager/roles/'],
            ['methods' => ['POST'], 'path' => '/api/v1/admin/role-manager/scopes'],
            ['methods' => ['PUT', 'PATCH', 'DELETE'], 'path' => '/api/v1/admin/role-manager/scopes/'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/grup-fitur'],
            ['methods' => ['PUT', 'PATCH', 'DELETE'], 'path' => '/api/v1/system-auth/grup-fitur/'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/menu-binding/'],
            ['methods' => ['POST'], 'path' => '/api/v1/menu-approval-matrix/menu-binding/'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/menu-capabilities/'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/distribution/execute'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/distribution/revoke'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/distribution/seed-existing'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/permission-matrix/toggle'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/permission-matrix/bulk-save'],
            ['methods' => ['POST'], 'path' => '/api/v1/system-auth/permission-matrix/reset-to-default'],
        ];

        foreach ($mutationRules as $rule) {
            if (! in_array($method, $rule['methods'], true)) {
                continue;
            }

            if ($path === $rule['path'] || str_starts_with($path, $rule['path'])) {
                return true;
            }
        }

        return false;
    }

    private function writeAudit(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        DB::transaction(function () use ($request, $response, $user) {
            $userCode = $this->resolveUserCode($user);
            $payload = $this->sanitizePayload($request->except(['_token', '_method']));
            $responseDetail = $this->responseDetail($response);
            $jenis = $this->resolveJenis($request);
            $targetKodeKaryawanList = $this->resolveTargetKodeKaryawanList($payload, $request, $userCode);
            $deskripsi = strtoupper($request->method()).' '.$request->path();

            /*
             * Satu pintu: AdminLogNumberService. Sampai perubahan ini,
             * middleware memuat SALINAN PERSIS metode itu, dengan komentar
             * di kedua sisi yang menyatakan keduanya "sengaja identik" —
             * komentar bukan penjaga. Nomornya diambil di dalam closure ini
             * supaya tiap percobaan ulang DB::transaction(..., 3) memperoleh
             * nomor baru, bukan mengulangi nomor yang sudah gagal.
             */
            $noTransaksi = app(AdminLogNumberService::class)->generate();
            $now = now();

            DB::table('HRIS_Transaksi_Log_Admin')->insert([
                'Kode_Perusahaan' => '001',
                'No_Transaksi' => $noTransaksi,
                'Tanggal' => $now,
                'Jam' => $now->format('H:i'),
                'UserID' => $userCode,
            ]);

            foreach ($targetKodeKaryawanList as $kodeKaryawanTujuan) {
                $urutDetail = DB::table('HRIS_Transaksi_Log_Admin_Detail')->insertGetId([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan' => $kodeKaryawanTujuan,
                    'Jenis' => $jenis,
                    'Deskripsi' => $deskripsi,
                ]);

                if (empty($payload)) {
                    DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                        'Kode_Perusahaan' => '001',
                        'No_Transaksi' => $noTransaksi,
                        'Field_Diubah' => 'request.'.$this->resolveDefaultTableByPath($request),
                        'Deskripsi' => $deskripsi,
                        'Nilai_Awal' => null,
                        'Nilai_Setelah' => '(no payload)',
                        'Urut_Detail' => $urutDetail,
                    ]);

                    continue;
                }

                $detEntry = $this->buildDetEntry($request, $payload, $responseDetail, $kodeKaryawanTujuan);
                DB::table('HRIS_Transaksi_Log_Admin_Det')->insert([
                    'Kode_Perusahaan' => '001',
                    'No_Transaksi' => $noTransaksi,
                    'Field_Diubah' => $detEntry['field'],
                    'Deskripsi' => $deskripsi,
                    'Nilai_Awal' => null,
                    'Nilai_Setelah' => $this->stringifyValue($detEntry['value']),
                    'Urut_Detail' => $urutDetail,
                ]);
            }
        }, 3);
    }

    private function buildDetEntry(
        Request $request,
        array $payload,
        array $responseDetail = [],
        ?string $targetKodeKaryawan = null,
    ): array {
        $path = strtolower($request->path());

        if (str_contains($path, '/menu-approval-matrix/menu-binding/')) {
            $change = $this->resolveMenuBindingChange($payload, $responseDetail, $targetKodeKaryawan);

            return [
                'field' => 'menu_access_change.HRIS_Page_Access',
                'value' => $change,
            ];
        }

        if (str_contains($path, '/admin/role-manager/user-roles/')) {
            return [
                'field' => 'Id_Role.N_HRIS_Role_User',
                'value' => array_values($payload['roleIds'] ?? []),
            ];
        }

        if (
            str_contains($path, '/system-auth/distribution/execute') ||
            str_contains($path, '/system-auth/distribution/revoke')
        ) {
            return [
                'field' => 'Jenis_page.HRIS_Page_Access',
                'value' => array_values($payload['pages'] ?? []),
            ];
        }

        if (str_contains($path, '/system-auth/permission-matrix/bulk-save')) {
            return [
                'field' => 'Id_Aksi.N_HRIS_Role_Menu_Access',
                'value' => array_values($payload['changes'] ?? []),
            ];
        }

        if (str_contains($path, '/system-auth/permission-matrix/toggle')) {
            return [
                'field' => 'Id_Aksi.N_HRIS_Role_Menu_Access',
                'value' => [
                    'id_page_access' => $payload['id_page_access'] ?? null,
                    'id_aksi' => $payload['id_aksi'] ?? null,
                    'flag_diizinkan' => $payload['flag_diizinkan'] ?? null,
                ],
            ];
        }

        $firstField = array_key_first($payload);
        if ($firstField !== null) {
            return [
                'field' => $this->formatFieldDiubah((string) $firstField, $request),
                'value' => $payload[$firstField],
            ];
        }

        return [
            'field' => 'request.'.$this->resolveDefaultTableByPath($request),
            'value' => '(no payload)',
        ];
    }

    private function responseDetail(Response $response): array
    {
        $decoded = json_decode((string) $response->getContent(), true);

        return is_array($decoded['detail'] ?? null) ? $decoded['detail'] : [];
    }

    private function resolveMenuBindingChange(
        array $payload,
        array $responseDetail,
        ?string $targetKodeKaryawan,
    ): array {
        if (array_key_exists('di_grant', $responseDetail) || array_key_exists('di_revoke', $responseDetail)) {
            return [
                'added' => array_values($responseDetail['di_grant'] ?? []),
                'removed' => array_values($responseDetail['di_revoke'] ?? []),
            ];
        }

        if (! empty($responseDetail['applied']) && $targetKodeKaryawan) {
            $targetUserId = DB::table('Karyawan')
                ->where('Kode_Karyawan', $targetKodeKaryawan)
                ->value('UserID_Web');

            foreach ($responseDetail['applied'] as $applied) {
                if ((string) ($applied['user_id'] ?? '') === (string) $targetUserId) {
                    return [
                        'added' => array_values($applied['added'] ?? []),
                        'removed' => array_values($applied['removed'] ?? []),
                    ];
                }
            }
        }

        return [
            'added' => array_values($payload['pages'] ?? []),
            'removed' => [],
            'detail_unavailable' => true,
        ];
    }

    private function resolveUserCode($user): string
    {
        if (isset($user->karyawan) && $user->karyawan && ! empty($user->karyawan->Kode_Karyawan)) {
            return (string) $user->karyawan->Kode_Karyawan;
        }

        if (! empty($user->Kode_Users)) {
            return (string) $user->Kode_Users;
        }

        if (! empty($user->Username)) {
            return (string) $user->Username;
        }

        return (string) ($user->Id_Users ?? ($user->id ?? 'SYSTEM'));
    }

    private function resolveTargetKodeKaryawanList(array $payload, Request $request, string $fallback): array
    {
        $path = strtolower($request->path());

        // For user role assignment endpoint, target must be the route user only.
        if (str_contains($path, '/admin/role-manager/user-roles/')) {
            $routeUserId = $request->route('userId');
            if ($routeUserId !== null && preg_match('/^\d+$/', (string) $routeUserId)) {
                $mapped = DB::table('Karyawan')->where('UserID_Web', (int) $routeUserId)->value('Kode_Karyawan');

                if (! empty($mapped)) {
                    return [trim((string) $mapped)];
                }
            }

            return [$fallback];
        }

        $candidateKeys = [
            'Kode_Karyawan',
            'kode_karyawan',
            'Kode_Karyawan_Requester',
            'Requester',
            'requester',
            'oldApprover',
            'newApprover',
            'UserID_Web',
            'user_id',
            'userId',
            'user_ids',
            'userIds',
            'target_user_ids',
        ];

        $rawValues = [];

        foreach ($candidateKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $rawValues = array_merge($rawValues, $this->extractScalarValues($payload[$key]));
        }

        $routeUserId = $request->route('userId');
        if ($routeUserId !== null) {
            $rawValues[] = $routeUserId;
        }

        $rawValues = array_values(
            array_filter(array_map(fn ($v) => trim((string) $v), $rawValues), fn ($v) => $v !== ''),
        );

        if (empty($rawValues)) {
            return [$fallback];
        }

        $kodeKaryawan = [];
        $numericUserIds = [];

        foreach ($rawValues as $value) {
            if (preg_match('/^\d+$/', $value)) {
                $numericUserIds[] = (int) $value;

                continue;
            }

            $kodeKaryawan[] = $value;
        }

        if (! empty($numericUserIds)) {
            $mappedKode = DB::table('Karyawan')
                ->whereIn('UserID_Web', $numericUserIds)
                ->pluck('Kode_Karyawan')
                ->map(fn ($v) => trim((string) $v))
                ->filter(fn ($v) => $v !== '')
                ->values()
                ->all();

            $kodeKaryawan = array_merge($kodeKaryawan, $mappedKode);
        }

        $kodeKaryawan = array_values(array_unique($kodeKaryawan));

        return ! empty($kodeKaryawan) ? $kodeKaryawan : [$fallback];
    }

    private function extractScalarValues($value): array
    {
        if (is_scalar($value) || $value === null) {
            if ($value === null) {
                return [];
            }

            $stringValue = (string) $value;
            if (str_contains($stringValue, ',')) {
                return array_map('trim', explode(',', $stringValue));
            }

            return [trim($stringValue)];
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $item) {
                $result = array_merge($result, $this->extractScalarValues($item));
            }

            return $result;
        }

        return [];
    }

    private function formatFieldDiubah(string $field, Request $request): string
    {
        $map = [
            'roleIds' => 'Id_Role.N_HRIS_Role_User',
            'user_ids' => 'UserID_Web.HRIS_Page_Access',
            'userIds' => 'UserID_Web.HRIS_Page_Access',
            'userId' => 'UserID_Web.HRIS_Page_Access',
            'UserID_Web' => 'UserID_Web.HRIS_Page_Access',
            'pages' => 'Jenis_page.HRIS_Page_Access',
            'pageFilter' => 'Jenis_page.HRIS_Page_Access',
            'changes' => 'Id_Page_Access.N_HRIS_Role_Menu_Access',
            'capabilities' => 'Id_Aksi.N_HRIS_Menu_Capabilities',
            'Kode_Karyawan' => 'Kode_Karyawan.Karyawan',
            'Requester' => 'Kode_Karyawan_Requester.HRIS_Approval_Flow',
            'requester' => 'Kode_Karyawan_Requester.HRIS_Approval_Flow',
            'oldApprover' => 'Kode_Karyawan.HRIS_Approval_Flow',
            'newApprover' => 'Kode_Karyawan.HRIS_Approval_Flow',
        ];

        if (isset($map[$field])) {
            return $map[$field];
        }

        return $field.'.'.$this->resolveDefaultTableByPath($request);
    }

    private function resolveDefaultTableByPath(Request $request): string
    {
        $path = strtolower($request->path());

        if (str_contains($path, '/menu-capabilities')) {
            return 'N_HRIS_Menu_Capabilities';
        }

        if (str_contains($path, '/menu-approval-matrix/menu-binding')) {
            return 'HRIS_Page_Access';
        }

        if (str_contains($path, '/menu-binding')) {
            return 'N_HRIS_Grup_Fitur_Map';
        }

        if (str_contains($path, '/grup-fitur')) {
            return 'N_HRIS_Master_Grup_Fitur';
        }

        if (str_contains($path, '/permission-matrix')) {
            return 'N_HRIS_Role_Menu_Access';
        }

        if (str_contains($path, '/distribution')) {
            return 'HRIS_Page_Access';
        }

        if (str_contains($path, '/user-roles')) {
            return 'N_HRIS_Role_User';
        }

        if (str_contains($path, '/roles')) {
            return 'N_HRIS_Role_Functional';
        }

        if (str_contains($path, '/scopes')) {
            return 'N_HRIS_Role_Scope';
        }

        return 'Unknown_Table';
    }

    private function resolveJenis(Request $request): string
    {
        $path = strtolower($request->path());

        if (str_contains($path, '/system-auth/')) {
            return 'SystemAuth';
        }

        if (str_contains($path, '/admin/role-manager')) {
            return 'RoleManager';
        }

        return 'AdminAction';
    }

    private function sanitizePayload(array $payload): array
    {
        $maskedKeys = ['password', 'new_password', 'old_password'];

        array_walk_recursive($payload, function (&$value, $key) use ($maskedKeys) {
            if (in_array(strtolower((string) $key), $maskedKeys, true)) {
                $value = '***masked***';
            }
        });

        return $payload;
    }

    private function stringifyValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[unserializable]';
    }
}
