<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin menata urutan wizard dan penanda Kalibrasi Penilai.
 *
 * Keduanya kamus hidup (spec W5): perubahan di sini langsung mengubah wizard setiap penilai
 * pada siklus yang sedang berjalan. Karena itu haknya diperiksa per master, bukan sekadar
 * "boleh membuka layar admin".
 */
class KalibrasiPenilaiAdminTest extends TestCase
{
    use DatabaseTransactions;

    private const API = '/api/v1/performance-appraisal/master/jenis-penilai';

    private const HALAMAN = 'PaMasterKategoriPenilaiPage';

    /** Pengguna pertama yang memegang hak master kategori penilai. */
    private function admin(): User
    {
        return $this->cariPengguna(fn (User $u) => $u->memilikiJabatan(self::HALAMAN))
            ?? $this->markTestSkipped('Tidak ada pengguna berhak '.self::HALAMAN.'.');
    }

    /**
     * Pengguna yang LOLOS gerbang rute tetapi tidak memegang hak kategori penilai — satu-
     * satunya pengguna yang membuktikan pemeriksaan per master. Di data dev tidak ada orang
     * seperti itu (dicari di 500 akun pertama), jadi keadaannya DIBANGUN: pengguna tanpa hak
     * diberi hak master kompetensi di dalam transaksi uji.
     */
    private function pemegangMasterLain(): User
    {
        $user = $this->cariPengguna(fn (User $u) => ! $u->memilikiJabatan(self::HALAMAN))
            ?? $this->markTestSkipped('Tidak ada pengguna tanpa hak '.self::HALAMAN.'.');

        $contoh = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'ID_Level', 'ID_Divisi']);

        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => $contoh->Kode_Perusahaan,
            'ID_Level' => $contoh->ID_Level,
            'ID_Divisi' => $contoh->ID_Divisi,
            'Jenis_page' => 'PaMasterKompetensiPage',
            'UserID_Web' => $user->Id_Users,
            'Flag_Aktif' => 'Y',
        ]);

        // Hak dibaca sekali per proses lalu disimpan statis; tanpa ini baris di atas tak terlihat.
        foreach (['menuAccessCache', 'menuAccessLegacyCache'] as $cache) {
            $prop = new \ReflectionProperty(User::class, $cache);
            $prop->setAccessible(true);
            $prop->setValue(null, []);
        }

        $this->assertTrue($user->memilikiJabatan('PaMasterKompetensiPage'), 'Hak master kompetensi tidak terbaca.');
        $this->assertFalse($user->memilikiJabatan(self::HALAMAN), 'Pengguna uji ternyata berhak kategori penilai.');

        return $user;
    }

    private function cariPengguna(callable $cocok): ?User
    {
        $ids = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('UserID_Web')
            ->orderBy('UserID_Web')->limit(100)->pluck('UserID_Web');

        foreach ($ids as $id) {
            $user = User::find($id);

            if ($user !== null && $cocok($user)) {
                return $user;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function kodeAktif(): array
    {
        return DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')
            ->orderByRaw('CASE WHEN Urutan_Pengisian IS NULL THEN 1 ELSE 0 END')
            ->orderBy('Urutan_Pengisian')->orderBy('Id_PA_Rater_Category')
            ->pluck('Kode_Kategori')->map(fn ($k) => trim((string) $k))->all();
    }

    public function test_tata_urutan_menukar_posisi_dalam_satu_pernyataan(): void
    {
        $this->actingAs($this->admin());
        $urutan = $this->kodeAktif();
        $this->assertGreaterThan(1, count($urutan), 'Butuh sedikitnya dua kategori aktif.');
        $dibalik = array_reverse($urutan);

        // Membalik = setiap posisi berpindah ke posisi yang SEDANG dipakai kategori lain.
        // Satu UPDATE per kategori pasti menabrak indeks unik di tengah jalan.
        $this->putJson(self::API.'/urutan-kategori', ['urutan' => $dibalik])
            ->assertOk()
            ->assertJsonPath('result.0.kode', $dibalik[0])
            ->assertJsonPath('result.0.urutan_pengisian', 1);

        $this->assertSame($dibalik, $this->kodeAktif(), 'Urutan baru tidak tersimpan apa adanya.');
    }

    public function test_urutan_yang_tidak_lengkap_atau_berlebih_ditolak(): void
    {
        $this->actingAs($this->admin());
        $urutan = $this->kodeAktif();

        $this->putJson(self::API.'/urutan-kategori', ['urutan' => array_slice($urutan, 1)])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Urutan harus memuat setiap kategori penilai aktif tepat sekali. Belum ada: {$urutan[0]}."]);
        $this->putJson(self::API.'/urutan-kategori', ['urutan' => [...$urutan, 'TIDAK_ADA']])->assertStatus(422);
        $this->putJson(self::API.'/urutan-kategori', ['urutan' => [...$urutan, $urutan[0]]])->assertStatus(422);

        $this->assertSame($urutan, $this->kodeAktif(), 'Penolakan tidak boleh mengubah apa pun.');
    }

    public function test_penanda_kalibrasi_bisa_dinyalakan_dan_matriks_melaporkannya(): void
    {
        $this->actingAs($this->admin());
        $id = (int) DB::table('N_PA_Rater_Category')->where('Kode_Kategori', 'PEER')->value('Id_PA_Rater_Category');
        $this->assertGreaterThan(0, $id, 'Kategori PEER tidak ada.');

        $this->putJson(self::API."/kategori/{$id}/kalibrasi", ['boleh' => true])
            ->assertOk()
            ->assertJsonPath('result.boleh_kalibrasi', true);
        $this->assertSame('Y', DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $id)->value('Flag_Boleh_Kalibrasi'));

        $kategori = collect($this->getJson(self::API.'/matriks')->assertOk()->json('result.kategori'))->keyBy('kode');
        $this->assertTrue($kategori['PEER']['boleh_kalibrasi']);
        $this->assertArrayHasKey('urutan_pengisian', $kategori['PEER']);

        $this->putJson(self::API."/kategori/{$id}/kalibrasi", ['boleh' => false])->assertOk();
        $this->assertSame('T', DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $id)->value('Flag_Boleh_Kalibrasi'));
    }

    public function test_kategori_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->admin());
        $maks = (int) DB::table('N_PA_Rater_Category')->max('Id_PA_Rater_Category');

        $this->putJson(self::API.'/kategori/'.($maks + 1000).'/kalibrasi', ['boleh' => true])->assertStatus(422);
    }

    public function test_tanpa_hak_master_kategori_penilai_ditolak(): void
    {
        /* Gerbang rute menerima hak master MANA PUN. Tanpa pemeriksaan per master, pemegang
           hak kompetensi saja bisa menata ulang wizard seluruh penilai. */
        $this->actingAs($this->pemegangMasterLain());
        $urutan = $this->kodeAktif();
        $id = (int) DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->value('Id_PA_Rater_Category');
        $flag = DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $id)->value('Flag_Boleh_Kalibrasi');

        // Bukti ia LOLOS gerbang rute: matriks tidak diperiksa per master dan menjawab 200.
        $this->getJson(self::API.'/matriks')->assertOk();

        $this->putJson(self::API.'/urutan-kategori', ['urutan' => array_reverse($urutan)])->assertForbidden();
        $this->putJson(self::API."/kategori/{$id}/kalibrasi", ['boleh' => $flag !== 'Y'])->assertForbidden();

        $this->assertSame($urutan, $this->kodeAktif(), 'Urutan berubah walau ditolak.');
        $this->assertSame($flag, DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $id)->value('Flag_Boleh_Kalibrasi'));
    }
}
