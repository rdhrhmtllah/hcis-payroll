<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Leave\Shared\TanggalCutiBersama;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Aturan "item cuti bersama → daftar tanggal" tinggal di SATU rumah.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * Aturan ini pernah tersalin BYTE-PER-BYTE di tiga controller: CutiController,
 * AbsensiController, dan uDashController — 37 baris, sidik isi identik, hanya
 * nama metodenya yang beda pada salah satunya.
 *
 * Tiga salinan berarti perbaikan pada satu tempat tidak pernah sampai ke dua
 * lainnya, dan itu bukan kekhawatiran teoretis untuk logika INI: himpunan
 * tanggal cuti sudah pernah melahirkan cacat overbooking. Kelas ini sendiri
 * membuktikannya — begitu ketiganya jadi satu, satu cacat yang selama ini
 * terbawa di ketiga salinan langsung bisa diperbaiki sekali (lihat uji
 * "entri kosong").
 *
 * Uji di sini menguji PERILAKU, bukan hanya bentuk. Fungsinya murni, jadi tidak
 * ada alasan untuk puas dengan pemeriksaan tekstual saja.
 */
class TanggalCutiBersamaSatuRumahTest extends TestCase
{
    /** Ketiga berkas yang dulu memegang salinannya sendiri. */
    private const BEKAS_PEMILIK_SALINAN = [
        'app/Http/Controllers/ESS/Attendance/Leave/Personal/CutiController.php',
        'app/Http/Controllers/ESS/Attendance/Legacy/AbsensiController.php',
        'app/Http/Controllers/ESS/Dashboard/Personal/uDashController.php',
    ];

    /** Lapis di atasnya, yang kini dipakai kedua controller kalender. */
    private const RUMAH_KALENDER = 'app/Services/ESS/Attendance/Leave/Shared/KalenderCutiBersama.php';

    public function test_selected_dates_menang_atas_rentang(): void
    {
        $hasil = TanggalCutiBersama::dariItem([
            'selected_dates' => ['2026-05-20', '2026-05-18'],
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-01-31',
        ]);

        $this->assertSame(
            ['2026-05-18', '2026-05-20'],
            $hasil,
            'selected_dates lebih spesifik daripada rentang, jadi ia yang dipakai — '.
            'dan hasilnya terurut serta unik.',
        );
    }

    public function test_rentang_dibentangkan_dan_yang_terbalik_ditukar(): void
    {
        $this->assertSame(
            ['2026-05-01', '2026-05-02', '2026-05-03'],
            TanggalCutiBersama::dariItem(['tanggal_mulai' => '2026-05-01', 'tanggal_selesai' => '2026-05-03']),
        );

        $this->assertSame(
            ['2026-05-01', '2026-05-02', '2026-05-03'],
            TanggalCutiBersama::dariItem(['tanggal_mulai' => '2026-05-03', 'tanggal_selesai' => '2026-05-01']),
            'Rentang terbalik ditukar, bukan ditolak: itu salah ketik, dan menolaknya '.
            'menghilangkan harinya sama sekali.',
        );

        $this->assertSame(
            ['2026-05-07'],
            TanggalCutiBersama::dariItem(['tanggal_mulai' => '2026-05-07']),
            'tanggal_selesai kosong berarti satu hari.',
        );
    }

    public function test_entri_kosong_tidak_menjadi_hari_ini(): void
    {
        /*
         * INI CACAT YANG DIPERBAIKI SAAT KETIGA SALINAN DISATUKAN, dan ia tidak
         * kentara: Carbon::parse(null), parse('') dan parse(' ') TIDAK melempar
         * — ketiganya mengembalikan HARI INI. Jadi satu entri kosong di
         * selected_dates diam-diam menjadi tanggal cuti hari ini, lalu ikut
         * terhitung sebagai pemakaian jatah.
         *
         * Uji ini memakai tanggal hari ini sebagai jangkar justru supaya ia
         * gagal tepat pada bentuk lamanya.
         */
        $hariIni = Carbon::now()->toDateString();

        $hasil = TanggalCutiBersama::dariItem([
            'selected_dates' => ['2026-03-01', null, '', '   '],
        ]);

        $this->assertSame(['2026-03-01'], $hasil);
        $this->assertNotContains(
            $hariIni,
            $hasil,
            'Entri kosong berubah menjadi HARI INI. Carbon::parse(null) tidak melempar; '.
            'ia mengembalikan sekarang. Buang entri kosong sebelum diurai.',
        );
    }

    public function test_teks_yang_benar_benar_rusak_dibuang_bukan_menggagalkan_semuanya(): void
    {
        $this->assertSame(
            ['2026-03-01'],
            TanggalCutiBersama::dariItem(['selected_dates' => ['2026-03-01', 'bukan-tanggal']]),
            'Satu baris rusak tidak boleh menghapus seluruh kalender cuti bersama seseorang.',
        );
    }

