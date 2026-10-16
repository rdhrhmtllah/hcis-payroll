<?php

namespace Tests\Feature\VISITOR\Permit\VehiclePermit;

use App\Http\Middleware\HrisApiGuard;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\VISITOR\SuntikAplikasiApi;
use Tests\TestCase;

/**
 * Kontrak endpoint pengajuan permit mitra.
 *
 * Guard sengaja dilewati di sini — keamanannya diuji terpisah
 * (HrisApiGuardJalurBertandaTanganTest dan PermitTamuApiRegistrasiTest). Yang
 * ditagih berkas ini adalah kontraknya DAN ketiga pengecualian yang
 * membedakannya dari pengajuan karyawan: tanpa foto, tanpa checklist, tanpa
 * kuota per karyawan. Kalau salah satunya masih menyala, pengajuan mitra tidak
 * akan pernah bisa masuk.
 */
class PermitTamuApiAjukanTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    private const URL = '/api/v1/hcis/visitor/permit-tamu';

    private int $idAplikasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HrisApiGuard::class);
        $this->idAplikasi = $this->buatAplikasi(self::PIC);

        // withoutMiddleware() ikut mematikan injeksi atribut yang biasanya
        // dilakukan guard, dan $this->app['request'] bukan objek request yang
        // sama dengan yang dipakai postJson(). Middleware uji ini menyuntikkan
        // atribut yang sama pada request yang benar-benar dipakai. Harus berupa
        // NAMA KELAS: closure yang didorong ke grup membuat resolver melempar
        // "Illegal offset type in isset or empty".
        SuntikAplikasiApi::$idAplikasi = $this->idAplikasi;
        $this->app['router']->pushMiddlewareToGroup('api', SuntikAplikasiApi::class);
    }

    protected function tearDown(): void
    {
        SuntikAplikasiApi::reset();

        parent::tearDown();
    }

    public function test_pengajuan_sah_menghasilkan_permit_pending(): void
    {
        $isi = $this->ajukan()->assertStatus(201)->json();

        $this->assertTrue($isi['sukses']);
        $this->assertSame('PENDING', $isi['result']['status']);

        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $isi['result']['no_transaksi'])->first();

        $this->assertSame('API', $baris->Sumber_Pengajuan);
        $this->assertSame($this->idAplikasi, (int) $baris->Id_Api_Aplikasi);
        $this->assertSame(self::PIC, trim((string) $baris->Kode_Karyawan_Pemohon));
        $this->assertNull($baris->Flag_Approval, 'Permit mitra tidak boleh langsung aktif.');
    }

    public function test_identitas_tamu_dan_status_pending_tersimpan(): void
    {
        $no = $this->ajukan()->json('result.no_transaksi');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $no)->first();

        $this->assertSame('Rudi Mitra', $detail->Nama_Tamu);
        $this->assertSame('PT Mitra Sejahtera', $detail->Instansi_Tamu);
        $this->assertNull($detail->Tanggal_Mulai_Berlaku);
        $this->assertNull($detail->Tanggal_Kadaluarsa);
        $this->assertNull($detail->Waktu_Mulai_Berlaku);
        $this->assertNull($detail->Waktu_Berakhir);
        $this->assertSame('COKLAT', $detail->Snapshot_Kategori_Permit);
    }

    public function test_api_hanya_menuntut_identitas_dan_keperluan(): void
    {
        $this->ajukan()->assertStatus(201)
            ->assertJsonPath('result.status', 'PENDING')
            ->assertJsonMissingPath('result.tanggal_berlaku')
            ->assertJsonMissingPath('result.waktu_mulai')
            ->assertJsonMissingPath('result.waktu_selesai');
    }

    public function test_tidak_menuntut_dokumen_kendaraan(): void
    {
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->where('No_Transaksi', $no)->count(),
            'Pengajuan mitra tidak boleh menuntut foto kendaraan, STNK, atau SIM dari mitranya; '
                .'dokumen itu diunggah verifikator saat pemeriksaan.',
        );
    }

    public function test_checklist_inspeksi_menunggu_verifikator_sejak_pengajuan(): void
    {
        // Mitra tidak mengirim data kendaraan apa pun, jadi merek, tahun, dan
        // warna adalah hasil pemeriksaan verifikator di pos — dan pemeriksaan
        // itu butuh pertanyaannya sudah ada sejak pengajuan diterima.
        // saveAnswers() meng-UPDATE baris ini, bukan menyisipkannya.
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $this->assertGreaterThan(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->count(),
            'Pengajuan mitra lahir tanpa satu pun pertanyaan inspeksi.',
        );
    }

    public function test_pengajuan_tanpa_kendaraan_tidak_diberi_checklist(): void
    {
        // Tidak ada kendaraan berarti tidak ada yang bisa diperiksa; checklist
        // yang tetap terbit membuat izin masuk pejalan kaki tidak akan pernah
        // bisa disetujui.
        $no = $this->ajukan(ubah: [
            'plat_prefix' => null,
            'plat_angka' => null,
            'plat_suffix' => null,
            'no_plat_kendaraan' => null,
            'jenis_kendaraan' => null,
        ])->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            0,
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->count(),
        );
    }

    public function test_jenis_kendaraan_mitra_tersimpan_untuk_gerbang(): void
    {
        // Satu-satunya petunjuk kendaraan apa yang datang, selama verifikator
        // belum menetapkan modelnya.
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            'Mobil',
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->where('No_Transaksi', $no)->value('Jenis_Kendaraan_Tamu'),
        );
    }

    public function test_kuota_karyawan_tidak_menghalangi_pengajuan_mitra(): void
    {
        // Kuota permit karyawan maksimal dua pengajuan aktif. Seluruh permit
        // mitra memakai satu PIC, jadi tanpa pengecualian permit KETIGA akan
        // ditolak "jatah penuh" — dan mitra tidak punya cara memahami kenapa.
        $this->ajukan()->assertStatus(201);
        $this->ajukan()->assertStatus(201);
        $this->ajukan()->assertStatus(201);
    }

    public function test_kiriman_ulang_dengan_kunci_sama_tidak_menggandakan(): void
    {
        $kunci = 'IDEM-'.Str::upper(Str::random(20));

        $pertama = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');
        $kedua = $this->ajukan(kunci: $kunci)->assertStatus(200);

        $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
        $this->assertTrue($kedua->json('result.idempotent_replay'));

        $this->assertSame(
            1,
            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('Id_Api_Aplikasi', $this->idAplikasi)
                ->where('Idempotency_Key', $kunci)
                ->count(),
        );
    }

    public function test_kiriman_ulang_memulangkan_status_permit_saat_ini(): void
    {
        // Dulu replay selalu PENDING, walau permitnya sudah dibatalkan.
        $kunci = 'IDEM-'.Str::upper(Str::random(20));
        $no = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');

        $this->postJson(self::URL."/{$no}/batal", ['alasan' => 'Uji replay'])->assertSuccessful();
        $sekarang = $this->getJson(self::URL."/{$no}")->json('result.status');

        $replay = $this->ajukan(kunci: $kunci)->assertStatus(200);

        $this->assertNotSame('PENDING', $sekarang);
        $this->assertSame($sekarang, $replay->json('result.status'));
    }

    public function test_kiriman_bersamaan_yang_kalah_di_indeks_unik_dijawab_sebagai_replay(): void
    {
        // Meniru dua kiriman berkunci sama yang sama-sama lolos pemeriksaan
        // replay: kunci permit pertama baru "muncul" sesudah pemeriksaan itu,
        // tepat sebelum insert kiriman kedua.
        //
        // Mock dipasang SEBELUM permintaan pertama: route menyimpan instans
        // controller-nya, jadi mock yang datang belakangan tidak pernah dipakai.
        $kunci = 'IDEM-'.Str::upper(Str::random(20));
        $pertama = null;

        $asli = app(VisitorPermitApiService::class);
        $this->mock(VisitorPermitApiService::class, function ($m) use ($asli, &$pertama, $kunci) {
            $m->shouldReceive('identitasPemohon')->andReturnUsing(fn ($id) => $asli->identitasPemohon($id));
            $m->shouldReceive('melampauiBatas')->andReturnUsing(function () use (&$pertama, $kunci) {
                if ($pertama !== null) {
                    DB::table('N_EMI_VISITOR_Transaksi_Permit')
                        ->where('No_Transaksi', $pertama)
                        ->update(['Idempotency_Key' => $kunci]);
                }

                return false;
            });
        });

        $pertama = $this->ajukan(kunci: 'SEMENTARA-'.Str::random(10))->assertStatus(201)->json('result.no_transaksi');

        $kedua = $this->ajukan(kunci: $kunci)->assertStatus(200);

        $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
        $this->assertTrue($kedua->json('result.idempotent_replay'));
    }

    public function test_dua_aplikasi_boleh_memakai_kunci_yang_sama(): void
    {
        // Inilah alasan indeks idempotensi diperluas mencakup Id_Api_Aplikasi.
        // Sebelumnya mitra kedua akan menerima nomor permit milik mitra pertama.
        $kunci = 'IDEM-'.Str::upper(Str::random(20));
        $pertama = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');

        SuntikAplikasiApi::$idAplikasi = $this->buatAplikasi(self::PIC);

        $kedua = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');

        $this->assertNotSame($pertama, $kedua, 'Mitra kedua menerima permit milik mitra pertama.');
    }

    public function test_api_b2b_tidak_lagi_menuntut_tanggal_berlaku_saat_pengajuan(): void
    {
        $this->ajukan(ubah: ['tanggal_berlaku' => null])->assertStatus(201);
    }

    public function test_tanpa_header_idempotency_tetap_diterima_dan_idempoten_lewat_referensi_mitra(): void
    {
        $ref = 'REF-B2B-'.Str::random(10);
        $pertama = $this->postJson(self::URL, array_merge($this->payload(), ['referensi_mitra' => $ref]))
            ->assertStatus(201)
            ->json('result.no_transaksi');

        $kedua = $this->postJson(self::URL, array_merge($this->payload(), ['referensi_mitra' => $ref]))
            ->assertStatus(200);

        $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
        $this->assertTrue($kedua->json('result.idempotent_replay'));
    }

    public function test_tanpa_header_idempotency_dan_tanpa_referensi_tetap_diterima(): void
    {
        // Keduanya opsional di OpenAPI; dulu jalur ini 500 karena Str tidak diimpor.
        $this->postJson(self::URL, array_merge($this->payload(), ['referensi_mitra' => null]))
            ->assertStatus(201);
    }

    public function test_aplikasi_tanpa_pic_tetap_bisa_mengajukan(): void
    {
        // PIC opsional: pemohonnya menjadi aplikasi itu sendiri. Sebelumnya
        // permintaan seperti ini ditolak 422, dan pendaftaran mitra jadi
        // menyandera satu kode karyawan yang sebenarnya tidak diperlukan.
        $tanpaPic = $this->buatAplikasi(null);
        SuntikAplikasiApi::$idAplikasi = $tanpaPic;

        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            'API#'.$tanpaPic,
            trim((string) DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $no)->value('Kode_Karyawan_Pemohon')),
        );
    }

    public function test_nama_pemohon_permit_mitra_adalah_nama_aplikasinya(): void
    {
        // Proyeksi gerbang menuntut Nama_Pemohon NOT NULL, dan JOIN ke Karyawan
        // tidak menghasilkan apa pun untuk penanda 'API#<id>'. Namanya harus
        // datang dari aplikasinya, kalau tidak penerbitan permit gagal menyisip.
        $tanpaPic = $this->buatAplikasi(null);
        SuntikAplikasiApi::$idAplikasi = $tanpaPic;
        $namaAplikasi = DB::table('N_HRIS_Api_Aplikasi')->where('Id_Api_Aplikasi', $tanpaPic)->value('Nama_Aplikasi');

        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $baris = app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)
            ->basePermitQuery()->where('h.No_Transaksi', $no)->first();

        $this->assertSame($namaAplikasi, $baris->Nama_Pemohon);
    }

    public function test_cermin_permit_karyawan_tetap_memakai_nama_karyawannya(): void
    {
        // Perubahan Nama_Pemohon menjadi COALESCE menyentuh SETIAP permit, bukan
        // hanya permit mitra. Uji ini menagih bahwa permit karyawan tidak ikut
        // berubah namanya.
        $no = 'PMT-HCIS-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Sumber_Pengajuan' => 'HCIS',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => null,
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'HCIS-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $no,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'HC',
        ]);

        $baris = app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)
            ->basePermitQuery()->where('h.No_Transaksi', $no)->first();

        $namaKaryawan = DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->value('Nama');

        $this->assertSame($namaKaryawan, $baris->Nama_Pemohon);
    }

    public function test_mengirim_no_plat_kendaraan_string_tunggal_dipecah_ke_prefix_angka_suffix(): void
    {
        $no = $this->ajukan(ubah: [
            'no_plat_kendaraan' => 'BG 5678 KKL',
            'plat_prefix' => null,
            'plat_angka' => null,
            'plat_suffix' => null,
        ])->assertStatus(201)->json('result.no_transaksi');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $no)->first();

        $this->assertSame('BG', $detail->Plat_Nomor_Prefix);
        $this->assertSame('5678', $detail->Plat_Nomor_Angka);
        $this->assertSame('KKL', $detail->Plat_Nomor_Suffix);
    }

    public function test_tamu_tanpa_plat_tetap_diterima(): void
    {
        // Konsisten dengan keputusan permit coklat: pejalan kaki tetap berpermit.
        $this->ajukan(ubah: [
            'plat_prefix' => null,
            'plat_angka' => null,
            'plat_suffix' => null,
            'jenis_kendaraan' => null,
        ])->assertStatus(201);
    }

    /** @param array<string,mixed> $ubah */
    private function ajukan(array $ubah = [], ?string $kunci = null): TestResponse
    {
        // array_merge, bukan '+': nilai null yang sengaja dikirim (mis. plat
        // kosong) harus MENIMPA nilai baku, bukan diabaikan.
        return $this->withHeader('Idempotency-Key', $kunci ?? 'API-'.Str::upper(Str::random(20)))
            ->postJson(self::URL, array_merge($this->payload(), $ubah));
    }

    /** @return array<string,mixed> */
    private function payload(): array
    {
        return [
            'nama_tamu' => 'Rudi Mitra',
            'instansi' => 'PT Mitra Sejahtera',
            'keperluan' => 'Pengiriman material',
            'jenis_kendaraan' => 'Mobil',
            'no_plat_kendaraan' => 'BG 1234 MT',
            'referensi_mitra' => 'GUEST-'.Str::random(6),
        ];
    }

    private function buatAplikasi(?string $pic): int
    {
        return (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => 4,
            'Nama_Aplikasi' => 'Uji Mitra '.Str::upper(Str::random(6)),
            'Api_Public' => 'pub-'.Str::random(20),
            'Api_Secret' => 'sec-'.Str::random(30),
            'Arah' => 'IN',
            'Kode_Karyawan_PIC' => $pic,
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ]);
    }
}
