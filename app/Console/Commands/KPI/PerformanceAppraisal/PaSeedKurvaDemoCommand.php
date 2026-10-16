<?php

namespace App\Console\Commands\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * SIKLUS DEMO KURVA — supaya kurva normal per grup benar-benar terbentuk di dev.
 *
 * Kurva baru bermakna mulai 30 orang per grup (`PaKurvaService::MIN_GRUP`), sedangkan siklus
 * contoh lain di dev hanya punya segelintir hasil DIHITUNG. Perintah ini membuat SATU siklus
 * KURVA berstatus CLOSED dengan tiga grup kurva (Staff, Officer, Manajerial), masing-masing di
 * atas 30 peserta, dan hasil DIHITUNG yang nilai KPI & kompetensinya tersebar normal.
 *
 * Aturan grade (batas z) dan pita HAV disalin dari siklus sumber (`--sumber`), jadi grade dan
 * kotak HAV dihitung dengan aturan yang sama dengan siklus sungguhan. Final = KPI × bobot KPI +
 * Kompetensi × bobot kompetensi, persis rumus perhitungan.
 *
 * BATASAN: tidak ada lembar penilai, jadi sesi Balancing tidak bisa dibuat di siklus ini.
 * Semua barisnya bertanda PENANDA pada `N_PA_Cycle.Deskripsi`; `--hapus` mencabut seluruhnya.
 */
class PaSeedKurvaDemoCommand extends Command
{
    private const PENANDA = 'KURVA-DEMO-SEED';

    private const KODE = 'PA-DEMO-KURVA';

    private const BENIH = 20261002;

    /**
     * kode grup => [nama, kuota per level (Keterangan HRIS_Level => orang), bobot KPI, rata KPI, rata kompetensi].
     * Kuota per level, bukan per grup: halaman Distribusi menganalisis per LEVEL dan juga menuntut
     * >= 30 orang, jadi level utama tiap grup diberi sedikitnya 30 (Senior Staff/Officer memang sedikit).
     */
    private const GRUP = [
        'STAFF' => ['Staff', ['STAFF' => 56, 'SENIOR STAFF' => 4], 50, 82.0, 74.0],
        'OFFICER' => ['Officer & Senior Officer', ['OFFICER' => 48, 'SENIOR OFFICER' => 2], 60, 85.0, 77.0],
        'MANAJERIAL' => ['Manajerial', ['MANAGER' => 32, 'GENERAL MANAGER' => 32], 70, 88.0, 80.0],
    ];

    protected $signature = 'pa:seed-kurva-demo
        {--sumber=10244 : Siklus asal salinan batas z grade dan pita HAV}
        {--kunci : Sekalian kunci kurvanya (bawaan: dibiarkan terbuka untuk dicoba di tahap Kurva)}
        {--hapus : Hapus siklus demo kurva beserta seluruh barisnya}';

    protected $description = 'Membuat siklus KURVA demo dengan tiga grup >= 30 peserta supaya kurva normal terbentuk';

