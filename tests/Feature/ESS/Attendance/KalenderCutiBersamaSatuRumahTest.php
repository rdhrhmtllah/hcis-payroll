<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Leave\Shared\KalenderCutiBersama;
use Tests\TestCase;

/**
 * Peta tanggal → judul cuti bersama: satu rumah untuk dua kalender.
 *
 * ── Cacat yang ditutup ───────────────────────────────────────────────────────
 *
 * getCutiBersamaByDateForKaryawan() ada dua salinan identik — 1.174 byte sama
 * persis di AbsensiController dan uDashController. Keduanya memberi makan
 * kalender yang dilihat karyawan: satu di halaman absensi, satu di dashboard
 * personal. Dua salinan berarti dua kalender yang bisa berbeda isinya tanpa ada
 * yang menyadari, dan yang berbeda itu hari libur — bukan hiasan.
 *
 * Tanggalnya sendiri sudah lama punya rumah (TanggalCutiBersama::dariItem,
 * dibuat justru karena aturan yang sama pernah tersalin di TIGA controller).
 * Yang belum punya rumah adalah lapisan di atasnya: menyaring ke rentang,
 * mengelompokkan judul per tanggal, lalu menggabungkannya.
 *
 * ── Dua perilaku yang dijaga apa adanya, bukan dirapikan ─────────────────────
 *
 *   1. Item tanpa `nama_cuti_bersama` DIBUANG — padahal CutiController, yang
 *      membaca sumber yang sama, memberinya nilai baku 'Cuti Bersama'.
 *   2. Rentang terbalik menghasilkan peta KOSONG, tidak ditukar — padahal
 *      dariItem() dan cutiBersamaItemOverlapsRange() menukarnya.
 *
 * Keduanya diuji supaya jelas bahwa perpindahan ini TIDAK mengubah apa yang
 * dilihat pengguna. Menyeragamkannya keputusan produk, dan uji ini akan merah
 * saat keputusan itu diambil — yang memang seharusnya.
 *
 * Uji ini tidak menyentuh basis data: petakan() murni, masukannya larik item.
 */
class KalenderCutiBersamaSatuRumahTest extends TestCase
{
    private const BEKAS_PEMILIK_SALINAN = [
        'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php',
        'app/Http/Controllers/ESS/Dashboard/Personal/uDashController.php',
    ];

    /** @return array<string, mixed> */
    private function item(string $judul, array $tanggal): array
    {
        return ['nama_cuti_bersama' => $judul, 'selected_dates' => $tanggal];
    }

    public function test_hanya_tanggal_di_dalam_rentang_yang_masuk(): void
    {
        $hasil = KalenderCutiBersama::petakan([
            $this->item('Lebaran', ['2026-03-19', '2026-03-20', '2026-03-25']),
        ], '2026-03-20', '2026-03-24');

        $this->assertSame(['2026-03-20' => 'Lebaran'], $hasil,
            'Tanggal di luar rentang tidak boleh ikut — kalender mingguan akan '
            .'menampilkan hari libur yang bukan miliknya.');
    }

    public function test_batas_rentang_bersifat_inklusif(): void
    {
        $hasil = KalenderCutiBersama::petakan([
            $this->item('Nyepi', ['2026-03-20', '2026-03-24']),
        ], '2026-03-20', '2026-03-24');

        $this->assertSame(
            ['2026-03-20' => 'Nyepi', '2026-03-24' => 'Nyepi'],
            $hasil,
            'Hari pertama dan terakhir rentang harus ikut.');
    }

    public function test_beberapa_judul_pada_satu_tanggal_digabung_dan_tidak_kembar(): void
    {
        $hasil = KalenderCutiBersama::petakan([
            $this->item('Cuti Bersama A', ['2026-05-01']),
            $this->item('Cuti Bersama B', ['2026-05-01']),
            $this->item('Cuti Bersama A', ['2026-05-01']),   // kembar: harus lebur
        ], '2026-05-01', '2026-05-31');

        $this->assertSame(['2026-05-01' => 'Cuti Bersama A, Cuti Bersama B'], $hasil);
    }

