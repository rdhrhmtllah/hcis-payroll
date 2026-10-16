<?php

namespace App\Http\Controllers\ESS\Employee\DataCollection;

use App\Http\Controllers\Controller;
use App\Services\ESS\Employee\DataCollection\AdminPengumpulanDataService;
use App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye;
use App\Services\ESS\Employee\DataCollection\PengumpulanDataExportService;
use App\Services\ESS\Employee\DataCollection\SkemaPengumpulanData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AdminPengumpulanDataController extends Controller
{
    public function __construct(
        private AdminPengumpulanDataService $admin,
        private SkemaPengumpulanData $skema,
        private KonfigurasiKampanye $konfigurasi,
    ) {}

    public function index(Request $request)
    {
        return inertia('ESS/Employee/DataCollection/Admin/Dashboard', [
            'ringkasan' => $this->admin->ringkasan(),
            'daftar' => $this->admin->daftar($this->filter($request)),
            'pilihanDepartemen' => $this->admin->pilihanDepartemen(),
            'pilihanLokasi' => $this->admin->pilihanLokasi(),
            // Skema PHL dipakai karena halaman admin harus bisa menampilkan
            // field atasan milik PHL juga -- bukan hanya field yang berlaku
            // untuk pembacanya sendiri.
            'skema' => $this->skema->untukUi(isPhl: true),
            'pengumuman' => $this->konfigurasi->pengumuman(),
        ]);
    }

    public function daftar(Request $request): JsonResponse
    {
        return response()->json($this->admin->daftar($this->filter($request)));
    }

    public function ringkasan(): JsonResponse
    {
        return response()->json($this->admin->ringkasan());
    }

    public function detail(string $kodeKaryawan): JsonResponse
    {
        return response()->json($this->admin->detail($kodeKaryawan));
    }

    public function pengecualian(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode_karyawan' => ['required', 'string', 'max:20'],
            'dikecualikan' => ['required', 'boolean'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        $this->admin->aturPengecualian(
            $data['kode_karyawan'],
            $data['dikecualikan'],
            $data['alasan'] ?? null,
            $this->pelaku(),
            $request->ip(),
        );

        return response()->json(['ok' => true, 'ringkasan' => $this->admin->ringkasan()]);
    }

    public function bukaKunci(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode_karyawan' => ['required', 'string', 'max:20'],
            'alasan' => ['required', 'string', 'max:500'],
        ]);

        $this->admin->bukaKunci($data['kode_karyawan'], $data['alasan'], $this->pelaku(), $request->ip());

        return response()->json(['ok' => true, 'ringkasan' => $this->admin->ringkasan()]);
    }

    public function sakelar(Request $request): JsonResponse
    {
        $data = $request->validate(['aktif' => ['required', 'boolean']]);

        $this->admin->aturSakelar($data['aktif'], $this->pelaku(), $request->ip());

        return response()->json(['ok' => true, 'aktif' => $data['aktif']]);
    }

    /**
     * Cakupan lokasi kerja yang kampanyenya dibuka.
     *
     * Daftar kosong DITERIMA dan berarti seluruh lokasi — itu cara admin
     * membatalkan pembukaan bertahap, jadi 'required' di sini justru akan
     * mengurungnya pada cakupan terakhir yang pernah ia simpan.
     */
    public function cakupan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lokasi' => ['present', 'array', 'max:200'],
            'lokasi.*' => ['string', 'max:50'],
        ]);

        $tersimpan = $this->admin->aturCakupan($data['lokasi'], $this->pelaku(), $request->ip());

        return response()->json([
            'ok' => true,
            'cakupan_lokasi' => $tersimpan,
            'ringkasan' => $this->admin->ringkasan(),
        ]);
    }

    public function pengumuman(Request $request): JsonResponse
    {
        $data = $request->validate([
            'judul' => ['required', 'string', 'max:200'],
            'teks' => ['required', 'string', 'max:1000'],
            'label_tombol_isi' => ['required', 'string', 'max:100'],
            'label_tombol_tunda' => ['required', 'string', 'max:100'],
        ]);

        $this->admin->simpanPengumuman($data, $this->pelaku(), $request->ip());

        return response()->json(['ok' => true]);
    }

    public function pengingat(Request $request): JsonResponse
    {
        $jumlah = $this->admin->kirimPengingat($this->pelaku(), $request->ip());

        return response()->json(['ok' => true, 'terkirim' => $jumlah]);
    }

    public function mintaEkspor(Request $request, PengumpulanDataExportService $ekspor): JsonResponse
    {
        $data = $request->validate([
            'format' => ['required', 'in:xlsx,csv'],
            'status' => ['nullable', 'in:,SELESAI,BELUM'],
            'departemen' => ['nullable', 'string', 'max:150'],
            'lokasi' => ['nullable', 'string', 'max:50'],
        ]);

        $filter = [
            'status' => $data['status'] ?? '',
            'departemen' => $data['departemen'] ?? '',
            'lokasi' => $data['lokasi'] ?? '',
        ];

        $exportId = $ekspor->makeExportId();
        $ekspor->startJob($exportId, $data['format'], $filter, $ekspor->hitungTarget($filter), $this->pelaku());
        $ekspor->dispatchJob($exportId);

        return response()->json(['ok' => true, 'export_id' => $exportId]);
    }

    public function statusEkspor(string $exportId, PengumpulanDataExportService $ekspor): JsonResponse
    {
        $progres = $ekspor->getProgress($exportId);

        return $progres
            ? response()->json($progres)
            : response()->json(['message' => 'Ekspor tidak ditemukan.'], 404);
    }

    public function daftarEkspor(PengumpulanDataExportService $ekspor): JsonResponse
    {
        return response()->json(['items' => $ekspor->getList($this->pelaku())]);
    }

    public function unduhEkspor(string $exportId, PengumpulanDataExportService $ekspor)
    {
        $job = $ekspor->jobUntukUnduh($exportId);

        if (! $job || $job->Status !== PengumpulanDataExportService::STATUS_READY || ! $job->File_Path) {
            abort(404, 'Berkas ekspor belum siap.');
        }

        if (! Storage::disk(PengumpulanDataExportService::DISK)->exists($job->File_Path)) {
            abort(404, 'Berkas ekspor sudah tidak tersedia.');
        }

        return Storage::disk(PengumpulanDataExportService::DISK)->download($job->File_Path, $job->File_Name);
    }

    public function kualitas(): JsonResponse
    {
        return response()->json(['temuan' => $this->admin->pemeriksaKualitas()]);
    }

    public function audit(): JsonResponse
    {
        return response()->json(['items' => $this->admin->audit()]);
    }

    private function filter(Request $request): array
    {
        return [
            'cari' => (string) $request->query('cari', ''),
            'status' => (string) $request->query('status', ''),
            'departemen' => (string) $request->query('departemen', ''),
            'lokasi' => (string) $request->query('lokasi', ''),
            'halaman' => (int) $request->query('halaman', 1),
            'per_halaman' => (int) $request->query('per_halaman', 25),
        ];
    }

    private function pelaku(): string
    {
        $user = Auth::user();

        return (string) ($user?->Username ?? $user?->Kode_Users ?? 'tidak diketahui');
    }
}
