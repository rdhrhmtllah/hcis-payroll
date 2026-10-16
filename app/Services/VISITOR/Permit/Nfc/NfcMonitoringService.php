<?php

namespace App\Services\VISITOR\Permit\Nfc;

use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Layanan pemantauan & manajemen operasional ID Card (NFC) dan pairing-nya:
 * - Query agregasi monitoring & filter
 * - Penghitungan statistik kartu, pemegang, dan anomali
 * - Pengubahan status aktif & validasi sistem
 *
 * Angka kartu statistik dan isi tab dihitung dari SATU penyaring (terapkanTab):
 * kalau dihitung terpisah, chip "Anomali (5)" bisa membuka daftar berisi 7 baris.
 */
class NfcMonitoringService
{
    public const T_MASTER = 'N_EMI_Master_NFC';

    public const T_BINDING = 'N_EMI_Binding_NFC';

    private const PEMEGANG_LAIN_SQL = "(SELECT COUNT(*) FROM N_EMI_Binding_NFC b2 WHERE b2.id_nfc = b.id_nfc AND b2.flag_aktif = 'Y' AND b2.id <> b.id)";

    /**
     * Mengambil daftar baris monitoring NFC dengan filter dan paginasi.
     *
     * @param  array<string, mixed>  $filters
     * @return array{items: array, total: int, page: int, limit: int}
     */
    public function list(array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = max(5, min(100, (int) ($filters['limit'] ?? 15)));
        $tab = strtolower(trim((string) ($filters['tab'] ?? 'all')));
        $search = trim((string) ($filters['search'] ?? ''));

        $query = $this->basis();
        $this->terapkanTab($query, $tab);

        if ($search !== '') {
            $pola = '%'.self::escapeLike($search).'%';
            $query->where(function ($q) use ($pola) {
                $q->where('b.nama', 'LIKE', $pola)
                    ->orWhere('b.kode_karyawan', 'LIKE', $pola)
                    ->orWhere('b.nik', 'LIKE', $pola)
                    ->orWhere('b.no_mitra', 'LIKE', $pola)
                    ->orWhere('m.nfc_hex', 'LIKE', $pola)
                    ->orWhereRaw('CAST(m.nfc_inbio AS varchar(20)) LIKE ?', [$pola]);
            });
        }

        $total = (clone $query)->count();
        $items = $query
            ->select(
                'b.id as id_binding',
                'b.id_nfc',
                'm.nfc_hex',
                'm.nfc_inbio',
                'm.flag_validasi_sistem',
                'm.flag_aktif as master_aktif',
                'b.nik',
                'b.kode_karyawan',
                'b.nama',
                'b.no_mitra',
                'b.flag_aktif as binding_aktif',
                'b.created_at',
                DB::raw(self::PEMEGANG_LAIN_SQL.' as pemegang_lain'),
            )
            ->orderByDesc('b.id')
            ->skip(($page - 1) * $limit)
            ->take($limit)
            ->get()
            ->map(function ($row) {
                $row->status = $this->klasifikasiStatus($row);
                $row->kategori = $this->kategoriPemegang($row);

                return (array) $row;
            })
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Statistik ringkasan. Kartu "Perlu Perhatian" = total_anomali + total_nonaktif
     * = jumlah baris tab Anomali, persis.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $hitung = function (string $tab, ?callable $tambahan = null): int {
            $q = $this->basis();
            $this->terapkanTab($q, $tab);
            if ($tambahan) {
                $tambahan($q);
            }

            return $q->count();
        };

        return [
            'total_master' => DB::table(self::T_MASTER)->count(),
            'total_karyawan' => $hitung('karyawan', fn (Builder $q) => $q->where('b.flag_aktif', 'Y')),
            'total_phl' => $hitung('phl', fn (Builder $q) => $q->where('b.flag_aktif', 'Y')),
            'total_anomali' => $hitung('anomali', fn (Builder $q) => $q->where('b.flag_aktif', 'Y')),
            'total_nonaktif' => DB::table(self::T_BINDING)->where('flag_aktif', 'T')->count(),
        ];
    }

    /**
     * Menentukan klasifikasi status kartu berdasarkan kondisi data.
     *
     * Pairing nonaktif dinilai lebih dulu: kartu yang sudah dipindah ke pemegang
     * baru meninggalkan baris lama yang nonaktif, dan itu bukan kartu ganda.
     */
    public function klasifikasiStatus(object $row): string
    {
        if (($row->binding_aktif ?? 'Y') !== 'Y') {
            return 'PAIRING_NONAKTIF';
        }
        if (($row->master_aktif ?? 'Y') !== 'Y') {
            return 'KARTU_DIBLOKIR';
        }
        if (($row->flag_validasi_sistem ?? 'Y') !== 'Y') {
            return 'MENUNGGU_VALIDASI';
        }
        if ((int) ($row->pemegang_lain ?? 0) > 0) {
            return 'KARTU_GANDA';
        }

        return 'TERDAFTAR';
    }

    /**
     * Menentukan kategori pemegang (KARYAWAN atau PHL).
     */
    public function kategoriPemegang(object $row): string
    {
        return ! empty($row->no_mitra) ? 'PHL' : 'KARYAWAN';
    }

