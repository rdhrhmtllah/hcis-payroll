<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Jalur tulis pertama modul PA: mengubah siklus kosong menjadi siklus yang siap dikunci.
 *
 * Tiga pekerjaan, berurutan dan tidak boleh ditukar:
 *
 *   1. Menyalin cetak biru menjadi salinan siklus, per level yang benar-benar dipakai.
 *   2. Membentuk baris peserta beserta kondisi organisasi yang dibekukan.
 *   3. Membentuk baris hasil berstatus BELUM_DIHITUNG untuk tiap peserta.
 *
 * SEMUA PENULISAN LEWAT DUA PENJAGA
 *
 * `PaCycleGate` memutuskan apakah operasinya masih boleh pada status siklus sekarang,
 * dan `PaBobotGuard` memastikan bobot yang disalin memang berjumlah 100. Keduanya
 * dipanggil di sini, bukan ditulis ulang — dua penjaga yang sama akan berbeda
 * perilakunya dalam hitungan bulan.
 *
 * KETIADAAN BARIS TIDAK BOLEH JADI JAWABAN
 *
 * Karyawan yang tidak layak dinilai tetap mendapat baris peserta, berstatus
 * DIKECUALIKAN beserta alasannya. Kalau mereka sekadar tidak dibuatkan baris,
 * "tidak ada di daftar" jadi bermakna ganda — belum disiapkan, tidak layak, atau
 * terlewat karena bug. Dengan barisnya selalu ada, statusnya yang menjawab.
 *
 * Sifatnya idempoten: menjalankan ulang tidak menggandakan apa pun.
 */
