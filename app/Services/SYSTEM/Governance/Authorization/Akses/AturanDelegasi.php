<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Menu\MenuDelegationPolicyService;
use App\Services\SYSTEM\Governance\Authorization\Scope\CakupanKaryawan;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Facades\DB;

/**
 * Aturan anti-eskalasi (spec §6), dicek di server sebelum setiap perubahan:
 *  1. tidak mengubah akses diri sendiri (termasuk peran yang dipegang sendiri);
 *  2. tingkat (master N_HRIS_Tingkat_Peran, DDL-2026-181) dihitung PER MODUL:
 *     hanya boleh mengangkat/mencabut/mengubah tingkat yang lebih rendah, dan
 *     karyawan sasaran harus bertingkat lebih rendah di modul itu. Peran admin
 *     butuh kemampuan "angkat" (Super Admin modul); peran pengguna & grant butuh
 *     "tugaskan"; matriks butuh "ubah matriks". Peran admin pembawa halaman
 *     SYSTEM_ONLY (mis. admin ESS) tetap hanya System Admin;
 *  3. pengubah = System Admin atau admin bertanggung jawab AKSES di modul itu;
 *  4. karyawan sasaran dalam cakupan admin; cakupan yang diberikan ⊆ miliknya;
 *  5. halaman yang ikut diberikan — langsung, lewat PERAN, atau lewat matriks —
 *     harus boleh didelegasikan (kebijakan menu: bukan SYSTEM_ONLY/RESTRICTED
 *     tanpa izin, bukan halaman pengatur akses), milik modul itu, dan bisa
 *     dibuka admin sendiri (yang diberikan ⊆ milik pemberi; tingkat yang boleh
 *     mengangkat — Super Admin modul — mengelola tanpa harus memakai);
 *  6. matriks hak hanya bisa diubah di modul BARU.
 * Melempar AksesDitolak dengan pesan yang bisa ditampilkan apa adanya.
 */
class AturanDelegasi
{
    /** @var array<string, array<string, bool>> memo kebijakan delegasi per admin|modul */
    private array $memoKebijakan = [];

    public function __construct(
        private KeputusanAkses $keputusan,
        private CakupanKaryawan $cakupan,
        private ModeAkses $mode,
        private MenuDelegationPolicyService $kebijakan,
        private TingkatPeran $tingkat,
    ) {}

    private function tolak(string $pesan): never
    {
        throw new AksesDitolak($pesan);
    }

    private function sa(User $u): bool
    {
        return $this->keputusan->apakahSystemAdmin($u);
    }

    /** Karyawan sasaran harus bertingkat lebih rendah dari pengubah di modul ini. */
    private function sasaranDiBawah(string $aktor, int $grup, int $idTarget): void
    {
        if ($this->tingkat->peringkat($this->keputusan->tingkatSasaranDiModul($idTarget, $grup)) >= $this->tingkat->peringkat($aktor)) {
            $this->tolak('Akses karyawan setingkat atau di atas Anda di modul ini hanya dapat diubah tingkat di atasnya.');
        }
    }

    /** Peran setingkat admin: butuh kemampuan "angkat" dan peringkat peran < pengubah. */
    private function bolehAngkatTingkat(string $aktor, string $tingkatPeran, string $kata): void
    {
        if (! $this->tingkat->boleh($aktor, TingkatPeran::ANGKAT) || $this->tingkat->peringkat($tingkatPeran) >= $this->tingkat->peringkat($aktor)) {
            $nama = strtolower((string) ($this->tingkat->semua()[$tingkatPeran]['Nama_Tingkat'] ?? $tingkatPeran));
            $this->tolak("Peran {$nama} hanya dapat {$kata} tingkat di atasnya (Super Admin modul atau System Admin).");
        }
    }

    private function bolehTugaskan(string $aktor): void
    {
        if (! $this->tingkat->boleh($aktor, TingkatPeran::TUGASKAN)) {
            $this->tolak('Tingkat Anda di modul ini tidak dapat menugaskan peran atau memberi akses.');
        }
    }

