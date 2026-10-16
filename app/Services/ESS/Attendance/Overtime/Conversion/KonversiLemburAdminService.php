<?php

namespace App\Services\ESS\Attendance\Overtime\Conversion;

use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Pantauan & pembatalan pengajuan Konversi Lembur oleh admin HC.
 *
 * Satu pengajuan = satu baris Detail + satu baris Det (satu tanggal lembur);
 * terverifikasi di dev 13 Okt 2026 (40 transaksi = 40 Det).
 *
 * Status diturunkan dari kolom yang ditulis alur yang ada, tanpa kolom baru:
 *  - header.Status 'Y'/'T'  -> DIBATALKAN (sama dengan applyNotCancelledFilter)
 *  - Flag_Approval 'T'      -> DITOLAK
 *  - det.Flag_Applied 'Y'   -> DITERAPKAN
 *  - detail.Flag_Approval Y -> DISETUJUI
 *  - selain itu             -> MENUNGGU
 */
class KonversiLemburAdminService
{
    public const STATUS = ['MENUNGGU', 'DISETUJUI', 'DITERAPKAN', 'DITOLAK', 'DIBATALKAN'];

    public const JENIS = ['DAY_OFF', 'MATERIAL', 'TO_CUTI'];

    /** Penanda di Det.Note_Tolak: pembeda batal-admin dari tolak & batal-karyawan. */
    public const PENANDA_BATAL_ADMIN = 'Dibatalkan admin: ';

    private const PANJANG_NOTE_TOLAK = 200;

    private const MIN_ALASAN = 5;

    /** Saringan organisasi (multi-pilih) => kolom yang dicocokkan persis. */
    public const SARINGAN_ORGANISASI = [
        'divisi' => 'vd.nama_divisi',
        'departemen' => 'vd.nama_sub_divisi',
        'level' => 'lvl.nama_level',
        'jabatan' => 'lvl.nama_jabatan',
        'lokasi' => 'k.Cabang_Untuk_Gaji',
    ];

    /** 5 saringan x 200 nilai tetap jauh di bawah batas 2.100 parameter SQL Server. */
    private const MAKS_NILAI_SARINGAN = 200;

    private const URUT = [
        'dibuat' => 'd.Created_At',
        'tanggal_lembur' => 'det.Tanggal_Lembur',
        'durasi' => 'det.Durasi_Jam',
    ];

    public static function statusSql(): string
    {
        return "CASE WHEN h.Status IN ('Y','T') THEN 'DIBATALKAN'"
            ." WHEN d.Flag_Approval = 'T' OR det.Flag_Approval = 'T' THEN 'DITOLAK'"
            ." WHEN det.Flag_Applied = 'Y' THEN 'DITERAPKAN'"
            ." WHEN d.Flag_Approval = 'Y' THEN 'DISETUJUI'"
            ." ELSE 'MENUNGGU' END";
    }

    /** Sama dengan KonversiLemburService::normalizeConversionType(), dalam SQL. */
    public static function jenisSql(): string
    {
        return "CASE WHEN UPPER(LTRIM(RTRIM(det.Jenis_Konversi))) IN ('TO_CUTI','KONVERSI_TO_CUTI') THEN 'TO_CUTI'"
            ." WHEN UPPER(LTRIM(RTRIM(det.Jenis_Konversi))) IN ('MATERIAL','MATERIAL_CONVERSION') THEN 'MATERIAL'"
            ." ELSE 'DAY_OFF' END";
    }

    public static function bolehDibatalkan(string $status): bool
    {
        return in_array($status, ['MENUNGGU', 'DISETUJUI', 'DITERAPKAN'], true);
    }

    /**
     * Catatan pembatalan untuk Det.Note_Tolak (varchar 200). Alasan wajib.
     */
    public static function catatanBatal(string $alasan): string
    {
        $alasan = trim(preg_replace('/\s+/', ' ', $alasan));
        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi (minimal '.self::MIN_ALASAN.' karakter).');
        }

