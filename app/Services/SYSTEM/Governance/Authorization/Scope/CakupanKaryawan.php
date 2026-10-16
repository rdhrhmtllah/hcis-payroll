<?php

namespace App\Services\SYSTEM\Governance\Authorization\Scope;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use Illuminate\Support\Facades\DB;

/**
 * Cakupan karyawan sebagai KODE, bukan SQL dari database (spec §4.2).
 *
 * Satu jenis = satu aturan. Nilai NULL berarti "milik pemegang sendiri"
 * (divisi/lokasi/tim-nya). Jenis tak dikenal & ENTITAS (belum ada sumber data
 * tepercaya) = TIDAK mencakup — gagal tertutup.
 */
class CakupanKaryawan
{
    public const JENIS = ['SEMUA', 'PHL', 'DIVISI', 'DEPARTEMEN', 'LOKASI', 'TIM', 'LEVEL', 'JABATAN', 'KARYAWAN', 'TEMPLATE'];

    /** Jenis yang boleh menjadi syarat template (bukan SEMUA, bukan template bersarang). */
    public const JENIS_SYARAT = ['PHL', 'DIVISI', 'DEPARTEMEN', 'LOKASI', 'TIM', 'LEVEL', 'JABATAN', 'KARYAWAN'];

    /** Level jabatan PHL — sama dengan SCOPE_GA_PHL (View_Golongan... Id_Level = 21). */
    private const LEVEL_PHL = 21;

    public function __construct(private KeputusanAkses $keputusan) {}

    /** @var array<int, ?object> memo per request (detail pengguna memeriksa banyak baris) */
    private static array $profil = [];

    /** @var array<int, ?object> memo template cakupan {modul, aktif, nama, syarat} (DDL-2026-184) */
    private static array $template = [];

    /** @var array<string, array<string, string>> memo label nilai cakupan per jenis */
    private static array $nama = [];

    /** @var array<string, ?array> memo cakupanAdmin per admin|modul */
    private static array $milikAdmin = [];

    public static function lupakanCache(): void
    {
        self::$profil = [];
        self::$milikAdmin = [];
        self::$nama = [];
        self::$template = [];
    }

    /** Template cakupan (DDL-2026-184); null bila tak ada / tabel belum ada. */
    public function template(int $id): ?object
    {
        if (array_key_exists($id, self::$template)) {
            return self::$template[$id];
        }
        if (! SkemaRbac::punya('template_cakupan')) {
            return self::$template[$id] = null;
        }
        $t = DB::table('N_HRIS_Cakupan_Template')->where('Id_Cakupan_Template', $id)->first(['Nama', 'Id_Grup_Fitur', 'Flag_Aktif']);

        return self::$template[$id] = $t ? (object) [
            'nama' => $t->Nama,
            'modul' => $t->Id_Grup_Fitur !== null ? (int) $t->Id_Grup_Fitur : null,
            'aktif' => $t->Flag_Aktif === 'Y',
            'syarat' => DB::table('N_HRIS_Cakupan_Template_Syarat')->where('Id_Cakupan_Template', $id)
                ->get(['Jenis_Cakupan', 'Nilai'])->map(fn ($r) => ['jenis' => strtoupper($r->Jenis_Cakupan), 'nilai' => $r->Nilai])->all(),
        ] : null;
    }

    /** Template aktif & berlaku untuk peran modul $modul (template lintas modul berlaku di mana pun). */
    public function templateBolehUntuk(int $id, mixed $modul): bool
    {
        $t = $this->template($id);

        return $t !== null && $t->aktif && ($t->modul === null || $t->modul === ($modul !== null ? (int) $modul : null));
    }

