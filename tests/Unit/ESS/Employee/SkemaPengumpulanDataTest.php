<?php

namespace Tests\Unit\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\SkemaPengumpulanData;
use Tests\TestCase;

class SkemaPengumpulanDataTest extends TestCase
{
    private function skema(): SkemaPengumpulanData
    {
        return app(SkemaPengumpulanData::class);
    }

    public function test_skema_punya_empat_langkah_berurutan(): void
    {
        $langkah = $this->skema()->langkah();

        $this->assertCount(4, $langkah);
        $this->assertSame(
            ['identitas', 'kontak', 'kepegawaian', 'darurat'],
            array_column($langkah, 'kunci'),
        );
    }

    public function test_field_atasan_hanya_ikut_validasi_untuk_phl(): void
    {
        $aturanPhl = $this->skema()->aturanValidasi(isPhl: true);
        $aturanBukanPhl = $this->skema()->aturanValidasi(isPhl: false);

        $this->assertArrayHasKey('atasan_spv', $aturanPhl);
        $this->assertContains('required', $aturanPhl['atasan_spv']);

        $this->assertArrayNotHasKey('atasan_spv', $aturanBukanPhl);
        $this->assertArrayNotHasKey('atasan_dept_head', $aturanBukanPhl);
    }

    public function test_nik_ktp_wajib_tepat_enam_belas_digit(): void
    {
        $aturan = $this->skema()->aturanValidasi(isPhl: false);

        $this->assertContains('digits:16', $aturan['nik_ktp']);
    }

    /**
     * Kode pajak TK/0..K/3 dicabut atas permintaan user: yang ditanyakan
     * sekarang status pernikahan dengan kata yang dimengerti orang. Jumlah
     * tanggungan ditanyakan terpisah, dan hanya kepada yang menikah — lihat
     * test_jumlah_tanggungan_hanya_ditanyakan_kepada_yang_menikah().
     */
    public function test_status_pernikahan_memakai_kata_yang_dimengerti_orang(): void
    {
        $field = $this->skema()->semuaField();

        $this->assertArrayNotHasKey('status_ptkp', $field, 'Kode PTKP sudah dicabut dari formulir.');

        $this->assertSame(
            ['Belum Menikah', 'Menikah', 'Cerai Hidup', 'Cerai Mati'],
            $field['status_pernikahan']['pilihan'],
        );

        // Dua keadaan yang paling peka dibacakan dengan kalimat yang lebih
        // lembut, sementara nilai yang tersimpan tetap istilah resmi.
        $this->assertSame('Bercerai', $field['status_pernikahan']['label_pilihan']['Cerai Hidup']);
        $this->assertSame(
            'Ditinggal wafat oleh pasangan',
            $field['status_pernikahan']['label_pilihan']['Cerai Mati'],
        );
    }

    /**
     * Sifat sebuah field DIDEKLARASIKAN, tidak ditebak dari labelnya.
     *
     * Penebakan lewat potongan kata pernah menjatuhkan formulir ini: label
     * 'Status Per-NIK-ahan' mengandung 'nik', sehingga pertanyaan status
     * pernikahan ikut dituntut '16 digit angka sesuai KTP' dan orangnya tidak
     * bisa maju. Uji ini mengikat sumber kebenarannya di config.
     */
    public function test_sifat_field_dideklarasikan_bukan_ditebak_dari_label(): void
    {
        $field = $this->skema()->semuaField();

        $this->assertSame('nik', $field['nik_ktp']['format']);
        $this->assertSame(16, $field['nik_ktp']['maks_digit']);

        foreach (['no_hp', 'darurat_no_hp'] as $kunci) {
            $this->assertSame('hp', $field[$kunci]['format'], $kunci);
            // Angka yang DIKETIK di kotak, tanpa +62 dan tanpa 0 di depan.
            // 13 angka + '62' di depan = batas atas aturan server.
            $this->assertSame(13, $field[$kunci]['maks_digit'], $kunci);
            $this->assertSame(9, $field[$kunci]['min_digit'], $kunci);
            $this->assertSame('8', $field[$kunci]['prefix'], $kunci);
            $this->assertSame('62', $field[$kunci]['prefix_simpan'], $kunci);
        }

        $this->assertSame('email', $field['email']['format']);

        // Yang bukan angka tidak boleh punya format sama sekali — di situlah
        // penebakan dulu menyelinap masuk.
        $this->assertArrayNotHasKey('format', $field['status_pernikahan']);
        $this->assertArrayNotHasKey('format', $field['nama_lengkap']);
    }

