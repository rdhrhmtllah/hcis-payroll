<?php

namespace App\Services\ESS\Attendance\Monitoring;

use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tab "Hari Ini": kartu per kategori + daftar karyawan satu tanggal.
 *
 * Kartu dan daftar sama-sama menyaring kolom `kategori` hasil
 * KategoriKehadiran::sqlCase() di subkueri yang sama, jadi angka kartu selalu
 * sama dengan jumlah baris daftar bila kartu itu diklik.
 */
class MonitoringHariIniService
{
    /** Tanda tambahan (tidak eksklusif) yang bisa dipilih sebagai saringan. */
    public const TANDA = ['pulang_cepat', 'lembur', 'diperbarui', 'tanpa_checkout'];

    public const URUT = ['kategori', 'nama', 'terlambat', 'check_in'];

    /**
     * Ringkasan untuk saringan yang sama KECUALI kategori & tanda, supaya angka
     * kartu menjawab "berapa yang muncul bila kartu ini dipilih".
     *
     * @return array{tanggal: string, total: int, kategori: array<string, int>, tanda: array<string, int>, data_per: ?string}
     */
    public function ringkasan(string $tanggal, array $filter, FunctionalRole $role, ?Carbon $sekarang = null): array
    {
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);
        $sub = $this->subkueri($tanggal, $f, $role, $sekarang ?? now());

        $baris = DB::query()->fromSub($sub, 'x')
            ->selectRaw('x.kategori, COUNT(*) as n')
            ->selectRaw('SUM(CASE WHEN x.tanda_pulang_cepat = 1 THEN 1 ELSE 0 END) as pulang_cepat')
            ->selectRaw('SUM(CASE WHEN x.tanda_lembur = 1 THEN 1 ELSE 0 END) as lembur')
            ->selectRaw('SUM(CASE WHEN x.tanda_diperbarui = 1 THEN 1 ELSE 0 END) as diperbarui')
            ->selectRaw('SUM(CASE WHEN x.tanda_tanpa_checkout = 1 THEN 1 ELSE 0 END) as tanpa_checkout')
            ->selectRaw('MAX(x.Last_Calculated_At) as data_per')
            ->groupBy('x.kategori')
            ->get();

        $kategori = array_fill_keys(KategoriKehadiran::URUTAN, 0);
        $tanda = array_fill_keys(self::TANDA, 0);
        $dataPer = null;

        foreach ($baris as $r) {
            $kategori[(string) $r->kategori] = (int) $r->n;
            foreach (self::TANDA as $t) {
                $tanda[$t] += (int) $r->{$t};
            }
            if ($r->data_per && ($dataPer === null || $r->data_per > $dataPer)) {
                $dataPer = (string) $r->data_per;
            }
        }

