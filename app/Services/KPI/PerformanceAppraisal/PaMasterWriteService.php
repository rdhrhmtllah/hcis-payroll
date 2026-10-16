<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaStatistik;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penulis master Performance Appraisal.
 *
 * TIDAK ADA PENGHAPUSAN
 *
 * Seluruh master dinonaktifkan (`Flag_Aktif = 'T'`), tidak pernah dihapus. Barisnya
 * dirujuk salinan siklus, jejak hasil, dan riwayat kalibrasi; menghapusnya membuat hasil
 * lama menunjuk sesuatu yang tak ada lagi, dan pertanyaan "kompetensi apa ini" berubah
 * jadi tak terjawab.
 *
 * MENYUNTING MASTER TIDAK MENGUBAH SIKLUS BERJALAN
 *
 * Siklus yang sudah disiapkan memakai SALINAN-nya sendiri (K26). Itu bukan efek samping
 * yang perlu ditakuti melainkan alasan master ini boleh disunting sama sekali: mengubah
 * bobot hari ini tidak menggeser nilai orang yang sudah dihitung kemarin.
 *
 * PENJAGA YANG SAMA DENGAN JALUR PERHITUNGAN
 *
 * Sigma bobot 100 diperiksa `PaBobotGuard` — penjaga yang sama yang dipakai mesin
 * perhitungan. Menuliskan pemeriksaan kedua di sini akan melahirkan dua aturan yang
 * pasti berselisih suatu hari.
 */
class PaMasterWriteService
{
    /** Batas atas pita teratas: pita terbuka, menampung nilai berapa pun di atas 100. */
    public const BATAS_ATAS_PITA = 999.99;

    /** Kegunaan pita yang memberi makan sumbu matriks HAV. */
    private const KEGUNAAN_HAVMAP = 'HAVMAP';

    /**
     * Ketiga tingkat sumbu HAV Map — dan ketiganya saja.
     *
     * Disebut sebagai konstanta, bukan angka tiga di dalam kode, karena yang mengikat
     * bukan JUMLAHNYA melainkan kecocokannya dengan `N_PA_Hav_Quadrant.Tingkat_Performa`
     * dan `Tingkat_Kompetensi`. Kalau suatu saat matriksnya memang diubah, di sinilah
     * perubahannya dimulai — bukan dengan melonggarkan penjaganya.
     */
    private const TINGKAT_HAVMAP = ['RENDAH', 'SEDANG', 'TINGGI'];

    public function __construct(
        private readonly PaBobotGuard $bobot,
        private readonly PaCycleGate $gate,
        private readonly PaCycleSetupStepService $langkah,
    ) {}

    /**
     * Menyimpan satu baris master. `$id` kosong berarti membuat baru.
     *
     * @param  array<string, mixed>  $data
     * @return array{id:int, master:string, baru:bool}
     */
    public function simpan(string $master, ?int $id, array $data, ?int $olehUserId = null): array
    {
        $k = PaMasterReadService::katalog($master);

        if ($id === null && ! $k['dapat_ditambah']) {
            throw new RuntimeException(
                "{$k['nama']} tidak bisa ditambah. ".$this->alasanTertutup($master)
            );
        }

        return match ($master) {
            'jenis-kompetensi' => $this->simpanJenisKompetensi($id, $data, $olehUserId),
            'kompetensi' => $this->simpanKompetensi($id, $data, $olehUserId),
            'kategori-penilai' => $this->simpanKategoriPenilai($id, $data, $olehUserId),
            'aturan-penilai' => $this->simpanAturanPenilai($id, $data, $olehUserId),
            'bobot-pilar' => $this->simpanBobotPilar($id, $data, $olehUserId),
            'pita-nilai' => $this->simpanPitaNilai($id, $data, $olehUserId),
            'kuadran-hav' => $this->simpanKuadran($id, $data, $olehUserId),
            'paket-soal' => $this->simpanPaketSoal($id, $data, $olehUserId),
            'butir-soal' => $this->simpanButirSoal($id, $data, $olehUserId),
            default => throw new RuntimeException("Master {$master} tidak bisa ditulis."),
        };
    }

    /**
     * Menonaktifkan satu baris. Bukan menghapus.
     */
    /**
     * Mengaktifkan kembali baris master yang dinonaktifkan. Pasangan `nonaktifkan()`.
     *
     * Skala nilai dan paket soal TIDAK lewat jalur ini: pita yang aktif lagi bisa bertumpuk
     * dengan pita lain, dan paket soal milik siklus tertentu. Aturan penilai punya jalurnya
     * sendiri (`aktifkanAturan`).
     *
     * @return list<string> peringatan untuk admin (mis. ikatan paket soal yang perlu diperiksa)
     */
    public function aktifkan(string $master, int $id, ?int $olehUserId = null): array
    {
        $k = PaMasterReadService::katalog($master);

        if (! $k['dapat_dinonaktifkan'] || in_array($master, ['pita-nilai', 'paket-soal', 'aturan-penilai'], true)) {
            throw new RuntimeException("{$k['nama']} tidak bisa diaktifkan kembali dari sini.");
        }

        $baris = DB::table($k['tabel'])->where($k['kunci'], $id)->first([$k['kunci'], 'Flag_Aktif']);

        if ($baris === null) {
            throw new RuntimeException("{$k['nama']} #{$id} tidak ada.");
        }

        if ($baris->Flag_Aktif === 'Y') {
            throw new RuntimeException("{$k['nama']} #{$id} sudah aktif.");
        }

        DB::table($k['tabel'])->where($k['kunci'], $id)->update(
            $this->kolomUbah($k['tabel'], ['Flag_Aktif' => 'Y'], $olehUserId)
        );

        return $master === 'kompetensi' ? $this->peringatanIkatanKompetensi($id) : [];
    }

    /**
     * Kompetensi yang aktif lagi menambah kompetensi wajib di setiap level yang mensyaratkan
     * jenisnya. Ikatan paket soal level itu memakai aturan 1 bagian = 1 kompetensi, jadi
     * jumlahnya bisa tidak sepadan lagi. Tidak ditolak — dilaporkan, supaya admin memeriksa.
     *
     * @return list<string>
     */
    private function peringatanIkatanKompetensi(int $competencyId): array
    {
        // Level yang menuntut kompetensi ini menurut Matriks Kompetensi per kompetensi (DDL-2026-175).
        $level = DB::table('N_PA_Competency as c')
            ->join('N_PA_Competency_Level_Requirement as r', 'r.PA_Competency_Id', '=', 'c.Id_PA_Competency')
            ->join('N_PA_Question_Binding_Master as b', 'b.ID_Level', '=', 'r.ID_Level')
            ->join('N_PA_Level as l', 'l.ID_Level', '=', 'r.ID_Level')
            ->where('c.Id_PA_Competency', $competencyId)
            ->where('r.Flag_Aktif', 'Y')
            ->where('b.Flag_Aktif', 'Y')
            ->whereNotIn('r.ID_Level', \App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan::daftar() ?: [0])
            ->distinct()
            ->orderBy('l.Nama_Level')
            ->pluck('l.Nama_Level')
            ->all();

        return $level === [] ? [] : [sprintf(
            'Kompetensi ini kini wajib di level %s. Periksa Matriks Soal: ikatan paket soal level itu '
            .'bisa tidak sepadan lagi (1 bagian ujian = 1 kompetensi).',
            implode(', ', $level),
        )];
    }

