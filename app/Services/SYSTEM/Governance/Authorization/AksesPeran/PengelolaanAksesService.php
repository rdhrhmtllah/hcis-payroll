<?php

namespace App\Services\SYSTEM\Governance\Authorization\AksesPeran;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\AksesDitolak;
use App\Services\SYSTEM\Governance\Authorization\Akses\AturanDelegasi;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use App\Services\SYSTEM\Governance\Authorization\Akses\TingkatPeran;
use App\Services\SYSTEM\Governance\Authorization\Scope\CakupanKaryawan;
use App\Services\SYSTEM\Governance\Authorization\Shared\AuthorizationScopeResolver;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tombol ubah layar Akses & Peran (RBAC Fase 3b). Setiap perubahan:
 * AturanDelegasi dulu → satu transaksi (perubahan + audit sebelum/sesudah)
 * → cache keputusan target dibuang.
 */
class PengelolaanAksesService
{
    public function __construct(
        private AturanDelegasi $aturan,
        private AuditAkses $audit,
        private KeputusanAkses $keputusan,
        private CakupanKaryawan $cakupan,
    ) {}

    /** Perubahan dari layar baru butuh model & tabel audit (DDL-2026-164/165) — tanpa itu tak ada jejak. */
    private function wajibSkema(): void
    {
        abort_unless(
            SkemaRbac::punya('model_baru') && SkemaRbac::punya('audit'),
            409,
            'Pengubahan akses dari layar ini menunggu DDL-2026-164 & DDL-2026-165 dijalankan.',
        );
    }

    /** "Berlaku sampai 20 Okt" = sampai akhir 20 Okt (review I8). */
    private function akhirHari(?string $tanggal): ?string
    {
        return $tanggal ? Carbon::parse($tanggal)->endOfDay()->format('Y-m-d H:i:s') : null;
    }

    /** @return list<string> kode peran aktif user (untuk audit) */
    private function kodePeran(int $idUser): array
    {
        return DB::table('N_HRIS_Role_User as ru')->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->where('ru.UserId_Web', $idUser)->where('ru.Flag_Aktif', 'Y')
            ->orderBy('rf.Role_Code')->pluck('rf.Role_Code')->all();
    }

    private function wajibUser(int $idUser): User
    {
        return User::where('Id_Users', $idUser)->first() ?? throw new AksesDitolak('Karyawan tidak ditemukan.');
    }

    private function segarkan(int $idUser): void
    {
        KeputusanAkses::lupakanCache();
        if ($u = User::where('Id_Users', $idUser)->first()) {
            AuthorizationScopeResolver::clearCache($u);
        }
    }

