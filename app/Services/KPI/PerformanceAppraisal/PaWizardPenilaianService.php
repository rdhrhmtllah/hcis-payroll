<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Wizard tugas penilaian: langkah per kategori penilai, lembar per langkah, dan pratinjau
 * Kalibrasi Penilai di tiap transisi (spec §5.3, §6).
 *
 * Lingkupnya SELALU `PaPesertaService::kueriPenugasan()` — siklus yang dapat diisi, penugasan
 * aktif milik penilai yang login, bukan yang dibatalkan. Tidak ada kueri lingkup kedua di
 * berkas ini, dan jangan ditambahkan.
 *
 * Kunci urutan berlaku di HCIS: langkah terkunci tidak membawa tautan maupun kode akses. Ia
 * tidak mengikat HC Learn — orang yang sudah memegang tautan tetap bisa mengisi lebih dulu,
 * dan kirimannya tetap diterima.
 */
class PaWizardPenilaianService
{
    public const LANGKAH_RINGKASAN = 'RINGKASAN';

    public const MAKS_LEMBAR = 200;

    /** Status yang dihitung "sudah dikerjakan" untuk membuka langkah berikutnya. */
    private const STATUS_TERISI = [
        PaStatusPenugasan::TERKUMPUL,
        PaStatusPenugasan::SELESAI,
        PaStatusPenugasan::MENUNGGU_VERIFIKASI,
    ];

    public function __construct(
        private readonly PaPesertaService $peserta,
        private readonly PaSkorEfektif $skor,
        private readonly PaCycleGate $gate,
    ) {}

    /** @return array<string, mixed> */
    public function wizard(int $userId, ?int $cycleId = null): array
    {
        $saya = $this->peserta->karyawan($userId);

        /* SEMUA siklus terbuka tempat saya punya tugas. Sistem tidak melarang dua siklus
           terbuka bersamaan; sebelumnya wizard hanya menampilkan yang id-nya terbesar, dan
           tugas di siklus lain tidak bisa dibuka maupun dikirim dari layar mana pun. */
        $tersedia = $this->peserta->kemajuanPengiriman($userId);

        /* Tanpa pilihan: siklus yang MASIH menuntut tindakan lebih dulu, bukan yang terbaru.
           Siklus yang sudah terkirim semua tidak perlu menyambut orang yang datang mengisi. */
        $cycleId ??= ($tersedia === [] ? null
            : (collect($tersedia)->first(fn ($k) => ! $k['sudah_terkirim_semua']) ?? $tersedia[0])['cycle_id']);

        $siklus = $this->siklus($saya, $cycleId);
        $daftarSiklus = array_map(fn ($k) => [
            'cycle_id' => $k['cycle_id'],
            'kode_cycle' => $k['kode_cycle'],
            'nama_cycle' => $k['nama_cycle'],
            'sudah_terkirim_semua' => $k['sudah_terkirim_semua'],
        ], $tersedia);

        if ($siklus === null) {
            return ['siklus' => null, 'siklus_tersedia' => $daftarSiklus, 'langkah' => [], 'langkah_aktif' => null, 'pengiriman' => null, 'boleh_kirim' => false];
        }

        $langkah = $this->langkah($saya, (int) $siklus->Id_PA_Cycle);
        $aktif = collect($langkah)->firstWhere('status', 'AKTIF');
        $pengiriman = $this->peserta->kemajuanPengiriman($userId, (int) $siklus->Id_PA_Cycle)[0] ?? null;

        return [
            'siklus' => $this->bentukSiklus($siklus),
            'siklus_tersedia' => $daftarSiklus,
            'langkah' => $langkah,
            // Ditentukan SERVER dari posisi, bukan ditebak layar dari kelengkapan isi.
            'langkah_aktif' => $aktif['kode_kategori'] ?? ($langkah === [] ? null : self::LANGKAH_RINGKASAN),
            'pengiriman' => $pengiriman,
            // Aturan "boleh kirim" milik PaRaterSubmitService; tidak ditulis ulang di sini.
            'boleh_kirim' => (bool) ($pengiriman['bisa_kirim'] ?? false),
        ];
    }

