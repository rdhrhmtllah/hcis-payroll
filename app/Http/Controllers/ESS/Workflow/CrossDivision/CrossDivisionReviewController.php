<?php

namespace App\Http\Controllers\ESS\Workflow\CrossDivision;

use App\Http\Controllers\Controller;
use App\Models\ESS\Organization\Division\Models\Division;
use App\Models\ESS\Workflow\CrossDivision\Models\CategoryCross;
use App\Models\ESS\Workflow\CrossDivision\Models\CrossReviewDetails;
use App\Models\ESS\Workflow\CrossDivision\Models\CrossReviewPeriode;
use App\Models\ESS\Workflow\CrossDivision\Models\CrossReviews;
use App\Models\ESS\Workflow\Evaluation\Models\EvaluationCross;
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
    /**
     * Kategori beserta evaluasinya, diambil dengan DUA kueri alih-alih 1+N.
     *
     * Bentuk yang sama disusun di tiga tempat pada controller ini, masing-masing
     * dengan satu kueri per kategori. Di sini seluruh evaluasi diambil sekali
     * lalu dikelompokkan di memori.
     *
     * @param  \Illuminate\Support\Collection  $kategori
     * @param  bool  $hash  true -> id di-Hashids dan kuncinya 'Name' (dipakai dua
     *                      layar review); false -> id mentah dan kuncinya 'name'.
     * @return array<int, array<string, mixed>>
     */
    private function kategoriDenganEvaluasi($kategori, bool $hash): array
    {
        $evaluasiPerKategori = EvaluationCross::whereIn(
            'Category_Cross_Id',
            $kategori->pluck('Id_Category_Cross')->all()
        )->get()->groupBy('Category_Cross_Id');

        $hasil = [];

        foreach ($kategori as $item) {
            $sub = [];

            foreach ($evaluasiPerKategori[$item->Id_Category_Cross] ?? [] as $itemEval) {
                $sub[] = $hash
                    ? [
                        'id' => Hashids::connection('custom')->encode($itemEval->Id_Evaluations_Cross),
                        'Name' => $itemEval->Name,
                    ]
                    : [
                        'id' => $itemEval->Id_Evaluations_Cross,
                        'name' => $itemEval->Name,
                    ];
            }

            $hasil[] = ['category' => $item->Name, 'sub_category' => $sub];
        }

        return $hasil;
    }

    public function index()
    {
        $title = 'Cross Division Review Baru';
        $division = Division::where('Flag_Active', 'Y')->get();
        $out = [];
        foreach ($division as $divisi) {
            $temp = [];
            $temp['Id_Division'] = Hashids::connection('custom')->encode($divisi->Id_Division);
            $temp['Name'] = $divisi->Name;

            $out[] = $temp;
        }

        return inertia('ESS/Workflow/CrossDivision/CrossDivisionReview', [
            'title' => $title,
            'division' => $out,
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'ratings' => 'required|array',
        ]);

        // dd($request->all());
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
                'Keterangan ' => $request->input('Keterangan'),
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
            $dataPeriode_temp = CrossReviewPeriode::select('Id_Periode', 'Kode_Periode', 'Keterangan')
                ->where('Division_Id', $id)
                ->whereNull('Flag_Sudah_Review')
                /* Rentang setengah-terbuka menggantikan MONTH()/YEAR() pada
                   kolomnya, yang mematikan pemakaian indeks. */
                ->where('Tanggal_Awal', '>=', now()->startOfMonth())
                ->where('Tanggal_Awal', '<', now()->startOfMonth()->addMonth())
                ->where('Tanggal_Akhir', '>=', now()->startOfMonth())
                ->where('Tanggal_Akhir', '<', now()->startOfMonth()->addMonth())
                ->orderBy('Kode_Periode')
                ->get();
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

    public function getUser($divisi_temp, $periode_temp)
    {
        // dd($periode_temp, $divisi_temp);

        try {
            $user = Auth::user();
            $angka_Periode = Hashids::connection('custom')->decode($periode_temp)[0];
            $periodeChoose = CrossReviewPeriode::where('Id_Periode', $angka_Periode)->get();
            $angka_divisi = Hashids::connection('custom')->decode($divisi_temp)[0];

            //  dd($periodeChoose);
            $category = CategoryCross::where('Flag_Active', 'Y')->get();
            $query = "
                 SELECT
                     a.Id_Users,
                     a.Nama,
                     ISNULL((
                         SELECT 'Y'
                         FROM KPI_Cross_Review x
                         JOIN KPI_Cross_Review_Detail y ON x.No_Review = y.No_Review
                         WHERE
                             x.Status IS NULL
                             AND y.Employee_Id = a.Id_Users
                             AND x.Id_Periode = :Periode_Id
                         GROUP BY y.Employee_Id
                     ), NULL) AS Sudah_Review
                 FROM KPI_Users a
                 WHERE
                     a.Division_Id = :Division_Id
                     AND a.Id_Users <> :User_Id
             ";

            $dataTicket = DB::select($query, [
                'Division_Id' => $periodeChoose[0]->Division_Id,
                'Periode_Id' => $angka_Periode,
                'User_Id' => $user->Id_Users,
            ]);

            // dd($dataTicket);
            $data = $this->kategoriDenganEvaluasi($category, true);

            if (empty($dataTicket) || empty($data)) {

                return response()->json([
                    'Error' => 'Terjadi Kesalahan1',
                ], 422);
            }

            $userTicket = [];
            foreach ($dataTicket as $user) {
                $temp = [];
                $temp['id_user'] = Hashids::connection('custom')->encode($user->Id_Users);
                $temp['Nama'] = $user->Nama;

                $userTicket[] = $temp;
            }

            return response()->json([
                'user' => $userTicket,
                'data' => $data,
            ]);

        } catch (\Throwable $th) {
            Log::channel('review_log')->info('review_log', [
                'error' => $th->getMessage(),
            ]);

            return response()->json([
                'error' => 'Terjadi Kesalahan 2'.$th->getMessage(),
            ]);
        }
    }

    public function getReviews($PeriodeID_temp, $userID_temp)
    {
        try {

            $user = Hashids::connection('custom')->decode($userID_temp)[0];
            $angka_Periode = Hashids::connection('custom')->decode($PeriodeID_temp)[0];
            $periodeChoose = CrossReviewPeriode::where('Id_Periode', $angka_Periode)->get();
            // dd($user);
            $category = CategoryCross::where('Flag_Active', 'Y')->get();

            $query = '
            SELECT a.Id_Users AS id_user, a.Nama
            FROM KPI_Users a
            JOIN KPI_Divisions b ON a.Division_Id = b.Id_Division
            JOIN KPI_Master_Cross_Periode c ON c.Division_Id = b.Id_Division
            WHERE b.Id_Division = :division_id
            AND c.Id_Periode = :periode_id
            and a.Id_Users = :id_users
            GROUP BY a.Id_Users, a.Nama
        ';
            $dataTicket = DB::select($query, [
                'division_id' => $periodeChoose[0]->Division_Id,
                'periode_id' => $angka_Periode,
                'id_users' => $user,
            ]);

            // dd($dataTicket);
            $data = $this->kategoriDenganEvaluasi($category, true);

            if (empty($dataTicket) || empty($data)) {

                return response()->json([
                    'Error' => 'Terjadi Kesalahan1',
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
                'error' => 'Terjadi Kesalahan 2'.$th->getMessage(),
            ]);
        }
    }

    public function history()
    {
        $user = Auth::user()->Id_Users;
        $title = 'History Cross Review';
        $cross = CrossReviews::where('Status', null)->get();
        $query = '
                with cte as(SELECT a.No_Review,a.User_Id_Review as Id_User , d.Employee_Id, b.Nama, a.Time, b.Id_Users, a.Date, c.Name as division
                FROM KPI_Cross_Review a
                JOIN KPI_Cross_Review_Detail d ON a.No_Review = d.No_Review
                JOIN KPI_Users b ON d.Employee_Id = b.Id_Users JOIN KPI_Divisions c on b.Division_Id = c.Id_Division

                )select No_Review, Nama, Time, Id_Users, Date, division from cte  where Employee_Id = :Id_User group by No_Review,Nama, Time, Id_Users, Date, division

                    ';
        $Data_User = DB::select($query, [
            'Id_User' => $user,
        ]);

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

    public function summary()
    {

        $users = Auth::user();

        if (! $users) {
            abort(404);
        }

        if ($users->Username !== 'Reza') {
            abort(404);
        }

        $title = 'Summary Cross Review';
        $user = Auth::user();
        $query = '

        SELECT
            f.Id_Periode AS Id,
            ROUND(SUM(b.Score) / COUNT(b.Score), 2) AS Score,
            d.Id_Division,
            d.Name as Division,
            f.Kode_Periode as periode,
            f.Tanggal_Awal,
            f.Tanggal_Akhir
        FROM
            KPI_Cross_Review_Detail b
            INNER JOIN KPI_Cross_Review c ON b.No_Review = c.No_Review
            INNER JOIN KPI_Divisions d ON c.Division_Id = d.Id_Division
            INNER JOIN KPI_Master_Cross_Periode f ON c.Id_Periode = f.Id_Periode
        GROUP BY
            f.Id_Periode,
            d.Id_Division,
            d.Name,
            f.Tanggal_Awal,
            f.Kode_Periode,
            f.Tanggal_Akhir
        ';
        $data = DB::select($query);
        // dd($data);
        $dataEncrip = [];
        foreach ($data as $item) {
            $temp = [];
            $temp['Id'] = Hashids::connection('custom')->encode($item->Id);
            $temp['Id_Division'] = Hashids::connection('custom')->encode($item->Id_Division);
            $temp['Division'] = $item->Division;
            $temp['periode'] = $item->periode;
            $temp['Score'] = $item->Score;
            $temp['Tanggal_Awal'] = $item->Tanggal_Awal;
            $temp['Tanggal_Akhir'] = $item->Tanggal_Akhir;
            $dataEncrip[] = $temp;
        }

        // dd($dataEncrip);
        return view('cross.summary', ['title' => $title, 'data' => $dataEncrip]);
    }

    public function summaryReview($Id_Periode, $Id_Division)
    {
        // $no_reviewReal = str_replace('@', '/', $no_review);
        $title = 'Summary Review';
        $Periode_Id = Hashids::connection('custom')->decode($Id_Periode)[0];
        $Division_Id = Hashids::connection('custom')->decode($Id_Division)[0];
        $AllCategoryCross = CategoryCross::where('Flag_Active', 'Y')->get();
        /*
         * Blok ini dulu memanggil EvaluationCross::where(...) TANPA ->get(),
         * lalu mem-foreach hasilnya. Yang di-foreach adalah objek Builder, dan
         * PHP hanya menelusuri properti publik sebuah objek -- Builder tidak
         * punya, jadi badan perulangannya tidak pernah berjalan dan
         * sub_category selalu kosong. Nama kolomnya pun keliru:
         * Id_Evaluation_Cross, sedangkan primary key modelnya
         * Id_Evaluations_Cross.
         *
         * Tidak ada dampak yang terlihat pengguna hari ini: satu-satunya
         * pemakai data ini di CrossSummary.vue adalah updateCompletion(), yang
         * tidak pernah dipanggil dari mana pun. Diperbaiki karena ia jebakan --
         * begitu pemakainya disambungkan, daftarnya diam-diam kosong.
         */
        $dataAllCategory = $this->kategoriDenganEvaluasi($AllCategoryCross, false);
        $queryCategory = '
        select a.Name, a.Id_Category_Cross from KPI_Category_Cross a, KPI_Cross_Review_Detail c, KPI_Evaluations_Cross b
        where a.Id_Category_Cross = b.Category_Cross_Id and c.Evaluation_Id = b.Id_Evaluations_Cross
        group by  a.Name, a.Id_Category_Cross';
        $categoryChoose = DB::select($queryCategory);
        // dd($categoryChoose);
        $queryPeriode = 'Select a.Keterangan as judulPeriode, c.Name as division, a.Tanggal_Awal, a.Tanggal_Akhir from KPI_Master_Cross_Periode a, KPI_Cross_Review b
                        ,KPI_Divisions c Where a.Id_Periode = b.Id_Periode and a.Division_Id = c.Id_Division and c.Id_Division =:Id_Division group by
                         a.Keterangan, c.Name, a.Tanggal_Awal, a.Tanggal_Akhir ';
        $dataPeriode = DB::select($queryPeriode, ['Id_Division' => $Division_Id]);
        // dd($dataPeriode);
        $dataSummary = [];
        foreach ($categoryChoose as $item) {
            $temp = [];
            $temp['category'] = $item->Name;
            $query = '
            WITH cte AS (
                SELECT
                    b.Employee_Id,
                    c.nama AS Name,
                    d.Id_Periode,
                    f.Id_Division,
                    ROUND(SUM(Score) / COUNT(Score), 2) AS AVG
                FROM
                    KPI_Cross_Review a,
                    KPI_Cross_Review_Detail b,
                    KPI_Users c,
                    KPI_Master_Cross_Periode d,
                    KPI_Evaluations_Cross e,
                    KPI_Divisions f
                WHERE
                    a.No_Review = b.No_Review
                    AND a.status IS NULL
                    AND b.Employee_Id = c.Id_Users
                    AND a.Id_Periode = d.Id_Periode
                    AND b.Evaluation_Id = e.Id_Evaluations_Cross
                    AND d.Division_Id = f.Id_Division
                    AND e.Category_Cross_Id = :Category_Id
                    AND d.Id_Periode = :Id_Periode
                    AND f.Id_Division = :Id_Division
                GROUP BY
                    b.Employee_Id,
                    c.nama,
                    d.Id_Periode,
                    f.Id_Division
            )
            SELECT
                Name, AVG, Employee_Id, Id_Periode, Id_Division
            FROM
                cte
        ';

            $dataAVG = DB::select($query, [
                'Id_Division' => $Division_Id,
                'Id_Periode' => $Periode_Id,
                'Category_Id' => $item->Id_Category_Cross,
            ]);

            $temp['sub_category'] = [];

            foreach ($dataAVG as $itemAVG) {
                array_push($temp['sub_category'], [
                    'User_Id' => Hashids::connection('custom')->encode($itemAVG->Employee_Id),
                    'Name' => $itemAVG->Name,
                    'AVGScore' => number_format($itemAVG->AVG, 2, '.'),
                ]);
            }

            $dataSummary[] = $temp;
        }

        // dd($dataSummary);
        return inertia('ESS/Workflow/CrossDivision/CrossSummary', ['data' => $dataAllCategory, 'title' => $title, 'dataSummary' => $dataSummary, 'dataPeriode' => $dataPeriode, 'tanggal' => date('d M Y', strtotime($dataPeriode[0]->Tanggal_Awal)).' - '.date('d M Y', strtotime($dataPeriode[0]->Tanggal_Akhir))]);
    }

    public function feedback()
    {
        $user = Auth::user()->Id_Users;
        $title = 'History Cross Review';
        $cross = CrossReviews::where('Status', null)->get();
        $query = '
        SELECT
            a.No_Review,
            ROUND(AVG(CAST(d.Score AS FLOAT)), 2) AS score,
            a.Time,
            a.Keterangan,
            a.Date,
            c.Name AS division
        FROM KPI_Cross_Review a
        JOIN KPI_Cross_Review_Detail d ON a.No_Review = d.No_Review
        JOIN KPI_Users b ON d.Employee_Id = b.Id_Users
        JOIN KPI_Divisions c ON b.Division_Id = c.Id_Division
        WHERE d.Employee_Id = :Id_User
        GROUP BY
            a.No_Review,
            a.Time,
            a.Keterangan,
            a.Date,
            c.Name';
        $Data_User = DB::select($query, [
            'Id_User' => $user,
        ]);
        // dd($Data_User);

        return view('cross.feedback', ['title' => $title, 'data' => $Data_User]);
    }

    public function feedbackDetail($no_review_temp)
    {
        $no_review_real = str_replace('@', '/', $no_review_temp);
        $title = $no_review_real;
        $user = Auth::user();
        /*
         * Penjaganya dulu hanya menanyakan "apakah review ini ada", bukan
         * "apakah review ini milik yang membuka". Selama rutenya tak terdaftar
         * itu tak pernah terasa; begitu dipasang kembali, siapa pun yang login
         * bisa menebak No_Review orang lain dan lolos. Kedua kueri di bawah
         * memang menyaring per Id_Users, jadi yang didapat bukan data orang
         * lain -- melainkan nol baris, lalu $AllScore[0] melempar. Menyaring di
         * penjaganya menutup keduanya sekaligus.
         */
        $dataEmployee = CrossReviewDetails::where('No_Review', $no_review_real)
            ->where('Employee_Id', $user->Id_Users)
            ->exists();
        if (! $dataEmployee) {
            abort(403);
        } else {
            $querySummary = '
                   SELECT
            a.Keterangan,
            e.Kode_Periode,
            ROUND(AVG(CAST(d.Score AS FLOAT)), 2) AS score

        FROM KPI_Cross_Review a
        JOIN KPI_Cross_Review_Detail d ON a.No_Review = d.No_Review
        JOIN KPI_Users b ON d.Employee_Id = b.Id_Users
        JOIN KPI_Master_Cross_Periode e ON a.Id_Periode = e.Id_Periode
        JOIN KPI_Divisions c ON b.Division_Id = c.Id_Division
        WHERE d.Employee_Id = :Id_User and a.No_Review =:no_review
        group by a.Keterangan,e.Kode_Periode
            ';
            $AllScore = DB::select($querySummary, [
                'Id_User' => $user->Id_Users,
                'no_review' => $no_review_real,
            ]);
            $query = '
            select a.Id_Category_Cross, a.Name, ROUND(sum(c.Score)/count(c.score),2) as score from KPI_Category_Cross a
            join KPI_Evaluations_Cross b on a.Id_Category_Cross = b.Category_Cross_Id
            join KPI_Cross_Review_Detail c on b.Id_Evaluations_Cross = c.Evaluation_Id
            join KPI_Cross_Review d on c.No_Review = d.No_Review
            where c.Employee_Id =:employee and d.No_Review =:no_review
            group by a.Id_Category_Cross, a.Name
            order by a.Id_Category_Cross asc
            ';
            $dataScoreCategory = DB::select($query, ['employee' => $user->Id_Users, 'no_review' => $no_review_real]);
            // dd($AllScore);

            /* Penjaga di atas menjamin ada baris detail, tetapi ringkasan ini
               ikut menjoin periode dan divisi -- satu master yang hilang membuat
               hasilnya kosong dan $AllScore[0] melempar Error, bukan Exception. */
            if ($AllScore === []) {
                abort(404);
            }

            return inertia('ESS/Workflow/CrossDivision/feedbackDetail', ['title' => $title, 'dataScore' => $dataScoreCategory, 'allscore' => $AllScore[0]]);
        }
    }
}
