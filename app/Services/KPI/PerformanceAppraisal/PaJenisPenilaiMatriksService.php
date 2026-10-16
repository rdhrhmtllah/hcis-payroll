<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Matriks LEVEL JABATAN × JENIS PENILAI — siapa menilai siapa, dan siapa yang tidak dinilai.
 *
 * ── Kenapa halamannya berbentuk matriks, bukan daftar ─────────────────────────
 *
 * Sampai 9 September 2026 halaman "Jenis Penilai" hanyalah daftar empat baris: Atasan,
 * Bawahan, Rekan Sejawat, Diri Sendiri. Daftar itu benar tetapi tidak menjawab satu pun
 * pertanyaan yang sebenarnya ditanyakan orang tentangnya — keempatnya berlaku di level mana,
 * dengan bobot berapa, dan level mana yang justru tidak dinilai sama sekali. Jawaban itu
 * tersebar di tiga tabel dan hanya bisa dilihat utuh sebagai matriks.
 *
 * ── Yang DIBACA di sini dan yang DISUNTING di tempat lain ─────────────────────
 *
 * Bobot dan kuota penilai adalah milik `N_PA_Rater_Policy` beserta detailnya, dan yang
 * menyuntingnya tetap layar "Komposisi Penilai" — di sanalah versi, masa berlaku, dan
 * status kebijakannya hidup. Matriks ini MEMBACANYA supaya keduanya tidak pernah bisa
 * berselisih: satu penulis, banyak pembaca.
 *
 * Yang BISA diubah dari sini hanya satu hal, dan memang tidak punya rumah lain:
 * pengecualian level dari Performance Appraisal.
 *
 * ── Kenapa pengecualiannya tinggal di sini ────────────────────────────────────
 *
 * "Level ini tidak dinilai" adalah pernyataan tentang BARIS matriks ini — ia mengosongkan
 * seluruh selnya sekaligus, bukan satu kategori penilai saja. Menaruhnya di layar lain
 * berarti admin menutup satu baris dari tempat yang tidak memperlihatkan baris itu.
 */
