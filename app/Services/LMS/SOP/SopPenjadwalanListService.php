<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\SYSTEM\Application\Exports\AbstractExportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Daftar penjadwalan SOP — kartu batch berpaginasi + drill per-karyawan.
 *
 * Menggantikan listByPaket() yang hanya sanggup menjawab "berapa persen batch
 * ini" tanpa saringan, paginasi, maupun cara melihat SIAPA yang tertinggal.
 *
 * Aturan MSSQL yang dipegang (sama dengan monitoring):
 *  - Tanggal_Deadline dibandingkan langsung terhadap Carbon::today() —
 *    TIDAK PERNAH whereDate()/CAST di predikat (non-sargable).
 *  - Agregat per batch lewat subquery berkorelasi, bukan join yang
 *    menggandakan baris lalu merusak COUNT untuk paginasi.
 */
class SopPenjadwalanListService
{
    /* ================= KARTU BATCH ================= */

    /**
     * @param  array  $f  q, paket[], sumber, tenggat (SEMUA|DEADLINE|SAMPAI_DICABUT|TERLAMBAT),
     *                    dari, sampai
     * @return array{rows:\Illuminate\Support\Collection, total:int, ringkas:array}
     */
    public function batches(array $f, int $page, int $limit): array
    {
        $q = $this->baseBatch($f);
        $total = (clone $q)->count('pj.Id_SOP_Penjadwalan');

        $hariIni = Carbon::today();

        // Agregat per batch lewat subquery berkorelasi — join ke detail akan
        // menggandakan baris header lalu merusak COUNT untuk paginasi.
        $sub = fn (string $cond, string $alias) => DB::raw(
            "(SELECT COUNT(*) FROM N_SOP_Penjadwalan_Detail d
              WHERE d.Id_SOP_Penjadwalan = pj.Id_SOP_Penjadwalan AND {$cond}) as {$alias}",
        );

        $rows = $q->orderByDesc('pj.Id_SOP_Penjadwalan')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get([
                'pj.Id_SOP_Penjadwalan', 'pj.Id_SOP_Paket', 'p.Nama_Paket', 'p.Kode_Paket', 'p.Prioritas',
                'p.Versi_Paket_Aktif', 'p.Deskripsi', 'p.Tujuan_Ruang_Lingkup', 'p.Estimasi_Waktu_Baca_Menit',
                'p.Mode_Distribusi',
                'pj.Sumber', 'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',
                'pj.Flag_Reminder', 'pj.Irama_Reminder_Hari', 'pj.Tanggal_Assign',
                'pj.Nama_Batch', 'pj.Keterangan', 'pj.Created_At', 'pj.Updated_At',
                'kar.Nama as Dibuat_Oleh', 'upd.Nama as Diperbarui_Oleh',
                $sub("d.Flag_Aktif = 'Y' AND d.Flag_Cabut_Akses = 'T'", 'Total_Target'),
                $sub("d.Flag_Aktif = 'Y' AND d.Flag_Cabut_Akses = 'T' AND d.Status_Paket = 'SELESAI'", 'Total_Selesai'),
                $sub("d.Flag_Aktif = 'Y' AND d.Flag_Cabut_Akses = 'T' AND d.Status_Paket = 'PROGRESS'", 'Total_Progress'),
                $sub("d.Flag_Aktif = 'Y' AND d.Flag_Cabut_Akses = 'T' AND d.Status_Paket = 'BELUM'", 'Total_Belum'),
                $sub("d.Flag_Cabut_Akses = 'Y'", 'Total_Dicabut'),
            ]);

        $paketIds = $rows->pluck('Id_SOP_Paket')->unique()->values()->all();
        $komposisi = ! empty($paketIds)
            ? DB::table('N_SOP_Paket_Item')
                ->whereIn('Id_SOP_Paket', $paketIds)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->groupBy('Id_SOP_Paket', 'Tipe_Item')
                ->selectRaw('Id_SOP_Paket, Tipe_Item, COUNT(*) as jml')
                ->get()
                ->groupBy('Id_SOP_Paket')
            : collect();

        // Task async yang masih berjalan (≤20 baris) → dipetakan di PHP.
        $proses = $this->taskAktif();