    public function tugaskan(User $admin, int $idUser, int $idRole, array $cakupan, array $tanggungJawab, ?string $sampai, string $alasan): array
    {
        $this->wajibSkema();
        $this->wajibUser($idUser);
        $sa = $this->keputusan->apakahSystemAdmin($admin);
        $cakupan = array_values(array_filter(array_map(fn ($c) => [
            'jenis' => strtoupper(trim((string) ($c['jenis'] ?? ''))), 'nilai' => ($c['nilai'] ?? null) !== '' ? ($c['nilai'] ?? null) : null,
        ], $cakupan), fn ($c) => $c['jenis'] !== '' && $c['jenis'] !== 'SEMUA'));
        $tanggungJawab = array_values(array_unique(array_intersect(array_map('strtoupper', $tanggungJawab), ['AKSES', 'APPROVAL', 'KEANGGOTAAN'])));
        // Layar tak lagi menanyakan tanggung jawab: admin yang diangkat otomatis
        // berwenang AKSES (satu-satunya tanggung jawab yang ditegakkan).
        if ($tanggungJawab === [] && app(TingkatPeran::class)->setingkatAdmin(app(TingkatPeran::class)->dariPeran($this->aturan->peran($idRole)))) {
            $tanggungJawab = ['AKSES'];
        }
        $peran = $this->aturan->periksaPenugasan($admin, $idRole, $idUser, $cakupan, $tanggungJawab);
        if (! $sa) {
            // Simpan nilai konkret milik admin, bukan "milik pemegang" (review I9).
            $cakupan = array_map(fn ($c) => ['jenis' => $c['jenis'], 'nilai' => $this->cakupan->nilaiNyata($c['jenis'], $c['nilai'], (int) $admin->Id_Users)], $cakupan);
        }
        // Pool approval (Visitor/KPI) dibaca resolver approval yang belum mengenal
        // kedaluwarsa — jangan janjikan batas yang tak ditegakkan (review I2).
        abort_if($sampai && $peran->Jenis_Role === 'POOL_APPROVAL', 422, 'Batas waktu belum didukung untuk peran pool approval.');
        $sampai = $this->akhirHari($sampai);

        $id = DB::transaction(function () use ($admin, $idUser, $idRole, $cakupan, $tanggungJawab, $sampai, $alasan, $peran) {
            // Cek ganda DI DALAM transaksi dengan kunci (review I5: klik ganda).
            // Penugasan yang sudah kedaluwarsa tak dihitung "memegang": ditutup di
            // sini, sehingga memperpanjang cukup dengan menugaskan ulang.
            $aktif = DB::table('N_HRIS_Role_User')->where('UserId_Web', $idUser)->where('Id_Role', $idRole)->where('Flag_Aktif', 'Y')
                ->lockForUpdate()->get(['Id', 'Berlaku_Sampai']);
            abort_if(
                $aktif->contains(fn ($r) => $r->Berlaku_Sampai === null || strtotime((string) $r->Berlaku_Sampai) > time()),
                422,
                'Karyawan ini sudah memegang peran tersebut.',
            );
            if ($aktif->isNotEmpty()) {
                $lewat = $aktif->pluck('Id')->all();
                DB::table('N_HRIS_Role_User')->whereIn('Id', $lewat)->update(['Flag_Aktif' => 'T', 'Alasan' => 'Kedaluwarsa; diganti penugasan baru.']);
                DB::table('N_HRIS_Role_User_Cakupan')->whereIn('Id_Role_User', $lewat)->update(['Flag_Aktif' => 'T']);
                DB::table('N_HRIS_Role_User_Tanggung_Jawab')->whereIn('Id_Role_User', $lewat)->update(['Flag_Aktif' => 'T']);
            }
            $sebelum = $this->kodePeran($idUser);
            $id = DB::table('N_HRIS_Role_User')->insertGetId([
                'UserId_Web' => $idUser, 'Id_Role' => $idRole, 'Assigned_At' => now(), 'Assigned_By' => (string) $admin->Id_Users,
                'Flag_Aktif' => 'Y', 'Berlaku_Sampai' => $sampai, 'Alasan' => $alasan,
            ], 'Id');
            foreach ($cakupan as $c) {
                DB::table('N_HRIS_Role_User_Cakupan')->insert(['Id_Role_User' => $id, 'Jenis_Cakupan' => $c['jenis'], 'Nilai' => $c['nilai']]);
            }
            foreach ($tanggungJawab as $k) {
                DB::table('N_HRIS_Role_User_Tanggung_Jawab')->insert(['Id_Role_User' => $id, 'Kode' => $k]);
            }
            $this->audit->catat('PENUGASAN', (string) $idUser, 'TUGASKAN_PERAN', ['peran' => $sebelum], [
                'peran' => $this->kodePeran($idUser),
                'penugasan' => ['id' => (int) $id, 'kode' => $peran->Role_Code, 'cakupan' => $cakupan ?: [['jenis' => 'SEMUA', 'nilai' => null]],
                    'tanggung_jawab' => $tanggungJawab, 'berlaku_sampai' => $sampai],
            ], $idUser, $alasan, 'UI');

            return $id;
        });
        $this->segarkan($idUser);

        return ['id_penugasan' => (int) $id];
    }