    /** @return array<string, mixed> */
    public function lembar(int $userId, string $kodeKategori, ?int $cycleId = null): array
    {
        $saya = $this->peserta->karyawan($userId);
        $siklus = $this->siklusAtauGagal($saya, $cycleId);
        $langkah = $this->langkahAtauGagal($saya, (int) $siklus->Id_PA_Cycle, $kodeKategori);
        $terkunci = $langkah['status'] === 'TERKUNCI';
        $simulasiLokal = ! app()->isProduction();

        $dasar = fn () => $this->peserta->kueriPenugasan($saya, (int) $siklus->Id_PA_Cycle)
            ->where('c.Kode_Kategori', $kodeKategori);

        $total = (int) $dasar()->distinct()->count('a.Id_PA_Assignment');

        $baris = $dasar()
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('a.Id_PA_Assignment')
            ->limit(self::MAKS_LEMBAR)
            ->get(PaPesertaService::kolomTugas())
            ->unique('Id_PA_Assignment')
            ->map(function ($b) use ($saya, $simulasiLokal, $terkunci) {
                $r = $this->peserta->barisTugas($b, $saya, $simulasiLokal);

                /* Bentuk yang DIKURASI, bukan baris mentah. Baris mentah membawa
                   Id_Ujian_Token, Link_Ujian, Short_Token, dan Akses_Otp apa adanya — dan
                   penguncian yang hanya mengosongkan versi olahannya akan membocorkan kodenya
                   lewat kolom mentah itu. */
                return [
                    'assignment_id' => (int) $r['Id_PA_Assignment'],
                    'status_penugasan' => (string) $r['Status_Penugasan'],
                    'peserta' => [
                        'kode_karyawan' => (string) $r['Kode_Karyawan'],
                        'nama' => (string) $r['Nama_Karyawan_Saat_Cycle'],
                        'jabatan' => $r['Nama_Jabatan_Saat_Cycle'],
                        'divisi' => $r['Nama_Divisi_Saat_Cycle'],
                    ],
                    'diri_sendiri' => (bool) $r['diri_sendiri'],
                    'tenggat' => $r['Due_At'] === null ? null : substr((string) $r['Due_At'], 0, 19),
                    'terlambat' => (bool) $r['terlambat'],
                    'hari_terlambat' => $r['hari_terlambat'],
                    'sisa_hari' => $r['sisa_hari'],
                    'url_ujian' => $terkunci ? null : $r['url_ujian'],
                    'short_token' => $terkunci ? null : $r['short_token'],
                    'akses_otp' => $terkunci ? null : $r['akses_otp'],
                    'status_pintu' => $r['status_pintu'],
                    'kiriman_tertahan' => $r['kiriman_tertahan'],
                ];
            })
            ->values()
            ->all();

        return [
            'siklus' => $this->bentukSiklus($siklus),
            'langkah' => $langkah,
            'lembar' => $baris,
            'total' => $total,
            'dipangkas' => $total > self::MAKS_LEMBAR,
        ];
    }

    /** @return array<string, mixed> */
    public function pratinjau(int $userId, string $kodeKategori, ?int $cycleId = null): array
    {
        $saya = $this->peserta->karyawan($userId);
        $siklus = $this->siklusAtauGagal($saya, $cycleId);
        $cycle = (int) $siklus->Id_PA_Cycle;
        $langkah = $this->langkahAtauGagal($saya, $cycle, $kodeKategori);
        $siklusTerbuka = $this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, $cycle);

        $dasar = fn () => $this->peserta->kueriPenugasan($saya, $cycle)->where('c.Kode_Kategori', $kodeKategori);
        $total = (int) $dasar()->distinct()->count('a.Id_PA_Assignment');

