<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Leave\Shared\PeriodeBulananCuti;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Jendela periode akrual kuota cuti: satu aturan untuk dua perintah terjadwal.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * resolveTargetPeriod() ada dua salinan — HakCutiTambahan dan
 * tambahKuotaCutiBulanan — identik sampai ke tanda bacanya; satu-satunya beda
 * adalah nama parameternya. Keduanya perintah terjadwal yang MENAMBAH saldo
 * cuti, jadi jendela yang menyimpang di salah satunya berarti kuota bulan yang
 * salah masuk ke buku cuti orang, dan koreksinya harus dikejar lewat ledger.
 *
 * ── Lubang yang sekalian ditutup ─────────────────────────────────────────────
 *
 * Kedua salinan memeriksa --month dengan /^\d{4}-\d{2}$/ lalu menyerahkannya ke
 * Carbon::createFromFormat. Regex itu meloloskan bulan di luar 01-12, dan Carbon
 * melimpahkannya diam-diam:
 *
 *     --month=2026-13  ->  2027-01-01   (masih tertangkap penjaga masa depan)
 *     --month=2026-00  ->  2025-12-01   (LOLOS — masa lalu, jadi diterima)
 *
 * Salah ketik "00" menambahkan kuota untuk Desember 2025 tanpa satu pun
 * peringatan. Sekarang ditolak dengan pesan yang menyebut apa yang salah.
 *
 * Uji ini tidak menyentuh basis data: aturannya murni, waktunya disuntikkan.
 */
class PeriodeBulananCutiSatuRumahTest extends TestCase
{
    private const BEKAS_PEMILIK_SALINAN = [
        'app/Console/Commands/ESS/Attendance/Leave/Admin/HakCutiTambahan.php',
        'app/Console/Commands/ESS/Attendance/Leave/Admin/tambahKuotaCutiBulanan.php',
    ];

    private function acuan(): Carbon
    {
        return Carbon::create(2026, 9, 8, 14, 30, 0);
    }

    public function test_tanpa_opsi_memakai_bulan_berjalan_dan_berhenti_hari_ini(): void
    {
        $hasil = PeriodeBulananCuti::untuk(null, $this->acuan());

        $this->assertNull($hasil['galat']);
        $this->assertSame('2026-09-01', $hasil['mulai']->toDateString());
        $this->assertSame('2026-09-08', $hasil['selesai']->toDateString(),
            'Bulan berjalan harus berhenti hari ini — hari yang belum terjadi tidak '
            .'boleh ikut menghasilkan akrual.');
        $this->assertSame('00:00:00', $hasil['selesai']->format('H:i:s'),
            'Ujung jendela dinormalkan ke awal hari, seperti perilaku lama.');
    }

    public function test_bulan_lampau_memakai_seluruh_bulan_itu(): void
    {
        $hasil = PeriodeBulananCuti::untuk('2026-06', $this->acuan());

        $this->assertNull($hasil['galat']);
        $this->assertSame('2026-06-01', $hasil['mulai']->toDateString());
        $this->assertSame('2026-06-30', $hasil['selesai']->toDateString());
    }

    public function test_bulan_berjalan_lewat_opsi_tetap_berhenti_hari_ini(): void
    {
        $hasil = PeriodeBulananCuti::untuk('2026-09', $this->acuan());

        $this->assertNull($hasil['galat']);
        $this->assertSame('2026-09-08', $hasil['selesai']->toDateString());
    }

    public function test_bulan_masa_depan_ditolak(): void
    {
        $hasil = PeriodeBulananCuti::untuk('2026-10', $this->acuan());

        $this->assertSame('Future month tidak boleh diproses.', $hasil['galat']);
        $this->assertNull($hasil['mulai']);
    }

    public function test_format_salah_ditolak(): void
    {
        foreach (['2026-6', '202606', 'juni', '2026/06', '2026-06-01'] as $buruk) {
            $hasil = PeriodeBulananCuti::untuk($buruk, $this->acuan());
            $this->assertSame(
                'Format --month harus YYYY-MM, contoh: --month=2026-06',
                $hasil['galat'],
                "Masukan {$buruk} seharusnya ditolak karena bentuknya.");
        }
    }

