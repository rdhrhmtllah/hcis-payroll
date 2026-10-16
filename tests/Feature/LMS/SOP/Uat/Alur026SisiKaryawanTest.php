<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\SopRepositoryReadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * Sisi karyawan — jalur normal.
 *
 * UAT-SOP-026 menjelajah & memfavoritkan Repository · 028 melihat daftar tugas
 * di SOP Saya · 029 kartu kepatuhan pribadi · 030 item tersusun sesuai rakitan
 * admin.
 *
 * Keempatnya diuji pada satu paket yang BENAR-BENAR ditugaskan lewat jalur
 * penjadwalan, bukan pada data yang kebetulan sudah ada di database. Paket
 * pinjaman membuat uji lulus atau gagal karena keadaan orang lain, dan
 * kegagalannya tak pernah bisa diulang.
 */
class Alur026SisiKaryawanTest extends TestCase
{
    use DatabaseTransactions;

    private string $kode;

    /**
     * @param  string|null  $distribusi  'PUBLIK' bila paket harus terlihat di rak
     *                                   Repository. Bawaan pembuatan paket adalah TERBATAS, dan paket
     *                                   TERBATAS SENGAJA tidak muncul di Repository sekalipun ditugaskan —
     *                                   rak itu memang rak publik, sedangkan penugasan muncul di SOP Saya.
     *                                   Menguji pencarian dan favorit dengan paket TERBATAS berarti
     *                                   menuntut perilaku yang memang tidak dijanjikan.
     */
    private function dunia(int $jumlahItem = 2, ?string $distribusi = null): UatDunia
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
                ->buatDokumenTerbit()->buatPaket();

            for ($i = 1; $i <= $jumlahItem; $i++) {
                $d->tambahItem("Item {$i}", wajib: true);
            }

            $d->terbitkanPaket()->tugaskanKe($kode);

            if ($distribusi !== null) {
                DB::table('N_SOP_Master_Paket')
                    ->where('Id_SOP_Paket', $d->idPaket)
                    ->update(['Mode_Distribusi' => $distribusi]);
            }

            return $d;
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    #[Group('UAT-SOP-028')]
    public function test_paket_yang_ditugaskan_muncul_di_daftar_tugas_karyawan(): void
    {
        $d = $this->dunia();

        $tugas = app(SopProgressReadService::class)->tugasSaya($this->kode, 'aktif');
        $datar = json_encode($tugas, JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString($d->nama('Paket'), $datar,
            'Paket yang baru ditugaskan tidak muncul di Functional Document. Karyawan tak akan '
            .'pernah tahu ada kewajiban baru — dan ketidaktahuan itu akan terbaca '
            .'sebagai kelalaian pada laporan kepatuhan.');
    }

    #[Group('UAT-SOP-030')]
    public function test_item_tersusun_sesuai_urutan_yang_dirakit_admin(): void
    {
        $d = $this->dunia(3);

        $bundle = app(SopProgressReadService::class)->detailBundle($this->kode, $d->idPaket);

        $this->assertNotNull($bundle, 'Karyawan yang ditugaskan justru tak bisa membuka paketnya.');

        $judul = [];
        foreach ($bundle['items'] ?? [] as $it) {
            $judul[] = is_array($it) ? ($it['Judul_Item'] ?? $it['judul'] ?? '') : ($it->Judul_Item ?? '');
        }
        $judul = array_values(array_filter($judul));

        $this->assertSame(
            [$d->nama('Item 1'), $d->nama('Item 2'), $d->nama('Item 3')],
            $judul,
            'Urutan item yang dilihat karyawan berbeda dari yang disusun admin di Builder. '
            .'Urutan pemahaman yang dirancang tidak pernah sampai.'
        );
    }

    #[Group('UAT-SOP-029')]
    public function test_kartu_kepatuhan_menghitung_paket_yang_belum_selesai(): void
    {
        $d = $this->dunia();

        $tugas = app(SopProgressReadService::class)->tugasSaya($this->kode, 'aktif');

        $status = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan', $d->idPenjadwalan)
            ->where('Kode_Karyawan', $this->kode)
            ->value('Status_Paket');

        $this->assertNotSame('SELESAI', $status,
            'Paket yang belum dibaca sama sekali sudah berstatus SELESAI.');
        $this->assertNotEmpty($tugas,
            'Daftar tugas kosong padahal ada paket aktif yang ditugaskan.');
    }

    #[Group('UAT-SOP-026')]
    public function test_favorit_bisa_dipasang_dan_dicabut(): void
    {
        $d = $this->dunia(2, 'PUBLIK');
        $svc = app(SopRepositoryReadService::class);

        $pasang = $svc->toggleFavorit($this->kode, $d->idPaket);
        $this->assertSame(1,
            DB::table('N_SOP_Favorit')->where('Kode_Karyawan', $this->kode)
                ->where('Id_SOP_Paket', $d->idPaket)->count(),
            'Favorit tidak tersimpan.');

        /* Sisi cabutnya diuji juga: tombol yang hanya bisa dinyalakan membuat
           daftar favorit tumbuh selamanya dan berhenti berguna. */
        $svc->toggleFavorit($this->kode, $d->idPaket);
        $this->assertSame(0,
            DB::table('N_SOP_Favorit')->where('Kode_Karyawan', $this->kode)
                ->where('Id_SOP_Paket', $d->idPaket)->count(),
            'Favorit tidak bisa dicabut.');

        $this->assertIsArray($pasang);
    }

    #[Group('UAT-SOP-026')]
    public function test_pencarian_menemukan_paket_di_rak_publik(): void
    {
        $d = $this->dunia(2, 'PUBLIK');

        $hasil = app(SopRepositoryReadService::class)->cari($this->kode, $d->tanda());

        $this->assertStringContainsString($d->tanda(), json_encode($hasil, JSON_UNESCAPED_UNICODE),
            'Pencarian tidak menemukan paket yang ada di rak publik, sehingga SOP hanya '
            .'bisa ditemukan dengan menggulir.');
    }
}
