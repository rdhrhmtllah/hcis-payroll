<?php

namespace App\Services\ESS\Attendance\Shift\Admin;

use App\Services\ESS\Attendance\Shift\Request\ShiftRequestService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pantauan & pembatalan seluruh pengajuan perubahan jadwal oleh admin HC:
 * Ganti Shift (karyawan sendiri, leader, admin), Tukar Hari (karyawan), dan
 * Status Hari (leader, admin).
 *
 * Satu baris = satu karyawan dalam satu transaksi (pengajuan leader bisa
 * berisi banyak anggota). Statusnya diturunkan dari tanggal-tanggal AKTIF:
 *  - terap = sudah masuk jadwal (Flag_Applied 'Y')
 *  - tunda = belum diterapkan dan tidak ditolak
 *
 * Admin HANYA membatalkan tanggal tertunda. Tanggal yang sudah diterapkan tidak
 * bisa dibalik dengan bersih — penerapan menonaktifkan shift sementara lain dan
 * mengakhiri custom-off tanpa tautan ke transaksinya — jadi koreksinya lewat
 * Shift Admin (keputusan user, 13 Okt 2026). Karena jadwal tak tersentuh,
 * pembatalan tidak memicu hitung ulang absensi.
 */
class PengajuanShiftAdminService
{
    public const STATUS = ['MENUNGGU', 'SEBAGIAN', 'DITERAPKAN', 'DITOLAK', 'DIBATALKAN'];

    public const JENIS = ['GANTI_SHIFT', 'STATUS_HARI', 'TUKAR_HARI'];

    public const SUMBER = ['KARYAWAN', 'LEADER', 'ADMIN'];

    /** Penanda di Note_Tolak: pembeda batal-admin dari tolak approver & batal pengaju. */
    public const PENANDA_BATAL_ADMIN = 'Dibatalkan admin: ';

    /** Saringan organisasi (multi-pilih) => kolom yang dicocokkan persis. */
    public const SARINGAN_ORGANISASI = [
        'divisi' => 'vd.nama_divisi',
        'departemen' => 'vd.nama_sub_divisi',
        'level' => 'lvl.nama_level',
        'jabatan' => 'lvl.nama_jabatan',
        'lokasi' => 'k.Cabang_Untuk_Gaji',
    ];

    /** Sumber pengajuan yang mungkin per jenis. */
    private const SUMBER_PER_JENIS = [
        'GANTI_SHIFT' => ['KARYAWAN', 'LEADER', 'ADMIN'],
        'STATUS_HARI' => ['LEADER', 'ADMIN'],
        'TUKAR_HARI' => ['KARYAWAN'],
    ];

    /** Tabel 3 tingkat (header → karyawan → tanggal) per jenis. */
    private const TABEL = [
        'GANTI_SHIFT' => ['header' => 'N_HRIS_Transaksi_Shift', 'detail' => 'N_HRIS_Transaksi_Shift_Detail', 'det' => 'N_HRIS_Transaksi_Shift_Det', 'tanggal' => 'Tanggal_Shift'],
        'STATUS_HARI' => ['header' => 'N_HRIS_Transaksi_Status_Hari', 'detail' => 'N_HRIS_Transaksi_Status_Hari_Detail', 'det' => 'N_HRIS_Transaksi_Status_Hari_Det', 'tanggal' => 'Tanggal'],
    ];

    /** Note_Tolak varchar(200) di Tukar Hari & Status Hari — kolom terkecil. */
    private const PANJANG_CATATAN = 200;

    private const MIN_ALASAN = 5;

    /** 5 saringan x 200 nilai tetap jauh di bawah batas 2.100 parameter SQL Server. */
    private const MAKS_NILAI_SARINGAN = 200;

    private const URUT = [
        'dibuat' => 't.Created_At',
        'tanggal' => 't.Tgl_Min',
    ];

    public static function statusDariHitungan(bool $headerBatal, int $aktif, int $terap, int $tunda): string
    {
        if ($headerBatal || $aktif === 0) {
            return 'DIBATALKAN';
        }
        if ($tunda > 0) {
            return $terap > 0 ? 'SEBAGIAN' : 'MENUNGGU';
        }

        return $terap > 0 ? 'DITERAPKAN' : 'DITOLAK';
    }

