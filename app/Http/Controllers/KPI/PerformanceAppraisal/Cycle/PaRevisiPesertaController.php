<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Cycle;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Jobs\KPI\PerformanceAppraisal\RevisiPesertaPaJob;
use App\Services\KPI\PerformanceAppraisal\PaRevisiPesertaService;
use App\Services\KPI\PerformanceAppraisal\PaRevisiTugasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Revisi peserta sesudah siklus dikunci: terapkan Level PA terbaru, ikutkan kembali, peserta
 * susulan. Layar membaca pratinjau dampaknya dulu, lalu menjalankan satu tindakan beralasan.
 */
class PaRevisiPesertaController extends Controller
{
    public function __construct(
        private readonly PaRevisiPesertaService $revisi,
        private readonly PaRevisiTugasService $tugas,
    ) {}

    /** Mencari orang berfilter — dasar "centang semua yang lolos filter". */
    public function cari(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'sumber' => ['required', 'in:peserta,karyawan,penilai'],
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:DIIKUTKAN,DIKECUALIKAN,TERTUNDA,TIDAK_AKTIF'],
            'id_level' => ['nullable', 'array'], 'id_level.*' => ['integer'],
            'divisi' => ['nullable', 'array'], 'divisi.*' => ['string', 'max:150'],
            'departemen' => ['nullable', 'array'], 'departemen.*' => ['string', 'max:150'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.PaRevisiPesertaService::MAKS_ORANG],
        ]);

        return $this->jalankan(fn () => [
            'baris' => $this->revisi->cari(
                $cycleId, $data['sumber'], $data, (int) ($data['limit'] ?? PaRevisiPesertaService::MAKS_ORANG),
            ),
            /* Sisa jendela pengisian bagi penilai baru — dibaca layar revisi sejak awal. */
            'jendela' => $this->revisi->keadaanJendela($cycleId),
        ]);
    }

    public function pratinjauMassal(Request $request, int $cycleId)
    {
        $data = $this->validasiMassal($request, false);

        return $this->jalankan(fn () => $this->revisi->pratinjauBanyak($cycleId, $data['tindakan'], $data['orang']));
    }

    /**
     * Memulai revisi banyak orang sebagai tugas latar. Hanya yang menurut pratinjau BOLEH yang
     * dimasukkan — yang tidak boleh dilaporkan sebagai dilewati, bukan dicoba lalu gagal.
     */
    public function mulaiMassal(Request $request, int $cycleId)
    {
        $data = $this->validasiMassal($request, true);

        return $this->jalankan(function () use ($cycleId, $data) {
            if ($this->tugas->berjalanUntukSiklus($cycleId)) {
                throw new RuntimeException('Revisi lain untuk siklus ini masih berjalan — tunggu selesai (lihat Tugas & Aktivitas).');
            }

            /* Memulai cukup tahu siapa yang boleh — peringatan kategori sudah dilihat di pratinjau. */
            $pr = $this->revisi->pratinjauBanyak($cycleId, $data['tindakan'], $data['orang'], false);
            $boleh = array_values(array_filter($pr['baris'], fn ($b) => $b['boleh']));

            if ($boleh === []) {
                throw new RuntimeException('Tidak ada orang terpilih yang bisa direvisi dengan tindakan ini.');
            }

            $userId = Auth::id() === null ? null : (int) Auth::id();
            $mulai = $this->tugas->mulai(
                $cycleId,
                ['total' => count($boleh), 'sudah' => 0, 'berhasil' => 0, 'gagal' => 0, 'dibentuk' => 0, 'dibatalkan' => 0],
                $userId,
                optional(optional(Auth::user())->karyawan)->Kode_Karyawan,
            );
            $this->tugas->catat($mulai['id'], [
                'tindakan' => $data['tindakan'],
                'alasan' => trim($data['alasan']),
                'orang' => array_map(fn ($b) => ['kode_perusahaan' => $b['kode_perusahaan'], 'kode_karyawan' => $b['kode_karyawan'], 'nama' => $b['nama']], $boleh),
                'opsi' => ['lepas_penilai' => (bool) ($data['lepas_penilai'] ?? false)],
            ]);
            RevisiPesertaPaJob::dispatch($mulai['id']);

            return [
                'tugas' => $this->tugas->bentukLayar($this->tugas->baris($mulai['id'])),
                'diproses' => count($boleh),
                'dilewati' => count($pr['baris']) - count($boleh),
            ];
        });
    }

    /** Keadaan tugas revisi terakhir siklus ini — dibaca dialog tiap beberapa detik. */
    public function statusMassal(int $cycleId)
    {
        return $this->jalankan(fn () => ['tugas' => $this->tugas->bentukLayar($this->tugas->terakhirUntukSiklus($cycleId))]);
    }

    private function validasiMassal(Request $request, bool $denganAlasan): array
    {
        return $request->validate(array_filter([
            'tindakan' => ['required', 'in:'.implode(',', PaRevisiPesertaService::SEMUA_TINDAKAN)],
            'orang' => ['required', 'array', 'min:1', 'max:'.PaRevisiPesertaService::MAKS_ORANG],
            'orang.*.kode_perusahaan' => ['required', 'string', 'max:10'],
            'orang.*.kode_karyawan' => ['required', 'string', 'max:30'],
            'alasan' => $denganAlasan ? ['required', 'string', 'min:5', 'max:500'] : null,
            /* Kecualikan: lepas juga sebagai penilai orang lain dan bentuk penggantinya. */
            'lepas_penilai' => ['nullable', 'boolean'],
        ]));
    }

    public function pratinjau(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'kode_perusahaan' => ['required', 'string', 'max:10'],
            'kode_karyawan' => ['required', 'string', 'max:30'],
        ]);

        return $this->jalankan(fn () => $this->revisi->pratinjau($cycleId, $data['kode_perusahaan'], $data['kode_karyawan']));
    }

    public function jalankanRevisi(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'tindakan' => ['required', 'in:'.implode(',', PaRevisiPesertaService::SEMUA_TINDAKAN)],
            'kode_perusahaan' => ['required', 'string', 'max:10'],
            'kode_karyawan' => ['required', 'string', 'max:30'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
            'lepas_penilai' => ['nullable', 'boolean'],
        ]);

        return $this->jalankan(fn () => $this->revisi->jalankan(
            $data['tindakan'],
            $cycleId,
            $data['kode_perusahaan'],
            $data['kode_karyawan'],
            $data['alasan'],
            Auth::id() === null ? null : (int) Auth::id(),
            true,
            ['lepas_penilai' => (bool) ($data['lepas_penilai'] ?? false)],
        ));
    }

    private function jalankan(callable $operasi)
    {
        try {
            return ResponseHelper::success($operasi(), 'Berhasil');
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }
}
