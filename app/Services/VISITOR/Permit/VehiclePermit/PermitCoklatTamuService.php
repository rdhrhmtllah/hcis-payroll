<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penerbit permit COKLAT dari sebuah kunjungan tamu.
 *
 * Rancangan sebelumnya menulis permit COKLAT LANGSUNG ke proyeksi gerbang
 * sebagai baris AKTIF: tidak ada transaksi permit, tidak ada alur persetujuan,
 * dan karenanya tidak ada apa pun yang bisa disetujui atau ditolak. Izin akses
 * fisik terbit begitu kunjungannya efektif.
 *
 * Sekarang permit COKLAT adalah transaksi permit yang sesungguhnya — bernomor
 * PMT sendiri, tertaut ke kunjungannya lewat No_Transaksi_Tamu, dan melewati
 * mesin approval permit yang sama dengan permit kendaraan karyawan. Barisnya di
 * proyeksi gerbang baru lahir lewat syncPermitEfektif() setelah persetujuan
 * final, persis seperti permit lain.
 *
 * Kenapa satu kelas, bukan tersebar di pemanggilnya: ada TIGA pintu yang
 * menerbitkan permit ini — registrasi tamu yang auto-approve, persetujuan tamu
 * oleh verifikator, dan penyuntingan data tamu. Selama masing-masing menyusun
 * insert-nya sendiri, setiap aturan baru hanya akan terpasang di salah satunya.
 */
class PermitCoklatTamuService
{
    public const T_PERMIT = 'N_EMI_VISITOR_Transaksi_Permit';

    public const T_PERMIT_DETAIL = 'N_EMI_VISITOR_Transaksi_Permit_Detail';

    public const T_TAMU = 'N_EMI_VISITOR_Transaksi_Tamu';

