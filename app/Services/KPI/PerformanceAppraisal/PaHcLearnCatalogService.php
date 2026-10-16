<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\SignatureHelper;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaBagianKatalog;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaBijeksiLabel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Katalog paket soal HC Learn — satu pintu, dua sumber.
 *
 * BENTUK YANG DILAYANI
 *
 * HC Learn menyerahkan kontrak katalog PA-nya pada 9 September 2026. Satu panggilan ke
 * `/api/v1/pa/nama-tes` membawa ujian beserta paket, alat tes, dan bagian-bagiannya
 * sekaligus; tidak ada panggilan detail terpisah.
 *
 * Yang penting: bagian-bagian itu TIDAK berlabel kompetensi. Kompetensi bukan lagi milik
 * HC Learn — ia dipetakan admin HCIS di halaman Ikatan Paket Soal, satu bagian ke tepat satu
 * kompetensi. Layanan ini karena itu hanya meratakan dan menyeragamkan bentuknya; ia tidak
 * pernah menyimpulkan kompetensi apa pun sendiri.
 *
 * Rujukan: docs/superpowers/specs/2026-09-09-pa-ikatan-paket-soal-section-kompetensi-design.md
 *
 * TIDAK ADA LAGI JALUR PALSU. Simulasi katalog dibuang pada 9 September 2026, ketika
 * endpointnya terbukti hidup: satu-satunya sumber daftar paket adalah HC Learn, dan ketika
 * ia tidak bisa dihubungi layanan ini MELEMPAR, bukan memulangkan daftar karangan. Layar
 * yang kosong terlihat dan segera dilaporkan; daftar palsu yang tidak dikatakan palsu
 * menghasilkan siklus penilaian berisi soal yang tidak pernah ada di HC Learn, dan baru
 * ketahuan ketika penilai membuka formulir dan menemukannya kosong.
 */
class PaHcLearnCatalogService
{
    /**
     * Endpoint katalog PA milik HC Learn.
     *
     * Satu panggilan membawa ujian beserta paket, alat tes, dan bagian-bagiannya sekaligus —
     * tidak ada panggilan detail terpisah.
     *
     * PARAMETERNYA WAJIB TERURUT ABJAD (`limit` sebelum `page`). Server HC Learn mengurutkan
     * query sebelum memverifikasi tanda tangan, sehingga `?page=1&limit=100` ditolak dengan
     * 401 "Invalid Signature" — galat yang menuduh kunci, bukan urutan parameter. Contoh di
     * dokumentasi HC Learn sendiri justru menulis urutan yang salah itu.
     */
    private const ENDPOINT_KATALOG = 'api/v1/pa/nama-tes?limit=100&page=1';