    /**
     * Pilihan nilai konkret per jenis cakupan (untuk pengelola tanpa batas).
     *
     * @return array<string, list<array{nilai: string, label: string}>>
     */
    public function pilihanNilai(): array
    {
        $divisi = DB::table('View_Divisi_Sub_Divisi')->whereNotNull('ID_Divisi')->distinct()->orderBy('nama_divisi')
            ->get(['ID_Divisi', 'nama_divisi'])->unique('ID_Divisi')
            ->map(fn ($d) => ['nilai' => (string) $d->ID_Divisi, 'label' => trim((string) $d->nama_divisi)])->values()->all();
        $departemen = DB::table('View_Divisi_Sub_Divisi')->orderBy('nama_divisi')->orderBy('nama_sub_divisi')
            ->get(['ID_DIVISI_SUB_DIVISI', 'nama_divisi', 'nama_sub_divisi'])->unique('ID_DIVISI_SUB_DIVISI')
            ->map(fn ($d) => ['nilai' => (string) $d->ID_DIVISI_SUB_DIVISI, 'label' => trim((string) $d->nama_divisi).' — '.trim((string) $d->nama_sub_divisi)])->values()->all();
        $lokasi = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('Lokasi')->selectRaw('DISTINCT LTRIM(RTRIM(Lokasi)) as l')
            ->orderBy('l')->pluck('l')->filter()->map(fn ($l) => ['nilai' => (string) $l, 'label' => (string) $l])->values()->all();

        $dipakai = DB::table('Karyawan as k')->join('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web');
        $level = (clone $dipakai)->select('g.ID_Level', 'g.nama_level', 'g.Level_Hierarchy')->distinct()->orderBy('g.Level_Hierarchy')->get()
            ->unique('ID_Level')->map(fn ($r) => ['nilai' => (string) $r->ID_Level, 'label' => trim((string) $r->nama_level)])->values()->all();
        $jabatan = (clone $dipakai)->select('g.ID_Jabatan', 'g.nama_jabatan')->distinct()->orderBy('g.nama_jabatan')->get()
            ->unique('ID_Jabatan')->map(fn ($r) => ['nilai' => (string) $r->ID_Jabatan, 'label' => trim((string) $r->nama_jabatan)])->values()->all();

        // KARYAWAN tidak didaftar di sini (terlalu banyak) — layar memakai pencarian karyawan.
        return ['DIVISI' => $divisi, 'DEPARTEMEN' => $departemen, 'LOKASI' => $lokasi, 'LEVEL' => $level, 'JABATAN' => $jabatan];
    }

    /**
     * Tambahkan label terbaca ke baris cakupan ({jenis, nilai}) — nama divisi,
     * jabatan, karyawan, dst. alih-alih ID mentah.
     *
     * @param  list<array{jenis: string, nilai: ?string}>  $baris
     * @return list<array{jenis: string, nilai: ?string, label: string}>
     */
    public function labelkan(array $baris): array
    {
        $perJenis = [];
        foreach ($baris as $c) {
            if (($c['nilai'] ?? null) !== null && $c['nilai'] !== '') {
                $perJenis[strtoupper($c['jenis'])][] = (string) $c['nilai'];
            }
        }
        foreach ($perJenis as $jenis => $nilai) {
            $nilai = array_values(array_diff(array_unique($nilai), array_keys(self::$nama[$jenis] ?? [])));
            if ($nilai === []) {
                continue;
            }
            $ketemu = match ($jenis) {
                'DIVISI' => DB::table('View_Divisi_Sub_Divisi')->whereIn('ID_Divisi', $nilai)->pluck('nama_divisi', 'ID_Divisi'),
                'DEPARTEMEN' => DB::table('View_Divisi_Sub_Divisi')->whereIn('ID_DIVISI_SUB_DIVISI', $nilai)->pluck('nama_sub_divisi', 'ID_DIVISI_SUB_DIVISI'),
                'LEVEL' => DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')->whereIn('ID_Level', $nilai)->pluck('nama_level', 'ID_Level'),
                'JABATAN' => DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')->whereIn('ID_Jabatan', $nilai)->pluck('nama_jabatan', 'ID_Jabatan'),
                'KARYAWAN' => DB::table('Karyawan')->whereIn('UserID_Web', $nilai)->pluck('Nama', 'UserID_Web'),
                'TIM' => DB::table('Karyawan')->whereIn('Kode_Karyawan', $nilai)->pluck('Nama', 'Kode_Karyawan'),
                'TEMPLATE' => collect($nilai)->filter(fn ($n) => ctype_digit($n))->mapWithKeys(fn ($n) => [$n => $this->template((int) $n)?->nama])->filter(),
                default => collect(),
            };
            // Memo per request: tabel penugasan memanggil ini per baris.
            foreach ($nilai as $n) {
                self::$nama[$jenis][$n] = isset($ketemu[$n]) ? trim((string) $ketemu[$n]) : $n;
            }
        }
        $nama = self::$nama;
        $umum = ['SEMUA' => 'Semua karyawan', 'PHL' => 'PHL', 'DIVISI' => 'Divisi', 'DEPARTEMEN' => 'Departemen', 'LOKASI' => 'Lokasi',
            'TIM' => 'Tim', 'LEVEL' => 'Level', 'JABATAN' => 'Jabatan', 'KARYAWAN' => 'Karyawan', 'TEMPLATE' => 'Template'];

        return array_map(function ($c) use ($nama, $umum) {
            $jenis = strtoupper($c['jenis']);
            $judul = $umum[$jenis] ?? $jenis;
            $nilai = $c['nilai'] ?? null;
            if ($nilai === null || $nilai === '') {
                $label = in_array($jenis, ['SEMUA', 'PHL'], true) ? $judul : "{$judul} pemegang";
            } else {
                $label = $judul.': '.($nama[$jenis][(string) $nilai] ?? $nilai);
            }

            return $c + ['label' => $label];
        }, $baris);
    }

