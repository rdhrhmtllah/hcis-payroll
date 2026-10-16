<?php

namespace App\Services\KPI\PerformanceAppraisal;

/**
 * Tugas latar "penjadwalan rater ke HC Learn" — dikerjakan `PenjadwalanHcLearnPaJob`.
 *
 * Sampai Oktober 2026 penjadwalan digerakkan BROWSER: layar memanggil penjadwalan 200 penugasan
 * per putaran sampai habis, lalu menarik hasil HC Learn tiap 20 detik. Untuk siklus produksi
 * (10.000+) admin harus menjaga satu tab tetap terbuka belasan menit, dan menutupnya
 * menghentikan pekerjaan di tengah jalan tanpa jejak di mana pun.
 *
 * Siklus hidup tugasnya di `PaTugasLatarSiklusService`. Di sini yang khas penjadwalan:
 *
 * Payload_Json = {
 *   cycle_id, nama_siklus, user_id,
 *   fase: 'kirim' | 'tunggu',
 *   batch: int,                       // job ke berapa (kirim + tunggu)
 *   mulai_tunggu: ?string,            // kapan fase tunggu dimulai — batas 2 jam dihitung dari sini
 *   ringkasan: {total, terjadwal, antre, gagal, belum},
 *   contoh_galat: list<string>,
 *   aborted?: true,
 * }
 */
class PaPenjadwalanTugasService extends PaTugasLatarSiklusService
{
    public const TASK_TYPE = 'pa-penjadwalan';

    public const MODULE = 'pa-penjadwalan';

    protected const AWALAN_ID = 'pa-jdw';

    protected const FORMAT = 'hc-learn';

    public const FASE_KIRIM = 'kirim';

    public const FASE_TUNGGU = 'tunggu';

    protected function payloadAwal(): array
    {
        return [
            'fase' => self::FASE_KIRIM,
            'batch' => 0,
            'mulai_tunggu' => null,
            'contoh_galat' => [],
        ];
    }

    /** Diulang dari fase kirim dengan jam tunggu baru; nomor batch tetap berlanjut. */
    protected function payloadUlang(array $payload): array
    {
        return array_merge($payload, [
            'fase' => self::FASE_KIRIM,
            'mulai_tunggu' => null,
            'contoh_galat' => [],
        ]);
    }

    protected function diproses(array $r): int
    {
        return (int) ($r['terjadwal'] ?? 0);
    }

    protected function layarTambahan(array $p): array
    {
        return [
            'fase' => $p['fase'] ?? self::FASE_KIRIM,
            'mulai_tunggu' => $p['mulai_tunggu'] ?? null,
        ];
    }

    /**
     * Persen satu angka untuk dua fase: separuh pertama "terkirim", separuh kedua "bertautan".
     *
     * Satu bilangan untuk keduanya, bukan dua bilangan bergantian. Persen yang naik sampai
     * 100 saat kirim selesai lalu turun lagi saat menunggu HC Learn terbaca sebagai kemunduran.
     */
    public static function persen(array $r): int
    {
        $total = (int) ($r['total'] ?? 0);
        if ($total <= 0) {
            return 0;
        }

        $terkirim = $total - (int) ($r['belum'] ?? 0);
        $terjadwal = (int) ($r['terjadwal'] ?? 0);

        return (int) floor(50 * $terkirim / $total + 50 * $terjadwal / $total);
    }
}
