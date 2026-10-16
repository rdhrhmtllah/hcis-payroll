<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use DomainException;

/**
 * Pengajuan ditolak karena salah satu batas pemohon tercapai.
 *
 * Membawa kode galatnya sendiri, bukan diterjemahkan lagi di controller.
 * Ada tiga batas yang berbeda sebabnya - jatah permit, pengajuan yang masih
 * menunggu keputusan, dan akses inspeksi harian - dan pemohon perlu tahu
 * YANG MANA yang mengenainya, sebab jalan keluarnya berbeda: menunggu
 * keputusan, menunggu besok, atau memang sudah punya cukup permit.
 */
class VisitorPermitQuotaExceeded extends DomainException
{
    public function __construct(
        public readonly int $used,
        public readonly int $limit,
        public readonly string $kode = 'VISITOR_PERMIT_LIMIT_REACHED',
        string $pesan = '',
    ) {
        parent::__construct(
            $pesan !== ''
                ? $pesan
                : "Jatah permit Anda sudah penuh: {$used} dari maksimal {$limit}. "
                    .'Jatah kembali setelah permit ditolak, dicabut, atau kedaluwarsa.',
        );
    }

    /** Pengajuan lain masih menunggu keputusan. */
    public static function pending(int $used, int $limit): self
    {
        return new self(
            $used,
            $limit,
            'VISITOR_PERMIT_PENDING_LIMIT',
            $limit === 1
                ? 'Masih ada pengajuan permit Anda yang menunggu keputusan. '
                    .'Ajukan lagi setelah pengajuan itu disetujui atau ditolak.'
                : "Pengajuan yang menunggu keputusan sudah {$used} dari maksimal {$limit}. "
                    .'Ajukan lagi setelah salah satunya diputus.',
        );
    }

    /** Akses inspeksi hari ini sudah habis. */
    public static function aksesHarian(int $used, int $limit): self
    {
        return new self(
            $used,
            $limit,
            'VISITOR_PERMIT_AKSES_HARIAN',
            "Anda sudah memakai {$used} dari {$limit} kesempatan inspeksi hari ini. "
                .'Silakan ajukan kembali besok, atau hubungi SHE bila memang mendesak.',
        );
    }
}
