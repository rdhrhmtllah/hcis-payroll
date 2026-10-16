<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sebaran nilai akhir satu siklus — distribusi grade dan kurva per tingkat jabatan.
 *
 * HASIL BELUM TERBIT IKUT DIHITUNG, DAN ITU MEMANG MAKSUDNYA
 *
 * Layar ini dibaca admin justru SEBELUM penerbitan, untuk memutuskan apakah sebarannya
 * wajar dan perlu dikalibrasi. Menyaringnya menjadi "yang sudah terbit saja" akan
 * membuatnya kosong persis ketika ia paling dibutuhkan.
 *
 * Karena itu cacah per status selalu ikut dikirim: angka sebaran yang tidak menyebutkan
 * dari status mana ia berasal adalah angka yang tidak bisa dipertanggungjawabkan.
 *
 * KURVA NORMAL DARI LIMA ORANG BUKAN KURVA NORMAL
 *
 * Rata-rata dan simpangan baku tetap dihitung selama ada dua nilai — keduanya statistik
 * deskriptif yang sah. Tetapi "kurva lonceng" menyiratkan sebaran yang bermakna, dan
 * menggambarnya di atas segelintir orang menghasilkan grafik yang tampak meyakinkan
 * tentang sesuatu yang tidak diketahui. `cukup_untuk_kurva` yang memutuskan, di server,
 * supaya tiap layar tidak memilih ambangnya sendiri.
 */
class PaDistribusiService
{
    /**
     * Jumlah minimum nilai sebelum sebarannya layak digambar sebagai kurva.
     *
     * Tiga puluh adalah ambang lazim untuk menganggap sebaran sampel mulai berarti. Ia
     * memang konvensi, bukan hukum — tetapi konvensi yang disebut angkanya jauh lebih
     * baik daripada layar yang diam-diam menggambar apa saja.
     */
    public const MIN_UNTUK_KURVA = 30;

