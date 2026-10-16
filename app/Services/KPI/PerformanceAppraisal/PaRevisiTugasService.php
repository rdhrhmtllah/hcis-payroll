<?php

namespace App\Services\KPI\PerformanceAppraisal;

/**
 * Tugas latar "revisi peserta" — banyak orang sekaligus, dikerjakan `RevisiPesertaPaJob`.
 *
 * Revisi satu orang memakan beberapa detik (batalkan, bentuk, kadang ratusan kueri); centang
 * semua hasil filter bisa berarti ratusan orang. Dikerjakan server sedikit demi sedikit,
 * terpantau di panel Tugas & Aktivitas, dan penjadwalan ke HC Learn dikirim SEKALI di akhir.
 *
 * Payload_Json = {
 *   cycle_id, nama_siklus, user_id,
 *   tindakan, alasan, orang: list<{kode_perusahaan, kode_karyawan, nama}>,
 *   indeks: int, batch: int,
 *   hasil: list<{kode_karyawan, nama, ok, pesan, dibatalkan, dibentuk}>,
 *   ringkasan: {total, sudah, berhasil, gagal, dibentuk, dibatalkan},
 *   penjadwalan: ?string,
 * }
 */
class PaRevisiTugasService extends PaTugasLatarSiklusService
{
    public const TASK_TYPE = 'pa-revisi';

    public const MODULE = 'pa-revisi';

    protected const AWALAN_ID = 'pa-rvs';

    protected const FORMAT = 'revisi';

    protected function payloadAwal(): array
    {
        return ['indeks' => 0, 'batch' => 0, 'hasil' => [], 'penjadwalan' => null];
    }

    /* Diulang dari orang pertama yang belum berhasil: yang sudah berhasil dilewati karena
       pratinjaunya kini berkata "tidak perlu" (mis. level sudah sesuai). */
    protected function payloadUlang(array $payload): array
    {
        return array_merge($payload, ['indeks' => 0, 'hasil' => [], 'penjadwalan' => null,
            'ringkasan' => ['total' => count($payload['orang'] ?? []), 'sudah' => 0, 'berhasil' => 0, 'gagal' => 0, 'dibentuk' => 0, 'dibatalkan' => 0]]);
    }

    protected function diproses(array $r): int
    {
        return (int) ($r['sudah'] ?? 0);
    }

    protected function layarTambahan(array $p): array
    {
        return [
            'tindakan' => $p['tindakan'] ?? null,
            'hasil' => $p['hasil'] ?? [],
            'penjadwalan' => $p['penjadwalan'] ?? null,
            /* Penilai baru yang diperiksa/didaftarkan ke HC Learn sebelum dijadwalkan. */
            'hclearn' => $p['hclearn'] ?? null,
        ];
    }

    public static function persen(array $r): int
    {
        $total = (int) ($r['total'] ?? 0);

        return $total <= 0 ? 0 : (int) floor(100 * (int) ($r['sudah'] ?? 0) / $total);
    }
}