    /**
     * Katalog yang sudah terbentuk pada permintaan ini.
     *
     * Membentuknya bukan pekerjaan ringan: ia satu panggilan jaringan ke HC Learn, lalu
     * perataan empat tingkat. Tanpa memo, `impor()` membangunnya ulang SETIAP kali
     * dipanggil — dan
     * penyalinan master mengimpor 51 sel, jadi 51 kali pembangunan penuh untuk katalog yang
     * tidak berubah sedetik pun di antaranya.
     *
     * Memonya PER PERMINTAAN, bukan cache lintas permintaan: katalog HC Learn sungguhan bisa
     * berubah kapan saja, dan menyimpannya lebih lama membuat verifikasi memeriksa keadaan
     * yang sudah lewat.
     *
     * @var array<string, mixed>|null
     */
    private ?array $memoKatalog = null;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaCycleSetupStepService $langkah,
    ) {}

    /**
     * Pengikatan diselesaikan SAAT DIPAKAI, bukan saat dibangun.
     *
     * `PaQuestionBindingService` kini membutuhkan katalog ini untuk memvalidasi usulan
     * master, sementara katalog membutuhkannya untuk menghitung lubang saat menandai
     * langkah. Menyuntikkan keduanya lewat konstruktor menutup lingkarannya dan container
     * gagal membangun keduanya sama sekali.
     *
     * Yang dilonggarkan sisi ini karena ia hanya memakainya di satu metode, dan hanya
     * sesudah penulisan selesai.
     */
    private function pengikatan(): PaQuestionBindingService
    {
        return app(PaQuestionBindingService::class);
    }

    /** Melupakan memo katalog. Dipakai uji yang mengubah balasan HC Learn di tengah jalan. */
    public function lupakanMemo(): void
    {
        $this->memoKatalog = null;
    }

    /**
     * Daftar paket soal yang tersedia, beserta butirnya.
     *
     * @return array<string, mixed>
     */
    public function daftarPaket(): array
    {
        if ($this->memoKatalog !== null) {
            return $this->memoKatalog;
        }

        return $this->memoKatalog = $this->bentukDaftarPaket();
    }

    /**
     * @return array<string, mixed>
     */
    private function bentukDaftarPaket(): array
    {
        return [
            'sumber' => 'HC Learn',
            'paket' => $this->paketDariHcLearn(),
        ];
    }

    /**
     * Mengimpor satu paket beserta butirnya ke dalam siklus.
     *
     * Seluruhnya dalam SATU transaksi. Paket tanpa butir adalah keadaan yang paling
     * menyesatkan di modul ini: layar melaporkan "level ini sudah bersoal", langkahnya
     * ditandai selesai, lalu penilai membuka formulir kosong.
     *
     * @return array<string, mixed>
     */
    public function impor(
        int $cycleId,
        int $levelId,
        string $idMasterUjian,
        ?int $raterCategoryId = null,
        ?int $olehUserId = null,
        bool $tandaiLangkah = true,
        bool $ganti = false,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::PILIH_PAKET_SOAL, $cycleId);

        $katalog = $this->daftarPaket();
        $paket = collect($katalog['paket'])->firstWhere('Id_Master_Ujian', $idMasterUjian);

        if ($paket === null) {
            throw new RuntimeException(
                "Paket '{$idMasterUjian}' tidak ada di katalog HC Learn."
            );
        }

        if (($paket['Bagian'] ?? []) === []) {
            throw new RuntimeException(
                "Ujian '{$idMasterUjian}' tidak memuat satu bagian pun yang bisa dipetakan. "
                .'Alat tes khusus seperti Kraeplin, PAPI, dan DISC memang tidak dipecah per '
                .'bagian, sehingga tidak bisa diikat ke kompetensi.'
            );
        }

        /* Diperiksa per SEL (level x kategori), bukan per level. Sejak DDL-2026-086 satu
           level boleh punya beberapa paket -- satu per kategori penilai -- dan pemeriksaan
           per level akan menolak paket kedua yang justru diminta HC. */
        $sudah = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->when(
                $raterCategoryId === null,
                fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
            )
            ->first(['Id_PA_Cycle_Question_Package', 'Id_Master_Ujian']);

        if ($sudah !== null && ! $ganti) {
            throw new RuntimeException(sprintf(
                'Sel ini sudah ditempati ujian %s. Pilih sel itu di matriks lalu setujui '
                .'penggantiannya bila memang ingin menukar paketnya.',
                $sudah->Id_Master_Ujian,
            ));
        }

        if ($sudah !== null && (string) $sudah->Id_Master_Ujian === $idMasterUjian) {
            throw new RuntimeException(sprintf(
                'Sel ini sudah memakai ujian %s. Tidak ada yang perlu diganti.',
                $idMasterUjian,
            ));
        }

        /* PINTU YANG SUDAH TERBIT MENGUNCI PAKETNYA.
           |
           | Penugasan yang sudah dijadwalkan menyimpan paket soalnya di
           | `N_PA_Assignment_Integration`, dan tautan HC Learn yang sudah dibagikan
           | menunjuk soal yang lama. Menukar paketnya di sini membuat penilai membuka soal
           | yang berbeda dari yang tercatat pada penugasannya - dan kompetensi hasilnya
           | dipetakan lewat butir paket, sehingga selisihnya baru terlihat saat nilainya
           | tidak masuk hitungan mana pun.
           |
           | Diperiksa walau gerbang siklus hanya mengizinkan DRAFT/PREPARED: penugasan dan
           | pintunya dibentuk pada status yang sama. */
        if ($sudah !== null) {
            $berpintu = DB::table('N_PA_Assignment_Integration')
                ->where('PA_Cycle_Question_Package_Id', (int) $sudah->Id_PA_Cycle_Question_Package)
                ->where('Flag_Aktif', 'Y')
                ->count();

            if ($berpintu > 0) {
                throw new RuntimeException(sprintf(
                    'Paket sel ini sudah dipakai %d pintu penilaian yang terbit. Batalkan '
                    .'penjadwalan penilainya lebih dulu; menukar paketnya sekarang membuat '
                    .'tautan yang sudah dibagikan menunjuk soal yang berbeda.',
                    $berpintu,
                ));
            }
        }

        /* Kompetensi TIDAK LAGI datang dari HC Learn.
           |
           | Sejak kontrak barunya, bagian soal dilempar tanpa label kompetensi, dan
           | pemetaannya dibuat admin di halaman Ikatan Paket Soal. Impor per-sel ini karena
           | itu tidak boleh menebak sendiri: ia MEMBACA pemetaan yang sudah dibuat di sana.
           |
           | Kalau admin memilih ujian yang belum pernah dilabeli untuk level ini, impornya
           | ditolak dan ia diarahkan ke halaman masternya. Alternatifnya — mengimpor tanpa
           | label — menghasilkan butir siklus tanpa kompetensi, dan skornya tidak akan
           | pernah masuk hitungan mana pun tanpa meninggalkan satu pun galat. */
        $label = $this->labelDariMaster($levelId, $raterCategoryId, $idMasterUjian);

        if ($label === []) {
            throw new RuntimeException(sprintf(
                'Ujian %s belum dipetakan ke kompetensi untuk SEL INI (level x kategori '
                .'penilai). Buka Master Data > Ikatan Paket Soal, labeli tiap bagiannya '
                .'pada sel yang sama lebih dulu, lalu impor kembali. '
                .'Tanpa pemetaan itu, butirnya tersimpan tanpa kompetensi dan skornya tidak '
                .'akan pernah masuk hitungan.',
                $idMasterUjian,
            ));
        }

        $masalah = PaBijeksiLabel::periksa(
            $paket['Bagian'],
            $this->kompetensiWajibLevel($levelId),
            collect($label)->map(fn ($d) => (int) $d->PA_Competency_Id)->all(),
        );

        if ($masalah !== []) {
            throw new RuntimeException(collect($masalah)->pluck('pesan')->implode(' '));
        }

        $paketId = DB::transaction(function () use ($cycleId, $levelId, $raterCategoryId, $paket, $label, $olehUserId, $sudah) {
            /* Yang lama DINONAKTIFKAN, bukan dihapus - dan di dalam transaksi yang sama
               dengan penyisipan penggantinya, supaya sel tidak pernah kosong di tengah
               jalan. `UX_PA_CycleQPkg_Cycle_Level_Rater` tersaring pada Flag_Aktif='Y',
               jadi baris lamanya boleh tinggal dan riwayat keduanya tetap terbaca. */
            if ($sudah !== null) {
                $lamaId = (int) $sudah->Id_PA_Cycle_Question_Package;

                DB::table('N_PA_Cycle_Question_Package_Item')
                    ->where('PA_Cycle_Question_Package_Id', $lamaId)
                    ->update(['Flag_Aktif' => 'T']);

                DB::table('N_PA_Cycle_Question_Package')
                    ->where('Id_PA_Cycle_Question_Package', $lamaId)
                    ->update(['Flag_Aktif' => 'T']);
            }

            DB::table('N_PA_Cycle_Question_Package')->insert([
                'PA_Cycle_Id' => $cycleId,
                'ID_Level' => $levelId,
                'PA_Rater_Category_Id' => $raterCategoryId,
                'Id_Master_Ujian' => $paket['Id_Master_Ujian'],
                'Nama_Ujian_Sumber' => $paket['Nama_Ujian'] ?? null,
                'Id_Master_Paket_Ujian' => $paket['Id_Master_Paket_Ujian'] ?? null,
                'Nama_Paket_Ujian_Sumber' => $paket['Nama_Paket'] ?? null,
                'Sumber_Kontrak' => 'HCLEARN',
                'Catatan' => null,
                'Snapshot_By' => $olehUserId,
            ]);

            $id = (int) DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('ID_Level', $levelId)
                ->where('Flag_Aktif', 'Y')
                ->when(
                    $raterCategoryId === null,
                    fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                    fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
                )
                ->value('Id_PA_Cycle_Question_Package');

            $baris = [];

            foreach ($paket['Bagian'] as $b) {
                $kunci = PaBagianKatalog::kunci($b);

                $baris[] = [
                    'PA_Cycle_Question_Package_Id' => $id,
                    'Id_Jenis_Indikator' => $b['id_jenis_indikator'],
                    'Id_Jenis_Soal' => $b['id_jenis_soal'],
                    'Nama_Alat_Tes' => $b['nama_alat_tes'] ?? null,
                    'Nama_Bagian_Sumber' => $b['nama'] ?? null,
                    'PA_Competency_Id' => (int) $label[$kunci]->PA_Competency_Id,

                    /* KUNCI YANG DIPAKAI HC LEARN saat mengembalikan nilai, dibekukan
                       bersama paketnya. Tanpa ini `Detail[].Kode_Soal` pada callback tidak
                       punya pasangan di sisi kita, dan seluruh butir mendarat tak
                       terpetakan — penilai sudah mengisi, layar tetap "belum mulai". */
                    'Kode_Soal_Sumber' => $b['kode_soal'] ?? null,

                    /* Rumusnya di PaBagianKatalog, bukan di sini: penyalinan massal dari
                       master menyisipkan ke tabel yang sama, dan dua rumus kembar adalah
                       dua rumus yang bisa berbeda. */
                    'Urutan_Tampil' => PaBagianKatalog::urutanTampil($b),

                    /* Bobot_Penilaian, Skor_Maksimal, dan Jenis_Soal SENGAJA dibiarkan
                       kosong. Katalog HC Learn tidak membawanya di tingkat bagian — bobot
                       dan skor maksimal baru muncul di `tabs[].domain[]` pada endpoint
                       hasil, yaitu setelah peserta mengerjakan. Menebaknya di sini berarti
                       menyimpan angka yang tampak resmi padahal tak pernah datang dari
                       mana pun. */
                    'Snapshot_By' => $olehUserId,
                ];
            }

            foreach (array_chunk($baris, 100) as $potongan) {
                DB::table('N_PA_Cycle_Question_Package_Item')->insert($potongan);
            }

            return $id;
        });

        /* Penandaan langkah membangun ULANG seluruh matriks — puluhan kueri. Dipanggil di
           dalam perulangan impor (penyalinan master mengimpor 51 sel), biayanya menjadi 51
           kali pembangunan matriks untuk satu jawaban yang hanya dibaca di akhir. Pemanggil
           yang mengimpor beruntun mematikannya, lalu menandai sekali sendiri. */
        if ($tandaiLangkah) {
            $this->tandaiLangkah($cycleId, $olehUserId);
        }

        return [
            'paket_id' => $paketId,
            'id_level' => $levelId,
            'rater_category_id' => $raterCategoryId,
            'id_master_ujian' => $paket['Id_Master_Ujian'],
            'bagian' => count($paket['Bagian']),
        ];
    }

    /**
     * Pemetaan bagian → kompetensi yang sudah dibuat admin di halaman Ikatan Paket Soal.
     *
     * Dicari lewat (level, ujian) dan SENGAJA mengabaikan kategori penilai: kompetensi wajib
     * ditentukan per level saja, sehingga ujian yang sama di level yang sama selalu punya
     * pemetaan yang sama di sel mana pun. Menuntut sel yang persis sama membuat impor
     * per-kategori gagal hanya karena pelabelannya kebetulan dibuat di sel baku.
     *
     * @return array<string, object> berkunci "{Id_Jenis_Indikator}|{Id_Jenis_Soal}"
     */
    /**
     * Pemetaan bagian→kompetensi milik SATU SEL, bukan milik levelnya.
     *
     * Ikatan master berkunci (ID_Level, PA_Rater_Category_Id) — itu isi
     * `UX_PA_QBindMaster_Level_Rater` — sehingga tiap sel matriks punya pelabelannya
     * sendiri. Versi sebelumnya menyaring level dan ujian saja, dan akibatnya dua:
     *
     *   - sel yang BELUM pernah dilabeli tetap bisa diimpor, karena ia diam-diam meminjam
     *     label milik kategori penilai lain. Gerbang "belum dipetakan" di pemanggil jadi
     *     tidak pernah menyala, dan admin melihat impor yang berhasil untuk sel yang tak
     *     pernah ia isi;
     *   - ketika dua kategori mengikat ujian yang SAMA, barisnya bertabrakan di `keyBy()`
     *     dan yang menang ditentukan urutan pengembalian basis data. Label kompetensi
     *     sebuah sel jadi bergantung pada hal yang tidak dijanjikan siapa pun.
     *
     * Cadangan ke baris ber-rater NULL DISENGAJA dan bukan kelonggaran: baris itu memang
     * paket SELEVEL dalam rancangannya, dan `matriks()` memakai aturan yang sama persis
     * (`$khusus ?? $baku`). Yang tidak boleh, dan kini tidak bisa lagi, adalah meminjam
     * dari kategori penilai LAIN.
     *
     * @return array<string, object>
     */
    private function labelDariMaster(int $levelId, ?int $raterCategoryId, string $idMasterUjian): array
    {
        $header = function (bool $baku) use ($levelId, $raterCategoryId, $idMasterUjian) {
            return DB::table('N_PA_Question_Binding_Master')
                ->where('ID_Level', $levelId)
                ->where('Id_Master_Ujian', $idMasterUjian)
                ->where('Flag_Aktif', 'Y')
                ->when(
                    $baku || $raterCategoryId === null,
                    fn ($q) => $q->whereNull('PA_Rater_Category_Id'),
                    fn ($q) => $q->where('PA_Rater_Category_Id', $raterCategoryId),
                )
                ->value('Id_PA_Question_Binding_Master');
        };

        $bindingId = $header(false) ?? ($raterCategoryId === null ? null : $header(true));

        if ($bindingId === null) {
            return [];
        }

        return DB::table('N_PA_Question_Binding_Master_Detail')
            ->where('PA_Question_Binding_Master_Id', (int) $bindingId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_Jenis_Indikator', 'Id_Jenis_Soal', 'PA_Competency_Id'])
            ->keyBy(fn ($d) => $d->Id_Jenis_Indikator.'|'.$d->Id_Jenis_Soal)
            ->all();
    }

    /**
     * Kompetensi wajib satu level, sebagai daftar Id_PA_Competency.
     *
     * Sengaja meminjam dari `PaQuestionBindingMasterService`, bukan menghitung sendiri.
     * Dua perhitungan terpisah untuk daftar yang sama adalah cara keduanya kelak berselisih
     * — dan selisihnya akan muncul sebagai impor yang ditolak tanpa sebab yang jelas.
     *
     * @return list<int>
     */
    private function kompetensiWajibLevel(int $levelId): array
    {
        return app(PaQuestionBindingMasterService::class)->kompetensiWajibLevel($levelId);
    }

    /* ------------------------------------------------------------------ *
     * Sumber data                                                         *
     * ------------------------------------------------------------------ */

    /**
     * Paket sungguhan dari HC Learn.
     *
     * @return list<array<string, mixed>>
     */
    private function paketDariHcLearn(): array
    {
        $domain = rtrim((string) config('services.frans.hclearn_api.domain'), '/');
        $appId = Auth::user()->User_Key ?? null;
        $kodeKaryawan = Auth::user()->Kode_Users ?? null;

        if ($domain === '' || $appId === null) {
            throw new RuntimeException(
                'Sambungan HC Learn belum tersedia. Paket soal tidak bisa dibaca dari mana pun.'
            );
        }

        try {
            $auth = SignatureHelper::get(self::ENDPOINT_KATALOG, $appId, $kodeKaryawan);
            $response = Http::withHeaders($auth['headers'])
                ->timeout(20)
                ->get($domain.'/'.self::ENDPOINT_KATALOG);

            if (! $response->successful()) {
                throw new RuntimeException(
                    'HC Learn menolak permintaan daftar paket soal (HTTP '.$response->status().').'
                );
            }

            $isi = $response->json();
            $baris = $isi['result'] ?? $isi['data'] ?? $isi;

            return $this->rapikanBalasanHcLearn(is_array($baris) ? $baris : []);
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('PA: gagal membaca katalog HC Learn: '.$e->getMessage());

            throw new RuntimeException('Gagal menghubungi HC Learn: '.$e->getMessage());
        }
    }

    /**
     * Menyeragamkan balasan HC Learn ke bentuk yang dipakai layar dan penyimpanan.
     *
     * Bentuk mereka bertingkat empat — ujian → paket → alat tes → bagian — dan diratakan di
     * sini menjadi satu daftar bagian, karena admin melabeli kompetensi dan kompetensi tidak
     * mengenal batas alat tes. Nama alat tesnya tetap dibawa supaya layar bisa
     * mengelompokkannya kembali.
     *
     * `id_master_ujian` yang dipakai sebagai acuan, BUKAN paket dan bukan kode. Dokumentasi
     * HC Learn menyebut ujian sebagai "satuan yang dijadwalkan kepada peserta", dan kode
     * tidak unik: 23 alat di katalog mereka seluruhnya berkode "RDT".
     *
     * @param  array<int|string, mixed>  $baris
     * @return list<array<string, mixed>>
     */
    private function rapikanBalasanHcLearn(array $baris): array
    {
        $hasil = [];

        foreach ($baris as $u) {
            $u = (array) $u;
            $id = $u['id_master_ujian'] ?? ($u['Id_Master_Ujian'] ?? null);

            /* Ujian tanpa id DILEWATI, bukan disimpan ber-Id null. Bentuk lama menyimpannya,
               dan hasilnya ikatan yang menunjuk ke mana-mana. */
            if ($id === null || $id === '') {
                continue;
            }

            $paket = (array) ($u['paket'] ?? []);
            $bagian = PaBagianKatalog::ratakan($u);

            $hasil[] = [
                'Id_Master_Ujian' => (string) $id,
                'Nama_Ujian' => $u['nama'] ?? ($u['Nama_Ujian'] ?? null),
                'Id_Master_Paket_Ujian' => isset($paket['id_master_paket_ujian'])
                    ? (string) $paket['id_master_paket_ujian']
                    : null,
                'Nama_Paket' => $paket['nama'] ?? null,
                'Kode_Ujian' => $u['kode'] ?? null,
                'Kategori' => $u['kategori'] ?? null,
                'Sasaran_Level' => [],
                'Bagian' => $bagian,
                'jumlah_bagian' => count($bagian),

                /* Ujian yang seluruh alat tesnya khusus (Kraeplin, PAPI, DISC) memang tidak
                   berbagian. Tetap ditampilkan supaya admin tahu ia ada, tetapi ditandai
                   supaya layar bisa mencegah pengikatannya. */
                'bagian_tersedia' => $bagian !== [],
            ];
        }

        return $hasil;
    }

    /**
     * Menandai langkah PAKET_SOAL dari LUBANG sel (level x kategori), bukan dari berapa
     * level yang punya paket.
     *
     * Sejak soal dibedakan per kategori penilai, level yang punya satu paket tampak lengkap
     * pada hitungan lama -- padahal tiga kategori penilainya membaca susunan yang tidak
     * pernah dimaksudkan untuk mereka.
     */
    public function tandaiLangkah(int $cycleId, ?int $olehUserId = null): void
    {
        $m = $this->pengikatan()->matriks($cycleId);
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