class PaJenisPenilaiMatriksService
{
    /**
     * Matriks lengkap: seluruh level × seluruh kategori penilai.
     *
     * Dikirim SEKALIGUS, bukan per level. Yang ditanyakan orang tentang tabel ini selalu
     * berbentuk perbandingan antar level — "kenapa Manager punya Bawahan tetapi Staff
     * tidak" — dan memuatnya sepotong-sepotong membuat perbandingan itu mustahil.
     *
     * @return array<string, mixed>
     */
    public function matriks(): array
    {
        $kategori = $this->kategoriAktif();

        /* Cacah karyawan per level, SATU kueri. Menanyakannya per baris berarti belasan
           perjalanan ke database untuk halaman yang dibuka sekali. */
        // Level PA (DDL-2026-177): koreksi per karyawan ikut dihitung.
        $lvPa = PaLevelPaResolver::sqlLevel();
        $karyawan = PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'))
            ->where('k.Aktif', 'Y')
            ->groupBy(DB::raw($lvPa))
            ->get([DB::raw("{$lvPa} as ID_Level"), DB::raw('COUNT(DISTINCT k.Kode_Karyawan) as jumlah')])
            ->pluck('jumlah', 'ID_Level')
            ->all();

        /* Kebijakan AKTIF per level. Satu level bisa punya beberapa versi; yang berlaku
           hanya yang aktif, dan menampilkan yang lain di matriks membuat angkanya tampak
           berlipat tanpa sebab. */
        $policy = DB::table('N_PA_Rater_Policy')
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Rater_Policy as id', 'ID_Level as id_level', 'Kode_Policy as kode',
                'Nama_Policy as nama', 'Versi as versi', 'Status_Policy as status'])
            ->keyBy('id_level');

        $detail = DB::table('N_PA_Rater_Policy_Detail as d')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->whereIn('d.PA_Rater_Policy_Id', $policy->pluck('id')->all() ?: [0])
            ->where('d.Flag_Aktif', 'Y')
            ->get(['d.PA_Rater_Policy_Id as policy_id', 'c.Kode_Kategori as kode',
                'd.Bobot_Persen as bobot', 'd.Min_Rater as min', 'd.Maks_Rater as maks',
                'd.Min_Rater_Selesai as min_selesai', 'd.Flag_Izinkan_Manual as manual'])
            ->groupBy('policy_id');

        PaLevelMasterService::sinkronHris();
        $level = DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->orderBy('ID_Level')
            ->get(['ID_Level as id_level', 'Nama_Level as nama_level'])
            ->map(function ($l) use ($kategori, $karyawan, $policy, $detail) {
                $idLevel = (int) $l->id_level;
                $p = $policy[$idLevel] ?? null;
                $baris = $p === null ? collect() : ($detail[$p->id] ?? collect())->keyBy('kode');

                /* TIGA keadaan, bukan dua.

                   `null`            -> kategori ini TIDAK menilai level tersebut.
                   berlaku, !berbobot -> sudah ditandai di matriks, bobotnya menunggu
                                        diisi di Komposisi Penilai.
                   berlaku, berbobot  -> lengkap.

                   Keadaan kedua lahir sejak DDL-2026-092. Menyamakannya dengan bobot 0
                   membuat sel yang sedang menunggu terbaca sebagai kategori yang memang
                   tidak menyumbang apa-apa — dan totalnya tetap terlihat "kurang" tanpa
                   ada yang bisa menunjuk sel mana yang salah. */
                $sel = [];
                foreach ($kategori as $k) {
                    $d = $baris[$k['kode']] ?? null;

                    if ($d === null) {
                        $sel[$k['kode']] = null;

                        continue;
                    }

                    $berbobot = $d->bobot !== null;

                    $sel[$k['kode']] = [
                        'berlaku' => true,
                        'berbobot' => $berbobot,
                        'bobot' => $berbobot ? (float) $d->bobot : null,
                        'min' => $d->min === null ? null : (int) $d->min,
                        'maks' => $d->maks === null ? null : (int) $d->maks,
                        'min_selesai' => $d->min_selesai === null ? null : (int) $d->min_selesai,
                        'izinkan_manual' => $d->manual === 'Y',
                    ];
                }

                $terisi = array_filter($sel, fn ($s) => $s !== null);
                $berbobot = array_filter($terisi, fn ($s) => $s['berbobot']);

                return [
                    'id_level' => $idLevel,
                    'nama_level' => $l->nama_level,
                    'jumlah_karyawan' => (int) ($karyawan[$idLevel] ?? 0),
                    'dikecualikan_pa' => PaLevelDikecualikan::apakah($idLevel),
                    'alasan_dikecualikan' => PaLevelDikecualikan::alasan($idLevel),
                    'policy' => $p === null ? null : [
                        'id' => (int) $p->id,
                        'kode' => $p->kode,
                        'nama' => $p->nama,
                        'versi' => (int) $p->versi,
                        'status' => $p->status,
                    ],
                    'sel' => $sel,
                    'jumlah_kategori' => count($terisi),
                    'jumlah_belum_berbobot' => count($terisi) - count($berbobot),

                    /* Bobot yang tidak genap 100 bukan sekadar tidak rapi: skornya akan
                       dihitung atas pembagi yang bukan satu, dan hasil antar level berhenti
                       bisa dibandingkan. Dihitung di sini supaya layar tidak menjumlahkan
                       sendiri lalu berselisih dengan server. */
                    'total_bobot' => round(array_sum(array_map(fn ($s) => $s['bobot'], $berbobot)), 2),
                ];
            })
            ->values()
            ->all();

        return [
            'kategori' => $kategori,
            'level' => $level,

            /* Yang perlu ditonjolkan, dihitung server supaya definisinya satu.

               `perlu_perhatian` SENGAJA tidak menghitung level yang dikecualikan: nol
               kategori padanya bukan kekeliruan, dan penjaga yang berteriak atas keadaan
               yang memang disengaja berhenti dibaca. */
            'perlu_perhatian' => array_values(array_filter(
                $level,
                fn ($l) => ! $l['dikecualikan_pa']
                    && $l['jumlah_karyawan'] > 0
                    && (
                        $l['jumlah_kategori'] === 0
                        || $l['jumlah_belum_berbobot'] > 0
                        || abs($l['total_bobot'] - 100) > 0.01
                    ),
            )),
            'total_dikecualikan' => count(array_filter($level, fn ($l) => $l['dikecualikan_pa'])),
        ];
    }

    /**
     * Kategori penilai aktif dalam urutan pengisian wizard.
     *
     * @return list<array<string, mixed>>
     */
    private function kategoriAktif(): array
    {
        return DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            // Kategori tanpa urutan jatuh ke belakang, lalu menurut id — sama dengan wizard.
            ->orderByRaw('CASE WHEN Urutan_Pengisian IS NULL THEN 1 ELSE 0 END')
            ->orderBy('Urutan_Pengisian')
            ->orderBy('Id_PA_Rater_Category')
            ->get(['Id_PA_Rater_Category as id', 'Kode_Kategori as kode', 'Nama_Kategori as nama',
                'Deskripsi as deskripsi', 'Urutan_Pengisian as urutan', 'Flag_Boleh_Kalibrasi as boleh'])
            ->map(fn ($k) => [
                'id' => (int) $k->id,
                'kode' => $k->kode,
                'nama' => $k->nama,
                'deskripsi' => $k->deskripsi,
                'urutan_pengisian' => $k->urutan === null ? null : (int) $k->urutan,
                'boleh_kalibrasi' => $k->boleh === 'Y',
            ])
            ->values()
            ->all();
    }

    /**
     * Menata urutan pengisian SELURUH kategori aktif sekaligus.
     *
     * Satu pernyataan UPDATE ... CASE, bukan satu UPDATE per kategori: indeks unik
     * UX_PA_Rater_Category_Urutan diperiksa per pernyataan, sehingga menukar dua posisi satu
     * per satu akan menabraknya di tengah jalan. Daftarnya wajib memuat setiap kategori aktif
     * tepat sekali — urutan sebagian akan meninggalkan dua kategori berebut satu langkah.
     *
     * Berlaku SEKETIKA, termasuk pada siklus yang sedang berjalan (spec W5).
     *
     * @param  list<string>  $kodeBerurut
     * @return list<array<string, mixed>>
     */
    public function tataUrutanKategori(array $kodeBerurut, ?int $olehUserId = null): array
    {
        $aktif = DB::table('N_PA_Rater_Category')->where('Flag_Aktif', 'Y')->pluck('Kode_Kategori')
            ->map(fn ($k) => strtoupper(trim((string) $k)))->all();
        $diminta = array_values(array_map(fn ($k) => strtoupper(trim((string) $k)), $kodeBerurut));

        $kurang = array_values(array_diff($aktif, $diminta));
        $lebih = array_values(array_diff($diminta, $aktif));

        if ($kurang !== [] || $lebih !== [] || count($diminta) !== count(array_unique($diminta))) {
            throw new RuntimeException(
                'Urutan harus memuat setiap kategori penilai aktif tepat sekali.'
                .($kurang === [] ? '' : ' Belum ada: '.implode(', ', $kurang).'.')
                .($lebih === [] ? '' : ' Tidak dikenal: '.implode(', ', $lebih).'.')
            );
        }

        $kasus = implode(' ', array_fill(0, count($diminta), 'WHEN ? THEN ?'));
        $binding = [];

        foreach ($diminta as $i => $kode) {
            $binding[] = $kode;
            $binding[] = $i + 1;
        }

        DB::update(
            "UPDATE N_PA_Rater_Category
             SET Urutan_Pengisian = CASE UPPER(LTRIM(RTRIM(Kode_Kategori))) {$kasus} END,
                 Updated_At = sysdatetime(),
                 Updated_By = ?
             WHERE Flag_Aktif = 'Y'",
            [...$binding, $olehUserId],
        );

        return array_values(array_map(
            fn ($k) => array_intersect_key($k, array_flip(['id', 'kode', 'nama', 'urutan_pengisian', 'boleh_kalibrasi'])),
            $this->kategoriAktif(),
        ));
    }

    /**
     * Menyalakan atau mematikan Kalibrasi Penilai untuk satu kategori.
     *
     * Berlaku SEKETIKA, termasuk pada siklus yang sedang berjalan: penanda ini kamus hidup
     * atas keputusan pemilik proyek 18 September 2026 (spec W5), menyimpang dari K26.
     * Kalibrasi yang sudah tersimpan tidak disentuh saat penandanya dimatikan.
     *
     * @return array{id:int, kode:string, boleh_kalibrasi:bool}
     */
    public function setelBolehKalibrasi(int $kategoriId, bool $boleh, ?int $olehUserId = null): array
    {
        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', $kategoriId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Rater_Category', 'Kode_Kategori']);

        if ($kategori === null) {
            throw new RuntimeException("Kategori penilai {$kategoriId} tidak ada atau sudah nonaktif.");
        }

        DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $kategoriId)->update([
            'Flag_Boleh_Kalibrasi' => $boleh ? 'Y' : 'T',
            'Updated_At' => now(),
            'Updated_By' => $olehUserId,
        ]);

        return ['id' => $kategoriId, 'kode' => (string) $kategori->Kode_Kategori, 'boleh_kalibrasi' => $boleh];
    }

    /**
     * Menandai atau mencabut satu sel matriks: kategori ini menilai level itu, atau tidak.
     *
     * INI LANGKAH PERTAMA, dan sengaja TIDAK meminta bobot. Bobot serta kuotanya ditetapkan
     * menyusul di Komposisi Penilai — urutan yang selama ini tidak bisa diucapkan model,
     * karena satu baris detail dulu menyatakan keberlakuan dan bobot sekaligus.
     *
     * Baris yang lahir dari sini ber-`Bobot_Persen` dan `Min_Rater` NULL: BERLAKU, BELUM
     * BERBOBOT. Bukan nol — nol berarti "menilai tetapi tidak menyumbang skor", pernyataan
     * berbeda yang akan ikut tersalin ke siklus.
     *
     * @return array<string, mixed>
     */
    public function setelSel(int $levelId, int $kategoriId, bool $berlaku, ?int $olehUserId = null): array
    {
        $level = DB::table('N_PA_Level')->where('ID_Level', $levelId)->first(['ID_Level', 'Nama_Level as Keterangan']);

        if ($level === null) {
            throw new RuntimeException("Level {$levelId} tidak ada.");
        }

        if (PaLevelDikecualikan::apakah($levelId)) {
            throw new RuntimeException(
                "Level {$level->Keterangan} dikecualikan dari Performance Appraisal; "
                .'cabut pengecualiannya lebih dulu sebelum mengatur penilainya.'
            );
        }

        $kategori = DB::table('N_PA_Rater_Category')
            ->where('Id_PA_Rater_Category', $kategoriId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Rater_Category', 'Nama_Kategori', 'Kode_Kategori']);

        if ($kategori === null) {
            throw new RuntimeException("Kategori penilai {$kategoriId} tidak ada atau tidak aktif.");
        }

        return DB::transaction(function () use ($levelId, $level, $kategoriId, $kategori, $berlaku, $olehUserId) {
            $policyId = $this->policyAktif($levelId, $level->Keterangan, $olehUserId);

            $ada = DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policyId)
                ->where('PA_Rater_Category_Id', $kategoriId)
                ->where('Flag_Aktif', 'Y')
                ->first(['Id_PA_Rater_Policy_Detail', 'Bobot_Persen']);

            if (! $berlaku) {
                if ($ada !== null) {
                    /* DINONAKTIFKAN, bukan dihapus — sama seperti seluruh modul ini. Baris
                       yang hilang membuat "kenapa dulu Bawahan menilai level ini" mustahil
                       dijawab. */
                    DB::table('N_PA_Rater_Policy_Detail')
                        ->where('Id_PA_Rater_Policy_Detail', $ada->Id_PA_Rater_Policy_Detail)
                        ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $olehUserId]);
                }

                return ['id_level' => $levelId, 'pa_rater_category_id' => $kategoriId, 'berlaku' => false];
            }

            if ($ada !== null) {
                return [
                    'id_level' => $levelId,
                    'pa_rater_category_id' => $kategoriId,
                    'berlaku' => true,
                    'berbobot' => $ada->Bobot_Persen !== null,
                ];
            }

            $urutan = (int) DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policyId)
                ->where('Flag_Aktif', 'Y')
                ->max('Urutan_Tampil');

            DB::table('N_PA_Rater_Policy_Detail')->insert([
                'PA_Rater_Policy_Id' => $policyId,
                'PA_Rater_Category_Id' => $kategoriId,
                /* Jenis relasi diisi otomatis dari kode kategorinya (ATASAN → relasi ATASAN,
                   PEER → PEER, BAWAHAN → BAWAHAN). Dulu kosong, dan kategori tanpa jenis relasi
                   tidak punya calon penilai sama sekali: penugasan massal melewatkannya sampai
                   seseorang mengisinya di Komposisi Penilai — halaman yang kini milik System
                   Admin PA. SELF tidak punya relasi padanannya, jadi tetap kosong. */
                'Organization_Relationship_Type_Id' => $this->jenisRelasiBawaan((string) $kategori->Kode_Kategori),
                'Bobot_Persen' => null,
                'Min_Rater' => null,
                'Urutan_Tampil' => $urutan + 1,
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
            ]);

            return [
                'id_level' => $levelId,
                'pa_rater_category_id' => $kategoriId,
                'berlaku' => true,
                'berbobot' => false,
            ];
        });
    }

    /**
     * Menyimpan komposisi penilai (bobot & kuota) untuk satu level jabatan.
     *
     * Total bobot seluruh kategori penilai aktif wajib tepat 100%.
     *
     * @param  array<int, array<string, mixed>>  $rincian
     * @return array<string, mixed>
     */
    public function simpanKomposisi(int $levelId, array $rincian, ?int $olehUserId = null): array
    {
        $level = DB::table('N_PA_Level')->where('ID_Level', $levelId)->first(['ID_Level', 'Nama_Level as Keterangan']);

        if ($level === null) {
            throw new RuntimeException("Level {$levelId} tidak ada.");
        }

        if (PaLevelDikecualikan::apakah($levelId)) {
            throw new RuntimeException(
                "Level {$level->Keterangan} dikecualikan dari Performance Appraisal; "
                .'cabut pengecualiannya lebih dulu sebelum mengatur komposisi penilainya.'
            );
        }

        if ($rincian === []) {
            throw new RuntimeException('Komposisi penilai tidak boleh kosong.');
        }

        return DB::transaction(function () use ($levelId, $level, $rincian, $olehUserId) {
            $policyId = $this->policyAktif($levelId, $level->Keterangan, $olehUserId);

            /** @var PaMasterWriteService $writeService */
            $writeService = app(PaMasterWriteService::class);

            return $writeService->simpanRincianAturan($policyId, $rincian, $olehUserId);
        });
    }

    /**
     * Id kebijakan penilai yang aktif untuk satu level; dibuatkan bila belum ada.
     *
     * Dibuatkan DI SINI, bukan menyuruh admin ke Komposisi Penilai lebih dulu — karena itu
     * akan mengembalikan urutan yang justru sedang diperbaiki. Kebijakan yang lahir dari
     * matriks kosong dari rincian dan itu memang keadaan yang benar: ia baru berisi sesudah
     * selnya ditandai.
     */
    /** Jenis relasi aktif yang kodenya sama dengan kode kategori; null bila tidak ada (mis. SELF). */
    private function jenisRelasiBawaan(string $kodeKategori): ?int
    {
        $id = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', strtoupper(trim($kodeKategori)))
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Organization_Relationship_Type');

        return $id === null ? null : (int) $id;
    }

    private function policyAktif(int $levelId, ?string $namaLevel, ?int $olehUserId): int
    {
        $ada = DB::table('N_PA_Rater_Policy')
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Versi')
            ->value('Id_PA_Rater_Policy');

        if ($ada !== null) {
            return (int) $ada;
        }

        /* Kodenya diturunkan dari id level, bukan dari namanya: nama level bisa berubah, dan
           kode yang ikut berubah akan memutus (Kode_Policy, Versi) yang dijaga unik. */
        $kode = 'PA-RTR-L'.$levelId;

        DB::table('N_PA_Rater_Policy')->insert([
            'Kode_Policy' => $kode,
            'Nama_Policy' => 'Aturan Penilai '.($namaLevel ?? "Level {$levelId}"),
            'Deskripsi' => 'Dibuat otomatis dari Matriks Jenis Penilai; bobot dan kuotanya ditetapkan di Komposisi Penilai.',
            'ID_Level' => $levelId,
            'Versi' => 1,
            'Status_Policy' => 'ACTIVE',
            'Berlaku_Dari' => now()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_By' => $olehUserId,
        ]);

        return (int) DB::table('N_PA_Rater_Policy')
            ->where('Kode_Policy', $kode)
            ->where('Versi', 1)
            ->value('Id_PA_Rater_Policy');
    }

    /**
     * Mengecualikan satu level dari Performance Appraisal.
     *
     * Alasannya WAJIB. Pengecualian tanpa sebab menghasilkan level yang hilang dari
     * penilaian tanpa satu orang pun bisa menjawab kenapa — dan setahun kemudian tidak ada
     * yang berani mencabutnya karena tidak tahu apa yang dilindunginya.
     *
     * @return array<string, mixed>
     */
    public function kecualikan(int $levelId, string $alasan, ?int $olehUserId = null): array
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new RuntimeException('Pengecualian level wajib menyebut alasannya.');
        }

        $level = DB::table('N_PA_Level')->where('ID_Level', $levelId)->first(['ID_Level', 'Nama_Level as Keterangan']);

        if ($level === null) {
            throw new RuntimeException("Level {$levelId} tidak ada.");
        }

        if (PaLevelDikecualikan::apakah($levelId)) {
            throw new RuntimeException("Level {$level->Keterangan} sudah dikecualikan.");
        }

        /* SIKLUS BERJALAN TIDAK IKUT BERUBAH, dan itu perlu dikatakan di sini karena
           mudah disangka sebaliknya. Peserta yang sudah terbentuk membawa levelnya sebagai
           SALINAN (`ID_Level_Saat_Cycle`), jadi pengecualian ini berlaku pada penyiapan
           siklus BERIKUTNYA — bukan mencabut orang dari siklus yang sedang diisi. Mencabut
           mereka di tengah jalan akan membuang penilaian yang sudah masuk. */
        DB::table('N_PA_Level_Exclusion')->insert([
            'ID_Level' => $levelId,
            'Alasan' => $alasan,
            'Created_By' => $olehUserId,
        ]);

        PaLevelDikecualikan::lupakan();

        return [
            'id_level' => $levelId,
            'nama_level' => $level->Keterangan,
            'alasan' => $alasan,
            'peserta_siklus_berjalan' => $this->cacahPesertaBerjalan($levelId),
        ];
    }

    /**
     * Mencabut pengecualian. Barisnya DINONAKTIFKAN, bukan dihapus.
     *
     * @return array<string, mixed>
     */
    public function cabut(int $levelId, ?int $olehUserId = null): array
    {
        if (! PaLevelDikecualikan::apakah($levelId)) {
            throw new RuntimeException("Level {$levelId} tidak sedang dikecualikan.");
        }

        DB::table('N_PA_Level_Exclusion')
            ->where('ID_Level', $levelId)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Flag_Aktif' => 'T',
                'Updated_At' => now(),
                'Updated_By' => $olehUserId,
            ]);

        PaLevelDikecualikan::lupakan();

        return ['id_level' => $levelId];
    }

    /**
     * Mengecualikan banyak level sekaligus dari Performance Appraisal.
     *
     * @param  list<int>  $levelIds
     * @return array{jumlah_level: int, level: list<array<string, mixed>>, total_peserta_berjalan: int, alasan: string}
     */
    public function kecualikanBanyak(array $levelIds, string $alasan, ?int $olehUserId = null): array
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new RuntimeException('Pengecualian level wajib menyebut alasannya.');
        }

        $levelIds = array_values(array_unique(array_filter(array_map('intval', $levelIds))));

        if ($levelIds === []) {
            throw new RuntimeException('Pilih setidaknya satu level untuk dikecualikan.');
        }

        return DB::transaction(function () use ($levelIds, $alasan, $olehUserId) {
            $levels = DB::table('N_PA_Level')->whereIn('ID_Level', $levelIds)->get(['ID_Level', 'Nama_Level as Keterangan'])->keyBy('ID_Level');
            $hasil = [];
            $totalBerjalan = 0;

            foreach ($levelIds as $id) {
                if (! isset($levels[$id])) {
                    throw new RuntimeException("Level {$id} tidak ada.");
                }

                if (PaLevelDikecualikan::apakah($id)) {
                    continue; // Lewati yang sudah dikecualikan
                }

                DB::table('N_PA_Level_Exclusion')->insert([
                    'ID_Level' => $id,
                    'Alasan' => $alasan,
                    'Created_By' => $olehUserId,
                ]);

                $berjalan = $this->cacahPesertaBerjalan($id);
                $totalBerjalan += $berjalan;

                $hasil[] = [
                    'id_level' => $id,
                    'nama_level' => $levels[$id]->Keterangan,
                    'peserta_siklus_berjalan' => $berjalan,
                ];
            }

            PaLevelDikecualikan::lupakan();

            return [
                'jumlah_level' => count($hasil),
                'level' => $hasil,
                'total_peserta_berjalan' => $totalBerjalan,
                'alasan' => $alasan,
            ];
        });
    }

    /**
     * Mencabut pengecualian banyak level sekaligus.
     *
     * @param  list<int>  $levelIds
     * @return array{jumlah_level: int, id_level: list<int>}
     */
    public function cabutBanyak(array $levelIds, ?int $olehUserId = null): array
    {
        $levelIds = array_values(array_unique(array_filter(array_map('intval', $levelIds))));

        if ($levelIds === []) {
            throw new RuntimeException('Pilih setidaknya satu level untuk dicabut pengecualiannya.');
        }

        return DB::transaction(function () use ($levelIds, $olehUserId) {
            $tercabut = DB::table('N_PA_Level_Exclusion')
                ->whereIn('ID_Level', $levelIds)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => now(),
                    'Updated_By' => $olehUserId,
                ]);

            PaLevelDikecualikan::lupakan();

            return [
                'jumlah_level' => (int) $tercabut,
                'id_level' => $levelIds,
            ];
        });
    }

    /**
     * Berapa peserta level ini yang sedang berada di siklus yang belum tertutup.
     *
     * Dipakai layar untuk mengatakan apa adanya bahwa mereka TIDAK ikut tercabut. Angka
     * yang tidak disebutkan membuat admin menebak, dan tebakan yang paling sering diambil
     * adalah yang paling menakutkan.
     */
    private function cacahPesertaBerjalan(int $levelId): int
    {
        return (int) DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('p.ID_Level_Saat_Cycle', $levelId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('c.Flag_Aktif', 'Y')
            /* Status siklus yang sebenarnya: DRAFT/PREPARED/LOCKED/OPEN/CLOSED/PUBLISHED. Dulu
               tertulis SELESAI/DIBATALKAN (tidak ada di data), sehingga siklus yang sudah terbit
               ikut terhitung "berjalan". */
            ->where('c.Status_Cycle', '<>', 'PUBLISHED')
            ->count();
    }
}
