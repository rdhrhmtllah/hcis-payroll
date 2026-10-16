<?php

namespace Tests\Feature\LMS\SOP;

use App\Http\Controllers\LMS\SOP\Governance\SopAdminDokumenController;
use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lencana merah "Tanpa Pengesah" hanya untuk tipe yang PUNYA blok pengesahan.
 *
 * Profil tipe (SopTipeItem::PROFIL) sudah lama menyatakan bahwa hanya DOKUMEN
 * yang memiliki blok pengesahan; wizard-nya pun tak pernah menawarkan langkah
 * pengesahan kepada FORM, VIDEO, dan KUIS. Tetapi daftar Master Dokumen menilai
 * lencana itu hanya dari "Disahkan_Oleh kosong", tanpa bertanya tipenya —
 * sehingga formulir yang sudah benar tetap dicap merah atas kekurangan yang tak
 * mungkin dipenuhi siapa pun.
 */
class SopLencanaTanpaPengesahTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): ?User
    {
        return User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
    }

    private string $tanda = '';

    /** Dokumen + satu versi berlaku TANPA pengesah. */
    private function dokumenBerlakuTanpaPengesah(string $tipe): int
    {
        $this->tanda = 'UJILENCANA'.bin2hex(random_bytes(4));

        $idDok = (int) DB::table('N_SOP_Dokumen')->insertGetId([
            'Judul' => 'Uji lencana '.$tipe.' '.$this->tanda,
            'Nomor_SOP_Resmi' => 'UJI/'.$tipe.'/'.bin2hex(random_bytes(3)),
            'Tipe' => $tipe,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ], 'Id_SOP_Dokumen');

        $idVersi = (int) DB::table('N_SOP_Item_Versi')->insertGetId([
            'Id_SOP_Dokumen' => $idDok,
            'Versi_Ke' => 1,
            'Status' => SopStatus::VERSI_PUBLISHED,
            'Disahkan_Oleh' => null,   // justru inilah yang dulu memicu lencana
            'Created_At' => now(),
        ], 'Id_SOP_Item_Versi');

        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)
            ->update(['Id_Versi_Aktif' => $idVersi]);

        return $idDok;
    }

    private function baris(int $idDok): ?array
    {
        $admin = $this->admin();

        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        $this->actingAs($admin);

        /* Dicari lewat q: daftarnya berpaginasi dan berisi ratusan dokumen
           nyata, jadi baris uji belum tentu ada di halaman pertama. */
        $permintaan = Request::create('/api/v1/sop/admin/dokumen/current', 'GET', [
            'limit' => 25,
            'q' => $this->tanda,
        ]);
        $permintaan->setUserResolver(fn () => $admin);

        $respons = app(SopAdminDokumenController::class)
            ->getDataCurrent($permintaan)
            ->getData(true);

        /* Dicocokkan lewat TANDA pada judulnya: id di daftar dikirim sudah
           ter-hash, jadi membandingkannya dengan id mentah tak pernah cocok. */
        foreach (data_get($respons, 'result.rows', []) as $baris) {
            if (str_contains((string) ($baris['Judul'] ?? ''), $this->tanda)) {
                return $baris;
            }
        }

        return null;
    }

    public function test_dokumen_tanpa_pengesah_tetap_berlencana(): void
    {
        $baris = $this->baris($this->dokumenBerlakuTanpaPengesah(SopTipeItem::DOKUMEN));

        $this->assertNotNull($baris, 'Dokumen uji tak ikut di daftar.');
        $this->assertTrue((bool) $baris['Tanpa_Pengesah'],
            'DOKUMEN berlaku tanpa pengesah adalah cacat nyata — lencananya harus tetap menyala.');
    }

    /**
     * VIDEO dan KUIS tak punya blok pengesahan; FORM punya, tetapi opsional
     * (5 Okt 2026). Lencana hanya menagih yang WAJIB.
     */
    public function test_tipe_tanpa_pengesahan_wajib_tidak_berlencana(): void
    {
        foreach ([SopTipeItem::FORM, SopTipeItem::VIDEO, SopTipeItem::KUIS] as $tipe) {
            $this->assertFalse(
                SopTipeItem::blokWajib($tipe, SopTipeItem::BLOK_PENGESAHAN),
                "{$tipe} seharusnya tak wajib disahkan.",
            );

            $baris = $this->baris($this->dokumenBerlakuTanpaPengesah($tipe));

            $this->assertNotNull($baris, "Dokumen {$tipe} tak ikut di daftar.");
            $this->assertFalse((bool) $baris['Tanpa_Pengesah'],
                "{$tipe} tak punya pengesah sama sekali — mencapnya merah menagih hal yang mustahil.");
        }
    }
}
