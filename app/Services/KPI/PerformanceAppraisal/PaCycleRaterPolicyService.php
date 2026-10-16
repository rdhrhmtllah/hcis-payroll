<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Aturan penilai MILIK SATU SIKLUS — siapa menilai siapa, dan berapa bobot tiap kategori.
 *
 * MASALAH YANG DISELESAIKAN
 *
 * Aturan penilai sampai sekarang hanya ada sebagai master global. Salinan per siklus
 * memang dibentuk, tetapi dibentuknya DI DALAM `siapkan()` — sesudah admin menekan tombol,
 * bersamaan dengan ratusan baris peserta yang lahir darinya. Praktisnya satu-satunya
 * kesempatan melihat aturan yang dipakai siklus adalah sesudah ia terlanjur dipakai, dan
 * satu-satunya cara mengubahnya adalah mengubah master — yang berarti mengubahnya untuk
 * semua siklus sekaligus, termasuk yang sudah berjalan.
 *
 * Layanan ini memindahkan penyalinan ke DEPAN dan membuat salinannya bisa disunting.
 *
 * KENAPA MENYUNTING SALINAN, BUKAN MASTER
 *
 * Master adalah cetakan; salinan adalah yang berlaku. Siklus 2026 boleh memberi bobot
 * atasan 60% sementara siklus 2025 memakai 50%, dan keduanya harus tetap benar ketika
 * dibaca ulang tahun depan. Menyunting master akan menulis ulang sejarah siklus yang sudah
 * selesai — angka yang sudah dilihat orang berubah tanpa ada yang mengubahnya.
 *
 * BOBOT DIPERIKSA SAAT DISIMPAN, BUKAN SAAT DIPAKAI
 *
 * Bobot antar kategori wajib berjumlah 100. Memeriksanya saat penyiapan berjalan berarti
 * admin baru tahu aturannya salah ketika ratusan baris sudah terbentuk dan seluruh
 * transaksinya harus dibatalkan.
 */
