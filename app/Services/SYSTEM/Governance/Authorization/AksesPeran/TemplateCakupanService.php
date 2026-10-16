<?php

namespace App\Services\SYSTEM\Governance\Authorization\AksesPeran;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\AksesDitolak;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use App\Services\SYSTEM\Governance\Authorization\Scope\CakupanKaryawan;
use Illuminate\Support\Facades\DB;

/**
 * Template cakupan bernama (DDL-2026-184). Template berisi nilai konkret, jadi
 * hanya pengelola TANPA batas cakupan di modulnya (atau System Admin) yang boleh
 * membuat/memakainya — sama dengan aturan memilih nilai konkret.
 */
class TemplateCakupanService
{
    public function __construct(
        private CakupanKaryawan $cakupan,
        private KeputusanAkses $keputusan,
        private AuditAkses $audit,
    ) {}

    public function tersedia(): bool
    {
        return SkemaRbac::punya('template_cakupan');
    }

    /** Boleh membuat/mengubah template untuk modul $modul (null = lintas modul, khusus System Admin). */
    public function bolehKelola(User $u, ?int $modul): bool
    {
        if ($this->keputusan->apakahSystemAdmin($u)) {
            return true;
        }

        return $modul !== null && $this->cakupan->cakupanAdmin($u, $modul) === null;
    }

    /**
     * Template aktif yang boleh dipakai $u. $modul = lingkup halaman (null = semua).
     *
     * @return list<array{id: int, nama: string, keterangan: ?string, modul: ?string, pemakai: int, syarat: list<array>}>
     */
    public function daftar(User $u, ?array $modul): array
    {
        if (! $this->tersedia()) {
            return [];
        }
        $sa = $this->keputusan->apakahSystemAdmin($u);
        $kode = DB::table('N_HRIS_Master_Grup_Fitur')->pluck('Kode_Grup_Fitur', 'Id_Grup_Fitur');
        $baris = DB::table('N_HRIS_Cakupan_Template')->where('Flag_Aktif', 'Y')->orderBy('Nama')
            ->get(['Id_Cakupan_Template', 'Nama', 'Keterangan', 'Id_Grup_Fitur'])
            ->filter(function ($t) use ($sa, $u, $modul) {
                $m = $t->Id_Grup_Fitur !== null ? (int) $t->Id_Grup_Fitur : null;
                if ($m !== null && $modul !== null && ! in_array($m, $modul, true)) {
                    return false;
                }
                if ($sa) {
                    return true;
                }
                // Template lintas modul: boleh dipakai bila tanpa batas di salah satu modul halaman ini.
                $calon = $m !== null ? [$m] : ($modul ?? []);

                return collect($calon)->contains(fn ($x) => $this->bolehKelola($u, (int) $x));
            });
        $syarat = DB::table('N_HRIS_Cakupan_Template_Syarat')->whereIn('Id_Cakupan_Template', $baris->pluck('Id_Cakupan_Template'))
            ->get(['Id_Cakupan_Template', 'Jenis_Cakupan', 'Nilai'])->groupBy('Id_Cakupan_Template');
        $pemakai = DB::table('N_HRIS_Role_User_Cakupan as c')->join('N_HRIS_Role_User as ru', 'ru.Id', '=', 'c.Id_Role_User')
            ->where('c.Flag_Aktif', 'Y')->where('ru.Flag_Aktif', 'Y')->where('c.Jenis_Cakupan', 'TEMPLATE')
            ->whereIn('c.Nilai', $baris->pluck('Id_Cakupan_Template')->map(fn ($v) => (string) $v)->all() ?: ['-'])
            ->selectRaw('c.Nilai as id, COUNT(*) as n')->groupBy('c.Nilai')->pluck('n', 'id');

        return $baris->map(fn ($t) => [
            'id' => (int) $t->Id_Cakupan_Template,
            'nama' => $t->Nama,
            'keterangan' => $t->Keterangan,
            'modul' => $t->Id_Grup_Fitur !== null ? ($kode[$t->Id_Grup_Fitur] ?? null) : null,
            'pemakai' => (int) ($pemakai[(string) $t->Id_Cakupan_Template] ?? 0),
            'syarat' => $this->cakupan->labelkan(collect($syarat->get($t->Id_Cakupan_Template, []))
                ->map(fn ($s) => ['jenis' => strtoupper($s->Jenis_Cakupan), 'nilai' => $s->Nilai])->values()->all()),
        ])->values()->all();
    }

