<?php

namespace App\Services\LMS;

use Illuminate\Support\Facades\DB;

class TrainingProgressReadService
{
    /**
     * Banyak id per pernyataan.
     *
     * SQL Server menolak lebih dari 2.100 parameter dalam satu pernyataan, dan
     * satu bagian di sini bisa dipakai lebih dari sekali (id penjadwalan, lalu
     * id transaksi turunannya). 500 memberi jarak yang lapang dari batas itu
     * sekaligus menjaga rencana kueri tetap dapat digunakan ulang.
     */
    private const POTONGAN_ID = 500;

    public function computeProgressSnapshot(int $penjadwalanId): array
    {
        $totalItems = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->count();

        if ($totalItems <= 0) {
            return [
                'total_items' => 0,
                'completed_items' => 0,
                'progress_percent' => 0,
            ];
        }

        $completedFromPenjadwalan = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->leftJoin('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->where(function ($q) {
                $q->where('pdet.Flag_Selesai', 'Y')->orWhere(function ($qAbs) {
                    $qAbs
                        ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(akt.Kode_Aktivitas, '')))) = 'ABS'")
                        ->where('pdet.Flag_Checkin', 'Y');
                });
            })
            ->count();

        $completedFromProgress = 0;
        $transaksi = DB::table('N_LMS_Transaksi_Training')
            ->where('Id_Penjadwalan', $penjadwalanId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if ($transaksi) {
            $completedFromProgress = DB::table('N_LMS_Training_Progress')
                ->where('Id_Transaksi_Training', $transaksi->Id_Transaksi_Training)
                ->where('Status_Progress', 'SELESAI')
                ->count();
        }

        $completedItems = min($totalItems, max($completedFromPenjadwalan, $completedFromProgress));
        $progressPercent = (int) round(($completedItems / $totalItems) * 100);

        return [
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'progress_percent' => $progressPercent,
        ];
    }

    public function hasStartedActivity(int $penjadwalanId): bool
    {
        if ($penjadwalanId <= 0) {
            return false;
        }

        $hasStartedPenjadwalanDet = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->where('pd.Id_Penjadwalan', $penjadwalanId)
            ->where(function ($q) {
                $q->where('pdet.Flag_Checkin', 'Y')
                    ->orWhere('pdet.Flag_Selesai', 'Y')
                    ->orWhereNotNull('pdet.Waktu_Mulai_Akses');
            })
            ->exists();

        if ($hasStartedPenjadwalanDet) {
            return true;
        }

        $hasCheckinout = DB::table('N_LMS_CHECKINOUT')->where('Id_Penjadwalan', $penjadwalanId)->exists();
        if ($hasCheckinout) {
            return true;
        }

        $transaksi = DB::table('N_LMS_Transaksi_Training')
            ->where('Id_Penjadwalan', $penjadwalanId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $transaksi) {
            return false;
        }

        return DB::table('N_LMS_Training_Progress')
            ->where('Id_Transaksi_Training', $transaksi->Id_Transaksi_Training)
            ->whereIn('Status_Progress', ['PROGRESS', 'SELESAI'])
            ->exists();
    }

    /**
     * Bentuk JAMAK dari computeProgressSnapshot() + hasStartedActivity().
     *
     * Keduanya berbiaya 4–6 kueri per penjadwalan. Katalog training memanggil
     * keduanya di dalam satu perulangan, sehingga sembilan baris menghasilkan
     * 61 kueri — 58 di antaranya per-baris. Biayanya naik lurus mengikuti
     * jumlah training yang diikuti seseorang.
     *
     * Di sini kueri yang sama dijalankan SEKALI untuk semua id sekaligus, lalu
     * hasilnya dikelompokkan. Aritmetikanya sengaja disalin apa adanya dari
     * bentuk tunggal di atas — termasuk `min`/`max` dan pembulatannya — supaya
     * angka yang keluar identik, bukan sekadar mirip.
     *
     * @param  array<int,int>  $penjadwalanIds
     * @return array<int,array{total_items:int,completed_items:int,progress_percent:int,has_started:bool}>
     */
    public function ringkasanMassal(array $penjadwalanIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $penjadwalanIds), fn ($v) => $v > 0)));

        if ($ids === []) {
            return [];
        }

        /* Dipotong per bagian karena SQL Server menolak lebih dari 2.100
           parameter dalam satu pernyataan. Versi pertama metode ini mengirim
           seluruh id sekaligus dan meledak dengan
           "Tried to bind parameter number 2101" begitu diberi daftar penuh —
           aman untuk satu halaman, rusak untuk pemanggil yang mengirim semuanya.
           Ketahuan dari skrip diagnostik, bukan dari layar. */
        if (count($ids) > self::POTONGAN_ID) {
            $hasil = [];
            foreach (array_chunk($ids, self::POTONGAN_ID) as $bagian) {
                $hasil += $this->ringkasanMassal($bagian);
            }

            return $hasil;
        }

        $total = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->whereIn('pd.Id_Penjadwalan', $ids)
            ->groupBy('pd.Id_Penjadwalan')
            ->selectRaw('pd.Id_Penjadwalan as id, COUNT(*) as n')
            ->pluck('n', 'id')
            ->all();

        $selesaiPenjadwalan = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->leftJoin('N_LMS_Materi_Det as det', 'pdet.Id_Materi_Det', '=', 'det.Id_Materi_Det')
            ->leftJoin('N_LMS_Master_Aktivitas as akt', 'det.Id_Master_Aktivitas', '=', 'akt.Id_Master_Aktivitas')
            ->whereIn('pd.Id_Penjadwalan', $ids)
            ->where(function ($q) {
                $q->where('pdet.Flag_Selesai', 'Y')->orWhere(function ($qAbs) {
                    $qAbs
                        ->whereRaw("UPPER(LTRIM(RTRIM(ISNULL(akt.Kode_Aktivitas, '')))) = 'ABS'")
                        ->where('pdet.Flag_Checkin', 'Y');
                });
            })
            ->groupBy('pd.Id_Penjadwalan')
            ->selectRaw('pd.Id_Penjadwalan as id, COUNT(*) as n')
            ->pluck('n', 'id')
            ->all();

        /* Sudah dimulai — dari sisi penjadwalan. Satu baris yang sudah checkin,
           selesai, atau punya waktu mulai sudah cukup. */
        $mulaiPenjadwalan = DB::table('N_LMS_Penjadwalan_Detail as pd')
            ->join('N_LMS_Penjadwalan_Det as pdet', 'pd.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->whereIn('pd.Id_Penjadwalan', $ids)
            ->where(function ($q) {
                $q->where('pdet.Flag_Checkin', 'Y')
                    ->orWhere('pdet.Flag_Selesai', 'Y')
                    ->orWhereNotNull('pdet.Waktu_Mulai_Akses');
            })
            ->distinct()
            ->pluck('pd.Id_Penjadwalan')
            ->all();
        $mulaiPenjadwalan = array_flip(array_map('intval', $mulaiPenjadwalan));

        $adaCheckinout = array_flip(array_map('intval', DB::table('N_LMS_CHECKINOUT')
            ->whereIn('Id_Penjadwalan', $ids)
            ->distinct()
            ->pluck('Id_Penjadwalan')
            ->all()));

        $transaksi = DB::table('N_LMS_Transaksi_Training')
            ->whereIn('Id_Penjadwalan', $ids)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_Transaksi_Training', 'Id_Penjadwalan')
            ->all();

        $selesaiProgres = [];
        $sedangProgres = [];
        if ($transaksi !== []) {
            $idTransaksi = array_values(array_map('intval', $transaksi));

            $selesaiProgres = DB::table('N_LMS_Training_Progress')
                ->whereIn('Id_Transaksi_Training', $idTransaksi)
                ->where('Status_Progress', 'SELESAI')
                ->groupBy('Id_Transaksi_Training')
                ->selectRaw('Id_Transaksi_Training as id, COUNT(*) as n')
                ->pluck('n', 'id')
                ->all();

            $sedangProgres = array_flip(array_map('intval', DB::table('N_LMS_Training_Progress')
                ->whereIn('Id_Transaksi_Training', $idTransaksi)
                ->whereIn('Status_Progress', ['PROGRESS', 'SELESAI'])
                ->distinct()
                ->pluck('Id_Transaksi_Training')
                ->all()));
        }

        $hasil = [];
        foreach ($ids as $id) {
            $totalItems = (int) ($total[$id] ?? 0);

            if ($totalItems <= 0) {
                $hasil[$id] = [
                    'total_items' => 0,
                    'completed_items' => 0,
                    'progress_percent' => 0,
                    'has_started' => isset($mulaiPenjadwalan[$id]) || isset($adaCheckinout[$id]),
                ];

                continue;
            }

            $idTx = isset($transaksi[$id]) ? (int) $transaksi[$id] : null;
            $dariProgres = $idTx !== null ? (int) ($selesaiProgres[$idTx] ?? 0) : 0;
            $dariPenjadwalan = (int) ($selesaiPenjadwalan[$id] ?? 0);

            $selesai = min($totalItems, max($dariPenjadwalan, $dariProgres));

            $hasil[$id] = [
                'total_items' => $totalItems,
                'completed_items' => $selesai,
                'progress_percent' => (int) round(($selesai / $totalItems) * 100),
                'has_started' => isset($mulaiPenjadwalan[$id])
                    || isset($adaCheckinout[$id])
                    || ($idTx !== null && isset($sedangProgres[$idTx])),
            ];
        }

        return $hasil;
    }
}
