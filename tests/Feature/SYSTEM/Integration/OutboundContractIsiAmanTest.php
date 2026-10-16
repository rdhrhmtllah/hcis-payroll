<?php

namespace Tests\Feature\SYSTEM\Integration;

use App\Services\SYSTEM\Integration\OutboundContractService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Keamanan ISI kontrak integrasi outbound.
 *
 * Berkas uji ini berangkat dari satu kenyataan: spesifikasi kontrak disajikan
 * di /api/contracts/{slug} yang SENGAJA publik — tanpa login, tanpa Api_Public —
 * karena tautannya memang dibagikan ke developer vendor. Keputusan itu benar
 * untuk tujuannya, tetapi konsekuensinya adalah setiap nilai contoh yang ditulis
 * di dalam kontrak ikut terbit ke internet.
 *
 * Sampai sekarang penjagaannya hanya berupa peringatan di layar portal, dan
 * peringatan tidak menahan apa pun. Yang di bawah ini menahan.
 *
 * Yang TIDAK dilarang, supaya jelas: menyebut NAMA variabel lingkungan tempat
 * rahasia disimpan (mis. `secret_env_name` => 'HC_LEARN_SHARED_SECRET') justru
 * praktik yang benar — ia memberi tahu vendor di mana kuncinya tanpa memuat
 * kuncinya. Contoh tanda tangan HMAC juga bukan rahasia; tanda tangan memang
 * dikirim polos di setiap permintaan.
 */
class OutboundContractIsiAmanTest extends TestCase
{
    /** Domain yang dicadangkan untuk dokumentasi (RFC 2606) — dijamin bukan kotak surat sungguhan. */
    private const DOMAIN_DOKUMENTASI = ['example.com', 'example.net', 'example.org'];

    /**
     * Seluruh nilai string di dalam kontrak, beserta jejak kuncinya.
     *
     * @return array<string, string> jalur => nilai
     */
    private function nilaiKontrak(): array
    {
        $hasil = [];

        $telusuri = function (array $simpul, string $jalur) use (&$telusuri, &$hasil): void {
            foreach ($simpul as $kunci => $nilai) {
                $jalurIni = $jalur === '' ? (string) $kunci : "{$jalur}.{$kunci}";

                if (is_array($nilai)) {
                    $telusuri($nilai, $jalurIni);
                } elseif (is_string($nilai)) {
                    $hasil[$jalurIni] = $nilai;
                }
            }
        };

        $telusuri(app(OutboundContractService::class)->all(), '');

        return $hasil;
    }

    /** @test */
    public function alamat_email_contoh_wajib_memakai_domain_dokumentasi(): void
    {
        // Domain sungguhan berarti kotak surat sungguhan: contoh yang salah
        // ketik bisa mengirimi orang asing data uji, dan alamat yang terbit ke
        // halaman publik ikut dipanen pengirim spam.
        foreach ($this->nilaiKontrak() as $jalur => $nilai) {
            if (preg_match_all('/[\w.+-]+@([\w-]+\.[\w.-]+)/i', $nilai, $cocok) === 0) {
                continue;
            }

            foreach ($cocok[1] as $domain) {
                $this->assertContains(
                    strtolower($domain),
                    self::DOMAIN_DOKUMENTASI,
                    "Nilai '{$jalur}' memuat email berdomain '{$domain}'. Halaman kontrak publik; "
                    .'pakai domain dokumentasi RFC 2606 (example.com/.net/.org).',
                );
            }
        }
    }

    /** @test */
    public function kontrak_tidak_boleh_memuat_nilai_rahasia(): void
    {
        // Dua lapis: nama kunci yang MEMUAT rahasia, dan bentuk nilai yang khas
        // milik kredensial sungguhan.
        $kunciTerlarang = '/(^|\.)(secret|api_secret|password|passwd|private_key|credential|access_token|refresh_token)$/i';

        $bentukKredensial = [
            'kunci privat PEM' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
            'AWS access key' => '/\bAKIA[0-9A-Z]{16}\b/',
            'kunci Stripe live' => '/\bsk_live_[0-9a-zA-Z]{10,}/',
            'token GitHub' => '/\bgh[pousr]_[0-9A-Za-z]{20,}/',
            'token Slack' => '/\bxox[baprs]-[0-9A-Za-z-]{10,}/',
            'JWT' => '/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\./',
        ];

        foreach ($this->nilaiKontrak() as $jalur => $nilai) {
            $this->assertDoesNotMatchRegularExpression(
                $kunciTerlarang,
                $jalur,
                "Kunci '{$jalur}' terdengar memuat rahasia. Sebutkan NAMA variabel lingkungannya "
                .'(mis. secret_env_name), jangan nilainya — halaman ini publik.',
            );

            foreach ($bentukKredensial as $nama => $pola) {
                $this->assertDoesNotMatchRegularExpression(
                    $pola,
                    $nilai,
                    "Nilai '{$jalur}' berbentuk {$nama}. Kontrak ini terbit ke internet.",
                );
            }
        }
    }

