<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kesehatan penugasan penilai — apa yang HARUS diselesaikan admin sesudah peserta terbentuk.
 *
 * MASALAH YANG DISELESAIKAN
 *
 * Aturan penilai (Langkah 3) menyatakan berapa penilai minimum per kategori. Penyiapan lalu
 * mencoba memenuhinya dari relasi organisasi yang ada. Yang tidak terpenuhi tidak pernah
 * dilaporkan: pesertanya tetap `DIIKUTKAN`, penugasannya kurang, dan angkanya baru terlihat
 * ketika hasilnya dihitung dan sebagian orang tertahan tanpa sebab yang jelas.
 *
 * Layanan ini membandingkan aturan dengan kenyataan, lalu menyebut selisihnya.
 *
 * KENAPA INI TIDAK BISA DI LANGKAH 4
 *
 * Baris `N_PA_Assignment` baru ada SESUDAH penyiapan berjalan. Sebelum itu tidak ada apa pun
 * untuk dibandingkan — Langkah 4 hanya bisa memperingatkan tentang aturan yang tidak lengkap,
 * bukan tentang penugasan yang kurang.
 *
 * EMPAT KEADAAN YANG DIBEDAKAN
 *
 * KURANG          penugasan aktif lebih sedikit daripada `Min_Rater`.
 * TANPA_ATURAN    peserta tidak punya salinan aturan sama sekali; ia tidak akan pernah
 *                 bisa dihitung, dan penyebabnya di Langkah 3, bukan di sini.
 * PENILAI_NONAKTIF  penilainya sudah tidak aktif sebagai karyawan. Penugasannya ada, tetapi
 *                 tidak akan pernah diisi.
 * BELUM_SELESAI   penugasannya cukup tetapi belum diisi; ini bukan cacat, hanya kemajuan.
 *
 * AMBANG = MAKS(Min_Rater, Min_Rater_Selesai) (13 Okt 2026). Kategori dihitung hanya bila
 * yang SELESAI mencapai `Min_Rater_Selesai`. Mengukur kekurangan dari `Min_Rater` saja
 * meloloskan aturan "ditugaskan 1, harus selesai 2" — cukup terpasang, tetapi pasti gugur.
 *
 * TIDAK DIIKUTKAN (13 Okt 2026). Kategori yang admin tandai sengaja tidak diikutkan bagi
 * satu peserta (override `Flag_Dipakai = 'T'`) BUKAN kekurangan — itu keputusan yang
 * tercatat beralasan. Override yang mengubah `Min_Rater` peserta juga dipakai di sini,
 * supaya kekurangan diukur dari aturan yang sama dengan yang dibaca resolver.
 *
 * Keempatnya SENGAJA tidak digabung. "Kurang penilai" dan "penilainya sudah resign" sama-sama
 * menghasilkan skor timpang, tetapi yang pertama diselesaikan dengan menambah penugasan dan
 * yang kedua dengan menggantinya. Angka gabungan menyembunyikan mana yang harus dikerjakan.
 */
class PaAssignmentHealthService
{
    public function __construct(
        private readonly PaCycleGate $gate,
    ) {}

