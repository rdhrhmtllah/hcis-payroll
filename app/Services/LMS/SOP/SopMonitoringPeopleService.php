<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopGerbangPaket;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Monitoring SOP — sudut pandang KARYAWAN (padanan daftar karyawan di monitoring LMS).
 *
 * Prinsip yang dipegang di sini:
 *  1. Gerbang penugasan selalu `Flag_Aktif='Y' AND Flag_Cabut_Akses='T'`.
 *     `Flag_Cabut_Akses='T'` artinya BELUM dicabut — menulis 'Y' karena intuisi
 *     menghasilkan angka yang diam-diam berbeda dari coverage table.
 *  2. Saringan status ditegakkan lewat EXISTS/NOT EXISTS, jadi berlaku SEBELUM
 *     paginasi. Monitoring LMS menyaring koleksi yang sudah dipaginasi
 *     (LmsMonitoringController:281-294) sehingga total_data-nya berbohong.
 *  3. Agregat per-halaman satu query ber-GROUP BY atas kode halaman itu saja —
 *     bukan satu query per baris seperti exportEmployeeTrainingSummary() di LMS.
 *  4. Tanpa whereDate(): Laravel menghasilkan CAST(col AS DATE) yang non-sargable.
 */
class SopMonitoringPeopleService
{
    /** Status yang bisa disaring dari UI. */
    public const STATUS = ['all', 'belum', 'progress', 'selesai', 'terlambat'];

    /* ================= OPSI FILTER ================= */

    /**
     * Nilai distinct untuk dropdown, dibatasi karyawan yang PUNYA penugasan SOP aktif —
     * daftar penuh master data akan memuat ratusan nilai yang nihil hasilnya.
     */
    public function filterOptions(): array
    {
        return Cache::store('database')->remember('sop_mon_filteropts', 1800, function () {
            $base = fn () => DB::table('Karyawan as k')->whereExists(fn ($q) => $this->gate($q, 'k'));

            $div = fn (string $col) => $base()
                ->join('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->whereNotNull("div.{$col}")->where("div.{$col}", '<>', '')
                ->distinct()->orderBy("div.{$col}")->pluck("div.{$col}");

            $lvl = fn (string $col) => $base()
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->whereNotNull("lvl.{$col}")->where("lvl.{$col}", '<>', '')
                ->distinct()->orderBy("lvl.{$col}")->pluck("lvl.{$col}");

            return [
                'departemen' => $div('nama_sub_divisi'),
                'divisi' => $div('nama_divisi'),
                'level' => $lvl('nama_level'),
                'jabatan' => $lvl('nama_jabatan'),
                'lokasi' => $base()->whereNotNull('k.Cabang_Untuk_Gaji')->where('k.Cabang_Untuk_Gaji', '<>', '')
                    ->distinct()->orderBy('k.Cabang_Untuk_Gaji')->pluck('k.Cabang_Untuk_Gaji'),
                'paket' => DB::table('N_SOP_Master_Paket')->where('Flag_Aktif', SopStatus::AKTIF)
                    ->where('Status', SopStatus::PAKET_PUBLISHED)->whereNotNull('Nama_Paket')
                    ->distinct()->orderBy('Nama_Paket')->pluck('Nama_Paket'),
                // Daftar saringan ikut master; tingkat baru langsung bisa dipakai menyaring.
                'prioritas' => app(SopPrioritasService::class)->semua()->pluck('Kode')->all(),
                'status' => self::STATUS,
            ];
        });
    }

    /* ================= DAFTAR KARYAWAN ================= */

    /**
     * @param  array  $f  q, status, dept[], divisi[], level[], jabatan[], lokasi[],
     *                    prioritas, paket(hashid), date_mode, date_start,
     *                    date_end, sort
     * @return array{0:\Illuminate\Support\Collection,1:int,2:array} [rows, total, summary]
     */
    public function employees(array $f, int $page, int $limit): array
    {
        $base = $this->baseKaryawan($f);
        $total = (clone $base)->count('k.Kode_Karyawan');

        $rows = (clone $base)
            ->orderBy('k.Nama')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get([
                'k.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan',
                'div.nama_sub_divisi as Departemen', 'div.nama_divisi as Divisi',
                'lvl.nama_jabatan as Jabatan', 'k.Cabang_Untuk_Gaji as Lokasi',
            ]);

        $kodes = $rows->pluck('Kode_Karyawan')->all();

        return [$this->lekatkanAgregat($rows, $kodes, $f), $total, $this->summary($f)];
    }

    /** KPI global — dihitung terpisah, bukan penjumlahan halaman yang sedang tampil. */
    public function summary(array $f): array
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF);

