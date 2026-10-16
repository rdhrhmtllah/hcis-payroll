<?php

namespace App\Console\Commands\VISITOR\Operations\Expiration;

use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalDecisionService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Set Status_Efektif='EXPIRED' pada baris N_VISITOR_Permit_Efektif /
 * N_VISITOR_Tamu_Efektif yang masa berlakunya sudah lewat, dan reset
 * Flag_Sync='T' supaya aplikasi puller menarik ulang status barunya. HANYA
 * menyentuh baris yang masih 'AKTIF' — mencegah race condition menimpa baris
 * yang baru saja di-DIBATALKAN.
 *
 * Perintah ini punya dua ketelitian sekaligus, dan yang menentukan bukan sumber
 * permitnya melainkan ADA-TIDAKNYA jendela jam:
 *
 *  - Permit tanpa jendela (permit kendaraan karyawan, masa berlakunya dalam
 *    bulan) dinilai per HARI lewat Tanggal_Kadaluarsa.
 *  - Permit berjendela (permit coklat tamu dan permit mitra B2B) dinilai per
 *    JAM lewat Waktu_Berakhir. Permit coklat tamu berakhir juga begitu tamunya
 *    tercatat keluar — mana yang lebih dulu.
 *
 * Bersandar pada fakta, bukan pada daftar sumber: daftar semacam itu harus
 * diingat setiap kali sumber baru muncul, dan yang lupa tidak mendapat galat
 * apa pun — permitnya sekadar tidak pernah mati.
 *
 * Karena itu jadwalnya dinaikkan menjadi tiap 5 menit
 * (DML-2026-078-visitor-expire-tiap-5-menit). Konsekuensi yang diterima sadar:
 * permit coklat mati paling lambat 5 menit setelah jam selesai atau setelah
 * tamu keluar, bukan seketika.
 *
 * Awal jendela SENGAJA tidak dijaga di sini. Permit yang baru berlaku besok
 * tetap 'AKTIF' sejak disetujui; aplikasi palang wajib membandingkan
 * Waktu_Mulai_Berlaku / Tanggal_Mulai_Berlaku sebelum meloloskan. Menambah
 * nilai status "belum berlaku" akan memutus kontrak enum Status_Efektif yang
 * dibaca aplikasi di luar repo ini.
 */
class ExpireVisitorEfektif extends Command
{
    protected $signature = 'visitor:expire-efektif';

    protected $description = 'Expire proyeksi Efektif visitor (permit karyawan per hari, permit coklat per jam dan saat tamu keluar)';

