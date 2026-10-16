<?php

namespace App\Http\Controllers\KPI\Performance\Update;

use App\Helpers\FormatTanggalHelper;
use App\Helpers\ResponseHelper;
use App\Helpers\Whatsapp;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\KpiEnforcementService;
use App\Services\KPI\KpiPicaIsi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

class UpdatePerformanceController extends Controller
{
    /**
     * Nilai Metode_Cap untuk record yang difinalisasi dengan cap PER PARAMETER
     * (berlaku sejak 28 Agustus 2026). Record lama ber-'TOTAL'/NULL dihitung ulang
     * ke metode ini oleh KpiCapParameterBackfillService (12 Okt 2026).
     */
    public const METODE_CAP_PARAMETER = 'PARAMETER';

    private function DateCarbon()
    {
        $Tanggal = FormatTanggalHelper::getCurrentTime();
        if ($Tanggal) {
            return Carbon::parse($Tanggal);
        }

        return Carbon::now('Asia/Jakarta');
    }

    private function DateCarbon2()
    {
        $Tanggal = FormatTanggalHelper::getCurrentTime2();
        if ($Tanggal) {
            return Carbon::parse($Tanggal);
        }

        return Carbon::now('Asia/Jakarta');
    }

    public function getDynamicPeriod($nik = null)
    {
        $now = Carbon::now('Asia/Jakarta');

        if ($nik) {
            $userPeriod = DB::table('N_KPI_Master_Penjadwalan_Perorangan as detail')
                ->join(
                    'KPI_Master_Penjadwalan as master',
                    'detail.Id_Master_Penjadwalan',
                    '=',
                    'master.Id_Master_Penjadwalan',
                )
                ->where('detail.Kode_Karyawan', $nik)
                ->where('detail.Flag_Aktif', 'Y')
                ->where('master.Flag_Aktif', 'Y')
                ->where('master.Flag_Perorangan', 'Y')
                ->where('master.Flag_Penilaian_KPI', 'Y')
                ->where('master.Jadwal_Mulai', '<=', $now)
                ->where('master.Jadwal_Akhir', '>=', $now)
                ->orderBy('master.Id_Master_Penjadwalan', 'DESC')
                ->select('master.Periode')
                ->first();

            if ($userPeriod && ! empty($userPeriod->Periode)) {
                return Carbon::parse($userPeriod->Periode);
            }
        }

        $kpiGlobal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->orderBy('Id_Master_Penjadwalan', 'DESC')
            ->first();

        if ($kpiGlobal && ! empty($kpiGlobal->Periode)) {
            return Carbon::parse($kpiGlobal->Periode);
        }

        return null;
    }

