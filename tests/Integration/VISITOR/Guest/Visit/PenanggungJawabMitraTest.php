<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Services\VISITOR\Guest\Visit\RegistrasiTamuService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kunjungan mitra tidak punya karyawan pendaftar — dan berhenti berpura-pura.
 *
 * Sebelum DDL-2026-135, Kode_Karyawan_PIC diisi dari registry aplikasi,
 * sehingga SELURUH kunjungan mitra tercatat atas nama satu orang yang tidak
 * pernah mengajukannya. Dari layar mana pun ia terlihat seperti orang itu yang
 * mendaftarkan ratusan tamu.
 *
 * Empat peran yang selama ini menyamar jadi satu kolom kini terpisah:
 *
 *  - Kode_Karyawan_PIC     : PEMILIK. Kosong pada kunjungan mitra.
 *  - Created_By            : JEJAK AUDIT yang dibaca manusia, mis.
 *                            "Budi Hartono (API#233)" — namanya untuk dicari,
 *                            penandanya supaya tak dikira karyawan bernama sama.
 *  - penjangkar approval   : dari siapa rantai persetujuan disusun. Tidak
 *                            disimpan di header; hanya diteruskan ke
 *                            createFlow(), yang membangun rantai dari hierarki
 *                            seseorang — tanpa itu kunjungannya macet.
 *  - Nama_Penanggung_Jawab : NAMA yang dihubungi petugas gerbang. Wajib
 *                            dikirim mitra.
 *
 * Yang membuat kekosongan itu aman adalah Nama_PIC di proyeksi gerbang: ia
 * TETAP NOT NULL, dan diisi dari karyawan bila ada kodenya atau dari nama
 * penanggung jawab bila tidak. Gerbang butuh nama, bukan kode.
 */
class PenanggungJawabMitraTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kunjungan_mitra_tidak_bertuan_tetapi_punya_nama_penanggung_jawab(): void
    {
        $nomor = $this->daftarLewatMitra('Budi Hartono');

        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->first();
        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();

        $this->assertNull($header->Kode_Karyawan_PIC, 'Kunjungan mitra masih dicatat atas nama karyawan yang tak pernah mengajukannya.');
        $this->assertSame('Budi Hartono', $detail->Nama_Penanggung_Jawab);

        // Jejak audit memuat NAMA dan penanda aplikasinya sekaligus. Namanya
        // yang dicari orang saat membuka barisnya; penandanya menahan siapa pun
        // dari mengira seorang karyawan bernama sama yang mengetiknya.
        $this->assertStringContainsString('Budi Hartono', (string) $header->Created_By);
        $this->assertStringContainsString('API#', (string) $header->Created_By);

        // Kode karyawan TIDAK boleh ada di sini - itu yang dulu membuat
        // kunjungan mitra terbaca "dibuat oleh" orang yang tak pernah
        // menyentuhnya.
        $this->assertStringNotContainsString(
            (string) DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan'),
            (string) $header->Created_By,
        );
    }

    public function test_rantai_approval_tetap_terbentuk_walau_tanpa_pemilik(): void
    {
        /* Bawaan B2B tidak lagi meminta approval KUNJUNGAN (25 Sep 2026), jadi
           setelannya dihidupkan di sini dengan sengaja: yang diuji bukan ADA
           atau tidaknya approval, melainkan dari SIAPA rantainya disusun ketika
           approval itu memang menyala. Membiarkannya mengikuti bawaan membuat
           penjaga ini berhenti menguji apa pun — nol baris approval, hijau. */
        config(['services.visitor.tamu_b2b.needs_approval' => true]);

        // Inti biayanya. createFlow() menyusun rantai dari hierarki seseorang;
        // tanpa penjangkar, kunjungan mitra tidak akan pernah bisa disetujui.
        $nomor = $this->daftarLewatMitra('Budi Hartono');

        $this->assertGreaterThan(
            0,
            DB::table('HRIS_Approval_Request')->where('No_Transaksi', $nomor)->count(),
            'Kunjungan mitra lahir tanpa rantai approval; ia akan menggantung selamanya.',
        );
    }

    public function test_nama_pic_di_layar_memakai_nama_penanggung_jawab(): void
    {
        $nomor = $this->daftarLewatMitra('Siti Rahmawati');

        $baris = app(VisitorPermitQueryService::class)->baseTamuQuery()
            ->where('h.No_Transaksi', $nomor)
            ->first();

        $this->assertSame('Siti Rahmawati', $baris->Nama_PIC);
    }

    public function test_proyeksi_gerbang_terbit_dengan_pic_kosong(): void
    {
        // Kolom Nama_PIC di proyeksi NOT NULL. Kalau ia hanya bergantung pada
        // JOIN ke Karyawan, kunjungan mitra akan gagal terbit ke gerbang —
        // kegagalan yang baru muncul jauh setelah pengajuan diterima.
        $nomor = $this->daftarLewatMitra('Siti Rahmawati');
        $this->setujui($nomor);

        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);

        $efektif = DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertNotNull($efektif, 'Kunjungan mitra tidak pernah sampai ke gerbang.');
        $this->assertNull($efektif->Kode_Karyawan_PIC);
        $this->assertSame('Siti Rahmawati', $efektif->Nama_PIC);
    }

    public function test_kunjungan_layar_hcis_tetap_bertuan(): void
    {
        // Sisi sebaliknya: pintu internal TIDAK boleh ikut kehilangan pemilik.
        $pic = (string) DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');
        $nomor = $this->daftar(['kode_karyawan_pic' => $pic]);

        $this->assertSame(
            $pic,
            (string) DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Kode_Karyawan_PIC'),
        );
    }

    // ---------------------------------------------------------------- helpers

    private function daftarLewatMitra(string $penanggungJawab): string
    {
        return $this->daftar([
            'kode_karyawan_pic' => null,
            'kode_karyawan_approval' => (string) DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan'),
            'dibuat_oleh' => $penanggungJawab.' (API#233)',
            'nama_penanggung_jawab' => $penanggungJawab,
            'tipe_tamu' => 'B2B',
            'b2b_unit' => 'PROCUREMENT',
            'id_api_aplikasi' => (int) DB::table('N_HRIS_Api_Aplikasi')->where('Nama_Aplikasi', 'B2B')->value('Id_Api_Aplikasi'),
        ]);
    }

    /** @param array<string, mixed> $ubah */
    private function daftar(array $ubah): string
    {
        $hasil = app(RegistrasiTamuService::class)->simpan($ubah + [
            'idempotency_key' => 'UJI-PJ-'.Str::upper(Str::random(16)),
            'nama_tamu' => 'Tamu Uji PJ',
            'instansi_asal' => 'PT Uji',
            'keperluan' => 'Kirim barang',
            'waktu_mulai_kunjungan' => now()->addDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => now()->addDays(3)->setTime(11, 0)->format('Y-m-d H:i:s'),
            'jenis_kendaraan' => 'Truk',
            'no_plat_kendaraan' => 'BG 1111 UJ',
            'pic_tujuan' => 'Warehouse',
        ]);

        return $hasil['no_transaksi'];
    }

    private function setujui(string $nomor): void
    {
        DB::table('HRIS_Approval_Request')->where('No_Transaksi', $nomor)->whereNull('Flag_Approval')
            ->update(['Flag_Approval' => 'Y', 'Tanggal_Approval' => now(), 'update_at' => now()]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)
            ->update(['Flag_Approval' => 'Y', 'Status_Kunjungan' => 'SCHEDULED']);
    }
}
