<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bobot pilar per siklus — pembagian KPI lawan kompetensi, per level (K23).
 *
 * KENAPA SATU-SATUNYA SALINAN YANG BOLEH DISUNTING SESUDAH KUNCI
 *
 * Salinan siklus yang lain membeku saat penguncian karena mereka menentukan SIAPA
 * MENILAI SIAPA, dan mengubahnya di tengah jalan berarti sebagian orang dinilai dengan
 * aturan yang berbeda dari sebagian yang lain. Bobot pilar tidak menyentuh itu sama
 * sekali: ia hanya menimbang dua angka yang sudah terkumpul. Karena itu gerbangnya
 * membolehkannya sampai `CLOSED`.
 *
 * TETAPI ADA SATU HAL YANG TIDAK BISA DIURUNGKAN
 *
 * Hasil yang sudah TERBIT dihitung memakai pembagian yang lama, dan
 * `PaResultCalculationService` menolak menghitung ulang hasil terbit. Jadi menyunting
 * bobot sesudah ada hasil terbit menghasilkan keadaan yang tidak bisa dirapikan lagi:
 * angka yang sudah dilihat orang tidak akan pernah cocok dengan bobot yang tertulis di
 * siklusnya, dan tidak ada satu pun pesan galat yang akan muncul. Karena itu service ini
 * MENOLAK bila ada hasil terbit pada level itu — satu-satunya batasan yang ditambahkan di
 * luar gerbang, dan alasannya bukan kehati-hatian melainkan ketidakterbalikan.
 *
 * Hasil yang baru `DIHITUNG` tidak menghalangi, tetapi jumlahnya dilaporkan: baris itu
 * menjadi basi dan harus dihitung ulang. Diam-diam membiarkannya berarti admin
 * menerbitkan angka yang lahir dari bobot yang sudah tidak berlaku.
 *
 * TABEL INI DIJAGA K32. Service ini ada di daftar izin `ResolverSatuPintuTest` karena ia
 * MENULIS salinan tingkat level, bukan membacanya untuk menentukan aturan efektif satu
 * orang — untuk itu satu-satunya pintu tetap `PaParticipantRuleResolver`.
 */
