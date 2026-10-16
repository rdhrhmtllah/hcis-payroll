<?php

namespace App\Services\SYSTEM\Notification;

use App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService;
use App\Services\KPI\Notification\KpiNotificationService;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registry terpusat untuk routing URL notifikasi.
 *
 * Memetakan jenis transaksi (berdasarkan prefix No_Transaksi) ke URL tujuan
 * untuk tiga keadaan notifikasi:
 *   - pending  : notifikasi ke approver (maju ke halaman approval)
 *   - rejected : notifikasi ke requester (balik ke halaman pengajuan)
 *   - approved : notifikasi ke requester (riwayat/detail)
 *
 * Hardcode untuk sementara (tanpa migrasi DB). Sengaja diisolasi sebagai satu
 * kelas agar mudah dipindah ke tabel master (mis. N_HRIS_Transaction_Type_Config)
 * di masa depan tanpa mengubah pemanggil.
 *
 * Catatan: cabang group/admin approval (mis. /admin/cuti untuk cuti, /kpi-verifikasi
 * untuk verifikator AKPI) sudah ditangani oleh override `action_url` eksplisit di
 * CutiNotificationService & KpiNotificationService, sehingga TIDAK perlu diulang
 * di sini - registry hanya menyediakan tujuan default.
 */
class NotificationRouteRegistry
{
    public const STATE_PENDING = 'pending';

    public const STATE_REJECTED = 'rejected';

    public const STATE_APPROVED = 'approved';

    /**
     * Peta routing per jenis transaksi.
     *
     * Tiap entri: 'prefixes' => string[], lalu URL per state. Nilai URL boleh
     * berupa string statis atau Closure(string $noTransaksi): string untuk kasus
     * dinamis (mis. shift leader vs self). URL akan otomatis ditempeli
     * `?no_transaksi={encoded}` oleh appendNoTransaksi().
     *
     * @return array<int, array<string, mixed>>
     */
    protected function map(): array
    {
        // Routing dinamis shift: tujuan requester tergantung Jenis_Transaksi.
        $shiftRequesterUrl = fn (string $noTransaksi): string => $this->resolveShiftRequesterUrl($noTransaksi);

        return [
            // === IZIN / CUTI ===
            [
                'prefixes' => ['CT'],
                self::STATE_PENDING => '/izinLevelUp',
                self::STATE_REJECTED => '/cuti',
                self::STATE_APPROVED => '/cuti',
            ],
            [
                'prefixes' => ['IS', 'IP', 'TP', 'TL', 'PC'],
                self::STATE_PENDING => '/izinLevelUp',
                self::STATE_REJECTED => '/izin',
                self::STATE_APPROVED => '/izin',
            ],

            // === SHIFT ===
            [
                'prefixes' => ['SH'],
                self::STATE_PENDING => '/shift-approval',
                self::STATE_REJECTED => $shiftRequesterUrl,
                self::STATE_APPROVED => $shiftRequesterUrl,
            ],
            [
                'prefixes' => ['TH'],
                self::STATE_PENDING => '/shift-approval',
                self::STATE_REJECTED => '/self-shift',
                self::STATE_APPROVED => '/self-shift',
            ],
            [
                // Status Hari: tabel terpisah tanpa Jenis_Transaksi -> selalu leader-shift.
                'prefixes' => ['TSH'],
                self::STATE_PENDING => '/shift-approval',
                self::STATE_REJECTED => '/leader-shift',
                self::STATE_APPROVED => '/leader-shift',
            ],
            [
                'prefixes' => ['KL'],
                self::STATE_PENDING => '/shift-approval',
                self::STATE_REJECTED => '/konversi-lembur',
                self::STATE_APPROVED => '/konversi-lembur',
            ],

            // === LEMBUR ===
            [
                'prefixes' => ['LM'],
                self::STATE_PENDING => '/lembur-management',
                self::STATE_REJECTED => '/lembur-management',
                self::STATE_APPROVED => '/lembur-management',
            ],

            // === LMS (penugasan training) ===
            // Prefix 'LMS' HARUS lebih panjang dari 'LM' (lembur) agar No_Transaksi
            // "LMS-xxxx" tidak salah cocok ke lembur (matchEntry pakai prefix terpanjang).
            [
                'prefixes' => ['LMS'],
                self::STATE_PENDING => '/lms/training/catalog',
                self::STATE_REJECTED => '/lms/training/catalog',
                self::STATE_APPROVED => '/lms/training/catalog',
            ],

            // === KPI ===
            [
                // PKPI sebelum KPI ditangani oleh pengurutan prefix terpanjang dulu.
                'prefixes' => ['PKPI', 'KPI'],
                self::STATE_PENDING => '/kpi-approval',
                self::STATE_REJECTED => '/form-kpi',
                self::STATE_APPROVED => '/form-kpi',
            ],
            [
                'prefixes' => ['TAKPI', 'AKPI'],
                self::STATE_PENDING => '/performance-approval',
                self::STATE_REJECTED => '/update-performance',
                self::STATE_APPROVED => '/update-performance',
            ],
        ];
    }

