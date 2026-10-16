<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\SopVersiReviewService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopTipeItemAktif;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RANTAI PENUH MASTER DOKUMEN, SATU KALI UNTUK TIAP TIPE YANG DITAWARKAN.
 *
 * Master Dokumen menawarkan setiap tipe ber-versi di pemilih Tipe
 * (`SopTipeItemAktif::aktifVersioned()`). Yang ditawarkan harus bisa dijalani
 * sampai selesai — kalau tidak, admin memilih sesuatu yang tak pernah sampai ke
 * siapa pun, dan kegagalannya baru terlihat berminggu-minggu kemudian.
 *
 * KENAPA SATU UJI PANJANG, BUKAN LIMA UJI PENDEK. Cacat yang berulang di modul
 * ini bukan "satu lapis salah", melainkan DUA LAPIS YANG MASING-MASING BENAR
 * bertemu sebagai jalan buntu. Contoh terjelasnya: `SopTipeItem::PROFIL`
 * menyatakan blok pengesahan hanya milik DOKUMEN, sementara syarat tahap
 * `REALISASI_PENGESAH` di `N_SOP_Master_Tahap_Versi_Syarat` berlaku untuk semua
 * tipe. Keduanya lolos uji unit masing-masing; FORM/VIDEO/KUIS tetap mustahil
 * terbit. Hanya rantai penuh yang menangkapnya.
 *
 * Rantainya: buat identitas → tambah versi → REVIEW → APPROVAL → APPROVED
 * (Pemberkasan) → terbit. Persis urutan yang dilalui wizard.
 */
class SopDokumenSiklusPerTipeTest extends TestCase
{
    use DatabaseTransactions;