    /** Untuk layar: peran admin ini boleh diangkat/dicabut pengelola non-System Admin? */
    public function bolehAngkatAdmin(object $peran): bool
    {
        try {
            $this->peranAdminTanpaHalamanSistem($peran, 'menugaskannya');

            return true;
        } catch (AksesDitolak) {
            return false;
        }
    }

    /** Spec aturan 1: tanggung jawab yang diberikan ⊆ tanggung jawab penugasan admin pemberi di modul itu. */
    private function tanggungJawabDalamMilik(User $admin, int $grup, array $tanggungJawab): void
    {
        $milik = DB::table('N_HRIS_Role_User_Tanggung_Jawab')->where('Flag_Aktif', 'Y')
            ->whereIn('Id_Role_User', $this->keputusan->kueriPenugasanAdmin($admin, true)->where('rf.Id_Grup_Fitur', $grup)->select('ru.Id'))
            ->pluck('Kode')->map(fn ($k) => strtoupper((string) $k))->unique()->all();
        if (array_diff(array_map('strtoupper', $tanggungJawab), $milik) !== []) {
            $this->tolak('Tanggung jawab yang diberikan melebihi tanggung jawab Anda.');
        }
    }

    private function bukanDiriSendiri(User $admin, int $idTarget): void
    {
        if ((int) $admin->Id_Users === $idTarget) {
            $this->tolak('Anda tidak dapat mengubah akses diri sendiri.');
        }
    }

    /** Peran aktif; Jenis_Role dibaca bila DDL-2026-165 sudah ada, selain itu dari config. */
    /** $termasukNonaktif: untuk MENCABUT — penugasan peran yang sudah dimatikan tetap bisa dibersihkan. */
    public function peran(int $idRole, bool $termasukNonaktif = false): object
    {
        $kolom = ['Id_Role', 'Role_Code', 'Role_Name', 'Id_Grup_Fitur'];
        if (SkemaRbac::punya('model_baru')) {
            $kolom[] = 'Jenis_Role';
        }
        if (SkemaRbac::punya('tingkat')) {
            $kolom[] = 'Kode_Tingkat';
        }
        $r = DB::table('N_HRIS_Role_Functional')->where('Id_Role', $idRole)
            ->when(! $termasukNonaktif, fn ($q) => $q->where('Flag_Aktif', 'Y'))->first($kolom);
        if (! $r) {
            $this->tolak('Peran tidak ditemukan atau nonaktif.');
        }
        $r->Jenis_Role ??= in_array(strtoupper((string) $r->Role_Code), HibahTataKelola::peranAdmin(), true) ? 'ADMIN' : 'AKSES';

        return $r;
    }

    /** Halaman yang dibawa sebuah peran menurut mode modulnya (BARU: Role_Hak; lainnya: hibah tersaring). */
    public function halamanPeran(object $peran): array
    {
        $grup = $peran->Id_Grup_Fitur !== null ? (int) $peran->Id_Grup_Fitur : null;
        if ($grup === null) {
            return [];
        }
        if ($this->mode->modeModul($grup) === ModeAkses::BARU && SkemaRbac::punya('model_baru')) {
            return DB::table('N_HRIS_Role_Hak')->where('Id_Role', $peran->Id_Role)->where('Flag_Aktif', 'Y')
                ->distinct()->pluck('Jenis_page')->map(fn ($p) => (string) $p)->all();
        }
        $admin = in_array(strtoupper((string) $peran->Role_Code), HibahTataKelola::peranAdmin(), true);

        return DB::table('N_HRIS_Grup_Fitur_Map')->where('Id_Grup_Fitur', $grup)->where('Flag_Hibah_Role', 'Y')
            ->distinct()->pluck('Jenis_page')->map(fn ($p) => (string) $p)
            ->filter(fn ($p) => $admin || ! HibahTataKelola::apakahHalamanTataKelola($p))->values()->all();
    }

    /** Halaman boleh didelegasikan admin di modul ini (fail-closed bila kebijakan tak terbaca). */
    private function bolehDidelegasikan(User $admin, string $halaman, int $grup): bool
    {
        if (HibahTataKelola::apakahHalamanTataKelola($halaman)) {
            return false;
        }
        $kunci = $admin->Id_Users.'|'.$grup;
        if (! isset($this->memoKebijakan[$kunci])) {
            try {
                $this->memoKebijakan[$kunci] = $this->kebijakan->policiesForGroup($admin, $grup)
                    ->mapWithKeys(fn ($m) => [HibahTataKelola::normalisasi((string) $m->Jenis_page) => (bool) $m->can_delegate])->all();
            } catch (\Throwable) {
                $this->memoKebijakan[$kunci] = [];
            }
        }

        return $this->memoKebijakan[$kunci][HibahTataKelola::normalisasi($halaman)] ?? false;
    }

