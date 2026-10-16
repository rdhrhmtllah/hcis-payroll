<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\SopPenjadwalanTargetService;
use App\Services\LMS\SOP\Support\SopKaryawanToken;
use App\Services\LMS\SOP\Support\SopSebabCabut;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Vinkla\Hashids\Facades\Hashids;

/**
 * SOP — Admin Penjadwalan (distribusi manual).
 * Assign paket PUBLISHED ke karyawan → seed progress + rollup (SopPenjadwalanService).
 */
class SopAdminPenjadwalanController extends Controller
{
    public function __construct(
        private SopPenjadwalanService $service,
        private SopPenjadwalanTargetService $target,
        private \App\Services\LMS\SOP\SopPenjadwalanListService $listSvc,
    ) {}

    /** Normalisasi saringan target dari query/body (array atau CSV). */
    private function filterTarget(Request $request, string $sumber = 'query'): array
    {
        $ambil = fn (string $k) => $sumber === 'query' ? $request->query($k) : $request->input($k);
        $arr = function (string $k) use ($ambil) {
            $v = $ambil($k);
            $list = is_array($v) ? $v : array_filter(explode(',', (string) $v));

            return array_values(array_filter(array_map('trim', $list), fn ($x) => $x !== ''));
        };

        return [
            'q' => $ambil('q'),
            'dept' => $arr('dept'),
            'divisi' => $arr('divisi'),
            'level' => $arr('level'),
            'jabatan' => $arr('jabatan'),
            'lokasi' => $arr('lokasi'),
            'tipe' => $ambil('tipe') ?: 'Semua',
        ];
    }

