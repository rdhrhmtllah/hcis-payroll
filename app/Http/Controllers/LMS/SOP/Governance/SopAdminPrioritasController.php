<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopPrioritasService;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master tingkat prioritas paket SOP.
 *
 * Dijaga kunci izinnya sendiri, menuSopPrioritasPage. Sampai 28 Agu 2026
 * rutenya menumpang hak akses Kategori karena tiga baris pendaftarannya di
 * database tak pernah ditanam; DML-2026-057 menanamnya dan tumpangan itu
 * dilepas bersamaan dengan pencabutan Kategori dari modul.
 *
 * TIGA ATURAN YANG MENJAGA ANGKA LAPORAN TETAP BENAR:
 *  1. Kode tak bisa diubah setelah dipakai paket. Kode itulah yang tersimpan di
 *     N_SOP_Master_Paket; menggantinya membuat paket menunjuk tingkat yang tak
 *     ada lagi — dan kunci asing DDL 35 akan menolaknya dengan galat mentah.
 *  2. Tingkat yang sedang dipakai tak bisa dihapus maupun dinonaktifkan.
 *  3. Tingkat terakhir tak bisa dihabiskan. Master kosong membuat SETIAP
 *     penyimpanan paket ditolak validasi dengan pesan yang tak menuntun ke
 *     sebabnya.
 */
class SopAdminPrioritasController extends Controller
{
    public function __construct(private SopPrioritasService $service) {}

    private function belumSiap()
    {
        return ResponseHelper::error(
            'Master prioritas belum aktif di database ini. Jalankan skrip 35_master_prioritas.sql lebih dulu.',
            409);
    }

    private function decode($id): ?int
    {
        $d = Hashids::connection('custom')->decode((string) $id);

        return $d[0] ?? null;
    }

    /** GET /sop/admin/prioritas — halaman master. */
    public function halaman()
    {
        return inertia('LMS/SOP/Governance/Admin/SopPrioritas', [
            'prioritas' => $this->service->untukLayar(),
        ]);
    }

