<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Membuat, menyunting, dan membaca satu siklus penilaian.
 *
 * DUA TINGKAT PENYUNTINGAN, DAN PERBEDAANNYA BUKAN SOAL KEHATI-HATIAN
 *
 * Mengganti NAMA siklus tidak mengubah angka siapa pun. Mengganti PERIODE-nya mengubah
 * baris KPI mana yang ditarik untuk setiap peserta — jadi menyuntingnya sesudah hasil
 * dihitung berarti nilai orang berubah tanpa satu pun jejak yang mengatakannya. Karena
 * itu nama dan deskripsi boleh disunting kapan saja, sedangkan tahun, periode dinilai,
 * dan jendela pengisian membeku begitu siklus dikunci.
 *
 * TAHUN HARUS SESUAI PERIODENYA — INI PENJAGA TERPENTING DI BERKAS INI
 *
 * Pilar KPI menyaring `KPI_Master_Penjadwalan.Tahun` memakai `Tahun` siklus, sementara
 * bulan-bulannya diturunkan dari `Periode_Dinilai_*`. Kalau keduanya berselisih —
 * misalnya tahun 2026 dengan periode Juli–Desember 2025 — saringannya tidak menemukan
 * satu baris pun, SETIAP peserta berakhir "Tidak ada KPI disetujui", dan sebab
 * sebenarnya tidak muncul di mana pun. Siklus terlihat berjalan; hasilnya kosong semua.
 *
 * Kegagalan itu tidak bisa dilihat dari layar mana pun, hanya dari menyandingkan dua
 * kolom yang tampak tidak berhubungan. Karena itu ia dijaga di sini, saat angkanya
 * ditulis, bukan dibiarkan sampai perhitungan.
 */
class PaCycleSetupService
{
    /** Status yang masih boleh mengubah tahun, periode, dan jendela pengisian. */
    public const STATUS_BOLEH_UBAH_PERIODE = ['DRAFT', 'PREPARED'];

    /**
     | JENDELA PENGISIAN DIPISAHKAN DARI PERIODE PENILAIAN.
     |
     | Keduanya semula satu kelompok, dan itu memberi jendela pengisian sebuah pembekuan yang
     | alasannya tidak berlaku baginya. Alasan yang tertulis di penolakannya berbunyi: "periode
     | menentukan baris KPI mana yang ditarik untuk setiap peserta, sehingga mengubahnya sesudah
     | siklus dikunci menggeser nilai orang tanpa jejak."
     |
     | Itu benar untuk `Periode_Dinilai_*`, `Tanggal_Acuan_Organisasi`, dan `Tahun` — ketiganya
     | menentukan APA yang diukur. Jendela pengisian tidak menentukan apa pun yang diukur; ia
     | hanya menentukan KAPAN penilai boleh mengisi. Mengubahnya tidak menggeser nilai siapa pun.
     |
     | Penggabungan itu melahirkan jalan buntu permanen. Membuka pengisian MENUNTUT jendela
     | terisi, tetapi mengisi jendela ditolak begitu siklus LOCKED, dan tidak ada satu pun
     | operasi gerbang yang mengembalikan siklus dari LOCKED ke PREPARED. Siklus yang dikunci
     | sebelum jendelanya diisi tidak akan pernah bisa dibuka — dan pesan yang ditampilkan
     | menyuruh admin melakukan hal yang sistemnya sendiri tolak.
     |
     | `OPEN` dan `CLOSED` sengaja TIDAK disertakan. Tenggat penugasan diturunkan dari jendela
     | ini pada saat penugasan dibentuk dan tidak ikut berubah sesudahnya; menggeser jendela
     | ketika pengisian sudah berjalan akan membuat jendela siklus dan tenggat penugasan
     | menunjuk dua tanggal berbeda. Memperpanjang tenggat siklus yang sedang berjalan adalah
     | kebutuhan yang nyata, tetapi menuntut penerusan tenggat yang sesungguhnya — pekerjaan
     | tersendiri, bukan efek samping dari melonggarkan daftar ini.
     */
    /*
     | OPEN ikut, dan itu perubahan yang disengaja.
     |
     | Semula berhenti di LOCKED dengan alasan yang benar: tenggat sudah diturunkan ke
     | penugasan saat dibentuk dan tidak ikut berubah, sehingga menggeser jendelanya
     | membuat keduanya menunjuk tanggal yang berbeda.
     |
     | Alasan itu DIHAPUS, bukan disiasati — `perbarui()` kini menurunkan ulang tenggatnya
     | setiap kali jendela berubah. Yang tersisa adalah kebutuhan yang paling nyata di
     | tengah siklus: memperpanjang tenggat karena peserta menyusul atau atasan cuti.
     | Memperpanjangnya tidak mengubah satu angka pun — hanya siapa yang tercatat
     | terlambat.
     |
     | CLOSED dan PUBLISHED tetap ditutup: jendela pengisian tidak lagi berarti apa-apa
     | sesudah pengisiannya berhenti, dan menggesernya di sana hanya mengaburkan catatan
     | keterlambatan yang sudah terbentuk.
     */
    public const STATUS_BOLEH_UBAH_JENDELA = ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN'];