    /**
     * Review Super Admin C1: peran ADMIN yang diangkat/dicabut Super Admin tak
     * boleh membawa halaman SYSTEM_ONLY (kebijakan delegasi) — layar tata kelola
     * sistem ikut di sini. Gagal tertutup bila kebijakan tak terbaca.
     */
    private function peranAdminTanpaHalamanSistem(object $peran, string $aksi): void
    {
        $sistem = rescue(fn () => DB::table('N_HRIS_Menu_Delegation_Policy')->where('Flag_Aktif', 'Y')->where('Policy_Mode', 'SYSTEM_ONLY')
            ->pluck('Jenis_page')->map(fn ($p) => HibahTataKelola::normalisasi((string) $p))->all(), null, false);
        if ($sistem === null) {
            $this->tolak("Kebijakan delegasi tak terbaca; peran admin hanya dapat {$aksi} System Admin.");
        }
        $kena = collect($this->halamanPeran($peran))
            ->filter(fn ($p) => in_array(HibahTataKelola::normalisasi($p), $sistem, true))->values()->all();
        if ($kena !== []) {
            $nama = DB::table('N_HRIS_Menus')->whereIn('Jenis_page', array_slice($kena, 0, 5))->pluck('Nama_Menu')->unique()->implode(', ');
            $this->tolak("Peran admin ini membawa halaman khusus System Admin ({$nama}); hanya System Admin yang dapat {$aksi}.");
        }
    }

    /** Aturan 5 untuk sekumpulan halaman yang akan diberikan. */
    private function halamanBolehDiberikan(User $admin, array $halaman, int $grup, string $konteks, string $aktor): void
    {
        // Super Admin modul ke atas mengelola tanpa harus memakai halamannya. Diukur dari
        // PERINGKAT, bukan kemampuan "angkat" yang bisa dicentang untuk Admin (review M1).
        $tanpaSyaratBuka = $this->tingkat->peringkat($aktor) >= $this->tingkat->peringkat(TingkatPeran::SUPER_ADMIN);
        $terlarang = [];
        foreach ($halaman as $p) {
            if ($this->mode->modulHalaman($p) !== $grup || ! $this->bolehDidelegasikan($admin, $p, $grup)
                || (! $tanpaSyaratBuka && ! $this->keputusan->boleh($admin, $p))) {
                $terlarang[] = $p;
            }
        }
        if ($terlarang !== []) {
            $nama = DB::table('N_HRIS_Menus')->whereIn('Jenis_page', array_slice($terlarang, 0, 5))->pluck('Nama_Menu')->unique()->implode(', ');
            $this->tolak("{$konteks} membawa halaman yang hanya dapat diberikan System Admin atau di luar wewenang Anda: ".($nama ?: implode(', ', array_slice($terlarang, 0, 5))).'.');
        }
    }

    private function modulKelola(User $admin, mixed $modul, ?int $idTarget = null): int
    {
        if ($modul === null || ! in_array((int) $modul, $this->keputusan->modulKelolaAkses($admin) ?? [], true)) {
            $this->tolak('Peran atau halaman ini di luar modul yang Anda kelola.');
        }
        if ($idTarget !== null && ! $this->cakupan->adminMencakup($admin, (int) $modul, $idTarget)) {
            $this->tolak('Karyawan ini di luar cakupan Anda.');
        }

        return (int) $modul;
    }

