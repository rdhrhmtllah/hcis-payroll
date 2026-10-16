<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\MasterData;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaGrupKurvaMasterService;
use App\Services\KPI\PerformanceAppraisal\PaCompetencyRequirementService;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnCatalogService;
use App\Services\KPI\PerformanceAppraisal\PaJenisPenilaiMatriksService;
use App\Services\KPI\PerformanceAppraisal\PaKelayakanMasaKerjaService;
use App\Services\KPI\PerformanceAppraisal\PaKompetensiMatriksService;
use App\Services\KPI\PerformanceAppraisal\PaKoreksiLevelPaService;
use App\Services\KPI\PerformanceAppraisal\PaLevelMasterService;
use App\Services\KPI\PerformanceAppraisal\PaMasterReadService;
use App\Services\KPI\PerformanceAppraisal\PaMasterWriteService;
use App\Services\KPI\PerformanceAppraisal\PaQuestionBindingMasterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Admin Performance Appraisal — master data.
 *
 * SATU CONTROLLER UNTUK DELAPAN MASTER
 *
 * Nama master datang sebagai bagian alamat, bukan sebagai delapan controller yang
 * hampir sama. Yang membedakan tiap master bukan bentuk permintaannya melainkan
 * aturannya, dan aturan itu tinggal di service. Delapan controller berarti delapan
 * tempat yang harus diingat ketika bentuk responsnya berubah.
 *
 * Master yang tidak dikenal DITOLAK dengan menyebut daftar yang tersedia — bukan
 * dijawab kosong. Salah ketik nama master harus terasa, bukan tampak seperti data yang
 * memang habis.
 */
class PaMasterDataController extends Controller
{
    public function __construct(
        private readonly PaMasterReadService $baca,
        private readonly PaMasterWriteService $tulis,
        private readonly PaCompetencyRequirementService $syarat,
        private readonly PaQuestionBindingMasterService $ikatanBaku,
        private readonly PaHcLearnCatalogService $katalog,
        private readonly PaJenisPenilaiMatriksService $jenisPenilai,
        private readonly PaGrupKurvaMasterService $grupKurva,
        private readonly PaKelayakanMasaKerjaService $kelayakanMasaKerja,
    ) {}

    public function index(Request $request, ?string $master = null)
    {
        if ($master === 'paket-soal') {
            return redirect('/performance-appraisal/master/ikatan-paket-soal');
        }

        /* Daftarnya DISARING menurut hak orang yang membuka, bukan dikirim utuh.

           Sebelum 3 September 2026 seluruh delapan master dikirim apa adanya, sehingga
           layar menampilkan pilihan yang begitu diklik menjawab 403 dari API-nya —
           gerbang rute memakai logika "salah satu", jadi memegang satu master saja sudah
           cukup untuk membuka alamat master mana pun, dan yang menahannya baru
           `pastikanBolehMaster()` di lapisan data.

           Datanya memang tidak pernah bocor. Yang bocor adalah HARAPAN: menawarkan pintu
           yang pasti tertutup membuat orang mengira sistemnya rusak, bukan mengira dirinya
           tidak berhak. */
        $boleh = $this->masterYangBoleh($request);

        return inertia('KPI/PerformanceAppraisal/MasterData/master', [
            // Master yang dituju, atau null bila dibuka dari akar. Divalidasi di sini
            // supaya alamat yang salah ketik tidak mendarat di layar kosong tanpa sebab —
            // dan yang tidak boleh dibuka orang ini diperlakukan sama seperti yang tidak ada.
            'master' => $master !== null && in_array($master, $boleh, true) ? $master : null,
            'daftar_master' => $boleh,
        ]);
    }

    /**
     * Master yang benar-benar boleh dibuka orang yang sedang login.
     *
     * @return list<string>
     */
    private function masterYangBoleh(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return array_values(array_filter(
            PaMasterReadService::daftarMaster(),
            fn (string $m): bool => $user->memilikiJabatan(PaMasterReadService::halamanMaster($m))
                || ($user->memilikiJabatan('PerformanceAppraisalPage') && in_array($m, ['paket-soal', 'butir-soal', 'bobot-pilar', 'aturan-penilai'], true)),
        ));
    }

    /**
     * Seluruh daftar acuan untuk dropdown, satu panggilan.
     */
    public function referensi(Request $request)
    {
        return $this->jalankan(fn () => $this->baca->referensi());
    }