    /**
     * Batas UI dan aturan server harus menyebut angka yang sama.
     *
     * Polanya DIAMBIL dari aturan yang benar-benar dipakai server, bukan
     * disalin ke dalam uji: salinan akan tetap hijau setelah aturan aslinya
     * diubah, dan justru saat itulah orang mengetik penuh lalu ditolak sesudah
     * menekan Kirim.
     */
    public function test_batas_ketikan_hp_sama_dengan_aturan_server(): void
    {
        foreach (['no_hp', 'darurat_no_hp'] as $kunci) {
            $field = $this->skema()->semuaField()[$kunci];
            $pola = null;

            foreach ($field['aturan'] as $aturan) {
                if (is_string($aturan) && str_starts_with($aturan, 'regex:')) {
                    $pola = substr($aturan, 6);
                }
            }

            $this->assertNotNull($pola, "Aturan {$kunci} harus punya pola regex.");

            // Bentuk simpannya DIBACA dari skema, tidak disalin ke sini —
            // salinan akan tetap hijau setelah aturan aslinya berubah, dan
            // justru saat itulah browser menyimpan bentuk yang ditolak server.
            $awalanSimpan = $field['prefix_simpan'] ?? null;
            $this->assertNotNull($awalanSimpan, "{$kunci}: bentuk simpan harus dideklarasikan.");

            // Awalan ikut satu sumber: UI menuntut angka pertama '8', server
            // menuntut '62' + '8'. Kalau hanya salah satu yang tahu, nomor
            // seperti 021-2333… lolos di browser lalu ditolak saat Kirim.
            $this->assertSame('8', $field['prefix'], "{$kunci}: awalan ketik harus dideklarasikan.");

            $palingPanjang = $awalanSimpan.str_repeat('8', $field['maks_digit']);
            $palingPendek = $awalanSimpan.str_repeat('8', $field['min_digit']);

            $this->assertDoesNotMatchRegularExpression(
                $pola,
                '02'.str_repeat('1', $field['min_digit'] - 1),
                "{$kunci}: nomor yang tidak diawali {$awalanSimpan}{$field['prefix']} harus ditolak server.",
            );

            // Bentuk simpan LAMA ('08…', dipakai sampai 4 Sep 2026) harus
            // benar-benar ditolak. Tanpa penjaga ini pola bisa dilonggarkan
            // jadi menerima keduanya, dan dua bentuk hidup berdampingan lagi —
            // yang membuat deteksi HP kembar buta terhadap pasangan lintas-bentuk.
            $this->assertDoesNotMatchRegularExpression(
                $pola,
                '0'.str_repeat('8', $field['min_digit']),
                "{$kunci}: bentuk simpan lama '08…' tidak boleh ikut diterima.",
            );

            $this->assertMatchesRegularExpression($pola, $palingPanjang, "{$kunci}: batas atas UI harus masih diterima server.");
            $this->assertMatchesRegularExpression($pola, $palingPendek, "{$kunci}: batas bawah UI harus masih diterima server.");
            $this->assertDoesNotMatchRegularExpression($pola, $palingPanjang.'8', "{$kunci}: satu angka di atas batas UI harus sudah ditolak server.");
        }
    }

    public function test_untuk_ui_tidak_membocorkan_field_phl_ke_non_phl(): void
    {
        $ui = $this->skema()->untukUi(isPhl: false);

        $this->assertSame(4, $ui['versi']);
        $this->assertArrayNotHasKey('atasan_spv', $ui['field']);
        $this->assertNotContains('atasan_spv', $ui['langkah'][2]['field']);
    }

