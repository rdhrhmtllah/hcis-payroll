<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga rantai status siklus: setiap status yang dipakai HARUS punya PENULIS.
 *
 * KENAPA PENJAGA INI ADA
 *
 * Pada 2 September 2026, `php artisan pa:audit` melaporkan 115/115 (100%) sementara modul
 * ini **tidak bisa dijalani sampai selesai**. `OPEN` dan `CLOSED` tidak punya satu pun
 * penulis di kode produksi — hanya perintah data contoh yang menulisnya — sehingga siklus
 * sungguhan mentok di `LOCKED` selamanya, dan empat operasi inti yang bergerbang pada
 * kedua status itu (terima payload, hitung, kalibrasi, terbitkan) tak pernah terjangkau.
 *
 * Audit lama buta terhadapnya karena ia mencari **operasi gerbang tanpa pemakai**,
 * sedangkan yang terjadi adalah **status gerbang tanpa penulis** — satu tingkat di atas
 * yang dicarinya. Uji ini menutup tingkat itu.
 *
 * BACA "PENULIS" DENGAN KETAT
 *
 * Yang dihitung hanya penulisan sungguhan `'Status_Cycle' => …` di dalam `app/`, dan
 * `PaDemoCycleCommand` SENGAJA TIDAK DIHITUNG. Ia hanya bekerja pada siklus ber-kode
 * `PA-DEMO-FE` dan komentarnya sendiri menyatakan bahwa perjalanan status yang sebenarnya
 * "milik aplikasi". Menghitungnya sebagai penulis akan membuat uji ini hijau justru pada
 * keadaan yang membuatnya dibuat.
 */
class RantaiStatusTest extends TestCase
{
    use DatabaseTransactions;

    private const DIR = 'app';

    /**
     * Berkas yang menulis status TETAPI tidak boleh dihitung sebagai jalur produksi.
     *
     * Jalur relatif terhadap akar proyek, memakai garis miring maju.
     */
    private const BUKAN_JALUR_PRODUKSI = [
        'app/Console/Commands/KPI/PerformanceAppraisal/PaDemoCycleCommand.php',
    ];

    /**
     * Status yang memang BELUM punya penulis, dan itu disengaja — untuk sekarang.
     *
     * Menambah baris ke sini BOLEH, tetapi harus terlihat di diff dan menyebutkan
     * alasannya. Yang dilarang adalah status yang tak punya penulis tanpa ada yang tahu.
     *
     * @var array<string, string>
     */
    private const BELUM_ADA_PENULIS = [
        /* Arsip. Tanpa penulis, siklus tidak pernah berpindah ke arsip dan daftar siklus
           aktif tumbuh selamanya — tetapi tidak ada yang rusak hari ini, dan mengarsipkan
           adalah keputusan produk (siklus arsip hilang dari daftar aktif dan menjadi
           hanya-baca), bukan sekadar satu perpindahan status lagi.

           Dicatat di sini supaya terlihat, bukan supaya dilupakan. */
        'ARCHIVED' => 'Belum diputuskan: kapan siklus diarsipkan dan apa akibatnya di layar.',
    ];