    /** Padanan SQL statusDariHitungan() atas kolom turunan subkueri `t`. */
    public static function statusSql(): string
    {
        return "CASE WHEN t.Batal = 1 OR t.N_Aktif = 0 THEN 'DIBATALKAN'"
            ." WHEN t.N_Tunda > 0 AND t.N_Terap > 0 THEN 'SEBAGIAN'"
            ." WHEN t.N_Tunda > 0 THEN 'MENUNGGU'"
            ." WHEN t.N_Terap > 0 THEN 'DITERAPKAN'"
            ." ELSE 'DITOLAK' END";
    }

    public static function bolehDibatalkan(string $status): bool
    {
        return in_array($status, ['MENUNGGU', 'SEBAGIAN'], true);
    }

    public static function catatanBatal(string $alasan): string
    {
        $alasan = trim(preg_replace('/\s+/', ' ', $alasan));
        if (mb_strlen($alasan) < self::MIN_ALASAN) {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi (minimal '.self::MIN_ALASAN.' karakter).');
        }

        return mb_substr(self::PENANDA_BATAL_ADMIN.$alasan, 0, self::PANJANG_CATATAN);
    }

    public static function apakahBatalAdmin(?string $catatan): bool
    {
        return $catatan !== null && str_starts_with($catatan, self::PENANDA_BATAL_ADMIN);
    }

    /** Sama dengan deteksi di ShiftRequestService::cancelLeaderShiftTransaction(). */
    public static function jenisDariNomor(string $noTransaksi): string
    {
        if (str_starts_with($noTransaksi, 'TSH')) {
            return 'STATUS_HARI';
        }

        return str_starts_with($noTransaksi, 'TH') ? 'TUKAR_HARI' : 'GANTI_SHIFT';
    }

    public static function sumberDari(string $jenisTransaksi, ?string $flagAdmin): string
    {
        if (in_array($jenisTransaksi, ['SELF_SHIFT', 'TUKAR_HARI'], true)) {
            return 'KARYAWAN';
        }

        return $flagAdmin === 'Y' ? 'ADMIN' : 'LEADER';
    }

