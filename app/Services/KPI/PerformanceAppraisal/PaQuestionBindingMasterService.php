<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaBijeksiLabel;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pilihan paket soal BAKU per (level × kategori penilai), dan pemeriksaan kesahihannya.
 *
 * MASALAH YANG DISELESAIKAN
 *
 * Pengikatan paket hanya ada per siklus, sehingga setiap siklus baru mengulang pengisian
 * dua puluh sel dari nol — pekerjaan yang sama, dengan peluang salah yang sama besar tiap
 * kali. Master ini menyimpan pilihan bakunya; siklus baru menyalinnya lalu admin hanya
 * menyesuaikan yang berbeda.
 *
 * YANG DISIMPAN: PILIHAN UJIANNYA, PLUS PEMETAAN KOMPETENSINYA
 *
 * Header menyimpan id UJIAN — satuan yang dijadwalkan HC Learn kepada peserta, bukan
 * paketnya. Isi soalnya tetap tidak disalin: master yang menyimpan isi akan diam-diam
 * menyajikan susunan usang yang tidak lagi cocok dengan formulir yang dibuka penilai.
 *
 * Yang DISALIN adalah pemetaannya. Sejak kontrak baru HC Learn, bagian soal dilempar tanpa
 * label kompetensi, sehingga kompetensi menjadi milik kita — dan `N_PA_Question_Binding_-
 * Master_Detail` menyimpan pemetaan satu-satu bagian↔kompetensi yang dibuat admin.
 *
 * ENAM HAL YANG DIPERIKSA
 *
 * 1. `UJIAN_HILANG` — ujiannya tak ada lagi di katalog. Selnya tampak terisi tetapi tidak
 *    bisa disalin ke siklus mana pun.
 * 2. `BAGIAN_KEMBAR` — dua bagian beridentitas sama. Diperiksa PALING DULU: selama ambigu,
 *    kelima pemeriksaan lain tidak bermakna karena keduanya menunjuk baris yang sama.
 * 3. `BAGIAN_HILANG` — bagian yang sudah dilabeli tak ada lagi di ujiannya.
 * 4. `JUMLAH_TAK_SEPADAN` — cacah bagian tak sama dengan cacah kompetensi wajib. Bisa dari
 *    sisi HC Learn, bisa dari sisi kita; pesannya menyebut keduanya.
 * 5. `KOMPETENSI_KURANG` dan 6. `KOMPETENSI_LEBIH` — himpunan labelnya tak sepadan.
 *
 * KAPAN DIPERIKSA
 *
 * Hanya di DUA gerbang penulisan: `simpan()` dan `salinKeSiklus()`, ditambah tombol periksa
 * manual. `matriks()` TIDAK memanggil HC Learn sama sekali — ia membaca `Status_Kesehatan`
 * yang tersimpan, beserta cap waktunya, supaya halaman ini tidak ikut mati ketika HC Learn
 * mati.
 *
 * `salinKeSiklus()` tetap memeriksa SEGAR dan tidak memercayai status tersimpan: status itu
 * bisa berumur berminggu-minggu, dan memercayainya di gerbang penyalinan berarti meloloskan
 * ujian rusak ke siklus baru — persis kerusakan yang mau dicegah.
 */
class PaQuestionBindingMasterService
{
    public function __construct(
        private readonly PaHcLearnCatalogService $katalog,
        private readonly PaCycleGate $gate,
    ) {}

    /**
     * Kompetensi wajib satu level, sebagai daftar Id_PA_Competency.
     *
     * SATU penulis untuk daftar ini. Layar, gerbang simpan, pemeriksaan kesehatan, dan impor
     * per-sel semuanya membacanya dari sini — perhitungan yang disalin akan berselisih
     * diam-diam begitu salah satunya diperbaiki, dan selisihnya muncul sebagai penolakan
     * tanpa sebab yang jelas.
     *
     * @return list<int>
     */
    public function kompetensiWajibLevel(int $levelId): array
    {
        /* Dari Matriks Kompetensi per KOMPETENSI (DDL-2026-175), bukan penjabaran
           "jenis wajib → seluruh kompetensi aktif jenis itu". */
        return app(PaKompetensiMatriksService::class)->idWajibLevel($levelId);
    }

    /**
     * Pemetaan bagian → kompetensi satu sel, berkunci pasangan identitasnya.
     *
     * @return array<string, int>
     */
    public function labelSel(int $bindingId): array
    {
        return $this->labelSelBanyak([$bindingId])[$bindingId];
    }

