<?php

namespace App\Services\ESS\Attendance\Monitoring;

use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tab "Rekap Periode": rekap per karyawan, per kelompok, dan rincian harian.
 *
 * Seluruh angka satu kueri GROUP BY atas basis yang sama dengan tab Hari Ini;
 * jumlah kueri tetap, tak bergantung jumlah karyawan. Periode yang sudah tutup
 * dan bersih (tanpa baris menunggu hitung ulang) di-cache 30 menit.
 */
class RekapPeriodeService
{
    /** Kunci kolom => ekspresi agregat. Urutan = urutan kolom di layar/ekspor. */
    private const AGREGAT = [
        'terjadwal' => "SUM(CASE WHEN s.StatusHari = 'WORK' THEN 1 ELSE 0 END)",
        'hadir' => "SUM(CASE WHEN s.StatusHari = 'WORK' AND s.CheckIn IS NOT NULL THEN 1 ELSE 0 END)",
        'hadir_terlambat' => "SUM(CASE WHEN s.StatusHari = 'WORK' AND s.CheckIn IS NOT NULL AND ISNULL(s.RealLate, 0) > 0 THEN 1 ELSE 0 END)",
        'terlambat_kali' => 'SUM(CASE WHEN ISNULL(s.RealLate, 0) > 0 THEN 1 ELSE 0 END)',
        'terlambat_menit' => 'SUM(CASE WHEN ISNULL(s.RealLate, 0) > 0 THEN s.RealLate ELSE 0 END)',
        'terlambat_berizin' => "SUM(CASE WHEN ISNULL(s.RealLate, 0) > 0 AND (s.Status_Kehadiran = 'IZIN TERLAMBAT' OR :ada_terlambat) THEN 1 ELSE 0 END)",
        'cepat_kali' => 'SUM(CASE WHEN ISNULL(s.RealEarly, 0) > 0 THEN 1 ELSE 0 END)',
        'cepat_menit' => 'SUM(CASE WHEN ISNULL(s.RealEarly, 0) > 0 THEN s.RealEarly ELSE 0 END)',
        'cepat_berizin' => "SUM(CASE WHEN ISNULL(s.RealEarly, 0) > 0 AND (s.Status_Kehadiran = 'IZIN PULANG CEPAT' OR :ada_cepat) THEN 1 ELSE 0 END)",
        'sakit' => 'SUM(CASE WHEN :ada_sakit THEN 1 ELSE 0 END)',
        'izin' => 'SUM(CASE WHEN :ada_izin THEN 1 ELSE 0 END)',
        'cuti' => 'SUM(CASE WHEN :ada_cuti THEN 1 ELSE 0 END)',
        // Snapshot sudah menandai alpha untuk hari ini dan tanggal depan (production
        // 14 Okt 2026: 1.013 alpha pada 15 Okt); alpha baru final setelah harinya lewat.
        'alpha' => "SUM(CASE WHEN s.Tanggal < :hari_ini AND s.Flag_Alpha = 'Y' THEN 1 ELSE 0 END)",
        'lupa_checkout' => 'SUM(CASE WHEN s.Tanggal < :hari_ini AND s.CheckIn IS NOT NULL AND s.CheckOut IS NULL THEN 1 ELSE 0 END)',
        'lembur_jam' => 'SUM(ISNULL(s.Koefisien_OT, 0))',
        'lembur_hari' => "SUM(CASE WHEN s.Flag_Lembur = 'Y' OR ISNULL(s.Koefisien_OT, 0) > 0 THEN 1 ELSE 0 END)",
        'konversi' => "SUM(CASE WHEN s.Flag_Konversi_Lembur = 'Y' THEN 1 ELSE 0 END)",
        'diperbarui' => "SUM(CASE WHEN s.Flag_Generate_Ulang = 'Y' THEN 1 ELSE 0 END)",
    ];

    public const URUT = ['nama', 'terjadwal', 'hadir', 'terlambat_kali', 'terlambat_menit', 'cepat_kali', 'cepat_menit',
        'sakit', 'izin', 'cuti', 'alpha', 'lupa_checkout', 'lembur_jam', 'lembur_hari', 'konversi'];

