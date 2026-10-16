<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sisi LEADER: memantau tim sendiri, dan melihat hasil anggota sebatas yang boleh.
 *
 * SUMBER "SIAPA ANGGOTA SAYA"
 *
 * `N_HRIS_Organization_Relationship` — tabel yang SAMA dengan yang dibaca mesin penugasan
 * ketika menentukan siapa berhak menilai siapa
 * (`PaAssignmentBuilderService::hangatkanCalon()`).
 *
 * Semula `HRIS_Karyawan_Team`, dan itu pilihan yang benar pada waktunya: per 2 September
 * 2026 tabel relasi masih berisi NOL baris, sehingga memakainya berarti setiap leader
 * membuka layar kosong. Alasan itu kedaluwarsa, dan pilihannya ikut diperbaiki 6
 * September 2026.
 *
 * Yang menuntut penggantian bukan jumlah barisnya, melainkan bahwa DUA TABEL MENJAWAB
 * SATU PERTANYAAN. Selama penugasan dibentuk dari relasi organisasi sementara halaman ini
 * membaca `HRIS_Karyawan_Team`, seorang atasan bisa ditugaskan menilai bawahannya namun
 * tetap disambut "Anda belum memiliki scope tim" di sini — dua jawaban berbeda untuk
 * pertanyaan yang sama, keduanya tanpa galat, dan tak satu pun bisa ditunjuk sebagai yang
 * keliru.
 *
 * DUA ARAH DIBACA, BUKAN SATU. Relasi ditulis berpasangan: "A ATASAN B" disertai
 * cerminnya "B BAWAHAN A". Membaca satu arah cukup SELAMA pasangannya selalu utuh — dan
 * itu berlaku untuk baris yang ditulis modul ini, tetapi tidak dijamin untuk baris yang
 * datang dari impor atau penyuntingan manual. Karena itu keduanya dibaca lalu disatukan.
 * Kehilangan satu anggota di sini berarti hasil orang itu tidak pernah terlihat
 * atasannya, dan tidak ada yang akan melaporkannya.
 *
 * WAKTU ACUANNYA HARI INI, bukan periode siklus. Yang dijawab halaman ini adalah "siapa
 * tim saya sekarang". Konsekuensinya disengaja: atasan yang baru pindah unit langsung
 * melihat tim barunya dan kehilangan tim lamanya, termasuk pada siklus yang sudah lewat.
 *
 * DUA BATAS YANG MEMBENTUK SELURUH BERKAS INI
 *
 * 1. **Angka hanya tampak setelah TERBIT.** Kemajuan pengisian boleh dilihat kapan saja —
 *    itu informasi operasional, dan justru gunanya memantau. Tetapi nilai, grade, dan
 *    kuadran hanya muncul untuk hasil `TERBIT`. Leader bukan admin; menampilkan angka
 *    yang belum diterbitkan meniadakan guna langkah penerbitan, persis seperti yang sudah
 *    dijaga di sisi peserta.
 *
 * 2. **Tidak pernah ada baris per penilai.** Leader melihat agregat dan rincian per
 *    KATEGORI penilai, tidak pernah "siapa memberi berapa". Dan rincian kategori pun
 *    disembunyikan bila pengisinya terlalu sedikit — lihat `MIN_PENILAI_UNTUK_TAMPIL`.
 */
class PaLeaderService
{
    /**
     * Berapa pengisi minimal sebelum satu kategori boleh ditampilkan angkanya.
     *
     * Angka ini soal aritmetika, bukan selera. Penilaian 360 hanya menghasilkan jawaban
     * jujur kalau penilai percaya ia tidak bisa dikenali, dan pada tim kecil rata-rata
     * sebuah kategori BISA membongkar orangnya:
     *
     * - 1 pengisi: nilai kategori ADALAH nilai orang itu. Tidak ada yang tersembunyi.
     * - 2 pengisi: rata-ratanya tidak menunjuk siapa pun — KECUALI bila leader sendiri
     *   salah satu pengisinya, dan itu justru lazim, karena leader biasanya atasan
     *   anggotanya. Ia tahu nilainya sendiri, maka nilai satunya lagi tinggal dihitung.
     * - 3 pengisi: sekalipun leader salah satunya, yang bisa ia turunkan hanya JUMLAH dua
     *   nilai sisanya, bukan nilai per orang.
     *
     * Karena itu tiga, bukan dua. Ini angka kebijakan: pemilik proses boleh menaikkannya,
     * tetapi menurunkannya di bawah tiga berarti menerima bahwa leader-yang-juga-penilai
     * dapat menyimpulkan nilai rekan tunggal anggotanya.
     */
    public const MIN_PENILAI_UNTUK_TAMPIL = 3;

    /**
     * Kategori yang tidak pernah disembunyikan meski pengisinya satu.
     *
     * Penilaian diri memang selalu berpengisi satu, dan orang itu adalah anggota yang
     * sedang dilihat — bukan pihak ketiga yang butuh dilindungi. Menyembunyikannya tidak
     * melindungi siapa pun dan justru membuang bahan percakapan yang paling berguna:
     * selisih antara penilaian diri dan penilaian orang lain.
     */
    private const KATEGORI_SELALU_TAMPAK = ['SELF'];

    public function __construct(
        private readonly PaParticipantRuleResolver $resolver,
    ) {}

