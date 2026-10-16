<?php

namespace App\Services\ESS\Attendance\Leave\Notification;

use App\Services\SYSTEM\Governance\Approval\Shared\NotifikasiKolamRole;
use App\Services\SYSTEM\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Helper IN_APP notification untuk modul cuti.
 *
 * - notifyAccrual(): notif ke karyawan ketika saldo cuti bertambah / berubah
 *   karena akrual bulanan, loyalty, matured payment, atau promosi Kartap.
 *   Idempotent berbasis Notification_Key.
 *
 * - notifyRolePoolForCurrentStep(): notif ke seluruh member role pool ketika
 *   sebuah pengajuan cuti berada pada step approval bertipe ROLE (mis. setelah
 *   atasan approve atau pada submit jika step pertama adalah ROLE). Dipakai
 *   sebagai pengganti WhatsApp untuk admin pool — sesuai keputusan: role pool
 *   tidak dapat WA, hanya IN_APP supaya admin bisa langsung notice.
 */
class CutiNotificationService
{
    public const TYPE_ACCRUAL = 'CUTI_ACCRUAL';

    public const TYPE_ROLE_REVIEW = 'CUTI_ROLE_REVIEW';

    public const SOURCE_MONTHLY = 'MONTHLY';

    public const SOURCE_LOYALTY = 'LOYALTY';

    public const SOURCE_MATURED = 'MATURED';

    public const SOURCE_PROMOTION = 'PROMOTION';

