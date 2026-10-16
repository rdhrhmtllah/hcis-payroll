<?php

namespace App\Services\KPI\PerformanceAppraisal;

/**
 * Tugas latar "pembentukan penugasan rater" — dikerjakan `PembentukanPenugasanPaJob`.
 *
 * Sampai Oktober 2026 pembentukan digerakkan BROWSER: 100 peserta per putaran, sekitar 10 detik
 * sekali jalan. Untuk 10.000 peserta itu 15-20 menit dengan tab tetap terbuka, dan galatnya
 * hanya muncul sebagai teks abu di footer — 13 Okt pembentukan "berhenti di 9%" karena database
 * penuh, dan tidak ada yang terbaca sebagai kegagalan.
 *
 * Siklus hidup tugasnya di `PaTugasLatarSiklusService`. Di sini yang khas pembentukan:
 *
 * Payload_Json = {
 *   cycle_id, nama_siklus, user_id,
 *   batch: int,
 *   jalur: 'cepat' | 'lengkapi',      // cepat = peserta tanpa penugasan, berkursor
 *   mulai_dari: ?int,                 // kursor jalur cepat
 *   dibuat: int,                      // penugasan yang terbentuk oleh tugas ini
 *   ringkasan: {total, sudah, belum, penugasan, selesai},   // progresPembentukan()
 *   contoh_galat: list<string>, jumlah_galat: int,
 *   aborted?: true,
 * }
 */
class PaPembentukanTugasService extends PaTugasLatarSiklusService
{
    public const TASK_TYPE = 'pa-pembentukan';

    public const MODULE = 'pa-pembentukan';

    protected const AWALAN_ID = 'pa-btk';

    protected const FORMAT = 'penugasan';

    public const JALUR_CEPAT = 'cepat';

    public const JALUR_LENGKAPI = 'lengkapi';

    protected function payloadAwal(): array
    {
        return [
            'batch' => 0,
            'jalur' => self::JALUR_CEPAT,
            'mulai_dari' => null,
            'dibuat' => 0,
            'contoh_galat' => [],
            'jumlah_galat' => 0,
        ];
    }

    /* Diulang dari awal kursor: yang sudah terbentuk dilewati sendiri oleh pembentukannya. */
    protected function payloadUlang(array $payload): array
    {
        return array_merge($payload, [
            'jalur' => self::JALUR_CEPAT,
            'mulai_dari' => null,
            'contoh_galat' => [],
            'jumlah_galat' => 0,
        ]);
    }

    protected function diproses(array $r): int
    {
        return (int) ($r['sudah'] ?? 0);
    }

    protected function layarTambahan(array $p): array
    {
        return [
            'jalur' => $p['jalur'] ?? self::JALUR_CEPAT,
            'dibuat' => (int) ($p['dibuat'] ?? 0),
            'jumlah_galat' => (int) ($p['jumlah_galat'] ?? 0),
        ];
    }

    public static function persen(array $r): int
    {
        $total = (int) ($r['total'] ?? 0);

        return $total <= 0 ? 0 : (int) floor(100 * (int) ($r['sudah'] ?? 0) / $total);
    }
}
