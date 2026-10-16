<?php

namespace App\Http\Controllers\ESS\Employee\Profile;

use App\Helpers\StatusKepegawaianHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class userController extends Controller
{
    private const PROFILE_PHOTO_TABLE = 'N_HRIS_Profile_Photos';

    public function index()
    {
        $authUser = Auth::user();
        $karyawan = $authUser->karyawan;
        $Kode_KaryawanReal = $karyawan->Kode_Karyawan;

        $nama_anggota = DB::table('HRIS_Karyawan_Team as t')
            ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->where('k.Aktif', 'Y')
            ->where('t.Kode_Karyawan_Team', $Kode_KaryawanReal)
            ->select('k.Nama', 'k.HP', 'd.nama_divisi as divisi')
            ->groupBy('k.HP', 'd.nama_divisi ', 'k.Nama')
            ->get();

        $nama_tergabung = DB::table('HRIS_Karyawan_Team as t')
            ->join('Karyawan as k', 't.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->where('t.Kode_Karyawan', $Kode_KaryawanReal)
            ->where('k.Aktif', 'Y')
            ->select('k.Nama', 'k.HP', 'd.nama_divisi as divisi')
            ->groupBy('k.Nama', 'k.HP', 'd.nama_divisi')
            ->where('t.Kode_Karyawan_Team', '<>', $Kode_KaryawanReal)
            ->where('t.Kode_Karyawan_Team', '<>', 'RIDHO RAHMAT')
            ->get();

        $nama_Approver = DB::table('HRIS_Approval_Flow as t')
            ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('t.Kode_Karyawan_Requester', $Kode_KaryawanReal)
            ->select('k.Nama', 't.order_flow')
            ->groupBy('k.Nama', 't.order_flow')
            ->where('t.Kode_Karyawan', '<>', $Kode_KaryawanReal)
            ->whereNull('t.Status')
            ->get();

        $nama_mengapprove = DB::table('HRIS_Approval_Flow as t')
            ->join('Karyawan as k', 't.Kode_Karyawan_Requester', '=', 'k.Kode_Karyawan')
            ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
            ->where('k.Aktif', 'Y')
            ->where('t.Kode_Karyawan', $Kode_KaryawanReal)
            ->select('k.Nama', 'd.nama_divisi as divisi')
            ->groupBy('k.Nama', 'd.nama_divisi')
            ->where('t.Kode_Karyawan_Requester', '<>', $Kode_KaryawanReal)
            ->get();

        // Label status kepegawaian HANYA boleh berasal dari StatusKepegawaianHelper.
        // Jangan menyalin logikanya ke sini atau ke Vue: sebelum ini kedua tempat
        // punya rantai if-else sendiri dan sudah menyimpang (PKWTT terbaca 'Probation'
        // padahal master aturan cuti menyebutnya Tetap).
        $employeeType = StatusKepegawaianHelper::labelUntuk($karyawan->status_kontrak ?? null);

        $position = [
            'jabatan' => $karyawan->level->nama_jabatan ?? '-',
            'level' => $karyawan->level->nama_level ?? '-',
            'lokasi' => $karyawan->Cabang_Untuk_Gaji.' - '.($karyawan->ID_Perusahaan ?? '-') ?? '-',
            'lokasiSingkat' => $karyawan->Cabang_Untuk_Gaji ?? '-',
            'areaBranch' => $karyawan->ID_Perusahaan ?? '-',
            'jamKerja' => '--:-- - --:-- WIB',
            'tipe' => $employeeType,
            'golongan' => $karyawan->level->nama_golongan ?? '-',
            'sub_golongan' => $karyawan->level->sub_nama_golongan ?? '-',
            'status_kontrak' => $employeeType,
        ];

        return inertia('ESS/Employee/Profile/userProfile', [
            'user' => $karyawan,
            'divisi' => $karyawan->division->nama_divisi,
            'dapartmen' => $karyawan->level->nama_sub_divisi,
            'jabatan' => $karyawan->level->nama_jabatan,
            'level' => $karyawan->level->nama_level,
            'position' => $position,
            'nama_anggota' => $nama_anggota,
            'nama_tergabung' => $nama_tergabung,
            'nama_Approver' => $nama_Approver,
            'nama_mengapprove' => $nama_mengapprove,
            'profilePhoto' => $this->resolveProfilePhoto($Kode_KaryawanReal),
            'profileBannerUrl' => asset('img/prod.webp'),
        ]);
    }

    public function uploadProfilePhoto(Request $request)
    {
        $authUser = Auth::user();
        $karyawan = $authUser?->karyawan;
        $kodeKaryawan = $karyawan?->Kode_Karyawan;

        if (! $authUser || ! $kodeKaryawan) {
            return response()->json(
                [
                    'message' => 'Data user tidak ditemukan.',
                ],
                422,
            );
        }

        $validated = $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'full' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'sidebar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:1024'],
            'original' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
            'face_orientation' => ['nullable', 'in:left,center,right,unknown'],
            'placement' => ['nullable', 'in:left,center,right'],
        ]);

        $storedPaths = [];

        try {
            $uploadedAt = now();
            $baseName = (string) Str::uuid();
            $folderPath = $this->buildProfilePhotoFolderPath($kodeKaryawan, $uploadedAt, $baseName);

            $avatarPath = $this->storeProfilePhotoFile($request, 'avatar', $folderPath, 'avatar');
            $storedPaths[] = $avatarPath;

            $fullPath = $this->storeProfilePhotoFile($request, 'full', $folderPath, 'full');
            $storedPaths[] = $fullPath;

            $hasSidebarPathColumn = $this->profilePhotoTableHasColumn('Sidebar_Path');
            $sidebarPath = $avatarPath;
            if ($hasSidebarPathColumn && $request->hasFile('sidebar')) {
                $sidebarPath = $this->storeProfilePhotoFile($request, 'sidebar', $folderPath, 'sidebar');
                $storedPaths[] = $sidebarPath;
            }

            $originalPath = null;
            if ($request->hasFile('original')) {
                $originalPath = $this->storeProfilePhotoFile($request, 'original', $folderPath, 'original');
                $storedPaths[] = $originalPath;
            }

            DB::beginTransaction();

            $oldPhotos = DB::table(self::PROFILE_PHOTO_TABLE)
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->get();

            DB::table(self::PROFILE_PHOTO_TABLE)
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'T',
                    'Deleted_At' => now(),
                    'Updated_At' => now(),
                ]);

            $insertPayload = [
                'Kode_Karyawan' => $kodeKaryawan,
                'Avatar_Path' => $avatarPath,
                'Full_Path' => $fullPath,
                'Original_Path' => $originalPath,
                'Original_Mime' => $request->file('original')?->getMimeType() ?: $request->file('full')->getMimeType(),
                'Face_Orientation' => $validated['face_orientation'] ?? 'unknown',
                'Placement' => $validated['placement'] ?? 'right',
                'Flag_Aktif' => 'Y',
                'Uploaded_By_UserID' => $authUser->Id_Users,
                'Created_At' => now(),
                'Updated_At' => now(),
            ];

            if ($hasSidebarPathColumn) {
                $insertPayload['Sidebar_Path'] = $sidebarPath;
            }

            DB::table(self::PROFILE_PHOTO_TABLE)->insert($insertPayload);

            DB::commit();

            Cache::forget("profile-photo-sidebar:{$kodeKaryawan}");
            $this->deleteInactiveProfilePhotoFiles($oldPhotos);

            return response()->json([
                'message' => 'Foto profil berhasil disimpan.',
                'profile_photo' => $this->resolveProfilePhoto($kodeKaryawan),
            ]);
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            foreach ($storedPaths as $path) {
                if ($path && Storage::disk('gcs')->exists($path)) {
                    Storage::disk('gcs')->delete($path);
                }
            }

            Log::error('Gagal upload foto profil user.', [
                'kode_karyawan' => $kodeKaryawan,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(
                [
                    'message' => 'Gagal menyimpan foto profil. Silakan coba lagi.',
                ],
                500,
            );
        }
    }

    private function storeProfilePhotoFile(
        Request $request,
        string $field,
        string $folderPath,
        string $fileNameWithoutExt,
    ): string {
        $file = $request->file($field);
        $extension = strtolower($file->extension() ?: $file->getClientOriginalExtension() ?: 'png');
        $fileName = $fileNameWithoutExt.'.'.$extension;
        $targetPath = $folderPath.'/'.$fileName;

        try {
            $storedPath = $file->storeAs($folderPath, $fileName, 'gcs');
        } catch (\ValueError $e) {
            if (stripos($e->getMessage(), 'Path cannot be empty') === false) {
                throw $e;
            }

            $stream = fopen($file->getRealPath() ?: $file->getPathname(), 'rb');
            if ($stream === false) {
                throw new \RuntimeException('Gagal membaca file temporary foto profil.');
            }

            try {
                $stored = Storage::disk('gcs')->put($targetPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $stored) {
                throw new \RuntimeException('Gagal upload foto profil ke GCS.');
            }

            $storedPath = $targetPath;
        }

        if (! is_string($storedPath) || trim($storedPath) === '') {
            throw new \RuntimeException('Path upload foto profil tidak valid.');
        }

        return trim($storedPath);
    }

    private function buildProfilePhotoFolderPath(
        string $kodeKaryawan,
        \Carbon\CarbonInterface $uploadedAt,
        string $uploadId,
    ): string {
        return implode('/', [
            'profile-photos',
            $uploadedAt->format('Y'),
            $uploadedAt->format('m'),
            $this->normalizeProfilePhotoPathSegment($kodeKaryawan),
            $uploadId,
        ]);
    }

    private function normalizeProfilePhotoPathSegment(string $value): string
    {
        $segment = preg_replace('/[^A-Za-z0-9._-]+/', '-', trim($value));
        $segment = trim((string) $segment, '.-_');

        return $segment !== '' ? $segment : 'unknown';
    }

    private function profilePhotoTableHasColumn(string $column): bool
    {
        static $columns = [];

        if (! array_key_exists($column, $columns)) {
            try {
                $columns[$column] = SkemaTersedia::kolom(self::PROFILE_PHOTO_TABLE, $column);
            } catch (\Throwable $e) {
                $columns[$column] = false;
            }
        }

        return $columns[$column];
    }

    private function deleteInactiveProfilePhotoFiles($photos): void
    {
        foreach ($photos as $photo) {
            $paths = [
                $photo->Avatar_Path ?? null,
                $photo->Full_Path ?? null,
                $photo->Original_Path ?? null,
                $photo->Sidebar_Path ?? null,
            ];

            foreach (array_unique(array_filter($paths)) as $path) {
                try {
                    if (Storage::disk('gcs')->exists($path)) {
                        Storage::disk('gcs')->delete($path);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Gagal menghapus snapshot foto profil lama.', [
                        'path' => $path,
                        'message' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    private function resolveProfilePhoto(?string $kodeKaryawan): ?array
    {
        if (! $kodeKaryawan) {
            return null;
        }

        try {
            $photo = DB::table(self::PROFILE_PHOTO_TABLE)
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->orderByDesc('Updated_At')
                ->orderByDesc('Id')
                ->first();
        } catch (\Throwable $e) {
            Log::warning('Gagal membaca foto profil aktif.', [
                'kode_karyawan' => $kodeKaryawan,
                'message' => $e->getMessage(),
            ]);

            return null;
        }

        if (! $photo) {
            return null;
        }

        return [
            'id' => $photo->Id,
            'avatar_path' => $photo->Avatar_Path,
            'full_path' => $photo->Full_Path,
            'sidebar_path' => $photo->Sidebar_Path ?? $photo->Avatar_Path,
            'original_path' => $photo->Original_Path,
            'avatar_url' => $this->temporaryGcsUrl($photo->Avatar_Path),
            'full_url' => $this->temporaryGcsUrl($photo->Full_Path),
            'sidebar_url' => $this->temporaryGcsUrl($photo->Sidebar_Path ?? $photo->Avatar_Path),
            'face_orientation' => $photo->Face_Orientation,
            'placement' => $photo->Placement,
            'updated_at' => $photo->Updated_At,
        ];
    }

    private function temporaryGcsUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            Log::warning('Gagal membuat temporary URL foto profil.', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
