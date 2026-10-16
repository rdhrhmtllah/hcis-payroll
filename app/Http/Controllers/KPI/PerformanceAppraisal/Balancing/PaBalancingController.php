<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Balancing;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingException;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingHitungUlangService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKandidatService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKualitatifService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSesiService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingSimulasiService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingTimPenilaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** API Balancing (spec 2026-09-30). Semua penolakan aturan → 422/404/409 dengan `error_code`. */
class PaBalancingController extends Controller
{
    public function __construct(
        private readonly PaBalancingSesiService $sesi,
        private readonly PaBalancingKandidatService $kandidat,
        private readonly PaBalancingSimulasiService $simulasi,
        private readonly PaBalancingHitungUlangService $hitungUlang,
        private readonly PaBalancingPenjaga $penjaga,
        private readonly PaBalancingTimPenilaiService $timPenilai,
        private readonly PaBalancingKualitatifService $kualitatif,
    ) {}

    public function halamanDaftar()
    {
        return inertia('KPI/PerformanceAppraisal/Balancing/BalancingPage');
    }

    public function halamanSesi(int $sesiId)
    {
        return inertia('KPI/PerformanceAppraisal/Balancing/BalancingSesiPage', ['sesiId' => $sesiId]);
    }

    public function daftarSesi(int $cycleId)
    {
        return $this->jalankan(function () use ($cycleId) {
            $buat = $this->sesi->bolehBuat($cycleId);

            return [
                'sesi' => $this->sesi->daftar($cycleId),
                'menunggu_hitung_ulang' => $this->penjaga->sesiFinalBelumDihitung($cycleId)->count(),
                'terbuka' => $this->penjaga->sesiTerbuka($cycleId)->count(),
                'boleh_buat' => $buat['boleh'],
                'alasan_tidak_boleh_buat' => $buat['alasan'],
            ];
        });
    }

    /** Calon tim penilai: semua karyawan aktif, tidak terbatas peserta siklus. */
    public function timPenilai()
    {
        return $this->jalankan(fn () => $this->timPenilai->daftar());
    }

