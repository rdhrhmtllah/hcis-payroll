<?php

namespace App\Services\ESS\Attendance\Leave\Personal;

use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CutiService
{
    protected $transactionService;

    // Inject TransactionService
    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function approve(string $noTransaksi, string $approverKode, ?string $catatan = null)
    {
        // START TRANSACTION
        DB::beginTransaction();
        try {
            // 1. Validasi: Cari Request Pending untuk User ini
            $req = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Flag_Approval')
                ->where(function ($q) use ($approverKode) {
                    $q->where('Approver_Karyawan_Kode', $approverKode)
                        // Cek Logic Group HC juga disini jika perlu
                        ->orWhere('Flag_Group', 'Y');
                })
                ->lockForUpdate() // Kunci row agar tidak double approve
                ->first();

            if (! $req) {
                throw new \Exception('Data approval tidak ditemukan atau sudah diproses.');
            }

            // 2. Update Status Approval
            DB::table('HRIS_Approval_Request')
                ->where('id', $req->id)
                ->update([
                    'Flag_Approval' => 'Y',
                    'Approved_By_Kode' => $approverKode,
                    'Catatan' => $catatan,
                    'Tanggal_Approval' => now(),
                    'updated_at' => now(),
                ]);

            // 3. Cek apakah ini Final Step?
            // Cek apakah ada step lain dengan order lebih tinggi?
            $isNotFinal = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->where('Request_Order_Flow', '>', $req->Request_Order_Flow)
                ->exists();

            if (! $isNotFinal) {
                // === FINAL APPROVAL ===
                // Panggil Service Transaksi untuk eksekusi bisnis
                $this->transactionService->handleFinalApprove($noTransaksi, $approverKode);
            }

            // COMMIT TRANSACTION
            DB::commit();

            return true;
        } catch (\Exception $e) {
            // ROLLBACK TRANSACTION
            DB::rollBack();

            // LOG SYSTEM ERROR
            // Kita log disini agar developer tahu detail errornya (Line, File, StackTrace)
            Log::error("Approval Error [$noTransaksi]: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            // RETHROW
            // Kita lempar ulang errornya agar Controller bisa menangkapnya
            // dan menampilkan pesan error ke user (frontend).
            throw $e;
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function reject(string $noTransaksi, string $rejectorKode, string $alasan)
    {
        DB::beginTransaction();
        try {
            // 1. Validasi
            $req = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNull('Flag_Approval')
                ->first();

            if (! $req) {
                throw new \Exception('Data tidak ditemukan.');
            }

            // 2. Update Reject
            DB::table('HRIS_Approval_Request')
                ->where('id', $req->id)
                ->update([
                    'Flag_Approval' => 'T', // Tolak
                    'Approved_By_Kode' => $rejectorKode,
                    'Catatan' => $alasan,
                    'Tanggal_Approval' => now(),
                    'updated_at' => now(),
                ]);

            // 3. Panggil Service Transaksi untuk batalkan transaksi
            $this->transactionService->handleReject($noTransaksi);

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Reject Error [$noTransaksi]: ".$e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }
}
