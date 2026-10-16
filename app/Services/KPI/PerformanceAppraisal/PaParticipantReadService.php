<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembaca daftar peserta dan detail hasil.
 *
 * TIDAK MENYENTUH MASTER HIDUP
 *
 * Seluruh identitas yang ditampilkan — nama, level, divisi, jabatan — dibaca dari kolom
 * `*_Saat_Cycle` milik peserta, bukan dari `Karyawan` hari ini. Itu bukan kelalaian
 * normalisasi: siklus penilaian menilai keadaan seseorang pada saat itu, dan orang yang
 * pindah divisi bulan depan tidak boleh membuat hasil tahun lalu berubah divisi.
 *
 * SATU KUERI PER HALAMAN, BUKAN PER BARIS
 *
 * Cacah penugasan diambil satu kali untuk seluruh baris halaman, lalu dijodohkan di
 * memori. Menanyakannya per peserta menghasilkan N+1 yang tak terlihat sampai
 * halamannya berisi lima puluh orang — dan database pengembangan berjarak puluhan
 * milidetik per perjalanan.
 */
class PaParticipantReadService
{
    /** Batas atas ukuran halaman. Permintaan yang lebih besar dipangkas, dan dilaporkan. */
    public const MAKS_PER_HALAMAN = 100;

    public function __construct(
        private readonly PaParticipantRuleResolver $resolver,
        private readonly PaKalibrasiPenilaiService $kalibrasiPenilai,
        private readonly PaSkorEfektif $skorEfektif,
        private readonly PaKurvaService $kurva,
    ) {}