    public function cabut(User $admin, int $idPenugasan, string $alasan): void
    {
        $this->wajibSkema();
        $p = DB::table('N_HRIS_Role_User')->where('Id', $idPenugasan)->where('Flag_Aktif', 'Y')->first();
        abort_unless($p, 404, 'Penugasan tidak ditemukan atau sudah dicabut.');
        $this->aturan->periksaCabut($admin, $p);

        DB::transaction(function () use ($p, $alasan) {
            $sebelum = $this->kodePeran((int) $p->UserId_Web);
            $n = DB::table('N_HRIS_Role_User')->where('Id', $p->Id)->where('Flag_Aktif', 'Y')->update(['Flag_Aktif' => 'T', 'Alasan' => $alasan]);
            abort_if($n === 0, 409, 'Penugasan ini baru saja dicabut orang lain.');
            DB::table('N_HRIS_Role_User_Cakupan')->where('Id_Role_User', $p->Id)->update(['Flag_Aktif' => 'T']);
            DB::table('N_HRIS_Role_User_Tanggung_Jawab')->where('Id_Role_User', $p->Id)->update(['Flag_Aktif' => 'T']);
            $this->audit->catat('PENUGASAN', (string) $p->UserId_Web, 'CABUT_PERAN', ['peran' => $sebelum, 'penugasan' => (int) $p->Id],
                ['peran' => $this->kodePeran((int) $p->UserId_Web)], (int) $p->UserId_Web, $alasan, 'UI');
        });
        $this->segarkan((int) $p->UserId_Web);
    }

    /**
     * Perpanjang / ubah batas waktu penugasan (null = tanpa batas). Wewenangnya sama
     * dengan menugaskan peran itu ke orang itu (tanpa memeriksa ulang cakupan yang ada).
     */
    public function ubahBatas(User $admin, int $idPenugasan, ?string $sampai, string $alasan): array
    {
        $this->wajibSkema();
        $p = DB::table('N_HRIS_Role_User')->where('Id', $idPenugasan)->where('Flag_Aktif', 'Y')->first();
        abort_unless($p, 404, 'Penugasan tidak ditemukan atau sudah dicabut.');
        $peran = $this->aturan->periksaPenugasan($admin, (int) $p->Id_Role, (int) $p->UserId_Web, [], [], true);
        abort_if($sampai && $peran->Jenis_Role === 'POOL_APPROVAL', 422, 'Batas waktu belum didukung untuk peran pool approval.');
        $sampai = $this->akhirHari($sampai);

        DB::transaction(function () use ($p, $sampai, $alasan, $peran) {
            DB::table('N_HRIS_Role_User')->where('Id', $p->Id)->update(['Berlaku_Sampai' => $sampai, 'Alasan' => $alasan]);
            $this->audit->catat('PENUGASAN', (string) $p->UserId_Web, 'UBAH_BATAS',
                ['penugasan' => (int) $p->Id, 'kode' => $peran->Role_Code, 'berlaku_sampai' => $p->Berlaku_Sampai],
                ['penugasan' => (int) $p->Id, 'kode' => $peran->Role_Code, 'berlaku_sampai' => $sampai], (int) $p->UserId_Web, $alasan, 'UI');
        });
        $this->segarkan((int) $p->UserId_Web);

        return ['id_penugasan' => (int) $p->Id, 'berlaku_sampai' => $sampai];
    }

    public function beriGrant(User $admin, int $idUser, string $halaman, ?string $sampai, string $alasan): void
    {
        $this->wajibSkema();
        $this->wajibUser($idUser);
        $this->aturan->periksaGrant($admin, $halaman, $idUser);
        $sampai = $this->akhirHari($sampai);

        DB::transaction(function () use ($idUser, $halaman, $sampai, $alasan) {
            $ada = DB::table('HRIS_Page_Access')->where('UserID_Web', $idUser)->where('Jenis_page', $halaman)->lockForUpdate()->orderByDesc('Id_Page_Access')->first();
            $sebelum = $ada ? ['aktif' => $ada->Flag_Aktif === 'Y', 'berlaku_sampai' => $ada->Berlaku_Sampai ?? null] : null;
            if ($ada) {
                // SEMUA baris kembar ikut diperbarui — satu baris lama tanpa tanggal tak boleh tersisa (review I4).
                DB::table('HRIS_Page_Access')->where('UserID_Web', $idUser)->where('Jenis_page', $halaman)
                    ->update(['Flag_Aktif' => 'Y', 'Berlaku_Sampai' => $sampai, 'Alasan' => $alasan, 'Updated_At' => now()]);
            } else {
                $k = DB::table('Karyawan as a')
                    ->leftJoin('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                    ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as c', 'a.ID_Level_Jabatan', '=', 'c.ID_Level_Jabatan')
                    ->where('a.UserID_Web', $idUser)->first(['b.ID_Divisi', 'c.ID_Level']);
                DB::table('HRIS_Page_Access')->insert([
                    'Kode_Perusahaan' => '001', 'Jenis_page' => $halaman, 'UserID_Web' => $idUser,
                    'ID_Divisi' => $k->ID_Divisi ?? 0, 'ID_Level' => $k->ID_Level ?? 0, 'Flag_Aktif' => 'Y', // kolom NOT NULL
                    'Berlaku_Sampai' => $sampai, 'Alasan' => $alasan, 'Created_At' => now(), 'Updated_At' => now(),
                ]);
            }
            $this->audit->catat('GRANT', $halaman, 'BERI_GRANT', $sebelum, ['aktif' => true, 'berlaku_sampai' => $sampai], $idUser, $alasan, 'UI');
        });
        $this->segarkan($idUser);
    }

