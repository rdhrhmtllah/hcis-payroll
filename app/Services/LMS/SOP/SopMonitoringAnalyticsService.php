<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SaringanMonitoring;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Analitik monitoring SOP — mengubah data yang selama ini terpendam jadi
 * antrean kerja yang bisa ditindaklanjuti.
 *
 * Aturan MSSQL yang dipegang:
 *  - `CAST(col AS DATE)` HANYA boleh muncul di GROUP BY, tak pernah di WHERE
 *    (di WHERE ia non-sargable dan memaksa scan).
 *  - Perbandingan waktu memakai rentang setengah terbuka.
 *  - `DATEDIFF` dihitung di SELECT lalu disaring di derived table, bukan
 *    dibandingkan langsung terhadap kolom berindeks.
 */
class SopMonitoringAnalyticsService
{
    use SaringanMonitoring;

    /** Di bawah ini tren dianggap belum layak digambar. */
    private const MIN_PERISTIWA = 10;

    public function __construct(private SopMonitoringPeopleService $people) {}

    /* ================= SARINGAN BERSAMA =================

       Saringan tab Ringkasan: paket, prioritas (lingkup SOP) dan
       divisi, sub divisi, level, jabatan, lokasi (lingkup orang).

       Ditafsirkan SEKALI di trait SaringanMonitoring lalu dipinjamkan ke seluruh
       query analitik — termasuk SopRingkasanAgregatService. Sebelumnya tiap
       method punya sikapnya sendiri: papanRisiko() menerima array saringan tapi
       diam-diam hanya memakai `prioritas`, sehingga panel risiko tak pernah ikut
       menyempit bersama panel lain di layar yang sama. */

    /* ================= TREN KEPATUHAN ================= */

    /**
     * Deret waktu dari ledger `N_SOP_Kepatuhan_Riwayat` — satu-satunya time-series
     * di skema SOP.
     *
     * Batas kejujuran yang disengaja: coverage historis TIDAK bisa direkonstruksi
     * mundur, deretnya mulai sejak ledger mulai terisi. Bila peristiwa di jendela
     * ini < MIN_PERISTIWA, `cukup_data` false supaya UI menampilkan empty state,
     * bukan garis nol yang terbaca "coverage 0".
     */
    public function tren(?string $dari = null, ?string $sampai = null, string $granular = 'day', array $f = []): array
    {
        $mulai = $dari ? Carbon::parse($dari)->startOfDay() : Carbon::today()->subDays(89);
        $akhir = $sampai ? Carbon::parse($sampai)->startOfDay()->addDay() : Carbon::today()->addDay();
        $kunci = 'sop_mon_tren_'.md5($mulai->toDateString().$akhir->toDateString().$granular);

        return $this->mungkinCache($kunci, 900, $f, function () use ($mulai, $akhir, $granular, $f) {
            $ekspr = $granular === 'week'
                ? 'CAST(DATEADD(day, -(DATEPART(weekday, r.Waktu_Peristiwa) - 1), CAST(r.Waktu_Peristiwa AS DATE)) AS DATE)'
                : 'CAST(r.Waktu_Peristiwa AS DATE)';

            $peristiwa = DB::table('N_SOP_Kepatuhan_Riwayat as r')
                ->where('r.Waktu_Peristiwa', '>=', $mulai)
                ->where('r.Waktu_Peristiwa', '<', $akhir)
                ->tap(fn ($q) => $this->saringLedger($q, $f, 'r'))
                ->groupByRaw($ekspr)
                ->selectRaw(
                    "{$ekspr} as tanggal,
                     SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as selesai,
                     SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as dibuka_lagi,
                     SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as dicabut,
                     SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as kadaluarsa,
                     COUNT(*) as total",
                    [SopStatus::RWT_SELESAI, SopStatus::RWT_DIBUKA_LAGI, SopStatus::RWT_DICABUT, SopStatus::RWT_KADALUARSA],
                )
                ->orderByRaw($ekspr)
                ->get();

            $totalPeristiwa = (int) $peristiwa->sum('total');
            if ($totalPeristiwa < self::MIN_PERISTIWA) {
                return [
                    'titik' => [], 'peristiwa_total' => [], 'cukup_data' => false,
                    'jumlah_peristiwa' => $totalPeristiwa, 'minimum' => self::MIN_PERISTIWA,
                    'mulai_ledger' => optional(DB::table('N_SOP_Kepatuhan_Riwayat')->min('Waktu_Peristiwa'))
                        ? Carbon::parse(DB::table('N_SOP_Kepatuhan_Riwayat')->min('Waktu_Peristiwa'))->format('Y-m-d')
                        : null,
                ];
            }

            // Penugasan kumulatif sebagai penyebut coverage. WAJIB memakai saringan
            // yang sama dengan pembilangnya — penyebut se-organisasi dibagi
            // pembilang satu divisi menghasilkan coverage yang selalu terlalu
            // rendah, dan tak ada di layar yang menunjukkan kenapa.
            $ditugaskan = $this->penugasanTersaring($f)
                ->where('d.Created_At', '<', $akhir)
                ->groupByRaw('CAST(d.Created_At AS DATE)')
                ->selectRaw('CAST(d.Created_At AS DATE) as tanggal, COUNT(*) as jml')
                ->get()->keyBy(fn ($r) => Carbon::parse($r->tanggal)->toDateString());

            $sebelum = $this->penugasanTersaring($f)->where('d.Created_At', '<', $mulai)->count();
            $selesaiSebelum = DB::table('N_SOP_Kepatuhan_Riwayat as r')
                ->where('r.Waktu_Peristiwa', '<', $mulai)
                ->where('r.Peristiwa', SopStatus::RWT_SELESAI)
                ->tap(fn ($q) => $this->saringLedger($q, $f, 'r'))
                ->count();

            $kumTugas = $sebelum;
            $kumSelesai = $selesaiSebelum;
            $titik = [];

            foreach ($peristiwa as $p) {
                $tgl = Carbon::parse($p->tanggal)->toDateString();
                $kumTugas += (int) ($ditugaskan[$tgl]->jml ?? 0);
                $kumSelesai += (int) $p->selesai - (int) $p->dibuka_lagi;
                $kumSelesai = max(0, $kumSelesai);

                $titik[] = [
                    'tanggal' => $tgl,
                    'kumulatif_ditugaskan' => $kumTugas,
                    'kumulatif_selesai' => $kumSelesai,
                    'coverage' => $kumTugas > 0 ? (int) round(($kumSelesai / $kumTugas) * 100) : 0,
                    'selesai' => (int) $p->selesai,
                    'dibuka_lagi' => (int) $p->dibuka_lagi,
                    'dicabut' => (int) $p->dicabut,
                    'kadaluarsa' => (int) $p->kadaluarsa,
                ];
            }

            return [
                'titik' => $titik,
                'peristiwa_total' => [
                    'SELESAI' => (int) $peristiwa->sum('selesai'),
                    'DIBUKA_LAGI' => (int) $peristiwa->sum('dibuka_lagi'),
                    'DICABUT' => (int) $peristiwa->sum('dicabut'),
                    'KADALUARSA' => (int) $peristiwa->sum('kadaluarsa'),
                ],
                'cukup_data' => true,
                'jumlah_peristiwa' => $totalPeristiwa,
            ];
        });
    }