    private function decode($id): ?int
    {
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    /** GET /sop/admin/penjadwalan */
    public function index()
    {
        return inertia('LMS/SOP/Governance/Admin/SopPenjadwalan');
    }

    /** GET /api/v1/sop/admin/penjadwalan/options/paket */
    public function optionsPaket()
    {
        try {
            $opsi = $this->service->paketPublished();

            // `result` sengaja tetap array datar: SopPenjadwalan.vue dan
            // SopMonitoring.vue membacanya langsung. Penanda pemotongan
            // ditempel sebagai field tambahan di level atas.
            return ResponseHelper::success($opsi['items'], 'Berhasil', 200, [
                'terpotong' => $opsi['terpotong'],
                'batas' => $opsi['batas'],
            ]);
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan optionsPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/admin/penjadwalan/karyawan?q= */
    public function searchKaryawan(Request $request)
    {
        try {
            return ResponseHelper::success($this->service->searchKaryawan($request->query('q'), 20));
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan searchKaryawan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/penjadwalan/list
     *
     * Kartu batch berpaginasi + bersaringan. Kompatibel mundur: `?paket={hash}`
     * tunggal tetap dipahami (dulu satu-satunya saringan yang ada).
     */
    public function list(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 10)));

            $paket = $request->query('paket');
            $paketIds = [];
            foreach (is_array($paket) ? $paket : array_filter(explode(',', (string) $paket)) as $h) {
                if ($id = $this->decode(trim($h))) {
                    $paketIds[] = $id;
                }
            }

            $f = [
                'q' => $request->query('q'),
                'paket' => $paketIds,
                'sumber' => $request->query('sumber'),
                'tenggat' => $request->query('tenggat', 'SEMUA'),
                'dari' => $request->query('dari'),
                'sampai' => $request->query('sampai'),
            ];

            $res = $this->listSvc->batches($f, $page, $limit);

            return ResponseHelper::successWithPaginationV2($res['rows'], $page, $limit, $res['total'], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan list gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /ringkas — KPI strip, saringan sama dengan /list.
     *
     * Endpoint terpisah, bukan disisipkan ke respons /list: successWithPaginationV2
     * adalah helper BERSAMA lintas modul — menambah slot payload di sana demi satu
     * halaman bukan pertukaran yang sepadan.
     */
    public function ringkas(Request $request)
    {
        try {
            $paket = $request->query('paket');
            $paketIds = [];
            foreach (is_array($paket) ? $paket : array_filter(explode(',', (string) $paket)) as $h) {
                if ($id = $this->decode(trim($h))) {
                    $paketIds[] = $id;
                }
            }

            return ResponseHelper::success($this->listSvc->ringkas([
                'q' => $request->query('q'),
                'paket' => $paketIds,
                'sumber' => $request->query('sumber'),
                'tenggat' => $request->query('tenggat', 'SEMUA'),
                'dari' => $request->query('dari'),
                'sampai' => $request->query('sampai'),
            ]), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan ringkas gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /batch/{id} — header satu batch (deep-link ?batch= & dialog edit). */
    public function batch(string $id)
    {
        try {
            $pjId = $this->decode($id);
            if (! $pjId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $row = $this->listSvc->batch($pjId);

            return $row
                ? ResponseHelper::success($row, 'Data Ditemukan')
                : ResponseHelper::error('Penjadwalan tidak ditemukan', 404);
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan batch gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Saringan daftar karyawan di dalam satu batch.
     *
     * SATU pembaca untuk daftar DAN untuk "pilih semua lintas halaman". Kedua
     * endpoint itu memberi makan tombol pencabutan massal yang sama; kalau
     * masing-masing membaca query-nya sendiri, cepat atau lambat salah satunya
     * ketinggalan satu kunci — dan gejalanya adalah admin menyaring satu divisi
     * lalu mencabut akses seluruh batch, dengan layar yang tetap memperlihatkan
     * divisi tadi.
     */
    private function saringanDetail(Request $request): array
    {
        $arr = fn (string $k) => array_values(array_filter(
            is_array($request->query($k)) ? $request->query($k) : array_filter(explode(',', (string) $request->query($k, ''))),
            fn ($x) => trim((string) $x) !== '',
        ));

        return [
            'q' => $request->query('q'),
            'status' => $request->query('status', 'SEMUA'),
            'divisi' => $arr('divisi'),
            'dept' => $arr('dept'),
            'level' => $arr('level'),
            'jabatan' => $arr('jabatan'),
            'lokasi' => $arr('lokasi'),
        ];
    }

    /** GET /batch/{id}/karyawan — drill per-karyawan di dalam batch. */
    public function batchKaryawan(string $id, Request $request)
    {
        try {
            $pjId = $this->decode($id);
            if (! $pjId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 25)));

            $res = $this->listSvc->batchKaryawan($pjId, $this->saringanDetail($request), $page, $limit);
            // BatchKaryawanTable.vue menautkan tiap baris ke halaman monitoring
            // per orang, yang alamatnya bertoken — bukan Kode_Karyawan.
            $rows = SopKaryawanToken::sematkan($res['rows']);

            return ResponseHelper::successWithPaginationV2($rows, $page, $limit, $res['total'], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan batchKaryawan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /batch/{id}/karyawan-kodes — "pilih semua lintas halaman". */
    public function batchKaryawanKodes(string $id, Request $request)
    {
        try {
            $pjId = $this->decode($id);
            if (! $pjId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            return ResponseHelper::success(
                $this->listSvc->batchKaryawanKodes($pjId, $this->saringanDetail($request)),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan batchKaryawanKodes gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /batch/{id}/riwayat — timeline peristiwa batch. */
    public function riwayat(string $id)
    {
        try {
            $pjId = $this->decode($id);
            if (! $pjId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            return ResponseHelper::success($this->listSvc->riwayat($pjId), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan riwayat gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /batch/{id}/detail — ubah label/keterangan/tenggat/reminder satu batch. */
    public function ubahDetail(string $id, Request $request)
    {
        $data = $request->validate([
            'nama_batch' => 'nullable|string|max:150',
            'keterangan' => 'nullable|string|max:500',
            'tipe_tenggat' => 'required|in:DEADLINE,SAMPAI_DICABUT',
            'tanggal_deadline' => 'nullable|date',
            'flag_reminder' => 'nullable|boolean',
            'irama_reminder_hari' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            $pjId = $this->decode($id);
            if (! $pjId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $res = $this->service->ubahDetail($pjId, $data, Auth::user()->Id_Users);

            return $res['ok']
                ? ResponseHelper::success(null, $res['message'])
                : ResponseHelper::error($res['message'], 422);
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan ubahDetail gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /batch/{id}/karyawan — tambah orang ke batch yang sudah ada. */
    public function tambahKaryawan(string $id, Request $request)
    {
        $data = $request->validate([
            /* Pagar terhadap payload absurd, BUKAN plafon fungsional. Ada 958
               karyawan aktif (diukur 2026-09-01), jadi menugaskan seluruh
               organisasi sekaligus adalah pemakaian yang sah — plafon di bawah
               headcount akan mengulang cacat "tempel >1000 lenyap senyap" yang
               sudah pernah dibayar di jalur target penjadwalan. Bila terlampaui,
               Laravel menolak dengan pesan yang terbaca admin, bukan memotong. */
            'kode_karyawan' => 'required|array|min:1|max:5000',
            'kode_karyawan.*' => 'required|string|max:20',
        ]);

        try {
            $pjId = $this->decode($id);
            if (! $pjId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $pj = \Illuminate\Support\Facades\DB::table('N_SOP_Penjadwalan')
                ->where('Id_SOP_Penjadwalan', $pjId)->first(['Id_SOP_Paket']);
            if (! $pj) {
                return ResponseHelper::error('Penjadwalan tidak ditemukan', 404);
            }

            $idPaket = (int) $pj->Id_SOP_Paket;
            if ($gagal = $this->tolakBilaIdentitasKuisTakSah($idPaket)) {
                return $gagal;
            }

            $userId = Auth::user()->Id_Users;
            $kodes = array_values(array_unique(array_filter(array_map('trim', $data['kode_karyawan']))));
            if (empty($kodes)) {
                return ResponseHelper::error('Tidak ada karyawan target.', 422);
            }

            /*
             * Ambang async yang SAMA dengan assign().
             *
             * Sebelumnya jalur ini tak punya gerbang sama sekali: validasinya hanya
             * min:1 tanpa max, dan seluruh daftar dikerjakan dalam SATU transaksi.
             * Padahal biayanya sekelas assign() — tiap karyawan berarti satu
             * insertGetId detail plus seed progress per item wajib — sehingga
             * menambahkan ribuan orang ke batch yang sudah ada menahan lock pada
             * N_SOP_Penjadwalan_Detail, N_SOP_Progress, dan N_SOP_Coverage_Rollup
             * selama menit-menit, dengan pembaca ikut terblokir.
             *
             * Gerbangnya ada di assign() sejak awal; ketiadaannya di sini membuat
             * pengaman itu bisa dilangkahi hanya dengan menambah orang belakangan
             * alih-alih saat membuat batch.
             */
            $ambang = \App\Services\LMS\SOP\Support\SopSetting::int('penjadwalan_async_ambang', 30);

            if (count($kodes) > $ambang) {
                $namaPaket = \Illuminate\Support\Facades\DB::table('N_SOP_Master_Paket')
                    ->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket');

                $task = app(\App\Services\LMS\SOP\SopBulkAssignTaskService::class);
                $taskId = $task->mulai([
                    'id_penjadwalan' => $pjId,
                    'id_paket' => $idPaket,
                    'nama_paket' => $namaPaket,
                    'kodes' => $kodes,
                    'user_id' => $userId,
                ], count($kodes), optional(Auth::user()->karyawan)->Kode_Karyawan);

                \App\Jobs\LMS\SOP\Automation\ProsesBulkAssignSop::dispatch($taskId);

                return ResponseHelper::success(
                    ['task_id' => $taskId, 'total' => count($kodes), 'async' => true],
                    'Penambahan '.count($kodes).' karyawan diproses di latar belakang. Pantau di Pusat Aktivitas.',
                    202,
                );
            }

            $res = \Illuminate\Support\Facades\DB::transaction(
                fn () => $this->service->assignKeHeader($pjId, $idPaket, $kodes, $userId),
            );

            // Notifikasi setelah commit — pola yang sama dengan assign().
            if (! empty($res['targets'])) {
                $nama = \Illuminate\Support\Facades\DB::table('N_SOP_Master_Paket')
                    ->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket') ?? 'Paket Dokumen';
                (new \App\Services\LMS\SOP\SopNotificationService)->notifPenugasan($res['targets'], $idPaket, $nama);

                \App\Services\LMS\SOP\Support\SopLog::catat(
                    'ASSIGN',
                    "Tambah {$res['assigned']} karyawan ke batch yang sudah ada.",
                    $idPaket, null, null, null,
                    ['id_penjadwalan' => $pjId, 'kode_karyawan' => $res['targets'], 'oleh_id_users' => $userId],
                );
            }

            unset($res['targets']);
            $msg = "{$res['assigned']} karyawan ditambahkan"
                .($res['skipped'] > 0 ? ", {$res['skipped']} dilewati (sudah ter-assign)." : '.');

            return ResponseHelper::success($res, $msg, 201);
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan tambahKaryawan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /batch/{id}/bulk-cabut — cabut banyak karyawan sekaligus dari satu batch. */
    public function bulkCabut(string $id, Request $request)
    {
        $data = $request->validate([
            // Pagar payload absurd, bukan plafon fungsional — lihat tambahKaryawan().
            'kode_karyawan' => 'required|array|min:1|max:5000',
            'kode_karyawan.*' => 'required|string|max:20',
            'sebab' => ['required', Rule::in(SopSebabCabut::kunciManual())],
            /*
             * Sebab "LAINNYA" tanpa catatan sama tak menjelaskannya dengan tidak
             * memilih sebab sama sekali — bahkan lebih buruk, karena tampak sudah
             * dijawab. Aturannya dipasang di server, bukan hanya di formulir.
             */
            'catatan' => 'nullable|string|max:500|required_if:sebab,LAINNYA',
        ]);

        try {
            $pjId = $this->decode($id);
            if (! $pjId) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $res = $this->service->bulkCabut(
                $pjId,
                $data['kode_karyawan'],
                Auth::user()->Id_Users,
                $data['sebab'],
                $data['catatan'] ?? null,
            );

            /*
             * "Tak ada yang perlu dicabut" dan "semuanya gagal dicabut" sama-sama
             * menghasilkan dicabut === 0, tapi artinya berlawanan. Menyamakan
             * keduanya sebagai 404 membuat kegagalan total terbaca sebagai keadaan
             * normal, padahal orang-orang di dalamnya masih memegang akses penuh.
             */
            if ($res['dicabut'] === 0 && empty($res['gagal'])) {
                return ResponseHelper::error('Tidak ada penugasan aktif untuk dicabut.', 404);
            }

            if ($res['dicabut'] === 0) {
                $tersisa = array_sum(array_column($res['gagal'], 'jumlah'));

                return ResponseHelper::error(
                    "Pencabutan gagal total — {$tersisa} penugasan MASIH aktif. Silakan ulangi.",
                    500,
                );
            }

            // Seluruh kode diproses lewat chunk; yang perlu dilaporkan hanyalah
            // potongan yang benar-benar gagal, karena orang di dalamnya masih
            // memegang akses dan akan tampil sebagai kewajiban aktif.
            $pesan = "{$res['dicabut']} akses dicabut.";
            if (! empty($res['gagal'])) {
                $tersisa = array_sum(array_column($res['gagal'], 'jumlah'));
                $pesan .= " {$tersisa} penugasan GAGAL dicabut — ulangi untuk sisanya.";
            }

            return ResponseHelper::success($res, $pesan);
        } catch (\Throwable $e) {
            // \Throwable, bukan \Exception: galat tingkat PHP (\Error) lolos dari
            // catch(\Exception) dan keluar sebagai 500 kosong tanpa satu baris log,
            // persis yang sempat terjadi lewat facade tanpa import di service ini.
            Log::error('SOP penjadwalan bulkCabut gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Tolak penjadwalan bila paketnya memuat KUIS sementara identitas HC Learn
     * admin penjadwal tidak diterima di seberang.
     *
     * Kenapa harus preflight NYATA, bukan sekadar cek kolom User_Key terisi:
     * H45 dan A4 memegang User_Key yang SAMA, tapi H45 ditolak 401 sementara A4
     * diterima — HC Learn memvalidasi pasangan X-App-ID + X-Kode-Karyawan. Cek
     * kolom akan meloloskan H45 di sini, lalu gagal saat karyawan menekan tombol
     * ujian: kegagalannya cuma berpindah ke titik yang jauh lebih mahal.
     *
     * Dilewati untuk paket tanpa item KUIS — SOP biasa tak menyentuh HC Learn.
     *
     * @return \Illuminate\Http\JsonResponse|null null = lolos
     */
    private function tolakBilaIdentitasKuisTakSah(int $idPaket)
    {
        // Logika pindah ke service (cekIdentitasKuis) supaya /preflight bisa
        // memakainya advisori; di sini tetap MEMBLOK seperti semula.
        $cek = $this->service->cekIdentitasKuis($idPaket, Auth::user());

        return in_array($cek['status'], ['DITOLAK', 'TIDAK_TERTAUT'], true)
            ? ResponseHelper::error($cek['pesan'], 422)
            : null;
    }

    /** GET /options/filter — opsi saringan organisasi (semua karyawan aktif). */
    public function optionsFilter()
    {
        try {
            return ResponseHelper::success($this->target->filterOptions());
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan optionsFilter gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /options/kandidat — picker karyawan berpaginasi + kesadaran sudah-assign. */
    public function optionsKandidat(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 20)));
            $idPaket = $request->query('paket') ? $this->decode($request->query('paket')) : null;

            [$rows, $total] = $this->target->kandidat($this->filterTarget($request), $page, $limit, $idPaket);

            return ResponseHelper::successWithPaginationV2($rows, $page, $limit, $total, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan optionsKandidat gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /options/kandidat-ringkas — pratinjau dampak (mode unit / daftar kode). */
    public function kandidatRingkas(Request $request)
    {
        try {
            $idPaket = $this->decode((string) $request->query('paket'));
            if (! $idPaket) {
                return ResponseHelper::error('ID paket tidak valid', 400);
            }

            $kodes = $request->query('kodes');
            $kodes = is_array($kodes) ? $kodes : (is_string($kodes) && $kodes !== '' ? explode(',', $kodes) : null);

            return ResponseHelper::success(
                $this->target->hitungRingkas($this->filterTarget($request), $kodes, $idPaket),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan kandidatRingkas gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /options/validasi-tempel — laporan validasi daftar NIK/kode tempelan. */
    public function validasiTempel(Request $request)
    {
        $v = $request->validate([
            'teks' => 'required|string|max:50000',
            'paket' => 'nullable|string',
        ]);

        try {
            $idPaket = ! empty($v['paket']) ? $this->decode($v['paket']) : null;

            return ResponseHelper::success($this->target->validasiTempel($v['teks'], $idPaket), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan validasiTempel gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /preflight — panel Review wizard: seluruh pemeriksaan DITAMPILKAN
     * sebelum submit, bukan gagal mendadak saat submit.
     */
    public function preflight(Request $request)
    {
        try {
            $idPaket = $this->decode((string) $request->query('paket'));
            if (! $idPaket) {
                return ResponseHelper::error('ID paket tidak valid', 400);
            }

            $paket = \Illuminate\Support\Facades\DB::table('N_SOP_Master_Paket as p')
                ->where('p.Id_SOP_Paket', $idPaket)
                ->first(['p.Nama_Paket', 'p.Prioritas', 'p.Estimasi_Waktu_Baca_Menit', 'p.Mode_Distribusi']);
            if (! $paket) {
                return ResponseHelper::error('Paket tidak ditemukan', 404);
            }

            $item = \Illuminate\Support\Facades\DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $idPaket)->where('Flag_Aktif', 'Y')
                ->selectRaw("COUNT(*) as total,
                    SUM(CASE WHEN Flag_Wajib = 'Y' THEN 1 ELSE 0 END) as wajib,
                    SUM(CASE WHEN Tipe_Item = 'KUIS' THEN 1 ELSE 0 END) as kuis")
                ->first();

            // Target: dari kodes[] eksplisit ATAU saringan unit
            $kodes = $request->query('kodes');
            $kodes = is_array($kodes) ? $kodes : (is_string($kodes) && $kodes !== '' ? explode(',', $kodes) : null);
            $ringkas = $this->target->hitungRingkas($this->filterTarget($request), $kodes, $idPaket);

            // Sanity tenggat
            $peringatan = [];
            $deadline = $request->query('tanggal_deadline');
            $tenggatValid = true;
            if ($request->query('tipe_tenggat') === 'DEADLINE') {
                if (! $deadline) {
                    $tenggatValid = false;
                    $peringatan[] = 'Tanggal tenggat belum diisi.';
                } elseif (\Illuminate\Support\Carbon::parse($deadline)->lt(\Illuminate\Support\Carbon::today())) {
                    $tenggatValid = false;
                    $peringatan[] = 'Tenggat berada di masa lalu.';
                }
            }
            if ($ringkas['baru'] === 0 && $ringkas['total'] > 0) {
                $peringatan[] = 'Seluruh target sudah ter-assign — tidak ada penugasan baru yang akan dibuat.';
            }

            $ambang = \App\Services\LMS\SOP\Support\SopSetting::int('penjadwalan_async_ambang', 30);

            return ResponseHelper::success([
                'identitas_kuis' => $this->service->cekIdentitasKuis($idPaket, Auth::user()),
                'paket' => [
                    'nama' => $paket->Nama_Paket,
                    'prioritas' => $paket->Prioritas,
                    'distribusi' => $paket->Mode_Distribusi ?? 'PUBLIK',
                    'estimasi_menit' => $paket->Estimasi_Waktu_Baca_Menit,
                    'ada_draft_perubahan' => $this->service->paketMemilikiDraft($idPaket),
                    'total_item' => (int) ($item->total ?? 0),
                    'total_wajib' => (int) ($item->wajib ?? 0),
                    'ada_kuis' => (int) ($item->kuis ?? 0) > 0,
                ],
                'target' => $ringkas,
                'tenggat' => ['valid' => $tenggatValid, 'peringatan' => $peringatan],
                'mode_eksekusi' => ['async' => $ringkas['baru'] > $ambang, 'ambang' => $ambang],
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan preflight gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * POST /api/v1/sop/admin/penjadwalan/assign
     *
     * Back-compat penuh: payload lama (kode_karyawan[]) tetap diterima dengan
     * respons identik. Tambahan: `target {mode: unit, filter}` untuk mode unit
     * organisasi — daftar kode di-resolve SEKALI di sini lalu di-snapshot.
     *
     * ≤ ambang (SopSetting penjadwalan_async_ambang, default 30) → sinkron.
     * Lebih → header dibuat sinkron (batch langsung tampil), sisanya lewat
     * job ProsesBulkAssignSop + Activity Center. Balas 202 {task_id, ...}.
     */
    public function assign(Request $request)
    {
        $data = $request->validate([
            'id_paket' => 'required|string',
            'nama_batch' => 'nullable|string|max:150',
            'keterangan' => 'nullable|string|max:500',
            // Pagar payload absurd, bukan plafon fungsional — lihat tambahKaryawan().
            'kode_karyawan' => 'nullable|array|max:5000',
            'kode_karyawan.*' => 'string|max:20',
            'target' => 'nullable|array', // {mode:'unit', filter:{dept[],divisi[],...}}
            'tipe_tenggat' => 'nullable|in:DEADLINE,SAMPAI_DICABUT',
            'tanggal_deadline' => 'nullable|date',
            'flag_reminder' => 'nullable|boolean',
            'irama_reminder_hari' => 'nullable|integer|min:1',
        ]);

        $idPaket = $this->decode($data['id_paket']);
        if (! $idPaket) {
            return ResponseHelper::error('ID paket tidak valid', 400);
        }

        // Gerbang identitas HC Learn — hanya untuk paket yang memuat kuis.
        if ($gagal = $this->tolakBilaIdentitasKuisTakSah($idPaket)) {
            return $gagal;
        }

        // Tenggat lampau ditolak di sini juga (preflight hanya menampilkan).
        if (($data['tipe_tenggat'] ?? null) === 'DEADLINE' && ! empty($data['tanggal_deadline'])
            && \Illuminate\Support\Carbon::parse($data['tanggal_deadline'])->lt(\Illuminate\Support\Carbon::today())) {
            return ResponseHelper::error('Tenggat tidak boleh di masa lalu.', 422);
        }

        try {
            // Resolusi target: kode eksplisit ATAU saringan unit (di-snapshot sekali
            // di sini — retry job tak me-resolve ulang terhadap org yang berubah).
            $kodes = $data['kode_karyawan'] ?? [];
            if (empty($kodes) && ($data['target']['mode'] ?? null) === 'unit') {
                $req = new Request($data['target']['filter'] ?? []);
                $kodes = $this->target->resolveTargets($this->filterTarget($req, 'input'));
            }
            $kodes = array_values(array_unique(array_filter(array_map('trim', $kodes))));
            if (empty($kodes)) {
                return ResponseHelper::error('Tidak ada karyawan target.', 422);
            }

            $opts = [
                'sumber' => 'MANUAL',
                'nama_batch' => $data['nama_batch'] ?? null,
                'keterangan' => $data['keterangan'] ?? null,
                'tipe_tenggat' => $data['tipe_tenggat'] ?? 'SAMPAI_DICABUT',
                'tanggal_deadline' => $data['tanggal_deadline'] ?? null,
                'flag_reminder' => $data['flag_reminder'] ?? false,
                'irama_reminder_hari' => $data['irama_reminder_hari'] ?? null,
            ];
            $userId = Auth::user()->Id_Users;
            $ambang = \App\Services\LMS\SOP\Support\SopSetting::int('penjadwalan_async_ambang', 30);

            // ── Jalur sinkron (perilaku lama, respons identik) ──
            if (count($kodes) <= $ambang) {
                $res = $this->service->assign($idPaket, $kodes, $opts, $userId);
                $msg = "Distribusi berhasil. {$res['assigned']} karyawan di-assign"
                    .($res['skipped'] > 0 ? ", {$res['skipped']} dilewati (sudah ter-assign)." : '.');

                return ResponseHelper::success($res, $msg, 201);
            }

            // ── Jalur async ──
            $penjadwalanId = \Illuminate\Support\Facades\DB::transaction(
                fn () => $this->service->buatHeader($idPaket, $opts, $userId),
            );
            $namaPaket = \Illuminate\Support\Facades\DB::table('N_SOP_Master_Paket')
                ->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket');

            $task = app(\App\Services\LMS\SOP\SopBulkAssignTaskService::class);
            $taskId = $task->mulai([
                'id_penjadwalan' => $penjadwalanId,
                'id_paket' => $idPaket,
                'nama_paket' => $namaPaket,
                'kodes' => $kodes,
                'opts' => $opts,
                'user_id' => $userId,
            ], count($kodes), optional(Auth::user()->karyawan)->Kode_Karyawan);

            \App\Jobs\LMS\SOP\Automation\ProsesBulkAssignSop::dispatch($taskId);

            return ResponseHelper::success(
                [
                    'task_id' => $taskId,
                    'penjadwalan_id' => \Vinkla\Hashids\Facades\Hashids::connection('custom')->encode($penjadwalanId),
                    'total' => count($kodes),
                    'async' => true,
                ],
                'Penjadwalan '.count($kodes).' karyawan diproses di latar belakang. Pantau di Pusat Aktivitas.',
                202,
            );
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan assign gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/admin/penjadwalan/cabut — cabut akses (offboarding/pindah/bulk). */
    public function cabut(Request $request)
    {
        $data = $request->validate([
            'kode_karyawan' => 'nullable|string|max:20',
            // Pagar payload absurd, bukan plafon fungsional — lihat tambahKaryawan().
            'kode_karyawan_list' => 'nullable|array|max:5000',
            'kode_karyawan_list.*' => 'string|max:20',
            'id_paket' => 'nullable|string', // kosong = cabut dari SEMUA SOP
            'sebab' => ['required', Rule::in(SopSebabCabut::kunciManual())],
            /*
             * Sebab "LAINNYA" tanpa catatan sama tak menjelaskannya dengan tidak
             * memilih sebab sama sekali — bahkan lebih buruk, karena tampak sudah
             * dijawab. Aturannya dipasang di server, bukan hanya di formulir.
             */
            'catatan' => 'nullable|string|max:500|required_if:sebab,LAINNYA',
        ]);

        $idPaket = null;
        if (! empty($data['id_paket'])) {
            $idPaket = $this->decode($data['id_paket']);
            if (! $idPaket) {
                return ResponseHelper::error('ID paket tidak valid', 400);
            }
        }

        $userId = Auth::user()->Id_Users;

        try {
            if (! empty($data['kode_karyawan_list'])) {
                $res = $this->service->cabutAksesBanyak(
                    $data['kode_karyawan_list'],
                    $idPaket,
                    $userId,
                    $data['sebab'],
                    $data['catatan'] ?? null,
                );
                if ($res['dicabut'] === 0) {
                    return ResponseHelper::error('Tidak ada penugasan aktif untuk dicabut.', 404);
                }
                $scope = $idPaket ? 'pada dokumen ini' : 'dari semua dokumen';
                $jml = count($data['kode_karyawan_list']);

                return ResponseHelper::success($res, "Akses {$jml} karyawan dicabut {$scope} ({$res['dicabut']} penugasan).");
            }

            if (empty($data['kode_karyawan'])) {
                return ResponseHelper::error('Karyawan belum dipilih.', 422);
            }

            $res = $this->service->cabutAkses(
                $data['kode_karyawan'],
                $idPaket,
                $userId,
                $data['sebab'],
                $data['catatan'] ?? null,
            );
            if ($res['dicabut'] === 0) {
                return ResponseHelper::error('Tidak ada penugasan aktif untuk dicabut.', 404);
            }
            $scope = $idPaket ? 'pada dokumen ini' : 'dari semua dokumen';

            return ResponseHelper::success($res, "Akses {$data['kode_karyawan']} dicabut {$scope} ({$res['dicabut']} penugasan).");
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan cabut gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /api/v1/sop/admin/penjadwalan/batch/{id}/ingatkan — Kirim pengingat manual ke batch */
    public function ingatkanBatch(Request $request, string $id)
    {
        try {
            $idPenjadwalan = $this->decode($id);
            if (! $idPenjadwalan) {
                return ResponseHelper::error('ID penjadwalan tidak valid', 400);
            }

            $userId = Auth::user()->Id_Users;
            $pesan = $request->input('pesan');

            $res = $this->service->ingatkanBatch($idPenjadwalan, $pesan, $userId);

            if (! $res['sukses']) {
                return ResponseHelper::error($res['pesan'] ?? 'Gagal mengirim pengingat', 400);
            }

            return ResponseHelper::success($res, "Pengingat berhasil dikirim kepada {$res['sent']} karyawan.");
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan ingatkanBatch gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /api/v1/sop/admin/penjadwalan/batch/{id}/export — Download CSV Roster Karyawan */
    public function exportBatch(Request $request, string $id)
    {
        try {
            $idPenjadwalan = $this->decode($id);
            if (! $idPenjadwalan) {
                return ResponseHelper::error('ID penjadwalan tidak valid', 400);
            }

            $batch = $this->listSvc->batch($idPenjadwalan);
            if (! $batch) {
                return ResponseHelper::error('Batch penjadwalan tidak ditemukan', 404);
            }

            $f = $this->filterTarget($request);
            $f['status'] = $request->query('status', 'SEMUA');

            $res = $this->listSvc->batchKaryawan($idPenjadwalan, $f, 1, 10000);
            $rows = $res['rows'];

            $namaFile = 'Roster_SOP_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $batch->Nama_Batch ?: $batch->Nama_Paket).'_'.date('Ymd_His').'.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$namaFile}\"",
            ];

            $callback = function () use ($rows, $batch) {
                $out = fopen('php://output', 'w');
                // UTF-8 BOM for Excel compatibility
                fwrite($out, "\xEF\xBB\xBF");

                fputcsv($out, ['ROSTER DISTRIBUSI DOKUMEN SOP']);
                fputcsv($out, ['Batch / Label', $batch->Nama_Batch ?: $batch->Nama_Paket]);
                fputcsv($out, ['Nama Dokumen/Paket', $batch->Nama_Paket]);
                fputcsv($out, ['Tipe Tenggat', $batch->Tipe_Tenggat]);
                fputcsv($out, ['Tanggal Deadline', $batch->Tanggal_Deadline ?: '—']);
                fputcsv($out, ['Tanggal Ekspor', date('d/m/Y H:i:s')]);
                fputcsv($out, []);

                fputcsv($out, [
                    'No',
                    'Kode Karyawan',
                    'Nama Karyawan',
                    'Departemen',
                    'Divisi',
                    'Jabatan',
                    'Level',
                    'Lokasi',
                    'Status Kepatuhan',
                    'Progres (%)',
                    'Item Selesai',
                    'Total Item',
                    'Tanggal Penugasan',
                    'Terakhir Dibuka',
                    'Tanggal Selesai',
                    'Skor Kuis',
                    'Status Kuis',
                ]);

                $no = 1;
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $no++,
                        $r->Kode_Karyawan,
                        $r->Nama,
                        $r->Departemen ?? '—',
                        $r->Divisi ?? '—',
                        $r->Jabatan ?? '—',
                        $r->Level ?? '—',
                        $r->Lokasi ?? '—',
                        $r->Flag_Cabut_Akses === 'Y' ? 'DICABUT' : ($r->Status_Paket ?? 'BELUM'),
                        $r->Persen.'%',
                        $r->Item_Selesai,
                        $r->Item_Total,
                        $r->Tanggal_Assign_Format ?? '—',
                        $r->Terakhir_Buka_Format ?? '—',
                        $r->Tanggal_Selesai_Format ?? '—',
                        $r->Skor_Quiz !== null ? $r->Skor_Quiz : '—',
                        $r->Status_Lulus ?? '—',
                    ]);
                }

                fclose($out);
            };

            return response()->stream($callback, 200, $headers);
        } catch (\Exception $e) {
            Log::error('SOP penjadwalan exportBatch gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }
}
