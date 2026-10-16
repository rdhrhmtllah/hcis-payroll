<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SaringanMonitoring;
use App\Services\LMS\SOP\Support\SopKaryawanToken;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Agregat tab Ringkasan monitoring SOP — lapis kedua di atas
 * SopMonitoringAnalyticsService.
 *
 * Pembagian tugasnya: Analytics menjawab "berapa banyak & sejak kapan"
 * (tren, aging, heatmap, papan risiko). Service ini menjawab pertanyaan yang
 * tak bisa dijawab satu angka — apakah kita mengejar atau tertinggal, siapa
 * yang benar-benar memikul tunggakan, di mana orang berhenti membaca, dan
 * apakah yang dibaca itu versi yang masih berlaku.
 *
 * Catatan kejujuran data yang melekat pada seluruh method di bawah:
 *
 *  - `N_SOP_Penjadwalan_Detail.Tanggal_Selesai` TIDAK bisa dipakai sebagai
 *    sumbu waktu penyelesaian. Per 20 Agu 2026 hanya 5 dari 77 penugasan
 *    berstatus SELESAI yang kolomnya terisi, jadi laju apa pun yang dihitung
 *    darinya akan salah 15×. Sumbu waktu penyelesaian tingkat PENUGASAN diambil
 *    dari ledger `N_SOP_Kepatuhan_Riwayat` (peristiwa SELESAI), dan tingkat ITEM
 *    dari `N_SOP_Progress.Waktu_Konfirmasi`.
 *
 *  - Ledger baru mulai terisi 16 Mei 2026. Semua perbandingan "vs sekian hari
 *    lalu" karenanya membawa `basis_ledger` supaya UI bisa menyatakan batasnya
 *    alih-alih menampilkan nol yang terbaca sebagai "tidak ada penyelesaian".
 *
 *  - `Jumlah_Reminder`/`Terakhir_Diingatkan_At` SENGAJA tak dipakai di sini.
 *    Keduanya hanya ditulis oleh SopReminderService (pengingat terjadwal);
 *    tombol "Ingatkan" manual di Monitoring tidak menaikkannya sama sekali,
 *    sehingga agregat efektivitas pengingat akan mengukur separuh kenyataan.
 */
class SopRingkasanAgregatService
{
    use SaringanMonitoring;

    /**
     * Jendela panel Pemahaman — keputusan produk 2026-09-01.
     *
     * Dipakai HANYA oleh kualitasBaca() dan bacaVsEstimasi(), yang tampil pada
     * satu panel dan karena itu wajib memakai populasi yang sama.
     *
     * Panel Titik Henti (penyerapanVersi) sengaja TIDAK memakainya: ia menjawab
     * "siapa yang masih tertinggal di versi lama", yaitu KEADAAN SEKARANG, bukan
     * aktivitas terkini. Orang yang mengonfirmasi revisi kedua tiga tahun lalu
     * sementara yang berlaku sudah keempat memang masih tertinggal —
     * menyaringnya lewat jendela waktu akan menyembunyikan persis orang yang
     * dicari panel itu.
     */
    private const JENDELA_HARI = 90;

    /** Di bawah jumlah ini, agregat kuis dianggap belum layak dibaca sebagai persentase. */
    private const MIN_PERCOBAAN_KUIS = 5;

    /** Di bawah jumlah ini, laju penyelesaian terlalu tipis untuk diproyeksikan. */
    private const MIN_PERISTIWA_LAJU = 3;

    public function __construct(private SopMonitoringPeopleService $people) {}

    /* ================= BAB 2 — MOMENTUM & PROYEKSI ================= */

    /**
     * Apakah kita mengejar atau makin tertinggal?
     *
     * Satu angka coverage tak pernah menjawab itu: 60% yang naik 2 poin per
     * minggu dan 60% yang turun 2 poin per minggu terlihat persis sama di kartu
     * KPI. Yang membedakan adalah dua aliran — penugasan MASUK dan penugasan
     * TUNTAS — dan selisihnya dari minggu ke minggu.
     */
    public function momentum(array $f = [], int $minggu = 12): array
    {
        $minggu = max(4, min(26, $minggu));

        // Rentang ikut ke kunci: tanpa itu permintaan 26 minggu akan dilayani
        // dengan deret 12 minggu yang kebetulan sudah tersimpan.
        return $this->mungkinCache("sop_ring_momentum_{$minggu}", 600, $f, function () use ($f, $minggu) {
            $awal = Carbon::today()->startOfWeek()->subWeeks($minggu - 1);
            $akhir = Carbon::today()->startOfWeek()->addWeek(); // setengah terbuka

            $sisa = $this->sisaBacklog($f);
            $laju = $this->lajuPenyelesaian($f);
            $deret = $this->deretMingguan($f, $awal, $akhir, $minggu);

            // Empat minggu terakhir dipakai sebagai "arah sekarang". Satu minggu
            // terlalu berisik (satu batch besar membalik tandanya), satu kuartal
            // terlalu lambat menyadari perubahan.
            $ekor = array_slice($deret, -4);
            $masukEkor = array_sum(array_column($ekor, 'masuk'));
            $tuntasEkor = array_sum(array_column($ekor, 'tuntas'));
            $net = $tuntasEkor - $masukEkor;

            $lajuHarian = $laju['penugasan_30'] ?: $laju['penugasan_7'];
            $cukupProyeksi = $laju['peristiwa_30'] >= self::MIN_PERISTIWA_LAJU && $lajuHarian > 0;

            return [
                'sisa' => $sisa,
                'laju' => $laju,
                'deret' => $deret,
                'arah' => [
                    'masuk_4mgg' => $masukEkor,
                    'tuntas_4mgg' => $tuntasEkor,
                    'net' => $net,
                    // Ambang 0 disengaja: "seimbang" hanya benar-benar seimbang.
                    'status' => $net > 0 ? 'mengejar' : ($net < 0 ? 'tertinggal' : 'seimbang'),
                ],
                'proyeksi' => [
                    'cukup_data' => $cukupProyeksi,
                    'hari' => $cukupProyeksi ? (int) ceil($sisa['penugasan'] / $lajuHarian) : null,
                    'tanggal' => $cukupProyeksi
                        ? Carbon::today()->addDays((int) ceil($sisa['penugasan'] / $lajuHarian))->toDateString()
                        : null,
                    'dasar' => $laju['peristiwa_30'] >= self::MIN_PERISTIWA_LAJU ? '30 hari' : '7 hari',
                ],
                'basis_ledger' => $this->awalLedger(),
            ];
        });
    }

