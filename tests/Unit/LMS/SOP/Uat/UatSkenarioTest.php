<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatSkenario;
use PHPUnit\Framework\TestCase;

/**
 * Klasifikasi 48 skenario UAT — penjaga atas daftarnya sendiri.
 *
 * Daftar ini menentukan bunyi laporan kesiapan, dan laporan kesiapan itulah
 * yang dibaca sebelum memutuskan sesi UAT layak digelar. Salah klasifikasi
 * tidak pernah tampak sebagai kesalahan: sebuah skenario yang keliru ditandai
 * MANUAL akan diam-diam berhenti dituntut hijau, selamanya.
 */
class UatSkenarioTest extends TestCase
{
    public function test_tiap_nomor_punya_modul_yang_dikenal(): void
    {
        foreach (UatSkenario::semua() as $n) {
            $this->assertNotSame('(di luar daftar)', UatSkenario::modul($n),
                "UAT-SOP-{$n} tidak masuk rentang modul mana pun; ia akan muncul di "
                .'laporan tanpa konteks apa pun.');
        }
    }

    public function test_id_diformat_tiga_digit(): void
    {
        $this->assertSame('UAT-SOP-001', UatSkenario::id(1));
        $this->assertSame('UAT-SOP-048', UatSkenario::id(48));
    }

    public function test_seluruh_nomor_terklasifikasi_berada_dalam_rentang(): void
    {
        foreach ([UatSkenario::KONTROL, UatSkenario::PERAMBAN, UatSkenario::MANUAL] as $daftar) {
            foreach ($daftar as $n) {
                $this->assertGreaterThanOrEqual(1, $n);
                $this->assertLessThanOrEqual(UatSkenario::TOTAL, $n,
                    "Nomor {$n} melebihi jumlah skenario dokumen; klasifikasinya menunjuk "
                    .'skenario yang tidak ada.');
            }
        }
    }

    /**
     * Sebuah skenario tak bisa sekaligus "butuh peramban" dan "ada bagian yang
     * tak bisa diotomatiskan sama sekali" — keduanya menuntut penanganan yang
     * berbeda pada laporan, dan yang bertabrakan akan dilaporkan dengan salah
     * satu saja tanpa ada yang menyadari.
     */
    public function test_peramban_dan_manual_tidak_beririsan(): void
    {
        $this->assertSame([], array_intersect(UatSkenario::PERAMBAN, UatSkenario::MANUAL));
    }

    public function test_tidak_ada_nomor_kembar_dalam_satu_klasifikasi(): void
    {
        foreach ([
            'KONTROL' => UatSkenario::KONTROL,
            'PERAMBAN' => UatSkenario::PERAMBAN,
            'MANUAL' => UatSkenario::MANUAL,
        ] as $nama => $daftar) {
            $this->assertSame(count($daftar), count(array_unique($daftar)),
                "Ada nomor kembar pada daftar {$nama}.");
        }
    }

    /**
     * Jumlah skenario kontrol dijaga angkanya. Bukan karena angkanya keramat,
     * tapi karena Acceptance Criteria menyebut skenario kontrol tak bisa
     * diturunkan jadi Minor — daftar yang menyusut diam-diam berarti sebagian
     * kewajiban itu hilang tanpa keputusan siapa pun.
     */
    public function test_jumlah_skenario_kontrol_sesuai_dokumen(): void
    {
        // 12 sejak 28 Agu 2026: skenario 004 (kategori terpakai tak bisa
        // dihapus) ikut dicabut bersama Master Kategori. Angkanya diturunkan
        // BERSAMA daftarnya, sesuai yang dituntut pesan di bawah.
        $this->assertCount(12, UatSkenario::KONTROL,
            'Jumlah skenario kontrol berubah. Kalau dokumen UAT memang diubah, perbarui '
            .'angka ini bersama daftarnya — jangan hanya salah satunya.');
    }

    /**
     * Skenario yang dicabut tak boleh diam-diam ikut dituntut hijau, dan tak
     * boleh pula ikut dihitung sebagai kontrol yang terbukti.
     *
     * Nomornya tetap dipesan: dokumen UAT yang sudah ditandatangani memakai
     * penomoran ini, jadi 46 dan 48 tidak boleh dipakai ulang untuk skenario
     * lain — rujukan lama akan menunjuk hal yang sama sekali berbeda.
     */
    public function test_skenario_dicabut_keluar_dari_daftar_periksa(): void
    {
        foreach (UatSkenario::DICABUT as $n) {
            $this->assertNotContains($n, UatSkenario::semua(),
                "UAT-SOP-{$n} sudah dicabut tapi masih ikut diperiksa laporan.");
            $this->assertNotContains($n, UatSkenario::KONTROL,
                "UAT-SOP-{$n} sudah dicabut tapi masih dihitung sebagai skenario kontrol.");
            $this->assertLessThanOrEqual(UatSkenario::TOTAL, $n,
                'Nomor yang dicabut tetap harus berada di dalam penomoran dokumen.');
        }
    }
}
