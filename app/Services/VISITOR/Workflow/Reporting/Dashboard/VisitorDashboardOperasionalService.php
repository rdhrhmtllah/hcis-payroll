<?php

namespace App\Services\VISITOR\Workflow\Reporting\Dashboard;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Baris 1-2 dashboard Visitor: keadaan area dan antrian approval.
 *
 * Module: VISITOR · Submodule: Workflow · Feature: Reporting Dashboard.
 *
 * Sumber lifecycle tamu adalah tabel header + detail, BUKAN
 * N_VISITOR_Tamu_Efektif. Tabel efektif hanya salinan saat approval;
 * check-in/check-out tidak pernah menulisnya, jadi membacanya untuk
 * keadaan area menghasilkan nol tanpa error.
 */
class VisitorDashboardOperasionalService
{
    public const BATAS_DAFTAR = 8;

    private const T_H = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_D = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    public function __construct(
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorKehadiranQueryService $kehadiran,
    ) {}

    /**
     * Tamu yang sedang berada di dalam area.
     *
     * Kehadiran DITURUNKAN dari waktu masuk/keluar, bukan dari Status_Kunjungan:
     * kolom status hanya ditulis tombol manual HCIS, sehingga tamu yang lewat
     * palang tetap berstatus TERJADWAL dan dulu tidak pernah muncul di sini.
     */
    public function queryDiDalamArea(): Builder
    {
        $query = DB::table(self::T_H.' as h')
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC');

        return $this->kehadiran->join($query)
            ->where('h.Flag_Aktif', 'Y')
            ->whereNotIn('h.Status_Kunjungan', [StatusKunjunganTamu::REJECTED, StatusKunjunganTamu::WITHDRAWN, StatusKunjunganTamu::REVOKED])
            ->whereRaw($this->kehadiran->diDalamSql())
            ->select(
                'h.No_Transaksi',
                'h.Tanggal_Kunjungan',
                'd.Nama_Lengkap_Tamu as Nama_Tamu',
                'd.Instansi_Atau_Asal as Instansi',
                'd.Jumlah_Tamu',
                DB::raw($this->kehadiran->masukSql().' as Waktu_Aktual_Masuk'),
                DB::raw("ISNULL(k.Nama, '-') as Nama_PIC"),
            );
    }

    /**
     * Terjadwal hari ini tetapi belum tercatat masuk. "Hari ini" = rentang
     * estimasinya beririsan dengan hari ini, supaya MITRA berhari-hari ikut.
     */
    public function queryTerjadwalBelumDatang(): Builder
    {
        $query = DB::table(self::T_H.' as h')
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi');
        [$irisan, $ikatan] = $this->kehadiran->beririsanHariSql(now()->toDateString());

        return $this->kehadiran->join($query)
            ->where('h.Flag_Aktif', 'Y')
            ->where('h.Status_Kunjungan', StatusKunjunganTamu::SCHEDULED)
            ->whereRaw($irisan, $ikatan)
            ->whereRaw($this->kehadiran->belumMasukSql())
            ->select('h.No_Transaksi', 'd.Jumlah_Tamu');
    }

