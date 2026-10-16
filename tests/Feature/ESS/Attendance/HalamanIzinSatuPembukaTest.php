<?php

namespace Tests\Feature\ESS\Attendance;

use Tests\TestCase;

/**
 * Ketiga halaman izin menyusun "shift hari ini" lewat satu pintu.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * indexIzinAdmin(), indexIzin() dan indexIzinDH() dulu menuliskan kueri shift
 * yang sama beserta pemetaannya masing-masing — tiga salinan, dan ketiganya
 * juga membawa delapan baris hitungan $Hari yang TIDAK PERNAH DIBACA siapa pun:
 *
 *     $Hari = date('N');
 *     if ($Hari <= 6) { $Hari += 1; } elseif ($Hari == 7) { $Hari = 1; }
 *
 * Diperiksa: lima sebutan, empat di antaranya penugasan, dan tak satu pun
 * pembacaan di luar hitungannya sendiri. Kode mati yang disalin tiga kali.
 *
 * ── Yang lebih penting: satu perbedaan yang dulu tak terbaca ─────────────────
 *
 * Hanya jalur admin yang memperkaya hasilnya dengan status hari. Selama aturan
 * itu tersebar di tiga salinan, perbedaannya terbaca seperti kelalaian, bukan
 * keputusan. Sekarang ia sebuah argumen — dan uji di bawah ini mematok
 * pemasangannya supaya perubahan berikutnya harus disengaja.
 *
 * Catatan untuk pembaca berikutnya: pemasangan itu tampaknya TERBALIK.
 * izinPageAdmin.vue mendeklarasikan prop dataShift dan tidak pernah memakainya,
 * sedangkan izinPage.vue bergantung pada is_day_off yang tidak pernah dikirim
 * kepadanya. Itu diperlakukan sebagai temuan, bukan diperbaiki diam-diam:
 * menyalakannya berarti mulai memblokir tombol "Ajukan Izin" pada hari libur,
 * dan itu keputusan produk.
 */
class HalamanIzinSatuPembukaTest extends TestCase
{
    private const CONTROLLER = 'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php';

    private const FIXTURE = 'tests/Support/Fixtures/Izin/shift-hari-ini.sql';

    private function isi(): string
    {
        return (string) file_get_contents(base_path(self::CONTROLLER));
    }