    /**
     * Mengubah status aktif binding.
     *
     * Mengaktifkan kembali tidak boleh melahirkan kartu ganda: bila kartunya kini
     * aktif pada orang lain, pairing itu harus dinonaktifkan dulu (aturan yang sama
     * dengan form manual dan impor). Pairing aktif lain milik orang yang sama
     * dinonaktifkan, karena satu orang hanya memegang satu kartu aktif.
     */
    public function toggleBindingStatus(int $idBinding): array
    {
        return DB::transaction(function () use ($idBinding) {
            $binding = DB::table(self::T_BINDING)->where('id', $idBinding)->lockForUpdate()->first();
            if (! $binding) {
                throw new DomainException('Data pairing NFC tidak ditemukan.');
            }

            if ($binding->flag_aktif === 'Y') {
                DB::table(self::T_BINDING)->where('id', $idBinding)->update(['flag_aktif' => 'T']);

                return ['id' => $idBinding, 'flag_aktif' => 'T'];
            }

            $pemegangLain = DB::table(self::T_BINDING)
                ->where('id_nfc', $binding->id_nfc)
                ->where('flag_aktif', 'Y')
                ->where('id', '<>', $idBinding)
                ->first();
            if ($pemegangLain) {
                $nama = $pemegangLain->nama ?: $pemegangLain->kode_karyawan ?: $pemegangLain->no_mitra ?: 'pemegang lain';
                throw new DomainException(
                    "Kartu ini sedang aktif pada '{$nama}'. Nonaktifkan pairing tersebut terlebih dahulu."
                );
            }

            $milikOrangSama = DB::table(self::T_BINDING)
                ->where('flag_aktif', 'Y')
                ->where('id', '<>', $idBinding);
            $kode = trim((string) ($binding->kode_karyawan ?? ''));
            $mitra = trim((string) ($binding->no_mitra ?? ''));
            if ($kode !== '') {
                // Kode Karyawan, bukan No Mitra: satu No Mitra bisa dipakai dua PHL.
                $milikOrangSama->where('kode_karyawan', $kode)->update(['flag_aktif' => 'T']);
            } elseif ($mitra !== '') {
                $milikOrangSama->whereNull('kode_karyawan')->where('no_mitra', $mitra)->update(['flag_aktif' => 'T']);
            }

            DB::table(self::T_BINDING)->where('id', $idBinding)->update(['flag_aktif' => 'Y']);

            return ['id' => $idBinding, 'flag_aktif' => 'Y'];
        });
    }

    /**
     * Mengubah validasi sistem master kartu.
     */
    public function toggleMasterValidasi(int $idMaster): array
    {
        $master = DB::table(self::T_MASTER)->where('id', $idMaster)->first();
        if (! $master) {
            throw new DomainException('Master NFC tidak ditemukan.');
        }

        $statusBaru = $master->flag_validasi_sistem === 'Y' ? 'T' : 'Y';
        DB::table(self::T_MASTER)->where('id', $idMaster)->update(['flag_validasi_sistem' => $statusBaru]);

        return ['id' => $idMaster, 'flag_validasi_sistem' => $statusBaru];
    }

    /**
     * Pencarian saran data karyawan untuk autofill form manual.
     *
     * Karyawan yang hari kerja terakhirnya sudah lewat tidak ditawarkan: kartunya
     * tidak lagi dibaca untuk permit (VisitorPermitQueryService::pemohonResignSql).
     *
     * @return array<int, array{kode_karyawan: string, nama: string, nik: string}>
     */
    public function suggestKaryawan(string $keyword): array
    {
        $q = trim($keyword);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $pola = '%'.self::escapeLike($q).'%';

        return DB::table('Karyawan')
            ->select('Kode_Karyawan', 'Nama', 'NIK', 'No_Induk_Karyawan')
            ->where(function ($query) use ($pola) {
                $query->where('Nama', 'LIKE', $pola)
                    ->orWhere('Kode_Karyawan', 'LIKE', $pola)
                    ->orWhere('NIK', 'LIKE', $pola)
                    ->orWhere('No_Induk_Karyawan', 'LIKE', $pola);
            })
            ->where(function ($query) {
                $query->whereNull('Tanggal_Resign')
                    ->orWhereRaw("CAST(Tanggal_Resign AS date) >= CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'SE Asia Standard Time' AS date)");
            })
            ->orderBy('Nama')
            ->limit(15)
            ->get()
            ->map(fn ($r) => [
                'kode_karyawan' => trim((string) $r->Kode_Karyawan),
                'nama' => trim((string) $r->Nama),
                'nik' => trim((string) ($r->No_Induk_Karyawan ?: $r->NIK ?: '')),
            ])
            ->all();
    }

    /** Karakter khusus LIKE SQL Server (% _ [) diperlakukan sebagai huruf biasa. */
    public static function escapeLike(string $nilai): string
    {
        return strtr($nilai, ['[' => '[[]', '%' => '[%]', '_' => '[_]']);
    }

    private function basis(): Builder
    {
        return DB::table(self::T_BINDING.' as b')->join(self::T_MASTER.' as m', 'm.id', '=', 'b.id_nfc');
    }

    private function terapkanTab(Builder $query, string $tab): void
    {
        if ($tab === 'karyawan') {
            $query->whereNull('b.no_mitra');
        } elseif ($tab === 'phl') {
            $query->whereNotNull('b.no_mitra');
        } elseif ($tab === 'anomali') {
            $query->where(function ($q) {
                $q->where('b.flag_aktif', 'T')
                    ->orWhere('m.flag_aktif', 'T')
                    ->orWhere('m.flag_validasi_sistem', 'T')
                    ->orWhereRaw(self::PEMEGANG_LAIN_SQL.' > 0');
            });
        }
    }
}
