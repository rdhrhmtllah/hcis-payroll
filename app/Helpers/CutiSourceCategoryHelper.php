<?php

namespace App\Helpers;

/**
 * Pemetaan terpusat Kategori_Transaksi (+Jenis_Mutasi) → nilai enum Source_Category
 * untuk ledger cuti (N_HRIS_Log_Buku_Cuti).
 *
 * Satu sumber kebenaran, dipakai:
 *   - Console\Commands\BackfillCutiProvenance (backfill baris lama)
 *   - Services\TransactionService (isi Source_Category untuk baris BARU / dual-write)
 *
 * Fallback selalu mengembalikan slug dari kategori (≤30 char) → tidak pernah NULL,
 * tetap tertelusur meskipun kategori belum dikenal.
 */
class CutiSourceCategoryHelper
{
    public static function map(string $kategori, string $jenis): string
    {
        $k = strtoupper(trim($kategori));
        $isCredit = strtoupper(trim($jenis)) === 'CREDIT';
        $has = fn (string $n) => str_contains($k, $n);

        if (in_array($k, ['CO', 'MLY', 'CLB', 'BMLY'], true)) {
            return 'accrual_yearend';
        }
        if ($has('BERSAMA')) {
            return 'cuti_bersama_exec';
        }
        // Pengembalian saldo karena transaksi cuti dibatalkan admin. HARUS diperiksa
        // sesudah BERSAMA (CUTI_BERSAMA_ROLLBACK tetap 'cuti_bersama_exec') dan sebelum
        // cabang PENGAJUAN, supaya tidak jatuh ke fallback slug.
        if ($has('PEMBATALAN') || ($isCredit && $has('ROLLBACK'))) {
            return 'pembatalan_cuti';
        }
        if ($has('EXPIRED') || $has('HANGUS')) {
            return 'expired';
        }
        if ($has('KOREKSI') && $has('MIGRAS')) {
            return 'koreksi_migrasi';
        }
        if ($has('MIGRAS') || $has('OPENING')) {
            return 'migrated_from_manual_book';
        }
        if ($has('ADJUSTMENT') || $has('MANUAL')) {
            return 'manual_adjustment';
        }
        if ($has('PROMOTION')) {
            return 'promotion';
        }
        if ($has('AWAL')) {
            return 'accrual_firstyear';
        }
        if ($has('LOYALTY') || $has('REWARD')) {
            return 'loyalty';
        }
        if ($has('MATURITY')) {
            return 'maturity_activated';
        }
        if ($has('AKRUAL') || $has('MONTHLY') || $has('SALDO_CUTI') || $has('KONVERSI')) {
            return 'accrual_monthly';
        }
        if ($has('PENGAJUAN') || $has('CONSUME') || $has('PEMAKAIAN')) {
            return 'pengajuan_cuti';
        }
        if ($has('PAY_DEBT') || $has('PELUNASAN') || ($isCredit && $has('HUTANG'))) {
            return 'pelunasan_hutang';
        }
        if ($has('HUTANG')) {
            return 'hutang_cuti';
        }

        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $kategori) ?? 'unknown');

        return substr(trim($slug, '_'), 0, 30) ?: 'unknown';
    }
}