    public function test_bulan_di_luar_01_sampai_12_ditolak_bukan_dilimpahkan(): void
    {
        /*
         * Inti perbaikannya. Sebelum ini, "2026-00" menjadi Desember 2025 —
         * masa lalu, jadi lolos penjaga masa depan — dan operator yang salah
         * ketik menambahkan kuota untuk bulan yang tidak ia maksud.
         */
        foreach (['2026-00' => '00', '2026-13' => '13', '2026-99' => '99'] as $buruk => $angka) {
            $hasil = PeriodeBulananCuti::untuk($buruk, $this->acuan());

            $this->assertSame("Bulan pada --month harus 01-12, diterima: {$angka}",
                $hasil['galat'],
                "{$buruk} harus ditolak, bukan dilimpahkan ke bulan lain.");
            $this->assertNull($hasil['mulai']);
        }
    }

    public function test_batas_bulan_yang_sah_tetap_diterima(): void
    {
        foreach (['2026-01', '2025-12'] as $sah) {
            $hasil = PeriodeBulananCuti::untuk($sah, $this->acuan());
            $this->assertNull($hasil['galat'], "{$sah} seharusnya diterima.");
        }
    }

    public function test_opsi_kosong_diperlakukan_seperti_tanpa_opsi(): void
    {
        /*
         * Command::option() mengembalikan null saat tidak diberikan, tetapi
         * string kosong bisa datang dari pemanggilan lain. Keduanya berarti
         * "pakai bulan berjalan", bukan "format salah".
         */
        foreach ([null, ''] as $kosong) {
            $hasil = PeriodeBulananCuti::untuk($kosong, $this->acuan());
            $this->assertNull($hasil['galat']);
            $this->assertSame('2026-09-01', $hasil['mulai']->toDateString());
        }
    }

    public function test_acuan_tidak_ikut_berubah(): void
    {
        /*
         * Perintahnya memakai $today/$now lagi sesudah memanggil ini. Kalau
         * kelas ini memutasi acuannya, perhitungan berikutnya ikut bergeser —
         * jenis cacat yang tidak terlihat sampai dijalankan di produksi.
         *
         * Catatan kejujuran tentang penjaga ini: sifatnya dilindungi DUA lapis
         * yang saling menutupi — salinan Carbon::instance() di awal, dan
         * clone() di setiap pemakaian. Karena itu tidak ada satu baris pun yang
         * bisa mematahkannya sendirian; ia hanya terlihat merah bila KEDUANYA
         * dilepas. Itu sudah dibuktikan sekali dengan sabotase dua bagian,
         * sehingga uji ini bukan hiasan — tetapi pembaca berikutnya perlu tahu
         * bahwa melepas salah satu lapis saja tidak akan membuatnya berbunyi.
         */
        foreach ([null, '2026-06', '2026-09'] as $opsi) {
            $acuan = $this->acuan();
            $salinan = $acuan->copy();

            PeriodeBulananCuti::untuk($opsi, $acuan);

            $this->assertTrue($acuan->equalTo($salinan),
                'Acuan waktu termutasi oleh pemanggilan dengan --month='
                .var_export($opsi, true).'. Yang melindunginya dua lapis: salinan '
                .'Carbon::instance() di awal dan clone() di tiap pemakaian; kalau '
                .'keduanya lepas, startOfMonth() menggeser objek milik pemanggil.');
        }
    }

    public function test_tidak_ada_lagi_salinan_di_perintah(): void
    {
        foreach (self::BEKAS_PEMILIK_SALINAN as $berkas) {
            $isi = (string) file_get_contents(base_path($berkas));

            $this->assertStringContainsString('PeriodeBulananCuti::untuk(', $isi,
                "{$berkas} tidak lagi memakai rumah bersamanya.");
            $this->assertStringNotContainsString('Future month tidak boleh diproses.', $isi,
                "{$berkas} memegang aturannya sendiri lagi — pesan galatnya kembali "
                .'ditulis di sini, artinya keputusannya juga.');
        }
    }
}
