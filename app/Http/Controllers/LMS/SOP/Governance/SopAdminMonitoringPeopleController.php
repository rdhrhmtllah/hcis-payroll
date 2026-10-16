<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopComplianceExportService;
use App\Services\LMS\SOP\SopMonitoringPeopleService;
use App\Services\LMS\SOP\SopNotificationService;
use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\Support\SopKaryawanToken;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Monitoring SOP — sudut pandang KARYAWAN (padanan daftar karyawan di monitoring LMS)
 * + export v2 + aksi pengingat.
 *
 * Controller sengaja tipis: seluruh query hidup di service. LmsMonitoringController
 * yang 2.646 baris adalah contoh yang dihindari, bukan ditiru.
 *
 * Seluruh route di sini berada dalam grup `check.jabatan:menuSopMonitoringPage`.
 */
class SopAdminMonitoringPeopleController extends Controller
{
    public function __construct(
        private SopMonitoringPeopleService $people,
        private SopProgressReadService $read,
        private SopComplianceExportService $exportSvc,
    ) {}

    /* ================= HALAMAN ================= */

    /** GET /sop/admin/monitoring/karyawan/{token} — deep dive per orang. */
    public function karyawanPage(string $token)
    {
        $kode = $this->kodeDariToken($token);

        return inertia('LMS/SOP/Governance/Admin/SopMonitoringKaryawan', [
            'kode' => $kode,
            'token' => $token, // dipakai halaman untuk merakit tautan turunannya
        ]);
    }

    /** GET /sop/admin/monitoring/karyawan/{token}/paket/{id} — tampilan admin atas paket. */
    public function karyawanPaketPage(string $token, string $id)
    {
        $kode = $this->kodeDariToken($token);
        $karyawan = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('k.Kode_Karyawan', $kode)
            ->first([
                'k.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan', 'k.Cabang_Untuk_Gaji as Lokasi',
                'div.nama_sub_divisi as Departemen', 'div.nama_divisi as Divisi',
                'lvl.nama_jabatan as Jabatan', 'lvl.nama_level as Level',
            ]);

        return inertia('LMS/SOP/Governance/Admin/SopMonitoringKaryawanPaket', [
            'kode' => $kode,
            'token' => $token,
            'idPaket' => $id,
            'karyawanInitial' => $karyawan,
        ]);
    }

    /**
     * 404 — bukan 400/422 — supaya token rusak dan kode polos yang ditebak
     * tidak bisa dibedakan dari luar.
     */
    private function kodeDariToken(string $token): string
    {
        return SopKaryawanToken::decode($token) ?? abort(404);
    }

    /* ================= API ================= */