    /** @test */
    public function nilai_contoh_tidak_boleh_cocok_dengan_karyawan_sungguhan(): void
    {
        // Penjaga yang paling penting, dan satu-satunya yang tidak bisa
        // disimpulkan dari bentuk: sebuah NIK contoh terlihat persis sama
        // dengan NIK sungguhan. Yang membedakannya hanya isi tabel Karyawan.
        if (! filter_var(env('RUN_KONTRAK_PII_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_KONTRAK_PII_TEST=true untuk menjalankan (butuh DB dev).');
        }

        $nilai = $this->nilaiKontrak();
        $kandidat = array_values(array_unique(array_map('trim', $nilai)));

        /*
         | DIPOTONG, karena SQL Server hanya menerima 2.100 parameter per pernyataan dan
         | kueri ini memakai LIMA kolom sekaligus — jadi batasnya bukan 2.100 nilai contoh
         | melainkan 420.
         |
         | Uji ini sempat MERAH bukan karena menemukan kebocoran, melainkan karena kontrak
         | bertambah besar sampai melewati batas itu. Kegagalan seperti itu berbahaya
         | dengan caranya sendiri: ia muncul sebagai galat database, bukan sebagai temuan,
         | sehingga cara termudah menghijaukannya adalah mematikan ujinya — dan yang
         | dimatikan justru satu-satunya penjaga yang bisa membedakan NIK karangan dari
         | NIK sungguhan.
         |
         | 400 nilai × 5 kolom = 2.000 parameter, menyisakan ruang untuk kolom yang mungkin
         | ditambahkan kelak tanpa harus mengingat batasnya lagi.
         */
        $tabrakan = [];

        foreach (array_chunk($kandidat, 400) as $potongan) {
            $tabrakan = array_merge($tabrakan, DB::table('Karyawan')
                ->where(function ($q) use ($potongan) {
                    $q->whereIn('Kode_Karyawan', $potongan)
                        ->orWhereIn('NIK', $potongan)
                        ->orWhereIn('Nama', $potongan)
                        ->orWhereIn('HP', $potongan)
                        ->orWhereIn('Email', $potongan);
                })
                ->limit(5)
                ->get(['Kode_Karyawan'])
                ->pluck('Kode_Karyawan')
                ->all());

            // Lima sudah lebih dari cukup untuk menunjukkan ada masalah; sisanya hanya
            // memperpanjang pesan tanpa mengubah keputusan.
            if (count($tabrakan) >= 5) {
                $tabrakan = array_slice($tabrakan, 0, 5);
                break;
            }
        }

        $this->assertSame(
            [],
            $tabrakan,
            'Nilai contoh di dalam kontrak cocok dengan data karyawan sungguhan ('
            .implode(', ', $tabrakan).'). Kontrak ini terbit ke internet tanpa login — '
            .'ganti dengan nilai karangan.',
        );
    }

    /** @test */
    public function penjaga_ini_benar_benar_memeriksa_sesuatu(): void
    {
        // Kalau penelusurannya rusak, seluruh penjaga di atas lolos dengan
        // sempurna karena tidak ada yang diperiksa — hijau palsu yang paling
        // mahal justru berbentuk begini.
        $nilai = $this->nilaiKontrak();

        $this->assertGreaterThan(100, count($nilai), 'Penelusuran nilai kontrak tampaknya tidak menjangkau isinya.');
        $this->assertContains('example.com', array_map(
            fn ($v) => str_contains($v, '@') ? substr($v, strrpos($v, '@') + 1) : $v,
            $nilai,
        ), 'Tidak ada satu pun email di dalam kontrak — uji domain di atas jadi tak menguji apa pun.');
    }
}
