<?php

namespace App\Services\KPI\PerformanceAppraisal\Kurva;

use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaResultHistoryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kurva grade per grup level, dikunci per siklus (spec 2026-09-18 grade kurva normal).
 *
 * SEBELUM KUNCI grade hanya dihitung untuk DIBACA (pratinjau), tidak pernah disimpan: grade
 * seseorang bergantung pada semua orang di grupnya, jadi grade yang disimpan sebelum semua
 * hasil masuk hanyalah grade basi yang menunggu dipakai.
 *
 * SESUDAH KUNCI μ, σ, dan pita absolut tiap grup dibekukan di N_PA_Cycle_Curve(_Band). Hasil
 * yang dihitung sesudahnya memakai pita beku dan tidak menggeser kurva siapa pun.
 */
class PaKurvaService
{
    public const MIN_GRUP = 30;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaCyclePreparationService $penyiapan,
        private readonly PaGrupKurvaMasterService $grupMaster,
        private readonly PaResultHistoryService $riwayat,
    ) {}

    /** Kolomnya char(5): 'PITA' terbaca 'PITA ' — dipangkas supaya perbandingan tidak bergantung panjang kode. */
    public function metode(int $cycleId): string
    {
        return trim((string) (DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->value('Metode_Grade') ?? 'PITA'));
    }

    public function terkunci(int $cycleId): bool
    {
        return DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->exists();
    }

    public function pastikanTerkunci(int $cycleId): void
    {
        if ($this->metode($cycleId) === 'KURVA' && ! $this->terkunci($cycleId)) {
            throw new RuntimeException(
                'Kurva grade siklus ini belum dikunci. Kunci kurva lebih dulu di tahap Kurva (Monitoring Cycle) — '
                .'sebelum itu grade setiap orang masih bisa bergeser saat hasil lain masuk.'
            );
        }
    }

    /** @return array<string, mixed> */
    public function pratinjau(int $cycleId): array
    {
        $metode = $this->metode($cycleId);
        $terkunci = $this->terkunci($cycleId);
        $gradeDef = $this->gradeSiklus($cycleId);
        $grup = $this->grupSiklus($cycleId);
        $hasil = $this->hasilDihitung($cycleId);
        $beku = $terkunci ? $this->kurvaAktif($cycleId) : collect();
        $daftar = [];

        foreach ($grup as $g) {
            $nilai = $hasil->where('grup_id', $g['id'])->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all();
            $kurva = $beku->get($g['id']);
            $tanpaKurva = $terkunci && $kurva === null;

            if ($kurva !== null) {
                $stat = ['n' => (int) $kurva->Jumlah_Peserta, 'rata' => $kurva->Rata_Rata === null ? null : (float) $kurva->Rata_Rata,
                    'sd' => $kurva->Simpangan_Baku === null ? null : (float) $kurva->Simpangan_Baku];
                $pita = $this->pitaBeku((int) $kurva->Id_PA_Cycle_Curve);
            } elseif ($tanpaKurva) {
                /* Grup yang kosong saat dikunci tidak mendapat kurva — kurva dari nol orang akan
                   memberi siapa pun yang menyusul grade tengah secara otomatis. Tanpa pita di sini;
                   hasil yang menyusul TERTAHAN sampai kurva dibuka dan dikunci ulang. */
                $stat = $this->statBeku($nilai);
                $pita = [];
            } else {
                $stat = $this->statBeku($nilai);
                $pita = $gradeDef === [] ? [] : PaKurvaStatistik::pita($gradeDef, $stat['rata'], $stat['sd']);
            }

            $sebaran = array_fill_keys(array_column($pita, 'kode'), 0);

            foreach ($nilai as $v) {
                $k = PaKurvaStatistik::gradeUntuk($pita, $v);

                if ($k !== null) {
                    $sebaran[$k]++;
                }
            }

            $daftar[] = $g + [
                'n' => $stat['n'], 'rata' => $stat['rata'], 'sd' => $stat['sd'],
                'pita' => $pita, 'sebaran' => $sebaran,
                'peringatan' => $tanpaKurva
                    ? ["Grup {$g['kode']} kosong saat kurva dikunci dan tidak punya kurva beku; hasil yang menyusul TERTAHAN sampai kurva dibuka lalu dikunci ulang."]
                    : $this->peringatanGrup($g, $stat),
            ];
        }

        $kunci = $terkunci ? DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->orderBy('Id_PA_Cycle_Curve')->first(['Locked_At', 'Locked_By']) : null;

        $peringatan = [];

        if ($metode === 'KURVA' && $gradeDef === []) {
            $peringatan[] = 'Batas z grade belum diatur di salinan siklus ini.';
        }

        ['tertahan' => $tertahan, 'belum' => $belum] = $this->cacahBelumBerangka($cycleId);

        if ($tertahan > 0) {
            $peringatan[] = "{$tertahan} hasil masih TERTAHAN dan tidak ikut membentuk kurva.";
        }

        /* Yang belum dihitung sama nasibnya dengan yang tertahan: tidak ikut membentuk kurva,
           lalu mendapat grade dari kurva beku tanpa pernah menentukannya. */
        if ($belum > 0) {
            $peringatan[] = "{$belum} hasil belum dihitung dan tidak ikut membentuk kurva; sesudah dikunci mereka mendapat grade dari kurva beku.";
        }

        /* Level tanpa grup membuat kunci() menolak. Disebut di sini dan tombolnya dimatikan,
           bukan dibiarkan aktif lalu menjawab galat sesudah diklik. */
        $levelTanpaGrup = $terkunci ? [] : $this->levelTanpaGrup($cycleId, $hasil);

        if ($levelTanpaGrup !== []) {
            $peringatan[] = 'Level '.implode(', ', $levelTanpaGrup).' belum masuk grup kurva. Atur grup level lebih dulu sebelum mengunci.';
        }

        /*
         | SYARAT KUNCI DISEBUT SATU PER SATU, BUKAN SATU BOOLEAN BISU.
         |
         | Sampai 8 Oktober 2026 `boleh_kunci` hanya memeriksa gerbang dan level tanpa grup,
         | sementara `kunci()` juga menolak batas z kosong dan hasil kosong — tombolnya menyala
         | lalu ditolak — dan layar selalu menjelaskan penolakan apa pun sebagai "harus CLOSED".
         | Kini sebabnya dikirim apa adanya di `alasan_kunci`.
         |
         | Hasil yang belum dihitung/tertahan BUKAN penghalang mutlak: peserta yang tertahan
         | permanen (mis. tanpa akun KPI) tidak bisa dikeluarkan sesudah CLOSED, dan menolak
         | mutlak berarti siklusnya tak pernah bisa terbit. Ia penghalang yang bisa dilewati
         | DENGAN ALASAN tertulis (`boleh_kunci_dengan_alasan`) — keputusan, bukan kelalaian.
         */
        $penghalang = $this->penghalangKunci($cycleId, $metode, $terkunci, $gradeDef, $hasil, $levelTanpaGrup);
        $belumLengkap = $this->kalimatPerluAlasan($belum, $tertahan, $terkunci ? [] : $this->grupDatar($cycleId, $hasil));

        return [
            'metode' => $metode,
            'terkunci' => $terkunci,
            'boleh_kunci' => $penghalang === [] && $belumLengkap === null,
            'boleh_kunci_dengan_alasan' => $penghalang === [] && $belumLengkap !== null,
            'alasan_kunci' => $penghalang !== [] ? implode(' ', $penghalang) : $belumLengkap,
            'kunci' => $kunci === null ? null : ['pada' => substr((string) $kunci->Locked_At, 0, 19), 'oleh' => $kunci->Locked_By === null ? null : (int) $kunci->Locked_By],
            'grup' => $daftar,
            'peringatan' => $peringatan,
            'hasil_sesudah_kunci' => $kunci === null ? 0 : (int) DB::table('N_PA_Result as r')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')
                ->where('r.Calculated_At', '>', $kunci->Locked_At)->count(),
            'sudah_terbit' => $this->jumlahTerbit($cycleId),
            'sudah_dirilis' => $this->jumlahDirilisLeader($cycleId),
            'tertahan' => $tertahan,
            'belum_dihitung' => $belum,
            'level_tanpa_grup' => $levelTanpaGrup,
        ];
    }

    /**
     * @param  string|null  $alasanBelumLengkap  Wajib (5–500 karakter) bila masih ada hasil
     *                                           peserta DIIKUTKAN yang belum dihitung/tertahan.
     * @return array<string, mixed>
     */
    public function kunci(int $cycleId, ?int $olehUserId, ?string $alasanBelumLengkap = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::KUNCI_KURVA, $cycleId);

        if ($this->metode($cycleId) !== 'KURVA') {
            throw new RuntimeException('Siklus ini memakai pita nilai tetap (PITA). Pindahkan ke metode kurva lebih dulu.');
        }

        $alasanBelumLengkap = trim((string) $alasanBelumLengkap);

        DB::transaction(function () use ($cycleId, $olehUserId, $alasanBelumLengkap) {
            // Dikunci barisnya supaya dua admin yang menekan bersamaan tidak membuat dua kurva.
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->lockForUpdate()->first(['Id_PA_Cycle']);

            if ($this->terkunci($cycleId)) {
                throw new RuntimeException('Kurva siklus ini sudah dikunci.');
            }

            $gradeDef = $this->gradeSiklus($cycleId);

            if ($gradeDef === []) {
                throw new RuntimeException('Batas z grade belum diatur di salinan siklus ini.');
            }

            $hasil = $this->hasilDihitung($cycleId);
            $kurvaBaru = [];

            if ($hasil->isEmpty()) {
                throw new RuntimeException('Belum ada hasil DIHITUNG untuk membentuk kurva. Hitung hasil lebih dulu.');
            }

            /* Kurva yang dikunci selagi sebagian besar hasil belum masuk membekukan μ dan σ dari
               segelintir orang, lalu sisanya menerima grade dari kurva yang tidak mereka bentuk.
               Itu boleh — tetapi hanya bila diputuskan dengan sadar, dan keputusannya tercatat
               di riwayat setiap hasil yang dikunci. */
            ['tertahan' => $tertahan, 'belum' => $belum] = $this->cacahBelumBerangka($cycleId);
            $datar = $this->grupDatar($cycleId, $hasil);
            $belumLengkap = $this->kalimatPerluAlasan($belum, $tertahan, $datar);

            if ($belumLengkap !== null && mb_strlen($alasanBelumLengkap) < 5) {
                throw new RuntimeException(
                    $belumLengkap.' Bila kurva memang hendak dikunci tanpa mereka, isi alasannya (5 sampai 500 karakter).'
                );
            }

            if (mb_strlen($alasanBelumLengkap) > 500) {
                throw new RuntimeException('Alasan mengunci kurva paling panjang 500 karakter.');
            }

            $keterangan = $belumLengkap === null ? null
                : "Dikunci dengan {$belum} hasil belum dihitung dan {$tertahan} tertahan"
                    .($datar === [] ? '' : '; grup tanpa sebaran: '.implode(', ', $datar))
                    .". Alasan: {$alasanBelumLengkap}";

            $tanpaGrup = $hasil->whereNull('grup_id');

            if ($tanpaGrup->isNotEmpty()) {
                throw new RuntimeException(sprintf(
                    'Level %s belum masuk grup kurva di siklus ini. Atur grup siklus lebih dulu.',
                    $tanpaGrup->pluck('ID_Level_Saat_Cycle')->unique()->implode(', ')
                ));
            }

            foreach ($this->grupSiklus($cycleId) as $g) {
                $anggota = $hasil->where('grup_id', $g['id']);

                /* Grup kosong TIDAK dikunci. Kurva dari nol orang berpita datar (μ = 0), dan siapa
                   pun yang menyusul akan mendapat grade tengah tanpa pernah dibandingkan dengan
                   siapa-siapa. Tanpa kurva, gradeBeku() menahan hasilnya dengan alasan jelas. */
                if ($anggota->isEmpty()) {
                    continue;
                }

                $stat = $this->statBeku($anggota->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all());
                $pita = PaKurvaStatistik::pita($gradeDef, $stat['rata'], $stat['sd']);

                $kurvaId = (int) DB::table('N_PA_Cycle_Curve')->insertGetId([
                    'PA_Cycle_Id' => $cycleId,
                    'PA_Cycle_Curve_Group_Id' => $g['id'],
                    'Jumlah_Peserta' => $stat['n'],
                    'Rata_Rata' => $stat['rata'],
                    'Simpangan_Baku' => $stat['sd'],
                    'Locked_By' => $olehUserId,
                ], 'Id_PA_Cycle_Curve');

                foreach ($pita as $p) {
                    DB::table('N_PA_Cycle_Curve_Band')->insert([
                        'PA_Cycle_Curve_Id' => $kurvaId, 'Kode_Range' => $p['kode'], 'Nama_Range' => $p['nama'],
                        'Batas_Z' => $p['batas_z'], 'Nilai_Dari' => $p['nilai_dari'], 'Nilai_Sampai' => $p['nilai_sampai'],
                        'Urutan_Tampil' => $p['urutan'],
                    ]);
                }

                $this->beriGrade($cycleId, $g['id'], $kurvaId, $pita);
                $kurvaBaru[] = $kurvaId;
            }

            // Grade sistem baru saja ditulis untuk seluruh grup; potret per orang (DDL-2026-168).
            $this->riwayat->potret($this->hasilPadaKurva($kurvaBaru), PaResultHistoryService::KURVA_DIKUNCI, $olehUserId, $keterangan);
        });

        return $this->pratinjau($cycleId);
    }

    /** @return array<string, mixed> */
    public function buka(int $cycleId, ?string $alasan, ?int $olehUserId): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::BUKA_KURVA, $cycleId);
        $alasan = trim((string) $alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 500) {
            throw new RuntimeException('Alasan membuka kunci kurva wajib diisi, 5 sampai 500 karakter.');
        }

        if ($this->jumlahTerbit($cycleId) > 0) {
            throw new RuntimeException(
                'Kurva tidak bisa dibuka: sudah ada hasil yang terbit. Membukanya akan mengubah grade '
                .'yang sudah dibaca karyawan tanpa pemberitahuan.'
            );
        }

        /* Aturan yang sama dengan hitung ulang Balancing (PaBalancingPenjaga::jumlahSudahDibaca):
           hasil yang sudah DIRILIS ke leader juga sudah dibaca orang. Membuka kunci mengosongkan
           grade resminya diam-diam di layar leader. */
        $dirilis = $this->jumlahDirilisLeader($cycleId);

        if ($dirilis > 0) {
            throw new RuntimeException(
                "Kurva tidak bisa dibuka: {$dirilis} hasil sudah dirilis ke leader. Membukanya akan mengosongkan "
                .'grade yang sedang dibaca leader. Cabut rilis leader lebih dulu.'
            );
        }

        if (! $this->terkunci($cycleId)) {
            throw new RuntimeException('Kurva siklus ini belum dikunci.');
        }

        DB::transaction(function () use ($cycleId, $alasan, $olehUserId) {
            $kurva = DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
                ->pluck('Id_PA_Cycle_Curve')->all();

            $terdampak = $this->hasilPadaKurva($kurva);

            // Grade mesin dikosongkan; keputusan forum (Flag_Dikalibrasi) dipertahankan.
            DB::table('N_PA_Result')->whereIn('PA_Cycle_Curve_Id', $kurva)->update([
                'Kode_Grade_Sistem' => null,
                'Kode_Grade_Final' => DB::raw("CASE WHEN Flag_Dikalibrasi = 'Y' THEN Kode_Grade_Final ELSE NULL END"),
                'PA_Cycle_Curve_Id' => null,
            ]);

            DB::table('N_PA_Cycle_Curve')->whereIn('Id_PA_Cycle_Curve', $kurva)->update([
                'Flag_Aktif' => 'T', 'Unlocked_At' => now(), 'Unlocked_By' => $olehUserId, 'Alasan_Buka' => $alasan,
            ]);

            $this->riwayat->potret($terdampak, PaResultHistoryService::KURVA_DIBUKA, $olehUserId, $alasan);
        });

        return $this->pratinjau($cycleId);
    }

    /** @return array<string, mixed> */
    public function ubahMetode(int $cycleId, string $metode, ?int $olehUserId): array
    {
        $metode = strtoupper(trim($metode));

        if (! in_array($metode, ['PITA', 'KURVA'], true)) {
            throw new RuntimeException('Metode grade hanya PITA atau KURVA.');
        }

        if ($this->jumlahTerbit($cycleId) > 0 || $this->terkunci($cycleId)) {
            throw new RuntimeException('Metode grade hanya bisa diubah sebelum kurva dikunci dan sebelum ada hasil yang terbit.');
        }

        DB::transaction(function () use ($cycleId, $metode, $olehUserId) {
            DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->update(['Metode_Grade' => $metode]);

            if ($metode === 'KURVA') {
                // Batas z diambil dari master saat pindah; pita nilai salinan tetap utuh untuk PITA.
                foreach (DB::table('N_PA_Score_Range')->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
                    ->where('Flag_Aktif', 'Y')->get(['Id_PA_Score_Range', 'Batas_Z']) as $m) {
                    DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $cycleId)
                        ->where('PA_Score_Range_Id', $m->Id_PA_Score_Range)->update(['Batas_Z' => $m->Batas_Z]);
                }

                $this->penyiapan->salinGrupKurva($cycleId, $olehUserId);
                // Grade PITA lama tidak berlaku lagi sampai kurva dikunci.
                DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                    ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Flag_Dikalibrasi', '<>', 'Y')
                    ->update(['r.Kode_Grade_Sistem' => null, 'r.Kode_Grade_Final' => null]);
            }
        });

        return $this->pratinjau($cycleId);
    }

    /**
     * @param  list<array{kode:string, nama:string, level_ids:list<int>}>  $grup
     * @return array<string, mixed>
     */
    public function aturGrupSiklus(int $cycleId, array $grup, ?int $olehUserId): array
    {
        if ($this->terkunci($cycleId)) {
            throw new RuntimeException('Grup kurva tidak bisa diubah selama kurva terkunci. Buka kunci lebih dulu.');
        }

        $bersih = $this->grupMaster->periksa($grup);

        DB::transaction(function () use ($cycleId, $bersih, $olehUserId) {
            DB::table('N_PA_Cycle_Curve_Group_Level')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->update(['Flag_Aktif' => 'T']);
            DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->update(['Flag_Aktif' => 'T']);

            foreach ($bersih as $i => $g) {
                $id = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId([
                    'PA_Cycle_Id' => $cycleId, 'Kode_Grup' => $g['kode'], 'Nama_Grup' => $g['nama'],
                    'Urutan_Tampil' => $i + 1, 'Created_By' => $olehUserId,
                ], 'Id_PA_Cycle_Curve_Group');

                foreach ($g['level_ids'] as $l) {
                    DB::table('N_PA_Cycle_Curve_Group_Level')->insert(['PA_Cycle_Curve_Group_Id' => $id, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => $l]);
                }
            }
        });

        return $this->pratinjau($cycleId);
    }

    /**
     * Grade dari kurva beku grup peserta.
     *
     * `alasan` terisi bila siklus KURVA sudah dikunci tetapi peserta ini tidak punya kurva beku
     * (levelnya tanpa grup, atau grupnya kosong saat dikunci). Pemanggil MENAHAN hasilnya
     * dengan alasan itu — bukan melempar, supaya satu peserta susulan tidak menggagalkan
     * penghitungan seluruh siklus.
     *
     * @return array{kode:?string, curve_id:?int, alasan:?string}
     */
    public function gradeBeku(int $participantId, float $nilai): array
    {
        $baris = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->leftJoin('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Cycle_Curve as k', function ($j) {
                $j->on('k.PA_Cycle_Curve_Group_Id', '=', 'gl.PA_Cycle_Curve_Group_Id')->where('k.Flag_Aktif', 'Y');
            })
            ->where('p.Id_PA_Participant', $participantId)
            ->first(['c.Metode_Grade', 'k.Id_PA_Cycle_Curve', 'p.PA_Cycle_Id', 'gl.PA_Cycle_Curve_Group_Id as grup_id']);

        if ($baris === null || $baris->Metode_Grade !== 'KURVA' || $baris->Id_PA_Cycle_Curve === null) {
            $alasan = null;

            if ($baris !== null && $baris->Metode_Grade === 'KURVA' && $this->terkunci((int) $baris->PA_Cycle_Id)) {
                $angka = number_format($nilai, 2, ',', '.');
                $alasan = $baris->grup_id === null
                    ? "Nilai akhir {$angka} sudah terhitung, tetapi level peserta tidak masuk grup kurva mana pun saat kurva dikunci. Buka kunci kurva, atur grupnya, lalu kunci ulang."
                    : "Nilai akhir {$angka} sudah terhitung, tetapi grup kurva peserta kosong saat kurva dikunci sehingga tidak punya kurva beku. Buka kunci kurva lalu kunci ulang supaya ia ikut membentuk kurva.";
            }

            return ['kode' => null, 'curve_id' => null, 'alasan' => $alasan];
        }

        $kode = PaKurvaStatistik::gradeUntuk($this->pitaBeku((int) $baris->Id_PA_Cycle_Curve), $nilai);

        if ($kode === null) {
            throw new RuntimeException("Nilai akhir {$nilai} peserta {$participantId} tidak masuk satu pun pita kurva beku.");
        }

        return ['kode' => $kode, 'curve_id' => (int) $baris->Id_PA_Cycle_Curve, 'alasan' => null];
    }

    /**
     * Pita beku seluruh peserta satu siklus dalam DUA kueri — untuk pemanggil yang butuh grade
     * beku ratusan orang sekaligus (jejak hitung ulang Balancing). `gradeBeku()` per orang
     * berarti dua kueri per peserta, dan di sana dijalankan selagi baris siklus terkunci.
     * Peserta tanpa kurva beku (level tanpa grup, grup kosong saat kunci) tidak ada di peta.
     *
     * @return array<int, list<array{kode:string, nama:string, batas_z:?float, nilai_dari:float, nilai_sampai:float, urutan:int}>>
     */
    public function pitaBekuPerPeserta(int $cycleId): array
    {
        $kurvaPeserta = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Cycle_Curve as k', function ($j) {
                $j->on('k.PA_Cycle_Curve_Group_Id', '=', 'gl.PA_Cycle_Curve_Group_Id')->where('k.Flag_Aktif', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->pluck('k.Id_PA_Cycle_Curve', 'p.Id_PA_Participant');

        if ($kurvaPeserta->isEmpty()) {
            return [];
        }

        // Satu kurva per grup: jumlahnya sebanyak grup, jauh di bawah batas parameter.
        $pita = DB::table('N_PA_Cycle_Curve_Band')->whereIn('PA_Cycle_Curve_Id', $kurvaPeserta->unique()->values()->all())
            ->orderBy('Urutan_Tampil')
            ->get(['PA_Cycle_Curve_Id', 'Kode_Range', 'Nama_Range', 'Batas_Z', 'Nilai_Dari', 'Nilai_Sampai', 'Urutan_Tampil'])
            ->groupBy(fn ($b) => (int) $b->PA_Cycle_Curve_Id)
            ->map(fn ($baris) => $baris->map(fn ($b) => [
                'kode' => (string) $b->Kode_Range, 'nama' => (string) $b->Nama_Range,
                'batas_z' => $b->Batas_Z === null ? null : (float) $b->Batas_Z,
                'nilai_dari' => (float) $b->Nilai_Dari, 'nilai_sampai' => (float) $b->Nilai_Sampai, 'urutan' => (int) $b->Urutan_Tampil,
            ])->values()->all());

        return $kurvaPeserta->mapWithKeys(fn ($kurvaId, $pid) => [(int) $pid => $pita->get((int) $kurvaId, [])])->all();
    }

    /**
     * Grade SEMENTARA per peserta untuk siklus KURVA yang belum dikunci — hanya untuk DIBACA
     * (Distribusi, Pemantauan, detail hasil), tidak pernah disimpan. Dihitung dari kurva grup
     * saat ini, jadi bisa bergeser saat hasil lain masuk; layar wajib menandainya "sementara".
     *
     * Kosong untuk siklus PITA, siklus yang sudah dikunci (grade resmi sudah di N_PA_Result),
     * atau siklus yang batas z-nya belum diatur. Peserta yang levelnya tanpa grup tidak ikut.
     *
     * @return array<int, string> Id_PA_Participant => Kode_Range
     */
    public function gradeSementara(int $cycleId): array
    {
        if ($this->metode($cycleId) !== 'KURVA' || $this->terkunci($cycleId)) {
            return [];
        }

        $gradeDef = $this->gradeSiklus($cycleId);

        if ($gradeDef === []) {
            return [];
        }

        $peta = [];

        foreach ($this->hasilDihitung($cycleId)->whereNotNull('grup_id')->groupBy('grup_id') as $anggota) {
            $stat = $this->statBeku($anggota->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all());
            $pita = PaKurvaStatistik::pita($gradeDef, $stat['rata'], $stat['sd']);

            foreach ($anggota as $r) {
                $kode = PaKurvaStatistik::gradeUntuk($pita, (float) $r->Final_Score);

                if ($kode !== null) {
                    $peta[(int) $r->pid] = $kode;
                }
            }
        }

        return $peta;
    }

    /**
     * Grade SELURUH hasil siklus seandainya Final sebagian orang diganti — dibaca, tidak disimpan.
     *
     * Jalurnya sama dengan `kunci()`: hasilDihitung → statBeku → PaKurvaStatistik::pita →
     * gradeUntuk. Karena itu simulasi Balancing di rapat sama dengan grade sesudah hitung ulang.
     *
     * @param  array<int, float>  $finalPengganti  Id_PA_Participant => Final baru
     * @return array{grade: array<int, ?string>, grup: array<int, int>, pita: array<int, list<array>>}
     */
    public function simulasikanGrade(int $cycleId, array $finalPengganti): array
    {
        $gradeDef = $this->gradeSiklus($cycleId);

        if ($gradeDef === []) {
            throw new RuntimeException('Batas z grade belum diatur di salinan siklus ini.');
        }

        $hasil = $this->hasilDihitung($cycleId)->map(function ($r) use ($finalPengganti) {
            if (array_key_exists((int) $r->pid, $finalPengganti)) {
                $r->Final_Score = $finalPengganti[(int) $r->pid];
            }

            return $r;
        });

        $grade = [];
        $grup = [];
        $pita = [];
        $statGrup = [];

        foreach ($hasil->whereNotNull('grup_id')->groupBy('grup_id') as $grupId => $anggota) {
            $stat = $this->statBeku($anggota->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all());
            $statGrup[(int) $grupId] = $stat;
            $pita[(int) $grupId] = PaKurvaStatistik::pita($gradeDef, $stat['rata'], $stat['sd']);

            foreach ($anggota as $r) {
                $grade[(int) $r->pid] = PaKurvaStatistik::gradeUntuk($pita[(int) $grupId], (float) $r->Final_Score);
                $grup[(int) $r->pid] = (int) $grupId;
            }
        }

        // `stat` (n, μ, σ per grup) untuk menggambar kurva sesudah di modal simulasi Balancing.
        return ['grade' => $grade, 'grup' => $grup, 'pita' => $pita, 'stat' => $statGrup];
    }

    /**
     * Rincian perhitungan kurva SATU grup untuk modal detail: anggota beserta Final, selisih
     * terhadap μ, z, dan kuadrat selisihnya; langkah μ dan σ; serta turunan batas tiap grade.
     * μ, σ, dan pita diambil dari `pratinjau()` — sumber yang sama dengan kartu grup — jadi
     * angka modal tidak pernah berbeda dari kartu yang diklik.
     *
     * @return array<string, mixed>
     */
    public function detailGrup(int $cycleId, int $grupId): array
    {
        $pratinjau = $this->pratinjau($cycleId);
        $grup = collect($pratinjau['grup'])->firstWhere('id', $grupId);

        if ($grup === null) {
            throw new RuntimeException("Grup kurva {$grupId} tidak ada di siklus ini.");
        }

        $anggota = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->join('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('gl.PA_Cycle_Curve_Group_Id', $grupId)
            ->where('r.Flag_Aktif', 'Y')->whereIn('r.Status_Hasil', ['DIHITUNG', 'TERBIT'])->whereNotNull('r.Final_Score')
            ->orderByDesc('r.Final_Score')->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->get(['p.Id_PA_Participant', 'p.Nama_Karyawan_Saat_Cycle', 'p.Kode_Karyawan', 'p.Nama_Level_Saat_Cycle', 'r.Final_Score',
                'r.Kode_Grade_Sistem', 'r.Kode_Grade_Final']);

        $nilai = $anggota->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all();
        $n = count($nilai);
        $jumlah = array_sum($nilai);
        $rataMentah = $n > 0 ? $jumlah / $n : null;
        $kuadrat = $rataMentah === null ? 0.0 : array_sum(array_map(fn ($v) => ($v - $rataMentah) ** 2, $nilai));
        $varians = $n > 1 ? $kuadrat / ($n - 1) : null;
        $rata = $grup['rata'];
        $sd = $grup['sd'];

        return [
            'grup' => ['id' => $grup['id'], 'kode' => $grup['kode'], 'nama' => $grup['nama'], 'level' => array_column($grup['level'], 'nama')],
            'metode' => $pratinjau['metode'],
            'terkunci' => $pratinjau['terkunci'],
            'peringatan' => $grup['peringatan'],
            'min_grup' => self::MIN_GRUP,
            'langkah' => [
                'n' => $n,
                'jumlah' => round($jumlah, 4),
                'rata_mentah' => $rataMentah,
                'jumlah_kuadrat' => round($kuadrat, 4),
                'varians' => $varians === null ? null : round($varians, 4),
                'sd_mentah' => $varians === null ? null : sqrt($varians),
                // Yang dipakai membentuk pita: dibulatkan 4 desimal (lihat statBeku) atau nilai beku kurva terkunci.
                'rata' => $rata,
                'sd' => $sd,
                'n_kurva' => $grup['n'],
            ],
            'pita' => array_map(fn ($p) => $p + [
                'jumlah' => (int) ($grup['sebaran'][$p['kode']] ?? 0),
                'batas_mentah' => ($p['batas_z'] === null || $rata === null || $sd === null) ? null : $rata + (float) $p['batas_z'] * $sd,
            ], $grup['pita']),
            'proporsi' => $this->proporsiTeoretis($cycleId),
            'anggota' => $anggota->map(function ($a) use ($rata, $sd, $grup) {
                $x = (float) $a->Final_Score;

                /* DUA GRADE, karena keduanya benar untuk pertanyaan yang berbeda. `grade_sistem`
                   adalah letak nilai orang ini di pita kurva — yang dijelaskan modal ini.
                   `grade_final` adalah grade yang BERLAKU: hasil Kalibrasi Forum bila ada. Sampai
                   8 Oktober 2026 hanya yang pertama dikirim, sehingga peserta terkalibrasi
                   (mis. #1206270 di siklus 8781, final C_PLUS) terbaca ber-grade C di sini
                   sementara layar lain berkata C_PLUS. `grade` = grade berlaku, untuk layar lama. */
                $sistem = $grup['pita'] === [] ? $a->Kode_Grade_Sistem : PaKurvaStatistik::gradeUntuk($grup['pita'], $x);
                $final = $a->Kode_Grade_Final ?? $sistem;

                return [
                    'participant_id' => (int) $a->Id_PA_Participant, 'nama' => $a->Nama_Karyawan_Saat_Cycle,
                    'kode_karyawan' => $a->Kode_Karyawan, 'level' => $a->Nama_Level_Saat_Cycle, 'final' => $x,
                    'selisih' => $rata === null ? null : round($x - $rata, 4),
                    'kuadrat' => $rata === null ? null : round(($x - $rata) ** 2, 4),
                    'z' => ($rata === null || ! $sd) ? null : round(($x - $rata) / $sd, 4),
                    'grade' => $final,
                    'grade_sistem' => $sistem,
                    'grade_final' => $final,
                ];
            })->values()->all(),
        ];
    }

    /**
     * Posisi satu peserta di grup kurvanya — dipakai dialog hasil admin. Memakai ulang
     * `detailGrup()` supaya μ, σ, pita, dan grade sama persis dengan layar Kurva.
     * NULL bila siklus bukan metode KURVA, levelnya belum masuk grup, atau hasilnya belum
     * dihitung (tidak ada di anggota grup).
     *
     * @return array<string, mixed>|null
     */
    public function posisiPeserta(int $participantId): ?array
    {
        $p = DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)
            ->first(['PA_Cycle_Id', 'ID_Level_Saat_Cycle']);

        if ($p === null || $this->metode((int) $p->PA_Cycle_Id) !== 'KURVA') {
            return null;
        }

        $grupId = DB::table('N_PA_Cycle_Curve_Group_Level')
            ->where('PA_Cycle_Id', $p->PA_Cycle_Id)->where('ID_Level', $p->ID_Level_Saat_Cycle)
            ->where('Flag_Aktif', 'Y')->value('PA_Cycle_Curve_Group_Id');

        if ($grupId === null) {
            return null;
        }

        $d = $this->detailGrup((int) $p->PA_Cycle_Id, (int) $grupId);
        $indeks = collect($d['anggota'])->search(fn ($a) => $a['participant_id'] === $participantId);

        if ($indeks === false) {
            return null;
        }

        $saya = $d['anggota'][$indeks];
        $n = count($d['anggota']);

        // Grade berikutnya = pita dengan batas bawah terkecil yang masih di atas nilai orang ini.
        $berikut = collect($d['pita'])
            ->filter(fn ($p) => $p['batas_mentah'] !== null && $p['batas_mentah'] > $saya['final'])
            ->sortBy('batas_mentah')->first();

        return [
            'grup' => ['id' => $d['grup']['id'], 'nama' => $d['grup']['nama']],
            'terkunci' => $d['terkunci'],
            'n' => $n,
            'peringkat' => $indeks + 1,
            // Persentil: persen anggota grup yang nilainya di bawah orang ini.
            'persentil' => $n > 1 ? (int) round(($n - 1 - $indeks) / ($n - 1) * 100) : null,
            'nilai' => $saya['final'],
            'rata' => $d['langkah']['rata'],
            'sd' => $d['langkah']['sd'],
            'z' => $saya['z'],
            // Grade berlaku (kalibrasi forum bila ada) dan grade menurut pita — lihat detailGrup().
            'grade' => $saya['grade_final'],
            'grade_sistem' => $saya['grade_sistem'],
            'grade_final' => $saya['grade_final'],
            'grade_berikut' => $berikut === null ? null : [
                'kode' => $berikut['kode'],
                'batas' => round((float) $berikut['batas_mentah'], 2),
                'jarak' => round((float) $berikut['batas_mentah'] - $saya['final'], 2),
            ],
            'peringatan' => $d['peringatan'],
        ];
    }

    /**
     * Proporsi teoretis tiap grade (persen) dari batas z salinan siklus; kosong bila belum diatur.
     *
     * @return array<string, float>
     */
    public function proporsiTeoretis(int $cycleId): array
    {
        $gradeDef = $this->gradeSiklus($cycleId);

        return $gradeDef === [] ? [] : PaKurvaStatistik::proporsiTeoretis($gradeDef);
    }

    /* ------------------------------------------------------------------ */

    /**
     * μ dan σ dibulatkan ke presisi kolomnya (4 desimal) SEBELUM pita diturunkan.
     *
     * Yang tersimpan di N_PA_Cycle_Curve adalah angka bulat itu, dan `pa:verify` menurunkan
     * ulang pita darinya. Pita dari μ/σ penuh meleset 0,0001 dan terbaca "disunting tangan"
     * (PA-2026-11: C_PLUS 72,7444 vs 72,7443). Pratinjau dan grade sementara memakai jalur
     * yang sama supaya grade sebelum dan sesudah kunci tidak berbeda di tepi pita.
     *
     * @param  list<float>  $nilai
     * @return array{n:int, rata:?float, sd:?float}
     */
    private function statBeku(array $nilai): array
    {
        $stat = PaKurvaStatistik::ringkas($nilai);

        return [
            'n' => $stat['n'],
            'rata' => $stat['rata'] === null ? null : round($stat['rata'], 4),
            'sd' => $stat['sd'] === null ? null : round($stat['sd'], 4),
        ];
    }

    /**
     * Hasil peserta DIIKUTKAN yang belum berangka. Peserta yang dikeluarkan tidak dihitung:
     * hasilnya memang tidak akan pernah ada, dan menghitungnya menahan kunci selamanya.
     *
     * @return array{tertahan:int, belum:int}
     */
    private function cacahBelumBerangka(int $cycleId): array
    {
        $baris = DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')->where('p.Flag_Aktif', 'Y')
            ->where('r.Flag_Aktif', 'Y')->whereIn('r.Status_Hasil', ['TERTAHAN', 'BELUM_DIHITUNG'])
            ->groupBy('r.Status_Hasil')->selectRaw('r.Status_Hasil as s, COUNT(*) as n')->pluck('n', 's');

        return ['tertahan' => (int) ($baris['TERTAHAN'] ?? 0), 'belum' => (int) ($baris['BELUM_DIHITUNG'] ?? 0)];
    }

    /** Kalimat penghalang yang bisa dilewati dengan alasan; NULL bila semua hasil sudah berangka. */
    private function kalimatBelumLengkap(int $belum, int $tertahan): ?string
    {
        if ($belum + $tertahan === 0) {
            return null;
        }

        return "Masih ada {$belum} hasil belum dihitung dan {$tertahan} tertahan. Mereka tidak ikut membentuk kurva, "
            .'lalu menerima grade dari kurva beku yang tidak mereka bentuk.';
    }

    /**
     * Penghalang yang bisa dilewati DENGAN ALASAN: hasil belum lengkap, dan (14 Okt 2026) grup
     * tanpa sebaran. Grup berisi satu orang — atau yang nilainya sama semua — berpita datar,
     * sehingga SEMUA anggotanya mendapat grade tengah berapa pun nilainya. Itu boleh (level
     * khusus seperti Officer Specialist memang kecil), tetapi harus diputuskan, bukan terjadi.
     *
     * @param  list<string>  $grupDatar
     */
    private function kalimatPerluAlasan(int $belum, int $tertahan, array $grupDatar): ?string
    {
        $bagian = array_filter([
            $this->kalimatBelumLengkap($belum, $tertahan),
            $grupDatar === [] ? null : 'Grup '.implode(', ', $grupDatar).' tidak punya sebaran nilai (satu orang atau '
                .'nilainya sama), sehingga semua anggotanya akan mendapat grade tengah berapa pun nilainya.',
        ]);

        return $bagian === [] ? null : implode(' ', $bagian);
    }

    /**
     * Kode grup yang berisi hasil tetapi tanpa sebaran (n = 1, atau simpangan baku nol).
     *
     * @return list<string>
     */
    private function grupDatar(int $cycleId, Collection $hasil): array
    {
        $datar = [];

        foreach ($this->grupSiklus($cycleId) as $g) {
            $nilai = $hasil->where('grup_id', $g['id'])->pluck('Final_Score')->map(fn ($v) => (float) $v)->values()->all();

            if ($nilai === []) {
                continue;
            }

            $stat = $this->statBeku($nilai);

            if ($stat['sd'] === null || $stat['sd'] <= 0.0) {
                $datar[] = (string) $g['kode'];
            }
        }

        return $datar;
    }

    /**
     * Penghalang MUTLAK kunci — cermin penolakan di `kunci()`, supaya tombol tidak menyala untuk
     * aksi yang pasti ditolak.
     *
     * @return list<string>
     */
    private function penghalangKunci(int $cycleId, string $metode, bool $terkunci, array $gradeDef, Collection $hasil, array $levelTanpaGrup): array
    {
        if ($terkunci) {
            return ['Kurva siklus ini sudah dikunci.'];
        }

        if ($metode !== 'KURVA') {
            return ['Siklus ini memakai pita nilai tetap (PITA).'];
        }

        $p = [];

        if (! $this->gate->boleh(PaCycleGate::KUNCI_KURVA, $cycleId)) {
            $p[] = 'Kurva hanya bisa dikunci sesudah pengisian ditutup (siklus CLOSED); status sekarang '.$this->gate->status($cycleId).'.';
        }

        if ($gradeDef === []) {
            $p[] = 'Batas z grade belum diatur di salinan siklus ini.';
        }

        if ($hasil->isEmpty()) {
            $p[] = 'Belum ada hasil DIHITUNG untuk membentuk kurva.';
        }

        if ($levelTanpaGrup !== []) {
            $p[] = 'Level '.implode(', ', $levelTanpaGrup).' belum masuk grup kurva.';
        }

        return $p;
    }

    /** @param list<array> $pita */
    /** @return list<int> */
    private function hasilPadaKurva(array $kurvaIds): array
    {
        return $kurvaIds === [] ? [] : DB::table('N_PA_Result')->whereIn('PA_Cycle_Curve_Id', $kurvaIds)->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Result')->map(fn ($id) => (int) $id)->all();
    }

    private function beriGrade(int $cycleId, int $grupId, int $kurvaId, array $pita): void
    {
        foreach ($pita as $p) {
            if ($p['nilai_sampai'] <= $p['nilai_dari']) {
                continue;
            }

            // Set-based per pita: SATU pernyataan untuk semua orang di rentang ini.
            DB::update(
                "UPDATE N_PA_Result
                 SET Kode_Grade_Sistem = ?,
                     Kode_Grade_Final = CASE WHEN Flag_Dikalibrasi = 'Y' THEN Kode_Grade_Final ELSE ? END,
                     PA_Cycle_Curve_Id = ?
                 WHERE Flag_Aktif = 'Y' AND Status_Hasil = 'DIHITUNG'
                   AND Final_Score >= ? AND Final_Score < ?
                   AND PA_Participant_Id IN (
                       SELECT p.Id_PA_Participant FROM N_PA_Participant p
                       JOIN N_PA_Cycle_Curve_Group_Level gl
                         ON gl.PA_Cycle_Id = p.PA_Cycle_Id AND gl.ID_Level = p.ID_Level_Saat_Cycle AND gl.Flag_Aktif = 'Y'
                       WHERE p.PA_Cycle_Id = ? AND p.Status_Peserta = 'DIIKUTKAN' AND gl.PA_Cycle_Curve_Group_Id = ?
                   )",
                [$p['kode'], $p['kode'], $kurvaId, $p['nilai_dari'], $p['nilai_sampai'], $cycleId, $grupId],
            );
        }
    }

    /** @return list<array{kode:string, nama:string, batas_z:?float, urutan:int}> */
    private function gradeSiklus(int $cycleId): array
    {
        $baris = DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->where('Score_Type', 'FINAL')->where('Score_Usage', 'GRADE')
            ->orderBy('Urutan_Tampil')->get(['Kode_Range', 'Nama_Range', 'Batas_Z', 'Urutan_Tampil']);

        if ($baris->whereNotNull('Batas_Z')->isEmpty()) {
            return [];
        }

        return $baris->map(fn ($b) => [
            'kode' => (string) $b->Kode_Range, 'nama' => (string) $b->Nama_Range,
            'batas_z' => $b->Batas_Z === null ? null : (float) $b->Batas_Z, 'urutan' => (int) $b->Urutan_Tampil,
        ])->values()->all();
    }

    /** @return list<array{id:int, kode:string, nama:string, level:list<array{id:int, nama:string}>}> */
    private function grupSiklus(int $cycleId): array
    {
        $anggota = DB::table('N_PA_Cycle_Curve_Group_Level as gl')->join('N_PA_Level as l', 'l.ID_Level', '=', 'gl.ID_Level')
            ->where('gl.PA_Cycle_Id', $cycleId)->where('gl.Flag_Aktif', 'Y')->orderBy('gl.ID_Level')
            ->get(['gl.PA_Cycle_Curve_Group_Id', 'gl.ID_Level', 'l.Nama_Level as Keterangan'])->groupBy('PA_Cycle_Curve_Group_Id');

        return DB::table('N_PA_Cycle_Curve_Group')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            // Id sebagai pemutus seri: urutan kembar membuat urutan grup berubah antar-muat.
            ->orderBy('Urutan_Tampil')->orderBy('Id_PA_Cycle_Curve_Group')
            ->get(['Id_PA_Cycle_Curve_Group', 'Kode_Grup', 'Nama_Grup'])
            ->map(fn ($g) => [
                'id' => (int) $g->Id_PA_Cycle_Curve_Group, 'kode' => (string) $g->Kode_Grup, 'nama' => (string) $g->Nama_Grup,
                'level' => collect($anggota->get($g->Id_PA_Cycle_Curve_Group, []))
                    ->map(fn ($l) => ['id' => (int) $l->ID_Level, 'nama' => (string) $l->Keterangan])->values()->all(),
            ])->values()->all();
    }

    /** Hasil DIHITUNG peserta DIIKUTKAN beserta grup siklusnya (NULL = level tanpa grup). */
    private function hasilDihitung(int $cycleId): Collection
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->leftJoin('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')->where('gl.Flag_Aktif', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')->whereIn('r.Status_Hasil', ['DIHITUNG', 'TERBIT'])->whereNotNull('r.Final_Score')
            ->get(['p.Id_PA_Participant as pid', 'r.Final_Score', 'p.ID_Level_Saat_Cycle', 'gl.PA_Cycle_Curve_Group_Id as grup_id'])
            ->map(function ($r) {
                $r->grup_id = $r->grup_id === null ? null : (int) $r->grup_id;

                return $r;
            });
    }

    private function kurvaAktif(int $cycleId): Collection
    {
        return DB::table('N_PA_Cycle_Curve')->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Cycle_Curve', 'PA_Cycle_Curve_Group_Id', 'Jumlah_Peserta', 'Rata_Rata', 'Simpangan_Baku'])
            ->keyBy(fn ($k) => (int) $k->PA_Cycle_Curve_Group_Id);
    }

    /** @return list<array{kode:string, nama:string, batas_z:?float, nilai_dari:float, nilai_sampai:float, urutan:int}> */
    private function pitaBeku(int $kurvaId): array
    {
        return DB::table('N_PA_Cycle_Curve_Band')->where('PA_Cycle_Curve_Id', $kurvaId)->orderBy('Urutan_Tampil')
            ->get(['Kode_Range', 'Nama_Range', 'Batas_Z', 'Nilai_Dari', 'Nilai_Sampai', 'Urutan_Tampil'])
            ->map(fn ($b) => [
                'kode' => (string) $b->Kode_Range, 'nama' => (string) $b->Nama_Range,
                'batas_z' => $b->Batas_Z === null ? null : (float) $b->Batas_Z,
                'nilai_dari' => (float) $b->Nilai_Dari, 'nilai_sampai' => (float) $b->Nilai_Sampai, 'urutan' => (int) $b->Urutan_Tampil,
            ])->values()->all();
    }

    /** @return list<string> */
    private function peringatanGrup(array $g, array $stat): array
    {
        $p = [];

        if ($stat['n'] < self::MIN_GRUP) {
            $p[] = "Grup {$g['kode']} hanya {$stat['n']} orang (di bawah ".self::MIN_GRUP.'); kurvanya kurang bermakna.';
        }

        if ($stat['n'] >= 1 && ($stat['sd'] === null || $stat['sd'] <= 0.0)) {
            $p[] = "Grup {$g['kode']} tidak punya sebaran; semua anggotanya mendapat grade rata-rata.";
        }

        return $p;
    }

    private function jumlahDirilisLeader(int $cycleId): int
    {
        return (int) DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Status_Hasil', '<>', 'TERBIT')
            ->whereNotNull('r.Published_Leader_At')->count();
    }

    /** @return list<string> nama level peserta berhasil-hitung yang belum masuk grup kurva siklus ini */
    private function levelTanpaGrup(int $cycleId, Collection $hasil): array
    {
        $ids = $hasil->whereNull('grup_id')->pluck('ID_Level_Saat_Cycle')->filter()->unique()->values()->all();

        return $ids === [] ? [] : DB::table('N_PA_Participant')->where('PA_Cycle_Id', $cycleId)->whereIn('ID_Level_Saat_Cycle', $ids)
            ->distinct()->orderBy('Nama_Level_Saat_Cycle')->pluck('Nama_Level_Saat_Cycle')
            ->map(fn ($n) => (string) ($n ?: '?'))->values()->all();
    }

    private function jumlahTerbit(int $cycleId): int
    {
        return (int) DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)->where('r.Flag_Aktif', 'Y')->where('r.Status_Hasil', 'TERBIT')->count();
    }
}
