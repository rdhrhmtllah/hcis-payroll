<?php

namespace App\Http\Controllers\LMS\SOP\Export;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\LMS\SOP\Export\JalankanLaporanRequest;
use App\Http\Requests\LMS\SOP\Export\SimpanTemplateRequest;
use App\Services\LMS\SOP\Export\SopReportExportService;
use App\Services\LMS\SOP\Export\SopTemplateSistem;
use App\Services\LMS\SOP\SopTeamService;
use App\Services\SYSTEM\Application\Reports\DatasetRegistry;
use App\Services\SYSTEM\Application\Reports\DefinisiLaporan;
use App\Services\SYSTEM\Application\Reports\DefinisiValidator;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\ReportRunner;
use App\Services\SYSTEM\Application\Reports\ReportTemplateService;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Studio laporan untuk modul SOP.
 *
 * MODULE DIPATRI DI SINI, tak pernah dibaca dari permintaan. Controller inilah
 * satu-satunya tempat kata 'sop' muncul di jalur ini; seluruh lapisan di
 * bawahnya menerimanya lewat konstruktor atau payload.
 */
class SopExportStudioController extends Controller
{
    private const MODULE = 'sop';

    /**
     * Hak halaman yang berarti "boleh melihat data kepatuhan seluruh organisasi".
     *
     * Harus SAMA dengan daftar di routes/LMS/SOP/Export/SopExportStudio.php.
     * Sebelumnya hanya monitoring yang diakui di sini padahal route sudah
     * menerima keduanya, sehingga pemegang menuSopExportStudioPage bisa membuka
     * halaman tapi jatuh ke lingkup tim -- halaman terbuka, datanya tidak.
     */
    private const PAGE_ADMIN = ['menuSopExportStudioPage', 'menuSopMonitoringPage'];

    /**
     * Dataset yang boleh dibuka Studio SOP.
     *
     * Didaftarkan juga di SopReportServiceProvider; daftar di sini adalah
     * gerbang ROUTE-nya, dan sengaja tidak dibaca dari registry. Registry
     * memuat apa yang ada, daftar ini memuat apa yang boleh dibuka lewat
     * halaman — dua hal yang kebetulan sama hari ini dan tak harus selamanya.
     */
    private const DATASETS = ['kepatuhan', 'dokumen'];

    public function __construct(
        private DatasetRegistry $registry,
        private DefinisiValidator $validator,
        private ReportRunner $runner,
        private SopReportExportService $exportSvc,
        private SopTeamService $team,
    ) {}

    private function templates(): ReportTemplateService
    {
        return new ReportTemplateService(
            self::MODULE,
            $this->registry,
            $this->validator,
            SopTemplateSistem::semua(),
        );
    }

    private function kodeKaryawan(): string
    {
        return (string) optional(Auth::user()->karyawan)->Kode_Karyawan;
    }

    /**
     * Siapa yang menjalankan, dan apa yang boleh ia lihat.
     *
     * Tidak membuat sistem izin baru: kelayakan diikat ke hak halaman admin
     * SOP yang SUDAH ada (System Admin selalu boleh).
     */
    private function lingkup(): Lingkup
    {
        $user = Auth::user();
        $kode = $this->kodeKaryawan();

        // RBAC Fase 4: keputusan halaman lewat titik tunggal (ikut mode modul).
        $keputusan = app(KeputusanAkses::class);
        $adminSop = $user !== null
            && collect(self::PAGE_ADMIN)->contains(fn ($p) => $keputusan->boleh($user, $p));

        return new Lingkup(
            kodeKaryawan: $kode,
            adminPenuh: $adminSop,
            anggotaTim: $adminSop ? [] : $this->team->teamKodes($kode),
            bolehFieldTerbatas: $adminSop,
        );
    }

    /**
     * Halaman Studio.
     *
     * Katalog TIDAK dikirim dari sini. Layar mengambilnya per dataset lewat
     * endpoint katalog; mengirimnya dua kali membuat dua sumber yang bisa
     * berbeda pendapat, dan yang di halaman akan basi lebih dulu.
     */
    public function halaman()
    {
        /*
         * baseUrl TANPA dataset di ujungnya. Halaman yang menempelkannya,
         * karena halaman pula yang tahu dataset mana sedang aktif — dan
         * berpindah dataset karena itu tak perlu memuat ulang halaman.
         */
        return inertia('LMS/SOP/Export/SopExportStudioPage', [
            'baseUrl' => '/api/v1/sop/export-studio',
            'datasetAwal' => 'kepatuhan',
        ]);
    }

