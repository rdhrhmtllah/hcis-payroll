<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaBijeksiLabel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pengikatan paket soal ke pasangan (level × kategori penilai).
 *
 * Inilah matriks "RATER × Level" yang dimaksud HC: Manager yang menilai DIRI SENDIRI membaca
 * susunan soal yang berbeda dari yang dibaca ATASAN-nya, dan berbeda lagi dari BAWAHAN dan
 * PEER. Sel yang dikosongkan berarti kategori itu memang tidak menilai level tersebut —
 * tanda "x" di gambar HC.
 *
 * PAKET BAWAAN, DAN URUTAN PENCARIANNYA
 *
 * Baris dengan `PA_Rater_Category_Id` NULL adalah paket BAWAAN level itu: dipakai kategori
 * mana pun yang tidak punya paket sendiri. Pencariannya WAJIB mencocokkan kategori tepat
 * lebih dulu, baru jatuh ke bawaan. Dibalik, bawaan selalu menang dan paket khusus kategori
 * tidak pernah terpakai walau sudah dibuat — tanpa satu pun pesan.
 *
 * BEDA "KOSONG" DAN "BELUM DIISI"
 *
 * Keduanya terlihat sama di layar sebagai sel kosong, tetapi artinya berlawanan: yang
 * pertama keputusan ("bawahan tidak menilai staff"), yang kedua kelalaian ("belum sempat").
 * Yang membedakan bukan tabel ini, melainkan aturan penilai siklus: kategori yang TIDAK ADA
 * di aturan penilai level itu memang tidak menilai, dan selnya sah kosong. Kategori yang ADA
 * di aturan tetapi tanpa paket adalah lubang — penilainya akan membuka formulir kosong.
 */