    /**
     * Transaksi visitor yang belum diputus, satu baris per No_Transaksi.
     *
     * Penyaring ke ranah visitor lewat EXISTS ke tabel transaksi, bukan lewat
     * awalan nomor: awalan adalah kesepakatan penamaan yang bisa berubah,
     * bukan kunci relasi.
     */
    public function queryAntrianApproval(?string $domain = null): Builder
    {
        // Tanpa argumen perilakunya persis seperti sebelumnya: kedua domain.
        // Penyaring per domain cukup memilih satu cabang EXISTS, sehingga
        // definisi "ranah visitor" tetap satu dan tidak bercabang dua.
        $tamu = "EXISTS (SELECT 1 FROM N_EMI_VISITOR_Transaksi_Tamu t WHERE t.No_Transaksi = ar.No_Transaksi AND t.Flag_Aktif = 'Y')";
        $permit = "EXISTS (SELECT 1 FROM N_EMI_VISITOR_Transaksi_Permit p WHERE p.No_Transaksi = ar.No_Transaksi AND p.Flag_Aktif = 'Y')";
        // 'tamu' = Regular, 'tamu_b2b' = B2B (DDL-2026-121): keduanya kini
        // halaman terpisah dengan antrean sendiri.
        $tamuBertipe = fn (string $syarat) => 'EXISTS (SELECT 1 FROM N_EMI_VISITOR_Transaksi_Tamu t '
            .'JOIN N_EMI_VISITOR_Transaksi_Tamu_Detail td ON td.No_Transaksi = t.No_Transaksi '
            ."WHERE t.No_Transaksi = ar.No_Transaksi AND t.Flag_Aktif = 'Y' AND {$syarat})";

        $ranah = match ($domain) {
            'tamu' => $tamuBertipe("(td.Tipe_Tamu IS NULL OR td.Tipe_Tamu = 'REGULAR')"),
            'tamu_b2b' => $tamuBertipe("td.Tipe_Tamu = 'B2B'"),
            'permit' => $permit,
            default => $tamu.' OR '.$permit,
        };

        return DB::table('HRIS_Approval_Request as ar')
            ->whereRaw($ranah)
            ->groupBy('ar.No_Transaksi')
            ->havingRaw("MAX(CASE WHEN ar.Flag_Approval = 'T' THEN 1 ELSE 0 END) = 0")
            ->havingRaw('MAX(CASE WHEN ar.Flag_Approval IS NULL THEN 1 ELSE 0 END) = 1')
            ->select(
                'ar.No_Transaksi',
                DB::raw('MIN(ar.created_at) as masuk_antrian'),
                DB::raw('MAX(CASE WHEN ar.Flag_Approval IS NULL AND (ar.Expired_At IS NULL OR ar.Expired_At >= GETDATE()) THEN 1 ELSE 0 END) as ada_pending'),
                DB::raw('MAX(CASE WHEN ar.Flag_Approval IS NULL AND ar.Expired_At IS NOT NULL AND ar.Expired_At < GETDATE() THEN 1 ELSE 0 END) as ada_kadaluarsa'),
            );
    }

    /** Kolom antrean yang boleh diurutkan. Nilai permintaan tidak pernah masuk ORDER BY. */
    private const URUT_ANTRIAN = [
        'menunggu' => 'q.masuk_antrian',
        'nomor' => 'q.No_Transaksi',
        'jenis' => 'q.jenis',
        'judul' => 'q.judul',
        'pemohon' => 'q.pemohon',
        'approver' => 'q.approver',
    ];

    /**
     * Antrean approval berhalaman, dicari, dan diurutkan DI SQL.
     *
     * Identitas (nama tamu / plat, PIC / pemohon, approver) dulu ditempelkan
     * SESUDAH pemotongan halaman lewat lengkapiAntrian(). Itu cukup selama
     * daftarnya delapan baris, tetapi membuat pencarian dan pengurutan mustahil
     * menyentuh kolom-kolom itu — yang ada di PHP tidak bisa dipakai SQL. Di
     * sini identitasnya jadi kolom turunan sehingga WHERE dan ORDER BY bekerja
     * pada seluruh kolom yang tampil, bukan hanya nomor transaksi.
     *
     * @param  array<string,mixed>  $opsi
     * @return array<string,mixed>
     */
    public function antrianBerhalaman(array $opsi = [], ?string $domain = null): array
    {
        $halaman = max((int) ($opsi['page'] ?? 1), 1);
        $batas = min(max((int) ($opsi['limit'] ?? 10), 1), 100);
        $cari = trim((string) ($opsi['cari'] ?? ''));

        $dasar = $this->dasarAntrian($domain, $cari);

        // GROUP BY + HAVING di kueri inti membuat count() biasa menghitung
        // jumlah grup per baris. Karena inti sudah jadi tabel turunan, count()
        // di sini menghitung barisnya — bukan grupnya.
        $total = (clone $dasar)->count();

        [$kolom, $arah] = $this->urutanAntrian($opsi);

        $dasar->select('q.*')->orderBy(DB::raw($kolom), $arah);

        // Kunci kedua yang unik: tanpa ini baris berperingkat sama bisa
        // berpindah antar halaman sehingga ada pengajuan yang tak pernah tampil
        // di halaman mana pun. Saat pengurutnya SUDAH nomor transaksi, kunci
        // ini justru menyebutkan kolom yang sama dua kali — SQL Server menolak
        // ORDER BY berkolom kembar, jadi kueri gagal total, bukan sekadar
        // mengurut aneh.
        if ($kolom !== self::URUT_ANTRIAN['nomor']) {
            $dasar->orderBy('q.No_Transaksi', $arah);
        }

        $baris = $dasar
            ->forPage($halaman, $batas)
            ->get()
            ->map(fn ($r) => [
                'no_transaksi' => (string) $r->No_Transaksi,
                'sejak' => $r->masuk_antrian,
                'umur_menit' => (int) $r->umur_menit,
                'jenis' => (string) $r->jenis,
                'judul' => (string) $r->judul,
                'nama' => (string) $r->judul,
                'instansi' => (string) $r->instansi,
                'pic' => (string) $r->pemohon,
                'pemohon' => (string) $r->pemohon,
                'approver' => (string) $r->approver,
            ])
            ->all();

        return [
            'baris' => $baris,
            'paginasi' => [
                'total' => $total,
                'current_page' => $halaman,
                'per_page' => $batas,
                'total_pages' => (int) ceil($total / $batas),
            ],
        ];
    }

