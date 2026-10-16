<?php

namespace App\Http\Controllers;

use App\Models\CategoryCross;
use App\Models\CrossReviewDetails;
use App\Models\CrossReviewPeriode;
use App\Models\CrossReviews;
use App\Models\Division;
use App\Models\EvaluationCross;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use RealRashid\SweetAlert\Facades\Alert;
use Vinkla\Hashids\Facades\Hashids;

class CrossDivisionReviewController extends Controller
{
    //
    public function index()
    {
        $title = 'Cross Division Review';
        $division = Division::where('Flag_Active', 'Y')->get();
        $out = [];
        foreach ($division as $divisi) {
            $temp = [];
            $temp['Id_Division'] = Hashids::connection('custom')->encode($divisi->Id_Division);
            $temp['Name'] = $divisi->Name;

            $out[] = $temp;
        }

        return inertia('CrossDivisionReview', [
            'title' => $title,
            'division' => $out,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'ratings' => 'required|array',
        ]);

        $currenDate = now();
        $userNow = Auth::user();
        $IdNow = $userNow->Id_Users;
        $ratings = $request->input('ratings');
        $division = Hashids::connection('custom')->decode($request->input('division'));
        $periode = Hashids::connection('custom')->decode($request->input('periode'));
        try {
            DB::beginTransaction();

            $format_tanggal_sekarang = date('y').'/'.date('m');

            $string = 'SELECT TOP 1  ';
            $string .= 'RIGHT(No_Review, 4) as angka FROM KPI_Cross_Review ';
            $string .= "WHERE LEFT(No_Review, 9) = 'RC-".$format_tanggal_sekarang."-'";
            $string .= 'order by RIGHT(No_Review,4) desc ';
            $check_last_faktur = DB::select($string);
            if (count($check_last_faktur) == 0) {
                $angka_terakhir = 1;
            } else {
                $angka_terakhir = (int) $check_last_faktur[0]->angka + 1;
            }
            $init = 'RC'.'-'.date('y/m');
            $no_faktur = $init.'-'.sprintf('%04d', $angka_terakhir);
            // Insert ke CrossReviews
            CrossReviews::insert([
                'No_Review' => $no_faktur,
                'Date' => $currenDate->toDateString(),
                'Time' => $currenDate->toTimeString(),
                'User_Id_Review' => $IdNow,
                'Division_Id' => $division[0],
                'Id_Periode' => $periode[0],
            ]);

            $records = [];
            foreach ($ratings as $userId => $userRatings) {
                foreach ($userRatings as $criteriaId => $score) {
                    $records[] = [
                        'No_Review' => $no_faktur,
                        'Score' => $score,
                        'Evaluation_Id' => Hashids::connection('custom')->decode($criteriaId)[0],
                        'Employee_Id' => Hashids::connection('custom')->decode($userId)[0],
                    ];
                }
            }

            if (empty($records)) {
                throw new \Exception('Tidak ada data yang bisa disimpan ke review detail');
            }

            $inserted = CrossReviewDetails::insert($records);

            if (! $inserted) {
                throw new \Exception('Gagal insert ke GlobalReviewDetail');
            }

            DB::commit();
            Alert::success('Success', 'Cross Review has been submitted');

            return redirect()->back();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Gagal simpan review: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to Save Data',
                'error' => 'An error occurred while saving the cross review, Please Try Again',
            ], 500);
        }
    }

    public function getPeriode($id_temp)
    {
        try {
            $user = Auth()->user();
            // dd($user);
            $id = Hashids::connection('custom')->decode($id_temp);
            // dd($id_temp);
            $dataPeriode_temp = CrossReviewPeriode::select('Id_Periode', 'Kode_Periode', 'Keterangan')->where([
                'Division_Id' => $id,
                'Flag_Sudah_Review' => null,

            ])->where('Tanggal_Awal', '<=', Carbon::today())
                ->where('Tanggal_Akhir', '>=', Carbon::today())
                ->OrderBy('Kode_Periode')->get();
            $dataPeriode = [];
            // dd($dataPeriode_temp[0]);

            foreach ($dataPeriode_temp as $item) {
                $temp = [];
                $temp['Id_Periode'] = Hashids::connection('custom')->encode($item->Id_Periode);
                $temp['Kode_Periode'] = $item->Kode_Periode;
                $temp['Keterangan'] = $item->Keterangan;

                $dataPeriode[] = $temp;
            }

            // dd($dataPeriode);
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
                'error' => 'terjadi kesalahan  ',
            ]);
        }
    }

    public function getReviews($id_temp)
    {
        try {

            $user = Auth::user();
            $angka_Periode = Hashids::connection('custom')->decode($id_temp)[0];
            $periodeChoose = CrossReviewPeriode::where('Id_Periode', $angka_Periode)->first();
            // dd($periodeChoose);
            $category = CategoryCross::where('Flag_Active', 'Y')->get();

            // $query = "
            //     WITH cte AS (
            //         SELECT
            //             a.Requester_Id AS id_user,
            //             b.Nama,
            //             ISNULL((
            //                 SELECT TOP 1 'Y'
            //                 FROM KPI_Cross_Review_Detail x, KPI_Cross_Review i
            //                 WHERE a.Requester_Id = x.Employee_Id AND x.No_Review = i.No_Review AND i.Status is NULL
            //             ), NULL) AS Sudah_Review
            //         FROM KPI_Ticket a
            //         JOIN KPI_Users b ON a.Requester_Id = b.Id_Users
            //         WHERE
            //             a.Date >= ? AND
            //             a.Tanggal_Selesai <= ? AND
            //             a.Division_Id = ? AND
            //             a.Recipient_Id = ? AND
            //             b.Division_Id = ?

            //         UNION ALL

            //         SELECT
            //             a.Recipient_Id AS id_user,
            //             b.Nama,
            //             ISNULL((
            //                 SELECT TOP 1 'Y'
            //                 FROM KPI_Cross_Review_Detail x, KPI_Cross_Review i
            //                 WHERE a.Recipient_Id = x.Employee_Id AND x.No_Review = i.No_Review AND i.Status is NULL
            //             ), NULL) AS Sudah_Review
            //         FROM KPI_Ticket a
            //         JOIN KPI_Users b ON a.Recipient_Id = b.Id_Users
            //         WHERE
            //             a.Date >= ? AND
            //             a.Tanggal_Selesai <= ? AND
            //             a.Division_Id = ? AND
            //             a.Requester_Id = ? AND
            //             b.Division_Id = ?
            //     )
            //     SELECT id_user, Nama
            //     FROM cte
            //     WHERE Sudah_Review IS NULL
            //     GROUP BY id_user, Nama
            // ";

            $query = "
                WITH cte AS (
                    SELECT
                        a.Requester_Id AS id_user,
                        b.Nama,
                        ISNULL((
                            SELECT TOP 1 'Y'
                            FROM KPI_Cross_Review_Detail x, KPI_Cross_Review i
                            WHERE a.Requester_Id = x.Employee_Id and x.No_Review = i.No_Review AND i.Status is NULL
                        ), NULL) AS Sudah_Review
                    FROM KPI_Ticket a
                    JOIN KPI_Users b ON a.Requester_Id = b.Id_Users
                    JOIN KPI_Master_Cross_Periode c ON 
                        a.Division_Id = c.Division_Id
                    WHERE a.Recipient_Id = :recipient_id AND b.Division_Id = :division_id and c.Id_Periode = :id_periode and a.Tanggal_Selesai between c.Tanggal_Awal and c.Tanggal_Akhir 

                    UNION ALL

                    SELECT
                        a.Recipient_Id AS id_user,
                        b.Nama,
                        ISNULL((
                            SELECT TOP 1 'Y'
                            FROM KPI_Cross_Review_Detail x , KPI_Cross_Review i
                            WHERE a.Recipient_Id = x.Employee_Id and x.No_Review = i.No_Review AND  i.Status is NULL
                        ), NULL) AS Sudah_Review
                    FROM KPI_Ticket a
                    JOIN KPI_Users b ON a.Recipient_Id = b.Id_Users
                JOIN KPI_Master_Cross_Periode c ON 
                    
                    a.Division_Id = c.Division_Id
                    WHERE a.Requester_Id = :requester_id AND b.Division_Id = :division_id2  and c.Id_Periode = :id_periode2 and a.Tanggal_Selesai between c.Tanggal_Awal and c.Tanggal_Akhir 
                )
                SELECT id_user, Nama
                FROM cte 
                WHERE Sudah_Review IS NULL
                GROUP BY id_user, Nama
";

            $dataTicket = DB::select($query, [
                'recipient_id' => $user->Id_Users,
                'division_id' => $periodeChoose->Division_Id,
                'id_periode' => $angka_Periode,
                'requester_id' => $user->Id_Users,
                'division_id2' => $periodeChoose->Division_Id,
                'id_periode2' => $angka_Periode,
                // $periodeChoose[0]->Tanggal_Awal,
                // $periodeChoose[0]->Tanggal_Akhir,
                // $periodeChoose[0]->Division_Id,
                // $user->Id_Users,
                // $periodeChoose[0]->Division_Id,
                // $periodeChoose[0]->Tanggal_Awal,
                // $periodeChoose[0]->Tanggal_Akhir,
                // $periodeChoose[0]->Division_Id,
                // $user->Id_Users,
                // $periodeChoose[0]->Division_Id,
            ]);
            // dd($dataTicket);
            $data = [];
            foreach ($category as $item) {
                $temp = [];
                $temp['category'] = $item->Name;
                $temp['sub_category'] = [];
                $dataEval = EvaluationCross::where('Category_Cross_Id', $item->Id_Category_Cross)->get();
                foreach ($dataEval as $itemEval) {
                    array_push($temp['sub_category'], [
                        'id' => Hashids::connection('custom')->encode($itemEval->Id_Evaluations_Cross),
                        'Name' => $itemEval->Name,
                    ]);
                }
                $data[] = $temp;
            }
            // dd($dataTicket);
            if (empty($dataTicket) || empty($data)) {

                return response()->json([
                    'message' => 'Belum ada data cross review',
                ], 422);
            }

            $userTicket = [];
            foreach ($dataTicket as $user) {
                $temp = [];
                $temp['id_user'] = Hashids::connection('custom')->encode($user->id_user);
                $temp['Nama'] = $user->Nama;

                $userTicket[] = $temp;
            }

            return response()->json([
                'user' => $userTicket,
                'data' => $data,
            ]);
        } catch (\throwable $th) {
            Log::channel('review_log')->info('review_log', [
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'error' => 'Terjadi Kesalahan!!! ',
            ]);
        }
    }

    public function history()
    {
        $title = 'History Cross Review';
        $cross = CrossReviews::where('Status', null)->get();
        $query = '
                with cte as(SELECT a.No_Review, b.Nama, a.Time, b.Id_Users, a.Date, c.Name as division
                FROM KPI_Cross_Review a
                JOIN KPI_Cross_Review_Detail d ON a.No_Review = d.No_Review
                JOIN KPI_Users b ON d.Employee_Id = b.Id_Users JOIN KPI_Divisions c on b.Division_Id = c.Id_Division

                )select No_Review, Nama, Time, Id_Users, Date, division from cte group by No_Review,Nama, Time, Id_Users, Date, division
                    ';
        $Data_User = DB::select($query);

        return view('cross.history', ['title' => $title, 'data' => $Data_User]);
    }

    public function periode()
    {
        $title = 'Cross Review Periode';
        $periode = CrossReviewPeriode::all();

        // dd($periode);

        return view('cross.periode', ['title' => $title, 'data' => $periode]);
    }

    public function addPeriode()
    {
        $title = 'Tambah Data Periode';
        $division = Division::where('Flag_Active', 'Y')->get();

        return view('cross.tambah', ['title' => $title, 'datas' => $division]);
    }

    public function storePeriode(Request $request)
    {
        // dd($request->all());
        $validation = $request->validate([
            'Kode_Periode' => 'required',
            'Division_Id' => 'required',
            'Tanggal_Awal' => 'required|date',
            'Tanggal_Akhir' => 'required|date|after:Tanggal_Awal',
            'Keterangan' => 'required',
            'User_Id' => 'required',
        ]);
        // dd($validation);

        try {
            DB::beginTransaction();
            CrossReviewPeriode::create($validation);

            DB::commit();
            Alert::success('Success', 'Period hass been saved!');

            return Redirect::to('/crossReviewPeriode');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info('Error add Period', [
                'pesan' => $th->getMessage(),
            ]);
            Alert::error('Error', 'Failed to save Period!, Please Try Again');

            return back();
        }
    }

    public function destroyPeriode(Request $request)
    {
        // dd($request->all());
        try {
            DB::beginTransaction();

            CrossReviewPeriode::where('Id_Periode', $request->id)->delete();

            DB::commit();
            Alert::success('Succeess', 'Period Has been Deleted!');

            return Redirect::to('/crossReviewPeriode');
        } catch (\Throwable $th) {
            DB::rollback();

            Log::error('Gagal_Delete_Cross_Periode', [
                'pesan' => $th->getMessage(),
            ]);
            Alert::error('Error', 'Failed to delete period!, Please Try Again');

            return back();
        }
    }
}
