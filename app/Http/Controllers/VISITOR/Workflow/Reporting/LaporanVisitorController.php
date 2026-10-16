<?php

namespace App\Http\Controllers\VISITOR\Workflow\Reporting;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\VISITOR\Guest\Visit\FotoKendaraanTamuService;
use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\Inspection\KontakPemohonInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Errors\VisitorApiFailure;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\SaringanMonitoringPermit;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanHarianService;
use App\Services\VISITOR\Workflow\Reporting\Monitoring\VisitorPapanTamuService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LaporanVisitorController extends Controller
{
    private const T_TAMU_H = 'N_EMI_VISITOR_Transaksi_Tamu';

    private const T_TAMU_D = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    private const T_PERMIT_H = 'N_EMI_VISITOR_Transaksi_Permit';

    private const T_PERMIT_D = 'N_EMI_VISITOR_Transaksi_Permit_Detail';

    private const M_KATEGORI = 'N_EMI_VISITOR_Master_Kategori_Permit';

    private const M_VM = 'N_EMI_VISITOR_Master_Vehicle_Model';

    private const M_VT = 'N_EMI_VISITOR_Master_Vehicle_Type';

    public function __construct(
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorPapanHarianService $papanHarian,
        protected VisitorKehadiranQueryService $kehadiran,
        protected FotoKendaraanTamuService $fotoKendaraan,
        protected MatriksTamuService $matriks,
    ) {}

    /**
     * Kategori tamu yang boleh dilihat pemakai, dari MATRIKS - satu-satunya
     * sumber kebenaran.
     *
     * Kunci halaman per orang sengaja TIDAK lagi menjadi jalan pintas.
     * Dua mekanisme untuk satu pertanyaan yang sama membuat keduanya saling
     * tidak tahu: kunci dihibahkan tangan lalu terlupakan - itulah sebabnya
     * visitorJadwalTamuPage berakhir nol pemegang tanpa ada yang menyadarinya -
     * sedangkan matriks terikat role sehingga merawat dirinya sendiri.
     *
     * Admin tetap melihat semuanya karena DML-2026-136 memberi ADMIN_TAMU
     * ketiga kategori; tidak ada yang kehilangan akses saat jalan pintasnya
     * ditutup.
     *
     * @return list<string>
     */
    protected function kategoriBolehLihat(Request $request): array
    {
        $idUser = $request->user()?->Id_Users;

        return $idUser === null ? [] : $this->matriks->kategoriBolehLihat((int) $idUser);
    }

    /**
     * Batasi daftar tamu pada kategori yang boleh dilihat pemakai.
     *
     * Halaman Monitoring Tamu kini juga dibuka lewat hibah role - bukan hanya
     * oleh admin berkunci - sesudah Jadwal Kedatangan Tamu dilebur ke sini.
     * Tanpa penyaring ini, setiap anggota grup Visitor langsung melihat
     * SELURUH tamu lintas kategori, termasuk B2B yang selama ini sengaja
     * dipisah ke halamannya sendiri.
     */
    protected function saringMatriks($query, Request $request): void
    {
        MatriksTamuService::saring($query, $this->kategoriBolehLihat($request));
    }

    /**
     * Daftar putih kolom urut.
     *
     * Nilai dari permintaan TIDAK PERNAH menyentuh ORDER BY: menyambungnya
     * langsung adalah lubang injeksi, dan kolom karangan harus jatuh ke urutan
     * baku, bukan melahirkan galat SQL di layar pengguna.
     */
    /**
     * Halaman yang wewenang revoke-nya menempel.
     *
     * Disebut sekali di sini dan dipakai untuk menjawab "boleh_revoke";
     * middleware route revoke memakai nama halaman yang sama. Menuliskannya
     * dua kali berarti tombolnya bisa tampil untuk orang yang endpoint-nya
     * justru menolak.
     */
    private const HALAMAN_VERIFIKATOR = 'visitorVerifikatorPermitPage';

    private const URUT_TAMU = [
        'diajukan' => 'h.Created_At',
        'tanggal' => 'h.Tanggal_Kunjungan',
        'nama' => 'd.Nama_Lengkap_Tamu',
        'instansi' => 'd.Instansi_Atau_Asal',
        'no_transaksi' => 'h.No_Transaksi',
        'status' => 'COALESCE(e.Status_Efektif, h.Status_Kunjungan)',
        'masuk' => 'COALESCE(e.Waktu_Aktual_Masuk, d.Waktu_Aktual_Masuk)',
        'keluar' => 'COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar)',
    ];

    private const URUT_PERMIT = [
        'diajukan' => 'h.Created_At',
        'kadaluarsa' => 'd.Tanggal_Kadaluarsa',
        'mulai' => 'd.Tanggal_Mulai_Berlaku',
        'pemohon' => 'k.Nama',
        'no_transaksi' => 'h.No_Transaksi',
        'kategori' => 'mk.Nama_Kategori',
        'divisi' => 'vd.nama_divisi',
        'sub_divisi' => 'vd.nama_sub_divisi',
        'jabatan' => 'g.nama_jabatan',
        'level' => 'g.nama_level',
    ];

    /**
     * @param  array<string,string>  $daftarPutih
     * @return array{0:string,1:string}
     */
    /** Batas baris per halaman daftar monitoring. */
    private const BATAS_PER_HALAMAN = 500;

    /**
     * Halaman & ukuran halaman yang AMAN (30 Sep 2026).
     *
     * Tanpa batas, limit=0 menjatuhkan ceil($total / $limit) menjadi galat 500,
     * limit raksasa menarik seluruh tabel dalam satu permintaan, dan page ≤ 0
     * menghasilkan OFFSET negatif yang ditolak SQL Server.
     *
     * @return array{0: int, 1: int}
     */
    private function halaman(Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(max((int) $request->input('limit', 20), 1), self::BATAS_PER_HALAMAN);

        return [$page, $limit];
    }

    private function urutan(Request $request, array $daftarPutih, string $baku): array
    {
        $kunci = (string) $request->input('urut', $baku);
        $kolom = $daftarPutih[$kunci] ?? $daftarPutih[$baku];
        $arah = strtolower((string) $request->input('arah', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Sebagian kolom urut adalah EKSPRESI (COALESCE), bukan nama kolom.
        // orderBy() akan mengutipnya sebagai satu identifier — `[COALESCE(...)]`
        // — dan SQL Server menolaknya, jadi ekspresi harus lewat DB::raw().
        return [str_contains($kolom, '(') ? DB::raw($kolom) : $kolom, $arah];
    }

    /**
     * URL lama halaman gabungan.
     *
     * Monitoring dipecah menjadi dua halaman (DML-2026-105). Rute ini
     * dipertahankan sebagai pengalihan, bukan dihapus: tautan lama, bookmark,
     * dan menu yang belum tersegarkan tetap mendarat di tempat yang benar
     * alih-alih 404 — pola yang sama dengan /approvalvisitor yang sudah lebih
     * dulu pensiun ke Verifikator.
     */
    public function index(Request $request)
    {
        return redirect('/monitoring-tamu');
    }

    public function indexTamu(Request $request)
    {
        $tipe = TipeTamu::dariPermintaan($request);
        $b2b = $tipe === TipeTamu::B2B;

        return Inertia::render('VISITOR/Workflow/Reporting/Monitoring/monitoringTamu', [
            'user' => $request->user(),
            'tipeTamu' => $tipe,
            // Endpoint halaman ini. B2B memakai salinan rute ber-kunci sendiri
            // (DDL-2026-121); pemegang kunci Regular tidak bisa memanggilnya.
            // Hak kategori pemakai, ditampilkan supaya jelas MENGAPA ia melihat
            // jenis tamu yang ia lihat - dan mengapa daftarnya kosong bila
            // departemennya belum diberi hak apa pun. Panel ini pindahan dari
            // Jadwal Kedatangan Tamu, yang dilebur ke halaman ini.
            'kategoriBolehLihat' => array_map(fn (string $k) => [
                'kode' => $k,
                'label' => MatriksTamuService::LABEL_KATEGORI[$k] ?? $k,
            ], $this->kategoriBolehLihat($request)),
            'departemen' => array_map(fn (array $d) => [
                'nama' => $d['nama'],
                'lihat' => array_map(fn ($k) => MatriksTamuService::LABEL_KATEGORI[$k] ?? $k, $d['lihat']),
                'ajukan' => array_map(fn ($k) => MatriksTamuService::LABEL_KATEGORI[$k] ?? $k, $d['ajukan']),
            ], $this->matriks->departemen($request->user()?->Id_Users !== null ? (int) $request->user()->Id_Users : null)),
            'endpoints' => [
                'laporan' => $b2b ? '/laporanvisitor-b2b-tamu' : '/laporanvisitor-tamu',
                'detail' => $b2b ? '/laporanvisitor-b2b-detail-tamu' : '/laporanvisitor-detail-tamu',
                'papan_harian' => $b2b ? '/laporanvisitor-b2b-papan-harian' : '/laporanvisitor-papan-harian',
                'papan_tamu' => $b2b ? '/laporanvisitor-b2b-papan-tamu' : '/laporanvisitor-papan-tamu',
                'antrian' => $b2b ? '/laporanvisitor-b2b-antrian' : '/laporanvisitor-antrian',
                'verifikator' => TipeTamu::urlVerifikator($tipe),
            ],
            'needsApproval' => (bool) config('services.visitor.'.TipeTamu::modulApproval($tipe).'.needs_approval', $b2b),
            // Boleh tidaknya membuka izin gerbang dari baris papan — DITANYAKAN
            // KE SERVER, bukan disimpulkan layar.
            //
            // Sejak halaman dipecah (DML-2026-105), admin tamu murni tidak punya
            // kunci halaman permit sama sekali. Tombol yang tetap ditampilkan
            // untuknya berujung 403 pada halaman berikutnya — galat yang muncul
            // SETELAH ia menekan, untuk sesuatu yang memang bukan haknya.
            // Keadaan izinnya sendiri tetap ditampilkan; yang disembunyikan
            // hanya lompatannya.
            'bolehBukaPermit' => $request->user() !== null
                && collect(['visitorVerifikatorPermitPage', 'visitorMonitoringPermitPage'])
                    ->contains(fn ($p) => app(KeputusanAkses::class)->boleh($request->user(), $p)),
        ]);
    }

    public function indexPermit(Request $request)
    {
        return Inertia::render('VISITOR/Workflow/Reporting/Monitoring/monitoringPermit', [
            'user' => $request->user(),
            'needsApproval' => (bool) config('services.visitor.permit.needs_approval', true),
        ]);
    }

    /**
     * Ranah papan/antrean yang boleh dibaca pemakai ini.
     *
     * Rute bersama dijaga kunci tamu ATAU permit, sehingga `domain` dari
     * permintaan tidak boleh dipercaya begitu saja: pemegang kunci permit saja
     * dulu bisa menarik data tamu (nama, HP, plat) dengan domain=tamu atau
     * tanpa domain (audit ulang 28 Sep). Rute B2B hanya melayani tamu B2B.
     *
     * @throws AuthorizationException bila ranah yang diminta bukan haknya
     */
    private function domainPapanDiizinkan(Request $request, ?string $diminta = null): ?string
    {
        $diminta ??= in_array($request->input('domain'), ['tamu', 'permit'], true)
            ? (string) $request->input('domain')
            : null;

        // Rute bertipe (B2B) dijaga kunci tamu B2B: ranahnya selalu tamu.
        if (TipeTamu::dariPermintaanAtauSemua($request) !== null) {
            return 'tamu';
        }

        $user = $request->user();
        $punya = fn (string $kunci) => $user instanceof User
            && app(KeputusanAkses::class)->boleh($user, $kunci);
        $bolehTamu = $punya('visitorMonitoringTamuPage');
        $bolehPermit = $punya('visitorMonitoringPermitPage');

        $hasil = match ($diminta) {
            'tamu' => $bolehTamu ? 'tamu' : false,
            'permit' => $bolehPermit ? 'permit' : false,
            default => $bolehTamu && $bolehPermit ? null : ($bolehTamu ? 'tamu' : ($bolehPermit ? 'permit' : false)),
        };

        if ($hasil === false) {
            throw new AuthorizationException('Anda tidak memiliki akses ke ranah ini.');
        }

        return $hasil;
    }

    /**
     * Papan harian: keadaan tamu dan permit pada hari berjalan.
     *
     * Pengelompokannya dihitung di VisitorPapanHarianService dan diteruskan apa
     * adanya; menghitung ulang di sini membuat layar berselisih dengan papan.
     */
    public function getPapanHarian(Request $request)
    {
        try {
            $domain = $this->domainPapanDiizinkan($request);

            return ResponseHelper::success($this->papanHarian->rakit(
                $domain,
                TipeTamu::untukDomain($request, $domain),
                $domain === 'permit' ? null : $this->kategoriBolehLihat($request),
            ));
        } catch (AuthorizationException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_FORBIDDEN', 403);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_daily_board', 'VISITOR_REPORT_DAILY_BOARD_FAILED', 'Papan harian Visitor gagal dimuat.');
        }
    }

    /**
     * Daftar "Tamu hari ini" — berhalaman, dicari, dan diurutkan DI SQL.
     *
     * Dipisah dari getPapanHarian() yang mengembalikan ringkasan angka: kartu
     * ringkas butuh seluruh himpunan, tabel hanya butuh satu halaman. Menyatukan
     * keduanya memaksa salah satunya menarik data yang tidak dipakai.
     */
    public function getPapanTamu(Request $request, VisitorPapanTamuService $papanTamu)
    {
        try {
            if ($this->domainPapanDiizinkan($request, 'tamu') !== 'tamu') {
                throw new AuthorizationException('Anda tidak memiliki akses ke daftar tamu.');
            }

            return ResponseHelper::success($papanTamu->daftar(array_merge($request->only([
                'page', 'limit', 'cari', 'urut', 'arah',
            ]), [
                'tipe' => TipeTamu::untukDomain($request, 'tamu'),
                'kategori' => $this->kategoriBolehLihat($request),
            ])));
        } catch (AuthorizationException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_FORBIDDEN', 403);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_guest_board', 'VISITOR_REPORT_GUEST_BOARD_FAILED', 'Daftar tamu gagal dimuat.');
        }
    }

    /** Antrean approval berhalaman. */
    public function getAntrianApproval(Request $request, VisitorDashboardOperasionalService $operasional)
    {
        try {
            $domain = $this->domainPapanDiizinkan($request);

            // Antrean tamu kini per tipe: 'tamu' = Regular, 'tamu_b2b' = B2B.
            if ($domain === 'tamu' && TipeTamu::untukDomain($request, $domain) === TipeTamu::B2B) {
                $domain = 'tamu_b2b';
            }

            return ResponseHelper::success($operasional->antrianBerhalaman(
                $request->only(['page', 'limit', 'cari', 'urut', 'arah']),
                $domain,
            ));
        } catch (AuthorizationException $e) {
            return ResponseHelper::errorWithCode($e->getMessage(), 'VISITOR_FORBIDDEN', 403);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_approval_queue', 'VISITOR_REPORT_APPROVAL_QUEUE_FAILED', 'Antrean approval Visitor gagal dimuat.');
        }
    }

    /**
     * Detail satu permit untuk halaman monitoring — BACA SAJA.
     *
     * Isinya diambil dari metode VisitorPermitQueryService yang sama persis
     * dengan yang dipakai meja verifikasi, jadi keduanya tidak bisa berselisih
     * isi. Yang sengaja TIDAK ikut: is_final_approval dan seluruh aksi
     * approve/reject — halaman ini memantau, bukan memutuskan. Satu-satunya
     * tindakan yang tersedia adalah pencabutan, dan wewenangnya tetap milik
     * meja verifikasi (lihat boleh_revoke).
     */
    public function getDetailPermit(Request $request, string $id)
    {
        try {
            $idTransaksi = $this->idTransaksi(VisitorNotificationService::DOMAIN_PERMIT, $id);

            $query = $this->permitQuery->basePermitQuery();
            if ($idTransaksi !== null) {
                $query->where('h.Id_Transaksi_Permit', $idTransaksi);
            } else {
                $query->where(function ($q) use ($id) {
                    $q->where('h.No_Transaksi', $id)
                        ->orWhere('h.No_Transaksi_Tamu', $id);
                });
            }

            $permit = $query->first();

            if (! $permit) {
                return ResponseHelper::error('Data permit tidak ditemukan.', 404);
            }

            return ResponseHelper::success([
                'permit' => $permit,
                'dokumens' => $this->permitQuery->getDokumens($permit->No_Transaksi),
                'checklists' => $this->permitQuery->getPermitChecklists($permit->No_Transaksi),
                'approvals' => $this->permitQuery->getApprovals($permit->No_Transaksi),
                'boleh_revoke' => $this->bolehRevoke($request),
                'kontak_pemohon' => app(KontakPemohonInspeksiService::class)->untukPermit(
                    $permit,
                    app(JadwalInspeksiService::class)->ringkasan($permit->No_Transaksi),
                ),
                'kunjungan_tamu' => $this->kehadiran->ringkasanKunjungan($permit->No_Transaksi_Tamu ?? null),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_permit_detail', 'VISITOR_REPORT_PERMIT_DETAIL_FAILED', 'Detail permit gagal dimuat.');
        }
    }

    /** Detail satu kunjungan tamu untuk halaman monitoring — BACA SAJA. */
    public function getDetailTamu(Request $request, string $id)
    {
        try {
            $idTransaksi = $this->idTransaksi(VisitorNotificationService::DOMAIN_TAMU, $id);

            $query = $this->permitQuery->baseTamuQuery();
            if ($idTransaksi !== null) {
                $query->where('h.Id_Transaksi_Tamu', $idTransaksi);
            } else {
                $query->where('h.No_Transaksi', $id);
            }

            // Matriks berlaku di sini juga, bukan hanya di daftarnya. Daftar
            // yang tersaring tanpa detail yang tersaring hanya menyembunyikan
            // barisnya: nomor transaksinya tetap bisa ditebak atau dibawa dari
            // notifikasi, dan satu permintaan langsung ke endpoint ini
            // memulangkan kunjungan kategori yang tidak diberikan matriks.
            $this->saringMatriks($query, $request);

            $tamu = TipeTamu::saring($query, TipeTamu::dariPermintaan($request))->first();

            if (! $tamu) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $tamu->Foto_Kendaraan_Url = $this->fotoKendaraan->url($tamu->Path_Foto_Kendaraan ?? null);

            return ResponseHelper::success([
                'tamu' => $tamu,
                'approvals' => $this->permitQuery->getApprovals($tamu->No_Transaksi),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_guest_detail', 'VISITOR_REPORT_GUEST_DETAIL_FAILED', 'Detail tamu gagal dimuat.');
        }
    }

    /**
     * Kunjungan asal sebuah permit, untuk halaman PERMIT — BACA SAJA.
     *
     * Dijaga kunci halaman permit dan hanya melayani kunjungan yang memang
     * punya permit. Admin permit tidak memegang kunci halaman tamu, sehingga
     * menautkannya ke endpoint tamu berujung 403 — dan sejak endpoint tamu
     * disaring per tipe, kunjungan B2B (satu-satunya pemilik permit COKLAT)
     * juga tidak lagi dilayani endpoint Regular.
     */
    public function getDetailTamuPermit(Request $request, string $id)
    {
        try {
            $idTransaksi = $this->idTransaksi(VisitorNotificationService::DOMAIN_TAMU, $id);

            // Meja permit menautkan lewat NOMOR kunjungan (No_Transaksi_Tamu);
            // token/angka tetap diterima.
            $tamu = $this->permitQuery->baseTamuQuery()
                ->when(
                    $idTransaksi !== null,
                    fn ($q) => $q->where('h.Id_Transaksi_Tamu', $idTransaksi),
                    fn ($q) => $q->where('h.No_Transaksi', $id),
                )
                ->whereExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('N_EMI_VISITOR_Transaksi_Permit as pt')
                        ->whereColumn('pt.No_Transaksi_Tamu', 'h.No_Transaksi');
                })
                ->first();

            if (! $tamu) {
                return ResponseHelper::error('Data tamu tidak ditemukan.', 404);
            }

            $tamu->Foto_Kendaraan_Url = $this->fotoKendaraan->url($tamu->Path_Foto_Kendaraan ?? null);

            return ResponseHelper::success([
                'tamu' => $tamu,
                'approvals' => $this->permitQuery->getApprovals($tamu->No_Transaksi),
            ]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_permit_guest_detail', 'VISITOR_REPORT_PERMIT_GUEST_DETAIL_FAILED', 'Detail tamu gagal dimuat.');
        }
    }

    /**
     * Id transaksi dari token notifikasi ATAU angka mentah.
     *
     * Tautan notifikasi membawa token tersamar, sedangkan tabel monitoring
     * memegang Id-nya langsung. Menerima keduanya membuat satu endpoint
     * melayani kedua jalan masuk; nilai yang tidak terbaca harus berhenti di
     * sini, karena mengalirkannya ke kolom int berbalas 500, bukan 404.
     */
    /**
     * Apakah pengguna ini boleh mencabut permit yang sudah disetujui.
     *
     * Dijawab server memakai pemeriksaan yang sama dengan middleware route
     * revoke. Gagal TERTUTUP: pengguna yang tidak ada, atau objek pengguna yang
     * bentuknya tidak seperti yang diharapkan, dijawab "tidak boleh" — bukan
     * melempar 500 yang menyeret seluruh halaman monitoring ikut gagal.
     */
    private function bolehRevoke(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User
            && app(KeputusanAkses::class)->boleh($user, self::HALAMAN_VERIFIKATOR);
    }

    private function idTransaksi(string $domain, string $id): ?int
    {
        $dariToken = VisitorDetailToken::bacaId($domain, $id);

        if ($dariToken !== null) {
            return $dariToken;
        }

        return ctype_digit($id) ? (int) $id : null;
    }

    public function getLaporanHarian(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from', Carbon::today()->toDateString());
            $dateTo = $request->input('date_to', Carbon::today()->toDateString());
            $kategori = $request->input('kategori', '');
            [$page, $limit] = $this->halaman($request);

            $tamuQuery = DB::table(self::T_TAMU_H.' as h')
                ->leftJoin(self::T_TAMU_D.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'h.No_Transaksi', '=', 'e.No_Transaksi')
                ->where('h.Flag_Aktif', 'Y')->whereBetween('h.Tanggal_Kunjungan', [$dateFrom, $dateTo])
                ->select(
                    'h.Tanggal_Kunjungan as tanggal', DB::raw('COALESCE(e.Waktu_Aktual_Masuk, d.Waktu_Aktual_Masuk) as waktu_masuk'),
                    'd.Nama_Lengkap_Tamu as nama', 'd.Instansi_Atau_Asal as asal', 'd.Keterangan_Tujuan_Detail as tujuan',
                    DB::raw($this->permitQuery->platNomorSql('d').' as no_plat'),
                    // Status siklus, bukan kolom mentah — sama dengan Monitoring Tamu (audit M10).
                    DB::raw($this->kehadiran->siklusKunjunganSql().' as status'), DB::raw('COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar) as Waktu_Aktual_Keluar'),
                    DB::raw("'TAMU' as kategori"), DB::raw("'Tamu' as tipe_label")
                );

            if ($kategori && $kategori !== 'Tamu') {
                $tamuQuery->whereRaw('1=0');
            }

            $permitQuery = DB::table(self::T_PERMIT_H.' as h')
                ->leftJoin(self::T_PERMIT_D.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                ->leftJoin('Karyawan as k', 'h.Kode_Karyawan_Pemohon', '=', 'k.Kode_Karyawan')
                ->leftJoin(self::M_VM.' as vm', 'd.Id_Master_Model', '=', 'vm.Id_Vehicle_Model')
                // Laporan HISTORIS: permit yang disetujui dan mulai berlaku di
                // rentang itu, apa pun keadaannya sekarang. Menyaring status saat
                // ini ('APPROVED') menghapus yang sejak itu kedaluwarsa/dicabut
                // dari laporan masa lalu (audit ulang 28 Sep). Status tetap kolom.
                ->where('h.Flag_Aktif', 'Y')->where('h.Flag_Approval', 'Y')
                ->whereBetween('d.Tanggal_Mulai_Berlaku', [$dateFrom, $dateTo])
                ->select(
                    'd.Tanggal_Mulai_Berlaku as tanggal', DB::raw('NULL as waktu_masuk'),
                    DB::raw("CONCAT(ISNULL(vm.Nama_Model_Kendaraan,''), ' · ', ".$this->permitQuery->platNomorSql('d').') as nama'),
                    DB::raw('k.Nama as asal'), DB::raw("'Area Perusahaan' as tujuan"),
                    DB::raw($this->permitQuery->platNomorSql('d').' as no_plat'),
                    DB::raw($this->permitQuery->permitStatusSql().' as status'), DB::raw('NULL as Waktu_Aktual_Keluar'),
                    DB::raw("'PERMIT' as kategori"),
                    DB::raw("CASE WHEN h.No_Transaksi_Tamu IS NOT NULL THEN 'Tamu' "
                        ."WHEN UPPER(ISNULL(h.Sumber_Pengajuan, 'HCIS')) = 'API' THEN 'Mitra' ELSE 'Karyawan' END as tipe_label")
                );

            if ($kategori && $kategori !== 'Kendaraan Karyawan') {
                $permitQuery->whereRaw('1=0');
            }

            $combined = $tamuQuery->unionAll($permitQuery);
            $total = DB::table(DB::raw("({$combined->toSql()}) as combined"))->mergeBindings($combined)->count();
            $data = DB::table(DB::raw("({$combined->toSql()}) as combined"))->mergeBindings($combined)
                ->orderByDesc('tanggal')->skip(($page - 1) * $limit)->take($limit)->get();

            return ResponseHelper::successWithPaginationV2($data, $page, $limit, $total, 'Berhasil');
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_daily', 'VISITOR_REPORT_DAILY_FAILED', 'Laporan harian Visitor gagal dimuat.');
        }
    }

    public function getLaporanTamu(Request $request)
    {
        try {
            $dateFrom = $request->input('date_from', Carbon::today()->subDays(30)->toDateString());
            $dateTo = $request->input('date_to', Carbon::today()->toDateString());
            $status = $request->input('status', '');
            $keperluan = $request->input('keperluan', '');
            // Tipe DITETAPKAN RUTE (halaman Regular / B2B), bukan diambil dari
            // isian: pemegang kunci Regular tidak boleh menarik baris B2B hanya
            // dengan mengganti ?tipe=.
            $tipe = TipeTamu::dariPermintaan($request);
            $sumber = strtoupper(trim((string) $request->input('sumber', '')));
            [$page, $limit] = $this->halaman($request);

            $query = DB::table(self::T_TAMU_H.' as h')
                ->leftJoin(self::T_TAMU_D.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'h.No_Transaksi', '=', 'e.No_Transaksi')
                ->where('h.Flag_Aktif', 'Y')->whereBetween('h.Tanggal_Kunjungan', [$dateFrom, $dateTo])
                ->select(
                    'h.Id_Transaksi_Tamu as Id', 'h.No_Transaksi', 'h.Tanggal_Kunjungan',
                    DB::raw('COALESCE(e.Waktu_Aktual_Masuk, d.Waktu_Aktual_Masuk) as Waktu_Aktual_Masuk'),
                    DB::raw('COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar) as Waktu_Aktual_Keluar'),
                    // Keadaan SIKLUS, bukan Status_Efektif mentah.
                    //
                    // Status_Efektif menyatakan barisnya hidup di gerbang, bukan
                    // bahwa orangnya ada di dalam — dan layar dulu melabelinya
                    // "Aktif / Di Dalam". Setiap kunjungan yang disetujui karena
                    // itu berlencana hijau "Di Dalam", termasuk yang tamunya tak
                    // pernah datang dan yang sudah pulang.
                    DB::raw($this->kehadiran->siklusKunjunganSql().' as Status_Kunjungan'),
                    // Nilai mentahnya tetap dikirim untuk penelusuran; ia hanya
                    // tidak lagi menjadi yang dibaca manusia.
                    DB::raw('COALESCE(e.Status_Efektif, h.Status_Kunjungan) as Status_Efektif_Mentah'),
                    'd.Nama_Lengkap_Tamu as Nama_Tamu', 'd.Instansi_Atau_Asal as Instansi_Asal',
                    'd.Nomor_Handphone as No_HP', 'd.Deksripsi_Keperluan',
                    'd.Keterangan_Tujuan_Detail as PIC_Tujuan', 'd.Jumlah_Tamu',
                    DB::raw("ISNULL(d.Tipe_Tamu, 'REGULAR') as Tipe_Tamu"),
                    'd.Tamu_Kategori',
                    'd.B2B_Unit',
                    'd.Waktu_Mulai_Kunjungan', 'd.Waktu_Selesai_Kunjungan',
                    DB::raw("CASE WHEN h.Id_Api_Aplikasi IS NULL THEN 'HCIS' ELSE 'MITRA' END as Sumber_Kunjungan"),
                    DB::raw('(SELECT TOP 1 ap.Nama_Aplikasi FROM N_HRIS_Api_Aplikasi ap '
                        .'WHERE ap.Id_Api_Aplikasi = h.Id_Api_Aplikasi) as Nama_Mitra'),
                    // Keadaan izin gerbangnya. Dibaca dari TRANSAKSI permit,
                    // bukan dari proyeksi: proyeksi hanya memuat izin yang sudah
                    // hidup, sehingga izin yang masih menunggu verifikator tidak
                    // bisa dibedakan dari izin yang tidak pernah diajukan.
                    DB::raw($this->permitQuery->statusPermitKunjunganSql('h.No_Transaksi', 'IZIN').' as Status_Permit_Coklat'),
                    'd.Daftar_Anggota_Json',
                );

            // Lingkup kategori. Angka kartu di bawah memakai penyaring yang
            // SAMA - daftar dan angka yang berbeda lingkup membuat kartu
            // berkata "12 tamu" sementara tabelnya berisi 3.
            $this->saringMatriks($query, $request);

            if ($status && in_array($status, StatusKunjunganTamu::TAMPIL, true)) {
                // Penyaring memakai EKSPRESI YANG SAMA dengan kolomnya. Menyaring
                // lewat nilai mentah sementara layar menampilkan siklus membuat
                // penyaring "Di dalam" memulangkan baris berlencana "Sudah
                // keluar" — dan tak satu pun galat menjelaskannya.
                $query->whereRaw($this->kehadiran->siklusKunjunganSql().' = ?', [$status]);
            }
            if ($keperluan) {
                $query->where('d.Deksripsi_Keperluan', 'LIKE', "%{$keperluan}%");
            }
            // REGULAR mencakup baris lama yang kolomnya masih kosong.
            TipeTamu::saring($query, $tipe);
            $kategori = strtoupper(trim((string) $request->input('kategori', '')));
            if (in_array($kategori, [TipeTamu::KATEGORI_VISITOR, TipeTamu::KATEGORI_MITRA], true)) {
                $query->where('d.Tamu_Kategori', $kategori);
            }
            // Unit pengaju B2B (DDL-2026-122).
            $unit = strtoupper(trim((string) $request->input('unit', '')));
            if (in_array($unit, MatriksTamuService::UNIT, true)) {
                $query->where('d.B2B_Unit', $unit);
            }
            if ($sumber !== '') {
                $sumber === 'MITRA'
                    ? $query->whereNotNull('h.Id_Api_Aplikasi')
                    : $query->whereNull('h.Id_Api_Aplikasi');
            }
            // Keadaan izin gerbang (permit COKLAT) — kosakata yang SAMA dengan
            // kolom Permit di tabelnya (statusPermitKunjunganSql 'IZIN').
            $statusPermit = strtoupper(trim((string) $request->input('status_permit', '')));
            if ($statusPermit === 'TANPA_PERMIT') {
                $query->whereRaw($this->permitQuery->statusPermitKunjunganSql('h.No_Transaksi', 'IZIN').' IS NULL');
            } elseif (in_array($statusPermit, ['AKTIF', 'MENUNGGU', 'DITOLAK', 'KEDALUWARSA', 'DICABUT'], true)) {
                $query->whereRaw($this->permitQuery->statusPermitKunjunganSql('h.No_Transaksi', 'IZIN').' = ?', [$statusPermit]);
            }

            $cari = trim((string) $request->input('cari', ''));
            if ($cari !== '') {
                $query->where(function ($q) use ($cari) {
                    $q->where('d.Nama_Lengkap_Tamu', 'LIKE', "%{$cari}%")
                        ->orWhere('d.Instansi_Atau_Asal', 'LIKE', "%{$cari}%")
                        ->orWhere('h.No_Transaksi', 'LIKE', "%{$cari}%")
                        // Plat, PIC tujuan, dan keperluan (30 Sep 2026): yang
                        // biasa ditanyakan satpam dan PIC saat mencari tamu.
                        ->orWhereRaw($this->permitQuery->platNomorSql('d').' LIKE ?', ["%{$cari}%"])
                        ->orWhere('d.Keterangan_Tujuan_Detail', 'LIKE', "%{$cari}%")
                        ->orWhere('d.Deksripsi_Keperluan', 'LIKE', "%{$cari}%")
                        // Nama anggota rombongan hanya ada di manifesnya, dan
                        // justru nama itu yang sering dicari admin ketika
                        // seseorang menanyakan "tamu siapa yang datang tadi".
                        ->orWhere('d.Daftar_Anggota_Json', 'LIKE', "%{$cari}%");
                });
            }

            $total = $query->count();
            // Kolom Status menampilkan status siklus; urutannya harus mengikuti yang
            // tampil, bukan kolom mentah (audit M10).
            // Bawaan: pengajuan TERBARU di atas, sama dengan monitoring permit.
            [$kolomUrut, $arah] = $this->urutan($request, ['status' => $this->kehadiran->siklusKunjunganSql()] + self::URUT_TAMU, 'diajukan');
            $data = $query
                ->orderBy($kolomUrut, $arah)
                // Kunci kedua yang unik: tanpa itu baris berperingkat sama bisa
                // berpindah antar halaman dan orang hilang dari paginasi.
                ->orderBy('h.Id_Transaksi_Tamu', $arah)
                ->skip(($page - 1) * $limit)->take($limit)->get();

            // Sumber lifecycle adalah header + detail. N_VISITOR_Tamu_Efektif hanya
            // salinan yang ditulis sekali saat approval - check-in/check-out tidak
            // pernah menyentuhnya, sehingga membaca Status_Efektif di sini dulunya
            // membuat sudah_keluar dan expired selalu 0.
            $statsBase = function () use ($dateFrom, $dateTo, $tipe, $request) {
                $q = TipeTamu::saring(DB::table(self::T_TAMU_H.' as h')
                    ->join(self::T_TAMU_D.' as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                    ->where('h.Flag_Aktif', 'Y')
                    ->whereRaw('h.Tanggal_Kunjungan >= ?', [$dateFrom])
                    ->whereRaw('h.Tanggal_Kunjungan < DATEADD(day, 1, CAST(? AS date))', [$dateTo]), $tipe);

                $this->saringMatriks($q, $request);

                return $q;
            };

            // Kehadiran dibaca lewat VisitorKehadiranQueryService: baris efektif
            // (ditulis palang) dengan kolom detail sebagai cadangan. Membaca
            // kolom detail saja membuat tamu yang benar-benar lewat gerbang
            // tidak pernah terhitung.
            $stats = [
                'total_tamu' => $statsBase()->count(),
                'sudah_keluar' => $this->kehadiran->join($statsBase())
                    ->whereRaw($this->kehadiran->adaKeluarSql())
                    ->count(),
                // "Tidak pernah datang" (CANCELLED): definisi status tampil
                // yang sama dengan kolomnya — Estimasi Keluar lewat tanpa masuk.
                'expired' => $this->kehadiran->join($statsBase())
                    ->whereRaw($this->kehadiran->siklusKunjunganSql().' = ?', [StatusKunjunganTamu::CANCELLED])
                    ->count(),
            ];

            return response()->json(['success' => true, 'result' => $data, 'stats' => $stats, 'pagination' => ['total' => $total, 'current_page' => $page, 'per_page' => $limit, 'total_pages' => ceil($total / $limit)]]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_guest', 'VISITOR_REPORT_GUEST_FAILED', 'Laporan tamu gagal dimuat.');
        }
    }

    public function getLaporanPermit(Request $request)
    {
        try {
            [$page, $limit] = $this->halaman($request);

            $query = DB::table(self::T_PERMIT_H.' as h')
                ->leftJoin(self::T_PERMIT_D.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                ->leftJoin('Karyawan as k', 'h.Kode_Karyawan_Pemohon', '=', 'k.Kode_Karyawan')
                ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_Divisi_Sub_Divisi')
                ->leftJoin(self::M_KATEGORI.' as mk', 'd.Id_Master_Kategori_Permit', '=', 'mk.Id_Kategori_Permit')
                ->leftJoin(self::M_VM.' as vm', 'd.Id_Master_Model', '=', 'vm.Id_Vehicle_Model')
                ->leftJoin(self::M_VT.' as vt', 'vm.Id_Master_Tipe', '=', 'vt.Id_Vehicle_Type')
                // Jabatan dan level pemohon. Satu baris per ID_Level_Jabatan,
                // jadi join ini tidak menggandakan baris permit.
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'k.ID_Level_Jabatan', '=', 'g.ID_Level_Jabatan')
                // Status akses efektif. Revoke menulis ke tabel INI, bukan ke
                // tabel transaksi — tanpa join ini permit yang sudah dicabut
                // tetap tampil persis seperti yang masih berlaku, karena
                // permitStatusSql() hanya membaca header + tanggal.
                ->leftJoin('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
                // REVOKED ikut ditampilkan, bukan disingkirkan: kolom Aksi
                // memang menandainya "Dicabut" beserta sebabnya. Menyaringnya
                // ke luar akan membuat permit lenyap dari monitoring persis
                // pada saat orang paling ingin melihatnya.
                ->where('h.Flag_Aktif', 'Y')
                ->whereRaw($this->saringIzinPernahHidupSql())
                ->select(
                    'h.Id_Transaksi_Permit as Id', 'h.No_Transaksi', 'h.Kode_Karyawan_Pemohon',
                    'd.Tanggal_Mulai_Berlaku as Tanggal_Berlaku', 'd.Tanggal_Kadaluarsa',
                    'k.Nama as Nama_Karyawan', 'vd.nama_divisi as Departemen',
                    DB::raw("ISNULL(vd.nama_divisi, '-') as Nama_Divisi"),
                    DB::raw("ISNULL(vd.nama_sub_divisi, '-') as Nama_Sub_Divisi"),
                    DB::raw("ISNULL(g.nama_jabatan, '-') as Nama_Jabatan"),
                    DB::raw("ISNULL(g.nama_level, '-') as Nama_Level"),
                    DB::raw($this->permitQuery->platNomorSql('d').' as No_Plat_Kendaraan'),
                    'vm.Nama_Model_Kendaraan as Nama_Model_Manual', 'd.Tahun_Kendaraan',
                    'mk.Nama_Kategori', 'mk.Kode_Warna_UI as Kode_Warna', 'vt.Nama_Tipe_Kendaraan as Nama_Vehicle_Type',
                    DB::raw("ISNULL(pe.Status_Efektif, 'TANPA_AKSES') as Status_Efektif"),
                    // Sebab pencabutan ikut ditampilkan di sisi admin juga.
                    // Verifikator yang mencabut belum tentu orang yang membuka
                    // monitoring besok pagi; label "Dicabut" tanpa sebabnya
                    // memaksa mereka menebak atau bertanya.
                    'pe.Alasan_Status as Alasan_Pencabutan',
                    'pe.Dibatalkan_Oleh_Nama as Dicabut_Oleh',
                    'pe.Dibatalkan_At as Dicabut_Pada',
                    DB::raw('DATEDIFF(day, GETDATE(), d.Tanggal_Kadaluarsa) as Sisa_Hari'),
                    'h.No_Transaksi_Tamu',
                    DB::raw("CASE WHEN h.No_Transaksi_Tamu IS NOT NULL THEN 'TAMU' "
                        ."WHEN UPPER(ISNULL(h.Sumber_Pengajuan, 'HCIS')) = 'API' THEN 'MITRA' "
                        ."ELSE 'KARYAWAN' END as Jenis_Asal_Permit"),
                    // PEMEGANG izin, bukan pemohonnya. Kolom Nama_Karyawan di
                    // atas memulangkan nama PIC untuk izin tamu — satpam yang
                    // membacanya akan mencari orang yang memang tidak datang.
                    DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(d.Nama_Tamu)), ''), k.Nama, '-') as Nama_Pemegang"),
                    DB::raw("NULLIF(LTRIM(RTRIM(ISNULL(d.Instansi_Tamu, ''))), '') as Instansi_Pemegang"),
                    // Jendela berjam — sejak verifikator menetapkannya sendiri,
                    // "kedaluwarsa hari ini" bisa berarti pukul delapan pagi.
                    'd.Waktu_Mulai_Berlaku',
                    'd.Waktu_Berakhir',
                    'd.Snapshot_Nama_Durasi',
                );

            // Satu definisi dengan pencabutan massal "pilih semua yang sesuai
            // filter" — yang dicabut harus persis yang terlihat di sini.
            app(SaringanMonitoringPermit::class)->terapkan($query, $request->only(SaringanMonitoringPermit::KUNCI));
            $akhirSql = SaringanMonitoringPermit::AKHIR_SQL;

            $total = $query->count();
            // Bawaan: pengajuan TERBARU di atas (permintaan user 30 Sep 2026).
            [$kolomUrut, $arah] = $this->urutan($request, self::URUT_PERMIT, 'diajukan');
            $data = $query
                ->orderBy($kolomUrut, $arah)
                ->orderBy('h.Id_Transaksi_Permit', $arah)
                ->skip(($page - 1) * $limit)->take($limit)->get();

            $statsBase = fn () => DB::table(self::T_PERMIT_H.' as h')
                ->leftJoin(self::T_PERMIT_D.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
                // Sama persis dengan penyaring daftarnya; kalau berbeda, angka
                // kartu dan isi tabel berselisih tanpa ada yang menyalakannya.
                ->where('h.Flag_Aktif', 'Y')
                ->whereRaw($this->saringIzinPernahHidupSql());

            // Daftarnya memang memuat yang dicabut (supaya sebabnya terlihat),
            // tetapi ANGKA "masih berlaku" tidak boleh ikut menghitungnya:
            // aksesnya sudah tidak ada. Tanpa penyaring ini, kartu "Masih
            // berlaku" tetap mencantumkan permit yang barusan dicabut.
            /*
             * SATU perjalanan untuk empat angka.
             *
             * Bentuk sebelumnya menembakkan empat COUNT yang memindai populasi
             * yang sama, berbeda hanya pada batas tanggalnya. Pada basis data
             * jarak jauh, biayanya bukan pemindaian melainkan PERJALANAN:
             * latensi terukur ±65 ms per kueri, jadi tiga kueri tambahan adalah
             * ~200 ms yang dihabiskan menunggu.
             *
             * Ekspresi pencabutan dihitung DI TURUNAN. SQL Server menolak
             * agregat di atas ekspresi yang memuat sub-kueri, dan dicabutSql()
             * membaca proyeksi gerbang lewat EXISTS — membungkusnya langsung
             * dengan COUNT() gagal di basis data, bukan di PHP, sehingga baru
             * ketahuan saat endpointnya dipanggil.
             *
             * Daftarnya memang memuat yang dicabut (supaya sebabnya terlihat),
             * tetapi angka "masih berlaku" tidak boleh ikut menghitungnya:
             * aksesnya sudah tidak ada.
             */
            // Batas yang sama dengan penyaring daftar ($akhirSql, jendela jam
            // lebih dulu): kartu yang membaca tanggal saja berselisih dengan
            // isi tabel untuk izin yang berakhir di tengah hari.
            $angka = DB::query()
                ->fromSub(
                    $statsBase()->selectRaw(
                        "h.No_Transaksi, {$akhirSql} AS akhir, "
                        .'CASE WHEN '.$this->permitQuery->dicabutSql().' THEN 1 ELSE 0 END AS dicabut'
                    ),
                    'x'
                )
                ->selectRaw(
                    'COUNT(DISTINCT CASE WHEN x.dicabut = 0 AND x.akhir >= GETDATE() THEN x.No_Transaksi END) AS permit_aktif,
                     COUNT(DISTINCT CASE WHEN x.dicabut = 0 AND x.akhir >= GETDATE() AND x.akhir <= DATEADD(day, 30, GETDATE()) THEN x.No_Transaksi END) AS hampir_habis,
                     COUNT(DISTINCT CASE WHEN x.dicabut = 0 AND x.akhir <  GETDATE() THEN x.No_Transaksi END) AS kadaluarsa,
                     COUNT(DISTINCT CASE WHEN x.dicabut = 1 THEN x.No_Transaksi END) AS dicabut'
                )
                ->first();

            $stats = [
                'permit_aktif' => (int) ($angka->permit_aktif ?? 0),
                'hampir_habis' => (int) ($angka->hampir_habis ?? 0),
                'kadaluarsa' => (int) ($angka->kadaluarsa ?? 0),
                'dicabut' => (int) ($angka->dicabut ?? 0),
            ];

            // Wewenang mencabut permit yang sudah disetujui tetap milik meja
            // verifikasi; halaman ini hanya MENAMPILKAN tombolnya. Jawabannya
            // dihitung server memakai pemeriksaan yang sama dengan middleware
            // route revoke — kalau diputuskan di layar, tombolnya cuma
            // disembunyikan dan endpoint-nya tetap terbuka bagi yang tahu
            // alamatnya.
            $bolehRevoke = $this->bolehRevoke($request);

            // Pilihan saringan kategori dari master aktif: kategori baru ikut
            // muncul tanpa mengubah layar.
            $opsiKategori = DB::table(self::M_KATEGORI)->where('Flag_Aktif', 'Y')
                ->orderBy('Id_Kategori_Permit')->pluck('Nama_Kategori')->all();

            return response()->json(['success' => true, 'result' => $data, 'stats' => $stats, 'boleh_revoke' => $bolehRevoke, 'opsi_kategori' => $opsiKategori, 'pagination' => ['total' => $total, 'current_page' => $page, 'per_page' => $limit, 'total_pages' => ceil($total / $limit)]]);
        } catch (\Exception $e) {
            return VisitorApiFailure::respond($e, 'report_permit', 'VISITOR_REPORT_PERMIT_FAILED', 'Laporan permit gagal dimuat.');
        }
    }

    /**
     * Izin yang PERNAH hidup: berlaku, dicabut, atau sudah kedaluwarsa setelah
     * disetujui. Daftar dan kartu statistik Monitoring Permit memakai penyaring
     * yang sama. EXPIRED hanya bila Flag_Approval='Y' — pengajuan yang approval-
     * nya kedaluwarsa sebelum diputus tak pernah menjadi izin (audit 27 Sep, M7:
     * tanpa EXPIRED, filter & kartu "Sudah kedaluwarsa" selalu 0).
     */
    private function saringIzinPernahHidupSql(): string
    {
        $status = $this->permitQuery->permitStatusSql();

        return "({$status} IN ('APPROVED','REVOKED') OR ({$status} = 'EXPIRED' AND h.Flag_Approval = 'Y'))";
    }
}