    /**
     * Antrean + identitasnya sebagai satu tabel turunan yang bisa disaring.
     *
     * Identitas diambil lewat subkueri skalar, bukan JOIN: satu transaksi bisa
     * punya lebih dari satu baris detail, dan JOIN akan menggandakan barisnya
     * sehingga totalnya salah tanpa satu pun error.
     */
    private function dasarAntrian(?string $domain, string $cari): Builder
    {
        $detail = self::T_D;
        $header = self::T_H;
        $plat = $this->permitQuery->platNomorSql('pd');

        $judulTamu = "(SELECT TOP 1 td.Nama_Lengkap_Tamu FROM {$detail} td WHERE td.No_Transaksi = a.No_Transaksi)";
        $instansiTamu = "(SELECT TOP 1 td.Instansi_Atau_Asal FROM {$detail} td WHERE td.No_Transaksi = a.No_Transaksi)";
        $picTamu = "(SELECT TOP 1 k.Nama FROM {$header} th LEFT JOIN Karyawan k ON k.Kode_Karyawan = th.Kode_Karyawan_PIC WHERE th.No_Transaksi = a.No_Transaksi)";
        $judulPermit = "(SELECT TOP 1 {$plat} FROM N_EMI_VISITOR_Transaksi_Permit_Detail pd WHERE pd.No_Transaksi = a.No_Transaksi)";
        $pemohonPermit = '(SELECT TOP 1 k.Nama FROM N_EMI_VISITOR_Transaksi_Permit ph LEFT JOIN Karyawan k ON k.Kode_Karyawan = ph.Kode_Karyawan_Pemohon WHERE ph.No_Transaksi = a.No_Transaksi)';
        $approver = "(SELECT TOP 1 ISNULL(rf.Role_Name, ISNULL(ka.Nama, '-')) FROM HRIS_Approval_Request ar2 LEFT JOIN N_HRIS_Role_Functional rf ON rf.Id_Role = ar2.Group_Reference_Id LEFT JOIN Karyawan ka ON ka.Kode_Karyawan = ar2.Approver_Karyawan_Kode WHERE ar2.No_Transaksi = a.No_Transaksi AND ar2.Flag_Approval IS NULL)";
        $adaTamu = "EXISTS (SELECT 1 FROM {$header} t WHERE t.No_Transaksi = a.No_Transaksi)";

        $berkolom = DB::query()
            ->fromSub($this->queryAntrianApproval($domain), 'a')
            // Hanya yang benar-benar masih menunggu keputusan. Baris yang
            // seluruh approvernya kedaluwarsa dihitung terpisah sebagai
            // "kadaluarsa" dan bukan bagian dari antrean ini.
            ->where('a.ada_pending', 1)
            ->select(
                'a.No_Transaksi',
                'a.masuk_antrian',
                DB::raw('DATEDIFF(minute, a.masuk_antrian, GETDATE()) as umur_menit'),
                DB::raw("CASE WHEN {$adaTamu} THEN 'TAMU' ELSE 'PERMIT' END as jenis"),
                DB::raw("ISNULL({$judulTamu}, ISNULL({$judulPermit}, '-')) as judul"),
                DB::raw("ISNULL({$instansiTamu}, '-') as instansi"),
                DB::raw("ISNULL({$picTamu}, ISNULL({$pemohonPermit}, '-')) as pemohon"),
                DB::raw("ISNULL({$approver}, '-') as approver"),
            );

        // Dibungkus sekali lagi supaya penyaring dan pengurut bisa menyebut
        // ALIAS kolom turunan; menyebutnya di lapis yang sama dengan SELECT-nya
        // tidak diizinkan SQL Server.
        $query = DB::query()->fromSub($berkolom, 'q');

        if ($cari !== '') {
            $suka = '%'.$cari.'%';
            $query->where(function ($q) use ($suka) {
                $q->where('q.No_Transaksi', 'LIKE', $suka)
                    ->orWhere('q.judul', 'LIKE', $suka)
                    ->orWhere('q.instansi', 'LIKE', $suka)
                    ->orWhere('q.pemohon', 'LIKE', $suka)
                    ->orWhere('q.approver', 'LIKE', $suka)
                    ->orWhere('q.jenis', 'LIKE', $suka);
            });
        }

        return $query;
    }