    public function cabutGrant(User $admin, int $idUser, string $halaman, string $alasan): void
    {
        $this->wajibSkema();
        // Halaman yang sudah hilang dari menu tetap bisa dicabut.
        $this->aturan->periksaGrant($admin, $halaman, $idUser, false);
        $n = 0;
        DB::transaction(function () use ($idUser, $halaman, $alasan, &$n) {
            $n = DB::table('HRIS_Page_Access')->where('UserID_Web', $idUser)->where('Jenis_page', $halaman)->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T', 'Alasan' => $alasan, 'Updated_At' => now()]);
            if ($n > 0) {
                $this->audit->catat('GRANT', $halaman, 'CABUT_GRANT', ['aktif' => true], ['aktif' => false], $idUser, $alasan, 'UI');
            }
        });
        abort_if($n === 0, 404, 'Karyawan ini tidak memegang grant langsung untuk halaman tersebut.');
        $this->segarkan($idUser);
    }

    /** @return list<array{jenis_page: string, aksi: list<string>}> */
    private function hakAktif(int $idRole): array
    {
        return DB::table('N_HRIS_Role_Hak as h')->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
            ->where('h.Id_Role', $idRole)->where('h.Flag_Aktif', 'Y')->orderBy('h.Jenis_page')->get(['h.Jenis_page', 'a.Nama_Aksi'])
            ->groupBy('Jenis_page')->map(fn ($g, $p) => ['jenis_page' => $p, 'aksi' => $g->pluck('Nama_Aksi')->sort()->values()->all()])
            ->values()->all();
    }

    /** @param list<array{jenis_page: string, aksi: list<string>}> $hak */
    /** Versi matriks untuk konkurensi optimistis (review minor Fase 3b). */
    public function versiHak(int $idRole): string
    {
        return md5((string) json_encode($this->hakAktif($idRole)));
    }

    public function ubahHak(User $admin, int $idRole, array $hak, string $alasan, ?string $versi = null): void
    {
        $this->wajibSkema();
        $aksiId = DB::table('N_HRIS_Klasifikasi_Aksi')->pluck('Id_Klasifikasi_Actions', 'Nama_Aksi')->mapWithKeys(fn ($v, $k) => [strtoupper($k) => (int) $v]);
        $menu = DB::table('N_HRIS_Menus')->pluck('Jenis_page')->mapWithKeys(fn ($p) => [HibahTataKelola::normalisasi($p) => $p]);
        $baru = [];
        foreach ($hak as $h) {
            $page = $menu[HibahTataKelola::normalisasi((string) ($h['jenis_page'] ?? ''))] ?? null;
            abort_unless($page, 422, 'Halaman tidak dikenal: '.($h['jenis_page'] ?? ''));
            $aksi = array_values(array_unique(array_map('strtoupper', (array) ($h['aksi'] ?? []))));
            foreach ($aksi as $a) {
                abort_unless(isset($aksiId[$a]), 422, "Aksi tidak dikenal: {$a}");
            }
            if ($aksi !== []) {
                sort($aksi);
                $baru[] = ['jenis_page' => $page, 'aksi' => $aksi];
            }
        }
        $lama = $this->hakAktif($idRole);
        $this->aturan->periksaUbahHak($admin, $idRole, $baru, $lama);

        try {
            $this->simpanHak($idRole, $baru, $aksiId, $admin, $alasan, $versi);
        } catch (\Illuminate\Database\QueryException $e) {
            // Dua admin menyimpan bersamaan → UQ_HRIS_Role_Hak; jawab 409, bukan 500.
            abort_if(str_contains($e->getMessage(), 'UQ_HRIS_Role_Hak') || (string) $e->getCode() === '23000', 409, 'Matriks ini baru saja diubah admin lain. Muat ulang lalu ulangi.');
            throw $e;
        }
        KeputusanAkses::lupakanCache();
    }

