<?php

namespace App\Http\Controllers\ESS\Attendance\Leave\Admin;

use App\Helpers\StatusKepegawaianHelper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KetentuanCutiController extends Controller
{
    public function getGolonganOptions()
    {
        $golongans = DB::table('View_Golongan_Sub_Golongan_Level_Jabatan')
            ->select('ID_Golongan', 'nama_golongan')
            ->selectRaw('TRY_CAST(nama_golongan AS INT) AS sort_order')
            ->whereNotNull('nama_golongan')
            ->where('nama_golongan', '!=', 'NOT SET')
            ->distinct()
            ->orderBy('sort_order', 'ASC')
            ->orderBy('nama_golongan', 'ASC')
            ->get()
            ->map(function ($row) {
                unset($row->sort_order);

                return $row;
            });

        return response()->json($golongans);
    }

    // --- Aturan Kelayakan Bulanan ---
    public function getAturanKelayakan()
    {
        $aturan = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')
            ->orderBy('Status_Karyawan', 'asc')
            ->get();

        return response()->json($aturan);
    }

    /*
     * Keempat penulisan di bawah dulu memakai $request->except([...]), yang
     * meneruskan SELURUH muatan permintaan ke DB::table()->insert/update --
     * bukan hanya field yang divalidasi tepat di atasnya. Karena ini query
     * builder, bukan Eloquent, $guarded tidak berlaku: setiap field tambahan
     * langsung menjadi kolom yang ditulis. Field yang kebetulan bernama sama
     * dengan kolom nyata tertulis diam-diam; yang tidak, membuat pernyataannya
     * gagal.
     *
     * Nilai kembalian validate() berisi TEPAT field yang aturannya ditulis di
     * sini, jadi daftar kolomnya tidak bisa lagi ditentukan pengirim.
     */
    public function storeAturanKelayakan(Request $request)
    {
        $data = $request->validate([
            'Status_Karyawan' => 'required|string|max:50',
            'Status_Kontrak' => 'nullable|string|max:50',
            'Bulan_Minimum' => 'nullable|integer',
            'Delay_Aktivasi' => 'nullable|integer',
            'Ref_Date_Source' => 'nullable|string|max:50',
            'Reset_Resign' => 'nullable|integer',
            'Keterangan' => 'nullable|string|max:255',
        ]);

        $id = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->insertGetId($data);
        $aturan = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->where('Id_Aturan_Cuti', $id)->first();

        // Tabel ini juga memasok label Status Kepegawaian di halaman profil.
        StatusKepegawaianHelper::lupakanPeta();

        return response()->json(['message' => 'Aturan berhasil ditambahkan', 'data' => $aturan], 201);
    }

    public function updateAturanKelayakan(Request $request, $id)
    {
        $data = $request->validate([
            'Status_Karyawan' => 'required|string|max:50',
            'Status_Kontrak' => 'nullable|string|max:50',
            'Bulan_Minimum' => 'nullable|integer',
            'Delay_Aktivasi' => 'nullable|integer',
            'Ref_Date_Source' => 'nullable|string|max:50',
            'Reset_Resign' => 'nullable|integer',
            'Keterangan' => 'nullable|string|max:255',
        ]);

        DB::table('N_HRIS_Aturan_Kelayakan_Cuti')
            ->where('Id_Aturan_Cuti', $id)
            ->update($data);

        $aturan = DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->where('Id_Aturan_Cuti', $id)->first();

        StatusKepegawaianHelper::lupakanPeta();

        return response()->json(['message' => 'Aturan berhasil diupdate', 'data' => $aturan]);
    }

    public function deleteAturanKelayakan($id)
    {
        DB::table('N_HRIS_Aturan_Kelayakan_Cuti')->where('Id_Aturan_Cuti', $id)->delete();

        StatusKepegawaianHelper::lupakanPeta();

        return response()->json(['message' => 'Aturan berhasil dihapus']);
    }

    // --- Hak Cuti Tambahan ---
    public function getHakCutiTambahan()
    {
        $hak = DB::table('HRIS_Hak_Cuti_Tambahan')
            ->orderBy('tahun_minimal', 'asc')
            ->get();

        return response()->json($hak);
    }

    public function storeHakCutiTambahan(Request $request)
    {
        $data = $request->validate([
            'nama_rule' => 'required|string|max:255',
            'tahun_minimal' => 'required|integer',
            'golongan_min_id' => 'nullable|integer',
            'golongan_max_id' => 'nullable|integer',
            'tambahan_hari' => 'required|integer',
            'masa_berlaku_cuti' => 'required|integer',
            'Status' => 'nullable|string|max:1',
        ]);

        $data['Created_By'] = auth()->user()->username ?? 'System';
        $data['Created_At'] = Carbon::now();
        $data['Updated_At'] = Carbon::now();

        $id = DB::table('HRIS_Hak_Cuti_Tambahan')->insertGetId($data);
        $hak = DB::table('HRIS_Hak_Cuti_Tambahan')->where('id_Hak_Cuti', $id)->first();

        return response()->json(['message' => 'Hak Cuti Tambahan berhasil ditambahkan', 'data' => $hak], 201);
    }

    public function updateHakCutiTambahan(Request $request, $id)
    {
        $data = $request->validate([
            'nama_rule' => 'required|string|max:255',
            'tahun_minimal' => 'required|integer',
            'golongan_min_id' => 'nullable|integer',
            'golongan_max_id' => 'nullable|integer',
            'tambahan_hari' => 'required|integer',
            'masa_berlaku_cuti' => 'required|integer',
            'Status' => 'nullable|string|max:1',
        ]);

        $data['Updated_By'] = auth()->user()->username ?? 'System';
        $data['Updated_At'] = Carbon::now();

        DB::table('HRIS_Hak_Cuti_Tambahan')
            ->where('id_Hak_Cuti', $id)
            ->update($data);

        $hak = DB::table('HRIS_Hak_Cuti_Tambahan')->where('id_Hak_Cuti', $id)->first();

        return response()->json(['message' => 'Hak Cuti Tambahan berhasil diupdate', 'data' => $hak]);
    }

    public function deleteHakCutiTambahan($id)
    {
        DB::table('HRIS_Hak_Cuti_Tambahan')->where('id_Hak_Cuti', $id)->delete();

        return response()->json(['message' => 'Hak Cuti Tambahan berhasil dihapus']);
    }
}
