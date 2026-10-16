<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopAssignmentRuleService;
use App\Services\LMS\SOP\SopExportRinciService;
use App\Services\LMS\SOP\Support\SopSetting;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Kebijakan & otomasi, dan laporan — jalur normal.
 *
 * UAT-SOP-024 menyusun aturan penugasan otomatis
 * berikut pratinjau dampaknya · 025 mengaktifkan aturan, mengubah setelan, dan
 * melihat riwayat · 042 mengunduh laporan kepatuhan.
 *
 * Untuk 024 yang dijaga adalah PRATINJAU: aturan otomatis menugaskan orang tanpa
 * admin menekan apa pun lagi, jadi satu-satunya kesempatan menimbang dampaknya
 * adalah sebelum ia diaktifkan. Aturan yang tak bisa dipratinjau adalah aturan
 * yang baru diketahui akibatnya setelah orang-orang menerima tugasnya.
 */
class Alur024KebijakanOtomasiTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Aturan')->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    /**
     * Aturan disimpan sebagai DRAFT lebih dulu, lalu dipratinjau. Urutan itu yang
     * membuat pratinjau punya arti: aturan yang langsung aktif saat disimpan tak
     * punya jendela waktu untuk ditimbang.
     */
    #[Group('UAT-SOP-024')]
    public function test_aturan_penugasan_disimpan_sebagai_draft_dan_bisa_dipratinjau(): void
    {
        $d = $this->dunia();
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Otomatis'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null]],
            $d->userIdUji()
        );

        $this->assertGreaterThan(0, $ruleId, 'Aturan penugasan gagal disimpan.');

        $aturan = $svc->find($ruleId);
        $this->assertNotNull($aturan, 'Aturan yang baru disimpan tak bisa dibaca kembali.');

        $pratinjau = $svc->preview($ruleId);

        $this->assertArrayHasKey('paket', $pratinjau,
            'Pratinjau aturan tidak merinci dampak per paket; dampaknya baru diketahui '
            .'setelah orang-orang menerima tugasnya.');
        $this->assertArrayHasKey('total', $pratinjau,
            'Pratinjau tidak memberi baris TOTAL.');
    }

    /**
     * Aturan yang dinonaktifkan harus BERHENTI menugaskan. Aturan otomatis yang
     * tak bisa dimatikan adalah aturan yang harus dibersihkan akibatnya setiap
     * hari.
     */
    #[Group('UAT-SOP-025')]
    public function test_aturan_bisa_dinonaktifkan(): void
    {
        $d = $this->dunia();
        $svc = app(SopAssignmentRuleService::class);

        $ruleId = $svc->saveDraft(
            $d->nama('Aturan Nonaktif'),
            ['groups' => [['tipe' => 'Karyawan']]],
            [['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null]],
            $d->userIdUji()
        );

        $svc->deactivate($ruleId, $d->userIdUji());

        $status = DB::table('N_SOP_Assignment_Rule')
            ->where('Id_SOP_Assignment_Rule', $ruleId)
            ->first();

        $this->assertNotNull($status);
        $this->assertNotSame('ACTIVE', strtoupper((string) ($status->Status_Aturan ?? '')),
            'Aturan yang dinonaktifkan masih berstatus aktif dan akan terus menugaskan.');
        $this->assertNotNull($status->Deactivated_At,
            'Waktu penonaktifan tidak tercatat, sehingga tak ada jejak kapan aturan '
            .'berhenti bekerja.');
    }

    /**
     * Setelan modul harus bisa diubah DAN dibaca kembali dengan nilai barunya.
     * Setelan yang tersimpan tapi tak terbaca membuat admin mengubahnya berulang
     * kali tanpa efek apa pun.
     */
    #[Group('UAT-SOP-025')]
    public function test_setelan_modul_tersimpan_dan_terbaca_kembali(): void
    {
        /* Memakai kunci yang BENAR-BENAR ADA di tabel. Percobaan pertama memakai
           'penjadwalan_async_ambang' — kunci yang dibaca kode produksi tapi tak
           punya baris, sehingga SopSetting::int() selalu memulangkan bawaannya
           dan uji ini membandingkan nilai bawaan dengan nilai bawaan. Ia gagal;
           seandainya angkanya kebetulan cocok, ia akan lulus tanpa menguji
           apa pun. */
        $kunci = 'default_reminder_hari';

        $semula = DB::table('N_SOP_Setting')->where('Kode_Setting', $kunci)->value('Nilai');
        if ($semula === null) {
            $this->markTestSkipped("Setelan '{$kunci}' tidak ada di database ini.");
        }

        $baru = (int) $semula + 7;

        DB::table('N_SOP_Setting')->where('Kode_Setting', $kunci)->update(['Nilai' => (string) $baru]);
        SopSetting::flush();

        $this->assertSame($baru, SopSetting::int($kunci, -1),
            'Setelan yang baru disimpan masih terbaca dengan nilai lama — cache-nya '
            .'tidak dibersihkan, dan admin akan mengubahnya berulang tanpa efek.');

        DB::table('N_SOP_Setting')->where('Kode_Setting', $kunci)->update(['Nilai' => (string) $semula]);
        SopSetting::flush();
    }

    /**
     * Grup target tanpa satu pun kriteria berarti "seluruh karyawan aktif", dan
     * itu ditolak dengan sengaja: bentuk grup kosong persis sama dengan bentuk
     * bawaan di layar, sehingga satu klik tak sengaja bisa menugaskan SOP ke
     * seluruh headcount.
     */
    #[Group('UAT-SOP-024')]
    public function test_aturan_tanpa_kriteria_apa_pun_ditolak(): void
    {
        $d = $this->dunia();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(SopAssignmentRuleService::class)->saveDraft(
            $d->nama('Aturan Tanpa Kriteria'),
            ['groups' => [['tipe' => 'Semua']]],
            [['id_paket' => $d->idPaket, 'urutan' => 1, 'override' => false, 'schedule' => null]],
            $d->userIdUji()
        );
    }

    /**
     * UAT-SOP-042 — laporan kepatuhan.
     *
     * Yang diperiksa adalah ISI barisnya, bukan sekadar "berkasnya terbentuk".
     * Laporan audit yang jumlah barisnya benar tapi isinya milik orang lain
     * lolos dari pemeriksaan mana pun yang hanya menghitung.
     */
    #[Group('UAT-SOP-042')]
    public function test_laporan_kepatuhan_memuat_baris_untuk_karyawan_yang_ditugaskan(): void
    {
        $admin = UatAkun::admin();
        $karyawan = UatAkun::karyawanBiasa();
        $kode = $karyawan ? UatAkun::kodeKaryawan($karyawan) : null;
        if (! $admin || ! $kode) {
            $this->markTestSkipped('Akun uji admin/karyawan tak lengkap.');
        }

        try {
            $d = UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()->buatPaket()
                ->tambahItem('Item Laporan')->terbitkanPaket()->tugaskanKe($kode);
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        $svc = app(SopExportRinciService::class);
        /* Saringan 'paket' pada export menerima NAMA paket, bukan id-nya —
           terapkanSaringanPaket() mencocokkannya ke p.Nama_Paket. Mengirim id
           membuat SQL Server mencoba mengubah nama paket menjadi int dan gagal
           dengan galat konversi yang tak menyebut sebabnya. */
        $f = ['paket' => [$d->nama('Paket')]];

        $jumlah = $svc->count($f);
        $baris = $svc->rows($f, 1, 100);

        $this->assertGreaterThan(0, $jumlah,
            'Laporan kepatuhan tak memuat satu baris pun untuk paket yang jelas '
            .'ditugaskan.');
        $this->assertStringContainsString($kode, json_encode($baris, JSON_UNESCAPED_UNICODE),
            'Karyawan yang ditugaskan tidak muncul di baris laporan.');
    }
}