        $tugas = $dasar()
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('a.Id_PA_Assignment')
            ->limit(self::MAKS_LEMBAR)
            ->get(['a.Id_PA_Assignment', 'a.Status_Penugasan', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle', 'p.ID_Level_Saat_Cycle', 'p.Nama_Level_Saat_Cycle'])
            ->unique('Id_PA_Assignment')
            ->values();

        $ids = $tugas->pluck('Id_PA_Assignment')->map(fn ($x) => (int) $x)->all();
        $butir = $this->skor->butirBerskor($ids);
        $naratif = $this->skor->jawabanNaratif($ids);
        $terakhir = $this->kalibrasiTerakhir($ids);
        $meta = $this->metaKompetensi($butir, $naratif);

        // Rata-rata pada skala 0-100 dan HANYA atas orang yang memiliki kompetensi itu:
        // rata-rata skor mentah dari skala berbeda tidak bermakna.
        $normal = [];

        foreach ($butir as $list) {
            foreach ($list as $b) {
                if ($b->PA_Competency_Id !== null) {
                    $normal[$b->PA_Competency_Id][] = $b->Skor / $b->Skor_Maksimal * 100;
                }
            }
        }

        $kompetensi = $meta
            ->filter(fn ($m) => isset($normal[(int) $m->id]))
            ->map(fn ($m) => [
                'competency_id' => (int) $m->id,
                'kode' => (string) $m->kode,
                'nama' => (string) $m->nama,
                'jenis_kode' => (string) $m->jenis_kode,
                'jenis_nama' => (string) $m->jenis_nama,
                // Patokan yang dibaca rater saat mengkalibrasi: arti kompetensi dan perilaku
                // yang diharapkan. NULL = belum ditulis HC; layar tidak menampilkan apa-apa.
                'deskripsi' => $m->deskripsi,
                'expected_behaviour' => $m->expected_behaviour,
                'rata_rata' => round(array_sum($normal[(int) $m->id]) / count($normal[(int) $m->id]), 2),
                'jumlah_dinilai' => count($normal[(int) $m->id]),
            ])
            ->values()
            ->all();

        $namaKompetensi = $meta->keyBy('id');
        $bolehKategori = (bool) $langkah['boleh_kalibrasi'];

        /* PER LEVEL — layar kalibrasi membandingkan orang dalam SATU level per tab: orang beda
           level dituntut tingkat kompetensi berbeda, jadi menjajarkannya menyesatkan. Rata-rata
           dihitung ulang per level (skala 0-100), dan Expected Behaviour diambil dari tingkat
           yang dituntut untuk level itu (DDL-2026-175), cadangannya Expected Behaviour umum. */
        $levelPerTugas = $tugas->mapWithKeys(fn ($t) => [(int) $t->Id_PA_Assignment => $t->ID_Level_Saat_Cycle === null ? 0 : (int) $t->ID_Level_Saat_Cycle]);
        $normalLevel = [];
        foreach ($butir as $aid => $list) {
            foreach ($list as $b) {
                if ($b->PA_Competency_Id !== null) {
                    $normalLevel[$levelPerTugas[(int) $aid] ?? 0][(int) $b->PA_Competency_Id][] = $b->Skor / $b->Skor_Maksimal * 100;
                }
            }
        }
        $matriksKompetensi = app(PaKompetensiMatriksService::class);
        $level = $tugas->groupBy(fn ($t) => $t->ID_Level_Saat_Cycle === null ? 0 : (int) $t->ID_Level_Saat_Cycle)
            ->map(function ($g, $lv) use ($normalLevel, $matriksKompetensi, $cycle) {
                $perilaku = $lv === 0 ? [] : $matriksKompetensi->perilakuDituntut($cycle, (int) $lv);

                return [
                    'id_level' => (int) $lv,
                    'nama_level' => (string) ($g->first()->Nama_Level_Saat_Cycle ?? 'Tanpa level'),
                    'jumlah' => $g->count(),
                    // DAFTAR ber-competency_id, bukan objek berkunci id: bentuk respons tetap stabil.
                    'rata_rata' => collect($normalLevel[$lv] ?? [])
                        ->map(fn ($v, $cid) => ['competency_id' => (int) $cid, 'nilai' => round(array_sum($v) / count($v), 2)])
                        ->values()->all(),
                    'perilaku' => collect($perilaku)->map(fn ($p, $cid) => [
                        'competency_id' => (int) $cid,
                        'tingkat' => $p['tingkat'], 'nama_tingkat' => $p['nama_tingkat'], 'expected_behaviour' => $p['expected_behaviour'],
                    ])->values()->all(),
                ];
            })
            ->sortBy('id_level')
            ->values()
            ->all();

        $orang = $tugas->map(function ($t) use ($butir, $naratif, $terakhir, $namaKompetensi, $saya, $siklusTerbuka, $bolehKategori) {
            $aid = (int) $t->Id_PA_Assignment;
            $sel = collect($butir[$aid] ?? [])
                ->filter(fn ($b) => $b->PA_Competency_Id !== null)
                ->map(fn ($b) => [
                    'competency_id' => $b->PA_Competency_Id,
                    'skor_asli' => $b->Skor_Asli,
                    'skor_efektif' => $b->Skor,
                    'skor_maksimal' => $b->Skor_Maksimal,
                    'terkalibrasi' => $b->Terkalibrasi,
                ])
                ->values()
                ->all();

            return [
                'assignment_id' => $aid,
                'peserta' => [
                    'kode_karyawan' => (string) $t->Kode_Karyawan,
                    'nama' => (string) $t->Nama_Karyawan_Saat_Cycle,
                    'jabatan' => $t->Nama_Jabatan_Saat_Cycle,
                    'divisi' => $t->Nama_Divisi_Saat_Cycle,
                    'id_level' => $t->ID_Level_Saat_Cycle === null ? 0 : (int) $t->ID_Level_Saat_Cycle,
                    'nama_level' => $t->Nama_Level_Saat_Cycle,
                ],
                'diri_sendiri' => strtoupper(trim((string) $t->Kode_Karyawan)) === strtoupper(trim((string) $saya->Kode_Karyawan)),
                'status_penugasan' => (string) $t->Status_Penugasan,
                // Lembar yang hanya berisi esai pun bisa dikalibrasi (DDL-2026-119 r2).
                'boleh_diubah' => $bolehKategori && $siklusTerbuka
                    && $t->Status_Penugasan === PaStatusPenugasan::TERKUMPUL
                    && ($sel !== [] || ! empty($naratif[$aid])),
                'kalibrasi_terakhir' => $terakhir[$aid] ?? null,
                'sel' => $sel,
                'naratif' => collect($naratif[$aid] ?? [])->map(fn ($n) => [
                    'kode_soal' => $n['kode_soal'],
                    // Format per soal: layar menampilkan pertanyaan di atas jawabannya dan
                    // mengirim revisi per (kode_soal, id_master_soal).
                    'id_master_soal' => $n['id_master_soal'],
                    'pertanyaan' => $n['pertanyaan'],
                    'per_soal' => $n['per_soal'],
                    'competency_id' => $n['competency_id'],
                    'nama' => $n['competency_id'] === null ? null : ($namaKompetensi[$n['competency_id']]->nama ?? null),
                    'jawaban' => $n['jawaban'],
                    'jawaban_html' => $n['jawaban_html'],
                    'jawaban_asli' => $n['jawaban_asli'],
                    'terkalibrasi' => $n['terkalibrasi'],
                ])->values()->all(),
            ];
        })->values()->all();

        return [
            'siklus' => $this->bentukSiklus($siklus),
            'kategori' => [
                'kode' => $langkah['kode_kategori'],
                'nama' => $langkah['nama_kategori'],
                'boleh_kalibrasi' => $bolehKategori,
                'status' => $langkah['status'],
            ],
            'kompetensi' => $kompetensi,
            'level' => $level,
            'orang' => $orang,
            'dipangkas' => $total > self::MAKS_LEMBAR,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /** Siklus terbuka tempat saya punya tugas; `cycle_id` hanya mempersempit. */
    private function siklus(object $saya, ?int $cycleId): ?object
    {
        return $this->peserta->kueriPenugasan($saya, $cycleId)
            ->orderByDesc('k.Id_PA_Cycle')
            ->first(['k.Id_PA_Cycle', 'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun']);
    }

    private function siklusAtauGagal(object $saya, ?int $cycleId): object
    {
        return $this->siklus($saya, $cycleId) ?? throw new PaKalibrasiPenilaiException(
            'Tidak ada siklus terbuka yang memuat tugas penilaian Anda.',
            PaKalibrasiPenilaiException::LANGKAH_TIDAK_DIKENAL,
            404,
        );
    }

    /** @return array<string, mixed> */
    private function langkahAtauGagal(object $saya, int $cycleId, string $kode): array
    {
        return collect($this->langkah($saya, $cycleId))->firstWhere('kode_kategori', $kode)
            ?? throw new PaKalibrasiPenilaiException(
                "Anda tidak punya tugas penilaian kategori {$kode} pada siklus ini.",
                PaKalibrasiPenilaiException::LANGKAH_TIDAK_DIKENAL,
                404,
            );
    }

    /** @return list<array<string, mixed>> */
    private function langkah(object $saya, int $cycleId): array
    {
        $tugas = $this->peserta->kueriPenugasan($saya, $cycleId)
            ->get(['a.Id_PA_Assignment', 'a.Status_Penugasan', 'c.Id_PA_Rater_Category', 'c.Kode_Kategori',
                'c.Nama_Kategori', 'c.Urutan_Pengisian', 'c.Flag_Boleh_Kalibrasi'])
            ->unique('Id_PA_Assignment');

        $terkalibrasi = $this->penugasanTerkalibrasi($tugas->pluck('Id_PA_Assignment')->map(fn ($x) => (int) $x)->all());

        $kelompok = $tugas->groupBy('Kode_Kategori')
            ->map(function (Collection $baris) use ($terkalibrasi) {
                $k = $baris->first();

                return [
                    'id' => (int) $k->Id_PA_Rater_Category,
                    'urutan' => $k->Urutan_Pengisian === null ? null : (int) $k->Urutan_Pengisian,
                    'kode_kategori' => (string) $k->Kode_Kategori,
                    'nama_kategori' => PaPesertaService::namaKategoriPenilai($k->Kode_Kategori, $k->Nama_Kategori),
                    'boleh_kalibrasi' => $k->Flag_Boleh_Kalibrasi === 'Y',
                    'jumlah_lembar' => $baris->count(),
                    'jumlah_terisi' => $baris->whereIn('Status_Penugasan', self::STATUS_TERISI)->count(),
                    'jumlah_terkalibrasi' => $baris->filter(fn ($b) => isset($terkalibrasi[(int) $b->Id_PA_Assignment]))->count(),
                ];
            })
            ->sort(fn ($a, $b) => [$a['urutan'] ?? PHP_INT_MAX, $a['id']] <=> [$b['urutan'] ?? PHP_INT_MAX, $b['id']])
            ->values();

        $sebelumnyaTuntas = true;
        $penghambat = null;
        $hasil = [];

        foreach ($kelompok as $i => $l) {
            $tuntas = $l['jumlah_terisi'] >= $l['jumlah_lembar'];

            $status = ! $sebelumnyaTuntas ? 'TERKUNCI' : ($tuntas ? 'TUNTAS' : 'AKTIF');
            $sebab = $status === 'TERKUNCI' ? "Selesaikan penilaian {$penghambat} lebih dulu." : null;

            if ($sebelumnyaTuntas && ! $tuntas) {
                $penghambat = $l['nama_kategori'];
            }

            $sebelumnyaTuntas = $sebelumnyaTuntas && $tuntas;
            unset($l['id']);

            $hasil[] = $l + ['langkah_ke' => $i + 1, 'status' => $status, 'sebab_terkunci' => $sebab];
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private function penugasanTerkalibrasi(array $ids): array
    {
        $hasil = [];

        foreach (array_chunk($ids, 500) as $potongan) {
            foreach (DB::table('N_PA_Kalibrasi_Penilai')->whereIn('PA_Assignment_Id', $potongan)
                ->distinct()->pluck('PA_Assignment_Id') as $id) {
                $hasil[(int) $id] = true;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{alasan:string, pada:string}>
     */
    private function kalibrasiTerakhir(array $ids): array
    {
        $hasil = [];

        foreach (array_chunk($ids, 500) as $potongan) {
            foreach (DB::table('N_PA_Kalibrasi_Penilai')->whereIn('PA_Assignment_Id', $potongan)
                ->orderByDesc('Id_PA_Kalibrasi_Penilai')
                ->get(['PA_Assignment_Id', 'Alasan', 'Created_At']) as $k) {
                $hasil[(int) $k->PA_Assignment_Id] ??= [
                    'alasan' => (string) $k->Alasan,
                    'pada' => substr((string) $k->Created_At, 0, 19),
                ];
            }
        }

        return $hasil;
    }

    /**
     * Nama dan jenis seluruh kompetensi yang muncul, diurut seperti layar pelabelan paket
     * (jenis lalu kode), supaya kolom matriks punya urutan yang sama dengan yang dikenal admin.
     *
     * @param  array<int, Collection<int, object>>  $butir
     * @param  array<int, list<array{kode_soal:string, competency_id:?int, jawaban:string}>>  $naratif
     */
    private function metaKompetensi(array $butir, array $naratif): Collection
    {
        $ids = collect($butir)->flatten(1)->pluck('PA_Competency_Id')
            ->merge(collect($naratif)->flatten(1)->pluck('competency_id'))
            ->filter()
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return DB::table('N_PA_Competency as c')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
            ->whereIn('c.Id_PA_Competency', $ids)
            ->orderBy('t.Kode_Type')
            ->orderBy('c.Kode_Competency')
            ->get(['c.Id_PA_Competency as id', 'c.Kode_Competency as kode', 'c.Nama_Competency as nama',
                // Expected Behaviour umum kompetensi tidak dipakai lagi (10 Okt 2026): perilaku yang
                // tampil hanya dari tingkat yang dituntut tiap level (lihat `level[].perilaku`).
                'c.Deskripsi as deskripsi', DB::raw('CAST(NULL AS NVARCHAR(4000)) as expected_behaviour'),
                't.Kode_Type as jenis_kode', 't.Nama_Type as jenis_nama']);
    }

    /** @return array{cycle_id:int, kode_cycle:string, nama_cycle:string, tahun:int} */
    private function bentukSiklus(object $s): array
    {
        return [
            'cycle_id' => (int) $s->Id_PA_Cycle,
            'kode_cycle' => (string) $s->Kode_Cycle,
            'nama_cycle' => (string) $s->Nama_Cycle,
            'tahun' => (int) $s->Tahun,
        ];
    }
}