    /** GET /api/v1/sop/admin/prioritas/current */
    public function index()
    {
        try {
            if (! $this->service->tersedia()) {
                return $this->belumSiap();
            }

            $rows = DB::table(SopPrioritasService::TABEL)
                ->orderBy('Urutan')->orderBy('Kode')
                ->get(['Id_SOP_Prioritas', 'Kode', 'Nama', 'Urutan', 'Warna', 'Flag_Aktif']);

            /* Jumlah pemakai ikut dikirim, satu kueri untuk seluruh daftar.
               Tanpa angka ini admin baru tahu sebuah tingkat sedang dipakai
               ketika penghapusannya ditolak — pemberitahuan yang datang
               sesudah keputusan diambil. */
            $pakai = DB::table('N_SOP_Master_Paket')
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->select('Prioritas', DB::raw('COUNT(*) as n'))
                ->groupBy('Prioritas')->pluck('n', 'Prioritas');

            return ResponseHelper::success($rows->map(function ($r) use ($pakai) {
                $r->Jumlah_Paket = (int) ($pakai[$r->Kode] ?? 0);
                $r->Id_SOP_Prioritas = Hashids::connection('custom')->encode($r->Id_SOP_Prioritas);

                return $r;
            }), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP prioritas index gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    private function validasi(Request $request, ?int $id): array
    {
        return $request->validate([
            /* Huruf besar, angka, garis bawah. Kodenya masuk ke kolom paket dan
               dibandingkan apa adanya di banyak kueri; spasi dan huruf kecil di
               sana melahirkan "Sangat Tinggi" dan "SANGAT_TINGGI" sebagai dua
               tingkat berbeda yang tampak sama di layar. */
            'Kode' => 'required|string|max:30|regex:/^[A-Z0-9_]+$/',
            'Nama' => 'required|string|max:60',
            'Urutan' => 'required|integer|min:0|max:9998',
            'Warna' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'Flag_Aktif' => 'nullable|boolean',
        ], [
            'Kode.regex' => 'Kode hanya boleh huruf kapital, angka, dan garis bawah — mis. SANGAT_TINGGI.',
            'Warna.regex' => 'Warna harus hex 6 digit berawalan #, mis. #EF4444.',
            'Urutan.max' => 'Urutan maksimal 9998; angka 9999 dipakai sistem untuk tingkat yang tak dikenal.',
        ]);
    }

    /** POST /api/v1/sop/admin/prioritas/store */
    public function store(Request $request)
    {
        try {
            if (! $this->service->tersedia()) {
                return $this->belumSiap();
            }

            $data = $this->validasi($request, null);

            if (DB::table(SopPrioritasService::TABEL)->where('Kode', $data['Kode'])->exists()) {
                return ResponseHelper::error("Kode \"{$data['Kode']}\" sudah dipakai tingkat lain.", 422);
            }

            $id = DB::table(SopPrioritasService::TABEL)->insertGetId([
                'Kode' => $data['Kode'],
                'Nama' => $data['Nama'],
                'Urutan' => (int) $data['Urutan'],
                'Warna' => $data['Warna'],
                'Flag_Aktif' => SopStatus::AKTIF,
                'Created_By' => Auth::id(),
                'Created_At' => now(),
            ], 'Id_SOP_Prioritas');

            $this->service->lupakan();
            SopLog::catat('PRIORITAS_TAMBAH', "Tingkat prioritas \"{$data['Nama']}\" ({$data['Kode']}) ditambahkan.",
                null, null, null, null, ['oleh_id_users' => Auth::id(), 'id_prioritas' => $id]);

            return ResponseHelper::success(null, 'Tingkat prioritas ditambahkan', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('SOP prioritas store gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** PUT /api/v1/sop/admin/prioritas/update/{id} */
    public function update(Request $request, $id)
    {
        try {
            if (! $this->service->tersedia()) {
                return $this->belumSiap();
            }

            $decoded = $this->decode($id);
            if (! $decoded) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $lama = DB::table(SopPrioritasService::TABEL)->where('Id_SOP_Prioritas', $decoded)->first();
            if (! $lama) {
                return ResponseHelper::error('Tingkat prioritas tidak ditemukan', 404);
            }

            $data = $this->validasi($request, $decoded);
            $dipakai = $this->service->dipakai($lama->Kode);

            /* KODE TERKUNCI BEGITU DIPAKAI. Nama, warna, dan urutan bebas diubah
               kapan pun — itu memang gunanya master ini. Kode tidak: ia yang
               tersimpan di kolom paket. */
            if ($data['Kode'] !== $lama->Kode && $dipakai > 0) {
                return ResponseHelper::error(
                    "Kode tak bisa diubah — {$dipakai} paket sudah menyimpan \"{$lama->Kode}\". "
                    .'Ubah namanya saja, atau buat tingkat baru lalu pindahkan paketnya.',
                    422);
            }

            if ($data['Kode'] !== $lama->Kode
                && DB::table(SopPrioritasService::TABEL)->where('Kode', $data['Kode'])->exists()) {
                return ResponseHelper::error("Kode \"{$data['Kode']}\" sudah dipakai tingkat lain.", 422);
            }

            $aktif = ($data['Flag_Aktif'] ?? true) ? SopStatus::AKTIF : SopStatus::INAKTIF;

            // Menonaktifkan = menyembunyikannya dari pemilih. Paket yang telanjur
            // memakainya akan menunjuk tingkat yang tak ada di daftar mana pun.
            if ($aktif === SopStatus::INAKTIF && $dipakai > 0) {
                return ResponseHelper::error(
                    "Tingkat ini sedang dipakai {$dipakai} paket, jadi belum bisa dinonaktifkan. "
                    .'Pindahkan paketnya ke tingkat lain lebih dulu.',
                    422);
            }

            if ($aktif === SopStatus::INAKTIF && $this->tinggalSatuAktif($decoded)) {
                return ResponseHelper::error(
                    'Ini satu-satunya tingkat yang tersisa. Kalau dinonaktifkan, tak ada tingkat '
                    .'yang bisa dipilih dan setiap penyimpanan paket akan ditolak.',
                    422);
            }

            DB::table(SopPrioritasService::TABEL)->where('Id_SOP_Prioritas', $decoded)->update([
                'Kode' => $data['Kode'],
                'Nama' => $data['Nama'],
                'Urutan' => (int) $data['Urutan'],
                'Warna' => $data['Warna'],
                'Flag_Aktif' => $aktif,
                'Updated_By' => Auth::id(),
                'Updated_At' => now(),
            ]);

            $this->service->lupakan();
            SopLog::catat('PRIORITAS_UBAH', "Tingkat prioritas \"{$data['Nama']}\" ({$data['Kode']}) diubah.",
                null, null, null, null, ['oleh_id_users' => Auth::id(), 'id_prioritas' => $decoded]);

            return ResponseHelper::success(null, 'Tingkat prioritas diperbarui');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('SOP prioritas update gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** DELETE /api/v1/sop/admin/prioritas/delete/{id} */
    public function destroy($id)
    {
        try {
            if (! $this->service->tersedia()) {
                return $this->belumSiap();
            }

            $decoded = $this->decode($id);
            if (! $decoded) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $row = DB::table(SopPrioritasService::TABEL)->where('Id_SOP_Prioritas', $decoded)->first();
            if (! $row) {
                return ResponseHelper::error('Tingkat prioritas tidak ditemukan', 404);
            }

            $dipakai = $this->service->dipakai($row->Kode);
            if ($dipakai > 0) {
                return ResponseHelper::error(
                    "Tingkat \"{$row->Nama}\" sedang dipakai {$dipakai} paket, jadi tak bisa dihapus. "
                    .'Pindahkan paketnya ke tingkat lain lebih dulu.',
                    422);
            }

            if ($this->tinggalSatuAktif($decoded)) {
                return ResponseHelper::error(
                    'Ini satu-satunya tingkat yang tersisa. Kalau dihapus, tak ada tingkat yang '
                    .'bisa dipilih dan setiap penyimpanan paket akan ditolak.',
                    422);
            }

            DB::table(SopPrioritasService::TABEL)->where('Id_SOP_Prioritas', $decoded)->delete();

            $this->service->lupakan();
            SopLog::catat('PRIORITAS_HAPUS', "Tingkat prioritas \"{$row->Nama}\" ({$row->Kode}) dihapus.",
                null, null, null, null, ['oleh_id_users' => Auth::id(), 'id_prioritas' => $decoded]);

            return ResponseHelper::success(null, 'Tingkat prioritas dihapus');
        } catch (\Exception $e) {
            Log::error('SOP prioritas destroy gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** Benar bila baris ini satu-satunya yang masih aktif. */
    private function tinggalSatuAktif(int $id): bool
    {
        return DB::table(SopPrioritasService::TABEL)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Prioritas', '<>', $id)
            ->doesntExist();
    }
}