    public function handle(PaKurvaService $kurva): int
    {
        if (DB::selectOne('SELECT DB_NAME() AS d')->d !== 'Web_HRIS') {
            $this->error('Hanya untuk Web_HRIS development.');

            return self::FAILURE;
        }

        if ($this->option('hapus')) {
            return $this->hapus();
        }

        if (DB::table('N_PA_Cycle')->where('Kode_Cycle', self::KODE)->exists()) {
            $this->warn('Siklus '.self::KODE.' sudah ada. Jalankan dengan --hapus dulu bila ingin membuat ulang.');

            return self::FAILURE;
        }

        $sumber = (int) $this->option('sumber');
        $snapshot = DB::table('N_PA_Cycle_Score_Range_Snapshot')->where('PA_Cycle_Id', $sumber)->where('Flag_Aktif', 'Y')->get();

        if ($snapshot->where('Score_Usage', 'GRADE')->whereNotNull('Batas_Z')->isEmpty()) {
            $this->error("Siklus sumber {$sumber} tidak punya batas z grade.");

            return self::FAILURE;
        }

        mt_srand(self::BENIH);

        try {
            $cycleId = DB::transaction(fn () => $this->bangun($snapshot));
        } catch (Throwable $e) {
            $this->error('Gagal: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('kunci')) {
            // Data demo: hasil yang belum lengkap memang bagian dari skenarionya.
            $kurva->kunci($cycleId, null, 'Data demo kurva (pa:seed-kurva-demo).');
            $this->info('Kurva dikunci.');
        }

        $this->ringkas($cycleId);

        return self::SUCCESS;
    }

    private function bangun($snapshot): int
    {
        $tahun = (int) date('Y');
        $cycleId = (int) DB::table('N_PA_Cycle')->insertGetId([
            'Kode_Cycle' => self::KODE, 'Nama_Cycle' => 'Demo Kurva Normal', 'Tahun' => $tahun,
            'Status_Cycle' => 'CLOSED', 'Metode_Grade' => 'KURVA', 'Flag_Aktif' => 'Y',
            'Deskripsi' => self::PENANDA.' | Data contoh kurva normal (3 grup >= 30 orang), bukan penilaian sungguhan.',
            'Periode_Dinilai_Dari' => "{$tahun}-01-01", 'Periode_Dinilai_Sampai' => "{$tahun}-06-30",
            'Pengisian_Dari' => "{$tahun}-07-01", 'Pengisian_Sampai' => "{$tahun}-07-31",
        ], 'Id_PA_Cycle');

        foreach ($snapshot as $s) {
            $baris = (array) $s;
            unset($baris['Id_PA_Cycle_Score_Range_Snapshot']);
            $baris['PA_Cycle_Id'] = $cycleId;
            DB::table('N_PA_Cycle_Score_Range_Snapshot')->insert($baris);
        }

        $pitaHav = fn (string $tipe) => $snapshot->where('Score_Type', $tipe)->where('Score_Usage', 'HAVMAP')->values();
        $pitaKpi = $pitaHav('KPI');
        $pitaKomp = $pitaHav('KOMPETENSI');
        $kuadran = DB::table('N_PA_Hav_Quadrant')->where('Flag_Aktif', 'Y')->get(['Id_PA_Hav_Quadrant', 'Tingkat_Performa', 'Tingkat_Kompetensi']);
        $sudah = [];
        $urut = 0;

        foreach (self::GRUP as $kode => [$nama, $kuota, $bobotKpi, $rataKpi, $rataKomp]) {
            $levelId = DB::table('HRIS_Level')->whereIn('Keterangan', array_keys($kuota))->pluck('ID_Level', 'Keterangan');
            $grupId = (int) DB::table('N_PA_Cycle_Curve_Group')->insertGetId([
                'PA_Cycle_Id' => $cycleId, 'Kode_Grup' => $kode, 'Nama_Grup' => $nama, 'Urutan_Tampil' => ++$urut, 'Flag_Aktif' => 'Y',
            ], 'Id_PA_Cycle_Curve_Group');

            foreach ($levelId as $id) {
                DB::table('N_PA_Cycle_Curve_Group_Level')->insert([
                    'PA_Cycle_Curve_Group_Id' => $grupId, 'PA_Cycle_Id' => $cycleId, 'ID_Level' => (int) $id, 'Flag_Aktif' => 'Y',
                ]);
            }

            $karyawan = collect();

            foreach ($kuota as $levelNama => $jumlah) {
                $karyawan = $karyawan->merge(DB::table('Karyawan as k')
                    ->join('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                    ->leftJoin('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                    ->where('k.Aktif', 'Y')->whereNull('k.Tanggal_Resign')->where('g.ID_Level', (int) ($levelId[$levelNama] ?? 0))
                    ->whereNotIn('k.Kode_Karyawan', $sudah ?: [''])
                    ->orderBy('k.Kode_Karyawan')->limit($jumlah)
                    ->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'k.ID_Level_Jabatan', 'k.ID_Divisi_Sub_Divisi', 'g.ID_Level', 'g.nama_level',
                        'g.nama_jabatan', 'd.ID_Divisi', 'd.nama_divisi', 'd.ID_Sub_Divisi', 'd.nama_sub_divisi']));
            }

            if ($karyawan->count() < 30) {
                throw new \RuntimeException("Grup {$kode} hanya mendapat {$karyawan->count()} karyawan aktif; kurva butuh >= 30.");
            }

            foreach ($karyawan as $k) {
                $sudah[] = $k->Kode_Karyawan;
                $pid = (int) DB::table('N_PA_Participant')->insertGetId([
                    'PA_Cycle_Id' => $cycleId, 'Kode_Perusahaan' => $k->Kode_Perusahaan, 'Kode_Karyawan' => $k->Kode_Karyawan,
                    'Nama_Karyawan_Saat_Cycle' => $k->Nama, 'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
                    'ID_Level_Saat_Cycle' => (int) $k->ID_Level, 'Nama_Level_Saat_Cycle' => $k->nama_level,
                    'ID_Divisi_Sub_Divisi_Saat_Cycle' => $k->ID_Divisi_Sub_Divisi, 'ID_Divisi_Saat_Cycle' => $k->ID_Divisi,
                    'Nama_Divisi_Saat_Cycle' => $k->nama_divisi, 'ID_Sub_Divisi_Saat_Cycle' => $k->ID_Sub_Divisi,
                    'Nama_Sub_Divisi_Saat_Cycle' => $k->nama_sub_divisi, 'Nama_Jabatan_Saat_Cycle' => $k->nama_jabatan,
                    'Status_Peserta' => 'DIIKUTKAN', 'Flag_Aktif' => 'Y',
                ], 'Id_PA_Participant');

                $kpi = round($this->normal($rataKpi, 11.0, 35.0, 120.0), 2);
                $komp = round($this->normal($rataKomp, 8.0, 30.0, 100.0), 2);
                $final = round($kpi * $bobotKpi / 100 + $komp * (100 - $bobotKpi) / 100, 2);

                DB::table('N_PA_Result')->insert([
                    'PA_Participant_Id' => $pid, 'Status_Hasil' => 'DIHITUNG',
                    'Nilai_KPI' => $kpi, 'Nilai_Kompetensi' => $komp, 'Final_Score' => $final,
                    'Bobot_KPI_Terpakai' => $bobotKpi, 'Bobot_Kompetensi_Terpakai' => 100 - $bobotKpi,
                    'PA_Hav_Quadrant_Id' => $this->kuadran($kuadran, $pitaKpi, $pitaKomp, $kpi, $komp),
                    'Calculation_Version' => 1, 'Calculated_At' => now(), 'Flag_Aktif' => 'Y',
                ]);
            }
        }

        return $cycleId;
    }

