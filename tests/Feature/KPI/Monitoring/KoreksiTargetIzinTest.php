<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kuasa mengubah angka penilaian harus terpisah dari kuasa memantaunya.
 *
 * Di sistem ini sebuah role memberi SELURUH halaman grup fiturnya sekaligus —
 * granularitasnya nol. Karena itu kunci `kpiKoreksiTarget` sengaja TIDAK
 * didaftarkan di N_HRIS_Grup_Fitur_Map: menaruhnya di grup KPI berarti
 * memberikannya kepada setiap pemegang role KPI mana pun, yang persis
 * memusnahkan pemisahan yang jadi tujuannya.
 *
 * Pemberiannya lewat HRIS_Page_Access per orang — satu-satunya jalur di sistem
 * ini yang bisa memberi izin kepada orang tertentu saja.
 */
class KoreksiTargetIzinTest extends TestCase
{
    use DatabaseTransactions;

    private function userContoh(): ?User
    {
        return User::query()->whereNotNull('Id_Users')->orderBy('Id_Users')->first();
    }

    /**
     * Kosongkan cache izin yang hidup di properti STATIS User.
     *
     * Cache itu diisi pada panggilan memilikiJabatan() pertama dan bertahan
     * lintas uji dalam satu proses PHPUnit. Tanpa dikosongkan, baris izin yang
     * baru disisipkan tidak akan pernah terbaca dan ujinya gagal karena cache,
     * bukan karena izinnya.
     *
     * Di produksi hal yang sama berarti: pemberian izin baru berlaku pada
     * permintaan BERIKUTNYA, bukan pada permintaan yang sedang berjalan.
     */
    private function lupakanCacheIzin(): void
    {
        foreach (['menuAccessCache', 'menuAccessLegacyCache'] as $nama) {
            $prop = new \ReflectionProperty(User::class, $nama);
            $prop->setAccessible(true);
            $prop->setValue(null, []);
        }
    }

    /**
     * Sasarannya PEMEGANG PERAN PEMANTAU yang sebenarnya, bukan user sembarangan.
     *
     * Menguji dengan user acak hanya membuktikan "orang tanpa izin ditolak" —
     * klaim yang jauh lebih lemah dari judulnya, dan yang tetap hijau meski
     * kunci rutenya diganti jadi kpiEnforcementMonitoring. Yang harus dibuktikan
     * adalah bahwa memantau TIDAK memberi kuasa mengubah.
     */
    public function test_pemegang_peran_pemantau_tetap_ditolak(): void
    {
        $this->lupakanCacheIzin();

        $idPemantau = DB::table('HRIS_Page_Access')
            ->where('Jenis_page', 'kpiEnforcementMonitoring')
            ->where('Flag_Aktif', 'Y')
            ->value('UserID_Web');

        $user = $idPemantau ? User::find($idPemantau) : null;

        if (! $user) {
            $this->markTestSkipped('Tidak ada pemegang kpiEnforcementMonitoring di data dev.');
        }

        $this->assertTrue(
            $user->memilikiJabatan('kpiEnforcementMonitoring'),
            'Prasyarat: user ini harus benar-benar pemegang peran pemantau.',
        );

        if ($user->memilikiJabatan('kpiKoreksiTarget')) {
            $this->markTestSkipped('Pemantau ini sudah diberi izin koreksi secara eksplisit.');
        }

        $this->actingAs($user)
            ->postJson('/api/v1/kpi-enforcement/target/koreksi', [
                'sebab' => 'coba',
                'perubahan' => [['id_pengajuan_det' => 1, 'nilai_baru' => 1]],
            ])
            ->assertStatus(403);
    }

    public function test_pemegang_izin_eksplisit_diterima(): void
    {
        $user = $this->userContoh();

        if (! $user) {
            $this->markTestSkipped('Tidak ada user di data dev.');
        }

        // Bentuk barisnya dipelajari dari baris SIAPA PUN, lalu ditulis untuk
        // user kita. Tabelnya punya Kode_Perusahaan/ID_Level/ID_Divisi yang ikut
        // menentukan bentuk baris yang sah, dan menebaknya membuat uji gagal
        // karena alasan yang tak ada hubungannya dengan izin.
        //
        // Sumber bentuk sengaja DIPISAH dari user sasaran: menuntut user contoh
        // sudah punya barisnya sendiri membuat uji ini melewati dirinya sendiri
        // di data dev — hijau di laporan, nol yang dibuktikan.
        $contoh = DB::table('HRIS_Page_Access')->first();

        if (! $contoh) {
            $this->markTestSkipped('Tabel HRIS_Page_Access kosong; bentuk barisnya tak bisa dipelajari.');
        }

        $baris = (array) $contoh;
        unset($baris['Id_Page_Access']);
        $baris['UserID_Web'] = $user->Id_Users;
        $baris['Jenis_page'] = 'kpiKoreksiTarget';
        $baris['Flag_Aktif'] = 'Y';

        DB::table('HRIS_Page_Access')->insert($baris);
        $this->lupakanCacheIzin();

        $res = $this->actingAs($user)
            ->getJson('/api/v1/kpi-enforcement/target/kelayakan?ids[]=999999999');

        $this->assertNotSame(403, $res->status(), 'Izin eksplisit lewat HRIS_Page_Access harus diterima.');
    }

