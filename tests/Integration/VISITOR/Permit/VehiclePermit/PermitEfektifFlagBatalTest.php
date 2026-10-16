<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Flag_Batal harus mengikuti Status_Efektif, di SETIAP pintu.
 *
 * Pencabutan permit punya dua penulis yang berdiri sendiri:
 * revokeEfektifRow() untuk pencabutan satuan (dipakai permit dan tamu) dan
 * revokePermitEfektifMany() yang punya UPDATE sendiri untuk pencabutan
 * massal. Aturan yang hanya dipasang di salah satunya akan lolos uji satuan
 * dan bocor lewat tombol massal — bentuk kegagalan yang tidak menimbulkan
 * error apa pun, hanya baris yang salah tanda.
 *
 * Semua uji di sini menagih ISI KOLOM sesudah operasi, bukan pemanggilan
 * method, supaya pintu ketiga yang ditambahkan nanti ikut ketahuan.
 */
class PermitEfektifFlagBatalTest extends TestCase
{
    use DatabaseTransactions;

    private const PENCABUT = 'ZZFB01';

    private const ALASAN = 'Kendaraan sudah tidak dipakai di area pabrik.';

    public function test_pencabutan_satuan_menandai_flag_batal(): void
    {
        $nomor = $this->barisEfektifAktif();

        $this->assertSame('T', $this->flagBatal($nomor), 'Prasyarat gagal: barisnya sudah bertanda batal sejak awal.');

        app(VisitorEfektifService::class)->revokePermitEfektif($nomor, self::ALASAN, self::PENCABUT);

        $this->assertSame('Y', $this->flagBatal($nomor));
        $this->assertSame('DIBATALKAN', $this->statusEfektif($nomor));
    }

    public function test_pencabutan_massal_menandai_flag_batal(): void
    {
        // Pintu kedua. Kalau aturannya hanya dipasang di revokeEfektifRow(),
        // uji ini yang menyalakannya.
        $satu = $this->barisEfektifAktif();
        $dua = $this->barisEfektifAktif();

        $dicabut = app(VisitorEfektifService::class)
            ->revokePermitEfektifMany([$satu, $dua], self::ALASAN, self::PENCABUT);

        $this->assertCount(2, $dicabut, 'Pencabutan massal tidak menyentuh kedua barisnya.');

        foreach ([$satu, $dua] as $nomor) {
            $this->assertSame('Y', $this->flagBatal($nomor), "Baris {$nomor} dicabut tanpa ditandai.");
            $this->assertSame('DIBATALKAN', $this->statusEfektif($nomor));
        }
    }

    public function test_pencabutan_tamu_juga_menandai_flag_batal(): void
    {
        // Satu penulis yang sama melayani permit dan tamu. Uji ini menahan
        // penulis itu tetap satu; kalau suatu saat dipecah, salah satu sisi
        // akan berhenti menandai tanpa suara.
        $nomor = $this->barisEfektifTamuAktif();

        app(VisitorEfektifService::class)->revokeTamuEfektif($nomor, self::ALASAN, self::PENCABUT);

        $this->assertSame(
            'Y',
            DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Flag_Batal'),
        );
    }

    public function test_pencabutan_tamu_menulis_revoked_di_header_terjadwal(): void
    {
        // DDL-2026-121: dulu hanya proyeksi yang berubah, sehingga header tetap
        // "terjadwal" dan pencabutan tak terbedakan dari penolakan di laporan.
        $nomor = $this->barisEfektifTamuAktif(StatusKunjunganTamu::SCHEDULED);

        $terpengaruh = app(VisitorEfektifService::class)->revokeTamuEfektif($nomor, self::ALASAN, self::PENCABUT);

        $this->assertSame(1, $terpengaruh);
        $this->assertSame(StatusKunjunganTamu::REVOKED, $this->statusHeaderTamu($nomor));
    }

