<?php

namespace App\Http\Controllers\SYSTEM\MasterData\Departemen;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\Support\SopUnitKerja;
use App\Services\SYSTEM\MasterData\Departemen\DepartemenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Kode Departemen — pengelolaan, khusus System Admin.
 *
 * GERBANGNYA `system.admin`, BUKAN `check.jabatan` — mengikuti preseden
 * routes/SYSTEM/DatabaseChanges.php dan Master Entitas. Grup fitur SYSTEM tak
 * dipegang satu pun role di N_HRIS_Role_Functional, jadi check.jabatan di sini
 * akan menjadi gerbang yang tak seorang pun bisa lewati.
 *
 * Daftar departemen dibaca modul lain lewat endpoint bergerbang masing-masing
 * (mis. /api/v1/sop/admin/master-data/departemen). Yang ada di sini hanya
 * pengelolaannya — termasuk BINDING ke sub-divisi HRIS, yang tak pernah ditebak
 * sistem dan selalu dipasangkan manusia.
 */
class DepartemenController extends Controller
{
    public function __construct(private DepartemenService $service) {}

    private function decode($id): ?int
    {
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    /** GET /system/master-data/departemen */
    public function page()
    {
        return Inertia::render('SYSTEM/MasterData/Departemen/DepartemenPage');
    }

    /** GET /system/master-data/departemen/data */
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

    /** GET /system/master-data/departemen/sub-divisi — kandidat binding. */
    public function opsiSubDivisi()
    {
        try {
            return ResponseHelper::success(SopUnitKerja::opsiDepartemen(), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /system/master-data/departemen/store */
    public function store(Request $request)
    {
        $data = $this->validasi($request);

        DB::beginTransaction();
        try {
            $id = $this->service->create($data, Auth::user()->Id_Users);
            DB::commit();

            return ResponseHelper::success(
                ['Id_Departemen' => Hashids::connection('custom')->encode($id)],
                'Departemen berhasil disimpan!',
                201,
            );
        } catch (\DomainException $e) {
            DB::rollBack();

            // Kode bentrok atau sub-divisi tak dikenal — pesannya sudah untuk admin.
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

    /** PUT /system/master-data/departemen/update/{id} */
    public function update(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $this->validasi($request);

        DB::beginTransaction();
        try {
            if (! $this->service->update($decoded, $data, Auth::user()->Id_Users)) {
                DB::rollBack();

                return ResponseHelper::error('Departemen tidak ditemukan', 404);
            }
            DB::commit();

            return ResponseHelper::success(null, 'Departemen berhasil diperbarui!');
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

    /** DELETE /system/master-data/departemen/delete/{id} (soft delete) */
    public function destroy($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        DB::beginTransaction();
        try {
            $dipakai = $this->service->softDelete($decoded, Auth::user()->Id_Users);
            if ($dipakai > 0) {
                DB::rollBack();

                return ResponseHelper::error(
                    "Departemen ini masih dipakai {$dipakai} dokumen. "
                    .'Pindahkan dulu dokumennya sebelum menonaktifkan.',
                    422,
                );
            }
            DB::commit();

            return ResponseHelper::success(null, 'Departemen berhasil dinonaktifkan!');
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
     * Bentuk data departemen.
     *
     * `Kode_Departemen` tetap divalidasi saat update supaya keunikannya
     * diperiksa, tetapi service sengaja TIDAK menuliskannya: kode menjadi
     * segmen nomor dokumen yang sudah tercetak, dan memindahkannya berarti
     * memindahkan arti nomor yang sudah beredar tanpa satu pun jejak.
     */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'Kode_Departemen' => 'required|string|max:10|regex:/^[A-Za-z0-9_-]+$/',
            'Nama_Departemen' => 'required|string|max:150',
            /* Binding ke sub-divisi HRIS. Boleh kosong, dan itu keadaan yang
               lazim: 35 dari 52 kode memang tak punya pasangan di struktur yang
               tersimpan. Memaksanya berarti memasangkan ke sub-divisi yang salah. */
            'Sub_Divisi_Id' => 'nullable|integer',
            'Urutan' => 'nullable|integer|min:0|max:999',
        ], [
            'Kode_Departemen.regex' => 'Kode departemen hanya boleh huruf, angka, garis bawah, dan strip — ia menjadi segmen nomor dokumen.',
        ]);
    }
}
