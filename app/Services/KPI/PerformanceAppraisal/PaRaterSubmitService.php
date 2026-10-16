<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Jobs\KPI\PerformanceAppraisal\HitungHasilPesertaJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SATU PENGIRIMAN UNTUK SELURUH TUGAS SEORANG PENILAI.
 *
 * ==========================================================================================
 * MASALAH YANG DISELESAIKAN
 * ==========================================================================================
 *
 * Seorang penilai jarang punya satu tugas. Di data 7 September 2026: 894 penilai, rata-rata
 * empat penugasan, terbanyak dua puluh empat, dan 570 di antaranya punya lebih dari satu.
 * Seseorang mengisi untuk dirinya sendiri, untuk atasannya, lalu berhenti — dan sampai
 * sekarang tiap lembar yang masuk langsung dianggap final sendiri-sendiri.
 *
 * Akibatnya penilaian seseorang bisa ikut menentukan angka orang lain padahal ia sendiri
 * belum menganggap pekerjaannya selesai. Lebih buruk lagi bila ia lembar TERAKHIR seorang
 * peserta: hasil peserta itu langsung dihitung dan bisa langsung terbit, di atas penilaian
 * yang pengisinya masih berniat meninjau ulang.
 *
 * ==========================================================================================
 * BENTUK PENYELESAIANNYA
 * ==========================================================================================
 *
 * Lembar yang masuk TIDAK menahan penilai mengerjakan lembar berikutnya — ia hanya belum
 * dihitung. Penugasannya berhenti di `TERKUMPUL`: jawabannya tersimpan dan terpetakan utuh,
 * pengingatnya berhenti, tetapi ia belum `SELESAI`. Sesudah SELURUH tugasnya di siklus itu
 * terkumpul, penilai menekan satu tombol kirim, dan barulah semuanya menjadi `SELESAI`
 * sekaligus — dan barulah peserta-pesertanya boleh dihitung.
 *
 * `TERKUMPUL` sengaja tidak dikecualikan dari `sisaPenugasan()`. Justru itu yang membuat
 * seluruh rantai bekerja tanpa satu pun kondisi tambahan: peserta yang penilainya masih
 * terkumpul terbaca belum lengkap, jadi perhitungan otomatisnya tidak berjalan, jadi tidak
 * ada angka yang lahir di atas pekerjaan yang belum dikirim.
 *
 * ==========================================================================================
 * DUA PINTU DARURAT, DAN KENAPA KEDUANYA WAJIB
 * ==========================================================================================
 *
 * Aturan ini memindahkan risiko, bukan menghapusnya. Sebelumnya lembar yang macet hanya
 * merugikan satu peserta; sekarang seorang penilai yang mengerjakan 23 dari 24 lembar —
 * atau menyelesaikan semuanya lalu lupa menekan kirim — membekukan hasil dua puluh tiga
 * orang lain yang tidak tahu-menahu.
 *
 * Karena itu ada dua jalan keluar, dan keduanya bukan tambalan melainkan bagian dari
 * rancangannya:
 *
 *   `lepasPaksa()`            Admin melepas kunci seorang penilai, dengan sebab tercatat.
 *   `lepasSemuaSaatDitutup()` Penutupan siklus melepas seluruh yang terkumpul.
 *
 * Yang kedua yang paling penting: menutup pengisian berarti menyatakan pengumpulan usai, dan
 * pekerjaan yang sudah masuk pada saat itu tidak boleh hangus hanya karena pengirimnya tidak
 * sempat menekan satu tombol.
 */
class PaRaterSubmitService
{
    /** Terisi dan terpetakan, tetapi belum dikirim penilainya. */
    public const STATUS_TERKUMPUL = 'TERKUMPUL';

    public const STATUS_SELESAI = 'SELESAI';

    /**
     * Status yang berarti "masih pekerjaan penilai".
     *
     * `MENUNGGU_VERIFIKASI` ikut di sini walau orangnya sudah mengirim ke HC Learn: nilainya
     * belum sah sampai diverifikasi, dan melepasnya sebagai selesai berarti menghitung angka
     * yang masih bisa berubah.
     */
    private const STATUS_BELUM_TERKUMPUL = ['BELUM_MULAI', 'BERJALAN', 'MENUNGGU_VERIFIKASI'];

    public function __construct(
        private readonly PaCycleGate $gate,
    ) {}

    /* ====================================================================== *
     * BACA — apa yang dilihat penilai                                         *
     * ====================================================================== */