    public function test_item_tanpa_judul_dibuang(): void
    {
        /*
         * Perilaku LAMA yang dipertahankan. CutiController memberi nilai baku
         * 'Cuti Bersama' untuk item yang sama; menyeragamkannya keputusan
         * produk — apakah hari libur tanpa nama pantas muncul di kalender.
         */
        $hasil = KalenderCutiBersama::petakan([
            ['selected_dates' => ['2026-05-01']],
            ['nama_cuti_bersama' => '', 'selected_dates' => ['2026-05-02']],
            ['nama_cuti_bersama' => null, 'selected_dates' => ['2026-05-03']],
            $this->item('Bernama', ['2026-05-04']),
        ], '2026-05-01', '2026-05-31');

        $this->assertSame(['2026-05-04' => 'Bernama'], $hasil);
    }

    public function test_rentang_terbalik_menghasilkan_peta_kosong(): void
    {
        /*
         * Juga perilaku lama, dan tidak konsisten dengan tetangganya:
         * TanggalCutiBersama::dariItem menukar rentang terbalik, begitu pula
         * cutiBersamaItemOverlapsRange. Lapis ini tidak. Dibiarkan supaya
         * perpindahan ini tidak diam-diam mengubah hasil; uji ini yang membuat
         * ketidakkonsistenannya terlihat alih-alih tersembunyi.
         */
        $hasil = KalenderCutiBersama::petakan([
            $this->item('Lebaran', ['2026-03-20']),
        ], '2026-03-24', '2026-03-20');

        $this->assertSame([], $hasil);
    }

    public function test_bentuk_rentang_item_ikut_terbaca_lewat_rumah_tanggal(): void
    {
        /*
         * Item boleh menyebut tanggalnya sebagai rentang, bukan selected_dates.
         * Lapis ini tidak boleh punya aturannya sendiri — ia harus mewarisi
         * TanggalCutiBersama::dariItem, termasuk aturan "selected_dates menang".
         */
        $hasil = KalenderCutiBersama::petakan([
            [
                'nama_cuti_bersama' => 'Rentang',
                'tanggal_mulai' => '2026-06-01',
                'tanggal_selesai' => '2026-06-03',
            ],
        ], '2026-06-01', '2026-06-30');

        $this->assertSame([
            '2026-06-01' => 'Rentang',
            '2026-06-02' => 'Rentang',
            '2026-06-03' => 'Rentang',
        ], $hasil);
    }

    public function test_selected_dates_menang_atas_rentang_di_lapis_ini_juga(): void
    {
        $hasil = KalenderCutiBersama::petakan([
            [
                'nama_cuti_bersama' => 'Pilihan',
                'selected_dates' => ['2026-06-10'],
                'tanggal_mulai' => '2026-06-01',
                'tanggal_selesai' => '2026-06-30',
            ],
        ], '2026-06-01', '2026-06-30');

        $this->assertSame(['2026-06-10' => 'Pilihan'], $hasil,
            'selected_dates lebih spesifik daripada rentang dan harus menang — '
            .'kalau tidak, satu item menandai sebulan penuh sebagai libur.');
    }

    public function test_daftar_item_kosong_menghasilkan_peta_kosong(): void
    {
        $this->assertSame([], KalenderCutiBersama::petakan([], '2026-01-01', '2026-12-31'));
    }

    public function test_tidak_ada_lagi_salinan_di_controller(): void
    {
        foreach (self::BEKAS_PEMILIK_SALINAN as $berkas) {
            $isi = (string) file_get_contents(base_path($berkas));

            $this->assertStringContainsString('KalenderCutiBersama', $isi,
                "{$berkas} tidak lagi memakai rumah bersamanya.");

            /*
             * Penanda salinan: mengelompokkan judul per tanggal SENDIRI.
             * Delegasinya cuma satu baris, jadi tidak mungkin memuat ini.
             */
            $this->assertStringNotContainsString("\$item['nama_cuti_bersama']", $isi,
                "{$berkas} membaca judul cuti bersama sendiri lagi — apakah pemetanya "
                .'ditulis ulang di sini?');
        }
    }
}