    /** Skema versi 4: 'D4/S1' dipisah, aturan server dan daftar pilihan harus sepakat. */
    public function test_pendidikan_d4_dan_s1_pilihan_terpisah(): void
    {
        $def = $this->skema()->semuaField()['pendidikan_terakhir'];

        $this->assertContains('D4', $def['pilihan']);
        $this->assertContains('S1', $def['pilihan']);
        $this->assertNotContains('D4/S1', $def['pilihan']);
        $this->assertContains('in:'.implode(',', $def['pilihan']), $def['aturan']);
    }

    public function test_departemen_hanya_ditanyakan_kepada_phl(): void
    {
        $this->assertArrayHasKey('departemen', $this->skema()->aturanValidasi(isPhl: true));
        $this->assertArrayNotHasKey('departemen', $this->skema()->aturanValidasi(isPhl: false));

        $uiNonPhl = $this->skema()->untukUi(isPhl: false);
        $this->assertArrayNotHasKey('departemen', $uiNonPhl['field']);
        $this->assertNotContains('departemen', $uiNonPhl['langkah'][2]['field']);
    }

    public function test_langkah_kepegawaian_non_phl_tersisa_pendidikan_dan_status_pernikahan(): void
    {
        $kepegawaian = $this->skema()->untukUi(isPhl: false)['langkah'][2];

        $this->assertSame(
            ['pendidikan_terakhir', 'status_pernikahan', 'jumlah_tanggungan'],
            $kepegawaian['field'],
        );
    }

    /**
     * Jumlah tanggungan ditanyakan kepada semua KECUALI yang belum menikah:
     * anak tetap menjadi tanggungan setelah bercerai atau ditinggal wafat.
     *
     * Syaratnya DIDEKLARASIKAN di config sebagai 'tampil_jika' dan dibaca satu
     * kali di sini, supaya browser dan server tidak menyimpulkannya
     * sendiri-sendiri. Kalau keduanya menyimpulkan terpisah, yang paling mungkin
     * terjadi adalah kotaknya hilang di layar tetapi tetap dituntut server —
     * jalan buntu tanpa isian yang bisa memuaskannya.
     */
    public function test_jumlah_tanggungan_ditanyakan_kecuali_kepada_yang_belum_menikah(): void
    {
        $field = $this->skema()->semuaField();
        $def = $field['jumlah_tanggungan'];
        $pilihanStatus = $field['status_pernikahan']['pilihan'];

        $this->assertSame('status_pernikahan', $def['tampil_jika']['field']);
        $this->assertSame(['Menikah', 'Cerai Hidup', 'Cerai Mati'], $def['tampil_jika']['nilai']);

        // Tiap nilai harus benar-benar ada di daftar pilihan status pernikahan.
        // Salah ketik di sini membuat kotaknya tidak pernah muncul, tanpa galat.
        foreach ($def['tampil_jika']['nilai'] as $nilai) {
            $this->assertContains($nilai, $pilihanStatus, "'{$nilai}' bukan pilihan status pernikahan.");
        }

        // Penjaga yang menuntut KEPUTUSAN, bukan sekadar mencatat keadaan:
        // status pernikahan yang ditambahkan kelak tidak boleh diam-diam
        // kehilangan pertanyaan ini. Kalau memang tidak perlu, kecualikan
        // dengan sadar di sini.
        $this->assertSame(
            array_values(array_diff($pilihanStatus, ['Belum Menikah'])),
            $def['tampil_jika']['nilai'],
            'Ada pilihan status pernikahan yang belum diputuskan ikut ditanyai tanggungan atau tidak.',
        );
    }

    public function test_aturan_jumlah_tanggungan_mengikuti_jawaban_status_pernikahan(): void
    {
        $skema = $this->skema();

        foreach (['Menikah', 'Cerai Hidup', 'Cerai Mati'] as $status) {
            $aturan = $skema->aturanValidasi(isPhl: false, jawaban: ['status_pernikahan' => $status]);

            $this->assertArrayHasKey('jumlah_tanggungan', $aturan, $status);
            $this->assertContains('required', $aturan['jumlah_tanggungan'], $status);
        }

        $belum = $skema->aturanValidasi(isPhl: false, jawaban: ['status_pernikahan' => 'Belum Menikah']);
        $this->assertArrayNotHasKey('jumlah_tanggungan', $belum);

        // Tanpa jawaban = seluruh katalog pertanyaan (dipakai ekspor dan admin),
        // bukan katalog yang menyusut diam-diam.
        $this->assertArrayHasKey('jumlah_tanggungan', $skema->aturanValidasi(isPhl: false));
    }

