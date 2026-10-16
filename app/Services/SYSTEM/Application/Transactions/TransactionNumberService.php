<?php

namespace App\Services\SYSTEM\Application\Transactions;

use Illuminate\Support\Facades\DB;

/**
 * Generator No_Transaksi berbasis COUNTER ATOMIK (tabel HRIS_Counter).
 *
 * Menggantikan pola lama "SELECT MAX(CAST(RIGHT(No_Transaksi,4) AS INT)) + 1"
 * (kadang + lockForUpdate) yang:
 *  - NON-SARGABLE / men-scan tabel → memperlambat & memperpanjang transaksi
 *    (menahan koneksi lebih lama → berkontribusi ke "Login timeout expired"), dan
 *  - RAWAN DUPLIKAT saat submit konkuren (tanpa/lemah lock).
 *
 * next() mengunci HANYA 1 baris counter untuk prefix bersangkutan selama satu
 * UPDATE (mikrodetik) lalu mengembalikan nomor urut berikutnya secara atomik.
 *
 * SELF-INITIALIZING: saat sebuah prefix dipakai pertama kali (baris counter belum
 * ada), nilai awal diambil dari MAX yang sudah ada di tabel sumber via
 * $seedResolver — sehingga TIDAK perlu seeding manual (yang rawan salah → duplikat).
 * Query max ini hanya jalan SEKALI per prefix (mis. sekali per bulan per seri).
 *
 * PENTING: panggil SEBELUM membuka transaksi bisnis besar. Bila dipanggil di dalam
 * transaksi caller, kunci baris counter ikut tertahan hingga commit caller.
 */
class TransactionNumberService
{
    /**
     * Ambil nomor urut berikutnya (integer) untuk sebuah prefix secara atomik.
     *
     * @param  string  $prefix  Prefix penuh, mis. "CT07-26-".
     * @param  callable|null  $seedResolver  Fungsi yang mengembalikan nilai urut
     *                                       TERTINGGI yang sudah ada di tabel sumber untuk prefix ini (int|null).
     *                                       Dipakai HANYA saat inisialisasi pertama prefix. Null → mulai dari 0.
     */
    public function next(string $prefix, ?callable $seedResolver = null): int
    {
        // TANPA pembungkus DB::transaction: tiap statement autocommit → kunci baris
        // counter lepas SEKETIKA (bukan tertahan sampai commit) & bebas deadlock,
        // dan PK-violation pada insert jadi error statement terisolasi (bukan
        // transaksi "doomed"). PANGGIL SEBELUM transaksi bisnis; bila terpaksa di
        // dalam transaksi caller, statement ini ikut transaksi itu.
        $newVal = $this->atomicIncrement($prefix);
        if ($newVal !== null) {
            return $newVal;
        }

        // Prefix belum ada → inisialisasi dari MAX sumber (self-seeding).
        $seed = $seedResolver ? (int) ($seedResolver() ?? 0) : 0;

        try {
            DB::insert('INSERT INTO HRIS_Counter (prefix, next_val) VALUES (?, ?)', [$prefix, $seed + 1]);

            return $seed + 1;
        } catch (\Throwable $e) {
            // Race: request lain menyisipkan baris prefix ini lebih dulu →
            // increment ulang secara atomik.
            $retry = $this->atomicIncrement($prefix);

            return $retry ?? ($seed + 1);
        }
    }

    /**
     * Bangun No_Transaksi lengkap: prefix + urut ter-zero-pad.
     */
    public function generate(string $prefix, ?callable $seedResolver = null, int $pad = 4): string
    {
        return $prefix.str_pad((string) $this->next($prefix, $seedResolver), $pad, '0', STR_PAD_LEFT);
    }

    /**
     * UPDATE atomik +1 dan kembalikan nilai baru; null bila baris prefix belum ada.
     * SET NOCOUNT ON agar rowset OUTPUT terbaca bersih oleh driver sqlsrv.
     *
     * Sengaja TANPA SERIALIZABLE: saat baris sudah ada, UPDATE satu-statement
     * mengambil exclusive row-lock → serialisasi bersih per baris (nilai selalu
     * unik) tanpa risiko deadlock. Race penyisipan awal (baris belum ada) ditangani
     * di next() lewat PRIMARY KEY + retry — bukan range-lock (yang bikin deadlock
     * saat banyak request pertama datang bersamaan untuk prefix baru).
     */
    private function atomicIncrement(string $prefix): ?int
    {
        $rows = DB::select(
            'SET NOCOUNT ON; '
            .'UPDATE HRIS_Counter WITH (UPDLOCK) '
            .'SET next_val = next_val + 1 '
            .'OUTPUT inserted.next_val AS val '
            .'WHERE prefix = ?',
            [$prefix]
        );

        return empty($rows) ? null : (int) $rows[0]->val;
    }
}
