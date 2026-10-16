<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Models\ESS\Employee\Models\User;
use App\Services\KPI\PerformanceAppraisal\PaHcLearnJadwalContract;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kontrak Karyawan & Kandidat milik HC Learn.
 *
 * Dua batas paling mahal dijaga di sini:
 *
 * 1. checking dan add-new menerima maksimal 200 karyawan per permintaan. Penjadwalan
 *    menerima 1000, jadi memakai konstanta penjadwalan di sini baru pecah pada siklus
 *    sungguhan — bukan pada contoh tiga orang di dokumentasi.
 * 2. add-new boleh menjawab 207: sebagian tersimpan, sebagian gagal. HTTP 207 tetap
 *    `successful()`, sehingga berhenti pada status HTTP menghasilkan pesan sukses palsu
 *    dan orang yang gagal baru diketahui ketika store penjadwalan menolak satu batch.
 */
class HcLearnKaryawanContractTest extends TestCase
{
    private PaHcLearnJadwalContract $kontrak;

    protected function setUp(): void
    {
        parent::setUp();

        $pengguna = User::whereNotNull('User_Key')->first();
        $this->assertNotNull($pengguna, 'Tidak ada pengguna ber-User_Key untuk menandatangani permintaan uji.');
        $this->actingAs($pengguna);

        $this->kontrak = app(PaHcLearnJadwalContract::class);
    }

    /**
     * Perubahan yang membuat uji ini merah: buang array_chunk(..., 200) dari
     * periksaKaryawan(). Fake sengaja menolak potongan ke-201 seperti kontrak aslinya.
     */
    public function test_pemeriksaan_201_karyawan_dikirim_sebagai_200_dan_1(): void
    {
        $kode = array_map(fn (int $i) => sprintf('UJI-%03d', $i), range(1, 201));
        $ukuran = [];

        Http::fake(function ($request) use (&$ukuran) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
            $potongan = array_values(array_filter(explode(',', (string) ($query['kode_karyawan'] ?? ''))));
            $ukuran[] = count($potongan);

            if (count($potongan) > 200) {
                return Http::response([
                    'success' => false,
                    'message' => 'kode_karyawan melebihi 200.',
                ], 422);
            }

            return Http::response([
                'success' => true,
                'status' => 200,
                'result' => [
                    'dicari_dengan' => 'kode_karyawan',
                    'ringkasan' => [
                        'diminta' => count($potongan),
                        'ditemukan' => count($potongan),
                        'belum_ada' => 0,
                        'siap_dipakai' => count($potongan),
                    ],
                    'data' => array_map(fn (string $k) => [
                        'dicari' => $k,
                        'ada' => true,
                        'siap_dipakai' => true,
                        'alasan' => 'SIAP',
                        'keterangan' => 'Karyawan terdaftar dan aktif.',
                        'karyawan' => ['kode_karyawan' => $k],
                    ], $potongan),
                ],
            ]);
        });

        $hasil = $this->kontrak->periksaKaryawan($kode);

        $this->assertSame([200, 1], $ukuran);
        $this->assertCount(201, $hasil);
        $this->assertTrue($hasil['UJI-201']['ada']);
        $this->assertTrue($hasil['UJI-201']['siap']);
    }

    /**
     * Perubahan yang membuat uji ini merah: anggap response 207 selesai tanpa menggabung
     * `gagal[]`, atau kirim 201 baris dalam satu permintaan.
     */
    public function test_pendaftaran_207_menggabungkan_yang_tersimpan_dan_gagal_per_baris(): void
    {
        $karyawan = array_map(fn (int $i) => [
            'kode_karyawan' => sprintf('BARU-%03d', $i),
            'nama' => 'Karyawan Uji '.$i,
        ], range(1, 201));
        $ukuran = [];

        Http::fake(function ($request) use (&$ukuran) {
            $baris = (array) ($request->data()['data'] ?? []);
            $ukuran[] = count($baris);

            if (count($baris) === 200) {
                return Http::response([
                    'success' => true,
                    'status' => 207,
                    'message' => 'Sebagian data tidak tersimpan.',
                    'result' => [
                        'ringkasan' => ['diminta' => 200, 'tersimpan' => 199, 'sudah_ada' => 0, 'gagal' => 1],
                        'tersimpan' => array_map(fn (array $k) => [
                            'kode' => $k['kode_karyawan'],
                            'nama' => $k['nama'],
                            'kolom_terisi' => 2,
                        ], array_slice($baris, 0, 199)),
                        'sudah_ada' => [],
                        'gagal' => [[
                            'baris' => 199,
                            'kode' => 'BARU-200',
                            'alasan' => 'GAGAL-SIMPAN',
                            'keterangan' => 'Baris uji sengaja gagal.',
                        ]],
                    ],
                ], 207);
            }

            return Http::response([
                'success' => true,
                'status' => 200,
                'message' => 'Semua data terproses.',
                'result' => [
                    'ringkasan' => ['diminta' => 1, 'tersimpan' => 1, 'sudah_ada' => 0, 'gagal' => 0],
                    'tersimpan' => [[
                        'kode' => 'BARU-201',
                        'nama' => 'Karyawan Uji 201',
                        'kolom_terisi' => 2,
                    ]],
                    'sudah_ada' => [],
                    'gagal' => [],
                ],
            ]);
        });

        $hasil = $this->kontrak->daftarkanKaryawan($karyawan);

        $this->assertSame([200, 1], $ukuran);
        $this->assertSame([
            'diminta' => 201,
            'tersimpan' => 200,
            'sudah_ada' => 0,
            'gagal' => 1,
        ], $hasil['ringkasan']);
        $this->assertCount(200, $hasil['tersimpan']);
        $this->assertSame('BARU-200', $hasil['gagal'][0]['kode']);
        $this->assertSame('Baris uji sengaja gagal.', $hasil['gagal'][0]['keterangan']);
    }
}
