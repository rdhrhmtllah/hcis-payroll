<?php

namespace App\Services\KPI\PerformanceAppraisal\Balancing;

use App\Services\KPI\PerformanceAppraisal\PaLevelPaResolver;
use Illuminate\Support\Facades\DB;

/**
 * Calon tim penilai sesi Balancing: semua karyawan aktif, tidak terbatas peserta siklus.
 * Jabatan dan level dibaca dari View_Golongan_Sub_Golongan_Level_Jabatan lewat ID_Level_Jabatan.
 */
class PaBalancingTimPenilaiService
{
    /** @return list<array<string, mixed>> */
    public function daftar(): array
    {
        return $this->kueri()->orderBy('k.Nama')->get()->map(fn ($r) => $this->baris($r))->all();
    }

    /**
     * Karyawan aktif yang dipilih sebagai tim penilai, sudah lengkap dengan jabatannya.
     *
     * @return array<string, mixed>|null
     */
    public function satu(string $kodePerusahaan, string $kodeKaryawan): ?array
    {
        $r = $this->kueri()->where('k.Kode_Perusahaan', $kodePerusahaan)->where('k.Kode_Karyawan', $kodeKaryawan)->first();

        return $r === null ? null : $this->baris($r);
    }

    private function kueri()
    {
        // Level = Level PA (DDL-2026-177): koreksi per karyawan ikut, sama dengan peserta PA.
        $lv = PaLevelPaResolver::sqlLevel('g');

        return PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'), 'k', 'g')
            ->leftJoin('N_PA_Level as lv_pa', 'lv_pa.ID_Level', '=', DB::raw($lv))
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')->whereNull('k.Tanggal_Resign')
            ->select([
                'k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'g.nama_jabatan', DB::raw("{$lv} as ID_Level"), DB::raw('COALESCE(lv_pa.Nama_Level, g.nama_level) as nama_level'),
                'd.ID_Divisi', 'd.nama_divisi', 'd.ID_Sub_Divisi', 'd.nama_sub_divisi',
            ]);
    }

    /** @return array<string, mixed> */
    private function baris(object $r): array
    {
        $bersih = fn ($v) => ($v === null || trim((string) $v) === '' || strtoupper(trim((string) $v)) === 'NOT SET') ? null : trim((string) $v);

        return [
            'kunci' => trim((string) $r->Kode_Perusahaan).'|'.trim((string) $r->Kode_Karyawan),
            'kode_perusahaan' => trim((string) $r->Kode_Perusahaan),
            'kode_karyawan' => trim((string) $r->Kode_Karyawan),
            'nama' => trim((string) $r->Nama),
            'jabatan' => $bersih($r->nama_jabatan),
            'level_id' => $r->ID_Level === null ? null : (int) $r->ID_Level,
            'level' => $bersih($r->nama_level),
            'divisi_id' => $r->ID_Divisi === null ? null : (int) $r->ID_Divisi,
            'divisi' => $bersih($r->nama_divisi),
            'sub_divisi_id' => $r->ID_Sub_Divisi === null ? null : (int) $r->ID_Sub_Divisi,
            'sub_divisi' => $bersih($r->nama_sub_divisi),
        ];
    }
}
