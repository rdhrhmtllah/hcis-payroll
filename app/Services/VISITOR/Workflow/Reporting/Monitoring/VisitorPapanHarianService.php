<?php

namespace App\Services\VISITOR\Workflow\Reporting\Monitoring;

use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Illuminate\Support\Facades\DB;

/**
 * Papan harian Visitor: keadaan tamu dan permit pada hari berjalan.
 *
 * Satu-satunya tempat pengelompokan ini dihitung. Angka ringkas diturunkan dari
 * daftar yang sama — menghitungnya lewat query terpisah membuat layar dan daftar
 * berselisih diam-diam begitu salah satu aturannya berubah.
 */
class VisitorPapanHarianService
{
    /**
     * Ambang "segera kedaluwarsa", dalam hari.
     *
     * Satu jendela saja. Sebelumnya papan memakai 7 hari sementara blok permit
     * memakai 30, sehingga permit yang sama tampil dua kali di layar yang sama.
     * Dipilih 30 karena perpanjangan permit butuh waktu; ubah di sini kalau
     * ritme operasionalnya berbeda.
     */
    private const HARI_SEGERA = 30;

    public function __construct(
        protected VisitorPermitQueryService $permitQuery,
        protected VisitorDashboardOperasionalService $operasional,
        protected VisitorKehadiranQueryService $kehadiran,
    ) {}

    /**
     * @param  string|null  $domain  'tamu' | 'permit' | null (kedua ranah)
     * @param  string|null  $tipeTamu  REGULAR | B2B | null (kedua tipe)
     * @return array<string,mixed>
     */
    public function rakit(?string $domain = null, ?string $tipeTamu = null, ?array $kategoriBolehLihat = null): array
    {
        // Disaring di SERVER, bukan hanya saat render: menyaring di layar berarti
        // barisnya tetap dikueri, dikirim lewat jaringan, lalu dibuang — persis
        // yang membuat muatannya panjang tanpa guna.
        $perluTamu = $domain !== 'permit';
        $perluPermit = $domain !== 'tamu';

        $tamu = $perluTamu ? $this->tamu($tipeTamu, $kategoriBolehLihat) : collect();

        // Tautan detail dirakit di server memakai token yang sama dengan
        // notifikasi: Id berurutan tidak pernah muncul di alamat, dan halaman
        // tujuannya sudah tahu cara membacanya.
        $tamu->each(function ($baris) {
            // Langsung ke meja tamu. URL gabungan lama masih hidup sebagai
            // pengalihan, tetapi menautkan ke sana menambah satu lompatan pada
            // SETIAP baris papan.
            $baris->Tautan_Detail = TipeTamu::urlVerifikator($baris->Tipe_Tamu ?? null).'?detail='
                .VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, (int) $baris->Id_Transaksi_Tamu);
        });
        $permit = $perluPermit ? $this->permit() : collect();