    public function daftar(Request $request, string $master)
    {
        $this->pastikanBolehMaster($request, $master);

        $saringan = $request->validate([
            'q' => 'nullable|string|max:100',
            'flag_aktif' => 'nullable|in:Y,T',
            'cycle_id' => 'nullable|integer',
            'package_id' => 'nullable|integer',
            'id_level' => 'nullable|integer',
            'sumbu' => 'nullable|string|max:20',
            'kegunaan' => 'nullable|string|max:20',
            'halaman' => 'nullable|integer|min:1',
            'per_halaman' => 'nullable|integer|min:1',
        ]);

        return $this->jalankan(fn () => $this->baca->daftar($master, $saringan));
    }

    public function detail(Request $request, string $master, int $id)
    {
        $this->pastikanBolehMaster($request, $master);

        return $this->jalankan(fn () => $this->baca->detail($master, $id));
    }

    public function simpan(Request $request, string $master)
    {
        $this->pastikanBolehMaster($request, $master);

        return $this->jalankanTulis(fn () => $this->tulis->simpan(
            $master, null, $request->all(), $this->userId()
        ));
    }

    public function perbarui(Request $request, string $master, int $id)
    {
        $this->pastikanBolehMaster($request, $master);

        return $this->jalankanTulis(fn () => $this->tulis->simpan(
            $master, $id, $request->all(), $this->userId()
        ));
    }

    /**
     * Menonaktifkan, BUKAN menghapus. Barisnya dirujuk salinan siklus dan jejak hasil.
     */
    public function aktifkan(Request $request, string $master, int $id)
    {
        $this->pastikanBolehMaster($request, $master);

        return $this->jalankanTulis(function () use ($master, $id) {
            $peringatan = $this->tulis->aktifkan($master, $id, $this->userId());

            return ['master' => $master, 'id' => $id, 'flag_aktif' => 'Y', 'peringatan' => $peringatan];
        });
    }

    public function nonaktifkan(Request $request, string $master, int $id)
    {
        $this->pastikanBolehMaster($request, $master);

        return $this->jalankanTulis(function () use ($master, $id) {
            $this->tulis->nonaktifkan($master, $id, $this->userId());

            return ['master' => $master, 'id' => $id, 'flag_aktif' => 'T'];
        });
    }

    /**
     * Menyimpan SELURUH rincian kategori satu aturan penilai sekaligus.
     *
     * Sengaja tidak per baris: bobot antar kategori harus berjumlah 100, dan itu hanya
     * bisa diperiksa ketika seluruh anggotanya diketahui.
     */
    public function simpanRincianAturan(Request $request, int $policyId)
    {
        $this->pastikanBolehMaster($request, 'aturan-penilai');

        $data = $request->validate([
            'rincian' => 'required|array|min:1',
            'rincian.*.pa_rater_category_id' => 'required|integer',
            'rincian.*.bobot_persen' => 'required|numeric|min:0|max:100',
            'rincian.*.min_rater' => 'nullable|integer|min:1',
            'rincian.*.maks_rater' => 'nullable|integer|min:1',
            'rincian.*.min_rater_selesai' => 'nullable|integer|min:1',
            'rincian.*.organization_relationship_type_id' => 'nullable|integer',
            'rincian.*.flag_izinkan_manual' => 'nullable|in:Y,T',
            'rincian.*.urutan_tampil' => 'nullable|integer|min:1',
            'rincian.*.catatan_aturan' => 'nullable|string|max:500',
        ]);

        return $this->jalankanTulis(fn () => $this->tulis->simpanRincianAturan(
            $policyId, $data['rincian'], $this->userId()
        ));
    }

    public function aktifkanAturan(Request $request, int $policyId)
    {
        $this->pastikanBolehMaster($request, 'aturan-penilai');

        return $this->jalankanTulis(fn () => $this->tulis->aktifkanAturanPenilai($policyId, $this->userId()));
    }

    /**
     * Membaca. TIDAK dibungkus transaksi.
     *
     * RCSI mati di `Web_HRIS`, sehingga pembacaan di dalam transaksi menahan kunci
     * bersama lebih lama daripada yang dibutuhkannya — dan pembaca yang menahan kunci
     * adalah persis pola yang membuat layar orang lain menggantung. Membaca tidak butuh
     * transaksi; membungkusnya hanya menambah biaya tanpa menambah jaminan.
     */
    /**
     * Halaman matriks dimensi penilaian x level.
     *
     * Berdiri sendiri, bukan salah satu tab `master.vue`: bentuknya kisi, bukan daftar
     * baris, dan memaksanya ke dalam kerangka daftar akan mengubur satu-satunya hal yang
     * ditanyakan orang tentang tabel ini -- perbandingan antar level.
     */
    public function halamanSyaratKompetensi()
    {
        // Matriks per KOMPETENSI dan tingkatnya (DDL-2026-175) menggantikan matriks per jenis.
        return inertia('KPI/PerformanceAppraisal/MasterData/matriks-kompetensi');
    }