    private function profil(int $idUser): ?object
    {
        if (array_key_exists($idUser, self::$profil)) {
            return self::$profil[$idUser];
        }

        return self::$profil[$idUser] = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.UserID_Web', $idUser)
            ->first(['k.Kode_Karyawan', 'k.Lokasi', 'k.Aktif', 'k.ID_Divisi_Sub_Divisi', 'd.ID_Divisi', 'g.Id_Level', 'g.ID_Jabatan']);
    }

    /**
     * Nilai konkret sebuah cakupan: nilai yang tersimpan, atau — bila kosong —
     * nilai milik $idPemilik (divisi/departemen/lokasi/kode untuk TIM).
     * Dipakai agar "TIM (kosong)" milik admin tak berubah arti saat diberikan
     * ke orang lain (review Fase 3b I9).
     */
    public function nilaiNyata(string $jenis, ?string $nilai, int $idPemilik): ?string
    {
        if ($nilai !== null && $nilai !== '') {
            return $nilai;
        }
        $p = $this->profil($idPemilik);

        return match (strtoupper($jenis)) {
            'DIVISI' => $p?->ID_Divisi !== null ? (string) $p->ID_Divisi : null,
            'DEPARTEMEN' => $p?->ID_Divisi_Sub_Divisi !== null ? (string) $p->ID_Divisi_Sub_Divisi : null,
            'LOKASI' => $p?->Lokasi !== null ? trim((string) $p->Lokasi) : null,
            'TIM' => $p?->Kode_Karyawan,
            'LEVEL' => $p?->Id_Level !== null ? (string) $p->Id_Level : null,
            'JABATAN' => $p?->ID_Jabatan !== null ? (string) $p->ID_Jabatan : null,
            'KARYAWAN' => (string) $idPemilik,
            default => null, // TEMPLATE selalu bernilai (ditangani di atas)
        };
    }