    /**
     * Satu halaman peserta beserta ringkasan kesiapannya.
     *
     * @param  array{
     *     status_peserta?:string|null, status_hasil?:string|null,
     *     id_divisi?:int|null, id_level?:int|null, q?:string|null,
     *     halaman?:int, per_halaman?:int
     * }  $saringan
     * @return array{
     *     peserta:list<array<string, mixed>>,
     *     halaman:int, per_halaman:int, total:int, total_halaman:int,
     *     dipangkas:bool,
     *     ringkasan:array<string, int>
     * }
     */
    public function daftar(int $cycleId, array $saringan = []): array
    {
        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $diminta = (int) ($saringan['per_halaman'] ?? 25);
        $perHalaman = max(1, min($diminta, self::MAKS_PER_HALAMAN));

        $dasar = fn () => $this->kueriTersaring($cycleId, $saringan);

        /* PILIH SEMUA LINTAS HALAMAN. Yang dikembalikan hanya id peserta yang memang bisa
           dipilih (hasil DIHITUNG, atau TERBIT yang belum dirilis ke leader) di bawah saringan
           yang sama dengan daftarnya — bukan sekadar baris halaman yang sedang terbuka. */
        if (! empty($saringan['hanya_id'])) {
            $ids = $dasar()
                ->where(function ($w) {
                    $w->where('r.Status_Hasil', 'DIHITUNG')
                        ->orWhere(fn ($t) => $t->where('r.Status_Hasil', 'TERBIT')->whereNull('r.Published_Leader_At'));
                })
                ->orderBy('p.Id_PA_Participant')
                ->pluck('p.Id_PA_Participant')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            return ['ids' => $ids, 'total' => count($ids)];
        }

        $total = (int) $dasar()->count();

        $baris = $dasar()
            // Urutan HARUS deterministik sampai ke kunci utama. Tanpa itu, orang bisa
            // muncul di dua halaman atau lenyap sama sekali di antara halaman.
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('p.Id_PA_Participant')
            ->offset(($halaman - 1) * $perHalaman)
            ->limit($perHalaman)
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Perusahaan', 'p.Kode_Karyawan',
                'p.Nama_Karyawan_Saat_Cycle', 'p.ID_Level_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
                // Jejak Level PA (DDL-2026-177): level HRIS asli & koreksi yang dipakai.
                'p.Nama_Level_HRIS_Saat_Cycle', 'p.PA_Karyawan_Level_PA_Id',
                'p.ID_Divisi_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Sub_Divisi_Saat_Cycle',
                'p.Nama_Jabatan_Saat_Cycle', 'p.Lokasi_Saat_Cycle',
                'p.Status_Peserta', 'p.Alasan_Dikecualikan',
                'p.PA_Cycle_Rater_Policy_Snapshot_Id',
                'r.Id_PA_Result', 'r.Status_Hasil', 'r.Nilai_KPI', 'r.Nilai_Kompetensi',
                'r.Final_Score', 'r.Kode_Grade_Sistem', 'r.Kode_Grade_Final',
                // Bobot terpakai dan waktu hitung dibaca tabel Hasil (persen pilar, "Dihitung: …").
                'r.Bobot_KPI_Terpakai', 'r.Bobot_Kompetensi_Terpakai', 'r.Calculated_At',
                'r.Flag_Dikalibrasi', 'r.Alasan_Tertahan', 'r.Calculation_Version',
                'r.Published_At', 'r.Published_Leader_At', 'r.Unpublished_Leader_At', 'r.Alasan_Cabut_Leader',
            ]);

        $cacah = $this->cacahPenugasan($baris->pluck('Id_PA_Participant')->all());
        /* Siklus KURVA belum dikunci: grade tersimpan masih NULL. `Grade_Sementara` adalah
           grade menurut kurva grup SAAT INI — hanya dibaca, bisa bergeser; NULL di luar itu. */
        $sementara = $this->kurva->gradeSementara($cycleId);

        $peserta = $baris->map(function ($b) use ($cacah, $sementara) {
            $c = $cacah[(int) $b->Id_PA_Participant] ?? ['terpasang' => 0, 'selesai' => 0];

            return (array) $b + [
                'Grade_Sementara' => $sementara[(int) $b->Id_PA_Participant] ?? null,
                'penilai_terpasang' => $c['terpasang'],
                'penilai_selesai' => $c['selesai'],
            ];
        })->values()->all();

        return [
            'peserta' => $peserta,
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => $total,
            'total_halaman' => (int) ceil($total / $perHalaman),
            // Pembatasan apa pun wajib dilaporkan, bukan diberlakukan diam-diam.
            'dipangkas' => $diminta > self::MAKS_PER_HALAMAN,
            'ringkasan' => $this->ringkasan($cycleId),
        ] + (empty($saringan['dengan_opsi']) ? [] : ['opsi' => $this->opsiSaringan($cycleId)]);
    }

    /**
     * Pilihan divisi, level, sub divisi (departemen), jabatan, dan lokasi untuk komponen saringan
     * — diambil dari snapshot peserta siklus ini, supaya admin memilih nama yang ada, bukan mengetik ID.
     *
     * @return array{
     *     divisi: list<array{value:int,label:string}>,
     *     level: list<array{value:int,label:string}>,
     *     sub_divisi: list<array{value:int,label:string}>,
     *     jabatan: list<array{value:string,label:string}>,
     *     lokasi: list<array{value:string,label:string}>
     * }
     */
    private function opsiSaringan(int $cycleId): array
    {
        $dasar = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Peserta', 'DIIKUTKAN');

        $opsiAngka = fn (string $kolomId, string $kolomNama) => (clone $dasar)
            ->whereNotNull($kolomId)
            ->groupBy($kolomId, $kolomNama)
            ->orderBy($kolomNama)
            ->get([$kolomId.' as id', $kolomNama.' as nama'])
            ->map(fn ($r) => ['value' => (int) $r->id, 'label' => (string) ($r->nama ?: "#{$r->id}")])
            ->values()
            ->all();

        $opsiTeks = fn (string $kolomKode, string $kolomNama) => (clone $dasar)
            ->whereNotNull($kolomKode)
            ->where($kolomKode, '<>', '')
            ->groupBy($kolomKode, $kolomNama)
            ->orderBy($kolomNama)
            ->get([$kolomKode.' as kode', $kolomNama.' as nama'])
            ->map(fn ($r) => ['value' => (string) $r->kode, 'label' => (string) ($r->nama ?: $r->kode)])
            ->values()
            ->all();

        $opsiLokasi = (clone $dasar)
            ->whereNotNull('Lokasi_Saat_Cycle')
            ->where('Lokasi_Saat_Cycle', '<>', '')
            ->distinct()
            ->orderBy('Lokasi_Saat_Cycle')
            ->pluck('Lokasi_Saat_Cycle')
            ->map(fn ($l) => ['value' => (string) $l, 'label' => (string) $l])
            ->values()
            ->all();

        return [
            'divisi' => $opsiAngka('ID_Divisi_Saat_Cycle', 'Nama_Divisi_Saat_Cycle'),
            'level' => $opsiAngka('ID_Level_Saat_Cycle', 'Nama_Level_Saat_Cycle'),
            'sub_divisi' => $opsiAngka('ID_Sub_Divisi_Saat_Cycle', 'Nama_Sub_Divisi_Saat_Cycle'),
            'jabatan' => $opsiTeks('Kode_Jabatan_Saat_Cycle', 'Nama_Jabatan_Saat_Cycle'),
            'lokasi' => $opsiLokasi,
        ];
    }

    /**
     * Cacah peserta per status untuk seluruh siklus — bukan cuma halaman ini.
     *
     * @return array<string, int>
     */
    public function ringkasan(int $cycleId): array
    {
        $baris = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Status_Peserta')
            ->select(['Status_Peserta', DB::raw('COUNT(*) AS jumlah')])
            ->get();

        $hasil = ['DIIKUTKAN' => 0, 'DIKECUALIKAN' => 0, 'TERTUNDA' => 0];

        foreach ($baris as $b) {
            $hasil[$b->Status_Peserta] = (int) $b->jumlah;
        }

        return $hasil;
    }

    /**
     * Seluruh isi layar hasil satu peserta, termasuk bahan radar.
     *
     * @return array<string, mixed>
     */
    public function detailHasil(int $participantId): array
    {
        $peserta = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', 'r.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->where('p.Id_PA_Participant', $participantId)
            ->first([
                'p.Id_PA_Participant', 'p.PA_Cycle_Id', 'p.Kode_Perusahaan', 'p.Kode_Karyawan',
                'p.Nama_Karyawan_Saat_Cycle', 'p.ID_Level_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
                'p.Nama_Level_HRIS_Saat_Cycle', 'p.PA_Karyawan_Level_PA_Id',
                'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Sub_Divisi_Saat_Cycle',
                'p.Nama_Jabatan_Saat_Cycle', 'p.Lokasi_Saat_Cycle',
                'p.Status_Peserta', 'p.Alasan_Dikecualikan',
                'r.Id_PA_Result', 'r.Status_Hasil',
                'r.Nilai_KPI', 'r.Nilai_KPI_Sebelum_Cap',
                'r.KPI_Periode_Dari', 'r.KPI_Periode_Sampai', 'r.Jumlah_KPI_Belum_Disetujui',
                'r.Nilai_Kompetensi', 'r.Jumlah_Rater_Diharapkan', 'r.Jumlah_Rater_Mengisi',
                'r.Bobot_KPI_Terpakai', 'r.Bobot_Kompetensi_Terpakai', 'r.Final_Score',
                'r.Kode_Grade_Sistem', 'r.Kode_Grade_Final', 'r.Flag_Dikalibrasi',
                'r.PA_Hav_Quadrant_Id', 'r.Alasan_Tertahan', 'r.Calculation_Version',
                'r.Calculated_At', 'r.Published_At',
            ]);

        if ($peserta === null) {
            throw new RuntimeException("Peserta {$participantId} tidak ada.");
        }

        $resultId = $peserta->Id_PA_Result === null ? null : (int) $peserta->Id_PA_Result;

        // Dibaca sekali, dipakai dua kali: sebagai daftar jawaban, dan sebagai bahan
        // penghitung berapa penilai yang sudah menjawab tiap pertanyaan.
        $naratif = $this->jawabanNaratif($participantId);

        return [
            // Grade_Sementara: lihat daftar() — hanya siklus KURVA yang kurvanya belum dikunci.
            'peserta' => (array) $peserta + [
                'Grade_Sementara' => $this->kurva->gradeSementara((int) $peserta->PA_Cycle_Id)[$participantId] ?? null,
            ],
            'kuadran' => $this->kuadran($peserta->PA_Hav_Quadrant_Id),
            'kategori' => $resultId === null ? [] : $this->jejakKategori($resultId),
            'kompetensi' => $resultId === null ? [] : $this->nilaiKompetensi($resultId),
            'catatan' => $resultId === null ? [] : $this->catatan($resultId),

            /* Jawaban naratif MENTAH, terpisah dari `catatan`. Lihat alasannya di
               `jawabanNaratif()`. */
            'jawaban_naratif' => $naratif,

            /* PERTANYAAN kualitatif yang benar-benar diajukan kepada penilai orang ini,
               beserta berapa yang sudah menjawabnya. Tanpa daftar ini layar hanya bisa
               menggambar pertanyaan yang ADA JAWABANNYA, sehingga pertanyaan yang diajukan
               tetapi belum dijawab siapa pun tidak terlihat sama sekali — dan admin tidak
               bisa membedakan "tidak ditanyakan" dari "belum dijawab". */
            'pertanyaan_kualitatif' => $this->pertanyaanKualitatif($participantId, $naratif),

            /* Kategori yang tersedia, dikirim supaya layar merender kotak catatannya dari
               DATA. Versi sebelumnya menuliskan dua kode tetap di komponen, sehingga
               menambah kategori lewat master tidak menambah kotaknya — dan tidak ada satu
               pun galat yang menandai bahwa kategori baru itu tak bisa diisi. */
            'kategori_catatan' => $this->kategoriCatatan($naratif, $resultId),
            'penilai' => $this->penilai($participantId),
            'riwayat_kalibrasi' => $resultId === null ? [] : $this->riwayatKalibrasi($resultId),

            /* Kalibrasi PENILAI (penilai merevisi skornya sebelum mengirim), dipisah dari
               `riwayat_kalibrasi` yang milik forum. Dua mekanisme, dua kunci: menyatukannya
               membuat pembaca audit tidak bisa lagi membedakan siapa yang mengubah apa. */
            'kalibrasi_penilai' => $this->kalibrasiPenilai->riwayatPeserta($participantId),

            /* Kalibrasi yang dibuat SEBELUM perhitungan terakhir.

               Perhitungan ulang kini mempertahankan keputusan forum alih-alih menimpanya —
               tetapi angka yang mendasarinya bisa saja sudah bergeser sesudah forum
               memutuskan. Diturunkan dari perbandingan waktu, bukan disimpan sebagai kolom:
               kolom penanda harus dijaga tetap benar oleh setiap jalur tulis, sedangkan
               perbandingan ini selalu benar dengan sendirinya. */
            'kalibrasi_perlu_ditinjau' => $resultId === null
                ? false
                : $this->kalibrasiLebihTuaDariPerhitungan($peserta, $resultId),
            // Aturan yang berlaku bagi orang ini, lengkap dengan penanda penyimpangan.
            'aturan_penilai' => $this->aturanPenilaiAman($participantId),
            'penyimpangan' => $this->penyimpanganAman($participantId),
            // Bobot level dan kategori yang sengaja tidak diikutkan (13 Okt 2026).
            'bobot_penilai' => PaKategoriTidakDiikutkanService::rincianPeserta($participantId),

            /* Pilihan sah bagi forum kalibrasi.

               Dikirim bersama detail yang memang sudah dimuat layar itu, bukan lewat
               endpoint sendiri: dialog kalibrasi tidak pernah terbuka tanpa detail ini
               lebih dulu ada, jadi permintaan kedua hanya menambah satu perjalanan tanpa
               menambah satu pun keterangan. */
            'pilihan_kalibrasi' => $this->pilihanKalibrasi((int) $peserta->PA_Cycle_Id),

            // Peringkat, skor z, dan jarak ke grade berikutnya di grup kurvanya (NULL bila bukan KURVA).
            'posisi_kurva' => $resultId === null ? null : $this->kurva->posisiPeserta($participantId),

            // Hasil orang yang sama di siklus aktif sebelumnya (NULL bila tidak ada).
            'tren' => $this->trenSebelumnya($peserta),
        ];
    }

    /**
     * Hasil karyawan yang sama di siklus aktif tepat sebelum siklus ini (urutan Tahun, lalu
     * Id siklus), yang sudah dihitung. Jalur admin: tidak dibatasi status terbit.
     *
     * @return array<string, mixed>|null
     */
    private function trenSebelumnya(object $peserta): ?array
    {
        $siklus = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $peserta->PA_Cycle_Id)->first(['Tahun', 'Id_PA_Cycle']);

        if ($siklus === null) {
            return null;
        }

        $r = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('p.Kode_Perusahaan', $peserta->Kode_Perusahaan)
            ->where('p.Kode_Karyawan', $peserta->Kode_Karyawan)
            ->where('c.Flag_Aktif', 'Y')
            ->where('r.Flag_Aktif', 'Y')
            ->whereIn('r.Status_Hasil', ['DIHITUNG', 'TERBIT'])
            ->whereNotNull('r.Final_Score')
            ->where(function ($q) use ($siklus) {
                $q->where('c.Tahun', '<', $siklus->Tahun)
                    ->orWhere(fn ($w) => $w->where('c.Tahun', $siklus->Tahun)->where('c.Id_PA_Cycle', '<', $siklus->Id_PA_Cycle));
            })
            ->orderByDesc('c.Tahun')->orderByDesc('c.Id_PA_Cycle')
            ->first(['c.Id_PA_Cycle', 'c.Kode_Cycle', 'c.Nama_Cycle', 'r.Final_Score', 'r.Kode_Grade_Final',
                'r.Nilai_KPI', 'r.Nilai_Kompetensi']);

        return $r === null ? null : [
            'cycle' => ['cycle_id' => (int) $r->Id_PA_Cycle, 'kode_cycle' => $r->Kode_Cycle, 'nama_cycle' => $r->Nama_Cycle],
            'final' => (float) $r->Final_Score,
            'grade' => $r->Kode_Grade_Final,
            'kpi' => $r->Nilai_KPI === null ? null : (float) $r->Nilai_KPI,
            'kompetensi' => $r->Nilai_Kompetensi === null ? null : (float) $r->Nilai_Kompetensi,
        ];
    }

    /**
     * Grade dan kuadran yang BOLEH dipilih forum kalibrasi.
     *
     * Sumbernya sengaja disamakan persis dengan yang dipakai `PaCalibrationService`
     * untuk MENOLAK: grade dari salinan pita siklus (`N_PA_Cycle_Score_Range_Snapshot`,
     * FINAL/GRADE, aktif), kuadran dari `N_PA_Hav_Quadrant` yang aktif.
     *
     * Kalau daftar ini diambil dari master hidup, layar akan menawarkan grade yang lalu
     * ditolak server — dan itu lebih buruk daripada kotak teks yang sekarang, karena
     * pilihan yang ditawarkan berbunyi seperti janji.
     *
     * @return array{grade:list<array<string, mixed>>, kuadran:list<array<string, mixed>>}
     */
    private function pilihanKalibrasi(int $cycleId): array
    {
        return [
            'grade' => DB::table('N_PA_Cycle_Score_Range_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Score_Type', 'FINAL')
                ->where('Score_Usage', 'GRADE')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Tampil')
                ->get(['Kode_Range', 'Nama_Range', 'Nilai_Dari', 'Nilai_Sampai'])
                ->map(fn ($r) => (array) $r)->all(),

            /* `Nomor_Box` ikut dikirim, dan itu bukan hiasan: dua kuadran bisa bernama
               SAMA — terukur di development, #2 FUTURE_STAR_A dan #3 FUTURE_STAR_B
               keduanya bernama "Future Star". Tanpa nomor box, dua baris dropdown akan
               berbunyi identik dan yang memilih tidak punya cara membedakannya. */
            'kuadran' => DB::table('N_PA_Hav_Quadrant')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Nomor_Box')
                ->get(['Id_PA_Hav_Quadrant', 'Kode_Kuadran', 'Nama_Kuadran', 'Nomor_Box'])
                ->map(fn ($r) => (array) $r)->all(),
        ];
    }

    /**
     * Sebaran seluruh peserta pada sembilan kotak HAV Map, plus distribusi grade.
     *
     * SEMBILAN KOTAK SELALU DIKEMBALIKAN, termasuk yang kosong. Kotak yang hilang dari
     * respons akan membuat layar menggambar matriks berlubang, dan kotak kosong justru
     * bagian dari ceritanya — "tidak ada seorang pun di Deadwood" adalah temuan, bukan
     * ketiadaan data.
     *
     * `Target_Persen` berasal dari pita salinan siklus dan disandingkan dengan angka
     * sebenarnya. Itu PEMBANDING, bukan kuota: tidak ada satu pun tempat di sistem ini
     * yang memaksa sebaran mengikuti target.
     *
     * `pita` adalah batas Rendah/Sedang/Tinggi per sumbu dari salinan siklus, sumber
     * yang sama dengan penempatan kotak. Grafik sebar menggambar area kotak dari sini;
     * tanpa itu layar menebak batasnya dan titik jatuh di kotak yang salah.
     *
     * @return array{
     *     kuadran:list<array<string, mixed>>,
     *     grade:list<array<string, mixed>>,
     *     pita:array{kpi:list<array<string, mixed>>, kompetensi:list<array<string, mixed>>},
     *     ringkasan:array{dihitung:int, tertahan:int, belum_dihitung:int, terbit:int, total:int}
     * }
     */
    /**
     * Soal kualitatif yang diajukan kepada penilai seseorang beserta jawaban naratifnya —
     * bagian yang sama dengan `detailHasil()`, tanpa menghitung seluruh detail hasil.
     * Dipakai modal kualitatif di board Balancing.
     *
     * @return array{pertanyaan: list<array<string, mixed>>, jawaban: list<array<string, mixed>>}
     */
    public function kualitatifPeserta(int $participantId): array
    {
        $naratif = $this->jawabanNaratif($participantId);

        return ['pertanyaan' => $this->pertanyaanKualitatif($participantId, $naratif), 'jawaban' => $naratif];
    }

    public function havMap(int $cycleId): array
    {
        $cacahKuadran = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNotNull('r.PA_Hav_Quadrant_Id')
            ->groupBy('r.PA_Hav_Quadrant_Id')
            ->select(['r.PA_Hav_Quadrant_Id', DB::raw('COUNT(*) AS jumlah')])
            ->pluck('jumlah', 'PA_Hav_Quadrant_Id');

        $pesertaRows = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('r.Flag_Aktif', 'Y')
            ->whereNotNull('r.PA_Hav_Quadrant_Id')
            ->select([
                'r.Id_PA_Result',
                'p.Id_PA_Participant',
                'p.Nama_Karyawan_Saat_Cycle',
                'p.Kode_Karyawan',
                'p.Nama_Jabatan_Saat_Cycle',
                'p.ID_Divisi_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle',
                'p.Nama_Sub_Divisi_Saat_Cycle',
                'p.ID_Level_Saat_Cycle',
                'p.Nama_Level_Saat_Cycle',
                'p.Lokasi_Saat_Cycle',
                'r.PA_Hav_Quadrant_Id',
                'q.Nomor_Box',
                'q.Nama_Kuadran',
                'r.Nilai_KPI as Skor_KPI',
                'r.Nilai_Kompetensi as Skor_Kompetensi',
                'r.Final_Score',
                'r.Kode_Grade_Final',
                'r.Kode_Grade_Sistem',
                'r.Status_Hasil',
            ])
            ->get();

        $pesertaPerKuadran = $pesertaRows->groupBy('PA_Hav_Quadrant_Id');

        $kuadran = DB::table('N_PA_Hav_Quadrant')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            // `Deskripsi` ikut: tanpanya layar menampilkan panduan karangan yang bertentangan dengan master.
            ->get(['Id_PA_Hav_Quadrant', 'Kode_Kuadran', 'Nama_Kuadran', 'Nomor_Box',
                'Tingkat_Performa', 'Tingkat_Kompetensi', 'Warna', 'Deskripsi'])
            ->map(fn ($k) => (array) $k + [
                'jumlah' => (int) ($cacahKuadran[$k->Id_PA_Hav_Quadrant] ?? 0),
                'peserta' => ($pesertaPerKuadran[$k->Id_PA_Hav_Quadrant] ?? collect())->values()->all(),
            ])
            ->values()
            ->all();

        // Grade yang dipakai adalah Kode_Grade_FINAL: itulah yang berlaku bagi orangnya
        // setelah kalibrasi. Memakai grade sistem akan menggambar sebaran yang tidak
        // cocok dengan apa pun yang dilihat karyawan.
        $cacahGrade = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNotNull('r.Kode_Grade_Final')
            ->groupBy('r.Kode_Grade_Final')
            ->select(['r.Kode_Grade_Final', DB::raw('COUNT(*) AS jumlah')])
            ->pluck('jumlah', 'Kode_Grade_Final');

        $totalBergrade = (int) $cacahGrade->sum();

        $grade = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Score_Type', 'FINAL')
            ->where('Score_Usage', 'GRADE')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get(['Kode_Range', 'Nama_Range', 'Nilai_Dari', 'Nilai_Sampai',
                'Target_Persen', 'Warna'])
            ->map(function ($g) use ($cacahGrade, $totalBergrade) {
                $jumlah = (int) ($cacahGrade[$g->Kode_Range] ?? 0);

                return (array) $g + [
                    'jumlah' => $jumlah,
                    'persen' => $totalBergrade > 0
                        ? round($jumlah / $totalBergrade * 100, 2)
                        : 0.0,
                ];
            })
            ->values()
            ->all();

        $pitaHav = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Score_Usage', 'HAVMAP')
            ->whereIn('Score_Type', ['KPI', 'KOMPETENSI'])
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Nilai_Dari')
            ->get(['Score_Type', 'Kode_Range', 'Nama_Range', 'Nilai_Dari', 'Nilai_Sampai'])
            ->groupBy('Score_Type');

        $pitaSumbu = fn (string $tipe) => ($pitaHav[$tipe] ?? collect())
            ->map(fn ($p) => [
                'Kode_Range' => $p->Kode_Range,
                'Nama_Range' => $p->Nama_Range,
                'Nilai_Dari' => (float) $p->Nilai_Dari,
                'Nilai_Sampai' => (float) $p->Nilai_Sampai,
            ])
            ->values()
            ->all();

        return [
            'kuadran' => $kuadran,
            'grade' => $grade,
            'pita' => [
                'kpi' => $pitaSumbu('KPI'),
                'kompetensi' => $pitaSumbu('KOMPETENSI'),
            ],
            'ringkasan' => $this->ringkasanHasil($cycleId),
        ];
    }

    /**
     * @return array{dihitung:int, tertahan:int, belum_dihitung:int, terbit:int, total:int}
     */
    private function ringkasanHasil(int $cycleId): array
    {
        $baris = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            // Peserta dikecualikan atau nonaktif bukan bagian siklus; dulu ikut dalam angka tertahan.
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->groupBy('r.Status_Hasil')
            ->select(['r.Status_Hasil', DB::raw('COUNT(*) AS jumlah')])
            ->get();

        $hasil = ['dihitung' => 0, 'tertahan' => 0, 'belum_dihitung' => 0, 'terbit' => 0, 'total' => 0];
        $peta = [
            'DIHITUNG' => 'dihitung',
            'TERTAHAN' => 'tertahan',
            'BELUM_DIHITUNG' => 'belum_dihitung',
            'TERBIT' => 'terbit',
        ];

        foreach ($baris as $b) {
            $kunci = $peta[$b->Status_Hasil] ?? null;
            $hasil['total'] += (int) $b->jumlah;

            if ($kunci !== null) {
                $hasil[$kunci] = (int) $b->jumlah;
            }
        }

        return $hasil;
    }

    /**
     * Keterlambatan satu penugasan — DITURUNKAN, tidak disimpan.
     *
     * Status tersimpan akan menuntut pekerjaan yang membalik baris tiap tengah malam, dan
     * pekerjaan semacam itu yang mati diam-diam menghasilkan lencana salah tanpa ada yang
     * tahu. Tenggat juga bisa diperpanjang, dan status tersimpan harus dibalik lagi.
     *
     * Dihitung di SERVER, bukan di layar. Dua rumus untuk satu gagasan pasti berselisih
     * suatu hari, dan yang berselisih di sini adalah angka yang dipakai mengejar orang.
     *
     * Definisi KETAT (`PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT`): hanya
     * SELESAI dan DIBATALKAN yang dibebaskan. Padanannya
     * `PaAdminDashboardService::syaratTerlambat()` (sama-sama sudah dialihkan ke
     * konstanta ini). Jangan disamakan dengan `TIDAK_DIHITUNG_TERLAMBAT` (longgar,
     * ikut membebaskan TERKUMPUL/MENUNGGU_VERIFIKASI) — lihat docblock
     * `PaStatusPenugasan` untuk alasannya.
     *
     * @return array{terlambat:bool, hari_terlambat:int|null}
     */
    private function keterlambatan(object $penugasan): array
    {
        $selesai = in_array($penugasan->Status_Penugasan, PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT_KETAT, true);

        if ($penugasan->Due_At === null || $selesai) {
            // Tanpa tenggat, tidak ada yang bisa dilewati. Yang sudah selesai atau
            // dibatalkan tidak lagi ditunggu siapa pun.
            return ['terlambat' => false, 'hari_terlambat' => null];
        }

        /* `Due_At` bertipe DATE, bukan datetime. strtotime('2026-09-06') jatuh di TENGAH
           MALAM, sehingga tanpa penyesuaian ini orang yang tenggatnya hari ini sudah
           ditandai terlambat sejak pukul 00:00 - pada hari yang justru masih miliknya,
           dengan lencana merah bertuliskan "Terlambat 0 hari". Tenggat tanggal berarti
           SAMPAI AKHIR hari itu. */
        $mentah = (string) $penugasan->Due_At;
        $tenggat = strtotime(strlen($mentah) <= 10 ? $mentah.' 23:59:59' : $mentah);
        $sekarang = time();

        if ($tenggat === false || $tenggat >= $sekarang) {
            return ['terlambat' => false, 'hari_terlambat' => null];
        }

        /* Hari KALENDER, bukan selisih jam dibagi 24. Tenggat kemarin sore yang lewat
           sembilan belas jam menghasilkan nol kalau dibagi 24, dan lencana merah
           bertuliskan "Terlambat 0 hari" membuat orang berhenti mempercayai layarnya. */
        $tenggatHari = new \DateTimeImmutable(substr($mentah, 0, 10));
        $hariIni = new \DateTimeImmutable(date('Y-m-d'));

        return [
            'terlambat' => true,
            'hari_terlambat' => max(0, (int) $tenggatHari->diff($hariIni)->days),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $saringan
     */
    private function kueriTersaring(int $cycleId, array $saringan)
    {
        $q = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', 'r.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y');

        /*
         | SATU PESERTA, dituju langsung.
         |
         | Dipakai tautan dari keranjang keputusan admin: alamatnya membawa id peserta, dan
         | dialog hasilnya perlu baris orang itu — bukan halaman yang kebetulan memuatnya.
         | Tanpa saringan ini pemanggil hanya bisa memuat satu halaman lalu berharap orangnya
         | ada di sana; yang di halaman kelima akan membuat dialognya tidak terbuka sama sekali,
         | tanpa satu pun pesan.
         */
        if (! empty($saringan['participant_id'])) {
            $q->where('p.Id_PA_Participant', (int) $saringan['participant_id']);
        }

        if (! empty($saringan['status_peserta'])) {
            $q->where('p.Status_Peserta', $saringan['status_peserta']);
        }

        if (! empty($saringan['status_hasil'])) {
            /* Menerima BEBERAPA status sekaligus, dipisah koma.

               Layar "Hasil, Kalibrasi & Publikasi" bekerja pada dua status sekaligus —
               `DIHITUNG` yang menunggu ditinjau dan `TERBIT` yang sudah selesai — dan
               menyaringnya di layar berarti menarik seluruh peserta lebih dulu. Pada siklus
               berisi hampir seribu orang, halaman pertama bisa habis oleh peserta yang belum
               dihitung, dan orang yang justru dicari tidak pernah muncul: penyaringan yang
               dilakukan SESUDAH pemenggalan halaman menghilangkan orang tanpa pesan apa pun.

               Nilai tunggal tetap bekerja seperti sebelumnya. */
            $status = array_values(array_filter(array_map(
                'trim',
                explode(',', (string) $saringan['status_hasil']),
            )));

            count($status) > 1
                ? $q->whereIn('r.Status_Hasil', $status)
                : $q->where('r.Status_Hasil', $status[0] ?? $saringan['status_hasil']);
        }

        // Status rilis ke leader: Y = sudah dibuka untuk leader, T = belum.
        if (($saringan['rilis_leader'] ?? '') === 'Y') {
            $q->whereNotNull('r.Published_Leader_At');
        } elseif (($saringan['rilis_leader'] ?? '') === 'T') {
            $q->whereNull('r.Published_Leader_At');
        }

        if (! empty($saringan['id_divisi'])) {
            $q->where('p.ID_Divisi_Saat_Cycle', (int) $saringan['id_divisi']);
        }

        if (! empty($saringan['id_sub_divisi'])) {
            $q->where('p.ID_Sub_Divisi_Saat_Cycle', (int) $saringan['id_sub_divisi']);
        }

        if (! empty($saringan['id_level'])) {
            $q->where('p.ID_Level_Saat_Cycle', (int) $saringan['id_level']);
        }

        if (! empty($saringan['kode_jabatan'])) {
            $q->where('p.Kode_Jabatan_Saat_Cycle', $saringan['kode_jabatan']);
        }

        if (! empty($saringan['lokasi'])) {
            $q->where('p.Lokasi_Saat_Cycle', $saringan['lokasi']);
        }

        $cari = trim((string) ($saringan['q'] ?? ''));

        if ($cari !== '') {
            // Pencarian nama memang tidak sargable; itu ongkos yang disadari untuk
            // kotak cari, dan hanya berlaku ketika kotaknya benar-benar diisi.
            $q->where(function ($w) use ($cari) {
                $w->where('p.Nama_Karyawan_Saat_Cycle', 'LIKE', '%'.$cari.'%')
                    ->orWhere('p.Kode_Karyawan', 'LIKE', $cari.'%');
            });
        }

        return $q;
    }

    /**
     * @param  list<int>  $participantIds
     * @return array<int, array{terpasang:int, selesai:int}>
     */
    private function cacahPenugasan(array $participantIds): array
    {
        if ($participantIds === []) {
            return [];
        }

        $hasil = [];

        foreach (DB::table('N_PA_Assignment')
            ->whereIn('PA_Participant_Id', $participantIds)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Participant_Id')
            ->select([
                'PA_Participant_Id',
                DB::raw('COUNT(*) AS terpasang'),
                DB::raw("SUM(CASE WHEN Status_Penugasan = 'SELESAI' THEN 1 ELSE 0 END) AS selesai"),
            ])->get() as $b) {
            $hasil[(int) $b->PA_Participant_Id] = [
                'terpasang' => (int) $b->terpasang,
                'selesai' => (int) $b->selesai,
            ];
        }

        return $hasil;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jejakKategori(int $resultId): array
    {
        return DB::table('N_PA_Result_Rater_Category as rc')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'rc.PA_Rater_Category_Id')
            ->where('rc.PA_Result_Id', $resultId)
            ->where('rc.Flag_Aktif', 'Y')
            ->orderBy('c.Id_PA_Rater_Category')
            ->get([
                'c.Kode_Kategori', 'c.Nama_Kategori',
                'rc.Nilai', 'rc.Jumlah_Rater_Diharapkan', 'rc.Jumlah_Rater_Mengisi',
                'rc.Bobot_Terpakai', 'rc.Calculation_Version',
            ])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Nilai per kompetensi.
     *
     * Baris ber-`kode_kategori` KOSONG adalah **agregat lintas kategori** — itulah yang
     * digambar radar. Baris lainnya adalah rincian per kategori penilai. Menggabungkan
     * keduanya tanpa membedakan akan menggambar radar berlapis-lapis yang salah.
     *
     * @return list<array<string, mixed>>
     */
    private function nilaiKompetensi(int $resultId): array
    {
        $q = DB::table('N_PA_Result_Competency as rk')
            ->join('N_PA_Result as hr', 'hr.Id_PA_Result', '=', 'rk.PA_Result_Id')
            ->join('N_PA_Participant as hp', 'hp.Id_PA_Participant', '=', 'hr.PA_Participant_Id')
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'rk.PA_Competency_Id');

        // KKM dan status radar milik SIKLUS hasil ini, bukan master hari ini.
        return PaKompetensiSiklus::gabung($q, 'k', 'hp.PA_Cycle_Id')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->leftJoin('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'rk.PA_Rater_Category_Id')
            ->where('rk.PA_Result_Id', $resultId)
            ->where('rk.Flag_Aktif', 'Y')
            ->orderBy('t.Urutan_Tampil')
            ->orderBy('k.Urutan_Tampil')
            /* `Flag_Radar` ikut dikirim tanpa disaring — lihat alasan yang sama di
               PaPesertaService: baris ini melayani radar DAN daftar rincian sekaligus. */
            ->get([
                'k.Kode_Competency', 'k.Nama_Competency', 'k.Deskripsi', 'k.Expected_Behaviour',
                't.Kode_Type', 't.Nama_Type',
                'c.Kode_Kategori', 'c.Nama_Kategori',
                'rk.Nilai', 'rk.Jumlah_Penilai', 'rk.Calculation_Version',
                PaKompetensiSiklus::kolom('Nilai_Ambang', 'k'), PaKompetensiSiklus::kolom('Flag_Radar', 'k'),
            ])
            ->map(fn ($r) => (array) $r + ['agregat' => $r->Kode_Kategori === null])
            // Expected Behaviour sesuai tingkat yang dituntut untuk level orang ini (DDL-2026-175).
            ->pipe(fn ($rows) => app(PaKompetensiMatriksService::class)->terapkanPerilakuPerKode(
                $rows, ...app(PaKompetensiMatriksService::class)->konteksHasil($resultId), kunciKode: 'Kode_Competency', kunciPerilaku: 'Expected_Behaviour'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catatan(int $resultId): array
    {
        return DB::table('N_PA_Result_Note as n')
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'n.PA_Competency_Id')
            ->leftJoin('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'n.PA_Assignment_Id')
            ->where('n.PA_Result_Id', $resultId)
            ->where('n.Flag_Aktif', 'Y')
            ->orderBy('k.Urutan_Tampil')
            ->orderBy('n.Urutan_Tampil')
            ->get([
                // Nama kuncinya dipertahankan supaya layar tidak perlu ikut berubah:
                // yang bergeser sumber kategorinya, bukan bentuk jawabannya.
                'k.Kode_Competency as Kode_Type', 'k.Nama_Competency as Nama_Type',
                'n.Isi', 'n.Urutan_Tampil',
                'a.Nama_Penilai_Saat_Cycle',
            ])
            // Asal-usulnya penting: catatan dari penilai dan catatan yang diketik admin
            // punya bobot berbeda saat dibaca karyawan.
            ->map(fn ($r) => (array) $r + ['dari_penilai' => $r->Nama_Penilai_Saat_Cycle !== null])
            ->all();
    }

    /**
     * Kategori catatan bagi PESERTA INI — bukan seluruh isi master.
     *
     * KENAPA BUKAN MASTER, DAN KENAPA SEMPAT MASTER
     *
     * Versi pertama (DDL-2026-088) mengambilnya dari master kompetensi, dengan alasan panel
     * report sebaiknya sama bentuknya bagi semua orang. Alasan itu tidak bertahan menghadapi
     * data sungguhan: butir kualitatif hanya ada pada sebagian level, sehingga peserta yang
     * penilainya tidak pernah ditanyai apa pun tetap disodori empat kotak isian kosong. Kotak
     * yang tidak punya bahan bukan keseragaman, melainkan pekerjaan yang tampak menggantung.
     *
     * Yang dipakai sekarang dua sumber, digabung:
     *
     *   1. Dimensi yang BENAR-BENAR DITANYAKAN kepada penilai orang ini — diturunkan dari
     *      jawaban naratifnya, yang berpangkal pada paket soal pintunya.
     *   2. Dimensi yang SUDAH TERLANJUR punya catatan pada hasil ini.
     *
     * Sumber kedua bukan kehati-hatian berlebih: paket soal siklus bisa berubah, dan tanpa
     * itu catatan yang sudah ditulis admin tahun lalu akan hilang dari layar — masih ada di
     * basis data, tetapi tidak bisa dibaca maupun disunting siapa pun.
     *
     * Daftar kosong berarti peserta ini memang tidak punya dimensi kualitatif, dan layarnya
     * menyembunyikan seluruh panel catatan alih-alih menampilkan kotak yang tak berdasar.
     *
     * @param  list<array<string, mixed>>  $naratif  Hasil `jawabanNaratif()`, dipakai ulang.
     * @return list<array<string, mixed>>
     */
    private function kategoriCatatan(array $naratif, ?int $resultId): array
    {
        $dipakai = [];

        foreach ($naratif as $j) {
            $nama = $j['Nama_Competency'] ?? null;

            if ($nama !== null) {
                $dipakai[(string) $nama] = true;
            }
        }

        $berkatatan = $resultId === null ? [] : DB::table('N_PA_Result_Note as n')
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'n.PA_Competency_Id')
            ->where('n.PA_Result_Id', $resultId)
            ->where('n.Flag_Aktif', 'Y')
            ->distinct()
            ->pluck('k.Nama_Competency')
            ->all();

        foreach ($berkatatan as $nama) {
            $dipakai[(string) $nama] = true;
        }

        if ($dipakai === []) {
            return [];
        }

        /* Urutannya tetap diambil dari MASTER, bukan dari urutan kemunculan jawaban:
           urutan panel report harus sama bagi semua orang, sedangkan urutan jawaban
           bergantung pada siapa yang kebetulan mengisi lebih dulu.

           HANYA KOMPETENSI KUALITATIF menurut PaKompetensiKualitatif — saringan yang sama
           persis dengan PaResultNoteService: berjenis KUALITATIF, atau dijawab dengan ESAI
           terstruktur HC Learn (mis. CV03 "Mutual Growth" pada ujian 100164). Komentar bebas
           pada butir berskor tanpa esai TIDAK ikut; dulu ia menjadi kotak catatan yang
           kemudian ditolak saat disimpan, dan penolakan itu menggagalkan seluruh simpanan.
           Tidak ada cadangan "nama sebagai kode": kategori di luar predikat pasti ditolak
           penyimpan, jadi lebih jujur tidak ditampilkan. */
        return DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->where('k.Flag_Aktif', 'Y')
            ->whereRaw(PaKompetensiKualitatif::sqlKompetensi('k', 't'))
            ->whereIn('k.Nama_Competency', array_keys($dipakai))
            ->orderBy('k.Urutan_Tampil')
            ->orderBy('k.Kode_Competency')
            ->get(['k.Kode_Competency', 'k.Nama_Competency'])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Pertanyaan kualitatif yang diajukan kepada penilai peserta ini.
     *
     * SUMBERNYA PAKET SIKLUS, bukan master. Yang ditanyakan kepada seseorang ditentukan oleh
     * paket soal yang terpasang pada pintu penilainya, dan paket itu berbeda per level dan
     * per kategori penilai. Mengambilnya dari master akan menampilkan pertanyaan yang tidak
     * pernah diajukan kepada siapa pun.
     *
     * TEKS PERTANYAANNYA TIDAK ADA, DAN ITU DISENGAJA. Katalog HC Learn tidak mengirimkan
     * kalimat soalnya; menyalinnya ke sini berarti seluruh bank soal tersimpan di HCIS dan
     * terbaca siapa pun yang membuka halaman paket. Yang dikenal HCIS hanya kode soal dan
     * kompetensi yang diukurnya — dan itulah yang ditampilkan.
     *
     * `diminta` dihitung dari penugasan yang pintunya AKTIF. Penugasan yang pintunya belum
     * dicetak belum menerima pertanyaan apa pun, dan menghitungnya sebagai "diminta" membuat
     * angka terjawab terlihat kurang padahal tidak ada yang terlambat.
     *
     * @param  list<array<string, mixed>>  $naratif  Hasil `jawabanNaratif()`, dipakai ulang
     *                                               supaya jawabannya tidak dibaca dua kali.
     * @return list<array<string, mixed>>
     */
    private function pertanyaanKualitatif(int $participantId, array $naratif): array
    {
        $diminta = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Cycle_Question_Package_Item as it', function ($j) {
                $j->on('it.PA_Cycle_Question_Package_Id', '=', 'i.PA_Cycle_Question_Package_Id')
                    ->where('it.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'it.PA_Competency_Id')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->leftJoin('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('a.PA_Participant_Id', $participantId)
            ->where('a.Flag_Aktif', 'Y')
            ->whereRaw(PaKompetensiKualitatif::sqlKompetensi('k', 't'))
            ->orderBy('k.Urutan_Tampil')
            ->orderBy('k.Kode_Competency')
            ->orderBy('c.Kode_Kategori')
            ->orderBy('it.Kode_Soal_Sumber')
            ->get([
                /* KODE, bukan id bagian: jangkarnya harus sama dengan yang dipakai pemeta
                   payload, kalau tidak pertanyaan yang diminta dan jawaban yang masuk tidak
                   pernah bertemu dan panelnya kosong walau jawabannya ada. */
                'it.Kode_Soal_Sumber as Kode_Soal', 'k.Kode_Competency', 'k.Nama_Competency',
                'c.Kode_Kategori', 'c.Nama_Kategori',
            ]);

        /* Berapa penilai yang MENJAWAB tiap soal, dihitung dari jawaban yang sudah masuk.
           Kode soalnya sama persis dengan `Kode_Soal_Sumber` pada payload — itulah jangkar
           yang dipakai pemeta payload untuk mengenali butir. */
        $terjawab = [];

        foreach ($naratif as $j) {
            $kode = (string) ($j['Kode_Soal_Sumber'] ?? '');

            if ($kode === '') {
                continue;
            }

            $terjawab[$kode] = ($terjawab[$kode] ?? 0) + 1;
        }

        $peta = [];

        foreach ($diminta as $b) {
            /* Satu kode soal bisa muncul berkali-kali: dua penilai dalam kategori yang sama
               memakai paket yang sama, jadi pertanyaannya satu tetapi dimintanya dua. */
            $kunci = (string) $b->Kode_Soal;

            if (! isset($peta[$kunci])) {
                $peta[$kunci] = (array) $b + ['diminta' => 0, 'terjawab' => (int) ($terjawab[$kunci] ?? 0)];
            }

            $peta[$kunci]['diminta']++;
        }

        return array_values($peta);
    }

    /**
     * Seluruh jawaban naratif penilai, apa adanya.
     *
     * KENAPA TERPISAH DARI `catatan()`. Catatan hasil hanya memuat butir yang berpangkal
     * pada KOMPETENSI kualitatif, dan batas itu sengaja ketat: teks yang tidak diketahui
     * perannya lebih baik tidak muncul di layar karyawan daripada muncul di bawah judul
     * yang salah.
     *
     * Tetapi ketatnya aturan itu membuat admin ikut buta. Pada siklus yang butir uraiannya
     * tidak punya jenis catatan — dan itu keadaan bawaan, karena katalog HC Learn tidak
     * mengirimkan jenis catatan sama sekali — jawaban esai tersimpan lengkap di basis data
     * tetapi TIDAK MUNCUL DI MANA PUN. Admin yang membuka hasil peserta menyimpulkan
     * penilainya tidak mengisi, padahal ia mengisi.
     *
     * Daftar ini karena itu tidak berjudul jenis apa pun: ia menyebut PERTANYAANNYA dan
     * SIAPA yang menjawab, lalu membiarkan isinya bicara sendiri. Tidak ada yang ditebak.
     *
     * Nama penilainya ikut disebut. Ini layar ADMIN, bukan layar karyawan — dan berbeda
     * dari nilai per penilai yang memang disembunyikan, jawaban naratif tidak bisa dipakai
     * menghitung mundur angka siapa pun.
     *
     * @return list<array<string, mixed>>
     */
    private function jawabanNaratif(int $participantId): array
    {
        $baris = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Assignment_Payload as pl',
                'pl.PA_Assignment_Integration_Id', '=', 'i.Id_PA_Assignment_Integration')
            ->join('N_PA_Assignment_Score as s', function ($j) {
                $j->on('s.PA_Assignment_Payload_Id', '=', 'pl.Id_PA_Assignment_Payload')
                    ->where('s.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 's.PA_Competency_Id')
            ->where('a.PA_Participant_Id', $participantId)
            ->where('a.Flag_Aktif', 'Y')
            // Jawaban di baris bagian ATAU di anak esai per soal; isinya diuraikan dan ditimpa di bawah.
            ->whereRaw(PaSkorEfektif::sqlAdaJawaban('s'))
            /* Diurutkan lewat kategori lalu penugasan: `N_PA_Rater_Category` tidak punya
               kolom urutan, jadi kodenya sendiri yang jadi urutan — tetap dan terbaca. */
            ->orderBy('c.Kode_Kategori')
            ->orderBy('a.Id_PA_Assignment')
            ->orderBy('s.Urutan_Tampil')
            ->get([
                'a.Id_PA_Assignment', 'c.Kode_Kategori', 'c.Nama_Kategori',
                'a.Nama_Penilai_Saat_Cycle',
                's.Id_PA_Assignment_Score', 's.Kode_Soal_Sumber', 'k.Nama_Competency',
                's.Jawaban_Teks', // jawaban-mentah: langsung ditimpa jawaban efektif lewat PaSkorEfektif::timpaJawaban().
            ]);

        /* Teks yang ditampilkan adalah jawaban EFEKTIF — revisi Kalibrasi Penilai terbaru bila
           ada. Bentuk barisnya dikembalikan persis seperti sebelumnya: teks aslinya sudah
           terbaca di `kalibrasi_penilai`, dan kunci baru di sini akan mengubah kontrak. */
        return $this->skorEfektif->timpaJawaban($baris)
            ->map(fn ($r) => [
                'Kode_Kategori' => $r->Kode_Kategori,
                'Nama_Kategori' => $r->Nama_Kategori,
                'Nama_Penilai_Saat_Cycle' => $r->Nama_Penilai_Saat_Cycle,
                'Kode_Soal_Sumber' => $r->Kode_Soal_Sumber,
                'Nama_Competency' => $r->Nama_Competency,
                // Format per soal: bunyi soalnya. Null pada format lama dan pada soal yang sudah dihapus di HC Learn.
                'Pertanyaan' => $r->Pertanyaan,
                'Jawaban_Teks' => $r->Jawaban_Teks, // jawaban-mentah: nilainya sudah efektif (ditimpa di atas).
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function penilai(int $participantId): array
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('a.PA_Participant_Id', $participantId)
            ->where('a.Flag_Aktif', 'Y')
            ->orderBy('c.Id_PA_Rater_Category')
            ->orderBy('a.Nama_Penilai_Saat_Cycle')
            ->get([
                'a.Id_PA_Assignment', 'a.Nama_Penilai_Saat_Cycle', 'a.Kode_Karyawan_Penilai',
                'c.Kode_Kategori', 'c.Nama_Kategori',
                /* `Bobot_Kustom` ikut dikirim supaya layar bisa MEMBERI TAHU, bukan
                   menebak: bobot yang diatur admin tidak dibagi rata begitu saja saat satu
                   penilai dibatalkan, dan pratinjau yang menjanjikan 50:50 pada kategori
                   berbobot khusus akan berbohong tepat pada kasus yang paling diperhatikan. */
                'a.Bobot_Dalam_Kategori', 'a.Bobot_Kustom',
                'a.Sumber_Penugasan', 'a.Alasan_Manual',
                'a.Status_Penugasan', 'a.Due_At', 'a.Finished_At',
            ])
            ->map(fn ($r) => (array) $r + $this->keterlambatan($r))
            ->all();
    }

    /**
     * Benarkah kalibrasi terakhir dibuat sebelum perhitungan terakhir?
     *
     * Hasil yang tidak dikalibrasi selalu `false` — tidak ada keputusan yang bisa basi.
     */
    private function kalibrasiLebihTuaDariPerhitungan(object $peserta, int $resultId): bool
    {
        if (($peserta->Flag_Dikalibrasi ?? 'T') !== 'Y') {
            return false;
        }

        $kalibrasiTerakhir = DB::table('N_PA_Calibration_History')
            ->where('PA_Result_Id', $resultId)
            ->max('Performed_At');

        if ($kalibrasiTerakhir === null || $peserta->Calculated_At === null) {
            return false;
        }

        return (string) $peserta->Calculated_At > (string) $kalibrasiTerakhir;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function riwayatKalibrasi(int $resultId): array
    {
        return DB::table('N_PA_Calibration_History')
            ->where('PA_Result_Id', $resultId)
            ->orderByDesc('Id_PA_Calibration_History')
            ->get([
                'Kode_Grade_Sebelum', 'Kode_Grade_Sesudah',
                'PA_Hav_Quadrant_Sebelum_Id', 'PA_Hav_Quadrant_Sesudah_Id',
                'Alasan', 'Performed_By', 'Performed_At',
            ])
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function kuadran(mixed $kuadranId): ?array
    {
        if ($kuadranId === null) {
            return null;
        }

        $k = DB::table('N_PA_Hav_Quadrant')
            ->where('Id_PA_Hav_Quadrant', (int) $kuadranId)
            ->first(['Kode_Kuadran', 'Nama_Kuadran', 'Nomor_Box',
                'Tingkat_Performa', 'Tingkat_Kompetensi', 'Warna']);

        return $k === null ? null : (array) $k;
    }

    /**
     * Aturan penilai yang berlaku, atau daftar kosong bila belum bisa diresolusi.
     *
     * Peserta TERTUNDA memang belum menunjuk salinan aturan. Itu keadaan yang sah, dan
     * layar detailnya harus tetap bisa dibuka untuk melihat KENAPA — melempar di sini
     * akan membuat satu-satunya layar yang bisa menjelaskannya ikut mati.
     *
     * @return array<string, mixed>
     */
    private function aturanPenilaiAman(int $participantId): array
    {
        try {
            return $this->resolver->raterRules($participantId);
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * @return array{rater:int, pilar:int, kompetensi:int, total:int}
     */
    private function penyimpanganAman(int $participantId): array
    {
        try {
            return $this->resolver->penyimpangan($participantId);
        } catch (RuntimeException) {
            return ['rater' => 0, 'pilar' => 0, 'kompetensi' => 0, 'total' => 0];
        }
    }
}
