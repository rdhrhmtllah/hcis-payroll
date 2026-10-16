<?php

namespace Tests\Integration\VISITOR\Guest\Visit;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\VISITOR\PermintaanRegistrasiTamu;
use Tests\TestCase;

/**
 * Jendela jam kunjungan: satu-satunya sumber masa berlaku baris TAMU di gerbang.
 *
 * Sebelum ini, satu-satunya isyarat waktu pada registrasi tamu adalah
 * Waktu_Estimasi_Kunjungan: varchar(50) berisi label kasar hasil dropdown
 * ('1-2 jam', 'Seharian') yang di-hardcode di getMasterData(). Kolom itu tidak
 * bisa menjawab "tamu ini berlaku sampai jam berapa", dan datanya sudah
 * bercampur — ada baris berisi '13:30'. Kalau jendela jam boleh kosong atau
 * boleh terbalik, kunjungannya lahir dengan rentang yang mustahil.
 *
 * Sejak DDL-2026-121 jendela ini (Estimasi Masuk/Keluar) WAJIB pada registrasi
 * HCIS, Estimasi Masuk tidak boleh sebelum hari ini, dan permit COKLAT B2B
 * mengikuti rentangnya. Tanggal di uji ini karena itu relatif terhadap hari
 * ini, bukan tanggal tetap yang lama-lama menjadi masa lalu.
 */
class JendelaKunjunganTamuTest extends TestCase
{
    use DatabaseTransactions;
    use PermintaanRegistrasiTamu;

    /**
     * Kode karyawan NYATA di Web_HRIS development.
     *
     * Kode karangan tidak bisa dipakai: syncTamuEfektif() menulis Nama_PIC yang
     * NOT NULL di N_VISITOR_Tamu_Efektif, dan namanya di-resolve lewat JOIN ke
     * tabel Karyawan. PIC yang tidak ada membuat seluruh registrasi gagal 500 —
     * kegagalan yang menyamar sebagai bug fitur, padahal fixture-nya yang salah.
     */
    private const PIC = 'A1';