        $permit->each(function ($baris) {
            $baris->Tautan_Detail = '/verifikator-visitor/permit?detail='
                .VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_PERMIT, (int) $baris->Id_Transaksi_Permit);

            // Izin turunan kunjungan menyimpan asal-usulnya. Tanpa tautan balik,
            // admin yang melihat izin bermasalah harus menyalin nomornya lalu
            // mencari kunjungannya sendiri di halaman lain.
            // Permit coklat hanya milik tamu B2B.
            $baris->Tautan_Tamu = $baris->Id_Transaksi_Tamu
                ? TipeTamu::urlVerifikator(TipeTamu::B2B).'?detail='
                    .VisitorDetailToken::buat(
                        VisitorNotificationService::DOMAIN_TAMU,
                        (int) $baris->Id_Transaksi_Tamu,
                    )
                : null;
            unset($baris->Id_Transaksi_Tamu);
        });

        // Sama dengan VisitorKehadiranQueryService::diDalamSql: kunjungan yang sudah
        // berakhir di gerbang tanpa jam keluar adalah KELUAR_TAK_TERCATAT, bukan di dalam.
        $sedangDiDalam = $tamu->filter(fn ($r) => $r->Waktu_Aktual_Masuk !== null
            && $r->Waktu_Aktual_Keluar === null
            && $r->Status_Efektif !== 'EXPIRED');

        $kelompok = [
            // Terjadwal hari ini, sudah disetujui, dan belum tercatat masuk.
            // Yang sudah di dalam sengaja pindah kelompok, bukan tampil dua kali.
            // Yang belum disetujui juga dikeluarkan: ia tidak dapat di-check-in,
            // jadi menaruhnya di sini memberi admin tombol yang dijamin gagal.
            // "Hari ini" = rentang estimasinya beririsan dengan hari ini:
            // kunjungan MITRA berhari-hari tetap harus datang di hari kedua.
            'harus_datang' => $tamu
                ->filter(fn ($r) => (int) $r->Hadir_Hari_Ini === 1
                    && $r->Waktu_Aktual_Masuk === null
                    && ! $this->menungguApproval($r))
                ->values(),

            // Dikeluarkan dari "harus datang" TAPI tetap ditampilkan: kunjungan
            // hari ini yang tertahan approval justru yang perlu dikejar, dan
            // menyaringnya tanpa menampilkannya menciptakan titik buta.
            'menunggu_approval_hari_ini' => $tamu
                ->filter(fn ($r) => (int) $r->Hadir_Hari_Ini === 1
                    && $this->menungguApproval($r))
                ->values(),

            'sedang_di_dalam' => $sedangDiDalam->values(),

            // Masih di dalam padahal masuknya bukan hari ini. TIDAK disaring ke
            // hari ini: orang yang belum keluar sejak kemarin justru baris
            // terpenting di papan, dan penyaringan tanggal menyembunyikannya.
            'menginap' => $sedangDiDalam
                ->filter(fn ($r) => $this->tanggalDari($r->Waktu_Aktual_Masuk) !== $this->hariIni())
                ->values(),

            'permit_kedaluwarsa_hari_ini' => $permit
                ->filter(fn ($r) => $r->Tanggal_Kadaluarsa_Ymd === $this->hariIni())
                ->values(),

            'permit_segera_kedaluwarsa' => $permit
                ->filter(fn ($r) => $r->Tanggal_Kadaluarsa_Ymd !== null
                    && $r->Tanggal_Kadaluarsa_Ymd > $this->hariIni()
                    && $r->Tanggal_Kadaluarsa_Ymd <= $this->batasSegera())
                ->values(),
        ];

        $kelompok = array_filter(
            $kelompok,
            static fn (string $kunci) => str_starts_with($kunci, 'permit_') ? $perluPermit : $perluTamu,
            ARRAY_FILTER_USE_KEY,
        );

        $hasil = array_map(static fn ($daftar) => $daftar->all(), $kelompok);
        $hasil['ringkasan'] = array_map(static fn ($daftar) => $daftar->count(), $kelompok);
        $hasil['tanggal'] = $this->hariIni();

        // Angka "menunggu review" TIDAK punya kelompok daftar sendiri: daftarnya
        // sudah dimiliki tabel antrean keputusan di dashboard. Di sini ia hanya
        // angka pengingat, dan sumbernya kueri antrean yang sama supaya kedua
        // layar tidak pernah menampilkan angka yang berbeda.
        if ($perluPermit) {
            $hasil['ringkasan']['permit_menunggu_review'] = $this->menungguReview();
        }

        // Hitungan ORANG, bukan hanya transaksi. Untuk lobi, "12 orang di dalam"
        // beda arti dengan "5 kunjungan" — dan justru angka inilah yang dulu
        // hanya ada di Kartu Keadaan.
        foreach (['harus_datang', 'sedang_di_dalam', 'menginap', 'menunggu_approval_hari_ini'] as $kunci) {
            if (! isset($kelompok[$kunci])) {
                continue;
            }
            $hasil['ringkasan'][$kunci.'_orang'] = (int) $kelompok[$kunci]
                ->sum(fn ($r) => max((int) ($r->Jumlah_Tamu ?? 1), 1));
        }

        $hasil['needs_approval'] = match (true) {
            $domain === 'permit' => (bool) config('services.visitor.permit.needs_approval', true),
            $tipeTamu === TipeTamu::B2B => (bool) config('services.visitor.tamu_b2b.needs_approval', true),
            default => (bool) config('services.visitor.tamu.needs_approval', false),
        };

        return $hasil;
    }

    /**
     * Permit yang masih menunggu keputusan.
     *
     * Memakai queryAntrianApproval() yang sama dengan tabel antrean, bukan
     * menghitung ulang dari Flag_Approval IS NULL: definisi "masih menunggu"
     * melibatkan baris approval yang kedaluwarsa, dan menuliskannya dua kali
     * berarti dua layar bisa berselisih diam-diam.
     */
    private function menungguReview(): int
    {
        return DB::query()
            ->fromSub($this->operasional->queryAntrianApproval('permit'), 'a')
            ->where('a.ada_pending', 1)
            ->count();
    }

    private function menungguApproval(object $baris): bool
    {
        return $baris->Status_Kunjungan === StatusKunjunganTamu::PENDING_APPROVAL;
    }

    private function hariIni(): string
    {
        return now()->toDateString();
    }

    private function batasSegera(): string
    {
        return now()->addDays(self::HARI_SEGERA)->toDateString();
    }

    private function tanggalDari(?string $waktu): ?string
    {
        return $waktu === null ? null : substr($waktu, 0, 10);
    }

    /** @param  list<string>|null  $kategoriBolehLihat  matriks kategori pemakai; null = tanpa penyaring */
    private function tamu(?string $tipeTamu = null, ?array $kategoriBolehLihat = null): \Illuminate\Support\Collection
    {
        [$irisan, $ikatan] = $this->kehadiran->beririsanHariSql($this->hariIni());

        $query = DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'e.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
            ->where('h.Flag_Aktif', 'Y')
            ->whereNotIn('h.Status_Kunjungan', [StatusKunjunganTamu::REJECTED, StatusKunjunganTamu::WITHDRAWN, StatusKunjunganTamu::REVOKED])
            // Batasi jendela supaya papan tidak menyeret seluruh riwayat, tapi
            // tetap memuat yang belum keluar dari hari-hari sebelumnya.
            //
            // "Yang belum keluar" = masih di dalam (diDalamSql), bukan "pernah
            // masuk": syarat lama menyeret SETIAP kunjungan yang pernah check-in
            // sepanjang riwayat, dan papannya kian lambat tiap hari.
            ->where(function ($q) {
                $q->whereRaw('h.Tanggal_Kunjungan >= DATEADD(day, -30, CAST(GETDATE() AS date))')
                    ->orWhereRaw($this->kehadiran->diDalamSql('e', 'd'));
            })
            ->select(
                'h.Id_Transaksi_Tamu',
                'h.No_Transaksi',
                'h.Status_Kunjungan',
                'h.Kode_Karyawan_PIC',
                'k.Nama as Nama_PIC',
                DB::raw('CONVERT(varchar(10), h.Tanggal_Kunjungan, 23) as Tanggal_Kunjungan_Ymd'),
                'd.Nama_Lengkap_Tamu',
                'd.Instansi_Atau_Asal',
                'd.Nomor_Handphone',
                'd.Jumlah_Tamu',
                'd.Keterangan_Tujuan_Detail',
                'd.Waktu_Estimasi_Kunjungan',
                DB::raw("ISNULL(d.Tipe_Tamu, 'REGULAR') as Tipe_Tamu"),
                'd.Tamu_Kategori',
                DB::raw('CONVERT(varchar(16), d.Waktu_Mulai_Kunjungan, 120) as Estimasi_Masuk'),
                DB::raw('CONVERT(varchar(16), d.Waktu_Selesai_Kunjungan, 120) as Estimasi_Keluar'),
                DB::raw("CASE WHEN {$irisan} THEN 1 ELSE 0 END as Hadir_Hari_Ini"),
                // Sumber kebenaran kehadiran adalah baris efektif: aplikasi
                // palang di gerbang yang menulisnya, dan syncTamuEfektif sengaja
                // tidak menyentuh kedua kolom ini. Kolom detail hanya cadangan
                // untuk catatan manual selama palang belum menulis apa pun —
                // konvensi COALESCE yang sama dipakai tiga controller lain.
                DB::raw('CONVERT(varchar(19), COALESCE(e.Waktu_Aktual_Masuk, d.Waktu_Aktual_Masuk), 120) as Waktu_Aktual_Masuk'),
                DB::raw('CONVERT(varchar(19), COALESCE(e.Waktu_Aktual_Keluar, d.Waktu_Aktual_Keluar), 120) as Waktu_Aktual_Keluar'),
                'e.Status_Efektif',
                DB::raw($this->permitQuery->platNomorSql('d').' as No_Plat_Kendaraan'),
            )
            ->addBinding($ikatan, 'select')
            ->orderBy('h.Tanggal_Kunjungan')
            ->orderBy('h.Id_Transaksi_Tamu');

        if ($tipeTamu !== null) {
            TipeTamu::saring($query, $tipeTamu);
        }

        if ($kategoriBolehLihat !== null) {
            MatriksTamuService::saring($query, $kategoriBolehLihat);
        }

        return $query->get();
    }

    private function permit(): \Illuminate\Support\Collection
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_Pemohon')
            ->where('h.Flag_Aktif', 'Y')
            ->where('h.Flag_Approval', 'Y')
            // Izin yang sudah dicabut tidak punya akses lagi; ia bukan
            // "kedaluwarsa hari ini" maupun "segera kedaluwarsa" (audit 27 Sep, M8).
            ->whereRaw('NOT '.$this->permitQuery->dicabutSql('h'))
            // Papan hanya memakai izin yang kedaluwarsa hari ini s.d. batas
            // "segera"; tanpa batas ini seluruh riwayat izin ditarik lalu
            // dibuang di PHP (audit ulang 28 Sep).
            ->whereBetween('d.Tanggal_Kadaluarsa', [$this->hariIni(), $this->batasSegera()])
            ->select(
                'h.Id_Transaksi_Permit',
                'h.No_Transaksi',
                'h.Kode_Karyawan_Pemohon',
                'k.Nama as Nama_Pemohon',
                'd.Snapshot_Kategori_Permit',
                'h.No_Transaksi_Tamu',
                // Tiga asal izin yang sekarang hidup berdampingan. Tanpa
                // pembeda ini, izin kendaraan karyawan dan izin gerbang seorang
                // tamu tampil identik di papan yang sama.
                DB::raw("CASE WHEN h.No_Transaksi_Tamu IS NOT NULL THEN 'TAMU' "
                    ."WHEN UPPER(ISNULL(h.Sumber_Pengajuan, 'HCIS')) = 'API' THEN 'MITRA' "
                    ."ELSE 'KARYAWAN' END as Jenis_Permit"),
                // PEMEGANG izin, bukan pemohonnya. Izin tamu diajukan atas nama
                // PIC; menampilkan nama PIC di papan gerbang berarti satpam
                // mencari orang yang memang tidak akan datang.
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(d.Nama_Tamu)), ''), k.Nama, '-') as Nama_Pemegang"),
                DB::raw("NULLIF(LTRIM(RTRIM(ISNULL(d.Instansi_Tamu, ''))), '') as Instansi_Pemegang"),
                DB::raw('CONVERT(varchar(10), d.Tanggal_Mulai_Berlaku, 23) as Tanggal_Mulai_Berlaku_Ymd'),
                DB::raw('CONVERT(varchar(10), d.Tanggal_Kadaluarsa, 23) as Tanggal_Kadaluarsa_Ymd'),
                // Jendela berjam. Sejak verifikator menetapkannya sendiri, dua
                // izin yang sama-sama "kedaluwarsa hari ini" bisa berarti pukul
                // delapan pagi dan tengah malam — dan yang pertama sebenarnya
                // sudah mati saat papannya dibaca.
                DB::raw('CONVERT(varchar(19), d.Waktu_Mulai_Berlaku, 120) as Waktu_Mulai_Berlaku'),
                DB::raw('CONVERT(varchar(19), d.Waktu_Berakhir, 120) as Waktu_Berakhir'),
                'd.Snapshot_Nama_Durasi',
                DB::raw('(SELECT TOP 1 t.Id_Transaksi_Tamu FROM N_EMI_VISITOR_Transaksi_Tamu t '
                    .'WHERE t.No_Transaksi = h.No_Transaksi_Tamu) as Id_Transaksi_Tamu'),
                DB::raw($this->permitQuery->platNomorSql('d').' as No_Plat_Kendaraan'),
            )
            ->orderBy('d.Tanggal_Kadaluarsa')
            ->orderBy('h.Id_Transaksi_Permit')
            ->get();
    }
}
