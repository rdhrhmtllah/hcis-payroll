<?php

namespace Tests\Feature\SYSTEM\Application;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Panduan\PanduanService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Manajemen panduan (DDL-2026-146): siapa melihat panduan apa, dan berkasnya
 * hanya terbuka bagi yang berhak.
 *
 * DDL modul ini dijalankan manual (tak ada Laravel migration), jadi tes skema
 * di sini satu-satunya bukti otomatis bahwa tabelnya ada di basis data aktif.
 */
class PanduanTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): PanduanService
    {
        return app(PanduanService::class);
    }

    /**
     * Pengguna tiruan: bisa membuka halaman di $halaman saja. Turunan User
     * karena keputusan halaman lewat KeputusanAkses (RBAC Fase 4), yang di
     * mode LAMA bertanya ke memilikiJabatan.
     */
    private function pengguna(array $halaman, ?int $idUsers = null): User
    {
        $u = new class extends User
        {
            public array $halamanTiruan = [];

            public function memilikiJabatan($page): bool
            {
                return in_array($page, $this->halamanTiruan, true);
            }
        };
        $u->halamanTiruan = $halaman;
        $u->Id_Users = $idUsers;

        return $u;
    }

    /** Dua Jenis_page nyata dari daftar menu (pemetaan memvalidasinya). */
    private function duaHalaman(): array
    {
        $halaman = DB::table('N_HRIS_Menus')->orderBy('Jenis_page')->limit(2)->pluck('Jenis_page')->all();
        $this->assertCount(2, $halaman, 'N_HRIS_Menus kosong.');

        return $halaman;
    }

    private function buatPanduan(string $judul = 'Panduan Uji', string $aktif = 'Y'): int
    {
        return (int) DB::table(PanduanService::T_PANDUAN)->insertGetId([
            'Judul' => $judul,
            'Path_File' => 'panduan/uji/'.uniqid().'.pdf',
            'Nama_File_Asli' => 'uji.pdf',
            'Flag_Aktif' => $aktif,
            'Created_By' => 'UJI',
        ], 'Id_Panduan');
    }

    private function petakan(int $idPanduan, string $page, ?int $idRole = null, ?int $mulai = null): void
    {
        DB::table(PanduanService::T_HALAMAN)->insert([
            'Id_Panduan' => $idPanduan,
            'Jenis_page' => $page,
            'Halaman_Mulai' => $mulai,
            'Id_Role' => $idRole,
            'Urutan_Tampil' => 0,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);
        $this->svc()->lupakanCache();
    }

    /** Pengguna web yang punya role aktif, berikut satu role yang TIDAK ia punya. */
    private function penggunaBerRole(): array
    {
        $baris = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->where('ru.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->select('ru.UserId_Web', 'ru.Id_Role')
            ->first();
        if (! $baris) {
            $this->markTestSkipped('Tidak ada pengguna ber-role aktif di basis data ini.');
        }

        $roleLain = DB::table('N_HRIS_Role_Functional')
            ->whereNotIn('Id_Role', DB::table('N_HRIS_Role_User')->where('UserId_Web', $baris->UserId_Web)->where('Flag_Aktif', 'Y')->select('Id_Role'))
            ->value('Id_Role');

        return [(int) $baris->UserId_Web, (int) $baris->Id_Role, $roleLain !== null ? (int) $roleLain : null];
    }

    /* ── Skema ──────────────────────────────────────────────────────────── */

    public function test_tabel_panduan_ada(): void
    {
        foreach ([
            PanduanService::T_PANDUAN => ['Id_Panduan', 'Judul', 'Deskripsi', 'Versi', 'Path_File', 'Nama_File_Asli', 'Ukuran_Byte', 'Flag_Aktif'],
            PanduanService::T_HALAMAN => ['Id_Panduan_Halaman', 'Id_Panduan', 'Jenis_page', 'Halaman_Mulai', 'Id_Role', 'Urutan_Tampil', 'Flag_Aktif'],
            PanduanService::T_ROLE => ['Id_Panduan_Role', 'Id_Panduan', 'Id_Role', 'Halaman_Mulai', 'Urutan_Tampil', 'Flag_Aktif'],
        ] as $tabel => $wajib) {
            $kolom = array_map(fn ($r) => $r->COLUMN_NAME, DB::select('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ?', [$tabel]));
            $this->assertNotEmpty($kolom, "Tabel {$tabel} belum ada — jalankan DDL-2026-146 dan DDL-2026-148 (database/ddl/).");
            foreach ($wajib as $k) {
                $this->assertContains($k, $kolom, "Kolom {$k} hilang di {$tabel}.");
            }
        }
    }

    /* ── Siapa melihat apa ──────────────────────────────────────────────── */

    public function test_panduan_tampil_hanya_di_halaman_yang_bisa_dibuka_pengguna(): void
    {
        [$a, $b] = $this->duaHalaman();
        $id = $this->buatPanduan();
        $this->petakan($id, $a, null, 4);

        $bisa = $this->pengguna([$a]);
        $tidak = $this->pengguna([$b]);

        $hasil = $this->svc()->untukHalaman($bisa, $a);
        $this->assertSame([$id], array_column($hasil, 'id'));
        $this->assertSame(4, $hasil[0]['halaman_mulai']);

        $this->assertSame([], $this->svc()->untukHalaman($tidak, $a), 'Pengguna tanpa akses halaman ikut melihat panduannya.');
        $this->assertSame([], $this->svc()->untukHalaman($bisa, $b), 'Panduan bocor ke halaman yang tidak dipetakan.');
    }

    public function test_halaman_mulai_kosong_berarti_halaman_satu(): void
    {
        [$a] = $this->duaHalaman();
        $id = $this->buatPanduan();
        $this->petakan($id, $a);

        $this->assertSame(1, $this->svc()->untukHalaman($this->pengguna([$a]), $a)[0]['halaman_mulai']);
    }

    public function test_panduan_nonaktif_tidak_tampil_dan_berkasnya_tertutup(): void
    {
        [$a] = $this->duaHalaman();
        $id = $this->buatPanduan('Nonaktif', 'T');
        $this->petakan($id, $a);
        $pengguna = $this->pengguna([$a]);

        $this->assertSame([], $this->svc()->untukHalaman($pengguna, $a));
        $this->assertFalse($this->svc()->bolehBukaBerkas($pengguna, $id));
        $this->assertNull($this->svc()->berkas($id));
        $this->assertNotNull($this->svc()->berkasAdmin($id), 'Admin tetap harus bisa meninjau panduan nonaktif.');
    }

    public function test_role_khusus_menyaring_pengguna_yang_tidak_memegangnya(): void
    {
        [$a] = $this->duaHalaman();
        [$idWeb, $rolePunya, $roleLain] = $this->penggunaBerRole();
        if ($roleLain === null) {
            $this->markTestSkipped('Pengguna uji memegang semua role.');
        }

        $untukRolenya = $this->buatPanduan('Untuk rolenya');
        $untukRoleLain = $this->buatPanduan('Untuk role lain');
        $umum = $this->buatPanduan('Umum');
        $this->petakan($untukRolenya, $a, $rolePunya);
        $this->petakan($untukRoleLain, $a, $roleLain);
        $this->petakan($umum, $a);

        $pengguna = $this->pengguna([$a], $idWeb);
        $ids = array_column($this->svc()->untukHalaman($pengguna, $a), 'id');

        $this->assertContains($untukRolenya, $ids);
        $this->assertContains($umum, $ids);
        $this->assertNotContains($untukRoleLain, $ids, 'Panduan role lain tampil.');

        $this->assertTrue($this->svc()->bolehBukaBerkas($pengguna, $untukRolenya));
        $this->assertFalse($this->svc()->bolehBukaBerkas($pengguna, $untukRoleLain), 'Berkas role lain bisa dibuka lewat Id.');
    }

    public function test_berkas_tertutup_bila_tidak_dipetakan_ke_halaman_yang_bisa_dibuka(): void
    {
        [$a, $b] = $this->duaHalaman();
        $id = $this->buatPanduan();
        $this->petakan($id, $a);

        $this->assertTrue($this->svc()->bolehBukaBerkas($this->pengguna([$a]), $id));
        $this->assertFalse($this->svc()->bolehBukaBerkas($this->pengguna([$b]), $id));
        $this->assertFalse($this->svc()->bolehBukaBerkas($this->pengguna([$a]), $this->buatPanduan('Belum dipetakan')));
    }

    /* ── Pengelolaan ────────────────────────────────────────────────────── */

    private function pdf(string $nama = 'manual.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 12, 'application/pdf');
    }

    public function test_panduan_baru_langsung_dipetakan_ke_halaman_yang_dipilih(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a, $b] = $this->duaHalaman();

        $baru = $this->svc()->buat(['judul' => 'Manual Uji'], $this->pdf(), [
            ['jenis_page' => $a, 'halaman_mulai' => 3],
            ['jenis_page' => $b],
        ]);

        $peta = collect($this->svc()->daftarHalaman($baru['id']))->keyBy('jenis_page');
        $this->assertEqualsCanonicalizing([$a, $b], $peta->keys()->all());
        $this->assertSame(3, $peta[$a]['halaman_mulai']);
        $this->assertNull($peta[$b]['halaman_mulai']);
        $this->assertSame(2, $baru['jumlah_halaman_terpetakan']);

        // Cache pengguna ikut segar.
        $this->assertSame([$baru['id']], array_column($this->svc()->untukHalaman($this->pengguna([$a]), $a), 'id'));
    }

    public function test_halaman_tak_dikenal_atau_ganda_ditolak_tanpa_meninggalkan_berkas(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a] = $this->duaHalaman();
        $sebelum = DB::table(PanduanService::T_PANDUAN)->count();

        foreach ([
            [['jenis_page' => 'halamanYangTidakAda_'.uniqid()]],
            [['jenis_page' => $a], ['jenis_page' => $a]],
        ] as $halaman) {
            try {
                $this->svc()->buat(['judul' => 'Ditolak'], $this->pdf(), $halaman);
                $this->fail('Daftar halaman tidak sah diterima.');
            } catch (DomainException) {
            }
        }

        $this->assertSame($sebelum, DB::table(PanduanService::T_PANDUAN)->count());
        $this->assertSame([], Storage::disk(PanduanService::DISK)->allFiles(), 'Berkas yatim tertinggal di penyimpanan.');
    }

    public function test_ubah_menyinkronkan_halaman_dan_null_berarti_tidak_diubah(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a, $b] = $this->duaHalaman();
        $baru = $this->svc()->buat(['judul' => 'Manual Uji'], $this->pdf(), [['jenis_page' => $a, 'halaman_mulai' => 2]]);
        $idBarisA = DB::table(PanduanService::T_HALAMAN)->where('Id_Panduan', $baru['id'])->value('Id_Panduan_Halaman');

        // Tanpa daftar halaman: pemetaan tidak disentuh.
        $this->svc()->ubah($baru['id'], ['judul' => 'Manual Uji v2']);
        $this->assertSame([$a], array_column($this->svc()->daftarHalaman($baru['id']), 'jenis_page'));

        // Tambah B, ubah halaman mulai A: baris A dipertahankan (jejak pembuatnya tetap).
        $this->svc()->ubah($baru['id'], ['judul' => 'Manual Uji v2'], [
            ['jenis_page' => $a, 'halaman_mulai' => 5],
            ['jenis_page' => $b],
        ]);
        $peta = collect($this->svc()->daftarHalaman($baru['id']))->keyBy('jenis_page');
        $this->assertCount(2, $peta);
        $this->assertSame(5, $peta[$a]['halaman_mulai']);
        $this->assertSame((int) $idBarisA, $peta[$a]['id']);

        // Lepas A.
        $this->svc()->ubah($baru['id'], ['judul' => 'Manual Uji v2'], [['jenis_page' => $b]]);
        $this->assertSame([$b], array_column($this->svc()->daftarHalaman($baru['id']), 'jenis_page'));
        $this->assertSame([], $this->svc()->untukHalaman($this->pengguna([$a]), $a), 'Halaman yang dilepas masih menampilkan panduan.');

        // Halaman wajib: melepas semuanya ditolak, pemetaan lama tetap.
        try {
            $this->svc()->ubah($baru['id'], ['judul' => 'Manual Uji v3'], []);
            $this->fail('Panduan tanpa halaman diterima.');
        } catch (DomainException) {
        }
        $this->assertSame([$b], array_column($this->svc()->daftarHalaman($baru['id']), 'jenis_page'));
    }

    public function test_http_simpan_dengan_halaman_dari_form(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a, $b] = $this->duaHalaman();
        $admin = User::query()->where('Flag_Active', 'Y')->where('Is_System_Admin', 'Y')->first();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada System Admin aktif.');
        }

        $res = $this->actingAs($admin)->post('/system/panduan', [
            'judul' => 'Manual HTTP',
            'berkas' => $this->pdf(),
            'halaman' => [['jenis_page' => $a, 'halaman_mulai' => 2], ['jenis_page' => $b]],
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $res->json('result.id');
        $this->assertSame(2, $res->json('result.jumlah_halaman_terpetakan'));

        // Ubah tanpa atur_sasaran: pemetaan tetap.
        $this->actingAs($admin)->post("/system/panduan/{$id}", ['judul' => 'Manual HTTP 2'], ['Accept' => 'application/json'])->assertOk();
        $this->assertCount(2, $this->svc()->daftarHalaman($id));

        // Ubah dengan atur_sasaran tanpa halaman: ditolak (halaman wajib).
        $this->actingAs($admin)->post("/system/panduan/{$id}", ['judul' => 'Manual HTTP 2', 'atur_sasaran' => '1'], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertCount(2, $this->svc()->daftarHalaman($id));

        // Halaman mulai tidak sah ditolak validasi.
        $this->actingAs($admin)->post("/system/panduan/{$id}", [
            'judul' => 'Manual HTTP 2', 'atur_sasaran' => '1', 'halaman' => [['jenis_page' => $a, 'halaman_mulai' => 0]],
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_unggah_menyimpan_berkas_dan_ubah_tidak_menyentuh_berkas(): void
    {
        Storage::fake(PanduanService::DISK);

        [$a] = $this->duaHalaman();
        $baru = $this->svc()->buat(['judul' => 'Manual Uji', 'versi' => 'v1'], $this->pdf(), [['jenis_page' => $a]]);
        $path = DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $baru['id'])->value('Path_File');
        Storage::disk(PanduanService::DISK)->assertExists($path);
        $this->assertStringStartsWith('panduan/', $path);
        $this->assertSame('manual.pdf', $baru['nama_file']);
        $this->assertSame('v1', $baru['versi']);

        // Form Ubah hanya keterangan: berkas & label versi tetap (berkas baru lewat versi).
        $this->svc()->ubah($baru['id'], ['judul' => 'Manual Uji 2', 'versi' => 'diabaikan']);
        $baris = DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $baru['id'])->first();
        $this->assertSame($path, $baris->Path_File);
        $this->assertSame('v1', $baris->Versi);
        $this->assertSame('Manual Uji 2', $baris->Judul);
    }

    public function test_berkas_disajikan_inline_sebagai_pdf(): void
    {
        Storage::fake(PanduanService::DISK);
        Storage::disk(PanduanService::DISK)->put('panduan/uji/a.pdf', '%PDF-1.4 uji');

        $res = $this->svc()->sajikanPdf(['path' => 'panduan/uji/a.pdf', 'nama' => 'a.pdf']);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $res->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
    }

    /* ── Halaman wajib, role pembatas opsional ──────────────────────────── */

    public function test_panduan_tanpa_halaman_ditolak_tanpa_meninggalkan_berkas(): void
    {
        Storage::fake(PanduanService::DISK);
        [, $role] = $this->penggunaBerRole();

        foreach ([[[], []], [[], [$role]]] as [$halaman, $roles]) {
            try {
                $this->svc()->buat(['judul' => 'Tanpa halaman'], $this->pdf(), $halaman, $roles);
                $this->fail('Panduan tanpa halaman diterima.');
            } catch (DomainException) {
            }
        }
        $this->assertSame([], Storage::disk(PanduanService::DISK)->allFiles());
    }

    public function test_role_pembatas_menyaring_di_halaman_yang_dipilih(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a] = $this->duaHalaman();
        [$idWeb, $rolePunya, $roleLain] = $this->penggunaBerRole();
        if ($roleLain === null) {
            $this->markTestSkipped('Pengguna uji memegang semua role.');
        }

        $untukRolenya = $this->svc()->buat(['judul' => 'Untuk rolenya'], $this->pdf(), [['jenis_page' => $a]], [$rolePunya, $roleLain]);
        $untukRoleLain = $this->svc()->buat(['judul' => 'Untuk role lain'], $this->pdf(), [['jenis_page' => $a]], [$roleLain]);
        $umum = $this->svc()->buat(['judul' => 'Umum'], $this->pdf(), [['jenis_page' => $a]]);
        $this->assertSame(2, $untukRolenya['jumlah_role']);
        $this->assertSame(0, $umum['jumlah_role']);

        $pengguna = $this->pengguna([$a], $idWeb);
        $ids = array_column($this->svc()->untukHalaman($pengguna, $a), 'id');
        $this->assertContains($untukRolenya['id'], $ids, 'Pemegang salah satu role pembatas tidak melihat panduannya.');
        $this->assertContains($umum['id'], $ids);
        $this->assertNotContains($untukRoleLain['id'], $ids, 'Panduan role lain tampil.');

        $this->assertTrue($this->svc()->bolehBukaBerkas($pengguna, $untukRolenya['id']));
        $this->assertFalse($this->svc()->bolehBukaBerkas($pengguna, $untukRoleLain['id']), 'Berkas role lain bisa dibuka lewat Id.');

        // Role saja tidak cukup: halaman tetap harus bisa dibuka.
        $tanpaHalaman = $this->pengguna([], $idWeb);
        $this->assertSame([], $this->svc()->untukHalaman($tanpaHalaman, $a));
        $this->assertFalse($this->svc()->bolehBukaBerkas($tanpaHalaman, $untukRolenya['id']));
    }

    public function test_ubah_role_pembatas_dan_lepas_semua(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a] = $this->duaHalaman();
        [, $role] = $this->penggunaBerRole();
        $baru = $this->svc()->buat(['judul' => 'Role berubah'], $this->pdf(), [['jenis_page' => $a]], [$role, $role]);

        $this->assertSame([$role], array_column($this->svc()->sasaran($baru['id'])['role'], 'id_role'), 'Role ganda tersimpan dua kali.');

        // Ubah tanpa daftar: tetap. Ubah dengan role kosong: pembatas dilepas.
        $this->svc()->ubah($baru['id'], ['judul' => 'Role berubah']);
        $this->assertCount(1, $this->svc()->daftarRole($baru['id']));
        $this->svc()->ubah($baru['id'], ['judul' => 'Role berubah'], [['jenis_page' => $a]], []);
        $sasaran = $this->svc()->sasaran($baru['id']);
        $this->assertSame([], $sasaran['role']);
        $this->assertSame([$a], array_column($sasaran['halaman'], 'jenis_page'));
    }

    public function test_role_tak_dikenal_ditolak(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a] = $this->duaHalaman();
        $tidakAda = (int) DB::table('N_HRIS_Role_Functional')->max('Id_Role') + 1000;

        $this->expectException(DomainException::class);
        $this->svc()->buat(['judul' => 'Ditolak'], $this->pdf(), [['jenis_page' => $a]], [$tidakAda]);
    }

    public function test_http_halaman_dan_role_dari_form(): void
    {
        Storage::fake(PanduanService::DISK);
        [$a] = $this->duaHalaman();
        [, $role] = $this->penggunaBerRole();
        $admin = User::query()->where('Flag_Active', 'Y')->where('Is_System_Admin', 'Y')->first();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada System Admin aktif.');
        }

        $res = $this->actingAs($admin)->post('/system/panduan', [
            'judul' => 'Manual Role HTTP',
            'berkas' => $this->pdf(),
            'halaman' => [['jenis_page' => $a, 'halaman_mulai' => 2]],
            'role' => [$role],
        ], ['Accept' => 'application/json'])->assertCreated();
        $id = $res->json('result.id');

        $this->actingAs($admin)->getJson("/system/panduan/{$id}/sasaran")
            ->assertOk()
            ->assertJsonPath('result.halaman.0.jenis_page', $a)
            ->assertJsonPath('result.halaman.0.halaman_mulai', 2)
            ->assertJsonPath('result.role.0.id_role', $role);

        // Tanpa halaman: ditolak walau role diisi.
        $this->actingAs($admin)->post('/system/panduan', [
            'judul' => 'Role saja', 'berkas' => $this->pdf(), 'role' => [$role],
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    /* ── Gerbang HTTP ───────────────────────────────────────────────────── */

    public function test_halaman_admin_khusus_system_admin(): void
    {
        $bukanAdmin = User::query()->where('Flag_Active', 'Y')->where(fn ($q) => $q->whereNull('Is_System_Admin')->orWhere('Is_System_Admin', '<>', 'Y'))->first();
        if (! $bukanAdmin) {
            $this->markTestSkipped('Tidak ada pengguna aktif non-admin.');
        }

        $this->actingAs($bukanAdmin)->getJson('/system/panduan/data')->assertForbidden();
        $this->actingAs($bukanAdmin)->getJson('/system/panduan/1/berkas')->assertForbidden();
    }

    public function test_berkas_panduan_yang_tidak_dipetakan_ditolak_untuk_pengguna(): void
    {
        $pengguna = User::query()->where('Flag_Active', 'Y')->first();
        $id = $this->buatPanduan('Tanpa halaman');

        $this->actingAs($pengguna)->get("/panduan/{$id}/berkas")->assertForbidden();
    }

    public function test_endpoint_panduan_halaman_menolak_nama_halaman_aneh(): void
    {
        $pengguna = User::query()->where('Flag_Active', 'Y')->first();

        $this->actingAs($pengguna)->getJson('/panduan/halaman/'.rawurlencode('a/../b'))->assertNotFound();
    }
}