    /** Lebar tiap kelompok pada histogram, dalam satuan nilai akhir. */
    public const LEBAR_KELOMPOK = 5;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaKurvaService $kurva,
    ) {}

    /**
     * @param  array{id_level?:int|null, id_divisi?:int|null}  $saringan
     * @return array<string, mixed>
     */
    public function distribusi(int $cycleId, array $saringan = []): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Status_Cycle']);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        $metode = $this->kurva->metode($cycleId);
        /* Siklus KURVA yang belum dikunci tidak punya grade tersimpan. Sebarannya dibaca dari
           grade SEMENTARA (kurva grup saat ini) — tanpa itu grafik grade kosong persis saat
           admin menimbang apakah kurva sudah layak dikunci. */
        $sementara = $metode === 'KURVA' && ! $this->kurva->terkunci($cycleId);

        return [
            'siklus' => (array) $siklus,
            'operasi_boleh' => $this->gate->operasiYangBoleh($cycleId),
            'asal_angka' => $this->asalAngka($cycleId, $saringan),
            'keseluruhan' => $this->ringkasStatistik($cycleId, $saringan, null),
            'per_level' => $this->perLevel($cycleId, $saringan),
            'talent_highlights' => $this->talentHighlights($cycleId, $saringan, $sementara),
            'metode_grade' => $metode,
            'grade_sementara' => $sementara,
            'grade' => $this->sebaranGrade($cycleId, $saringan, $metode, $sementara),
            'kuadran' => $this->sebaranKuadran($cycleId, $saringan),
            'histogram' => $this->histogram($cycleId, $saringan),
            'min_untuk_kurva' => self::MIN_UNTUK_KURVA,
            'lebar_kelompok' => self::LEBAR_KELOMPOK,
        ];
    }

    /**
     * Menghasilkan berkas PDF Laporan Eksekutif Distribusi Performance untuk Manajemen / BoD.
     *
     * @param  array{id_level?:int|null, id_divisi?:int|null}  $saringan
     * @param  array{nama?:string, nik?:string, divisi?:string, waktu?:string}  $penarik
     */
    public function exportPdf(int $cycleId, array $saringan = [], array $penarik = [])
    {
        $distribusi = $this->distribusi($cycleId, $saringan);

        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first([
                'Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Status_Cycle',
                'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai',
                'Pengisian_Dari', 'Pengisian_Sampai', 'Published_At',
            ]);

        if (empty($penarik)) {
            $penarik = [
                'nama' => 'Administrator HCIS',
                'nik' => 'ADM-HCIS',
                'divisi' => 'Human Capital Management',
                'waktu' => now()->format('d F Y, H:i'),
            ];
        }

        return Pdf::loadView('exports.pa-distribusi-executive-pdf', [
            'distribusi' => $distribusi,
            'siklus' => $siklus,
            'penarik' => $penarik,
            'generated_at' => now(),
        ])
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
            ]);
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Dari status hasil mana angka sebaran ini berasal.
     *
     * @param  array<string, mixed>  $saringan
     * @return array<string, mixed>
     */
    private function asalAngka(int $cycleId, array $saringan): array
    {
        $per = $this->dasar($cycleId, $saringan)
            ->groupBy('r.Status_Hasil')
            ->selectRaw('r.Status_Hasil as nilai, COUNT(*) as jumlah')
            ->pluck('jumlah', 'nilai')
            ->mapWithKeys(fn ($n, $k) => [(string) $k => (int) $n])
            ->all();

        // Hasil TERTAHAN tidak punya nilai akhir, jadi ia tidak masuk sebaran sama sekali.
        // Jumlahnya tetap disebut supaya selisihnya terhadap jumlah peserta tidak jadi
        // teka-teki yang dicari orang berjam-jam.
        $tertahan = (int) DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->where('r.Status_Hasil', 'TERTAHAN')
            ->count();

        return [
            'per_status' => $per,
            'dinilai' => array_sum($per),
            'tertahan_tanpa_nilai' => $tertahan,
            'catatan' => 'Sebaran mencakup seluruh hasil yang sudah punya nilai akhir, '
                .'termasuk yang belum diterbitkan — karena layar ini justru dipakai '
                .'sebelum penerbitan untuk memutuskan kalibrasi.',
        ];
    }

    /**
     * @param  array<string, mixed>  $saringan
     */
    private function dasar(int $cycleId, array $saringan)
    {
        $q = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNotNull('r.Final_Score');

        if (! empty($saringan['id_level'])) {
            $q->where('p.ID_Level_Saat_Cycle', (int) $saringan['id_level']);
        }

        if (! empty($saringan['id_divisi'])) {
            $q->where('p.ID_Divisi_Saat_Cycle', (int) $saringan['id_divisi']);
        }

        return $q;
    }

    /**
     * @param  array<string, mixed>  $saringan
     * @return array<string, mixed>
     */
    private function ringkasStatistik(int $cycleId, array $saringan, ?int $levelId): array
    {
        $q = $this->dasar($cycleId, $saringan);

        if ($levelId !== null) {
            $q->where('p.ID_Level_Saat_Cycle', $levelId);
        }

        $r = $q->selectRaw(
            'COUNT(*) as jumlah, AVG(r.Final_Score) as rata, STDEV(r.Final_Score) as sd, '
            .'MIN(r.Final_Score) as terendah, MAX(r.Final_Score) as tertinggi, '
            .'AVG(r.Nilai_KPI) as rata_kpi, STDEV(r.Nilai_KPI) as sd_kpi, '
            .'AVG(r.Nilai_Kompetensi) as rata_kompetensi, STDEV(r.Nilai_Kompetensi) as sd_kompetensi'
        )->first();

        $stats = $this->bentukStatistik($r);
        $stats['rata_kpi'] = $r?->rata_kpi === null ? null : round((float) $r->rata_kpi, 2);
        $stats['standar_deviasi_kpi'] = $r?->sd_kpi === null ? null : round((float) $r->sd_kpi, 2);
        $stats['rata_kompetensi'] = $r?->rata_kompetensi === null ? null : round((float) $r->rata_kompetensi, 2);
        $stats['standar_deviasi_kompetensi'] = $r?->sd_kompetensi === null ? null : round((float) $r->sd_kompetensi, 2);

        return $stats;
    }

    /**
     * @return array<string, mixed>
     */
    private function bentukStatistik(?object $r): array
    {
        $jumlah = (int) ($r->jumlah ?? 0);

        return [
            'jumlah' => $jumlah,
            /* Dicetak ke float sejak di sini. Kolom decimal SQL Server tiba sebagai
               string, dan yang menerimanya JavaScript — tempat "9.00" > "82.50" bernilai
               true. Statistik yang dikirim sebagai teks akan terurut salah di layar. */
            'rata_rata' => $r?->rata === null ? null : round((float) $r->rata, 2),
            // STDEV mengembalikan NULL untuk satu baris, dan itu jawaban yang benar:
            // simpangan baku satu nilai memang tidak ada.
            'standar_deviasi' => $r?->sd === null ? null : round((float) $r->sd, 2),
            'terendah' => $r?->terendah === null ? null : round((float) $r->terendah, 2),
            'tertinggi' => $r?->tertinggi === null ? null : round((float) $r->tertinggi, 2),
            'cukup_untuk_kurva' => $jumlah >= self::MIN_UNTUK_KURVA,
            'alasan_kurva_ditahan' => $jumlah >= self::MIN_UNTUK_KURVA ? null : sprintf(
                'Baru %d nilai; kurva sebaran baru bermakna mulai %d. Angkanya tetap benar, '
                .'yang ditahan hanya penggambarannya sebagai kurva.',
                $jumlah, self::MIN_UNTUK_KURVA,
            ),
        ];
    }

    /**
     * Statistik per tingkat jabatan — SATU kueri untuk seluruh level.
     *
     * @param  array<string, mixed>  $saringan
     * @return list<array<string, mixed>>
     */
    private function perLevel(int $cycleId, array $saringan): array
    {
        return $this->dasar($cycleId, $saringan)
            ->groupBy('p.ID_Level_Saat_Cycle', 'p.Nama_Level_Saat_Cycle')
            ->selectRaw(
                'p.ID_Level_Saat_Cycle as id_level, p.Nama_Level_Saat_Cycle as nama_level, '
                .'COUNT(*) as jumlah, AVG(r.Final_Score) as rata, STDEV(r.Final_Score) as sd, '
                .'MIN(r.Final_Score) as terendah, MAX(r.Final_Score) as tertinggi, '
                .'AVG(r.Nilai_KPI) as rata_kpi, STDEV(r.Nilai_KPI) as sd_kpi, '
                .'AVG(r.Nilai_Kompetensi) as rata_kompetensi, STDEV(r.Nilai_Kompetensi) as sd_kompetensi'
            )
            ->orderBy('p.Nama_Level_Saat_Cycle')
            ->get()
            ->map(function ($r) {
                $base = $this->bentukStatistik($r);
                $base['rata_kpi'] = $r->rata_kpi === null ? null : round((float) $r->rata_kpi, 2);
                $base['standar_deviasi_kpi'] = $r->sd_kpi === null ? null : round((float) $r->sd_kpi, 2);
                $base['rata_kompetensi'] = $r->rata_kompetensi === null ? null : round((float) $r->rata_kompetensi, 2);
                $base['standar_deviasi_kompetensi'] = $r->sd_kompetensi === null ? null : round((float) $r->sd_kompetensi, 2);

                return [
                    'id_level' => $r->id_level === null ? null : (int) $r->id_level,
                    'nama_level' => $r->nama_level ?? '(Tanpa level)',
                ] + $base;
            })
            ->values()
            ->all();
    }

    /**
     * Sorotan talenta — dua keluaran berbeda, masing-masing dari sumber resminya.
     *
     * PERINGKAT KINERJA (grade tertinggi & terendah) ikut GRADE: posisi relatif dari kurva
     * grup (atau pita siklus), sama dengan yang tampil di sebaran grade. "Tertinggi" dan
     * "terendah" adalah DUA pita teratas dan terbawah menurut urutan pita siklus itu — bukan
     * angka nilai akhir mati (dulu ≥ 80 / < 80), yang tidak mengenal kurva dan membuat orang
     * bergrade "Baik" tercap perlu pembinaan.
     *
     * POTENSI ikut HAV: kuadran yang menurut master berkompetensi TINGGI. Dibaca dari kolom
     * `Tingkat_Kompetensi` master, bukan nomor kotak yang ditulis di kode (dulu memuat kotak 8
     * "Problem Employee").
     *
     * Ketiga daftar berdiri sendiri: seseorang boleh muncul di peringkat DAN di potensi.
     *
     * @param  array<string, mixed>  $saringan
     * @return array<string, mixed>
     */
    private function talentHighlights(int $cycleId, array $saringan, bool $sementara): array
    {
        $rows = $this->dasar($cycleId, $saringan)
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->get([
                'p.Id_PA_Participant as participant_id',
                'p.Kode_Karyawan as kode_karyawan',
                'p.Nama_Karyawan_Saat_Cycle as nama_karyawan',
                'p.Nama_Level_Saat_Cycle as level',
                'p.Nama_Divisi_Saat_Cycle as divisi',
                'r.Final_Score as final_score',
                'r.Nilai_KPI as nilai_kpi',
                'r.Nilai_Kompetensi as nilai_kompetensi',
                'r.Kode_Grade_Final as kode_grade',
                'r.Flag_Dikalibrasi',
                'q.Nama_Kuadran as nama_kuadran',
                'q.Tingkat_Kompetensi as tingkat_kompetensi',
            ]);

        /* Grade yang SAMA dengan sebaran grade: sebelum kurva dikunci dipakai grade sementara,
           kecuali keputusan kalibrasi yang sudah ada. */
        $petaSementara = $sementara ? $this->kurva->gradeSementara($cycleId) : [];
        $gradeOf = fn ($r) => $sementara && ! ($r->Flag_Dikalibrasi === 'Y' && $r->kode_grade !== null)
            ? ($petaSementara[(int) $r->participant_id] ?? null)
            : $r->kode_grade;

        $urutan = $this->urutanGrade($cycleId);
        $teratas = array_slice($urutan, 0, 2);
        $terbawah = count($urutan) > 2 ? array_slice($urutan, -2) : [];

        $bentuk = fn ($r) => [
            'participant_id' => (int) $r->participant_id,
            'kode_karyawan' => $r->kode_karyawan,
            'nama' => $r->nama_karyawan,
            'level' => $r->level ?? '—',
            'divisi' => $r->divisi ?? '—',
            'skor' => round((float) $r->final_score, 1),
            'nilai_kpi' => $r->nilai_kpi !== null ? round((float) $r->nilai_kpi, 1) : null,
            'nilai_kompetensi' => $r->nilai_kompetensi !== null ? round((float) $r->nilai_kompetensi, 1) : null,
            'grade' => $gradeOf($r),
            'kuadran' => $r->nama_kuadran,
        ];

        $atas = $rows->filter(fn ($r) => in_array($gradeOf($r), $teratas, true))
            ->sortByDesc(fn ($r) => (float) $r->final_score)->take(5);

        $potensi = $rows->filter(fn ($r) => $r->tingkat_kompetensi === 'TINGGI')
            ->sortByDesc(fn ($r) => (float) ($r->nilai_kompetensi ?? 0))->take(5);

        $bawah = $rows->filter(fn ($r) => in_array($gradeOf($r), $terbawah, true))
            ->sortBy(fn ($r) => (float) $r->final_score)->take(5);

        return [
            'top_performers' => $atas->map($bentuk)->values()->all(),
            'high_potential' => $potensi->map($bentuk)->values()->all(),
            'need_improvement' => $bawah->map($bentuk)->values()->all(),
            // Kriterianya ikut dikirim, supaya layar bisa menyebut dasarnya.
            'kriteria' => [
                'grade_teratas' => $teratas,
                'grade_terbawah' => $terbawah,
                'potensi' => 'Kuadran HAV berkompetensi TINGGI',
            ],
        ];
    }

    /**
     * Kode grade siklus ini, dari yang TERTINGGI ke terendah, menurut urutan pita salinan
     * siklus (pita kurva memakai kode dan urutan yang sama); master bila salinannya kosong.
     *
     * @return list<string>
     */
    private function urutanGrade(int $cycleId): array
    {
        $kode = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan_Tampil')->pluck('Kode_Range');

        if ($kode->isEmpty()) {
            $kode = DB::table('N_PA_Score_Range')
                ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
                ->where('Flag_Aktif', 'Y')->orderBy('Urutan_Tampil')->pluck('Kode_Range');
        }

        return $kode->map(fn ($k) => (string) $k)->unique()->values()->all();
    }

    /**
     * @param  array<string, mixed>  $saringan
     * @return list<array<string, mixed>>
     */
    private function sebaranGrade(int $cycleId, array $saringan, string $metode, bool $sementara): array
    {
        if ($sementara) {
            $peta = $this->kurva->gradeSementara($cycleId);
            // Keputusan forum yang sudah ada (dari sebelum pindah metode) tetap dihormati.
            $baris = $this->dasar($cycleId, $saringan)
                ->get(['p.Id_PA_Participant as pid', 'r.Kode_Grade_Final', 'r.Flag_Dikalibrasi'])
                ->map(fn ($r) => $r->Flag_Dikalibrasi === 'Y' && $r->Kode_Grade_Final !== null
                    ? (string) $r->Kode_Grade_Final
                    : ($peta[(int) $r->pid] ?? ''))
                ->countBy()
                ->map(fn ($jumlah, $kode) => (object) ['kode' => $kode === '' ? null : (string) $kode, 'jumlah' => $jumlah])
                ->values();
        } else {
            $baris = $this->dasar($cycleId, $saringan)
                ->groupBy('r.Kode_Grade_Final')
                ->selectRaw('r.Kode_Grade_Final as kode, COUNT(*) as jumlah')
                ->get();
        }

        /* Pembanding siklus KURVA adalah proporsi teoretis kurva normal dari batas z, bukan
           `Target_Persen` master pita — kolom itu milik metode PITA. */
        $teoretis = $metode === 'KURVA' ? $this->kurva->proporsiTeoretis($cycleId) : [];

        $total = (int) $baris->sum('jumlah');

        $pita = DB::table('N_PA_Cycle_Score_Range_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Score_Type', 'FINAL')
            ->where('Score_Usage', 'GRADE')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get();

        if ($pita->isEmpty()) {
            $pita = DB::table('N_PA_Score_Range')
                ->where('Score_Type', 'FINAL')
                ->where('Score_Usage', 'GRADE')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Tampil')
                ->get();
        }

        $pitaMap = $pita->keyBy('Kode_Range');

        return $baris
            ->sortBy(function ($r) use ($pitaMap) {
                $p = $pitaMap->get($r->kode);

                return $p ? (int) $p->Urutan_Tampil : 99;
            })
            ->map(function ($r) use ($total, $pitaMap, $metode, $teoretis) {
                $p = $pitaMap->get($r->kode);
                $persen = $total > 0 ? round((int) $r->jumlah / $total * 100, 1) : 0.0;
                $target = $metode === 'KURVA'
                    ? (isset($teoretis[$r->kode]) ? round($teoretis[$r->kode], 1) : null)
                    : ($p && $p->Target_Persen !== null ? round((float) $p->Target_Persen, 1) : null);
                $selisih = $target !== null ? round($persen - $target, 1) : null;

                return [
                    'kode_grade' => $r->kode ?? '(Tanpa grade)',
                    'nama_grade' => $p?->Nama_Range ?? null,
                    'jumlah' => (int) $r->jumlah,
                    'persen' => $persen,
                    'target_persen' => $target,
                    'selisih' => $selisih,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $saringan
     * @return list<array<string, mixed>>
     */
    private function sebaranKuadran(int $cycleId, array $saringan): array
    {
        $baris = $this->dasar($cycleId, $saringan)
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->groupBy('q.Id_PA_Hav_Quadrant', 'q.Kode_Kuadran', 'q.Nama_Kuadran', 'q.Nomor_Box')
            ->selectRaw(
                'q.Id_PA_Hav_Quadrant as id, q.Kode_Kuadran as kode, q.Nama_Kuadran as nama, '
                .'q.Nomor_Box as box, COUNT(*) as jumlah'
            )
            ->get();

        $total = (int) $baris->sum('jumlah');

        return $baris
            ->sortBy(fn ($r) => $r->box ?? 99)
            ->map(fn ($r) => [
                'id_kuadran' => $r->id === null ? null : (int) $r->id,
                // Hasil tanpa kuadran ikut dilaporkan, tidak dibuang. Ia berarti salah satu
                // pilar tertahan - keadaan yang justru perlu dilihat admin.
                'kode_kuadran' => $r->kode ?? '(Belum berkuadran)',
                'nama_kuadran' => $r->nama ?? 'Belum berkuadran',
                'nomor_box' => $r->box === null ? null : (int) $r->box,
                'jumlah' => (int) $r->jumlah,
                'persen' => $total > 0 ? round((int) $r->jumlah / $total * 100, 1) : 0.0,
            ])
            ->values()
            ->all();
    }

    /**
     * Histogram nilai akhir, dikelompokkan per {@see LEBAR_KELOMPOK}.
     *
     * Dikelompokkan di SQL supaya seluruh nilai tidak perlu dibaca ke memori — satu
     * siklus penuh berisi hampir seribu baris, dan halaman ini akan dibuka berulang kali.
     *
     * @param  array<string, mixed>  $saringan
     * @return list<array<string, mixed>>
     */
    private function histogram(int $cycleId, array $saringan): array
    {
        $lebar = self::LEBAR_KELOMPOK;

        return $this->dasar($cycleId, $saringan)
            ->groupByRaw("FLOOR(r.Final_Score / {$lebar})")
            ->selectRaw("FLOOR(r.Final_Score / {$lebar}) as kelompok, COUNT(*) as jumlah")
            ->orderByRaw("FLOOR(r.Final_Score / {$lebar})")
            ->get()
            ->map(fn ($r) => [
                'dari' => (float) ((int) $r->kelompok * $lebar),
                // Batas atas EKSKLUSIF, mengikuti pita nilai di modul ini. Batas yang
                // tumpang tindih membuat satu orang terhitung di dua kelompok.
                'sampai' => (float) (((int) $r->kelompok + 1) * $lebar),
                'jumlah' => (int) $r->jumlah,
            ])
            ->values()
            ->all();
    }
}
