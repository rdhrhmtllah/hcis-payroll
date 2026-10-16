<?php

namespace App\Services\SYSTEM\Application\Transactions;

use App\Helpers\CutiSourceCategoryHelper;
use App\Helpers\FormatTanggalHelper;
use App\Helpers\WhatsappLogHelper;
use App\Jobs\ESS\Attendance\Processing\MarkAttendanceDirtyJob;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TransactionService
{
    const ID_TAHUNAN = 1;

    const ID_LOYALTY = 2;

    private function resolvePrefix(string $noTransaksi): string
    {
        if (str_starts_with($noTransaksi, 'TSH')) {
            return 'TSH';
        }

        // KPI punya mesin persetujuannya sendiri (KpiApprovalService) dan tidak
        // menitipkan apa pun ke service ini. Dikenali eksplisit supaya
        // peringatan "Prefix tidak dikenal" tetap berarti apa yang dikatakannya:
        // sebelum ini setiap approval KPI menuliskannya, sehingga prefix yang
        // BENAR-BENAR asing tenggelam di antara derau yang sudah diketahui.
        if (preg_match('/^[AP]KPI/', $noTransaksi)) {
            return 'KPI';
        }

        return substr($noTransaksi, 0, 2);
    }

    /**
     * Menangani logika saat Transaksi FINAL APPROVE
     */
    public function handleFinalApprove(string $noTransaksi, string $approverKode)
    {
        $prefix = $this->resolvePrefix($noTransaksi);

        switch ($prefix) {
            case 'CT': // Cuti
                $this->processCuti($noTransaksi);
                break;
            case 'IS': // Izin Sakit
                $this->processIzin($noTransaksi);
                break;
            case 'TP': // Lembur
                $this->proccessTerpulang($noTransaksi);
                break;
            case 'SH':
            case 'TH':
            case 'TSH':
            case 'KPI':
                return true;
            default:
                Log::channel('TransactionService')->warning(
                    "TransactionService: Prefix tidak dikenal untuk $noTransaksi",
                );

                return false;
        }
    }

    public function handleNextApprove(string $noTransaksi, string $approverKode)
    {
        $prefix = $this->resolvePrefix($noTransaksi);

        switch ($prefix) {
            case 'CT': // Cuti
                $transaksi = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $noTransaksi)->first();
                $tanggal = DB::table('Transaksi_Cuti_Det')
                    ->where('No_Transaksi', $noTransaksi)
                    ->get()
                    ->pluck('Tanggal_Cuti')
                    ->toArray();
                break;
            case 'IS': // Izin Sakit
                $transaksi = DB::table('Transaksi_Sakit_Izin_Detail')->where('No_Transaksi', $noTransaksi)->first();
                $tanggal = DB::table('Transaksi_Sakit_Izin_Det')
                    ->where('No_Transaksi', $noTransaksi)
                    ->get()
                    ->pluck('Tanggal_Sakit_Izin')
                    ->toArray();
                break;
            case 'TP': // Terlambat pulang cepat
                $transaksi = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                    ->where('No_Transaksi', $noTransaksi)
                    ->first();
                $tanggal = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                    ->where('No_Transaksi', $noTransaksi)
                    ->get()
                    ->pluck('Tanggal_Masuk_Pulang')
                    ->toArray();
                break;
            case 'SH':
            case 'TH':
            case 'TSH':
            case 'KPI':
                return true;
            default:
                Log::channel('TransactionService')->warning(
                    "handleNextApprove: Prefix tidak dikenal untuk $noTransaksi",
                );

                return false;
        }

        if (! $transaksi) {
            Log::channel('TransactionService')->warning(
                "handleNextApprove: Data transaksi tidak ditemukan untuk $noTransaksi",
            );

            return false;
        }

        if (! isset($tanggal)) {
            Log::channel('TransactionService')->warning(
                "handleNextApprove: tanggal transaksi tidak ditemukan untuk $noTransaksi",
            );

            return false;
        }

        $CurrentApprove = DB::table('N_HRIS_VW_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flow_State', 'CURRENT')
            ->first();

        if (! $CurrentApprove) {
            Log::channel('TransactionService')->warning(
                "handleNextApprove: tidak ada step CURRENT untuk $noTransaksi, notifikasi approver berikutnya dilewati",
            );

            return false;
        }

        // Step bergrup/role tidak punya satu approver tertunjuk; notifikasinya
        // ditangani service per-modul (mis. CutiNotificationService::notifyRolePoolForCurrentStep).
        // Dicatat supaya diamnya terlihat, bukan hilang tanpa jejak.
        if (! empty($CurrentApprove->Target_Group_Id)) {
            Log::channel('TransactionService')->info(
                "handleNextApprove: step CURRENT $noTransaksi bergrup (Target_Group_Id={$CurrentApprove->Target_Group_Id}), notif per-approver dilewati",
            );

            return true;
        }

        if (empty($CurrentApprove->Current_Approver_Kode)) {
            Log::channel('TransactionService')->warning(
                "handleNextApprove: step CURRENT $noTransaksi tanpa Current_Approver_Kode, notifikasi dilewati",
            );

            return false;
        }

        $this->notifApprovertoApprove(
            $noTransaksi,
            $transaksi->Jenis,
            $tanggal,
            $transaksi->Kode_Karyawan,
            $CurrentApprove->Current_Approver_Kode,
        );

        return true;
    }

    /**
     * Menangani logika saat Transaksi DITOLAK
     */
    public function handleReject(string $noTransaksi, ?string $approverKode = null, ?string $alasan = null)
    {
        // dd('handleReject',$noTransaksi, $approverKode);
        $prefix = substr($noTransaksi, 0, 2);

        $tableMap = [
            'CT' => 'Transaksi_Cuti_Detail',
            'IS' => 'Transaksi_Sakit_Izin_Detail',
            'LB' => 'Transaksi_Lembur_Detail',
            'TP' => 'Transaksi_Terlambat_Pulang_Cepat_Detail',
        ];

        $tanggalMap = [
            'CT' => 'Tanggal_Cuti',
            'IS' => 'Tanggal_Sakit_Izin',
            'TP' => 'Tanggal_Masuk_Pulang',
        ];

        if (! isset($tableMap[$prefix])) {
            return; // jika prefix tidak sesuai
        }

        // Update status flag
        DB::table($tableMap[$prefix])
            ->where('No_Transaksi', $noTransaksi)
            ->update(['Flag_Approval' => 'T']);

        // Dirty-mark absen dipindah ke worker (after_commit) agar EXEC SP tak menahan
        // lock selama transaksi approval/reject.
        MarkAttendanceDirtyJob::dispatch(
            $noTransaksi,
            "{$prefix}_REJECTED",
            'TRANSACTION_SERVICE_REJECT',
            1,
            app(AttendanceDirtyResolverService::class)->requestedBy(),
        );

        // Ambil data transaksi
        $jenis = DB::table($tableMap[$prefix])->where('No_Transaksi', $noTransaksi)->first();

        if (! $jenis) {
            Log::channel('TransactionService')->warning(
                "handleReject: Data transaksi tidak ditemukan untuk $noTransaksi",
            );

            return;
        }

        // Ambil tanggal dinamis kalau tersedia
        $tanggal = isset($tanggalMap[$prefix]) ? $jenis->{$tanggalMap[$prefix]} ?? null : null;

        // dd($noTransaksi,
        //     $jenis->Jenis ?? null,
        //     $tanggal,
        //     $jenis->Kode_Karyawan ?? null,
        //     $approverKode
        // );
        $requesterKode = DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->value('Kode_Karyawan');

        if (empty($requesterKode)) {
            $requesterKode = $jenis->Kode_Karyawan ?? null;
        }

        if (empty($approverKode)) {
            $approverKode = DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noTransaksi)
                ->whereNotNull('Approved_By_Kode')
                ->orderByDesc('Tanggal_Approval')
                ->value('Approved_By_Kode');
        }

        // Kirim notifikasi ditolak
        $this->notifDitolak($noTransaksi, $jenis->Jenis ?? null, $tanggal, $requesterKode, $approverKode);
    }

    /**
     * Fitur Admin: Manual Adjustment Saldo (Add/Subtract)
     */
    public function manualAdjustment(
        $kodeKaryawan,
        $idJenisCuti,
        $type,
        $amount,
        $alasan,
        $adminId,
        $activatedAt = null,
        $idBukuCuti = null,
    ) {
        DB::beginTransaction();
        try {
            $now = FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');

            if ($type == 'ADD') {
                // === ADJUSTMENT PENAMBAHAN (BAYAR HUTANG DULU) ===

                // 1. Cek semua hutang dan lunasi FIFO sebelum menambah saldo positif.
                $hutangRows = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $kodeKaryawan)
                    ->where('Id_Jenis_Cuti', $idJenisCuti)
                    ->where('sisa_cuti', '<', 0)
                    ->where('Flag_Aktif', 'Y')
                    ->orderBy('tanggal_expired', 'asc')
                    ->lockForUpdate()
                    ->get();

                $sisaAmount = $amount;

                foreach ($hutangRows as $rowHutang) {
                    if ($sisaAmount <= 0) {
                        break;
                    }

                    $hutangSaatIni = abs($rowHutang->sisa_cuti);
                    $bayar = min($sisaAmount, $hutangSaatIni);

                    DB::table('HRIS_Buku_Cuti')
                        ->where('Id_Buku_Cuti', $rowHutang->Id_Buku_Cuti)
                        ->update([
                            'sisa_cuti' => DB::raw("sisa_cuti + $bayar"),
                            'updated_at' => $now,
                        ]); // Kurangi minus

                    $this->logMutasi(
                        $rowHutang->Id_Buku_Cuti,
                        'MANUAL-ADJUST',
                        'CREDIT',
                        'ADJUSTMENT_DEBT',
                        $bayar,
                        $rowHutang->sisa_cuti,
                        $rowHutang->sisa_cuti + $bayar,
                        "Adjust Admin (Bayar Hutang): $alasan",
                        $adminId,
                        null,
                        $now,
                    );

                    $sisaAmount -= $bayar;
                }

                // 2. Jika Masih Ada Sisa (Surplus), Masukkan ke Saldo Aktif
                if ($sisaAmount > 0) {
                    $rowAktif = null;
                    // If specific wallet ID provided, use that wallet
                    if ($idBukuCuti) {
                        $rowAktif = DB::table('HRIS_Buku_Cuti')
                            ->where('Id_Buku_Cuti', $idBukuCuti)
                            ->where('Kode_Karyawan', $kodeKaryawan)
                            ->where('Id_Jenis_Cuti', $idJenisCuti)
                            ->where('Flag_Aktif', 'Y')
                            ->lockForUpdate()
                            ->first();
                    }

                    // dd($rowAktif, $idBukuCuti);
                    //  else {
                    //     // Otherwise, find wallet with longest expiry for current year
                    //     $rowAktif = DB::table('HRIS_Buku_Cuti')
                    //         ->where('Kode_Karyawan', $kodeKaryawan)
                    //         ->where('Id_Jenis_Cuti', $idJenisCuti)
                    //         ->where('sisa_cuti', '>=', 0)
                    //         ->where('Flag_Aktif', 'Y')
                    //         ->where('Periode', Carbon::now()->year)
                    //         ->whereDate('tanggal_expired', '>=', now())
                    //         ->orderBy('tanggal_expired', 'desc')
                    //         ->first();
                    // }

                    // dd($rowAktif, $idBukuCuti);

                    // Kalau gak punya row aktif, buat baru
                    if ($rowAktif) {
                        $saldoSebelum = $rowAktif->sisa_cuti;
                        DB::table('HRIS_Buku_Cuti')
                            ->where('Id_Buku_Cuti', $rowAktif->Id_Buku_Cuti)
                            ->update([
                                'sisa_cuti' => DB::raw("sisa_cuti + $sisaAmount"),
                                'jumlah_awal_cuti' => DB::raw("jumlah_awal_cuti + $sisaAmount"),
                                'updated_at' => $now,
                            ]);
                        // Refresh row
                        $rowAktif = DB::table('HRIS_Buku_Cuti')
                            ->where('Id_Buku_Cuti', $rowAktif->Id_Buku_Cuti)
                            ->first();
                    } else {
                        $tanggalExpired = $now->copy()->addYear()->endOfYear()->toDateString();

                        if ($idJenisCuti != self::ID_TAHUNAN) {
                            $masaBerlakuBulan = DB::table('N_HRIS_Jenis_Cuti')
                                ->where('Id_Jenis_Cuti', $idJenisCuti)
                                ->value('Masa_Berlaku_Bulan');

                            $tanggalExpired = $now->copy()->addMonths($masaBerlakuBulan ?? 12)->toDateString();
                        }

                        // Buat row baru
                        $newId = DB::table('HRIS_Buku_Cuti')->insertGetId([
                            'Kode_Karyawan' => $kodeKaryawan,
                            'Id_Jenis_Cuti' => $idJenisCuti,
                            'Periode' => $now->year,
                            'tipe_cuti' => DB::table('N_HRIS_Jenis_Cuti')
                                ->where('Id_Jenis_Cuti', $idJenisCuti)
                                ->value('Nama_Jenis_Cuti'),
                            'tanggal_diberikan' => $now->toDateString(),
                            'tanggal_expired' => $tanggalExpired,
                            'jumlah_awal_cuti' => $sisaAmount,
                            'sisa_cuti' => $sisaAmount,
                            'jumlah_terpakai' => 0,
                            'Activated_At' => $activatedAt,
                            'notes' => 'Saldo_Cuti',
                            'Flag_Aktif' => 'Y',
                            'created_at' => $now,
                        ]);

                        $rowAktif = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $newId)->first();
                    }

                    // Include wallet info in log keterangan
                    $walletInfo =
                        "Periode {$rowAktif->Periode} - Exp: ".
                        Carbon::parse($rowAktif->tanggal_expired)->format('d/m/Y');

                    $this->logMutasi(
                        $rowAktif->Id_Buku_Cuti,
                        'MANUAL-ADJUST',
                        'CREDIT',
                        'ADJUSTMENT_ADD',
                        $sisaAmount,
                        $rowAktif->sisa_cuti - $sisaAmount,
                        $rowAktif->sisa_cuti,
                        "Adjust Admin: $alasan ($walletInfo)",
                        $adminId,
                        null,
                        $now,
                    );
                }
            } else {
                // === ADJUSTMENT PENGURANGAN (FIFO) ===
                // Gunakan logika sama dengan ProcessCuti, tapi sumbernya Manual

                $sisaPotong = $amount;
                $dompets = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $kodeKaryawan)
                    ->where('Id_Jenis_Cuti', $idJenisCuti)
                    ->where('sisa_cuti', '>', 0)
                    ->where('Flag_Aktif', 'Y')
                    ->where('Activated_At', '<=', now())
                    ->whereDate('tanggal_expired', '>=', now())
                    ->orderBy('tanggal_expired', 'asc')
                    ->lockForUpdate()
                    ->get();
                if ($dompets->isEmpty()) {
                    // Tidak ada saldo cuti, langsung buat hutang
                    $namaCuti = DB::table('N_HRIS_Jenis_Cuti')
                        ->where('Id_Jenis_Cuti', $idJenisCuti)
                        ->value('Nama_Jenis_Cuti');
                    $this->processDebtCreation(
                        $kodeKaryawan,
                        $idJenisCuti,
                        $sisaPotong,
                        'MANUAL-ADJUST',
                        $namaCuti,
                        $adminId,
                        'ADJUSTMENT_SUB',
                        "Adjust Admin (Hutang): $alasan",
                    );
                    DB::commit();

                    return;
                } else {
                    foreach ($dompets as $d) {
                        if ($sisaPotong <= 0) {
                            break;
                        }
                        $ambil = min($sisaPotong, $d->sisa_cuti);

                        DB::table('HRIS_Buku_Cuti')
                            ->where('Id_Buku_Cuti', $d->Id_Buku_Cuti)
                            ->update([
                                'sisa_cuti' => DB::raw("sisa_cuti - $ambil"),
                                'jumlah_terpakai' => DB::raw("jumlah_terpakai + $ambil"),
                                'updated_at' => $now,
                            ]);

                        $this->logMutasi(
                            $d->Id_Buku_Cuti,
                            'MANUAL-ADJUST',
                            'DEBIT',
                            'ADJUSTMENT_SUB',
                            $ambil,
                            $d->sisa_cuti,
                            $d->sisa_cuti - $ambil,
                            "Adjust Admin (Kurang): $alasan",
                            $adminId,
                            null,
                            $now,
                        );
                        $sisaPotong -= $ambil;
                    }

                    // Jika masih kurang, create hutang (pakai helper tadi)
                    if ($sisaPotong > 0) {
                        $namaCuti = DB::table('N_HRIS_Jenis_Cuti')
                            ->where('Id_Jenis_Cuti', $idJenisCuti)
                            ->value('Nama_Jenis_Cuti');
                        $this->processDebtCreation(
                            $kodeKaryawan,
                            $idJenisCuti,
                            $sisaPotong,
                            'MANUAL-ADJUST',
                            $namaCuti,
                            $adminId,
                            'ADJUSTMENT_SUB',
                            "Adjust Admin (Hutang): $alasan",
                        );
                    }
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            $this->safeRollback();
            throw $e;
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }

    public function notifRequesterFinal($noFaktur, $jenis, $requester)
    {
        $requesterData = DB::table('Karyawan')->where('Kode_Karyawan', $requester)->first();
        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $requesterData->HP,
            'type' => 'template',
            'template' => [
                'name' => 'confirm_izin_diterima',
                'language' => ['code' => 'id', 'policy' => 'deterministic'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $requesterData->Nama],
                            ['type' => 'text', 'text' => $this->parseIzin($jenis).' anda No. '.$noFaktur],
                        ],
                    ],
                ],
            ],
        ];

        $waResult = WhatsappLogHelper::sendAndLog(
            $requesterData->Kode_Karyawan,
            $requesterData->Nama,
            $requesterData->HP,
            $noFaktur,
            $pesan,
            function ($response) use ($requesterData) {
                // DB::table('HRIS_Approval_Request as a')
                // ->where("a.No_Transaksi", $noFaktur)
                // ->where("a.Kode_Karyawan_Approver", $requesterData->Kode_Karyawan)
                // ->update(['a.angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]);

                Log::channel('TransactionService')->info(
                    "NotifRequesterFinal : WA sukses dikirim ke approver {$requesterData->Kode_Karyawan}",
                    [
                        'requester' => $requesterData->Kode_Karyawan,
                        'response' => $response,
                    ],
                );
            },
            function ($reason, $response) use ($requesterData, $noFaktur, $pesan) {
                Log::channel('TransactionService')->warning(
                    "NotifRequesterFinal : Gagal kirim WA ke approver {$requesterData->Kode_Karyawan}",
                    [
                        'reason' => $reason,
                        'response' => $response,
                    ],
                );

                DB::table('Whatsapp_Log')->insert([
                    'Kode_Karyawan_Destination' => $requesterData->Kode_Karyawan,
                    'Nama' => $requesterData->Nama,
                    'HP' => $requesterData->HP,
                    'No_Transaksi' => $noFaktur,
                    'Error_Message' => is_array($response)
                        ? json_encode($response, JSON_UNESCAPED_UNICODE)
                        : $reason ?? 'Unknown error',
                    'RawMessage' => print_r($pesan, true),
                    'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'Created_At' => now(),
                    'Is_Retried' => 0,
                ]);
            },
        );
    }

    public function notifApprovertoApprove($noFaktur, $jenis, $tanggal, $requester, $approver)
    {
        $approverData = DB::table('Karyawan')->where('Kode_Karyawan', $approver)->first();
        $requesterData = DB::table('Karyawan')->where('Kode_Karyawan', $requester)->first();

        // Dipanggil DI DALAM transaksi approval: property access pada null di sini
        // menjadi fatal error yang me-rollback approval yang sah. Gagal dengan
        // tenang + jejak log, jangan menjatuhkan transaksinya.
        if (! $approverData || empty($approverData->HP)) {
            Log::channel('TransactionService')->warning(
                "notifApprovertoApprove: approver tidak valid / HP kosong untuk $noFaktur",
                ['approver' => $approver, 'ada_data' => (bool) $approverData],
            );

            return;
        }

        if (! $requesterData) {
            Log::channel('TransactionService')->warning(
                "notifApprovertoApprove: requester tidak ditemukan untuk $noFaktur",
                ['requester' => $requester],
            );

            return;
        }

        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $approverData->HP,
            'type' => 'template',
            'template' => [
                'name' => 'notif_approver_hcis',
                'language' => [
                    'code' => 'id',
                    'policy' => 'deterministic',
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $approverData->Nama],
                            ['type' => 'text', 'text' => $noFaktur],
                            ['type' => 'text', 'text' => $jenis],
                            ['type' => 'text', 'text' => FormatTanggalHelper::formatTanggalMultiple($tanggal)],
                            ['type' => 'text', 'text' => $requesterData->Nama],
                        ],
                    ],
                ],
            ],
        ];

        $waResult = WhatsappLogHelper::sendAndLog(
            $approverData->Kode_Karyawan,
            $approverData->Nama,
            $approverData->HP,
            $noFaktur,
            $pesan,
            function ($response) use ($approverData, $noFaktur) {
                // DB::table('HRIS_Approval_Request as a')
                //     ->leftJoin('HRIS_Approval_Flow as b', 'a.Flow_Id', 'b.id')
                //     ->where("a.No_Transaksi", $noFaktur)
                //     ->where("a.Approver_Karyawan_Kode", $approverData->Kode_Karyawan)
                //     ->orWhere("b.Kode_Karyawan", $approverData->Kode_Karyawan)
                //     ->update([
                //         'a.angka_wa' => DB::raw('COALESCE(a.angka_wa, 0) + 1')
                //     ]);
                DB::table('HRIS_Approval_Request as a')
                    ->leftJoin('HRIS_Approval_Flow as b', 'a.Flow_Id', 'b.id')
                    ->where('a.No_Transaksi', $noFaktur)
                    ->where(function ($q) use ($approverData) {
                        $q->where('a.Approver_Karyawan_Kode', $approverData->Kode_Karyawan)->orWhere(
                            'b.Kode_Karyawan',
                            $approverData->Kode_Karyawan,
                        );
                    })
                    ->update([
                        'a.angka_wa' => DB::raw('COALESCE(a.angka_wa, 0) + 1'),
                    ]);

                Log::channel('TransactionService')->info(
                    "NotifApproverToApprove: WA sukses dikirim ke approver {$approverData->Kode_Karyawan}",
                    [
                        'requester' => $approverData->Kode_Karyawan,
                        'response' => $response,
                    ],
                );
            },
            function ($reason, $response) use ($approverData, $noFaktur, $pesan) {
                Log::channel('TransactionService')->warning(
                    "NotifApproverToApprove : Gagal kirim WA ke approver {$approverData->Kode_Karyawan}",
                    [
                        'reason' => $reason,
                        'response' => $response,
                    ],
                );

                DB::table('Whatsapp_Log')->insert([
                    'Kode_Karyawan_Destination' => $approverData->Kode_Karyawan,
                    'Nama' => $approverData->Nama,
                    'HP' => $approverData->HP,
                    'No_Transaksi' => $noFaktur,
                    'Error_Message' => is_array($response)
                        ? json_encode($response, JSON_UNESCAPED_UNICODE)
                        : $reason ?? 'Unknown error',
                    'RawMessage' => print_r($pesan, true),
                    'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'Created_At' => now(),
                    'Is_Retried' => 0,
                ]);
            },
        );
    }

    public function notifDitolak($noFaktur, $jenis, $tanggal, $requester, $approver)
    {
        $approverData = DB::table('Karyawan')->where('Kode_Karyawan', $approver)->first();
        $requesterData = DB::table('Karyawan')->where('Kode_Karyawan', $requester)->first();
        if (! $requesterData || empty($requesterData->HP)) {
            Log::channel('TransactionService')->warning("notifDitolak: requester tidak valid untuk $noFaktur", [
                'requester' => $requester,
                'approver' => $approver,
            ]);

            return;
        }

        $namaApprover = $approverData->Nama ?? 'Approver';
        // dd($approverData, $requesterData);
        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $requesterData->HP,
            'type' => 'template',
            'template' => [
                'name' => 'confirm_izin_tolak',
                'language' => [
                    'code' => 'id',
                    'policy' => 'deterministic',
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $requesterData->Nama,
                            ],
                            [
                                'type' => 'text',
                                'text' => $noFaktur.' Pengajuan '.$this->parseIzin($jenis),
                            ],
                            [
                                'type' => 'text',
                                'text' => $namaApprover,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $waResult = WhatsappLogHelper::sendAndLog(
            $requesterData->Kode_Karyawan,
            $requesterData->Nama,
            $requesterData->HP,
            $noFaktur,
            $pesan,
            function ($response) use ($requesterData) {
                // DB::table('HRIS_Approval_Request as a')
                // ->where("a.No_Transaksi", $noFaktur)
                // ->where("a.Kode_Karyawan_Approver", $requesterData->Kode_Karyawan)
                // ->update(['a.angka_wa' => DB::raw('COALESCE(angka_wa, 0) + 1')]);

                Log::channel('TransactionService')->info(
                    "notifDitolak : WA sukses dikirim ke requester {$requesterData->Kode_Karyawan}",
                    [
                        'requester' => $requesterData->Kode_Karyawan,
                        'response' => $response,
                    ],
                );
            },
            function ($reason, $response) use ($requesterData, $noFaktur, $pesan) {
                Log::channel('TransactionService')->warning(
                    "notifDitolak : Gagal kirim WA ke requester {$requesterData->Kode_Karyawan}",
                    [
                        'reason' => $reason,
                        'response' => $response,
                    ],
                );

                DB::table('Whatsapp_Log')->insert([
                    'Kode_Karyawan_Destination' => $requesterData->Kode_Karyawan,
                    'Nama' => $requesterData->Nama,
                    'HP' => $requesterData->HP,
                    'No_Transaksi' => $noFaktur,
                    'Error_Message' => is_array($response)
                        ? json_encode($response, JSON_UNESCAPED_UNICODE)
                        : $reason ?? 'Unknown error',
                    'RawMessage' => print_r($pesan, true),
                    'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'Created_At' => now(),
                    'Is_Retried' => 0,
                ]);
            },
        );
    }

    // =================================================================
    // PRIVATE HELPERS
    // =================================================================

    // private function processCuti($noTransaksi)
    // {
    //     $detail = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $noTransaksi)->first();
    //     if (!$detail) throw new \Exception("Data Cuti tidak ditemukan: $noTransaksi");

    //     $insert = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $noTransaksi)
    //         ->update(['Flag_Approval' => 'Y']);

    //     $jenis = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $detail->Id_Jenis_Cuti)->first();
    //     // dd($jenis);
    //     if ($insert && $jenis && $jenis->Memotong_Saldo_Cuti == 'Y') {

    //         // $start = Carbon::parse($detail->Tanggal_Cuti_Dari);
    //         // $end = Carbon::parse($detail->Tanggal_Cuti_Sampai);
    //         $jumlahHari = DB::table('Transaksi_Cuti_Detail')
    //                     ->where('No_Transaksi', $noTransaksi)
    //                     ->count();

    //         $sisaButuh = $jumlahHari;
    //         // dd($sisaButuh);

    //         // Ambil dompet user, urutkan expired paling dekat
    //         $dompets = DB::table('HRIS_Buku_Cuti')
    //             ->where('Kode_Karyawan', $detail->Kode_Karyawan)
    //             ->where('Id_Jenis_Cuti', $detail->Id_Jenis_Cuti)
    //             ->where('sisa_cuti', '>', 0)
    //             ->where('Flag_Aktif', 'Y')
    //             ->whereDate('tanggal_expired', '>=', now())
    //             ->whereDate('Activated_At', '>=', now())
    //             ->orderBy('tanggal_expired', 'asc')
    //             ->lockForUpdate()
    //             ->get();
    //         // dd($dompets);

    //         foreach ($dompets as $d) {
    //             if ($sisaButuh <= 0) break;

    //             $ambil = min($sisaButuh, $d->sisa_cuti);

    //             // Update Saldo
    //             DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $d->Id_Buku_Cuti)
    //                 ->update([
    //                     'sisa_cuti' => DB::raw("sisa_cuti - $ambil"),
    //                     'jumlah_terpakai' => DB::raw("jumlah_terpakai + $ambil"),
    //                     'updated_at' => now()
    //                 ]);

    //             DB::table('N_HRIS_Log_Buku_Cuti')->insert([
    //                 'Id_Buku_Cuti' => $d->Id_Buku_Cuti,
    //                 'No_Transaksi_Ref' => $noTransaksi,
    //                 'Jenis_Mutasi' => 'DEBIT',
    //                 'Kategori_Transaksi' => 'PENGAJUAN_CUTI',
    //                 'Jumlah' => $ambil,
    //                 'Saldo_Sebelum' => $d->sisa_cuti,
    //                 'Saldo_Sesudah' => $d->sisa_cuti - $ambil,
    //                 'Keterangan' => 'Potong Cuti Approved',
    //                 'Created_By' => 'SYSTEM',
    //                 'Created_At' => now()
    //             ]);

    //             $sisaButuh -= $ambil;
    //         }

    //         if ($sisaButuh > 0) {

    //             $existingDebtRow = DB::table('HRIS_Buku_Cuti')
    //                 ->where('Kode_Karyawan', $detail->Kode_Karyawan)
    //                 ->where('Id_Jenis_Cuti', $detail->Id_Jenis_Cuti)
    //                 ->where('sisa_cuti', '<', 0) // Cari yang minus
    //                 ->where('Flag_Aktif', 'Y')
    //                 ->orderBy('tanggal_expired', 'desc') // Ambil yang paling 'awet'
    //                 ->first();

    //             $idBukuTarget = null;
    //             $saldoSebelum = 0;

    //             if ($existingDebtRow) {
    //                 $idBukuTarget = $existingDebtRow->Id_Buku_Cuti;
    //                 $saldoSebelum = $existingDebtRow->sisa_cuti;

    //                 DB::table('HRIS_Buku_Cuti')
    //                     ->where('Id_Buku_Cuti', $idBukuTarget)
    //                     ->update([
    //                         'sisa_cuti' => DB::raw("sisa_cuti - $sisaButuh"),
    //                         'updated_at' => now(),
    //                         'updated_by' => Auth::user()->karyawan->Kode_Karyawan
    //                     ]);

    //             } else {
    //                 $saldoSebelum = 0;

    //                 $idBukuTarget = DB::table('HRIS_Buku_Cuti')->insertGetId([
    //                     'Kode_Karyawan'     => $detail->Kode_Karyawan,
    //                     'Id_Jenis_Cuti'     => $detail->Id_Jenis_Cuti,
    //                     'tanggal_diberikan' => now(),
    //                     'tipe_cuti'         => $jenis->Nama_Jenis_Cuti,
    //                     'tanggal_expired'   => now()->addYears(10),
    //                     'Activated_At'      => now(),
    //                     'jumlah_awal_cuti'  => 0, // Awal 0
    //                     'sisa_cuti'         => -$sisaButuh,
    //                     'jumlah_terpakai'   => 0,
    //                     'notes'             => "Kantong Hutang Cuti",
    //                     'Flag_Aktif'        => 'Y',
    //                     'created_at'        => now(),
    //                     'created_by'        => 'SYSTEM'
    //                 ]);
    //             }

    //             DB::table('N_HRIS_Log_Buku_Cuti')->insert([
    //                 'Id_Buku_Cuti'       => $idBukuTarget,
    //                 'No_Transaksi_Ref'   => $noTransaksi,
    //                 'Jenis_Mutasi'       => 'DEBIT',
    //                 'Kategori_Transaksi' => 'HUTANG_CUTI',
    //                 'Jumlah'             => $sisaButuh,
    //                 'Saldo_Sebelum'      => $saldoSebelum,
    //                 'Saldo_Sesudah'      => $saldoSebelum - $sisaButuh,
    //                 'Keterangan'         => "Penambahan Hutang dari Transaksi $noTransaksi",
    //                 'Created_By'         => 'SYSTEM',
    //                 'Created_At'         => now()
    //             ]);

    //             Log::info("User {$detail->Kode_Karyawan} menambah hutang sebesar $sisaButuh hari.");
    //         }
    //     }

    //     if($insert){
    //         $this->notifRequesterFinal(
    //             $noTransaksi,
    //             $detail->Jenis,
    //             $detail->Kode_Karyawan
    //         );
    //         }
    // }

    // private function processCuti($noTransaksi)
    // {
    //     $detail = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $noTransaksi)->first();
    //     if (!$detail) throw new \Exception("Data Cuti tidak ditemukan: $noTransaksi");

    //     // 1. Update Status
    //     $insert = DB::table('Transaksi_Cuti_Detail')
    //         ->where('No_Transaksi', $noTransaksi)
    //         ->update(['Flag_Approval' => 'Y']);

    //     // 2. Cek Jenis Cuti
    //     $jenis = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $detail->Id_Jenis_Cuti)->first();

    //     if ($insert && $jenis && $jenis->Memotong_Saldo_Cuti == 'Y') {

    //         // Hitung Jumlah Hari (Bisa dari count row detail atau kolom total)
    //         $jumlahHari = DB::table('Transaksi_Cuti_Det')
    //                         ->where('No_Transaksi', $noTransaksi)
    //                         ->count();

    //         $sisaButuh = $jumlahHari;

    //          // AMBIL DOMPET (FIFO)

    //         $targetIds = [$detail->Id_Jenis_Cuti];
    //         if ($detail->Id_Jenis_Cuti == self::ID_TAHUNAN) {
    //             $targetIds = [self::ID_TAHUNAN, self::ID_LOYALTY];
    //         }

    //         $dompets = DB::table('HRIS_Buku_Cuti')
    //             ->where('Kode_Karyawan', $detail->Kode_Karyawan)
    //             ->whereIn('Id_Jenis_Cuti', $targetIds)
    //             ->where('sisa_cuti', '>', 0)
    //             ->where('Flag_Aktif', 'Y')
    //             ->whereDate('tanggal_expired', '>=', now())
    //             ->whereDate('Activated_At', '<=', now())
    //             ->orderBy('tanggal_expired', 'asc')
    //             ->lockForUpdate()
    //             ->get();

    //         foreach ($dompets as $d) {
    //             if ($sisaButuh <= 0) break;

    //             $ambil = min($sisaButuh, $d->sisa_cuti);

    //             DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $d->Id_Buku_Cuti)
    //                 ->update([
    //                     'sisa_cuti' => DB::raw("sisa_cuti - $ambil"),
    //                     'jumlah_terpakai' => DB::raw("jumlah_terpakai + $ambil"),
    //                     'updated_at' => now()
    //                 ]);

    //             DB::table('N_HRIS_Log_Buku_Cuti')->insert([
    //                 'Id_Buku_Cuti' => $d->Id_Buku_Cuti,
    //                 'No_Transaksi_Ref' => $noTransaksi,
    //                 'Jenis_Mutasi' => 'DEBIT',
    //                 'Kategori_Transaksi' => 'PENGAJUAN_CUTI',
    //                 'Jumlah' => $ambil,
    //                 'Saldo_Sebelum' => $d->sisa_cuti,
    //                 'Saldo_Sesudah' => $d->sisa_cuti - $ambil,
    //                 'Keterangan' => 'Potong Cuti Approved (FIFO)',
    //                 'Created_By' => 'SYSTEM',
    //                 'Created_At' => now()
    //             ]);

    //             $sisaButuh -= $ambil;
    //         }

    //         // B. HANDLING HUTANG (JIKA SALDO KURANG)
    //         if ($sisaButuh > 0) {
    //             $this->processDebtCreation($detail->Kode_Karyawan, $detail->Id_Jenis_Cuti, $sisaButuh, $noTransaksi, $jenis->Nama_Jenis_Cuti);
    //         }
    //     }

    //     if($insert){
    //         $this->notifRequesterFinal(
    //             $noTransaksi,
    //             $detail->Jenis,
    //             $detail->Kode_Karyawan
    //         );
    //         }
    // }

    private function processCuti($noTransaksi)
    {
        $detail = DB::transaction(function () use ($noTransaksi) {
            $detail = DB::table('Transaksi_Cuti_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->lockForUpdate()
                ->first();

            if (! $detail) {
                throw new \Exception("Data Cuti tidak ditemukan: $noTransaksi");
            }

            $insert = DB::table('Transaksi_Cuti_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->update(['Flag_Approval' => 'Y']);

            $jenis = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $detail->Id_Jenis_Cuti)->first();

            if ($insert && $jenis && $jenis->Memotong_Saldo_Cuti == 'Y') {
                $jumlahHari = DB::table('Transaksi_Cuti_Det')->where('No_Transaksi', $noTransaksi)->count();

                $this->debitBalanceWithDebt(
                    $detail->Kode_Karyawan,
                    (int) $detail->Id_Jenis_Cuti,
                    (float) $jumlahHari,
                    $noTransaksi,
                    'PENGAJUAN_CUTI',
                    'Potong Cuti Approved',
                    'SYSTEM',
                    $jenis->Nama_Jenis_Cuti,
                );
            }

            return $insert ? $detail : null;
        });

        if ($detail) {
            MarkAttendanceDirtyJob::dispatch(
                $noTransaksi,
                'CUTI_APPROVED',
                'TRANSACTION_SERVICE_FINAL_APPROVE',
                1,
                app(AttendanceDirtyResolverService::class)->requestedBy(),
            );

            $this->notifRequesterFinal($noTransaksi, $detail->Jenis, $detail->Kode_Karyawan);
        }
    }

    private function processIzin($noTransaksi)
    {
        $insert = DB::table('Transaksi_Sakit_Izin_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->update(['Flag_Approval' => 'Y']);

        if ($insert) {
            $detail = DB::table('Transaksi_Sakit_Izin_Detail')->where('No_Transaksi', $noTransaksi)->first();

            MarkAttendanceDirtyJob::dispatch(
                $noTransaksi,
                'IZIN_SAKIT_APPROVED',
                'TRANSACTION_SERVICE_FINAL_APPROVE',
                1,
                app(AttendanceDirtyResolverService::class)->requestedBy(),
            );

            $this->notifRequesterFinal($noTransaksi, $detail->Jenis, $detail->Kode_Karyawan);
        }
    }

    private function parseIzin($jenis)
    {
        switch ($jenis) {
            case 'sakit':
                return 'Izin Sakit';
            case 'izinFull':
                return 'Izin Pribadi';
            case 'cuti':
                return 'Cuti';
            case 'terlambat':
                return 'Izin Terlambat';
            case 'pulangCepat':
                return 'Izin Pulang Cepat';
            default:
                return $jenis;
        }
    }

    private function processLembur($noTransaksi)
    {
        DB::table('Transaksi_Lembur_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->update(['Status' => 'Y', 'updated_at' => now()]);

        MarkAttendanceDirtyJob::dispatch(
            $noTransaksi,
            'LEMBUR_APPROVED',
            'TRANSACTION_SERVICE_FINAL_APPROVE',
            1,
            app(AttendanceDirtyResolverService::class)->requestedBy(),
        );
    }

    private function proccessTerpulang($noTransaksi)
    {
        $insert = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
            ->where('No_Transaksi', $noTransaksi)
            ->update(['Flag_Approval' => 'Y']);
        if ($insert) {
            $detail = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')
                ->where('No_Transaksi', $noTransaksi)
                ->first();

            MarkAttendanceDirtyJob::dispatch(
                $noTransaksi,
                'TP_APPROVED',
                'TRANSACTION_SERVICE_FINAL_APPROVE',
                1,
                app(AttendanceDirtyResolverService::class)->requestedBy(),
            );

            $this->notifRequesterFinal($noTransaksi, $detail->Jenis, $detail->Kode_Karyawan);
        }
    }

    private function processManualAdd($kode, $idJenis, $amount, $alasan, $adminId)
    {
        $this->manualAdjustment($kode, $idJenis, 'ADD', $amount, $alasan, $adminId, now()->toDateString());
    }

    // Helper untuk Manual Adjust Subtract (Reuse logic FIFO)
    private function processManualDeduction($kode, $idJenis, $amount, $alasan, $adminId)
    {
        $this->manualAdjustment($kode, $idJenis, 'SUB', $amount, $alasan, $adminId);
    }

    // public function getNoTransaction($namaTB, $prefix, $kolom, $datetime)
    // {
    //     if (!Schema::hasTable($namaTB)) {
    //         throw new \Exception("Tabel {$namaTB} tidak ditemukan.");
    //     }

    //     if (!$prefix) {
    //         throw new \Exception("Prefix tidak boleh kosong.");
    //     }

    //     if (!$kolom) {
    //         throw new \Exception("Kolom tidak boleh kosong.");
    //     }

    //     $formatTanggalSekarang = $datetime
    //         ? $datetime
    //         : Carbon::now()->format('m-y');

    //     $prefix = $prefix . $formatTanggalSekarang . "-";

    //     try {
    //         $checkLastFaktur = DB::table($namaTB)
    //             ->select(DB::raw('RIGHT('.$kolom.', 4) AS angka'))
    //             ->where($kolom, 'like', $prefix . '%')
    //             ->lockForUpdate()
    //             ->orderByDesc(DB::raw('RIGHT('.$kolom.', 4)'))
    //             ->first();
    //     } catch (\Throwable $e) {
    //         throw new \Exception("Gagal mengambil data transaksi: " . $e->getMessage());
    //     }

    //     $angkaTerakhir = $checkLastFaktur
    //         ? (int)$checkLastFaktur->angka + 1
    //         : 1;

    //     return $prefix . sprintf('%04d', $angkaTerakhir);
    // }

    public function getNoTransaction($namaTB, $prefix, $kolom, $datetime = null)
    {
        /* Dipanggil setiap kali nomor transaksi dibuat - jadi setiap pengajuan
           cuti/izin/lembur/shift ikut membayarnya. Jawabannya tetap. */
        if (! SkemaTersedia::tabel($namaTB)) {
            throw new \Exception("Tabel {$namaTB} tidak ditemukan.");
        }

        if (! $prefix || ! $kolom) {
            throw new \Exception('Prefix dan kolom tidak boleh kosong.');
        }

        $formatTanggal = $datetime ?: Carbon::now()->format('m-y');
        $prefixFinal = $prefix.$formatTanggal.'-';

        return DB::transaction(function () use ($namaTB, $kolom, $prefixFinal) {
            $last = DB::table($namaTB)
                ->select(DB::raw("RIGHT($kolom, 4) AS angka"))
                ->where($kolom, 'like', $prefixFinal.'%')
                ->lockForUpdate()
                ->orderByDesc(DB::raw("RIGHT($kolom, 4)"))
                ->first();

            $next = $last ? (int) $last->angka + 1 : 1;

            return $prefixFinal.str_pad($next, 4, '0', STR_PAD_LEFT);
        });
    }

    // private function processDebtCreation($kodeKaryawan, $idJenisCuti, $jumlahHutang, $noRef, $namaJenis)
    // {
    //     // Cek apakah sudah punya kantong hutang aktif?
    //     $existingDebtRow = DB::table('HRIS_Buku_Cuti')
    //         ->where('Kode_Karyawan', $kodeKaryawan)
    //         ->where('Id_Jenis_Cuti', $idJenisCuti)
    //         ->where('sisa_cuti', '<', 0)
    //         ->where('Flag_Aktif', 'Y')
    //         ->orderBy('tanggal_expired', 'desc')
    //         ->first();

    //     $idBukuTarget = null;
    //     $saldoSebelum = 0;

    //     if ($existingDebtRow) {
    //         $idBukuTarget = $existingDebtRow->Id_Buku_Cuti;
    //         $saldoSebelum = $existingDebtRow->sisa_cuti;

    //         DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBukuTarget)
    //             ->update([
    //                 'sisa_cuti' => DB::raw("sisa_cuti - $jumlahHutang"), // Tambah minus
    //                 'updated_at' => now()
    //             ]);
    //     } else {
    //         $saldoSebelum = 0;
    //         $idBukuTarget = DB::table('HRIS_Buku_Cuti')->insertGetId([
    //             'Kode_Karyawan'     => $kodeKaryawan,
    //             'Id_Jenis_Cuti'     => $idJenisCuti,
    //             'tipe_cuti'         => $namaJenis,
    //             'tanggal_diberikan' => now(),
    //             'tanggal_expired'   => now()->addYears(10), // Hutang tahan lama
    //             'Activated_At'      => now(),
    //             'jumlah_awal_cuti'  => 0,
    //             'sisa_cuti'         => -$jumlahHutang, // Minus
    //             'jumlah_terpakai'   => 0,
    //             'notes'             => "Kantong Hutang Cuti",
    //             'Flag_Aktif'        => 'Y',
    //             'created_at'        => now(),
    //             'created_by'        => 'SYSTEM'
    //         ]);
    //     }

    //     DB::table('N_HRIS_Log_Buku_Cuti')->insert([
    //         'Id_Buku_Cuti' => $idBukuTarget,
    //         'No_Transaksi_Ref' => $noRef,
    //         'Jenis_Mutasi' => 'DEBIT',
    //         'Kategori_Transaksi' => 'HUTANG_CUTI',
    //         'Jumlah' => $jumlahHutang,
    //         'Saldo_Sebelum' => $saldoSebelum,
    //         'Saldo_Sesudah' => $saldoSebelum - $jumlahHutang,
    //         'Keterangan' => "Hutang dari Transaksi $noRef",
    //         'Created_By' => 'SYSTEM',
    //         'Created_At' => now()
    //     ]);
    // }

    public function creditBalance(
        $kodeKaryawan,
        $idJenisCuti,
        $amount,
        $refNo,
        $kategori,
        $reason,
        $actor,
        $customActivatedAt = null,
        $expiredDate = null,
        $periode = null,
        $cutiKey = null,
        $effectiveDate = null,
    ) {
        if ($cutiKey) {
            return DB::transaction(function () use (
                $kodeKaryawan,
                $idJenisCuti,
                $amount,
                $refNo,
                $kategori,
                $reason,
                $actor,
                $customActivatedAt,
                $expiredDate,
                $periode,
                $cutiKey,
                $effectiveDate
            ) {
                $alreadyExists = DB::table('N_HRIS_Log_Buku_Cuti')
                    ->where('Cuti_Key', $cutiKey)
                    ->lockForUpdate()
                    ->exists();

                if ($alreadyExists) {
                    return false;
                }

                return $this->performCreditBalance(
                    $kodeKaryawan,
                    $idJenisCuti,
                    $amount,
                    $refNo,
                    $kategori,
                    $reason,
                    $actor,
                    $customActivatedAt,
                    $expiredDate,
                    $periode,
                    $cutiKey,
                    $effectiveDate,
                );
            });
        }

        return $this->performCreditBalance(
            $kodeKaryawan,
            $idJenisCuti,
            $amount,
            $refNo,
            $kategori,
            $reason,
            $actor,
            $customActivatedAt,
            $expiredDate,
            $periode,
            null,
            $effectiveDate,
        );
    }

    private function performCreditBalance(
        $kodeKaryawan,
        $idJenisCuti,
        $amount,
        $refNo,
        $kategori,
        $reason,
        $actor,
        $customActivatedAt = null,
        $expiredDate = null,
        $periode = null,
        $cutiKey = null,
        $effectiveDate = null,
    ) {
        $now = FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
        $tanggalDiberikan = $effectiveDate ? Carbon::parse($effectiveDate) : $now;
        $sisaAmount = $amount;

        $activatedAt = $customActivatedAt ? Carbon::parse($customActivatedAt) : $now;
        $isFutureBalance = $activatedAt->gt($now);
        // dd($activatedAt->gt($now));

        // jika belum aktif maka skip byar hutang
        if (! $isFutureBalance) {
            $attachKeyToDebtLog = false;
            if ($cutiKey) {
                $debtBeforeCredit = DB::table('HRIS_Buku_Cuti')
                    ->where('Kode_Karyawan', $kodeKaryawan)
                    ->where('Id_Jenis_Cuti', $idJenisCuti)
                    ->where('sisa_cuti', '<', 0)
                    ->where('Flag_Aktif', 'Y')
                    ->sum('sisa_cuti');
                $attachKeyToDebtLog = abs((float) $debtBeforeCredit) >= (float) $sisaAmount;
            }

            // Cek Hutang Dulu (Hanya jika saldo ini cair sekarang)
            $terbayar = $this->repayDebt(
                $kodeKaryawan,
                $sisaAmount,
                $refNo,
                'PAY_DEBT',
                "Pelunasan Otomatis: $reason",
                $actor,
                $idJenisCuti,
                $attachKeyToDebtLog ? $cutiKey : null,
            );

            $sisaAmount -= $terbayar;
        }

        // ============================================================
        // LOGIC PENYIMPANAN SALDO
        // ============================================================

        // Jika masih ada sisa (atau karena ini future balance jadi belum dipakai bayar hutang)
        if ($sisaAmount > 0) {
            $queryRow = DB::table('HRIS_Buku_Cuti')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Id_Jenis_Cuti', $idJenisCuti)
                ->where('sisa_cuti', '>=', 0)
                ->where('Flag_Aktif', 'Y');

            if ($isFutureBalance) {
                // Jika future, cari yang activated_at nya sama persis (atau berdekatan)
                $queryRow->whereDate('Activated_At', $activatedAt->toDateString());
            } else {
                // Jika cair sekarang, cari yang sudah aktif
                $queryRow->whereDate('Activated_At', '<=', $now);
            }

            if ($periode) {
                $queryRow->where('Periode', $periode);
            }

            if ($expiredDate) {
                $queryRow->whereDate('tanggal_expired', Carbon::parse($expiredDate)->toDateString());
            } else {
                // Jika tidak ada expired date khusus, kita asumsikan expirednya 1 tahun dari activated_at
                /* Rentang setengah-terbuka: YEAR(tanggal_expired) mematikan indeks. */
                $queryRow->where('tanggal_expired', '>=', Carbon::create($now->year + 1, 1, 1)->startOfDay())
                    ->where('tanggal_expired', '<', Carbon::create($now->year + 2, 1, 1)->startOfDay());
            }

            // if($idJenisCuti == 1){
            //     $queryRow->where('Periode', $now->year)
            //             ->whereYear('tanggal_expired', $now->year + 1);
            // }

            $rowTarget = $queryRow->orderBy('tanggal_expired', 'desc')->lockForUpdate()->first();
            // dd($rowTarget);

            $targetId = null;
            $saldoAwal = 0;

            if ($rowTarget) {
                // Tambah ke row yang ada
                $targetId = $rowTarget->Id_Buku_Cuti;
                $saldoAwal = $rowTarget->sisa_cuti;

                DB::table('HRIS_Buku_Cuti')
                    ->where('Id_Buku_Cuti', $targetId)
                    ->update([
                        'sisa_cuti' => DB::raw("sisa_cuti + $sisaAmount"),
                        'jumlah_awal_cuti' => DB::raw("jumlah_awal_cuti + $sisaAmount"),
                        'updated_at' => $now,
                    ]);
            } else {
                $namaCuti = DB::table('N_HRIS_Jenis_Cuti')
                    ->where('Id_Jenis_Cuti', $idJenisCuti)
                    ->value('Nama_Jenis_Cuti');

                $expiredDateChoose = $expiredDate ? Carbon::parse($expiredDate) : $now->copy()->addYear();

                $targetId = DB::table('HRIS_Buku_Cuti')->insertGetId([
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Id_Jenis_Cuti' => $idJenisCuti,
                    'Periode' => $periode ? $periode : null,
                    'tipe_cuti' => $namaCuti,
                    'notes' => $kategori,
                    'tanggal_diberikan' => $tanggalDiberikan,
                    'tanggal_expired' => $expiredDateChoose,
                    'jumlah_awal_cuti' => $sisaAmount,
                    'sisa_cuti' => $sisaAmount,
                    'Flag_Aktif' => 'Y',
                    'Activated_At' => $activatedAt, // Kuncinya disini
                    'created_at' => $now,
                ]);
            }

            $ket = $reason;
            if ($isFutureBalance) {
                $ket .= ' (Aktif pada '.$activatedAt->format('d-M-Y').')';
            }

            $this->logMutasi(
                $targetId,
                $refNo,
                'CREDIT',
                $kategori,
                $sisaAmount,
                $saldoAwal,
                $saldoAwal + $sisaAmount,
                $ket,
                $actor,
                $cutiKey,
                $now,
            );
        }

        return true;
    }

    public function debitBalance($kodeKaryawan, $targetJenisIds, $amount, $refNo, $kategori, $reason, $actor, $asOfDate = null)
    {
        $now = $asOfDate ? Carbon::parse($asOfDate) : FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
        $asOfDateString = $now->toDateString();
        if (! is_array($targetJenisIds)) {
            $targetJenisIds = [$targetJenisIds];
        }
        $sisaPotong = $amount;

        // Ambil saldo available (FIFO)
        $dompets = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereIn('Id_Jenis_Cuti', $targetJenisIds)
            ->where('sisa_cuti', '>', 0)
            ->where('Flag_Aktif', 'Y')
            ->whereDate('tanggal_expired', '>=', $asOfDateString)
            ->whereDate('Activated_At', '<=', $asOfDateString)
            ->orderBy('tanggal_expired', 'asc') // FIFO
            ->lockForUpdate()
            ->get();

        foreach ($dompets as $d) {
            if ($sisaPotong <= 0) {
                break;
            }

            $ambil = min($sisaPotong, $d->sisa_cuti);

            $this->applyWalletMutation(
                $d->Id_Buku_Cuti,
                [
                    'sisa_cuti' => DB::raw("sisa_cuti - $ambil"),
                    'jumlah_terpakai' => DB::raw("jumlah_terpakai + $ambil"),
                    'updated_at' => $now,
                ],
                'DEBIT',
                $kategori,
                $refNo,
                $ambil,
                $d->sisa_cuti,
                $d->sisa_cuti - $ambil,
                $reason,
                $actor,
                null,
                $now,
            );

            $sisaPotong -= $ambil;
        }

        return $sisaPotong; // Kembalikan sisa yang tidak tercover
    }

    public function debitBalanceWithDebt(
        $kodeKaryawan,
        $idJenisCuti,
        $amount,
        $refNo,
        $kategori,
        $reason,
        $actor,
        $namaJenis = null,
    ) {
        $targetJenisIds = [(int) $idJenisCuti];
        if ((int) $idJenisCuti === self::ID_TAHUNAN) {
            $targetJenisIds[] = self::ID_LOYALTY;
        }

        $remaining = $this->debitBalance($kodeKaryawan, $targetJenisIds, $amount, $refNo, $kategori, $reason, $actor);

        if ($remaining > 0) {
            if (! $namaJenis) {
                $namaJenis =
                    DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $idJenisCuti)->value('Nama_Jenis_Cuti') ??
                    'Tahunan';
            }

            // Debt overflow HARUS berkategori HUTANG_CUTI (bukan $kategori operasi mis. PENGAJUAN_CUTI)
            // agar terhitung di getHutangTerpakai / jatah hutang. $reason tetap dibawa untuk audit.
            $this->processDebtCreation($kodeKaryawan, $idJenisCuti, $remaining, $refNo, $namaJenis, $actor, 'HUTANG_CUTI', $reason);
        }

        return $remaining;
    }

    /** Kategori ledger untuk pengembalian saldo cuti yang dibatalkan admin. */
    public const KATEGORI_PEMBATALAN = 'PEMBATALAN_CUTI_ROLLBACK';

    /**
     * Kembalikan saldo cuti yang sudah terlanjur dipotong saat approval final,
     * karena transaksinya dibatalkan admin. Kebalikan persis debitBalanceWithDebt().
     *
     * SENGAJA BUKAN creditBalance(): creditBalance melunasi hutang lebih dulu dan
     * menaikkan jumlah_awal_cuti — itu API akrual (saldo BARU), bukan pembalikan.
     * Refund harus kembali ke DOMPET ASAL yang dulu didebit, kalau tidak rantai
     * Saldo_Sebelum→Saldo_Sesudah per dompet putus dan VerifyCutiBalance menjerit.
     *
     * Dompet asal direkonstruksi dari jurnal (satu-satunya catatan dompet mana yang
     * dipotong & berapa) — cuti reguler tak punya tabel alokasi seperti cuti bersama.
     *
     * Kategori WAJIB mengandung 'ROLLBACK': getHutangTerpakai() mengecualikan kredit
     * '%ROLLBACK%' dari hutang GROSS, jadi hutang dari cuti yang dibatalkan tidak
     * ikut membebani kuota pinjam karyawan.
     *
     * @return array{refunded: float, wallets: int, already: bool}
     */
    public function refundCutiTransaction(string $noTransaksi, string $actor, string $keterangan): array
    {
        $kosong = ['refunded' => 0.0, 'wallets' => 0, 'already' => false];

        // Idempotensi: sudah pernah di-refund → jangan dobel.
        $sudah = DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('No_Transaksi_Ref', $noTransaksi)
            ->where('Kategori_Transaksi', self::KATEGORI_PEMBATALAN)
            ->exists();

        if ($sudah) {
            return ['refunded' => 0.0, 'wallets' => 0, 'already' => true];
        }

        $debits = $this->cutiDebitLedgerRows($noTransaksi);

        if ($debits->isEmpty()) {
            return $kosong;
        }

        $now = FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
        $totalRefund = 0.0;
        $walletCount = 0;

        foreach ($debits as $debit) {
            $jumlah = abs((float) $debit->Jumlah);
            if ($jumlah <= 0) {
                continue;
            }

            // Saldo dibaca ULANG di bawah lock, bukan pakai Saldo_Sesudah dari jurnal:
            // dompet bisa saja bermutasi setelah debit ini. Rantai ledger harus menyambung
            // ke saldo TERKINI, bukan ke saldo saat debit dulu.
            $saldoSebelum = (float) (DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $debit->Id_Buku_Cuti)
                ->lockForUpdate()
                ->value('sisa_cuti') ?? 0);

            $walletUpdate = [
                'sisa_cuti' => DB::raw("sisa_cuti + $jumlah"),
                'updated_at' => $now,
            ];

            // jumlah_terpakai hanya dinaikkan saat debit dari dompet POSITIF
            // (debitBalance). Luapan ke dompet hutang (processDebtCreation) tidak
            // pernah menyentuhnya — jadi jangan dikurangi di sini, nanti minus.
            if ((float) $debit->Saldo_Sebelum >= 0) {
                $walletUpdate['jumlah_terpakai'] = DB::raw("jumlah_terpakai - $jumlah");
            }

            $this->applyWalletMutation(
                (int) $debit->Id_Buku_Cuti,
                $walletUpdate,
                'CREDIT',
                self::KATEGORI_PEMBATALAN,
                $noTransaksi,
                $jumlah,
                $saldoSebelum,
                $saldoSebelum + $jumlah,
                $keterangan,
                $actor,
                null,
                $now,
            );

            $totalRefund += $jumlah;
            $walletCount++;
        }

        return ['refunded' => $totalRefund, 'wallets' => $walletCount, 'already' => false];
    }

    /**
     * Baris ledger DEBIT milik satu transaksi cuti = dompet mana saja yang dulu
     * dipotong dan berapa. Dipakai refundCutiTransaction() untuk mengeksekusi dan
     * IzinCancellationService untuk memperlihatkan preview refund ke admin.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public function cutiDebitLedgerRows(string $noTransaksi): Collection
    {
        return collect(DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('No_Transaksi_Ref', $noTransaksi)
            ->where('Jenis_Mutasi', 'DEBIT')
            ->orderBy('Id_Log')
            ->get(['Id_Log', 'Id_Buku_Cuti', 'Jumlah', 'Saldo_Sebelum', 'Saldo_Sesudah', 'Kategori_Transaksi']));
    }

    /** Kategori ledger kredit konversi lembur -> cuti (applyApprovedTransaction). */
    public const KATEGORI_KONVERSI_CUTI = 'KONVERSI_TO_CUTI';

    /** Kategori ledger penarikan kembali kredit konversi yang dibatalkan admin. */
    public const KATEGORI_BATAL_KONVERSI_CUTI = 'KONVERSI_TO_CUTI_BATAL';

    /**
     * Rencana pembalik kredit konversi lembur -> cuti. Murni: tanpa basis data.
     *
     * Kredit konversi tercatat sebagai CREDIT dalam dua bentuk, dan pembaliknya
     * mencerminkan masing-masing di dompet yang SAMA:
     *  - Saldo_Sebelum < 0  = pembayaran hutang  -> hutangnya dipulihkan.
     *  - Saldo_Sebelum >= 0 = tambahan jatah     -> jatahnya ditarik; bagian yang
     *    sudah terpakai tidak bisa ditarik dan menjadi hutang baru.
     *
     * Saldo dibaca berjalan: dua kredit di dompet yang sama memakai saldo setelah
     * langkah sebelumnya, bukan saldo awal dua kali.
     *
     * @param  iterable<object{Id_Buku_Cuti:int, Jumlah:float, Saldo_Sebelum:float}>  $kredit
     * @param  array<int, float>  $saldoPerDompet  sisa_cuti terkini per Id_Buku_Cuti
     * @return array{langkah: list<array{id_buku:int, jenis:string, jumlah:float, saldo_sebelum:?float, saldo_sesudah:?float}>, hutang_dipulihkan: float, jatah_ditarik: float, hutang_baru: float}
     */
    public static function rencanaBalikKreditKonversi(iterable $kredit, array $saldoPerDompet): array
    {
        $saldo = array_map('floatval', $saldoPerDompet);
        $langkah = [];
        $hutangDipulihkan = 0.0;
        $jatahDitarik = 0.0;
        $hutangBaru = 0.0;

        foreach ($kredit as $k) {
            $id = (int) $k->Id_Buku_Cuti;
            $jumlah = round(abs((float) $k->Jumlah), 2);
            if ($jumlah <= 0) {
                continue;
            }
            $sekarang = $saldo[$id] ?? 0.0;

            if ((float) $k->Saldo_Sebelum < 0) {
                $sesudah = round($sekarang - $jumlah, 2);
                $langkah[] = ['id_buku' => $id, 'jenis' => 'HUTANG_DIPULIHKAN', 'jumlah' => $jumlah, 'saldo_sebelum' => $sekarang, 'saldo_sesudah' => $sesudah];
                $saldo[$id] = $sesudah;
                $hutangDipulihkan += $jumlah;

                continue;
            }

            $ambil = round(max(0.0, min($jumlah, $sekarang)), 2);
            if ($ambil > 0) {
                $sesudah = round($sekarang - $ambil, 2);
                $langkah[] = ['id_buku' => $id, 'jenis' => 'JATAH_DITARIK', 'jumlah' => $ambil, 'saldo_sebelum' => $sekarang, 'saldo_sesudah' => $sesudah];
                $saldo[$id] = $sesudah;
                $jatahDitarik += $ambil;
            }

            $kurang = round($jumlah - $ambil, 2);
            if ($kurang > 0) {
                $langkah[] = ['id_buku' => $id, 'jenis' => 'HUTANG_BARU', 'jumlah' => $kurang, 'saldo_sebelum' => null, 'saldo_sesudah' => null];
                $hutangBaru += $kurang;
            }
        }

        return [
            'langkah' => $langkah,
            'hutang_dipulihkan' => round($hutangDipulihkan, 2),
            'jatah_ditarik' => round($jatahDitarik, 2),
            'hutang_baru' => round($hutangBaru, 2),
        ];
    }

    /** @return Collection<int, object> baris CREDIT konversi lembur -> cuti milik satu transaksi */
    public function kreditKonversiLedgerRows(string $noTransaksi): Collection
    {
        return collect(DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('No_Transaksi_Ref', $noTransaksi)
            ->where('Jenis_Mutasi', 'CREDIT')
            ->where('Kategori_Transaksi', self::KATEGORI_KONVERSI_CUTI)
            ->orderBy('Id_Log')
            ->get(['Id_Log', 'Id_Buku_Cuti', 'Jumlah', 'Saldo_Sebelum']));
    }

    /**
     * Pratinjau pembalik untuk ditampilkan ke admin SEBELUM konfirmasi. Tidak menulis.
     *
     * @return array<string, mixed>
     */
    public function pratinjauBalikKreditKonversi(string $noTransaksi): array
    {
        $kredit = $this->kreditKonversiLedgerRows($noTransaksi);
        $saldo = $kredit->isEmpty() ? [] : DB::table('HRIS_Buku_Cuti')
            ->whereIn('Id_Buku_Cuti', $kredit->pluck('Id_Buku_Cuti')->unique()->values()->all())
            ->pluck('sisa_cuti', 'Id_Buku_Cuti')
            ->map(fn ($v) => (float) $v)
            ->all();

        return self::rencanaBalikKreditKonversi($kredit, $saldo) + [
            'already' => DB::table('N_HRIS_Log_Buku_Cuti')
                ->where('No_Transaksi_Ref', $noTransaksi)
                ->where('Kategori_Transaksi', self::KATEGORI_BATAL_KONVERSI_CUTI)
                ->exists(),
        ];
    }

    /**
     * Tarik kembali kredit konversi lembur -> cuti yang dibatalkan admin.
     * WAJIB dipanggil di dalam transaksi milik pemanggil. Idempoten.
     *
     * Semua tulisan lewat applyWalletMutation()/processDebtCreation() sehingga rantai
     * Saldo_Sebelum -> Saldo_Sesudah per dompet tetap menyambung (cuti:verify).
     *
     * @return array<string, mixed>
     */
    public function balikKreditKonversiCuti(string $noTransaksi, string $kodeKaryawan, string $actor, string $keterangan): array
    {
        if (DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('No_Transaksi_Ref', $noTransaksi)
            ->where('Kategori_Transaksi', self::KATEGORI_BATAL_KONVERSI_CUTI)
            ->exists()) {
            return self::rencanaBalikKreditKonversi([], []) + ['already' => true];
        }

        $kredit = $this->kreditKonversiLedgerRows($noTransaksi);
        if ($kredit->isEmpty()) {
            return self::rencanaBalikKreditKonversi([], []) + ['already' => false];
        }

        $dompet = DB::table('HRIS_Buku_Cuti')
            ->whereIn('Id_Buku_Cuti', $kredit->pluck('Id_Buku_Cuti')->unique()->values()->all())
            ->lockForUpdate()
            ->get(['Id_Buku_Cuti', 'Id_Jenis_Cuti', 'sisa_cuti'])
            ->keyBy('Id_Buku_Cuti');

        $rencana = self::rencanaBalikKreditKonversi(
            $kredit,
            $dompet->map(fn ($d) => (float) $d->sisa_cuti)->all(),
        );
        $now = FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');

        foreach ($rencana['langkah'] as $l) {
            $jumlah = $l['jumlah'];

            if ($l['jenis'] === 'HUTANG_BARU') {
                $idJenis = (int) $dompet[$l['id_buku']]->Id_Jenis_Cuti;
                $namaJenis = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $idJenis)->value('Nama_Jenis_Cuti') ?? 'Tahunan';
                $this->processDebtCreation($kodeKaryawan, $idJenis, $jumlah, $noTransaksi, $namaJenis, $actor, 'HUTANG_CUTI', $keterangan);

                continue;
            }

            $update = ['sisa_cuti' => DB::raw("sisa_cuti - $jumlah"), 'updated_at' => $now];
            if ($l['jenis'] === 'JATAH_DITARIK') {
                // Kebalikan creditLeaveBalance(), yang menaikkan jumlah_awal_cuti.
                $update['jumlah_awal_cuti'] = DB::raw("jumlah_awal_cuti - $jumlah");
            }

            $this->applyWalletMutation(
                (int) $l['id_buku'],
                $update,
                'DEBIT',
                self::KATEGORI_BATAL_KONVERSI_CUTI,
                $noTransaksi,
                $jumlah,
                $l['saldo_sebelum'],
                $l['saldo_sesudah'],
                $keterangan,
                $actor,
                null,
                $now,
            );
        }

        return $rencana + ['already' => false];
    }

    public function createOrUpdateDebtBalance(
        $kodeKaryawan,
        $idJenisCuti,
        $jumlahHutang,
        $refNo,
        $kategori,
        $reason,
        $actor,
        $namaJenis = null,
        $tanggalExpired = null,
        $periode = null,
        $activatedAt = null,
        $notes = 'Hutang_Cuti',
    ) {
        $now = FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');

        if (! $namaJenis) {
            $namaJenis =
                DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $idJenisCuti)->value('Nama_Jenis_Cuti') ??
                'Tahunan';
        }

        $existingDebtRow = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Id_Jenis_Cuti', $idJenisCuti)
            ->where('sisa_cuti', '<', 0)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('tanggal_expired', 'desc')
            ->first();

        $targetId = null;
        $saldoAwal = 0;

        if ($existingDebtRow) {
            $targetId = $existingDebtRow->Id_Buku_Cuti;
            $saldoAwal = (float) $existingDebtRow->sisa_cuti;

            DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $targetId)
                ->update([
                    'sisa_cuti' => DB::raw("sisa_cuti - $jumlahHutang"),
                    'updated_at' => $now,
                ]);
        } else {
            $targetId = DB::table('HRIS_Buku_Cuti')->insertGetId([
                'Kode_Karyawan' => $kodeKaryawan,
                'Id_Jenis_Cuti' => $idJenisCuti,
                'Periode' => $periode,
                'tipe_cuti' => $namaJenis,
                'tanggal_diberikan' => $now,
                'tanggal_expired' => $tanggalExpired ? Carbon::parse($tanggalExpired) : $now->copy()->addYears(10),
                'Activated_At' => $activatedAt ? Carbon::parse($activatedAt) : $now,
                'jumlah_awal_cuti' => 0,
                'sisa_cuti' => -$jumlahHutang,
                'jumlah_terpakai' => 0,
                'notes' => $notes,
                'Flag_Aktif' => 'Y',
                'created_at' => $now,
            ]);
        }

        $this->logMutasi(
            $targetId,
            $refNo,
            'DEBIT',
            $kategori,
            $jumlahHutang,
            $saldoAwal,
            $saldoAwal - $jumlahHutang,
            $reason,
            $actor,
            null,
            $now,
        );

        return $targetId;
    }

    /**
     * Hitung window periode hutang cuti [start, end].
     *
     * @param  string|null  $tglMasuk  Tgl_Masuk karyawan (dipakai saat basis JOIN_DATE)
     * @param  string  $basis  'KALENDER' | 'JOIN_DATE'
     * @return array{0: Carbon, 1: Carbon} [start, end]
     */
    public function getHutangPeriodeWindow(?string $tglMasuk, string $basis): array
    {
        $now = Carbon::now();

        if ($basis === 'JOIN_DATE' && ! empty($tglMasuk)) {
            $join = Carbon::parse($tglMasuk);

            // cycle_start = anniversary Tgl_Masuk terakhir yang <= hari ini
            $day = min($join->day, Carbon::create($now->year, $join->month, 1)->daysInMonth);
            $cycleStart = Carbon::create($now->year, $join->month, $day)->startOfDay();

            if ($cycleStart->gt($now)) {
                $cycleStart = $cycleStart->copy()->subYear();
            }

            return [$cycleStart, $cycleStart->copy()->addYear()];
        }

        // Default: tahun kalender (Jan - Des)
        return [$now->copy()->startOfYear(), $now->copy()->endOfYear()];
    }

    /**
     * Hitung hutang cuti yang sudah terpakai sesuai mode.
     *
     * @param  string  $mode  'OUTSTANDING' | 'PERIODIK'
     * @param  string  $basis  'KALENDER' | 'JOIN_DATE'
     * @param  string  $perhitungan  'GROSS' (total dipinjam) | 'NETTO' (dipinjam - dilunasi) — hanya untuk PERIODIK
     */
    public function getHutangTerpakai(string $kodeKaryawan, int $idJenisCuti, string $mode, string $basis, ?string $tglMasuk, string $perhitungan = 'GROSS'): float
    {
        if ($mode === 'PERIODIK') {
            [$start, $end] = $this->getHutangPeriodeWindow($tglMasuk, $basis);

            // Kantong hutang = dompet notes mengandung 'Hutang' (mis. 'Hutang_Cuti',
            // 'Hutang - Cuti Bersama'). Semua sumber hutang ikut, tak bergantung Kategori.
            $debtWalletIds = DB::table('HRIS_Buku_Cuti')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Id_Jenis_Cuti', $idJenisCuti)
                ->where('notes', 'like', '%Hutang%')
                ->pluck('Id_Buku_Cuti');

            $journal = 0.0;
            if ($debtWalletIds->isNotEmpty()) {
                if ($perhitungan === 'NETTO') {
                    // NETTO: dipinjam − dilunasi − rollback (semua CREDIT). -SUM(Jumlah bertanda).
                    $net = DB::table('N_HRIS_Log_Buku_Cuti')
                        ->whereIn('Id_Buku_Cuti', $debtWalletIds)
                        ->whereBetween('Created_At', [$start, $end])
                        ->sum('Jumlah');
                    $journal = max(0.0, (float) -$net);
                } else {
                    // GROSS: total DIPINJAM (yang sudah dilunasi TETAP dihitung), TAPI
                    // kecualikan yang di-ROLLBACK (mis. cuti bersama dibatalkan → bukan hutang valid).
                    $debit = (float) DB::table('N_HRIS_Log_Buku_Cuti')
                        ->whereIn('Id_Buku_Cuti', $debtWalletIds)
                        ->where('Jenis_Mutasi', 'DEBIT')
                        ->whereBetween('Created_At', [$start, $end])
                        ->sum(DB::raw('ABS(Jumlah)'));
                    $rollback = (float) DB::table('N_HRIS_Log_Buku_Cuti')
                        ->whereIn('Id_Buku_Cuti', $debtWalletIds)
                        ->where('Jenis_Mutasi', 'CREDIT')
                        ->where('Kategori_Transaksi', 'like', '%ROLLBACK%')
                        ->whereBetween('Created_At', [$start, $end])
                        ->sum(DB::raw('ABS(Jumlah)'));
                    $journal = max(0.0, $debit - $rollback);
                }
            }

            // Hutang dari pengajuan cuti PENDING (belum masuk jurnal). Agregat jenis 1&2 →
            // hanya ditambahkan sekali di jenis Tahunan.
            $pending = 0.0;
            if ($idJenisCuti === self::ID_TAHUNAN) {
                $karyawan = \App\Models\ESS\Employee\Models\Karyawan::where('Kode_Karyawan', $kodeKaryawan)->first();
                $pending = $karyawan ? (float) $karyawan->hutangPendingContribution() : 0.0;
            }

            return $journal + $pending;
        }

        // OUTSTANDING: total hutang berjalan (agregat jenis 1 & 2 lewat model, netted).
        $karyawan = \App\Models\ESS\Employee\Models\Karyawan::where('Kode_Karyawan', $kodeKaryawan)->first();

        return $karyawan ? (float) $karyawan->hutang_cuti() : 0.0;
    }

    /**
     * Sisa jatah hutang cuti yang masih boleh diambil.
     * Maks null/0 => tak terbatas (dikembalikan sebagai INF).
     */
    public function getSisaJatahHutang(string $kodeKaryawan, int $idJenisCuti, ?int $maks, string $mode, string $basis, ?string $tglMasuk, string $perhitungan = 'GROSS'): float
    {
        if (empty($maks)) {
            return INF;
        }

        $terpakai = $this->getHutangTerpakai($kodeKaryawan, $idJenisCuti, $mode, $basis, $tglMasuk, $perhitungan);

        return max(0.0, (float) $maks - $terpakai);
    }

    private function processDebtCreation(
        $kodeKaryawan,
        $idJenisCuti,
        $jumlahHutang,
        $refNo,
        $namaJenis,
        $actor = 'SYSTEM',
        $kategori = 'HUTANG_CUTI',
        $reason = null,
    ) {
        $now = FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
        // Cari apakah sudah punya kantong hutang?
        $existingDebtRow = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Id_Jenis_Cuti', $idJenisCuti)
            ->where('sisa_cuti', '<', 0)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('tanggal_expired', 'desc')
            ->lockForUpdate()
            ->first();

        $targetId = null;
        $saldoAwal = 0;

        if ($existingDebtRow) {
            $targetId = $existingDebtRow->Id_Buku_Cuti;
            $saldoAwal = $existingDebtRow->sisa_cuti;

            DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $targetId)
                ->update([
                    'sisa_cuti' => DB::raw("sisa_cuti - $jumlahHutang"), // Tambah minus
                    'updated_at' => $now,
                ]);
        } else {
            // Buat Row Hutang Baru
            $targetId = DB::table('HRIS_Buku_Cuti')->insertGetId([
                'Kode_Karyawan' => $kodeKaryawan,
                'Id_Jenis_Cuti' => $idJenisCuti,
                'tipe_cuti' => $namaJenis,
                'tanggal_diberikan' => $now,
                'tanggal_expired' => $now->copy()->addYears(10), // Hutang tahan lama
                'Activated_At' => $now,
                'jumlah_awal_cuti' => 0,
                'sisa_cuti' => -$jumlahHutang,
                'notes' => 'Hutang_Cuti',
                'Flag_Aktif' => 'Y',
                'created_at' => $now,
            ]);
        }

        $this->logMutasi(
            $targetId,
            $refNo,
            'DEBIT',
            $kategori,
            $jumlahHutang,
            $saldoAwal,
            $saldoAwal - $jumlahHutang,
            $reason ?: "Hutang dari Transaksi $refNo",
            $actor,
            null,
            $now,
        );
    }

    private function logMutasi($idBuku, $ref, $jenis, $kategori, $jml, $saldoAwl, $saldoAkh, $ket, $by, $cutiKey = null, $asOfDate = null)
    {
        $now = $asOfDate ? Carbon::parse($asOfDate) : FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
        // Store negative amount for DEBIT operations
        $jumlahFinal = $jenis === 'DEBIT' ? -abs($jml) : abs($jml);
        $payload = [
            'Id_Buku_Cuti' => $idBuku,
            'No_Transaksi_Ref' => $ref,
            'Jenis_Mutasi' => $jenis,
            'Kategori_Transaksi' => $kategori,
            'Jumlah' => $jumlahFinal,
            'Saldo_Sebelum' => $saldoAwl,
            'Saldo_Sesudah' => $saldoAkh,
            'Keterangan' => $ket,
            'Created_By' => $by,
            'Created_At' => now(),
        ];

        $effectiveSnapshot = $this->buildEffectiveBalanceSnapshot(
            $idBuku,
            (float) $saldoAwl,
            (float) $saldoAkh,
            $now,
            $jenis,
            $kategori,
            $ket,
        );
        if ($effectiveSnapshot) {
            $payload = array_merge($payload, $effectiveSnapshot);
        }

        if ($cutiKey) {
            $payload['Cuti_Key'] = $cutiKey;
        }

        // Dual-write provenance: isi Source_Category untuk baris ledger BARU (guarded
        // oleh keberadaan kolom, jadi aman bila DDL provenance belum diterapkan).
        if ($this->hasSourceCategoryColumn()) {
            $payload['Source_Category'] = CutiSourceCategoryHelper::map((string) $kategori, (string) $jenis);
        }

        DB::table('N_HRIS_Log_Buku_Cuti')->insert($payload);
    }

    public function recordBalanceMutation($idBuku, $ref, $jenis, $kategori, $jml, $saldoAwl, $saldoAkh, $ket, $by, $cutiKey = null, $asOfDate = null): void
    {
        $this->logMutasi($idBuku, $ref, $jenis, $kategori, $jml, $saldoAwl, $saldoAkh, $ket, $by, $cutiKey, $asOfDate);
    }

    /**
     * Catat perubahan KETERSEDIAAN wallet (mis. Activated_At di-unlock) sebagai baris
     * ledger MATURITY_ACTIVATED dengan Jumlah=0 — saldo TIDAK berubah, hanya menjadi
     * tersedia. Menutup gap audit: UPDATE Activated_At langsung yang sebelumnya tak
     * berjejak. Saldo_Sebelum == Saldo_Sesudah, jadi rekonsiliasi rantai tetap valid.
     */
    public function recordWalletMaturityChange(int $idBukuCuti, $oldActivatedAt, $newActivatedAt, string $actor, $ref = null, $asOfDate = null): void
    {
        $sisa = (float) (DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBukuCuti)->value('sisa_cuti') ?? 0);
        $ket = 'Aktivasi saldo (Activated_At: '.($oldActivatedAt ?: '-').' -> '.($newActivatedAt ?: '-').')';
        $this->logMutasi($idBukuCuti, $ref ?: 'MATURITY_ACTIVATED', 'CREDIT', 'MATURITY_ACTIVATED', 0, $sisa, $sisa, $ket, $actor, null, $asOfDate);
    }

    /**
     * Choke point: memasangkan UPDATE wallet + baris ledger dalam satu tempat,
     * menjamin setiap perubahan sisa_cuti disertai log. Behavior-preserving —
     * $walletUpdate berisi kolom PERSIS seperti sebelum refactor (termasuk
     * ekspresi DB::raw untuk sisa_cuti/jumlah_terpakai), dan argumen log
     * diteruskan apa adanya ke logMutasi.
     */
    private function applyWalletMutation(
        int $idBukuCuti,
        array $walletUpdate,
        string $jenis,
        string $kategori,
        $ref,
        $jml,
        $saldoSebelum,
        $saldoSesudah,
        string $ket,
        string $actor,
        ?string $cutiKey = null,
        $asOfDate = null,
    ): void {
        DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBukuCuti)->update($walletUpdate);
        $this->logMutasi($idBukuCuti, $ref, $jenis, $kategori, $jml, $saldoSebelum, $saldoSesudah, $ket, $actor, $cutiKey, $asOfDate);
    }

    public function recordBalanceAvailabilityChange(
        $idBuku,
        $ref,
        string $kategori,
        float $jumlah,
        float $saldoWallet,
        string $ket,
        string $by,
        float $saldoEfektifSebelum,
        float $saldoEfektifSesudah,
        float $totalHutangSebelum,
        float $totalHutangSesudah,
        $asOfDate = null,
    ): void {
        $now = $asOfDate ? Carbon::parse($asOfDate) : FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
        $payload = [
            'Id_Buku_Cuti' => $idBuku,
            'No_Transaksi_Ref' => $ref,
            'Jenis_Mutasi' => 'CREDIT',
            'Kategori_Transaksi' => $kategori,
            'Jumlah' => abs($jumlah),
            'Saldo_Sebelum' => $saldoWallet,
            'Saldo_Sesudah' => $saldoWallet,
            'Keterangan' => $ket,
            'Created_By' => $by,
            'Created_At' => $now,
        ];

        if ($this->hasEffectiveBalanceColumns()) {
            $payload = array_merge($payload, [
                'Saldo_Efektif_Sebelum' => round($saldoEfektifSebelum, 2),
                'Saldo_Efektif_Sesudah' => round($saldoEfektifSesudah, 2),
                'Total_Hutang_Sebelum' => round($totalHutangSebelum, 2),
                'Total_Hutang_Sesudah' => round($totalHutangSesudah, 2),
            ]);
        }

        if ($this->hasSourceCategoryColumn()) {
            $payload['Source_Category'] = CutiSourceCategoryHelper::map((string) $kategori, 'CREDIT');
        }

        DB::table('N_HRIS_Log_Buku_Cuti')->insert($payload);
    }

    private function buildEffectiveBalanceSnapshot(
        $idBuku,
        float $saldoAwalWallet,
        float $saldoAkhirWallet,
        Carbon $asOfDate,
        ?string $jenisMutasi = null,
        ?string $kategori = null,
        ?string $keterangan = null,
    ): ?array {
        if (! $this->hasEffectiveBalanceColumns()) {
            return null;
        }

        $wallet = DB::table('HRIS_Buku_Cuti')
            ->where('Id_Buku_Cuti', $idBuku)
            ->select('Kode_Karyawan', 'Flag_Aktif', 'Activated_At', 'tanggal_expired')
            ->first();

        if (! $wallet || empty($wallet->Kode_Karyawan)) {
            return null;
        }

        $asOfDateString = $asOfDate->toDateString();
        $isUsableAfter = $this->isWalletUsableOnDate($wallet->Flag_Aktif, $wallet->Activated_At, $wallet->tanggal_expired, $asOfDate);
        $isUsableBefore = $isUsableAfter;
        if ($wallet->Flag_Aktif === 'T' && $saldoAwalWallet > 0 && $saldoAkhirWallet == 0.0) {
            // Untuk close/expired cleanup, wallet sudah Flag_Aktif=T saat log ditulis.
            // Nilai "sebelum" harus tetap menangkap saldo yang dihapus jika wallet sudah aktif,
            // walaupun tanggal_expired sudah lewat pada hari cleanup.
            $isUsableBefore = $this->isWalletActivatedOnDate($wallet->Activated_At, $asOfDate);
        }

        $walletPositiveBefore = $isUsableBefore ? max(0.0, $saldoAwalWallet) : 0.0;
        $walletPositiveAfter = $isUsableAfter ? max(0.0, $saldoAkhirWallet) : 0.0;
        $positiveDelta = $walletPositiveAfter - $walletPositiveBefore;
        $settlementDebitAmount = $this->isDebtSettlementSourceDebit($jenisMutasi, $kategori, $keterangan)
            ? max(0.0, $walletPositiveBefore - $walletPositiveAfter)
            : 0.0;

        $saldoPositifSesudah = (float) DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $wallet->Kode_Karyawan)
            ->where('Flag_Aktif', 'Y')
            ->where('sisa_cuti', '>', 0)
            ->whereDate('Activated_At', '<=', $asOfDateString)
            ->whereDate('tanggal_expired', '>=', $asOfDateString)
            ->sum('sisa_cuti');

        $isDebtActiveAfter = $wallet->Flag_Aktif === 'Y';
        $isDebtActiveBefore = $isDebtActiveAfter;
        if ($wallet->Flag_Aktif === 'T') {
            $isDebtActiveBefore = true;
        }

        $walletDebtBefore = $isDebtActiveBefore ? max(0.0, -$saldoAwalWallet) : 0.0;
        $walletDebtAfter = $isDebtActiveAfter ? max(0.0, -$saldoAkhirWallet) : 0.0;
        $debtDelta = $walletDebtAfter - $walletDebtBefore;

        $totalHutangSesudah = abs((float) DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $wallet->Kode_Karyawan)
            ->where('Flag_Aktif', 'Y')
            ->where('sisa_cuti', '<', 0)
            ->sum('sisa_cuti'));

        if ($settlementDebitAmount > 0) {
            // Debit sumber dana pelunasan hutang adalah mutasi internal berpasangan
            // dengan PAY_DEBT. Jangan tampilkan saldo efektif sementara menjadi negatif.
            $positiveDelta = 0.0;
            $saldoPositifSesudah += $settlementDebitAmount;
        }

        $isDebtRepaymentCredit = $this->isDebtRepaymentCredit($jenisMutasi, $kategori);
        $effectiveDelta = $isDebtRepaymentCredit ? 0.0 : $positiveDelta - $debtDelta;
        $saldoEfektifSesudah = $saldoPositifSesudah - $totalHutangSesudah;

        return [
            'Saldo_Efektif_Sebelum' => round($saldoEfektifSesudah - $effectiveDelta, 2),
            'Saldo_Efektif_Sesudah' => round($saldoEfektifSesudah, 2),
            'Total_Hutang_Sebelum' => round($totalHutangSesudah - $debtDelta, 2),
            'Total_Hutang_Sesudah' => round($totalHutangSesudah, 2),
        ];
    }

    private function isWalletUsableOnDate($flagAktif, $activatedAt, $tanggalExpired, Carbon $asOfDate): bool
    {
        if ($flagAktif !== 'Y' || empty($activatedAt) || empty($tanggalExpired)) {
            return false;
        }

        $date = $asOfDate->toDateString();

        return Carbon::parse($activatedAt)->toDateString() <= $date &&
            Carbon::parse($tanggalExpired)->toDateString() >= $date;
    }

    private function isWalletActivatedOnDate($activatedAt, Carbon $asOfDate): bool
    {
        if (empty($activatedAt)) {
            return false;
        }

        return Carbon::parse($activatedAt)->toDateString() <= $asOfDate->toDateString();
    }

    private function isDebtSettlementSourceDebit(?string $jenisMutasi, ?string $kategori, ?string $keterangan): bool
    {
        if (strtoupper((string) $jenisMutasi) !== 'DEBIT') {
            return false;
        }

        $kategori = strtoupper((string) $kategori);
        $keterangan = strtoupper((string) $keterangan);

        return str_contains($kategori, 'PELUNASAN_HUTANG') ||
            str_contains($keterangan, 'MELUNASI HUTANG') ||
            str_contains($keterangan, 'AUTO-DEDUCTION');
    }

    private function isDebtRepaymentCredit(?string $jenisMutasi, ?string $kategori): bool
    {
        return strtoupper((string) $jenisMutasi) === 'CREDIT' && strtoupper((string) $kategori) === 'PAY_DEBT';
    }

    private function hasEffectiveBalanceColumns(): bool
    {
        static $hasColumns = null;

        if ($hasColumns === null) {
            $hasColumns =
                SkemaTersedia::kolom('N_HRIS_Log_Buku_Cuti', 'Saldo_Efektif_Sebelum') &&
                SkemaTersedia::kolom('N_HRIS_Log_Buku_Cuti', 'Saldo_Efektif_Sesudah') &&
                SkemaTersedia::kolom('N_HRIS_Log_Buku_Cuti', 'Total_Hutang_Sebelum') &&
                SkemaTersedia::kolom('N_HRIS_Log_Buku_Cuti', 'Total_Hutang_Sesudah');
        }

        return $hasColumns;
    }

    private function hasSourceCategoryColumn(): bool
    {
        static $hasColumn = null;

        if ($hasColumn === null) {
            $hasColumn = SkemaTersedia::kolom('N_HRIS_Log_Buku_Cuti', 'Source_Category');
        }

        return $hasColumn;
    }

    public function repayDebt($kodeKaryawan, $amount, $refNo, $kategori, $reason, $actor, $targetIdJenisCuti = null, $cutiKey = null, $asOfDate = null)
    {
        $now = $asOfDate ? Carbon::parse($asOfDate) : FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
        $sisaUang = $amount;
        $totalTerbayar = 0;
        $cutiKeyLogged = false;

        // Query Hutang
        $query = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('sisa_cuti', '<', 0)
            ->where('Flag_Aktif', 'Y');

        // [MODIFIKASI] Tambahkan filter jika ada
        if ($targetIdJenisCuti) {
            $query->where('Id_Jenis_Cuti', $targetIdJenisCuti);
        }

        $hutangRows = $query->orderBy('tanggal_expired', 'asc')->lockForUpdate()->get();

        foreach ($hutangRows as $hRow) {
            if ($sisaUang <= 0) {
                break;
            }

            $hutangDiRowIni = abs($hRow->sisa_cuti);
            $bayar = min($sisaUang, $hutangDiRowIni);

            // Update saldo minus mendekati 0 + log, dipasangkan via choke point.
            $this->applyWalletMutation(
                $hRow->Id_Buku_Cuti,
                [
                    'sisa_cuti' => DB::raw("sisa_cuti + $bayar"),
                    'updated_at' => $now,
                ],
                'CREDIT',
                $kategori,
                $refNo,
                $bayar,
                $hRow->sisa_cuti,
                $hRow->sisa_cuti + $bayar,
                $reason,
                $actor,
                ! $cutiKeyLogged ? $cutiKey : null,
                $now,
            );

            $cutiKeyLogged = $cutiKeyLogged || ! empty($cutiKey);
            $sisaUang -= $bayar;
            $totalTerbayar += $bayar; // [MODIFIKASI] Catat total
        }

        return $totalTerbayar; // [MODIFIKASI] Return hasil
    }

    /**
     * KREDIT SALDO POSITIF (TAMBAH JATAH CUTI)
     * Menambah saldo cuti positif ke dompet aktif; bila belum ada dompet aktif untuk
     * jenis tsb, membuat row baru. TIDAK membuka transaksi sendiri — harus dipanggil
     * dari dalam transaksi milik caller. Meniru cabang "surplus → saldo aktif" pada
     * manualAdjustment() agar konsisten.
     */
    public function creditLeaveBalance(
        $kodeKaryawan,
        $idJenisCuti,
        $amount,
        $refNo,
        string $kategori,
        string $reason,
        string $actor,
        $asOfDate = null,
    ): float {
        $amount = (float) $amount;
        if ($amount <= 0) {
            return 0.0;
        }

        $now = $asOfDate ? Carbon::parse($asOfDate) : FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');

        // Cari dompet aktif jenis ini (positif/nol, belum expired) untuk ditambah.
        $rowAktif = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Id_Jenis_Cuti', $idJenisCuti)
            ->where('sisa_cuti', '>=', 0)
            ->where('Flag_Aktif', 'Y')
            ->whereDate('tanggal_expired', '>=', $now->toDateString())
            ->orderBy('tanggal_expired', 'desc')
            ->lockForUpdate()
            ->first();

        if ($rowAktif) {
            DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $rowAktif->Id_Buku_Cuti)
                ->update([
                    'sisa_cuti' => DB::raw("sisa_cuti + $amount"),
                    'jumlah_awal_cuti' => DB::raw("jumlah_awal_cuti + $amount"),
                    'updated_at' => $now,
                ]);
            $rowAktif = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $rowAktif->Id_Buku_Cuti)->first();
        } else {
            $tanggalExpired = $now->copy()->addYear()->endOfYear()->toDateString();

            if ($idJenisCuti != self::ID_TAHUNAN) {
                $masaBerlakuBulan = DB::table('N_HRIS_Jenis_Cuti')
                    ->where('Id_Jenis_Cuti', $idJenisCuti)
                    ->value('Masa_Berlaku_Bulan');

                $tanggalExpired = $now->copy()->addMonths($masaBerlakuBulan ?? 12)->toDateString();
            }

            $newId = DB::table('HRIS_Buku_Cuti')->insertGetId([
                'Kode_Karyawan' => $kodeKaryawan,
                'Id_Jenis_Cuti' => $idJenisCuti,
                'Periode' => $now->year,
                'tipe_cuti' => DB::table('N_HRIS_Jenis_Cuti')
                    ->where('Id_Jenis_Cuti', $idJenisCuti)
                    ->value('Nama_Jenis_Cuti'),
                'tanggal_diberikan' => $now->toDateString(),
                'tanggal_expired' => $tanggalExpired,
                'jumlah_awal_cuti' => $amount,
                'sisa_cuti' => $amount,
                'jumlah_terpakai' => 0,
                'Activated_At' => $now,
                'notes' => 'Saldo_Cuti',
                'Flag_Aktif' => 'Y',
                'created_at' => $now,
            ]);

            $rowAktif = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $newId)->first();
        }

        $walletInfo =
            "Periode {$rowAktif->Periode} - Exp: ".
            Carbon::parse($rowAktif->tanggal_expired)->format('d/m/Y');

        $this->logMutasi(
            $rowAktif->Id_Buku_Cuti,
            $refNo,
            'CREDIT',
            $kategori,
            $amount,
            $rowAktif->sisa_cuti - $amount,
            $rowAktif->sisa_cuti,
            "$reason ($walletInfo)",
            $actor,
            null,
            $now,
        );

        return $amount;
    }

    /**
     * CLOSE BALANCE (HANGUSKAN/TUTUP)
     * Meng-nol-kan sisa cuti pada row tertentu.
     */
    public function closeBalance($idBukuCuti, $reason, $actor, $noRef)
    {
        return DB::transaction(function () use ($idBukuCuti, $reason, $actor, $noRef) {
            $now = FormatTanggalHelper::getCurrentTime('transactionServiceDateTime');
            $row = DB::table('HRIS_Buku_Cuti')
                ->where('Id_Buku_Cuti', $idBukuCuti)
                ->lockForUpdate()
                ->first();

            if (! $row || $row->sisa_cuti <= 0 || $row->Flag_Aktif == 'T') {
                return false;
            }

            $this->applyWalletMutation(
                $idBukuCuti,
                [
                    'sisa_cuti' => 0,
                    'Flag_Aktif' => 'T',
                    'updated_at' => $now,
                    'notes' => trim((string) ($row->notes ?? '')." | $reason"),
                ],
                'DEBIT',
                'EXPIRED_CLEANUP',
                $noRef,
                $row->sisa_cuti,
                $row->sisa_cuti,
                0,
                $reason,
                $actor,
                null,
                $now,
            );

            return true;
        });
    }

    private function safeRollback(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
