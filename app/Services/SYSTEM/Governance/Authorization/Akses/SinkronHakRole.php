<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menyamakan N_HRIS_Role_Hak dengan kondisi LAMA (RBAC Fase 3a).
 *
 * Target per role aktif = halaman hibah grupnya (tanpa saring gfm.Flag_Aktif,
 * persis User::memilikiAksesBaru; halaman tata kelola hanya untuk role admin
 * sah) × (VIEW + capability default). Aturannya sama dengan DML-2026-166, tapi
 * dihitung dari config — satu sumber. Hanya modul LAMA/BAYANGAN; di modul BARU
 * hak role milik admin dan tidak disentuh. Tak pernah menghapus: yang tak lagi
 * berlaku dinonaktifkan.
 */
class SinkronHakRole
{
    public function __construct(private ModeAkses $mode) {}

    /** @var array<int, int> jumlah perubahan per modul pada jalankan terakhir */
    private array $perModul = [];

    /**
     * Perkiraan beda hak peran vs hibah per modul — sinkron KERING, tak menulis
     * (laporan kesiapan RBAC Fase 5). Modul BARU / pernah BARU tidak dihitung.
     *
     * @return array<int, int>
     *
     * @throws SinkronSedangBerjalan
     */
    public function bedaPerModul(): array
    {
        $this->perModul = [];
        $this->jalankan(true);

        return $this->perModul;
    }

    /** @return array{tambah: int, aktifkan: int, nonaktif: int} */
    public function jalankan(bool $kering = false, string $sumber = 'SISTEM'): array
    {
        $hasil = ['tambah' => 0, 'aktifkan' => 0, 'nonaktif' => 0];
        if (! SkemaTersedia::tabel('N_HRIS_Role_Hak')) {
            return $hasil;
        }

        // Sinkron kering hanya membaca — tak perlu kunci, dan tak boleh menahan
        // sinkron terjadwal (laporan kesiapan, review Fase 5).
        if ($kering) {
            return $this->jalankanTerkunci(true, $sumber, $hasil);
        }

        // Satu sinkron dalam satu waktu (jadwal + tombol layar tak boleh bertabrakan).
        // TTL pendek: sinkron makan hitungan detik; kunci yang tertinggal karena
        // request dibunuh tak boleh menahan perubahan mode lama-lama.
        $kunci = Cache::lock('rbac:sinkron-hak', 120);
        if (! $kunci->get()) {
            throw new SinkronSedangBerjalan('Sinkron hak peran sedang berjalan. Coba lagi sebentar.');
        }
        try {
            return $this->jalankanTerkunci($kering, $sumber, $hasil);
        } finally {
            $kunci->release();
        }
    }