    public function kandidat(Request $request, int $cycleId)
    {
        $s = $request->validate([
            'level_ids' => ['nullable', 'array'], 'level_ids.*' => ['integer'],
            'divisi_ids' => ['nullable', 'array'], 'divisi_ids.*' => ['integer'],
            'sub_divisi_ids' => ['nullable', 'array'], 'sub_divisi_ids.*' => ['integer'],
            'grup_kurva_ids' => ['nullable', 'array'], 'grup_kurva_ids.*' => ['integer'],
            'grade' => ['nullable', 'array'], 'grade.*' => ['string', 'max:40'],
            'kuadran_ids' => ['nullable', 'array'], 'kuadran_ids.*' => ['integer'],
            'jumlah_sesi' => ['nullable', 'integer', 'min:0'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->jalankan(fn () => $this->kandidat->daftar($cycleId, $s));
    }

    public function buatSesi(Request $request, int $cycleId)
    {
        $d = $request->validate([
            'nama' => ['required', 'string', 'max:200'],
            'pic_user_id' => ['required', 'integer'],
            'forum' => ['required', 'array', 'min:1', 'max:30'],
            'forum.*.kode_perusahaan' => ['required', 'string', 'max:3'],
            'forum.*.kode_karyawan' => ['required', 'string', 'max:255'],
            'peserta_ids' => ['required', 'array', 'min:1', 'max:500'],
            'peserta_ids.*' => ['integer'],
        ]);

        return $this->jalankan(fn () => $this->sesi->buat($cycleId, $d, $this->userId()));
    }

    public function detailSesi(int $sesiId)
    {
        return $this->jalankan(fn () => $this->sesi->detail($sesiId));
    }

    public function simpanSesi(Request $request, int $sesiId)
    {
        $d = $request->validate([
            'versi_data' => ['required', 'integer', 'min:1'],
            'catatan_rapat' => ['nullable', 'string', 'max:20000'],
            'peserta' => ['present', 'array'],
            'peserta.*.participant_id' => ['required', 'integer'],
            'peserta.*.status_tinjau' => ['nullable', 'in:BELUM,TANPA_PERUBAHAN'],
            'peserta.*.alasan' => ['nullable', 'string', 'max:1000'],
            'peserta.*.kompetensi' => ['nullable', 'array'],
            'peserta.*.kompetensi.*.competency_id' => ['required', 'integer'],
            'peserta.*.kompetensi.*.nilai_sesudah' => ['required', 'numeric'],
            'peserta.*.kompetensi.*.alasan' => ['nullable', 'string', 'max:1000'],
            'peserta.*.kualitatif' => ['nullable', 'array'],
            'peserta.*.kualitatif.*.competency_id' => ['required', 'integer'],
            'peserta.*.kualitatif.*.isi_sesudah' => ['nullable', 'string', 'max:20000'],
        ]);

        return $this->jalankan(fn () => $this->sesi->simpanDraft(
            $sesiId, (int) $d['versi_data'], $d['peserta'], $d['catatan_rapat'] ?? null, $this->userId(),
        ));
    }

    public function finalkanSesi(Request $request, int $sesiId)
    {
        $d = $request->validate(['versi_data' => ['required', 'integer', 'min:1']]);

        return $this->jalankan(fn () => $this->sesi->finalkan($sesiId, (int) $d['versi_data'], $this->userId()));
    }

    public function batalkanSesi(Request $request, int $sesiId)
    {
        $d = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        return $this->jalankan(fn () => $this->sesi->batalkan($sesiId, $d['alasan'], $this->userId()));
    }

    public function simulasikan(Request $request, int $sesiId)
    {
        $d = $request->validate([
            'peserta' => ['present', 'array'],
            'peserta.*.participant_id' => ['required', 'integer'],
            'peserta.*.kompetensi' => ['nullable', 'array'],
            'peserta.*.kompetensi.*.competency_id' => ['required', 'integer'],
            'peserta.*.kompetensi.*.nilai_sesudah' => ['required', 'numeric'],
        ]);

        return $this->jalankan(fn () => $this->simulasi->simulasikan($sesiId, $d['peserta']));
    }

    public function hitungUlang(int $cycleId)
    {
        // Menghitung ulang peserta sesi satu per satu; beberapa puluh orang bisa melewati 30 detik.
        @set_time_limit(600);

        return $this->jalankan(fn () => $this->hitungUlang->jalankan($cycleId, $this->userId()));
    }

    /** Soal + jawaban penilai untuk kualitatif yang dibahas di Balancing (modal board). */
    public function kualitatifPeserta(int $participantId)
    {
        return $this->jalankan(fn () => $this->kualitatif->untukPeserta($participantId));
    }

    public function riwayatPeserta(int $participantId)
    {
        return $this->jalankan(function () use ($participantId) {
            $sesi = DB::table('N_PA_Balancing_Peserta as bp')
                ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
                ->where('bp.PA_Participant_Id', $participantId)
                ->orderByDesc('s.Id_PA_Balancing_Sesi')
                ->get(['s.Id_PA_Balancing_Sesi']);

            return [
                'sesi' => $sesi->map(function ($s) use ($participantId) {
                    $d = $this->sesi->detail((int) $s->Id_PA_Balancing_Sesi);
                    $orang = collect($d['peserta'])->firstWhere('participant_id', $participantId);

                    return [
                        'id' => $d['id'], 'nama' => $d['nama'], 'status' => $d['status'], 'final_at' => $d['final_at'],
                        'hitung_ulang_at' => $d['hitung_ulang_at'], 'status_tinjau' => $orang['status_tinjau'] ?? null,
                        'kompetensi' => collect($orang['kompetensi'] ?? [])
                            ->map(fn ($k) => ['nama' => $k['nama'], 'sebelum' => $k['sebelum'], 'sesudah' => $k['sesudah'], 'alasan' => $k['alasan']])
                            ->values()->all(),
                    ];
                })->all(),
                'jejak' => DB::table('N_PA_Balancing_Jejak_Hitung')->where('PA_Participant_Id', $participantId)
                    ->orderByDesc('Nomor_Hitung_Ulang')
                    ->get(['Nomor_Hitung_Ulang', 'PA_Balancing_Sesi_Id', 'Final_Score_Sebelum', 'Final_Score_Sesudah',
                        'Kode_Grade_Sebelum', 'Kode_Grade_Sesudah', 'PA_Hav_Quadrant_Sebelum_Id', 'PA_Hav_Quadrant_Sesudah_Id', 'Sebab', 'Created_At'])
                    ->all(),
            ];
        });
    }

    private function jalankan(callable $operasi)
    {
        try {
            return ResponseHelper::success($operasi(), 'Berhasil');
        } catch (PaBalancingException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), $e->errorCode, $e->httpStatus);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'PA_BAL_DITOLAK', 422);
        }
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