    /**
     * L01 — daftar anggota tim pada satu siklus, berikut kemajuan dan penahanannya.
     *
     * @return array<string, mixed>
     */
    public function tim(int $userId, ?int $cycleId = null): array
    {
        $leader = $this->leader($userId);
        $anggota = $this->kodeAnggota($leader->Kode_Karyawan);

        if ($anggota === []) {
            return [
                'leader' => $this->identitas($leader),
                'cycle' => null,
                'daftar_cycle' => [],
                'anggota' => [],
                'ringkasan' => $this->ringkasanKosong(),
                'batas' => $this->batas(),
                'tanpa_tim' => true,
            ];
        }

        $daftarSiklus = $this->daftarSiklus($anggota);
        $siklus = $this->siklus($cycleId, $anggota);

        if ($siklus === null) {
            return [
                'leader' => $this->identitas($leader),
                'cycle' => null,
                'daftar_cycle' => $daftarSiklus,
                'anggota' => [],
                'ringkasan' => $this->ringkasanKosong(),
                'batas' => $this->batas(),
                'tanpa_tim' => false,
            ];
        }

        $baris = $this->barisAnggota((int) $siklus->Id_PA_Cycle, $anggota);
        $tren = $this->trenSebelumnya((int) $siklus->Id_PA_Cycle, $daftarSiklus, $anggota);
        $kemajuan = $this->kemajuanPenugasan(array_column($baris, 'participant_id'));

        $anggotaKeluar = [];
        $hasilTerlihat = [];

        foreach ($baris as $b) {
            $k = $kemajuan[$b['participant_id']] ?? ['total' => 0, 'selesai' => 0, 'terlambat' => 0];
            $terbit = $b['status_hasil'] === PaResultCalculationService::STATUS_TERBIT;

            /* SEJAK DDL-2026-130 ada dua pintu, dan leader cukup lewat salah satunya.
               `Published_Leader_At` membuka angka untuk leader saja; `TERBIT` membuka untuk
               karyawannya, dan yang sudah dibaca karyawan tidak masuk akal disembunyikan dari
               atasannya. Bentuk "atau" inilah yang membuat siklus lama tidak berubah sedikit
               pun tanpa satu baris DML. */
            $boleh = $terbit || $b['dirilis_ke_leader'];

            if ($boleh && $b['result_id'] !== null) {
                $hasilTerlihat[] = $b['result_id'];
            }

            $anggotaKeluar[] = [
                'participant_id' => $b['participant_id'],
                'kode_karyawan' => $b['kode_karyawan'],
                'nama' => $b['nama'],
                'nama_jabatan' => $b['nama_jabatan'],
                'status_peserta' => $b['status_peserta'],
                'status_hasil' => $b['status_hasil'],
                // Kemajuan pengisian boleh dilihat kapan pun; ini yang membuat memantau
                // ada gunanya sebelum ada satu angka pun.
                'penugasan_total' => $k['total'],
                'penugasan_selesai' => $k['selesai'],
                'penugasan_terlambat' => $k['terlambat'],
                'progres_persen' => $k['total'] === 0 ? 0 : (int) round($k['selesai'] / $k['total'] * 100),
                /* Angka HANYA sesudah terbit. `null` di sini bukan "belum dihitung",
                   melainkan "belum boleh dilihat" — dan keduanya sengaja tidak dibedakan
                   di sisi leader, karena membedakannya sudah membocorkan bahwa angkanya
                   sebenarnya sudah ada. */
                'nilai_akhir' => $boleh ? $this->angka($b['final_score']) : null,
                'nilai_kpi' => $boleh ? $this->angka($b['nilai_kpi']) : null,
                'nilai_kompetensi' => $boleh ? $this->angka($b['nilai_kompetensi']) : null,
                'kode_grade' => $boleh ? $b['kode_grade'] : null,
                'kode_kuadran' => $boleh ? $b['kode_kuadran'] : null,
                'terbit' => $terbit,

                /* Dibedakan dari `terbit` supaya layar leader bisa menjelaskan keadaan yang
                   kini mungkin: angkanya terlihat oleh leader, tetapi orangnya sendiri belum
                   diberi tahu. Leader perlu tahu itu sebelum membicarakannya. */
                'dirilis_ke_leader' => $b['dirilis_ke_leader'],
                /* Nilai orang ini di siklus sebelumnya — hanya bila hasil itu sudah boleh
                   dibaca leader. NULL berarti "tidak ada pembanding", bukan nol. */
                'nilai_sebelum' => $tren['per_anggota'][$b['kode_karyawan']]['nilai'] ?? null,
                'grade_sebelum' => $tren['per_anggota'][$b['kode_karyawan']]['grade'] ?? null,
                /* Sebab tertahan ditampilkan meski belum terbit. Ini bukan nilai, ini
                   pekerjaan yang menunggu seseorang — dan leader adalah orang yang paling
                   mungkin menindaklanjutinya. */
                'alasan_tertahan' => $b['status_hasil'] === PaResultCalculationService::STATUS_TERTAHAN
                    ? $b['alasan_tertahan']
                    : null,
            ];
        }

        return [
            'leader' => $this->identitas($leader),
            'cycle' => [
                'cycle_id' => (int) $siklus->Id_PA_Cycle,
                'kode_cycle' => $siklus->Kode_Cycle,
                'nama_cycle' => $siklus->Nama_Cycle,
                'status_cycle' => $siklus->Status_Cycle,
                'periode_dinilai_dari' => $siklus->Periode_Dinilai_Dari,
                'periode_dinilai_sampai' => $siklus->Periode_Dinilai_Sampai,
                'tahun' => $siklus->Tahun !== null ? (int) $siklus->Tahun : null,
            ],
            'daftar_cycle' => $daftarSiklus,
            'anggota' => $anggotaKeluar,
            'ringkasan' => $this->ringkasan($anggotaKeluar),
            'tren' => ['cycle' => $tren['cycle'], 'rata_rata_sebelum' => $tren['rata_rata']],
            'kompetensi_tim' => $this->kompetensiTim($hasilTerlihat),
            // Kamus sembilan kotak HAV, supaya mini 9-box di tab Ringkasan tidak menebak posisi dari kode.
            'kamus_kuadran' => DB::table('N_PA_Hav_Quadrant')->where('Flag_Aktif', 'Y')->orderBy('Nomor_Box')
                ->get(['Kode_Kuadran', 'Nama_Kuadran', 'Nomor_Box'])
                ->map(fn ($q) => ['kode' => $q->Kode_Kuadran, 'nama' => $q->Nama_Kuadran, 'nomor_box' => (int) $q->Nomor_Box])
                ->all(),
            'batas' => $this->batas(),
            'tanpa_tim' => false,
        ];
    }