        return mb_substr(self::PENANDA_BATAL_ADMIN.$alasan, 0, self::PANJANG_NOTE_TOLAK);
    }

    public static function apakahBatalAdmin(?string $catatan): bool
    {
        return $catatan !== null && str_starts_with($catatan, self::PENANDA_BATAL_ADMIN);
    }

    /** @return array<string, mixed> */
    public function normalisasiFilter(array $f): array
    {
        $tanggal = function ($v): ?string {
            $v = trim((string) $v);
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                return null;
            }
            try {
                return Carbon::createFromFormat('Y-m-d', $v)->toDateString() === $v ? $v : null;
            } catch (\Throwable) {
                return null;
            }
        };

        $status = strtoupper(trim((string) ($f['status'] ?? '')));
        $jenis = strtoupper(trim((string) ($f['jenis'] ?? '')));
        $urut = (string) ($f['urut'] ?? 'dibuat');

        $daftar = function ($v): array {
            $hasil = [];
            foreach ((array) $v as $x) {
                if (is_scalar($x) && ($x = trim((string) $x)) !== '') {
                    $hasil[$x] = true;
                }
            }

            return array_slice(array_keys($hasil), 0, self::MAKS_NILAI_SARINGAN);
        };

        $organisasi = [];
        foreach (array_keys(self::SARINGAN_ORGANISASI) as $kunci) {
            $organisasi[$kunci] = $daftar($f[$kunci] ?? []);
        }

        return $organisasi + [
            'status' => in_array($status, self::STATUS, true) ? $status : null,
            'jenis' => in_array($jenis, self::JENIS, true) ? $jenis : null,
            'tanggal_dari' => $tanggal($f['tanggal_dari'] ?? null),
            'tanggal_sampai' => $tanggal($f['tanggal_sampai'] ?? null),
            'search' => trim((string) ($f['search'] ?? '')),
            'page' => max(1, (int) ($f['page'] ?? 1)),
            'limit' => max(5, min(100, (int) ($f['limit'] ?? 15))),
            'urut' => array_key_exists($urut, self::URUT) ? $urut : 'dibuat',
            'arah' => strtolower((string) ($f['arah'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }

    /** @return array{items: array, total: int, page: int, limit: int} */
    public function list(array $filter): array
    {
        $f = $this->normalisasiFilter($filter);
        $q = $this->basis();
        $this->terapkanFilter($q, $f, true);

        $total = (clone $q)->count();
        $rows = $q->select($this->kolomBaris())
            ->orderBy(self::URUT[$f['urut']], $f['arah'])
            ->orderByDesc('d.Id')
            ->forPage($f['page'], $f['limit'])
            ->get();

        return [
            'items' => $rows->map(fn ($r) => $this->bentukBaris($r))->values()->all(),
            'total' => $total,
            'page' => $f['page'],
            'limit' => $f['limit'],
        ];
    }

    /**
     * Ringkasan per status untuk filter yang sama KECUALI status, supaya angka
     * di chip menjawab "berapa yang akan muncul bila chip ini dipilih".
     *
     * @return array<string, int|float>
     */
    public function stats(array $filter): array
    {
        $f = $this->normalisasiFilter($filter);
        $q = $this->basis();
        $this->terapkanFilter($q, $f, false);

        $status = self::statusSql();
        $parts = ['COUNT(*) AS total'];
        foreach (self::STATUS as $s) {
            $parts[] = "SUM(CASE WHEN ({$status}) = '{$s}' THEN 1 ELSE 0 END) AS ".strtolower($s);
        }
        $parts[] = "SUM(CASE WHEN ({$status}) = 'DITERAPKAN' THEN ISNULL(det.Durasi_Jam, 0) ELSE 0 END) AS jam_diterapkan";

        $r = $q->selectRaw(implode(', ', $parts))->first();

        $hasil = ['total' => (int) ($r->total ?? 0)];
        foreach (self::STATUS as $s) {
            $hasil[strtolower($s)] = (int) ($r->{strtolower($s)} ?? 0);
        }
        $hasil['jam_diterapkan'] = round((float) ($r->jam_diterapkan ?? 0), 2);

        return $hasil;
    }

    /**
     * Detail satu pengajuan: data, jejak approval, lampiran, hasil konversi, dan
     * (bila Jadi Cuti) pratinjau dampak pembatalan ke saldo cuti.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $noTransaksi, KonversiLemburService $konversi, TransactionService $transaksi): ?array
    {
        $row = $this->basis()->where('d.No_Transaksi', $noTransaksi)->select($this->kolomBaris())->first();
        if (! $row) {
            return null;
        }

        $baris = $this->bentukBaris($row);
        $langkah = $konversi->getApprovalStepsMapForTransactions([$noTransaksi => []]);

        $baris['approval_steps'] = $langkah[$noTransaksi] ?? [];
        $baris['lampiran_url'] = $this->urlLampiran((string) ($row->File_Path ?? ''));
        $baris['hasil_konversi'] = DB::table('N_HRIS_Konversi_Lembur_Efektif')
            ->where('No_Transaksi_Reference', $noTransaksi)
            ->orderBy('Id')
            ->get(['Tanggal_Off', 'Tanggal_Lembur_Asal', 'Jenis_Konversi', 'Flag_Aktif'])
            ->map(fn ($e) => [
                'tanggal_off' => $e->Tanggal_Off ? Carbon::parse($e->Tanggal_Off)->toDateString() : null,
                'tanggal_lembur_asal' => $e->Tanggal_Lembur_Asal ? Carbon::parse($e->Tanggal_Lembur_Asal)->toDateString() : null,
                'jenis' => (string) $e->Jenis_Konversi,
                'aktif' => $e->Flag_Aktif === 'Y',
            ])->all();
        $baris['dampak_saldo_cuti'] = $baris['jenis_konversi'] === 'TO_CUTI' && $baris['boleh_batal']
            ? $transaksi->pratinjauBalikKreditKonversi($noTransaksi)
            : null;

        return $baris;
    }

    /**
     * Pilihan saringan organisasi, hanya dari karyawan yang PERNAH mengajukan konversi
     * lembur: daftar master penuh memuat ratusan nilai yang hasilnya selalu kosong.
     * Departemen membawa divisinya agar layar bisa menyempitkannya.
     *
     * @return array{divisi: array, departemen: array, level: array, jabatan: array, lokasi: array}
     */
    public function opsiSaringan(): array
    {
        $nilai = fn (string $kolom) => $this->basis()
            ->whereNotNull($kolom)->where($kolom, '<>', '')
            ->distinct()->orderBy($kolom)->pluck($kolom)
            ->map(fn ($v) => (string) $v)->values()->all();

        $departemen = $this->basis()
            ->whereNotNull('vd.nama_sub_divisi')->where('vd.nama_sub_divisi', '<>', '')
            ->distinct()->orderBy('vd.nama_sub_divisi')->orderBy('vd.nama_divisi')
            ->get(['vd.nama_sub_divisi', 'vd.nama_divisi'])
            ->map(fn ($r) => ['nama' => (string) $r->nama_sub_divisi, 'divisi' => (string) ($r->nama_divisi ?? '')])
            ->values()->all();

        return [
            'divisi' => $nilai('vd.nama_divisi'),
            'departemen' => $departemen,
            'level' => $nilai('lvl.nama_level'),
            'jabatan' => $nilai('lvl.nama_jabatan'),
            'lokasi' => $nilai('k.Cabang_Untuk_Gaji'),
        ];
    }

    private function basis(): Builder
    {
        return DB::table('N_HRIS_Transaksi_Konversi_Lembur_Detail as d')
            ->join('N_HRIS_Transaksi_Konversi_Lembur as h', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('N_HRIS_Transaksi_Konversi_Lembur_Det as det', 'det.Id_Detail', '=', 'd.Id')
            ->leftJoin('Karyawan as k', function ($j) {
                // Kode_Karyawan unik hanya per perusahaan (PK komposit).
                $j->on('k.Kode_Karyawan', '=', 'd.Kode_Karyawan')->on('k.Kode_Perusahaan', '=', 'd.Kode_Perusahaan');
            })
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_Divisi_Sub_Divisi', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan');
    }

    private function terapkanFilter(Builder $q, array $f, bool $denganStatus): void
    {
        if ($denganStatus && $f['status']) {
            $q->whereRaw(self::statusSql().' = ?', [$f['status']]);
        }
        foreach (self::SARINGAN_ORGANISASI as $kunci => $kolom) {
            if ($f[$kunci] !== []) {
                $q->whereIn($kolom, $f[$kunci]);
            }
        }
        if ($f['jenis']) {
            $q->whereRaw(self::jenisSql().' = ?', [$f['jenis']]);
        }
        if ($f['tanggal_dari']) {
            $q->where('det.Tanggal_Lembur', '>=', $f['tanggal_dari']);
        }
        if ($f['tanggal_sampai']) {
            $q->where('det.Tanggal_Lembur', '<=', $f['tanggal_sampai']);
        }
        if ($f['search'] !== '') {
            $pola = '%'.strtr($f['search'], ['[' => '[[]', '%' => '[%]', '_' => '[_]']).'%';
            $q->where(function ($w) use ($pola) {
                $w->where('k.Nama', 'LIKE', $pola)
                    ->orWhere('d.Kode_Karyawan', 'LIKE', $pola)
                    ->orWhere('d.No_Transaksi', 'LIKE', $pola);
            });
        }
    }

    /** @return array<int, mixed> */
    private function kolomBaris(): array
    {
        return [
            'd.No_Transaksi',
            'd.Kode_Karyawan',
            'k.Nama',
            'vd.nama_divisi as Divisi',
            'vd.nama_sub_divisi as Departemen',
            'lvl.nama_level as Level',
            'lvl.nama_jabatan as Jabatan',
            'k.Cabang_Untuk_Gaji as Lokasi',
            'det.Tanggal_Lembur',
            'det.CheckIn_Pilih',
            'det.CheckOut_Pilih',
            'det.Durasi_Jam',
            'det.Hari_Dikonversi',
            'det.Target_Off',
            'det.Note_Tolak',
            'd.Note',
            'd.File_Path',
            'h.Note as Header_Note',
            'd.Created_At',
            'h.Updated_At',
            'h.Updated_By',
            DB::raw(self::statusSql().' as Status'),
            DB::raw(self::jenisSql().' as Jenis'),
            DB::raw('(SELECT TOP 1 e.Tanggal_Off FROM N_HRIS_Konversi_Lembur_Efektif e WHERE e.No_Transaksi_Reference = d.No_Transaksi ORDER BY e.Id DESC) as Tanggal_Off_Efektif'),
        ];
    }

    /** @return array<string, mixed> */
    private function bentukBaris(object $r): array
    {
        $tgl = fn ($v) => $v ? Carbon::parse($v)->toDateString() : null;
        $jam = fn ($v) => $v ? Carbon::parse($v)->format('H:i') : null;
        $status = (string) $r->Status;
        $catatanTolak = $r->Note_Tolak !== null ? (string) $r->Note_Tolak : null;

        return [
            'no_transaksi' => (string) $r->No_Transaksi,
            'kode_karyawan' => (string) $r->Kode_Karyawan,
            'nama' => (string) ($r->Nama ?? $r->Kode_Karyawan),
            'divisi' => $r->Divisi !== null ? (string) $r->Divisi : null,
            'departemen' => $r->Departemen !== null ? (string) $r->Departemen : null,
            'level' => $r->Level !== null ? (string) $r->Level : null,
            'jabatan' => $r->Jabatan !== null ? (string) $r->Jabatan : null,
            'lokasi' => $r->Lokasi !== null ? (string) $r->Lokasi : null,
            'tanggal_lembur' => $tgl($r->Tanggal_Lembur),
            'jam_masuk' => $jam($r->CheckIn_Pilih),
            'jam_keluar' => $jam($r->CheckOut_Pilih),
            'durasi_jam' => round((float) ($r->Durasi_Jam ?? 0), 2),
            'hari_dikonversi' => (int) ($r->Hari_Dikonversi ?? 0),
            'jenis_konversi' => (string) $r->Jenis,
            'tanggal_off' => $tgl($r->Target_Off) ?? $tgl($r->Tanggal_Off_Efektif),
            'status' => $status,
            'catatan_karyawan' => (string) ($r->Note ?: ($r->Header_Note ?? '')),
            'catatan_keputusan' => $catatanTolak,
            'dibatalkan_admin' => self::apakahBatalAdmin($catatanTolak),
            'diajukan_pada' => $r->Created_At ? Carbon::parse($r->Created_At)->format('Y-m-d H:i') : null,
            'diubah_oleh' => $r->Updated_By,
            'diubah_pada' => $r->Updated_At ? Carbon::parse($r->Updated_At)->format('Y-m-d H:i') : null,
            'punya_lampiran' => trim((string) ($r->File_Path ?? '')) !== '',
            'boleh_batal' => self::bolehDibatalkan($status),
        ];
    }

    private function urlLampiran(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            Log::warning('Lampiran konversi lembur tidak bisa ditautkan', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