    public function test_batas_jumlah_tanggungan_satu_sumber_dengan_batas_ketikan(): void
    {
        $def = $this->skema()->semuaField()['jumlah_tanggungan'];

        $this->assertSame('angka', $def['format']);

        $maks = null;
        foreach ($def['aturan'] as $aturan) {
            if (is_string($aturan) && str_starts_with($aturan, 'max:')) {
                $maks = (int) substr($aturan, 4);
            }
        }

        $this->assertNotNull($maks, 'Aturan jumlah tanggungan harus punya batas atas.');
        $this->assertSame($maks, $def['nilai_maks'], 'Batas UI dan batas server harus angka yang sama.');

        // Batas ketikan harus cukup untuk mengetik batas atasnya — kalau tidak,
        // angka yang sah tidak akan pernah bisa diketik penuh.
        $this->assertSame(strlen((string) $maks), $def['maks_digit']);
    }

    /**
     * Tanggal lahir SENGAJA tidak berbatas usia.
     *
     * Batas 15-70 tahun pernah ada, lalu dilepas atas keputusan pemilik pada
     * 3 Sep 2026 — bersamaan dengan dinyalakannya prefill dari data lama.
     * Keduanya satu paket: 56 dari 957 karyawan aktif punya Tgl_Lahir yang
     * jelas bukan tanggal lahir (2019-2025, contohnya A2 = 2025-04-10), dan
     * dengan prefill menyala batas usia membuat formulir mereka mustahil
     * dikirim.
     *
     * Penjaga ini menuntut keadaan itu dinyatakan, bukan sekadar terjadi.
     * Satu-satunya pagar yang tersisa adalah "tidak boleh di masa depan", dan
     * kalender di formatBidang.js berhenti di hari yang sama — kalender tidak
     * pernah boleh menawarkan tanggal yang lalu ditolak server. Kalau kelak
     * batas usia dipasang lagi, ia harus dipasang di KEDUA sisi, dan uji ini
     * akan menyala lebih dulu untuk mengingatkannya.
     */
    public function test_tanggal_lahir_hanya_dipagari_hari_ini(): void
    {
        $field = $this->skema()->semuaField()['tanggal_lahir'];

        $this->assertSame('tanggal_lahir', $field['format']);
        $this->assertContains('before_or_equal:today', $field['aturan']);

        $this->assertArrayNotHasKey('usia_min', $field);
        $this->assertArrayNotHasKey('usia_maks', $field);

        foreach ($field['aturan'] as $aturan) {
            $this->assertDoesNotMatchRegularExpression(
                '/^(before|after):-\d+ years$/',
                (string) $aturan,
                'Batas usia dipasang lagi di server. Pasang juga di kalender '.
                '(batasTanggalLahir di formatBidang.js), kalau tidak kalender '.
                'akan menawarkan tanggal yang kemudian ditolak.',
            );
        }
    }

    /**
     * Tidak ada kalimat mesin berbahasa Inggris yang boleh sampai ke layar
     * karyawan. Yang pernah terlihat: "The tanggal lahir field must be a date
     * before -15 years." — nama kolom mentah, potongan aturan mentah, bahasa
     * yang bukan bahasa penggunanya.
     */
    public function test_pesan_validasi_berbahasa_indonesia_dan_menyebut_label(): void
    {
        $skema = $this->skema();

        $galat = \Illuminate\Support\Facades\Validator::make(
            ['tanggal_lahir' => '2099-01-01'],
            ['tanggal_lahir' => $skema->semuaField()['tanggal_lahir']['aturan']],
            $skema->pesanValidasi(),
            $skema->atributValidasi(),
        )->errors()->first('tanggal_lahir');

        $this->assertStringNotContainsStringIgnoringCase('must be', $galat);
        $this->assertStringNotContainsStringIgnoringCase('field', $galat);
        $this->assertStringNotContainsString('before_or_equal', $galat);
        $this->assertMatchesRegularExpression('/masa depan/i', $galat);
    }

