<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopAbsensiService;
use App\Services\LMS\SOP\Support\SopSequenceGate;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * UAT-SOP-033 — item wajib tidak bisa dilewati.
 * UAT-SOP-037 — tanpa check-in, materi paket tetap tersegel.
 *
 * Dua skenario KONTROL yang menumpang satu dunia uji, karena keduanya menjaga
 * hal yang sama dari sisi berbeda: urutan pemahaman yang dirancang tidak boleh
 * bisa dilompati.
 *
 * Diuji pada gerbang BACKEND (SopSequenceGate, SopAbsensiService::terkunci),
 * bukan pada tampilannya. Kunci di layar hanya menyembunyikan tombol; yang
 * menentukan apakah aturannya benar-benar berlaku adalah apa yang terjadi saat
 * seseorang memanggil endpointnya langsung — dan gerbang inilah yang dipanggil
 * konfirmasi() maupun peluncuran kuis.
 */
class Kontrol033UrutanItemWajibTest extends TestCase
{
    use DatabaseTransactions;

    private function siapkan(string $modeUrutan = 'SEKUENSIAL', bool $absensi = false): UatDunia
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

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket($modeUrutan, $absensi)
                ->tambahItem('Item Wajib Pertama', wajib: true)
                ->tambahItem('Item Kedua', wajib: true)
                ->tugaskanKe($kode);
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    #[Group('UAT-SOP-033')]
    public function test_item_kedua_terkunci_selama_item_wajib_pertama_belum_selesai(): void
    {
        $d = $this->siapkan();

        $this->assertTrue(
            SopSequenceGate::isLocked($d->kodeKaryawan, $d->idPaket, $d->idItem[1]),
            'Item kedua dapat dibuka padahal item wajib sebelumnya belum dikonfirmasi. '
            .'Urutan pemahaman yang dirancang admin bisa dilompati lewat API.'
        );
    }

    /**
     * Sisi positif yang wajib ada: gerbang yang mengunci SEGALANYA lulus uji di
     * atas dengan sempurna, sambil membuat paket mustahil diselesaikan siapa pun.
     */
    #[Group('UAT-SOP-033')]
    public function test_item_pertama_tidak_pernah_terkunci(): void
    {
        $d = $this->siapkan();

        $this->assertFalse(
            SopSequenceGate::isLocked($d->kodeKaryawan, $d->idPaket, $d->idItem[0]),
            'Item pertama terkunci — tak ada prasyarat sebelumnya, jadi paket ini '
            .'tak akan pernah bisa dimulai.'
        );
    }

    #[Group('UAT-SOP-033')]
    public function test_kunci_terbuka_setelah_item_wajib_sebelumnya_dikonfirmasi(): void
    {
        $d = $this->siapkan();
        $d->tandaiSelesai($d->idItem[0]);

        $this->assertFalse(
            SopSequenceGate::isLocked($d->kodeKaryawan, $d->idPaket, $d->idItem[1]),
            'Item kedua tetap terkunci walau prasyaratnya sudah dikonfirmasi.'
        );
    }

    /**
     * Mode BEBAS memang dimaksudkan tanpa urutan. Diuji supaya gerbangnya tidak
     * diam-diam berlaku pada paket yang admin sengaja bebaskan — kerusakan yang
     * tak akan terlihat sampai ada karyawan mengeluh tak bisa membuka apa pun.
     */
    #[Group('UAT-SOP-033')]
    public function test_paket_mode_bebas_tidak_pernah_mengunci(): void
    {
        $d = $this->siapkan('BEBAS');

        $this->assertFalse(
            SopSequenceGate::isLocked($d->kodeKaryawan, $d->idPaket, $d->idItem[1]),
            'Paket bermode BEBAS ikut terkunci; gerbang urutan bocor ke paket '
            .'yang sengaja dibebaskan admin.'
        );
    }

    #[Group('UAT-SOP-037')]
    public function test_paket_berabsensi_tersegel_sebelum_check_in(): void
    {
        $d = $this->siapkan('BEBAS', absensi: true);

        $this->assertTrue(
            app(SopAbsensiService::class)->terkunci($d->kodeKaryawan, $d->idPaket),
            'Paket yang mensyaratkan kehadiran terbuka tanpa check-in; catatan '
            .'kehadiran sosialisasi bisa dilewati.'
        );
    }

    #[Group('UAT-SOP-037')]
    public function test_paket_tanpa_syarat_absensi_tidak_tersegel(): void
    {
        $d = $this->siapkan('BEBAS', absensi: false);

        $this->assertFalse(
            app(SopAbsensiService::class)->terkunci($d->kodeKaryawan, $d->idPaket),
            'Paket tanpa syarat kehadiran ikut tersegel; gerbang presensi bocor '
            .'ke paket yang tak pernah memintanya.'
        );
    }
}
