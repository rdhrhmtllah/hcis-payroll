<?php

namespace App\Http\Controllers\KPI\Planning\Scheduling;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\AuditTargetSebelumMulaiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class PenjadwalanKpiController extends Controller
{
    public function index()
    {
        return inertia('KPI/Planning/Scheduling/masterPenjadwalan');
    }

    public function getDataCurrent(Request $request)
    {
        try {
            $limit = (int) $request->query('limit', 10);
            $page = (int) $request->query('page', 1);
            $offset = ($page - 1) * $limit;
            $search = $request->query('q', null);

            // Tab daftar: 'aktif' (default) = jadwal belum diarsipkan, 'arsip' = terarsip.
            // Arsip hanya menyembunyikan dari daftar utama; barisnya tetap utuh di DB.
            $status = $request->query('status') === 'arsip' ? 'arsip' : 'aktif';
            $flagArsip = $status === 'arsip' ? 'Y' : 'T';

            $query = DB::table('KPI_Master_Penjadwalan')->where('Flag_Arsip', $flagArsip);

            if (! empty($search)) {
                // FORMAT(...) dan kolom tanggal/tahun/jam hanya menghasilkan angka,
                // titik dua, dan tanda hubung. Kata pencarian tanpa satu pun angka
                // MUSTAHIL cocok di sana, jadi klausanya dilewati — hasilnya persis
                // sama, tapi tidak memaksa FORMAT() dihitung untuk setiap baris.
                $adaAngka = (bool) preg_match('/\d/', $search);

                $query->where(function ($q) use ($search, $adaAngka) {
                    $q->where('Nama_Kegiatan', 'LIKE', "%{$search}%")->orWhere('Deskripsi', 'LIKE', "%{$search}%");

                    if ($adaAngka) {
                        $q->orWhere('Tahun', 'LIKE', "%{$search}%")
                            ->orWhere('Jam', 'LIKE', "%{$search}%")
                            ->orWhereRaw("FORMAT(Tanggal, 'dd-MM-yyyy') LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("FORMAT(Jadwal_Mulai, 'dd-MM-yyyy HH:mm') LIKE ?", ["%{$search}%"])
                            ->orWhereRaw("FORMAT(Jadwal_Akhir, 'dd-MM-yyyy HH:mm') LIKE ?", ["%{$search}%"]);
                    }
                });
            }

            $total = $query->count();

            // Hitungan untuk badge kedua tab (tidak terpengaruh pencarian/paginasi).
            $counts = DB::table('KPI_Master_Penjadwalan')
                ->selectRaw("SUM(CASE WHEN Flag_Arsip = 'Y' THEN 1 ELSE 0 END) AS arsip")
                ->selectRaw("SUM(CASE WHEN Flag_Arsip = 'Y' THEN 0 ELSE 1 END) AS aktif")
                ->first();

            $tabCounts = [
                'aktif' => (int) ($counts->aktif ?? 0),
                'arsip' => (int) ($counts->arsip ?? 0),
            ];

            $getData = $query
                ->offset($offset)
                ->limit($limit)
                ->orderBy('Id_Master_Penjadwalan', 'DESC')
                ->get();

            // Daftar kosong BUKAN kesalahan. Dulu dibalas 404, sehingga frontend
            // masuk cabang catch dan halaman terakhir pagination atau pencarian
            // tanpa hasil terlihat seperti gagal memuat, bukan "tidak ada data".
            // Tab kosong juga masuk ke sini, sehingga empty-state per tab bisa
            // dirender dengan tab_counts yang tetap terkirim.
            if ($getData->isEmpty()) {
                return ResponseHelper::successWithPagination(
                    [],
                    $page,
                    $limit,
                    $total,
                    $search ? 'Tidak ada jadwal yang cocok dengan pencarian.' : 'Belum ada jadwal.',
                    200,
                    ['tab_counts' => $tabCounts]
                );
            }

            $masterIds = $getData->pluck('Id_Master_Penjadwalan')->toArray();

            $pesertaData = DB::table('N_KPI_Master_Penjadwalan_Perorangan as kp')
                ->join('Karyawan as k', 'kp.Kode_Karyawan', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.ID_Level_Jabatan')
                ->whereIn('kp.Id_Master_Penjadwalan', $masterIds)
                ->select(
                    'kp.Id_Master_Penjadwalan_Perorangan',
                    'kp.Id_Master_Penjadwalan',
                    'kp.Kode_Karyawan',
                    'kp.Start_Periode',
                    'kp.Keterangan',
                    'kp.Flag_Aktif',
                    'k.Nama',
                    'vdsd.nama_sub_divisi',
                    'vgsglj.nama_level'
                )
                ->get()
                ->groupBy('Id_Master_Penjadwalan');

            $encodedData = $getData->map(function ($item) use ($pesertaData) {
                $originalId = $item->Id_Master_Penjadwalan;
                $item->Id_Master_Penjadwalan = Hashids::connection('custom')->encode($item->Id_Master_Penjadwalan);

                if (! empty($item->Id_User)) {
                    $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
                }

                $item->peserta = [];

                if ($item->Flag_Perorangan === 'Y' && isset($pesertaData[$originalId])) {
                    $item->peserta = $pesertaData[$originalId]->map(function ($p) {
                        return [
                            'Id_Perorangan' => Hashids::connection('custom')->encode($p->Id_Master_Penjadwalan_Perorangan),
                            'Kode_Karyawan' => $p->Kode_Karyawan,
                            'Nama_Karyawan' => $p->Nama,
                            'Jabatan' => $p->nama_level,
                            'Nama_Sub_Divisi' => $p->nama_sub_divisi,
                            'Periode' => $p->Start_Periode,
                            'Flag_Aktif' => $p->Flag_Aktif,
                            'Keterangan' => $p->Keterangan,
                            'Custom_Periode' => ! empty($p->Start_Periode) ? date('m', strtotime($p->Start_Periode.'-01')) : null,
                        ];
                    })->values()->toArray();
                }

                if (! empty($item->Periode)) {
                    $bulan = [
                        '01' => 'Januari',
                        '02' => 'Februari',
                        '03' => 'Maret',
                        '04' => 'April',
                        '05' => 'Mei',
                        '06' => 'Juni',
                        '07' => 'Juli',
                        '08' => 'Agustus',
                        '09' => 'September',
                        '10' => 'Oktober',
                        '11' => 'November',
                        '12' => 'Desember',
                    ];

                    $parts = explode('-', $item->Periode); // ["2025", "01"]
                    $item->periode_label = ($bulan[$parts[1]] ?? $parts[1]).' '.$parts[0];
                } else {
                    $item->periode_label = null;
                }

                return $item;
            });

            return ResponseHelper::successWithPagination(
                $encodedData,
                $page,
                $limit,
                $total,
                'Data Ditemukan',
                200,
                ['tab_counts' => $tabCounts]
            );
        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Audit MEMBACA SAJA: karyawan jalur tim yang punya baris target di bulan
     * sebelum bulan mulainya.
     *
     * Target bulan yang tak pernah dijalani ikut masuk penyebut YTD dan menekan
     * achievement tanpa sebab yang terlihat di layar mana pun. Endpoint ini hanya
     * memperlihatkan cakupannya; pembersihan dilakukan lewat
     * `php artisan kpi:rapikan-target-sebelum-mulai --terapkan`.
     */
    public function auditTargetSebelumMulai(Request $request, AuditTargetSebelumMulaiService $audit)
    {
        $request->validate([
            'kode' => 'nullable|string|max:50',
            'tahun' => 'nullable|digits:4',
        ]);

        try {
            $hasil = $audit->jalankan($request->query('kode'), $request->query('tahun'));

            $pesan = $hasil['ringkasan']['total_karyawan'] === 0
                ? 'Bersih: tidak ada target sebelum bulan mulai.'
                : "Ditemukan {$hasil['ringkasan']['total_baris']} baris pada {$hasil['ringkasan']['total_karyawan']} karyawan.";

            return ResponseHelper::success($hasil, $pesan, 200);
        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error('ERROR AUDIT TARGET: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getDataKaryawan(Request $request)
    {
        // Validasi DI LUAR try: ValidationException turunan \Exception, jadi kalau
        // dipanggil di dalamnya ia tertangkap catch di bawah dan input yang salah
        // dilaporkan sebagai 500, bukan 422.
        //
        // Wajib ketat: $periodeDb ikut menyusun klausa WHERE di subquery bawah.
        // Tanpa batasan tipe, isi request masuk ke SQL apa adanya (lihat komentar
        // pada selectRaw Is_Scheduled_Admin / Jadwal_Range).
        $request->validate([
            'periode' => 'required|integer|between:1,12',
            'tahun' => 'required|digits:4',
        ], [
            'periode.integer' => 'Periode harus berupa angka bulan 1-12.',
            'periode.between' => 'Periode harus berupa angka bulan 1-12.',
            'tahun.digits' => 'Tahun harus berupa 4 digit angka.',
        ]);

        try {
            $periodeDb = $request->tahun.'-'.str_pad($request->periode, 2, '0', STR_PAD_LEFT);
            $today = date('Y-m-d');

            $karyawanData = DB::table('Karyawan as k')
                // Dibatasi ke pengajuan TAHUN yang sedang dijadwalkan. Tanpa ini
                // pengajuan tahun lain ikut terjaring dan hasil dedup di bawah bisa
                // menampilkan No_KPI serta status milik tahun yang salah.
                ->join('KPI_Pengajuan as kp', function ($join) use ($request) {
                    $join->on('k.UserID_Web', '=', 'kp.Id_User')
                        ->where('kp.Flag_Aktif', 'Y')
                        // Parameter yang DIBATALKAN (Status='Y') tidak boleh membuat
                        // karyawan tampil beserta No_KPI-nya. Syaratnya sama dengan
                        // join kup di bawah: hidup = Flag_Aktif 'Y' DAN Status NULL.
                        ->whereNull('kp.Status')
                        ->whereExists(function ($sub) use ($request) {
                            $sub->select(DB::raw(1))
                                ->from('KPI_Master_Penjadwalan as mp')
                                ->whereColumn('mp.Id_Master_Penjadwalan', 'kp.Id_Penjadwalan')
                                ->where('mp.Tahun', $request->tahun);
                        });
                })
                ->leftJoin('KPI_Update_Performance as kup', function ($join) use ($periodeDb) {
                    $join->on('kp.Id_Pengajuan', '=', 'kup.Id_Pengajuan')
                        ->where('kup.Periode', $periodeDb)
                        ->where('kup.Flag_Aktif', 'Y')
                        // Header yang dibatalkan admin ditandai Status='Y' -> jangan dianggap "sudah update performance"
                        ->whereNull('kup.Status');
                })
                ->leftJoin('KPI_Master_Performance as kmp', 'kup.Id_Performance', '=', 'kmp.Id_Master_Performance')
                ->leftJoin('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_Divisi_Sub_Divisi')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj', 'k.Id_Level_Jabatan', '=', 'vgsglj.ID_Level_Jabatan')
                ->where('k.Aktif', 'Y')
                ->where(function ($q) use ($today) {
                    $q->whereNull('k.Tanggal_Resign')
                        ->orWhere('k.Tanggal_Resign', '>=', $today);
                })
                ->whereNotNull('k.UserID_Web')
                ->select(
                    'k.Kode_Karyawan',
                    'k.Nama',
                    'vdsd.nama_sub_divisi',
                    'vgsglj.nama_level',
                    'kp.Id_Pengajuan',
                    'kp.No_Transaksi as No_KPI',
                    // Penanda jalur ada di PENGAJUAN, bukan di realisasi: anggota boleh
                    // mengisi parameter tim lewat halaman individu tanpa cap Flag_Team,
                    // jadi Flag_Team pada KPI_Update_Performance tidak bisa dipercaya.
                    'kp.Flag_Team',
                    'kup.Id_Update_Performance',
                    'kup.Total_Score as Total_Score_Achievement',
                    'kup.Status_Pengajuan as Status_Approval',
                    'kup.Tanggal as Tanggal_Submit',
                    'kup.Jam as Jam_Submit',
                    'kmp.Keterangan as Performance_Keterangan',
                    'kmp.Warna as Performance_Warna',
                    DB::raw("
                        (
                            SELECT COUNT(1)
                            FROM KPI_Update_Performance_Detail
                            WHERE Id_Update_Performance = kup.Id_Update_Performance
                            AND Flag_Pica = 'Y'
                        ) as Pica_Count
                    ")
                )
                // $periodeDb DIIKAT sebagai parameter, bukan disisipkan ke string SQL.
                // Sebelumnya nilainya ditempel langsung ("... = '$periodeDb'"), sehingga
                // input seperti periode="06' OR 1=1 --" mematikan filter periode dan
                // membuat seluruh baris perorangan ikut terhitung.
                ->selectRaw("
                    (
                        SELECT COUNT(1)
                        FROM N_KPI_Master_Penjadwalan_Perorangan nk
                        JOIN KPI_Master_Penjadwalan km
                            ON km.Id_Master_Penjadwalan = nk.Id_Master_Penjadwalan
                        WHERE nk.Kode_Karyawan = k.Kode_Karyawan
                        AND nk.Flag_Aktif = 'Y'
                        AND km.Flag_Penilaian_KPI = 'Y'
                        AND km.Flag_Perorangan = 'Y'
                        AND km.Flag_Aktif = 'Y'
                        AND km.Periode = ?
                    ) as Is_Scheduled_Admin
                ", [$periodeDb])
                ->selectRaw("
                    (
                        SELECT TOP 1
                            CONVERT(varchar, km.Jadwal_Mulai, 23) + '|' +
                            CONVERT(varchar, km.Jadwal_Akhir, 23)
                        FROM N_KPI_Master_Penjadwalan_Perorangan nk
                        JOIN KPI_Master_Penjadwalan km
                            ON km.Id_Master_Penjadwalan = nk.Id_Master_Penjadwalan
                        WHERE nk.Kode_Karyawan = k.Kode_Karyawan
                        AND nk.Flag_Aktif = 'Y'
                        AND km.Flag_Penilaian_KPI = 'Y'
                        AND km.Flag_Perorangan = 'Y'
                        AND km.Flag_Aktif = 'Y'
                        AND km.Periode = ?
                    ) as Jadwal_Range
                ", [$periodeDb])
                ->orderBy('k.Nama', 'asc')
                ->get();

            // Satu karyawan bisa punya beberapa baris (fan-out JOIN kp/kup, dan bisa
            // punya pengajuan personal DAN tim sekaligus). Dirangkum per karyawan,
            // dengan status dipisah per jalur.
            $formattedData = $karyawanData
                ->groupBy('Kode_Karyawan')
                ->map(function ($baris) use ($periodeDb) {
                    $pertama = $baris->first();

                    $jalur = $baris
                        ->groupBy(function ($item) {
                            // 'T' maupun NULL sama-sama berarti bukan jalur tim.
                            return $item->Flag_Team === 'Y' ? 'tim' : 'personal';
                        })
                        ->map(function ($barisJalur, $namaJalur) {
                            // Ambil realisasi paling "maju" di jalur ini: disetujui
                            // mengalahkan menunggu, menunggu mengalahkan ditolak.
                            $urutan = ['disetujui' => 3, 'menunggu' => 2, 'ditolak' => 1];

                            $terpilih = $barisJalur
                                ->filter(fn ($i) => ! empty($i->Id_Update_Performance))
                                ->sortByDesc(fn ($i) => $urutan[strtolower((string) $i->Status_Approval)] ?? 0)
                                ->first();

                            $statusApproval = $terpilih->Status_Approval ?? null;

                            // "Ditolak" BUKAN selesai: leader masih harus memperbaikinya,
                            // jadi jendelanya justru perlu tetap/kembali dibuka. Sebelumnya
                            // baris ditolak ikut terhitung selesai dan mengunci karyawan.
                            $selesai = in_array(strtolower((string) $statusApproval), ['disetujui', 'menunggu'], true);

                            return [
                                'jalur' => $namaJalur,
                                'status_approval' => $statusApproval,
                                'status_kpi' => $selesai
                                    ? (strtolower((string) $statusApproval) === 'disetujui' ? 'Selesai' : 'Sedang Diproses')
                                    : ($statusApproval ? 'Perlu Perbaikan' : 'Belum Diisi'),
                                'selesai' => $selesai,
                                'no_kpi' => $barisJalur->first()->No_KPI,
                            ];
                        })
                        ->values();

                    // Terkunci hanya bila TIDAK ADA jalur yang masih menyisakan pekerjaan.
                    // Karyawan yang jalur personalnya sudah selesai tetapi jalur timnya
                    // belum harus tetap bisa dijadwalkan.
                    $isFilled = $jalur->isNotEmpty() && $jalur->every(fn ($j) => $j['selesai']);
                    $isScheduled = ! $isFilled && $pertama->Is_Scheduled_Admin > 0;

                    if ($isFilled) {
                        $status = 'Selesai';
                    } elseif ($isScheduled) {
                        $status = 'Sudah Dijadwalkan';
                    } elseif ($jalur->contains(fn ($j) => $j['status_kpi'] === 'Perlu Perbaikan')) {
                        $status = 'Perlu Perbaikan';
                    } else {
                        $status = 'Belum Dijadwalkan';
                    }

                    $scheduleRange = null;
                    if ($isScheduled && $pertama->Jadwal_Range) {
                        [$start, $end] = explode('|', $pertama->Jadwal_Range);
                        $scheduleRange = date('d M', strtotime($start)).' - '.date('d M Y', strtotime($end));
                    }

                    // Kolom ringkas tetap memakai baris yang paling informatif supaya
                    // tampilan lama tidak berubah artinya.
                    $sorot = $baris->first(fn ($i) => ! empty($i->Id_Update_Performance)) ?? $pertama;

                    return [
                        'kode_karyawan' => $pertama->Kode_Karyawan,
                        'nama_karyawan' => $pertama->Nama,
                        'nama_level' => $pertama->nama_level ?? '-',
                        'nama_divisi' => $pertama->nama_sub_divisi ?? '-',
                        'periode' => $periodeDb,
                        'status_kpi' => $status,
                        'is_filled' => $isFilled,
                        'is_scheduled_admin' => $isScheduled,
                        'is_locked' => $isFilled || $isScheduled,
                        'jalur' => $jalur,
                        'no_kpi' => $sorot->No_KPI,
                        'score_achievement' => $sorot->Total_Score_Achievement
                                                    ? (float) $sorot->Total_Score_Achievement
                                                    : 0,
                        'status_approval' => $sorot->Status_Approval ?? 'Pending',
                        'tanggal_submit' => $sorot->Tanggal_Submit
                                                    ? FormatTanggalHelper::formatDateTransaksi(
                                                        $sorot->Tanggal_Submit,
                                                        $sorot->Jam_Submit
                                                    )
                                                    : null,
                        'performance_keterangan' => $sorot->Performance_Keterangan ?? '-',
                        'performance_warna' => $sorot->Performance_Warna ?? '#000000',
                        'has_pica' => $sorot->Pica_Count > 0,
                        'jadwal_admin_range' => $scheduleRange,
                    ];
                })
                ->values();

            return ResponseHelper::success(
                $formattedData,
                "Berhasil mengambil data Update Performance periode $periodeDb"
            );
        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error($e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan: '.$e->getMessage(), 500);
        }
    }

    public function getDataKaryawanBelumAdaKpi(Request $request)
    {
        // Di luar try, alasan sama seperti getDataKaryawan: ValidationException yang
        // tertangkap catch generik akan dilaporkan sebagai 500 tanpa pesan bidangnya.
        $request->validate([
            'tahun' => 'required|numeric',
        ]);

        try {
            $today = date('Y-m-d');
            $tahun = $request->tahun;

            $karyawanData = DB::table('Karyawan as k')

                /**
                 * ============================
                 * JOIN KPI PENGAJUAN (FORM KPI SAJA)
                 * ============================
                 */
                ->leftJoin('KPI_Pengajuan as kp', function ($join) use ($tahun) {
                    $join->on('k.UserID_Web', '=', 'kp.Id_User')
                        // Pengajuan yang DIBATALKAN tidak boleh terhitung sudah
                        // mengisi. Pembatalan di Monitoring menulis Flag_Aktif='T'
                        // DAN Status='Y'; tanpa dua syarat ini, is_filled di bawah
                        // tetap true dan orangnya terkunci — padahal justru
                        // pembatalan itu yang dimaksudkan membebaskannya.
                        //
                        // Syaratnya SAMA dengan join kp di getDataKaryawan():
                        // hidup = Flag_Aktif 'Y' DAN Status NULL.
                        ->where('kp.Flag_Aktif', 'Y')
                        ->whereNull('kp.Status')
                        ->whereExists(function ($sub) use ($tahun) {
                            $sub->select(DB::raw(1))
                                ->from('KPI_Master_Penjadwalan as kmp')
                                ->whereColumn('kmp.Id_Master_Penjadwalan', 'kp.Id_Penjadwalan')
                                ->where('kmp.Tahun', $tahun)
                                ->where('kmp.Flag_Form_KPI', 'Y')
                                ->where('kmp.Flag_Aktif', 'Y');
                        });
                })

                /**
                 * ============================
                 * JOIN PENJADWALAN PERORANGAN (FORM KPI SAJA)
                 * ============================
                 */
                ->leftJoin('N_KPI_Master_Penjadwalan_Perorangan as nkmp', function ($join) use ($tahun) {
                    $join->on('k.Kode_Karyawan', '=', 'nkmp.Kode_Karyawan')
                        ->where('nkmp.Flag_Aktif', 'Y')
                        ->whereExists(function ($sub) use ($tahun) {
                            $sub->select(DB::raw(1))
                                ->from('KPI_Master_Penjadwalan as kmp')
                                ->whereColumn('kmp.Id_Master_Penjadwalan', 'nkmp.Id_Master_Penjadwalan')
                                ->where('kmp.Tahun', $tahun)
                                ->where('kmp.Flag_Form_KPI', 'Y')
                                ->where('kmp.Flag_Aktif', 'Y');
                        });
                })

                /**
                 * ============================
                 * JOIN MASTER JADWAL (UNTUK RANGE TANGGAL)
                 * ============================
                 */
                ->leftJoin(
                    'KPI_Master_Penjadwalan as kmp_existing',
                    'nkmp.Id_Master_Penjadwalan',
                    '=',
                    'kmp_existing.Id_Master_Penjadwalan'
                )

                /**
                 * ============================
                 * JOIN DATA PENDUKUNG
                 * ============================
                 */
                ->leftJoin(
                    'View_Divisi_Sub_Divisi as vdsd',
                    'k.ID_Divisi_Sub_Divisi',
                    '=',
                    'vdsd.ID_Divisi_Sub_Divisi'
                )
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vgsglj',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vgsglj.ID_Level_Jabatan'
                )

                /**
                 * ============================
                 * FILTER KARYAWAN AKTIF
                 * ============================
                 */
                ->where('k.Aktif', 'Y')
                ->where(function ($q) use ($today) {
                    $q->whereNull('k.Tanggal_Resign')
                        ->orWhere('k.Tanggal_Resign', '>=', $today);
                })
                ->whereNotNull('k.UserID_Web')

                /**
                 * ============================
                 * SELECT
                 * ============================
                 */
                ->select(
                    'k.Kode_Karyawan',
                    'k.Tgl_Masuk',
                    'k.Nama',
                    'vdsd.nama_sub_divisi',
                    'vgsglj.nama_level',

                    'kp.Id_Pengajuan',
                    'kp.No_Transaksi as No_KPI',
                    'kp.Tanggal as Tanggal_Submit',
                    'kp.Jam as Jam_Submit',

                    'nkmp.Keterangan as Keterangan_Jadwal',
                    'nkmp.Start_Periode',

                    'kmp_existing.Jadwal_Mulai',
                    'kmp_existing.Jadwal_Akhir'
                )

                /**
                 * ============================
                 * SORTING
                 * ============================
                 */
                ->orderByRaw('CASE WHEN kp.Id_Pengajuan IS NULL THEN 0 ELSE 1 END ASC')
                ->orderBy('k.Nama', 'ASC')
                ->get();

            /**
             * ============================
             * FORMAT RESPONSE
             * ============================
             */
            $formattedData = $karyawanData->map(function ($item) {
                $isFilled = ! empty($item->Id_Pengajuan);
                $isScheduled = ! empty($item->Keterangan_Jadwal);

                $scheduleRange = null;
                if ($isScheduled && $item->Jadwal_Mulai && $item->Jadwal_Akhir) {
                    $scheduleRange =
                        date('d M Y', strtotime($item->Jadwal_Mulai)).
                        ' - '.
                        date('d M Y', strtotime($item->Jadwal_Akhir));
                }

                return [
                    'kode_karyawan' => $item->Kode_Karyawan,
                    'Tgl_Join' => $item->Tgl_Masuk,
                    'nama_karyawan' => $item->Nama,
                    'nama_level' => $item->nama_level ?? '-',
                    'nama_divisi' => $item->nama_sub_divisi ?? '-',

                    'status_kpi' => $isFilled
                        ? 'Sudah Mengisi Pengajuan'
                        : 'Belum Mengisi Pengajuan',

                    'is_filled' => $isFilled,
                    'is_scheduled' => $isScheduled,
                    'keterangan_jadwal' => $item->Keterangan_Jadwal,
                    'start_periode' => $item->Start_Periode,
                    'jadwal_range' => $scheduleRange,
                    'no_kpi' => $item->No_KPI ?? '-',

                    'tanggal_submit' => $item->Tanggal_Submit
                        ? FormatTanggalHelper::formatDateTransaksi(
                            $item->Tanggal_Submit,
                            $item->Jam_Submit
                        )
                        : null,
                ];
            })
            // Cegah karyawan tampil ganda akibat fan-out JOIN (kp/nkmp bisa >1 baris
            // per karyawan). Utamakan baris yang sudah mengisi / terjadwal agar
            // info tidak hilang setelah dedup.
                ->groupBy('kode_karyawan')
                ->map(function ($group) {
                    return $group->firstWhere('is_filled', true)
                        ?? $group->firstWhere('is_scheduled', true)
                        ?? $group->first();
                })
                ->values();

            return ResponseHelper::success(
                $formattedData,
                'Berhasil mengambil data pengajuan KPI karyawan tahun '.$tahun
            );

        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error($e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan', 500);
        }
    }

    public function store(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;

        $request->validate([
            '*.Nama_Kegiatan' => 'required|string|max:255',
            '*.Deskripsi' => 'required|string',
            '*.Jadwal_Mulai' => 'required|date',
            '*.Jadwal_Akhir' => 'required|date|after_or_equal:*.Jadwal_Mulai',
            // Hanya dua jenis yang benar-benar dibaca modul lain: 'pengisian'
            // (Flag_Form_KPI) dan 'penilaian' (Flag_Penilaian_KPI).
            //
            // 'update' dan 'verifikasi' dihapus dari daftar: Flag_Update_KPI dan
            // Flag_Batas_Verifikasi ditulis ke database tapi tidak dibaca kode mana
            // pun, dan 'update' bahkan tidak pernah ditawarkan di dropdown. Kolomnya
            // dibiarkan (tidak perlu migrasi) dan baris payload di bawah tetap ada
            // supaya jelas apa yang harus dipulihkan bila fiturnya dibangun.
            //
            // Aman dihapus: 0 baris memakai kedua flag itu saat perubahan ini dibuat.
            '*.Jenis_Kegiatan' => 'required|in:pengisian,penilaian',
            '*.Tahun' => 'required|digits:4',
            '*.Periode' => 'nullable|integer|between:1,12',
            '*.Flag_Perorangan' => 'nullable|in:Y,T',
            '*.Peserta' => 'nullable|array',
        ], [
            '*.Jadwal_Akhir.after_or_equal' => 'Jadwal Akhir tidak boleh lebih kecil dari Jadwal Mulai.',
            '*.Periode.between' => 'Periode harus berupa angka bulan 1-12.',
        ]);

        $allData = $request->all();

        DB::beginTransaction();

        try {
            foreach ($allData as $data) {
                $flagPerorangan = isset($data['Flag_Perorangan']) ? $data['Flag_Perorangan'] : 'T';
                $tahun = $data['Tahun'];
                $jenis = $data['Jenis_Kegiatan'];

                $periodeMaster = null;
                if ($flagPerorangan === 'T' || $jenis === 'penilaian') {
                    // Periode WAJIB di jalur ini. Tanpa penjagaan ini str_pad(null)
                    // menghasilkan bulan '00' yang tidak sah, mis. '2026-00'.
                    if (! isset($data['Periode']) || $data['Periode'] === '') {
                        DB::rollBack();

                        return ResponseHelper::error(
                            "Periode wajib diisi untuk '{$data['Nama_Kegiatan']}'.",
                            422,
                        );
                    }

                    $periodeMaster = $tahun.'-'.str_pad($data['Periode'], 2, '0', STR_PAD_LEFT);
                }

                // Jadwal terarsip tidak lagi memblokir nama kegiatan yang sama —
                // membebaskan nama adalah bagian dari nilai fitur arsip.
                $queryCek = DB::table('KPI_Master_Penjadwalan')
                    ->where('Nama_Kegiatan', $data['Nama_Kegiatan'])
                    ->where('Tahun', $tahun)
                    ->where('Flag_Arsip', 'T');

                if ($periodeMaster === null) {
                    $queryCek->whereNull('Periode');
                } else {
                    $queryCek->where('Periode', $periodeMaster);
                }

                if ($queryCek->exists()) {
                    DB::rollBack();
                    $msgPeriode = $periodeMaster ? $periodeMaster : 'Perorangan (Multi Periode)';

                    return ResponseHelper::error("Data '{$data['Nama_Kegiatan']}' untuk periode {$msgPeriode} sudah ada.", 409);
                }

                $alasan = isset($data['Alasan_Pembukaan']) ? $data['Alasan_Pembukaan'] : '-';

                if ($flagPerorangan === 'Y') {
                    $alasanClean = str_replace(' ', '', $alasan);
                    if (strlen($alasanClean) < 10) {
                        DB::rollBack();

                        return ResponseHelper::error('Alasan pembukaan wajib diisi minimal 10 karakter (huruf/angka) tanpa menghitung spasi.', 422);
                    }
                }

                $payload = [
                    'Nama_Kegiatan' => $data['Nama_Kegiatan'],
                    'Deskripsi' => $data['Deskripsi'],
                    'Jadwal_Mulai' => date('Y-m-d H:i:s', strtotime($data['Jadwal_Mulai'])),
                    'Jadwal_Akhir' => date('Y-m-d H:i:s', strtotime($data['Jadwal_Akhir'])),
                    'Tahun' => $tahun,
                    'Periode' => $periodeMaster,
                    'Flag_Perorangan' => $flagPerorangan,
                    'Id_User' => $pengguna,
                    'Jam' => date('H:i:s'),
                    'Tanggal' => date('Y-m-d'),
                    'Flag_Form_KPI' => $jenis === 'pengisian' ? 'Y' : null,
                    'Flag_Update_KPI' => $jenis === 'update' ? 'Y' : null,
                    'Flag_Penilaian_KPI' => $jenis === 'penilaian' ? 'Y' : null,
                    'Flag_Batas_Verifikasi' => $jenis === 'verifikasi' ? 'Y' : null,
                    'Flag_Aktif' => 'Y',
                ];

                $idMaster = DB::table('KPI_Master_Penjadwalan')->insertGetId($payload);

                if ($flagPerorangan === 'Y' && ! empty($data['Peserta'])) {
                    $detailPayload = [];

                    foreach ($data['Peserta'] as $pesertaItem) {
                        $kodeKaryawan = null;
                        $periodeDetailRaw = null;

                        if (is_array($pesertaItem)) {
                            $kodeKaryawan = $pesertaItem['Kode_Karyawan'];
                            $periodeDetailRaw = isset($pesertaItem['Custom_Periode']) ? $pesertaItem['Custom_Periode'] : null;
                        } else {
                            $kodeKaryawan = $pesertaItem;
                            $periodeDetailRaw = isset($data['Periode']) ? $data['Periode'] : null;
                        }

                        $startPeriodeValue = null;
                        if ($jenis === 'pengisian' && $periodeDetailRaw) {
                            $startPeriodeValue = $tahun.'-'.str_pad($periodeDetailRaw, 2, '0', STR_PAD_LEFT);
                        }

                        $detailPayload[] = [
                            'Id_Master_Penjadwalan' => $idMaster,
                            'Kode_Karyawan' => $kodeKaryawan,
                            'Keterangan' => $alasan,
                            'Start_Periode' => $startPeriodeValue,
                            'Flag_Aktif' => 'Y',
                        ];
                    }

                    if (count($detailPayload) > 0) {
                        DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert($detailPayload);
                    }
                }
            }
            DB::commit();

            return ResponseHelper::success(null, 'Semua data berhasil disimpan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('PenjadwalanKpiController')->error('Error STORE: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $Id_Master_Penjadwalan = Hashids::connection('custom')->decode($id)[0];
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'Format Kunci Tidak Valid.',
            ], 400);
        }

        // Validasi payload didahulukan sebelum menyentuh DB, mengikuti urutan
        // FormRequest Laravel: kiriman yang bentuknya salah ditolak 422 tanpa
        // perlu query, dan tidak membocorkan ada/tidaknya sebuah jadwal.
        $request->validate([
            'Nama_Kegiatan' => 'required',
            'Deskripsi' => 'required',
            'Jadwal_Mulai' => 'required|date',
            'Jadwal_Akhir' => 'required|date|after_or_equal:Jadwal_Mulai',
            // Disamakan dgn store(): tanpa daftar ini, jenis di luar pilihan yang sah
            // membuat semua flag bernilai NULL sehingga jadwal tetap tampil aktif tapi
            // tidak dibaca modul mana pun. Alasan 'update'/'verifikasi' tidak ada di
            // daftar: lihat komentar pada store().
            'Jenis_Kegiatan' => 'required|in:pengisian,penilaian',
            'Tahun' => 'required|digits:4',
            'Periode' => 'nullable|integer|between:1,12',
            'Flag_Perorangan' => 'nullable|in:Y,T',
            'Peserta' => 'nullable|array',
        ]);

        // Jadwal terarsip bersifat read-only. UI sudah menyembunyikan tombol Edit;
        // guard ini menutup jalur request langsung ke endpoint.
        $jadwalTarget = DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $Id_Master_Penjadwalan)
            ->first();

        if (! $jadwalTarget) {
            return ResponseHelper::error('Data penjadwalan tidak ditemukan.', 404);
        }

        if ($jadwalTarget->Flag_Arsip === 'Y') {
            return ResponseHelper::error('Jadwal yang sudah diarsipkan tidak bisa diubah. Pulihkan dulu dari arsip.', 422);
        }

        $flagPerorangan = $request->Flag_Perorangan ?? 'T';
        $tahun = $request->Tahun;
        $jenis = $request->Jenis_Kegiatan;

        $periodeMaster = null;
        if ($flagPerorangan === 'T' || $jenis === 'penilaian') {
            // Periode WAJIB di jalur ini. Tanpa penjagaan ini str_pad(null) menghasilkan
            // bulan '00' yang tidak sah dan lolos tersimpan sebagai mis. '2026-00'.
            if ($request->Periode === null || $request->Periode === '') {
                return ResponseHelper::error('Periode wajib diisi untuk jenis kegiatan ini.', 422);
            }

            $periodeMaster = $tahun.'-'.str_pad($request->Periode, 2, '0', STR_PAD_LEFT);
        }

        $queryCek = DB::table('KPI_Master_Penjadwalan')
            ->where('Nama_Kegiatan', $request->Nama_Kegiatan)
            ->where('Tahun', $tahun)
            ->where('Flag_Arsip', 'T')
            ->where('Id_Master_Penjadwalan', '!=', $Id_Master_Penjadwalan);

        if ($periodeMaster === null) {
            $queryCek->whereNull('Periode');
        } else {
            $queryCek->where('Periode', $periodeMaster);
        }

        if ($queryCek->exists()) {
            return ResponseHelper::error('Data sudah ada untuk kegiatan ini di periode tersebut.', 409);
        }

        $alasan = $request->Alasan_Pembukaan ?? '-';

        if ($flagPerorangan === 'Y') {
            $alasanClean = str_replace(' ', '', $alasan);
            if (strlen($alasanClean) < 10) {
                return ResponseHelper::error('Alasan pembukaan wajib diisi minimal 10 karakter (huruf/angka) tanpa menghitung spasi.', 422);
            }
        }

        $pengguna = Auth::user()->Id_Users;

        DB::beginTransaction();

        try {
            $payload = [
                'Nama_Kegiatan' => $request->Nama_Kegiatan,
                'Deskripsi' => $request->Deskripsi,
                'Jadwal_Mulai' => date('Y-m-d H:i:s', strtotime($request->Jadwal_Mulai)),
                'Jadwal_Akhir' => date('Y-m-d H:i:s', strtotime($request->Jadwal_Akhir)),
                'Tahun' => $tahun,
                'Periode' => $periodeMaster,
                'Flag_Perorangan' => $flagPerorangan,
                'Id_User' => $pengguna,
                'Jam' => date('H:i:s'),
                'Tanggal' => date('Y-m-d'),
                'Flag_Form_KPI' => $jenis === 'pengisian' ? 'Y' : null,
                'Flag_Update_KPI' => $jenis === 'update' ? 'Y' : null,
                'Flag_Penilaian_KPI' => $jenis === 'penilaian' ? 'Y' : null,
                'Flag_Batas_Verifikasi' => $jenis === 'verifikasi' ? 'Y' : null,
            ];

            DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $Id_Master_Penjadwalan)
                ->update($payload);

            // Peserta di-UPSERT, bukan dihapus lalu disisipkan ulang.
            //
            // Cara lama menulis 'Flag_Aktif' => 'Y' untuk semua baris, sehingga hasil
            // toggleSwitchPeserta hilang setiap kali jadwal disunting — mengubah
            // tanggal saja mengaktifkan kembali peserta yang sengaja dinonaktifkan.
            // Baris yang tidak lagi dikirim tetap dihapus, karena itu memang tindakan
            // sadar admin (mencabut centang), bukan efek samping.
            $detailPayload = [];

            if ($flagPerorangan === 'Y' && ! empty($request->Peserta)) {
                foreach ($request->Peserta as $pesertaItem) {
                    $kodeKaryawan = null;
                    $periodeDetailRaw = null;

                    if (is_array($pesertaItem)) {
                        $kodeKaryawan = $pesertaItem['Kode_Karyawan'];
                        $periodeDetailRaw = isset($pesertaItem['Custom_Periode']) ? $pesertaItem['Custom_Periode'] : null;
                    } else {
                        $kodeKaryawan = $pesertaItem;
                        $periodeDetailRaw = $request->Periode;
                    }

                    $startPeriodeValue = null;
                    if ($jenis === 'pengisian' && $periodeDetailRaw) {
                        $startPeriodeValue = $tahun.'-'.str_pad($periodeDetailRaw, 2, '0', STR_PAD_LEFT);
                    }

                    $detailPayload[$kodeKaryawan] = [
                        'Id_Master_Penjadwalan' => $Id_Master_Penjadwalan,
                        'Kode_Karyawan' => $kodeKaryawan,
                        'Keterangan' => $alasan,
                        'Start_Periode' => $startPeriodeValue,
                    ];
                }
            }

            $pesertaLama = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                ->where('Id_Master_Penjadwalan', $Id_Master_Penjadwalan)
                ->get()
                ->keyBy('Kode_Karyawan');

            // Yang tidak lagi dikirim: dibuang. Array kosong membuat whereNotIn
            // selalu benar, jadi peralihan ke jadwal global membersihkan semuanya.
            DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                ->where('Id_Master_Penjadwalan', $Id_Master_Penjadwalan)
                ->whereNotIn('Kode_Karyawan', array_keys($detailPayload))
                ->delete();

            foreach ($detailPayload as $kodeKaryawan => $baris) {
                $lama = $pesertaLama->get($kodeKaryawan);

                if ($lama) {
                    // Flag_Aktif sengaja TIDAK ikut diperbarui.
                    DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                        ->where('Id_Master_Penjadwalan_Perorangan', $lama->Id_Master_Penjadwalan_Perorangan)
                        ->update([
                            'Keterangan' => $baris['Keterangan'],
                            'Start_Periode' => $baris['Start_Periode'],
                        ]);

                    continue;
                }

                DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert(
                    $baris + ['Flag_Aktif' => 'Y'],
                );
            }

            DB::commit();

            return ResponseHelper::success(null, 'Data Berhasil Diperbaharui', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('PenjadwalanKpiController')->error('Error UPDATE: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    public function toggleSwitch(Request $request)
    {
        $request->validate(['id' => 'required']);

        try {
            // Decode ID yang di-hash
            $decodedId = Hashids::connection('custom')->decode($request->id);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID Penjadwalan tidak valid.', 400);
            }
            $id = $decodedId[0];

            // Cari data penjadwalan
            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id)
                ->first();

            if (! $jadwal) {
                return ResponseHelper::error('Data penjadwalan tidak ditemukan.', 404);
            }

            // Cegah jadwal terarsip diam-diam menjadi aktif dan bocor ke modul hilir.
            if ($jadwal->Flag_Arsip === 'Y') {
                return ResponseHelper::error('Pulihkan jadwal dari arsip sebelum mengubah statusnya.', 422);
            }

            // Tentukan status baru (toggle Y/N)
            $newStatus = ($jadwal->Flag_Aktif === 'Y') ? 'T' : 'Y';

            // Update database
            DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id)
                ->update(['Flag_Aktif' => $newStatus]);

            return ResponseHelper::success(
                ['new_status' => $newStatus],
                'Status jadwal berhasil diubah.'
            );

        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error('ERROR TOGGLESWITCH: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat mengubah status.', 500);
        }
    }

    public function toggleSwitchPeserta(Request $request)
    {
        $request->validate(['id' => 'required']);

        try {
            $decodedId = Hashids::connection('custom')->decode($request->id);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID Peserta tidak valid.', 400);
            }
            $id = $decodedId[0];

            $peserta = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                ->where('Id_Master_Penjadwalan_Perorangan', $id)
                ->first();

            if (! $peserta) {
                return ResponseHelper::error('Data peserta tidak ditemukan.', 404);
            }

            $newStatus = ($peserta->Flag_Aktif === 'Y') ? 'T' : 'Y';

            DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                ->where('Id_Master_Penjadwalan_Perorangan', $id)
                ->update(['Flag_Aktif' => $newStatus]);

            return ResponseHelper::success(
                ['new_status' => $newStatus],
                'Status peserta berhasil diubah.'
            );

        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error('ERROR TOGGLESWITCH PESERTA: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat mengubah status peserta.', 500);
        }
    }

    /**
     * Arsipkan jadwal: singkirkan dari daftar utama tanpa menghapus datanya.
     * Hanya boleh untuk jadwal yang sudah dinonaktifkan (Flag_Aktif = 'T'),
     * sehingga modul hilir yang menyaring Flag_Aktif = 'Y' tidak terpengaruh.
     */
    public function arsipkan(Request $request)
    {
        $request->validate(['id' => 'required']);

        try {
            $decodedId = Hashids::connection('custom')->decode($request->id);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID Penjadwalan tidak valid.', 400);
            }
            $id = $decodedId[0];

            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id)
                ->first();

            if (! $jadwal) {
                return ResponseHelper::error('Data penjadwalan tidak ditemukan.', 404);
            }

            if ($jadwal->Flag_Arsip === 'Y') {
                return ResponseHelper::error('Jadwal ini sudah berada di arsip.', 422);
            }

            if ($jadwal->Flag_Aktif !== 'T') {
                return ResponseHelper::error('Hanya jadwal yang sudah dinonaktifkan yang bisa diarsipkan.', 422);
            }

            DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id)
                ->update(['Flag_Arsip' => 'Y']);

            return ResponseHelper::success(['Flag_Arsip' => 'Y'], 'Jadwal berhasil diarsipkan.');
        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error('ERROR ARSIPKAN: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat mengarsipkan jadwal.', 500);
        }
    }

    /**
     * Pulihkan jadwal dari arsip. Jadwal kembali sebagai Non-Aktif — pengaktifan
     * kembali tetap keputusan admin lewat toggle biasa.
     */
    public function pulihkan(Request $request)
    {
        $request->validate(['id' => 'required']);

        try {
            $decodedId = Hashids::connection('custom')->decode($request->id);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID Penjadwalan tidak valid.', 400);
            }
            $id = $decodedId[0];

            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id)
                ->first();

            if (! $jadwal) {
                return ResponseHelper::error('Data penjadwalan tidak ditemukan.', 404);
            }

            if ($jadwal->Flag_Arsip !== 'Y') {
                return ResponseHelper::error('Jadwal ini tidak berada di arsip.', 422);
            }

            DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id)
                ->update([
                    'Flag_Arsip' => 'T',
                    'Flag_Aktif' => 'T',
                ]);

            return ResponseHelper::success(
                ['Flag_Arsip' => 'T', 'Flag_Aktif' => 'T'],
                'Jadwal berhasil dipulihkan dan berstatus Non-Aktif.'
            );
        } catch (\Exception $e) {
            Log::channel('PenjadwalanKpiController')->error('ERROR PULIHKAN: '.$e->getMessage());

            return ResponseHelper::error('Terjadi kesalahan saat memulihkan jadwal.', 500);
        }
    }
}