    /**
     * Kemajuan pengumpulan seorang penilai, per siklus.
     *
     * Dikembalikan PER SIKLUS, bukan satu angka gabungan. Penilai bisa punya tugas di lebih
     * dari satu siklus sekaligus, dan pengiriman satu siklus tidak ada hubungannya dengan
     * siklus lain — menggabungkannya menjadi satu batang kemajuan akan membuat orang
     * menunggu siklus yang belum dibuka sebelum bisa mengirim yang sudah selesai.
     *
     * @return list<array{
     *     cycle_id:int, kode_cycle:string, nama_cycle:string, total:int, terkumpul:int,
     *     terkirim:int, sisa:int, persen:int, bisa_kirim:bool, sudah_terkirim_semua:bool
     * }>
     */
    public function kemajuan(string $kodePerusahaan, string $kodeKaryawan, ?int $cycleId = null): array
    {
        $belum = "'".implode("','", self::STATUS_BELUM_TERKUMPUL)."'";

        $q = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('a.Kode_Perusahaan_Penilai', $kodePerusahaan)
            ->where('a.Kode_Karyawan_Penilai', $kodeKaryawan)
            ->where('a.Flag_Aktif', 'Y')

            /* Penugasan yang dibatalkan bukan pekerjaan siapa pun lagi, dan membiarkannya
               ikut dihitung berarti penilai melihat "5 dari 6" selamanya atas lembar yang
               memang tidak akan pernah ada. */
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->groupBy('k.Id_PA_Cycle', 'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun')
            ->selectRaw("
                k.Id_PA_Cycle as cycle_id,
                k.Kode_Cycle as kode_cycle,
                k.Nama_Cycle as nama_cycle,
                COUNT(*) as total,
                SUM(CASE WHEN a.Status_Penugasan = '".self::STATUS_TERKUMPUL."' THEN 1 ELSE 0 END) as terkumpul,
                SUM(CASE WHEN a.Status_Penugasan = '".self::STATUS_SELESAI."' THEN 1 ELSE 0 END) as terkirim,
                SUM(CASE WHEN a.Status_Penugasan IN ({$belum}) THEN 1 ELSE 0 END) as sisa,
                SUM(CASE WHEN a.Status_Penugasan = '".PaStatusPenugasan::MENUNGGU_VERIFIKASI."' THEN 1 ELSE 0 END) as menunggu_verifikasi
            ")
            ->orderByDesc('k.Tahun')
            ->orderByDesc('k.Id_PA_Cycle');

        if ($cycleId !== null) {
            $q->where('k.Id_PA_Cycle', $cycleId);
        } else {
            $q->whereIn('k.Status_Cycle', PaPesertaService::STATUS_SIKLUS_DAPAT_DIISI);
        }

        return $q->get()->map(function ($b) {
            $total = (int) $b->total;
            $terkumpul = (int) $b->terkumpul;
            $terkirim = (int) $b->terkirim;
            $sisa = (int) $b->sisa;
            $menunggu = (int) $b->menunggu_verifikasi;

            return [
                'cycle_id' => (int) $b->cycle_id,
                'kode_cycle' => (string) $b->kode_cycle,
                'nama_cycle' => (string) $b->nama_cycle,
                'total' => $total,
                'terkumpul' => $terkumpul,
                'terkirim' => $terkirim,
                'sisa' => $sisa,

                /* Bagian dari `sisa` yang SUDAH diisi penilai tetapi nilainya menunggu
                   diverifikasi admin HC Learn. Penilainya tidak bisa berbuat apa-apa atasnya;
                   tanpa angka ini layar hanya bisa menampilkan tombol Kirim yang mati tanpa
                   alasan, padahal seluruh kategorinya sudah terlihat tuntas. */
                'menunggu_verifikasi' => $menunggu,
                'belum_diisi' => $sisa - $menunggu,

                // Yang dilihat orang adalah "berapa lembar sudah saya kerjakan": terkumpul,
                // terkirim, dan yang menunggu verifikasi sama-sama sudah dikerjakan.
                'persen' => $total === 0 ? 0 : (int) round((($terkumpul + $terkirim + $menunggu) / $total) * 100),

                /* Tombol kirim hidup hanya bila TIDAK ADA sisa DAN ada yang bisa dikirim.
                   Dua syarat, bukan satu: tanpa syarat kedua tombolnya hidup pada penilai
                   yang seluruh tugasnya sudah terkirim, dan menekannya menghasilkan
                   penolakan atas keadaan yang sebetulnya sudah benar. */
                'bisa_kirim' => $sisa === 0 && $terkumpul > 0,
                'sudah_terkirim_semua' => $total > 0 && $terkirim === $total,
            ];
        })->all();
    }

    /* ====================================================================== *
     * TULIS                                                                   *
     * ====================================================================== */

    /**
     * Penilai mengirim SELURUH tugasnya di satu siklus.
     *
     * @return array{cycle_id:int, dilepas:int, peserta_diantrekan:int}
     */
    public function kirimSemua(
        string $kodePerusahaan,
        string $kodeKaryawan,
        int $cycleId,
        ?int $olehUserId = null,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::TERIMA_PAYLOAD, $cycleId);

        $kemajuan = $this->kemajuan($kodePerusahaan, $kodeKaryawan, $cycleId)[0] ?? null;

        if ($kemajuan === null) {
            throw new RuntimeException(
                'Anda tidak punya tugas penilaian pada siklus ini, jadi tidak ada yang bisa dikirim.'
            );
        }

        /* SEBABNYA DISEBUT DENGAN ANGKA. "Belum bisa dikirim" saja memaksa orang membuka
           daftarnya satu per satu untuk menemukan mana yang tertinggal — pada penilai
           dengan dua puluh empat lembar itu pekerjaan tersendiri. */
        if ($kemajuan['sisa'] > 0) {
            throw new RuntimeException(sprintf(
                'Masih ada %d dari %d penilaian yang belum Anda selesaikan. Seluruhnya harus '
                .'selesai lebih dulu, baru bisa dikirim sekaligus.',
                $kemajuan['sisa'],
                $kemajuan['total'],
            ));
        }

        if ($kemajuan['terkumpul'] === 0) {
            throw new RuntimeException(
                'Seluruh penilaian Anda pada siklus ini sudah terkirim sebelumnya.'
            );
        }

        return $this->lepaskan(
            $cycleId,
            $olehUserId,
            fn ($q) => $q->where('a.Kode_Perusahaan_Penilai', $kodePerusahaan)
                ->where('a.Kode_Karyawan_Penilai', $kodeKaryawan),
            'PENILAI',
        );
    }