    private function dok(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function tahap(): SopTahapVersiService
    {
        return app(SopTahapVersiService::class);
    }

    /**
     * Identitas minimum yang MASIH SAH bagi sebuah tipe.
     *
     * Jenis & APQC diambil dari master, bukan dikarang: pasangannya adalah DATA
     * (`N_SOP_Master_Jenis_Dokumen_Tipe_Item`, `N_SOP_Master_APQC_Tipe_Item`),
     * dan id karangan akan ditolak `pastikanJenisSesuaiTipe()` dengan alasan
     * yang tak ada hubungannya dengan apa yang sedang diuji.
     *
     * Tipe yang belum punya pasangan sama sekali berangkat tanpa keduanya, dan
     * SERVICE MEMANG MENERIMANYA — itu bukan kekurangan fixture.
     *
     * Perhatikan bedanya dengan LAYAR. Sejak 10 Sep 2026 wizard Master Dokumen
     * menolak identitas yang nomor resminya tak bisa dirakit utuh, jadi dokumen
     * seperti ini tak bisa lahir dari tangan manusia. Service tetap longgar
     * dengan sengaja: ia juga melayani backfill, penyalinan paket, dan 40
     * dokumen lama yang seluruh field identitasnya memang kosong — menolak di
     * sini akan mematikan jalur yang tak melakukan kesalahan apa pun. Aturan
     * "nomor wajib utuh" ditegakkan di setiap pintu MANUSIA (wizard, dan nanti
     * scan import), bukan di pintu mesin.
     */
    private function identitas(string $tipe): array
    {
        $jenis = DB::table('N_SOP_Master_Jenis_Dokumen_Tipe_Item as m')
            ->join('N_SOP_Master_Jenis_Dokumen as j', 'j.Id_SOP_Jenis_Dokumen', '=', 'm.Jenis_Dokumen_Id')
            ->where('m.Tipe_Item', $tipe)
            ->where('m.Flag_Aktif', SopStatus::AKTIF)
            ->where('j.Flag_Aktif', SopStatus::AKTIF)
            ->value('m.Jenis_Dokumen_Id');

        /* m.Flag_Aktif WAJIB ikut disaring, persis seperti pencarian jenis di
           atas. Tanpa itu fixture memungut pemetaan yang sudah DINONAKTIFKAN,
           lalu service menolaknya dengan benar — dan kegagalannya terbaca
           seperti "KUIS tak bisa terbit", padahal fixture-nya yang menyodorkan
           pasangan yang memang tidak sah. Dua pencarian setara yang hanya satu
           disaring: pola yang sudah berulang di modul ini. */
        $apqc = DB::table('N_SOP_Master_APQC_Tipe_Item as m')
            ->join('N_SOP_Master_APQC as a', 'a.Id_SOP_APQC', '=', 'm.APQC_Id')
            ->where('m.Tipe_Item', $tipe)
            ->where('m.Flag_Aktif', SopStatus::AKTIF)
            ->where('a.Flag_Aktif', SopStatus::AKTIF)
            ->value('m.APQC_Id');

        return [
            'Judul' => 'SIKLUS '.$tipe.' '.substr((string) microtime(true), -6),
            'Tipe' => $tipe,
            'Jenis_Dokumen_Id' => $jenis,
            'APQC_Id' => $apqc,
        ];
    }

    /** Isi versi yang sah untuk tipe ini: ujian bagi KUIS, berkas bagi sisanya. */
    private function isiVersi(string $tipe): array
    {
        $isi = [
            'Ringkasan_Perubahan' => 'Terbit awal',
            'Tanggal_Berlaku' => now()->toDateString(),
        ];

        if ($tipe === SopTipeItem::KUIS) {
            $isi['Id_Master_Ujian'] = 'UJI-SIKLUS-'.substr((string) microtime(true), -6);
            $isi['Nama_Ujian'] = 'Ujian Siklus';

            return $isi;
        }

        /* Jenis berkas HARUS cocok dengan tipenya — `kontenCocok()` menolak
           video yang dilampiri PDF, dan penolakan itu justru bagian dari yang
           diuji rantai ini. */
        $jenisKonten = $tipe === SopTipeItem::VIDEO ? 'video' : 'document';
        $konten = DB::table('N_LMS_Master_Konten')
            ->where('Tipe_Konten', $jenisKonten)
            ->value('Id_Master_Konten');

        $this->assertNotNull(
            $konten,
            "Library tak punya satu pun konten bertipe \"{$jenisKonten}\" — "
            .'rantai tipe '.$tipe.' tak bisa diuji tanpa itu.',
        );

        $isi['Id_Master_Konten'] = $konten;

        /* Blok tanda tangan HANYA untuk tipe yang mengenalnya. Mengirimnya pada
           tipe lain bukan sekadar mubazir — `pastikanFieldSesuaiTipe()` menolak
           permintaannya, dan itu justru salah satu perilaku yang dijaga rantai
           ini. */
        if (SopTipeItem::blokBerlaku($tipe, SopTipeItem::BLOK_PENGESAHAN)) {
            $isi['Pengesah'] = [[
                'Peran' => 'Disahkan oleh',
                'Nama' => 'Pengesah Uji',
                'Jabatan' => 'Manager',
            ]];
        }

        return $isi;
    }

    /**
     * Jalani rantai penuh, kembalikan id versinya.
     *
     * Kegagalan sengaja dibiarkan naik sebagai DomainException apa adanya:
     * pesannya sudah ditulis untuk manusia, dan membungkusnya jadi assertion
     * sendiri hanya akan menyembunyikan sebab yang paling berguna.
     */
    private function jalaniSiklus(string $tipe): int
    {
        $idDok = $this->dok()->buat($this->identitas($tipe), 1);

        $idVersi = $this->dok()->tambahVersi($idDok, $this->isiVersi($tipe), 1);
        $this->assertNotNull($idVersi, "tambahVersi() menolak tipe {$tipe}.");

        $this->tahap()->pindah($idVersi, 'REVIEW', 1);

        app(SopVersiReviewService::class)->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Tanggal_Review' => now()->toDateString(),
            'Hasil_Review' => '<p>Sudah direview.</p>',
        ]], 1);

        $this->tahap()->pindah($idVersi, 'APPROVAL', 1);
        $this->tahap()->pindah($idVersi, 'APPROVED', 1);

        $this->assertTrue(
            $this->dok()->terbitkanVersi($idVersi, 1),
            "terbitkanVersi() gagal untuk tipe {$tipe}.",
        );

        return $idVersi;
    }

    /* ── Rantai penuh, satu uji per tipe ─────────────────────────────────── */

    public function test_tipe_dokumen_bisa_dijalani_sampai_terbit(): void
    {
        $this->assertTerbit(SopTipeItem::DOKUMEN);
    }

    public function test_tipe_form_bisa_dijalani_sampai_terbit(): void
    {
        $this->assertTerbit(SopTipeItem::FORM);
    }

    public function test_tipe_video_bisa_dijalani_sampai_terbit(): void
    {
        $this->assertTerbit(SopTipeItem::VIDEO);
    }

    public function test_tipe_kuis_bisa_dijalani_sampai_terbit(): void
    {
        $this->assertTerbit(SopTipeItem::KUIS);
    }

    private function assertTerbit(string $tipe): void
    {
        $idVersi = $this->jalaniSiklus($tipe);

        $versi = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->first(['v.Status', 'd.Id_Versi_Aktif']);

        $this->assertSame(SopStatus::PAKET_PUBLISHED, $versi->Status, "Versi {$tipe} tak berstatus terbit.");
        $this->assertSame(
            $idVersi,
            (int) $versi->Id_Versi_Aktif,
            "Pointer dokumen {$tipe} tak bergeser ke versi yang baru terbit.",
        );
    }

    /**
     * PEMILIH TIPE DAN RANTAI DI ATAS HARUS MENYEBUT DAFTAR YANG SAMA.
     *
     * Kalau suatu saat tipe ber-versi kelima ditambahkan, berkas ini harus
     * ikut merah — bukan diam-diam melewatkannya. Tipe yang ditawarkan tanpa
     * pernah dijalani sampai selesai adalah cara persis bagaimana FORM bisa
     * bertahun-tahun tak bisa terbit tanpa ada yang tahu.
     */
    public function test_setiap_tipe_yang_ditawarkan_master_dokumen_punya_uji_siklus(): void
    {
        $diuji = [SopTipeItem::DOKUMEN, SopTipeItem::FORM, SopTipeItem::VIDEO, SopTipeItem::KUIS];

        $ditawarkan = SopTipeItemAktif::aktifVersioned();

        $this->assertSame(
            [],
            array_values(array_diff($ditawarkan, $diuji)),
            'Ada tipe yang ditawarkan Master Dokumen tetapi tak punya uji rantai penuh di berkas ini.',
        );
    }
}
