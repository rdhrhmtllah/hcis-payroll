<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya tempat yang boleh menggabungkan salinan aturan siklus dengan
 * penyimpangan per peserta.
 *
 * Konfigurasi PA berlapis tiga. Cetak biru hidup lintas siklus. Setiap siklus
 * menyalinnya jadi salinan per level yang membeku saat kunci. Lapisan ketiga
 * TIDAK menyalin apa pun — ia hanya menyimpan yang menyimpang, dan normalnya
 * ketiga tabel override itu kosong (K32).
 *
 * Bentuk jarang itu memindahkan risiko dari jalur tulis ke jalur baca: bug tulis
 * harus dibereskan dengan memperbaiki data, bug baca cukup dengan memperbaiki
 * kode. Pertukaran itu hanya menguntungkan kalau jalur bacanya BENAR-BENAR satu.
 * Karena itu:
 *
 *   TIDAK ADA kode lain yang boleh membaca N_PA_Cycle_Rater_Policy_Detail_Snapshot,
 *   N_PA_Cycle_Pillar_Weight_Snapshot, N_PA_Cycle_Competency_Requirement_Snapshot,
 *   atau ketiga tabel N_PA_Participant_*_Override untuk keperluan per orang.
 *
 * Aturan itu ditegakkan uji, bukan sekadar dititipkan di komentar — lihat
 * tests/Feature/KPI/PerformanceAppraisal/ResolverSatuPintuTest.php.
 *
 * Service ini HANYA MEMBACA. Ia tidak menulis, tidak memvalidasi bahwa bobot
 * berjumlah 100, dan tidak peduli status siklus. Penjumlahan bobot adalah
 * gerbang saat penguncian, bukan urusan pembaca.
 */
class PaParticipantRuleResolver
{
    /** Nilainya datang dari salinan siklus; peserta ini mengikuti standar levelnya. */
    public const SUMBER_SALINAN = 'SALINAN';

    /** Nilainya diubah khusus untuk peserta ini. */
    public const SUMBER_OVERRIDE = 'OVERRIDE';

    /** Kategori/jenis ini tidak ada di salinan level, ditambahkan untuk peserta ini. */
    public const SUMBER_TAMBAHAN = 'TAMBAHAN';

    /**
     * Memo per instance. Salinan siklus dibaca sekali per (siklus, level) lalu
     * dipakai ulang, karena 958 peserta hanya bermuara pada belasan level.
     *
     * Sengaja TIDAK memakai cache lintas request: salinan siklus boleh berubah
     * sebelum kunci, dan aturan basi jauh lebih mahal daripada satu kueri.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * Siklus yang baris override-nya sudah dipramuat seluruhnya.
     *
     * @var array<int, true>
     */
    private array $overrideTermuat = [];