    /**
     * Admin melepas kunci seorang penilai tanpa menunggu ia menekan kirim.
     *
     * Dipakai ketika penilai sudah mengerjakan tetapi tidak bisa dihubungi, atau sisa
     * lembarnya memang tidak akan pernah dikerjakan. Bedanya dengan `kirimSemua()`: ini
     * TIDAK menuntut sisa nol — justru keadaan itulah yang membuatnya dibutuhkan.
     *
     * @return array{cycle_id:int, dilepas:int, peserta_diantrekan:int}
     */
    public function lepasPaksa(
        int $cycleId,
        string $kodePerusahaan,
        string $kodeKaryawan,
        string $alasan,
        ?int $olehUserId = null,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::TERIMA_PAYLOAD, $cycleId);

        if (trim($alasan) === '') {
            throw new RuntimeException(
                'Pelepasan paksa wajib bersebab. Yang membaca kelak perlu tahu kenapa '
                .'penilaian ini dihitung tanpa pengirimnya menekan kirim.'
            );
        }

        $hasil = $this->lepaskan(
            $cycleId,
            $olehUserId,
            fn ($q) => $q->where('a.Kode_Perusahaan_Penilai', $kodePerusahaan)
                ->where('a.Kode_Karyawan_Penilai', $kodeKaryawan),
            'ADMIN',
        );

        Log::info('PA_LEPAS_PAKSA', [
            'cycle_id' => $cycleId,
            'penilai' => $kodePerusahaan.'/'.$kodeKaryawan,
            'dilepas' => $hasil['dilepas'],
            'alasan' => $alasan,
            'oleh' => $olehUserId,
        ]);