    /** @param list<array{jenis: string, nilai: ?string}> $cakupanBaru [] = SEMUA */
    public function periksaPenugasan(User $admin, int $idRole, int $idTarget, array $cakupanBaru, array $tanggungJawab = [], bool $abaikanCakupan = false): object
    {
        $this->bukanDiriSendiri($admin, $idTarget);
        $peran = $this->peran($idRole);
        foreach ($cakupanBaru as $c) {
            if (! in_array(strtoupper($c['jenis'] ?? ''), CakupanKaryawan::JENIS, true)) {
                $this->tolak('Jenis cakupan tidak dikenal.');
            }
        }
        foreach ($cakupanBaru as $c) {
            if (strtoupper($c['jenis']) === 'TEMPLATE'
                && ! $this->cakupan->templateBolehUntuk((int) ($c['nilai'] ?? 0), $peran->Id_Grup_Fitur)) {
                $this->tolak('Template cakupan tidak aktif atau bukan untuk modul peran ini.');
            }
        }
        $tingkatPeran = $this->tingkat->dariPeran($peran);
        if ($tanggungJawab !== [] && ! $this->tingkat->setingkatAdmin($tingkatPeran)) {
            $this->tolak('Tanggung jawab hanya untuk peran admin.');
        }
        if ($this->sa($admin)) {
            return $peran;
        }
        $grup = $this->modulKelola($admin, $peran->Id_Grup_Fitur, $idTarget);
        $aktor = $this->keputusan->tingkatDiModul($admin, $grup);
        $this->sasaranDiBawah($aktor, $grup, $idTarget);
        if ($this->tingkat->setingkatAdmin($tingkatPeran)) {
            $this->bolehAngkatTingkat($aktor, $tingkatPeran, 'ditugaskan');
            $this->peranAdminTanpaHalamanSistem($peran, 'menugaskannya');
            if ($tanggungJawab !== []) {
                $this->tanggungJawabDalamMilik($admin, $grup, $tanggungJawab);
            }
        } else {
            $this->bolehTugaskan($aktor);
            $this->halamanBolehDiberikan($admin, $this->halamanPeran($peran), $grup, 'Peran ini', $aktor);
        }

        $milik = $abaikanCakupan ? null : $this->cakupan->cakupanAdmin($admin, $grup);
        if ($milik !== null) {
            if ($cakupanBaru === []) {
                $this->tolak('Cakupan Anda terbatas; peran ini tidak bisa diberikan tanpa batas.');
            }
            foreach ($cakupanBaru as $c) {
                $idAdmin = (int) $admin->Id_Users;
                $cocok = collect($milik)->contains(fn ($m) => $m['jenis'] === strtoupper($c['jenis'])
                    && (string) $this->cakupan->nilaiNyata($m['jenis'], $m['nilai'], $idAdmin)
                        === (string) $this->cakupan->nilaiNyata($c['jenis'], $c['nilai'] ?? null, $idAdmin));
                if (! $cocok) {
                    $this->tolak('Cakupan yang diberikan melebihi cakupan Anda.');
                }
            }
        }

        return $peran;
    }

    public function periksaCabut(User $admin, object $penugasan): void
    {
        $this->bukanDiriSendiri($admin, (int) $penugasan->UserId_Web);
        if ($this->sa($admin)) {
            return;
        }
        $peran = $this->peran((int) $penugasan->Id_Role, true);
        $grup = $this->modulKelola($admin, $peran->Id_Grup_Fitur, (int) $penugasan->UserId_Web);
        $aktor = $this->keputusan->tingkatDiModul($admin, $grup);
        $this->sasaranDiBawah($aktor, $grup, (int) $penugasan->UserId_Web);
        $tingkatPeran = $this->tingkat->dariPeran($peran);
        if ($this->tingkat->setingkatAdmin($tingkatPeran)) {
            $this->bolehAngkatTingkat($aktor, $tingkatPeran, 'dicabut');
            $this->peranAdminTanpaHalamanSistem($peran, 'mencabutnya');
        } else {
            $this->bolehTugaskan($aktor);
        }
    }

    public function periksaGrant(User $admin, string $halaman, int $idTarget, bool $wajibAdaDiMenu = true): void
    {
        $this->bukanDiriSendiri($admin, $idTarget);
        if ($wajibAdaDiMenu && ! DB::table('N_HRIS_Menus')->where('Jenis_page', $halaman)->exists()) {
            $this->tolak('Halaman tidak dikenal.');
        }
        if ($this->sa($admin)) {
            return;
        }
        $grup = $this->modulKelola($admin, $this->mode->modulHalaman($halaman), $idTarget);
        $aktor = $this->keputusan->tingkatDiModul($admin, $grup);
        $this->sasaranDiBawah($aktor, $grup, $idTarget);
        $this->bolehTugaskan($aktor);
        $this->halamanBolehDiberikan($admin, [$halaman], $grup, 'Akses ini', $aktor);
    }

