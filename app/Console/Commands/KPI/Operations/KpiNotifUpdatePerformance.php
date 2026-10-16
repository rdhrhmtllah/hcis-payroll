<?php

namespace App\Console\Commands\KPI\Operations;

use App\Helpers\FormatTanggalHelper;
use App\Http\Controllers\KPI\Performance\Update\UpdatePerformanceController;
use App\Jobs\KPI\Notification\SendKpiNotifWhatsappJob;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiNotifUpdatePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi:notif-update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mengirimkan notifikasi WhatsApp kepada karyawan yang belum melakukan update performance KPI untuk periode sebelumnya.';

    /**
     * Siapa yang dikirimi pengingat untuk satu periode.
     *
     * Dipisahkan dari handle() supaya aturannya bisa diuji tanpa menghidupkan
     * seluruh command (yang menuntut ada jadwal penilaian yang sedang berjalan
     * pada hari uji dijalankan, lalu mengirim WhatsApp).
     *
     * Kepegawaian dinilai SEKARANG (`k.Aktif='Y'`), bukan kepegawaian pada
     * periode — dan perbedaan itu disengaja terhadap universe monitoring/Auto Cap
     * di App\Services\KPI\KpiKepegawaianPeriode. Sebuah pengingat adalah
     * tindakan kepada orang saat ini; mengirim WhatsApp ke mantan karyawan tidak
     * menjadi benar hanya karena periodenya benar. Terukur di produksi 16 Sep
     * 2026: tanpa penyaring ini, 35 nomor milik karyawan yang sudah resign ikut
     * jadi sasaran pengingat Juli 2026 — dan 35 lagi pada Agustus 2026.
     *
     * @return \Illuminate\Support\Collection<int,object>
     */
    public static function sasaranPengingat(string $periode)
    {
        return DB::table('KPI_Pengajuan as p')
            ->join('KPI_Pengajuan_Detail as pd', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->join('KPI_Pengajuan_Det as pdet', 'pdet.Id_Pengajuan_Detail', '=', 'pd.Id_Pengajuan_Detail')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'p.Id_User')
            ->where('k.Aktif', 'Y')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNull('p.Status')
            ->where('p.Flag_Approval', 'Y')
            // Skip pengajuan KPI TIM (Flag_Team='Y', diisi via leader/sender).
            // Pakai kondisi yg juga menerima NULL/'T' (pengajuan individual).
            // ->where(function ($q) {
            //     $q->where('p.Flag_Team', '<>', 'Y')->orWhereNull('p.Flag_Team');
            // })
            ->where('pdet.Periode', 'like', $periode.'%')
            ->whereNotExists(function ($q) use ($periode) {
                $q->select(DB::raw(1))
                    ->from('KPI_Update_Performance as up')
                    ->whereColumn('up.Id_User', 'p.Id_User')
                    ->where('up.Flag_Aktif', 'Y')
                    // Record dibatalkan (Status='Y') tidak dianggap "sudah mengisi"
                    // -> hanya update aktif (Status NULL) yang mengeksklusi notif.
                    ->whereNull('up.Status')
                    ->where('up.Periode', 'like', $periode.'%');
            })
            ->distinct()
            ->get(['k.Kode_Karyawan', 'k.Nama', 'k.HP', 'p.No_Transaksi', 'p.Id_User']);
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $datetime = FormatTanggalHelper::getCurrentTime('kpiNotif');

        $this->info("Memulai Pengiriman Notifikasi untuk Tanggal {$datetime->toDateString()}.");
        Log::channel('kpiNotifUpdate')->info(
            "Memulai Pengiriman Notifikasi untuk Tanggal {$datetime->toDateString()}.",
        );

        $notificationService = app(\App\Services\SYSTEM\Notification\NotificationService::class);

        try {
            // Bandingkan berbasis TANGGAL (abaikan jam): jadwal aktif bila tanggal
            // sekarang berada di antara tanggal Jadwal_Mulai..Jadwal_Akhir. Mencegah
            // jadwal tersaring hanya karena jam mulai (mis. 11:22) > jam "sekarang"
            // (mis. 00:00 dari KPI_Get_Date_Dummy) pada tanggal yang sama.
            $tanggalIni = $datetime->toDateString();
            /* Rentang, bukan CAST pada kolomnya: CAST(Jadwal_Mulai AS date)
               mematikan pemakaian indeks di kolom itu. Maknanya dijaga sama --
               "bagian tanggal Jadwal_Mulai <= hari ini" setara dengan
               "Jadwal_Mulai < besok", dan batas atasnya eksklusif. */
            $besok = $datetime->copy()->startOfDay()->addDay();

            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Jadwal_Mulai', '<', $besok)
                ->where('Jadwal_Akhir', '>=', $datetime->copy()->startOfDay())
                ->where('Flag_Penilaian_KPI', 'Y')
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $jadwal) {
                $this->warn("Tidak ada Jadwal Update Performance pada {$tanggalIni}");
                Log::channel('kpiNotifUpdate')->info("Tidak ada Jadwal Update Performance pada {$tanggalIni}");

                return 0;
            }
            $start = Carbon::parse($jadwal->Jadwal_Mulai);
            $end = Carbon::parse($jadwal->Jadwal_Akhir);

            $nowNotif = $datetime->clone()->startOfDay();
            $notif1 = $start->copy()->startOfDay();
            $notif2 = $start
                ->copy()
                ->addDays(floor($start->diffInDays($end) / 2))
                ->startOfDay();
            $notif3 = $end->copy()->subDay()->startOfDay();

            if (! $nowNotif->equalTo($notif1) && ! $nowNotif->equalTo($notif2) && ! $nowNotif->equalTo($notif3)) {
                $this->warn(
                    "Tidak ada Jadwal notifikasi pada tanggal {$datetime->toDateString()} dengan {$notif1},{$notif2}, {$notif3}",
                );
                Log::channel('kpiNotifUpdate')->info(
                    "Tidak ada Jadwal notifikasi pada tanggal {$datetime->toDateString()} dengan {$notif1},{$notif2}, {$notif3}",
                );

                return 0;
            }

            $notifPoint = match (true) {
                $nowNotif->equalTo($notif1) => 1,
                $nowNotif->equalTo($notif2) => 2,
                $nowNotif->equalTo($notif3) => 3,
                default => 1,
            };

            $tanggalCustom = null;
            $tanggalCustomNotif = DB::table('KPI_Get_Date_Dummy')
                ->where('Flag_Aktif', 'Y')
                ->where('Jenis', 'All')
                ->orderBy('Id_Get_Date', 'DESC')
                ->first();

            if ($tanggalCustomNotif && ! empty($tanggalCustomNotif->Tanggal)) {
                $tanggalCustom = Carbon::parse($tanggalCustomNotif->Tanggal);
            }
            // dd($tanggalCustom);

            // Periode = periode penilaian dari jadwal yang SEDANG berlangsung
            // ($jadwal->Periode), bukan bulan lalu. Konsisten dengan halaman Update
            // Performance (UpdatePerformanceController::getDynamicPeriod). Override
            // KPI_Get_Date_Dummy (testing) tetap diutamakan bila ada.
            $periodeBase = $tanggalCustom ? Carbon::parse($tanggalCustom) : Carbon::parse($jadwal->Periode);
            $periode = $periodeBase->format('Y-m');
            $periodeText = $periodeBase->format('F Y');

            // Sasaran notif = karyawan yang:
            //  (a) punya pengajuan parameter APPROVED (p.Flag_Approval='Y', aktif),
            //  (b) punya MINIMAL SATU target (KPI_Pengajuan_Det) pada periode berjalan
            //      -> INNER JOIN pd/pdet memastikan yg tak punya parameter / tak punya
            //         target periode ini otomatis tersaring keluar,
            //  (c) BELUM mengisi update performance untuk periode tsb.
            //
            // "Belum mengisi" dideteksi via KEBERADAAN baris KPI_Update_Performance
            // aktif untuk (Id_User, Periode) — TANPA menyaring approval. Sehingga user
            // yang sudah submit & menunggu approval pun ikut tereksklusi (sudah mengisi).
            // pdet.Periode dicocokkan dgn LIKE 'Y-m%' karena bisa tersimpan sbg tanggal
            // penuh (mis. 2026-06-01), konsisten dgn resolveRelevantPeriodDetail.
            $Pengajuan = self::sasaranPengingat($periode);

            $pengajuanGrouped = collect($Pengajuan)->groupBy('Kode_Karyawan')->toArray();
            // dd($pengajuanGrouped);

            // ── Filter skip list: karyawan PERMANENT atau skip periode ini
            $skipList = DB::table('N_HRIS_KPI_Notif_Skip')
                ->where('Flag_Aktif', 'Y')
                ->where(function ($q) use ($periode) {
                    $q->where('Tipe_Skip', 'PERMANENT')->orWhere(function ($q2) use ($periode) {
                        $q2->where('Tipe_Skip', 'PERIODE')->where('Periode', $periode);
                    });
                })
                ->pluck('Kode_Karyawan')
                ->toArray();

            if (! empty($skipList)) {
                $skipped = array_keys(
                    array_filter($pengajuanGrouped, fn ($kode) => in_array($kode, $skipList), ARRAY_FILTER_USE_KEY),
                );
                if (! empty($skipped)) {
                    Log::channel('kpiNotifUpdate')->info('Karyawan di-skip dari notifikasi KPI Update Performance.', [
                        'kode_karyawan' => $skipped,
                        'periode' => $periode,
                    ]);
                }
                $pengajuanGrouped = array_filter(
                    $pengajuanGrouped,
                    fn ($kode) => ! in_array($kode, $skipList),
                    ARRAY_FILTER_USE_KEY,
                );
            }

            // ── Deadline per karyawan = MAX(Jadwal_Akhir) di antara SEMUA jadwal
            // penilaian (Flag_Penilaian_KPI='Y') PERIODE yang SAMA yang MENCAKUP
            // karyawan tsb. Untuk satu periode bisa ada >1 jadwal dgn batas berbeda
            // (mis. perorangan 11 Jun & global 30 Jun) -> ambil yg paling akhir.
            // Catatan: TIDAK menyaring Flag_Aktif (sesuai kebutuhan), TIDAK syarat
            // tanggal sekarang di antara Mulai..Akhir (cukup Periode sama).
            // Dihitung SEKALI (hindari N+1): 1 query global + 1 query perorangan.
            $kodeSasaran = array_keys($pengajuanGrouped);
            $deadlineMap = [];
            if (count($kodeSasaran) > 0) {
                // Global (Flag_Perorangan='T') berlaku utk SEMUA karyawan: ambil 1 nilai MAX.
                $deadlineGlobal = DB::table('KPI_Master_Penjadwalan')
                    ->where('Flag_Penilaian_KPI', 'Y')
                    ->where('Flag_Perorangan', 'T')
                    ->where('Periode', 'like', $periode.'%')
                    ->max('Jadwal_Akhir');

                // Perorangan (Flag_Perorangan='Y'): MAX per Kode_Karyawan yg terdaftar.
                $deadlinePerorangan = DB::table('KPI_Master_Penjadwalan as j')
                    ->join(
                        'N_KPI_Master_Penjadwalan_Perorangan as pp',
                        'pp.Id_Master_Penjadwalan',
                        '=',
                        'j.Id_Master_Penjadwalan',
                    )
                    ->where('j.Flag_Penilaian_KPI', 'Y')
                    ->where('j.Flag_Perorangan', 'Y')
                    ->where('j.Periode', 'like', $periode.'%')
                    ->where('pp.Flag_Aktif', 'Y')
                    ->whereIn('pp.Kode_Karyawan', $kodeSasaran)
                    ->groupBy('pp.Kode_Karyawan')
                    ->select('pp.Kode_Karyawan', DB::raw('MAX(j.Jadwal_Akhir) as Deadline'))
                    ->pluck('Deadline', 'pp.Kode_Karyawan');

                foreach ($kodeSasaran as $kode) {
                    $candidates = [];
                    if ($deadlineGlobal) {
                        $candidates[] = Carbon::parse($deadlineGlobal);
                    }
                    if (isset($deadlinePerorangan[$kode]) && $deadlinePerorangan[$kode]) {
                        $candidates[] = Carbon::parse($deadlinePerorangan[$kode]);
                    }
                    if ($candidates) {
                        $deadlineMap[$kode] = collect($candidates)->max();
                    }
                }
            }

            if (count($pengajuanGrouped) > 0) {
                foreach ($pengajuanGrouped as $employee) {
                    $dataEmployee = collect($employee)->first();
                    // Identitas (Kode_Karyawan/Nama/HP) sudah ikut di hasil query
                    // (join Karyawan), tak perlu query Karyawan lagi per karyawan.
                    $user = $dataEmployee;

                    // Deadline jadwal untuk karyawan ini (paling akhir antar-jadwal
                    // periode sama). Fallback ke jadwal guard bila—anomali—tak ketemu.
                    $deadlineKaryawan = (
                        $deadlineMap[$dataEmployee->Kode_Karyawan] ?? Carbon::parse($jadwal->Jadwal_Akhir)
                    )->format('d F Y');

                    // Notifikasi in-app (topbar) — kanal terpisah dari WA. Dibuat
                    // SEBELUM cek HP agar reminder topbar tetap muncul walau No HP
                    // kosong. upsertByKey (key per karyawan+periode) mencegah double
                    // notif lintas jadwal kirim (notif1/notif2/notif3) & menghormati
                    // notif yg sudah di-dismiss user.
                    try {
                        $notificationService->upsertByKey([
                            'kode_karyawan' => $dataEmployee->Kode_Karyawan,
                            'type' => 'KPI_UPDATE_PERFORMANCE_REMINDER',
                            'title' => 'Reminder Update Performance KPI',
                            'message' => "Anda belum mengisi Update Performance KPI periode {$periodeText}. ".
                                "Segera lengkapi sebelum {$deadlineKaryawan}.",
                            'module' => 'HCIS',
                            'severity' => 'warning',
                            'action_url' => '/update-performance',
                            'reference_id' => $dataEmployee->No_Transaksi,
                            'notification_key' => 'KPI_UPDATE_PERF_REMINDER|'.$dataEmployee->Kode_Karyawan.'|'.$periode.'|notif-'.$notifPoint,
                            'channel' => \App\Services\SYSTEM\Notification\NotificationService::DELIVERY_IN_APP,
                            // WAJIB: topbar (AppTopbar.vue) memfilter source='MAIN_NOTIF'.
                            // Tanpa ini notifikasi tersimpan tapi tak muncul di bel topbar.
                            'source' => 'MAIN_NOTIF',
                            'created_by' => 'SYSTEM',
                            'priority' => 3,
                        ]);
                    } catch (\Throwable $eNotif) {
                        Log::channel('kpiNotifUpdate')->warning(
                            'Gagal membuat notifikasi in-app reminder Update Performance: '.$eNotif->getMessage(),
                            ['kode_karyawan' => $dataEmployee->Kode_Karyawan],
                        );
                    }

                    if (! $user || ! $user->HP || $user->HP == '' || ! $user->Nama) {
                        Log::channel('kpiNotifUpdate')->error(
                            'Karyawan tidak punya No HP/Nama! Karyawan : '.$dataEmployee->Kode_Karyawan,
                        );

                        continue;
                    }

                    $pesan = [
                        'messaging_product' => 'whatsapp',
                        'to' => $user->HP,
                        'type' => 'template',
                        'template' => [
                            'name' => 'kpi_notif_update_performance',
                            'language' => [
                                'code' => 'id',
                                'policy' => 'deterministic',
                            ],
                            'components' => [
                                [
                                    'type' => 'body',
                                    'parameters' => [
                                        ['type' => 'text', 'text' => $user->Nama],
                                        ['type' => 'text', 'text' => "periode {$periodeText}"],
                                        ['type' => 'text', 'text' => $deadlineKaryawan],
                                    ],
                                ],
                            ],
                        ],
                    ];

                    // Kirim WA via QUEUE (Cloud Tasks), bukan sinkron. Dulu sendAndLog
                    // dipanggil inline di loop ratusan karyawan → request belasan menit
                    // & dibunuh di ~300s. Kini 1 job/karyawan: command hanya men-dispatch
                    // (cepat) dan tiap pengiriman WA jalan di handler queue terpisah.
                    // Logging + insert Whatsapp_Log tetap ditangani di dalam job
                    // (WhatsappLogHelper::sendAndLog).
                    SendKpiNotifWhatsappJob::dispatch(
                        (string) $user->Kode_Karyawan,
                        (string) $user->Nama,
                        (string) $user->HP,
                        $dataEmployee->No_Transaksi !== null ? (string) $dataEmployee->No_Transaksi : null,
                        $pesan,
                    )->onQueue(config('queue.connections.cloudtasks.queue_kpi_notif'));
                }
            }
        } catch (\Exception $e) {
            Log::channel('kpiNotifUpdate')->error('Kesalahan umum selama proses notifikasi Kpi Update Performance.', [
                'tanggal_proses' => $datetime->toDateString(),
                'pesan_pengecualian' => $e->getMessage(),
                'kode_pengecualian' => $e->getCode(),
                'trace_pengecualian' => $e->getTraceAsString(),
            ]);

            return 1;
        }

        $this->info("Proses notifikasi Kpi Update Performance selesai untuk tanggal {$datetime->toDateString()}.");
        Log::channel('kpiNotifUpdate')->info(
            "Proses notifikasi Kpi Update Performance selesai untuk tanggal {$datetime->toDateString()}.",
        );

        return 0;
    }
}