    /** Box–Muller, dibatasi ke rentang yang masuk akal. */
    private function normal(float $rata, float $sd, float $min, float $max): float
    {
        $u = max(mt_rand() / mt_getrandmax(), 1e-9);
        $v = mt_rand() / mt_getrandmax();

        return max($min, min($max, $rata + $sd * sqrt(-2 * log($u)) * cos(2 * M_PI * $v)));
    }

    /** Sama dengan `PaResultCalculationService::cariKuadran`: pita HAV KPI × pita HAV kompetensi. */
    private function kuadran($kuadran, $pitaKpi, $pitaKomp, float $kpi, float $komp): ?int
    {
        $cari = fn ($pita, $v) => $pita->first(fn ($p) => (float) $p->Nilai_Dari <= $v && $v < (float) $p->Nilai_Sampai)?->Kode_Range;
        $performa = $cari($pitaKpi, $kpi);
        $kompetensi = $cari($pitaKomp, $komp);

        if ($performa === null || $kompetensi === null) {
            return null;
        }

        $k = $kuadran->first(fn ($q) => $q->Tingkat_Performa === $performa && $q->Tingkat_Kompetensi === $kompetensi);

        return $k === null ? null : (int) $k->Id_PA_Hav_Quadrant;
    }

    private function ringkas(int $cycleId): void
    {
        $this->info("Siklus demo dibuat: #{$cycleId} ".self::KODE.' (CLOSED, KURVA).');
        $baris = DB::table('N_PA_Cycle_Curve_Group as g')
            ->join('N_PA_Cycle_Curve_Group_Level as gl', 'gl.PA_Cycle_Curve_Group_Id', '=', 'g.Id_PA_Cycle_Curve_Group')
            ->join('N_PA_Participant as p', fn ($j) => $j->on('p.PA_Cycle_Id', '=', 'g.PA_Cycle_Id')->on('p.ID_Level_Saat_Cycle', '=', 'gl.ID_Level'))
            ->join('N_PA_Result as r', fn ($j) => $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('r.Flag_Aktif', 'Y'))
            ->where('g.PA_Cycle_Id', $cycleId)->groupBy('g.Nama_Grup')
            ->selectRaw('g.Nama_Grup as grup, COUNT(*) as n, AVG(r.Final_Score) as rata, STDEV(r.Final_Score) as sd, SUM(CASE WHEN r.PA_Hav_Quadrant_Id IS NULL THEN 1 ELSE 0 END) as tanpa_hav')
            ->get();
        $this->table(['Grup', 'Peserta', 'Rata Final', 'SD', 'Tanpa kotak HAV'],
            $baris->map(fn ($b) => [$b->grup, $b->n, round((float) $b->rata, 2), round((float) $b->sd, 2), $b->tanpa_hav])->all());
        $this->line("Buka: /performance-appraisal/pemantauan?tahap=kurva&cycle={$cycleId}");
    }