        $rows = $rows->map(function ($r) use ($hariIni, $proses, $komposisi) {
            $comp = $komposisi->get($r->Id_SOP_Paket, collect())->keyBy('Tipe_Item');
            $r->Total_Dokumen = (int) ($comp->get('DOKUMEN')->jml ?? 0);
            $r->Total_Kuis = (int) ($comp->get('KUIS')->jml ?? 0);
            $r->Total_Item_Paket = (int) $comp->sum('jml');
            $r->Versi_Paket = (int) ($r->Versi_Paket_Aktif ?: 1);
            $r->Estimasi = $r->Estimasi_Waktu_Baca_Menit ? (int) $r->Estimasi_Waktu_Baca_Menit : 10;
            $r->Deskripsi_Paket = $r->Deskripsi ?: $r->Tujuan_Ruang_Lingkup;

            $r->Total_Target = (int) $r->Total_Target;
            $r->Total_Selesai = (int) $r->Total_Selesai;
            $r->Total_Progress = (int) $r->Total_Progress;
            $r->Total_Belum = (int) $r->Total_Belum;
            $r->Total_Dicabut = (int) $r->Total_Dicabut;
            $r->Persen = $r->Total_Target > 0 ? (int) round($r->Total_Selesai / $r->Total_Target * 100) : 0;

            // Terlambat = batch berdeadline yang lewat, dihitung dari yang BELUM selesai.
            $lewat = $r->Tipe_Tenggat === 'DEADLINE' && $r->Tanggal_Deadline
                && Carbon::parse($r->Tanggal_Deadline)->lt($hariIni);
            $r->Is_Terlambat = $lewat;
            $r->Total_Terlambat = $lewat ? ($r->Total_Target - $r->Total_Selesai) : 0;
            $r->Sisa_Hari = ($r->Tipe_Tenggat === 'DEADLINE' && $r->Tanggal_Deadline)
                ? $hariIni->diffInDays(Carbon::parse($r->Tanggal_Deadline), false) : null;

            $r->Label = $this->label($r);
            $r->Diperbarui = $r->Updated_At && $r->Created_At
                && Carbon::parse($r->Updated_At)->gt(Carbon::parse($r->Created_At)->addMinute());

            $t = $proses[(int) $r->Id_SOP_Penjadwalan] ?? null;
            $r->Status_Proses = $t['status'] ?? null;
            $r->Proses_Persen = $t['percent'] ?? null;
            $r->Task_Id = $t['id'] ?? null;

            $r->Id_SOP_Penjadwalan = Hashids::connection('custom')->encode($r->Id_SOP_Penjadwalan);
            $r->Id_SOP_Paket = Hashids::connection('custom')->encode($r->Id_SOP_Paket);

            return $r;
        });

