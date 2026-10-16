<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopPrioritasService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Tingkat prioritas paket jadi master data.
 *
 * Yang membuat ini bukan sekadar "daftar nama" ada di kolom URUTAN: prioritas
 * punya TINGKATAN. Pengurutan dulu memakai CASE bernama ('KRITIS'→0,
 * 'TINGGI'→1, sisanya→2), jadi tingkat baru sekritis apa pun mendarat DI BAWAH
 * Normal.
 *
 * Metrik "SOP kritis belum selesai" SENGAJA tetap mencocokkan kode 'KRITIS' apa
 * adanya — penanda "hitung sebagai kritis" di master dicabut 15 Agu 2026 karena
 * akibatnya tak terlihat dari layar yang mencentangnya. Lihat skrip 38.
 *
 * Uji ini juga menjaga JALUR MUNDUR: selama DDL 35 belum dijalankan, seluruhnya
 * harus berperilaku persis seperti sebelum master ada.
 */
class SopMasterPrioritasTest extends TestCase
{
    use DatabaseTransactions;

    private SopPrioritasService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(SopPrioritasService::class);
        $this->svc->lupakan();
        URL::forceRootUrl('http://localhost');
    }

    /**
     * Pemegang kunci yang menjaga halaman ini.
     *
     * Sejak DML-2026-057 kuncinya tunggal: menuSopPrioritasPage. Sebelumnya
     * rute ini juga menerima menuSopKategoriPage sebagai tongkat, karena baris
     * page-access-nya belum ditanam. Tongkat itu sudah dilepas bersamaan dengan
     * pencabutan Kategori dari modul.
     */
    private function adminPrioritas(): User
    {
        $uid = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'menuSopPrioritasPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopPrioritasPage.');
        }

        return $u;
    }

    /** Halamannya benar-benar bisa dibuka, bukan cuma API-nya yang ada. */
    public function test_halaman_masternya_bisa_dibuka(): void
    {
        $this->actingAs($this->adminPrioritas())
            ->get('/sop/admin/prioritas')
            ->assertOk();
    }

    /* ================= YANG BERLAKU DI KEDUA KEADAAN ================= */

    /**
     * Tiga tingkat lama tetap ada dengan urutan yang sama — baik master sudah
     * ada maupun belum.
     *
     * Kalau ini merah, urutan papan risiko berubah pada hari yang sama tanpa
     * satu baris kode pun yang berubah.
     */
    public function test_tiga_tingkat_lama_tetap_utuh_dan_urutannya_tak_bergeser(): void
    {
        $kode = $this->svc->kode();

        foreach ([SopStatus::PRIORITAS_KRITIS, SopStatus::PRIORITAS_TINGGI, SopStatus::PRIORITAS_NORMAL] as $k) {
            $this->assertContains($k, $kode, "Tingkat {$k} hilang dari daftar yang sah.");
        }

        // Paling mendesak di depan — inilah yang dulu ditulis sebagai CASE 0/1/2.
        $urut = $this->svc->semua()->pluck('Kode')->all();
        $this->assertLessThan(array_search(SopStatus::PRIORITAS_TINGGI, $urut, true),
            array_search(SopStatus::PRIORITAS_KRITIS, $urut, true),
            'KRITIS tak lagi lebih mendesak daripada TINGGI.');
        $this->assertLessThan(array_search(SopStatus::PRIORITAS_NORMAL, $urut, true),
            array_search(SopStatus::PRIORITAS_TINGGI, $urut, true),
            'TINGGI tak lagi lebih mendesak daripada NORMAL.');

    }

    /** Paket baru tetap lahir dengan tingkat paling tidak mendesak. */
    public function test_bawaan_paket_baru_adalah_tingkat_paling_bawah(): void
    {
        $this->assertSame(SopStatus::PRIORITAS_NORMAL, $this->svc->kodeBawaan());
    }

    /**
     * CASE urutan memakai binding, bukan tempelan string.
     *
     * Kodenya memang milik kita, tapi "milik kita" bukan alasan menyusun SQL
     * dari isi tabel — dan tanda tanya yang jumlahnya tak sepadan dengan
     * bindingnya menghasilkan galat yang menunjuk ke tempat yang salah.
     */
    public function test_case_urutan_terikat_bukan_ditempel(): void
    {
        [$sql, $bind] = $this->svc->urutanCase('p.Prioritas');

        $this->assertSame(substr_count($sql, '?'), count($bind),
            'Jumlah tanda tanya tak sama dengan jumlah binding.');
        $this->assertStringContainsString('ELSE 9999', $sql,
            'Tingkat tak dikenal harus jatuh paling belakang, bukan menyusup ke atas.');
        $this->assertStringNotContainsString('KRITIS', $sql,
            'Kode tertempel langsung ke SQL.');
    }

    /** Papan risiko & daftar paket tetap bisa dijalankan dengan CASE dari master. */
    public function test_pengurutan_paket_menurut_prioritas_tetap_jalan(): void
    {
        [$rows] = app(\App\Services\LMS\SOP\SopPaketService::class)
            ->paginate(['sort' => 'prioritas'], 1, 10);

        $this->assertNotNull($rows, 'Pengurutan menurut prioritas gagal dijalankan.');
    }

    /* ================= HANYA SAAT MASTER SUDAH ADA ================= */

    /**
     * Master harus ADA sekaligus TERISI.
     *
     * Tabel yang ada tapi kosong bukan keadaan mustahil: skrip 35 membuat
     * tabelnya di batch berbeda dari yang mengisinya, dan versi pertamanya
     * memang pernah berhenti persis di antara keduanya. Service memperlakukan
     * master kosong sama dengan master belum ada — jatuh ke tiga tingkat
     * bawaan — jadi uji yang menuntut baris nyata harus SKIP, bukan merah:
     * merah di sini menunjuk cacat kode yang tidak ada.
     */
    private function butuhMaster(): void
    {
        if (! $this->svc->tersedia()) {
            $this->markTestSkipped('DDL 35 belum dijalankan di environment ini.');
        }
        if (DB::table(SopPrioritasService::TABEL)->doesntExist()) {
            $this->markTestSkipped('Tabel master prioritas ada tapi masih kosong — skrip 35 belum selesai dijalankan.');
        }
    }

    /**
     * Tingkat BARU yang lebih mendesak benar-benar naik ke atas.
     *
     * Inilah inti masalahnya. Dengan CASE bernama yang lama, tingkat apa pun di
     * luar KRITIS/TINGGI masuk kelompok "sisanya" — sehingga "SANGAT_TINGGI"
     * yang seharusnya paling atas justru duduk di bawah Normal, pada papan yang
     * dibaca justru untuk tahu mana yang harus dikejar lebih dulu.
     */
    public function test_tingkat_baru_yang_lebih_mendesak_naik_ke_atas(): void
    {
        $this->butuhMaster();

        DB::table(SopPrioritasService::TABEL)->insert([
            'Kode' => 'UJI_TERATAS', 'Nama' => 'Uji Teratas', 'Urutan' => 1,
            'Warna' => '#EF4444', 'Flag_Aktif' => SopStatus::AKTIF,
            'Created_At' => now(),
        ]);
        $this->svc->lupakan();

        $urut = $this->svc->semua()->pluck('Kode')->all();
        $this->assertSame('UJI_TERATAS', $urut[0],
            'Tingkat baru yang urutannya paling kecil tidak berada di paling depan.');

    }

    /** Tingkat baru langsung sah disimpan — validasinya membaca master. */
    public function test_tingkat_baru_langsung_diterima_validasi_paket(): void
    {
        $this->butuhMaster();

        DB::table(SopPrioritasService::TABEL)->insert([
            'Kode' => 'UJI_SEDANG', 'Nama' => 'Uji Sedang', 'Urutan' => 25,
            'Warna' => '#F59E0B', 'Flag_Aktif' => SopStatus::AKTIF,
            'Created_At' => now(),
        ]);
        $this->svc->lupakan();

        $this->assertContains('UJI_SEDANG', $this->svc->kode(),
            'Tingkat baru tak masuk daftar nilai yang sah, jadi setiap penyimpanan paket akan ditolak.');
    }

    public function test_tingkat_yang_sedang_dipakai_tak_bisa_dihapus(): void
    {
        $this->butuhMaster();

        $dipakai = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Prioritas')->value('Prioritas');
        if (! $dipakai) {
            $this->markTestSkipped('Tak ada paket yang memakai tingkat mana pun.');
        }

        $id = DB::table(SopPrioritasService::TABEL)->where('Kode', $dipakai)->value('Id_SOP_Prioritas');
        $token = Hashids::connection('custom')->encode((int) $id);

        $res = $this->actingAs($this->adminPrioritas())
            ->deleteJson("/api/v1/sop/admin/prioritas/delete/{$token}");

        $res->assertStatus(422);
        $this->assertStringContainsString('dipakai', (string) $res->json('message'));

        // Penolakan yang cuma di layar tak ada gunanya.
        $this->assertTrue(
            DB::table(SopPrioritasService::TABEL)->where('Id_SOP_Prioritas', $id)->exists(),
            'Barisnya terhapus padahal permintaannya ditolak.');
    }

    public function test_kode_tak_bisa_diubah_saat_sudah_dipakai(): void
    {
        $this->butuhMaster();

        $dipakai = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Prioritas')->value('Prioritas');
        if (! $dipakai) {
            $this->markTestSkipped('Tak ada paket yang memakai tingkat mana pun.');
        }

        $row = DB::table(SopPrioritasService::TABEL)->where('Kode', $dipakai)->first();
        $token = Hashids::connection('custom')->encode((int) $row->Id_SOP_Prioritas);

        $res = $this->actingAs($this->adminPrioritas())
            ->putJson("/api/v1/sop/admin/prioritas/update/{$token}", [
                'Kode' => 'KODE_BARU_UJI',
                'Nama' => $row->Nama,
                'Urutan' => (int) $row->Urutan,
                'Warna' => $row->Warna,
            ]);

        $res->assertStatus(422);
        $this->assertSame($dipakai,
            DB::table(SopPrioritasService::TABEL)->where('Id_SOP_Prioritas', $row->Id_SOP_Prioritas)->value('Kode'),
            'Kodenya berubah padahal ditolak — paket yang memakainya jadi menunjuk tingkat yang tak ada.');
    }

    /** Nama & warna tetap bebas diubah walau sedang dipakai — itu gunanya master. */
    public function test_nama_dan_warna_tetap_bisa_diubah_saat_dipakai(): void
    {
        $this->butuhMaster();

        $dipakai = DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Prioritas')->value('Prioritas');
        if (! $dipakai) {
            $this->markTestSkipped('Tak ada paket yang memakai tingkat mana pun.');
        }

        $row = DB::table(SopPrioritasService::TABEL)->where('Kode', $dipakai)->first();
        $token = Hashids::connection('custom')->encode((int) $row->Id_SOP_Prioritas);

        $this->actingAs($this->adminPrioritas())
            ->putJson("/api/v1/sop/admin/prioritas/update/{$token}", [
                'Kode' => $row->Kode,
                'Nama' => 'Nama Uji Baru',
                'Urutan' => (int) $row->Urutan,
                'Warna' => '#0EA5E9',
            ])->assertOk();

        $this->assertSame('Nama Uji Baru',
            DB::table(SopPrioritasService::TABEL)->where('Id_SOP_Prioritas', $row->Id_SOP_Prioritas)->value('Nama'));
    }

    public function test_kode_berspasi_ditolak(): void
    {
        $this->butuhMaster();

        $this->actingAs($this->adminPrioritas())
            ->postJson('/api/v1/sop/admin/prioritas/store', [
                'Kode' => 'Sangat Tinggi',
                'Nama' => 'Sangat Tinggi',
                'Urutan' => 5,
                'Warna' => '#EF4444',
            ])->assertStatus(422);

        $this->assertFalse(
            DB::table(SopPrioritasService::TABEL)->where('Kode', 'Sangat Tinggi')->exists(),
            'Kode berspasi tersimpan — "Sangat Tinggi" dan "SANGAT_TINGGI" akan jadi dua tingkat '
            .'berbeda yang tampak sama di layar.');
    }
}