    /* ================= DENYUT (PULSE) ================= */

    /**
     * Denyut kepatuhan — ARAH, bukan sekadar angka hari ini.
     *
     * Angka telanjang ("12 terlambat") tak bisa ditindaklanjuti: naik dari 8
     * minggu lalu berarti sesuatu memburuk, turun dari 30 berarti perbaikan
     * sedang berjalan. Dua situasi itu menuntut respons berbeda, dan dasbor lama
     * tak membedakannya sama sekali.
     *
     * KEJUJURAN DATA yang dipegang:
     *  - Nilai "lalu" hanya bisa direkonstruksi dari ledger. Bila ledger belum
     *    mencakup jendela pembanding, delta dikembalikan NULL — UI menampilkan
     *    "belum ada pembanding", BUKAN 0 yang terbaca "tidak berubah".
     *  - Sparkline memakai penyelesaian harian (fakta yang tercatat), bukan
     *    coverage kumulatif yang butuh rekonstruksi penuh.
     *
     * @param  string|null  $sejak  ISO timestamp kunjungan terakhir (dari klien)
     */
    public function pulse(?string $sejak = null, array $f = []): array
    {
        return $this->mungkinCache(
            'sop_mon_pulse_'.md5((string) $sejak),
            300,
            $f,
            function () use ($sejak, $f) {
                $hariIni = Carbon::today();
                $mulai14 = $hariIni->copy()->subDays(13);

                // Deret harian penyelesaian — fakta terekam, bukan rekonstruksi.
                $harian = DB::table('N_SOP_Kepatuhan_Riwayat as r')
                    ->where('r.Waktu_Peristiwa', '>=', $mulai14)
                    ->where('r.Waktu_Peristiwa', '<', $hariIni->copy()->addDay())
                    ->tap(fn ($q) => $this->saringLedger($q, $f, 'r'))
                    ->groupByRaw('CAST(r.Waktu_Peristiwa AS DATE)')
                    ->selectRaw(
                        'CAST(r.Waktu_Peristiwa AS DATE) as tanggal,
                         SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as selesai',
                        [SopStatus::RWT_SELESAI],
                    )
                    ->get()->keyBy(fn ($r) => Carbon::parse($r->tanggal)->toDateString());

                $spark = [];
                for ($i = 0; $i < 14; $i++) {
                    $t = $mulai14->copy()->addDays($i)->toDateString();
                    $spark[] = ['tanggal' => $t, 'nilai' => (int) ($harian[$t]->selesai ?? 0)];
                }

                // Pembanding 7 hari: jumlah selesai minggu ini vs minggu sebelumnya.
                $hitung = fn (Carbon $a, Carbon $b) => DB::table('N_SOP_Kepatuhan_Riwayat as r')
                    ->where('r.Peristiwa', SopStatus::RWT_SELESAI)
                    ->where('r.Waktu_Peristiwa', '>=', $a)->where('r.Waktu_Peristiwa', '<', $b)
                    ->tap(fn ($q) => $this->saringLedger($q, $f, 'r'))
                    ->count();

                $mingguIni = $hitung($hariIni->copy()->subDays(6), $hariIni->copy()->addDay());
                $mingguLalu = $hitung($hariIni->copy()->subDays(13), $hariIni->copy()->subDays(6));

                // Ledger belum cukup tua → jangan mengarang pembanding.
                $awalLedger = DB::table('N_SOP_Kepatuhan_Riwayat')->min('Waktu_Peristiwa');
                $adaPembanding = $awalLedger && Carbon::parse($awalLedger)->lte($hariIni->copy()->subDays(13));

                $sejakBlok = null;
                if ($sejak) {
                    try {
                        $t = Carbon::parse($sejak);
                        // Batasi ke 30 hari: "sejak kunjungan terakhir" yang berumur
                        // setengah tahun bukan lagi kabar, itu laporan.
                        if ($t->gt($hariIni->copy()->subDays(30))) {
                            $b = DB::table('N_SOP_Kepatuhan_Riwayat as r')
                                ->where('r.Waktu_Peristiwa', '>=', $t)
                                ->tap(fn ($q) => $this->saringLedger($q, $f, 'r'))
                                ->selectRaw(
                                    'SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as selesai,
                                     SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as dibuka_lagi,
                                     SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as dicabut,
                                     SUM(CASE WHEN r.Peristiwa = ? THEN 1 ELSE 0 END) as kadaluarsa',
                                    [SopStatus::RWT_SELESAI, SopStatus::RWT_DIBUKA_LAGI, SopStatus::RWT_DICABUT, SopStatus::RWT_KADALUARSA],
                                )->first();

                            $total = (int) $b->selesai + (int) $b->dibuka_lagi + (int) $b->dicabut + (int) $b->kadaluarsa;
                            if ($total > 0) {
                                $sejakBlok = [
                                    'selesai' => (int) $b->selesai,
                                    'dibuka_lagi' => (int) $b->dibuka_lagi,
                                    'dicabut' => (int) $b->dicabut,
                                    'kadaluarsa' => (int) $b->kadaluarsa,
                                    'sejak' => $t->toIso8601String(),
                                ];
                            }
                        }
                    } catch (\Throwable $e) {
                        $sejakBlok = null; // timestamp klien tak dipercaya buta
                    }
                }

                return [
                    'selesai_7hr' => [
                        'kini' => $mingguIni,
                        'lalu' => $adaPembanding ? $mingguLalu : null,
                        'delta' => $adaPembanding ? ($mingguIni - $mingguLalu) : null,
                    ],
                    'sparkline' => $spark,
                    'ada_pembanding' => $adaPembanding,
                    'sejak' => $sejakBlok,
                ];
            },
        );
    }

