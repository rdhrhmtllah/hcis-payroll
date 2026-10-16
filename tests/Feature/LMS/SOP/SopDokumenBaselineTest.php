<?php

namespace Tests\Feature\LMS\SOP;

use App\Console\Commands\LMS\SOP\Operations\SopDokumenBaseline;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopDokumenBaselineTest extends TestCase
{
    use DatabaseTransactions;

    private string $berkas;

    protected function setUp(): void
    {
        parent::setUp();
        // Symfony Console's StringInput (used by $this->artisan() when given a
        // single command string) treats backslash as a shell-style escape
        // character, mangling native Windows paths from storage_path(). Forward
        // slashes are accepted by the Windows filesystem APIs and survive
        // StringInput's tokenizer intact, so normalize before interpolating.
        $this->berkas = str_replace('\\', '/', storage_path('app/test-baseline-dokumen.json'));
        @unlink($this->berkas);
    }

    protected function tearDown(): void
    {
        @unlink($this->berkas);
        parent::tearDown();
    }

    public function test_menyimpan_sidik_jari_untuk_setiap_paket(): void
    {
        $jumlahPaket = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', 'Y')->count();
        if ($jumlahPaket === 0) {
            $this->markTestSkipped('Tak ada paket aktif di data dev.');
        }

        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();

        $this->assertFileExists($this->berkas);
        $isi = json_decode(file_get_contents($this->berkas), true);
        $this->assertCount($jumlahPaket, $isi['paket'] ?? [], 'Baseline harus mencakup semua paket aktif.');
    }

    public function test_membandingkan_dengan_dirinya_sendiri_selalu_identik(): void
    {
        if (DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', 'Y')->count() === 0) {
            $this->markTestSkipped('Tak ada paket aktif di data dev.');
        }

        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(0);
    }

    public function test_mendeteksi_perbedaan(): void
    {
        if (DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', 'Y')->count() === 0) {
            $this->markTestSkipped('Tak ada paket aktif di data dev.');
        }

        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();

        // Rusak sidik jarinya — pembanding wajib menyadarinya.
        $isi = json_decode(file_get_contents($this->berkas), true);
        $kunciPertama = array_key_first($isi['paket']);
        $isi['paket'][$kunciPertama]['sidik'] = 'sengaja-diubah';
        file_put_contents($this->berkas, json_encode($isi));

        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(1);
    }

    /**
     * Dua tes di bawah menguji hal yang tak diuji test_mendeteksi_perbedaan:
     * bahwa sidik jarinya benar-benar TERIKAT PADA DATA. Mengedit string JSON
     * dengan tangan hanya membuktikan pembandingnya bekerja — ia akan lulus
     * persis sama seandainya ambil() mengembalikan null untuk setiap paket.
     */
    public function test_sidik_jari_bereaksi_pada_perubahan_data_paket(): void
    {
        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $isi = json_decode(file_get_contents($this->berkas), true);

        /*
         * Populasinya WAJIB via_probe, bukan sembarang "paket ber-bundle non-null".
         * Paket via_probe tak punya penugasan aktif (lihat ambil() di
         * SopDokumenBaseline.php), jadi pemanggilnya adalah $kodeProbe — yang GAGAL
         * paketBolehDiakses() di paket-paket ini (semuanya DRAFT di data dev) —
         * sehingga bypassGate DIPAKSA true. Itu mengunci dua jalur sekaligus:
         *   1) detailBundle() menempuh cabang TABEL AKTIF (SopProgressReadService.php
         *      ~256-271), bukan releasedSnapshot() — jadi Judul_Item yang kita ubah di
         *      bawah PROVABLY sampai ke bundle, bukan dibaca dari JSON snapshot beku
         *      (yang, bila ada, akan membuat sidik per-paket fisik tak bisa bereaksi).
         *   2) baseVersiAktif() (SopDocumentControlService.php ~176) mensyaratkan
         *      p.Status='PUBLISHED', jadi paket DRAFT via_probe TAK PERNAH masuk
         *      register — kontrol_register tak bisa ikut bergerak dan menyamar
         *      sebagai bukti. assertExitCode(1) di bawah karena itu HANYA bisa
         *      berasal dari sidik per-paket, persis klaim tes ini.
         * JANGAN kembalikan ke "paket ber-bundle non-null": populasi itu bisa jatuh
         * ke paket PUBLISHED berpenugasan (bypassGate:false), yang membaca Judul_Item
         * dari snapshot rilis bila ada — sidik per-paket takkan pernah bergerak, dan
         * exit code 1 bisa datang diam-diam dari kontrol_register (lihat kembarannya:
         * test_sidik_jari_bereaksi_pada_perubahan_register_kontrol_dokumen).
         */
        $viaProbe = $isi['via_probe'] ?? [];
        if (! $viaProbe) {
            $this->markTestSkipped('via_probe kosong — tak ada paket tanpa penugasan untuk diuji.');
        }

        $item = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket', $viaProbe)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_SOP_Paket_Item')
            ->first(['Id_SOP_Paket_Item', 'Judul_Item']);
        if (! $item) {
            $this->markTestSkipped('Tak ada paket via_probe yang punya item aktif.');
        }

        // Di-rollback otomatis oleh DatabaseTransactions.
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->update(['Judul_Item' => 'JUDUL UJI SIDIK JARI '.uniqid()]);

        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(1);
    }

    public function test_sidik_jari_bereaksi_pada_perubahan_register_kontrol_dokumen(): void
    {
        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $isi = json_decode(file_get_contents($this->berkas), true);

        if (($isi['kontrol_register']['jumlah'] ?? 0) === 0) {
            $this->markTestSkipped('Register kontrol dokumen kosong di data dev.');
        }

        // Versi AKTIF (i.Id_Item_Versi_Aktif) — hanya itu yang masuk register.
        $versi = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', function ($j) {
                $j->on('i.Id_SOP_Paket_Item', '=', 'v.Id_SOP_Paket_Item')
                    ->on('i.Id_Item_Versi_Aktif', '=', 'v.Id_SOP_Item_Versi');
            })
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Flag_Aktif', 'Y')->where('p.Flag_Aktif', 'Y')->where('p.Status', 'PUBLISHED')
            ->orderBy('v.Id_SOP_Item_Versi')
            ->first(['v.Id_SOP_Item_Versi']);
        if (! $versi) {
            $this->markTestSkipped('Tak ada versi aktif terbit di data dev.');
        }

        /* Diusik lewat Disahkan_Oleh, bukan Tanggal_Kadaluarsa. Sejak masa
           berlaku dicabut (15 Agu 2026) register tak lagi membaca tanggal itu,
           jadi mengusiknya membuat sidik jari DIAM — dan uji ini akan lulus
           tanpa pernah membuktikan sidik jarinya peka. Pengesah dibaca register
           sebagai kolom sekaligus sebagai ember TANPA_PENGESAHAN. */
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->update(['Disahkan_Oleh' => 'UJI SIDIK JARI '.uniqid()]);

        $this->artisan("sop:dokumen-baseline --bandingkan={$this->berkas}")->assertExitCode(1);
    }

    /**
     * Export rinci SENGAJA ditunda dari Fase 1: ia menjoin lewat
     * i.Id_Item_Versi_Aktif, dan Fase 1 tak menyentuh pointer itu sama sekali,
     * jadi ia mustahil bergeser di sana. Fase 2 memindahkan pembacaan versi —
     * persis yang membuatnya bisa bergeser. Baseline pra-perubahan hanya bisa
     * diambil SEBELUM perubahan; menambahkannya setelah kode bergerak berarti
     * membakukan hasil yang sudah berubah sebagai "sebelum", dan bukti nol
     * regresinya jadi kosong.
     */
    public function test_baseline_mencakup_export_rinci(): void
    {
        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();

        $isi = json_decode(file_get_contents($this->berkas), true);

        $this->assertArrayHasKey('export_rinci', $isi,
            'Export rinci adalah jalur baca yang justru dipindahkan Fase 2. '
            .'Tanpa ia di baseline, bukti nol-regresinya kosong.');
        $this->assertArrayHasKey('sidik', $isi['export_rinci']);
        $this->assertNotEmpty($isi['export_rinci']['sidik']);
        $this->assertArrayHasKey('jumlah', $isi['export_rinci']);
    }

    /**
     * Sidik export rinci harus stabil dalam satu hari yang sama. Kalau tidak,
     * ia akan melaporkan "BERUBAH" pada setiap perbandingan dan gerbang nol
     * regresi Fase 2 jadi kebisingan yang diabaikan.
     */
    public function test_sidik_export_rinci_deterministik(): void
    {
        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $satu = json_decode(file_get_contents($this->berkas), true)['export_rinci']['sidik'] ?? null;

        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $dua = json_decode(file_get_contents($this->berkas), true)['export_rinci']['sidik'] ?? null;

        $this->assertNotNull($satu);
        $this->assertSame($satu, $dua, 'Sidik export rinci tak boleh berubah tanpa data berubah.');
    }

    /**
     * Sidik yang cuma menutupi halaman pertama adalah bukti palsu.
     *
     * Percobaan pertama memakai limit tetap 500 pada 916 baris di dev: 45%
     * keluaran tak terlindungi, dan regresi di sana akan dilaporkan
     * "Identik. Nol regresi." Test ini menahan agar plafon tak diam-diam
     * kembali di bawah jumlah baris sebenarnya.
     */
    public function test_sidik_export_rinci_menutupi_seluru_h_baris_bukan_satu_halaman(): void
    {
        $this->artisan("sop:dokumen-baseline --simpan={$this->berkas}")->assertSuccessful();
        $e = json_decode(file_get_contents($this->berkas), true)['export_rinci'];

        $this->assertArrayHasKey('tercakup', $e);
        $this->assertSame($e['jumlah'], $e['tercakup'],
            "Sidik export rinci hanya menutupi {$e['tercakup']} dari {$e['jumlah']} baris. "
            .'Bukti nol regresi yang bolong lebih berbahaya daripada tidak ada bukti, '
            .'karena ia melaporkan "Identik" untuk bagian yang tak pernah diperiksa.');
    }
}