    /*
     | CLOSED boleh mengubah jendela HANYA sambil dibuka ulang.
     |
     | Gerbang `BUKA_PENGISIAN` menerima CLOSED — menutup pengisian terlalu cepat adalah
     | kekeliruan yang wajar, dan jalan kembalinya memang disediakan. Tetapi jendelanya
     | tertutup untuk CLOSED, dan `bukaPengisian()` menuliskan jendela SEBELUM statusnya
     | berpindah. Akibatnya "buka ulang sambil memperpanjang" selalu ditolak, sementara
     | membuka ulang TANPA tanggal berhasil dan meninggalkan siklus OPEN dengan tenggat yang
     | sudah lewat — seluruh penilai tercatat terlambat sejak detik pertama.
     |
     | Yang dibuka di sini hanya jalur itu. Layar sunting siklus tetap menolak menggeser
     | jendela siklus yang sudah ditutup: di sana tidak ada pembukaan yang menyertainya, dan
     | menggesernya hanya mengaburkan catatan keterlambatan yang sudah terbentuk.
     */
    private const STATUS_BOLEH_UBAH_JENDELA_SAAT_DIBUKA = ['CLOSED'];

    /** Kolom yang menentukan APA yang diukur. */
    private const KOLOM_PERIODE_UKUR = [
        'Tahun', 'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai', 'Tanggal_Acuan_Organisasi',
    ];

    /** Kolom yang menentukan KAPAN pengisian dibuka. */
    private const KOLOM_JENDELA = ['Pengisian_Dari', 'Pengisian_Sampai'];

    public function __construct(
        private readonly PaCycleGate $gate,

        /* Dipakai satu tempat saja: menurunkan ulang tenggat saat jendela siklus
           bergeser. Disuntikkan alih-alih dipanggil lewat app() supaya ketergantungannya
           terbaca dari luar — layanan yang diam-diam menyentuh layanan lain adalah yang
           paling sulit ditelusuri saat perilakunya berubah. */
        private readonly PaAssignmentBuilderService $penugasan,

        /* Sumber nilai awal minimal masa kerja siklus baru (DDL-2026-191). */
        private readonly PaKelayakanMasaKerjaService $kelayakan,
    ) {}

    /**
     * Detail satu siklus beserta kesiapan dan operasi yang boleh dijalankan.
     *
     * @return array<string, mixed>
     */
    public function detail(int $cycleId): array
    {
        $siklus = $this->siklus($cycleId);

        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Status_Peserta')
            ->selectRaw('Status_Peserta as nilai, COUNT(*) as jumlah')
            ->pluck('jumlah', 'nilai')
            ->mapWithKeys(fn ($n, $k) => [(string) $k => (int) $n])
            ->all();

        return [
            'siklus' => (array) $siklus,
            'operasi_boleh' => $this->gate->operasiYangBoleh($cycleId),

            /* Dikirim sebagai keadaan, bukan disimpulkan layar dari `Status_Cycle`.
               Aturannya milik server; layar yang menyimpulkannya sendiri akan berselisih
               begitu aturannya berubah, dan yang berselisih di sini menentukan apakah
               sebuah formulir boleh disunting. */
            'boleh_ubah_periode' => in_array(
                $siklus->Status_Cycle,
                self::STATUS_BOLEH_UBAH_PERIODE,
                true,
            ),
            'alasan_periode_terkunci' => in_array($siklus->Status_Cycle, self::STATUS_BOLEH_UBAH_PERIODE, true)
                ? null
                : sprintf(
                    'Siklus berstatus %s. Mengubah periode sesudah dikunci akan menggeser '
                    .'baris KPI yang ditarik untuk setiap peserta, sehingga nilai orang '
                    .'berubah tanpa satu pun jejak yang mengatakannya.',
                    $siklus->Status_Cycle,
                ),

            /* DIKIRIM TERPISAH dari `boleh_ubah_periode`. Layar yang memakai satu penanda untuk
               dua aturan akan mematikan jendela pengisian di status LOCKED — dan mematikannya di
               sana persis menutup satu-satunya jalan menuju pembukaan pengisian. */
            'boleh_ubah_jendela' => in_array(
                $siklus->Status_Cycle,
                self::STATUS_BOLEH_UBAH_JENDELA,
                true,
            ),
            'alasan_jendela_terkunci' => in_array($siklus->Status_Cycle, self::STATUS_BOLEH_UBAH_JENDELA, true)
                ? null
                : sprintf(
                    'Siklus berstatus %s. Tenggat penugasan sudah diturunkan dari jendela ini '
                    .'saat penugasan dibentuk dan tidak ikut berubah, sehingga menggesernya '
                    .'sekarang membuat keduanya menunjuk tanggal yang berbeda.',
                    $siklus->Status_Cycle,
                ),

            'peserta' => [
                'total' => array_sum($peserta),
                'per_status' => $peserta,
            ],

            'kelengkapan' => $this->kelengkapan($cycleId),
        ];
    }