    /** Badan sebuah metode, dipotong dari kurung buka sampai kurung tutupnya. */
    private function badanMetode(string $isi, string $nama): string
    {
        $cocok = preg_match(
            '/\n[ \t]*(?:public|private|protected) function '.preg_quote($nama, '/').'\s*\(/',
            $isi,
            $m,
            PREG_OFFSET_CAPTURE,
        );
        $this->assertSame(1, $cocok, "Metode {$nama} hilang dari controller.");

        $buka = strpos($isi, '{', $m[0][1]);
        $dalam = 0;
        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $m[0][1], $i - $m[0][1] + 1);
                }
            }
        }

        $this->fail("Kurung metode {$nama} tidak seimbang.");
    }

    public function test_hitungan_hari_yang_tak_pernah_dibaca_sudah_hilang(): void
    {
        $isi = $this->isi();

        $this->assertSame(
            0,
            preg_match_all("/\\\$Hari\s*=\s*date\('N'\)/", $isi),
            'Hitungan $Hari kembali. Ia tidak pernah dibaca setelah dihitung — '.
            'delapan baris kode mati, dulu disalin tiga kali.',
        );
    }

    public function test_ketiga_halaman_tidak_lagi_menulis_kueri_shift_nya_sendiri(): void
    {
        $isi = $this->isi();

        foreach (['indexIzinAdmin', 'indexIzin', 'indexIzinDH'] as $metode) {
            $badan = $this->badanMetode($isi, $metode);

            $this->assertStringContainsString(
                '$this->shiftHariIni(',
                $badan,
                "{$metode}() tidak lagi mengambil shift-nya dari satu pintu.",
            );

            $this->assertDoesNotMatchRegularExpression(
                '/\$query\s*=\s*["\']/',
                $badan,
                "{$metode}() kembali menulis kueri shift-nya sendiri.",
            );

            $this->assertStringNotContainsString(
                'Crypt::encryptString',
                $badan,
                "{$metode}() kembali memetakan hasilnya sendiri — enkripsi UserID_Absen ".
                'adalah bagian dari pintu bersamanya.',
            );
        }
    }

    public function test_pemasangan_status_hari_dipatok_apa_adanya(): void
    {
        /*
         * Yang dipatok di sini BUKAN "ini benar" melainkan "ini disengaja".
         * Satu-satunya jalur yang meminta status hari adalah admin. Kalau
         * seseorang mengubahnya, uji ini merah dan ia harus menuliskan alasannya
         * — persis yang tidak terjadi ketika perbedaannya masih tersembunyi di
         * tiga salinan.
         */
        $isi = $this->isi();

        $this->assertMatchesRegularExpression(
            '/shiftHariIni\(\$kodeKaryawan, \$tanggal, true\)/',
            $this->badanMetode($isi, 'indexIzinAdmin'),
            'indexIzinAdmin() berhenti meminta status hari.',
        );

        foreach (['indexIzin', 'indexIzinDH'] as $metode) {
            $this->assertMatchesRegularExpression(
                '/shiftHariIni\(\$kodeKaryawan, \$tanggal, false\)/',
                $this->badanMetode($isi, $metode),
                "{$metode}() mulai meminta status hari. Itu perubahan perilaku yang ".
                'terlihat pengguna: tombol "Ajukan Izin" jadi bisa dinonaktifkan pada '.
                'hari libur. Sah, tapi harus disengaja.',
            );
        }

        $this->assertSame(
            1,
            preg_match_all('/getStatusHariInfo\(\$kodeKaryawan, \$tanggal\)/', $isi),
            'Status hari hanya boleh diambil di satu tempat, di dalam pintu bersamanya.',
        );
    }

    public function test_rantai_approver_juga_satu_pintu(): void
    {
        $isi = $this->isi();

        foreach (['indexIzinAdmin', 'indexIzin'] as $metode) {
            $this->assertStringContainsString(
                '$this->approverIzin($kodeKaryawan)',
                $this->badanMetode($isi, $metode),
                "{$metode}() tidak lagi mengambil rantai approver dari satu pintu.",
            );
        }

        $this->assertStringNotContainsString(
            '$this->approverIzin(',
            $this->badanMetode($isi, 'indexIzinDH'),
            'indexIzinDH() memang tidak mengirim IzinApprover ke halamannya; '.
            'menambahkannya berarti menambah satu kueri untuk data yang tak dipakai.',
        );

        $this->assertSame(
            1,
            preg_match_all("/->where\('h.Kode_Karyawan_Requester'/", $isi),
            'Kueri rantai approver kembali tersalin.',
        );
    }

    public function test_kueri_shift_tidak_berubah_sebyte_pun(): void
    {
        /*
         * Kuerinya dipindah, bukan ditulis ulang. Fixture-nya adalah teks
         * SEBELUM pemindahan; kalau berbeda, yang berpindah bukan cuma
         * tempatnya.
         */
        $p = base_path(self::FIXTURE);
        $this->assertFileExists($p, 'Fixture kueri shift hilang — tanpa itu penjaga ini kosong.');

        $isi = $this->isi();
        $awal = strpos($isi, '$query = " select');
        $this->assertNotFalse($awal, 'Literal kueri shift tidak ditemukan.');

        $mulai = strpos($isi, '"', $awal) + 1;
        $selesai = strpos($isi, '";', $mulai);

        $this->assertSame(
            (string) file_get_contents($p),
            substr($isi, $mulai, $selesai - $mulai),
            'Teks kueri shift berubah. Ia hanya dipindahkan ke satu pintu; perubahan '.
            'isinya berarti sesuatu yang lain ikut terbawa.',
        );
    }

    public function test_penjaga_dan_fixture_tidak_kosong(): void
    {
        $this->assertGreaterThan(4000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
        $this->assertGreaterThan(500, (int) filesize(base_path(self::FIXTURE)), 'Fixture menyusut drastis.');
    }
}
