<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\KpiScoringService;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingKalkulator;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingNilaiBerlaku;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Mesin perhitungan hasil Performance Appraisal.
 *
 * EMPAT TAHAP, DAN URUTANNYA MENGIKAT
 *
 * 1. Nilai KPI — diambil YTD lewat `KpiScoringService::accumulatedScore()` atas rentang
 *    periode siklus. PA tidak membaca satu pun kolom nilai bulanan: keempat kolom nilai
 *    di `KPI_Update_Performance` sudah terbukti tidak saling sepakat, sedangkan service
 *    itulah yang juga dipakai layar KPI yang dilihat karyawan.
 * 2. Nilai kompetensi — butir jawaban dinormalkan per butir, dirata-rata per penilai,
 *    ditimbang di dalam kategori, lalu ditimbang antar kategori.
 * 3. Penggabungan — bobot pilar dari resolver, dijaga berjumlah 100.
 * 4. Grade dan kuadran — dicari di pita SALINAN SIKLUS, bukan master hidup.
 *
 * SATU AMBANG, BUKAN DUA
 *
 * Kategori penilai yang jumlah pengisinya di bawah `Min_Rater_Selesai` DIKELUARKAN dan
 * bobotnya dibagikan ke kategori yang memenuhi. Kategori yang kosong sama sekali
 * diperlakukan persis sama — nol dan satu sama-sama di bawah ambang. Menerapkan dua
 * aturan berbeda untuk keduanya akan menghasilkan hal yang ganjil: kategori tanpa
 * pengisi lolos sebagai "kosong" sementara kategori dengan satu pengisi justru menahan
 * hasilnya.
 *
 * KAPAN HASIL DITAHAN
 *
 * `TERTAHAN` bukan tahap, melainkan cabang. Yang ditahan: peserta tanpa KPI yang
 * disetujui, peserta yang bobot KPI-nya bukan 100, dan peserta yang tak satu pun
 * kategori penilainya memenuhi ambang. Ketiganya bisa saja diberi angka yang tampak
 * wajar — dan justru itu bahayanya.
 *
 * MENGHITUNG ULANG TIDAK MENGHAPUS
 *
 * Setiap perhitungan menaikkan `Calculation_Version`. Baris anak versi lama dinonaktifkan,
 * bukan dihapus. Catatan yang diketik admin sengaja selamat: yang dinonaktifkan hanya
 * catatan yang berasal dari jawaban penilai.
 */
class PaResultCalculationService
{
    /* Lingkup perhitungan massal. Dua, bukan satu, karena "hitung" pada siklus yang
       sebagian hasilnya sudah ditinjau admin adalah dua pekerjaan yang sangat berbeda:
       menyusulkan yang tertinggal, atau menghitung ulang semuanya. Sampai 7 September 2026
       hanya yang kedua yang ada, dan admin yang hanya ingin menyusulkan tidak punya pilihan
       selain menjalankannya juga atas peserta yang sudah ia kalibrasi. */
    public const LINGKUP_SEMUA = 'SEMUA';

    public const LINGKUP_BELUM = 'BELUM';

    public const STATUS_BELUM = 'BELUM_DIHITUNG';

    public const STATUS_DIHITUNG = 'DIHITUNG';

    public const STATUS_TERTAHAN = 'TERTAHAN';

    public const STATUS_TERBIT = 'TERBIT';

    /** Bobot total KPI yang sah menurut ketetapan bisnis. */
    private const TOTAL_BOBOT_KPI_SAH = 100.0;

    /**
     * Panjang maksimum `Alasan_Tertahan`, mengikuti `nvarchar(500)` di DDL-2026-080.
     *
     * Disebut sebagai konstanta supaya angkanya punya nama dan bisa ditemukan bila kolom
     * itu suatu saat dilebarkan. SQL Server MENOLAK, bukan memotong, nilai yang melampaui
     * lebar kolom — jadi melewatkan pemendekan ini bukan menghasilkan teks terpotong
     * melainkan penyimpanan hasil yang gagal.
     */
    private const BATAS_ALASAN = 500;

    /**
     * Bahan per siklus yang sama untuk semua peserta, diambil sekali.
     *
     * Sebelum ini, satu peserta memicu belasan kueri yang jawabannya identik bagi
     * seluruh peserta siklus itu: baris siklus, pita nilai, kuadran, pemetaan akun KPI,
     * dan parameter KPI. Pada 900 peserta di database jarak jauh itu berarti puluhan
     * ribu perjalanan bolak-balik — terukur lebih dari sepuluh menit, dan pernah
     * membuat perhitungan satu siklus tidak pernah selesai.
     *
     * @var array<int, array<string, mixed>>
     */
    private array $konteks = [];

    /** Peta kompetensi ke jenisnya; diambil sekali per proses. */
    private ?array $petaJenis = null;