    /**
     * Aturan penilai yang berlaku bagi satu peserta, per kategori.
     *
     * Kolom yang override TIDAK pernah bawa — sumber kandidat relasi, izin
     * memilih manual, urutan tampil — tetap disertakan di sini supaya pemanggil
     * tidak punya alasan menyentuh tabel salinan sendiri.
     *
     * @return array<string, array{
     *     kategori_id:int, kode_kategori:string, nama_kategori:string,
     *     bobot_persen:float, min_rater:int, maks_rater:int|null,
     *     min_rater_selesai:int|null, urutan_tampil:int|null,
     *     relationship_type_id:int|null, kode_relasi:string|null,
     *     flag_izinkan_manual:string|null,
     *     sumber:string, alasan_perubahan:string|null
     * }> dikunci kode kategori
     */
    public function raterRules(int $participantId): array
    {
        /*
         | DIMEMOKAN per peserta dalam satu proses.
         |
         | `usulkan()` dan `tugaskan()` sama-sama memanggilnya untuk peserta yang sama, sehingga
         | snapshot aturan dan override dibaca DUA KALI berturut-turut untuk jawaban yang persis
         | sama. Pada DB yang 74 ms per perjalanan, itu setengah detik terbuang per peserta.
         |
         | Memo dibuang lewat `lupakanMemo()`, yang memang sudah dipanggil setiap kali salinan
         | siklus diubah — jadi tidak ada jalan bagi aturan basi untuk bertahan melewati
         | perubahannya.
         */
        $kunciMemo = "raterRules:{$participantId}";

        if (array_key_exists($kunciMemo, $this->memo)) {
            return $this->memo[$kunciMemo];
        }

        $peserta = $this->participant($participantId);

        $hasil = [];

        foreach ($this->raterSnapshotRows($peserta->PA_Cycle_Rater_Policy_Snapshot_Id) as $baris) {
            $hasil[$baris->Kode_Kategori_Salinan] = [
                'kategori_id' => (int) $baris->PA_Rater_Category_Id,
                'kode_kategori' => $baris->Kode_Kategori_Salinan,
                'nama_kategori' => $baris->Nama_Kategori_Salinan,
                'bobot_persen' => (float) $baris->Bobot_Persen,
                'min_rater' => (int) $baris->Min_Rater,
                'maks_rater' => $baris->Maks_Rater === null ? null : (int) $baris->Maks_Rater,
                'min_rater_selesai' => $baris->Min_Rater_Selesai === null ? null : (int) $baris->Min_Rater_Selesai,
                'urutan_tampil' => $baris->Urutan_Tampil === null ? null : (int) $baris->Urutan_Tampil,
                'relationship_type_id' => $baris->Organization_Relationship_Type_Id === null
                    ? null
                    : (int) $baris->Organization_Relationship_Type_Id,
                'kode_relasi' => $baris->Kode_Relasi_Salinan,
                'flag_izinkan_manual' => $baris->Flag_Izinkan_Manual,
                'sumber' => self::SUMBER_SALINAN,
                'alasan_perubahan' => null,
            ];
        }

        foreach ($this->raterOverrideRows($participantId) as $ovr) {
            $kode = $ovr->Kode_Kategori;

            // Dicabut untuk peserta ini. Kategori yang tidak ada di salinan pun
            // boleh dicabut tanpa efek — itu tidak salah, hanya tidak berguna.
            if ($ovr->Flag_Dipakai === 'T') {
                unset($hasil[$kode]);

                continue;
            }

            $asal = $hasil[$kode] ?? null;

            $hasil[$kode] = [
                'kategori_id' => (int) $ovr->PA_Rater_Category_Id,
                // Nama beku dari salinan menang bila kategorinya memang ada di
                // sana; nama master hidup hanya dipakai untuk kategori tambahan.
                'kode_kategori' => $kode,
                'nama_kategori' => $asal['nama_kategori'] ?? $ovr->Nama_Kategori,
                'bobot_persen' => (float) $ovr->Bobot_Persen,
                'min_rater' => (int) $ovr->Min_Rater,
                'maks_rater' => $ovr->Maks_Rater === null ? null : (int) $ovr->Maks_Rater,
                'min_rater_selesai' => $ovr->Min_Rater_Selesai === null ? null : (int) $ovr->Min_Rater_Selesai,
                // Ketiga hal berikut memang tidak pernah menyimpang per orang,
                // jadi nilainya tetap milik salinan (K32).
                'urutan_tampil' => $asal['urutan_tampil'] ?? null,
                'relationship_type_id' => $asal['relationship_type_id'] ?? null,
                'kode_relasi' => $asal['kode_relasi'] ?? null,
                'flag_izinkan_manual' => $asal['flag_izinkan_manual'] ?? null,
                'sumber' => $asal === null ? self::SUMBER_TAMBAHAN : self::SUMBER_OVERRIDE,
                'alasan_perubahan' => $ovr->Alasan_Perubahan,
            ];
        }

        uasort($hasil, static function (array $a, array $b): int {
            // Yang tanpa urutan (kategori tambahan) jatuh ke belakang, lalu
            // diurutkan kodenya supaya susunannya tetap sama tiap kali dibaca.
            $ua = $a['urutan_tampil'] ?? PHP_INT_MAX;
            $ub = $b['urutan_tampil'] ?? PHP_INT_MAX;

            return $ua === $ub
                ? strcmp($a['kode_kategori'], $b['kode_kategori'])
                : $ua <=> $ub;
        });

        return $this->memo[$kunciMemo] = $hasil;
    }

