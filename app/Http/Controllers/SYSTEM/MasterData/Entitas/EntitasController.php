<?php

namespace App\Http\Controllers\SYSTEM\MasterData\Entitas;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Entitas Perusahaan — pengelolaan, khusus System Admin.
 *
 * GERBANGNYA `system.admin`, BUKAN `check.jabatan`. Itu mengikuti preseden
 * routes/SYSTEM/DatabaseChanges.php, dan alasannya nyata: grup fitur SYSTEM
 * (Id_Grup_Fitur = 5) tidak dipegang satu pun role di N_HRIS_Role_Functional,
 * dan HRIS_Page_Access tak punya baris untuk halaman SYSTEM mana pun. Di repo
 * ini "System Admin" bukan role, melainkan flag KPI_Users.Is_System_Admin.
 * Memakai check.jabatan berarti memasang gerbang yang tak seorang pun bisa
 * lewati — atau, kalau ditambal dengan grant, membuka master lintas modul ke
 * pemegang menu modul tertentu.
 *
 * Daftar entitas dibaca modul lain lewat endpoint bergerbang masing-masing
 * (mis. /api/v1/sop/admin/master-data/entitas). Yang ada di sini hanya
 * pengelolaannya.
 */
class EntitasController extends Controller
{
    public function __construct(private EntitasService $service) {}

    private function decode($id): ?int
    {
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    /** GET /system/master-data/entitas */
    public function page()
    {
        return Inertia::render('SYSTEM/MasterData/Entitas/EntitasPage');
    }

    /** GET /system/master-data/entitas/data */
    public function index(Request $request)
    {
        try {
            $limit = min(100, max(5, (int) $request->query('limit', 25)));
            $page = max(1, (int) $request->query('page', 1));

            [$rows, $total] = $this->service->paginate($request->query('q'), $page, $limit);

            return ResponseHelper::successWithPagination($rows, $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /system/master-data/entitas/options — kandidat induk untuk form. */
    public function options()
    {
        try {
            return ResponseHelper::success($this->service->opsi(), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /system/master-data/entitas/pohon — struktur grup untuk org chart. */
    public function pohon()
    {
        try {
            return ResponseHelper::success($this->service->pohon(), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /system/master-data/entitas/store */
    public function store(Request $request)
    {
        $data = $this->validasi($request);

        DB::beginTransaction();
        try {
            $id = $this->service->create($data, Auth::user()->Id_Users);
            DB::commit();

            return ResponseHelper::success(
                ['Id_Entitas' => Hashids::connection('custom')->encode($id)],
                'Entitas berhasil disimpan!',
                201,
            );
        } catch (\DomainException $e) {
            DB::rollBack();

            // Kode bentrok atau induk melingkar — pesannya sudah untuk admin.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /** PUT /system/master-data/entitas/update/{id} */
    public function update(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $this->validasi($request, $decoded);

        DB::beginTransaction();
        try {
            if (! $this->service->update($decoded, $data, Auth::user()->Id_Users)) {
                DB::rollBack();

                return ResponseHelper::error('Entitas tidak ditemukan', 404);
            }
            DB::commit();

            return ResponseHelper::success(null, 'Entitas berhasil diperbarui!');
        } catch (\DomainException $e) {
            DB::rollBack();

            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /** DELETE /system/master-data/entitas/delete/{id} (soft delete) */
    public function destroy($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        DB::beginTransaction();
        try {
            $penghalang = $this->service->softDelete($decoded, Auth::user()->Id_Users);
            if ($penghalang > 0) {
                DB::rollBack();

                return ResponseHelper::error(
                    "Entitas ini masih dipakai {$penghalang} dokumen atau entitas anak. "
                    .'Pindahkan dulu pemakainya sebelum menonaktifkan.',
                    422,
                );
            }
            DB::commit();

            return ResponseHelper::success(null, 'Entitas berhasil dinonaktifkan!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Bentuk data entitas.
     *
     * `Kode_Entitas` tetap divalidasi saat update supaya keunikannya diperiksa,
     * tetapi service sengaja TIDAK menuliskannya — kode terkunci setelah dibuat.
     */
    private function validasi(Request $request, ?int $idSendiri = null): array
    {
        $data = $request->validate([
            'Kode_Entitas' => 'required|string|max:10|regex:/^[A-Za-z0-9_]+$/',
            /* Kode yang DICETAK ketika entitas ini berperan sebagai pemilik
               (DDL-2026-056). Kosong berarti "pakai Kode_Entitas" — itulah
               keadaan tiga dari empat entitas, dan mengharuskan pengisiannya
               hanya akan melahirkan salinan yang bisa menyimpang. */
            'Kode_Sebagai_Pemilik' => 'nullable|string|max:10|regex:/^[A-Za-z0-9_]+$/',
            'Nama_Entitas' => 'required|string|max:150',
            'Nama_Singkat' => 'nullable|string|max:50',
            /* Hex enam digit. Kosong dibiarkan: layar jatuh ke kategoriTheme()
               bila warna merek belum diketahui. */
            'Warna' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'Induk_Entitas_Id' => 'nullable|string',
            'Urutan' => 'nullable|integer|min:0|max:999',
            /* Data URL hasil unggah ATAU URL/path yang sudah ada. Batas 2 MB
               dihitung dari panjang string base64 (~1,37x ukuran berkas), bukan
               dari berkas yang tak pernah menyentuh disk. */
            'Logo' => 'nullable|string|max:2800000',
        ], [
            'Kode_Entitas.regex' => 'Kode entitas hanya boleh huruf, angka, dan garis bawah — ia rujukan tetap bagi seed dan integrasi.',
            'Kode_Sebagai_Pemilik.regex' => 'Kode pemilik hanya boleh huruf, angka, dan garis bawah — ia tercetak di nomor dokumen resmi.',
            'Warna.regex' => 'Warna harus hex enam digit, misalnya #1D4ED8.',
        ]);

        $data['Induk_Entitas_Id'] = filled($data['Induk_Entitas_Id'] ?? null)
            ? $this->decode($data['Induk_Entitas_Id'])
            : null;

        $data['Logo_Path'] = $this->simpanLogo($data['Logo'] ?? null, $data['Kode_Entitas']);
        unset($data['Logo']);

        return $data;
    }

    /**
     * Simpan logo, atau teruskan rujukan yang sudah ada.
     *
     * MENERIMA DUA BENTUK, dan itu disengaja. Data URL base64 diunggah ke GCS;
     * apa pun selain itu (URL http/https atau path publik) disimpan apa adanya.
     * Polanya menyalin SopAdminBuilderController::resolveCover(), bukan
     * menciptakan jalur unggah kedua dengan aturan sendiri.
     *
     * Dua bentuk dalam satu kolom membuat entitas tetap bisa berlogo di
     * environment yang kredensial GCS-nya belum siap — nyata di server dev ini,
     * yang path kuncinya bergaya Windows dijalankan di Linux.
     */
    private function simpanLogo(?string $logo, string $kode): ?string
    {
        if (! is_string($logo) || $logo === '') {
            return null;
        }

        if (! preg_match('/^data:image\/(\w+);base64,/', $logo, $m)) {
            return $logo; // sudah berupa URL atau path
        }

        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $binary = base64_decode(substr($logo, strpos($logo, ',') + 1)) ?: '';
        if ($binary === '') {
            return null;
        }

        $path = 'system/entitas-logo/'.now()->format('Y/m').'/'
            .Str::slug($kode).'-'.Str::uuid().'.'.$ext;

        Storage::disk('gcs')->put($path, $binary);

        return $path;
    }
}
