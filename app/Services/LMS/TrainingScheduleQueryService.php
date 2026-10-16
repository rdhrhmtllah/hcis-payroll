<?php

namespace App\Services\LMS;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingScheduleQueryService
{
    public function penjadwalanQuery(string $alias = 'p', bool $activeOnly = true): Builder
    {
        $query = DB::table("N_LMS_Penjadwalan as {$alias}");

        if ($activeOnly) {
            $this->applyActiveFilter($query, $alias);
        }

        return $query;
    }

    public function byKodeKaryawanQuery(string $kodeKaryawan, string $alias = 'p', bool $activeOnly = true): Builder
    {
        $normalizedKodeKaryawan = strtoupper(trim($kodeKaryawan));

        return $this->penjadwalanQuery($alias, $activeOnly)->whereRaw(
            "UPPER(LTRIM(RTRIM({$alias}.Kode_Karyawan))) = ?",
            [$normalizedKodeKaryawan],
        );
    }

    public function byKodeKaryawanWithPaketQuery(
        string $kodeKaryawan,
        string $alias = 'p',
        bool $activeOnly = true,
    ): Builder {
        return $this->byKodeKaryawanQuery($kodeKaryawan, $alias, $activeOnly)->join(
            'N_LMS_Master_Paket as mp',
            "{$alias}.Id_Master_Paket",
            '=',
            'mp.Id_Master_Paket',
        );
    }