class PaCyclePreparationService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaBobotGuard $bobot,
        private readonly PaCycleSetupStepService $langkah,

        /* Dipakai menurunkan tenggat ke penugasan yang lahir sebelum jendela pengisiannya
           terisi. Tidak melingkar: pembentuk penugasan tidak mengenal layanan ini. */
        private readonly PaAssignmentBuilderService $penugasan,

        /* Satu-satunya penulis jendela pengisian. Didelegasikan supaya aturan "status mana yang
           boleh mengubahnya" hanya hidup di satu tempat. */
        private readonly PaCycleSetupService $setup,

        /* Kesiapan karyawan di HC Learn. Dipakai HANYA saat mengunci — satu tindakan sadar,
           satu perjalanan ke sistem luar — bukan pada tiap pemuatan layar. */
        private readonly PaHcLearnKaryawanSyncService $karyawanHcLearn,

        /* Kekurangan penilai yang belum diputuskan menahan penguncian (13 Okt 2026). */
        private readonly PaAssignmentHealthService $kesehatan,
    ) {}

    /**
     * Menyiapkan satu siklus dari awal sampai siap dikunci.
     *
     * @return array{salinan: array<string,int>, peserta: array<string,int>}
     */
    public function siapkan(int $cycleId, ?int $olehUserId = null): array
    {
        /* SENGAJA TIDAK memeriksa penanda langkah `N_PA_Cycle_Setup_Step`.

           Penanda itu mencatat sejauh mana admin berjalan di layar, BUKAN apakah datanya
           lengkap. Siklus yang disusun lewat jalur lain — pemulihan, uji, penyalinan —
           punya setelan yang sah tanpa satu pun penanda, dan menolaknya berarti menolak
           pekerjaan yang benar karena layarnya tidak dilewati.

           Yang menjaga kebenaran tetap di tempatnya masing-masing: bobot wajib 100
           diperiksa saat disimpan, level tanpa aturan mendaratkan pesertanya di TERTUNDA,
           dan `kunci()` menolak selama keranjang itu belum bersih. */
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_SALINAN, $cycleId);

        $hasil = DB::transaction(function () use ($cycleId) {
            /* Baris siklus dikunci SEPANJANG penyiapan. Pengubah minimal masa kerja mengunci
               baris yang sama lalu menolak bila peserta sudah ada; tanpa kunci ini angkanya
               bisa berganti di antara saat dibaca dan saat peserta ditulis, sehingga siklus
               menyebut 6 bulan padahal pesertanya dinilai dengan 3. */
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->lockForUpdate()->value('Id_PA_Cycle');

            $level = $this->levelYangDipakai($cycleId);

            $salinan = $this->salinAturan($cycleId, $level);
            $peserta = $this->bentukPeserta($cycleId, $this->batasResign($cycleId));
            $this->bentukBarisHasil($cycleId);

            DB::table('N_PA_Cycle')
                ->where('Id_PA_Cycle', $cycleId)
                ->where('Status_Cycle', 'DRAFT')
                ->update([
                    'Status_Cycle' => 'PREPARED',
                    'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                ]);

            return ['salinan' => $salinan, 'peserta' => $peserta];
        });

        $this->tandaiLangkahPeserta($cycleId, $olehUserId);

        /* Memo gerbang DIBUANG sesudah status berubah.
           `PaCycleGate` memo status per siklus dan tidak terdaftar singleton, jadi memonya
           hidup selama instance-nya hidup — yaitu sepanjang satu permintaan. Tanpa baris
           ini, gerbang mana pun yang diperiksa SESUDAH `siapkan()` dalam permintaan yang
           sama masih melihat `DRAFT`.

           Selama ini laten: tak ada yang memeriksa gerbang lagi sesudah penyiapan. Begitu
           `kunci()` diberi gerbang pada 2 September 2026, ia langsung patah — menolak
           siklus yang baru saja disiapkan dengan alasan "berstatus DRAFT". Setiap
           perpindahan status wajib diikuti baris ini. */
        $this->gate->lupakanMemo();

        return $hasil;
    }

    /**
     * PRATINJAU penyiapan — apa yang AKAN terjadi bila "Siapkan" ditekan. Tanpa menulis.
     *
     * KENAPA ADA
     *
     * Sampai 3 September 2026 "Siapkan" langsung membentuk peserta untuk seluruh karyawan
     * aktif — 958 baris sekaligus — dan admin baru tahu hasilnya sesudah semuanya
     * terlanjur ada. Kalau ternyata satu level belum punya aturan penilai, puluhan orang
     * mendarat di keranjang TERTUNDA dan siklus tidak bisa dikunci; memperbaikinya berarti
     * bekerja di atas baris yang sudah telanjur terbentuk.
     *
     * Pratinjau membalik urutannya: lihat dulu, baru putuskan.
     *
     * MEMAKAI KEPUTUSAN YANG SAMA, BUKAN MENYALINNYA
     *
     * Kelayakan tiap orang dinilai `nilaiKelayakan()` — method yang sama persis yang
     * dipakai penyiapan sungguhan. Menyalin aturannya ke sini akan melahirkan pratinjau
     * yang suatu hari berselisih dengan kenyataan, dan pratinjau yang berbohong lebih
     * berbahaya daripada tidak ada pratinjau: orang mengambil keputusan berdasarkannya.
     *
     * Satu hal memang harus DIRAMALKAN: salinan aturan per level belum ada sebelum
     * penyiapan berjalan. Yang dipakai adalah aturan master yang AKAN disalin — yaitu
     * `N_PA_Rater_Policy` ber-`Status_Policy = ACTIVE` untuk level yang benar-benar dipakai
     * karyawan aktif. Itu persis yang `salinAturan()` lakukan.
     *
     * @return array<string, mixed>
     */
    public function pratinjauPenyiapan(int $cycleId): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_SALINAN, $cycleId);

        $batasResign = $this->batasResign($cycleId);
        $akanPunyaAturan = $this->levelYangAkanPunyaAturan($cycleId);

        /* Pratinjau membaca daftar yang SAMA. Kalau ia melewatkannya, angkanya akan
           berselisih dengan penyiapan sungguhan tepat pada orang-orang yang sengaja
           dikecualikan admin — dan itu justru orang yang paling ia perhatikan. */
        $praKecuali = $this->praKecuali($cycleId);
        $minMasaKerja = $this->minMasaKerja($cycleId);

        $sudahAda = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Participant', 'Kode_Perusahaan', 'Kode_Karyawan', 'ID_Level_Saat_Cycle', 'PA_Karyawan_Level_PA_Id', 'Updated_By', 'Status_Peserta'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan => $r])
            ->all();

        /* Sesudah penyiapan pertama, pratinjau juga menjawab "apa yang berubah bila dijalankan
           ULANG": peserta yang Level PA-nya bergeser akan disegarkan — kecuali yang tertahan
           (sudah berpenugasan / punya penyesuaian), dengan pemeriksaan yang sama persis. */
        $snapshotPerLevel = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot', 'ID_Level')
            ->map(fn ($x) => (int) $x)
            ->all();
        $levelBerubah = 0;
        $levelTertahan = 0;

        /* DUA MACAM PENGECUALIAN, DAN HANYA SATU YANG KEPUTUSAN.

           `dikecualikan` menghitung yang namanya ditulis admin di daftar pra-kecuali;
           `dikecualikan_otomatis` menghitung yang gugur karena aturan tetap — level
           FREELANCE dan karyawan yang mundur sebelum periode berakhir.

           Sebelum 7 September 2026 keduanya satu angka. Pada data sungguhan itu berarti
           admin yang mengecualikan DUA orang membaca angka 101, dan yang terbaca olehnya
           bukan "aturan bekerja" melainkan "saya tidak sengaja mengeluarkan 99 orang".
           Pemisahan yang sama sudah berlaku di kartu siklus, dashboard, dan ringkasan
           pra-kunci; pratinjau Langkah 4 satu-satunya yang tertinggal. */
        $cacah = [
            'diikutkan' => 0,
            'dikecualikan' => 0,
            'dikecualikan_otomatis' => 0,
            'tertunda' => 0,
            'sudah_ada' => 0,
        ];
        $sebab = [];
        $perLevelTertunda = [];
        $contoh = ['dikecualikan' => [], 'tertunda' => []];

        foreach ($this->karyawanAktif($cycleId) as $k) {
            if (isset($sudahAda[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan])) {
                $cacah['sudah_ada']++;
                $lama = $sudahAda[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan];

                if (! $this->levelSama($lama, $k)) {
                    $levelBerubah++;
                    if ($this->sebabLevelTertahan((int) $lama->Id_PA_Participant, $lama, $k, $snapshotPerLevel) !== null) {
                        $levelTertahan++;
                    }
                }

                continue;
            }

            [$status, $alasan] = $this->nilaiKelayakan(
                $k, $akanPunyaAturan, $batasResign, $praKecuali, $minMasaKerja
            );

            if ($status === 'DIIKUTKAN') {
                $cacah['diikutkan']++;

                continue;
            }

            if ($status === 'DIKECUALIKAN') {
                /* Pembedanya DAFTAR PRA-KECUALI, bukan teks alasannya. Alasan otomatis
                   berbunyi kalimat tetap, tetapi alasan manual diketik admin sendiri dan
                   bisa berbunyi apa saja — termasuk kebetulan menyerupai yang otomatis. */
                if (isset($praKecuali[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan])) {
                    $cacah['dikecualikan']++;
                } else {
                    $cacah['dikecualikan_otomatis']++;
                }

                /* Dikelompokkan menurut SEBABNYA, bukan didaftar satu per satu. Sembilan
                   puluh sembilan baris "Level FREELANCE dikecualikan" menenggelamkan satu
                   baris yang benar-benar perlu dilihat. Sebab resign memuat tanggal, jadi
                   dipangkas di " pada " supaya tetap mengelompok jadi satu. */
                $kunci = explode(' pada ', (string) $alasan)[0];
                $sebab[$kunci] = ($sebab[$kunci] ?? 0) + 1;

                if (count($contoh['dikecualikan']) < 10) {
                    $contoh['dikecualikan'][] = [
                        'kode_karyawan' => $k->Kode_Karyawan,
                        'nama' => $k->Nama,
                        'sebab' => $alasan,
                    ];
                }

                continue;
            }

            // TERTUNDA — inilah yang paling perlu dilihat SEBELUM menekan Siapkan.
            $cacah['tertunda']++;

            $level = $k->ID_Level === null ? 'tanpa level' : (string) $k->ID_Level;
            $perLevelTertunda[$level] = ($perLevelTertunda[$level] ?? 0) + 1;

            if (count($contoh['tertunda']) < 10) {
                $contoh['tertunda'][] = [
                    'kode_karyawan' => $k->Kode_Karyawan,
                    'nama' => $k->Nama,
                    'id_level' => $k->ID_Level,
                ];
            }
        }

        arsort($sebab);
        arsort($perLevelTertunda);

        return [
            'cycle_id' => $cycleId,
            'cacah' => $cacah,
            'sebab_dikecualikan' => $sebab,
            'tertunda_per_level' => $perLevelTertunda,
            'level_tanpa_aturan' => $this->levelTanpaAturan($cycleId),
            // Berapa yang dikecualikan karena DAFTAR ADMIN, bukan karena aturan otomatis.
            // Dibedakan supaya admin bisa memastikan daftarnya memang terpakai.
            'pra_kecuali_terpakai' => count($praKecuali),
            'contoh' => $contoh,
            ...$this->keadaanPenyiapan($cycleId, $cacah, $levelBerubah, $levelTertahan),
            /* Dinyatakan terang-terangan supaya layar tidak perlu menebak. Pratinjau yang
               tidak mengatakan dirinya pratinjau akan disangka hasil. */
            'ini_pratinjau' => true,
            'catatan' => $cacah['tertunda'] > 0
                ? 'Ada peserta yang akan mendarat di keranjang TERTUNDA. Siklus tidak bisa '
                  .'dikunci selama keranjang itu terisi — lengkapi aturan penilai levelnya '
                  .'lebih dulu, atau siapkan sekarang lalu kecualikan mereka satu per satu.'
                : 'Tidak ada yang akan tertunda. Penyiapan bisa dijalankan.',
        ];
    }

    /**
     * APA YANG BERUBAH SEJAK PESERTA (DAN PENUGASANNYA) DIBENTUK. Hanya membaca.
     *
     * Menjalankan ulang penyiapan tidak pernah menyentuh penugasan yang sudah ada, jadi ada
     * empat keadaan yang perlu ditindaklanjuti admin di Langkah 5 — dan tanpa daftar ini
     * keempatnya tidak terlihat di mana pun:
     *  - karyawan baru / Level PA bergeser yang BELUM diterapkan (jalankan ulang Langkah 4);
     *  - peserta yang belum berpenugasan (lengkapi penugasan);
     *  - peserta yang Level PA-nya berubah tetapi TERTAHAN karena sudah berpenugasan;
     *  - peserta diikutkan yang kini resign / tidak aktif di HRIS tetapi masih berpenugasan.
     *
     * @return array<string, mixed>
     */
    public function perubahanPeserta(int $cycleId): array
    {
        $status = (string) DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Status_Cycle');
        $batasResign = $this->batasResign($cycleId);

        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Participant', 'Kode_Perusahaan', 'Kode_Karyawan', 'Nama_Karyawan_Saat_Cycle', 'ID_Level_Saat_Cycle',
                'Nama_Level_Saat_Cycle', 'PA_Karyawan_Level_PA_Id', 'Updated_By', 'Status_Peserta', 'Alasan_Dikecualikan'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan => $r])
            ->all();

        $snapshotPerLevel = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot', 'ID_Level')
            ->map(fn ($x) => (int) $x)
            ->all();

        $aktif = [];
        $baru = 0;
        $levelBebas = 0;
        $tertahan = [];
        $resign = [];

        foreach ($this->karyawanAktif($cycleId) as $k) {
            $kunci = $k->Kode_Perusahaan.'|'.$k->Kode_Karyawan;
            $aktif[$kunci] = true;
            $lama = $peserta[$kunci] ?? null;

            if ($lama === null) {
                $baru++;

                continue;
            }

            if (! $this->levelSama($lama, $k)) {
                $sebab = $this->sebabLevelTertahan((int) $lama->Id_PA_Participant, $lama, $k, $snapshotPerLevel);

                if ($sebab === null) {
                    $levelBebas++;
                } else {
                    $tertahan[] = [
                        'participant_id' => (int) $lama->Id_PA_Participant,
                        'nama' => $k->Nama,
                        'kode_karyawan' => $k->Kode_Karyawan,
                        'nama_level_sekarang' => $lama->Nama_Level_Saat_Cycle,
                        'nama_level_seharusnya' => $k->nama_level,
                        'sebab' => $sebab,
                        /* Bisa diselesaikan dengan satu tombol HANYA bila satu-satunya sebabnya
                           penugasan yang belum diisi siapa pun. */
                        'sebab_lain' => $this->sebabLevelTertahan((int) $lama->Id_PA_Participant, $lama, $k, $snapshotPerLevel, true),
                    ];
                }
            }

            if ($lama->Status_Peserta === 'DIIKUTKAN' && $batasResign !== null && $k->Tanggal_Resign !== null
                && substr((string) $k->Tanggal_Resign, 0, 10) <= $batasResign) {
                $resign[] = [
                    'participant_id' => (int) $lama->Id_PA_Participant,
                    'kode_perusahaan' => $lama->Kode_Perusahaan,
                    'kode_karyawan' => $lama->Kode_Karyawan,
                    'nama' => $lama->Nama_Karyawan_Saat_Cycle,
                    'keterangan' => 'Mengundurkan diri pada '.substr((string) $k->Tanggal_Resign, 0, 10),
                ];
            }
        }

        foreach ($peserta as $kunci => $p) {
            if (! isset($aktif[$kunci]) && $p->Status_Peserta === 'DIIKUTKAN') {
                $resign[] = [
                    'participant_id' => (int) $p->Id_PA_Participant,
                    'kode_perusahaan' => $p->Kode_Perusahaan,
                    'kode_karyawan' => $p->Kode_Karyawan,
                    'nama' => $p->Nama_Karyawan_Saat_Cycle,
                    'keterangan' => 'Tidak aktif lagi di HRIS',
                ];
            }
        }

        $cacah = $this->cacahPenugasanPeserta(array_merge(array_column($tertahan, 'participant_id'), array_column($resign, 'participant_id')));
        $isi = fn (array $baris) => array_map(fn ($b) => $b + [
            'penugasan_aktif' => $cacah[$b['participant_id']]['aktif'] ?? 0,
            'penugasan_diisi' => $cacah[$b['participant_id']]['diisi'] ?? 0,
        ], $baris);
        $tertahan = array_map(function ($t) {
            $t['bisa_disegarkan'] = $t['sebab_lain'] === null && $t['penugasan_diisi'] === 0;
            unset($t['sebab_lain']);

            return $t;
        }, $isi($tertahan));

        $adaPenugasan = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->exists();

        return [
            'cycle_id' => $cycleId,
            'dapat_ditindak' => in_array($status, ['DRAFT', 'PREPARED'], true),
            'ada_penugasan' => $adaPenugasan,
            'belum_berpenugasan' => $this->penugasan->pesertaTanpaPenugasan($cycleId),
            'belum_diterapkan' => ['karyawan_baru' => $baru, 'level_berubah' => $levelBebas],
            'level_tertahan' => $tertahan,
            'resign' => $isi($resign),
        ];
    }

    /**
     * Membatalkan penugasan (yang belum diisi siapa pun) milik peserta yang Level PA-nya
     * tertahan, lalu menyegarkan levelnya — pekerjaan tiga langkah (batalkan, siapkan ulang,
     * bentuk lagi) yang dulu harus dirangkai admin sendiri. Penugasan untuk level barunya
     * dibentuk sesudahnya lewat "Lengkapi penugasan".
     *
     * Yang tertahan karena sebab lain (penugasan sudah diisi, penyesuaian aturan per orang,
     * diikutkan manual) DILEWATI dan disebut sebabnya — keputusan itu milik admin.
     *
     * @param  list<int>  $participantIds
     * @return array{disegarkan:int, penugasan_dibatalkan:int, dilewati:list<string>}
     */
    public function segarkanLevelTertahan(int $cycleId, array $participantIds, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_SALINAN, $cycleId);
        $this->gate->pastikanBoleh(PaCycleGate::BUAT_PENUGASAN, $cycleId);

        $daftar = collect($this->perubahanPeserta($cycleId)['level_tertahan'])->keyBy('participant_id');
        $hasil = ['disegarkan' => 0, 'penugasan_dibatalkan' => 0, 'dilewati' => []];
        $siap = [];

        foreach (array_unique(array_map('intval', $participantIds)) as $pid) {
            $t = $daftar->get($pid);
            if ($t === null) {
                $hasil['dilewati'][] = "Peserta {$pid} tidak sedang tertahan Level PA.";

                continue;
            }
            if (! $t['bisa_disegarkan']) {
                $hasil['dilewati'][] = "{$t['nama']}: {$t['sebab']}";

                continue;
            }

            $ids = DB::table('N_PA_Assignment')
                ->where('PA_Participant_Id', $pid)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_PA_Assignment');
            foreach ($ids as $aid) {
                $this->penugasan->batalkan((int) $aid, 'Level PA berubah ke '.($t['nama_level_seharusnya'] ?? 'level baru')
                    .'; penugasan dibentuk ulang sesuai level baru.', $olehUserId);
                $hasil['penugasan_dibatalkan']++;
            }
            $siap[$pid] = true;
        }

        if ($siap !== []) {
            $hasil['disegarkan'] = $this->segarkanLevelUntuk($cycleId, array_keys($siap));
        }

        return $hasil;
    }

    /** Menyegarkan Level PA peserta tertentu dengan aturan yang sama dengan penyiapan. */
    private function segarkanLevelUntuk(int $cycleId, array $participantIds): int
    {
        $lama = DB::table('N_PA_Participant')
            ->whereIn('Id_PA_Participant', $participantIds)
            ->where('PA_Cycle_Id', $cycleId)
            ->get(['Id_PA_Participant', 'Kode_Perusahaan', 'Kode_Karyawan', 'ID_Level_Saat_Cycle', 'PA_Karyawan_Level_PA_Id', 'Updated_By', 'Status_Peserta', 'Alasan_Dikecualikan'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan => $r])
            ->all();
        $snapshotPerLevel = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot', 'ID_Level')
            ->map(fn ($x) => (int) $x)
            ->all();
        $batasResign = $this->batasResign($cycleId);
        $praKecuali = $this->praKecuali($cycleId);
        $minMasaKerja = $this->minMasaKerja($cycleId);
        $n = 0;

        foreach ($this->karyawanAktif($cycleId) as $k) {
            $p = $lama[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan] ?? null;
            if ($p !== null && ! $this->levelSama($p, $k)
                && $this->sebabLevelTertahan((int) $p->Id_PA_Participant, $p, $k, $snapshotPerLevel) === null) {
                $this->segarkanLevelPeserta($p, $k, $snapshotPerLevel, $batasResign, $praKecuali, $minMasaKerja);
                $n++;
            }
        }

        return $n;
    }

    /** @return array<int, array{aktif:int, diisi:int}> */
    private function cacahPenugasanPeserta(array $participantIds): array
    {
        if ($participantIds === []) {
            return [];
        }

        $diisi = "'".implode("','", PaStatusPenugasan::SUDAH_DIKERJAKAN)."'";

        return DB::table('N_PA_Assignment')
            ->whereIn('PA_Participant_Id', array_values(array_unique($participantIds)))
            ->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Participant_Id')
            ->selectRaw("PA_Participant_Id as id, COUNT(*) as aktif, SUM(CASE WHEN Status_Penugasan IN ({$diisi}) THEN 1 ELSE 0 END) as diisi")
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->id => ['aktif' => (int) $r->aktif, 'diisi' => (int) $r->diisi]])
            ->all();
    }

    /**
     * Apakah penyiapan sudah pernah dijalankan, hasil nyatanya, dan apa yang berubah bila
     * dijalankan ulang. Tanpa ini Langkah 4 terus bertanya "Siap menjalankan?" pada siklus
     * yang pesertanya sudah terbentuk, dan admin tidak tahu apakah menekannya lagi berguna.
     *
     * @param  array<string, int>  $cacah  cacah pratinjau (orang yang BELUM jadi peserta)
     * @return array<string, mixed>
     */
    private function keadaanPenyiapan(int $cycleId, array $cacah, int $levelBerubah, int $levelTertahan): array
    {
        $nyata = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Status_Peserta')
            ->selectRaw('Status_Peserta as s, COUNT(*) as n')
            ->pluck('n', 's');
        $total = (int) $nyata->sum();

        $langkah = DB::table('N_PA_Cycle_Setup_Step')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Langkah', 'PESERTA')
            ->first(['Disimpan_At', 'Disimpan_By']);
        $oleh = null;
        if ($langkah?->Disimpan_By !== null) {
            $u = DB::table('KPI_Users')->where('Id_Users', (int) $langkah->Disimpan_By)->first(['Nama', 'Username']);
            $oleh = $u === null ? null : (trim((string) ($u->Nama ?? '')) ?: (string) $u->Username);
        }

        return [
            'sudah_dijalankan' => $total > 0,
            'terbentuk' => [
                'total' => $total,
                'diikutkan' => (int) ($nyata['DIIKUTKAN'] ?? 0),
                'dikecualikan' => (int) ($nyata['DIKECUALIKAN'] ?? 0),
                'tertunda' => (int) ($nyata['TERTUNDA'] ?? 0),
            ],
            'terakhir_dijalankan' => $total > 0 && $langkah?->Disimpan_At !== null
                ? ['pada' => (string) $langkah->Disimpan_At, 'oleh' => $oleh]
                : null,
            'bila_diulang' => [
                'peserta_baru' => $cacah['diikutkan'] + $cacah['dikecualikan'] + $cacah['dikecualikan_otomatis'] + $cacah['tertunda'],
                'level_berubah' => $levelBerubah,
                'level_tertahan' => $levelTertahan,
            ],
        ];
    }

    /**
     * Level yang AKAN punya salinan aturan sesudah penyiapan berjalan.
     *
     * Nilainya sengaja penanda (1), bukan id sungguhan: `nilaiKelayakan()` hanya memeriksa
     * ADA atau tidaknya, dan id salinan memang belum lahir saat pratinjau dijalankan.
     *
     * @return array<int, int>
     */
    private function levelYangAkanPunyaAturan(int $cycleId): array
    {
        $peta = [];

        foreach ($this->levelYangDipakai($cycleId) as $levelId) {
            $ada = DB::table('N_PA_Rater_Policy')
                ->where('ID_Level', $levelId)
                ->where('Status_Policy', 'ACTIVE')
                ->where('Flag_Aktif', 'Y')
                ->exists();

            if ($ada) {
                $peta[$levelId] = 1;
            }
        }

        return $peta;
    }

    /**
     * Level yang dipakai karyawan aktif TETAPI belum punya aturan penilai.
     *
     * Inilah "yang belum tercakup": orangnya sah dinilai dan levelnya ada, tetapi tidak ada
     * aturan yang mengatakan siapa yang menilai mereka. Mereka akan mendarat di TERTUNDA,
     * dan siklus tidak bisa dikunci sampai keranjang itu bersih.
     *
     * @return list<array<string, mixed>>
     */
    /* Publik karena alur penyiapan berlangkah menanyakannya sendiri: langkah "Aturan
       Penilai" perlu menyebut level mana yang belum tercakup TANPA menjalankan pratinjau
       penuh, yang memindai seluruh karyawan aktif untuk jawaban yang tidak ia butuhkan. */
    public function levelTanpaAturan(int $cycleId): array
    {
        $punya = $this->levelYangAkanPunyaAturan($cycleId);
        $hasil = [];

        foreach ($this->levelYangDipakai($cycleId) as $levelId) {
            if (isset($punya[$levelId])) {
                continue;
            }

            $hasil[] = [
                'id_level' => $levelId,
                'nama_level' => DB::table('N_PA_Level')->where('ID_Level', $levelId)->value('Nama_Level'),
                // Level PA (DDL-2026-177): koreksi per karyawan ikut dihitung.
                'jumlah_karyawan' => PaLevelPaResolver::gabung(DB::table('Karyawan as k')
                    ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                        'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'), 'k', 'v', $this->tanggalAcuanLevel($cycleId))
                    ->where('k.Aktif', 'Y')
                    ->where(DB::raw(PaLevelPaResolver::sqlLevel()), $levelId)
                    ->count(),
            ];
        }

        return $hasil;
    }

    /**
     * Gerbang G10. Siklus tidak boleh dikunci selama masih ada peserta di keranjang
     * TERTUNDA — yaitu orang yang levelnya gagal diresolusi atau levelnya tidak punya
     * aturan penilai.
     *
     * Ini satu-satunya alasan keranjang itu ada. Mengunci siklus dengan keranjang
     * terisi berarti menilai orang memakai aturan yang tidak pernah ada.
     */
    /**
     * Apa yang akan dibekukan, dan apa yang masih janggal — SEBELUM tombolnya ditekan.
     *
     * KENAPA ADA
     *
     * Mengunci itu SATU ARAH: gerbangnya tidak punya jalur `LOCKED -> PREPARED`, dan itu
     * keputusan yang disengaja. Satu-satunya pengaman untuk tindakan yang tak bisa dibatalkan
     * adalah melihat akibatnya lebih dulu.
     *
     * Sebelum ini dialog konfirmasinya hanya berbunyi "pastikan seluruh peserta tertunda sudah
     * ditinjau" — menyuruh memastikan tanpa menunjukkan apa pun untuk dipastikan. Tahap
     * peninjauan yang tidak bisa dipakai meninjau bukan pengaman, ia hanya klik tambahan.
     *
     * Angkanya dihitung server supaya layar tidak menyusun aturannya sendiri, dan supaya
     * `penghalang` di sini PERSIS yang ditegakkan `kunci()` sesaat kemudian.
     *
     * @return array{peserta: array<string,int>, penugasan: array<string,int>, penghalang: list<string>, boleh_dikunci: bool}
     */
    /**
     * Sel (level × kategori penilai) yang punya penugasan aktif tetapi tidak tertutup paket
     * soal berujian — paket khusus kategori, atau paket bawaan level (kategori NULL). Aturan
     * yang SAMA dengan penjadwalan HC Learn (`PaHcLearnScheduleService`).
     *
     * @return list<array{level:string, kategori:string}>
     */
    public function selTanpaPaket(int $cycleId): array
    {
        $paket = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->whereNotNull('Id_Master_Ujian')
            ->get(['ID_Level', 'PA_Rater_Category_Id'])
            ->mapWithKeys(fn ($p) => [$p->ID_Level.'|'.$p->PA_Rater_Category_Id => true]);

        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Flag_Aktif', 'Y')->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('a.Flag_Aktif', 'Y')->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->distinct()
            ->get(['p.ID_Level_Saat_Cycle as level_id', 'p.Nama_Level_Saat_Cycle as level', 'a.PA_Rater_Category_Id as kategori_id', 'c.Nama_Kategori as kategori'])
            ->reject(fn ($r) => isset($paket[$r->level_id.'|'.$r->kategori_id]) || isset($paket[$r->level_id.'|']))
            ->map(fn ($r) => ['level' => (string) ($r->level ?? $r->level_id), 'kategori' => (string) $r->kategori])
            ->unique(fn ($x) => $x['level'].'|'.$x['kategori'])
            ->values()
            ->all();
    }

    /** @param list<array{level:string, kategori:string}> $sel */
    private function pesanSelTanpaPaket(array $sel): string
    {
        $contoh = array_map(fn ($x) => "{$x['level']} × {$x['kategori']}", array_slice($sel, 0, 5));

        return sprintf(
            '%d sel penilaian belum terikat paket soal (%s%s). Penilai di sel itu tidak akan mendapat akses HC Learn; lengkapi langkah Paket Soal lebih dulu.',
            count($sel), implode(', ', $contoh), count($sel) > 5 ? ', …' : '',
        );
    }

    /**
     * KEKURANGAN PENILAI YANG BELUM DIPUTUSKAN MENAHAN PENGUNCIAN (UAT 13 Okt 2026).
     *
     * Kategori yang penilainya di bawah minimal gugur saat dihitung, dan porsinya dibagikan
     * ke kategori lain tanpa seorang pun memutuskannya — Andre dinilai tanpa rekan sejawat
     * dan tidak ada yang tahu sampai hasilnya dibuka. Sesudah dikunci, aturannya beku.
     *
     * Jadi sebelum dikunci setiap kekurangan wajib diputuskan: tambah penilainya, atau
     * tandai kategorinya sengaja tidak diikutkan (`PaKategoriTidakDiikutkanService`), yang
     * sudah dikeluarkan dari hitungan kekurangan oleh `PaAssignmentHealthService`.
     *
     * PENILAI YANG SUDAH NONAKTIF DI HRIS JUGA MENAHAN PENGUNCIAN. Sebelumnya hanya "Disarankan". Penahan HC Learn baru menangkapnya bila HC Learn juga
     * sudah tahu orangnya keluar; bila belum, penugasannya lolos, tidak pernah diisi, dan
     * kategorinya gugur di akhir — persis kelas kesalahan yang sama dengan kekurangan penilai.
     *
     * @return array{kurang:?string, nonaktif:?string}
     */
    private function penghalangPenugasan(int $cycleId): array
    {
        if (! config('services.kpi.pa_kunci_tahan_kekurangan', true)) {
            return ['kurang' => null, 'nonaktif' => null];
        }

        $r = $this->kesehatan->ringkasan($cycleId);
        $kurang = (int) ($r['total_peserta_kurang'] ?? 0);
        $nonaktif = (int) ($r['total_penilai_nonaktif'] ?? 0);

        return [
            'kurang' => $kurang === 0 ? null : "{$kurang} peserta masih punya kategori penilai di bawah minimal. Tambah "
                .'penilainya, atau tandai kategori itu "sengaja tidak diikutkan" di Langkah 5 '
                .'supaya keputusan itu tercatat.',
            'nonaktif' => $nonaktif === 0 ? null : "{$nonaktif} penugasan menunjuk penilai yang sudah nonaktif di HRIS. "
                .'Penilai itu tidak akan mengisi; ganti penilainya lewat Kelola Rater.',
        ];
    }

    public function ringkasanPraKunci(int $cycleId): array
    {
        $this->siklusAtauGagal($cycleId);

        $peserta = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->selectRaw('Status_Peserta, COUNT(*) as jumlah')
            ->groupBy('Status_Peserta')
            ->pluck('jumlah', 'Status_Peserta');

        $diikutkan = (int) ($peserta['DIIKUTKAN'] ?? 0);
        $tertunda = (int) ($peserta['TERTUNDA'] ?? 0);

        $berpenilai = DB::table('N_PA_Participant as p')
            ->join('N_PA_Assignment as a', 'a.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')
            ->distinct()
            ->count('p.Id_PA_Participant');

        $totalPenugasan = (int) DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->count();

        /* PINTU YANG SUDAH TERCETAK, dan karena itu berapa yang masih kurang.
           Penguncian bukan satu tindakan melainkan dua: membekukan aturan, lalu mencetak
           pintu bagi ribuan penugasan. Bagian keduanya memakan belasan detik dan bisa
           berhenti di tengah. Tanpa angka ini layar tidak bisa membedakan "belum dikunci"
           dari "sudah dikunci, pintunya yang belum selesai" — dua keadaan yang menuntut
           kalimat berbeda dari tombol yang sama. */
        /* PINTU ADA HANYA BILA `Api_Ref` ADA. Barisnya sendiri bukan pintu.
           |
           | Sampai 10 September 2026 hitungan ini menerima baris integrasi APA PUN yang aktif,
           | termasuk yang masih `ANTRE` tanpa satu pun tautan. Akibatnya siklus yang baru saja
           | dititipkan ke HC Learn — 1.140 baris ANTRE, NOL tautan — dilaporkan "pintu kurang
           | 0", dan layar penjadwalan memasang "100% SELESAI · Seluruh 1.140 rater telah
           | memiliki akses" pada siklus yang belum punya satu akses pun. Admin lalu membuka
           | jendela pengisian dan tidak seorang pun bisa masuk.
           |
           | Kejanggalannya bahkan terbaca di layar yang sama: "Akses Selesai 0 / 1.140"
           | berdampingan dengan "Belum Terjadwal 0". Tiga angka, tiga sumber, tiga arti — dan
           | yang paling menenangkan yang dipercaya. */
        $pintu = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->selectRaw(
                'COUNT(DISTINCT CASE WHEN i.Api_Ref IS NOT NULL THEN a.Id_PA_Assignment END) as tercetak, '
                .'COUNT(DISTINCT CASE WHEN i.Api_Ref IS NULL AND i.Status_Integrasi = ? THEN a.Id_PA_Assignment END) as antre',
                ['ANTRE'],
            )
            ->first();

        $pintuTercetak = (int) ($pintu->tercetak ?? 0);
        $pintuAntre = (int) ($pintu->antre ?? 0);

        $penghalang = [];

        /* PERSIS syarat `kunci()`. Menuliskannya berbeda di sini berarti layar menjanjikan
           boleh lalu server menolak — atau sebaliknya, dan yang kedua lebih buruk. */
        if ($tertunda > 0) {
            $penghalang[] = "Masih ada {$tertunda} peserta berstatus TERTUNDA. Selesaikan "
                .'resolusi levelnya atau kecualikan mereka lebih dulu.';
        }

        if ($diikutkan === 0) {
            $penghalang[] = 'Tidak ada satu pun peserta yang diikutkan.';
        }

        if (($selKosong = $this->selTanpaPaket($cycleId)) !== []) {
            $penghalang[] = $this->pesanSelTanpaPaket($selKosong);
        }

        ['kurang' => $kurang, 'nonaktif' => $nonaktif] = $this->penghalangPenugasan($cycleId);

        foreach ([$kurang, $nonaktif] as $pesan) {
            if ($pesan !== null) {
                $penghalang[] = $pesan;
            }
        }

        $tanpaPenilai = max(0, $diikutkan - $berpenilai);

        /*
         | DUA MACAM PENGECUALIAN, DAN HANYA SATU YANG PANTAS DIHITUNG SEBAGAI KEPUTUSAN.
         |
         | Angka "dikecualikan" di dialog penguncian dulu menggabungkan keduanya. Pada data
         | sungguhan itu berarti admin yang mengecualikan DUA orang membaca angka 101 — 99
         | sisanya level FREELANCE dan karyawan yang keburu resign, yang dikecualikan mesin
         | menurut aturan tetap. Yang terbaca olehnya bukan "aturan bekerja", melainkan
         | "saya tidak sengaja mengeluarkan 99 orang".
         |
         | Yang dihitung sekarang hanya pengecualian yang DIPUTUSKAN ORANG, yaitu yang
         | namanya ada di daftar pra-kecuali siklus ini. Sisanya tetap dilaporkan terpisah
         | sebagai `otomatis` — disembunyikan sama sekali akan membuat 857 + 2 tidak pernah
         | berjumlah 958, dan angka yang tidak pernah genap justru mengundang pertanyaan
         | yang lebih sulit dijawab.
         */
        $cacah = $this->cacahDikecualikan([$cycleId])[$cycleId]
            ?? ['manual' => 0, 'otomatis' => 0, 'total' => 0];

        $dikecualikanManual = $cacah['manual'];
        $dikecualikanTotal = $cacah['total'];

        return [
            /* Status siklus ikut dikirim supaya Langkah 5 bisa memilih kalimat tombolnya
               sendiri. Menebaknya di layar berarti dua tempat memutuskan hal yang sama, dan
               yang satu akan tertinggal saat gerbangnya berubah. */
            'status_siklus' => $this->gate->status($cycleId),

            'pintu' => [
                'diharapkan' => $totalPenugasan,
                'tercetak' => $pintuTercetak,
                'kurang' => max(0, $totalPenugasan - $pintuTercetak),

                /* DIPISAH dari `kurang`, dan itu menentukan kalimat layar. Dititipkan ke
                   antrean HC Learn bukan kegagalan — tautannya menyusul lewat penarikan hasil
                   — tetapi juga bukan selesai. Menggabungkannya ke salah satu memaksa layar
                   memilih antara berbohong optimis dan berbohong pesimis. */
                'antre' => $pintuAntre,
            ],

            'peserta' => [
                'diikutkan' => $diikutkan,

                /* Yang diputuskan admin. Inilah yang ditampilkan sebagai "dikecualikan". */
                'dikecualikan' => $dikecualikanManual,

                /* Aturan tetap: level FREELANCE, karyawan yang resign sebelum periode
                   berakhir, level tanpa aturan penilai. Bukan keputusan siapa pun hari ini. */
                'dikecualikan_otomatis' => max(0, $dikecualikanTotal - $dikecualikanManual),
                'dikecualikan_total' => $dikecualikanTotal,
                'tertunda' => $tertunda,
            ],
            'penugasan' => [
                'total' => $totalPenugasan,
                'peserta_berpenilai' => $berpenilai,

                /* Bukan penghalang, tetapi WAJIB terlihat. Peserta tanpa penilai adalah peserta
                   yang tidak akan pernah punya nilai, dan sesudah dikunci penugasannya tidak
                   bisa lagi ditambah. */
                'peserta_tanpa_penilai' => $tanpaPenilai,
            ],
            'penghalang' => $penghalang,

            /* Kalimat penghalang kekurangan penilai (atau null), supaya layar bisa memasang
               tombolnya pada butir yang tepat tanpa mencocokkan teks. */
            'penghalang_kekurangan' => $kurang,
            'penghalang_nonaktif' => $nonaktif,
            'boleh_dikunci' => $penghalang === [],
        ];
    }

    /**
     * Memisahkan pengecualian yang DIPUTUSKAN ORANG dari yang ditetapkan aturan.
     *
     * SATU PENULIS, dipakai setiap layar yang menampilkan angka "dikecualikan": kartu
     * siklus, dashboard admin, alur langkah, dan dialog penguncian. Menghitungnya sendiri
     * di tiap tempat sudah pernah membuat satu layar berkata 101 dan layar lain berkata 2
     * untuk siklus yang sama.
     *
     * Pembedanya DAFTAR PRA-KECUALI, bukan teks alasannya. Alasan otomatis berbunyi "Level
     * FREELANCE dikecualikan..." dan alasan manual ditulis admin sendiri; membedakannya
     * lewat pencocokan kata berarti admin yang kebetulan mengetik kata "FREELANCE"
     * menghilang dari hitungannya.
     *
     * @param  list<int>  $cycleIds
     * @return array<int, array{manual:int, otomatis:int, total:int}>
     */
    public function cacahDikecualikan(array $cycleIds): array
    {
        if ($cycleIds === []) {
            return [];
        }

        $total = DB::table('N_PA_Participant')
            ->whereIn('PA_Cycle_Id', $cycleIds)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Peserta', 'DIKECUALIKAN')
            ->selectRaw('PA_Cycle_Id, COUNT(*) as jumlah')
            ->groupBy('PA_Cycle_Id')
            ->pluck('jumlah', 'PA_Cycle_Id');

        $manual = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle_Pre_Exclusion as e', function ($j) {
                $j->on('e.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')
                    ->on('e.Kode_Perusahaan', '=', 'p.Kode_Perusahaan')
                    ->on('e.Kode_Karyawan', '=', 'p.Kode_Karyawan');
            })
            ->whereIn('p.PA_Cycle_Id', $cycleIds)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIKECUALIKAN')
            ->where('e.Flag_Aktif', 'Y')
            ->selectRaw('p.PA_Cycle_Id, COUNT(DISTINCT p.Id_PA_Participant) as jumlah')
            ->groupBy('p.PA_Cycle_Id')
            ->pluck('jumlah', 'PA_Cycle_Id');

        $hasil = [];

        foreach ($cycleIds as $id) {
            $id = (int) $id;
            $t = (int) ($total[$id] ?? 0);
            $m = (int) ($manual[$id] ?? 0);

            $hasil[$id] = [
                'manual' => $m,
                'otomatis' => max(0, $t - $m),
                'total' => $t,
            ];
        }

        return $hasil;
    }

    /**
     | Mengunci siklus. MENGEMBALIKAN apakah penguncian itu benar-benar terjadi sekarang.
     |
     | @return array{status:string, sudah_terkunci:bool}
     */
    public function kunci(int $cycleId, ?int $olehUserId = null): array
    {
        /*
         | SIKLUS YANG SUDAH LOCKED BUKAN KEGAGALAN — ia sudah berada di keadaan yang diminta.
         |
         | Penguncian di layar bukan satu tindakan melainkan dua: mengunci, lalu membuatkan pintu
         | penilai bagi ribuan penugasan. Bagian keduanya memakan belasan detik dan bisa gagal di
         | tengah jalan. Ketika itu terjadi, satu-satunya jalan meneruskannya adalah menekan
         | tombol yang sama sekali lagi.
         |
         | Sebelumnya percobaan kedua itu dijawab "Operasi KUNCI_SIKLUS tidak diizinkan saat
         | siklus berstatus LOCKED" — gerbangnya benar, tetapi akibatnya jalan buntu: siklusnya
         | terkunci, sebagian penilainya tidak punya pintu, dan tidak ada satu pun tombol yang
         | bisa menyelesaikannya. Persis pola yang sama juga muncul dari klik ganda biasa, karena
         | penguncian pertama berhasil dalam sedetik sementara pembuatan pintunya belum.
         |
         | Jadi keadaan LOCKED dijawab dengan keberhasilan yang JUJUR: dikatakan bahwa
         | penguncian tidak dilakukan lagi, dan `Locked_At`/`Locked_By` TIDAK ditulis ulang —
         | menimpanya akan memalsukan kapan dan oleh siapa aturan siklus ini dibekukan.
         */
        if ($this->gate->status($cycleId) === 'LOCKED') {
            return ['status' => 'LOCKED', 'sudah_terkunci' => true];
        }

        /* Sampai 2 September 2026 ini SATU-SATUNYA perpindahan status yang tidak
           digerbangi, sementara saudaranya `siapkan()` tepat di atas memanggilnya.
           Akibatnya siklus CLOSED yang hasilnya sudah TERBIT bisa dikembalikan ke LOCKED
           — dibuktikan dengan menjalankannya. Penghitungan TERTUNDA dan DIIKUTKAN di
           bawah memang penjaga, tetapi keduanya menjaga ISI siklus, bukan status asalnya;
           siklus yang sudah berjalan tetap memenuhi keduanya. */
        $this->gate->pastikanBoleh(PaCycleGate::KUNCI_SIKLUS, $cycleId);

        $tertunda = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'TERTUNDA')
            ->where('Flag_Aktif', 'Y')
            ->count();

        if ($tertunda > 0) {
            throw new RuntimeException(
                "Siklus {$cycleId} tidak bisa dikunci: masih ada {$tertunda} peserta berstatus "
                .'TERTUNDA. Selesaikan resolusi levelnya atau kecualikan mereka lebih dulu.'
            );
        }

        $diikutkan = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->count();

        if ($diikutkan === 0) {
            throw new RuntimeException(
                "Siklus {$cycleId} tidak bisa dikunci: tidak ada satu pun peserta yang diikutkan."
            );
        }

        /* Paket soal wajib lengkap sebelum dikunci: sesudah LOCKED, PILIH_PAKET_SOAL tertutup,
           dan penilai di sel tanpa paket tidak akan pernah mendapat akses HC Learn. */
        if (($selKosong = $this->selTanpaPaket($cycleId)) !== []) {
            throw new RuntimeException("Siklus {$cycleId} tidak bisa dikunci: ".$this->pesanSelTanpaPaket($selKosong));
        }

        foreach ($this->penghalangPenugasan($cycleId) as $pesan) {
            if ($pesan !== null) {
                throw new RuntimeException("Siklus {$cycleId} tidak bisa dikunci: {$pesan}");
            }
        }

        $this->pastikanSiapDiHcLearn($cycleId);

        $sekarang = FormatTanggalHelper::getCurrentTime();

        DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->update([
                'Status_Cycle' => 'LOCKED',
                'Locked_At' => $sekarang,
                /* Kolomnya sudah ada sejak DDL-2026-066 tetapi tidak pernah diisi, dan
                   methodnya bahkan tidak menerima id pengguna. Akibatnya lini masa audit
                   melaporkan "Aturan penilaian dibekukan" sebagai peristiwa TANPA PELAKU
                   selamanya — padahal membekukan aturan satu siklus adalah keputusan
                   manusia yang paling perlu bisa ditelusuri. */
                'Locked_By' => $olehUserId,
                'Updated_At' => $sekarang,
            ]);

        /* Atribut kompetensi (berskor, radar, KKM) dibekukan bersama aturan siklus, supaya
           suntingan master sesudah ini tidak menggeser hitung maupun tampilan hasilnya. */
        PaKompetensiSiklus::bekukan($cycleId, $olehUserId);

        $this->gate->lupakanMemo();

        return ['status' => 'LOCKED', 'sudah_terkunci' => false];
    }

    /**
     * PENGUNCIAN DITAHAN selama masih ada orang yang tidak bisa dipakai HC Learn.
     *
     * KENAPA DI SINI, BUKAN DI LAYAR SAJA
     *
     * Mengunci adalah titik ketika penyiapan berhenti bisa diulang. Sesudahnya penilai yang
     * ditolak HC Learn tidak akan pernah punya pintu, penjadwalannya melapor "1 belum siap"
     * selamanya, dan tombol "Jadwalkan sisanya" menjanjikan sesuatu yang tidak pernah bisa
     * ia berikan — dibuktikan pada siklus PA-2026-02: 1.139 dari 1.140 terjadwal, sisanya
     * seorang penilai yang sudah resign di sisi mereka.
     *
     * Penjaga di layar saja tidak cukup: penguncian juga bisa dipanggil dari jalur lain, dan
     * penjaga yang hanya hidup di satu dari dua pintu setara adalah cacat yang berulang di
     * repo ini.
     *
     * SAMBUNGAN PUTUS TIDAK MENAHAN PENGUNCIAN. Kalau HC Learn tidak bisa dihubungi kita tidak
     * TAHU ada penghalang — dan menahan pekerjaan atas dugaan berarti satu layanan luar yang
     * tumbang membekukan pekerjaan yang tidak membutuhkannya. Yang ditahan hanya jawaban yang
     * TEGAS: ada orang yang memang tidak bisa dipakai.
     */
    private function pastikanSiapDiHcLearn(int $cycleId): void
    {
        try {
            $kesiapan = $this->karyawanHcLearn->kesiapan($cycleId);
        } catch (Throwable $e) {
            Log::warning('Kesiapan HC Learn tidak bisa diperiksa saat mengunci siklus.', [
                'modul' => 'PA',
                'cycle_id' => $cycleId,
                'pesan' => $e->getMessage(),
            ]);

            return;
        }

        /* Yang MENGHALANGI, bukan semua yang tercatat tidak siap. Penilai yang penugasannya
           sudah dicabut tetap muncul di daftar — aturan penilai masih memilihnya sebagai calon
           selama ia aktif di HCIS — tetapi penjadwalan tidak akan pernah mengirimkan namanya.
           Menahan penguncian atas namanya berarti pekerjaan yang sudah dibereskan admin tidak
           pernah menyelesaikan apa pun; itu terjadi, dan jalan keluarnya tidak ada. */
        $penghalang = array_values(array_filter(
            $kesiapan['baris'],
            fn (array $b) => ! empty($b['menghalangi']),
        ));

        if ($penghalang === []) {
            return;
        }

        /* Namanya DISEBUT, bukan diringkas jadi angka. "3 orang belum siap" memindahkan
           pekerjaan menemukan orangnya kepada admin, dan nama itu hanya ada di HC Learn. */
        $nama = array_map(
            fn (array $b) => sprintf('%s (%s) — %s', $b['nama'], $b['kode_karyawan'], $b['alasan']),
            array_slice($penghalang, 0, 5),
        );

        throw new RuntimeException(sprintf(
            'Siklus %d tidak bisa dikunci: %d orang yang terlibat tidak bisa dipakai HC Learn. '
            .'%s%s Bereskan di Langkah 4 (kecualikan pesertanya) atau Langkah 5 (ganti '
            .'penilainya) lebih dulu — sesudah dikunci, pintu untuk mereka tidak akan pernah '
            .'terbit dan penjadwalan akan melapor "belum siap" selamanya.',
            $cycleId,
            count($penghalang),
            implode('; ', $nama),
            count($penghalang) > 5 ? sprintf('; dan %d lainnya.', count($penghalang) - 5) : '.',
        ));
    }

    /**
     * `LOCKED`/`CLOSED` -> `OPEN`. Membuka jendela pengisian.
     *
     * KENAPA METHOD INI BARU ADA SEKARANG. Sampai 2 September 2026 tidak ada satu baris
     * kode produksi pun yang menulis `OPEN` maupun `CLOSED`; satu-satunya penulisnya
     * adalah perintah data contoh `pa:demo-cycle`, yang hanya bekerja pada siklus berkode
     * `PA-DEMO-FE` dan komentarnya sendiri menyatakan bahwa perjalanan status yang
     * sebenarnya "milik aplikasi". Aplikasi itu tidak pernah dibuat.
     *
     * Akibatnya empat operasi inti — terima payload, hitung, kalibrasi, terbitkan —
     * bergerbang pada dua status yang tak terjangkau, dan siklus sungguhan mentok di
     * `LOCKED` selamanya. Cacat ini luput dari `pa:audit` karena audit itu mencari operasi
     * gerbang tanpa pemakai, bukan status gerbang tanpa penulis.
     *
     * @return array{cycle_id:int, status_lama:string, status:string, dibuka_ulang:bool}
     */
    public function bukaPengisian(int $cycleId, ?int $olehUserId = null, array $jendela = []): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::BUKA_PENGISIAN, $cycleId);

        /*
         | JENDELA BOLEH DITETAPKAN DI SINI JUGA.
         |
         | Membuka pengisian menuntut jendela terisi, dan siklus yang dikunci sebelum jadwalnya
         | dipastikan — urutan yang wajar — tidak punya jendela. Sebelumnya admin disuruh keluar
         | ke layar sunting siklus untuk dua tanggal, lalu mencari lagi baris yang tadi sudah ada
         | di depannya. Dua tanggal itu adalah SYARAT tindakan ini; tempatnya di sini.
         |
         | Penulisannya DIDELEGASIKAN ke `PaCycleSetupService`, bukan ditulis ulang. Aturan
         | status mana yang boleh mengubah jendela ada di sana, dan aturan yang disalin ke sini
         | akan menyimpang begitu salah satunya disesuaikan — lalu satu pintu mengizinkan apa
         | yang pintu lain tolak.
         |
         | Seluruhnya SATU transaksi. Jendela yang tersimpan sementara pembukaannya gagal akan
         | meninggalkan siklus dengan jadwal yang tak pernah diminta siapa pun.
         */
        $isiJendela = array_filter(
            [
                'pengisian_dari' => $jendela['pengisian_dari'] ?? null,
                'pengisian_sampai' => $jendela['pengisian_sampai'] ?? null,
            ],
            fn ($v) => $v !== null && $v !== '',
        );

        return DB::transaction(function () use ($cycleId, $olehUserId, $isiJendela) {
            $this->pastikanTidakAdaSiklusLainTerbuka($cycleId);

            if ($isiJendela !== []) {
                /* `true` = sedang dibuka ulang. Itulah yang membuat siklus CLOSED boleh
                   memperpanjang jendelanya di sini padahal layar sunting siklus menolaknya:
                   di sini tanggal barunya ditulis bersamaan dengan pembukaannya, dalam satu
                   transaksi, sesudah gerbang `BUKA_PENGISIAN` lolos. */
                $this->setup->perbarui($cycleId, $isiJendela, $olehUserId, true);
            }

            return $this->bukaPengisianSetelahJendela($cycleId, $olehUserId);
        });
    }

    /**
     | HANYA SATU SIKLUS BOLEH TERBUKA PADA SATU WAKTU.
     |
     | Bukan kerapian administratif. Seseorang bisa menjadi peserta DAN penilai di lebih dari
     | satu siklus sekaligus — H45 tercatat di empat. Ketika dua siklus terbuka bersamaan,
     | antrean tugasnya memuat dua himpunan penilaian atas orang-orang yang sebagian sama,
     | dengan pertanyaan yang sama, pada periode yang tumpang tindih. Yang terjadi kemudian
     | bukan sekadar membingungkan: penilaian yang dimaksudkan untuk satu siklus akan
     | dikerjakan pada siklus yang lain, dan tidak ada satu pun kolom yang bisa mengatakan
     | bahwa itu telah terjadi.
     |
     | DIKUNCI, BUKAN SEKADAR DIHITUNG. `lockForUpdate()` pada SQL Server menghasilkan
     | `with(rowlock,updlock,holdlock)` — HOLDLOCK menahan RENTANGNYA, bukan hanya baris yang
     | kebetulan ada. Tanpa itu dua admin yang menekan tombolnya pada detik yang sama akan
     | sama-sama membaca "tidak ada yang terbuka" lalu sama-sama membuka, dan pemeriksaan yang
     | benar menghasilkan keadaan yang salah. RCSI mati di basis data ini, sehingga penguncian
     | inilah satu-satunya yang menyerialkan keduanya.
     |
     | Siklus ini sendiri DIKECUALIKAN. Lewat aplikasi pengecualian itu tidak pernah terpakai —
     | gerbang sudah melarang `BUKA_PENGISIAN` dari status OPEN, sehingga siklus yang terbuka
     | tak pernah sampai ke sini. Tetap ditulis karena aturannya berbunyi "tidak boleh ada
     | siklus LAIN yang terbuka", dan aturan yang menghitung dirinya sendiri sebagai pelanggar
     | akan menjadi jebakan bagi pemanggil berikutnya yang gerbangnya kebetulan lebih longgar.
     */
    private function pastikanTidakAdaSiklusLainTerbuka(int $cycleId): void
    {
        $lain = DB::table('N_PA_Cycle')
            ->where('Status_Cycle', 'OPEN')
            ->where('Flag_Aktif', 'Y')
            ->where('Id_PA_Cycle', '<>', $cycleId)
            ->lockForUpdate()
            ->first(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle']);

        if ($lain === null) {
            return;
        }

        /* Siklus penghalangnya DISEBUT NAMANYA. "Sudah ada siklus lain yang terbuka" memaksa
           admin membuka daftar dan mencarinya sendiri, padahal jawabannya sudah ada di sini. */
        throw new RuntimeException(sprintf(
            'Siklus %s (%s) sedang terbuka, dan hanya satu siklus boleh terbuka pada satu '
            .'waktu. Seseorang bisa menjadi penilai di dua siklus sekaligus, dan dua jendela '
            .'yang terbuka bersamaan membuat penilaian untuk siklus yang satu dikerjakan pada '
            .'siklus yang lain. Tutup dulu siklus %s.',
            $lain->Kode_Cycle,
            $lain->Nama_Cycle,
            $lain->Kode_Cycle,
        ));
    }

    /**
     * Berapa siklus yang sedang terbuka, dan yang mana.
     *
     * Dipakai layar untuk memadamkan tombolnya SEBELUM ditekan — menawarkan tombol yang sudah
     * pasti ditolak berarti menyuruh admin menekannya untuk mengetahui sesuatu yang sudah bisa
     * dikatakan sekarang.
     *
     * @return array{id:int, kode:string, nama:string}|null
     */
    public function siklusSedangTerbuka(?int $kecuali = null): ?array
    {
        $q = DB::table('N_PA_Cycle')
            ->where('Status_Cycle', 'OPEN')
            ->where('Flag_Aktif', 'Y');

        if ($kecuali !== null) {
            $q->where('Id_PA_Cycle', '<>', $kecuali);
        }

        $baris = $q->first(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle']);

        return $baris === null ? null : [
            'id' => (int) $baris->Id_PA_Cycle,
            'kode' => (string) $baris->Kode_Cycle,
            'nama' => (string) $baris->Nama_Cycle,
        ];
    }

    /**
     * Bagian pembukaan yang murni, sesudah jendelanya dipastikan ada.
     *
     * @return array{cycle_id:int, status_lama:string, status:string, dibuka_ulang:bool,
     *               tenggat_diturunkan:int}
     */
    private function bukaPengisianSetelahJendela(int $cycleId, ?int $olehUserId): array
    {
        $siklus = $this->siklusAtauGagal($cycleId);
        $lama = (string) $siklus->Status_Cycle;

        /* Jendela pengisian yang belum diisi tanggalnya membuat tenggat penugasan lahir
           kosong — dan tenggat kosong membuat "terlambat" mustahil dihitung, sehingga
           penilai yang tidak mengisi baru ketahuan saat siklus sudah ditutup. Ditolak di
           sini, bukan dibiarkan menjadi kekosongan yang menyebar. */
        if ($siklus->Pengisian_Dari === null || $siklus->Pengisian_Sampai === null) {
            throw new RuntimeException(
                "Siklus {$cycleId} belum punya jendela pengisian (Pengisian_Dari/Sampai). "
                .'Tanpa itu penugasan lahir tanpa tenggat, dan penilai yang tidak mengisi '
                .'tidak akan pernah tercatat terlambat. Isi Pengisian Dari dan Pengisian '
                .'Sampai pada dialog pembukaan, atau lewat sunting siklus.'
            );
        }

        /*
         | Penugasan yang lahir SEBELUM jendelanya diisi tidak punya tenggat, dan penjaga di atas
         | tidak melihatnya — ia hanya memeriksa jendela siklusnya. Tanpa langkah ini siklus bisa
         | dibuka dengan jendela lengkap sementara ribuan penugasan di dalamnya tetap kosong
         | tenggat, sehingga bahaya yang baru saja disebut penjaga itu tetap terjadi.
         |
         | Dijalankan di sini, bukan saat jendelanya disunting: membuka pengisian adalah saat
         | tenggat mulai berarti, dan menjalankannya di sini juga menangkap penugasan yang
         | dibentuk di antara penyuntingan jendela dan pembukaan.
         */
        /* MENIMPA, bukan hanya mengisi yang kosong.

           Pembukaan bisa terjadi lebih dari sekali — siklus yang ditutup terlalu cepat
           boleh dibuka lagi — dan jendelanya mungkin sudah bergeser di antaranya. Mengisi
           yang kosong saja akan meninggalkan penugasan lama memegang tenggat dari jendela
           yang sudah tidak berlaku, sementara layar siklus menunjukkan yang baru.

           Yang sudah SELESAI atau DIBATALKAN tetap dilewati di dalam sana: tenggatnya
           sudah menjadi bagian dari catatan keterlambatan yang terjadi. */
        $tenggatDiturunkan = $this->penugasan->turunkanTenggat($cycleId, true);

        $sekarang = FormatTanggalHelper::getCurrentTime();

        DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->update([
                'Status_Cycle' => 'OPEN',
                'Updated_At' => $sekarang,
                'Updated_By' => $olehUserId,
            ]);

        $this->gate->lupakanMemo();

        return [
            'cycle_id' => $cycleId,
            'status_lama' => $lama,
            'status' => 'OPEN',
            // Dibedakan supaya layar bisa memberi peringatan yang berbeda: membuka ulang
            // siklus yang sudah ditutup berarti hasil yang sudah dihitung menjadi basi.
            'dibuka_ulang' => $lama === 'CLOSED',

            /* DILAPORKAN. Menuliskan tenggat pada ribuan penugasan sekaligus adalah perubahan
               data yang besar, dan perubahan sebesar itu tidak boleh terjadi diam-diam. */
            'tenggat_diturunkan' => $tenggatDiturunkan,
        ];
    }

    /**
     * `OPEN` -> `CLOSED`. Menutup jendela pengisian dan membuka tahap perhitungan.
     *
     * Tidak menolak penugasan yang belum selesai — itu keputusan admin, bukan aturan.
     * Jumlahnya DILAPORKAN supaya keputusannya diambil sambil melihat: menutup siklus
     * dengan 200 penilaian belum masuk sah, tetapi tidak boleh terjadi tanpa disadari.
     *
     * @return array{cycle_id:int, status:string, penugasan_belum_selesai:int}
     */
    public function tutupPengisian(int $cycleId, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::TUTUP_PENGISIAN, $cycleId);

        $this->siklusAtauGagal($cycleId);

        $sekarang = FormatTanggalHelper::getCurrentTime();

        DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->update([
                'Status_Cycle' => 'CLOSED',
                'Updated_At' => $sekarang,
                'Updated_By' => $olehUserId,
            ]);

        $this->gate->lupakanMemo();

        /* PENUTUPAN MELEPAS SELURUH YANG TERKUMPUL.
           Menutup pengisian berarti menyatakan pengumpulan usai. Lembar yang sudah terisi
           penuh tetapi penilainya belum menekan kirim akan terkunci selamanya sesudah titik
           ini - tidak ada satu pun layar sesudah penutupan yang menyediakan tombol itu
           kepadanya - sehingga pekerjaan yang sudah dilakukan orang hangus karena satu
           tombol yang tidak sempat ditekan. Dijalankan SESUDAH status menjadi CLOSED supaya
           gerbangnya membaca keadaan yang benar. */
        $pelepasan = app(PaRaterSubmitService::class)->lepasSemuaSaatDitutup($cycleId, $olehUserId);

        /* Dihitung SESUDAH pelepasan, bukan sebelumnya. Yang ditanyakan angka ini adalah
           "berapa yang benar-benar tidak terisi saat pintu ditutup", dan sebelum pelepasan
           seluruh lembar TERKUMPUL - yang justru sudah terisi penuh - masih terhitung di
           dalamnya. Melaporkannya dari sana akan menyebut ratusan lembar sebagai tidak
           dikerjakan pada siklus yang sebetulnya nyaris tuntas. */
        $belum = (int) DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->whereNotIn('a.Status_Penugasan', ['SELESAI', 'DIBATALKAN'])
            ->count();

        return [
            'cycle_id' => $cycleId,
            'status' => 'CLOSED',
            'penugasan_belum_selesai' => $belum,
            'penilaian_dilepas' => $pelepasan['dilepas'],
            'perhitungan_diantrekan' => $pelepasan['peserta_diantrekan'],
        ];
    }

    private function siklusAtauGagal(int $cycleId): object
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Id_PA_Cycle', 'Status_Cycle', 'Pengisian_Dari', 'Pengisian_Sampai']);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        return $siklus;
    }

    /**
     * Isi keranjang TERTUNDA beserta sebabnya, untuk ditampilkan sebelum penguncian
     * dicoba. Menampilkan penghalang lebih dulu jauh lebih berguna daripada menolak
     * saat tombol ditekan.
     *
     * @return list<object>
     */
    public function keranjangTertunda(int $cycleId): array
    {
        return DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'TERTUNDA')
            ->where('Flag_Aktif', 'Y')
            ->select([
                'Id_PA_Participant', 'Kode_Perusahaan', 'Kode_Karyawan',
                'Nama_Karyawan_Saat_Cycle', 'ID_Level_Saat_Cycle', 'Nama_Level_Saat_Cycle',
            ])
            ->orderBy('Nama_Karyawan_Saat_Cycle')
            ->get()
            ->all();
    }

    /* ------------------------------------------------------------------ *
     * 1. Menyalin cetak biru                                              *
     * ------------------------------------------------------------------ */

    /**
     * Menyalin cetak biru siklus TANPA membentuk peserta.
     *
     * Dipakai alur penyiapan berlangkah: admin perlu melihat dan menyunting aturan penilai
     * siklusnya SEBELUM ratusan baris peserta terbentuk darinya. Menyalin belakangan —
     * seperti dahulu, di dalam `siapkan()` — berarti satu-satunya kesempatan melihat aturan
     * yang dipakai adalah sesudah ia terlanjur dipakai.
     *
     * Aman dipanggil berkali-kali: `salinPolicy()` dan kawan-kawannya melewati level yang
     * salinannya sudah ada. Karena itu `siapkan()` yang berjalan sesudah ini tidak menimpa
     * suntingan admin — ia menemukan salinannya sudah lengkap dan melewatinya.
     *
     * @return array<string, int>
     */
    public function salinCetakBiru(int $cycleId): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_SALINAN, $cycleId);

        return DB::transaction(fn () => $this->salinAturan($cycleId, $this->levelYangDipakai($cycleId)));
    }

    /**
     * @param  list<int>  $levelIds
     * @return array<string, int>
     */
    private function salinAturan(int $cycleId, array $levelIds): array
    {
        $hitung = ['policy' => 0, 'detail' => 0, 'pilar' => 0, 'kompetensi' => 0, 'pita' => 0];

        foreach ($levelIds as $levelId) {
            $policy = DB::table('N_PA_Rater_Policy')
                ->where('ID_Level', $levelId)
                ->where('Status_Policy', 'ACTIVE')
                ->where('Flag_Aktif', 'Y')
                ->orderByDesc('Versi')
                ->first();

            if ($policy === null) {
                // Level tanpa aturan penilai TIDAK dibuatkan salinan. Pesertanya akan
                // mendarat di keranjang TERTUNDA dan terlihat, bukan dinilai memakai
                // aturan karangan.
                continue;
            }

            $detail = DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policy->Id_PA_Rater_Policy)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Tampil')
                ->get();

            /* Sel yang BERLAKU tetapi BELUM BERBOBOT disebut lebih dulu, dengan namanya.

               Sejak DDL-2026-092 sel bisa ditandai di Matriks Jenis Penilai lalu menunggu
               bobotnya diisi di Komposisi Penilai. `pastikanSeratus()` di bawah memang sudah
               menolaknya — NULL tidak akan pernah menjumlah 100 — tetapi pesannya akan
               berbunyi "totalnya 80%", dan admin lalu mencari sel mana yang bobotnya salah
               padahal yang benar adalah ada sel yang belum diisi sama sekali. */
            $menunggu = $detail->whereNull('Bobot_Persen');

            if ($menunggu->isNotEmpty()) {
                $nama = DB::table('N_PA_Rater_Category')
                    ->whereIn('Id_PA_Rater_Category', $menunggu->pluck('PA_Rater_Category_Id')->all())
                    ->pluck('Nama_Kategori')
                    ->all();

                throw new RuntimeException(sprintf(
                    'Aturan penilai level %d belum lengkap: %d kategori sudah ditandai menilai '
                    .'tetapi bobotnya belum ditetapkan (%s). Isi bobotnya di Master > Komposisi '
                    .'Penilai, atau cabut selnya di Master > Jenis Penilai.',
                    $levelId,
                    $menunggu->count(),
                    implode(', ', $nama),
                ));
            }

            // Bobot diperiksa SEBELUM disalin. Menyalin dulu lalu memeriksa berarti
            // siklus sudah terkontaminasi saat kesalahannya ketahuan.
            $this->bobot->pastikanSeratus(
                $detail->pluck('Bobot_Persen', 'PA_Rater_Category_Id')->all(),
                "aturan penilai level {$levelId}"
            );

            $hitung['policy'] += $this->salinPolicy($cycleId, $levelId, $policy, $detail, $hitung);
            $hitung['pilar'] += $this->salinPilar($cycleId, $levelId);
            $hitung['kompetensi'] += $this->salinKompetensi($cycleId, $levelId);
        }

        $hitung['pita'] = $this->salinPita($cycleId);

        // Tidak ikut `$hitung`: bentuk ringkasan penyiapan adalah kontrak layar yang sudah ada.
        $this->salinGrupKurva($cycleId, null);

        return $hitung;
    }

    private function salinPolicy(int $cycleId, int $levelId, object $policy, $detail, array &$hitung): int
    {
        $sudah = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        if ($sudah !== null) {
            return 0;
        }

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')->insert([
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => $levelId,
            'PA_Rater_Policy_Id' => $policy->Id_PA_Rater_Policy,
            'Kode_Policy_Salinan' => $policy->Kode_Policy,
            'Nama_Policy_Salinan' => $policy->Nama_Policy,
            'Versi_Policy_Salinan' => $policy->Versi,
            'Deskripsi_Policy_Salinan' => $policy->Deskripsi ?? null,
        ]);

        $snapshotId = (int) DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        foreach ($detail as $d) {
            $kategori = DB::table('N_PA_Rater_Category')
                ->where('Id_PA_Rater_Category', $d->PA_Rater_Category_Id)
                ->first(['Kode_Kategori', 'Nama_Kategori']);

            $relasi = $d->Organization_Relationship_Type_Id === null ? null
                : DB::table('N_HRIS_Organization_Relationship_Type')
                    ->where('Id_Organization_Relationship_Type', $d->Organization_Relationship_Type_Id)
                    ->value('Kode_Relasi');

            DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')->insert([
                'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
                'PA_Rater_Policy_Detail_Id' => $d->Id_PA_Rater_Policy_Detail,
                'PA_Rater_Category_Id' => $d->PA_Rater_Category_Id,
                // Nama ikut dibekukan supaya laporan siklus lama tetap terbaca benar
                // walau master kelak berganti nama.
                'Kode_Kategori_Salinan' => $kategori->Kode_Kategori,
                'Nama_Kategori_Salinan' => $kategori->Nama_Kategori,
                'Organization_Relationship_Type_Id' => $d->Organization_Relationship_Type_Id,
                'Kode_Relasi_Salinan' => $relasi,
                'Bobot_Persen' => $d->Bobot_Persen,
                'Min_Rater' => $d->Min_Rater,
                'Maks_Rater' => $d->Maks_Rater,
                'Min_Rater_Selesai' => $d->Min_Rater_Selesai,
                'Flag_Izinkan_Manual' => $d->Flag_Izinkan_Manual,
                'Urutan_Tampil' => $d->Urutan_Tampil,
                'Catatan_Aturan_Salinan' => $d->Catatan_Aturan ?? null,
            ]);

            $hitung['detail']++;
        }

        return 1;
    }

    private function salinPilar(int $cycleId, int $levelId): int
    {
        $sudah = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)->where('ID_Level', $levelId)->exists();

        if ($sudah) {
            return 0;
        }

        $pilar = DB::table('N_PA_Pillar_Weight')->where('ID_Level', $levelId)->first();

        if ($pilar === null) {
            return 0;
        }

        $this->bobot->pastikanSeratus(
            ['KPI' => $pilar->Bobot_KPI, 'KOMPETENSI' => $pilar->Bobot_Kompetensi],
            "bobot pilar level {$levelId}"
        );

        DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')->insert([
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => $levelId,
            'PA_Pillar_Weight_Id' => $pilar->Id_PA_Pillar_Weight,
            'Bobot_KPI' => $pilar->Bobot_KPI,
            'Bobot_Kompetensi' => $pilar->Bobot_Kompetensi,
        ]);

        return 1;
    }

    private function salinKompetensi(int $cycleId, int $levelId): int
    {
        $n = 0;

        $syarat = DB::table('N_PA_Competency_Requirement as r')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'r.PA_Competency_Type_Id')
            ->where('r.ID_Level', $levelId)
            ->where('r.Flag_Aktif', 'Y')
            ->get(['r.Id_PA_Competency_Requirement', 'r.PA_Competency_Type_Id', 'r.Flag_Wajib',
                't.Kode_Type', 't.Nama_Type']);

        foreach ($syarat as $s) {
            $sudah = DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('ID_Level', $levelId)
                ->where('PA_Competency_Type_Id', $s->PA_Competency_Type_Id)
                ->exists();

            if ($sudah) {
                continue;
            }

            DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')->insert([
                'PA_Cycle_Id' => $cycleId,
                'ID_Level' => $levelId,
                'PA_Competency_Type_Id' => $s->PA_Competency_Type_Id,
                'PA_Competency_Requirement_Id' => $s->Id_PA_Competency_Requirement,
                'Kode_Type_Salinan' => $s->Kode_Type,
                'Nama_Type_Salinan' => $s->Nama_Type,
                'Flag_Wajib' => $s->Flag_Wajib,
            ]);

            $n++;
        }

        // Matriks per kompetensi + bunyi tingkat dan Expected Behaviour-nya (DDL-2026-175).
        $n += app(PaKompetensiMatriksService::class)->salinKeSiklus($cycleId, $levelId);

        return $n;
    }

    /**
     * Pita nilai disalin per siklus, bukan per level — dua orang bernilai sama wajib
     * mendapat grade sama, apa pun levelnya.
     */
    /**
     * Grup level kurva disalin per siklus seperti pita: mengubah master sesudah siklus
     * disiapkan tidak boleh menggeser pembanding siapa pun. Setiap level WAJIB punya grup di
     * siklus — level yang belum dipetakan admin menjadi grupnya sendiri, supaya kunci kurva
     * tidak pernah menemukan peserta tanpa kurva.
     */
    public function salinGrupKurva(int $cycleId, ?int $olehUserId): int
    {
        if (DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->exists()) {
            return 0;
        }

        $grup = DB::table('N_PA_Curve_Group')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')->get(['Id_PA_Curve_Group', 'Kode_Grup', 'Nama_Grup', 'Urutan_Tampil']);
        $anggota = DB::table('N_PA_Curve_Group_Level')->where('Flag_Aktif', 'Y')
            ->get(['PA_Curve_Group_Id', 'ID_Level'])->groupBy('PA_Curve_Group_Id');
        $terpakai = [];
        $n = 0;

        foreach ($grup as $g) {
            $id = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId([
                'PA_Cycle_Id' => $cycleId,
                'PA_Curve_Group_Id' => $g->Id_PA_Curve_Group,
                'Kode_Grup' => $g->Kode_Grup,
                'Nama_Grup' => $g->Nama_Grup,
                'Urutan_Tampil' => $g->Urutan_Tampil,
                'Created_By' => $olehUserId,
            ], 'Id_PA_Cycle_Curve_Group');

            foreach ($anggota->get($g->Id_PA_Curve_Group, []) as $l) {
                DB::table('N_PA_Cycle_Curve_Group_Level')->insert([
                    'PA_Cycle_Curve_Group_Id' => $id, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $l->ID_Level,
                ]);
                $terpakai[(int) $l->ID_Level] = true;
            }

            $n++;
        }

        $urutan = $grup->count();

        PaLevelMasterService::sinkronHris();
        foreach (DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')->orderBy('Urutan')->orderBy('ID_Level')->get(['ID_Level', 'Nama_Level as Keterangan']) as $l) {
            if (isset($terpakai[(int) $l->ID_Level])) {
                continue;
            }

            $id = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId([
                'PA_Cycle_Id' => $cycleId,
                'Kode_Grup' => 'LV'.$l->ID_Level,
                'Nama_Grup' => (string) $l->Keterangan,
                'Urutan_Tampil' => ++$urutan,
                'Created_By' => $olehUserId,
            ], 'Id_PA_Cycle_Curve_Group');

            DB::table('N_PA_Cycle_Curve_Group_Level')->insert([
                'PA_Cycle_Curve_Group_Id' => $id, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $l->ID_Level,
            ]);
            $n++;
        }

        return $n;
    }

    private function salinPita(int $cycleId): int
    {
        if (DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $cycleId)->exists()) {
            return 0;
        }

        $pita = DB::table('N_PA_Score_Range')->where('Flag_Aktif', 'Y')->get();
        $n = 0;

        foreach ($pita as $p) {
            DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert([
                'PA_Cycle_Id' => $cycleId,
                'PA_Score_Range_Id' => $p->Id_PA_Score_Range,
                'Score_Type' => $p->Score_Type,
                'Score_Usage' => $p->Score_Usage,
                'Kode_Range' => $p->Kode_Range,
                'Nama_Range' => $p->Nama_Range,
                'Nilai_Dari' => $p->Nilai_Dari,
                'Nilai_Sampai' => $p->Nilai_Sampai,
                'Batas_Z' => $p->Batas_Z,
                'Nilai_Hasil' => $p->Nilai_Hasil,
                'Target_Persen' => $p->Target_Persen,
                'Warna' => $p->Warna,
                'Urutan_Tampil' => $p->Urutan_Tampil,
            ]);
            $n++;
        }

        $target = $pita->where('Score_Usage', 'GRADE')->pluck('Target_Persen', 'Kode_Range')->all();

        if ($target !== []) {
            $this->bobot->pastikanSeratus($target, 'target distribusi grade');
        }

        return $n;
    }

    /* ------------------------------------------------------------------ *
     * 2. Membentuk peserta                                                *
     * ------------------------------------------------------------------ */

    /**
     * Membentuk seluruh baris peserta dengan jumlah kueri TETAP, bukan per karyawan.
     *
     * Versi pertama memanggil dua kueri untuk tiap karyawan — pada 958 orang itu
     * hampir dua ribu kueri untuk satu penyiapan. Proyek ini sudah pernah kena CPU
     * 100 persen karena pola N+1, jadi jalur tulis pertamanya tidak boleh lahir
     * dengan pola yang sama. Sekarang: satu kueri memuat peserta yang sudah ada,
     * satu memuat salinan aturan per level, sisanya sisip berkelompok.
     *
     * @return array<string, int>
     */
    private function bentukPeserta(int $cycleId, ?string $batasResign): array
    {
        /* Dipisah dengan aturan yang sama dengan pratinjaunya. Laporan sesudah penyiapan
           dan pratinjau sebelum penyiapan membicarakan hal yang sama; kalau yang satu
           memisahkan dan yang satu menggabungkan, angkanya akan berselisih tepat di depan
           mata admin yang baru saja membaca keduanya berturut-turut. */
        $hitung = [
            'diikutkan' => 0,
            'dikecualikan' => 0,
            'dikecualikan_otomatis' => 0,
            'tertunda' => 0,
            'sudah_ada' => 0,
            'level_diperbarui' => 0,
        ];
        $tertahan = [];

        $sudahAda = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Participant', 'Kode_Perusahaan', 'Kode_Karyawan', 'ID_Level_Saat_Cycle', 'PA_Karyawan_Level_PA_Id', 'Updated_By', 'Status_Peserta', 'Alasan_Dikecualikan'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan => $r])
            ->all();

        $snapshotPerLevel = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot', 'ID_Level')
            ->map(fn ($x) => (int) $x)
            ->all();

        $praKecuali = $this->praKecuali($cycleId);
        $minMasaKerja = $this->minMasaKerja($cycleId);

        $baris = [];

        foreach ($this->karyawanAktif($cycleId) as $k) {
            if (isset($sudahAda[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan])) {
                $hitung['sudah_ada']++;
                $lama = $sudahAda[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan];

                /* Level PA (DDL-2026-177) BERUBAH sejak peserta dibentuk — koreksi baru, dicabut,
                   atau level HRIS-nya bergeser. Tanpa langkah ini koreksi yang dibuat sesudah
                   penyiapan pertama diam-diam tidak berlaku di siklus ini. */
                if (! $this->levelSama($lama, $k)) {
                    $sebab = $this->sebabLevelTertahan((int) $lama->Id_PA_Participant, $lama, $k, $snapshotPerLevel);

                    if ($sebab === null) {
                        $this->segarkanLevelPeserta($lama, $k, $snapshotPerLevel, $batasResign, $praKecuali, $minMasaKerja);
                        $hitung['level_diperbarui']++;
                    } else {
                        $tertahan[] = [
                            'participant_id' => (int) $lama->Id_PA_Participant,
                            'nama' => $k->Nama,
                            'kode_karyawan' => $k->Kode_Karyawan,
                            'level_sekarang' => $lama->ID_Level_Saat_Cycle === null ? null : (int) $lama->ID_Level_Saat_Cycle,
                            'level_seharusnya' => $k->ID_Level === null ? null : (int) $k->ID_Level,
                            'nama_level_seharusnya' => $k->nama_level,
                            'sebab' => $sebab,
                        ];
                    }
                }

                continue;
            }

            [$status, $alasan, $snapshotId] = $this->nilaiKelayakan(
                $k, $snapshotPerLevel, $batasResign, $praKecuali, $minMasaKerja
            );

            $baris[] = $this->barisPesertaBaru($cycleId, $k, $status, $alasan, $snapshotId);

            /* Pengecualian dipisah menurut ASALNYA, sama seperti pratinjaunya. Pembedanya
               daftar pra-kecuali, bukan teks alasannya: alasan manual diketik admin dan
               bisa berbunyi apa saja, termasuk menyerupai kalimat aturan otomatis. */
            if ($status === 'DIKECUALIKAN') {
                $hitung[isset($praKecuali[$k->Kode_Perusahaan.'|'.$k->Kode_Karyawan])
                    ? 'dikecualikan'
                    : 'dikecualikan_otomatis']++;
            } else {
                $hitung[strtolower($status)]++;
            }
        }

        /* Ukuran potongan DIHITUNG dari jumlah kolom, bukan angka tetap: SQL Server menolak
           lebih dari 2.099 parameter per perintah. Angka tetap 100 aman pada 18 kolom, lalu
           gagal begitu kolom Level PA (DDL-2026-177) menjadikannya 21 — 100 x 21 = 2.100. */
        $perSisip = max(1, intdiv(2000, max(1, count($baris[0] ?? []))));

        foreach (array_chunk($baris, $perSisip) as $potongan) {
            DB::table('N_PA_Participant')->insert($potongan);
        }

        $hitung['level_tertahan'] = $tertahan;

        return $hitung;
    }

    /**
     * Baris peserta baru dari satu karyawan — satu bentuk untuk penyiapan DAN peserta susulan
     * sesudah dikunci. Dua salinan susunan kolom akan menyimpang (kolom jejak Level PA
     * DDL-2026-177 baru ditambahkan di satu tempat saja dan yang lain lupa).
     *
     * @return array<string, mixed>
     */
    private function barisPesertaBaru(int $cycleId, object $k, string $status, ?string $alasan, ?int $snapshotId): array
    {
        return [
            'PA_Cycle_Id' => $cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $k->ID_Level,
            'Nama_Level_Saat_Cycle' => $k->nama_level,
            // Jejak Level PA (DDL-2026-177): level HRIS asli + koreksi yang dipakai (null = tanpa koreksi).
            'ID_Level_HRIS_Saat_Cycle' => $k->ID_Level_HRIS,
            'Nama_Level_HRIS_Saat_Cycle' => $k->nama_level_hris,
            'PA_Karyawan_Level_PA_Id' => $k->Koreksi_Level_PA_Id,
            'ID_Divisi_Sub_Divisi_Saat_Cycle' => $k->ID_Divisi_Sub_Divisi,
            'ID_Divisi_Saat_Cycle' => $k->ID_Divisi,
            'Nama_Divisi_Saat_Cycle' => $k->nama_divisi,
            'ID_Sub_Divisi_Saat_Cycle' => $k->ID_Sub_Divisi,
            'Nama_Sub_Divisi_Saat_Cycle' => $k->nama_sub_divisi,
            'Kode_Jabatan_Saat_Cycle' => $k->Kode_Jabatan,
            'Nama_Jabatan_Saat_Cycle' => $k->nama_jabatan,
            'Lokasi_Saat_Cycle' => $k->Lokasi,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
            'Status_Peserta' => $status,
            'Alasan_Dikecualikan' => $alasan,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bantuan untuk revisi peserta sesudah dikunci (PaRevisiPesertaService) *
     * ------------------------------------------------------------------ */

    /** Data karyawan TERKINI menurut HRIS + Koreksi Level PA — null bila tidak aktif. */
    public function karyawanTerkini(int $cycleId, string $kodePerusahaan, string $kodeKaryawan): ?object
    {
        foreach ($this->karyawanAktif($cycleId) as $k) {
            if ($k->Kode_Perusahaan === $kodePerusahaan && trim((string) $k->Kode_Karyawan) === trim($kodeKaryawan)) {
                return $k;
            }
        }

        return null;
    }

    /**
     * Seluruh karyawan aktif terkini, dipetakan "perusahaan|karyawan" — SATU pemindaian untuk
     * revisi banyak orang sekaligus. Memanggil `karyawanTerkini()` per orang berarti memindai
     * seluruh HRIS sekali untuk tiap orang.
     *
     * @return array<string, object>
     */
    public function petaKaryawanTerkini(int $cycleId): array
    {
        $peta = [];
        foreach ($this->karyawanAktif($cycleId) as $k) {
            $peta[$k->Kode_Perusahaan.'|'.trim((string) $k->Kode_Karyawan)] = $k;
        }

        return $peta;
    }

    /** @return array<int, int> level => id salinan aturan penilai siklus ini */
    public function salinanAturanPerLevel(int $cycleId): array
    {
        return DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot', 'ID_Level')
            ->map(fn ($x) => (int) $x)
            ->all();
    }

    /**
     * Kelayakan karyawan dengan aturan yang SAMA dengan penyiapan — tanpa daftar pra-kecuali,
     * karena yang menambahkannya admin sendiri dengan sadar.
     *
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    public function kelayakanKaryawan(int $cycleId, object $k, ?array $salinanPerLevel = null): array
    {
        $this->memoBatasResign[$cycleId] ??= $this->batasResign($cycleId);
        if (! array_key_exists($cycleId, $this->memoMasaKerja)) {
            $this->memoMasaKerja[$cycleId] = $this->minMasaKerja($cycleId);
        }

        return $this->nilaiKelayakan(
            $k, $salinanPerLevel ?? $this->salinanAturanPerLevel($cycleId),
            $this->memoBatasResign[$cycleId], [], $this->memoMasaKerja[$cycleId]
        );
    }

    /** @var array<int, ?string> */
    private array $memoBatasResign = [];

    /** @var array<int, ?int> */
    private array $memoMasaKerja = [];

    /** @return array<string, mixed> baris siap disisipkan untuk peserta susulan */
    public function barisPesertaSusulan(int $cycleId, object $k, ?int $snapshotId): array
    {
        return $this->barisPesertaBaru($cycleId, $k, 'DIIKUTKAN', null, $snapshotId);
    }

    public function levelBerbeda(object $peserta, object $k): bool
    {
        return ! $this->levelSama($peserta, $k);
    }

    private function levelSama(object $lama, object $k): bool
    {
        $a = $lama->ID_Level_Saat_Cycle === null ? null : (int) $lama->ID_Level_Saat_Cycle;
        $b = $k->ID_Level === null ? null : (int) $k->ID_Level;

        return $a === $b
            && ($lama->PA_Karyawan_Level_PA_Id === null ? null : (int) $lama->PA_Karyawan_Level_PA_Id)
                === ($k->Koreksi_Level_PA_Id === null ? null : (int) $k->Koreksi_Level_PA_Id);
    }

    /**
     * Peserta yang levelnya TIDAK boleh disegarkan otomatis, beserta sebabnya; null = boleh.
     * Penugasan dan penyesuaian aturan per orang bertumpu pada aturan level lama, jadi admin
     * membereskannya dulu lalu menjalankan Siapkan lagi.
     */
    private function sebabLevelTertahan(int $pid, object $lama, object $k, array $snapshotPerLevel, bool $abaikanPenugasan = false): ?string
    {
        if (! $abaikanPenugasan && DB::table('N_PA_Assignment')->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', 'DIBATALKAN')->exists()) {
            return 'Sudah punya penugasan penilai. Batalkan penugasannya, lalu jalankan Siapkan lagi.';
        }

        foreach (['N_PA_Participant_Rater_Override', 'N_PA_Participant_Pillar_Override', 'N_PA_Participant_Requirement_Override'] as $t) {
            if (DB::table($t)->where('PA_Participant_Id', $pid)->where('Flag_Aktif', 'Y')->exists()) {
                return 'Punya penyesuaian aturan per orang. Hapus penyesuaiannya, lalu jalankan Siapkan lagi.';
            }
        }

        // Diikutkan admin secara manual, tetapi level barunya belum punya aturan penilai.
        if ($lama->Updated_By !== null && $lama->Status_Peserta === 'DIIKUTKAN'
            && ($k->ID_Level === null || ! isset($snapshotPerLevel[(int) $k->ID_Level]))) {
            return 'Diikutkan manual oleh admin, tetapi level '.($k->nama_level ?? 'barunya').' belum punya aturan penilai.';
        }

        return null;
    }

    /**
     * Menulis ulang level peserta dengan aturan yang sama dengan pembentukan. Status yang pernah
     * DIPUTUSKAN admin (Updated_By terisi) dipertahankan — hanya level dan salinan aturannya yang
     * ikut level baru; keputusan manusia tidak ditimpa mesin.
     */
    private function segarkanLevelPeserta(object $lama, object $k, array $snapshotPerLevel, ?string $batasResign, array $praKecuali, ?int $minMasaKerja): void
    {
        if ($lama->Updated_By !== null) {
            $status = $lama->Status_Peserta;
            $alasan = $lama->Alasan_Dikecualikan;
            $snapshotId = $status === 'DIIKUTKAN' ? ($snapshotPerLevel[(int) $k->ID_Level] ?? null) : null;
        } else {
            [$status, $alasan, $snapshotId] = $this->nilaiKelayakan($k, $snapshotPerLevel, $batasResign, $praKecuali, $minMasaKerja);
        }

        DB::table('N_PA_Participant')->where('Id_PA_Participant', (int) $lama->Id_PA_Participant)->update([
            'ID_Level_Saat_Cycle' => $k->ID_Level,
            'Nama_Level_Saat_Cycle' => $k->nama_level,
            'ID_Level_HRIS_Saat_Cycle' => $k->ID_Level_HRIS,
            'Nama_Level_HRIS_Saat_Cycle' => $k->nama_level_hris,
            'PA_Karyawan_Level_PA_Id' => $k->Koreksi_Level_PA_Id,
            'PA_Cycle_Rater_Policy_Snapshot_Id' => $snapshotId,
            'Status_Peserta' => $status,
            'Alasan_Dikecualikan' => $alasan,
            // Updated_By SENGAJA tidak diubah: penyegaran ini kerja mesin, bukan keputusan admin.
            'Updated_At' => FormatTanggalHelper::getCurrentTime(),
        ]);
    }

    /**
     * Menentukan status peserta. Tiga hasil, dan ketiganya harus terlihat:
     *
     * - DIKECUALIKAN: sengaja tidak dinilai, alasannya tertulis.
     * - TERTUNDA: seharusnya dinilai tetapi levelnya belum punya aturan; ini yang
     *   memblokir penguncian.
     * - DIIKUTKAN: siap dinilai, dan salinan aturannya sudah ditunjuk.
     *
     * @param  array<int, int>  $snapshotPerLevel  peta level => id salinan, dimuat sekali
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    /**
     * Daftar pengecualian awal satu siklus, untuk ditampilkan.
     *
     * @return list<array<string, mixed>>
     */
    public function daftarPraKecuali(int $cycleId): array
    {
        return DB::table('N_PA_Cycle_Pre_Exclusion as x')
            ->leftJoin('Karyawan as k', function ($j) {
                $j->on('k.Kode_Perusahaan', '=', 'x.Kode_Perusahaan')
                    ->on('k.Kode_Karyawan', '=', 'x.Kode_Karyawan');
            })
            ->where('x.PA_Cycle_Id', $cycleId)
            ->where('x.Flag_Aktif', 'Y')
            ->orderBy('x.Kode_Karyawan')
            ->get([
                'x.Id_PA_Cycle_Pre_Exclusion as id',
                'x.Kode_Perusahaan as kode_perusahaan',
                'x.Kode_Karyawan as kode_karyawan',
                'x.Alasan as alasan',
                'x.Created_At as dibuat_pada',
                'k.Nama as nama',
                'k.Aktif as karyawan_aktif',
            ])
            ->map(fn ($r) => (array) $r + [
                /* Karyawan yang sudah tidak aktif TIDAK dibuang dari daftar, hanya
                   ditandai. Membuangnya membuat daftar berubah sendiri tanpa ada yang
                   mengubahnya, dan admin yang mencari namanya akan mengira ia terhapus. */
                'karyawan_sudah_tidak_aktif' => $r->karyawan_aktif !== 'Y',

                /* Yang dibaca layar untuk menampilkan tombol "Cabut Pengecualian". Daftar ini
                   hanya memuat baris aktif, jadi selalu benar — tetapi sampai 13 Okt field-nya
                   tidak dikirim sama sekali: tombolnya tidak pernah tampil dan lencananya
                   berbunyi "Dicabut" untuk orang yang masih dikecualikan. */
                'dikecualikan_saat_ini' => true,
            ])
            ->all();
    }

    /**
     * Menambahkan orang ke daftar pengecualian awal.
     *
     * Digerbangi `SUNTING_PESERTA`: daftar ini hanya bermakna sebelum siklus dikunci, dan
     * mengubahnya sesudah peserta terbentuk tidak akan mengubah apa pun — baris pesertanya
     * sudah ada. Untuk itu jalurnya `PaParticipantEditService`, bukan ini.
     *
     * @param  list<array{kode_perusahaan:string, kode_karyawan:string}>  $orang
     * @return array{ditambah:int, sudah_ada:int}
     */
    public function tambahPraKecuali(int $cycleId, array $orang, string $alasan, ?int $olehUserId = null): array
    {
        /* Gerbangnya KECUALIKAN_PESERTA (sampai OPEN), sama dengan tombol yang memanggilnya.
           Dulu SUNTING_PESERTA (DRAFT/PREPARED): sesudah dikunci tombolnya tampil tetapi selalu
           ditolak, dan pada PREPARED server menjawab "ditambahkan" padahal peserta yang sudah
           terbentuk tetap DIIKUTKAN beserta penilainya. */
        $this->gate->pastikanBoleh(PaCycleGate::KECUALIKAN_PESERTA, $cycleId);

        $alasan = trim($alasan);

        if ($alasan === '') {
            /* Wajib, dan bukan formalitas. Pengecualian tanpa alasan menghasilkan orang
               yang hilang dari penilaian tanpa siapa pun bisa menjawab kenapa — dan
               pertanyaan itu selalu datang setahun kemudian. */
            throw new RuntimeException(
                'Pengecualian wajib beralasan. Tanpa itu, setahun lagi tidak ada yang bisa '
                .'menjawab kenapa orang ini tidak dinilai.'
            );
        }

        if ($orang === []) {
            throw new RuntimeException('Tidak ada karyawan yang dipilih.');
        }

        $sudah = DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Kode_Perusahaan', 'Kode_Karyawan'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan => true])
            ->all();

        $baris = [];
        $sudahAda = 0;

        foreach ($orang as $o) {
            $kp = trim((string) ($o['kode_perusahaan'] ?? ''));
            $kk = trim((string) ($o['kode_karyawan'] ?? ''));

            if ($kp === '' || $kk === '') {
                continue;
            }

            if (isset($sudah[$kp.'|'.$kk])) {
                $sudahAda++;

                continue;
            }

            // Dicegah di sini supaya batch yang memuat orang yang sama dua kali tidak
            // menabrak indeks unik dan menggagalkan seluruh sisipannya.
            $sudah[$kp.'|'.$kk] = true;

            $baris[] = [
                'PA_Cycle_Id' => $cycleId,
                'Kode_Perusahaan' => $kp,
                'Kode_Karyawan' => $kk,
                'Alasan' => $alasan,
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
            ];
        }

        foreach (array_chunk($baris, 100) as $potongan) {
            DB::table('N_PA_Cycle_Pre_Exclusion')->insert($potongan);
        }

        /* Orang yang SUDAH menjadi peserta siklus ini ikut dikecualikan saat itu juga, lewat
           pintu yang sama dengan layar peserta — ia juga membatalkan penugasan yang belum diisi
           dan mencatat alasannya. Daftar pra-kecuali saja hanya berlaku saat peserta dibentuk. */
        $dikecualikan = 0;
        $edit = app(PaParticipantEditService::class);

        foreach ($orang as $o) {
            $pid = DB::table('N_PA_Participant')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Kode_Perusahaan', trim((string) ($o['kode_perusahaan'] ?? '')))
                ->where('Kode_Karyawan', trim((string) ($o['kode_karyawan'] ?? '')))
                ->where('Flag_Aktif', 'Y')
                ->where('Status_Peserta', '<>', PaParticipantEditService::STATUS_DIKECUALIKAN)
                ->value('Id_PA_Participant');

            if ($pid !== null) {
                $edit->ubahStatus((int) $pid, PaParticipantEditService::STATUS_DIKECUALIKAN, $alasan, (int) ($olehUserId ?? 0));
                $dikecualikan++;
            }
        }

        return ['ditambah' => count($baris), 'sudah_ada' => $sudahAda, 'peserta_dikecualikan' => $dikecualikan];
    }

    /**
     * Mencabut satu baris dari daftar — dinonaktifkan, bukan dihapus — dan MENGIKUTKAN
     * KEMBALI orangnya bila ia sudah terbentuk sebagai peserta.
     *
     * Baris yang dihapus membuat "kenapa dia dinilai padahal dulu dikecualikan" mustahil
     * dijawab. Indeks uniknya tersaring pada `Flag_Aktif = 'Y'`, jadi orang yang sama boleh
     * dikecualikan lagi kemudian dengan alasan lain.
     *
     * PESERTA YANG SUDAH TERBENTUK IKUT KEMBALI (13 Okt 2026). Sebelumnya pencabutan hanya
     * menyentuh daftar: pesertanya tetap DIKECUALIKAN, dan "Jalankan Ulang" sengaja tidak
     * mengubah peserta yang sudah ada — orangnya tidak bisa dikembalikan dari layar mana pun.
     * Sekarang ia diikutkan kembali lewat pintu yang sama dengan layar peserta
     * (`PaParticipantEditService::ubahStatus`), lalu Langkah 5 menyebutnya "belum
     * berpenugasan" untuk dilengkapi. Penugasan lamanya yang sudah dibatalkan tetap riwayat.
     *
     * HANYA yang dikecualikan OLEH BARIS INI: alasannya sama persis dengan alasan baris
     * daftar. Peserta yang dikecualikan atas sebab lain (resign, level tanpa aturan) tidak
     * ikut dikembalikan hanya karena namanya kebetulan juga ada di daftar — begitu pula
     * karyawan yang sudah tidak aktif di HRIS.
     *
     * Satu transaksi: bila pesertanya tidak bisa diikutkan (mis. levelnya belum punya
     * aturan penilai), pencabutannya pun batal — daftar yang berkata "dicabut" sementara
     * pesertanya tetap dikecualikan adalah keadaan yang tidak bisa dijelaskan siapa pun.
     *
     * `peserta_ada` membedakan "tidak dikembalikan karena sebab lain" dari "belum pernah
     * menjadi peserta" — yang kedua baru masuk lewat Jalankan Ulang Penyiapan.
     *
     * @return array{dicabut:bool, peserta_diikutkan:bool, peserta_ada:bool, nama:?string}
     */
    public function cabutPraKecuali(int $cycleId, int $id, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_PESERTA, $cycleId);

        return DB::transaction(function () use ($cycleId, $id, $olehUserId) {
            $baris = DB::table('N_PA_Cycle_Pre_Exclusion')
                ->where('Id_PA_Cycle_Pre_Exclusion', $id)
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Alasan']);

            $nama = $baris === null ? null : DB::table('Karyawan')
                ->where('Kode_Perusahaan', $baris->Kode_Perusahaan)
                ->where('Kode_Karyawan', $baris->Kode_Karyawan)
                ->value('Nama');

            if ($baris === null) {
                /* Menyebut siklusnya, bukan hanya id-nya. Kekeliruan yang paling lazim di sini
                   adalah mencabut baris milik siklus lain, dan "baris tidak ditemukan" tidak
                   memberi tahu bahwa itu yang terjadi. */
                throw new RuntimeException(
                    "Baris pengecualian {$id} tidak ada pada siklus {$cycleId}, atau sudah dicabut."
                );
            }

            DB::table('N_PA_Cycle_Pre_Exclusion')
                ->where('Id_PA_Cycle_Pre_Exclusion', $id)
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                    'Updated_By' => $olehUserId,
                ]);

            $peserta = DB::table('N_PA_Participant as p')
                ->join('Karyawan as k', function ($j) {
                    $j->on('k.Kode_Perusahaan', '=', 'p.Kode_Perusahaan')
                        ->on('k.Kode_Karyawan', '=', 'p.Kode_Karyawan');
                })
                ->where('p.PA_Cycle_Id', $cycleId)
                ->where('p.Kode_Perusahaan', $baris->Kode_Perusahaan)
                ->where('p.Kode_Karyawan', $baris->Kode_Karyawan)
                ->where('p.Flag_Aktif', 'Y')
                ->where('p.Status_Peserta', PaParticipantEditService::STATUS_DIKECUALIKAN)
                ->where('p.Alasan_Dikecualikan', $baris->Alasan)
                ->where('k.Aktif', 'Y')
                ->first(['p.Id_PA_Participant', 'p.ID_Level_Saat_Cycle', 'p.PA_Cycle_Rater_Policy_Snapshot_Id']);

            if ($peserta === null) {
                $ada = DB::table('N_PA_Participant')
                    ->where('PA_Cycle_Id', $cycleId)
                    ->where('Kode_Perusahaan', $baris->Kode_Perusahaan)
                    ->where('Kode_Karyawan', $baris->Kode_Karyawan)
                    ->where('Flag_Aktif', 'Y')
                    ->exists();

                return ['dicabut' => true, 'peserta_diikutkan' => false, 'peserta_ada' => $ada, 'nama' => $nama];
            }

            /* PENUNJUK ATURAN DIISI LEBIH DULU (UAT 13 Okt). Penyiapan hanya mengisinya untuk
               peserta DIIKUTKAN; orang yang dikecualikan sejak pertama dibentuk lahir tanpa
               penunjuk, dan `ubahStatus()` lalu menolak "levelnya belum punya aturan" padahal
               levelnya punya. Diisi dari salinan aturan level ITU di siklus ini — bila memang
               tidak ada, penolakannya benar dan tetap terjadi. */
            if ($peserta->PA_Cycle_Rater_Policy_Snapshot_Id === null && $peserta->ID_Level_Saat_Cycle !== null) {
                $snapshot = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
                    ->where('PA_Cycle_Id', $cycleId)
                    ->where('ID_Level', (int) $peserta->ID_Level_Saat_Cycle)
                    ->where('Flag_Aktif', 'Y')
                    ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

                if ($snapshot !== null) {
                    DB::table('N_PA_Participant')
                        ->where('Id_PA_Participant', $peserta->Id_PA_Participant)
                        ->update(['PA_Cycle_Rater_Policy_Snapshot_Id' => (int) $snapshot]);
                }
            }

            try {
                app(PaParticipantEditService::class)->ubahStatus(
                    (int) $peserta->Id_PA_Participant,
                    PaParticipantEditService::STATUS_DIIKUTKAN,
                    'Pengecualian dicabut.',
                    (int) ($olehUserId ?? 0),
                );
            } catch (RuntimeException $e) {
                throw new RuntimeException(
                    'Pengecualian tidak dicabut: orangnya sudah menjadi peserta dan tidak bisa '
                    .'diikutkan kembali. '.$e->getMessage(),
                    0,
                    $e,
                );
            }

            return ['dicabut' => true, 'peserta_diikutkan' => true, 'peserta_ada' => true, 'nama' => $nama];
        });
    }

    /**
     * Mencabut BANYAK baris sekaligus — tiap baris lewat `cabutPraKecuali()` sendiri-sendiri.
     *
     * Satu-satu, bukan satu UPDATE: tiap baris punya transaksi dan penjaganya sendiri
     * (pesertanya bisa ditolak diikutkan karena levelnya belum punya aturan). Yang gagal
     * dikumpulkan beserta sebabnya; sisanya tetap tercabut.
     *
     * Jawabannya membawa LANGKAH BERIKUTNYA yang dibaca layar:
     *   `belum_peserta`  orang yang belum pernah menjadi peserta — masuk lewat Jalankan Ulang.
     *   `ada_penugasan`  penugasan siklus ini sudah pernah dibentuk — yang diikutkan kembali
     *                    belum punya penugasan dan harus dilengkapi di Langkah 5.
     *
     * @param  list<int>  $ids
     * @return array{dicabut:int, diikutkan:int, belum_peserta:int, ada_penugasan:bool, gagal:list<array{id:int, nama:?string, sebab:string}>}
     */
    public function cabutPraKecualiBanyak(int $cycleId, array $ids, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::SUNTING_PESERTA, $cycleId);

        $hasil = ['dicabut' => 0, 'diikutkan' => 0, 'belum_peserta' => 0, 'ada_penugasan' => false, 'gagal' => []];

        foreach (array_values(array_unique(array_map('intval', $ids))) as $id) {
            try {
                $r = $this->cabutPraKecuali($cycleId, $id, $olehUserId);
                $hasil['dicabut']++;
                $hasil['diikutkan'] += $r['peserta_diikutkan'] ? 1 : 0;
                $hasil['belum_peserta'] += $r['peserta_ada'] ? 0 : 1;
            } catch (RuntimeException $e) {
                $hasil['gagal'][] = [
                    'id' => $id,
                    'nama' => DB::table('N_PA_Cycle_Pre_Exclusion as x')
                        ->leftJoin('Karyawan as k', function ($j) {
                            $j->on('k.Kode_Perusahaan', '=', 'x.Kode_Perusahaan')
                                ->on('k.Kode_Karyawan', '=', 'x.Kode_Karyawan');
                        })
                        ->where('x.Id_PA_Cycle_Pre_Exclusion', $id)
                        ->value(DB::raw('COALESCE(k.Nama, x.Kode_Karyawan)')),
                    'sebab' => $e->getMessage(),
                ];
            }
        }

        $hasil['ada_penugasan'] = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('a.Flag_Aktif', 'Y')
            ->exists();

        return $hasil;
    }

    /**
     * Daftar pengecualian yang ditulis admin sebelum peserta dibentuk.
     *
     * Dibaca SEKALI per penyiapan, bukan sekali per karyawan. Pada 958 orang, menanyakannya
     * per baris berarti 958 perjalanan ke database untuk jawaban yang sama — pola N+1 yang
     * sudah dua kali menjadi sebab CPU 100 persen di repo ini.
     *
     * @return array<string, string> "perusahaan|karyawan" => alasan
     */
    private function praKecuali(int $cycleId): array
    {
        return DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Alasan'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan => (string) $r->Alasan])
            ->all();
    }

    private function nilaiKelayakan(
        object $k,
        array $snapshotPerLevel,
        ?string $batasResign,
        array $praKecuali = [],
        ?int $minMasaKerja = null,
    ): array {
        /* Daftar pengecualian yang dibuat admin SEBELUM peserta dibentuk, diperiksa paling
           awal — sebelum aturan otomatis mana pun.

           Urutannya disengaja: keputusan manusia menang atas aturan mesin. Kalau seseorang
           sudah ditulis di daftar dengan alasan tertentu, alasan ITU yang tercatat, bukan
           "Level FREELANCE dikecualikan" yang kebetulan juga berlaku. Yang membaca setahun
           kemudian perlu tahu bahwa ada orang yang memutuskannya, bukan aturan. */
        $kunci = $k->Kode_Perusahaan.'|'.$k->Kode_Karyawan;

        if (isset($praKecuali[$kunci])) {
            return ['DIKECUALIKAN', $praKecuali[$kunci], null];
        }

        /* Alasannya dibawa dari datanya, bukan dikarang ulang di sini: "DIKECUALIKAN"
           tanpa sebab menghasilkan orang yang hilang dari penilaian tanpa siapa pun bisa
           menjawab kenapa, dan setahun kemudian tidak ada yang berani mencabutnya. */
        if (PaLevelDikecualikan::apakah($k->ID_Level)) {
            return ['DIKECUALIKAN', PaLevelDikecualikan::alasan($k->ID_Level), null];
        }

        /* Karyawan yang mundur sebelum periode penilaian berakhir tidak dinilai:
           penilainya tidak akan sempat mengisi, dan hasilnya akan tertahan selamanya.
           Pemeriksaannya hanya berjalan bila siklusnya sudah punya tanggal akhir —
           siklus DRAFT boleh belum punya, dan menebak tanggalnya lebih buruk daripada
           melewatkan pemeriksaannya. */
        if ($batasResign !== null && $k->Tanggal_Resign !== null
            && substr((string) $k->Tanggal_Resign, 0, 10) <= $batasResign) {
            return [
                'DIKECUALIKAN',
                'Mengundurkan diri pada '.substr((string) $k->Tanggal_Resign, 0, 10)
                .', sebelum periode penilaian berakhir.',
                null,
            ];
        }

        /* Masa kerja dihitung terhadap akhir periode yang SAMA dengan pemeriksaan resign, dan
           batas bulannya dari salinan siklus — bukan master — supaya mengubah master tidak
           diam-diam mengubah siapa yang dinilai di siklus yang sudah berjalan. */
        $alasanMasaKerja = PaKelayakanMasaKerjaService::alasanTidakLayak(
            $k->Tgl_Masuk ?? null, $batasResign, $minMasaKerja
        );
        if ($alasanMasaKerja !== null) {
            return ['DIKECUALIKAN', $alasanMasaKerja, null];
        }

        if ($k->ID_Level === null) {
            return ['TERTUNDA', null, null];
        }

        $snapshotId = $snapshotPerLevel[(int) $k->ID_Level] ?? null;

        if ($snapshotId === null) {
            // Aturan #15: peserta DIIKUTKAN wajib menunjuk salinan aturan. Tanpa
            // salinan, satu-satunya jawaban jujur adalah TERTUNDA.
            return ['TERTUNDA', null, null];
        }

        return ['DIIKUTKAN', null, $snapshotId];
    }

    /* ------------------------------------------------------------------ *
     * 3. Membentuk baris hasil                                            *
     * ------------------------------------------------------------------ */

    /**
     * Satu baris hasil per peserta, dibuat SEJAK AWAL berstatus BELUM_DIHITUNG.
     *
     * Kalau barisnya baru muncul setelah perhitungan berhasil, ketiadaan baris jadi
     * bermakna ganda. Dengan barisnya selalu ada, Status_Hasil yang menjawab dan layar
     * monitoring cukup menghitung per status.
     */
    private function bentukBarisHasil(int $cycleId): int
    {
        return DB::insert(
            'INSERT INTO N_PA_Result (PA_Participant_Id)
             SELECT p.Id_PA_Participant
             FROM N_PA_Participant p
             WHERE p.PA_Cycle_Id = ?
               AND p.Flag_Aktif = ?
               AND NOT EXISTS (
                   SELECT 1 FROM N_PA_Result r
                   WHERE r.PA_Participant_Id = p.Id_PA_Participant AND r.Flag_Aktif = ?
               )',
            [$cycleId, 'Y', 'Y']
        );
    }

    /* ------------------------------------------------------------------ *
     * Pembacaan sumber                                                    *
     * ------------------------------------------------------------------ */

    /**
     * Level TIDAK ada di tabel Karyawan; nilainya diresolusi dua lompatan lewat view
     * golongan (K19). Divisi induk juga turunan, lewat view divisi.
     *
     * Keduanya dijoin LEFT supaya karyawan yang gagal diresolusi tetap terbaca dan
     * mendarat di keranjang TERTUNDA — bukan hilang diam-diam dari daftar.
     */
    /**
     * Karyawan aktif dengan level EFEKTIF untuk PA (DDL-2026-177): `ID_Level`/`nama_level` sudah
     * memakai koreksi Level PA yang berlaku pada tanggal acuan siklus; level HRIS aslinya ada di
     * `ID_Level_HRIS`/`nama_level_hris`, koreksinya di `Koreksi_Level_PA_Id`. Semua keputusan
     * peserta (pengecualian level, aturan penilai, salinan) membaca level efektif ini.
     */
    private function karyawanAktif(?int $cycleId = null): \Generator
    {
        $resolver = app(PaLevelPaResolver::class);
        $koreksi = $resolver->koreksiBerlaku($cycleId === null ? null : $this->tanggalAcuanLevel($cycleId));

        foreach ($this->karyawanAktifHris() as $k) {
            $r = $resolver->resolusi($k, $koreksi);
            $k->ID_Level_HRIS = $r['id_level_hris'];
            $k->nama_level_hris = $r['nama_level_hris'];
            $k->ID_Level = $r['id_level'];
            $k->nama_level = $r['nama_level'];
            $k->Koreksi_Level_PA_Id = $r['koreksi_id'];
            $k->Level_PA_Perlu_Ditinjau = $r['perlu_ditinjau'];

            yield $k;
        }
    }

    private function tanggalAcuanLevel(int $cycleId): ?string
    {
        $c = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->first(['Tanggal_Acuan_Organisasi', 'Periode_Dinilai_Sampai']);
        $t = $c?->Tanggal_Acuan_Organisasi ?? $c?->Periode_Dinilai_Sampai;

        return $t === null ? null : substr((string) $t, 0, 10);
    }

    private function karyawanAktifHris()
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as d',
                'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->orderBy('k.Kode_Perusahaan')
            ->orderBy('k.Kode_Karyawan')
            ->select([
                'k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'k.ID_Level_Jabatan',
                'k.ID_Divisi_Sub_Divisi', 'k.Kode_Jabatan', 'k.Lokasi', 'k.Tanggal_Resign', 'k.Tgl_Masuk',
                'v.ID_Level', 'v.nama_level', 'v.nama_jabatan',
                'd.ID_Divisi', 'd.nama_divisi', 'd.ID_Sub_Divisi', 'd.nama_sub_divisi',
            ])
            ->cursor();
    }

    /**
     * Tanggal akhir periode yang dinilai, dipakai memutuskan siapa yang keburu mundur.
     *
     * Mengembalikan null bila siklusnya belum punya tanggal — dan itu bukan kegagalan.
     * Siklus DRAFT memang boleh dibuat sebelum jadwalnya ditetapkan (K30), jadi pada
     * tahap itu pemeriksaan resign dilewati alih-alih menebak batasnya.
     */
    /**
     * Minimal masa kerja (bulan) yang berlaku di siklus ini — salinan siklus, bukan master.
     * Null untuk siklus yang dibuat sebelum aturan ini ada: aturannya tidak berlaku di sana.
     */
    private function minMasaKerja(int $cycleId): ?int
    {
        $n = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->value('Min_Masa_Kerja_Bulan');

        return $n === null ? null : (int) $n;
    }

    private function batasResign(int $cycleId): ?string
    {
        $tgl = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->value('Periode_Dinilai_Sampai');

        return $tgl === null ? null : substr((string) $tgl, 0, 10);
    }

    /**
     * Level yang benar-benar dipakai karyawan aktif. Menyalin aturan untuk 16 level
     * padahal hanya 7 yang berisi orang akan membuat salinan siklus penuh baris yang
     * tidak pernah dibaca siapa pun.
     *
     * @return list<int>
     */
    private function levelYangDipakai(int $cycleId): array
    {
        // Level PA (DDL-2026-177): level hasil koreksi per karyawan ikut dipakai.
        $lv = PaLevelPaResolver::sqlLevel();

        return PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'), 'k', 'v', $this->tanggalAcuanLevel($cycleId))
            ->where('k.Aktif', 'Y')
            ->whereNotIn(DB::raw($lv), PaLevelDikecualikan::daftar() ?: [0])
            ->distinct()
            ->orderBy('lv')
            ->select(DB::raw("{$lv} as lv"))
            ->pluck('lv')
            ->map(fn ($x) => (int) $x)
            ->all();
    }

    /**
     * Menandai langkah PESERTA dari KEADAAN TABEL, bukan dari selisih penyiapan barusan.
     *
     * `siapkan()` memulangkan berapa baris yang IA bentuk. Dijalankan ulang atas siklus
     * yang pesertanya sudah lengkap, ia memulangkan nol untuk semuanya — dan penanda yang
     * membaca angka itu akan menulis "0 diikutkan" pada siklus berisi 857 orang.
     *
     * Kekeliruan yang sama menghasilkan penanda SELESAI pada siklus yang benar-benar tidak
     * membentuk siapa pun, karena nol tidak bisa dibedakan dari nol.
     */
    private function tandaiLangkahPeserta(int $cycleId, ?int $olehUserId = null): void
    {
        $cacah = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->selectRaw(
                "SUM(CASE WHEN Status_Peserta = 'DIIKUTKAN' THEN 1 ELSE 0 END) as diikutkan, "
                ."SUM(CASE WHEN Status_Peserta = 'DIKECUALIKAN' THEN 1 ELSE 0 END) as dikecualikan, "
                ."SUM(CASE WHEN Status_Peserta = 'TERTUNDA' THEN 1 ELSE 0 END) as tertunda"
            )
            ->first();

        $diikutkan = (int) ($cacah->diikutkan ?? 0);
        $dikecualikan = (int) ($cacah->dikecualikan ?? 0);
        $tertunda = (int) ($cacah->tertunda ?? 0);

        /* Nol peserta diikutkan BUKAN langkah yang selesai. Siklus tanpa satu pun peserta
           akan berjalan sampai akhir tanpa satu pun nilai, dan tak ada yang menyadarinya
           sampai hasilnya kosong. */
        $selesai = $diikutkan > 0;

        $this->langkah->tandai(
            $cycleId,
            'PESERTA',
            $selesai
                ? PaCycleSetupStepService::STATUS_SELESAI
                : PaCycleSetupStepService::STATUS_DRAFT,
            $selesai
                ? sprintf('%d diikutkan, %d dikecualikan, %d tertunda.', $diikutkan, $dikecualikan, $tertunda)
                : 'Belum ada satu pun peserta yang diikutkan.',
            $olehUserId,
        );
    }
}