    public function test_kunci_izin_tidak_terdaftar_di_grup_fitur(): void
    {
        $this->assertSame(
            0,
            DB::table('N_HRIS_Grup_Fitur_Map')->where('Jenis_page', 'kpiKoreksiTarget')->count(),
            'kpiKoreksiTarget di grup fitur berarti setiap pemegang role KPI ikut dapat kuasa mengubah target.',
        );
    }

    /**
     * Peringatan menyimpang harus SAMPAI ke pemanggil sebelum koreksi terpasang.
     *
     * Mekanismenya sempat ada, teruji, dan dilihat merah — tapi tidak pernah
     * dipanggil siapa pun, sehingga peringatannya tidak akan pernah terlihat.
     * Penjaga ini menguji jalur HTTP-nya, bukan fungsinya, supaya sambungan itu
     * tidak bisa lepas lagi tanpa ketahuan.
     */
    public function test_endpoint_memperingatkan_nilai_menyimpang_sebelum_menerapkan(): void
    {
        $user = $this->userContoh();

        if (! $user) {
            $this->markTestSkipped('Tidak ada user di data dev.');
        }

        $contoh = DB::table('HRIS_Page_Access')->first();

        if (! $contoh) {
            $this->markTestSkipped('Tabel HRIS_Page_Access kosong.');
        }

        $baris = (array) $contoh;
        unset($baris['Id_Page_Access']);
        $baris['UserID_Web'] = $user->Id_Users;
        $baris['Jenis_page'] = 'kpiKoreksiTarget';
        $baris['Flag_Aktif'] = 'Y';
        DB::table('HRIS_Page_Access')->insert($baris);
        $this->lupakanCacheIzin();

        $t = DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->where('p.Flag_Aktif', 'Y')->where('p.Flag_Approval', 'Y')->whereNull('p.Status')
            ->where('det.Nilai_Target', '>', 0)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail as upd')
                ->whereColumn('upd.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail_Sementara as sm')
                ->whereColumn('sm.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Pengajuan_Det as lain')
                ->whereColumn('lain.Id_Pengajuan_Detail', 'det.Id_Pengajuan_Detail')
                ->whereColumn('lain.Id_Pengajuan_Det', '<>', 'det.Id_Pengajuan_Det')
                ->where('lain.Nilai_Target', '>', 0))
            ->orderByDesc('det.Id_Pengajuan_Det')
            ->select('det.Id_Pengajuan_Det', 'det.Nilai_Target')
            ->first();

        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih yang punya saudara sebaris berteget positif.');
        }

        $ekstrem = (float) $t->Nilai_Target * 1000;
        $muatan = [
            'sebab' => 'uji peringatan',
            'perubahan' => [['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => $ekstrem]],
        ];

        $res = $this->actingAs($user)->postJson('/api/v1/kpi-enforcement/target/koreksi', $muatan);

        $res->assertStatus(422);
        $this->assertNotEmpty(
            $res->json('result.peringatan'),
            'Nilai 1000x median harus diperingatkan sebelum diterapkan.',
        );
        $this->assertSame(
            (float) $t->Nilai_Target,
            (float) DB::table('KPI_Pengajuan_Det')->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)->value('Nilai_Target'),
            'Selama peringatan belum diakui, nilainya TIDAK boleh berubah.',
        );

        // Diakui sekali -> nilainya diterima. Memperingatkan, bukan menolak.
        $this->actingAs($user)
            ->postJson('/api/v1/kpi-enforcement/target/koreksi?abaikan_peringatan=1', $muatan)
            ->assertStatus(200);

        $this->assertSame(
            $ekstrem,
            (float) DB::table('KPI_Pengajuan_Det')->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)->value('Nilai_Target'),
        );
    }
}