    public function test_nama_field_yang_muncul_di_pesan_adalah_labelnya(): void
    {
        $skema = $this->skema();

        $galat = \Illuminate\Support\Facades\Validator::make(
            ['nik_ktp' => ''],
            ['nik_ktp' => $skema->semuaField()['nik_ktp']['aturan']],
            $skema->pesanValidasi(),
            $skema->atributValidasi(),
        )->errors()->first('nik_ktp');

        // 'nik_ktp' mentah tidak pernah dibaca orang; yang dibaca 'Nomor KTP (NIK)'.
        $this->assertStringContainsString('Nomor KTP (NIK)', $galat);
        $this->assertStringNotContainsString('nik_ktp', $galat);
    }

    /**
     * Kalimat penolakan nomor HP harus menyebut BENTUK yang benar. Sebelumnya
     * yang muncul cuma 'Format Nomor HP / WhatsApp belum benar.' — benar secara
     * bahasa, tapi tidak memberi tahu apa pun yang bisa dikerjakan orangnya.
     */
    public function test_pesan_nomor_hp_menyebut_bentuk_yang_benar(): void
    {
        $skema = $this->skema();

        $galat = \Illuminate\Support\Facades\Validator::make(
            ['no_hp' => '02123333333333'],
            ['no_hp' => $skema->semuaField()['no_hp']['aturan']],
            $skema->pesanValidasi(),
            $skema->atributValidasi(),
        )->errors()->first('no_hp');

        // Kalimatnya harus memakai SATUAN KOTAKNYA. Kotak itu sudah menampilkan
        // '+62' sebagai awalan tetap, jadi yang diketik orang '8123456789' —
        // menyuruhnya "diawali 08" menyebut angka yang tidak pernah ia ketik.
        $this->assertStringContainsString('+62', $galat);
        $this->assertStringContainsString('8123456789', $galat);
        $this->assertStringNotContainsString('08', $galat);
        $this->assertMatchesRegularExpression('/contoh/i', $galat);

        // Angka panjangnya juga harus satu satuan dengan kotaknya: 9-13 angka
        // yang DIKETIK, bukan 9-13 angka sesudah '08' (yang sebenarnya 8-12).
        $field = $skema->semuaField()['no_hp'];
        $this->assertStringContainsString((string) $field['min_digit'], $galat);
        $this->assertStringContainsString((string) $field['maks_digit'], $galat);
    }

    // -------------------------------------------------------------------------
    // Sakelar env per pertanyaan (config/services.php → hcis.pengumpulan_data).
    // -------------------------------------------------------------------------

    public function test_pertanyaan_phl_aktif_secara_baku(): void
    {
        $ui = $this->skema()->untukUi(isPhl: true);

        foreach (['departemen', 'atasan_spv', 'atasan_dept_head'] as $kunci) {
            $this->assertTrue($this->skema()->pertanyaanAktif($kunci));
            $this->assertArrayHasKey($kunci, $ui['field']);
        }
    }

    public function test_pertanyaan_yang_dimatikan_hilang_dari_wizard_dan_validasi(): void
    {
        config(['services.hcis.pengumpulan_data.pertanyaan_aktif.atasan_spv' => false]);

        $skema = $this->skema();
        $ui = $skema->untukUi(isPhl: true);
        $aturan = $skema->aturanValidasi(isPhl: true);

        $this->assertArrayNotHasKey('atasan_spv', $ui['field']);
        $this->assertNotContains('atasan_spv', array_merge(...array_column($ui['langkah'], 'field')));
        $this->assertArrayNotHasKey('atasan_spv', $aturan);

        // Sakelar satu pertanyaan tidak boleh ikut mematikan tetangganya.
        $this->assertArrayHasKey('atasan_dept_head', $aturan);
        $this->assertArrayHasKey('departemen', $aturan);

        // Katalog lengkap (ekspor, jawaban lama) tetap memuatnya.
        $this->assertArrayHasKey('atasan_spv', $skema->semuaField());
    }

