<?php

namespace App\Services\VISITOR\Guest\Visit;

use Illuminate\Support\Facades\DB;

/**
 * Matriks departemen × jenis tamu (DDL-2026-122).
 *
 * Satu-satunya pembaca N_VISITOR_Matriks_Tamu. Menjawab dua pertanyaan untuk
 * seorang pengguna, lewat role departemen yang dipegangnya:
 *
 *  - jenis tamu apa yang boleh ia LIHAT di Jadwal Kedatangan Tamu;
 *  - unit B2B apa yang boleh ia AJUKAN.
 *
 * Matriks adalah data, bukan kode: menambah departemen atau mengubah centang
 * cukup lewat baris tabel, tanpa deploy. Kedua flag keanggotaan (baris
 * N_HRIS_Role_User dan role-nya) wajib aktif — N_HRIS_Role_User bisa berbaris
 * kembar dengan Flag_Aktif berbeda.
 */
class MatriksTamuService
{
    public const REGULAR = 'REGULAR';

    public const B2B_PROCUREMENT = 'B2B_PROCUREMENT';

    public const B2B_LOGISTIC = 'B2B_LOGISTIC';

    /** Urutan tampil tab. */
    public const KATEGORI = [self::REGULAR, self::B2B_PROCUREMENT, self::B2B_LOGISTIC];

    public const UNIT_PROCUREMENT = 'PROCUREMENT';

    public const UNIT_LOGISTIC = 'LOGISTIC';

    public const UNIT = [self::UNIT_PROCUREMENT, self::UNIT_LOGISTIC];

    public const LABEL_KATEGORI = [
        self::REGULAR => 'Tamu Regular',
        self::B2B_PROCUREMENT => 'B2B Procurement',
        self::B2B_LOGISTIC => 'B2B Logistic',
    ];

    /** @var array<int, array{lihat: list<string>, ajukan: list<string>}> */
    private array $cache = [];

    /** @return list<string> kategori yang boleh dilihat, urut KATEGORI */
    public function kategoriBolehLihat(?int $userIdWeb): array
    {
        return $this->untuk($userIdWeb)['lihat'];
    }

    /** @return list<string> unit B2B (PROCUREMENT/LOGISTIC) yang boleh diajukan */
    public function unitBolehAjukan(?int $userIdWeb): array
    {
        return array_values(array_map(
            fn (string $kategori) => self::unitDariKategori($kategori),
            array_filter(
                $this->untuk($userIdWeb)['ajukan'],
                fn (string $kategori) => $kategori !== self::REGULAR,
            ),
        ));
    }

    /**
     * Role departemen yang dipegang pengguna beserta hak masing-masing —
     * untuk ditunjukkan di layar ("Anda melihat sebagai QC Incoming").
     *
     * @return list<array{kode: string, nama: string, lihat: list<string>, ajukan: list<string>}>
     */
    public function departemen(?int $userIdWeb): array
    {
        if (! $userIdWeb) {
            return [];
        }

        return DB::table('N_VISITOR_Matriks_Tamu as m')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'm.Role_Id')
            ->join('N_HRIS_Role_User as ru', 'ru.Id_Role', '=', 'rf.Id_Role')
            ->where('ru.UserId_Web', $userIdWeb)
            ->where('ru.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->select('rf.Role_Code', 'rf.Role_Name', 'm.Kategori_Tamu', 'm.Flag_Lihat', 'm.Flag_Ajukan')
            ->get()
            ->groupBy('Role_Code')
            ->map(fn ($baris, $kode) => [
                'kode' => (string) $kode,
                // "Departemen QC Incoming (Tamu)" -> "QC Incoming"
                'nama' => trim(preg_replace('/^Departemen\s+|\s*\(Tamu\)$/', '', (string) $baris->first()->Role_Name)),
                'lihat' => $baris->where('Flag_Lihat', 'Y')->pluck('Kategori_Tamu')->unique()->values()->all(),
                'ajukan' => $baris->where('Flag_Ajukan', 'Y')->pluck('Kategori_Tamu')->unique()->values()->all(),
            ])
            ->sortBy('nama')
            ->values()
            ->all();
    }

    public static function kategori(?string $tipeTamu, ?string $unit): string
    {
        if (strtoupper((string) $tipeTamu) !== TipeTamu::B2B) {
            return self::REGULAR;
        }

        return strtoupper((string) $unit) === self::UNIT_PROCUREMENT ? self::B2B_PROCUREMENT : self::B2B_LOGISTIC;
    }

    public static function unitDariKategori(string $kategori): string
    {
        return $kategori === self::B2B_PROCUREMENT ? self::UNIT_PROCUREMENT : self::UNIT_LOGISTIC;
    }

    public static function unitSah(string $unit): string
    {
        $unit = strtoupper(trim($unit));

        if (! in_array($unit, self::UNIT, true)) {
            throw new \InvalidArgumentException("Unit B2B tidak dikenal: {$unit}");
        }

        return $unit;
    }

    /**
     * Penyaring kategori untuk kueri ber-alias detail `d`.
     *
     * @param  list<string>  $kategori
     */
    public static function saring($query, array $kategori, string $detailAlias = 'd')
    {
        if ($kategori === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($q) use ($kategori, $detailAlias) {
            foreach ($kategori as $k) {
                if ($k === self::REGULAR) {
                    $q->orWhere(fn ($s) => $s->whereNull($detailAlias.'.Tipe_Tamu')->orWhere($detailAlias.'.Tipe_Tamu', TipeTamu::REGULAR));
                } else {
                    $q->orWhere(fn ($s) => $s->where($detailAlias.'.Tipe_Tamu', TipeTamu::B2B)
                        ->where($detailAlias.'.B2B_Unit', self::unitDariKategori($k)));
                }
            }
        });
    }

    /** @return array{lihat: list<string>, ajukan: list<string>} */
    private function untuk(?int $userIdWeb): array
    {
        if (! $userIdWeb) {
            return ['lihat' => [], 'ajukan' => []];
        }

        if (isset($this->cache[$userIdWeb])) {
            return $this->cache[$userIdWeb];
        }

        $baris = DB::table('N_VISITOR_Matriks_Tamu as m')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'm.Role_Id')
            ->join('N_HRIS_Role_User as ru', 'ru.Id_Role', '=', 'rf.Id_Role')
            ->where('ru.UserId_Web', $userIdWeb)
            ->where('ru.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->select('m.Kategori_Tamu', 'm.Flag_Lihat', 'm.Flag_Ajukan')
            ->get();

        $urut = fn (array $daftar) => array_values(array_filter(self::KATEGORI, fn ($k) => in_array($k, $daftar, true)));

        return $this->cache[$userIdWeb] = [
            'lihat' => $urut($baris->where('Flag_Lihat', 'Y')->pluck('Kategori_Tamu')->unique()->all()),
            'ajukan' => $urut($baris->where('Flag_Ajukan', 'Y')->pluck('Kategori_Tamu')->unique()->all()),
        ];
    }
}
