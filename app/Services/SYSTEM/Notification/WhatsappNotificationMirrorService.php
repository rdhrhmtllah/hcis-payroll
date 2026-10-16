<?php

namespace App\Services\SYSTEM\Notification;

use App\Services\KPI\Notification\KpiNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsappNotificationMirrorService
{
    public const SOURCE = 'MAIN_NOTIF';

    public const SCOPE_SEND = 'MAIN_NOTIF_SEND';

    public function __construct(
        protected NotificationService $notificationService,
        protected NotificationRouteRegistry $routeRegistry,
    ) {}

    public function mirrorAttempt(array $payload, array $context = []): ?array
    {
        $recipient = $this->resolveRecipient($payload, $context);
        if (! $recipient) {
            Log::warning('WhatsApp mirror skipped: recipient not resolved', [
                'to' => $payload['to'] ?? null,
                'context_keys' => array_keys($context),
            ]);

            return null;
        }

        $templateName = $this->resolveTemplateName($payload);
        $noTransaksi = trim((string) ($context['no_transaksi'] ?? ($context['No_Transaksi'] ?? '')));
        $notificationKey = $this->buildNotificationKey(
            (string) $recipient['kode_karyawan'],
            $templateName,
            $noTransaksi,
            $payload,
        );
        $this->migrateLegacyNotificationKey(
            (string) $recipient['kode_karyawan'],
            $this->buildLegacyNotificationKey(
                (string) $recipient['kode_karyawan'],
                $templateName,
                $noTransaksi,
                $payload,
            ),
            $notificationKey,
        );
        $display = $this->buildDisplayPayload($payload, $context, $templateName, $noTransaksi, 'ATTEMPT');
        $meta = $this->buildMeta($payload, $context, $recipient, $templateName, 'ATTEMPT');
        $actionUrl = $this->resolveActionUrl($context, $noTransaksi, $templateName, $recipient);
        $meta['action_url'] = $actionUrl;

        $id = $this->notificationService->upsertByKey([
            'kode_karyawan' => $recipient['kode_karyawan'],
            'type' => $this->buildType($templateName),
            'title' => $display['title'],
            'message' => $display['message'],
            'module' => (string) ($context['module'] ?? 'HCIS'),
            'severity' => (string) ($context['severity'] ?? 'info'),
            'action_url' => $actionUrl,
            'reference_id' => $noTransaksi !== '' ? $noTransaksi : $templateName,
            'notification_key' => $notificationKey,
            'priority' => isset($context['priority']) ? (int) $context['priority'] : 3,
            'channel' => NotificationService::DELIVERY_IN_APP,
            'meta' => $meta,
            'created_by' => 'SYSTEM',
            'expired_at' => null,
            'sent_at' => null,
        ]);

        return [
            'id' => $id,
            'kode_karyawan' => $recipient['kode_karyawan'],
            'notification_key' => $notificationKey,
            'template_name' => $templateName,
            'no_transaksi' => $noTransaksi,
            'context' => $context,
            'payload' => $payload,
            'recipient' => $recipient,
        ];
    }

    public function markSuccess(?array $mirror, array $response = []): void
    {
        $this->updateStatus($mirror, 'SUCCESS', ['response' => $this->compactResponse($response)]);
    }

    public function markFailed(?array $mirror, string $reason): void
    {
        $this->updateStatus($mirror, 'FAILED', ['error_message' => $reason]);
    }

    protected function updateStatus(?array $mirror, string $status, array $extraMeta = []): void
    {
        if (! $mirror) {
            return;
        }

        $context = (array) ($mirror['context'] ?? []);
        $payload = (array) ($mirror['payload'] ?? []);
        $recipient = (array) ($mirror['recipient'] ?? []);
        $templateName = (string) ($mirror['template_name'] ?? $this->resolveTemplateName($payload));
        $noTransaksi = (string) ($mirror['no_transaksi'] ?? '');
        $meta = array_merge($this->buildMeta($payload, $context, $recipient, $templateName, $status), $extraMeta);
        $display = $this->buildDisplayPayload($payload, $context, $templateName, $noTransaksi, $status);
        $actionUrl = $this->resolveActionUrl($context, $noTransaksi, $templateName, $recipient);
        $meta['action_url'] = $actionUrl;

        $this->notificationService->upsertByKey([
            'kode_karyawan' => $mirror['kode_karyawan'],
            'type' => $this->buildType($templateName),
            'title' => $display['title'],
            'message' => $display['message'],
            'module' => (string) ($context['module'] ?? 'HCIS'),
            'severity' => $status === 'FAILED' ? 'warning' : (string) ($context['severity'] ?? 'info'),
            'action_url' => $actionUrl,
            'reference_id' => $noTransaksi !== '' ? $noTransaksi : $templateName,
            'notification_key' => $mirror['notification_key'],
            'priority' => isset($context['priority']) ? (int) $context['priority'] : 3,
            'channel' => NotificationService::DELIVERY_IN_APP,
            'meta' => $meta,
            'created_by' => 'SYSTEM',
            'expired_at' => null,
            'sent_at' => null,
        ]);
    }

    protected function resolveRecipient(array $payload, array $context): ?array
    {
        $kodeKaryawan = trim((string) ($context['kode_karyawan'] ?? ($context['Kode_Karyawan'] ?? '')));
        if ($kodeKaryawan !== '') {
            $row = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->first();

            return [
                'kode_karyawan' => $kodeKaryawan,
                'nama' => $context['nama'] ?? ($context['Nama'] ?? ($row->Nama ?? null)),
                'hp' => $context['hp'] ?? ($context['HP'] ?? ($row->HP ?? ($payload['to'] ?? null))),
            ];
        }

        $to = $this->normalizePhone((string) ($context['hp'] ?? ($context['HP'] ?? ($payload['to'] ?? ''))));
        if ($to === '') {
            return null;
        }

        $variants = array_values(array_unique(array_filter([$to, '0'.substr($to, 2), '+'.$to])));
        $row = DB::table('Karyawan')
            ->whereIn('HP', $variants)
            ->orWhereRaw("REPLACE(REPLACE(REPLACE(HP, '+', ''), '-', ''), ' ', '') = ?", [$to])
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'kode_karyawan' => $row->Kode_Karyawan,
            'nama' => $row->Nama ?? null,
            'hp' => $row->HP ?? $to,
        ];
    }

    protected function normalizePhone(string $hp): string
    {
        $hp = preg_replace('/[^0-9+]/', '', trim($hp)) ?? '';
        if (str_starts_with($hp, '+62')) {
            return '62'.substr($hp, 3);
        }
        if (str_starts_with($hp, '0')) {
            return '62'.substr($hp, 1);
        }

        return $hp;
    }

    protected function resolveTemplateName(array $payload): string
    {
        $templateName = trim((string) data_get($payload, 'template.name', ''));

        return $templateName !== '' ? strtoupper($templateName) : 'WA_MESSAGE';
    }

    protected function buildType(string $templateName): string
    {
        $type = $templateName === 'WA_MESSAGE' ? 'WA_MESSAGE' : 'WA_TEMPLATE_'.$this->normalizeToken($templateName);

        return substr($type, 0, 50);
    }

    /**
     * Notification key & type publik agar domain (mis. KpiNotificationService) bisa
     * memakai kunci yang SAMA dengan mirror WA → satu baris notifikasi (dedup), bukan
     * dobel (satu dari domain, satu dari mirror).
     */
    public function notificationKeyFor(string $kodeKaryawan, string $templateName, string $noTransaksi): string
    {
        return $this->buildNotificationKey($kodeKaryawan, strtoupper(trim($templateName)), trim($noTransaksi), []);
    }

    public function notificationTypeFor(string $templateName): string
    {
        return $this->buildType(strtoupper(trim($templateName)));
    }

    protected function resolveActionUrl(
        array $context,
        string $noTransaksi,
        string $templateName,
        array $recipient = [],
    ): ?string {
        $explicitUrl = trim((string) ($context['action_url'] ?? ($context['Action_Url'] ?? ($context['url'] ?? ''))));
        if ($explicitUrl !== '') {
            return $explicitUrl;
        }

        // Penolakan KPI → requester diarahkan ke halaman revisi sesuai DOMAIN
        // No_Transaksi: parameter (PKPI/KPI) → /form-kpi, performance (TAKPI/AKPI)
        // → /update-performance. Pakai registry (prefix-aware), bukan hardcode, agar
        // notif penolakan parameter tidak salah arah ke Update Performance.
        if ($this->normalizeToken($templateName) === 'HCIS_TOLAK_PENGAJUAN_KPI') {
            return $this->routeRegistry->resolve(trim($noTransaksi), NotificationRouteRegistry::STATE_REJECTED)
                ?? '/update-performance';
        }

        // Penugasan training LMS → peserta diarahkan ke katalog training.
        if ($this->normalizeToken($templateName) === 'HCIS_PENUGASAN_LMS') {
            return '/lms/training/catalog';
        }

        $noTransaksi = trim($noTransaksi);
        if ($noTransaksi === '') {
            return null;
        }

        $encodedNoTransaksi = rawurlencode($noTransaksi);
        $normalizedTemplate = $this->normalizeToken($templateName);
        $state = $this->resolveNotificationState($normalizedTemplate);

        return $this->routeRegistry->resolve($noTransaksi, $state) ?? "/homepage?no_transaksi={$encodedNoTransaksi}";
    }

    protected function resolveNotificationState(string $normalizedTemplate): string
    {
        if ($normalizedTemplate === 'CONFIRM_IZIN_TOLAK' || str_ends_with($normalizedTemplate, 'DITOLAK')) {
            return NotificationRouteRegistry::STATE_REJECTED;
        }

        if (
            $normalizedTemplate === 'CONFIRM_IZIN_DITERIMA'
            || str_ends_with($normalizedTemplate, 'DITERIMA')
            || $normalizedTemplate === 'NOTIF_SELESAI_TRANSAKSI'
            || $normalizedTemplate === 'HCIS_NOTIF_APPROVAL_SHIFT_PER_KARYAWAN'
            || $normalizedTemplate === 'NOTIF_GANTI_SHIFT'
        ) {
            return NotificationRouteRegistry::STATE_APPROVED;
        }

        return NotificationRouteRegistry::STATE_PENDING;
    }

    protected function buildNotificationKey(
        string $kodeKaryawan,
        string $templateName,
        string $noTransaksi,
        array $payload,
    ): string {
        return $this->buildNotificationKeyWithSource(
            self::SOURCE,
            $kodeKaryawan,
            $templateName,
            $noTransaksi,
            $payload,
        );
    }

    protected function buildLegacyNotificationKey(
        string $kodeKaryawan,
        string $templateName,
        string $noTransaksi,
        array $payload,
    ): string {
        return $this->buildNotificationKeyWithSource('WA_MIRROR', $kodeKaryawan, $templateName, $noTransaksi, $payload);
    }

    protected function buildNotificationKeyWithSource(
        string $source,
        string $kodeKaryawan,
        string $templateName,
        string $noTransaksi,
        array $payload,
    ): string {
        $parts = [$source, $this->normalizeToken($kodeKaryawan)];

        if ($noTransaksi !== '') {
            $parts[] = $this->normalizeToken($noTransaksi);
            $parts[] = $this->normalizeToken($templateName);

            return implode('|', $parts);
        }

        $parts[] = $this->normalizeToken($templateName);
        $parts[] = substr(sha1(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 0, 16);

        return implode('|', $parts);
    }

    protected function migrateLegacyNotificationKey(
        string $kodeKaryawan,
        string $legacyKey,
        string $notificationKey,
    ): void {
        if ($legacyKey === $notificationKey) {
            return;
        }

        $legacyRow = DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Notification_Key', $legacyKey)
            ->first();

        if (! $legacyRow) {
            return;
        }

        $newRowExists = DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Notification_Key', $notificationKey)
            ->exists();

        if ($newRowExists) {
            DB::table('N_HRIS_Notification')
                ->where('Id_Notification', $legacyRow->Id_Notification)
                ->update([
                    'Is_Read' => 'Y',
                    'Is_Deleted' => 'Y',
                    'Read_At' => now(),
                ]);

            return;
        }

        DB::table('N_HRIS_Notification')
            ->where('Id_Notification', $legacyRow->Id_Notification)
            ->update(['Notification_Key' => $notificationKey]);
    }

    protected function buildMessage(string $templateName, string $noTransaksi, string $status): string
    {
        $statusLabel = match ($status) {
            'SUCCESS' => 'berhasil dikirim',
            'FAILED' => 'gagal dikirim',
            default => 'sedang dikirim',
        };
        $templateLabel = $templateName === 'WA_MESSAGE' ? 'pesan WhatsApp' : 'template '.strtolower($templateName);
        $transactionLabel = $noTransaksi !== '' ? ' untuk transaksi '.$noTransaksi : '';

        return ucfirst($templateLabel).$transactionLabel.' '.$statusLabel.'.';
    }

    protected function buildDisplayPayload(
        array $payload,
        array $context,
        string $templateName,
        string $noTransaksi,
        string $status,
    ): array {
        $contextTitle = trim((string) ($context['title'] ?? ''));
        $contextMessage = trim((string) ($context['message'] ?? ($context['body'] ?? '')));

        if ($contextTitle !== '' || $contextMessage !== '') {
            return [
                'title' => $contextTitle !== '' ? $contextTitle : 'Notifikasi HCIS',
                'message' => $contextMessage !== ''
                        ? $contextMessage
                        : $this->buildMessage($templateName, $noTransaksi, $status),
            ];
        }

        $params = $this->extractBodyTextParameters($payload);
        $normalizedTemplate = $this->normalizeToken($templateName);

        if ($normalizedTemplate === 'NOTIF_APPROVER_HCIS') {
            $transaction = $params[1] ?? $noTransaksi;
            $submissionType = $params[2] ?? 'Pengajuan HCIS';
            $period = $params[3] ?? null;
            $requester = $params[4] ?? 'Karyawan';

            return [
                'title' => $this->compactText("Pengajuan {$submissionType} {$requester}"),
                'message' => $this->compactText(
                    "{$requester} mengajukan {$submissionType}".
                        ($period ? " untuk {$period}" : '').
                        ($transaction
                            ? ". No transaksi {$transaction} menunggu approval Anda."
                            : ' dan menunggu approval Anda.'),
                ),
            ];
        }

        if ($normalizedTemplate === 'NOTIF_LEMBUR') {
            $namaKaryawan = $params[0] ?? 'Karyawan';
            $noTransaksiLembur = $params[1] ?? $noTransaksi;
            $tanggalWaktu = $params[2] ?? null;
            $alasan = $params[3] ?? null;

            $messageParts = ["Halo {$namaKaryawan},"];
            $messageParts[] = 'Anda mendapatkan penugasan lembur';
            if ($tanggalWaktu) {
                $messageParts[] = "pada {$tanggalWaktu}";
            }
            if ($alasan && $alasan !== '-') {
                $messageParts[] = "dengan alasan: {$alasan}";
            }
            $messageParts[] = "No. Transaksi: {$noTransaksiLembur}";

            return [
                'title' => $this->compactText("Penugasan Lembur - {$noTransaksiLembur}"),
                'message' => $this->compactText(implode("\n", $messageParts)),
            ];
        }

        if ($normalizedTemplate === 'NOTIF_REQUEST_TRANSAKSI_TANGGAL') {
            $approverName = $params[0] ?? 'Approver';
            $tipeTransaksi = $params[1] ?? 'Pengajuan';
            $noTransaksiApproval = $params[2] ?? $noTransaksi;
            $namaRequester = $params[3] ?? 'Karyawan';
            $tanggal = $params[4] ?? null;

            $messageParts = ["Halo {$approverName},"];
            $messageParts[] = "{$namaRequester} mengajukan {$tipeTransaksi}";
            if ($tanggal && $tanggal !== '-') {
                $messageParts[] = "untuk {$tanggal}";
            }
            $messageParts[] = "No. Transaksi: {$noTransaksiApproval}";
            $messageParts[] = 'Menunggu persetujuan Anda.';

            return [
                'title' => $this->compactText("Persetujuan {$tipeTransaksi} - {$namaRequester}"),
                'message' => $this->compactText(implode("\n", $messageParts)),
            ];
        }

        if ($normalizedTemplate === 'NOTIF_SELESAI_TRANSAKSI') {
            $namaPenerima = $params[0] ?? 'Karyawan';
            $tipeTransaksi = $params[1] ?? 'Pengajuan';
            $noTransaksiSelesai = $params[2] ?? $noTransaksi;
            $tanggalSelesai = $params[3] ?? null;

            $messageParts = ["Halo {$namaPenerima},"];
            $messageParts[] = "Pengajuan {$tipeTransaksi} Anda";
            if ($tanggalSelesai && $tanggalSelesai !== '-') {
                $messageParts[] = "untuk {$tanggalSelesai}";
            }
            $messageParts[] = "dengan No. Transaksi: {$noTransaksiSelesai} telah selesai diproses.";

            return [
                'title' => $this->compactText("Transaksi Selesai - {$noTransaksiSelesai}"),
                'message' => $this->compactText(implode("\n", $messageParts)),
            ];
        }

        if ($normalizedTemplate === 'NOTIF_GANTI_SHIFT') {
            $namaKaryawan = $params[0] ?? 'Karyawan';
            $tipeShift = $params[1] ?? '';
            $shift = $params[2] ?? '';
            $leaderName = $params[3] ?? 'Leader';
            $tanggalShift = $params[4] ?? null;

            $messageParts = ["Halo {$namaKaryawan},"];
            $shiftLabel = $shift ? "menjadi {$shift}" : '';
            $tipeLabel = $tipeShift ? " ({$tipeShift})" : '';
            if ($shiftLabel) {
                $messageParts[] = "{$leaderName} mengubah shift Anda {$shiftLabel}{$tipeLabel}";
            } else {
                $messageParts[] = "{$leaderName} telah mengubah shift Anda";
            }
            if ($tanggalShift) {
                $messageParts[] = "untuk {$tanggalShift}.";
            }

            return [
                'title' => $this->compactText("Perubahan Shift - {$namaKaryawan}"),
                'message' => $this->compactText(implode("\n", $messageParts)),
            ];
        }

        if ($normalizedTemplate === 'HCIS_APPROVAL_SHIFT_PER_KARYAWAN') {
            // Notif ke atasan/approver: karyawan mengajukan perubahan shift.
            // Params template: [noTransaksi, namaRequester, tanggal]
            $noTransaksiShift = $params[0] ?? $noTransaksi;
            $namaRequester = $params[1] ?? 'Karyawan';
            $tanggalShift = $params[2] ?? null;

            $messageParts = ["Halo, {$namaRequester} mengajukan perubahan shift"];
            if ($tanggalShift && $tanggalShift !== '-') {
                $messageParts[] = "untuk {$tanggalShift}";
            }
            $messageParts[] = 'dan menunggu persetujuan Anda.';
            if ($noTransaksiShift !== '') {
                $messageParts[] = "No. Transaksi: {$noTransaksiShift}";
            }

            return [
                'title' => $this->compactText("Persetujuan Shift - {$namaRequester}"),
                'message' => $this->compactText(implode("\n", $messageParts)),
            ];
        }

        if ($normalizedTemplate === 'HCIS_NOTIF_APPROVAL_SHIFT_PER_KARYAWAN') {
            $noTransaksiShift = $params[0] ?? $noTransaksi;
            $tanggalApproval = $params[1] ?? null;

            $messageParts = [];
            $messageParts[] = 'Approval shift Anda';
            if ($tanggalApproval) {
                $messageParts[] = "untuk {$tanggalApproval}";
            }
            $messageParts[] = "dengan No. Transaksi: {$noTransaksiShift} telah diproses.";
            $messageParts[] = 'Silakan cek detailnya.';

            return [
                'title' => $this->compactText("Status Approval Shift - {$noTransaksiShift}"),
                'message' => $this->compactText(implode("\n", $messageParts)),
            ];
        }

        if (in_array($normalizedTemplate, ['CONFIRM_IZIN_DITERIMA', 'CONFIRM_IZIN_TOLAK'], true)) {
            $submission = $params[1] ?? ($noTransaksi !== '' ? "pengajuan {$noTransaksi}" : 'pengajuan Anda');
            $isApproved = $normalizedTemplate === 'CONFIRM_IZIN_DITERIMA';

            return [
                'title' => $isApproved ? 'Pengajuan Disetujui' : 'Pengajuan Ditolak',
                'message' => $this->compactText(
                    "{$submission} ".($isApproved ? 'telah disetujui.' : 'telah ditolak.'),
                ),
            ];
        }

        // KPI template family: tangani semua varian (pengajuan, diterima, ditolak)
        // Gunakan prefix-match agar lebih robust terhadap selisih normalisasi.
        if (str_starts_with($normalizedTemplate, 'KPI_APPROVAL_PENGAJUAN_DAN_PERFORMANCE')) {
            // --- DITERIMA: pengajuan disetujui penuh, notif ke requester ---
            // Params: [namaPenerima, jenisPengajuan]
            if (str_ends_with($normalizedTemplate, '_DITERIMA')) {
                $namaPenerima = $params[0] ?? 'Karyawan';
                $jenisPengajuan = $params[1] ?? 'Pengajuan KPI';

                return [
                    'title' => $this->compactText("Pengajuan Disetujui - {$jenisPengajuan}"),
                    'message' => $this->compactText(
                        "Pengajuan {$jenisPengajuan} Anda telah disetujui.".
                            ($noTransaksi !== '' ? "\nNo. Transaksi: {$noTransaksi}" : ''),
                    ),
                ];
            }

            // --- DITOLAK: pengajuan ditolak, notif ke requester ---
            // Params: [namaPenerima, judulPengajuan, alasanTolak]
            if (str_ends_with($normalizedTemplate, '_DITOLAK')) {
                $namaPenerima = $params[0] ?? 'Karyawan';
                $judulPengajuan = $params[1] ?? 'Pengajuan KPI';
                $alasanTolak = $params[2] ?? '';

                return [
                    'title' => $this->compactText("Pengajuan Ditolak - {$judulPengajuan}"),
                    'message' => $this->compactText(
                        "Pengajuan {$judulPengajuan} Anda telah ditolak.".
                            ($noTransaksi !== '' ? "\nNo. Transaksi: {$noTransaksi}" : '').
                            ($alasanTolak !== '' ? "\nAlasan: {$alasanTolak}" : ''),
                    ),
                ];
            }

            // --- Default: permintaan persetujuan ke approver ---
            // Params: [namaApprover, level, jenisPengajuan, namaPengaju]
            $approverName = $params[0] ?? 'Approver';
            $jenisPengajuan = $params[2] ?? 'Pengajuan KPI';
            $namaPengaju = $params[3] ?? 'Karyawan';

            return [
                'title' => $this->compactText("Persetujuan {$jenisPengajuan} - {$namaPengaju}"),
                'message' => $this->compactText(
                    "{$namaPengaju} mengajukan {$jenisPengajuan}.\n".
                        "No. Transaksi: {$noTransaksi}\n".
                        'Menunggu persetujuan Anda.',
                ),
            ];
        }

        // Penolakan Update Performance KPI (perlu revisi), notif ke requester.
        // Params template: [Nama, Jenis Pengajuan, No_Transaksi, Nama Approver]
        if ($normalizedTemplate === 'HCIS_TOLAK_PENGAJUAN_KPI') {
            $nama = $params[0] ?? 'Karyawan';
            $jenis = $params[1] ?? 'Update Performance KPI';
            $noTrx = $params[2] ?? $noTransaksi;
            $approver = $params[3] ?? 'Approver';

            return [
                'title' => $this->compactText("{$jenis} Ditolak — Perlu Revisi"),
                'message' => $this->compactText(
                    "Halo {$nama}, pengajuan {$jenis} Anda".
                        ($noTrx !== '' ? " (No. {$noTrx})" : '').
                        " ditolak oleh {$approver}. Silakan periksa catatan penolakan, lakukan perbaikan, lalu ajukan kembali.",
                ),
            ];
        }

        // Penugasan training LMS, notif ke peserta. Params: [nama, nama training].
        // Sengaja status-agnostic: tampil sebagai info penugasan, bukan log "berhasil dikirim".
        if ($normalizedTemplate === 'HCIS_PENUGASAN_LMS') {
            $namaPeserta = $params[0] ?? 'Karyawan';
            $namaTraining = $params[1] ?? 'training';

            return [
                'title' => 'Penugasan Training Baru',
                'message' => $this->compactText(
                    "Halo {$namaPeserta}, Anda mendapat penugasan training \"{$namaTraining}\". ".
                        'Selesaikan seluruh pembelajaran hingga 100% termasuk pre-test & post-test. Selamat belajar!',
                ),
            ];
        }

        return [
            'title' => 'Notifikasi HCIS',
            'message' => $this->buildMessage($templateName, $noTransaksi, $status),
        ];
    }

    protected function extractBodyTextParameters(array $payload): array
    {
        $components = (array) data_get($payload, 'template.components', []);

        foreach ($components as $component) {
            if (strtolower((string) ($component['type'] ?? '')) !== 'body') {
                continue;
            }

            return collect($component['parameters'] ?? [])
                ->map(fn ($parameter) => trim((string) ($parameter['text'] ?? '')))
                ->filter(fn ($value) => $value !== '')
                ->values()
                ->all();
        }

        return [];
    }

    protected function compactText(string $value): string
    {
        return preg_replace('/\s+/', ' ', trim($value)) ?: $value;
    }

    protected function buildMeta(
        array $payload,
        array $context,
        array $recipient,
        string $templateName,
        string $status,
    ): array {
        return [
            'source' => self::SOURCE,
            'scope' => self::SCOPE_SEND,
            'delivery_channel' => 'WHATSAPP',
            'wa_status' => $status,
            'template_name' => $templateName,
            'template_params' => $this->extractBodyTextParameters($payload),
            'to' => $payload['to'] ?? ($context['hp'] ?? null),
            'kode_karyawan' => $recipient['kode_karyawan'] ?? null,
            'nama' => $recipient['nama'] ?? null,
            'no_transaksi' => $context['no_transaksi'] ?? ($context['No_Transaksi'] ?? null),
            'source_context' => $context['source_context'] ?? null,
            'attempted_at' => now()->toIso8601String(),
        ];
    }

    protected function compactResponse(array $response): array
    {
        return [
            'message_id' => data_get($response, 'messages.0.id'),
            'message_status' => data_get($response, 'messages.0.message_status'),
        ];
    }

    protected function normalizeToken(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9\-]+/', '_', $value) ?: '';

        return trim($value, '_');
    }
}
