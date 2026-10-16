<?php

namespace Tests\Feature\LMS\SOP\Otomasi;

use App\Services\LMS\SOP\SopAturanPaketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Daftar paket sebuah aturan otomasi, dan penjaga klaimnya.
 *
 * Penjaga tumpang tindih di sini adalah SATU-SATUNYA pengaman terhadap dua
 * aturan yang mewajibkan paket sama — mesin pencabutan sengaja dibiarkan tak
 * tahu apa pun tentang aturan lain. Karena itu uji penolakannya wajib ada, dan
 * wajib pernah terlihat MERAH.
 */
class SopAturanPaketServiceTest extends TestCase
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

    private function aturanKosong(string $nama): int
    {
        return (int) DB::table('N_SOP_Assignment_Rule')->insertGetId([
            'Nama_Aturan' => $nama,
            'Status_Aturan' => 'DRAFT',
            'Draft_Config_JSON' => '{}',
            'Draft_Hash' => hash('sha256', $nama),
            'Last_Apply_Status' => 'IDLE',
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ], 'Id_SOP_Assignment_Rule');
    }

    private function baris(int $idPaket, int $urutan = 1, bool $override = false, ?array $schedule = null): array
    {
        return ['id_paket' => $idPaket, 'urutan' => $urutan, 'override' => $override, 'schedule' => $schedule];
    }

    public function test_daftar_paket_tersimpan_dan_terbaca_urut(): void
    {
        $d = $this->dunia();
        $lain = $d->paketTambahan('Paket Kedua');
        $d->terbitkanPaketLain($lain['paket']);
        $svc = app(SopAturanPaketService::class);
        $ruleId = $this->aturanKosong($d->nama('Aturan Multi'));

        $svc->sinkron($ruleId, [
            $this->baris($lain['paket'], 2),
            $this->baris($d->idPaket, 1),
        ], $d->userIdUji());

        $daftar = $svc->daftar($ruleId);

        $this->assertCount(2, $daftar, 'Daftar paket tidak tersimpan lengkap.');
        $this->assertSame($d->idPaket, (int) $daftar[0]->Id_SOP_Paket,
            'Daftar tidak urut menurut Urutan, sehingga urutan apply tak bisa diandalkan.');
        $this->assertNotEmpty($daftar[0]->Nama_Paket, 'Nama paket tidak ikut terbaca.');
    }

    /**
     * PENJAGA. Satu paket hanya boleh diklaim satu aturan, dan pesannya harus
     * menyebut pemiliknya — kalau tidak, admin tahu ia ditolak tapi tak tahu
     * harus pergi ke mana.
     */
    public function test_paket_milik_aturan_lain_ditolak_dan_pesannya_menyebut_pemiliknya(): void
    {
        $d = $this->dunia();
        $svc = app(SopAturanPaketService::class);

        $namaA = $d->nama('Aturan Pemilik');
        $ruleA = $this->aturanKosong($namaA);
        $svc->sinkron($ruleA, [$this->baris($d->idPaket)], $d->userIdUji());

        $ruleB = $this->aturanKosong($d->nama('Aturan Penyerobot'));

        try {
            $svc->sinkron($ruleB, [$this->baris($d->idPaket)], $d->userIdUji());
            $this->fail('Klaim atas paket milik aturan lain LOLOS. Penjaga tumpang tindih tidak bekerja.');
        } catch (ValidationException $e) {
            $pesan = implode(' ', array_merge(...array_values($e->errors())));
            $this->assertStringContainsString($namaA, $pesan,
                'Pesan penolakan tidak menyebut aturan pemiliknya.');
        }
    }

    /**
     * Membuang paket melepas kepemilikan tanpa menyentuh penugasan lama, dan
     * paket itu langsung bebas diklaim aturan lain. Inilah yang membuat
     * filtered unique index dipilih terfilter, bukan unique biasa.
     */
    public function test_paket_yang_dibuang_bebas_diklaim_aturan_lain(): void
    {
        $d = $this->dunia();
        $svc = app(SopAturanPaketService::class);

        $ruleA = $this->aturanKosong($d->nama('Aturan A'));
        $svc->sinkron($ruleA, [$this->baris($d->idPaket)], $d->userIdUji());
        $svc->sinkron($ruleA, [], $d->userIdUji());

        $ruleB = $this->aturanKosong($d->nama('Aturan B'));
        $svc->sinkron($ruleB, [$this->baris($d->idPaket)], $d->userIdUji());

        $this->assertCount(1, $svc->daftar($ruleB), 'Paket yang dibuang tidak bisa diklaim aturan lain.');
        $this->assertCount(0, $svc->daftar($ruleA), 'Paket yang dibuang masih menempel pada aturan lamanya.');
    }

    public function test_menyunting_daftar_paket_tidak_menggandakan_baris_hidup(): void
    {
        $d = $this->dunia();
        $lain = $d->paketTambahan('Paket Tambahan');
        $d->terbitkanPaketLain($lain['paket']);
        $svc = app(SopAturanPaketService::class);
        $ruleId = $this->aturanKosong($d->nama('Aturan Disunting'));

        $svc->sinkron($ruleId, [$this->baris($d->idPaket, 1)], $d->userIdUji());
        $svc->sinkron($ruleId, [$this->baris($d->idPaket, 1), $this->baris($lain['paket'], 2)], $d->userIdUji());
        $svc->sinkron($ruleId, [$this->baris($lain['paket'], 1)], $d->userIdUji());

        $daftar = $svc->daftar($ruleId);

        $this->assertCount(1, $daftar, 'Penyuntingan berulang meninggalkan baris hidup kembar.');
        $this->assertSame($lain['paket'], (int) $daftar[0]->Id_SOP_Paket);
    }

    public function test_paket_belum_published_ditolak(): void
    {
        $d = $this->dunia();
        $draft = $d->paketTambahan('Paket Draft');
        $svc = app(SopAturanPaketService::class);
        $ruleId = $this->aturanKosong($d->nama('Aturan Draft'));

        $this->expectException(ValidationException::class);
        $svc->sinkron($ruleId, [$this->baris($draft['paket'])], $d->userIdUji());
    }

    public function test_jadwal_efektif_memakai_override_ketika_dinyalakan(): void
    {
        $svc = app(SopAturanPaketService::class);
        $induk = [
            'deadline_mode' => 'RELATIVE', 'deadline_days' => 7, 'deadline_date' => null,
            'reminder_enabled' => false, 'reminder_interval_days' => null,
        ];

        $ikut = (object) ['Flag_Override_Jadwal' => 'T'];
        $this->assertSame(7, $svc->jadwalEfektif($ikut, $induk)['deadline_days']);

        $sendiri = (object) [
            'Flag_Override_Jadwal' => 'Y', 'Deadline_Mode' => 'RELATIVE', 'Deadline_Hari' => 30,
            'Tanggal_Deadline' => null, 'Flag_Reminder' => 'Y', 'Irama_Reminder_Hari' => 5,
        ];
        $hasil = $svc->jadwalEfektif($sendiri, $induk);

        $this->assertSame(30, $hasil['deadline_days'], 'Override tenggat tidak dipakai.');
        $this->assertTrue($hasil['reminder_enabled'], 'Override reminder tidak dipakai.');
    }

    /**
     * Override adalah penggantian UTUH, bukan tambahan di atas induk.
     */
    public function test_override_none_membatalkan_tenggat_induk(): void
    {
        $svc = app(SopAturanPaketService::class);
        $induk = [
            'deadline_mode' => 'RELATIVE', 'deadline_days' => 7, 'deadline_date' => null,
            'reminder_enabled' => true, 'reminder_interval_days' => 3,
        ];
        $sendiri = (object) [
            'Flag_Override_Jadwal' => 'Y', 'Deadline_Mode' => 'NONE', 'Deadline_Hari' => null,
            'Tanggal_Deadline' => null, 'Flag_Reminder' => 'T', 'Irama_Reminder_Hari' => null,
        ];

        $hasil = $svc->jadwalEfektif($sendiri, $induk);

        $this->assertSame('NONE', $hasil['deadline_mode']);
        $this->assertNull($hasil['deadline_days'], 'Tenggat induk bocor ke paket yang di-override NONE.');
    }

    public function test_tandai_gantung_hanya_menyentuh_paket_yang_belum_selesai(): void
    {
        $d = $this->dunia();
        $lain = $d->paketTambahan('Paket Selesai');
        $d->terbitkanPaketLain($lain['paket']);
        $svc = app(SopAturanPaketService::class);
        $ruleId = $this->aturanKosong($d->nama('Aturan Gantung'));

        $svc->sinkron($ruleId, [
            $this->baris($d->idPaket, 1),
            $this->baris($lain['paket'], 2),
        ], $d->userIdUji());

        $daftar = $svc->daftar($ruleId);
        $svc->tandaiStatus((int) $daftar[0]->Id_SOP_Assignment_Rule_Paket, 'SUCCESS');

        $disapu = $svc->tandaiGantung($ruleId, 'Worker mati di tengah jalan.');

        $sesudah = collect($svc->daftar($ruleId))->keyBy('Id_SOP_Paket');

        $this->assertSame(1, $disapu, 'Jumlah paket yang disapu tidak sesuai.');
        $this->assertSame('SUCCESS', $sesudah[$d->idPaket]->Last_Apply_Status,
            'Paket yang sudah SUCCESS ikut ditandai gagal.');
        $this->assertSame('FAILED', $sesudah[$lain['paket']]->Last_Apply_Status,
            'Paket yang menggantung tidak ditandai, sehingga sebab kegagalan tak terlihat di mana pun.');
    }
}