    /**
     * Membuat siklus baru. Selalu lahir `DRAFT`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function buat(array $data, ?int $olehUserId = null): array
    {
        $isi = $this->periksa($data, null);

        /* KODENYA DIBUAT SERVER, TIDAK DIMINTA DARI ADMIN.

           Sebelum 6 September 2026 admin mengetiknya sendiri, dan hasilnya persis seperti
           yang bisa diduga: `CONTOHSAJA`, `UDUHEU`. Kode itu bukan hiasan — ia yang dipakai
           menyebut siklus di perintah artisan, laporan, dan pesan galat, jadi kode yang
           tidak bisa dibaca membuat semua itu ikut tidak bisa dibaca.

           Nilai yang dikirim pemanggil DIABAIKAN, bukan ditolak: menolaknya membuat setiap
           klien lama gagal karena mengirim medan yang kini tak dipakai, padahal maksudnya
           sudah terpenuhi. */
        $kode = $this->kodeBaru((int) $isi['Tahun']);
        $isi['Kode_Cycle'] = $kode;

        /* TANGGAL ACUAN ORGANISASI DIISI SENDIRI, tidak lagi ditanyakan ke admin.

           Medannya dulu satu isian opsional bertulis "Opsional" — dan pertanyaan yang tidak
           bisa dijawab admin memang selalu berakhir kosong. Kosongnya bukan tanpa akibat:
           pembaca relasi jatuh ke `Periode_Dinilai_Sampai`, sehingga susunan organisasi
           dibaca menurut keadaan pada akhir periode yang dinilai. Untuk siklus yang dibuat
           berbulan-bulan sesudahnya, itu berarti atasan yang menilai adalah atasan LAMA.

           Yang dipakai sekarang tanggal siklus ini dibuat: keadaan organisasi saat penilaian
           disiapkan, yaitu susunan yang benar-benar berlaku bagi orang yang akan menilai.
           Nilainya tetap DITULIS, bukan dibiarkan kosong lalu disimpulkan saat dibaca —
           tanggal yang tersimpan bisa dipertanggungjawabkan enam bulan kemudian, sedangkan
           kekosongan yang ditafsirkan berubah artinya setiap kali aturan tafsirnya berubah.

           Pemanggil yang tetap mengirimkannya dihormati: API-nya tidak berubah, hanya
           layarnya yang berhenti bertanya. */
        if (($isi['Tanggal_Acuan_Organisasi'] ?? null) === null) {
            $isi['Tanggal_Acuan_Organisasi'] = FormatTanggalHelper::getCurrentTime()->format('Y-m-d');
        }

        $sumberId = $data['salin_dari_cycle_id'] ?? null;

        /* SATU TRANSAKSI: siklus baru dan salinan setelannya. Penyalinan yang gagal di tengah
           dulu meninggalkan siklus baru yang sudah tercipta tanpa setelan apa pun. */
        [$id, $salinan] = DB::transaction(function () use ($isi, $olehUserId, $sumberId) {
            $id = (int) DB::table('N_PA_Cycle')->insertGetId($isi + [
                // Selalu DRAFT. Siklus yang lahir langsung terkunci akan melewati penyiapan,
                // dan pesertanya tidak pernah terbentuk.
                'Status_Cycle' => 'DRAFT',
                // Siklus baru memakai grade kurva; DEFAULT kolom 'PITA' hanya untuk siklus lama.
                'Metode_Grade' => 'KURVA',
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
                // Asal salinan dicatat, supaya "setelan ini datang dari mana" bisa dijawab.
                'PA_Cycle_Sumber_Id' => $sumberId !== null && $sumberId !== '' ? (int) $sumberId : null,
                /* Salinan master saat siklus lahir — juga bila setelan lain disalin dari siklus
                   sebelumnya: aturan kelayakan mengikuti kebijakan yang berlaku SEKARANG. */
                'Min_Masa_Kerja_Bulan' => $this->kelayakan->nilaiMaster(),
            ], 'Id_PA_Cycle');

            $this->gate->lupakanMemo();

            return [$id, $this->salinSetelan($id, $sumberId, $olehUserId)];
        });