    public function byKodeKaryawansQuery(array $kodeKaryawans, string $alias = 'p', bool $activeOnly = true): Builder
    {
        $normalizedCodes = collect($kodeKaryawans)
            ->map(fn ($kode) => strtoupper(trim((string) $kode)))
            ->filter()
            ->unique()
            ->values();

        $query = $this->penjadwalanQuery($alias, $activeOnly);

        if ($normalizedCodes->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw(
            "UPPER(LTRIM(RTRIM({$alias}.Kode_Karyawan))) IN (".
                implode(',', array_fill(0, $normalizedCodes->count(), '?')).
                ')',
            $normalizedCodes->all(),
        );
    }

    public function byKodeKaryawansWithPaketQuery(
        array $kodeKaryawans,
        string $alias = 'p',
        bool $activeOnly = true,
    ): Builder {
        return $this->byKodeKaryawansQuery($kodeKaryawans, $alias, $activeOnly)->join(
            'N_LMS_Master_Paket as mp',
            "{$alias}.Id_Master_Paket",
            '=',
            'mp.Id_Master_Paket',
        );
    }

    public function getSchedulesByKodeKaryawans(array $kodeKaryawans, bool $activeOnly = true): Collection
    {
        $durationByPaket = DB::table('N_LMS_Master_Paket_Detail as mpd')
            ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
            ->where(function ($query) {
                $query->whereNull('m.Flag_Aktif')->orWhere('m.Flag_Aktif', 'Y');
            })
            ->select(
                'mpd.Id_Master_Paket',
                DB::raw('SUM(COALESCE(TRY_CAST(m.Total_Durasi AS FLOAT), 0)) as total_durasi_menit'),
            )
            ->groupBy('mpd.Id_Master_Paket');

        return $this->byKodeKaryawansQuery($kodeKaryawans, 'p', $activeOnly)
            ->leftJoin('N_LMS_Master_Paket as mp', 'p.Id_Master_Paket', '=', 'mp.Id_Master_Paket')
            ->leftJoinSub($durationByPaket, 'durasi_paket', function ($join) {
                $join->on('p.Id_Master_Paket', '=', 'durasi_paket.Id_Master_Paket');
            })
            ->select(
                'p.Kode_Karyawan',
                'p.Id_Penjadwalan',
                'p.Id_Master_Paket',
                'p.Flag_Selesai',
                'p.Flag_Cabut_Akses',
                'p.Flag_Learning_Gain',
                'p.Waktu_Enroll',
                'mp.Nama_Paket',
                DB::raw('COALESCE(durasi_paket.total_durasi_menit, 0) as total_durasi_menit'),
            )
            ->orderByDesc('p.Waktu_Enroll')
            ->get();
    }

    public function getProgressSnapshotsByPenjadwalanIds(array $penjadwalanIds): Collection
    {
        $normalizedIds = collect($penjadwalanIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($normalizedIds->isEmpty()) {
            return collect();
        }

        return DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join(
                'N_LMS_Penjadwalan_Det as pdet',
                'pd.Id_Penjadwalan_Detail',
                '=',
                'pdet.Id_Penjadwalan_Detail',
            )
            ->leftJoin('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->whereIn('pd.Id_Penjadwalan', $normalizedIds->all())
            ->select(
                'pd.Id_Penjadwalan',
                DB::raw('COUNT(*) as total_items'),
                DB::raw("
                    SUM(
                        CASE
                            WHEN pdet.Flag_Selesai = 'Y'
                                OR (
                                    UPPER(LTRIM(RTRIM(ISNULL(akt.Kode_Aktivitas, '')))) = 'ABS'
                                    AND pdet.Flag_Checkin = 'Y'
                                )
                            THEN 1 ELSE 0
                        END
                    ) as completed_items
                "),
            )
            ->groupBy('pd.Id_Penjadwalan')
            ->get()
            ->keyBy('Id_Penjadwalan');
    }

    public function resolvePenjadwalanByMateriAndEmployee(int $materiId, string $kodeKaryawan): ?object
    {
        $baseQuery = $this->byKodeKaryawanQuery($kodeKaryawan, 'p');

        $viaPaket = (clone $baseQuery)
            ->join('N_LMS_Master_Paket_Detail as mpd', 'p.Id_Master_Paket', '=', 'mpd.Id_Master_Paket')
            ->where('mpd.Id_Master_Training', $materiId)
            ->select('p.Id_Penjadwalan', 'p.Id_Master_Rumus')
            ->orderByDesc('p.Id_Penjadwalan')
            ->first();

        if ($viaPaket) {
            return $viaPaket;
        }

        return (clone $baseQuery)
            ->join('N_LMS_Penjadwalan_Detail as pd', 'p.Id_Penjadwalan', '=', 'pd.Id_Penjadwalan')
            ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
            ->where('md.Id_Master_Training', $materiId)
            ->select('p.Id_Penjadwalan', 'p.Id_Master_Rumus')
            ->distinct()
            ->orderByDesc('p.Id_Penjadwalan')
            ->first();
    }

    public function resolvePenjadwalanByIdAndEmployee(int $penjadwalanId, string $kodeKaryawan): ?object
    {
        if ($penjadwalanId <= 0) {
            return null;
        }

        return $this->byKodeKaryawanQuery($kodeKaryawan, 'p')
            ->select('p.Id_Penjadwalan', 'p.Id_Master_Paket', 'p.Id_Master_Rumus')
            ->where('p.Id_Penjadwalan', $penjadwalanId)
            ->first();
    }

    public function penjadwalanContainsMateri(int $penjadwalanId, int $materiId): bool
    {
        if ($penjadwalanId <= 0 || $materiId <= 0) {
            return false;
        }

        $viaPaket = DB::table('N_LMS_Penjadwalan as p')
            ->join('N_LMS_Master_Paket_Detail as mpd', 'p.Id_Master_Paket', '=', 'mpd.Id_Master_Paket')
            ->where('p.Id_Penjadwalan', $penjadwalanId)
            ->where('mpd.Id_Master_Training', $materiId)
            ->exists();

        if ($viaPaket) {
            return true;
        }

        return DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Materi_Detail as md', 'pd.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->where('md.Id_Master_Training', $materiId)
            ->exists();
    }

    public function getFirstActiveMateriInPackageWithMetadata(
        int $idMasterPaket,
        ?string $search = null,
        ?string $kategori = null,
    ): ?object {
        $query = DB::table('N_LMS_Master_Paket_Detail as mpd')
            ->join('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
            ->leftJoin('N_LMS_Training_Kategori as k', 'm.Id_Training_Kategori', '=', 'k.Id_Training_Kategori')
            ->where('mpd.Id_Master_Paket', $idMasterPaket)
            ->where('m.Flag_Aktif', 'Y');

        if (filled($search)) {
            $query->where('m.Judul_Training', 'LIKE', '%'.trim((string) $search).'%');
        }

        if (filled($kategori)) {
            $query->whereRaw("COALESCE(k.Nama_Kategori, 'General') LIKE ?", ['%'.trim((string) $kategori).'%']);
        }

        return $query
            ->select([
                'm.Id_Materi',
                'm.Judul_Training',
                'm.Deskripsi',
                'm.Thumbnail_Training',
                'm.Tanggal',
                'k.Nama_Kategori',
            ])
            ->orderBy('mpd.Urutan_Paket_Detail')
            ->first();
    }

    public function activeKodeKaryawanSubQuery(): Builder
    {
        $query = DB::table('N_LMS_Penjadwalan')->select('Kode_Karyawan');
        $this->applyActiveFilter($query);

        return $query;
    }

    public function applyActiveFilter(Builder $query, string $alias = ''): Builder
    {
        $column = $alias !== '' ? "{$alias}.Flag_Cabut_Akses" : 'Flag_Cabut_Akses';

        return $query->where(function ($q) use ($column) {
            $q->whereNull($column)->orWhereRaw("UPPER(LTRIM(RTRIM(ISNULL({$column}, '')))) != 'Y'");
        });
    }
}