        return [
            'tanggal' => $tanggal,
            'total' => array_sum($kategori),
            'kategori' => $kategori,
            'tanda' => $tanda,
            'data_per' => $dataPer ? Carbon::parse($dataPer)->format('Y-m-d H:i') : null,
        ];
    }

    /**
     * Sebaran jam check-in per 15 menit + jam masuk shift terbanyak, untuk
     * saringan yang sama dengan kartu (tanpa kategori/tanda).
     *
     * @return array{sebaran: list<array{menit: int, jumlah: int}>, jam_masuk_umum: ?string}
     */
    public function grafik(string $tanggal, array $filter, FunctionalRole $role): array
    {
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);
        // Menit sejak tengah malam, dibulatkan ke bawah per 15 menit.
        $bucket = '((DATEPART(hour, s.CheckIn) * 60 + DATEPART(minute, s.CheckIn)) / 15) * 15';

        $sebaran = MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->whereNotNull('s.CheckIn')
            ->selectRaw("{$bucket} as menit, COUNT(*) as jumlah")
            ->groupByRaw($bucket)
            ->orderByRaw($bucket)
            ->get()
            ->map(fn ($r) => ['menit' => (int) $r->menit, 'jumlah' => (int) $r->jumlah])
            ->values()->all();

        $jamMasuk = MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->where('s.StatusHari', 'WORK')
            ->whereNotNull('s.Jam_Masuk')->where('s.Jam_Masuk', '<>', '')
            ->select('s.Jam_Masuk')->selectRaw('COUNT(*) as n')
            ->groupBy('s.Jam_Masuk')
            ->orderByDesc('n')->orderBy('s.Jam_Masuk')
            ->first();

        return ['sebaran' => $sebaran, 'jam_masuk_umum' => self::jamPendek($jamMasuk?->Jam_Masuk)];
    }

    /**
     * Data analitik lengkap hari ini:
     * - metrik agregat transaksi (sakit, izin, cuti tahunan, cuti khusus, lembur, konversi lembur)
     * - tren check-in jam ke jam (04:00 - 23:00)
     * - sebaran per lokasi / cabang
     * - stream feed aktivitas terbaru (check-in terbaru & transaksi diajukan terbaru)
     *
     * @return array<string, mixed>
     */
    public function analitik(string $tanggal, array $filter, FunctionalRole $role, ?Carbon $sekarang = null): array
    {
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);
        $ada = fn ($col) => MonitoringKehadiranQuery::adaSql($col);

        // 1. Agregat transaksi & pengajuan
        $tx = MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->selectRaw("SUM(CASE WHEN {$ada('s.No_Transaksi_Sakit')} THEN 1 ELSE 0 END) as sakit")
            ->selectRaw("SUM(CASE WHEN {$ada('s.No_Transaksi_Izin')} THEN 1 ELSE 0 END) as izin")
            ->selectRaw("SUM(CASE WHEN {$ada('s.No_Transaksi_Cuti')} AND (s.Jenis_Cuti IS NULL OR s.Jenis_Cuti NOT LIKE '%Khusus%') THEN 1 ELSE 0 END) as cuti_tahunan")
            ->selectRaw("SUM(CASE WHEN {$ada('s.No_Transaksi_Cuti')} AND s.Jenis_Cuti LIKE '%Khusus%' THEN 1 ELSE 0 END) as cuti_khusus")
            ->selectRaw("SUM(CASE WHEN s.Flag_Lembur = 'Y' OR {$ada('s.No_Transaksi_Lembur')} OR ISNULL(s.Koefisien_OT, 0) > 0 THEN 1 ELSE 0 END) as lembur")
            ->selectRaw("SUM(CASE WHEN s.Flag_Konversi_Lembur = 'Y' THEN 1 ELSE 0 END) as konversi_lembur")
            ->selectRaw('SUM(CASE WHEN ISNULL(s.RealLate, 0) > 0 THEN 1 ELSE 0 END) as terlambat')
            ->selectRaw('SUM(CASE WHEN ISNULL(s.RealEarly, 0) > 0 THEN 1 ELSE 0 END) as pulang_cepat')
            ->selectRaw("SUM(CASE WHEN s.StatusHari = 'WORK' THEN 1 ELSE 0 END) as terjadwal")
            ->selectRaw('SUM(CASE WHEN s.CheckIn IS NOT NULL THEN 1 ELSE 0 END) as hadir')
            ->selectRaw("SUM(CASE WHEN s.StatusHari LIKE 'OFF%' THEN 1 ELSE 0 END) as libur")
            ->first();

        // 2. Tren check-in jam ke jam
        $hourlyRows = MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->whereNotNull('s.CheckIn')
            ->selectRaw('DATEPART(hour, s.CheckIn) as jam, COUNT(*) as jumlah')
            ->groupByRaw('DATEPART(hour, s.CheckIn)')
            ->orderByRaw('DATEPART(hour, s.CheckIn)')
            ->get()
            ->keyBy('jam');

        $trenJam = [];
        $kumulatif = 0;
        for ($h = 4; $h <= 23; $h++) {
            $jml = (int) ($hourlyRows->get($h)?->jumlah ?? 0);
            $kumulatif += $jml;
            $trenJam[] = [
                'jam' => sprintf('%02d:00', $h),
                'jam_angka' => $h,
                'jumlah' => $jml,
                'kumulatif' => $kumulatif,
            ];
        }

        // 3. Sebaran per lokasi
        $sebaranLokasi = MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->selectRaw("ISNULL(NULLIF(LTRIM(RTRIM(k.Cabang_Untuk_Gaji)), ''), '(Tanpa Lokasi)') as lokasi")
            ->selectRaw("SUM(CASE WHEN s.StatusHari = 'WORK' THEN 1 ELSE 0 END) as terjadwal")
            ->selectRaw('SUM(CASE WHEN s.CheckIn IS NOT NULL THEN 1 ELSE 0 END) as hadir')
            ->selectRaw("SUM(CASE WHEN s.StatusHari LIKE 'OFF%' THEN 1 ELSE 0 END) as libur")
            ->selectRaw("SUM(CASE WHEN {$ada('s.No_Transaksi_Cuti')} OR {$ada('s.No_Transaksi_Sakit')} OR {$ada('s.No_Transaksi_Izin')} THEN 1 ELSE 0 END) as izin_cuti_sakit")
            ->groupByRaw("ISNULL(NULLIF(LTRIM(RTRIM(k.Cabang_Untuk_Gaji)), ''), '(Tanpa Lokasi)')")
            ->get()
            ->map(fn ($r) => [
                'lokasi' => (string) $r->lokasi,
                'terjadwal' => (int) $r->terjadwal,
                'hadir' => (int) $r->hadir,
                'libur' => (int) $r->libur,
                'izin_cuti_sakit' => (int) $r->izin_cuti_sakit,
                'persen_hadir' => (int) $r->terjadwal > 0 ? round(((int) $r->hadir * 100) / (int) $r->terjadwal, 1) : 0,
            ])
            ->sortByDesc('terjadwal')
            ->values()
            ->all();

        // 4. Feed terbaru check-in (top 10)
        $terbaruAbsen = MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->whereNotNull('s.CheckIn')
            ->select([
                's.Kode_Karyawan', 'k.Nama', 'vd.nama_sub_divisi as Departemen',
                'k.Cabang_Untuk_Gaji as Lokasi', 's.Nama_Shift',
                's.Jam_Masuk', 's.CheckIn', 's.RealLate',
            ])
            ->orderByDesc('s.CheckIn')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'kode' => (string) $r->Kode_Karyawan,
                'nama' => (string) ($r->Nama ?: $r->Kode_Karyawan),
                'departemen' => (string) ($r->Departemen ?? ''),
                'lokasi' => (string) ($r->Lokasi ?? ''),
                'shift' => (string) ($r->Nama_Shift ?? '—'),
                'check_in' => Carbon::parse($r->CheckIn)->format('H:i'),
                'terlambat_menit' => (int) ($r->RealLate ?? 0),
            ])
            ->all();

        // 5. Feed terbaru pengajuan / transaksi (top 12)
        $transaksiRows = MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->where(function ($w) use ($ada) {
                $w->whereRaw($ada('s.No_Transaksi_Cuti'))
                    ->orWhereRaw($ada('s.No_Transaksi_Sakit'))
                    ->orWhereRaw($ada('s.No_Transaksi_Izin'))
                    ->orWhereRaw($ada('s.No_Transaksi_Lembur'))
                    ->orWhere('s.Flag_Lembur', 'Y')
                    ->orWhere('s.Flag_Konversi_Lembur', 'Y');
            })
            ->select([
                's.Kode_Karyawan', 'k.Nama', 'vd.nama_sub_divisi as Departemen',
                'k.Cabang_Untuk_Gaji as Lokasi',
                's.No_Transaksi_Cuti', 's.Jenis_Cuti', 's.Alasan_Cuti',
                's.No_Transaksi_Sakit', 's.Alasan_Sakit',
                's.No_Transaksi_Izin', 's.Alasan_Izin',
                's.No_Transaksi_Lembur', 's.Alasan_Lembur', 's.Koefisien_OT',
                's.Flag_Konversi_Lembur', 's.Jenis_Konversi_Lembur',
            ])
            ->limit(12)
            ->get();

        $daftarTransaksi = [];
        foreach ($transaksiRows as $r) {
            $nama = (string) ($r->Nama ?: $r->Kode_Karyawan);
            if ($r->No_Transaksi_Cuti && $r->No_Transaksi_Cuti !== '-') {
                $isKhusus = str_contains($r->Jenis_Cuti ?? '', 'Khusus');
                $daftarTransaksi[] = [
                    'kode' => (string) $r->Kode_Karyawan,
                    'nama' => $nama,
                    'departemen' => (string) ($r->Departemen ?? ''),
                    'lokasi' => (string) ($r->Lokasi ?? ''),
                    'jenis' => $isKhusus ? 'Cuti Khusus' : 'Cuti Tahunan',
                    'tipe_badge' => $isKhusus ? 'purple' : 'brand',
                    'nomor' => (string) $r->No_Transaksi_Cuti,
                    'keterangan' => $r->Jenis_Cuti ? ($r->Jenis_Cuti.($r->Alasan_Cuti ? " · {$r->Alasan_Cuti}" : '')) : ($r->Alasan_Cuti ?? '—'),
                ];
            }
            if ($r->No_Transaksi_Sakit && $r->No_Transaksi_Sakit !== '-') {
                $daftarTransaksi[] = [
                    'kode' => (string) $r->Kode_Karyawan,
                    'nama' => $nama,
                    'departemen' => (string) ($r->Departemen ?? ''),
                    'lokasi' => (string) ($r->Lokasi ?? ''),
                    'jenis' => 'Sakit',
                    'tipe_badge' => 'danger',
                    'nomor' => (string) $r->No_Transaksi_Sakit,
                    'keterangan' => (string) ($r->Alasan_Sakit ?? 'Surat dokter / sakit'),
                ];
            }
            if ($r->No_Transaksi_Izin && $r->No_Transaksi_Izin !== '-') {
                $daftarTransaksi[] = [
                    'kode' => (string) $r->Kode_Karyawan,
                    'nama' => $nama,
                    'departemen' => (string) ($r->Departemen ?? ''),
                    'lokasi' => (string) ($r->Lokasi ?? ''),
                    'jenis' => 'Izin',
                    'tipe_badge' => 'info',
                    'nomor' => (string) $r->No_Transaksi_Izin,
                    'keterangan' => (string) ($r->Alasan_Izin ?? 'Pengajuan izin'),
                ];
            }
            if (($r->No_Transaksi_Lembur && $r->No_Transaksi_Lembur !== '-') || (int) $r->Koefisien_OT > 0) {
                $daftarTransaksi[] = [
                    'kode' => (string) $r->Kode_Karyawan,
                    'nama' => $nama,
                    'departemen' => (string) ($r->Departemen ?? ''),
                    'lokasi' => (string) ($r->Lokasi ?? ''),
                    'jenis' => 'Lembur',
                    'tipe_badge' => 'warning',
                    'nomor' => (string) ($r->No_Transaksi_Lembur ?: 'Lembur Shift'),
                    'keterangan' => ($r->Koefisien_OT ? "{$r->Koefisien_OT} jam · " : '').($r->Alasan_Lembur ?? 'Lembur kerja'),
                ];
            }
            if ($r->Flag_Konversi_Lembur === 'Y') {
                $daftarTransaksi[] = [
                    'kode' => (string) $r->Kode_Karyawan,
                    'nama' => $nama,
                    'departemen' => (string) ($r->Departemen ?? ''),
                    'lokasi' => (string) ($r->Lokasi ?? ''),
                    'jenis' => 'Konversi Lembur',
                    'tipe_badge' => 'purple',
                    'nomor' => 'Konversi',
                    'keterangan' => (string) ($r->Jenis_Konversi_Lembur ?? 'Dikonversi ke saldo/off'),
                ];
            }
        }

        return [
            'transaksi' => [
                'sakit' => (int) ($tx->sakit ?? 0),
                'izin' => (int) ($tx->izin ?? 0),
                'cuti_tahunan' => (int) ($tx->cuti_tahunan ?? 0),
                'cuti_khusus' => (int) ($tx->cuti_khusus ?? 0),
                'lembur' => (int) ($tx->lembur ?? 0),
                'konversi_lembur' => (int) ($tx->konversi_lembur ?? 0),
                'terlambat' => (int) ($tx->terlambat ?? 0),
                'pulang_cepat' => (int) ($tx->pulang_cepat ?? 0),
                'terjadwal' => (int) ($tx->terjadwal ?? 0),
                'hadir' => (int) ($tx->hadir ?? 0),
                'libur' => (int) ($tx->libur ?? 0),
                'total_pengajuan' => (int) ($tx->sakit ?? 0) + (int) ($tx->izin ?? 0) + (int) ($tx->cuti_tahunan ?? 0) + (int) ($tx->cuti_khusus ?? 0) + (int) ($tx->lembur ?? 0) + (int) ($tx->konversi_lembur ?? 0),
            ],
            'tren_jam' => $trenJam,
            'sebaran_lokasi' => $sebaranLokasi,
            'terbaru_absen' => $terbaruAbsen,
            'terbaru_transaksi' => array_slice($daftarTransaksi, 0, 10),
        ];
    }

    /** @return array{items: array, total: int, page: int, limit: int} */
    public function daftar(string $tanggal, array $filter, FunctionalRole $role, ?Carbon $sekarang = null): array
    {
        $f = SaringanMonitoring::normalisasi($filter, self::URUT);
        $tandaDipilih = in_array($filter['tanda'] ?? null, self::TANDA, true) ? $filter['tanda'] : null;

        $q = DB::query()->fromSub($this->subkueri($tanggal, $f, $role, $sekarang ?? now()), 'x');

        if ($f['kategori']) {
            $q->where('x.kategori', $f['kategori']);
        }
        if ($tandaDipilih) {
            $q->where('x.tanda_'.$tandaDipilih, 1);
        }

        $total = (clone $q)->count();

        $this->urutkan($q, $f['urut'], $f['arah']);
        $rows = $q->select('x.*')->forPage($f['page'], $f['limit'])->get();

        return [
            'items' => $rows->map(fn ($r) => $this->bentukBaris($r))->values()->all(),
            'total' => $total,
            'page' => $f['page'],
            'limit' => $f['limit'],
        ];
    }

    private function subkueri(string $tanggal, array $f, FunctionalRole $role, Carbon $sekarang): Builder
    {
        $jamAcuan = KategoriKehadiran::jamAcuan($tanggal, $sekarang);
        $lampau = $tanggal < $sekarang->toDateString() ? 1 : 0;
        $ada = fn (string $kolom) => MonitoringKehadiranQuery::adaSql($kolom);

        return MonitoringKehadiranQuery::basis($tanggal, $tanggal, $f, $role)
            ->selectRaw(KategoriKehadiran::sqlCase('s').' as kategori', [$jamAcuan])
            ->selectRaw('CASE WHEN ISNULL(s.RealEarly, 0) > 0 THEN 1 ELSE 0 END as tanda_pulang_cepat')
            ->selectRaw("CASE WHEN s.Flag_Lembur = 'Y' OR ISNULL(s.Koefisien_OT, 0) > 0 THEN 1 ELSE 0 END as tanda_lembur")
            ->selectRaw("CASE WHEN s.Flag_Generate_Ulang = 'Y' THEN 1 ELSE 0 END as tanda_diperbarui")
            // Hari ini belum bisa disebut lupa check-out: orangnya mungkin masih bekerja.
            ->selectRaw("CASE WHEN {$lampau} = 1 AND s.CheckIn IS NOT NULL AND s.CheckOut IS NULL THEN 1 ELSE 0 END as tanda_tanpa_checkout")
            ->selectRaw("CASE WHEN s.Status_Kehadiran = 'IZIN TERLAMBAT' OR {$ada('s.No_Transaksi_Terlambat')} THEN 1 ELSE 0 END as izin_terlambat")
            ->selectRaw("CASE WHEN s.Status_Kehadiran = 'IZIN PULANG CEPAT' OR {$ada('s.No_Transaksi_PulangCepat')} THEN 1 ELSE 0 END as izin_pulang_cepat")
            ->addSelect([
                's.Kode_Karyawan', 's.Tanggal', 'k.Nama',
                'vd.nama_divisi as Divisi', 'vd.nama_sub_divisi as Departemen',
                'lvl.nama_jabatan as Jabatan', 'k.Cabang_Untuk_Gaji as Lokasi',
                's.StatusHari', 's.Nama_Shift', 's.Jam_Masuk', 's.Jam_Keluar',
                's.CheckIn', 's.CheckOut', 's.RealLate', 's.RealEarly', 's.Koefisien_OT',
                's.Status_Kehadiran', 's.Flag_Alpha',
                's.No_Transaksi_Cuti', 's.No_Transaksi_Izin', 's.No_Transaksi_Sakit',
                's.No_Transaksi_Lembur', 's.No_Transaksi_Terlambat', 's.No_Transaksi_PulangCepat',
                's.Jenis_Cuti', 's.Last_Calculated_At',
            ]);
    }

    private function urutkan(Builder $q, ?string $urut, string $arah): void
    {
        if ($urut === 'nama') {
            $q->orderBy('x.Nama', $arah);
        } elseif ($urut === 'terlambat') {
            $q->orderByRaw('ISNULL(x.RealLate, 0) '.$arah);
        } elseif ($urut === 'check_in') {
            $q->orderBy('x.CheckIn', $arah);
        } else {
            // Yang paling butuh perhatian di atas.
            $kasus = collect(KategoriKehadiran::URUTAN_PERHATIAN)
                ->map(fn ($k, $i) => "WHEN '{$k}' THEN {$i}")->implode(' ');
            $q->orderByRaw("CASE x.kategori {$kasus} ELSE 99 END");
        }

        $q->orderBy('x.Nama')->orderBy('x.Kode_Karyawan');
    }

    /** @return array<string, mixed> */
    private function bentukBaris(object $r): array
    {
        $jam = fn ($v) => $v ? Carbon::parse($v)->format('H:i') : null;
        $no = fn ($v) => KategoriKehadiran::ada($v) ? trim((string) $v) : null;
        $teks = fn ($v) => ($v = trim((string) $v)) !== '' ? $v : null;

        return [
            'kode' => (string) $r->Kode_Karyawan,
            'nama' => $teks($r->Nama) ?? (string) $r->Kode_Karyawan,
            'divisi' => $teks($r->Divisi),
            'departemen' => $teks($r->Departemen),
            'jabatan' => $teks($r->Jabatan),
            'lokasi' => $teks($r->Lokasi),
            'kategori' => (string) $r->kategori,
            'status_hari' => $teks($r->StatusHari),
            'shift' => $teks($r->Nama_Shift),
            'jam_masuk' => self::jamPendek($r->Jam_Masuk),
            'jam_keluar' => self::jamPendek($r->Jam_Keluar),
            'check_in' => $jam($r->CheckIn),
            'check_out' => $jam($r->CheckOut),
            'terlambat_menit' => (int) ($r->RealLate ?? 0),
            'pulang_cepat_menit' => (int) ($r->RealEarly ?? 0),
            'lembur_jam' => (int) ($r->Koefisien_OT ?? 0),
            'izin_terlambat' => (bool) $r->izin_terlambat,
            'izin_pulang_cepat' => (bool) $r->izin_pulang_cepat,
            // Alpha baru final setelah harinya lewat (snapshot menandainya sejak pagi).
            'alpha' => ($r->Flag_Alpha ?? '') === 'Y' && Carbon::parse($r->Tanggal)->toDateString() < now()->toDateString(),
            'diperbarui' => (bool) $r->tanda_diperbarui,
            'tanpa_checkout' => (bool) $r->tanda_tanpa_checkout,
            'status_kehadiran' => $teks($r->Status_Kehadiran),
            'jenis_cuti' => $teks($r->Jenis_Cuti),
            'transaksi' => array_filter([
                'cuti' => $no($r->No_Transaksi_Cuti),
                'izin' => $no($r->No_Transaksi_Izin),
                'sakit' => $no($r->No_Transaksi_Sakit),
                'lembur' => $no($r->No_Transaksi_Lembur),
                'terlambat' => $no($r->No_Transaksi_Terlambat),
                'pulang_cepat' => $no($r->No_Transaksi_PulangCepat),
            ]),
        ];
    }

    public static function jamPendek(mixed $v): ?string
    {
        $jam = KategoriKehadiran::normalJam((string) $v);

        return $jam ? substr($jam, 0, 5) : null;
    }
}
