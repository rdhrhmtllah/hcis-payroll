<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RealRashid\SweetAlert\Facades\Alert;

class uDashController extends Controller
{
    public function index()
    {
        $Kode_KaryawanReal = Auth::user()->karyawan->Kode_Karyawan ?? null;
        try {
            $batasHariPengajuan = config('services.hcis.batas_approve_izin');
            $batasApproveCuti = config('services.hcis.batas_approve_cuti');
            $title = 'uDash for You';
            $now = Carbon::now();
            $cutoffDateThisMonth = $now->copy()->day(20);

            if ($now->gt($cutoffDateThisMonth)) {
                // Jika hari ini sudah lewat tanggal 20 bulan ini
                $startWeek = $cutoffDateThisMonth->copy()->addDay(); // Tanggal 21 bulan ini
                $endWeek = $cutoffDateThisMonth->copy()->addMonth(); // Tanggal 20 bulan depan
            } else {
                // Jika hari ini belum lewat tanggal 20 bulan ini
                $endWeek = $cutoffDateThisMonth->copy(); // Tanggal 20 bulan ini
                $startWeek = $cutoffDateThisMonth->copy()->subMonth()->addDay(); // Tanggal 21 bulan lalu
            }

            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
            $UserID_Web = Auth::user()->karyawan->UserID_Web;
            $Id_Division = Auth::user()->karyawan->division->ID_Divisi;
            $Id_Level = Auth::user()->karyawan->level->ID_Level;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            // dd($Id_Level);

            // Tentukan rentang tanggal
            // Ambil data absensi utama dari stored procedure
            $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
            $absen_data = collect(DB::select($query));
            // dd($absen_data);
            // Ambil semua tanggal unik dari data absen untuk dijadikan filter
            $tanggal_absens = $absen_data->pluck('Tanggal')->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'));
            $CHECKIN = $absen_data->pluck('CheckIn')->map(fn ($date) => Carbon::parse($date)->format('Y-m-d h:i:s'));

            $pulang_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal')
                ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('b.Kode_Karyawan', $Kode_Karyawan)
                ->where('b.Jenis', 'pulangCepat')
                ->where('b.Flag_Approval', 'Y')
                ->where('a.Status', null)
                ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
                ->get();

            $terlambat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal', 'b.Jam')
                ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('b.Kode_Karyawan', $Kode_Karyawan)
                ->where('b.Flag_Approval', 'Y')
                ->where('b.Jenis', 'terlambat')
                ->where('a.Status', null)
                ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
                ->get();

            $tanggal_libur = DB::table('HRIS_Hari_Libur')
                ->whereBetween('Tanggal', [$startWeek, $endWeek])
                ->pluck('Tanggal')
                ->map(fn ($e) => Carbon::parse($e)->format('Y-m-d'));
            // dd($tanggal_libur);
            $total_keterlambatan_menit = 0;

            foreach ($absen_data as $item) {
                // Hari Libur
                if ($tanggal_libur->contains(Carbon::parse($item->Tanggal)->toDateString())) {
                    continue;
                }

                // Tidak checkin
                if (is_null($item->CheckIn)) {
                    continue;
                }

                $check_in_carbon = Carbon::parse($item->CheckIn);

                $pengajuan_terlambat = $terlambat_data->firstWhere('Tanggal', $item->Tanggal);

                $keterlambatan_hari_ini = 0; // simpan keterlambatan per hari

                if ($pengajuan_terlambat && ! empty($pengajuan_terlambat?->Jam)) {
                    $format_jam = strlen($pengajuan_terlambat->Jam) === 5 ? 'H:i' : 'H:i:s';
                    $jam_toleransi_carbon = Carbon::createFromFormat(
                        'Y-m-d '.$format_jam,
                        $check_in_carbon->format('Y-m-d').' '.$pengajuan_terlambat->Jam,
                    );

                    if ($check_in_carbon > $jam_toleransi_carbon) {
                        $check_in_no_seconds = $check_in_carbon->copy()->seconds(0);
                        $jam_toleransi_no_seconds = $jam_toleransi_carbon->copy()->seconds(0);
                        $keterlambatan_hari_ini = $check_in_no_seconds->diffInMinutes($jam_toleransi_no_seconds);
                    }
                } else {
                    $jam_masuk_value = $item->Jam_Masuk ?? '00:00';
                    $format_jam = strlen($jam_masuk_value) === 5 ? 'H:i' : 'H:i:s';
                    $jam_masuk_carbon = Carbon::createFromFormat(
                        'Y-m-d '.$format_jam,
                        $check_in_carbon->format('Y-m-d').' '.$jam_masuk_value,
                    );

                    if ($check_in_carbon > $jam_masuk_carbon) {
                        $check_in_no_seconds = $check_in_carbon->copy()->seconds(0);
                        $jam_masuk_no_seconds = $jam_masuk_carbon->copy()->seconds(0);

                        $keterlambatan_hari_ini = $check_in_no_seconds->diffInMinutes($jam_masuk_no_seconds);
                    }
                }

                // batasi max 50 menit per hari
                $total_keterlambatan_menit += min($keterlambatan_hari_ini, 50);
            }

            // dd($total_keterlambatan_menit);
            // Konversi ke menit
            // $total_keterlambatan_menit = round($total_keterlambatan_detik / 60, 2);

            // --- Pre-load Semua Data Transaksi dalam Beberapa Query ---

            // 1. Pre-load data lembur
            $lembur_data = DB::table('Transaksi_Lembur_Detail as b')
                ->select('a.No_Transaksi', 'b.Tanggal_Lembur_dari as mulai', 'b.Tanggal_Lembur_Sampai as selesai')
                ->join('Transaksi_Lembur as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('b.Kode_Karyawan', $Kode_Karyawan)
                ->where(function ($query) use ($tanggal_absens) {
                    $query
                        ->whereIn(DB::raw('CAST(b.Tanggal_Lembur_dari AS DATE)'), $tanggal_absens)
                        ->orWhereIn(DB::raw('CAST(b.Tanggal_Lembur_Sampai AS DATE)'), $tanggal_absens);
                })
                ->get();
            // dd($lembur_data);

            $tanggal_lembur_valid = $lembur_data
                ->pluck('mulai')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->toArray();

            // --- Langkah 2: Filter data absen berdasarkan tanggal 'CheckIn' atau 'Tanggal_Absen' ---
            $filtered_data_lembur = $absen_data->filter(function ($absen_item) use ($tanggal_lembur_valid) {
                // Ganti 'CheckIn' dengan nama kolom yang benar-benar berisi tanggal absen
                if (is_null($absen_item->CheckIn)) {
                    return false;
                }

                $tanggal_absensi = Carbon::parse($absen_item->CheckIn)->toDateString();

                return in_array($tanggal_absensi, $tanggal_lembur_valid);
            });

            // dd($filtered_data_lembur);

            // --- Langkah 3: Jumlahkan total menit lembur dari data yang sudah difilter ---
            $total_lembur_menit = $filtered_data_lembur->sum('Overtime_Real');

            // Konversi ke jam desimal
            // $total_lembur_jam = floor($total_lembur_menit / 60);
            $total_lembur_jam = $filtered_data_lembur
                ->map(function ($item) {
                    // Ubah menit ke jam desimal lalu floor per transaksi
                    $jam = $item->Overtime_Real / 60;

                    return floor($jam);
                })
                ->sum();
            // dd($filtered_data_lembur);
            $today = Carbon::today()->toDateString();
            $tomorrow = Carbon::tomorrow()->toDateString();

            $absen_hari_ini = $absen_data->first(function ($item) use ($today) {
                if (is_null($item->Tanggal)) {
                    return false;
                }

                // Bandingkan tanggal dari item dengan tanggal hari ini
                return Carbon::parse($item->Tanggal)->toDateString() === $today;
            });

            $tanggalBesok = Carbon::tomorrow();
            $queryBesok = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$tanggalBesok}', '{$tanggalBesok}', '{$UserID_Absen}', '', ''";
            $absen_besok_raw = DB::Select($queryBesok);
            $absen_besok = collect($absen_besok_raw)->first();
            // dd($absen_besok->Nama_Shift);
            // $absen_besok = $absen_data->first(function ($item) use ($tomorrow) {
            //     if (is_null($item->Tanggal)) {
            //         return false;
            //     }

            //     return Carbon::parse($item->Tanggal)->toDateString() === $tomorrow;
            // });

            $accessPage = DB::table('HRIS_Page_Access')
                ->select('Jenis_page')
                ->where('Flag_Aktif', 'Y')
                ->where('UserID_Web', $UserID_Web)
                ->get();
            // dd($accessPage);
            $sisaCuti = (int) Auth::user()->karyawan->sisa_cuti();
            // dd($sisaCuti);
            $namaKaryawan = Auth::user()->karyawan->Nama;

            $berita = DB::table('HRIS_News_Dashboard')->orderBy('Id_News', 'desc')->first();

            $nama_anggota = DB::table('HRIS_Karyawan_Team as t')
                ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->where('t.Kode_Karyawan_Team', $Kode_KaryawanReal)
                ->select('k.Nama', 'k.HP', 'd.nama_divisi as divisi')
                ->groupBy('k.HP', 'd.nama_divisi ', 'k.Nama')
                ->get();

            $nama_tergabung = DB::table('HRIS_Karyawan_Team as t')
                ->join('Karyawan as k', 't.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
                ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->where('t.Kode_Karyawan', $Kode_KaryawanReal)
                ->select('k.Nama', 'k.HP', 'd.nama_divisi as divisi')
                ->groupBy('k.Nama', 'k.HP', 'd.nama_divisi')
                ->where('t.Kode_Karyawan_Team', '<>', $Kode_KaryawanReal)
                ->where('t.Kode_Karyawan_Team', '<>', 'RIDHO RAHMAT')
                ->get();

            $nama_Approver = DB::table('HRIS_Approval_Flow as t')
                ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->where('t.Kode_Karyawan_Requester', $Kode_KaryawanReal)
                ->select('k.Nama', 't.order_flow')
                ->groupBy('k.Nama', 't.order_flow')
                ->where('t.Kode_Karyawan', '<>', $Kode_KaryawanReal)
                ->get();

            $nama_mengapprove = DB::table('HRIS_Approval_Flow as t')
                ->join('Karyawan as k', 't.Kode_Karyawan_Requester', '=', 'k.Kode_Karyawan')
                ->where('t.Kode_Karyawan', $Kode_KaryawanReal)
                ->select('k.Nama')
                ->groupBy('k.Nama')
                ->where('t.Kode_Karyawan_Requester', '<>', $Kode_KaryawanReal)
                ->get();

            // dd($nama_anggota);

            $LeaderTeam = DB::table('HRIS_Karyawan_Team')->where('Kode_Karyawan_Team', $Kode_KaryawanReal)->exists();
            // if($Kode_KaryawanReal == 'RIDHO RAHMAT' || $Kode_KaryawanReal == 'H45'){
            //     $LeaderTeam = false;
            // }
            // dd($LeaderTeam);
            if ($LeaderTeam) {
                $datasPending = DB::table('HRIS_Approval_Request as a')
                    ->join('HRIS_Approval_Flow as b', 'a.Flow_Id', '=', 'b.id')
                    ->whereNull('a.Flag_Approval')
                    ->whereNull('b.Status')
                    ->where('b.Kode_Karyawan', $Kode_KaryawanReal)

                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('HRIS_Approval_Request as prev')
                            ->join('HRIS_Approval_Flow as prev_flow', 'prev.Flow_Id', '=', 'prev_flow.id')
                            ->whereColumn('prev.No_Transaksi', 'a.No_Transaksi')
                            ->whereRaw('prev_flow.order_flow < b.order_flow')
                            ->where(function ($q) {
                                $q->whereNull('prev.Flag_Approval')
                                    ->whereNull('prev_flow.Status')
                                    ->orWhere('prev.Flag_Approval', '!=', 'Y');
                            });
                    })
                    ->whereIn('a.id', function ($query) {
                        $query
                            ->selectRaw('MIN(t1.id)')
                            ->from('HRIS_Approval_Request as t1')
                            ->join('HRIS_Approval_Flow as t2', 't1.Flow_Id', '=', 't2.id')
                            ->whereNull('t1.Flag_Approval')
                            ->whereNull('t2.Status')
                            ->groupBy('t1.No_Transaksi');
                    })

                    // Cek apakah di 3 tabel transaksi ada yang statusnya 'Y'
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from(
                                DB::raw("
                                (
                                    SELECT No_Transaksi FROM Transaksi_Terlambat_Pulang_Cepat WHERE Status = 'Y'
                                    UNION ALL
                                    SELECT No_Transaksi FROM Transaksi_Sakit_Izin WHERE Status = 'Y'
                                    UNION ALL
                                    SELECT No_Transaksi FROM Transaksi_Cuti WHERE Status = 'Y'
                                ) as t_check
                            "),
                            )
                            ->whereColumn('t_check.No_Transaksi', 'a.No_Transaksi');
                    })
                    ->whereNotExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from(
                                DB::raw("
                                (
                                    SELECT No_Transaksi FROM Transaksi_Terlambat_Pulang_Cepat_Detail b WHERE b.Flag_Approval IN ('Y','P') 
                                    UNION ALL
                                    SELECT No_Transaksi FROM Transaksi_Sakit_Izin_Detail b WHERE b.Flag_Approval IN ('Y','P') 
                                    UNION ALL
                                    SELECT No_Transaksi FROM Transaksi_Cuti_Detail b WHERE b.Flag_Approval IN ('Y','P') 
                                ) as t_check
                            "),
                            )
                            ->whereColumn('t_check.No_Transaksi', 'a.No_Transaksi');
                        //     ->whereRaw("
                        //     DATEDIFF(
                        //         DAY,
                        //         (
                        //             SELECT TOP 1
                        //                 CAST(t.Tanggal AS DATETIME) + CAST(t.Jam AS DATETIME)
                        //             FROM (
                        //                 SELECT No_Transaksi, Tanggal, Jam
                        //                 FROM Transaksi_Terlambat_Pulang_Cepat

                        //                 UNION ALL

                        //                 SELECT No_Transaksi, Tanggal, Jam
                        //                 FROM Transaksi_Sakit_Izin
                        //             ) t
                        //             WHERE t.No_Transaksi = a.No_Transaksi
                        //         ),
                        //         GETDATE()
                        //     ) < ?
                        // ", [$batasHariPengajuan]);
                    })
                    ->whereNotExists(function ($sub) use ($batasHariPengajuan) {
                        $sub->select(DB::raw(1))
                            ->from(
                                DB::raw('
                                (
                                    SELECT No_Transaksi, Tanggal, Jam FROM Transaksi_Terlambat_Pulang_Cepat
                                    UNION ALL
                                    SELECT No_Transaksi, Tanggal, Jam FROM Transaksi_Sakit_Izin
                                ) as t
                            '),
                            )
                            ->whereColumn('t.No_Transaksi', 'a.No_Transaksi')
                            ->whereRaw(
                                '
                                DATEDIFF(
                                    DAY,
                                    CAST(t.Tanggal AS DATETIME) + CAST(t.Jam AS DATETIME),
                                    GETDATE()
                                ) >= ?
                            ',
                                [$batasHariPengajuan],
                            );
                    })

                    ->select('a.*', 'b.order_flow')
                    ->orderBy('a.No_Transaksi')
                    ->count();
                // dd($datasPending);
            } else {
            }