class PaQuestionBindingService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaCycleSetupStepService $langkah,
        private readonly PaHcLearnCatalogService $katalog,
    ) {}

    /**
     * Matriks pengikatan satu siklus: baris level, kolom kategori penilai.
     *
     * @return array<string, mixed>
     */
    public function matriks(int $cycleId): array
    {
        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Rater_Category')
            ->get(['Id_PA_Rater_Category as id', 'Kode_Kategori as kode', 'Nama_Kategori as nama'])
            /* `id` DIPAKSA int. SQL Server memulangkan bilangan sebagai string, dan
               perbandingan ketat di bawah (`=== $k['id']`) akan selalu salah tanpa ini —
               setiap sel tampak berlubang walau paketnya sudah terikat, dan tak ada satu
               pun galat yang muncul. */
            ->map(fn ($k) => ['id' => (int) $k->id, 'kode' => $k->kode, 'nama' => $k->kode === 'PEER' ? 'Peer' : $k->nama])
            ->all();

        /* Kategori yang BENAR-BENAR menilai tiap level, dibaca dari aturan penilai siklus.
           Inilah yang membedakan sel "sengaja kosong" dari sel "lubang". */
        $berlaku = DB::table('N_PA_Cycle_Rater_Policy_Snapshot as s')
            ->join('N_PA_Cycle_Rater_Policy_Detail_Snapshot as d',
                'd.PA_Cycle_Rater_Policy_Snapshot_Id', '=', 's.Id_PA_Cycle_Rater_Policy_Snapshot')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->where('s.Flag_Aktif', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->get(['s.ID_Level', 'd.PA_Rater_Category_Id'])
            ->groupBy('ID_Level')
            ->map(fn ($g) => $g->pluck('PA_Rater_Category_Id')->map(fn ($x) => (int) $x)->flip());

        $paket = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get([
                'Id_PA_Cycle_Question_Package as id', 'ID_Level', 'PA_Rater_Category_Id',
                'Id_Master_Ujian as kode', 'Nama_Ujian_Sumber as nama',
            ]);

        /* DUA cacah, dan bedanya penting: satu kompetensi diukur beberapa soal. Cacah soal
           dipakai sebagai keterangan; cacah KOMPETENSI yang dibandingkan dengan syarat
           level. Menyamakannya membuat paket 22 soal tampak melebihi syarat 17 kompetensi
           padahal bisa saja ada kompetensi yang belum tersentuh sama sekali. */
        $butir = DB::table('N_PA_Cycle_Question_Package_Item')
            ->whereIn('PA_Cycle_Question_Package_Id', $paket->pluck('id')->all())
            ->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Cycle_Question_Package_Id')
            ->get([
                'PA_Cycle_Question_Package_Id',
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('COUNT(DISTINCT PA_Competency_Id) as kompetensi'),
            ])
            ->keyBy('PA_Cycle_Question_Package_Id');

        $perLevel = $paket->groupBy('ID_Level');

        /* Pilihan BAKU dari Master Data, dibaca sebagai USULAN.

           Dibaca tabelnya langsung, bukan lewat `PaQuestionBindingMasterService`: layanan
           itu bergantung pada katalog, yang bergantung pada layanan ini — memanggilnya dari
           sini menutup lingkarannya dan container gagal membangun keduanya.

           Usulan ini TIDAK ditulis ke siklus. Menulis saat halaman dibuka berarti satu
           kunjungan mengubah data, dan admin yang hanya melihat-lihat meninggalkan
           pengikatan yang tidak ia maksudkan. Yang dilakukan hanya menampilkannya, dan
           menyediakan satu klik untuk menerimanya. */
        /* Katalog HC Learn, dibaca SEKALI. Dipakai memvalidasi tiap usulan master: paket
           yang dulu tepat bisa sudah tidak lagi, entah karena isinya berubah di HC Learn
           atau karena dimensi level ini ditambah di Master Data. */
        $katalogPaket = collect($this->katalog->daftarPaket()['paket'] ?? [])
            ->keyBy('Id_Master_Ujian');

        $usulan = DB::table('N_PA_Question_Binding_Master')
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Question_Binding_Master', 'ID_Level', 'PA_Rater_Category_Id',
                'Id_Master_Ujian', 'Nama_Ujian_Sumber', 'Jumlah_Kompetensi', 'Jumlah_Bagian'])
            ->groupBy('ID_Level');

        // Label seluruh usulan sekaligus: memeriksanya per sel di bawah adalah satu kueri per sel.
        $labelUsulan = app(PaQuestionBindingMasterService::class)
            ->labelSelBanyak($usulan->flatten(1)->pluck('Id_PA_Question_Binding_Master')->all());

        /* Dimensi wajib tiap level, dari matriks Master Data. Dikirim supaya layar bisa
           menandai paket mana yang COCOK sebelum diklik — penolakan yang baru muncul
           sesudah admin memilih adalah cara paling melelahkan menyampaikan aturan. */
        $dimensiWajib = DB::table('N_PA_Competency_Requirement as r')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'r.PA_Competency_Type_Id')
            ->where('r.Flag_Aktif', 'Y')
            ->where('t.Flag_Aktif', 'Y')
            ->orderBy('t.Urutan_Tampil')
            ->get(['r.ID_Level', 't.Id_PA_Competency_Type as tipe_id', 't.Kode_Type as kode', 't.Nama_Type as nama'])
            ->groupBy('ID_Level');

        /* Kompetensi di bawah tiap dimensi. Dimensi saja terlalu kasar untuk memilih paket:
           "level ini butuh Kompetensi Manajerial" tidak memberi tahu apakah paket yang
           memuat sembilan butir sudah cukup atau kurang dua. */
        // Per "level|jenis" dari salinan siklus bila ada, selain itu matriks master (DDL-2026-175).
        $kompetensiPerTipe = app(PaKompetensiMatriksService::class)->perLevelJenisSiklus($cycleId);

        $level = DB::table('N_PA_Cycle_Rater_Policy_Snapshot as s')
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', 's.ID_Level')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->where('s.Flag_Aktif', 'Y')
            ->orderBy('s.ID_Level')
            ->get(['s.ID_Level as id_level', 'l.Nama_Level as nama_level'])
            ->map(function ($l) use ($kategori, $perLevel, $butir, $berlaku, $dimensiWajib, $kompetensiPerTipe, $usulan, $katalogPaket, $labelUsulan) {
                $milik = $perLevel[$l->id_level] ?? collect();
                $usulanLevel = $usulan[$l->id_level] ?? collect();
                $usulanBaku = $usulanLevel->first(fn ($u) => $u->PA_Rater_Category_Id === null);
                $bawaan = $milik->first(fn ($p) => $p->PA_Rater_Category_Id === null);
                $menilai = $berlaku[$l->id_level] ?? collect();

                /* Id kompetensi wajib LEVEL INI — pembanding untuk memvalidasi usulan.
                   `$dimensiWajib` berkelompok per level; memakainya langsung akan
                   mengumpulkan kompetensi SELURUH level dan usulan apa pun tampak kurang.

                   ID, bukan KODE: pemetaan bagian→kompetensi menyimpan PA_Competency_Id,
                   dan membandingkan dua satuan yang berbeda akan melaporkan setiap usulan
                   kurang seluruh kompetensinya. */
                $kompetensiWajibId = ($dimensiWajib[$l->id_level] ?? collect())
                    // Kolomnya sudah DIALIASKAN di kueri di atas. Memetik nama aslinya
                    // memulangkan null untuk setiap baris, `unique()` meringkasnya jadi satu
                    // null, dan setiap usulan dilaporkan "kurang 1 kompetensi" dengan kode
                    // kosong -- persis gejala yang pernah muncul.
                    ->flatMap(fn ($d) => ($kompetensiPerTipe[$l->id_level.'|'.$d->tipe_id] ?? collect())->pluck('id'))
                    ->filter()
                    ->map(fn ($x) => (int) $x)
                    ->unique()
                    ->values()
                    ->all();

                $sel = [];
                $lubang = 0;

                foreach ($kategori as $k) {
                    $khusus = $milik->first(fn ($p) => (int) $p->PA_Rater_Category_Id === $k['id']);
                    $dipakai = $khusus ?? $bawaan;
                    $ikutMenilai = $menilai->has($k['id']);

                    if ($ikutMenilai && $dipakai === null) {
                        $lubang++;
                    }

                    /* Usulan master untuk sel ini: kategori tepat lebih dulu, baru baku
                       level — urutan yang sama dengan pencarian paket siklus, supaya yang
                       diusulkan sama dengan yang akan benar-benar berlaku. */
                    $u = $usulanLevel->first(fn ($x) => (int) $x->PA_Rater_Category_Id === $k['id'])
                        ?? $usulanBaku;

                    /* USULAN DIVALIDASI SETIAP KALI DIBACA, bukan dipercaya karena pernah
                       sah saat ditetapkan.

                       Dua hal bisa membuatnya gugur, dan keduanya terjadi di luar sepengetahuan
                       master: paketnya berubah di HC Learn, atau dimensi level ini ditambah di
                       Master Data. Usulan yang gugur TIDAK ditampilkan sebagai bawaan —
                       menampilkannya berarti admin menekan "Pakai usulan" dan ditolak 422,
                       lalu menyimpulkan sistemnya rusak.

                       Alasan gugurnya tetap disebut. Membuatnya kosong tanpa keterangan
                       membuat admin mengisi manual berulang kali tanpa tahu bahwa masternya
                       yang perlu dibereskan. */
                    $usulanGugur = null;

                    if ($u !== null) {
                        $usulanGugur = $this->alasanUsulanGugur($u, $katalogPaket, $kompetensiWajibId, $labelUsulan[(int) $u->Id_PA_Question_Binding_Master] ?? []);

                        if ($usulanGugur !== null) {
                            $u = null;
                        }
                    }

                    $sel[$k['kode']] = [
                        'paket_id' => $dipakai?->id !== null ? (int) $dipakai->id : null,
                        'kode_paket' => $dipakai->kode ?? null,
                        'nama_paket' => $dipakai->nama ?? null,
                        'jumlah_butir' => $dipakai === null ? 0 : (int) ($butir[$dipakai->id]->jumlah ?? 0),
                        'jumlah_kompetensi' => $dipakai === null ? 0 : (int) ($butir[$dipakai->id]->kompetensi ?? 0),
                        // Membedakan paket milik kategori ini dari paket bawaan yang
                        // kebetulan berlaku. Layar harus menampilkan keduanya berbeda.
                        'khusus' => $khusus !== null,
                        // Kategori ini memang menilai level ini menurut aturan penilai.
                        'ikut_menilai' => $ikutMenilai,

                        /* Apa yang DIUSULKAN master untuk sel ini. Ditampilkan pada sel
                           kosong sebagai bawaan yang bisa diterima satu klik. */
                        /* Kenapa usulan masternya tidak dipakai. `null` bila usulannya
                           memang sahih atau master belum punya usulan sama sekali. */
                        'usulan_master_gugur' => $usulanGugur,

                        'usulan_master' => $u === null ? null : [
                            /* Id ikatan master: pemilih paket membaca mapping bagiannya untuk pratinjau. */
                            'id_ikatan' => (int) $u->Id_PA_Question_Binding_Master,
                            'kode_paket' => $u->Id_Master_Ujian,
                            'nama_paket' => $u->Nama_Ujian_Sumber,
                            'jumlah_kompetensi' => (int) $u->Jumlah_Kompetensi,
                            'jumlah_bagian' => (int) $u->Jumlah_Bagian,
                        ],

                        /* Apakah yang terpasang SAMA dengan usulan master.

                           `null` bila selnya masih kosong atau master belum punya usulan —
                           dibedakan dari `false`, karena "belum diisi" dan "sengaja
                           disesuaikan" adalah dua hal yang berlawanan artinya, dan layar
                           yang menyatukannya akan menuduh admin menyimpang padahal ia belum
                           mulai. */
                        'sesuai_master' => ($dipakai === null || $u === null)
                            ? null
                            : $dipakai->kode === $u->Id_Master_Ujian,
                    ];
                }

                return [
                    'id_level' => (int) $l->id_level,
                    'nama_level' => $l->nama_level ?? "Level {$l->id_level}",
                    'dimensi_wajib' => ($dimensiWajib[$l->id_level] ?? collect())
                        ->map(fn ($d) => ['kode' => $d->kode, 'nama' => $d->nama])
                        ->values()
                        ->all(),

                    /* Kompetensi konkret yang berlaku di level ini, dikelompokkan per
                       dimensi. Dipakai layar untuk menampilkan "level ini membutuhkan apa"
                       saat admin memilih paket, dan untuk menandai butir mana yang tidak
                       tercakup paket pilihannya. */
                    'kompetensi_wajib' => ($dimensiWajib[$l->id_level] ?? collect())
                        ->map(fn ($d) => [
                            'dimensi_kode' => $d->kode,
                            'dimensi_nama' => $d->nama,
                            'kompetensi' => ($kompetensiPerTipe[$l->id_level.'|'.$d->tipe_id] ?? collect())
                                ->map(fn ($c) => ['kode' => $c->kode, 'nama' => $c->nama])
                                ->values()
                                ->all(),
                        ])
                        ->values()
                        ->all(),
                    'paket_bawaan' => $bawaan === null ? null : [
                        'paket_id' => (int) $bawaan->id,
                        'kode_paket' => $bawaan->kode,
                        'jumlah_butir' => (int) ($butir[$bawaan->id]->jumlah ?? 0),
                        'jumlah_kompetensi' => (int) ($butir[$bawaan->id]->kompetensi ?? 0),
                    ],
                    'sel' => $sel,
                    'lubang' => $lubang,
                ];
            })
            ->all();

        return [
            'kategori' => $kategori,
            'level' => $level,

            /* Sel yang penilainya ADA tetapi paketnya tidak. Penilai di sel ini akan
               membuka formulir kosong: tidak ada galat, tidak ada pesan, hanya layar tanpa
               pertanyaan. Ini angka yang paling perlu ditonjolkan di layar. */
            'total_lubang' => array_sum(array_column($level, 'lubang')),

            /* Berapa sel yang SENGAJA berbeda dari master. Dikirim supaya layar bisa
               mengatakannya di kepala halaman — penyimpangan yang hanya terlihat kalau
               setiap sel diperiksa satu per satu sama saja dengan tidak terlihat. */
            /* Berapa sel yang usulan masternya GUGUR. Angka ini menunjuk ke Master Data,
               bukan ke siklus ini: yang perlu dibereskan ada di sana. */
            'total_usulan_gugur' => array_sum(array_map(
                fn ($l) => count(array_filter(
                    $l['sel'],
                    fn ($s) => ($s['usulan_master_gugur'] ?? null) !== null,
                )),
                $level,
            )),

            'total_disesuaikan' => array_sum(array_map(
                fn ($l) => count(array_filter(
                    $l['sel'],
                    fn ($s) => ($s['sesuai_master'] ?? null) === false,
                )),
                $level,
            )),
            'boleh_disunting' => $this->gate->boleh(PaCycleGate::PILIH_PAKET_SOAL, $cycleId),
        ];
    }

    /**
     * Kenapa satu usulan master tidak lagi bisa dipakai — atau `null` bila masih sahih.
     *
     * Aturannya SAMA dengan gerbang impor, dan sengaja dipinjam dari penulis yang sama
     * (`PaBijeksiLabel`). Kalau di sini lebih longgar, usulan yang ditampilkan sebagai bawaan
     * akan ditolak saat ditekan; kalau lebih ketat, usulan yang sebenarnya sah disembunyikan
     * tanpa sebab. Dua salinan aturan yang sama adalah cara keduanya kelak berselisih.
     *
     * KOMPETENSI TIDAK LAGI DATANG DARI HC LEARN. Sejak kontrak barunya, bagian dilempar
     * tanpa label, jadi yang dibandingkan di sini adalah PEMETAAN yang dibuat admin di
     * halaman Ikatan Paket Soal — bukan isi katalog.
     *
     * @param  list<int>  $kompetensiWajibId
     * @param  array<string, int>  $label  dari `labelSelBanyak()`, diambil sekali untuk seluruh matriks
     */
    private function alasanUsulanGugur(object $u, $katalogPaket, array $kompetensiWajibId, array $label): ?string
    {
        $ujian = $katalogPaket[$u->Id_Master_Ujian] ?? null;

        if ($ujian === null) {
            return sprintf(
                'Ujian %s tidak ada lagi di katalog HC Learn.',
                $u->Id_Master_Ujian,
            );
        }

        if ($kompetensiWajibId === []) {
            return 'Level ini belum punya dimensi penilaian di Master Data > Dimensi per Level.';
        }

        if ($label === []) {
            return sprintf(
                'Ujian %s belum dipetakan ke kompetensi. Labeli tiap bagiannya lebih dulu di '
                .'Master Data > Ikatan Paket Soal.',
                $u->Id_Master_Ujian,
            );
        }

        $masalah = PaBijeksiLabel::periksa($ujian['Bagian'] ?? [], $kompetensiWajibId, $label);

        return $masalah === [] ? null : $masalah[0]['pesan'];
    }

    /**
     * Mengikat satu paket yang SUDAH ADA di siklus ini ke satu sel (level × kategori).
     *
     * `raterCategoryId` NULL berarti menjadikannya paket bawaan level itu.
     *
     * @return array<string, mixed>
     */
    public function ikat(int $cycleId, int $paketId, ?int $raterCategoryId, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::PILIH_PAKET_SOAL, $cycleId);

        $paket = DB::table('N_PA_Cycle_Question_Package')
            ->where('Id_PA_Cycle_Question_Package', $paketId)
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Cycle_Question_Package', 'ID_Level', 'Id_Master_Ujian', 'PA_Rater_Category_Id']);

        if ($paket === null) {
            /* Menyebut siklusnya, bukan hanya id. Kekeliruan paling lazim di sini adalah
               mengirim id paket milik siklus lain, dan "paket tidak ditemukan" tidak
               memberi tahu bahwa itu yang terjadi. */
            throw new RuntimeException(
                "Paket {$paketId} tidak ada pada siklus {$cycleId}, atau sudah dinonaktifkan."
            );
        }

        if ($raterCategoryId !== null) {
            $adaKategori = DB::table('N_PA_Rater_Category')
                ->where('Id_PA_Rater_Category', $raterCategoryId)
                ->where('Flag_Aktif', 'Y')
                ->exists();

            if (! $adaKategori) {
                throw new RuntimeException("Kategori penilai {$raterCategoryId} tidak dikenal.");
            }
        }

        $bentrok = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level', $paket->ID_Level)
            ->where('Flag_Aktif', 'Y')
            ->where('Id_PA_Cycle_Question_Package', '<>', $paketId)
            ->when(
                $raterCategoryId === null,
                fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
            )
            ->first(['Id_Master_Ujian']);

        if ($bentrok !== null) {
            /* Ditangkap SEBELUM menulis, bukan dibiarkan menabrak indeks unik. Pelanggaran
               indeks memulangkan pesan database yang tidak menyebut paket mana yang sudah
               menempati selnya. */
            throw new RuntimeException(sprintf(
                'Sel ini sudah ditempati paket %s. Lepaskan yang lama lebih dulu bila ingin menggantinya.',
                $bentrok->Id_Master_Ujian,
            ));
        }

        DB::table('N_PA_Cycle_Question_Package')
            ->where('Id_PA_Cycle_Question_Package', $paketId)
            ->update([
                'PA_Rater_Category_Id' => $raterCategoryId,
                'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                'Updated_By' => $olehUserId,
            ]);

        $this->tandaiLangkah($cycleId, $olehUserId);

        return [
            'paket_id' => $paketId,
            'kode_paket' => $paket->Id_Master_Ujian,
            'id_level' => (int) $paket->ID_Level,
            'rater_category_id' => $raterCategoryId,
        ];
    }

    /**
     * Menandai langkah PAKET_SOAL dari lubang yang tersisa.
     *
     * Dihitung dari sel yang penilainya ADA tetapi paketnya tidak — bukan dari "berapa level
     * punya paket". Level yang punya satu paket bawaan tampak lengkap pada hitungan lama,
     * padahal tiga kategori penilainya membaca susunan yang tidak pernah dimaksudkan untuk
     * mereka.
     */
    private function tandaiLangkah(int $cycleId, ?int $olehUserId): void
    {
        $m = $this->matriks($cycleId);
        $lubang = $m['total_lubang'];

        $this->langkah->tandai(
            $cycleId,
            'PAKET_SOAL',
            $lubang === 0
                ? PaCycleSetupStepService::STATUS_SELESAI
                : PaCycleSetupStepService::STATUS_DRAFT,
            $lubang === 0
                ? sprintf('%d level, seluruh kategori penilainya bersoal.', count($m['level']))
                : sprintf('%d sel penilai masih tanpa paket soal.', $lubang),
            $olehUserId,
        );
    }
}