        $this->terapkanSaringanPaket($q, $f, 'p', 'pj', 'd');
        $this->terapkanSaringanOrang($q, $f, 'd');

        $r = (clone $q)->selectRaw(
            'COUNT(DISTINCT d.Kode_Karyawan) as total_karyawan,
             SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as total_selesai,
             SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as total_progress,
             SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as total_belum,
             COUNT(*) as total_penugasan',
            [SopStatus::STATUS_SELESAI, SopStatus::STATUS_PROGRESS, SopStatus::STATUS_BELUM],
        )->first();

        $terlambat = (clone $q)
            ->where('pj.Tipe_Tenggat', 'DEADLINE')
            ->whereNotNull('pj.Tanggal_Deadline')
            ->where('pj.Tanggal_Deadline', '<', Carbon::today())
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->count();

        $penugasan = (int) ($r->total_penugasan ?? 0);

        return [
            'total_karyawan' => (int) ($r->total_karyawan ?? 0),
            'total_penugasan' => $penugasan,
            'total_selesai' => (int) ($r->total_selesai ?? 0),
            'total_progress' => (int) ($r->total_progress ?? 0),
            'total_belum' => (int) ($r->total_belum ?? 0),
            'total_terlambat' => $terlambat,
            'coverage' => $penugasan > 0 ? (int) round(((int) $r->total_selesai / $penugasan) * 100) : 0,
        ];
    }

    /** Ringkasan satu karyawan untuk halaman deep-dive. */
    public function employeeRingkas(string $kode): ?array
    {
        $k = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('k.Kode_Karyawan', $kode)
            ->first([
                'k.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan', 'k.Cabang_Untuk_Gaji as Lokasi',
                'div.nama_sub_divisi as Departemen', 'div.nama_divisi as Divisi',
                'lvl.nama_jabatan as Jabatan', 'lvl.nama_level as Level',
            ]);
        if (! $k) {
            return null;
        }

        $rows = $this->lekatkanAgregat(collect([$k]), [$kode], []);

        return ['karyawan' => $rows->first()];
    }

    /**
     * Rekam baca per ITEM — lapisan yang tak tersedia di mana pun sebelumnya.
     *
     * Halaman per-karyawan selama ini hanya menampilkan tingkat paket dan
     * tingkat ledger; seluruh detail faktual justru tersimpan di N_SOP_Progress
     * (durasi aktif, waktu buka/konfirmasi, halaman terakhir, versi yang
     * dihadapi, skor kuis, jejak atestasi) dan tak pernah dibaca UI.
     *
     * Yang dikembalikan sengaja fakta apa adanya: hitungan, jumlah, dan stempel
     * waktu. Tanpa skor gabungan, tanpa pembanding, tanpa label penilaian —
     * kesimpulan adalah pekerjaan admin, bukan pekerjaan endpoint ini.
     *
     * Komposisi dan jejak versi diturunkan di PHP dari satu tarikan baris,
     * bukan query tambahan per kelompok.
     *
     * @return array{item:array, komposisi:array, ringkas:array, jejak_versi:array}
     */
    public function rekamBaca(string $kode): array
    {
        $rows = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->leftJoin('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->leftJoin('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'pr.Id_SOP_Item_Versi')
            ->where('d.Kode_Karyawan', $kode)
            ->orderBy('p.Nama_Paket')
            ->orderBy('i.Urutan')
            ->orderBy('v.Versi_Ke')
            ->get([
                'pr.Id_SOP_Paket_Item', 'pr.Id_SOP_Item_Versi', 'pr.Tipe_Item', 'pr.Status',
                'pr.Halaman_Terakhir', 'pr.Persen_Selesai', 'pr.Durasi_Aktif_Detik',
                'pr.Waktu_Buka', 'pr.Waktu_Konfirmasi',
                'pr.Skor_Quiz', 'pr.Jumlah_Percobaan', 'pr.Status_Lulus',
                'pr.Konfirmasi_IP', 'pr.Konfirmasi_User_Agent',
                'i.Judul_Item', 'i.Nomor_SOP_Resmi', 'p.Id_SOP_Paket', 'p.Nama_Paket', 'v.Versi_Ke',
                // Jejak pengesahan versi. Pindah ke sini 20 Agu 2026 saat bagian
                // "Bukti Konfirmasi" dilepas: ini SATU-SATUNYA kolom yang dulu
                // hanya ada di sana dan tak punya padanan di rekam baca.
                'v.Nomor_Pengesahan', 'v.Disahkan_Oleh', 'v.Tanggal_Pengesahan',
            ]);

        $waktu = fn ($t) => $t ? Carbon::parse($t)->format('Y-m-d H:i') : null;

        $item = $rows->map(fn ($r) => [
            'Judul_Item' => $r->Judul_Item,
            'Nomor_SOP_Resmi' => $r->Nomor_SOP_Resmi,
            // Identitas paket ikut dikirim supaya pengelompokan di UI tak
            // bersandar pada nama — dua paket bisa bernama sama.
            'Id_SOP_Paket' => $r->Id_SOP_Paket ? Hashids::connection('custom')->encode($r->Id_SOP_Paket) : null,
            'Nama_Paket' => $r->Nama_Paket,
            'Tipe_Item' => $r->Tipe_Item,
            'Status' => $r->Status,
            'Versi_Ke' => $r->Versi_Ke !== null ? (int) $r->Versi_Ke : null,
            'Waktu_Buka' => $waktu($r->Waktu_Buka),
            'Waktu_Konfirmasi' => $waktu($r->Waktu_Konfirmasi),
            'Durasi_Aktif_Detik' => (int) ($r->Durasi_Aktif_Detik ?? 0),
            'Halaman_Terakhir' => $r->Halaman_Terakhir !== null ? (int) $r->Halaman_Terakhir : null,
            'Persen_Selesai' => $r->Persen_Selesai !== null ? (float) $r->Persen_Selesai : null,
            'Skor_Quiz' => $r->Skor_Quiz !== null ? (float) $r->Skor_Quiz : null,
            'Jumlah_Percobaan' => (int) ($r->Jumlah_Percobaan ?? 0),
            'Status_Lulus' => $r->Status_Lulus,
            'Konfirmasi_IP' => $r->Konfirmasi_IP,
            'Konfirmasi_User_Agent' => $r->Konfirmasi_User_Agent,
            'Nomor_Pengesahan' => $r->Nomor_Pengesahan,
            'Disahkan_Oleh' => $r->Disahkan_Oleh,
            'Tanggal_Pengesahan' => $r->Tanggal_Pengesahan
                ? Carbon::parse($r->Tanggal_Pengesahan)->format('Y-m-d')
                : null,
        ])->all();

        // Terbanyak dulu, lalu label sebagai pemecah seri: urutan yang stabil
        // antar-pemuatan supaya chip komposisi tak berpindah-pindah tempat.
        $hitung = fn (string $kunci) => $rows
            ->groupBy($kunci)
            ->map(fn ($g, $label) => ['label' => (string) $label, 'jumlah' => $g->count()])
            ->sortBy([['jumlah', 'desc'], ['label', 'asc']])
            ->values()
            ->all();

        // Item yang versinya lebih dari satu: menjelaskan asal status DIGANTIKAN
        // tanpa menyimpulkan apa pun tentang orangnya.
        $jejak = $rows
            ->groupBy('Id_SOP_Paket_Item')
            ->filter(fn ($g) => $g->pluck('Id_SOP_Item_Versi')->filter()->unique()->count() > 1)
            ->map(fn ($g) => [
                'Judul_Item' => $g->first()->Judul_Item,
                'Nama_Paket' => $g->first()->Nama_Paket,
                'versi' => $g->sortBy('Versi_Ke')->map(fn ($r) => [
                    'Versi_Ke' => $r->Versi_Ke !== null ? (int) $r->Versi_Ke : null,
                    'Status' => $r->Status,
                    'Waktu' => $waktu($r->Waktu_Konfirmasi ?: $r->Waktu_Buka),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'item' => $item,
            'komposisi' => ['status' => $hitung('Status'), 'tipe' => $hitung('Tipe_Item')],
            'ringkas' => [
                'total_rekam' => $rows->count(),
                'total_detik' => (int) $rows->sum(fn ($r) => (int) ($r->Durasi_Aktif_Detik ?? 0)),
                'item_unik' => $rows->pluck('Id_SOP_Paket_Item')->filter()->unique()->count(),
                'versi_unik' => $rows->pluck('Id_SOP_Item_Versi')->filter()->unique()->count(),
                'pernah_dibuka' => $rows->filter(fn ($r) => $r->Waktu_Buka !== null)->count(),
                'dikonfirmasi' => $rows->filter(fn ($r) => $r->Waktu_Konfirmasi !== null)->count(),
            ],
            'jejak_versi' => $jejak,
        ];
    }

    /**
     * Paket milik satu karyawan DI DALAM saringan yang sedang berlaku.
     *
     * Ini bukan duplikat tugasSaya(). tugasSaya() menjawab "apa saja tugas orang
     * ini" dan tak mengenal saringan; method ini menjawab "bagian mana dari tugas
     * orang ini yang akan ikut terekspor". Studio export memakai yang kedua:
     * angka ringkas di kepala kartu dihitung dengan saringan, jadi daftar di
     * bawahnya harus dihitung dengan saringan yang sama. Memakai tugasSaya() di
     * sana membuat kepala kartu menyebut 1 SOP sementara isinya memperlihatkan 4.
     *
     * COUNT(DISTINCT item) — bukan COUNT(*) — karena satu item bisa punya lebih
     * dari satu baris progress bila versinya pernah berganti.
     *
     * @return array<int,array<string,mixed>>
     */
    public function paketTersaring(string $kode, array $f): array
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->leftJoin('N_SOP_Paket_Item as i', function ($j) {
                $j->on('i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
                    ->where('i.Flag_Aktif', '=', SopStatus::AKTIF);
            })
            ->leftJoin('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item');
            })
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF);

        // Saringan yang sama dengan yang dipakai lekatkanAgregat() — itulah
        // sumber angka di kepala kartu.
        $this->terapkanSaringanPaket($q, $f, 'p', 'pj', 'd');

        $hariIni = Carbon::today();

        $rows = $q->groupBy(
            'd.Id_SOP_Penjadwalan_Detail', 'p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Prioritas',
            'd.Status_Paket', 'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',
        )->selectRaw(
            'd.Id_SOP_Penjadwalan_Detail as did, p.Id_SOP_Paket as pid, p.Nama_Paket, p.Prioritas,
             d.Status_Paket, pj.Tipe_Tenggat, pj.Tanggal_Deadline,
             COUNT(DISTINCT i.Id_SOP_Paket_Item) as total_item,
             COUNT(DISTINCT CASE WHEN pr.Status IN (?, ?) THEN i.Id_SOP_Paket_Item END) as item_selesai,
             COUNT(DISTINCT CASE WHEN pr.Status IN (?, ?)
                                  AND pr.Id_SOP_Item_Versi IS NOT NULL
                                  AND i.Id_Item_Versi_Aktif IS NOT NULL
                                  AND pr.Id_SOP_Item_Versi <> i.Id_Item_Versi_Aktif
                                 THEN i.Id_SOP_Paket_Item END) as item_perlu_ulang',
            [
                SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI,
                SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI,
            ],
        )->orderBy('p.Nama_Paket')->get();

        return $rows->map(function ($r) use ($hariIni) {
            $total = (int) $r->total_item;
            $selesai = (int) $r->item_selesai;

            return [
                'Id_SOP_Paket' => Hashids::connection('custom')->encode($r->pid),
                'Nama_Paket' => $r->Nama_Paket,
                'Prioritas' => $r->Prioritas,
                'Status_Paket' => $r->Status_Paket,
                'Total_Item' => $total,
                'Item_Selesai' => $selesai,
                'Item_Perlu_Ulang' => (int) $r->item_perlu_ulang,
                'Persen' => $total > 0 ? (int) round(($selesai / $total) * 100) : 0,
                'Tenggat' => $r->Tipe_Tenggat === 'DEADLINE' && $r->Tanggal_Deadline
                    ? Carbon::parse($r->Tanggal_Deadline)->format('Y-m-d') : null,
                'Is_Terlambat' => $r->Tipe_Tenggat === 'DEADLINE'
                    && $r->Tanggal_Deadline
                    && Carbon::parse($r->Tanggal_Deadline)->lt($hariIni)
                    && $r->Status_Paket !== SopStatus::STATUS_SELESAI,
            ];
        })->all();
    }

    /** Berapa karyawan yang akan ikut terekspor dengan saringan ini (untuk estimasi). */
    public function selectionCount(array $f): int
    {
        return $this->baseKaryawan($f)->count('k.Kode_Karyawan');
    }

    /**
     * Kode karyawan SELURUH hasil saringan — dipakai aksi massal dari kartu
     * antrean ("Ingatkan semua yang terlambat") supaya admin tak perlu memilih
     * satu per satu lintas halaman lebih dulu.
     *
     * Dibatasi: pengingat massal adalah aksi yang mengirim notifikasi ke orang
     * sungguhan, jadi pagarnya harus terlihat oleh pemanggil — bukan diam-diam
     * memotong daftar.
     */
    public function selectionKodes(array $f, int $maks = 500): array
    {
        return $this->baseKaryawan($f)
            ->orderBy('k.Nama')->limit($maks)
            ->pluck('k.Kode_Karyawan')->all();
    }

    /**
     * Bukti atestasi: item yang sudah dikonfirmasi + versi, pengesahan, waktu, IP, perangkat.
     * Hanya permukaan admin — jangan dipakai di route karyawan.
     */
    public function atestasi(string $kode, ?int $idPaket = null): \Illuminate\Support\Collection
    {
        $q = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'pr.Id_SOP_Item_Versi')
            ->where('pr.Kode_Karyawan', $kode)
            ->whereIn('pr.Status', [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI])
            ->whereNotNull('pr.Waktu_Konfirmasi');

        if ($idPaket) {
            $q->where('p.Id_SOP_Paket', $idPaket);
        }

        return $q->orderByDesc('pr.Waktu_Konfirmasi')->limit(500)->get([
            'p.Nama_Paket', 'p.Kode_Paket', 'i.Judul_Item', 'i.Tipe_Item', 'i.Nomor_SOP_Resmi',
            'v.Versi_Ke', 'v.Nomor_Pengesahan', 'v.Disahkan_Oleh', 'v.Tanggal_Pengesahan',
            'pr.Waktu_Buka', 'pr.Waktu_Konfirmasi', 'pr.Konfirmasi_IP', 'pr.Konfirmasi_User_Agent',
            'pr.Skor_Quiz', 'pr.Status_Lulus',
        ])->map(function ($r) {
            $r->Durasi_Detik = ($r->Waktu_Buka && $r->Waktu_Konfirmasi)
                ? Carbon::parse($r->Waktu_Buka)->diffInSeconds(Carbon::parse($r->Waktu_Konfirmasi))
                : null;

            return $r;
        });
    }

    /* ================= INTERNAL ================= */

    /** Gerbang "punya penugasan SOP aktif" — dipakai sebagai EXISTS, bukan whereIn subquery. */
    private function gate($q, string $aliasKaryawan)
    {
        return $q->select(DB::raw(1))
            ->from('N_SOP_Penjadwalan_Detail as gd')
            ->join('N_SOP_Penjadwalan as gpj', 'gpj.Id_SOP_Penjadwalan', '=', 'gd.Id_SOP_Penjadwalan')
            ->whereColumn('gd.Kode_Karyawan', "{$aliasKaryawan}.Kode_Karyawan")
            ->where('gd.Flag_Aktif', SopStatus::AKTIF)
            ->where('gd.Flag_Cabut_Akses', SopStatus::INAKTIF);
    }

    /** Query dasar daftar karyawan + seluruh saringan (semua pra-paginasi). */
    private function baseKaryawan(array $f)
    {
        $q = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan');

        // Gerbang + saringan yang menyangkut penugasan, semuanya di dalam SATU EXISTS
        // supaya total_data tetap jujur (ini bug yang dihindari dari monitoring LMS).
        $q->whereExists(function ($s) use ($f) {
            $this->gate($s, 'k');
            $s->join('N_SOP_Master_Paket as gp', 'gp.Id_SOP_Paket', '=', 'gpj.Id_SOP_Paket');
            $this->terapkanSaringanPaket($s, $f, 'gp', 'gpj', 'gd');
            $this->terapkanStatus($s, $f['status'] ?? 'all', 'gd', 'gpj');
        });

        // "selesai" = TIDAK ADA penugasan yang belum selesai (bukan sekadar punya yang selesai)
        if (($f['status'] ?? 'all') === 'selesai') {
            $q->whereNotExists(function ($s) use ($f) {
                $this->gate($s, 'k');
                $s->join('N_SOP_Master_Paket as gp', 'gp.Id_SOP_Paket', '=', 'gpj.Id_SOP_Paket');
                $this->terapkanSaringanPaket($s, $f, 'gp', 'gpj', 'gd');
                $s->where('gd.Status_Paket', '<>', SopStatus::STATUS_SELESAI);
            });
        }

        $this->terapkanSaringanOrangKaryawan($q, $f);

        // Pilihan eksplisit dari layar export. Bukan pengganti saringan melainkan
        // penyempit di atasnya: admin menyaring dulu, lalu mencentang sebagian.
        // Yang dikirim cuma kode karyawan — pendek, tak perlu dienkripsi, dan tak
        // punya plafon seperti daftar id training di LMS.
        if (! empty($f['kodes'])) {
            $q->whereIn('k.Kode_Karyawan', (array) $f['kodes']);
        }

        $cari = trim((string) ($f['q'] ?? ''));
        if (mb_strlen($cari) >= 2) { // <2 karakter = scan penuh, sengaja ditolak
            $q->where(function ($w) use ($cari) {
                $w->where('k.Nama', 'LIKE', "%{$cari}%")
                    ->orWhere('k.Kode_Karyawan', 'LIKE', "%{$cari}%")
                    ->orWhere('k.No_Induk_Karyawan', 'LIKE', "%{$cari}%");
            });
        }

        return $q;
    }

    /**
     * Saringan yang menyangkut paket/penjadwalan — dipakai di dalam EXISTS maupun query datar.
     *
     * $d (alias N_SOP_Penjadwalan_Detail) WAJIB diparameterkan, bukan di-hardcode:
     * pemanggilnya memakai alias berbeda — 'gd' di dalam EXISTS baseKaryawan(),
     * 'd' pada summary() dan lekatkanAgregat(). Alias mati membuat mode tanggal
     * "selesai" melempar "multi-part identifier could not be bound" di dua jalur.
     *
     * Publik karena SopExportRinciService menyusun query datar sendiri dan HARUS
     * memakai penafsiran saringan yang persis sama. Menyalinnya ke sana berarti
     * dua tafsir yang bebas menyimpang — persis yang dulu terjadi pada dataset
     * coverage, yang diam-diam kehilangan kolom Terlambat.
     */
    public function terapkanSaringanPaket($q, array $f, string $p, string $pj, string $d = 'd'): void
    {
        $this->terapkanSaringanPaketRingkas($q, $f, $p);

        /* SUMBER PENUGASAN — memisahkan kewajiban dari minat.
           N_SOP_Progress berkunci pada Id_SOP_Penjadwalan_Detail yang NOT NULL,
           sehingga karyawan yang membuka SOP PUBLIK didaftarkan sendiri oleh
           sistem dengan Sumber='MANDIRI' (SopPendaftaranMandiri). Tanpa
           penyaring ini, seluruh angka kepatuhan mencampur orang yang
           diwajibkan dengan orang yang sekadar penasaran.

           Dipasang DI SINI, bukan di sebelas endpoint analitik satu per satu:
           semuanya masuk lewat metode ini, dan aturan yang disalin sebelas kali
           akan menyimpang pada salinan yang terlewat. Tanpa nilai `sumber`,
           perilakunya persis seperti sebelumnya — tak ada pemanggil lama yang
           berubah diam-diam. */
        $sumber = array_values(array_filter((array) ($f['sumber'] ?? [])));
        if ($sumber !== []) {
            $q->whereIn("{$pj}.Sumber", $sumber);
        }

        /* Hanya hitung:
           - Paket TERBATAS (SOP Tertutup) -> selalu penugasan resmi.
           - Paket PUBLIK yang DITUGASKAN dengan sengaja -> kewajiban sejak
             ditugaskan, dibuka atau belum.
           - Paket PUBLIK sisanya -> hanya bila karyawan sudah membukanya.

           Baris `Sumber` di tengah menutup cacat yang lama tak terlihat.
           Sebelumnya gerbang ini memakai "sudah dibuka" sebagai proksi untuk
           "ini kewajiban". Proksi itu benar bagi MANDIRI — orang yang sekadar
           penasaran memang tak boleh masuk angka kepatuhan — tetapi salah bagi
           MANUAL/AUTO_RULE: penugasan yang dibuat admin secara sadar ikut
           terbuang dari SELURUH laporan monitoring, dan tak ada apa pun di layar
           yang memberi tahu admin bahwa penugasannya tidak terpantau.

           Niatnya sudah terekam eksplisit di kolom Sumber, jadi dibaca dari
           sana, bukan ditebak dari perilaku karyawannya. */
        $q->where(fn ($w) => SopGerbangPaket::pasang($w, $p, $pj, $d));

        // Rentang tanggal — setengah terbuka, tanpa CAST supaya tetap sargable.
        $mulai = trim((string) ($f['date_start'] ?? ($f['dari'] ?? '')));
        $akhir = trim((string) ($f['date_end'] ?? ($f['sampai'] ?? '')));
        if ($mulai !== '' && $akhir !== '') {
            $mode = $f['date_mode'] ?? 'assign';
            $kol = $mode === 'deadline' ? "{$pj}.Tanggal_Deadline" : ($mode === 'selesai' ? "{$d}.Tanggal_Selesai" : "{$pj}.Tanggal_Assign");
            $q->where($kol, '>=', Carbon::parse($mulai)->startOfDay())
                ->where($kol, '<', Carbon::parse($akhir)->startOfDay()->addDay());
        }
    }

    /**
     * Saringan berlingkup PAKET saja — prioritas, nama/id paket.
     *
     * Dipisah dari terapkanSaringanPaket() karena sebagian query analitik berpusat
     * di ledger atau di progress dan sama sekali tak menjoin N_SOP_Penjadwalan;
     * di sana saringan tanggal tak punya kolom untuk berpijak, sedangkan tiga
     * saringan ini tetap sah. Menyalinnya ke tiap pemanggil berarti beberapa
     * tafsir "paket" yang bebas menyimpang satu sama lain.
     */
    public function terapkanSaringanPaketRingkas($q, array $f, string $p): void
    {
        if (! empty($f['prioritas'])) {
            $q->where("{$p}.Prioritas", $f['prioritas']);
        }

        if (! empty($f['paket'])) {
            $idPakets = [];
            $namaPakets = [];
            foreach ((array) $f['paket'] as $pv) {
                if (is_string($pv) && strlen($pv) >= 5) {
                    $decoded = Hashids::connection('custom')->decode($pv);
                    if (! empty($decoded)) {
                        $idPakets[] = (int) $decoded[0];

                        continue;
                    }
                }
                $namaPakets[] = $pv;
            }
            $q->where(function ($w) use ($p, $idPakets, $namaPakets) {
                if (! empty($idPakets)) {
                    $w->orWhereIn("{$p}.Id_SOP_Paket", $idPakets);
                }
                if (! empty($namaPakets)) {
                    $w->orWhereIn("{$p}.Nama_Paket", $namaPakets);
                }
            });
        }
    }

    /** Adakah saringan yang benar-benar menyempitkan? Dipakai untuk memutus cache. */
    public function adaSaringan(array $f): bool
    {
        /* 'sumber' WAJIB ada di daftar ini.
           Bila tidak, summary() menganggap tak ada saringan dan mengambil
           coverage dari N_SOP_Coverage_Rollup — tabel pra-agregat yang
           dihitung tanpa memandang sumber penugasan. Akibatnya satu kartu
           melaporkan angka tak tersaring sementara semua kartu di sebelahnya
           tersaring, tanpa satu pun tanda bahwa keduanya menghitung himpunan
           yang berbeda. */
        foreach (['paket', 'dept', 'divisi', 'level', 'jabatan', 'lokasi', 'sumber'] as $k) {
            if (! empty($f[$k])) {
                return true;
            }
        }

        return ! empty($f['prioritas'])
            || (! empty($f['date_start']) && ! empty($f['date_end']))
            || (! empty($f['dari']) && ! empty($f['sampai']));
    }

    /** Saringan status penugasan — di dalam EXISTS, jadi berlaku pra-paginasi. */
    public function terapkanStatus($q, string $status, string $d, string $pj): void
    {
        if ($status === 'belum') {
            $q->where("{$d}.Status_Paket", SopStatus::STATUS_BELUM);
        }
        if ($status === 'progress') {
            $q->where("{$d}.Status_Paket", SopStatus::STATUS_PROGRESS);
        }
        if ($status === 'terlambat') {
            $q->where("{$pj}.Tipe_Tenggat", 'DEADLINE')
                ->whereNotNull("{$pj}.Tanggal_Deadline")
                ->where("{$pj}.Tanggal_Deadline", '<', Carbon::today())
                ->where("{$d}.Status_Paket", '<>', SopStatus::STATUS_SELESAI);
        }
        // 'selesai' ditangani lewat whereNotExists di baseKaryawan(); 'all' tanpa syarat.
    }

    /** Saringan organisasi pada query yang beralias k/div/lvl. */
    public function terapkanSaringanOrangKaryawan($q, array $f): void
    {
        if (! empty($f['dept'])) {
            $q->whereIn('div.nama_sub_divisi', (array) $f['dept']);
        }
        if (! empty($f['divisi'])) {
            $q->whereIn('div.nama_divisi', (array) $f['divisi']);
        }
        if (! empty($f['level'])) {
            $q->whereIn('lvl.nama_level', (array) $f['level']);
        }
        if (! empty($f['jabatan'])) {
            $q->whereIn('lvl.nama_jabatan', (array) $f['jabatan']);
        }
        if (! empty($f['lokasi'])) {
            $q->whereIn('k.Cabang_Untuk_Gaji', (array) $f['lokasi']);
        }
    }

    /**
     * Saringan organisasi lewat EXISTS terhadap kolom Kode_Karyawan milik $d.
     *
     * Publik karena seluruh query analitik memerlukannya dan aliasnya berbeda-beda:
     * 'd' pada penugasan, 'pr' pada progress, 'r' pada ledger. Yang dibutuhkan
     * hanya satu hal — alias itu punya kolom Kode_Karyawan.
     */
    public function terapkanSaringanOrang($q, array $f, string $d): void
    {
        $perlu = ! empty($f['dept']) || ! empty($f['divisi']) || ! empty($f['level'])
            || ! empty($f['jabatan']) || ! empty($f['lokasi']);
        if (! $perlu) {
            return;
        }

        $q->whereExists(function ($s) use ($f, $d) {
            $s->select(DB::raw(1))->from('Karyawan as sk')
                ->leftJoin('View_Divisi_Sub_Divisi as sdiv', 'sk.ID_Divisi_Sub_Divisi', '=', 'sdiv.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as slvl', 'sk.ID_Level_Jabatan', '=', 'slvl.ID_Level_Jabatan')
                ->whereColumn('sk.Kode_Karyawan', "{$d}.Kode_Karyawan");
            if (! empty($f['dept'])) {
                $s->whereIn('sdiv.nama_sub_divisi', (array) $f['dept']);
            }
            if (! empty($f['divisi'])) {
                $s->whereIn('sdiv.nama_divisi', (array) $f['divisi']);
            }
            if (! empty($f['level'])) {
                $s->whereIn('slvl.nama_level', (array) $f['level']);
            }
            if (! empty($f['jabatan'])) {
                $s->whereIn('slvl.nama_jabatan', (array) $f['jabatan']);
            }
            if (! empty($f['lokasi'])) {
                $s->whereIn('sk.Cabang_Untuk_Gaji', (array) $f['lokasi']);
            }
        });
    }

    /**
     * Agregat per karyawan untuk baris yang sedang tampil.
     * SATU query GROUP BY atas ≤ limit kode — bukan satu query per baris.
     */
    private function lekatkanAgregat($rows, array $kodes, array $f)
    {
        if (empty($kodes)) {
            return collect();
        }

        $hariIni = Carbon::today();

        $agg = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->whereIn('d.Kode_Karyawan', $kodes)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF);

        $this->terapkanSaringanPaket($agg, $f, 'p', 'pj', 'd');

        /* kritis_belum dicocokkan ke kode KRITIS APA ADANYA. Tingkat buatan
           admin di atasnya TIDAK ikut terhitung di sini — keputusan sadar
           15 Agu 2026: penanda 'hitung sebagai kritis' di master prioritas
           dicabut karena akibatnya tak terlihat dari layar yang mencentangnya.
           Lihat plans/sop/sql/38_cabut_flag_kritis.sql. */
        $agg = $agg->groupBy('d.Kode_Karyawan')
            ->selectRaw(
                "d.Kode_Karyawan,
                 COUNT(*) as total_sop,
                 SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as selesai,
                 SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as progress,
                 SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as belum,
                 SUM(CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' AND pj.Tanggal_Deadline IS NOT NULL
                           AND pj.Tanggal_Deadline < ? AND d.Status_Paket <> ? THEN 1 ELSE 0 END) as terlambat,
                 SUM(CASE WHEN p.Prioritas = ? AND d.Status_Paket <> ? THEN 1 ELSE 0 END) as kritis_belum,
                 MIN(CASE WHEN d.Status_Paket <> ? THEN pj.Tanggal_Deadline END) as tenggat_terdekat,
                 MAX(d.Updated_At) as terakhir_aktif",
                [
                    SopStatus::STATUS_SELESAI, SopStatus::STATUS_PROGRESS, SopStatus::STATUS_BELUM,
                    $hariIni, SopStatus::STATUS_SELESAI,
                    SopStatus::PRIORITAS_KRITIS, SopStatus::STATUS_SELESAI,
                    SopStatus::STATUS_SELESAI,
                ],
            )
            ->get()->keyBy('Kode_Karyawan');

        return $rows->map(function ($r) use ($agg, $hariIni) {
            $a = $agg[$r->Kode_Karyawan] ?? null;
            $total = (int) ($a->total_sop ?? 0);
            $selesai = (int) ($a->selesai ?? 0);

            $r->total_sop = $total;
            $r->selesai = $selesai;
            $r->progress = (int) ($a->progress ?? 0);
            $r->belum = (int) ($a->belum ?? 0);
            $r->terlambat = (int) ($a->terlambat ?? 0);
            $r->sop_kritis_belum = (int) ($a->kritis_belum ?? 0);
            $r->persen = $total > 0 ? (int) round(($selesai / $total) * 100) : 0;
            $r->tenggat_terdekat = $a && $a->tenggat_terdekat ? Carbon::parse($a->tenggat_terdekat)->format('Y-m-d') : null;
            $r->hari_tersisa = $r->tenggat_terdekat
                ? (int) $hariIni->diffInDays(Carbon::parse($r->tenggat_terdekat), false)
                : null;
            $r->terakhir_aktif = $a && $a->terakhir_aktif ? Carbon::parse($a->terakhir_aktif)->format('Y-m-d H:i') : null;

            return $r;
        });
    }
}
