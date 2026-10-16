<?php

namespace App\Services\LMS\SOP;

use App\Services\SYSTEM\Notification\NotificationService;
use Illuminate\Support\Carbon;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Notifikasi in-app SOP (reuse N_HRIS_Notification via NotificationService).
 * IN-APP saja (tanpa WA/email). Idempoten per notification_key.
 * module='HCIS' (tanpa mengubah file shared), type='SOP_*' untuk pembeda.
 */
class SopNotificationService
{
    /** Penugasan baru → "SOP Baru Untuk Anda" (link ke /sop/saya). */
    public function notifPenugasan(array $kodeList, int $idPaket, string $namaPaket): void
    {
        $this->kirim($kodeList, 'SOP_ASSIGNED', $idPaket, [
            'title' => 'Dokumen Baru Untuk Anda',
            'message' => "Anda mendapat penugasan dokumen \"{$namaPaket}\". Silakan baca dan tandai selesai.",
            'action_url' => '/sop/saya',
            'severity' => 'info',
            'priority' => 3,
        ]);
    }

    /** Kewajiban dibuka kembali (versi/item baru) → "SOP Diperbarui" (link ke detail). */
    public function notifDiperbarui(array $kodeList, int $idPaket, string $namaPaket, ?string $ringkasan = null): void
    {
        $url = '/sop/'.Hashids::connection('custom')->encode($idPaket);
        $msg = "Paket \"{$namaPaket}\" diperbarui — mohon baca ulang bagian yang berubah."
            .($ringkasan ? " ({$ringkasan})" : '');

        $this->kirim($kodeList, 'SOP_UPDATED', $idPaket, [
            'title' => 'Dokumen Diperbarui',
            'message' => $msg,
            'action_url' => $url,
            'severity' => 'warning',
            'priority' => 2,
        ]);
    }

    /**
     * Reminder tenggat (dipakai SopReminderScan).
     *
     * @param  array  $items  [ ['kode'=>, 'id_paket'=>, 'nama'=>, 'tenggat'=>, 'overdue'=>bool], ... ]
     */
    public function notifReminder(array $items): int
    {
        $payloads = [];
        foreach ($items as $it) {
            $kode = trim((string) ($it['kode'] ?? ''));
            if ($kode === '') {
                continue;
            }

            $overdue = (bool) ($it['overdue'] ?? false);
            $title = $overdue ? 'Dokumen Terlambat' : 'Pengingat Dokumen';
            $msg = $overdue
                ? "Paket \"{$it['nama']}\" sudah melewati tenggat ({$it['tenggat']}). Segera selesaikan."
                : "Paket \"{$it['nama']}\" jatuh tempo {$it['tenggat']}. Mohon segera diselesaikan.";

            $payloads[] = $this->payload($kode, 'SOP_REMINDER', (int) $it['id_paket'], [
                'title' => $title,
                'message' => $msg,
                'action_url' => '/sop/saya',
                'severity' => $overdue ? 'danger' : 'warning',
                'priority' => $overdue ? 1 : 2,
            ]);
        }

        if ($payloads) {
            app(NotificationService::class)->upsertManyByKey($payloads);
        }

        return count($payloads);
    }

