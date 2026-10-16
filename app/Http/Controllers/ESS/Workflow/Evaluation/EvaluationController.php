<?php

namespace App\Http\Controllers\ESS\Workflow\Evaluation;

use App\Http\Controllers\Controller;
use App\Models\ESS\Workflow\Case\Models\Cases;
use App\Models\ESS\Workflow\Evaluation\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use RealRashid\SweetAlert\Facades\Alert;

class EvaluationController extends Controller
{
    public function index()
    {
        $title = 'Evaluation';
        $case = Cases::all();
        $data = Evaluation::orderBy('Id_Evaluation', 'desc')->get();
        $user = Auth::user();
        $query = 'select Id_Division, Name from KPI_Divisions a, KPI_User_Divisions b where   ';
        $query .= 'a.Id_Division = b.Division_Id and b.User_Id = :id_user ';
        $data_divisi_temp = DB::select($query, ['id_user' => $user->Id_Users]);

        return view('evaluation.index', ['data' => $data, 'title' => $title, 'cases' => $case, 'dataDiv' => $data_divisi_temp]);
    }

    public function create()
    {
        $title = 'Evaluation';
        $user = Auth::user();
        $query = 'select Id_Division, Name from KPI_Divisions a, KPI_User_Divisions b where   ';
        $query .= 'a.Id_Division = b.Division_Id and b.User_Id = :id_user ';
        $data_divisi_temp = DB::select($query, ['id_user' => $user->Id_Users]);

        return view('evaluation.tambah', ['title' => $title, 'datas' => $data_divisi_temp]);
    }

    public function store(Request $request)
    {
        try {

            $validation = $request->validate([
                'Name' => 'required|different:Evaluation, Name',
                'Description' => 'required',
                'Case_Id' => 'required',
            ]);

            $validation['Flag_Active'] = 'Y';
            Evaluation::insert($validation);
            Alert::success('Succeess', 'Evaluation berhasil Disimpan');

            return Redirect::to('/evaluation');
        } catch (\throwable $th) {
            Log::info('simpan Evaluation', [
                'pesan' => $th->getMessage(),
            ]);

            return back()->with('pesan', 'Terjadi Kesalahan!');
        }

    }

    public function destroy() {}

    // public function search(Request $request)
    //     {
    //         // Ambil semua data secara default
    //         $data = Evaluation::orderBy('Id_Evaluation', 'desc')->paginate(10);

    //         // Jika ada parameter pencarian
    //         if ($request->search) {
    //             $data = Evaluation::where('name', 'LIKE', '%' . $request->search . '%')->get();
    //         }

    //         $output = '';
    //         foreach ($data as $index => $item) {
    //             $output .= '
    //                 <tr>
    //                     <td>' . $index. '</td>
    //                     <td id="evalName" class="evalName">' . $item->Name . '</td>
    //                     <td id="evalDesc">' . $item->Description . '</td>
    //                     <td>' . ($item->Flag_Active == 'Y' ? 'Aktif' : 'Tidak Aktif') . '</td>
    //                     <td class="caseName" id="caseName">' . $item->cases->Name . '</td>
    //                 </tr>
    //             ';
    //         }

    //     return response()->json($output);
    // }

    public function get(Request $request)
    {
        if ($request->ajax()) {
            $output = '';
            $data = Cases::where('Division_Id', $request->search)->get();
            if ($data) {
                foreach ($data as $item) {

                    $output .= '
                    
                                        
                                        <option id="Case_Value" value=" '.$item->Id_Case.'">'.$item->Name.'</option>
                                        
                    ';
                }

                return response()->json($output);
            }

        }

        return back();
    }
}
