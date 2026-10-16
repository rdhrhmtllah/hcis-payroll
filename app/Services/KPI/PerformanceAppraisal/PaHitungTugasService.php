<?php

namespace App\Services\KPI\PerformanceAppraisal;

/**
 * Perhitungan hasil satu siklus sebagai TUGAS LATAR (tombol "Hitung" di Pemantauan).
 *
 * Sampai 14 Oktober 2026 layar mencicil 100 peserta per permintaan dan mengulanginya dari
 * browser: pada 1.600 peserta itu belasan menit dengan tab yang wajib tetap terbuka, dan tab
 * yang tertutup meninggalkan separuh siklus terhitung. Kini `HitungSiklusPaJob` mengerjakannya
 * potongan demi potongan di antrean; layar hanya memulai dan membaca kemajuan.
 *
 * Ringkasan: total = peserta yang akan diambil saat tugas dimulai, sudah = yang sudah diproses.
 */
class PaHitungTugasService extends PaTugasLatarSiklusService
{
    public const TASK_TYPE = 'pa-hitung';

    public const MODULE = 'pa-hitung';

    protected const AWALAN_ID = 'pa-htg';

    protected const FORMAT = 'hasil';

    protected function payloadAwal(): array
    {
        return [
            'batch' => 0,
            'lingkup' => PaResultCalculationService::LINGKUP_SEMUA,
            'mulai_dari' => null,
            'dihitung' => 0,
            'tertahan' => 0,
            'contoh_galat' => [],
            'jumlah_galat' => 0,
        ];
    }

    /* Coba Lagi MELANJUTKAN dari kursor: yang sudah dihitung tidak dihitung dua kali. */
    protected function payloadUlang(array $payload): array
    {
        return array_merge($payload, ['contoh_galat' => []]);
    }

    protected function diproses(array $r): int
    {
        return (int) ($r['sudah'] ?? 0);
    }

    protected function layarTambahan(array $p): array
    {
        return [
            'lingkup' => $p['lingkup'] ?? PaResultCalculationService::LINGKUP_SEMUA,
            'dihitung' => (int) ($p['dihitung'] ?? 0),
            'tertahan' => (int) ($p['tertahan'] ?? 0),
            'jumlah_galat' => (int) ($p['jumlah_galat'] ?? 0),
        ];
    }

    public static function persen(array $r): int
    {
        $total = (int) ($r['total'] ?? 0);

        return $total <= 0 ? 0 : (int) min(100, floor(100 * (int) ($r['sudah'] ?? 0) / $total));
    }
}
