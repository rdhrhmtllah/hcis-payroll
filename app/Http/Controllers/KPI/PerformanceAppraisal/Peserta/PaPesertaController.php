<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Peserta;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\PaPesertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Sisi peserta — layar yang dilihat 958 orang.
 *
 * TIDAK ADA SATU PUN METHOD DI SINI YANG MENERIMA IDENTITAS PESERTA
 *
 * Lingkupnya selalu `Auth::id()`. Tidak ada `{participantId}` di rutenya, tidak ada
 * `kode_karyawan` di query string, dan tidak boleh ditambahkan kelak "supaya atasan bisa
 * ikut melihat" — untuk itu sudah ada jalur admin dengan gerbangnya sendiri. Endpoint
 * peserta yang menerima id lalu mempercayainya adalah cara paling lazim membuat orang
 * membaca hasil penilaian rekannya hanya dengan mengganti angka di alamat.
 *
 * `cycle_id` boleh diterima karena ia mempersempit, bukan memperlebar: apa pun isinya,
 * yang dikembalikan tetap hanya milik yang sedang login.
 */
class PaPesertaController extends Controller
{
    public function __construct(
        private readonly PaPesertaService $peserta,
    ) {}

    public function index()
    {
        return inertia('KPI/PerformanceAppraisal/Peserta/saya');
    }

    public function dashboardIndividu()
    {
        return inertia('KPI/PerformanceAppraisal/Peserta/dashboard-individu');
    }

    public function ringkasan(Request $request)
    {
        return $this->jawab(fn () => $this->peserta->ringkasan(
            (int) Auth::id(),
            $this->cycleId($request),
        ));
    }

    /**
     * Progres saya per kategori penilai.
     *
     * Terpisah dari `daftarAssignment` karena pertanyaannya berbeda: yang itu "tampilkan satu
     * halaman tugas", yang ini "berapa yang selesai dari SELURUHNYA, per kategori". Menyatukan
     * keduanya berarti agregatnya ikut terpotong paginasi, dan kelompok yang isinya 40 akan
     * melaporkan 25.
     */
    public function progresKategori(Request $request)
    {
        return $this->jawab(fn () => $this->peserta->progresPerKategori(
            (int) Auth::id(),
            $this->cycleId($request),
        ));
    }

    /**
     * Kemajuan pengumpulan saya — dasar tombol "Kirim semua penilaian".
     */
    public function kemajuanPengiriman(Request $request)
    {
        return $this->jawab(fn () => $this->peserta->kemajuanPengiriman(
            (int) Auth::id(),
            $this->cycleId($request),
        ));
    }

    /**
     * Mengirim SELURUH penilaian saya di satu siklus sekaligus.
     *
     * `cycle_id` WAJIB dan tidak boleh punya nilai baku. Pengiriman adalah tindakan yang
     * tidak bisa ditarik kembali - sesudahnya angka orang lain lahir di atasnya - dan
     * membiarkan siklusnya ditebak dari "yang sedang aktif" berarti satu permintaan tanpa
     * parameter bisa mengunci siklus yang bukan yang sedang dilihat orangnya.
     */
    public function kirimSemua(Request $request)
    {
        $data = $request->validate([
            'cycle_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->jawab(fn () => $this->peserta->kirimSemua(
            (int) Auth::id(),
            (int) $data['cycle_id'],
        ));
    }

    public function daftarAssignment(Request $request)
    {
        $data = $request->validate([
            /* TERKUMPUL dan MENUNGGU_VERIFIKASI ikut boleh disaring: keduanya keadaan yang
               dilihat penilai pada daftarnya sendiri, dan saringan yang menolaknya membuat
               chip di layar menjawab 422 alih-alih menyaring. */
            'status' => ['nullable', 'string', 'in:BELUM_MULAI,BERJALAN,TERKUMPUL,MENUNGGU_VERIFIKASI,SELESAI'],
            'kategori' => ['nullable', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
            'cycle_id' => ['nullable', 'integer'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->jawab(fn () => $this->peserta->daftarAssignment((int) Auth::id(), $data));
    }

    public function hasilSaya(Request $request)
    {
        return $this->jawab(fn () => $this->peserta->hasilSaya(
            (int) Auth::id(),
            $this->cycleId($request),
        ));
    }

    public function riwayatSaya(Request $request)
    {
        return $this->jawab(fn () => $this->peserta->riwayatSaya((int) Auth::id()));
    }

    private function cycleId(Request $request): ?int
    {
        $v = $request->query('cycle_id');

        return ($v === null || $v === '') ? null : (int) $v;
    }

    /**
     * Pesan RuntimeException diteruskan apa adanya sebagai 422 — pola yang sama dengan
     * controller admin. Pesannya memang ditulis untuk dibaca manusia, dan menggantinya
     * dengan "terjadi kesalahan" membuang satu-satunya keterangan yang berguna.
     */
    private function jawab(callable $kerja)
    {
        try {
            return ResponseHelper::success($kerja());
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
