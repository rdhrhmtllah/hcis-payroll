<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;

/**
 * Level PA seorang karyawan — SATU pintu (DDL-2026-177).
 *
 * Level HRIS sebagian karyawan tidak sesuai pekerjaannya (mis. SENIOR STAFF yang bekerja sebagai
 * OFFICER dan punya bawahan). Koreksi `N_PA_Karyawan_Level_PA` mengganti level itu KHUSUS untuk PA;
 * HRIS tidak diubah. Level PA memakai ID_Level HRIS yang sudah ada, sehingga seluruh master PA
 * (bobot, komposisi penilai, matriks kompetensi & soal, grup kurva, pengecualian) tetap berlaku apa adanya.
 *
 * Dipakai saat peserta siklus dibentuk; hasilnya disalin ke peserta beserta level HRIS aslinya,
 * dan seluruh hilir membaca salinan itu.
 *
 * KOREKSI BASI. Koreksi menyimpan level HRIS saat dibuat. Bila level HRIS orang itu sudah berubah
 * sejak itu (mis. HR sudah memperbaikinya), koreksi ditandai PERLU DITINJAU dan TIDAK dipakai —
 * lebih aman jatuh ke level HRIS yang baru daripada menimpanya dengan keputusan lama.
 */
class PaLevelPaResolver
{
    /** @var array<int, string>|null */
    private ?array $memoNamaLevel = null;

    /**
     * Koreksi aktif yang berlaku pada tanggal itu, berkunci "PERUSAHAAN|KODE".
     *
     * @return array<string, object>
     */
    public function koreksiBerlaku(?string $tanggal = null): array
    {
        $tanggal = substr($tanggal ?? now()->format('Y-m-d'), 0, 10);

        return DB::table('N_PA_Karyawan_Level_PA')
            ->where('Flag_Aktif', 'Y')
            ->where('Berlaku_Dari', '<=', $tanggal)
            ->where(fn ($q) => $q->whereNull('Berlaku_Sampai')->orWhere('Berlaku_Sampai', '>=', $tanggal))
            ->get(['Id_PA_Karyawan_Level_PA', 'Kode_Perusahaan', 'Kode_Karyawan', 'ID_Level_PA', 'ID_Level_HRIS_Saat_Dibuat', 'Alasan'])
            ->keyBy(fn ($r) => self::kunci($r->Kode_Perusahaan, $r->Kode_Karyawan))
            ->all();
    }

    /**
     * Level efektif satu karyawan.
     *
     * @param  object  $k  wajib: Kode_Perusahaan, Kode_Karyawan, ID_Level (HRIS, boleh null), nama_level
     * @param  array<string, object>  $koreksi  hasil `koreksiBerlaku()`
     * @return array{id_level:?int, nama_level:?string, id_level_hris:?int, nama_level_hris:?string, koreksi_id:?int, perlu_ditinjau:bool}
     */
    public function resolusi(object $k, array $koreksi): array
    {
        $hris = $k->ID_Level === null ? null : (int) $k->ID_Level;
        $hasil = [
            'id_level' => $hris,
            'nama_level' => $k->nama_level ?? null,
            'id_level_hris' => $hris,
            'nama_level_hris' => $k->nama_level ?? null,
            'koreksi_id' => null,
            'perlu_ditinjau' => false,
        ];

        $o = $koreksi[self::kunci($k->Kode_Perusahaan, $k->Kode_Karyawan)] ?? null;

        if ($o === null) {
            return $hasil;
        }

        if ($this->basi($o, $hris)) {
            $hasil['perlu_ditinjau'] = true;

            return $hasil;
        }

        $hasil['id_level'] = (int) $o->ID_Level_PA;
        $hasil['nama_level'] = $this->namaLevel()[(int) $o->ID_Level_PA] ?? null;
        $hasil['koreksi_id'] = (int) $o->Id_PA_Karyawan_Level_PA;

        return $hasil;
    }

    /** Level HRIS orang itu sudah berubah sejak koreksi dibuat. */
    public function basi(object $koreksi, ?int $levelHrisSekarang): bool
    {
        return $koreksi->ID_Level_HRIS_Saat_Dibuat !== null
            && (int) $koreksi->ID_Level_HRIS_Saat_Dibuat !== (int) ($levelHrisSekarang ?? 0);
    }

    /** @return array<int, string> */
    public function namaLevel(): array
    {
        PaLevelMasterService::sinkronHris();

        return $this->memoNamaLevel ??= DB::table('N_PA_Level')->pluck('Nama_Level', 'ID_Level')
            ->mapWithKeys(fn ($v, $k) => [(int) $k => (string) $v])->all();
    }

    /**
     * Padanan SQL `resolusi()` untuk kueri hitungan: LEFT JOIN koreksi yang aktif, berlaku pada
     * tanggal itu, dan TIDAK basi (level HRIS saat dibuat = level HRIS sekarang). Ekspresi level
     * efektifnya `sqlLevel()`. Dipakai supaya angka di layar penyiapan sama dengan isi peserta.
     *
     * @param  \Illuminate\Database\Query\Builder  $q
     */
    public static function gabung($q, string $k = 'k', string $v = 'v', ?string $tanggal = null)
    {
        $tanggal = substr($tanggal ?? now()->format('Y-m-d'), 0, 10);

        return $q->leftJoin('N_PA_Karyawan_Level_PA as lpa', function ($j) use ($k, $v, $tanggal) {
            $j->on('lpa.Kode_Perusahaan', '=', "{$k}.Kode_Perusahaan")
                ->on('lpa.Kode_Karyawan', '=', "{$k}.Kode_Karyawan")
                ->where('lpa.Flag_Aktif', '=', 'Y')
                ->where('lpa.Berlaku_Dari', '<=', $tanggal)
                ->where(fn ($w) => $w->whereNull('lpa.Berlaku_Sampai')->orWhere('lpa.Berlaku_Sampai', '>=', $tanggal))
                ->where(fn ($w) => $w->whereNull('lpa.ID_Level_HRIS_Saat_Dibuat')->orWhereColumn('lpa.ID_Level_HRIS_Saat_Dibuat', "{$v}.ID_Level"));
        });
    }

    public static function sqlLevel(string $v = 'v'): string
    {
        return "COALESCE(lpa.ID_Level_PA, {$v}.ID_Level)";
    }

    public static function kunci(?string $perusahaan, ?string $kode): string
    {
        return strtoupper(trim((string) $perusahaan)).'|'.strtoupper(trim((string) $kode));
    }
}