    /**
     * Resolve URL tujuan untuk sebuah No_Transaksi pada keadaan tertentu.
     *
     * @param  string  $state  salah satu dari STATE_PENDING|STATE_REJECTED|STATE_APPROVED
     * @return string|null URL (sudah berisi ?no_transaksi=...), atau null jika prefix tak dikenal
     */
    public function resolve(string $noTransaksi, string $state): ?string
    {
        $noTransaksi = trim($noTransaksi);
        if ($noTransaksi === '') {
            return null;
        }

        $entry = $this->matchEntry($noTransaksi);
        if ($entry === null) {
            return null;
        }

        $url = $entry[$state] ?? $entry[self::STATE_PENDING] ?? null;
        if ($url === null) {
            return null;
        }

        if ($url instanceof Closure) {
            $url = $url($noTransaksi);
        }

        return $this->appendNoTransaksi((string) $url, $noTransaksi);
    }

    /**
     * Cari entri yang prefix-nya cocok dengan No_Transaksi, mencocokkan prefix
     * TERPANJANG lebih dulu agar tidak ambigu (TSH sebelum TH/TP, PKPI sebelum KPI,
     * TAKPI sebelum AKPI, dst).
     *
     * @return array<string, mixed>|null
     */
    protected function matchEntry(string $noTransaksi): ?array
    {
        $upper = strtoupper($noTransaksi);

        $candidates = [];
        foreach ($this->map() as $entry) {
            foreach ($entry['prefixes'] as $prefix) {
                $candidates[] = ['prefix' => strtoupper($prefix), 'entry' => $entry];
            }
        }

        // Urutkan prefix dari yang terpanjang ke terpendek.
        usort($candidates, fn ($a, $b) => strlen($b['prefix']) <=> strlen($a['prefix']));

        foreach ($candidates as $candidate) {
            if (str_starts_with($upper, $candidate['prefix'])) {
                return $candidate['entry'];
            }
        }

        return null;
    }

    protected function appendNoTransaksi(string $url, string $noTransaksi): string
    {
        $encoded = rawurlencode($noTransaksi);
        $separator = str_contains($url, '?') ? '&' : '?';

        return "{$url}{$separator}no_transaksi={$encoded}";
    }

    /**
     * Tentukan halaman requester untuk transaksi shift berdasarkan Jenis_Transaksi.
     * LEADER_SHIFT* -> /leader-shift, selain itu -> /self-shift.
     */
    protected function resolveShiftRequesterUrl(string $noTransaksi): string
    {
        try {
            $jenis = (string) DB::table('N_HRIS_Transaksi_Shift')
                ->where('No_Transaksi', $noTransaksi)
                ->value('Jenis_Transaksi');

            return str_starts_with(strtoupper($jenis), 'LEADER_SHIFT') ? '/leader-shift' : '/self-shift';
        } catch (\Throwable $e) {
            Log::warning('Gagal resolve URL requester shift', [
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);

            return '/self-shift';
        }
    }
}