    public function test_pencabutan_tamu_yang_tak_mengenai_proyeksi_tidak_menyentuh_header(): void
    {
        // Proyeksi yang sudah tidak AKTIF = no-op. Header tidak boleh ikut
        // berubah: tidak ada pencabutan yang benar-benar terjadi.
        $nomor = $this->barisEfektifTamuAktif(StatusKunjunganTamu::SCHEDULED);
        DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->update(['Status_Efektif' => 'EXPIRED']);

        $terpengaruh = app(VisitorEfektifService::class)->revokeTamuEfektif($nomor, self::ALASAN, self::PENCABUT);

        $this->assertSame(0, $terpengaruh);
        $this->assertSame(StatusKunjunganTamu::SCHEDULED, $this->statusHeaderTamu($nomor));
    }

    public function test_pencabutan_tamu_tidak_menimpa_keputusan_header_lain(): void
    {
        // Hanya SCHEDULED yang menjadi REVOKED; keputusan lain (mis. WITHDRAWN)
        // tidak ditimpa walau proyeksinya kebetulan masih AKTIF.
        $nomor = $this->barisEfektifTamuAktif(StatusKunjunganTamu::WITHDRAWN);

        app(VisitorEfektifService::class)->revokeTamuEfektif($nomor, self::ALASAN, self::PENCABUT);

        $this->assertSame(StatusKunjunganTamu::WITHDRAWN, $this->statusHeaderTamu($nomor));
    }

    public function test_baris_baru_dari_sync_bertanda_tidak_batal(): void
    {
        $nomor = $this->buatPermit();

        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame('T', $this->flagBatal($nomor));
        $this->assertSame('AKTIF', $this->statusEfektif($nomor));
    }

    public function test_sync_ulang_meluruskan_tanda_yang_terlanjur_salah(): void
    {
        // Delapan permit AKTIF di development bertanda 'Y' padahal tidak
        // pernah dicabut. Karena Flag_Batal ikut di payload update, baris
        // aktif yang tandanya salah ikut diluruskan saat di-sync ulang.
        $nomor = $this->buatPermit();
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->update(['Flag_Batal' => 'Y']);
        $this->assertSame('Y', $this->flagBatal($nomor), 'Prasyarat gagal: tandanya tidak berhasil dirusak.');

        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame('T', $this->flagBatal($nomor));
    }

    public function test_kedaluwarsa_bukan_pembatalan(): void
    {
        // EXPIRED dan DIBATALKAN dua hal berbeda. Kalau auto-expire ikut
        // menandai batal, laporan "dicabut" akan membengkak oleh permit yang
        // sebenarnya hanya habis masa berlakunya.
        $nomor = $this->barisEfektifAktif(kadaluarsa: now()->subDays(3)->toDateString());

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('EXPIRED', $this->statusEfektif($nomor), 'Prasyarat gagal: barisnya tidak ikut kedaluwarsa.');
        $this->assertSame('T', $this->flagBatal($nomor));
    }

    public function test_sync_ulang_menyalakan_kembali_penanda_tarik_permit(): void
    {
        // Lubang yang ini TIDAK menimbulkan galat apa pun: baris yang sudah
        // pernah ditarik palang bertanda 'Y', dan payload update yang tidak
        // menyalakannya kembali membuat perubahan berikutnya — persetujuan,
        // ralat plat, geser jadwal — berhenti di HCIS. Gerbang terus memakai
        // data lama, dan tidak ada yang tahu sampai ada kendaraan yang salah.
        $nomor = $this->buatPermit();
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->tandaiSudahDitarikPalang('N_VISITOR_Permit_Efektif', $nomor);

        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame('T', $this->flagSync('N_VISITOR_Permit_Efektif', $nomor));
    }

    public function test_sync_ulang_menyalakan_kembali_penanda_tarik_tamu(): void
    {
        // Pintu kedua dengan payload update sendiri. Aturan yang hanya
        // dipasang di sisi permit akan lolos uji di atas dan bocor di sini.
        $nomor = $this->buatTamu();
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $this->tandaiSudahDitarikPalang('N_VISITOR_Tamu_Efektif', $nomor);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $this->assertSame('T', $this->flagSync('N_VISITOR_Tamu_Efektif', $nomor));
    }

