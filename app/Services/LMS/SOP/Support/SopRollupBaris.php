<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Penjamin keberadaan baris N_SOP_Coverage_Rollup untuk (paket × versi item).
 *
 * Tiga penulis rollup — SopCompletionService::bumpRollup, SopPenjadwalanService::
 * bumpRollup, dan SopReconciliationService::bumpTarget — sama-sama menjalankan
 * exists() lalu insert(). Di antara keduanya ada jeda, dan tabelnya dijaga
 * UQ_N_SOP_Rollup(Id_SOP_Paket, Id_SOP_Item_Versi). Dua konfirmasi pertama atas
 * versi item yang baru terbit, atau publish yang beriringan dengan konfirmasi,
 * membuat insert kedua melanggar unique — dan karena semuanya berjalan di dalam
 * DB::transaction, yang batal bukan cuma insert itu melainkan SELURUH transaksi:
 * karyawan menerima 500 dan konfirmasinya hilang.
 *
 * Baris rollup tak punya identitas selain pasangan kuncinya, jadi kalah balapan
 * tidak merugikan siapa pun — barisnya sudah ada, dan itu memang yang diinginkan.
 * Karena itu pelanggaran unique di sini diperlakukan sebagai keberhasilan pihak
 * lain, bukan sebagai galat.
 *
 * Nilai awal dibiarkan ditentukan pemanggil karena ketiganya memang berbeda:
 * jalur konfirmasi mulai dari 0 lalu menaikkan, jalur assign menanam delta-nya
 * langsung, jalur rekonsiliasi menanam 1.
 *
 * @return bool true bila baris DIBUAT oleh pemanggil ini. false berarti barisnya
 *              sudah ada — pemanggil yang membedakan jalur "buat" dan "tambah"
 *              harus meneruskan ke UPDATE, bukan berhenti.
 */
final class SopRollupBaris
{
    public static function pastikan(int $idPaket, int $idVersi, int $totalTargetAwal = 0): bool
    {
        $ada = DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->exists();

        if ($ada) {
            return false;
        }

        try {
            DB::table('N_SOP_Coverage_Rollup')->insert([
                'Id_SOP_Paket' => $idPaket,
                'Id_SOP_Item_Versi' => $idVersi,
                'Total_Target' => $totalTargetAwal,
                'Jumlah_Dibuka' => 0,
                'Jumlah_Dikonfirmasi' => 0,
                'Persen_Dikonfirmasi' => 0,
                'Updated_At' => now(),
            ]);
        } catch (QueryException $e) {
            // 2627 = pelanggaran UNIQUE/PK, 2601 = pelanggaran index unik.
            if (! in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw $e;
            }

            return false; // didahului request lain — barisnya tetap ada.
        }

        return true;
    }
}