    /** @param list<array{jenis_page: string, aksi: list<string>}> $hakBaru */
    public function periksaUbahHak(User $admin, int $idRole, array $hakBaru, array $hakLama): object
    {
        $peran = $this->peran($idRole);
        if ($this->mode->modeModul($peran->Id_Grup_Fitur !== null ? (int) $peran->Id_Grup_Fitur : null) !== ModeAkses::BARU) {
            $this->tolak('Matriks hak hanya bisa diubah di modul yang sudah BARU; di mode lain hak disamakan otomatis dari hibah.');
        }
        if ($this->sa($admin)) {
            return $peran;
        }
        $grup = $this->modulKelola($admin, $peran->Id_Grup_Fitur);
        $aktor = $this->keputusan->tingkatDiModul($admin, $grup);
        if (! $this->tingkat->boleh($aktor, TingkatPeran::UBAH_MATRIKS)) {
            $this->tolak('Matriks hak hanya dapat diubah Super Admin modul atau System Admin.');
        }
        $tingkatPeran = $this->tingkat->dariPeran($peran);
        if ($this->tingkat->peringkat($tingkatPeran) >= $this->tingkat->peringkat($aktor)) {
            $this->tolak('Hak peran setingkat atau di atas Anda hanya dapat diubah tingkat di atasnya.');
        }
        // Peran admin pembawa halaman SYSTEM_ONLY hanya System Admin — termasuk matriksnya:
        // membuang halaman itu akan membuat perannya "lolos" diangkat Super Admin (review I2).
        if ($this->tingkat->setingkatAdmin($tingkatPeran)) {
            $this->peranAdminTanpaHalamanSistem($peran, 'mengubah haknya');
        }
        if (DB::table('N_HRIS_Role_User')->where('UserId_Web', $admin->Id_Users)->where('Id_Role', $idRole)->where('Flag_Aktif', 'Y')->exists()) {
            $this->tolak('Anda memegang peran ini; hak peran yang Anda pegang hanya dapat diubah admin lain.');
        }
        // Halaman yang DITAMBAHKAN — atau yang aksinya BERTAMBAH (review I2) — harus boleh
        // didelegasikan; halaman yang tetap sama tak diperiksa ulang.
        $aksiLama = collect($hakLama)->mapWithKeys(fn ($h) => [HibahTataKelola::normalisasi($h['jenis_page']) => array_map('strtoupper', (array) $h['aksi'])]);
        $ditambah = collect($hakBaru)->filter(function ($h) use ($aksiLama) {
            $k = HibahTataKelola::normalisasi($h['jenis_page']);

            return ! $aksiLama->has($k) || array_diff(array_map('strtoupper', (array) $h['aksi']), $aksiLama[$k]) !== [];
        })->pluck('jenis_page')->values()->all();
        $this->halamanBolehDiberikan($admin, $ditambah, $grup, 'Perubahan ini', $aktor);

        // Sel halaman pengatur akses tak boleh disentuh selain System Admin.
        $tk = fn (array $hak) => collect($hak)->filter(fn ($h) => HibahTataKelola::apakahHalamanTataKelola($h['jenis_page']))
            ->mapWithKeys(fn ($h) => [HibahTataKelola::normalisasi($h['jenis_page']) => collect($h['aksi'])->sort()->values()->all()])->all();
        if ($tk($hakBaru) != $tk($hakLama)) {
            $this->tolak('Hak atas halaman pengatur akses hanya dapat diubah System Admin.');
        }

        return $peran;
    }

    /** Pratinjau hanya dalam lingkup yang sama dengan perubahannya (review I7). */
    public function periksaLihatPeran(User $admin, int $idRole): void
    {
        if ($this->sa($admin)) {
            return;
        }
        $this->modulKelola($admin, $this->peran($idRole)->Id_Grup_Fitur);
    }
}
