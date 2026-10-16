<?php

namespace App\Services\SYSTEM\Audit\Numbering;

use App\Services\SYSTEM\Application\Transactions\TransactionNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis No_Transaksi untuk HRIS_Transaksi_Log_Admin.
 *
 * ── Yang berubah, dan kenapa ─────────────────────────────────────────────────
 *
 * Strategi lama: sp_getapplock (Exclusive, LockOwner='Transaction') pada resource
 * "HRIS_Transaksi_Log_Admin:{mm-yy}", lalu membaca MAX No_Transaksi. Dua akibatnya:
 *
 *   1. SATU LOCK UNTUK SEBULAN PENUH. Setiap penulisan admin dalam bulan yang
 *      sama antre di resource yang sama, dengan timeout 10 detik. Karena
 *      LockOwner='Transaction', lock itu dipegang sampai transaksi pemanggil
 *      commit — dan salah satu pemanggilnya, karyawanTeamController::simpanUser,
 *      adalah transaksi ratusan baris. Selama itu berjalan, tidak ada penulisan
 *      admin lain yang bisa lewat.
 *   2. LOGIKANYA DISALIN. AuditAdminTransaction memuat salinan persis metode ini
 *      — token demi token — dengan komentar di kedua sisi yang menyatakan
 *      keduanya "sengaja identik". Komentar bukan penjaga: begitu satu sisi
 *      diperbaiki dan yang lain tidak, keduanya berhenti saling-eksklusi dan
 *      nomor kembar muncul justru pada tabel AUDIT.
 *
 * Strategi baru: counter atomik per-prefix di HRIS_Counter, lewat
 * TransactionNumberService — persis yang sudah dipakai seri Cuti, Lembur, Shift,
 * KPI, dan Visitor. Yang dikunci hanya SATU BARIS counter untuk prefix bulan itu,
 * bukan seluruh bulan, dan keunikan dijamin oleh baris counter, bukan oleh dua
 * salinan kode yang harus tetap sama.
 *
 * ── Konsekuensi yang harus diketahui ─────────────────────────────────────────
 *
 * Nomor bisa BERLUBANG bila transaksi pemanggil gagal sesudah nomornya diambil.
 * Itu sudah jadi norma sistem ini: seluruh seri lain memakai counter yang sama
 * dan berperilaku begitu. Yang ditukar adalah lubang nomor (tak berbahaya)
 * dengan nomor kembar pada tabel audit (berbahaya).
 *
 * Penyemaian awal berjalan sendiri: saat sebuah prefix bulan dipakai pertama kali,
 * TransactionNumberService membaca nomor tertinggi yang sudah ada di
 * HRIS_Transaksi_Log_Admin untuk prefix itu, jadi tidak ada seeding manual dan
 * tidak ada tabrakan dengan nomor lama.
 *
 * ── Catatan pemanggilan ──────────────────────────────────────────────────────
 *
 * Tidak lagi WAJIB dipanggil di dalam transaksi. Bila tetap dipanggil dari dalam
 * transaksi (kedua pemanggil saat ini begitu), kunci yang tertahan sampai commit
 * hanya satu baris counter — bukan lagi seluruh bulan.
 */
class AdminLogNumberService
{
    /** Panjang bagian urut pada No_Transaksi, mis. "0010". */
    private const PANJANG_URUT = 4;

    /**
     * Prefix untuk sebuah bulan, mis. "LHC09-26-".
     *
     * Murni dan tanpa basis data supaya bentuknya bisa dijaga uji: kalau formatnya
     * bergeser, nomor baru tidak lagi sebaris dengan 213 nomor lama dan penyemaian
     * counter akan mulai dari nol.
     */
    public static function prefix(?\DateTimeInterface $saat = null): string
    {
        return 'LHC'.($saat ? \Carbon\Carbon::instance($saat) : now())->format('m-y').'-';
    }

    public function generate(): string
    {
        $prefix = self::prefix();

        return app(TransactionNumberService::class)->generate(
            $prefix,
            /*
             * Penyemaian sekali per prefix. ORDER BY No_Transaksi (bukan
             * RIGHT(...,4)): prefix sama dan urutnya zero-padded 4 digit, jadi
             * urutan leksikal sama dengan urutan numerik — dan dengan index pada
             * No_Transaksi ini jadi seek, bukan scan.
             */
            function () use ($prefix) {
                $terakhir = DB::table('HRIS_Transaksi_Log_Admin')
                    ->where('No_Transaksi', 'like', $prefix.'%')
                    ->orderBy('No_Transaksi', 'desc')
                    ->value('No_Transaksi');

                return $terakhir === null
                    ? 0
                    : (int) substr((string) $terakhir, -self::PANJANG_URUT);
            },
            self::PANJANG_URUT,
        );
    }
}