    public const DIMENSI = [
        'divisi' => 'vd.nama_divisi',
        'departemen' => 'vd.nama_sub_divisi',
        'lokasi' => 'k.Cabang_Untuk_Gaji',
    ];

    private const CACHE_DETIK = 1800;

    /**
     * @param  bool  $semua  true = tanpa paginasi (ekspor)
     * @return array{items: array, total: int, page: int, limit: int, diperbarui: int}
     */
    public function rekapKaryawan(string $dari, string $sampai, array $filter, FunctionalRole $role, bool $semua = false): array
    {
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);

        return $this->ingat('karyawan', [$dari, $sampai, $f, $semua], $dari, $sampai, $f, $role, function () use ($dari, $sampai, $f, $role, $semua) {
            $grup = $this->agregat(MonitoringKehadiranQuery::basis($dari, self::sampaiHariIni($sampai), $f, $role))
                ->addSelect('s.Kode_Karyawan')
                ->selectRaw('MAX(k.Nama) as Nama, MAX(vd.nama_divisi) as Divisi, MAX(vd.nama_sub_divisi) as Departemen')
                ->selectRaw('MAX(lvl.nama_jabatan) as Jabatan, MAX(k.Cabang_Untuk_Gaji) as Lokasi')
                ->groupBy('s.Kode_Karyawan');

            $q = DB::query()->fromSub($grup, 'r');
            $total = (clone $q)->count();
            $diperbarui = (int) (clone $q)->sum('r.diperbarui');

            // Nama bawaannya A-Z; kolom angka bawaannya terbesar dulu.
            $arah = isset($filter['arah']) ? $f['arah'] : ($f['urut'] === 'nama' ? 'asc' : 'desc');
            if ($f['urut'] !== 'nama') {
                $q->orderBy('r.'.$f['urut'], $arah);
            }
            $q->orderBy('r.Nama', $f['urut'] === 'nama' ? $arah : 'asc')->orderBy('r.Kode_Karyawan');

            if (! $semua) {
                $q->forPage($f['page'], $f['limit']);
            }

            return [
                'items' => $q->get()->map(fn ($r) => $this->bentukKaryawan($r))->values()->all(),
                'total' => $total,
                'page' => $semua ? 1 : $f['page'],
                'limit' => $semua ? $total : $f['limit'],
                'diperbarui' => $diperbarui,
            ];
        });
    }

    /** @return list<array<string, mixed>> */
    public function rekapKelompok(string $dari, string $sampai, string $dimensi, array $filter, FunctionalRole $role): array
    {
        $kolom = self::DIMENSI[$dimensi] ?? self::DIMENSI['divisi'];
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);

        return $this->ingat('kelompok', [$dari, $sampai, $dimensi, $f], $dari, $sampai, $f, $role, function () use ($dari, $sampai, $kolom, $f, $role) {
            $grup = $this->agregat(MonitoringKehadiranQuery::basis($dari, self::sampaiHariIni($sampai), $f, $role))
                ->selectRaw("ISNULL(NULLIF(LTRIM(RTRIM({$kolom})), ''), '(Tanpa data)') as kelompok")
                ->selectRaw('COUNT(DISTINCT s.Kode_Karyawan) as karyawan')
                ->groupByRaw("ISNULL(NULLIF(LTRIM(RTRIM({$kolom})), ''), '(Tanpa data)')");

            return $grup->get()->map(function ($r) {
                $terjadwal = (int) $r->terjadwal;

                return [
                    'kelompok' => (string) $r->kelompok,
                    'karyawan' => (int) $r->karyawan,
                    'terjadwal' => $terjadwal,
                    'hadir' => (int) $r->hadir,
                    'persen_hadir' => $terjadwal > 0 ? round(((int) $r->hadir) * 100 / $terjadwal, 1) : null,
                    'terlambat_kali' => (int) $r->terlambat_kali,
                    'terlambat_menit' => (int) $r->terlambat_menit,
                    'alpha' => (int) $r->alpha,
                    'lembur_jam' => (int) $r->lembur_jam,
                ];
            })->sortBy('kelompok', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        });
    }

    /**
     * Data grafik tab Rekap: tren per tanggal, sel heatmap per (unit, tanggal),
     * dan rekap per unit untuk peringkat. Basis & agregat sama dengan rekap
     * tabel, jadi angka grafik selalu sama dengan tabelnya.
     *
     * @return array{tren: list<array<string, mixed>>, sel: list<array<string, mixed>>, kelompok: list<array<string, mixed>>}
     */
    public function grafik(string $dari, string $sampai, string $dimensi, array $filter, FunctionalRole $role): array
    {
        $dimensi = array_key_exists($dimensi, self::DIMENSI) ? $dimensi : 'divisi';
        $kolom = self::DIMENSI[$dimensi];
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);
        $kelompokSql = "ISNULL(NULLIF(LTRIM(RTRIM({$kolom})), ''), '(Tanpa data)')";
        $tanggal = fn ($v) => Carbon::parse($v)->toDateString();

        $hasil = $this->ingat('grafik', [$dari, $sampai, $dimensi, $f], $dari, $sampai, $f, $role, function () use ($dari, $sampai, $f, $role, $kelompokSql, $tanggal) {
            $basis = fn () => $this->agregat(MonitoringKehadiranQuery::basis($dari, self::sampaiHariIni($sampai), $f, $role));

            $tren = $basis()->addSelect('s.Tanggal')->groupBy('s.Tanggal')->orderBy('s.Tanggal')->get()
                ->map(fn ($r) => [
                    'tanggal' => $tanggal($r->Tanggal),
                    'terjadwal' => (int) $r->terjadwal,
                    'hadir' => (int) $r->hadir,
                    'hadir_terlambat' => (int) $r->hadir_terlambat,
                    'sakit' => (int) $r->sakit,
                    'izin' => (int) $r->izin,
                    'cuti' => (int) $r->cuti,
                    'alpha' => (int) $r->alpha,
                ])->values()->all();

            $sel = $basis()->selectRaw("{$kelompokSql} as kelompok")->addSelect('s.Tanggal')
                ->groupByRaw("{$kelompokSql}, s.Tanggal")->get()
                ->map(fn ($r) => [
                    'kelompok' => (string) $r->kelompok,
                    'tanggal' => $tanggal($r->Tanggal),
                    'terjadwal' => (int) $r->terjadwal,
                    'hadir' => (int) $r->hadir,
                    'terlambat_kali' => (int) $r->terlambat_kali,
                    'alpha' => (int) $r->alpha,
                ])->values()->all();

            return ['tren' => $tren, 'sel' => $sel];
        });

        return $hasil + [
            'diperbarui' => $this->barisKotor($dari, $sampai, $filter, $role),
            'kelompok' => $this->rekapKelompok($dari, $sampai, $dimensi, $filter, $role),
            'hierarki' => $this->hierarki($dari, $sampai, $filter, $role),
        ];
    }

    /**
     * Pohon hierarki organisasi (Lokasi → Divisi → Departemen → Karyawan) dengan
     * metrik agregat di setiap tingkat, untuk visualisasi drill-down interaktif.
     *
     * @return array<string, mixed>
     */
    public function hierarki(string $dari, string $sampai, array $filter, FunctionalRole $role): array
    {
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);

        return $this->ingat('hierarki', [$dari, $sampai, $f], $dari, $sampai, $f, $role, function () use ($dari, $sampai, $filter, $role) {
            $items = $this->rekapKaryawan($dari, $sampai, $filter, $role, semua: true)['items'];

            return $this->susunPohonHierarki($items);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function susunPohonHierarki(array $items): array
    {
        $pohon = [
            'id' => 'root',
            'name' => 'Semua Lokasi',
            'tingkat' => 'root',
            'karyawan_count' => 0,
            'terjadwal' => 0,
            'hadir' => 0,
            'terlambat_kali' => 0,
            'terlambat_menit' => 0,
            'alpha' => 0,
            'lembur_jam' => 0,
            'children' => [],
        ];

        $lokMap = [];
        foreach ($items as $k) {
            $lok = $k['lokasi'] ?: '(Tanpa Lokasi)';
            $div = $k['divisi'] ?: '(Tanpa Divisi)';
            $dep = $k['departemen'] ?: '(Tanpa Departemen)';

            if (! isset($lokMap[$lok])) {
                $lokMap[$lok] = [
                    'id' => "lokasi:{$lok}",
                    'name' => $lok,
                    'tingkat' => 'lokasi',
                    'lokasi' => $lok,
                    'karyawan_count' => 0,
                    'terjadwal' => 0,
                    'hadir' => 0,
                    'terlambat_kali' => 0,
                    'terlambat_menit' => 0,
                    'alpha' => 0,
                    'lembur_jam' => 0,
                    'divisiMap' => [],
                ];
            }

            if (! isset($lokMap[$lok]['divisiMap'][$div])) {
                $lokMap[$lok]['divisiMap'][$div] = [
                    'id' => "divisi:{$lok}:{$div}",
                    'name' => $div,
                    'tingkat' => 'divisi',
                    'lokasi' => $lok,
                    'divisi' => $div,
                    'karyawan_count' => 0,
                    'terjadwal' => 0,
                    'hadir' => 0,
                    'terlambat_kali' => 0,
                    'terlambat_menit' => 0,
                    'alpha' => 0,
                    'lembur_jam' => 0,
                    'depMap' => [],
                ];
            }

            if (! isset($lokMap[$lok]['divisiMap'][$div]['depMap'][$dep])) {
                $lokMap[$lok]['divisiMap'][$div]['depMap'][$dep] = [
                    'id' => "departemen:{$lok}:{$div}:{$dep}",
                    'name' => $dep,
                    'tingkat' => 'departemen',
                    'lokasi' => $lok,
                    'divisi' => $div,
                    'departemen' => $dep,
                    'karyawan_count' => 0,
                    'terjadwal' => 0,
                    'hadir' => 0,
                    'terlambat_kali' => 0,
                    'terlambat_menit' => 0,
                    'alpha' => 0,
                    'lembur_jam' => 0,
                    'karyawan' => [],
                ];
            }

            $lokMap[$lok]['divisiMap'][$div]['depMap'][$dep]['karyawan'][] = [
                'kode' => $k['kode'],
                'nama' => $k['nama'],
                'jabatan' => $k['jabatan'],
                'terjadwal' => (int) ($k['terjadwal'] ?? 0),
                'hadir' => (int) ($k['hadir'] ?? 0),
                'terlambat_kali' => (int) ($k['terlambat_kali'] ?? 0),
                'terlambat_menit' => (int) ($k['terlambat_menit'] ?? 0),
                'alpha' => (int) ($k['alpha'] ?? 0),
                'lembur_jam' => (int) ($k['lembur_jam'] ?? 0),
            ];

            $kelompokList = [
                &$pohon,
                &$lokMap[$lok],
                &$lokMap[$lok]['divisiMap'][$div],
                &$lokMap[$lok]['divisiMap'][$div]['depMap'][$dep],
            ];

            foreach ($kelompokList as &$node) {
                $node['karyawan_count'] += 1;
                $node['terjadwal'] += (int) ($k['terjadwal'] ?? 0);
                $node['hadir'] += (int) ($k['hadir'] ?? 0);
                $node['terlambat_kali'] += (int) ($k['terlambat_kali'] ?? 0);
                $node['terlambat_menit'] += (int) ($k['terlambat_menit'] ?? 0);
                $node['alpha'] += (int) ($k['alpha'] ?? 0);
                $node['lembur_jam'] += (int) ($k['lembur_jam'] ?? 0);
            }
            unset($node);
        }

        $hitungPersen = function (&$node) {
            $node['persen_hadir'] = $node['terjadwal'] > 0
                ? round(($node['hadir'] * 100) / $node['terjadwal'], 1)
                : 0;
        };

        $hitungPersen($pohon);

        foreach ($lokMap as &$lokNode) {
            $hitungPersen($lokNode);
            $divChildren = [];
            foreach ($lokNode['divisiMap'] as &$divNode) {
                $hitungPersen($divNode);
                $depChildren = [];
                foreach ($divNode['depMap'] as &$depNode) {
                    $hitungPersen($depNode);
                    usort($depNode['karyawan'], fn ($a, $b) => strcmp($a['nama'], $b['nama']));
                    $depChildren[] = $depNode;
                }
                unset($depNode);
                unset($divNode['depMap']);
                usort($depChildren, fn ($a, $b) => strcmp($a['name'], $b['name']));
                $divNode['children'] = $depChildren;
                $divChildren[] = $divNode;
            }
            unset($divNode);
            unset($lokNode['divisiMap']);
            usort($divChildren, fn ($a, $b) => strcmp($a['name'], $b['name']));
            $lokNode['children'] = $divChildren;
            $pohon['children'][] = $lokNode;
        }
        unset($lokNode);
        usort($pohon['children'], fn ($a, $b) => strcmp($a['name'], $b['name']));

        return $pohon;
    }

    /**
     * Rincian harian satu karyawan; null bila karyawan di luar cakupan admin
     * atau tak punya baris snapshot pada periode itu.
     *
     * @return array{karyawan: array, hari: list<array<string, mixed>>}|null
     */
    public function rincian(string $kode, string $dari, string $sampai, FunctionalRole $role, ?Carbon $sekarang = null): ?array
    {
        $sekarang ??= now();
        $hariIni = $sekarang->toDateString();

        $rows = MonitoringKehadiranQuery::basis($dari, min($sampai, $hariIni), [], $role)
            ->where('s.Kode_Karyawan', $kode)
            ->select([
                's.Kode_Karyawan', 's.Tanggal', 'k.Nama', 'vd.nama_divisi as Divisi', 'vd.nama_sub_divisi as Departemen',
                'lvl.nama_jabatan as Jabatan', 'k.Cabang_Untuk_Gaji as Lokasi',
                's.StatusHari', 's.Nama_Shift', 's.Jam_Masuk', 's.Jam_Keluar', 's.CheckIn', 's.CheckOut',
                's.RealLate', 's.RealEarly', 's.Koefisien_OT', 's.Flag_Alpha', 's.Flag_Konversi_Lembur',
                's.Flag_Generate_Ulang', 's.Status_Kehadiran',
                's.No_Transaksi_Cuti', 's.No_Transaksi_Izin', 's.No_Transaksi_Sakit', 's.No_Transaksi_Lembur',
                's.No_Transaksi_Terlambat', 's.No_Transaksi_PulangCepat',
            ])
            ->orderBy('s.Tanggal')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $pertama = $rows->first();
        $teks = fn ($v) => ($v = trim((string) $v)) !== '' ? $v : null;
        $jam = fn ($v) => $v ? Carbon::parse($v)->format('H:i') : null;
        $no = fn ($v) => KategoriKehadiran::ada($v) ? trim((string) $v) : null;

        return [
            'karyawan' => [
                'kode' => (string) $pertama->Kode_Karyawan,
                'nama' => $teks($pertama->Nama) ?? (string) $pertama->Kode_Karyawan,
                'divisi' => $teks($pertama->Divisi),
                'departemen' => $teks($pertama->Departemen),
                'jabatan' => $teks($pertama->Jabatan),
                'lokasi' => $teks($pertama->Lokasi),
            ],
            'hari' => $rows->map(function ($r) use ($teks, $jam, $no, $hariIni, $sekarang) {
                $tanggal = Carbon::parse($r->Tanggal)->toDateString();
                $kategori = KategoriKehadiran::tentukan((array) $r, KategoriKehadiran::jamAcuan($tanggal, $sekarang));

                return [
                    'tanggal' => $tanggal,
                    'kategori' => $kategori,
                    'status_hari' => $teks($r->StatusHari),
                    'shift' => $teks($r->Nama_Shift),
                    'jam_masuk' => MonitoringHariIniService::jamPendek($r->Jam_Masuk),
                    'jam_keluar' => MonitoringHariIniService::jamPendek($r->Jam_Keluar),
                    'check_in' => $jam($r->CheckIn),
                    'check_out' => $jam($r->CheckOut),
                    'terlambat_menit' => (int) ($r->RealLate ?? 0),
                    'pulang_cepat_menit' => (int) ($r->RealEarly ?? 0),
                    'lembur_jam' => (int) ($r->Koefisien_OT ?? 0),
                    'alpha' => $tanggal < $hariIni && ($r->Flag_Alpha ?? '') === 'Y',
                    'konversi' => ($r->Flag_Konversi_Lembur ?? '') === 'Y',
                    'diperbarui' => ($r->Flag_Generate_Ulang ?? '') === 'Y',
                    'tanpa_checkout' => $tanggal < $hariIni && $r->CheckIn && ! $r->CheckOut,
                    'status_kehadiran' => $teks($r->Status_Kehadiran),
                    'transaksi' => array_filter([
                        'cuti' => $no($r->No_Transaksi_Cuti),
                        'izin' => $no($r->No_Transaksi_Izin),
                        'sakit' => $no($r->No_Transaksi_Sakit),
                        'lembur' => $no($r->No_Transaksi_Lembur),
                        'terlambat' => $no($r->No_Transaksi_Terlambat),
                        'pulang_cepat' => $no($r->No_Transaksi_PulangCepat),
                    ]),
                ];
            })->values()->all(),
        ];
    }

    /** Jumlah baris yang menunggu hitung ulang pada rentang & saringan ini. */
    public function barisKotor(string $dari, string $sampai, array $f, FunctionalRole $role): int
    {
        return MonitoringKehadiranQuery::basis($dari, self::sampaiHariIni($sampai), $f, $role)
            ->where('s.Flag_Generate_Ulang', 'Y')
            ->count();
    }

    /**
     * Periode berjalan dihitung sampai hari ini saja: tanggal depan sudah punya
     * baris snapshot (jadwal) tetapi belum punya kehadiran, sehingga "terjadwal"
     * dan "% hadir" akan terbaca seolah karyawan tidak masuk.
     */
    public static function sampaiHariIni(string $sampai): string
    {
        return min($sampai, now()->toDateString());
    }

    private function agregat(Builder $q): Builder
    {
        $ganti = [
            ':ada_terlambat' => MonitoringKehadiranQuery::adaSql('s.No_Transaksi_Terlambat'),
            ':ada_cepat' => MonitoringKehadiranQuery::adaSql('s.No_Transaksi_PulangCepat'),
            ':ada_sakit' => MonitoringKehadiranQuery::adaSql('s.No_Transaksi_Sakit'),
            ':ada_izin' => MonitoringKehadiranQuery::adaSql('s.No_Transaksi_Izin'),
            ':ada_cuti' => MonitoringKehadiranQuery::adaSql('s.No_Transaksi_Cuti'),
        ];

        foreach (self::AGREGAT as $alias => $ekspresi) {
            $sql = strtr($ekspresi, $ganti);
            str_contains($sql, ':hari_ini')
                ? $q->selectRaw(str_replace(':hari_ini', '?', $sql)." as {$alias}", array_fill(0, substr_count($sql, ':hari_ini'), now()->toDateString()))
                : $q->selectRaw("{$sql} as {$alias}");
        }

        return $q;
    }

    /**
     * Cache hanya untuk periode yang sudah tutup dan bersih; kunci memuat
     * pengguna karena cakupan admin berbeda per orang.
     */
    private function ingat(string $jenis, array $kunci, string $dari, string $sampai, array $f, FunctionalRole $role, \Closure $hitung): array
    {
        if ($sampai >= now()->toDateString() || $this->barisKotor($dari, $sampai, $f, $role) > 0) {
            return $hitung();
        }

        $kunciCache = 'ess:monitoring-kehadiran:'.$jenis.':'.$role->currentUserId().':'.md5(json_encode($kunci));

        return Cache::remember($kunciCache, self::CACHE_DETIK, $hitung);
    }

    /** @return array<string, mixed> */
    private function bentukKaryawan(object $r): array
    {
        $teks = fn ($v) => ($v = trim((string) $v)) !== '' ? $v : null;
        $hasil = [
            'kode' => (string) $r->Kode_Karyawan,
            'nama' => $teks($r->Nama) ?? (string) $r->Kode_Karyawan,
            'divisi' => $teks($r->Divisi),
            'departemen' => $teks($r->Departemen),
            'jabatan' => $teks($r->Jabatan),
            'lokasi' => $teks($r->Lokasi),
        ];

        foreach (array_keys(self::AGREGAT) as $kolom) {
            $hasil[$kolom] = (int) $r->{$kolom};
        }

        return $hasil;
    }
}