class PaCycleRaterPolicyService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaBobotGuard $bobot,
        private readonly PaCyclePreparationService $penyiapan,
        private readonly PaCycleSetupStepService $langkah,
    ) {}

    /**
     * Aturan penilai siklus ini, per level.
     *
     * Bila salinannya belum ada, dikembalikan daftar kosong dengan `perlu_disalin: true` —
     * BUKAN diam-diam disalin. Menyalin pada jalur BACA berarti satu kunjungan halaman
     * mengubah data, dan admin yang hanya melihat-lihat meninggalkan jejak yang tidak ia
     * maksudkan.
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

        /* Nama level diambil dari `N_PA_Level` (Master Level PA), BUKAN dari view level-jabatan. View itu
           berbaris per (level x jabatan), jadi menggabungnya di sini menghasilkan puluhan
           baris untuk satu aturan dan menuntut GROUP BY atas seluruh kolom hanya untuk
           memulihkan satu nama. */
        $kepala = DB::table('N_PA_Cycle_Rater_Policy_Snapshot as s')
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', 's.ID_Level')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->where('s.Flag_Aktif', 'Y')
            ->orderBy('s.ID_Level')
            ->get([
                's.Id_PA_Cycle_Rater_Policy_Snapshot as id',
                's.ID_Level as id_level',
                'l.Nama_Level as nama_level',
                's.Kode_Policy_Salinan as kode_policy',
                's.Nama_Policy_Salinan as nama_policy',
                's.Versi_Policy_Salinan as versi_policy',
                's.Deskripsi_Policy_Salinan as deskripsi',
                's.Snapshot_At as disalin_pada',
                's.Updated_At as disunting_pada',
            ]);

        if ($kepala->isEmpty()) {
            return [
                'cycle' => $this->ringkasSiklus($siklus),
                'level' => [],
                'perlu_disalin' => true,
                'boleh_disunting' => $this->gate->boleh(PaCycleGate::BUAT_PENUGASAN, $cycleId),
            ];
        }

        /* Seluruh rincian diambil dalam SATU kueri lalu dikelompokkan di memori. Satu kueri
           per level terlihat rapi dan berubah menjadi puluhan perjalanan ke database untuk
           halaman yang dibuka satu kali. */
        $rincian = DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
            ->whereIn('PA_Cycle_Rater_Policy_Snapshot_Id', $kepala->pluck('id')->all())
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get()
            ->groupBy('PA_Cycle_Rater_Policy_Snapshot_Id');

        $level = $kepala->map(function ($k) use ($rincian) {
            $baris = ($rincian[$k->id] ?? collect())->map(fn ($d) => [
                'id' => (int) $d->Id_PA_Cycle_Rater_Policy_Detail_Snapshot,
                'kategori_id' => (int) $d->PA_Rater_Category_Id,
                'kode_kategori' => $d->Kode_Kategori_Salinan,
                'nama_kategori' => (strtoupper($d->Kode_Kategori_Salinan ?? '') === 'PEER' || str_contains(strtolower($d->Nama_Kategori_Salinan ?? ''), 'sejawat')) ? 'Peer' : $d->Nama_Kategori_Salinan,
                'kode_relasi' => $d->Kode_Relasi_Salinan,
                // SQL Server memulangkan decimal sebagai string; layar menghitung dengannya.
                'bobot_persen' => (float) $d->Bobot_Persen,
                'min_rater' => (int) $d->Min_Rater,
                'maks_rater' => $d->Maks_Rater === null ? null : (int) $d->Maks_Rater,
                // NULL dipertahankan; layar menampilkannya sebagai "ikuti minimum penilai".
                'min_rater_selesai' => $d->Min_Rater_Selesai === null
                    ? null
                    : (int) $d->Min_Rater_Selesai,
                'izinkan_manual' => $d->Flag_Izinkan_Manual === 'Y',
                'urutan' => (int) $d->Urutan_Tampil,
                'catatan' => $d->Catatan_Aturan_Salinan,
            ])->values()->all();

            return [
                'id' => (int) $k->id,
                'id_level' => (int) $k->id_level,
                'nama_level' => $k->nama_level ?? "Level {$k->id_level}",
                'kode_policy' => $k->kode_policy,
                'nama_policy' => $k->nama_policy,
                'versi_policy' => (int) $k->versi_policy,
                'deskripsi' => $k->deskripsi,
                'disalin_pada' => $k->disalin_pada,
                'disunting_pada' => $k->disunting_pada,
                'rincian' => $baris,
                'total_bobot' => round(array_sum(array_column($baris, 'bobot_persen')), 2),
            ];
        })->all();

        return [
            'cycle' => $this->ringkasSiklus($siklus),
            'level' => $level,
            'perlu_disalin' => false,
            'boleh_disunting' => $this->gate->boleh(PaCycleGate::BUAT_PENUGASAN, $cycleId),
            'level_tanpa_aturan' => $this->penyiapan->levelTanpaAturan($cycleId),
        ];
    }

    /**
     * Menyalin aturan dari master ke siklus ini.
     *
     * Jalur TULIS yang eksplisit — admin menekan "Salin dari master" dan tahu ia sedang
     * membuat sesuatu. Aman diulang: level yang salinannya sudah ada dilewati, sehingga
     * menekannya dua kali tidak menggandakan apa pun dan tidak menghapus suntingan.
     *
     * @return array<string, int>
     */
    public function salinDariMaster(int $cycleId, ?int $olehUserId = null): array
    {
        $hasil = $this->penyiapan->salinCetakBiru($cycleId);

        $this->langkah->tandai(
            $cycleId,
            'ATURAN_PENILAI',
            PaCycleSetupStepService::STATUS_DRAFT,
            sprintf('%d level disalin dari master.', $hasil['policy'] ?? 0),
            $olehUserId,
        );

        return $hasil;
    }

    /**
     * Menyimpan rincian aturan satu level.
     *
     * Disimpan SEKALIGUS per level, bukan per baris: bobotnya wajib berjumlah 100, dan
     * angka itu hanya bermakna bila seluruh barisnya dilihat bersamaan. Menyimpan satu
     * baris pada satu waktu membuat setiap penyimpanan tengah jalan melanggar aturannya.
     *
     * @param  list<array<string, mixed>>  $rincian
     * @return array<string, mixed>
     */
    public function simpan(int $cycleId, int $levelId, array $rincian, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::BUAT_PENUGASAN, $cycleId);

        $snapshotId = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_PA_Cycle_Rater_Policy_Snapshot');

        if ($snapshotId === null) {
            throw new RuntimeException(
                "Siklus ini belum punya salinan aturan penilai untuk level {$levelId}. "
                .'Salin dari master lebih dahulu.'
            );
        }

        $milik = DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
            ->where('PA_Cycle_Rater_Policy_Snapshot_Id', $snapshotId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('PA_Rater_Category_Id', 'Id_PA_Cycle_Rater_Policy_Detail_Snapshot')
            ->all();

        $bobotPerKategori = [];
        $perubahan = [];

        foreach ($rincian as $r) {
            $id = (int) ($r['id'] ?? 0);

            if (! array_key_exists($id, $milik)) {
                /* Menyebut siklus dan levelnya, bukan hanya id. Kekeliruan yang paling
                   lazim di sini adalah mengirim baris milik level lain — dan "baris tidak
                   ditemukan" tidak memberi tahu bahwa itu yang terjadi. */
                throw new RuntimeException(
                    "Baris aturan {$id} bukan milik level {$levelId} pada siklus {$cycleId}."
                );
            }

            $bobot = (float) ($r['bobot_persen'] ?? 0);
            $minRater = (int) ($r['min_rater'] ?? 0);
            $maksRater = ($r['maks_rater'] ?? null) === null ? null : (int) $r['maks_rater'];
            /* NULL DIPERTAHANKAN, tidak dijadikan nol.

               NULL berarti "ikuti Min_Rater" — seluruh penilai minimum harus selesai.
               `PaAssignmentBuilderService` sudah menanganinya: `$min_rater_selesai ?? $min_rater`.

               Memaksanya jadi 0 mengubah artinya menjadi "nol yang harus selesai", dan
               `$selesai >= 0` selalu benar: penjaga penghitungan berhenti bekerja, kategori
               tanpa satu pun pengisi tetap lolos, dan hasil terhitung dari nol jawaban.
               Komentar di builder itu memperingatkan tepat hal ini. */
            $minSelesai = ($r['min_rater_selesai'] ?? null) === null
                || $r['min_rater_selesai'] === ''
                ? null
                : (int) $r['min_rater_selesai'];

            if ($maksRater !== null && $maksRater < $minRater) {
                throw new RuntimeException(
                    "Maksimum penilai ({$maksRater}) lebih kecil daripada minimum ({$minRater}) "
                    ."pada kategori {$milik[$id]}."
                );
            }

            if ($minSelesai !== null && $minSelesai < 1) {
                /* Nol tidak pernah sah. Kalau maksudnya "ikuti minimum penilai", kosongkan
                   saja — itu arti NULL. Nol berarti "tidak seorang pun perlu mengisi", dan
                   tidak ada yang pernah bermaksud begitu. */
                throw new RuntimeException(
                    "Minimum penilai selesai tidak boleh nol pada kategori {$milik[$id]}. "
                    .'Kosongkan bila seluruh penilai minimum harus menyelesaikan.'
                );
            }

            if ($minSelesai !== null && $minSelesai > $minRater) {
                /* Menuntut lebih banyak penilai SELESAI daripada yang ditugaskan membuat
                   peserta itu tidak akan pernah bisa dihitung, dan tidak ada satu pun pesan
                   yang akan muncul — hasilnya hanya diam di antrean selamanya. */
                throw new RuntimeException(
                    "Minimum penilai selesai ({$minSelesai}) melebihi minimum penilai ({$minRater}) "
                    ."pada kategori {$milik[$id]}; sebagian peserta tidak akan pernah bisa dihitung."
                );
            }

            $bobotPerKategori[$milik[$id]] = $bobot;

            $perubahan[$id] = [
                'Bobot_Persen' => $bobot,
                'Min_Rater' => $minRater,
                'Maks_Rater' => $maksRater,
                'Min_Rater_Selesai' => $minSelesai,
                'Flag_Izinkan_Manual' => ($r['izinkan_manual'] ?? false) ? 'Y' : 'T',
                'Catatan_Aturan_Salinan' => $r['catatan'] ?? null,
            ];
        }

        if ($perubahan === []) {
            throw new RuntimeException('Tidak ada rincian aturan yang dikirim.');
        }

        if (count($perubahan) !== count($milik)) {
            /* Kirim SELURUH barisnya. Menyimpan sebagian membuat bobot yang diperiksa
               berbeda dari bobot yang tersimpan — pemeriksaannya lulus, datanya salah. */
            throw new RuntimeException(sprintf(
                'Aturan level %d punya %d kategori, tetapi %d yang dikirim. '
                .'Bobot hanya bisa diperiksa bila seluruh kategorinya dikirim bersamaan.',
                $levelId, count($milik), count($perubahan),
            ));
        }

        $this->bobot->pastikanSeratus($bobotPerKategori, "aturan penilai level {$levelId}");

        $sekarang = FormatTanggalHelper::getCurrentTime();

        DB::transaction(function () use ($perubahan, $sekarang, $olehUserId) {
            foreach ($perubahan as $id => $nilai) {
                DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
                    ->where('Id_PA_Cycle_Rater_Policy_Detail_Snapshot', $id)
                    ->update($nilai + ['Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);
            }
        });

        DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('Id_PA_Cycle_Rater_Policy_Snapshot', $snapshotId)
            ->update(['Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);

        $this->tandaiLangkah($cycleId, $olehUserId);

        return ['level' => $levelId, 'baris_diperbarui' => count($perubahan)];
    }

    /**
     * Menandai langkah ATURAN_PENILAI selesai bila SETIAP level yang dipakai sudah punya
     * salinan yang bobotnya genap 100.
     *
     * Ditandai dari keadaan sebenarnya, bukan dari "admin baru saja menekan simpan".
     * Menandainya selesai karena satu level tersimpan membuat penyiapan berjalan dengan
     * level lain yang aturannya masih kosong.
     */
    private function tandaiLangkah(int $cycleId, ?int $olehUserId): void
    {
        $keadaan = $this->daftar($cycleId);
        $level = $keadaan['level'];
        $belumGenap = array_filter($level, fn ($l) => abs($l['total_bobot'] - 100.0) > 0.01);
        $tanpaAturan = $keadaan['level_tanpa_aturan'] ?? [];

        $selesai = $level !== [] && $belumGenap === [] && $tanpaAturan === [];

        $this->langkah->tandai(
            $cycleId,
            'ATURAN_PENILAI',
            $selesai ? PaCycleSetupStepService::STATUS_SELESAI : PaCycleSetupStepService::STATUS_DRAFT,
            $selesai
                ? sprintf('%d level, bobot genap 100 semua.', count($level))
                : sprintf(
                    '%d level tersalin; %d bobot belum genap, %d level belum punya aturan.',
                    count($level), count($belumGenap), count($tanpaAturan),
                ),
            $olehUserId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function ringkasSiklus(object $siklus): array
    {
        return [
            'id' => (int) $siklus->Id_PA_Cycle,
            'kode' => $siklus->Kode_Cycle,
            'nama' => $siklus->Nama_Cycle,
            'status' => $siklus->Status_Cycle,
        ];
    }
}