    /** @param array{tambah:int, aktifkan:int, nonaktif:int} $hasil */
    private function jalankanTerkunci(bool $kering, string $sumber, array $hasil): array
    {
        ModeAkses::lupakanCache(); // mode dibaca di dalam kunci

        $view = (int) DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions');
        $cap = DB::table('N_HRIS_Menu_Capabilities')->where('Flag_Aktif', 'Y')->where('Is_Active_Default', 'Y')
            ->get(['Jenis_page', 'Id_Aksi'])
            ->groupBy(fn ($r) => HibahTataKelola::normalisasi((string) $r->Jenis_page))
            ->map(fn ($g) => $g->pluck('Id_Aksi')->map(fn ($v) => (int) $v)->all());
        $peranAdmin = HibahTataKelola::peranAdmin();

        $roles = DB::table('N_HRIS_Role_Functional')->get(['Id_Role', 'Role_Code', 'Id_Grup_Fitur', 'Flag_Aktif',
            ...(SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role') ? ['Jenis_Role'] : []),
            ...(SkemaRbac::punya('tingkat') ? ['Kode_Tingkat'] : [])]);
        $tingkat = app(TingkatPeran::class);
        $hibah = DB::table('N_HRIS_Grup_Fitur_Map')->where('Flag_Hibah_Role', 'Y')->get(['Id_Grup_Fitur', 'Jenis_page'])
            ->groupBy('Id_Grup_Fitur');

        // Modul BARU — dan yang pernah BARU — matriksnya milik admin.
        $milikAdmin = $this->mode->modulPernahBaru();

        DB::transaction(function () use ($roles, $hibah, $cap, $view, $peranAdmin, $kering, $milikAdmin, $tingkat, &$hasil) {
            foreach ($roles as $r) {
                $grup = $r->Id_Grup_Fitur !== null ? (int) $r->Id_Grup_Fitur : null;
                if ($this->mode->modeModul($grup) === ModeAkses::BARU || in_array($grup, $milikAdmin, true)) {
                    continue;
                }
                // Super Admin modul berhak penuh lewat tingkatnya (KeputusanAkses::superAdminHalaman); matriksnya tak dipakai.
                if ($grup !== null && $tingkat->dariPeran($r) === TingkatPeran::SUPER_ADMIN) {
                    continue;
                }

                $awal = array_sum($hasil);

                // Target: kunci "halaman|aksi" → Jenis_page asli.
                $target = [];
                if ($r->Flag_Aktif === 'Y' && $grup !== null) {
                    $admin = in_array(strtoupper((string) $r->Role_Code), $peranAdmin, true);
                    foreach ($hibah->get($grup, []) as $m) {
                        $kunci = HibahTataKelola::normalisasi((string) $m->Jenis_page);
                        if (! $admin && HibahTataKelola::apakahHalamanTataKelola($kunci)) {
                            continue;
                        }
                        foreach (array_unique(array_merge([$view], $cap->get($kunci, []))) as $aksi) {
                            $target[$kunci.'|'.$aksi] ??= (string) $m->Jenis_page;
                        }
                    }
                }

                $ada = DB::table('N_HRIS_Role_Hak')->where('Id_Role', $r->Id_Role)->get(['Id_Role_Hak', 'Jenis_page', 'Id_Aksi', 'Flag_Aktif'])
                    ->keyBy(fn ($h) => HibahTataKelola::normalisasi((string) $h->Jenis_page).'|'.(int) $h->Id_Aksi);

                $batchInsert = [];
                $batchAktif = [];
                foreach ($target as $kunci => $page) {
                    $baris = $ada->get($kunci);
                    if (! $baris) {
                        $hasil['tambah']++;
                        if (! $kering) {
                            $batchInsert[] = [
                                'Id_Role' => $r->Id_Role, 'Jenis_page' => $page, 'Id_Aksi' => (int) explode('|', $kunci)[1],
                                'Flag_Aktif' => 'Y', 'Created_By' => 'SINKRON',
                            ];
                        }
                    } elseif ($baris->Flag_Aktif !== 'Y') {
                        $hasil['aktifkan']++;
                        if (! $kering) {
                            $batchAktif[] = $baris->Id_Role_Hak;
                        }
                    }
                }
                $batchNonaktif = [];
                foreach ($ada as $kunci => $baris) {
                    if ($baris->Flag_Aktif === 'Y' && ! isset($target[$kunci])) {
                        $hasil['nonaktif']++;
                        if (! $kering) {
                            $batchNonaktif[] = $baris->Id_Role_Hak;
                        }
                    }
                }
                if (! $kering) {
                    if (! empty($batchInsert)) {
                        foreach (array_chunk($batchInsert, 100) as $chunk) {
                            DB::table('N_HRIS_Role_Hak')->insert($chunk);
                        }
                    }
                    if (! empty($batchAktif)) {
                        foreach (array_chunk($batchAktif, 500) as $chunk) {
                            DB::table('N_HRIS_Role_Hak')->whereIn('Id_Role_Hak', $chunk)
                                ->update(['Flag_Aktif' => 'Y', 'Updated_At' => now(), 'Updated_By' => 'SINKRON']);
                        }
                    }
                    if (! empty($batchNonaktif)) {
                        foreach (array_chunk($batchNonaktif, 500) as $chunk) {
                            DB::table('N_HRIS_Role_Hak')->whereIn('Id_Role_Hak', $chunk)
                                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => 'SINKRON']);
                        }
                    }
                }
                if ($grup !== null) {
                    $this->perModul[$grup] = ($this->perModul[$grup] ?? 0) + array_sum($hasil) - $awal;
                }
            }
        });

        if (! $kering && array_sum($hasil) > 0) {
            KeputusanAkses::lupakanCache();
            app(AuditAkses::class)->catat('KATALOG', 'N_HRIS_Role_Hak', 'SINKRON_HAK', null, $hasil, null, null, $sumber);
        }

        return $hasil;
    }
}