    /** Apakah cakupan (jenis, nilai) milik $idPemilik mencakup karyawan $idTarget. */
    public function mencakup(string $jenis, ?string $nilai, int $idPemilik, int $idTarget): bool
    {
        $jenis = strtoupper(trim($jenis));
        if ($jenis === 'SEMUA') {
            return true;
        }
        if ($jenis === 'TEMPLATE') {
            // Beda jenis = DAN, sejenis = ATAU. Nonaktif/kosong/hilang = tidak mencakup.
            $t = ctype_digit((string) $nilai) ? $this->template((int) $nilai) : null;
            if (! $t || ! $t->aktif || $t->syarat === []) {
                return false;
            }
            foreach (collect($t->syarat)->groupBy('jenis') as $j => $baris) {
                if (! in_array($j, self::JENIS_SYARAT, true)
                    || ! $baris->contains(fn ($c) => $this->mencakup($j, $c['nilai'], $idPemilik, $idTarget))) {
                    return false;
                }
            }

            return true;
        }
        $t = $this->profil($idTarget);
        if (! $t) {
            return false;
        }
        $p = in_array($jenis, ['DIVISI', 'DEPARTEMEN', 'LOKASI', 'TIM', 'LEVEL', 'JABATAN'], true) && ($nilai === null || $nilai === '') ? $this->profil($idPemilik) : null;

        return match ($jenis) {
            'PHL' => (int) $t->Id_Level === self::LEVEL_PHL,
            'DIVISI' => (string) ($nilai ?: $p?->ID_Divisi) !== '' && (string) $t->ID_Divisi === (string) ($nilai ?: $p?->ID_Divisi),
            'DEPARTEMEN' => (string) ($nilai ?: $p?->ID_Divisi_Sub_Divisi) !== '' && (string) $t->ID_Divisi_Sub_Divisi === (string) ($nilai ?: $p?->ID_Divisi_Sub_Divisi),
            'LOKASI' => trim((string) ($nilai ?: $p?->Lokasi)) !== '' && strcasecmp(trim((string) $t->Lokasi), trim((string) ($nilai ?: $p?->Lokasi))) === 0,
            'TIM' => DB::table('HRIS_Karyawan_Team')
                ->where('Kode_Karyawan_Team', $nilai ?: $p?->Kode_Karyawan)
                ->where('Kode_Karyawan', $t->Kode_Karyawan)->exists(),
            'LEVEL' => (string) ($nilai ?: $p?->Id_Level) !== '' && (string) $t->Id_Level === (string) ($nilai ?: $p?->Id_Level),
            'JABATAN' => (string) ($nilai ?: $p?->ID_Jabatan) !== '' && (string) $t->ID_Jabatan === (string) ($nilai ?: $p?->ID_Jabatan),
            'KARYAWAN' => (string) ($nilai ?: $idPemilik) === (string) $idTarget,
            default => false,
        };
    }

    /**
     * Cakupan admin di sebuah modul: daftar {jenis, nilai} dari penugasan role
     * ADMIN aktif & belum kedaluwarsa di modul itu. null = SEMUA (System Admin,
     * atau ada penugasan tanpa baris cakupan / bercakupan SEMUA). [] = tidak
     * mengelola modul itu.
     *
     * @return list<array{jenis: string, nilai: ?string}>|null
     */
    public function cakupanAdmin(User $admin, int $modul): ?array
    {
        $kunci = $admin->Id_Users.'|'.$modul;
        if (! array_key_exists($kunci, self::$milikAdmin)) {
            self::$milikAdmin[$kunci] = $this->hitungCakupanAdmin($admin, $modul);
        }

        return self::$milikAdmin[$kunci];
    }

    private function hitungCakupanAdmin(User $admin, int $modul): ?array
    {
        if ($this->keputusan->apakahSystemAdmin($admin)) {
            return null;
        }
        // Hanya penugasan admin bertanggung jawab AKSES, aktif & belum kedaluwarsa.
        $penugasan = $this->keputusan->kueriPenugasanAdmin($admin, true)->where('rf.Id_Grup_Fitur', $modul)->pluck('ru.Id');
        if ($penugasan->isEmpty()) {
            return [];
        }
        $cakupan = SkemaRbac::punya('model_baru')
            ? DB::table('N_HRIS_Role_User_Cakupan')->whereIn('Id_Role_User', $penugasan)->where('Flag_Aktif', 'Y')
                ->get(['Id_Role_User', 'Jenis_Cakupan', 'Nilai'])->groupBy('Id_Role_User')
            : collect();

        $hasil = [];
        foreach ($penugasan as $id) {
            $baris = $cakupan->get($id, collect());
            if ($baris->isEmpty() || $baris->contains(fn ($c) => strtoupper($c->Jenis_Cakupan) === 'SEMUA')) {
                return null; // salah satu penugasannya tanpa batas
            }
            foreach ($baris as $c) {
                $hasil[] = ['jenis' => strtoupper($c->Jenis_Cakupan), 'nilai' => $c->Nilai];
            }
        }

        return $hasil;
    }

    /** Admin mengelola modul ini DAN karyawan target ada dalam cakupannya. */
    public function adminMencakup(User $admin, int $modul, int $idTarget): bool
    {
        $cakupan = $this->cakupanAdmin($admin, $modul);
        if ($cakupan === null) {
            return true;
        }
        foreach ($cakupan as $c) {
            if ($this->mencakup($c['jenis'], $c['nilai'], (int) $admin->Id_Users, $idTarget)) {
                return true;
            }
        }

        return false;
    }
}