    public function test_akses_inspeksi_yang_diakhiri_ditandai_batal_dan_ditarik_ulang(): void
    {
        // Bagi palang, pengajuan yang ditolak adalah pembatalan: tanpa
        // Flag_Batal='Y' ia tetap membuka gerbang pada jam slotnya.
        $nomor = $this->buatPermit();
        $slot = now()->addDay()->setTime(9, 0);

        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($nomor, $slot, $slot->copy()->addMinutes(30));
        $this->tandaiSudahDitarikPalang('N_VISITOR_Permit_Efektif', $nomor);

        $terpengaruh = app(VisitorEfektifService::class)->akhiriAksesInspeksi($nomor);

        $this->assertSame(1, $terpengaruh, 'Akses inspeksi tidak ditemukan saat hendak diakhiri.');
        $this->assertSame('Y', $this->flagBatal($nomor));
        $this->assertSame('T', $this->flagSync('N_VISITOR_Permit_Efektif', $nomor));

        // EXPIRED, bukan DIBATALKAN: cabang 'REVOKED' di permitStatusSql
        // membaca Status_Efektif dan pengajuan yang ditolak akan terbaca
        // "Dicabut" di seluruh layar — sebab yang salah untuk kejadian benar.
        $this->assertSame('EXPIRED', $this->statusEfektif($nomor));
    }

    public function test_akses_inspeksi_tidak_mengakhiri_permit_yang_sudah_disetujui(): void
    {
        // Sesudah disetujui, barisnya permit sungguhan. Penolakan susulan
        // tidak boleh mencabut akses yang sah hanya karena nomornya sama.
        $nomor = $this->buatPermit();
        $slot = now()->addDay()->setTime(9, 0);

        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($nomor, $slot, $slot->copy()->addMinutes(30));
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $terpengaruh = app(VisitorEfektifService::class)->akhiriAksesInspeksi($nomor);

        $this->assertSame(0, $terpengaruh, 'Permit yang sudah disetujui ikut diakhiri sebagai akses inspeksi.');
        $this->assertSame('AKTIF', $this->statusEfektif($nomor));
        $this->assertSame('T', $this->flagBatal($nomor));
    }

    public function test_sync_membangkitkan_baris_expired_bila_jendelanya_masih_akan_datang(): void
    {
        // Penyapu kedaluwarsa berjalan tiap lima menit, jadi kunjungan yang
        // harinya lewat hampir pasti sudah EXPIRED saat PIC menyuntingnya.
        // Tanpa kebangkitan ini, jadwal barunya tidak pernah sampai ke gerbang
        // dan tamunya ditolak di pos tanpa satu pun galat.
        $nomor = $this->buatTamu();
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->update(['Status_Efektif' => 'EXPIRED']);

        // Jadwal digeser ke besok, lalu disinkronkan seperti yang dilakukan
        // penyuntingan kunjungan.
        DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $nomor)
            ->update(['Tanggal_Kunjungan' => now()->addDays(2)->toDateString()]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $baris = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertSame('AKTIF', $baris->Status_Efektif, 'Jadwal baru tidak pernah sampai ke gerbang.');
        $this->assertSame('T', $baris->Flag_Sync);
    }

    public function test_sync_tidak_membangkitkan_baris_yang_jendelanya_sudah_lewat(): void
    {
        // Sisi berbahayanya. Tamu sudah check-out sehingga aksesnya diakhiri;
        // PIC lalu menyunting hal lain pada kunjungan yang sama. Jendelanya
        // tetap yang lama, jadi aksesnya TIDAK boleh terbuka lagi.
        $nomor = $this->buatTamu();
        DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('No_Transaksi', $nomor)
            ->update(['Tanggal_Kunjungan' => now()->subDays(3)->toDateString()]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->update(['Status_Efektif' => 'EXPIRED']);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $this->assertSame(
            'EXPIRED',
            (string) DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Status_Efektif'),
            'Akses tamu yang jendelanya sudah lewat terbuka kembali.',
        );
    }

    public function test_sync_tidak_pernah_menghidupkan_baris_yang_dicabut(): void
    {
        // Pencabutan adalah keputusan orang. Sinkronisasi susulan — persetujuan
        // susulan, ralat plat, geser jadwal — tidak boleh membatalkannya.
        $nomor = $this->buatPermit();
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);
        app(VisitorEfektifService::class)->revokePermitEfektif($nomor, self::ALASAN, self::PENCABUT);

        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame('DIBATALKAN', $this->statusEfektif($nomor), 'Permit yang dicabut hidup lagi lewat sync.');
        $this->assertSame('Y', $this->flagBatal($nomor));
    }

