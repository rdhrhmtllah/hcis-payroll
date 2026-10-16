<?php

namespace App\Http\Controllers\LMS\SOP\Governance\SopApqc;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopApqcService;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master APQC — klasifikasi proses bisnis untuk dokumen SOP.
 *
 * Tinggal di Administrator SOP, bukan SYSTEM, karena pemiliknya memang tata
 * kelola SOP. Bedakan dari dua master SOP lain yang namanya berdekatan:
 *   Jenis Dokumen — TINGKAT hirarki mutu (Kebijakan…Formulir).
 * APQC menjawab pertanyaan ketiga: dokumen ini memetakan PROSES BISNIS yang mana.
 *
 * Pola CRUD-nya menyalin SopJenisDokumenController, termasuk pemetaan tipe item
 * yang menjadikan kewajiban mengisi APQC sebagai turunan dari master.
 */
class SopApqcController extends Controller
{
    public function __construct(private SopApqcService $service) {}

    /** Halaman. Route: GET /sop/admin/apqc */
    public function index()
    {
        return inertia('LMS/SOP/Governance/SopApqc/SopApqcPage', [
            'tipeOpsi' => SopTipeItem::VERSIONED,
        ]);
    }

    /** GET /api/v1/sop/admin/apqc/current */
    public function getDataCurrent(Request $request)
    {
        try {
            $limit = min(100, max(5, (int) $request->query('limit', 25)));
            $page = max(1, (int) $request->query('page', 1));

            [$rows, $total] = $this->service->paginate($request->query('q'), $page, $limit);

            return ResponseHelper::successWithPagination($rows, $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP apqc getDataCurrent gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/master-data/apqc?tipe=DOKUMEN
     *
     * Digerbangi ganda (Master Dokumen + Builder), satu implementasi untuk dua
     * layar — mengikuti preseden route token secure-PDF.
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
            Log::error('SOP apqc opsi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/admin/apqc/store */
    public function store(Request $request)
    {
        $data = $this->validasi($request);

        try {
            $id = DB::transaction(fn () => $this->service->create($data, Auth::user()->Id_Users));
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP apqc store gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(
            ['Id_SOP_APQC' => Hashids::connection('custom')->encode($id)],
            'Proses APQC berhasil disimpan!',
            201,
        );
    }

    /** PUT /api/v1/sop/admin/apqc/update/{id} */
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
            Log::error('SOP apqc update gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        /* SENGAJA bukan \DomainException: gerbang ini menjawab 404, bukan 422,
           dan melemparkannya sebagai DomainException akan menggeser statusnya
           diam-diam. Aman di luar transaksi karena SopApqcService::update()
           mengembalikan false di baris pertamanya, sebelum menulis apa pun. */
        if (! $ditemukan) {
            return ResponseHelper::error('Proses APQC tidak ditemukan', 404);
        }

        return ResponseHelper::success(null, 'Proses APQC berhasil diperbarui!');
    }

    /** DELETE /api/v1/sop/admin/apqc/delete/{id} (soft delete) */
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
                   manual. Dilempar dari DALAM closure supaya closure yang
                   membatalkan; pesan dan status 422-nya tetap sama. */
                if ($dipakai > 0) {
                    throw new \DomainException(
                        "Proses ini masih dipakai {$dipakai} dokumen. Pindahkan dokumennya ke proses lain sebelum menonaktifkan.",
                    );
                }
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP apqc destroy gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(null, 'Proses APQC berhasil dinonaktifkan!');
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'Kode_Proses' => 'required|string|max:10|regex:/^[A-Za-z0-9._-]+$/',
            'Nama_Proses' => 'required|string|max:200',
            'Deskripsi' => 'nullable|string|max:500',
            'Urutan' => 'nullable|integer|min:0|max:999',
            /* Boleh kosong: proses yang belum punya tipe item sah tetap sah
               disimpan sebagai kerangka. Yang dijaga adalah pemakaiannya —
               SopDokumenService menolak memasangkannya ke dokumen mana pun
               sampai tipenya diisi, dan pesannya menyebut halaman ini. */
            'Tipe_Item' => 'nullable|array',
            'Tipe_Item.*' => 'string|in:'.implode(',', SopTipeItem::SEMUA),
        ], [
            'Kode_Proses.regex' => 'Nomor proses hanya boleh huruf, angka, titik, garis bawah, dan strip — ia rujukan tetap yang dikutip di audit.',
        ]);
    }
}
