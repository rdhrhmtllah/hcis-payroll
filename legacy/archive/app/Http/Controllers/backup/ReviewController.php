<?php

namespace App\Http\Controllers;

use App\Models\CategoryEvaluations;
use App\Models\Evaluation;
use App\Models\EvaluationsGlobal;
use App\Models\GlobalReview;
use App\Models\GlobalReviewDetail;
use App\Models\MasterPeriode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use RealRashid\SweetAlert\Facades\Alert;
use Vinkla\Hashids\Facades\Hashids;

class ReviewController extends Controller
{
    public function index()
    {
        $user = Auth()->user();

        $divisiUser = $user->Division_Id;
        // dd($divisiUser);

        $dataUser = User::where('Division_Id', $divisiUser)
            ->where('Id_Users', '!=', Auth()->user()->Id_Users)
            ->get();

        $query = 'select Id_Division, Name from KPI_Divisions a, KPI_User_Divisions b where   ';
        $query .= 'a.Id_Division = b.Division_Id and b.User_Id = :id_user ';
        $data_divisi = DB::select($query, [
            'id_user' => $user->Id_Users,
        ]);

        $query = 'select Id_Division, Name from KPI_Divisions a, KPI_User_Divisions b where   ';
        $query .= 'a.Id_Division = b.Division_Id and b.User_Id = :id_user ';
        $data_divisi_temp = DB::select($query, [
            'id_user' => $user->Id_Users,
        ]);

        $data_divisi = [];

        foreach ($data_divisi_temp as $item) {
            $temp = [];
            $temp['Id_Division'] = Hashids::connection('custom')->encode($item->Id_Division);
            $temp['Name'] = $item->Name;

            $data_divisi[] = $temp;
        }

        $title = 'Global Review';

        return Inertia::render('GeneralReview', [
            'title' => 'Penilaian Global',
            // 'data' => $data, // kategori + sub kategori
            // 'user' => $dataUser, // daftar user yang dinilai,,
            // 'dataPeriode' => $dataPeriode,
            'data_division' => $data_divisi,
        ]);
    }

