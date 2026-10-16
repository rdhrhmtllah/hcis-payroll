<?php

namespace App\Http\Controllers\ESS\Workflow\Task;

use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\User;
use App\Models\ESS\Organization\Division\Models\Division;
use App\Models\ESS\Workflow\Models\Progress;
use App\Models\ESS\Workflow\Project\Models\Project;
use App\Models\ESS\Workflow\Task\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use RealRashid\SweetAlert\Facades\Alert;

class TaskController extends Controller
{
    //

    public function index()
    {
        $title = 'Task';
        $user = Auth::user();
        if ($user->Level_Id == 2) {
            $tasks = Task::orderBy('End_Date', 'asc')->get();

        } else {
            $tasks = Task::where('User_Id', $user->Id_Users)->orderBy('End_Date', 'asc')->get();

        }
        $dataProgress = Progress::orderBY('Id_Progress')->get();
        $dataProject = Project::select('Id_Project', 'Kode_Project')->get();
        $ganttDatas = $tasks->map(function ($task) {
            return [
                'id' => $task->Id_Task,
                'name' => $task->Task,
                'start' => $task->Start_Date,
                'end' => $task->End_Date,
                'project' => $task->project->Keterangan,
                'division' => $task->division->Name,
                'user' => $task->user->Nama,
                'status' => $task->urgency_status,
                'progress' => $task->progress->Progress,
                'id_progress' => $task->Progress_Id,
            ];
        });
        $dummy = include base_path('public/assets/items_data.php');
        $ganttData = collect($dummy['items']);

        // dd($ganttData);
        return inertia('ESS/Workflow/Task/timeline', ['data' => $tasks, 'title' => $title, 'dataProject' => $dataProject, 'dataGant' => $ganttData, 'dataProgress' => $dataProgress]);
        // return view('task.index',['data'=>$tasks, 'title'=>$title, 'dataProject'=>$dataProject, 'dataProgress'=> $dataProgress,'ganttData'=> $ganttData]);
    }

    public function create()
    {
        $title = 'Task';
        $Project = Project::select('Id_Project', 'Kode_Project', 'Keterangan')->get();
        $Dvision = Division::where('Flag_Active', 'Y')->get();

        return view('task.tambah', ['datas' => $Project, 'title' => $title, 'division' => $Dvision]);
    }

    public function getUser(Request $request)
    {

        if ($request->ajax()) {
            $user = User::where('Division_Id', $request->divisi)->get();
            $out = '';
            foreach ($user as $row) {
                $out .= '<option value=" '.$row->Id_Users.' "> '.$row->Username.' </option>';
            }

            return response()->json(
                $out
            );
        }

    }

    public function store(Request $request)
    {
        $validation = $this->validate($request, [

            'Task' => 'required',
            'Project_Id' => 'required',
            'Division_Id' => 'required',
            'User_Id' => 'required',
            'Start_Date' => 'required|date',
            'End_Date' => 'required|date|after:Start_Date',
            'urgency_status' => 'required',
        ]);
        try {
            DB::beginTransaction();
            $validation['Tanggal'] = now()->toDateString();
            $validation['Jam'] = now()->toTimeString();
            $validation['Progress_Id'] = 1;

            Task::create($validation);

            DB::commit();
            Alert::success('Succeess', 'Task Berhasil Disimpan!');

            return Redirect::to('/task');

        } catch (\throwable $th) {
            DB::rollBack();
            Log::info('Simpan Task', [
                'pesan' => $th->getMessage(),
            ]);

            return back()->with('pesan', 'Terjadi Kesalahan');
        }

    }

    public function edit(Request $request)
    {
        $validation = $request->validate([
            'Project_Id' => 'required',
            'Task' => 'required',
            'Start_Date' => 'required',
            'End_Date' => 'required',
        ]);
        try {
            DB::beginTransaction();
            Task::Where('Id_Task', $request->id)->update($validation);
            DB::commit();
            Alert::success('Success', 'Task Berhasil DIupdate');

            return back();
        } catch (\throwable $th) {
            DB::rollBack();
            Log::info('Simpan Task', [
                'pesan' => $th->getMessage(),
            ]);

            return back()->with('Pesan', 'Terjadi Kesalahan');
        }
    }

    public function destroy(Request $request)
    {
        try {
            DB::beginTransaction();
            $task = Task::find($request->id);
            $task->delete();
            DB::commit();

            return back()->with('pesan', 'Task Berhasil Dihapus');
        } catch (\throwable $th) {
            DB::rollBack();
            Log::info('Simpan Case', [
                'Pesan' => $th->getMessage(),
            ]);

            return back()->with('pesan', 'Terjadi Kesalahan!');
        }
    }

    public function progressUpdate(Request $request)
    {
        if ($request->all()) {
            $validation = $request->validate([
                'Progress_Id' => 'required',

            ]);
            $task = Task::find($request->Task_Id)->exists();

            if ($task) {
                Task::Where('Id_Task', $request->Task_Id)->update($validation);

                return response()->json([
                    'success' => true, 'message' => 'Progress has been updated',
                ]);

            } else {

                return response()->json([
                    'success' => false, 'message' => 'Task Not Found',
                ]);
            }

            return response()->json($request->all());
        }
    }
}