        return $hasil;
    }

    /**
     * Penutupan pengisian melepas SELURUH yang masih terkumpul di siklus itu.
     *
     * Dipanggil dari `tutupPengisian()`. Menutup pengisian berarti menyatakan pengumpulan
     * usai; membiarkan lembar yang sudah masuk tetap terkunci sesudahnya berarti membuang
     * pekerjaan yang sudah dilakukan orang hanya karena pengirimnya tidak sempat menekan
     * satu tombol — dan tidak ada satu pun layar sesudah penutupan yang menyediakan tombol
     * itu kepadanya.
     *
     * @return array{cycle_id:int, dilepas:int, peserta_diantrekan:int}
     */
    public function lepasSemuaSaatDitutup(int $cycleId, ?int $olehUserId = null): array
    {
        // SENGAJA TANPA gerbang: pemanggilnya adalah penutupan siklus itu sendiri, yang
        // sudah lolos gerbangnya sendiri, dan pada saat ini status siklusnya sudah CLOSED.
        return $this->lepaskan($cycleId, $olehUserId, null, 'PENUTUPAN');
    }

    /* ====================================================================== *
     * Inti                                                                    *
     * ====================================================================== */

    /**
     * Melepas penugasan TERKUMPUL menjadi SELESAI, lalu mengantrekan pesertanya.
     *
     * @param  (callable(\Illuminate\Database\Query\Builder): mixed)|null  $saring
     * @return array{cycle_id:int, dilepas:int, peserta_diantrekan:int}
     */
    private function lepaskan(int $cycleId, ?int $olehUserId, ?callable $saring, string $sebab): array
    {
        $q = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', self::STATUS_TERKUMPUL);

        if ($saring !== null) {
            $saring($q);
        }

        $baris = $q->get(['a.Id_PA_Assignment', 'a.PA_Participant_Id']);

        if ($baris->isEmpty()) {
            return ['cycle_id' => $cycleId, 'dilepas' => 0, 'peserta_diantrekan' => 0];
        }

        $assignmentIds = $baris->pluck('Id_PA_Assignment')->map(fn ($v) => (int) $v)->all();
        $participantIds = $baris->pluck('PA_Participant_Id')->map(fn ($v) => (int) $v)->unique()->values();

        $sekarang = FormatTanggalHelper::getCurrentTime();

        /* SATU TRANSAKSI untuk seluruh lembar penilai ini. Melepasnya satu per satu berarti
           ada saat ketika sebagian sudah SELESAI dan sebagian belum — dan tepat pada saat
           itu perhitungan otomatis bisa berjalan atas penilaian yang setengah dilepas,
           yang persis keadaan yang aturan ini dibuat untuk mencegah. */
        DB::transaction(function () use ($assignmentIds, $sekarang, $olehUserId, $sebab) {
            foreach (array_chunk($assignmentIds, 400) as $potongan) {
                DB::table('N_PA_Assignment')
                    ->whereIn('Id_PA_Assignment', $potongan)
                    ->update([
                        'Status_Penugasan' => self::STATUS_SELESAI,

                        /* Kolom kirim tersendiri (DDL-2026-169): Updated_At tertimpa oleh
                           perubahan apa pun sesudahnya, sehingga "kapan dikirim, oleh siapa,
                           lewat jalur apa" tidak bisa dijawab dari situ. */
                        'Submitted_At' => $sekarang,
                        'Submitted_By' => $olehUserId,
                        'Submitted_Via' => $sebab,

                        'Updated_At' => $sekarang,
                        'Updated_By' => $olehUserId,
                    ]);
            }
        });

        /* Perhitungan diantrekan SESUDAH transaksi ditutup. Job yang lahir di dalam transaksi
           bisa diambil pekerja sebelum commit-nya mendarat, lalu membaca penugasan yang
           masih TERKUMPUL dan menyimpulkan pesertanya belum lengkap. */
        $diantrekan = 0;

        foreach ($participantIds as $pid) {
            if ($this->sisaPenugasan($pid) > 0) {
                continue;
            }

            try {
                HitungHasilPesertaJob::dispatch($pid, $olehUserId);
                $diantrekan++;
            } catch (\Throwable $e) {
                /* Antrean yang bermasalah TIDAK boleh menggagalkan pengirimannya. Lembarnya
                   sudah SELESAI dan tersimpan; yang hilang hanya angkanya, dan admin bisa
                   menghitungnya kapan saja lewat tombolnya. */
                Log::error('PA: antrean perhitungan gagal sesudah pengiriman penilai', [
                    'participant_id' => $pid,
                    'sebab_lepas' => $sebab,
                    'galat' => $e->getMessage(),
                ]);
            }
        }

        Log::info('PA_PENILAI_KIRIM', [
            'cycle_id' => $cycleId,
            'sebab' => $sebab,
            'dilepas' => count($assignmentIds),
            'peserta_diantrekan' => $diantrekan,
        ]);

        return [
            'cycle_id' => $cycleId,
            'dilepas' => count($assignmentIds),
            'peserta_diantrekan' => $diantrekan,
        ];
    }

    /**
     * Ditanyakan ke pemilik aturannya, bukan dihitung ulang di sini.
     *
     * Dua tempat yang menjawab "sudah lengkap belum" akan menyimpang, dan yang menyimpang
     * di sini menentukan kapan angka seseorang lahir.
     */
    private function sisaPenugasan(int $participantId): int
    {
        return app(PaResultCalculationService::class)->sisaPenugasan($participantId);
    }
}