            // dd($Id_Division, $Id_Level, $UserID_Web);

            return inertia('uDash', [
                'title' => $title,
                'LeaderTeam' => $LeaderTeam,
                'accessPage' => $accessPage ?? [],
                'namaKaryawan' => $namaKaryawan,
                'shiftHariIni' => $absen_hari_ini->Nama_Shift ?? 'NO SHIFT',
                'Jam_Masuk' => $absen_hari_ini->Jam_Masuk ?? '00:00',
                'Jam_Keluar' => $absen_hari_ini->Jam_Keluar ?? '00:00',
                'shiftBesok' => $absen_besok->Nama_Shift ?? 'NO SHIFT',
                'totalTerlambat' => $total_keterlambatan_menit ?? 0,
                'totalLembur' => $total_lembur_jam ?? 0,
                'sisaCuti' => $sisaCuti ?? 0,
                'judulBerita' => $berita->Judul,
                'isiBerita' => $berita->Content,
                'nama_anggota' => $nama_anggota,
                'nama_tergabung' => $nama_tergabung,
                'nama_Approver' => $nama_Approver,
                'nama_mengapprove' => $nama_mengapprove,
                'jumlahAntrianPengajuan' => $datasPending ?? 0,
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error(
                'Gagal menampilkan page Dashboard: '.$e->getMessage().'Karyawan : '.$Kode_KaryawanReal,
            );
            Alert::error('error', 'Terjadi Error saat menampilkan page Dashboard!');

            return abort(500);
        }
    }