    /**
     * Jenis yang perlu dihitung untuk saringan ini — subkueri yang tak mungkin
     * cocok tidak ikut dijalankan.
     *
     * @return array<int, string>
     */
    public static function sumberDihitung(array $f): array
    {
        return array_values(array_filter(self::JENIS, fn ($j) => (! $f['jenis'] || $f['jenis'] === $j)
            && (! $f['sumber'] || in_array($f['sumber'], self::SUMBER_PER_JENIS[$j], true))));
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

        $pilih = function (string $kunci, array $boleh) use ($f): ?string {
            $v = strtoupper(trim((string) ($f[$kunci] ?? '')));

            return in_array($v, $boleh, true) ? $v : null;
        };
        $urut = (string) ($f['urut'] ?? 'dibuat');

        return $organisasi + [
            'status' => $pilih('status', self::STATUS),
            'jenis' => $pilih('jenis', self::JENIS),
            'sumber' => $pilih('sumber', self::SUMBER),
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
        $kosong = ['items' => [], 'total' => 0, 'page' => $f['page'], 'limit' => $f['limit']];
        $q = $this->basis(self::sumberDihitung($f));
        if (! $q) {
            return $kosong;
        }
        $this->terapkanFilter($q, $f, true);

        $total = (clone $q)->count();
        $rows = $q->select($this->kolomBaris())
            ->orderBy(self::URUT[$f['urut']], $f['arah'])
            ->orderBy('t.No_Transaksi', 'desc')
            ->orderBy('t.Id_Detail')
            ->forPage($f['page'], $f['limit'])
            ->get();

        return ['items' => $rows->map(fn ($r) => $this->bentukBaris($r))->values()->all(), 'total' => $total] + $kosong;
    }

    /**
     * Ringkasan per status untuk filter yang sama KECUALI status, supaya angka
     * di chip menjawab "berapa yang akan muncul bila chip ini dipilih".
     *
     * @return array<string, int>
     */
    public function stats(array $filter): array
    {
        $f = $this->normalisasiFilter($filter);
        $hasil = ['total' => 0] + array_fill_keys(array_map('strtolower', self::STATUS), 0);
        $q = $this->basis(self::sumberDihitung($f));
        if (! $q) {
            return $hasil;
        }
        $this->terapkanFilter($q, $f, false);

        $status = self::statusSql();
        $parts = ['COUNT(*) AS total'];
        foreach (self::STATUS as $s) {
            $parts[] = "SUM(CASE WHEN ({$status}) = '{$s}' THEN 1 ELSE 0 END) AS ".strtolower($s);
        }
        $r = $q->selectRaw(implode(', ', $parts))->first();

        foreach (array_keys($hasil) as $k) {
            $hasil[$k] = (int) ($r->{$k} ?? 0);
        }

        return $hasil;
    }

    /**
     * Pilihan saringan organisasi, hanya dari karyawan yang PERNAH masuk pengajuan.
     * Departemen membawa divisinya agar layar bisa menyempitkannya.
     */
    public function opsiSaringan(): array
    {
        $nilai = fn (string $kolom) => $this->basis(self::JENIS)
            ->whereNotNull($kolom)->where($kolom, '<>', '')
            ->distinct()->orderBy($kolom)->pluck($kolom)
            ->map(fn ($v) => (string) $v)->values()->all();

        $departemen = $this->basis(self::JENIS)
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

    /**
     * Detail satu karyawan dalam satu transaksi: data baris, rincian per tanggal,
     * alasan pengajuan, dan jejak persetujuan transaksinya.
     *
     * @return array<string, mixed>|null
     */
    public function detail(string $noTransaksi, int $idDetail, ShiftRequestService $shift): ?array
    {
        $jenis = self::jenisDariNomor($noTransaksi);
        $row = $this->basis([$jenis])
            ->where('t.No_Transaksi', $noTransaksi)->where('t.Id_Detail', $idDetail)
            ->select($this->kolomBaris())->first();
        if (! $row) {
            return null;
        }

        $baris = $this->bentukBaris($row);
        $sumber = $shift->getDetail($noTransaksi) ?? [];

        $baris['alasan'] = (string) ($sumber['header']->Alasan ?? '');
        $baris['tanggal'] = $jenis === 'TUKAR_HARI'
            ? $this->rincianTukarHari($idDetail)
            : $this->rincianTigaTingkat($jenis, $idDetail);
        $baris['approval_steps'] = $this->langkahApproval($sumber['approval_steps'] ?? []);

        return $baris;
    }

    /**
     * Batalkan tanggal-tanggal yang BELUM diterapkan milik satu karyawan dalam
     * satu transaksi. Yang sudah diterapkan tetap berlaku. Anggota lain dalam
     * pengajuan leader yang sama tidak tersentuh.
     *
     * @return array{status: bool, message: string, dibatalkan: int, tetap_berlaku: int}
     */
    public function batal(string $noTransaksi, int $idDetail, string $aktor, string $alasan): array
    {
        $catatan = self::catatanBatal($alasan);
        $jenis = self::jenisDariNomor($noTransaksi);

        [$dibatalkan, $tetap] = DB::transaction(fn () => $jenis === 'TUKAR_HARI'
            ? $this->batalTukarHari($noTransaksi, $idDetail, $aktor, $catatan)
            : $this->batalTigaTingkat($jenis, $noTransaksi, $idDetail, $aktor, $catatan));

        return [
            'status' => true,
            'message' => $tetap > 0
                ? "{$dibatalkan} tanggal dibatalkan; {$tetap} tanggal yang sudah diterapkan tetap berlaku."
                : 'Pengajuan dibatalkan.',
            'dibatalkan' => $dibatalkan,
            'tetap_berlaku' => $tetap,
        ];
    }

    /**
     * Ganti Shift & Status Hari (header → karyawan → tanggal).
     *
     * @return array{0:int,1:int} [dibatalkan, tetap berlaku]
     */
    private function batalTigaTingkat(string $jenis, string $noTransaksi, int $idDetail, string $aktor, string $catatan): array
    {
        $t = self::TABEL[$jenis];

        $header = DB::table($t['header'])->where('No_Transaksi', $noTransaksi)->lockForUpdate()->first();
        $jenisSah = $jenis === 'STATUS_HARI' ? ['STATUS_HARI'] : ['SELF_SHIFT', 'LEADER_SHIFT'];
        if (! $header || ! in_array($header->Jenis_Transaksi, $jenisSah, true)) {
            throw new DomainException('Pengajuan tidak ditemukan.');
        }
        if ($header->Status === 'Y') {
            throw new DomainException('Pengajuan sudah dibatalkan sebelumnya.');
        }

        $detail = DB::table($t['detail'])->where('No_Transaksi', $noTransaksi)->where('Id', $idDetail)->first();
        if (! $detail) {
            throw new DomainException('Karyawan ini tidak ada dalam pengajuan tersebut.');
        }
        if ($detail->Status === 'Y') {
            throw new DomainException('Pengajuan untuk karyawan ini sudah dibatalkan sebelumnya.');
        }

        $aktif = DB::table($t['det'])->where('Id_Detail', $idDetail)->whereNull('Status')
            ->get(['Id', 'Flag_Applied', 'Flag_Approval']);
        $terap = $aktif->where('Flag_Applied', 'Y');
        $tunda = $aktif->filter(fn ($r) => $r->Flag_Applied !== 'Y' && $r->Flag_Approval !== 'T' && $detail->Flag_Approval !== 'T');
        if ($tunda->isEmpty()) {
            throw new DomainException('Tidak ada tanggal yang belum diterapkan. Ubah jadwal yang sudah berlaku lewat Shift Admin.');
        }

        DB::table($t['det'])->whereIn('Id', $tunda->pluck('Id')->all())
            ->update(['Status' => 'Y', 'Note_Tolak' => $catatan]);

        if ($terap->isEmpty()) {
            // Sisa tanggal aktif hanya yang ditolak: karyawan ini keluar seluruhnya.
            DB::table($t['det'])->where('Id_Detail', $idDetail)->whereNull('Status')->update(['Status' => 'Y']);
            DB::table($t['detail'])->where('Id', $idDetail)->update(['Status' => 'Y']);
        }

        $pembaruanHeader = ['Updated_At' => now(), 'Updated_By' => $aktor];
        $sisaKaryawan = DB::table($t['detail'])->where('No_Transaksi', $noTransaksi)->whereNull('Status')->exists();
        if (! $sisaKaryawan) {
            $pembaruanHeader += ['Status' => 'Y', 'Flag_Selesai' => 'Y'];
        }

        // Anggota lain yang masih menunggu tetap butuh approver: approval
        // ditutup hanya bila tak ada lagi tanggal tertunda di seluruh transaksi.
        $masihTertunda = DB::table($t['det'].' as det')
            ->join($t['detail'].' as d', 'd.Id', '=', 'det.Id_Detail')
            ->where('det.No_Transaksi', $noTransaksi)
            ->whereNull('det.Status')->whereNull('d.Status')
            ->whereRaw("ISNULL(det.Flag_Applied, '') <> 'Y' AND ISNULL(det.Flag_Approval, '') <> 'T' AND ISNULL(d.Flag_Approval, '') <> 'T'")
            ->exists();
        if (! $masihTertunda) {
            $pembaruanHeader['Flag_Selesai'] = 'Y';
            $this->tutupApprovalTertunda($noTransaksi);
        }

        DB::table($t['header'])->where('No_Transaksi', $noTransaksi)->update($pembaruanHeader);

        return [$tunda->count(), $terap->count()];
    }

    /** @return array{0:int,1:int} [dibatalkan, tetap berlaku] */
    private function batalTukarHari(string $noTransaksi, int $idDetail, string $aktor, string $catatan): array
    {
        $header = DB::table('N_HRIS_Transaksi_Tukar_Hari')->where('No_Transaksi', $noTransaksi)->lockForUpdate()->first();
        if (! $header) {
            throw new DomainException('Pengajuan tidak ditemukan.');
        }
        if ($header->Status === 'Y') {
            throw new DomainException('Pengajuan sudah dibatalkan sebelumnya.');
        }

        $baris = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')
            ->where('No_Transaksi', $noTransaksi)->where('Id', $idDetail)->whereNull('Status')
            ->first(['Id', 'Flag_Applied', 'Flag_Approval']);
        if (! $baris || $baris->Flag_Applied === 'Y' || $baris->Flag_Approval === 'T') {
            throw new DomainException('Tukar hari ini sudah diterapkan, ditolak, atau dibatalkan. Ubah jadwal yang sudah berlaku lewat Shift Admin.');
        }

        DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')->where('Id', $idDetail)
            ->update(['Status' => 'Y', 'Note_Tolak' => $catatan]);

        $sisa = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')->where('No_Transaksi', $noTransaksi)->whereNull('Status')->get(['Flag_Applied', 'Flag_Approval']);
        $pembaruanHeader = ['Updated_At' => now(), 'Updated_By' => $aktor];
        if ($sisa->where('Flag_Applied', 'Y')->isEmpty()) {
            DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail')->where('No_Transaksi', $noTransaksi)->whereNull('Status')->update(['Status' => 'Y']);
            $pembaruanHeader['Status'] = 'Y';
        }
        $tetap = $sisa->where('Flag_Applied', 'Y')->count();
        if ($sisa->filter(fn ($r) => $r->Flag_Applied !== 'Y' && $r->Flag_Approval !== 'T')->isEmpty()) {
            $pembaruanHeader['Flag_Selesai'] = 'Y';
            $this->tutupApprovalTertunda($noTransaksi);
        }

        DB::table('N_HRIS_Transaksi_Tukar_Hari')->where('No_Transaksi', $noTransaksi)->update($pembaruanHeader);

        return [1, $tetap];
    }

    /**
     * Langkah approval yang belum diputus ditutup — sama dengan yang dilakukan
     * pembatalan oleh pengaju — agar tak tersisa di kotak masuk approver.
     */
    private function tutupApprovalTertunda(string $noTransaksi): void
    {
        DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->whereNull('Flag_Approval')
            ->whereNull('Status')
            ->update(['Status' => 'Y', 'update_at' => now()]);
    }

    /**
     * Satu baris per karyawan per transaksi dari jenis-jenis yang diminta.
     * Kolom yang dikelompokkan sengaja tanpa teks panjang (nvarchar(max) tak
     * boleh di GROUP BY). Null bila tak ada jenis yang perlu dihitung.
     */
    private function basis(array $jenis): ?Builder
    {
        $bagian = array_map(fn ($j) => $j === 'TUKAR_HARI' ? $this->subTukarHari() : $this->subTigaTingkat($j), $jenis);
        if ($bagian === []) {
            return null;
        }

        $gabungan = array_shift($bagian);
        foreach ($bagian as $b) {
            $gabungan->unionAll($b);
        }

        return DB::query()
            ->fromSub($gabungan, 't')
            ->leftJoin('Karyawan as k', function ($j) {
                // Kode_Karyawan unik hanya per perusahaan (PK komposit).
                $j->on('k.Kode_Karyawan', '=', 't.Kode_Karyawan')->on('k.Kode_Perusahaan', '=', 't.Kode_Perusahaan');
            })
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_Divisi_Sub_Divisi', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            // Created_By menyimpan Id_Users (varchar); UserID_Web unik di Karyawan.
            ->leftJoin('Karyawan as pengaju', 'pengaju.UserID_Web', '=', DB::raw('TRY_CAST(t.Created_By AS int)'));
    }

    private function subTigaTingkat(string $jenis): Builder
    {
        $t = self::TABEL[$jenis];
        $tgl = 'det.'.$t['tanggal'];
        $aktif = 'det.Id IS NOT NULL AND det.Status IS NULL AND d.Status IS NULL';
        $sumber = "CASE WHEN h.Jenis_Transaksi = 'SELF_SHIFT' THEN 'KARYAWAN' WHEN h.Flag_Admin = 'Y' THEN 'ADMIN' ELSE 'LEADER' END";
        $ringkasStatusHari = $jenis === 'STATUS_HARI'
            ? "CASE WHEN MIN(det.Status_Hari) = MAX(det.Status_Hari) THEN MIN(det.Status_Hari) ELSE 'CAMPURAN' END"
            : 'CAST(NULL AS varchar(10))';

        return DB::table($t['header'].' as h')
            ->join($t['detail'].' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin($t['det'].' as det', 'det.Id_Detail', '=', 'd.Id')
            ->whereIn('h.Jenis_Transaksi', $jenis === 'STATUS_HARI' ? ['STATUS_HARI'] : ['SELF_SHIFT', 'LEADER_SHIFT'])
            ->groupBy('d.Id', 'h.No_Transaksi', 'd.Kode_Karyawan', 'd.Kode_Perusahaan', 'd.Target_Table', 'h.Created_At', 'h.Created_By',
                'h.Updated_At', 'h.Updated_By', 'h.Status', 'h.Jenis_Transaksi', 'h.Flag_Admin')
            ->selectRaw("CAST('{$jenis}' AS varchar(20)) AS Jenis, CAST({$sumber} AS varchar(10)) AS Sumber,"
                .' h.No_Transaksi, d.Id AS Id_Detail, d.Kode_Karyawan, d.Kode_Perusahaan,'
                .' h.Created_At, CAST(h.Created_By AS varchar(50)) AS Created_By, h.Updated_At, CAST(h.Updated_By AS varchar(50)) AS Updated_By,'
                ." CASE WHEN h.Status = 'Y' THEN 1 ELSE 0 END AS Batal,"
                .' CAST(d.Target_Table AS varchar(50)) AS Target_Table,'
                ." MIN({$tgl}) AS Tgl_Min, MAX({$tgl}) AS Tgl_Max,"
                .' CAST(NULL AS date) AS Tanggal_Bekerja, CAST(NULL AS date) AS Tanggal_Off,'
                ." {$ringkasStatusHari} AS Status_Hari,"
                .' COUNT(det.Id) AS N_Tanggal,'
                ." SUM(CASE WHEN {$aktif} THEN 1 ELSE 0 END) AS N_Aktif,"
                ." SUM(CASE WHEN {$aktif} AND det.Flag_Applied = 'Y' THEN 1 ELSE 0 END) AS N_Terap,"
                ." SUM(CASE WHEN {$aktif} AND ISNULL(det.Flag_Applied, '') <> 'Y'"
                ." AND ISNULL(det.Flag_Approval, '') <> 'T' AND ISNULL(d.Flag_Approval, '') <> 'T' THEN 1 ELSE 0 END) AS N_Tunda,"
                .' MAX(CASE WHEN det.Note_Tolak LIKE ? THEN CAST(det.Note_Tolak AS nvarchar(300)) END) AS Catatan_Admin,'
                ." MAX(CASE WHEN ISNULL(det.Flag_Approval, '') = 'T' THEN CAST(det.Note_Tolak AS nvarchar(300)) END) AS Catatan_Tolak",
                [self::PENANDA_BATAL_ADMIN.'%']);
    }

    private function subTukarHari(): Builder
    {
        return DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->selectRaw("CAST('TUKAR_HARI' AS varchar(20)) AS Jenis, CAST('KARYAWAN' AS varchar(10)) AS Sumber,"
                .' h.No_Transaksi, d.Id AS Id_Detail, h.Kode_Karyawan, h.Kode_Perusahaan,'
                .' h.Created_At, CAST(h.Created_By AS varchar(50)) AS Created_By, h.Updated_At, CAST(h.Updated_By AS varchar(50)) AS Updated_By,'
                ." CASE WHEN h.Status = 'Y' THEN 1 ELSE 0 END AS Batal,"
                .' CAST(NULL AS varchar(50)) AS Target_Table,'
                .' CASE WHEN d.Tanggal_Off IS NULL OR d.Tanggal_Bekerja < d.Tanggal_Off THEN d.Tanggal_Bekerja ELSE d.Tanggal_Off END AS Tgl_Min,'
                .' CASE WHEN d.Tanggal_Off IS NULL OR d.Tanggal_Bekerja > d.Tanggal_Off THEN d.Tanggal_Bekerja ELSE d.Tanggal_Off END AS Tgl_Max,'
                .' d.Tanggal_Bekerja, d.Tanggal_Off, CAST(NULL AS varchar(10)) AS Status_Hari,'
                .' 1 AS N_Tanggal,'
                .' CASE WHEN d.Status IS NULL THEN 1 ELSE 0 END AS N_Aktif,'
                ." CASE WHEN d.Status IS NULL AND d.Flag_Applied = 'Y' THEN 1 ELSE 0 END AS N_Terap,"
                ." CASE WHEN d.Status IS NULL AND ISNULL(d.Flag_Applied, '') <> 'Y' AND ISNULL(d.Flag_Approval, '') <> 'T' THEN 1 ELSE 0 END AS N_Tunda,"
                .' CASE WHEN d.Note_Tolak LIKE ? THEN CAST(d.Note_Tolak AS nvarchar(300)) END AS Catatan_Admin,'
                ." CASE WHEN ISNULL(d.Flag_Approval, '') = 'T' THEN CAST(d.Note_Tolak AS nvarchar(300)) END AS Catatan_Tolak",
                [self::PENANDA_BATAL_ADMIN.'%']);
    }

    private function terapkanFilter(Builder $q, array $f, bool $denganStatus): void
    {
        if ($denganStatus && $f['status']) {
            $q->whereRaw(self::statusSql().' = ?', [$f['status']]);
        }
        if ($f['sumber']) {
            $q->where('t.Sumber', $f['sumber']);
        }
        foreach (self::SARINGAN_ORGANISASI as $kunci => $kolom) {
            if ($f[$kunci] !== []) {
                $q->whereIn($kolom, $f[$kunci]);
            }
        }
        // Rentang tanggal: baris yang tanggal-tanggalnya BERIRISAN dengan rentang.
        if ($f['tanggal_dari']) {
            $q->where('t.Tgl_Max', '>=', $f['tanggal_dari']);
        }
        if ($f['tanggal_sampai']) {
            $q->where('t.Tgl_Min', '<=', $f['tanggal_sampai']);
        }
        if ($f['search'] !== '') {
            $pola = '%'.strtr($f['search'], ['[' => '[[]', '%' => '[%]', '_' => '[_]']).'%';
            $q->where(function ($w) use ($pola) {
                $w->where('k.Nama', 'LIKE', $pola)
                    ->orWhere('t.Kode_Karyawan', 'LIKE', $pola)
                    ->orWhere('t.No_Transaksi', 'LIKE', $pola)
                    ->orWhere('pengaju.Nama', 'LIKE', $pola);
            });
        }
    }

    /** @return array<int, mixed> */
    private function kolomBaris(): array
    {
        return [
            't.*',
            'k.Nama',
            'vd.nama_divisi as Divisi',
            'vd.nama_sub_divisi as Departemen',
            'lvl.nama_level as Level',
            'lvl.nama_jabatan as Jabatan',
            'k.Cabang_Untuk_Gaji as Lokasi',
            'pengaju.Nama as Nama_Pengaju',
            DB::raw(self::statusSql().' as Status_Turunan'),
        ];
    }

    /** @return array<string, mixed> */
    private function bentukBaris(object $r): array
    {
        $tgl = fn ($v) => $v ? Carbon::parse($v)->toDateString() : null;
        $teks = fn ($v) => $v !== null && $v !== '' ? (string) $v : null;
        $status = (string) $r->Status_Turunan;

        return [
            'kunci' => $r->No_Transaksi.'#'.$r->Id_Detail,
            'no_transaksi' => (string) $r->No_Transaksi,
            'id_detail' => (int) $r->Id_Detail,
            'jenis' => (string) $r->Jenis,
            'sumber' => (string) $r->Sumber,
            'pengaju' => $teks($r->Nama_Pengaju),
            'kode_karyawan' => (string) $r->Kode_Karyawan,
            'nama' => (string) ($r->Nama ?? $r->Kode_Karyawan),
            'divisi' => $teks($r->Divisi),
            'departemen' => $teks($r->Departemen),
            'level' => $teks($r->Level),
            'jabatan' => $teks($r->Jabatan),
            'lokasi' => $teks($r->Lokasi),
            'target' => $teks($r->Target_Table),
            'status_hari' => $teks($r->Status_Hari),
            'tanggal_awal' => $tgl($r->Tgl_Min),
            'tanggal_akhir' => $tgl($r->Tgl_Max),
            'tanggal_bekerja' => $tgl($r->Tanggal_Bekerja),
            'tanggal_off' => $tgl($r->Tanggal_Off),
            'jumlah_tanggal' => (int) $r->N_Tanggal,
            'jumlah_diterapkan' => (int) $r->N_Terap,
            'jumlah_tertunda' => (int) $r->N_Tunda,
            'status' => $status,
            'catatan_batal_admin' => $teks($r->Catatan_Admin),
            'catatan_tolak' => $teks($r->Catatan_Tolak),
            'diajukan_pada' => $r->Created_At ? Carbon::parse($r->Created_At)->format('Y-m-d H:i') : null,
            'diubah_pada' => $r->Updated_At ? Carbon::parse($r->Updated_At)->format('Y-m-d H:i') : null,
            'boleh_batal' => self::bolehDibatalkan($status),
        ];
    }

    private static function statusTanggal(?string $status, ?string $applied, ?string $approval): string
    {
        if ($status === 'Y') {
            return 'DIBATALKAN';
        }
        if ($applied === 'Y') {
            return 'DITERAPKAN';
        }

        return $approval === 'T' ? 'DITOLAK' : 'MENUNGGU';
    }

    /** @return array<int, array<string, mixed>> */
    private function rincianTigaTingkat(string $jenis, int $idDetail): array
    {
        $t = self::TABEL[$jenis];
        $q = DB::table($t['det'].' as det')
            ->join($t['detail'].' as d', 'd.Id', '=', 'det.Id_Detail')
            ->where('det.Id_Detail', $idDetail)
            ->orderBy('det.'.$t['tanggal']);

        $kolom = ['det.'.$t['tanggal'].' as Tanggal', 'det.Status', 'det.Flag_Applied', 'det.Flag_Approval', 'det.Note_Tolak', 'd.Status as Status_Detail'];
        if ($jenis === 'STATUS_HARI') {
            $kolom[] = 'det.Status_Hari';
        } else {
            $q->leftJoin('HRIS_Shift_Kerja as baru', 'baru.ID_Shift', '=', 'det.Id_Shift_Diminta')
                ->leftJoin('HRIS_Shift_Kerja as lama', 'lama.ID_Shift', '=', 'det.Id_Shift_Sebelumnya');
            array_push($kolom, 'baru.Nama as Shift_Baru', 'lama.Nama as Shift_Lama');
        }

        return $q->get($kolom)->map(fn ($r) => [
            'tanggal' => Carbon::parse($r->Tanggal)->toDateString(),
            'shift_lama' => $r->Shift_Lama ?? null,
            'shift_baru' => $r->Shift_Baru ?? null,
            'status_hari' => $r->Status_Hari ?? null,
            'status' => self::statusTanggal($r->Status ?? $r->Status_Detail, $r->Flag_Applied, $r->Flag_Approval),
            'catatan' => $r->Note_Tolak,
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function rincianTukarHari(int $idDetail): array
    {
        return DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
            ->leftJoin('HRIS_Shift_Kerja as s', 's.ID_Shift', '=', 'd.Id_Shift_Bekerja')
            ->where('d.Id', $idDetail)
            ->get(['d.Tanggal_Bekerja', 'd.Tanggal_Off', 'd.Status', 'd.Flag_Applied', 'd.Flag_Approval', 'd.Note_Tolak', 's.Nama as Shift_Bekerja'])
            ->map(fn ($r) => [
                'tanggal_bekerja' => $r->Tanggal_Bekerja ? Carbon::parse($r->Tanggal_Bekerja)->toDateString() : null,
                'tanggal_off' => $r->Tanggal_Off ? Carbon::parse($r->Tanggal_Off)->toDateString() : null,
                'shift_bekerja' => $r->Shift_Bekerja,
                'status' => self::statusTanggal($r->Status, $r->Flag_Applied, $r->Flag_Approval),
                'catatan' => $r->Note_Tolak,
            ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function langkahApproval(iterable $langkah): array
    {
        $hasil = [];
        $giliranDitemukan = false;
        foreach ($langkah as $l) {
            $l = (object) $l;
            $status = match (true) {
                ($l->Flag_Approval ?? null) === 'Y' => 'approved',
                ($l->Flag_Approval ?? null) === 'T' => 'rejected',
                ($l->Status ?? null) === 'Y' => 'closed',
                ! $giliranDitemukan => 'pending',
                default => 'upcoming',
            };
            $giliranDitemukan = $giliranDitemukan || $status === 'pending';

            $hasil[] = [
                'urutan' => (int) ($l->Request_Order_Flow ?? count($hasil) + 1),
                'nama' => (string) ($l->Nama ?? $l->Approver_Karyawan_Kode ?? 'Approver'),
                'status' => $status,
                'waktu' => ! empty($l->Tanggal_Approval) ? Carbon::parse($l->Tanggal_Approval)->format('Y-m-d H:i') : null,
                'catatan' => $l->Catatan ?? null,
            ];
        }

        return $hasil;
    }
}
