<?php

namespace App\Services\KPI;

use Illuminate\Support\Facades\DB;

/**
 * Cari baris KPI_Pengajuan_Det yang berada SEBELUM bulan mulai karyawannya.
 *
 * Kenapa ini penting: YTD menjumlahkan target dari baris yang ADA
 * (DashboardCeoController::allTargets), bukan dari 12 bulan tetap. Target bulan
 * yang tak pernah dijalani karyawan karena itu ikut masuk penyebut dan menekan
 * achievement-nya tanpa sebab yang terlihat di layar mana pun.
 *
 * Sejak bulan mulai diresolusi per anggota saat menyimpan, baris seperti ini
 * tidak terbentuk lagi — service ini hanya membaca peninggalan lama.
 *
 * MEMBACA SAJA. Penghapusan ditangani perintah artisan, bukan di sini.
 */
class AuditTargetSebelumMulaiService
{
    public function __construct(private KpiStartPeriodeResolver $resolver) {}

    /**
     * @return array{ringkasan: array, data: array}
     */
    public function jalankan(?string $kodeKaryawan = null, ?string $tahun = null): array
    {
        $pengajuan = $this->ambilPengajuan($kodeKaryawan, $tahun);
        $data = [];

        foreach ($pengajuan as $p) {
            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $p->Id_Penjadwalan)
                ->first();

            if (! $jadwal) {
                continue;
            }

            $resolusi = $this->resolver->resolve($p->Kode_Karyawan, $jadwal);
            $mulai = $resolusi['mulai'];

            // Tidak tercakup payung ini berarti tidak ada bulan mulai yang bisa
            // dijadikan pembanding. Melanjutkan dengan null akan membandingkan
            // Periode < '' dan melaporkan NOL temuan — audit yang selalu bersih
            // justru bentuk kegagalan yang paling sulit disadari.
            if ($mulai === null) {
                continue;
            }

            $baris = DB::table('KPI_Pengajuan_Det as d')
                ->join('KPI_Pengajuan_Detail as dt', 'dt.Id_Pengajuan_Detail', '=', 'd.Id_Pengajuan_Detail')
                ->where('dt.Id_Pengajuan', $p->Id_Pengajuan)
                ->where('d.Periode', '<', $mulai)
                ->select(
                    'd.Id_Pengajuan_Det',
                    'd.Periode',
                    'd.Nilai_Target',
                    'd.Nama_Target',
                    'dt.Nama_Parameter',
                    'dt.Bobot',
                    'dt.Id_Pengajuan_Detail',
                )
                ->orderBy('d.Periode')
                ->get();

            if ($baris->isEmpty()) {
                continue;
            }

            // Baris yang sudah dipakai realisasi tidak boleh dihapus: menghapusnya
            // memutus KPI_Update_Performance_Detail.Id_Pengajuan_Det dan merusak
            // data yang sudah disetujui.
            $terpakai = DB::table('KPI_Update_Performance_Detail')
                ->whereIn('Id_Pengajuan_Det', $baris->pluck('Id_Pengajuan_Det'))
                ->pluck('Id_Pengajuan_Det')
                ->unique()
                ->all();

            $data[] = [
                'kode_karyawan' => $p->Kode_Karyawan,
                'nama_karyawan' => $p->Nama,
                'no_transaksi' => $p->No_Transaksi,
                'id_pengajuan' => $p->Id_Pengajuan,
                'bulan_mulai' => $mulai,
                'sumber_bulan_mulai' => $resolusi['sumber'],
                'jumlah_baris' => $baris->count(),
                'jumlah_baris_bisa_dibersihkan' => $baris->whereNotIn('Id_Pengajuan_Det', $terpakai)->count(),
                'baris' => $baris
                    ->map(fn ($b) => [
                        'id_pengajuan_det' => $b->Id_Pengajuan_Det,
                        'periode' => $b->Periode,
                        'nama_parameter' => $b->Nama_Parameter,
                        'nama_target' => $b->Nama_Target,
                        'nilai_target' => (float) $b->Nilai_Target,
                        'bisa_dibersihkan' => ! in_array($b->Id_Pengajuan_Det, $terpakai, true),
                    ])
                    ->values()
                    ->all(),
                'dampak_parameter' => $this->dampakParameter($baris, $terpakai),
            ];
        }