    /**
     * Jam server untuk satu perhitungan.
     *
     * `FormatTanggalHelper::getCurrentTime()` membaca tabel, jadi setiap pemanggilan
     * adalah satu perjalanan ke database. Terukur 2,5 pemanggilan per peserta - lebih
     * mahal daripada seluruh pembacaan penugasannya. Satu perhitungan memakai satu jam:
     * selain hemat, itu juga lebih benar, karena seluruh baris yang lahir dari satu
     * perhitungan memang terjadi pada saat yang sama.
     */
    private mixed $jamProses = null;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaParticipantRuleResolver $resolver,
        private readonly PaBobotGuard $bobot,
        private readonly KpiScoringService $scoring,
        private readonly PaSkorEfektif $skorEfektif,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingNilaiBerlaku $balancing,
        private readonly PaBalancingPenjaga $penjagaBalancing,
        private readonly PaResultHistoryService $riwayat,
    ) {}

    /**
     * Menghitung hasil seluruh peserta DIIKUTKAN dalam satu siklus.
     *
     * Kegagalan satu peserta tidak menjatuhkan sisanya: yang gagal dicatat berikut
     * pesannya. Menghentikan seluruh siklus karena satu orang akan membuat siklus besar
     * mustahil diselesaikan.
     *
     * `$batas` memotong jumlah peserta yang diproses satu panggilan, dan `$mulaiDariId`
     * melanjutkan dari peserta berikutnya. Keduanya ada karena menghitung satu siklus
     * penuh BUKAN pekerjaan satu permintaan HTTP: terukur ±0,5 detik per orang pada
     * database pengembangan, jadi 859 peserta memakan menit-menit yang pasti melewati
     * batas waktu permintaan mana pun. Pemanggil yang serius memakai `pa:hitung-siklus`.
     *
     * `$lingkup` memisahkan dua pekerjaan yang selama ini menumpang satu tombol:
     *
     *   LINGKUP_BELUM   Hanya yang BELUM PERNAH dihitung dan penilainya sudah lengkap.
     *                   Menyusulkan yang tertinggal, tanpa menyentuh satu pun hasil yang
     *                   sudah ditinjau atau dikalibrasi admin.
     *
     *   LINGKUP_SEMUA   Seluruh peserta diikutkan, termasuk yang sudah dihitung dan
     *                   dikalibrasi. Keputusan kalibrasinya dipertahankan (lihat
     *                   `nilaiKalibrasiSesudahHitung`), tetapi grade sistemnya berubah dan
     *                   selisihnya menjadi pekerjaan tinjau ulang bagi admin.
     *
     * @return array{
     *     dihitung:int, tertahan:int, gagal:list<array{participant_id:int, pesan:string}>,
     *     diproses:int, terakhir_id:int|null, selesai:bool, lingkup:string
     * }
     */
    public function hitungSiklus(
        int $cycleId,
        ?int $olehUserId = null,
        ?int $batas = null,
        ?int $mulaiDariId = null,
        string $lingkup = self::LINGKUP_SEMUA,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::HITUNG_HASIL, $cycleId);

        /* PERHITUNGAN MASSAL tetap menuntut siklus TERTUTUP.
           Menjalankannya selagi jendela terbuka berarti menghitung ratusan orang yang
           penilainya masih berjalan, lalu menghitung ulang seluruhnya begitu mereka selesai —
           pekerjaan yang hasilnya dibuang sendiri. Jalur per orang di `hitung()` yang melayani
           siklus berjalan. */
        if ($this->gate->status($cycleId) !== 'CLOSED') {
            throw new RuntimeException(
                "Perhitungan massal hanya untuk siklus yang sudah ditutup; siklus {$cycleId} "
                .'masih berstatus '.$this->gate->status($cycleId).'. Peserta yang penilainya '
                .'sudah lengkap dihitung satu per satu tanpa menunggu penutupan.'
            );
        }

        $kueri = $this->kueriPesertaHitung($cycleId, $lingkup)->orderBy('Id_PA_Participant');

        if ($mulaiDariId !== null) {
            $kueri->where('Id_PA_Participant', '>', $mulaiDariId);
        }

        if ($batas !== null) {
            $kueri->limit($batas);
        }

        $ids = $kueri->pluck('Id_PA_Participant');

        // Override sparse dipramuat sekali; tanpa ini tiap peserta menanyakan dua tabel
        // yang pada siklus normal memang kosong.
        $this->resolver->muatOverrideSiklus($cycleId);

        // Nilai berlaku Balancing seluruh siklus dimuat sekali, bukan satu kueri per peserta.
        $this->balancing->muatSiklus($cycleId);

        $dihitung = 0;
        $tertahan = 0;
        $gagal = [];

        try {
            foreach ($ids as $id) {
                try {
                    $hasil = $this->hitung((int) $id, $olehUserId);

                    if ($hasil['status'] === self::STATUS_TERTAHAN) {
                        $tertahan++;
                    } else {
                        $dihitung++;
                    }
                } catch (RuntimeException $e) {
                    $gagal[] = ['participant_id' => (int) $id, 'pesan' => $e->getMessage()];
                }
            }
        } finally {
            /* Memo hanya berlaku selama satu potongan. Bila dibiarkan, pemanggilan hitung()
               berikutnya di proses yang sama (pekerja antrean, cicilan berikutnya) memakai After
               yang sudah basi sesudah ada hitung ulang Balancing di antaranya. */
            $this->balancing->lupakan();
        }

        return [
            'dihitung' => $dihitung,
            'tertahan' => $tertahan,
            'gagal' => $gagal,
            'diproses' => $ids->count(),
            'terakhir_id' => $ids->last() === null ? null : (int) $ids->last(),
            // Potongan yang tidak penuh berarti tidak ada lagi yang tersisa.
            'selesai' => $batas === null || $ids->count() < $batas,

            /* Lingkupnya DIKEMBALIKAN, tidak sekadar diterima. Layar mencicil panggilan ini
               belasan kali; kalau lingkupnya pernah salah kirim di tengah cicilan, satu-
               satunya yang bisa memberi tahu adalah balasannya sendiri. */
            'lingkup' => $lingkup,
        ];
    }

    /**
     * Peserta yang BELUM PERNAH dihitung dan penilainya sudah lengkap.
     *
     * "Sudah selesai" di sini berarti dua hal sekaligus, dan keduanya wajib: tidak ada lagi
     * penugasan yang berjalan, DAN ada setidaknya satu yang benar-benar selesai. Syarat
     * kedua bukan hiasan - tanpa itu, peserta yang seluruh penugasannya dibatalkan (atau
     * yang tidak pernah punya penugasan sama sekali) lolos sebagai "lengkap", dihitung, dan
     * mendarat sebagai TERTAHAN. Admin yang menekan "hitung yang tertinggal" lalu menerima
     * lima puluh baris tertahan baru akan menyimpulkan perhitungannya rusak.
     *
     * Mereka tidak jadi tak terlihat: pratinjaunya menghitung mereka sebagai
     * `belum_lengkap`, dan lingkup SEMUA tetap menyapunya.
     *
     * @param  \Illuminate\Database\Query\Builder  $kueri
     */
    private function batasiKeBelumTerhitung($kueri): void
    {
        $kueri
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Result as r')
                    ->whereColumn('r.PA_Participant_Id', 'N_PA_Participant.Id_PA_Participant')
                    ->where('r.Flag_Aktif', 'Y')
                    ->where('r.Status_Hasil', '<>', self::STATUS_BELUM);
            })
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Assignment as a')
                    ->whereColumn('a.PA_Participant_Id', 'N_PA_Participant.Id_PA_Participant')
                    ->where('a.Flag_Aktif', 'Y')
                    ->whereNotIn('a.Status_Penugasan', ['SELESAI', 'DIBATALKAN']);
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Assignment as a')
                    ->whereColumn('a.PA_Participant_Id', 'N_PA_Participant.Id_PA_Participant')
                    ->where('a.Flag_Aktif', 'Y')
                    ->where('a.Status_Penugasan', 'SELESAI');
            });
    }

    /**
     * Peserta yang AKAN diambil perhitungan massal untuk lingkup ini — satu-satunya definisi,
     * dipakai `hitungSiklus()` dan penghitung total tugas latar. Dua salinan aturan pemilih
     * membuat bilah kemajuan berhenti di 97% atau melewati 100%.
     */
    private function kueriPesertaHitung(int $cycleId, string $lingkup)
    {
        $kueri = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y');

        if ($lingkup === self::LINGKUP_BELUM) {
            $this->batasiKeBelumTerhitung($kueri);
        }

        return $kueri;
    }

    /** Berapa peserta yang akan diambil `hitungSiklus()` untuk lingkup ini (total tugas latar). */
    public function jumlahAkanDihitung(int $cycleId, string $lingkup = self::LINGKUP_SEMUA): int
    {
        return (int) $this->kueriPesertaHitung($cycleId, $lingkup)->count();
    }

    /**
     * Apa yang akan terjadi kalau tombol Hitung ditekan - SEBELUM ditekan.
     *
     * Dibuat 7 September 2026. Tombolnya menjalankan pekerjaan yang berjalan menit-menit dan
     * menyentuh hasil yang sudah ditinjau admin, dan sampai sekarang satu-satunya cara tahu
     * berapa banyak yang tersentuh adalah menjalankannya. Konfirmasi yang tidak menyebut
     * angka bukan konfirmasi; ia hanya pintu yang harus dibuka.
     *
     * SATU KUERI, bukan tujuh. Angka-angka ini muncul di dialog yang dibuka berkali-kali
     * pada siklus berisi hampir seribu peserta.
     *
     * @return array{
     *     total:int, siap_hitung_pertama:int, belum_lengkap:int, sudah_dihitung:int,
     *     tertahan:int, terkalibrasi:int, terbit:int
     * }
     */
    public function pratinjauHitungSiklus(int $cycleId): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::HITUNG_HASIL, $cycleId);

        /* DUA TINGKAT, bukan satu. SQL Server menolak agregat atas ekspresi yang memuat
           sub-kueri ("Cannot perform an aggregate function on an expression containing an
           aggregate or a subquery"), sehingga EXISTS-nya harus lebih dulu menjadi kolom
           0/1 di kueri dalam, baru dijumlahkan di luar. */
        $dalam = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', function ($join) {
                $join->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')
                    ->where('r.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->selectRaw("
                /* Hasil BELUM_DIHITUNG diperlakukan sama dengan tidak ada baris hasil sama
                   sekali. Keduanya berarti hal yang sama bagi admin - belum ada angka - dan
                   membedakannya di layar hanya memaksa orang menghafal seluk-beluk
                   penyimpanan. */
                CASE WHEN r.Id_PA_Result IS NULL OR r.Status_Hasil = '".self::STATUS_BELUM."'
                     THEN 1 ELSE 0 END AS belum_berangka,

                /* TERKUMPUL sengaja ikut terhitung 'berjalan': lembarnya memang sudah terisi,
                   tetapi penilainya belum mengirim, jadi ia belum boleh ikut dihitung. */
                CASE WHEN EXISTS (
                    SELECT 1 FROM N_PA_Assignment a
                    WHERE a.PA_Participant_Id = p.Id_PA_Participant AND a.Flag_Aktif = 'Y'
                      AND a.Status_Penugasan NOT IN ('SELESAI', 'DIBATALKAN')
                ) THEN 1 ELSE 0 END AS ada_berjalan,

                CASE WHEN EXISTS (
                    SELECT 1 FROM N_PA_Assignment a
                    WHERE a.PA_Participant_Id = p.Id_PA_Participant AND a.Flag_Aktif = 'Y'
                      AND a.Status_Penugasan = 'SELESAI'
                ) THEN 1 ELSE 0 END AS ada_selesai,

                r.Status_Hasil, r.Flag_Dikalibrasi
            ");

        $baris = DB::query()
            ->fromSub($dalam, 't')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN belum_berangka = 1 AND ada_berjalan = 0 AND ada_selesai = 1
                         THEN 1 ELSE 0 END) as siap_hitung_pertama,
                SUM(CASE WHEN belum_berangka = 1 AND NOT (ada_selesai = 1 AND ada_berjalan = 0)
                         THEN 1 ELSE 0 END) as belum_lengkap,
                SUM(CASE WHEN Status_Hasil = '".self::STATUS_DIHITUNG."' THEN 1 ELSE 0 END) as sudah_dihitung,
                SUM(CASE WHEN Status_Hasil = '".self::STATUS_TERTAHAN."' THEN 1 ELSE 0 END) as tertahan,
                SUM(CASE WHEN Flag_Dikalibrasi = 'Y' THEN 1 ELSE 0 END) as terkalibrasi,
                SUM(CASE WHEN Status_Hasil = '".self::STATUS_TERBIT."' THEN 1 ELSE 0 END) as terbit
            ")
            ->first();

        return [
            'total' => (int) ($baris->total ?? 0),
            'siap_hitung_pertama' => (int) ($baris->siap_hitung_pertama ?? 0),
            'belum_lengkap' => (int) ($baris->belum_lengkap ?? 0),
            'sudah_dihitung' => (int) ($baris->sudah_dihitung ?? 0),
            'tertahan' => (int) ($baris->tertahan ?? 0),
            'terkalibrasi' => (int) ($baris->terkalibrasi ?? 0),
            'terbit' => (int) ($baris->terbit ?? 0),
        ];
    }

    /**
     * Menghitung hasil satu peserta.
     *
     * @return array{
     *     result_id:int, status:string, versi:int,
     *     nilai_kpi:float|null, nilai_kompetensi:float|null, final_score:float|null,
     *     kode_grade:string|null, kode_kuadran:string|null, alasan_tertahan:string|null
     * }
     */
    /**
     * @param  string|null  $sebab  Jenis peristiwa riwayat bila pemanggilnya bukan hitung biasa
     *                              (mis. PaResultHistoryService::BALANCING).
     */
    public function hitung(int $participantId, ?int $olehUserId = null, ?string $sebab = null): array
    {
        /* Baris pesertanya dibaca lebih dulu, lalu gerbangnya ditanya memakai siklus
           dari baris itu. `pastikanBolehUntukPeserta()` akan menanyakan siklus peserta
           lewat kuerinya sendiri - jawaban yang sudah ada di tangan. Gerbangnya tetap
           berjalan; hanya sumber siklusnya yang berubah. */
        [$peserta, $hasilLama] = $this->pesertaDanHasil($participantId);

        $this->gate->pastikanBoleh(PaCycleGate::HITUNG_HASIL, (int) $peserta->PA_Cycle_Id);

        /* Selagi jendela masih TERBUKA, hanya peserta yang penilainya sudah lengkap yang boleh
           dihitung. Sesudah ditutup, sisa yang tidak pernah lengkap dihitung apa adanya dengan
           ambang `Min_Rater_Selesai` yang sudah berlaku — itu keputusan penutupan, bukan
           kelalaian. */
        if ($this->gate->status((int) $peserta->PA_Cycle_Id) === 'OPEN') {
            $this->pastikanPesertaLengkap($participantId);
        }

        if ($peserta->Status_Peserta !== 'DIIKUTKAN') {
            throw new RuntimeException(
                "Peserta {$participantId} berstatus {$peserta->Status_Peserta}; "
                .'hanya peserta DIIKUTKAN yang dihitung.'
            );
        }

        /* Hasil yang SUDAH TERBIT tidak dihitung ulang.
           Karyawan sudah melihat angkanya. Menghitung ulang akan menggantinya tanpa
           seorang pun tahu bahwa yang dilihat kemarin bukan yang tersimpan hari ini —
           dan tidak ada jejak yang bisa menjelaskan selisihnya. Yang benar untuk hasil
           terbit yang keliru adalah kalibrasi, karena kalibrasi meninggalkan riwayat. */
        if ($hasilLama->Status_Hasil === self::STATUS_TERBIT) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} sudah TERBIT dan tidak dihitung ulang. "
                .'Karyawan sudah melihat angkanya; menggantinya diam-diam tidak meninggalkan '
                .'jejak apa pun. Pakai kalibrasi bila hasilnya perlu digeser — kalibrasi '
                .'mencatat sebab, pelaku, dan nilai sebelumnya.'
            );
        }

        /* Hasil yang sudah DIRILIS KE LEADER juga tidak dihitung ulang, dengan alasan yang sama:
           leader sudah membaca angka dan grade-nya di halaman PA Tim. Sampai 8 Oktober 2026
           hanya TERBIT yang ditolak, sehingga kiriman HC Learn susulan atau tombol hitung massal
           mengganti angka yang sedang dibaca leader tanpa jejak apa pun di layarnya. Aturannya
           sama dengan `PaBalancingPenjaga::jumlahSudahDibaca()` dan `PaKurvaService::buka()`:
           terbit ATAU dirilis berarti sudah dibaca orang. Jalan keluarnya ada dan tercatat —
           cabut rilis leader (wajib bersebab), lalu hitung ulang. */
        if ($hasilLama->Published_Leader_At !== null) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} sudah dirilis ke leader dan tidak dihitung ulang. "
                .'Leader sudah membaca angkanya; menggantinya diam-diam membuat yang dibaca berbeda '
                .'dari yang tersimpan. Cabut rilis leader lebih dulu (alasannya tercatat), lalu hitung ulang.'
            );
        }

        /* Peserta yang SEDANG dibahas di sesi Balancing tidak dihitung ulang. Before di sesi
           itu adalah potret hasil saat sesi dibuat; menghitung ulang sekarang membuatnya basi
           tanpa ada yang tahu — termasuk dari kiriman HC Learn susulan. */
        $sesiTerbuka = $this->penjagaBalancing->sesiTerbukaPeserta($participantId);

        if ($sesiTerbuka !== null) {
            throw new RuntimeException(
                "Peserta {$participantId} sedang dibahas di sesi Balancing \"{$sesiTerbuka->Nama_Sesi}\". "
                .'Finalkan atau batalkan sesinya lebih dulu, lalu hitung ulang.'
            );
        }

        // Satu jam untuk seluruh baris yang lahir dari perhitungan ini. Lihat $jamProses.
        $this->jamProses = FormatTanggalHelper::getCurrentTime();

        $kpi = $this->tahapKpi($peserta);
        $kompetensi = $this->tahapKompetensi($participantId, null, (int) $peserta->PA_Cycle_Id);

        return DB::transaction(function () use (
            $participantId, $peserta, $hasilLama, $kpi, $kompetensi, $olehUserId, $sebab
        ) {
            /*
             | DIKUNCI DAN DIPERIKSA ULANG DI DALAM TRANSAKSI (14 Okt 2026).
             |
             | Penjaga TERBIT/dirilis di atas membaca hasil SEBELUM masukannya dihitung (±0,5 detik).
             | Admin yang menekan Terbitkan di antara keduanya membuat perhitungan ini menimpa
             | hasil yang baru terbit kembali menjadi DIHITUNG — tanpa jejak. Dua perhitungan
             | bersamaan untuk orang yang sama (dua penilai terakhir mengirim berbarengan) juga
             | menulis nomor versi yang sama. Baris hasilnya dikunci di sini supaya keduanya
             | berurutan, lalu status dan versinya dibaca dari baris yang terkunci.
             */
            $terkini = DB::table('N_PA_Result')
                ->where('Id_PA_Result', (int) $hasilLama->Id_PA_Result)
                ->lockForUpdate()
                ->first(['Status_Hasil', 'Published_Leader_At', 'Calculation_Version']);

            if ($terkini !== null && $terkini->Status_Hasil === self::STATUS_TERBIT) {
                throw new RuntimeException(
                    "Hasil peserta {$participantId} baru saja TERBIT selagi dihitung; perhitungan ini dibatalkan "
                    .'supaya angka yang sudah dilihat karyawan tidak tertimpa.'
                );
            }

            if ($terkini !== null && $terkini->Published_Leader_At !== null) {
                throw new RuntimeException(
                    "Hasil peserta {$participantId} baru saja dirilis ke leader selagi dihitung; perhitungan ini "
                    .'dibatalkan. Cabut rilis leader lebih dulu bila memang perlu dihitung ulang.'
                );
            }

            if ($terkini !== null) {
                $hasilLama->Calculation_Version = $terkini->Calculation_Version;
            }

            /*
             | BARIS VERSI LAMA SELALU DINONAKTIFKAN LEBIH DULU.
             |
             | Versi sebelumnya melewatinya ketika `Status_Hasil` masih `BELUM_DIHITUNG`, dengan
             | alasan "perhitungan pertama tidak punya versi lama untuk dinonaktifkan". Alasan
             | itu memakai status sebagai penanda ada-tidaknya baris anak — dan penanda itu
             | BERBOHONG: satu siklus di pengembangan punya 859 hasil berstatus BELUM_DIHITUNG
             | yang seluruhnya memiliki 2.648 baris kategori AKTIF.
             |
             | Akibatnya bukan baris berlebih melainkan kebuntuan total. `UX_PA_Result_Rater_Cat_Aktif`
             | menegakkan satu baris aktif per (hasil, kategori); penyisipan versi baru tanpa
             | menonaktifkan yang lama langsung ditolak "Cannot insert duplicate key", dan TIDAK
             | SATU PUN peserta di siklus itu bisa dihitung. Kegagalannya pun menyesatkan: yang
             | terbaca adalah galat basis data, bukan aturan yang dilanggar.
             |
             | Biayanya dua UPDATE per peserta yang pada perhitungan perdana menyentuh nol baris.
             | Itu harga yang dibayar dengan sadar: perjalanan bolak-balik bisa dihitung, siklus
             | yang mustahil dihitung tidak bisa ditawar.
             */
            $barisLama = $this->nonaktifkanVersiLama((int) $hasilLama->Id_PA_Result, $olehUserId);

            /*
             | NOMOR VERSINYA DITURUNKAN DARI KENYATAAN, bukan dari status.
             |
             | `Calculation_Version` bawaannya 1 bahkan pada hasil yang belum pernah dihitung,
             | sehingga ia tidak bisa membedakan "sekali dihitung" dari "belum pernah". Yang bisa
             | membedakannya adalah jumlah baris yang barusan dinonaktifkan — dan angka itu sudah
             | ada di tangan tanpa satu kueri tambahan.
             |
             | Tanpa ini nomornya bisa MUNDUR: hasil ber-`Calculation_Version` 2 yang statusnya
             | masih BELUM_DIHITUNG akan menulis versi 1 di atas versi 2, dan riwayat perhitungan
             | membaca seolah yang lebih baru justru lebih lama.
             */
            $versi = $barisLama > 0
                ? ((int) $hasilLama->Calculation_Version) + 1
                : 1;

            $tertahan = $this->gabungAlasanTertahan(
                $kpi['alasan_tertahan'] ?? null,
                $kompetensi['alasan_tertahan'] ?? null,
            );

            $pilar = $this->resolver->pillarWeight($participantId);
            $this->bobot->pastikanSeratus(
                ['KPI' => $pilar['bobot_kpi'], 'KOMPETENSI' => $pilar['bobot_kompetensi']],
                "bobot pilar peserta {$participantId}"
            );

            $final = null;
            $grade = null;
            $kurvaId = null;
            $kuadranId = null;
            $kodeKuadran = null;

            if ($tertahan === null) {
                $final = PaBalancingKalkulator::nilaiAkhir(
                    (float) $kpi['nilai'], (float) $kompetensi['nilai'],
                    (float) $pilar['bobot_kpi'], (float) $pilar['bobot_kompetensi'],
                );

                if ($this->konteks((int) $peserta->PA_Cycle_Id)['metode_grade'] === 'KURVA') {
                    /* Grade dari kurva BEKU grup peserta; NULL selama kurva belum dikunci —
                       sebelum itu grade hanya dibaca sebagai pratinjau di layar Distribusi. */
                    $beku = $this->kurva->gradeBeku($participantId, $final);
                    $grade = $beku['kode'];
                    $kurvaId = $beku['curve_id'];

                    /* Kurva sudah dikunci tetapi grup peserta tidak punya kurva beku: DITAHAN
                       dengan alasan yang menyebut nilainya, bukan dilempar — satu peserta
                       susulan tidak boleh menggagalkan penghitungan seluruh siklus. Nilai akhir
                       ikut dikosongkan: hasil TERTAHAN tidak membawa angka. */
                    if ($beku['alasan'] !== null) {
                        $tertahan = $beku['alasan'];
                        $final = null;
                    }
                } else {
                    $grade = $this->cariPita(
                        (int) $peserta->PA_Cycle_Id, 'FINAL', 'GRADE', $final
                    )?->Kode_Range;

                    if ($grade === null) {
                        throw new RuntimeException(
                            "Nilai akhir {$final} peserta {$participantId} tidak masuk satu pun pita "
                            .'grade pada salinan siklus. Pitanya berlubang atau tidak terbuka ke atas.'
                        );
                    }
                }

                if ($tertahan === null) {
                    [$kuadranId, $kodeKuadran] = $this->cariKuadran(
                        (int) $peserta->PA_Cycle_Id, $kpi['nilai'], $kompetensi['nilai']
                    );
                }
            }

            /*
             | KEPUTUSAN KALIBRASI TIDAK DIHAPUS PERHITUNGAN ULANG.
             |
             | `Kode_Grade_Sistem` dan `Kode_Grade_Final` sengaja dua kolom: yang pertama apa
             | kata mesin, yang kedua apa yang diputuskan forum. Sampai 7 September 2026
             | perhitungan ulang menimpa KEDUANYA dan menyetel `Flag_Dikalibrasi` kembali ke
             | 'T' — sehingga satu kiriman susulan dari seorang penilai cukup untuk
             | mengembalikan grade yang sudah disepakati, otomatis, tanpa ada yang menekan
             | apa pun. Yang tersisa hanya baris di `N_PA_Calibration_History`, sementara
             | angka yang dibaca orang sudah berubah.
             |
             | Sekarang: grade sistem selalu diperbarui, grade final dan kuadran hasil
             | kalibrasi DIPERTAHANKAN. Bahwa angkanya berubah sesudah forum memutuskan tidak
             | disembunyikan — layar menurunkannya dari perbandingan `Calculated_At` dengan
             | waktu kalibrasi terakhir, jadi tidak perlu kolom baru.
             |
             | KECUALI hasilnya menjadi TERTAHAN: di sana tidak ada grade sama sekali, dan
             | grade final tidak bisa berdiri di atas hasil yang tak punya angka. Kalibrasinya
             | dilepas, dan pelepasannya dicatat sebagai riwayat kalibrasi tersendiri supaya
             | bisa ditelusuri.
             */
            $kalibrasi = $this->nilaiKalibrasiSesudahHitung(
                $hasilLama, $tertahan, $grade, $kuadranId,
            );

            if ($kalibrasi['dilepas']) {
                $this->lepaskanKalibrasi($hasilLama, (string) $tertahan, $olehUserId);
            }

            DB::table('N_PA_Result')
                ->where('Id_PA_Result', $hasilLama->Id_PA_Result)
                ->update([
                    'Status_Hasil' => $tertahan === null ? self::STATUS_DIHITUNG : self::STATUS_TERTAHAN,
                    'Nilai_KPI' => $kpi['nilai'],
                    'Nilai_KPI_Sebelum_Cap' => $kpi['nilai_sebelum_cap'],
                    'KPI_Periode_Dari' => $kpi['periode_dari'],
                    'KPI_Periode_Sampai' => $kpi['periode_sampai'],
                    'Jumlah_KPI_Belum_Disetujui' => $kpi['belum_disetujui'],
                    'Nilai_Kompetensi' => $kompetensi['nilai'],
                    'Jumlah_Rater_Diharapkan' => $kompetensi['rater_diharapkan'],
                    'Jumlah_Rater_Mengisi' => $kompetensi['rater_mengisi'],
                    'Bobot_KPI_Terpakai' => $pilar['bobot_kpi'],
                    'Bobot_Kompetensi_Terpakai' => $pilar['bobot_kompetensi'],
                    'Final_Score' => $final,
                    // Apa kata mesin. SELALU diperbarui, juga pada hasil yang dikalibrasi —
                    // di situlah selisih antara mesin dan forum bisa dibaca.
                    'Kode_Grade_Sistem' => $grade,
                    // Kurva yang memberi grade ini (siklus KURVA terkunci); NULL untuk PITA.
                    'PA_Cycle_Curve_Id' => $kurvaId,

                    /* Grade final mengikuti grade sistem sampai ada yang mengkalibrasi.
                       Mengosongkannya akan membuat layar hasil tampak belum jadi. Yang sudah
                       dikalibrasi mempertahankan keputusannya. */
                    'Kode_Grade_Final' => $kalibrasi['grade_final'],
                    'Flag_Dikalibrasi' => $kalibrasi['flag'],
                    'PA_Hav_Quadrant_Id' => $kalibrasi['kuadran_id'],
                    'Alasan_Tertahan' => $tertahan,
                    'Calculation_Version' => $versi,
                    'Calculated_At' => $this->jamProses,
                    'Calculated_By' => $olehUserId,
                    'Updated_At' => $this->jamProses,
                    'Updated_By' => $olehUserId,
                ]);

            $this->simpanJejakKategori(
                (int) $hasilLama->Id_PA_Result, $kompetensi, $versi, $olehUserId
            );
            $this->simpanNilaiKompetensi(
                (int) $hasilLama->Id_PA_Result, $kompetensi, $versi, $olehUserId
            );
            // Peserta tanpa satu pun penilai yang mengisi tidak mungkin punya catatan
            // kualitatif; kuerinya dilewati alih-alih dijalankan untuk hasil kosong.
            if ($kompetensi['rater_mengisi'] > 0) {
                $this->simpanCatatanDariPenilai(
                    (int) $hasilLama->Id_PA_Result, $participantId, $olehUserId
                );
            }

            // N_PA_Result baru saja ditimpa; potretnya menyimpan angka versi ini (DDL-2026-168).
            $this->riwayat->potret(
                (int) $hasilLama->Id_PA_Result,
                $sebab ?? ($versi === 1 ? PaResultHistoryService::HITUNG : PaResultHistoryService::HITUNG_ULANG),
                $olehUserId,
                null,
                $this->jamProses,
            );

            return [
                'result_id' => (int) $hasilLama->Id_PA_Result,
                'status' => $tertahan === null ? self::STATUS_DIHITUNG : self::STATUS_TERTAHAN,
                'versi' => $versi,
                'nilai_kpi' => $kpi['nilai'],
                'nilai_kompetensi' => $kompetensi['nilai'],
                'final_score' => $final,
                'kode_grade' => $grade,
                'kode_kuadran' => $kodeKuadran,
                'alasan_tertahan' => $tertahan,
            ];
        });
    }

    /**
     * Rincian pilar kompetensi TANPA menulis apa pun. Dipakai Balancing untuk membekukan
     * bobot saat sesi dibuat, dan untuk simulasi.
     *
     * @param  array<int, float>|null  $berlakuOverride  null = nilai berlaku dari sesi FINAL
     * @return array<string, mixed>
     */
    public function rincianKompetensi(int $participantId, ?array $berlakuOverride = null): array
    {
        return $this->tahapKompetensi($participantId, $berlakuOverride);
    }

    /**
     * Nilai Kompetensi, Final, kotak HAV, dan grade PITA seandainya After = `$berlakuOverride`.
     * Tidak menulis. KPI dibaca dari hasil tersimpan: Balancing tidak menyentuh pilar KPI.
     * Grade siklus KURVA TIDAK dihitung di sini — ia bergantung pada orang lain dan dihitung
     * `PaKurvaService::simulasikanGrade` atas seluruh grup.
     *
     * @param  array<int, float>|null  $berlakuOverride
     * @return array{nilai_kompetensi:?float, final:?float, kuadran_id:?int, kode_kuadran:?string, grade_pita:?string, alasan_tertahan:?string}
     */
    public function simulasiHasil(int $participantId, ?array $berlakuOverride = null): array
    {
        $cycleId = (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id');
        $kpi = DB::table('N_PA_Result')->where('PA_Participant_Id', $participantId)->where('Flag_Aktif', 'Y')->value('Nilai_KPI');
        $komp = $this->tahapKompetensi($participantId, $berlakuOverride, $cycleId);

        if ($komp['nilai'] === null || $kpi === null) {
            return [
                'nilai_kompetensi' => $komp['nilai'], 'final' => null, 'kuadran_id' => null, 'kode_kuadran' => null,
                'grade_pita' => null, 'alasan_tertahan' => $komp['alasan_tertahan'] ?? 'Nilai KPI peserta ini belum ada.',
            ];
        }

        $pilar = $this->resolver->pillarWeight($participantId);
        $final = PaBalancingKalkulator::nilaiAkhir((float) $kpi, (float) $komp['nilai'], (float) $pilar['bobot_kpi'], (float) $pilar['bobot_kompetensi']);
        [$kuadranId, $kodeKuadran] = $this->cariKuadran($cycleId, (float) $kpi, (float) $komp['nilai']);

        return [
            'nilai_kompetensi' => (float) $komp['nilai'],
            'final' => $final,
            'kuadran_id' => $kuadranId,
            'kode_kuadran' => $kodeKuadran,
            'grade_pita' => $this->konteks($cycleId)['metode_grade'] === 'KURVA'
                ? null
                : $this->cariPita($cycleId, 'FINAL', 'GRADE', $final)?->Kode_Range,
            'alasan_tertahan' => null,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Tahap 1 — nilai KPI                                                 *
     * ------------------------------------------------------------------ */

    /**
     * @return array{
     *     nilai:float|null, nilai_sebelum_cap:float|null,
     *     periode_dari:string|null, periode_sampai:string|null,
     *     belum_disetujui:int, alasan_tertahan:string|null
     * }
     */
    private function tahapKpi(object $peserta): array
    {
        $k = $this->konteks((int) $peserta->PA_Cycle_Id);

        $dari = $k['periode_dari'];
        $sampai = $k['periode_sampai'];

        $kode = strtoupper(trim((string) $peserta->Kode_Karyawan));
        $kandidat = $k['user_per_kode'][$kode] ?? [];

        $kosong = [
            'nilai' => null,
            'nilai_sebelum_cap' => null,
            'periode_dari' => $dari,
            'periode_sampai' => $sampai,
            'belum_disetujui' => 0,
        ];

        if ($kandidat === []) {
            return $kosong + [
                'alasan_tertahan' => 'Karyawan tidak punya akun KPI, sehingga nilai KPI tidak bisa '
                    .'diambil. Sistem tidak memberinya nol — itu keputusan admin.',
            ];
        }

        if (count($kandidat) > 1) {
            // Menebak salah satunya berarti berpeluang membebankan KPI milik orang lain,
            // dan hasilnya akan tampak wajar. Ditahan, dan sebabnya disebutkan.
            return $kosong + [
                'alasan_tertahan' => sprintf(
                    'Kode karyawan %s menunjuk %d akun KPI (%s). Nilai KPI tidak diambil karena '
                    .'memilih salah satunya berarti berpeluang memakai KPI milik orang lain.',
                    $kode, count($kandidat), implode(', ', $kandidat)
                ),
            ];
        }

        $userId = $kandidat[0];

        $baris = $k['parameter_per_user'][(string) $userId] ?? collect();
        $belumDisetujui = (int) ($k['belum_disetujui_per_user'][(string) $userId] ?? 0);

        if ($baris->isEmpty()) {
            return $kosong + [
                'belum_disetujui' => $belumDisetujui,
                'alasan_tertahan' => 'Tidak ada KPI disetujui pada rentang periode siklus. '
                    .'Sistem menandai, admin yang memutuskan — bukan otomatis nol.',
            ];
        }

        $paramDefs = $baris->map(fn ($r) => [
            'id' => (string) $r->id,
            'bobot' => (float) $r->bobot,
            'formula' => $r->formula,
        ])->all();

        $sumTarget = $baris->mapWithKeys(fn ($r) => [(string) $r->id => (float) $r->tgt])->all();
        $sumActual = $baris->mapWithKeys(fn ($r) => [(string) $r->id => (float) $r->act])->all();

        $calc = $this->scoring->accumulatedScore(
            $paramDefs, $sumTarget, $sumActual, [], ['cap' => true, 'round' => 2]
        );

        /* Bobot yang DIPERIKSA berasal dari himpunan parameter yang disetujui, bukan dari
           `$calc` — yang hanya memuat parameter yang kebetulan sudah punya realisasi
           disetujui. Keduanya sengaja dipisah: `$calc` menghitung NILAI (dan memang benar
           memakai yang berealisasi), sedangkan yang diperiksa di bawah adalah KETETAPAN
           MASTER. Menyatukan keduanya membuat penjaga menahan orang yang masternya sehat
           dan meloloskan yang masternya rusak — dua-duanya terukur di dev. */
        $totalBobot = (float) ($k['bobot_parameter_per_user'][(string) $userId]
            ?? $calc['total_bobot']);

        if ((float) $calc['total_bobot'] <= 0) {
            return $kosong + [
                'belum_disetujui' => $belumDisetujui,
                'alasan_tertahan' => 'Seluruh parameter KPI berbobot nol pada rentang siklus.',
            ];
        }

        // Ketetapan bisnis: Total_Bobot KPI selalu 100. accumulatedScore() memang sudah
        // membagi dengan Sigma bobot sehingga angkanya tetap sebanding — tetapi bobot
        // yang bukan 100 berarti datanya menyimpang dari ketetapan, dan memakainya
        // diam-diam menyembunyikan penyimpangan itu.
        if (abs($totalBobot - self::TOTAL_BOBOT_KPI_SAH) > PaBobotGuard::TOLERANSI) {
            return $kosong + [
                'belum_disetujui' => $belumDisetujui,
                'alasan_tertahan' => sprintf(
                    'Total bobot parameter KPI yang disetujui pada tahun siklus berjumlah %s, '
                    .'bukan 100. Nilainya tetap bisa dihitung, tetapi rencana KPI-nya '
                    .'menyimpang dari ketetapan dan harus diperiksa dulu di master KPI.',
                    rtrim(rtrim(number_format($totalBobot, 2, '.', ''), '0'), '.')
                ),
            ];
        }

        return [
            'nilai' => (float) $calc['final_pct'],
            'nilai_sebelum_cap' => (float) $calc['raw_pct'],
            'periode_dari' => $dari,
            'periode_sampai' => $sampai,
            'belum_disetujui' => $belumDisetujui,
            'alasan_tertahan' => null,
        ];
    }

    /**
     * Bahan per siklus, diambil SEKALI untuk seluruh peserta.
     *
     * Semua yang ada di sini jawabannya sama bagi setiap peserta siklus. Mengambilnya
     * per peserta berarti mengulang pertanyaan yang sama ratusan kali ke database yang
     * jaraknya puluhan milidetik.
     *
     * @return array<string, mixed>
     */
    private function konteks(int $cycleId): array
    {
        if (isset($this->konteks[$cycleId])) {
            return $this->konteks[$cycleId];
        }

        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai', 'Tahun', 'Metode_Grade']);

        $dari = $siklus?->Periode_Dinilai_Dari;
        $sampai = $siklus?->Periode_Dinilai_Sampai;

        if ($dari === null || $sampai === null) {
            throw new RuntimeException(
                "Siklus {$cycleId} belum punya rentang periode dinilai; nilai KPI YTD tidak "
                .'punya batas untuk diakumulasi.'
            );
        }

        $dari = substr((string) $dari, 0, 10);
        $sampai = substr((string) $sampai, 0, 10);

        if (substr($dari, 0, 4) !== substr($sampai, 0, 4)) {
            throw new RuntimeException(
                "Rentang siklus {$dari}..{$sampai} melintasi tahun. Penjadwalan KPI disimpan per "
                .'tahun, sehingga akumulasi lintas tahun akan diam-diam memakai satu tahun saja.'
            );
        }

        $tahun = (int) substr($dari, 0, 4);
        $periode = $this->daftarPeriode($dari, $sampai);

        /* Kode karyawan dinormalkan huruf besar dan dipangkas: mencocokkan lewat kolom
           teks yang tidak seragam adalah cara paling sering kehilangan orang tanpa
           menyadarinya.

           TIDAK disaring Flag_Active. Diukur ulang 1 Sep 2026 pada development, atas
           himpunan yang PERSIS dipakai di bawah (realisasi KPI yang disetujui, bukan
           pengajuannya): 7 pemilik KPI, dan KETUJUHNYA ber-Flag_Active NULL. Menyalakan
           saringan itu membuang 7 dari 7 - seluruhnya.

           Catatan bagi yang hendak mengubahnya: mengukur lewat KPI_Pengajuan
           menghasilkan angka yang jauh berbeda dan menyesatkan. Yang dipakai PA adalah
           REALISASI (KPI_Update_Performance), bukan rencananya. Yang
           dibutuhkan di sini hanya penerjemahan kode karyawan menjadi Id_User; keabsahan
           barisnya sudah dijaga penyaring di kueri KPI-nya sendiri.

           Satu kode BISA menunjuk lebih dari satu akun - terukur: D12 dua baris, USERS
           empat baris. Karena itu petanya menyimpan DAFTAR, bukan satu nilai. Memilih
           salah satunya diam-diam berarti berpeluang membebankan KPI milik orang lain. */
        $userPerKode = [];

        foreach (DB::table('KPI_Users')->whereNotNull('Kode_Users')->orderBy('Id_Users')
            ->get(['Id_Users', 'Kode_Users']) as $r) {
            $kode = strtoupper(trim((string) $r->Kode_Users));

            if ($kode === '') {
                continue;
            }

            $userPerKode[$kode][] = (string) $r->Id_Users;
        }

        $userIds = array_values(array_unique(array_merge(...array_values($userPerKode) ?: [[]])));

        $this->konteks[$cycleId] = [
            'periode_dari' => $dari,
            'periode_sampai' => $sampai,
            'tahun' => $tahun,
            'periode' => $periode,
            'user_per_kode' => $userPerKode,
            'parameter_per_user' => $this->parameterKpi($userIds, $tahun, $periode),
            'bobot_parameter_per_user' => $this->bobotParameterKpi($userIds, $tahun),
            'belum_disetujui_per_user' => $this->cacahBelumDisetujui($userIds, $periode, $tahun),
            'pita' => $this->pitaSiklus($cycleId),
            // char(5): dipangkas, 'PITA' tersimpan sebagai 'PITA '.
            'metode_grade' => trim((string) ($siklus?->Metode_Grade ?? 'PITA')),
            'kuadran' => DB::table('N_PA_Hav_Quadrant')->where('Flag_Aktif', 'Y')
                ->get(['Id_PA_Hav_Quadrant', 'Kode_Kuadran', 'Tingkat_Performa', 'Tingkat_Kompetensi']),
        ];

        return $this->konteks[$cycleId];
    }

    /**
     * Melupakan bahan per siklus. Perlu dipanggil bila siklusnya berubah di tengah
     * proses yang sama - misalnya oleh perintah pembuat data contoh.
     */
    /**
     | Berapa penugasan peserta ini yang MASIH menunggu.
     |
     | `DIBATALKAN` tidak dihitung — penugasan yang dicabut bukan pekerjaan siapa pun lagi.
     | Aturan yang sama sudah dipakai `PaPesertaService::kueriPenugasan()`; ditulis ulang di
     | sini hanya bentuk SQL-nya, bukan aturannya.
     */
    public function sisaPenugasan(int $participantId): int
    {
        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->whereNotIn('Status_Penugasan', ['SELESAI', 'DIBATALKAN'])
            ->count();
    }

    /**
     * Menolak menghitung peserta yang penilaiannya belum lengkap, selagi jendela terbuka.
     */
    private function pastikanPesertaLengkap(int $participantId): void
    {
        $sisa = $this->sisaPenugasan($participantId);

        if ($sisa === 0) {
            return;
        }

        /* SEBABNYA DIBEDAKAN: "belum mengisi" dan "sudah mengisi tetapi belum dikirim"
           menuntut tindakan yang sama sekali berbeda dari admin. Yang pertama menagih
           penilainya mengerjakan; yang kedua menagihnya menekan kirim - atau melepasnya
           paksa. Satu kalimat untuk keduanya mengirim admin menagih pekerjaan yang sudah
           selesai. */
        $terkumpul = (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', PaAssignmentBuilderService::STATUS_TERKUMPUL)
            ->count();

        if ($terkumpul > 0) {
            throw new RuntimeException(
                "Peserta {$participantId} masih menunggu {$sisa} penilai, dan {$terkumpul} di "
                .'antaranya SUDAH mengisi tetapi belum mengirim — penilai mengirim seluruh '
                .'tugasnya sekaligus, dan sebagian tugasnya belum selesai. Tunggu pengirimannya, '
                .'atau lepas paksa penilai itu dari halaman pemantauan.'
            );
        }

        throw new RuntimeException(
            "Peserta {$participantId} masih punya {$sisa} penilai yang belum mengisi. "
            .'Selagi jendela pengisian terbuka, hasil hanya dihitung untuk peserta yang '
            .'penilaiannya sudah lengkap — menghitung sekarang menghasilkan angka yang akan '
            .'berubah begitu sisanya masuk.'
        );
    }

    /**
     | MENGHITUNG ULANG PESERTA YANG TERTAHAN.
     |
     | `TERTAHAN` hampir selalu bersebab di LUAR modul ini — KPI belum disetujui, karyawan
     | belum punya akun KPI, bobot parameter KPI menyimpang. Ketiganya diperbaiki orang lain di
     | modul lain, dan PA tidak akan pernah tahu kapan itu terjadi: tidak ada satu baris pun di
     | modul KPI yang mengenal PA.
     |
     | Menempelkan pemicu di jalur persetujuan KPI akan membalik ketergantungan itu — modul KPI
     | harus mengenal PA, pada jalur yang ramai dan dipakai untuk banyak hal di luar penilaian.
     | Bertanya ulang secara berkala jauh lebih murah daripada kopling itu.
     |
     | AMAN DIULANG. Perhitungan ulang atas peserta yang sebabnya belum hilang menghasilkan
     | `TERTAHAN` lagi — tidak ada efek samping, hanya nomor versi yang naik. Yang sudah TERBIT
     | tidak ikut: `hitung()` menolaknya, dan penyapu ini pun tidak memilihnya.
     |
     | BERCICIL DENGAN PENANDA POSISI (koreksi 7 Okt 2026). `$mulaiDariId` = `terakhir_id`
     | cicilan sebelumnya. Tanpa penanda, cicilan berikutnya mengambil orang yang SAMA lagi,
     | dan yang tetap tertahan menghalangi sisanya selamanya. `$jenis` TERTAHAN, atau SIAP =
     | BELUM_DIHITUNG yang penilainya sudah tuntas semua (keranjang "Siap Dihitung").
     |
     | @return array{diperiksa:int, jadi_dihitung:int, tetap_tertahan:int, gagal:int,
     |               contoh_galat:list<string>, jenis:string, terakhir_id:int|null, selesai:bool}
     */
    public function sapuTertahan(int $cycleId, ?int $olehUserId = null, int $batas = 0, ?int $mulaiDariId = null, string $jenis = self::STATUS_TERTAHAN): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::HITUNG_HASIL, $cycleId);

        $q = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')
            ->orderBy('p.Id_PA_Participant');

        if ($jenis === 'SIAP') {
            $q->where('r.Status_Hasil', self::STATUS_BELUM)
                ->whereNotExists(fn ($w) => $w->from('N_PA_Assignment as a')
                    ->whereColumn('a.PA_Participant_Id', 'p.Id_PA_Participant')
                    ->where('a.Flag_Aktif', 'Y')
                    ->whereNotIn('a.Status_Penugasan', ['SELESAI', 'DIBATALKAN']));
        } else {
            $q->where('r.Status_Hasil', self::STATUS_TERTAHAN);
        }

        if ($mulaiDariId !== null) {
            $q->where('p.Id_PA_Participant', '>', $mulaiDariId);
        }

        if ($batas > 0) {
            $q->limit($batas);
        }

        $ids = $q->pluck('p.Id_PA_Participant')->all();

        $jadi = 0;
        $tetap = 0;
        $gagal = 0;
        $contoh = [];

        foreach ($ids as $pid) {
            try {
                $h = $this->hitung((int) $pid, $olehUserId);

                if (($h['status'] ?? null) === self::STATUS_TERTAHAN) {
                    $tetap++;
                } else {
                    $jadi++;
                }
            } catch (RuntimeException $e) {
                /* Aturan yang menolak — peserta yang penilainya ternyata belum lengkap lagi,
                   misalnya. Bukan kerusakan; dihitung sebagai tetap tertahan. */
                $tetap++;
            } catch (\Throwable $e) {
                $gagal++;

                if (count($contoh) < 3) {
                    $contoh[] = "peserta {$pid}: ".$e->getMessage();
                }
            }
        }

        return [
            'diperiksa' => count($ids),
            'jadi_dihitung' => $jadi,
            'tetap_tertahan' => $tetap,
            'gagal' => $gagal,
            'contoh_galat' => $contoh,
            'jenis' => $jenis === 'SIAP' ? 'SIAP' : self::STATUS_TERTAHAN,
            // Penanda untuk cicilan berikutnya; `selesai` bila tak ada lagi yang tersisa.
            'terakhir_id' => $ids === [] ? $mulaiDariId : (int) end($ids),
            'selesai' => $batas <= 0 || count($ids) < $batas,
        ];
    }

    public function lupakanKonteks(): void
    {
        $this->konteks = [];
        $this->petaJenis = null;
        $this->balancing->lupakan();
    }

    /**
     * Parameter KPI beserta jumlah target dan realisasinya, untuk SEMUA user sekaligus.
     *
     * Bentuk kuerinya sengaja mengikuti HomepageKpiService::accumulatedScoresForUsers()
     * persis, termasuk pengelompokannya per user. Kalau PA memakai himpunan baris yang
     * berbeda, angkanya akan berbeda dari yang dilihat karyawan di layar KPI - dan tidak
     * ada yang bisa menjelaskan kenapa.
     *
     * @param  list<string>  $userIds
     * @param  list<string>  $periode
     * @return array<string, \Illuminate\Support\Collection>
     */
    /**
     * Total bobot parameter KPI yang DISETUJUI per user — tanpa memandang realisasi.
     *
     * Inilah himpunan yang benar untuk memeriksa ketetapan "total bobot KPI selalu 100".
     * `parameterKpi()` di bawah tidak bisa dipakai untuk itu: ia meng-INNER JOIN
     * `KPI_Update_Performance`, sehingga parameter yang belum punya realisasi disetujui
     * HILANG dari himpunannya. Bobot lalu dijumlahkan dari sisanya, dan penjaganya
     * mengukur sesuatu yang sama sekali lain dari yang hendak dijaga.
     *
     * Terukur di basis data pengembangan pada 2 September 2026 — dan salahnya ke DUA arah:
     *
     * | Karyawan | Σ bobot parameter disetujui | berealisasi | yang dilihat PA | akibat |
     * |---|---|---|---|---|
     * | B8  | 100 (sehat)      | 1 dari 2 | 50  | TERTAHAN keliru |
     * | Y62 | 100 (sehat)      | 1 dari 3 | 50  | TERTAHAN keliru |
     * | B6  | 200 (menyimpang) | 2 dari 3 | 100 | LOLOS, padahal justru inilah yang dijaga |
     *
     * Pesannya pun menuduh pihak yang salah: "datanya menyimpang dari ketetapan" menunjuk
     * master KPI, padahal master B8 benar dan yang kurang adalah realisasinya.
     *
     * Sengaja TIDAK menyaring periode. Ketetapan bobot 100 berlaku pada rencana KPI
     * setahun, bukan pada potongan bulan yang kebetulan dinilai siklus ini; menyaringnya
     * per periode akan mengulang persis kekeliruan yang sedang diperbaiki, dalam bentuk
     * lain.
     *
     * @param  list<int|string>  $userIds
     * @return array<string, float>
     */
    private function bobotParameterKpi(array $userIds, int $tahun): array
    {
        if ($userIds === []) {
            return [];
        }

        $hasil = [];

        // Dipotong 500: satu pernyataan SQL Server dibatasi jumlah parameternya.
        foreach (array_chunk($userIds, 500) as $potongan) {
            $rows = DB::table('KPI_Pengajuan_Detail as pd')
                ->join('KPI_Pengajuan as p', function ($j) {
                    $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                        ->where('p.Flag_Aktif', 'Y')
                        ->where('p.Flag_Approval', 'Y')
                        ->whereNull('p.Status');
                })
                ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($tahun) {
                    $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                        ->where('mp.Tahun', (string) $tahun);
                })
                ->whereIn('p.Id_User', $potongan)
                ->groupBy('p.Id_User')
                ->selectRaw('p.Id_User as uid, SUM(pd.Bobot) as total')
                ->get();

            foreach ($rows as $r) {
                $hasil[(string) $r->uid] = (float) $r->total;
            }
        }

        return $hasil;
    }

    private function parameterKpi(array $userIds, int $tahun, array $periode): array
    {
        if ($userIds === [] || $periode === []) {
            return [];
        }

        $hasil = [];

        // Dipotong karena satu pernyataan SQL Server dibatasi jumlah parameternya, dan
        // daftar user bisa mencapai ribuan.
        foreach (array_chunk($userIds, 500) as $potongan) {
            $rows = DB::table('KPI_Pengajuan_Detail as pd')
                ->join('KPI_Pengajuan as p', function ($j) {
                    $j->on('p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
                        ->where('p.Flag_Aktif', 'Y')
                        ->where('p.Flag_Approval', 'Y')
                        ->whereNull('p.Status');
                })
                ->join('KPI_Master_Penjadwalan as mp', function ($j) use ($tahun) {
                    $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                        ->where('mp.Tahun', (string) $tahun);
                })
                ->leftJoin('KPI_Pengajuan_Det as pdet', function ($j) use ($periode) {
                    $j->on('pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
                        ->whereIn('pdet.Periode', $periode);
                })
                ->leftJoin('KPI_Update_Performance_Detail as upd', 'upd.Id_Pengajuan_Det', '=', 'pdet.Id_Pengajuan_Det')
                ->join('KPI_Update_Performance as up', function ($j) {
                    $j->on('up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
                        ->where('up.Flag_Aktif', 'Y')
                        ->where('up.Flag_Approval', 'Y')
                        ->where('up.Flag_Verifikasi', 'Y')
                        ->whereNull('up.Status');
                })
                ->leftJoin('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'pd.Id_Master_Formula')
                ->whereIn('p.Id_User', $potongan)
                ->groupBy('p.Id_User', 'pd.Id_Pengajuan_Detail', 'pd.Bobot', 'pd.Id_Master_Formula')
                ->select(
                    'p.Id_User',
                    'pd.Id_Pengajuan_Detail as id',
                    'pd.Bobot as bobot',
                    DB::raw('COALESCE(SUM(pdet.Nilai_Target), 0) as tgt'),
                    DB::raw('COALESCE(SUM(upd.Nilai_Actual), 0) as act'),
                    DB::raw('MAX(CAST(mf.Rumus AS VARCHAR(MAX))) as formula'),
                )
                ->get();

            foreach ($rows->groupBy('Id_User') as $userId => $baris) {
                $hasil[(string) $userId] = $baris;
            }
        }

        return $hasil;
    }

    /**
     * Berapa pengajuan KPI yang masih menggantung di rentang itu, per user.
     *
     * Bukan penghalang, melainkan jejak. Hasil yang dihitung saat masih ada pengajuan
     * menunggu bisa berubah bila pengajuan itu kelak disetujui, dan angka inilah yang
     * memberitahu bahwa itu mungkin terjadi.
     *
     * @param  list<string>  $userIds
     * @param  list<string>  $periode
     * @return array<string, int>
     */
    private function cacahBelumDisetujui(array $userIds, array $periode, $tahun = null): array
    {
        if ($userIds === [] || $periode === []) {
            return [];
        }

        $hasil = [];

        foreach (array_chunk($userIds, 500) as $potongan) {
            $rows = DB::table('KPI_Update_Performance as up')
                /* Pengajuan yang SAMA dengan sumber nilai KPI (`parameterKpi`): aktif, disetujui,
                   tahun siklus. Pembaruan milik pengajuan lama yang sudah nonaktif tidak lagi
                   menggantung apa pun (UAT 13 Okt 2026, Erlangga F19). */
                ->join('KPI_Pengajuan as p', function ($j) {
                    $j->on('p.Id_Pengajuan', '=', 'up.Id_Pengajuan')
                        ->where('p.Flag_Aktif', 'Y')
                        ->where('p.Flag_Approval', 'Y')
                        ->whereNull('p.Status');
                })
                ->when($tahun !== null, fn ($q) => $q->join('KPI_Master_Penjadwalan as mp', function ($j) use ($tahun) {
                    $j->on('mp.Id_Master_Penjadwalan', '=', 'p.Id_Penjadwalan')
                        ->where('mp.Tahun', (string) $tahun);
                }))
                ->whereIn('p.Id_User', $potongan)
                ->where('up.Flag_Aktif', 'Y')
                ->whereIn('up.Periode', $periode)
                ->where(function ($q) {
                    $q->where('up.Flag_Approval', '<>', 'Y')
                        ->orWhere('up.Flag_Verifikasi', '<>', 'Y')
                        ->orWhereNotNull('up.Status');
                })
                /* Pembaruan yang sudah DIGANTIKAN versi yang disetujui pada pengajuan & periode
                   yang sama tidak lagi menggantung. Tanpa ini baris lama yang ditolak (masih
                   Flag_Aktif) terhitung "belum disetujui" padahal periodenya lengkap — hasilnya
                   diberi peringatan palsu dan tertahan dari Terbitkan massal, sementara keranjang
                   "KPI belum lengkap" benar menganggapnya lengkap (UAT 13 Okt 2026, Andre B6). */
                ->whereNotExists(fn ($w) => $w->from('KPI_Update_Performance as ok')
                    ->whereColumn('ok.Id_Pengajuan', 'up.Id_Pengajuan')
                    ->whereColumn('ok.Periode', 'up.Periode')
                    ->where('ok.Flag_Aktif', 'Y')
                    ->where('ok.Flag_Approval', 'Y')
                    ->where('ok.Flag_Verifikasi', 'Y')
                    ->whereNull('ok.Status'))
                ->groupBy('p.Id_User')
                ->select(['p.Id_User', DB::raw('COUNT(*) AS jumlah')])
                ->get();

            foreach ($rows as $r) {
                $hasil[(string) $r->Id_User] = (int) $r->jumlah;
            }
        }

        return $hasil;
    }

    /**
     * Seluruh pita SALINAN SIKLUS, dikelompokkan menurut (sumbu, kegunaan).
     *
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function pitaSiklus(int $cycleId): array
    {
        return DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get(['Score_Type', 'Score_Usage', 'Kode_Range', 'Nama_Range', 'Nilai_Dari', 'Nilai_Sampai'])
            ->groupBy(fn ($r) => $r->Score_Type.'|'.$r->Score_Usage)
            ->all();
    }

    /**
     * @return list<string> daftar YYYY-MM dari kedua batas, keduanya ikut
     */
    private function daftarPeriode(string $dari, string $sampai): array
    {
        $mulai = substr($dari, 0, 7);
        $akhir = substr($sampai, 0, 7);

        $hasil = [];
        $kursor = new DateTimeImmutable($mulai.'-01');
        $batas = new DateTimeImmutable($akhir.'-01');

        // Batas 24 langkah cukup untuk dua tahun penuh dan mencegah lingkaran tak
        // berujung bila kedua batasnya kelak tertukar.
        for ($i = 0; $i < 24 && $kursor <= $batas; $i++) {
            $hasil[] = $kursor->format('Y-m');
            $kursor = $kursor->modify('+1 month');
        }

        return $hasil;
    }

    /* ------------------------------------------------------------------ *
     * Tahap 2 — nilai kompetensi                                          *
     * ------------------------------------------------------------------ */

    /**
     * @return array{
     *     nilai:float|null, rater_diharapkan:int, rater_mengisi:int,
     *     kategori:array<string, array{kategori_id:int, nilai:float|null, diharapkan:int,
     *                                  mengisi:int, bobot_terpakai:float|null, dipakai:bool}>,
     *     kompetensi:list<array{competency_id:int, kategori_id:int|null, nilai:float, penilai:int}>,
     *     alasan_tertahan:string|null
     * }
     */
    private function tahapKompetensi(int $participantId, ?array $berlakuOverride = null, ?int $cycleId = null): array
    {
        $aturan = $this->resolver->raterRules($participantId);
        $penugasan = $this->penugasanDenganNilai($participantId);

        $kategoriInfo = [];
        $nilaiKategori = [];
        $bobotAturan = [];
        $diharapkanTotal = 0;
        $mengisiTotal = 0;

        foreach ($aturan as $kode => $a) {
            $anggota = $penugasan[$a['kategori_id']] ?? ['diharapkan' => 0, 'penilai' => []];
            $mengisi = count($anggota['penilai']);
            $minSelesai = $a['min_rater_selesai'] ?? $a['min_rater'];

            $diharapkanTotal += $anggota['diharapkan'];
            $mengisiTotal += $mengisi;
            $bobotAturan[$kode] = $a['bobot_persen'];

            $dipakai = $mengisi >= $minSelesai && $mengisi > 0;
            $nilai = $dipakai ? $this->nilaiSatuKategori($anggota['penilai']) : null;

            if ($dipakai && $nilai !== null) {
                $nilaiKategori[$kode] = $nilai;
            }

            $kategoriInfo[$kode] = [
                'kategori_id' => $a['kategori_id'],
                'nilai' => $nilai,
                'diharapkan' => $anggota['diharapkan'],
                'mengisi' => $mengisi,
                'bobot_terpakai' => null,
                'dipakai' => $dipakai && $nilai !== null,
                'penilai' => $dipakai ? $anggota['penilai'] : [],
            ];
        }

        if ($nilaiKategori === []) {
            return [
                'nilai' => null,
                'rater_diharapkan' => $diharapkanTotal,
                'rater_mengisi' => $mengisiTotal,
                'kategori' => $this->tanpaPenilai($kategoriInfo),
                'kompetensi' => [],
                'alasan_tertahan' => 'Tak satu pun kategori penilai memenuhi ambang jumlah pengisi. '
                    .'Menghitung dari sisa yang ada akan menghasilkan angka yang tampak wajar '
                    .'tetapi tidak mewakili siapa pun.',
            ];
        }

        $bobotTerpakai = $this->bobot->redistribusi($bobotAturan, array_keys($nilaiKategori));

        $nilai = 0.0;
        foreach ($bobotTerpakai as $kode => $b) {
            $nilai += $nilaiKategori[$kode] * $b / 100;
            $kategoriInfo[$kode]['bobot_terpakai'] = $b;
        }

        $kompetensi = $this->nilaiPerKompetensi($kategoriInfo, $bobotTerpakai);
        $tidakTercakup = $this->jenisWajibYangTidakTercakup($participantId, $kompetensi);

        if ($tidakTercakup !== []) {
            /* Jenis kompetensi WAJIB yang tak satu butir pun dinilai.
               Sebabnya hampir selalu paket soal yang tidak menutup seluruh jenis wajib
               bagi level peserta ini — dan tak ada yang memeriksanya saat paket dipilih.

               Ditahan, bukan dihitung dari yang ada. Menghitungnya menghasilkan angka
               yang tampak utuh padahal lahir dari sebagian: seorang manajer bisa dinilai
               tanpa satu pun butir Manajerial, mendapat grade, dan tidak ada apa pun di
               layar yang mengatakan sepertiga penilaiannya tidak pernah terjadi.

               Ini pola yang sama dengan pilar KPI di atas: sistem menandai, admin yang
               memutuskan — bukan otomatis nol. */
            return [
                'nilai' => null,
                'rater_diharapkan' => $diharapkanTotal,
                'rater_mengisi' => $mengisiTotal,
                'kategori' => $this->tanpaPenilai($kategoriInfo),
                /* Radar yang SUDAH terhitung tetap dikembalikan, tidak dikosongkan.
                   Berbeda dari penahanan di atas — yang mengembalikan senarai kosong
                   karena memang tak ada nilai sama sekali — di sini kompetensinya sudah
                   dinilai; yang kurang adalah CAKUPANNYA. Membuangnya justru menghapus
                   bukti yang dibutuhkan admin untuk melihat apa yang sudah dinilai dan
                   menyimpulkan apa yang belum. Penahanannya tetap tegas lewat
                   `nilai => null` berikut sebabnya, jadi tak ada angka akhir yang lahir
                   dari sebagian. */
                'kompetensi' => $kompetensi,
                /* Sengaja dipadatkan. Kalimat ini bisa muncul BERSAMA sebab pilar KPI,
                   dan kolomnya `nvarchar(500)`; versi panjangnya menghasilkan gabungan
                   546 karakter, yang lalu dipotong tepat pada kalimat tindakannya. */
                'alasan_tertahan' => sprintf(
                    'Jenis kompetensi wajib tidak tercakup penilaian: %s. Periksa dua hal: '
                    .'paket soal siklus ini tidak memuat satu butir pun untuk jenis itu, '
                    .'atau butirnya ada tetapi tak seorang penilai pun menjawabnya. '
                    .'Lengkapi paketnya lalu hitung ulang, atau cabut kewajiban jenis itu '
                    .'bagi peserta ini.',
                    implode(', ', $tidakTercakup),
                ),
            ];
        }

        /* LAPISAN BALANCING (spec §6). Nilai mesin tidak pernah ditimpa; yang berlaku adalah
           mesin + selisih berbobot dari After sesi FINAL terbaru. Tanpa sesi, hasilnya sama
           persis dengan sebelum fitur ini ada. */
        $bobotKompetensi = PaBalancingKalkulator::bobotKompetensi(array_values(array_map(
            fn (string $kode) => ['bobot_terpakai' => (float) $bobotTerpakai[$kode], 'penilai' => $kategoriInfo[$kode]['penilai']],
            array_keys($bobotTerpakai),
        )));

        $kompetensiMesin = [];

        foreach ($kompetensi as $k) {
            if ($k['kategori_id'] === null) {
                $kompetensiMesin[$k['competency_id']] = (float) $k['nilai'];
            }
        }

        $berlaku = $berlakuOverride ?? $this->balancing->kompetensiBerlaku($participantId, $cycleId);

        foreach ($kompetensi as $i => $k) {
            // Radar menampilkan nilai yang berlaku; baris per kategori tetap apa kata penilai.
            if ($k['kategori_id'] === null && isset($berlaku[$k['competency_id']])) {
                $kompetensi[$i]['nilai'] = round((float) $berlaku[$k['competency_id']], 2);
            }
        }

        return [
            'nilai' => PaBalancingKalkulator::nilaiKompetensi($nilai, $bobotKompetensi, $kompetensiMesin, $berlaku),
            'nilai_mesin' => round($nilai, 2),
            'bobot_kompetensi' => $bobotKompetensi,
            'kompetensi_mesin' => $kompetensiMesin,
            'rater_diharapkan' => $diharapkanTotal,
            'rater_mengisi' => $mengisiTotal,
            'kategori' => $this->tanpaPenilai($kategoriInfo),
            'kompetensi' => $kompetensi,
            'alasan_tertahan' => null,
        ];
    }

    /**
     * Menggabungkan sebab tertahan dari kedua pilar menjadi satu keterangan.
     *
     * Sebelumnya keduanya dipilih dengan `??`, sehingga sebab KPI SELALU menutupi sebab
     * kompetensi. Keduanya sudah terlanjur dihitung, jadi yang hilang bukan biaya
     * melainkan informasi: admin memperbaiki akun KPI, menjalankan ulang perhitungan
     * satu siklus penuh — pada 858 peserta itu belasan menit — lalu baru mengetahui
     * masalah kedua yang sejak awal sudah diketahui mesin.
     *
     * Kolom `Alasan_Tertahan` bertipe `nvarchar(500)` dan SQL Server MENOLAK, bukan
     * memotong, nilai yang melampauinya. Karena itu hasil gabungan dipendekkan di sini;
     * tanpa pagar ini perbaikan yang menambah informasi justru bisa menggagalkan
     * penyimpanan hasil.
     */
    private function gabungAlasanTertahan(?string $kpi, ?string $kompetensi): ?string
    {
        $bagian = array_values(array_filter([$kpi, $kompetensi]));

        if ($bagian === []) {
            return null;
        }

        $gabung = implode(' ', $bagian);

        if (mb_strlen($gabung) <= self::BATAS_ALASAN) {
            return $gabung;
        }

        /* Yang dipendekkan adalah bagian TERPANJANG, bukan ekor gabungannya.
           Memotong ekor selalu mengorbankan sebab KEDUA — persis sebab yang baru saja
           diperjuangkan supaya terlihat — dan ia hilang seluruhnya bersama kalimat
           tindakannya, sementara sebab pertama tetap utuh. Terukur: gabungan 546 karakter
           terpotong tepat pada "…atau cabut kewajiban jenis…".

           Dengan memendekkan yang terpanjang, kedua sebab tetap terbaca; yang berkurang
           hanya keterangan pada sebab yang memang paling banyak bicara. */
        $terpanjang = 0;

        foreach ($bagian as $i => $teks) {
            if (mb_strlen($teks) > mb_strlen($bagian[$terpanjang])) {
                $terpanjang = $i;
            }
        }

        $kelebihan = mb_strlen($gabung) - self::BATAS_ALASAN + 1;
        $sisa = mb_strlen($bagian[$terpanjang]) - $kelebihan;

        // Kalau memendekkan yang terpanjang pun tak cukup — hanya mungkin bila kedua
        // bagian sama-sama sangat panjang — barulah ekornya yang dipotong.
        if ($sisa < 40) {
            return mb_substr($gabung, 0, self::BATAS_ALASAN - 1).'…';
        }

        $bagian[$terpanjang] = mb_substr($bagian[$terpanjang], 0, $sisa).'…';

        return implode(' ', $bagian);
    }

    /**
     * Jenis kompetensi WAJIB yang tak satu butir pun dinilai.
     *
     * Sampai 1 September 2026 aturan ini hanya hidup sebagai prosa. `N_PA_Competency_Requirement`
     * berisi 33 baris, salinannya dibekukan per siklus, dan `competencyRequirements()` sudah
     * ada — tetapi tak satu pun kode produksi memanggilnya. Seluruh anggota badan itu
     * dibangun untuk keputusan yang tidak pernah diambil.
     *
     * @param  list<array<string, mixed>>  $kompetensi  baris hasil per kompetensi
     * @return list<string> nama jenis yang tidak tercakup, kosong bila semuanya tertutup
     */
    private function jenisWajibYangTidakTercakup(int $participantId, array $kompetensi): array
    {
        $wajib = array_filter(
            $this->resolver->competencyRequirements($participantId),
            fn ($r) => ($r['flag_wajib'] ?? 'T') === 'Y',
        );

        if ($wajib === []) {
            return [];
        }

        $peta = $this->petaJenisKompetensi();

        $tercakup = [];

        foreach ($kompetensi as $baris) {
            // Hanya baris AGREGAT lintas kategori; rincian per kategori akan menghitung
            // jenis yang sama berkali-kali tanpa menambah informasi.
            if (($baris['kategori_id'] ?? null) !== null) {
                continue;
            }

            $typeId = $peta[(int) $baris['competency_id']] ?? null;

            if ($typeId !== null) {
                $tercakup[$typeId] = true;
            }
        }

        /*
         | JENIS YANG TIDAK BERANGKA IKUT DIHITUNG TERCAKUP BILA TERJAWAB.
         |
         | `$kompetensi` di atas hanya memuat kompetensi yang menghasilkan NILAI. Jenis
         | `KUALITATIF` menurut definisinya sendiri "butir bertekstual tanpa angka; tidak
         | ikut perhitungan" — ia tidak akan pernah muncul di sana. Akibatnya, sebelum
         | 6 September 2026, setiap level yang mewajibkan jenis itu TERTAHAN SELAMANYA,
         | sebanyak apa pun esainya dijawab, dan pesannya menyuruh admin "melengkapi paket"
         | yang sebenarnya sudah lengkap.
         |
         | Kriterianya karena itu bukan "berapa nilainya" melainkan "apakah dijawab" —
         | persis yang dikatakan kalimat penahanannya sendiri.
         */
        foreach ($this->jenisTerjawabTanpaAngka($participantId) as $typeId) {
            $tercakup[$typeId] = true;
        }

        $kurang = [];

        foreach ($wajib as $r) {
            if (! isset($tercakup[(int) $r['type_id']])) {
                $kurang[] = $r['nama_type'] ?: $r['kode_type'];
            }
        }

        return $kurang;
    }

    /**
     * Jenis kompetensi yang butirnya DIJAWAB peserta ini tanpa menghasilkan angka.
     *
     * Dibaca dari jawaban yang benar-benar masuk, bukan dari isi paket soalnya: paket yang
     * memuat butir kualitatif tetapi tak seorang pun menjawabnya memang belum tercakup, dan
     * itu perbedaan yang justru ingin dijaga penahanan ini.
     *
     * @return list<int>
     */
    private function jenisTerjawabTanpaAngka(int $participantId): array
    {
        return DB::table('N_PA_Assignment_Score as s')
            ->join('N_PA_Assignment_Payload as pl', 'pl.Id_PA_Assignment_Payload', '=', 's.PA_Assignment_Payload_Id')
            ->join('N_PA_Assignment_Integration as i', 'i.Id_PA_Assignment_Integration', '=', 'pl.PA_Assignment_Integration_Id')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 's.PA_Competency_Id')
            ->where('a.PA_Participant_Id', $participantId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('s.Flag_Aktif', 'Y')
            ->whereNull('s.Skor') // skor-mentah: hanya memeriksa ADA-TIDAKNYA angka; butir tanpa angka tidak pernah dikalibrasi.
            // Jawaban di baris bagian ATAU di anak esai per soal; revisi esai tidak boleh kosong, jadi ada-tidaknya sama.
            ->whereRaw(PaSkorEfektif::sqlAdaJawaban('s'))
            ->distinct()
            ->pluck('c.PA_Competency_Type_Id')
            ->map(fn ($x) => (int) $x)
            ->all();
    }

    /**
     * Peta kompetensi ke jenisnya, diambil SEKALI per proses.
     *
     * Master kompetensi berjumlah puluhan dan tidak berubah di tengah perhitungan, jadi
     * mengambilnya per peserta berarti satu perjalanan ke basis data untuk jawaban yang
     * sama - dan pada siklus 958 orang, perjalanan itulah yang mendominasi waktunya.
     *
     * @return array<int, int>
     */
    private function petaJenisKompetensi(): array
    {
        if ($this->petaJenis === null) {
            $this->petaJenis = DB::table('N_PA_Competency')
                ->pluck('PA_Competency_Type_Id', 'Id_PA_Competency')
                ->mapWithKeys(fn ($t, $c) => [(int) $c => (int) $t])
                ->all();
        }

        return $this->petaJenis;
    }

    /**
     * Penilai yang sudah mengisi, beserta nilai butirnya, dikelompokkan per kategori.
     *
     * @return array<int, array{diharapkan:int, penilai:list<array{
     *     assignment_id:int, bobot:float, nilai:float,
     *     kompetensi:array<int, float>
     * }>}>
     */
    private function penugasanDenganNilai(int $participantId): array
    {
        $tugas = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Assignment', 'PA_Rater_Category_Id', 'Bobot_Dalam_Kategori',
                'Status_Penugasan']);

        if ($tugas->isEmpty()) {
            return [];
        }

        /* SKOR EFEKTIF, LEWAT SATU PINTU. PaSkorEfektif memegang dua aturan sekaligus:
           payload terpeta terakhir yang berlaku (yang lama tetap tersimpan), dan Kalibrasi
           Penilai terbaru menang atas skor asli HC Learn. Membaca N_PA_Assignment_Score
           langsung di sini berarti perhitungan diam-diam mengabaikan setiap kalibrasi. */
        $butirPerTugas = $this->skorEfektif->butirBerskor(
            $tugas->pluck('Id_PA_Assignment')->map(fn ($x) => (int) $x)->all()
        );

        $hasil = [];

        foreach ($tugas as $t) {
            $kategoriId = (int) $t->PA_Rater_Category_Id;
            $hasil[$kategoriId] ??= ['diharapkan' => 0, 'penilai' => []];
            $hasil[$kategoriId]['diharapkan']++;

            if ($t->Status_Penugasan !== 'SELESAI') {
                continue;
            }

            /* BUTIR TANPA KOMPETENSI TIDAK DIHITUNG (14 Okt 2026). Sebelumnya ia masuk nilai
               penilai — jadi ikut nilai kategori dan nilai akhir — tetapi tidak muncul di radar
               dan tidak ikut pemeriksaan jenis kompetensi wajib: satu angka, dua cerita.
               Butir yang tidak mengukur kompetensi mana pun tidak pantas membentuk nilai.

               Ini bukan aturan yang diam: butir paket siklus wajib berkompetensi (kolomnya
               NOT NULL), jadi skor tanpa kompetensi hanya lahir dari Kode_Soal kiriman yang
               tidak dikenal paket. Kiriman seperti itu sudah menahan penugasannya (tidak
               SELESAI) dan tampil di "Kiriman perlu ditinjau" (`PaKirimanTinjauService`). */
            $butir = ($butirPerTugas[(int) $t->Id_PA_Assignment] ?? null)
                ?->filter(fn ($b) => $b->PA_Competency_Id !== null)
                ->values();

            if ($butir === null || $butir->isEmpty()) {
                // Payload sampai tetapi tak satu butir pun berskor. Penilai ini tidak
                // dihitung sebagai pengisi: menghitungnya sebagai nol akan menghukum
                // peserta atas butir yang memang tidak berangka.
                continue;
            }

            $hasil[$kategoriId]['penilai'][] = [
                'assignment_id' => (int) $t->Id_PA_Assignment,
                'bobot' => (float) $t->Bobot_Dalam_Kategori,
                'nilai' => $this->nilaiSatuPenilai($butir),
                'kompetensi' => $this->nilaiKompetensiSatuPenilai($butir),
                // Porsi tiap kompetensi di nilai penilai ini — bahan bobot Balancing (spec §6.1).
                'porsi' => PaBalancingKalkulator::porsiPenilai($butir),
            ];
        }

        return $hasil;
    }

    /**
     * Nilai satu penilai: butir dinormalkan ke 0–100 lalu dirata-rata.
     *
     * Bobot antar butir dipakai hanya bila SELURUH butir berskor punya bobot. Memakai
     * bobot sebagian berarti butir tanpa bobot ikut dianggap berbobot nol dan hilang
     * dari nilai tanpa jejak.
     */
    private function nilaiSatuPenilai($butir): float
    {
        $semuaBerbobot = $butir->every(fn ($b) => $b->Bobot_Sumber !== null);
        $totalBobot = $semuaBerbobot
            ? (float) $butir->sum(fn ($b) => (float) $b->Bobot_Sumber)
            : 0.0;

        if ($semuaBerbobot && $totalBobot > 0) {
            $jumlah = 0.0;
            foreach ($butir as $b) {
                $jumlah += $this->normal($b) * (float) $b->Bobot_Sumber;
            }

            return round($jumlah / $totalBobot, 4);
        }

        $jumlah = 0.0;
        foreach ($butir as $b) {
            $jumlah += $this->normal($b);
        }

        return round($jumlah / $butir->count(), 4);
    }

    /**
     * @return array<int, float> nilai per kompetensi bagi satu penilai
     */
    private function nilaiKompetensiSatuPenilai($butir): array
    {
        $kumpulan = [];

        foreach ($butir as $b) {
            if ($b->PA_Competency_Id === null) {
                continue;
            }

            $kumpulan[(int) $b->PA_Competency_Id][] = $this->normal($b);
        }

        $hasil = [];
        foreach ($kumpulan as $id => $nilai) {
            $hasil[$id] = round(array_sum($nilai) / count($nilai), 4);
        }

        return $hasil;
    }

    private function normal(object $butir): float
    {
        return (float) $butir->Skor / (float) $butir->Skor_Maksimal * 100;
    }

    /**
     * Nilai satu kategori: rata-rata tertimbang `Bobot_Dalam_Kategori` para penilainya.
     *
     * Bobotnya dinormalkan ulang di sini karena penilai yang tidak mengisi tetap
     * memegang bagiannya di tabel penugasan. Tanpa normalisasi ulang, kategori dengan
     * tiga penilai yang hanya diisi dua orang akan bernilai dua per tiga dari yang
     * seharusnya.
     *
     * @param  list<array{bobot:float, nilai:float}>  $penilai
     */
    private function nilaiSatuKategori(array $penilai): ?float
    {
        if ($penilai === []) {
            return null;
        }

        $totalBobot = 0.0;
        $jumlah = 0.0;

        foreach ($penilai as $p) {
            $totalBobot += $p['bobot'];
            $jumlah += $p['nilai'] * $p['bobot'];
        }

        if ($totalBobot <= 0) {
            // Bobot belum pernah dihitung. Rata-rata sederhana lebih jujur daripada
            // membagi dengan nol atau mengembalikan kosong.
            return round(
                array_sum(array_column($penilai, 'nilai')) / count($penilai), 4
            );
        }

        return round($jumlah / $totalBobot, 4);
    }

    /**
     * Nilai per kompetensi: satu baris per (kompetensi, kategori) ditambah satu baris
     * agregat lintas kategori per kompetensi.
     *
     * @param  array<string, array<string, mixed>>  $kategoriInfo
     * @param  array<string, float>  $bobotTerpakai
     * @return list<array{competency_id:int, kategori_id:int|null, nilai:float, penilai:int}>
     */
    private function nilaiPerKompetensi(array $kategoriInfo, array $bobotTerpakai): array
    {
        $perKategori = [];
        $penilaiPerKategori = [];

        foreach ($kategoriInfo as $kode => $info) {
            if (! $info['dipakai']) {
                continue;
            }

            $tertimbang = [];
            $bobotPer = [];
            $mentah = [];

            foreach ($info['penilai'] as $p) {
                $bobotPenilai = max($p['bobot'], 0);

                foreach ($p['kompetensi'] as $competencyId => $nilai) {
                    $tertimbang[$competencyId] = ($tertimbang[$competencyId] ?? 0.0)
                        + $nilai * $bobotPenilai;
                    $bobotPer[$competencyId] = ($bobotPer[$competencyId] ?? 0.0) + $bobotPenilai;
                    $mentah[$competencyId][] = $nilai;
                }
            }

            foreach ($mentah as $competencyId => $nilaiMentah) {
                $totalBobot = $bobotPer[$competencyId];

                // Bobot dalam kategori bisa masih nol bila penugasannya belum pernah
                // dihitung ulang. Rata-rata sederhana lebih jujur daripada nol.
                $nilai = $totalBobot > 0
                    ? $tertimbang[$competencyId] / $totalBobot
                    : array_sum($nilaiMentah) / count($nilaiMentah);

                $perKategori[$kode][$competencyId] = round($nilai, 2);
                $penilaiPerKategori[$kode][$competencyId] = count($nilaiMentah);
            }
        }

        $baris = [];
        $agregat = [];
        $agregatBobot = [];
        $agregatPenilai = [];

        foreach ($perKategori as $kode => $nilaiKompetensi) {
            $bobot = $bobotTerpakai[$kode] ?? 0.0;

            foreach ($nilaiKompetensi as $competencyId => $nilai) {
                $baris[] = [
                    'competency_id' => (int) $competencyId,
                    'kategori_id' => $kategoriInfo[$kode]['kategori_id'],
                    'nilai' => $nilai,
                    'penilai' => $penilaiPerKategori[$kode][$competencyId],
                ];

                $agregat[$competencyId] = ($agregat[$competencyId] ?? 0.0) + $nilai * $bobot;
                $agregatBobot[$competencyId] = ($agregatBobot[$competencyId] ?? 0.0) + $bobot;
                $agregatPenilai[$competencyId] = ($agregatPenilai[$competencyId] ?? 0)
                    + $penilaiPerKategori[$kode][$competencyId];
            }
        }

        foreach ($agregat as $competencyId => $tertimbang) {
            $bobot = $agregatBobot[$competencyId];

            if ($bobot <= 0) {
                continue;
            }

            $baris[] = [
                'competency_id' => (int) $competencyId,
                // Kosong berarti agregat lintas kategori — inilah yang digambar radar.
                'kategori_id' => null,
                'nilai' => round($tertimbang / $bobot, 2),
                'penilai' => $agregatPenilai[$competencyId],
            ];
        }

        return $baris;
    }

    /**
     * @param  array<string, array<string, mixed>>  $kategoriInfo
     * @return array<string, array<string, mixed>>
     */
    private function tanpaPenilai(array $kategoriInfo): array
    {
        foreach ($kategoriInfo as $kode => $info) {
            unset($kategoriInfo[$kode]['penilai']);
        }

        return $kategoriInfo;
    }

    /* ------------------------------------------------------------------ *
     * Tahap 4 — pita dan kuadran                                          *
     * ------------------------------------------------------------------ */

    /**
     * Pita SALINAN SIKLUS. Batas atas eksklusif, sesuai DML-2026-079.
     */
    private function cariPita(int $cycleId, string $sumbu, string $kegunaan, float $nilai): ?object
    {
        $pita = $this->konteks($cycleId)['pita'][$sumbu.'|'.$kegunaan] ?? null;

        if ($pita === null) {
            return null;
        }

        // Batas bawah inklusif, batas atas EKSKLUSIF - sesuai DML-2026-079. Menukarnya
        // membuat nilai tepat di batas jatuh ke dua pita sekaligus.
        foreach ($pita as $p) {
            if ((float) $p->Nilai_Dari <= $nilai && $nilai < (float) $p->Nilai_Sampai) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Menentukan nasib keputusan kalibrasi sesudah satu perhitungan ulang.
     *
     * DIPISAH DARI `hitung()` DENGAN SENGAJA. Keputusannya menyentuh tiga kolom sekaligus
     * — `Kode_Grade_Final`, `Flag_Dikalibrasi`, `PA_Hav_Quadrant_Id` — dan selama ia hidup
     * sebagai tiga ternari berdampingan, ketiganya bisa berbeda pendapat tanpa ada yang
     * memperhatikan: satu diperbaiki, dua tertinggal, dan hasilnya adalah baris yang
     * mengaku dikalibrasi tetapi grade-nya sudah angka mesin. Di sini ketiganya lahir dari
     * satu percabangan, dan penjaganya bisa memanggilnya tanpa membangun seluruh siklus.
     *
     * TIGA KEADAAN, bukan dua:
     *
     *   belum pernah dikalibrasi   Semuanya ikut mesin. Grade final mengikuti grade sistem
     *                              sampai ada yang mengkalibrasi; mengosongkannya membuat
     *                              layar hasil tampak belum jadi.
     *
     *   dikalibrasi, hasil ada     Keputusan forum DIPERTAHANKAN. Sebelum 7 September 2026
     *                              perhitungan ulang menimpanya, sehingga satu kiriman
     *                              susulan seorang penilai cukup untuk mengembalikan grade
     *                              yang sudah disepakati — otomatis, tanpa ada yang menekan
     *                              apa pun.
     *
     *   dikalibrasi, jadi TERTAHAN Keputusan forum DILEPAS. Hasil tertahan tidak punya
     *                              grade sama sekali, dan grade final tidak bisa berdiri di
     *                              atas hasil yang tak punya angka. Pelepasannya dicatat.
     *
     * @return array{grade_final:string|null, flag:string, kuadran_id:int|null, dilepas:bool}
     */
    private function nilaiKalibrasiSesudahHitung(
        object $hasilLama,
        ?string $tertahan,
        ?string $grade,
        ?int $kuadranId,
    ): array {
        $sudahDikalibrasi = ($hasilLama->Flag_Dikalibrasi ?? 'T') === 'Y';

        if (! $sudahDikalibrasi) {
            return [
                'grade_final' => $grade,
                'flag' => 'T',
                'kuadran_id' => $kuadranId,
                'dilepas' => false,
            ];
        }

        if ($tertahan !== null) {
            return [
                'grade_final' => $grade,
                'flag' => 'T',
                'kuadran_id' => $kuadranId,
                'dilepas' => true,
            ];
        }

        return [
            'grade_final' => $hasilLama->Kode_Grade_Final ?? $grade,
            'flag' => 'Y',
            'kuadran_id' => $hasilLama->PA_Hav_Quadrant_Id ?? $kuadranId,
            'dilepas' => false,
        ];
    }

    /**
     * Mencatat bahwa kalibrasi dilepas karena hasilnya menjadi TERTAHAN.
     *
     * Ditulis ke riwayat kalibrasi, bukan sekadar diubah diam-diam di baris hasil. Yang
     * membaca setahun kemudian perlu tahu bahwa grade yang pernah disepakati forum pernah
     * ada dan kenapa ia tidak ada lagi — dan riwayat kalibrasi adalah satu-satunya tempat
     * yang menyimpan nilai SEBELUMNYA.
     *
     * TIDAK ADA GRADE SESUDAH, dan sering tidak ada pelaku. Keduanya sengaja ditulis NULL:
     * hasil tertahan memang tidak punya grade, dan pemicu yang paling sering adalah kiriman
     * terakhir seorang penilai yang masuk lewat callback HC Learn — di sana tidak ada sesi
     * HCIS, jadi tidak ada user yang jujur bisa dituliskan. Menuliskan grade karangan akan
     * ditampilkan layar sebagai grade, dan menuliskan user karangan menaruh nama orang pada
     * tindakan yang tidak ia lakukan. Kelonggarannya diikat CK_PA_Calibration_History_Pelaku
     * (DDL-2026-089): baris tanpa pelaku hanya sah bila ia memang pelepasan.
     *
     * `Performed_By_Nama` tetap diisi walau pelakunya kosong. Itu satu-satunya kolom yang
     * membuat baris ini terbaca di layar audit; tanpanya ia muncul sebagai peristiwa tanpa
     * siapa pun, yang terbaca seperti data rusak alih-alih tindakan otomatis.
     */
    private function lepaskanKalibrasi(object $hasilLama, string $sebabTertahan, ?int $olehUserId): void
    {
        $alasan = 'Kalibrasi dilepas otomatis: perhitungan ulang membuat hasil ini TERTAHAN, '
            .'sehingga tidak ada grade yang bisa dipertahankan. Sebabnya: '.$sebabTertahan;

        DB::table('N_PA_Calibration_History')->insert([
            'PA_Result_Id' => (int) $hasilLama->Id_PA_Result,
            'Kode_Grade_Sebelum' => $hasilLama->Kode_Grade_Final,
            'Kode_Grade_Sesudah' => null,
            'PA_Hav_Quadrant_Sebelum_Id' => $hasilLama->PA_Hav_Quadrant_Id,
            'PA_Hav_Quadrant_Sesudah_Id' => null,

            /* Alasan nvarchar(1000). Sebab tertahan disusun dari kalimat yang panjangnya
               tidak dibatasi apa pun, dan kolom yang kepenuhan menolak SELURUH baris —
               pelepasannya batal justru karena keterangannya terlalu lengkap. */
            'Alasan' => mb_substr($alasan, 0, 1000),

            'Performed_By' => $olehUserId,
            'Performed_By_Nama' => $olehUserId === null
                ? 'Sistem (perhitungan ulang otomatis)'
                : null,
            'Performed_At' => $this->jamProses,
        ]);
    }

    /**
     * @return array{0:int|null, 1:string|null}
     */
    private function cariKuadran(int $cycleId, float $nilaiKpi, float $nilaiKompetensi): array
    {
        $performa = $this->cariPita($cycleId, 'KPI', 'HAVMAP', $nilaiKpi)?->Kode_Range;
        $kompetensi = $this->cariPita($cycleId, 'KOMPETENSI', 'HAVMAP', $nilaiKompetensi)?->Kode_Range;

        if ($performa === null || $kompetensi === null) {
            // Kuadran yang tak bisa ditentukan dibiarkan kosong, bukan ditebak. HAV Map
            // yang salah kotak lebih berbahaya daripada HAV Map yang bolong.
            return [null, null];
        }

        $kuadran = $this->konteks($cycleId)['kuadran']
            ->first(fn ($k) => $k->Tingkat_Performa === $performa && $k->Tingkat_Kompetensi === $kompetensi);

        return $kuadran === null
            ? [null, null]
            : [(int) $kuadran->Id_PA_Hav_Quadrant, $kuadran->Kode_Kuadran];
    }

    /* ------------------------------------------------------------------ *
     * Penyimpanan jejak                                                   *
     * ------------------------------------------------------------------ */

    /**
     * MENGEMBALIKAN berapa baris jejak yang dinonaktifkan.
     *
     * Angkanya dipakai memutuskan nomor versi berikutnya — lihat pemanggilnya. Baris catatan
     * yang diketik admin TIDAK ikut dihitung: ia bukan jejak perhitungan, dan menghitungnya
     * akan membuat perhitungan perdana pada peserta yang sudah punya catatan tangan disangka
     * perhitungan ulang.
     */
    private function nonaktifkanVersiLama(int $resultId, ?int $olehUserId): int
    {
        $sekarang = $this->jamProses ?? FormatTanggalHelper::getCurrentTime();
        $tersentuh = 0;

        foreach (['N_PA_Result_Rater_Category', 'N_PA_Result_Competency'] as $tabel) {
            $tersentuh += (int) DB::table($tabel)
                ->where('PA_Result_Id', $resultId)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);
        }

        // Catatan yang diketik admin SELAMAT. Yang dinonaktifkan hanya yang berasal
        // dari jawaban penilai, karena hanya itu yang akan dibentuk ulang.
        DB::table('N_PA_Result_Note')
            ->where('PA_Result_Id', $resultId)
            ->whereNotNull('PA_Assignment_Id')
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Flag_Aktif' => 'T',
                'Updated_At' => $sekarang,
                'Updated_By' => $olehUserId,
            ]);

        return $tersentuh;
    }

    /**
     * @param  array<string, mixed>  $kompetensi
     */
    private function simpanJejakKategori(int $resultId, array $kompetensi, int $versi, ?int $olehUserId): void
    {
        $baris = [];

        foreach ($kompetensi['kategori'] as $info) {
            $baris[] = [
                'PA_Result_Id' => $resultId,
                'PA_Rater_Category_Id' => $info['kategori_id'],
                'Nilai' => $info['nilai'] === null ? null : round($info['nilai'], 2),
                'Jumlah_Rater_Diharapkan' => $info['diharapkan'],
                'Jumlah_Rater_Mengisi' => $info['mengisi'],
                'Bobot_Terpakai' => $info['bobot_terpakai'],
                'Calculation_Version' => $versi,
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
            ];
        }

        foreach (array_chunk($baris, 100) as $potongan) {
            DB::table('N_PA_Result_Rater_Category')->insert($potongan);
        }
    }

    /**
     * @param  array<string, mixed>  $kompetensi
     */
    private function simpanNilaiKompetensi(int $resultId, array $kompetensi, int $versi, ?int $olehUserId): void
    {
        $baris = [];

        foreach ($kompetensi['kompetensi'] as $k) {
            $baris[] = [
                'PA_Result_Id' => $resultId,
                'PA_Competency_Id' => $k['competency_id'],
                'PA_Rater_Category_Id' => $k['kategori_id'],
                'Nilai' => $k['nilai'],
                'Jumlah_Penilai' => $k['penilai'],
                'Calculation_Version' => $versi,
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
            ];
        }

        foreach (array_chunk($baris, 100) as $potongan) {
            DB::table('N_PA_Result_Competency')->insert($potongan);
        }
    }

    /**
     * Jawaban kualitatif penilai menjadi catatan hasil.
     *
     * Jenis catatannya ditentukan pemetaan soal, bukan ditebak dari isinya. Butir yang
     * tidak dipetakan ke jenis catatan mana pun sengaja tidak menjadi catatan — jawaban
     * teks yang tidak diketahui perannya lebih baik tidak muncul di layar hasil karyawan
     * daripada muncul di bawah judul yang salah.
     */
    private function simpanCatatanDariPenilai(int $resultId, int $participantId, ?int $olehUserId): void
    {
        $cycleId = (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id');

        $q = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Assignment_Integration as ai', function ($j) {
                $j->on('ai.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')
                    ->where('ai.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Assignment_Payload as ap',
                'ap.PA_Assignment_Integration_Id', '=', 'ai.Id_PA_Assignment_Integration')
            ->join('N_PA_Assignment_Score as s', function ($j) {
                $j->on('s.PA_Assignment_Payload_Id', '=', 'ap.Id_PA_Assignment_Payload')
                    ->where('s.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Cycle_Question_Package_Item as m', function ($j) {
                $j->on('m.PA_Cycle_Question_Package_Id', '=', 'ai.PA_Cycle_Question_Package_Id')
                    /* KODE BERTEMU KODE. Sisi skor menyimpan kode yang DIKIRIM HC Learn;
                       sisi butir menyimpan kode yang MEREKA berikan saat paket diikat, dan
                       sejak DDL-2026-095 kode itu ikut dibekukan di sini. Sampai
                       10 September 2026 join ini memakai `Id_Jenis_Soal` — pengenal bagian,
                       bukan kode — sehingga panel catatan kualitatif selalu kosong pada
                       kiriman sungguhan. */
                    ->on('m.Kode_Soal_Sumber', '=', 's.Kode_Soal_Sumber')
                    ->where('m.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Competency as kmp', 'kmp.Id_PA_Competency', '=', 's.PA_Competency_Id');

        $baris = PaKompetensiSiklus::gabung($q, 'kmp', $cycleId)
            ->where('a.PA_Participant_Id', $participantId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('ap.Flag_Terpeta', 'Y')
            ->whereRaw(PaSkorEfektif::sqlAdaJawaban('s'))
            ->orderBy('a.Id_PA_Assignment')
            ->orderBy('s.Urutan_Tampil')
            ->get([
                'a.Id_PA_Assignment', 's.Id_PA_Assignment_Score', 's.PA_Competency_Id', 's.Kode_Soal_Sumber',
                PaKompetensiSiklus::kolom('Flag_Berskor', 'kmp'),
                's.Jawaban_Teks', // jawaban-mentah: langsung ditimpa jawaban efektif lewat PaSkorEfektif::timpaJawaban().
            ]);

        /* Catatan hasil memakai jawaban EFEKTIF — revisi Kalibrasi Penilai terbaru bila ada —
           dan disimpan sebagai HTML AMAN, seragam dengan catatan yang ditulis admin lewat editor
           teks kaya. Layar karyawan dan leader menggambarnya dengan `v-html`; teks polos HC Learn
           karena itu di-escape per baris, bukan dituang mentah. */
        $baris = $this->skorEfektif->timpaJawaban($baris);

        $urutan = 0;
        $sisip = [];

        foreach ($baris as $b) {
            if (trim((string) $b->Jawaban_Teks) === '') { // jawaban-mentah: sudah teks polos efektif (ditimpa di atas).
                continue;
            }

            /* KATEGORINYA ADALAH KOMPETENSINYA SENDIRI.
               Butir yang jawabannya teks tetapi tidak terpetakan ke kompetensi mana pun
               tidak bisa ditempatkan di panel apa pun — dilewati, bukan ditebak. Teksnya
               tetap utuh di `N_PA_Assignment_Score` dan tetap terbaca admin pada daftar
               "Jawaban Naratif Penilai", jadi tidak ada yang hilang. */
            if ($b->PA_Competency_Id === null) {
                continue;
            }

            /* Butir BERSKOR tidak menjadi catatan meski jawabannya memuat teks: sebagian
               formulir mengizinkan komentar di samping angka, dan komentar itu pelengkap
               nilai, bukan umpan balik naratif yang berdiri sendiri. */
            if (($b->Flag_Berskor ?? 'Y') === 'Y') {
                continue;
            }

            $urutan++;

            $sisip[] = [
                'PA_Result_Id' => $resultId,
                'PA_Competency_Id' => (int) $b->PA_Competency_Id,
                'PA_Assignment_Id' => (int) $b->Id_PA_Assignment,
                /* Format per soal: pertanyaannya ditaruh di atas jawaban, supaya catatan
                   tetap terbaca tanpa layar kalibrasi. Soal yang sudah dihapus di HC Learn
                   (pertanyaan null) tampil sebagai jawaban saja. */
                'Isi' => ($b->Pertanyaan === null ? '' : '<p><strong>'.e((string) $b->Pertanyaan).'</strong></p>')
                    .(string) $b->Jawaban_Html,
                'Urutan_Tampil' => $urutan,
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
            ];
        }

        foreach (array_chunk($sisip, 100) as $potongan) {
            DB::table('N_PA_Result_Note')->insert($potongan);
        }
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Baris peserta dan baris hasilnya sekaligus.
     *
     * Keduanya SELALU dibutuhkan bersama dan berhubungan satu-ke-satu, jadi memisahnya
     * menjadi dua kueri hanya menambah satu perjalanan ke database untuk setiap orang
     * yang dihitung — dan satu siklus berisi ratusan orang.
     *
     * @return array{0:object, 1:object}
     */
    private function pesertaDanHasil(int $participantId): array
    {
        $baris = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', 'r.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->where('p.Id_PA_Participant', $participantId)
            ->first([
                'p.Id_PA_Participant', 'p.PA_Cycle_Id', 'p.Kode_Perusahaan', 'p.Kode_Karyawan',
                'p.ID_Level_Saat_Cycle', 'p.Status_Peserta',
                'r.Id_PA_Result', 'r.Calculation_Version', 'r.Status_Hasil',

                /* Keputusan kalibrasi ikut dibaca supaya perhitungan ulang bisa MEMPERTAHANKANNYA.
                   Tanpa ketiganya, satu-satunya jalan adalah menimpanya dengan angka sistem —
                   dan itu menghapus keputusan forum tanpa seorang pun menekan apa pun. */
                'r.Flag_Dikalibrasi', 'r.Kode_Grade_Final', 'r.PA_Hav_Quadrant_Id',

                // Hasil yang sudah dibaca leader ditolak dihitung ulang — lihat `hitung()`.
                'r.Published_Leader_At',
            ]);

        if ($baris === null) {
            throw new RuntimeException("Peserta {$participantId} tidak ada.");
        }

        if ($baris->Id_PA_Result === null) {
            throw new RuntimeException(
                "Peserta {$participantId} tidak punya baris hasil. Baris hasil dibentuk saat "
                .'siklus disiapkan; ketiadaannya berarti penyiapannya belum selesai.'
            );
        }

        return [
            (object) [
                'Id_PA_Participant' => $baris->Id_PA_Participant,
                'PA_Cycle_Id' => $baris->PA_Cycle_Id,
                'Kode_Perusahaan' => $baris->Kode_Perusahaan,
                'Kode_Karyawan' => $baris->Kode_Karyawan,
                'ID_Level_Saat_Cycle' => $baris->ID_Level_Saat_Cycle,
                'Status_Peserta' => $baris->Status_Peserta,
            ],
            (object) [
                'Id_PA_Result' => $baris->Id_PA_Result,
                'Calculation_Version' => $baris->Calculation_Version,
                'Status_Hasil' => $baris->Status_Hasil,

                /* Objeknya DIRAKIT ULANG, bukan diteruskan apa adanya — jadi kolom yang
                   ditambahkan ke SELECT tidak sampai ke pemanggil kalau tidak disebut di
                   sini juga. Ketiganya wajib ada: tanpa `Flag_Dikalibrasi`, penjagaan
                   kalibrasi di `hitung()` selalu membaca 'T' dan diam-diam kembali menimpa
                   keputusan forum — persis cacat yang hendak ditutupnya. */
                'Flag_Dikalibrasi' => $baris->Flag_Dikalibrasi,
                'Kode_Grade_Final' => $baris->Kode_Grade_Final,
                'PA_Hav_Quadrant_Id' => $baris->PA_Hav_Quadrant_Id,
                'Published_Leader_At' => $baris->Published_Leader_At,
            ],
        ];
    }
}