    /* ------------------------------------------------------------------ *
     * Matriks level x jenis penilai                                       *
     * ------------------------------------------------------------------ */

    public function halamanGrupKurva(Request $request)
    {
        $this->pastikanBolehGrupKurva($request);

        return inertia('KPI/PerformanceAppraisal/MasterData/grup-kurva');
    }

    public function daftarGrupKurva(Request $request)
    {
        $this->pastikanBolehGrupKurva($request);

        return $this->jalankan(fn () => $this->grupKurva->daftar());
    }

    /** Menyimpan SELURUH konfigurasi grup level kurva sekaligus. */
    public function simpanGrupKurva(Request $request)
    {
        $this->pastikanBolehGrupKurva($request);

        $data = $request->validate([
            'grup' => ['required', 'array', 'min:1', 'max:50'],
            'grup.*.kode' => ['required', 'string', 'max:40'],
            'grup.*.nama' => ['required', 'string', 'max:200'],
            'grup.*.level_ids' => ['required', 'array', 'min:1'],
            'grup.*.level_ids.*' => ['required', 'integer'],
        ]);

        return $this->jalankanTulis(fn () => $this->grupKurva->simpan($data['grup'], $this->userId()));
    }

    /**
     * Gerbang rute master menerima hak master MANA PUN; grup kurva menentukan pembanding grade
     * seluruh karyawan, jadi haknya diperiksa sendiri.
     */
    private function pastikanBolehGrupKurva(Request $request): void
    {
        abort_unless((bool) $request->user()?->memilikiJabatan('PaMasterGrupKurvaPage'), 403,
            'Anda tidak punya akses ke master Grup Level Kurva.');
    }

    public function halamanJenisPenilai()
    {
        return inertia('KPI/PerformanceAppraisal/MasterData/jenis-penilai');
    }

    /**
     * Matriks lengkap: seluruh level x seluruh kategori penilai.
     *
     * Bobot dan kuotanya DIBACA dari kebijakan penilai; yang menyuntingnya tetap layar
     * Komposisi Penilai. Satu penulis, banyak pembaca — supaya keduanya tidak pernah bisa
     * berselisih.
     */
    public function matriksJenisPenilai(Request $request)
    {
        return $this->jalankan(fn () => $this->jenisPenilai->matriks());
    }