    public function handle(): int
    {
        $now = now();
        $today = $now->toDateString();

        // Cabang 1 — permit tanpa jendela jam, ketelitian HARI.
        //
        // Penyaringnya bersandar pada FAKTA yang relevan — tidak punya jendela —
        // bukan pada daftar sumber. Sebelumnya cabang ini menyaring
        // `Sumber_Permit <> 'TAMU'`, dan begitu muncul sumber ketiga (permit
        // mitra 'API') penyaring itu menyeret permit berjam-jam ke ketelitian
        // hari: yang habis pukul 12:00 baru mati lewat tengah malam.
        $permitPerHari = $this->langkah('permit per hari', fn () => DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->whereNull('Waktu_Berakhir')
            ->whereDate('Tanggal_Kadaluarsa', '<', $today)
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Updated_At' => $now,
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]));

        // Cabang 2 — SEMUA permit berjendela jam, apa pun sumbernya.
        //
        // Permit coklat tamu dan permit mitra sama-sama lewat sini. Tidak ada
        // daftar sumber yang harus diingat setiap kali bertambah — sumber
        // keempat kelak ikut tersapu tanpa menyentuh baris ini.
        $permitBerjendela = $this->langkah('permit berjendela', fn () => DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->whereNotNull('Waktu_Berakhir')
            ->where('Waktu_Berakhir', '<', $now)
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Alasan_Status' => 'Jendela berlaku berakhir',
                'Updated_At' => $now,
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]));

        // Cabang 3 — permit COKLAT, tamunya sudah tercatat keluar.
        //
        // Tautannya lewat N_EMI_VISITOR_Transaksi_Permit.No_Transaksi_Tamu.
        // Sebelum permit COKLAT punya transaksi sendiri, baris proyeksinya
        // berkunci nomor KUNJUNGAN sehingga korelasinya langsung; sejak permitnya
        // melewati approval, nomor itu nomor permit — dan korelasi lama diam-diam
        // tidak pernah menemukan satu baris pun.
        //
        // Kehadiran TIDAK PERNAH ditulis HCIS: aplikasi palang menulis
        // Waktu_Aktual_Keluar langsung ke N_VISITOR_Tamu_Efektif, dan kolom di
        // tabel Detail hanya jalur catatan manual. Karena itu dua-duanya dibaca,
        // dan Status_Kunjungan TIDAK dipakai sama sekali — tamu yang lewat palang
        // tetap berstatus TERJADWAL selamanya, sehingga menyaring lewat kolom itu
        // membuat permit tamu yang benar-benar sudah pulang tidak pernah mati.
        //
        // Tabelnya TIDAK di-alias. SQL Server menolak `UPDATE tabel AS alias`
        // dengan "Incorrect syntax near the keyword 'as'", jadi korelasi ke
        // baris luar memakai nama tabel penuh.
        $permitCoklatKeluar = $this->langkah('permit coklat keluar', fn () => DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->where('Sumber_Permit', 'TAMU')
            // HANYA izin yang berlaku satu tanggal.
            //
            // Sejak verifikator boleh menetapkan jendela berapa hari pun, tamu
            // proyek yang pulang di sore hari PERTAMA akan kehilangan izinnya
            // untuk sisa harinya — dan ia baru mengetahuinya besok pagi di
            // gerbang, tanpa satu pun galat yang menjelaskan sebabnya. Pulang
            // pada penghujung hari adalah perilaku normal izin berhari-hari,
            // bukan tanda izinnya selesai.
            //
            // Izin berhari-hari sepenuhnya diatur jendelanya sendiri (cabang 2)
            // dan pembatalan kunjungannya (jalur terpisah).
            //
            // Tanda kurungnya WAJIB: whereRaw tidak membungkus apa pun, dan AND
            // mengikat lebih erat daripada OR. Tanpanya penyaring status ikut
            // terlepas, dan sapuan ini mengakhiri permit yang sama sekali tidak
            // berhubungan.
            ->whereRaw(
                '(Waktu_Berakhir IS NULL
                  OR CAST(Waktu_Mulai_Berlaku AS date)
                     = CAST(DATEADD(second, -1, Waktu_Berakhir) AS date))',
            )
            ->whereRaw(
                'EXISTS (SELECT 1
                           FROM N_EMI_VISITOR_Transaksi_Permit p
                           JOIN N_VISITOR_Tamu_Efektif te ON te.No_Transaksi = p.No_Transaksi_Tamu
                           LEFT JOIN N_EMI_VISITOR_Transaksi_Tamu_Detail td ON td.No_Transaksi = te.No_Transaksi
                          WHERE p.No_Transaksi = N_VISITOR_Permit_Efektif.No_Transaksi
                            AND COALESCE(te.Waktu_Aktual_Keluar, td.Waktu_Aktual_Keluar) IS NOT NULL)',
            )
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Alasan_Status' => 'Tamu tercatat keluar',
                'Updated_At' => $now,
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]));

        // Cabang 4 — proyeksi TAMU. Pindah dari Tanggal_Kunjungan ke akhir
        // jendela: kunjungan yang menyeberang tengah malam kedaluwarsa kepagian
        // kalau tidak. Baris tamu lama belum punya jendela, jadi COALESCE jatuh
        // kembali ke perilaku semula — H+1 dari tanggal kunjungan.
        //
        // Akhir jendela dibaca dari baris efektif itu sendiri lebih dulu: sejak
        // 29 Sep 2026 kolom itu memuat jendela permit COKLAT yang ditetapkan
        // verifikator (VisitorEfektifService::jendelaAksesTamu). Membaca tabel
        // Detail saja mematikan akses tamu yang izinnya diperpanjang admin
        // pada jam estimasi keluar kunjungan aslinya.
        $tamu = $this->langkah('tamu', fn () => DB::table('N_VISITOR_Tamu_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->whereRaw(
                'COALESCE(N_VISITOR_Tamu_Efektif.Waktu_Estimasi_Keluar,
                    (SELECT TOP 1 COALESCE(td.Waktu_Selesai_Kunjungan, DATEADD(day, 1, CAST(N_VISITOR_Tamu_Efektif.Tanggal_Kunjungan AS datetime2(0))))
                       FROM N_EMI_VISITOR_Transaksi_Tamu_Detail td
                      WHERE td.No_Transaksi = N_VISITOR_Tamu_Efektif.No_Transaksi)) < ?',
                [$now],
            )
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Updated_At' => $now,
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]));

        // Cabang resign - permit karyawan yang hari kerja terakhirnya lewat
        // (keputusan user 3 Okt 2026). Ditaruh SEBELUM cabang booking supaya
        // slot inspeksinya ikut dilepas pada sapuan yang sama.
        $permitResign = $this->langkah('permit pemohon resign', fn () => $this->akhiriPermitResign());

        // Cabang 5 - booking yang permitnya sudah tidak hidup.
        //
        // Booking tidak ikut mati bersama permitnya: jendela approval yang
        // lewat tanpa diputus, penolakan, dan pencabutan semuanya meninggalkan
        // baris TERJADWAL yang menggantung. Akibatnya bukan sekadar tampilan -
        // slot itu tetap terhitung penuh, sehingga pemohon lain kehilangan
        // tempat karena pengajuan yang sudah tidak mungkin berlanjut.
        //
        // Ditandai DIBATALKAN dengan sebabnya, bukan KEDALUWARSA: yang terakhir
        // berarti "slotnya lewat dan pemohon memesan ulang", dan memakainya di
        // sini akan mengaburkan dua kejadian yang perlu dibedakan saat menghitung
        // ketidakhadiran.
        $permitQuery = app(VisitorPermitQueryService::class);

        $bookingMenggantung = $this->langkah('booking menggantung', function () use ($permitQuery, $now) {
            $menggantung = fn () => DB::table('N_VISITOR_Inspeksi_Booking')
                ->where('Status', JadwalInspeksiService::TERJADWAL)
                ->whereExists(function ($q) use ($permitQuery) {
                    $q->select(DB::raw(1))
                        ->from('N_EMI_VISITOR_Transaksi_Permit as h')
                        ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
                        ->whereColumn('h.No_Transaksi', 'N_VISITOR_Inspeksi_Booking.No_Transaksi_Permit')
                        ->whereRaw('NOT ('.$permitQuery->masihPantasBerjadwalSql().')');
                });

            $nomor = $menggantung()->distinct()->pluck('No_Transaksi_Permit')->all();

            $jumlah = $menggantung()->update([
                'Status' => JadwalInspeksiService::DIBATALKAN,
                'Alasan' => 'Permit tidak lagi berjalan (ditolak, dicabut, atau jendela approval lewat).',
                'Updated_At' => $now,
                'Updated_By' => 'visitor:expire-efektif',
            ]);

            // Akses palang INSPEKSI-nya ikut diakhiri: booking yang dibatalkan
            // dengan slot masih di depan meninggalkan baris AKTIF, dan palang
            // tetap terbuka untuk pengajuan yang sudah mati (audit ulang 28 Sep).
            $efektif = app(VisitorEfektifService::class);
            foreach ($nomor as $no) {
                $efektif->akhiriAksesInspeksi((string) $no);
            }

            return $jumlah;
        });

        // Permit COKLAT yang masih menunggu saat kunjungannya sudah berakhir:
        // tamunya tak akan datang lagi (celah #2, keputusan user 26 Sep 2026).
        $coklatBerlalu = $this->langkah('coklat kunjungan berlalu', fn () => app(PermitCoklatTamuService::class)->tutupKunjunganBerlalu());

        // Kunjungan yang approval-nya kedaluwarsa tanpa keputusan (audit M11).
        $approvalKedaluwarsa = $this->langkah('tamu approval kedaluwarsa', function () {
            $ditutup = app(VisitorApprovalDecisionService::class)->tutupTamuApprovalKedaluwarsa();
            $this->kabariPemohon($ditutup);

            return count($ditutup);
        });

        // Permit karyawan yang approval-nya kedaluwarsa: tidak ditutup, hanya
        // dikabarkan sekali ke pemohonnya (permintaan user 3 Okt 2026).
        $permitKedaluwarsa = $this->langkah('permit approval kedaluwarsa', function () {
            $ditandai = app(VisitorApprovalDecisionService::class)->tandaiPermitApprovalKedaluwarsa();
            $this->kabariPemohonPermit($ditandai);

            return count($ditandai);
        });

        $this->info(
            "Permit per hari: {$permitPerHari}, permit berjendela (jam habis): {$permitBerjendela}, "
            ."permit coklat (tamu keluar): {$permitCoklatKeluar}, tamu: {$tamu}, "
            ."booking menggantung: {$bookingMenggantung}, coklat kunjungan berlalu: {$coklatBerlalu}, "
            ."tamu approval kedaluwarsa: {$approvalKedaluwarsa}, permit approval kedaluwarsa: {$permitKedaluwarsa}, "
            ."permit pemohon resign: {$permitResign}"
        );

        // Satu cabang yang gagal tidak lagi menghentikan cabang sesudahnya, tetapi
        // kegagalannya tetap terlihat: kode keluar FAILURE untuk penjadwal (audit, Low).
        return $this->gagal === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @var list<string> */
    private array $gagal = [];

    /**
     * Pemohon diberi tahu kunjungannya ditutup; tanpa ini ia menunggu keputusan
     * yang tidak akan pernah datang. Kegagalan notifikasi tidak menggagalkan sapuan.
     *
     * @param  list<array{no_transaksi:string, tipe_tamu:?string}>  $ditutup
     */
    private function kabariPemohon(array $ditutup): void
    {
        if ($ditutup === []) {
            return;
        }

        try {
            PublishVisitorNotificationsJob::dispatch(array_map(fn ($k) => [
                'kind' => 'decision',
                'domain' => $k['tipe_tamu'] === TipeTamu::B2B
                    ? VisitorNotificationService::DOMAIN_TAMU_B2B
                    : VisitorNotificationService::DOMAIN_TAMU,
                'no_transaksi' => $k['no_transaksi'],
                'decision' => VisitorNotificationService::KEPUTUSAN_DITOLAK,
                'context' => ['alasan' => VisitorApprovalDecisionService::ALASAN_APPROVAL_KEDALUWARSA],
            ], $ditutup));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public const ALASAN_RESIGN = 'Karyawan resign';

    public const EXPIRED_OLEH_RESIGN = 'Sistem (Resign)';

    /**
     * Akhiri permit karyawan yang pemohonnya sudah lewat hari kerja terakhir.
     *
     * - Akses gerbang (proyeksi Efektif, Sumber_Permit KARYAWAN, termasuk akses
     *   inspeksi) jadi EXPIRED bersebab, Flag_Sync='T' supaya palang menarik
     *   ulang. Statusnya di layar HCIS sudah EXPIRED lewat permitStatusSql().
     * - Pengajuan yang masih menunggu: Expired_At langkah approval-nya ditarik
     *   ke akhir hari resign. Dengan begitu semua penjaga kedaluwarsa yang ada
     *   (approve, batal, pindah jadwal) ikut menolaknya. Expired_by diisi
     *   supaya kabar "permit kedaluwarsa" tidak dikirim ke orang yang sudah
     *   keluar.
     */
    private function akhiriPermitResign(): int
    {
        $hariIni = app(VisitorPermitQueryService::class)->tanggalHariIniSql();

        $akses = DB::table('N_VISITOR_Permit_Efektif')
            ->where('Status_Efektif', 'AKTIF')
            ->where('Sumber_Permit', 'KARYAWAN')
            ->whereExists(fn ($q) => $q->select(DB::raw(1))
                ->from('Karyawan as k_rs')
                ->whereColumn('k_rs.Kode_Karyawan', 'N_VISITOR_Permit_Efektif.Kode_Karyawan_Pemohon')
                ->whereNotNull('k_rs.Tanggal_Resign')
                ->whereRaw("CAST(k_rs.Tanggal_Resign AS date) < {$hariIni}"))
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Alasan_Status' => self::ALASAN_RESIGN,
                'Updated_At' => now(),
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]);

        $pengajuan = DB::update("
            UPDATE ar
               SET ar.Expired_At = DATEADD(second, 86399, CAST(CAST(k.Tanggal_Resign AS date) AS datetime)),
                   ar.Expired_by = ?,
                   ar.update_at = ?
              FROM HRIS_Approval_Request ar
              JOIN N_EMI_VISITOR_Transaksi_Permit h ON h.No_Transaksi = ar.No_Transaksi
              JOIN Karyawan k ON k.Kode_Karyawan = h.Kode_Karyawan_Pemohon
             WHERE ar.Flag_Approval IS NULL
               AND ar.Expired_by IS NULL
               AND h.Flag_Aktif = 'Y'
               AND h.Flag_Approval IS NULL
               AND h.No_Transaksi_Tamu IS NULL
               AND ISNULL(h.Sumber_Pengajuan, 'HCIS') <> 'API'
               AND k.Tanggal_Resign IS NOT NULL
               AND CAST(k.Tanggal_Resign AS date) < {$hariIni}
        ", [self::EXPIRED_OLEH_RESIGN, now()]);

        return $akses + $pengajuan;
    }

    /** @param  list<string>  $nomor */
    private function kabariPemohonPermit(array $nomor): void
    {
        if ($nomor === []) {
            return;
        }

        try {
            PublishVisitorNotificationsJob::dispatch(array_map(fn ($no) => [
                'kind' => 'decision',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $no,
                'decision' => VisitorNotificationService::KEPUTUSAN_KEDALUWARSA,
            ], $nomor));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Jalankan satu cabang; galatnya dicatat, cabang berikutnya tetap jalan. */
    private function langkah(string $nama, callable $kerja): int
    {
        try {
            return (int) $kerja();
        } catch (\Throwable $e) {
            report($e);
            $this->gagal[] = $nama;
            $this->error("Cabang '{$nama}' gagal: ".$e->getMessage());

            return 0;
        }
    }
}