    /**
     * Buat (id null) atau ubah template. $syarat: list {jenis, nilai}.
     *
     * @param  list<array{jenis: string, nilai: ?string}>  $syarat
     */
    public function simpan(User $u, ?int $id, string $nama, ?string $keterangan, ?int $modul, array $syarat): int
    {
        abort_unless($this->tersedia(), 422, 'Template cakupan belum tersedia (DDL-2026-184 belum dijalankan).');
        $syarat = collect($syarat)->map(fn ($s) => ['jenis' => strtoupper(trim($s['jenis'])), 'nilai' => ($s['nilai'] ?? '') !== '' ? (string) $s['nilai'] : null])
            ->unique(fn ($s) => $s['jenis'].'|'.$s['nilai'])->values()->all();
        abort_if($syarat === [], 422, 'Template harus punya minimal satu syarat.');
        foreach ($syarat as $s) {
            abort_unless(in_array($s['jenis'], CakupanKaryawan::JENIS_SYARAT, true), 422, 'Jenis syarat tidak dikenal.');
        }
        if ($id !== null) {
            $lama = DB::table('N_HRIS_Cakupan_Template')->where('Id_Cakupan_Template', $id)->first();
            abort_unless($lama, 404, 'Template tidak ditemukan.');
            $modul = $lama->Id_Grup_Fitur !== null ? (int) $lama->Id_Grup_Fitur : null; // modul tak berpindah
        }
        if (! $this->bolehKelola($u, $modul)) {
            throw new AksesDitolak($modul === null
                ? 'Template lintas modul hanya untuk System Admin.'
                : 'Hanya pengelola tanpa batas cakupan di modul ini yang boleh mengatur template.');
        }

        return DB::transaction(function () use ($u, $id, $nama, $keterangan, $modul, $syarat) {
            $isi = ['Nama' => trim($nama), 'Keterangan' => $keterangan !== null && trim($keterangan) !== '' ? trim($keterangan) : null];
            if ($id === null) {
                $id = (int) DB::table('N_HRIS_Cakupan_Template')->insertGetId($isi + [
                    'Id_Grup_Fitur' => $modul, 'Created_By' => (int) $u->Id_Users,
                ], 'Id_Cakupan_Template');
                $sebelum = null;
            } else {
                $sebelum = $this->cakupan->template($id);
                DB::table('N_HRIS_Cakupan_Template')->where('Id_Cakupan_Template', $id)
                    ->update($isi + ['Updated_At' => now(), 'Updated_By' => (int) $u->Id_Users]);
                DB::table('N_HRIS_Cakupan_Template_Syarat')->where('Id_Cakupan_Template', $id)->delete();
            }
            foreach ($syarat as $s) {
                DB::table('N_HRIS_Cakupan_Template_Syarat')->insert(['Id_Cakupan_Template' => $id, 'Jenis_Cakupan' => $s['jenis'], 'Nilai' => $s['nilai']]);
            }
            CakupanKaryawan::lupakanCache();
            $this->audit->catat('CAKUPAN', (string) $id, $sebelum ? 'UBAH_TEMPLATE' : 'BUAT_TEMPLATE',
                $sebelum ? ['nama' => $sebelum->nama, 'syarat' => $sebelum->syarat] : null,
                ['nama' => $isi['Nama'], 'modul' => $modul, 'syarat' => $syarat]);

            return $id;
        });
    }

    /** Nonaktifkan template: penugasan yang memakainya berhenti mencakup siapa pun. */
    public function nonaktifkan(User $u, int $id, string $alasan): void
    {
        abort_unless($this->tersedia(), 422, 'Template cakupan belum tersedia.');
        $t = DB::table('N_HRIS_Cakupan_Template')->where('Id_Cakupan_Template', $id)->first();
        abort_unless($t && $t->Flag_Aktif === 'Y', 404, 'Template tidak ditemukan.');
        $modul = $t->Id_Grup_Fitur !== null ? (int) $t->Id_Grup_Fitur : null;
        if (! $this->bolehKelola($u, $modul)) {
            throw new AksesDitolak('Anda tidak berwenang mengatur template ini.');
        }
        DB::table('N_HRIS_Cakupan_Template')->where('Id_Cakupan_Template', $id)
            ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => (int) $u->Id_Users]);
        CakupanKaryawan::lupakanCache();
        $this->audit->catat('CAKUPAN', (string) $id, 'NONAKTIF_TEMPLATE', ['nama' => $t->Nama], null, null, $alasan);
    }

    /** Jumlah penugasan aktif yang memakai template (untuk peringatan sebelum menonaktifkan). */
    public function jumlahPemakai(int $id): int
    {
        return DB::table('N_HRIS_Role_User_Cakupan as c')->join('N_HRIS_Role_User as ru', 'ru.Id', '=', 'c.Id_Role_User')
            ->where('c.Flag_Aktif', 'Y')->where('ru.Flag_Aktif', 'Y')
            ->where('c.Jenis_Cakupan', 'TEMPLATE')->where('c.Nilai', (string) $id)->count();
    }
}
