<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Facades\DB;

/**
 * Unit kerja pemilik dokumen SOP.
 *
 * Sumbernya View_Divisi_Sub_Divisi — view yang SAMA dengan yang sudah dipakai
 * SopMonitoringPeopleService dan SopPenjadwalanTargetService untuk menempelkan
 * divisi/departemen pada karyawan. Memakai sumber lain akan melahirkan dua
 * daftar unit yang tidak pernah persis sama, dan laporan kepemilikan tak akan
 * bisa dicocokkan dengan laporan kepatuhan.
 *
 * Bentuk kepemilikan: Divisi WAJIB, Sub-Divisi OPSIONAL. Sub-divisi kosong
 * berarti dokumen milik seluruh divisi — keadaan yang nyata untuk Kebijakan,
 * yang memang berlaku lintas departemen.
 */
class SopUnitKerja
{
    private const VIEW = 'View_Divisi_Sub_Divisi';

    /**
     * Daftar divisi beserta sub-divisi di bawahnya, untuk dua dropdown bertingkat.
     *
     * @return array<int, array{Id:int, Nama:string, Sub:array<int, array{Id:int, Nama:string}>}>
     */
    public static function opsi(): array
    {
        $rows = DB::table(self::VIEW)
            ->orderBy('nama_divisi')->orderBy('nama_sub_divisi')
            ->get(['ID_Divisi', 'ID_Sub_Divisi', 'nama_divisi', 'nama_sub_divisi']);

        $out = [];
        foreach ($rows as $r) {
            $div = (int) $r->ID_Divisi;
            if (! isset($out[$div])) {
                $out[$div] = ['Id' => $div, 'Nama' => (string) $r->nama_divisi, 'Sub' => []];
            }
            if ($r->ID_Sub_Divisi !== null) {
                $out[$div]['Sub'][] = ['Id' => (int) $r->ID_Sub_Divisi, 'Nama' => (string) $r->nama_sub_divisi];
            }
        }

        return array_values($out);
    }

    /**
     * Daftar Departemen (sub-divisi) untuk satu dropdown tunggal.
     *
     * Inilah bentuk yang dipakai layar sejak 22 Agu 2026. Sebelumnya admin
     * diminta memilih Divisi lebih dulu lalu Sub-Divisi, padahal yang
     * benar-benar dipakai operasional hanya sub-divisi — dan namanya di
     * lapangan adalah "Departemen". Meminta dua pilihan untuk satu keputusan
     * membuat field pertama diisi asal supaya bisa lanjut.
     *
     * @return array<int, array{Id:int, Nama:string, Divisi:?string}>
     */
    public static function opsiDepartemen(): array
    {
        $rows = DB::table(self::VIEW)
            ->whereNotNull('ID_Sub_Divisi')
            ->orderBy('nama_sub_divisi')
            ->get(['ID_Sub_Divisi', 'nama_sub_divisi', 'nama_divisi']);

        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->ID_Sub_Divisi;
            if (isset($out[$id])) {
                /* Departemen yang bernaung di lebih dari satu divisi tidak
                   menyebut divisinya sama sekali. Menyebut salah satunya berarti
                   memilih diam-diam; per 22 Agu 2026 ada dua kasus begini. */
                $out[$id]['Divisi'] = null;

                continue;
            }
            $out[$id] = ['Id' => $id, 'Nama' => (string) $r->nama_sub_divisi, 'Divisi' => $r->nama_divisi];
        }

        return array_values($out);
    }

    /**
     * Divisi induk sebuah Departemen, HANYA bila induknya tunggal.
     *
     * Mengembalikan null saat departemen itu bernaung di lebih dari satu divisi.
     * Menebak salah satunya akan menulis fakta yang tak pernah dinyatakan siapa
     * pun ke dalam kolom yang kemudian dibaca laporan sebagai kebenaran.
     */
    public static function divisiTunggal(int $subDivisiId): ?int
    {
        $ids = DB::table(self::VIEW)
            ->where('ID_Sub_Divisi', $subDivisiId)
            ->distinct()->pluck('ID_Divisi')->filter()->all();

        return count($ids) === 1 ? (int) reset($ids) : null;
    }

    public static function subDivisiAda(int $subDivisiId): bool
    {
        return DB::table(self::VIEW)->where('ID_Sub_Divisi', $subDivisiId)->exists();
    }

    public static function divisiAda(int $divisiId): bool
    {
        return DB::table(self::VIEW)->where('ID_Divisi', $divisiId)->exists();
    }

    /** Apakah sub-divisi itu benar-benar berada di bawah divisi tersebut? */
    public static function pasanganAda(int $divisiId, int $subDivisiId): bool
    {
        return DB::table(self::VIEW)
            ->where('ID_Divisi', $divisiId)
            ->where('ID_Sub_Divisi', $subDivisiId)
            ->exists();
    }

    /**
     * Label siap tampil, DEPARTEMEN lebih dulu.
     *
     * Urutannya dibalik pada 22 Agu 2026. Sebelumnya helper ini mengembalikan
     * null begitu divisinya kosong — asumsi yang benar selama divisi wajib, dan
     * salah sejak Departemen berdiri sendiri: dokumen yang pemiliknya terisi
     * akan tampil "tanpa pemilik" hanya karena divisinya bercabang.
     *
     * Null hanya bila memang tak ada satu pun yang terisi; pemanggil yang
     * memutuskan kalimat penggantinya, bukan helper ini.
     */
    public static function label(?int $divisiId, ?int $subDivisiId = null): ?string
    {
        if ($subDivisiId) {
            $row = DB::table(self::VIEW)
                ->where('ID_Sub_Divisi', $subDivisiId)
                ->when($divisiId, fn ($q) => $q->where('ID_Divisi', $divisiId))
                ->first(['nama_divisi', 'nama_sub_divisi']);

            if ($row) {
                return $divisiId && $row->nama_divisi
                    ? $row->nama_sub_divisi.' · '.$row->nama_divisi
                    : (string) $row->nama_sub_divisi;
            }
        }

        if (! $divisiId) {
            return null;
        }

        return DB::table(self::VIEW)->where('ID_Divisi', $divisiId)->value('nama_divisi');
    }

    /**
     * Nama divisi dan sub-divisi untuk sekumpulan dokumen sekaligus.
     *
     * Dipakai daftar Master Dokumen supaya 41 baris tidak melahirkan 41 query.
     *
     * @return array{divisi: array<int,string>, sub: array<int,string>}
     */
    public static function nama(array $divisiIds, array $subDivisiIds): array
    {
        $divisiIds = array_values(array_unique(array_filter($divisiIds)));
        $subDivisiIds = array_values(array_unique(array_filter($subDivisiIds)));

        $divisi = $divisiIds
            ? DB::table(self::VIEW)->whereIn('ID_Divisi', $divisiIds)
                ->pluck('nama_divisi', 'ID_Divisi')->all()
            : [];

        $sub = $subDivisiIds
            ? DB::table(self::VIEW)->whereIn('ID_Sub_Divisi', $subDivisiIds)
                ->pluck('nama_sub_divisi', 'ID_Sub_Divisi')->all()
            : [];

        return ['divisi' => $divisi, 'sub' => $sub];
    }
}
