<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Atribut kompetensi yang berlaku bagi SATU siklus: salinan beku bila ada, master bila belum.
 *
 * `Flag_Berskor`, `Flag_Radar`, dan `Nilai_Ambang` hidup di master `N_PA_Competency` dan boleh
 * disunting HC kapan saja. Tanpa salinan, mengubah KKM menggeser jaring KKM hasil yang sudah
 * terbit, dan mengubah status berskor mengubah cara hasil siklus berjalan dihitung ulang.
 * Sejak DDL-2026-170 ketiganya dibekukan per siklus saat siklus DIKUNCI (`bekukan()`).
 *
 * Siklus tanpa salinan (dikunci sebelum perubahan ini) tetap membaca master — perilaku lama,
 * tanpa DML. Pembeda "ada salinan" memakai id barisnya, BUKAN COALESCE nilainya: ambang NULL
 * di salinan berarti "tidak ada KKM" dan tidak boleh jatuh ke master.
 */
final class PaKompetensiSiklus
{
    public const TABEL = 'N_PA_Cycle_Competency_Snapshot';

    /** Atribut yang dibekukan. */
    public const KOLOM = ['Flag_Berskor', 'Flag_Radar', 'Nilai_Ambang'];

    /**
     * LEFT JOIN salinan untuk kompetensi beralias `$aliasKompetensi`.
     *
     * @param  int|string  $siklus  id siklus, atau nama kolom berisi id siklus (mis. `p.PA_Cycle_Id`)
     */
    public static function gabung(Builder $q, string $aliasKompetensi, int|string $siklus, string $alias = 'cks'): Builder
    {
        return $q->leftJoin(self::TABEL.' as '.$alias, function ($j) use ($aliasKompetensi, $siklus, $alias) {
            $j->on("{$alias}.PA_Competency_Id", '=', "{$aliasKompetensi}.Id_PA_Competency");

            is_int($siklus)
                ? $j->where("{$alias}.PA_Cycle_Id", '=', $siklus)
                : $j->on("{$alias}.PA_Cycle_Id", '=', $siklus);
        });
    }

    /** Ekspresi SQL satu atribut yang berlaku (tanpa alias kolom). */
    public static function sql(string $kolom, string $aliasKompetensi, string $alias = 'cks'): string
    {
        return "CASE WHEN {$alias}.Id_PA_Cycle_Competency_Snapshot IS NULL "
            ."THEN {$aliasKompetensi}.{$kolom} ELSE {$alias}.{$kolom} END";
    }

    /** Kolom select satu atribut yang berlaku, beralias nama kolom aslinya. */
    public static function kolom(string $kolom, string $aliasKompetensi, string $alias = 'cks'): Expression
    {
        return DB::raw(self::sql($kolom, $aliasKompetensi, $alias)." AS {$kolom}");
    }

    /**
     * Membekukan atribut seluruh kompetensi aktif untuk satu siklus. Aman diulang: kompetensi
     * yang sudah bersalinan tidak ditulis lagi, jadi salinan yang ada tidak pernah tertimpa.
     */
    public static function bekukan(int $cycleId, ?int $olehUserId = null): int
    {
        $sudah = DB::table(self::TABEL)->where('PA_Cycle_Id', $cycleId)->pluck('PA_Competency_Id')
            ->map(fn ($x) => (int) $x)->flip();

        $baris = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Competency', 'Flag_Berskor', 'Flag_Radar', 'Nilai_Ambang'])
            ->reject(fn ($k) => $sudah->has((int) $k->Id_PA_Competency))
            ->map(fn ($k) => [
                'PA_Cycle_Id' => $cycleId,
                'PA_Competency_Id' => (int) $k->Id_PA_Competency,
                'Flag_Berskor' => $k->Flag_Berskor,
                'Flag_Radar' => $k->Flag_Radar,
                'Nilai_Ambang' => $k->Nilai_Ambang,
                'Snapshot_By' => $olehUserId,
            ])->values()->all();

        foreach (array_chunk($baris, 200) as $potongan) {
            DB::table(self::TABEL)->insert($potongan);
        }

        return count($baris);
    }
}
