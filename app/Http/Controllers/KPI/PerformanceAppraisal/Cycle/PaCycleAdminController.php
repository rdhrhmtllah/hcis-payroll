<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Cycle;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentBuilderService;
use App\Services\KPI\PerformanceAppraisal\PaAssignmentHealthService;
use App\Services\KPI\PerformanceAppraisal\PaCalibrationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleGate;
use App\Services\KPI\PerformanceAppraisal\PaCyclePillarWeightService;
use App\Services\KPI\PerformanceAppraisal\PaCyclePreparationService;
use App\Services\KPI\PerformanceAppraisal\PaCycleRaterPolicyService;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupService;
use App\Services\KPI\PerformanceAppraisal\PaCycleSetupStepService;
use App\Services\KPI\PerformanceAppraisal\PaCycleStepService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnKaryawanSyncService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnScheduleService;
use App\Services\KPI\PerformanceAppraisal\PaKategoriGugurException;
use App\Services\KPI\PerformanceAppraisal\PaKategoriTidakDiikutkanService;
use App\Services\KPI\PerformanceAppraisal\PaKelayakanMasaKerjaService;
use App\Services\KPI\PerformanceAppraisal\PaKesimpulanKualitatifService;
use App\Services\KPI\PerformanceAppraisal\PaKirimanTinjauService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantEditService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantOverrideService;
use App\Services\KPI\PerformanceAppraisal\PaParticipantReadService;
use App\Services\KPI\PerformanceAppraisal\PaPenutupanSiklusService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingService;
use App\Services\KPI\PerformanceAppraisal\PaRaterSubmitService;
use App\Services\KPI\PerformanceAppraisal\PaResultCalculationService;
use App\Services\KPI\PerformanceAppraisal\PaResultHistoryService;
use App\Services\KPI\PerformanceAppraisal\PaResultNoteService;
use App\Services\KPI\PerformanceAppraisal\PaResultPublishService;
use App\Services\KPI\PerformanceAppraisal\PaRevisiPesertaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pintu HTTP modul Performance Appraisal.
 *
 * Controller ini sengaja tipis. Seluruh aturan — status siklus mana yang mengizinkan
 * apa, bobot yang wajib berjumlah 100, siapa yang boleh menilai siapa — hidup di
 * service, dan sudah punya ujinya sendiri. Menyalin sepotong pun ke sini akan
 * menciptakan pintu kedua yang harus dijaga sama persis, dan modul SOP sudah pernah
 * membayar mahal untuk pelajaran itu.
 *
 * Setiap kegagalan aturan datang sebagai RuntimeException dengan pesan yang memang
 * ditujukan kepada manusia. Pesannya diteruskan apa adanya sebagai 422: menggantinya
 * dengan "terjadi kesalahan" akan membuang satu-satunya keterangan yang berguna.
 */
class PaCycleAdminController extends Controller
{
    /**
     * Berapa peserta dihitung dalam satu permintaan bila layar tidak menyebutkan.
     *
     * Kecil dengan sengaja, dan angkanya DIUKUR bukan ditebak: 5 peserta memakan 6,0 detik
     * pada database pengembangan 3 September 2026 — sekitar 1,2 detik per orang. Sepuluh
     * berarti ±12 detik, yang selesai dengan selamat bahkan pada tautan paling lambat.
     *
     * Angka ini akan berubah bersama latensi, bukan bersama kode. Kalau suatu saat terasa
     * lambat, ukur dulu `SELECT 1` ke database sebelum menuduh perhitungannya melambat.
     */
    private const BAKU_HITUNG_SEKALI = 10;

    /**
     * Plafon keras, bahkan bila layar meminta lebih.
     *
     * Tanpa plafon, satu angka besar yang diketik di layar mengembalikan persis masalah
     * yang sedang diperbaiki — dan kali ini atas permintaan pemakainya sendiri, sehingga
     * lebih sulit dikenali sebagai cacat.
     */
    private const MAKS_HITUNG_SEKALI = 100;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaCyclePreparationService $penyiapan,

        /* Catatan hasil yang ditulis admin. Dipisahkan dari layanan perhitungan: yang satu
           membentuk catatan dari jawaban penilai, yang ini menerima tulisan manusia — dan
           keduanya tidak boleh saling menghapus. */
        private readonly PaResultNoteService $catatanHasil,
        private readonly PaAssignmentBuilderService $penugasan,
        private readonly PaResultCalculationService $perhitungan,
        private readonly PaParticipantReadService $pembaca,
        private readonly PaCalibrationService $kalibrasi,
        private readonly PaResultPublishService $penerbitan,
        private readonly PaParticipantOverrideService $penyimpangan,
        private readonly PaCyclePillarWeightService $bobotPilar,
        private readonly PaParticipantEditService $suntingPeserta,
        private readonly PaCycleSetupService $penyiapanSiklus,
        private readonly PaCycleStepService $alurLangkah,
        private readonly PaPenutupanSiklusService $penutupan,
        private readonly PaCycleSetupStepService $langkahSetup,
        private readonly PaCycleRaterPolicyService $aturanPenilai,
        private readonly PaHcLearnCatalogService $katalogHcLearn,
        private readonly PaQuestionBindingService $pengikatan,
        private readonly PaQuestionBindingMasterService $ikatanBaku,
        private readonly PaAssignmentHealthService $kesehatanPenugasan,
        private readonly PaHcLearnScheduleService $penjadwalanHcLearn,

        /* Kesiapan karyawan di HC Learn. Dipisahkan dari penjadwalan karena ia menjawab
           pertanyaan yang berbeda dan pada waktu yang berbeda: penjadwalan menerbitkan
           pintu, yang ini memastikan orangnya dikenal SEBELUM pintu diminta. */
        private readonly PaHcLearnKaryawanSyncService $karyawanHcLearn,

        /* Pemetaan ulang kiriman yang sudah masuk. Layanannya yang memeriksa kiriman itu
           memang milik siklus ini — controller tidak boleh memutuskan kepemilikan sendiri. */
        private readonly PaKirimanTinjauService $kirimanTinjau,

        /* Kurva grade siklus KURVA: kunci, buka, pindah metode, grup siklus. */
        private readonly PaKurvaService $kurva,

