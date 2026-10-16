<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopRepositoryReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rail "Kelompokkan per" di Public Document.
 *
 * Halaman ini memotong daftarnya per halaman DI SERVER, sedangkan Functional
 * Document memuat semuanya lalu mengelompokkan di memori. Keduanya memberi
 * makan komponen daftar yang sama, dan yang dijaga di sini adalah satu-satunya
 * hal yang bisa membuat perbedaan itu terlihat oleh pemakai: angka di rail
 * harus dihitung atas SELURUH himpunan tersaring, bukan atas dua belas baris
 * yang kebetulan termuat.
 *
 * Angka rail yang salah lebih buruk daripada rail tanpa angka: ia mengundang
 * orang mengklik kelompok berisi 30 dokumen yang tertulis "4", lalu membaca
 * daftar yang muncul sebagai kerusakan.
 */
class SopRepositoryRekapSumbuTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopRepositoryReadService
    {
        return app(SopRepositoryReadService::class);
    }

    private function kode(): string
    {
        $kode = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Flag_Aktif', 'Y')->whereNotNull('Kode_Karyawan')
            ->value('Kode_Karyawan');

        if (! $kode) {
            $this->markTestSkipped('Tak ada penugasan SOP di database ini.');
        }

        return (string) $kode;
    }

    private function jumlahPaketPublik(): int
    {
        return DB::table('N_SOP_Master_Paket')
            ->where('Status', 'PUBLISHED')->where('Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->where('Mode_Distribusi', 'PUBLIK')->orWhereNull('Mode_Distribusi'))
            ->count();
    }

    /**
     * PENJAGA UTAMA. Rekap dihitung atas seluruh himpunan, bukan atas halaman.
     *
     * Diminta dengan limit=1 justru supaya bedanya bisa terlihat: kalau suatu
     * saat rekap ikut dipotong paginasi, `tugas` akan ikut turun jadi 1 dan uji
     * ini memerah. Tanpa limit yang sengaja kecil, penjaga ini tak pernah bisa
     * membedakan yang benar dari yang salah.
     */
    public function test_rekap_dihitung_atas_seluruh_himpunan_bukan_atas_halaman(): void
    {
        $total = $this->jumlahPaketPublik();
        if ($total < 2) {
            $this->markTestSkipped('Butuh minimal dua paket publik agar beda halaman vs himpunan terlihat.');
        }

        $kode = $this->kode();
        $halaman = $this->svc()->daftarPaket($kode, [], 1, 1);
        $rekap = $this->svc()->rekapSumbu($kode, [], 'departemen');

        $this->assertCount(1, $halaman['rows'], 'Paginasi tak memotong — premis uji ini gugur.');
        $this->assertSame($total, $halaman['total']);
        $this->assertSame($total, $rekap['tugas'], 'Rail menghitung halaman, bukan himpunan.');
    }

    /**
     * Kelompok TIDAK menjumlah ke jumlah paket, dan itu memang benar.
     *
     * Kepemilikan melekat pada DOKUMEN sementara yang dihitung PAKET, jadi satu
     * paket yang memuat dokumen tiga departemen muncul di tiga kelompok.
     * `penempatan` ada supaya layar bisa menyebut selisih itu alih-alih
     * menyembunyikannya; menjumlahkan kelompok lalu menyebutnya "total" akan
     * menghitung paket yang sama berkali-kali.
     */
    public function test_penempatan_bukan_jumlah_paket_dan_kelompok_menjumlah_ke_penempatan(): void
    {
        $rekap = $this->svc()->rekapSumbu($this->kode(), [], 'departemen');

        if ($rekap['tugas'] === 0) {
            $this->markTestSkipped('Tak ada paket publik — uji ini tak menguji apa pun.');
        }

        $jumlahGrup = array_sum(array_column($rekap['grup'], 'total'));

        $this->assertSame($rekap['penempatan'], $jumlahGrup,
            'Kelompok harus menjumlah ke penempatan, bukan ke angka lain.');
        $this->assertGreaterThanOrEqual($rekap['tugas'], $rekap['penempatan'],
            'Penempatan tak mungkin lebih sedikit daripada paketnya.');
    }

    /**
     * Angka di rail harus sama dengan isi yang muncul setelah kelompok diklik.
     *
     * Ini gabungan dua jalur yang ditulis terpisah — rekap lewat
     * SopIdentitasDokumenService, penyaringan lewat whereExists di SQL. Dua
     * definisi "termasuk kelompok X" yang melenceng tak akan melempar galat
     * apa pun; gejalanya hanya angka yang tak cocok dengan daftarnya.
     */
    public function test_angka_kelompok_sama_dengan_isi_daftarnya(): void
    {
        $kode = $this->kode();

        foreach (['departemen', 'jenis', 'apqc'] as $sumbu) {
            $rekap = $this->svc()->rekapSumbu($kode, [], $sumbu);
            if ($rekap['grup'] === []) {
                continue;
            }

            foreach ($rekap['grup'] as $g) {
                $isi = $this->svc()->daftarPaket(
                    $kode,
                    ['sumbu' => $sumbu, 'grup' => $g['nama']],
                    1,
                    100,
                );

                $this->assertSame(
                    $g['total'],
                    $isi['total'],
                    "Sumbu {$sumbu}, kelompok \"{$g['nama']}\": rail menulis {$g['total']}, daftarnya berisi {$isi['total']}.",
                );
            }
        }
    }

    /**
     * Paket TANPA dokumen sama sekali tetap harus bisa ditemukan.
     *
     * Paket yang isinya kuis dan tautan saja tak punya departemen mana pun.
     * Membiarkannya tanpa kelompok membuatnya terlihat di "Semua" lalu lenyap
     * tanpa pesan begitu kelompok apa pun dipilih — kehilangan yang tak
     * meninggalkan jejak untuk ditelusuri.
     */
    public function test_paket_tanpa_dokumen_masuk_kelompok_tanpa_pemilik(): void
    {
        $kode = $this->kode();
        $rekap = $this->svc()->rekapSumbu($kode, [], 'departemen');
        $semua = array_sum(array_column($rekap['grup'], 'total'));

        $this->assertGreaterThanOrEqual($rekap['tugas'], $semua,
            'Ada paket yang tak masuk kelompok mana pun — ia akan lenyap dari rail.');
    }

    /** Lensa rak menyaring rekap dan daftar dengan himpunan yang sama. */
    public function test_lensa_menyaring_rail_dan_daftar_bersamaan(): void
    {
        $kode = $this->kode();

        foreach (['semua', 'baru', 'favorit'] as $lensa) {
            $daftar = $this->svc()->daftarPaket($kode, ['lensa' => $lensa], 1, 100);
            $rekap = $this->svc()->rekapSumbu($kode, ['lensa' => $lensa], 'departemen');

            $this->assertSame($daftar['total'], $rekap['tugas'],
                "Lensa \"{$lensa}\": rail dan daftar menghitung himpunan yang berbeda.");
        }
    }

    /**
     * Sumbu yang sedang dipakai tidak menyaring dirinya sendiri.
     *
     * Kalau ikut, memilih "HSE" membuat rail menyusut jadi satu baris "HSE" dan
     * kelompok lain lenyap — orang kehilangan cara berpindah kelompok tanpa
     * mundur lebih dulu, tanpa satu pun petunjuk bahwa itu yang terjadi.
     */
    public function test_memilih_kelompok_tidak_meruntuhkan_rail(): void
    {
        $kode = $this->kode();
        $polos = $this->svc()->rekapSumbu($kode, [], 'departemen');

        if (count($polos['grup']) < 2) {
            $this->markTestSkipped('Butuh minimal dua kelompok agar runtuhnya rail bisa terlihat.');
        }

        $terpilih = $this->svc()->rekapSumbu(
            $kode,
            ['sumbu' => 'departemen', 'grup' => $polos['grup'][0]['nama']],
            'departemen',
        );

        $this->assertCount(count($polos['grup']), $terpilih['grup'],
            'Rail menyusut saat satu kelompok dipilih.');
    }

    /**
     * Agregat kartu metrik sepakat dengan predikat yang menyaring daftarnya.
     *
     * "Baru" ditulis dua kali: sebagai builder di predikatBaru() yang menyaring
     * daftar, dan sebagai SQL mentah di hitungRak() yang mengisi angka di kepala
     * halaman — digabung ke satu agregat demi anggaran query. Dua definisi yang
     * melenceng tak melempar galat apa pun; gejalanya hanya kartu bertulis "7"
     * yang membuka daftar berisi 5.
     */
    public function test_hitungan_baru_sepakat_dengan_predikatnya(): void
    {
        $kode = $this->kode();
        $beranda = $this->svc()->beranda($kode);
        $lewatDaftar = $this->svc()->daftarPaket($kode, ['lensa' => 'baru'], 1, 1);

        $this->assertSame($beranda['jumlah_baru'], $lewatDaftar['total'],
            'Angka "Baru & Diperbarui" di kepala halaman tak sama dengan isi lensanya.');

        $lewatFavorit = $this->svc()->daftarPaket($kode, ['lensa' => 'favorit'], 1, 1);
        $this->assertSame($beranda['jumlah_favorit'], $lewatFavorit['total'],
            'Angka "Rak Saya" tak sama dengan isi lensanya.');

        $this->assertSame($this->jumlahPaketPublik(), $beranda['total']);
    }

    /** Kartu paket membawa identitas yang dibutuhkan rail dan kartu bersama. */
    public function test_kartu_membawa_bentuk_yang_sama_dengan_functional_document(): void
    {
        $rows = $this->svc()->daftarPaket($this->kode(), [], 1, 12)['rows'];

        if ($rows === []) {
            $this->markTestSkipped('Tak ada paket publik — uji ini tak menguji apa pun.');
        }

        foreach ([
            'Id_SOP_Paket', 'Nama_Paket', 'Prioritas', 'Departemen', 'Jenis_Dokumen',
            'Apqc', 'Total_Item', 'Item_Tugas', 'Item_Selesai', 'Persen', 'Cover_Url',
            'Favorit', 'Baru', 'Diperbarui', 'Item_Baru', 'Wajib', 'Isi',
        ] as $kunci) {
            $this->assertArrayHasKey($kunci, $rows[0], "Kartu pustaka kehilangan medan {$kunci}.");
        }

        /* Id mentah tak boleh ikut menyeberang: ia membocorkan jumlah & urutan
           baris tabel, dan mengundang klien menebak-nebak Id lain. */
        $this->assertArrayNotHasKey('_id', $rows[0]);
    }

    /**
     * `Total_Item` dan `Item_Tugas` menjawab dua pertanyaan berbeda.
     *
     * Yang pertama isi paket, yang kedua berapa di antaranya yang ditugaskan
     * kepada orang ini. Menukarnya membuat penjelajah biasa diberitahu bahwa ia
     * "0 dari 5 selesai" atas pekerjaan yang tak pernah dibebankan kepadanya.
     */
    public function test_isi_paket_dan_item_tugas_tidak_tertukar(): void
    {
        $rows = $this->svc()->daftarPaket($this->kode(), [], 1, 12)['rows'];

        if ($rows === []) {
            $this->markTestSkipped('Tak ada paket publik — uji ini tak menguji apa pun.');
        }

        foreach ($rows as $r) {
            $this->assertSame(array_sum($r['Isi']), $r['Total_Item'],
                "Total_Item paket {$r['Nama_Paket']} tak sama dengan jumlah isinya.");
            $this->assertLessThanOrEqual($r['Item_Tugas'], $r['Item_Selesai'],
                "Item selesai melebihi item yang ditugaskan pada {$r['Nama_Paket']}.");
        }
    }
}