    public function test_jendela_jam_tersimpan_apa_adanya(): void
    {
        $nomor = $this->daftarkan([
            'waktu_mulai_kunjungan' => $this->pada('08:00:00'),
            'waktu_selesai_kunjungan' => $this->pada('17:00:00'),
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();

        $this->assertSame($this->pada('08:00:00'), substr((string) $detail->Waktu_Mulai_Kunjungan, 0, 19));
        $this->assertSame($this->pada('17:00:00'), substr((string) $detail->Waktu_Selesai_Kunjungan, 0, 19));
    }

    public function test_jendela_boleh_menyeberang_tengah_malam(): void
    {
        // Kunjungan shift malam. Tanggal_Kunjungan tetap tanggal MULAI supaya
        // papan harian dan laporan tidak goyah; akhir jendela yang menyeberang.
        $nomor = $this->daftarkan([
            'tanggal_kunjungan' => $this->tanggal(),
            'waktu_mulai_kunjungan' => $this->pada('20:00:00'),
            'waktu_selesai_kunjungan' => $this->pada('06:00:00', 2),
        ]);

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->first();
        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->first();

        $this->assertSame($this->pada('06:00:00', 2), substr((string) $detail->Waktu_Selesai_Kunjungan, 0, 19));
        $this->assertSame($this->tanggal(), substr((string) $header->Tanggal_Kunjungan, 0, 10));
    }

    public function test_jendela_terbalik_ditolak(): void
    {
        $this->expectException(ValidationException::class);

        $this->daftarkan([
            'waktu_mulai_kunjungan' => $this->pada('17:00:00'),
            'waktu_selesai_kunjungan' => $this->pada('08:00:00'),
        ]);
    }

    public function test_jendela_yang_berakhir_tepat_saat_mulai_ditolak(): void
    {
        // Jendela nol menit adalah akses yang sudah habis pada detik ia terbit.
        // 'after' (bukan 'after_or_equal') yang menangkapnya.
        $this->expectException(ValidationException::class);

        $this->daftarkan([
            'waktu_mulai_kunjungan' => $this->pada('08:00:00'),
            'waktu_selesai_kunjungan' => $this->pada('08:00:00'),
        ]);
    }

    public function test_jendela_wajib_pada_registrasi_tamu(): void
    {
        // DDL-2026-121: Estimasi Masuk/Keluar WAJIB di layar HCIS (dulu
        // opsional dan tersimpan NULL). Tanggal kunjungan saja tidak cukup.
        try {
            $this->daftarkan(['waktu_mulai_kunjungan' => null, 'waktu_selesai_kunjungan' => null]);
            $this->fail('Registrasi tanpa Estimasi Masuk/Keluar seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('waktu_mulai_kunjungan', $e->errors());
            $this->assertArrayHasKey('waktu_selesai_kunjungan', $e->errors());
        }
    }

    public function test_estimasi_masuk_sebelum_hari_ini_ditolak(): void
    {
        // DDL-2026-121: setara aturan tanggal kunjungan — tidak boleh mundur.
        try {
            $this->daftarkan([
                'waktu_mulai_kunjungan' => $this->pada('08:00:00', -1),
                'waktu_selesai_kunjungan' => $this->pada('17:00:00', -1),
            ]);
            $this->fail('Estimasi Masuk kemarin seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('waktu_mulai_kunjungan', $e->errors());
        }
    }

    public function test_tanggal_kunjungan_diturunkan_dari_estimasi_masuk(): void
    {
        // DDL-2026-121: tanggal_kunjungan opsional; header selalu mengikuti
        // tanggal Estimasi Masuk.
        $nomor = $this->daftarkan([
            'tanggal_kunjungan' => null,
            'waktu_mulai_kunjungan' => $this->pada('09:00:00', 3),
            'waktu_selesai_kunjungan' => $this->pada('15:00:00', 3),
        ]);

        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->first();

        $this->assertSame($this->tanggal(3), substr((string) $header->Tanggal_Kunjungan, 0, 10));
    }

    public function test_base_tamu_query_membawa_jendela(): void
    {
        // Proyeksi TAMU membaca jendela kunjungannya lewat baseTamuQuery().
        // Kalau kolomnya tidak ikut ter-select, kunjungan lintas hari terbaca
        // kedaluwarsa kepagian di gerbang — tanpa satu pun galat.
        $nomor = $this->daftarkan([
            'waktu_mulai_kunjungan' => $this->pada('08:00:00'),
            'waktu_selesai_kunjungan' => $this->pada('17:00:00'),
        ]);

        $baris = app(VisitorPermitQueryService::class)
            ->baseTamuQuery()->where('h.No_Transaksi', $nomor)->first();

        $this->assertNotNull($baris->Waktu_Mulai_Kunjungan);
        $this->assertNotNull($baris->Waktu_Selesai_Kunjungan);
    }

    /** @param array<string,mixed> $ubah */
    private function daftarkan(array $ubah): string
    {
        $payload = array_merge([
            'nama_tamu' => 'Tamu Uji Jendela',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            'tanggal_kunjungan' => $this->tanggal(),
            'jumlah_tamu' => 1,
            'daftar_anggota' => [],
            'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => 'Tidak Berkendara',
            'waktu_mulai_kunjungan' => $this->pada('08:00:00'),
            'waktu_selesai_kunjungan' => $this->pada('17:00:00'),
        ], $ubah);

        $request = $this->permintaanRegistrasiTamu(
            $payload,
            self::PIC,
            'JDL-'.Str::upper(Str::random(20)),
        );

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi tamu gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }

    /** Tanggal relatif hari ini (bawaan besok): Estimasi Masuk tak boleh mundur. */
    private function tanggal(int $tambahHari = 1): string
    {
        return Carbon::today()->addDays($tambahHari)->toDateString();
    }

    private function pada(string $jam, int $tambahHari = 1): string
    {
        return $this->tanggal($tambahHari).' '.$jam;
    }
}