    /* ================= PAPAN RISIKO ================= */

    /**
     * Urutan yang bisa ditelusuri, BUKAN skor gabungan bernilai ajaib:
     * Prioritas → jumlah terlambat → coverage terendah → keterlambatan terlama.
     * Admin tak akan memercayai peringkat yang tak bisa direproduksi sendiri,
     * jadi keempat nilainya ikut ditampilkan sebagai kolom.
     */
    public function papanRisiko(array $f = [], int $limit = 25): Collection
    {
        $hariIni = Carbon::today();

        /* Urutan mendesak dibaca dari master prioritas, bukan ditebak dari nama.
           CASE bernama yang dulu di sini ('KRITIS'→0, 'TINGGI'→1, sisanya→2)
           akan menaruh tingkat baru — betapapun mendesaknya — di kelompok
           "sisanya", yaitu DI BAWAH Normal, pada papan yang justru dibaca untuk
           tahu mana yang harus dikejar lebih dulu. */
        [$sqlUrutan, $bindUrutan] = app(SopPrioritasService::class)->urutanCase('p.Prioritas');

        $q = DB::table('N_SOP_Master_Paket as p')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF);

        // Dulu hanya `prioritas` yang dipakai di sini, sementara pemanggilnya
        // mengirim array saringan lengkap. Akibatnya papan risiko tak pernah ikut
        // menyempit bersama panel lain di layar yang sama.
        $this->saringPenugasan($q, $f);

