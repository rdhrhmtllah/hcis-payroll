<?php

namespace Tests\Integration\VISITOR\Workflow\Api;

use App\Http\Controllers\VISITOR\Guest\Visit\TamuB2BApiController;
use App\Http\Controllers\VISITOR\Permit\VehiclePermit\PermitTamuApiController;
use App\Http\Requests\VISITOR\Guest\Visit\AjukanTamuB2BApiRequest;
use App\Http\Requests\VISITOR\Permit\VehiclePermit\AjukanPermitApiRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mitra tidak pernah boleh menentukan siapa pemilik barisnya.
 *
 * Kepemilikan kunjungan dan permit diturunkan SERVER dari aplikasi yang
 * terautentikasi (`N_HRIS_Api_Aplikasi.Kode_Karyawan_PIC`), bukan dari payload.
 * Kalau payload bisa menentukannya, sebuah mitra dapat menerbitkan izin akses
 * fisik atas nama karyawan mana pun — dan jejak yang tertinggal di gerbang
 * menunjuk orang yang tidak pernah terlibat.
 *
 * Membaca kode hanya membuktikan tidak ada jalur yang TERLIHAT. Penjaga ini
 * menyerang endpointnya dengan payload yang memuat setiap nama field
 * kepemilikan yang masuk akal, lalu memeriksa baris yang benar-benar tersimpan.
 * Itu juga menutup celah yang paling mudah muncul kelak: seseorang mengganti
 * penyusunan array eksplisit menjadi `$request->all()` atau `validated()`, dan
 * tidak ada satu pun uji yang menyadarinya.
 */
class MitraTidakMenentukanKepemilikanTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{id:int, pic:string, korban:string} */
    private function mitra(): array
    {
        $app = DB::table('N_HRIS_Api_Aplikasi')
            ->whereNotNull('Kode_Karyawan_PIC')
            ->first(['Id_Api_Aplikasi', 'Kode_Karyawan_PIC']);

        $this->assertNotNull($app, 'Tidak ada aplikasi mitra ber-PIC; fixture perlu ditinjau.');

        $korban = DB::table('Karyawan')
            ->where('Kode_Karyawan', '<>', $app->Kode_Karyawan_PIC)
            ->value('Kode_Karyawan');

        $this->assertNotNull($korban, 'Butuh satu karyawan lain sebagai sasaran penyuntikan.');

        return [
            'id' => (int) $app->Id_Api_Aplikasi,
            'pic' => (string) $app->Kode_Karyawan_PIC,
            'korban' => (string) $korban,
        ];
    }

    /**
     * Setiap nama field kepemilikan yang masuk akal, dalam kedua penulisan.
     *
     * @return array<string, mixed>
     */
    private function suntikan(string $korban): array
    {
        return [
            'kode_karyawan_pic' => $korban,
            'Kode_Karyawan_PIC' => $korban,
            'created_by' => $korban,
            'Created_By' => $korban,
            'kode_karyawan_pemohon' => $korban,
            'Kode_Karyawan_Pemohon' => $korban,
            'id_api_aplikasi' => 999999,
            'Id_Api_Aplikasi' => 999999,
            'sumber_pengajuan' => 'HCIS',
            'Sumber_Pengajuan' => 'HCIS',
            'flag_approval' => 'Y',
            'Flag_Approval' => 'Y',
        ];
    }

    public function test_payload_mitra_tidak_bisa_menentukan_pemilik_kunjungan(): void
    {
        ['id' => $idAplikasi, 'pic' => $pic, 'korban' => $korban] = $this->mitra();

        $request = AjukanTamuB2BApiRequest::create(
            '/api/v1/hcis/visitor/tamu-b2b',
            'POST',
            array_merge([
                'nama_tamu' => 'Tamu Uji Suntik',
                'instansi' => 'PT Uji',
                'keperluan' => 'Uji penyuntikan kepemilikan',
                'tanggal_kunjungan' => now()->addDay()->toDateString(),
                'pic_tujuan' => 'Bagian SHE',
                // Wajib sejak DDL-2026-135. Justru memperkuat uji ini: mitra
                // menyebut NAMA penanggung jawab, tetapi tetap tidak bisa
                // menentukan siapa PEMILIK kunjungannya di HCIS.
                'penanggung_jawab' => 'Penanggung Jawab Mitra',
                'daftar_anggota' => [],
                'referensi_mitra' => 'SUNTIK-'.strtoupper(bin2hex(random_bytes(4))),
            ], $this->suntikan($korban)),
        );

        /* Penjangkar approval hanya bisa diuji kalau approval kunjungannya
           memang dibuat. Bawaan B2B sejak 25 Sep 2026 tidak membuatnya, jadi
           setelannya dihidupkan di sini — subjek penjaga ini adalah "payload
           mitra tidak menentukan penjangkar", bukan "kunjungan wajib
           di-approve". */
        config(['services.visitor.tamu_b2b.needs_approval' => true]);

        $request->headers->set('Idempotency-Key', 'SUNTIK-'.strtoupper(bin2hex(random_bytes(8))));
        $request->attributes->set('hris_api_app_id', $idAplikasi);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        $isi = json_decode(app(TamuB2BApiController::class)->ajukan($request)->getContent(), true);

        $this->assertTrue($isi['sukses'] ?? false, 'Pengajuan gagal: '.json_encode($isi));

        $no = $isi['result']['no_transaksi'] ?? null;

        // Berhenti KERAS bila nomornya tak terbaca: tanpa ini, pemeriksaan di
        // bawah membandingkan baris kosong dan melaporkan kebocoran di SETIAP
        // kolom — penjaga rusak yang menyamar jadi celah keamanan.
        $this->assertNotNull($no, 'Nomor transaksi tidak terbaca; penjaga ini berhenti menguji apa pun.');

        $tamu = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->first();
        $this->assertNotNull($tamu, 'Baris tamu tidak ditemukan padahal pengajuan sukses.');

        // Sejak DDL-2026-135 kunjungan mitra TIDAK BERTUAN. Yang dijaga di sini
        // bukan "PIC-nya si A" melainkan "payload mitra tidak menentukan
        // apa pun" - jadi yang ditagih: kolomnya kosong, dan korban suntikan
        // tidak muncul di mana pun.
        $this->assertNull($tamu->Kode_Karyawan_PIC, 'Mitra berhasil menentukan PIC kunjungan.');
        $this->assertStringNotContainsString($korban, (string) $tamu->Kode_Karyawan_PIC, 'Korban suntikan menjadi PIC.');
        $this->assertStringNotContainsString($korban, (string) $tamu->Created_By, 'Korban suntikan menjadi pembuat.');

        // Penjangkar approval tetap dari registry, bukan dari payload.
        $this->assertSame(
            $pic,
            (string) DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->value('Kode_Karyawan'),
            'Mitra berhasil menentukan penjangkar alur persetujuan.',
        );
        $this->assertSame($idAplikasi, (int) $tamu->Id_Api_Aplikasi, 'Mitra berhasil mengaku sebagai aplikasi lain.');
        $this->assertSame('API', $tamu->Sumber_Pengajuan, 'Mitra berhasil menyamarkan sumber pengajuannya.');
        $this->assertNotSame($korban, $tamu->Kode_Karyawan_PIC);

        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->whereNotNull('No_Transaksi_Tamu')
            ->where('No_Transaksi_Tamu', $no)
            ->first();

        if ($permit === null) {
            return;
        }

        $this->assertSame($pic, $permit->Kode_Karyawan_Pemohon, 'Mitra berhasil menentukan pemohon permit turunannya.');
        $this->assertSame($pic, $permit->Created_By);
        $this->assertSame($idAplikasi, (int) $permit->Id_Api_Aplikasi);
        $this->assertSame('API', $permit->Sumber_Pengajuan);

        // Permit COKLAT WAJIB lahir menunggu verifikator. Payload yang bisa
        // menerbitkannya langsung berarti izin akses fisik tanpa seorang pun
        // pernah melihat kendaraannya.
        $this->assertNull($permit->Flag_Approval, 'Mitra berhasil menyetujui permitnya sendiri.');
    }

    public function test_payload_mitra_tidak_bisa_menentukan_pemohon_permit(): void
    {
        ['id' => $idAplikasi, 'pic' => $pic, 'korban' => $korban] = $this->mitra();

        $request = AjukanPermitApiRequest::create(
            '/api/v1/hcis/visitor/permit-tamu',
            'POST',
            array_merge([
                'nama_tamu' => 'Tamu Permit Suntik',
                'instansi' => 'PT Uji',
                'keperluan' => 'Uji penyuntikan kepemilikan permit',
                'no_plat_kendaraan' => 'BG 1234 MT',
                'jenis_kendaraan' => 'Mobil',
                'referensi_mitra' => 'SUNTIK-'.strtoupper(bin2hex(random_bytes(4))),
            ], $this->suntikan($korban)),
        );

        $request->headers->set('Idempotency-Key', 'SUNTIKP-'.strtoupper(bin2hex(random_bytes(8))));
        $request->attributes->set('hris_api_app_id', $idAplikasi);
        $request->setContainer(app())->setRedirector(app('redirect'));
        $request->validateResolved();

        $isi = json_decode(app(PermitTamuApiController::class)->ajukan($request)->getContent(), true);

        $this->assertTrue($isi['sukses'] ?? false, 'Pengajuan permit gagal: '.json_encode($isi));

        $no = $isi['result']['no_transaksi'] ?? null;
        $this->assertNotNull($no, 'Nomor permit tidak terbaca; penjaga ini berhenti menguji apa pun.');

        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $no)->first();
        $this->assertNotNull($permit);

        $this->assertSame($pic, $permit->Kode_Karyawan_Pemohon, 'Mitra berhasil menentukan pemohon permitnya.');
        $this->assertSame($pic, $permit->Created_By);
        $this->assertSame($idAplikasi, (int) $permit->Id_Api_Aplikasi);
        $this->assertSame('API', $permit->Sumber_Pengajuan);
        $this->assertNull($permit->Flag_Approval, 'Mitra berhasil menyetujui permitnya sendiri.');
    }

    /**
     * Sisi PENERIMAAN: penyetujuan mengubah jejak KEPUTUSAN, bukan kepemilikan.
     *
     * Tanpa ini, implementasi yang menimpa Kode_Karyawan_Pemohon dengan kode
     * penyetuju tetap menghijaukan kedua uji di atas — keduanya hanya melihat
     * baris yang belum disetujui.
     */
    public function test_penyetujuan_tidak_memindahkan_kepemilikan(): void
    {
        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Sumber_Pengajuan', 'API')
            ->where('Flag_Approval', 'Y')
            ->whereNotNull('Approved_By')
            ->first(['No_Transaksi', 'Kode_Karyawan_Pemohon', 'Created_By', 'Approved_By']);

        if ($baris === null) {
            $this->markTestSkipped('Belum ada permit mitra yang disetujui untuk diperiksa.');
        }

        $this->assertNotSame(
            $baris->Approved_By,
            $baris->Kode_Karyawan_Pemohon,
            "Permit {$baris->No_Transaksi}: pemohonnya berubah menjadi penyetujunya.",
        );
        $this->assertNotSame($baris->Approved_By, $baris->Created_By);
    }
}
