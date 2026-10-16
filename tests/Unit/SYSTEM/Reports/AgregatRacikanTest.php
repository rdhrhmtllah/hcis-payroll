<?php

namespace Tests\Unit\SYSTEM\Reports;

use App\Services\SYSTEM\Application\Reports\AgregatRacikan;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use PHPUnit\Framework\TestCase;

/**
 * Agregat racikan pengguna boleh bebas, TAPI tidak boleh menghasilkan angka
 * yang salah sambil tampak benar.
 *
 * Ukuran::class menyatakan bahwa agregat adalah "ekspresi lengkap yang KAMI
 * tulis, bukan hasil rakitan agregasi × field dari pengguna". Kelas yang diuji
 * di sini tidak melanggarnya — pengguna memilih PASANGAN dari whitelist, dan
 * ekspresinya tetap dirakit kode ini. Penjaga di bawah menagih pagarnya, bukan
 * sekadar bahwa fiturnya jalan.
 */
class AgregatRacikanTest extends TestCase
{
    private function dimensi(string $tipe, bool $sensitif = false): Dimensi
    {
        return new Dimensi('kolom', 'Kolom Uji', 'e.Kolom', $tipe, 'grup', $sensitif);
    }

    public function test_kolom_teks_hanya_boleh_dihitung(): void
    {
        // SUM atas teks bukan sekadar tak berarti — ia galat basis data, dan
        // galatnya muncul sebagai "laporan gagal dibuat" tanpa menyebut kolomnya.
        $teks = $this->dimensi('teks');

        $this->assertSame(['count', 'count_distinct'], AgregatRacikan::fungsiUntuk($teks));
        $this->assertFalse(AgregatRacikan::diizinkan($teks, 'sum'));
        $this->assertFalse(AgregatRacikan::diizinkan($teks, 'avg'));
        // MIN/MAX atas teks SAH secara SQL (abjad terkecil) tetapi tidak pernah
        // menjawab pertanyaan siapa pun — dan pada kolom sensitif ia
        // membocorkan satu nilai utuh lewat laporan yang mestinya cuma hitungan.
        $this->assertFalse(AgregatRacikan::diizinkan($teks, 'min'));
    }

    public function test_kolom_tanggal_boleh_min_max_tetapi_tidak_dijumlah(): void
    {
        $tanggal = $this->dimensi('tanggal');

        $this->assertTrue(AgregatRacikan::diizinkan($tanggal, 'max'));
        $this->assertFalse(AgregatRacikan::diizinkan($tanggal, 'sum'));
        $this->assertFalse(AgregatRacikan::diizinkan($tanggal, 'avg'));
    }

    public function test_rata_rata_dan_nilai_ekstrem_tidak_boleh_dijumlah_di_grand_total(): void
    {
        // Rata-rata dari rata-rata bukan rata-rata; nilai terbesar dari dua
        // kelompok bukan jumlah keduanya; dan satu instansi yang muncul di dua
        // kelompok terhitung dua kali. Ketiganya tetap tercetak rapi kalau
        // dijumlahkan — itulah kenapa penandanya wajib otomatis.
        $angka = $this->dimensi('angka');

        foreach (['avg', 'min', 'max', 'count_distinct'] as $fungsi) {
            $this->assertSame(
                Ukuran::TOTAL_ABAIKAN,
                AgregatRacikan::buat($angka, $fungsi)->totalisasi,
                "Fungsi {$fungsi} seharusnya tidak boleh dijumlahkan di GRAND TOTAL.",
            );
        }

        foreach (['sum', 'count'] as $fungsi) {
            $this->assertSame(Ukuran::TOTAL_JUMLAH, AgregatRacikan::buat($angka, $fungsi)->totalisasi);
        }
    }

    public function test_rata_rata_tidak_kehilangan_pecahannya(): void
    {
        // AVG pada kolom bilangan bulat di SQL Server membuang pecahan:
        // rata-rata 2,8 orang tercetak 2, tanpa satu pun galat.
        $agregat = AgregatRacikan::buat($this->dimensi('angka'), 'avg')->agregat;

        $this->assertStringContainsString('CAST(', $agregat);
        $this->assertStringContainsString('decimal', $agregat);
    }

    public function test_count_menghitung_kolom_bukan_baris(): void
    {
        // COUNT(*) diam-diam menghitung baris yang kolomnya NULL — yaitu baris
        // yang justru tidak punya apa pun untuk dihitung.
        $this->assertSame('COUNT(e.Kolom)', AgregatRacikan::buat($this->dimensi('angka'), 'count')->agregat);
        $this->assertSame(
            'COUNT(DISTINCT e.Kolom)',
            AgregatRacikan::buat($this->dimensi('angka'), 'count_distinct')->agregat,
        );
    }

    public function test_pasangan_terlarang_ditolak_bukan_diam_diam_diubah(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AgregatRacikan::buat($this->dimensi('teks'), 'sum');
    }

    public function test_label_bawaan_menyebut_fungsinya(): void
    {
        // "Jumlah Tamu" saja ambigu antara SUM dan COUNT; pada dataset yang
        // grain-nya satu kunjungan, keduanya menjawab pertanyaan yang berbeda —
        // orang versus kunjungan.
        $this->assertSame('Total Kolom Uji', AgregatRacikan::buat($this->dimensi('angka'), 'sum')->label);
        $this->assertSame('Jumlah Unik Kolom Uji', AgregatRacikan::buat($this->dimensi('angka'), 'count_distinct')->label);
        $this->assertSame('Namaku', AgregatRacikan::buat($this->dimensi('angka'), 'sum', 'Namaku')->label);
    }

