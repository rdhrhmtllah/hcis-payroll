<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use App\Services\KPI\PerformanceAppraisal\PaRaterSubmitService;
use App\Services\KPI\PerformanceAppraisal\PaStatusPenugasan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * [DEV/UAT] Mengisi lembar penilaian yang BELUM dikerjakan dengan jawaban karangan, lalu
 * menekan "kirim semua" atas nama penilainya.
 *
 * KENAPA ADA
 *
 * Satu lembar 360 berisi 65 soal; satu siklus UAT berisi ratusan lembar. Mengisi semuanya
 * dengan tangan tidak menguji apa pun yang belum teruji oleh beberapa lembar pertama. Jadi:
 * tester mengisi beberapa lembar ASLI lewat HC Learn (membuktikan jalur login → isi →
 * callback → TERKUMPUL → kirim), dan perintah ini mengisi sisanya supaya separuh hilir —
 * perhitungan, kurva, HAV Map, Balancing — punya data untuk diuji.
 *
 * BEDANYA DENGAN `pa:simulasi-hclearn`
 *
 * Yang itu hanya menyentuh token simulasi `SIM-PA-`. Siklus UAT dijadwalkan ke HC Learn
 * sungguhan, sehingga barisnya dikenali lewat `Api_Ref`. Perintah ini memakai pengenal yang
 * sama dengan callback asli, dan bentuk muatannya disalin dari kiriman HC Learn yang nyata
 * (8 Okt 2026), lewat `PaPayloadMapperService::terimaDanPetakan()` yang sama.
 *
 * YANG DIJAGA
 *
 *  - Menolak berjalan di production, tanpa bergantung setelan apa pun.
 *  - Lembar yang SUDAH dikerjakan (TERKUMPUL, MENUNGGU_VERIFIKASI, SELESAI) tidak disentuh,
 *    jadi isian tester tidak pernah tertimpa.
 *  - `--kecuali-penilai` / `--kecuali-peserta` melindungi lembar yang masih akan diisi tangan.
 *  - Tanpa `--jalankan` hanya pratinjau.
 *
 * Di sisi HC Learn lembar-lembar ini tetap tampak belum dikerjakan — yang diuji adalah alur
 * HCIS sesudah nilai masuk, bukan HC Learn.
 */
class PaUatIsiOtomatisCommand extends Command
{
    protected $signature = 'pa:uat-isi-otomatis
        {cycle : Id siklus}
        {--kecuali-penilai= : Kode karyawan penilai, dipisah koma — lembar mereka tidak diisi dan tidak dikirimkan}
        {--hanya-penilai= : Kode karyawan penilai, dipisah koma — hanya lembar mereka yang diisi}
        {--kecuali-peserta= : Kode karyawan peserta (yang dinilai), dipisah koma — seluruh lembar tentang mereka tidak diisi}
        {--batas=0 : Isi paling banyak N lembar (0 = semua) — untuk uji coba}
        {--esai=K14 : Kode soal yang di HC Learn berupa esai (dipisah koma) — dikirim Flag_Essai beserta teks jawaban}
        {--tanpa-kirim : Hanya mengisi (TERKUMPUL); "kirim semua" dibiarkan untuk ditekan penilainya}
        {--jalankan : Tanpa ini hanya pratinjau}
        {--seed=2026 : Benih acak, supaya nilainya bisa diulang persis}';

    protected $description = '[DEV/UAT] Mengisi lembar PA yang belum dikerjakan dengan jawaban karangan lewat jalur callback HC Learn';

    /** Profil peserta: [peluang %, skor bawah, skor atas]. Sebaran sengaja tidak seragam. */
    private const PROFIL = [
        'TINGGI' => [25, 82, 97],
        'SEDANG' => [50, 66, 84],
        'RENDAH' => [25, 45, 67],
    ];

    /** Kecenderungan per jenis penilai — penilaian diri biasanya lebih murah hati. */
    private const BIAS_KATEGORI = ['SELF' => 6, 'ATASAN' => 0, 'BAWAHAN' => 2, 'PEER' => -2];

    private const ESAI = [
        'TINGGI' => [
            'Sangat konsisten menyelesaikan pekerjaan tepat waktu dan sering membantu rekan yang kesulitan.',
            'Mampu mengambil keputusan cepat dengan data yang cukup; menjadi rujukan tim untuk masalah teknis.',
            'Inisiatif tinggi dalam perbaikan proses, komunikasi jelas ke atasan maupun bawahan.',
        ],
        'SEDANG' => [
            'Pekerjaan rutin diselesaikan dengan baik; perlu lebih proaktif menyampaikan kendala sejak awal.',
            'Kerja sama tim baik, namun ketelitian pada laporan masih perlu ditingkatkan.',
            'Cukup menguasai bidangnya; disarankan memperdalam analisis data dan manajemen waktu.',
        ],
        'RENDAH' => [
            'Sering terlambat menyelesaikan target; perlu pendampingan dalam menyusun prioritas kerja.',
            'Komunikasi dengan tim belum efektif; perlu pelatihan dasar pemecahan masalah.',
            'Kualitas hasil kerja belum stabil; disarankan mengikuti program pengembangan kompetensi inti.',
        ],
    ];

