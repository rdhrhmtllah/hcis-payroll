<?php

namespace Tests\Feature\KPI\PerformanceAppraisal\Support;

/**
 * Pembanding bentuk respons terhadap fixture yang dipakai frontend.
 *
 * Diangkat menjadi trait karena dua berkas uji sekarang membandingkan hal yang sama.
 * Dua salinan logika perbandingan adalah dua hal yang bisa berselisih — dan yang
 * berselisih di sini adalah penjaga yang seharusnya mencegah perselisihan.
 *
 * Yang dibandingkan BENTUKNYA, bukan nilainya: nilai memang berbeda tiap database,
 * susunan kunci tidak boleh.
 *
 * Dipakai `KontrakResponsTest` dan `KontrakLaporanTest`. Yang pertama sempat memegang
 * salinan privatnya sendiri; dialihkan ke sini pada 1 September 2026, sehingga kini hanya
 * ada satu pembanding di seluruh modul.
 */
trait MembandingkanBentukRespons
{
    /**
     * Direktori fixture, relatif terhadap akar proyek.
     *
     * Milik trait, bukan kelas ujinya. Menaruhnya di kelas berarti dua sumber kebenaran
     * untuk satu jalur — persis jenis perselisihan yang membuat pembanding ini diangkat
     * ke sini.
     */
    protected const DIR_FIXTURE = 'resources/js/Pages/KPI/PerformanceAppraisal/api/fixtures';

    /**
     * Alamat peta yang kuncinya adalah NILAI BISNIS, bukan nama field.
     *
     * `per_status` berkunci `SELESAI`, `BELUM_MULAI`, dan seterusnya — kuncinya berbeda
     * antar siklus, jadi membandingkannya harfiah akan gagal untuk alasan yang salah.
     * Peta seperti itu dibandingkan lewat satu anaknya saja.
     *
     * Kelas yang memakai trait ini boleh menimpanya.
     *
     * @return list<string>
     */
    protected function petaDinamis(): array
    {
        return [];
    }

    /**
     * Menyalin respons SUNGGUHAN ke berkas berdampingan, bila diminta lewat lingkungan.
     *
     * Dinyalakan dengan `PA_REKAM_RESPONS=1`. Keluarannya TIDAK menimpa fixture: ia ditulis
     * ke `storage/app/pa-respons/<nama>` supaya perbaruan fixture tetap keputusan manusia
     * yang membaca selisihnya. Fixture yang ditimpa otomatis berhenti menjadi penjaga —
     * ia akan selalu cocok dengan apa pun yang dikembalikan kode hari itu, termasuk yang
     * rusak.
     *
     * Alasannya ada di sini dan bukan sebagai perintah tersendiri: kontrak frontend
     * bertambah kunci berkali-kali (result.lingkup, keranjang.perlu_verifikasi,
     * talent_highlights), dan tiap kali penyalinannya dikerjakan dengan tangan — cara
     * paling andal untuk melewatkan satu kunci.
     */
    private function rekamBilaDiminta(string $namaFixture, array $nyata): void
    {
        if ((string) env('PA_REKAM_RESPONS', '') !== '1') {
            return;
        }

        $dir = storage_path('app/pa-respons');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $dir.'/'.$namaFixture,
            json_encode($nyata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    protected function samakanBentuk(string $namaFixture, array $nyata): void
    {
        $this->rekamBilaDiminta($namaFixture, $nyata);

        $jalur = base_path(self::DIR_FIXTURE.'/'.$namaFixture);

        $this->assertFileExists($jalur, "Fixture {$namaFixture} tidak ada.");

        $fixture = json_decode((string) file_get_contents($jalur), true);

        $this->assertIsArray($fixture, "Fixture {$namaFixture} tidak terbaca.");

        $diharapkan = $this->bentuk($fixture);
        $didapat = $this->bentuk($nyata);

        $hilang = array_values(array_diff($diharapkan, $didapat));
        $tambahan = array_values(array_diff($didapat, $diharapkan));

        $this->assertSame([], $hilang, sprintf(
            'Fixture %s menjanjikan kunci yang TIDAK ADA di respons sungguhan: %s. '
            .'Frontend sedang membangun layar atas bentuk yang sudah tidak berlaku.',
            $namaFixture, implode(', ', $hilang)
        ));

        $this->assertSame([], $tambahan, sprintf(
            'Respons sungguhan memuat kunci yang TIDAK ADA di fixture %s: %s. '
            .'Perbarui fixture dan kontraknya, jangan biarkan frontend menemukannya sendiri.',
            $namaFixture, implode(', ', $tambahan)
        ));
    }

    /**
     * Menurunkan struktur menjadi daftar alamat kunci.
     *
     * @return list<string>
     */
    protected function bentuk(mixed $nilai, string $alamat = ''): array
    {
        if (! is_array($nilai)) {
            return [$alamat];
        }

        if ($nilai === []) {
            /* Daftar kosong tidak mengatakan apa-apa tentang bentuk isinya. Ditandai
               supaya tidak diam-diam dianggap cocok dengan daftar berisi — kalau tidak,
               fixture yang dipanen saat datanya kosong akan lolos selamanya tanpa pernah
               membandingkan apa pun. */
            return [$alamat.'[]'];
        }

        if (array_is_list($nilai)) {
            return $this->bentuk($nilai[0], $alamat.'[]');
        }

        if (in_array(ltrim($alamat, '.'), $this->petaDinamis(), true)) {
            return $this->bentuk(reset($nilai), $alamat.'.*');
        }

        $hasil = [];

        foreach ($nilai as $kunci => $isi) {
            $hasil = array_merge(
                $hasil,
                $this->bentuk($isi, $alamat === '' ? (string) $kunci : $alamat.'.'.$kunci),
            );
        }

        sort($hasil);

        return $hasil;
    }
}
