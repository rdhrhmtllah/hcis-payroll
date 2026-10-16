<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Processing\AttendanceDirtyResolverService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

class CutiBersamaController extends Controller
{
    public function __construct(private readonly AttendanceDirtyResolverService $attendanceDirtyResolver) {}

    public function index()
    {
        //   {
        //             Id_Cuti_Bersama: 1,
        //             Nama_Cuti_Bersama: "Libur Idul Fitri 2024",
        //             Tanggal_Mulai: "2024-04-08",
        //             Tanggal_Selesai: "2024-04-15",
        //             Jumlah_Hari: 8,
        //             Flag_Aktif: "T",
        //             Created_At: "2023-12-01",
        //             Created_By: "Admin",
        //         },

        $cutiBersama = DB::table('N_HRIS_Cuti_Bersama')
            ->where('Flag_Aktif', 'Y')
            ->get();

        $encodedData = $cutiBersama->map(function ($cuti) {
            $cuti->Id_Cuti_Bersama = Hashids::connection('custom')->encode($cuti->Id_Cuti_Bersama);

            return $cuti;
        });

        return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'Nama_Cuti_Bersama' => 'required|string|max:255',
            'Tanggal_Mulai' => 'required|date',
            'Tanggal_Selesai' => 'required|date|after_or_equal:Tanggal_Mulai',
        ]);

        $jumlahHari = Carbon::parse($validated['Tanggal_Mulai'])->diffInDays(Carbon::parse($validated['Tanggal_Selesai'])) + 1;

        $cutiBersama = DB::table('N_HRIS_Cuti_Bersama')->insert([
            'Nama_Cuti_Bersama' => $validated['Nama_Cuti_Bersama'],
            'Tanggal_Mulai' => $validated['Tanggal_Mulai'],
            'Tanggal_Selesai' => $validated['Tanggal_Selesai'],
            'Jumlah_Hari' => $jumlahHari,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => Auth::user()->Id_Users,
        ]);

        $this->markCutiBersamaDirty($validated['Tanggal_Mulai'], $validated['Tanggal_Selesai'], 'CUTI_BERSAMA_LEGACY_CHANGED');

        return ResponseHelper::success($cutiBersama, 'Data Ditemukan', 200);

    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'Nama_Cuti_Bersama' => 'required|string|max:255',
            'Tanggal_Mulai' => 'required|date',
            'Tanggal_Selesai' => 'required|date|after_or_equal:Tanggal_Mulai',
        ]);

        $jumlahHari = Carbon::parse($validated['Tanggal_Mulai'])->diffInDays(Carbon::parse($validated['Tanggal_Selesai'])) + 1;

        DB::table('N_HRIS_Cuti_Bersama')
            ->where('Id_Cuti_Bersama', $id)
            ->update([
                'Nama_Cuti_Bersama' => $validated['Nama_Cuti_Bersama'],
                'Tanggal_Mulai' => $validated['Tanggal_Mulai'],
                'Tanggal_Selesai' => $validated['Tanggal_Selesai'],
                'Jumlah_Hari' => $jumlahHari,
                'Updated_At' => now(),
                'Updated_By' => Auth::user()->Id_Users,
            ]);

        $this->markCutiBersamaDirty($validated['Tanggal_Mulai'], $validated['Tanggal_Selesai'], 'CUTI_BERSAMA_LEGACY_CHANGED');

        return response()->json(['message' => 'Cuti bersama berhasil diperbarui']);
    }

    public function destroy($id)
    {
        DB::table('N_HRIS_Cuti_Bersama')
            ->where('Id_Cuti_Bersama', $id)
            ->update([
                'Flag_Aktif' => 'T',
                'Updated_At' => now(),
                'Updated_By' => Auth::user()->Id_Users,
            ]);

        $record = DB::table('N_HRIS_Cuti_Bersama')->where('Id_Cuti_Bersama', $id)->first();
        if ($record) {
            $this->markCutiBersamaDirty($record->Tanggal_Mulai, $record->Tanggal_Selesai, 'CUTI_BERSAMA_LEGACY_CHANGED');
        }

        return response()->json(['message' => 'Cuti bersama berhasil dihapus']);
    }

    public function applyToAll(Request $request, $id)
    {
        // Logika untuk menerapkan cuti bersama ke semua karyawan
        // Ini bisa berarti mengurangi saldo cuti tahunan mereka atau menandai hari-hari tersebut sebagai cuti bersama
        // Implementasi tergantung pada bagaimana sistem cuti Anda bekerja
        return response()->json(['message' => 'Cuti bersama berhasil diterapkan ke semua karyawan']);

    }

    private function markCutiBersamaDirty(string $tglAwal, string $tglAkhir, string $reason): void
    {
        $targets = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->pluck('Kode_Karyawan')
            ->map(fn ($kode) => [
                'kode_karyawan' => $kode,
                'tgl_awal' => $tglAwal,
                'tgl_akhir' => $tglAkhir,
            ]);

        $this->attendanceDirtyResolver->markMany($targets, $reason, 'CUTI_BERSAMA_LEGACY', 4);
    }
}