    // -------------------------------------------------------------------------
    // Atasan PHL: teks bebas (19 Sep 2026); pencarian disembunyikan, tidak dihapus.
    // -------------------------------------------------------------------------

    public function test_atasan_phl_baku_teks_bebas_tanpa_aturan_kode_karyawan(): void
    {
        $skema = $this->skema();
        $ui = $skema->untukUi(isPhl: true);
        $aturan = $skema->aturanValidasi(isPhl: true);

        foreach (['atasan_spv', 'atasan_dept_head'] as $kunci) {
            $this->assertSame('teks', $ui['field'][$kunci]['tipe']);
            $this->assertArrayNotHasKey('isian_teks', $ui['field'][$kunci], 'Blok pengganti tidak boleh bocor ke UI.');
            $this->assertContains('min:3', $aturan[$kunci]);
            $this->assertContains('max:150', $aturan[$kunci]);
            // Tanpa aturan 'exists': nama yang ditulis bebas tidak dicocokkan ke tabel Karyawan.
            $this->assertEmpty(array_filter($aturan[$kunci], fn ($a) => ! is_string($a)), "{$kunci} masih membawa aturan objek.");
        }

        $this->assertNotContains('cari_karyawan', array_column($skema->semuaField(), 'tipe'));
    }

    /**
     * Judul langkah 3 menjanjikan isinya. Bagi karyawan tetap, seluruh
     * pertanyaan pekerjaan (departemen + dua atasan) tersaring habis, jadi
     * "Info Pekerjaan" menyuruh orang mencari kolom yang tidak akan muncul.
     */
    public function test_judul_langkah_kepegawaian_mengikuti_isinya(): void
    {
        $indeks = 2;

        $phl = $this->skema()->untukUi(isPhl: true)['langkah'][$indeks];
        $this->assertSame('kepegawaian', $phl['kunci']);
        $this->assertSame('Info Pekerjaan', $phl['judul']);
        $this->assertArrayNotHasKey('judul_tanpa_phl', $phl, 'Judul cadangan tidak boleh bocor ke UI.');

        $tetap = $this->skema()->untukUi(isPhl: false)['langkah'][$indeks];
        $this->assertSame('Pendidikan & Keluarga', $tetap['judul']);
        $this->assertStringNotContainsStringIgnoringCase('pekerjaan', $tetap['deskripsi']);
        $this->assertSame(['pendidikan_terakhir', 'status_pernikahan', 'jumlah_tanggungan'], $tetap['field']);
    }

    /**
     * Diputuskan dari field yang tampil, bukan dari status PHL: mematikan
     * ketiga pertanyaan pekerjaan lewat env meninggalkan isi yang sama persis
     * dengan milik karyawan tetap, jadi judulnya harus ikut.
     */
    public function test_judul_ikut_berubah_bila_pertanyaan_pekerjaan_dimatikan(): void
    {
        config([
            'services.hcis.pengumpulan_data.pertanyaan_aktif.departemen' => false,
            'services.hcis.pengumpulan_data.pertanyaan_aktif.atasan_spv' => false,
            'services.hcis.pengumpulan_data.pertanyaan_aktif.atasan_dept_head' => false,
        ]);

        $langkah = $this->skema()->untukUi(isPhl: true)['langkah'][2];

        $this->assertSame('Pendidikan & Keluarga', $langkah['judul']);
    }

    public function test_pencarian_atasan_masih_utuh_bila_sakelar_dinyalakan(): void
    {
        config(['services.hcis.pengumpulan_data.atasan_pakai_pencarian' => true]);

        $skema = $this->skema();
        $aturan = $skema->aturanValidasi(isPhl: true);

        foreach (['atasan_spv', 'atasan_dept_head'] as $kunci) {
            $this->assertSame('cari_karyawan', $skema->semuaField()[$kunci]['tipe']);
            $this->assertContains('max:20', $aturan[$kunci]);
            $this->assertNotEmpty(array_filter($aturan[$kunci], fn ($a) => ! is_string($a)), "{$kunci} kehilangan aturan 'exists'.");
        }
    }
}