        /*
         * ringkas() TIDAK dihitung di sini. Controller membuang kunci itu dan
         * memanggil endpoint /ringkas terpisah, jadi setiap pemuatan daftar
         * membayar satu agregat plus dua panggilan taskAktif() secara percuma.
         * Pemanggil yang butuh KPI strip memanggil ringkas() langsung.
         */
        return ['rows' => $rows, 'total' => $total];
    }

    /** KPI strip — dihitung atas saringan yang sama dengan daftarnya. */
    public function ringkas(array $f): array
    {
        $hariIni = Carbon::today();

        $agg = $this->baseBatch($f)
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->selectRaw(
                "COUNT(DISTINCT pj.Id_SOP_Penjadwalan) as batch,
                 COUNT(*) as target,
                 SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as selesai,
                 SUM(CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' AND pj.Tanggal_Deadline IS NOT NULL
                           AND pj.Tanggal_Deadline < ? AND d.Status_Paket <> ? THEN 1 ELSE 0 END) as terlambat",
                [SopStatus::STATUS_SELESAI, $hariIni, SopStatus::STATUS_SELESAI],
            )->first();

        $target = (int) ($agg->target ?? 0);
        $selesai = (int) ($agg->selesai ?? 0);

        $counts = DB::table('N_SOP_Penjadwalan as pj')
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->selectRaw("
                SUM(CASE WHEN pj.Sumber IN ('MANUAL', 'AUTO_RULE') THEN 1 ELSE 0 END) as ditugaskan,
                SUM(CASE WHEN pj.Sumber = 'MANDIRI' THEN 1 ELSE 0 END) as mandiri
            ")->first();

        return [
            'batch' => (int) ($agg->batch ?? 0),
            'target' => $target,
            'selesai' => $selesai,
            'persen' => $target > 0 ? (int) round($selesai / $target * 100) : 0,
            'terlambat' => (int) ($agg->terlambat ?? 0),
            'diproses' => count($this->taskAktif()),
            'tab_ditugaskan' => (int) ($counts->ditugaskan ?? 0),
            'tab_mandiri' => (int) ($counts->mandiri ?? 0),
        ];
    }

    /* ================= DRILL PER-KARYAWAN ================= */

    /**
     * @param  array  $f  q, status (BELUM|PROGRESS|SELESAI|DICABUT),
     *                    divisi[], dept[], level[], jabatan[], lokasi[]
     * @return array{rows:\Illuminate\Support\Collection, total:int}
     */
    public function batchKaryawan(int $penjadwalanId, array $f, int $page, int $limit): array
    {
        $q = $this->baseDetail($penjadwalanId, $f);
        $total = (clone $q)->count('d.Id_SOP_Penjadwalan_Detail');

        $rows = $q->orderBy('kar.Nama')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get([
                'd.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan', 'kar.Nama',
                'd.Status_Paket', 'd.Flag_Cabut_Akses', 'd.Tanggal_Selesai', 'd.Created_At',
                'd.Terakhir_Diingatkan_At', 'd.Jumlah_Reminder',
                'div.nama_sub_divisi as Departemen', 'div.nama_divisi as Divisi',
                'lvl.nama_jabatan as Jabatan', 'lvl.nama_level as Level',
                'kar.Cabang_Untuk_Gaji as Lokasi',
                DB::raw("(SELECT COUNT(*) FROM N_SOP_Progress g
                          WHERE g.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail
                            AND g.Status <> 'DIGANTIKAN') as Item_Total"),
                DB::raw("(SELECT COUNT(*) FROM N_SOP_Progress g
                          WHERE g.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail
                            AND g.Status IN ('SELESAI','DIKONFIRMASI')) as Item_Selesai"),
                DB::raw('(SELECT MAX(g.Waktu_Buka) FROM N_SOP_Progress g
                          WHERE g.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail) as Terakhir_Buka'),
                DB::raw('(SELECT MAX(g.Waktu_Konfirmasi) FROM N_SOP_Progress g
                          WHERE g.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail) as Terakhir_Konfirmasi'),
                DB::raw("(SELECT AVG(g.Skor_Quiz) FROM N_SOP_Progress g
                          WHERE g.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail
                            AND g.Tipe_Item = 'KUIS' AND g.Skor_Quiz IS NOT NULL) as Skor_Quiz"),
                DB::raw("(SELECT MIN(g.Status_Lulus) FROM N_SOP_Progress g
                          WHERE g.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail
                            AND g.Tipe_Item = 'KUIS' AND g.Status_Lulus IS NOT NULL) as Status_Lulus"),
            ])
            ->map(function ($r) {
                $r->Item_Total = (int) $r->Item_Total;
                $r->Item_Selesai = (int) $r->Item_Selesai;
                $r->Persen = $r->Item_Total > 0 ? (int) round($r->Item_Selesai / $r->Item_Total * 100) : 0;
                $r->Skor_Quiz = $r->Skor_Quiz !== null ? (int) round($r->Skor_Quiz) : null;
                $r->Terakhir_Buka_Format = $r->Terakhir_Buka ? Carbon::parse($r->Terakhir_Buka)->format('d M Y H:i') : null;
                $r->Tanggal_Selesai_Format = $r->Tanggal_Selesai ? Carbon::parse($r->Tanggal_Selesai)->format('d M Y H:i') : null;
                $r->Tanggal_Assign_Format = $r->Created_At ? Carbon::parse($r->Created_At)->format('d M Y') : null;
                $r->Id_SOP_Penjadwalan_Detail = Hashids::connection('custom')->encode($r->Id_SOP_Penjadwalan_Detail);

                return $r;
            });

        return ['rows' => $rows, 'total' => $total];
    }

    /** Kode karyawan SELURUH hasil saringan — untuk "pilih semua lintas halaman". */
    public function batchKaryawanKodes(int $penjadwalanId, array $f): array
    {
        return $this->baseDetail($penjadwalanId, $f)->pluck('d.Kode_Karyawan')->all();
    }

    /** Header batch tunggal (untuk deep-link ?batch= dan dialog edit). */
    public function batch(int $penjadwalanId): ?object
    {
        $r = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('pj.Id_SOP_Penjadwalan', $penjadwalanId)
            ->first([
                'pj.Id_SOP_Penjadwalan', 'pj.Id_SOP_Paket', 'p.Nama_Paket', 'pj.Sumber',
                'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline', 'pj.Flag_Reminder',
                'pj.Irama_Reminder_Hari', 'pj.Tanggal_Assign', 'pj.Nama_Batch', 'pj.Keterangan',
            ]);
        if (! $r) {
            return null;
        }

        $r->Id_SOP_Penjadwalan = Hashids::connection('custom')->encode($r->Id_SOP_Penjadwalan);
        $r->Id_SOP_Paket = Hashids::connection('custom')->encode($r->Id_SOP_Paket);

        return $r;
    }

    /* ================= RIWAYAT BATCH ================= */

    /**
     * Timeline satu batch. Baris "dibuat" disintesis dari header (peristiwa itu
     * mendahului adanya penulis log), sisanya dari N_SOP_Log lewat kolom
     * Id_SOP_Penjadwalan yang berindeks.
     */
    public function riwayat(int $penjadwalanId, int $limit = 50): array
    {
        $pj = DB::table('N_SOP_Penjadwalan as pj')
            ->leftJoin('Karyawan as kar', 'kar.UserID_Web', '=', 'pj.Created_By')
            ->where('pj.Id_SOP_Penjadwalan', $penjadwalanId)
            ->first(['pj.Created_At', 'pj.Sumber', 'pj.Keterangan', 'kar.Nama as oleh']);
        if (! $pj) {
            return [];
        }

        $log = DB::table('N_SOP_Log as l')
            ->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 'l.Oleh_Id_Users')
            ->leftJoin('Karyawan as kar', 'kar.Kode_Karyawan', '=', 'u.Kode_Users')
            ->where('l.Id_SOP_Penjadwalan', $penjadwalanId)
            ->orderByDesc('l.Created_At')->orderByDesc('l.Id_SOP_Log')
            ->limit($limit)
            ->get(['l.Aksi', 'l.Keterangan', 'l.Created_At', 'kar.Nama as oleh', 'l.Meta_JSON']);

        $semuaKodes = [];
        foreach ($log as $l) {
            $meta = json_decode($l->Meta_JSON ?: '{}', true) ?: [];
            if (! empty($meta['kode_karyawan'])) {
                foreach ((array) $meta['kode_karyawan'] as $kd) {
                    if (is_string($kd) && $kd !== '') {
                        $semuaKodes[] = $kd;
                    }
                }
            }
        }
        $karyawanMap = ! empty($semuaKodes)
            ? DB::table('Karyawan')->whereIn('Kode_Karyawan', array_unique($semuaKodes))->pluck('Nama', 'Kode_Karyawan')->all()
            : [];

        $baris = $log->map(function ($l) use ($karyawanMap) {
            $meta = json_decode($l->Meta_JSON ?: '{}', true) ?: [];
            $kodes = array_values(array_filter((array) ($meta['kode_karyawan'] ?? [])));
            $daftarKaryawan = array_map(function ($kd) use ($karyawanMap) {
                $nama = $karyawanMap[$kd] ?? null;

                return $nama ? "{$nama} ({$kd})" : $kd;
            }, $kodes);

            $sebabLabel = null;
            if (! empty($meta['sebab'])) {
                $sebabLabel = \App\Services\LMS\SOP\Support\SopSebabCabut::semua()[$meta['sebab']] ?? $meta['sebab'];
            }

            return [
                'aksi' => $l->Aksi,
                'judul' => $this->judulAksi($l->Aksi),
                'keterangan' => $l->Keterangan,
                'oleh' => $l->oleh ?: 'Sistem',
                'waktu' => $l->Created_At,
                'jumlah' => count($kodes) > 0 ? count($kodes) : null,
                'karyawan' => $daftarKaryawan,
                'sebab' => $sebabLabel,
                'catatan' => $meta['catatan'] ?? null,
            ];
        })->values()->all();

        $baris[] = [
            'aksi' => 'DIBUAT',
            'judul' => 'Penjadwalan Dibuat',
            'keterangan' => $pj->Keterangan ?: ($pj->Sumber === 'MANDIRI'
                ? 'Pendaftaran mandiri oleh karyawan.'
                : ($pj->Sumber === 'AUTO_RULE' ? 'Distribusi dibuat oleh assignment rule.' : 'Distribusi dibuat oleh admin.')),
            'oleh' => $pj->oleh ?: ($pj->Sumber === 'MANDIRI' ? 'Karyawan' : 'Sistem'),
            'waktu' => $pj->Created_At,
            'jumlah' => null,
            'karyawan' => [],
            'sebab' => null,
            'catatan' => null,
        ];

        return $baris;
    }

    private function judulAksi(string $aksi): string
    {
        return [
            'ASSIGN' => 'Karyawan Ditugaskan',
            'TAMBAH_KARYAWAN' => 'Karyawan Ditambahkan',
            'CABUT_AKSES' => 'Akses Dihapus',
            'PULIHKAN_AKSES' => 'Akses Dipulihkan',
            'UBAH_TENGGAT' => 'Tenggat Diubah',
            'UBAH_LABEL' => 'Label Diubah',
            'UBAH_KETERANGAN' => 'Keterangan Diubah',
            'UBAH_REMINDER' => 'Pengingat Diubah',
            'INGATKAN' => 'Pengingat Dikirim',
            'DIBUAT' => 'Penjadwalan Dibuat',
        ][$aksi] ?? $aksi;
    }

    /* ================= INTERNAL ================= */

    /**
     * Label kartu. Nama_Batch bila diisi; kalau tidak, disusun dari tanggal +
     * jumlah target supaya dua distribusi paket yang sama TETAP bisa dibedakan
     * sekilas — itulah keluhan aslinya (judul kembar sepanjang daftar).
     */
    private function label(object $r): string
    {
        if (! empty($r->Nama_Batch)) {
            return $r->Nama_Batch;
        }

        $tgl = $r->Tanggal_Assign ? Carbon::parse($r->Tanggal_Assign)->translatedFormat('d M Y') : '—';
        $n = (int) $r->Total_Target;

        if ($r->Sumber === 'MANDIRI') {
            return "Pendaftaran mandiri - {$tgl}";
        }
        if ($r->Sumber === 'AUTO_RULE') {
            return "Cohort otomatis - {$tgl}".($n > 0 ? " - {$n} orang" : '');
        }

        return "Angkatan {$tgl}".($n > 0 ? " - {$n} orang" : '');
    }

    private function baseBatch(array $f)
    {
        $q = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->leftJoin('Karyawan as kar', 'kar.UserID_Web', '=', 'pj.Created_By')
            ->leftJoin('Karyawan as upd', 'upd.UserID_Web', '=', 'pj.Updated_By')
            ->where('pj.Flag_Aktif', SopStatus::AKTIF);

        if (! empty($f['paket'])) {
            $q->whereIn('pj.Id_SOP_Paket', (array) $f['paket']);
        }
        if (! empty($f['sumber'])) {
            if ($f['sumber'] === 'DITUGASKAN') {
                $q->whereIn('pj.Sumber', ['MANUAL', 'AUTO_RULE']);
            } elseif (is_array($f['sumber'])) {
                $q->whereIn('pj.Sumber', $f['sumber']);
            } else {
                $q->where('pj.Sumber', $f['sumber']);
            }
        }

        $tenggat = $f['tenggat'] ?? 'SEMUA';
        if ($tenggat === 'TERLAMBAT') {
            $q->where('pj.Tipe_Tenggat', 'DEADLINE')
                ->whereNotNull('pj.Tanggal_Deadline')
                ->where('pj.Tanggal_Deadline', '<', Carbon::today()); // sargable
        } elseif (in_array($tenggat, ['DEADLINE', 'SAMPAI_DICABUT'], true)) {
            $q->where('pj.Tipe_Tenggat', $tenggat);
        }

        // Rentang tanggal assign — setengah terbuka, tanpa CAST di predikat.
        if (! empty($f['dari'])) {
            $q->where('pj.Tanggal_Assign', '>=', Carbon::parse($f['dari'])->startOfDay());
        }
        if (! empty($f['sampai'])) {
            $q->where('pj.Tanggal_Assign', '<', Carbon::parse($f['sampai'])->startOfDay()->addDay());
        }

        $cari = trim((string) ($f['q'] ?? ''));
        if (mb_strlen($cari) >= 2) {
            $q->where(function ($w) use ($cari) {
                $w->where('p.Nama_Paket', 'LIKE', "%{$cari}%")
                    // cari nama karyawan di dalam batch — EXISTS, bukan join yang menggandakan
                    ->orWhereExists(function ($s) use ($cari) {
                        $s->select(DB::raw(1))
                            ->from('N_SOP_Penjadwalan_Detail as dd')
                            ->join('Karyawan as kk', 'kk.Kode_Karyawan', '=', 'dd.Kode_Karyawan')
                            ->whereColumn('dd.Id_SOP_Penjadwalan', 'pj.Id_SOP_Penjadwalan')
                            ->where(fn ($x) => $x->where('kk.Nama', 'LIKE', "%{$cari}%")
                                ->orWhere('dd.Kode_Karyawan', 'LIKE', "%{$cari}%"));
                    });
            });
        }

        return $q;
    }

    private function baseDetail(int $penjadwalanId, array $f)
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('Karyawan as kar', 'kar.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'kar.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'kar.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('d.Id_SOP_Penjadwalan', $penjadwalanId)
            ->where('d.Flag_Aktif', SopStatus::AKTIF);

        $status = $f['status'] ?? 'SEMUA';
        if ($status === 'DICABUT') {
            $q->where('d.Flag_Cabut_Akses', 'Y');
        } else {
            $q->where('d.Flag_Cabut_Akses', 'T');
            if (in_array($status, ['BELUM', 'PROGRESS', 'SELESAI'], true)) {
                $q->where('d.Status_Paket', $status);
            }
        }

        $cari = trim((string) ($f['q'] ?? ''));
        if (mb_strlen($cari) >= 2) {
            $q->where(fn ($w) => $w->where('kar.Nama', 'LIKE', "%{$cari}%")
                ->orWhere('d.Kode_Karyawan', 'LIKE', "%{$cari}%"));
        }

        $this->saringOrganisasi($q, $f);

        return $q;
    }

    /**
     * Saringan atribut organisasi. Kolomnya SENGAJA sama persis dengan yang
     * dipakai monitoring (SopMonitoringPeopleService) — "Divisi" di dua layar
     * yang menghitung dari kolom berbeda adalah cara paling halus membuat dua
     * angka yang benar tampak saling bertentangan.
     *
     * Dipakai baseDetail(), jadi daftar dan "pilih semua lintas halaman"
     * mustahil berbeda: keduanya lewat sini.
     */
    private function saringOrganisasi($q, array $f): void
    {
        if (! empty($f['divisi'])) {
            $q->whereIn('div.nama_divisi', (array) $f['divisi']);
        }
        if (! empty($f['dept'])) {
            $q->whereIn('div.nama_sub_divisi', (array) $f['dept']);
        }
        if (! empty($f['level'])) {
            $q->whereIn('lvl.nama_level', (array) $f['level']);
        }
        if (! empty($f['jabatan'])) {
            $q->whereIn('lvl.nama_jabatan', (array) $f['jabatan']);
        }
        if (! empty($f['lokasi'])) {
            $q->whereIn('kar.Cabang_Untuk_Gaji', (array) $f['lokasi']);
        }
    }

    /** Task bulk-assign yang masih berjalan, dipetakan per Id_SOP_Penjadwalan. */
    private function taskAktif(): array
    {
        try {
            $rows = DB::table(AbstractExportService::TABLE)
                ->where('Task_Type', SopBulkAssignTaskService::TASK_TYPE)
                ->whereIn('Status', [AbstractExportService::STATUS_QUEUED, AbstractExportService::STATUS_PROCESSING])
                ->whereNull('Deleted_At')
                ->orderByDesc('Id_Export')->limit(20)
                ->get(['Export_Id', 'Status', 'Percent', 'Payload_Json']);
        } catch (\Throwable $e) {
            /* Daftar tetap tidak dijatuhkan - tetapi "tidak ada penugasan massal
               yang sedang berjalan" dan "kueri tabel task-nya gagal" menghasilkan
               layar yang sama persis. Tanpa baris ini, admin menunggu proses yang
               sebenarnya tidak pernah terlihat oleh sistem. */
            Log::warning('Gagal membaca task penugasan massal SOP yang aktif', [
                'kelas' => $e::class,
                'pesan' => $e->getMessage(),
                'sql' => $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : null,
            ]);

            return [];
        }

        $peta = [];
        foreach ($rows as $r) {
            $p = json_decode($r->Payload_Json ?: '{}', true) ?: [];
            if (empty($p['id_penjadwalan'])) {
                continue;
            }
            $peta[(int) $p['id_penjadwalan']] = [
                'id' => $r->Export_Id,
                'status' => $r->Status,
                'percent' => (int) $r->Percent,
            ];
        }

        return $peta;
    }
}
