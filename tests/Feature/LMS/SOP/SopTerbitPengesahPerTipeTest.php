<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penjaga "tak boleh terbit tanpa pengesah" harus berhenti berlaku untuk tipe
 * yang memang tidak mengenal pengesahan.
 *
 * Kalau tidak, FORM/VIDEO/KUIS jadi MUSTAHIL diterbitkan begitu blok tanda
 * tangannya dibersihkan — penjaga menuntut sesuatu yang profil tipenya sendiri
 * nyatakan tidak berlaku.
 *
 * UTAMAKAN BACA-SAJA. Uji ini memakai baris yang sudah ada bila tersedia:
 * server dev ini RCSI-nya mati, sehingga penulis menahan pembaca dan transaksi
 * uji ikut mengantrekan UI orang yang sedang bekerja.
 *
 * TAPI TIDAK BOLEH BERGANTUNG PADANYA. Per 1 Sep 2026 N_SOP_Dokumen sama sekali
 * tak memuat dokumen bertipe KUIS maupun VIDEO — hanya DOKUMEN (46) dan FORM
 * (7). Uji yang menagih baris yang tak ada akan MERAH tanpa satu pun aturan
 * dilanggar, dan menggantinya dengan markTestSkipped justru membuat dua dari
 * tiga tipe yang jadi ALASAN berkas ini ada berhenti diuji selamanya — persis
 * jebakan yang membuat FORM/VIDEO/KUIS pernah mustahil diterbitkan tanpa satu
 * pun uji merah.
 *
 * Karena itu fiksturnya menyediakan barisnya sendiri bila master dev belum
 * punya: satu dokumen + satu versi, tanpa pengesah dalam bentuk apa pun,
 * digulung balik oleh DatabaseTransactions. Dua baris, dan hanya ketika memang
 * tak ada yang bisa dipinjam.
 */
class SopTerbitPengesahPerTipeTest extends TestCase
{
    use DatabaseTransactions;

    /** Satu versi bertipe $tipe yang tidak punya pengesah dalam bentuk apa pun. */
    private function versiTanpaPengesah(string $tipe): int
    {
        $id = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('d.Tipe', $tipe)
            ->where(fn ($w) => $w->whereNull('v.Disahkan_Oleh')->orWhere('v.Disahkan_Oleh', ''))
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_SOP_Item_Versi_Pengesah as p')
                    ->whereColumn('p.Id_SOP_Item_Versi', 'v.Id_SOP_Item_Versi')
                    ->where('p.Flag_Aktif', 'Y');
            })
            ->value('v.Id_SOP_Item_Versi');

        return $id !== null ? (int) $id : $this->buatVersiTanpaPengesah($tipe);
    }

    /**
     * Dokumen + versi bertipe $tipe, tanpa pengesah, di dalam transaksi uji.
     *
     * Sengaja lewat SopDokumenService::buat() untuk dokumennya — di sanalah
     * seluruh penjaga identitas hidup, dan fikstur yang menembus langsung ke
     * tabel bisa melahirkan baris yang tak akan pernah lolos pintu mana pun.
     * Barisnya versi ditulis langsung karena yang diuji justru keadaan yang
     * tidak boleh dilahirkan jalur normal: versi tanpa blok tanda tangan.
     */
    private function buatVersiTanpaPengesah(string $tipe): int
    {
        $unik = substr((string) microtime(true), -5);

        $idDokumen = app(SopDokumenService::class)->buat([
            'Judul' => "SOP UJI PENGESAH {$tipe} {$unik}",
            'Tipe' => $tipe,
        ], 1);

        $idVersi = DB::table('N_SOP_Item_Versi')->insertGetId([
            'Id_SOP_Dokumen' => $idDokumen,
            /* Versi_Ke = 0: nomorVersiBerikut() memang memulai dari NOL, dan
               dokumen ini baru lahir sehingga belum ada nomor yang terpakai. */
            'Versi_Ke' => 0,
            'Status' => 'DRAFT',
            'Flag_Aktif' => SopStatus::AKTIF,
            'Wajib_Baca_Ulang' => SopStatus::INAKTIF,
            'Created_At' => now(),
            'Created_By' => 1,
        ], 'Id_SOP_Item_Versi');

        /* Penjaga kerja-nyata: kalau baris ini ternyata TIDAK memenuhi syarat
           "tanpa pengesah", uji di bawah akan hijau atau merah karena sebab yang
           sama sekali lain. */
        $this->assertFalse(
            DB::table('N_SOP_Item_Versi_Pengesah')
                ->where('Id_SOP_Item_Versi', $idVersi)->exists(),
            "Fikstur versi {$tipe} lahir sudah membawa blok pengesah — bukan lagi kondisi yang diuji.",
        );

        return (int) $idVersi;
    }

    private function panggilPenjaga(int $idVersi): void
    {
        $svc = app(SopDokumenService::class);
        $m = new \ReflectionMethod($svc, 'pastikanAdaPengesah');
        $m->setAccessible(true);
        $m->invoke($svc, $idVersi);
    }

    public function test_dokumen_tanpa_pengesah_tetap_ditolak(): void
    {
        $id = $this->versiTanpaPengesah(SopTipeItem::DOKUMEN);

        $this->expectException(\DomainException::class);
        $this->panggilPenjaga($id);
    }

    public function test_form_tanpa_pengesah_boleh_terbit(): void
    {
        $this->panggilPenjaga($this->versiTanpaPengesah(SopTipeItem::FORM));
        $this->addToAssertionCount(1);
    }

    public function test_kuis_tanpa_pengesah_boleh_terbit(): void
    {
        $this->panggilPenjaga($this->versiTanpaPengesah(SopTipeItem::KUIS));
        $this->addToAssertionCount(1);
    }

    public function test_video_tanpa_pengesah_boleh_terbit(): void
    {
        $this->panggilPenjaga($this->versiTanpaPengesah(SopTipeItem::VIDEO));
        $this->addToAssertionCount(1);
    }
}