    /**
     * Ringkasan per (level × kategori penilai): berapa yang seharusnya, berapa yang ada.
     *
     * Dihitung dengan tiga kueri berbasis himpunan, bukan satu per peserta. Pada 958 peserta,
     * menanyakannya per orang berarti ribuan perjalanan ke database untuk satu tabel.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(int $cycleId): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Status_Cycle']);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        /* 1. Syarat: minimum penilai per (level, kategori), dari salinan aturan siklus. */
        $syarat = DB::table('N_PA_Cycle_Rater_Policy_Snapshot as s')
            ->join('N_PA_Cycle_Rater_Policy_Detail_Snapshot as d',
                'd.PA_Cycle_Rater_Policy_Snapshot_Id', '=', 's.Id_PA_Cycle_Rater_Policy_Snapshot')
            ->join('N_PA_Rater_Category as k', 'k.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', 's.ID_Level')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->where('s.Flag_Aktif', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->orderBy('s.ID_Level')
            ->orderBy('d.Urutan_Tampil')
            ->get([
                's.ID_Level as id_level',
                'l.Nama_Level as nama_level',
                'd.PA_Rater_Category_Id as kategori_id',
                'k.Kode_Kategori as kode_kategori',
                'k.Nama_Kategori as nama_kategori',
                'd.Min_Rater as min_rater',
                'd.Min_Rater_Selesai as min_selesai',
                'd.Bobot_Persen as bobot',
            ]);

        /* 2. Cacah peserta per level yang benar-benar dinilai. */
        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->groupBy('ID_Level_Saat_Cycle')
            ->get(['ID_Level_Saat_Cycle', DB::raw('COUNT(*) as jumlah')])
            ->pluck('jumlah', 'ID_Level_Saat_Cycle')
            ->all();

        /* 3. Kenyataan: cacah peserta yang penugasannya CUKUP per (level, kategori),
              beserta cacah penugasan yang penilainya sudah tidak aktif.

              Dihitung di database, bukan dengan menarik seluruh penugasan ke memori:
              pada 958 peserta x 4 kategori itu ribuan baris untuk beberapa angka. */
        $nyata = DB::table('N_PA_Participant as p')
            ->join('N_PA_Assignment as a', 'a.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->leftJoin('Karyawan as k', function ($j) {
                $j->on('k.Kode_Perusahaan', '=', 'a.Kode_Perusahaan_Penilai')
                    ->on('k.Kode_Karyawan', '=', 'a.Kode_Karyawan_Penilai');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')
            ->groupBy('p.ID_Level_Saat_Cycle', 'a.PA_Rater_Category_Id', 'p.Id_PA_Participant')
            ->get([
                'p.ID_Level_Saat_Cycle as id_level',
                'a.PA_Rater_Category_Id as kategori_id',
                'p.Id_PA_Participant as peserta_id',
                DB::raw('COUNT(*) as terpasang'),
                DB::raw("SUM(CASE WHEN a.Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) as selesai"),
                DB::raw("SUM(CASE WHEN ISNULL(k.Aktif, 'T') <> 'Y' THEN 1 ELSE 0 END) as penilai_nonaktif"),
            ])
            ->groupBy(fn ($r) => $r->id_level.'|'.$r->kategori_id);

        /* Id peserta per level, bukan hanya cacahnya.

           Dibutuhkan untuk menghitung ORANG yang kurang, bukan slot kategori yang kurang.
           Tanpa daftar id-nya, satu-satunya angka yang bisa disusun adalah jumlah per
           kategori — dan itulah yang selama ini dilaporkan sebagai "peserta". */
        $idPerLevel = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Participant as id', 'ID_Level_Saat_Cycle as lvl'])
            ->groupBy('lvl')
            ->map(fn ($g) => $g->pluck('id')->all())
            ->all();

        $override = $this->overridePenilai($cycleId);

        /* Himpunan peserta yang kurang di SETIDAKNYA SATU kategori. Himpunan, bukan cacah:
           peserta yang kurang di empat kategori tetap SATU orang. */
        $pesertaKurang = [];

        $baris = $syarat->map(function ($s) use ($nyata, $peserta, $idPerLevel, $override, &$pesertaKurang) {
            $kunci = $s->id_level.'|'.$s->kategori_id;
            $milik = $nyata[$kunci] ?? collect();
            $min = self::ambang($s->min_rater, $s->min_selesai);

            $totalPeserta = (int) ($peserta[$s->id_level] ?? 0);
            $terpasangPer = $milik->mapWithKeys(fn ($r) => [(int) $r->peserta_id => (int) $r->terpasang])->all();

            /* Diukur PER PESERTA, termasuk yang belum punya baris sama sekali pada kategori
               ini (ia tidak muncul di `$nyata`; menghitung hanya yang punya baris melaporkan
               nol kekurangan pada kategori yang seluruhnya kosong). Minimumnya milik peserta
               itu: override menang atas salinan level, dan kategori yang ditandai tidak
               diikutkan tidak diukur sama sekali. */
            $kurang = 0;
            $cukup = 0;
            $tidakDiikutkan = 0;

            foreach ($idPerLevel[$s->id_level] ?? [] as $id) {
                $id = (int) $id;
                $o = $override[$id.'|'.$s->kategori_id] ?? null;

                if ($o !== null && $o['dipakai'] === false) {
                    $tidakDiikutkan++;

                    continue;
                }

                $minPeserta = $o['min'] ?? $min;

                if (($terpasangPer[$id] ?? 0) < $minPeserta) {
                    $kurang++;
                    $pesertaKurang[$id] = true;
                } else {
                    $cukup++;
                }
            }

            return [
                'id_level' => (int) $s->id_level,
                'nama_level' => $s->nama_level ?? "Level {$s->id_level}",
                'kategori_id' => (int) $s->kategori_id,
                'kode_kategori' => $s->kode_kategori,
                'nama_kategori' => $s->nama_kategori,
                'min_rater' => (int) $s->min_rater,
                // Terpasang yang dibutuhkan agar kategori bisa dihitung: maks(min, min selesai).
                'ambang_terpasang' => $min,
                /* NULL berarti mengikuti `min_rater`. Menampilkannya sebagai 0 membuat
                   layar berkata "0 harus selesai" pada aturan yang justru paling ketat. */
                'min_selesai' => $s->min_selesai === null ? (int) $s->min_rater : (int) $s->min_selesai,
                'min_selesai_mengikuti' => $s->min_selesai === null,
                'bobot_persen' => (float) $s->bobot,
                'peserta' => $totalPeserta,
                'peserta_cukup' => $cukup,
                'peserta_kurang' => $kurang,
                'peserta_tidak_diikutkan' => $tidakDiikutkan,
                'penugasan_terpasang' => (int) $milik->sum('terpasang'),
                'penugasan_selesai' => (int) $milik->sum('selesai'),
                'penilai_nonaktif' => (int) $milik->sum('penilai_nonaktif'),
            ];
        })->all();

        /* Peserta tanpa salinan aturan sama sekali. Ia tidak akan pernah bisa dihitung, dan
           penyebabnya ada di Langkah 3 — bukan sesuatu yang bisa diperbaiki dari sini. */
        $tanpaAturan = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('PA_Cycle_Rater_Policy_Snapshot_Id')
            ->count();

        return [
            'cycle' => [
                'id' => (int) $siklus->Id_PA_Cycle,
                'kode' => $siklus->Kode_Cycle,
                'nama' => $siklus->Nama_Cycle,
                'status' => $siklus->Status_Cycle,
            ],
            'baris' => $baris,

            /* ORANG, bukan slot. Sebelum ini kolom ini menjumlahkan `peserta_kurang` dari
               seluruh baris (level x kategori), sehingga satu peserta yang kurang di empat
               kategori terhitung empat kali — pada siklus 859 peserta angkanya keluar 2.648.

               Angka yang lebih besar daripada populasinya bukan sekadar keliru: ia membuat
               seluruh layar tidak bisa dipercaya, dan admin yang membacanya tidak punya cara
               tahu bagian mana lagi yang salah. */
            'total_peserta_kurang' => count($pesertaKurang),

            /* Jumlah per-kategori TETAP dilaporkan, dengan nama yang menyebut isinya. Ia
               berguna untuk menakar volume pekerjaan — berapa slot penilai yang harus diisi —
               dan itu memang bukan jumlah orang. */
            'total_slot_kurang' => array_sum(array_column($baris, 'peserta_kurang')),
            'total_penilai_nonaktif' => array_sum(array_column($baris, 'penilai_nonaktif')),
            'peserta_tanpa_aturan' => $tanpaAturan,

            /* Kategori yang sengaja tidak diikutkan, supaya keputusan itu tetap terlihat. */
            'tidak_diikutkan' => $this->kategoriTidakDiikutkan($cycleId),
            'boleh_disunting' => $this->gate->boleh(PaCycleGate::BUAT_PENUGASAN, $cycleId),

            /* Siap dilanjutkan hanya bila tidak ada kekurangan sama sekali. Dikirim sebagai
               satu nilai supaya layar tidak menyusun aturannya sendiri dan berselisih. */
            'siap_dikunci' => $tanpaAturan === 0
                && array_sum(array_column($baris, 'peserta_kurang')) === 0
                && array_sum(array_column($baris, 'penilai_nonaktif')) === 0,
        ];
    }

    /**
     * Peserta yang penugasannya bermasalah, untuk ditindaklanjuti satu per satu.
     *
     * Berhalaman. Tanpa itu, level dengan 391 peserta memulangkan 391 baris ke layar yang
     * hanya menampilkan sebagian — dan sisanya tidak pernah terlihat.
     *
     * @return array<string, mixed>
     */
    public function pesertaBermasalah(int $cycleId, array $saringan = []): array
    {
        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $per = min(100, max(10, (int) ($saringan['per_halaman'] ?? 25)));

        $syarat = DB::table('N_PA_Cycle_Rater_Policy_Snapshot as s')
            ->join('N_PA_Cycle_Rater_Policy_Detail_Snapshot as d',
                'd.PA_Cycle_Rater_Policy_Snapshot_Id', '=', 's.Id_PA_Cycle_Rater_Policy_Snapshot')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->where('s.Flag_Aktif', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->get(['s.Id_PA_Cycle_Rater_Policy_Snapshot as snapshot_id',
                'd.PA_Rater_Category_Id as kategori_id', 'd.Min_Rater as min_rater',
                'd.Min_Rater_Selesai as min_selesai'])
            ->groupBy('snapshot_id');

        $kategoriNama = DB::table('N_PA_Rater_Category')
            ->pluck('Nama_Kategori', 'Id_PA_Rater_Category')
            ->all();

        $q = DB::table('N_PA_Participant as p')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y');

        if (! empty($saringan['id_level'])) {
            $q->where('p.ID_Level_Saat_Cycle', (int) $saringan['id_level']);
        }

        /* Saringan "hanya yang bermasalah" DITEGAKKAN DI DATABASE.

           Sebelumnya ia dijalankan SESUDAH satu halaman diambil, dengan alasan "masalahnya baru
           diketahui sesudah barisnya diambil". Alasan itu tidak benar, dan akibatnya parah:
           `total` dihitung sebelum penyaringan sementara `baris` sesudahnya, jadi layar bisa
           berkata "859" sambil menampilkan NOL baris. Dan peserta bermasalah yang jatuh di
           halaman 12 tidak pernah bisa ditemukan lewat saringan yang bekerja per halaman.

           Kekurangan penilai memang bisa dinyatakan sebagai syarat SQL: cacah penugasan aktif
           per (peserta, kategori) dibandingkan dengan `Min_Rater` pada salinan aturannya.
           Tiga bentuk masalah yang ditegakkan di sini:

             1. tidak punya salinan aturan sama sekali
             2. ada kategori yang penugasan aktifnya kurang dari minimum
             3. ada penugasan aktif yang penilainya sudah tidak aktif sebagai karyawan

           Dengan begitu `total` dan `baris` berbicara tentang himpunan yang SAMA. */
        if (! empty($saringan['hanya_bermasalah'])) {
            $q->where(function ($w) {
                // 1. tanpa salinan aturan
                $w->whereNull('p.PA_Cycle_Rater_Policy_Snapshot_Id')

                    // 2. ada kategori yang kurang dari minimum
                    ->orWhereExists(function ($sub) {
                        $sub->from('N_PA_Cycle_Rater_Policy_Detail_Snapshot as dd')
                            ->join(
                                'N_PA_Cycle_Rater_Policy_Snapshot as ss',
                                'ss.Id_PA_Cycle_Rater_Policy_Snapshot',
                                '=',
                                'dd.PA_Cycle_Rater_Policy_Snapshot_Id',
                            )
                            ->whereColumn('ss.Id_PA_Cycle_Rater_Policy_Snapshot', 'p.PA_Cycle_Rater_Policy_Snapshot_Id')
                            ->where('ss.Flag_Aktif', 'Y')
                            ->where('dd.Flag_Aktif', 'Y')
                            // Kategori yang ditandai tidak diikutkan bukan kekurangan, dan
                            // minimum override peserta menang atas salinan level.
                            ->whereNotExists(function ($o) {
                                $o->from('N_PA_Participant_Rater_Override as ot')
                                    ->whereColumn('ot.PA_Participant_Id', 'p.Id_PA_Participant')
                                    ->whereColumn('ot.PA_Rater_Category_Id', 'dd.PA_Rater_Category_Id')
                                    ->where('ot.Flag_Aktif', 'Y')
                                    ->where('ot.Flag_Dipakai', 'T')
                                    ->selectRaw('1');
                            })
                            ->whereRaw(
                                '(SELECT COUNT(*) FROM N_PA_Assignment aa '
                                .'WHERE aa.PA_Participant_Id = p.Id_PA_Participant '
                                .'AND aa.PA_Rater_Category_Id = dd.PA_Rater_Category_Id '
                                ."AND aa.Flag_Aktif = 'Y') < COALESCE("
                                .'(SELECT TOP 1 CASE WHEN ISNULL(oy.Min_Rater_Selesai, 0) > oy.Min_Rater '
                                .'THEN oy.Min_Rater_Selesai ELSE oy.Min_Rater END '
                                .'FROM N_PA_Participant_Rater_Override oy '
                                .'WHERE oy.PA_Participant_Id = p.Id_PA_Participant '
                                .'AND oy.PA_Rater_Category_Id = dd.PA_Rater_Category_Id '
                                ."AND oy.Flag_Aktif = 'Y' AND oy.Flag_Dipakai = 'Y'), "
                                .'CASE WHEN ISNULL(dd.Min_Rater_Selesai, 0) > dd.Min_Rater '
                                .'THEN dd.Min_Rater_Selesai ELSE dd.Min_Rater END)',
                            )
                            ->selectRaw('1');
                    })

                    // 3. ada penilai yang sudah tidak aktif
                    ->orWhereExists(function ($sub) {
                        $sub->from('N_PA_Assignment as an')
                            ->leftJoin('Karyawan as kn', function ($j) {
                                $j->on('kn.Kode_Perusahaan', '=', 'an.Kode_Perusahaan_Penilai')
                                    ->on('kn.Kode_Karyawan', '=', 'an.Kode_Karyawan_Penilai');
                            })
                            ->whereColumn('an.PA_Participant_Id', 'p.Id_PA_Participant')
                            ->where('an.Flag_Aktif', 'Y')
                            ->where(fn ($x) => $x->whereNull('kn.Kode_Karyawan')->orWhere('kn.Aktif', '<>', 'Y'))
                            ->selectRaw('1');
                    });
            });
        }

        if (! empty($saringan['cari'])) {
            $kata = trim((string) $saringan['cari']);
            $q->where(function ($w) use ($kata) {
                $w->where('p.Nama_Karyawan_Saat_Cycle', 'like', "%{$kata}%")
                    ->orWhere('p.Kode_Karyawan', 'like', "{$kata}%");
            });
        }

        $total = (clone $q)->count();

        $rows = $q->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->forPage($halaman, $per)
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Perusahaan', 'p.Kode_Karyawan',
                'p.Nama_Karyawan_Saat_Cycle', 'p.ID_Level_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle', 'p.PA_Cycle_Rater_Policy_Snapshot_Id',
            ]);

        if ($rows->isEmpty()) {
            return ['baris' => [], 'total' => $total, 'halaman' => $halaman, 'per_halaman' => $per];
        }

        /* Penugasan seluruh peserta pada halaman ini, SATU kueri. */
        $penugasan = DB::table('N_PA_Assignment as a')
            ->leftJoin('Karyawan as k', function ($j) {
                $j->on('k.Kode_Perusahaan', '=', 'a.Kode_Perusahaan_Penilai')
                    ->on('k.Kode_Karyawan', '=', 'a.Kode_Karyawan_Penilai');
            })
            ->whereIn('a.PA_Participant_Id', $rows->pluck('Id_PA_Participant'))
            ->where('a.Flag_Aktif', 'Y')
            ->get([
                'a.PA_Participant_Id', 'a.PA_Rater_Category_Id', 'a.Nama_Penilai_Saat_Cycle',
                'a.Kode_Karyawan_Penilai', 'a.Status_Penugasan', 'k.Aktif as penilai_aktif',
            ])
            ->groupBy('PA_Participant_Id');

        $override = $this->overridePenilai($cycleId, $rows->pluck('Id_PA_Participant')->all());
        $kategoriKode = DB::table('N_PA_Rater_Category')
            ->pluck('Kode_Kategori', 'Id_PA_Rater_Category')
            ->all();

        $baris = $rows->map(function ($p) use ($penugasan, $syarat, $kategoriNama, $kategoriKode, $override) {
            $milik = $penugasan[$p->Id_PA_Participant] ?? collect();
            $perKategori = $milik->groupBy('PA_Rater_Category_Id');
            $butuh = $syarat[$p->PA_Cycle_Rater_Policy_Snapshot_Id] ?? collect();

            $masalah = [];

            if ($p->PA_Cycle_Rater_Policy_Snapshot_Id === null) {
                $masalah[] = [
                    'jenis' => 'TANPA_ATURAN',
                    'pesan' => 'Peserta ini tidak punya salinan aturan penilai; ia tidak akan pernah bisa dihitung.',
                ];
            }

            foreach ($butuh as $b) {
                $ada = ($perKategori[$b->kategori_id] ?? collect());
                $nama = $kategoriNama[$b->kategori_id] ?? "Kategori {$b->kategori_id}";
                $o = $override[$p->Id_PA_Participant.'|'.$b->kategori_id] ?? null;

                if ($o !== null && $o['dipakai'] === false) {
                    continue;
                }

                $min = $o['min'] ?? self::ambang($b->min_rater, $b->min_selesai);

                if ($ada->count() < $min) {
                    $masalah[] = [
                        'jenis' => 'KURANG',
                        /* Dibawa supaya layar bisa menawarkan "tandai tidak diikutkan" tepat
                           pada kategori ini, tanpa menebak dari kalimatnya. */
                        'kategori_id' => (int) $b->kategori_id,
                        'kode_kategori' => $kategoriKode[$b->kategori_id] ?? null,
                        'nama_kategori' => $nama,
                        'terpasang' => $ada->count(),
                        'min_rater' => $min,
                        'pesan' => sprintf(
                            '%s: %d dari %d penilai minimum.',
                            $nama, $ada->count(), $min,
                        ),
                    ];
                }

                $nonaktif = $ada->filter(fn ($x) => ($x->penilai_aktif ?? 'T') !== 'Y');

                if ($nonaktif->isNotEmpty()) {
                    $masalah[] = [
                        'jenis' => 'PENILAI_NONAKTIF',
                        'pesan' => sprintf(
                            '%s: %s sudah tidak aktif sebagai karyawan.',
                            $nama,
                            $nonaktif->pluck('Nama_Penilai_Saat_Cycle')->implode(', '),
                        ),
                    ];
                }
            }

            return [
                'participant_id' => (int) $p->Id_PA_Participant,
                'kode_karyawan' => $p->Kode_Karyawan,
                'nama' => $p->Nama_Karyawan_Saat_Cycle,
                'id_level' => (int) $p->ID_Level_Saat_Cycle,
                'nama_level' => $p->Nama_Level_Saat_Cycle,
                'divisi' => $p->Nama_Divisi_Saat_Cycle,
                'penilai' => $milik->map(fn ($x) => [
                    'nama' => $x->Nama_Penilai_Saat_Cycle,
                    'kode' => $x->Kode_Karyawan_Penilai,
                    'kategori' => $kategoriNama[$x->PA_Rater_Category_Id] ?? '-',
                    'status' => $x->Status_Penugasan,
                    'aktif' => ($x->penilai_aktif ?? 'T') === 'Y',
                ])->values()->all(),
                'masalah' => $masalah,
                'sehat' => $masalah === [],
            ];
        })->all();

        /* Tidak ada lagi penyaringan pasca-ambil di sini: saringannya sudah menjadi syarat SQL
           di atas, jadi `total` dan `baris` berbicara tentang himpunan yang sama.

           Penjaga: kalau saringannya aktif, tidak boleh ada baris SEHAT yang lolos. Kalau ada,
           berarti syarat SQL-nya dan perhitungan masalah di PHP tidak sepakat — dan
           ketidaksepakatan itu harus terdengar, bukan ditutup dengan menyaring ulang. */
        if (! empty($saringan['hanya_bermasalah'])) {
            $sehatLolos = count(array_filter($baris, fn ($b) => $b['sehat']));

            if ($sehatLolos > 0) {
                $baris = array_map(function (array $b) use ($sehatLolos) {
                    if ($b['sehat']) {
                        $b['masalah'][] = [
                            'jenis' => 'TIDAK_SEPAKAT',
                            'pesan' => 'Baris ini lolos saringan "bermasalah" tetapi dihitung sehat. '
                                .'Syarat penyaringan dan perhitungan masalah tidak sepakat; laporkan ini. '
                                ."({$sehatLolos} baris serupa di halaman ini)",
                        ];
                        $b['sehat'] = false;
                    }

                    return $b;
                }, $baris);
            }
        }

        return [
            'baris' => $baris,
            'total' => $total,
            'halaman' => $halaman,
            'per_halaman' => $per,

            /* Saringannya kini bekerja atas SELURUH himpunan, bukan per halaman. Field lama
               `saringan_per_halaman` dipertahankan supaya layar yang membacanya tidak pecah,
               tetapi nilainya sekarang selalu false — dan itu bukan kebohongan, itu keadaan
               yang baru. */
            'saringan_per_halaman' => false,
        ];
    }

