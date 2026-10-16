<?php

namespace Tests\Unit\SYSTEM\Application\Transactions;

use App\Services\SYSTEM\Application\Transactions\TransactionService;
use PHPUnit\Framework\TestCase;

/**
 * Perencana pembalik kredit "Konversi Lembur -> Cuti" (murni, tanpa basis data).
 *
 * Kredit konversi tercatat di jurnal dalam dua bentuk:
 *  - pembayaran hutang  : CREDIT di dompet bersaldo minus (Saldo_Sebelum < 0)
 *  - tambahan jatah     : CREDIT di dompet aktif (Saldo_Sebelum >= 0)
 * Pembaliknya harus mencerminkan keduanya di dompet yang SAMA.
 */
class RencanaBalikKonversiCutiTest extends TestCase
{
    public function test_pembayaran_hutang_dipulihkan_di_dompet_hutang_yang_sama(): void
    {
        $kredit = [(object) ['Id_Buku_Cuti' => 7, 'Jumlah' => 2, 'Saldo_Sebelum' => -3]];

        $rencana = TransactionService::rencanaBalikKreditKonversi($kredit, [7 => -1.0]);

        $this->assertSame([['id_buku' => 7, 'jenis' => 'HUTANG_DIPULIHKAN', 'jumlah' => 2.0, 'saldo_sebelum' => -1.0, 'saldo_sesudah' => -3.0]], $rencana['langkah']);
        $this->assertSame(2.0, $rencana['hutang_dipulihkan']);
        $this->assertSame(0.0, $rencana['jatah_ditarik']);
        $this->assertSame(0.0, $rencana['hutang_baru']);
    }

    public function test_jatah_yang_belum_terpakai_ditarik_penuh(): void
    {
        $kredit = [(object) ['Id_Buku_Cuti' => 9, 'Jumlah' => 3, 'Saldo_Sebelum' => 5]];

        $rencana = TransactionService::rencanaBalikKreditKonversi($kredit, [9 => 8.0]);

        $this->assertSame(3.0, $rencana['jatah_ditarik']);
        $this->assertSame(0.0, $rencana['hutang_baru']);
        $this->assertSame(5.0, $rencana['langkah'][0]['saldo_sesudah']);
    }

    public function test_jatah_yang_sudah_terpakai_sisanya_menjadi_hutang(): void
    {
        // Dikredit 3 hari, sekarang dompet tinggal 1: hanya 1 bisa ditarik, 2 jadi hutang.
        $kredit = [(object) ['Id_Buku_Cuti' => 9, 'Jumlah' => 3, 'Saldo_Sebelum' => 0]];

        $rencana = TransactionService::rencanaBalikKreditKonversi($kredit, [9 => 1.0]);

        $this->assertSame(1.0, $rencana['jatah_ditarik']);
        $this->assertSame(2.0, $rencana['hutang_baru']);
        $this->assertSame([
            ['id_buku' => 9, 'jenis' => 'JATAH_DITARIK', 'jumlah' => 1.0, 'saldo_sebelum' => 1.0, 'saldo_sesudah' => 0.0],
            ['id_buku' => 9, 'jenis' => 'HUTANG_BARU', 'jumlah' => 2.0, 'saldo_sebelum' => null, 'saldo_sesudah' => null],
        ], $rencana['langkah']);
    }

    public function test_dua_kredit_di_dompet_sama_memakai_saldo_berjalan(): void
    {
        $kredit = [
            (object) ['Id_Buku_Cuti' => 9, 'Jumlah' => 2, 'Saldo_Sebelum' => 0],
            (object) ['Id_Buku_Cuti' => 9, 'Jumlah' => 2, 'Saldo_Sebelum' => 2],
        ];

        $rencana = TransactionService::rencanaBalikKreditKonversi($kredit, [9 => 3.0]);

        $this->assertSame(3.0, $rencana['jatah_ditarik']);
        $this->assertSame(1.0, $rencana['hutang_baru']);
    }

    public function test_tanpa_kredit_tidak_ada_langkah(): void
    {
        $rencana = TransactionService::rencanaBalikKreditKonversi([], []);

        $this->assertSame([], $rencana['langkah']);
        $this->assertSame(0.0, $rencana['jatah_ditarik'] + $rencana['hutang_dipulihkan'] + $rencana['hutang_baru']);
    }
}