        return [
            'cycle_id' => $id,
            'kode_cycle' => $kode,
            'status' => 'DRAFT',
            'disalin' => $salinan,
            'cetak_biru' => $this->salinCetakBiruDariMaster($id),
        ];
    }

    /**
     * Menyalin cetak biru master ke siklus yang baru lahir: aturan penilai, bobot pilar,
     * syarat kompetensi, dan pita nilai.
     *
     * KENAPA OTOMATIS. Sebelum 6 September 2026 salinan ini hanya terbentuk ketika admin
     * menekan "Salin dari master" pada langkah Aturan Penilai. Akibatnya langkah PERTAMA
     * wizard — Bobot Penilaian — menyajikan layar kosong pada siklus yang baru dibuat,
     * karena ia membaca salinan siklus dan salinannya belum ada. Admin diminta menyetel
     * sesuatu yang baru bisa ada dua langkah kemudian.
     *
     * Masternya memang sudah lengkap saat siklus dibuat; tidak ada yang perlu ditunggu.
     *
     * KENAPA TIDAK MENGGAGALKAN PEMBUATAN. Penyalinan menolak level yang bobot masternya
     * bukan 100 — penolakan yang benar, tetapi kalau ia menggagalkan pembuatan, SATU baris
     * master yang cacat membuat tidak ada siklus yang bisa dibuat sama sekali, dan pesannya
     * muncul di layar yang tidak ada hubungannya dengan master. Karena itu kegagalannya
     * DILAPORKAN, bukan dilempar: siklusnya tetap lahir, dan sebabnya terbaca.
     *
     * Aman diulang: seluruh `salin*()` melewati level yang salinannya sudah ada, sehingga
     * tombol "Salin dari master" yang ditekan kemudian tidak menggandakan apa pun dan tidak
     * menimpa suntingan admin.
     *
     * @return array<string, mixed>
     */
    private function salinCetakBiruDariMaster(int $cycleId): array
    {
        try {
            /* Diresolusi di sini, bukan lewat konstruktor: `PaCyclePreparationService`
               menyuntikkan layanan INI, jadi menyuntikkannya balik membuat lingkaran yang
               ditolak container saat kedua layanan dibangun. */
            $hasil = app(PaCyclePreparationService::class)->salinCetakBiru($cycleId);

            return $hasil + ['berhasil' => true, 'galat' => null];
        } catch (\Throwable $e) {
            Log::warning('PA: cetak biru master gagal disalin ke siklus baru', [
                'cycle_id' => $cycleId,
                'sebab' => $e->getMessage(),
            ]);

            return [
                'policy' => 0, 'detail' => 0, 'pilar' => 0, 'kompetensi' => 0, 'pita' => 0,
                'berhasil' => false,
                'galat' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kode siklus berikutnya untuk satu tahun: `PA-2026-01`, `PA-2026-02`, dan seterusnya.
     *
     * BENTUKNYA DIPILIH SUPAYA BISA DIURUTKAN SEBAGAI TEKS. Nomor urut dua digit ber-nol
     * di depan membuat `PA-2026-02` jatuh sebelum `PA-2026-10`; tanpa nol itu urutan
     * abjadnya menaruh 10 sebelum 2, dan setiap laporan yang mengurutkan berdasarkan kode
     * menyajikan siklus dengan urutan yang salah tanpa ada yang menyadarinya.
     *
     * NOMORNYA DICARI LEWAT AWALAN, BUKAN DIHITUNG DARI JUMLAH BARIS. Menghitung baris
     * memberi nomor yang sudah terpakai begitu satu siklus dihapus — dan `LIKE 'PA-2026-%'`
     * masih memakai indeks karena awalannya tetap.
     *
     * BENTURAN DIJAWAB DENGAN MENGULANG, bukan dengan mengunci tabel. Dua admin yang
     * menekan "buat" pada detik yang sama akan menghitung nomor yang sama; yang kalah
     * ditolak `UX_PA_Cycle_Kode`, lalu menghitung ulang dan mendapat nomor berikutnya.
     * Mengunci tabelnya untuk kejadian yang mungkin terjadi sekali setahun berarti setiap
     * pembuatan siklus mengantre demi hal yang hampir tak pernah ada.
     */
    private function kodeBaru(int $tahun): string
    {
        $awalan = sprintf('PA-%d-', $tahun);

        $tertinggi = 0;

        foreach (DB::table('N_PA_Cycle')
            ->where('Kode_Cycle', 'like', $awalan.'%')
            ->pluck('Kode_Cycle') as $kode) {
            $ekor = substr((string) $kode, strlen($awalan));

            /* Hanya ekor yang SELURUHNYA angka yang dihitung. Siklus contoh bernama
               `PA-DEMO-FE` dan siklus lama bernama `PA-2026-H1` tidak boleh ikut
               menentukan nomor berikutnya — `(int) 'H1'` bernilai 0 dan diam-diam
               menyamakannya dengan "belum ada satu pun". */
            if ($ekor === '' || ! ctype_digit($ekor)) {
                continue;
            }

            $tertinggi = max($tertinggi, (int) $ekor);
        }

        return $awalan.str_pad((string) ($tertinggi + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Menyalin setelan dari siklus sebelumnya — yang MEMANG per siklus dan dibangun tangan.
     *
     * KENAPA HANYA PAKET SOAL
     *
     * Sebagian besar "setelan" siklus sebenarnya master: aturan penilai, bobot pilar, pita
     * nilai, syarat kompetensi. Keempatnya disalin sendiri oleh `siapkan()` dari masternya
     * yang hidup, jadi ia terbawa ke siklus baru tanpa perlu disalin di sini — menyalinnya
     * justru akan ditimpa beberapa menit kemudian.
     *
     * Paket soal berbeda: ia tidak punya master. `Sumber_Kontrak = MANUAL` berarti seseorang
     * menyusunnya butir demi butir untuk siklus itu, dan menyusun ulang tiap tahun adalah
     * pekerjaan berjam-jam yang hasilnya seharusnya sama.
     *
     * PENYESUAIAN BOBOT PILAR SENGAJA TIDAK DISALIN — DILAPORKAN
     *
     * Siklus sumber mungkin punya bobot pilar yang disesuaikan tangan (K23 mengizinkannya
     * bahkan sesudah kunci). Menyalinnya diam-diam berarti mengubah nilai akhir SETIAP
     * peserta pada level itu tanpa seorang pun memutuskannya tahun ini. Jumlahnya
     * dilaporkan supaya admin memutuskannya sendiri sesudah menyiapkan.
     *
     * @return array<string, mixed>|null null bila tidak menyalin dari mana pun
     */
    private function salinSetelan(int $tujuanId, mixed $sumberId, ?int $olehUserId): ?array
    {
        if ($sumberId === null || $sumberId === '') {
            return null;
        }

        $sumberId = (int) $sumberId;

        $sumber = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $sumberId)
            ->first(['Id_PA_Cycle', 'Kode_Cycle']);

        if ($sumber === null) {
            throw new RuntimeException(
                "Siklus sumber {$sumberId} tidak ditemukan, jadi tidak ada setelan yang bisa disalin."
            );
        }

        $paket = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $sumberId)
            ->where('Flag_Aktif', 'Y')
            ->get();

        $jumlahPaket = 0;
        $jumlahButir = 0;

        foreach ($paket as $pk) {
            $idBaru = (int) DB::table('N_PA_Cycle_Question_Package')->insertGetId([
                'PA_Cycle_Id' => $tujuanId,
                'ID_Level' => $pk->ID_Level,
                /* Kategori penilai ikut disalin. Tanpanya beberapa paket per level (satu per
                   kategori) semuanya ber-kategori NULL dan menabrak indeks unik
                   `UX_PA_CycleQPkg_Cycle_Level_Rater`. */
                'PA_Rater_Category_Id' => $pk->PA_Rater_Category_Id,
                'Id_Master_Paket_Ujian' => $pk->Id_Master_Paket_Ujian,
                'Nama_Paket_Ujian_Sumber' => $pk->Nama_Paket_Ujian_Sumber,
                'Id_Master_Ujian' => $pk->Id_Master_Ujian,
                'Nama_Ujian_Sumber' => $pk->Nama_Ujian_Sumber,
                'Sumber_Kontrak' => $pk->Sumber_Kontrak,
                'Catatan' => $pk->Catatan,
                'Flag_Aktif' => 'Y',
                'Snapshot_By' => $olehUserId,
            ], 'Id_PA_Cycle_Question_Package');

            $jumlahPaket++;

            $butir = DB::table('N_PA_Cycle_Question_Package_Item')
                ->where('PA_Cycle_Question_Package_Id', $pk->Id_PA_Cycle_Question_Package)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Tampil')
                ->get();

            $baris = [];

            foreach ($butir as $b) {
                $baris[] = [
                    'PA_Cycle_Question_Package_Id' => $idBaru,
                    'Id_Jenis_Soal' => $b->Id_Jenis_Soal,
                    'Nama_Bagian_Sumber' => $b->Nama_Bagian_Sumber,
                    'PA_Competency_Id' => $b->PA_Competency_Id,
                    /* Kunci pemetaan kiriman HC Learn (`Kode_Soal_Sumber`) dan bagiannya. Tanpa
                       ini setiap kiriman siklus salinan mendarat "butir hilang" dan penugasannya
                       tidak pernah naik ke TERKUMPUL. */
                    'Kode_Soal_Sumber' => $b->Kode_Soal_Sumber,
                    'Id_Jenis_Indikator' => $b->Id_Jenis_Indikator,
                    'Nama_Alat_Tes' => $b->Nama_Alat_Tes,

                    // Kolom warisan, tidak lagi dibaca siapa pun sejak DDL-2026-088.
                    // Tetap disalin apa adanya supaya salinan siklus benar-benar cermin
                    // masternya sampai kolomnya dicabut manusia.
                    'PA_Result_Note_Type_Id' => $b->PA_Result_Note_Type_Id,
                    'Jenis_Soal' => $b->Jenis_Soal,
                    'Bobot_Penilaian' => $b->Bobot_Penilaian,
                    'Skor_Maksimal' => $b->Skor_Maksimal,
                    'Urutan_Tampil' => $b->Urutan_Tampil,
                    'Flag_Aktif' => 'Y',
                    'Snapshot_By' => $olehUserId,
                ];
            }

            /* Dipotong 100. Satu paket bisa memuat puluhan butir dan satu siklus punya
               paket per level; tanpa potongan, satu sisipan bisa melewati batas 2.100
               parameter SQL Server pada organisasi yang levelnya banyak. */
            foreach (array_chunk($baris, 100) as $potongan) {
                DB::table('N_PA_Cycle_Question_Package_Item')->insert($potongan);
            }

            $jumlahButir += count($baris);
        }

        $bobotDisesuaikan = (int) DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $sumberId)
            ->whereNotNull('Alasan_Perubahan')
            ->count();

        return [
            'dari_cycle_id' => $sumberId,
            'dari_kode_cycle' => $sumber->Kode_Cycle,
            'paket_soal' => $jumlahPaket,
            'butir_soal' => $jumlahButir,
            /* Dilaporkan, TIDAK disalin. Menyalin penyesuaian bobot diam-diam berarti
               mengubah nilai akhir setiap peserta pada level itu tanpa seorang pun
               memutuskannya tahun ini. */
            'bobot_pilar_disesuaikan_di_sumber' => $bobotDisesuaikan,
            'catatan' => $bobotDisesuaikan > 0
                ? sprintf(
                    'Siklus sumber punya %d penyesuaian bobot pilar yang SENGAJA tidak ikut '
                    .'disalin — ia mengubah nilai akhir setiap peserta pada levelnya. '
                    .'Periksa ulang bobot pilar sesudah menyiapkan siklus ini.',
                    $bobotDisesuaikan,
                )
                : 'Aturan penilai, bobot pilar, pita nilai, dan syarat kompetensi tidak perlu '
                  .'disalin — semuanya diambil dari master saat siklus disiapkan.',
        ];
    }

    /**
     * Menyunting siklus.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function perbarui(
        int $cycleId,
        array $data,
        ?int $olehUserId = null,

        /* Dipasang HANYA oleh `PaCyclePreparationService::bukaPengisian()`, yang sudah
           melewati gerbang `BUKA_PENGISIAN` dan menjalankan keduanya dalam satu transaksi.
           Ditulis sebagai parameter, bukan disimpulkan dari status siklus: menyimpulkannya
           berarti setiap penyuntingan siklus CLOSED ikut terbuka, dan itu justru yang
           sengaja dilarang. */
        bool $sedangDibukaUlang = false,
    ): array {
        /* Ditolak, bukan diabaikan. Field ini lolos validasi controller karena
           `aturanSiklus()` dipakai bersama oleh buat dan perbarui — dan field yang
           DITERIMA lalu diabaikan diam-diam adalah bentuk kegagalan paling sulit
           dikenali: balasannya tetap 200, dan orang mengira setelannya tersalin.

           Menyalin saat menyunting juga memang tidak masuk akal: paket soal siklus ini
           sudah ada, dan menyalin dari siklus lain akan menggandakannya. */
        if (! empty($data['salin_dari_cycle_id'])) {
            throw new RuntimeException(
                'Setelan hanya bisa disalin saat MEMBUAT siklus, bukan saat menyuntingnya. '
                .'Paket soal siklus ini sudah ada; menyalin dari siklus lain akan '
                .'menggandakannya.'
            );
        }

        $siklus = $this->siklus($cycleId);
        $bolehPeriode = in_array($siklus->Status_Cycle, self::STATUS_BOLEH_UBAH_PERIODE, true);

        $isi = $this->periksa($data, $siklus);

        /* Kode TIDAK bisa disunting. Ia dibuat server dan dipakai menyebut siklus di
           perintah, laporan, dan pesan galat — mengubahnya membuat setiap rujukan yang
           sudah ditulis orang menunjuk sesuatu yang tidak ada lagi.

           Nilai yang SAMA dibuang diam-diam: layar mengirim kembali seluruh isian form,
           termasuk kode yang cuma ditampilkan. Nilai yang BERBEDA ditolak, bukan ikut
           dibuang — perubahan yang dimaksudkan seseorang tidak boleh hilang tanpa kabar. */
        if (! empty($isi['Kode_Cycle']) && $isi['Kode_Cycle'] !== $siklus->Kode_Cycle) {
            throw new RuntimeException(sprintf(
                'Kode siklus dibuat server dan tidak bisa diubah. Siklus ini tetap %s.',
                $siklus->Kode_Cycle,
            ));
        }

        unset($isi['Kode_Cycle']);

        $bolehJendela = in_array($siklus->Status_Cycle, self::STATUS_BOLEH_UBAH_JENDELA, true)
            || ($sedangDibukaUlang && in_array(
                $siklus->Status_Cycle,
                self::STATUS_BOLEH_UBAH_JENDELA_SAAT_DIBUKA,
                true,
            ));

        $berubah = fn (array $kolom) => array_values(array_filter(
            $kolom,
            fn ($k) => array_key_exists($k, $isi) && $this->berbeda($k, $isi[$k], $siklus->{$k} ?? null),
        ));

        $periodeBerubah = $berubah(self::KOLOM_PERIODE_UKUR);
        $jendelaBerubah = $berubah(self::KOLOM_JENDELA);

        if ($jendelaBerubah !== [] && ! $bolehJendela) {
            throw new RuntimeException(sprintf(
                'Siklus berstatus %s; %s tidak bisa diubah lagi di sini. Pengisian yang sudah '
                .'berjalan memakai tenggat yang diturunkan saat penugasan dibentuk, sehingga '
                .'menggeser jendelanya sekarang membuat tenggat siklus dan tenggat penugasan '
                .'menunjuk dua tanggal yang berbeda. Siklus yang sudah DITUTUP tetap bisa '
                .'diperpanjang, tetapi lewat "Buka Pengisian" — di sana tanggal barunya '
                .'ditulis bersamaan dengan pembukaannya.',
                $siklus->Status_Cycle,
                implode(', ', $jendelaBerubah),
            ));
        }

        if ($periodeBerubah !== [] && ! $bolehPeriode) {
            throw new RuntimeException(sprintf(
                'Siklus berstatus %s; %s tidak bisa diubah lagi. Periode menentukan baris '
                .'KPI mana yang ditarik untuk setiap peserta, sehingga mengubahnya sesudah '
                .'siklus dikunci menggeser nilai orang tanpa jejak. Nama dan deskripsi '
                .'tetap boleh disunting.',
                $siklus->Status_Cycle,
                implode(', ', $periodeBerubah),
            ));
        }

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update($isi + [
            'Updated_At' => now()->format('Y-m-d H:i:s'),
            'Updated_By' => $olehUserId,
        ]);

        $this->gate->lupakanMemo();

        /* TENGGAT DITURUNKAN ULANG BEGITU JENDELANYA BERGESER.

           Tanpa ini, layar siklus menunjukkan tanggal baru sementara penilai masih ditagih
           menurut tanggal lama — dua tanggal untuk satu jendela, dan tidak ada yang
           mengatakan mana yang berlaku. Inilah yang dulu menjadi alasan jendelanya dikunci
           sesudah dikunci; sekarang alasannya hilang karena keadaannya diperbaiki, bukan
           dilarang. */
        $tenggatDiperbarui = $jendelaBerubah === []
            ? 0
            : $this->penugasan->turunkanTenggat($cycleId, true);

        return [
            'cycle_id' => $cycleId,
            'periode_berubah' => $periodeBerubah,
            'tenggat_diperbarui' => $tenggatDiperbarui,
            /* Disebut terang-terangan. Peserta yang sudah terbentuk memakai salinan
               organisasi pada tanggal acuan LAMA; mengubah periodenya tidak menyiapkan
               ulang mereka dengan sendirinya. */
            'perlu_siapkan_ulang' => $periodeBerubah !== []
                && DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->exists(),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Apakah dua nilai periode benar-benar berbeda.
     *
     * TIDAK dibandingkan sebagai string, dan itu bukan kerapian. Kolomnya tidak setipe:
     * `Periode_Dinilai_*` dan `Tanggal_Acuan_Organisasi` bertipe `date`, sedangkan
     * `Pengisian_*` bertipe `datetime2(0)`. Basis data memulangkan yang pertama sebagai
     * "2026-07-01" dan yang kedua sebagai "2026-07-01 00:00:00" — sehingga formulir yang
     * mengirim ulang nilai yang SAMA PERSIS akan dinilai berubah.
     *
     * Akibatnya nyata dan menyesatkan: siklus DRAFT melaporkan `perlu_siapkan_ulang`
     * untuk simpan yang tidak mengubah apa-apa, dan siklus OPEN MENOLAK dengan
     * "Pengisian_Dari tidak bisa diubah lagi" padahal orangnya hanya mengganti nama.
     *
     * `Tahun` DIKELUARKAN dari jalur ini secara eksplisit. Ia bertipe angka, dan
     * `strtotime('2026')` sebenarnya berhasil — diurai sebagai pukul 20:26 hari ini.
     * Hasilnya kebetulan tetap benar, tetapi bergantung pada kuirk seperti itu adalah
     * ketepatan yang akan patah tanpa ada yang menduganya.
     */
    private function berbeda(string $kolom, mixed $baru, mixed $lama): bool
    {
        if ($baru === null || $lama === null) {
            return $baru !== $lama;
        }

        if ($kolom === 'Tahun') {
            return (int) $baru !== (int) $lama;
        }

        $a = strtotime((string) $baru);
        $b = strtotime((string) $lama);

        if ($a === false || $b === false) {
            return (string) $baru !== (string) $lama;
        }

        return $a !== $b;
    }

    private function siklus(int $cycleId): object
    {
        $s = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->first();

        if ($s === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        return $s;
    }

    /**
     * Memeriksa dan membentuk kolom yang akan ditulis.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function periksa(array $data, ?object $lama): array
    {
        $isi = [];

        foreach ([
            'kode_cycle' => 'Kode_Cycle',
            'nama_cycle' => 'Nama_Cycle',
            'deskripsi' => 'Deskripsi',
            'tahun' => 'Tahun',
            'periode_dinilai_dari' => 'Periode_Dinilai_Dari',
            'periode_dinilai_sampai' => 'Periode_Dinilai_Sampai',
            'pengisian_dari' => 'Pengisian_Dari',
            'pengisian_sampai' => 'Pengisian_Sampai',
            'tanggal_acuan_organisasi' => 'Tanggal_Acuan_Organisasi',
        ] as $kirim => $kolom) {
            if (array_key_exists($kirim, $data)) {
                $isi[$kolom] = $data[$kirim] === '' ? null : $data[$kirim];
            }
        }

        if ($lama === null) {
            // `Kode_Cycle` tidak lagi disebut: ia dibuat server sesudah pemeriksaan ini.
            foreach (['Nama_Cycle', 'Tahun'] as $wajib) {
                if (empty($isi[$wajib])) {
                    throw new RuntimeException("Kolom {$wajib} wajib diisi saat membuat siklus.");
                }
            }
        }

        if (isset($isi['Kode_Cycle'])) {
            $isi['Kode_Cycle'] = strtoupper(trim((string) $isi['Kode_Cycle']));
        }

        $this->periksaTanggal($isi, $lama);
        $this->periksaTahunSesuaiPeriode($isi, $lama);

        return $isi;
    }

    /**
     * @param  array<string, mixed>  $isi
     */
    private function periksaTanggal(array $isi, ?object $lama): void
    {
        foreach ([
            ['Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai', 'Periode dinilai'],
            ['Pengisian_Dari', 'Pengisian_Sampai', 'Jendela pengisian'],
        ] as [$dari, $sampai, $sebutan]) {
            $d = $isi[$dari] ?? ($lama->{$dari} ?? null);
            $s = $isi[$sampai] ?? ($lama->{$sampai} ?? null);

            if ($d === null || $s === null) {
                // Salah satu boleh kosong: siklus DRAFT memang belum tentu berjadwal, dan
                // menuntutnya sejak awal memaksa orang mengarang tanggal.
                continue;
            }

            if (strtotime((string) $s) < strtotime((string) $d)) {
                throw new RuntimeException(
                    "{$sebutan}: tanggal selesai lebih awal daripada tanggal mulai."
                );
            }
        }
    }

    /**
     * `Tahun` wajib sesuai tahun periode yang dinilai.
     *
     * @param  array<string, mixed>  $isi
     */
    private function periksaTahunSesuaiPeriode(array $isi, ?object $lama): void
    {
        $tahun = (int) ($isi['Tahun'] ?? ($lama->Tahun ?? 0));
        $dari = $isi['Periode_Dinilai_Dari'] ?? ($lama->Periode_Dinilai_Dari ?? null);
        $sampai = $isi['Periode_Dinilai_Sampai'] ?? ($lama->Periode_Dinilai_Sampai ?? null);

        if ($tahun === 0 || $dari === null) {
            return;
        }

        $tahunDari = (int) substr((string) $dari, 0, 4);
        $tahunSampai = $sampai === null ? $tahunDari : (int) substr((string) $sampai, 0, 4);

        if ($tahun === $tahunDari || $tahun === $tahunSampai) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Tahun siklus (%d) tidak sesuai periode yang dinilai (%s s.d. %s). Pilar KPI '
            .'menyaring jadwal KPI memakai TAHUN siklus sementara bulannya diturunkan dari '
            .'periode — kalau keduanya berselisih, saringannya tidak menemukan satu baris '
            .'pun dan SETIAP peserta berakhir "Tidak ada KPI disetujui", tanpa sebab yang '
            .'terbaca di layar mana pun.',
            $tahun, substr((string) $dari, 0, 10), substr((string) ($sampai ?? '-'), 0, 10),
        ));
    }

    /**
     * Apa yang sudah dan belum disiapkan pada siklus ini.
     *
     * @return array<string, mixed>
     */
    private function kelengkapan(int $cycleId): array
    {
        $cacah = fn (string $tabel) => (int) DB::table($tabel)->where('PA_Cycle_Id', $cycleId)->count();

        return [
            'salinan_aturan_penilai' => $cacah('N_PA_Cycle_Rater_Policy_Snapshot'),
            'salinan_bobot_pilar' => $cacah('N_PA_Cycle_Pillar_Weight_Snapshot'),
            'salinan_pita_nilai' => $cacah('N_PA_Cycle_Score_Range_Snapshot'),
            'salinan_syarat_kompetensi' => $cacah('N_PA_Cycle_Competency_Requirement_Snapshot'),
            'paket_soal' => $cacah('N_PA_Cycle_Question_Package'),
        ];
    }
}
