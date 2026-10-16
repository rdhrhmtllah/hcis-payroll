<?php

namespace App\Http\Controllers\LMS\SOP\Employee;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopAbsensiService;
use App\Services\LMS\SOP\SopKontenViewService;
use App\Services\LMS\SOP\SopPaketService;
use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\SopVersiLampiranService;
use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopVersiItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;
use ZipStream\ZipStream;

/**
 * SOP — Sisi Karyawan (Repository & Detail).
 *
 * Perpustakaan dilayani SopRepositoryReadService (daftar paket, lanjutkan
 * membaca, kewajiban, favorit, pencarian lintas nomor SOP). `apiList()` yang
 * lama DIPERTAHANKAN apa adanya demi kompatibilitas pemanggil yang belum pindah.
 *
 * Gerbang visibilitas hidup di service (basePaket) — jangan pernah menyusun
 * query paket langsung di controller ini.
 */
class SopRepositoryController extends Controller
{
    public function __construct(
        private SopProgressReadService $read,
        private \App\Services\LMS\SOP\SopRepositoryReadService $repo,
    ) {}

    private function decode($id): ?int
    {
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    /** Kode karyawan user login (null bila tak tertaut). */
    private function kodeKaryawan(): ?string
    {
        return optional(Auth::user()->karyawan)->Kode_Karyawan;
    }

    /* ================= HALAMAN (Inertia) ================= */

    /** Halaman Repository (perpustakaan). Route: GET /sop */
    public function index()
    {
        return inertia('LMS/SOP/Employee/Repository/SopRepository', [
            'prioritas' => app(\App\Services\LMS\SOP\SopPrioritasService::class)->untukLayar(),
        ]);
    }

    /** Halaman Detail bundle (silabus). Route: GET /sop/{id} */
    public function show($id)
    {
        // GERBANG PRESENSI DI TINGKAT HALAMAN.
        //
        // Sebelumnya halaman detail tetap dirender dan presensinya cuma kartu di
        // dalamnya — isi SOP sudah terlanjur termuat, hanya konfirmasi yang
        // ditolak. Sekarang halamannya sendiri tak pernah dirender: yang keluar
        // adalah layar check-in. API detail & konten di bawah menolak dengan
        // alasan yang sama, jadi mematikan JavaScript tak membuka apa pun.
        $kode = $this->kodeKaryawan();
        $realId = $this->parseId($id);

        if ($kode && $realId && app(SopAbsensiService::class)->terkunci($kode, $realId)) {
            return inertia('LMS/SOP/Employee/Detail/SopCheckin', [
                'prioritas' => app(\App\Services\LMS\SOP\SopPrioritasService::class)->untukLayar(),
                'idPaket' => $id,
                'paket' => $this->ringkasUntukCheckin($realId, $kode),
            ]);
        }

        return inertia('LMS/SOP/Employee/Detail/SopDetail', [
            'prioritas' => app(\App\Services\LMS\SOP\SopPrioritasService::class)->untukLayar(),
            'idPaket' => $id, // hashid, diteruskan ke Vue untuk fetch detail
        ]);
    }

    /**
     * Bekal layar check-in: cukup untuk MEMPERKENALKAN SOP-nya, tanpa satu pun
     * isi yang sebenarnya.
     *
     * Batas yang dijaga: judul, tujuan/ruang lingkup, komposisi item per TIPE,
     * dan metadata versi. Judul item, isi dokumen, dan pertanyaan kuis tidak
     * pernah ikut — orang perlu tahu apa yang akan dibaca, bukan membacanya
     * lebih dulu.
     */
    private function ringkasUntukCheckin(int $idPaket, string $kode): array
    {
        $p = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Id_SOP_Paket', $idPaket)
            ->first([
                'p.Nama_Paket', 'p.Kode_Paket', 'p.Prioritas', 'p.Cover_Path',
                'p.Tujuan_Ruang_Lingkup', 'p.Deskripsi', 'p.Estimasi_Waktu_Baca_Menit',
            ]);

        // Komposisi per tipe — "5 dokumen, 2 formulir, 1 kuis" jauh lebih
        // berguna daripada "8 item" untuk memperkirakan beban di depan.
        $komposisi = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)->where('Flag_Aktif', 'Y')
            ->groupBy('Tipe_Item')->selectRaw('Tipe_Item, COUNT(*) as jml')
            ->get()->mapWithKeys(fn ($r) => [$r->Tipe_Item => (int) $r->jml])->all();

        $versi = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->where('i.Id_SOP_Paket', $idPaket)->where('i.Flag_Aktif', 'Y')
            ->selectRaw('MAX(v.Versi_Ke) as versi, MAX(v.Tanggal_Berlaku) as berlaku')
            ->first();

        $jadwal = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', 'Y')->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->first(['pj.Tipe_Tenggat', 'pj.Tanggal_Deadline']);

        $tenggat = $jadwal && $jadwal->Tipe_Tenggat === 'DEADLINE' && $jadwal->Tanggal_Deadline
            ? Carbon::parse($jadwal->Tanggal_Deadline) : null;

        return [
            'nama' => $p->Nama_Paket ?? 'Paket Dokumen',
            'kode' => $p->Kode_Paket ?? null,
            'prioritas' => $p->Prioritas ?? null,
            'tujuan' => $p->Tujuan_Ruang_Lingkup ?: $p->Deskripsi,
            'cover_url' => (new SopPaketService)->coverUrl($p->Cover_Path ?? null),
            'estimasi' => $p && $p->Estimasi_Waktu_Baca_Menit ? (int) $p->Estimasi_Waktu_Baca_Menit : null,
            'item' => array_sum($komposisi),
            'komposisi' => $komposisi,
            'versi' => $versi && $versi->versi ? (int) $versi->versi : null,
            'berlaku' => $versi && $versi->berlaku ? Carbon::parse($versi->berlaku)->toDateString() : null,
            'tenggat' => $tenggat ? $tenggat->toDateString() : null,
            'sisa_hari' => $tenggat ? (int) Carbon::today()->diffInDays($tenggat, false) : null,
        ];
    }

