<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Halaman wizard versi dokumen SOP.
 *
 * Gerbangnya SENGAJA memakai ulang `menuSopDokumenPage` — kunci yang sudah
 * dipakai Master Dokumen. Kunci baru menuntut baris page-access yang ditanam
 * manual lebih dulu, dan sampai itu terjadi halamannya menolak SEMUA orang.
 * Uji di sini mengunci dua sisi gerbang itu: yang tak punya kunci ditolak,
 * yang punya diterima.
 *
 * Akun uji DICARI dari HRIS_Page_Access, tidak dikarang — pola yang sama
 * dengan SopMonitoringKaryawanTokenTest.
 */
class SopWizardHalamanTest extends TestCase
{
    use DatabaseTransactions;

    private function adminDokumen(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage di database ini.');
        }

        return $u;
    }

    private function tanpaHak(): User
    {
        $punya = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')->pluck('UserID_Web');

        $u = User::query()->whereNotIn('Id_Users', $punya->all())->first();
        if (! $u) {
            $this->markTestSkipped('Semua user punya menuSopDokumenPage di database ini.');
        }

        return $u;
    }

    /**
     * Header X-Inertia membuat respons berupa JSON, bukan blade root yang
     * memanggil @vite — supaya test tak bergantung pada hasil `npm run build`.
     * Versinya ikut dikirim, kalau tidak Inertia membalas 409 (minta reload).
     */
    private function kepalaInertia(): array
    {
        $versi = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        return ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $versi];
    }

    private function dokumenUji(): string
    {
        $id = app(SopDokumenService::class)->buat([
            'Judul' => 'SOP UJI HALAMAN '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        return Hashids::connection('custom')->encode($id);
    }

    public function test_menolak_tanpa_hak_master_dokumen(): void
    {
        $this->actingAs($this->tanpaHak())
            ->get('/sop/admin/dokumen/'.$this->dokumenUji().'/versi', $this->kepalaInertia())
            ->assertStatus(403);
    }

    public function test_menerima_pemegang_menu_sop_dokumen(): void
    {
        $r = $this->actingAs($this->adminDokumen())
            ->get('/sop/admin/dokumen/'.$this->dokumenUji().'/versi', $this->kepalaInertia());

        $r->assertStatus(200);
        $r->assertJsonPath('component', 'LMS/SOP/Governance/Admin/SopVersiWizard');
    }

    public function test_membawa_identitas_dokumen_sebagai_prop(): void
    {
        /* Tanpa identitas awal, halaman terbuka kosong lalu mendadak terisi —
           dan admin sempat mengira ia salah membuka dokumen. */
        $r = $this->actingAs($this->adminDokumen())
            ->get('/sop/admin/dokumen/'.$this->dokumenUji().'/versi', $this->kepalaInertia());

        $r->assertStatus(200);
        $r->assertJsonPath('props.dokumen.Tipe', 'DOKUMEN');
        $r->assertJsonPath('props.idVersi', null);
    }

    /**
     * Baku masa berlaku ikut sebagai prop.
     *
     * Layar menghitung tanggal kadaluarsa dan jadwal review dari dua angka ini.
     * Kalau propnya hilang, komponen jatuh ke default 24/12 miliknya sendiri —
     * dan itu diam-diam mengabaikan penyetelan per jenis dokumen tanpa satu pun
     * galat yang menyebutkannya.
     */
    public function test_membawa_baku_masa_berlaku_sebagai_prop(): void
    {
        $r = $this->actingAs($this->adminDokumen())
            ->get('/sop/admin/dokumen/'.$this->dokumenUji().'/versi', $this->kepalaInertia());

        $r->assertStatus(200);
        $r->assertJsonPath('props.dokumen.Masa_Berlaku_Bulan', 24);
        $r->assertJsonPath('props.dokumen.Siklus_Review_Bulan', 12);
    }

    /**
     * Nomor gabungan ikut sebagai prop, bukan dirakit layar.
     *
     * Tanpa ketiganya kepala halaman hanya punya Nomor_SOP_Resmi — yang NULL
     * selama satu dari tujuh segmen belum terisi — lalu jatuh ke No_Dokumen,
     * yang cuma SATU segmen. Dokumen bernomor SK/HOL/IA/EHG/02/01/?? tampil
     * sebagai "1": angka yang tak pernah ada di dokumen mana pun.
     *
     * Yang diuji BENTUKNYA, bukan nilainya. Rumus tujuh segmen tinggal di
     * SopNomorDokumen, dan menuliskan nomor harapan di sini akan menyalin
     * rumus itu ke tempat kedua — persis yang dihindari rancangannya.
     */
    public function test_membawa_nomor_gabungan_sebagai_prop(): void
    {
        $r = $this->actingAs($this->adminDokumen())
            ->get('/sop/admin/dokumen/'.$this->dokumenUji().'/versi', $this->kepalaInertia());

        $r->assertStatus(200);

        $dok = $r->json('props.dokumen');

        foreach (['Nomor_Lengkap', 'Nomor_Pratinjau', 'Nomor_Segmen_Kosong'] as $kunci) {
            $this->assertArrayHasKey($kunci, $dok,
                "Prop {$kunci} hilang — layar kembali jatuh ke No_Dokumen, satu segmen saja.");
        }

        /* Pratinjau SELALU ada bentuknya, bahkan saat segmennya berlubang:
           itulah bedanya dengan Nomor_Lengkap yang null selama belum utuh. */
        $this->assertStringContainsString('/', (string) $dok['Nomor_Pratinjau'],
            'Pratinjau harus berbentuk tujuh segmen bertanda "/", bukan satu penggal.');

        /* Dua kolom yang saling menopang: nomor utuh berarti tak ada lubang,
           dan sebaliknya. Kalau keduanya bisa benar bersamaan, salah satunya
           dihitung dari sumber yang berbeda. */
        $this->assertSame(
            $dok['Nomor_Lengkap'] !== null,
            $dok['Nomor_Segmen_Kosong'] === [],
            'Nomor utuh dan daftar segmen kosong harus sepakat — keduanya dirakit '
            .'dari segmen yang sama.');
    }

    /**
     * Versi baru lahir KOSONG — muatan halaman tak membawa isi versi sebelumnya.
     *
     * Sampai 30 Agu 2026 formulir versi baru mengisi dirinya sendiri dari
     * v(n-1): matriks pengesah, berkas, dan paket ujian. Isian itu TERSIMPAN
     * pada penyimpanan pertama, dan sejak saat itu tak ada lagi yang
     * membedakannya dari pilihan sadar admin. Dua akibatnya nyata:
     *
     *   1. Gerbang REALISASI_PENGESAH (DML-2026-048) lolos sendiri di tiap
     *      revisi sesudah v00 — versi tercatat disahkan oleh orang-orang yang
     *      belum melihatnya.
     *   2. Riwayat versi melaporkan "berkas terlampir" pada versi yang berkasnya
     *      belum pernah dipilih siapa pun; revisi yang terbit tanpa disadari
     *      membawa BERKAS LAMA di bawah nomor revisi baru.
     *
     * Dijaga di PINTUNYA — muatan halaman. Selama isinya masih dikirim, ia akan
     * dirangkai ulang oleh pembaca berikutnya. Yang boleh tersisa hanya NOMOR
     * versi sebelumnya, dipakai untuk kalimat "melanjutkan dari v00".
     */
    public function test_versi_sebelumnya_hanya_membawa_nomornya(): void
    {
        $svc = app(SopDokumenService::class);

        $idDok = $svc->buat([
            'Judul' => 'SOP UJI WARISAN PENGESAH '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        /* Versi pertama sengaja dibuat SEKAYA mungkin: pengesah, berkas, dan
           tanggal. Fixture yang miskin akan lulus hanya karena tak ada yang bisa
           bocor, bukan karena kebocorannya tertutup. */
        $konten = DB::table('N_LMS_Master_Konten')->where('Scope', 'SOP')->value('Id_Master_Konten');

        $idVersi = $svc->tambahVersi($idDok, [
            'Id_Master_Konten' => $konten,
            'Tanggal_Berlaku' => now()->toDateString(),
            'Pengesah' => [
                ['Peran' => 'Disiapkan oleh', 'Nama' => 'Penyiap Uji', 'Jabatan' => 'STAF'],
                ['Peran' => 'Disahkan oleh', 'Nama' => 'Pengesah Uji', 'Jabatan' => 'KEPALA'],
            ],
        ], 1);
        $this->assertNotNull($idVersi, 'Versi fixture gagal lahir.');

        $r = $this->actingAs($this->adminDokumen())
            ->get('/sop/admin/dokumen/'.Hashids::connection('custom')->encode($idDok).'/versi',
                $this->kepalaInertia());

        $r->assertStatus(200);

        $prev = $r->json('props.infoVersi.Versi_Sebelumnya');

        /* Fixture harus benar-benar terbaca — tanpa ini test lulus hanya karena
           tak ada versi sebelumnya sama sekali, dan tak menguji apa pun. */
        $this->assertNotNull($prev, 'Versi sebelumnya tak terbaca; fixture-nya yang salah, bukan kodenya.');
        $this->assertSame(0, $prev['Versi_Ke']);

        /* Diuji sebagai DAFTAR TERTUTUP, bukan sekadar beberapa kunci terlarang.
           Menyebut yang dilarang satu per satu membuat kunci berikutnya yang
           ditambahkan lolos tanpa ada yang menyadarinya — dan justru penambahan
           itulah yang dulu melahirkan cacat ini. */
        $this->assertSame(['Versi_Ke'], array_keys($prev),
            'Muatan membawa lebih dari nomor versi sebelumnya. Apa pun yang ikut di sini '
            .'akan dipakai mengisi formulir versi baru oleh pembaca berikutnya, dan isian '
            .'yang tersimpan tanpa ada yang memilihnya membuat riwayat versi melaporkan '
            .'pengesah serta berkas yang tak pernah ada.');
    }

    public function test_dokumen_tak_dikenali_membalas_404(): void
    {
        $this->actingAs($this->adminDokumen())
            ->get('/sop/admin/dokumen/bukan-hashid/versi', $this->kepalaInertia())
            ->assertStatus(404);
    }
}
