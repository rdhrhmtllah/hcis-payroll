<?php

namespace App\Http\Controllers\ESS\Workflow\Project;

use App\Http\Controllers\Controller;
use App\Models\ESS\Workflow\Project\Models\Project;
use App\Models\ESS\Workflow\Task\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use RealRashid\SweetAlert\Facades\Alert;

class ProjectController extends Controller
{
    public function index()
    {
        $title = 'Project';
        $projects = Project::all();

        return view('project.index', ['title' => $title, 'data' => $projects]);
    }

    public function create()
    {
        $title = 'Project';

        return view('project.tambah', ['title' => $title]);
    }

    public function store(Request $request)
    {

        $validation = $request->validate([
            'Kode_Project' => 'required',
            'Keterangan' => 'required',
        ]);

        try {
            DB::beginTransaction();

            Project::create($validation);

            DB::commit();
            Alert::success('Success', 'Project Berhasil Disimpan!');

            return Redirect::to('/project');
        } catch (\throwable $th) {
            DB::rollBack();
            Log::info('Simpan Project', [
                'pesan' => $th->getMessage(),

            ]);

            return back()->with('pesan', 'Terjadi Kesalahan!');
        }
    }

    public function destroy(Request $request)
    {
        $task = Task::where('Project_Id', $request->id)->exists();
        try {
            // dd($task);

            if (! $task) {
                DB::beginTransaction();

                $project = Project::find($request->id);
                $project->delete();
                DB::commit();
            } else {

                return back()->with('pesan', 'Terdapat Task Untuk Project!');
            }

            return back()->with('pesan', 'Project Berhasil Dihapus');

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info('Simpan Case', [
                'pesan' => $th->getMessage(),
            ]);

            return back()->with('pesan', 'Terjadi Kesalahan!');

        }
    }

    public function edit(Request $request)
    {
        $validation = $request->validate([

            'Kode_Project' => [
                'required',
                Rule::unique('KPI_Master_Project', 'Kode_Project')->ignore($request->Kode_Project, 'Kode_Project'),
            ],
            'Keterangan' => 'required',
        ]);
        try {
            DB::beginTransaction();
            $project = Project::find($request->id);
            $project->update($validation);
            DB::commit();
            Alert::success('Success', 'Project Berhasil Diupdate');

            return back();
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info('Simpan Project', [
                'pesan' => $th->getMessage(),
            ]);

            return back()->with('pesan', 'Terjadi Kesalahan');
        }
    }
}