    /**
     * `labelSel()` untuk banyak sel sekaligus — satu kueri per 1.000 ikatan, bukan satu per sel.
     * Matriks siklus memeriksa usulan setiap level × kategori; memanggil `labelSel()` per sel
     * membuatnya satu kueri per sel. Setiap id yang diminta selalu ada di hasil (`[]` bila
     * belum berlabel), jadi pemanggil tidak perlu membedakan "tidak diambil" dari "kosong".
     *
     * @param  list<int>  $bindingIds
     * @return array<int, array<string, int>>
     */
    public function labelSelBanyak(array $bindingIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $bindingIds)));
        $hasil = array_fill_keys($ids, []);

        foreach (array_chunk($ids, 1000) as $potongan) {
            DB::table('N_PA_Question_Binding_Master_Detail')
                ->whereIn('PA_Question_Binding_Master_Id', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->get(['PA_Question_Binding_Master_Id', 'Id_Jenis_Indikator', 'Id_Jenis_Soal', 'PA_Competency_Id'])
                ->each(function ($d) use (&$hasil) {
                    $hasil[(int) $d->PA_Question_Binding_Master_Id][$d->Id_Jenis_Indikator.'|'.$d->Id_Jenis_Soal] = (int) $d->PA_Competency_Id;
                });
        }

        return $hasil;
    }

    /**
     * Label sel yang dicari lewat identitas SELNYA, bukan id ikatannya.
     *
     * Layar Langkah 2 tidak pernah memegang id ikatan — ia hanya tahu sel mana yang
     * sedang ia isi dan ujian apa yang dipilih. Kosong berarti sel itu memang belum
     * pernah dilabeli untuk ujian tersebut, dan itu jawaban yang sah, bukan kegagalan.
     *
     * @return array<string, int>
     */
    public function labelSelUntuk(int $levelId, ?int $raterCategoryId, string $idMasterUjian): array
    {
        $bindingId = DB::table('N_PA_Question_Binding_Master')
            ->where('ID_Level', $levelId)
            ->where('Id_Master_Ujian', $idMasterUjian)
            ->where('Flag_Aktif', 'Y')
            ->when(
                $raterCategoryId === null,
                fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
            )
            ->value('Id_PA_Question_Binding_Master');

        return $bindingId === null ? [] : $this->labelSel((int) $bindingId);
    }

    /**
     * Label untuk mengisi dialog pelabelan: milik sel ini bila ada, kalau tidak SALINAN
     * dari sel serumpun.
     *
     * Serumpun = level yang sama, ujian yang sama, kategori penilai yang berbeda. Aman
     * disalin karena kompetensi wajib ditentukan per LEVEL saja — `kompetensiWajibLevel()`
     * tidak mengenal kategori penilai — sehingga pemetaan yang benar untuk satu sel pasti
     * benar juga untuk tetangganya sebaris. Mengetik ulang dua puluh satu baris untuk
     * hasil yang sudah pasti sama adalah pekerjaan yang tidak perlu ada.
     *
     * SALINAN, BUKAN RUJUKAN. Yang dipulangkan hanya isian awal; admin tetap menyimpannya
     * sebagai ikatan sel ini sendiri. Membuat sel meminjam ikatan tetangganya adalah
     * cacat yang baru saja ditutup di jalur impor — di sana peminjaman terjadi diam-diam
     * dan membuat sel yang tak pernah dilabeli tetap lolos.
     *
     * `dari` ikut dipulangkan supaya layar bisa mengatakan salinannya datang dari mana.
     * Isian yang muncul sendiri tanpa sebab akan dikira pekerjaan yang sudah pernah
     * dilakukan, lalu disimpan tanpa dibaca.
     *
     * @return array{label: array<string, int>, dari: ?array{rater_category_id: ?int, nama: ?string}}
     */
    public function labelAcuanSel(int $levelId, ?int $raterCategoryId, string $idMasterUjian): array
    {
        $milik = $this->labelSelUntuk($levelId, $raterCategoryId, $idMasterUjian);

        if ($milik !== []) {
            return ['label' => $milik, 'dari' => null];
        }

        $serumpun = DB::table('N_PA_Question_Binding_Master as m')
            ->leftJoin('N_PA_Rater_Category as k', 'k.Id_PA_Rater_Category', '=', 'm.PA_Rater_Category_Id')
            ->where('m.ID_Level', $levelId)
            ->where('m.Id_Master_Ujian', $idMasterUjian)
            ->where('m.Flag_Aktif', 'Y')
            ->when(
                $raterCategoryId === null,
                fn ($q) => $q->whereNotNull('m.PA_Rater_Category_Id'),
                fn ($q) => $q->where(fn ($w) => $w
                    ->whereNull('m.PA_Rater_Category_Id')
                    ->orWhere('m.PA_Rater_Category_Id', '<>', $raterCategoryId)),
            )
            /* Yang TERBARU, bukan yang pertama ditemukan. Bila satu baris punya beberapa
               sel berlabel, yang paling akhir disunting adalah yang paling mungkin sudah
               diperbaiki. */
            ->orderByDesc('m.Id_PA_Question_Binding_Master')
            ->first(['m.Id_PA_Question_Binding_Master as id', 'm.PA_Rater_Category_Id as rater', 'k.Nama_Kategori as nama']);

        if ($serumpun === null) {
            return ['label' => [], 'dari' => null];
        }

        return [
            'label' => $this->labelSel((int) $serumpun->id),
            'dari' => [
                'rater_category_id' => $serumpun->rater === null ? null : (int) $serumpun->rater,
                'nama' => $serumpun->nama ?? 'Bawaan level',
            ],
        ];
    }

    /**
     * Matriks baku, beserta hasil pemeriksaan tiap selnya terhadap katalog HC Learn.
     *
     * @return array<string, mixed>
     */
    public function matriks(): array
    {
        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Rater_Category')
            ->get(['Id_PA_Rater_Category as id', 'Kode_Kategori as kode', 'Nama_Kategori as nama'])
            ->map(fn ($k) => ['id' => (int) $k->id, 'kode' => $k->kode, 'nama' => $k->nama])
            ->all();

        /* TIDAK ada panggilan ke HC Learn di sini.
           |
           | Versi lama memeriksa setiap sel terhadap katalog pada SETIAP kunjungan halaman.
           | Akibatnya halaman ini ikut mati ketika HC Learn mati atau lambat, dan masalah
           | pada sel yang halamannya tak pernah dibuka tidak pernah terdeteksi juga.
           |
           | Yang ditampilkan sekarang adalah Status_Kesehatan TERSIMPAN, ditulis di dua
           | gerbang penulisan atau oleh tombol periksa manual, beserta cap waktunya supaya
           | tidak ada yang mengiranya realtime. */
        $ikatan = DB::table('N_PA_Question_Binding_Master')
            ->where('Flag_Aktif', 'Y')
            ->get()
            ->groupBy('ID_Level');

        /* Dimensi wajib tiap level — sumber pemeriksaan cakupan. Perubahan di sini terjadi
           di sisi KITA, dan master tidak akan tahu tanpa diperiksa ulang. */
        $wajib = DB::table('N_PA_Competency_Requirement as r')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'r.PA_Competency_Type_Id')
            ->where('r.Flag_Aktif', 'Y')
            ->where('t.Flag_Aktif', 'Y')
            ->get(['r.ID_Level', 't.Id_PA_Competency_Type as tipe_id', 't.Kode_Type as kode', 't.Nama_Type as nama'])
            ->groupBy('ID_Level');

        // Per "level|jenis" dari Matriks Kompetensi per kompetensi (DDL-2026-175).
        $kompetensiPerTipe = app(PaKompetensiMatriksService::class)->perLevelJenis();

        /* Kategori yang BENAR-BENAR menilai tiap level, dari MASTER aturan penilai.
           Halaman ini tidak punya siklus, jadi sumbernya `N_PA_Rater_Policy` — padanan
           master dari snapshot yang dipakai matriks siklus.

           Tanpa ini, sel untuk kategori yang tidak menilai level itu tetap bisa diisi:
           PRAMUKARYA hanya dinilai ATASAN, tetapi keempat selnya tampak menunggu diisi.
           Paket yang terikat di sana tidak akan pernah dipakai siapa pun — ia hanya membuat
           matriksnya tampak lengkap sambil menyembunyikan sel yang benar-benar kosong. */
        $menilai = DB::table('N_PA_Rater_Policy as p')
            ->join('N_PA_Rater_Policy_Detail as d', 'd.PA_Rater_Policy_Id', '=', 'p.Id_PA_Rater_Policy')
            ->where('p.Status_Policy', 'ACTIVE')
            ->where('p.Flag_Aktif', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->get(['p.ID_Level', 'd.PA_Rater_Category_Id'])
            ->groupBy('ID_Level')
            ->map(fn ($g) => $g->pluck('PA_Rater_Category_Id')->map(fn ($x) => (int) $x)->flip());

        PaLevelMasterService::sinkronHris();
        $level = DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->orderBy('ID_Level')
            ->get(['ID_Level as id_level', 'Nama_Level as nama_level'])
            ->filter(fn ($l) => ($wajib[$l->id_level] ?? collect())->isNotEmpty())
            ->map(function ($l) use ($kategori, $ikatan, $wajib, $kompetensiPerTipe, $menilai) {
                $milik = $ikatan[$l->id_level] ?? collect();
                $baku = $milik->first(fn ($b) => $b->PA_Rater_Category_Id === null);

                $dimensiWajib = ($wajib[$l->id_level] ?? collect());
                $penilaiLevel = $menilai[$l->id_level] ?? collect();
                $kompetensiWajib = $dimensiWajib
                    ->flatMap(fn ($d) => ($kompetensiPerTipe[$l->id_level.'|'.$d->tipe_id] ?? collect())->pluck('Kode_Competency'))
                    ->unique()
                    ->values()
                    ->all();

                $sel = [];

                foreach ($kategori as $k) {
                    $khusus = $milik->first(fn ($b) => (int) $b->PA_Rater_Category_Id === $k['id']);
                    $dipakai = $khusus ?? $baku;

                    $ikutMenilai = $penilaiLevel->has($k['id']);

                    $sel[$k['kode']] = $dipakai === null
                        ? ['terisi' => false, 'ikut_menilai' => $ikutMenilai]
                        : [
                            'terisi' => true,
                            'ikut_menilai' => $ikutMenilai,
                            'id' => (int) $dipakai->Id_PA_Question_Binding_Master,
                            'id_master_ujian' => $dipakai->Id_Master_Ujian,
                            'nama_ujian' => $dipakai->Nama_Ujian_Sumber,
                            'id_master_paket_ujian' => $dipakai->Id_Master_Paket_Ujian,
                            'nama_paket' => $dipakai->Nama_Paket_Ujian_Sumber,
                            'khusus' => $khusus !== null,
                            'jumlah_bagian' => (int) $dipakai->Jumlah_Bagian,
                            'jumlah_kompetensi' => (int) $dipakai->Jumlah_Kompetensi,

                            /* Status TERSIMPAN, bukan hasil pemeriksaan sekarang. Cap
                               waktunya ikut dikirim supaya layar bisa menuliskan "diperiksa
                               3 hari lalu" — tanpa itu, status lama terbaca seperti status
                               realtime, dan itu justru lebih menyesatkan daripada tidak
                               menampilkan apa-apa. */
                            'status_kesehatan' => $dipakai->Status_Kesehatan,
                            'masalah' => json_decode((string) $dipakai->Masalah_Json, true) ?: [],
                            'diperiksa_pada' => $dipakai->Diverifikasi_At,
                            'diverifikasi_pada' => $dipakai->Diverifikasi_At,
                            'sahih' => $dipakai->Status_Kesehatan === 'SEHAT',

                            /* Sel hasil salinan saat level PA dibuat (Master Level PA): sah dipakai,
                               tetapi mapping-nya milik level dasar dan belum ditinjau admin. */
                            'salinan_dari' => $this->salinanDari($dipakai->Catatan ?? null),
                        ];
                }

                return [
                    'id_level' => (int) $l->id_level,
                    'nama_level' => $l->nama_level,
                    'dimensi_wajib' => $dimensiWajib->map(fn ($d) => ['kode' => $d->kode, 'nama' => $d->nama])->values()->all(),
                    'jumlah_kompetensi_wajib' => count($kompetensiWajib),

                    /* Bentuk yang sama dengan matriks siklus, supaya pemilih paket bisa
                       dipakai kedua layar tanpa penyesuaian. Bentuk yang berbeda-beda untuk
                       data yang sama memaksa komponen bersama menebak, dan tebakan itu yang
                       kelak berbeda di satu layar saja. */
                    'kompetensi_wajib' => $dimensiWajib->map(fn ($d) => [
                        'dimensi_kode' => $d->kode,
                        'dimensi_nama' => $d->nama,
                        'kompetensi' => ($kompetensiPerTipe[$l->id_level.'|'.$d->tipe_id] ?? collect())
                            ->map(fn ($c) => ['kode' => $c->Kode_Competency])
                            ->values()
                            ->all(),
                    ])->values()->all(),
                    'sel' => $sel,
                    'bermasalah' => count(array_filter(
                        $sel,
                        fn ($s) => ($s['terisi'] ?? false) && ($s['sahih'] ?? true) === false,
                    )),

                    /* Hanya sel yang penilainya BENAR-BENAR menilai level ini dihitung
                       kosong. Menghitung sel yang memang tidak dinilai membuat angkanya
                       tidak pernah bisa mencapai nol, dan admin berhenti mempercayainya. */
                    'kosong' => count(array_filter(
                        $sel,
                        fn ($s) => ($s['ikut_menilai'] ?? false) && ! ($s['terisi'] ?? false),
                    )),

                    /* Sel yang TERISI padahal kategorinya tidak menilai level ini. Paketnya
                       tidak akan pernah dipakai; ia hanya membuat matriks tampak lengkap. */
                    'salah_tempat' => count(array_filter(
                        $sel,
                        fn ($s) => ($s['terisi'] ?? false) && ! ($s['ikut_menilai'] ?? false),
                    )),
                ];
            })
            ->values()
            ->all();

        return [
            'kategori' => $kategori,
            'level' => $level,

            'total_bermasalah' => array_sum(array_column($level, 'bermasalah')),
            'total_kosong' => array_sum(array_column($level, 'kosong')),
            'total_salah_tempat' => array_sum(array_column($level, 'salah_tempat')),
        ];
    }

    /**
     * Bahan yang dibutuhkan layar untuk MELABELI satu sel: bagian ujiannya, kompetensi
     * wajib levelnya, dan peringatan yang harus dilihat sebelum mengisi.
     *
     * Berdiri di layanan, bukan di kontroler, karena pemanggilnya DUA: halaman master dan
     * Langkah 2 penyiapan siklus. Disalin ke keduanya, ia akan berbeda di salah satunya
     * suatu hari — dan yang berbeda diam-diam adalah daftar kompetensi wajib, yaitu hal
     * yang menentukan pelabelan mana yang dianggap lengkap.
     *
     * @return array<string, mixed>
     */
    public function bahanPelabelan(string $idMasterUjian, int $levelId): array
    {
        $ujian = collect($this->katalog->daftarPaket()['paket'] ?? [])
            ->firstWhere('Id_Master_Ujian', $idMasterUjian);

        if ($ujian === null) {
            throw new RuntimeException("Ujian '{$idMasterUjian}' tidak ada di katalog HC Learn.");
        }

        $wajibId = $this->kompetensiWajibLevel($levelId);
        $bagian = $ujian['Bagian'] ?? [];

        /* Kekembaran dilaporkan ke LAYAR, bukan hanya ke gerbang simpan. Admin harus tahu
           sebelum melabeli, bukan sesudah mengisi dua puluh baris. */
        $kembar = PaBagianKatalog::kembar($bagian);

        return [
            'id_master_ujian' => $ujian['Id_Master_Ujian'],
            'nama_ujian' => $ujian['Nama_Ujian'],
            'nama_paket' => $ujian['Nama_Paket'] ?? null,
            'bagian' => $bagian,
            'kompetensi_wajib' => DB::table('N_PA_Competency as c')
                ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
                ->whereIn('c.Id_PA_Competency', $wajibId)
                ->orderBy('t.Kode_Type')
                ->orderBy('c.Kode_Competency')
                ->get([
                    'c.Id_PA_Competency as id',
                    'c.Kode_Competency as kode',
                    'c.Nama_Competency as nama',
                    't.Kode_Type as dimensi_kode',
                    't.Nama_Type as dimensi_nama',
                ]),
            'kembar' => $kembar,
            'jumlah_wajib' => count($wajibId),
            'sepadan' => $kembar === [] && count($bagian) === count($wajibId),
        ];
    }

    /**
     * Mengikat satu sel ke ujian HC Learn, beserta pemetaan bagian → kompetensi.
     *
     * SELURUHNYA dalam satu transaksi, dan divalidasi SEBELUM menulis apa pun. Header yang
     * tersimpan tanpa detail adalah keadaan paling menyesatkan di alur ini: matriks
     * melaporkan sel terisi, langkah penyiapan ditandai selesai, lalu penyalinannya ke
     * siklus menghasilkan paket kosong — dan tidak ada satu pun galat sepanjang jalan.
     *
     * @param  array<string, int>  $label  [ "{Id_Jenis_Indikator}|{Id_Jenis_Soal}" => PA_Competency_Id ]
     * @return array<string, mixed>
     */
    public function simpan(
        int $levelId,
        ?int $raterCategoryId,
        string $idMasterUjian,
        array $label,
        ?int $olehUserId = null,
    ): array {
        $ujian = collect($this->katalog->daftarPaket()['paket'] ?? [])
            ->firstWhere('Id_Master_Ujian', $idMasterUjian);

        if ($ujian === null) {
            throw new RuntimeException("Ujian '{$idMasterUjian}' tidak ada di katalog HC Learn.");
        }

        $bagian = $ujian['Bagian'] ?? [];

        if ($bagian === []) {
            throw new RuntimeException(
                "Ujian '{$idMasterUjian}' tidak memuat satu bagian pun yang bisa dilabeli. "
                .'Alat tes khusus seperti Kraeplin, PAPI, dan DISC memang tidak dipecah per '
                .'bagian, sehingga tidak bisa dipetakan ke kompetensi.'
            );
        }

        $this->pastikanKategoriMenilai($levelId, $raterCategoryId);

        $wajib = $this->kompetensiWajibLevel($levelId);
        $masalah = PaBijeksiLabel::periksa($bagian, $wajib, $label);

        if ($masalah !== []) {
            throw new RuntimeException(collect($masalah)->pluck('pesan')->implode(' '));
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();
        $petaBagian = collect($bagian)->keyBy(fn ($b) => PaBagianKatalog::kunci($b));

        $nilai = [
            'Id_Master_Ujian' => $idMasterUjian,
            'Nama_Ujian_Sumber' => $ujian['Nama_Ujian'] ?? null,
            'Id_Master_Paket_Ujian' => $ujian['Id_Master_Paket_Ujian'] ?? null,
            'Nama_Paket_Ujian_Sumber' => $ujian['Nama_Paket'] ?? null,
            'Jumlah_Kompetensi' => count($wajib),
            'Jumlah_Bagian' => count($bagian),
            'Sidik_Bagian' => PaBagianKatalog::sidik($bagian),
            'Status_Kesehatan' => 'SEHAT',
            'Masalah_Json' => null,
            'Diverifikasi_At' => $sekarang,
            'Diverifikasi_By' => $olehUserId,
        ];

        /* Menyimpan mapping = meninjaunya: penanda salinan level PA dilepas. Catatan lain dibiarkan. */
        $catatanLama = DB::table('N_PA_Question_Binding_Master')->where('ID_Level', $levelId)->where('Flag_Aktif', 'Y')
            ->where('Id_Master_Ujian', $idMasterUjian)
            ->when($raterCategoryId === null, fn ($q) => $q->whereNull('PA_Rater_Category_Id'), fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId))
            ->value('Catatan');
        if ($this->salinanDari($catatanLama) !== null) {
            $nilai['Catatan'] = null;
        }

        return DB::transaction(function () use (
            $levelId, $raterCategoryId, $nilai, $label, $petaBagian, $sekarang, $olehUserId
        ) {
            $ada = DB::table('N_PA_Question_Binding_Master')
                ->where('ID_Level', $levelId)
                ->where('Flag_Aktif', 'Y')
                ->when(
                    $raterCategoryId === null,
                    fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                    fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
                )
                ->value('Id_PA_Question_Binding_Master');

            if ($ada !== null) {
                /* Baris yang ada DIPERBARUI, bukan dinonaktifkan lalu disisipkan baru.
                   Pilihan ujian bukan riwayat keputusan berjenjang — ia satu setelan yang
                   berubah, dan menumpuk baris mati membuat "sel ini pernah pakai apa saja"
                   bercampur dengan "sel ini pakai apa sekarang". */
                DB::table('N_PA_Question_Binding_Master')
                    ->where('Id_PA_Question_Binding_Master', $ada)
                    ->update($nilai + ['Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);

                /* Detail lama DIHAPUS, bukan dinonaktifkan. Kedua indeks uniknya berlaku
                   pada seluruh baris tanpa memandang Flag_Aktif, jadi menyisakan baris mati
                   akan menolak pelabelan ulang yang sah. */
                DB::table('N_PA_Question_Binding_Master_Detail')
                    ->where('PA_Question_Binding_Master_Id', $ada)
                    ->delete();

                $id = (int) $ada;
                $baru = false;
            } else {
                DB::table('N_PA_Question_Binding_Master')->insert($nilai + [
                    'ID_Level' => $levelId,
                    'PA_Rater_Category_Id' => $raterCategoryId,
                    'Created_By' => $olehUserId,
                ]);

                $id = (int) DB::table('N_PA_Question_Binding_Master')
                    ->where('ID_Level', $levelId)
                    ->where('Flag_Aktif', 'Y')
                    ->when(
                        $raterCategoryId === null,
                        fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                        fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
                    )
                    ->value('Id_PA_Question_Binding_Master');

                $baru = true;
            }

            $baris = [];

            foreach ($label as $kunci => $kompetensiId) {
                $b = $petaBagian[(string) $kunci] ?? null;

                $baris[] = [
                    'PA_Question_Binding_Master_Id' => $id,
                    'Id_Jenis_Indikator' => (string) ($b['id_jenis_indikator'] ?? ''),
                    'Id_Jenis_Soal' => (string) ($b['id_jenis_soal'] ?? ''),
                    'Nama_Alat_Tes' => $b['nama_alat_tes'] ?? null,
                    'Nama_Bagian_Sumber' => $b['nama'] ?? null,
                    'Kode_Soal_Sumber' => $b['kode_soal'] ?? null,
                    'Urutan_Alat_Tes' => $b['urutan_alat_tes'] ?? null,
                    'Urutan_Bagian' => $b['urutan_bagian'] ?? null,
                    'PA_Competency_Id' => (int) $kompetensiId,
                    'Created_At' => $sekarang,
                    'Created_By' => $olehUserId,
                ];
            }

            /* 150, bukan 400. SQL Server menolak lebih dari 2.100 parameter per pernyataan,
               dan tiap baris di sini memakai 11 kolom. Batasnya soal PARAMETER, bukan baris
               — angka ini harus ditinjau setiap kali kolomnya bertambah. */
            foreach (array_chunk($baris, 150) as $potongan) {
                DB::table('N_PA_Question_Binding_Master_Detail')->insert($potongan);
            }

            return [
                'id' => $id,
                'baru' => $baru,
                'jumlah_bagian' => count($baris),
                'status_kesehatan' => 'SEHAT',
            ] + $nilai;
        });
    }

    /**
     * Kategori yang tidak menilai level ini DITOLAK.
     *
     * Ujian yang diikat di sel seperti itu tidak akan pernah dipakai siapa pun, dan
     * keberadaannya membuat matriks tampak lengkap sambil menyembunyikan sel yang
     * benar-benar kosong.
     */
    private function pastikanKategoriMenilai(int $levelId, ?int $raterCategoryId): void
    {
        if ($raterCategoryId === null) {
            return;
        }

        $ikutMenilai = DB::table('N_PA_Rater_Policy as p')
            ->join('N_PA_Rater_Policy_Detail as d', 'd.PA_Rater_Policy_Id', '=', 'p.Id_PA_Rater_Policy')
            ->where('p.ID_Level', $levelId)
            ->where('p.Status_Policy', 'ACTIVE')
            ->where('p.Flag_Aktif', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->where('d.PA_Rater_Category_Id', $raterCategoryId)
            ->exists();

        if ($ikutMenilai) {
            return;
        }

        $nama = DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', $raterCategoryId)
            ->value('Nama_Kategori') ?? $raterCategoryId;

        throw new RuntimeException(
            "Kategori {$nama} tidak menilai level ini menurut master aturan penilai. "
            .'Ujian yang diikat di sini tidak akan pernah dipakai. Ubah aturan penilainya '
            .'lebih dulu bila kategori ini seharusnya menilai.'
        );
    }

    /**
     * Memeriksa SATU sel terhadap katalog HC Learn dan MENYIMPAN hasilnya.
     *
     * Ini satu-satunya jalan di kelas ini yang menyentuh HC Learn. Pemanggilnya hanya tiga:
     * gerbang simpan, gerbang salin ke siklus, dan tombol "Periksa Sekarang". `matriks()`
     * TIDAK memanggilnya — ia membaca kolom yang ditulis di sini.
     *
     * @return array{status: string, masalah: list<array{jenis: string, pesan: string}>}
     */
    public function periksaSel(int $bindingId, ?int $olehUserId = null): array
    {
        $ikatan = DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $bindingId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if ($ikatan === null) {
            throw new RuntimeException("Ikatan {$bindingId} tidak ditemukan atau sudah dicabut.");
        }

        $ujian = collect($this->katalog->daftarPaket()['paket'] ?? [])
            ->firstWhere('Id_Master_Ujian', $ikatan->Id_Master_Ujian);

        $masalah = [];

        if ($ujian === null) {
            $masalah[] = [
                'jenis' => 'UJIAN_HILANG',
                'pesan' => sprintf(
                    'Ujian %s tidak ada lagi di katalog HC Learn. Sel ini tampak terisi tetapi '
                    .'tidak bisa disalin ke siklus mana pun.',
                    $ikatan->Id_Master_Ujian,
                ),
            ];
        } else {
            $bagian = $ujian['Bagian'] ?? [];

            $masalah = PaBijeksiLabel::periksa(
                $bagian,
                $this->kompetensiWajibLevel((int) $ikatan->ID_Level),
                $this->labelSel($bindingId),
            );

            /* Sidik hanya disegarkan bila selnya SAHIH. Menyegarkan sel bermasalah menghapus
               bukti bahwa ia bermasalah, dan pemeriksaan berikutnya akan melaporkannya
               bersih padahal tak ada yang diperbaiki. */
            if ($masalah === []) {
                DB::table('N_PA_Question_Binding_Master')
                    ->where('Id_PA_Question_Binding_Master', $bindingId)
                    ->update([
                        'Sidik_Bagian' => PaBagianKatalog::sidik($bagian),
                        'Jumlah_Bagian' => count($bagian),
                    ]);
            }
        }

        DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $bindingId)
            ->update([
                'Status_Kesehatan' => $masalah === [] ? 'SEHAT' : 'BERMASALAH',
                'Masalah_Json' => $masalah === [] ? null : json_encode($masalah, JSON_UNESCAPED_UNICODE),
                'Diverifikasi_At' => FormatTanggalHelper::getCurrentTime(),
                'Diverifikasi_By' => $olehUserId,
            ]);

        return ['status' => $masalah === [] ? 'SEHAT' : 'BERMASALAH', 'masalah' => $masalah];
    }

    /** Nama level asal bila catatannya penanda salinan Master Level PA; null bila bukan salinan. */
    private function salinanDari(?string $catatan): ?string
    {
        if ($catatan === null || ! str_starts_with($catatan, PaLevelMasterService::TANDA_SALINAN_IKATAN)) {
            return null;
        }
        $id = (int) substr($catatan, strlen(PaLevelMasterService::TANDA_SALINAN_IKATAN));

        return (string) (DB::table('N_PA_Level')->where('ID_Level', $id)->value('Nama_Level') ?? "Level {$id}");
    }

    /** Mencabut pilihan baku satu sel. Dinonaktifkan, bukan dihapus. */
    public function cabut(int $id, ?int $olehUserId = null): void
    {
        $terpengaruh = DB::table('N_PA_Question_Binding_Master')
            ->where('Id_PA_Question_Binding_Master', $id)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Flag_Aktif' => 'T',
                'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                'Updated_By' => $olehUserId,
            ]);

        if ($terpengaruh === 0) {
            throw new RuntimeException("Pilihan baku {$id} tidak ditemukan atau sudah dicabut.");
        }
    }

    /**
     * Memeriksa ulang SELURUH sel dan menyimpan hasilnya masing-masing.
     *
     * Dipakai tombol "Periksa Semua". Sengaja tidak dijalankan otomatis: pemeriksaan
     * menyentuh HC Learn, dan menjalankannya tanpa diminta mengembalikan ketergantungan
     * yang justru baru dilepas dari `matriks()`.
     *
     * Katalognya di-memo per permintaan, jadi perulangan ini memanggil HC Learn SEKALI untuk
     * seluruh sel — bukan sekali per sel.
     *
     * @return array<string, mixed>
     */
    public function verifikasiUlang(?int $olehUserId = null): array
    {
        $id = DB::table('N_PA_Question_Binding_Master')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Question_Binding_Master');

        $sahih = 0;
        $bermasalah = [];

        foreach ($id as $satu) {
            $hasil = $this->periksaSel((int) $satu, $olehUserId);

            if ($hasil['status'] === 'SEHAT') {
                $sahih++;

                continue;
            }

            $bermasalah[] = ['id' => (int) $satu, 'masalah' => $hasil['masalah']];
        }

        return [
            'diperiksa' => count($id),
            'sahih' => $sahih,
            'bermasalah' => $bermasalah,
        ];
    }

    /**
     * Menyalin seluruh pilihan baku ke satu siklus — BERBASIS HIMPUNAN.
     *
     * KENAPA DITULIS ULANG
     *
     * Versi pertama memanggil `impor()` sekali per sel. Pada 45 sel itu menjadi ~230 kueri
     * dan 48 detik pada DB dev — sebagian besar hanya pemeriksaan "sel ini sudah terisi?"
     * yang dijalankan satu per satu. N+1 yang saya buat sendiri.
     *
     * Lebih buruk daripada lambatnya: versi itu MENANGKAP pengecualian dari `impor()` lalu
     * melanjutkan, dan setiap `impor()` membuka transaksinya sendiri. Kegagalan satu sel
     * meninggalkan transaksi luar dalam keadaan yang tidak bisa dipercaya — sisa
     * penyalinannya bisa ter-commit walau pemanggilnya menggulung balik. Data uji yang
     * seharusnya hilang justru tertinggal 45 baris, dan itu petunjuknya.
     *
     * Versi ini: baca sekali, VALIDASI SELURUHNYA DI MEMORI, lalu tulis dalam SATU transaksi
     * dengan sisipan borongan. Tidak ada pengecualian yang dipakai sebagai alur.
     *
     * @return array<string, mixed>
     */
    public function salinKeSiklus(int $cycleId, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::PILIH_PAKET_SOAL, $cycleId);

        /* Diperiksa SEGAR, bukan dari Status_Kesehatan yang tersimpan.
           |
           | Status tersimpan ditulis saat sel terakhir disimpan atau diperiksa manual, dan
           | bisa berumur berminggu-minggu. HC Learn boleh berubah kapan saja di antaranya;
           | memercayai status lama di gerbang ini berarti meloloskan ujian rusak ke siklus
           | baru — kerusakan yang justru mau dicegah pemeriksaan ini.
           |
           | Dijalankan SEBELUM matriks() supaya matriks membaca status yang baru saja
           | disegarkan, bukan yang sedang diperbaiki. */
        foreach (DB::table('N_PA_Question_Binding_Master')->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Question_Binding_Master') as $bindingId) {
            $this->periksaSel((int) $bindingId, $olehUserId);
        }

        $m = $this->matriks();
        $kategoriId = collect($m['kategori'])->pluck('id', 'kode')->all();

        /* Sel yang SUDAH terisi di siklus, dibaca SEKALI. Versi lama menanyakannya per sel —
           45 perjalanan ke database untuk jawaban yang muat dalam satu kueri. */
        $sudahTerisi = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['ID_Level', 'PA_Rater_Category_Id'])
            ->mapWithKeys(fn ($r) => [
                $r->ID_Level.'|'.($r->PA_Rater_Category_Id ?? 'baku') => true,
            ])
            ->all();

        /* Level yang BENAR-BENAR dinilai siklus ini — sumber yang sama dengan yang dipakai
           matriks siklus.

           Tanpa saringan ini, penyalinan menulis paket untuk SELURUH level master: pada data
           sekarang 45 paket untuk 13 level, padahal siklus 2585 hanya menilai 6. Duapuluh
           lima di antaranya tidak akan pernah dipakai, tidak terlihat di matriks siklus
           (matriks hanya menampilkan level yang dinilai), dan karena itu tidak bisa dilihat
           maupun dicabut admin dari layar mana pun. Angka "disalin" pun membesar tanpa arti.

           Gejalanya sempat menyesatkan saya: satu paket dicabut lewat jalur produk, tetapi
           `lubang` tidak naik — karena yang tercabut ada di level yang matriksnya tidak
           pernah menampilkan. */
        $levelSiklus = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->distinct()
            ->pluck('ID_Level')
            ->map(fn ($x) => (int) $x)
            ->flip();

        $katalog = collect($this->katalog->daftarPaket()['paket'] ?? [])->keyBy('Id_Master_Ujian');

        /* Pemetaan bagian→kompetensi seluruh sel, dibaca SEKALI dan dikelompokkan menurut
           ikatannya. Membacanya per sel mengembalikan N+1 yang sudah pernah dibuang di
           metode ini. */
        $detailMaster = DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('Flag_Aktif', 'Y')
            ->get()
            ->groupBy('PA_Question_Binding_Master_Id');

        $rencana = [];
        $dilewati = [];
        $penanda = [];

        foreach ($m['level'] as $l) {
            if (! $levelSiklus->has($l['id_level'])) {
                // Level ini tidak dinilai siklus tsb. Bukan kegagalan — tidak dilaporkan.
                continue;
            }

            foreach ($l['sel'] as $kodeKategori => $sel) {
                if (! ($sel['terisi'] ?? false)) {
                    continue;
                }

                if (! ($sel['ikut_menilai'] ?? true)) {
                    // Bukan kegagalan: kategorinya memang tidak menilai level ini.
                    continue;
                }

                if (($sel['sahih'] ?? true) === false) {
                    $dilewati[] = [
                        'level' => $l['nama_level'],
                        'kategori' => $kodeKategori,
                        'id_master_ujian' => $sel['id_master_ujian'],
                        'alasan' => $sel['masalah'][0]['pesan'] ?? 'Pilihan baku bermasalah.',
                    ];

                    continue;
                }

                $raterId = ($sel['khusus'] ?? false) ? ($kategoriId[$kodeKategori] ?? null) : null;
                $kunci = $l['id_level'].'|'.($raterId ?? 'baku');

                // Sel baku muncul di keempat kategorinya; tanpa penanda ini ia direncanakan
                // empat kali dan tiga di antaranya menabrak indeks unik.
                if (isset($penanda[$kunci])) {
                    continue;
                }

                $penanda[$kunci] = true;

                if (isset($sudahTerisi[$kunci])) {
                    /* Sudah terisi di siklus. TIDAK ditimpa dan TIDAK dilaporkan sebagai
                       kegagalan: penyalinan master melengkapi yang kosong, bukan membuang
                       suntingan admin. */
                    continue;
                }

                $paket = $katalog[$sel['id_master_ujian']] ?? null;
                $label = $detailMaster[$sel['id']] ?? collect();

                if ($paket === null || ($paket['Bagian'] ?? []) === [] || $label->isEmpty()) {
                    $dilewati[] = [
                        'level' => $l['nama_level'],
                        'kategori' => $kodeKategori,
                        'id_master_ujian' => $sel['id_master_ujian'],
                        'alasan' => 'Ujian tidak ada di katalog, tidak memuat bagian, atau belum dipetakan ke kompetensi.',
                    ];

                    continue;
                }

                $rencana[] = [
                    'level' => $l['id_level'],
                    'rater' => $raterId,
                    'paket' => $paket,
                    'label' => $label,
                ];
            }
        }

        if ($rencana === []) {
            $this->katalog->tandaiLangkah($cycleId, $olehUserId);

            return ['disalin' => 0, 'dilewati' => $dilewati];
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();

        /* SATU transaksi untuk seluruh penyalinan. Sisipan borongan, bukan per sel.
           Kalau ada yang gagal, tidak ada yang tersimpan — dan itu benar: siklus yang
           setengah tersalin lebih menyesatkan daripada yang belum tersalin sama sekali,
           karena angka lubangnya turun tanpa selnya benar-benar siap. */
        $disalin = DB::transaction(function () use ($cycleId, $rencana, $olehUserId) {
            $barisPaket = [];

            foreach ($rencana as $r) {
                $barisPaket[] = [
                    'PA_Cycle_Id' => $cycleId,
                    'ID_Level' => $r['level'],
                    'PA_Rater_Category_Id' => $r['rater'],
                    'Id_Master_Ujian' => $r['paket']['Id_Master_Ujian'],
                    'Nama_Ujian_Sumber' => $r['paket']['Nama_Ujian'] ?? null,
                    'Id_Master_Paket_Ujian' => $r['paket']['Id_Master_Paket_Ujian'] ?? null,
                    'Nama_Paket_Ujian_Sumber' => $r['paket']['Nama_Paket'] ?? null,
                    'Sumber_Kontrak' => 'HCLEARN',
                    'Catatan' => 'Disalin dari pilihan baku Master Data.',
                    'Snapshot_By' => $olehUserId,
                ];
            }

            foreach (array_chunk($barisPaket, 100) as $potongan) {
                DB::table('N_PA_Cycle_Question_Package')->insert($potongan);
            }

            /* Id-nya dibaca kembali SEKALI untuk seluruh siklus, lalu dicocokkan lewat
               pasangan (level, kategori). Membacanya per baris mengembalikan N+1 yang baru
               saja dibuang. */
            $id = DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Flag_Aktif', 'Y')
                ->get(['Id_PA_Cycle_Question_Package', 'ID_Level', 'PA_Rater_Category_Id'])
                ->mapWithKeys(fn ($r) => [
                    $r->ID_Level.'|'.($r->PA_Rater_Category_Id ?? 'baku') => (int) $r->Id_PA_Cycle_Question_Package,
                ])
                ->all();

            $barisButir = [];

            foreach ($rencana as $r) {
                $paketId = $id[$r['level'].'|'.($r['rater'] ?? 'baku')] ?? null;

                if ($paketId === null) {
                    continue;
                }

                /* Labelnya diambil dari pemetaan MASTER, bukan disimpulkan dari katalog.
                   Sejak kontrak baru HC Learn, bagian dilempar tanpa kompetensi — satu-satunya
                   sumber yang sah adalah pemetaan yang dibuat admin. */
                $label = $r['label']->mapWithKeys(fn ($d) => [
                    $d->Id_Jenis_Indikator.'|'.$d->Id_Jenis_Soal => $d,
                ]);

                foreach ($r['paket']['Bagian'] as $b) {
                    $d = $label[PaBagianKatalog::kunci($b)] ?? null;

                    if ($d === null) {
                        /* Bagian yang tidak berlabel TIDAK disisipkan. Seharusnya tak pernah
                           terjadi — gerbang segar di awal metode ini menolak sel yang
                           pemetaannya tak lengkap — tetapi menyisipkannya tanpa kompetensi
                           akan membuat skornya hilang tanpa satu pun galat. */
                        continue;
                    }

                    $barisButir[] = [
                        'PA_Cycle_Question_Package_Id' => $paketId,
                        'Id_Jenis_Indikator' => $b['id_jenis_indikator'],
                        'Id_Jenis_Soal' => $b['id_jenis_soal'],
                        'Nama_Alat_Tes' => $b['nama_alat_tes'] ?? null,
                        'Nama_Bagian_Sumber' => $b['nama'] ?? null,
                        'PA_Competency_Id' => (int) $d->PA_Competency_Id,

                        /* Kunci yang dipakai HC Learn saat mengembalikan nilai — lihat
                           catatan yang sama di `PaHcLearnCatalogService`. Diambil dari
                           katalog, bukan dari detail master, supaya sumbernya satu. */
                        'Kode_Soal_Sumber' => $b['kode_soal'] ?? null,

                        /* Rumusnya di PaBagianKatalog, satu tempat untuk kedua penyisip.

                           Bobot_Penilaian, Skor_Maksimal, dan Jenis_Soal SENGAJA dibiarkan
                           kosong — katalog HC Learn tidak membawanya di tingkat bagian, dan
                           menebaknya berarti menyimpan angka yang tampak resmi padahal tak
                           pernah datang dari mana pun. */
                        'Urutan_Tampil' => PaBagianKatalog::urutanTampil($b),
                        'Snapshot_By' => $olehUserId,
                    ];
                }
            }

            /* 250, bukan 400. SQL Server menolak lebih dari 2.100 parameter per pernyataan,
               dan tiap butir kini memakai 7 kolom. Batasnya soal PARAMETER, bukan baris —
               menambah kolom kelak menurunkan jumlah baris yang aman, jadi angka ini harus
               ditinjau setiap kali kolomnya bertambah. */
            foreach (array_chunk($barisButir, 250) as $potongan) {
                DB::table('N_PA_Cycle_Question_Package_Item')->insert($potongan);
            }

            return count($rencana);
        });

        $this->katalog->tandaiLangkah($cycleId, $olehUserId);

        return ['disalin' => $disalin, 'dilewati' => $dilewati];
    }

    /**
     * Menerima usulan master untuk SATU sel siklus.
     *
     * Bedanya dengan `salinKeSiklus()`: yang itu mengisi seluruh sel kosong sekaligus, yang
     * ini satu sel yang ditunjuk admin. Keduanya perlu — mengisi 45 sel sekaligus terlalu
     * kasar ketika yang ingin diubah hanya satu, dan mengisi satu-satu terlalu lambat
     * ketika siklusnya baru.
     *
     * @return array<string, mixed>
     */
    public function terimaUsulan(
        int $cycleId,
        int $levelId,
        ?int $raterCategoryId,
        ?int $olehUserId = null,
    ): array {
        $usulan = DB::table('N_PA_Question_Binding_Master')
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->when(
                $raterCategoryId === null,
                fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
            )
            ->value('Id_Master_Ujian');

        /* Jatuh ke usulan BAKU level itu bila kategorinya tidak punya sendiri — urutan yang
           sama dengan pencarian di matriks, supaya yang diterima sama dengan yang
           ditampilkan. Urutan yang berbeda membuat admin menerima sesuatu yang lain dari
           yang ia lihat. */
        $usulan ??= DB::table('N_PA_Question_Binding_Master')
            ->where('ID_Level', $levelId)
            ->whereNull('PA_Rater_Category_Id')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Master_Ujian');

        if ($usulan === null) {
            throw new RuntimeException(
                'Master belum punya pilihan baku untuk sel ini. '
                .'Tetapkan dulu di Master Data > Ikatan Paket Soal, atau pilih ujiannya langsung di sini.'
            );
        }

        $hasil = $this->katalog->impor($cycleId, $levelId, $usulan, $raterCategoryId, $olehUserId);

        return $hasil + ['dari_master' => true];
    }
}