    /** Satu digest per karyawan per hari, idempoten untuk retry scan di hari yang sama. */
    public function notifReminderDigest(array $groups, ?string $dateBucket = null): int
    {
        $bucket = $dateBucket ?: Carbon::today()->format('Y-m-d');
        $payloads = [];
        foreach ($groups as $kode => $items) {
            $kode = trim((string) $kode);
            if ($kode === '' || ! $items) {
                continue;
            }

            $overdue = collect($items)->where('overdue', true)->count();
            $count = count($items);
            $names = collect($items)->pluck('nama')->filter()->take(2)->implode(', ');
            $extra = $count > 2 ? ' dan '.($count - 2).' lainnya' : '';
            $message = $overdue > 0
                ? "{$count} dokumen memerlukan perhatian ({$overdue} terlambat): {$names}{$extra}."
                : "{$count} dokumen belum selesai: {$names}{$extra}. Lanjutkan dari daftar dokumen Anda.";

            $payloads[] = [
                'kode_karyawan' => $kode,
                'type' => 'SOP_REMINDER_DIGEST',
                'module' => 'HCIS',
                'channel' => NotificationService::DELIVERY_IN_APP,
                'title' => $overdue > 0 ? 'Ringkasan Dokumen Terlambat' : 'Pengingat Dokumen Anda',
                'message' => $message,
                'action_url' => '/sop/saya',
                'severity' => $overdue > 0 ? 'danger' : 'warning',
                'priority' => $overdue > 0 ? 1 : 2,
                'reference_id' => 'SOP-DIGEST-'.$bucket,
                'notification_key' => "SOP_REMINDER_DIGEST:{$bucket}:{$kode}",
                'created_by' => 'SYSTEM_SOP',
                'revive_deleted' => true,
                'meta' => [
                    'source' => 'SOP_AUTOMATION',
                    'date_bucket' => $bucket,
                    'total' => $count,
                    'overdue' => $overdue,
                ],
            ];
        }

        if ($payloads) {
            app(NotificationService::class)->upsertManyByKey($payloads);
        }

        return count($payloads);
    }

    /* ================= internal ================= */

    /**
     * Satu notifikasi untuk satu revisi DOKUMEN, bukan satu per paket — Fase 6
     * (keputusan produk user 2026-08-10).
     *
     * Pengukuran: 58 dari 59 karyawan ditugaskan ke lebih dari satu paket,
     * maksimum 6. Satu dokumen bersama yang direvisi karena itu bisa
     * menghasilkan 6 notifikasi untuk satu orang dalam sekejap.
     *
     * Nama paket tetap DISEBUT di dalam pesannya. Kewajiban karyawan memang
     * per paket — masing-masing punya tenggat dan halamannya sendiri — jadi
     * menggabungkan notifikasinya tak boleh sampai menyembunyikan paket mana
     * saja yang harus ia buka. Yang digabung adalah pemberitahuannya, bukan
     * kewajibannya.
     *
     * $idPaket dipakai untuk tautan: paket pertama yang terdampak, supaya
     * karyawan punya satu pintu masuk yang pasti sah.
     */
    public function notifDokumenDiperbarui(
        array $kodeList,
        int $idPaketUtama,
        string $judulDokumen,
        array $namaPaket,
        ?string $ringkasan = null,
    ): void {
        $url = '/sop/'.Hashids::connection('custom')->encode($idPaketUtama);

        $daftar = implode(', ', array_slice($namaPaket, 0, 3))
            .(count($namaPaket) > 3 ? ' dan '.(count($namaPaket) - 3).' paket lain' : '');

        $msg = "Dokumen \"{$judulDokumen}\" diperbarui — mohon baca ulang bagian yang berubah."
            .($daftar !== '' ? " Terdampak: {$daftar}." : '')
            .($ringkasan ? " ({$ringkasan})" : '');

        $this->kirim($kodeList, 'SOP_UPDATED', $idPaketUtama, [
            'title' => 'Dokumen Diperbarui',
            'message' => $msg,
            'action_url' => $url,
            'severity' => 'warning',
            'priority' => 2,
        ]);
    }

    private function kirim(array $kodeList, string $type, int $idPaket, array $base): void
    {
        $payloads = [];
        foreach (array_unique($kodeList) as $kode) {
            $kode = trim((string) $kode);
            if ($kode === '') {
                continue;
            }
            $payloads[] = $this->payload($kode, $type, $idPaket, $base);
        }
        if ($payloads) {
            app(NotificationService::class)->upsertManyByKey($payloads);
        }
    }

    private function payload(string $kode, string $type, int $idPaket, array $base): array
    {
        return array_merge($base, [
            'kode_karyawan' => $kode,
            'type' => $type,
            'module' => 'HCIS',
            'channel' => NotificationService::DELIVERY_IN_APP,
            'reference_id' => 'SOP-'.$idPaket,
            'notification_key' => $type.':'.$idPaket.':'.$kode,
            'created_by' => 'SYSTEM_SOP',
            'revive_deleted' => true,
            'meta' => ['source' => 'MAIN_NOTIF', 'id_sop' => $idPaket],
        ]);
    }
}