    /**
     * Pembagian bobot pilar yang berlaku bagi satu peserta.
     *
     * @return array{
     *     bobot_kpi:float, bobot_kompetensi:float,
     *     sumber:string, alasan_perubahan:string|null
     * }
     */
    public function pillarWeight(int $participantId): array
    {
        $peserta = $this->participant($participantId);

        $ovr = $this->pastiTanpaOverride('pilar', $participantId, (int) $peserta->PA_Cycle_Id)
            ? null
            : DB::table('N_PA_Participant_Pillar_Override')
                ->where('PA_Participant_Id', $participantId)
                ->where('Flag_Aktif', 'Y')
                ->first();

        if ($ovr !== null) {
            return [
                'bobot_kpi' => (float) $ovr->Bobot_KPI,
                'bobot_kompetensi' => (float) $ovr->Bobot_Kompetensi,
                'sumber' => self::SUMBER_OVERRIDE,
                'alasan_perubahan' => $ovr->Alasan_Perubahan,
            ];
        }

        $salinan = $this->pillarSnapshotRow(
            (int) $peserta->PA_Cycle_Id,
            (int) $peserta->ID_Level_Saat_Cycle
        );

        if ($salinan === null) {
            throw new RuntimeException(
                "Bobot pilar peserta {$participantId} tidak dapat diresolusi: "
                .'salinan siklus untuk levelnya tidak ada.'
            );
        }

        return [
            'bobot_kpi' => (float) $salinan->Bobot_KPI,
            'bobot_kompetensi' => (float) $salinan->Bobot_Kompetensi,
            'sumber' => self::SUMBER_SALINAN,
            'alasan_perubahan' => null,
        ];
    }

    /**
     * Jenis kompetensi yang berlaku bagi satu peserta.
     *
     * @return array<string, array{
     *     type_id:int, kode_type:string, nama_type:string,
     *     flag_wajib:string, sumber:string, alasan_perubahan:string|null
     * }> dikunci kode jenis kompetensi
     */
    public function competencyRequirements(int $participantId): array
    {
        $peserta = $this->participant($participantId);

        $hasil = [];

        $rows = $this->requirementSnapshotRows(
            (int) $peserta->PA_Cycle_Id,
            (int) $peserta->ID_Level_Saat_Cycle
        );

        foreach ($rows as $baris) {
            $hasil[$baris->Kode_Type_Salinan] = [
                'type_id' => (int) $baris->PA_Competency_Type_Id,
                'kode_type' => $baris->Kode_Type_Salinan,
                'nama_type' => $baris->Nama_Type_Salinan,
                'flag_wajib' => $baris->Flag_Wajib,
                'sumber' => self::SUMBER_SALINAN,
                'alasan_perubahan' => null,
            ];
        }

        foreach ($this->requirementOverrideRows($participantId) as $ovr) {
            $kode = $ovr->Kode_Type;

            if ($ovr->Flag_Dipakai === 'T') {
                unset($hasil[$kode]);

                continue;
            }

            $asal = $hasil[$kode] ?? null;

            $hasil[$kode] = [
                'type_id' => (int) $ovr->PA_Competency_Type_Id,
                'kode_type' => $kode,
                'nama_type' => $asal['nama_type'] ?? $ovr->Nama_Type,
                'flag_wajib' => $ovr->Flag_Wajib,
                'sumber' => $asal === null ? self::SUMBER_TAMBAHAN : self::SUMBER_OVERRIDE,
                'alasan_perubahan' => $ovr->Alasan_Perubahan,
            ];
        }

        ksort($hasil);

        return $hasil;
    }

    /**
     * Ringkasan penyimpangan satu peserta, untuk layar yang perlu menandai siapa
     * yang tidak mengikuti standar levelnya.
     *
     * Menghitung baris override, BUKAN membandingkan hasil resolusi dengan
     * salinan — keberadaan barisnya sendiri sudah berarti menyimpang, itulah
     * sebabnya tidak ada kolom penanda di tabelnya.
     *
     * @return array{rater:int, pilar:int, kompetensi:int, total:int}
     */
    public function penyimpangan(int $participantId): array
    {
        $rater = DB::table('N_PA_Participant_Rater_Override')
            ->where('PA_Participant_Id', $participantId)->where('Flag_Aktif', 'Y')->count();

        $pilar = DB::table('N_PA_Participant_Pillar_Override')
            ->where('PA_Participant_Id', $participantId)->where('Flag_Aktif', 'Y')->count();

        $kompetensi = DB::table('N_PA_Participant_Requirement_Override')
            ->where('PA_Participant_Id', $participantId)->where('Flag_Aktif', 'Y')->count();

        return [
            'rater' => $rater,
            'pilar' => $pilar,
            'kompetensi' => $kompetensi,
            'total' => $rater + $pilar + $kompetensi,
        ];
    }

    /**
     * Membuang memo. Dipanggil setelah salinan siklus diubah di dalam satu proses
     * yang sama — misalnya perintah artisan yang menyunting lalu membaca ulang.
     */
    public function lupakanMemo(): void
    {
        $this->memo = [];
        $this->overrideTermuat = [];
    }

