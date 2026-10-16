<?php

namespace App\Http\Controllers\KPI\Performance\Parameter;

use App\Helpers\ResponseHelper;
use App\Helpers\WhatsappLogHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\Approval\KpiApprovalService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalFlowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

class FormKpiController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // dd($this->getListTarget(11));

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
            ->where('m.Flag_Form_KPI', 'Y')
            ->where('m.Flag_Perorangan', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->select(
                'm.*',
                DB::raw("
                    CONCAT(
                        m.Nama_Kegiatan,
                        ' - Start Bulan Ke - ',
                        RIGHT(p.Start_Periode, 2)
                    ) AS nama_kegiatan_label
                "),
            )
            ->get();

        $activePerorangan = $jadwalPerorangan->first(function ($item) use ($now) {
            $mulai = Carbon::parse($item->Jadwal_Mulai);
            $akhir = Carbon::parse($item->Jadwal_Akhir);

            return $now->between($mulai, $akhir);
        });

        $jadwalGlobal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Perorangan', 'T')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Form_KPI', 'Y')
            ->orderBy('Jadwal_Mulai', 'asc')
            ->get();

        $activeGlobal = $jadwalGlobal->first(function ($item) use ($now) {
            $mulai = Carbon::parse($item->Jadwal_Mulai);
            $akhir = Carbon::parse($item->Jadwal_Akhir);

            return $now->between($mulai, $akhir);
        });

        $jadwalValid = $activePerorangan ?? $activeGlobal;

        if ($jadwalValid) {
            $approver = $this->getKpiParameterApproverPreview($kodeKaryawan);

            $jadwalValid->Id_Master_Penjadwalan = Hashids::connection('custom')->encode(
                $jadwalValid->Id_Master_Penjadwalan,
            );

            return inertia('KPI/Performance/Parameter/FormKpi/formKPI', [
                'penjadwalan' => $jadwalValid,
                'approvers' => $approver,
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
                'description' => 'Halaman pengisian parameter KPI akan segera dibuka sesuai jadwal. Silahkan hubungi HC jika ada kendala.',
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
            'title' => 'Periode Pengisian Parameter KPI Telah Berakhir',
            'description' => 'Periode pengisian parameter KPI telah ditutup. Silahkan hubungi HC jika ada kendala.',
        ])->withViewData([
            'layout' => 'layouts.master-withoutnavbar',
        ]);
    }

    public function indexTeam()
    {
        $now = date('Y-m-d');
        $now = date('Y-m-d');

        $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
        $level = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'kt.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.Id_Level_Jabatan', '=', 'k.Id_Level_Jabatan')
            ->select('vg.Id_Level as value', 'vg.nama_level as label')
            ->groupBy('vg.Id_Level', 'vg.nama_level')
            ->get();
        $depart = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'kt.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.Id_Divisi_Sub_Divisi')
            ->select('vd.Id_Sub_Divisi as value', 'vd.nama_sub_divisi as label')
            ->groupBy('vd.Id_Sub_Divisi', 'vd.nama_sub_divisi')
            ->get();
        // dd($level);

        // Payung tim: GLOBAL lebih dulu, perorangan hanya bila tak ada global
        // yang berjalan. Aturannya tinggal di payungTimBerlaku() dan dipakai
        // juga oleh pratinjau serta simpan, supaya yang bisa dibuka dan yang
        // bisa disimpan tidak pernah berselisih.
        $PenjadwalanRaw = $this->payungTimBerlaku($now);

        if ($PenjadwalanRaw) {
            $PenjadwalanRaw = (object) [
                'id' => $PenjadwalanRaw->Id_Master_Penjadwalan,
                'deskripsi' => $PenjadwalanRaw->Deskripsi,
                'jadwal_mulai' => $PenjadwalanRaw->Jadwal_Mulai,
                'jadwal_akhir' => $PenjadwalanRaw->Jadwal_Akhir,
                'tahun' => $PenjadwalanRaw->Tahun,
                'nama_kegiatan' => $PenjadwalanRaw->Nama_Kegiatan,
            ];
        }
        // dd($PenjadwalanRaw);

        if ($PenjadwalanRaw) {
            $PenjadwalanRaw->id = Hashids::connection('custom')->encode($PenjadwalanRaw->id);
        }

        $Penjadwalan = $PenjadwalanRaw;

        // dd($Penjadwalan);
        return inertia('KPI/Performance/Parameter/FormKpi/team/FormKPITeam', [
            'penjadwalan' => $Penjadwalan,
            'department' => $depart,
            'level' => $level,
            // Kosong saat payungnya ketemu; berisi SEBAB saat tidak.
            'sebabTanpaPenjadwalan' => $Penjadwalan ? [] : $this->sebabTanpaPayungTim($now),
            // SELALU terisi — lihat catatan pada statusJadwalAnggotaTim().
            'statusJadwalAnggota' => $this->statusJadwalAnggotaTim($Kode_Karyawan, $now),
        ]);
    }

    /**
     * Status penjadwalan Form KPI untuk TIAP anggota tim, terlepas dari ada
     * tidaknya payung tim hari ini.
     *
     * ── Kenapa per anggota, dan kenapa selalu ────────────────────────────────
     *
     * Payung tim adalah SATU jadwal untuk seluruh tim: begitu ia tak ketemu,
     * halaman menutup untuk semua orang sekaligus dan tak memberi tahu apa pun.
     * Padahal penjadwalan perorangan pada dasarnya per orang — anggota A bisa
     * terjadwal mulai Maret sementara anggota B mulai Juni, dan seorang leader
     * berhak tahu itu tanpa harus menunggu ada jadwal yang kebetulan terbuka.
     *
     * Kemampuannya sebenarnya sudah ada: N_KPI_Master_Penjadwalan_Perorangan
     * menyimpan Start_Periode per Kode_Karyawan. Yang belum ada hanyalah jalan
     * bagi leader untuk melihatnya. Daftar ini menutup celah itu, dan ia sengaja
     * dihitung SELALU — bukan hanya ketika payungnya kosong — supaya leader bisa
     * memeriksa cakupan timnya kapan saja, termasuk saat halaman terbuka.
     *
     * Ini murni pembacaan. Ia tidak mengubah siapa yang boleh mengisi apa;
     * gerbang pengisian tetap di payungTimBerlaku() dan bolehJadiPayungTim().
     *
     * @return array<int,array<string,mixed>>
     */
    private function statusJadwalAnggotaTim(?string $kodeLeader, string $now): array
    {
        if (empty($kodeLeader)) {
            return [];
        }

        $anggota = DB::table('KPI_Karyawan_Team as kt')
            ->join('Karyawan as k', 'kt.Kode_Karyawan_Team', '=', 'k.Kode_Karyawan')
            ->where('kt.Kode_Karyawan_Leader', $kodeLeader)
            ->where('kt.Flag_Aktif', 'Y')
            ->orderBy('k.Nama')
            ->get(['k.Kode_Karyawan', 'k.Nama']);

        if ($anggota->isEmpty()) {
            return [];
        }

        // SATU kueri untuk seluruh anggota, bukan satu per anggota: daftar ini
        // dihitung pada setiap muat halaman tim.
        $kodeAnggota = $anggota->pluck('Kode_Karyawan')
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->values();

        $roster = collect();
        foreach ($kodeAnggota->chunk(500) as $bagian) {
            $roster = $roster->merge(
                DB::table('N_KPI_Master_Penjadwalan_Perorangan as p')
                    ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
                    ->whereIn('p.Kode_Karyawan', $bagian->all())
                    ->where('p.Flag_Aktif', 'Y')
                    ->where('m.Flag_Form_KPI', 'Y')
                    ->orderByDesc('m.Id_Master_Penjadwalan')
                    ->get([
                        'p.Kode_Karyawan',
                        'p.Start_Periode',
                        'm.Id_Master_Penjadwalan',
                        'm.Nama_Kegiatan',
                        'm.Tahun',
                        'm.Jadwal_Mulai',
                        'm.Jadwal_Akhir',
                        'm.Flag_Aktif',
                    ]),
            );
        }

        $perKode = $roster->groupBy(fn ($r) => trim((string) $r->Kode_Karyawan));

        return $anggota->map(function ($a) use ($perKode, $now) {
            $kode = trim((string) $a->Kode_Karyawan);
            $jadwal = ($perKode[$kode] ?? collect())->first();

            if (! $jadwal) {
                return [
                    'kode_karyawan' => $kode,
                    'nama' => trim((string) $a->Nama),
                    'terjadwal' => false,
                    'sumber' => null,
                    'nama_jadwal' => null,
                    'tahun' => null,
                    'start_periode' => null,
                    'mulai' => null,
                    'akhir' => null,
                    'terbuka' => false,
                    'keterangan' => 'Belum terdaftar di penjadwalan perorangan mana pun',
                ];
            }

            $mulai = $jadwal->Jadwal_Mulai ? substr((string) $jadwal->Jadwal_Mulai, 0, 10) : null;
            $akhir = $jadwal->Jadwal_Akhir ? substr((string) $jadwal->Jadwal_Akhir, 0, 10) : null;
            $aktif = ($jadwal->Flag_Aktif ?? 'T') === 'Y';
            $terbuka = $aktif && $mulai !== null && $akhir !== null && $now >= $mulai && $now <= $akhir;

            return [
                'kode_karyawan' => $kode,
                'nama' => trim((string) $a->Nama),
                'terjadwal' => true,
                'sumber' => 'perorangan',
                'nama_jadwal' => trim((string) ($jadwal->Nama_Kegiatan ?? '')) ?: 'Penjadwalan',
                'tahun' => $jadwal->Tahun,
                'start_periode' => $jadwal->Start_Periode,
                'mulai' => $mulai,
                'akhir' => $akhir,
                'terbuka' => $terbuka,
                'keterangan' => match (true) {
                    ! $aktif => 'Penjadwalannya dinonaktifkan',
                    $mulai === null || $akhir === null => 'Jendela pengisian belum diisi',
                    $now < $mulai => "Jendela pengisian baru dibuka {$mulai}",
                    $now > $akhir => "Jendela pengisian sudah ditutup {$akhir}",
                    default => 'Terbuka',
                },
            ];
        })->values()->all();
    }

    /**
     * Kenapa halaman Form KPI Tim tidak menemukan payung hari ini.
     *
     * ── Kenapa ini perlu ada ─────────────────────────────────────────────────
     *
     * Layar kosongnya hanya berkata "Tidak Ada Penjadwalan Aktif — belum ada
     * periode KPI yang aktif saat ini". Bagi admin yang BARU SAJA membuat
     * penjadwalan perorangan dan memasukkan seluruh anggotanya, kalimat itu
     * terbaca seperti keliru, dan kesimpulan wajarnya adalah "fiturnya rusak".
     *
     * Padahal syaratnya dua, dan keduanya tak terlihat dari layar: penjadwalan
     * harus Flag_Aktif='Y' DAN hari ini harus berada di antara Jadwal_Mulai dan
     * Jadwal_Akhir. Yang kedua inilah yang paling sering terlewat, karena orang
     * memikirkan PERIODE (bulan yang dinilai) sementara gerbangnya memakai
     * JENDELA PENGISIAN — dua kolom yang berbeda dan bisa berjauhan.
     *
     * Yang dipulangkan bukan tebakan melainkan daftar penjadwalan Form KPI yang
     * benar-benar ada berikut alasan spesifik tiap satunya, sehingga layar bisa
     * menyebut tanggal yang menutup pintunya alih-alih menyangkal keberadaannya.
     *
     * @return array<int,array{nama:string,periode:?string,mulai:?string,akhir:?string,perorangan:bool,sebab:string}>
     */
    private function sebabTanpaPayungTim(string $now): array
    {
        return DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Form_KPI', 'Y')
            ->orderByDesc('Id_Master_Penjadwalan')
            ->limit(8)
            ->get(['Nama_Kegiatan', 'Periode', 'Jadwal_Mulai', 'Jadwal_Akhir', 'Flag_Aktif', 'Flag_Perorangan'])
            ->map(function ($j) use ($now) {
                $mulai = $j->Jadwal_Mulai ? substr((string) $j->Jadwal_Mulai, 0, 10) : null;
                $akhir = $j->Jadwal_Akhir ? substr((string) $j->Jadwal_Akhir, 0, 10) : null;

                $sebab = match (true) {
                    ($j->Flag_Aktif ?? 'T') !== 'Y' => 'Penjadwalan dinonaktifkan',
                    $mulai === null || $akhir === null => 'Jendela pengisian belum diisi',
                    $now < $mulai => "Jendela pengisian baru dibuka {$mulai}",
                    $now > $akhir => "Jendela pengisian sudah ditutup {$akhir}",
                    default => 'Seharusnya berlaku — laporkan bila layar tetap kosong',
                };

                return [
                    'nama' => trim((string) ($j->Nama_Kegiatan ?? '')) ?: 'Penjadwalan',
                    'periode' => $j->Periode ?: null,
                    'mulai' => $mulai,
                    'akhir' => $akhir,
                    'perorangan' => ($j->Flag_Perorangan ?? 'T') === 'Y',
                    'sebab' => $sebab,
                ];
            })
            ->values()
            ->all();
    }

    public function getStatus(string $hashId)
    {
        try {
            $id = Hashids::connection('custom')->decode($hashId)[0] ?? null;
            if (empty($id)) {
                return ResponseHelper::error('ID Formula tidak valid', 400);
            }

            $pengguna = Auth::user()->Id_Users;

            $query = "select
                    a.No_Transaksi,
                    a.Flag_Approval,
                    a.Alasan_Reject,
                    a.Tanggal,
                    a.Jam,
                    (
                        select
                        top 1 v.Nama
                        from
                        KPI_Approval_Request_Parameter z,
                        KPI_Approval_Flow_Parameter y,
                        Karyawan v
                        where
                        y.Kode_Karyawan_Approver = v.Kode_Karyawan
                        and y.Id_Approval_Flow = z.Flow_Id
                        and a.No_Transaksi = z.No_Transaksi
                        and (z.Flag_Approval = 'T' OR z.Flag_Approval is null)
                        order by
                        y.order_flow
                    ) as approver,
                    (
                        select
                        top 1 z.Flag_Approval
                        from
                        KPI_Approval_Request_Parameter z,
                        KPI_Approval_Flow_Parameter y
                        where
                        y.Id_Approval_Flow = z.Flow_Id
                        and a.No_Transaksi = z.No_Transaksi
                        and (z.Flag_Approval = 'T' OR z.Flag_Approval is null)
                        order by
                        y.order_flow
                    ) as approval,
                    (
                        select
                        top 1 y.order_flow
                        from
                        KPI_Approval_Request_Parameter z,
                        KPI_Approval_Flow_Parameter y
                        where
                        y.Id_Approval_Flow = z.Flow_Id
                        and a.No_Transaksi = z.No_Transaksi
                        and (z.Flag_Approval = 'T' OR z.Flag_Approval is null)
                        order by
                        y.order_flow
                    ) as current_flow
                from
                KPI_Pengajuan a
                where
                a.Flag_Aktif = 'Y'
                and a.status is null
                    and a.Id_Penjadwalan = ?
                    and a.Id_User = ?";

            $headerRaw = DB::select($query, [$id, $pengguna]);
            $header = collect($headerRaw)->first();

            if (! $header) {
                return ResponseHelper::error('Belum ada submit KPI', 200);
            }

            // ambil detail + target
            $rawData = DB::table('KPI_Pengajuan as a')
                ->join('KPI_Pengajuan_Detail as b', 'a.Id_Pengajuan', '=', 'b.Id_Pengajuan')
                ->join('KPI_Pengajuan_Det as c', 'c.Id_Pengajuan_detail', '=', 'b.Id_Pengajuan_detail')
                ->join('KPI_Target_Breakdown as tb', 'tb.Id_Target_Breakdown', 'c.Id_Target_Breakdown')
                ->join('KPI_Master_Target as mt', 'mt.Id_Master_Target', '=', 'b.Id_Master_Target')
                ->join('KPI_Master_Satuan as d', 'b.Id_Master_Satuan', '=', 'd.Id_Master_Satuan')
                ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'a.Id_Penjadwalan')
                ->select(
                    'b.Id_Pengajuan_Detail',
                    'b.Nama_Parameter',
                    'b.Id_Bsc_Kategori',
                    'mp.Tahun',
                    'b.Deskripsi',
                    'b.Id_Master_Formula',
                    'b.Id_Master_Satuan',
                    'd.Simbol_Satuan as Nama_Satuan',
                    'd.Format_Input',
                    'b.Bobot',
                    'b.Id_Master_Target',
                    'b.Alasan_Tolak',
                    // 'c.Nama_Target',
                    'c.Nilai_Target',
                    'tb.Id_Target_Breakdown',
                    'tb.Nama_Breakdown',
                    'mt.Nama_Target',
                )
                ->where('a.Id_Penjadwalan', $id)
                ->where('a.Id_User', $pengguna)
                ->whereNull('a.Status')
                ->where('a.Flag_Aktif', 'Y')
                ->orderBy('b.Id_Pengajuan_Detail')
                ->get();

            // group by detail
            $grouped = $rawData->groupBy('Id_Pengajuan_Detail');

            $kpis = $grouped
                ->map(function ($items) {
                    $first = $items->first();

                    return [
                        'parameter' => $first->Nama_Parameter,
                        'Alasan_Tolak' => $first->Alasan_Tolak,
                        'bscCategory' => Hashids::connection('custom')->encode($first->Id_Bsc_Kategori),
                        'definition' => $first->Deskripsi,
                        'formula' => Hashids::connection('custom')->encode($first->Id_Master_Formula),
                        'unit' => Hashids::connection('custom')->encode($first->Id_Master_Satuan),
                        'selectedScale' => $first->Nama_Target,
                        'Nama_Satuan' => $first->Nama_Satuan,
                        'Format_Input' => $first->Format_Input,
                        'weight' => $first->Bobot,
                        'target' => Hashids::connection('custom')->encode($first->Id_Master_Target),
                        'targetSimpan' => $items
                            ->mapWithKeys(function ($item) {
                                $encodedId = Hashids::connection('custom')->encode($item->Id_Target_Breakdown);

                                return [$encodedId => $item->Nilai_Target];
                            })
                            ->toArray(),
                        'targetDataPreview' => $items
                            ->map(function ($item) {
                                return [
                                    'month' => $item->Nama_Breakdown,
                                    'period' => $item->Tahun,
                                    'target' => (float) $item->Nilai_Target,
                                ];
                            })
                            ->toArray(),

                        'availableUnits' => [],
                    ];
                })
                ->values()
                ->toArray();

            $isSubmitted = $header ? true : false;

            if ($header && $header->Flag_Approval == 'T') {
                $status = 'rejected';
            } elseif ($header && $header->Flag_Approval == 'Y') {
                $status = 'approved';
            } elseif ($header && $header->Flag_Approval == null) {
                $status = 'pending';
            } else {
                $status = null;
            }

            // Read-path approver list: cabang CENTRAL (HRIS_Approval_Request) vs LEGACY.
            $isCentral = app(\App\Services\KPI\Approval\KpiApprovalService::class)->isCentral($header->No_Transaksi);

            if ($isCentral) {
                $approvals = DB::table('N_HRIS_VW_Approval_Request_V2 as a')
                    ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'a.Current_Approver')
                    ->leftJoin(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                        'k.ID_Level_Jabatan',
                        '=',
                        'vg.ID_Level_Jabatan',
                    )
                    ->where('a.No_Transaksi', $header->No_Transaksi)
                    ->orderBy('a.Request_Order_Flow')
                    ->select(
                        DB::raw('COALESCE(k.Nama, a.Role_Name, a.Current_Approver) as name'),
                        DB::raw('COALESCE(vg.nama_jabatan, a.Role_Name) as role'),
                        'a.Flag_Approval as Flag_approval',
                    )
                    ->get();
            } else {
                $approvals = DB::table('KPI_Approval_Request_Parameter as a')
                    ->select('k.Nama as name', 'vg.nama_jabatan as role', 'a.Flag_approval')
                    ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                    ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'b.Kode_Karyawan_Approver')
                    ->join(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                        'k.ID_Level_Jabatan',
                        '=',
                        'vg.ID_Level_Jabatan',
                    )
                    ->where('a.No_Transaksi', $header->No_Transaksi)
                    ->orderBy('b.order_flow')
                    ->get();
            }

            $firstNullFound = false;
            $hasRejectedApproval = $approvals->contains(fn ($item) => $item->Flag_approval === 'T');

            $approvers = $approvals->map(function ($item) use (&$firstNullFound, $hasRejectedApproval) {
                if ($item->Flag_approval === 'Y') {
                    $item->status = 'approved';
                } elseif ($item->Flag_approval === 'T') {
                    $item->status = 'rejected';
                } elseif (is_null($item->Flag_approval)) {
                    if (! $hasRejectedApproval && ! $firstNullFound) {
                        $item->status = 'inprogress';
                        $firstNullFound = true;
                    } else {
                        $item->status = 'pending';
                    }
                }

                return $item;
            });

            $approvalData = $header;

            $result = [
                // 'No_Transaksi' => $header->No_Transaksi,
                'approvers' => $approvers,
                'isSubmitted' => $isSubmitted,
                'submissionDate' => Carbon::parse($header->Tanggal)->format('Y-m-d'),
                'submissionTime' => Carbon::parse($header->Jam)->format('H:i:s'),
                'kpis' => $kpis,
                'status' => $status,
                // Alasan penolakan GLOBAL (komentar umum approver/verifikator) — hanya
                // relevan saat status 'rejected'.
                'alasan_reject_global' => $status === 'rejected' ? ($header->Alasan_Reject ?? null) : null,
                'approvalData' => $approvalData,
            ];

            return ResponseHelper::success($result, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('formKPIController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    // public function getDataCurrentTeam(Request $request, string $hashId)
    // {
    //     try {
    //         $search = strip_tags($request->query('search', ''));
    //         $status = $request->query('status', '');
    //         $level = $request->query('level', '');
    //         $division = $request->query('division', '');
    //         $history = $request->query('history', '');

    //         // Decode hash & validasi
    //         $id = Hashids::connection('custom')->decode($hashId)[0] ?? null;
    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
    //         if (!$id) return ResponseHelper::error("ID Formula tidak valid", 400);

    //         // Ambil jadwal aktif
    //         $jadwal = DB::table('KPI_Master_Penjadwalan')
    //             ->where('Id_Master_Penjadwalan', $id)
    //             ->where('Flag_Aktif', 'Y')
    //             ->where('Flag_Form_KPI', 'Y')
    //             ->first();
    //         if (!$jadwal){
    //             return ResponseHelper::error("Tidak ada jadwal yang cocok", 404);
    //         }

    //         // Query utama - hanya data dasar tanpa detail KPI
    //         $queryRaw = DB::table('KPI_Karyawan_Team as kt')
    //             ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
    //             ->join('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
    //             ->join('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'k.ID_Level_Jabatan', '=', 'vg.ID_Level_Jabatan')
    //             ->leftJoin('KPI_Pengajuan as p', function($join) use ($jadwal) {
    //                 $join->on('p.Id_User', '=', 'k.UserId_Web')
    //                     ->where('p.Id_Penjadwalan', '=', $jadwal->Id_Master_Penjadwalan)
    //                     ->whereNull('p.Status')
    //                     ->where('p.Flag_Aktif', 'Y');
    //             })
    //             ->where('kt.Kode_Karyawan_Leader', $Kode_Karyawan)
    //             ->where('kt.Flag_Aktif', 'Y')
    //             ->select(
    //                 'kt.Id_Karyawan_Team as id',
    //                 'k.Nama as name',
    //                 'k.Kode_Karyawan as employeeId',
    //                 'k.UserId_Web',
    //                 'vg.nama_jabatan as position',
    //                 'vd.nama_sub_divisi as division',
    //                 'p.Id_Pengajuan as Pengajuan',
    //                 'p.Flag_Approval',
    //                 'p.Tanggal',
    //                 'p.Jam',
    //                 'p.No_Transaksi'
    //             );

    //         // Hitung stats (tetap diperlukan untuk quick stat)
    //         $totalApproved = (clone $queryRaw)
    //             ->where('p.Flag_Approval', 'Y')
    //             ->count();

    //         $totalRejected = (clone $queryRaw)
    //             ->where('p.Flag_Approval', 'T')
    //             ->count();

    //         $totalWithoutKPI = (clone $queryRaw)
    //             ->whereNull('p.Id_Pengajuan')
    //             ->count();

    //         $totalPending = (clone $queryRaw)
    //             ->whereNotNull('p.Id_Pengajuan')
    //             ->whereNull('p.Flag_Approval')
    //             ->count();

    //         $totalAll = (clone $queryRaw)->count();

    //         // Apply filters
    //         if(!empty($history)){
    //             $queryRaw->whereNotNull('p.Id_Pengajuan');
    //         }

    //         if(!empty($level)){
    //             $queryRaw->where('vg.Id_Level', $level);
    //         }

    //         if(!empty($division)){
    //             $queryRaw->where('vg.Id_Divisi', $division);
    //         }

    //         if(!empty($status)){
    //             if ($status === 'approved') {
    //                 $queryRaw->where('p.Flag_Approval', 'Y');
    //             } elseif ($status === 'rejected') {
    //                 $queryRaw->where('p.Flag_Approval', 'T');
    //             } elseif ($status === 'pending') {
    //                 $queryRaw->whereNull('p.Flag_Approval')->whereNotNull('p.Id_Pengajuan');
    //             }
    //         }

    //         if (!empty($search)) {
    //             $queryRaw->where(function ($q) use ($search) {
    //                 $q->where('vg.nama_level', 'LIKE', "%{$search}%")
    //                 ->orWhere('k.Nama', 'LIKE', "%{$search}%")
    //                 ->orWhere('k.Kode_Karyawan', 'LIKE', "%{$search}%")
    //                 ->orWhere('vd.nama_divisi', 'LIKE', "%{$search}%")
    //                 ->orWhere('vd.nama_sub_divisi', 'LIKE', "%{$search}%")
    //                 ->orWhere('vg.nama_jabatan', 'LIKE', "%{$search}%");
    //             });
    //         }

    //         // Ambil semua data sekaligus (hanya data dasar)
    //         $teams = $queryRaw
    //                 ->orderBy('k.Nama')
    //                 ->get();

    //         if ($teams->isEmpty()){
    //             return ResponseHelper::error("Tim tidak ditemukan", 404);
    //         }

    //         // Process data dasar saja - TANPA detail KPI
    //         $results = $teams->map(function ($team) {
    //             $hasSubmittedKPI = !is_null($team->Pengajuan);

    //             // Tentukan status dasar
    //             $submissionStatus = 'draft';
    //             $lastUpdate = null;

    //             if ($hasSubmittedKPI) {
    //                 $submissionStatus = match ($team->Flag_Approval) {
    //                     'T' => 'rejected',
    //                     'Y' => 'approved',
    //                     null => 'waiting_approval',
    //                     default => 'draft'
    //                 };

    //                 // Fix datetime parsing - gunakan hanya Tanggal saja atau gabungkan dengan benar
    //                 if ($team->Tanggal) {
    //                     // Jika hanya ada tanggal, gunakan tanggal saja
    //                     $lastUpdate = Carbon::parse($team->Tanggal)->format('Y-m-d');

    //                     // Jika ada jam, gabungkan dengan benar
    //                     if ($team->Jam) {
    //                         try {
    //                             $lastUpdate = Carbon::parse($team->Tanggal . ' ' . $team->Jam)->format('Y-m-d H:i:s');
    //                         } catch (\Exception $e) {
    //                             // Fallback ke tanggal saja jika parsing gagal
    //                             $lastUpdate = Carbon::parse($team->Tanggal)->format('Y-m-d');
    //                         }
    //                     }
    //                 }
    //             }

    //             return [
    //                 'id' => $team->id,
    //                 'name' => $team->name,
    //                 'employeeId' => $team->employeeId,
    //                 'position' => $team->position,
    //                 'division' => $team->division,
    //                 'UserId_Web' => $team->UserId_Web,
    //                 'hasSubmittedKPI' => $hasSubmittedKPI,
    //                 'submissionStatus' => $submissionStatus,
    //                 'lastUpdate' => $lastUpdate,
    //                 'No_Transaksi' => $team->No_Transaksi,
    //                 // Data detail akan di-load terpisah saat dibutuhkan
    //                 'kpis' => [],
    //                 'approvers' => []
    //             ];
    //         })->toArray();

    //         $Final_result = [
    //             'totalWithoutKPI' => $totalWithoutKPI,
    //             'totalRejected' => $totalRejected,
    //             'totalApproved' => $totalApproved,
    //             'totalPending' => $totalPending,
    //             'totalAll' => $totalAll,
    //             'data' => $results,
    //         ];

    //         return ResponseHelper::success($Final_result, "Data Ditemukan", 200);

    //     } catch (\Exception $e) {
    //         Log::channel('formKPIController')->error('ERROR GETDATACURRENTTEAM: ' . $e->getMessage());
    //         return ResponseHelper::error("Terjadi Kesalahan", 500);
    //     }
    // }

    public function getDataCurrentTeam(string $hashId)
    {
        try {
            // Decode hash & validasi
            $id = Hashids::connection('custom')->decode($hashId)[0] ?? null;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $user = Auth::user();
            if (! $id) {
                return ResponseHelper::error('ID Formula tidak valid', 400);
            }

            // Ambil jadwal aktif
            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Form_KPI', 'Y')
                ->first();
            if (! $jadwal) {
                return ResponseHelper::error('Tidak ada jadwal yang cocok', 404);
            }

            //  Ambil semua anggota tim
            $teams = DB::table('KPI_Karyawan_Team as kt')
                ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
                ->join('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.ID_Level_Jabatan',
                    '=',
                    'vg.ID_Level_Jabatan',
                )
                ->where('kt.Kode_Karyawan_Leader', $Kode_Karyawan)
                ->where('kt.Flag_Aktif', 'Y')
                ->select(
                    'kt.Id_Karyawan_Team as id',
                    'k.Nama as name',
                    'k.Kode_Karyawan as employeeId',
                    'k.UserId_Web',
                    'vg.nama_jabatan as position',
                    'vd.nama_sub_divisi as division',
                )
                ->groupBy(
                    'kt.Id_Karyawan_Team',
                    'k.Nama',
                    'k.Kode_Karyawan',
                    'k.UserId_Web',
                    'vg.nama_jabatan',
                    'vd.nama_sub_divisi',
                )
                ->get();

            if ($teams->isEmpty()) {
                return ResponseHelper::error('Tim tidak ditemukan', 404);
            }

            $userIds = $teams->pluck('UserId_Web');

            // Identitas pengajuan parameter terikat pada TAHUN jadwal, bukan pada
            // id jadwalnya. Sejak bulan mulai ditentukan per anggota, satu kali
            // simpan dari satu form tim bisa menulis Id_Penjadwalan yang berbeda
            // antar anggota: yang punya jadwal perorangan memakai jadwalnya
            // sendiri, sisanya memakai jadwal global. Mengunci ke jadwal yang
            // sedang dibuka leader membuat kelompok pertama tampil "Belum Ada
            // KPI" padahal pengajuannya hidup dan approver-nya sudah dinotifikasi.
            //
            // Tahun adalah patokan yang sama dengan yang dipakai
            // cariPengajuanParameterDigantikanMassal() saat membatalkan pengajuan lama,
            // sehingga tulis dan baca memakai satu definisi.
            $idJadwalSetahun = DB::table('KPI_Master_Penjadwalan')
                ->where('Tahun', $jadwal->Tahun)
                // HANYA jadwal PENGISIAN PARAMETER. Setahun bukan patokan yang
                // cukup: jadwal update performance juga hidup di tahun yang
                // sama, dan pengajuan yang bernaung di sana akan ikut tertarik
                // — membuat anggota terlihat "sudah mengisi" dengan 0 KPI dan
                // tanpa approver, padahal parameternya belum disentuh.
                ->where('Flag_Form_KPI', 'Y')
                ->pluck('Id_Master_Penjadwalan');

            //  Ambil semua pengajuan sekaligus
            $pengajuan = DB::table('KPI_Pengajuan')
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Status')
                ->whereIn('Id_Penjadwalan', $idJadwalSetahun)
                ->whereIn('Id_User', $userIds)
                // Menaik, supaya bila suatu saat ada lebih dari satu baris hidup
                // dalam setahun, keyBy menyisakan yang TERBARU dan bukan acak.
                ->orderBy('Id_Pengajuan')
                ->get()
                ->keyBy('Id_User');

            // Preview approver disiapkan SEKALI untuk semua anggota yang belum
            // mengajukan. Sebelumnya diselesaikan per anggota di dalam
            // perulangan, dan config approval yang sama dibaca ulang tiap kali.
            $anggotaTanpaPengajuan = $teams
                ->filter(fn ($team) => ! $pengajuan->has($team->UserId_Web))
                ->pluck('employeeId')
                ->all();

            $previewApprover = $this->getKpiParameterApproverPreviewBatch($anggotaTanpaPengajuan);

            // Detail KPI seluruh anggota yang SUDAH mengajukan, sekali jalan.
            // Dulu satu query per anggota di dalam perulangan; jumlahnya tidak
            // terasa selama tim baru sebagian mengisi, lalu tumbuh mengikuti
            // jumlah anggota begitu satu tim penuh submit.
            $userDenganPengajuan = $pengajuan->keys()->all();

            $detailPerUser = empty($userDenganPengajuan)
                ? collect()
                : DB::table('KPI_Pengajuan as a')
                    ->join('KPI_Pengajuan_Detail as b', 'a.Id_Pengajuan', '=', 'b.Id_Pengajuan')
                    ->join('KPI_Pengajuan_Det as c', 'c.Id_Pengajuan_detail', '=', 'b.Id_Pengajuan_detail')
                    ->join('KPI_Target_Breakdown as tb', 'tb.Id_Target_Breakdown', 'c.Id_Target_Breakdown')
                    ->join('KPI_Master_Target as mt', 'mt.Id_Master_Target', '=', 'b.Id_Master_Target')
                    ->join('KPI_Master_Satuan as d', 'b.Id_Master_Satuan', '=', 'd.Id_Master_Satuan')
                    ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'a.Id_Penjadwalan')
                    ->join('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'b.Id_Master_Formula')
                    ->join('KPI_Master_Bsc_Kategori as mbk', 'mbk.Id_Master_Bsc_Kategori', '=', 'b.Id_Bsc_Kategori')
                    ->select(
                        'a.Id_User',
                        'b.Id_Pengajuan_Detail',
                        'b.Nama_Parameter',
                        'mp.Tahun',
                        'b.Id_Bsc_Kategori',
                        'b.Deskripsi',
                        'b.Id_Master_Formula',
                        'b.Id_Master_Satuan',
                        'd.Simbol_Satuan as Nama_Satuan',
                        'b.Bobot',
                        'b.Id_Master_Target',
                        'b.Alasan_Tolak',
                        'c.Nilai_Target',
                        'tb.Id_Target_Breakdown',
                        'tb.Nama_Breakdown',
                        'mt.Nama_Target',
                        'd.Format_Input',
                        'mbk.Nama_Kategori',
                        'mt.Nama_Target as Nama_Target_Induk',
                        'mf.Rumus',
                    )
                    // Tahun jadwal, bukan id jadwal — alasannya sama dengan
                    // pemilihan header di atas: anggota berjadwal perorangan
                    // menyimpan ke Id_Penjadwalan miliknya sendiri.
                    ->where('mp.Tahun', $jadwal->Tahun)
                    ->where('mp.Flag_Form_KPI', 'Y')
                    ->whereIn('a.Id_User', $userDenganPengajuan)
                    ->whereNull('a.Status')
                    ->where('a.Flag_Aktif', 'Y')
                    ->orderBy('b.Id_Pengajuan_Detail')
                    ->get()
                    ->groupBy('Id_User');

            $resolusiMap = [];
            $jadwalIds = [(int) $jadwal->Id_Master_Penjadwalan];
            foreach ($teams as $team) {
                $res = $this->resolveJadwalPengisianAnggota($team->employeeId, $jadwal);
                $resolusiMap[$team->employeeId] = $res;
                if (! empty($res['id_penjadwalan'])) {
                    $jadwalIds[] = (int) $res['id_penjadwalan'];
                }
            }

            $namaJadwalMap = DB::table('KPI_Master_Penjadwalan')
                ->whereIn('Id_Master_Penjadwalan', array_unique($jadwalIds))
                ->pluck('Nama_Kegiatan', 'Id_Master_Penjadwalan');

            $results = [];

            foreach ($teams as $team) {
                $resolusi = $resolusiMap[$team->employeeId] ?? $this->resolveJadwalPengisianAnggota($team->employeeId, $jadwal);
                $isEligible = ($resolusi['tercakup'] ?? true) && ! empty($resolusi['mulai']);
                $ineligibleReason = $isEligible ? null : 'Belum terdaftar di penjadwalan aktif ini';
                $idPenjadwalanAnggota = $resolusi['id_penjadwalan'] ?? $jadwal->Id_Master_Penjadwalan;
                $namaJadwalAnggota = $namaJadwalMap[$idPenjadwalanAnggota] ?? $jadwal->Nama_Kegiatan;

                $header = $pengajuan->get($team->UserId_Web);

                if (! $header) {
                    // Belum submit → preview calon approver via engine terpusat (engine-aware),
                    // bukan query KPI_Approval_Flow_Parameter langsung. Tandai status 'pending'
                    // agar tampil "Menunggu" di SubmissionSidebar.
                    $approvals = collect($previewApprover[$team->employeeId] ?? [])
                        ->map(function ($item) {
                            $item['status'] = 'pending';

                            return $item;
                        })
                        ->values();

                    $results[] = array_merge((array) $team, [
                        'approvers' => $approvals,
                        'hasSubmittedKPI' => false,
                        'submissionStatus' => 'draft',
                        'lastUpdate' => null,
                        'kpis' => [],
                        'is_eligible' => $isEligible,
                        'ineligible_reason' => $ineligibleReason,
                        'start_periode' => $resolusi['mulai'] ?? null,
                        'sumber_jadwal' => $resolusi['sumber'] ?? null,
                        'nama_jadwal' => ($resolusi['tercakup'] ?? false) ? $namaJadwalAnggota : null,
                    ]);

                    continue;
                }

                //  Detail KPI anggota ini, dari hasil batch di atas.
                $rawData = collect($detailPerUser->get($team->UserId_Web, collect()));

                $grouped = $rawData->groupBy('Id_Pengajuan_Detail');

                $kpis = $grouped
                    ->map(function ($items) {
                        $first = $items->first();

                        return [
                            'id' => $first->Id_Pengajuan_Detail,
                            'parameter' => $first->Nama_Parameter,
                            'Alasan_Tolak' => $first->Alasan_Tolak,
                            'category' => Hashids::connection('custom')->encode($first->Id_Bsc_Kategori),
                            'description' => $first->Deskripsi,
                            'formula' => Hashids::connection('custom')->encode($first->Id_Master_Formula),
                            'unit' => Hashids::connection('custom')->encode($first->Id_Master_Satuan),
                            'Nama_Satuan' => $first->Nama_Satuan,
                            'weight' => (int) $first->Bobot,
                            'target' => Hashids::connection('custom')->encode($first->Id_Master_Target),
                            'Nama_Target' => $first->Nama_Target,
                            'Nama_Target_Induk' => $first->Nama_Target_Induk,
                            'Id_Target_Breakdown' => Hashids::connection('custom')->encode($first->Id_Target_Breakdown),
                            'Nama_Kategori' => $first->Nama_Kategori,
                            'Rumus' => $first->Rumus,
                            'Format_Input' => $first->Format_Input,
                            'targetSimpan' => $items
                                ->mapWithKeys(function ($item) {
                                    $encodedId = Hashids::connection('custom')->encode($item->Id_Target_Breakdown);

                                    return [$encodedId => $item->Nilai_Target];
                                })
                                ->toArray(),
                            'targetDataPreview' => $items
                                ->map(function ($item) {
                                    return [
                                        'month' => $item->Nama_Breakdown,
                                        'period' => $item->Tahun,
                                        'target' => (float) $item->Nilai_Target,
                                    ];
                                })
                                ->toArray(),
                            'availableUnits' => [],
                        ];
                    })
                    ->values()
                    ->toArray();

                //  Tentukan status pengajuan
                $status = match ($header->Flag_Approval) {
                    'T' => 'rejected',
                    'Y' => 'approved',
                    null => 'waiting_approval',
                    default => null,
                };

                // Ambil approval flow — dukung dual-engine: CENTRAL (HRIS_Approval_Request) vs LEGACY.
                $isCentral = app(\App\Services\KPI\Approval\KpiApprovalService::class)->isCentral($header->No_Transaksi);

                if ($isCentral) {
                    $approvals = DB::table('N_HRIS_VW_Approval_Request_V2 as a')
                        ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'a.Current_Approver')
                        ->leftJoin(
                            'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                            'k.ID_Level_Jabatan',
                            '=',
                            'vg.ID_Level_Jabatan',
                        )
                        ->where('a.No_Transaksi', $header->No_Transaksi)
                        ->orderBy('a.Request_Order_Flow')
                        ->select(
                            DB::raw('COALESCE(k.Nama, a.Role_Name, a.Current_Approver) as name'),
                            DB::raw('COALESCE(vg.nama_jabatan, a.Role_Name) as role'),
                            'a.Flag_Approval as Flag_approval',
                            'a.Request_Order_Flow as level',
                        )
                        ->get();
                } else {
                    $approvals = DB::table('KPI_Approval_Request_Parameter as a')
                        ->select('k.Nama as name', 'vg.nama_jabatan as role', 'a.Flag_approval', 'b.order_flow as level')
                        ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                        ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'b.Kode_Karyawan_Approver')
                        ->join(
                            'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                            'k.ID_Level_Jabatan',
                            '=',
                            'vg.ID_Level_Jabatan',
                        )
                        ->where('a.No_Transaksi', $header->No_Transaksi)
                        ->whereNull('b.Status')
                        ->orderBy('b.order_flow')
                        ->get();
                }

                $firstNullFound = false;
                $hasRejectedApproval = $approvals->contains(fn ($item) => $item->Flag_approval === 'T');

                $approvers = $approvals->map(function ($item) use (&$firstNullFound, $hasRejectedApproval) {
                    if ($item->Flag_approval === 'Y') {
                        $item->status = 'approved';
                    } elseif ($item->Flag_approval === 'T') {
                        $item->status = 'rejected';
                    } elseif (is_null($item->Flag_approval)) {
                        if (! $hasRejectedApproval && ! $firstNullFound) {
                            $item->status = 'inprogress';
                            $firstNullFound = true;
                        } else {
                            $item->status = 'pending';
                        }
                    }

                    return $item;
                });

                $results[] = array_merge((array) $team, [
                    'originalKpis' => $kpis,
                    'approvalData' => $header,
                    'approvers' => $approvers,
                    'hasSubmittedKPI' => true,
                    'submissionStatus' => $status,
                    'is_eligible' => $isEligible,
                    'ineligible_reason' => $ineligibleReason,
                    'start_periode' => $resolusi['mulai'] ?? null,
                    'sumber_jadwal' => $resolusi['sumber'] ?? null,
                    'nama_jadwal' => ($resolusi['tercakup'] ?? false) ? $namaJadwalAnggota : null,
                    // Alasan penolakan GLOBAL (komentar umum approver, di luar
                    // komentar per parameter). Bentuk & aturannya sama dengan
                    // form individu: hanya relevan saat status 'rejected'.
                    'alasan_reject_global' => $status === 'rejected'
                        ? ($header->Alasan_Reject ?? null)
                        : null,
                    'lastUpdate' => Carbon::parse(
                        Carbon::parse($header->Tanggal)->format('Y-m-d').' '.$header->Jam,
                    )->format('Y-m-d H:i:s'),
                    'kpis' => $kpis,
                ]);
            }

            // dd($results);
            return ResponseHelper::success($results, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('formKPIController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getMemberKpiDetail(Request $request, string $memberId)
    {
        try {
            $scheduleHashId = $request->query('schedule_id');

            // Decode schedule
            $scheduleId = Hashids::connection('custom')->decode($scheduleHashId)[0] ?? null;
            if (! $scheduleId) {
                return ResponseHelper::error('ID Jadwal tidak valid', 400);
            }

            // Ambil data member
            $member = DB::table('KPI_Karyawan_Team as kt')
                ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'kt.Kode_Karyawan_Team')
                ->where('kt.Id_Karyawan_Team', $memberId)
                ->where('kt.Flag_Aktif', 'Y')
                ->select('k.UserId_Web', 'k.Kode_Karyawan')
                ->first();

            if (! $member) {
                return ResponseHelper::error('Member tidak ditemukan', 404);
            }

            // Tahun jadwal, bukan id jadwal — alasan lengkapnya di
            // getDataCurrentTeam(). Detail per anggota harus memakai patokan yang
            // sama dengan grid, kalau tidak barisnya terlihat terisi di grid tapi
            // kosong saat dibuka.
            $tahunJadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $scheduleId)
                ->value('Tahun');

            $idJadwalSetahun = DB::table('KPI_Master_Penjadwalan')
                ->where('Tahun', $tahunJadwal)
                // Lihat catatan di getDataCurrentTeam(): jadwal update
                // performance hidup di tahun yang sama dan tidak boleh ikut.
                ->where('Flag_Form_KPI', 'Y')
                ->pluck('Id_Master_Penjadwalan');

            // Ambil header pengajuan
            $header = DB::table('KPI_Pengajuan')
                ->where('Id_User', $member->UserId_Web)
                ->whereIn('Id_Penjadwalan', $idJadwalSetahun)
                ->whereNull('Status')
                ->where('Flag_Aktif', 'Y')
                ->orderByDesc('Id_Pengajuan')
                ->first();

            if (! $header) {
                return ResponseHelper::success(
                    [
                        'kpis' => [],
                        'approvers' => [],
                        'hasSubmittedKPI' => false,
                    ],
                    'Tidak ada KPI yang diajukan',
                    200,
                );
            }

            // Ambil detail KPI
            $rawData = DB::table('KPI_Pengajuan as a')
                ->join('KPI_Pengajuan_Detail as b', 'a.Id_Pengajuan', '=', 'b.Id_Pengajuan')
                ->join('KPI_Pengajuan_Det as c', 'c.Id_Pengajuan_detail', '=', 'b.Id_Pengajuan_detail')
                ->join('KPI_Target_Breakdown as tb', 'tb.Id_Master_Target', '=', 'b.Id_Master_Target')
                ->join('KPI_Master_Satuan as d', 'b.Id_Master_Satuan', '=', 'd.Id_Master_Satuan')
                ->join('KPI_Master_Target as mt', 'mt.Id_Master_Target', '=', 'b.Id_Master_Target')
                ->join('KPI_Master_Formula as mf', 'mf.Id_Master_Formula', '=', 'b.Id_Master_Formula')
                ->join('KPI_Master_Bsc_Kategori as mbk', 'mbk.Id_Master_Bsc_Kategori', '=', 'b.Id_Bsc_Kategori')
                ->select(
                    'b.Id_Pengajuan_Detail',
                    'b.Nama_Parameter',
                    'b.Id_Bsc_Kategori',
                    'b.Deskripsi',
                    'b.Id_Master_Formula',
                    'b.Id_Master_Satuan',
                    'd.Simbol_Satuan as Nama_Satuan',
                    'b.Bobot',
                    'b.Id_Master_Target',
                    'b.Alasan_Tolak',
                    'c.Nama_Target',
                    'c.Nilai_Target',
                    'tb.Id_Target_Breakdown',
                    'mbk.Nama_Kategori',
                    'mt.Nama_Target as Nama_Target_Induk',
                    'mf.Rumus',
                )
                ->whereIn('a.Id_Penjadwalan', $idJadwalSetahun)
                ->where('a.Id_User', $member->UserId_Web)
                ->whereNull('a.Status')
                ->where('a.Flag_Aktif', 'Y')
                ->orderBy('b.Id_Pengajuan_Detail')
                ->get();

            $grouped = $rawData->groupBy('Id_Pengajuan_Detail');

            $kpis = $grouped
                ->map(function ($items) {
                    $first = $items->first();

                    return [
                        'id' => $first->Id_Pengajuan_Detail,
                        'parameter' => $first->Nama_Parameter,
                        'Alasan_Tolak' => $first->Alasan_Tolak,
                        'category' => Hashids::connection('custom')->encode($first->Id_Bsc_Kategori),
                        'description' => $first->Deskripsi,
                        'formula' => Hashids::connection('custom')->encode($first->Id_Master_Formula),
                        'unit' => Hashids::connection('custom')->encode($first->Id_Master_Satuan),
                        'Nama_Satuan' => $first->Nama_Satuan,
                        'weight' => (int) $first->Bobot,
                        'target' => Hashids::connection('custom')->encode($first->Id_Master_Target),
                        'Nama_Target' => $first->Nama_Target,
                        'Nama_Target_Induk' => $first->Nama_Target_Induk,
                        'Id_Target_Breakdown' => Hashids::connection('custom')->encode($first->Id_Target_Breakdown),
                        'Nama_Kategori' => $first->Nama_Kategori,
                        'Rumus' => $first->Rumus,
                        'targetSimpan' => $items
                            ->mapWithKeys(function ($item) {
                                $encodedId = Hashids::connection('custom')->encode($item->Id_Target_Breakdown);

                                return [$encodedId => $item->Nilai_Target];
                            })
                            ->toArray(),
                        'availableUnits' => [],
                    ];
                })
                ->values()
                ->toArray();

            // Ambil approval flow — dual-engine, disamakan dengan getDataCurrentTeam().
            //
            // Sebelumnya endpoint ini HANYA membaca tabel legacy, sehingga pada
            // engine CENTRAL modal detail anggota selalu tampil tanpa approver
            // padahal grid di halaman yang sama menampilkannya. Deteksinya per
            // transaksi, bukan per config global, supaya pengajuan lama yang
            // dibuat sebelum migrasi tetap terbaca.
            $isCentral = app(\App\Services\KPI\Approval\KpiApprovalService::class)->isCentral($header->No_Transaksi);

            if ($isCentral) {
                $approvals = DB::table('N_HRIS_VW_Approval_Request_V2 as a')
                    ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'a.Current_Approver')
                    ->leftJoin(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                        'k.ID_Level_Jabatan',
                        '=',
                        'vg.ID_Level_Jabatan',
                    )
                    ->where('a.No_Transaksi', $header->No_Transaksi)
                    ->orderBy('a.Request_Order_Flow')
                    ->select(
                        DB::raw('COALESCE(k.Nama, a.Role_Name, a.Current_Approver) as name'),
                        DB::raw('COALESCE(vg.nama_jabatan, a.Role_Name) as role'),
                        'a.Flag_Approval as Flag_approval',
                        'a.Request_Order_Flow as level',
                    )
                    ->get();
            } else {
                $approvals = DB::table('KPI_Approval_Request_Parameter as a')
                    ->select('k.Nama as name', 'vg.nama_jabatan as role', 'a.Flag_approval', 'b.order_flow as level')
                    ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                    ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'b.Kode_Karyawan_Approver')
                    ->join(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                        'k.ID_Level_Jabatan',
                        '=',
                        'vg.ID_Level_Jabatan',
                    )
                    ->where('a.No_Transaksi', $header->No_Transaksi)
                    ->orderBy('b.order_flow')
                    ->get();
            }

            $firstNullFound = false;
            $approvers = $approvals->map(function ($item) use (&$firstNullFound) {
                if ($item->Flag_approval === 'Y') {
                    $item->status = 'approved';
                } elseif ($item->Flag_approval === 'T') {
                    $item->status = 'rejected';
                } elseif (is_null($item->Flag_approval)) {
                    $item->status = 'waiting';
                    $firstNullFound = true;
                }

                return $item;
            });

            // Fix datetime parsing untuk lastUpdate
            $lastUpdate = null;
            if ($header->Tanggal) {
                $lastUpdate = Carbon::parse($header->Tanggal)->format('Y-m-d');

                if ($header->Jam) {
                    try {
                        $lastUpdate = Carbon::parse($header->Tanggal.' '.$header->Jam)->format('Y-m-d H:i:s');
                    } catch (\Exception $e) {
                        // Fallback ke tanggal saja jika parsing gagal
                        $lastUpdate = Carbon::parse($header->Tanggal)->format('Y-m-d');
                    }
                }
            }

            return ResponseHelper::success(
                [
                    'kpis' => $kpis,
                    'approvers' => $approvers,
                    'hasSubmittedKPI' => true,
                    'submissionStatus' => match ($header->Flag_Approval) {
                        'T' => 'rejected',
                        'Y' => 'approved',
                        null => 'waiting_approval',
                        default => 'draft',
                    },
                    'lastUpdate' => $lastUpdate,
                ],
                'Detail KPI berhasil diambil',
                200,
            );
        } catch (\Exception $e) {
            Log::channel('formKPIController')->error('ERROR GETMEMBERKPIDETAIL: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getFormulaById(string $hashId)
    {
        try {
            $id = Hashids::connection('custom')->decode($hashId)[0] ?? null;
            if (empty($id)) {
                return ResponseHelper::error('ID Formula tidak valid', 400);
            }

            $rawData = DB::table('KPI_Master_Satuan_Formula as a')
                ->join('KPI_Master_Satuan as b', 'a.Id_Master_Satuan', '=', 'b.Id_Master_Satuan')
                ->select('b.*')
                ->where('b.Flag_Aktif', 'Y')
                ->where('a.Id_Master_Formula', $id)
                ->get();
            // dd($rawData);

            if ($rawData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            $encodedData = $rawData->map(function ($item) {
                if ($item->Id_Master_Satuan) {
                    $item->Id_Master_Satuan = Hashids::connection('custom')->encode($item->Id_Master_Satuan);
                }

                return $item;
            });

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('formKPIController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    // public function getDataCurrent(Request $request)
    // {
    //     // dd($request->all());

    //     try {
    //         $id_Penjadwalan = $request->query('Id_Penjadwalan');

    //         $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

    //         if (!empty($id_Penjadwalan)) {
    //             $id_Penjadwalan = Hashids::connection('custom')->decode($id_Penjadwalan)[0] ?? null;
    //         }

    //         if (empty($id_Penjadwalan)) {
    //             return ResponseHelper::error("ID Penjadwalan tidak valid", 400);
    //         }

    //         $cekPerorangan = DB::table('KPI_Master_Penjadwalan')
    //                         ->where('Id_Master_Penjadwalan', $id_Penjadwalan)
    //                         ->first();

    //         $startPeriode = 01;

    //         if($cekPerorangan->Flag_Perorangan == 'Y'){
    //             $startPeriode = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
    //                             ->where('Id_Master_Penjadwalan', $id_Penjadwalan)
    //                             ->where('Kode_Karyawan', $Kode_Karyawan)
    //                             ->select('Start_Periode')
    //                             ->first();
    //             if(!$startPeriode){
    //                 return ResponseHelper::error("Data Periode Tidak Ditemukan", 404);
    //             }
    //             $startPeriode = explode('-',$startPeriode->Start_Periode)[1];
    //         }
    //         $startPeriode = str_pad($startPeriode, 2, '0', STR_PAD_LEFT);

    //         // Get Formula Data
    //         $rawDataFormula = DB::table('KPI_Master_Formula')
    //             ->select("Id_Master_Formula", 'Nama_Formula', "Deskripsi", 'Rumus')
    //             ->where("Flag_Aktif", 'Y')
    //             ->get();

    //         // Get BSC Kategori
    //         $rawDataBSCKategori = DB::table('KPI_Master_Bsc_Kategori')
    //             ->select("Id_Master_Bsc_Kategori", 'Nama_Kategori', "Keterangan")
    //             ->where("Flag_Aktif", 'Y')
    //             ->get();

    //         // Get Target
    //         // $rawDataTarget = $this->getListTarget($startPeriode);

    //           $rawDataTarget = DB::table('KPI_Master_Target')
    //             ->select("Id_Master_Target", 'Nama_Target', "Deskripsi", 'Kode_Target')
    //             ->get();
    //             // dd($rawDataTarget);

    //         $dataFormula = $rawDataFormula->map(function ($item) {

    //             if($item->Rumus){

    //                 $rumus = preg_replace('/\[(.*?)\]/', '$1', $item->Rumus);

    //                 $mapping = [
    //                     'targetKpiEvo' => 'Target',
    //                     'actualKpiEvo' => 'Actual',
    //                 ];

    //                 $rumus = str_replace(array_keys($mapping), array_values($mapping), $rumus);

    //                 $item->Rumus = $rumus;
    //             }

    //             if ($item->Id_Master_Formula) {
    //                 $item->Id_Master_Formula = Hashids::connection('custom')->encode($item->Id_Master_Formula);
    //             }
    //             return $item;
    //         });

    //         $dataBSCKategori = $rawDataBSCKategori->map(function ($item) {
    //             if ($item->Id_Master_Bsc_Kategori) {
    //                 $item->Id_Master_Bsc_Kategori = Hashids::connection('custom')->encode($item->Id_Master_Bsc_Kategori);
    //             }
    //             return $item;
    //         });

    //         $dataTarget = $rawDataTarget->map(function ($item) {
    //             // dd($item);
    //             if ($item['Id_Master_Target']) {
    //                 $item['Id_Master_Target'] = Hashids::connection('custom')->encode($item['Id_Master_Target']);
    //             }
    //             return $item;
    //         });

    //         // dd($dataTarget);

    //         $result = [
    //             "dataTarget" => $dataTarget,
    //             "dataBSCKategori" => $dataBSCKategori,
    //             "dataFormula" => $dataFormula,
    //         ];

    //         return ResponseHelper::success($result, "Data Ditemukan", 200);
    //     } catch (\Exception $e) {
    //         Log::channel('formKPIController')->error('ERROR GETDATACURRENT: '. $e->getMessage());
    //         return ResponseHelper::error("Terjadi Kesalahan", 500);
    //     }
    // }

    public function getDataCurrent(Request $request)
    {
        try {
            // dd($request->all());
            $id_Penjadwalan = $request->query('Id_Penjadwalan');

            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;

            if (! empty($id_Penjadwalan)) {
                $id_Penjadwalan = Hashids::connection('custom')->decode($id_Penjadwalan)[0] ?? null;
            }

            // dd($id_Penjadwalan);

            if (empty($id_Penjadwalan)) {
                return ResponseHelper::error('ID Penjadwalan tidak valid', 400);
            }

            $cekPerorangan = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id_Penjadwalan)
                ->first();

            // Endpoint ini dipakai form individu MAUPUN form tim (FormKPITeam.vue
            // memanggilnya untuk mengisi daftar target/formula/kategori). Karena itu
            // ia menerima daftar kode_karyawan opsional: form tim mengirim seluruh
            // anggota yang dicentang, lalu dipakai bulan mulai PALING AWAL di antara
            // mereka.
            //
            // Kalau tidak, bulan mulai leader dipakai untuk semua, dan target yang
            // seluruh periodenya lebih awal dari itu ditandai tidak aktif — padahal
            // anggota yang mulai lebih awal berhak memakainya.
            //
            // Tanpa parameter ini perilaku lama dipertahankan utuh (form individu).
            $kodeAnggota = array_filter((array) $request->input('kode_karyawan', []));

            if (! empty($kodeAnggota)) {
                $mulai = collect($kodeAnggota)
                    ->map(function ($kode) use ($cekPerorangan) {
                        return $this->resolveJadwalPengisianAnggota($kode, $cekPerorangan)['mulai'];
                    })
                    // Anggota yang TIDAK tercakup payung ini memulangkan null dan
                    // harus dibuang, bukan diikutkan: null mengurutkan paling awal
                    // sehingga satu orang di luar roster akan menarik acuan seluruh
                    // daftar. Dulu ia memulangkan Januari dan menariknya ke bulan 1 —
                    // gejala yang sama dengan bentuk berbeda.
                    ->filter()
                    ->sort()
                    ->first();

                $startPeriode = substr($mulai, 5, 2);
            } else {
                // Default: ambil bulan start dari Periode penjadwalan (format "YYYY-MM").
                $periodeJadwal = $cekPerorangan->Periode ?? null;
                $startPeriode = $periodeJadwal
                    ? (str_contains($periodeJadwal, '-') ? explode('-', $periodeJadwal)[1] : $periodeJadwal)
                    : 1; // fallback aman bila Periode kosong → semua periode aktif

                if ($cekPerorangan->Flag_Perorangan == 'Y') {
                    $startPeriode = $this->bulanMulaiPerorangan($id_Penjadwalan, $Kode_Karyawan);
                }

                $startPeriode = str_pad($startPeriode, 2, '0', STR_PAD_LEFT);
            }
            // Get Formula Data
            $rawDataFormula = DB::table('KPI_Master_Formula')
                ->select('Id_Master_Formula', 'Nama_Formula', 'Deskripsi', 'Rumus', 'Min_Range', 'Max_Range')
                ->where('Flag_Aktif', 'Y')
                ->get();

            // Get BSC Kategori
            $rawDataBSCKategori = DB::table('KPI_Master_Bsc_Kategori')
                ->select('Id_Master_Bsc_Kategori', 'Nama_Kategori', 'Keterangan')
                ->where('Flag_Aktif', 'Y')
                ->get();

            // Get Target
            // $rawDataTarget = DB::table('KPI_Master_Target')
            //     ->select("Id_Master_Target", 'Nama_Target', "Deskripsi", 'Kode_Target')
            //     ->get();

            $rawDataTarget = $this->getListTarget($startPeriode);
            // dd($rawDataTarget);

            $dataFormula = $rawDataFormula->map(function ($item) {
                // Simpan raw formula untuk deteksi Has_Range sebelum transform
                $rawUpper = strtoupper($item->Rumus ?? '');
                $item->Has_Range = (
                    str_contains($rawUpper, 'MIN(') ||
                    str_contains($rawUpper, 'MAX(') ||
                    str_contains($rawUpper, 'IF(')
                );
                $item->Min_Range = isset($item->Min_Range) ? (float) $item->Min_Range : null;
                $item->Max_Range = isset($item->Max_Range) ? (float) $item->Max_Range : null;

                if ($item->Rumus) {
                    $rumus = preg_replace('/\[(.*?)\]/', '$1', $item->Rumus);
                    $mapping = [
                        'targetKpiEvo' => 'Target',
                        'actualKpiEvo' => 'Actual',
                    ];
                    $rumus = str_replace(array_keys($mapping), array_values($mapping), $rumus);
                    $item->Rumus = $rumus;
                }

                if ($item->Id_Master_Formula) {
                    $item->Id_Master_Formula = Hashids::connection('custom')->encode($item->Id_Master_Formula);
                }

                return $item;
            });

            $dataBSCKategori = $rawDataBSCKategori->map(function ($item) {
                if ($item->Id_Master_Bsc_Kategori) {
                    $item->Id_Master_Bsc_Kategori = Hashids::connection('custom')->encode(
                        $item->Id_Master_Bsc_Kategori,
                    );
                }

                return $item;
            });

            $dataTarget = $rawDataTarget->map(function ($item) {
                if ($item->Id_Master_Target) {
                    $item->Id_Master_Target = Hashids::connection('custom')->encode($item->Id_Master_Target);
                }

                return $item;
            });

            $result = [
                'dataTarget' => $dataTarget,
                'dataBSCKategori' => $dataBSCKategori,
                'dataFormula' => $dataFormula,
            ];

            return ResponseHelper::success($result, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('formKPIController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function getTargetBreakdown(Request $request)
    {
        // dd($request->all());
        $validate = $request->validate([
            'Id_Master_Target' => 'required|string',
            'Id_Penjadwalan' => 'required|string',
        ]);

        try {
            $id = Hashids::connection('custom')->decode($validate['Id_Master_Target'])[0] ?? null;
            $id_Penjadwalan = Hashids::connection('custom')->decode($validate['Id_Penjadwalan'])[0] ?? null;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            if (empty($id)) {
                return ResponseHelper::error('ID Target tidak valid', 400);
            }
            // dd($id, $id_Penjadwalan);
            if (empty($id_Penjadwalan)) {
                return ResponseHelper::error('ID Penjadwalan tidak valid', 400);
            }

            $cekPerorangan = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $id_Penjadwalan)
                ->first();

            // Jalur tim: bulan mulai milik ANGGOTA, bukan milik leader yang sedang
            // login. Dikirim sebagai daftar supaya satu pemanggilan bisa melayani
            // dua keadaan: tab satu anggota (kirim satu NIK → tepat untuk dia), dan
            // template bersama (kirim semua yang dicentang → dipakai yang PALING
            // AWAL, agar tidak ada bulan yang mestinya bisa diisi malah tak pernah
            // dirender). Tanpa parameter ini, perilaku lama dipertahankan utuh.
            $kodeAnggota = array_filter((array) $request->input('kode_karyawan', []));

            if (! empty($kodeAnggota)) {
                $mulai = collect($kodeAnggota)
                    ->map(function ($kode) use ($cekPerorangan) {
                        return $this->resolveJadwalPengisianAnggota($kode, $cekPerorangan)['mulai'];
                    })
                    // Anggota yang TIDAK tercakup payung ini memulangkan null dan
                    // harus dibuang, bukan diikutkan: null mengurutkan paling awal
                    // sehingga satu orang di luar roster akan menarik acuan seluruh
                    // daftar. Dulu ia memulangkan Januari dan menariknya ke bulan 1 —
                    // gejala yang sama dengan bentuk berbeda.
                    ->filter()
                    ->sort()
                    ->first();

                $bln = substr($mulai, 5, 2);
            } else {
                // Default (team): ambil bulan start dari Periode penjadwalan (format "YYYY-MM").
                $periodeJadwal = $cekPerorangan->Periode ?? null;
                $startPeriode = $periodeJadwal
                    ? (str_contains($periodeJadwal, '-') ? explode('-', $periodeJadwal)[1] : $periodeJadwal)
                    : 1; // fallback aman bila Periode kosong → semua periode aktif

                if ($cekPerorangan->Flag_Perorangan == 'Y') {
                    $startPeriode = $this->bulanMulaiPerorangan($id_Penjadwalan, $Kode_Karyawan);
                }

                $bln = str_pad($startPeriode, 2, '0', STR_PAD_LEFT);
            }
            // dd($bln);
            $rawData = DB::table('KPI_Master_Target as a')
                ->join('KPI_Target_Breakdown as b', 'a.Id_Master_Target', '=', 'b.Id_Master_Target')
                ->select('b.*')
                ->where('a.Flag_Aktif', 'Y')
                ->where('a.Id_Master_Target', $id)
                ->get();
            // dd($rawData);

            if ($rawData->isEmpty()) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            // $encodedData = $rawData->map(function($item) use($bln) {
            //     if($item->Id_Master_Target){
            //         $item->Id_Master_Target  = Hashids::connection('custom')->encode($item->Id_Master_Target);
            //     }
            //     if($item->Id_Target_Breakdown){
            //         $item->Id_Target_Breakdown  = Hashids::connection('custom')->encode($item->Id_Target_Breakdown);
            //     }

            //     if(!empty($bln)){
            //         $item->isActive = $item->Periode >= $bln ? true : false;
            //     }else{
            //         $item->isActive = true;
            //     }
            //     return $item;
            // });

            $encodedData = collect($rawData)
                ->sortBy('Periode')
                ->values()
                ->map(function ($item, $index) use ($bln) {
                    // hitung START periode
                    // if ($index === 0) {
                    //     $startRange = 1;
                    // } else {
                    //     $startRange = $rawData[$index - 1]->Periode + 1;
                    // }

                    // ACTIVE hanya kalau BELUM DIMULAI
                    // $item->isActive = $startRange >= (int) $bln;
                    $item->isActive = $item->Periode >= (int) $bln;

                    // encode id
                    if ($item->Id_Master_Target) {
                        $item->Id_Master_Target = Hashids::connection('custom')->encode($item->Id_Master_Target);
                    }
                    if ($item->Id_Target_Breakdown) {
                        $item->Id_Target_Breakdown = Hashids::connection('custom')->encode($item->Id_Target_Breakdown);
                    }

                    // optional: reason singkat
                    $item->reason = $item->isActive ? 'Tersedia' : 'Tidak tersedia (periode sudah berjalan)';

                    return $item;
                });

            // dd($encodedData);

            return ResponseHelper::success($encodedData, 'Data Ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('formKPIController')->error('ERROR GETDATACURRENT: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    public function store(Request $request)
    {
        // dd($request->all());
        $validator = $request->validate([
            'kpis' => 'required|array|min:1',
            'kpis.*.Id_Penjadwalan' => 'required|string',
            'kpis.*.parameter' => 'required|string',
            'kpis.*.bscCategory' => 'required|string',
            'kpis.*.definition' => 'nullable|string',
            'kpis.*.formula' => 'required|string',
            'kpis.*.unit' => 'required|string',
            'kpis.*.weight' => 'required|numeric',
            'kpis.*.target' => 'required|string',
            'kpis.*.targetSimpan' => 'required|array|min:1',
            'no_transaksi' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $kpis = $request->kpis;

            $no_transaksi_lama = $request->no_transaksi ?? null;

            $id_pengguna = Auth::user()->Id_Users;
            $Kode_Karyawan = Auth::user()->karyawan->Kode_Karyawan;
            $paramEngine = strtoupper((string) config('services.kpi.param_engine', 'CENTRAL'));
            if ($paramEngine !== 'LEGACY') {
                $approverAll = $this->getKpiParameterApproverPreview($Kode_Karyawan)->isNotEmpty();
            } else {
                $approverAll = DB::table('KPI_Approval_Flow_Parameter as a')
                    ->join('Karyawan as b', 'a.Kode_Karyawan_Approver', '=', 'b.Kode_Karyawan')
                    ->whereNull('a.Status')
                    ->where('b.Aktif', 'Y')
                    ->whereNull('b.Tanggal_resign')
                    ->where('a.Kode_Karyawan_Requester', $Kode_Karyawan)
                    ->exists();
            }

            if (! $approverAll) {
                Log::channel('formKPIController')->error(
                    'Tidak ada approver sama sekali! Karyawan : '.$Kode_Karyawan,
                );

                DB::rollBack();

                return ResponseHelper::error('Belum memiliki Approval', 404);
            }

            $decoded = Hashids::connection('custom')->decode($kpis[0]['Id_Penjadwalan'] ?? '');
            $idPenjadwalan = $decoded[0] ?? null;

            if (! $idPenjadwalan) {
                Log::channel('formKPIController')->error('Id_Penjadwalan tidak valid! Karyawan : '.$Kode_Karyawan);

                DB::rollBack();

                return ResponseHelper::error('Belum memiliki Approval', 404);
            }

            $ambilJadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $idPenjadwalan)
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Form_KPI', 'Y')
                ->first();
            // Harus memeriksa hasil query, bukan $idPenjadwalan yang sudah divalidasi di
            // atas. Bila jadwal tak ditemukan dan lolos ke bawah, $ambilJadwal->Tahun
            // bernilai null sehingga Periode tersimpan "-01" dan tak cocok dengan periode
            // mana pun di Update Performance maupun dashboard.
            if (! $ambilJadwal) {
                DB::rollBack();
                Log::channel('formKPIController')->error(
                    'Tidak ada jadwal yang sesuai dengan Id_Penjadwalan! Karyawan : '.$Kode_Karyawan,
                );

                return ResponseHelper::error('Tidak ada jadwal yang cocok', 404);
            }

            $checkKPIJadwal = DB::table('KPI_Pengajuan')
                // ->where('Id_Penjadwalan', $idPenjadwalan)
                ->whereNull('Status')
                ->where('Id_User', $id_pengguna)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if ($checkKPIJadwal) {
                $pembatalanTransaksi = DB::table('KPI_Pengajuan')
                    // ->where('Id_Penjadwalan', $idPenjadwalan)
                    ->whereNull('Status')
                    ->where('Flag_Aktif', 'Y')
                    ->where('No_Transaksi', $checkKPIJadwal->No_Transaksi)
                    ->update([
                        'Flag_Aktif' => 'T',
                        'Status' => 'Y',
                    ]);
                if (! $pembatalanTransaksi) {
                    Log::channel('formKPIController')->error(
                        'Terjadi Kesalahan Pembatalan Transaksi, Karyawan : '.$Kode_Karyawan,
                    );
                    DB::rollBack();

                    return ResponseHelper::error('Terjadi Kesalahan Pembatalan Transaksi, Silahkan Coba lagi', 404);
                }

                // Langkah approval milik pengajuan lama WAJIB ikut ditutup. Tanpa ini
                // barisnya tetap menggantung di inbox approver sehingga versi lama
                // masih bisa disetujui (pola sama dengan KpiCancellationService).
                DB::table('HRIS_Approval_Request')
                    ->where('No_Transaksi', $checkKPIJadwal->No_Transaksi)
                    ->whereNull('Flag_Approval')
                    ->update(['Status' => 'Y']);

                Log::channel('formKPIController')->info('Melakukan Pengajuan Ulang, Karyawan : '.$Kode_Karyawan);
            }

            $formatTanggalSekarang = Carbon::now()->format('m-y');
            $prefix = 'PKPI'.$formatTanggalSekarang.'-';

            // no Transaksi via COUNTER ATOMIK (bebas race duplikat + tanpa scan).
            $no_faktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
                $prefix,
                fn () => DB::table('KPI_Pengajuan')
                    ->where('No_Transaksi', 'like', $prefix.'%')
                    ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
            );

            // $no_faktur_ref = $checkKPIJadwal && $checkKPIJadwal->No_Transaksi ? $checkKPIJadwal->No_Transaksi : null;

            $pengajuanId = DB::table('KPI_Pengajuan')->insertGetId([
                'No_Transaksi' => $no_faktur,
                'Id_Penjadwalan' => $idPenjadwalan,
                'No_Transaksi_Ref' => $no_transaksi_lama,
                // 'Status_Pengajuan' => 'Menunggu',
                'Flag_Aktif' => 'Y',
                'Id_User' => $id_pengguna,
                'Jam' => now()->format('H:i:s'),
                'Tanggal' => now()->format('Y-m-d'),
            ]);

            /*
             * Breakdown target diambil SEKALI. Sebelumnya satu kueri per KPI, di
             * dalam transaksi pengajuan -- jumlahnya tumbuh bersama banyaknya
             * parameter yang diajukan, dan selama itu kuncinya ditahan.
             *
             * KPI_Target_Breakdown hanya DIBACA di metode ini, jadi petanya tidak
             * bisa basi oleh perulangannya sendiri. Penguraian id di sini sengaja
             * longgar: validasinya tetap di tempat semula, di dalam perulangan,
             * supaya urutan dan pesan galatnya tidak berubah.
             */
            $idTargetSemua = [];
            foreach ($kpis as $kpiPra) {
                $idT = Hashids::connection('custom')->decode($kpiPra['target'])[0] ?? null;
                if ($idT) {
                    $idTargetSemua[$idT] = true;
                }
            }

            $breakdownPerTarget = DB::table('KPI_Target_Breakdown')
                ->whereIn('Id_Master_Target', array_keys($idTargetSemua))
                ->get()
                ->groupBy('Id_Master_Target');

            foreach ($kpis as $kpi) {
                $idBscKategori = Hashids::connection('custom')->decode($kpi['bscCategory'])[0] ?? null;
                $idFormula = Hashids::connection('custom')->decode($kpi['formula'])[0] ?? null;
                $idUnit = Hashids::connection('custom')->decode($kpi['unit'])[0] ?? null;
                $idTargetM = Hashids::connection('custom')->decode($kpi['target'])[0] ?? null;

                if (! $idBscKategori || ! $idFormula || ! $idUnit || ! $idTargetM) {
                    throw new \Exception('Data ID tidak valid');
                }

                $detailId = DB::table('KPI_Pengajuan_Detail')->insertGetId([
                    'Id_Pengajuan' => $pengajuanId,
                    'Nama_Parameter' => $kpi['parameter'],
                    'Id_Bsc_Kategori' => $idBscKategori,
                    'Deskripsi' => $kpi['definition'],
                    'Kode_Karyawan' => $Kode_Karyawan,
                    'Id_Master_Formula' => $idFormula,
                    'Id_Master_Satuan' => $idUnit,
                    'Bobot' => $kpi['weight'],
                    'Id_Master_Target' => $idTargetM,
                ]);

                $targetDatas = $breakdownPerTarget[$idTargetM] ?? collect();

                $targetData = [];
                foreach ($kpi['targetSimpan'] as $id_targetB => $nilai) {
                    // Periode pra-start dikirim null/'' dari frontend → tidak di-insert.
                    if ($nilai === null || $nilai === '') {
                        continue;
                    }
                    $decodeTargetBreak = Hashids::connection('custom')->decode($id_targetB)[0] ?? null;
                    if (! $decodeTargetBreak) {
                        Log::channel('formKPIController')->error(
                            'Target dipilih berbeda dengan yang diisi! Karyawan : '.$Kode_Karyawan,
                        );

                        DB::rollBack();

                        return ResponseHelper::error('Target dipilih berbeda dengan yang diisi', 404);
                    }
                    $targetPicked = $targetDatas->firstWhere('Id_Target_Breakdown', $decodeTargetBreak);
                    $valuePeriode = $ambilJadwal->Tahun.'-'.$targetPicked->Periode;
                    $targetData[] = [
                        'Id_Pengajuan_Detail' => $detailId,
                        'Nama_Target' => $targetPicked->Nama_Breakdown,
                        'Nilai_Target' => $nilai,
                        'Periode' => $valuePeriode,
                        'Id_Target_Breakdown' => $targetPicked->Id_Target_Breakdown,
                    ];
                }

                // dd($kpi['targetSimpan'], $targetData );

                $pegajuanDet = DB::table('KPI_Pengajuan_Det')->insert($targetData);
            }

            if (! $pengajuanId || ! $detailId || ! $pegajuanDet) {
                Log::channel('formKPIController')->error(
                    'Gagal dalam menyimpan transaksi!, Karyawan : '.$Kode_Karyawan,
                );
                DB::rollBack();

                return ResponseHelper::error('Gagal dalam menyimpan transaksi!, Silahkan Coba lagi', 404);
            }

            // ENGINE ROUTER: CENTRAL (HRIS_Approval_Request) atau LEGACY.
            // Default CENTRAL via KpiApprovalService::createParamFlow. Rollback: env KPI_PARAM_ENGINE=LEGACY.
            // Catatan: $Kode_Karyawan = kode requester (untuk tim, requester = anggota yang dinilai).
            if ($paramEngine !== 'LEGACY') {
                $flowResult = app(\App\Services\KPI\Approval\KpiApprovalService::class)
                    ->createParamFlow($no_faktur, $Kode_Karyawan);

                if (! $flowResult->success) {
                    Log::channel('formKPIController')->error(
                        'Gagal membuat flow approval (CENTRAL) parameter: '.($flowResult->error ?? '-').
                            ' | Karyawan: '.$Kode_Karyawan,
                    );

                    return ResponseHelper::error($flowResult->error ?: 'Gagal membuat flow approval.', 404);
                }

                // Notifikasi approver pertama (template KPI) bila ada approver tunggal.
                $this->notifyKpiParamFirstApproverCentral($no_faktur, $Kode_Karyawan);

                // Bila step pertama langsung verifikator ROLE, kirim notif in-app ke
                // role pool. No-op bila current step bukan role.
                app(\App\Services\KPI\Notification\KpiNotificationService::class)->notifyRolePoolForCurrentStep($no_faktur);

                DB::commit();

                return ResponseHelper::success(null, 'Data berhasil disimpan', 200);
            }

            $flow = DB::table('KPI_Approval_Flow_Parameter')
                ->whereNull('Status')
                ->where('Kode_Karyawan_Requester', $Kode_Karyawan)
                ->get()
                ->map(function ($item) use ($Kode_Karyawan, $no_faktur) {
                    return [
                        'Kode_Karyawan_Requester' => $Kode_Karyawan,
                        'Flow_Id' => $item->Id_Approval_Flow,
                        'No_Transaksi' => $no_faktur,
                    ];
                });

            $inserFlowtoRequest = DB::table('KPI_Approval_Request_Parameter')->insert($flow->toArray());

            if ($inserFlowtoRequest) {
                $user = DB::table('KPI_Approval_Flow_Parameter as a')
                    ->select('b.HP', 'b.Nama', 'b.Kode_Karyawan')
                    ->join('Karyawan as b', 'a.Kode_Karyawan_Approver', '=', 'b.Kode_Karyawan')
                    ->where('a.Kode_Karyawan_Requester', $Kode_Karyawan)
                    ->whereNull('a.Status')
                    ->orderBy('a.order_flow')
                    ->first();

                if (! $user || ! $user->HP || $user->HP == '' || ! $user->Nama) {
                    Log::channel('formKPIController')->error(
                        'Approval tidak punya No HP/Nama! Karyawan : '.$Kode_Karyawan,
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
                                    [
                                        'type' => 'text',
                                        'text' => 'Pengajuan Parameter KPI',
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => Auth::user()->karyawan->Nama,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ];

                $waResult = WhatsappLogHelper::sendAndLog(
                    $user->Kode_Karyawan,
                    $user->Nama,
                    $user->HP,
                    $no_faktur,
                    $pesan,
                    function ($response) use ($Kode_Karyawan, $no_faktur, $user) {
                        DB::table('KPI_Approval_Request_Parameter as a')
                            ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                            ->where('a.No_Transaksi', $no_faktur)
                            ->whereNull('b.Status')
                            ->where('b.Kode_Karyawan_Approver', $user->Kode_Karyawan)
                            ->update([
                                'a.Wa_Sent_Count' => DB::raw('COALESCE(Wa_Sent_Count, 0) + 1'),
                            ]);

                        Log::channel('formKPIController')->info(
                            "WA sukses dikirim ke approver {$user->Kode_Karyawan}",
                            [
                                'requester' => $Kode_Karyawan,
                                'response' => $response,
                            ],
                        );
                    },
                    function ($reason, $response) use ($user, $no_faktur, $pesan) {
                        Log::channel('formKPIController')->warning(
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
                            'No_Transaksi' => $no_faktur,
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

                // if ($waResult['status'] === 'success') {
                //     DB::table('KPI_Approval_Request_Parameter as a')
                //         ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                //         ->where("a.No_Transaksi", $no_faktur)
                //         ->whereNull("b.Status")
                //         ->where("b.Kode_Karyawan_Approver", $user->Kode_Karyawan)
                //         ->update(['a.Wa_Sent_Count' => DB::raw('COALESCE(Wa_Sent_Count, 0) + 1')]);
                // } else {
                //     Log::channel('formKPIController')->warning('Gagal kirim WA ke approver', [
                //         'user' => $user->Nama,
                //         'hp' => $user->HP,
                //         'no_faktur' => $no_faktur,
                //         'reason' => $waResult['reason'] ?? null,
                //         'response' => $waResult['response'] ?? null,
                //     ]);
                // }
            }

            DB::commit();

            return ResponseHelper::success(null, 'Data berhasil disimpan', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('formKPIController')->error('Error STORE: '.$e->getMessage());

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

    /**
     * Pengajuan parameter yang masih hidup dan akan DIGANTIKAN oleh pengajuan baru.
     *
     * Identitas sebuah pengajuan ditentukan oleh JADWALNYA, bukan oleh kalender saat
     * mengisi. Tanpa ikatan tahun, mengajukan parameter 2027 akan menonaktifkan
     * parameter 2026 milik orang yang sama — keduanya sama-sama "hidup" kalau yang
     * dilihat hanya Flag_Aktif + Status.
     *
     * Mengembalikan SEMUA No_Transaksi yang cocok, bukan satu: bila ada lebih dari
     * satu yang hidup (mis. peninggalan bug lama), versi sebelumnya hanya mematikan
     * satu dan menyisakan yang lain tetap hidup. Urutan dikunci agar deterministik.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    /**
     * Bentuk jamak: pengajuan yang akan digantikan untuk BANYAK pengguna sekaligus.
     *
     * Kiriman tim memanggil versi tunggalnya satu kali per anggota, padahal
     * tahunnya sama untuk semua dan yang berbeda hanya Id_User. Urutannya
     * dipertahankan persis (Id_Pengajuan menurun) supaya isi tiap daftar sama
     * seperti sebelumnya, bukan sekadar sama anggotanya.
     *
     * @param  array<int,mixed>  $idUsers
     * @return array<mixed,\Illuminate\Support\Collection>
     */
    private function cariPengajuanParameterDigantikanMassal(array $idUsers, $tahunJadwal): array
    {
        if ($idUsers === []) {
            return [];
        }

        return DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->whereNull('kp.Status')
            ->whereIn('kp.Id_User', $idUsers)
            ->where('kp.Flag_Aktif', 'Y')
            ->where('mp.Tahun', $tahunJadwal)
            ->where('mp.Flag_Form_KPI', 'Y')
            ->orderByDesc('kp.Id_Pengajuan')
            ->get(['kp.Id_User', 'kp.No_Transaksi'])
            ->groupBy('Id_User')
            ->map(fn ($baris) => $baris->pluck('No_Transaksi'))
            ->all();
    }

    /**
     * Bentuk tunggal — pembungkus tipis di atas bentuk massalnya.
     *
     * Dipertahankan karena KpiRealisasiGandaTest memanggilnya lewat refleksi
     * untuk menjaga dua aturan yang tak boleh hilang: pengajuan TAHUN LAIN dan
     * pengajuan yang DIBATALKAN tidak ikut digantikan. Menghapusnya membuat uji
     * itu gagal dengan ReflectionException — dan saya sempat melakukannya,
     * karena hanya menyisir app/ dan lupa tests/.
     *
     * Isinya sengaja tidak disalin: satu sumber kebenaran, satu kueri.
     */
    private function cariPengajuanParameterDigantikan($idUser, $tahunJadwal)
    {
        $massal = $this->cariPengajuanParameterDigantikanMassal([$idUser], $tahunJadwal);

        foreach ($massal as $kunci => $daftar) {
            if ((string) $kunci === (string) $idUser) {
                return $daftar;
            }
        }

        return collect();
    }

    /**
     * Jadwal pengisian dan bulan mulai yang berlaku untuk SATU anggota.
     *
     * Aturannya tinggal di KpiStartPeriodeResolver supaya penyimpanan, pratinjau,
     * dan audit data lama tidak punya salinan aturan masing-masing.
     */
    /**
     * Jadwal yang SAH dipakai sebagai payung pengisian TIM.
     *
     * Jadwal perorangan DULU dikecualikan di sini. Alasannya nyata: anggota di
     * luar rosternya jatuh ke `Periode` payung — yang pada jadwal perorangan
     * lazim NULL — lalu KpiStartPeriodeResolver::normalkan(null) mengubahnya
     * jadi Januari. PKPI08-26-0054 dan -0055 (Dhea dan Anita) tersimpan mulai
     * 2026-01 lewat jalur itu, sementara Amelia yang ada di roster tersimpan
     * benar mulai 2026-02.
     *
     * Lubang itu kini ditutup DI SUMBERNYA: resolver memulangkan
     * 'tidak_tercakup' alih-alih sebuah bulan, dan setiap pemanggil menolak
     * memakainya. Karena itu pengecualian di sini tidak lagi diperlukan —
     * mempertahankannya hanya menyisakan gejalanya, yaitu halaman Form KPI Tim
     * yang tak bisa dibuka ketika admin menugaskan orang lewat jadwal
     * perorangan saja.
     */
    private function jadwalTimQuery()
    {
        return DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Form_KPI', 'Y')
            // Jadwal perorangan TIDAK ikut pemilihan otomatis. Pemilihannya
            // ORDER BY Id DESC, jadi jadwal perorangan yang dibuat belakangan
            // akan membajak payung tim meski ada jadwal global yang sehat.
            ->where(function ($q) {
                $q->where('Flag_Perorangan', '<>', 'Y')->orWhereNull('Flag_Perorangan');
            });
    }

    /** Jadwal perorangan yang sedang berjalan, sebagai CADANGAN payung tim. */
    private function jadwalTimCadanganQuery()
    {
        return DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Form_KPI', 'Y')
            ->where('Flag_Perorangan', 'Y');
    }

    /**
     * Payung tim yang berlaku sekarang: GLOBAL lebih dulu, perorangan hanya
     * bila tidak ada global yang berjalan.
     *
     * Urutan ini yang menyelesaikan dua kebutuhan sekaligus. Mendahulukan
     * global menjaga jadwal perorangan dari membajak pemilihan; membolehkannya
     * sebagai cadangan membuat halaman Form KPI Tim tetap terbuka ketika admin
     * hanya menugaskan orang lewat jadwal perorangan — keluhan yang memulai
     * perubahan ini.
     *
     * Aman karena lubang Januari sudah ditutup di KpiStartPeriodeResolver:
     * anggota di luar roster payung perorangan memulangkan 'tidak_tercakup',
     * bukan bulan 1.
     */
    private function payungTimBerlaku(?string $tanggal = null)
    {
        $now = $tanggal ?? date('Y-m-d');

        $berjalan = fn ($q) => $q
            ->whereDate('Jadwal_Mulai', '<=', $now)
            ->whereDate('Jadwal_Akhir', '>=', $now)
            ->orderByDesc('Id_Master_Penjadwalan');

        return $berjalan($this->jadwalTimQuery())->first()
            ?? $berjalan($this->jadwalTimCadanganQuery())->first();
    }

    /**
     * Bolehkah SATU jadwal tertentu dipakai sebagai payung tim sekarang.
     *
     * Dipakai jalur pratinjau dan simpan, yang menerima id jadwal dari halaman.
     * Aturannya sama dengan payungTimBerlaku(), supaya yang bisa dibuka dan
     * yang bisa disimpan tidak pernah berselisih.
     */
    private function bolehJadiPayungTim($jadwal): bool
    {
        if (! $jadwal) {
            return false;
        }

        if (($jadwal->Flag_Perorangan ?? null) !== 'Y') {
            return true;
        }

        $now = date('Y-m-d');

        return ! $this->jadwalTimQuery()
            ->whereDate('Jadwal_Mulai', '<=', $now)
            ->whereDate('Jadwal_Akhir', '>=', $now)
            ->exists();
    }

    private function resolveJadwalPengisianAnggota(?string $kodeKaryawan, $jadwalGlobal): array
    {
        return app(\App\Services\KPI\KpiStartPeriodeResolver::class)
            ->resolve($kodeKaryawan, $jadwalGlobal);
    }

    /**
     * Bulan mulai yang berlaku untuk tiap anggota pada satu jadwal pengisian.
     *
     * Dipakai form KPI tim untuk menampilkan pemangkasan SEBELUM dikirim.
     * Aturan resolusinya sama persis dengan yang dipakai saat menyimpan
     * (resolveJadwalPengisianAnggota), jadi yang dilihat leader = yang tersimpan.
     */
    public function getStartPeriodeTeam(Request $request)
    {
        $request->validate([
            'Id_Penjadwalan' => 'required|string',
            'kode_karyawan' => 'required|array',
            'kode_karyawan.*' => 'required|string',
        ]);

        try {
            $idJadwal = Hashids::connection('custom')->decode($request->Id_Penjadwalan)[0] ?? null;

            if (empty($idJadwal)) {
                return ResponseHelper::error('ID Penjadwalan tidak valid', 400);
            }

            // Penyaring yang SAMA dengan jalur simpan: kalau pratinjau memakai
            // aturan yang lebih longgar, leader melihat bulan mulai yang tidak
            // pernah benar-benar dipakai saat menyimpan.
            $jadwalGlobal = DB::table('KPI_Master_Penjadwalan')
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Form_KPI', 'Y')
                ->where('Id_Master_Penjadwalan', $idJadwal)
                ->first();

            if (! $this->bolehJadiPayungTim($jadwalGlobal)) {
                $jadwalGlobal = null;
            }

            if (! $jadwalGlobal) {
                return ResponseHelper::error('Jadwal pengisian tidak ditemukan', 404);
            }

            $nama = DB::table('Karyawan')
                ->whereIn('Kode_Karyawan', $request->kode_karyawan)
                ->pluck('Nama', 'Kode_Karyawan');

            $hasil = collect($request->kode_karyawan)
                ->unique()
                ->map(function ($kode) use ($jadwalGlobal, $nama) {
                    $resolusi = $this->resolveJadwalPengisianAnggota($kode, $jadwalGlobal);

                    return [
                        'kode_karyawan' => $kode,
                        'nama_karyawan' => $nama[$kode] ?? $kode,
                        'mulai' => $resolusi['mulai'],
                        // null bila tidak tercakup. substr(null) memberi 0, dan
                        // "bulan 0" di layar terbaca seperti data rusak alih-alih
                        // seperti keadaan yang memang punya nama.
                        'bulan' => $resolusi['mulai'] === null
                            ? null
                            : (int) substr($resolusi['mulai'], 5, 2),
                        'sumber' => $resolusi['sumber'],
                        'tercakup' => $resolusi['tercakup'] ?? true,
                    ];
                })
                ->values();

            return ResponseHelper::success($hasil, 'Bulan mulai per anggota ditemukan', 200);
        } catch (\Exception $e) {
            Log::channel('formKPIController')->error('ERROR GETSTARTPERIODETEAM: '.$e->getMessage());

            return ResponseHelper::error('Terjadi Kesalahan', 500);
        }
    }

    /**
     * Tolak kiriman tim yang mencampur revisi seseorang dengan anggota lain.
     *
     * Mengembalikan response error bila terlarang, atau null bila kiriman aman.
     * Dipisah dari storeTeam() supaya aturannya bisa diuji sendiri tanpa
     * menjalankan seluruh proses penyimpanan.
     *
     * @param  \Illuminate\Support\Collection  $kodeKaryawan  kode karyawan dalam kiriman
     * @param  string|null  $hashJadwal  Id_Master_Penjadwalan ter-hash dari frontend
     */
    private function tolakRevisiBercampur($kodeKaryawan, ?string $hashJadwal)
    {
        $kodeUnik = collect($kodeKaryawan)->filter()->unique()->values();

        // Satu orang saja tidak mungkin bercampur.
        if ($kodeUnik->count() < 2) {
            return null;
        }

        $idJadwal = $hashJadwal
            ? (Hashids::connection('custom')->decode($hashJadwal)[0] ?? null)
            : null;

        $tahunJadwal = $idJadwal
            ? DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $idJadwal)->value('Tahun')
            : null;

        // Tanpa tahun jadwal, "pengajuan hidup di tahun yang sama" tidak bisa
        // ditentukan. Jangan menebak — biarkan lewat, validasi jadwal di bawah
        // yang akan menolaknya kalau memang jadwalnya tidak sah.
        if (! $tahunJadwal) {
            return null;
        }

        $sudahPunyaPengajuan = DB::table('KPI_Pengajuan as kp')
            ->join('KPI_Master_Penjadwalan as mp', 'mp.Id_Master_Penjadwalan', '=', 'kp.Id_Penjadwalan')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kp.Id_User')
            ->whereIn('k.Kode_Karyawan', $kodeUnik)
            ->where('kp.Flag_Aktif', 'Y')
            ->whereNull('kp.Status')
            ->where('mp.Tahun', $tahunJadwal)
            // Hanya pengajuan parameter yang relevan; pengajuan di jadwal update
            // performance tidak akan tergantikan oleh kiriman ini, jadi tidak
            // boleh dipakai sebagai alasan menolak.
            ->where('mp.Flag_Form_KPI', 'Y')
            ->pluck('k.Nama', 'k.Kode_Karyawan');

        if ($sudahPunyaPengajuan->isEmpty()) {
            return null;
        }

        $nama = collect($sudahPunyaPengajuan)->filter()->implode(', ');

        Log::channel('formKPIController')->warning('Revisi dicampur dengan anggota lain ditolak', [
            'tahun_jadwal' => $tahunJadwal,
            'jumlah_dikirim' => $kodeUnik->count(),
            'sedang_direvisi' => $sudahPunyaPengajuan->keys()->all(),
        ]);

        return ResponseHelper::error(
            'Revisi harus dikirim sendiri. '.$nama.' sudah punya pengajuan yang berjalan, '
                .'jadi kiriman ini tidak boleh memuat anggota lain. '
                .'Simpan revisinya lebih dulu, baru isi anggota yang lain.',
            422,
        );
    }

    public function storeTeam(Request $request)
    {
        // dd($request->all());
        $allData = $request->data;
        $PenjadwalanData = $request->penjadwalan;
        if (empty($allData)) {
            return ResponseHelper::error('Tidak ada data yang dikirim', 400);
        }

        $getKode_Karyawan = collect($allData)->pluck('employeeId');

        // Revisi harus menyendiri.
        //
        // Menyimpan pengajuan tim akan MEMBATALKAN pengajuan lama yang masih
        // hidup di tahun yang sama (lihat cariPengajuanParameterDigantikanMassal).
        // Itu memang yang diinginkan saat merevisi orang yang ditolak — tapi
        // menjadi kerusakan diam-diam bila orang lain ikut terbawa dalam
        // kiriman yang sama: template milik satu orang tertulis ke orang lain,
        // dan pengajuan mereka yang sedang menunggu approver ikut dibatalkan.
        //
        // Penjagaan ada di server, bukan hanya di layar, karena kiriman bisa
        // datang dari mana saja. Aturannya: begitu ada satu anggota dalam
        // kiriman yang sudah punya pengajuan hidup — artinya ini revisi atau
        // penyuntingan — kiriman itu tidak boleh memuat siapa pun selain dia.
        $penjagaanRevisi = $this->tolakRevisiBercampur($getKode_Karyawan, $PenjadwalanData['id'] ?? null);
        if ($penjagaanRevisi) {
            return $penjagaanRevisi;
        }

        // ENGINE ROUTER (tim): pre-check approver hanya valid untuk LEGACY.
        // Pada CENTRAL (HRIS_Approval_Request) flow di-resolve oleh
        // KpiApprovalService::createParamFlow per anggota — tabel
        // KPI_Approval_Flow_Parameter kosong, jadi pre-check legacy ini akan
        // salah memvonis "belum punya approval". Validasi approver pada CENTRAL
        // dilakukan di createParamFlow saat penyimpanan (lihat di bawah).
        $paramEngine = strtoupper((string) config('services.kpi.param_engine', 'CENTRAL'));

        if ($paramEngine === 'LEGACY') {
            $checkApproval = DB::table('KPI_Approval_Flow_Parameter as a')
                ->join('Karyawan as b', 'a.Kode_Karyawan_Requester', '=', 'b.Kode_Karyawan')
                ->whereIn('b.Kode_Karyawan', $getKode_Karyawan)
                ->where('b.Aktif', 'Y')
                ->where(function ($query) {
                    $query->whereNull('b.Tanggal_Resign')->orWhere('b.Tanggal_Resign', '>=', now());
                })
                ->whereNull('a.Status')
                ->pluck('a.Kode_Karyawan_Requester');

            $notHaveApproval = $getKode_Karyawan->diff($checkApproval);

            if ($notHaveApproval->isNotEmpty()) {
                $listKaryawan = $notHaveApproval->join(', ');

                Log::channel('formKPIController')->warning(
                    'Karyawan belum memiliki approval/approver resign flow: '.$listKaryawan,
                );

                return ResponseHelper::error(
                    "Silahkan Hubungi Admin,<br/> Beberapa karyawan belum memiliki approval/approver resign flow: {$listKaryawan} ",
                    400,
                );
            }
        }

        // dd($allData, $PenjadwalanData, $PenjadwalanData['id']);

        DB::beginTransaction();
        try {
            $results = [];
            $approverList = collect();
            /* Jadwalnya SAMA untuk seluruh anggota — ia diturunkan dari
               $PenjadwalanData yang ditetapkan sekali di awal permintaan, bukan
               dari tiap anggota. Dulu kueri ini dijalankan ulang untuk setiap
               orang dan mengembalikan baris yang sama persis: satu kueri per
               anggota untuk jawaban yang tak pernah berbeda. */
            $idJadwalTerjadwal = Hashids::connection('custom')->decode($PenjadwalanData['id'])[0] ?? null;

            $ambilJadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Flag_Aktif', 'Y')
                ->where('Flag_Form_KPI', 'Y')
                ->where('Id_Master_Penjadwalan', $idJadwalTerjadwal)
                ->first();

            if (! $this->bolehJadiPayungTim($ambilJadwal)) {
                $ambilJadwal = null;
            }

            if (! $ambilJadwal) {
                Log::channel('formKPIController')->error('Tidak ada jadwal aktif untuk kiriman tim ini.');
                DB::rollBack();

                return ResponseHelper::error(
                    'Silahkan Hubungi Admin,<br/> Jadwal pengisian parameter tidak aktif.',
                    400,
                );
            }

            /* Pengajuan lama yang akan digantikan dicari SEKALI untuk seluruh
               anggota. Tahunnya sama; yang berbeda hanya Id_User. */
            $idPenggunaSemua = collect($allData)
                ->pluck('UserId_Web')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $pengajuanLamaPerPengguna = $this->cariPengajuanParameterDigantikanMassal(
                $idPenggunaSemua,
                $ambilJadwal->Tahun,
            );

            foreach ($allData as $employeeData) {
                $id_sender = Auth::user()->Id_Users;
                $id_pengguna = $employeeData['UserId_Web'] ?? null;
                $Kode_Karyawan = $employeeData['employeeId'] ?? null;

                if (! $id_pengguna || ! $Kode_Karyawan) {
                    Log::channel('formKPIController')->error('User ID atau Kode Karyawan kosong');
                    DB::rollBack();

                    return ResponseHelper::error(
                        "Silahkan Hubungi Admin,<br/> User ID atau Kode Karyawan kosong: {$employeeData['employeeId']} ",
                        400,
                    );
                    Db::rollback();
                }

                // Dulu di sini ada query ke KPI_Approval_Flow_Parameter untuk mengisi
                // $approverAll, tapi satu-satunya pemakainya sudah lama dikomentari —
                // jadi hasilnya dibuang dan biayanya satu query per anggota setiap
                // submit. Dihapus. Ketiadaan approver pada engine CENTRAL sudah
                // ditangani createParamFlow() saat penyimpanan.

                // Jadwal tim WAJIB punya bulan mulai untuk jadwal global.
                // Untuk jadwal perorangan, bulan mulai ditentukan per anggota di KpiStartPeriodeResolver.
                if (empty($ambilJadwal->Periode) && ($ambilJadwal->Flag_Perorangan ?? null) !== 'Y') {
                    DB::rollBack();

                    return ResponseHelper::error(
                        'Jadwal pengisian ini belum punya periode mulai. '.
                        'Hubungi admin KPI untuk menetapkannya sebelum mengirim pengajuan tim.',
                        422,
                    );
                }

                // Bulan mulai dihitung PER ANGGOTA, bukan sekali dari NIK leader.
                // Jadwal perorangan anggota menimpa jadwal global yang dipilih leader.
                $jadwalAnggota = $this->resolveJadwalPengisianAnggota($Kode_Karyawan, $ambilJadwal);

                if (! ($jadwalAnggota['tercakup'] ?? true) || empty($jadwalAnggota['mulai'])) {
                    DB::rollBack();

                    return ResponseHelper::error(
                        "Karyawan {$employeeData['name']} ({$Kode_Karyawan}) tidak terdaftar dalam jadwal pengisian ini.",
                        422,
                    );
                }

                $pengajuanLama = $pengajuanLamaPerPengguna[$id_pengguna] ?? collect();

                if ($pengajuanLama->isNotEmpty()) {
                    DB::table('KPI_Pengajuan')
                        ->whereNull('Status')
                        ->where('Flag_Aktif', 'Y')
                        ->whereIn('No_Transaksi', $pengajuanLama)
                        ->update([
                            'Flag_Aktif' => 'T',
                            'Status' => 'Y',
                        ]);

                    // Langkah approval milik pengajuan lama WAJIB ikut ditutup. Tanpa ini
                    // barisnya tetap menggantung di inbox approver sehingga versi lama
                    // masih bisa disetujui (pola sama dengan KpiCancellationService).
                    DB::table('HRIS_Approval_Request')
                        ->whereIn('No_Transaksi', $pengajuanLama)
                        ->whereNull('Flag_Approval')
                        ->update(['Status' => 'Y']);

                    Log::channel('formKPIController')->info(
                        'Pengajuan ulang parameter, Karyawan: '.$Kode_Karyawan.
                        ', tahun: '.$ambilJadwal->Tahun.
                        ', digantikan: '.$pengajuanLama->implode(', '),
                    );
                }

                $formatTanggalSekarang = Carbon::now()->format('m-y');
                $prefix = 'PKPI'.$formatTanggalSekarang.'-';

                // no Transaksi via COUNTER ATOMIK (bebas race duplikat + tanpa scan).
                $no_faktur = app(\App\Services\SYSTEM\Application\Transactions\TransactionNumberService::class)->generate(
                    $prefix,
                    fn () => DB::table('KPI_Pengajuan')
                        ->where('No_Transaksi', 'like', $prefix.'%')
                        ->max(DB::raw('CAST(RIGHT(No_Transaksi, 4) AS INT)')),
                );
                $no_faktur_ref = $employeeData['No_Transaksi_Ref'] ?? null;

                $pengajuanId = DB::table('KPI_Pengajuan')->insertGetId([
                    'No_Transaksi' => $no_faktur,
                    // Jadwal yang BENAR-BENAR mengatur anggota ini: jadwal perorangannya
                    // bila ada, kalau tidak jadwal global pilihan leader.
                    'Id_Penjadwalan' => $jadwalAnggota['id_penjadwalan'],
                    'No_Transaksi_Ref' => $no_faktur_ref,
                    'Flag_Aktif' => 'Y',
                    'Id_User_Sender' => $id_sender,
                    'Flag_Team' => 'Y',
                    'Id_User' => $id_pengguna,
                    'Jam' => now()->format('H:i:s'),
                    'Tanggal' => now()->format('Y-m-d'),
                ]);
                /*
                 * Breakdown target diambil SEKALI. Sebelumnya satu kueri per KPI, di
                 * dalam transaksi pengajuan -- jumlahnya tumbuh bersama banyaknya
                 * parameter yang diajukan, dan selama itu kuncinya ditahan.
                 *
                 * KPI_Target_Breakdown hanya DIBACA di metode ini, jadi petanya tidak
                 * bisa basi oleh perulangannya sendiri. Penguraian id di sini sengaja
                 * longgar: validasinya tetap di tempat semula, di dalam perulangan,
                 * supaya urutan dan pesan galatnya tidak berubah.
                 */
                $idTargetSemua = [];
                foreach ($employeeData['kpis'] as $kpiPra) {
                    $idT = Hashids::connection('custom')->decode($kpiPra['target'])[0] ?? null;
                    if ($idT) {
                        $idTargetSemua[$idT] = true;
                    }
                }

                $breakdownPerTarget = DB::table('KPI_Target_Breakdown')
                    ->whereIn('Id_Master_Target', array_keys($idTargetSemua))
                    ->get()
                    ->groupBy('Id_Master_Target');

                foreach ($employeeData['kpis'] as $kpi) {
                    $idBscKategori = Hashids::connection('custom')->decode($kpi['bscCategory'])[0] ?? null;
                    $idFormula = Hashids::connection('custom')->decode($kpi['formula'])[0] ?? null;
                    $idUnit = Hashids::connection('custom')->decode($kpi['unit'])[0] ?? null;
                    $idTargetM = Hashids::connection('custom')->decode($kpi['target'])[0] ?? null;

                    if (! $idBscKategori || ! $idFormula || ! $idUnit || ! $idTargetM) {
                        Log::channel('formKPIController')->warning(
                            'Data ID tidak valid untuk bsc, formula, unit, target: '.$Kode_Karyawan,
                        );

                        DB::rollback();

                        return ResponseHelper::error(
                            "Silahkan Hubungi Admin,<br/> Beberapa Data ID tidak valid untuk bsc, formula, unit, target: {$Kode_Karyawan} ",
                            400,
                        );
                    }

                    $detailId = DB::table('KPI_Pengajuan_Detail')->insertGetId([
                        'Id_Pengajuan' => $pengajuanId,
                        'Nama_Parameter' => $kpi['parameter'],
                        'Id_Bsc_Kategori' => $idBscKategori,
                        'Deskripsi' => $kpi['definition'] ?? null,
                        'Kode_Karyawan' => $Kode_Karyawan,
                        'Id_Master_Formula' => $idFormula,
                        'Id_Master_Satuan' => $idUnit,
                        'Bobot' => $kpi['weight'],
                        'Id_Master_Target' => $idTargetM,
                    ]);

                    $targetDatas = $breakdownPerTarget[$idTargetM] ?? collect();

                    $targetData = [];
                    foreach ($kpi['targetSimpan'] as $id_targetB => $nilai) {
                        // Periode pra-start dikirim null/'' dari frontend → tidak di-insert.
                        if ($nilai === null || $nilai === '') {
                            continue;
                        }
                        $decodeTargetBreak = Hashids::connection('custom')->decode($id_targetB)[0] ?? null;
                        if (! $decodeTargetBreak) {
                            Log::channel('formKPIController')->warning(
                                'Target breakdown tidak valid untuk: '.$Kode_Karyawan,
                            );

                            DB::rollback();

                            return ResponseHelper::error(
                                "Silahkan Hubungi Admin,<br/> Target breakdown tidak valid untuk: {$KKode_Karyawanode_} ",
                                400,
                            );
                        }

                        $targetPicked = $targetDatas->firstWhere('Id_Target_Breakdown', $decodeTargetBreak);
                        $valuePeriode = $ambilJadwal->Tahun.'-'.$targetPicked->Periode;

                        // Buang periode sebelum bulan mulai anggota ini. WAJIB, bukan
                        // kerapian: YTD menjumlahkan target dari baris yang ADA
                        // (DashboardCeoController::allTargets), jadi target bulan yang
                        // tak pernah ia jalani akan menekan achievement-nya tanpa sebab
                        // yang terlihat di layar mana pun. Perbandingan string aman
                        // karena kedua sisi berformat 'YYYY-MM' dengan bulan dua digit.
                        // Breakdown kuartalan yang MEMUAT bulan mulai tetap ikut
                        // (mis. mulai Mei, Q2 = '06' > '05' → dipertahankan).
                        // Anggota yang tidak tercakup payung ini sudah ditolak lebih
                        // awal; penjagaan di sini menutup kemungkinan null lolos ke
                        // perbandingan, yang akan membandingkan dengan string kosong
                        // dan MELOLOSKAN seluruh periode — kebalikan dari maksudnya.
                        if ($jadwalAnggota['mulai'] === null || $valuePeriode < $jadwalAnggota['mulai']) {
                            continue;
                        }

                        $targetData[] = [
                            'Id_Pengajuan_Detail' => $detailId,
                            'Nama_Target' => $targetPicked->Nama_Breakdown,
                            'Nilai_Target' => $nilai,
                            'Periode' => $valuePeriode,
                            'Id_Target_Breakdown' => $targetPicked->Id_Target_Breakdown,
                        ];
                    }

                    DB::table('KPI_Pengajuan_Det')->insert($targetData);
                }

                // ENGINE ROUTER (tim): requester = $Kode_Karyawan (anggota yang dinilai).
                if ($paramEngine !== 'LEGACY') {
                    $flowResultTeam = app(\App\Services\KPI\Approval\KpiApprovalService::class)
                        ->createParamFlow($no_faktur, $Kode_Karyawan);

                    if (! $flowResultTeam->success) {
                        Log::channel('formKPIController')->error(
                            'Gagal flow approval (CENTRAL) parameter tim: '.($flowResultTeam->error ?? '-').
                                ' | Anggota: '.$Kode_Karyawan,
                        );
                        DB::rollback();

                        return ResponseHelper::error($flowResultTeam->error ?: 'Gagal membuat flow approval.', 404);
                    }

                    // Notifikasi approver pertama anggota ini (template KPI).
                    $this->notifyKpiParamFirstApproverCentral($no_faktur, $Kode_Karyawan);

                    // Bila step pertama langsung verifikator ROLE, notif in-app role pool.
                    app(\App\Services\KPI\Notification\KpiNotificationService::class)->notifyRolePoolForCurrentStep($no_faktur);

                    // Lanjut ke anggota berikutnya (lewati build/WA batch legacy).
                    continue;
                }

                $flow = DB::table('KPI_Approval_Flow_Parameter')
                    ->whereNull('Status')
                    ->where('Kode_Karyawan_Requester', $Kode_Karyawan)
                    ->get()
                    ->map(function ($item) use ($Kode_Karyawan, $no_faktur) {
                        return [
                            'Kode_Karyawan_Requester' => $Kode_Karyawan,
                            'Flow_Id' => $item->Id_Approval_Flow,
                            'No_Transaksi' => $no_faktur,
                        ];
                    });

                DB::table('KPI_Approval_Request_Parameter')->insert($flow->toArray());

                $approvers = DB::table('KPI_Approval_Flow_Parameter as a')
                    ->select('b.HP', 'b.Nama', 'b.Kode_Karyawan')
                    ->join('Karyawan as b', 'a.Kode_Karyawan_Approver', '=', 'b.Kode_Karyawan')
                    ->where('a.Kode_Karyawan_Requester', $Kode_Karyawan)
                    ->whereNull('a.Status')
                    ->orderBy('a.order_flow')
                    ->whereIn('a.Kode_Karyawan_Requester', $getKode_Karyawan)
                    ->where('b.Aktif', 'Y')
                    ->where(function ($query) {
                        $query->whereNull('b.Tanggal_Resign')->orWhere('b.Tanggal_Resign', '>=', now());
                    })
                    ->first();

                if ($approvers && $approvers->HP) {
                    $approverList->push([
                        'Kode_Karyawan_Approver' => $approvers->Kode_Karyawan,
                        'Nama_Approver' => $approvers->Nama,
                        'HP' => $approvers->HP,
                        'Kode_Karyawan_Requester' => $Kode_Karyawan,
                        'No_Transaksi' => $no_faktur,
                    ]);
                }
            }

            $groupedApprovers = $approverList->groupBy('Kode_Karyawan_Approver');
            $groupedKodeKaryawan = $groupedApprovers->pluck('Kode_Karyawan_Requester');

            foreach ($groupedApprovers as $kodeApprover => $items) {
                $approverInfo = $items->first();
                $jumlahPengajuan = $items->count();
                $daftarRequester = $items->pluck('Kode_Karyawan_Requester');

                $daftarNamaRaw = DB::table('Karyawan')->whereIn('Kode_Karyawan', $daftarRequester)->get();

                $daftarNama = collect($daftarNamaRaw)->pluck('Nama')->implode(', ');

                $pesan = [
                    'messaging_product' => 'whatsapp',
                    'to' => $approverInfo['HP'],
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
                                    [
                                        'type' => 'text',
                                        'text' => $approverInfo['Nama_Approver'],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "Total {$jumlahPengajuan}",
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => 'Pengajuan KPI',
                                    ],
                                    ['type' => 'text', 'text' => $daftarNama],
                                ],
                            ],
                        ],
                    ],
                ];

                WhatsappLogHelper::sendAndLog(
                    $kodeApprover,
                    $approverInfo['Nama_Approver'],
                    $approverInfo['HP'],
                    collect($items)->pluck('No_Transaksi')->implode(','),
                    $pesan,

                    function ($response) use ($kodeApprover, $items, $approverInfo) {
                        $noTransaksiList = collect($items)->pluck('No_Transaksi')->unique();

                        DB::table('KPI_Approval_Request_Parameter as a')
                            ->join('KPI_Approval_Flow_Parameter as b', 'a.Flow_Id', '=', 'b.Id_Approval_Flow')
                            ->whereIn('a.No_Transaksi', $noTransaksiList)
                            ->whereNull('b.Status')
                            ->where('b.Kode_Karyawan_Approver', $kodeApprover)
                            ->update([
                                'a.Wa_Sent_Count' => DB::raw('COALESCE(a.Wa_Sent_Count, 0) + 1'),
                            ]);

                        Log::channel('formKPIController')->info("WA sukses dikirim ke approver {$kodeApprover}", [
                            'nama' => $approverInfo['Nama_Approver'],
                            'response' => $response,
                        ]);
                    },

                    function ($reason, $response) use ($kodeApprover, $approverInfo, $items, $pesan) {
                        Log::channel('formKPIController')->warning("Gagal kirim WA ke approver {$kodeApprover}", [
                            'reason' => $reason,
                            'response' => $response,
                        ]);

                        DB::table('Whatsapp_Log')->insert([
                            'Kode_Karyawan_Destination' => $kodeApprover,
                            'Nama' => $approverInfo['Nama_Approver'],
                            'HP' => $approverInfo['HP'],
                            'No_Transaksi' => collect($items)->pluck('No_Transaksi')->implode(','),
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

            DB::commit();
            $results[] = [
                'employeeId' => $groupedKodeKaryawan,
                'status' => 'success',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::channel('formKPIController')->error(
                "Error multiple store {$groupedKodeKaryawan} : ".$e->getMessage(),
            );
            $results[] = [
                'employeeId' => $groupedKodeKaryawan,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }

        return ResponseHelper::success($results, 'Proses selesai', 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    private function getKpiParameterApproverPreview(string $kodeKaryawan)
    {
        try {
            $resolvedApprovers = collect(
                app(ApprovalFlowService::class)->getApproverFlow(
                    $kodeKaryawan,
                    KpiApprovalService::CONFIG_PARAM,
                ),
            );
        } catch (\Throwable $e) {
            Log::channel('formKPIController')->warning(
                'Gagal resolve preview approver parameter KPI: '.$e->getMessage(),
                ['kode_karyawan' => $kodeKaryawan],
            );

            return collect();
        }

        if ($resolvedApprovers->isEmpty()) {
            return collect();
        }

        $kodeApprovers = $resolvedApprovers
            ->pluck('kode_karyawan')
            ->filter()
            ->unique()
            ->values();

        $employees = $kodeApprovers->isEmpty()
            ? collect()
            : DB::table('Karyawan as k')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.ID_Level_Jabatan',
                    '=',
                    'vg.ID_Level_Jabatan',
                )
                ->whereIn('k.Kode_Karyawan', $kodeApprovers)
                ->select('k.Kode_Karyawan', 'k.Nama', 'vg.nama_jabatan')
                ->get()
                ->keyBy('Kode_Karyawan');

        return $resolvedApprovers
            ->values()
            ->map(function ($approver, $index) use ($employees) {
                $kodeApprover = $approver->kode_karyawan ?? null;
                $type = $approver->type ?? 'SERIAL';
                $employee = $kodeApprover ? $employees->get($kodeApprover) : null;

                return [
                    'name' => $employee->Nama ?? ($approver->role_name ?? 'Approver'),
                    'role' => $employee->nama_jabatan ?? ($type === 'GROUP' ? 'Group Approval' : 'Approver'),
                    'level' => $approver->order_flow ?? ($index + 1),
                    'kode_karyawan' => $kodeApprover,
                    'type' => $type,
                    'source' => 'approval_flow_service',
                ];
            });
    }

    /**
     * Preview approver untuk BANYAK karyawan sekaligus.
     *
     * Versi tunggalnya dipanggil di dalam perulangan anggota tim, dan tiap
     * panggilan membaca ulang config approval yang sama. Pada grid dengan 51
     * anggota itu berarti 126 query untuk data yang sebagian besar identik.
     *
     * @param  array<int, string>  $kodeKaryawan
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function getKpiParameterApproverPreviewBatch(array $kodeKaryawan): array
    {
        $kodeKaryawan = array_values(array_unique(array_filter($kodeKaryawan)));

        if (empty($kodeKaryawan)) {
            return [];
        }

        try {
            $perKaryawan = app(ApprovalFlowService::class)->getApproverFlowBatch(
                $kodeKaryawan,
                KpiApprovalService::CONFIG_PARAM,
            );
        } catch (\Throwable $e) {
            Log::channel('formKPIController')->warning(
                'Gagal resolve preview approver parameter KPI (batch): '.$e->getMessage(),
                ['jumlah_karyawan' => count($kodeKaryawan)],
            );

            return array_fill_keys($kodeKaryawan, collect());
        }

        // Satu kali ambil identitas SEMUA approver yang muncul, lalu dipakai
        // ulang untuk setiap karyawan — approver kerap sama antar anggota.
        $kodeApprovers = collect($perKaryawan)
            ->flatMap(fn ($approvers) => collect($approvers)->pluck('kode_karyawan')->all())
            ->filter()
            ->unique()
            ->values();

        $employees = $kodeApprovers->isEmpty()
            ? collect()
            : DB::table('Karyawan as k')
                ->leftJoin(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vg',
                    'k.ID_Level_Jabatan',
                    '=',
                    'vg.ID_Level_Jabatan',
                )
                ->whereIn('k.Kode_Karyawan', $kodeApprovers)
                ->select('k.Kode_Karyawan', 'k.Nama', 'vg.nama_jabatan')
                ->get()
                ->keyBy('Kode_Karyawan');

        $hasil = [];

        foreach ($kodeKaryawan as $kode) {
            $hasil[$kode] = collect($perKaryawan[$kode] ?? [])
                ->values()
                ->map(function ($approver, $index) use ($employees) {
                    $kodeApprover = $approver->kode_karyawan ?? null;
                    $type = $approver->type ?? 'SERIAL';
                    $employee = $kodeApprover ? $employees->get($kodeApprover) : null;

                    return [
                        'name' => $employee->Nama ?? ($approver->role_name ?? 'Approver'),
                        'role' => $employee->nama_jabatan ?? ($type === 'GROUP' ? 'Group Approval' : 'Approver'),
                        'level' => $approver->order_flow ?? ($index + 1),
                        'kode_karyawan' => $kodeApprover,
                        'type' => $type,
                        'source' => 'approval_flow_service',
                    ];
                });
        }

        return $hasil;
    }

    /**
     * Bulan mulai (MM) pada jadwal PERORANGAN, untuk satu karyawan.
     *
     * Dipakai getDataCurrent() DAN getTargetBreakdown(). Dulu keduanya memuat
     * salinan aturan yang sama persis; dua salinan pasti berselisih suatu hari,
     * dan selisihnya berupa dua layar yang menampilkan target aktif berbeda
     * untuk orang yang sama — bukan galat.
     *
     * Tiga keadaan, masing-masing dijawab sengaja:
     *
     * 1. Barisnya ada dan berbulan-mulai  -> pakai bulan itu. Inilah jalur
     *    form INDIVIDU, dan perilakunya tidak berubah.
     * 2. Barisnya ada tetapi Start_Periode kosong -> `1`, sama dengan fallback
     *    ketika Periode jadwal kosong: semua periode aktif. Kode lama
     *    menghasilkan '00' di sini, dan getListTarget() menolak '00' dengan
     *    "Periode tidak valid" — terverifikasi, 17 baris di basis dev berada
     *    dalam keadaan ini.
     * 3. Barisnya TIDAK ada -> ini lead form TIM yang bukan peserta jadwal.
     *    Dulu 404 dan seluruh form mati saat dibuka. Dipakai bulan mulai paling
     *    awal di antara peserta; kalau jadwalnya kosong sama sekali, `1`.
     */
    private function bulanMulaiPerorangan(int $idPenjadwalan, ?string $kodeKaryawan): string
    {
        $baris = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->where('Id_Master_Penjadwalan', $idPenjadwalan)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first();

        $periode = $baris
            ? $baris->Start_Periode
            : DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                ->where('Id_Master_Penjadwalan', $idPenjadwalan)
                ->min('Start_Periode');

        if (empty($periode)) {
            return '1';
        }

        return str_contains((string) $periode, '-')
            ? explode('-', (string) $periode)[1]
            : (string) $periode;
    }

    public function getListTarget($start)
    {
        if ($start > 12 || $start < 1) {
            return ResponseHelper::error('Periode tidak valid', 400);
        }

        $target = DB::table('KPI_Master_Target as a')->where('a.Flag_Aktif', 'Y')->get()->toArray();

        $finalTarget = collect($target)->map(function ($item) use ($start) {
            $breakdowns = DB::table('KPI_Target_Breakdown')
                ->where('Id_Master_Target', $item->Id_Master_Target)
                ->orderBy('Periode', 'asc')
                ->pluck('Periode')
                ->toArray();

            $isActive = false;
            $prevEnd = 0;
            $startMonth = null;

            foreach ($breakdowns as $end) {
                // $startRange = $prevEnd + 1;

                // // simpan bulan mulai paling awal
                // if ($startMonth === null) {
                //     $startMonth = $startRange;
                // }

                // // masih ada periode yang belum dimulai
                // if ($startRange >= $start) {
                //     $isActive = true;
                //     $startMonth = $startRange;
                //     break;
                // }

                // $prevEnd = $end;

                if ($end >= $start) {
                    $isActive = true;
                    break;
                }
            }

            $disStart = Carbon::create()->locale('id')->month($start)->translatedFormat('F');

            return (object) [
                'Id_Master_Target' => $item->Id_Master_Target,
                'Nama_Target' => $item->Nama_Target,
                'Kode_Target' => $item->Kode_Target,
                'Deskripsi' => $item->Deskripsi,
                'is_active' => $isActive ? 'Y' : 'T',
                'reason' => $isActive ? 'Target masih tersedia.' : 'Tidak tersedia — periode sudah berjalan.',
            ];
        });

        return $finalTarget;
    }

    /**
     * Kirim WA template KPI ke approver pertama untuk pengajuan parameter (CENTRAL).
     *
     * Hanya menangani approver tunggal (SERIAL). Step ROLE pool dilewati.
     */
    private function notifyKpiParamFirstApproverCentral(string $noTransaksi, string $kodeRequester): void
    {
        $current = app(\App\Services\SYSTEM\Governance\Approval\Shared\ApprovalRequestService::class)
            ->getCurrentApprovalRequest($noTransaksi);

        $approverKode = $current->Current_Approver ?? null;
        if (! $approverKode) {
            return; // step ROLE pool / tidak ada approver tunggal
        }

        $user = DB::table('Karyawan')->where('Kode_Karyawan', $approverKode)->first();
        $requester = DB::table('Karyawan')->where('Kode_Karyawan', $kodeRequester)->first();

        if (! $user || ! $user->HP || trim($user->HP) === '') {
            Log::channel('formKPIController')->warning(
                'Approver pertama (CENTRAL) tidak punya HP. No_Transaksi: '.$noTransaksi,
            );

            return;
        }

        $pesan = [
            'messaging_product' => 'whatsapp',
            'to' => $user->HP,
            'type' => 'template',
            'template' => [
                'name' => 'kpi_approval_pengajuan_dan_performance',
                'language' => ['code' => 'id', 'policy' => 'deterministic'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $user->Nama],
                        ['type' => 'text', 'text' => '1'],
                        ['type' => 'text', 'text' => 'Pengajuan Parameter KPI'],
                        ['type' => 'text', 'text' => $requester->Nama ?? '-'],
                    ],
                ]],
            ],
        ];

        WhatsappLogHelper::sendAndLog(
            $user->Kode_Karyawan,
            $user->Nama,
            $user->HP,
            $noTransaksi,
            $pesan,
            function ($response) use ($user, $noTransaksi) {
                Log::channel('formKPIController')->info(
                    "WA approver pertama KPI param (CENTRAL) terkirim ke {$user->Kode_Karyawan}",
                    ['no_transaksi' => $noTransaksi],
                );
            },
            function ($reason, $response) use ($user, $noTransaksi, $pesan) {
                Log::channel('formKPIController')->warning(
                    "Gagal WA approver pertama KPI param (CENTRAL) {$user->Kode_Karyawan}",
                    ['reason' => $reason],
                );
                DB::table('Whatsapp_Log')->insert([
                    'Kode_Karyawan_Destination' => $user->Kode_Karyawan,
                    'Nama' => $user->Nama,
                    'HP' => $user->HP,
                    'No_Transaksi' => $noTransaksi,
                    'Error_Message' => is_array($response) ? json_encode($response, JSON_UNESCAPED_UNICODE) : ($reason ?? 'Unknown error'),
                    'RawMessage' => print_r($pesan, true),
                    'RawMessageJson' => json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                    'Created_At' => now(),
                    'Is_Retried' => 0,
                ]);
            },
        );
    }
}