    public function getPeriode($id_temp)
    {
        try {
            $user = Auth()->user();
            // dd($user);
            $id = Hashids::connection('custom')->decode($id_temp)[0];
            // dd($id)
            $dataPeriode_temp = MasterPeriode::select('Id_Periode', 'Kode_Periode', 'Keterangan')->where([
                'Division_Id' => $id,
                'Flag_Sudah_Review' => null,
            ])->where('Tanggal_Awal', '<=', Carbon::today())
                ->where('Tanggal_Akhir', '>=', Carbon::today())
                ->OrderBy('Kode_Periode')->get();
            // dd($data);
            $dataPeriode = [];
            foreach ($dataPeriode_temp as $item) {
                $temp = [];
                $temp['Id_Periode'] = Hashids::connection('custom')->encode($item->Id_Periode);
                $temp['Kode_Periode'] = $item->Kode_Periode;
                $temp['Keterangan'] = $item->Keterangan;

                $dataPeriode[] = $temp;
            }

            return response()->json([
                'data' => $dataPeriode,
                // 'cases' => $dataCase
            ]);

            // Menyimpan log revisi ke database

        } catch (\Throwable $e) {
            // dd("oke");
            Log::channel('review_log')->info('review_log', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'terjadi kesalahan',
            ]);
        }
    }

    public function getReviewUser($id_temp)
    {
        try {
            $user = Auth()->user();
            // dd($user);
            $id = Hashids::connection('custom')->decode($id_temp)[0];
            // dd($id)
            $dataUser = User::where('Division_Id', $id)
                ->where('Id_Users', '!=', Auth()->user()->Id_Users)
                ->get();

            $data = [];

            $categoryEvaluations = CategoryEvaluations::where('division_id', $id)->get();

            foreach ($categoryEvaluations as $item) {
                $temp = [];

                $temp['category'] = $item->Name;
                $temp['sub_category'] = [];
                $dataGlobalEva = EvaluationsGlobal::where('category_evaluation_id', $item->Id_Category_Evaluation)->get();

                foreach ($dataGlobalEva as $item2) {
                    // dd($item2->Name);
                    array_push($temp['sub_category'], [
                        'id' => $item2->Id_Evaluations_Global,
                        'name' => $item2->Name,
                    ]);
                }

                $data[] = $temp;
            }

            return response()->json([
                'data' => $data,
                'user' => $dataUser,
                // 'cases' => $dataCase
            ]);

            // Menyimpan log revisi ke database

        } catch (\Throwable $e) {
            // dd("oke");
            Log::channel('review_log')->info('review_log', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'terjadi kesalahan',
            ]);
        }
    }

    public function index2()
    {
        $user = Auth()->user();

        $divisiUser = $user->Division_Id;
        // dd($divisiUser);

        $dataUser = User::where('Division_Id', $divisiUser)
            ->where('Id_Users', '!=', Auth()->user()->Id_Users)
            ->get();

        // dd($dataUser);
        $evaluationCategory = Evaluation::where('Case_Id', 3)->get();

        $data = [];
        $categoryEvaluations = CategoryEvaluations::where('division_id', $divisiUser)->get();

        foreach ($categoryEvaluations as $item) {
            $temp = [];

            $temp['category'] = $item->Name;
            $temp['sub_category'] = [];
            $dataGlobalEva = EvaluationsGlobal::where('category_evaluation_id', $item->Id_Category_Evaluation)->get();

            foreach ($dataGlobalEva as $item2) {
                // dd($item2->Name);
                array_push($temp['sub_category'], [
                    'id' => $item2->Id_Evaluations_Global,
                    'name' => $item2->Name,
                ]);
            }

            $data[] = $temp;
        }

        $dataPeriode = MasterPeriode::select('Id_Periode', 'Kode_Periode', 'Keterangan')->where([
            'Division_Id' => Auth::user()->Division_Id,
            'Flag_Sudah_Review' => null,
        ])->get();
        // dd($dataPeriode);

        // dd($evaluationCategory);
        $title = 'Global Review';

        // dd($user);
        //  return view('review.index', ['title' => $title, 'evaluationCategory' => $evaluationCategory, 'user' => $dataUser, 'data' => $data]);
        return Inertia::render('GeneralRiview2', [
            'title' => 'Penilaian Global',
            'data' => $data, // kategori + sub kategori
            'user' => $dataUser, // daftar user yang dinilai,,
            'dataPeriode' => $dataPeriode,
        ]);
    }

    public function summaryReviewIndex()
    {
        $title = 'Daftar Summary Global Review';

        $query = 'select a.User_Id_Review as id,a.No_Review,d.Name as Division,c.Keterangan,c.Tanggal_Awal,c.Tanggal_Akhir, b.nama as username from KPI_Global_Review a, KPI_Users b, KPI_Master_Periode c ,kpi_divisions d ';
        $query .= 'where a.User_Id_Review = b.Id_Users and a.Id_Periode = c.Id_Periode and a.Division_Id = d.Id_Division and a.User_Id_Review = :id_user ';

        $data_temp = DB::select($query, ['id_user' => Auth::user()->Id_Users]);
        $data = [];
        foreach ($data_temp as $item) {
            $temp = [];
            $temp['id'] = Hashids::connection('custom')->encode($item->id);
            $temp['No_Review'] = $item->No_Review;
            $temp['Keterangan'] = $item->Keterangan;
            $temp['Tanggal_Awal'] = $item->Tanggal_Awal;
            $temp['Tanggal_Akhir'] = $item->Tanggal_Akhir;
            $temp['username'] = $item->username;
            $temp['division'] = $item->Division;
            // $temp['User_Id_Review'] = $item->User_Id_Review;
            $data[] = $temp;
        }

        return view('review.summary_review', ['data' => $data, 'title' => $title]);
    }

    public function summaryReview($no_review, $id)
    {
        $user = Auth()->user();

        $divisiUser = $user->Division_Id;
        // dd($divisiUser);

        $dataUser = User::where('Division_Id', $divisiUser)
            ->where('Id_Users', '!=', Auth()->user()->Id_Users)
            ->get();

        // dd($dataUser);
        $evaluationCategory = Evaluation::where('Case_Id', 3)->get();

        $data = [];
        $categoryEvaluations = CategoryEvaluations::where('division_id', $divisiUser)->get();

        foreach ($categoryEvaluations as $item) {
            $temp = [];

            $temp['category'] = $item->Name;
            $temp['sub_category'] = [];
            $dataGlobalEva = EvaluationsGlobal::where('category_evaluation_id', $item->Id_Category_Evaluation)->get();

            foreach ($dataGlobalEva as $item2) {
                // dd($item2->Name);
                array_push($temp['sub_category'], [
                    'id' => $item2->Id_Evaluations_Global,
                    'name' => $item2->Name,
                ]);
            }

            $data[] = $temp;
        }
        // $no_review = $request->input('no_review');
        $query1 = 'select b.Keterangan, b.Tanggal_Awal,b.Tanggal_Akhir from KPI_Global_Review a, kpi_master_periode b where a.Id_Periode = b.Id_Periode and a.No_Review = :No_Review ';
        $periode = DB::select($query1, [
            'No_Review' => str_replace('@', '/', $no_review),
        ]);

        $queryKPI = 'select Category_Evaluation_Id, c.Description From KPI_Global_Review_Detail a, KPI_Evaluations_Global b, KPI_Category_Evaluations c where a.No_Review = :no_review ';
        $queryKPI .= 'and  a.Evaluation_Id = b.Id_Evaluations_Global and b.Category_Evaluation_Id = c.Id_Category_Evaluation ';
        $queryKPI .= 'group by Category_Evaluation_Id,c.Description ';

        $kpi = DB::select($queryKPI, ['no_review' => str_replace('@', '/', $no_review)]);

        $dataKPI = [];
        foreach ($kpi as $item) {
            $temp = [];

            $temp['case_category'] = $item->Description;
            // ambil total ticket + global rewview
            $string = 'with cte as (';
            $string .= 'select a.User_Id_Review as id_users,b.Employee_Id,c.nama as Name, ROUND(sum(Score) / count(Score),2) as AVG,';
            $string .= "isnull((select count(x.No_Ticket) from KPI_Ticket x, KPI_Ticket_Response y where b.Employee_Id = x.Recipient_Id and x.No_Ticket = y.No_Ticket and y.Flag_Finish_Reciepent = 'Y' ";
            $string .= "and Y.Flag_Finish_Requester = 'Y' and x.Tanggal_Selesai between d.Tanggal_Awal and d.Tanggal_Akhir), 0 ) as Total_Ticket, ";
            $string .= "isnull((select sum(y.Score)/ count(y.no_ticket) from KPI_Ticket x, kpi_ticket_detail_review y,KPI_Ticket_Response z where b.Employee_Id = x.Recipient_Id  and x.No_Ticket = z.No_Ticket and z.Flag_Finish_Reciepent = 'Y'  ";
            $string .= "and z.Flag_Finish_Requester = 'Y' and x.Tanggal_Selesai between d.Tanggal_Awal and d.Tanggal_Akhir ";
            $string .= 'and x.No_Ticket = y.No_Ticket ';
            $string .= '), 0 ) as Total_Score_Ticket ';
            $string .= 'From KPI_Global_Review a, KPI_Global_Review_Detail b, KPI_Users c, KPI_Master_Periode d, KPI_Evaluations_Global e ';
            $string .= 'where a.No_Review = b.No_Review and a.status is null    ';
            $string .= 'and b.Employee_Id = c.Id_Users ';
            $string .= 'and a.Id_Periode = d.Id_Periode ';
            $string .= 'and b.Evaluation_Id = e.Id_Evaluations_Global ';
            $string .= 'and b.No_Review = :no_review   and e.Category_Evaluation_Id = :category_evaluation_id ';
            $string .= 'group by a.User_Id_Review,b.Employee_Id,c.nama,b.Employee_Id,d.Tanggal_Awal,d.Tanggal_Akhir ';
            $string .= ') select Name,AVG,Total_Ticket,Employee_Id,Total_Score_Ticket, (avg+total_score_ticket) /2 as Total_Keseluruhan ';
            $string .= 'from cte  where id_users = :id_user ';
            // dd($string);
            $dataTotalAVRG = DB::select($string, [
                'no_review' => str_replace('@', '/', $no_review),
                'category_evaluation_id' => $item->Category_Evaluation_Id,
                'id_user' => Auth::user()->Id_Users,
            ]);
            // dd($dataTotalAVRG);

            $temp['sub_category'] = [];

            foreach ($dataTotalAVRG as $item2) {
                array_push($temp['sub_category'], [
                    'Id_User_Recipient' => Hashids::connection('custom')->encode($item2->Employee_Id),
                    'name' => $item2->Name,
                    'AVG' => $item2->AVG,
                    'Total_Ticket' => $item2->Total_Ticket,
                    'Total_Score_Ticket' => round($item2->Total_Score_Ticket, 2),
                    'Total_Keseluruhan' => round($item2->Total_Keseluruhan, 2),
                ]);
            }

            $dataKPI[] = $temp;
        }

        $title = 'Global Review';

        return Inertia::render('SummaryReview', [
            'title' => 'Penilaian Global',
            'data' => $data, // kategori + sub kategori
            'user' => $dataUser,  // daftar user yang dinilai
            'no_review' => str_replace('@', '/', $no_review),
            'periode' => $periode[0]->Keterangan,
            'dataKPI' => $dataKPI,
            'tanggal' => date('d M Y', strtotime($periode[0]->Tanggal_Awal)).' - '.date('d M Y', strtotime($periode[0]->Tanggal_Akhir)),
        ]);
    }

    public function store(Request $request)
    {
        // Validate the request
        $request->validate([
            // 'criteria_ids' => 'required|array',
            // 'user_ids' => 'required|array',
            'ratings' => 'required|array',
        ]);

        $currentDate = Carbon::now();
        $user = Auth::user();
        $assessorId = $user->Id_Users;

        // Get the submitted data
        $criteriaIds = $request->input('criteria_ids');
        $userIds = $request->input('user_ids');
        $ratings = $request->input('ratings');
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalAkhir = $request->input('tanggal_akhir');
        $periode = Hashids::connection('custom')->decode($request->input('periode'))[0];
        $division = Hashids::connection('custom')->decode($request->input('division'))[0];

        try {
            // Get all ratings data
            DB::beginTransaction();

            $ratings = $request->input('ratings');

            // Create array to store all records
            $records = [];

            $format_tanggal_sekarang = date('y').'/'.date('m');

            $string = 'SELECT TOP 1  ';
            $string .= 'RIGHT(No_Review, 4) as angka FROM KPI_Global_Review ';
            $string .= "WHERE LEFT(No_Review, 9) = 'RV-".$format_tanggal_sekarang."-'";
            $string .= 'order by RIGHT(No_Review,4) desc ';
            // dd($string);
            $check_last_faktur = DB::select($string);
            if (count($check_last_faktur) == 0) {
                $angka_terakhir = 1;
            } else {
                // dd("sini");
                $angka_terakhir = (int) $check_last_faktur[0]->angka + 1;
                // dd($angka_terakhir);
            }
            // dd($angka_terakhir);

            $init = 'RV'.'-'.date('y/m');
            $no_faktur = $init.'-'.sprintf('%04d', $angka_terakhir);

            GlobalReview::insert([
                'No_Review' => $no_faktur,
                'Date' => $currentDate->toDateString(),
                'Time' => $currentDate->format('H:i:s'),
                'User_Id_Review' => $assessorId,
                'Id_Periode' => $periode,
                'Division_Id' => $division,
            ]);

            // Loop through each user's ratings
            foreach ($ratings as $userId => $userRatings) {
                // Loop through each criteria rating for this user
                foreach ($userRatings as $criteriaId => $score) {
                    $records[] = [
                        'No_Review' => $no_faktur,
                        // 'Date' => $currentDate->toDateString(),
                        // 'Time' => $currentDate->format('H:i:s'),
                        'Score' => $score,
                        'Evaluation_Id' => $criteriaId,
                        // 'Assesor_Id' => $assessorId,
                        'Employee_Id' => $userId,
                        // 'Tanggal_Mulai' => $tanggalMulai,
                        // 'Tanggal_Akhir' => $tanggalAkhir
                        // 'Id_Periode' => $periode
                    ];
                }
            }

            // Batch insert all records
            GlobalReviewDetail::insert($records);

            MasterPeriode::where('Id_Periode', $periode)->update([
                'Flag_Sudah_Review' => 'Y',
            ]);

            DB::commit();
            Alert::success('Success', 'KPI Global Review has been submitted successfully.');

            return redirect()->back();
        } catch (\Throwable $e) {
            // Alert::error('Error', 'An error occurred while saving the review. Please try again.');
            Log::info($e->getMessage());

            return response()->json([
                'message' => 'Gagal menyimpan data',
                'error' => 'An error occurred while saving the review. Please try again',
            ], 500); // Penting: set status 500 agar masuk onError
        }
    }
}