    public function test_akses_inspeksi_bertanda_jenis_inspeksi_bukan_lewat_teks(): void
    {
        // DDL-2026-132. Sebelum ini pembedanya kalimat di Nama_Durasi, dan satu
        // orang merapikan label itu memutus aplikasi palang sekaligus penyapu
        // kita sendiri — tanpa galat.
        $nomor = $this->buatPermit();
        $slot = now()->addDay()->setTime(9, 0);

        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($nomor, $slot, $slot->copy()->addMinutes(30));

        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertSame(VisitorEfektifService::JENIS_INSPEKSI, $baris->Jenis_Akses);

        // Kolom kategori berhenti merangkap dua makna: ia berisi kategori ASLI,
        // bukan kalimat status, supaya dashboard tidak punya ember hantu.
        $this->assertNotSame(VisitorEfektifService::KATEGORI_AKSES_INSPEKSI, $baris->Nama_Kategori_Permit);
    }

    public function test_persetujuan_mengubahnya_menjadi_permit_sungguhan(): void
    {
        $nomor = $this->buatPermit();
        $slot = now()->addDay()->setTime(9, 0);

        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($nomor, $slot, $slot->copy()->addMinutes(30));
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);

        $this->assertSame(
            VisitorEfektifService::JENIS_PERMIT,
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->value('Jenis_Akses'),
            'Baris tetap bertanda INSPEKSI sesudah disetujui; ia akan hilang dari seluruh laporan.',
        );
    }

    public function test_pencabutan_massal_tidak_menyeret_pengajuan_yang_belum_diputus(): void
    {
        // Kegagalan yang paling merusak dari invarian yang berubah: "pilih
        // semua yang cocok" merangkai targetnya dari baris AKTIF di proyeksi
        // gerbang. Pengajuan yang baru memesan slot inspeksi kini punya baris
        // seperti itu, dan mencabutnya berarti membatalkan keputusan yang
        // belum pernah diambil.
        $nomor = $this->buatPermit();
        $slot = now()->addDay()->setTime(9, 0);
        app(VisitorEfektifService::class)->terbitkanAksesInspeksi($nomor, $slot, $slot->copy()->addMinutes(30));

        $kena = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->join('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->where('pe.Status_Efektif', 'AKTIF')
            ->where('pe.Jenis_Akses', VisitorEfektifService::JENIS_PERMIT)
            ->where('h.No_Transaksi', $nomor)
            ->exists();

        $this->assertFalse($kena, 'Pengajuan yang belum diputus ikut masuk daftar pencabutan massal.');
    }

    // ---------------------------------------------------------------- helpers

    private function flagSync(string $tabel, string $nomor): ?string
    {
        $baris = DB::table($tabel)->where('No_Transaksi', $nomor)->first();

        $this->assertNotNull($baris, "Baris {$tabel} untuk {$nomor} tidak ada.");

        return $baris->Flag_Sync;
    }

    /**
     * Palang sudah menarik barisnya. Tanpa langkah ini seluruh uji di atas
     * berangkat dari 'T' dan akan tetap hijau walau perbaikannya dicabut —
     * penjaga yang tidak menjaga apa pun.
     */
    private function tandaiSudahDitarikPalang(string $tabel, string $nomor): void
    {
        DB::table($tabel)->where('No_Transaksi', $nomor)->update(['Flag_Sync' => 'Y']);

        $this->assertSame('Y', $this->flagSync($tabel, $nomor), 'Prasyarat gagal: penanda tarik tidak berhasil dimatikan.');
    }

    /** Kunjungan tamu lengkap, supaya syncTamuEfektif() punya bahan. */
    private function buatTamu(): string
    {
        $nomor = 'TMU-FBS-'.Str::upper(Str::random(10));
        $pic = $this->pemohonNyata();

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => $pic,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => now()->addDay()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $pic,
            'Idempotency_Key' => 'FBS-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Sync',
            'Tipe_Tamu' => 'REGULAR',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
        ]);

        return $nomor;
    }

    private function flagBatal(string $nomor): ?string
    {
        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertNotNull($baris, "Baris efektif untuk {$nomor} tidak ada.");

        return $baris->Flag_Batal;
    }

    private function statusEfektif(string $nomor): string
    {
        return (string) DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->value('Status_Efektif');
    }

    /** Baris efektif AKTIF yang tandanya sudah benar sejak awal. */
    private function barisEfektifAktif(?string $kadaluarsa = null): string
    {
        $nomor = 'PMT-FB-'.Str::upper(Str::random(10));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PENCABUT,
            'Nama_Pemohon' => 'Pemohon Uji',
            'Status_Efektif' => 'AKTIF',
            'Flag_Batal' => 'T',
            'Tanggal_Mulai_Berlaku' => now()->subDays(10)->toDateString(),
            'Tanggal_Kadaluarsa' => $kadaluarsa ?? now()->addMonths(3)->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now()->subDay(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);

        return $nomor;
    }

    private function statusHeaderTamu(string $nomor): ?string
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan');
    }

    /**
     * `$statusHeader` null = proyeksi saja tanpa header (bentuk lama uji ini).
     */
    private function barisEfektifTamuAktif(?string $statusHeader = null): string
    {
        $nomor = 'TMU-FB-'.Str::upper(Str::random(10));

        if ($statusHeader !== null) {
            $pic = $this->pemohonNyata();

            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insert([
                'No_Transaksi' => $nomor,
                'Kode_Karyawan_PIC' => $pic,
                'Status_Kunjungan' => $statusHeader,
                'Flag_Approval' => 'Y',
                'Tanggal_Kunjungan' => now()->addDay()->toDateString(),
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $pic,
                'Idempotency_Key' => 'FB-'.Str::upper(Str::random(20)),
            ]);
        }

        DB::table('N_VISITOR_Tamu_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Nama_Tamu' => 'Tamu Uji',
            'Kode_Karyawan_PIC' => self::PENCABUT,
            'Nama_PIC' => 'PIC Uji',
            'Tanggal_Kunjungan' => now()->addDay()->toDateString(),
            'Status_Efektif' => 'AKTIF',
            'Flag_Batal' => 'T',
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now()->subDay(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);

        return $nomor;
    }

    /**
     * Kode karyawan yang BENAR-BENAR ada, untuk fixture yang disinkronkan.
     *
     * syncPermitEfektif() menyusun Nama_Pemohon lewat JOIN ke Karyawan, dan
     * kolom itu NOT NULL. Kode karangan seperti PENCABUT lolos di fixture yang
     * mengisi namanya sendiri, tetapi pada jalur sync ia menghasilkan NULL dan
     * INSERT-nya ditolak basis data — bukan galat validasi yang mudah dibaca,
     * melainkan SQLSTATE 23000 dari kedalaman query builder.
     */
    private function pemohonNyata(): string
    {
        $kode = DB::table('Karyawan')
            ->whereNotNull('UserID_Web')
            ->orderBy('Kode_Karyawan')
            ->value('Kode_Karyawan');

        $this->assertNotNull($kode, 'Tidak ada karyawan ber-UserID_Web untuk dijadikan pemohon.');

        return (string) $kode;
    }

    /** Pengajuan permit lengkap, supaya syncPermitEfektif() punya bahan. */
    private function buatPermit(): string
    {
        $nomor = 'PMT-FBS-'.Str::upper(Str::random(10));
        $pemohon = $this->pemohonNyata();

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => $pemohon,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Approved_At' => now()->subDay(),
            'Created_At' => now()->subDays(2),
            'Created_By' => $pemohon,
            'Idempotency_Key' => 'FB-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(100000, 999999),
            'Plat_Nomor_Suffix' => 'FB',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        return $nomor;
    }
}