    /**
     * Memuat SELURUH baris override satu siklus sekaligus.
     *
     * Override bersifat sparse (K32) — pada siklus normal tabelnya kosong sama sekali.
     * Menanyakannya per peserta berarti ratusan kueri yang seluruhnya menjawab "tidak
     * ada". Terukur: dua dari enam belas kueri per peserta hanyalah dua tabel override
     * yang kosong.
     *
     * Ini semata pramuat. Semantiknya tidak berubah sedikit pun: pembaca per peserta
     * tetap satu-satunya jalan, dan hasilnya identik dengan atau tanpa pramuat.
     */
    /**
     * Memuat baris peserta sekaligus ke memo — satu kueri untuk ratusan orang.
     *
     * Semata pramuat: `participant()` tetap satu-satunya pembaca, dan hasilnya identik dengan
     * atau tanpa pramuat. Tanpa ini pembentukan massal membaca satu peserta per kueri, dan pada
     * DB yang setiap perjalanannya puluhan hingga ratusan milidetik itu ratusan perjalanan untuk
     * data yang bisa diambil sekali.
     *
     * @param  list<int>  $ids
     */
    public function muatPesertaBanyak(array $ids): void
    {
        $belum = array_values(array_filter(
            $ids,
            fn ($id) => ! array_key_exists("peserta:{$id}", $this->memo),
        ));

        if ($belum === []) {
            return;
        }

        foreach (array_chunk($belum, 500) as $potongan) {
            foreach (DB::table('N_PA_Participant')
                ->select([
                    'Id_PA_Participant',
                    'PA_Cycle_Id',
                    'ID_Level_Saat_Cycle',
                    'PA_Cycle_Rater_Policy_Snapshot_Id',
                    'Status_Peserta',
                    'Flag_Aktif',
                ])
                ->whereIn('Id_PA_Participant', $potongan)
                ->get() as $r) {
                $this->memo['peserta:'.((int) $r->Id_PA_Participant)] = $r;
            }
        }
    }

    public function muatOverrideSiklus(int $cycleId): void
    {
        if (isset($this->overrideTermuat[$cycleId])) {
            return;
        }

        foreach ([
            'rater' => ['N_PA_Participant_Rater_Override', 'raterOverrideRows'],
            'pilar' => ['N_PA_Participant_Pillar_Override', 'pillarOverrideRow'],
            'syarat' => ['N_PA_Participant_Requirement_Override', 'requirementOverrideRows'],
        ] as $jenis => [$tabel, $metode]) {
            $baris = DB::table($tabel.' as o')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'o.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $cycleId)
                ->where('o.Flag_Aktif', 'Y')
                ->pluck('o.PA_Participant_Id')
                ->unique();

            // Yang disimpan hanya DAFTAR peserta yang punya override. Peserta di luar
            // daftar dijawab dari memo tanpa menyentuh database; yang ada di dalamnya
            // tetap dibaca lewat jalur biasa, supaya bentuk barisnya persis sama.
            $this->memo["punya:{$jenis}:{$cycleId}"] = $baris->flip()->all();
        }

        $this->overrideTermuat[$cycleId] = true;
    }

    /**
     * Apakah peserta ini bisa dipastikan TIDAK punya override, tanpa bertanya lagi.
     */
    private function pastiTanpaOverride(string $jenis, int $participantId, int $cycleId): bool
    {
        if (! isset($this->overrideTermuat[$cycleId])) {
            return false;
        }

        return ! isset($this->memo["punya:{$jenis}:{$cycleId}"][$participantId]);
    }

    private function participant(int $participantId): object
    {
        $kunci = "peserta:{$participantId}";

        if (! array_key_exists($kunci, $this->memo)) {
            $baris = DB::table('N_PA_Participant')
                ->select([
                    'Id_PA_Participant',
                    'PA_Cycle_Id',
                    'ID_Level_Saat_Cycle',
                    'PA_Cycle_Rater_Policy_Snapshot_Id',
                    'Status_Peserta',
                    'Flag_Aktif',
                ])
                ->where('Id_PA_Participant', $participantId)
                ->first();

            if ($baris === null) {
                throw new RuntimeException("Peserta PA {$participantId} tidak ditemukan.");
            }

            $this->memo[$kunci] = $baris;
        }

        return $this->memo[$kunci];
    }