    /**
     * Resolve periode dari IdMasterPenjadwalan hash string.
     *
     * Secara baku jadwal harus masih berjalan, sama seperti syarat di jalur simpan —
     * kalau tidak, halaman bisa dibuka tetapi tombol simpan pasti gagal. Jalur by-pass
     * memanggil dengan $harusAktif = false karena memang alat override admin yang
     * jalur simpannya juga tidak terikat rentang jadwal.
     *
     * Return Carbon atau null jika gagal/tidak ada.
     */
    private function resolvePeriodFromScheduleHash(?string $hash, bool $harusAktif = true): ?\Carbon\Carbon
    {
        if (! $hash) {
            return null;
        }

        try {
            $decodedId = Hashids::connection('custom')->decode($hash)[0] ?? null;
            if (! $decodedId) {
                return null;
            }

            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $decodedId)
                ->when($harusAktif, function ($query) {
                    $now = Carbon::now('Asia/Jakarta');

                    $query
                        ->where('Flag_Aktif', 'Y')
                        ->where('Flag_Penilaian_KPI', 'Y')
                        ->where('Jadwal_Mulai', '<=', $now)
                        ->where('Jadwal_Akhir', '>=', $now);
                })
                ->first();

            return $jadwal && ! empty($jadwal->Periode) ? Carbon::parse($jadwal->Periode) : null;
        } catch (\Exception $e) {
            /*
             * null di sini terbaca "tidak ada penjadwalan penilaian yang sedang
             * berjalan" — jawaban yang sah dan sering benar. Masalahnya, kueri
             * yang GAGAL menghasilkan null yang sama persis, sehingga halaman
             * Update Performance tampak tutup padahal sebenarnya rusak. Nilai
             * baliknya tidak diubah; yang ditambahkan hanya cara membedakan
             * keduanya setelah kejadian.
             */
            Log::error('Gagal menentukan periode penilaian aktif', [
                'kelas' => $e::class,
                'pesan' => $e->getMessage(),
                'berkas' => $e->getFile().':'.$e->getLine(),
                'sql' => $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : null,
            ]);

            return null;
        }
    }

    /**
     * Ambil SEMUA jadwal aktif (Perorangan + Global) untuk user tertentu,
     * diurutkan berdasarkan Periode ASC (terlama dulu → kronologis).
     *
     * @param  string|null  $nik  Kode_Karyawan
     * @param  Carbon  $now  Waktu sekarang
     * @return \Illuminate\Support\Collection
     */
    private function getUserActiveSchedules(?string $nik, Carbon $now)
    {
        $schedules = collect();

        // 1. Jadwal Perorangan untuk user ini
        if ($nik) {
            $personalSchedules = DB::table('KPI_Master_Penjadwalan as m')
                ->join(
                    'N_KPI_Master_Penjadwalan_Perorangan as p',
                    'm.Id_Master_Penjadwalan',
                    '=',
                    'p.Id_Master_Penjadwalan',
                )
                ->where('p.Kode_Karyawan', $nik)
                ->where('p.Flag_Aktif', 'Y')
                ->where('m.Flag_Aktif', 'Y')
                ->where('m.Flag_Perorangan', 'Y')
                ->where('m.Flag_Penilaian_KPI', 'Y')
                ->where('m.Jadwal_Mulai', '<=', $now)
                ->where('m.Jadwal_Akhir', '>=', $now)
                ->select('m.*')
                ->get();

            $schedules = $schedules->merge($personalSchedules);
        }

        // 2. Jadwal Global
        $globalSchedules = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->get();

        $schedules = $schedules->merge($globalSchedules);

        // Urutkan berdasarkan Periode ASC (terlama dulu)
        return $schedules
            ->unique('Id_Master_Penjadwalan')
            ->sortBy(function ($item) {
                return Carbon::parse($item->Periode)->timestamp;
            })
            ->values();
    }

    /**
     * Daftar periode (format Y-m) yang sudah tidak perlu diisi lagi oleh leader, yaitu yang
     * punya pengajuan hidup (Flag_Aktif = 'Y') berstatus menunggu approval atau sudah
     * disetujui. Pengajuan yang dibatalkan maupun riwayat lama yang sudah dinonaktifkan
     * tidak dihitung, dan yang ditolak sengaja dianggap belum selesai karena masih
     * bisa diperbaiki.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getPeriodeTerkunciUser($idUser)
    {
        return DB::table('KPI_Update_Performance')
            ->where('Id_User', $idUser)
            ->where('Flag_Aktif', 'Y')
            ->select('Periode', 'Status_Pengajuan', 'Flag_Approval')
            ->get()
            ->filter(function ($row) {
                return $row->Status_Pengajuan === 'menunggu' || $row->Flag_Approval === 'Y';
            })
            ->map(function ($row) {
                return Carbon::parse($row->Periode)->format('Y-m');
            })
            ->unique()
            ->values();
    }

    public function index()
    {
        $idUser = Auth::user()->Id_Users;
        $karyawan = DB::table('Karyawan')->where('UserID_WEb', $idUser)->first();

        if (! $karyawan) {
            abort(403, 'Data Karyawan tidak ditemukan');
        }

        $kodeKaryawan = $karyawan->Kode_Karyawan;
        $now = Carbon::now();

        $jadwalPerorangan = DB::table('KPI_Master_Penjadwalan as m')
            ->join(
                'N_KPI_Master_Penjadwalan_Perorangan as p',
                'm.Id_Master_Penjadwalan',
                '=',
                'p.Id_Master_Penjadwalan',
            )
            ->where('p.Kode_Karyawan', $kodeKaryawan)
            ->where('p.Flag_Aktif', 'Y')
            ->where('m.Flag_Penilaian_KPI', 'Y')
            ->where('m.Flag_Perorangan', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->select('m.*')
            ->get();

        $activePerorangan = $jadwalPerorangan->first(function ($item) use ($now) {
            $mulai = Carbon::parse($item->Jadwal_Mulai);
            $akhir = Carbon::parse($item->Jadwal_Akhir);

            return $now->between($mulai, $akhir);
        });

        $jadwalGlobal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Perorangan', 'T')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->orderBy('Jadwal_Mulai', 'asc')
            ->get();

        $activeGlobal = $jadwalGlobal->first(function ($item) use ($now) {
            $mulai = Carbon::parse($item->Jadwal_Mulai);
            $akhir = Carbon::parse($item->Jadwal_Akhir);

            return $now->between($mulai, $akhir);
        });

        // Periode yang SUDAH punya pengajuan hidup milik orang ini tidak boleh
        // dibuka begitu saja.
        //
        // Halaman tim sudah lama melewati periode terkunci (lihat
        // showUpdatePerformanceByTeam), halaman pribadi tidak — jadi seseorang
        // bisa mengisi seluruh formulir untuk periode yang sudah ia selesaikan,
        // dan baru ditolak di langkah TERAKHIR oleh assertBelumAdaRealisasiHidup:
        // "Periode 2026-05 sudah memiliki update performance yang aktif". Semua
        // pekerjaannya sudah telanjur dikerjakan saat penolakan itu muncul.
        //
        // Terjadi pada A4: enam periode berturut-turut (2026-01..2026-06) sudah
        // berstatus 'disetujui', tetapi jadwal yang berjalan menunjuk 2026-05.
        $periodeTerkunci = $this->getPeriodeTerkunciUser($idUser);

        // SELURUH jadwal yang sedang berjalan, bukan hanya satu perorangan + satu
        // global. Memilih dari dua kandidat saja membuat "lewati periode terkunci"
        // gagal begitu ada beberapa jadwal global berjalan bersamaan — yang
        // pertama kebetulan terkunci, dan sisanya tak pernah dilihat.
        $jadwalBerjalan = $jadwalPerorangan
            ->filter(fn ($item) => $now->between(Carbon::parse($item->Jadwal_Mulai), Carbon::parse($item->Jadwal_Akhir)))
            ->merge(
                $jadwalGlobal->filter(
                    fn ($item) => $now->between(Carbon::parse($item->Jadwal_Mulai), Carbon::parse($item->Jadwal_Akhir)),
                ),
            )
            ->unique('Id_Master_Penjadwalan')
            ->values();

        // Periode tanpa target jatuh tempo tidak membuka formulir. Dulu formulirnya
        // tetap dibuka dengan seluruh parameter nonaktif, dan orangnya bisa lanjut
        // serta menyimpan draft kosong.
        [$jadwalBerjalan, $jadwalTanpaTarget] = $jadwalBerjalan->partition(
            fn ($item) => $this->punyaTargetPeriode([(int) $idUser], $item->Periode)[(int) $idUser],
        );
        $jadwalBerjalan = $jadwalBerjalan->values();

        if ($jadwalBerjalan->isEmpty() && $jadwalTanpaTarget->isNotEmpty()) {
            $contoh = $jadwalTanpaTarget->first();

            return inertia('KPI/components/ui/statusPage', [
                'status' => 'tanpa_target',
                'jadwalMulai' => $contoh->Jadwal_Mulai,
                'jadwalAkhir' => $contoh->Jadwal_Akhir,
                'title' => 'Tidak Ada Target Periode Ini',
                'description' => $this->pesanTanpaTarget($contoh->Periode),
            ])->withViewData([
                'layout' => 'layouts.master-withoutnavbar',
            ]);
        }

        $jadwalValid = $jadwalBerjalan->first(function ($item) use ($periodeTerkunci) {
            return empty($item->Periode)
                || ! $periodeTerkunci->contains(Carbon::parse($item->Periode)->format('Y-m'));
        }) ?? $jadwalBerjalan->first();

        if ($jadwalValid) {
            // Bila seluruh jadwal yang berjalan ternyata terkunci, halamannya tetap
            // dibuka — riwayat dan alur persetujuannya masih perlu dilihat — tetapi
            // keadaannya dikatakan di muka, bukan disimpan sampai tombol terakhir.
            $terkunci = ! empty($jadwalValid->Periode)
                && $periodeTerkunci->contains(Carbon::parse($jadwalValid->Periode)->format('Y-m'));

            // Status baris yang mengunci ikut dibawa. Tanpa itu layar hanya bisa
            // bicara satu nada — "batalkan yang lama" — padahal keadaan yang paling
            // sering terjadi justru keadaan yang SEHAT: pengajuannya baru dikirim
            // dan sedang menunggu persetujuan. Menyuruh membatalkan di situ salah
            // arah, dan membuat proses normal terbaca seperti kegagalan.
            $pengajuanTerkunci = null;
            $statusTerkunci = null;
            if ($terkunci) {
                $baris = DB::table('KPI_Update_Performance')
                    ->where('Id_User', $idUser)
                    ->where('Periode', 'like', Carbon::parse($jadwalValid->Periode)->format('Y-m').'%')
                    ->where('Flag_Aktif', 'Y')
                    ->whereNull('Status')
                    ->orderByDesc('Id_Update_Performance')
                    ->first(['No_Transaksi', 'Status_Pengajuan']);

                $pengajuanTerkunci = $baris->No_Transaksi ?? null;
                $statusTerkunci = $baris->Status_Pengajuan ?? null;
            }

            return inertia('KPI/Performance/Update/updatePerformance', [
                'jadwalMulai' => $jadwalValid->Jadwal_Mulai,
                'jadwalAkhir' => $jadwalValid->Jadwal_Akhir,
                'IdMasterPenjadwalan' => Hashids::connection('custom')->encode($jadwalValid->Id_Master_Penjadwalan),
                'periodeTerkunci' => $terkunci,
                'periodeTerkunciLabel' => $terkunci ? Carbon::parse($jadwalValid->Periode)->format('Y-m') : null,
                'noTransaksiTerkunci' => $pengajuanTerkunci,
                'statusTerkunci' => $statusTerkunci,
            ]);
        }

        $futurePerorangan = $jadwalPerorangan->first(function ($item) use ($now) {
            return Carbon::parse($item->Jadwal_Mulai)->gt($now);
        });

        $futureGlobal = $jadwalGlobal->first(function ($item) use ($now) {
            return Carbon::parse($item->Jadwal_Mulai)->gt($now);
        });

        $jadwalMendatang = null;
        if ($futurePerorangan && $futureGlobal) {
            $jadwalMendatang =
                $futurePerorangan->Jadwal_Mulai < $futureGlobal->Jadwal_Mulai ? $futurePerorangan : $futureGlobal;
        } elseif ($futurePerorangan) {
            $jadwalMendatang = $futurePerorangan;
        } else {
            $jadwalMendatang = $futureGlobal;
        }

        if ($jadwalMendatang) {
            return inertia('KPI/components/ui/statusPage', [
                'status' => 'menunggu',
                'jadwalMulai' => $jadwalMendatang->Jadwal_Mulai,
                'jadwalAkhir' => $jadwalMendatang->Jadwal_Akhir,
                'title' => 'COMING SOON',
                'description' => 'Halaman update performance akan segera aktif secara otomatis. Silahkan hubungi HC jika ada kendala.',
            ])->withViewData([
                'layout' => 'layouts.master-withoutnavbar',
            ]);
        }

        $allSchedules = $jadwalPerorangan->merge($jadwalGlobal);
        $jadwalTerakhir = $allSchedules->sortByDesc('Jadwal_Akhir')->first();

        return inertia('KPI/components/ui/statusPage', [
            'status' => 'close',
            'jadwalMulai' => $jadwalTerakhir?->Jadwal_Mulai,
            'jadwalAkhir' => $jadwalTerakhir?->Jadwal_Akhir,
            'title' => 'Periode Update Performance Telah Berakhir',
            'description' => 'Periode pengisian dan update performance untuk jadwal ini telah ditutup. Silahkan hubungi HC jika ada kendala.',
        ])->withViewData([
            'layout' => 'layouts.master-withoutnavbar',
        ]);
    }

    public function indexUpdatePerformanceTeam()
    {
        $idUser = Auth::user()->Id_Users;
        $karyawan = DB::table('Karyawan')->where('UserID_WEb', $idUser)->first();

        if (! $karyawan) {
            abort(403, 'Data Karyawan tidak ditemukan');
        }

        // Halaman ini TIDAK lagi digerbangi jadwal milik leader.
        //
        // Dulu kelayakannya dinilai dari jadwal penilaian leader sendiri, lalu
        // seluruh halaman ditutup dengan "Periode Penilaian Team Telah Berakhir"
        // bila jadwal itu lewat. Itu keliru dua kali:
        //
        //   1. Leader di sini adalah PENILAI, bukan yang dinilai. Jadwal yang
        //      menentukan apakah sebuah penilaian boleh diisi adalah jadwal
        //      ANGGOTA, dan itu sudah divalidasi per anggota di dalam tab
        //      To Update (buildToUpdatePairs).
        //   2. Tab On Going dan Returned sengaja tidak memeriksa jadwal sama
        //      sekali — approval yang menunggu dan penolakan yang harus
        //      diperbaiki tidak boleh ikut terkunci hanya karena sebuah jadwal
        //      lewat. Gerbang lama menutup ketiganya sekaligus.
        //
        // Nyata terjadi di data dev: leader H45 terkunci total padahal 9
        // anggotanya punya jadwal perorangan yang sedang aktif.
        //
        // Yang tersisa hanyalah pemeriksaan kepemilikan tim, dan gerbang itu harus
        // memakai definisi UNION dari seluruh tab yang ada di dalamnya. Dulu ia
        // hanya menerima "punya pengajuan KPI yang saya kirim dan sudah disetujui"
        // — definisi milik tab To Update. Sejak ada tab "Belum Parameter" yang
        // justru bersumber dari roster KPI_Karyawan_Team, gerbang itu menutup
        // halaman tepat pada orang yang paling membutuhkannya: leader yang sudah
        // punya anggota tetapi belum satu pun parameternya disetujui.
        //
        // Nyata terjadi: leader A4 (Aji) punya satu anggota di roster (E14, Dhea)
        // dan nol pengajuan disetujui, lalu dikunci pesan "Belum Ada Anggota Tim"
        // — padahal anggotanya ada, dan tab yang dibuat untuk menampilkannya ada
        // di balik pintu yang sama.
        $punyaTim = $this->anggotaTimSaya($idUser)->isNotEmpty();

        if (! $punyaTim) {
            return inertia('KPI/components/ui/statusPage', [
                'status' => 'close',
                'jadwalMulai' => null,
                'jadwalAkhir' => null,
                'title' => 'Belum Ada Anggota Tim',
                'description' => 'Anda belum memiliki anggota tim dengan parameter KPI yang sudah disetujui. '.
                    'Isi dan ajukan parameter KPI anggota terlebih dahulu melalui menu Form KPI Team.',
            ])->withViewData([
                'layout' => 'layouts.master-withoutnavbar',
            ]);
        }

        return inertia('KPI/Performance/Update/team/HomeTeamPerformance');
    }

    public function indexUpdatePerformanceByPass()
    {
        $getData = DB::table('KPI_Get_Date_Dummy2')->where('Flag_Aktif', 'Y')->first();

        if (! $getData) {
            abort(403, 'Akses tidak diizinkan');
        }

        return inertia('KPI/Performance/Update/team/HomeTeamPerformanceBaypass');
    }

    public function showUpdatePerformanceByTeam($user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $now = Carbon::now();

        // Jika schedule hash dikirim via query param, gunakan jadwal tersebut — tetapi
        // hanya bila jadwalnya masih berjalan, sama seperti syarat di jalur simpan.
        // Jadwal yang sudah tutup dibiarkan jatuh ke halaman status di bawah.
        $scheduleHash = request()->query('schedule');
        $jadwalFromParam = null;
        if ($scheduleHash) {
            try {
                $decodedScheduleId = Hashids::connection('custom')->decode($scheduleHash)[0] ?? null;
                if ($decodedScheduleId) {
                    $jadwalFromParam = DB::table('KPI_Master_Penjadwalan')
                        ->where('Id_Master_Penjadwalan', $decodedScheduleId)
                        ->where('Flag_Aktif', 'Y')
                        ->where('Flag_Penilaian_KPI', 'Y')
                        ->where('Jadwal_Mulai', '<=', $now)
                        ->where('Jadwal_Akhir', '>=', $now)
                        ->first();
                }
            } catch (\Exception $e) {
                $jadwalFromParam = null;
            }
        }

        // Fallback: cek jadwal user ini (perorangan + global, diurutkan Periode ASC)
        $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();
        $nik = $karyawan->Kode_Karyawan ?? null;
        $allSchedules = $this->getUserActiveSchedules($nik, $now);

        // Default: jadwal TERLAMA yang belum selesai (menunggu approval / sudah disetujui),
        // supaya pengisian susulan berjalan runut. Param schedule tetap menang.
        $jadwalDefault = null;
        if ($allSchedules->isNotEmpty()) {
            $periodeTerkunci = $this->getPeriodeTerkunciUser($Id_User);
            // Periode bertarget didahulukan; tanpa satu pun, penjaga di bawah
            // menampilkan status "Tidak Ada Target".
            $bertarget = $allSchedules->filter(
                fn ($item) => $this->punyaTargetPeriode([(int) $Id_User], $item->Periode)[(int) $Id_User],
            );
            $kandidat = $bertarget->isNotEmpty() ? $bertarget : $allSchedules;

            $jadwalDefault =
                $kandidat->first(function ($item) use ($periodeTerkunci) {
                    return ! $periodeTerkunci->contains(Carbon::parse($item->Periode)->format('Y-m'));
                }) ?? $kandidat->first();
        }

        $jadwalValid = $jadwalFromParam ?? $jadwalDefault;

        if ($jadwalValid && ! $this->punyaTargetPeriode([(int) $Id_User], $jadwalValid->Periode)[(int) $Id_User]) {
            return inertia('KPI/components/ui/statusPage', [
                'status' => 'tanpa_target',
                'jadwalMulai' => $jadwalValid->Jadwal_Mulai,
                'jadwalAkhir' => $jadwalValid->Jadwal_Akhir,
                'title' => 'Tidak Ada Target Periode Ini',
                'description' => $this->pesanTanpaTarget($jadwalValid->Periode),
            ])->withViewData([
                'layout' => 'layouts.master-withoutnavbar',
            ]);
        }

        if ($jadwalValid) {
            $getNamaKandidat = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

            return inertia('KPI/Performance/Update/team/updatePerformance', [
                'jadwalMulai' => $jadwalValid->Jadwal_Mulai,
                'jadwalAkhir' => $jadwalValid->Jadwal_Akhir,
                'Id_User' => $user_id,
                'nama_kandidat' => $getNamaKandidat->Nama ?? '-',
                'IdMasterPenjadwalan' => Hashids::connection('custom')->encode($jadwalValid->Id_Master_Penjadwalan),
            ]);
        }

        // Tidak ada jadwal aktif: ambil seluruh jadwal user (tanpa batas rentang waktu)
        // untuk menentukan halaman status "menunggu jadwal" atau "sudah tutup".
        $jadwalPerorangan = collect();
        if ($nik) {
            $jadwalPerorangan = DB::table('KPI_Master_Penjadwalan as m')
                ->join(
                    'N_KPI_Master_Penjadwalan_Perorangan as p',
                    'm.Id_Master_Penjadwalan',
                    '=',
                    'p.Id_Master_Penjadwalan',
                )
                ->where('p.Kode_Karyawan', $nik)
                ->where('p.Flag_Aktif', 'Y')
                ->where('m.Flag_Aktif', 'Y')
                ->where('m.Flag_Perorangan', 'Y')
                ->where('m.Flag_Penilaian_KPI', 'Y')
                ->select('m.*')
                ->get();
        }

        $jadwalGlobal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where(function ($query) {
                $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->get();

        $getDataJadwal = $jadwalPerorangan->merge($jadwalGlobal)->unique('Id_Master_Penjadwalan');

        $jadwalMendatang = $getDataJadwal
            ->filter(function ($item) use ($now) {
                return Carbon::parse($item->Jadwal_Mulai)->gt($now);
            })
            ->sortBy('Jadwal_Mulai')
            ->first();

        if ($jadwalMendatang) {
            return inertia('KPI/components/ui/statusPage', [
                'status' => 'menunggu',
                'jadwalMulai' => $jadwalMendatang->Jadwal_Mulai,
                'jadwalAkhir' => $jadwalMendatang->Jadwal_Akhir,
            ])->withViewData([
                'layout' => 'layouts.master-withoutnavbar',
            ]);
        }

        $jadwalTerakhir = $getDataJadwal->sortByDesc('Jadwal_Akhir')->first();

        return inertia('KPI/components/ui/statusPage', [
            'status' => 'close',
            'jadwalMulai' => $jadwalTerakhir?->Jadwal_Mulai,
            'jadwalAkhir' => $jadwalTerakhir?->Jadwal_Akhir,
        ])->withViewData([
            'layout' => 'layouts.master-withoutnavbar',
        ]);
    }

    public function showUpdatePerformanceByPass($user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        // Jadwal yang diklik dari daftar (mis. baris periode tertentu yang ditolak)
        // menang atas tanggal acuan by-pass yang berlaku global.
        $jadwalDariParam = null;
        $scheduleHash = request()->query('schedule');

        if ($scheduleHash) {
            try {
                $decodedScheduleId = Hashids::connection('custom')->decode($scheduleHash)[0] ?? null;
                if ($decodedScheduleId) {
                    $jadwalDariParam = DB::table('KPI_Master_Penjadwalan')
                        ->where('Id_Master_Penjadwalan', $decodedScheduleId)
                        ->first();
                }
            } catch (\Exception $e) {
                $jadwalDariParam = null;
            }
        }

        // Periode yang dipilih operator di dropdown daftar by-pass.
        //
        // Daftarnya berbasis BULAN sedangkan halaman ini berbasis JADWAL, jadi
        // tanpa jembatan ini pilihan operator hilang di tengah jalan: baris
        // muncul karena April belum terisi, lalu yang terbuka justru Mei —
        // periode acuan global dari KPI_Get_Date_Dummy2.
        //
        // ?schedule= tetap menang bila dikirim: itu penunjukan yang lebih
        // spesifik (satu baris jadwal tertentu), sedangkan periode bisa
        // dipenuhi lebih dari satu jadwal.
        if (! $jadwalDariParam) {
            $periodeParam = trim((string) request()->query('periode'));

            if ($periodeParam !== '' && preg_match('/^\d{4}-\d{2}$/', $periodeParam)) {
                $jadwalDariParam = DB::table('KPI_Master_Penjadwalan')
                    ->where('Periode', $periodeParam)
                    ->where('Flag_Penilaian_KPI', 'Y')
                    // Yang aktif didahulukan; bila semuanya nonaktif, ambil yang
                    // terbaru daripada gagal — by-pass memang jalur darurat.
                    ->orderByRaw("CASE WHEN Flag_Aktif = 'Y' THEN 0 ELSE 1 END")
                    ->orderByDesc('Id_Master_Penjadwalan')
                    ->first();

                if (! $jadwalDariParam) {
                    // Sengaja berhenti, bukan diam-diam mundur ke periode acuan.
                    // Membuka bulan yang berbeda dari yang diminta persis itulah
                    // bug yang sedang diperbaiki di sini.
                    abort(404, 'Belum ada jadwal penilaian untuk periode '.$periodeParam.'.');
                }
            }
        }

        $getDataJadwal = DB::table('KPI_Get_Date_Dummy2')->where('Flag_Aktif', 'Y')->first();

        if ($jadwalDariParam) {
            $bypassPenjadwalanId = $jadwalDariParam->Id_Master_Penjadwalan;
        } elseif (! empty($getDataJadwal->Id_Master_Penjadwalan)) {
            // Acuan by-pass memang menunjuk satu jadwal tertentu.
            $bypassPenjadwalanId = $getDataJadwal->Id_Master_Penjadwalan;
        } else {
            // Tidak ada jadwal yang dipilih: turunkan dari PERIODE acuan, bukan
            // dari tahun berjalan. Memakai tahun berjalan membuat periode 2025
            // yang sengaja dipilih membuka jadwal 2026.
            $periodeAcuan = ! empty($getDataJadwal->Tanggal)
                ? Carbon::parse($getDataJadwal->Tanggal)
                : Carbon::now('Asia/Jakarta');

            $fallbackJadwal = $this->jadwalPenilaianUntukPeriode($periodeAcuan);

            if (! $fallbackJadwal) {
                abort(404, 'Belum ada jadwal penilaian untuk periode '.$periodeAcuan->format('Y-m').'.');
            }

            $bypassPenjadwalanId = $fallbackJadwal->Id_Master_Penjadwalan;
        }

        // By-pass boleh di luar jendela jadwal, tetapi periode tanpa target jatuh
        // tempo tetap tidak membuka formulir — sama dengan halaman pribadi & tim.
        $jadwalBypass = DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $bypassPenjadwalanId)
            ->first(['Periode', 'Jadwal_Mulai', 'Jadwal_Akhir']);

        if ($jadwalBypass && ! $this->punyaTargetPeriode([(int) $Id_User], $jadwalBypass->Periode)[(int) $Id_User]) {
            return inertia('KPI/components/ui/statusPage', [
                'status' => 'tanpa_target',
                'jadwalMulai' => $jadwalBypass->Jadwal_Mulai,
                'jadwalAkhir' => $jadwalBypass->Jadwal_Akhir,
                'title' => 'Tidak Ada Target Periode Ini',
                'description' => $this->pesanTanpaTarget($jadwalBypass->Periode),
            ]);
        }

        $getNamaKandidat = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

        return inertia('KPI/Performance/Update/team/updatePerformanceByPass', [
            'Id_User' => $user_id,
            'nama_kandidat' => $getNamaKandidat->Nama ?? '-',
            'IdMasterPenjadwalan' => Hashids::connection('custom')->encode($bypassPenjadwalanId),
        ]);
    }

    public function getDataKpiItems(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;
        $karyawan = DB::table('Karyawan')->where('UserID_WEb', $pengguna)->first();

        if (! $karyawan) {
            abort(403, 'Data Karyawan tidak ditemukan');
        }

        // Jadwal yang dikirim halaman selalu menang; getDynamicPeriod hanya cadangan
        // agar frontend lama yang belum mengirim parameter tetap berjalan.
        $nik = $karyawan->Kode_Karyawan ?? null;
        $targetDate =
            $this->resolvePeriodFromScheduleHash($request->query('Id_Master_Penjadwalan')) ??
            $this->getDynamicPeriod($nik);

        if (! $targetDate) {
            return ResponseHelper::success(
                [
                    'header' => [
                        'periode' => '-',
                        'tahun' => date('Y'),
                        'bulan' => date('m'),
                    ],
                    'details' => [],
                ],
                200,
            );
        }

        $yearTarget = $targetDate->year;

        $kpiByUser = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('p.Id_User', $pengguna)
            ->where('j.Tahun', $yearTarget)
            ->whereNull('p.Status')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->select('p.*')
            ->orderBy(DB::raw('CAST(p.Tanggal + p.Jam AS DATETIME)'), 'DESC')
            ->first();

        if (! $kpiByUser) {
            return ResponseHelper::error('Data Pengajuan KPI tidak ditemukan', 404);
        }

        $mainKpis = DB::table('KPI_Pengajuan_Detail')
            ->join(
                'KPI_Master_Bsc_Kategori',
                'KPI_Pengajuan_Detail.Id_Bsc_Kategori',
                '=',
                'KPI_Master_Bsc_Kategori.Id_Master_Bsc_Kategori',
            )
            ->join(
                'KPI_Master_Formula',
                'KPI_Pengajuan_Detail.Id_Master_Formula',
                '=',
                'KPI_Master_Formula.Id_Master_Formula',
            )
            ->join(
                'KPI_Master_Satuan',
                'KPI_Pengajuan_Detail.Id_Master_Satuan',
                '=',
                'KPI_Master_Satuan.Id_Master_Satuan',
            )
            ->join(
                'KPI_Master_Target',
                'KPI_Pengajuan_Detail.Id_Master_Target',
                '=',
                'KPI_Master_Target.Id_Master_Target',
            )
            ->where('KPI_Pengajuan_Detail.Id_Pengajuan', $kpiByUser->Id_Pengajuan)
            ->select(
                'KPI_Pengajuan_Detail.*',
                'KPI_Master_Bsc_Kategori.Nama_Kategori',
                'KPI_Master_Formula.Rumus',
                'KPI_Master_Formula.Min_Range',
                'KPI_Master_Formula.Max_Range',
                'KPI_Master_Satuan.Simbol_Satuan',
                'KPI_Master_Target.Nama_Target AS Tipe_Target',
                'KPI_Master_Target.Kode_Target',
            )
            ->get();

        $allPeriodDetails = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $mainKpis->pluck('Id_Pengajuan_Detail'))
            ->get()
            ->groupBy('Id_Pengajuan_Detail');

        $result = $mainKpis
            ->map(function ($kpi) use ($allPeriodDetails, $targetDate) {
                $detailsForThisKpi = $allPeriodDetails->get($kpi->Id_Pengajuan_Detail);
                if (! $detailsForThisKpi) {
                    return null;
                }

                $relevantDetail = null;
                $isInputPeriod = false;
                $kodeTarget = $kpi->Kode_Target;

                foreach ($detailsForThisKpi as $detail) {
                    $periodeDate = Carbon::parse($detail->Periode);
                    if ($periodeDate->format('Y-m') === $targetDate->format('Y-m')) {
                        $relevantDetail = $detail;
                        $isInputPeriod = true;
                        break;
                    }
                }
                if (! $relevantDetail) {
                    foreach ($detailsForThisKpi as $detail) {
                        $periodeDate = Carbon::parse($detail->Periode);
                        $isCurrent = false;

                        if ($kodeTarget === 'M') {
                            if ($targetDate->isSameMonth($periodeDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'Y') {
                            if ($targetDate->isSameYear($periodeDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'Q') {
                            $startDate = $periodeDate->copy()->subMonths(2)->startOfMonth();
                            $endDate = $periodeDate->copy()->endOfMonth();
                            if ($targetDate->between($startDate, $endDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'S') {
                            $startDate = $periodeDate->copy()->subMonths(5)->startOfMonth();
                            $endDate = $periodeDate->copy()->endOfMonth();
                            if ($targetDate->between($startDate, $endDate)) {
                                $isCurrent = true;
                            }
                        }
                        if ($isCurrent) {
                            $relevantDetail = $detail;
                            break;
                        }
                    }
                }

                if (! $relevantDetail) {
                    // Tidak ada target untuk periode ini -> tetap tampilkan parameter dalam
                    // keadaan NONAKTIF (belum dimulai / sudah berakhir) memakai target
                    // terdekat. Sebelumnya parameter di-drop sehingga hilang dari form.
                    $relevantDetail = $this->pickFallbackPeriodDetail($detailsForThisKpi, $targetDate);
                    $isInputPeriod = false;
                }

                if (! $relevantDetail) {
                    return null; // benar-benar tak ada target sama sekali (anomali data)
                }

                $periodName = '';
                if ($kodeTarget === 'M') {
                    $periodName = Carbon::parse($relevantDetail->Periode)->locale('id')->isoFormat('MMMM YYYY');
                } elseif ($kodeTarget === 'Q') {
                    $periodName =
                        'Kuartal '.
                        strtoupper($relevantDetail->Nama_Target).
                        ' '.
                        Carbon::parse($relevantDetail->Periode)->year;
                } elseif ($kodeTarget === 'Y') {
                    $periodName = 'Akhir Tahun '.Carbon::parse($relevantDetail->Periode)->year;
                } elseif ($kodeTarget === 'S') {
                    $periodName =
                        'Semester '.
                        strtoupper($relevantDetail->Nama_Target).
                        ' '.
                        Carbon::parse($relevantDetail->Periode)->year;
                }

                $rumus = str_replace(
                    ['[actualKpiEvo]', '[targetKpiEvo]'],
                    ['[actual]', (float) $relevantDetail->Nilai_Target],
                    $kpi->Rumus,
                );

                return [
                    'id' => bin2hex(random_bytes(16)).Str::random(16),
                    'idPengajuanDetail' => Hashids::connection('custom')->encode($kpi->Id_Pengajuan_Detail),
                    'idPengajuanDet' => Hashids::connection('custom')->encode($relevantDetail->Id_Pengajuan_Det),
                    'idPengajuan' => Hashids::connection('custom')->encode($kpi->Id_Pengajuan),
                    'periode' => $periodName,
                    'parameter' => $kpi->Nama_Parameter,
                    'bsc' => $kpi->Nama_Kategori,
                    'weight' => (float) $kpi->Bobot,
                    'jenis_target' => $kodeTarget,
                    'periode_spesifik' => $relevantDetail->Nama_Target,
                    'targetValue' => (float) $relevantDetail->Nilai_Target,
                    'targetDescription' => (float) $relevantDetail->Nilai_Target,
                    'targetUnit' => $kpi->Simbol_Satuan,
                    'rumus' => $rumus,
                    'min_range' => isset($kpi->Min_Range) ? (float) $kpi->Min_Range : null,
                    'max_range' => isset($kpi->Max_Range) ? (float) $kpi->Max_Range : null,
                    'actual' => null,
                    'isInverted' => str_contains($kpi->Rumus, '[targetKpiEvo]/[actualKpiEvo]'),
                    'pica' => ['problem' => '', 'action' => '', 'targetDate' => ''],
                    'achievement' => 0,
                    'score' => 0,
                    'dataSourceFile' => null,
                    'isReviewed' => false,
                    'is_input_period' => $isInputPeriod,
                    // Batas bulan target representatif (dipakai tooltip kartu mobile FE).
                    'start_date' => Carbon::parse($relevantDetail->Periode)->startOfMonth()->toDateString(),
                    'end_date' => Carbon::parse($relevantDetail->Periode)->endOfMonth()->toDateString(),
                ];
            })
            ->filter();

        $headerPeriode = $targetDate->locale('id')->isoFormat('MMMM YYYY');

        return ResponseHelper::success(
            [
                'header' => [
                    'periode' => $headerPeriode,
                    'tahun' => $targetDate->year,
                    'bulan' => $targetDate->month,
                ],
                'details' => $result->values()->all(),
            ],
            200,
        );
    }

    public function getDataKpiItemsByIdUser(Request $request)
    {
        $request->validate([
            'Id_User' => 'required',
        ]);

        try {
            $Id_User = Hashids::connection('custom')->decode($request->Id_User)[0];
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'status' => 400,
                    'message' => 'Format Kunci Tidak Valid.',
                ],
                400,
            );
        }

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();
        $nik = $karyawan->Kode_Karyawan ?? null;

        // Jadwal yang sedang dibuka halaman menang atas tebakan periode otomatis,
        // supaya daftar KPI yang tampil sama dengan periode yang nanti disimpan.
        $targetDate =
            $this->resolvePeriodFromScheduleHash($request->input('Id_Master_Penjadwalan')) ??
            $this->getDynamicPeriod($nik);

        if (! $targetDate) {
            return ResponseHelper::success(
                [
                    'header' => [
                        'periode' => '-',
                        'tahun' => date('Y'),
                        'bulan' => date('m'),
                    ],
                    'details' => [],
                ],
                200,
            );
        }

        $kpiByUser = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
            ->where('kp.Id_User', $karyawan->UserID_Web)
            ->whereNull('kp.Status')
            ->where('kp.Flag_Aktif', 'Y')
            ->where('kp.Flag_Approval', 'Y')
            // Flag_Team TIDAK ikut disaring, sama seperti getTeamPengajuanIds().
            // Sejak keanggotaan ditentukan roster, anggota bisa muncul di To Update
            // atas dasar parameter yang Flag_Team-nya kosong — dan dari 856
            // pengajuan hidup+disetujui, hanya 3 yang berstempel 'Y'. Menyaringnya
            // di sini saja membuat baris yang tampil di daftar menjawab 404 begitu
            // diklik: daftar dan detailnya memakai dua definisi yang berbeda.
            ->where('mp.Tahun', $targetDate->year)
            ->orderBy('kp.Id_Pengajuan', 'DESC')
            ->select('kp.*')
            ->first();

        if (! $kpiByUser) {
            return ResponseHelper::error('Data Pengajuan KPI tidak ditemukan', 404);
        }

        $mainKpis = DB::table('KPI_Pengajuan_Detail')
            ->join(
                'KPI_Master_Bsc_Kategori',
                'KPI_Pengajuan_Detail.Id_Bsc_Kategori',
                '=',
                'KPI_Master_Bsc_Kategori.Id_Master_Bsc_Kategori',
            )
            ->join(
                'KPI_Master_Formula',
                'KPI_Pengajuan_Detail.Id_Master_Formula',
                '=',
                'KPI_Master_Formula.Id_Master_Formula',
            )
            ->join(
                'KPI_Master_Satuan',
                'KPI_Pengajuan_Detail.Id_Master_Satuan',
                '=',
                'KPI_Master_Satuan.Id_Master_Satuan',
            )
            ->join(
                'KPI_Master_Target',
                'KPI_Pengajuan_Detail.Id_Master_Target',
                '=',
                'KPI_Master_Target.Id_Master_Target',
            )
            ->where('KPI_Pengajuan_Detail.Id_Pengajuan', $kpiByUser->Id_Pengajuan)
            ->select(
                'KPI_Pengajuan_Detail.*',
                'KPI_Master_Bsc_Kategori.Nama_Kategori',
                'KPI_Master_Formula.Rumus',
                'KPI_Master_Formula.Min_Range',
                'KPI_Master_Formula.Max_Range',
                'KPI_Master_Satuan.Simbol_Satuan',
                'KPI_Master_Target.Nama_Target AS Tipe_Target',
                'KPI_Master_Target.Kode_Target',
            )
            ->get();

        $allPeriodDetails = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $mainKpis->pluck('Id_Pengajuan_Detail'))
            ->get()
            ->groupBy('Id_Pengajuan_Detail');

        $result = $mainKpis
            ->map(function ($kpi) use ($allPeriodDetails, $targetDate) {
                $detailsForThisKpi = $allPeriodDetails->get($kpi->Id_Pengajuan_Detail);
                if (! $detailsForThisKpi) {
                    return null;
                }

                $relevantDetail = null;
                $isInputPeriod = false;
                $kodeTarget = $kpi->Kode_Target;

                // --- TAHAP 1: Cek Exact Match (Untuk menentukan is_input_period = true) ---
                foreach ($detailsForThisKpi as $detail) {
                    $periodeDate = Carbon::parse($detail->Periode);

                    if ($periodeDate->format('Y-m') === $targetDate->format('Y-m')) {
                        $relevantDetail = $detail;
                        $isInputPeriod = true;
                        break;
                    }
                }

                // --- TAHAP 2: Fallback Display (Jika tidak ada match persis) ---
                if (! $relevantDetail) {
                    foreach ($detailsForThisKpi as $detail) {
                        $periodeDate = Carbon::parse($detail->Periode);
                        $isCurrent = false;

                        if ($kodeTarget === 'M') {
                            if ($targetDate->isSameMonth($periodeDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'Y') {
                            if ($targetDate->isSameYear($periodeDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'Q') {
                            $startDate = $periodeDate->copy()->subMonths(2)->startOfMonth();
                            $endDate = $periodeDate->copy()->endOfMonth();
                            if ($targetDate->between($startDate, $endDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'S') {
                            $startDate = $periodeDate->copy()->subMonths(5)->startOfMonth();
                            $endDate = $periodeDate->copy()->endOfMonth();
                            if ($targetDate->between($startDate, $endDate)) {
                                $isCurrent = true;
                            }
                        }

                        if ($isCurrent) {
                            $relevantDetail = $detail;
                            // isInputPeriod TETAP FALSE
                            break;
                        }
                    }
                }

                if (! $relevantDetail) {
                    // Tidak ada target untuk periode ini -> tetap tampilkan parameter dalam
                    // keadaan NONAKTIF (belum dimulai / sudah berakhir) memakai target
                    // terdekat. Sebelumnya parameter di-drop sehingga hilang dari form.
                    $relevantDetail = $this->pickFallbackPeriodDetail($detailsForThisKpi, $targetDate);
                    $isInputPeriod = false;
                }

                if (! $relevantDetail) {
                    return null; // benar-benar tak ada target sama sekali (anomali data)
                }

                $periodName = '';
                if ($kodeTarget === 'M') {
                    $periodName = Carbon::parse($relevantDetail->Periode)->locale('id')->isoFormat('MMMM YYYY');
                } elseif ($kodeTarget === 'Q') {
                    $periodName =
                        'Kuartal '.
                        strtoupper($relevantDetail->Nama_Target).
                        ' '.
                        Carbon::parse($relevantDetail->Periode)->year;
                } elseif ($kodeTarget === 'Y') {
                    $periodName = 'Akhir Tahun '.Carbon::parse($relevantDetail->Periode)->year;
                } elseif ($kodeTarget === 'S') {
                    $periodName =
                        'Semester '.
                        strtoupper($relevantDetail->Nama_Target).
                        ' '.
                        Carbon::parse($relevantDetail->Periode)->year;
                }

                $rumus = str_replace(
                    ['[actualKpiEvo]', '[targetKpiEvo]'],
                    ['[actual]', (float) $relevantDetail->Nilai_Target],
                    $kpi->Rumus,
                );

                return [
                    'id' => bin2hex(random_bytes(16)).Str::random(16),
                    'idPengajuanDetail' => Hashids::connection('custom')->encode($kpi->Id_Pengajuan_Detail),
                    'idPengajuanDet' => Hashids::connection('custom')->encode($relevantDetail->Id_Pengajuan_Det),
                    'idPengajuan' => Hashids::connection('custom')->encode($kpi->Id_Pengajuan),
                    'periode' => $periodName,
                    'parameter' => $kpi->Nama_Parameter,
                    'bsc' => $kpi->Nama_Kategori,
                    'weight' => (float) $kpi->Bobot,
                    'jenis_target' => $kodeTarget,
                    'periode_spesifik' => $relevantDetail->Nama_Target,
                    'targetValue' => (float) $relevantDetail->Nilai_Target,
                    'targetDescription' => (float) $relevantDetail->Nilai_Target,
                    'targetUnit' => $kpi->Simbol_Satuan,
                    'rumus' => $rumus,
                    'min_range' => isset($kpi->Min_Range) ? (float) $kpi->Min_Range : null,
                    'max_range' => isset($kpi->Max_Range) ? (float) $kpi->Max_Range : null,
                    'actual' => null,
                    'isInverted' => str_contains($kpi->Rumus, '[targetKpiEvo]/[actualKpiEvo]'),
                    'pica' => ['problem' => '', 'action' => '', 'targetDate' => ''],
                    'achievement' => 0,
                    'score' => 0,
                    'dataSourceFile' => null,
                    'isReviewed' => false,
                    'is_input_period' => $isInputPeriod,
                    // Batas bulan target representatif (dipakai tooltip kartu mobile FE).
                    'start_date' => Carbon::parse($relevantDetail->Periode)->startOfMonth()->toDateString(),
                    'end_date' => Carbon::parse($relevantDetail->Periode)->endOfMonth()->toDateString(),
                ];
            })
            ->filter();

        $headerPeriode = $targetDate->locale('id')->isoFormat('MMMM YYYY');

        return ResponseHelper::success(
            [
                'header' => [
                    'periode' => $headerPeriode,
                    'tahun' => $targetDate->year,
                    'bulan' => $targetDate->month,
                ],
                'details' => $result->values()->all(),
            ],
            200,
        );
    }

    public function getDataKpiItemsByIdUserByPass(Request $request)
    {
        $request->validate([
            'Id_User' => 'required',
        ]);

        try {
            $Id_User = Hashids::connection('custom')->decode($request->Id_User)[0];
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'status' => 400,
                    'message' => 'Format Kunci Tidak Valid.',
                ],
                400,
            );
        }

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

        // Jadwal yang dikirim halaman menang; bila tidak ada, tetap memakai
        // tanggal acuan by-pass seperti sebelumnya.
        $periodeJadwal = $this->resolvePeriodFromScheduleHash($request->input('Id_Master_Penjadwalan'), false);
        // dd($periodeJadwal);

        if ($periodeJadwal) {
            $now = $periodeJadwal;
        } else {
            $dummyDate = DB::table('KPI_Get_Date_Dummy2')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Get_Date2', 'DESC')
                ->first();

            if ($dummyDate && ! empty($dummyDate->Tanggal)) {
                $now = Carbon::parse($dummyDate->Tanggal);
            } else {
                $now = Carbon::now('Asia/Jakarta');
            }
        }

        $kpiByUser = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
            ->where('kp.Id_User', $karyawan->UserID_Web)
            ->whereNull('kp.Status')
            ->where('kp.Flag_Aktif', 'Y')
            ->where('kp.Flag_Approval', 'Y')
            ->where('mp.Tahun', $now->year)
            ->orderBy('kp.Id_Pengajuan', 'DESC')
            ->select('kp.*')
            ->first();
        // dd($kpiByUser);

        if (! $kpiByUser) {
            return ResponseHelper::error('Data Pengajuan KPI tidak ditemukan', 404);
        }

        $mainKpis = DB::table('KPI_Pengajuan_Detail')
            ->join(
                'KPI_Master_Bsc_Kategori',
                'KPI_Pengajuan_Detail.Id_Bsc_Kategori',
                '=',
                'KPI_Master_Bsc_Kategori.Id_Master_Bsc_Kategori',
            )
            ->join(
                'KPI_Master_Formula',
                'KPI_Pengajuan_Detail.Id_Master_Formula',
                '=',
                'KPI_Master_Formula.Id_Master_Formula',
            )
            ->join(
                'KPI_Master_Satuan',
                'KPI_Pengajuan_Detail.Id_Master_Satuan',
                '=',
                'KPI_Master_Satuan.Id_Master_Satuan',
            )
            ->join(
                'KPI_Master_Target',
                'KPI_Pengajuan_Detail.Id_Master_Target',
                '=',
                'KPI_Master_Target.Id_Master_Target',
            )
            ->where('KPI_Pengajuan_Detail.Id_Pengajuan', $kpiByUser->Id_Pengajuan)
            ->select(
                'KPI_Pengajuan_Detail.*',
                'KPI_Master_Bsc_Kategori.Nama_Kategori',
                'KPI_Master_Formula.Rumus',
                'KPI_Master_Formula.Min_Range',
                'KPI_Master_Formula.Max_Range',
                'KPI_Master_Satuan.Simbol_Satuan',
                'KPI_Master_Target.Nama_Target AS Tipe_Target',
                'KPI_Master_Target.Kode_Target',
            )
            ->get();

        $allPeriodDetails = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $mainKpis->pluck('Id_Pengajuan_Detail'))
            ->get()
            ->groupBy('Id_Pengajuan_Detail');

        $result = $mainKpis
            ->map(function ($kpi) use ($allPeriodDetails, $now) {
                $detailsForThisKpi = $allPeriodDetails->get($kpi->Id_Pengajuan_Detail);
                if (! $detailsForThisKpi) {
                    return null;
                }

                $relevantDetail = null;
                $isInputPeriod = false;
                $kodeTarget = $kpi->Kode_Target;

                foreach ($detailsForThisKpi as $detail) {
                    $periodeDate = Carbon::parse($detail->Periode);
                    $isMatch = false;

                    if ($kodeTarget === 'M') {
                        if ($periodeDate->format('Y-m') === $now->format('Y-m')) {
                            $isMatch = true;
                        }
                    } elseif ($kodeTarget === 'Q') {
                        if ($now->month === $periodeDate->month && $now->year === $periodeDate->year) {
                            $isMatch = true;
                        }
                    } elseif ($kodeTarget === 'S') {
                        $semesterEndMonth = $periodeDate->month;
                        if (
                            ($now->month === $semesterEndMonth + 1 && $now->year === $periodeDate->year) ||
                            ($semesterEndMonth == 12 && $now->month == 1 && $now->year == $periodeDate->year + 1)
                        ) {
                            $isMatch = true;
                        }
                    } elseif ($kodeTarget === 'Y') {
                        if ($now->year === $periodeDate->year + 1 && $now->month === 1) {
                            $isMatch = true;
                        }
                    }

                    if ($isMatch) {
                        $relevantDetail = $detail;
                        $isInputPeriod = true;
                        break;
                    }
                }

                if (! $relevantDetail) {
                    foreach ($detailsForThisKpi as $detail) {
                        $periodeDate = Carbon::parse($detail->Periode);
                        $isCurrent = false;

                        if ($kodeTarget === 'M') {
                            if ($now->isSameMonth($periodeDate, true)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'Q') {
                            $startDate = $periodeDate->copy()->subMonths(2)->startOfMonth();
                            $endDate = $periodeDate->copy()->endOfMonth();
                            if ($now->between($startDate, $endDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'S') {
                            $startDate = $periodeDate->copy()->subMonths(5)->startOfMonth();
                            $endDate = $periodeDate->copy()->endOfMonth();
                            if ($now->between($startDate, $endDate)) {
                                $isCurrent = true;
                            }
                        } elseif ($kodeTarget === 'Y') {
                            if ($now->isSameYear($periodeDate)) {
                                $isCurrent = true;
                            }
                        }

                        if ($isCurrent) {
                            $relevantDetail = $detail;
                            break;
                        }
                    }
                }

                if (! $relevantDetail) {
                    // Tidak ada target untuk periode ini -> tetap tampilkan parameter dalam
                    // keadaan NONAKTIF (belum dimulai / sudah berakhir) memakai target
                    // terdekat. Sebelumnya parameter di-drop sehingga hilang dari form.
                    // Acuan periode di fungsi ini bernama $now (bukan $targetDate seperti pada
                    // dua fungsi kembarannya). Salah nama di sini membuat seluruh endpoint
                    // balas 500 begitu ada satu parameter yang tak punya target di periode itu.
                    $relevantDetail = $this->pickFallbackPeriodDetail($detailsForThisKpi, $now);
                    $isInputPeriod = false;
                }

                if (! $relevantDetail) {
                    return null; // benar-benar tak ada target sama sekali (anomali data)
                }

                $periodName = '';
                if ($kodeTarget === 'M') {
                    $periodName = Carbon::parse($relevantDetail->Periode)->locale('id')->isoFormat('MMMM YYYY');
                } elseif ($kodeTarget === 'Q') {
                    $periodName =
                        'Kuartal '.
                        strtoupper($relevantDetail->Nama_Target).
                        ' '.
                        Carbon::parse($relevantDetail->Periode)->year;
                } elseif ($kodeTarget === 'Y') {
                    $periodName = 'Akhir Tahun '.Carbon::parse($relevantDetail->Periode)->year;
                } elseif ($kodeTarget === 'S') {
                    $periodName =
                        'Semester '.
                        strtoupper($relevantDetail->Nama_Target).
                        ' '.
                        Carbon::parse($relevantDetail->Periode)->year;
                }

                $rumus = str_replace(
                    ['[actualKpiEvo]', '[targetKpiEvo]'],
                    ['[actual]', (float) $relevantDetail->Nilai_Target],
                    $kpi->Rumus,
                );
                // dd($relevantDetail);

                // dd($kpi, $relevantDetail, $periodName, $rumus, $isInputPeriod);

                return [
                    'id' => bin2hex(random_bytes(16)).Str::random(16),
                    'idPengajuanDetail' => Hashids::connection('custom')->encode($kpi->Id_Pengajuan_Detail),
                    'idPengajuanDet' => Hashids::connection('custom')->encode($relevantDetail->Id_Pengajuan_Det),
                    'idPengajuan' => Hashids::connection('custom')->encode($kpi->Id_Pengajuan),
                    'periode' => $periodName,
                    'parameter' => $kpi->Nama_Parameter,
                    'bsc' => $kpi->Nama_Kategori,
                    'weight' => (float) $kpi->Bobot,
                    'jenis_target' => $kodeTarget,
                    'periode_spesifik' => $relevantDetail->Nama_Target,
                    'targetValue' => (float) $relevantDetail->Nilai_Target,
                    'targetDescription' => (float) $relevantDetail->Nilai_Target,
                    'targetUnit' => $kpi->Simbol_Satuan,
                    'rumus' => $rumus,
                    'min_range' => isset($kpi->Min_Range) ? (float) $kpi->Min_Range : null,
                    'max_range' => isset($kpi->Max_Range) ? (float) $kpi->Max_Range : null,
                    'actual' => null,
                    'isInverted' => str_contains($kpi->Rumus, '[targetKpiEvo]/[actualKpiEvo]'),
                    'pica' => ['problem' => '', 'action' => '', 'targetDate' => ''],
                    'achievement' => 0,
                    'score' => 0,
                    'dataSourceFile' => null,
                    'isReviewed' => false,
                    'is_input_period' => $isInputPeriod,
                    // Batas bulan target representatif (dipakai tooltip kartu mobile FE).
                    'start_date' => Carbon::parse($relevantDetail->Periode)->startOfMonth()->toDateString(),
                    'end_date' => Carbon::parse($relevantDetail->Periode)->endOfMonth()->toDateString(),
                ];
            })
            ->filter();
        // dd($result);

        $headerPeriode = $now->locale('id')->isoFormat('MMMM YYYY');

        return ResponseHelper::success(
            [
                'header' => [
                    'periode' => $headerPeriode,
                    'tahun' => $now->year,
                    'bulan' => $now->month,
                ],
                'details' => $result->values()->all(),
            ],
            200,
        );
    }

    /**
     * Anggota tim seorang leader — SATU-SATUNYA definisi keanggotaan di halaman
     * Update Performance Tim.
     *
     * Sumbernya roster KPI_Karyawan_Team, bukan lagi "siapa yang mengirim
     * pengajuan parameter". Aturan lama (Id_User_Sender = leader) berarti hanya
     * orang yang kebetulan menginput parameter yang boleh menilai, sehingga
     * leader yang sah tidak bisa melihat anggotanya sendiri begitu parameternya
     * diinput orang lain — dan itu memang keadaan yang lumrah di sini: dari 856
     * pengajuan hidup+disetujui, hanya 3 yang berstempel Flag_Team='Y'.
     *
     * CATATAN PENTING: satu anggota BOLEH berada di roster lebih dari satu leader
     * (di Web_HRIS 10 Sep 2026 ada 6 orang seperti itu, satu di antaranya di 3
     * roster). Konsekuensinya disengaja: ketiganya melihat orang itu di To Update,
     * dan siapa pun yang mengisi lebih dulu membuatnya hilang dari daftar yang
     * lain — sebab "sudah diisi" dinilai per (anggota, periode), bukan per leader.
     *
     * @return \Illuminate\Support\Collection<int,int> UserID_Web anggota
     */
    private function anggotaTimSaya(int $leaderId)
    {
        $leader = DB::table('Karyawan')->where('UserID_Web', $leaderId)->first();

        if (! $leader || empty($leader->Kode_Karyawan)) {
            return collect();
        }

        return DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->where('kt.Kode_Karyawan_Leader', $leader->Kode_Karyawan)
            ->where('kt.Flag_Aktif', 'Y')
            ->whereNotNull('k.UserID_Web')
            ->distinct()
            ->pluck('k.UserID_Web')
            ->map(fn ($v) => (int) $v)
            ->values();
    }

    /**
     * Id_Pengajuan parameter yang boleh dinilai leader ini: milik anggota
     * rosternya, disetujui, dan masih hidup.
     *
     * Flag_Team sengaja TIDAK ikut disaring. Kolom itu hanya menandai lewat form
     * mana parameternya masuk, dan sejak keanggotaan ditentukan roster, jalur
     * masuk parameter tidak lagi menentukan apa pun. Menyaringnya justru membuat
     * halaman hampir selalu kosong.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getTeamPengajuanIds($leaderId)
    {
        $anggota = $this->anggotaTimSaya((int) $leaderId);

        if ($anggota->isEmpty()) {
            return collect();
        }

        $hasil = collect();

        // Dipotong 500: SQL Server menolak lebih dari 2.100 parameter, dan satu
        // tim besar sudah cukup untuk menyentuhnya.
        foreach ($anggota->chunk(500) as $bagian) {
            $hasil = $hasil->merge(
                DB::table('KPI_Pengajuan')
                    ->whereIn('Id_User', $bagian->values()->all())
                    ->where('Flag_Aktif', 'Y')
                    ->where('Flag_Approval', 'Y')
                    ->whereNull('Status')
                    ->pluck('Id_Pengajuan'),
            );
        }

        return $hasil->unique()->values();
    }

    /**
     * Peta Periode ('Y-m') → hash Id_Master_Penjadwalan untuk jadwal penilaian yang
     * sedang berjalan.
     *
     * Dipakai daftar On Going/Returned untuk menentukan jadwal mana yang dibuka saat
     * sebuah baris diklik. Sengaja TIDAK memakai kolom Id_Penjadwalan pada baris
     * transaksi: pada jalur by-pass, jadwal dan bulan/tahun dipilih terpisah sehingga
     * Id_Penjadwalan bisa menunjuk jadwal dengan periode yang berbeda dari baris itu.
     * Periode yang tidak punya jadwal berjalan menghasilkan null — halaman detail akan
     * menampilkan status jadwal tutup, bukan membuka periode yang keliru.
     *
     * @return \Illuminate\Support\Collection
     */
    private function getScheduleHashByPeriode()
    {
        $now = $this->DateCarbon();

        return DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->whereNotNull('Periode')
            ->where('Periode', '<>', '')
            ->orderBy('Id_Master_Penjadwalan')
            ->get()
            ->mapWithKeys(function ($jadwal) {
                return [
                    Carbon::parse($jadwal->Periode)->format('Y-m') => Hashids::connection('custom')->encode(
                        $jadwal->Id_Master_Penjadwalan,
                    ),
                ];
            });
    }

    /**
     * Susun seluruh baris "To Update": pasangan (anggota tim × jadwal aktif) yang belum
     * punya pengajuan update performance. Satu anggota bisa muncul lebih dari sekali bila
     * punya beberapa jadwal aktif sekaligus (mis. susulan Mei + global Juni), diurutkan
     * dari periode terlama supaya pengisian berjalan runut.
     *
     * Hanya di sinilah jadwal master divalidasi (Flag_Aktif, Flag_Penilaian_KPI, dan rentang
     * Jadwal_Mulai–Jadwal_Akhir). Draft yang belum disubmit sengaja TIDAK menghilangkan
     * anggota dari daftar ini agar leader bisa melanjutkan pengisiannya, dan pengajuan yang
     * dibatalkan (Flag_Aktif = 'T' dan Status = 'Y') dianggap tidak pernah ada.
     *
     * @return \Illuminate\Support\Collection
     */
    private function buildToUpdatePairs($leaderId, ?string $search = null)
    {
        $now = $this->DateCarbon();

        // 1. Parameter milik anggota ROSTER yang sudah disetujui. Keanggotaannya
        //    ditentukan KPI_Karyawan_Team, bukan lagi siapa yang mengirim.
        $anggotaTim = $this->anggotaTimSaya((int) $leaderId);

        if ($anggotaTim->isEmpty()) {
            return collect();
        }

        $pengajuans = collect();
        foreach ($anggotaTim->chunk(500) as $bagian) {
            $pengajuans = $pengajuans->merge(
                DB::table('KPI_Pengajuan as kp')
                    ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
                    ->whereIn('kp.Id_User', $bagian->values()->all())
                    ->where('kp.Flag_Aktif', 'Y')
                    ->where('kp.Flag_Approval', 'Y')
                    ->whereNull('kp.Status')
                    ->select('kp.Id_Pengajuan', 'kp.Id_User', 'mp.Tahun')
                    ->get(),
            );
        }

        if ($pengajuans->isEmpty()) {
            return collect();
        }

        // Tahun pengajuan per anggota: sebuah periode hanya relevan bila tahunnya
        // punya pengajuan KPI (menggantikan filter tahun global yang dulu bikin
        // daftar tim kosong setiap awal tahun).
        $tahunPengajuanPerUser = $pengajuans->groupBy('Id_User')->map(function ($rows) {
            return $rows
                ->pluck('Tahun')
                ->map(function ($tahun) {
                    return (int) $tahun;
                })
                ->unique();
        });
        $teamPengajuanIds = $pengajuans->pluck('Id_Pengajuan')->unique();

        // 2. Data tampilan anggota sekaligus filter pencarian.
        $anggotaQuery = DB::table('karyawan')
            ->join(
                'View_Divisi_Sub_Divisi',
                'karyawan.ID_Divisi_Sub_Divisi',
                '=',
                'View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI',
            )
            ->join(
                'View_Golongan_Sub_Golongan_Level_Jabatan',
                'karyawan.ID_Level_Jabatan',
                '=',
                'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level_Jabatan',
            )
            ->whereIn('karyawan.UserID_Web', $tahunPengajuanPerUser->keys())
            ->select(
                'karyawan.UserID_Web as id_user',
                'karyawan.Kode_Karyawan as kode_karyawan',
                'karyawan.Nama as nama_lengkap',
                'karyawan.Tgl_Lahir as tgl_lahir',
                'karyawan.Jenis_Kelamin as jenis_kelamin',
                'karyawan.HP as hp',
                'View_Divisi_Sub_Divisi.nama_divisi',
                'View_Divisi_Sub_Divisi.nama_sub_divisi',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan',
            );

        if (! empty($search)) {
            $anggotaQuery->where(function ($q) use ($search) {
                $q->where('karyawan.Nama', 'like', "%{$search}%")
                    ->orWhere('karyawan.Kode_Karyawan', 'like', "%{$search}%")
                    ->orWhere('View_Divisi_Sub_Divisi.nama_divisi', 'like', "%{$search}%")
                    ->orWhere('View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }

        $anggota = $anggotaQuery->orderBy('karyawan.Nama', 'asc')->get();

        if ($anggota->isEmpty()) {
            return collect();
        }

        // 3. Jadwal global yang sedang berjalan.
        $jadwalGlobal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where(function ($query) {
                $query->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->get();

        // 4. Jadwal perorangan yang sedang berjalan untuk seluruh anggota, sekali query.
        $niks = $anggota->pluck('kode_karyawan')->filter()->unique()->values();
        $jadwalPeroranganPerNik = collect();

        if ($niks->isNotEmpty()) {
            $jadwalPeroranganPerNik = DB::table('KPI_Master_Penjadwalan as m')
                ->join(
                    'N_KPI_Master_Penjadwalan_Perorangan as p',
                    'm.Id_Master_Penjadwalan',
                    '=',
                    'p.Id_Master_Penjadwalan',
                )
                ->whereIn('p.Kode_Karyawan', $niks)
                ->where('p.Flag_Aktif', 'Y')
                ->where('m.Flag_Aktif', 'Y')
                ->where('m.Flag_Perorangan', 'Y')
                ->where('m.Flag_Penilaian_KPI', 'Y')
                ->where('m.Jadwal_Mulai', '<=', $now)
                ->where('m.Jadwal_Akhir', '>=', $now)
                ->select('m.*', 'p.Kode_Karyawan as kode_karyawan')
                ->get()
                ->groupBy('kode_karyawan');
        }

        if ($jadwalGlobal->isEmpty() && $jadwalPeroranganPerNik->isEmpty()) {
            return collect();
        }

        // 5. Realisasi yang masih HIDUP, sekali query.
        //
        // Hidup = Flag_Aktif 'Y' DAN Status NULL. Dua syarat, bukan satu: pernah
        // ditemukan baris ber-Flag_Aktif 'Y' TAPI Status 'Y' sekaligus, dan dengan
        // hanya menyaring Flag_Aktif baris seperti itu terhitung "sudah diisi"
        // sehingga anggotanya hilang dari To Update tanpa sebab.
        //
        // Yang tidak hidup — dibatalkan (Status 'Y') maupun riwayat lama yang sudah
        // digantikan (Flag_Aktif 'T', Status NULL) — tidak dihitung, sehingga
        // periodenya kembali muncul selama jadwalnya masih berjalan.
        $sudahDiisi = DB::table('KPI_Update_Performance')
            ->whereIn('Id_Pengajuan', $teamPengajuanIds)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->select('Id_User', 'Periode')
            ->get()
            ->mapWithKeys(function ($row) {
                return [$row->Id_User.'|'.Carbon::parse($row->Periode)->format('Y-m') => true];
            });

        // 5b. Siapa yang punya target jatuh tempo, sekali per PERIODE untuk seluruh
        //     anggota (bukan per orang). Anggota tanpa target bulan itu tidak punya
        //     apa pun untuk diisi, jadi tidak muncul di To Update — sama dengan
        //     label "Tidak Ada Target" di monitoring.
        $idAnggota = $anggota->pluck('id_user')->map(fn ($id) => (int) $id)->all();
        $punyaTargetPerPeriode = $jadwalGlobal
            ->merge($jadwalPeroranganPerNik->flatten(1))
            ->pluck('Periode')
            ->filter()
            ->map(fn ($periode) => Carbon::parse($periode)->format('Y-m'))
            ->unique()
            ->mapWithKeys(fn ($periodeRaw) => [$periodeRaw => $this->punyaTargetPeriode($idAnggota, $periodeRaw)]);

        // 6. Susun pasangan anggota × jadwal.
        $pairs = collect();

        foreach ($anggota as $orang) {
            $jadwalOrang = collect($jadwalPeroranganPerNik->get($orang->kode_karyawan, []))
                ->merge($jadwalGlobal)
                ->unique('Id_Master_Penjadwalan')
                ->sortBy(function ($jadwal) {
                    return Carbon::parse($jadwal->Periode)->timestamp;
                });

            $tahunPengajuan = $tahunPengajuanPerUser->get($orang->id_user, collect());

            foreach ($jadwalOrang as $jadwal) {
                $periode = Carbon::parse($jadwal->Periode);
                $periodeRaw = $periode->format('Y-m');

                if (! $tahunPengajuan->contains($periode->year)) {
                    continue;
                }

                if ($sudahDiisi->has($orang->id_user.'|'.$periodeRaw)) {
                    continue;
                }

                if (! ($punyaTargetPerPeriode->get($periodeRaw)[(int) $orang->id_user] ?? false)) {
                    continue;
                }

                $pairs->push([
                    'id' => bin2hex(random_bytes(16)),
                    'id_user_raw' => $orang->id_user,
                    'id_user' => Hashids::connection('custom')->encode($orang->id_user),
                    // Kode karyawan SUDAH diambil oleh kuerinya, tetapi dulu
                    // dibuang di sini — sehingga dua orang bernama sama di
                    // divisi yang sama tak bisa dibedakan sama sekali di layar,
                    // padahal yang dipilih menentukan siapa yang dinilai.
                    'kode_karyawan' => $orang->kode_karyawan,
                    'nama_lengkap' => $orang->nama_lengkap,
                    'tgl_lahir' => $orang->tgl_lahir,
                    'jenis_kelamin' => $orang->jenis_kelamin,
                    'hp' => $orang->hp,
                    'nama_divisi' => $orang->nama_divisi,
                    'nama_sub_divisi' => $orang->nama_sub_divisi,
                    'level' => $orang->nama_level,
                    'nama_jabatan' => $orang->nama_jabatan,
                    'periode' => $periode->locale('id')->translatedFormat('F Y'),
                    'periode_raw' => $periodeRaw,
                    'schedule_hash' => Hashids::connection('custom')->encode($jadwal->Id_Master_Penjadwalan),
                    'is_to_update' => true,
                ]);
            }
        }

        // Periode terlama lebih dulu supaya pengisian susulan didahulukan.
        return $pairs->sortBy([['periode_raw', 'asc'], ['nama_lengkap', 'asc']])->values();
    }

    public function getDataKpiItemsTeam(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;
        $search = $request->input('q', $request->input('search'));
        $limit = (int) $request->input('limit', 20);
        $page = (int) $request->input('page', 1);

        if ($this->anggotaTimSaya($pengguna)->isEmpty()) {
            return ResponseHelper::error('Tidak ada tim aktif yang ditemukan untuk Anda.', 404);
        }

        $pairs = $this->buildToUpdatePairs($pengguna, $search);

        if ($pairs->isEmpty()) {
            return ResponseHelper::successWithPagination(
                [],
                1,
                $limit,
                0,
                'Tidak ada anggota tim yang perlu diupdate saat ini.',
                200,
            );
        }

        $totalData = $pairs->count();

        $data = $pairs
            ->slice(($page - 1) * $limit, $limit)
            ->values()
            ->map(function ($row) {
                unset($row['id_user_raw']);

                return $row;
            });

        return ResponseHelper::successWithPagination($data, $page, $limit, $totalData, 'Data ditemukan', 200);
    }

    public function getDataKpiItemsByPass(Request $request)
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 20);
        $page = $request->input('page', 1);

        $reqBulan = $request->input('bulan');
        $reqTahun = $request->input('tahun');

        if ($reqBulan && $reqTahun) {
            $targetPeriode = sprintf('%s-%02d', $reqTahun, $reqBulan);
        } else {
            $now = $this->DateCarbon2();
            $targetPeriode = $now->copy()->subMonthNoOverflow()->format('Y-m');
        }

        /*
         * ── Kenapa ini SUBKUERI, bukan pluck lalu whereIn ────────────────────
         *
         * Bentuk sebelumnya menarik SELURUH Id_Pengajuan yang disetujui ke PHP,
         * lalu mengirimnya kembali sebagai IN(...) — dua kali — dan menutupnya
         * dengan satu IN(...) lagi berisi seluruh Id_User. Terukur di Web_HRIS
         * 10 September 2026: 859 id pengajuan dan 858 id user.
         *
         * Tiga akibatnya, dan yang ketiga yang paling serius:
         *
         *   1. Kueri pertama tidak menyaring apa pun — ia membaca seluruh
         *      KPI_Pengajuan yang disetujui, milik siapa pun. Itu sebabnya
         *      terasa seperti pemindaian penuh; memang begitu adanya.
         *   2. Jumlah parameter yang selalu berubah membuat SQL Server
         *      mengompilasi rencana BARU untuk tiap jumlah yang berbeda —
         *      859 parameter hari ini, 860 besok, masing-masing satu rencana.
         *   3. Batas keras SQL Server 2.100 parameter. Dengan 859 terpakai,
         *      sisa ruangnya 1.241 dan menyusut setiap kali ada pengajuan baru
         *      disetujui. Ini bukan akan melambat — ini akan GAGAL, dan
         *      gagalnya menyamar jadi galat basis data, bukan galat kapasitas.
         *
         * Sebagai subkueri, seluruh penyaringan tinggal di dalam basis data:
         * nol parameter, satu rencana, dan tak ada batas yang bisa ditembus.
         */
        $subPengajuan = fn () => DB::table('KPI_Pengajuan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->select('Id_Pengajuan');

        if (! $subPengajuan()->exists()) {
            return ResponseHelper::error('Tidak ada tim aktif yang ditemukan untuk Anda.', 404);
        }

        $subAnggota = fn () => DB::table('KPI_Pengajuan')
            ->whereIn('Id_Pengajuan', $subPengajuan())
            ->select('Id_User')
            ->distinct();

        if (! $subAnggota()->exists()) {
            return ResponseHelper::error('Tim Anda tidak memiliki anggota.', 404);
        }

        // Anggota yang BELUM di-update pada periode ini. NOT EXISTS dipakai
        // alih-alih diff di PHP supaya daftarnya tak pernah singgah ke sana.
        $subBelumUpdate = fn () => $subAnggota()->whereNotExists(
            fn ($q) => $q->from('KPI_Update_Performance as u')
                ->whereColumn('u.Id_User', 'KPI_Pengajuan.Id_User')
                ->whereIn('u.Id_Pengajuan', $subPengajuan())
                ->where('u.Periode', $targetPeriode)
                ->where('u.Flag_Aktif', 'Y')
                ->select(DB::raw('1')),
        );

        if (! $subBelumUpdate()->exists()) {
            return ResponseHelper::successWithPagination(
                [],
                1,
                $limit,
                0,
                "Semua anggota tim telah melakukan update performance untuk periode {$targetPeriode}",
                200,
            );
        }

        $query = DB::table('karyawan')
            ->join(
                'View_Divisi_Sub_Divisi',
                'karyawan.ID_Divisi_Sub_Divisi',
                '=',
                'View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI',
            )
            ->join(
                'View_Golongan_Sub_Golongan_Level_Jabatan',
                'karyawan.ID_Level_Jabatan',
                '=',
                'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level_Jabatan',
            )
            ->whereIn('karyawan.UserID_Web', $subBelumUpdate())
            // Hanya yang punya target jatuh tempo pada periode ini. Disaring di SQL,
            // bukan di PHP, supaya paginasi tetap utuh. Bentuknya sama dengan
            // kandidat auto-cap di KpiEnforcementController (target TEPAT bulan itu).
            ->whereExists(function ($q) use ($targetPeriode) {
                $q->select(DB::raw(1))
                    ->from('KPI_Pengajuan as tp')
                    ->join('KPI_Master_Penjadwalan as tj', 'tp.Id_Penjadwalan', '=', 'tj.Id_Master_Penjadwalan')
                    ->join('KPI_Pengajuan_Detail as tpd', 'tpd.Id_Pengajuan', '=', 'tp.Id_Pengajuan')
                    ->join('KPI_Pengajuan_Det as tpdet', 'tpdet.Id_Pengajuan_Detail', '=', 'tpd.Id_Pengajuan_Detail')
                    ->whereColumn('tp.Id_User', 'karyawan.UserID_Web')
                    ->where('tp.Flag_Aktif', 'Y')
                    ->where('tp.Flag_Approval', 'Y')
                    ->whereNull('tp.Status')
                    ->where('tj.Tahun', substr($targetPeriode, 0, 4))
                    ->where('tpdet.Periode', 'like', $targetPeriode.'%');
            })
            ->select(
                'karyawan.UserID_Web as id_user',
                // Pembeda satu-satunya ketika nama DAN divisi kebetulan sama.
                'karyawan.Kode_Karyawan as kode_karyawan',
                'karyawan.Nama as nama_lengkap',
                'karyawan.Tgl_Lahir as tgl_lahir',
                'karyawan.Jenis_Kelamin as jenis_kelamin',
                'karyawan.HP as hp',
                'View_Divisi_Sub_Divisi.nama_divisi',
                'View_Divisi_Sub_Divisi.nama_sub_divisi',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan',
            );

        // Logic Searching
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('karyawan.Nama', 'like', "%{$search}%")
                    ->orWhere('karyawan.Kode_Karyawan', 'like', "%{$search}%")
                    ->orWhere('View_Divisi_Sub_Divisi.nama_divisi', 'like', "%{$search}%")
                    ->orWhere('View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }

        $totalData = $query->count();
        $offset = ($page - 1) * $limit;

        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('karyawan.Nama', 'asc')
            ->get()
            ->map(function ($k) use ($targetPeriode) {
                return [
                    'id' => bin2hex(random_bytes(16)),
                    'id_user' => Hashids::connection('custom')->encode($k->id_user),
                    'kode_karyawan' => $k->kode_karyawan,
                    'nama_lengkap' => $k->nama_lengkap,
                    'tgl_lahir' => $k->tgl_lahir,
                    'jenis_kelamin' => $k->jenis_kelamin,
                    'hp' => $k->hp,
                    'nama_divisi' => $k->nama_divisi,
                    'nama_sub_divisi' => $k->nama_sub_divisi,
                    'level' => $k->nama_level,
                    'nama_jabatan' => $k->nama_jabatan,
                    'is_to_update' => true,
                    // Periode yang sedang dipilih di dropdown. Baris ini muncul
                    // KARENA periode itu belum terisi, jadi halaman detail harus
                    // dibuka pada periode yang sama. Tanpa ini, detail menebak
                    // sendiri lewat KPI_Get_Date_Dummy2 dan membuka bulan lain —
                    // pilih April, yang terbuka Mei.
                    'periode_raw' => $targetPeriode,
                ];
            });

        return ResponseHelper::successWithPagination($data, $page, $limit, $totalData, 'Data ditemukan', 200);
    }

    public function getDataKpiItemsTeamForApproval(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;
        $search = $request->input('q', $request->input('search'));
        $limit = $request->input('limit', 20);
        $page = $request->input('page', 1);

        $teamPengajuanIds = $this->getTeamPengajuanIds($pengguna);

        // Tanpa filter periode: seluruh periode yang menunggu approval ditampilkan,
        // sehingga satu anggota bisa punya beberapa baris periode sekaligus.
        $query = DB::table('KPI_Update_Performance as kup')
            ->join('karyawan', 'kup.Id_User', '=', 'karyawan.UserID_Web')
            ->join(
                'View_Divisi_Sub_Divisi',
                'karyawan.ID_Divisi_Sub_Divisi',
                '=',
                'View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI',
            )
            ->join(
                'View_Golongan_Sub_Golongan_Level_Jabatan',
                'karyawan.ID_Level_Jabatan',
                '=',
                'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level_Jabatan',
            )
            ->whereIn('kup.Id_Pengajuan', $teamPengajuanIds)
            ->where('kup.Status_Pengajuan', 'menunggu')
            ->whereNull('kup.Flag_Approval')
            ->where('kup.Flag_Aktif', 'Y')
            ->select(
                'kup.Id_Update_Performance as id_update_performance',
                'kup.No_Transaksi as no_transaksi',
                'kup.Periode as periode',
                'kup.Jam as jam',
                'kup.Tanggal as tanggal',
                'kup.Total_Score as total_score',
                'kup.Total_Score_Achievement as total_score_achievement',
                'kup.Total_Bobot as total_bobot',
                'karyawan.UserID_Web as id_user',
                'karyawan.Nama as nama_lengkap',
                'karyawan.Tgl_Lahir as tgl_lahir',
                'karyawan.Jenis_Kelamin as jenis_kelamin',
                'karyawan.HP as hp',
                'View_Divisi_Sub_Divisi.nama_divisi',
                'View_Divisi_Sub_Divisi.nama_sub_divisi',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan',
            );

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('karyawan.Nama', 'like', "%{$search}%")
                    ->orWhere('kup.No_Transaksi', 'like', "%{$search}%")
                    ->orWhere('View_Divisi_Sub_Divisi.nama_divisi', 'like', "%{$search}%");
            });
        }

        $totalData = $query->count();
        if ($totalData === 0) {
            return ResponseHelper::successWithPagination(
                [],
                1,
                $limit,
                0,
                'Tidak ada data yang menunggu persetujuan.',
                200,
            );
        }

        $scheduleHashByPeriode = $this->getScheduleHashByPeriode();

        $offset = ($page - 1) * $limit;

        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('kup.Periode', 'asc')
            ->orderBy('kup.Tanggal', 'desc')
            ->get()
            ->map(function ($item) use ($scheduleHashByPeriode) {
                return [
                    'id' => Hashids::connection('custom')->encode($item->id_update_performance),
                    'id_user' => Hashids::connection('custom')->encode($item->id_user),
                    'tgl_lahir' => $item->tgl_lahir,
                    'jenis_kelamin' => $item->jenis_kelamin,
                    'hp' => $item->hp,
                    'tanggal' => $item->tanggal,
                    'jam' => $item->jam,
                    'nama_divisi' => $item->nama_divisi,
                    'nama_sub_divisi' => $item->nama_sub_divisi,
                    'level' => $item->nama_level,
                    'nama_jabatan' => $item->nama_jabatan,
                    'no_transaksi' => $item->no_transaksi,
                    'nama_lengkap' => $item->nama_lengkap,
                    'periode' => Carbon::parse($item->periode)->locale('id')->translatedFormat('F Y'),
                    'periode_raw' => Carbon::parse($item->periode)->format('Y-m'),
                    'schedule_hash' => $scheduleHashByPeriode->get(Carbon::parse($item->periode)->format('Y-m')),
                    'total_score' => (float) $item->total_score,
                    'total_score_achievement' => (float) $item->total_score_achievement,
                    'total_bobot' => (float) $item->total_bobot,
                    'is_on_going' => true,
                ];
            });

        return ResponseHelper::successWithPagination($data, $page, $limit, $totalData, 'Data ditemukan', 200);
    }

    public function getDataKpiItemsTeamForApprovalRejected(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;
        $search = $request->input('q', $request->input('search'));
        $limit = $request->input('limit', 20);
        $page = $request->input('page', 1);

        $teamPengajuanIds = $this->getTeamPengajuanIds($pengguna);

        // Pengajuan terakhir per (anggota, periode) — tanpa filter periode, supaya
        // setiap periode yang ditolak muncul sebagai barisnya sendiri.
        $latestEntriesSubquery = DB::table('KPI_Update_Performance')
            ->select(DB::raw('MAX(Id_Update_Performance) as last_id'))
            ->whereIn('Id_Pengajuan', $teamPengajuanIds)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Id_User', 'Periode');

        $query = DB::table('KPI_Update_Performance as kup')
            ->join('karyawan', 'kup.Id_User', '=', 'karyawan.UserID_Web')
            ->join(
                'View_Divisi_Sub_Divisi',
                'karyawan.ID_Divisi_Sub_Divisi',
                '=',
                'View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI',
            )
            ->join(
                'View_Golongan_Sub_Golongan_Level_Jabatan',
                'karyawan.ID_Level_Jabatan',
                '=',
                'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level_Jabatan',
            )
            ->whereIn('kup.Id_Update_Performance', $latestEntriesSubquery)
            ->where('kup.Status_Pengajuan', 'ditolak')
            ->where('kup.Flag_Approval', 'T')
            ->where('kup.Flag_Aktif', 'Y')
            ->whereIn('kup.Id_Pengajuan', $teamPengajuanIds)
            ->select(
                'kup.Id_Update_Performance as id_update_performance',
                'kup.No_Transaksi as no_transaksi',
                'kup.Periode as periode',
                'kup.Tanggal as Tanggal',
                'kup.Total_Score as total_score',
                'kup.Total_Score_Achievement as total_score_achievement',
                'kup.Total_Bobot as total_bobot',
                'karyawan.UserID_Web as id_user',
                'karyawan.Nama as nama_lengkap',
                'karyawan.Tgl_Lahir as tgl_lahir',
                'karyawan.Jenis_Kelamin as jenis_kelamin',
                'karyawan.HP as hp',
                'View_Divisi_Sub_Divisi.nama_divisi',
                'View_Divisi_Sub_Divisi.nama_sub_divisi',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan',
            );

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('karyawan.Nama', 'like', "%{$search}%")
                    ->orWhere('kup.No_Transaksi', 'like', "%{$search}%")
                    ->orWhere('View_Divisi_Sub_Divisi.nama_divisi', 'like', "%{$search}%");
            });
        }

        $totalDataQuery = clone $query;
        $totalData = $totalDataQuery->count();

        if ($totalData === 0) {
            return ResponseHelper::successWithPagination([], 1, $limit, 0, 'Tidak ada data yang ditolak.', 200);
        }

        $scheduleHashByPeriode = $this->getScheduleHashByPeriode();

        $offset = ($page - 1) * $limit;

        $data = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('kup.Periode', 'asc')
            ->orderBy('kup.Tanggal', 'desc')
            ->get()
            ->map(function ($item) use ($scheduleHashByPeriode) {
                return [
                    'id' => Hashids::connection('custom')->encode($item->id_update_performance),
                    'id_user' => Hashids::connection('custom')->encode($item->id_user),
                    'tgl_lahir' => $item->tgl_lahir,
                    'jenis_kelamin' => $item->jenis_kelamin,
                    'hp' => $item->hp,
                    'nama_divisi' => $item->nama_divisi,
                    'nama_sub_divisi' => $item->nama_sub_divisi,
                    'level' => $item->nama_level,
                    'nama_jabatan' => $item->nama_jabatan,
                    'no_transaksi' => $item->no_transaksi,
                    'nama_lengkap' => $item->nama_lengkap,
                    'periode' => Carbon::parse($item->periode)->locale('id')->translatedFormat('F Y'),
                    'periode_raw' => Carbon::parse($item->periode)->format('Y-m'),
                    'schedule_hash' => $scheduleHashByPeriode->get(Carbon::parse($item->periode)->format('Y-m')),
                    'total_score' => (float) $item->total_score,
                    'total_score_achievement' => (float) $item->total_score_achievement,
                    'total_bobot' => (float) $item->total_bobot,
                    'is_rejected' => true,
                ];
            });

        return ResponseHelper::successWithPagination($data, $page, $limit, $totalData, 'Data ditemukan', 200);
    }

    /**
     * Kueri roster anggota tim yang BELUM punya pengajuan parameter tim yang
     * disetujui.
     *
     * Sumber keanggotaan di sini adalah KPI_Karyawan_Team (roster pemetaan
     * leader → anggota), BUKAN KPI_Pengajuan. Dua hal ini sengaja berbeda:
     * daftar kerja "To Update" memang harus berbasis pengajuan parameter (tidak
     * ada achievement yang bisa di-update tanpa parameternya), tetapi roster
     * justru menjawab pertanyaan yang tidak bisa dijawab daftar kerja itu —
     * "siapa anggota tim yang belum mengisi parameter sama sekali".
     *
     * Mengembalikan null bila pengguna tidak terpetakan sebagai leader.
     */
    private function belumParameterQuery(int $pengguna)
    {
        $leader = DB::table('Karyawan')->where('UserID_Web', $pengguna)->first();

        if (! $leader || empty($leader->Kode_Karyawan)) {
            return null;
        }

        return DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
            ->where('kt.Kode_Karyawan_Leader', $leader->Kode_Karyawan)
            ->where('kt.Flag_Aktif', 'Y')
            // Tanpa akun web, anggota tidak bisa mengisi parameter di sistem ini.
            ->whereNotNull('k.UserID_Web')
            // Satu anggota tetap satu baris meski roster atau data karyawan
            // kelak punya pasangan kembar — angka badge dan jumlah kartu
            // dihitung dari kueri yang sama, jadi keduanya tidak bisa berselisih.
            ->distinct()
            // "Sudah punya parameter" memakai definisi yang PERSIS sama dengan
            // keanggotaan daftar To Update (lihat getTeamPengajuanIds): parameter
            // disetujui yang masih hidup, siapa pun pengirimnya. Kalau definisinya
            // dibuat berbeda, akan lahir anggota yang tidak muncul di tab mana pun
            // — persis keluhan yang tab ini datang untuk tutup.
            ->whereNotExists(function ($q) {
                $q->from('KPI_Pengajuan as p')
                    ->whereColumn('p.Id_User', 'k.UserID_Web')
                    ->where('p.Flag_Aktif', 'Y')
                    ->where('p.Flag_Approval', 'Y')
                    ->whereNull('p.Status')
                    ->select(DB::raw('1'));
            });
    }

    /**
     * Status parameter untuk anggota yang tidak punya pengajuan tim disetujui.
     *
     * Tanpa ini semua orang di tab tampak sama, padahal ada dua keadaan yang
     * menuntut tindakan berbeda: yang benar-benar belum dikirim (leader harus
     * mengisi Form KPI Team) dan yang sudah dikirim tetapi approval-nya
     * menggantung (leader harus menagih approver, bukan mengisi ulang).
     *
     * @param  array<int,int>  $idUser
     * @return \Illuminate\Support\Collection<int,string>
     */
    private function statusParameterPerAnggota(int $pengguna, array $idUser)
    {
        if (empty($idUser)) {
            return collect();
        }

        // Pengirimnya tidak lagi disaring. Sejak keanggotaan ditentukan roster,
        // parameter yang diinput siapa pun sama sahnya — yang membedakan hanya
        // sudah disetujui atau belum, dan itulah dua tindakan berbeda bagi leader:
        // mengisi Form KPI Team, atau menagih approver.
        return DB::table('KPI_Pengajuan')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->whereIn('Id_User', $idUser)
            // Menaik supaya bila satu anggota punya lebih dari satu baris hidup,
            // keyBy menyisakan yang TERBARU dan bukan yang kebetulan lebih dulu.
            ->orderBy('Id_Pengajuan')
            ->get(['Id_User', 'Flag_Approval'])
            ->keyBy('Id_User')
            ->map(function ($baris) {
                return $baris->Flag_Approval === 'T' ? 'DITOLAK' : 'MENUNGGU_APPROVAL';
            });
    }

    /**
     * Anggota mana yang sedang tercakup jadwal berjalan untuk sebuah keperluan.
     *
     * $flag adalah kolom penanda keperluan jadwalnya:
     *   'Flag_Form_KPI'      — jendela PENGISIAN PARAMETER
     *   'Flag_Penilaian_KPI' — jendela PENILAIAN (yang menghidupkan To Update)
     *
     * Dua-duanya perlu dibedakan di layar. Tanpa itu, "belum mengisi parameter"
     * dan "jendela pengisiannya memang belum dibuka untuk dia" tampak sama persis,
     * padahal yang pertama pekerjaan leader dan yang kedua pekerjaan admin KPI.
     *
     * @param  array<int,string>  $kodeKaryawan
     * @return array<string,bool>
     */
    private function cakupanJadwal(array $kodeKaryawan, string $flag): array
    {
        if (empty($kodeKaryawan)) {
            return [];
        }

        $now = $this->DateCarbon();

        // Jadwal global mencakup semua orang sekaligus — tak perlu menanyakan
        // per karyawan kalau ada satu saja yang berjalan.
        $adaGlobal = DB::table('KPI_Master_Penjadwalan')
            ->where($flag, 'Y')   // tanpa alias: kolomnya polos
            ->where('Flag_Aktif', 'Y')
            ->where(function ($q) {
                $q->where('Flag_Perorangan', 'T')->orWhereNull('Flag_Perorangan');
            })
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->exists();

        if ($adaGlobal) {
            return array_fill_keys($kodeKaryawan, true);
        }

        $tercakup = [];
        // Dipotong 500 per kueri: SQL Server menolak lebih dari 2.100 parameter,
        // dan satu tim besar sudah cukup untuk menyentuhnya.
        foreach (array_chunk($kodeKaryawan, 500) as $bagian) {
            $tercakup = array_merge(
                $tercakup,
                DB::table('KPI_Master_Penjadwalan as m')
                    ->join(
                        'N_KPI_Master_Penjadwalan_Perorangan as p',
                        'm.Id_Master_Penjadwalan',
                        '=',
                        'p.Id_Master_Penjadwalan',
                    )
                    ->whereIn('p.Kode_Karyawan', $bagian)
                    ->where('p.Flag_Aktif', 'Y')
                    ->where('m.Flag_Aktif', 'Y')
                    ->where('m.Flag_Perorangan', 'Y')
                    ->where('m.'.$flag, 'Y')
                    ->where('m.Jadwal_Mulai', '<=', $now)
                    ->where('m.Jadwal_Akhir', '>=', $now)
                    ->distinct()
                    ->pluck('p.Kode_Karyawan')
                    ->all(),
            );
        }

        $tercakup = array_flip($tercakup);

        $hasil = [];
        foreach ($kodeKaryawan as $kode) {
            $hasil[$kode] = isset($tercakup[$kode]);
        }

        return $hasil;
    }

    /**
     * Daftar informasional "Belum Parameter": anggota tim yang belum mengisi
     * KPI Parameter sama sekali. Tidak ikut dalam daftar kerja To Update.
     */
    public function getBelumParameterTeam(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;
        $search = $request->input('q', $request->input('search'));
        $limit = (int) $request->input('limit', 20);
        $page = (int) $request->input('page', 1);

        $query = $this->belumParameterQuery($pengguna);

        if (! $query) {
            return ResponseHelper::successWithPagination(
                [],
                $page,
                $limit,
                0,
                'Anda tidak terdaftar sebagai leader tim.',
                200,
            );
        }

        $query
            ->leftJoin(
                'View_Divisi_Sub_Divisi',
                'k.ID_Divisi_Sub_Divisi',
                '=',
                'View_Divisi_Sub_Divisi.ID_DIVISI_SUB_DIVISI',
            )
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan',
                'k.ID_Level_Jabatan',
                '=',
                'View_Golongan_Sub_Golongan_Level_Jabatan.ID_Level_Jabatan',
            )
            ->select(
                'k.UserID_Web as id_user',
                'k.Kode_Karyawan as kode_karyawan',
                'k.Nama as nama_lengkap',
                'View_Divisi_Sub_Divisi.nama_divisi',
                'View_Divisi_Sub_Divisi.nama_sub_divisi',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_level',
                'View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan',
            );

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('k.Nama', 'like', "%{$search}%")
                    ->orWhere('k.Kode_Karyawan', 'like', "%{$search}%")
                    ->orWhere('View_Divisi_Sub_Divisi.nama_divisi', 'like', "%{$search}%")
                    ->orWhere('View_Golongan_Sub_Golongan_Level_Jabatan.nama_jabatan', 'like', "%{$search}%");
            });
        }

        // Dihitung dari kueri yang sama persis dengan yang mengambil barisnya —
        // termasuk distinct-nya — supaya total paginasi tidak pernah menjanjikan
        // baris yang tidak akan pernah dikirim.
        $totalData = (clone $query)->count('k.UserID_Web');
        $offset = ($page - 1) * $limit;

        $baris = $query
            ->offset($offset)
            ->limit($limit)
            ->orderBy('k.Nama', 'asc')
            ->get();

        // Satu kueri untuk seluruh halaman, bukan satu per anggota.
        $status = $this->statusParameterPerAnggota(
            $pengguna,
            $baris->pluck('id_user')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all(),
        );

        $kodeKaryawan = $baris->pluck('kode_karyawan')->filter()->map(fn ($v) => (string) $v)->unique()->values()->all();
        $jadwalParameter = $this->cakupanJadwal($kodeKaryawan, 'Flag_Form_KPI');
        $jadwalPenilaian = $this->cakupanJadwal($kodeKaryawan, 'Flag_Penilaian_KPI');

        $label = [
            'BELUM_ISI' => 'Belum mengisi parameter',
            'BELUM_TERJADWAL' => 'Belum terjadwal pengisian parameter',
            'MENUNGGU_APPROVAL' => 'Menunggu approval parameter',
            'DITOLAK' => 'Parameter ditolak',
        ];

        $data = $baris->map(function ($k) use ($status, $label, $jadwalParameter, $jadwalPenilaian) {
            $kode = $status[(int) $k->id_user] ?? 'BELUM_ISI';
            $bisaMengisi = $jadwalParameter[(string) $k->kode_karyawan] ?? false;

            // Selama jendela pengisiannya belum dibuka, "belum mengisi" bukan
            // keterangan yang bisa ditindaklanjuti siapa pun. Yang benar-benar
            // menahan adalah jadwalnya, jadi itu yang ditampilkan.
            if ($kode === 'BELUM_ISI' && ! $bisaMengisi) {
                $kode = 'BELUM_TERJADWAL';
            }

            return [
                // Kunci render harus stabil antar-muat; kunci acak memaksa Vue
                // membuang dan membangun ulang seluruh kartu setiap fetch.
                'id' => 'bp-'.$k->kode_karyawan,
                'id_user' => Hashids::connection('custom')->encode($k->id_user),
                'kode_karyawan' => $k->kode_karyawan,
                'nama_lengkap' => $k->nama_lengkap,
                'nama_divisi' => $k->nama_divisi,
                'nama_sub_divisi' => $k->nama_sub_divisi,
                'level' => $k->nama_level,
                'nama_jabatan' => $k->nama_jabatan,
                'is_belum_parameter' => true,
                'status_parameter' => $kode,
                'status_label' => $label[$kode],
                'jadwal_parameter' => $bisaMengisi,
                // Parameter yang sudah diisi pun belum tentu memunculkan anggota
                // di To Update: daftar itu menuntut jadwal PENILAIAN yang sedang
                // berjalan. Tanpa penanda ini, leader mengira pekerjaannya belum
                // selesai padahal yang kurang jadwal dari admin KPI.
                'jadwal_penilaian' => $jadwalPenilaian[(string) $k->kode_karyawan] ?? false,
            ];
        });

        return ResponseHelper::successWithPagination($data, $page, $limit, $totalData, 'Data ditemukan', 200);
    }

    public function getKpiTeamSummaryCounts()
    {
        $pengguna = Auth::user()->Id_Users;

        // Dihitung SEBELUM gerbang $adaTim. Justru leader yang belum punya satu pun
        // pengajuan disetujui adalah kasus terparah dari tab ini: seluruh rosternya
        // belum berparameter. Kalau angkanya ikut dinolkan oleh gerbang itu, badge
        // menunjukkan 0 sementara tabnya berisi puluhan orang.
        $belumParameterQuery = $this->belumParameterQuery($pengguna);
        $countBelumParameter = $belumParameterQuery
            ? (clone $belumParameterQuery)->count('k.UserID_Web')
            : 0;

        $adaTim = $this->anggotaTimSaya($pengguna)->isNotEmpty();

        if (! $adaTim) {
            return ResponseHelper::success(
                [
                    'periode' => null,
                    'to_update' => 0,
                    'on_progress' => 0,
                    'rejected' => 0,
                    'belum_parameter' => $countBelumParameter,
                ],
                'Tidak ada tim aktif yang ditemukan.',
            );
        }

        $teamPengajuanIds = $this->getTeamPengajuanIds($pengguna);

        // Ketiga angka menghitung JUMLAH ORANG (unik), bukan jumlah baris — satu anggota
        // dengan dua periode belum terisi tetap dihitung satu.
        $countToUpdate = $this->buildToUpdatePairs($pengguna)->pluck('id_user_raw')->unique()->count();

        $countOnProgress = DB::table('KPI_Update_Performance')
            ->whereIn('Id_Pengajuan', $teamPengajuanIds)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Pengajuan', 'menunggu')
            ->whereNull('Flag_Approval')
            ->distinct()
            ->count('Id_User');

        // Sama seperti daftar Returned: pengajuan terakhir per (anggota, periode).
        $latestEntriesSubquery = DB::table('KPI_Update_Performance')
            ->select(DB::raw('MAX(Id_Update_Performance) as last_id'))
            ->whereIn('Id_Pengajuan', $teamPengajuanIds)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Id_User', 'Periode');

        $countRejected = DB::table('KPI_Update_Performance as kup')
            ->whereIn('kup.Id_Update_Performance', $latestEntriesSubquery)
            ->where('kup.Status_Pengajuan', 'ditolak')
            ->where('kup.Flag_Approval', 'T')
            ->distinct()
            ->count('kup.Id_User');

        $jadwalTerlama = $this->getUserActiveSchedules(null, $this->DateCarbon())->first();

        $result = [
            'periode' => $jadwalTerlama
                ? Carbon::parse($jadwalTerlama->Periode)->locale('id')->translatedFormat('F Y')
                : null,
            'to_update' => (int) $countToUpdate,
            'on_progress' => (int) $countOnProgress,
            'rejected' => (int) $countRejected,
            'belum_parameter' => (int) $countBelumParameter,
        ];

        return ResponseHelper::success($result, 'Data ringkasan KPI tim berhasil diambil.', 200);
    }

    public function getDataHistoryKpiAndUpdateActive()
    {
        $historyKPI = [
            [
                'type' => 'TARGET_SUBMIT',
                'date' => '2025-01-10 09:15',
                'title' => 'Pengajuan Target KPI Awal',
                'by' => 'Frans (Web Programmer)',
                'statusColor' => 'primary',
                'kpiData' => [
                    [
                        'id' => 1,
                        'nama_parameter' => 'Penyelesaian Sprint Task Sesuai Estimasi',
                        'bsc' => 'Internal Process',
                        'formula' => '1 + (Actual - Target)',
                        'satuan' => '%',
                        'deskripsi' => 'Mengukur akurasi estimasi dan kecepatan penyelesaian tugas dalam sprint.',
                        'bobot' => 30,
                        'periodicity' => 'monthly',
                        'target' => '90',
                    ],
                    [
                        'id' => 2,
                        'nama_parameter' => 'Code Quality (Bug Density)',
                        'bsc' => 'Internal Process',
                        'formula' => 'Actual - Target',
                        'satuan' => 'bugs/KLOC',
                        'deskripsi' => 'Mengukur kualitas kode sebelum rilis. Target lebih rendah lebih baik.',
                        'bobot' => 25,
                        'periodicity' => 'quarterly',
                        'target' => [
                            'q1' => '0.5',
                            'q2' => '0.5',
                            'q3' => '0.4',
                            'q4' => '0.4',
                        ],
                    ],
                    [
                        'id' => 3,
                        'nama_parameter' => 'Kecepatan Respon Perbaikan Bug Kritis',
                        'bsc' => 'Customer',
                        'formula' => 'Actual - Target',
                        'satuan' => 'Jam',
                        'deskripsi' => 'Kecepatan menangani isu mendesak. Target lebih rendah lebih baik.',
                        'bobot' => 20,
                        'periodicity' => 'monthly',
                        'target' => '4',
                    ],
                    [
                        'id' => 4,
                        'nama_parameter' => 'Peningkatan Skill (Sertifikasi/Training)',
                        'bsc' => 'Learning & Growth',
                        'formula' => '1 + (Actual - Target)',
                        'satuan' => 'Sertifikasi',
                        'deskripsi' => 'Pengembangan kompetensi teknis pribadi.',
                        'bobot' => 15,
                        'periodicity' => 'yearly',
                        'target' => [
                            'jan' => 0,
                            'feb' => 0,
                            'mar' => 0,
                            'apr' => 0,
                            'mei' => 0,
                            'jun' => 1,
                            'jul' => 1,
                            'agu' => 1,
                            'sep' => 1,
                            'okt' => 1,
                            'nov' => 1,
                            'des' => 2,
                        ],
                    ],
                    [
                        'id' => 5,
                        'nama_parameter' => 'Kontribusi pada Knowledge Base Tim',
                        'bsc' => 'Learning & Growth',
                        'formula' => '1 + (Actual - Target)',
                        'satuan' => 'Artikel',
                        'deskripsi' => 'Berbagi pengetahuan dengan anggota tim lain.',
                        'bobot' => 10,
                        'periodicity' => 'quarterly',
                        'target' => ['q1' => '2', 'q2' => '2', 'q3' => '3', 'q4' => '3'],
                    ],
                ],
            ],
            [
                'type' => 'REJECT',
                'date' => '2025-01-11 15:00',
                'title' => 'Target Ditolak oleh Manajer',
                'by' => 'Hendry (Manajer)',
                'statusColor' => 'danger',
                'reason' => "Target 'Kecepatan Respon Bug' terlalu lama, standar SLA kita adalah 2 jam. Untuk 'Code Quality' Q3 dan Q4, mari kita targetkan 0.3 bugs/KLOC.",
            ],
            [
                'type' => 'RESUBMIT',
                'date' => '2025-01-12 11:30',
                'title' => 'Pengajuan Ulang Target KPI',
                'by' => 'Frans (Web Programmer)',
                'statusColor' => 'warning',
                'changes' => [
                    [
                        'field' => 'Target Code Quality Q3',
                        'oldValue' => '0.4',
                        'newValue' => '0.3',
                    ],
                    [
                        'field' => 'Target Code Quality Q4',
                        'oldValue' => '0.4',
                        'newValue' => '0.3',
                    ],
                    [
                        'field' => 'Target Kecepatan Respon Bug',
                        'oldValue' => '4 Jam',
                        'newValue' => '2 Jam',
                    ],
                ],
                'kpiData' => [
                    [
                        'id' => 1,
                        'nama_parameter' => 'Penyelesaian Sprint Task Sesuai Estimasi',
                        'bsc' => 'Internal Process',
                        'formula' => '1 + (Actual - Target)',
                        'satuan' => '%',
                        'deskripsi' => 'Mengukur akurasi estimasi dan kecepatan penyelesaian tugas dalam sprint.',
                        'bobot' => 30,
                        'periodicity' => 'monthly',
                        'target' => '90',
                    ],
                    [
                        'id' => 2,
                        'nama_parameter' => 'Code Quality (Bug Density)',
                        'bsc' => 'Internal Process',
                        'formula' => 'Actual - Target',
                        'satuan' => 'bugs/KLOC',
                        'deskripsi' => 'Mengukur kualitas kode sebelum rilis. Target lebih rendah lebih baik.',
                        'bobot' => 25,
                        'periodicity' => 'quarterly',
                        'target' => ['q1' => '0.5', 'q2' => '0.5', 'q3' => '0.3', 'q4' => '0.3'],
                    ],
                    [
                        'id' => 3,
                        'nama_parameter' => 'Kecepatan Respon Perbaikan Bug Kritis',
                        'bsc' => 'Customer',
                        'formula' => 'Actual - Target',
                        'satuan' => 'Jam',
                        'deskripsi' => 'Kecepatan menangani isu mendesak. Target lebih rendah lebih baik.',
                        'bobot' => 20,
                        'periodicity' => 'monthly',
                        'target' => '2',
                    ],
                    [
                        'id' => 4,
                        'nama_parameter' => 'Peningkatan Skill (Sertifikasi/Training)',
                        'bsc' => 'Learning & Growth',
                        'formula' => '1 + (Actual - Target)',
                        'satuan' => 'Sertifikasi',
                        'deskripsi' => 'Pengembangan kompetensi teknis pribadi.',
                        'bobot' => 15,
                        'periodicity' => 'yearly',
                        'target' => [
                            'jan' => 0,
                            'feb' => 0,
                            'mar' => 0,
                            'apr' => 0,
                            'mei' => 0,
                            'jun' => 1,
                            'jul' => 1,
                            'agu' => 1,
                            'sep' => 1,
                            'okt' => 1,
                            'nov' => 1,
                            'des' => 2,
                        ],
                    ],
                    [
                        'id' => 5,
                        'nama_parameter' => 'Kontribusi pada Knowledge Base Tim',
                        'bsc' => 'Learning & Growth',
                        'formula' => '1 + (Actual - Target)',
                        'satuan' => 'Artikel',
                        'deskripsi' => 'Berbagi pengetahuan dengan anggota tim lain.',
                        'bobot' => 10,
                        'periodicity' => 'quarterly',
                        'target' => ['q1' => '2', 'q2' => '2', 'q3' => '3', 'q4' => '3'],
                    ],
                ],
            ],
            [
                'type' => 'APPROVE',
                'date' => '2025-01-12 17:00',
                'title' => 'Target Disetujui oleh Manajer',
                'by' => 'Hendry (Manajer)',
                'statusColor' => 'success',
            ],
            [
                'type' => 'PERFORMANCE_SUBMIT',
                'date' => '2025-04-05 13:00',
                'title' => 'Pengisian Realisasi Kinerja Q1',
                'by' => 'Frans (Web Programmer)',
                'statusColor' => 'primary',
                'performanceData' => [
                    [
                        'parameter' => 'Penyelesaian Sprint Task (Rata-rata Jan-Mar)',
                        'actual' => '85%',
                        'target' => '90%',
                        'achievement' => 94.44,
                    ],
                    [
                        'parameter' => 'Code Quality Q1',
                        'actual' => '0.6 bugs/KLOC',
                        'target' => '0.5 bugs/KLOC',
                        'achievement' => 83.33,
                    ],
                    [
                        'parameter' => 'Kecepatan Respon Bug (Rata-rata Jan-Mar)',
                        'actual' => '1.8 Jam',
                        'target' => '2 Jam',
                        'achievement' => 100,
                    ],
                    [
                        'parameter' => 'Peningkatan Skill Q1',
                        'actual' => '0 Sertifikasi',
                        'target' => '0 Sertifikasi',
                        'achievement' => 100,
                    ],
                    [
                        'parameter' => 'Kontribusi Knowledge Base Q1',
                        'actual' => '3 Artikel',
                        'target' => '2 Artikel',
                        'achievement' => 100,
                    ],
                ],
                'picaRequired' => true,
            ],
            [
                'type' => 'PICA_UPDATE',
                'date' => '2025-04-05 13:20',
                'title' => 'Pengisian PICA Kinerja Q1',
                'by' => 'Frans (Web Programmer)',
                'statusColor' => 'warning',
                'picaData' => [
                    'parameter' => 'Penyelesaian Sprint Task & Code Quality',
                    'problem' => 'Terdapat 1 sprint dengan scope-creep yang tidak terduga dan adopsi library baru yang menyebabkan beberapa bug minor lolos ke UAT.',
                    'action' => 'Untuk sprint berikutnya, akan diadakan sesi pre-sprint planning yang lebih ketat untuk memvalidasi task. Untuk library baru, akan dibuatkan unit test yang lebih komprehensif.',
                ],
            ],
            [
                'type' => 'APPROVE',
                'date' => '2025-04-06 10:00',
                'title' => 'Kinerja Disetujui oleh Manajer',
                'by' => 'Hendry (Manajer)',
                'statusColor' => 'success',
            ],
            [
                'type' => 'ESCALATE',
                'date' => '2025-04-06 10:01',
                'title' => 'Diteruskan ke Talent Management',
                'by' => 'Sistem',
                'statusColor' => 'info',
            ],
            [
                'type' => 'REJECT',
                'date' => '2025-04-07 14:00',
                'title' => 'Ditolak oleh Talent Management',
                'by' => 'OKY (Talent Management)',
                'statusColor' => 'danger',
                'reason' => "PICA perlu lebih terukur. Mohon tambahkan metrik keberhasilan pada 'Tindakan Perbaikan'. Contoh: '...unit test yang lebih komprehensif untuk mencapai code coverage 85%.'",
            ],
            [
                'type' => 'RESUBMIT',
                'date' => '2025-04-08 09:45',
                'title' => 'Pengajuan Ulang Kinerja & PICA',
                'by' => 'Frans (Web Programmer)',
                'statusColor' => 'warning',
                'changes' => [
                    [
                        'field' => 'Tindakan PICA',
                        'oldValue' => 'Akan dibuatkan unit test yang lebih komprehensif.',
                        'newValue' => 'Akan dibuatkan unit test yang lebih komprehensif untuk mencapai code coverage minimal 85% pada modul baru.',
                    ],
                ],
                'performanceData' => [
                    [
                        'parameter' => 'Penyelesaian Sprint Task (Rata-rata Jan-Mar)',
                        'actual' => '85%',
                        'target' => '90%',
                        'achievement' => 94.44,
                    ],
                    [
                        'parameter' => 'Code Quality Q1',
                        'actual' => '0.6 bugs/KLOC',
                        'target' => '0.5 bugs/KLOC',
                        'achievement' => 83.33,
                    ],
                    [
                        'parameter' => 'Kecepatan Respon Bug (Rata-rata Jan-Mar)',
                        'actual' => '1.8 Jam',
                        'target' => '2 Jam',
                        'achievement' => 100,
                    ],
                    [
                        'parameter' => 'Peningkatan Skill Q1',
                        'actual' => '0 Sertifikasi',
                        'target' => '0 Sertifikasi',
                        'achievement' => 100,
                    ],
                    [
                        'parameter' => 'Kontribusi Knowledge Base Q1',
                        'actual' => '3 Artikel',
                        'target' => '2 Artikel',
                        'achievement' => 100,
                    ],
                ],
                'picaData' => [
                    'parameter' => 'Penyelesaian Sprint Task & Code Quality',
                    'problem' => 'Terdapat 1 sprint dengan scope-creep yang tidak terduga dan adopsi library baru yang menyebabkan beberapa bug minor lolos ke UAT.',
                    'action' => 'Untuk sprint berikutnya, akan diadakan sesi pre-sprint planning yang lebih ketat untuk memvalidasi task. Untuk library baru, akan dibuatkan unit test yang lebih komprehensif untuk mencapai code coverage minimal 85% pada modul baru.',
                ],
            ],
            [
                'type' => 'APPROVE',
                'date' => '2025-04-09 11:00',
                'title' => 'Disetujui Ulang oleh Manajer',
                'by' => 'Hendry (Manajer)',
                'statusColor' => 'success',
            ],
            [
                'type' => 'APPROVE',
                'date' => '2025-04-10 15:30',
                'title' => 'Disetujui oleh Talent Management',
                'by' => 'OKY (Talent Management)',
                'statusColor' => 'success',
            ],
            [
                'type' => 'APPROVE',
                'date' => '2025-04-11 10:00',
                'title' => 'Final: Disetujui oleh Pimpinan',
                'by' => 'Irfan (Pimpinan Tertinggi)',
                'statusColor' => 'success',
            ],
        ];

        return ResponseHelper::success($historyKPI, 200);
    }

    public function storeTemporaryPerformanceStep1(Request $request)
    {
        $request->validate([
            'kpi_data' => 'required|array',
            'kpi_data.*.Id_Pengajuan' => 'required',
            'kpi_data.*.Id_Pengajuan_Detail' => 'required',
            'kpi_data.*.Id_Pengajuan_Det' => 'required',
            'kpi_data.*.is_input_period' => 'required|in:true,false',
            'current_step' => 'required',
            'Id_Master_Penjadwalan' => 'required|string',
            'rejected_transaction_ref' => 'nullable|string',
        ]);

        try {
            $decodedPenjadwalanId = Hashids::connection('custom')->decode($request->Id_Master_Penjadwalan)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Tidak Valid.', 400);
        }

        $pengguna = Auth::user();

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $pengguna->Id_Users)->first();

        if (! $karyawan) {
            return ResponseHelper::error('Data Tidak Ditemukan !', 404);
        }

        $now = Carbon::now('Asia/Jakarta');

        $penjadwalan = DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $decodedPenjadwalanId)
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->first();

        if (! $penjadwalan) {
            throw new \Exception('Jadwal penilaian KPI tidak ditemukan, tidak aktif, atau sudah berakhir.');
        }

        if (! $this->punyaTargetPeriode([(int) $pengguna->Id_Users], $penjadwalan->Periode)[(int) $pengguna->Id_Users]) {
            return ResponseHelper::error($this->pesanTanpaTarget($penjadwalan->Periode), 422);
        }

        $targetDate = Carbon::parse($penjadwalan->Periode);

        // Upload berkas KPI ke GCS SEBELUM transaksi agar HTTP upload tidak
        // menahan lock DB. Bila transaksi gagal, file baru dihapus di catch.
        try {
            [$preUploadedKpiFiles, $newlyUploadedGcsPaths] = $this->preUploadKpiFilesStream($request);
        } catch (\Throwable $e) {
            Log::channel('UpdatePerformanceController')->error('Pre-upload berkas KPI gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengunggah berkas: '.$e->getMessage(), 500);
        }

        /* Nomor sementara diambil SEBELUM transaksi dibuka: counter-nya mengunci
           satu baris selama satu UPDATE autocommit. Di dalam transaksi, kunci
           itu tertahan sampai commit dan submit bersamaan kembali antre. */
        $noTransaksiSementara = $this->generateNomorSementara();

        DB::beginTransaction();

        try {
            $pengguna = Auth::user()->Id_Users;
            $kpiDataFromRequest = $request->kpi_data;
            $rejectedTransactionRef = $request->rejected_transaction_ref;

            $firstItem = $kpiDataFromRequest[0] ?? null;
            if (! $firstItem || ! isset($firstItem['Id_Pengajuan'])) {
                throw new \Exception('Tidak ada data pengajuan yang valid untuk diproses.');
            }

            $Id_Pengajuan = Hashids::connection('custom')->decode($firstItem['Id_Pengajuan'])[0] ?? null;
            if (! $Id_Pengajuan) {
                throw new \Exception('ID Pengajuan tidak valid.');
            }

            [$idHeader] = $this->handleHeader(
                $pengguna,
                $Id_Pengajuan,
                $request->current_step,
                $rejectedTransactionRef,
                $decodedPenjadwalanId,
                $noTransaksiSementara,
            );

            $decodedDetIds = collect($kpiDataFromRequest)
                ->map(fn ($d) => Hashids::connection('custom')->decode($d['Id_Pengajuan_Det'])[0] ?? null)
                ->filter()
                ->all();

            $decodedDetailIds = collect($kpiDataFromRequest)
                ->map(fn ($d) => Hashids::connection('custom')->decode($d['Id_Pengajuan_Detail'])[0] ?? null)
                ->filter()
                ->all();

            $targetRows = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Det', $decodedDetIds)
                ->get()
                ->keyBy('Id_Pengajuan_Det');

            $detailRows = DB::table('KPI_Pengajuan_Detail')
                ->whereIn('Id_Pengajuan_Detail', $decodedDetailIds)
                ->get()
                ->keyBy('Id_Pengajuan_Detail');

            $nilaiMaks = DB::table('KPI_Master_Grading')->value('Nilai_Maksimum') ?? 100;

            $formulas = DB::table('KPI_Master_Formula')->pluck('Rumus', 'Id_Master_Formula')->toArray();

            $upsertDetails = [];

            foreach ($kpiDataFromRequest as $index => $performance) {
                $performance = (object) $performance;

                if (! isset($performance->Actual) || ! is_numeric($performance->Actual)) {
                    continue;
                }

                $Id_Pengajuan_Det = Hashids::connection('custom')->decode($performance->Id_Pengajuan_Det)[0] ?? null;
                $Id_Pengajuan_Detail =
                    Hashids::connection('custom')->decode($performance->Id_Pengajuan_Detail)[0] ?? null;
                if (! $Id_Pengajuan_Det || ! $Id_Pengajuan_Detail) {
                    continue;
                }

                $fileBerkas = $preUploadedKpiFiles[$index] ?? null;

                $targetRow = $targetRows->get($Id_Pengajuan_Det);
                $detail = $detailRows->get($Id_Pengajuan_Detail);
                if (! $targetRow || ! $detail) {
                    continue;
                }

                $calc = $this->calculateFormulaStream(
                    $performance->Actual,
                    $targetRow->Nilai_Target,
                    $detail->Bobot,
                    $detail->Id_Master_Formula,
                    $formulas,
                );

                $flagPica = $calc['achievement'] < $nilaiMaks ? 'Y' : 'T';

                $periodeDate = Carbon::parse($targetRow->Periode);
                $flagAktif = $periodeDate->format('Y-m') === $targetDate->format('Y-m') ? 'Y' : 'T';

                $newData = [
                    'Id_Update_Performance_Sementara' => $idHeader,
                    'Id_Pengajuan_Det' => $Id_Pengajuan_Det,
                    'Nilai_Actual' => (float) $performance->Actual,
                    'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                    'Score' => (float) $calc['achievement'],
                    'Achievement' => (float) $calc['score'],
                    'Score_Raw' => (float) ($calc['achievement_raw'] ?? $calc['achievement']),
                    'Achievement_Raw' => (float) ($calc['score_raw'] ?? $calc['score']),
                    'Flag_Capped' => ! empty($calc['is_capped']) ? 'Y' : 'T',
                    'File_Berkas' => $fileBerkas,
                    'Flag_Pica' => $flagPica,
                    'Flag_Aktif' => $flagAktif,
                ];

                $upsertDetails[] = $newData;
            }

            if (! empty($upsertDetails)) {
                DB::table('KPI_Update_Performance_Detail_Sementara')->upsert(
                    $upsertDetails,
                    ['Id_Update_Performance_Sementara', 'Id_Pengajuan_Det'],
                    ['Nilai_Actual', 'Score', 'Achievement', 'Score_Raw', 'Achievement_Raw', 'Flag_Capped', 'File_Berkas', 'Flag_Pica', 'Flag_Aktif'],
                );
            }

            // Parameter yang kini mencapai target (Flag_Pica != 'Y') tidak boleh
            // menyisakan konten PICA lama di tabel sementara.
            $this->pruneStalePicaSementara($idHeader);

            // Pertahankan PICA sebelumnya (atau simpan PICA baru) untuk parameter yang
            // masih memerlukan PICA, dari payload yang dikirim frontend.
            $this->persistPicaSementaraFromRequest($idHeader, $kpiDataFromRequest);

            $detailsData = DB::table('KPI_Update_Performance_Detail_Sementara as upds')
                ->join('KPI_Pengajuan_Det as pd', 'upds.Id_Pengajuan_Det', '=', 'pd.Id_Pengajuan_Det')
                ->join(
                    'KPI_Pengajuan_Detail as p_detail',
                    'pd.Id_Pengajuan_Detail',
                    '=',
                    'p_detail.Id_Pengajuan_Detail',
                )
                ->where('upds.Id_Update_Performance_Sementara', $idHeader)
                ->where('upds.Flag_Aktif', 'Y')
                ->select('upds.Achievement', 'upds.Achievement_Raw', 'p_detail.Bobot')
                ->get();

            $scores = $this->calculateScores($detailsData);
            // Cap skor sebelum lookup level — sama seperti jalur finalisasi. Tanpa ini,
            // skor total negatif (nilai actual buruk) tak cocok band mana pun & throw
            // "Konfigurasi Master Performance tidak ditemukan atau tidak lengkap".
            $scores = $this->applyAchievementCap($scores);
            $scoreForChecking = floor($scores['final_score_percentage']);
            $performanceLevel = $this->getPerformanceLevel($scoreForChecking);

            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeader)
                ->update([
                    'Total_Score' => $scores['final_score_percentage'],
                    'Total_Score_Achievement' => $scores['sum_of_scores'],
                    'Total_Bobot' => $scores['sum_of_active_bobot'],
                    'Id_Performance' => $performanceLevel->Id_Master_Performance,
                ]);

            DB::commit();

            $savedDetails = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeader)
                ->get();

            return ResponseHelper::success($savedDetails, 'Draf berhasil disimpan.', 201);
        } catch (\Exception $e) {
            $this->safeRollback();
            // Transaksi gagal → hapus berkas yang sudah terlanjur ter-upload ke GCS
            // (upload dilakukan sebelum transaksi) agar tidak menjadi orphan.
            $this->cleanupUploadedGcsFiles($newlyUploadedGcsPaths ?? []);
            Log::channel('UpdatePerformanceController')->error(
                'STORETEMPORARYPERFORMANCESTEP1 ERROR: '.$e->getMessage().' Line: '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal menyimpan data: '.$e->getMessage(), 500);
        }
    }

    /**
     * Pastikan karyawan sasaran benar-benar anggota tim pengguna yang login.
     *
     * Jalur TULIS tim (simpan draf & finalisasi) menerima id karyawan dan id
     * pengajuan APA ADANYA dari permintaan; tidak ada satu pun kueri di dalamnya
     * yang menyempitkan sasaran ke tim si pemanggil. Artinya siapa pun yang punya
     * kapabilitas UpdatePerformanceTeam bisa menulis penilaian untuk karyawan mana
     * pun di perusahaan hanya dengan mengganti id di URL — dan karena penilaian
     * itu lalu berjalan di alur approval seperti biasa, tidak ada satu pun tanda
     * bahwa asalnya keliru.
     *
     * Lubang ini LEBIH TUA dari perubahan keanggotaan berbasis roster: pada versi
     * sebelum itu pun kedua metode ini tidak memeriksa apa pun. Yang berubah hanya
     * bahwa sekarang ada satu definisi keanggotaan yang jelas untuk dipakai.
     */
    private function tolakBukanAnggotaTim(int $pengguna, $karyawan): ?\Illuminate\Http\JsonResponse
    {
        $idKaryawan = (int) ($karyawan->UserID_Web ?? 0);

        if ($idKaryawan > 0 && $this->anggotaTimSaya($pengguna)->contains($idKaryawan)) {
            return null;
        }

        Log::channel('UpdatePerformanceController')->warning('AKSES_LUAR_TIM_DITOLAK', [
            'pengguna' => $pengguna,
            'sasaran' => $idKaryawan,
            'kode_karyawan' => $karyawan->Kode_Karyawan ?? null,
        ]);

        return ResponseHelper::error('Karyawan ini bukan anggota tim Anda.', 403);
    }

    public function storeTemporaryPerformanceStep1ByTeam(Request $request, $user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $request->validate([
            'kpi_data' => 'required|array',
            'kpi_data.*.Id_Pengajuan' => 'required',
            'kpi_data.*.Id_Pengajuan_Detail' => 'required',
            'kpi_data.*.Id_Pengajuan_Det' => 'required',
            'kpi_data.*.is_input_period' => 'required|in:true,false',
            'current_step' => 'required',
            'Id_Master_Penjadwalan' => 'required|string',
            'rejected_transaction_ref' => 'nullable|string',
        ]);

        try {
            $decodedPenjadwalanId = Hashids::connection('custom')->decode($request->Id_Master_Penjadwalan)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Tidak Valid.', 400);
        }

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

        if (! $karyawan) {
            return ResponseHelper::error('Data Tidak Ditemukan !', 404);
        }

        if ($tolak = $this->tolakBukanAnggotaTim((int) Auth::user()->Id_Users, $karyawan)) {
            return $tolak;
        }

        $now = Carbon::now('Asia/Jakarta');

        $penjadwalan = DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $decodedPenjadwalanId)
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Jadwal_Mulai', '<=', $now)
            ->where('Jadwal_Akhir', '>=', $now)
            ->first();

        if (! $penjadwalan) {
            return ResponseHelper::error('Jadwal penilaian KPI tidak ditemukan atau sudah berakhir.', 404);
        }

        if (! $this->punyaTargetPeriode([(int) $Id_User], $penjadwalan->Periode)[(int) $Id_User]) {
            return ResponseHelper::error($this->pesanTanpaTarget($penjadwalan->Periode), 422);
        }

        $targetDate = Carbon::parse($penjadwalan->Periode);

        // Upload berkas KPI ke GCS SEBELUM transaksi agar HTTP upload tidak
        // menahan lock DB. Bila transaksi gagal, file baru dihapus di catch.
        try {
            [$preUploadedKpiFiles, $newlyUploadedGcsPaths] = $this->preUploadKpiFilesStream($request);
        } catch (\Throwable $e) {
            Log::channel('UpdatePerformanceController')->error('Pre-upload berkas KPI gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengunggah berkas: '.$e->getMessage(), 500);
        }

        /* Nomor sementara diambil SEBELUM transaksi dibuka: counter-nya mengunci
           satu baris selama satu UPDATE autocommit. Di dalam transaksi, kunci
           itu tertahan sampai commit dan submit bersamaan kembali antre. */
        $noTransaksiSementara = $this->generateNomorSementara();

        DB::beginTransaction();

        try {
            $penggunaSeeder = Auth::user()->Id_Users;
            $kpiDataFromRequest = $request->kpi_data;
            $rejectedTransactionRef = $request->rejected_transaction_ref;

            $firstItem = $kpiDataFromRequest[0] ?? null;
            if (! $firstItem || ! isset($firstItem['Id_Pengajuan'])) {
                throw new \Exception('Tidak ada data pengajuan yang valid untuk diproses.');
            }

            $Id_Pengajuan = Hashids::connection('custom')->decode($firstItem['Id_Pengajuan'])[0] ?? null;
            if (! $Id_Pengajuan) {
                throw new \Exception('ID Pengajuan tidak valid.');
            }

            [$idHeader] = $this->handleHeaderByTeam(
                $karyawan->UserID_Web,
                $penggunaSeeder,
                $Id_Pengajuan,
                $request->current_step,
                $rejectedTransactionRef,
                $decodedPenjadwalanId,
                $noTransaksiSementara,
            );

            $decodedDetIds = collect($kpiDataFromRequest)
                ->map(fn ($d) => Hashids::connection('custom')->decode($d['Id_Pengajuan_Det'])[0] ?? null)
                ->filter()
                ->all();

            $decodedDetailIds = collect($kpiDataFromRequest)
                ->map(fn ($d) => Hashids::connection('custom')->decode($d['Id_Pengajuan_Detail'])[0] ?? null)
                ->filter()
                ->all();

            $targetRows = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Det', $decodedDetIds)
                ->get()
                ->keyBy('Id_Pengajuan_Det');

            $detailRows = DB::table('KPI_Pengajuan_Detail')
                ->whereIn('Id_Pengajuan_Detail', $decodedDetailIds)
                ->get()
                ->keyBy('Id_Pengajuan_Detail');

            $nilaiMaks = DB::table('KPI_Master_Grading')->value('Nilai_Maksimum') ?? 100;

            $formulas = DB::table('KPI_Master_Formula')->pluck('Rumus', 'Id_Master_Formula')->toArray();

            $upsertDetails = [];

            foreach ($kpiDataFromRequest as $index => $performance) {
                $performance = (object) $performance;

                if (! isset($performance->Actual) || ! is_numeric($performance->Actual)) {
                    continue;
                }

                $Id_Pengajuan_Det = Hashids::connection('custom')->decode($performance->Id_Pengajuan_Det)[0] ?? null;
                $Id_Pengajuan_Detail =
                    Hashids::connection('custom')->decode($performance->Id_Pengajuan_Detail)[0] ?? null;
                if (! $Id_Pengajuan_Det || ! $Id_Pengajuan_Detail) {
                    continue;
                }

                $fileBerkas = $preUploadedKpiFiles[$index] ?? null;

                $targetRow = $targetRows->get($Id_Pengajuan_Det);
                $detail = $detailRows->get($Id_Pengajuan_Detail);
                if (! $targetRow || ! $detail) {
                    continue;
                }

                $calc = $this->calculateFormulaStream(
                    $performance->Actual,
                    $targetRow->Nilai_Target,
                    $detail->Bobot,
                    $detail->Id_Master_Formula,
                    $formulas,
                );

                $flagPica = $calc['achievement'] < $nilaiMaks ? 'Y' : 'T';

                $periodeDate = Carbon::parse($targetRow->Periode);
                $flagAktif = $periodeDate->format('Y-m') === $targetDate->format('Y-m') ? 'Y' : 'T';

                $newData = [
                    'Id_Update_Performance_Sementara' => $idHeader,
                    'Id_Pengajuan_Det' => $Id_Pengajuan_Det,
                    'Nilai_Actual' => (float) $performance->Actual,
                    'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                    'Score' => (float) $calc['achievement'],
                    'Achievement' => (float) $calc['score'],
                    'Score_Raw' => (float) ($calc['achievement_raw'] ?? $calc['achievement']),
                    'Achievement_Raw' => (float) ($calc['score_raw'] ?? $calc['score']),
                    'Flag_Capped' => ! empty($calc['is_capped']) ? 'Y' : 'T',
                    'File_Berkas' => $fileBerkas,
                    'Flag_Pica' => $flagPica,
                    'Flag_Aktif' => $flagAktif,
                ];

                $upsertDetails[] = $newData;
            }

            if (! empty($upsertDetails)) {
                DB::table('KPI_Update_Performance_Detail_Sementara')->upsert(
                    $upsertDetails,
                    ['Id_Update_Performance_Sementara', 'Id_Pengajuan_Det'],
                    ['Nilai_Actual', 'Score', 'Achievement', 'Score_Raw', 'Achievement_Raw', 'Flag_Capped', 'File_Berkas', 'Flag_Pica', 'Flag_Aktif'],
                );
            }

            // Parameter yang kini mencapai target (Flag_Pica != 'Y') tidak boleh
            // menyisakan konten PICA lama di tabel sementara.
            $this->pruneStalePicaSementara($idHeader);

            // Pertahankan PICA sebelumnya (atau simpan PICA baru) untuk parameter yang
            // masih memerlukan PICA, dari payload yang dikirim frontend.
            $this->persistPicaSementaraFromRequest($idHeader, $kpiDataFromRequest);

            $detailsData = DB::table('KPI_Update_Performance_Detail_Sementara as upds')
                ->join('KPI_Pengajuan_Det as pd', 'upds.Id_Pengajuan_Det', '=', 'pd.Id_Pengajuan_Det')
                ->join(
                    'KPI_Pengajuan_Detail as p_detail',
                    'pd.Id_Pengajuan_Detail',
                    '=',
                    'p_detail.Id_Pengajuan_Detail',
                )
                ->where('upds.Id_Update_Performance_Sementara', $idHeader)
                ->where('upds.Flag_Aktif', 'Y')
                ->select('upds.Achievement', 'upds.Achievement_Raw', 'p_detail.Bobot')
                ->get();

            $scores = $this->calculateScores($detailsData);
            // Cap skor sebelum lookup level — sama seperti jalur finalisasi. Tanpa ini,
            // skor total negatif (nilai actual buruk) tak cocok band mana pun & throw
            // "Konfigurasi Master Performance tidak ditemukan atau tidak lengkap".
            $scores = $this->applyAchievementCap($scores);
            $scoreForChecking = floor($scores['final_score_percentage']);
            $performanceLevel = $this->getPerformanceLevel($scoreForChecking);

            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeader)
                ->update([
                    'Total_Score' => $scores['final_score_percentage'],
                    'Total_Score_Achievement' => $scores['sum_of_scores'],
                    'Total_Bobot' => $scores['sum_of_active_bobot'],
                    'Id_Performance' => $performanceLevel->Id_Master_Performance,
                ]);

            DB::commit();

            $savedDetails = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeader)
                ->get();

            return ResponseHelper::success($savedDetails, 'Draf berhasil disimpan.', 201);
        } catch (\Exception $e) {
            $this->safeRollback();
            // Transaksi gagal → hapus berkas yang sudah terlanjur ter-upload ke GCS
            // (upload dilakukan sebelum transaksi) agar tidak menjadi orphan.
            $this->cleanupUploadedGcsFiles($newlyUploadedGcsPaths ?? []);
            Log::channel('UpdatePerformanceController')->error(
                'STORETEMPORARYPERFORMANCESTEP1 ERROR: '.$e->getMessage().' Line: '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal menyimpan data: '.$e->getMessage(), 500);
        }
    }

    public function storeTemporaryPerformanceStep1ByTeamByPass(Request $request, $user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $request->validate([
            'kpi_data' => 'required|array',
            'kpi_data.*.Id_Pengajuan' => 'required',
            'kpi_data.*.Id_Pengajuan_Detail' => 'required',
            'kpi_data.*.Id_Pengajuan_Det' => 'required',
            'kpi_data.*.is_input_period' => 'required|in:true,false',
            'current_step' => 'required',
            'rejected_transaction_ref' => 'nullable|string',
            'Id_Master_Penjadwalan' => 'nullable|string',
        ]);

        $penggunaSeeder = Auth::user();

        // Jadwal yang sedang dibuka halaman menang, supaya yang tampil di layar itulah
        // yang tersimpan. Tanpa parameter, tetap memakai acuan by-pass yang lama.
        $decodedPenjadwalanId = null;

        if ($request->filled('Id_Master_Penjadwalan')) {
            $idDariHalaman = Hashids::connection('custom')->decode($request->input('Id_Master_Penjadwalan'))[0] ?? null;

            if ($idDariHalaman) {
                $decodedPenjadwalanId = DB::table('KPI_Master_Penjadwalan')
                    ->where('Id_Master_Penjadwalan', $idDariHalaman)
                    ->value('Id_Master_Penjadwalan');
            }
        }

        if (! $decodedPenjadwalanId) {
            $dummyData = DB::table('KPI_Get_Date_Dummy2')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Get_Date2', 'DESC')
                ->first();

            if (! empty($dummyData->Id_Master_Penjadwalan)) {
                $decodedPenjadwalanId = $dummyData->Id_Master_Penjadwalan;
            } else {
                // Sama seperti halaman detailnya: periode acuan yang menentukan,
                // bukan tahun berjalan.
                $periodeAcuan = ! empty($dummyData->Tanggal)
                    ? Carbon::parse($dummyData->Tanggal)
                    : Carbon::now('Asia/Jakarta');

                $fallbackJadwal = $this->jadwalPenilaianUntukPeriode($periodeAcuan);

                if (! $fallbackJadwal) {
                    return ResponseHelper::error(
                        'Belum ada jadwal penilaian untuk periode '.$periodeAcuan->format('Y-m').'.',
                        404,
                    );
                }

                $decodedPenjadwalanId = $fallbackJadwal->Id_Master_Penjadwalan;
            }
        }

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

        if (! $karyawan) {
            return ResponseHelper::error('Data Tidak Ditemukan !', 404);
        }

        // By-pass boleh di luar jendela jadwal, tetapi tidak boleh mengisi periode
        // yang memang tak punya target jatuh tempo.
        $periodeBypass = DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', $decodedPenjadwalanId)
            ->value('Periode');

        if (! $this->punyaTargetPeriode([(int) $Id_User], $periodeBypass)[(int) $Id_User]) {
            return ResponseHelper::error($this->pesanTanpaTarget($periodeBypass), 422);
        }

        // Upload berkas KPI ke GCS SEBELUM transaksi agar HTTP upload tidak
        // menahan lock DB. Bila transaksi gagal, file baru dihapus di catch.
        try {
            [$preUploadedKpiFiles, $newlyUploadedGcsPaths] = $this->preUploadKpiFilesStream($request);
        } catch (\Throwable $e) {
            Log::channel('UpdatePerformanceController')->error('Pre-upload berkas KPI gagal: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengunggah berkas: '.$e->getMessage(), 500);
        }

        /* Nomor sementara diambil SEBELUM transaksi dibuka: counter-nya mengunci
           satu baris selama satu UPDATE autocommit. Di dalam transaksi, kunci
           itu tertahan sampai commit dan submit bersamaan kembali antre. */
        $noTransaksiSementara = $this->generateNomorSementara();

        DB::beginTransaction();

        try {
            $penggunaSeeder = Auth::user()->Id_Users;
            $kpiDataFromRequest = $request->kpi_data;
            $rejectedTransactionRef = $request->rejected_transaction_ref;

            $firstItem = $kpiDataFromRequest[0] ?? null;
            if (! $firstItem || ! isset($firstItem['Id_Pengajuan'])) {
                throw new \Exception('Tidak ada data pengajuan yang valid untuk diproses.');
            }

            $Id_Pengajuan = Hashids::connection('custom')->decode($firstItem['Id_Pengajuan'])[0] ?? null;
            if (! $Id_Pengajuan) {
                throw new \Exception('ID Pengajuan tidak valid.');
            }

            [$idHeader] = $this->handleHeaderByTeamByPass(
                $karyawan->UserID_Web,
                $penggunaSeeder,
                $Id_Pengajuan,
                $request->current_step,
                $rejectedTransactionRef,
                $decodedPenjadwalanId,
                $noTransaksiSementara,
            );

            $decodedDetIds = collect($kpiDataFromRequest)
                ->map(fn ($d) => Hashids::connection('custom')->decode($d['Id_Pengajuan_Det'])[0] ?? null)
                ->filter()
                ->all();

            $decodedDetailIds = collect($kpiDataFromRequest)
                ->map(fn ($d) => Hashids::connection('custom')->decode($d['Id_Pengajuan_Detail'])[0] ?? null)
                ->filter()
                ->all();

            $targetRows = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Det', $decodedDetIds)
                ->get()
                ->keyBy('Id_Pengajuan_Det');

            $detailRows = DB::table('KPI_Pengajuan_Detail')
                ->whereIn('Id_Pengajuan_Detail', $decodedDetailIds)
                ->get()
                ->keyBy('Id_Pengajuan_Detail');

            $nilaiMaks = DB::table('KPI_Master_Grading')->value('Nilai_Maksimum') ?? 100;

            $formulas = DB::table('KPI_Master_Formula')->pluck('Rumus', 'Id_Master_Formula')->toArray();

            $upsertDetails = [];

            foreach ($kpiDataFromRequest as $index => $performance) {
                $performance = (object) $performance;
                $isInputPeriod = filter_var($performance->is_input_period, FILTER_VALIDATE_BOOLEAN);

                if (! isset($performance->Actual) || ! is_numeric($performance->Actual)) {
                    continue;
                }

                $Id_Pengajuan_Det = Hashids::connection('custom')->decode($performance->Id_Pengajuan_Det)[0] ?? null;
                $Id_Pengajuan_Detail =
                    Hashids::connection('custom')->decode($performance->Id_Pengajuan_Detail)[0] ?? null;
                if (! $Id_Pengajuan_Det || ! $Id_Pengajuan_Detail) {
                    continue;
                }

                $fileBerkas = $preUploadedKpiFiles[$index] ?? null;

                $targetRow = $targetRows->get($Id_Pengajuan_Det);
                $detail = $detailRows->get($Id_Pengajuan_Detail);
                if (! $targetRow || ! $detail) {
                    continue;
                }

                $calc = $this->calculateFormulaStream(
                    $performance->Actual,
                    $targetRow->Nilai_Target,
                    $detail->Bobot,
                    $detail->Id_Master_Formula,
                    $formulas,
                );

                $flagPica = $calc['achievement'] < $nilaiMaks ? 'Y' : 'T';
                $flagAktif = $isInputPeriod ? 'Y' : 'T';

                $newData = [
                    'Id_Update_Performance_Sementara' => $idHeader,
                    'Id_Pengajuan_Det' => $Id_Pengajuan_Det,
                    'Nilai_Actual' => (float) $performance->Actual,
                    'Kode_Karyawan' => $karyawan->Kode_Karyawan,
                    'Score' => (float) $calc['achievement'],
                    'Achievement' => (float) $calc['score'],
                    'Score_Raw' => (float) ($calc['achievement_raw'] ?? $calc['achievement']),
                    'Achievement_Raw' => (float) ($calc['score_raw'] ?? $calc['score']),
                    'Flag_Capped' => ! empty($calc['is_capped']) ? 'Y' : 'T',
                    'File_Berkas' => $fileBerkas,
                    'Flag_Pica' => $flagPica,
                    'Flag_Aktif' => $flagAktif,
                ];

                $upsertDetails[] = $newData;
            }

            if (! empty($upsertDetails)) {
                DB::table('KPI_Update_Performance_Detail_Sementara')->upsert(
                    $upsertDetails,
                    ['Id_Update_Performance_Sementara', 'Id_Pengajuan_Det'],
                    ['Nilai_Actual', 'Score', 'Achievement', 'Score_Raw', 'Achievement_Raw', 'Flag_Capped', 'File_Berkas', 'Flag_Pica', 'Flag_Aktif'],
                );
            }

            // Parameter yang kini mencapai target (Flag_Pica != 'Y') tidak boleh
            // menyisakan konten PICA lama di tabel sementara.
            $this->pruneStalePicaSementara($idHeader);

            // Pertahankan PICA sebelumnya (atau simpan PICA baru) untuk parameter yang
            // masih memerlukan PICA, dari payload yang dikirim frontend.
            $this->persistPicaSementaraFromRequest($idHeader, $kpiDataFromRequest);

            $detailsData = DB::table('KPI_Update_Performance_Detail_Sementara as upds')
                ->join('KPI_Pengajuan_Det as pd', 'upds.Id_Pengajuan_Det', '=', 'pd.Id_Pengajuan_Det')
                ->join(
                    'KPI_Pengajuan_Detail as p_detail',
                    'pd.Id_Pengajuan_Detail',
                    '=',
                    'p_detail.Id_Pengajuan_Detail',
                )
                ->where('upds.Id_Update_Performance_Sementara', $idHeader)
                ->where('upds.Flag_Aktif', 'Y')
                ->select('upds.Achievement', 'upds.Achievement_Raw', 'p_detail.Bobot')
                ->get();

            $scores = $this->calculateScores($detailsData);
            // Cap skor sebelum lookup level — sama seperti jalur finalisasi. Tanpa ini,
            // skor total negatif (nilai actual buruk) tak cocok band mana pun & throw
            // "Konfigurasi Master Performance tidak ditemukan atau tidak lengkap".
            $scores = $this->applyAchievementCap($scores);
            $scoreForChecking = floor($scores['final_score_percentage']);
            $performanceLevel = $this->getPerformanceLevel($scoreForChecking);

            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeader)
                ->update([
                    'Total_Score' => $scores['final_score_percentage'],
                    'Total_Score_Achievement' => $scores['sum_of_scores'],
                    'Total_Bobot' => $scores['sum_of_active_bobot'],
                    'Id_Performance' => $performanceLevel->Id_Master_Performance,
                ]);

            DB::commit();

            $savedDetails = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeader)
                ->get();

            return ResponseHelper::success($savedDetails, 'Draf berhasil disimpan.', 201);
        } catch (\Exception $e) {
            $this->safeRollback();
            // Transaksi gagal → hapus berkas yang sudah terlanjur ter-upload ke GCS
            // (upload dilakukan sebelum transaksi) agar tidak menjadi orphan.
            $this->cleanupUploadedGcsFiles($newlyUploadedGcsPaths ?? []);
            Log::channel('UpdatePerformanceController')->error(
                'STORETEMPORARYPERFORMANCESTEP1 ERROR: '.$e->getMessage().' Line: '.$e->getLine(),
            );

            return ResponseHelper::error('Gagal menyimpan data: '.$e->getMessage(), 500);
        }
    }

    private function handleHeaderByTeamByPass(
        $KaryawanIdUser,
        $penggunaSender,
        $Id_Pengajuan,
        $step,
        $rejectedTransactionRef,
        $idPenjadwalan,
        string $noTransaksiSementara,
    ) {
        Log::channel('UpdatePerformanceController')->info('================================================');
        Log::channel('UpdatePerformanceController')->info('MEMULAI handleHeaderByTeamByPass pada '.now());
        Log::channel('UpdatePerformanceController')->info('PARAMETER INPUT:', [
            'pengguna' => $penggunaSender,
            'Id_Pengajuan' => $Id_Pengajuan,
            'step' => $step,
            'rejectedTransactionRef' => $rejectedTransactionRef,
        ]);

        $creationTime = now();

        $masterJadwal = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $idPenjadwalan)->first();

        if (! $masterJadwal) {
            throw new \Exception('Data Penjadwalan tidak ditemukan untuk ID: '.$idPenjadwalan);
        }

        $periode = Carbon::parse($masterJadwal->Periode)->format('Y-m');

        Log::channel('UpdatePerformanceController')->info("TARGET PERIODE (From Schedule): {$periode}");

        Log::channel('UpdatePerformanceController')->info(
            'Mencari draf yang bisa diedit (Flag_Aktif=Y DAN Flag_Pindah!=Y)...',
        );
        $editableDraft = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $Id_Pengajuan)
            ->where('Id_User', $KaryawanIdUser)
            ->where('Periode', $periode)
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y');
            })
            ->first();

        if ($editableDraft) {
            Log::channel('UpdatePerformanceController')->info(
                'HASIL: Ditemukan draf yang bisa diedit.',
                (array) $editableDraft,
            );
        } else {
            Log::channel('UpdatePerformanceController')->info('HASIL: TIDAK ditemukan draf yang bisa diedit.');
        }

        if ($editableDraft) {
            Log::channel('UpdatePerformanceController')->info(
                'KEPUTUSAN: Ditemukan draf yang bisa diedit, masuk ke jalur UPDATE.',
            );
            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $editableDraft->Id_Update_Performance_Sementara)
                ->update([
                    'Jam_Terakhir' => $creationTime->format('H:i:s'),
                    'Tanggal_Terakhir' => $creationTime,
                    'Steps' => $step,
                ]);
            Log::channel('UpdatePerformanceController')->info(
                "SUCCESS: Berhasil meng-update draf ID: {$editableDraft->Id_Update_Performance_Sementara}.",
            );

            return [$editableDraft->Id_Update_Performance_Sementara, $editableDraft->No_Transaksi_Sementara, collect()];
        }

        // INFO, bukan ERROR. Ini jalur NORMAL — pengisian pertama pada sebuah
        // periode memang tidak punya draf untuk diedit. Ditulis sebagai ERROR ia
        // muncul 32 kali sehari pada log uji 10 September 2026 dan mendorong
        // galat sungguhan keluar dari pandangan.
        Log::channel('UpdatePerformanceController')->info(
            'KEPUTUSAN: TIDAK ada draf yang bisa diedit, masuk ke jalur INSERT.',
        );

        $oldDetails = collect();
        $tempRefNo = null;

        $lastHeaderForPeriod = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $Id_Pengajuan)
            ->where('Id_User', $KaryawanIdUser)
            ->where('Periode', $periode)
            ->latest('Tanggal_Terakhir')
            ->first();

        if ($rejectedTransactionRef) {
            Log::channel('UpdatePerformanceController')->info(
                "Ini adalah revisi baru, ref dari frontend: {$rejectedTransactionRef}",
            );

            // No_Transaksi_Ref WAJIB menunjuk ke pengajuan FINAL yang DITOLAK
            // (No_Transaksi AKPI), bukan nomor draf/temporary terakhir. Nomor temp
            // (TAKPI) dan final (AKPI) adalah dua counter terpisah, sehingga konversi
            // string TAKPI->AKPI menghasilkan ref yang salah (menunjuk ke nomor final
            // lain). rejected_transaction_ref dari FE bisa AKPI (dari data final) atau
            // TAKPI (dari draf) — resolve ke pengajuan final yang ditolak.
            $rejectedFinalHeader = DB::table('KPI_Update_Performance')
                ->where('No_Transaksi', $rejectedTransactionRef)
                ->first();

            if (! $rejectedFinalHeader) {
                $rejectedFinalHeader = DB::table('KPI_Update_Performance')
                    ->where('Id_Pengajuan', $Id_Pengajuan)
                    ->where('Id_User', $KaryawanIdUser)
                    ->where('Periode', $periode)
                    ->where('Status_Pengajuan', 'ditolak')
                    ->latest('Tanggal')
                    ->first();
            }

            if ($rejectedFinalHeader) {
                $tempRefNo = $rejectedFinalHeader->No_Transaksi;
                Log::channel('UpdatePerformanceController')->info(
                    "No Transaksi Ref (pengajuan ditolak) untuk draf baru: {$tempRefNo}",
                );

                $oldDetails = DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $rejectedFinalHeader->Id_Update_Performance)
                    ->get()
                    ->keyBy('Id_Pengajuan_Det');
            }
        } else {
            Log::channel('UpdatePerformanceController')->info('Ini adalah draf awal (bukan revisi).');
        }

        if ($lastHeaderForPeriod) {
            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $lastHeaderForPeriod->Id_Update_Performance_Sementara)
                ->update(['Flag_Aktif' => 'T']);
            Log::channel('UpdatePerformanceController')->info(
                "Draf lama (ID: {$lastHeaderForPeriod->Id_Update_Performance_Sementara}) dinonaktifkan.",
            );
        }

        /* Nomornya sudah diambil pemanggil SEBELUM transaksi dibuka; lihat
           generateNomorSementara(). Membuatnya di sini berarti membuatnya di
           dalam transaksi, dan itu justru sumber antreannya. */
        $noTransaksi = $noTransaksiSementara;
        Log::channel('UpdatePerformanceController')->info("Nomor transaksi baru dibuat: {$noTransaksi}");

        $idHeader = DB::table('KPI_Update_Performance_Sementara')->insertGetId([
            'No_Transaksi_Sementara' => $noTransaksi,
            'Id_Penjadwalan' => $idPenjadwalan,
            'No_Transaksi_Ref' => $tempRefNo,
            'Id_Pengajuan' => $Id_Pengajuan,
            'Periode' => $periode,
            'Status_Pengajuan' => 'drafted',
            'Jam' => $creationTime->format('H:i:s'),
            'Tanggal' => $creationTime,
            'Jam_Terakhir' => $creationTime->format('H:i:s'),
            'Tanggal_Terakhir' => $creationTime,
            'Flag_Aktif' => 'Y',
            'Id_User' => $KaryawanIdUser,
            'Steps' => $step,
        ]);

        Log::channel('UpdatePerformanceController')->info(
            "SUCCESS: Draf baru berhasil di-insert dengan ID: {$idHeader}.",
        );

        return [$idHeader, $noTransaksi, $oldDetails];
    }

    private function handleHeaderByTeam(
        $KaryawanIdUser,
        $penggunaSender,
        $Id_Pengajuan,
        $step,
        $rejectedTransactionRef,
        $idPenjadwalan,
        string $noTransaksiSementara,
    ) {
        Log::channel('UpdatePerformanceController')->info('================================================');
        Log::channel('UpdatePerformanceController')->info('MEMULAI handleHeaderByTeam pada '.now());
        Log::channel('UpdatePerformanceController')->info('PARAMETER INPUT:', [
            'pengguna' => $penggunaSender,
            'Id_Pengajuan' => $Id_Pengajuan,
            'step' => $step,
            'rejectedTransactionRef' => $rejectedTransactionRef,
        ]);

        $creationTime = now();

        $masterJadwal = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $idPenjadwalan)->first();

        if (! $masterJadwal) {
            throw new \Exception('Data Penjadwalan tidak ditemukan untuk ID: '.$idPenjadwalan);
        }

        $periode = Carbon::parse($masterJadwal->Periode)->format('Y-m');

        Log::channel('UpdatePerformanceController')->info("TARGET PERIODE (From Schedule): {$periode}");

        Log::channel('UpdatePerformanceController')->info(
            'Mencari draf yang bisa diedit (Flag_Aktif=Y DAN Flag_Pindah!=Y)...',
        );
        $editableDraft = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $Id_Pengajuan)
            ->where('Id_User', $KaryawanIdUser)
            ->where('Periode', $periode)
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y');
            })
            ->first();

        if ($editableDraft) {
            Log::channel('UpdatePerformanceController')->info(
                'HASIL: Ditemukan draf yang bisa diedit.',
                (array) $editableDraft,
            );
        } else {
            Log::channel('UpdatePerformanceController')->info('HASIL: TIDAK ditemukan draf yang bisa diedit.');
        }

        if ($editableDraft) {
            Log::channel('UpdatePerformanceController')->info(
                'KEPUTUSAN: Ditemukan draf yang bisa diedit, masuk ke jalur UPDATE.',
            );
            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $editableDraft->Id_Update_Performance_Sementara)
                ->update([
                    'Jam_Terakhir' => $creationTime->format('H:i:s'),
                    'Tanggal_Terakhir' => $creationTime,
                    'Steps' => $step,
                ]);
            Log::channel('UpdatePerformanceController')->info(
                "SUCCESS: Berhasil meng-update draf ID: {$editableDraft->Id_Update_Performance_Sementara}.",
            );

            return [$editableDraft->Id_Update_Performance_Sementara, $editableDraft->No_Transaksi_Sementara, collect()];
        }

        // INFO, bukan ERROR. Ini jalur NORMAL — pengisian pertama pada sebuah
        // periode memang tidak punya draf untuk diedit. Ditulis sebagai ERROR ia
        // muncul 32 kali sehari pada log uji 10 September 2026 dan mendorong
        // galat sungguhan keluar dari pandangan.
        Log::channel('UpdatePerformanceController')->info(
            'KEPUTUSAN: TIDAK ada draf yang bisa diedit, masuk ke jalur INSERT.',
        );

        $oldDetails = collect();
        $tempRefNo = null;

        $lastHeaderForPeriod = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $Id_Pengajuan)
            ->where('Id_User', $KaryawanIdUser)
            ->where('Periode', $periode)
            ->latest('Tanggal_Terakhir')
            ->first();

        if ($rejectedTransactionRef) {
            Log::channel('UpdatePerformanceController')->info(
                "Ini adalah revisi baru, ref dari frontend: {$rejectedTransactionRef}",
            );

            // No_Transaksi_Ref WAJIB menunjuk ke pengajuan FINAL yang DITOLAK
            // (No_Transaksi AKPI), bukan nomor draf/temporary terakhir. Nomor temp
            // (TAKPI) dan final (AKPI) adalah dua counter terpisah, sehingga konversi
            // string TAKPI->AKPI menghasilkan ref yang salah (menunjuk ke nomor final
            // lain). rejected_transaction_ref dari FE bisa AKPI (dari data final) atau
            // TAKPI (dari draf) — resolve ke pengajuan final yang ditolak.
            $rejectedFinalHeader = DB::table('KPI_Update_Performance')
                ->where('No_Transaksi', $rejectedTransactionRef)
                ->first();

            if (! $rejectedFinalHeader) {
                $rejectedFinalHeader = DB::table('KPI_Update_Performance')
                    ->where('Id_Pengajuan', $Id_Pengajuan)
                    ->where('Id_User', $KaryawanIdUser)
                    ->where('Periode', $periode)
                    ->where('Status_Pengajuan', 'ditolak')
                    ->latest('Tanggal')
                    ->first();
            }

            if ($rejectedFinalHeader) {
                $tempRefNo = $rejectedFinalHeader->No_Transaksi;
                Log::channel('UpdatePerformanceController')->info(
                    "No Transaksi Ref (pengajuan ditolak) untuk draf baru: {$tempRefNo}",
                );

                $oldDetails = DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $rejectedFinalHeader->Id_Update_Performance)
                    ->get()
                    ->keyBy('Id_Pengajuan_Det');
            }
        } else {
            Log::channel('UpdatePerformanceController')->info('Ini adalah draf awal (bukan revisi).');
        }

        if ($lastHeaderForPeriod) {
            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $lastHeaderForPeriod->Id_Update_Performance_Sementara)
                ->update(['Flag_Aktif' => 'T']);
            Log::channel('UpdatePerformanceController')->info(
                "Draf lama (ID: {$lastHeaderForPeriod->Id_Update_Performance_Sementara}) dinonaktifkan.",
            );
        }

        /* Nomornya sudah diambil pemanggil SEBELUM transaksi dibuka; lihat
           generateNomorSementara(). Membuatnya di sini berarti membuatnya di
           dalam transaksi, dan itu justru sumber antreannya. */
        $noTransaksi = $noTransaksiSementara;
        Log::channel('UpdatePerformanceController')->info("Nomor transaksi baru dibuat: {$noTransaksi}");

        $idHeader = DB::table('KPI_Update_Performance_Sementara')->insertGetId([
            'No_Transaksi_Sementara' => $noTransaksi,
            'Id_Penjadwalan' => $idPenjadwalan,
            'No_Transaksi_Ref' => $tempRefNo,
            'Id_Pengajuan' => $Id_Pengajuan,
            'Periode' => $periode,
            'Status_Pengajuan' => 'drafted',
            'Jam' => $creationTime->format('H:i:s'),
            'Tanggal' => $creationTime,
            'Jam_Terakhir' => $creationTime->format('H:i:s'),
            'Tanggal_Terakhir' => $creationTime,
            'Flag_Aktif' => 'Y',
            'Id_User' => $KaryawanIdUser,
            'Steps' => $step,
            'Flag_Team' => 'Y',
            'Id_User_Sender' => $penggunaSender,
        ]);

        Log::channel('UpdatePerformanceController')->info(
            "SUCCESS: Draf baru berhasil di-insert dengan ID: {$idHeader}.",
        );

        return [$idHeader, $noTransaksi, $oldDetails];
    }

    private function handleHeader(
        $pengguna,
        $Id_Pengajuan,
        $step,
        $rejectedTransactionRef,
        $idPenjadwalan,
        string $noTransaksiSementara,
    ) {
        Log::channel('UpdatePerformanceController')->info('================================================');
        Log::channel('UpdatePerformanceController')->info('MEMULAI handleHeader pada '.now());
        Log::channel('UpdatePerformanceController')->info('PARAMETER INPUT:', [
            'pengguna' => $pengguna,
            'Id_Pengajuan' => $Id_Pengajuan,
            'step' => $step,
            'rejectedTransactionRef' => $rejectedTransactionRef,
        ]);

        $creationTime = now();

        $masterJadwal = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $idPenjadwalan)->first();

        if (! $masterJadwal) {
            throw new \Exception('Data Penjadwalan tidak ditemukan untuk ID: '.$idPenjadwalan);
        }

        $periode = Carbon::parse($masterJadwal->Periode)->format('Y-m');

        Log::channel('UpdatePerformanceController')->info("TARGET PERIODE (From Schedule): {$periode}");

        Log::channel('UpdatePerformanceController')->info(
            'Mencari draf yang bisa diedit (Flag_Aktif=Y DAN Flag_Pindah!=Y)...',
        );
        $editableDraft = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $Id_Pengajuan)
            ->where('Id_User', $pengguna)
            ->where('Periode', $periode)
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', '<>', 'Y');
            })
            ->first();

        if ($editableDraft) {
            Log::channel('UpdatePerformanceController')->info(
                'HASIL: Ditemukan draf yang bisa diedit.',
                (array) $editableDraft,
            );
        } else {
            Log::channel('UpdatePerformanceController')->info('HASIL: TIDAK ditemukan draf yang bisa diedit.');
        }

        if ($editableDraft) {
            Log::channel('UpdatePerformanceController')->info(
                'KEPUTUSAN: Ditemukan draf yang bisa diedit, masuk ke jalur UPDATE.',
            );
            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $editableDraft->Id_Update_Performance_Sementara)
                ->update([
                    'Jam_Terakhir' => $creationTime->format('H:i:s'),
                    'Tanggal_Terakhir' => $creationTime,
                    'Steps' => $step,
                ]);
            Log::channel('UpdatePerformanceController')->info(
                "SUCCESS: Berhasil meng-update draf ID: {$editableDraft->Id_Update_Performance_Sementara}.",
            );

            return [$editableDraft->Id_Update_Performance_Sementara, $editableDraft->No_Transaksi_Sementara, collect()];
        }

        // INFO, bukan ERROR. Ini jalur NORMAL — pengisian pertama pada sebuah
        // periode memang tidak punya draf untuk diedit. Ditulis sebagai ERROR ia
        // muncul 32 kali sehari pada log uji 10 September 2026 dan mendorong
        // galat sungguhan keluar dari pandangan.
        Log::channel('UpdatePerformanceController')->info(
            'KEPUTUSAN: TIDAK ada draf yang bisa diedit, masuk ke jalur INSERT.',
        );

        $oldDetails = collect();
        $tempRefNo = null;

        $lastHeaderForPeriod = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $Id_Pengajuan)
            ->where('Id_User', $pengguna)
            ->where('Periode', $periode)
            ->latest('Tanggal_Terakhir')
            ->first();

        if ($rejectedTransactionRef) {
            Log::channel('UpdatePerformanceController')->info(
                "Ini adalah revisi baru, ref dari frontend: {$rejectedTransactionRef}",
            );

            // No_Transaksi_Ref WAJIB menunjuk ke pengajuan FINAL yang DITOLAK
            // (No_Transaksi AKPI), bukan nomor draf/temporary terakhir. Nomor temp
            // (TAKPI) dan final (AKPI) adalah dua counter terpisah, sehingga konversi
            // string TAKPI->AKPI menghasilkan ref yang salah (menunjuk ke nomor final
            // lain). rejected_transaction_ref dari FE bisa AKPI (dari data final) atau
            // TAKPI (dari draf) — resolve ke pengajuan final yang ditolak.
            $rejectedFinalHeader = DB::table('KPI_Update_Performance')
                ->where('No_Transaksi', $rejectedTransactionRef)
                ->first();

            if (! $rejectedFinalHeader) {
                $rejectedFinalHeader = DB::table('KPI_Update_Performance')
                    ->where('Id_Pengajuan', $Id_Pengajuan)
                    ->where('Id_User', $pengguna)
                    ->where('Periode', $periode)
                    ->where('Status_Pengajuan', 'ditolak')
                    ->latest('Tanggal')
                    ->first();
            }

            if ($rejectedFinalHeader) {
                $tempRefNo = $rejectedFinalHeader->No_Transaksi;
                Log::channel('UpdatePerformanceController')->info(
                    "No Transaksi Ref (pengajuan ditolak) untuk draf baru: {$tempRefNo}",
                );

                $oldDetails = DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $rejectedFinalHeader->Id_Update_Performance)
                    ->get()
                    ->keyBy('Id_Pengajuan_Det');
            }
        } else {
            Log::channel('UpdatePerformanceController')->info('Ini adalah draf awal (bukan revisi).');
        }

        if ($lastHeaderForPeriod) {
            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $lastHeaderForPeriod->Id_Update_Performance_Sementara)
                ->update(['Flag_Aktif' => 'T']);
            Log::channel('UpdatePerformanceController')->info(
                "Draf lama (ID: {$lastHeaderForPeriod->Id_Update_Performance_Sementara}) dinonaktifkan.",
            );
        }

        /* Nomornya sudah diambil pemanggil SEBELUM transaksi dibuka; lihat
           generateNomorSementara(). Membuatnya di sini berarti membuatnya di
           dalam transaksi, dan itu justru sumber antreannya. */
        $noTransaksi = $noTransaksiSementara;
        Log::channel('UpdatePerformanceController')->info("Nomor transaksi baru dibuat: {$noTransaksi}");

        $idHeader = DB::table('KPI_Update_Performance_Sementara')->insertGetId([
            'No_Transaksi_Sementara' => $noTransaksi,
            'Id_Penjadwalan' => $idPenjadwalan,
            'No_Transaksi_Ref' => $tempRefNo,
            'Id_Pengajuan' => $Id_Pengajuan,
            'Periode' => $periode,
            'Status_Pengajuan' => 'drafted',
            'Jam' => $creationTime->format('H:i:s'),
            'Tanggal' => $creationTime,
            'Jam_Terakhir' => $creationTime->format('H:i:s'),
            'Tanggal_Terakhir' => $creationTime,
            'Flag_Aktif' => 'Y',
            'Id_User' => $pengguna,
            'Steps' => $step,
        ]);
        Log::channel('UpdatePerformanceController')->info(
            "SUCCESS: Draf baru berhasil di-insert dengan ID: {$idHeader}.",
        );

        return [$idHeader, $noTransaksi, $oldDetails];
    }

    private function handleFileUploadStream(Request $request, $index)
    {
        $fileInputKey = "kpi_data.{$index}.File_Berkas";
        if ($request->hasFile($fileInputKey)) {
            $file = $request->file($fileInputKey);

            if (! $file->isValid()) {
                throw new \Exception('File '.$file->getClientOriginalName().' gagal diunggah.');
            }

            if ($file->getSize() > 3 * 1024 * 1024) {
                throw new \Exception('File '.$file->getClientOriginalName().' melebihi ukuran maksimal 3MB.');
            }

            $allowedMimes = ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'docx', 'ppt', 'pptx', 'zip'];
            if (! in_array(strtolower($file->getClientOriginalExtension()), $allowedMimes)) {
                throw new \Exception('File '.$file->getClientOriginalName().' tidak diperbolehkan.');
            }

            $newFileName =
                pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).
                '_'.
                \Illuminate\Support\Str::random(6).
                '_'.
                date('dmY').
                '.'.
                $file->getClientOriginalExtension();

            $temporaryPath = $file->getRealPath() ?: $file->getPathname();
            if (! is_string($temporaryPath) || trim($temporaryPath) === '' || ! is_file($temporaryPath)) {
                throw new \Exception(
                    'File '.$file->getClientOriginalName().' tidak memiliki temporary path yang valid.',
                );
            }

            $stream = fopen($temporaryPath, 'rb');
            if ($stream === false) {
                throw new \Exception('File '.$file->getClientOriginalName().' gagal dibaca.');
            }

            $gcsPath = 'KPI/data-source/'.$newFileName;

            try {
                $stored = Storage::disk('gcs')->put($gcsPath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (! $stored) {
                throw new \Exception('File '.$file->getClientOriginalName().' gagal disimpan.');
            }

            return $gcsPath;
        }

        $existingFileValue = $request->input($fileInputKey);

        return ! empty($existingFileValue) ? $existingFileValue : null;
    }

    /**
     * Upload SEMUA berkas KPI ke GCS SEBELUM transaksi bisnis dibuka, agar HTTP
     * upload (lambat) tidak menahan lock DB di dalam transaksi (penyebab
     * "Login timeout expired"). Mengembalikan:
     *   [0] map index => path (untuk dipakai di dalam loop transaksi),
     *   [1] daftar path yang BARU di-upload (untuk cleanup bila transaksi gagal).
     * Bila salah satu upload gagal, file yang terlanjur ter-upload langsung dihapus
     * lalu exception dilempar ulang (validasi ukuran/mime tetap menggagalkan submit).
     */
    private function preUploadKpiFilesStream(Request $request): array
    {
        $map = [];
        $newPaths = [];
        try {
            foreach (array_keys((array) $request->input('kpi_data', [])) as $index) {
                $isNewUpload = $request->hasFile("kpi_data.{$index}.File_Berkas");
                $path = $this->handleFileUploadStream($request, $index);
                $map[$index] = $path;
                if ($isNewUpload && is_string($path) && $path !== '') {
                    $newPaths[] = $path;
                }
            }
        } catch (\Throwable $e) {
            $this->cleanupUploadedGcsFiles($newPaths);
            throw $e;
        }

        return [$map, $newPaths];
    }

    /**
     * Hapus file GCS yang menjadi orphan karena transaksi gagal/rollback setelah
     * upload. Best-effort: kegagalan hapus hanya di-log (file sisa bisa dibersihkan
     * lifecycle GCS), tidak melempar.
     */
    private function cleanupUploadedGcsFiles(array $paths): void
    {
        foreach ($paths as $p) {
            if (! is_string($p) || $p === '') {
                continue;
            }
            try {
                Storage::disk('gcs')->delete($p);
            } catch (\Throwable $e) {
                Log::channel('UpdatePerformanceController')->warning(
                    'Gagal hapus berkas GCS orphan setelah rollback: '.$p.' — '.$e->getMessage(),
                );
            }
        }
    }

    private function calculateFormulaStream($actual, $target, $weight, $idMasterFormula, $formulas)
    {
        // Terpusat di KpiScoringService (acuan kanonik). Perilaku identik.
        return app(\App\Services\KPI\KpiScoringService::class)->scoreParameterById(
            $actual,
            $target,
            $weight,
            $idMasterFormula,
            $formulas,
        );
    }

    public function getDataTemporaryDataStep1(Request $request)
    {
        $pengguna = Auth::user()->Id_Users;

        $karyawan = DB::table('Karyawan')->where('UserID_WEb', $pengguna)->first();
        $nik = $karyawan->Kode_Karyawan ?? null;

        // Draft disimpan memakai periode dari jadwal yang sedang dibuka, jadi
        // pembacaannya pun harus memakai jadwal yang sama agar draft tidak "hilang".
        $targetDate =
            $this->resolvePeriodFromScheduleHash($request->query('Id_Master_Penjadwalan')) ??
            $this->getDynamicPeriod($nik);

        if (! $targetDate) {
            return ResponseHelper::error('Periode Pengisian KPI tidak aktif.', 404);
        }

        $periodToFill = $targetDate->format('Y-m');

        $pengajuan = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('p.Id_User', $pengguna)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->where('j.Tahun', $targetDate->year)
            ->orderBy('p.Id_Pengajuan', 'DESC')
            ->select('p.*')
            ->first();

        if (! $pengajuan) {
            return ResponseHelper::error('Pengajuan KPI aktif tidak ditemukan.', 404);
        }

        $getDataHeader = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $pengajuan->Id_Pengajuan)
            ->where('Id_User', $pengguna)
            ->where('Status_Pengajuan', 'drafted')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', 'T');
            })
            ->where('Periode', $periodToFill)
            ->first();

        if ($getDataHeader) {
            $realPerformanceData = DB::table('KPI_Update_Performance')
                ->where('Id_Pengajuan', $getDataHeader->Id_Pengajuan)
                // Kunci ke PERIODE draft yang sedang diisi + hanya pengajuan AKTIF, agar
                // status ditolak/PICA/alasan dari periode LAIN tidak bocor ke periode ini.
                ->where('Periode', $getDataHeader->Periode)
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('Flag_Approval')
                ->latest('Tanggal')
                ->first();

            $isRejected = false;
            $rejectedDetails = collect();

            if ($realPerformanceData && $realPerformanceData->Status_Pengajuan === 'ditolak') {
                $isRejected = true;
                $rejectedDetails = DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $realPerformanceData->Id_Update_Performance)
                    ->whereNotNull('Alasan_Reject')
                    ->get()
                    ->keyBy('Id_Pengajuan_Det');
            }

            $finalPicaFallback = $this->getFinalPicaFallbackMap($realPerformanceData ?? null);

            $getDataDetails = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Sementara', $getDataHeader->Id_Update_Performance_Sementara)
                ->get()
                ->map(function ($item) use ($rejectedDetails, $finalPicaFallback) {
                    $originalId = $item->Id_Update_Performance_Detail_Sementara;
                    $rawPengajuanDet = $item->Id_Pengajuan_Det;
                    $rejectionInfo = $rejectedDetails->get($item->Id_Pengajuan_Det);
                    $item->isRejected = $rejectionInfo ? true : false;
                    $item->alasan_penolakan = $rejectionInfo ? $rejectionInfo->Alasan_Reject : null;
                    $item->Id_Pengajuan_Det = Hashids::connection('custom')->encode($item->Id_Pengajuan_Det);
                    $item->Id_Update_Performance_Detail_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Detail_Sementara,
                    );
                    $item->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Sementara,
                    );
                    $item->Nilai_Actual = is_numeric($item->Nilai_Actual) ? (float) $item->Nilai_Actual : null;
                    $item->Score = is_numeric($item->Score) ? (float) $item->Score : null;
                    $item->Achievement = is_numeric($item->Achievement) ? (float) $item->Achievement : null;
                    $item->File_Berkas_Url = ! empty($item->File_Berkas)
                        ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20))
                        : null;

                    if (isset($item->Flag_Pica) && $item->Flag_Pica === 'Y') {
                        $pica = DB::table('KPI_Update_Performance_Det_Sementara')
                            ->where('Id_Update_Performance_Detail_Sementara', $originalId)
                            ->first();
                        if ($pica) {
                            $item->Pica = [
                                'Identifikasi' => $pica->Identifikasi,
                                'Koreksi_Aksi' => $pica->Koreksi_Aksi,
                                'Tanggal_Pelaksanaan' => FormatTanggalHelper::format($pica->Tanggal_Pelaksanaan, true),
                            ];
                        } elseif (isset($finalPicaFallback[$rawPengajuanDet])) {
                            // Fallback: PICA lama dari pengajuan yang ditolak, yang belum
                            // ter-seed ke draf sementara — agar tidak hilang di layar revisi.
                            $fb = $finalPicaFallback[$rawPengajuanDet];
                            $item->Pica = [
                                'Identifikasi' => $fb['Identifikasi'],
                                'Koreksi_Aksi' => $fb['Koreksi_Aksi'],
                                'Tanggal_Pelaksanaan' => FormatTanggalHelper::format($fb['Tanggal_Pelaksanaan'], true),
                            ];
                        } else {
                            $item->Pica = null;
                        }
                    } else {
                        $item->Pica = null;
                    }

                    return $item;
                });

            $getDataHeader->isFinalized = false;
            $getDataHeader->isRejected = $isRejected;
            // Alasan penolakan GLOBAL (komentar umum approver) — ditampilkan di
            // banner "Perlu Revisi". Komentar per-parameter ada di tiap detail.
            $getDataHeader->alasan_penolakan_global =
                $isRejected && $realPerformanceData ? $realPerformanceData->Alasan_Reject ?? null : null;

            $getDataHeader->Id_Pengajuan = Hashids::connection('custom')->encode($getDataHeader->Id_Pengajuan);
            $getDataHeader->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                $getDataHeader->Id_Update_Performance_Sementara,
            );
            $getDataHeader->Id_User = Hashids::connection('custom')->encode($getDataHeader->Id_User);

            return ResponseHelper::success(
                ['header' => $getDataHeader, 'details' => $getDataDetails],
                'Data Temporary Ditemukan!',
                200,
            );
        }

        $lastFinalHeader = DB::table('KPI_Update_Performance')
            ->where('Id_Pengajuan', $pengajuan->Id_Pengajuan)
            ->where('Id_User', $pengguna)
            ->where('Flag_Aktif', 'Y')
            ->where('Periode', $periodToFill)
            ->latest('Tanggal')
            ->first();

        if ($lastFinalHeader) {
            $isResubmission =
                $lastFinalHeader->Status_Pengajuan === 'menunggu' && ! is_null($lastFinalHeader->No_Transaksi_Ref);

            $fakeHeader = (object) [
                'Id_Update_Performance_Sementara' => Hashids::connection('custom')->encode(
                    $lastFinalHeader->Id_Update_Performance,
                ),
                'No_Transaksi_Sementara' => $lastFinalHeader->No_Transaksi,
                'Id_Pengajuan' => Hashids::connection('custom')->encode($lastFinalHeader->Id_Pengajuan),
                'Status_Pengajuan' => $lastFinalHeader->Status_Pengajuan,
                'isFinalized' => in_array($lastFinalHeader->Status_Pengajuan, ['disetujui', 'menunggu']),
                'isRejected' => $lastFinalHeader->Status_Pengajuan === 'ditolak',
                'alasan_penolakan_global' => $lastFinalHeader->Status_Pengajuan === 'ditolak' ? $lastFinalHeader->Alasan_Reject ?? null : null,
                'isResubmission' => $isResubmission,
                'Jam' => $lastFinalHeader->Jam,
                'Jam_Terakhir' => $lastFinalHeader->Jam,
                'Tanggal' => $lastFinalHeader->Tanggal,
                'Tanggal_Terakhir' => $lastFinalHeader->Tanggal,
                'Id_User' => Hashids::connection('custom')->encode($lastFinalHeader->Id_User),
                'Steps' => $lastFinalHeader->Status_Pengajuan === 'ditolak' ? 1 : 3,
                'Flag_Aktif' => 'Y',
                'Flag_Pindah' => 'Y',
            ];

            $finalDetails = DB::table('KPI_Update_Performance_Detail')
                ->where('Id_Update_Performance', $lastFinalHeader->Id_Update_Performance)
                ->get()
                ->map(function ($item) {
                    $newItem = new \stdClass;
                    $newItem->Id_Update_Performance_Detail_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Detail,
                    );
                    $newItem->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance,
                    );
                    $newItem->Id_Pengajuan_Det = Hashids::connection('custom')->encode($item->Id_Pengajuan_Det);
                    $newItem->Nilai_Actual = (float) $item->Nilai_Actual;
                    $newItem->Score = (float) $item->Score;
                    $newItem->Achievement = (float) $item->Achievement;
                    $newItem->File_Berkas = ! empty($item->File_Berkas) ? $item->File_Berkas : null;
                    $newItem->File_Berkas_Url = ! empty($item->File_Berkas)
                        ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20))
                        : null;
                    $newItem->Flag_Pica = $item->Flag_Pica;
                    $newItem->isRejected = ! is_null($item->Alasan_Reject);
                    $newItem->alasan_penolakan = $item->Alasan_Reject;

                    if ($item->Flag_Pica === 'Y') {
                        $pica = DB::table('KPI_Update_Performance_Det')
                            ->where('Id_Update_Performance_Detail', $item->Id_Update_Performance_Detail)
                            ->first();
                        $newItem->Pica = $pica
                            ? [
                                'Identifikasi' => $pica->Identifikasi,
                                'Koreksi_Aksi' => $pica->Koreksi_Aksi,
                                'Tanggal_Pelaksanaan' => $pica->Tanggal_Pelaksanaan,
                            ]
                            : null;
                    } else {
                        $newItem->Pica = null;
                    }

                    return $newItem;
                });

            return ResponseHelper::success(
                ['header' => $fakeHeader, 'details' => $finalDetails],
                'Data Final Ditemukan!',
                200,
            );
        }

        return ResponseHelper::error('Data Tidak Ditemukan', 404);
    }

    public function getDataTemporaryDataStep1ByTeamUserId(Request $request, $user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

        if (! $karyawan) {
            return ResponseHelper::error('Data Tidak Ditemukan !', 404);
        }

        $nik = $karyawan->Kode_Karyawan ?? null;

        // Draft disimpan memakai periode dari jadwal yang sedang dibuka, jadi
        // pembacaannya pun harus memakai jadwal yang sama agar draft tidak "hilang".
        $targetDate =
            $this->resolvePeriodFromScheduleHash($request->input('Id_Master_Penjadwalan')) ??
            $this->getDynamicPeriod($nik);

        if (! $targetDate) {
            return ResponseHelper::error('Periode Pengisian KPI tidak aktif.', 404);
        }

        $periodToFill = $targetDate->format('Y-m');
        $pengguna = Auth::user()->Id_Users;

        // Gerbang kepemilikan pindah dari "saya yang mengirim parameternya" ke
        // "orang ini anggota saya". Tanpa penggantinya, membuang saringan pengirim
        // berarti siapa pun berkapabilitas UpdatePerformanceTeam bisa menilai
        // karyawan mana pun.
        if (! $this->anggotaTimSaya($pengguna)->contains((int) $karyawan->UserID_Web)) {
            return ResponseHelper::error('Karyawan ini bukan anggota tim Anda.', 403);
        }

        $pengajuan = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('p.Id_User', $karyawan->UserID_Web)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->where('j.Tahun', $targetDate->year)
            ->orderBy('p.Id_Pengajuan', 'DESC')
            ->select('p.*')
            ->first();

        if (! $pengajuan) {
            return ResponseHelper::error('Pengajuan KPI aktif tidak ditemukan.', 404);
        }

        $getDataHeader = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $pengajuan->Id_Pengajuan)
            ->where('Id_User_Sender', $pengguna)
            ->where('Id_User', $karyawan->UserID_Web)
            ->where('Status_Pengajuan', 'drafted')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Team', 'Y')
            ->where(function ($query) {
                $query->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', 'T');
            })
            ->where('Periode', $periodToFill)
            ->first();

        if ($getDataHeader) {
            $realPerformanceData = DB::table('KPI_Update_Performance')
                ->where('Id_Pengajuan', $getDataHeader->Id_Pengajuan)
                // Kunci ke PERIODE draft yang sedang diisi + hanya pengajuan AKTIF, agar
                // status ditolak/PICA/alasan dari periode LAIN tidak bocor ke periode ini.
                ->where('Periode', $getDataHeader->Periode)
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('Flag_Approval')
                ->latest('Tanggal')
                ->first();

            $isRejected = false;
            $rejectedDetails = collect();

            if ($realPerformanceData && $realPerformanceData->Status_Pengajuan === 'ditolak') {
                $isRejected = true;
                $rejectedDetails = DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $realPerformanceData->Id_Update_Performance)
                    ->whereNotNull('Alasan_Reject')
                    ->get()
                    ->keyBy('Id_Pengajuan_Det');
            }

            $finalPicaFallback = $this->getFinalPicaFallbackMap($realPerformanceData ?? null);

            $getDataDetails = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Sementara', $getDataHeader->Id_Update_Performance_Sementara)
                ->get()
                ->map(function ($item) use ($rejectedDetails, $finalPicaFallback) {
                    $originalId = $item->Id_Update_Performance_Detail_Sementara;
                    $rawPengajuanDet = $item->Id_Pengajuan_Det;
                    $rejectionInfo = $rejectedDetails->get($item->Id_Pengajuan_Det);
                    $item->isRejected = $rejectionInfo ? true : false;
                    $item->alasan_penolakan = $rejectionInfo ? $rejectionInfo->Alasan_Reject : null;
                    $item->Id_Pengajuan_Det = Hashids::connection('custom')->encode($item->Id_Pengajuan_Det);
                    $item->Id_Update_Performance_Detail_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Detail_Sementara,
                    );
                    $item->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Sementara,
                    );
                    $item->Nilai_Actual = is_numeric($item->Nilai_Actual) ? (float) $item->Nilai_Actual : null;
                    $item->Score = is_numeric($item->Score) ? (float) $item->Score : null;
                    $item->Achievement = is_numeric($item->Achievement) ? (float) $item->Achievement : null;
                    $item->File_Berkas_Url = ! empty($item->File_Berkas)
                        ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20))
                        : null;

                    if (isset($item->Flag_Pica) && $item->Flag_Pica === 'Y') {
                        $pica = DB::table('KPI_Update_Performance_Det_Sementara')
                            ->where('Id_Update_Performance_Detail_Sementara', $originalId)
                            ->first();
                        if ($pica) {
                            $item->Pica = [
                                'Identifikasi' => $pica->Identifikasi,
                                'Koreksi_Aksi' => $pica->Koreksi_Aksi,
                                'Tanggal_Pelaksanaan' => FormatTanggalHelper::format($pica->Tanggal_Pelaksanaan, true),
                            ];
                        } elseif (isset($finalPicaFallback[$rawPengajuanDet])) {
                            // Fallback: PICA lama dari pengajuan yang ditolak, yang belum
                            // ter-seed ke draf sementara — agar tidak hilang di layar revisi.
                            $fb = $finalPicaFallback[$rawPengajuanDet];
                            $item->Pica = [
                                'Identifikasi' => $fb['Identifikasi'],
                                'Koreksi_Aksi' => $fb['Koreksi_Aksi'],
                                'Tanggal_Pelaksanaan' => FormatTanggalHelper::format($fb['Tanggal_Pelaksanaan'], true),
                            ];
                        } else {
                            $item->Pica = null;
                        }
                    } else {
                        $item->Pica = null;
                    }

                    return $item;
                });

            $getDataHeader->isFinalized = false;
            $getDataHeader->isRejected = $isRejected;
            // Alasan penolakan GLOBAL (komentar umum approver) — ditampilkan di
            // banner "Perlu Revisi". Komentar per-parameter ada di tiap detail.
            $getDataHeader->alasan_penolakan_global =
                $isRejected && $realPerformanceData ? $realPerformanceData->Alasan_Reject ?? null : null;

            $getDataHeader->Id_Pengajuan = Hashids::connection('custom')->encode($getDataHeader->Id_Pengajuan);
            $getDataHeader->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                $getDataHeader->Id_Update_Performance_Sementara,
            );
            $getDataHeader->Id_User = Hashids::connection('custom')->encode($getDataHeader->Id_User);

            return ResponseHelper::success(
                ['header' => $getDataHeader, 'details' => $getDataDetails],
                'Data Temporary Ditemukan!',
                200,
            );
        }

        // Tanpa saringan pengirim: bila anggota ini berada di roster lebih dari
        // satu leader, pengisian yang sudah ada harus terlihat oleh mereka semua —
        // kalau tidak, dua leader membuat dua baris untuk periode yang sama.
        $lastFinalHeader = DB::table('KPI_Update_Performance')
            ->where('Id_Pengajuan', $pengajuan->Id_Pengajuan)
            ->where('Id_User', $karyawan->UserID_Web)
            ->where('Flag_Aktif', 'Y')
            ->where('Periode', $periodToFill)
            ->latest('Tanggal')
            ->first();

        if ($lastFinalHeader) {
            $isResubmission =
                $lastFinalHeader->Status_Pengajuan === 'menunggu' && ! is_null($lastFinalHeader->No_Transaksi_Ref);

            $fakeHeader = (object) [
                'Id_Update_Performance_Sementara' => Hashids::connection('custom')->encode(
                    $lastFinalHeader->Id_Update_Performance,
                ),
                'No_Transaksi_Sementara' => $lastFinalHeader->No_Transaksi,
                'Id_Pengajuan' => Hashids::connection('custom')->encode($lastFinalHeader->Id_Pengajuan),
                'Status_Pengajuan' => $lastFinalHeader->Status_Pengajuan,
                'isFinalized' => in_array($lastFinalHeader->Status_Pengajuan, ['disetujui', 'menunggu']),
                'isRejected' => $lastFinalHeader->Status_Pengajuan === 'ditolak',
                'alasan_penolakan_global' => $lastFinalHeader->Status_Pengajuan === 'ditolak' ? $lastFinalHeader->Alasan_Reject ?? null : null,
                'isResubmission' => $isResubmission,
                'Jam' => $lastFinalHeader->Jam,
                'Jam_Terakhir' => $lastFinalHeader->Jam,
                'Tanggal' => $lastFinalHeader->Tanggal,
                'Tanggal_Terakhir' => $lastFinalHeader->Tanggal,
                'Id_User' => Hashids::connection('custom')->encode($lastFinalHeader->Id_User),
                'Steps' => $lastFinalHeader->Status_Pengajuan === 'ditolak' ? 1 : 3,
                'Flag_Aktif' => 'Y',
                'Flag_Pindah' => 'Y',
            ];

            $finalDetails = DB::table('KPI_Update_Performance_Detail')
                ->where('Id_Update_Performance', $lastFinalHeader->Id_Update_Performance)
                ->get()
                ->map(function ($item) {
                    $newItem = new \stdClass;
                    $newItem->Id_Update_Performance_Detail_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Detail,
                    );
                    $newItem->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance,
                    );
                    $newItem->Id_Pengajuan_Det = Hashids::connection('custom')->encode($item->Id_Pengajuan_Det);
                    $newItem->Nilai_Actual = (float) $item->Nilai_Actual;
                    $newItem->Score = (float) $item->Score;
                    $newItem->Achievement = (float) $item->Achievement;
                    $newItem->File_Berkas = ! empty($item->File_Berkas) ? $item->File_Berkas : null;
                    $newItem->File_Berkas_Url = ! empty($item->File_Berkas)
                        ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20))
                        : null;
                    $newItem->Flag_Pica = $item->Flag_Pica;
                    $newItem->isRejected = ! is_null($item->Alasan_Reject);
                    $newItem->alasan_penolakan = $item->Alasan_Reject;

                    if ($item->Flag_Pica === 'Y') {
                        $pica = DB::table('KPI_Update_Performance_Det')
                            ->where('Id_Update_Performance_Detail', $item->Id_Update_Performance_Detail)
                            ->first();
                        $newItem->Pica = $pica
                            ? [
                                'Identifikasi' => $pica->Identifikasi,
                                'Koreksi_Aksi' => $pica->Koreksi_Aksi,
                                'Tanggal_Pelaksanaan' => $pica->Tanggal_Pelaksanaan,
                            ]
                            : null;
                    } else {
                        $newItem->Pica = null;
                    }

                    return $newItem;
                });

            return ResponseHelper::success(
                ['header' => $fakeHeader, 'details' => $finalDetails],
                'Data Final Ditemukan!',
                200,
            );
        }

        return ResponseHelper::error('Data Tidak Ditemukan', 404);
    }

    public function getDataTemporaryDataStep1ByTeamUserIdByPass(Request $request, $user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

        if (! $karyawan) {
            return ResponseHelper::error('Data Tidak Ditemukan !', 404);
        }

        $pengguna = Auth::user()->Id_Users;
        // Jadwal yang dikirim halaman menang; bila tidak ada, tetap memakai
        // bulan acuan by-pass seperti sebelumnya.
        $periodeJadwal = $this->resolvePeriodFromScheduleHash($request->input('Id_Master_Penjadwalan'), false);
        $periodToFill = ($periodeJadwal ?? $this->DateCarbon2())->format('Y-m');

        // Urutan & select harus persis sama dengan getDataKpiItemsByIdUserByPass,
        // karena parameter KPI dan draft-nya wajib mendarat di Id_Pengajuan yang
        // sama. Tanpa orderBy, karyawan yang punya pengajuan individu dan tim di
        // tahun yang sama akan memuat daftar parameter dari pengajuan A tetapi
        // draft dari pengajuan B. select('p.*') mencegah kolom bernama sama
        // (Id_User, Tanggal, Jam, Flag_Aktif) tertimpa nilai milik jadwal.
        $pengajuan = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('p.Id_User', $karyawan->UserID_Web)
            ->where('j.Tahun', $periodeJadwal ? $periodeJadwal->year : $this->DateCarbon2()->year)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->orderBy('p.Id_Pengajuan', 'DESC')
            ->select('p.*')
            ->first();

        if (! $pengajuan) {
            return ResponseHelper::error('Pengajuan KPI aktif tidak ditemukan.', 404);
        }

        // dd($pengajuan->Id_Pengajuan, $periodToFill);

        $getDataHeader = DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Pengajuan', $pengajuan->Id_Pengajuan)
            ->where('Id_User', $karyawan->UserID_Web)
            ->where('Status_Pengajuan', 'drafted')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Flag_Pindah')->orWhere('Flag_Pindah', 'T');
            })
            ->where('Periode', $periodToFill)
            ->first();

        if ($getDataHeader) {
            $realPerformanceData = DB::table('KPI_Update_Performance')
                ->where('Id_Pengajuan', $getDataHeader->Id_Pengajuan)
                // Kunci ke PERIODE draft yang sedang diisi + hanya pengajuan AKTIF, agar
                // status ditolak/PICA/alasan dari periode LAIN tidak bocor ke periode ini.
                ->where('Periode', $getDataHeader->Periode)
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('Flag_Approval')
                ->latest('Tanggal')
                ->first();

            $isRejected = false;
            $rejectedDetails = collect();

            if ($realPerformanceData && $realPerformanceData->Status_Pengajuan === 'ditolak') {
                $isRejected = true;
                $rejectedDetails = DB::table('KPI_Update_Performance_Detail')
                    ->where('Id_Update_Performance', $realPerformanceData->Id_Update_Performance)
                    ->whereNotNull('Alasan_Reject')
                    ->get()
                    ->keyBy('Id_Pengajuan_Det');
            }

            $finalPicaFallback = $this->getFinalPicaFallbackMap($realPerformanceData ?? null);

            $getDataDetails = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Sementara', $getDataHeader->Id_Update_Performance_Sementara)
                ->get()
                ->map(function ($item) use ($rejectedDetails, $finalPicaFallback) {
                    $originalId = $item->Id_Update_Performance_Detail_Sementara;
                    $rawPengajuanDet = $item->Id_Pengajuan_Det;
                    $rejectionInfo = $rejectedDetails->get($item->Id_Pengajuan_Det);
                    $item->isRejected = $rejectionInfo ? true : false;
                    $item->alasan_penolakan = $rejectionInfo ? $rejectionInfo->Alasan_Reject : null;
                    $item->Id_Pengajuan_Det = Hashids::connection('custom')->encode($item->Id_Pengajuan_Det);
                    $item->Id_Update_Performance_Detail_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Detail_Sementara,
                    );
                    $item->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Sementara,
                    );
                    $item->Nilai_Actual = is_numeric($item->Nilai_Actual) ? (float) $item->Nilai_Actual : null;
                    $item->Score = is_numeric($item->Score) ? (float) $item->Score : null;
                    $item->Achievement = is_numeric($item->Achievement) ? (float) $item->Achievement : null;
                    $item->File_Berkas_Url = ! empty($item->File_Berkas)
                        ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20))
                        : null;

                    if (isset($item->Flag_Pica) && $item->Flag_Pica === 'Y') {
                        $pica = DB::table('KPI_Update_Performance_Det_Sementara')
                            ->where('Id_Update_Performance_Detail_Sementara', $originalId)
                            ->first();
                        if ($pica) {
                            $item->Pica = [
                                'Identifikasi' => $pica->Identifikasi,
                                'Koreksi_Aksi' => $pica->Koreksi_Aksi,
                                'Tanggal_Pelaksanaan' => FormatTanggalHelper::format($pica->Tanggal_Pelaksanaan, true),
                            ];
                        } elseif (isset($finalPicaFallback[$rawPengajuanDet])) {
                            // Fallback: PICA lama dari pengajuan yang ditolak, yang belum
                            // ter-seed ke draf sementara — agar tidak hilang di layar revisi.
                            $fb = $finalPicaFallback[$rawPengajuanDet];
                            $item->Pica = [
                                'Identifikasi' => $fb['Identifikasi'],
                                'Koreksi_Aksi' => $fb['Koreksi_Aksi'],
                                'Tanggal_Pelaksanaan' => FormatTanggalHelper::format($fb['Tanggal_Pelaksanaan'], true),
                            ];
                        } else {
                            $item->Pica = null;
                        }
                    } else {
                        $item->Pica = null;
                    }

                    return $item;
                });

            $getDataHeader->isFinalized = false;
            $getDataHeader->isRejected = $isRejected;
            // Alasan penolakan GLOBAL (komentar umum approver) — ditampilkan di
            // banner "Perlu Revisi". Komentar per-parameter ada di tiap detail.
            $getDataHeader->alasan_penolakan_global =
                $isRejected && $realPerformanceData ? $realPerformanceData->Alasan_Reject ?? null : null;

            $getDataHeader->Id_Pengajuan = Hashids::connection('custom')->encode($getDataHeader->Id_Pengajuan);
            $getDataHeader->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                $getDataHeader->Id_Update_Performance_Sementara,
            );
            $getDataHeader->Id_User = Hashids::connection('custom')->encode($getDataHeader->Id_User);

            return ResponseHelper::success(
                ['header' => $getDataHeader, 'details' => $getDataDetails],
                'Data Temporary Ditemukan!',
                200,
            );
        }

        $lastFinalHeader = DB::table('KPI_Update_Performance')
            ->where('Id_Pengajuan', $pengajuan->Id_Pengajuan)
            ->where('Id_User', $karyawan->UserID_Web)
            ->where('Flag_Aktif', 'Y')
            ->where('Periode', $periodToFill)
            ->latest('Tanggal')
            ->first();

        if ($lastFinalHeader) {
            $isResubmission =
                $lastFinalHeader->Status_Pengajuan === 'menunggu' && ! is_null($lastFinalHeader->No_Transaksi_Ref);

            $fakeHeader = (object) [
                'Id_Update_Performance_Sementara' => Hashids::connection('custom')->encode(
                    $lastFinalHeader->Id_Update_Performance,
                ),
                'No_Transaksi_Sementara' => $lastFinalHeader->No_Transaksi,
                'Id_Pengajuan' => Hashids::connection('custom')->encode($lastFinalHeader->Id_Pengajuan),
                'Status_Pengajuan' => $lastFinalHeader->Status_Pengajuan,
                'isFinalized' => in_array($lastFinalHeader->Status_Pengajuan, ['disetujui', 'menunggu']),
                'isRejected' => $lastFinalHeader->Status_Pengajuan === 'ditolak',
                'alasan_penolakan_global' => $lastFinalHeader->Status_Pengajuan === 'ditolak' ? $lastFinalHeader->Alasan_Reject ?? null : null,
                'isResubmission' => $isResubmission,
                'Jam' => $lastFinalHeader->Jam,
                'Jam_Terakhir' => $lastFinalHeader->Jam,
                'Tanggal' => $lastFinalHeader->Tanggal,
                'Tanggal_Terakhir' => $lastFinalHeader->Tanggal,
                'Id_User' => Hashids::connection('custom')->encode($lastFinalHeader->Id_User),
                'Steps' => $lastFinalHeader->Status_Pengajuan === 'ditolak' ? 1 : 3,
                'Flag_Aktif' => 'Y',
                'Flag_Pindah' => 'Y',
            ];

            $finalDetails = DB::table('KPI_Update_Performance_Detail')
                ->where('Id_Update_Performance', $lastFinalHeader->Id_Update_Performance)
                ->get()
                ->map(function ($item) {
                    $newItem = new \stdClass;
                    $newItem->Id_Update_Performance_Detail_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance_Detail,
                    );
                    $newItem->Id_Update_Performance_Sementara = Hashids::connection('custom')->encode(
                        $item->Id_Update_Performance,
                    );
                    $newItem->Id_Pengajuan_Det = Hashids::connection('custom')->encode($item->Id_Pengajuan_Det);
                    $newItem->Nilai_Actual = (float) $item->Nilai_Actual;
                    $newItem->Score = (float) $item->Score;
                    $newItem->Achievement = (float) $item->Achievement;
                    $newItem->File_Berkas = ! empty($item->File_Berkas) ? $item->File_Berkas : null;
                    $newItem->File_Berkas_Url = ! empty($item->File_Berkas)
                        ? Storage::disk('gcs')->temporaryUrl($item->File_Berkas, now()->addMinutes(20))
                        : null;
                    $newItem->Flag_Pica = $item->Flag_Pica;
                    $newItem->isRejected = ! is_null($item->Alasan_Reject);
                    $newItem->alasan_penolakan = $item->Alasan_Reject;

                    if ($item->Flag_Pica === 'Y') {
                        $pica = DB::table('KPI_Update_Performance_Det')
                            ->where('Id_Update_Performance_Detail', $item->Id_Update_Performance_Detail)
                            ->first();
                        $newItem->Pica = $pica
                            ? [
                                'Identifikasi' => $pica->Identifikasi,
                                'Koreksi_Aksi' => $pica->Koreksi_Aksi,
                                'Tanggal_Pelaksanaan' => $pica->Tanggal_Pelaksanaan,
                            ]
                            : null;
                    } else {
                        $newItem->Pica = null;
                    }

                    return $newItem;
                });

            return ResponseHelper::success(
                ['header' => $fakeHeader, 'details' => $finalDetails],
                'Data Final Ditemukan!',
                200,
            );
        }

        return ResponseHelper::error('Data Tidak Ditemukan', 404);
    }

    public function getMasterGrading()
    {
        try {
            $ambangBatasScore = DB::table('KPI_Master_Grading')->value('Nilai_Maksimum');

            $ambangBatasScore = $ambangBatasScore !== null ? (float) $ambangBatasScore : 100;

            return ResponseHelper::success($ambangBatasScore, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error('getMasterGrading: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getKpiCapConfig()
    {
        try {
            $bounds = app(\App\Services\KPI\KpiScoringService::class)->paramCapBounds();

            $config = [
                // Batas cap TOTAL — dipertahankan untuk pemanggil lama.
                'max_achievement_score' => (int) config('services.kpi.max_achievement_score', 125),
                'min_achievement_score' => (int) config('services.kpi.min_achievement_score', 50),
                // Batas cap PER PARAMETER. null = sisi itu tidak dijepit.
                'param_max_achievement_score' => $bounds[0],
                'param_min_achievement_score' => $bounds[1],
            ];

            return ResponseHelper::success($config, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error('getKpiCapConfig: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function storePicaUpdatePerformance(Request $request)
    {
        $request->validate([
            'Id_Update_Performance_Detail_Sementara' => 'required|string',
            'Identifikasi' => 'required',
            'Koreksi_Aksi' => 'required',
            'Tanggal_Pelaksanaan' => 'required',
        ]);

        try {
            $idDetailSementara =
                Hashids::connection('custom')->decode($request->Id_Update_Performance_Detail_Sementara)[0] ?? null;

            if (! $idDetailSementara) {
                return ResponseHelper::error('Format ID tidak valid.', 400);
            }
        } catch (\Exception $e) {
            return ResponseHelper::error('Format ID tidak valid.', 400);
        }

        DB::beginTransaction();
        try {
            $ambangBatasScore = (float) DB::table('KPI_Master_Grading')->value('Nilai_Maksimum') ?? 100;

            $dataDetail = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Detail_Sementara', $idDetailSementara)
                ->first();

            if (! $dataDetail) {
                $this->safeRollback();

                DB::rollBack();

                return ResponseHelper::error('Data KPI Detail Sementara tidak ditemukan.', 404);
            }

            if ($dataDetail->Score >= $ambangBatasScore) {
                $this->safeRollback();
                $errorMessage = sprintf(
                    'PICA tidak diperlukan. Skor Anda (%s) sudah memenuhi atau melebihi ambang batas (%s).',
                    $dataDetail->Score,
                    $ambangBatasScore,
                );

                DB::rollBack();

                return ResponseHelper::error($errorMessage, 422);
            }

            $idUntukPayloadPica = $dataDetail->Id_Update_Performance_Detail_Sementara;
            /* Pintu penulisan PICA — lihat App\Services\KPI\KpiPicaIsi untuk
               alasan dan angka yang mendasarinya. Ditolak di sini, bukan disaring
               di laporan: menyaring di laporan hanya menyembunyikan isian tak
               berarti dari orang yang seharusnya memperbaikinya. */
            if (! KpiPicaIsi::pasanganBermakna($request->Identifikasi, $request->Koreksi_Aksi)) {
                $this->safeRollback();

                DB::rollBack();

                return ResponseHelper::error(KpiPicaIsi::PESAN, 422);
            }

            $tanggal = Carbon::parse($request->Tanggal_Pelaksanaan)->format('Y-m-d H:i:s');

            $picaPayload = [
                'Id_Update_Performance_Detail_Sementara' => $idUntukPayloadPica,
                'Identifikasi' => $request->Identifikasi,
                'Koreksi_Aksi' => $request->Koreksi_Aksi,
                'Tanggal_Pelaksanaan' => $tanggal,
            ];

            $existing = DB::table('KPI_Update_Performance_Det_Sementara')
                ->where('Id_Update_Performance_Detail_Sementara', $idUntukPayloadPica)
                ->first();

            $header = DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $dataDetail->Id_Update_Performance_Sementara)
                ->first();

            if (! $header) {
                $this->safeRollback();

                DB::rollBack();

                return ResponseHelper::error('Data header KPI sementara tidak ditemukan.', 404);
            }

            if ($existing) {
                DB::table('KPI_Update_Performance_Det_Sementara')
                    ->where('Id_Update_Performance_Detail_Sementara', $idUntukPayloadPica)
                    ->update($picaPayload);

                DB::commit();

                return ResponseHelper::success($picaPayload, 'Data PICA berhasil diperbarui.', 200);
            } else {
                DB::table('KPI_Update_Performance_Det_Sementara')->insert($picaPayload);

                DB::commit();

                return ResponseHelper::success($picaPayload, 'Data PICA berhasil disimpan.', 201);
            }
        } catch (\Exception $e) {
            $this->safeRollback();
            Log::channel('UpdatePerformanceController')->error(
                'storeOrUpdatePicaUpdatePerformance: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Terjadi kesalahan pada server saat menyimpan PICA.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }

    public function storePicaUpdatePerformanceByPass(Request $request)
    {
        $request->validate([
            'Id_Update_Performance_Detail_Sementara' => 'required|string',
            'Identifikasi' => 'required',
            'Koreksi_Aksi' => 'required',
            'Tanggal_Pelaksanaan' => 'required',
        ]);

        try {
            $idDetailSementara =
                Hashids::connection('custom')->decode($request->Id_Update_Performance_Detail_Sementara)[0] ?? null;

            if (! $idDetailSementara) {
                return ResponseHelper::error('Format ID tidak valid.', 400);
            }
        } catch (\Exception $e) {
            return ResponseHelper::error('Format ID tidak valid.', 400);
        }

        DB::beginTransaction();
        try {
            $ambangBatasScore = (float) DB::table('KPI_Master_Grading')->value('Nilai_Maksimum') ?? 100;

            $dataDetail = DB::table('KPI_Update_Performance_Detail_Sementara')
                ->where('Id_Update_Performance_Detail_Sementara', $idDetailSementara)
                ->first();

            if (! $dataDetail) {
                $this->safeRollback();

                DB::rollBack();

                return ResponseHelper::error('Data KPI Detail Sementara tidak ditemukan.', 404);
            }

            if ($dataDetail->Score >= $ambangBatasScore) {
                $this->safeRollback();
                $errorMessage = sprintf(
                    'PICA tidak diperlukan. Skor Anda (%s) sudah memenuhi atau melebihi ambang batas (%s).',
                    $dataDetail->Score,
                    $ambangBatasScore,
                );

                DB::rollBack();

                return ResponseHelper::error($errorMessage, 422);
            }

            $idUntukPayloadPica = $dataDetail->Id_Update_Performance_Detail_Sementara;
            /* Pintu penulisan PICA — lihat App\Services\KPI\KpiPicaIsi untuk
               alasan dan angka yang mendasarinya. Ditolak di sini, bukan disaring
               di laporan: menyaring di laporan hanya menyembunyikan isian tak
               berarti dari orang yang seharusnya memperbaikinya. */
            if (! KpiPicaIsi::pasanganBermakna($request->Identifikasi, $request->Koreksi_Aksi)) {
                $this->safeRollback();

                DB::rollBack();

                return ResponseHelper::error(KpiPicaIsi::PESAN, 422);
            }

            $tanggal = Carbon::parse($request->Tanggal_Pelaksanaan)->format('Y-m-d H:i:s');

            $picaPayload = [
                'Id_Update_Performance_Detail_Sementara' => $idUntukPayloadPica,
                'Identifikasi' => $request->Identifikasi,
                'Koreksi_Aksi' => $request->Koreksi_Aksi,
                'Tanggal_Pelaksanaan' => $tanggal,
            ];

            $existing = DB::table('KPI_Update_Performance_Det_Sementara')
                ->where('Id_Update_Performance_Detail_Sementara', $idUntukPayloadPica)
                ->first();

            $header = DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $dataDetail->Id_Update_Performance_Sementara)
                ->first();

            if (! $header) {
                $this->safeRollback();

                DB::rollBack();

                return ResponseHelper::error('Data header KPI sementara tidak ditemukan.', 404);
            }

            if ($existing) {
                DB::table('KPI_Update_Performance_Det_Sementara')
                    ->where('Id_Update_Performance_Detail_Sementara', $idUntukPayloadPica)
                    ->update($picaPayload);

                DB::commit();

                return ResponseHelper::success($picaPayload, 'Data PICA berhasil diperbarui.', 200);
            } else {
                DB::table('KPI_Update_Performance_Det_Sementara')->insert($picaPayload);

                DB::commit();

                return ResponseHelper::success($picaPayload, 'Data PICA berhasil disimpan.', 201);
            }
        } catch (\Exception $e) {
            $this->safeRollback();
            Log::channel('UpdatePerformanceController')->error(
                'storeOrUpdatePicaUpdatePerformance: '.$e->getMessage().' on line '.$e->getLine(),
            );

            return ResponseHelper::error('Terjadi kesalahan pada server saat menyimpan PICA.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            $this->safeRollback();

            throw $e;
        }
    }

    public function storeTemporaryPerformanceStep2(Request $request)
    {
        try {
            $decoded = Hashids::connection('custom')->decode($request->Id_Update_Performance_Sementara);
            if (! $decoded) {
                return ResponseHelper::error('ID tidak valid.', 400);
            }

            $id = $decoded[0];

            return DB::transaction(function () use ($id) {
                $record = DB::table('KPI_Update_Performance_Sementara')
                    ->lockForUpdate()
                    ->where('Id_Update_Performance_Sementara', $id)
                    ->first();

                if (! $record) {
                    return ResponseHelper::error('Data tidak ditemukan.', 404);
                }

                if ($record->Steps == 3) {
                    return ResponseHelper::success(null, 'Data sudah berada di Step 3', 200);
                }

                DB::table('KPI_Update_Performance_Sementara')
                    ->where('Id_Update_Performance_Sementara', $id)
                    ->update([
                        'Steps' => 3,
                        'Jam_Terakhir' => date('H:i:s'),
                        'Tanggal_Terakhir' => date('Y-m-d'),
                    ]);

                return ResponseHelper::success(null, 'Data berhasil disimpan.', 201);
            });
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error('storeTemporaryPerformanceStep2: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error('Terjadi kesalahan server.', 500);
        }
    }

    /**
     * Target row representatif saat tidak ada target untuk periode berjalan.
     *
     * Ambil target TERDEKAT yang akan datang (Periode >= bulan berjalan); bila
     * semua target sudah lewat, ambil yang PALING AKHIR. Dipakai agar parameter
     * (terutama monthly yang belum/di luar bulan berjalan) tetap ditampilkan dalam
     * keadaan nonaktif, bukan hilang dari daftar form.
     */
    private function pickFallbackPeriodDetail($details, Carbon $targetDate)
    {
        $startOfMonth = $targetDate->copy()->startOfMonth();

        $upcoming = collect($details)
            ->sortBy(fn ($d) => Carbon::parse($d->Periode))
            ->first(fn ($d) => Carbon::parse($d->Periode)->gte($startOfMonth));

        return $upcoming ?: collect($details)->sortByDesc(fn ($d) => Carbon::parse($d->Periode))->first();
    }

    public function getDataPerformance()
    {
        $getData = DB::table('KPI_Master_Performance')->orderBy('Range_Awal', 'asc')->get();

        if ($getData->isEmpty()) {
            return ResponseHelper::error('Data Tidak Ditemukan', 404);
        }

        $data = $getData->map(function ($item) {
            return [
                'Id_Master_Performance' => Hashids::connection('custom')->encode($item->Id_Master_Performance),
                'Range_Awal' => (float) $item->Range_Awal,
                'Range_Akhir' => (float) $item->Range_Akhir,
                'Keterangan' => $item->Keterangan,
                'Warna' => $item->Warna,
                'Jam' => $item->Jam,
                'Tanggal' => $item->Tanggal,
            ];
        });

        return ResponseHelper::success($data, 'Data Ditemukan', 200);
    }

    public function finalisasiPerformance(Request $request)
    {
        $validated = $request->validate([
            'Id_Update_Performance_Sementara' => 'required|string',
        ]);

        try {
            $decodedId = Hashids::connection('custom')->decode($validated['Id_Update_Performance_Sementara']);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID performance sementara tidak valid.', 400);
            }
            $idHeaderSementara = $decodedId[0];

            $user = Auth::user();
            $karyawan = DB::table('Karyawan')->where('UserID_Web', $user->Id_Users)->first();

            if (! $karyawan) {
                return ResponseHelper::error('Data karyawan tidak ditemukan.', 404);
            }

            $result = $this->processPerformanceTransaction($idHeaderSementara, $user, $karyawan);

            return ResponseHelper::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error('FINALISASI_PERFORMANCE_ERROR: '.$e->getMessage());
            if (str_contains($e->getMessage(), 'approver')) {
                return ResponseHelper::error($e->getMessage(), 404);
            }

            return ResponseHelper::error('Gagal Menyimpan, Terjadi Kesalahan', 500);
        }
    }

    public function finalisasiPerformanceByTeam(Request $request, $user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $validated = $request->validate([
            'Id_Update_Performance_Sementara' => 'required|string',
        ]);

        try {
            $decodedId = Hashids::connection('custom')->decode($validated['Id_Update_Performance_Sementara']);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID performance sementara tidak valid.', 400);
            }
            $idHeaderSementara = $decodedId[0];

            $penggunaSender = Auth::user();
            $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

            if (! $karyawan) {
                return ResponseHelper::error('Data karyawan tidak ditemukan.', 404);
            }

            // Diperiksa lagi di sini, bukan hanya saat menyimpan draf: draf dan
            // finalisasi adalah dua permintaan terpisah, dan keanggotaan bisa
            // berubah di antara keduanya.
            if ($tolak = $this->tolakBukanAnggotaTim((int) $penggunaSender->Id_Users, $karyawan)) {
                return $tolak;
            }

            $result = $this->processPerformanceTransactionByTeam(
                $idHeaderSementara,
                $karyawan->UserID_Web,
                $penggunaSender,
                $karyawan,
            );

            return ResponseHelper::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error('FINALISASI_PERFORMANCE_ERROR: '.$e->getMessage());
            if (str_contains($e->getMessage(), 'approver')) {
                return ResponseHelper::error($e->getMessage(), 404);
            }

            return ResponseHelper::error('Gagal Menyimpan, Terjadi Kesalahan', 500);
        }
    }

    public function finalisasiPerformanceByPass(Request $request, $user_id)
    {
        try {
            $Id_User = Hashids::connection('custom')->decode($user_id)[0];
        } catch (\Exception $e) {
            return ResponseHelper::error('Format Kunci Formula atau Satuan Tidak Valid.', 400);
        }

        $validated = $request->validate([
            'Id_Update_Performance_Sementara' => 'required|string',
        ]);

        try {
            $decodedId = Hashids::connection('custom')->decode($validated['Id_Update_Performance_Sementara']);
            if (empty($decodedId)) {
                return ResponseHelper::error('ID performance sementara tidak valid.', 400);
            }
            $idHeaderSementara = $decodedId[0];

            $penggunaSender = Auth::user();
            $karyawan = DB::table('Karyawan')->where('UserID_Web', $Id_User)->first();

            if (! $karyawan) {
                return ResponseHelper::error('Data karyawan tidak ditemukan.', 404);
            }

            $result = $this->processPerformanceTransactionByPass(
                $idHeaderSementara,
                $karyawan->UserID_Web,
                $penggunaSender,
                $karyawan,
            );

            return ResponseHelper::success($result, $result['message'], 201);
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error('FINALISASI_PERFORMANCE_ERROR: '.$e->getMessage());
            if (str_contains($e->getMessage(), 'approver')) {
                return ResponseHelper::error($e->getMessage(), 404);
            }

            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Satu karyawan hanya boleh punya SATU realisasi hidup per periode.
     *
     * Penjaga sebelumnya hanya mencegah draft yang SAMA difinalisasi dua kali
     * (Flag_Pindah) dan menggantikan baris yang ditunjuk No_Transaksi_Ref. Draft
     * BARU untuk periode yang sudah terisi lolos begitu saja — itulah yang dulu
     * menghasilkan dua realisasi hidup pada satu periode, keduanya disetujui,
     * dengan skor berbeda, dan YTD mengambil salah satunya tanpa aturan jelas.
     *
     * Kuncinya (Id_User, Periode) LINTAS SEMUA PENGAJUAN — bukan dibatasi ke satu
     * Id_Pengajuan. Kasus lamanya justru dua pengajuan berbeda yang sama-sama
     * hidup pada periode yang sama, jadi penjaga per-pengajuan akan melewatkannya.
     *
     * Hidup = Flag_Aktif 'Y' DAN Status NULL. Dipanggil SETELAH blok
     * No_Transaksi_Ref supaya jalur perbaikan/resubmit yang sah tetap lewat.
     */
    private function assertBelumAdaRealisasiHidup($karyawanIdUser, ?string $periode): void
    {
        if (empty($periode)) {
            return; // tanpa periode tidak ada yang bisa dibandingkan
        }

        // Prefix 'YYYY-MM' agar cocok baik untuk 'YYYY-MM' maupun 'YYYY-MM-DD',
        // dan tetap sargable karena wildcard hanya di belakang.
        $awalan = Carbon::parse($periode)->format('Y-m');

        $bentrok = DB::table('KPI_Update_Performance')
            ->where('Id_User', $karyawanIdUser)
            ->where('Periode', 'like', $awalan.'%')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->select('No_Transaksi', 'Status_Pengajuan')
            ->first();

        if ($bentrok) {
            throw new \Exception(
                'Periode '.
                    $awalan.
                    ' sudah memiliki update performance yang aktif ('.
                    $bentrok->No_Transaksi.
                    ', status '.
                    ($bentrok->Status_Pengajuan ?? '-').
                    '). Batalkan yang lama terlebih dahulu sebelum mengirim yang baru.',
            );
        }
    }

    /**
     * Apakah orang ini punya target yang JATUH TEMPO pada periode tersebut.
     *
     * Satu aturan dengan monitoring, export, dan auto-cap
     * (KpiEnforcementService::classifyParameterTargetForPeriod): minimal satu
     * parameter bertarget TEPAT di bulan itu. Target Q/S/Y yang baru cocok lewat
     * rentang belum jatuh tempo — formulirnya menampilkannya nonaktif — jadi
     * tidak dihitung.
     *
     * Sebelumnya halaman ini membuka periode hanya berdasarkan jadwal yang
     * berjalan. Orang tanpa target bulan itu mendapat formulir yang seluruh
     * parameternya nonaktif, tetapi tetap bisa lanjut dan menyimpan draft kosong,
     * sementara monitoring melabelinya "Tidak Ada Target".
     *
     * @param  array<int>  $idUsers
     * @return array<int, bool> Id_User => punya target
     */
    private function punyaTargetPeriode(array $idUsers, ?string $periode): array
    {
        $idUsers = array_values(array_unique(array_map('intval', $idUsers)));

        if (empty($periode)) {
            // Tanpa periode tidak ada yang bisa dibandingkan; penjaga lain yang menentukan.
            return array_fill_keys($idUsers, true);
        }

        $kelas = app(KpiEnforcementService::class)->classifyParameterTargetForPeriod(
            $idUsers,
            Carbon::parse($periode),
        );

        $hasil = [];
        foreach ($idUsers as $idUser) {
            $hasil[$idUser] = ($kelas[$idUser] ?? 'no_parameter') === 'has';
        }

        return $hasil;
    }

    private function pesanTanpaTarget(?string $periode): string
    {
        $label = empty($periode) ? '-' : Carbon::parse($periode)->locale('id')->translatedFormat('F Y');

        return 'Tidak ada target KPI yang jatuh tempo pada periode '.$label.', sehingga tidak ada yang perlu diperbarui.';
    }

    /**
     * Penjaga server untuk seluruh jalur simpan: halaman boleh disembunyikan,
     * tetapi permintaan langsung tetap harus ditolak.
     */
    private function assertPunyaTargetPeriode($idUser, ?string $periode): void
    {
        if (! ($this->punyaTargetPeriode([(int) $idUser], $periode)[(int) $idUser] ?? false)) {
            throw new \Exception($this->pesanTanpaTarget($periode));
        }
    }

    private function processPerformanceTransactionByPass(
        int $idHeaderSementara,
        $karyawanIdUser,
        $IdUserSender,
        $karyawan,
    ): array {
        /* Nomor diambil SEBELUM transaksi bisnis dibuka. Counter-nya mengunci
           satu baris selama satu UPDATE autocommit; kalau dipanggil di dalam
           transaksi ini, kunci baris itu ikut tertahan sampai commit dan
           serialisasinya kembali seperti semula. */
        $noTransaksi = $this->generateNewTransactionNumber();

        return DB::transaction(function () use ($idHeaderSementara, $karyawanIdUser, $IdUserSender, $karyawan, $noTransaksi) {
            $headerSementara = DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
                ->where('Id_User', $karyawanIdUser)
                ->lockForUpdate()
                ->first();

            if (! $headerSementara) {
                throw new \Exception('Sesi update performance tidak ditemukan atau bukan milik Anda.');
            }
            if ($headerSementara->Flag_Pindah === 'Y') {
                throw new \Exception('Data ini sudah pernah difinalisasi sebelumnya.');
            }
            // Draf yang dibatalkan admin (monitoring) tidak boleh terkirim dari
            // halaman yang masih terbuka. Header sudah dikunci di atas, jadi
            // pembatalan dan finalisasi tidak bisa saling menyalip.
            if (($headerSementara->Flag_Aktif ?? 'Y') !== 'Y') {
                throw new \Exception('Draft ini sudah dibatalkan admin. Muat ulang halaman untuk mengisi kembali.');
            }

            if ($headerSementara->No_Transaksi_Ref) {
                $this->deactivatePreviousPerformanceByTeam(
                    $headerSementara->No_Transaksi_Ref,
                    $IdUserSender->Id_Users,
                    $karyawanIdUser,
                );
            }

            // Jalur by-pass sengaja TIDAK memanggil validateActiveSchedule() —
            // memang boleh di luar jendela jadwal. Tapi larangan dua realisasi
            // hidup dalam satu periode tetap berlaku.
            $this->assertBelumAdaRealisasiHidup($karyawanIdUser, $headerSementara->Periode);
            $this->assertPunyaTargetPeriode($karyawanIdUser, $headerSementara->Periode);

            $detailsData = $this->getPerformanceDetails($idHeaderSementara);
            if ($detailsData->isEmpty()) {
                throw new \Exception('Tidak ada detail KPI yang aktif untuk dihitung.');
            }
            $scores = $this->calculateScores($detailsData);
            $scores = $this->applyAchievementCap($scores);
            $scoreForChecking = floor($scores['final_score_percentage']);
            $performanceLevel = $this->getPerformanceLevel($scoreForChecking);
            $idHeaderFinal = $this->createFinalPerformanceHeaderByPass(
                $headerSementara,
                $scores,
                $performanceLevel,
                $noTransaksi,
                $IdUserSender,
                $karyawanIdUser,
            );

            $this->createFinalPerformanceDetailsByPass($idHeaderFinal, $detailsData, $karyawan->Kode_Karyawan);
            $this->createFinalPicaDetailsByPass($idHeaderFinal, $idHeaderSementara, $detailsData);

            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
                ->update([
                    'Flag_Pindah' => 'Y',
                    'Steps' => 1,
                ]);

            return [
                'message' => 'Data performance berhasil difinalisasi.',
                'no_transaksi' => $noTransaksi,
                'total_score' => $scores['final_score_percentage'], // Tetap return nilai asli
                'performance_level' => $performanceLevel->Keterangan ?? 'N/A',
            ];
        });
    }

    private function processPerformanceTransactionByTeam(
        int $idHeaderSementara,
        $karyawanIdUser,
        $IdUserSender,
        $karyawan,
    ): array {
        /* Nomor diambil SEBELUM transaksi bisnis dibuka. Counter-nya mengunci
           satu baris selama satu UPDATE autocommit; kalau dipanggil di dalam
           transaksi ini, kunci baris itu ikut tertahan sampai commit dan
           serialisasinya kembali seperti semula. */
        $noTransaksi = $this->generateNewTransactionNumber();

        return DB::transaction(function () use ($idHeaderSementara, $karyawanIdUser, $IdUserSender, $karyawan, $noTransaksi) {
            $headerSementara = DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
                ->where('Id_User_Sender', $IdUserSender->Id_Users)
                ->where('Id_User', $karyawanIdUser)
                ->lockForUpdate()
                ->first();

            if (! $headerSementara) {
                throw new \Exception('Sesi update performance tidak ditemukan atau bukan milik Anda.');
            }
            if ($headerSementara->Flag_Pindah === 'Y') {
                throw new \Exception('Data ini sudah pernah difinalisasi sebelumnya.');
            }
            // Draf yang dibatalkan admin (monitoring) tidak boleh terkirim dari
            // halaman yang masih terbuka. Header sudah dikunci di atas, jadi
            // pembatalan dan finalisasi tidak bisa saling menyalip.
            if (($headerSementara->Flag_Aktif ?? 'Y') !== 'Y') {
                throw new \Exception('Draft ini sudah dibatalkan admin. Muat ulang halaman untuk mengisi kembali.');
            }

            $this->validateActiveSchedule();

            if ($headerSementara->No_Transaksi_Ref) {
                $this->deactivatePreviousPerformanceByTeam(
                    $headerSementara->No_Transaksi_Ref,
                    $IdUserSender->Id_Users,
                    $karyawanIdUser,
                );
            }

            $this->assertBelumAdaRealisasiHidup($karyawanIdUser, $headerSementara->Periode);
            $this->assertPunyaTargetPeriode($karyawanIdUser, $headerSementara->Periode);

            $detailsData = $this->getPerformanceDetails($idHeaderSementara);
            if ($detailsData->isEmpty()) {
                throw new \Exception('Tidak ada detail KPI yang aktif untuk dihitung.');
            }

            // 1. Hitung Score Asli (Misal: 106.5)
            $scores = $this->calculateScores($detailsData);

            // Terapkan cap min/max achievement (nilai asli tetap tersimpan di key raw_* untuk audit)
            $scores = $this->applyAchievementCap($scores);

            // 2. [FIX] Buat Variable Tampungan dengan FLOOR untuk pengecekan Level
            // Misal: 106.5 di-floor jadi 106. Maka 106 akan masuk range EXCELLENT (Start 106)
            $scoreForChecking = floor($scores['final_score_percentage']);

            // 3. Cari Level berdasarkan nilai yang sudah di-floor
            $performanceLevel = $this->getPerformanceLevel($scoreForChecking);

            // 4. Simpan ke database menggunakan $scores (nilai asli/raw), BUKAN nilai floor
            $idHeaderFinal = $this->createFinalPerformanceHeaderByTeam(
                $headerSementara,
                $scores,
                $performanceLevel,
                $noTransaksi,
                $IdUserSender,
                $karyawanIdUser,
            );

            $this->createFinalPerformanceDetails($idHeaderFinal, $detailsData, $karyawan->Kode_Karyawan);
            $this->createFinalPicaDetails($idHeaderFinal, $idHeaderSementara, $detailsData);
            $this->createApprovalRequest($noTransaksi, $karyawan->Kode_Karyawan, true);

            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
                ->update([
                    'Flag_Pindah' => 'Y',
                    'Steps' => 1,
                ]);

            return [
                'message' => 'Data performance berhasil difinalisasi.',
                'no_transaksi' => $noTransaksi,
                'total_score' => $scores['final_score_percentage'], // Tetap return nilai asli
                'performance_level' => $performanceLevel->Keterangan ?? 'N/A',
            ];
        });
    }

    private function processPerformanceTransaction(int $idHeaderSementara, $user, $karyawan): array
    {
        /* Nomor diambil SEBELUM transaksi bisnis dibuka. Counter-nya mengunci
           satu baris selama satu UPDATE autocommit; kalau dipanggil di dalam
           transaksi ini, kunci baris itu ikut tertahan sampai commit dan
           serialisasinya kembali seperti semula. */
        $noTransaksi = $this->generateNewTransactionNumber();

        return DB::transaction(function () use ($idHeaderSementara, $user, $karyawan, $noTransaksi) {
            $headerSementara = DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
                ->where('Id_User', $user->Id_Users)
                ->lockForUpdate()
                ->first();

            if (! $headerSementara) {
                throw new \Exception('Sesi update performance tidak ditemukan atau bukan milik Anda.');
            }
            if ($headerSementara->Flag_Pindah === 'Y') {
                throw new \Exception('Data ini sudah pernah difinalisasi sebelumnya.');
            }
            // Draf yang dibatalkan admin (monitoring) tidak boleh terkirim dari
            // halaman yang masih terbuka. Header sudah dikunci di atas, jadi
            // pembatalan dan finalisasi tidak bisa saling menyalip.
            if (($headerSementara->Flag_Aktif ?? 'Y') !== 'Y') {
                throw new \Exception('Draft ini sudah dibatalkan admin. Muat ulang halaman untuk mengisi kembali.');
            }

            $this->validateActiveSchedule();

            if ($headerSementara->No_Transaksi_Ref) {
                $this->deactivatePreviousPerformance($headerSementara->No_Transaksi_Ref, $user->Id_Users);
            }

            $this->assertBelumAdaRealisasiHidup($user->Id_Users, $headerSementara->Periode);
            $this->assertPunyaTargetPeriode($user->Id_Users, $headerSementara->Periode);

            $detailsData = $this->getPerformanceDetails($idHeaderSementara);
            if ($detailsData->isEmpty()) {
                throw new \Exception('Tidak ada detail KPI yang aktif untuk dihitung.');
            }

            // 1. Hitung Score Asli (Misal: 106.5)
            $scores = $this->calculateScores($detailsData);

            // Terapkan cap min/max achievement (nilai asli tetap tersimpan di key raw_* untuk audit)
            $scores = $this->applyAchievementCap($scores);

            // 2. [FIX] Buat Variable Tampungan dengan FLOOR untuk pengecekan Level
            // Misal: 106.5 di-floor jadi 106. Maka 106 akan masuk range EXCELLENT
            $scoreForChecking = floor($scores['final_score_percentage']);

            // 3. Cari Level berdasarkan nilai yang sudah di-floor
            $performanceLevel = $this->getPerformanceLevel($scoreForChecking);

            // 4. Simpan ke database menggunakan $scores (nilai asli/raw)
            $idHeaderFinal = $this->createFinalPerformanceHeader(
                $headerSementara,
                $scores,
                $performanceLevel,
                $noTransaksi,
                $user,
            );

            $this->createFinalPerformanceDetails($idHeaderFinal, $detailsData, $karyawan->Kode_Karyawan);
            $this->createFinalPicaDetails($idHeaderFinal, $idHeaderSementara, $detailsData);
            $this->createApprovalRequest($noTransaksi, $karyawan->Kode_Karyawan, true);

            DB::table('KPI_Update_Performance_Sementara')
                ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
                ->update([
                    'Flag_Pindah' => 'Y',
                    'Steps' => 1,
                ]);

            return [
                'message' => 'Data performance berhasil difinalisasi.',
                'no_transaksi' => $noTransaksi,
                'total_score' => $scores['final_score_percentage'],
                'performance_level' => $performanceLevel->Keterangan ?? 'N/A',
            ];
        });
    }

    private function validateActiveSchedule(): void
    {
        $isScheduleActive = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->whereDate('Jadwal_Mulai', '<=', now())
            ->whereDate('Jadwal_Akhir', '>=', now())
            ->exists();

        if (! $isScheduleActive) {
            throw new \Exception('Tidak ada jadwal penilaian KPI yang aktif saat ini.');
        }
    }

    /**
     * $userId (pengirim) sengaja TIDAK ikut menyaring.
     *
     * Sejak anggota bisa berada di roster lebih dari satu leader, revisi boleh
     * dikerjakan leader yang berbeda dari pengisi pertama. Menyaring pengirim
     * membuat baris lama tidak ikut dinonaktifkan — dan dua baris aktif untuk
     * satu (anggota, No_Transaksi) tidak akan pernah bisa dijelaskan mana yang
     * berlaku. No_Transaksi + Id_User sudah cukup spesifik.
     */
    private function deactivatePreviousPerformanceByTeam(string $noTransaksiRef, int $userId, int $karyawanIdUser): void
    {
        $noTransaksiRealRef = str_replace('TAKPI', 'AKPI', $noTransaksiRef);
        DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $noTransaksiRealRef)
            ->where('Id_User', $karyawanIdUser)
            ->update(['Flag_Aktif' => 'T']);
    }

    private function deactivatePreviousPerformance(string $noTransaksiRef, int $userId): void
    {
        $noTransaksiRealRef = str_replace('TAKPI', 'AKPI', $noTransaksiRef);
        DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $noTransaksiRealRef)
            ->where('Id_User', $userId)
            ->update(['Flag_Aktif' => 'T']);
    }

    /**
     * Detail draft yang akan difinalisasi, dengan skor DIHITUNG ULANG.
     *
     * Dulu skor draft disalin apa adanya. Draft yang disimpan sebelum
     * penjepitan per parameter berlaku (di produksi baru ter-deploy ~12 Sep
     * 2026) lalu dikirim sesudahnya membawa skor mentah ke transaksi final —
     * approver melihat achievement 1466,7% untuk target 3 jam, aktual 44.
     * Hal yang sama terjadi bila targetnya dikoreksi setelah draft disimpan.
     *
     * Ketiga jalur finalisasi memakai fungsi ini untuk detail DAN totalnya,
     * jadi menghitung di sini menjaga keduanya tetap sepakat. Flag_Pica tidak
     * disentuh: isian PICA karyawan terikat padanya.
     */
    private function getPerformanceDetails(int $idHeaderSementara)
    {
        $scoring = app(\App\Services\KPI\KpiScoringService::class);
        $formulas = DB::table('KPI_Master_Formula')->pluck('Rumus', 'Id_Master_Formula')->toArray();

        return DB::table('KPI_Update_Performance_Detail_Sementara as upds')
            ->join('KPI_Pengajuan_Det as pd', 'upds.Id_Pengajuan_Det', '=', 'pd.Id_Pengajuan_Det')
            ->join('KPI_Pengajuan_Detail as p_detail', 'pd.Id_Pengajuan_Detail', '=', 'p_detail.Id_Pengajuan_Detail')
            ->where('upds.Id_Update_Performance_Sementara', $idHeaderSementara)
            ->where('upds.Flag_Aktif', 'Y')
            ->select('upds.*', 'p_detail.Bobot', 'p_detail.Id_Master_Formula', 'pd.Nilai_Target')
            ->get()
            ->map(function ($item) use ($scoring, $formulas) {
                if ($item->Nilai_Actual === null || ! is_numeric($item->Nilai_Actual)) {
                    return $item;
                }

                $calc = $scoring->scoreParameterById(
                    $item->Nilai_Actual,
                    $item->Nilai_Target,
                    $item->Bobot,
                    $item->Id_Master_Formula,
                    $formulas,
                );

                // Konvensi kolom lama: Score = achievement %, Achievement = skor berbobot.
                $item->Score = (float) $calc['achievement'];
                $item->Achievement = (float) $calc['score'];
                $item->Score_Raw = (float) ($calc['achievement_raw'] ?? $calc['achievement']);
                $item->Achievement_Raw = (float) ($calc['score_raw'] ?? $calc['score']);
                $item->Flag_Capped = ! empty($calc['is_capped']) ? 'Y' : 'T';

                return $item;
            });
    }

    private function calculateScores($detailsData): array
    {
        // Terpusat di KpiScoringService. Konvensi: weighted = kolom `Achievement`.
        return app(\App\Services\KPI\KpiScoringService::class)->aggregate($detailsData);
    }

    /**
     * Membatasi Total_Score (achievement %) sesuai cap min/max dari config services.kpi.
     * Nilai asli (sebelum cap) disimpan di key raw_* sebagai jejak audit.
     * HANYA final_score_percentage yang di-cap — sum_of_scores TETAP nilai asli
     * karena itu adalah data faktual penjumlahan achievement per KPI.
     */
    private function applyAchievementCap(array $scores): array
    {
        // Terpusat di KpiScoringService (cap dari config services.kpi, efektif 125/50).
        return app(\App\Services\KPI\KpiScoringService::class)->applyCap($scores);
    }

    private function getPerformanceLevel(float $finalScore)
    {
        return app(\App\Services\KPI\KpiScoringService::class)->resolveLevel($finalScore);
    }

    /**
     * Nomor sementara TAKPI lewat COUNTER ATOMIK.
     *
     * Tiga metode handleHeader* dulu menyalin blok yang sama:
     * `MAX(CAST(RIGHT(No_Transaksi_Sementara,4) AS INT))` + `lockForUpdate`.
     * `KPI_Update_Performance_Sementara` tidak punya indeks pada kolom itu, jadi
     * yang terkunci RENTANG seluruh baris yang dipindai — dan `HOLDLOCK` menahan
     * kunci itu sampai transaksi bisnis pemanggilnya commit, bukan sampai
     * nomornya jadi. Pada 19 Agustus satu permintaan tercatat menunggu 13,7
     * detik di situ, sementara `UPDATE KPI_Pengajuan ... WHERE No_Transaksi = ?`
     * di jendela yang sama menunggu 17,1 detik — antrean yang sama.
     *
     * WAJIB DIPANGGIL SEBELUM `DB::beginTransaction()`. Di dalam transaksi,
     * kunci satu baris counter ikut tertahan sampai commit dan serialisasinya
     * kembali seperti semula — perbaikannya jadi tak berarti.
     */
    private function generateNomorSementara(): string
    {
        $now = Carbon::now();
        $prefix = 'TAKPI-'.$now->format('m').'-'.$now->format('Y').'-';

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $prefix,
            fn () => DB::table('KPI_Update_Performance_Sementara')
                ->where('No_Transaksi_Sementara', 'like', $prefix.'%')
                ->max(DB::raw('CAST(RIGHT(No_Transaksi_Sementara,4) AS INT)')),
        );
    }

    /**
     * Nomor final AKPI lewat COUNTER ATOMIK, bukan MAX + lockForUpdate.
     *
     * Bentuk lamanya `MAX(CAST(RIGHT(No_Transaksi,4) AS INT))` dengan
     * `lockForUpdate` — di SQL Server itu `UPDLOCK, HOLDLOCK`. Tanpa indeks pada
     * kolom nomor, yang terkunci bukan satu baris melainkan RENTANG seluruh
     * baris yang dipindai, dan kuncinya tertahan sampai transaksi bisnis
     * pemanggilnya commit — bukan sampai nomornya selesai dibuat. Semua yang
     * memfinalisasi bersamaan antre di belakangnya. Pada 19 Agustus pola kembar
     * di tabel sementara tercatat menahan satu permintaan 13,7 detik.
     *
     * Penggantinya mengunci SATU baris di HRIS_Counter selama satu UPDATE
     * autocommit. Ia self-seeding: saat prefix bulan ini pertama dipakai,
     * nilai awalnya diambil dari MAX yang sudah ada, jadi tak perlu penyemaian
     * manual dan nomor tidak akan mundur.
     *
     * KONSEKUENSI YANG MEMANG DITERIMA: nomor bisa "bolong" bila permintaan
     * gagal sesudah nomornya diambil. Itu sudah jadi norma sistem — Cuti,
     * Lembur, Shift, dan Visitor memakai counter yang sama.
     */
    private function generateNewTransactionNumber(): string
    {
        $now = Carbon::now();
        $prefix = 'AKPI-'.$now->format('m').'-'.$now->format('Y').'-';

        return app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
            $prefix,
            fn () => DB::table('KPI_Update_Performance')
                ->where('No_Transaksi', 'like', $prefix.'%')
                ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
        );
    }

    private function createFinalPerformanceHeaderByTeam(
        $headerSementara,
        $scores,
        $performanceLevel,
        $noTransaksi,
        $user,
        $karyawanIdUser,
    ): int {
        return DB::table('KPI_Update_Performance')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'No_Transaksi_Ref' => $headerSementara->No_Transaksi_Ref
                ? str_replace('TAKPI', 'AKPI', $headerSementara->No_Transaksi_Ref)
                : null,
            'Id_Pengajuan' => $headerSementara->Id_Pengajuan,
            'Id_Penjadwalan' => $headerSementara->Id_Penjadwalan,
            'Periode' => $headerSementara->Periode,
            'Total_Score' => (float) $scores['final_score_percentage'],
            'Total_Score_Achievement' => (float) $scores['sum_of_scores'],
            'Total_Bobot' => (float) $scores['sum_of_active_bobot'],
            'Total_Score_Raw' => (float) ($scores['raw_final_score_percentage'] ?? $scores['final_score_percentage']),
            'Total_Score_Achievement_Raw' => (float) ($scores['raw_sum_of_scores'] ?? $scores['sum_of_scores']),
            'Flag_Capped' => ! empty($scores['is_capped']) ? 'Y' : 'T',
            // Penanda metode cap. Header Flag_Capped sendiri bermakna ganda:
            // pada record lama artinya TOTAL-nya dipangkas, pada record baru
            // artinya ada PARAMETER-nya yang dipangkas. Kolom ini yang
            // membedakannya secara langsung, dan yang dipakai backfill cap
            // header untuk tahu record ini tidak boleh disentuh.
            'Metode_Cap' => self::METODE_CAP_PARAMETER,
            'Cap_Min_Applied' => isset($scores['cap_min_applied']) ? (float) $scores['cap_min_applied'] : null,
            'Cap_Max_Applied' => isset($scores['cap_max_applied']) ? (float) $scores['cap_max_applied'] : null,
            'Id_Performance' => $performanceLevel->Id_Master_Performance,
            'Id_User_Sender' => $user->Id_Users,
            'Id_User' => $karyawanIdUser,
            'Tanggal' => now(),
            'Jam' => now()->format('H:i:s'),
            'Status_Pengajuan' => 'menunggu',
            'Flag_Aktif' => 'Y',
            // Jalur ini hanya dipakai saat LEADER yang memfinalisasi, jadi selalu 'Y'.
            // Sebelumnya ada 'Flag_Team' => $headerSementara->Flag_Team ?? 'T' di atas
            // baris ini; kunci kembar itu mati karena PHP memakai nilai terakhir.
            'Flag_Team' => 'Y',
        ]);
    }

    private function createFinalPerformanceHeaderByPass(
        $headerSementara,
        $scores,
        $performanceLevel,
        $noTransaksi,
        $user,
        $karyawanIdUser,
    ): int {
        return DB::table('KPI_Update_Performance')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'No_Transaksi_Ref' => $headerSementara->No_Transaksi_Ref
                ? str_replace('TAKPI', 'AKPI', $headerSementara->No_Transaksi_Ref)
                : null,
            'Id_Pengajuan' => $headerSementara->Id_Pengajuan,
            'Id_Penjadwalan' => $headerSementara->Id_Penjadwalan,
            'Periode' => $headerSementara->Periode,
            'Total_Score' => (float) $scores['final_score_percentage'],
            'Total_Score_Achievement' => (float) $scores['sum_of_scores'],
            'Total_Bobot' => (float) $scores['sum_of_active_bobot'],
            'Total_Score_Raw' => (float) ($scores['raw_final_score_percentage'] ?? $scores['final_score_percentage']),
            'Total_Score_Achievement_Raw' => (float) ($scores['raw_sum_of_scores'] ?? $scores['sum_of_scores']),
            'Flag_Capped' => ! empty($scores['is_capped']) ? 'Y' : 'T',
            // Penanda metode cap. Header Flag_Capped sendiri bermakna ganda:
            // pada record lama artinya TOTAL-nya dipangkas, pada record baru
            // artinya ada PARAMETER-nya yang dipangkas. Kolom ini yang
            // membedakannya secara langsung, dan yang dipakai backfill cap
            // header untuk tahu record ini tidak boleh disentuh.
            'Metode_Cap' => self::METODE_CAP_PARAMETER,
            'Cap_Min_Applied' => isset($scores['cap_min_applied']) ? (float) $scores['cap_min_applied'] : null,
            'Cap_Max_Applied' => isset($scores['cap_max_applied']) ? (float) $scores['cap_max_applied'] : null,
            'Id_Performance' => $performanceLevel->Id_Master_Performance,
            'Id_User' => $karyawanIdUser,
            'Tanggal' => now(),
            'Jam' => now()->format('H:i:s'),
            'Status_Pengajuan' => 'disetujui',
            'Flag_Team' => $headerSementara->Flag_Team ?? 'T',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Flag_Verifikasi' => 'Y',
        ]);
    }

    private function createFinalPerformanceHeader(
        $headerSementara,
        $scores,
        $performanceLevel,
        $noTransaksi,
        $user,
    ): int {
        return DB::table('KPI_Update_Performance')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'No_Transaksi_Ref' => $headerSementara->No_Transaksi_Ref
                ? str_replace('TAKPI', 'AKPI', $headerSementara->No_Transaksi_Ref)
                : null,
            'Id_Pengajuan' => $headerSementara->Id_Pengajuan,
            'Id_Penjadwalan' => $headerSementara->Id_Penjadwalan,
            'Periode' => $headerSementara->Periode,
            'Total_Score' => (float) $scores['final_score_percentage'],
            'Total_Score_Achievement' => (float) $scores['sum_of_scores'],
            'Total_Bobot' => (float) $scores['sum_of_active_bobot'],
            'Total_Score_Raw' => (float) ($scores['raw_final_score_percentage'] ?? $scores['final_score_percentage']),
            'Total_Score_Achievement_Raw' => (float) ($scores['raw_sum_of_scores'] ?? $scores['sum_of_scores']),
            'Flag_Capped' => ! empty($scores['is_capped']) ? 'Y' : 'T',
            // Penanda metode cap. Header Flag_Capped sendiri bermakna ganda:
            // pada record lama artinya TOTAL-nya dipangkas, pada record baru
            // artinya ada PARAMETER-nya yang dipangkas. Kolom ini yang
            // membedakannya secara langsung, dan yang dipakai backfill cap
            // header untuk tahu record ini tidak boleh disentuh.
            'Metode_Cap' => self::METODE_CAP_PARAMETER,
            'Cap_Min_Applied' => isset($scores['cap_min_applied']) ? (float) $scores['cap_min_applied'] : null,
            'Cap_Max_Applied' => isset($scores['cap_max_applied']) ? (float) $scores['cap_max_applied'] : null,
            'Id_Performance' => $performanceLevel->Id_Master_Performance,
            'Id_User' => $user->Id_Users,
            'Tanggal' => now(),
            'Jam' => now()->format('H:i:s'),
            'Status_Pengajuan' => 'menunggu',
            'Flag_Team' => $headerSementara->Flag_Team ?? 'T',
            'Flag_Aktif' => 'Y',
        ]);
    }

    private function createFinalPerformanceDetails(int $idHeaderFinal, $detailsData, string $kodeKaryawan): void
    {
        $batchDetails = $detailsData
            ->map(
                fn ($item) => [
                    'Id_Update_Performance' => $idHeaderFinal,
                    'Id_Pengajuan_Det' => $item->Id_Pengajuan_Det,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Nilai_Actual' => $item->Nilai_Actual,
                    'Score' => $item->Score,
                    'Achievement' => $item->Achievement,
                    'Score_Raw' => $item->Score_Raw ?? $item->Score,
                    'Achievement_Raw' => $item->Achievement_Raw ?? $item->Achievement,
                    'Flag_Capped' => $item->Flag_Capped ?? 'T',
                    'File_Berkas' => $item->File_Berkas,
                    'Flag_Pica' => $item->Flag_Pica,
                ],
            )
            ->all();

        DB::table('KPI_Update_Performance_Detail')->insert($batchDetails);
    }

    private function createFinalPerformanceDetailsByPass(int $idHeaderFinal, $detailsData, string $kodeKaryawan): void
    {
        $batchDetails = $detailsData
            ->map(
                fn ($item) => [
                    'Id_Update_Performance' => $idHeaderFinal,
                    'Id_Pengajuan_Det' => $item->Id_Pengajuan_Det,
                    'Kode_Karyawan' => $kodeKaryawan,
                    'Nilai_Actual' => $item->Nilai_Actual,
                    'Score' => $item->Score,
                    'Achievement' => $item->Achievement,
                    'Score_Raw' => $item->Score_Raw ?? $item->Score,
                    'Achievement_Raw' => $item->Achievement_Raw ?? $item->Achievement,
                    'Flag_Capped' => $item->Flag_Capped ?? 'T',
                    'File_Berkas' => $item->File_Berkas,
                    'Flag_Pica' => $item->Flag_Pica,
                ],
            )
            ->all();

        DB::table('KPI_Update_Performance_Detail')->insert($batchDetails);
    }

    /**
     * Hapus konten PICA (KPI_Update_Performance_Det_Sementara) untuk detail yang
     * sudah TIDAK lagi memerlukan PICA (Flag_Pica != 'Y') pada satu header sementara.
     *
     * Dipanggil setiap simpan draf: bila requester merevisi actual sehingga parameter
     * yang tadinya di bawah target kini tercapai, PICA-nya harus ikut terbuang — bukan
     * hanya flag-nya. Tanpa ini, konten PICA lama menjadi orphan di tabel sementara.
     */
    /**
     * Peta PICA dari pengajuan FINAL yang ditolak, di-key oleh Id_Pengajuan_Det.
     * Dipakai sebagai FALLBACK saat menampilkan draf revisi: bila draf punya
     * Flag_Pica='Y' tetapi konten PICA di tabel sementara belum ada (mis. draf dibuat
     * sebelum konten PICA di-seed), tampilkan PICA lama dari pengajuan yang ditolak
     * agar tidak hilang di layar.
     *
     * @return array<int,array{Identifikasi:mixed,Koreksi_Aksi:mixed,Tanggal_Pelaksanaan:mixed}>
     */
    private function getFinalPicaFallbackMap(?object $rejectedFinalHeader): array
    {
        if (! $rejectedFinalHeader || ($rejectedFinalHeader->Status_Pengajuan ?? null) !== 'ditolak') {
            return [];
        }

        return DB::table('KPI_Update_Performance_Detail as upd')
            ->join(
                'KPI_Update_Performance_Det as pd',
                'pd.Id_Update_Performance_Detail',
                '=',
                'upd.Id_Update_Performance_Detail',
            )
            ->where('upd.Id_Update_Performance', $rejectedFinalHeader->Id_Update_Performance)
            ->where('upd.Flag_Pica', 'Y')
            ->get(['upd.Id_Pengajuan_Det', 'pd.Identifikasi', 'pd.Koreksi_Aksi', 'pd.Tanggal_Pelaksanaan'])
            ->keyBy('Id_Pengajuan_Det')
            ->map(
                fn ($r) => [
                    'Identifikasi' => $r->Identifikasi,
                    'Koreksi_Aksi' => $r->Koreksi_Aksi,
                    'Tanggal_Pelaksanaan' => $r->Tanggal_Pelaksanaan,
                ],
            )
            ->all();
    }

    private function pruneStalePicaSementara(int $idHeaderSementara): void
    {
        DB::table('KPI_Update_Performance_Det_Sementara')
            ->whereIn('Id_Update_Performance_Detail_Sementara', function ($q) use ($idHeaderSementara) {
                $q->select('Id_Update_Performance_Detail_Sementara')
                    ->from('KPI_Update_Performance_Detail_Sementara')
                    ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
                    ->where('Flag_Pica', '<>', 'Y');
            })
            ->delete();
    }

    /**
     * Simpan konten PICA yang dikirim frontend (kpi_data[i][pica]) ke tabel sementara,
     * HANYA untuk parameter yang masih memerlukan PICA (Flag_Pica='Y').
     *
     * Tujuan: mempertahankan PICA sebelumnya saat revisi. Saat pengajuan yang ditolak
     * dibuka lagi, frontend memuat PICA lama ke item.pica lalu mengirimnya kembali di
     * tiap simpan draf. Tanpa ini, PICA lama tidak pernah tertulis ke tabel sementara
     * (hanya ditulis lewat modal PICA di step 2), sehingga saat finalisasi berubah jadi
     * "Tidak ada". PICA baru dari user tetap tersimpan karena payload selalu berisi nilai
     * terbaru item.pica.
     *
     * @param  array  $kpiDataFromRequest  payload kpi_data mentah dari request
     */
    private function persistPicaSementaraFromRequest(int $idHeaderSementara, array $kpiDataFromRequest): void
    {
        // Kumpulkan payload PICA per Id_Pengajuan_Det (decoded).
        $picaByPengajuanDet = [];
        foreach ($kpiDataFromRequest as $performance) {
            $performance = (object) $performance;
            if (empty($performance->pica) || empty($performance->Id_Pengajuan_Det)) {
                continue;
            }
            $detId = Hashids::connection('custom')->decode($performance->Id_Pengajuan_Det)[0] ?? null;
            if (! $detId) {
                continue;
            }
            $pica = is_string($performance->pica) ? json_decode($performance->pica, true) : (array) $performance->pica;
            if (is_array($pica)) {
                $picaByPengajuanDet[$detId] = $pica;
            }
        }

        if (empty($picaByPengajuanDet)) {
            return;
        }

        // Hanya detail yang masih ber-Flag_Pica='Y' yang berhak menyimpan PICA.
        $details = DB::table('KPI_Update_Performance_Detail_Sementara')
            ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
            ->where('Flag_Pica', 'Y')
            ->whereIn('Id_Pengajuan_Det', array_keys($picaByPengajuanDet))
            ->get(['Id_Update_Performance_Detail_Sementara', 'Id_Pengajuan_Det']);

        foreach ($details as $d) {
            $pica = $picaByPengajuanDet[$d->Id_Pengajuan_Det] ?? null;
            if (! $pica) {
                continue;
            }

            $identifikasi = trim((string) ($pica['problem'] ?? ''));
            $koreksi = trim((string) ($pica['action'] ?? ''));

            // Normalisasi tanggal (frontend mengirim format datetime-local "Y-m-dTH:i")
            // ke format yang diterima MSSQL — sama seperti storePicaUpdatePerformance.
            $tanggal = null;
            if (! empty($pica['targetDate'])) {
                try {
                    $tanggal = Carbon::parse($pica['targetDate'])->format('Y-m-d H:i:s');
                } catch (\Throwable $e) {
                    $tanggal = null;
                }
            }

            // Jangan menimpa dengan PICA kosong.
            if ($identifikasi === '' && $koreksi === '') {
                continue;
            }

            DB::table('KPI_Update_Performance_Det_Sementara')->updateOrInsert(
                ['Id_Update_Performance_Detail_Sementara' => $d->Id_Update_Performance_Detail_Sementara],
                [
                    'Identifikasi' => $identifikasi,
                    'Koreksi_Aksi' => $koreksi,
                    'Tanggal_Pelaksanaan' => $tanggal,
                ],
            );
        }
    }

    private function createFinalPicaDetails(int $idHeaderFinal, int $idHeaderSementara, $detailsData): void
    {
        $picaItems = $detailsData->where('Flag_Pica', 'Y');
        if ($picaItems->isEmpty()) {
            return;
        }

        $picaDataMap = DB::table('KPI_Update_Performance_Det_Sementara as pds')
            ->join(
                'KPI_Update_Performance_Detail_Sementara as upds',
                'pds.Id_Update_Performance_Detail_Sementara',
                '=',
                'upds.Id_Update_Performance_Detail_Sementara',
            )
            ->where('upds.Id_Update_Performance_Sementara', $idHeaderSementara)
            ->where('upds.Flag_Pica', 'Y')
            ->select('upds.Id_Pengajuan_Det', 'pds.Identifikasi', 'pds.Koreksi_Aksi', 'pds.Tanggal_Pelaksanaan')
            ->get()
            ->keyBy('Id_Pengajuan_Det');

        $finalDetailMap = DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance', $idHeaderFinal)
            ->whereIn('Id_Pengajuan_Det', $picaItems->pluck('Id_Pengajuan_Det'))
            ->pluck('Id_Update_Performance_Detail', 'Id_Pengajuan_Det');

        $batchPica = [];
        foreach ($picaItems as $item) {
            if (isset($finalDetailMap[$item->Id_Pengajuan_Det])) {
                $picaSementara = $picaDataMap->get($item->Id_Pengajuan_Det);
                $batchPica[] = [
                    'Id_Update_Performance_Detail' => $finalDetailMap[$item->Id_Pengajuan_Det],
                    'Identifikasi' => $picaSementara->Identifikasi ?? 'Tidak ada',
                    'Koreksi_Aksi' => $picaSementara->Koreksi_Aksi ?? 'Tidak ada',
                    // $picaSementara bisa null bila detail Flag_Pica='Y' tapi baris konten
                    // PICA (Det_Sementara) belum terisi — jangan baca properti di null.
                    'Tanggal_Pelaksanaan' => $picaSementara->Tanggal_Pelaksanaan ?? null,
                ];
            }
        }

        if (! empty($batchPica)) {
            DB::table('KPI_Update_Performance_Det')->insert($batchPica);
        }
    }

    private function createFinalPicaDetailsByPass(int $idHeaderFinal, int $idHeaderSementara, $detailsData): void
    {
        $picaItems = $detailsData->where('Flag_Pica', 'Y');
        if ($picaItems->isEmpty()) {
            return;
        }

        $picaDataMap = DB::table('KPI_Update_Performance_Det_Sementara as pds')
            ->join(
                'KPI_Update_Performance_Detail_Sementara as upds',
                'pds.Id_Update_Performance_Detail_Sementara',
                '=',
                'upds.Id_Update_Performance_Detail_Sementara',
            )
            ->where('upds.Id_Update_Performance_Sementara', $idHeaderSementara)
            ->where('upds.Flag_Pica', 'Y')
            ->select('upds.Id_Pengajuan_Det', 'pds.Identifikasi', 'pds.Koreksi_Aksi', 'pds.Tanggal_Pelaksanaan')
            ->get()
            ->keyBy('Id_Pengajuan_Det');

        $finalDetailMap = DB::table('KPI_Update_Performance_Detail')
            ->where('Id_Update_Performance', $idHeaderFinal)
            ->whereIn('Id_Pengajuan_Det', $picaItems->pluck('Id_Pengajuan_Det'))
            ->pluck('Id_Update_Performance_Detail', 'Id_Pengajuan_Det');

        $batchPica = [];
        foreach ($picaItems as $item) {
            if (isset($finalDetailMap[$item->Id_Pengajuan_Det])) {
                $picaSementara = $picaDataMap->get($item->Id_Pengajuan_Det);
                $batchPica[] = [
                    'Id_Update_Performance_Detail' => $finalDetailMap[$item->Id_Pengajuan_Det],
                    'Identifikasi' => $picaSementara->Identifikasi ?? 'Tidak ada',
                    'Koreksi_Aksi' => $picaSementara->Koreksi_Aksi ?? 'Tidak ada',
                    // $picaSementara bisa null bila detail Flag_Pica='Y' tapi baris konten
                    // PICA (Det_Sementara) belum terisi — jangan baca properti di null.
                    'Tanggal_Pelaksanaan' => $picaSementara->Tanggal_Pelaksanaan ?? null,
                ];
            }
        }

        if (! empty($batchPica)) {
            DB::table('KPI_Update_Performance_Det')->insert($batchPica);
        }
    }

    /**
     * Router approval performance: CENTRAL (HRIS_Approval_Request) atau LEGACY.
     *
     * Default CENTRAL via KpiApprovalService::createPerfFlow (Kode_Config KPI_PERF_STD).
     * Fallback LEGACY dipertahankan utuh di createApprovalRequestLegacy() untuk rollback
     * cepat: set env KPI_PERF_ENGINE=LEGACY tanpa revert kode.
     *
     * Catatan: $kodeKaryawan = kode ANGGOTA yang dinilai (berlaku untuk submit
     * perorangan maupun tim/leader), sehingga approver ter-resolve milik anggota.
     */
    /**
     * Alur & progress persetujuan untuk halaman Update Performance (self & team).
     *
     * - Belum difinalisasi  -> preview urutan approver tujuan pengajuan (semua 'pending').
     * - Sudah difinalisasi   -> progress live per approver (approved/rejected/inprogress/pending)
     *   + ringkasan (progressPercent, currentApproverName) dari engine terpusat.
     *
     * Alurnya TERIKAT PERIODE yang sedang dibuka. Sampai 4 Sep 2026 tidak, dan
     * itu membuat langkah 3 menampilkan alur milik transaksi lain: pencariannya
     * hanya menyaring Id_User lalu mengambil yang terakhir menurut Tanggal, tanpa
     * peduli periode. Dua arah kesalahannya sama-sama terjadi di data dev:
     *
     *   Id_User 642  periode baru menampilkan "3 dari 3 approver telah menyetujui"
     *                milik periode 2026-05 yang sudah lama selesai
     *   Id_User 634  empat periode yang SUDAH disetujui tampil "belum diajukan",
     *                karena baris terakhirnya kebetulan periode lain yang ditolak
     *
     * @param  string|null  $user_id  Hashids UserID_Web anggota tim (null = user login sendiri).
     */
    public function getApprovalFlow(Request $request, $user_id = null)
    {
        try {
            if ($user_id !== null) {
                try {
                    $userIdWeb = Hashids::connection('custom')->decode($user_id)[0];
                } catch (\Throwable $e) {
                    return ResponseHelper::error('Parameter tidak valid.', 400);
                }
            } else {
                $userIdWeb = Auth::user()->Id_Users;
            }

            $karyawan = DB::table('Karyawan')->where('UserID_Web', $userIdWeb)->first();
            if (! $karyawan) {
                return ResponseHelper::error('Data karyawan tidak ditemukan.', 404);
            }
            $kodeKaryawan = $karyawan->Kode_Karyawan;

            // Periode yang sedang DIBUKA di layar. Jadwal yang dikirim halaman
            // dipakai apa adanya, TANPA menuntut jendelanya masih terbuka
            // (harusAktif: false) — berbeda dari jalur penyimpanan draft, yang
            // memang harus menolak menulis ke periode yang sudah tutup.
            //
            // Membacanya beda dari menulisnya: alur persetujuan periode yang
            // jendelanya baru saja tutup tetap harus terbaca. Kalau ikut menuntut
            // aktif, layar yang sedang menampilkan progres akan mendadak kembali
            // ke "belum diajukan" begitu jendelanya lewat.
            //
            // Bila pemanggil tidak mengirim jadwal sama sekali (pemanggil lama),
            // jatuh ke periode aktif orang itu. Sengaja TIDAK dibiarkan tanpa
            // penyaring: "tanpa penyaring" persis keadaan yang keliru sebelum ini.
            $targetDate =
                $this->resolvePeriodFromScheduleHash(
                    $request->input('Id_Master_Penjadwalan'),
                    harusAktif: false,
                ) ??
                $this->getDynamicPeriod($kodeKaryawan);

            if (! $targetDate) {
                return ResponseHelper::success(
                    [
                        'submitted' => false,
                        'status' => null,
                        'summary' => null,
                        'steps' => $this->buildApprovalPreviewSteps($kodeKaryawan),
                    ],
                    'Preview alur persetujuan.',
                    200,
                );
            }

            $periode = $targetDate->format('Y-m');

            // Submission final terakhir PADA PERIODE ITU (yang sudah punya No_Transaksi).
            //
            // Pemecah seri Id_Update_Performance: pengajuan yang lahir dari satu
            // proses batch berbagi Tanggal sama persis sampai ke milidetik —
            // Id_User 622 punya enam baris ber-Tanggal identik. Tanpa pemecah
            // seri, urutan yang dipulangkan tidak dijamin oleh SQL Server dan
            // bisa berubah mengikuti rencana kueri.
            //
            // Ini TIDAK punya uji penjaga, dan itu disengaja: diukur pada dev,
            // mesinnya kebetulan sudah memulangkan Id terbesar lebih dulu, jadi
            // uji apa pun akan hijau dengan maupun tanpa baris ini — hijau yang
            // tidak membuktikan apa-apa. Barisnya tetap dipasang karena
            // bersandar pada perilaku yang tak dijanjikan adalah cacat yang
            // menunggu rencana kueri berubah.
            $lastFinal = DB::table('KPI_Update_Performance')
                ->where('Id_User', $userIdWeb)
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('No_Transaksi')
                ->where('Periode', $periode)
                ->orderByDesc('Tanggal')
                ->orderByDesc('Id_Update_Performance')
                ->first();

            // Tampilkan PREVIEW alur (urutan approver yang AKAN ditempuh setelah
            // finalisasi) bila:
            //  - belum ada pengajuan final sama sekali, ATAU
            //  - pengajuan final terakhir DITOLAK (user sedang merevisi & belum
            //    finalisasi ulang) — jangan tampilkan progress pengajuan yang ditolak.
            // Setelah user finalisasi revisi, pengajuan 'menunggu' baru menjadi
            // final terakhir sehingga progress per tahap yang berjalan kembali tampil.
            if (! $lastFinal || ! $lastFinal->No_Transaksi || $lastFinal->Status_Pengajuan === 'ditolak') {
                return ResponseHelper::success(
                    [
                        'submitted' => false,
                        'status' => null,
                        'summary' => null,
                        'steps' => $this->buildApprovalPreviewSteps($kodeKaryawan),
                    ],
                    'Preview alur persetujuan.',
                    200,
                );
            }

            $noTransaksi = $lastFinal->No_Transaksi;

            // Engine legacy tidak menyimpan flow di HRIS_Approval_Request.
            //
            // Dulu cabang ini memulangkan steps KOSONG, dan layar menuliskan
            // "Alur persetujuan belum tersedia" — kalimat yang keliru dua kali:
            // approval config-nya ADA (KPI_PERF_STD memulangkan approver untuk
            // setiap karyawan yang diperiksa), dan halaman Parameter menampilkan
            // alurnya tanpa masalah karena ia memang selalu memakai preview.
            // Pengguna melihat "belum tersedia" untuk sesuatu yang jelas ada.
            //
            // Dua tingkat penggantinya, dari yang paling faktual:
            //   1. jejak legacy asli (KPI_Approval_Request_Performance), bila ada;
            //   2. urutan approver menurut config, ditandai TIDAK TEREKAM.
            // Yang kedua sengaja tidak memakai status 'pending': pengajuan yang
            // sudah 'disetujui' tak boleh digambar seolah masih menunggu orang.
            $engine = app(\App\Services\KPI\Approval\KpiApprovalService::class)->engineFor($noTransaksi);
            if ($engine !== 'CENTRAL') {
                $jejak = $this->buildLegacyApprovalSteps($noTransaksi);

                if ($jejak !== []) {
                    return ResponseHelper::success(
                        [
                            'submitted' => true,
                            'status' => $lastFinal->Status_Pengajuan,
                            'summary' => $this->ringkasLangkah($jejak),
                            'steps' => $jejak,
                            'jejak_tersedia' => true,
                            'engine' => 'LEGACY',
                        ],
                        'Progress persetujuan (engine legacy).',
                        200,
                    );
                }

                return ResponseHelper::success(
                    [
                        'submitted' => true,
                        'status' => $lastFinal->Status_Pengajuan,
                        'summary' => null,
                        'steps' => array_map(
                            fn ($langkah) => array_merge($langkah, ['status' => 'unknown']),
                            $this->buildApprovalPreviewSteps($kodeKaryawan),
                        ),
                        'jejak_tersedia' => false,
                        'engine' => 'LEGACY',
                    ],
                    ' ',
                    200,
                );
            }

            $steps = $this->buildApprovalProgressSteps($noTransaksi);

            $summary = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class)->getApprovalSummary($noTransaksi);
            $current = collect($steps)->firstWhere('status', 'inprogress');
            $summary['currentApproverName'] = $current['name'] ?? null;
            unset($summary['currentStep']); // buang objek view mentah dari payload

            return ResponseHelper::success(
                [
                    'submitted' => true,
                    'status' => $summary['status'] ?? $lastFinal->Status_Pengajuan,
                    'summary' => $summary,
                    'steps' => $steps,
                ],
                'Progress persetujuan.',
                200,
            );
        } catch (\Throwable $e) {
            Log::channel('UpdatePerformanceController')->error('getApprovalFlow error: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat alur persetujuan.', 500);
        }
    }

    /**
     * Alur persetujuan dari jejak engine LEGACY (KPI_Approval_Request_Performance).
     *
     * Mengembalikan [] bila transaksi itu memang tidak punya jejak sama sekali —
     * keadaan yang nyata: pengajuan A4 AKPI-06-2026-0033 berstatus 'disetujui'
     * tanpa satu pun baris permintaan approval, di engine mana pun.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildLegacyApprovalSteps(string $noTransaksi): array
    {
        // LEFT JOIN, bukan INNER. Flow_Id pada jejak lama bisa menunjuk baris
        // KPI_Approval_Flow_Performance yang sudah tidak ada — di Web_HRIS
        // 10 September 2026 ketiga jejak yang tersisa menunjuk Id 18/21/22
        // sedangkan tabelnya mulai dari 19. INNER JOIN membuang seluruh jejak itu
        // TANPA SUARA, dan hasilnya terbaca sebagai "tidak ada jejak" — padahal
        // yang paling berharga di sini, siapa menyetujui dan siapa menolak,
        // tersimpan di baris permintaannya sendiri, bukan di baris flow-nya.
        $baris = DB::table('KPI_Approval_Request_Performance as ar')
            ->leftJoin('KPI_Approval_Flow_Performance as f', 'f.Id_Approval_Flow', '=', 'ar.Flow_Id')
            ->where('ar.No_Transaksi', $noTransaksi)
            ->orderByRaw('ISNULL(f.order_flow, 2147483647)')
            ->orderBy('ar.Id_Approval_Request')
            ->get(['ar.Flag_Approval', 'ar.Alasan', 'ar.Id_Approval_Request', 'f.Kode_Karyawan_Approver', 'f.order_flow']);

        if ($baris->isEmpty()) {
            return [];
        }

        $employees = $this->resolveApproverEmployees(
            $baris->pluck('Kode_Karyawan_Approver')->filter()->unique()->values(),
        );

        // Tahap menggantung yang PERTAMA sedang berjalan; sisanya masih antre.
        // Sama seperti cara engine terpusat menggambarkannya, supaya satu layar
        // tidak memakai dua bahasa untuk keadaan yang sama.
        $sudahAdaBerjalan = false;

        return $baris
            ->values()
            ->map(function ($b, $index) use ($employees, &$sudahAdaBerjalan) {
                $person = $b->Kode_Karyawan_Approver ? $employees->get($b->Kode_Karyawan_Approver) : null;

                if ($b->Flag_Approval === 'Y') {
                    $status = 'approved';
                } elseif ($b->Flag_Approval === 'T') {
                    $status = 'rejected';
                } elseif (! $sudahAdaBerjalan) {
                    $status = 'inprogress';
                    $sudahAdaBerjalan = true;
                } else {
                    $status = 'pending';
                }

                return [
                    'name' => $person->Nama ?? 'Approver',
                    'role' => $person->nama_jabatan ?? 'Approver',
                    'status' => $status,
                    'order' => (int) ($b->order_flow ?? $index + 1),
                    'alasan' => $b->Alasan,
                ];
            })
            ->all();
    }

    /**
     * Ringkasan progres dari daftar langkah, dalam bentuk yang sama dengan yang
     * dipulangkan engine terpusat — layar membacanya dengan kode yang sama.
     *
     * @param  array<int,array<string,mixed>>  $langkah
     * @return array<string,mixed>
     */
    private function ringkasLangkah(array $langkah): array
    {
        $total = count($langkah);
        $selesai = collect($langkah)->where('status', 'approved')->count();
        $berjalan = collect($langkah)->firstWhere('status', 'inprogress');

        return [
            'totalSteps' => $total,
            'completedSteps' => $selesai,
            'progressPercent' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
            'currentApproverName' => $berjalan['name'] ?? null,
        ];
    }

    /**
     * Preview urutan approver sebelum finalisasi (semua status 'pending').
     */
    private function buildApprovalPreviewSteps(string $kodeKaryawan): array
    {
        try {
            $resolved = collect(
                app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService::class)->getApproverFlow(
                    $kodeKaryawan,
                    \App\Services\KPI\Approval\KpiApprovalService::CONFIG_PERF,
                ),
            );
        } catch (\Throwable $e) {
            return [];
        }

        if ($resolved->isEmpty()) {
            return [];
        }

        $employees = $this->resolveApproverEmployees($resolved->pluck('kode_karyawan')->filter()->unique()->values());

        // Nama role untuk step grup/pool (mis. "Admin HC LnD Verifikator").
        $roleMaps = $this->resolveRoleNames(
            $resolved->map(fn ($a) => $a->source_reference ?? null),
            $resolved->map(fn ($a) => $a->group_id ?? null),
        );

        return $resolved
            ->values()
            ->map(function ($approver, $index) use ($employees, $roleMaps) {
                $type = $approver->type ?? 'SERIAL';

                if ($type === 'GROUP') {
                    $roleName = null;
                    if (! empty($approver->source_reference)) {
                        $roleName = optional($roleMaps['byCode']->get($approver->source_reference))->Role_Name;
                    }
                    if (! $roleName && ! empty($approver->group_id)) {
                        $roleName = optional($roleMaps['byId']->get($approver->group_id))->Role_Name;
                    }

                    $name = $roleName ?: 'Group Approver';
                    $role = 'Persetujuan Grup';
                } else {
                    $kode = $approver->kode_karyawan ?? null;
                    $person = $kode ? $employees->get($kode) : null;
                    $name = $person->Nama ?? 'Approver';
                    $role = $person->nama_jabatan ?? 'Approver';
                }

                return [
                    'name' => $name,
                    'role' => $role,
                    'status' => 'pending',
                    'order' => (int) ($approver->order_flow ?? $index + 1),
                ];
            })
            ->all();
    }

    /**
     * Progress persetujuan per approver dari view engine terpusat.
     */
    private function buildApprovalProgressSteps(string $noTransaksi): array
    {
        $rows = DB::table('N_HRIS_VW_Approval_Request_V2')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Flow_State', '!=', 'VOID')
            ->orderBy('Request_Order_Flow', 'asc')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $employees = $this->resolveApproverEmployees(
            $rows
                ->flatMap(fn ($r) => [$r->Approved_By_Kode ?? null, $r->Current_Approver ?? null])
                ->filter()
                ->unique()
                ->values(),
        );

        return $rows
            ->map(function ($r) use ($employees) {
                $state = $r->Flow_State;
                $flag = $r->Flag_Approval;

                if ($state === 'HISTORY' && $flag === 'Y') {
                    $status = 'approved';
                } elseif ($state === 'HISTORY' && $flag === 'T') {
                    $status = 'rejected';
                } elseif ($state === 'CURRENT') {
                    $status = 'inprogress';
                } else {
                    $status = 'pending';
                }

                $roleName = $r->Role_Name ?? null; // nama role/pool dari view (mis. "Admin HC LnD Verifikator")
                $isGroup = ($r->Flag_Group ?? null) === 'Y';

                if (in_array($status, ['approved', 'rejected'], true)) {
                    $personName = $r->Approved_By_Nama ?? ($r->Current_Approver_Name ?? null);
                    $kode = $r->Approved_By_Kode ?? ($r->Current_Approver ?? null);
                } else {
                    $personName = $r->Current_Approver_Name ?? null;
                    $kode = $r->Current_Approver ?? null;
                }

                $jabatan = $kode ? $employees->get($kode)->nama_jabatan ?? null : null;

                if ($personName) {
                    // Ada approver spesifik -> nama orang; subtitle pakai nama role (jika pool) atau jabatan.
                    $name = $personName;
                    $role = $roleName ?: ($jabatan ?: 'Approver');
                } else {
                    // Belum ada approver spesifik (pool/grup menunggu) -> tampilkan nama role.
                    $name = $roleName ?: ($isGroup ? 'Group Approver' : 'Menunggu approver');
                    $role = $isGroup ? 'Persetujuan Grup' : ($jabatan ?: 'Approver');
                }

                return [
                    'name' => $name,
                    'role' => $role,
                    'status' => $status,
                    'order' => (int) $r->Request_Order_Flow,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resolve nama + jabatan approver dari kumpulan Kode_Karyawan.
     */
    private function resolveApproverEmployees($kodes)
    {
        $kodes = collect($kodes)->filter()->unique()->values();

        if ($kodes->isEmpty()) {
            return collect();
        }

        return DB::table('Karyawan as k')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                'k.ID_Level_Jabatan',
                '=',
                'vg.ID_Level_Jabatan',
            )
            ->whereIn('k.Kode_Karyawan', $kodes->all())
            ->select('k.Kode_Karyawan', 'k.Nama', 'vg.nama_jabatan')
            ->get()
            ->keyBy('Kode_Karyawan');
    }

    /**
     * Resolve nama role dari N_HRIS_Role_Functional berdasarkan Role_Code dan/atau Id_Role.
     *
     * @return array{byCode:\Illuminate\Support\Collection, byId:\Illuminate\Support\Collection}
     */
    private function resolveRoleNames($codes, $ids): array
    {
        $codes = collect($codes)->filter()->unique()->values();
        $ids = collect($ids)->filter()->unique()->values();

        if ($codes->isEmpty() && $ids->isEmpty()) {
            return ['byCode' => collect(), 'byId' => collect()];
        }

        $roles = DB::table('N_HRIS_Role_Functional')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($q) use ($codes, $ids) {
                if ($codes->isNotEmpty()) {
                    $q->orWhereIn('Role_Code', $codes->all());
                }
                if ($ids->isNotEmpty()) {
                    $q->orWhereIn('Id_Role', $ids->all());
                }
            })
            ->select('Id_Role', 'Role_Code', 'Role_Name')
            ->get();

        return [
            'byCode' => $roles->keyBy('Role_Code'),
            'byId' => $roles->keyBy('Id_Role'),
        ];
    }

    public function createApprovalRequest($noTransaksi, $kodeKaryawan, bool $wa)
    {
        // config(), bukan env(): env() mengembalikan default setelah config:cache,
        // sehingga tombol rollback ke LEGACY mati tanpa tanda apa pun.
        $engine = strtoupper((string) config('services.kpi.perf_engine', 'CENTRAL'));

        if ($engine === 'LEGACY') {
            return $this->createApprovalRequestLegacy($noTransaksi, $kodeKaryawan, $wa);
        }

        $result = app(\App\Services\KPI\Approval\KpiApprovalService::class)->createPerfFlow($noTransaksi, $kodeKaryawan);

        if (! $result->success) {
            throw new \Exception($result->error ?? 'Gagal membuat flow approval performance.');
        }

        if ($wa && $result->hasFirstApprover()) {
            $this->sendApprovalNotification($noTransaksi, $kodeKaryawan, $result);
        }

        // Bila step pertama langsung verifikator ROLE (tanpa atasan), kirim notif
        // in-app ke role pool. No-op bila current step bukan role.
        app(\App\Services\KPI\Notification\KpiNotificationService::class)->notifyRolePoolForCurrentStep($noTransaksi);

        return $result;
    }

    /**
     * Kirim notifikasi WA + in-app ke approver pertama setelah flow approval dibuat.
     * INDIVIDUAL: WA (otomatis mirror in-app) + in-app.
     * GROUP: hanya in-app notification (tidak kirim WA).
     */
    private function sendApprovalNotification(string $noTransaksi, string $kodeKaryawan, $result): void
    {
        try {
            $requester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->select('Nama')->first();
            $namaRequester = $requester->Nama ?? (Auth::user()?->karyawan?->Nama ?? 'Karyawan');

            $isGroup = $result->isGroupFirst();

            if ($isGroup) {
                // GROUP: hanya in-app notification
                $this->sendGroupInAppNotification($noTransaksi, $kodeKaryawan, $result, $namaRequester);
            } else {
                // INDIVIDUAL: WA + in-app (in-app otomatis via mirror)
                $this->sendIndividualWaNotification($noTransaksi, $kodeKaryawan, $result, $namaRequester);
            }
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error('sendApprovalNotification error: '.$e->getMessage(), [
                'no_transaksi' => $noTransaksi,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Kirim WA ke approver individu (in-app otomatis via WhatsappNotificationMirrorService).
     */
    private function sendIndividualWaNotification(
        string $noTransaksi,
        string $kodeKaryawan,
        $result,
        string $namaRequester,
    ): void {
        $approverKode = $result->getFirstApproverKode();
        if (! $approverKode) {
            Log::channel('UpdatePerformanceController')->warning('sendIndividualWaNotification: approver kode kosong', [
                'no_transaksi' => $noTransaksi,
            ]);

            return;
        }

        $approver = DB::table('Karyawan')
            ->where('Kode_Karyawan', $approverKode)
            ->select('Kode_Karyawan', 'Nama', 'HP')
            ->first();

        if (! $approver) {
            Log::channel('UpdatePerformanceController')->warning(
                'sendIndividualWaNotification: data karyawan approver tidak ditemukan',
                ['approver_kode' => $approverKode],
            );

            return;
        }

        if (empty($approver->HP)) {
            Log::channel('UpdatePerformanceController')->warning(
                'sendIndividualWaNotification: approver tidak punya HP',
                ['kode_karyawan' => $approver->Kode_Karyawan],
            );

            return;
        }

        $pesan = $this->buildWaPayload($approver->Nama, $approver->HP, $namaRequester);

        WhatsappLogHelper::sendAndLog(
            $approver->Kode_Karyawan,
            $approver->Nama,
            $approver->HP,
            $noTransaksi,
            $pesan,
            function ($response) use ($approver, $noTransaksi) {
                try {
                    DB::table('HRIS_Approval_Request')
                        ->where('No_Transaksi', $noTransaksi)
                        ->where('Approver_Karyawan_Kode', $approver->Kode_Karyawan)
                        ->update(['Wa_Sent_Count' => DB::raw('COALESCE(Wa_Sent_Count, 0) + 1')]);
                } catch (\Exception $e) {
                    /*
                     * Dulu tertulis "Kolom mungkin belum ada" lalu dibuang.
                     * Dugaan itu tidak pernah bisa dibuktikan maupun dibantah,
                     * karena tak ada satu pun jejaknya — dan kalau kolomnya
                     * MEMANG belum ada di production, penghitung WA berhenti
                     * bertambah selamanya tanpa seorang pun tahu. Tetap tidak
                     * menggagalkan pengiriman WA; hanya berhenti diam.
                     */
                    Log::channel('UpdatePerformanceController')->warning(
                        'Gagal menaikkan Wa_Sent_Count',
                        [
                            'no_transaksi' => $noTransaksi,
                            'kode_karyawan' => $approver->Kode_Karyawan,
                            'kelas' => $e::class,
                            'pesan' => $e->getMessage(),
                            'sql' => $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : null,
                        ],
                    );
                }
                Log::channel('UpdatePerformanceController')->info(
                    "WA sukses dikirim ke approver {$approver->Kode_Karyawan}",
                    ['no_transaksi' => $noTransaksi],
                );
            },
            function ($reason, $response) use ($approver, $noTransaksi) {
                Log::channel('UpdatePerformanceController')->warning(
                    "Gagal kirim WA ke approver {$approver->Kode_Karyawan}",
                    ['reason' => $reason, 'no_transaksi' => $noTransaksi],
                );
            },
        );
    }

    /**
     * Kirim in-app notification ke semua anggota group approver (tanpa WA).
     */
    private function sendGroupInAppNotification(
        string $noTransaksi,
        string $kodeKaryawan,
        $result,
        string $namaRequester,
    ): void {
        $groupMembers = DB::table('HRIS_Approval_Group_Member as gm')
            ->join('Karyawan as k', 'gm.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('gm.Group_Approval_Id', $result->getFirstApproverGroupId())
            ->where('gm.Flag_Aktif', 'Y')
            ->select('k.Kode_Karyawan', 'k.Nama')
            ->get();

        if ($groupMembers->isEmpty()) {
            Log::channel('UpdatePerformanceController')->warning(
                'sendGroupInAppNotification: tidak ada anggota group',
                ['group_id' => $result->getFirstApproverGroupId()],
            );

            return;
        }

        $notificationService = app(\App\Services\SYSTEM\Notification\NotificationService::class);
        $actionUrl = '/performance-approval';

        // Ambil info transaksi untuk konteks notifikasi
        $perfInfo = DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $noTransaksi)
            ->select('Periode')
            ->first();
        $periode = $perfInfo->Periode ?? '-';

        $title = 'Update Performance KPI - Menunggu Persetujuan';
        $message =
            "No. Transaksi: {$noTransaksi}\n".
            "Jenis: Update Performance KPI\n".
            "Periode: {$periode}\n".
            "Pengaju: {$namaRequester}\n\n".
            'Segera lakukan persetujuan melalui menu Performance Approval.';

        foreach ($groupMembers as $member) {
            $notificationService->createNotification([
                'kode_karyawan' => $member->Kode_Karyawan,
                'type' => 'KPI_PERF_APPROVAL',
                'title' => $title,
                'message' => $message,
                'module' => 'HCIS',
                'severity' => 'info',
                'action_url' => $actionUrl,
                'reference_id' => $noTransaksi,
                'channel' => \App\Services\SYSTEM\Notification\NotificationService::DELIVERY_IN_APP,
                'created_by' => 'SYSTEM',
                'priority' => 3,
            ]);
        }

        Log::channel('UpdatePerformanceController')->info(
            'In-app notification terkirim ke '.$groupMembers->count().' anggota group',
            ['no_transaksi' => $noTransaksi, 'group_id' => $result->getFirstApproverGroupId()],
        );
    }

    /**
     * Build WhatsApp template payload.
     */
    private function buildWaPayload(string $namaApprover, string $hp, string $namaRequester): array
    {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $hp,
            'type' => 'template',
            'template' => [
                'name' => 'kpi_approval_pengajuan_dan_performance',
                'language' => [
                    'code' => 'id',
                    'policy' => 'deterministic',
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $namaApprover],
                            ['type' => 'text', 'text' => '1'],
                            ['type' => 'text', 'text' => 'Update Performance KPI'],
                            ['type' => 'text', 'text' => $namaRequester],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @deprecated Jalur legacy — insert ke KPI_Approval_Request_Performance.
     * Dipertahankan sebagai fallback (KPI_PERF_ENGINE=LEGACY). Akan dihapus di Fase 8.
     */
    public function createApprovalRequestLegacy($noTransaksi, $kodeKaryawan, bool $wa)
    {
        $approverInsertRaw = DB::table('KPI_Approval_Flow_Performance')
            ->where('Kode_Karyawan_Requester', $kodeKaryawan)
            ->whereNull('Status')
            ->get();

        if ($approverInsertRaw->isEmpty()) {
            throw new \Exception('Tidak ada approver yang ditemukan untuk pengajuan ini.');
        }

        $approverInsertFinal = $approverInsertRaw
            ->map(function ($e) use ($noTransaksi, $kodeKaryawan) {
                return [
                    'No_Transaksi' => $noTransaksi,
                    'Kode_Karyawan_Requester' => $kodeKaryawan,
                    'Flow_Id' => $e->Id_Approval_Flow,
                ];
            })
            ->toArray();

        if (empty($approverInsertFinal)) {
            throw new \Exception('Tidak ada approver yang ditemukan untuk pengajuan ini.');
        }
        $insertProccess = DB::table('KPI_Approval_Request_Performance')->insert($approverInsertFinal);

        if ($wa && $insertProccess) {
            $user = DB::table('KPI_Approval_Flow_Performance as a')
                ->select('b.HP', 'b.Nama', 'b.Kode_Karyawan')
                ->join('Karyawan as b', 'a.Kode_Karyawan_Approver', '=', 'b.Kode_Karyawan')
                ->where('a.Kode_Karyawan_Requester', $kodeKaryawan)
                ->whereNull('a.Status')
                ->orderBy('a.order_flow')
                ->first();

            if (! $user || ! $user->HP || $user->HP == '' || ! $user->Nama) {
                Log::channel('UpdatePerformanceController')->error(
                    'Approval tidak punya No HP/Nama! Karyawan : '.$kodeKaryawan,
                );

                return ResponseHelper::error('Approval tidak punya No HP/Nama!', 404);
            }

            $pesan = [
                'messaging_product' => 'whatsapp',
                'to' => $user->HP,
                'type' => 'template',
                'template' => [
                    'name' => 'kpi_approval_pengajuan_dan_performance',
                    'language' => [
                        'code' => 'id',
                        'policy' => 'deterministic',
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $user->Nama],
                                ['type' => 'text', 'text' => '1'],
                                ['type' => 'text', 'text' => 'Update Performance KPI'],
                                ['type' => 'text', 'text' => Auth::user()->karyawan->Nama],
                            ],
                        ],
                    ],
                ],
            ];

            $waResult = WhatsappLogHelper::sendAndLog(
                $user->Kode_Karyawan,
                $user->Nama,
                $user->HP,
                $noTransaksi,
                $pesan,
                function ($response) use ($kodeKaryawan, $noTransaksi, $user) {
                    DB::table('KPI_Approval_Request_Performance as a')
                        ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                        ->where('a.No_Transaksi', $noTransaksi)
                        ->whereNull('b.Status')
                        ->where('b.Kode_Karyawan_Approver', $user->Kode_Karyawan)
                        ->update(['a.Wa_Sent_Count' => DB::raw('COALESCE(Wa_Sent_Count, 0) + 1')]);

                    Log::channel('UpdatePerformanceController')->info(
                        "WA sukses dikirim ke approver {$user->Kode_Karyawan}",
                        [
                            'requester' => $kodeKaryawan,
                            'response' => $response,
                        ],
                    );
                },
                function ($reason, $response) use ($user, $noTransaksi, $pesan) {
                    Log::channel('UpdatePerformanceController')->warning(
                        "Gagal kirim WA ke approver {$user->Kode_Karyawan}",
                        [
                            'reason' => $reason,
                            'response' => $response,
                        ],
                    );

                    DB::table('Whatsapp_Log')->insert([
                        'Kode_Karyawan_Destination' => $user->Kode_Karyawan,
                        'Nama' => $user->Nama,
                        'HP' => $user->HP,
                        'No_Transaksi' => $noTransaksi,
                        'Error_Message' => is_array($response)
                            ? json_encode($response, JSON_UNESCAPED_UNICODE)
                            : $reason ?? 'Unknown error',
                        'RawMessage' => print_r($pesan, true),
                        'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        'Created_At' => now(),
                        'Is_Retried' => 0,
                    ]);
                },
            );
        }
    }

    // public function finalisasiPerformance(Request $request)
    // {
    //     $request->validate([
    //         'Id_Update_Performance_Sementara' => 'required|string',
    //     ]);

    //     $pengguna = Auth::user();

    //     $karyawan = DB::table('Karyawan')
    //                 ->where('UserID_Web', $pengguna->Id_Users)
    //                 ->first();

    //     if(!$karyawan){
    //         return ResponseHelper::error('Data Tidak Ditemukan !', 404);
    //     }

    //     try {
    //         $decodedId = Hashids::connection('custom')->decode($request->input('Id_Update_Performance_Sementara'));
    //         if (empty($decodedId)) {
    //             return ResponseHelper::error("ID performance sementara tidak valid.", 400);
    //         }
    //         $idHeaderSementara = $decodedId[0];
    //         $pengguna = Auth::user()->Id_Users;

    //         $result = DB::transaction(function () use ($idHeaderSementara, $pengguna) {
    //         $jadwalAktif = DB::table('KPI_Master_Penjadwalan')
    //                 ->where('Flag_Penilaian_KPI', 'Y')
    //                 ->where('Flag_Aktif', 'Y')
    //                 ->where(function($q){
    //                     $q->where('Jadwal_Mulai', '<=', now())
    //                     ->where('Jadwal_Akhir', '>=', now());
    //                 })
    //                 ->first();

    //             if (!$jadwalAktif) {
    //                 throw new \Exception("Tidak ada jadwal penilaian KPI yang aktif saat ini.");
    //             }

    //             $headerSementara = DB::table('KPI_Update_Performance_Sementara')
    //                 ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
    //                 ->where('Id_User', $pengguna)
    //                 ->first();

    //             if (!$headerSementara) {
    //                 throw new \Exception("Sesi update performance tidak ditemukan, sudah difinalisasi, atau bukan milik Anda.");
    //             }

    //             if($headerSementara->No_Transaksi_Ref){
    //                 $noTransaksiRealRef = str_replace('TAKPI', 'AKPI', $headerSementara->No_Transaksi_Ref);

    //                 DB::table('KPI_Update_Performance')
    //                     ->where('No_Transaksi', $noTransaksiRealRef)
    //                     ->where('Id_User', $pengguna)
    //                     ->update(['Flag_Aktif' => 'T']);
    //             }

    //             $detailsData = DB::table('KPI_Update_Performance_Detail_Sementara as upds')
    //                 ->join('KPI_Pengajuan_Det as pd', 'upds.Id_Pengajuan_Det', '=', 'pd.Id_Pengajuan_Det')
    //                 ->join('KPI_Pengajuan_Detail as p_detail', 'pd.Id_Pengajuan_Detail', '=', 'p_detail.Id_Pengajuan_Detail')
    //                 ->where('upds.Id_Update_Performance_Sementara', $idHeaderSementara)
    //                 ->where('upds.Flag_Aktif', 'Y')
    //                 ->select('upds.*', 'p_detail.Bobot')
    //                 ->get();

    //             if ($detailsData->isEmpty()) {
    //                 throw new \Exception("Tidak ada data KPI yang aktif untuk dihitung.");
    //             }

    //             $sumOfScores = $detailsData->sum(fn($item) => (float) $item->Achievement);
    //             $sumOfActiveBobot = $detailsData->sum(fn($item) => (float) $item->Bobot);
    //             $finalScorePercentage = $sumOfActiveBobot > 0 ? ($sumOfScores / $sumOfActiveBobot) * 100 : 0;

    //             $performanceLevel = DB::table('KPI_Master_Performance')
    //                 ->where('Range_Awal', '<=', $finalScorePercentage)
    //                 ->where('Range_Akhir', '>=', $finalScorePercentage)
    //                 ->first();

    //             if (!$performanceLevel) {
    //                 $performanceLevel = DB::table('KPI_Master_Performance')->orderByDesc('Range_Akhir')->first();
    //             }
    //             if (!$performanceLevel) {
    //                 throw new \Exception("Konfigurasi Master Performance tidak ditemukan.");
    //             }

    //             $bulan = date('m');
    //             $tahun = date('Y');
    //             $lastNo = DB::table('KPI_Update_Performance')
    //                         ->whereRaw("MONTH(Tanggal) = ?", [$bulan])
    //                         ->whereRaw("YEAR(Tanggal) = ?", [$tahun])
    //                         ->lockForUpdate()
    //                         ->select(DB::raw("MAX(CAST(RIGHT(No_Transaksi,4) AS INT)) as max_no"))
    //                         ->first();

    //             $nextNo = $lastNo && $lastNo->max_no ? intval($lastNo->max_no) + 1 : 1;
    //             $noUrut = str_pad($nextNo, 4, '0', STR_PAD_LEFT);
    //             $noTransaksi = "AKPI-{$bulan}-{$tahun}-{$noUrut}";
    //             $finalScoreFormatted = (fmod($finalScorePercentage, 1) == 0)
    //                                     ? (int)$finalScorePercentage
    //                                     : round($finalScorePercentage, 1);

    //             $idHeaderFinal = DB::table('KPI_Update_Performance')->insertGetId([
    //                 'No_Transaksi' => $noTransaksi,
    //                 'No_Transaksi_Ref' => $headerSementara->No_Transaksi_Ref ? str_replace('TAKPI', 'AKPI', $headerSementara->No_Transaksi_Ref) : null,
    //                 'Id_Pengajuan' => $headerSementara->Id_Pengajuan,
    //                 'Status' => NULL,
    //                 'Periode' => $headerSementara->Periode,
    //                 'Total_Score' => $finalScoreFormatted,
    //                 'Total_Score_Achievement' => $sumOfScores,
    //                 'Total_Bobot' => $sumOfActiveBobot,
    //                 'Status_Pengajuan' => 'menunggu',
    //                 'Id_Performance' => $performanceLevel->Id_Master_Performance,
    //                 'Jam' => now()->format('H:i:s'),
    //                 'Tanggal' => now(),
    //                 'Id_User' => $pengguna,
    //                 'Flag_Approval' => NULL,
    //                 'Flag_Team' => $headerSementara->Flag_Team ?? 'T',
    //                 'Flag_Aktif' => 'Y',
    //                 'Flag_Verifikasi' => NULL
    //             ]);

    //             $picaDataMap = DB::table('KPI_Update_Performance_Det_Sementara as pds')
    //                 ->join('KPI_Update_Performance_Detail_Sementara as upds', 'pds.Id_Update_Performance_Detail_Sementara', '=', 'upds.Id_Update_Performance_Detail_Sementara')
    //                 ->where('upds.Id_Update_Performance_Sementara', $idHeaderSementara)
    //                 ->where('upds.Flag_Pica', 'Y')
    //                 ->select('upds.Id_Pengajuan_Det', 'pds.Identifikasi', 'pds.Koreksi_Aksi', 'pds.Tanggal_Pelaksanaan')
    //                 ->get()
    //                 ->keyBy('Id_Pengajuan_Det');

    //             $batchDetails = [];
    //             foreach ($detailsData as $item) {
    //                 $batchDetails[] = [
    //                     'Id_Update_Performance' => $idHeaderFinal,
    //                     'Id_Pengajuan_Det' => $item->Id_Pengajuan_Det,
    //                     'Kode_Karyawan' => $karyawan->Kode_Karyawan,
    //                     'Nilai_Actual' => $item->Nilai_Actual,
    //                     'Score' => $item->Score,
    //                     'Achievement' => $item->Achievement,
    //                     'File_Berkas' => $item->File_Berkas,
    //                     'Flag_Pica' => $item->Flag_Pica,
    //                 ];
    //             }
    //             DB::table('KPI_Update_Performance_Detail')->insert($batchDetails);

    //             $detailMap = DB::table('KPI_Update_Performance_Detail')
    //                 ->where('Id_Update_Performance', $idHeaderFinal)
    //                 ->pluck('Id_Update_Performance_Detail', 'Id_Pengajuan_Det');

    //             $picaDetails = [];
    //             foreach ($detailsData as $item) {
    //                 if ($item->Flag_Pica === 'Y' && isset($detailMap[$item->Id_Pengajuan_Det])) {
    //                     $picaSementara = $picaDataMap->get($item->Id_Pengajuan_Det);
    //                     $picaDetails[] = [
    //                         'Id_Update_Performance_Detail' => $detailMap[$item->Id_Pengajuan_Det],
    //                         'Identifikasi' => $picaSementara->Identifikasi ?? 'Tidak ada',
    //                         'Koreksi_Aksi' => $picaSementara->Koreksi_Aksi ?? 'Tidak ada',
    //                         'Tanggal_Pelaksanaan' => $picaSementara->Tanggal_Pelaksanaan,
    //                     ];
    //                 }
    //             }
    //             if (!empty($picaDetails)) {
    //                 DB::table('KPI_Update_Performance_Det')->insert($picaDetails);
    //             }

    //             DB::table('KPI_Update_Performance_Sementara')
    //                 ->where('Id_Update_Performance_Sementara', $idHeaderSementara)
    //                 ->update([
    //                     'Flag_Pindah' => 'Y',
    //                     'Steps' => 1
    //                 ]);

    //             return [
    //                 'message' => 'Data performance berhasil difinalisasi.',
    //                 'sum_of_scores' => round($sumOfScores, 2),
    //                 'sum_of_active_bobot' => round($sumOfActiveBobot, 2),
    //                 'total_score' => round($finalScorePercentage, 2),
    //                 'performance_level' => $performanceLevel->Keterangan ?? 'N/A',
    //                 'no_transaksi' => $noTransaksi
    //             ];
    //         });
    //         return ResponseHelper::success($result, $result['message'], 201);
    //     } catch (\Exception $e) {
    //         Log::channel('UpdatePerformanceController')
    //             ->error('FINALISASIPERFORMANCE ERROR: ' . $e->getMessage() . ' Line: ' . $e->getLine());
    //         return ResponseHelper::error('Gagal memfinalisasi data, Terjadi Kesalahan', 500);
    //     }
    // }

    public function updateGetDateDummy2(Request $request)
    {
        // Penjadwalan OPSIONAL. Acuan by-pass sekarang global menurut PERIODE:
        // bulan+tahun-lah yang menentukan, dan jadwalnya diturunkan dari periode
        // itu bila tidak dipilih. Dulu wajib, sehingga mengganti bulan saja tidak
        // mungkin tanpa lebih dulu memilih jadwal — padahal jadwal yang tepat
        // untuk bulan itu justru yang sedang dicari.
        $request->validate([
            'id_master_penjadwalan' => 'nullable',
            'bulan' => 'required|numeric|min:1|max:12',
            'tahun' => 'required|numeric',
        ]);

        try {
            $Id_Master_Penjadwalan = null;

            if ($request->filled('id_master_penjadwalan')) {
                try {
                    // decode Id_Master_Penjadwalan dari hashids
                    $Id_Master_Penjadwalan = Hashids::connection('custom')->decode(
                        $request->id_master_penjadwalan,
                    )[0] ?? null;
                } catch (\Exception $e) {
                    $Id_Master_Penjadwalan = null;
                }

                if (! $Id_Master_Penjadwalan) {
                    return response()->json(
                        [
                            'success' => false,
                            'status' => 400,
                            'message' => 'Format Kunci Tidak Valid.',
                        ],
                        400,
                    );
                }
            }

            $newDate = Carbon::createFromDate($request->tahun, $request->bulan, 1)->startOfDay()->format('Y-m-d H:i:s');

            // NULL disimpan apa adanya: itulah tanda "ikuti periode saja", dan
            // pembacanya menurunkan jadwal dari Tanggal di atas.
            DB::table('KPI_Get_Date_Dummy2')
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Tanggal' => $newDate,
                    'Id_Master_Penjadwalan' => $Id_Master_Penjadwalan,
                ]);

            return ResponseHelper::success('Periode berhasil diperbarui ke: '.$newDate);
        } catch (\Exception $e) {
            Log::channel('UpdatePerformanceController')->error($e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan: '.$e->getMessage(), 500);
        }
    }

    /**
     * Jadwal penilaian yang mewakili sebuah periode acuan by-pass.
     *
     * Dipakai ketika penjadwalan tidak dipilih (acuan global menurut periode).
     * Urutan pemilihannya: periode PERSIS lebih dulu, lalu yang aktif, lalu yang
     * jadwalnya berakhir paling akhir. Periode yang persis menang atas keaktifan
     * dengan sengaja — yang dipilih pengguna adalah bulannya, dan membuka bulan
     * yang berbeda dari yang diminta persis itulah bug yang berulang di jalur ini.
     */
    private function jadwalPenilaianUntukPeriode(Carbon $periode): ?object
    {
        $bulan = $periode->format('Y-m');

        return DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->where(function ($q) use ($bulan, $periode) {
                $q->where('Periode', $bulan)->orWhere('Tahun', $periode->year);
            })
            ->orderByRaw('CASE WHEN Periode = ? THEN 0 ELSE 1 END', [$bulan])
            ->orderByRaw("CASE WHEN Flag_Aktif = 'Y' THEN 0 ELSE 1 END")
            ->orderByDesc('Jadwal_Akhir')
            ->first();
    }

    public function getMasterPenjadwalanForByPass()
    {
        $getData = DB::table('KPI_Master_Penjadwalan')->where('Flag_Penilaian_KPI', 'Y')->get();
        $encodedData = $getData->map(function ($item) {
            $item->Id_Master_Penjadwalan = Hashids::connection('custom')->encode($item->Id_Master_Penjadwalan);
            if (! empty($item->Id_User)) {
                $item->Id_User = Hashids::connection('custom')->encode($item->Id_User);
            }

            return $item;
        });

        return ResponseHelper::success($encodedData, 'Data Berhasil Ditemukan !');
    }

    private function safeRollback(): void
    {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
