<?php

namespace Tests\Feature\SYSTEM\Governance\Authorization;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Data untuk perbaikan UI/UX Akses & Peran: "perlu perhatian", daftar anggota
 * modul, filter audit & riwayat peran, perpanjang batas waktu, konteks "Anda".
 */
class AksesPeranUxTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');
    }

    private function sa(): User
    {
        return User::where('Id_Users', 4)->firstOrFail();
    }

    private function karyawan(array $kecuali = []): User
    {
        $id = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')
            ->where(fn ($w) => $w->whereNull('u.Is_System_Admin')->orWhere('u.Is_System_Admin', '<>', 'Y'))
            ->whereNotExists(fn ($w) => $w->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'u.Id_Users'))
            ->whereNotIn('u.Id_Users', $kecuali ?: [0])
            ->orderBy('u.Id_Users')
            ->value('u.Id_Users');

        return User::where('Id_Users', $id)->firstOrFail();
    }

    private function idRole(string $kode): int
    {
        return (int) DB::table('N_HRIS_Role_Functional')->where('Role_Code', $kode)->value('Id_Role');
    }

    private function pegang(User $u, string $kode, array $tj = ['AKSES'], ?string $sampai = null): int
    {
        $id = DB::table('N_HRIS_Role_User')->insertGetId(
            [
                'UserId_Web' => $u->Id_Users,
                'Id_Role' => $this->idRole($kode),
                'Assigned_At' => now(),
                'Assigned_By' => 'UJI',
                'Flag_Aktif' => 'Y',
                'Berlaku_Sampai' => $sampai,
            ],
            'Id',
        );
        foreach ($tj as $k) {
            DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => $k]);
        }
        KeputusanAkses::lupakanCache();

        return $id;
    }

    public function test_ringkasan_memuat_daftar_perlu_perhatian(): void
    {
        $t = $this->karyawan();
        $this->pegang($t, 'VISITOR_ADMIN', [], now()->addDays(5)->toDateTimeString());

        $r = $this->actingAs($this->sa())
            ->getJson('/api/v1/akses-peran/ringkasan?konteks=visitor')
            ->assertOk()
            ->json('result.perlu_perhatian');

        $this->assertContains((int) $t->Id_Users, array_column($r['segera_kedaluwarsa'], 'id_user'));
        $this->assertContains(
            (int) $t->Id_Users,
            array_column($r['admin_tanpa_akses'], 'id_user'),
            'Admin tanpa tanggung jawab Akses tak ditandai.',
        );
        $this->assertContains('TAMU_DEPT_WAREHOUSE', array_column($r['peran_tanpa_pemegang'], 'kode'));
    }

    public function test_daftar_anggota_modul_dan_saringannya(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'DMS_ADMIN');

        $r = $this->actingAs($this->sa())
            ->getJson('/api/v1/akses-peran/pengguna?anggota=1&konteks=dms')
            ->assertOk()
            ->json('result');
        $this->assertContains((int) $a->Id_Users, array_column($r['data'], 'id_user'));
        $baris = collect($r['data'])->firstWhere('id_user', (int) $a->Id_Users);
        $this->assertSame('ADMIN', $baris['peran'][0]['tingkat']);

        $kosong = $this->actingAs($this->sa())
            ->getJson('/api/v1/akses-peran/pengguna?anggota=1&konteks=dms&tingkat=SUPER_ADMIN')
            ->assertOk()
            ->json('result.data');
        $this->assertNotContains((int) $a->Id_Users, array_column($kosong, 'id_user'));
    }

    public function test_audit_bisa_disaring_dan_riwayat_peran(): void
    {
        $role = $this->idRole('DMS_MENU_ADMINISTRATOR');
        DB::table('N_HRIS_Akses_Audit')->insert([
            'Waktu' => now(),
            'Oleh_Id_User' => 4,
            'Subjek_Jenis' => 'ROLE',
            'Subjek_Id' => (string) $role,
            'Aksi' => 'UBAH_HAK',
            'Nilai_Sebelum' => '[]',
            'Nilai_Sesudah' => '[]',
            'Sumber' => 'UI',
        ]);

        $r = $this->actingAs($this->sa())
            ->getJson("/api/v1/akses-peran/audit?konteks=dms&peran={$role}")
            ->assertOk()
            ->json('result.data');
        $this->assertNotEmpty($r);
        $this->assertSame(['UBAH_HAK'], array_values(array_unique(array_column($r, 'aksi'))));
        $saring = $this->actingAs($this->sa())
            ->getJson('/api/v1/akses-peran/audit?konteks=dms&aksi=CABUT_PERAN')
            ->assertOk()
            ->json('result.data');
        $this->assertSame([], array_values(array_diff(array_unique(array_column($saring, 'aksi')), ['CABUT_PERAN'])));
    }

    public function test_perpanjang_batas_penugasan(): void
    {
        $t = $this->karyawan();
        $id = $this->pegang($t, 'DMS_ADMIN', [], now()->addDays(3)->toDateTimeString());
        $baru = now()->addDays(60)->format('Y-m-d');

        $this->actingAs($this->sa())
            ->putJson("/api/v1/akses-peran/penugasan/{$id}/batas?konteks=dms", [
                'berlaku_sampai' => $baru,
                'alasan' => 'perpanjang dua bulan',
            ])
            ->assertOk();

        $this->assertStringStartsWith(
            $baru,
            (string) DB::table('N_HRIS_Role_User')->where('Id', $id)->value('Berlaku_Sampai'),
        );
        $this->assertSame(
            1,
            DB::table('N_HRIS_Akses_Audit')
                ->where('Aksi', 'UBAH_BATAS')
                ->where('Target_Id_User', $t->Id_Users)
                ->count(),
        );
        // Admin yang tak berwenang atas peran itu ditolak.
        $a = $this->karyawan([(int) $t->Id_Users]);
        $this->pegang($a, 'DMS_ADMIN');
        $this->actingAs($a)
            ->putJson("/api/v1/akses-peran/penugasan/{$id}/batas?konteks=dms", [
                'berlaku_sampai' => null,
                'alasan' => 'coba tanpa batas',
            ])
            ->assertForbidden();
    }

    public function test_halaman_modul_membawa_konteks_anda(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'DMS_SUPER_ADMIN');

        $anda = $this->actingAs($s)->get('/admin/dms/akses-peran')->assertOk()->viewData('page')['props']['lingkup'][
            'anda'
        ];
        $this->assertSame('SUPER_ADMIN', $anda['tingkat']);
        $this->assertTrue($anda['boleh_angkat']);
        $this->assertTrue($anda['boleh_ubah_matriks']);
    }

    public function test_daftar_admin_membawa_tingkat_dan_bisa_cabut(): void
    {
        $s = $this->karyawan();
        $this->pegang($s, 'DMS_SUPER_ADMIN');
        $a = $this->karyawan([(int) $s->Id_Users]);
        $this->pegang($a, 'DMS_ADMIN');

        $baris = collect(
            $this->actingAs($s)->getJson('/api/v1/akses-peran/admin?konteks=dms')->assertOk()->json('result'),
        )->keyBy('id_user');
        $this->assertSame('ADMIN', $baris[$a->Id_Users]['tingkat']);
        $this->assertTrue($baris[$a->Id_Users]['bisa_cabut']);
        $this->assertFalse($baris[$s->Id_Users]['bisa_cabut'], 'Diri sendiri tak bisa dicabut.');
    }

    /** Audit perubahan mode menyebut modulnya, bukan sekadar nomor. */
    public function test_audit_mode_menyebut_kode_modul(): void
    {
        $dms = (int) DB::table('N_HRIS_Master_Grup_Fitur')->where('Kode_Grup_Fitur', 'DMS')->value('Id_Grup_Fitur');
        $this->assertTrue(
            app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
                'MODE',
                (string) $dms,
                'UBAH_MODE',
                'BAYANGAN',
                'BARU',
                null,
                'uji label modul',
                'UI',
                (int) $this->sa()->Id_Users,
            ),
        );

        $baris = collect(
            $this->actingAs($this->sa())
                ->getJson('/api/v1/akses-peran/audit?aksi=UBAH_MODE')
                ->assertOk()
                ->json('result.data'),
        )->firstWhere('alasan', 'uji label modul');
        $this->assertNotNull($baris);
        $this->assertSame('DMS', $baris['subjek_label'] ?? null);
    }

    /** Ringkasan = dasbor: sebaran per tingkat, peran terbanyak, masa berlaku, tren & jenis perubahan. */
    public function test_ringkasan_memuat_statistik_dasbor(): void
    {
        $t = $this->karyawan();
        $this->pegang($t, 'DMS_SUPER_ADMIN', ['AKSES'], now()->addDays(10)->toDateTimeString());
        app(\App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses::class)->catat(
            'PENUGASAN',
            '1',
            'TUGASKAN_PERAN',
            null,
            ['DMS_SUPER_ADMIN'],
            (int) $t->Id_Users,
            'uji dasbor',
            'UI',
            (int) $this->sa()->Id_Users,
        );

        $s = $this->actingAs($this->sa())
            ->getJson('/api/v1/akses-peran/ringkasan?konteks=dms')
            ->assertOk()
            ->json('result.statistik');

        $this->assertGreaterThanOrEqual(1, $s['pemegang_unik']);
        $this->assertGreaterThanOrEqual(1, $s['per_tingkat']['SUPER_ADMIN'] ?? 0);
        $this->assertContains('DMS_SUPER_ADMIN', array_column($s['peran_teratas'], 'kode'));
        $this->assertGreaterThanOrEqual(1, $s['masa_berlaku']['maks_30']);
        $this->assertCount(30, $s['tren_audit']);
        $this->assertGreaterThanOrEqual(1, end($s['tren_audit'])['jumlah']);
        $this->assertGreaterThanOrEqual(1, $s['aksi_audit']['TUGASKAN_PERAN'] ?? 0);
    }

    /** Pilih karyawan: daftar bersaring + berhalaman, tanda "sudah memegang", dan semua id hasil saringan. */
    public function test_pilih_karyawan_bersaring_dan_menandai_pemegang(): void
    {
        $t = $this->karyawan();
        $this->pegang($t, 'DMS_ADMIN');
        $role = $this->idRole('DMS_ADMIN');
        $profil = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.UserID_Web', $t->Id_Users)
            ->first(['k.Kode_Karyawan', 'd.ID_Divisi']);

        $r = $this->actingAs($this->sa())
            ->getJson("/api/v1/akses-peran/pengguna/pilih?konteks=dms&cari={$profil->Kode_Karyawan}&id_role={$role}")
            ->assertOk()
            ->json('result');
        $baris = collect($r['data'])->firstWhere('id_user', (int) $t->Id_Users);
        $this->assertNotNull($baris);
        $this->assertTrue($baris['memegang']);
        $this->assertArrayHasKey('divisi', $baris);

        $belum = $this->actingAs($this->sa())
            ->getJson(
                "/api/v1/akses-peran/pengguna/pilih?konteks=dms&id_role={$role}&status=belum&divisi={$profil->ID_Divisi}",
            )
            ->assertOk()
            ->json('result');
        $this->assertNotContains((int) $t->Id_Users, array_column($belum['data'], 'id_user'));
        $this->assertLessThanOrEqual(25, count($belum['data']));

        $ids = $this->actingAs($this->sa())
            ->getJson("/api/v1/akses-peran/pengguna/pilih?konteks=dms&divisi={$profil->ID_Divisi}&semua_id=1")
            ->assertOk()
            ->json('result.id_user');
        $this->assertContains((int) $t->Id_Users, $ids);

        $opsi = $this->actingAs($this->sa())
            ->getJson('/api/v1/akses-peran/pengguna/opsi-saring?konteks=dms')
            ->assertOk()
            ->json('result');
        foreach (['divisi', 'departemen', 'lokasi', 'level', 'jabatan', 'status_kepegawaian'] as $k) {
            $this->assertArrayHasKey($k, $opsi);
        }
    }

    /** Penugasan massal: yang boleh ditugaskan, yang sudah memegang dilaporkan gagal — tak menggagalkan yang lain. */
    public function test_tugaskan_massal_melaporkan_per_karyawan(): void
    {
        $a = $this->karyawan();
        $b = $this->karyawan([(int) $a->Id_Users]);
        $c = $this->karyawan([(int) $a->Id_Users, (int) $b->Id_Users]);
        $this->pegang($c, 'DMS_ADMIN');
        $role = $this->idRole('DMS_ADMIN');

        $r = $this->actingAs($this->sa())
            ->postJson('/api/v1/akses-peran/penugasan/massal?konteks=dms', [
                'id_users' => [(int) $a->Id_Users, (int) $b->Id_Users, (int) $c->Id_Users],
                'id_role' => $role,
                'alasan' => 'uji penugasan massal',
            ])
            ->assertOk()
            ->json('result');

        $this->assertEqualsCanonicalizing(
            [(int) $a->Id_Users, (int) $b->Id_Users],
            array_column($r['berhasil'], 'id_user'),
        );
        $this->assertSame([(int) $c->Id_Users], array_column($r['gagal'], 'id_user'));
        $this->assertNotEmpty($r['gagal'][0]['alasan']);
        $this->assertSame(
            2,
            DB::table('N_HRIS_Role_User')
                ->where('Id_Role', $role)
                ->where('Flag_Aktif', 'Y')
                ->whereIn('UserId_Web', [$a->Id_Users, $b->Id_Users])
                ->count(),
        );

        // Admin modul lain tak boleh menugaskan peran DMS lewat jalur massal.
        $this->actingAs($this->sa())
            ->postJson('/api/v1/akses-peran/penugasan/massal?konteks=ess', [
                'id_users' => [(int) $a->Id_Users],
                'id_role' => $role,
                'alasan' => 'salah modul ya',
            ])
            ->assertForbidden();
    }

    public function test_tugaskan_massal_banyak_peran_sekaligus(): void
    {
        $a = $this->karyawan();
        $b = $this->karyawan([(int) $a->Id_Users]);
        $this->pegang($b, 'ADMIN_PERMIT');
        $menu = $this->idRole('ADMIN_PERMIT');
        $approval = $this->idRole('ADMIN_TAMU');
        $url = '/api/v1/akses-peran/penugasan/massal?konteks=visitor';

        $r = $this->actingAs($this->sa())
            ->postJson($url, [
                'id_users' => [(int) $a->Id_Users, (int) $b->Id_Users],
                'id_roles' => [$menu, $approval],
                'alasan' => 'uji banyak peran',
            ])
            ->assertOk()
            ->json('result');

        // 2 karyawan × 2 peran: hanya pasangan b+menu (sudah dipegang) yang dilewati.
        $this->assertCount(3, $r['berhasil']);
        $this->assertCount(1, $r['gagal']);
        $this->assertSame([(int) $b->Id_Users, $menu], [$r['gagal'][0]['id_user'], $r['gagal'][0]['id_role']]);
        $this->assertNotEmpty($r['gagal'][0]['peran']);

        // Super Admin tidak digabung dengan tingkat lain (Admin + Pengguna boleh).
        $this->actingAs($this->sa())
            ->postJson($url, [
                'id_users' => [(int) $a->Id_Users],
                'id_roles' => [$menu, $this->idRole('VISITOR_SUPER_ADMIN')],
                'alasan' => 'campur super admin',
            ])
            ->assertStatus(422);

        $c = $this->karyawan([(int) $a->Id_Users, (int) $b->Id_Users]);
        $r = $this->actingAs($this->sa())
            ->postJson($url, [
                'id_users' => [(int) $c->Id_Users],
                'id_roles' => [$approval, $this->idRole('VISITOR_ADMIN')],
                'tanggung_jawab' => ['AKSES'],
                'alasan' => 'admin dan pengguna',
            ])
            ->assertOk()
            ->json('result');
        $this->assertCount(2, $r['berhasil'], json_encode($r['gagal']));
    }

    public function test_template_cakupan_dan_antar_jenis_atau_sejenis(): void
    {
        $a = $this->karyawan();
        $b = $this->karyawan([(int) $a->Id_Users]);
        $profil = fn (User $u) => DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.UserID_Web', $u->Id_Users)
            ->first(['d.ID_Divisi', 'g.ID_Level']);
        $pa = $profil($a);
        $url = '/api/v1/akses-peran/cakupan-template?konteks=dms';

        // Divisi a DAN (level a ATAU level palsu) → mencakup a.
        $id = $this->actingAs($this->sa())
            ->postJson($url, [
                'nama' => 'Uji template',
                'syarat' => [
                    ['jenis' => 'DIVISI', 'nilai' => (string) $pa->ID_Divisi],
                    ['jenis' => 'LEVEL', 'nilai' => (string) $pa->ID_Level],
                    ['jenis' => 'LEVEL', 'nilai' => '-1'],
                ],
            ])
            ->assertOk()
            ->json('result.id');
        $cakupan = app(\App\Services\SYSTEM\Governance\Authorization\Scope\CakupanKaryawan::class);
        $this->assertTrue($cakupan->mencakup('TEMPLATE', (string) $id, (int) $b->Id_Users, (int) $a->Id_Users));

        // Ubah: divisi a DAN level palsu → tidak mencakup a.
        $this->actingAs($this->sa())
            ->putJson("/api/v1/akses-peran/cakupan-template/{$id}?konteks=dms", [
                'nama' => 'Uji template',
                'syarat' => [
                    ['jenis' => 'DIVISI', 'nilai' => (string) $pa->ID_Divisi],
                    ['jenis' => 'LEVEL', 'nilai' => '-1'],
                ],
            ])
            ->assertOk();
        $this->assertFalse($cakupan->mencakup('TEMPLATE', (string) $id, (int) $b->Id_Users, (int) $a->Id_Users));
        $this->assertTrue(
            $cakupan->mencakup('KARYAWAN', (string) $a->Id_Users, (int) $b->Id_Users, (int) $a->Id_Users),
        );

        $daftar = $this->actingAs($this->sa())->getJson($url)->assertOk()->json('result');
        $this->assertTrue($daftar['bisa_buat']);
        $this->assertContains($id, array_column($daftar['data'], 'id'));

        // Dipakai saat penugasan; label terbaca di daftar penugasan.
        $this->actingAs($this->sa())
            ->postJson('/api/v1/akses-peran/penugasan?konteks=dms', [
                'id_user' => (int) $b->Id_Users,
                'id_role' => $this->idRole('DMS_ADMIN'),
                'cakupan' => [['jenis' => 'TEMPLATE', 'nilai' => (string) $id]],
                'alasan' => 'uji template cakupan',
            ])
            ->assertOk();

        // Nonaktif → tak bisa dipakai lagi.
        $this->actingAs($this->sa())
            ->postJson("/api/v1/akses-peran/cakupan-template/{$id}/nonaktif?konteks=dms", ['alasan' => 'tidak dipakai'])
            ->assertOk();
        $this->actingAs($this->sa())
            ->postJson('/api/v1/akses-peran/penugasan?konteks=dms', [
                'id_user' => (int) $a->Id_Users,
                'id_role' => $this->idRole('DMS_ADMIN'),
                'cakupan' => [['jenis' => 'TEMPLATE', 'nilai' => (string) $id]],
                'alasan' => 'uji template nonaktif',
            ])
            ->assertForbidden();
    }

    public function test_angkat_admin_otomatis_bertanggung_jawab_akses(): void
    {
        $a = $this->karyawan();
        $this->actingAs($this->sa())
            ->postJson('/api/v1/akses-peran/penugasan?konteks=dms', [
                'id_user' => (int) $a->Id_Users,
                'id_role' => $this->idRole('DMS_ADMIN'),
                'alasan' => 'angkat admin tanpa centang',
            ])
            ->assertOk();

        $id = DB::table('N_HRIS_Role_User')
            ->where('UserId_Web', $a->Id_Users)
            ->where('Id_Role', $this->idRole('DMS_ADMIN'))
            ->where('Flag_Aktif', 'Y')
            ->value('Id');
        $this->assertSame(
            ['AKSES'],
            DB::table('N_HRIS_Role_User_Tanggung_Jawab')
                ->where('Id_Role_User', $id)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Kode')
                ->all(),
        );
    }

    public function test_isi_peran_peta_halaman_dan_peran_kembar(): void
    {
        $menu = $this->idRole('HC_OPS_ADMIN');
        $approval = $this->idRole('GA_PHL_ADMIN');
        $set = fn (int $id) => DB::table('N_HRIS_Role_Hak')
            ->where('Id_Role', $id)
            ->where('Flag_Aktif', 'Y')
            ->get(['Jenis_page', 'Id_Aksi'])
            ->map(fn ($h) => strtolower($h->Jenis_page).'#'.$h->Id_Aksi)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $isi = $this->actingAs($this->sa())
            ->getJson("/api/v1/akses-peran/peran/isi?konteks=ess&ids[]={$menu}")
            ->assertOk()
            ->json('result');
        $this->assertSame($menu, $isi[0]['id']);
        $this->assertCount(
            count(array_unique(array_map(fn ($x) => explode('#', $x)[0], $set($menu)))),
            $isi[0]['halaman'],
        );

        // Kembar = himpunan hak persis sama.
        $peran = collect(
            $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/peran?konteks=ess')->assertOk()->json('result'),
        );
        $kembarMenu = array_column($peran->firstWhere('id', $menu)['kembar'], 'id');
        $this->assertSame($set($menu) === $set($approval), in_array($approval, $kembarMenu, true));

        // Satu hak tambahan → tidak kembar lagi.
        $lain = DB::table('N_HRIS_Menus')
            ->whereNotIn('Jenis_page', DB::table('N_HRIS_Role_Hak')->where('Id_Role', $menu)->pluck('Jenis_page'))
            ->value('Jenis_page');
        DB::table('N_HRIS_Role_Hak')->insert([
            'Id_Role' => $menu,
            'Jenis_page' => $lain,
            'Id_Aksi' => 1,
            'Flag_Aktif' => 'Y',
        ]);
        $peran = collect(
            $this->actingAs($this->sa())->getJson('/api/v1/akses-peran/peran?konteks=ess')->assertOk()->json('result'),
        );
        $this->assertNotContains($approval, array_column($peran->firstWhere('id', $menu)['kembar'], 'id'));

        $peta = collect(
            $this->actingAs($this->sa())
                ->getJson('/api/v1/akses-peran/halaman?konteks=ess')
                ->assertOk()
                ->json('result'),
        );
        $baris = $peta->first(fn ($h) => strtolower($h['jenis_page']) === strtolower($lain));
        $this->assertNotNull($baris);
        $this->assertContains($menu, array_column($baris['peran'], 'id'));
    }

    public function test_super_admin_modul_otomatis_berhak_penuh_atas_modulnya(): void
    {
        $u = $this->karyawan();
        $this->pegang($u, 'DMS_SUPER_ADMIN');
        $peran = $this->idRole('DMS_SUPER_ADMIN');
        $dms = (int) DB::table('N_HRIS_Role_Functional')->where('Id_Role', $peran)->value('Id_Grup_Fitur');
        // Kosongkan matriks Super Admin: haknya tetap penuh karena tingkatnya.
        DB::table('N_HRIS_Role_Hak')
            ->where('Id_Role', $peran)
            ->update(['Flag_Aktif' => 'T']);
        $halaman = DB::table('N_HRIS_Grup_Fitur_Map')
            ->where('Id_Grup_Fitur', $dms)
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'not like', '%AksesPeran%')
            ->value('Jenis_page');
        $lain = DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'gfm.Jenis_page')
            ->where('gfm.Id_Grup_Fitur', '<>', $dms)
            ->where('gfm.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('m.Is_Global_Menu')->orWhere('m.Is_Global_Menu', '<>', 'Y'))
            ->value('gfm.Jenis_page');
        \App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses::lupakanCache();
        KeputusanAkses::lupakanCache();
        $k = app(KeputusanAkses::class);

        $this->assertTrue($k->boleh($u, $halaman, 'VIEW'));
        $this->assertTrue($k->boleh($u, $halaman, 'DELETE'));
        $this->assertFalse($k->boleh($u, $lain, 'VIEW'), 'Super Admin DMS tak boleh membuka halaman modul lain.');

        $isi = $this->actingAs($this->sa())
            ->getJson("/api/v1/akses-peran/peran/isi?konteks=dms&ids[]={$peran}")
            ->assertOk()
            ->json('result.0');
        $this->assertTrue($isi['otomatis']);
        $this->assertNotEmpty($isi['halaman']);
    }

    public function test_rincian_orang_halaman_dan_cabut_grant(): void
    {
        $a = $this->karyawan();
        $this->pegang($a, 'DMS_ADMIN');

        $r = $this->actingAs($this->sa())
            ->getJson('/api/v1/akses-peran/halaman/orang?konteks=dms&halaman=menuSopDokumenPage')
            ->assertOk()
            ->json('result');

        $this->assertSame('menuSopDokumenPage', $r['jenis_page']);
        $this->assertNotEmpty($r['orang_peran']);
        $this->assertContains((int) $a->Id_Users, array_column($r['orang_peran'], 'id_user'));
    }

    public function test_helper_user_bisa_memeriksa_kemampuan_fitur_bisnis(): void
    {
        $sa = $this->sa();
        $this->assertTrue($sa->bisa('ESS.CUTI.AJUKAN'));
        $this->assertTrue($sa->bisa('VISITOR.PERMIT.ADMIN'));
        $this->assertFalse($sa->bisa('CAPABILITY.TIDAK.TERDAFTAR'));

        $uEss = $this->karyawan();
        $this->pegang($uEss, 'ESS_SUPER_ADMIN');
        $this->assertTrue($uEss->bisa('ESS.CUTI.AJUKAN'));
        $this->assertTrue($uEss->bisa('ESS.CUTI.VERIFIKASI'));
        $this->assertFalse($uEss->bisa('VISITOR.PERMIT.ADMIN'), 'Super Admin ESS tak punya wewenang modul VISITOR');

        $uVerif = $this->karyawan([(int) $uEss->Id_Users]);
        $this->pegang($uVerif, 'ADMIN_HC_CUTI_VERIFIKATOR');
        $this->assertTrue($uVerif->bisa('ESS.CUTI.VERIFIKASI'));
        $this->assertFalse($uVerif->bisa('ESS.CUTI.ADMIN'), 'Verifikator cuti tak punya wewenang admin kuota cuti');

        $plain = $this->karyawan([(int) $uEss->Id_Users, (int) $uVerif->Id_Users]);
        $this->assertFalse($plain->bisa('ESS.CUTI.VERIFIKASI'));
    }
}
