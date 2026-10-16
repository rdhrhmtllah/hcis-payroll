<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Guest\Visit\DaftarAnggotaRombongan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\VISITOR\PermintaanRegistrasiTamu;
use Tests\TestCase;

/**
 * Registrasi tamu mencatat siapa saja yang datang.
 *
 * Jumlah_Tamu wajib diturunkan dari daftar, bukan dari angka terpisah. Kalau
 * keduanya boleh berbeda, layar internal dan aplikasi gerbang membaca angka
 * yang tidak sesuai daftar nama tanpa satu pun galat.
 */
class DaftarAnggotaTamuTest extends TestCase
{
    use DatabaseTransactions;
    use PermintaanRegistrasiTamu;

    /** Kode karyawan nyata di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_daftar_tersimpan_lengkap_tanpa_menggandakan_tamu_utama(): void
    {
        $nomor = $this->daftarkan([
            'nama_tamu' => 'Rudi Hartono',
            'daftar_anggota' => [
                ['nama' => 'Siti Aminah', 'jenis_identitas' => 'KTP', 'no_identitas' => '3201234567890001'],
                ['nama' => 'Budi'],
            ],
        ]);

        $json = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->value('Daftar_Anggota_Json');
        $daftar = app(DaftarAnggotaRombongan::class)->dariJson($json);

        $this->assertCount(2, $daftar);
        $this->assertSame('Siti Aminah', $daftar[0]['nama']);
        $this->assertSame('KTP', $daftar[0]['jenis_identitas']);
        $this->assertSame('Budi', $daftar[1]['nama']);
        $this->assertArrayNotHasKey('no_identitas', $daftar[1]);
    }

    public function test_jumlah_tamu_diturunkan_dari_daftar_bukan_dari_isian(): void
    {
        $nomor = $this->daftarkan([
            'jumlah_tamu' => 99,
            'daftar_anggota' => [['nama' => 'Siti'], ['nama' => 'Budi']],
        ]);

        $this->assertSame(
            3,
            (int) DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
                ->where('No_Transaksi', $nomor)
                ->value('Jumlah_Tamu'),
        );
    }

    public function test_tamu_tunggal_menghasilkan_daftar_kosong_dengan_jumlah_satu(): void
    {
        $nomor = $this->daftarkan([
            'nama_tamu' => 'Rudi Hartono',
            'daftar_anggota' => [],
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->first();
        $daftar = app(DaftarAnggotaRombongan::class)->dariJson($detail->Daftar_Anggota_Json);

        $this->assertCount(0, $daftar);
        $this->assertSame(1, (int) $detail->Jumlah_Tamu);
    }

    public function test_anggota_bernama_kosong_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan(['daftar_anggota' => [['nama' => '   ']]]);
    }

    public function test_melebihi_batas_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan([
            'daftar_anggota' => array_fill(0, DaftarAnggotaRombongan::MAKS_ANGGOTA_ROMBONGAN, ['nama' => 'Orang']),
        ]);
    }

    public function test_daftar_bukan_array_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan(['daftar_anggota' => 'Siti, Budi']);
    }

    public function test_daftar_yang_tidak_dikirim_dibaca_tamu_tunggal(): void
    {
        // DDL-2026-121: 'daftar_anggota' kini nullable, bukan present —
        // pengajuan B2B berfoto dikirim multipart dan FormData tidak bisa
        // membawa array kosong. Ketiadaannya dibaca tamu yang datang sendiri.
        $payload = $this->payloadDasar();
        unset($payload['daftar_anggota']);

        $nomor = $this->daftarkanPayload($payload);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $nomor)
            ->first();

        $this->assertCount(0, app(DaftarAnggotaRombongan::class)->dariJson($detail->Daftar_Anggota_Json));
        $this->assertSame(1, (int) $detail->Jumlah_Tamu);
    }

    /** @param array<string, mixed> $ubah */
    private function daftarkan(array $ubah): string
    {
        return $this->daftarkanPayload(array_merge($this->payloadDasar(), $ubah));
    }

    /** @param array<string, mixed> $payload */
    private function daftarkanPayload(array $payload): string
    {
        $request = $this->permintaanRegistrasiTamu(
            $payload,
            self::PIC,
            'ANG-'.Str::upper(Str::random(20)),
        );

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }

    /** @return array<string, mixed> */
    private function payloadDasar(): array
    {
        return [
            'nama_tamu' => 'Rudi Hartono',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            // Relatif hari ini: Estimasi Masuk tak boleh mundur (DDL-2026-121).
            'tanggal_kunjungan' => now()->addDay()->toDateString(),
            'jumlah_tamu' => 99,
            'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => 'Tidak Berkendara',
            'waktu_mulai_kunjungan' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => now()->addDay()->setTime(17, 0)->format('Y-m-d H:i:s'),
            'daftar_anggota' => [],
        ];
    }
}