    public function test_setiap_status_siklus_punya_penulis_di_jalur_produksi(): void
    {
        $status = DB::table('N_PA_Cycle_Status')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Alur')
            ->pluck('Kode_Status')
            ->map(fn ($k) => (string) $k)
            ->all();

        $this->assertNotEmpty($status, 'Master status siklus kosong; uji ini tidak menguji apa pun.');

        $penulis = $this->statusYangDitulis();

        // PENJAGA KERJA-NYATA. Kalau pemindaiannya tidak menemukan penulis sama sekali,
        // seluruh uji di bawah akan gagal untuk alasan yang salah — atau, kalau daftarnya
        // dibalik, lulus tanpa memeriksa apa pun.
        $this->assertNotEmpty(
            $penulis,
            'Pemindaian tidak menemukan satu penulis status pun. Pola pencariannya rusak, '
            .'bukan kodenya.'
        );

        $tanpaPenulis = [];

        foreach ($status as $kode) {
            if (isset($penulis[$kode]) || array_key_exists($kode, self::BELUM_ADA_PENULIS)) {
                continue;
            }

            $tanpaPenulis[] = $kode;
        }

        $this->assertSame(
            [],
            $tanpaPenulis,
            "Status siklus berikut tidak punya satu pun penulis di jalur produksi:\n  "
            .implode(', ', $tanpaPenulis)."\n\n"
            .'Status tanpa penulis adalah cabang mati yang TIDAK TERLIHAT dari mana pun: '
            .'gerbang yang bergantung padanya tak pernah terbuka, dan auditnya tetap hijau '
            .'karena semua lapisan fiturnya lengkap. Ini persis yang terjadi pada OPEN dan '
            ."CLOSED sampai 2 September 2026.\n\n"
            .'Kalau memang belum waktunya, tambahkan ke BELUM_ADA_PENULIS beserta alasannya '
            .'supaya keputusannya terlihat di diff.'
        );
    }

    public function test_status_yang_dipakai_gerbang_semuanya_dikenal_master(): void
    {
        $master = DB::table('N_PA_Cycle_Status')
            ->pluck('Kode_Status')
            ->map(fn ($k) => (string) $k)
            ->all();

        $gate = new PaCycleGate;
        $dipakai = [];

        foreach ($master as $kode) {
            foreach ($gate->operasiUntukStatus($kode) as $op) {
                $dipakai[$op][] = $kode;
            }
        }

        $this->assertNotEmpty($dipakai, 'Gerbang tidak mengizinkan operasi apa pun; pemindaian rusak.');

        /* Setiap operasi harus punya SETIDAKNYA SATU status tempat ia diizinkan.
           Operasi yang tidak diizinkan di status mana pun adalah gerbang yang selamanya
           tertutup — dan pemanggilnya akan selalu ditolak dengan pesan yang terbaca
           seperti kesalahan pemakai, padahal aturannya yang mustahil dipenuhi. */
        $mustahil = [];

        foreach ($this->operasiGerbang() as $op) {
            if (! isset($dipakai[$op])) {
                $mustahil[] = $op;
            }
        }

        $this->assertSame(
            [],
            $mustahil,
            'Operasi berikut tidak diizinkan pada status mana pun, sehingga mustahil '
            .'dijalankan: '.implode(', ', $mustahil)
        );
    }

    public function test_rantai_status_bisa_ditempuh_dari_draft_sampai_published(): void
    {
        /* Bukan memeriksa keberadaan method, melainkan bahwa RANTAINYA nyambung: tiap
           status yang punya penulis harus bisa dicapai dari status sebelumnya menurut
           izin gerbang.

           Ini yang membedakan uji ini dari `pa:audit`. Audit memeriksa tiap fitur berdiri
           lengkap; uji ini memeriksa sambungan di antaranya. */
        $gate = new PaCycleGate;

        $rantai = [
            'PREPARED' => [PaCycleGate::KUNCI_SIKLUS, 'LOCKED'],
            'LOCKED' => [PaCycleGate::BUKA_PENGISIAN, 'OPEN'],
            'OPEN' => [PaCycleGate::TUTUP_PENGISIAN, 'CLOSED'],
            'CLOSED' => [PaCycleGate::TERBITKAN, 'PUBLISHED'],
        ];

        foreach ($rantai as $dari => [$operasi, $ke]) {
            $this->assertContains(
                $operasi,
                $gate->operasiUntukStatus($dari),
                "Dari status {$dari} tidak ada jalan menuju {$ke}: operasi {$operasi} "
                .'tidak diizinkan di sana, sehingga rantainya putus di titik ini.'
            );
        }

        /* Dan satu jalan KEMBALI yang disengaja: menutup pengisian terlalu cepat adalah
           kekeliruan yang wajar, jadi CLOSED harus bisa dibuka lagi. Tanpa ini,
           perbaikannya pindah ke SQL manual — dan di sanalah kerusakan yang sesungguhnya
           terjadi. */
        $this->assertContains(
            PaCycleGate::BUKA_PENGISIAN,
            $gate->operasiUntukStatus('CLOSED'),
            'Siklus yang sudah ditutup harus bisa dibuka lagi selama belum terbit.'
        );

        // PUBLISHED adalah ujungnya: tidak ada operasi yang masih diizinkan di sana.
        $this->assertSame(
            [],
            $gate->operasiUntukStatus('PUBLISHED'),
            'Hasil yang sudah sampai ke karyawan tidak boleh berubah lagi.'
        );
    }