    /**
     * Kategori yang ditandai SENGAJA tidak diikutkan bagi peserta tertentu, beserta sebabnya.
     *
     * Ditampilkan di Langkah 5 supaya keputusan itu tetap terlihat dan bisa dibatalkan —
     * kekurangan yang hilang dari daftar masalah tanpa jejak sama berbahayanya dengan
     * kekurangan yang tidak pernah dilaporkan.
     *
     * @return list<array<string, mixed>>
     */
    public function kategoriTidakDiikutkan(int $cycleId): array
    {
        return DB::table('N_PA_Participant_Rater_Override as o')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'o.PA_Participant_Id')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'o.PA_Rater_Category_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('o.Flag_Aktif', 'Y')
            ->where('o.Flag_Dipakai', 'T')
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('c.Id_PA_Rater_Category')
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Level_Saat_Cycle', 'c.Id_PA_Rater_Category', 'c.Kode_Kategori',
                'c.Nama_Kategori', 'o.Alasan_Perubahan', 'o.Created_By', 'o.Created_At',
            ])
            ->map(fn ($r) => [
                'participant_id' => (int) $r->Id_PA_Participant,
                'kode_karyawan' => $r->Kode_Karyawan,
                'nama' => $r->Nama_Karyawan_Saat_Cycle,
                'nama_level' => $r->Nama_Level_Saat_Cycle,
                'kategori_id' => (int) $r->Id_PA_Rater_Category,
                'kode_kategori' => $r->Kode_Kategori,
                'nama_kategori' => $r->Nama_Kategori,
                'alasan' => PaKategoriTidakDiikutkanService::alasanTanpaTanda($r->Alasan_Perubahan),
                'oleh' => $r->Created_By === null ? null : (int) $r->Created_By,
                'pada' => $r->Created_At,
            ])
            ->values()
            ->all();
    }

    /**
     * Override penilai aktif per (peserta, kategori): apakah kategorinya dipakai, dan
     * minimum penilai milik peserta itu bila diubah.
     *
     * @param  list<int>|null  $hanyaPeserta
     * @return array<string, array{dipakai:bool, min:int|null}>
     */
    private function overridePenilai(int $cycleId, ?array $hanyaPeserta = null): array
    {
        $q = DB::table('N_PA_Participant_Rater_Override as o')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'o.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('o.Flag_Aktif', 'Y');

        if ($hanyaPeserta !== null) {
            $q->whereIn('o.PA_Participant_Id', $hanyaPeserta);
        }

        $hasil = [];

        foreach ($q->get(['o.PA_Participant_Id', 'o.PA_Rater_Category_Id', 'o.Flag_Dipakai', 'o.Min_Rater', 'o.Min_Rater_Selesai']) as $r) {
            $dipakai = $r->Flag_Dipakai !== 'T';
            $hasil[$r->PA_Participant_Id.'|'.$r->PA_Rater_Category_Id] = [
                'dipakai' => $dipakai,
                'min' => $dipakai && $r->Min_Rater !== null ? self::ambang($r->Min_Rater, $r->Min_Rater_Selesai) : null,
            ];
        }

        return $hasil;
    }

    /** Penilai terpasang yang dibutuhkan agar kategori BISA dihitung: maks(min ditugaskan, min selesai). */
    private static function ambang(mixed $minRater, mixed $minSelesai): int
    {
        return max((int) $minRater, $minSelesai === null ? 0 : (int) $minSelesai);
    }
}
