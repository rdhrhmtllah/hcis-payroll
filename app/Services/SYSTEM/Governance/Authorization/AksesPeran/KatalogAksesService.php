<?php

namespace App\Services\SYSTEM\Governance\Authorization\AksesPeran;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SinkronHakRole;
use App\Services\SYSTEM\Governance\Authorization\Akses\SinkronSedangBerjalan;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use App\Services\SYSTEM\Governance\Authorization\Akses\TingkatPeran;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Facades\DB;

/**
 * Tab Katalog layar Akses & Peran (RBAC Fase 3a) — khusus System Admin:
 * mode keputusan per modul, selisih aturan lama vs baru, sinkron hak role.
 */
class KatalogAksesService
{
    public function __construct(private ModeAkses $mode, private AuditAkses $audit, private SinkronHakRole $sinkron) {}

    public function daftar(): array
    {
        $adaSelisih = SkemaTersedia::tabel('N_HRIS_Akses_Selisih');
        $adaHak = SkemaTersedia::tabel('N_HRIS_Role_Hak');

        return DB::table('N_HRIS_Master_Grup_Fitur as g')->where('g.Flag_Aktif', 'Y')->orderBy('g.Sort_Order')
            ->get(['g.Id_Grup_Fitur', 'g.Kode_Grup_Fitur', 'g.Nama_Grup_Fitur'])
            ->map(function ($g) use ($adaSelisih, $adaHak) {
                $id = (int) $g->Id_Grup_Fitur;
                $selisih = $adaSelisih ? $this->kueriSelisih($id) : null;

                return [
                    'id' => $id,
                    'kode' => $g->Kode_Grup_Fitur,
                    'nama' => $g->Nama_Grup_Fitur,
                    'mode' => $this->mode->modeModul($id),
                    'selisih' => $selisih ? (clone $selisih)->count() : 0,
                    'selisih_terakhir' => $selisih ? (clone $selisih)->max('s.Terakhir_At') : null,
                    'jumlah_hak' => $adaHak
                        ? DB::table('N_HRIS_Role_Hak as h')->join('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'h.Id_Role')
                            ->where('r.Id_Grup_Fitur', $id)->where('h.Flag_Aktif', 'Y')->count()
                        : 0,
                ];
            })->values()->all();
    }

    /**
     * Review Fase 5 (I1): halaman modul BARU (atau pernah BARU) yang katalognya
     * baru PERTAMA kali mendapat aksi tulis. Sebelumnya cukup VIEW untuk menulis;
     * tanpa langkah ini semua pemegang peran langsung 403 (sinkron tak menyentuh
     * modul milik admin). Peran yang memegang VIEW diberi aksi tulis baru itu —
     * admin bisa mencabutnya lewat matriks. Diaudit per peran.
     *
     * @param  list<int>  $aksiTulisBaru  Id aksi non-VIEW yang kini aktif di katalog
     * @return int jumlah hak yang ditambahkan/diaktifkan
     */
    public function pertahankanTulis(string $halaman, bool $adaTulisSebelum, array $aksiTulisBaru): int
    {
        $modul = $this->mode->modulHalaman($halaman);
        if ($adaTulisSebelum || $aksiTulisBaru === [] || $modul === null || ! SkemaRbac::punya('model_baru')
            || ($this->mode->modeModul($modul) !== ModeAkses::BARU && ! in_array($modul, $this->mode->modulPernahBaru(), true))) {
            return 0;
        }
        $kunci = HibahTataKelola::normalisasi($halaman);
        $view = (int) DB::table('N_HRIS_Klasifikasi_Aksi')->where('Nama_Aksi', 'VIEW')->value('Id_Klasifikasi_Actions');
        $peran = DB::table('N_HRIS_Role_Hak')->where('Id_Aksi', $view)->where('Flag_Aktif', 'Y')
            ->whereRaw("LOWER(REPLACE(LTRIM(RTRIM(Jenis_page)), ' ', '')) = ?", [$kunci])
            ->get(['Id_Role', 'Jenis_page'])->unique('Id_Role');

        $jumlah = 0;
        DB::transaction(function () use ($peran, $aksiTulisBaru, $kunci, $halaman, &$jumlah) {
            foreach ($peran as $p) {
                $ditambah = [];
                foreach ($aksiTulisBaru as $aksi) {
                    $ada = DB::table('N_HRIS_Role_Hak')->where('Id_Role', $p->Id_Role)->where('Id_Aksi', $aksi)
                        ->whereRaw("LOWER(REPLACE(LTRIM(RTRIM(Jenis_page)), ' ', '')) = ?", [$kunci])->first(['Id_Role_Hak', 'Flag_Aktif']);
                    if ($ada && $ada->Flag_Aktif === 'Y') {
                        continue;
                    }
                    $ada
                        ? DB::table('N_HRIS_Role_Hak')->where('Id_Role_Hak', $ada->Id_Role_Hak)->update(['Flag_Aktif' => 'Y', 'Updated_At' => now(), 'Updated_By' => 'KATALOG'])
                        : DB::table('N_HRIS_Role_Hak')->insert(['Id_Role' => $p->Id_Role, 'Jenis_page' => $p->Jenis_page, 'Id_Aksi' => $aksi, 'Flag_Aktif' => 'Y', 'Created_By' => 'KATALOG']);
                    $ditambah[] = $aksi;
                }
                if ($ditambah !== []) {
                    $jumlah += count($ditambah);
                    $this->audit->catat('ROLE', (string) $p->Id_Role, 'TAMBAH_HAK_KATALOG', null,
                        ['halaman' => $halaman, 'aksi' => $ditambah], null,
                        'Katalog halaman mendapat aksi tulis; pemegang VIEW dipertahankan bisa menulis.', 'SISTEM');
                }
            }
        });
        KeputusanAkses::lupakanCache();

        return $jumlah;
    }

    /**
     * Master tingkat peran (DDL-2026-181) untuk tab Katalog. Hanya kemampuan
     * Super Admin & Admin yang bisa diubah: System Admin adalah bendera identitas,
     * Pengguna memang tak mengelola.
     */
    public function tingkat(): array
    {
        return collect(app(TingkatPeran::class)->semua())->map(fn ($t, $kode) => [
            'kode' => $kode, 'nama' => $t['Nama_Tingkat'], 'peringkat' => $t['Peringkat'],
            'boleh_angkat' => $t[TingkatPeran::ANGKAT] === 'Y', 'boleh_ubah_matriks' => $t[TingkatPeran::UBAH_MATRIKS] === 'Y',
            'boleh_tugaskan' => $t[TingkatPeran::TUGASKAN] === 'Y', 'keterangan' => $t['Keterangan'] ?? null,
            'bisa_diubah' => in_array($kode, self::TINGKAT_BISA_DIUBAH, true),
        ])->values()->all();
    }

    private const TINGKAT_BISA_DIUBAH = [TingkatPeran::SUPER_ADMIN, TingkatPeran::ADMIN];

    /** @param array{boleh_angkat?: bool, boleh_ubah_matriks?: bool, boleh_tugaskan?: bool} $kemampuan */
    public function ubahKemampuanTingkat(string $kode, array $kemampuan, string $alasan): array
    {
        abort_unless(SkemaRbac::punya('tingkat'), 409, 'Master tingkat belum tersedia (DDL-2026-181).');
        $kode = strtoupper($kode);
        abort_unless(in_array($kode, self::TINGKAT_BISA_DIUBAH, true), 422, 'Hanya kemampuan Super Admin dan Admin yang bisa diubah.');
        $peta = ['boleh_angkat' => TingkatPeran::ANGKAT, 'boleh_ubah_matriks' => TingkatPeran::UBAH_MATRIKS, 'boleh_tugaskan' => TingkatPeran::TUGASKAN];
        $ubah = [];
        foreach ($peta as $isian => $kolom) {
            if (array_key_exists($isian, $kemampuan)) {
                $ubah[$kolom] = $kemampuan[$isian] ? 'Y' : 'T';
            }
        }
        abort_if($ubah === [], 422, 'Tidak ada kemampuan yang diubah.');

        DB::transaction(function () use ($kode, $ubah, $alasan) {
            $sebelum = (array) DB::table('N_HRIS_Tingkat_Peran')->where('Kode_Tingkat', $kode)->lockForUpdate()
                ->first([TingkatPeran::ANGKAT, TingkatPeran::UBAH_MATRIKS, TingkatPeran::TUGASKAN]);
            abort_if($sebelum === [], 404, 'Tingkat tidak ditemukan.');
            DB::table('N_HRIS_Tingkat_Peran')->where('Kode_Tingkat', $kode)->update($ubah + ['Updated_At' => now(), 'Updated_By' => auth()->id()]);
            $this->audit->catat('TINGKAT', $kode, 'UBAH_KEMAMPUAN', $sebelum, array_merge($sebelum, $ubah), null, $alasan);
        });
        TingkatPeran::lupakan();
        KeputusanAkses::lupakanCache();

        return collect($this->tingkat())->firstWhere('kode', $kode);
    }

    /** Tingkat sebuah peran — Jenis_Role ikut: setingkat admin → ADMIN; turun dari admin → AKSES. */
    public function ubahTingkatPeran(int $idRole, string $kode, string $alasan): array
    {
        abort_unless(SkemaRbac::punya('tingkat'), 409, 'Master tingkat belum tersedia (DDL-2026-181).');
        $tingkat = app(TingkatPeran::class);
        $kode = strtoupper($kode);
        abort_unless($kode !== TingkatPeran::SYSTEM_ADMIN && isset($tingkat->semua()[$kode]), 422, 'Tingkat tidak dikenal.');

        DB::transaction(function () use ($idRole, $kode, $alasan, $tingkat) {
            $r = DB::table('N_HRIS_Role_Functional')->where('Id_Role', $idRole)->lockForUpdate()->first(['Role_Code', 'Jenis_Role', 'Kode_Tingkat']);
            abort_unless($r, 404, 'Peran tidak ditemukan.');
            // Pool approval dibaca resolver approval lewat jenisnya — jangan sampai hilang (review M2).
            abort_if($r->Jenis_Role === 'POOL_APPROVAL', 422, 'Peran pool approval tetap bertingkat Pengguna.');
            if ($tingkat->setingkatAdmin($r->Kode_Tingkat) && ! $tingkat->setingkatAdmin($kode)) {
                // Turun dari admin: tanggung jawab admin pemegangnya ikut dimatikan, supaya
                // menaikkannya lagi tak diam-diam memulihkan wewenang lama.
                DB::table('N_HRIS_Role_User_Tanggung_Jawab')->where('Flag_Aktif', 'Y')
                    ->whereIn('Id_Role_User', DB::table('N_HRIS_Role_User')->where('Id_Role', $idRole)->select('Id'))
                    ->update(['Flag_Aktif' => 'T']);
            }
            $jenis = $tingkat->setingkatAdmin($kode) ? 'ADMIN' : ($r->Jenis_Role === 'ADMIN' ? 'AKSES' : $r->Jenis_Role);
            DB::table('N_HRIS_Role_Functional')->where('Id_Role', $idRole)->update(['Kode_Tingkat' => $kode, 'Jenis_Role' => $jenis]);
            $this->audit->catat('ROLE', (string) $idRole, 'UBAH_TINGKAT', ['tingkat' => $r->Kode_Tingkat, 'jenis' => $r->Jenis_Role],
                ['tingkat' => $kode, 'jenis' => $jenis], null, $alasan);
        });
        KeputusanAkses::lupakanCache();

        return ['id' => $idRole, 'tingkat' => $kode];
    }

    /**
     * Laporan kesiapan pensiun jalur lama per modul (RBAC Fase 5c). Tidak
     * menulis: beda hak-vs-hibah dihitung dengan sinkron kering.
     * Status: BARU | PERLU_SINKRON | TINJAU_SELISIH | SIAP_BARU | SIAP_BAYANGAN.
     *
     * @throws SinkronSedangBerjalan
     */
    public function kesiapan(): array
    {
        $beda = $this->sinkron->bedaPerModul();
        $pernah = $this->mode->modulPernahBaru();
        $halaman = DB::table('N_HRIS_Grup_Fitur_Map')->where('Flag_Aktif', 'Y')->distinct()->pluck('Jenis_page')
            ->map(fn ($p) => $this->mode->modulHalaman((string) $p))->filter()->countBy()->all();
        $penugasan = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->where('ru.Flag_Aktif', 'Y')->where('rf.Flag_Aktif', 'Y')
            ->tap(fn ($q) => SkemaRbac::saringPenugasanAktif($q, 'ru.'))
            ->groupBy('rf.Id_Grup_Fitur')->selectRaw('rf.Id_Grup_Fitur as g, COUNT(*) as n')
            ->pluck('n', 'g')->mapWithKeys(fn ($n, $g) => [(int) $g => (int) $n])->all();

        return collect($this->daftar())->map(function ($m) use ($beda, $pernah, $halaman, $penugasan) {
            $pernahBaru = in_array($m['id'], $pernah, true);
            $bedaHak = $m['mode'] === ModeAkses::BARU || $pernahBaru ? null : ($beda[$m['id']] ?? 0);
            $status = match (true) {
                $m['mode'] === ModeAkses::BARU => 'BARU',
                $bedaHak > 0 => 'PERLU_SINKRON',
                $m['mode'] === ModeAkses::BAYANGAN && $m['selisih'] > 0 => 'TINJAU_SELISIH',
                $m['mode'] === ModeAkses::BAYANGAN => 'SIAP_BARU',
                default => 'SIAP_BAYANGAN',
            };

            return $m + [
                'pernah_baru' => $pernahBaru,
                'halaman' => $halaman[$m['id']] ?? 0,
                'penugasan' => $penugasan[$m['id']] ?? 0,
                'beda_hak' => $bedaHak,
                'status' => $status,
            ];
        })->values()->all();
    }

    /**
     * Selisih yang halamannya milik modul ini — pemilik dihitung dengan aturan
     * yang SAMA dengan keputusan (ModeAkses::modulHalaman), bukan SQL tersendiri.
     */
    private function kueriSelisih(int $modul)
    {
        $halaman = DB::table('N_HRIS_Akses_Selisih')->distinct()->pluck('Jenis_page')
            ->filter(fn ($p) => $this->mode->modulHalaman((string) $p) === $modul)->values()->all();

        return DB::table('N_HRIS_Akses_Selisih as s')->whereIn('s.Jenis_page', $halaman ?: ['-']);
    }

    public function ubahMode(int $modul, string $modeBaru, string $alasan, int $oleh): array
    {
        abort_unless(SkemaTersedia::tabel('N_HRIS_Akses_Mode_Modul'), 422, 'Tabel mode belum tersedia (DDL-2026-168).');
        abort_unless(DB::table('N_HRIS_Master_Grup_Fitur')->where('Id_Grup_Fitur', $modul)->exists(), 404, 'Modul tidak ditemukan.');
        // RBAC Fase 4: semua jalur keputusan halaman kini lewat KeputusanAkses,
        // jadi BARU dibuka. Sakelar config tetap ada sebagai rem darurat.
        abort_if(
            $modeBaru === ModeAkses::BARU && ! config('authorization.mode_baru_diizinkan', true),
            422,
            'Mode BARU sedang dimatikan lewat konfigurasi (RBAC_MODE_BARU_DIIZINKAN).',
        );
        $sebelum = $this->mode->modeModul($modul);
        if ($sebelum === $modeBaru) {
            return ['id' => $modul, 'mode_sebelum' => $sebelum, 'mode' => $modeBaru];
        }
        if ($modeBaru === ModeAkses::BARU) {
            // BARU hanya lewat BAYANGAN: selisihnya harus sempat terlihat dulu.
            abort_unless($sebelum === ModeAkses::BAYANGAN, 422, 'Pindahkan modul ke BAYANGAN dulu dan tinjau selisihnya sebelum BARU.');
            // Sinkron terakhir: sejak BARU, hak peran modul ini tak lagi
            // mengikuti hibah, jadi samakan dulu. Sedang berjalan → 409.
            try {
                $this->sinkron->jalankan(false, 'UI');
            } catch (SinkronSedangBerjalan $e) {
                abort(409, $e->getMessage());
            }
        }

        DB::transaction(function () use ($modul, $modeBaru, $alasan, $oleh, $sebelum) {
            DB::table('N_HRIS_Akses_Mode_Modul')->updateOrInsert(
                ['Id_Grup_Fitur' => $modul],
                ['Mode' => $modeBaru, 'Updated_At' => now(), 'Updated_By' => $oleh],
            );
            $this->audit->catat('MODE', (string) $modul, 'UBAH_MODE', $sebelum, $modeBaru, null, $alasan);
        });
        ModeAkses::lupakan(); // dalam proses DAN lintas-request
        KeputusanAkses::lupakanCache();

        return ['id' => $modul, 'mode_sebelum' => $sebelum, 'mode' => $modeBaru];
    }

    public function selisih(?int $modul, int $halaman = 1, int $perHalaman = 20): array
    {
        if (! SkemaTersedia::tabel('N_HRIS_Akses_Selisih')) {
            return ['data' => [], 'total' => 0, 'halaman' => 1, 'per_halaman' => $perHalaman];
        }
        $q = $modul ? $this->kueriSelisih($modul) : DB::table('N_HRIS_Akses_Selisih as s');
        $total = (clone $q)->count();
        $data = $q->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 's.Id_User')
            ->leftJoin('N_HRIS_Menus as mn', 'mn.Jenis_page', '=', 's.Jenis_page')
            ->orderByDesc('s.Terakhir_At')
            ->offset(($halaman - 1) * $perHalaman)->limit($perHalaman)
            ->get(['s.Id_Akses_Selisih', 's.Id_User', 'k.Nama', 'k.Kode_Karyawan', 's.Jenis_page', 'mn.Nama_Menu', 's.Aksi',
                's.Keputusan_Lama', 's.Keputusan_Baru', 's.Jumlah', 's.Pertama_At', 's.Terakhir_At'])
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Akses_Selisih, 'id_user' => (int) $r->Id_User, 'nama' => $r->Nama, 'kode_karyawan' => $r->Kode_Karyawan,
                'jenis_page' => $r->Jenis_page, 'nama_menu' => $r->Nama_Menu, 'aksi' => $r->Aksi,
                'lama' => $r->Keputusan_Lama === 'Y', 'baru' => $r->Keputusan_Baru === 'Y',
                'jumlah' => (int) $r->Jumlah, 'pertama' => $r->Pertama_At, 'terakhir' => $r->Terakhir_At,
            ])->all();

        return ['data' => $data, 'total' => $total, 'halaman' => $halaman, 'per_halaman' => $perHalaman];
    }

    /** Hanya selisih yang SUDAH terlihat ($sampai = Terakhir_At terbaru di layar); yang muncul sesudahnya tetap. */
    public function bersihkanSelisih(int $modul, string $alasan, ?string $sampai = null): int
    {
        if (! SkemaTersedia::tabel('N_HRIS_Akses_Selisih')) {
            return 0;
        }
        $n = DB::table('N_HRIS_Akses_Selisih')->whereIn('Id_Akses_Selisih', $this->kueriSelisih($modul)
            ->when($sampai, fn ($q) => $q->where('s.Terakhir_At', '<', \Illuminate\Support\Carbon::parse($sampai)->addSecond()))->select('s.Id_Akses_Selisih'))->delete();
        $this->audit->catat('MODE', (string) $modul, 'BERSIHKAN_SELISIH', ['jumlah' => $n, 'sampai' => $sampai], null, null, $alasan);

        return $n;
    }
}
