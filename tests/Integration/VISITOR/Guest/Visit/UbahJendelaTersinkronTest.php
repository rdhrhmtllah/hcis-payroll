<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\TestCase;

/**
 * Mengubah jendela kunjungan harus sampai ke gerbang.
 *
 * update() sebelumnya mengubah Tanggal_Kunjungan tanpa menyentuh proyeksi sama
 * sekali: aplikasi palang terus membaca nilai lama tanpa satu pun galat.
 * Dengan permit coklat, nilai basi itu bukan lagi sekadar tampilan yang keliru
 * — ia adalah masa berlaku akses fisik yang salah.
 *
 * Sejak DDL-2026-121 jendela bawaan permit COKLAT = rentang Estimasi
 * Masuk–Keluar kunjungannya; satu hari kalender dari Tanggal_Kunjungan hanya
 * cadangan bagi baris LAMA tanpa estimasi. Tiga uji pertama memakai baris lama
 * itu (estimasi NULL) supaya yang dijaga tetap jalur tanggal; uji terakhir
 * menjaga jalur estimasi.
 */
class UbahJendelaTersinkronTest extends TestCase
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

    public function test_mengubah_tanggal_memperbarui_permit_satu_hari(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['tanggal_kunjungan' => '2026-09-12']);

        $permit = DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $this->nomorPermitCoklat($nomor))
            ->first();

        $this->assertNotNull($permit, 'Permit coklat hilang setelah tanggalnya diubah.');
        $this->assertSame('2026-09-12 00:00:00', substr((string) $permit->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-13 00:00:00', substr((string) $permit->Waktu_Berakhir, 0, 19));
        $this->assertSame('2026-09-12', substr((string) $permit->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-12', substr((string) $permit->Tanggal_Kadaluarsa, 0, 10));
        $this->assertSame('T', $permit->Flag_Sync, 'Perubahan tidak ditandai untuk ditarik ulang gerbang.');
    }

    public function test_mengubah_tanggal_juga_memperbarui_proyeksi_tamu(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();

        $this->ubah($id, ['tanggal_kunjungan' => '2026-09-12']);

        $tamu = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertSame('2026-09-12', substr((string) $tamu->Tanggal_Kunjungan, 0, 10));
    }

    /**
     * Jendela yang DIPILIH verifikator tidak boleh tersapu penyuntingan tamu.
     *
     * Kebalikan dari dua uji di atas, dan keduanya harus benar bersamaan.
     * Sinkronisasi yang rajin akan memundurkan izin tiga hari yang sudah terbit
     * kembali ke bawaan sehari begitu ada yang menyentuh data kunjungannya —
     * tamunya ditahan di gerbang pada hari kedua, dan tidak ada galat yang
     * menjelaskan sebabnya.
     */
    public function test_jendela_pilihan_verifikator_bertahan_saat_tamu_disunting(): void
    {
        [$nomor, $id] = $this->buatTamuEfektif();
        $noPermit = $this->nomorPermitCoklat($nomor);

        // Bentuk yang TIDAK mungkin lahir dari turunan tanggal kunjungan:
        // bukan tengah malam, dan lebih dari sehari.
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noPermit)
            ->update([
                'Waktu_Mulai_Berlaku' => '2026-09-10 08:00:00',
                'Waktu_Berakhir' => '2026-09-13 17:00:00',
                'Tanggal_Mulai_Berlaku' => '2026-09-10',
                'Tanggal_Kadaluarsa' => '2026-09-13',
                'Snapshot_Nama_Durasi' => '4 Hari',
            ]);

        $this->ubah($id, ['tanggal_kunjungan' => '2026-09-12']);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noPermit)
            ->first();

        $this->assertSame('2026-09-10 08:00:00', substr((string) $detail->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-13 17:00:00', substr((string) $detail->Waktu_Berakhir, 0, 19));
        $this->assertSame('4 Hari', $detail->Snapshot_Nama_Durasi);
    }

    /**
     * DDL-2026-121: permit COKLAT mengikuti rentang Estimasi Masuk–Keluar, bukan
     * satu hari kalender — MITRA berhari-hari tidak boleh ditolak gerbang di
     * hari kedua. Label durasi & tanggal kedaluwarsa (inklusif) ikut rentangnya.
     */
    public function test_permit_mengikuti_rentang_estimasi_kunjungan(): void
    {
        [$nomor] = $this->buatTamuEfektif('2026-09-10 08:00:00', '2026-09-12 17:00:00');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $this->nomorPermitCoklat($nomor))
            ->first();

        $this->assertNotNull($detail, 'Permit coklat tidak terbit untuk kunjungan berestimasi.');
        $this->assertSame('2026-09-10 08:00:00', substr((string) $detail->Waktu_Mulai_Berlaku, 0, 19));
        // Sejak 29 Sep 2026 usulannya berakhir 23:59 di hari terakhir kunjungan.
        $this->assertSame('2026-09-12 23:59:00', substr((string) $detail->Waktu_Berakhir, 0, 19));
        $this->assertSame('2026-09-10', substr((string) $detail->Tanggal_Mulai_Berlaku, 0, 10));
        $this->assertSame('2026-09-12', substr((string) $detail->Tanggal_Kadaluarsa, 0, 10));
        $this->assertSame('3 Hari', $detail->Snapshot_Nama_Durasi);
    }

    public function test_permit_estimasi_sehari_berlabel_satu_hari(): void
    {
        // Rentang 08:00–17:00 sehari: label bawaan '1 Hari', berlaku sampai
        // 23:59 hari itu (usulan sejak 29 Sep 2026), kedaluwarsa tanggal yang sama.
        [$nomor] = $this->buatTamuEfektif('2026-09-10 08:00:00', '2026-09-10 17:00:00');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $this->nomorPermitCoklat($nomor))
            ->first();

        $this->assertSame('2026-09-10 08:00:00', substr((string) $detail->Waktu_Mulai_Berlaku, 0, 19));
        $this->assertSame('2026-09-10 23:59:00', substr((string) $detail->Waktu_Berakhir, 0, 19));
        $this->assertSame('2026-09-10', substr((string) $detail->Tanggal_Kadaluarsa, 0, 10));
        $this->assertSame(PermitCoklatTamuService::NAMA_DURASI, $detail->Snapshot_Nama_Durasi);
    }

    /**
     * DDL-2026-126: palang hanya membaca N_VISITOR_Tamu_Efektif, jadi rentang
     * estimasi, kategori, unit B2B, dan foto kendaraan wajib ikut terproyeksi —
     * dan ikut berubah saat kunjungannya disinkron ulang.
     */
    public function test_proyeksi_membawa_estimasi_kategori_unit_dan_foto(): void
    {
        [$nomor] = $this->buatTamuEfektif('2026-09-10 08:00:00', '2026-09-12 17:00:00');

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->update([
            'Tamu_Kategori' => 'MITRA',
            'B2B_Unit' => 'LOGISTIC',
            'Path_Foto_Kendaraan' => 'visitor/tamu/'.$nomor.'/foto-kendaraan-uji.jpg',
        ]);
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $efektif = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();

        // Permitnya sudah disetujui, jadi jendela akses tamu = jendela permit
        // (VisitorEfektifService::jendelaAksesTamu), berakhir 23:59.
        $this->assertSame('2026-09-10 08:00:00', substr((string) $efektif->Waktu_Estimasi_Masuk, 0, 19));
        $this->assertSame('2026-09-12 23:59:00', substr((string) $efektif->Waktu_Estimasi_Keluar, 0, 19));
        $this->assertSame('MITRA', $efektif->Tamu_Kategori);
        $this->assertSame('LOGISTIC', $efektif->B2B_Unit);
        $this->assertSame('visitor/tamu/'.$nomor.'/foto-kendaraan-uji.jpg', $efektif->Path_Foto_Kendaraan);

        // Data kunjungan yang berubah ikut terproyeksi, TETAPI jendela aksesnya
        // tetap milik permit yang sudah disetujui admin — estimasi tamu yang
        // dipendekkan tidak mencabut izin yang sudah ditetapkan verifikator.
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->update([
            'Waktu_Selesai_Kunjungan' => '2026-09-10 17:00:00',
            'Tamu_Kategori' => 'VISITOR',
        ]);
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $efektif = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();
        $this->assertSame('2026-09-12 23:59:00', substr((string) $efektif->Waktu_Estimasi_Keluar, 0, 19));
        $this->assertSame('VISITOR', $efektif->Tamu_Kategori);
    }

    /** @param array<string,mixed> $payload */
    private function ubah(int $id, array $payload): void
    {
        $request = Request::create('/tamu/'.$id, 'PUT', $payload);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        app(GuestRegistrationController::class)->update($request, (string) $id);
    }

    /** @return array{0:string,1:int} */
    private function buatTamuEfektif(?string $estimasiMasuk = null, ?string $estimasiKeluar = null): array
    {
        $nomor = 'VSR-UBH-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-10',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'UBH-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Ubah',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil',
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'UB',
            // NULL = baris lama tanpa estimasi (lihat docblock kelas).
            'Waktu_Mulai_Kunjungan' => $estimasiMasuk,
            'Waktu_Selesai_Kunjungan' => $estimasiKeluar,
        ]);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        $this->terbitkanPermitCoklat($nomor, self::PIC);

        return [$nomor, (int) $id];
    }
}