    /** Source value yang muncul di topbar (filter InertiaShellService). */
    public const META_SOURCE = 'MAIN_NOTIF';

    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Kirim notifikasi IN_APP untuk akrual / mutasi saldo cuti.
     *
     * $payload:
     *   - source: MONTHLY|LOYALTY|MATURED|PROMOTION  (required)
     *   - jumlah: float|int|null
     *   - jenisCutiNama: string (default: Cuti)
     *   - periodKey: string|null (mis. 2026-06)
     *   - refNo: string|null (No_Transaksi_Ref)
     *   - notificationKey: string|null (kalau kosong dibuat otomatis)
     *   - title|message|actionUrl: override copy default
     *   - meta: array tambahan
     */
    public function notifyAccrual(string $kodeKaryawan, array $payload): ?int
    {
        $kodeKaryawan = trim($kodeKaryawan);
        if ($kodeKaryawan === '') {
            return null;
        }

        try {
            $source = strtoupper(trim((string) ($payload['source'] ?? '')));
            $jumlah = $payload['jumlah'] ?? null;
            $periodKey = $payload['periodKey'] ?? null;
            $refNo = $payload['refNo'] ?? null;
            $cutiKey = $payload['cutiKey'] ?? null;

            // Resolve nama jenis cuti dari DB kalau caller tidak set, supaya
            // selalu match dengan naming production di N_HRIS_Jenis_Cuti.
            $jenisCutiNama = trim((string) ($payload['jenisCutiNama'] ?? ''));
            if ($jenisCutiNama === '') {
                $ids = [];
                if (! empty($payload['idJenisCuti'])) {
                    $ids[] = (int) $payload['idJenisCuti'];
                }
                if (! empty($payload['idJenisCutis']) && is_array($payload['idJenisCutis'])) {
                    foreach ($payload['idJenisCutis'] as $id) {
                        if ((int) $id > 0) {
                            $ids[] = (int) $id;
                        }
                    }
                }
                $jenisCutiNama = $this->resolveJenisCutiNames($ids) ?: 'Cuti';
            }

            $notifKey =
                $payload['notificationKey'] ??
                $this->buildAccrualKey($source, $kodeKaryawan, $cutiKey, $periodKey, $refNo);

            [$defaultTitle, $defaultMessage, $defaultSeverity] = $this->resolveAccrualCopy(
                $source,
                $jumlah,
                $jenisCutiNama,
            );

            return $this->notificationService->upsertByKey([
                'kode_karyawan' => $kodeKaryawan,
                'type' => self::TYPE_ACCRUAL,
                'title' => $payload['title'] ?? $defaultTitle,
                'message' => $payload['message'] ?? $defaultMessage,
                'module' => 'HCIS',
                'severity' => $payload['severity'] ?? $defaultSeverity,
                'action_url' => $payload['actionUrl'] ?? '/cuti',
                'channel' => NotificationService::DELIVERY_IN_APP,
                'notification_key' => $notifKey,
                'reference_id' => $refNo,
                'created_by' => $payload['createdBy'] ?? 'SYSTEM_SCHEDULER',
                'priority' => $payload['priority'] ?? 3,
                'meta' => array_merge(
                    [
                        'source' => self::META_SOURCE,
                        'event_type' => $source,
                        'jumlah' => $jumlah,
                        'period_key' => $periodKey,
                        'ref_no' => $refNo,
                        'cuti_key' => $cutiKey,
                        'jenis_cuti' => $jenisCutiNama,
                    ],
                    (array) ($payload['meta'] ?? []),
                ),
            ]);
        } catch (\Throwable $e) {
            Log::warning('CutiNotificationService::notifyAccrual gagal', [
                'kode_karyawan' => $kodeKaryawan,
                'source' => $payload['source'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Notif IN_APP ke seluruh member kolam role kalau langkah approval yang
     * sedang berjalan bertipe ROLE. Tidak melakukan apa-apa kalau langkahnya
     * bukan ROLE atau role tidak punya member aktif.
     *
     * Pipa-nya milik NotifikasiKolamRole dan dipakai bersama modul KPI; yang
     * tinggal di sini hanya bahasa domain cuti.
     *
     * Context optional: requesterNama, jenisCutiNama (dipakai untuk melewati
     * kueri lookup kalau caller sudah memegangnya).
     *
     * @return int jumlah notif yang dikirim/di-refresh
     */
    public function notifyRolePoolForCurrentStep(string $noTransaksi, array $context = []): int
    {
        return app(NotifikasiKolamRole::class)->siarkan(
            $noTransaksi,
            self::TYPE_ROLE_REVIEW,
            'cutiController',
            function (object $langkah) use ($noTransaksi, $context): array {
                [$requesterNama, $jenisCutiNama] = $this->resolveContextLabels($noTransaksi, $context);

                return [
                    'title' => 'Pengajuan Cuti Menunggu Verifikasi',
                    'message' => "{$requesterNama} mengajukan {$jenisCutiNama} (No. {$noTransaksi}). Mohon ditinjau.",
                    'module' => 'HCIS',
                    'severity' => 'warning',
                    'action_url' => "/admin/cuti?tab=pengajuan&no_transaksi={$noTransaksi}",
                    'meta' => [
                        'source' => self::META_SOURCE,
                        'event_type' => self::TYPE_ROLE_REVIEW,
                        'no_transaksi' => $noTransaksi,
                        'requester_nama' => $requesterNama,
                        'jenis_cuti' => $jenisCutiNama,
                        'role_code' => $langkah->role_code ?? null,
                        'id_role' => (int) $langkah->Group_Reference_Id,
                        'id_approval_request' => (int) $langkah->id_approval_request,
                        'order_flow' => (int) ($langkah->Request_Order_Flow ?? 0),
                    ],
                ];
            },
        );
    }

    /**
     * Tandai semua notif kolam role untuk transaksi ini sebagai selesai
     * (dismissed + read). Dipanggil setelah admin approve/reject supaya tray
     * admin lain ikut bersih.
     */
    public function resolveRolePoolNotifications(string $noTransaksi): int
    {
        return app(NotifikasiKolamRole::class)->selesaikan($noTransaksi, self::TYPE_ROLE_REVIEW);
    }

    protected function resolveContextLabels(string $noTransaksi, array $context): array
    {
        $requesterNama = trim((string) ($context['requesterNama'] ?? ''));
        $jenisCutiNama = trim((string) ($context['jenisCutiNama'] ?? ''));

        if ($requesterNama !== '' && $jenisCutiNama !== '') {
            return [$requesterNama, $jenisCutiNama];
        }

        $row = DB::table('Transaksi_Cuti_Detail as tcd')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'tcd.Kode_Karyawan')
            ->leftJoin('N_HRIS_Jenis_Cuti as jc', 'jc.Id_Jenis_Cuti', '=', 'tcd.Id_Jenis_Cuti')
            ->where('tcd.No_Transaksi', $noTransaksi)
            ->select('k.Nama as nama', 'jc.Nama_Jenis_Cuti as jenis')
            ->first();

        if ($requesterNama === '') {
            $requesterNama = (string) ($row->nama ?? 'Karyawan');
        }
        if ($jenisCutiNama === '') {
            $jenisCutiNama = (string) ($row->jenis ?? 'Cuti');
        }

        return [$requesterNama, $jenisCutiNama];
    }

    /**
     * Lookup nama Jenis Cuti dari N_HRIS_Jenis_Cuti by Id. Dedup + sort, dipisah
     * koma untuk multiple ids. Return null kalau tidak ditemukan.
     */
    protected function resolveJenisCutiNames(array $ids): ?string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        if (empty($ids)) {
            return null;
        }

        try {
            $names = DB::table('N_HRIS_Jenis_Cuti')
                ->whereIn('Id_Jenis_Cuti', $ids)
                ->orderBy('Nama_Jenis_Cuti')
                ->pluck('Nama_Jenis_Cuti')
                ->filter(fn ($n) => trim((string) $n) !== '')
                ->values()
                ->all();

            if (empty($names)) {
                return null;
            }

            return implode(', ', $names);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function buildAccrualKey(
        string $source,
        string $kode,
        ?string $cutiKey,
        ?string $periodKey,
        ?string $refNo,
    ): string {
        if ($cutiKey) {
            return 'CUTI_ACCRUAL:'.strtoupper($source).':'.$cutiKey;
        }

        $parts = ['CUTI_ACCRUAL', strtoupper($source), strtoupper($kode)];
        if ($periodKey) {
            $parts[] = $periodKey;
        }
        if ($refNo) {
            $parts[] = $refNo;
        }

        return implode(':', $parts);
    }

    protected function resolveAccrualCopy(string $source, $jumlah, string $jenisCutiNama): array
    {
        $jumlahStr = $jumlah === null ? '' : (string) $jumlah;

        return match ($source) {
            self::SOURCE_MONTHLY => [
                'Saldo Cuti Bertambah',
                "Jatah {$jenisCutiNama} bulanan Anda bertambah {$jumlahStr} hari.",
                'success',
            ],
            self::SOURCE_LOYALTY => [
                'Reward Loyalty Diterima',
                "Selamat! Anda mendapat tambahan {$jumlahStr} hari {$jenisCutiNama} sebagai reward masa kerja.",
                'success',
            ],
            self::SOURCE_MATURED => [
                'Saldo Cuti Cair',
                "Saldo {$jenisCutiNama} Anda yang ditangguhkan telah aktif. {$jumlahStr} hari otomatis digunakan untuk melunasi hutang cuti.",
                'info',
            ],
            self::SOURCE_PROMOTION => [
                'Saldo Cuti Terbuka (Kartap)',
                ((float) $jumlah) > 0
                    ? "Selamat menjadi Karyawan Tetap! Saldo {$jenisCutiNama} Anda kini aktif. {$jumlahStr} hari digunakan untuk melunasi hutang cuti."
                    : "Selamat menjadi Karyawan Tetap! Saldo {$jenisCutiNama} Anda kini aktif dan dapat digunakan.",
                'success',
            ],
            default => [
                'Update Saldo Cuti',
                "Ada perubahan saldo {$jenisCutiNama} sebanyak {$jumlahStr} hari.",
                'info',
            ],
        };
    }
}