    public function getRingkasanTeam(Request $request)
    {
        // dd($request);
        try {
            $now = Carbon::now();
            $cutoffDateThisMonth = $now->copy()->day(20);

            if ($now->gt($cutoffDateThisMonth)) {
                // Jika hari ini sudah lewat tanggal 20 bulan ini
                $startWeek = $cutoffDateThisMonth->copy()->addDay(); // Tanggal 21 bulan ini
                $endWeek = $cutoffDateThisMonth->copy()->addMonth(); // Tanggal 20 bulan depan
            } else {
                // Jika hari ini belum lewat tanggal 20 bulan ini
                $endWeek = $cutoffDateThisMonth->copy(); // Tanggal 20 bulan ini
                $startWeek = $cutoffDateThisMonth->copy()->subMonth()->addDay(); // Tanggal 21 bulan lalu
            }

            $Kode_KaryawanReal = Auth::user()->karyawan->Kode_Karyawan ?? null;

            $nama_anggota = DB::table('HRIS_Karyawan_Team as t')
                ->join('Karyawan as k', 't.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->join('View_Divisi_Sub_Divisi as d', 'k.ID_Divisi_Sub_Divisi', '=', 'd.ID_DIVISI_SUB_DIVISI')
                ->where('t.Kode_Karyawan_Team', $Kode_KaryawanReal)
                ->where('t.Kode_Karyawan', '<>', $Kode_KaryawanReal)
                ->select('k.UserID_Absen', 'k.Kode_Karyawan')
                ->groupBy('k.UserID_Absen', 'k.Kode_Karyawan')
                ->get();

            $kode_anggota = $nama_anggota->pluck('Kode_Karyawan')->toArray();
            $IDAbsen_anggota = $nama_anggota->pluck('UserID_Absen')->toArray();
            // dd($IDAbsen_anggota);

            // $userIds = array_column($users, 'UserID_Absen');
            $userIds = $IDAbsen_anggota;
            $userIdPlaceholders = implode(',', array_fill(0, count($userIds), '?'));
            // $datePlaceholders = implode(',', array_fill(0, count($dateRange), '?'));
            $userQuery = implode(',', $userIds);
            // dd($startWeek, $endWeek, $userQuery);
            $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001','$startWeek','$endWeek','$userQuery', '', ''";
            $resultData = DB::select($query);

            $daftar_anggota = collect($resultData)
                ->pluck('Nama') // ambil semua kolom Tanggal

                ->unique() // buang duplikat
                ->values(); // reset key biar rapi

            // dd($daftar_anggota);

            $tanggal_absens = collect($resultData)
                ->pluck('Tanggal') // ambil semua kolom Tanggal
                ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d')) // format ke Y-m-d
                ->unique() // buang duplikat
                ->values(); // reset key biar rapi

            // dd($tanggal_absens);

            // pre-load data terlambat
            $pulang_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal', 'b.Kode_Karyawan')
                ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('b.Jenis', 'pulangCepat')
                ->where('b.Flag_Approval', 'Y')
                ->where('a.Status', null)
                ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
                ->get();

            $terlambat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
                ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal', 'b.Jam', 'b.Kode_Karyawan')
                ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('b.Flag_Approval', 'Y')
                ->where('b.Jenis', 'terlambat')
                ->whereNull('a.Status') // ✅ perbaikan
                ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens) // pastikan array
                ->get();
            $lembur_data = DB::table('Transaksi_Lembur_Detail as b')
                ->select(
                    'b.Kode_Karyawan',
                    'a.No_Transaksi',
                    'b.Tanggal_Lembur_dari as mulai',
                    'b.Tanggal_Lembur_Sampai as selesai',
                )
                ->join('Transaksi_Lembur as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where(function ($query) use ($tanggal_absens) {
                    $query
                        ->whereIn(DB::raw('CAST(b.Tanggal_Lembur_dari AS DATE)'), $tanggal_absens)
                        ->orWhereIn(DB::raw('CAST(b.Tanggal_Lembur_Sampai AS DATE)'), $tanggal_absens);
                })
                ->get();

            // --- Ambil data sakit per karyawan
            $sakit_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
                ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('Jenis', 'sakit')
                ->where('b.Flag_Approval', 'Y')
                ->where(function ($query) use ($tanggal_absens) {
                    $query
                        ->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
                        ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
                })
                ->select('b.Kode_Karyawan', DB::raw('COUNT(*) as total_sakit'))
                ->groupBy('b.Kode_Karyawan')
                ->pluck('total_sakit', 'b.Kode_Karyawan'); // hasil: [kode_karyawan => total]

            // --- Ambil data izin per karyawan
            $izin_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
                ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('Jenis', 'izinFull')
                ->where('b.Flag_Approval', 'Y')
                ->where(function ($query) use ($tanggal_absens) {
                    $query
                        ->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
                        ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
                })
                ->select('b.Kode_Karyawan', DB::raw('COUNT(*) as total_izin'))
                ->groupBy('b.Kode_Karyawan')
                ->pluck('total_izin', 'b.Kode_Karyawan');

            // --- Ambil data cuti per karyawan
            $cuti_data = DB::table('Transaksi_Cuti_Detail as b')
                ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->join('Transaksi_Cuti_Det as c', 'a.No_Transaksi', '=', 'c.No_Transaksi')
                ->whereIn('b.Kode_Karyawan', $kode_anggota)
                ->where('b.Flag_Approval', 'Y')
                // ->where(function($query) use ($tanggal_absens) {
                //     $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
                //         ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);
                // })
                ->where(function ($query) use ($tanggal_absens) {
                    $query->whereIn(DB::raw('CAST(c.Tanggal_Cuti AS DATE)'), $tanggal_absens);
                })
                ->whereNull('a.status')
                ->select('b.Kode_Karyawan', DB::raw('COUNT(*) as total_cuti'))
                ->groupBy('b.Kode_Karyawan')
                ->pluck('total_cuti', 'b.Kode_Karyawan');

            // dd($cuti_data);
            $tanggal_lembur_valid = $lembur_data
                ->pluck('mulai')
                ->map(fn ($date) => Carbon::parse($date)->toDateString())
                ->toArray();

            $groupedByKaryawan = collect($resultData)->groupBy('Kode_Karyawan');

            // sebelum membuat $rekap — tentukan batas perhitungan (pilih yg lebih awal: endWeek atau sekarang)
            $endDateForCalculation = $endWeek->lte(Carbon::now())
                ? $endWeek->copy()->endOfDay()
                : Carbon::now()->endOfDay();

            // daftar shift yang harus dikecualikan (normalisasi uppercase & trim)
            $excludedShifts = ['LIBUR', 'NO SHIFT', 'HARI MINGGU'];

            // kemudian saat membuat $rekap, tambahkan use $endDateForCalculation
            // $today = Carbon::today();
            $today = Carbon::now();
            $hariLibur = DB::table('HRIS_Hari_Libur')
                ->whereBetween('Tanggal', [$startWeek, $endWeek])
                ->count();
            // dd($today);
            $rekap = collect($resultData)
                ->groupBy('Kode_Karyawan')
                ->map(function ($records, $kodeKaryawan) use (
                    $terlambat_data,
                    $pulang_data,
                    $lembur_data,
                    $sakit_data,
                    $izin_data,
                    $cuti_data,
                    $endDateForCalculation,
                    $excludedShifts,
                    $hariLibur,
                    $today,
                ) {
                    $total_keterlambatan_menit = 0;
                    $total_pulang_cepat_menit = 0;
                    $total_lembur_menit = 0;
                    $total_lembur_jam = 0;

                    foreach ($records as $record) {
                        $tanggal = Carbon::parse($record->Tanggal)->toDateString();

                        if (is_null($record->CheckIn)) {
                            $sakit = $sakit_data[$kodeKaryawan] ?? 0;
                            $izin = $izin_data[$kodeKaryawan] ?? 0;
                            $cuti = $cuti_data[$kodeKaryawan] ?? 0;

                            if ($sakit > 0 || $izin > 0 || $cuti > 0) {
                                continue;
                            }

                            continue; // dianggap alpha
                        }

                        $check_in_carbon = Carbon::parse($record->CheckIn);

                        // 1. keterlambatan
                        // $pengajuan_terlambat = collect($terlambat_data)->firstWhere('Tanggal', $record->Tanggal);

                        // if ($pengajuan_terlambat && !empty($pengajuan_terlambat?->Jam)) {
                        //     $format_jam = strlen($pengajuan_terlambat->Jam) === 5 ? 'H:i' : 'H:i:s';
                        //     $jam_toleransi_carbon = Carbon::createFromFormat(
                        //         'Y-m-d ' . $format_jam,
                        //         $check_in_carbon->format('Y-m-d') . ' ' . $pengajuan_terlambat->Jam
                        //     );

                        //     if ($check_in_carbon > $jam_toleransi_carbon) {
                        //         $total_keterlambatan_menit += $check_in_carbon->copy()->seconds(0)
                        //             ->diffInMinutes($jam_toleransi_carbon->copy()->seconds(0));
                        //     }
                        // } else {
                        //     $jam_masuk_value = $record->Jam_Masuk ?? "00:00";
                        //     $format_jam = strlen($jam_masuk_value) === 5 ? 'H:i' : 'H:i:s';
                        //     $jam_masuk_carbon = Carbon::createFromFormat(
                        //         'Y-m-d ' . $format_jam,
                        //         $check_in_carbon->format('Y-m-d') . ' ' . $jam_masuk_value
                        //     );

                        //     if ($check_in_carbon > $jam_masuk_carbon) {
                        //         $total_keterlambatan_menit += $check_in_carbon->copy()->seconds(0)
                        //             ->diffInMinutes($jam_masuk_carbon->copy()->seconds(0));
                        //     }
                        // }

                        $pengajuan_terlambat = collect($terlambat_data)
                            ->where('Kode_Karyawan', $record->Kode_Karyawan)
                            ->firstWhere('Tanggal', $record->Tanggal);
                        // dd($pengajuan_terlambat);
                        // dd($records);
                        $keterlambatan_hari_ini = 0; // simpan sementara per hari
                        $test = [];

                        if ($pengajuan_terlambat && ! empty($pengajuan_terlambat?->Jam)) {
                            $format_jam = strlen($pengajuan_terlambat->Jam) === 5 ? 'H:i' : 'H:i:s';
                            $jam_toleransi_carbon = Carbon::createFromFormat(
                                'Y-m-d '.$format_jam,
                                $check_in_carbon->format('Y-m-d').' '.$pengajuan_terlambat->Jam,
                            );

                            // $test[] = $check_in_carbon .','. $jam_toleransi_carbon. ',' . $pengajuan_terlambat->Kode_Karyawan. ','. $pengajuan_terlambat->Tanggal . ' '. $pengajuan_terlambat->Jam ;

                            if ($check_in_carbon > $jam_toleransi_carbon) {
                                $keterlambatan_hari_ini = $check_in_carbon
                                    ->copy()
                                    ->seconds(0)
                                    ->diffInMinutes($jam_toleransi_carbon->copy()->seconds(0));
                            }
                        } else {
                            $jam_masuk_value = $record->Jam_Masuk ?? '00:00';
                            $format_jam = strlen($jam_masuk_value) === 5 ? 'H:i' : 'H:i:s';
                            $jam_masuk_carbon = Carbon::createFromFormat(
                                'Y-m-d '.$format_jam,
                                $check_in_carbon->format('Y-m-d').' '.$jam_masuk_value,
                            );

                            if ($check_in_carbon > $jam_masuk_carbon) {
                                $keterlambatan_hari_ini = $check_in_carbon
                                    ->copy()
                                    ->seconds(0)
                                    ->diffInMinutes($jam_masuk_carbon->copy()->seconds(0));
                            }
                        }

                        // batasi max 50 menit per hari
                        $total_keterlambatan_menit += min($keterlambatan_hari_ini, 50);
                        // dd($test);

                        // 2. pulang cepat
                        if (! is_null($record->CheckOut) && ! empty($record->Jam_Keluar)) {
                            $checkout_carbon = Carbon::parse($record->CheckOut);
                            $format_jam_pulang = strlen($record->Jam_Keluar) === 5 ? 'H:i' : 'H:i:s';
                            $jam_pulang_carbon = Carbon::createFromFormat(
                                'Y-m-d '.$format_jam_pulang,
                                $checkout_carbon->format('Y-m-d').' '.$record->Jam_Keluar,
                            );

                            $pengajuan_pulang = collect($pulang_data)->firstWhere('Tanggal', $record->Tanggal);

                            if (! $pengajuan_pulang && $checkout_carbon < $jam_pulang_carbon) {
                                $total_pulang_cepat_menit += $checkout_carbon
                                    ->copy()
                                    ->seconds(0)
                                    ->diffInMinutes($jam_pulang_carbon->copy()->seconds(0));
                            }
                        }

                        // 3. lembur
                        // dd($lembur_data);
                        // --- cari apakah ada pengajuan lembur baru ---
                        $pengajuan_lembur = collect($lembur_data)->first(function ($item) use ($record) {
                            $tanggalPengajuan = Carbon::parse($item->mulai)->toDateString();
                            $tanggalRecord = Carbon::parse($record->Tanggal)->toDateString();
                            // dd($item->Kode_Karyawan .' '. $record->Kode_Karyawan);

                            return $item->Kode_Karyawan === $record->Kode_Karyawan &&
                                $tanggalPengajuan === $tanggalRecord;
                        });

                        if ($pengajuan_lembur) {
                            // Kalau ada pengajuan → langsung pakai Overtime_Real (dalam jam, floor)
                            $total_lembur_jam += floor($record->Overtime_Real / 60);
                        }
                    }

                    // --- hitung hari & hadir sampai endDateForCalculation ---
                    $recordsUpToNow = $records->filter(
                        fn ($r) => Carbon::parse($r->Tanggal)->lte($endDateForCalculation),
                    );

                    // $total_hari = $recordsUpToNow->reject(function ($r) use ($excludedShifts) {
                    //     $shift = strtoupper(trim((string) ($r->Nama_Shift ?? '')));
                    //     return in_array($shift, $excludedShifts, true);
                    // })->count();

                    // $total_hadir = min($total_hari, $recordsUpToNow->filter(fn($r) => !is_null($r->CheckIn) && $r->CheckIn !== '')->count() +  $hariLibur);

                    // Total hari kerja (tidak termasuk shift yang dikecualikan)
                    $total_hari = $recordsUpToNow
                        ->reject(function ($r) use ($excludedShifts) {
                            $shift = strtoupper(trim((string) ($r->Nama_Shift ?? '')));

                            return in_array($shift, $excludedShifts, true);
                        })
                        ->count();

                    // Total hadir (hanya record dengan CheckIn, juga ikut filter shift yang sama)
                    $total_hadir = $recordsUpToNow
                        ->reject(function ($r) use ($excludedShifts) {
                            $shift = strtoupper(trim((string) ($r->Nama_Shift ?? '')));

                            return in_array($shift, $excludedShifts, true);
                        })
                        ->filter(fn ($r) => ! is_null($r->CheckIn) && $r->CheckIn !== '')
                        ->count();

                    // Jika mau tambahkan hari libur
                    $total_hadir = min($total_hari, $total_hadir + $hariLibur);

                    // ==============================
                    // Ambil shift & status hari ini
                    // ==============================
                    $recordToday = $records->filter(fn ($r) => Carbon::parse($r->Tanggal)->isSameDay($today))->first();

                    // dd($recordToday);

                    if ($recordToday) {
                        $shiftHariIni = $recordToday->Nama_Shift ?? 'NO SHIFT';
                        $statusHariIni = $recordToday->CheckIn ? 'Hadir' : 'Tidak Hadir';
                        $jamMasukHariIni = $recordToday->Jam_Masuk ?? '00:00';
                        $jamKeluarHariIni = $recordToday->Jam_Keluar ?? '00:00';

                        // kalau tidak ada CheckIn & tidak ada izin/sakit/cuti → anggap ALPHA
                        if (
                            is_null($recordToday->CheckIn) &&
                            ($sakit_data[$kodeKaryawan] ?? 0) == 0 &&
                            ($izin_data[$kodeKaryawan] ?? 0) == 0 &&
                            ($cuti_data[$kodeKaryawan] ?? 0) == 0
                        ) {
                            $statusHariIni = 'Tidak Hadir';
                        }
                    } else {
                        $shiftHariIni = 'NO SHIFT';
                        $statusHariIni = 'Tidak Hadir';
                        $jamMasukHariIni = '00:00';
                        $jamKeluarHariIni = '00:00';
                    }

                    return [
                        'kode_karyawan' => $kodeKaryawan,
                        'nama' => $records->first()->Nama ?? null,
                        'total_keterlambatan_menit' => $total_keterlambatan_menit,
                        'total_pulang_cepat_menit' => $total_pulang_cepat_menit,

                        'total_lembur_jam' => $total_lembur_jam,
                        'total_sakit' => $sakit_data[$kodeKaryawan] ?? 0,
                        'total_izin' => $izin_data[$kodeKaryawan] ?? 0,
                        'total_cuti' => $cuti_data[$kodeKaryawan] ?? 0,
                        'total_hadir' => $total_hadir,
                        'total_hari' => $total_hari,
                        'label_hadir' => "{$total_hadir}/{$total_hari} Hadir",
                        // tambahan baru
                        'shift_hari_ini' => $shiftHariIni,
                        'status_hari_ini' => $statusHariIni,
                        'jam_masuk_hari_ini' => $jamMasukHariIni,
                        'jam_keluar_hari_ini' => $jamKeluarHariIni,
                    ];
                })
                ->values();

            return response()->json([
                'status' => 200,
                'data' => $rekap,
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data data Absens getRingkasanTeam'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data data Absens',
                ],
                500,
            );
        }
    }

    // public function getDateAbsen(Request $request){
    //     // Dapatkan data karyawan yang sedang login
    //     try{
    //         $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
    //         $requestDate = $request->tanggal;
    //         $nowDate = $requestDate ? Carbon::parse($requestDate) : Carbon::now();

    //         if ($requestDate && $request->jenis == 'next') {
    //             // Mendapatkan Senin minggu depan
    //             $date = $nowDate->next(Carbon::MONDAY)->next(Carbon::MONDAY);
    //         } else if ($requestDate && $request->jenis == 'prev') {
    //             // Mendapatkan Senin minggu lalu
    //             $date = $nowDate->previous(Carbon::MONDAY);
    //         } else {
    //             $date = Carbon::now();
    //         }
    //         $startWeek = $date->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
    //         $endWeek = $date->endOfWeek(Carbon::SATURDAY)->format('Y-m-d');
    //         // Tentukan rentang tanggal

    //         // Ambil data absensi utama dari stored procedure
    //         $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
    //         $absen_data = collect(DB::select($query));

    //         // Ambil semua tanggal unik dari data absen untuk dijadikan filter
    //         $tanggal_absens = $absen_data->pluck('Tanggal')->map(fn($date) => Carbon::parse($date)->format('Y-m-d'));

    //          $hariLibur = DB::table('HRIS_Hari_Libur')
    //         ->whereBetween('Tanggal', [$startWeek, $endWeek])
    //         ->get();
    //         // --- Pre-load Semua Data Transaksi dalam Beberapa Query ---

    //         // 1. Pre-load data lembur
    //         $lembur_data = DB::table('Transaksi_Lembur_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Lembur_dari as mulai', 'b.Tanggal_Lembur_Sampai as selesai')
    //             ->join('Transaksi_Lembur as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Lembur_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Lembur_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();
    //         // 2. Pre-load data sakit/izin
    //         $sakit_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Sakit_Izin_dari as mulai', 'b.Tanggal_Sakit_Izin_Sampai as selesai')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "sakit")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();
    //         $izin_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Sakit_Izin_dari as mulai', 'b.Tanggal_Sakit_Izin_Sampai as selesai')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "izinFull")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();

    //         // 3. Pre-load data cuti
    //         $cuti_data = DB::table('Transaksi_Cuti_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Cuti_dari as mulai', 'b.Tanggal_Cuti_Sampai as selesai')
    //             ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("b.Jenis", "cuti")
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();

    //         $cutiHutang_data = DB::table('Transaksi_Cuti_Detail as b')
    //             ->select('a.No_Transaksi', 'b.Tanggal_Cuti_dari as mulai', 'b.Tanggal_Cuti_Sampai as selesai')
    //             ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "cutiHutang")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where("a.Status", null)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->get();

    //             // 4. Pre-load data terlambat/pulang cepat
    //             $pulang_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //                 ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal')
    //                 ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //                 ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //                 ->where("b.Jenis", "pulangCepat")
    //                 ->where('b.Flag_Approval', 'Y')
    //                 ->where("a.Status", null)
    //                 ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //                 ->get();

    //             $terlambat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //                 ->select('a.No_Transaksi', 'b.Tanggal_Masuk_Pulang as Tanggal')
    //                 ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //                 ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //                 ->where('b.Flag_Approval', 'Y')
    //                 ->where("b.Jenis", "terlambat")
    //                 ->where("a.Status", null)
    //                 ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //                 ->get();

    //             // --- Proses Data Absen dengan Data yang Sudah Di-preload ---

    //             $result = $absen_data->map(function ($item) use ($hariLibur,$lembur_data, $sakit_data,$izin_data, $cuti_data,$cutiHutang_data, $pulang_data, $terlambat_data) {
    //             $tanggal_formatted = Carbon::parse($item->Tanggal)->format('Y-m-d');

    //             // Cari data di dalam Collection yang sudah di-load di awal
    //             $lembur = $lembur_data->where(function($item) use ($tanggal_formatted){

    //             $mulai = Carbon::parse($item->mulai)->format('Y-m-d');
    //             $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //             return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $sakit = $sakit_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             // dd($sakit);
    //             $izin = $izin_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $cuti = $cuti_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $cutiHutang = $cutiHutang_data->filter(function($item) use ($tanggal_formatted) {
    //                 $mulai   = Carbon::parse($item->mulai)->format('Y-m-d');
    //                 $selesai = Carbon::parse($item->selesai)->format('Y-m-d');

    //                 return Carbon::parse($tanggal_formatted)->between($mulai, $selesai);
    //             })->first();

    //             $pulang = $pulang_data
    //                 ->first(function ($row) use ($tanggal_formatted) {
    //                     return Carbon::parse($row->Tanggal)->format('Y-m-d') === $tanggal_formatted;
    //                 });

    //             $terlambat = $terlambat_data
    //                 ->first(function ($row) use ($tanggal_formatted) {
    //                     return Carbon::parse($row->Tanggal)->format('Y-m-d') === $tanggal_formatted;
    //                 });
    //             $Libur = $hariLibur
    //                 ->first(function ($row) use ($tanggal_formatted) {
    //                     return Carbon::parse($row->Tanggal)->format('Y-m-d') === $tanggal_formatted;
    //                 });
    //             // dd($sakit);

    //             return [
    //                 'Nama_Shift' => $item->Nama_Shift,
    //                 'CheckIn' => $item->CheckIn,
    //                 'CheckOut' => $item->CheckOut,
    //                 'Jam_Masuk' => $item->Jam_Masuk,
    //                 'Jam_Keluar' => $item->Jam_Keluar,
    //                 'Tanggal' => $item->Tanggal,
    //                 'Lembur_No_Transaksi' => $lembur->No_Transaksi ?? null,
    //                 'Lembur_Mulai' => $lembur->mulai ?? null,
    //                 'Lembur_Selesai' => $lembur->selesai ?? null,
    //                 'Sakit_No_Transaksi' => $sakit->No_Transaksi ?? null,
    //                 'Sakit_Mulai' => $sakit->mulai ?? null,
    //                 'Sakit_Selesai' => $sakit->selesai ?? null,
    //                 'Izin_No_Transaksi' => $izin->No_Transaksi ?? null,
    //                 'Izin_Mulai' => $izin->mulai ?? null,
    //                 'Izin_Selesai' => $izin->selesai ?? null,
    //                 'Cuti_No_Transaksi' => $cuti->No_Transaksi ?? null,
    //                 'Cuti_Mulai' => $cuti->mulai ?? null,
    //                 'Cuti_Selesai' => $cuti->selesai ?? null,
    //                 'CutiHutang_No_Transaksi' => $cutiHutang->No_Transaksi ?? null,
    //                 'CutiHutang_Mulai' => $cutiHutang->mulai ?? null,
    //                 'CutiHutang_Selesai' => $cutiHutang->selesai ?? null,
    //                 'Pulang_No_Transaksi' => $pulang->No_Transaksi ?? null,
    //                 'Pulang_Tanggal' => $pulang->Tanggal ?? null,
    //                 'Terlambat_No_Transaksi' => $terlambat->No_Transaksi ?? null,
    //                 'Terlambat_Tanggal' => $terlambat->Tanggal ?? null,
    //                 'hariLibur' => $Libur->Nama_Hari_Libur ?? null
    //             ];
    //         });

    //         // dd($result);

    //         return response()->json([
    //             'status' => 200,
    //             'data' => $result ?? []
    //         ]);
    //     }catch(\Throwable $e){
    //         Log::channel('uDashLog')->error('Gagal Mengambil data data Absens getDateAbsen'. $e->getMessage());
    //         return response()->json([
    //             'status' => 500,
    //             'error' => "Tidak bisa mengambil data data Absens"
    //         ], 500);
    //     }
    // }

    public function getDateAbsen(Request $request)
    {
        try {
            $user = Auth::user();
            $UserID_Absen = $user->karyawan->UserID_Absen;
            $Kode_Karyawan = $user->karyawan->Kode_Karyawan;

            // 1. Setup Tanggal (Minggu Ini/Depan/Lalu)
            $requestDate = $request->tanggal;
            $nowDate = $requestDate ? Carbon::parse($requestDate) : Carbon::now();

            if ($requestDate && $request->jenis == 'next') {
                $date = $nowDate->next(Carbon::MONDAY)->next(Carbon::MONDAY);
            } elseif ($requestDate && $request->jenis == 'prev') {
                $date = $nowDate->previous(Carbon::MONDAY);
            } else {
                $date = Carbon::now();
            }

            $startWeek = $date->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $endWeek = $date->endOfWeek(Carbon::SATURDAY)->format('Y-m-d');

            // 2. Ambil Data Absensi Utama (SP)
            $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
            $absen_data = collect(DB::select($query));

            // 3. Ambil Hari Libur
            $hariLibur = DB::table('HRIS_Hari_Libur')
                ->whereBetween('Tanggal', [$startWeek, $endWeek])
                ->get();

            // 4. Future-Proof & Efisien: Ambil SEMUA Transaksi Approved dari VIEW
            // Daripada query 5 tabel berbeda, kita ambil sekali saja dari View.
            // View 'N_HRIS_VW_All_Transactions' diasumsikan sudah berisi tanggal detail (Mulai & Selesai) yang benar.

            $allTransactions = DB::table('N_HRIS_VW_All_Transactions')
                ->where('Kode_Karyawan_Requester', $Kode_Karyawan)
                // Filter Data yang beririsan dengan minggu ini
                ->where(function ($q) use ($startWeek, $endWeek) {
                    $q->whereDate('Tanggal_Mulai', '<=', $endWeek)->whereDate('Tanggal_Selesai', '>=', $startWeek);
                })
                // Filter hanya yang sudah diapprove (Sesuai logika lama: Flag_Approval = 'Y' atau Status Not Null)
                // Sesuaikan dengan logika View Anda, biasanya jika masuk View dan status 'Y' berarti approved.
                ->where(function ($q) {
                    $q->where('Flag_Approval', 'Y')->orWhere('Flag_Approval', 'Y');
                })
                ->get();

            // 5. Mapping Data (Looping Absen Harian)
            $result = $absen_data->map(function ($item) use ($hariLibur, $allTransactions) {
                $currentDate = Carbon::parse($item->Tanggal)->format('Y-m-d');

                // Cari Libur
                $libur = $hariLibur->first(fn ($l) => Carbon::parse($l->Tanggal)->format('Y-m-d') === $currentDate);

                // Filter transaksi yang mencakup hari ini ($currentDate)

                $todaysTx = $allTransactions->filter(function ($tx) use ($currentDate) {
                    $tanggalTransaksi = $this->getTanggal($tx->No_Transaksi)['tanggal']; // array tanggal lengkap

                    return collect($tanggalTransaksi)->contains(function ($tgl) use ($currentDate) {
                        return Carbon::parse($tgl)->format('Y-m-d') === $currentDate;
                    });
                });

                // Helper untuk mengambil transaksi berdasarkan Tipe & SubTipe
                // Kita pakai first() karena asumsinya 1 hari cuma ada 1 transaksi per tipe
                $getTx = fn ($tipe, $subTipe = null) => $todaysTx->first(function ($tx) use ($tipe, $subTipe) {
                    // Sesuaikan nama kolom Tipe/SubTipe dengan View Anda (misal: Tipe_Transaksi, Sub_Tipe)
                    $matchTipe = strtolower($tx->Tipe_Transaksi) === strtolower($tipe);
                    if ($subTipe) {
                        return $matchTipe && strtolower($tx->Sub_Tipe) === strtolower($subTipe);
                    }

                    return $matchTipe;
                });

                // Mapping spesifik sesuai kunci JSON lama
                // Note: Pastikan 'Sub_Tipe' di View sesuai dengan value 'Jenis' di tabel detail lama

                $lembur = $getTx('Lembur');
                $sakit = $getTx('Sakit', 'sakit') ?? $getTx('Izin', 'sakit'); // Handle jika view mengelompokkan beda
                $izin = $getTx('Izin', 'izinFull') ?? $getTx('Sakit', 'izinFull');
                $cuti = $getTx('Cuti');
                $cutiHutang = $getTx('Cuti', 'cutiHutang');

                // Untuk Terlambat/Pulang Cepat, biasanya Sub_Tipe nya 'pulangCepat' / 'terlambat'
                $pulang = $getTx('Terlambat_Pulang_Cepat', 'pulangCepat');
                $terlambat = $getTx('Terlambat_Pulang_Cepat', 'terlambat');

                return [
                    'Nama_Shift' => $item->Nama_Shift,
                    'CheckIn' => $item->CheckIn,
                    'CheckOut' => $item->CheckOut,
                    'Jam_Masuk' => $item->Jam_Masuk,
                    'Jam_Keluar' => $item->Jam_Keluar,
                    'Tanggal' => $item->Tanggal,

                    // Mapping Data Transaksi
                    'Lembur_No_Transaksi' => $lembur->No_Transaksi ?? null,
                    'Lembur_Mulai' => $lembur->Tanggal_Mulai ?? null,
                    'Lembur_Selesai' => $lembur->Tanggal_Selesai ?? null,

                    'Sakit_No_Transaksi' => $sakit->No_Transaksi ?? null,
                    'Sakit_Mulai' => $sakit->Tanggal_Mulai ?? null,
                    'Sakit_Selesai' => $sakit->Tanggal_Selesai ?? null,

                    'Izin_No_Transaksi' => $izin->No_Transaksi ?? null,
                    'Izin_Mulai' => $izin->Tanggal_Mulai ?? null,
                    'Izin_Selesai' => $izin->Tanggal_Selesai ?? null,

                    'Cuti_No_Transaksi' => $cuti->No_Transaksi ?? null,
                    'Cuti_Mulai' => $cuti->Tanggal_Mulai ?? null,
                    'Cuti_Selesai' => $cuti->Tanggal_Selesai ?? null,

                    'CutiHutang_No_Transaksi' => $cutiHutang->No_Transaksi ?? null,
                    'CutiHutang_Mulai' => $cutiHutang->Tanggal_Mulai ?? null,
                    'CutiHutang_Selesai' => $cutiHutang->Tanggal_Selesai ?? null,

                    'Pulang_No_Transaksi' => $pulang->No_Transaksi ?? null,
                    'Pulang_Tanggal' => $pulang->Tanggal_Mulai ?? null, // Pulang cepat biasanya 1 hari (start=end)

                    'Terlambat_No_Transaksi' => $terlambat->No_Transaksi ?? null,
                    'Terlambat_Tanggal' => $terlambat->Tanggal_Mulai ?? null,

                    'hariLibur' => $libur->Nama_Hari_Libur ?? null,
                ];
            });

            return response()->json([
                'status' => 200,
                'data' => $result ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data Absens getDateAbsen'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data Absens',
                ],
                500,
            );
        }
    }

    private function getTanggal($noTransaksi)
    {
        $prefix = substr($noTransaksi, 0, 2);

        $tableMap = [
            'CT' => 'Transaksi_Cuti_Det',
            'IS' => 'Transaksi_Sakit_Izin_Det',
            'TP' => 'Transaksi_Terlambat_Pulang_Cepat_Detail',
        ];

        $tanggalMap = [
            'CT' => 'Tanggal_Cuti',
            'IS' => 'Tanggal_Sakit_Izin',
            'TP' => 'Tanggal_Masuk_Pulang',
        ];
        $tanggal = null;
        if (isset($tableMap[$prefix])) {
            $det = DB::table($tableMap[$prefix])
                ->where('No_Transaksi', $noTransaksi)
                ->pluck($tanggalMap[$prefix])
                ->toArray();
            // dd($det);
            $jumlahHari = ! empty($det) ? count($det) : 0;
            // $tanggal = FormatTanggalHelper::formatTanggalMultiple($det);
            $tanggal = $det;
            // dd($tanggal);
        }

        // dd($tanggal);

        return [
            'tanggal' => $tanggal,
            'jumlahHari' => $jumlahHari,
        ];
    }

    public function getDataShift(Request $request)
    {
        try {
            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $requestDate = $request->tanggal;
            $nowDate = $requestDate ? Carbon::parse($requestDate) : Carbon::now();

            if ($requestDate && $request->jenis == 'next') {
                // Mendapatkan Senin minggu depan
                $date = $nowDate->next(Carbon::MONDAY)->next(Carbon::MONDAY);
            } elseif ($requestDate && $request->jenis == 'prev') {
                // Mendapatkan Senin minggu lalu
                $date = $nowDate->previous(Carbon::MONDAY);
            } else {
                $date = Carbon::now();
            }
            $startWeek = $date->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $endWeek = $date->endOfWeek(Carbon::SATURDAY)->format('Y-m-d');
            // Tentukan rentang tanggal

            // Ambil data absensi utama dari stored procedure
            $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";

            $absen_data = collect(DB::select($query))->map(function ($item) {
                return [
                    'Nama_Shift' => $item->Nama_Shift,
                    'Jam_Masuk' => $item->Jam_Masuk,
                    'Jam_Keluar' => $item->Jam_Keluar,
                    'Tanggal' => $item->Tanggal,
                ];
            });

            return response()->json([
                'status' => 200,
                'data' => $absen_data ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data shift getDataShift'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data shift',
                ],
                500,
            );
        }
    }

    // public function getAllIzin(){
    //     try{

    //         $perPage = 4;
    //         $currentPage = request()->get('page', 1);
    //         $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
    //         $query = "
    //                     WITH
    //                         cte AS (
    //                         SELECT
    //                             CAST(CONCAT(CAST(cti.Tanggal AS DATE),' ', cti.Jam) AS DATETIME) AS tanggal_create,
    //                             k.Kode_Karyawan,
    //                             k.Nama,
    //                             ct.No_Transaksi,
    //                             ct.Jenis as Tipe_Izin,
    //                             ct.Tanggal_Cuti_Dari AS Tanggal_Mulai,
    //                             ct.Tanggal_Cuti_Sampai AS Tanggal_Selesai,
    //                             ct.Alasan,
    //                             ct.filePath as Lampiran,
    //                             ISNULL(
    //                                 (
    //                                 SELECT
    //                                     TOP 1 af.order_flow
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Flag_Approval IS NOT NULL
    //                                 ORDER BY
    //                                     af.order_flow DESC
    //                                 ),
    //                                 0
    //                             ) as currentFlow,
    //                             ISNULL(
    //                                 (
    //                                 select
    //                                     top 1 order_flow
    //                                 from
    //                                     HRIS_Approval_Flow a,
    //                                     HRIS_Approval_Request b
    //                                 where
    //                                     a.id = b.Flow_Id
    //                                     and b.No_Transaksi = ct.No_Transaksi
    //                                 order by
    //                                     a.order_flow desc
    //                                 ),
    //                                 0
    //                             ) as MaxFlow,
    //                             CASE
    //                                 WHEN ct.Flag_Approval = 'Y' THEN 'Selesai'
    //                                 WHEN ct.Flag_Approval = 'T' THEN 'Ditolak'
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 'Diproses'
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 'NO'
    //                                 )
    //                             END as status,
    //                             CASE
    //                                 WHEN ct.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                                 WHEN ct.Flag_Approval = 'T' THEN (
    //                                 select
    //                                     top 1 k.Nama
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval = 'T'
    //                                 ORDER BY
    //                                     af.order_flow
    //                                 )
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 ka.Nama
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     JOIN
    //                                         Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                     WHERE
    //                                     ar.No_Transaksi = ct.No_Transaksi
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 null
    //                                 )
    //                             END AS Approver,
    //                             NULL AS Jam_Mulai,
    //                             (
    //                                 select
    //                                 STRING_AGG(
    //                                     CONCAT(
    //                                     b.Kode_Karyawan,
    //                                     ': ',
    //                                     CASE
    //                                         WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                         ELSE 'N'
    //                                     END
    //                                     ),
    //                                     ', '
    //                                 ) WITHIN GROUP (
    //                                     ORDER BY
    //                                     b.order_flow
    //                                 ) AS StatusList
    //                                 from
    //                                 HRIS_Approval_Request a
    //                                 JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                                 where
    //                                 a.No_Transaksi = ct.No_Transaksi
    //                             ) AS STATUSLIST
    //                             FROM
    //                             Karyawan AS k
    //                             INNER JOIN Transaksi_Cuti_Detail AS ct ON k.Kode_Karyawan = ct.Kode_Karyawan
    //                             INNER JOIN Transaksi_Cuti AS cti ON ct.No_Transaksi = cti.No_Transaksi
    //                             WHERE
    //                             k.Kode_Karyawan = ?
    //                             and cti.Status is null
    //                             UNION ALL
    //                             -- Query untuk Transaksi_Sakit_Izin_Detail
    //                             SELECT
    //                             CAST(CONCAT(CAST(tsip.Tanggal AS DATE),' ', tsip.Jam) AS DATETIME) AS tanggal_create,
    //                             k.Kode_Karyawan,
    //                             k.Nama,
    //                             tsi.No_Transaksi,
    //                             tsi.Jenis as Tipe_Izin,
    //                             tsi.Tanggal_Sakit_Izin_Dari AS Tanggal_Mulai,
    //                             tsi.Tanggal_Sakit_Izin_Sampai AS Tanggal_Selesai,
    //                             tsi.Alasan,
    //                             tsi.filePath as Lampiran,
    //                             ISNULL(
    //                                 (
    //                                 SELECT
    //                                     TOP 1 af.order_flow
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Flag_Approval IS NOT NULL
    //                                 ORDER BY
    //                                     af.order_flow DESC
    //                                 ),
    //                                 0
    //                             ) as currentFlow,
    //                             ISNULL(
    //                                 (
    //                                 select
    //                                     top 1 order_flow
    //                                 from
    //                                     HRIS_Approval_Flow a,
    //                                     HRIS_Approval_Request b
    //                                 where
    //                                     a.id = b.Flow_Id
    //                                     and b.No_Transaksi = tsi.No_Transaksi
    //                                 order by
    //                                     a.order_flow desc
    //                                 ),
    //                                 0
    //                             ) as MaxFlow,
    //                             CASE
    //                                 WHEN tsi.Flag_Approval = 'Y' THEN 'Selesai'
    //                                 WHEN tsi.Flag_Approval = 'T' THEN 'Ditolak'
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 'Diproses'
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 'NO'
    //                                 )
    //                             END as status,
    //                             CASE
    //                                 WHEN tsi.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                                 WHEN tsi.Flag_Approval = 'T' THEN (
    //                                 select
    //                                     top 1 k.Nama
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval = 'T'
    //                                 ORDER BY
    //                                     af.order_flow
    //                                 )
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 ka.Nama
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     JOIN
    //                                         Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                     WHERE
    //                                     ar.No_Transaksi = tsi.No_Transaksi
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 null
    //                                 )
    //                             END AS Approver,
    //                             NULL AS Jam_Mulai,
    //                             (
    //                                 select
    //                                 STRING_AGG(
    //                                     CONCAT(
    //                                     b.Kode_Karyawan,
    //                                     ': ',
    //                                     CASE
    //                                         WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                         ELSE 'N'
    //                                     END
    //                                     ),
    //                                     ', '
    //                                 ) WITHIN GROUP (
    //                                     ORDER BY
    //                                     b.order_flow
    //                                 ) AS StatusList
    //                                 from
    //                                 HRIS_Approval_Request a
    //                                 JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                                 where
    //                                 a.No_Transaksi = tsi.No_Transaksi
    //                             ) AS STATUSLIST
    //                             FROM
    //                             Karyawan AS k
    //                             INNER JOIN Transaksi_Sakit_Izin_Detail AS tsi ON k.Kode_Karyawan = tsi.Kode_Karyawan
    //                             INNER JOIN Transaksi_Sakit_Izin AS tsip ON tsi.No_Transaksi = tsip.No_Transaksi
    //                             WHERE
    //                             k.Kode_Karyawan = ?
    //                             and tsip.Status is null
    //                             UNION ALL -- Gunakan UNION ALL jika Anda tidak perlu menghilangkan duplikat
    //                             SELECT
    //                             CAST(CONCAT(CAST(ttpp.Tanggal AS DATE),' ', ttpp.Jam) AS DATETIME) AS tanggal_create,
    //                             k.Kode_Karyawan,
    //                             k.Nama,
    //                             ttp.No_Transaksi,
    //                             ttp.Jenis as Tipe_Izin,
    //                             ttp.Tanggal_Masuk_Pulang AS Tanggal_Mulai,
    //                             NULL AS Tanggal_Selesai,
    //                             ttp.Alasan,
    //                             ttp.filePath as Lampiran,
    //                             ISNULL(
    //                                 (
    //                                 SELECT
    //                                     TOP 1 af.order_flow
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NOT NULL
    //                                 ORDER BY
    //                                     af.order_flow DESC
    //                                 ),
    //                                 0
    //                             ) as currentFlow,
    //                             ISNULL(
    //                                 (
    //                                 select
    //                                     top 1 order_flow
    //                                 from
    //                                     HRIS_Approval_Flow a,
    //                                     HRIS_Approval_Request b
    //                                 where
    //                                     a.id = b.Flow_Id
    //                                     and b.No_Transaksi = ttp.No_Transaksi
    //                                 order by
    //                                     a.order_flow desc
    //                                 ),
    //                                 0
    //                             ) as MaxFlow,
    //                             CASE
    //                                 WHEN ttp.Flag_Approval = 'Y' THEN 'Selesai'
    //                                 WHEN ttp.Flag_Approval = 'T' THEN 'Ditolak'
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 'Diproses'
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 'NO'
    //                                 )
    //                             END as status,
    //                             CASE
    //                                 WHEN ttp.Flag_Approval = 'Y' THEN 'DiSetujui'
    //                                 WHEN ttp.Flag_Approval = 'T' THEN (
    //                                 select
    //                                     top 1 k.Nama
    //                                 FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                 WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Kode_Karyawan = k.Kode_Karyawan
    //                                     AND ar.Flag_Approval = 'T'
    //                                 ORDER BY
    //                                     af.order_flow
    //                                 )
    //                                 ELSE ISNULL(
    //                                 (
    //                                     SELECT
    //                                     TOP 1 ka.Nama
    //                                     FROM
    //                                     HRIS_Approval_Request ar
    //                                     JOIN HRIS_Approval_Flow af ON ar.Flow_Id = af.id
    //                                     JOIN
    //                                         Karyawan ka ON af.Kode_Karyawan = ka.Kode_Karyawan
    //                                     WHERE
    //                                     ar.No_Transaksi = ttp.No_Transaksi
    //                                     AND ar.Flag_Approval IS NULL
    //                                     ORDER BY
    //                                     af.order_flow
    //                                 ),
    //                                 null
    //                                 )
    //                             END AS Approver,
    //                             ttp.Jam AS Jam_Mulai,
    //                             (
    //                                 select
    //                                 STRING_AGG(
    //                                     CONCAT(
    //                                     b.Kode_Karyawan,
    //                                     ': ',
    //                                     CASE
    //                                         WHEN a.Flag_Approval IS NOT NULL THEN a.Flag_Approval
    //                                         ELSE 'N'
    //                                     END
    //                                     ),
    //                                     ', '
    //                                 ) WITHIN GROUP (
    //                                     ORDER BY
    //                                     b.order_flow
    //                                 ) AS StatusList
    //                                 from
    //                                 HRIS_Approval_Request a
    //                                 JOIN HRIS_Approval_Flow b ON a.Flow_Id = b.id
    //                                 where
    //                                 a.No_Transaksi = ttp.No_Transaksi
    //                             ) AS STATUSLIST
    //                             FROM
    //                             Karyawan AS k
    //                             INNER JOIN Transaksi_Terlambat_Pulang_Cepat_Detail AS ttp ON k.Kode_Karyawan = ttp.Kode_Karyawan
    //                             INNER JOIN Transaksi_Terlambat_Pulang_Cepat AS ttpp ON ttp.No_Transaksi = ttpp.No_Transaksi
    //                             WHERE
    //                             k.Kode_Karyawan = ? and ttpp.Status is null
    //                         )
    //                         SELECT
    //                         *
    //                         FROM
    //                         cte
    //                         where
    //                         MaxFlow <> 0
    //                         ORDER BY
    //                         tanggal_create desc
    //         ";
    //         $result = DB::select($query, [$Kode_Karyawan, $Kode_Karyawan, $Kode_Karyawan]);
    //         $collection = collect($result);
    //         $slice_collect = $collection->slice(($currentPage-1) * $perPage, $perPage)->values();

    //         $final_page = new LengthAwarePaginator(
    //             $slice_collect,
    //             $collection->count(),
    //             $perPage,
    //             $currentPage,
    //             ['path' => request()->url()]
    //         );

    //         return response()->json($final_page);
    //     }catch(\Throwable $e){
    //         Log::channel('uDashLog')->error('Gagal Mengambil data izin getAllIzin'. $e->getMessage());
    //         return response()->json([
    //             'status' => 500,
    //             'error' => "Tidak bisa mengambil data izin "
    //         ], 500);
    //     }
    // }

    public function getAllIzin()
    {
        try {
            $perPage = 4;
            $page = request()->get('page', 1);
            $user = Auth::user();
            $kodeKaryawan = $user->karyawan->Kode_Karyawan;

            // 1. Query Utama ke View Transaksi
            // Ini menggantikan UNION ALL yang panjang. View ini harus berisi semua jenis izin/cuti.
            $query = DB::table('N_HRIS_VW_All_Transactions')->where('Kode_Karyawan_Requester', $kodeKaryawan);

            // Hitung total untuk pagination
            $total = $query->count();

            // Ambil data sesuai halaman (Offset & Limit di Database, bukan di PHP)
            $items = $query->orderBy('Created_At', 'DESC')->forPage($page, $perPage)->get();

            // 2. Eager Load Approval Data
            // Ambil detail approval hanya untuk transaksi yang tampil di halaman ini
            $transactionIds = $items->pluck('No_Transaksi')->toArray();
            $approvals = collect();

            if (! empty($transactionIds)) {
                $approvals = DB::table('N_HRIS_VW_Approval_Request')
                    ->whereIn('No_Transaksi', $transactionIds)
                    ->orderBy('Request_Order_Flow', 'ASC')
                    ->get()
                    ->groupBy('No_Transaksi');
            }

            // 3. Mapping Data (Replikasi Logika SQL Lama ke PHP)
            // Kita manipulasi agar output JSON key-nya sama persis dengan query lama
            $formattedData = $items->map(function ($item) use ($approvals) {
                $currApprovals = $approvals->get($item->No_Transaksi, collect());

                // --- REPLIKASI LOGIKA 'MAGIC' DARI QUERY LAMA ---

                // Hitung Max Flow
                $maxFlow = $currApprovals->max('Request_Order_Flow') ?? 0;

                // Hitung Current Flow
                $lastAction = $currApprovals->whereNotNull('Flag_Approval')->sortByDesc('Request_Order_Flow')->first();
                $currentFlow = $lastAction ? $lastAction->Request_Order_Flow : 0;

                // Tentukan Status ('Selesai', 'Ditolak', 'Diproses', 'NO')
                $statusText = 'NO';
                // Cek apakah ada reject
                $rejector = $currApprovals->firstWhere('Flag_Approval', 'T');
                // Cek apakah semua approved
                $isFullyApproved =
                    $currApprovals->isNotEmpty() && $currApprovals->every(fn ($a) => $a->Flag_Approval == 'Y');
                // Cek pending (yang null flagnya)
                $pendingApprover = $currApprovals->sortBy('Request_Order_Flow')->firstWhere('Flag_Approval', null);

                // Logika SQL: CASE WHEN Flag='Y' THEN 'Selesai' ...
                // Karena di View header biasanya sudah ada status final, kita pakai logika detail approval agar akurat 100% dgn lama
                if ($isFullyApproved || $item->Flag_Approval == 'Y') {
                    $statusText = 'Selesai';
                } elseif ($rejector || $item->Flag_Approval == 'T') {
                    $statusText = 'Ditolak';
                } elseif ($pendingApprover) {
                    $statusText = 'Diproses';
                }

                // Tentukan Field 'Approver' (Logic unik query lama)
                // SQL Lama: Y='DiSetujui', T=NamaPenolak, Else=NamaPendingApprover
                $approverField = null;
                if ($statusText == 'Selesai') {
                    $approverField = 'DiSetujui';
                } elseif ($rejector) {
                    $approverField = $rejector->Current_Approver_Kode; // Nama yang menolak
                } elseif ($pendingApprover) {
                    $approverField = $pendingApprover->Current_Approver_Kode; // Nama yang sedang menunggu
                }

                // Status List (String Aggregation)
                $statusList = $currApprovals
                    ->map(function ($row) {
                        $flag = $row->Flag_Approval ?? 'N';

                        return "{$row->Current_Approver_Kode}: {$flag}";
                    })
                    ->implode(', ');

                // Return array dengan Key yang SAMA PERSIS dengan Query SQL Lama
                return [
                    'tanggal_create' => $item->Created_At,
                    'Kode_Karyawan' => $item->Kode_Karyawan_Requester,
                    'Nama' => $item->Nama_Requester,
                    'No_Transaksi' => $item->No_Transaksi,
                    'Tipe_Izin' => $item->Sub_Tipe, // Asumsi nama kolom di View
                    'Tanggal_Mulai' => $item->Tanggal_Mulai,
                    'Tanggal_Selesai' => $item->Tanggal_Selesai,
                    'Alasan' => $item->Deskripsi, // Asumsi Deskripsi adalah Alasan
                    'Lampiran' => $item->File_Path,
                    'currentFlow' => $currentFlow,
                    'MaxFlow' => $maxFlow,
                    'status' => $statusText,
                    'Approver' => $approverField,
                    'Jam_Mulai' => $item->Jam_Diajukan, // Null jika cuti, ada isinya jika pulang cepat
                    'StatusList' => $statusList,
                ];
            });

            // 4. Buat Paginator
            $final_page = new LengthAwarePaginator($formattedData, $total, $perPage, $page, [
                'path' => request()->url(),
            ]);

            return response()->json($final_page);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data izin getAllIzin'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data izin',
                ],
                500,
            );
        }
    }

    public function getRiwayatAbsen(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
            'tanggal' => ['nullable', 'date'], // filter dari tanggal
            // 'end_date'   => ['nullable', 'date'], // filter sampai tanggal
        ]);

        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $perPage = $validated['per_page'] ?? 7;
            $page = $validated['page'] ?? 1;
            $offset = ($page - 1) * $perPage;
            // dd($page);
            // --- COUNT distinct tanggal ---

            $queryCount = '
                SELECT COUNT(DISTINCT CAST(a.CheckTime AS DATE)) AS total
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
            ';
            $paramsCount = [$Kode_Karyawan];

            if ($request->tanggal) {
                $queryCount .= ' AND CAST(a.CheckTime AS DATE) = ? ';
                $paramsCount[] = $request->tanggal;
                // $paramsCount[] = $request->end_date;
            }

            $total = (int) DB::selectOne($queryCount, $paramsCount)->total;

            // --- DATA ---
            $queryData = '
                SELECT
                    CAST(a.CheckTime AS DATE) AS Tanggal,
                    COUNT(*) AS JumlahAbsensi
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
            ';
            $paramsData = [$Kode_Karyawan];

            if ($request->tanggal) {
                $queryData .= ' AND CAST(a.CheckTime AS DATE) = ? ';
                $paramsData[] = $request->tanggal;
                // $paramsData[] = $request->end_date;
            }

            $queryData .= '
                GROUP BY CAST(a.CheckTime AS DATE)
                ORDER BY Tanggal DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY
            ';

            $paramsData[] = $offset;
            $paramsData[] = $perPage;

            $results = DB::select($queryData, $paramsData);

            $final_page = new \Illuminate\Pagination\LengthAwarePaginator(collect($results), $total, $perPage, $page, [
                'path' => request()->url(),
                'query' => request()->query(),
            ]);

            return response()->json([
                'status' => 200,
                'data' => $final_page,
            ]);
        } catch (\Exception $e) {
            \Log::channel('uDashLog')->error('Gagal ambil riwayat absen: '.$e->getMessage());

            return response()->json(
                [
                    'message' => 'Terjadi kesalahan saat mengambil data',
                ],
                500,
            );
        }
    }

    public function getChartLembur(Request $request)
    {
        try {
            $tahun = $request->year ?? Carbon::now()->format('Y');
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
            $data = DB::table('Transaksi_Lembur as a')
                ->join('Transaksi_Lembur_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('b.Kode_Karyawan', $Kode_Karyawan)
                ->whereNull('a.Status')
                ->whereYear('b.Tanggal_Lembur_Dari', $tahun) // filter pertahun
                ->orderBy('b.Tanggal_Lembur_Dari')
                ->get();

            $tanggal_Lembur = $data->pluck('Tanggal_Lembur_Dari');

            if ($tanggal_Lembur->isEmpty()) {
                return response()->json([
                    'status' => 200,
                    'labels' => [],
                    'data' => [],
                ]);
            }
            // Cari range untuk ambil data absen
            $startWeek = Carbon::parse($tanggal_Lembur->first())->format('Y-m-d');
            $endWeek = Carbon::parse($tanggal_Lembur->last())->format('Y-m-d');

            $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
            $absen_data = collect(DB::select($query));

            // Proses data lembur
            $RawData = $data->map(function ($lembur) use ($absen_data) {
                $tanggalLembur = Carbon::parse($lembur->Tanggal_Lembur_Dari)->format('Y-m-d');

                $absenMatchCheckIn = collect($absen_data)
                    ->filter(
                        fn ($item) => Carbon::parse($item->Tanggal)->format('Y-m-d') === $tanggalLembur &&
                            ! is_null($item->CheckIn),
                    )
                    ->isNotEmpty();

                $absenMatchCheckOut = collect($absen_data)
                    ->filter(
                        fn ($item) => Carbon::parse($item->Tanggal)->format('Y-m-d') === $tanggalLembur &&
                            ! is_null($item->CheckOut),
                    )
                    ->isNotEmpty();

                $Status_Lembur = $absenMatchCheckIn && $absenMatchCheckOut ? 'Selesai' : 'Active';

                $jamAktual =
                    collect($absen_data)
                        ->filter(fn ($item) => Carbon::parse($item->Tanggal)->format('Y-m-d') === $tanggalLembur)
                        ->first()?->CheckOut ?? Carbon::parse($lembur->Tanggal_Lembur_Sampai);

                $durasi = floor(
                    Carbon::parse($lembur->Tanggal_Lembur_Dari)->diffInMinutes(Carbon::parse($jamAktual)) / 60,
                );

                return [
                    'Tanggal_Lembur_Dari' => $lembur->Tanggal_Lembur_Dari,
                    'Tanggal_Lembur_Sampai' => $lembur->Tanggal_Lembur_Sampai,
                    'JamAktual' => $jamAktual,
                    'durasi' => $durasi,
                    'Alasan' => $lembur->Alasan,
                    'Status' => $Status_Lembur,
                ];
            });

            // === Grouping per cutoff (21 - 20) ===
            $perCutoff = $RawData
                ->groupBy(function ($item) {
                    $tgl = Carbon::parse($item['Tanggal_Lembur_Dari']);
                    if ($tgl->day >= 21) {
                        // mulai tanggal 21 → periode bulan berjalan
                        return $tgl->format('Y-m');
                    } else {
                        // sebelum 21 → periode bulan sebelumnya
                        return $tgl->copy()->subMonth()->format('Y-m');
                    }
                })
                ->map(fn ($items) => collect($items)->sum('durasi'));
            // dd($perCutoff);
            $labels = [];
            $dataChart = [];
            $ranges = [];

            for ($i = 1; $i <= 12; $i++) {
                // periode cutoff: 21 bulan i → 20 bulan i+1
                $start = Carbon::createFromDate($tahun, $i, 21);
                $end = $start->copy()->addMonth()->day(20);

                $periode = $start->format('Y-m');
                $labels[] = $start->format('M'); // label bawah: "Jan", "Feb", dst
                $dataChart[] = round($perCutoff->get($periode, 0), 2);

                // simpan range cutoff untuk tooltip
                $ranges[] = $start->translatedFormat('d M').' - '.$end->translatedFormat('d M');
            }

            return response()->json([
                'status' => 200,
                'labels' => $labels,
                'data' => $dataChart,
                'ranges' => $ranges,
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data izin getDataCHartLembur'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data izin ',
                ],
                500,
            );
        }
    }

    public function getAbsenDetail(Request $request)
    {
        try {
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $tanggal = $request->tanggal;
            $query = '
                SELECT a.CheckTime
                FROM CHECKINOUT a
                JOIN Karyawan b ON a.USERID = b.UserID_Absen
                WHERE b.Kode_Karyawan = ?
                AND CAST(a.CheckTime AS DATE) = ?
                ORDER BY a.CheckTime ASC
            ';
            $results = DB::select($query, [$Kode_Karyawan, $tanggal]);

            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
            $queryCheckInOut = 'exec HRIS_SP_GET_ABSEN_NEW_V3 ?, ?, ?, ?, ?, ?';
            $resultCheckInOut = DB::select($queryCheckInOut, ['001', $tanggal, $tanggal, $UserID_Absen, '', '']);
            $resultCheckInOut = collect($resultCheckInOut)->map(function ($item) {
                return [
                    'Nama_Shift' => $item->Nama_Shift,
                    'CheckIn' => $item->CheckIn,
                    'CheckOut' => $item->CheckOut,
                    'Jam_Masuk' => Carbon::parse(
                        Carbon::parse($item->Tanggal)->format('Y-m-d').
                            ' '.
                            Carbon::parse($item->Jam_Masuk)->format('H:i:s'),
                    ),
                    'Jam_Keluar' => $item->Jam_Keluar < $item->Jam_Masuk
                            ? Carbon::parse(
                                Carbon::parse($item->Tanggal)->format('Y-m-d').
                                    ' '.
                                    Carbon::parse($item->Jam_Keluar)->format('H:i:s'),
                            )->addDay()
                            : Carbon::parse(
                                Carbon::parse($item->Tanggal)->format('Y-m-d').
                                    ' '.
                                    Carbon::parse($item->Jam_Keluar)->format('H:i:s'),
                            ),
                    'Tanggal' => $item->Tanggal,
                ];
            });

            return response()->json([
                'status' => 200,
                'tanggal' => $tanggal,
                'data' => $results,
                'checkInOut' => $resultCheckInOut,
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data izin getAllIzin'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data izin ',
                ],
                500,
            );
        }
    }

    public function getAllLembur()
    {
        try {
            $perPage = 7;
            $currentPage = request()->get('page', 1);
            $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            $data = DB::table('Transaksi_Lembur as a')
                ->join('Transaksi_Lembur_Detail as b', 'a.No_Transaksi', '=', 'b.No_Transaksi')
                ->where('b.Kode_Karyawan', $Kode_Karyawan)
                ->whereNull('a.Status')
                ->orderBy('a.Tanggal')

                ->get();

            $tanggal_Lembur = $data->pluck('Tanggal_Lembur_Dari');
            // dd($data);
            // Handle case where no overtime data is found
            if ($tanggal_Lembur->isEmpty()) {
                // Log::channel('uDashLog')->error('Gagal Mengambil data lembur getAllLembur'. $e->getMessage());
                return response()->json([
                    'status' => 200,
                    'data' => [],
                ]);
            }

            $startWeek = Carbon::parse($tanggal_Lembur->first())->format('Y-m-d');
            $endWeek = Carbon::parse($tanggal_Lembur->last())->format('Y-m-d');

            $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
            $absen_data = collect(DB::select($query));
            // dd($absen_data);
            $RawData = $data->map(function ($lembur) use ($absen_data) {
                $tanggalLembur = Carbon::parse($lembur->Tanggal_Lembur_Dari)->format('Y-m-d');

                $absenMatchCheckIn = collect($absen_data)
                    ->filter(function ($item) use ($tanggalLembur) {
                        return Carbon::parse($item->Tanggal)->format('Y-m-d') === $tanggalLembur &&
                            ! is_null($item->CheckIn);
                    })
                    ->isNotEmpty();

                $absenMatchCheckOut = collect($absen_data)
                    ->filter(function ($item) use ($tanggalLembur) {
                        return Carbon::parse($item->Tanggal)->format('Y-m-d') === $tanggalLembur &&
                            ! is_null($item->CheckOut);
                    })
                    ->isNotEmpty();

                $Status_Lembur = 'Active';
                if ($absenMatchCheckIn && $absenMatchCheckOut) {
                    $Status_Lembur = 'Selesai';
                }

                $jamAktual =
                    collect($absen_data)
                        ->filter(function ($item) use ($tanggalLembur) {
                            return Carbon::parse($item->Tanggal)->format('Y-m-d') === $tanggalLembur;
                        })
                        ->first()?->CheckOut ?? Carbon::parse($lembur->Tanggal_Lembur_Sampai);

                $durasi = floor(
                    Carbon::parse($lembur->Tanggal_Lembur_Dari)->diffInMinutes(Carbon::parse($jamAktual)) / 60,
                );

                return [
                    'Tanggal_Lembur_Dari' => $lembur->Tanggal_Lembur_Dari,
                    'Tanggal_Lembur_Sampai' => $lembur->Tanggal_Lembur_Sampai,
                    'JamAktual' => $jamAktual,
                    'durasi' => $durasi,
                    'Alasan' => $lembur->Alasan,
                    'Status' => $Status_Lembur,
                ];
            });

            // dd($RawData);

            $collection = collect($RawData)->sortByDesc('Tanggal_Lembur_Dari')->values();
            $slice_collect = $collection->slice(($currentPage - 1) * $perPage, $perPage)->values();

            $final_page = new LengthAwarePaginator($slice_collect, $collection->count(), $perPage, $currentPage, [
                'path' => request()->url(),
            ]);

            return response()->json($final_page);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data lembur getAllLembur'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data lembur',
                ],
                500,
            );
        }
    }

    // public function getChartData(Request $request){
    //     // dd($request->all());
    //     try {
    //         $title = 'uDash for You';

    //         // Ambil bulan & tahun dari request
    //         $input = $request->bulan . '-' . $request->tahun;
    //         $today = Carbon::today();

    //         // tanggal awal bulan input
    //         $inputDate = Carbon::createFromFormat('m-Y', $input)->day(1);

    //         if ($inputDate->month == $today->month && $inputDate->year == $today->year) {
    //             // bulan & tahun input adalah sekarang
    //             if ($today->day > 20) {
    //                 // sudah lewat tanggal 20 → cutoff bulan ini
    //                 $cutoffMonth = $inputDate->month;
    //                 $cutoffYear  = $inputDate->year;
    //             } else {
    //                 // masih <= 20 → cutoff bulan sebelumnya
    //                 $cutoffMonth = $inputDate->copy()->subMonthNoOverflow()->month;
    //                 $cutoffYear  = $inputDate->copy()->subMonthNoOverflow()->year;
    //             }
    //         } else {
    //             // bukan bulan sekarang → cutoff langsung pakai bulan input
    //             $cutoffMonth = $inputDate->month;
    //             $cutoffYear  = $inputDate->year;
    //         }

    //         // start = 21 cutoffMonth
    //         $startWeek = Carbon::createFromDate($cutoffYear, $cutoffMonth, 21);
    //         // end   = 20 bulan berikutnya
    //         $endWeek   = $startWeek->copy()->addMonthNoOverflow()->day(20);

    //         $UserID_Absen = Auth::user()->karyawan->UserID_Absen;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

    //         $hariLibur = DB::table('HRIS_Hari_Libur')
    //         ->whereBetween('Tanggal', [$startWeek, $endWeek])
    //         ->count();
    //         // dd($hariLibur);
    //         // Tentukan range berdasarkan jenis request
    //         // $startWeek = Carbon::create($tahun, $bulan, 21);
    //         // $endWeek   = Carbon::create($tahun, $bulan, 21)->addMonth()->subDay();
    //         // dd($startWeek, $endWeek);
    //         // --- Ambil data absen dari SP ---
    //         $query = "exec HRIS_SP_GET_ABSEN_NEW_V3s '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
    //         // dd($query->toSql());
    //         $absen_data = collect(DB::select($query));
    //         $tanggal_absens = $absen_data
    //             ->pluck('Tanggal')
    //             ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'));

    //         // Hitung jumlah CheckIn
    //         $jumlah_checkin = $absen_data->whereNotNull('CheckIn')->count();
    //         // dd($UserID_Absen);
    //         // 1. Lembur
    //         $lembur_data = DB::table('Transaksi_Lembur_Detail as b')
    //             ->join('Transaksi_Lembur as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Lembur_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Lembur_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 2. Sakit
    //         $sakit_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("Jenis", "sakit")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 3. Izin
    //         $izin_data = DB::table('Transaksi_Sakit_Izin_Detail as b')
    //             ->join('Transaksi_Sakit_Izin as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("Jenis", "izinFull")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where(function($query) use ($tanggal_absens) {
    //                 $query->whereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_dari AS DATE)'), $tanggal_absens)
    //                     ->orWhereIn(DB::raw('CAST(b.Tanggal_Sakit_Izin_Sampai AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 4. Cuti
    //         $cuti_data = DB::table('Transaksi_Cuti_Detail as b')
    //             ->join('Transaksi_Cuti as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->join('Transaksi_Cuti_Det as c', 'a.No_Transaksi', '=', 'c.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where('b.Flag_Approval', 'Y')
    //             ->where(function($query) use ($tanggal_absens) {
    //                 // $query->whereIn(DB::raw('CAST(b.Tanggal_Cuti_dari AS DATE)'), $tanggal_absens)
    //                 //     ->orWhereIn(DB::raw('CAST(b.Tanggal_Cuti_Sampai AS DATE)'), $tanggal_absens);

    //                 $query->whereIn(DB::raw('CAST(c.Tanggal_Cuti AS DATE)'), $tanggal_absens);
    //             })
    //             ->count();

    //         // 5. Pulang cepat
    //         $pulangCepat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //             ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "pulangCepat")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //             ->count();

    //         // 6. Terlambat
    //         $Terlambat_data = DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail as b')
    //             ->join('Transaksi_Terlambat_Pulang_Cepat as a', 'a.No_Transaksi', '=', 'b.No_Transaksi')
    //             ->where('b.Kode_Karyawan', $Kode_Karyawan)
    //             ->where("b.Jenis", "terlambat")
    //             ->where('b.Flag_Approval', 'Y')
    //             ->whereIn(DB::raw('CAST(b.Tanggal_Masuk_Pulang AS DATE)'), $tanggal_absens)
    //             ->count();

    //         $endDateForCalculation = Carbon::now()->min($endWeek);
    //         $totalHariKerjaYangBerlalu = $absen_data
    //         ->filter(function ($item) use ($endDateForCalculation) {
    //             return Carbon::parse($item->Tanggal)->lte($endDateForCalculation);
    //         })
    //         // Filter untuk mengecualikan hari libur atau hari tanpa jadwal kerja
    //         ->whereNotIn('Nama_Shift', ['LIBUR', 'NO SHIFT', 'HARI MINGGU']) // <-- SESUAIKAN INI
    //         ->count();

    //         $totalKehadiran = $jumlah_checkin  + $izin_data + $sakit_data + $cuti_data + $hariLibur;

    //         // dd($totalHariKerjaYangBerlalu, $jumlah_checkin);

    //             return response()->json([
    //                 'status' => 200,
    //                 'data' => [
    //                     'kehadiran'   => $jumlah_checkin,
    //                     'terlambat'   => $Terlambat_data,
    //                     'pulangCepat' => $pulangCepat_data,
    //                     'izin'        => $izin_data,
    //                     'sakit'       => $sakit_data,
    //                     'cuti'        => $cuti_data,
    //                     // kalau mau tambahkan lembur juga:
    //                     // 'lembur'      => $lembur_data,
    //                 ],
    //                 'summary' => [
    //                     'total' => $jumlah_checkin + $Terlambat_data + $pulangCepat_data + $izin_data + $sakit_data + $cuti_data,
    //                     'kehadiran_percent' => ($totalHariKerjaYangBerlalu > 0)
    //                         ? min(100, round(($totalKehadiran / $totalHariKerjaYangBerlalu) * 100))
    //                         : 0,

    //                 ]
    //             ]);

    //     } catch (\Throwable $e) {
    //         Log::channel('uDashLog')->error('Gagal Mengambil data chart getChartData'. $e->getMessage());
    //         return response()->json([
    //             'status' => 500,
    //             'error' => "Tidak bisa mengambil data chart"
    //         ], 500);
    //     }
    // }

    public function getChartData(Request $request)
    {
        try {
            $title = 'uDash for You';
            $user = Auth::user();
            $UserID_Absen = $user->karyawan->UserID_Absen;
            $Kode_Karyawan = $user->karyawan->Kode_Karyawan;

            // ---------------------------------------------------------
            // 1. LOGIKA CUTOFF (TETAP SAMA)
            // ---------------------------------------------------------
            $input = $request->bulan.'-'.$request->tahun;
            $today = Carbon::today();
            $inputDate = Carbon::createFromFormat('m-Y', $input)->day(1);

            if ($inputDate->month == $today->month && $inputDate->year == $today->year) {
                if ($today->day > 20) {
                    $cutoffMonth = $inputDate->month;
                    $cutoffYear = $inputDate->year;
                } else {
                    $cutoffMonth = $inputDate->copy()->subMonthNoOverflow()->month;
                    $cutoffYear = $inputDate->copy()->subMonthNoOverflow()->year;
                }
            } else {
                $cutoffMonth = $inputDate->month;
                $cutoffYear = $inputDate->year;
            }

            // start = 21 cutoffMonth
            $startWeek = Carbon::createFromDate($cutoffYear, $cutoffMonth, 21);
            // end   = 20 bulan berikutnya
            $endWeek = $startWeek->copy()->addMonthNoOverflow()->day(20);

            // ---------------------------------------------------------
            // 2. DATA PENDUKUNG (SP & LIBUR)
            // ---------------------------------------------------------

            // Hitung Hari Libur
            $hariLibur = DB::table('HRIS_Hari_Libur')
                ->whereBetween('Tanggal', [$startWeek, $endWeek])
                ->count();

            // Ambil data absen dari SP (Raw Data Fingerprint)
            // Penting: Kita butuh list tanggal dari SP untuk validasi transaksi nanti
            $query = "exec HRIS_SP_GET_ABSEN_NEW_V3 '001', '{$startWeek}', '{$endWeek}', '{$UserID_Absen}', '', ''";
            $absen_data = collect(DB::select($query));

            $tanggal_absens = $absen_data->pluck('Tanggal')->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'));

            // Hitung jumlah CheckIn
            $jumlah_checkin = $absen_data->whereNotNull('CheckIn')->count();

            // ---------------------------------------------------------
            // 3. OPTIMASI: AMBIL SEMUA TRANSAKSI DARI VIEW (1 QUERY)
            // ---------------------------------------------------------

            $allTransactions = DB::table('N_HRIS_VW_All_Transactions')
                ->where('Kode_Karyawan_Requester', $Kode_Karyawan)
                // Filter Data yang beririsan dengan range Cutoff
                ->where(function ($q) use ($startWeek, $endWeek) {
                    $q->whereDate('Tanggal_Mulai', '<=', $endWeek)->whereDate('Tanggal_Selesai', '>=', $startWeek);
                })
                // Filter hanya yang sudah diapprove
                ->where(function ($q) {
                    $q->where('Flag_Approval', 'Y')->orWhere('Flag_Approval', 'Y');
                })
                ->get();

            // ---------------------------------------------------------
            // 4. MAPPING & COUNTING DI PHP
            // ---------------------------------------------------------

            // Helper untuk menghitung transaksi yang valid
            // Syarat valid: Tipe cocok AND Tanggal transaksi ada di list $tanggal_absens (output SP)
            $countTx = function ($tipe, $subTipe = null) use ($allTransactions, $tanggal_absens) {
                return $allTransactions->sum(function ($tx) use ($tipe, $subTipe, $tanggal_absens) {
                    // 1. Cek Tipe
                    if (strtolower($tx->Tipe_Transaksi) !== strtolower($tipe)) {
                        return 0;
                    }

                    // 2. Cek Sub Tipe
                    if ($subTipe && strtolower($tx->Sub_Tipe) !== strtolower($subTipe)) {
                        return 0;
                    }

                    // 3. Ambil tanggal detail
                    $dataDetail = $this->getTanggal($tx->No_Transaksi);
                    if (empty($dataDetail) || ! isset($dataDetail['tanggal'])) {
                        return 0;
                    }

                    $hariValid = 0;

                    foreach ($dataDetail['tanggal'] as $tgl) {
                        $tglFormatted = Carbon::parse($tgl)->format('Y-m-d');
                        if ($tanggal_absens->contains($tglFormatted)) {
                            $hariValid++;
                        }
                    }

                    return $hariValid; // <--- ini disum oleh Collection::sum()
                });
            };

            // --- Eksekusi Counting ---

            // 1. Lembur
            $lembur_data = $countTx('Lembur');

            // 2. Sakit (Bisa di Tipe 'Sakit' atau Tipe 'Izin' dgn sub 'sakit')
            $sakit_data = $countTx('IZIN_SAKIT', 'sakit') + $countTx('Izin', 'sakit');

            // 3. Izin (izinFull)
            $izin_data = $countTx('Izin', 'izinFull') + $countTx('Sakit', 'izinFull');

            // 4. Cuti (Semua jenis Cuti)
            $cuti_data = $countTx('Cuti');

            // 5. Pulang Cepat
            $pulangCepat_data = $countTx('TERLAMBAT', 'pulangCepat');

            // 6. Terlambat
            $Terlambat_data = $countTx('TERLAMBAT', 'terlambat');

            // ---------------------------------------------------------
            // 5. KALKULASI PERSENTASE & RETURN (TETAP SAMA)
            // ---------------------------------------------------------

            $endDateForCalculation = Carbon::now()->min($endWeek);

            $totalHariKerjaYangBerlalu = $absen_data
                ->filter(function ($item) use ($endDateForCalculation) {
                    return Carbon::parse($item->Tanggal)->lte($endDateForCalculation);
                })
                ->whereNotIn('Nama_Shift', ['LIBUR', 'NO SHIFT', 'HARI MINGGU'])
                ->count();

            // Total kehadiran (Checkin + Izin + Sakit + Cuti + Libur)
            $totalKehadiran = $jumlah_checkin + $izin_data + $sakit_data + $cuti_data + $hariLibur;

            return response()->json([
                'status' => 200,
                'data' => [
                    'kehadiran' => $jumlah_checkin,
                    'terlambat' => $Terlambat_data,
                    'pulangCepat' => $pulangCepat_data,
                    'izin' => $izin_data,
                    'sakit' => $sakit_data,
                    'cuti' => $cuti_data,
                    // 'lembur'   => $lembur_data,
                ],
                'summary' => [
                    'total' => $jumlah_checkin + $Terlambat_data + $pulangCepat_data + $izin_data + $sakit_data + $cuti_data,
                    'kehadiran_percent' => $totalHariKerjaYangBerlalu > 0
                            ? min(100, round(($totalKehadiran / $totalHariKerjaYangBerlalu) * 100))
                            : 0,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::channel('uDashLog')->error('Gagal Mengambil data chart getChartData'.$e->getMessage());

            return response()->json(
                [
                    'status' => 500,
                    'error' => 'Tidak bisa mengambil data chart',
                ],
                500,
            );
        }
    }
}
