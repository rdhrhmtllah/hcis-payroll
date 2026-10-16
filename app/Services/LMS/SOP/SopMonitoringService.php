<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopGerbangPaket;
use App\Services\LMS\SOP\Support\SopKaryawanToken;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service Monitoring & Coverage SOP (admin).
 * KPI ringkas + coverage per paket (level karyawan) + drill (per-item rollup & per-karyawan).
 * Sumber: N_SOP_Penjadwalan_Detail (paket-level) & N_SOP_Coverage_Rollup (item-level).
 */
class SopMonitoringService
{
    /**
     * Item yang mewakili sebuah baris rollup (paket x versi) — Fase 4.
     *
     * KENAPA BUKAN `i.Id_SOP_Paket_Item = v.Id_SOP_Paket_Item`.
     * FK itu TUNGGAL: ia menunjuk satu item, yaitu yang kebetulan membuat versi
     * tersebut. Begitu satu dokumen dirujuk beberapa paket, join lewat FK itu
     * tidak menggandakan apa pun — ia mengembalikan item YANG SALAH. Baris
     * rollup paket ini akan menampilkan Judul_Item, Tipe_Item, Urutan, dan
     * Id_SOP_Section milik item di paket LAIN. Salah atribusi jauh lebih sulit
     * terlihat daripada baris ganda.
     *
     * KENAPA MIN(), bukan join biasa. Menautkan lewat (dokumen, paket) sebagai
     * kondisi join biasa akan MENGGANDAKAN baris bila satu paket kelak memuat
     * DUA item yang merujuk dokumen yang sama — dan penggandaan itu langsung
     * menggandakan Total_Target. Karena rollup hanya menyimpan SATU baris angka
     * per (paket, versi), angka itu hanya boleh diatribusikan ke satu item.
     * MIN() memilihnya secara deterministik.
     *
     * TERBUKA UNTUK FASE 6: bila satu paket memang boleh memuat dua item dari
     * dokumen yang sama, tampilan monitoring untuk kasus itu perlu diputuskan
     * tersendiri — angkanya milik dokumen, bukan milik salah satu itemnya.
     *
     * JALUR MUNDUR: versi ber-Id_SOP_Dokumen NULL (18 baris yatim di dev, milik
     * item yang Tipe_Item-nya diubah menjauh dari tipe ber-versi) tak akan
     * pernah cocok lewat perbandingan dokumen — NULL = NULL bernilai NULL, bukan
     * true — sehingga cabang FK lama yang menangkapnya. Tanpa cabang itu,
     * barisnya lenyap dari monitoring.
     */
    private const ITEM_PAKET_DOKUMEN = '(SELECT MIN(i2.Id_SOP_Paket_Item)
          FROM N_SOP_Paket_Item i2
         WHERE i2.Id_SOP_Paket = r.Id_SOP_Paket
           AND i2.Flag_Aktif = \'Y\'
           AND (i2.Id_SOP_Dokumen = v.Id_SOP_Dokumen
                OR i2.Id_SOP_Paket_Item = v.Id_SOP_Paket_Item))';

    /**
     * Versi item yang MASIH BERLAKU, yaitu yang ditunjuk Id_Item_Versi_Aktif.
     *
     * N_SOP_Coverage_Rollup menyimpan satu baris per (paket x versi) dan baris
     * versi lama tak pernah dipensiunkan saat versi baru terbit. Menjumlahkannya
     * begitu saja membuat penyebut membengkak setiap kali paket direvisi —
     * corong bisa melaporkan "20 ditugaskan" untuk paket berisi 5 orang, dan
     * coverage global terlihat makin jatuh tanpa sebab nyata.
     *
     * Dipakai sebagai subquery whereIn supaya tetap satu perjalanan ke DB.
     */
    private function versiBerlakuQuery(): \Closure
    {
        return function ($q) {
            $q->select('Id_Item_Versi_Aktif')
                ->from('N_SOP_Paket_Item')
                ->whereNotNull('Id_Item_Versi_Aktif')
                ->where('Flag_Aktif', SopStatus::AKTIF);
        };
    }

    /**
     * KPI kartu atas.
     *
     * $f = saringan tab Ringkasan (paket, prioritas, divisi, sub divisi,
     * level, jabatan, lokasi). Tanpa saringan, jalurnya persis seperti sebelumnya
     * — termasuk coverage dari tabel rollup yang murah.
     */
    public function summary(array $f = []): array
    {
        $people = app(SopMonitoringPeopleService::class);
        $tersaring = $people->adaSaringan($f);

        $penugasan = function () use ($f, $people) {
            $q = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T');
            $people->terapkanSaringanPaket($q, $f, 'p', 'pj', 'd');
            $people->terapkanSaringanOrang($q, $f, 'd');

            return $q;
        };

        $coverage = $tersaring
            ? $this->coverageTersaring($penugasan())
            : $this->coverageRollup();

        $qPaket = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF);
        $people->terapkanSaringanPaketRingkas($qPaket, $f, 'p');
        // Saringan organisasi memaknai kartu ini sebagai "paket yang relevan bagi
        // orang-orang itu" — paket tanpa satu pun penugasan ke mereka tak dihitung.
        if (! empty(array_filter([$f['dept'] ?? null, $f['divisi'] ?? null, $f['level'] ?? null, $f['jabatan'] ?? null, $f['lokasi'] ?? null]))) {
            $qPaket->whereExists(function ($s) use ($f, $people) {
                $s->select(DB::raw(1))
                    ->from('N_SOP_Penjadwalan_Detail as sd')
                    ->join('N_SOP_Penjadwalan as spj', 'spj.Id_SOP_Penjadwalan', '=', 'sd.Id_SOP_Penjadwalan')
                    ->whereColumn('spj.Id_SOP_Paket', 'p.Id_SOP_Paket')
                    ->where('sd.Flag_Aktif', SopStatus::AKTIF)
                    ->where('sd.Flag_Cabut_Akses', 'T');
                $people->terapkanSaringanOrang($s, $f, 'sd');
            });
        }
        $paketAktif = $qPaket->count();

        $tertunda = $penugasan()
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->distinct()->count('d.Kode_Karyawan');

        $jatuhTempo = $penugasan()
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->where('pj.Tipe_Tenggat', 'DEADLINE')
            ->whereBetween('pj.Tanggal_Deadline', [Carbon::today(), Carbon::today()->addDays(7)])
            ->count();

        // TERLAMBAT: DEADLINE sudah lewat & belum selesai (kewajiban abadi — tetap ditagih)
        $terlambat = $penugasan()
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->where('pj.Tipe_Tenggat', 'DEADLINE')
            // Perbandingan langsung, BUKAN whereDate(): Laravel menghasilkan
            // CAST(col AS DATE) < ? yang non-sargable — indeks pada Tanggal_Deadline
            // jadi tak terpakai. Hasilnya identik karena kolomnya memang tanggal.
            ->where('pj.Tanggal_Deadline', '<', Carbon::today())
            ->count();

        /* POPULASI PENGGUNA, dua angka yang harus satu populasi.
           'Aktif' pada Karyawan dipakai di kedua sisi — termasuk pada sisi
           penugasan — karena angka bawahnya adalah BAGIAN dari angka atasnya.
           Tanpa gerbang yang sama, karyawan nonaktif yang masih memegang
           penugasan membuat "222 dari 958" suatu hari jadi "223 dari 958" yang
           mustahil, dan sub-angka yang melebihi induknya adalah kerusakan yang
           tak bisa dijelaskan kepada siapa pun.

           Predikatnya SENGAJA sama dengan SopJangkauanPublikService, supaya
           dua kartu yang sama-sama menyebut "user" berangkat dari populasi
           yang sama. */
        $qUser = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('k.Aktif', SopStatus::AKTIF);
        $people->terapkanSaringanOrangKaryawan($qUser, $f);
        $userTotal = $qUser->count();

        $userDitugaskan = $penugasan()
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('Karyawan as ak')
                ->whereColumn('ak.Kode_Karyawan', 'd.Kode_Karyawan')
                ->where('ak.Aktif', SopStatus::AKTIF))
            ->distinct()->count('d.Kode_Karyawan');

        return [
            'coverage' => $coverage,
            'paket_aktif' => $paketAktif,
            'tertunda' => $tertunda,
            'jatuh_tempo' => $jatuhTempo,
            'terlambat' => $terlambat,
            'user_total' => $userTotal,
            'user_ditugaskan' => $userDitugaskan,
        ];
    }

    /** Coverage item-level dari rollup — jalur murah, dipakai saat tak ada saringan. */
    private function coverageRollup(): int
    {
        $roll = DB::table('N_SOP_Coverage_Rollup as r')
            ->whereIn('r.Id_SOP_Item_Versi', $this->versiBerlakuQuery())
            ->selectRaw('SUM(r.Total_Target) t, SUM(r.Jumlah_Dikonfirmasi) d')->first();

        return ($roll && $roll->t > 0) ? (int) round($roll->d / $roll->t * 100) : 0;
    }

    /**
     * Coverage item-level yang dihitung ulang dari penugasan tersaring.
     *
     * Rollup adalah ringkasan lintas-orang: begitu saringannya menyangkut divisi
     * atau jabatan, ia tak lagi bisa menjawab. Yang dihitung di sini UKURAN YANG
     * SAMA — item dikonfirmasi dibagi item ditugaskan — supaya angkanya tetap
     * sebanding dengan tampilan tanpa saringan, bukan diam-diam berganti makna
     * jadi coverage tingkat paket.
     */
    private function coverageTersaring($q): int
    {
        $r = $q->join('N_SOP_Paket_Item as ci', function ($j) {
            $j->on('ci.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
                ->where('ci.Flag_Aktif', '=', SopStatus::AKTIF);
        })
            ->leftJoin('N_SOP_Progress as cpr', function ($j) {
                $j->on('cpr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on('cpr.Id_SOP_Paket_Item', '=', 'ci.Id_SOP_Paket_Item');
            })
            ->selectRaw(
                'COUNT(DISTINCT CAST(d.Id_SOP_Penjadwalan_Detail AS VARCHAR(20)) + \':\' + CAST(ci.Id_SOP_Paket_Item AS VARCHAR(20))) as t,
                 COUNT(DISTINCT CASE WHEN cpr.Status IN (?, ?)
                        THEN CAST(d.Id_SOP_Penjadwalan_Detail AS VARCHAR(20)) + \':\' + CAST(ci.Id_SOP_Paket_Item AS VARCHAR(20)) END) as d',
                [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI],
            )->first();

        return ($r && $r->t > 0) ? (int) round($r->d / $r->t * 100) : 0;
    }

    /** Coverage per paket (level karyawan: selesai / total assigned). */
    public function coveragePaket(?string $q = null, array $filters = [], int $page = 1, int $limit = 10): array
    {
        $offset = ($page - 1) * $limit;

        $sub = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as mp', 'mp.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where(fn ($w) => SopGerbangPaket::pasang($w, 'mp', 'pj', 'd'))
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw("pj.Id_SOP_Paket,
                COUNT(*) as total,
                SUM(CASE WHEN d.Status_Paket = 'SELESAI' THEN 1 ELSE 0 END) as selesai,
                SUM(CASE WHEN d.Status_Paket = 'PROGRESS' THEN 1 ELSE 0 END) as progress,
                SUM(CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' AND pj.Tanggal_Deadline < CAST(GETDATE() AS DATE)
                         AND d.Status_Paket <> 'SELESAI' THEN 1 ELSE 0 END) as terlambat");

        $base = DB::table('N_SOP_Master_Paket as p')
            ->leftJoinSub($sub, 'r', 'r.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF);

        if (! empty($q)) {
            $base->where(function ($w) use ($q) {
                $w->where('p.Nama_Paket', 'LIKE', "%{$q}%");
            });
        }

        if (! empty($filters['prioritas'])) {
            $base->where('p.Prioritas', $filters['prioritas']);
        }

        $status = $filters['status'] ?? null;
        if (! empty($status) && $status !== 'all') {
            if ($status === 'selesai') {
                $base->whereRaw('COALESCE(r.total, 0) > 0 AND COALESCE(r.selesai, 0) >= COALESCE(r.total, 0)');
            } elseif ($status === 'terlambat') {
                $base->whereRaw('COALESCE(r.terlambat, 0) > 0');
            } elseif ($status === 'progress') {
                $base->whereRaw('COALESCE(r.total, 0) > 0 AND COALESCE(r.selesai, 0) < COALESCE(r.total, 0) AND COALESCE(r.terlambat, 0) = 0');
            }
        }

        $total = (clone $base)->count();

        $rows = $base->orderByDesc('p.Id_SOP_Paket')->offset($offset)->limit($limit)
            ->get([
                'p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Prioritas',
                DB::raw('COALESCE(r.total,0) as Total_Target'),
                DB::raw('COALESCE(r.selesai,0) as Selesai'),
                DB::raw('COALESCE(r.progress,0) as Progress'),
                DB::raw('COALESCE(r.terlambat,0) as Terlambat'),
            ])
            ->map(function ($p) {
                $p->Belum = max(0, $p->Total_Target - $p->Selesai - $p->Progress);
                $p->Persen = $p->Total_Target > 0 ? round($p->Selesai / $p->Total_Target * 100) : 0;
                $p->Id_SOP_Paket = Hashids::connection('custom')->encode($p->Id_SOP_Paket);

                return $p;
            });

        return [$rows, $total];
    }

    /** Drill: per-item (rollup) + per-karyawan untuk satu paket. */
    /** Drill: per-item (rollup) + per-karyawan + absensi + aktivitas untuk satu paket. */
    public function detailPaket(int $idPaket): array
    {
        $paket = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Id_SOP_Paket', $idPaket)
            ->first([
                'p.Id_SOP_Paket',
                'p.Kode_Paket',
                'p.Nama_Paket',
                'p.Deskripsi',
                'p.Prioritas',
                'p.Status',
                'p.Cover_Path',
                'p.Flag_Absensi',
                'p.Mode_Distribusi',
                'p.Estimasi_Waktu_Baca_Menit',
                'p.Created_At',
                'p.Updated_At',
            ]);

        if ($paket) {
            $paket->Id_SOP_Paket = Hashids::connection('custom')->encode($paket->Id_SOP_Paket);
        }

        // per item/versi: Ambil dari N_SOP_Paket_Item sebagai basis utama agar item tetap muncul walau belum ada rollup
        $items = DB::table('N_SOP_Paket_Item as i')
            ->leftJoin('N_SOP_Paket_Section as s', 's.Id_SOP_Section', '=', 'i.Id_SOP_Section')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->leftJoin('N_SOP_Coverage_Rollup as r', function ($join) use ($idPaket) {
                $join->on('r.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
                    ->where('r.Id_SOP_Paket', '=', $idPaket);
            })
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('i.Urutan')
            ->get([
                'i.Id_SOP_Paket_Item',
                'i.Judul_Item',
                'i.Tipe_Item',
                'i.Nomor_SOP_Resmi',
                'i.Urutan',
                's.Nama_Section',
                DB::raw('COALESCE(v.Versi_Ke, 1) as Versi_Ke'),
                DB::raw('COALESCE(r.Total_Target, 0) as Total_Target'),
                DB::raw('COALESCE(r.Jumlah_Dibuka, 0) as Jumlah_Dibuka'),
                DB::raw('COALESCE(r.Jumlah_Dikonfirmasi, 0) as Jumlah_Dikonfirmasi'),
                DB::raw('COALESCE(r.Persen_Dikonfirmasi, 0) as Persen_Dikonfirmasi'),
            ]);

        // per karyawan
        $karyawan = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as mp', 'mp.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'ky.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'ky.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderBy('d.Status_Paket')
            ->get([
                'd.Id_SOP_Penjadwalan_Detail',
                'd.Kode_Karyawan',
                'ky.Nama',
                'ky.No_Induk_Karyawan',
                'ky.Cabang_Untuk_Gaji as Lokasi',
                'div.nama_sub_divisi as Departemen',
                'div.nama_divisi as Divisi',
                'lvl.nama_jabatan as Jabatan',
                'lvl.nama_level as Level',
                'd.Status_Paket',
                'd.Tanggal_Selesai',
                'pj.Tipe_Tenggat',
                'pj.Tanggal_Deadline',
            ]);

        $totalItem = count($items);
        $selesaiPerKaryawan = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Paket_Item as pi', 'pi.Id_Item_Versi_Aktif', '=', 'pr.Id_SOP_Item_Versi')
            ->where('pi.Id_SOP_Paket', $idPaket)
            ->whereNotNull('pr.Waktu_Konfirmasi')
            ->groupBy('d.Kode_Karyawan')
            ->selectRaw('d.Kode_Karyawan, COUNT(DISTINCT pi.Id_SOP_Paket_Item) as item_selesai')
            ->pluck('item_selesai', 'd.Kode_Karyawan');

        $dibukaPerKaryawan = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Paket_Item as pi', 'pi.Id_Item_Versi_Aktif', '=', 'pr.Id_SOP_Item_Versi')
            ->where('pi.Id_SOP_Paket', $idPaket)
            ->whereNotNull('pr.Waktu_Buka')
            ->groupBy('d.Kode_Karyawan')
            ->selectRaw('d.Kode_Karyawan, COUNT(DISTINCT pi.Id_SOP_Paket_Item) as item_dibuka')
            ->pluck('item_dibuka', 'd.Kode_Karyawan');

        $karyawan = $karyawan->map(function ($k) use ($totalItem, $selesaiPerKaryawan, $dibukaPerKaryawan) {
            $itemSelesai = (int) ($selesaiPerKaryawan[$k->Kode_Karyawan] ?? 0);
            $itemDibuka = (int) ($dibukaPerKaryawan[$k->Kode_Karyawan] ?? 0);
            $k->Total_Item = $totalItem;
            $k->Item_Selesai = $itemSelesai;
            $k->Item_Dibuka = $itemDibuka;
            $k->Status_Penugasan = $k->Status_Paket;
            if ($k->Status_Paket === SopStatus::STATUS_SELESAI || ($totalItem > 0 && $itemSelesai >= $totalItem)) {
                $k->Persen = 100;
                $k->Status_Penugasan = SopStatus::STATUS_SELESAI;
            } elseif ($itemSelesai > 0 || $itemDibuka > 0 || $k->Status_Paket === SopStatus::STATUS_PROGRESS) {
                $k->Persen = $totalItem > 0 ? (int) round(($itemSelesai / $totalItem) * 100) : 50;
                $k->Status_Penugasan = SopStatus::STATUS_PROGRESS;
            } else {
                $k->Persen = 0;
                $k->Status_Penugasan = SopStatus::STATUS_BELUM;
            }
            $k->Is_Terlambat = $k->Tipe_Tenggat === 'DEADLINE'
                && $k->Tanggal_Deadline
                && Carbon::parse($k->Tanggal_Deadline)->lt(Carbon::today())
                && $k->Status_Penugasan !== SopStatus::STATUS_SELESAI;
            $k->Tanggal_Deadline = $k->Tanggal_Deadline
                ? Carbon::parse($k->Tanggal_Deadline)->format('Y-m-d') : null;
            $k->Tanggal_Selesai = $k->Tanggal_Selesai
                ? Carbon::parse($k->Tanggal_Selesai)->format('Y-m-d H:i') : null;

            return $k;
        });

        $karyawan = SopKaryawanToken::sematkan($karyawan);

        // absensi
        $absensiService = app(\App\Services\LMS\SOP\SopAbsensiService::class);
        $absensi = DB::table('N_SOP_Absensi as a')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'a.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'a.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'ky.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'ky.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->orderByDesc('a.Checktime')
            ->get([
                'a.Id_SOP_Absensi',
                'a.Kode_Karyawan',
                'ky.Nama',
                'ky.No_Induk_Karyawan',
                'ky.Cabang_Untuk_Gaji as Lokasi',
                'div.nama_sub_divisi as Departemen',
                'div.nama_divisi as Divisi',
                'lvl.nama_jabatan as Jabatan',
                'a.Checktime',
                'a.Output',
                'a.Latitude',
                'a.Longitude',
                'a.Akurasi_Meter',
                'a.File_Path',
                'a.User_Agent',
                'a.IP_Address',
            ])
            ->map(function ($a) use ($absensiService) {
                $a->Foto_Url = $absensiService->fotoUrl($a->File_Path);
                $a->Checktime = $a->Checktime ? Carbon::parse($a->Checktime)->format('Y-m-d H:i') : null;
                $a->Lokasi_Meragukan = $a->Akurasi_Meter !== null && $a->Akurasi_Meter > 50;

                return $a;
            });

        // aktivitas log audit
        $aktivitas = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->leftJoin('N_SOP_Paket_Item as pi', 'pi.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'ky.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where(function ($q) {
                $q->whereNotNull('pr.Waktu_Buka')
                    ->orWhereNotNull('pr.Waktu_Konfirmasi');
            })
            ->orderByDesc(DB::raw('COALESCE(pr.Waktu_Konfirmasi, pr.Waktu_Buka)'))
            ->limit(100)
            ->get([
                'pr.Id_SOP_Progress',
                'd.Kode_Karyawan',
                'ky.Nama',
                'ky.No_Induk_Karyawan',
                'div.nama_sub_divisi as Departemen',
                'div.nama_divisi as Divisi',
                'pi.Judul_Item',
                'pi.Tipe_Item',
                'pr.Waktu_Buka',
                'pr.Waktu_Konfirmasi',
                'pr.Durasi_Aktif_Detik',
                'pr.Skor_Quiz',
                'pr.Jumlah_Percobaan',
                'pr.Halaman_Terakhir',
                'pr.Konfirmasi_IP',
            ])
            ->map(function ($act) {
                $act->Waktu_Buka = $act->Waktu_Buka ? Carbon::parse($act->Waktu_Buka)->format('Y-m-d H:i') : null;
                $act->Waktu_Konfirmasi = $act->Waktu_Konfirmasi ? Carbon::parse($act->Waktu_Konfirmasi)->format('Y-m-d H:i') : null;
                $act->Status = $act->Waktu_Konfirmasi ? 'SELESAI' : 'DIBUKA';
                $act->Durasi_Detik = (int) ($act->Durasi_Aktif_Detik ?? 0);
                $act->Skor_Kuis = $act->Skor_Quiz;
                $act->Total_Percobaan = (int) ($act->Jumlah_Percobaan ?? 0);
                $act->IP_Address = $act->Konfirmasi_IP;

                return $act;
            });

        $sections = $this->sectionCoverage($idPaket);
        $funnel = $this->funnelPaket($idPaket);

        $totalTarget = count($karyawan);
        $totalSelesai = $karyawan->where('Status_Paket', SopStatus::STATUS_SELESAI)->count();
        $totalTerlambat = $karyawan->where('Is_Terlambat', true)->count();
        $totalProgress = max(0, $totalTarget - $totalSelesai - $totalTerlambat);
        $persenKepatuhan = $totalTarget > 0 ? (int) round(($totalSelesai / $totalTarget) * 100) : 0;

        $distribusiUnit = $karyawan->groupBy(fn ($k) => $k->Departemen ?: ($k->Divisi ?: 'Umum'))
            ->map(function ($group, $unitName) {
                $tot = count($group);
                $sel = $group->where('Status_Paket', SopStatus::STATUS_SELESAI)->count();
                $tel = $group->where('Is_Terlambat', true)->count();

                return [
                    'unit' => $unitName,
                    'total' => $tot,
                    'selesai' => $sel,
                    'terlambat' => $tel,
                    'persen' => $tot > 0 ? (int) round(($sel / $tot) * 100) : 0,
                ];
            })
            ->values();

        return [
            'paket' => $paket,
            'items' => $items,
            'sections' => $sections,
            'funnel' => $funnel,
            'karyawan' => $karyawan,
            'absensi' => $absensi,
            'aktivitas' => $aktivitas,
            'distribusi_unit' => $distribusiUnit,
            'kpi' => [
                'total_target' => $totalTarget,
                'selesai' => $totalSelesai,
                'progress' => $totalProgress,
                'terlambat' => $totalTerlambat,
                'persen' => $persenKepatuhan,
            ],
        ];
    }

    /**
     * Coverage per section — drill paket selama ini hanya per item, padahal admin
     * berpikir dalam satuan bagian ("Bagian Keselamatan belum jalan"), bukan item.
     */
    public function sectionCoverage(int $idPaket): \Illuminate\Support\Collection
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->leftJoin('N_SOP_Paket_Section as s', 's.Id_SOP_Section', '=', 'i.Id_SOP_Section')
            ->leftJoin('N_SOP_Coverage_Rollup as r', function ($join) use ($idPaket) {
                $join->on('r.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
                    ->where('r.Id_SOP_Paket', '=', $idPaket);
            })
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('s.Id_SOP_Section', 's.Nama_Section', 's.Urutan')
            ->selectRaw(
                "ISNULL(s.Nama_Section, 'Tanpa Bagian') as Nama_Section, s.Urutan,
                 COUNT(i.Id_SOP_Paket_Item) as Jumlah_Item,
                 ISNULL(SUM(r.Total_Target), 0) as Total_Target,
                 ISNULL(SUM(r.Jumlah_Dibuka), 0) as Jumlah_Dibuka,
                 ISNULL(SUM(r.Jumlah_Dikonfirmasi), 0) as Jumlah_Dikonfirmasi"
            )
            ->orderByRaw('ISNULL(s.Urutan, 9999)')
            ->get()
            ->map(function ($r) {
                $t = (int) $r->Total_Target;
                $r->Persen_Dibuka = $t > 0 ? (int) round(((int) $r->Jumlah_Dibuka / $t) * 100) : 0;
                $r->Persen_Dikonfirmasi = $t > 0 ? (int) round(((int) $r->Jumlah_Dikonfirmasi / $t) * 100) : 0;

                return $r;
            });
    }

    /**
     * Corong Ditugaskan → Dibuka → Dikonfirmasi.
     * `Jumlah_Dibuka` sudah lama tersedia di rollup tapi tak pernah dipakai;
     * selisih dibuka−dikonfirmasi itulah kebocoran yang selama ini tak terlihat.
     */
    public function funnelPaket(int $idPaket): array
    {
        /* "Sudah dibuka" dibawa lewat LEFT JOIN, bukan EXISTS di dalam SUM.
           SQL Server menolak agregat atas ekspresi yang memuat subquery —
           "Cannot perform an aggregate function on an expression containing an
           aggregate or a subquery" — sehingga bentuk sebelumnya TIDAK PERNAH
           berhasil dijalankan sekali pun; endpoint /paket/{id}/funnel selalu
           membalas galat.

           DISTINCT pada tabel turunan itu wajib: satu penugasan punya banyak
           baris progres (satu per item), dan tanpa DISTINCT join-nya
           menggandakan baris sehingga COUNT(*) melaporkan target yang lebih
           besar daripada jumlah orang yang ditugaskan. */
        $sudahDibuka = DB::raw(
            '(SELECT DISTINCT Id_SOP_Penjadwalan_Detail FROM N_SOP_Progress WHERE Waktu_Buka IS NOT NULL) as pb'
        );

        $r = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->leftJoin($sudahDibuka, 'pb.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->selectRaw("
                COUNT(*) as target,
                SUM(CASE WHEN d.Status_Paket IN ('PROGRESS', 'SELESAI')
                          OR pb.Id_SOP_Penjadwalan_Detail IS NOT NULL
                         THEN 1 ELSE 0 END) as dibuka,
                SUM(CASE WHEN d.Status_Paket = 'SELESAI' THEN 1 ELSE 0 END) as konfirmasi
            ")
            ->first();

        $target = (int) ($r->target ?? 0);
        $dibuka = (int) ($r->dibuka ?? 0);
        $konf = (int) ($r->konfirmasi ?? 0);

        return [
            'target' => $target,
            'dibuka' => $dibuka,
            'dikonfirmasi' => $konf,
            'belum_dibuka' => max(0, $target - $dibuka),
            'dibuka_belum_konf' => max(0, $dibuka - $konf),
            'persen_dibuka' => $target > 0 ? (int) round(($dibuka / $target) * 100) : 0,
            'persen_konfirmasi' => $target > 0 ? (int) round(($konf / $target) * 100) : 0,
        ];
    }

    /**
     * Kuis anomali: jatah percobaan habis tetapi status item belum difinalkan.
     *
     * Alur normal memfinalkan item saat jatah habis, termasuk bila hasilnya tidak
     * lulus. Daftar ini menjadi jaring pengaman untuk data lama atau callback yang
     * terputus sebelum proses finalisasi selesai.
     */
    public function kuisTertahan(?string $q = null): \Illuminate\Support\Collection
    {
        $maks = SopKuisDispatchService::MAKS_PERCOBAAN;

        $rows = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->leftJoin('Karyawan as ky', 'ky.Kode_Karyawan', '=', 'pr.Kode_Karyawan')
            ->where('i.Tipe_Item', 'KUIS')
            ->where('pr.Jumlah_Percobaan', '>=', $maks)
            ->whereNotIn('pr.Status', [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI]);

        if (! empty($q)) {
            $rows->where(function ($w) use ($q) {
                $w->where('pr.Kode_Karyawan', 'LIKE', "%{$q}%")
                    ->orWhere('ky.Nama', 'LIKE', "%{$q}%")
                    ->orWhere('p.Nama_Paket', 'LIKE', "%{$q}%");
            });
        }

        return $rows->orderByDesc('pr.Updated_At')
            ->limit(200)
            ->get([
                'pr.Id_SOP_Progress', 'pr.Kode_Karyawan', 'ky.Nama', 'pr.Skor_Quiz',
                'pr.Jumlah_Percobaan', 'pr.Status_Lulus', 'pr.Updated_At',
                'i.Judul_Item', 'i.Nilai_Minimum', 'p.Nama_Paket',
            ])
            ->map(function ($r) use ($maks) {
                $r->Id_SOP_Progress = Hashids::connection('custom')->encode($r->Id_SOP_Progress);
                $r->Maks_Percobaan = $maks;
                $r->Updated_At = $r->Updated_At ? Carbon::parse($r->Updated_At)->format('Y-m-d H:i') : null;

                return $r;
            });
    }

    /**
     * Kembalikan jatah percobaan kuis seorang karyawan.
     *
     * HANYA Jumlah_Percobaan yang dinolkan. Id_Ujian_Token_Hasil SENGAJA
     * dipertahankan: kolom itu dipakai SopKuisDispatchService::launch() untuk
     * mengenali bahwa sesi lama sudah dikerjakan, sehingga percobaan berikutnya
     * mencetak sesi HC Learn BARU. Kalau ikut dikosongkan, peserta justru
     * dilempar kembali ke halaman ujian lama yang sudah tertutup.
     *
     * Skor lama juga dibiarkan — sampai hasil baru masuk, nilai itu tetap
     * catatan sah dari percobaan yang sudah terjadi.
     *
     * @return array{ok:bool, message:string}
     */
    public function resetPercobaanKuis(int $idProgress, ?int $userId): array
    {
        $prog = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->where('pr.Id_SOP_Progress', $idProgress)
            ->first([
                'pr.Id_SOP_Progress', 'pr.Kode_Karyawan', 'pr.Status', 'pr.Jumlah_Percobaan',
                'pr.Skor_Quiz', 'pr.Id_SOP_Paket_Item', 'pr.Id_SOP_Item_Versi',
                'i.Tipe_Item', 'i.Id_SOP_Paket', 'i.Judul_Item',
            ]);

        if (! $prog) {
            return ['ok' => false, 'message' => 'Data progres tidak ditemukan.'];
        }
        if ($prog->Tipe_Item !== 'KUIS') {
            return ['ok' => false, 'message' => 'Item ini bukan kuis.'];
        }
        if (in_array($prog->Status, [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI], true)) {
            return ['ok' => false, 'message' => 'Kuis ini sudah lulus — tak perlu dibuka ulang.'];
        }
        if ((int) $prog->Jumlah_Percobaan === 0) {
            return ['ok' => false, 'message' => 'Jatah percobaan masih utuh.'];
        }

        DB::transaction(function () use ($prog, $userId, $idProgress) {
            DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $idProgress)->update([
                'Jumlah_Percobaan' => 0,
                'Updated_At' => now(),
            ]);

            // Lewat SopLog supaya oleh_id_users terpromosi jadi kolom berindeks;
            // INSERT langsung meninggalkannya NULL dan kolom "oleh" terbaca "Sistem".
            SopLog::catat(
                'KUIS_RESET',
                'Jatah percobaan kuis "'.$prog->Judul_Item.'" dikembalikan oleh admin.',
                (int) $prog->Id_SOP_Paket,
                (int) $prog->Id_SOP_Paket_Item,
                $prog->Id_SOP_Item_Versi ? (int) $prog->Id_SOP_Item_Versi : null,
                $prog->Kode_Karyawan,
                [
                    'id_sop_progress' => $idProgress,
                    'percobaan_sebelum' => (int) $prog->Jumlah_Percobaan,
                    'skor_saat_reset' => $prog->Skor_Quiz !== null ? (float) $prog->Skor_Quiz : null,
                    'oleh_id_users' => $userId,
                ],
            );
        });

        return ['ok' => true, 'message' => 'Jatah percobaan dikembalikan. Karyawan bisa mengerjakan ulang.'];
    }
}
