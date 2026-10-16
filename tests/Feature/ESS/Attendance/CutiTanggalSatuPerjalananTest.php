<?php

namespace Tests\Feature\ESS\Attendance;

use Tests\TestCase;

/**
 * Tanggal cuti ditulis dalam SATU perjalanan, bukan satu perjalanan per tanggal.
 *
 * Kenapa ini layak dijaga, padahal kueri yang dihemat sepele:
 *
 *   Satu bolak-balik ke basis data di lingkungan ini TERUKUR 54 ms — itu waktu
 *   `SELECT 1` yang tidak mengerjakan apa pun (p50 dari 30 percobaan).
 *   Eksekusi INSERT-nya sendiri 0,4 ms. Jadi 99% biayanya adalah PERJALANAN,
 *   dan menambah satu INSERT ke dalam perulangan berarti menambah 54 ms penuh
 *   per iterasi — bukan 0,4 ms seperti yang disangka orang saat menulisnya.
 *
 *   Yang lebih penting daripada waktunya: perulangan itu berada DI DALAM
 *   transaksi, dan RCSI mati di basis data ini. Selama perjalanan-perjalanan
 *   itu berlangsung, kunci pada Transaksi_Cuti_Det tertahan dan pembacanya
 *   ANTRE di belakangnya. Bentuk berulang memperpanjang jendela itu sebanyak
 *   jumlah tanggal yang diajukan.
 *
 * Penjaga ini membaca STRUKTUR, bukan perilaku, dan itu keputusan sadar:
 * menguji lewat perilaku menuntut menulis pengajuan cuti sungguhan ke basis
 * data dev yang dipakai bersama, dan dengan RCSI mati penulisan itu mengunci
 * UI orang lain. Batasnya jujur: penjaga ini melihat bentuk kode, jadi ia tidak
 * akan menangkap perjalanan tambahan yang lahir di pembantu yang dipanggil
 * dari dalam perulangan.
 */
class CutiTanggalSatuPerjalananTest extends TestCase
{
    private const BERKAS = 'app/Http/Controllers/ESS/Attendance/Leave/Personal/CutiController.php';

    /**
     * Badan metode $nama, dipotong dengan pencocokan kurung kurawal.
     *
     * Pencarian memakai regex ber-anchor awal baris, BUKAN strpos, dan itu
     * bukan gaya melainkan koreksi atas kekeliruan nyata: berkas ini masih
     * menyimpan bangkai store() lama sepanjang ~350 baris yang seluruhnya
     * dikomentari (`// public function store(Request $request)`), lengkap
     * dengan perulangan tanggal dan INSERT di dalamnya. strpos menemukan
     * bangkai itu lebih dulu, sehingga penjaga ini mengiris kode mati dan
     * melaporkan MERAH pada kode yang justru sudah benar.
     */
    private function badanMetode(string $isi, string $nama): string
    {
        $cocok = preg_match(
            '/^[ 	]*public function '.preg_quote($nama, '/').'\s*\(/m',
            $isi,
            $m,
            PREG_OFFSET_CAPTURE,
        );
        $this->assertSame(1, $cocok, "{$nama}() tidak ditemukan — namanya berubah?");
        $awal = (int) $m[0][1];

        $buka = strpos($isi, '{', $awal);
        $this->assertNotFalse($buka);

        $dalam = 0;
        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $awal, $i - $awal + 1);
                }
            }
        }

        $this->fail("Kurung kurawal {$nama}() tidak seimbang.");
    }

    /** Isi blok perulangan yang diawali pada posisi $mulai. */
    private function badanPerulangan(string $isi, int $mulai): string
    {
        $buka = strpos($isi, '{', $mulai);
        $this->assertNotFalse($buka, 'Perulangan tanpa blok — bentuknya berubah?');

        $dalam = 0;
        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $buka, $i - $buka + 1);
                }
            }
        }

        $this->fail('Kurung kurawal perulangan tidak seimbang.');
    }

    /**
     * Irisan store() yang HIDUP, dengan jaring pengaman.
     *
     * Penanda di bawah memastikan yang teriris benar-benar metode yang
     * berjalan. Tanpa itu, irisan yang salah menghasilkan penjaga yang
     * melaporkan sesuatu tentang kode yang tidak pernah dijalankan siapa pun —
     * dan itu sudah terjadi sekali saat penjaga ini ditulis.
     */
    private function irisanStore(): string
    {
        $store = $this->badanMetode((string) file_get_contents(base_path(self::BERKAS)), 'store');

        $this->assertStringContainsString(
            'DB::beginTransaction()',
            $store,
            'Irisan store() tidak berisi transaksi — kemungkinan besar yang teriris '.
            'bukan metode yang hidup.',
        );

        return $store;
    }

    public function test_tanggal_cuti_tidak_ditulis_satu_per_satu_di_dalam_perulangan(): void
    {
        $store = $this->irisanStore();

        $posisi = strpos($store, 'foreach ($selectedDates as $date)');
        $this->assertNotFalse(
            $posisi,
            'Perulangan atas $selectedDates tidak ada lagi. Kalau bentuknya memang '.
            'berubah, perbarui penjaga ini — jangan hapus: yang dijaga adalah '.
            'jumlah perjalanan ke basis data, bukan nama variabelnya.',
        );

        $this->assertStringNotContainsString(
            '->insert(',
            $this->badanPerulangan($store, $posisi),
            'INSERT kembali berada di dalam perulangan tanggal cuti. Tiap iterasi '.
            'membayar satu perjalanan penuh (54 ms terukur), dan seluruhnya '.
            'terjadi DI DALAM transaksi sehingga pembaca Transaksi_Cuti_Det '.
            'antre selama itu. Kumpulkan barisnya lebih dulu, lalu tulis sekali.',
        );
    }

    public function test_tanggal_cuti_ditulis_sekali_dan_dipotong_agar_aman_dari_batas_parameter(): void
    {
        $store = $this->irisanStore();

        $this->assertMatchesRegularExpression(
            '/array_chunk\(\s*\$barisTanggal\s*,\s*\d+\s*\)/',
            $store,
            'Penulisan massal tanggal cuti harus dipotong. SQL Server menolak di '.
            'atas 2.100 parameter per pernyataan, dan lima kolom dikali jumlah '.
            'tanggal bisa menembusnya kalau suatu saat ada pengajuan panjang.',
        );

        preg_match('/array_chunk\(\s*\$barisTanggal\s*,\s*(\d+)\s*\)/', $store, $m);
        $this->assertLessThanOrEqual(
            420,
            (int) ($m[1] ?? PHP_INT_MAX),
            'Potongan terlalu besar: lima kolom dikali potongan ini melewati batas '.
            '2.100 parameter SQL Server.',
        );
    }
}
