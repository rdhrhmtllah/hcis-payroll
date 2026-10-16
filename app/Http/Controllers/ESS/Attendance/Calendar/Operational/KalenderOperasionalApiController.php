<?php

namespace App\Http\Controllers\ESS\Attendance\Calendar\Operational;

use App\Http\Controllers\Controller;
use App\Services\ESS\Attendance\Calendar\Operational\KalenderOperasionalService;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API kalender operasional untuk konsumen luar (Web Careers), dipakai menghitung
 * SLA rekrutmen dalam hari kerja.
 *
 * Kontraknya milik konsumen, bukan milik kita: bungkusnya memakai kunci "sukses"
 * (bukan "success"), sehingga ResponseHelper SENGAJA tidak dipakai di sini.
 * Perubahan helper internal tidak boleh pernah merusak Web Careers.
 *
 * Spec: docs/superpowers/specs/2026-09-04-api-kalender-operasional-ho-design.md
 */
class KalenderOperasionalApiController extends Controller
{
    private const UNIT_BAKU = 'HEAD_OFFICE';

    public function __construct(private readonly KalenderOperasionalService $kalender) {}

    /** GET /api/v1/hcis/kalender-operasional/tahun/{tahun} */
    public function tahun(Request $request, string $tahun): JsonResponse
    {
        $minimal = (int) config('kalender_operasional.tahun_minimal', 2020);
        $maksimal = (int) config('kalender_operasional.tahun_maksimal', 2100);

        if (preg_match('/^\d{4}$/', $tahun) !== 1
            || (int) $tahun < $minimal
            || (int) $tahun > $maksimal) {
            return $this->galat("Parameter 'tahun' harus 4 digit antara {$minimal} dan {$maksimal}.");
        }

        $unit = $this->unitDiminta($request);
        if (! $this->kalender->unitDikenal($unit)) {
            return $this->galat("Unit '{$unit}' tidak dikenal.");
        }

        $kalender = $this->kalender->untukRentang($unit, "{$tahun}-01-01", "{$tahun}-12-31");

        return $this->sukses("Kalender operasional {$tahun}", [
            'unit' => $kalender['unit'],
            'tahun' => (int) $tahun,
            'hari_kerja_mingguan' => $kalender['hari_kerja_mingguan'],
            'digenerate_at' => $kalender['digenerate_at'],
            'tanggal_tidak_operasional' => $kalender['tanggal_tidak_operasional'],
        ]);
    }

    /** GET /api/v1/hcis/kalender-operasional?dari=&sampai= */
    public function rentang(Request $request): JsonResponse
    {
        $dari = trim((string) $request->query('dari', ''));
        $sampai = trim((string) $request->query('sampai', ''));

        foreach (['dari' => $dari, 'sampai' => $sampai] as $nama => $nilai) {
            if ($nilai === '') {
                return $this->galat("Parameter '{$nama}' wajib diisi (format YYYY-MM-DD).");
            }
            if (! $this->tanggalSah($nilai)) {
                return $this->galat("Parameter '{$nama}' harus berformat YYYY-MM-DD.");
            }
        }

        if ($sampai < $dari) {
            return $this->galat("Parameter 'sampai' tidak boleh lebih awal dari 'dari'.");
        }

        $batas = (int) config('kalender_operasional.batas_rentang_hari', 400);
        if ($this->jumlahHari($dari, $sampai) > $batas) {
            return $this->galat("Rentang tanggal maksimal {$batas} hari.");
        }

        $unit = $this->unitDiminta($request);
        if (! $this->kalender->unitDikenal($unit)) {
            return $this->galat("Unit '{$unit}' tidak dikenal.");
        }

        $kalender = $this->kalender->untukRentang($unit, $dari, $sampai);

        return $this->sukses("Kalender operasional {$dari} s/d {$sampai}", [
            'unit' => $kalender['unit'],
            'dari' => $dari,
            'sampai' => $sampai,
            'hari_kerja_mingguan' => $kalender['hari_kerja_mingguan'],
            'digenerate_at' => $kalender['digenerate_at'],
            'tanggal_tidak_operasional' => $kalender['tanggal_tidak_operasional'],
        ]);
    }

    private function unitDiminta(Request $request): string
    {
        $unit = strtoupper(trim((string) $request->query('unit', '')));

        return $unit !== '' ? $unit : self::UNIT_BAKU;
    }

    /**
     * Ketat: "2026-13-45" harus ditolak, bukan digeser diam-diam ke 2027-02-14
     * seperti yang dilakukan parser tanggal yang longgar.
     */
    private function tanggalSah(string $nilai): bool
    {
        $tanggal = DateTimeImmutable::createFromFormat('!Y-m-d', $nilai);

        return $tanggal !== false && $tanggal->format('Y-m-d') === $nilai;
    }

    /** Inklusif di kedua ujung: 1 Januari s/d 1 Januari berarti 1 hari. */
    private function jumlahHari(string $dari, string $sampai): int
    {
        return (new DateTimeImmutable($dari))->diff(new DateTimeImmutable($sampai))->days + 1;
    }

    private function sukses(string $message, array $result): JsonResponse
    {
        return response()->json([
            'sukses' => true,
            'message' => $message,
            'result' => $result,
        ], 200);
    }

    private function galat(string $message): JsonResponse
    {
        return response()->json([
            'sukses' => false,
            'message' => $message,
            'result' => null,
        ], 400);
    }
}
