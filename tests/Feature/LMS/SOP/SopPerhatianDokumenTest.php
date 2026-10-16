<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopPerhatianDokumen;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua daftar kerja Master Dokumen.
 *
 * PERLU DITINJAU = tanggalnya menagih (masa berlaku / jadwal review).
 * BELUM LENGKAP  = belum punya versi berlaku sama sekali.
 *
 * KENAPA DIUJI TERPISAH DARI TAHAP. Pil ini sempat berarti "versi berlaku tanpa
 * pengesah" — penggantian saat masa berlaku dicabut 15 Agu, yang tak ikut
 * dikembalikan ketika masa berlaku dihidupkan lagi 27 Agu. Akibatnya pil
 * mengukur hal lain daripada janji namanya, dan karena angkanya nol ia tak
 * pernah muncul: admin mengira ia sedang mengingatkan, padahal ia diam karena
 * menghitung sesuatu yang lain. Uji ini menuntut pil dan daftarnya menjawab
 * pertanyaan yang sama.
 */
class SopPerhatianDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private function adminAtauSkip(): User
    {
        $u = User::whereNotNull('Id_Users')->get()
            ->first(fn ($x) => $x->memilikiJabatan('menuSopDokumenPage'));
        if (! $u) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }

        return $u;
    }

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    /** Dokumen ber-versi berlaku, dengan tanggal yang bisa disetel bebas. */
    private function dokumenBerlaku(?string $review, ?string $kadaluarsa): int
    {
        $idDok = $this->svc()->buat([
            'Judul' => 'SOP UJI TINJAU '.substr((string) microtime(true), -6),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $idVersi = $this->svc()->tambahVersi($idDok, [], 1);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Status' => SopStatus::VERSI_PUBLISHED,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', 'FILING_REGISTERED')->value('Id_Tahap'),
            'Tanggal_Review_Berikutnya' => $review,
            'Tanggal_Kadaluarsa' => $kadaluarsa,
        ]);
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->update(['Id_Versi_Aktif' => $idVersi]);

        return $idDok;
    }

    /**
     * Id dokumen hasil penyaring, SEBAGAI INTEGER.
     *
     * Driver SQL Server mengembalikan kunci numerik sebagai STRING, sedangkan
     * SopDokumenService::buat() mengembalikan int. assertContains() bersifat
     * ketat, jadi 19008 tak pernah sama dengan '19008'.
     *
     * Yang berbahaya bukan merahnya, melainkan hijaunya: assertNotContains()
     * pada pasangan tipe yang berbeda LULUS SELALU — juga ketika baris yang
     * seharusnya tersaring justru lolos. Penyaring yang rusak akan tetap
     * terbaca aman.
     *
     * @return int[]
     */
    private function idsDari($q): array
    {
        return $q->pluck('d.Id_SOP_Dokumen')->map(fn ($x) => (int) $x)->all();
    }

    private function query()
    {
        return DB::table('N_SOP_Dokumen as d')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->whereNull('d.Nonaktif_At');
    }

    // ── Penilaian per baris ──────────────────────────────────────────────────

    /** Yang sudah lewat dan yang belum lewat bukan keadaan yang sama. */
    public function test_membedakan_terlambat_dari_segera(): void
    {
        $lewat = now()->subDay()->toDateString();
        $dekat = now()->addDays(10)->toDateString();
        $jauh = now()->addYears(2)->toDateString();

        $this->assertSame('TERLAMBAT', SopPerhatianDokumen::nilai($lewat, $jauh)['Keadaan']);
        $this->assertSame('SEGERA', SopPerhatianDokumen::nilai($dekat, $jauh)['Keadaan']);
        $this->assertNull(SopPerhatianDokumen::nilai($jauh, $jauh)['Keadaan']);
    }

    /** Tanpa tanggal sama sekali tak berarti menagih — ia berarti tak diketahui. */
    public function test_tanpa_tanggal_tidak_menagih(): void
    {
        $this->assertNull(SopPerhatianDokumen::nilai(null, null)['Keadaan']);
    }

    /**
     * Yang disebut adalah tanggal TERDEKAT, bukan yang kebetulan diperiksa dulu.
     *
     * Admin bertanya "apa yang harus saya kerjakan lebih dulu"; menyebut
     * kadaluarsa yang masih 80 hari lagi padahal reviewnya 5 hari lagi
     * menjawab pertanyaan yang lain.
     */
    public function test_menyebut_tanggal_terdekat_saat_keduanya_menagih(): void
    {
        $hasil = SopPerhatianDokumen::nilai(
            now()->addDays(5)->toDateString(),
            now()->addDays(80)->toDateString(),
        );

        $this->assertSame('SEGERA', $hasil['Keadaan']);
        $this->assertSame('REVIEW', $hasil['Sebab']);
    }

    /** Yang sudah lewat menang atas yang belum, apa pun urutan kolomnya. */
    public function test_yang_sudah_lewat_menang_atas_yang_belum(): void
    {
        $hasil = SopPerhatianDokumen::nilai(
            now()->addDays(5)->toDateString(),
            now()->subDays(3)->toDateString(),
        );

        $this->assertSame('TERLAMBAT', $hasil['Keadaan']);
        $this->assertSame('KADALUARSA', $hasil['Sebab']);
    }

    /** Ambangnya tepat 90 hari — sehari di luarnya tak ikut. */
    public function test_ambang_sembilan_puluh_hari_menggigit_tepat(): void
    {
        $this->assertSame('SEGERA', SopPerhatianDokumen::nilai(
            now()->addDays(SopPerhatianDokumen::AMBANG_HARI)->toDateString(), null)['Keadaan']);
        $this->assertNull(SopPerhatianDokumen::nilai(
            now()->addDays(SopPerhatianDokumen::AMBANG_HARI + 1)->toDateString(), null)['Keadaan']);
    }

    // ── Penyaring ────────────────────────────────────────────────────────────

    public function test_penyaring_menangkap_yang_menagih_dan_melewatkan_yang_jauh(): void
    {
        $menagih = $this->dokumenBerlaku(now()->addDays(10)->toDateString(), now()->addYears(2)->toDateString());
        $jauh = $this->dokumenBerlaku(now()->addYears(1)->toDateString(), now()->addYears(2)->toDateString());

        $ids = $this->idsDari(SopPerhatianDokumen::terapkanTinjau($this->query()));

        $this->assertContains($menagih, $ids);
        $this->assertNotContains($jauh, $ids);
    }

    /** Yang belum punya versi berlaku tak boleh masuk daftar tanggal. */
    public function test_dokumen_tanpa_versi_berlaku_bukan_urusan_tinjauan(): void
    {
        $idDok = $this->svc()->buat([
            'Judul' => 'SOP UJI BELUM LENGKAP '.substr((string) microtime(true), -6),
            'Tipe' => 'DOKUMEN',
        ], 1);
        $this->svc()->tambahVersi($idDok, [], 1);

        $tinjau = $this->idsDari(SopPerhatianDokumen::terapkanTinjau($this->query()));
        $lengkap = $this->idsDari(SopPerhatianDokumen::terapkanBelumLengkap($this->query()));

        $this->assertNotContains($idDok, $tinjau, 'Draft ikut ditagih tanggal yang belum pernah berlaku.');
        $this->assertContains($idDok, $lengkap);
    }

    // ── Pil dan daftarnya harus sepakat ──────────────────────────────────────

    /**
     * PENJAGA UTAMANYA.
     *
     * Angka di pil dan jumlah baris di daftarnya lahir dari dua pemanggilan
     * berbeda. Selama keduanya membaca predikat yang sama, mereka sepakat;
     * begitu salah satunya ditulis ulang, pil mengatakan "3" sementara
     * daftarnya menampilkan lima — dan admin berhenti memercayai keduanya.
     */
    public function test_pil_dan_daftar_menjawab_pertanyaan_yang_sama(): void
    {
        $this->dokumenBerlaku(now()->addDays(10)->toDateString(), now()->addYears(2)->toDateString());
        $u = $this->adminAtauSkip();

        /* Ringkasan ikut di dalam muatan /current dan TIDAK terpengaruh
           penyaring tahap — ia selalu menghitung seluruh katalog. Itulah yang
           membuatnya bisa dibandingkan dengan total baris pintunya. */
        $daftar = $this->actingAs($u)
            ->getJson('/api/v1/sop/admin/dokumen/current?tahap=PERHATIAN&limit=200')
            ->assertOk()->json('result');
        $ringkas = $daftar['ringkasan'];

        $this->assertSame((int) $ringkas['PERHATIAN'], (int) $daftar['total'],
            'Pil dan daftarnya tak sepakat — salah satunya memakai aturan lain.');

        $this->assertSame($ringkas['BARU'] + $ringkas['DRAFT'], $ringkas['BELUM_LENGKAP'],
            'BELUM_LENGKAP bukan lagi gabungan dua tahap pertama.');
    }

    /** Daftar BELUM_LENGKAP juga harus sepadan dengan angkanya. */
    public function test_daftar_belum_lengkap_sepadan_dengan_pilnya(): void
    {
        $u = $this->adminAtauSkip();

        $daftar = $this->actingAs($u)
            ->getJson('/api/v1/sop/admin/dokumen/current?tahap=BELUM_LENGKAP&limit=200')
            ->assertOk()->json('result');

        $this->assertSame((int) $daftar['ringkasan']['BELUM_LENGKAP'], (int) $daftar['total']);
    }

    /**
     * Muatan daftar membawa PENILAIANNYA, bukan cuma tanggal mentah.
     *
     * Layar yang membandingkan tanggal sendiri memakai jam browser: satu admin
     * melihat lencana merah dan yang lain tidak, untuk baris yang sama.
     */
    public function test_muatan_membawa_keadaan_tinjauan_bukan_tanggal_mentah(): void
    {
        $idDok = $this->dokumenBerlaku(now()->subDays(2)->toDateString(), now()->addYears(2)->toDateString());
        $u = $this->adminAtauSkip();

        $rows = $this->actingAs($u)
            ->getJson('/api/v1/sop/admin/dokumen/current?tahap=PERHATIAN&limit=200')
            ->assertOk()->json('result.rows');

        $baris = collect($rows)->firstWhere('Judul', DB::table('N_SOP_Dokumen')
            ->where('Id_SOP_Dokumen', $idDok)->value('Judul'));

        $this->assertNotNull($baris, 'Dokumen yang tanggalnya sudah lewat tak muncul di pintunya sendiri.');
        $this->assertSame('TERLAMBAT', $baris['Tinjau_Keadaan']);
        $this->assertSame('REVIEW', $baris['Tinjau_Sebab']);
    }

    /**
     * Pil ini daftar KERJA: yang paling menagih di atas.
     *
     * Tanpa urutannya ia cuma penyaring, dan admin masih harus membuka satu
     * per satu untuk tahu mana yang duluan.
     */
    public function test_daftar_tinjauan_diurut_dari_yang_paling_menagih(): void
    {
        $paling = $this->dokumenBerlaku(now()->subDays(30)->toDateString(), now()->addYears(2)->toDateString());
        $this->dokumenBerlaku(now()->addDays(60)->toDateString(), now()->addYears(2)->toDateString());
        $u = $this->adminAtauSkip();

        $rows = $this->actingAs($u)
            ->getJson('/api/v1/sop/admin/dokumen/current?tahap=PERHATIAN&limit=200')
            ->assertOk()->json('result.rows');

        $judulPaling = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $paling)->value('Judul');

        $this->assertSame($judulPaling, $rows[0]['Judul'] ?? null,
            'Yang paling lama terlambat tidak berada di baris pertama.');
    }
}