    /** Penjaga bersama untuk seluruh API yang membawa ISI paket. */
    private function tolakBilaBelumAbsen(string $kode, int $idPaket)
    {
        if (! app(SopAbsensiService::class)->terkunci($kode, $idPaket)) {
            return null;
        }

        // 423 Locked, bukan 403: aksesnya sah, cuma ada syarat yang belum
        // dipenuhi — dan klien perlu bisa membedakan keduanya.
        return ResponseHelper::error('Presensi kehadiran diperlukan sebelum membuka dokumen ini.', 423);
    }

    /* ================= API (dikonsumsi Vue) ================= */

    /**
     * GET /api/v1/sop/repository
     * List paket PUBLISHED yang relevan unit karyawan (paginated).
     */
    /** GET /api/v1/sop/repository/beranda — seluruh isi beranda perpustakaan. */
    public function apiBeranda(Request $request)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            return ResponseHelper::success($this->repo->beranda($kode), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiBeranda gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/repository/paket — daftar paket publik, berpaginasi. */
    public function apiDaftarPaket(Request $request)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $page = max(1, (int) $request->query('page', 1));
            $limit = min(48, max(6, (int) $request->query('limit', 12)));
            $f = $this->saringanDaftar($request);

            $r = $this->repo->daftarPaket($kode, $f, $page, $limit);

            /* Jembatan ke Functional Document: berapa TUGAS yang juga cocok
               dengan kata kunci yang sama. Tanpa ini pencarian jadi jalan
               buntu — orang mengetik "evakuasi", dokumennya memang ada tapi
               berstatus tugas (dan mungkin TERBATAS), lalu halaman menjawab
               "tak ada hasil" dan terasa rusak padahal aturannya sedang
               bekerja dengan benar. Ikut di respons yang sama karena ia
               keterangan atas pencarian ini, bukan permintaan tersendiri. */
            $extra = [];
            if (is_string($f['q']) && mb_strlen(trim($f['q'])) >= 2) {
                $extra['di_tugas'] = $this->repo->cariDiTugas($kode, $f['q']);
            }

            return ResponseHelper::successWithPaginationV2(
                $r['rows'], $page, $limit, $r['total'], 'Semua Dokumen', 200, $extra,
            );
        } catch (\Exception $e) {
            Log::error('SOP repo apiDaftarPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/repository/rekap?sumbu=departemen
     *
     * Isi rail "Kelompokkan per" untuk halaman berjalan. Menerima saringan yang
     * SAMA dengan /repository/paket dan mengembalikan bentuk yang sama dengan
     * `bangunGrup()` di klien, supaya komponen daftar bersama tak perlu tahu
     * halaman ini mengelompokkan di server sementara Functional Document
     * mengelompokkan di memori.
     */
    public function apiRekapSumbu(Request $request)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $sumbu = (string) $request->query('sumbu', 'departemen');
            $saringan = $this->saringanDaftar($request);
            $hasil = $this->repo->rekapSumbu($kode, $saringan, $sumbu);
            $hasil['tree'] = $this->repo->rekapTree($kode, $saringan);

            return ResponseHelper::success($hasil, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiRekapSumbu gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Saringan daftar paket, dibaca SEKALI untuk dua endpoint.
     *
     * Baris dan rekap rail wajib berangkat dari himpunan yang sama persis. Dua
     * pembacaan query string yang terpisah adalah undangan bagi keduanya untuk
     * melenceng diam-diam — dan gejalanya (angka rail tak cocok dengan isi
     * daftar) akan terbaca seperti cacat pengelompokan, bukan cacat parameter.
     */
    private function saringanDaftar(Request $request): array
    {
        return [
            'q' => $request->query('q'),
            'prioritas' => array_values(array_filter(
                is_array($request->query('prioritas'))
                    ? $request->query('prioritas')
                    : array_filter(explode(',', (string) $request->query('prioritas'))),
            )),
            'departemen' => array_values(array_filter(
                is_array($request->query('departemen'))
                    ? $request->query('departemen')
                    : array_filter(explode(',', (string) $request->query('departemen'))),
            )),
            'jenis' => array_values(array_filter(
                is_array($request->query('jenis'))
                    ? $request->query('jenis')
                    : array_filter(explode(',', (string) $request->query('jenis'))),
            )),
            'apqc' => array_values(array_filter(
                is_array($request->query('apqc'))
                    ? $request->query('apqc')
                    : array_filter(explode(',', (string) $request->query('apqc'))),
            )),
            'status' => $request->query('status', 'semua'),
            // Rak sudah PUBLIK semua, jadi tak ada lagi "tanpa tugas";
            // yang tersisa cuma menyempitkan ke yang juga jadi tugas Anda.
            'wajib' => $request->query('wajib') === '1',
            'sumbu' => (string) $request->query('sumbu', 'departemen'),
            'grup' => $request->query('grup'),
            'lensa' => (string) $request->query('lensa', 'semua'),
        ];
    }

    /* ================= KATALOG DOKUMEN ================= */

    /**
     * GET /api/v1/sop/repository/dokumen
     *
     * Perpustakaan yang sama, diproyeksikan per dokumen — bentuk yang identik
     * dengan daftar Dokumen di SOP Saya, hanya lingkupnya paket PUBLIK.
     *
     * Tanpa parameter dan tanpa paginasi: pengelompokan dan penyaringan
     * dikerjakan di klien oleh modul sumbu yang sama dengan SOP Saya, dan rail
     * hanya bisa menghitung jujur bila ia memegang seluruh himpunan.
     */
    public function apiKatalogDokumen()
    {
        try {
            $kode = $this->kodeKaryawan();

            return ResponseHelper::success($this->repo->katalogDokumen($kode), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiKatalogDokumen gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/repository/cari?q= — pencarian lintas nomor SOP & judul item. */
    public function apiCari(Request $request)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $q = (string) $request->query('q', '');

            // `di_tugas` = cacah tugas yang juga cocok. Repository tak boleh
            // merender kartunya (itu wilayah SOP Saya), tapi menyembunyikan
            // keberadaannya membuat pencarian terasa rusak.
            return ResponseHelper::success([
                'hasil' => $this->repo->cari($kode, $q),
                'di_tugas' => $this->repo->cariDiTugas($kode, $q),
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiCari gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/repository/intip/{id} — daftar isi ringkas tanpa meninggalkan rak. */
    public function apiIntip(string $id)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $idPaket = $this->decode($id);
            if (! $idPaket) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $r = $this->repo->intip($kode, $idPaket);

            // null = tak terlihat oleh karyawan ini. Dijawab 404, bukan 403:
            // membedakan "tak boleh" dari "tak ada" membocorkan keberadaan paket.
            return $r
                ? ResponseHelper::success($r, 'Data Ditemukan')
                : ResponseHelper::error('Dokumen tidak ditemukan', 404);
        } catch (\Exception $e) {
            Log::error('SOP repo apiIntip gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/repository/favorit/{id} — sematkan / lepas dari rak pribadi. */
    public function apiFavorit(string $id)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $idPaket = $this->decode($id);
            if (! $idPaket) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $r = $this->repo->toggleFavorit($kode, $idPaket);
            if (! empty($r['ditolak'])) {
                return ResponseHelper::error('Dokumen tidak ditemukan', 404);
            }

            return ResponseHelper::success($r, $r['favorit'] ? 'Disematkan ke rak Anda.' : 'Dilepas dari rak Anda.');
        } catch (\Exception $e) {
            Log::error('SOP repo apiFavorit gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    public function apiList(Request $request)
    {
        try {
            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            $page = (int) $request->query('page', 1);
            $limit = min(48, max(6, (int) $request->query('limit', 12)));
            $filters = [
                'q' => $request->query('q'),
                'prioritas' => $request->query('prioritas'),
            ];

            [$rows, $total] = $this->read->repositoryList($kode, $filters, $page, $limit);
            if ($total === 0) {
                return ResponseHelper::error('Data Tidak Ditemukan', 404);
            }

            return ResponseHelper::successWithPagination($rows, $page, $limit, $total);
        } catch (\Exception $e) {
            Log::error('SOP repo apiList gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    private function parseId($val): ?int
    {
        if (empty($val) || $val === 'undefined' || $val === 'null') {
            return null;
        }
        $d = Hashids::connection('custom')->decode($val);
        if (! empty($d)) {
            return (int) $d[0];
        }
        $d2 = Hashids::decode($val);
        if (! empty($d2)) {
            return (int) $d2[0];
        }
        if (is_numeric($val) && (int) $val > 0) {
            return (int) $val;
        }

        return null;
    }

    /**
     * GET /api/v1/sop/{id}
     * Detail bundle + silabus + status per item.
     */
    public function apiDetail($id)
    {
        try {
            $realId = $this->parseId($id);
            if (! $realId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            if ($tolak = $this->tolakBilaBelumAbsen($kode, $realId)) {
                return $tolak;
            }

            $data = $this->read->detailBundle($kode, $realId);
            if (! $data) {
                return ResponseHelper::error('Dokumen tidak ditemukan', 404);
            }

            return ResponseHelper::success($data, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiDetail gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/{idPaket}/item/{idItem}/versi
     * Riwayat versi item (PUBLISHED/OBSOLETE) + status baca karyawan per versi.
     */
    public function apiItemVersi($idPaket, $idItem)
    {
        try {
            $p = $this->parseId($idPaket);
            $i = $this->parseId($idItem);
            if (! $p || ! $i) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            if ($tolak = $this->tolakBilaBelumAbsen($kode, $p)) {
                return $tolak;
            }

            $data = $this->read->itemVersiHistory($kode, $p, $i);
            if ($data === null) {
                return ResponseHelper::error('Item tidak ditemukan', 404);
            }

            return ResponseHelper::success($data, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiItemVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/{idPaket}/versi/{idVersi}/konten[?unduh=1]
     * URL lihat konten versi (dok/video). Guard: versi milik item paket published & bukan DRAFT.
     * unduh=1 → signed URL dipaksa attachment (dipakai item FORM yang tak punya penampil).
     */
    public function apiVersiKonten(Request $request, $idPaket, $idVersi)
    {
        try {
            $p = $this->parseId($idPaket);
            $v = $this->parseId($idVersi);
            if (! $p || ! $v) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $kode = $this->kodeKaryawan();
            if (! $kode) {
                return ResponseHelper::error('Akun tidak tertaut data karyawan', 403);
            }

            if ($tolak = $this->tolakBilaBelumAbsen($kode, $p)) {
                return $tolak;
            }

            if (! $this->read->versiVisibleInPaket($kode, $p, $v)) {
                return ResponseHelper::error('Konten tidak tersedia', 403);
            }

            /*
             * ISI HANYA DARI VERSI YANG BERLAKU (keputusan produk 2026-08-12).
             *
             * Riwayat versi tetap terbuka — karyawan perlu tahu dokumennya pernah
             * direvisi dan mana yang sudah ia baca — tapi berkasnya tidak. Berkas
             * versi lama yang tetap bisa dibuka adalah cara paling mudah seseorang
             * mengerjakan prosedur yang sudah dicabut, dan itu justru yang hendak
             * dicegah seluruh mesin dokumen terkendali ini.
             *
             * 410, bukan 403: haknya tak berkurang, versinya yang sudah lewat —
             * dan klien perlu bisa membedakan "Anda tak berhak" dari "ini sudah
             * digantikan".
             */
            if (! $this->read->versiBerlakuDiPaket($p, $v)) {
                return ResponseHelper::error(
                    'Versi ini sudah digantikan. Yang bisa dibuka hanya versi yang berlaku.', 410);
            }

            /*
             * Mode_Akses ditegakkan di SINI, bukan hanya di UI.
             *
             * Sebelumnya kolom itu divalidasi saat tulis lalu tak pernah dibaca lagi
             * oleh satu pun pengecekan otorisasi: item VIEW_ONLY tetap mengembalikan
             * signed URL ke PDF asli tanpa watermark, dan tautan itu bisa dibagikan
             * ke siapa pun selama masa berlakunya. Itu meniadakan seluruh rantai
             * secure-PDF (watermark personal, nonce sekali pakai, rate limit) yang
             * dibangun persis untuk mencegahnya.
             *
             * DOKUMEN VIEW_ONLY dilayani reader aman lewat berkas_id + total_halaman,
             * jadi url mentahnya memang tidak diperlukan klien.
             *
             * Sejak 10 Okt 2026 aturannya juga berlaku untuk contoh pengisian
             * FORM yang berupa PDF; FORM non-PDF warisan tetap selalu boleh.
             * Karena itu jenis berkasnya ikut dibaca. Aturannya ada di
             * SopAksesBerkas, dipakai bersama pembaca item.
             */
            /*
             * Itemnya dicari lewat DOKUMEN juga, dan DIBATASI pada paket ini.
             *
             * Versi milik dokumen ber-Id_SOP_Paket_Item NULL, jadi join tunggal
             * tak menemukan apa pun — dan "tak ketemu" di sini tidak berarti
             * aman. Mode_Akses null membuat setiap FORM ditolak diunduh (padahal
             * diunduh justru alasan keberadaannya), sementara Tipe_Item null
             * membuat cabang DOKUMEN VIEW_ONLY di bawah tak pernah jalan
             * sehingga URL mentahnya ikut terkirim — persis kebocoran yang
             * seluruh rantai secure-PDF dibangun untuk mencegahnya.
             *
             * Dibatasi pada $p karena satu dokumen bisa dipakai banyak paket
             * dengan Mode_Akses berbeda; membacanya dari paket sembarang berarti
             * aturan paket lain yang ditegakkan di sini.
             */
            $item = DB::table('N_SOP_Item_Versi as vs')
                ->join('N_SOP_Paket_Item as i', fn ($j) => SopVersiItem::tautkan($j, 'i', 'vs'))
                ->leftJoin('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'vs.Id_Master_Konten')
                ->where('vs.Id_SOP_Item_Versi', $v)
                ->where('i.Id_SOP_Paket', $p)
                ->first(['i.Mode_Akses', 'i.Tipe_Item', 'b.Type_File', 'b.File_Path']);

            $bolehUnduh = SopAksesBerkas::bolehUnduh(
                $item->Mode_Akses ?? null,
                $item->Tipe_Item ?? null,
                $item->Type_File ?? null,
                $item->File_Path ?? null,
            );
            $paksaUnduh = filter_var($request->query('unduh'), FILTER_VALIDATE_BOOLEAN);

            if ($paksaUnduh && ! $bolehUnduh) {
                return ResponseHelper::error('Dokumen ini hanya untuk dibaca, tidak boleh diunduh.', 403);
            }

            $res = (new SopKontenViewService)->forVersi($v, $paksaUnduh);
            if (isset($res['_error'])) {
                return ResponseHelper::error($res['_error'], $res['_code'] ?? 404);
            }

            // DOKUMEN / contoh pengisian FORM yang hanya boleh dilihat: buang url
            // mentah supaya klien wajib lewat reader aman. VIDEO tetap butuh url
            // untuk diputar; FORM non-PDF tak pernah sampai sini (selalu boleh).
            if (! $bolehUnduh && in_array($item->Tipe_Item ?? null, ['DOKUMEN', 'FORM'], true)
                && ! empty($res['berkas_id'])) {
                unset($res['url']);
            }

            return ResponseHelper::success($res, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiVersiKonten gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/{idPaket}/versi/{idVersi}/lampiran/{idLampiran}
     * Form kosong item FORM, selalu sebagai unduhan.
     *
     * Gerbangnya SAMA dengan apiVersiKonten — hak atas paket, absensi, dan
     * hanya versi yang berlaku — lalu lampirannya harus milik versi itu dan
     * masih aktif. Id lampiran yang ditebak dari versi lain tak boleh lolos
     * hanya karena versinya sendiri sah.
     *
     * Tidak mencatat progres: progres item FORM tetap dari "Tandai selesai".
     */
    public function apiVersiLampiran(Request $request, $idPaket, $idVersi, $idLampiran)
    {
        try {
            [$tolak, $p, $v] = $this->gerbangVersiLampiran($idPaket, $idVersi);
            if ($tolak) {
                return $tolak;
            }

            $l = $this->parseId($idLampiran);
            $konten = $l ? app(SopVersiLampiranService::class)->kontenLampiran($l, $v) : null;
            if (! $konten) {
                return ResponseHelper::error('Form kosong tidak ditemukan.', 404);
            }

            $res = (new SopKontenViewService)->forKonten((int) $konten, true);
            if (isset($res['_error'])) {
                return ResponseHelper::error($res['_error'], $res['_code'] ?? 404);
            }

            return ResponseHelper::success($res, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP repo apiVersiLampiran gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/{idPaket}/versi/{idVersi}/lampiran-zip
     * Seluruh form kosong versi ini dalam SATU berkas ZIP.
     *
     * Laporan user 6 Okt 2026: "Unduh Semua" dulu membuka satu tab per berkas,
     * dan browser memblokir semua kecuali yang pertama. Berkasnya dialirkan
     * langsung dari GCS ke ZIP — tak ditampung di memori atau disk server.
     * Gerbangnya sama dengan unduhan per berkas.
     */
    public function apiVersiLampiranZip(Request $request, $idPaket, $idVersi)
    {
        try {
            [$tolak, $p, $v] = $this->gerbangVersiLampiran($idPaket, $idVersi);
            if ($tolak) {
                return $tolak;
            }

            $disk = Storage::disk('gcs');
            $berkas = array_values(array_filter(
                app(SopVersiLampiranService::class)->berkasZip($v),
                fn ($b) => $disk->exists($b['path']),
            ));
            if ($berkas === []) {
                return ResponseHelper::error('Belum ada form kosong yang bisa diunduh.', 404);
            }

            $judul = DB::table('N_SOP_Item_Versi as vs')
                ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'vs.Id_SOP_Dokumen')
                ->where('vs.Id_SOP_Item_Versi', $v)
                ->value('d.Judul');
            $namaZip = 'Form kosong - '.(trim(preg_replace('#[\\\\/:*?"<>|]+#', ' ', (string) $judul)) ?: 'SOP').'.zip';

            return response()->streamDownload(function () use ($berkas, $disk) {
                $zip = new ZipStream(sendHttpHeaders: false, defaultEnableZeroHeader: true);
                foreach ($berkas as $b) {
                    $aliran = $disk->readStream($b['path']);
                    if (! $aliran) {
                        continue;
                    }
                    $zip->addFileFromStream(fileName: $b['nama'], stream: $aliran);
                    if (is_resource($aliran)) {
                        fclose($aliran);
                    }
                }
                $zip->finish();
            }, $namaZip, ['Content-Type' => 'application/zip']);
        } catch (\Exception $e) {
            Log::error('SOP repo apiVersiLampiranZip gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Gerbang bersama unduhan form kosong — sama dengan apiVersiKonten: hak
     * atas paket, absensi, dan hanya versi yang berlaku.
     *
     * @return array{0: \Illuminate\Http\JsonResponse|null, 1: int|null, 2: int|null}
     */
    private function gerbangVersiLampiran($idPaket, $idVersi): array
    {
        $p = $this->parseId($idPaket);
        $v = $this->parseId($idVersi);
        if (! $p || ! $v) {
            return [ResponseHelper::error('ID tidak valid', 400), null, null];
        }

        $kode = $this->kodeKaryawan();
        if (! $kode) {
            return [ResponseHelper::error('Akun tidak tertaut data karyawan', 403), null, null];
        }

        if ($tolak = $this->tolakBilaBelumAbsen($kode, $p)) {
            return [$tolak, null, null];
        }

        if (! $this->read->versiVisibleInPaket($kode, $p, $v)) {
            return [ResponseHelper::error('Konten tidak tersedia', 403), null, null];
        }

        if (! $this->read->versiBerlakuDiPaket($p, $v)) {
            return [ResponseHelper::error(
                'Versi ini sudah digantikan. Yang bisa dibuka hanya versi yang berlaku.', 410), null, null];
        }

        return [null, $p, $v];
    }
}