    public function nonaktifkan(string $master, int $id, ?int $olehUserId = null): void
    {
        $k = PaMasterReadService::katalog($master);

        if (! $k['dapat_dinonaktifkan']) {
            throw new RuntimeException(
                "{$k['nama']} tidak bisa dinonaktifkan. ".$this->alasanTertutup($master)
            );
        }

        $baris = DB::table($k['tabel'])->where($k['kunci'], $id)->first([$k['kunci'], 'Flag_Aktif']);

        if ($baris === null) {
            throw new RuntimeException("{$k['nama']} #{$id} tidak ada.");
        }

        if ($baris->Flag_Aktif !== 'Y') {
            throw new RuntimeException("{$k['nama']} #{$id} sudah nonaktif.");
        }

        if ($master === 'pita-nilai') {
            $this->pastikanPitaMasihUtuhTanpa($id);
        }

        /* Siklus yang dimiliki baris ini DIBACA SEBELUM dinonaktifkan. Sesudahnya,
           `Flag_Aktif = 'T'` membuat kueri penanda tidak menemukannya lagi, dan langkahnya
           ditandai atas siklus yang salah — atau tidak ditandai sama sekali. */
        $cycleId = $master === 'paket-soal'
            ? (int) DB::table($k['tabel'])->where($k['kunci'], $id)->value('PA_Cycle_Id')
            : null;

        DB::table($k['tabel'])->where($k['kunci'], $id)->update(
            $this->kolomUbah($k['tabel'], ['Flag_Aktif' => 'T'], $olehUserId)
        );

        /* Langkah PAKET_SOAL DITANDAI ULANG.
           Tanpa ini, admin yang mencabut satu paket meninggalkan langkah bertanda SELESAI
           padahal selnya kosong — dan penyiapan berjalan dengan penilai yang akan membuka
           formulir tanpa satu pun pertanyaan. Penanda yang berbohong lebih buruk daripada
           penanda yang tidak ada, karena ia menghentikan orang memeriksa. */
        if ($cycleId !== null && $cycleId > 0) {
            $this->tandaiLangkahPaketSoal($cycleId, $olehUserId);
        }
    }

    /**
     * Mengaktifkan satu versi aturan penilai, dan menonaktifkan versi aktif sebelumnya
     * pada level yang sama.
     *
     * Dua aturan aktif pada satu level bukan keadaan yang bisa dijelaskan: penyiapan
     * siklus akan menyalin salah satunya tanpa ada yang memutuskan yang mana.
     *
     * @return array{policy_id:int, id_level:int, versi:int, menggantikan:int|null}
     */
    public function aktifkanAturanPenilai(int $policyId, ?int $olehUserId = null): array
    {
        $policy = DB::table('N_PA_Rater_Policy')
            ->where('Id_PA_Rater_Policy', $policyId)
            ->first(['Id_PA_Rater_Policy', 'ID_Level', 'Versi', 'Status_Policy', 'Flag_Aktif']);

        if ($policy === null) {
            throw new RuntimeException("Aturan penilai #{$policyId} tidak ada.");
        }

        $this->pastikanBobotAturanSeratus($policyId);

        /* Kodenya `ACTIVE`/`INACTIVE`, sesuai master `N_PA_Rater_Policy_Status` (berkunci FK).
           Sampai 8 Oktober 2026 di sini tertulis `AKTIF`/`NONAKTIF`, sehingga SETIAP aktivasi
           ditolak FK dan alur "versi baru → aktifkan" buntu. */
        return DB::transaction(function () use ($policy, $policyId, $olehUserId) {
            $sebelumnya = DB::table('N_PA_Rater_Policy')
                ->where('ID_Level', $policy->ID_Level)
                ->where('Flag_Aktif', 'Y')
                ->where('Id_PA_Rater_Policy', '<>', $policyId)
                ->value('Id_PA_Rater_Policy');

            if ($sebelumnya !== null) {
                DB::table('N_PA_Rater_Policy')
                    ->where('Id_PA_Rater_Policy', $sebelumnya)
                    ->update($this->kolomUbah('N_PA_Rater_Policy', [
                        'Flag_Aktif' => 'T',
                        'Status_Policy' => 'INACTIVE',
                    ], $olehUserId));
            }

            DB::table('N_PA_Rater_Policy')
                ->where('Id_PA_Rater_Policy', $policyId)
                ->update($this->kolomUbah('N_PA_Rater_Policy', [
                    'Flag_Aktif' => 'Y',
                    'Status_Policy' => 'ACTIVE',
                    'Activated_At' => FormatTanggalHelper::getCurrentTime(),
                    'Activated_By' => $olehUserId,
                ], $olehUserId));

            return [
                'policy_id' => $policyId,
                'id_level' => (int) $policy->ID_Level,
                'versi' => (int) $policy->Versi,
                'menggantikan' => $sebelumnya === null ? null : (int) $sebelumnya,
            ];
        });
    }

