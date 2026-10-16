<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopRepositoryReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * UAT-SOP-027 — mengintip SOP di luar penugasan hanya memperlihatkan
 * keterangannya, bukan isinya.
 *
 * Skenario KONTROL. Dokumen terkendali adalah aturan kerja resmi perusahaan;
 * beberapa di antaranya memuat prosedur yang sengaja dibatasi pada unit
 * tertentu. Rak publik yang membocorkan isinya membuat pembatasan itu sekadar
 * hiasan.
 */
class Kontrol027IntipTerbatasTest extends TestCase
{
    use DatabaseTransactions;

    private string $kode;

    private function dunia(string $modeDistribusi): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        $karyawan = UatAkun::karyawanBiasa();
        $kode = $karyawan ? UatAkun::kodeKaryawan($karyawan) : null;
        if (! $kode) {
            $this->markTestSkipped('Tidak ada akun karyawan yang tertaut data karyawan.');
        }
        $this->kode = $kode;

        try {
            $d = UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket()
                ->tambahItem('Item Rahasia')
                ->terbitkanPaket();
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }

        /* Mode distribusi disetel langsung: SopPaketService::create sengaja
           membakukannya TERBATAS ("keterbukaan harus dipilih sadar, bukan
           kebetulan") dan tidak menerimanya sebagai masukan pembuatan. */
        DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $d->idPaket)
            ->update(['Mode_Distribusi' => $modeDistribusi]);

        return $d;
    }

    #[Group('UAT-SOP-027')]
    public function test_paket_terbatas_tidak_terlihat_sama_sekali_oleh_yang_tak_ditugaskan(): void
    {
        $d = $this->dunia('TERBATAS');

        $this->assertNull(
            app(SopRepositoryReadService::class)->intip($this->kode, $d->idPaket),
            'Paket berdistribusi TERBATAS terintip oleh karyawan yang tak ditugaskan.'
        );
    }

    /**
     * Paket PUBLIK memang boleh diintip — tapi yang boleh terlihat hanya
     * keterangannya. Uji ini menjaga batas itu: kalau suatu hari intip()
     * mengembalikan tautan berkas atau isi dokumen, berkas terkendali menjadi
     * terbuka bagi siapa saja yang punya akun.
     */
    #[Group('UAT-SOP-027')]
    public function test_intip_paket_publik_hanya_memberi_keterangan_tanpa_isi_dokumen(): void
    {
        $d = $this->dunia('PUBLIK');

        $hasil = app(SopRepositoryReadService::class)->intip($this->kode, $d->idPaket);

        $this->assertNotNull($hasil, 'Paket PUBLIK justru tak bisa diintip sama sekali.');

        $datar = json_encode($hasil, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        foreach (['Id_Master_Konten', 'File_Path', 'berkas', 'konten_url', 'signed', 'storage.googleapis'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $datar,
                "Keluaran intip memuat '{$bocor}' — itu jalan menuju isi berkas, "
                .'bukan keterangan.');
        }

        /* Yang MEMANG boleh tampil, diperiksa supaya uji di atas tidak lulus
           hanya karena intip() mengembalikan sesuatu yang kosong. */
        $this->assertStringContainsString($d->nama('Item Rahasia'), $datar,
            'Judul item tak muncul; keterangan yang seharusnya boleh dilihat pun hilang.');
    }
}