    public function test_hitungan_atas_kolom_sensitif_tidak_menular_sensitif(): void
    {
        // Menghitung NIK tidak membocorkan satu pun NIK. Menyembunyikannya
        // justru membuat laporan kepatuhan mustahil dibuat tanpa hak penuh.
        $sensitif = $this->dimensi('angka', sensitif: true);

        $this->assertFalse(AgregatRacikan::buat($sensitif, 'count')->sensitif);
        $this->assertTrue(AgregatRacikan::buat($sensitif, 'max')->sensitif);
    }

    /**
     * Kunci racikan IKUT ke daftar ukuran terpilih.
     *
     * Tanpa ini agregatnya hanya bertambah di katalog: lolos seluruh validasi,
     * tidak menimbulkan galat, dan sama sekali tidak muncul di hasil. Pemakainya
     * menambah agregat, menekan Pratinjau, dan melihat tabel yang persis sama.
     * Persis itu yang terjadi pada percobaan pertama fitur ini.
     */
    public function test_kunci_racikan_masuk_daftar_ukuran(): void
    {
        $definisi = DefinisiLaporan::dariArray([
            'dataset' => 'tamu',
            'mode' => 'ringkasan',
            'dimensi' => ['tipe_tamu'],
            'ukuran' => ['jumlah_kunjungan'],
            'agregat' => [
                ['kolom' => 'jumlah_tamu', 'fungsi' => 'SUM'],
                ['kolom' => 'instansi_asal', 'fungsi' => 'count_distinct'],
            ],
        ]);

        $this->assertContains('jumlah_kunjungan', $definisi->ukuran);
        $this->assertContains(AgregatRacikan::kunci('jumlah_tamu', 'sum'), $definisi->ukuran);
        $this->assertContains(AgregatRacikan::kunci('instansi_asal', 'count_distinct'), $definisi->ukuran);
    }

    public function test_agregat_setengah_terisi_dibuang_saat_diurai(): void
    {
        // Baris perakit yang baru ditambahkan tetapi belum dipilih kolomnya
        // tidak boleh membuat seluruh definisi ditolak.
        $definisi = DefinisiLaporan::dariArray([
            'dataset' => 'tamu',
            'agregat' => [
                ['kolom' => '', 'fungsi' => 'sum'],
                ['kolom' => 'jumlah_tamu', 'fungsi' => ''],
                ['kolom' => 'jumlah_tamu', 'fungsi' => 'sum'],
            ],
        ]);

        $this->assertCount(1, $definisi->agregat);
    }

    /**
     * Satu kesalahan = SATU pesan.
     *
     * Kunci racikan otomatis ikut ke daftar ukuran, tetapi hanya masuk katalog
     * bila pasangannya sah. Tanpa pengecualian di validator, pasangan keliru
     * menghasilkan dua galat sekaligus — dan yang pertama berbunyi "Ukuran tidak
     * dikenal: racik__avg__nama", kunci internal yang tak berarti apa pun bagi
     * pemakainya sekaligus menutupi penjelasan yang sebenarnya.
     */
    public function test_pasangan_keliru_hanya_menghasilkan_satu_pesan(): void
    {
        $dataset = new class implements \App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan
        {
            public function kunci(): string
            {
                return 'uji';
            }

            public function label(): string
            {
                return 'Uji';
            }

            public function grain(): string
            {
                return 'Satu baris uji.';
            }

            public function dimensi(): array
            {
                return [new Dimensi('nama', 'Nama', 'e.Nama', 'teks', 'grup')];
            }

            public function ukuran(): array
            {
                return [new Ukuran('jumlah', 'Jumlah', 'COUNT(*)', 'angka')];
            }

            public function saringan(): array
            {
                return [];
            }

            public function baseQuery(): \Illuminate\Database\Query\Builder
            {
                throw new \LogicException('Tidak dipakai pada uji validasi.');
            }

            public function terapkanLingkup(\Illuminate\Database\Query\Builder $q, \App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup $lingkup): \Illuminate\Database\Query\Builder
            {
                return $q;
            }
        };

        $definisi = DefinisiLaporan::dariArray([
            'dataset' => 'uji',
            'mode' => 'ringkasan',
            'dimensi' => ['nama'],
            'ukuran' => ['jumlah'],
            'agregat' => [['kolom' => 'nama', 'fungsi' => 'avg']],
        ]);

        $galat = (new \App\Services\SYSTEM\Application\Reports\DefinisiValidator)->periksa($definisi, $dataset);

        $this->assertArrayHasKey('agregat', $galat);
        $this->assertArrayNotHasKey('ukuran', $galat, 'Kunci racikan bocor sebagai "ukuran tidak dikenal".');
        $this->assertStringContainsString('AVG', $galat['agregat'][0]);
        $this->assertStringContainsString('COUNT', $galat['agregat'][0]);
    }

    public function test_definisi_pulang_pergi_tanpa_kehilangan_agregat(): void
    {
        // Template disimpan sebagai array dan dibaca kembali; agregat yang
        // hilang saat perjalanan itu membuat template tersimpan diam-diam
        // kehilangan kolomnya pada pembukaan berikutnya.
        $asal = ['kolom' => 'jumlah_tamu', 'fungsi' => 'sum', 'label' => 'Total Orang'];

        $definisi = DefinisiLaporan::dariArray([
            'dataset' => 'tamu',
            'agregat' => [$asal],
        ]);

        $kembali = DefinisiLaporan::dariArray($definisi->toArray());

        $this->assertSame([$asal], $kembali->agregat);
    }
}