class PaCyclePillarWeightService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaBobotGuard $bobot,
        private readonly PaCycleSetupStepService $langkah,
    ) {}

    /**
     * Bobot pilar seluruh level pada satu siklus, beserta dampak penyuntingannya.
     *
     * Dampaknya ikut dikirim supaya layar bisa memperingatkan SEBELUM orang menyunting,
     * bukan menolak sesudahnya. Penolakan yang baru muncul setelah tombol simpan ditekan
     * adalah cara paling melelahkan untuk menyampaikan aturan.
     *
     * @return array<string, mixed>
     */
    public function daftar(int $cycleId): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Status_Cycle']);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        $terbit = $this->hasilPerLevel($cycleId, [PaResultPublishService::STATUS_TERBIT]);
        $dihitung = $this->hasilPerLevel($cycleId, ['DIHITUNG']);

        $baris = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot as s')
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', 's.ID_Level')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->orderBy('s.ID_Level')
            ->get([
                's.Id_PA_Cycle_Pillar_Weight_Snapshot', 's.ID_Level', 'l.Nama_Level as Nama_Level',
                's.Bobot_KPI', 's.Bobot_Kompetensi', 's.Alasan_Perubahan',
                's.Updated_At', 's.Updated_By',
            ])
            ->map(function ($b) use ($terbit, $dihitung) {
                $level = (int) $b->ID_Level;
                $jumlahTerbit = $terbit[$level] ?? 0;

                return (array) $b + [
                    'hasil_terbit' => $jumlahTerbit,
                    'hasil_dihitung' => $dihitung[$level] ?? 0,
                    // Dikirim sebagai keadaan, bukan disimpulkan layar dari jumlah di
                    // atas: aturannya milik server, dan layar yang menyimpulkannya sendiri
                    // akan berselisih begitu aturannya berubah.
                    'dapat_disunting' => $jumlahTerbit === 0,
                    'alasan_terkunci' => $jumlahTerbit === 0
                        ? null
                        : "Ada {$jumlahTerbit} hasil yang sudah terbit pada level ini; "
                            .'bobotnya tidak bisa diubah lagi karena angka yang sudah dilihat '
                            .'orang tidak dihitung ulang.',
                ];
            })
            ->values()
            ->all();

        return [
            'siklus' => (array) $siklus,
            /* Tingkat SIKLUS hanya bicara soal gerbang statusnya. Penguncian karena hasil
               terbit bersifat per level, dan sudah dinyatakan di tiap baris lewat
               `dapat_disunting`. Menjadikannya se-siklus di sini membuat formulir mati
               seluruhnya begitu satu level menerbitkan, sementara baris-barisnya masih
               berkata boleh disunting — dua pernyataan berlawanan di satu layar. */
            'boleh_sunting' => $this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $cycleId),

            /* Sebabnya dikirim, bukan disimpulkan layar. Formulir yang mati tanpa keterangan
               memaksa admin menebak apakah ia kurang hak atau memang terlambat. */
            'alasan_terkunci' => $this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $cycleId)
                ? null
                : 'Status siklusnya tidak lagi mengizinkan penyuntingan bobot pilar.',
            'bobot' => $baris,
        ];
    }

    /**
     * Menyunting bobot pilar satu level.
     *
     * @param  array{bobot_kpi: float|int|string, bobot_kompetensi: float|int|string,
     *               alasan?: string|null}  $nilai
     * @return array<string, mixed>
     */
    public function simpan(int $cycleId, int $levelId, array $nilai, int $olehUserId): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_BOBOT_PILAR, $cycleId);
        $this->pastikanBelumAdaYangTerbit($cycleId, $levelId);

        $baris = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level', $levelId)
            ->first();

        if ($baris === null) {
            throw new RuntimeException(
                "Siklus {$cycleId} belum punya salinan bobot pilar untuk level {$levelId}. "
                .'Salinannya lahir di langkah Aturan Penilai, saat aturan disalin dari master. '
                .'Buka langkah itu lebih dulu, lalu tekan Salin dari master.'
            );
        }

        $kpi = round((float) $nilai['bobot_kpi'], 2);
        $kompetensi = round((float) $nilai['bobot_kompetensi'], 2);

        /* Dipakai penjaga yang sama dengan bobot penilai, bukan penjumlahan sendiri di
           sini. Dua tempat yang memeriksa "harus 100" dengan toleransi pembulatan yang
           berbeda akan berselisih pada angka seperti 33,33 — dan yang menolak akan
           tampak sewenang-wenang. */
        $this->bobot->pastikanSeratus(
            ['KPI' => $kpi, 'KOMPETENSI' => $kompetensi],
            sprintf('bobot pilar level %d pada siklus %d', $levelId, $cycleId),
        );

        if ($kpi < 0 || $kompetensi < 0) {
            throw new RuntimeException('Bobot pilar tidak boleh negatif.');
        }

        $terbit = $this->cacahHasil($cycleId, $levelId, [PaResultPublishService::STATUS_TERBIT]);

        if ($terbit > 0) {
            throw new RuntimeException(
                "Ada {$terbit} hasil yang sudah TERBIT pada level ini, dan hasil terbit tidak "
                .'dihitung ulang. Mengubah bobotnya sekarang membuat angka yang sudah dilihat '
                .'orang tidak akan pernah cocok dengan bobot yang tertulis di siklus ini. '
                .'Kalau hasilnya memang perlu digeser, pakai kalibrasi — kalibrasi mencatat '
                .'sebab, pelaku, dan nilai sebelumnya.'
            );
        }

        $berubah = (float) $baris->Bobot_KPI !== $kpi
            || (float) $baris->Bobot_Kompetensi !== $kompetensi;

        $alasan = trim((string) ($nilai['alasan'] ?? ''));

        /* Alasan wajib begitu siklusnya sudah dikunci. Sebelum itu bobot masih bagian
           dari penyiapan dan menuntut alasan hanya menambah gesekan pada pekerjaan yang
           memang belum jadi. Sesudah kunci, perubahannya menggeser nilai orang, dan
           enam bulan kemudian tidak ada yang ingat kenapa. */
        $sesudahKunci = ! in_array($this->statusSiklus($cycleId), ['DRAFT', 'PREPARED'], true);

        if ($berubah && $sesudahKunci && $alasan === '') {
            throw new RuntimeException(
                'Siklus ini sudah dikunci, sehingga perubahan bobot pilar wajib menyertakan '
                .'alasan. Perubahannya menggeser nilai akhir setiap peserta pada level ini.'
            );
        }

        $basi = $this->cacahHasil($cycleId, $levelId, ['DIHITUNG']);

        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('Id_PA_Cycle_Pillar_Weight_Snapshot', $baris->Id_PA_Cycle_Pillar_Weight_Snapshot)
            ->update([
                'Bobot_KPI' => $kpi,
                'Bobot_Kompetensi' => $kompetensi,
                'Alasan_Perubahan' => $alasan === '' ? $baris->Alasan_Perubahan : $alasan,
                'Updated_At' => now()->format('Y-m-d H:i:s'),
                'Updated_By' => $olehUserId,
            ]);

        /* Ditandai dari keadaan SEBENARNYA seluruh level, bukan dari "satu level barusan
           tersimpan". Menandainya selesai karena satu level beres membuat penyiapan
           berjalan dengan level lain yang bobotnya masih kosong. */
        $this->tandaiLangkah($cycleId, $olehUserId);

        return [
            'cycle_id' => $cycleId,
            'id_level' => $levelId,
            'bobot_kpi' => $kpi,
            'bobot_kompetensi' => $kompetensi,
            'berubah' => $berubah,
            /* Bukan basa-basi. Baris DIHITUNG lahir dari bobot yang lama; membiarkannya
               berarti admin bisa menerbitkan angka yang tidak lagi sesuai bobot siklusnya.
               Angkanya dilaporkan supaya layar bisa menawarkan hitung ulang, bukan
               berharap admin ingat sendiri. */
            'hasil_perlu_hitung_ulang' => $berubah ? $basi : 0,
            'pesan' => $berubah && $basi > 0
                ? "Bobot tersimpan. {$basi} hasil pada level ini dihitung dengan bobot lama "
                    .'dan perlu dihitung ulang sebelum diterbitkan.'
                : 'Bobot tersimpan.',
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    private function statusSiklus(int $cycleId): string
    {
        return (string) DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->value('Status_Cycle');
    }

    /**
     * @param  list<string>  $status
     */
    private function cacahHasil(int $cycleId, int $levelId, array $status): int
    {
        return (int) DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.ID_Level_Saat_Cycle', $levelId)
            ->where('r.Flag_Aktif', 'Y')
            ->whereIn('r.Status_Hasil', $status)
            ->count();
    }

    /**
     * Cacah hasil per level dalam SATU kueri.
     *
     * Menghitungnya per baris pada `daftar()` berarti dua kueri dikali jumlah level —
     * tidak terasa pada enam level, dan tetap salah bentuknya.
     *
     * @param  list<string>  $status
     * @return array<int, int>
     */
    private function hasilPerLevel(int $cycleId, array $status): array
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->whereIn('r.Status_Hasil', $status)
            /* selectRaw dengan alias, bukan pluck(DB::raw('COUNT(*)'), ...). Yang kedua
               menghasilkan kolom bernama harfiah "COUNT(*)" yang tidak pernah cocok
               dengan kunci yang dicari, sehingga petanya KOSONG - dan peta kosong di sini
               terbaca sebagai "nol hasil terbit", yaitu "boleh disunting". Kegagalannya
               tidak melempar apa pun, hanya melaporkan angka yang meyakinkan dan salah. */
            ->groupBy('p.ID_Level_Saat_Cycle')
            ->selectRaw('p.ID_Level_Saat_Cycle as id_level, COUNT(*) as jumlah')
            ->pluck('jumlah', 'id_level')
            ->mapWithKeys(fn ($n, $lv) => [(int) $lv => (int) $n])
            ->all();
    }

    /**
     * Menandai langkah BOBOT_PILAR sesuai keadaan seluruh level siklus.
     *
     * Selesai hanya bila setiap level yang punya salinan sudah berjumlah 100. Level yang
     * belum punya salinan sama sekali TIDAK dihitung di sini — itu urusan langkah aturan
     * penilai, yang membentuk salinannya.
     */
    private function tandaiLangkah(int $cycleId, ?int $olehUserId): void
    {
        $baris = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->get(['ID_Level', 'Bobot_KPI', 'Bobot_Kompetensi']);

        if ($baris->isEmpty()) {
            return;
        }

        $belumGenap = $baris->filter(
            fn ($b) => abs(((float) $b->Bobot_KPI + (float) $b->Bobot_Kompetensi) - 100.0) > 0.01
        )->count();

        $this->langkah->tandai(
            $cycleId,
            'BOBOT_PILAR',
            $belumGenap === 0
                ? PaCycleSetupStepService::STATUS_SELESAI
                : PaCycleSetupStepService::STATUS_DRAFT,
            $belumGenap === 0
                ? sprintf('%d level, bobot genap 100 semua.', $baris->count())
                : sprintf('%d dari %d level bobotnya belum genap 100.', $belumGenap, $baris->count()),
            $olehUserId,
        );
    }

    /**
     | BOBOT PILAR BEKU BEGITU SATU ORANG TERBIT.
     |
     | Sampai 5 September 2026 hasil hanya bisa diterbitkan massal sesudah siklus ditutup,
     | sehingga "menyunting bobot lalu menerbitkan" selalu berurutan dan tidak pernah
     | bertabrakan. Sejak penerbitan menjadi PER ORANG, urutan itu tidak lagi dijamin.
     |
     | `Bobot_KPI_Terpakai` dan `Bobot_Kompetensi_Terpakai` disimpan PER BARIS HASIL, diambil
     | saat perhitungan. Kalau seseorang sudah terbit pada 75/25 lalu bobotnya digeser ke
     | 65/35, satu siklus akan memuat orang-orang yang dinilai dengan aturan berbeda — dan
     | tidak ada satu kolom pun yang mengatakannya. Kerusakan seperti itu tidak menimbulkan
     | galat; ia baru terlihat setahun kemudian ketika dua orang dibandingkan dan angkanya
     | tidak masuk akal, saat tidak seorang pun ingat bobotnya pernah diubah.
     |
     | Yang dijaga adalah TERBIT, bukan DIHITUNG. Hasil yang baru dihitung masih boleh
     | berubah — menghitung ulang sesudah bobot bergeser justru pekerjaan yang benar. Yang
     | tidak bisa ditarik kembali adalah angka yang sudah dibaca karyawan.
     */
    /**
     * Berapa hasil sudah TERBIT — per LEVEL bila levelnya disebut.
     *
     * Bobot pilar disimpan PER LEVEL, jadi penguncinya pun harus per level. Versi
     * pertama menghitung se-siklus, sehingga satu hasil terbit di level mana pun
     * membekukan bobot SELURUH level — dan pesannya tidak menyebut level sama sekali,
     * jadi admin yang menyunting level yang belum menerbitkan apa pun ditolak tanpa
     * petunjuk kenapa. Daftar di layar bahkan menyatakan sebaliknya: barisnya sendiri
     * berkata `dapat_disunting`, karena ia memakai hitungan per level yang benar.
     */
    private function jumlahTerbit(int $cycleId, ?int $levelId = null): int
    {
        return (int) DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->where('r.Status_Hasil', PaResultCalculationService::STATUS_TERBIT)
            ->when($levelId !== null, fn ($q) => $q->where('p.ID_Level_Saat_Cycle', $levelId))
            ->count();
    }

    private function alasanBobotTerkunci(int $cycleId, ?int $levelId = null): ?string
    {
        if (! $this->gate->boleh(PaCycleGate::SUNTING_BOBOT_PILAR, $cycleId)) {
            return 'Status siklusnya tidak lagi mengizinkan penyuntingan bobot pilar.';
        }

        $terbit = $this->jumlahTerbit($cycleId, $levelId);

        if ($terbit === 0) {
            return null;
        }

        /* Menolak tanpa menyebut jalan keluarnya membuat orang buntu: ia tahu tidak boleh,
           tidak tahu harus apa. Kalibrasi adalah jalur yang memang disediakan untuk
           menggeser hasil yang sudah terbit — dengan sebab dan jejak, yang justru tidak
           dimiliki penyuntingan bobot. */
        return sprintf(
            '%d hasil%s sudah TERBIT dan memakai pembagian bobot yang berlaku saat itu. '
            .'Mengubahnya sekarang membuat orang yang sudah terbit dan yang belum dinilai '
            .'dengan aturan yang berbeda, tanpa satu pun catatan yang mengatakannya. Bila '
            .'angka seseorang memang harus digeser, pakai kalibrasi — di sana perubahannya '
            .'bersebab dan berjejak.',
            $terbit,
            $levelId === null ? ' di siklus ini' : ' pada level ini',
        );
    }

    private function pastikanBelumAdaYangTerbit(int $cycleId, ?int $levelId = null): void
    {
        $alasan = $this->alasanBobotTerkunci($cycleId, $levelId);

        if ($alasan !== null && $this->jumlahTerbit($cycleId, $levelId) > 0) {
            throw new RuntimeException($alasan);
        }
    }
}