    /** GET /filter-options */
    public function filterOptions()
    {
        try {
            // Kelompok kolom ikut di sini, bukan endpoint sendiri: daftarnya
            // konstanta dan layar export sudah pasti memuat opsi saringan.
            // Ditempel di luar cache supaya perubahan konstanta langsung terlihat.
            return ResponseHelper::success(
                $this->people->filterOptions() + ['grup_kolom' => \App\Services\LMS\SOP\SopExportRinciService::GRUP],
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan filterOptions gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /karyawan — daftar per karyawan + blok KPI.
     * Amplop V2 (beda dengan /coverage yang memakai V1) — perhatikan saat konsumsi di FE.
     */
    public function karyawanList(Request $request)
    {
        try {
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 25)));

            [$rows, $total, $summary] = $this->people->employees($this->filters($request), $page, $limit);
            $rows = SopKaryawanToken::sematkan($rows);

            $res = ResponseHelper::successWithPaginationV2($rows, $page, $limit, $total, 'Data Ditemukan');
            $data = $res->getData(true);
            $data['summary'] = $summary; // KPI global, bukan penjumlahan halaman ini

            return response()->json($data, 200);
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanList gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /karyawan/{kode} — data ringkas & penugasan paket untuk drawer/modal */
    public function karyawanDetail(Request $request, string $kode)
    {
        try {
            $kodeKaryawan = SopKaryawanToken::decode($kode) ?? $kode;
            $ringkas = $this->people->employeeRingkas($kodeKaryawan);
            if (! $ringkas) {
                return ResponseHelper::error('Karyawan tidak ditemukan', 404);
            }
            $paket = $this->read->tugasSaya($kodeKaryawan, 'all');

            return ResponseHelper::success([
                'ringkas' => $ringkas['karyawan'] ?? $ringkas,
                'paket' => $paket,
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanDetail gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /karyawan/{kode}/ringkas */
    public function karyawanRingkas(string $kode)
    {
        try {
            $kodeKaryawan = SopKaryawanToken::decode($kode) ?? $kode;
            $d = $this->people->employeeRingkas($kodeKaryawan);

            return $d
                ? ResponseHelper::success($d, 'Data Ditemukan')
                : ResponseHelper::error('Karyawan tidak ditemukan', 404);
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanRingkas gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /karyawan/{kode}/paket — kartu paket milik karyawan (reuse tugasSaya). */
    public function karyawanPaket(Request $request, string $kode)
    {
        try {
            $kodeKaryawan = SopKaryawanToken::decode($kode) ?? $kode;
            $tab = $request->query('tab', 'all');

            return ResponseHelper::success($this->read->tugasSaya($kodeKaryawan, $tab), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanPaket gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /karyawan/{kode}/rekam-baca — rekam baca per ITEM.
     *
     * Lapisan yang tak dijangkau /paket (tingkat paket) maupun /riwayat
     * (tingkat ledger): durasi aktif, waktu buka/konfirmasi, halaman terakhir,
     * versi yang dihadapi, skor kuis, dan jejak atestasi per item.
     */
    public function karyawanRekamBaca(string $kode)
    {
        try {
            return ResponseHelper::success($this->people->rekamBaca($kode), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanRekamBaca gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /karyawan/{kode}/riwayat — timeline kepatuhan dari ledger. */
    public function karyawanRiwayat(string $kode)
    {
        try {
            return ResponseHelper::success($this->read->riwayat($kode), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanRiwayat gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** GET /karyawan/{kode}/atestasi — bukti atestasi (IP/perangkat: khusus admin). */
    public function karyawanAtestasi(Request $request, string $kode)
    {
        try {
            $idPaket = null;
            if ($request->query('paket')) {
                $d = Hashids::connection('custom')->decode($request->query('paket'));
                $idPaket = $d[0] ?? null;
            }

            return ResponseHelper::success($this->people->atestasi($kode, $idPaket), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanAtestasi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /karyawan/{kode}/absensi — bukti presensi (foto, koordinat, akurasi).
     * Foto dikirim sebagai URL bertanda tangan; path GCS mentah tak pernah keluar.
     */
    public function karyawanAbsensi(Request $request, string $kode)
    {
        try {
            $idPaket = null;
            if ($request->query('paket')) {
                $d = Hashids::connection('custom')->decode($request->query('paket'));
                $idPaket = $d[0] ?? null;
            }

            $svc = app(\App\Services\LMS\SOP\SopAbsensiService::class);
            $rows = $svc->bukti($kode, $idPaket)->map(function ($r) use ($svc) {
                $r->Foto_Url = $svc->fotoUrl($r->File_Path ?? null);
                unset($r->File_Path);

                return $r;
            });

            return ResponseHelper::success($rows, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanAbsensi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /karyawan/{kode}/paket/{id} — detail bundle milik karyawan, dilihat admin.
     * bypassGate menyala di sini SAJA: admin perlu bisa memeriksa paket TERBATAS
     * atau yang sudah diarsipkan namun masih tertugas.
     */
    public function karyawanPaketDetail(string $kode, string $id)
    {
        try {
            $d = Hashids::connection('custom')->decode($id);
            if (empty($d)) {
                return ResponseHelper::error('ID tidak valid', 400);
            }

            $idPaketInt = (int) $d[0];
            $data = $this->read->detailBundle($kode, $idPaketInt, true);

            if ($data) {
                $karyawan = DB::table('Karyawan as k')
                    ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                    ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                    ->where('k.Kode_Karyawan', $kode)
                    ->first([
                        'k.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan', 'k.Cabang_Untuk_Gaji as Lokasi',
                        'div.nama_sub_divisi as Departemen', 'div.nama_divisi as Divisi',
                        'lvl.nama_jabatan as Jabatan', 'lvl.nama_level as Level',
                    ]);
                $data['karyawan'] = $karyawan;

                $absSvc = app(\App\Services\LMS\SOP\SopAbsensiService::class);
                $absRow = $absSvc->bukti($kode, $idPaketInt)->first();
                if ($absRow) {
                    $absRow->Foto_Url = $absSvc->fotoUrl($absRow->File_Path ?? null);
                    unset($absRow->File_Path);
                    $data['absensi'] = $absRow;
                } else {
                    $data['absensi'] = null;
                }
            }

            return $data
                ? ResponseHelper::success($data, 'Data Ditemukan')
                : ResponseHelper::error('Dokumen tidak ditemukan', 404);
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan karyawanPaketDetail gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= EXPORT V2 ================= */

    /**
     * GET /export/estimasi — jumlah baris yang akan terekspor dengan saringan saat ini.
     * Satu COUNT; tak ada id yang dimaterialisasi (bandingkan dengan LMS yang
     * mengenkripsi hingga 25.000 id hanya untuk menampilkan angka ini).
     */
    public function exportEstimasi(Request $request)
    {
        try {
            $jenis = $request->query('jenis', 'karyawan');
            $f = $this->filters($request);

            $total = match ($jenis) {
                'karyawan' => $this->people->selectionCount($f),
                'rinci' => app(\App\Services\LMS\SOP\SopExportRinciService::class)->count($f),
                'dokumen' => app(\App\Services\LMS\SOP\SopDocumentControlService::class)->register($f, 1, 1)[1],
                'risiko' => app(\App\Services\LMS\SOP\SopMonitoringAnalyticsService::class)->papanRisiko($f, 200)->count(),
                'coverage' => app(\App\Services\LMS\SOP\SopMonitoringService::class)->coveragePaket($f['q'] ?? null, 1, 1)[1],
                default => 0,
            };

            return ResponseHelper::success(['jenis' => $jenis, 'total' => $total], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan exportEstimasi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /export/karyawan/{kode}/paket — paket orang ini DI DALAM saringan.
     *
     * Bedanya dengan /karyawan/{kode}/paket: yang itu menjawab "apa saja tugas
     * orang ini" tanpa mengenal saringan. Studio export butuh "bagian mana yang
     * akan ikut terekspor", dan angka ringkas di kepala kartunya memang dihitung
     * dengan saringan — kalau daftarnya tidak, keduanya bicara soal himpunan
     * berbeda dan salah satunya pasti terbaca salah.
     */
    public function exportPaketKaryawan(Request $request, string $kode)
    {
        try {
            return ResponseHelper::success(
                $this->people->paketTersaring($kode, $this->filters($request)),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan exportPaketKaryawan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * POST /export/request — antre export.
     * Yang dititipkan ke job adalah SARINGAN, bukan daftar id; job memanggil service
     * yang sama dengan yang dipakai layar sehingga hasilnya dijamin cocok.
     */
    public function exportRequest(Request $request)
    {
        $v = $request->validate([
            'jenis' => 'required|in:karyawan,rinci,coverage,dokumen,atestasi,risiko,kartu',
            'format' => 'nullable|in:xlsx,csv,pdf',
            'nama' => 'nullable|string|max:120',
            'kode_karyawan' => 'nullable|string|max:20',
            'filters' => 'nullable|array',
            // Kelompok kolom yang diminta ikut. Kosong = seluruhnya.
            'kolom' => 'nullable|array',
            'kolom.*' => 'string|max:20',
        ]);

        try {
            $f = $v['filters'] ?? $this->filters($request);

            $total = match ($v['jenis']) {
                'karyawan' => $this->people->selectionCount($f),
                'rinci' => app(\App\Services\LMS\SOP\SopExportRinciService::class)->count($f),
                'dokumen' => app(\App\Services\LMS\SOP\SopDocumentControlService::class)->register($f, 1, 1)[1],
                'risiko' => app(\App\Services\LMS\SOP\SopMonitoringAnalyticsService::class)->papanRisiko($f, 200)->count(),
                'coverage' => app(\App\Services\LMS\SOP\SopMonitoringService::class)->coveragePaket($f['q'] ?? null, 1, 1)[1],
                default => 1,
            };

            $exportId = $this->exportSvc->makeExportId();
            $this->exportSvc->startJob(
                $exportId,
                $v['format'] ?? 'xlsx',
                [
                    'jenis' => $v['jenis'],
                    'nama' => $v['nama'] ?? null,
                    'filters' => $f,
                    'kode_karyawan' => $v['kode_karyawan'] ?? null,
                    'kolom' => $v['kolom'] ?? [],
                ],
                max(1, $total),
                optional(Auth::user()->karyawan)->Kode_Karyawan,
            );
            $this->exportSvc->dispatchJob($exportId);

            return ResponseHelper::success(
                ['task_id' => $exportId, 'total_records' => $total],
                'Export diproses. Pantau di panel Aktivitas.',
                202,
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan exportRequest gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= AKSI ================= */

    /**
     * POST /ingatkan — kirim pengingat ke karyawan terpilih.
     *
     * Memakai SopNotificationService::notifReminder() apa adanya; service itu
     * idempoten lewat notification_key per (tipe:paket:karyawan), jadi menembak
     * dua kali tidak menggandakan notifikasi.
     */
    public function ingatkan(Request $request)
    {
        $v = $request->validate([
            'kode_karyawan' => 'nullable|array|max:500',
            'kode_karyawan.*' => 'required|string|max:20',
            'filter' => 'nullable|array',  // mode saringan: {status, dept[], divisi[], ...}
            'id_paket' => 'nullable', // string atau array of hashids; kosong = seluruh dokumen tertunggak orang tsb
        ]);

        try {
            /*
             * Dua mode masukan:
             *  - kode_karyawan[] : pilihan eksplisit dari tabel (perilaku lama).
             *  - filter{}        : saringan yang sama dengan daftar karyawan, di-resolve
             *                      di server. Inilah yang membuat kartu antrean di
             *                      Ringkasan bisa langsung bertindak ("Ingatkan semua
             *                      yang terlambat") tanpa admin memilih lintas halaman.
             */
            $kodes = $v['kode_karyawan'] ?? [];
            $belumTerkirim = 0;
            if (empty($kodes) && ! empty($v['filter'])) {
                $saringan = $this->normalisasiFilter($v['filter']);
                $kodes = $this->people->selectionKodes($saringan);
                /*
                 * selectionKodes() berplafon 500 sebagai pagar parameter SQL Server.
                 * Plafon itu tak boleh senyap: kartu antrean menampilkan jumlah dari
                 * selectionCount() yang TANPA plafon, jadi admin mengira semua orang
                 * pada angka itu diingatkan. Di headcount ribuan, sisanya hilang tanpa
                 * satu pun tanda.
                 */
                $belumTerkirim = max(0, $this->people->selectionCount($saringan) - count($kodes));
            }
            if (empty($kodes)) {
                return ResponseHelper::error('Tidak ada karyawan target.', 422);
            }
            $v['kode_karyawan'] = $kodes;

            $idPaketList = [];
            if (! empty($v['id_paket'])) {
                $rawList = is_array($v['id_paket']) ? $v['id_paket'] : [$v['id_paket']];
                foreach ($rawList as $raw) {
                    if (is_string($raw) || is_numeric($raw)) {
                        $d = Hashids::connection('custom')->decode((string) $raw);
                        if (! empty($d[0])) {
                            $idPaketList[] = (int) $d[0];
                        }
                    }
                }
            }

            $q = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
                ->whereIn('d.Kode_Karyawan', $v['kode_karyawan'])
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
                ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI);

            if (! empty($idPaketList)) {
                $q->whereIn('p.Id_SOP_Paket', $idPaketList);
            }

            $target = $q->get(['d.Kode_Karyawan', 'p.Id_SOP_Paket', 'p.Nama_Paket', 'pj.Tanggal_Deadline', 'pj.Tipe_Tenggat']);

            if ($target->isEmpty()) {
                return ResponseHelper::error('Tidak ada penugasan tertunggak untuk dikirimi pengingat.', 422);
            }

            // Bentuk item mengikuti kontrak SopNotificationService::notifReminder()
            // (kode / nama / tenggat / id_paket / overdue) — jangan diubah sepihak.
            $items = $target->map(function ($t) {
                $adaTenggat = $t->Tipe_Tenggat === 'DEADLINE' && $t->Tanggal_Deadline;

                return [
                    'kode' => $t->Kode_Karyawan,
                    'id_paket' => (int) $t->Id_SOP_Paket,
                    'nama' => $t->Nama_Paket,
                    'tenggat' => $adaTenggat
                        ? \Illuminate\Support\Carbon::parse($t->Tanggal_Deadline)->format('d M Y')
                        : 'tanpa tenggat',
                    'overdue' => $adaTenggat
                        && \Illuminate\Support\Carbon::parse($t->Tanggal_Deadline)->lt(now()->startOfDay()),
                ];
            })->all();

            app(SopNotificationService::class)->notifReminder($items);

            /*
             * Ditulis lewat SopLog, bukan INSERT langsung. SopLog mempromosikan
             * oleh_id_users dan id_penjadwalan dari meta menjadi kolom berindeks —
             * INSERT manual meninggalkan keduanya NULL, sehingga entri INGATKAN
             * tak pernah muncul di timeline batch (SopPenjadwalanListService::riwayat
             * menyaring lewat Id_SOP_Penjadwalan) dan kolom "oleh" selalu "Sistem".
             */
            SopLog::catat(
                'INGATKAN',
                'Pengingat Dokumen dikirim ke '.$target->pluck('Kode_Karyawan')->unique()->count().' karyawan.'
                    .($belumTerkirim > 0 ? " {$belumTerkirim} karyawan melebihi batas sekali proses dan BELUM diingatkan." : ''),
                $idPaket, null, null, null,
                [
                    'jumlah_notifikasi' => count($items),
                    'kode_karyawan' => $target->pluck('Kode_Karyawan')->unique()->values(),
                    'belum_terkirim' => $belumTerkirim,
                    'oleh_id_users' => Auth::user()->Id_Users ?? null,
                ],
            );

            $pesan = 'Pengingat terkirim.';
            if ($belumTerkirim > 0) {
                $pesan .= " {$belumTerkirim} karyawan melebihi batas sekali proses dan BELUM diingatkan —"
                    .' persempit saringan lalu ulangi untuk sisanya.';
            }

            return ResponseHelper::success(
                [
                    'terkirim' => count($items),
                    'karyawan' => $target->pluck('Kode_Karyawan')->unique()->count(),
                    'belum_terkirim' => $belumTerkirim,
                ],
                $pesan,
            );
        } catch (\Exception $e) {
            Log::error('SOP monitoring karyawan ingatkan gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /* ================= INTERNAL ================= */

    /** Normalisasi query string jadi array saringan yang dipahami service. */
    /**
     * Bentuk saringan yang sama dengan filters(), tapi sumbernya array body
     * (mode `filter` pada aksi massal) — bukan query string.
     */
    private function normalisasiFilter(array $f): array
    {
        $arr = function (string $k) use ($f) {
            $v = $f[$k] ?? [];
            $list = is_array($v) ? $v : array_filter(explode(',', (string) $v));

            return array_values(array_filter(array_map('trim', $list), fn ($x) => $x !== ''));
        };

        return [
            'q' => $f['q'] ?? null,
            'status' => $f['status'] ?? 'all',
            'dept' => $arr('dept'),
            'divisi' => $arr('divisi'),
            'level' => $arr('level'),
            'jabatan' => $arr('jabatan'),
            'lokasi' => $arr('lokasi'),
            'prioritas' => $f['prioritas'] ?? null,
            'paket' => $f['paket'] ?? null,
            'date_mode' => $f['date_mode'] ?? 'assign',
            'date_start' => $f['date_start'] ?? null,
            'date_end' => $f['date_end'] ?? null,
            'bucket' => $f['bucket'] ?? null,
        ];
    }

    private function filters(Request $request): array
    {
        $arr = fn (string $k) => array_values(array_filter(
            is_array($request->query($k)) ? $request->query($k) : array_filter(explode(',', (string) $request->query($k, ''))),
            fn ($x) => trim((string) $x) !== '',
        ));

        return [
            'q' => $request->query('q'),
            'status' => $request->query('status', 'all'),
            'dept' => $arr('dept'),
            'divisi' => $arr('divisi'),
            'level' => $arr('level'),
            'jabatan' => $arr('jabatan'),
            'lokasi' => $arr('lokasi'),
            'prioritas' => $request->query('prioritas'),
            'paket' => $arr('paket'),
            'date_mode' => $request->query('date_mode', 'assign'),
            'date_start' => $request->query('date_start'),
            'date_end' => $request->query('date_end'),
            'bucket' => $request->query('bucket'),
            // Pilihan eksplisit dari studio export — penyempit di atas saringan,
            // bukan penggantinya. Kosong berarti "semua yang lolos saringan".
            'kodes' => $arr('kodes'),
        ];
    }
}