    public function katalog(string $studioDataset, string $dataset)
    {
        // Studio yang sedang dibuka dan dataset yang dimintakan katalognya
        // harus SAMA. Membiarkannya berbeda membuat layar Dokumen bisa
        // memuat katalog Kepatuhan lalu merakit definisi yang tak pernah
        // cocok dengan datasetnya sendiri.
        if ($studioDataset !== $dataset || ! $this->datasetValid($dataset)) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        try {
            $ds = $this->registry->ambil(self::MODULE, $dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        return ResponseHelper::success([
            'kunci' => $ds->kunci(),
            'label' => $ds->label(),
            'grain' => $ds->grain(),
            'dimensi' => $ds->dimensi(),
            'ukuran' => $ds->ukuran(),
            'saringan' => $ds->saringan(),

            // Opsi isian saringan ikut di katalog, BUKAN diambil layar dari
            // endpoint modul lain. Sebelumnya ReportStudio memanggil
            // /api/v1/sop/admin/monitoring/filter-options langsung -- komponen
            // milik SYSTEM yang menyebut alamat SOP, dan alamat itu dijaga hak
            // halaman yang berbeda dari Studio. Lewat katalog, hak aksesnya
            // otomatis sama dengan halaman yang sedang dibuka.
            'opsi_saringan' => $ds instanceof PenyediaOpsiSaringan ? $ds->opsiSaringan() : [],
        ]);
    }

    /**
     * Galeri template — DISARING KE DATASET YANG SEDANG DIBUKA.
     *
     * Tanpa saringan ini, membuka Studio Dokumen akan menampilkan template
     * Kepatuhan berikut seluruh kolomnya. Ia bisa dibuka, lalu perakit
     * menemukan kunci-kunci yang tak ada di katalog aktif dan menggugurkannya
     * satu per satu — hasilnya template yang "hilang isinya" tanpa satu pun
     * pesan yang menjelaskan kenapa.
     */
    public function galeri(string $studioDataset)
    {
        if (! $this->datasetValid($studioDataset)) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        return ResponseHelper::success(
            $this->saringGaleri($this->templates()->galeri($this->kodeKaryawan()), $studioDataset)
        );
    }

    public function simpan(string $studioDataset, SimpanTemplateRequest $request)
    {
        $definisi = DefinisiLaporan::dariArray($request->input('definisi', []));

        if (! $this->definisiCocok($studioDataset, $definisi)) {
            return $this->datasetTidakCocok();
        }

        try {
            $ds = $this->registry->ambil(self::MODULE, $definisi->dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $galat);
        }

        // JANGAN pakai $request->validated(): ia hanya mengembalikan field yang
        // dideklarasikan di rules(), sehingga definisi.dimensi dan definisi.ukuran
        // dibuang diam-diam dan template tersimpan kosong. Definisi yang dikirim
        // adalah hasil normalisasi DefinisiLaporan yang baru saja lolos validator.
        return ResponseHelper::success(
            ['kode' => $this->templates()->simpan([
                'nama' => (string) $request->input('nama'),
                'deskripsi' => $request->input('deskripsi'),
                'definisi' => $definisi->toArray(),
                'tingkat_bagi' => $request->input('tingkat_bagi', 'PRIBADI'),
            ], $this->kodeKaryawan())],
            'Template tersimpan.',
            201
        );
    }

    public function perbarui(string $studioDataset, string $kode, SimpanTemplateRequest $request)
    {
        $definisi = DefinisiLaporan::dariArray($request->input('definisi', []));

        if (! $this->definisiCocok($studioDataset, $definisi)) {
            return $this->datasetTidakCocok();
        }

        try {
            $ds = $this->registry->ambil(self::MODULE, $definisi->dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $galat);
        }

        try {
            // Template milik dataset lain tidak boleh ditimpa dari Studio ini,
            // sekalipun kodenya diketahui: hasilnya template yang tiba-tiba
            // berpindah dataset dan lenyap dari galeri pemiliknya.
            $this->pastikanTemplateDataset($studioDataset, $kode);
            $kodeTersimpan = $this->templates()->perbarui($kode, [
                'nama' => (string) $request->input('nama'),
                'deskripsi' => $request->input('deskripsi'),
                'definisi' => $definisi->toArray(),
                'tingkat_bagi' => $request->input('tingkat_bagi', 'PRIBADI'),
            ], $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Template tidak ditemukan atau bukan milik Anda.', 'REPORT_TEMPLATE_FORBIDDEN', 403);
        }

        return ResponseHelper::success(
            ['kode' => $kodeTersimpan],
            'Template diperbarui.',
            200
        );
    }

    public function hapus(string $studioDataset, string $kode)
    {
        try {
            $this->pastikanTemplateDataset($studioDataset, $kode);
            $this->templates()->nonaktifkan($kode, $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Template tidak ditemukan atau bukan milik Anda.', 'REPORT_TEMPLATE_FORBIDDEN', 403);
        }

        return ResponseHelper::success(null, 'Template dinonaktifkan.');
    }

    public function duplikat(string $studioDataset, string $kode)
    {
        try {
            $this->pastikanTemplateDataset($studioDataset, $kode);
            $baru = $this->templates()->duplikat($kode, $this->kodeKaryawan());
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Template tidak ditemukan.', 'REPORT_TEMPLATE_FORBIDDEN', 403);
        }

        return ResponseHelper::success(['kode' => $baru], 'Template diduplikat.', 201);
    }

    public function pratinjau(string $studioDataset, JalankanLaporanRequest $request)
    {
        $kodeTemplate = $request->input('template_kode');
        if ($kodeTemplate) {
            try {
                $tpl = $this->templateDataset($studioDataset, $kodeTemplate);
                $definisi = DefinisiLaporan::dariArray($tpl['definisi'] ?? []);
            } catch (RuntimeException $e) {
                return ResponseHelper::errorWithCode('Template tidak ditemukan.', 'REPORT_TEMPLATE_FORBIDDEN', 403);
            }
        } else {
            $definisi = DefinisiLaporan::dariArray($request->input('definisi', []));
        }

        if (! $this->definisiCocok($studioDataset, $definisi)) {
            return $this->datasetTidakCocok();
        }

        try {
            $ds = $this->registry->ambil(self::MODULE, $definisi->dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        $sortKey = $request->input('sort_key');
        $sortOrder = $request->input('sort_order', 'asc');
        if ($sortKey) {
            $definisi = $definisi->denganUrut([
                ['key' => $sortKey, 'arah' => $sortOrder === 'desc' ? 'desc' : 'asc'],
            ]);
        }

        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $galat);
        }

        $halaman = (int) $request->input('page', 1);
        $perHalaman = (int) $request->input('per_page', 24);
        $pencarian = $request->input('search');
        $hitungTotal = $request->has('hitung_total') ? $request->boolean('hitung_total') : true;

        try {
            $hasil = $this->runner->jalankanPratinjau(
                $definisi,
                $ds,
                $this->lingkup(),
                'PRATINJAU',
                $halaman,
                $perHalaman,
                $pencarian,
                $hitungTotal
            );
        } catch (\Throwable $e) {
            Log::error('REPORT_PREVIEW_FAILED', [
                'module' => self::MODULE,
                'dataset' => $definisi->dataset,
                'definisi_hash' => md5((string) json_encode($definisi->toArray())),
                'exception' => $e,
            ]);

            // Pesan exception TIDAK ikut ke klien: pada kegagalan SQL ia
            // membawa potongan pernyataan dan nama kolom ke peramban. Jejaknya
            // sudah lengkap di log bersama definisi_hash di atas.
            return ResponseHelper::errorWithCode(
                'Pratinjau gagal diproses. Sederhanakan saringan atau kurangi kolom, lalu coba lagi.',
                'REPORT_PREVIEW_FAILED',
                500
            );
        }

        return ResponseHelper::success($hasil);
    }

    public function jalankan(string $studioDataset, JalankanLaporanRequest $request)
    {
        $kodeTemplate = $request->input('template_kode');

        try {
            $sumber = $kodeTemplate
                ? $this->templateDataset($studioDataset, $kodeTemplate)
                : ['nama' => 'Laporan', 'definisi' => $request->input('definisi', [])];
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Template tidak ditemukan.', 'REPORT_TEMPLATE_FORBIDDEN', 403);
        }

        $definisi = DefinisiLaporan::dariArray($sumber['definisi']);

        if (! $this->definisiCocok($studioDataset, $definisi)) {
            return $this->datasetTidakCocok();
        }

        try {
            $ds = $this->registry->ambil(self::MODULE, $definisi->dataset);
        } catch (RuntimeException $e) {
            return ResponseHelper::errorWithCode('Dataset tidak dikenal.', 'REPORT_DATASET_UNKNOWN', 404);
        }

        $galat = $this->validator->periksa($definisi, $ds);
        if ($galat !== []) {
            return ResponseHelper::errorWithCode('Definisi laporan tidak sah.', 'REPORT_DEFINITION_INVALID', 422, $galat);
        }

        $l = $this->lingkup();
        $exportId = $this->exportSvc->makeExportId();

        $customName = trim((string) $request->input('custom_name', ''));
        $bakuNama = $studioDataset === 'dokumen' ? 'Register Dokumen' : 'Laporan Kepatuhan Dokumen';
        $namaLaporan = $customName !== '' ? $customName : (string) ($sumber['nama'] ?? $bakuNama);

        // Cuplikan definisi + lingkup ikut ke payload: mengubah atau menghapus
        // template tidak boleh merusak job yang sudah antre.
        $this->exportSvc->startJob($exportId, (string) $request->input('format', $definisi->formatBawaan), [
            'module' => self::MODULE,
            'template_kode' => $kodeTemplate,
            'nama' => $namaLaporan,
            'judul' => $namaLaporan,
            'custom_name' => $customName,
            'definisi' => $definisi->toArray(),
            'lingkup' => [
                'kode_karyawan' => $l->kodeKaryawan,
                'admin_penuh' => $l->adminPenuh,
                'anggota_tim' => $l->anggotaTim,
                'boleh_field_terbatas' => $l->bolehFieldTerbatas,
            ],
        ], 0, $l->kodeKaryawan);

        $this->exportSvc->dispatchJob($exportId);

        return ResponseHelper::success(['task_id' => $exportId], 'Laporan diproses. Cek panel Aktivitas.', 202);
    }

    /*
    |--------------------------------------------------------------------------
    | Penjaga dataset
    |--------------------------------------------------------------------------
    | Studio SOP memuat lebih dari satu dataset sejak lapis dokumen ditambahkan.
    | Sebelum itu tak ada yang perlu dijaga — satu dataset tidak bisa keliru.
    */

    private function datasetValid(string $dataset): bool
    {
        return in_array($dataset, self::DATASETS, true);
    }

    private function definisiCocok(string $dataset, DefinisiLaporan $definisi): bool
    {
        return $this->datasetValid($dataset) && $definisi->dataset === $dataset;
    }

    private function datasetTidakCocok()
    {
        return ResponseHelper::errorWithCode(
            'Dataset definisi tidak sesuai dengan Studio yang sedang dibuka.',
            'REPORT_DATASET_MISMATCH',
            422
        );
    }

    /**
     * Ambil template DAN pastikan ia milik dataset yang sedang dibuka.
     *
     * Pemeriksaan kepemilikan tetap dikerjakan ReportTemplateService::ambil();
     * yang ditambahkan di sini hanya batas dataset. Keduanya sengaja tidak
     * dilebur: yang satu soal hak, yang lain soal bentuk, dan pesan galatnya
     * pun berbeda.
     */
    private function templateDataset(string $dataset, string $kode): array
    {
        $tpl = $this->templates()->ambil($kode, $this->kodeKaryawan());
        $milik = (string) ($tpl['dataset'] ?? ($tpl['definisi']['dataset'] ?? ''));

        if ($milik !== $dataset) {
            throw new RuntimeException('Template berada pada dataset berbeda.');
        }

        return $tpl;
    }

    private function pastikanTemplateDataset(string $dataset, string $kode): void
    {
        $this->templateDataset($dataset, $kode);
    }

    /**
     * Buang template milik dataset lain dari ketiga rak galeri.
     *
     * Baris dari database datang sebagai objek (kolom Dataset_Key), template
     * sistem sebagai array (kunci 'dataset'). Keduanya harus ditangani; membaca
     * satu bentuk saja membuat rak yang lain lolos tanpa disaring dan
     * gejalanya baru muncul pada rak yang jarang dipakai.
     */
    private function saringGaleri(array $galeri, string $dataset): array
    {
        foreach (['resmi', 'saya', 'dibagikan'] as $rak) {
            $galeri[$rak] = array_values(array_filter(
                (array) ($galeri[$rak] ?? []),
                function ($tpl) use ($dataset) {
                    if (is_object($tpl)) {
                        return (string) ($tpl->Dataset_Key ?? '') === $dataset;
                    }

                    return (string) ($tpl['dataset'] ?? $tpl['Dataset_Key'] ?? '') === $dataset;
                }
            ));
        }

        return $galeri;
    }
}