    /** @var array<string, array{pertanyaan:string, id:?int}> soal esai asli HC Learn per kode soal */
    private array $soalAsli = [];

    public function handle(PaPayloadMapperService $pemeta, PaRaterSubmitService $kirim): int
    {
        if (app()->isProduction()) {
            $this->error('DITOLAK: perintah ini mengarang nilai penilaian dan tidak boleh berjalan di production.');

            return self::FAILURE;
        }

        $cycleId = (int) $this->argument('cycle');
        $kecualiPenilai = $this->daftarKode('kecuali-penilai');
        $kecualiPeserta = $this->daftarKode('kecuali-peserta');
        $jalankan = (bool) $this->option('jalankan');
        mt_srand((int) $this->option('seed'));

        $status = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Status_Cycle');

        if ($status === null) {
            $this->error("Siklus {$cycleId} tidak ada.");

            return self::FAILURE;
        }

        $q = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Rater_Category as k', 'k.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', '=', 'Y');
            })
            ->leftJoin('N_PA_Cycle_Question_Package as pk', 'pk.Id_PA_Cycle_Question_Package', '=', 'i.PA_Cycle_Question_Package_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('a.Flag_Aktif', 'Y')
            ->whereNotIn('a.Status_Penugasan', [...PaStatusPenugasan::SUDAH_DIKERJAKAN, 'DIBATALKAN'])
            ->whereIn('i.Status_Integrasi', ['BERHASIL', 'SUDAH_ADA'])
            ->whereNotNull('i.Api_Ref');

        if ($kecualiPenilai !== []) {
            $q->whereNotIn('a.Kode_Karyawan_Penilai', $kecualiPenilai);
        }

        if ($kecualiPeserta !== []) {
            $q->whereNotIn('p.Kode_Karyawan', $kecualiPeserta);
        }

        if (($hanya = $this->daftarKode('hanya-penilai')) !== []) {
            $q->whereIn('a.Kode_Karyawan_Penilai', $hanya);
        }

        if ((int) $this->option('batas') > 0) {
            $q->limit((int) $this->option('batas'));
        }

        $lembar = $q->orderBy('a.Id_PA_Assignment')->get([
            'a.Id_PA_Assignment as id',
            'a.PA_Participant_Id as peserta_id',
            'a.Kode_Perusahaan_Penilai as pt_penilai',
            'a.Kode_Karyawan_Penilai as penilai',
            'k.Kode_Kategori as kategori',
            'i.Id_PA_Assignment_Integration as integrasi_id',
            'i.Api_Ref as api_ref',
            'i.PA_Cycle_Question_Package_Id as paket_id',
            'i.Waktu_Mulai as waktu_mulai',
            'i.Waktu_Akhir as waktu_akhir',
            'pk.Nama_Ujian_Sumber as nama_tes',
        ]);

        $perKategori = $lembar->countBy('kategori')->all();
        $penilaiTerlibat = $lembar->pluck('penilai')->map(fn ($k) => trim((string) $k))->unique()->values();

        $this->info(sprintf('Siklus %d (%s): %d lembar akan diisi, %d penilai.', $cycleId, $status, $lembar->count(), $penilaiTerlibat->count()));
        foreach ($perKategori as $kat => $n) {
            $this->line("  {$kat}: {$n}");
        }
        if ($kecualiPenilai !== []) {
            $this->line('  dikecualikan (penilai): '.implode(', ', $kecualiPenilai));
        }
        if ($kecualiPeserta !== []) {
            $this->line('  dikecualikan (peserta): '.implode(', ', $kecualiPeserta));
        }

        if (! $jalankan) {
            $this->warn('Pratinjau saja. Tambahkan --jalankan untuk mengisi.');

            return self::SUCCESS;
        }

        if ($status !== 'OPEN') {
            $this->error("Siklus berstatus {$status}; kiriman hanya diterima saat OPEN.");

            return self::FAILURE;
        }

        $butir = [];
        $profil = [];
        $berhasil = 0;
        $gagal = 0;

        $bar = $this->output->createProgressBar($lembar->count());
        $bar->start();

        foreach ($lembar as $t) {
            $paketId = (int) $t->paket_id;
            $butir[$paketId] ??= DB::table('N_PA_Cycle_Question_Package_Item')
                ->where('PA_Cycle_Question_Package_Id', $paketId)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Tampil')
                ->get(['Kode_Soal_Sumber', 'Nama_Bagian_Sumber', 'Nama_Alat_Tes', 'Jenis_Soal', 'Skor_Maksimal', 'Bobot_Penilaian', 'PA_Result_Note_Type_Id'])
                ->all();

            $profil[$t->peserta_id] ??= $this->pilihProfil();

            try {
                $muatan = $this->muatan($t, $butir[$paketId], $profil[$t->peserta_id], $this->daftarKode('esai'));
                $pemeta->terimaDanPetakan((string) $t->api_ref, $muatan, null);
                $berhasil++;
            } catch (Throwable $e) {
                $gagal++;

                if ($gagal <= 5) {
                    $this->newLine();
                    $this->error("  Penugasan {$t->id}: ".$e->getMessage());
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Terisi {$berhasil} lembar, gagal {$gagal}.");
        $this->line('  Profil peserta: '.json_encode(array_count_values($profil)));

        if ($this->option('tanpa-kirim')) {
            $this->warn('"Kirim semua" tidak ditekan (--tanpa-kirim); lembar berhenti di TERKUMPUL.');

            return $gagal > 0 ? self::FAILURE : self::SUCCESS;
        }

        $terkirim = 0;
        $tertahan = [];

        foreach ($lembar->unique(fn ($t) => $t->pt_penilai.'|'.trim((string) $t->penilai)) as $t) {
            try {
                $kirim->kirimSemua((string) $t->pt_penilai, trim((string) $t->penilai), $cycleId, null);
                $terkirim++;
            } catch (Throwable $e) {
                $tertahan[] = trim((string) $t->penilai).': '.$e->getMessage();
            }
        }

        $this->info("\"Kirim semua\" ditekan untuk {$terkirim} penilai.");

        if ($tertahan !== []) {
            $this->warn(count($tertahan).' penilai belum bisa dikirim (biasanya masih punya lembar manual yang belum diisi):');
            foreach (array_slice($tertahan, 0, 10) as $baris) {
                $this->line('  '.$baris);
            }
        }

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Soal esai ASLI dari kiriman HC Learn sungguhan untuk kode yang sama: bunyinya dan
     * `Id_Master_Soal`-nya. Tanpa id, esai per soal tidak bisa ditunjuk saat Kalibrasi Penilai
     * dan layar menguncinya — kiriman simulasi lalu tidak bisa dipakai menguji revisi esai.
     * Bila belum pernah ada kiriman asli, keduanya kosong (perilaku lama).
     *
     * @return array{pertanyaan:string, id:?int}
     */
    private function soalAsli(string $kode): array
    {
        return $this->soalAsli[$kode] ??= (function () use ($kode) {
            $r = DB::table('N_PA_Assignment_Score_Esai as e')
                ->join('N_PA_Assignment_Score as s', 's.Id_PA_Assignment_Score', '=', 'e.PA_Assignment_Score_Id')
                ->join('N_PA_Assignment_Payload as pl', 'pl.Id_PA_Assignment_Payload', '=', 's.PA_Assignment_Payload_Id')
                ->where('s.Kode_Soal_Sumber', $kode)
                ->where('pl.Id_Ujian_Nilai_Akhir', 'not like', 'UATSIM-%')
                ->whereNotNull('e.Pertanyaan')
                ->orderByDesc('e.Id_PA_Assignment_Score_Esai')
                ->first(['e.Pertanyaan', 'e.Id_Master_Soal']);

            return [
                'pertanyaan' => (string) ($r->Pertanyaan ?? ''),
                'id' => $r?->Id_Master_Soal === null ? null : (int) $r->Id_Master_Soal,
            ];
        })();
    }

    /** @return list<string> */
    private function daftarKode(string $opsi): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->option($opsi)))));
    }

    private function pilihProfil(): string
    {
        $angka = mt_rand(1, 100);
        $batas = 0;

        foreach (self::PROFIL as $nama => [$peluang]) {
            $batas += $peluang;

            if ($angka <= $batas) {
                return $nama;
            }
        }

        return 'SEDANG';
    }

    /**
     * Muatan berbentuk kiriman HC Learn yang nyata.
     *
     * @param  list<object>  $butir
     * @param  list<string>  $kodeEsai
     * @return array<string, mixed>
     */
    private function muatan(object $t, array $butir, string $profil, array $kodeEsai = []): array
    {
        [, $bawah, $atas] = self::PROFIL[$profil];
        $bias = self::BIAS_KATEGORI[strtoupper((string) $t->kategori)] ?? 0;
        $dasar = mt_rand($bawah, $atas) + $bias;

        $detail = [];
        $total = 0.0;
        $totalBobot = 0.0;

        foreach ($butir as $b) {
            $kode = trim((string) ($b->Kode_Soal_Sumber ?? ''));

            if ($kode === '') {
                continue;
            }

            $maks = $b->Skor_Maksimal !== null && (float) $b->Skor_Maksimal > 0 ? (float) $b->Skor_Maksimal : null;
            $esai = $maks === null && (in_array(strtolower((string) $b->Jenis_Soal), ['teks', 'uraian'], true) || $b->PA_Result_Note_Type_Id !== null);

            /* Esai BERSKOR, persis kiriman HC Learn untuk K14 "Mutual Growth" ujian 100164:
               Flag_Essai Y, satu jawaban teks, dan skor koreksi mesin di skala 100. */
            $esaiBerskor = ! $esai && in_array($kode, $kodeEsai, true);

            $satu = [
                'Kode_Soal' => $kode,
                'Kode_Alat_Tes' => 'uat-sim',
                'Nama_Alat_Tes' => $b->Nama_Alat_Tes,
                'Jenis_Soal' => $b->Jenis_Soal,
                'Bobot_Penilaian' => $b->Bobot_Penilaian,
                'Keterangan' => $b->Nama_Bagian_Sumber,
                'Jumlah_Soal' => 5,
                'Jumlah_Terjawab' => 5,
            ];

            if ($esai) {
                $pilihan = self::ESAI[$profil];
                $satu += [
                    'Skor' => null,
                    'Skor_Maksimal' => null,
                    'Jawaban_Teks' => null,
                    'Flag_Essai' => true,
                    'Jumlah_Esai' => 1,
                    'Esai' => [[
                        'Id_Master_Soal' => $this->soalAsli($kode)['id'],
                        'Pertanyaan' => $this->soalAsli($kode)['pertanyaan'] ?: $b->Nama_Bagian_Sumber,
                        'Jawaban_Peserta' => $pilihan[mt_rand(0, count($pilihan) - 1)],
                        'Waktu_Dijawab' => now()->format('Y-m-d H:i:s'),
                    ]],
                ];
            } else {
                $skala = $maks ?? 100.0;
                if ($esaiBerskor) {
                    $pilihan = self::ESAI[$profil];
                    $satu += [
                        'Flag_Essai' => true,
                        'Jumlah_Esai' => 1,
                        'Esai' => [[
                            'Id_Master_Soal' => $this->soalAsli($kode)['id'],
                            'Pertanyaan' => $this->soalAsli($kode)['pertanyaan'] ?: $b->Nama_Bagian_Sumber,
                            'Jawaban_Peserta' => $pilihan[mt_rand(0, count($pilihan) - 1)],
                            'Waktu_Dijawab' => now()->format('Y-m-d H:i:s'),
                        ]],
                    ];
                }
                $skor = max(1, min(100, $dasar + mt_rand(-8, 8)));
                $nilai = round($skala * $skor / 100, 2);
                $satu += [
                    'Skor' => $nilai,
                    'Skor_Maksimal' => $skala,
                    'Jawaban_Teks' => null,
                    'Flag_Essai' => false,
                    'Jumlah_Esai' => 0,
                    'Esai' => [],
                ];
                $bobot = (float) ($b->Bobot_Penilaian ?? 1);
                $total += ($nilai / $skala * 100) * $bobot;
                $totalBobot += $bobot;
            }

            $detail[] = $satu;
        }

        $selesai = now();
        $mulai = $selesai->copy()->subMinutes(mt_rand(15, 45));

        return [
            'Kode_Karyawan' => trim((string) $t->penilai),
            'Id_Integrasi' => (int) $t->integrasi_id,
            'Id_Ujian_Token' => (string) $t->api_ref,
            'Id_Ujian_Nilai_Akhir' => 'UATSIM-'.$t->integrasi_id,
            'Status_Skor' => 'FINAL',
            'is_multi' => false,
            'kode_akses' => 'uat-sim',
            'penilaian' => [
                'Nama_Tes' => $t->nama_tes,
                'Waktu_Mulai' => $t->waktu_mulai,
                'Waktu_Akhir' => $t->waktu_akhir,
                'Waktu_Mulai_Akses' => $mulai->format('Y-m-d H:i:s'),
                'Waktu_Selesai_Akses' => $selesai->format('Y-m-d H:i:s'),
                'Ambang_Batas_Nilai' => 0,
                'Status_Kelulusan' => 'PASS',
                'Warna' => '#2ecc71',
            ],
            'Total_Nilai' => $totalBobot > 0 ? round($total / $totalBobot, 2) : 0,
            'Detail' => $detail,
        ];
    }
}
