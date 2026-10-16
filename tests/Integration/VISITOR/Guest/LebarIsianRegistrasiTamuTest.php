<?php

namespace Tests\Integration\VISITOR\Guest;

use App\Services\VISITOR\Guest\Visit\RegistrasiTamuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Isian yang terlalu panjang ditolak SAAT REGISTRASI, bukan saat approval.
 *
 * Rantai penyimpanan tamu punya dua tahap dengan lebar kolom yang berbeda:
 * baris masuk ke N_EMI_VISITOR_Transaksi_Tamu_Detail, lalu diproyeksikan ke
 * N_VISITOR_Tamu_Efektif ketika verifikator menyetujuinya. Beberapa kolom di
 * tahap kedua LEBIH SEMPIT daripada tahap pertama — 'Keterangan_Tambahan'
 * bertipe text (tak terbatas) lalu menjadi varchar(500), dan 'No_Identitas'
 * 100 karakter lalu menjadi 50.
 *
 * Tanpa penjaga, kegagalannya tertunda dan itulah yang membuatnya mahal:
 * registrasi tersimpan mulus, lalu APPROVAL-nya yang meledak dengan "String
 * or binary data would be truncated". Pengajuannya terlanjur ada di sistem
 * dan tidak akan pernah bisa disetujui siapa pun, sementara verifikator hanya
 * melihat galat umum yang tak menyebut field mana pun.
 */
class LebarIsianRegistrasiTamuTest extends TestCase
{
    use DatabaseTransactions;

    private function pic(): string
    {
        $kode = DB::table('Karyawan')->value('Kode_Karyawan');
        $this->assertNotNull($kode, 'Fixture butuh minimal satu karyawan nyata.');

        return $kode;
    }

    /**
     * @param  array<string,mixed>  $isian
     * @return array<string,mixed>
     */
    private function payload(array $isian = []): array
    {
        return array_merge([
            'kode_karyawan_pic' => $this->pic(),
            'nama_tamu' => 'Tamu Uji Lebar',
            'instansi_asal' => 'PT Uji',
            'keperluan' => 'Uji penjaga lebar isian',
            'pic_tujuan' => 'PIC Uji',
            'tanggal_kunjungan' => now()->addDay()->toDateString(),
            // Estimasi Masuk/Keluar seperti yang dikirim layar sejak
            // DDL-2026-121; penulis kini menurunkan tanggal dari sini.
            'waktu_mulai_kunjungan' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => now()->addDay()->setTime(15, 0)->format('Y-m-d H:i:s'),
            'idempotency_key' => 'uji-lebar-'.uniqid(),
            'daftar_anggota' => [],
        ], $isian);
    }

    /**
     * @dataProvider isianKepanjangan
     */
    public function test_isian_melebihi_kolom_tersempit_ditolak_saat_registrasi(
        string $field,
        int $panjang,
        int $batas,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Isian '{$field}' melebihi {$batas} karakter.");

        app(RegistrasiTamuService::class)->simpan(
            $this->payload([$field => str_repeat('A', $panjang)]),
        );
    }

    /**
     * @return array<string, array{0:string,1:int,2:int}>
     */
    public static function isianKepanjangan(): array
    {
        return [
            // Kolom transaksinya 'text' dan menerima ini tanpa keluhan; yang
            // menolak adalah kolom proyeksinya, varchar(500).
            'keterangan melampaui kolom proyeksi' => ['keterangan', 600, 500],
            // Kolom transaksi 100, kolom proyeksi 50.
            'no_identitas melampaui kolom proyeksi' => ['no_identitas', 80, 50],
            'nama_tamu melampaui kolom transaksi' => ['nama_tamu', 200, 150],
            'keperluan melampaui kolom transaksi' => ['keperluan', 300, 250],
        ];
    }

    /**
     * Sisi PENERIMAAN. Tanpa ini, penjaga yang menolak SEGALANYA akan tetap
     * membuat seluruh uji di atas hijau sambil melumpuhkan registrasi tamu.
     */
    public function test_isian_dalam_batas_tetap_tersimpan(): void
    {
        $hasil = app(RegistrasiTamuService::class)->simpan(
            $this->payload([
                'keterangan' => str_repeat('A', 500),
                'no_identitas' => str_repeat('9', 50),
            ]),
        );

        $this->assertNotEmpty($hasil['no_transaksi']);

        $tersimpan = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->where('No_Transaksi', $hasil['no_transaksi'])
            ->first();

        $this->assertSame(50, strlen($tersimpan->No_Identitas));
    }

    /**
     * Batas yang lebih sempit daripada baris yang SUDAH tersimpan bukan
     * pengetatan, melainkan kerusakan: registrasi lama menjadi tak bisa
     * disunting ulang, dan salahnya baru ketahuan ketika seseorang gagal
     * menyimpan sesuatu yang kemarin baik-baik saja.
     */
    public function test_batas_baru_tidak_menolak_baris_yang_sudah_ada(): void
    {
        $terpanjang = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
            ->selectRaw('MAX(LEN(No_Identitas)) as identitas, MAX(LEN(Nama_Lengkap_Tamu)) as nama')
            ->first();

        $this->assertLessThanOrEqual(50, (int) $terpanjang->identitas);
        $this->assertLessThanOrEqual(150, (int) $terpanjang->nama);
    }

    /**
     * Kolom proyeksi benar-benar lebih sempit — inilah fakta yang membuat
     * seluruh penjaga di atas perlu ada. Bila DDL kelak menyamakannya,
     * uji ini MERAH dan batasnya boleh ditinjau ulang.
     */
    public function test_kolom_proyeksi_memang_lebih_sempit_daripada_kolom_transaksi(): void
    {
        $lebar = fn (string $tabel, string $kolom) => (int) DB::scalar(
            'SELECT c.max_length FROM sys.columns c WHERE c.object_id = OBJECT_ID(?) AND c.name = ?',
            [$tabel, $kolom],
        );

        $this->assertSame(
            50,
            $lebar('N_VISITOR_Tamu_Efektif', 'No_Identitas'),
            'Lebar kolom proyeksi berubah; batas validasi perlu ditinjau ulang.',
        );
        $this->assertSame(
            100,
            $lebar('N_EMI_VISITOR_Transaksi_Tamu_Detail', 'No_Identitas'),
        );
    }
}
