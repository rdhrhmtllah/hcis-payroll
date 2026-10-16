<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Permit coklat berakhir pada MANA YANG LEBIH DULU: habis jendelanya, atau tamu
 * keluar — dan yang kedua HANYA berlaku bagi izin satu tanggal.
 *
 * Jendela BAWAANNYA (sejak DDL-2026-121) rentang Estimasi Masuk–Keluar
 * kunjungannya; kunjungan lama tanpa estimasi memakai satu hari kalender
 * [tanggal 00:00, tanggal+1 00:00). Verifikator boleh melebarkannya saat
 * menyetujui, dan sejak itu "tamu tercatat keluar" berhenti berarti
 * "kunjungannya selesai" — pulang pada penghujung hari adalah perilaku normal
 * izin berhari-hari.
 *
 * Cabang check-out WAJIB memakai definisi kehadiran resmi
 * (COALESCE(efektif.Waktu_Aktual_Keluar, detail.Waktu_Aktual_Keluar)) dan TIDAK
 * BOLEH memakai Status_Kunjungan. Kolom itu status KEPUTUSAN (DDL-2026-121):
 * palang maupun tombol manual tidak menyentuhnya, sehingga tamu yang sudah
 * pulang tetap berstatus SCHEDULED di header.
 */
class PermitCoklatBerakhirTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;

    /**
     * Kode karyawan NYATA di Web_HRIS development.
     *
     * Kode karangan tidak bisa dipakai: syncTamuEfektif() menulis Nama_PIC yang
     * NOT NULL di N_VISITOR_Tamu_Efektif, dan namanya di-resolve lewat JOIN ke
     * tabel Karyawan. PIC yang tidak ada membuat seluruh registrasi gagal 500 —
     * kegagalan yang menyamar sebagai bug fitur, padahal fixture-nya yang salah.
     */
    private const PIC = 'A1';

    protected function setUp(): void
    {
        parent::setUp();

        // A1 tercatat resign di dev (3 Sep 2025). Di sini ia diperlakukan masih
        // bekerja; perubahannya ikut dibatalkan bersama transaksi uji.
        DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->update(['Tanggal_Resign' => null]);
    }

    public function test_permit_mati_setelah_hari_kunjungannya_lewat(): void
    {
        $nomor = $this->buatPermitCoklat(now()->subDay());

        Artisan::call('visitor:expire-efektif');

        $baris = $this->permitTamu($nomor);

        $this->assertSame('EXPIRED', $baris->Status_Efektif);
        // Pesannya berubah dari "Jendela kunjungan berakhir" ketika cabang jam
        // digeneralisasi untuk SEMUA permit berjendela, bukan hanya permit tamu:
        // kata "kunjungan" tidak berlaku bagi permit mitra yang lewat cabang
        // yang sama.
        $this->assertSame('Jendela berlaku berakhir', $baris->Alasan_Status);
        $this->assertSame('T', $baris->Flag_Sync, 'Perubahan tidak ditandai untuk ditarik ulang gerbang.');
    }

    public function test_permit_masih_hidup_pada_hari_kunjungannya(): void
    {
        $nomor = $this->buatPermitCoklat(now());

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('AKTIF', $this->permitTamu($nomor)->Status_Efektif);
    }

    public function test_jendela_permit_mengikuti_estimasi_walau_lintas_hari(): void
    {
        // Estimasi 20:00 sampai 06:00 keesokan hari. Dulu izin gerbangnya
        // dipaku satu hari kalender; sejak DDL-2026-121 ia mengikuti rentang
        // estimasi yang diputuskan approver kunjungan, supaya tamu malam tidak
        // ditolak gerbang lewat tengah malam.
        $tanggal = now()->startOfDay();
        $nomor = $this->buatPermitCoklat(
            $tanggal,
            $tanggal->copy()->setTime(20, 0),
            $tanggal->copy()->addDay()->setTime(6, 0),
        );

        $baris = $this->permitTamu($nomor);

        $this->assertSame(
            $tanggal->format('Y-m-d 20:00:00'),
            substr((string) $baris->Waktu_Mulai_Berlaku, 0, 19),
        );
        // Sejak 29 Sep 2026 usulannya berakhir 23:59 di hari keluarnya.
        $this->assertSame(
            $tanggal->copy()->addDay()->format('Y-m-d 23:59:00'),
            substr((string) $baris->Waktu_Berakhir, 0, 19),
        );
    }

    public function test_permit_mati_saat_palang_mencatat_keluar(): void
    {
        $nomor = $this->buatPermitCoklat(now());

        // Palang menulis HANYA ke tabel efektif — persis seperti aslinya.
        Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);
        Artisan::call('visitor:simulasi-palang', ['--keluar' => $nomor]);

        Artisan::call('visitor:expire-efektif');

        $baris = $this->permitTamu($nomor);

        $this->assertSame('EXPIRED', $baris->Status_Efektif);
        $this->assertSame('Tamu tercatat keluar', $baris->Alasan_Status);
        $this->assertSame(
            StatusKunjunganTamu::SCHEDULED,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan'),
            'Prasyarat berubah: palang kini menyentuh Status_Kunjungan, uji ini perlu ditinjau.',
        );
    }

    public function test_permit_mati_seketika_lewat_tombol_check_out_manual(): void
    {
        $nomor = $this->buatPermitCoklat(now());
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Id_Transaksi_Tamu');

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update(['Waktu_Aktual_Masuk' => now()->subMinutes(30)]);

        $request = Request::create('/tamu/'.$id.'/checkout', 'POST');
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);
        app(GuestRegistrationController::class)->checkOut($request, (string) $id);

        // Tanpa menunggu sapuan: jalur manual adalah pintu kedua yang setara.
        $this->assertSame('EXPIRED', $this->permitTamu($nomor)->Status_Efektif);
        // DDL-2026-121: checkOut() tidak lagi menulis Status_Kunjungan
        // (dulu CHECKED_OUT); CHECKED_OUT kini status tampil turunan jam.
        $this->assertSame(
            StatusKunjunganTamu::SCHEDULED,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan'),
        );
    }

    /**
     * Izin BERHARI-HARI tidak mati hanya karena tamunya pulang malam pertama.
     *
     * Sejak verifikator boleh menetapkan jendela berapa hari pun, "tamu tercatat
     * keluar" berhenti berarti "kunjungannya selesai". Tamu proyek yang pulang
     * pukul lima sore hari pertama akan kehilangan izinnya untuk sisa harinya —
     * dan ia baru mengetahuinya besok pagi di gerbang, tanpa satu pun galat.
     */
    public function test_izin_berhari_hari_bertahan_saat_tamu_pulang_hari_pertama(): void
    {
        $nomor = $this->buatPermitCoklat(now());
        $this->lebarkanJendela($nomor, 4);

        Artisan::call('visitor:simulasi-palang', ['--masuk' => $nomor]);
        Artisan::call('visitor:simulasi-palang', ['--keluar' => $nomor]);

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            'AKTIF',
            $this->permitTamu($nomor)->Status_Efektif,
            'Izin berhari-hari mati di hari pertama; tamunya akan ditolak besok pagi.',
        );
    }

    public function test_tombol_check_out_manual_juga_tidak_mengakhiri_izin_berhari_hari(): void
    {
        // Pintu kedua yang setara. Aturan yang hanya terpasang di sapuan
        // terjadwal akan dilewati sepenuhnya oleh tombol ini.
        $nomor = $this->buatPermitCoklat(now());
        $this->lebarkanJendela($nomor, 4);
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Id_Transaksi_Tamu');

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->update(['Waktu_Aktual_Masuk' => now()->subMinutes(30)]);

        $request = Request::create('/tamu/'.$id.'/checkout', 'POST');
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);
        app(GuestRegistrationController::class)->checkOut($request, (string) $id);

        $this->assertSame('AKTIF', $this->permitTamu($nomor)->Status_Efektif);
    }

    /**
     * Lebarkan jendela permit coklat menjadi $hari hari, seperti yang dilakukan
     * verifikator saat menyetujui.
     *
     * Transaksi DAN proyeksinya sama-sama ditulis: sapuan kedaluwarsa membaca
     * proyeksi, sementara sinkronisasi berikutnya membaca transaksi. Menulis
     * salah satunya saja membuat uji ini hijau karena alasan yang keliru.
     */
    private function lebarkanJendela(string $nomorTamu, int $hari): void
    {
        $noPermit = $this->nomorPermitCoklat($nomorTamu);
        $mulai = now()->startOfDay();
        $akhir = $mulai->copy()->addDays($hari - 1)->setTime(17, 0);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noPermit)
            ->update([
                'Waktu_Mulai_Berlaku' => $mulai,
                'Waktu_Berakhir' => $akhir,
                'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
                'Tanggal_Kadaluarsa' => $akhir->toDateString(),
                'Snapshot_Nama_Durasi' => $hari.' Hari',
            ]);

        DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noPermit)
            ->update([
                'Waktu_Mulai_Berlaku' => $mulai,
                'Waktu_Berakhir' => $akhir,
                'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
                'Tanggal_Kadaluarsa' => $akhir->toDateString(),
                'Nama_Durasi' => $hari.' Hari',
            ]);
    }

    public function test_pengakhiran_bukan_pembatalan(): void
    {
        // EXPIRED karena tamunya pulang bukan berarti ada yang membatalkan.
        // Flag_Batal dan kolom Dibatalkan_* harus tetap bersih, kalau tidak
        // laporan pencabutan akan menghitung kunjungan yang berjalan normal.
        $nomor = $this->buatPermitCoklat(now());

        app(VisitorEfektifService::class)->akhiriPermitCoklat($nomor, 'Tamu tercatat keluar');

        $baris = $this->permitTamu($nomor);

        $this->assertSame('EXPIRED', $baris->Status_Efektif);
        $this->assertSame('T', $baris->Flag_Batal);
        $this->assertNull($baris->Dibatalkan_At);
        $this->assertNull($baris->Dibatalkan_Oleh_Nama);
    }

    public function test_tamu_lintas_hari_juga_tidak_mati_kepagian(): void
    {
        // Cabang TAMU (bukan permit) memakai akhir jendela kunjungan, bukan
        // Tanggal_Kunjungan. Kunjungan yang menyeberang tengah malam tetap
        // terbaca aktif di gerbang sampai jam selesainya.
        $nomor = $this->buatPermitCoklat(
            now()->subDay(),
            now()->subDay()->setTime(20, 0),
            now()->addDay()->setTime(6, 0),
        );

        Artisan::call('visitor:expire-efektif');

        $this->assertSame(
            'AKTIF',
            DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Status_Efektif'),
        );
    }

    public function test_permit_karyawan_yang_masih_berlaku_tidak_terpengaruh(): void
    {
        $nomor = $this->buatPermitKaryawan('KARYAWAN', now()->addMonths(2));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('AKTIF', $this->permit($nomor)->Status_Efektif);
    }

    public function test_permit_karyawan_tanpa_sumber_permit_tetap_kedaluwarsa(): void
    {
        // Sumber_Permit nullable. Penyaring `<> 'TAMU'` telanjang menghasilkan
        // UNKNOWN untuk NULL, sehingga baris yang belum ter-backfill diam-diam
        // berhenti dikedaluwarsakan — tanpa satu pun galat.
        $nomor = $this->buatPermitKaryawan(null, now()->subDays(2));

        Artisan::call('visitor:expire-efektif');

        $this->assertSame('EXPIRED', $this->permit($nomor)->Status_Efektif);
    }

    private function permit(string $nomor): object
    {
        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertNotNull($baris, "Permit {$nomor} tidak ada.");

        return $baris;
    }

    /**
     * Baris gerbang milik permit coklat sebuah KUNJUNGAN.
     *
     * Nomornya diterjemahkan lewat tautan permit↔tamu: permit coklat punya
     * transaksi sendiri, sehingga mencari proyeksi dengan nomor kunjungan selalu
     * menghasilkan nol baris.
     */
    private function permitTamu(string $nomorTamu): object
    {
        $noPermit = $this->nomorPermitCoklat($nomorTamu);
        $this->assertNotNull($noPermit, "Permit coklat untuk {$nomorTamu} tidak pernah diajukan.");

        return $this->permit($noPermit);
    }

    private function buatPermitKaryawan(?string $sumber, Carbon $kadaluarsa): string
    {
        $nomor = 'PMT-EXP-'.Str::upper(Str::random(8));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Nama_Pemohon' => 'Pemohon Uji',
            'Status_Efektif' => 'AKTIF',
            'Sumber_Permit' => $sumber,
            'Tanggal_Mulai_Berlaku' => now()->subMonths(6)->toDateString(),
            'Tanggal_Kadaluarsa' => $kadaluarsa->toDateString(),
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now()->subMonths(6),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now()->subMonths(6),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);

        return $nomor;
    }

    /**
     * Satu kunjungan B2B lengkap dengan permit coklatnya yang SUDAH disetujui.
     *
     * Jam kunjungan = Estimasi Masuk/Keluar; bila diisi, masa berlaku permitnya
     * mengikutinya (DDL-2026-121). Tanpa jam, satu hari dari $tanggalKunjungan.
     */
    private function buatPermitCoklat(Carbon $tanggalKunjungan, ?Carbon $mulaiJam = null, ?Carbon $selesaiJam = null): string
    {
        $nomor = 'VSR-EXP-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => $tanggalKunjungan->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'EXP-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Expire',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil',
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'EX',
            'Waktu_Mulai_Kunjungan' => $mulaiJam?->format('Y-m-d H:i:s'),
            'Waktu_Selesai_Kunjungan' => $selesaiJam?->format('Y-m-d H:i:s'),
        ]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        $this->terbitkanPermitCoklat($nomor, self::PIC);

        return $nomor;
    }
}