        return $q->groupBy('p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Prioritas')
            ->selectRaw(
                "p.Id_SOP_Paket, p.Nama_Paket, p.Prioritas,
                 COUNT(*) as target,
                 SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as selesai,
                 SUM(CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' AND pj.Tanggal_Deadline IS NOT NULL
                           AND pj.Tanggal_Deadline < ? AND d.Status_Paket <> ? THEN 1 ELSE 0 END) as terlambat,
                 MAX(CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' AND pj.Tanggal_Deadline IS NOT NULL
                           AND pj.Tanggal_Deadline < ? AND d.Status_Paket <> ?
                          THEN DATEDIFF(day, pj.Tanggal_Deadline, ?) ELSE 0 END) as telat_maks_hari",
                [
                    SopStatus::STATUS_SELESAI,
                    $hariIni, SopStatus::STATUS_SELESAI,
                    $hariIni, SopStatus::STATUS_SELESAI, $hariIni,
                ],
            )
            ->orderByRaw($sqlUrutan, $bindUrutan)
            ->orderByRaw('terlambat DESC')
            ->orderByRaw('CASE WHEN COUNT(*) = 0 THEN 0 ELSE (SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) * 100 / COUNT(*)) END ASC', [SopStatus::STATUS_SELESAI])
            ->orderByRaw('telat_maks_hari DESC')
            ->limit($limit)
            ->get()
            ->map(function ($r) {
                $r->persen = (int) $r->target > 0 ? (int) round(((int) $r->selesai / (int) $r->target) * 100) : 0;
                $r->belum = (int) $r->target - (int) $r->selesai;
                $r->Id_SOP_Paket = Hashids::connection('custom')->encode($r->Id_SOP_Paket);

                return $r;
            });
    }

    /* ================= KONFIRMASI KILAT ================= */

    /**
     * Konfirmasi yang terlalu cepat untuk masuk akal — temuan khas modul kepatuhan
     * yang selama ini tak pernah dihitung siapa pun.
     *
     * Dua sinyal, keduanya dari data yang sudah ada:
     *  - durasi buka→konfirmasi jauh di bawah `Estimasi_Waktu_Baca_Menit` paket
     *  - `Halaman_Terakhir` jauh di bawah `Total_Halaman` berkas
     * `Durasi_Aktif_Detik` belum dipakai di sini: penulisnya (heartbeat reader,
     * simpanHalaman) baru ada — baris progres lama nilainya masih 0, jadi
     * memakainya sebagai sinyal akan memfitnah semua konfirmasi lama. Tinjau
     * ulang setelah datanya cukup terisi.
     *
     * @param  array  $f  rasio (default 0.2 = <20% estimasi), q
     */
    public function konfirmasiKilat(array $f, int $page, int $limit): array
    {
        $rasio = (float) ($f['rasio'] ?? 0.2);
        $rasio = max(0.01, min(1.0, $rasio));

        $inner = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'pr.Kode_Karyawan')
            // Jumlah halaman berasal dari LIBRARY, bukan N_SOP_Item_Versi_Berkas:
            // jalur unggah langsung sudah pensiun, sehingga join lama selalu
            // menghasilkan NULL dan satu lengan detektor "Konfirmasi Kilat"
            // (halaman-per-detik) diam-diam mati.
            ->leftJoin('N_SOP_Item_Versi as vr', 'vr.Id_SOP_Item_Versi', '=', 'pr.Id_SOP_Item_Versi')
            ->leftJoin('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'vr.Id_Master_Konten')
            // Penugasan + penjadwalan ikut di-join karena saringan paket membaca
            // Status_Paket/Tanggal_Selesai dari `d` dan Tanggal_Assign/Deadline
            // dari `pj`. Join polos tanpa penjagaan Flag_Aktif/Flag_Cabut_Akses:
            // Id_SOP_Penjadwalan_Detail tak pernah NULL pada progress, jadi
            // jumlah barisnya utuh — menambahkan penjagaan di sini akan
            // mengubah cakupan panel, bukan memperbaikinya.
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->whereNotNull('pr.Waktu_Buka')
            ->whereNotNull('pr.Waktu_Konfirmasi')
            ->whereIn('pr.Status', [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI])
            ->whereIn('i.Tipe_Item', ['DOKUMEN', 'FORM', 'VIDEO', 'PARAGRAF'])
            // Saringan ditegakkan di query DALAM, sebelum deteksi kilat berjalan:
            // menyaring di luar akan membuat paginasi dan totalnya berbeda.
            ->tap(fn ($q) => $this->saringPenugasan($q, $f, 'p', 'd', 'pr'))
            ->selectRaw(
                'pr.Id_SOP_Progress, pr.Kode_Karyawan, ky.Nama, p.Nama_Paket, i.Judul_Item, i.Tipe_Item,
                 pr.Waktu_Buka, pr.Waktu_Konfirmasi, pr.Halaman_Terakhir, pr.Konfirmasi_IP,
                 b.Total_Halaman,
                 ISNULL(p.Estimasi_Waktu_Baca_Menit, 0) as estimasi_menit,
                 DATEDIFF(second, pr.Waktu_Buka, pr.Waktu_Konfirmasi) as durasi_detik',
            );

        $q = DB::query()->fromSub($inner, 't')
            ->where('t.durasi_detik', '>=', 0)
            ->where(function ($w) use ($rasio) {
                // (a) jauh lebih cepat dari estimasi baca paket
                // Rasio ditulis inline sebagai literal desimal, bukan parameter: driver
                // sqlsrv mengikat float sebagai nvarchar dan MSSQL menolak konversinya
                // ("Conversion failed when converting the nvarchar value '0.2' to int").
                // Aman karena nilainya sudah di-clamp ke [0.01, 1.0] di atas.
                $w->where(function ($a) use ($rasio) {
                    $a->where('t.estimasi_menit', '>', 0)
                        ->whereRaw(sprintf('t.durasi_detik < (t.estimasi_menit * 60 * %.4f)', $rasio));
                })
                // (b) dokumen berhalaman tapi berhenti di sepertiga awal
                    ->orWhere(function ($a) {
                        $a->whereNotNull('t.Total_Halaman')->where('t.Total_Halaman', '>', 3)
                            ->whereRaw('ISNULL(t.Halaman_Terakhir, 0) * 3 < t.Total_Halaman');
                    })
                // (c) di bawah 15 detik itu mustahil untuk konten apa pun
                    ->orWhere('t.durasi_detik', '<', 15);
            });

        $cari = trim((string) ($f['q'] ?? ''));
        if (mb_strlen($cari) >= 2) {
            $q->where(fn ($w) => $w->where('t.Nama', 'LIKE', "%{$cari}%")
                ->orWhere('t.Kode_Karyawan', 'LIKE', "%{$cari}%")
                ->orWhere('t.Nama_Paket', 'LIKE', "%{$cari}%"));
        }

        $total = (clone $q)->count();

        $rows = $q->orderBy('t.durasi_detik')
            ->offset(($page - 1) * $limit)->limit($limit)->get()
            ->map(function ($r) {
                $r->Id_SOP_Progress = Hashids::connection('custom')->encode($r->Id_SOP_Progress);
                $r->alasan = [];
                if ((int) $r->estimasi_menit > 0 && (int) $r->durasi_detik < (int) $r->estimasi_menit * 60 * 0.2) {
                    $r->alasan[] = 'JAUH_DI_BAWAH_ESTIMASI';
                }
                if ($r->Total_Halaman && (int) $r->Total_Halaman > 3
                    && (int) ($r->Halaman_Terakhir ?? 0) * 3 < (int) $r->Total_Halaman) {
                    $r->alasan[] = 'BERHENTI_DI_AWAL';
                }
                if ((int) $r->durasi_detik < 15) {
                    $r->alasan[] = 'DI_BAWAH_15_DETIK';
                }
                $r->Waktu_Konfirmasi = $r->Waktu_Konfirmasi ? Carbon::parse($r->Waktu_Konfirmasi)->format('Y-m-d H:i') : null;

                return $r;
            });

        return [$rows, $total];
    }

    /* ================= MANDEK ================= */

    /** Sudah dibuka tapi tak kunjung dikonfirmasi — kebocoran di antara dua tahap funnel. */
    public function mandek(int $hari, int $page, int $limit, array $f = []): array
    {
        $batas = Carbon::now()->subDays(max(1, $hari));

        $q = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            // Mandek adalah antrean TINDAK LANJUT. Tanpa join ini, baris milik
            // penugasan yang aksesnya sudah dicabut ikut muncul dan admin mengejar
            // orang yang sudah tak berkewajiban — false positive langsung.
            ->join('N_SOP_Penjadwalan_Detail as d', function ($j) {
                $j->on('d.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
                    ->where('d.Flag_Aktif', '=', SopStatus::AKTIF)
                    ->where('d.Flag_Cabut_Akses', '=', SopStatus::INAKTIF);
            })
            // Penjadwalan dibutuhkan saringan tanggal (Tanggal_Assign/Deadline);
            // 1:1 terhadap `d`, jadi tak mengubah jumlah baris.
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'pr.Kode_Karyawan')
            ->where('pr.Status', SopStatus::ITEM_DIBUKA)
            ->whereNotNull('pr.Waktu_Buka')
            ->where('pr.Waktu_Buka', '<', $batas);

        // Alias ORANG di sini `pr`, bukan `d` — keduanya punya Kode_Karyawan,
        // tapi hanya `pr` yang pasti terisi untuk baris progress. Alias PENUGASAN
        // tetap `d`, karena Status_Paket hanya ada di sana.
        $this->saringPenugasan($q, $f, 'p', 'd', 'pr');

        $total = (clone $q)->count();

        $rows = $q->orderBy('pr.Waktu_Buka')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get([
                'pr.Kode_Karyawan', 'ky.Nama', 'p.Nama_Paket', 'i.Judul_Item', 'i.Tipe_Item',
                'pr.Waktu_Buka', 'pr.Halaman_Terakhir', 'pr.Persen_Selesai',
            ])
            ->map(function ($r) {
                $r->hari_mandek = (int) Carbon::parse($r->Waktu_Buka)->diffInDays(Carbon::now());
                $r->Waktu_Buka = Carbon::parse($r->Waktu_Buka)->format('Y-m-d H:i');

                return $r;
            });

        return [$rows, $total];
    }

    /* ================= GRAFIK ================= */

    /** Sebaran durasi buka→konfirmasi. Kelompok paling kiri = populasi konfirmasi kilat. */
    public function distribusiWaktuSelesai(array $f = []): array
    {
        return $this->mungkinCache('sop_mon_waktuselesai', 900, $f, function () use ($f) {
            $q = DB::table('N_SOP_Progress as pr')
                ->whereNotNull('pr.Waktu_Buka')->whereNotNull('pr.Waktu_Konfirmasi');

            // Berpusat di progress, jadi paketnya dicapai lewat item — bukan lewat
            // penjadwalan seperti query penugasan.
            if ($this->perluPaket($f)) {
                $q->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
                    ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket');
                $this->people->terapkanSaringanPaketRingkas($q, $f, 'p');
            }
            $this->people->terapkanSaringanOrang($q, $f, 'pr');

            $r = $q->selectRaw(
                'SUM(CASE WHEN DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) < 60 THEN 1 ELSE 0 END) as b1,
                     SUM(CASE WHEN DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) >= 60 AND DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) < 1440 THEN 1 ELSE 0 END) as b2,
                     SUM(CASE WHEN DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) >= 1440 AND DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) < 4320 THEN 1 ELSE 0 END) as b3,
                     SUM(CASE WHEN DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) >= 4320 AND DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) < 10080 THEN 1 ELSE 0 END) as b4,
                     SUM(CASE WHEN DATEDIFF(minute, pr.Waktu_Buka, pr.Waktu_Konfirmasi) >= 10080 THEN 1 ELSE 0 END) as b5,
                     COUNT(*) as total',
            )->first();

            return [
                'kelompok' => [
                    ['label' => '< 1 jam',   'jumlah' => (int) ($r->b1 ?? 0), 'kilat' => true],
                    ['label' => '1–24 jam',  'jumlah' => (int) ($r->b2 ?? 0), 'kilat' => false],
                    ['label' => '1–3 hari',  'jumlah' => (int) ($r->b3 ?? 0), 'kilat' => false],
                    ['label' => '3–7 hari',  'jumlah' => (int) ($r->b4 ?? 0), 'kilat' => false],
                    ['label' => '> 7 hari',  'jumlah' => (int) ($r->b5 ?? 0), 'kilat' => false],
                ],
                'total' => (int) ($r->total ?? 0),
            ];
        });
    }

    /** Umur keterlambatan × prioritas. "96 terlambat" diabaikan; "31 KRITIS >90 hari" ditindaklanjuti. */
    public function agingTerlambat(array $f = []): array
    {
        return $this->mungkinCache('sop_mon_aging', 900, $f, function () use ($f) {
            $hariIni = Carbon::today();

            $rows = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
                ->tap(fn ($q) => $this->saringPenugasan($q, $f))
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
                ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
                ->where('pj.Tipe_Tenggat', 'DEADLINE')
                ->whereNotNull('pj.Tanggal_Deadline')
                ->where('pj.Tanggal_Deadline', '<', $hariIni)
                ->groupBy('p.Prioritas')
                ->selectRaw(
                    'p.Prioritas,
                     SUM(CASE WHEN DATEDIFF(day, pj.Tanggal_Deadline, ?) <= 7 THEN 1 ELSE 0 END) as b1,
                     SUM(CASE WHEN DATEDIFF(day, pj.Tanggal_Deadline, ?) BETWEEN 8 AND 30 THEN 1 ELSE 0 END) as b2,
                     SUM(CASE WHEN DATEDIFF(day, pj.Tanggal_Deadline, ?) BETWEEN 31 AND 90 THEN 1 ELSE 0 END) as b3,
                     SUM(CASE WHEN DATEDIFF(day, pj.Tanggal_Deadline, ?) > 90 THEN 1 ELSE 0 END) as b4',
                    [$hariIni, $hariIni, $hariIni, $hariIni],
                )->get();

            $label = ['0–7 hari', '8–30 hari', '31–90 hari', '> 90 hari'];
            $seri = [];
            foreach ($rows as $r) {
                $seri[] = [
                    'prioritas' => $r->Prioritas,
                    'data' => [(int) $r->b1, (int) $r->b2, (int) $r->b3, (int) $r->b4],
                ];
            }

            return ['kategori' => $label, 'seri' => $seri, 'total' => collect($seri)->sum(fn ($s) => array_sum($s['data']))];
        });
    }

    /**
     * Sumbu X yang boleh dipilih, beserta BUTIRANNYA.
     *
     * Butiran bukan detail teknis melainkan penentu arti angkanya:
     *
     *  - Dimensi milik PAKET (prioritas) menempel pada penugasan, jadi
     *    satu penugasan jatuh tepat di satu kolom. Satuannya PENUGASAN —
     *    orang x paket, sama seperti kartu lain di dashboard.
     *  - Dimensi milik DOKUMEN (APQC, jenis, entitas) menuntut join ke
     *    Paket_Item -> Dokumen, dan join itu MELIPATGANDAKAN baris penugasan:
     *    satu paket berisi banyak dokumen dengan proses berbeda. COUNT(*) di
     *    sana membuat satu orang muncul di beberapa kolom sekaligus dan jumlah
     *    kolom melampaui totalnya sendiri — salah tanpa satu pun tanda. Karena
     *    itu butirannya berpindah ke KEWAJIBAN BACA (orang x dokumen) yang
     *    dibaca dari N_SOP_Progress, dan "selesai" berarti item dikonfirmasi.
     *
     * Satuan yang berlaku ikut dikirim ke layar supaya kartunya menyebutkannya.
     * Dua satuan yang tak dinyatakan adalah cara tercepat membuat orang
     * membandingkan dua angka yang tak sebanding.
     */
    private const SUMBU_X = [
        'prioritas' => ['label' => 'Prioritas Paket', 'butiran' => 'penugasan',
            'kolom' => 'p.Prioritas', 'kosong' => 'Tanpa Prioritas'],
        'apqc' => ['label' => 'Proses APQC', 'butiran' => 'kewajiban',
            'kolom' => 'apqc.Nama_Proses', 'kosong' => 'Tanpa Proses'],
        'jenis' => ['label' => 'Jenis Dokumen', 'butiran' => 'kewajiban',
            'kolom' => 'jd.Nama_Jenis', 'kosong' => 'Tanpa Jenis'],
        'entitas' => ['label' => 'Entitas Pemilik', 'butiran' => 'kewajiban',
            'kolom' => 'ent.Nama_Singkat', 'kosong' => 'Tanpa Entitas'],
    ];

    /** Daftar pilihan sumbu X untuk layar — bukan dihafal di sana. */
    public static function sumbuX(): array
    {
        $out = [];
        foreach (self::SUMBU_X as $kunci => $d) {
            $out[] = ['kunci' => $kunci, 'label' => $d['label'], 'butiran' => $d['butiran']];
        }

        return $out;
    }

    /**
     * Penyelesaian per departemen x sumbu X yang dipilih.
     *
     * BARIS BERPAGINASI, KOLOM BERPLAFON. Paginasi pada baris menggantikan
     * pelipatan "Lainnya" yang dulu menyembunyikan ekor departemen — pelipatan
     * menyembunyikan, paginasi menampakkan. Kolom tetap dilipat karena kolom
     * yang berpaginasi membuat matriksnya berhenti terbaca sebagai matriks:
     * dua halaman kolom tak bisa dibandingkan dengan mata.
     */
    public function heatmap(array $f = [], string $x = 'prioritas', int $halaman = 1, int $perHalaman = 12, int $maksKolom = 12): array
    {
        $x = isset(self::SUMBU_X[$x]) ? $x : 'prioritas';
        $def = self::SUMBU_X[$x];
        $halaman = max(1, $halaman);
        $perHalaman = min(40, max(4, $perHalaman));

        $kunciCache = 'sop_mon_heatmap_'.$x.'_'.$halaman.'_'.$perHalaman;

        return $this->mungkinCache($kunciCache, 900, $f, function () use ($f, $x, $def, $halaman, $perHalaman, $maksKolom) {
            $rows = $def['butiran'] === 'penugasan'
                ? $this->heatmapPenugasan($f, $def)
                : $this->heatmapKewajiban($f, $def);

            $kosong = [
                'dept' => [], 'kategori' => [], 'seri' => [], 'kosong' => true,
                'sumbu_x' => $x, 'label_x' => $def['label'], 'butiran' => $def['butiran'],
                'halaman' => 1, 'per_halaman' => $perHalaman, 'total_dept' => 0, 'total_halaman' => 1,
            ];

            if ($rows->isEmpty()) {
                return $kosong;
            }

            /* Departemen diurut menurut BEBAN, bukan abjad: yang menanggung
               paling banyak muncul di halaman pertama, karena itulah yang
               dicari orang saat membuka panel ini. */
            $semuaDept = $rows->groupBy('dept')->map(fn ($g) => $g->sum('target'))
                ->sortDesc()->keys()->values()->all();
            $totalDept = count($semuaDept);
            $totalHalaman = max(1, (int) ceil($totalDept / $perHalaman));
            $halaman = min($halaman, $totalHalaman);
            $deptHalaman = array_slice($semuaDept, ($halaman - 1) * $perHalaman, $perHalaman);

            $semuaKol = $rows->groupBy('kolom')->map(fn ($g) => $g->sum('target'))
                ->sortDesc()->keys()->values()->all();
            $adaSisaKol = count($semuaKol) > $maksKolom;
            $kol = array_slice($semuaKol, 0, $maksKolom);

            $peta = [];
            foreach ($rows as $r) {
                if (! in_array($r->dept, $deptHalaman, true)) {
                    continue;
                }
                $c = in_array($r->kolom, $kol, true) ? $r->kolom : ($adaSisaKol ? 'Lainnya' : $r->kolom);
                $peta[$r->dept][$c]['target'] = ($peta[$r->dept][$c]['target'] ?? 0) + (int) $r->target;
                $peta[$r->dept][$c]['selesai'] = ($peta[$r->dept][$c]['selesai'] ?? 0) + (int) $r->selesai;
            }

            $kolomTampil = $adaSisaKol ? array_values(array_unique(array_merge($kol, ['Lainnya']))) : $kol;
            $seri = [];
            foreach ($deptHalaman as $d) {
                $data = [];
                foreach ($kolomTampil as $c) {
                    $t = $peta[$d][$c]['target'] ?? 0;
                    $sl = $peta[$d][$c]['selesai'] ?? 0;
                    $data[] = ['x' => $c, 'y' => $t > 0 ? (int) round(($sl / $t) * 100) : null, 'target' => $t];
                }
                $seri[] = ['name' => $d, 'data' => $data];
            }

            return [
                'dept' => $deptHalaman,
                'kategori' => $kolomTampil,
                'seri' => $seri,
                'kosong' => $seri === [],
                'sumbu_x' => $x,
                'label_x' => $def['label'],
                'butiran' => $def['butiran'],
                'halaman' => $halaman,
                'per_halaman' => $perHalaman,
                'total_dept' => $totalDept,
                'total_halaman' => $totalHalaman,
            ];
        });
    }

    /** Butiran PENUGASAN — satu baris per orang x paket. */
    private function heatmapPenugasan(array $f, array $def)
    {
        $kolom = $def['kolom'];
        $kosong = $def['kosong'];

        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'ky.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->tap(fn ($q) => $this->saringPenugasan($q, $f))
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->groupByRaw("ISNULL(div.nama_sub_divisi, 'Tanpa Departemen'), ISNULL({$kolom}, '{$kosong}')")
            ->selectRaw(
                "ISNULL(div.nama_sub_divisi, 'Tanpa Departemen') as dept,
                 ISNULL({$kolom}, '{$kosong}') as kolom,
                 COUNT(*) as target,
                 SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as selesai",
                [SopStatus::STATUS_SELESAI],
            )->get();
    }

    /**
     * Butiran KEWAJIBAN BACA — satu baris per orang x dokumen.
     *
     * Berangkat dari N_SOP_Progress, bukan dari penugasan yang di-join ke item:
     * progress SUDAH berbutir satu baris per (penugasan, item), jadi tak ada
     * pelipatan yang harus dikoreksi belakangan. "Selesai" di sini berarti item
     * dikonfirmasi — bukan paketnya tuntas.
     */
    private function heatmapKewajiban(array $f, array $def)
    {
        $kolom = $def['kolom'];
        $kosong = $def['kosong'];

        return DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->leftJoin('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->leftJoin('N_SOP_Master_APQC as apqc', 'apqc.Id_SOP_APQC', '=', 'dok.APQC_Id')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->leftJoin('N_HRIS_Master_Entitas as ent', 'ent.Id_Entitas', '=', 'dok.Entitas_Pemilik_Id')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'pr.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'ky.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->tap(fn ($q) => $this->saringPenugasan($q, $f, 'p', 'd', 'pr'))
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->groupByRaw("ISNULL(div.nama_sub_divisi, 'Tanpa Departemen'), ISNULL({$kolom}, '{$kosong}')")
            ->selectRaw(
                "ISNULL(div.nama_sub_divisi, 'Tanpa Departemen') as dept,
                 ISNULL({$kolom}, '{$kosong}') as kolom,
                 COUNT(*) as target,
                 SUM(CASE WHEN pr.Status = ? THEN 1 ELSE 0 END) as selesai",
                [SopStatus::ITEM_DIKONFIRMASI],
            )->get();
    }
}