    /** Sisa yang harus dikejar, di dua satuan yang berbeda maknanya. */
    private function sisaBacklog(array $f): array
    {
        $penugasan = (clone $this->penugasanTersaring($f))
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->count();

        $qItem = DB::table('N_SOP_Progress as pr')
            ->whereIn('pr.Status', [SopStatus::ITEM_BELUM, SopStatus::ITEM_DIBUKA]);
        $this->saringProgress($qItem, $f, 'pr');
        $item = $qItem->count();

        $qOrang = (clone $this->penugasanTersaring($f))
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI);

        return [
            'penugasan' => $penugasan,
            'item' => $item,
            'orang' => $qOrang->distinct()->count('d.Kode_Karyawan'),
        ];
    }

    /**
     * Laju penyelesaian per hari, di dua satuan.
     *
     * Penugasan dihitung dari ledger; item dari Waktu_Konfirmasi. Keduanya
     * dilaporkan karena satu paket bisa berisi 12 item — "3 penugasan tuntas
     * seminggu" dan "36 item dikonfirmasi seminggu" adalah kalimat yang sama.
     */
    private function lajuPenyelesaian(array $f): array
    {
        $hitungLedger = function (int $hari) use ($f) {
            $q = DB::table('N_SOP_Kepatuhan_Riwayat as r')
                ->where('r.Peristiwa', SopStatus::RWT_SELESAI)
                ->where('r.Waktu_Peristiwa', '>=', Carbon::today()->subDays($hari))
                ->where('r.Waktu_Peristiwa', '<', Carbon::tomorrow());
            $this->saringLedger($q, $f, 'r');

            return $q->count();
        };

        $hitungItem = function (int $hari) use ($f) {
            $q = DB::table('N_SOP_Progress as pr')
                ->whereNotNull('pr.Waktu_Konfirmasi')
                ->where('pr.Waktu_Konfirmasi', '>=', Carbon::today()->subDays($hari))
                ->where('pr.Waktu_Konfirmasi', '<', Carbon::tomorrow());
            $this->saringProgress($q, $f, 'pr');

            return $q->count();
        };

        $p7 = $hitungLedger(7);
        $p30 = $hitungLedger(30);
        $i7 = $hitungItem(7);
        $i30 = $hitungItem(30);

        return [
            'peristiwa_7' => $p7,
            'peristiwa_30' => $p30,
            'penugasan_7' => round($p7 / 7, 2),
            'penugasan_30' => round($p30 / 30, 2),
            'item_7' => round($i7 / 7, 2),
            'item_30' => round($i30 / 30, 2),
            'item_peristiwa_7' => $i7,
            'item_peristiwa_30' => $i30,
        ];
    }

    /**
     * Deret mingguan MASUK vs TUNTAS.
     *
     * Dua query, bukan satu: keduanya bersandar pada kolom tanggal yang berbeda
     * di tabel yang berbeda, dan menggabungkannya dalam satu GROUP BY hanya bisa
     * dilakukan dengan mengorbankan salah satunya.
     */
    private function deretMingguan(array $f, Carbon $awal, Carbon $akhir, int $minggu): array
    {
        $ember = [];
        for ($i = 0; $i < $minggu; $i++) {
            $mulai = (clone $awal)->addWeeks($i);
            $ember[$i] = [
                'minggu' => $mulai->toDateString(),
                'label' => $mulai->translatedFormat('d M'),
                'masuk' => 0,
                'tuntas' => 0,
            ];
        }

        /* Jangkar minggu ditulis sebagai LITERAL, bukan parameter.
           SQL Server mencocokkan ekspresi GROUP BY dengan ekspresi SELECT secara
           tekstual SETELAH parameterisasi: `DATEDIFF(day, @p1, ...)` di SELECT dan
           `DATEDIFF(day, @p2, ...)` di GROUP BY adalah dua ekspresi berbeda
           baginya, dan ia menolaknya dengan menuduh Created_At tak ada di GROUP BY
           — pesan yang menyesatkan sepenuhnya. Nilainya dibentuk dari Carbon di
           sini, bukan dari masukan pemakai, jadi tak ada permukaan injeksi. */
        $jangkar = $awal->toDateString();
        $emberMasuk = "DATEDIFF(day, '{$jangkar}', d.Created_At) / 7";

        $qMasuk = (clone $this->penugasanTersaring($f))
            ->where('d.Created_At', '>=', $awal)
            ->where('d.Created_At', '<', $akhir)
            ->groupByRaw($emberMasuk)
            ->selectRaw("{$emberMasuk} as w, COUNT(*) as n");

        foreach ($qMasuk->get() as $r) {
            if (isset($ember[(int) $r->w])) {
                $ember[(int) $r->w]['masuk'] = (int) $r->n;
            }
        }

        $qTuntas = DB::table('N_SOP_Kepatuhan_Riwayat as r')
            ->where('r.Peristiwa', SopStatus::RWT_SELESAI)
            ->where('r.Waktu_Peristiwa', '>=', $awal)
            ->where('r.Waktu_Peristiwa', '<', $akhir);
        $this->saringLedger($qTuntas, $f, 'r');

        $emberTuntas = "DATEDIFF(day, '{$jangkar}', r.Waktu_Peristiwa) / 7";
        $qTuntas->groupByRaw($emberTuntas)
            ->selectRaw("{$emberTuntas} as w, COUNT(*) as n");

        foreach ($qTuntas->get() as $r) {
            if (isset($ember[(int) $r->w])) {
                $ember[(int) $r->w]['tuntas'] = (int) $r->n;
            }
        }

        return array_values($ember);
    }

    /** Kapan ledger kepatuhan mulai terisi — batas kejujuran semua perbandingan historis. */
    private function awalLedger(): ?string
    {
        $r = DB::table('N_SOP_Kepatuhan_Riwayat')->min('Waktu_Peristiwa');

        return $r ? Carbon::parse($r)->toDateString() : null;
    }

    /* ================= BAB 3 — PERINGKAT UNIT ================= */

    /** Dimensi organisasi yang boleh diperingkat, beserta kolom & label saringannya. */
    private const DIMENSI = [
        'divisi' => ['kolom' => 'v.nama_divisi', 'label' => 'Divisi', 'saringan' => 'divisi'],
        'dept' => ['kolom' => 'v.nama_sub_divisi', 'label' => 'Sub Divisi', 'saringan' => 'dept'],
        'lokasi' => ['kolom' => 'k.Cabang_Untuk_Gaji', 'label' => 'Lokasi Kerja', 'saringan' => 'lokasi'],
        'jabatan' => ['kolom' => 'l.nama_jabatan', 'label' => 'Jabatan', 'saringan' => 'jabatan'],
        'level' => ['kolom' => 'l.nama_level', 'label' => 'Level', 'saringan' => 'level'],
    ];

    /**
     * Papan peringkat unit — menjawab "siapa yang tertinggal", bukan "berapa
     * angkanya".
     *
     * Delta dihitung terhadap potret 30 hari lalu yang direkonstruksi dari
     * ledger: penugasan yang SUDAH ADA saat itu, dibanding yang sudah punya
     * peristiwa SELESAI sebelum tanggal itu. Unit yang belum punya satu pun
     * penugasan berumur 30 hari mengembalikan delta null — bukan 0, karena
     * "tidak bergerak" dan "belum bisa dibandingkan" adalah dua kabar berbeda.
     */
    public function peringkatUnit(string $dimensi, array $f = [], int $limit = 12): array
    {
        $dim = self::DIMENSI[$dimensi] ?? self::DIMENSI['divisi'];
        $limit = max(3, min(50, $limit));

        return $this->mungkinCache("sop_ring_unit_{$dimensi}_{$limit}", 600, $f, function () use ($dim, $f, $limit, $dimensi) {
            $hariIni = Carbon::today();
            $batas = Carbon::today()->subDays(30);

            $q = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as v', 'v.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'l.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                // Potret penyelesaian historis: peristiwa SELESAI PERTAMA per penugasan.
                ->leftJoin(DB::raw(
                    '(SELECT Id_SOP_Penjadwalan_Detail, MIN(Waktu_Peristiwa) AS Waktu_Selesai
                        FROM N_SOP_Kepatuhan_Riwayat
                       WHERE Peristiwa = \''.SopStatus::RWT_SELESAI.'\'
                         AND Id_SOP_Penjadwalan_Detail IS NOT NULL
                    GROUP BY Id_SOP_Penjadwalan_Detail) as sl'
                ), 'sl.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
                ->whereNotNull($dim['kolom']);

            if ($this->perluPaket($f)) {
                $q->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket');
                $this->people->terapkanSaringanPaket($q, $f, 'p', 'pj', 'd');
            }
            $this->people->terapkanSaringanOrang($q, $f, 'd');

            $rows = $q->groupBy(DB::raw($dim['kolom']))
                ->selectRaw(
                    $dim['kolom'].' as unit,
                     COUNT(*) as total,
                     COUNT(DISTINCT d.Kode_Karyawan) as orang,
                     SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as selesai,
                     SUM(CASE WHEN d.Status_Paket <> ? AND pj.Tipe_Tenggat = \'DEADLINE\'
                              AND pj.Tanggal_Deadline < ? THEN 1 ELSE 0 END) as terlambat,
                     SUM(CASE WHEN d.Created_At < ? THEN 1 ELSE 0 END) as total_lalu,
                     SUM(CASE WHEN d.Created_At < ? AND sl.Waktu_Selesai IS NOT NULL
                              AND sl.Waktu_Selesai < ? THEN 1 ELSE 0 END) as selesai_lalu',
                    [
                        SopStatus::STATUS_SELESAI,
                        SopStatus::STATUS_SELESAI, $hariIni,
                        $batas,
                        $batas, $batas,
                    ],
                )->get();

            $unit = $rows->map(function ($r) use ($dim) {
                $total = (int) $r->total;
                $persen = $total > 0 ? round((int) $r->selesai / $total * 100) : 0;
                $totalLalu = (int) $r->total_lalu;
                $persenLalu = $totalLalu > 0 ? round((int) $r->selesai_lalu / $totalLalu * 100) : null;

                return [
                    'unit' => (string) $r->unit,
                    'saringan' => $dim['saringan'],
                    'total' => $total,
                    'orang' => (int) $r->orang,
                    'selesai' => (int) $r->selesai,
                    'terlambat' => (int) $r->terlambat,
                    'persen' => $persen,
                    'persen_lalu' => $persenLalu,
                    'delta' => $persenLalu === null ? null : $persen - $persenLalu,
                ];
            })->sortBy([['persen', 'asc'], ['terlambat', 'desc']])->values();

            return [
                'dimensi' => $dimensi,
                'label' => $dim['label'],
                'unit' => $unit->take($limit)->values()->all(),
                'jumlah_unit' => $unit->count(),
                'terpotong' => max(0, $unit->count() - $limit),
                'rerata' => $unit->count() ? (int) round($unit->avg('persen')) : 0,
                'basis_ledger' => $this->awalLedger(),
            ];
        });
    }

    /* ================= BAB 3 — KONSENTRASI TUNGGAKAN ================= */

    /**
     * Apakah tunggakan MENYEBAR atau TERPUSAT?
     *
     * Pertanyaan ini menentukan tindakannya, dan tak ada satu pun panel lama yang
     * menjawabnya. "102 penugasan tertunggak" yang dipegang 90 orang menuntut
     * kampanye; angka yang sama yang dipegang 7 orang menuntut tujuh percakapan.
     */
    public function konsentrasi(array $f = [], int $limit = 10): array
    {
        $limit = max(5, min(50, $limit));

        return $this->mungkinCache("sop_ring_konsentrasi_{$limit}", 600, $f, function () use ($f, $limit) {
            $hariIni = Carbon::today();

            $q = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
                ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI);

            if ($this->perluPaket($f)) {
                $q->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket');
                $this->people->terapkanSaringanPaketRingkas($q, $f, 'p');
            }
            $this->people->terapkanSaringanOrang($q, $f, 'd');

            $rows = $q->groupBy('d.Kode_Karyawan')
                ->selectRaw(
                    'd.Kode_Karyawan,
                     COUNT(*) as tunggakan,
                     SUM(CASE WHEN pj.Tipe_Tenggat = \'DEADLINE\' AND pj.Tanggal_Deadline < ? THEN 1 ELSE 0 END) as terlambat,
                     MIN(pj.Tanggal_Deadline) as tenggat_terdekat',
                    [$hariIni],
                )
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->get();

            $total = (int) $rows->sum('tunggakan');
            $orang = $rows->count();

            // Pareto dihitung dari deret TERURUT menurun: berapa orang paling
            // sedikit yang sudah memikul 50% / 80% tunggakan.
            $urut = $rows->pluck('tunggakan')->map(fn ($n) => (int) $n)->sortDesc()->values();
            $kumulatif = 0;
            $orang50 = null;
            $orang80 = null;
            foreach ($urut as $i => $n) {
                $kumulatif += $n;
                if ($orang50 === null && $total > 0 && $kumulatif / $total >= 0.5) {
                    $orang50 = $i + 1;
                }
                if ($orang80 === null && $total > 0 && $kumulatif / $total >= 0.8) {
                    $orang80 = $i + 1;
                    break;
                }
            }

            $atas = $rows->take($limit);
            $kodes = $atas->pluck('Kode_Karyawan')->all();
            $identitas = empty($kodes) ? collect() : DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as v', 'v.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as l', 'l.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                ->whereIn('k.Kode_Karyawan', $kodes)
                ->get(['k.Kode_Karyawan', 'k.Nama', 'v.nama_divisi', 'v.nama_sub_divisi', 'l.nama_jabatan'])
                ->keyBy('Kode_Karyawan');

            $daftar = $atas->map(function ($r) use ($identitas, $total) {
                $id = $identitas->get($r->Kode_Karyawan);

                return [
                    'kode' => $r->Kode_Karyawan,
                    'token' => SopKaryawanToken::encode($r->Kode_Karyawan),
                    'nama' => $id->Nama ?? $r->Kode_Karyawan,
                    'divisi' => $id->nama_divisi ?? null,
                    'sub_divisi' => $id->nama_sub_divisi ?? null,
                    'jabatan' => $id->nama_jabatan ?? null,
                    'tunggakan' => (int) $r->tunggakan,
                    'terlambat' => (int) $r->terlambat,
                    'porsi' => $total > 0 ? round((int) $r->tunggakan / $total * 100, 1) : 0,
                    'tenggat_terdekat' => $r->tenggat_terdekat
                        ? Carbon::parse($r->tenggat_terdekat)->toDateString()
                        : null,
                ];
            })->values()->all();

            return [
                'total_tunggakan' => $total,
                'total_orang' => $orang,
                'rerata' => $orang > 0 ? round($total / $orang, 1) : 0,
                'terbanyak' => (int) ($urut->first() ?? 0),
                'pareto' => [
                    'orang_50' => $orang50,
                    'orang_80' => $orang80,
                    // Porsi tunggakan yang dipikul 20% orang teratas — angka
                    // pembanding klasik terhadap "aturan 80/20".
                    'porsi_20persen_teratas' => $this->porsiTeratas($urut, $total, 0.2),
                    'terpusat' => $orang > 0 && $orang50 !== null && ($orang50 / $orang) <= 0.2,
                ],
                'daftar' => $daftar,
                'terpotong' => max(0, $orang - count($daftar)),
            ];
        });
    }

    /** Berapa persen tunggakan yang dipikul $rasio teratas dari populasi. */
    private function porsiTeratas($urut, int $total, float $rasio): float
    {
        if ($total <= 0 || $urut->isEmpty()) {
            return 0;
        }
        $n = max(1, (int) ceil($urut->count() * $rasio));

        return round($urut->take($n)->sum() / $total * 100, 1);
    }

    /* ================= BAB 3 — TITIK HENTI ================= */

    /**
     * Dokumen yang DIBUKA lalu ditinggalkan — beserta seberapa jauh orang
     * sempat sampai.
     *
     * Sengaja memakai `Persen_Selesai`, bukan `Halaman_Terakhir`: per 20 Agu 2026
     * baru 14 baris progress yang punya halaman terakhir sedangkan 58 punya
     * persen, jadi memakai halaman berarti panel ini kosong padahal datanya ada.
     *
     * Bedanya dengan panel "Dibuka tapi Belum Selesai" yang lama: yang lama
     * berdaftar ORANG, ini berdaftar DOKUMEN. Sepuluh orang berhenti di dokumen
     * yang sama bukan sepuluh masalah — itu satu dokumen yang bermasalah.
     */
    public function titikHenti(array $f = [], int $limit = 10): array
    {
        $limit = max(5, min(50, $limit));

        return $this->mungkinCache("sop_ring_titikhenti_{$limit}", 600, $f, function () use ($f, $limit) {
            $q = DB::table('N_SOP_Progress as pr')
                ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
                ->where('pr.Status', SopStatus::ITEM_DIBUKA)
                ->where('i.Flag_Aktif', SopStatus::AKTIF);

            $this->people->terapkanSaringanPaketRingkas($q, $f, 'p');
            $this->people->terapkanSaringanOrang($q, $f, 'pr');

            $rows = $q->groupBy('i.Id_SOP_Paket_Item', 'i.Judul_Item', 'i.Nomor_SOP_Resmi', 'i.Tipe_Item', 'p.Nama_Paket', 'p.Prioritas')
                ->selectRaw(
                    'i.Id_SOP_Paket_Item,
                     i.Judul_Item,
                     i.Nomor_SOP_Resmi,
                     i.Tipe_Item,
                     p.Nama_Paket,
                     p.Prioritas,
                     COUNT(*) as tertahan,
                     AVG(CAST(ISNULL(pr.Persen_Selesai, 0) AS FLOAT)) as rerata_persen,
                     AVG(CAST(ISNULL(pr.Durasi_Aktif_Detik, 0) AS FLOAT)) as rerata_detik,
                     MAX(DATEDIFF(day, pr.Waktu_Buka, GETDATE())) as umur_terlama,
                     AVG(CAST(DATEDIFF(day, pr.Waktu_Buka, GETDATE()) AS FLOAT)) as rerata_umur',
                )
                ->orderByDesc(DB::raw('COUNT(*)'))
                ->get();

            $daftar = $rows->take($limit)->map(fn ($r) => [
                'id_item' => (int) $r->Id_SOP_Paket_Item,
                'judul' => $r->Judul_Item,
                'nomor' => $r->Nomor_SOP_Resmi,
                'tipe' => $r->Tipe_Item,
                'paket' => $r->Nama_Paket,
                'prioritas' => $r->Prioritas,
                'tertahan' => (int) $r->tertahan,
                'rerata_persen' => (int) round((float) $r->rerata_persen),
                'rerata_detik' => (int) round((float) $r->rerata_detik),
                'umur_terlama' => (int) $r->umur_terlama,
                'rerata_umur' => (int) round((float) $r->rerata_umur),
            ])->values()->all();

            return [
                'daftar' => $daftar,
                'total_tertahan' => (int) $rows->sum('tertahan'),
                'total_dokumen' => $rows->count(),
                'terpotong' => max(0, $rows->count() - count($daftar)),
                'rerata_persen' => $rows->count()
                    ? (int) round($rows->avg(fn ($r) => (float) $r->rerata_persen))
                    : 0,
            ];
        });
    }

    /* ================= BAB 4 — PEMAHAMAN & KUALITAS BACA ================= */

    /**
     * Tiga pertanyaan yang semuanya bermuara pada satu hal: apakah SOP ini
     * benar-benar terserap, atau hanya ter-klik?
     *
     *  baca  → berapa lama orang benar-benar menatapnya
     *  kuis  → apakah isinya terbukti dipahami
     *  versi → apakah yang sudah dibaca itu masih versi yang berlaku
     *
     * Digabung dalam satu endpoint karena ketiganya dibaca bersamaan di satu
     * bab; memisahnya hanya menambah tiga perjalanan jaringan untuk satu layar.
     */
    public function pemahaman(array $f = []): array
    {
        return $this->mungkinCache('sop_ring_pemahaman', 900, $f, function () use ($f) {
            return [
                'baca' => $this->kualitasBaca($f),
                'kuis' => $this->kuis($f),
                'versi' => $this->penyerapanVersi($f),
            ];
        });
    }

    /**
     * Sebaran durasi baca AKTIF (bukan selisih buka→konfirmasi).
     *
     * Panel "Distribusi Waktu Selesai" yang lama mengukur jarak buka→konfirmasi,
     * yang ikut menghitung waktu tab dibiarkan terbuka semalaman. `Durasi_Aktif_Detik`
     * mengukur waktu yang benar-benar dihabiskan di halamannya — dua angka yang
     * bisa berselisih ratusan kali pada baris yang sama.
     */
    private function kualitasBaca(array $f): array
    {
        /* Jendela yang SAMA dengan bacaVsEstimasi() di bawah, dan itu wajib:
           ember durasi dan daftar paket tampil pada satu panel. Memberi
           jendela hanya pada salah satunya membuat keduanya menggambarkan
           populasi yang berbeda — lebih menyesatkan daripada kedua pilihan
           itu sendiri. */
        $q = DB::table('N_SOP_Progress as pr')
            ->where('pr.Durasi_Aktif_Detik', '>', 0)
            ->where('pr.Waktu_Buka', '>=', Carbon::today()->subDays(self::JENDELA_HARI));
        $this->saringProgress($q, $f, 'pr');

        $r = $q->selectRaw(
            'COUNT(*) as total,
             AVG(CAST(pr.Durasi_Aktif_Detik AS FLOAT)) as rerata,
             SUM(CASE WHEN pr.Durasi_Aktif_Detik < 60 THEN 1 ELSE 0 END) as b1,
             SUM(CASE WHEN pr.Durasi_Aktif_Detik >= 60 AND pr.Durasi_Aktif_Detik < 300 THEN 1 ELSE 0 END) as b2,
             SUM(CASE WHEN pr.Durasi_Aktif_Detik >= 300 AND pr.Durasi_Aktif_Detik < 900 THEN 1 ELSE 0 END) as b3,
             SUM(CASE WHEN pr.Durasi_Aktif_Detik >= 900 AND pr.Durasi_Aktif_Detik < 1800 THEN 1 ELSE 0 END) as b4,
             SUM(CASE WHEN pr.Durasi_Aktif_Detik >= 1800 THEN 1 ELSE 0 END) as b5',
        )->first();

        $total = (int) ($r->total ?? 0);

        return [
            'total' => $total,
            'rerata_detik' => (int) round((float) ($r->rerata ?? 0)),
            'sekilas' => (int) ($r->b1 ?? 0),
            'persen_sekilas' => $total > 0 ? round((int) $r->b1 / $total * 100) : 0,
            'kelompok' => [
                ['label' => '< 1 menit', 'jumlah' => (int) ($r->b1 ?? 0), 'waspada' => true],
                ['label' => '1–5 menit', 'jumlah' => (int) ($r->b2 ?? 0), 'waspada' => false],
                ['label' => '5–15 menit', 'jumlah' => (int) ($r->b3 ?? 0), 'waspada' => false],
                ['label' => '15–30 menit', 'jumlah' => (int) ($r->b4 ?? 0), 'waspada' => false],
                ['label' => '> 30 menit', 'jumlah' => (int) ($r->b5 ?? 0), 'waspada' => false],
            ],
            'paket' => $this->bacaVsEstimasi($f),
            /* Dikirim ke layar supaya panel yang menyusut terbaca sebagai
               "90 hari terakhir", bukan sebagai data yang hilang. */
            'jendela_hari' => self::JENDELA_HARI,
        ];
    }

    /**
     * Durasi baca nyata dibanding estimasi yang dipasang admin di master paket.
     *
     * Rasio jauh di bawah 100% berarti estimasinya terlalu tinggi ATAU orang
     * melewatinya — panel ini tak mengaku bisa membedakan keduanya, ia hanya
     * menunjukkan paket mana yang selisihnya paling lebar untuk ditengok.
     */
    private function bacaVsEstimasi(array $f): array
    {
        /* JENDELA 90 HARI — keputusan produk 2026-09-01.
           Panel ini menjawab "estimasi paket mana yang paling meleset SEKARANG",
           jadi ia memang soal perilaku terkini. Tanpa batas waktu ia merata-
           ratakan cara orang membaca versi dokumen yang sudah lama dicabut:
           paket yang sudah direvisi lima kali tetap membawa serta pembacaan
           versi pertamanya, dan angkanya makin lama makin tak bisa
           ditindaklanjuti.

           Sekaligus menghentikan pindaian yang tumbuh selamanya:
           N_SOP_Progress tak pernah menyusut, dan sebelum ini seluruh isinya
           dipindai tiap kali panel dibuka.

           KOLOMNYA Waktu_Buka, BUKAN Updated_At — dan itu bukan selera.
           Diukur pada 2026-09-01: Updated_At NULL pada 1.152 dari 1.156 baris
           (99,7%), sehingga menyaring dengannya akan mengosongkan panel ini
           hampir seluruhnya TANPA satu pun galat. Sebaliknya, seluruh 801 baris
           berdurasi > 0 memiliki Waktu_Buka — nol NULL. */
        $q = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('pr.Durasi_Aktif_Detik', '>', 0)
            ->where('pr.Waktu_Buka', '>=', Carbon::today()->subDays(self::JENDELA_HARI))
            ->where('p.Estimasi_Waktu_Baca_Menit', '>', 0);

        $this->people->terapkanSaringanPaketRingkas($q, $f, 'p');
        $this->people->terapkanSaringanOrang($q, $f, 'pr');

        // Durasi dijumlahkan per (paket, karyawan) dulu — estimasi dipasang untuk
        // SATU PAKET utuh, jadi membandingkannya dengan durasi satu item saja
        // akan selalu terlihat seperti orang membaca 90% lebih cepat.
        $per = DB::query()->fromSub(
            $q->groupBy('p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Estimasi_Waktu_Baca_Menit', 'pr.Kode_Karyawan')
                ->selectRaw('p.Id_SOP_Paket, p.Nama_Paket, p.Estimasi_Waktu_Baca_Menit, pr.Kode_Karyawan,
                             SUM(CAST(pr.Durasi_Aktif_Detik AS FLOAT)) as detik'),
            'x'
        )
            ->groupBy('x.Id_SOP_Paket', 'x.Nama_Paket', 'x.Estimasi_Waktu_Baca_Menit')
            ->selectRaw('x.Nama_Paket, x.Estimasi_Waktu_Baca_Menit, COUNT(*) as pembaca, AVG(x.detik) as rerata_detik')
            ->get();

        return $per->map(function ($r) {
            $estimasi = (int) $r->Estimasi_Waktu_Baca_Menit * 60;
            $nyata = (int) round((float) $r->rerata_detik);

            return [
                'paket' => $r->Nama_Paket,
                'pembaca' => (int) $r->pembaca,
                'estimasi_detik' => $estimasi,
                'nyata_detik' => $nyata,
                'rasio' => $estimasi > 0 ? (int) round($nyata / $estimasi * 100) : 0,
            ];
        })->sortBy('rasio')->values()->all();
    }

    /** Agregat kuis. `cukup_data` false selama percobaannya masih bisa dihitung jari. */
    private function kuis(array $f): array
    {
        $q = DB::table('N_SOP_Progress as pr')->whereNotNull('pr.Skor_Quiz');
        $this->saringProgress($q, $f, 'pr');

        $r = $q->selectRaw(
            'COUNT(*) as percobaan,
             COUNT(DISTINCT pr.Kode_Karyawan) as peserta,
             AVG(CAST(pr.Skor_Quiz AS FLOAT)) as rerata_skor,
             AVG(CAST(ISNULL(pr.Jumlah_Percobaan, 1) AS FLOAT)) as rerata_percobaan,
             SUM(CASE WHEN pr.Status_Lulus = ? THEN 1 ELSE 0 END) as lulus,
             SUM(CASE WHEN pr.Skor_Quiz < 60 THEN 1 ELSE 0 END) as s1,
             SUM(CASE WHEN pr.Skor_Quiz >= 60 AND pr.Skor_Quiz < 70 THEN 1 ELSE 0 END) as s2,
             SUM(CASE WHEN pr.Skor_Quiz >= 70 AND pr.Skor_Quiz < 80 THEN 1 ELSE 0 END) as s3,
             SUM(CASE WHEN pr.Skor_Quiz >= 80 AND pr.Skor_Quiz < 90 THEN 1 ELSE 0 END) as s4,
             SUM(CASE WHEN pr.Skor_Quiz >= 90 THEN 1 ELSE 0 END) as s5',
            [SopStatus::AKTIF],
        )->first();

        $percobaan = (int) ($r->percobaan ?? 0);

        $terendah = [];
        if ($percobaan >= self::MIN_PERCOBAAN_KUIS) {
            $qt = DB::table('N_SOP_Progress as pr')
                ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
                ->whereNotNull('pr.Skor_Quiz');
            $this->people->terapkanSaringanPaketRingkas($qt, $f, 'p');
            $this->people->terapkanSaringanOrang($qt, $f, 'pr');

            $terendah = $qt->groupBy('i.Id_SOP_Paket_Item', 'i.Judul_Item', 'p.Nama_Paket')
                ->selectRaw(
                    'i.Judul_Item, p.Nama_Paket,
                     COUNT(*) as percobaan,
                     AVG(CAST(pr.Skor_Quiz AS FLOAT)) as rerata,
                     SUM(CASE WHEN pr.Status_Lulus = ? THEN 1 ELSE 0 END) as lulus',
                    [SopStatus::AKTIF],
                )
                ->get()
                ->map(fn ($x) => [
                    'judul' => $x->Judul_Item,
                    'paket' => $x->Nama_Paket,
                    'percobaan' => (int) $x->percobaan,
                    'rerata' => (int) round((float) $x->rerata),
                    'persen_lulus' => (int) $x->percobaan > 0
                        ? (int) round((int) $x->lulus / (int) $x->percobaan * 100)
                        : 0,
                ])
                ->sortBy('persen_lulus')->take(8)->values()->all();
        }

        return [
            'cukup_data' => $percobaan >= self::MIN_PERCOBAAN_KUIS,
            'percobaan' => $percobaan,
            'peserta' => (int) ($r->peserta ?? 0),
            'rerata_skor' => (int) round((float) ($r->rerata_skor ?? 0)),
            'rerata_percobaan' => round((float) ($r->rerata_percobaan ?? 0), 1),
            'lulus' => (int) ($r->lulus ?? 0),
            'persen_lulus' => $percobaan > 0 ? (int) round((int) $r->lulus / $percobaan * 100) : 0,
            'sebaran' => [
                ['label' => '< 60', 'jumlah' => (int) ($r->s1 ?? 0), 'waspada' => true],
                ['label' => '60–69', 'jumlah' => (int) ($r->s2 ?? 0), 'waspada' => true],
                ['label' => '70–79', 'jumlah' => (int) ($r->s3 ?? 0), 'waspada' => false],
                ['label' => '80–89', 'jumlah' => (int) ($r->s4 ?? 0), 'waspada' => false],
                ['label' => '90–100', 'jumlah' => (int) ($r->s5 ?? 0), 'waspada' => false],
            ],
            'terendah' => $terendah,
        ];
    }

    /**
     * Penyerapan VERSI AKTIF — panel yang paling dekat dengan alasan modul ini ada.
     *
     * Kepatuhan yang dihitung "sudah dikonfirmasi" tak membedakan konfirmasi
     * terhadap versi yang berlaku dari konfirmasi terhadap versi yang sudah
     * dicabut. Orang yang mengonfirmasi revisi ke-2 sementara yang berlaku sudah
     * revisi ke-4 tetap terhitung patuh — padahal ia bekerja dengan prosedur yang
     * salah. Di sinilah selisih itu dihitung.
     *
     * `tertinggal` = ada konfirmasi, tapi pada versi yang BUKAN versi aktif dan
     * belum ada konfirmasi untuk versi aktifnya.
     */
    private function penyerapanVersi(array $f): array
    {
        $q = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereNotNull('i.Id_Item_Versi_Aktif')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('pr.Status', [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI, SopStatus::ITEM_DIGANTIKAN]);

        $this->people->terapkanSaringanPaketRingkas($q, $f, 'p');
        $this->people->terapkanSaringanOrang($q, $f, 'pr');

        /* Satu baris per (orang, item): apakah dia punya konfirmasi atas versi
           aktif, dan apakah dia punya konfirmasi atas versi mana pun. Dihitung di
           derived table supaya "tertinggal" tidak menghitung ganda orang yang
           punya jejak di beberapa versi sekaligus. */
        $per = DB::query()->fromSub(
            $q->groupBy('p.Id_SOP_Paket', 'p.Nama_Paket', 'i.Id_SOP_Paket_Item', 'i.Judul_Item', 'pr.Kode_Karyawan')
                ->selectRaw(
                    'p.Id_SOP_Paket, p.Nama_Paket, i.Id_SOP_Paket_Item, i.Judul_Item, pr.Kode_Karyawan,
                     MAX(CASE WHEN pr.Id_SOP_Item_Versi = i.Id_Item_Versi_Aktif
                              AND pr.Status IN (?, ?) THEN 1 ELSE 0 END) as pada_versi_aktif,
                     MAX(CASE WHEN pr.Id_SOP_Item_Versi <> i.Id_Item_Versi_Aktif THEN 1 ELSE 0 END) as pada_versi_lama',
                    [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI],
                ),
            'x'
        );

        $ringkas = (clone $per)->selectRaw(
            'COUNT(*) as total,
             SUM(x.pada_versi_aktif) as terserap,
             SUM(CASE WHEN x.pada_versi_aktif = 0 AND x.pada_versi_lama = 1 THEN 1 ELSE 0 END) as tertinggal'
        )->first();

        $total = (int) ($ringkas->total ?? 0);
        $tertinggal = (int) ($ringkas->tertinggal ?? 0);

        $daftar = $tertinggal > 0
            ? (clone $per)->groupBy('x.Id_SOP_Paket_Item', 'x.Judul_Item', 'x.Nama_Paket')
                ->havingRaw('SUM(CASE WHEN x.pada_versi_aktif = 0 AND x.pada_versi_lama = 1 THEN 1 ELSE 0 END) > 0')
                ->selectRaw(
                    'x.Judul_Item, x.Nama_Paket,
                     COUNT(*) as pembaca,
                     SUM(CASE WHEN x.pada_versi_aktif = 0 AND x.pada_versi_lama = 1 THEN 1 ELSE 0 END) as tertinggal'
                )
                ->orderByDesc(DB::raw('SUM(CASE WHEN x.pada_versi_aktif = 0 AND x.pada_versi_lama = 1 THEN 1 ELSE 0 END)'))
                ->limit(8)->get()
                ->map(fn ($r) => [
                    'judul' => $r->Judul_Item,
                    'paket' => $r->Nama_Paket,
                    'pembaca' => (int) $r->pembaca,
                    'tertinggal' => (int) $r->tertinggal,
                ])->all()
            : [];

        return [
            'total' => $total,
            'terserap' => (int) ($ringkas->terserap ?? 0),
            'tertinggal' => $tertinggal,
            'persen_terserap' => $total > 0 ? (int) round((int) $ringkas->terserap / $total * 100) : 0,
            'daftar' => $daftar,
        ];
    }
}