        return [
            'ringkasan' => [
                'total_karyawan' => count($data),
                'total_baris' => array_sum(array_column($data, 'jumlah_baris')),
                'total_baris_bisa_dibersihkan' => array_sum(array_column($data, 'jumlah_baris_bisa_dibersihkan')),
                'filter' => ['kode_karyawan' => $kodeKaryawan, 'tahun' => $tahun],
            ],
            'data' => $data,
        ];
    }

    /**
     * Efek pada penyebut YTD: Σtarget per parameter sebelum vs sesudah, beserta
     * rasio achievement-nya — angka inilah yang benar-benar bergerak.
     */
    private function dampakParameter($baris, array $terpakai): array
    {
        $kelompok = $baris->groupBy('Id_Pengajuan_Detail');
        $idDetailSemua = $kelompok->keys()->filter()->values()->all();

        /*
         * DUA kueri untuk seluruh parameter, bukan dua PER parameter.
         *
         * Bentuk lama menembak sum() sekali per Id_Pengajuan_Detail di dalam
         * map(), dan metode ini sendiri dipanggil dari perulangan pengajuan.
         * Biayanya jadi pengajuan x parameter x 2 — pada layar audit yang
         * menyisir seluruh pengajuan tim, itu tumbuh dua arah sekaligus.
         *
         * Keduanya sudah berbentuk agregat; yang kurang hanya GROUP BY.
         */
        $sebelumPer = $this->totalTargetPer($idDetailSemua);
        $aktualPer = $this->totalAktualPer($idDetailSemua);

        return $kelompok
            ->map(function ($rows, $idDetail) use ($terpakai, $sebelumPer, $aktualPer) {
                $sebelum = (float) ($sebelumPer[$idDetail] ?? 0);

                $dibuang = (float) $rows
                    ->whereNotIn('Id_Pengajuan_Det', $terpakai)
                    ->sum('Nilai_Target');

                $aktual = (float) ($aktualPer[$idDetail] ?? 0);

                $sesudah = $sebelum - $dibuang;

                return [
                    'nama_parameter' => $rows->first()->Nama_Parameter,
                    'bobot' => (float) $rows->first()->Bobot,
                    'total_aktual' => $aktual,
                    'total_target_sekarang' => $sebelum,
                    'total_target_setelah_bersih' => $sesudah,
                    'rasio_sekarang_persen' => $sebelum > 0 ? round($aktual / $sebelum * 100, 2) : null,
                    'rasio_setelah_bersih_persen' => $sesudah > 0 ? round($aktual / $sesudah * 100, 2) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Total Nilai_Target per Id_Pengajuan_Detail — satu kueri.
     *
     * @param  list<int|string>  $idDetail
     * @return array<int|string,float>
     */
    private function totalTargetPer(array $idDetail): array
    {
        if ($idDetail === []) {
            return [];
        }

        $hasil = [];

        // Batas 2.100 parameter SQL Server: daftarnya dipotong, bukan diandalkan
        // tetap kecil.
        foreach (array_chunk($idDetail, 1000) as $potongan) {
            foreach (DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Detail', $potongan)
                ->groupBy('Id_Pengajuan_Detail')
                ->selectRaw('Id_Pengajuan_Detail, SUM(Nilai_Target) AS total')
                ->get() as $r) {
                $hasil[$r->Id_Pengajuan_Detail] = (float) $r->total;
            }
        }

        return $hasil;
    }

    /**
     * Total Nilai_Actual per Id_Pengajuan_Detail — satu kueri.
     *
     * @param  list<int|string>  $idDetail
     * @return array<int|string,float>
     */
    private function totalAktualPer(array $idDetail): array
    {
        if ($idDetail === []) {
            return [];
        }

        $hasil = [];

        foreach (array_chunk($idDetail, 1000) as $potongan) {
            foreach (DB::table('KPI_Update_Performance_Detail as d')
                ->join('KPI_Update_Performance as h', 'h.Id_Update_Performance', '=', 'd.Id_Update_Performance')
                ->join('KPI_Pengajuan_Det as pd', 'pd.Id_Pengajuan_Det', '=', 'd.Id_Pengajuan_Det')
                ->whereIn('pd.Id_Pengajuan_Detail', $potongan)
                ->where('h.Flag_Aktif', 'Y')
                ->groupBy('pd.Id_Pengajuan_Detail')
                ->selectRaw('pd.Id_Pengajuan_Detail AS Id_Pengajuan_Detail, SUM(d.Nilai_Actual) AS total')
                ->get() as $r) {
                $hasil[$r->Id_Pengajuan_Detail] = (float) $r->total;
            }
        }

        return $hasil;
    }

    private function ambilPengajuan(?string $kodeKaryawan, ?string $tahun)
    {
        $query = DB::table('KPI_Pengajuan as kp')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kp.Id_User')
            // Hanya jalur tim. Jalur individu tidak pernah kena masalah ini: bulan
            // mulainya memang diambil dari NIK orang yang mengisi, yaitu dirinya.
            ->where('kp.Flag_Team', 'Y')
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNotNull('kp.Id_Penjadwalan')
            ->select('kp.Id_Pengajuan', 'kp.No_Transaksi', 'kp.Id_Penjadwalan', 'k.Kode_Karyawan', 'k.Nama');

        if ($kodeKaryawan) {
            $query->where('k.Kode_Karyawan', $kodeKaryawan);
        }

        if ($tahun) {
            $query->whereExists(function ($sub) use ($tahun) {
                $sub->select(DB::raw(1))
                    ->from('KPI_Master_Penjadwalan as mp')
                    ->whereColumn('mp.Id_Master_Penjadwalan', 'kp.Id_Penjadwalan')
                    ->where('mp.Tahun', $tahun);
            });
        }

        return $query->orderBy('k.Nama')->get();
    }
}