        /* Minimal masa kerja siklus (DDL-2026-191), disunting di Langkah 4. */
        private readonly PaKelayakanMasaKerjaService $kelayakanMasaKerja,
    ) {}

    public function index()
    {
        return inertia('KPI/PerformanceAppraisal/Cycle/siklus');
    }

    /**
     * Daftar siklus beserta cacah pesertanya.
     *
     * Cacahnya dihitung satu kueri untuk semua siklus. Menghitungnya per baris akan
     * menghasilkan N+1 yang tak terlihat sampai siklusnya banyak.
     */
    public function daftarSiklus(Request $request)
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Tahun')
            ->orderByDesc('Id_PA_Cycle')
            ->get([
                'Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Status_Cycle',
                'Periode_Dinilai_Dari', 'Periode_Dinilai_Sampai',
                'Pengisian_Dari', 'Pengisian_Sampai', 'Locked_At', 'Published_At',
                'Langkah_Setup_Aktif',
            ]);

        if ($siklus->isEmpty()) {
            return ResponseHelper::success(['siklus' => []], 'Belum ada siklus.');
        }

        $cacah = DB::table('N_PA_Participant')
            ->whereIn('PA_Cycle_Id', $siklus->pluck('Id_PA_Cycle'))
            ->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Cycle_Id', 'Status_Peserta')
            ->select(['PA_Cycle_Id', 'Status_Peserta', DB::raw('COUNT(*) AS jumlah')])
            ->get()
            ->groupBy('PA_Cycle_Id');

        /* Keadaan langkah penyiapan, SATU kueri untuk semua siklus.

           Tanpa ini layar memetakan status -> aksi sendiri, dan petanya berbeda dari aturan
           server: siklus PREPARED yang wizard-nya belum selesai akan diarahkan KELUAR dari
           wizard ke halaman berikutnya, dan admin kehilangan jalan menyelesaikan setelan
           yang belum diisi tanpa tahu apa yang terlewat. */
        $langkah = DB::table('N_PA_Cycle_Setup_Step')
            ->whereIn('PA_Cycle_Id', $siklus->pluck('Id_PA_Cycle'))
            ->get(['PA_Cycle_Id', 'Kode_Langkah', 'Status_Langkah'])
            ->groupBy('PA_Cycle_Id');

        $totalLangkah = count(PaCycleSetupStepService::kodeLangkah());

        $cacahKecuali = $this->penyiapan->cacahDikecualikan(
            $siklus->pluck('Id_PA_Cycle')->map(fn ($x) => (int) $x)->all(),
        );

        $hasil = $siklus->map(function ($s) use ($cacah, $langkah, $totalLangkah, $cacahKecuali) {
            $baris = $cacah->get($s->Id_PA_Cycle, collect());
            $step = $langkah->get($s->Id_PA_Cycle, collect());

            /* Langkah PESERTA tidak dihitung sebagai syarat: ia yang MENJALANKAN penyiapan,
               dan menuntutnya selesai lebih dulu membuat tombolnya tak pernah menyala. */
            /* Aturannya milik `PaCycleSetupStepService`, tidak disusun ulang di sini.
               Versi lama menghitungnya sendiri: MENGECUALIKAN `PESERTA` tetapi IKUT
               MENGHITUNG `PENUGASAN`, dengan total "jumlah langkah dikurangi satu" — dua
               aturan yang berselisih tentang langkah mana yang dimaksud. Karena `PENUGASAN`
               tidak pernah ditandai selesai oleh siapa pun, hitungannya mentok SELAMANYA dan
               admin yang sudah selesai tetap didorong kembali ke wizard. */
            $ringkas = PaCycleSetupStepService::ringkasKonfigurasi($step);

            return (array) $s + [
                'peserta' => [
                    'diikutkan' => (int) $baris->firstWhere('Status_Peserta', 'DIIKUTKAN')?->jumlah,
                    /* Hanya yang DIPUTUSKAN ORANG. Menggabungkannya dengan pengecualian
                       otomatis (level FREELANCE, karyawan yang resign) membuat admin yang
                       mengecualikan dua orang membaca angka 101 — dan menyimpulkan ia tak
                       sengaja mengeluarkan 99 orang. Aturannya milik
                       `PaCyclePreparationService::cacahDikecualikan()`. */
                    'dikecualikan' => $cacahKecuali[(int) $s->Id_PA_Cycle]['manual'] ?? 0,
                    'dikecualikan_otomatis' => $cacahKecuali[(int) $s->Id_PA_Cycle]['otomatis'] ?? 0,
                    'tertunda' => (int) $baris->firstWhere('Status_Peserta', 'TERTUNDA')?->jumlah,
                ],
                // Layar tidak perlu menebak tombol mana yang hidup; gerbangnya yang
                // menjawab, dan jawabannya sama dengan yang ditegakkan saat ditekan.
                // Statusnya sudah ikut terbaca di kueri daftar, jadi gerbang dijawab
                // dari status itu - menanyakannya per baris berarti satu kueri tambahan
                // untuk jawaban yang sudah ada di tangan.
                'operasi_boleh' => $this->gate->operasiUntukStatus((string) $s->Status_Cycle),

                /* Dipakai layar untuk memutuskan apakah baris ini masih harus kembali ke
                   wizard. Aturannya milik server; layar hanya membacanya. */
                'setup' => [
                    'selesai' => $ringkas['selesai'],
                    'total' => $ringkas['total'],
                    'langkah_aktif' => $s->Langkah_Setup_Aktif,
                    'lengkap' => $ringkas['lengkap'],

                    /* Jumlah SELURUH langkah wizard, termasuk yang bukan konfigurasi. Layar
                       memerlukan keduanya: yang satu untuk "sudah boleh maju?", yang lain
                       untuk menyebut jumlah langkah dengan benar. */
                    'total_langkah' => $totalLangkah,
                ],
            ];
        });

        return ResponseHelper::success(['siklus' => $hasil], 'Berhasil');
    }

    /**
     * Enam halaman yang MENGIKUTI ALUR MOCKUP.
     *
     * Sampai 3 September 2026 keenamnya adalah dialog di dalam `siklus.vue` — satu layar
     * 1.578 baris menampung sembilan dialog untuk sebelas pekerjaan berbeda. Mockup
     * merancangnya sebagai halaman terpisah dalam tiga grup, dan itu memang bentuk yang
     * benar: admin menjalankan siklus sekali-dua kali setahun dan tidak mungkin mengingat
     * dialog mana menyimpan apa.
     *
     * Endpoint datanya SUDAH ADA sejak lama; yang kurang hanya halamannya. Karena itu
     * keenam method di bawah tipis — mereka hanya membuka layar, dan seluruh datanya
     * diambil layar lewat endpoint yang sudah berkontrak dan berpenjaga uji.
     */
    public function dashboardAdmin()
    {
        return inertia('KPI/PerformanceAppraisal/Ringkasan/dashboard');
    }

    /** Kalibrasi Forum digantikan Balancing (B7); alamat lama tetap hidup sebagai pengalih. */
    public function halamanKalibrasi(Request $request)
    {
        $cycleId = $request->query('cycle') ?? $request->query('cycle_id');

        return redirect()->route('performance-appraisal.balancing', $cycleId ? ['cycle' => $cycleId] : []);
    }

    public function halamanHavMap()
    {
        return inertia('KPI/PerformanceAppraisal/Hasil/hav-map');
    }

    public function halamanDistribusi()
    {
        return inertia('KPI/PerformanceAppraisal/Hasil/distribusi');
    }

    public function halamanRiwayat()
    {
        return inertia('KPI/PerformanceAppraisal/Hasil/riwayat');
    }

    /**
     * Fase PENGISIAN — memantau siapa sudah mengisi dan siapa terlambat.
     *
     * Halaman terpisah, bukan tab, karena pekerjaannya berbeda waktu dan sering berbeda
     * orang: menyiapkan siklus dilakukan sekali di awal, memantau berlangsung
     * berminggu-minggu. Yang memantau tidak perlu melihat tombol "kunci siklus" yang tak
     * akan ia sentuh sepanjang tahun.
     */
    /**
     * Halaman penyiapan berlangkah satu siklus.
     *
     * Siklusnya ada di ALAMAT, bukan di pemilih di dalam halaman. Menyiapkan adalah
     * pekerjaan atas satu siklus tertentu, dan pemilih di dalam halaman membuat admin bisa
     * berganti siklus di tengah wizard tanpa menyadarinya — lalu menyimpan setelan siklus A
     * ke siklus B.
     */
    public function halamanPenyiapan(int $cycleId)
    {
        /* Apakah admin ini boleh membuka master relasi organisasi.
         *
         * Sejak DML-2026-099 butir menunya SUDAH ADA di modul KPI, header Master Data. Jadi
         * panel di layar bukan lagi satu-satunya jalan ke sana; ia menjawab pertanyaan lain —
         * kenapa aturan penilai yang sudah benar tetap menghasilkan penugasan kosong.
         *
         * Haknya diputuskan di SERVER. Tautan yang berujung 403 lebih buruk daripada tidak ada
         * tautan: ia menyalahkan penggunanya atas hak yang memang tidak pernah ia punya.
         *
         * Dua kode halaman diperiksa karena gerbang rutenya menerima dua: butir SYSTEM dan
         * butir KPI menunjuk halaman yang sama. Memeriksa satu saja membuat panel ini hilang
         * bagi orang yang sebenarnya berhak lewat kode yang lain.
         */
        $user = Auth::user();
        $bisaKelolaRelasi = $user !== null && (
            ($user->Is_System_Admin ?? ($user->is_system_admin ?? null)) === 'Y'
            || $user->memilikiJabatan('systemRelasiOrganisasiPage')
            || $user->memilikiJabatan('PaMasterRelasiOrganisasiPage')
        );

        return inertia('KPI/PerformanceAppraisal/Cycle/penyiapan', [
            'cycleId' => $cycleId,
            'bisaKelolaRelasi' => $bisaKelolaRelasi,
        ]);
    }

    public function pemantauan(Request $request)
    {
        /* Hak per FASE milik pengguna ini. Halaman pemantauan hanya menuntut
         * `PaPemantauanPage`, tetapi memuat tombol milik fase lain: "Petakan Ulang" digerbangi
         * `PerformanceAppraisalPage`, "Lepas paksa" dan "Hitung Ulang" digerbangi `PaHasilPage`.
         * Tanpa ini pemegang izin pemantauan saja melihat tombol yang selalu berujung 403.
         * Diputuskan dengan `memilikiJabatan()` — aturan yang sama dengan middleware rutenya.
         */
        $user = $request->user();

        return inertia('KPI/PerformanceAppraisal/Cycle/pemantauan', [
            'akses' => [
                'penyiapan' => $user !== null && $user->memilikiJabatan('PerformanceAppraisalPage'),
                'pemantauan' => $user !== null && $user->memilikiJabatan('PaPemantauanPage'),
                'hasil' => $user !== null && $user->memilikiJabatan('PaHasilPage'),
            ],
        ]);
    }

    /**
     * Fase HASIL — menghitung, mengkalibrasi, menerbitkan.
     *
     * Dipisahkan karena ketiganya mengubah ANGKA YANG SAMPAI KE KARYAWAN. Menaruhnya satu
     * klik dari "siapkan peserta" membuat tindakan paling berkonsekuensi di modul ini
     * berada di tempat yang sama dengan pekerjaan rutin.
     */
    public function hasil()
    {
        return inertia('KPI/PerformanceAppraisal/Cycle/hasil');
    }

    /**
     * Pratinjau penyiapan — lihat dulu, baru putuskan.
     *
     * READ-ONLY. Ia menjawab pertanyaan yang selama ini hanya bisa dijawab dengan
     * menjalankan penyiapan sungguhan: berapa yang akan diikutkan, siapa yang akan
     * dikecualikan dan kenapa, dan LEVEL MANA yang belum punya aturan penilai sehingga
     * orangnya akan mendarat di keranjang TERTUNDA.
     */
    public function pratinjauPenyiapan(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penyiapan->pratinjauPenyiapan($cycleId));
    }

    /** Apa yang berubah sejak peserta dan penugasannya dibentuk (Langkah 4 & 5). Hanya membaca. */
    public function perubahanPeserta(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penyiapan->perubahanPeserta($cycleId));
    }

    /** Batalkan penugasan (yang belum diisi) peserta tertahan Level PA, lalu segarkan levelnya. */
    public function segarkanLevelTertahan(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1', 'max:500'],
            'participant_ids.*' => ['integer'],
        ]);

        return $this->jalankan(fn () => $this->penyiapan->segarkanLevelTertahan($cycleId, $data['participant_ids'], $this->userId()));
    }

    /**
     * Daftar pengecualian awal — siapa yang tidak akan dinilai, ditulis SEBELUM peserta
     * dibentuk.
     */
    /** Minimal masa kerja siklus beserta apakah masih boleh diubah. */
    public function kelayakanMasaKerja(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->kelayakanMasaKerja->untukSiklus($cycleId));
    }

    /** Hanya sebelum peserta pertama kali dibentuk — sesudahnya ditolak service. */
    public function simpanKelayakanMasaKerja(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'min_masa_kerja_bulan' => ['required', 'integer', 'min:0', 'max:'.PaKelayakanMasaKerjaService::MAKS_BULAN],
        ]);

        return $this->jalankan(fn () => $this->kelayakanMasaKerja->simpanSiklus(
            $cycleId, (int) $data['min_masa_kerja_bulan'], $this->userId(),
        ));
    }

    public function daftarPraKecuali(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => [
            'baris' => $this->penyiapan->daftarPraKecuali($cycleId),
        ]);
    }

    public function tambahPraKecuali(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'max:500'],
            'orang' => ['required', 'array', 'min:1'],
            'orang.*.kode_perusahaan' => ['required', 'string', 'max:3'],
            'orang.*.kode_karyawan' => ['required', 'string', 'max:255'],
        ]);

        return $this->jalankan(fn () => $this->penyiapan->tambahPraKecuali(
            $cycleId, $data['orang'], $data['alasan'], $this->userId(),
        ));
    }

    public function cabutPraKecuali(Request $request, int $cycleId, int $id)
    {
        return $this->jalankan(function () use ($cycleId, $id) {
            $this->penyiapan->cabutPraKecuali($cycleId, $id, $this->userId());

            return ['dicabut' => $id];
        });
    }

    /**
     * Mencabut beberapa baris pengecualian sekaligus (centang di Langkah 4). Jawabannya membawa
     * langkah berikutnya: Jalankan Ulang, atau lengkapi penugasan di Langkah 5.
     */
    public function cabutPraKecualiBanyak(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
        ]);

        return $this->jalankan(fn () => $this->penyiapan->cabutPraKecualiBanyak($cycleId, $data['ids'], $this->userId()));
    }

    /* ------------------------------------------------------------------ *
     * Alur penyiapan berlangkah                                           *
     * ------------------------------------------------------------------ */

    /**
     * Keadaan seluruh langkah penyiapan satu siklus.
     *
     * Satu panggilan, bukan satu per langkah: layar wizard menggambar seluruh penunjuk
     * posisinya sekaligus, dan meminta empat panggilan untuk itu membuat penunjuknya
     * terisi sepotong-sepotong di depan mata admin.
     */
    public function keadaanSetup(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->langkahSetup->keadaan($cycleId));
    }

    /**
     * Menerima usulan master untuk satu sel.
     *
     * Satu klik pada sel kosong, tanpa membuka dialog pemilih. Usulan yang harus dicari
     * sendiri di halaman lain bukan bawaan — ia hanya keterangan yang kebetulan ada.
     */
    public function terimaUsulanPaketSoal(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_level' => ['required', 'integer'],
            'rater_category_id' => ['nullable', 'integer'],
        ]);

        return $this->jalankan(fn () => $this->ikatanBaku->terimaUsulan(
            $cycleId,
            (int) $data['id_level'],
            isset($data['rater_category_id']) ? (int) $data['rater_category_id'] : null,
            $this->userId(),
        ));
    }

    /**
     * Kesehatan penugasan penilai: apa yang harus diselesaikan sebelum siklus dikunci.
     *
     * Membandingkan aturan penilai dengan kenyataan. Yang tidak terpenuhi tidak pernah
     * dilaporkan sendiri — pesertanya tetap DIIKUTKAN, penugasannya kurang, dan angkanya
     * baru terlihat saat hasilnya dihitung dan sebagian orang tertahan tanpa sebab jelas.
     */
    public function kesehatanPenugasan(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->kesehatanPenugasan->ringkasan($cycleId));
    }

    /**
     * Peserta yang penugasannya bermasalah, untuk ditindaklanjuti satu per satu.
     */
    public function pesertaPenugasanBermasalah(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_level' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
            'hanya_bermasalah' => ['nullable', 'boolean'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return $this->jalankan(fn () => $this->kesehatanPenugasan->pesertaBermasalah($cycleId, $data));
    }

    /**
     * Menyalin seluruh pilihan paket baku ke siklus ini.
     *
     * Butirnya diambil dari HC Learn SEKARANG, bukan dari yang tercatat master. Sel yang
     * bermasalah dilewati dan dilaporkan — memaksakannya berarti membawa cacat yang sudah
     * diketahui ke dalam siklus yang akan dinilai orang.
     */
    public function salinPaketSoalDariMaster(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->ikatanBaku->salinKeSiklus($cycleId, $this->userId()));
    }

    /**
     * Matriks pengikatan paket soal: baris level, kolom kategori penilai.
     *
     * Inilah "RATER x Level" yang dimaksud HC. `total_lubang` adalah angka yang paling
     * perlu ditonjolkan layar: sel yang penilainya ADA tetapi paketnya tidak — penilai di
     * sel itu akan membuka formulir kosong tanpa satu pun pesan.
     */
    public function matriksPaketSoal(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->pengikatan->matriks($cycleId));
    }

    /**
     * Mengikat paket yang SUDAH ADA di siklus ini ke satu sel (level x kategori).
     *
     * `rater_category_id` kosong menjadikannya paket bawaan level itu.
     */
    public function ikatPaketSoal(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'paket_id' => ['required', 'integer'],
            'rater_category_id' => ['nullable', 'integer'],
        ]);

        return $this->jalankan(fn () => $this->pengikatan->ikat(
            $cycleId,
            (int) $data['paket_id'],
            isset($data['rater_category_id']) ? (int) $data['rater_category_id'] : null,
            $this->userId(),
        ));
    }

    /**
     * Katalog paket soal HC Learn.
     *
     * Balasannya membawa `simulasi: true` bila sedang dilayani simulasi lokal. Layar WAJIB
     * menampilkannya: admin yang memilih paket dari daftar palsu harus tahu ia sedang
     * memilih dari daftar palsu.
     */
    public function katalogPaketSoal(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->katalogHcLearn->daftarPaket());
    }

    /**
     * Mengimpor satu paket HC Learn ke dalam siklus, beserta butirnya.
     *
     * Menggantikan pengetikan kode paket secara manual. Kode yang salah ketik dahulu
     * diterima diam-diam: paketnya tersimpan, butirnya nol, level ditandai bersoal, dan
     * penilai menemukan formulir kosong.
     */
    /**
     * Bagian satu ujian beserta label yang SUDAH ada untuk sel ini — bahan pelabelan di
     * Langkah 2.
     *
     * Kembar dengan alamat di halaman master, dan kembarnya DISENGAJA: keduanya dijaga
     * izin halaman yang berbeda. Admin siklus memegang `PerformanceAppraisalPage`, bukan
     * `PaMasterPaketSoalPage`; memanggil alamat master dari layar siklus akan dijawab 403
     * untuk orang yang justru sedang menjalankan pekerjaannya. Isinya sendiri tidak
     * disalin — keduanya memanggil `bahanPelabelan()` yang sama.
     */
    public function bagianPaketSoal(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_master_ujian' => ['required', 'string', 'max:100'],
            'id_level' => ['required', 'integer'],
            'rater_category_id' => ['nullable', 'integer'],
        ]);

        return $this->jalankan(function () use ($cycleId, $data) {
            $this->gate->pastikanBoleh(PaCycleGate::PILIH_PAKET_SOAL, $cycleId);

            $acuan = $this->ikatanBaku->labelAcuanSel(
                (int) $data['id_level'],
                isset($data['rater_category_id']) ? (int) $data['rater_category_id'] : null,
                $data['id_master_ujian'],
            );

            return $this->ikatanBaku->bahanPelabelan(
                $data['id_master_ujian'],
                (int) $data['id_level'],
            ) + [
                'label_awal' => $acuan['label'],

                /* Dari mana isian awalnya datang. Null berarti milik sel ini sendiri;
                   berisi berarti SALINAN dari sel serumpun, dan layar wajib mengatakannya
                   — isian yang muncul tanpa sebab akan dikira pekerjaan yang sudah pernah
                   dilakukan, lalu disimpan tanpa dibaca. */
                'label_acuan_dari' => $acuan['dari'],
            ];
        });
    }

    /**
     * Melabeli bagian ujian ke kompetensi LANGSUNG dari Langkah 2.
     *
     * YANG DITULIS ADALAH MASTER, bukan salinan siklus — dan itu bukan efek samping yang
     * tersembunyi, melainkan satu-satunya tempat pelabelan hidup. Layar mengatakannya apa
     * adanya kepada admin, karena akibatnya nyata: siklus berikutnya yang memakai ujian
     * dan sel yang sama akan memakai pelabelan ini juga.
     *
     * Digerbangi status siklus, bukan hanya izin halaman: melabeli ulang sesudah siklusnya
     * terkunci akan menggeser arti nilai yang sedang dikumpulkan.
     */
    public function labelPaketSoal(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_level' => ['required', 'integer'],
            'rater_category_id' => ['nullable', 'integer'],
            'id_master_ujian' => ['required', 'string', 'max:100'],
            'label' => ['required', 'array', 'min:1'],
            'label.*' => ['required', 'integer'],
        ]);

        /* `jalankan()`, bukan pembungkus transaksi tersendiri: `simpan()` sudah
           menjalankan seluruh tulisannya dalam satu transaksi. Membungkusnya lagi cuma
           menambah lapisan yang tidak menahan apa pun. */
        return $this->jalankan(function () use ($cycleId, $data) {
            $this->gate->pastikanBoleh(PaCycleGate::PILIH_PAKET_SOAL, $cycleId);

            return $this->ikatanBaku->simpan(
                (int) $data['id_level'],
                isset($data['rater_category_id']) ? (int) $data['rater_category_id'] : null,
                $data['id_master_ujian'],
                array_map('intval', $data['label']),
                $this->userId(),
            );
        });
    }

    public function imporPaketSoal(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'id_level' => ['required', 'integer'],
            'id_master_ujian' => ['required', 'string', 'max:100'],
            // NULL berarti paket BAWAAN level itu -- dipakai kategori mana pun yang tidak
            // punya paket sendiri.
            'rater_category_id' => ['nullable', 'integer'],

            /* Penggantian HARUS diminta secara terpisah, tidak disimpulkan dari kenyataan
               bahwa selnya terisi. Menyimpulkannya membuat klik kedua yang tidak sengaja
               menukar paket soal satu sel tanpa siapa pun memutuskannya. */
            'ganti' => ['sometimes', 'boolean'],
        ]);

        return $this->jalankan(fn () => $this->katalogHcLearn->impor(
            $cycleId,
            (int) $data['id_level'],
            $data['id_master_ujian'],
            isset($data['rater_category_id']) ? (int) $data['rater_category_id'] : null,
            $this->userId(),
            true,
            (bool) ($data['ganti'] ?? false),
        ));
    }

    /**
     * Level yang dipakai karyawan aktif, beserta cakupannya di siklus ini.
     *
     * Berdiri sendiri dari langkah mana pun. Layar langkah 2 dahulu menurunkan daftar
     * levelnya dari salinan aturan penilai — yang baru ada sesudah langkah 3 dijalankan —
     * sehingga langkah 2 kosong sampai langkah 3 selesai, padahal nomornya mengatakan
     * sebaliknya.
     */
    public function levelSiklus(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->langkahSetup->levelSiklus($cycleId));
    }

    /**
     * Mencari karyawan untuk dikecualikan.
     *
     * Tanpa ini layar hanya bisa meminta admin mengetik kode. Kode yang salah ketik
     * diterima diam-diam: barisnya tersimpan, tidak cocok dengan siapa pun, dan
     * pengecualiannya tidak berpengaruh apa-apa tanpa satu pun pesan.
     */
    public function cariKaryawanPraKecuali(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable'],
            'departemen' => ['nullable'],
            'id_level' => ['nullable'],
            'cabang' => ['nullable'],
            'kode_list' => ['nullable', 'array'],
            'kode_list.*' => ['string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ]);

        return $this->jalankan(fn () => [
            'baris' => $this->langkahSetup->cariKaryawan(
                $cycleId, $data, (int) ($data['limit'] ?? 1000),
            ),
        ]);
    }

    public function opsiFilterKaryawanPraKecuali(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->langkahSetup->opsiFilterKaryawan());
    }

    /**
     * Memindahkan posisi admin ke satu langkah.
     *
     * URUT, TIDAK BOLEH DILOMPATI — dibalik 6 September 2026 atas keputusan pemilik proyek.
     * Sebelumnya perpindahan ditulis sebagai fakta belaka dengan alasan "admin berhak
     * melihat langkah 3 sebelum mengisi langkah 1". Alasan itu meleset: tiap langkah
     * MENULISKAN salinannya sendiri dari master, dan langkah berikutnya menyusun setelannya
     * di atas salinan itu. Melompat berarti menyetel sesuatu di atas dasar yang belum ada.
     *
     * Yang dilarang hanya melompat ke DEPAN. Kembali ke langkah yang sudah selesai tetap
     * boleh, dan langkah yang dirinya sendiri sudah selesai tetap bisa diperiksa.
     * Penjaganya di `PaCycleSetupStepService::pastikanGilirannyaTiba()`, bukan di sini.
     */
    public function pindahLangkahSetup(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'langkah' => ['required', 'string', 'max:20'],
        ]);

        return $this->jalankan(function () use ($cycleId, $data) {
            $this->langkahSetup->pindah($cycleId, $data['langkah'], $this->userId());

            return $this->langkahSetup->keadaan($cycleId);
        });
    }

    /**
     * Menyimpan satu langkah sebagai draft, tanpa menyatakannya selesai.
     *
     * Langkah yang isinya ditulis layanan lain (bobot, paket soal, aturan) menandai
     * dirinya sendiri saat disimpan. Aksi ini untuk hal sebaliknya: admin yang ingin
     * berhenti setengah jalan dan menandainya supaya ia ingat harus kembali.
     */
    public function draftLangkahSetup(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'langkah' => ['required', 'string', 'max:20'],
            'catatan' => ['nullable', 'string', 'max:400'],
        ]);

        return $this->jalankan(function () use ($cycleId, $data) {
            $this->langkahSetup->tandai(
                $cycleId,
                $data['langkah'],
                PaCycleSetupStepService::STATUS_DRAFT,
                $data['catatan'] ?? null,
                $this->userId(),
            );

            return $this->langkahSetup->keadaan($cycleId);
        });
    }

    /* ------------------------------------------------------------------ *
     * Langkah 3 — aturan penilai milik siklus                             *
     * ------------------------------------------------------------------ */

    public function daftarAturanPenilai(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->aturanPenilai->daftar($cycleId));
    }

    public function salinAturanPenilai(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->aturanPenilai->salinDariMaster($cycleId, $this->userId()));
    }

    public function simpanAturanPenilai(Request $request, int $cycleId, int $levelId)
    {
        $data = $request->validate([
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.id' => ['required', 'integer'],
            'rincian.*.bobot_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'rincian.*.min_rater' => ['required', 'integer', 'min:0'],
            'rincian.*.maks_rater' => ['nullable', 'integer', 'min:0'],
            /* `nullable` dan `min:1`, bukan `required` dan `min:0`.
               Kosong berarti "seluruh penilai minimum harus selesai" — itu bawaannya, dan
               memaksa admin mengisi angka membuat ia menebak. Nol tidak pernah sah: ia
               membuat penjaga penghitungan selalu lolos. */
            'rincian.*.min_rater_selesai' => ['nullable', 'integer', 'min:1'],
            'rincian.*.izinkan_manual' => ['nullable', 'boolean'],
            'rincian.*.catatan' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankan(fn () => $this->aturanPenilai->simpan(
            $cycleId, $levelId, $data['rincian'], $this->userId(),
        ));
    }

    public function siapkan(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penyiapan->siapkan($cycleId, $this->userId()));
    }

    /**
     * Apakah seluruh orang yang terlibat siklus ini sudah dikenal HC Learn.
     *
     * READ-ONLY terhadap data kita: ia hanya bertanya ke HC Learn. Ditaruh di fase
     * penyiapan karena di sanalah jawabannya masih bisa ditindaklanjuti — sesudah pintu
     * diminta, orang yang belum terdaftar sudah menggagalkan kirimannya.
     */
    public function kesiapanKaryawanHcLearn(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->karyawanHcLearn->kesiapan($cycleId));
    }

    /**
     * Mendaftarkan yang belum dikenal HC Learn, lalu memeriksa ulang.
     *
     * Tidak menulis satu baris pun di basis data kita — yang berubah ada di sisi HC Learn.
     * Karena itu `jalankan()`, bukan `jalankanTulis()`: transaksi di sini tidak menahan apa
     * pun, dan membungkus panggilan HTTP dengan transaksi justru memegang koneksi selama
     * jaringan menunggu.
     */
    public function sinkronkanKaryawanHcLearn(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->karyawanHcLearn->sinkronkan($cycleId));
    }

    /**
     * Memetakan ULANG satu kiriman HC Learn.
     *
     * Dipakai sesudah admin membetulkan ikatan paket soalnya: kiriman yang tersimpan tidak
     * perlu diminta ulang ke HC Learn — bukti mentahnya sudah ada di sini, yang salah hanya
     * penerjemahannya. Aman diulang; pemeta menonaktifkan baris lama dan menulis baris baru
     * dengan versi pemeta berikutnya.
     */
    public function petakanUlangKiriman(Request $request, int $cycleId, int $payloadId)
    {
        return $this->jalankan(fn () => $this->kirimanTinjau->petakanUlang(
            $cycleId,
            $payloadId,
            Auth::id(),
        ));
    }

    /**
     * Kemajuan pembentukan penugasan. Ringan, karena dipanggil berulang selama prosesnya jalan.
     */
    public function progresPenugasan(int $cycleId)
    {
        return $this->jalankan(fn () => $this->penugasan->progresPembentukan($cycleId));
    }

    /**
     * Membentuk penugasan penilai untuk BANYAK peserta sekaligus.
     *
     * DICICIL lewat `batas`, dan itu bukan kemewahan. Tiap peserta melewati seluruh penjaga
     * `tugaskan()` dan memakan sekitar dua detik; 859 peserta berarti hampir setengah jam.
     * Satu permintaan HTTP sepanjang itu akan mati kehabisan waktu dan meninggalkan penugasan
     * separuh jadi — keadaan yang lebih sulit ditelusuri daripada nol penugasan.
     *
     * Karena `bentukMassal()` melewati peserta yang penilainya sudah cukup, memanggilnya
     * berulang aman: tiap panggilan mengerjakan potongan berikutnya.
     */
    public function bentukPenugasanMassal(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'batas' => 'nullable|integer|min:1|max:100',
            'penuh' => 'nullable|boolean',
            'mulai_dari_id' => 'nullable|integer|min:0',
        ]);

        return $this->jalankan(function () use ($cycleId, $data) {
            $penuh = (bool) ($data['penuh'] ?? false);
            $batas = (int) ($data['batas'] ?? 100);
            $mulaiDari = isset($data['mulai_dari_id']) ? (int) $data['mulai_dari_id'] : null;

            /*
             | JALUR CEPAT DULU, jalur lama sebagai cadangan.
             |
             | `bentukMassalCepat()` menggabung tulisannya: 0,4 kueri per peserta alih-alih 7,5.
             | Tetapi ia hanya melayani peserta yang BELUM punya satu penugasan pun, karena di
             | situlah pembagian bobotnya sudah pasti sebelum barisnya ditulis.
             |
             | Begitu semua peserta punya penugasan, yang tersisa adalah MELENGKAPI yang kurang —
             | dan itu wajib lewat `bentukMassal()`, karena `hitungUlangBobot()` harus berjalan
             | untuk menghormati bobot khusus yang mungkin sudah diatur admin.
             */
            $hasil = $this->penugasan->bentukMassalCepat($cycleId, $penuh, $batas, $this->userId(), $mulaiDari);

            if (($hasil['peserta_diproses'] ?? 0) > 0) {
                return $hasil + ['jalur' => 'cepat'];
            }

            return $this->penugasan->bentukMassal($cycleId, $penuh, $batas, $this->userId())
                + ['jalur' => 'lengkapi'];
        });
    }

    /**
     * Berapa penugasan siklus ini yang sudah punya pintu HC Learn.
     */
    public function ringkasanPenjadwalan(int $cycleId)
    {
        return $this->jalankan(fn () => $this->penjadwalanHcLearn->ringkasan($cycleId));
    }

    /**
     * Menarik hasil penjadwalan yang masih ANTRE di HC Learn.
     *
     * DIPISAH dari penjadwalannya, dan itu mengikuti bentuk kontrak mereka: `store` menjawab
     * 202 lalu menggarap antreannya belakangan, jadi tokennya belum ada saat penjadwalan
     * selesai. Endpoint ini yang mengisinya, dan boleh dipanggil berkali-kali - memanggilnya
     * tidak mengubah apa pun di sisi mereka.
     */
    public function tarikStatusPenjadwalan(int $cycleId)
    {
        return $this->jalankan(fn () => $this->penjadwalanHcLearn->tarikStatus($cycleId, Auth::id())
            + ['ringkasan' => $this->penjadwalanHcLearn->ringkasan($cycleId)]);
    }

    /**
     * Membuatkan pintu bagi penugasan yang belum punya.
     *
     * DIPISAH dari "buka pengisian", bukan disembunyikan di dalamnya. Membuka pengisian adalah
     * pembalikan status yang seketika; penjadwalan adalah ribuan permintaan ke sistem luar yang
     * bisa gagal sebagian. Menyatukannya membuat kegagalan sebagian tak terlihat - admin melihat
     * "pengisian dibuka" dan menyimpulkan semua penilai sudah punya pintu.
     */
    public function jadwalkanPenilaian(Request $request, int $cycleId)
    {
        $data = $request->validate(['batas' => 'nullable|integer|min:1|max:1000']);

        return $this->jalankan(function () use ($cycleId, $data) {
            $hasil = $this->penjadwalanHcLearn->jadwalkanSiklus(
                $cycleId,
                $this->userId(),
                (int) ($data['batas'] ?? 0),
            );

            return $hasil + ['ringkasan' => $this->penjadwalanHcLearn->ringkasan($cycleId)];
        });
    }

    /**
     * Mencetak ULANG pintu satu penugasan.
     *
     * `{assignmentId}` ada di alamat, dan itu BUKAN berarti idnya menjadi izin. Siklus
     * pemiliknya dibaca dari penugasannya sendiri di dalam service, lalu digerbangi
     * `CETAK_ULANG_PINTU` — id penugasan milik siklus lain berhenti di gerbang itu, bukan di
     * sini.
     *
     * Alasan WAJIB dan tidak boleh berbentuk pilihan tertutup. Penilai akan menerima tautan
     * baru sementara tautan lamanya berhenti berlaku; enam bulan kemudian, satu-satunya
     * penjelasan kenapa ia sempat memegang dua tautan adalah kalimat yang ditulis di sini.
     */
    public function cetakUlangPintu(Request $request, int $assignmentId)
    {
        $data = $request->validate([
            'alasan' => 'required|string|min:5|max:500',
        ]);

        return $this->jalankan(fn () => $this->penjadwalanHcLearn->cetakUlangPintu(
            $assignmentId,
            (string) $data['alasan'],
            $this->userId(),
        ));
    }

    /**
     * Jadwalkan ULANG: jadwal lama dihapus di HC Learn lalu dijadwalkan baru. Jawaban yang
     * sudah diisi ikut dihapus — layar meminta konfirmasi tegas sebelum memanggil ini.
     */
    public function jadwalkanUlangPintu(Request $request, int $assignmentId)
    {
        $data = $request->validate([
            'alasan' => 'required|string|min:5|max:500',
        ]);

        return $this->jalankan(fn () => $this->penjadwalanHcLearn->jadwalkanUlangPintu(
            $assignmentId,
            (string) $data['alasan'],
            $this->userId(),
        ));
    }

    /**
     * Menyimpan catatan yang DITULIS ADMIN, satu blok per kompetensi kualitatif.
     *
     * PUT, bukan POST: yang dikirim adalah SELURUH daftar milik orang itu sekaligus, karena
     * layarnya berbentuk kotak teks berisi semuanya. Menambahkannya baris demi baris akan
     * menggandakan isi setiap kali admin menekan simpan dua kali.
     *
     * Catatan dari jawaban penilai TIDAK tersentuh di sini — keduanya sengaja dibedakan,
     * karena yang satu observasi banyak orang dan yang satu kesimpulan satu orang.
     */
    public function simpanCatatanHasil(Request $request, int $participantId)
    {
        $data = $request->validate([
            'catatan' => ['required', 'array'],
            'catatan.*' => ['array'],
            'catatan.*.*' => ['nullable', 'string', 'max:50000'],
        ]);

        return $this->jalankan(fn () => $this->catatanHasil->simpanCatatanAdmin(
            $participantId,
            $data['catatan'],
            $this->userId(),
        ));
    }

    /**
     * Ringkasan sebelum penguncian. Dibaca dialog konfirmasi supaya keputusannya diambil
     * sambil melihat angkanya, bukan sambil disuruh "memastikan".
     */
    public function ringkasanPraKunci(int $cycleId)
    {
        return $this->jalankan(fn () => $this->penyiapan->ringkasanPraKunci($cycleId));
    }

    public function kunci(Request $request, int $cycleId)
    {
        return $this->jalankan(function () use ($cycleId) {
            // Pelakunya diteruskan. Tanpa ini `Locked_By` tetap kosong dan lini masa
            // audit melaporkan pembekuan aturan sebagai peristiwa tanpa pelaku.
            //
            // `sudah_terkunci` diteruskan apa adanya supaya layar bisa membedakan "baru saja
            // saya kunci" dari "memang sudah terkunci" — keduanya berhasil, tetapi hanya yang
            // pertama pantas diumumkan sebagai peristiwa.
            return $this->penyiapan->kunci($cycleId, (int) Auth::id());
        });
    }

    /**
     * Membuka jendela pengisian: `LOCKED`/`CLOSED` -> `OPEN`.
     *
     * Tanpa dua aksi ini dan pasangannya di bawah, siklus sungguhan mentok di `LOCKED`
     * selamanya — dan empat operasi inti yang bergerbang pada `OPEN`/`CLOSED` tak pernah
     * terjangkau lewat aplikasi.
     */
    public function bukaPengisian(Request $request, int $cycleId)
    {
        /* Jendelanya boleh ikut dalam permintaan yang SAMA. Ia syarat tindakan ini, dan
           memisahkannya menjadi dua permintaan berarti dua kali muat ulang untuk satu
           keputusan — selain meninggalkan celah tempat jendela tersimpan tanpa pembukaan. */
        $data = $request->validate([
            'pengisian_dari' => ['sometimes', 'nullable', 'date'],
            'pengisian_sampai' => ['sometimes', 'nullable', 'date', 'after_or_equal:pengisian_dari'],
        ]);

        return $this->jalankan(
            fn () => $this->penyiapan->bukaPengisian($cycleId, (int) Auth::id(), $data)
        );
    }

    /** Menutup jendela pengisian: `OPEN` -> `CLOSED`. */
    public function tutupPengisian(Request $request, int $cycleId)
    {
        return $this->jalankan(
            fn () => $this->penyiapan->tutupPengisian($cycleId, (int) Auth::id())
        );
    }

    /**
     * Peserta yang levelnya tidak punya aturan penilai.
     *
     * Ini keranjang yang menahan penguncian. Menampilkannya adalah satu-satunya cara
     * penggunanya tahu apa yang harus diperbaiki, alih-alih hanya melihat penguncian
     * ditolak berulang kali.
     */
    public function keranjangTertunda(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => [
            'tertunda' => $this->penyiapan->keranjangTertunda($cycleId),
        ]);
    }

    public function usulanPenilai(Request $request, int $participantId)
    {
        return $this->jalankan(fn () => [
            'usulan' => $this->penugasan->usulkan($participantId),
            'kesiapan' => $this->penugasan->kesiapan($participantId),
        ]);
    }

    public function tugaskanPenilai(Request $request, int $participantId)
    {
        $data = $request->validate([
            /* TIDAK lagi `required`: satu kali Simpan boleh membawa hanya perubahan bobot, tanpa
               penilai baru sama sekali. Sebelumnya keadaan itu dijawab "The penilai field is
               required" — pesan yang menuduh padahal tidak ada yang salah. */
            'penilai' => 'array',
            'penilai.*.kode_kategori' => 'required|string',
            'penilai.*.kode_perusahaan' => 'required|string',
            'penilai.*.kode_karyawan' => 'required|string',
            'penilai.*.sumber' => 'nullable|string',
            'penilai.*.organization_relationship_id' => 'nullable|integer',
            'penilai.*.alasan_manual' => 'nullable|string|max:500',

            /* Bobot ikut dalam permintaan yang SAMA. Ditunjuk lewat identitas orangnya, bukan id
               penugasan, supaya penilai yang barisnya baru lahir di transaksi ini pun bisa
               diberi bobot tanpa layar perlu tahu id-nya lebih dulu. */
            'bobot' => 'array',
            'bobot.*.kode_kategori' => 'required|string',
            'bobot.*.kode_perusahaan' => 'required|string',
            'bobot.*.kode_karyawan' => 'required|string',
            'bobot.*.bobot' => 'required|numeric|min:0|max:100',

            'bobot_rata' => 'array',
            'bobot_rata.*' => 'string',
        ]);

        return $this->jalankan(fn () => $this->jadwalkanBilaBertambah($participantId, $this->penugasan->tugaskan(
            $participantId,
            $data['penilai'] ?? [],
            $this->userId(),
            $data['bobot'] ?? [],
            $data['bobot_rata'] ?? [],
        ), 'dibuat'));
    }

    /**
     * Sesudah dikunci, penilai yang baru ditambah langsung dijadwalkan ke HC Learn (tugas
     * latar) — hasilnya dibawa sebagai `jadwal_hclearn`. Sebelum dikunci tidak ada apa-apa:
     * penjadwalan adalah bagian penguncian.
     *
     * @param  array<string, mixed>  $hasil
     * @return array<string, mixed>
     */
    private function jadwalkanBilaBertambah(int $participantId, array $hasil, string $kunciJumlah): array
    {
        if ((int) ($hasil[$kunciJumlah] ?? 0) > 0
            && ($jadwal = app(PaRevisiPesertaService::class)->jadwalkanSesudahKelola($participantId, $this->userId())) !== null) {
            $hasil['jadwal_hclearn'] = $jadwal;
        }

        return $hasil;
    }

    /**
     * Menyimpan pembagian bobot ANTAR PENILAI dalam kategori.
     *
     * Bobotnya dikirim sebagai peta `assignment_id => bobot`, bukan daftar berurutan: urutan
     * di layar bisa berbeda dari urutan di database, dan daftar berurutan akan memasangkan
     * angka ke penilai yang salah tanpa satu pun galat.
     */
    public function simpanBobotPenilai(Request $request, int $participantId)
    {
        $data = $request->validate([
            'bobot' => ['required', 'array', 'min:1'],

            /* Nol diizinkan: penilai yang ditugaskan tetapi sengaja tidak diberi bobot adalah
               keputusan yang sah. Yang tidak diizinkan negatif dan lebih dari 100. */
            'bobot.*' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $peta = [];

        foreach ($data['bobot'] as $id => $nilai) {
            $peta[(int) $id] = (float) $nilai;
        }

        return $this->jalankan(
            fn () => $this->penugasan->simpanBobotPenilai($participantId, $peta, $this->userId()),
        );
    }

    /**
     * Mengembalikan satu kategori ke pembagian rata dan MELEPAS penanda bobot khususnya.
     *
     * Terpisah dari `simpanBobotPenilai` karena artinya berbeda. Mengirim angka rata lewat
     * jalur itu tetap menandainya sebagai keputusan, sehingga kategorinya berhenti menyesuaikan
     * diri saat penilainya berubah — "kembali ke bawaan" yang ternyata tidak kembali.
     */
    public function kembalikanBobotRata(Request $request, int $participantId)
    {
        $data = $request->validate([
            'kategori_id' => ['required', 'integer', 'min:1'],
        ]);

        return $this->jalankan(
            fn () => $this->penugasan->kembalikanBobotRata(
                $participantId,
                (int) $data['kategori_id'],
                $this->userId(),
            ),
        );
    }

    /**
     * Menambah penilai sekaligus membentuk relasi organisasinya.
     *
     * Satu tindakan, bukan dua: admin yang tahu "orang ini atasannya" sedang menyatakan fakta
     * organisasi, bukan sekadar mengisi satu siklus. Memisahkannya berarti pengetahuan itu
     * hilang begitu siklusnya selesai.
     */
    /**
     * Mencari calon penilai untuk satu kategori, dengan alasan bila tidak bisa dipilih.
     *
     * Terpisah dari pencarian karyawan umum karena jawabannya bergantung pada PESERTA: relasi
     * apa yang sudah ada di antara mereka menentukan boleh-tidaknya.
     */
    public function cariCalonPenilai(Request $request, int $participantId)
    {
        $data = $request->validate([
            'kode_kategori' => ['required', 'string', 'max:30'],
            'q' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->jalankan(fn () => $this->penugasan->cariCalonPenilai(
            $participantId,
            $data['kode_kategori'],
            $data['q'] ?? '',
            (int) ($data['limit'] ?? 15),
        ));
    }

    public function tambahPenilaiDenganRelasi(Request $request, int $participantId)
    {
        $data = $request->validate([
            'kode_kategori' => ['required', 'string', 'max:30'],
            'kode_perusahaan' => ['required', 'string', 'max:3'],
            'kode_karyawan' => ['required', 'string', 'max:255'],
        ]);

        return $this->jalankan(fn () => $this->jadwalkanBilaBertambah($participantId, $this->penugasan->tambahPenilaiDenganRelasi(
            $participantId,
            $data['kode_kategori'],
            $data['kode_perusahaan'],
            $data['kode_karyawan'],
            $this->userId(),
        ), 'penugasan_dibuat'));
    }

    public function batalkanPenugasan(Request $request, int $assignmentId)
    {
        $data = $request->validate([
            // Wajib, dan wajib berisi. Pembatalan tanpa sebab adalah penugasan yang
            // lenyap begitu saja bagi siapa pun yang membacanya kemudian.
            'alasan' => 'required|string|max:500',
        ]);

        return $this->jalankan(function () use ($assignmentId, $data) {
            $this->penugasan->batalkan($assignmentId, $data['alasan'], $this->userId());

            return ['dibatalkan' => $assignmentId];
        });
    }

    /**
     * Satu halaman peserta.
     *
     * Berhalaman sejak awal: satu siklus memuat seluruh karyawan aktif — hampir seribu
     * orang — dan mengirim semuanya sekaligus akan membuat layar berhenti sebelum
     * sempat menampilkan apa pun.
     */
    public function daftarPeserta(Request $request, int $cycleId)
    {
        $saringan = $request->validate([
            /* Menuju satu orang langsung — dipakai tautan dari keranjang keputusan admin. */
            'participant_id' => 'nullable|integer',
            'status_peserta' => 'nullable|string|max:20',
            /* Boleh memuat beberapa status dipisah koma; 20 karakter tidak cukup untuk
               itu, dan batas yang terlalu pendek menolak saringan yang sah dengan pesan
               yang tidak menyebut sebab sesungguhnya. */
            'status_hasil' => 'nullable|string|max:120',
            'id_divisi' => 'nullable|integer',
            'id_sub_divisi' => 'nullable|integer',
            'kode_jabatan' => 'nullable|string|max:50',
            'lokasi' => 'nullable|string|max:50',
            'id_level' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
            'halaman' => 'nullable|integer|min:1',
            'per_halaman' => 'nullable|integer|min:1',
            'rilis_leader' => 'nullable|in:Y,T',
            'hanya_id' => 'nullable|boolean',
            'dengan_opsi' => 'nullable|boolean',
        ]);

        return $this->jalankan(fn () => $this->pembaca->daftar($cycleId, $saringan));
    }

    /**
     * Sebaran sembilan kotak HAV Map dan distribusi grade satu siklus.
     */
    public function havMap(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->pembaca->havMap($cycleId));
    }

    /**
     * Seluruh isi layar hasil satu peserta, termasuk bahan radar dan riwayat kalibrasi.
     */
    public function detailHasil(Request $request, int $participantId)
    {
        return $this->jalankan(fn () => $this->pembaca->detailHasil($participantId));
    }

    /**
     * Lini masa angka hasil satu peserta: kapan dihitung, oleh siapa, dan dari berapa ke
     * berapa. Dimuat terpisah saat tab Riwayat Perhitungan dibuka.
     */
    public function riwayatHasil(PaResultHistoryService $riwayat, int $participantId)
    {
        return $this->jalankan(fn () => $riwayat->lini($participantId));
    }

    /**
     * Tahap Kesimpulan Kualitatif: peserta yang punya jawaban kualitatif beserta status
     * kesimpulannya. Saringan dan paginasi di server.
     */
    public function daftarKesimpulanKualitatif(Request $request, PaKesimpulanKualitatifService $kualitatif, int $cycleId)
    {
        $saringan = $request->validate([
            'status' => 'nullable|in:BELUM,SUDAH',
            'id_divisi' => 'nullable|integer',
            'id_level' => 'nullable|integer',
            'q' => 'nullable|string|max:100',
            'halaman' => 'nullable|integer|min:1',
            'per_halaman' => 'nullable|integer|min:1',
            'dengan_opsi' => 'nullable|boolean',
        ]);

        return $this->jalankan(fn () => $kualitatif->daftar($cycleId, $saringan));
    }

    /** Jawaban penilai per kategori kualitatif dan kesimpulan yang berlaku, untuk satu peserta. */
    public function ruangKerjaKualitatif(PaKesimpulanKualitatifService $kualitatif, int $participantId)
    {
        return $this->jalankan(fn () => $kualitatif->ruangKerja($participantId));
    }

    /**
     * Menggeser grade akhir dan/atau kuadran. Sebabnya wajib.
     */
    public function kalibrasiHasil(Request $request, int $resultId)
    {
        /* KALIBRASI FORUM DITUTUP (B7, spec 2026-09-30 Balancing). Grade kini hanya bergeser
           lewat skor kompetensi di sesi Balancing dan kurva, sehingga grade selalu cocok dengan
           angkanya. Rutenya dipertahankan supaya klien lama mendapat penjelasan, bukan 404. */
        return ResponseHelper::errorWithCode(
            'Kalibrasi Forum sudah digantikan Balancing. Geser skor kompetensinya lewat sesi Balancing, lalu hitung ulang.',
            'PA_KALIBRASI_FORUM_DITUTUP',
            422,
        );
    }

    /**
     * Menghitung satu siklus — BERTAHAP, bukan sekaligus.
     *
     * `PaResultCalculationService::hitungSiklus()` sejak awal menerima `$batas` dan
     * `$mulaiDariId`, dan balasannya memuat `terakhir_id` serta `selesai` — bentuk yang
     * jelas dirancang untuk dicicil. Tetapi sampai 3 September 2026 controller ini tidak
     * pernah meneruskan keduanya, sehingga menekan "Hitung" pada siklus sungguhan
     * menjalankan SELURUH peserta dalam satu permintaan HTTP.
     *
     * Pada 958 peserta itu terukur belasan menit — jauh melewati batas waktu permintaan
     * mana pun. Gejalanya bukan galat yang menjelaskan dirinya, melainkan tombol yang
     * ditekan lalu tidak terjadi apa-apa: bentuk kegagalan yang paling sulit dilaporkan
     * orang, karena tidak ada yang bisa ditunjukkan.
     *
     * Bakunya 25 — cukup kecil untuk selesai dalam hitungan detik pada tautan yang lambat
     * sekalipun. Layar melanjutkan dengan mengirim `mulai_dari_id` = `terakhir_id`
     * sampai `selesai` bernilai true.
     */
    /**
     * Alur berlangkah satu siklus: ada di langkah mana, apa berikutnya, apa penghalangnya.
     *
     * Dibuat 3 September 2026 sesudah pemilik proyek menyebut pengalaman adminnya buruk.
     * Sebabnya bukan fitur yang kurang melainkan URUTAN yang tak terlihat: sembilan dialog
     * pada satu layar, dijalankan sekali-dua kali setahun, tanpa apa pun yang mengatakan
     * apa datang sesudah apa.
     */
    public function alurLangkah(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->alurLangkah->alur($cycleId));
    }

    /** Lima tahap penutupan siklus (pengisian → terbit): keadaan, penghambat, aktivitas terakhir. */
    public function alurPenutupan(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penutupan->alur($cycleId));
    }

    /**
     * Menghitung ulang seluruh peserta yang TERTAHAN di satu siklus.
     *
     * Dipakai tombol "Hitung Ulang yang Tertahan" pada keranjang, dan oleh perintah
     * berjadwal. Keduanya memanggil method yang sama — satu aturan, dua pemicu.
     */
    public function sapuTertahan(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'batas' => ['nullable', 'integer', 'min:1', 'max:'.self::MAKS_HITUNG_SEKALI],
            'mulai_dari_id' => ['nullable', 'integer', 'min:0'],
            'jenis' => ['nullable', 'in:TERTAHAN,SIAP'],
        ]);

        return $this->jalankan(fn () => $this->perhitungan->sapuTertahan(
            $cycleId,
            $this->userId(),
            (int) ($data['batas'] ?? self::BAKU_HITUNG_SEKALI),
            isset($data['mulai_dari_id']) ? (int) $data['mulai_dari_id'] : null,
            $data['jenis'] ?? 'TERTAHAN',
        ));
    }

    public function hitungSiklus(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'batas' => ['nullable', 'integer', 'min:1', 'max:'.self::MAKS_HITUNG_SEKALI],
            'mulai_dari_id' => ['nullable', 'integer', 'min:1'],

            /* Bakunya SEMUA, bukan BELUM. Lingkup yang lebih sempit harus diminta dengan
               sengaja; pemanggil lama yang tidak mengirim apa pun tetap mendapat perilaku
               yang sama persis seperti sebelum 7 September 2026. */
            'lingkup' => ['nullable', 'string', 'in:SEMUA,BELUM'],
        ]);

        return $this->jalankan(fn () => $this->perhitungan->hitungSiklus(
            $cycleId,
            $this->userId(),
            (int) ($data['batas'] ?? self::BAKU_HITUNG_SEKALI),
            isset($data['mulai_dari_id']) ? (int) $data['mulai_dari_id'] : null,
            (string) ($data['lingkup'] ?? PaResultCalculationService::LINGKUP_SEMUA),
        ));
    }

    /**
     * Berapa banyak yang akan tersentuh kalau tombol Hitung ditekan.
     *
     * Dipisah dari `hitungSiklus` DENGAN SENGAJA: yang satu membaca, yang lain menulis
     * selama menit-menit. Menggabungkannya sebagai "hitung dengan mode pratinjau" berarti
     * satu parameter yang salah kirim menjalankan seluruh siklus alih-alih menghitung
     * barisnya.
     */
    public function pratinjauHitungSiklus(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->perhitungan->pratinjauHitungSiklus($cycleId));
    }

    /**
     * Melepas kunci pengiriman seorang penilai, tanpa menunggu ia menekan kirim.
     *
     * Pintu darurat dari aturan satu-pintu: penilai yang tidak bisa dihubungi, atau yang
     * sisa lembarnya memang tidak akan pernah dikerjakan, kalau tidak akan membekukan hasil
     * seluruh peserta yang ia nilai - pada penilai dengan dua puluh empat lembar itu dua
     * puluh tiga orang lain yang tidak tahu-menahu.
     *
     * Wajib bersebab. Yang membaca kelak perlu tahu kenapa penilaian ini ikut dihitung
     * padahal pengirimnya tidak pernah menyatakan selesai.
     */
    public function lepasPaksaPenilai(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'kode_perusahaan' => ['required', 'string', 'max:20'],
            'kode_karyawan' => ['required', 'string', 'max:30'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->jalankan(fn () => app(PaRaterSubmitService::class)->lepasPaksa(
            $cycleId,
            (string) $data['kode_perusahaan'],
            (string) $data['kode_karyawan'],
            (string) $data['alasan'],
            $this->userId(),
        ));
    }

    public function hitungPeserta(Request $request, int $participantId)
    {
        return $this->jalankan(fn () => $this->perhitungan->hitung($participantId, $this->userId()));
    }

    /**
     * Menyimpan SELURUH penyimpangan penilai satu peserta sekaligus.
     *
     * Sengaja tidak per baris: bobot antar kategori wajib berjumlah 100 SESUDAH digabung
     * dengan salinan siklus, dan jumlah itu hanya bisa diperiksa ketika seluruh
     * penyimpangannya diketahui. Daftar kosong berarti mencabut semuanya.
     */
    public function simpanOverridePenilai(Request $request, int $participantId)
    {
        $data = $request->validate([
            'penilai' => 'present|array',
            'penilai.*.pa_rater_category_id' => 'required|integer',
            'penilai.*.alasan_perubahan' => 'required|string|max:500',
            'penilai.*.flag_dipakai' => 'nullable|in:Y,T',
            'penilai.*.bobot_persen' => 'nullable|numeric|min:0|max:100',
            'penilai.*.min_rater' => 'nullable|integer|min:1',
            'penilai.*.maks_rater' => 'nullable|integer|min:1',
            'penilai.*.min_rater_selesai' => 'nullable|integer|min:1',
        ]);

        return $this->jalankan(fn () => $this->penyimpangan->simpanOverridePenilai(
            $participantId, $data['penilai'], $this->userId()
        ));
    }

    /**
     * Menandai satu kategori penilai SENGAJA tidak diikutkan bagi satu peserta — jalan
     * kedua menyelesaikan kekurangan penilai di Langkah 5 selain menambah penilainya.
     */
    public function tandaiKategoriTidakDiikutkan(Request $request, PaKategoriTidakDiikutkanService $layanan, int $participantId)
    {
        $data = $request->validate([
            'kategori_id' => 'required|integer|min:1',
            'alasan' => 'required|string|max:400',
        ]);

        return $this->jalankan(fn () => $layanan->tandai(
            $participantId, (int) $data['kategori_id'], $data['alasan'], $this->userId()
        ));
    }

    /**
     * Membatalkan tanda "tidak diikutkan": kategori kembali dipakai, bobot kembali ke aturan
     * level, penugasan yang dibatalkan oleh tanda itu diaktifkan kembali.
     */
    public function batalkanKategoriTidakDiikutkan(PaKategoriTidakDiikutkanService $layanan, int $participantId, int $kategoriId)
    {
        return $this->jalankan(fn () => $layanan->batalkanTanda($participantId, $kategoriId, $this->userId()));
    }

    /**
     * Menyimpan penyimpangan bobot pilar satu peserta.
     */
    public function simpanOverridePilar(Request $request, int $participantId)
    {
        $data = $request->validate([
            'bobot_kpi' => 'required|numeric|min:0|max:100',
            'bobot_kompetensi' => 'required|numeric|min:0|max:100',
            'alasan_perubahan' => 'required|string|max:500',
        ]);

        return $this->jalankan(fn () => $this->penyimpangan->simpanOverridePilar(
            $participantId,
            (float) $data['bobot_kpi'],
            (float) $data['bobot_kompetensi'],
            $data['alasan_perubahan'],
            $this->userId()
        ));
    }

    /**
     * Menyimpan SELURUH penyimpangan jenis kompetensi satu peserta.
     */
    public function simpanOverrideSyarat(Request $request, int $participantId)
    {
        $data = $request->validate([
            'syarat' => 'present|array',
            'syarat.*.pa_competency_type_id' => 'required|integer',
            'syarat.*.alasan_perubahan' => 'required|string|max:500',
            'syarat.*.flag_dipakai' => 'nullable|in:Y,T',
            'syarat.*.flag_wajib' => 'nullable|in:Y,T',
        ]);

        return $this->jalankan(fn () => $this->penyimpangan->simpanOverrideSyarat(
            $participantId, $data['syarat'], $this->userId()
        ));
    }

    /**
     * Mencabut seluruh penyimpangan — peserta kembali ke aturan levelnya.
     */
    public function cabutOverride(Request $request, int $participantId)
    {
        return $this->jalankan(fn () => $this->penyimpangan->cabutSeluruhOverride(
            $participantId, $this->userId()
        ));
    }

    /**
     * Menerbitkan seluruh hasil yang sudah dihitung. Siklusnya TETAP `CLOSED`.
     *
     * Yang `TERTAHAN` sengaja tidak ikut: sistem menolak memberinya angka, dan
     * menerbitkannya berarti menunjukkan kepada karyawan sesuatu yang tidak sah.
     * Yang KPI-nya belum lengkap dilewati dan disebut di jawaban, kecuali diminta ikut.
     */
    public function terbitkan(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer',
            // Sama dengan jalur per orang: menimpa penahanan harus DIMINTA, bukan bawaan.
            'abaikan_kpi_belum_lengkap' => ['sometimes', 'boolean'],
            'abaikan_kategori_gugur' => ['sometimes', 'boolean'],
        ]);

        return $this->jalankan(fn () => $this->penerbitan->terbitkan(
            $cycleId,
            $this->userId(),
            $data['participant_ids'] ?? null,
            (bool) ($data['abaikan_kpi_belum_lengkap'] ?? false),
            (bool) ($data['abaikan_kategori_gugur'] ?? false),
        ));
    }

    /**
     * Menerbitkan hasil satu orang — untuk yang tertinggal dan baru selesai diperbaiki.
     */
    public function terbitkanPeserta(Request $request, int $participantId)
    {
        /* Menimpa penahanan "KPI belum lengkap" harus DIMINTA, bukan bawaan. Bawaan yang
           mengizinkan berarti admin menerbitkan angka dari periode yang belum utuh tanpa
           pernah diberi tahu — dan hasil terbit tidak bisa dihitung ulang. */
        $data = $request->validate([
            'abaikan_kpi_belum_lengkap' => ['sometimes', 'boolean'],
            'abaikan_kategori_gugur' => ['sometimes', 'boolean'],
        ]);

        /* Kategori gugur dijawab dengan kode stabil, supaya layar bisa menawarkan "terbitkan
           dengan sadar" tanpa mencocokkan kalimatnya. */
        try {
            return ResponseHelper::success($this->penerbitan->terbitkanPeserta(
                $participantId,
                $this->userId(),
                (bool) ($data['abaikan_kpi_belum_lengkap'] ?? false),
                (bool) ($data['abaikan_kategori_gugur'] ?? false),
            ), 'Berhasil');
        } catch (PaKategoriGugurException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), PaKategoriGugurException::KODE, 422);
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    /**
     * Merilis seluruh hasil siklus ini ke LEADER — bukan ke karyawan yang dinilai.
     */
    public function rilisLeader(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'integer',
            // Menimpa penahanan harus DIMINTA, sama dengan penerbitan.
            'abaikan_kpi_belum_lengkap' => ['sometimes', 'boolean'],
            'abaikan_kategori_gugur' => ['sometimes', 'boolean'],
        ]);

        return $this->jalankan(fn () => $this->penerbitan->rilisKeLeader(
            $cycleId,
            $this->userId(),
            $data['participant_ids'] ?? null,
            (bool) ($data['abaikan_kpi_belum_lengkap'] ?? false),
            (bool) ($data['abaikan_kategori_gugur'] ?? false),
        ));
    }

    /**
     * Merilis hasil satu orang ke leadernya.
     */
    public function rilisLeaderPeserta(Request $request, int $participantId)
    {
        $data = $request->validate([
            'abaikan_kpi_belum_lengkap' => ['sometimes', 'boolean'],
            'abaikan_kategori_gugur' => ['sometimes', 'boolean'],
        ]);

        // Kategori gugur dijawab dengan kode stabil, sama dengan terbitkanPeserta().
        try {
            return ResponseHelper::success($this->penerbitan->rilisKeLeaderPeserta(
                $participantId,
                $this->userId(),
                (bool) ($data['abaikan_kpi_belum_lengkap'] ?? false),
                (bool) ($data['abaikan_kategori_gugur'] ?? false),
            ), 'Berhasil');
        } catch (PaKategoriGugurException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), PaKategoriGugurException::KODE, 422);
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    /**
     * Menarik kembali akses leader atas satu hasil. Sebabnya wajib dan ikut tersimpan.
     */
    public function cabutRilisLeader(Request $request, int $participantId)
    {
        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->jalankan(fn () => $this->penerbitan->cabutRilisLeader(
            $participantId,
            $data['alasan'],
            $this->userId(),
        ));
    }

    /**
     * Memindahkan SIKLUS ke PUBLISHED — pintu satu arah, ditolak selama masih ada yang
     * belum selesai.
     */
    public function tutupPenerbitan(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penerbitan->tutupPenerbitan($cycleId, $this->userId()));
    }

    /**
     * Cacah hasil per status — dipakai layar untuk memutuskan tombol mana yang berguna.
     */
    public function ringkasanPenerbitan(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penerbitan->ringkasan($cycleId) + [
            // Syarat yang sama dengan aksi rilis/terbit, supaya layar mematikan tombolnya lebih dulu.
            'syarat' => $this->penerbitan->syaratPublish($cycleId),
            'kualitatif_belum' => app(PaKesimpulanKualitatifService::class)->belumSebelumTerbit($cycleId),
        ]);
    }

    /** Pengingat sebelum menerbitkan peserta terpilih: siapa yang belum punya kesimpulan kualitatif. */
    public function periksaKesimpulanSebelumTerbit(Request $request, PaKesimpulanKualitatifService $kualitatif, int $cycleId)
    {
        $data = $request->validate([
            'participant_ids' => ['nullable', 'array', 'max:5000'],
            'participant_ids.*' => ['integer'],
        ]);

        return $this->jalankan(fn () => $kualitatif->belumSebelumTerbit($cycleId, $data['participant_ids'] ?? null));
    }

    /* ================================================================== *
     * Penyiapan siklus (A09)                                              *
     * ================================================================== */

    public function detailSiklus(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penyiapanSiklus->detail($cycleId));
    }

    /**
     * Membuat siklus. Selalu lahir DRAFT — status tidak diterima dari kiriman, karena
     * siklus yang lahir terkunci melewati penyiapan dan pesertanya tak pernah terbentuk.
     */
    public function buatSiklus(Request $request)
    {
        return $this->jalankan(fn () => $this->penyiapanSiklus->buat(
            $this->aturanSiklus($request, wajib: true),
            $this->userId(),
        ));
    }

    public function perbaruiSiklus(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->penyiapanSiklus->perbarui(
            $cycleId,
            $this->aturanSiklus($request, wajib: false),
            $this->userId(),
        ));
    }

    /**
     * Validasi bentuk saja. Aturan bisnisnya — tahun wajib sesuai periode, periode beku
     * sesudah kunci — hidup di service, supaya jalur non-HTTP ikut terjaga.
     *
     * @return array<string, mixed>
     */
    private function aturanSiklus(Request $request, bool $wajib): array
    {
        $w = $wajib ? 'required' : 'sometimes';

        return $request->validate([
            /* Bukan `required` lagi: kodenya dibuat server (`PaCycleSetupService::kodeBaru()`).
               Masih DITERIMA supaya layar boleh mengirim kembali seluruh isian form apa
               adanya, termasuk kode yang cuma ditampilkan. Yang dikirim saat membuat
               diabaikan; yang berbeda saat menyunting ditolak service. */
            'kode_cycle' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_\-\.]+$/'],
            'nama_cycle' => [$w, 'string', 'max:150'],
            'tahun' => [$w, 'integer', 'min:2000', 'max:2100'],
            'deskripsi' => ['sometimes', 'nullable', 'string', 'max:500'],
            'periode_dinilai_dari' => ['sometimes', 'nullable', 'date'],
            'periode_dinilai_sampai' => ['sometimes', 'nullable', 'date'],
            'pengisian_dari' => ['sometimes', 'nullable', 'date'],
            'pengisian_sampai' => ['sometimes', 'nullable', 'date'],
            'tanggal_acuan_organisasi' => ['sometimes', 'nullable', 'date'],
            /* Menyalin setelan dari siklus sebelumnya. Hanya bermakna saat MEMBUAT —
               menyunting siklus yang sudah berjalan lalu tiba-tiba menyalin paket soal
               dari siklus lain akan menggandakan paketnya tanpa ada yang meminta. */
            'salin_dari_cycle_id' => ['sometimes', 'nullable', 'integer'],
        ]);
    }

    /* ================================================================== *
     * Bobot pilar per siklus (K23) dan penyuntingan peserta               *
     * ================================================================== */

    /**
     * Bobot pilar seluruh level pada satu siklus, beserta dampak penyuntingannya.
     */
    public function daftarBobotPilar(Request $request, int $cycleId)
    {
        return $this->jalankan(fn () => $this->bobotPilar->daftar($cycleId));
    }

    /**
     * Menyunting bobot pilar satu level.
     *
     * `id_level` ada di alamat, bukan di badan, karena ia MENUNJUK baris mana yang
     * disunting — bukan nilai yang diubah. Menaruhnya di badan membuat alamat yang sama
     * menulis baris yang berbeda tergantung isi kiriman.
     */
    public function simpanBobotPilar(Request $request, int $cycleId, int $levelId)
    {
        $data = $request->validate([
            'bobot_kpi' => ['required', 'numeric', 'min:0', 'max:100'],
            'bobot_kompetensi' => ['required', 'numeric', 'min:0', 'max:100'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankan(fn () => $this->bobotPilar->simpan(
            $cycleId,
            $levelId,
            $data,
            $this->userId(),
        ));
    }

    /**
     * Mengubah status keikutsertaan satu peserta.
     */
    public function ubahStatusPeserta(Request $request, int $participantId)
    {
        $data = $request->validate([
            // TERTUNDA sengaja TIDAK di sini. Ia keadaan, bukan pilihan — dan service
            // menolaknya lagi dengan penjelasan, supaya jalur non-HTTP ikut terjaga.
            'status' => ['required', 'string', 'in:DIIKUTKAN,DIKECUALIKAN'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankan(fn () => $this->suntingPeserta->ubahStatus(
            $participantId,
            $data['status'],
            (string) ($data['alasan'] ?? ''),
            $this->userId(),
        ));
    }

    /**
     * Mengubah status banyak peserta sekaligus.
     *
     * Yang gagal dikumpulkan dan dilaporkan, tidak menghentikan sisanya: berhenti di
     * tengah membuat admin harus menebak sudah sampai mana.
     */
    public function ubahStatusPesertaBanyak(Request $request)
    {
        $data = $request->validate([
            'participant_ids' => ['required', 'array', 'min:1', 'max:500'],
            'participant_ids.*' => ['integer'],
            'status' => ['required', 'string', 'in:DIIKUTKAN,DIKECUALIKAN'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankan(fn () => $this->suntingPeserta->ubahStatusBanyak(
            $data['participant_ids'],
            $data['status'],
            (string) ($data['alasan'] ?? ''),
            $this->userId(),
        ));
    }

    /**
     * Menjalankan satu operasi service dan menerjemahkan penolakannya.
     *
     * @param  callable():mixed  $operasi
     */
    /** Membekukan kurva grade per grup level dan memberi grade seluruh hasil DIHITUNG. */
    public function kunciKurva(Request $request, int $cycleId)
    {
        /* Alasan hanya wajib bila masih ada hasil yang belum dihitung/tertahan — servicenya
           yang memutuskan, supaya aturan itu tidak hidup di dua tempat. */
        $data = $request->validate(['alasan' => ['nullable', 'string', 'max:500']]);

        return $this->jalankan(fn () => $this->kurva->kunci($cycleId, $this->userId(), $data['alasan'] ?? null));
    }

    /** Membuka kurva: wajib alasan, ditolak bila sudah ada hasil yang terbit. */
    public function bukaKurva(Request $request, int $cycleId)
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'max:500']]);

        return $this->jalankan(fn () => $this->kurva->buka($cycleId, $data['alasan'], $this->userId()));
    }

    public function ubahMetodeGrade(Request $request, int $cycleId)
    {
        $data = $request->validate(['metode' => ['required', 'in:PITA,KURVA']]);

        return $this->jalankan(fn () => $this->kurva->ubahMetode($cycleId, $data['metode'], $this->userId()));
    }

    public function aturGrupKurva(Request $request, int $cycleId)
    {
        $data = $request->validate([
            'grup' => ['required', 'array', 'min:1', 'max:50'],
            'grup.*.kode' => ['required', 'string', 'max:40'],
            'grup.*.nama' => ['required', 'string', 'max:200'],
            'grup.*.level_ids' => ['required', 'array', 'min:1'],
            'grup.*.level_ids.*' => ['required', 'integer'],
        ]);

        return $this->jalankan(fn () => $this->kurva->aturGrupSiklus($cycleId, $data['grup'], $this->userId()));
    }

    private function jalankan(callable $operasi)
    {
        try {
            return ResponseHelper::success($operasi(), 'Berhasil');
        } catch (RuntimeException $e) {
            // 422, bukan 500: aturannya yang menolak, bukan sistemnya yang rusak.
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    /**
     * Nama pelaku untuk riwayat kalibrasi.
     *
     * Disimpan sebagai teks, bukan hanya id: yang membaca riwayat setahun kemudian
     * belum tentu punya akses ke daftar user.
     */
    private function namaPengguna(): ?string
    {
        $u = Auth::user();

        if ($u === null) {
            return null;
        }

        $nama = $u->Nama ?? $u->Username ?? null;

        return $nama === null ? null : (string) $nama;
    }
}
