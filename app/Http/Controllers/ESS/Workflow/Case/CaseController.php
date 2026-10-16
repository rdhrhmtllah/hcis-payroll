<?php

namespace App\Http\Controllers\ESS\Workflow\Case;

use App\Http\Controllers\Controller;
use App\Models\ESS\Organization\Division\Models\Division;
use App\Models\ESS\Workflow\Case\Models\Cases;
use App\Models\ESS\Workflow\Evaluation\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use RealRashid\SweetAlert\Facades\Alert;

class CaseController extends Controller
{
    public function index()
    {
        $divisi = Division::orderBy('Name')->get();
        $title = 'Case';
        $user = Auth::user();
        $data = Cases::orderBy('division_id', 'asc')
            ->orderBy('Name', 'asc')
            ->paginate(10);
        $query = 'select Id_Division, Name from KPI_Divisions a, KPI_User_Divisions b where   ';
        $query .= 'a.Id_Division = b.Division_Id and b.User_Id = :id_user ';
        $data_divisi_temp = DB::select($query, ['id_user' => $user->Id_Users]);

        return view('case.index', ['data' => $data, 'title' => $title, 'divisions' => $divisi, 'dataDiv' => $data_divisi_temp]);
    }

    public function create()
    {
        $title = 'Case';
        $user = Auth::user();
        $query = 'select Id_Division, Name from KPI_Divisions a, KPI_User_Divisions b where   ';
        $query .= 'a.Id_Division = b.Division_Id and b.User_Id = :id_user ';
        $data_divisi_temp = DB::select($query, ['id_user' => $user->Id_Users]);

        return view('case.tambah', ['title' => $title, 'datas' => $data_divisi_temp]);
    }

    public function store(Request $request)
    {
        $validation = $request->validate([
            'Name' => 'required|unique:KPI_Cases,Name',
            'Division_Id' => 'required',
            'Description' => 'required',
        ]);

        $validation['Flag_Active'] = 'Y';
        Cases::insert($validation);
        Alert::success('Success', 'Case Berhasil Disimpan');

        return Redirect::to('/case');
    }

    public function destroy(Request $request)
    {
        $case = Evaluation::where('Case_Id', $request->input('id'))->exists();
        if ($case) {
            return back()->with('pesan', 'Case sudah di binding dengan evaluations yang ada ....');
        }
        $del = Cases::where('Id_Case', $request->id)->delete();
        if ($del) {
            return back()->with('pesan', 'Data berhasil dihapus');
        }
    }

    public function edit(Request $request)
    {
        // dd($request->all());

        $validation = $request->validate([
            'Name' => 'required|unique:KPI_Cases,Name',
            'Description' => 'required',
            'Division_Id' => 'required',

        ]);

        $validation['Id_User_Updated'] = Auth::user()->Id_Users;
        // dd($validation);

        Cases::where('Id_Case', $request->id)->update($validation);

        Alert::success('Success', 'Case Berhasil Diupdate');

        return back();
    }
}
