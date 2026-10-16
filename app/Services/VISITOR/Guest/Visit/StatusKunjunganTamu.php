<?php

namespace App\Services\VISITOR\Guest\Visit;

/**
 * Satu-satunya tempat kode status kunjungan tamu ditulis.
 *
 * Dua kelompok yang sengaja dibedakan:
 *
 *  - TERSIMPAN di N_EMI_VISITOR_Transaksi_Tamu.Status_Kunjungan: keputusan
 *    manusia (pengajuan, persetujuan, penolakan, penarikan, pencabutan).
 *  - TURUNAN (CHECKED_IN, CHECKED_OUT, CANCELLED): dihitung saat dibaca dari
 *    jam palang di N_VISITOR_Tamu_Efektif dan Estimasi Keluar. HCIS tidak
 *    pernah menerima peristiwa palang, jadi menyimpannya berarti menyimpan
 *    salinan yang pasti tertinggal. Lihat VisitorKehadiranQueryService.
 *
 * Tidak dijaga CHECK constraint di basis data (AGENTS.md: daftar nilai bisnis
 * yang bisa berkembang divalidasi di backend).
 */
final class StatusKunjunganTamu
{
    public const PENDING_APPROVAL = 'PENDING_APPROVAL';

    public const SCHEDULED = 'SCHEDULED';

    public const REJECTED = 'REJECTED';

    public const WITHDRAWN = 'WITHDRAWN';

    public const REVOKED = 'REVOKED';

    public const CHECKED_IN = 'CHECKED_IN';

    public const CHECKED_OUT = 'CHECKED_OUT';

    public const CANCELLED = 'CANCELLED';

    /**
     * Tercatat masuk, tak pernah tercatat keluar, dan kunjungannya sudah berakhir
     * di gerbang. Status TAMPIL saja: jam keluarnya sengaja tidak diisi karangan
     * (celah #5, keputusan user 26 Sep 2026) supaya laporan durasi tetap jujur.
     */
    public const KELUAR_TAK_TERCATAT = 'KELUAR_TAK_TERCATAT';

    /** Nilai yang boleh ditulis ke kolom Status_Kunjungan. */
    public const TERSIMPAN = [
        self::PENDING_APPROVAL,
        self::SCHEDULED,
        self::REJECTED,
        self::WITHDRAWN,
        self::REVOKED,
    ];

    /** Keputusan akhir yang menang atas kehadiran saat status tampil dihitung. */
    public const AKHIR_TERSIMPAN = [
        self::PENDING_APPROVAL,
        self::REJECTED,
        self::WITHDRAWN,
        self::REVOKED,
    ];

    /** Seluruh nilai status tampil, dalam urutan daur hidup. */
    public const TAMPIL = [
        self::PENDING_APPROVAL,
        self::SCHEDULED,
        self::CHECKED_IN,
        self::CHECKED_OUT,
        self::KELUAR_TAK_TERCATAT,
        self::CANCELLED,
        self::REJECTED,
        self::WITHDRAWN,
        self::REVOKED,
    ];

    public const LABEL = [
        self::PENDING_APPROVAL => 'Pending Approval',
        self::SCHEDULED => 'Scheduled',
        self::CHECKED_IN => 'Checked In',
        self::CHECKED_OUT => 'Checked Out',
        self::KELUAR_TAK_TERCATAT => 'Keluar Tak Tercatat',
        self::CANCELLED => 'Cancelled',
        self::REJECTED => 'Rejected',
        self::WITHDRAWN => 'Withdrawn',
        self::REVOKED => 'Revoked',
    ];

    public static function label(?string $kode): string
    {
        return self::LABEL[$kode] ?? ($kode ?: '-');
    }

    public static function tersimpan(string $kode): string
    {
        if (! in_array($kode, self::TERSIMPAN, true)) {
            throw new \InvalidArgumentException("Status kunjungan tidak dikenal: {$kode}");
        }

        return $kode;
    }

    /** Daftar untuk SQL `IN (...)`; nilainya konstanta kelas ini, bukan masukan pengguna. */
    public static function sqlDaftar(array $kode): string
    {
        return implode(', ', array_map(fn (string $k) => "'".self::tersimpanAtauTampil($k)."'", $kode));
    }

    private static function tersimpanAtauTampil(string $kode): string
    {
        if (! in_array($kode, self::TAMPIL, true)) {
            throw new \InvalidArgumentException("Status kunjungan tidak dikenal: {$kode}");
        }

        return $kode;
    }
}