    /**
     * Nama kategori diambil dari kolom `_Salinan` yang beku, bukan dari master
     * hidup. Kalau kategorinya kelak berganti nama, laporan siklus lama harus
     * tetap terbaca dengan nama yang berlaku saat itu — itulah gunanya kolom
     * salinan disediakan, dan menjoin ke master justru membatalkannya.
     *
     * @return array<int, object>
     */
    private function raterSnapshotRows(?int $snapshotId): array
    {
        // Peserta TERTUNDA atau DIKECUALIKAN boleh belum menunjuk salinan aturan.
        // Itu keadaan sah, bukan kegagalan — hasilnya himpunan kosong.
        if ($snapshotId === null) {
            return [];
        }

        $kunci = "rater:{$snapshotId}";

        if (! array_key_exists($kunci, $this->memo)) {
            $this->memo[$kunci] = DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
                ->where('PA_Cycle_Rater_Policy_Snapshot_Id', $snapshotId)
                ->where('Flag_Aktif', 'Y')
                ->select([
                    'Id_PA_Cycle_Rater_Policy_Detail_Snapshot',
                    'PA_Rater_Category_Id',
                    'Kode_Kategori_Salinan',
                    'Nama_Kategori_Salinan',
                    'Organization_Relationship_Type_Id',
                    'Kode_Relasi_Salinan',
                    'Flag_Izinkan_Manual',
                    'Bobot_Persen',
                    'Min_Rater',
                    'Maks_Rater',
                    'Min_Rater_Selesai',
                    'Urutan_Tampil',
                ])
                ->get()
                ->all();
        }

        return $this->memo[$kunci];
    }

    /**
     * Override tidak punya kolom salinan nama, jadi di sinilah master hidup
     * memang satu-satunya sumber — dan hanya terpakai untuk kategori tambahan.
     *
     * @return array<int, object>
     */
    private function raterOverrideRows(int $participantId): array
    {
        if ($this->pastiTanpaOverride('rater', $participantId, (int) $this->participant($participantId)->PA_Cycle_Id)) {
            return [];
        }

        return DB::table('N_PA_Participant_Rater_Override as o')
            ->join('N_PA_Rater_Category as k', 'k.Id_PA_Rater_Category', '=', 'o.PA_Rater_Category_Id')
            ->where('o.PA_Participant_Id', $participantId)
            ->where('o.Flag_Aktif', 'Y')
            ->select([
                'o.PA_Rater_Category_Id',
                'o.Flag_Dipakai',
                'o.Bobot_Persen',
                'o.Min_Rater',
                'o.Maks_Rater',
                'o.Min_Rater_Selesai',
                'o.Alasan_Perubahan',
                'k.Kode_Kategori',
                'k.Nama_Kategori',
            ])
            ->get()
            ->all();
    }

    /**
     * Tabel ini sengaja tidak punya `Flag_Aktif` — bobot pilar boleh diubah
     * bahkan setelah pengisian selesai, jadi barisnya disunting di tempat dan
     * tidak pernah dipensiunkan.
     */
    private function pillarSnapshotRow(int $cycleId, int $levelId): ?object
    {
        $kunci = "pilar:{$cycleId}:{$levelId}";

        if (! array_key_exists($kunci, $this->memo)) {
            $this->memo[$kunci] = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('ID_Level', $levelId)
                ->select(['Bobot_KPI', 'Bobot_Kompetensi'])
                ->first();
        }

        return $this->memo[$kunci];
    }

    /**
     * @return array<int, object>
     */
    private function requirementSnapshotRows(int $cycleId, int $levelId): array
    {
        $kunci = "syarat:{$cycleId}:{$levelId}";

        if (! array_key_exists($kunci, $this->memo)) {
            $this->memo[$kunci] = DB::table('N_PA_Cycle_Competency_Requirement_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('ID_Level', $levelId)
                ->where('Flag_Aktif', 'Y')
                ->select([
                    'PA_Competency_Type_Id',
                    'Kode_Type_Salinan',
                    'Nama_Type_Salinan',
                    'Flag_Wajib',
                ])
                ->get()
                ->all();
        }

        return $this->memo[$kunci];
    }

    /**
     * @return array<int, object>
     */
    private function requirementOverrideRows(int $participantId): array
    {
        if ($this->pastiTanpaOverride('syarat', $participantId, (int) $this->participant($participantId)->PA_Cycle_Id)) {
            return [];
        }

        return DB::table('N_PA_Participant_Requirement_Override as o')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'o.PA_Competency_Type_Id')
            ->where('o.PA_Participant_Id', $participantId)
            ->where('o.Flag_Aktif', 'Y')
            ->select([
                'o.PA_Competency_Type_Id',
                'o.Flag_Dipakai',
                'o.Flag_Wajib',
                'o.Alasan_Perubahan',
                't.Kode_Type',
                't.Nama_Type',
            ])
            ->get()
            ->all();
    }
}
