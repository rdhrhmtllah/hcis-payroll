<?php

namespace App\Http\Controllers\LMS\SOP\Governance\SopJenisDokumen;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopJenisDokumenService;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopUnitKerja;
use App\Services\SYSTEM\MasterData\Departemen\DepartemenService;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Jenis Dokumen SOP — tingkat hirarki mutu.
 *
 * Kebijakan → Prosedur → Instruksi Kerja → Formulir.
 *
 * JANGAN KELIRU DENGAN dua halaman lain yang namanya berdekatan:
 *   Kebijakan & Otomasi SOP — aturan penugasan otomatis, bukan dokumen kebijakan.
 *
 * Pola CRUD-nya menyalin vertical slice SopCategoryController apa adanya
 * (validate → decode → service → ResponseHelper), bukan pola baru.
 */
class SopJenisDokumenController extends Controller
{
    public function __construct(private SopJenisDokumenService $service) {}

    /** Halaman. Route: GET /sop/admin/jenis-dokumen */
    public function index()
    {
        return inertia('LMS/SOP/Governance/SopJenisDokumen/SopJenisDokumenPage', [
            'tipeOpsi' => SopTipeItem::VERSIONED,
        ]);
    }

    /** GET /api/v1/sop/admin/jenis-dokumen/current */
    public function getDataCurrent(Request $request)
    {
        try {
            $limit = min(100, max(5, (int) $request->query('limit', 25)));
            $page = max(1, (int) $request->query('page', 1));
            $search = $request->query('q', null);

            [$rows, $total] = $this->service->paginate($search, $page, $limit);

            return ResponseHelper::successWithPagination($rows, $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP jenis-dokumen getDataCurrent gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/master-data/jenis-dokumen?tipe=DOKUMEN
     *
     * Digerbangi ganda (Master Dokumen + Builder) supaya dua layar memakai satu
     * implementasi. Menduplikasinya per halaman adalah persis kesalahan yang
     * sudah dihindari route token secure-PDF.
     */
    public function opsi(Request $request)
    {
        try {
            $tipe = $request->query('tipe');
            if ($tipe !== null && ! in_array($tipe, SopTipeItem::SEMUA, true)) {
                return ResponseHelper::error('Tipe item tidak dikenali', 422);
            }

            return ResponseHelper::success($this->service->opsi($tipe), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP jenis-dokumen opsi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/master-data/unit-kerja
     *
     * Mengembalikan DUA bentuk sekaligus: `departemen` (daftar datar, bentuk yang
     * dipakai layar sejak 22 Agu 2026) dan `divisi` (bertingkat, dipertahankan
     * untuk pemanggil lama). Menghapus bentuk lama dalam rilis yang sama dengan
     * mengubah layar akan memutus apa pun yang belum ikut berpindah, tanpa
     * peringatan.
     */
    public function opsiUnitKerja()
    {
        try {
            return ResponseHelper::success([
                'departemen' => SopUnitKerja::opsiDepartemen(),
                'divisi' => SopUnitKerja::opsi(),
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP jenis-dokumen opsiUnitKerja gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/master-data/entitas
     *
     * Master entitas milik SYSTEM; yang ada di sini hanya permukaan HTTP-nya,
     * karena gerbang aksesnya memang milik layar SOP. Modul lain yang butuh
     * entitas menambah endpoint bergerbang sendiri yang memanggil service yang
     * sama — bukan menyalin daftarnya.
     */
    public function opsiEntitas()
    {
        try {
            return ResponseHelper::success(app(EntitasService::class)->opsi(), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP jenis-dokumen opsiEntitas gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/master-data/departemen
     *
     * Master departemen milik SYSTEM; yang ada di sini hanya permukaan HTTP-nya,
     * karena gerbang aksesnya memang milik layar SOP.
     */
    public function opsiDepartemen()
    {
        try {
            return ResponseHelper::success(app(DepartemenService::class)->opsi(), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP jenis-dokumen opsiDepartemen gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/admin/jenis-dokumen/store */
    public function store(Request $request)
    {
        $data = $this->validasi($request);

        try {
            $id = DB::transaction(fn () => $this->service->create($data, Auth::user()->Id_Users));
        } catch (\DomainException $e) {
            // Kode bentrok — pesannya sudah ditulis untuk admin.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP jenis-dokumen store gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(
            ['Id_SOP_Jenis_Dokumen' => Hashids::connection('custom')->encode($id)],
            'Jenis dokumen berhasil disimpan!',
            201,
        );
    }

    /** PUT /api/v1/sop/admin/jenis-dokumen/update/{id} */
    public function update(Request $request, $id)
    {
        $decode = Hashids::connection('custom')->decode($id);
        if (! $decode) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $this->validasi($request);

        try {
            $ditemukan = DB::transaction(fn () => $this->service->update($decode[0], $data, Auth::user()->Id_Users));
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP jenis-dokumen update gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        /* SENGAJA bukan \DomainException: gerbang ini menjawab 404, bukan 422.
           Aman di luar transaksi karena service mengembalikan false SEBELUM
           menulis apa pun — tak ada mutasi yang perlu dibatalkan. */
        if (! $ditemukan) {
            return ResponseHelper::error('Jenis dokumen tidak ditemukan', 404);
        }

        return ResponseHelper::success(null, 'Jenis dokumen berhasil diperbarui!');
    }

    /** POST /api/v1/sop/admin/jenis-dokumen/reorder */
    public function reorder(Request $request)
    {
        $request->validate([
            // Jenis dokumen berjumlah puluhan; 500 jauh di atas kebutuhan nyata
            // dan jauh di bawah plafon 2100 parameter SQL Server.
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'required|string',
        ]);

        $decodedIds = [];
        foreach ($request->input('ids') as $hashId) {
            $d = Hashids::connection('custom')->decode($hashId);
            if (! empty($d)) {
                $decodedIds[] = $d[0];
            }
        }

        if (count($decodedIds) !== count($request->input('ids'))) {
            return ResponseHelper::error('Format ID tidak valid', 400);
        }

        /* Tanpa cabang \DomainException: SopJenisDokumenService::reorder() hanya
           menjalankan UPDATE berurutan dan tak memanggil satu pun penjaga
           keunikan, jadi cabang 422 di sini tak akan pernah tercapai. */
        try {
            DB::transaction(fn () => $this->service->reorder($decodedIds, Auth::user()->Id_Users));
        } catch (\Throwable $e) {
            Log::error('SOP jenis-dokumen reorder gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(null, 'Urutan hirarki jenis dokumen berhasil diperbarui!');
    }

    /** DELETE /api/v1/sop/admin/jenis-dokumen/delete/{id} (soft delete) */
    public function destroy($id)
    {
        $decode = Hashids::connection('custom')->decode($id);
        if (! $decode) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            DB::transaction(function () use ($decode) {
                $dipakai = $this->service->softDelete($decode[0], Auth::user()->Id_Users);

                /* Gerbang prasyarat, bukan penanganan galat: dulu ini rollBack()
                   manual. Dilempar dari DALAM closure supaya closure-lah yang
                   membatalkan, dan pesan serta status 422-nya tetap sama. */
                if ($dipakai > 0) {
                    throw new \DomainException(
                        "Jenis ini masih dipakai {$dipakai} dokumen. Pindahkan dokumennya ke jenis lain sebelum menonaktifkan.",
                    );
                }
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP jenis-dokumen destroy gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(null, 'Jenis dokumen berhasil dinonaktifkan!');
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'Kode_Jenis' => 'required|string|max:30|regex:/^[A-Za-z0-9_]+$/',
            /* Kode yang TERCETAK di nomor dokumen. Boleh kosong: jenis yang
               kepanjangannya belum ditetapkan tetap sah disimpan sebagai
               kerangka — yang dijaga adalah pemakaiannya, dan nomor lengkap
               memang tak terbit selama segmen ini belum diisi. */
            'Kode_Jenis_Dokumen' => 'nullable|string|max:10|regex:/^[A-Za-z0-9]+$/',
            'Nama_Jenis' => 'required|string|max:100',
            'Level_Hirarki' => 'required|integer|min:1|max:20',
            'Deskripsi' => 'nullable|string|max:500',
            'Urutan' => 'nullable|integer|min:0|max:999',
            /* Boleh kosong: jenis yang belum punya tipe item sah tetap sah
               disimpan sebagai kerangka. Yang dijaga adalah pemakaiannya —
               SopDokumenService menolak memasangkannya ke dokumen mana pun
               sampai tipenya diisi, dan pesannya menyebut halaman ini. */
            'Tipe_Item' => 'nullable|array',
            'Tipe_Item.*' => 'string|in:'.implode(',', SopTipeItem::SEMUA),
        ], [
            'Kode_Jenis.regex' => 'Kode jenis hanya boleh huruf, angka, dan garis bawah — ia dipakai sebagai rujukan tetap oleh seed dan integrasi.',
            'Kode_Jenis_Dokumen.regex' => 'Kode jenis dokumen hanya boleh huruf dan angka — ia tercetak sebagai segmen pertama nomor dokumen, dan pemisahnya sudah dipakai garis miring.',
        ]);
    }
}
