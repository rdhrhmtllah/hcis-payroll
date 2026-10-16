<?php

namespace App\Models\ESS\Employee\Models;

use App\Helpers\FormatTanggalHelper;
use App\Models\ESS\Organization\Division\Models\View_Divisi_Sub_Divisi;
use App\Models\ESS\Organization\Level\Models\View_Golongan_Sub_Golongan_Level_Jabatan;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Karyawan extends Model
{
    use HasFactory;

    protected $table = 'Karyawan';

    protected $primaryKey = 'Kode_Karyawan';

    public $incrementing = false; // Karena Kode_Karyawan mungkin bukan auto-incrementing integer

    protected $keyType = 'string';

    // protected
    protected $guarded = [];

    public $timestamps = false;

    public function getTime()
    {
        $Tanggal = FormatTanggalHelper::getCurrentTime('ModelKaryawan');

        $time = $Tanggal && ! empty($Tanggal) ? Carbon::parse($Tanggal) : Carbon::now('Asia/Jakarta');

        return $time;
    }

    public function user()
    {
        return $this->hasOne(User::class, 'UserID_Web', 'Id_Users');
    }

    public function division()
    {
        return $this->belongsTo(View_Divisi_Sub_Divisi::class, 'ID_Divisi_Sub_Divisi', 'ID_DIVISI_SUB_DIVISI');
    }

    public function level()
    {
        return $this->belongsTo(
            View_Golongan_Sub_Golongan_Level_Jabatan::class,
            'ID_Level_Jabatan',
            'ID_Level_Jabatan',
        );
    }

    // public function calculateLeaveBalance(): array
    // {
    //     $now = $this->getTime();
    //     $hitungCuti = DB::table('HRIS_Buku_Cuti')
    //                     ->where("Kode_Karyawan", $this->Kode_Karyawan)
    //                     ->whereDate('tanggal_expired', '>=', $now)
    //                     ->whereDate('Activated_At', '<=', $now)
    //                     ->where('Flag_Aktif', 'Y')
    //                     ->sum('sisa_cuti');

    //     $allBookingTransaksi = DB::table('Transaksi_Cuti_Detail as a')
    //                         ->join("Transaksi_Cuti as b", 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //                         ->where("a.Kode_Karyawan", $this->Kode_Karyawan)
    //                         ->where("a.Flag_Approval", null)
    //                         ->where("b.Status", null)
    //                         ->get();

    //     $totalHari = $allBookingTransaksi->sum(function($item) {
    //         return Carbon::parse($item->Tanggal_Cuti_Dari)
    //             ->diffInDays(Carbon::parse($item->Tanggal_Cuti_Sampai)) + 1;
    //     });

    //     $totalSaldo = $hitungCuti - $totalHari;

    //     return [
    //         'remaining' => max($totalSaldo, 0),
    //         'debt' => max(-$totalSaldo, 0)
    //     ];
    // }

    const ID_TAHUNAN = 1;

    const ID_LOYALTY = 2;

    public function calculateLeaveBalance(): array
    {
        $now = $this->getTime();

        $hitungCuti = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $this->Kode_Karyawan)
            ->where('Flag_Aktif', 'Y')

            // FILTER UTAMA: Ambil ID Tahunan DAN ID Loyalty
            ->whereIn('Id_Jenis_Cuti', [self::ID_TAHUNAN, self::ID_LOYALTY])

            // FILTER VALIDITAS
            ->where(function ($q) use ($now) {
                $q->whereDate('tanggal_expired', '>=', $now)
                    ->whereDate('Activated_At', '<=', $now) // Pastikan sudah aktif
                    ->orWhere('sisa_cuti', '<', 0); // Hutang selalu dihitung
            })
            ->sum('sisa_cuti');
        // dd($hitungCuti);

        $allBookingTransaksi = DB::table('Transaksi_Cuti_Det as a')
            ->join('Transaksi_Cuti as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('Transaksi_Cuti_Detail as c', 'a.No_Transaksi', '=', 'c.No_Transaksi')
            ->join('N_HRIS_Jenis_Cuti as jc', 'c.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
            ->where('jc.Memotong_Saldo_Cuti', 'Y')
            ->where('a.Kode_Karyawan', $this->Kode_Karyawan)
            // ->where(DB::raw('b.Tanggal'))
            ->whereNull('c.Flag_Approval')
            ->whereNull('b.Status')
            ->get();

        // dd($allBookingTransaksi);
        $totalHari = $allBookingTransaksi->count(function ($item) {
            return $item->Tanggal_Cuti;
        });
        // dd($totalHari);
        $cutiBersama = DB::table('N_HRIS_Cuti_Bersama')
            ->whereDate('Tanggal_Selesai', '>=', $now)
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Executed', 'T') // Hanya penahan (belum di-execute)
            ->sum('Jumlah_Hari');
        // dd($cutiBersama);
        // dd($hitungCuti);

        $totalSaldo = $hitungCuti - $totalHari - $cutiBersama;

        return [
            'remaining' => max($totalSaldo, 0),
            'debt' => abs(min($totalSaldo, 0)),
        ];
    }

    // public function calculateLeaveBalance(): array
    // {
    //     $now = now()->toDateString();

    //     // 1. AMBIL SALDO FISIK (Sudah Netto Hutang & Piutang)
    //     // Karena kita pakai sistem Update, SUM ini otomatis sudah hasil pengurangan
    //     $saldoDb = DB::table('HRIS_Buku_Cuti')
    //         ->where("Kode_Karyawan", $this->Kode_Karyawan)
    //         ->where('Flag_Aktif', 'Y')
    //         // Ambil yang masa berlakunya masih ada ATAU yang minus (Hutang)
    //         ->where(function($q) use ($now) {
    //             $q->whereDate('tanggal_expired', '>=', $now)
    //             ->orWhere('sisa_cuti', '<', 0); // Hutang selalu dihitung
    //         })
    //         ->where('')
    //         ->sum('sisa_cuti');

    //     // 2. HITUNG PENGGUNAAN PENDING (Booking)
    //     // Ini tetap perlu karena belum mengurangi saldo fisik
    //     $pendingUse = DB::table('Transaksi_Cuti_Det as a')
    //         ->join("Transaksi_Cuti as b", 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //         ->join("Transaksi_Cuti_Detail as c", 'a.No_Transaksi', '=', 'c.No_Transaksi')
    //         ->join('N_HRIS_Jenis_Cuti as jc', 'c.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
    //         ->where('jc.Memotong_Saldo_Cuti', 'Y')
    //         ->where("a.Kode_Karyawan", $this->Kode_Karyawan)
    //         ->whereNull("c.Flag_Approval")
    //         ->whereNull("b.Status")
    //         ->get();
    //     // 3. HITUNG CUTI BERSAMA FUTURE
    //     // Ambil yang belum dieksekusi (Flag_Eksekusi = 'N')
    //     $futureCutiBersama = DB::table('N_HRIS_Cuti_Bersama')
    //         ->where('Flag_Aktif', 'Y')
    //         // ->where('Flag_Eksekusi', 'N')
    //         ->whereDate('Tanggal_Mulai', '>', $now)
    //         ->sum('Jumlah_Hari');

    //     // 4. KALKULASI FINAL
    //     $totalSaldoEfektif = $saldoDb - $pendingUse - $futureCutiBersama;

    //     return [
    //         'remaining' => max($totalSaldoEfektif, 0), // Saldo positif yang bisa dipakai
    //         'debt'      => abs(min($totalSaldoEfektif, 0)) // Jumlah hutang (dijadikan positif untuk display)
    //     ];
    // }

    public function sisa_cuti(): float
    {
        return (int) $this->calculateLeaveBalance()['remaining'];
    }

    public function hutang_cuti(): float
    {
        return (int) $this->calculateLeaveBalance()['debt'];
    }

    /**
     * Rincian komponen hutang berbasis DOMPET AKTIF (sumber kebenaran, bukan jurnal):
     * - positive    : total saldo cuti positif yang aktif & valid (jenis 1&2)
     * - active_debt : total hutang yang masih berjalan = |Σ sisa_cuti dompet aktif < 0|
     * - pending_days: total hari pengajuan cuti yang masih PENDING (memotong saldo, aktif)
     *
     * Robust: otomatis mengecualikan rollback, pelunasan, dan dompet yang sudah ditutup
     * (Flag_Aktif='T') — karena semuanya sudah tercermin di saldo dompet.
     */
    public function hutangBreakdown(): array
    {
        $now = $this->getTime();

        $positive = (float) DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $this->Kode_Karyawan)
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Id_Jenis_Cuti', [self::ID_TAHUNAN, self::ID_LOYALTY])
            ->where('sisa_cuti', '>', 0)
            ->whereDate('tanggal_expired', '>=', $now)
            ->whereDate('Activated_At', '<=', $now)
            ->sum('sisa_cuti');

        $activeDebt = abs((float) DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $this->Kode_Karyawan)
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Id_Jenis_Cuti', [self::ID_TAHUNAN, self::ID_LOYALTY])
            ->where('sisa_cuti', '<', 0)
            ->sum('sisa_cuti'));

        $pendingDays = DB::table('Transaksi_Cuti_Det as a')
            ->join('Transaksi_Cuti as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
            ->join('Transaksi_Cuti_Detail as c', 'a.No_Transaksi', '=', 'c.No_Transaksi')
            ->join('N_HRIS_Jenis_Cuti as jc', 'c.Id_Jenis_Cuti', '=', 'jc.Id_Jenis_Cuti')
            ->where('jc.Memotong_Saldo_Cuti', 'Y')
            ->where('a.Kode_Karyawan', $this->Kode_Karyawan)
            ->whereNull('c.Flag_Approval')
            ->whereNull('b.Status')
            ->count();

        return [
            'positive' => $positive,
            'active_debt' => $activeDebt,
            'pending_days' => (float) $pendingDays,
        ];
    }

    /**
     * Hutang BARU yang akan lahir dari pengajuan cuti PENDING = kelebihan hari
     * pengajuan pending atas saldo positif yang tersedia. Konsisten dengan cara
     * approval (debitBalanceWithDebt) memakai dompet positif. Tidak termasuk
     * hutang yang sudah aktif (itu dihitung terpisah lewat active_debt).
     */
    public function hutangPendingContribution(): float
    {
        $b = $this->hutangBreakdown();

        return max(0.0, $b['pending_days'] - $b['positive']);
    }
}