    public function test_masukan_tanpa_tanggal_menghasilkan_larik_kosong(): void
    {
        foreach ([[], ['tanggal_mulai' => null], ['tanggal_mulai' => ''], ['tanggal_mulai' => 'xxx']] as $item) {
            $this->assertSame([], TanggalCutiBersama::dariItem($item));
        }
    }

    public function test_tidak_ada_lagi_salinan_di_controller(): void
    {
        foreach (self::BEKAS_PEMILIK_SALINAN as $berkas) {
            $isi = (string) file_get_contents(base_path($berkas));

            $this->assertDoesNotMatchRegularExpression(
                '/function normalizeCutiBersama\w*\s*\(/',
                $isi,
                "{$berkas} memegang salinannya sendiri lagi. Aturan ini punya satu rumah: ".
                'TanggalCutiBersama::dariItem(). Salinan kedua berarti perbaikan '.
                'berikutnya hanya sampai ke salah satunya.',
            );

            /*
             * Boleh memanggil rumah bersamanya LANGSUNG, atau lewat
             * KalenderCutiBersama yang memakainya. AbsensiController dan
             * uDashController naik satu lapis saat pemeta tanggal-ke-judul
             * dipusatkan: keduanya tidak lagi menyebut dariItem() sendiri,
             * tetapi aturannya tetap satu — dan lapis di atasnya punya
             * penjaganya sendiri, KalenderCutiBersamaSatuRumahTest.
             *
             * Yang tetap dilarang adalah menulis ulang aturannya dengan nama
             * lain, dan itu dijaga oleh assertion di atas.
             */
            $lewatRumah = str_contains($isi, 'TanggalCutiBersama::dariItem(')
                || str_contains($isi, 'KalenderCutiBersama');

            $this->assertTrue(
                $lewatRumah,
                "{$berkas} tidak lagi memanggil rumah bersamanya, langsung maupun ".
                'lewat KalenderCutiBersama — apakah aturannya ditulis ulang dengan nama lain?',
            );
        }
    }

    public function test_lapis_kalender_memakai_rumah_tanggal_bukan_aturannya_sendiri(): void
    {
        /*
         * Kalau KalenderCutiBersama suatu saat menghitung tanggalnya sendiri,
         * assertion "boleh lewat KalenderCutiBersama" di atas berubah dari
         * penjaga menjadi lubang.
         */
        /*
         * Komentar DIBUANG lebih dulu. Docblock kelas itu menyebut
         * TanggalCutiBersama::dariItem() dalam kalimat penjelasannya, dan
         * membaca berkas mentah membuat assertion ini hijau walau kodenya
         * berhenti memanggilnya — persis kegagalan yang sabotase pertama
         * penjaga ini temukan.
         */
        $isi = '';
        foreach (token_get_all((string) file_get_contents(base_path(self::RUMAH_KALENDER))) as $t) {
            if (is_array($t)) {
                if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                    continue;
                }
                $isi .= $t[1];
            } else {
                $isi .= $t;
            }
        }

        $this->assertStringContainsString('TanggalCutiBersama::dariItem(', $isi,
            'Lapis kalender tidak lagi memakai rumah tanggal — aturannya kembali bercabang.');
    }

    public function test_implementasi_kedua_yang_disengaja_tetap_terdokumentasi(): void
    {
        /*
         * PenentuTanggalTidakOperasional menegakkan aturan yang sama dengan
         * kontrak masukan berbeda (selected_dates bisa berupa STRING JSON dari
         * kolom basis data). Ia SENGAJA tidak digabung.
         *
         * Yang dijaga di sini bukan kodenya melainkan alasannya: divergensi yang
         * tak tertulis akan dibaca orang berikutnya sebagai kelalaian, lalu
         * "dirapikan" — dan perapian itu mengubah perilaku logika kuota cuti.
         */
        $isi = (string) file_get_contents(
            base_path('app/Services/ESS/Attendance/Calendar/Operational/PenentuTanggalTidakOperasional.php'),
        );

        $this->assertStringContainsString(
            'TanggalCutiBersama::dariItem()',
            $isi,
            'Implementasi kedua harus menyebut rumah bersamanya, supaya pembaca tahu '.
            'ada dua dan tahu di mana yang satunya.',
        );

        $this->assertStringContainsString(
            'STRING JSON',
            $isi,
            'Alasan divergensinya harus tertulis. Tanpa itu, orang berikutnya akan '.
            'menggabungnya dan mengubah perilaku kuota cuti tanpa sadar.',
        );
    }
}