    /**
     * Menata urutan pengisian kategori penilai. Hak per master DIPERIKSA: urutan ini menata
     * wizard setiap penilai, bukan sekadar tampilan satu layar admin.
     */
    public function tataUrutanKategoriPenilai(Request $request)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'urutan' => ['required', 'array', 'min:1'],
            'urutan.*' => ['required', 'string', 'max:40', 'distinct'],
        ]);

        return $this->jalankanTulis(fn () => $this->jenisPenilai->tataUrutanKategori(
            array_values($data['urutan']),
            $this->userId(),
        ));
    }

    /** Menyalakan atau mematikan Kalibrasi Penilai untuk satu kategori. */
    public function setelBolehKalibrasi(Request $request, int $kategoriId)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate(['boleh' => ['required', 'boolean']]);

        return $this->jalankanTulis(fn () => $this->jenisPenilai->setelBolehKalibrasi(
            $kategoriId,
            (bool) $data['boleh'],
            $this->userId(),
        ));
    }

    /**
     * Menandai atau mencabut satu sel matriks: kategori ini menilai level itu, atau tidak.
     *
     * Tidak meminta bobot — ini langkah PERTAMA; bobot dan kuotanya menyusul di Komposisi
     * Penilai.
     */
    public function setelSelJenisPenilai(Request $request, int $levelId)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'pa_rater_category_id' => ['required', 'integer', 'min:1'],
            'berlaku' => ['required', 'boolean'],
        ]);

        return $this->jalankan(fn () => $this->jenisPenilai->setelSel(
            $levelId,
            (int) $data['pa_rater_category_id'],
            (bool) $data['berlaku'],
            Auth::id(),
        ));
    }

    /** Mengecualikan satu level dari Performance Appraisal. Alasannya wajib. */
    public function kecualikanLevel(Request $request, int $levelId)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->jalankan(fn () => $this->jenisPenilai->kecualikan(
            $levelId,
            $data['alasan'],
            Auth::id(),
        ));
    }

    /** Minimal masa kerja master (DDL-2026-191): nilai awal setiap siklus baru. */
    public function kelayakanMasaKerja()
    {
        return $this->jalankan(fn () => $this->kelayakanMasaKerja->master());
    }

    /**
     * Hak yang sama dengan pengecualian level: keduanya menjawab "siapa yang tidak dinilai".
     * Hanya siklus yang dibuat SESUDAH ini yang memakai angka baru.
     */
    public function simpanKelayakanMasaKerja(Request $request)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'min_masa_kerja_bulan' => ['required', 'integer', 'min:0', 'max:'.PaKelayakanMasaKerjaService::MAKS_BULAN],
        ]);

        return $this->jalankan(fn () => $this->kelayakanMasaKerja->simpanMaster(
            (int) $data['min_masa_kerja_bulan'], Auth::id(),
        ));
    }

    /** Mencabut pengecualian satu level. */
    public function cabutPengecualianLevel(Request $request, int $levelId)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        return $this->jalankan(fn () => $this->jenisPenilai->cabut($levelId, Auth::id()));
    }

    /** Mengecualikan banyak level sekaligus dari Performance Appraisal. */
    public function kecualikanBanyakLevel(Request $request)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'level_ids' => ['required', 'array', 'min:1'],
            'level_ids.*' => ['required', 'integer', 'min:1'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return $this->jalankan(fn () => $this->jenisPenilai->kecualikanBanyak(
            $data['level_ids'],
            $data['alasan'],
            Auth::id(),
        ));
    }

    /** Mencabut pengecualian banyak level sekaligus. */
    public function cabutBanyakPengecualianLevel(Request $request)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'level_ids' => ['required', 'array', 'min:1'],
            'level_ids.*' => ['required', 'integer', 'min:1'],
        ]);

        return $this->jalankan(fn () => $this->jenisPenilai->cabutBanyak(
            $data['level_ids'],
            Auth::id(),
        ));
    }

    /** Menyimpan rincian komposisi penilai (bobot & kuota) untuk satu level jabatan. */
    public function simpanKomposisiLevel(Request $request, int $levelId)
    {
        $this->pastikanBolehMaster($request, 'kategori-penilai');

        $data = $request->validate([
            'rincian' => ['required', 'array', 'min:1'],
            'rincian.*.pa_rater_category_id' => ['required', 'integer', 'min:1'],
            'rincian.*.bobot_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'rincian.*.min_rater' => ['nullable', 'integer', 'min:1'],
            'rincian.*.maks_rater' => ['nullable', 'integer', 'min:1'],
            'rincian.*.min_rater_selesai' => ['nullable', 'integer', 'min:1'],
            'rincian.*.organization_relationship_type_id' => ['nullable', 'integer'],
            'rincian.*.flag_izinkan_manual' => ['nullable', 'in:Y,T'],
            'rincian.*.urutan_tampil' => ['nullable', 'integer', 'min:1'],
            'rincian.*.catatan_aturan' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->jalankanTulis(fn () => $this->jenisPenilai->simpanKomposisi(
            $levelId,
            $data['rincian'],
            $this->userId()
        ));
    }

    /* ------------------------------------------------------------------ *
     * Matriks dimensi penilaian x level                                   *
     * ------------------------------------------------------------------ */

    /**
     * Matriks lengkap. Dikirim sekaligus, bukan per level: yang ditanyakan orang tentang
     * tabel ini selalu berbentuk perbandingan antar level, dan memuatnya sepotong-sepotong
     * membuat perbandingan itu mustahil.
     */
    public function matriksSyaratKompetensi(Request $request)
    {
        return $this->jalankan(fn () => $this->syarat->matriks());
    }

    /**
     * Menyimpan satu baris matriks: dimensi apa saja yang berlaku pada satu level.
     *
     * SELURUH dimensi level itu dikirim, bukan yang berubah saja. Satu baris matriks hanya
     * bermakna bila seluruh selnya dilihat bersamaan.
     */
    public function simpanSyaratKompetensi(Request $request, int $levelId)
    {
        $this->pastikanBolehMaster($request, 'kompetensi');

        $data = $request->validate([
            'dimensi' => ['present', 'array'],
            'dimensi.*' => ['required', 'string', 'max:30'],
        ]);

        return $this->jalankanTulis(fn () => $this->syarat->simpan(
            $levelId, $data['dimensi'], $this->userId(),
        ));
    }

    /* ------------------------------------------------------------------ *
     * Koreksi Level PA per karyawan (DDL-2026-177)                        *
     * ------------------------------------------------------------------ */

    private function pastikanBolehLevelPa(Request $request): void
    {
        abort_unless((bool) $request->user()?->memilikiJabatan('PaMasterLevelPaPage'), 403,
            'Anda tidak punya akses ke master Koreksi Level PA.');
    }

    /** Master Level PA (DDL-2026-183): halaman sendiri; pemegang Koreksi Level PA juga boleh. */
    private function pastikanBolehLevelMaster(Request $request): void
    {
        $u = $request->user();
        abort_unless((bool) ($u?->memilikiJabatan('PaMasterLevelMasterPage') || $u?->memilikiJabatan('PaMasterLevelPaPage')), 403,
            'Anda tidak punya akses ke master Level PA.');
    }

    public function halamanLevelMaster(Request $request)
    {
        $this->pastikanBolehLevelMaster($request);

        return inertia('KPI/PerformanceAppraisal/MasterData/level');
    }

    public function halamanLevelPa(Request $request)
    {
        $this->pastikanBolehLevelPa($request);

        return inertia('KPI/PerformanceAppraisal/MasterData/level-pa');
    }

    public function daftarLevelPa(Request $request)
    {
        $this->pastikanBolehLevelPa($request);
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:AKTIF,PERLU_DITINJAU,BELUM_BERLAKU,KEDALUWARSA,DICABUT'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return $this->jalankan(fn () => app(PaKoreksiLevelPaService::class)->daftar($data));
    }

    public function cariKaryawanLevelPa(Request $request)
    {
        $this->pastikanBolehLevelPa($request);
        $q = (string) $request->validate(['q' => ['required', 'string', 'max:100']])['q'];

        return $this->jalankan(fn () => app(PaKoreksiLevelPaService::class)->cariKaryawan($q));
    }

    /** Tahap 5: karyawan yang level HRIS-nya kemungkinan tidak sesuai pekerjaannya (bahan tinjauan). */
    public function calonLevelPa(Request $request)
    {
        $this->pastikanBolehLevelPa($request);
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        return $this->jalankan(fn () => app(PaKoreksiLevelPaService::class)->calon($data));
    }

    public function simpanLevelPa(Request $request)
    {
        $this->pastikanBolehLevelPa($request);

        /* Banyak orang sekaligus (formulir Tambah): `karyawan` = daftar {kode_perusahaan, kode_karyawan}.
           Satu orang (Calon terdeteksi / klien lama): kode_perusahaan + kode_karyawan di akar. */
        if ($request->has('karyawan')) {
            $karyawan = $request->validate([
                'karyawan' => ['required', 'array', 'min:1', 'max:'.PaKoreksiLevelPaService::MAKS_SEKALIGUS],
                'karyawan.*.kode_perusahaan' => ['required', 'string', 'max:3'],
                'karyawan.*.kode_karyawan' => ['required', 'string', 'max:255'],
            ])['karyawan'];

            return $this->jalankanTulis(fn () => app(PaKoreksiLevelPaService::class)->simpanBanyak($karyawan, $this->validasiLevelPa($request, false), $this->userId()));
        }

        return $this->jalankanTulis(fn () => app(PaKoreksiLevelPaService::class)->simpan($this->validasiLevelPa($request, true), $this->userId()));
    }

    public function ubahLevelPa(Request $request, int $id)
    {
        $this->pastikanBolehLevelPa($request);

        return $this->jalankanTulis(fn () => app(PaKoreksiLevelPaService::class)->ubah($id, $this->validasiLevelPa($request, false), $this->userId()));
    }

    public function cabutLevelPa(Request $request, int $id)
    {
        $this->pastikanBolehLevelPa($request);
        $alasan = (string) $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']])['alasan'];

        return $this->jalankanTulis(fn () => app(PaKoreksiLevelPaService::class)->cabut($id, $alasan, $this->userId()));
    }

    /* ------------------------------------------------------------------ *
     * Master Level PA (DDL-2026-183) — halaman Master · Level PA (atau pemegang Koreksi Level PA).
     * ------------------------------------------------------------------ */

    public function daftarLevelMaster(Request $request)
    {
        $this->pastikanBolehLevelMaster($request);

        return $this->jalankan(fn () => ['level' => app(PaLevelMasterService::class)->daftar($request->boolean('semua'))]);
    }

    /** Keadaan Master Level PA dibanding HRIS_Level — tanpa mengubah apa pun. */
    public function periksaLevelMaster(Request $request)
    {
        $this->pastikanBolehLevelMaster($request);

        return $this->jalankan(fn () => app(PaLevelMasterService::class)->periksaHris());
    }

    /** Menyalin/menyamakan level HRIS sekarang, lalu memeriksa ulang. */
    public function sinkronLevelMaster(Request $request)
    {
        $this->pastikanBolehLevelMaster($request);

        return $this->jalankanTulis(fn () => [
            'hasil' => PaLevelMasterService::sinkronHris(true),
            'periksa' => app(PaLevelMasterService::class)->periksaHris(),
        ]);
    }

    public function tambahLevelMaster(Request $request)
    {
        $this->pastikanBolehLevelMaster($request);
        $d = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'id_level_dasar' => ['required', 'integer'],
            'urutan' => ['required', 'numeric', 'min:0', 'max:9999'],
            'salin_matriks' => ['nullable', 'boolean'],
        ]);

        return $this->jalankanTulis(fn () => app(PaLevelMasterService::class)->tambah(
            $d['nama'], (int) $d['id_level_dasar'], (float) $d['urutan'], (bool) ($d['salin_matriks'] ?? true), $this->userId(),
        ));
    }

    public function ubahLevelMaster(Request $request, int $id)
    {
        $this->pastikanBolehLevelMaster($request);
        $d = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'urutan' => ['required', 'numeric', 'min:0', 'max:9999'],
        ]);

        return $this->jalankanTulis(function () use ($id, $d) {
            app(PaLevelMasterService::class)->ubah($id, $d['nama'], (float) $d['urutan'], $this->userId());

            return ['id' => $id];
        });
    }

    public function aturAktifLevelMaster(Request $request, int $id)
    {
        $this->pastikanBolehLevelMaster($request);
        $aktif = (bool) $request->validate(['aktif' => ['required', 'boolean']])['aktif'];

        return $this->jalankanTulis(function () use ($id, $aktif) {
            app(PaLevelMasterService::class)->aturAktif($id, $aktif, $this->userId());

            return ['id' => $id, 'aktif' => $aktif];
        });
    }

    public function eksporLevelPa(Request $request)
    {
        $this->pastikanBolehLevelPa($request);
        $templat = $request->boolean('templat');
        $isi = app(PaKoreksiLevelPaService::class)->ekspor($templat);
        $nama = ($templat ? 'Templat-Koreksi-Level-PA' : 'Koreksi-Level-PA-'.now()->format('Ymd-His')).'.xlsx';

        return response($isi, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nama.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function imporLevelPa(Request $request)
    {
        $this->pastikanBolehLevelPa($request);
        $berkas = $request->validate(['berkas' => ['required', 'file', 'mimes:xlsx', 'max:5120']])['berkas'];

        return $this->jalankan(fn () => app(PaKoreksiLevelPaService::class)->impor($berkas->getRealPath(), $this->userId()));
    }

    /** @return array<string, mixed> */
    private function validasiLevelPa(Request $request, bool $baru): array
    {
        return $request->validate(array_filter([
            'kode_perusahaan' => $baru ? ['required', 'string', 'max:3'] : null,
            'kode_karyawan' => $baru ? ['required', 'string', 'max:255'] : null,
            'id_level_pa' => ['required', 'integer'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
            'berlaku_dari' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date'],
        ]));
    }

    /* ------------------------------------------------------------------ *
     * Matriks Kompetensi per kompetensi + tingkat (DDL-2026-175)          *
     * ------------------------------------------------------------------ */

    public function matriksKompetensi(Request $request)
    {
        return $this->jalankan(fn () => app(PaKompetensiMatriksService::class)->matriks());
    }

    /** Satu baris level: {kompetensi_id: tingkat_id|null}. Yang tidak disebut = tidak dinilai. */
    public function simpanMatriksKompetensi(Request $request, int $levelId)
    {
        $this->pastikanBolehMaster($request, 'kompetensi');

        $data = $request->validate([
            'isi' => ['present', 'array'],
            'isi.*' => ['nullable', 'integer'],
        ]);

        return $this->jalankanTulis(fn () => app(PaKompetensiMatriksService::class)->simpanLevel(
            $levelId, $data['isi'], $this->userId(),
        ));
    }

    public function tingkatKompetensi(Request $request, int $id)
    {
        return $this->jalankan(fn () => app(PaKompetensiMatriksService::class)->tingkat($id));
    }

    public function simpanTingkatKompetensi(Request $request, int $id)
    {
        $this->pastikanBolehMaster($request, 'kompetensi');

        $data = $request->validate([
            'tingkat' => ['present', 'array', 'max:20'],
            'tingkat.*.id' => ['nullable', 'integer'],
            'tingkat.*.tingkat' => ['required', 'integer', 'min:1', 'max:20'],
            'tingkat.*.nama' => ['required', 'string', 'max:150'],
            'tingkat.*.expected_behaviour' => ['nullable', 'string', 'max:4000'],
        ]);

        return $this->jalankanTulis(fn () => app(PaKompetensiMatriksService::class)->simpanTingkat(
            $id, $data['tingkat'], $this->userId(),
        ));
    }

    /* ------------------------------------------------------------------ *
     * Pilihan paket soal baku per (level x kategori penilai)               *
     * ------------------------------------------------------------------ */

    /**
     * Katalog paket HC Learn, TANPA menyebut siklus.
     *
     * Katalognya milik HC Learn, bukan milik satu siklus. Alamat per-siklus yang sudah ada
     * dipertahankan karena layar wizard memakainya, tetapi halaman master tidak punya siklus
     * untuk disebut — dan memaksanya mengirim id palsu membuat gerbang siklus diperiksa atas
     * siklus yang tidak ada.
     */
    public function katalogPaketSoalMaster(Request $request)
    {
        return $this->jalankan(fn () => $this->katalog->daftarPaket());
    }

    public function halamanIkatanBaku()
    {
        return inertia('KPI/PerformanceAppraisal/MasterData/ikatan-paket-soal');
    }

    /**
     * Matriks baku beserta status kesehatan TERSIMPAN tiap selnya.
     *
     * TIDAK memanggil HC Learn. Status yang dikirim di sini ditulis saat sel terakhir
     * disimpan, disalin ke siklus, atau diperiksa manual — dan cap waktunya ikut dikirim
     * supaya layar bisa menuliskan kapan pemeriksaannya terjadi.
     *
     * Versi lama memeriksa ulang setiap kali dibaca. Akibatnya halaman ini ikut mati ketika
     * HC Learn mati atau lambat, dan sel yang halamannya tak pernah dibuka tetap tak pernah
     * diperiksa juga — jadi harganya dibayar tanpa mendapat jaminan yang dijanjikan.
     */
    public function matriksIkatanBaku(Request $request)
    {
        return $this->jalankan(fn () => $this->ikatanBaku->matriks());
    }

    /**
     * Bagian satu ujian beserta kompetensi wajib level yang dituju.
     *
     * Keduanya dikirim BERSAMA, dari satu permintaan. Layar butuh keduanya untuk bisa menolak
     * lebih awal ketika cacahnya tidak sepadan — dan dua permintaan terpisah membuka jendela
     * di mana layar sudah menggambar 21 baris pelabelan yang ternyata mustahil diisi.
     */
    public function bagianUjian(Request $request)
    {
        $data = $request->validate([
            'id_master_ujian' => ['required', 'string', 'max:100'],
            'id_level' => ['required', 'integer'],
        ]);

        return $this->jalankan(fn () => $this->ikatanBaku->bahanPelabelan(
            $data['id_master_ujian'],
            (int) $data['id_level'],
        ));
    }

    public function simpanIkatanBaku(Request $request)
    {
        $this->pastikanBolehMaster($request, 'paket-soal');

        $data = $request->validate([
            'id_level' => ['required', 'integer'],
            'rater_category_id' => ['nullable', 'integer'],
            'id_master_ujian' => ['required', 'string', 'max:100'],
            'label' => ['required', 'array', 'min:1'],
            'label.*' => ['required', 'integer'],
        ]);

        return $this->jalankanTulis(fn () => $this->ikatanBaku->simpan(
            (int) $data['id_level'],
            isset($data['rater_category_id']) ? (int) $data['rater_category_id'] : null,
            $data['id_master_ujian'],
            array_map('intval', $data['label']),
            $this->userId(),
        ));
    }

    /** Pemeriksaan manual satu sel — tombol "Periksa Sekarang". */
    public function periksaSelIkatan(Request $request, int $id)
    {
        $this->pastikanBolehMaster($request, 'paket-soal');

        return $this->jalankanTulis(fn () => $this->ikatanBaku->periksaSel($id, $this->userId()));
    }

    /**
     * Label satu sel, untuk disalin ke sel lain yang memakai ujian dan level yang sama.
     *
     * Salinannya TERPISAH: memperbaiki label di sel sumber tidak menular ke sel yang sudah
     * menyalin. Itu disengaja — sel per-kategori boleh menyimpang, dan penularan diam-diam
     * akan mengubah sel yang admin tidak sedang buka.
     */
    public function labelIkatan(Request $request, int $id)
    {
        /* Kode & nama kompetensi ikut dikirim: pratinjau pemilih paket menampilkan mapping yang
           TERSIMPAN ("→ K01 Kompetensi X"), bukan "Belum berlabel" untuk sel yang sudah dilabeli. */
        return $this->jalankan(fn () => DB::table('N_PA_Question_Binding_Master_Detail as d')
            ->leftJoin('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'd.PA_Competency_Id')
            ->where('d.PA_Question_Binding_Master_Id', $id)
            ->where('d.Flag_Aktif', 'Y')
            ->orderBy('d.Urutan_Alat_Tes')
            ->orderBy('d.Urutan_Bagian')
            ->get([
                'd.Id_Jenis_Indikator as id_jenis_indikator',
                'd.Id_Jenis_Soal as id_jenis_soal',
                'd.Nama_Alat_Tes as nama_alat_tes',
                'd.Nama_Bagian_Sumber as nama_bagian',
                'd.PA_Competency_Id as kompetensi_id',
                'c.Kode_Competency as kode_kompetensi',
                'c.Nama_Competency as nama_kompetensi',
            ]));
    }

    public function cabutIkatanBaku(Request $request, int $id)
    {
        $this->pastikanBolehMaster($request, 'paket-soal');

        return $this->jalankanTulis(function () use ($id) {
            $this->ikatanBaku->cabut($id, $this->userId());

            return ['dicabut' => $id];
        });
    }

    /**
     * Memeriksa ulang seluruh sel dan menyegarkan sidik susunan yang masih sahih.
     *
     * Sel bermasalah SENGAJA tidak disegarkan — menyegarkannya menghapus bukti bahwa ia
     * bermasalah, dan pemeriksaan berikutnya akan melaporkannya bersih padahal tidak ada
     * yang diperbaiki.
     */
    public function verifikasiIkatanBaku(Request $request)
    {
        $this->pastikanBolehMaster($request, 'paket-soal');

        return $this->jalankanTulis(fn () => $this->ikatanBaku->verifikasiUlang($this->userId()));
    }

    private function jalankan(callable $operasi)
    {
        try {
            return ResponseHelper::success($operasi(), 'Berhasil');
        } catch (RuntimeException $e) {
            // 422, bukan 500: aturannya yang menolak, bukan sistemnya yang rusak.
            // Pesannya ditulis untuk dibaca manusia dan boleh ditampilkan apa adanya.
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    /**
     * Menulis. Dibungkus transaksi supaya penjaga yang menyala SESUDAH menulis —
     * kesinambungan pita nilai, misalnya — membatalkan tulisannya, bukan meninggalkan
     * separuh perubahan.
     */
    private function jalankanTulis(callable $operasi)
    {
        try {
            return ResponseHelper::success(DB::transaction($operasi), 'Berhasil');
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    /**
     * Hak per master, bukan satu hak untuk seluruh admin PA.
     *
     * Rute sudah menjaga bahwa pemanggilnya boleh membuka layar admin sama sekali; yang
     * dijaga di sini adalah master MANA. Tanpa ini, siapa pun yang boleh menyunting
     * kompetensi juga bisa mengubah pita grade lewat alamat yang tinggal diketik —
     * dan pita grade mengubah arti nilai semua orang pada siklus berikutnya.
     */
    /**
     * Hak per master, bukan sekadar "punya salah satu halaman master". Gerbang rute menerima
     * kunci `PaMaster*` mana pun; tanpa pemeriksaan ini pemegang master Grup Kurva bisa mengubah
     * bobot penilai atau ikatan paket soal. Dipanggil di setiap endpoint tulis milik satu master.
     */
    private function pastikanBolehMaster(Request $request, string $master): void
    {
        // Master yang tidak dikenal dibiarkan lewat; service yang menolaknya dengan
        // menyebut daftar yang tersedia, dan pesan itu lebih berguna daripada 403.
        if (! in_array($master, PaMasterReadService::daftarMaster(), true)) {
            return;
        }

        $halaman = PaMasterReadService::halamanMaster($master);
        $user = $request->user();

        if ($user === null) {
            abort(403, "Anda tidak punya akses ke master {$master}.");
        }

        if ($user->memilikiJabatan('PerformanceAppraisalPage') && in_array($master, ['paket-soal', 'butir-soal', 'bobot-pilar', 'aturan-penilai'], true)) {
            return;
        }

        if (! $user->memilikiJabatan($halaman)) {
            abort(403, "Anda tidak punya akses ke master {$master}.");
        }
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