    /**
     * Menyimpan seluruh rincian kategori satu aturan penilai sekaligus.
     *
     * Sengaja seluruhnya, bukan per baris: bobot antar kategori HARUS berjumlah 100, dan
     * jumlah itu hanya bisa diperiksa ketika seluruh anggotanya diketahui. Menyimpan
     * satu baris pada satu waktu berarti setiap penyimpanan di tengah pekerjaan pasti
     * melanggar aturannya sendiri.
     *
     * @param  list<array<string, mixed>>  $rincian
     * @return array{policy_id:int, baris:int}
     */
    public function simpanRincianAturan(int $policyId, array $rincian, ?int $olehUserId = null): array
    {
        if ($rincian === []) {
            throw new RuntimeException('Rincian aturan penilai tidak boleh kosong.');
        }

        $policy = DB::table('N_PA_Rater_Policy')
            ->where('Id_PA_Rater_Policy', $policyId)
            ->first(['Id_PA_Rater_Policy', 'Status_Policy']);

        if ($policy === null) {
            throw new RuntimeException("Aturan penilai #{$policyId} tidak ada.");
        }

        $bobot = [];
        $terlihat = [];

        foreach ($rincian as $r) {
            $kategoriId = (int) ($r['pa_rater_category_id'] ?? 0);

            if ($kategoriId <= 0) {
                throw new RuntimeException('Setiap rincian wajib menyebut kategori penilai.');
            }

            if (isset($terlihat[$kategoriId])) {
                throw new RuntimeException(
                    "Kategori penilai #{$kategoriId} disebut lebih dari sekali; bobotnya akan "
                    .'dihitung dua kali.'
                );
            }

            $terlihat[$kategoriId] = true;
            $bobot[(string) $kategoriId] = (float) ($r['bobot_persen'] ?? 0);
        }

        // Penjaga yang SAMA dengan yang dipakai mesin perhitungan.
        $this->bobot->pastikanSeratus($bobot, "rincian aturan penilai #{$policyId}");

        return DB::transaction(function () use ($policyId, $rincian, $olehUserId) {
            /* Nilai lama per kategori, untuk kolom yang TIDAK dikirim pemanggil. Penyimpanan ini
               menonaktifkan semua baris lalu menyisipkan ulang; tanpa ini, layar yang hanya
               mengirim bobot (Matriks Jenis Penilai) menghapus relasi organisasi tiap kategori,
               dan siklus berikutnya lahir tanpa calon penilai atasan/rekan/bawahan
               (`calonDariRelasi(null)` = kosong). Kunci yang dikirim bernilai null tetap berarti
               "kosongkan" — yang dibedakan adalah "tidak dikirim" dari "dikosongkan". */
            $lama = DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policyId)
                ->where('Flag_Aktif', 'Y')
                ->get(['PA_Rater_Category_Id', 'Organization_Relationship_Type_Id', 'Catatan_Aturan', 'Flag_Izinkan_Manual'])
                ->keyBy(fn ($d) => (int) $d->PA_Rater_Category_Id);

            DB::table('N_PA_Rater_Policy_Detail')
                ->where('PA_Rater_Policy_Id', $policyId)
                ->where('Flag_Aktif', 'Y')
                ->update($this->kolomUbah('N_PA_Rater_Policy_Detail', ['Flag_Aktif' => 'T'], $olehUserId));

            $sisip = [];
            $urutan = 0;

            foreach ($rincian as $r) {
                $urutan++;
                $min = (int) ($r['min_rater'] ?? 1);
                $maks = isset($r['maks_rater']) && $r['maks_rater'] !== null ? (int) $r['maks_rater'] : null;
                $minSelesai = isset($r['min_rater_selesai']) && $r['min_rater_selesai'] !== null
                    ? (int) $r['min_rater_selesai'] : null;

                if ($min < 1) {
                    throw new RuntimeException('Min_Rater minimal 1; kategori tanpa penilai bukan kategori.');
                }

                if ($maks !== null && $maks < $min) {
                    throw new RuntimeException("Maks_Rater ({$maks}) tidak boleh kurang dari Min_Rater ({$min}).");
                }

                /* Sama dengan jalur aturan siklus (PaCycleRaterPolicyService). Tanpa ini master
                   Officer Specialist (13 Okt 2026) tersimpan "ditugaskan 1, harus selesai 2":
                   pengusul cukup membentuk satu penilai, kategori itu lolos dikunci, lalu PASTI
                   gugur saat dihitung — tanpa satu pesan pun. */
                if ($minSelesai !== null && $minSelesai > $min) {
                    throw new RuntimeException(
                        "Min_Rater_Selesai ({$minSelesai}) melebihi Min_Rater ({$min}); penilai yang "
                        .'ditugaskan tidak akan pernah cukup untuk menghitung kategori ini. Naikkan '
                        .'Min_Rater, atau turunkan Min_Rater_Selesai.'
                    );
                }

                if ($minSelesai !== null && $maks !== null && $minSelesai > $maks) {
                    throw new RuntimeException(
                        "Min_Rater_Selesai ({$minSelesai}) melebihi Maks_Rater ({$maks}); ambang itu "
                        .'tidak akan pernah tercapai.'
                    );
                }

                $sebelum = $lama->get((int) $r['pa_rater_category_id']);
                $atauLama = fn (string $kunci, string $kolom) => array_key_exists($kunci, $r)
                    ? $r[$kunci]
                    : ($sebelum?->{$kolom} ?? null);

                $sisip[] = [
                    'PA_Rater_Policy_Id' => $policyId,
                    'PA_Rater_Category_Id' => (int) $r['pa_rater_category_id'],
                    'Organization_Relationship_Type_Id' => $atauLama('organization_relationship_type_id', 'Organization_Relationship_Type_Id'),
                    'Bobot_Persen' => (float) ($r['bobot_persen'] ?? 0),
                    'Min_Rater' => $min,
                    'Maks_Rater' => $maks,
                    'Min_Rater_Selesai' => $minSelesai,
                    'Flag_Izinkan_Manual' => ($atauLama('flag_izinkan_manual', 'Flag_Izinkan_Manual') ?? 'T') === 'Y' ? 'Y' : 'T',
                    'Urutan_Tampil' => (int) ($r['urutan_tampil'] ?? $urutan),
                    'Catatan_Aturan' => $atauLama('catatan_aturan', 'Catatan_Aturan'),
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $olehUserId,
                ];
            }

            DB::table('N_PA_Rater_Policy_Detail')->insert($sisip);

            return ['policy_id' => $policyId, 'baris' => count($sisip)];
        });
    }

    /* ------------------------------------------------------------------ *
     * Penulis per master                                                  *
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanJenisKompetensi(?int $id, array $d, ?int $u): array
    {
        $isi = [
            'Kode_Type' => $this->kode($d['kode_type'] ?? null, 'Kode jenis kompetensi'),
            'Nama_Type' => $this->teksWajib($d['nama_type'] ?? null, 'Nama jenis kompetensi'),
            'Deskripsi' => $d['deskripsi'] ?? null,
            'Urutan_Tampil' => max(1, (int) ($d['urutan_tampil'] ?? 1)),
        ];

        $this->pastikanKodeBelumDipakai('N_PA_Competency_Type', 'Kode_Type', 'Id_PA_Competency_Type', $isi['Kode_Type'], $id);

        return $this->tulis('jenis-kompetensi', 'N_PA_Competency_Type', 'Id_PA_Competency_Type', $id, $isi, $u);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanKompetensi(?int $id, array $d, ?int $u): array
    {
        $typeId = (int) ($d['pa_competency_type_id'] ?? 0);

        if (! DB::table('N_PA_Competency_Type')->where('Id_PA_Competency_Type', $typeId)->exists()) {
            throw new RuntimeException('Jenis kompetensi tidak ada.');
        }

        $isi = [
            'PA_Competency_Type_Id' => $typeId,
            'Kode_Competency' => $this->kode($d['kode_competency'] ?? null, 'Kode kompetensi'),
            'Nama_Competency' => $this->teksWajib($d['nama_competency'] ?? null, 'Nama kompetensi'),
            'Deskripsi' => $d['deskripsi'] ?? null,
            // Kompetensi tak berskor tidak menyumbang angka; ia hanya muncul sebagai
            // rincian. Bakunya berskor, karena itu yang lazim.
            'Flag_Berskor' => ($d['flag_berskor'] ?? 'Y') === 'T' ? 'T' : 'Y',
            'Nilai_Ambang' => $this->ambangKompetensi($d['nilai_ambang'] ?? null),
            'Flag_Radar' => ($d['flag_radar'] ?? 'Y') === 'T' ? 'T' : 'Y',
            'Urutan_Tampil' => max(1, (int) ($d['urutan_tampil'] ?? 1)),
        ];

        /* Disentuh hanya bila dikirim (atau saat membuat baru): klien lama yang belum
           mengenal kolom ini tidak boleh menghapus perilaku yang sudah ditulis HC. */
        if ($id === null || array_key_exists('expected_behaviour', $d)) {
            $isi['Expected_Behaviour'] = $this->expectedBehaviour($d['expected_behaviour'] ?? null);
        }

        /* Kompetensi kualitatif membawa teks, bukan angka: ia tidak pernah punya baris di
           `N_PA_Result_Competency`. Dibiarkan masuk radar, ia menjadi sumbu yang nilainya
           selamanya kosong — dan ambangnya menjadi standar bagi sesuatu yang tidak pernah
           diukur. Keduanya ditolak di sini, bukan lewat CHECK lintas kolom, supaya
           galatnya bisa menyebutkan sebabnya kepada HC. */
        if ($isi['Flag_Berskor'] === 'T') {
            if ($isi['Flag_Radar'] === 'Y') {
                throw new RuntimeException(
                    'Kompetensi tak berskor tidak bisa dijadikan sumbu radar: ia tidak '
                    .'menghasilkan angka, sehingga sumbunya akan selalu kosong.'
                );
            }

            if ($isi['Nilai_Ambang'] !== null) {
                throw new RuntimeException(
                    'Kompetensi tak berskor tidak bisa punya ambang nilai: tidak ada angka '
                    .'yang bisa dibandingkan dengannya.'
                );
            }
        }

        $this->pastikanKodeBelumDipakai('N_PA_Competency', 'Kode_Competency', 'Id_PA_Competency', $isi['Kode_Competency'], $id);

        $this->pastikanBerskorTidakBerubahDiSiklusBerjalan($id, $isi['Flag_Berskor']);

        return $this->tulis('kompetensi', 'N_PA_Competency', 'Id_PA_Competency', $id, $isi, $u);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    /**
     * `Flag_Berskor` dibaca LANGSUNG dari master saat menghitung — belum ada salinan per siklus
     * (itu butuh DDL tersendiri). Mengubahnya selagi kompetensi dipakai paket soal siklus yang
     * belum terbit akan mengubah cara hasil siklus itu dihitung pada hitung ulang berikutnya,
     * diam-diam. Ditolak dengan menyebut siklusnya. `Nilai_Ambang` dan `Flag_Radar` hanya
     * memengaruhi tampilan, jadi tidak ditahan di sini.
     */
    private function pastikanBerskorTidakBerubahDiSiklusBerjalan(?int $id, string $berskorBaru): void
    {
        if ($id === null) {
            return;
        }

        $lama = DB::table('N_PA_Competency')->where('Id_PA_Competency', $id)->value('Flag_Berskor');

        if ($lama === null || $lama === $berskorBaru) {
            return;
        }

        $siklus = DB::table('N_PA_Cycle_Question_Package_Item as i')
            ->join('N_PA_Cycle_Question_Package as k', 'k.Id_PA_Cycle_Question_Package', '=', 'i.PA_Cycle_Question_Package_Id')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'k.PA_Cycle_Id')
            ->where('i.PA_Competency_Id', $id)
            ->where('i.Flag_Aktif', 'Y')
            ->where('c.Flag_Aktif', 'Y')
            ->whereIn('c.Status_Cycle', ['PREPARED', 'LOCKED', 'OPEN', 'CLOSED'])
            /* Siklus yang sudah membekukan kompetensi ini (DDL-2026-170) membaca salinannya,
               jadi suntingan master tidak lagi menyentuhnya. */
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from(PaKompetensiSiklus::TABEL.' as cks')
                ->whereColumn('cks.PA_Cycle_Id', 'c.Id_PA_Cycle')
                ->where('cks.PA_Competency_Id', $id))
            ->distinct()
            ->pluck('c.Kode_Cycle');

        if ($siklus->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Status berskor kompetensi ini tidak bisa diubah: ia dipakai siklus yang belum terbit (%s), '
                .'dan perubahannya akan mengubah cara hasil siklus itu dihitung. Ubah sesudah siklus tersebut terbit.',
                $siklus->implode(', '),
            ));
        }
    }

    private function simpanKategoriPenilai(?int $id, array $d, ?int $u): array
    {
        if ($id === null) {
            throw new RuntimeException(
                'Kategori penilai tidak bisa ditambah. '.$this->alasanTertutup('kategori-penilai')
            );
        }

        // Kodenya SENGAJA tidak bisa diubah: ia menjadi kunci sambung di salinan siklus
        // dan jejak hasil yang sudah tersimpan.
        return $this->tulis('kategori-penilai', 'N_PA_Rater_Category', 'Id_PA_Rater_Category', $id, [
            'Nama_Kategori' => $this->teksWajib($d['nama_kategori'] ?? null, 'Nama kategori'),
            'Deskripsi' => $this->teksWajib($d['deskripsi'] ?? null, 'Deskripsi kategori'),
        ], $u);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanAturanPenilai(?int $id, array $d, ?int $u): array
    {
        $isi = [
            'Kode_Policy' => $this->kode($d['kode_policy'] ?? null, 'Kode aturan'),
            'Nama_Policy' => $this->teksWajib($d['nama_policy'] ?? null, 'Nama aturan'),
            'Deskripsi' => $d['deskripsi'] ?? null,
            'ID_Level' => (int) ($d['id_level'] ?? 0),
            'Berlaku_Dari' => $d['berlaku_dari'] ?? date('Y-m-d'),
            'Berlaku_Sampai' => $d['berlaku_sampai'] ?? null,
            'Catatan_Versi' => $d['catatan_versi'] ?? null,
        ];

        if ($isi['ID_Level'] <= 0) {
            throw new RuntimeException('Aturan penilai wajib menyebut level.');
        }

        if ($id === null) {
            // Versi baru selalu lahir sebagai DRAFT dan TIDAK aktif. Aturan yang langsung
            // aktif begitu disimpan akan menggeser aturan berjalan sebelum bobotnya
            // sempat diisi - dan bobot kosong berarti sigma bukan 100.
            $isi['Versi'] = 1 + (int) DB::table('N_PA_Rater_Policy')
                ->where('Kode_Policy', $isi['Kode_Policy'])->max('Versi');
            $isi['Status_Policy'] = 'DRAFT';
            $isi['Flag_Aktif'] = 'T';
        }

        return $this->tulis('aturan-penilai', 'N_PA_Rater_Policy', 'Id_PA_Rater_Policy', $id, $isi, $u);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanBobotPilar(?int $id, array $d, ?int $u): array
    {
        $level = (int) ($d['id_level'] ?? 0);
        $kpi = (float) ($d['bobot_kpi'] ?? 0);
        $kompetensi = (float) ($d['bobot_kompetensi'] ?? 0);

        if ($level <= 0) {
            throw new RuntimeException('Bobot pilar wajib menyebut level.');
        }

        $this->bobot->pastikanSeratus(
            ['KPI' => $kpi, 'KOMPETENSI' => $kompetensi],
            "bobot pilar level {$level}"
        );

        $isi = [
            'ID_Level' => $level,
            'Bobot_KPI' => $kpi,
            'Bobot_Kompetensi' => $kompetensi,
            'Catatan' => $d['catatan'] ?? null,
        ];

        // Satu level tepat satu bobot. Kalau levelnya sudah punya baris, yang terjadi
        // adalah pembaruan - bukan baris kedua yang membuat penyiapan siklus harus
        // memilih di antara keduanya.
        $adaId = $id ?? DB::table('N_PA_Pillar_Weight')->where('ID_Level', $level)->value('Id_PA_Pillar_Weight');

        return $this->tulis('bobot-pilar', 'N_PA_Pillar_Weight', 'Id_PA_Pillar_Weight',
            $adaId === null ? null : (int) $adaId, $isi, $u);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanPitaNilai(?int $id, array $d, ?int $u): array
    {
        $isi = [
            'Score_Type' => $this->kode($d['sumbu'] ?? null, 'Score_Type'),
            'Score_Usage' => $this->kode($d['kegunaan'] ?? null, 'Score_Usage'),
            'Kode_Range' => $this->kode($d['kode_range'] ?? null, 'Kode pita'),
            'Nama_Range' => $this->teksWajib($d['nama_range'] ?? null, 'Nama pita'),
            'Nilai_Dari' => (float) ($d['nilai_dari'] ?? 0),
            'Nilai_Sampai' => (float) ($d['nilai_sampai'] ?? 0),
            'Nilai_Hasil' => $d['nilai_hasil'] ?? null,
            'Target_Persen' => $d['target_persen'] ?? null,
            'Warna' => $d['warna'] ?? null,
            'Urutan_Tampil' => max(1, (int) ($d['urutan_tampil'] ?? 1)),
            // Batas bawah z (grade kurva). Kosong = grade terendah, atau belum diatur.
            'Batas_Z' => ($d['batas_z'] ?? null) === null || $d['batas_z'] === '' ? null : round((float) $d['batas_z'], 2),
        ];

        if ($isi['Batas_Z'] !== null && abs($isi['Batas_Z']) > 9.99) {
            throw new RuntimeException('Batas z grade harus di antara -9,99 dan 9,99.');
        }

        if ($isi['Nilai_Sampai'] <= $isi['Nilai_Dari']) {
            throw new RuntimeException(
                'Batas atas pita harus lebih besar daripada batas bawahnya. Batas atas bersifat '
                .'EKSKLUSIF: pita 80–90 memuat 80 sampai 89,99 dan tidak memuat 90.'
            );
        }

        /* Transaksinya dibawa sendiri, tidak menumpang pemanggil.
           Penjaga kesinambungan hanya bisa dinilai SESUDAH baris ini tertulis - keadaan
           akhirlah yang bermakna, bukan keadaan sebelum perubahan. Menggantungkan
           pembatalannya pada transaksi pemanggil berarti service ini benar hanya ketika
           dipanggil dari satu tempat tertentu, dan itu bukan jaminan yang bisa dipegang.
           Laravel memakai savepoint bila pemanggilnya memang sudah bertransaksi. */
        /* Pita HAV Map BARU tidak pernah sah: tiap sumbu wajib tepat tiga. Ditolak di pintu dengan
           pesan yang menunjuk jalan keluarnya — dulu penjaga kesinambungan bekerja lebih dulu dan
           melapor "berlubang atau tumpang tindih", yang menyuruh orang mencari celah yang tidak ada. */
        if ($id === null && $isi['Score_Usage'] === self::KEGUNAAN_HAVMAP
            && DB::table('N_PA_Score_Range')->where('Score_Usage', self::KEGUNAAN_HAVMAP)
                ->where('Score_Type', $isi['Score_Type'])->where('Flag_Aktif', 'Y')->count() >= count(self::TINGKAT_HAVMAP)) {
            throw new RuntimeException(sprintf(
                'Sumbu %s pada HAV Map sudah punya %d pita (%s) dan tidak bisa ditambah — matriksnya tiga kali tiga. Untuk mengubah '
                .'rentang (mis. RENDAH 0–30), sunting pita yang ada — batas pita tetangganya ikut menyesuaikan.',
                $isi['Score_Type'], count(self::TINGKAT_HAVMAP), implode(', ', self::TINGKAT_HAVMAP),
            ));
        }

        return DB::transaction(function () use ($id, $isi, $u) {
            $lama = $id === null ? null : DB::table('N_PA_Score_Range')->where('Id_PA_Score_Range', $id)
                ->first(['Score_Type', 'Score_Usage', 'Nilai_Dari', 'Nilai_Sampai', 'Flag_Aktif']);

            $hasil = $this->tulis('pita-nilai', 'N_PA_Score_Range', 'Id_PA_Score_Range', $id, $isi, $u);

            if ($lama !== null && $lama->Flag_Aktif === 'Y'
                && $lama->Score_Type === $isi['Score_Type'] && $lama->Score_Usage === $isi['Score_Usage']) {
                $hasil['tetangga_disesuaikan'] = $this->geserBatasTetangga($id, $lama, $isi, $u);
            }

            $this->pastikanPitaBersinambung((string) $isi['Score_Type'], (string) $isi['Score_Usage']);
            $this->pastikanSumbuHavMapTetapTiga((string) $isi['Score_Usage']);

            if ($isi['Score_Type'] === 'FINAL' && $isi['Score_Usage'] === 'GRADE') {
                $this->pastikanBatasZGrade();
            }

            return $hasil;
        });
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanKuadran(?int $id, array $d, ?int $u): array
    {
        if ($id === null) {
            throw new RuntimeException(
                'Kuadran HAV tidak bisa ditambah. '.$this->alasanTertutup('kuadran-hav')
            );
        }

        // Tingkat performa dan kompetensi TIDAK bisa diubah: keduanya menentukan sel
        // matriks, dan menggesernya akan menabrak kotak lain.
        return $this->tulis('kuadran-hav', 'N_PA_Hav_Quadrant', 'Id_PA_Hav_Quadrant', $id, [
            'Nama_Kuadran' => $this->teksWajib($d['nama_kuadran'] ?? null, 'Nama kuadran'),
            'Deskripsi' => $d['deskripsi'] ?? null,
            'Warna' => $d['warna'] ?? null,
            'Urutan_Tampil' => max(1, (int) ($d['urutan_tampil'] ?? 1)),
        ], $u);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanPaketSoal(?int $id, array $d, ?int $u): array
    {
        $cycleId = (int) ($d['pa_cycle_id'] ?? 0);

        if ($cycleId <= 0) {
            throw new RuntimeException('Paket soal wajib menyebut siklus.');
        }

        // Paket soal hanya boleh disunting selagi siklusnya belum dikunci.
        $this->gate->pastikanBoleh(PaCycleGate::PILIH_PAKET_SOAL, $cycleId);

        $hasil = $this->tulis('paket-soal', 'N_PA_Cycle_Question_Package', 'Id_PA_Cycle_Question_Package', $id, [
            'PA_Cycle_Id' => $cycleId,
            'ID_Level' => (int) ($d['id_level'] ?? 0),
            'Id_Master_Ujian' => $this->kode($d['id_master_ujian'] ?? null, 'Kode paket'),
            'Nama_Ujian_Sumber' => $d['nama_ujian_sumber'] ?? null,
            'Sumber_Kontrak' => $d['sumber_kontrak'] ?? 'HCLEARN',
            'Catatan' => $d['catatan'] ?? null,
        ], $u);

        $this->tandaiLangkahPaketSoal($cycleId, $u);

        return $hasil;
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array{id:int, master:string, baru:bool}
     */
    private function simpanButirSoal(?int $id, array $d, ?int $u): array
    {
        $paketId = (int) ($d['pa_cycle_question_package_id'] ?? 0);

        $paket = DB::table('N_PA_Cycle_Question_Package')
            ->where('Id_PA_Cycle_Question_Package', $paketId)
            ->first(['PA_Cycle_Id']);

        if ($paket === null) {
            throw new RuntimeException('Paket soal tidak ada.');
        }

        $this->gate->pastikanBoleh(PaCycleGate::PILIH_PAKET_SOAL, (int) $paket->PA_Cycle_Id);

        /* SATU PENUNJUK SAJA: kompetensinya.
           Sampai 7 September 2026 butir bisa menunjuk kompetensi ATAU jenis catatan, dan
           keduanya sekaligus ditolak di sini. Pemisahan itu tidak diperlukan lagi: perannya
           dibaca dari `Flag_Berskor` kompetensinya sendiri — 'Y' dirata-ratakan, 'T' dibaca
           apa adanya sebagai catatan. Kolom `PA_Result_Note_Type_Id` tidak lagi ditulis dari
           sini; pencabutan kolomnya perubahan tersendiri yang dijalankan manusia. */
        $competencyId = $d['pa_competency_id'] ?? null;

        return $this->tulis('butir-soal', 'N_PA_Cycle_Question_Package_Item',
            'Id_PA_Cycle_Question_Package_Item', $id, [
                'PA_Cycle_Question_Package_Id' => $paketId,
                'Id_Jenis_Soal' => $this->kode($d['kode_soal'] ?? null, 'Kode soal'),
                'Nama_Bagian_Sumber' => $d['nama_soal_sumber'] ?? null,
                'Jenis_Soal' => $d['jenis_soal'] ?? null,
                'PA_Competency_Id' => $competencyId,
                'Bobot_Penilaian' => $d['bobot_penilaian'] ?? null,
                'Skor_Maksimal' => $d['maksimal_score'] ?? null,
                'Urutan_Tampil' => max(1, (int) ($d['urutan_tampil'] ?? 1)),
            ], $u);
    }

    /* ------------------------------------------------------------------ *
     * Penjaga                                                             *
     * ------------------------------------------------------------------ */

    /**
     * Pita satu (sumbu, kegunaan) harus bersinambung tanpa lubang dan tanpa tumpang tindih.
     *
     * DML-2026-078 pernah menyeed pita seolah batas atasnya inklusif dan meninggalkan
     * lubang di setiap batas. Penjaganya waktu itu LOLOS justru karena cacatnya: ia
     * memeriksa nilai terkecil, terbesar, dan tumpang tindih — tidak pernah memeriksa
     * kesinambungan. Nilai yang jatuh ke lubang tidak menghasilkan galat apa pun sampai
     * seseorang benar-benar mendapat angka itu.
     */
    /**
     * Pita ber-kegunaan `HAVMAP` harus TETAP TIGA per sumbu, dan kodenya tetap itu-itu.
     *
     * Penjaga kesinambungan di bawah hanya memastikan pitanya tidak berlubang — ia tidak
     * peduli ada berapa. Padahal enam baris HAVMAP (tiga untuk KPI, tiga untuk kompetensi)
     * memberi makan SUMBU matriks HAV yang berukuran tiga kali tiga.
     *
     * Menambah pita keempat menghasilkan nilai yang jatuh ke kode yang tidak dikenal
     * `N_PA_Hav_Quadrant`, dan orangnya **dihitung lalu tidak mendarat di kotak mana pun**.
     * Bukan galat, bukan nol — hilang. Itu bentuk kegagalan yang paling lama tidak
     * ketahuan, karena satu-satunya gejalanya adalah HAV Map yang jumlahnya tidak
     * menggenapi jumlah peserta, dan tak seorang pun menjumlahkan sembilan kotak.
     *
     * Kegunaan `GRADE` sengaja TIDAK dibatasi: berapa banyak pita grade dan di mana
     * batasnya memang keputusan HC, dan menambah grade baru tidak merusak apa pun.
     */
    private function pastikanSumbuHavMapTetapTiga(string $kegunaan): void
    {
        if (strtoupper($kegunaan) !== self::KEGUNAAN_HAVMAP) {
            return;
        }

        $sumbu = DB::table('N_PA_Score_Range')
            ->where('Score_Usage', self::KEGUNAAN_HAVMAP)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Score_Type')
            ->selectRaw('Score_Type as sumbu, COUNT(*) as jumlah')
            ->get();

        foreach ($sumbu as $s) {
            if ((int) $s->jumlah === count(self::TINGKAT_HAVMAP)) {
                continue;
            }

            throw new RuntimeException(sprintf(
                'Sumbu %s pada HAV Map berisi %d pita, seharusnya tepat %d (%s). Matriks HAV '
                .'berukuran tiga kali tiga; nilai yang jatuh di luar ketiga tingkat itu akan '
                .'dihitung tetapi tidak mendarat di kotak mana pun — orangnya hilang dari '
                .'peta tanpa satu pun galat.',
                $s->sumbu, $s->jumlah, count(self::TINGKAT_HAVMAP),
                implode(', ', self::TINGKAT_HAVMAP),
            ));
        }

        // Kodenya juga harus itu-itu: `N_PA_Hav_Quadrant` mencocokkan lewat kode ini, jadi
        // pita bernama lain sama saja dengan pita yang tidak ada.
        $kode = DB::table('N_PA_Score_Range')
            ->where('Score_Usage', self::KEGUNAAN_HAVMAP)
            ->where('Flag_Aktif', 'Y')
            ->whereNotIn('Kode_Range', self::TINGKAT_HAVMAP)
            ->pluck('Kode_Range')
            ->unique()
            ->all();

        if ($kode !== []) {
            throw new RuntimeException(sprintf(
                'Kode pita HAV Map tidak dikenal: %s. Kuadran dicocokkan lewat kode ini, jadi '
                .'yang dipakai harus persis %s.',
                implode(', ', $kode), implode(', ', self::TINGKAT_HAVMAP),
            ));
        }
    }

    /**
     * Batas z grade dinilai SEBAGAI HIMPUNAN, sesudah baris tertulis. Selama belum satu pun
     * diisi (masa peralihan), pita lama tetap sah dan tidak ada yang ditolak.
     */
    private function pastikanBatasZGrade(): void
    {
        $grade = DB::table('N_PA_Score_Range')
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')->where('Flag_Aktif', 'Y')
            // Grade tertinggi (rentang nilai paling atas) lebih dulu, supaya arah z bisa diperiksa.
            ->orderByDesc('Nilai_Dari')
            ->get(['Kode_Range', 'Batas_Z']);

        if ($grade->whereNotNull('Batas_Z')->isEmpty()) {
            return;
        }

        PaKurvaStatistik::pastikanBatasZ($grade->map(fn ($g) => [
            'kode' => (string) $g->Kode_Range,
            'batas_z' => $g->Batas_Z === null ? null : (float) $g->Batas_Z,
        ])->values()->all(), true);
    }

    private function pastikanPitaBersinambung(string $sumbu, string $kegunaan): void
    {
        $pita = DB::table('N_PA_Score_Range')
            ->where('Score_Type', $sumbu)
            ->where('Score_Usage', $kegunaan)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nilai_Dari')
            ->get(['Kode_Range', 'Nilai_Dari', 'Nilai_Sampai']);

        if ($pita->isEmpty()) {
            return;
        }

        /* KEDUA UJUNG ikut dijaga, bukan hanya sambungannya. Nilai akhir bisa melewati 100
           (KPI dibatasi 125), dan pita teratas yang berakhir di 100 membuat nilai 116 tidak
           masuk pita mana pun: grade/kuadran kosong tanpa satu pun galat. Ujung atas memakai
           konvensi pita terbuka 999,99. */
        $bawah = (float) $pita->first()->Nilai_Dari;
        $atas = (float) $pita->last()->Nilai_Sampai;

        if ($bawah > 0.001) {
            throw new RuntimeException(sprintf(
                'Pita %s/%s harus dimulai dari 0; pita terendah (%s) mulai di %s, sehingga nilai di bawahnya tidak masuk pita mana pun.',
                $sumbu, $kegunaan, $pita->first()->Kode_Range, rtrim(rtrim(number_format($bawah, 2, '.', ''), '0'), '.'),
            ));
        }

        if ($atas < self::BATAS_ATAS_PITA - 0.001) {
            throw new RuntimeException(sprintf(
                'Pita teratas %s/%s (%s) berakhir di %s. Nilai bisa melewati 100, jadi pita teratas harus terbuka sampai %s.',
                $sumbu, $kegunaan, $pita->last()->Kode_Range, rtrim(rtrim(number_format($atas, 2, '.', ''), '0'), '.'),
                number_format(self::BATAS_ATAS_PITA, 2, ',', '.'),
            ));
        }

        if ($pita->count() < 2) {
            return;
        }

        $sebelumnya = null;

        foreach ($pita as $p) {
            if ($sebelumnya === null) {
                $sebelumnya = $p;

                continue;
            }

            $batasAtas = (float) $sebelumnya->Nilai_Sampai;
            $batasBawah = (float) $p->Nilai_Dari;

            if (abs($batasAtas - $batasBawah) > 0.001) {
                throw new RuntimeException(sprintf(
                    'Pita %s/%s berlubang atau tumpang tindih: %s berakhir di %s sedangkan %s '
                    .'mulai di %s. Batas atas bersifat eksklusif, jadi keduanya harus bertemu '
                    .'di angka yang sama persis — nilai yang jatuh di antaranya tidak akan '
                    .'masuk pita mana pun.',
                    $sumbu, $kegunaan, $sebelumnya->Kode_Range,
                    rtrim(rtrim(number_format($batasAtas, 2, '.', ''), '0'), '.'),
                    $p->Kode_Range,
                    rtrim(rtrim(number_format($batasBawah, 2, '.', ''), '0'), '.')
                ));
            }

            $sebelumnya = $p;
        }
    }

    /**
     * Menonaktifkan satu pita hampir selalu meninggalkan lubang. Diperiksa sebelum, bukan
     * sesudah — supaya penolakannya tidak perlu membatalkan tulisan.
     */
    /**
     * Batas pita yang digeser ikut menggeser batas TETANGGA yang bersentuhan dengannya.
     *
     * Tanpa ini batas antarpita mustahil diubah: tiap simpan hanya menulis satu pita lalu
     * kesinambungan diperiksa, sehingga menggeser batas Rendah dari 60 ke 70 selalu bertumpuk
     * dengan Sedang yang masih mulai di 60 — dan pita HAV tidak bisa ditambah (wajib tiga)
     * maupun dinonaktifkan (berlubang). Buntu (defect UAT 8 Okt 2026).
     *
     * @return list<string> kode pita tetangga yang ikut disesuaikan
     */
    private function geserBatasTetangga(int $id, object $lama, array $baru, ?int $u): array
    {
        $disesuaikan = [];
        $kelompok = fn () => DB::table('N_PA_Score_Range')
            ->where('Score_Type', $baru['Score_Type'])->where('Score_Usage', $baru['Score_Usage'])
            ->where('Flag_Aktif', 'Y')->where('Id_PA_Score_Range', '<>', $id);

        $geser = [
            // Tetangga bawah: yang berakhir tepat di batas bawah lama.
            ['kolom_cocok' => 'Nilai_Sampai', 'nilai_lama' => (float) $lama->Nilai_Dari, 'nilai_baru' => (float) $baru['Nilai_Dari']],
            // Tetangga atas: yang mulai tepat di batas atas lama.
            ['kolom_cocok' => 'Nilai_Dari', 'nilai_lama' => (float) $lama->Nilai_Sampai, 'nilai_baru' => (float) $baru['Nilai_Sampai']],
        ];

        foreach ($geser as $g) {
            if (abs($g['nilai_lama'] - $g['nilai_baru']) < 0.001) {
                continue;
            }

            $tetangga = $kelompok()->whereBetween($g['kolom_cocok'], [$g['nilai_lama'] - 0.001, $g['nilai_lama'] + 0.001])
                ->first(['Id_PA_Score_Range', 'Kode_Range', 'Nilai_Dari', 'Nilai_Sampai']);

            if ($tetangga === null) {
                continue;
            }

            $dari = $g['kolom_cocok'] === 'Nilai_Dari' ? $g['nilai_baru'] : (float) $tetangga->Nilai_Dari;
            $sampai = $g['kolom_cocok'] === 'Nilai_Sampai' ? $g['nilai_baru'] : (float) $tetangga->Nilai_Sampai;

            if ($sampai - $dari < 0.01) {
                throw new RuntimeException(sprintf(
                    'Batas baru menelan seluruh pita %s (%s–%s). Sisakan rentang untuknya, atau geser '
                    .'dulu batas pita di sebelahnya.',
                    $tetangga->Kode_Range,
                    rtrim(rtrim(number_format((float) $tetangga->Nilai_Dari, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format((float) $tetangga->Nilai_Sampai, 2, '.', ''), '0'), '.'),
                ));
            }

            DB::table('N_PA_Score_Range')->where('Id_PA_Score_Range', $tetangga->Id_PA_Score_Range)->update(
                $this->kolomUbah('N_PA_Score_Range', [$g['kolom_cocok'] => $g['nilai_baru']], $u)
            );
            $disesuaikan[] = (string) $tetangga->Kode_Range;
        }

        return $disesuaikan;
    }

    private function pastikanPitaMasihUtuhTanpa(int $id): void
    {
        $target = DB::table('N_PA_Score_Range')->where('Id_PA_Score_Range', $id)
            ->first(['Score_Type', 'Score_Usage', 'Kode_Range']);

        if ($target === null) {
            return;
        }

        $sisa = DB::table('N_PA_Score_Range')
            ->where('Score_Type', $target->Score_Type)->where('Score_Usage', $target->Score_Usage)
            ->where('Flag_Aktif', 'Y')->where('Id_PA_Score_Range', '<>', $id)
            ->count();

        if ($sisa === 0) {
            return;
        }

        if (strtoupper((string) $target->Score_Usage) === self::KEGUNAAN_HAVMAP) {
            throw new RuntimeException(sprintf(
                'Pita HAV Map %s tidak bisa dinonaktifkan: tiap sumbu wajib tepat tiga pita (%s) '
                .'karena matriksnya tiga kali tiga. Untuk mengubah rentangnya, sunting batas pitanya — '
                .'batas pita tetangga ikut menyesuaikan otomatis.',
                $target->Kode_Range, implode(', ', self::TINGKAT_HAVMAP),
            ));
        }

        throw new RuntimeException(sprintf(
            'Pita %s tidak bisa dinonaktifkan sendirian: %s/%s masih punya %d pita lain, dan '
            .'membuangnya akan meninggalkan rentang nilai yang tidak masuk pita mana pun. '
            .'Lebarkan dulu pita tetangganya sehingga menutup rentangnya.',
            $target->Kode_Range, $target->Score_Type, $target->Score_Usage, $sisa
        ));
    }

    private function pastikanBobotAturanSeratus(int $policyId): void
    {
        $bobot = DB::table('N_PA_Rater_Policy_Detail')
            ->where('PA_Rater_Policy_Id', $policyId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Bobot_Persen', 'PA_Rater_Category_Id')
            ->all();

        if ($bobot === []) {
            throw new RuntimeException(
                "Aturan penilai #{$policyId} belum punya satu pun rincian kategori. "
                .'Mengaktifkannya berarti level itu tidak punya penilai sama sekali.'
            );
        }

        $this->bobot->pastikanSeratus($bobot, "aturan penilai #{$policyId}");
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $isi
     * @return array{id:int, master:string, baru:bool}
     */
    private function tulis(string $master, string $tabel, string $kunci, ?int $id, array $isi, ?int $u): array
    {
        if ($id === null) {
            $isi['Flag_Aktif'] = $isi['Flag_Aktif'] ?? 'Y';
            $isi = $this->kolomBuat($tabel, $isi, $u);

            $baru = (int) DB::table($tabel)->insertGetId($isi, $kunci);

            return ['id' => $baru, 'master' => $master, 'baru' => true];
        }

        if (! DB::table($tabel)->where($kunci, $id)->exists()) {
            throw new RuntimeException("Baris #{$id} tidak ada.");
        }

        DB::table($tabel)->where($kunci, $id)->update($this->kolomUbah($tabel, $isi, $u));

        return ['id' => $id, 'master' => $master, 'baru' => false];
    }

    /**
     * Tabel salinan memakai `Snapshot_At/By`, master biasa memakai `Created_At/By`.
     *
     * @param  array<string, mixed>  $isi
     * @return array<string, mixed>
     */
    private function kolomBuat(string $tabel, array $isi, ?int $u): array
    {
        $jam = FormatTanggalHelper::getCurrentTime();

        if (str_contains($tabel, 'Cycle_Question_Package')) {
            return $isi + ['Snapshot_At' => $jam, 'Snapshot_By' => $u];
        }

        return $isi + ['Created_At' => $jam, 'Created_By' => $u];
    }

    /**
     * @param  array<string, mixed>  $isi
     * @return array<string, mixed>
     */
    private function kolomUbah(string $tabel, array $isi, ?int $u): array
    {
        return $isi + ['Updated_At' => FormatTanggalHelper::getCurrentTime(), 'Updated_By' => $u];
    }

    private function pastikanKodeBelumDipakai(string $tabel, string $kolom, string $kunci, string $kode, ?int $id): void
    {
        $q = DB::table($tabel)->where($kolom, $kode);

        if ($id !== null) {
            $q->where($kunci, '<>', $id);
        }

        if ($q->exists()) {
            throw new RuntimeException("Kode {$kode} sudah dipakai baris lain.");
        }
    }

    /**
     * Ambang nilai (KKM) satu kompetensi, skala 0–100.
     *
     * Kosong berarti BELUM DITETAPKAN, dan itu berbeda dari nol. Sumbu tanpa ambang
     * dilewati saat menggambar jaring KKM; sumbu berambang nol akan menariknya penyok ke
     * pusat dan terbaca sebagai standar yang sangat rendah. Karena itu string kosong
     * dipulangkan sebagai null, bukan dilewatkan ke `(float)` yang mengubahnya jadi 0.0.
     *
     * Skalanya mengikuti `PaResultCalculationService::normal()`, yang menormalkan setiap
     * butir menjadi `Skor / Skor_Maksimal * 100` sebelum dirata-ratakan.
     */
    /**
     * Satu perilaku per baris. Penanda butir yang terketik (-, *, •, "1.") dan baris kosong
     * dibuang, supaya layar yang memecahnya menjadi daftar tidak menampilkan butir ganda
     * atau butir hampa. Kosong seluruhnya = NULL (belum ditulis).
     */
    private function expectedBehaviour(mixed $teks): ?string
    {
        if ($teks === null || is_array($teks)) {
            return null;
        }

        $baris = array_values(array_filter(array_map(
            fn ($b) => trim((string) preg_replace('/^\s*(?:[-*•▪◦]|\d+[.)])\s*/u', '', $b)),
            preg_split('/\R/u', (string) $teks) ?: [],
        ), fn ($b) => $b !== ''));

        if ($baris === []) {
            return null;
        }

        $hasil = implode("\n", $baris);

        if (mb_strlen($hasil) > 2000) {
            throw new RuntimeException('Expected behaviour paling banyak 2.000 karakter.');
        }

        return $hasil;
    }

    private function ambangKompetensi(mixed $nilai): ?float
    {
        if ($nilai === null || $nilai === '' || $nilai === false) {
            return null;
        }

        if (! is_numeric($nilai)) {
            throw new RuntimeException('Ambang nilai kompetensi harus berupa angka 0 sampai 100.');
        }

        $angka = round((float) $nilai, 2);

        if ($angka < 0 || $angka > 100) {
            throw new RuntimeException(
                'Ambang nilai kompetensi berada di luar 0–100. Nilai kompetensi selalu '
                .'dinormalkan ke skala itu sebelum dirata-ratakan, sehingga ambang di luar '
                .'rentang tersebut tidak akan pernah tercapai atau tidak akan pernah gagal.'
            );
        }

        return $angka;
    }

    private function kode(mixed $nilai, string $label): string
    {
        $teks = strtoupper(trim((string) $nilai));

        if ($teks === '') {
            throw new RuntimeException("{$label} wajib diisi.");
        }

        if (! preg_match('/^[A-Z0-9_\-\.]+$/', $teks)) {
            throw new RuntimeException(
                "{$label} hanya boleh huruf, angka, garis bawah, tanda hubung, dan titik."
            );
        }

        return $teks;
    }

    private function teksWajib(mixed $nilai, string $label): string
    {
        $teks = trim((string) $nilai);

        if ($teks === '') {
            throw new RuntimeException("{$label} wajib diisi.");
        }

        return $teks;
    }

    private function alasanTertutup(string $master): string
    {
        return match ($master) {
            'kategori-penilai' => 'Kodenya menjadi kunci sambung di salinan siklus, penugasan, dan '
                .'jejak hasil yang sudah tersimpan. Kategori baru tanpa aturan di setiap level '
                .'tidak akan pernah muncul, dan menonaktifkannya memutus aturan siklus berjalan.',
            'kuadran-hav' => 'Matriksnya tiga kali tiga dan sudah penuh; setiap sel sudah punya '
                .'pemiliknya. Kotak kesepuluh tidak punya tempat untuk berdiri.',
            'bobot-pilar' => 'Satu level tepat satu bobot, dan level tanpa bobot pilar tidak bisa '
                .'dinilai sama sekali. Yang bisa dilakukan adalah mengubah angkanya.',
            default => '',
        };
    }

    /**
     * Menandai langkah PAKET_SOAL — DIDELEGASIKAN, bukan dihitung ulang di sini.
     *
     * Berkas ini dulu menghitungnya sendiri, dan hitungannya PER LEVEL: "level ini punya
     * paket atau tidak". Itu sudah salah sejak soal dibedakan per kategori penilai — level
     * dengan satu paket tampak lengkap padahal tiga kategori penilainya belum bersoal.
     *
     * Aturannya kini hanya ada di `PaHcLearnCatalogService::tandaiLangkah()`, yang
     * menghitung LUBANG SEL. Salinan terpisah di dua berkas berarti dua jawaban untuk satu
     * pertanyaan, dan cepat atau lambat hanya satu yang diperbaiki.
     *
     * Katalog diselesaikan saat dipakai: menyuntikkannya lewat konstruktor membuat berkas
     * ini bergantung pada rantai katalog hanya untuk satu penandaan.
     */
    private function tandaiLangkahPaketSoal(int $cycleId, ?int $olehUserId): void
    {
        app(PaHcLearnCatalogService::class)->tandaiLangkah($cycleId, $olehUserId);
    }
}
