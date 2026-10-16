<?php

namespace App\Http\Controllers\SYSTEM\Application\Panduan;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Panduan\PanduanService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * Manajemen Panduan — khusus System Admin (gerbang `system.admin`, seperti
 * halaman SYSTEM lain; grup SYSTEM tidak dipegang role mana pun).
 */
class PanduanController extends Controller
{
    public function __construct(private PanduanService $panduan) {}

    public function page()
    {
        return Inertia::render('SYSTEM/Application/Panduan/PanduanPage', [
            'maksKb' => PanduanService::MAKS_KB,
        ]);
    }

    public function index(Request $request)
    {
        return ResponseHelper::success($this->panduan->daftar(
            $request->query('q') ?? $request->query('cari'),
            $request->query('status'),
            $request->query('kondisi')
        ));
    }

    public function opsi()
    {
        return ResponseHelper::success($this->panduan->opsi());
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturan(true), [], $this->atribut());

        return $this->jalankan(
            fn () => $this->panduan->buat($data, $request->file('berkas'), ...$this->sasaranDariForm($data)),
            'Panduan ditambahkan.',
            201,
        );
    }

    /**
     * Keterangan, halaman, dan role. Berkas baru lewat PanduanVersiController
     * (versi baru, riwayat tersimpan). POST dipertahankan demi klien lama.
     */
    public function update(Request $request, int $id)
    {
        $data = $request->validate($this->aturan(false), [], $this->atribut());

        // FormData tidak bisa mengirim larik kosong: `atur_sasaran` menandai bahwa
        // halaman & role ikut dikirim (role kosong = pembatas dilepas). Tanpanya tetap.
        [$halaman, $role] = $request->boolean('atur_sasaran') ? $this->sasaranDariForm($data) : [null, null];

        return $this->jalankan(fn () => $this->panduan->ubah($id, $data, $halaman, $role), 'Panduan diperbarui.');
    }

    public function status(Request $request, int $id)
    {
        $data = $request->validate(['aktif' => 'required|boolean']);

        return $this->jalankan(fn () => $this->panduan->ubahStatus($id, (bool) $data['aktif']), 'Status panduan diperbarui.');
    }

    /** Pratinjau berkas untuk admin — tanpa syarat pemetaan halaman. */
    public function berkas(int $id)
    {
        $berkas = $this->panduan->berkasAdmin($id);
        if (! $berkas) {
            return ResponseHelper::errorWithCode('Panduan tidak ditemukan.', 'SYSTEM_PANDUAN_NOT_FOUND', 404);
        }

        return $this->panduan->sajikanPdf($berkas);
    }

    /** Halaman dan role pembatas satu panduan untuk form ubah. */
    public function sasaran(int $id)
    {
        return $this->jalankan(fn () => $this->panduan->sasaran($id));
    }

    /** @return array{0: array, 1: array} halaman (wajib) dan role pembatas (opsional) */
    private function sasaranDariForm(array $data): array
    {
        return [$data['halaman'] ?? [], $data['role'] ?? []];
    }

    private function aturan(bool $membuat): array
    {
        $berkas = $membuat ? [
            'versi' => 'nullable|string|max:30',
            // Isi berkas diperiksa (mimes membaca MIME dari isi), bukan nama.
            'berkas' => ['required', 'file', 'mimes:pdf', 'max:'.PanduanService::MAKS_KB],
        ] : [];

        return $berkas + [
            'judul' => 'required|string|max:150',
            'deskripsi' => 'nullable|string|max:500',
            'atur_sasaran' => 'nullable|boolean',
            'role' => 'nullable|array|max:200',
            'role.*' => 'integer',
            'halaman' => 'nullable|array|max:500',
            'halaman.*.jenis_page' => 'required|string|max:100',
            'halaman.*.halaman_mulai' => 'nullable|integer|min:1|max:9999',
        ];
    }

    private function atribut(): array
    {
        return [
            'judul' => 'judul', 'deskripsi' => 'deskripsi', 'versi' => 'versi', 'berkas' => 'berkas PDF',
            'halaman' => 'halaman', 'halaman.*.halaman_mulai' => 'halaman mulai', 'role' => 'role', 'role.*' => 'role',
        ];
    }

    private function jalankan(callable $kerja, string $pesan = 'Berhasil', int $status = 200)
    {
        try {
            return ResponseHelper::success($kerja(), $pesan, $status);
        } catch (DomainException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'SYSTEM_PANDUAN_INVALID', 422);
        } catch (\Throwable $e) {
            Log::error('Manajemen panduan gagal', ['error' => $e->getMessage()]);

            return ResponseHelper::errorWithCode('Terjadi kesalahan saat memproses panduan.', 'SYSTEM_PANDUAN_FAILED', 500);
        }
    }
}