    private function simpanHak(int $idRole, array $baru, $aksiId, User $admin, string $alasan, ?string $versi): void
    {
        DB::transaction(function () use ($idRole, $baru, $aksiId, $admin, $alasan, $versi) {
            DB::table('N_HRIS_Role_Functional')->where('Id_Role', $idRole)->lockForUpdate()->value('Id_Role');
            $lama = $this->hakAktif($idRole); // "sebelum" dibaca ulang di dalam transaksi
            abort_if($versi !== null && $versi !== md5((string) json_encode($lama)), 409, 'Matriks ini baru saja diubah admin lain. Muat ulang lalu ulangi.');
            $target = [];
            foreach ($baru as $h) {
                foreach ($h['aksi'] as $a) {
                    $target[HibahTataKelola::normalisasi($h['jenis_page']).'|'.$aksiId[$a]] = $h['jenis_page'];
                }
            }
            // Dikelompokkan per kunci ternormalisasi: data lama bisa punya baris kembar
            // yang hanya beda spasi — semuanya harus ikut, bukan baris terakhir saja.
            $ada = DB::table('N_HRIS_Role_Hak')->where('Id_Role', $idRole)->orderBy('Id_Role_Hak')->get(['Id_Role_Hak', 'Jenis_page', 'Id_Aksi', 'Flag_Aktif'])
                ->groupBy(fn ($r) => HibahTataKelola::normalisasi((string) $r->Jenis_page).'|'.(int) $r->Id_Aksi);
            $oleh = 'UI:'.$admin->Id_Users;
            $nonaktifkan = fn (array $id) => $id === [] ? null
                : DB::table('N_HRIS_Role_Hak')->whereIn('Id_Role_Hak', $id)->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);
            foreach ($target as $kunci => $page) {
                $baris = $ada->get($kunci);
                if (! $baris) {
                    DB::table('N_HRIS_Role_Hak')->insert(['Id_Role' => $idRole, 'Jenis_page' => $page, 'Id_Aksi' => (int) explode('|', $kunci)[1], 'Created_By' => $oleh]);

                    continue;
                }
                // Satu baris aktif per kunci; kembarannya dinonaktifkan.
                $pakai = $baris->firstWhere('Flag_Aktif', 'Y') ?? $baris->first();
                if ($pakai->Flag_Aktif !== 'Y') {
                    DB::table('N_HRIS_Role_Hak')->where('Id_Role_Hak', $pakai->Id_Role_Hak)->update(['Flag_Aktif' => 'Y', 'Updated_At' => now(), 'Updated_By' => $oleh]);
                }
                $nonaktifkan($baris->where('Flag_Aktif', 'Y')->where('Id_Role_Hak', '<>', $pakai->Id_Role_Hak)->pluck('Id_Role_Hak')->all());
            }
            foreach ($ada as $kunci => $baris) {
                if (! isset($target[$kunci])) {
                    $nonaktifkan($baris->where('Flag_Aktif', 'Y')->pluck('Id_Role_Hak')->all());
                }
            }
            $this->audit->catat('ROLE', (string) $idRole, 'UBAH_HAK', $lama, $baru, null, $alasan, 'UI');
        });
    }

    /** Pratinjau dampak sebelum simpan — dalam lingkup yang sama dengan perubahannya (review I7). */
    public function pratinjau(User $admin, array $d): array
    {
        $wajib = ['penugasan' => ['id_user', 'id_role'], 'grant' => ['id_user', 'jenis_page'], 'hak' => ['id_role']][$d['jenis'] ?? ''] ?? [];
        foreach ($wajib as $k) {
            abort_if(empty($d[$k]), 422, "Isian {$k} wajib untuk pratinjau.");
        }
        match ($d['jenis'] ?? '') {
            'penugasan' => $this->aturan->periksaPenugasan($admin, (int) $d['id_role'], (int) $d['id_user'], [], [], true),
            'grant' => $this->aturan->periksaGrant($admin, (string) $d['jenis_page'], (int) $d['id_user']),
            'hak' => $this->aturan->periksaLihatPeran($admin, (int) $d['id_role']),
            default => null,
        };
        $namaMenu = fn (array $pages) => DB::table('N_HRIS_Menus')->whereIn('Jenis_page', $pages ?: ['-'])
            ->get(['Jenis_page', 'Nama_Menu'])->unique('Jenis_page')
            ->map(fn ($m) => ['jenis_page' => $m->Jenis_page, 'nama_menu' => $m->Nama_Menu])->values()->all();

        switch ($d['jenis'] ?? '') {
            case 'penugasan':
                $u = $this->wajibUser((int) $d['id_user']);
                $pages = collect($this->aturan->halamanPeran($this->aturan->peran((int) $d['id_role'])))
                    ->filter(fn ($p) => ! $this->keputusan->boleh($u, (string) $p))->values()->all();

                return ['halaman_baru' => $namaMenu($pages)];
            case 'grant':
                $u = $this->wajibUser((int) $d['id_user']);

                return ['sudah_bisa' => $this->keputusan->boleh($u, (string) $d['jenis_page'])];
            case 'hak':
                $lama = collect($this->hakAktif((int) $d['id_role']))->pluck('jenis_page')->map(fn ($p) => HibahTataKelola::normalisasi($p));
                $baru = collect((array) ($d['hak'] ?? []))->filter(fn ($h) => ! empty($h['aksi']))->pluck('jenis_page')->map(fn ($p) => HibahTataKelola::normalisasi((string) $p));
                $pemegang = DB::table('N_HRIS_Role_User')->where('Id_Role', (int) $d['id_role'])->where('Flag_Aktif', 'Y')->count();
                $menu = DB::table('N_HRIS_Menus')->pluck('Jenis_page')->mapWithKeys(fn ($p) => [HibahTataKelola::normalisasi($p) => $p]);

                // Halaman yang tetap ada tapi aksinya berubah (mis. VIEW → VIEW+EDIT).
                $aksiLama = collect($this->hakAktif((int) $d['id_role']))
                    ->mapWithKeys(fn ($h) => [HibahTataKelola::normalisasi($h['jenis_page']) => $h['aksi']]);
                $aksiBaru = collect((array) ($d['hak'] ?? []))->filter(fn ($h) => ! empty($h['aksi']))
                    ->mapWithKeys(fn ($h) => [HibahTataKelola::normalisasi((string) $h['jenis_page']) => collect($h['aksi'])->map(fn ($a) => strtoupper((string) $a))->unique()->sort()->values()->all()]);
                $aksiBerubah = $aksiBaru->filter(fn ($a, $k) => $aksiLama->has($k) && $aksiLama[$k] !== $a)
                    ->map(fn ($a, $k) => ['jenis_page' => $menu[$k] ?? $k, 'sebelum' => $aksiLama[$k], 'sesudah' => $a])->values()->all();

                return [
                    'ditambah' => $namaMenu($baru->diff($lama)->map(fn ($k) => $menu[$k] ?? $k)->values()->all()),
                    'dihapus' => $namaMenu($lama->diff($baru)->map(fn ($k) => $menu[$k] ?? $k)->values()->all()),
                    'aksi_berubah' => $aksiBerubah,
                    'jumlah_pemegang' => $pemegang,
                ];
            default:
                abort(422, 'Jenis pratinjau tidak dikenal.');
        }
    }
}
