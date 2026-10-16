<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use App\Services\SYSTEM\Application\Transactions\TransactionNumberService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pembangkit nomor transaksi permit.
 *
 * Diangkat keluar dari VehiclePermitController ketika endpoint permit mitra
 * membutuhkannya juga. Penomoran kedua yang berdiri sendiri akan menghasilkan
 * nomor yang bertabrakan dengan pengajuan HCIS, dan tabrakannya baru terlihat
 * sebagai pelanggaran kunci unik pada pengajuan yang sah.
 *
 * Memakai counter atomik (sargable, anti-duplikat) alih-alih memindai LIKE +
 * ORDER BY: pemindaian itu pernah jadi sumber beban CPU nyata di modul ini.
 */
class PermitNomorTransaksi
{
    public function __construct(private readonly TransactionNumberService $nomor) {}

    public function buat(string $prefix = 'PMT'): string
    {
        $base = $prefix.Carbon::now()->format('my').'-';

        return $this->nomor->generate(
            $base,
            function () use ($base) {
                $last = DB::table('N_EMI_VISITOR_Transaksi_Permit')
                    ->where('No_Transaksi', 'LIKE', $base.'%')
                    ->orderByDesc('No_Transaksi')
                    ->value('No_Transaksi');

                return $last ? (int) substr($last, -4) : 0;
            },
            4,
        );
    }
}