    private function hapus(): int
    {
        $siklus = DB::table('N_PA_Cycle')->where('Kode_Cycle', self::KODE)->where('Deskripsi', 'like', '%'.self::PENANDA.'%')->pluck('Id_PA_Cycle');

        if ($siklus->isEmpty()) {
            $this->info('Tidak ada siklus demo kurva.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($siklus) {
            $peserta = DB::table('N_PA_Participant')->whereIn('PA_Cycle_Id', $siklus)->pluck('Id_PA_Participant');
            $sesi = DB::table('N_PA_Balancing_Sesi')->whereIn('PA_Cycle_Id', $siklus)->pluck('Id_PA_Balancing_Sesi');
            $bp = DB::table('N_PA_Balancing_Peserta')->whereIn('PA_Balancing_Sesi_Id', $sesi)->pluck('Id_PA_Balancing_Peserta');

            DB::table('N_PA_Balancing_Jejak_Hitung')->whereIn('PA_Cycle_Id', $siklus)->delete();

            foreach (['N_PA_Balancing_Kompetensi', 'N_PA_Balancing_Kualitatif'] as $t) {
                foreach ($bp->chunk(500) as $c) {
                    DB::table($t)->whereIn('PA_Balancing_Peserta_Id', $c)->delete();
                }
            }
            DB::table('N_PA_Balancing_Peserta')->whereIn('PA_Balancing_Sesi_Id', $sesi)->delete();
            DB::table('N_PA_Balancing_Forum')->whereIn('PA_Balancing_Sesi_Id', $sesi)->delete();
            DB::table('N_PA_Balancing_Sesi')->whereIn('Id_PA_Balancing_Sesi', $sesi)->delete();

            foreach ($peserta->chunk(500) as $c) {
                DB::table('N_PA_Result')->whereIn('PA_Participant_Id', $c)->delete();
            }

            $kurva = DB::table('N_PA_Cycle_Curve')->whereIn('PA_Cycle_Id', $siklus)->pluck('Id_PA_Cycle_Curve');
            DB::table('N_PA_Cycle_Curve_Band')->whereIn('PA_Cycle_Curve_Id', $kurva)->delete();
            DB::table('N_PA_Cycle_Curve')->whereIn('PA_Cycle_Id', $siklus)->delete();
            DB::table('N_PA_Cycle_Curve_Group_Level')->whereIn('PA_Cycle_Id', $siklus)->delete();
            DB::table('N_PA_Cycle_Curve_Group')->whereIn('PA_Cycle_Id', $siklus)->delete();
            DB::table('N_PA_Cycle_Score_Range_Snapshot')->whereIn('PA_Cycle_Id', $siklus)->delete();
            DB::table('N_PA_Participant')->whereIn('PA_Cycle_Id', $siklus)->delete();
            DB::table('N_PA_Cycle')->whereIn('Id_PA_Cycle', $siklus)->delete();
        });

        $this->info('Siklus demo kurva dihapus: #'.$siklus->implode(', #'));

        return self::SUCCESS;
    }
}