    public const T_TAMU_DETAIL = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    /**
     * Tidak ada master durasi satu hari — master hanya mengenal hitungan bulan.
     * Karena itu durasinya disimpan sebagai snapshot teks, dan proyeksi membaca
     * snapshot itu ketika master durasinya kosong.
     */
    public const NAMA_DURASI = '1 Hari';

    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected VisitorEfektifService $visitorEfektif,
        protected PermitNomorTransaksi $nomorTransaksi,
        protected VisitorPermitChecklistService $permitChecklist,
        protected VisitorPermitQueryService $permitQuery,
    ) {}

    /**
     * Terbitkan — atau selaraskan — pengajuan permit COKLAT milik satu kunjungan.
     *
     * Aman dipanggil berulang: satu kunjungan tepat satu permit, dijaga
     * UX_VISITOR_Permit_Transaksi_Tamu di basis data, bukan hanya di sini.
     *
     * @return string|null nomor transaksi permitnya, atau null bila kunjungan
     *                     ini memang tidak berhak atas permit COKLAT
     */
    public function sinkron(string $noTransaksiTamu): ?string
    {
        $tamu = $this->kunjungan($noTransaksiTamu);

        if (! $tamu) {
            return null;
        }

        $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
            ->where('Nama_Kategori', 'COKLAT')
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $kategori) {
            throw new \RuntimeException('Master kategori permit COKLAT tidak ada atau nonaktif.');
        }

        $ada = DB::table(self::T_PERMIT)
            ->where('No_Transaksi_Tamu', $noTransaksiTamu)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if ($ada) {
            return $this->selaraskan($ada, $tamu, $kategori);
        }

        return $this->terbitkan($tamu, $kategori);
    }

    /**
     * Kunjungannya dibatalkan — izin gerbangnya ikut mati.
     *
     * Dua keadaan harus ditutup sekaligus, dan melewatkan salah satunya tidak
     * menyalakan galat apa pun:
     *
     *  - Masih PENDING: pengajuannya menggantung selamanya di antrean
     *    verifikator, untuk kunjungan yang sudah tidak ada.
     *  - Sudah APPROVED: izinnya tetap hidup di gerbang, dan tamu dari
     *    kunjungan yang dibatalkan tetap bisa masuk.
     *
     * @return int jumlah baris proyeksi yang benar-benar dicabut
     */
    public function batalkan(string $noTransaksiTamu, ?string $alasan, string $olehKode): int
    {
        $noPermit = $this->nomorPermitUntukTamu($noTransaksiTamu);

        if (! $noPermit) {
            return 0;
        }

        DB::transaction(function () use ($noPermit, $alasan, $olehKode) {
            DB::table(self::T_PERMIT)
                ->where('No_Transaksi', $noPermit)
                ->whereNull('Flag_Approval')
                ->update(['Flag_Approval' => 'T', 'Updated_At' => now(), 'Updated_By' => $olehKode]);

            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $noPermit)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'T',
                    'Catatan' => $alasan,
                    'Tanggal_Approval' => now(),
                    'update_at' => now(),
                ]);
        });

        return $this->visitorEfektif->revokePermitEfektif($noPermit, $alasan, $olehKode);
    }

    /**
     * Cabut kunjungan beserta permit COKLAT-nya dalam SATU transaksi (audit M5).
     *
     * Dulu keduanya berjalan terpisah dan pembatalan permit dilewati bila baris
     * kunjungan di gerbang sudah tidak AKTIF — sehingga izin COKLAT berjendela
     * lebih panjang tetap hidup, dan kegagalan di tengah tak bisa diulang (404).
     *
     * @return array{tamu:int, permit:int} baris gerbang yang benar-benar dicabut
     */
    public function cabutKunjunganBesertaPermit(string $noTransaksiTamu, ?string $alasan, string $olehKode): array
    {
        return DB::transaction(fn () => [
            'tamu' => $this->visitorEfektif->revokeTamuEfektif($noTransaksiTamu, $alasan, $olehKode),
            'permit' => $this->batalkan($noTransaksiTamu, $alasan, $olehKode),
        ]);
    }

    public const ALASAN_KUNJUNGAN_BERLALU = 'Ditutup otomatis: kunjungan sudah berlalu tanpa keputusan permit.';

    /**
     * Tutup permit COKLAT yang masih menunggu padahal kunjungannya sudah berakhir.
     *
     * Batasnya akhir jendela yang diusulkan permitnya (sejak 29 Sep 2026: 23:59
     * hari terakhir kunjungan), jatuh ke estimasi KELUAR kunjungan bila
     * jendelanya kosong — bukan tanggalnya, jadi kunjungan yang diperpanjang
     * tetap hidup, dan tamu yang pulangnya molor dari estimasinya masih bisa
     * diputus permitnya hari itu juga. Hanya pengajuan yang belum diputus: izin yang
     * sudah disetujui berakhir lewat jalurnya sendiri di proyeksi gerbang.
     * Sengaja tidak memakai gugurkanPermit() — "gugur karena tidak hadir" bukan
     * sebabnya di sini.
     *
     * @return int jumlah permit yang ditutup
     */
    public function tutupKunjunganBerlalu(string $oleh = 'visitor:expire-efektif'): int
    {
        $keluar = app(\App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService::class)->estimasiKeluarSql('t', 'td');

        $nomor = DB::table(self::T_PERMIT.' as p')
            ->join('N_EMI_VISITOR_Transaksi_Tamu as t', 't.No_Transaksi', '=', 'p.No_Transaksi_Tamu')
            ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as td', 'td.No_Transaksi', '=', 't.No_Transaksi')
            ->leftJoin(self::T_PERMIT_DETAIL.' as pd', 'pd.No_Transaksi', '=', 'p.No_Transaksi')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNull('p.Flag_Approval')
            ->whereNotNull('p.No_Transaksi_Tamu')
            ->whereRaw("COALESCE(pd.Waktu_Berakhir, {$keluar}) < ?", [now()])
            ->distinct()
            ->pluck('p.No_Transaksi')
            ->all();

        foreach (array_chunk($nomor, 500) as $bagian) {
            DB::transaction(function () use ($bagian, $oleh) {
                DB::table(self::T_PERMIT)
                    ->whereIn('No_Transaksi', $bagian)
                    ->whereNull('Flag_Approval')
                    ->update(['Flag_Approval' => 'T', 'Updated_At' => now(), 'Updated_By' => $oleh]);

                DB::table('HRIS_Approval_Request')
                    ->whereIn('No_Transaksi', $bagian)
                    ->whereNull('Flag_Approval')
                    ->update([
                        'Flag_Approval' => 'T',
                        'Catatan' => self::ALASAN_KUNJUNGAN_BERLALU,
                        'Tanggal_Approval' => now(),
                        'update_at' => now(),
                    ]);
            });
        }

        return count($nomor);
    }

    /** Nomor permit COKLAT milik satu kunjungan, bila sudah pernah diajukan. */
    public function nomorPermitUntukTamu(string $noTransaksiTamu): ?string
    {
        return DB::table(self::T_PERMIT)
            ->where('No_Transaksi_Tamu', $noTransaksiTamu)
            ->where('Flag_Aktif', 'Y')
            ->value('No_Transaksi');
    }

    /**
     * Setujui tanpa verifikator.
     *
     * Dipakai HANYA ketika kebijakan approval permit memang dimatikan, dan oleh
     * seeder demo. Ia tetap lewat sini, bukan menulis proyeksi sendiri, supaya
     * bentuk baris permit yang aktif cuma punya satu asal.
     */
    public function setujuiOtomatis(string $noPermit, string $olehKode): void
    {
        // Alur persetujuannya ikut ditutup, bukan hanya header permitnya.
        // syncPermitEfektif() menolak menerbitkan selama masih ada langkah
        // approval yang menggantung, jadi melewatkan bagian ini menghasilkan
        // permit yang berstatus disetujui tetapi tidak pernah sampai ke gerbang
        // — tanpa satu pun galat. Tanggal_Approval wajib terisi: Disetujui_At di
        // proyeksi NOT NULL dan diturunkan dari kolom ini.
        DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noPermit)
            ->whereNull('Flag_Approval')
            ->update([
                'Flag_Approval' => 'Y',
                'Approved_By_Kode' => $olehKode,
                'Tanggal_Approval' => now(),
                'update_at' => now(),
            ]);

        DB::table(self::T_PERMIT)
            ->where('No_Transaksi', $noPermit)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Flag_Approval')
            ->update([
                'Flag_Approval' => 'Y',
                'Approved_At' => now(),
                'Approved_By' => $olehKode,
                'Updated_At' => now(),
                'Updated_By' => $olehKode,
            ]);

        $this->visitorEfektif->syncPermitEfektif($noPermit);
    }

    /**
     * Kunjungan yang berhak atas permit COKLAT.
     *
     * Empat syaratnya digabung di satu tempat supaya ketiga pintu penerbit tidak
     * bisa berbeda pendapat: aktif, bertipe B2B, bertanggal, dan persetujuan
     * kunjungannya sudah final. Syarat terakhir yang paling mudah terlewat —
     * mengajukan permit untuk kunjungan yang masih menunggu approval berarti
     * verifikator diminta menyetujui izin gerbang bagi tamu yang belum tentu
     * jadi datang.
     */
    private function kunjungan(string $noTransaksiTamu): ?object
    {
        $tamu = DB::table(self::T_TAMU.' as h')
            ->leftJoin(self::T_TAMU_DETAIL.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.No_Transaksi', $noTransaksiTamu)
            ->where('h.Flag_Aktif', 'Y')
            ->first([
                'h.No_Transaksi',
                'h.Kode_Karyawan_PIC',
                'h.Tanggal_Kunjungan',
                'h.Flag_Approval',
                'h.Sumber_Pengajuan',
                'h.Id_Api_Aplikasi',
                'h.Referensi_Mitra',
                'd.Tipe_Tamu',
                'd.Nama_Lengkap_Tamu',
                'd.Instansi_Atau_Asal',
                'd.Deksripsi_Keperluan',
                'd.No_Identitas',
                'd.Jenis_Identitas',
                'd.Jenis_Kendaraan',
                'd.Plat_Nomor_Prefix',
                'd.Plat_Nomor_Angka',
                'd.Plat_Nomor_Suffix',
                'd.Waktu_Mulai_Kunjungan',
                'd.Waktu_Selesai_Kunjungan',
            ]);

        if (! $tamu) {
            return null;
        }

        if (($tamu->Tipe_Tamu ?? 'REGULAR') !== 'B2B') {
            return null;
        }

        if (! $tamu->Tanggal_Kunjungan || $tamu->Flag_Approval !== 'Y') {
            return null;
        }

        return $tamu;
    }

    private function terbitkan(object $tamu, object $kategori): string
    {
        $pic = (string) $tamu->Kode_Karyawan_PIC;
        $noPermit = $this->nomorTransaksi->buat('PMT');

        $headerId = DB::table(self::T_PERMIT)->insertGetId([
            'No_Transaksi' => $noPermit,
            'Kode_Karyawan_Pemohon' => $pic,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            // Sumber pengajuannya mengikuti sumber KUNJUNGAN-nya: permit yang
            // lahir dari registrasi mitra tetap permit mitra, dan laporan yang
            // memisah keduanya tidak boleh kehilangan jejaknya di sini.
            'Sumber_Pengajuan' => $tamu->Sumber_Pengajuan ?: 'HCIS',
            'Id_Api_Aplikasi' => $tamu->Id_Api_Aplikasi,
            'Referensi_Mitra' => $tamu->Referensi_Mitra,
            'No_Transaksi_Tamu' => $tamu->No_Transaksi,
            'Created_At' => now(),
            'Created_By' => $pic,
            'Updated_At' => now(),
            'Updated_By' => $pic,
        ]);

        $idDetail = DB::table(self::T_PERMIT_DETAIL)->insertGetId(array_merge(
            ['Id_Transaksi_Permit' => $headerId, 'No_Transaksi' => $noPermit],
            $this->detail($tamu, $kategori),
        ));

        // Inspeksi fisik kendaraan tamu adalah tugas verifikator, jadi
        // pertanyaannya harus sudah menunggu di mejanya. Tipe kendaraannya
        // sengaja tidak dikirim: registrasi tamu hanya punya teks bebas, dan
        // justru verifikator yang menetapkan modelnya saat review.
        //
        // Tamu pejalan kaki tidak membawa apa pun untuk diperiksa.
        if ($this->permitQuery->wajibInspeksiKendaraan($noPermit)) {
            $this->permitChecklist->buatSnapshot(
                $noPermit,
                (int) $idDetail,
                (int) $kategori->Id_Kategori_Permit,
            );
        }

        // Tanpa unggahan dokumen dari pemohon dan tanpa pemeriksaan kuota
        // karyawan: keduanya mesin permit kendaraan karyawan yang tidak berlaku
        // bagi izin sehari milik tamu. Dokumennya diunggah verifikator di meja,
        // bukan oleh yang mendaftarkan tamunya.
        try {
            $this->visitorApprovalPolicy->createFlow('permit', $noPermit, $pic);
        } catch (\Throwable $e) {
            // Dibiarkan menggagalkan seluruh transaksi, bukan ditelan. Permit
            // tanpa alur tidak akan pernah muncul di meja siapa pun; ia akan
            // menggantung sebagai izin yang tidak pernah terbit, dan tamunya
            // baru tahu ketika sudah berdiri di gerbang.
            throw new \DomainException(
                'Permit COKLAT untuk kunjungan ini tidak dapat diajukan: '.$e->getMessage()
                    .' PIC '.$pic.' belum punya alur approval permit.',
                0,
                $e,
            );
        }

        if (! (bool) $this->visitorApprovalPolicy->config('permit')['needs_approval']) {
            $this->setujuiOtomatis($noPermit, $pic);
        }

        return $noPermit;
    }

    /**
     * Data kunjungan berubah — permitnya ikut, selama belum diputuskan.
     *
     * Permit yang sudah DITOLAK tidak dihidupkan kembali oleh penyuntingan data
     * tamu: keputusan verifikator bukan sesuatu yang bisa dibatalkan dengan
     * mengganti satu huruf pada nama tamunya.
     */
    /**
     * Apakah jendela permit ini masih jendela BAWAAN turunan tanggal kunjungan?
     *
     * Bawaannya berbentuk tepat satu hari penuh dari tengah malam ke tengah
     * malam — lihat detail(). Bentuk lain apa pun berarti ada yang menetapkannya
     * dengan sengaja, dan itu keputusan yang tidak boleh disinkronkan pergi.
     *
     * Jendela yang kosong dihitung BAWAAN: tidak ada keputusan yang hilang bila
     * ia ditulisi.
     */
    private function jendelaMasihBawaan(string $noPermit, ?object $tamu = null): bool
    {
        $detail = DB::table(self::T_PERMIT_DETAIL)
            ->where('No_Transaksi', $noPermit)
            ->first(['Waktu_Mulai_Berlaku', 'Waktu_Berakhir']);

        if (! $detail || ! $detail->Waktu_Mulai_Berlaku || ! $detail->Waktu_Berakhir) {
            return true;
        }

        $mulai = Carbon::parse($detail->Waktu_Mulai_Berlaku);
        $akhir = Carbon::parse($detail->Waktu_Berakhir);
        $tengahMalam = $mulai->copy()->startOfDay();

        if ($mulai->equalTo($tengahMalam) && $akhir->equalTo($tengahMalam->copy()->addDay())) {
            return true;
        }

        if (! $tamu || empty($tamu->Waktu_Mulai_Kunjungan) || empty($tamu->Waktu_Selesai_Kunjungan)) {
            return false;
        }

        // Dua bentuk bawaan kunjungan berestimasi: yang berlaku sekarang
        // (Estimasi Masuk s.d. 23:59 hari terakhirnya — lihat jendela()), dan
        // bentuk sebelum 29 Sep 2026 (Estimasi Masuk–Keluar apa adanya) yang
        // masih melekat pada permit lama. Keduanya bukan keputusan verifikator.
        [$estMulai, $estAkhir] = $this->jendela($tamu);
        $estKeluar = Carbon::parse($tamu->Waktu_Selesai_Kunjungan);

        return $mulai->equalTo($estMulai) && ($akhir->equalTo($estAkhir) || $akhir->equalTo($estKeluar));
    }

    /**
     * Jendela bawaan permit: sejak Estimasi Masuk kunjungannya sampai 23:59
     * pada tanggal terakhir kunjungan, atau satu hari kalender penuh untuk
     * kunjungan lama tanpa estimasi.
     *
     * Kunjungan MITRA berlangsung beberapa hari; permit satu hari membuat
     * gerbang menolaknya mulai hari kedua.
     *
     * Berakhir 23:59, bukan di jam Estimasi Keluar (permintaan user 29 Sep
     * 2026): estimasi keluar hanya perkiraan tamu, dan izin sehari yang mati
     * pukul 12:00 menahan tamu yang pulangnya molor di gerbang. Ini hanya
     * USULAN — verifikator menetapkan jendela akhirnya saat menyetujui.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function jendela(object $tamu): array
    {
        if (! empty($tamu->Waktu_Mulai_Kunjungan) && ! empty($tamu->Waktu_Selesai_Kunjungan)) {
            // Estimasi keluar TEPAT tengah malam milik hari sebelumnya — tanpa
            // mundur sedetik, kunjungan s.d. 30/09 00:00 berlaku sampai 30/09 23:59.
            $hariTerakhir = Carbon::parse($tamu->Waktu_Selesai_Kunjungan)->subSecond()->startOfDay();

            return [Carbon::parse($tamu->Waktu_Mulai_Kunjungan), $hariTerakhir->setTime(23, 59)];
        }

        $mulai = Carbon::parse($tamu->Tanggal_Kunjungan)->startOfDay();

        // Batas setengah-terbuka [mulai, akhir): tepat satu hari penuh tanpa
        // kehilangan presisi saat datetime2(0) membulatkan 23:59:59.999999.
        return [$mulai, $mulai->copy()->addDay()];
    }

    private function selaraskan(object $permit, object $tamu, object $kategori): string
    {
        if ($permit->Flag_Approval === 'T') {
            return $permit->No_Transaksi;
        }

        $detail = $this->detail($tamu, $kategori);

        // Dua hal benar sekaligus di sini, dan keduanya mudah saling menghapus:
        //
        //  - Mengubah tanggal kunjungan HARUS sampai ke gerbang. Jendela basi
        //    di proyeksi bukan sekadar tampilan keliru; ia masa berlaku akses
        //    fisik yang salah.
        //  - Jendela yang DIPILIH verifikator saat menyetujui bukan turunan
        //    tanggal kunjungan. Menimpanya berarti izin tiga hari yang terbit
        //    kemarin mati hari ini, tanpa satu pun galat yang menjelaskan
        //    kenapa tamunya ditahan di gerbang.
        //
        // Pembedanya BENTUK jendelanya, bukan niat yang tidak tercatat di mana
        // pun: jendela bawaan selalu tepat [D 00:00, D+1 00:00), sedangkan
        // jendela pilihan verifikator hampir tidak pernah begitu.
        if ($permit->Flag_Approval === 'Y' && ! $this->jendelaMasihBawaan($permit->No_Transaksi, $tamu)) {
            unset(
                $detail['Waktu_Mulai_Berlaku'],
                $detail['Waktu_Berakhir'],
                $detail['Tanggal_Mulai_Berlaku'],
                $detail['Tanggal_Kadaluarsa'],
                $detail['Snapshot_Nama_Durasi'],
            );
        }

        DB::table(self::T_PERMIT_DETAIL)
            ->where('No_Transaksi', $permit->No_Transaksi)
            ->update($detail);

        DB::table(self::T_PERMIT)
            ->where('No_Transaksi', $permit->No_Transaksi)
            ->update([
                'Referensi_Mitra' => $tamu->Referensi_Mitra,
                'Updated_At' => now(),
                'Updated_By' => (string) $tamu->Kode_Karyawan_PIC,
            ]);

        // Permit yang sudah disetujui sudah punya baris di gerbang, dan baris
        // itu memuat tanggal serta plat yang barusan berubah. Tanpa sinkron
        // ulang, aplikasi palang terus membaca nilai lama tanpa satu pun galat.
        if ($permit->Flag_Approval === 'Y') {
            $this->visitorEfektif->syncPermitEfektif($permit->No_Transaksi);
        }

        return $permit->No_Transaksi;
    }

    /**
     * Isi detail permit yang diturunkan dari kunjungannya.
     *
     * @return array<string,mixed>
     */
    private function detail(object $tamu, object $kategori): array
    {
        [$mulai, $selesai] = $this->jendela($tamu);
        $jumlahHari = max(1, (int) ceil($mulai->diffInSeconds($selesai) / 86400));

        return [
            'Plat_Nomor_Prefix' => $tamu->Plat_Nomor_Prefix,
            'Plat_Nomor_Angka' => $tamu->Plat_Nomor_Angka,
            'Plat_Nomor_Suffix' => $tamu->Plat_Nomor_Suffix,
            'Id_Master_Kategori_Permit' => $kategori->Id_Kategori_Permit,
            'Snapshot_Kategori_Permit' => $kategori->Nama_Kategori,
            // Master durasi hanya mengenal hitungan bulan; izin sehari milik
            // tamu tidak punya barisnya di sana.
            'Id_Master_Durasi' => null,
            'Snapshot_Nama_Durasi' => $jumlahHari === 1 ? self::NAMA_DURASI : $jumlahHari.' Hari',
            'Keterangan_Pengajuan' => trim(sprintf(
                'Tamu: %s (%s). Keperluan: %s',
                $tamu->Nama_Lengkap_Tamu ?: '-',
                $tamu->Instansi_Atau_Asal ?: '-',
                $tamu->Deksripsi_Keperluan ?: '-',
            )),
            // Identitas pemegang izin — bukan pemohonnya. Gerbang membaca satu
            // baris proyeksi saja; tanpa kolom ini satpam tidak punya petunjuk
            // siapa yang sebenarnya datang.
            'Nama_Tamu' => $tamu->Nama_Lengkap_Tamu,
            'Instansi_Tamu' => $tamu->Instansi_Atau_Asal,
            'No_Identitas_Tamu' => $tamu->No_Identitas,
            'Jenis_Identitas_Tamu' => $tamu->Jenis_Identitas,
            'Jenis_Kendaraan_Tamu' => $tamu->Jenis_Kendaraan,
            'Waktu_Mulai_Berlaku' => $mulai,
            'Waktu_Berakhir' => $selesai,
            'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
            // Kolom tanggal lama bersifat INKLUSIF, Waktu_Berakhir eksklusif:
            // tanggal kedaluwarsa = tanggal detik terakhir sebelum batasnya.
            'Tanggal_Kadaluarsa' => $selesai->copy()->subSecond()->toDateString(),
        ];
    }
}