    /**
     * @param  array<string,mixed>  $opsi
     * @return array{0:string,1:string}
     */
    private function urutanAntrian(array $opsi): array
    {
        $kunci = (string) ($opsi['urut'] ?? 'menunggu');
        $kolom = self::URUT_ANTRIAN[$kunci] ?? self::URUT_ANTRIAN['menunggu'];
        $arah = strtolower((string) ($opsi['arah'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return [$kolom, $arah];
    }

    /**
     * Menempelkan identitas ke baris antrian.
     *
     * @param  array<int, array<string, mixed>>  $daftar
     * @param  array<string, array<string, string>>  $meta
     * @param  array<string, string>  $approver
     */
    public static function gabungIdentitas(array $daftar, array $meta, array $approver): array
    {
        return array_map(function (array $baris) use ($meta, $approver) {
            $no = (string) ($baris['no_transaksi'] ?? '');
            $identitas = $meta[$no] ?? [];

            return array_merge($baris, [
                'jenis' => $identitas['jenis'] ?? '-',
                'judul' => $identitas['judul'] ?? '-',
                'pemohon' => $identitas['pemohon'] ?? '-',
                'approver' => $approver[$no] ?? '-',
            ]);
        }, $daftar);
    }

    /**
     * Melengkapi baris antrian yang ditampilkan dengan identitas.
     *
     * Hanya baris yang benar-benar tampil yang dilengkapi, sehingga whereIn
     * di sini tidak pernah melebihi BATAS_DAFTAR nilai.
     *
     * @param  array<int, array<string, mixed>>  $daftar
     */
    public function lengkapiAntrian(array $daftar): array
    {
        $nomor = array_values(array_filter(array_column($daftar, 'no_transaksi')));

        if ($nomor === []) {
            return $daftar;
        }

        $meta = [];

        DB::table(self::T_H.' as h')
            ->join(self::T_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
            ->whereIn('h.No_Transaksi', $nomor)
            ->select('h.No_Transaksi', 'd.Nama_Lengkap_Tamu as judul', DB::raw("ISNULL(k.Nama, '-') as pemohon"))
            ->get()
            ->each(function ($row) use (&$meta) {
                $meta[$row->No_Transaksi] = [
                    'jenis' => 'TAMU',
                    'judul' => (string) ($row->judul ?? '-'),
                    'pemohon' => (string) $row->pemohon,
                ];
            });

        DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_Pemohon')
            ->whereIn('h.No_Transaksi', $nomor)
            ->select(
                'h.No_Transaksi',
                DB::raw($this->permitQuery->platNomorSql('d').' as judul'),
                DB::raw("ISNULL(k.Nama, '-') as pemohon"),
            )
            ->get()
            ->each(function ($row) use (&$meta) {
                $meta[$row->No_Transaksi] = [
                    'jenis' => 'PERMIT',
                    'judul' => (string) ($row->judul ?: '-'),
                    'pemohon' => (string) $row->pemohon,
                ];
            });

        $approver = DB::table('HRIS_Approval_Request as ar')
            ->leftJoin('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ar.Group_Reference_Id')
            ->leftJoin('Karyawan as ka', 'ka.Kode_Karyawan', '=', 'ar.Approver_Karyawan_Kode')
            ->whereIn('ar.No_Transaksi', $nomor)
            ->whereNull('ar.Flag_Approval')
            ->select('ar.No_Transaksi', DB::raw("ISNULL(rf.Role_Name, ISNULL(ka.Nama, '-')) as nama"))
            ->get()
            ->groupBy('No_Transaksi')
            ->map(fn ($rows) => (string) $rows->first()->nama)
            ->all();

        return self::gabungIdentitas($daftar, $meta, $approver);
    }

    /**
     * @param  string|null  $domain  'tamu' | 'permit' | null (kedua domain)
     */
    public function ringkasan(?CarbonInterface $now = null, ?string $domain = null): array
    {
        $now = $now ? $now->copy() : Carbon::now();

        $queue = $this->queryAntrianApproval($domain);
        $stats = DB::query()
            ->fromSub($queue, 'a')
            ->selectRaw('SUM(CASE WHEN a.ada_pending = 1 THEN 1 ELSE 0 END) as pending')
            ->selectRaw('SUM(CASE WHEN a.ada_pending = 0 AND a.ada_kadaluarsa = 1 THEN 1 ELSE 0 END) as kadaluarsa')
            ->selectRaw(
                'SUM(CASE WHEN a.ada_pending = 1 AND DATEDIFF(minute, a.masuk_antrian, ?) < 60 THEN 1 ELSE 0 END) as kurang_1_jam',
                [$now->toDateTimeString()],
            )
            ->selectRaw(
                'SUM(CASE WHEN a.ada_pending = 1 AND DATEDIFF(minute, a.masuk_antrian, ?) >= 60 AND DATEDIFF(minute, a.masuk_antrian, ?) < 240 THEN 1 ELSE 0 END) as satu_sampai_empat_jam',
                [$now->toDateTimeString(), $now->toDateTimeString()],
            )
            ->selectRaw(
                'SUM(CASE WHEN a.ada_pending = 1 AND DATEDIFF(minute, a.masuk_antrian, ?) >= 240 THEN 1 ELSE 0 END) as lebih_4_jam',
                [$now->toDateTimeString()],
            )
            ->first();

        $page = $this->antrianBerhalaman([
            'page' => 1,
            'limit' => self::BATAS_DAFTAR,
            'urut' => 'menunggu',
            'arah' => 'asc',
        ], $domain);

        $antrian = [
            'pending' => (int) ($stats->pending ?? 0),
            'umur' => [
                'kurang_1_jam' => (int) ($stats->kurang_1_jam ?? 0),
                '1_sampai_4_jam' => (int) ($stats->satu_sampai_empat_jam ?? 0),
                'lebih_4_jam' => (int) ($stats->lebih_4_jam ?? 0),
            ],
            'kadaluarsa' => (int) ($stats->kadaluarsa ?? 0),
            'daftar' => $page['baris'],
            'daftar_total' => (int) $page['paginasi']['total'],
        ];

        // "Di dalam area" dan "terjadwal belum datang" adalah konsep TAMU; tidak
        // ada padanannya di permit. Pada ranah permit keduanya tidak dihitung
        // sama sekali — bukan sekadar disembunyikan saat render.
        if ($domain === 'permit') {
            return ['antrian' => $antrian];
        }

        $terjadwal = $this->queryTerjadwalBelumDatang()->get();

        return [
            'di_dalam_area' => self::agregasiDiDalamArea($this->queryDiDalamArea()->get(), $now),
            'terjadwal_belum_datang' => [
                'transaksi' => $terjadwal->count(),
                'orang' => (int) $terjadwal->sum(fn ($r) => max((int) ($r->Jumlah_Tamu ?? 1), 1)),
            ],
            'antrian' => $antrian,
        ];
    }

    /**
     * @param  iterable<object>  $rows  satu baris per transaksi tamu yang sedang di dalam area
     */
    public static function agregasiDiDalamArea(iterable $rows, CarbonInterface $now): array
    {
        $hariIni = $now->copy()->startOfDay();
        $orang = 0;
        $transaksi = 0;
        $overdueOrang = 0;
        $overdue = [];

        foreach ($rows as $row) {
            $jumlah = max((int) ($row->Jumlah_Tamu ?? 1), 1);
            $orang += $jumlah;
            $transaksi++;

            if (! self::sebelum($row->Tanggal_Kunjungan ?? null, $hariIni)) {
                continue;
            }

            $overdueOrang += $jumlah;
            $masuk = self::waktu($row->Waktu_Aktual_Masuk ?? null);
            $overdue[] = [
                'no_transaksi' => (string) ($row->No_Transaksi ?? ''),
                'nama' => (string) ($row->Nama_Tamu ?? '-'),
                'instansi' => (string) ($row->Instansi ?? '-'),
                'pic' => (string) ($row->Nama_PIC ?? '-'),
                'jumlah_tamu' => $jumlah,
                'masuk_pada' => $masuk?->toIso8601String(),
                'lama_menit' => $masuk ? $masuk->diffInMinutes($now) : null,
            ];
        }

        usort($overdue, fn ($a, $b) => ($b['lama_menit'] ?? -1) <=> ($a['lama_menit'] ?? -1));

        return [
            'orang' => $orang,
            'transaksi' => $transaksi,
            'overdue' => [
                'transaksi' => count($overdue),
                'orang' => $overdueOrang,
                'tertua_menit' => $overdue[0]['lama_menit'] ?? null,
            ],
            'daftar_perlu_ditutup' => array_slice($overdue, 0, self::BATAS_DAFTAR),
            'daftar_perlu_ditutup_total' => count($overdue),
        ];
    }

    /**
     * @param  iterable<object>  $rows  satu baris per transaksi yang belum diputus
     */
    public static function agregasiAntrian(iterable $rows, CarbonInterface $now): array
    {
        $umur = ['kurang_1_jam' => 0, '1_sampai_4_jam' => 0, 'lebih_4_jam' => 0];
        $kadaluarsa = 0;
        $pending = [];

        foreach ($rows as $row) {
            if ((int) ($row->ada_pending ?? 0) !== 1) {
                if ((int) ($row->ada_kadaluarsa ?? 0) === 1) {
                    $kadaluarsa++;
                }

                continue;
            }

            $sejak = self::waktu($row->masuk_antrian ?? null);
            $menit = $sejak ? $sejak->diffInMinutes($now) : 0;

            $ember = $menit < 60 ? 'kurang_1_jam' : ($menit < 240 ? '1_sampai_4_jam' : 'lebih_4_jam');
            $umur[$ember]++;

            $pending[] = [
                'no_transaksi' => (string) ($row->No_Transaksi ?? ''),
                'sejak' => $sejak?->toIso8601String(),
                'umur_menit' => $menit,
            ];
        }

        usort($pending, fn ($a, $b) => $b['umur_menit'] <=> $a['umur_menit']);

        return [
            'pending' => count($pending),
            'umur' => $umur,
            'kadaluarsa' => $kadaluarsa,
            'daftar' => array_slice($pending, 0, self::BATAS_DAFTAR),
            'daftar_total' => count($pending),
        ];
    }

    private static function waktu(mixed $nilai): ?Carbon
    {
        if (! $nilai) {
            return null;
        }

        try {
            return Carbon::parse((string) $nilai);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function sebelum(mixed $nilai, CarbonInterface $batas): bool
    {
        $waktu = self::waktu($nilai);

        return $waktu !== null && $waktu->startOfDay()->lt($batas);
    }
}