    /**
     * Rata-rata tiap kompetensi berskor di tim — bahan "kekuatan & area pengembangan" di
     * tab Ringkasan. HANYA dari hasil yang boleh dibaca leader (terbit/dirilis), memakai
     * baris agregat lintas kategori, jadi tidak ada nilai per penilai yang ikut terbaca.
     *
     * @param  list<int>  $resultIds
     * @return list<array<string, mixed>>
     */
    private function kompetensiTim(array $resultIds): array
    {
        if ($resultIds === []) {
            return [];
        }

        /* Nilai tiap anggota per kompetensi — rincian di dialog detail kompetensi. Masih
           baris agregat dan masih hanya hasil yang terlihat, sama dengan rata-ratanya. */
        $perAnggota = DB::table('N_PA_Result_Competency as rc')
            ->join('N_PA_Result as r', 'r.Id_PA_Result', '=', 'rc.PA_Result_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'rc.PA_Competency_Id');
        $perAnggota = PaKompetensiSiklus::gabung($perAnggota, 'c', 'p.PA_Cycle_Id')
            ->whereIn('rc.PA_Result_Id', $resultIds)
            ->where('rc.Flag_Aktif', 'Y')
            ->whereNull('rc.PA_Rater_Category_Id')
            ->whereRaw(PaKompetensiSiklus::sql('Flag_Berskor', 'c')." = 'Y'")
            ->orderByDesc('rc.Nilai')
            ->get(['c.Kode_Competency', 'c.Id_PA_Competency', 'p.Id_PA_Participant', 'p.Nama_Karyawan_Saat_Cycle', 'rc.Nilai',
                'p.PA_Cycle_Id', 'p.ID_Level_Saat_Cycle', 'p.Nama_Level_Saat_Cycle'])
            ->groupBy('Kode_Competency');

        /* PERILAKU PER LEVEL (DDL-2026-175). Anggota tim bisa beda level, dan tiap level dituntut
           tingkat berbeda — satu Expected Behaviour untuk seluruh tim akan salah bagi sebagian
           orang. Dikirim per level yang ada di tim; bila timnya satu level saja, perilaku umum
           ikut ditimpa perilaku tingkat level itu. */
        $matriks = app(PaKompetensiMatriksService::class);
        $perilakuLevel = [];
        $perilakuPerLevel = function ($kode) use ($perAnggota, $matriks, &$perilakuLevel) {
            return collect($perAnggota->get($kode, []))
                ->groupBy(fn ($x) => $x->PA_Cycle_Id.'|'.($x->ID_Level_Saat_Cycle ?? 0))
                ->map(function ($g) use ($matriks, &$perilakuLevel) {
                    $x = $g->first();
                    $kunci = $x->PA_Cycle_Id.'|'.($x->ID_Level_Saat_Cycle ?? 0);
                    $perilakuLevel[$kunci] ??= $matriks->perilakuDituntut((int) $x->PA_Cycle_Id,
                        $x->ID_Level_Saat_Cycle === null ? null : (int) $x->ID_Level_Saat_Cycle);
                    $p = $perilakuLevel[$kunci][(int) $x->Id_PA_Competency] ?? null;

                    return [
                        'nama_level' => $x->Nama_Level_Saat_Cycle,
                        'jumlah_orang' => $g->count(),
                        'tingkat' => $p['tingkat'] ?? null,
                        'nama_tingkat' => $p['nama_tingkat'] ?? null,
                        'expected_behaviour' => $p['expected_behaviour'] ?? null,
                    ];
                })
                ->filter(fn ($l) => $l['tingkat'] !== null)
                ->sortBy('nama_level')
                ->values()
                ->all();
        };

        /* Satu tim = satu siklus, jadi salinan per siklus memberi satu KKM per kompetensi. */
        $ambang = PaKompetensiSiklus::sql('Nilai_Ambang', 'c');

        return PaKompetensiSiklus::gabung(DB::table('N_PA_Result_Competency as rc')
            ->join('N_PA_Result as hr', 'hr.Id_PA_Result', '=', 'rc.PA_Result_Id')
            ->join('N_PA_Participant as hp', 'hp.Id_PA_Participant', '=', 'hr.PA_Participant_Id')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'rc.PA_Competency_Id'), 'c', 'hp.PA_Cycle_Id')
            ->leftJoin('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
            ->whereIn('rc.PA_Result_Id', $resultIds)
            ->where('rc.Flag_Aktif', 'Y')
            ->whereNull('rc.PA_Rater_Category_Id')
            ->whereRaw(PaKompetensiSiklus::sql('Flag_Berskor', 'c')." = 'Y'")
            ->groupBy('c.Id_PA_Competency', 'c.Kode_Competency', 'c.Nama_Competency', 'c.Deskripsi',
                'c.Expected_Behaviour', DB::raw($ambang), 't.Nama_Type')
            ->orderByRaw('AVG(CAST(rc.Nilai AS float)) DESC')
            ->get([
                'c.Kode_Competency', 'c.Nama_Competency', 'c.Deskripsi', 'c.Expected_Behaviour',
                DB::raw("{$ambang} AS Nilai_Ambang"), 't.Nama_Type',
                DB::raw('AVG(CAST(rc.Nilai AS float)) AS rata_rata'),
                DB::raw('COUNT(*) AS jumlah_orang'),
            ])
            ->map(fn ($r) => [
                'kode_kompetensi' => $r->Kode_Competency,
                'nama_kompetensi' => $r->Nama_Competency,
                'nama_type' => $r->Nama_Type,
                'deskripsi' => $r->Deskripsi,
                // EB umum kompetensi tidak dipakai lagi (10 Okt 2026); perilaku ada per level di bawah.
                'expected_behaviour' => null,
                'perilaku_per_level' => $perilakuPerLevel($r->Kode_Competency),
                'rata_rata' => round((float) $r->rata_rata, 2),
                'jumlah_orang' => (int) $r->jumlah_orang,
                // NULL = KKM belum ditetapkan, bukan nol.
                'nilai_ambang' => $r->Nilai_Ambang === null ? null : (float) $r->Nilai_Ambang,
                'anggota' => collect($perAnggota->get($r->Kode_Competency, []))
                    ->map(fn ($x) => [
                        'participant_id' => (int) $x->Id_PA_Participant,
                        'nama' => $x->Nama_Karyawan_Saat_Cycle,
                        'nama_level' => $x->Nama_Level_Saat_Cycle,
                        'nilai' => round((float) $x->Nilai, 2),
                    ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Pembanding tren: siklus aktif tepat sebelum siklus yang sedang dibuka (urutan sama
     * dengan pemilih periode), dibaca dengan aturan keterlihatan yang SAMA — hanya hasil
     * yang sudah terbit atau dirilis ke leader. Tanpa siklus sebelumnya, semuanya null.
     *
     * @param  list<array<string, mixed>>  $daftarSiklus
     * @param  list<string>  $anggota
     * @return array{cycle: ?array, rata_rata: ?float, per_anggota: array<string, array{nilai: float, grade: ?string}>}
     */
    private function trenSebelumnya(int $cycleId, array $daftarSiklus, array $anggota): array
    {
        $kosong = ['cycle' => null, 'rata_rata' => null, 'per_anggota' => []];
        $ids = array_column($daftarSiklus, 'cycle_id');
        $posisi = array_search($cycleId, $ids, true);

        if ($posisi === false || ! isset($daftarSiklus[$posisi + 1])) {
            return $kosong;
        }

        $sebelum = $daftarSiklus[$posisi + 1];
        $per = [];

        foreach ($this->barisAnggota((int) $sebelum['cycle_id'], $anggota) as $b) {
            $boleh = $b['status_hasil'] === PaResultCalculationService::STATUS_TERBIT || $b['dirilis_ke_leader'];
            $nilai = $this->angka($b['final_score']);

            if ($boleh && $nilai !== null) {
                $per[$b['kode_karyawan']] = ['nilai' => $nilai, 'grade' => $b['kode_grade']];
            }
        }

        return [
            'cycle' => [
                'cycle_id' => $sebelum['cycle_id'],
                'kode_cycle' => $sebelum['kode_cycle'],
                'nama_cycle' => $sebelum['nama_cycle'],
            ],
            'rata_rata' => $per === [] ? null : round(array_sum(array_column($per, 'nilai')) / count($per), 2),
            'per_anggota' => $per,
        ];
    }

    /**
     * L02 — hasil satu anggota tim, sebatas yang boleh dilihat leader.
     *
     * @return array<string, mixed>
     */
    public function detailAnggota(int $userId, int $participantId): array
    {
        $leader = $this->leader($userId);

        $peserta = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('p.Id_PA_Participant', $participantId)
            ->first([
                'p.Id_PA_Participant', 'p.PA_Cycle_Id', 'p.Kode_Karyawan',
                'p.Nama_Karyawan_Saat_Cycle', 'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
                'p.Status_Peserta',
                'c.Nama_Cycle', 'c.Kode_Cycle', 'c.Tahun',
            ]);

        if ($peserta === null) {
            throw new RuntimeException('Peserta tidak ditemukan.');
        }

        if ($peserta->Status_Peserta === 'DIKECUALIKAN') {
            throw new RuntimeException('Peserta ini dikecualikan dari siklus penilaian.');
        }

        /* Otorisasi berbasis KEANGGOTAAN, bukan berbasis siklus. Seorang leader boleh
           melihat anggotanya di siklus mana pun, tetapi tidak boleh melihat orang lain di
           siklus yang sama. Memeriksa siklus saja akan meloloskan seluruh peserta siklus
           itu. */
        if (! in_array((string) $peserta->Kode_Karyawan, $this->kodeAnggota($leader->Kode_Karyawan), true)) {
            throw new RuntimeException(
                'Karyawan ini bukan anggota tim Anda, sehingga hasil penilaiannya tidak '
                .'bisa dibuka dari halaman ini.'
            );
        }

        $hasil = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->first([
                'Id_PA_Result', 'Status_Hasil', 'Nilai_KPI', 'Nilai_Kompetensi', 'Final_Score', 'Kode_Grade_Final',
                'Bobot_KPI_Terpakai', 'Bobot_Kompetensi_Terpakai',
                'Alasan_Tertahan', 'Published_At', 'Published_Leader_At', 'Calculation_Version',
                // Penandanya saja, bukan alasannya — sama seperti sisi peserta.
                'Flag_Dikalibrasi',
            ]);

        $terbit = $hasil !== null
            && $hasil->Status_Hasil === PaResultCalculationService::STATUS_TERBIT;
        $dirilis = $hasil !== null && $hasil->Published_Leader_At !== null;
        // Sama seperti daftar tim: salah satu pintu sudah cukup bagi leader.
        $boleh = $terbit || $dirilis;

        $dasar = [
            'participant_id' => (int) $peserta->Id_PA_Participant,
            'cycle_id' => (int) $peserta->PA_Cycle_Id,
            'kode_karyawan' => $peserta->Kode_Karyawan,
            'nama' => $peserta->Nama_Karyawan_Saat_Cycle,
            'nama_jabatan' => $peserta->Nama_Jabatan_Saat_Cycle,
            'nama_level' => $peserta->Nama_Level_Saat_Cycle,
            'nama_cycle' => $peserta->Nama_Cycle,
            'kode_cycle' => $peserta->Kode_Cycle,
            'tahun' => $peserta->Tahun !== null ? (int) $peserta->Tahun : null,
            'status_peserta' => $peserta->Status_Peserta,
            'status_hasil' => $hasil?->Status_Hasil ?? PaResultCalculationService::STATUS_BELUM,
            'terbit' => $terbit,
            // Terlihat leader, tetapi orangnya sendiri belum tentu sudah diberi tahu.
            'dirilis_ke_leader' => $dirilis,
            'batas' => $this->batas(),
        ];

        if (! $boleh) {
            /* Belum dibuka lewat pintu mana pun: yang keluar hanya kemajuan dan sebab tertahan. Tidak ada satu
               pun angka — termasuk radar kompetensi, yang sama saja membocorkan hasilnya
               sepotong-sepotong. */
            $kemajuan = $this->kemajuanPenugasan([$participantId])[$participantId]
                ?? ['total' => 0, 'selesai' => 0, 'terlambat' => 0];

            return $dasar + [
                'penugasan_total' => $kemajuan['total'],
                'penugasan_selesai' => $kemajuan['selesai'],
                'penugasan_terlambat' => $kemajuan['terlambat'],
                'progres_persen' => $kemajuan['total'] === 0
                    ? 0
                    : (int) round($kemajuan['selesai'] / $kemajuan['total'] * 100),
                'alasan_tertahan' => $hasil?->Status_Hasil === PaResultCalculationService::STATUS_TERTAHAN
                    ? $hasil->Alasan_Tertahan
                    : null,
                'nilai_kpi' => null,
                'nilai_kompetensi' => null,
                'nilai_akhir' => null,
                'kode_grade' => null,
                'nama_grade' => null,
                'bobot_kpi' => null,
                'bobot_kompetensi' => null,
                'kode_kuadran' => null,
                'kompetensi' => [],
                'catatan' => [],
                'kategori' => [],
            ];
        }

        $resultId = (int) $hasil->Id_PA_Result;
        $kuadran = $this->kuadran($resultId);

        return $dasar + [
            'nilai_kpi' => $this->angka($hasil->Nilai_KPI),
            'nilai_kompetensi' => $this->angka($hasil->Nilai_Kompetensi),
            'nilai_akhir' => $this->angka($hasil->Final_Score),
            'kode_grade' => $hasil->Kode_Grade_Final,
            // Nama dari pita kurva beku / pita siklus tempat grade itu lahir — bukan karangan layar.
            'nama_grade' => app(PaPesertaService::class)->namaGrade($participantId, $hasil->Kode_Grade_Final),
            // Bobot yang SUNGGUH dipakai menghitung hasil ini (berbeda per level dan siklus).
            'bobot_kpi' => $this->angka($hasil->Bobot_KPI_Terpakai),
            'bobot_kompetensi' => $this->angka($hasil->Bobot_Kompetensi_Terpakai),
            'kode_kuadran' => $kuadran,
            /* Tanpa ini leader melihat nilai akhir yang tidak cocok dengan gradenya
               sendiri — kalibrasi menggeser grade tanpa menyentuh nilai — dan tidak punya
               apa pun untuk menjelaskannya kepada anggotanya. Justru leader yang paling
               mungkin ditanyai soal itu. Alasan kalibrasinya tetap tidak ikut. */
            'dikalibrasi' => $hasil->Flag_Dikalibrasi === 'Y',
            'published_at' => $hasil->Published_At,
            'kompetensi' => $this->radar($resultId),
            'catatan' => $this->catatan($resultId),
            'kategori' => $this->kategoriTersaring($resultId, $participantId),
            'alasan_tertahan' => null,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Anggota dan siklus                                                  *
     * ------------------------------------------------------------------ */

    private function leader(int $userId): object
    {
        $k = DB::table('Karyawan')
            ->where('UserID_Web', $userId)
            ->where('Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        if ($k === null) {
            throw new RuntimeException(
                'Akun Anda belum tersambung ke data karyawan yang aktif, sehingga tim Anda '
                .'tidak bisa ditemukan. Hubungi HC untuk menyambungkannya.'
            );
        }

        return $k;
    }

    /**
     * Kode karyawan anggota tim seorang leader, dari relasi organisasi.
     *
     * Penyaringnya disamakan dengan `PaAssignmentBuilderService::hangatkanCalon()` supaya
     * kedua sisi tidak bisa berbeda pendapat: baris aktif, belum dibatalkan, masa
     * berlakunya memuat tanggal acuan, dan orangnya masih karyawan aktif.
     *
     * DUA KUERI, BUKAN SATU BER-`OR`. Kedua arah menanyakan kolom yang berbeda sebagai
     * "lawan bicara", dan menyatukannya dalam satu kueri menuntut `CASE` di dalam `JOIN`
     * — bentuk yang benar tetapi tidak terbaca, dan yang paling mudah dirusak orang
     * berikutnya. Dua kueri kecil terhadap kolom ber-indeks lebih murah daripada satu
     * kueri yang tidak ada yang berani menyentuh.
     *
     * Jenis relasinya dicari lewat `Kode_Relasi`, bukan ditulis sebagai angka: idnya
     * berbeda antara dev dan production, dan angka yang keliru menghasilkan daftar orang
     * yang salah tanpa satu pun galat.
     *
     * Leader dikeluarkan dari daftarnya sendiri. Sebagian data memasangkan orang dengan
     * dirinya sendiri, dan tanpa ini leader muncul sebagai anggota timnya sendiri.
     *
     * @return list<string>
     */
    private function kodeAnggota(string $kodeLeader): array
    {
        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')
            ->whereIn('Kode_Relasi', ['ATASAN', 'BAWAHAN'])
            ->pluck('Id_Organization_Relationship_Type', 'Kode_Relasi');

        if ($tipe->isEmpty()) {
            throw new RuntimeException(
                'Master jenis relasi organisasi tidak memuat ATASAN maupun BAWAHAN, '
                .'sehingga tim Anda tidak bisa ditentukan. Lengkapi master relasi '
                .'organisasi lebih dulu.'
            );
        }

        $acuan = now()->format('Y-m-d');

        /* Satu penyaring yang dipakai dua kali, supaya syarat keberlakuannya tidak bisa
           menyimpang antara arah yang satu dan arah yang lain. */
        $berlaku = function ($kueri) use ($acuan) {
            return $kueri
                ->join('Karyawan as k', function ($j) {
                    $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Lawan')
                        ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Lawan');
                })
                ->where('r.Flag_Aktif', 'Y')
                ->whereNull('r.Cancelled_At')
                ->where('r.Berlaku_Dari', '<=', $acuan)
                ->where(function ($q) use ($acuan) {
                    $q->whereNull('r.Berlaku_Sampai')->orWhere('r.Berlaku_Sampai', '>=', $acuan);
                })
                ->where('k.Aktif', 'Y');
        };

        $kode = collect();

        // Arah 1: "saya punya BAWAHAN X" — lawan bicaranya kolom Terkait.
        if (isset($tipe['BAWAHAN'])) {
            $kode = $kode->merge($berlaku(
                DB::query()->fromSub(
                    DB::table('N_HRIS_Organization_Relationship')
                        ->where('Kode_Karyawan_Subjek', $kodeLeader)
                        ->where('Id_Organization_Relationship_Type', $tipe['BAWAHAN'])
                        ->select([
                            'Flag_Aktif', 'Cancelled_At', 'Berlaku_Dari', 'Berlaku_Sampai',
                            'Kode_Perusahaan_Terkait as Kode_Perusahaan_Lawan',
                            'Kode_Karyawan_Terkait as Kode_Karyawan_Lawan',
                        ]),
                    'r',
                )
            )->pluck('k.Kode_Karyawan'));
        }

        // Arah 2: cerminnya, "X punya ATASAN saya" — lawan bicaranya kolom Subjek.
        if (isset($tipe['ATASAN'])) {
            $kode = $kode->merge($berlaku(
                DB::query()->fromSub(
                    DB::table('N_HRIS_Organization_Relationship')
                        ->where('Kode_Karyawan_Terkait', $kodeLeader)
                        ->where('Id_Organization_Relationship_Type', $tipe['ATASAN'])
                        ->select([
                            'Flag_Aktif', 'Cancelled_At', 'Berlaku_Dari', 'Berlaku_Sampai',
                            'Kode_Perusahaan_Subjek as Kode_Perusahaan_Lawan',
                            'Kode_Karyawan_Subjek as Kode_Karyawan_Lawan',
                        ]),
                    'r',
                )
            )->pluck('k.Kode_Karyawan'));
        }

        return $kode
            ->map(fn ($k) => (string) $k)
            ->reject(fn (string $k) => $k === $kodeLeader)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Siklus yang dilihat: yang diminta, atau yang paling mutakhir yang memuat tim ini.
     *
     * @param  list<string>  $anggota
     */
    private function siklus(?int $cycleId, array $anggota): ?object
    {
        // Siklus yang sudah dihapus (soft delete) tidak boleh dipilih maupun ditebak.
        $kueri = DB::table('N_PA_Cycle as c')->where('c.Flag_Aktif', 'Y');

        if ($cycleId !== null) {
            $kueri->where('c.Id_PA_Cycle', $cycleId);
        } else {
            /* Tanpa siklus yang diminta, yang dipilih adalah siklus terbaru yang benar-benar
               memuat anggota tim ini — bukan sekadar siklus terbaru. Siklus terbaru yang
               tidak memuat seorang pun anggotanya akan menyajikan layar kosong yang
               terbaca seperti kesalahan. */
            $kueri->whereExists(function ($q) use ($anggota) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Participant as p')
                    ->whereColumn('p.PA_Cycle_Id', 'c.Id_PA_Cycle')
                    ->whereIn('p.Kode_Karyawan', $anggota)
                    ->where('p.Status_Peserta', '!=', 'DIKECUALIKAN');
            });
        }

        // Terbaru = periode dinilai yang berakhir paling akhir (sama dengan PA Saya).
        return $kueri
            ->orderByRaw(str_replace('k.', 'c.', PaPesertaService::URUTAN_PERIODE).' DESC')
            ->orderByDesc('c.Id_PA_Cycle')
            ->first([
                'c.Id_PA_Cycle', 'c.Kode_Cycle', 'c.Nama_Cycle', 'c.Status_Cycle',
                'c.Periode_Dinilai_Dari', 'c.Periode_Dinilai_Sampai', 'c.Tahun',
            ]);
    }

    /**
     * Seluruh siklus yang memuat anggota tim leader ini.
     *
     * @param  list<string>  $anggota
     * @return list<array<string, mixed>>
     */
    private function daftarSiklus(array $anggota): array
    {
        if ($anggota === []) {
            return [];
        }

        return DB::table('N_PA_Cycle as c')
            ->where('c.Flag_Aktif', 'Y')
            ->whereExists(function ($q) use ($anggota) {
                $q->select(DB::raw(1))
                    ->from('N_PA_Participant as p')
                    ->whereColumn('p.PA_Cycle_Id', 'c.Id_PA_Cycle')
                    ->whereIn('p.Kode_Karyawan', $anggota)
                    ->where('p.Status_Peserta', '!=', 'DIKECUALIKAN');
            })
            /* Urutan PERIODE, bukan tahun lalu id: `trenSebelumnya()` mengambil elemen
               berikutnya sebagai "siklus sebelumnya", dan siklus Jan–Jun yang dibuat lebih
               dulu dari siklus Jan–Mar akan menjadi pembanding yang keliru. */
            ->orderByRaw(str_replace('k.', 'c.', PaPesertaService::URUTAN_PERIODE).' DESC')
            ->orderByDesc('c.Id_PA_Cycle')
            ->get([
                'c.Id_PA_Cycle', 'c.Kode_Cycle', 'c.Nama_Cycle', 'c.Status_Cycle',
                'c.Periode_Dinilai_Dari', 'c.Periode_Dinilai_Sampai', 'c.Tahun',
            ])
            ->map(fn ($c) => [
                'cycle_id' => (int) $c->Id_PA_Cycle,
                'kode_cycle' => $c->Kode_Cycle,
                'nama_cycle' => $c->Nama_Cycle,
                'status_cycle' => $c->Status_Cycle,
                'periode_dinilai_dari' => $c->Periode_Dinilai_Dari,
                'periode_dinilai_sampai' => $c->Periode_Dinilai_Sampai,
                'tahun' => $c->Tahun !== null ? (int) $c->Tahun : null,
            ])
            ->all();
    }

    /**
     * Baris anggota tim pada satu siklus. SENGAJA TANPA PEMBATASAN JUMLAH.
     *
     * Batasnya berbentuk data, bukan kode: tim terbesar di `HRIS_Karyawan_Team` berisi
     * 57 orang (diperiksa 2 September 2026), jadi memasang halaman di sini hanya
     * merumitkan layar tanpa menghemat apa pun. Ditulis di sini supaya pembaca berikutnya
     * tidak perlu menurunkannya sendiri — dan supaya jelas kapan ini perlu ditinjau:
     * bila suatu saat ada tim berisi ratusan orang, atau bila `whereIn` di bawah mendekati
     * batas 2.100 parameter SQL Server, halaman menjadi wajib.
     *
     * @param  list<string>  $anggota
     * @return list<array<string, mixed>>
     */
    private function barisAnggota(int $cycleId, array $anggota): array
    {
        return DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', function ($j) {
                $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')
                    ->where('r.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->whereIn('p.Kode_Karyawan', $anggota)
            ->where('p.Status_Peserta', '!=', 'DIKECUALIKAN')
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Jabatan_Saat_Cycle', 'p.Status_Peserta',
                'r.Id_PA_Result', 'r.Status_Hasil', 'r.Final_Score', 'r.Kode_Grade_Final', 'r.Alasan_Tertahan',
                'r.Nilai_KPI', 'r.Nilai_Kompetensi',
                'r.Published_Leader_At', 'q.Kode_Kuadran',
            ])
            ->map(fn ($r) => [
                'participant_id' => (int) $r->Id_PA_Participant,
                'kode_karyawan' => (string) $r->Kode_Karyawan,
                'nama' => $r->Nama_Karyawan_Saat_Cycle,
                'nama_jabatan' => $r->Nama_Jabatan_Saat_Cycle,
                'status_peserta' => $r->Status_Peserta,
                'status_hasil' => $r->Status_Hasil ?? PaResultCalculationService::STATUS_BELUM,
                'result_id' => $r->Id_PA_Result === null ? null : (int) $r->Id_PA_Result,
                'final_score' => $r->Final_Score,
                'nilai_kpi' => $r->Nilai_KPI,
                'nilai_kompetensi' => $r->Nilai_Kompetensi,
                'kode_grade' => $r->Kode_Grade_Final,
                'kode_kuadran' => $r->Kode_Kuadran,
                'alasan_tertahan' => $r->Alasan_Tertahan,
                'dirilis_ke_leader' => $r->Published_Leader_At !== null,
            ])
            ->all();
    }

    /**
     * Kemajuan pengisian per peserta, satu kueri untuk seluruh tim.
     *
     * @param  list<int>  $participantIds
     * @return array<int, array{total:int, selesai:int, terlambat:int}>
     */
    private function kemajuanPenugasan(array $participantIds): array
    {
        if ($participantIds === []) {
            return [];
        }

        $peta = [];

        /* Satu kueri agregat untuk seluruh tim, bukan satu kueri per anggota. Tim
           terbesar di data sekarang berisi belasan orang, jadi bedanya belum terasa —
           tetapi pola per-anggota inilah yang sudah dua kali menjadi sebab lambat di
           repo ini, dan memperbaikinya sesudah terlanjur dipakai selalu lebih mahal. */
        $baris = DB::table('N_PA_Assignment')
            ->whereIn('PA_Participant_Id', $participantIds)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Participant_Id')
            ->selectRaw(
                'PA_Participant_Id as pid, COUNT(*) as total, '
                ."SUM(CASE WHEN Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) as selesai, "
                /* `Due_At` bertipe DATE, jadi tenggat berarti SAMPAI AKHIR hari itu.
                   Membandingkannya dengan CAST(GETDATE() AS DATE) membuat orang yang
                   tenggatnya HARI INI tidak terhitung terlambat sejak tengah malam. */
                // TERKUMPUL, MENUNGGU_VERIFIKASI, dan DIBATALKAN dikecualikan: sumbernya
                // PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT, bukan diketik ulang di sini.
                .'SUM(CASE WHEN Status_Penugasan NOT IN ('.PaStatusPenugasan::sqlDaftar(PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT).') AND Due_At IS NOT NULL '
                .'AND Due_At < CAST(GETDATE() AS DATE) THEN 1 ELSE 0 END) as terlambat'
            )
            ->get();

        foreach ($baris as $b) {
            $peta[(int) $b->pid] = [
                'total' => (int) $b->total,
                'selesai' => (int) $b->selesai,
                'terlambat' => (int) $b->terlambat,
            ];
        }

        return $peta;
    }

    /* ------------------------------------------------------------------ *
     * Isi hasil yang boleh dilihat                                        *
     * ------------------------------------------------------------------ */

    private function kuadran(int $resultId): ?string
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->where('r.Id_PA_Result', $resultId)
            ->value('q.Kode_Kuadran');
    }

    /**
     * Radar kompetensi — hanya baris AGREGAT lintas kategori.
     *
     * Baris per kategori sengaja dibuang. Ia tampak tak berbahaya, tetapi "Integritas
     * menurut Bawahan = 60" pada kategori berpengisi satu adalah nilai satu orang yang
     * diberi nama lain.
     *
     * @return list<array<string, mixed>>
     */
    private function radar(int $resultId): array
    {
        return PaKompetensiSiklus::gabung(DB::table('N_PA_Result_Competency as rc')
            ->join('N_PA_Result as hr', 'hr.Id_PA_Result', '=', 'rc.PA_Result_Id')
            ->join('N_PA_Participant as hp', 'hp.Id_PA_Participant', '=', 'hr.PA_Participant_Id')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'rc.PA_Competency_Id'), 'c', 'hp.PA_Cycle_Id')
            ->leftJoin('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
            ->where('rc.PA_Result_Id', $resultId)
            ->where('rc.Flag_Aktif', 'Y')
            ->whereNull('rc.PA_Rater_Category_Id')
            // Urutan tampil, bukan urutan id — sama dengan §2.10, supaya radar yang sama
            // tidak tersusun berbeda di layar leader dan layar admin.
            ->orderBy('t.Urutan_Tampil')
            ->orderBy('c.Urutan_Tampil')
            ->get([
                'c.Kode_Competency', 'c.Nama_Competency', 'c.Deskripsi', 'c.Expected_Behaviour',
                't.Kode_Type', 't.Nama_Type',
                'rc.Nilai', 'rc.Jumlah_Penilai',
                PaKompetensiSiklus::kolom('Nilai_Ambang', 'c'), PaKompetensiSiklus::kolom('Flag_Radar', 'c'),
            ])
            ->map(fn ($r) => [
                // Nama kolomnya `Kode_Competency` (Inggris), bukan `Kode_Kompetensi`.
                'kode_kompetensi' => $r->Kode_Competency,
                'nama_kompetensi' => $r->Nama_Competency,
                'deskripsi' => $r->Deskripsi,
                'expected_behaviour' => $r->Expected_Behaviour,
                'kode_type' => $r->Kode_Type,
                'nama_type' => $r->Nama_Type,
                'nilai' => $this->angka($r->Nilai),
                'jumlah_penilai' => (int) $r->Jumlah_Penilai,
                /* Kosong berarti ambangnya BELUM DITETAPKAN, bukan nol. `angka()` akan
                   mengubah null menjadi 0.0 dan itu terbaca sebagai standar yang sangat
                   rendah, jadi null di sini sengaja dipertahankan apa adanya. */
                'nilai_ambang' => $r->Nilai_Ambang === null ? null : (float) $r->Nilai_Ambang,
                'flag_radar' => $r->Flag_Radar,
            ])
            // Expected Behaviour sesuai tingkat yang dituntut untuk level orang ini (DDL-2026-175).
            ->pipe(fn ($rows) => app(PaKompetensiMatriksService::class)->terapkanPerilakuPerKode(
                $rows, ...app(PaKompetensiMatriksService::class)->konteksHasil($resultId), kunciKode: 'kode_kompetensi', kunciPerilaku: 'expected_behaviour'));
    }

    /**
     * Kesimpulan kualitatif resmi per pertanyaan — TANPA observasi mentah penilai.
     *
     * Sampai 8 Oktober 2026 berkas ini mengirim seluruh catatan, termasuk tulisan tiap penilai
     * BESERTA namanya (`Nama_Penilai_Saat_Cycle`); layar hanya menyembunyikannya, tetapi
     * isinya terbaca di peramban leader — "siapa menulis apa", persis yang dilarang batas
     * `tanpa_nilai_per_penilai` di berkas ini. Sumbernya kini SATU dengan sisi peserta.
     *
     * @return list<array<string, mixed>>
     */
    private function catatan(int $resultId): array
    {
        return app(PaPesertaService::class)->kesimpulanKualitatif($resultId);
    }

    /**
     * Rincian per kategori penilai, dengan yang berpengisi terlalu sedikit disembunyikan.
     *
     * Barisnya TETAP DIKEMBALIKAN meski disembunyikan, hanya nilainya yang dikosongkan
     * dan `disembunyikan` dinyalakan berikut sebabnya. Menghilangkan barisnya sama sekali
     * akan membuat layar diam-diam kehilangan satu kategori, dan leader tidak akan pernah
     * tahu bahwa ada bagian penilaian yang memang ada tetapi tidak boleh ia lihat —
     * ketiadaan yang tak dijelaskan lebih membingungkan daripada penolakan yang jujur.
     *
     * @return list<array<string, mixed>>
     */
    private function kategoriTersaring(int $resultId, int $participantId): array
    {
        $ambang = $this->ambangPerKategori($participantId);

        return DB::table('N_PA_Result_Rater_Category as rc')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'rc.PA_Rater_Category_Id')
            ->where('rc.PA_Result_Id', $resultId)
            ->where('rc.Flag_Aktif', 'Y')
            ->orderBy('c.Id_PA_Rater_Category')
            ->get([
                'c.Kode_Kategori', 'c.Nama_Kategori', 'rc.Nilai',
                'rc.Jumlah_Rater_Diharapkan', 'rc.Jumlah_Rater_Mengisi', 'rc.Bobot_Terpakai',
            ])
            ->map(function ($r) use ($ambang) {
                $kode = (string) $r->Kode_Kategori;
                $mengisi = (int) $r->Jumlah_Rater_Mengisi;
                $batasKategori = $ambang[$kode] ?? self::MIN_PENILAI_UNTUK_TAMPIL;

                $selalu = in_array($kode, self::KATEGORI_SELALU_TAMPAK, true);
                $sembunyi = ! $selalu && $mengisi < $batasKategori;

                return [
                    'kode_kategori' => $kode,
                    'nama_kategori' => $r->Nama_Kategori,
                    'jumlah_penilai_diharapkan' => (int) $r->Jumlah_Rater_Diharapkan,
                    'jumlah_penilai_mengisi' => $mengisi,
                    'bobot_terpakai' => $this->angka($r->Bobot_Terpakai),
                    'nilai' => $sembunyi ? null : $this->angka($r->Nilai),
                    'disembunyikan' => $sembunyi,
                    'alasan_disembunyikan' => $sembunyi
                        ? sprintf(
                            'Hanya %d penilai mengisi kategori ini, di bawah %d yang '
                            .'dibutuhkan agar nilainya tidak menunjuk orang tertentu.',
                            $mengisi, $batasKategori,
                        )
                        : null,
                ];
            })
            ->all();
    }

    /**
     * Ambang tampil per kategori bagi satu peserta.
     *
     * Diambil lewat `PaParticipantRuleResolver` — satu-satunya pintu yang tahu apakah
     * orang ini punya penyimpangan aturan penilai (K32). Membaca tabel salinan siklus
     * langsung akan benar untuk mayoritas dan diam-diam salah untuk yang dikecualikan.
     *
     * Ambang tampilnya adalah yang LEBIH BESAR antara ambang perhitungan aturan dan
     * `MIN_PENILAI_UNTUK_TAMPIL`. Ambang perhitungan menjawab "cukupkah untuk dihitung",
     * pertanyaan yang sama sekali lain dari "cukupkah untuk ditampilkan tanpa membongkar
     * orangnya" — dan aturan yang membolehkan satu pengisi sudah cukup untuk menghitung.
     *
     * @return array<string, int>
     */
    private function ambangPerKategori(int $participantId): array
    {
        $peta = [];

        foreach ($this->resolver->raterRules($participantId) as $r) {
            $kode = (string) ($r['kode_kategori'] ?? '');

            if ($kode === '') {
                continue;
            }

            $peta[$kode] = max(
                (int) ($r['min_rater_selesai'] ?? 0),
                self::MIN_PENILAI_UNTUK_TAMPIL,
            );
        }

        return $peta;
    }

    /* ------------------------------------------------------------------ *
     * Serba-serbi                                                         *
     * ------------------------------------------------------------------ */

    /** @param list<array<string, mixed>> $anggota */
    private function ringkasan(array $anggota): array
    {
        $terbit = 0;
        $tertahan = 0;
        $terlambat = 0;
        $selesaiPenuh = 0;

        foreach ($anggota as $a) {
            if ($a['terbit']) {
                $terbit++;
            }

            if ($a['status_hasil'] === PaResultCalculationService::STATUS_TERTAHAN) {
                $tertahan++;
            }

            if ($a['penugasan_terlambat'] > 0) {
                $terlambat++;
            }

            if ($a['penugasan_total'] > 0 && $a['penugasan_selesai'] === $a['penugasan_total']) {
                $selesaiPenuh++;
            }
        }

        return [
            'total_anggota' => count($anggota),
            'hasil_terbit' => $terbit,
            'hasil_tertahan' => $tertahan,
            'anggota_punya_keterlambatan' => $terlambat,
            'anggota_penilaian_lengkap' => $selesaiPenuh,
        ];
    }

    private function ringkasanKosong(): array
    {
        return [
            'total_anggota' => 0,
            'hasil_terbit' => 0,
            'hasil_tertahan' => 0,
            'anggota_punya_keterlambatan' => 0,
            'anggota_penilaian_lengkap' => 0,
        ];
    }

    /**
     * Batas yang berlaku, dinyatakan dalam balasan alih-alih hanya berlaku diam-diam.
     *
     * Layar yang tidak tahu batasnya akan menyajikan ketiadaan sebagai ketiadaan data.
     */
    private function batas(): array
    {
        return [
            'sumber_tim' => 'N_HRIS_Organization_Relationship',
            'min_penilai_untuk_tampil' => self::MIN_PENILAI_UNTUK_TAMPIL,
            'nilai_hanya_setelah_terbit' => true,
            'tanpa_nilai_per_penilai' => true,
        ];
    }

    private function identitas(object $k): array
    {
        return [
            'kode_karyawan' => (string) $k->Kode_Karyawan,
            'nama' => $k->Nama,
        ];
    }

    /**
     * Kolom desimal SQL Server pulang sebagai string lewat PDO.
     *
     * Dibiarkan apa adanya akan membuat layar membandingkan "9.5" dengan 9.5 dan
     * mengurutkan angka secara leksikografis.
     */
    private function angka(mixed $v): ?float
    {
        return $v === null ? null : (float) $v;
    }
}