    /* ------------------------------------------------------------------ *
     * Pemindaian                                                          *
     * ------------------------------------------------------------------ */

    /**
     * Status yang benar-benar DITULIS oleh kode produksi, beserta berkasnya.
     *
     * @return array<string, list<string>>
     */
    private function statusYangDitulis(): array
    {
        $hasil = [];

        foreach ($this->berkasPhp(base_path(self::DIR)) as $berkas) {
            $relatif = str_replace('\\', '/', substr($berkas, strlen(base_path()) + 1));

            if (in_array($relatif, self::BUKAN_JALUR_PRODUKSI, true)) {
                continue;
            }

            $isi = (string) file_get_contents($berkas);

            if (! str_contains($isi, 'Status_Cycle')) {
                continue;
            }

            foreach ($this->statusDalamBerkas($isi) as $kode) {
                $hasil[$kode][] = $relatif;
            }
        }

        return $hasil;
    }

    /**
     * Status yang ditulis satu berkas — literal maupun lewat konstanta di berkas itu.
     *
     * Sengaja TIDAK memungut setiap kata yang kebetulan berbunyi seperti status: daftar
     * izin di `PaCycleGate` menyebut kelima status sebagai IZIN, bukan sebagai penulisan,
     * dan memungutnya akan membuat setiap status tampak punya penulis — uji ini lalu
     * hijau selamanya tanpa menjaga apa pun.
     *
     * @return list<string>
     */
    private function statusDalamBerkas(string $isi): array
    {
        $kode = [];

        // Bentuk langsung: 'Status_Cycle' => 'LOCKED'
        preg_match_all(
            "/'Status_Cycle'\s*=>\s*'([A-Z_]+)'/",
            $isi,
            $langsung
        );
        $kode = array_merge($kode, $langsung[1]);

        // Bentuk lewat konstanta: 'Status_Cycle' => self::SIKLUS_PUBLISHED
        preg_match_all(
            "/'Status_Cycle'\s*=>\s*self::([A-Z_]+)/",
            $isi,
            $lewatKonstanta
        );

        foreach ($lewatKonstanta[1] as $nama) {
            if (preg_match("/const\s+{$nama}\s*=\s*'([A-Z_]+)'/", $isi, $nilai) === 1) {
                $kode[] = $nilai[1];
            }
        }

        return array_values(array_unique($kode));
    }

    /** @return list<string> */
    private function operasiGerbang(): array
    {
        $refleksi = new \ReflectionClass(PaCycleGate::class);
        $operasi = [];

        foreach ($refleksi->getConstants() as $nama => $nilai) {
            // Hanya konstanta operasi: bernilai string dan namanya sama dengan nilainya.
            if (is_string($nilai) && $nama === $nilai) {
                $operasi[] = $nilai;
            }
        }

        return $operasi;
    }

    /** @return list<string> */
    private function berkasPhp(string $akar): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($akar, \FilesystemIterator::SKIP_DOTS)
        );

        $berkas = [];

        foreach ($iterator as $item) {
            if ($item->isFile() && $item->getExtension() === 'php') {
                $berkas[] = $item->getPathname();
            }
        }

        return $berkas;
    }
}
