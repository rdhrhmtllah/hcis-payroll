<?php

namespace Tests\Feature\VISITOR\Guest\Visit;

use App\Http\Middleware\HrisApiGuard;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\VISITOR\SuntikAplikasiApi;
use Tests\TestCase;

/**
 * Kontrak endpoint registrasi tamu B2B milik aplikasi mitra.
 *
 * Guard sengaja dilewati di sini — keamanannya diuji terpisah
 * (HrisApiGuardJalurBertandaTanganTest dan TamuB2BApiRegistrasiTest). Yang
 * ditagih berkas ini adalah hal yang membedakannya dari endpoint permit-tamu:
 * pintu ini membuat baris KUNJUNGAN sungguhan yang sampai ke meja verifikator,
 * bukan sekadar izin gerbang tanpa jejak tamu.
 *
 * Sejak DDL-2026-121 payload bakunya membawa estimasi_masuk/estimasi_keluar.
 * Bentuk lama (tanggal_kunjungan saja) diuji terpisah sebagai MASA TRANSISI
 * (services.visitor.tamu_b2b_api.wajib_lengkap = false): tetap diterima,
 * dengan `peringatan` di balasannya; mode wajib menolaknya 422.
 */
class TamuB2BApiTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    private const URL = '/api/v1/hcis/visitor/tamu-b2b';

    private int $idAplikasi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(HrisApiGuard::class);
        $this->idAplikasi = $this->buatAplikasi(self::PIC);

        // withoutMiddleware() ikut mematikan injeksi atribut yang biasanya
        // dilakukan guard. Middleware uji ini menyuntikkan atribut yang sama
        // pada request yang benar-benar dipakai postJson(). Harus berupa NAMA
        // KELAS: closure yang didorong ke grup membuat resolver melempar
        // "Illegal offset type in isset or empty".
        SuntikAplikasiApi::$idAplikasi = $this->idAplikasi;
        $this->app['router']->pushMiddlewareToGroup('api', SuntikAplikasiApi::class);
    }

    protected function tearDown(): void
    {
        SuntikAplikasiApi::reset();

        parent::tearDown();
    }

    public function test_registrasi_sah_membuat_kunjungan_tamu_b2b(): void
    {
        $isi = $this->ajukan()->assertStatus(201)->json();

        $this->assertTrue($isi['sukses']);
        // Kunjungan berpermit TIDAK PERNAH dibalas APPROVED saat baru diajukan:
        // izin gerbangnya baru saja masuk antrean verifikator, dan `status`
        // menjawab "boleh masuk", bukan "kunjungannya tercatat".
        $this->assertSame('PENDING', $isi['result']['status']);
        $this->assertSame(
            $this->butuhApproval() ? 'PENDING' : 'APPROVED',
            $isi['result']['status_kunjungan'],
        );

        $no = $isi['result']['no_transaksi'];
        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->first();
        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $no)->first();

        $this->assertSame('API', $header->Sumber_Pengajuan);
        $this->assertSame($this->idAplikasi, (int) $header->Id_Api_Aplikasi);
        // Kunjungan mitra TIDAK bertuan (DDL-2026-135). Sebelumnya kolom ini
        // diisi PIC registry, sehingga seluruh kunjungan mitra terbaca seolah
        // orang itu yang mendaftarkannya.
        $this->assertNull($header->Kode_Karyawan_PIC);

        // Jejak audit memuat nama penanggung jawab DAN penanda aplikasinya;
        // kode karyawan sengaja tidak ada di sana.
        $this->assertStringContainsString('Budi Santoso', (string) $header->Created_By);
        $this->assertStringContainsString('API#', (string) $header->Created_By);
        $this->assertStringNotContainsString(self::PIC, (string) $header->Created_By);
        $this->assertSame(
            $this->butuhApproval() ? null : 'Y',
            $header->Flag_Approval,
            'Kunjungan mitra tidak mengikuti kebijakan approval tamu.',
        );
        $this->assertSame('B2B', $detail->Tipe_Tamu);
        $this->assertSame('Rudi Mitra', $detail->Nama_Lengkap_Tamu);
        $this->assertSame('PT Mitra Sejahtera', $detail->Instansi_Atau_Asal);
    }

    /**
     * Satu meja saja: permit COKLAT-nya, bukan kunjungannya.
     *
     * Bawaan B2B sejak 25 Sep 2026 tidak lagi meminta approval KUNJUNGAN — dua
     * meja berurutan hanya menunda, sebab yang benar-benar memberi akses
     * gerbang adalah permitnya dan meja permit memeriksa hal yang sama.
     * Penjaga ini memakukan seluruh akibat yang harus menyertainya sekaligus,
     * karena melewatkan salah satunya tidak menyalakan galat apa pun:
     * kunjungannya terjadwal, proyeksi gerbang tamunya ikut terbit, permit
     * COKLAT-nya ADA dan MENUNGGU, dan tidak ada satu pun baris approval yang
     * dibuat untuk nomor kunjungan itu.
     */
    public function test_bawaan_b2b_hanya_meminta_approval_permit_bukan_kunjungannya(): void
    {
        $this->assertFalse(
            $this->butuhApproval(),
            'Bawaan tamu_b2b.needs_approval berubah; keputusan 25 Sep 2026 dibatalkan tanpa catatan.',
        );

        $hasil = $this->ajukan()->assertStatus(201)->json('result');
        $no = $hasil['no_transaksi'];

        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->first();
        $this->assertSame('Y', $header->Flag_Approval, 'Kunjungan B2B masih menunggu keputusan.');
        $this->assertSame('APPROVED', $hasil['status_kunjungan']);

        $this->assertSame(
            0,
            DB::table('HRIS_Approval_Request')->where('No_Transaksi', $no)->count(),
            'Kunjungan B2B masih membuat alur approval; mejanya akan terisi baris yang tak seorang pun perlu putuskan.',
        );

        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi_Tamu', $no)->first();
        $this->assertNotNull($permit, 'Permit COKLAT tidak terbit padahal kunjungannya langsung terjadwal.');
        $this->assertNull($permit->Flag_Approval, 'Permit COKLAT ikut lolos tanpa diperiksa siapa pun.');

        // `status` menjawab "boleh masuk gerbang", jadi ia mengikuti permitnya.
        $this->assertSame('PENDING', $hasil['status']);

        $this->assertTrue(
            DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $no)->exists(),
            'Kunjungan terjadwal tanpa proyeksi gerbang: tamunya tak akan dikenali petugas.',
        );
    }

    public function test_kunjungan_mitra_sampai_ke_meja_verifikator(): void
    {
        // Inilah yang membedakan pintu ini dari /permit-tamu. Kalau barisnya
        // tidak terbaca baseTamuQuery(), tamu mitra tidak pernah muncul di
        // antrean verifikator dan kunjungannya menggantung tanpa satu pun galat.
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $baris = app(VisitorPermitQueryService::class)
            ->baseTamuQuery()->where('h.No_Transaksi', $no)->first();

        $this->assertNotNull($baris, 'Kunjungan mitra tidak terbaca di kueri meja verifikator.');
        $this->assertSame('B2B', $baris->Tipe_Tamu);
    }

    public function test_tipe_tamu_dipaku_b2b_walau_payload_memaksa_regular(): void
    {
        // Tipe yang bisa dikarang pemanggil berarti mitra dapat menghindari
        // permit COKLAT hanya dengan mengirim satu field tambahan.
        $no = $this->ajukan(ubah: ['tipe_tamu' => 'REGULAR'])->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            'B2B',
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $no)->value('Tipe_Tamu'),
        );
    }

    public function test_plat_nomor_tunggal_dipecah_ke_prefix_angka_suffix(): void
    {
        $no = $this->ajukan(ubah: ['no_plat_kendaraan' => 'BG 5678 KKL'])
            ->assertStatus(201)->json('result.no_transaksi');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $no)->first();

        $this->assertSame('BG', $detail->Plat_Nomor_Prefix);
        $this->assertSame('5678', $detail->Plat_Nomor_Angka);
        $this->assertSame('KKL', $detail->Plat_Nomor_Suffix);
    }

    public function test_manifes_tersimpan_persis_seperti_yang_dikirim_mitra(): void
    {
        // Nama tamu utama TIDAK disisipkan ke manifes. Mitra yang membacanya
        // kembali harus menemukan persis daftar yang ia kirim; nama tambahan
        // yang muncul sendiri akan dibacanya sebagai data yang kami karang.
        $no = $this->ajukan(ubah: [
            'daftar_anggota' => [
                ['nama' => 'Andi Pratama', 'jenis_identitas' => 'KTP', 'no_identitas' => '3201123456780002'],
                ['nama' => 'Sinta Dewi'],
            ],
        ])->assertStatus(201)->json('result.no_transaksi');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $no)->first();
        $manifes = json_decode((string) $detail->Daftar_Anggota_Json, true);

        $this->assertCount(2, $manifes, 'Manifes mitra ikut memuat nama yang tidak pernah dikirimnya.');
        $this->assertSame('Andi Pratama', $manifes[0]['nama']);
        $this->assertSame('Sinta Dewi', $manifes[1]['nama']);

        // Jumlahnya tetap menghitung tamu utamanya: dua penyerta + satu tamu.
        $this->assertSame(3, (int) $detail->Jumlah_Tamu);
    }

    public function test_tamu_sendirian_berdaftar_anggota_kosong(): void
    {
        $no = $this->ajukan(ubah: ['daftar_anggota' => []])
            ->assertStatus(201)
            ->json('result.no_transaksi');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $no)->first();

        $this->assertSame([], json_decode((string) $detail->Daftar_Anggota_Json, true));
        // Kosong bukan berarti nol orang: tamunya tetap satu.
        $this->assertSame(1, (int) $detail->Jumlah_Tamu);
    }

    public function test_tanpa_mengirim_daftar_anggota_sama_sekali_juga_kosong(): void
    {
        $payload = $this->payload();
        unset($payload['daftar_anggota']);

        $no = $this->withHeader('Idempotency-Key', 'API-'.Str::upper(Str::random(20)))
            ->postJson(self::URL, $payload)
            ->assertStatus(201)
            ->json('result.no_transaksi');

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $no)->first();

        $this->assertSame([], json_decode((string) $detail->Daftar_Anggota_Json, true));
        $this->assertSame(1, (int) $detail->Jumlah_Tamu);
    }

    public function test_tanpa_header_idempotency_tetap_idempoten_lewat_referensi_mitra(): void
    {
        $ref = 'REF-B2B-'.Str::upper(Str::random(10));

        $pertama = $this->postJson(self::URL, array_merge($this->payload(), ['referensi_mitra' => $ref]))
            ->assertStatus(201)->json('result.no_transaksi');
        $kedua = $this->postJson(self::URL, array_merge($this->payload(), ['referensi_mitra' => $ref]))
            ->assertStatus(200);

        $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
        $this->assertTrue($kedua->json('result.idempotent_replay'));
    }

    public function test_kiriman_ulang_dengan_kunci_sama_tidak_menggandakan(): void
    {
        $kunci = 'IDEM-'.Str::upper(Str::random(20));

        $pertama = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');
        $kedua = $this->ajukan(kunci: $kunci)->assertStatus(200);

        $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
        $this->assertSame(
            1,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                ->where('Id_Api_Aplikasi', $this->idAplikasi)
                ->where('Idempotency_Key', $kunci)
                ->count(),
        );
    }

    public function test_dua_aplikasi_boleh_memakai_kunci_yang_sama(): void
    {
        // Seluruh tamu satu mitra memakai satu PIC aplikasi. Tanpa Id_Api_Aplikasi
        // di kunci idempotensi, mitra kedua menerima nomor kunjungan milik mitra
        // pertama sebagai "sudah pernah diterima" — kebocoran antar pelanggan
        // yang tidak menyalakan galat apa pun.
        $kunci = 'IDEM-'.Str::upper(Str::random(20));
        $pertama = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');

        SuntikAplikasiApi::$idAplikasi = $this->buatAplikasi(self::PIC);

        $kedua = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');

        $this->assertNotSame($pertama, $kedua, 'Mitra kedua menerima kunjungan milik mitra pertama.');
    }

    public function test_aplikasi_tanpa_pic_ditolak_dengan_sebab_yang_jelas(): void
    {
        // Sejak DDL-2026-135 sebabnya BERGESER, dan penjaganya tetap perlu.
        //
        // Nama_PIC tidak lagi jadi soal - ia kini diisi dari nama penanggung
        // jawab yang dikirim mitra. Yang tetap menuntut karyawan nyata adalah
        // ALUR PERSETUJUANNYA: createFlow() menyusun rantai dari hierarki
        // seseorang, dan penanda 'API#<id>' tidak punya atasan. Tanpa penjaga
        // ini kunjungannya diterima dengan 201 lalu menggantung selamanya,
        // sebab tidak ada satu pun langkah approval yang bisa dikerjakan.
        SuntikAplikasiApi::$idAplikasi = $this->buatAplikasi(null);

        $this->ajukan()
            ->assertStatus(422)
            ->assertJsonPath('sukses', false)
            ->assertJsonFragment(['message' => 'Aplikasi ini belum menunjuk Kode_Karyawan_PIC. Registrasi tamu B2B membutuhkan '
                    .'seorang karyawan sebagai penjangkar alur persetujuan, walaupun kunjungannya '
                    .'sendiri dicatat tanpa pemilik.']);
    }

    public function test_pic_tujuan_kosong_jatuh_ke_identitas_aplikasi(): void
    {
        $namaAplikasi = DB::table('N_HRIS_Api_Aplikasi')
            ->where('Id_Api_Aplikasi', $this->idAplikasi)->value('Nama_Aplikasi');

        $no = $this->ajukan(ubah: ['pic_tujuan' => null])->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            $namaAplikasi,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
                ->where('No_Transaksi', $no)->value('Keterangan_Tujuan_Detail'),
        );
    }

    public function test_payload_tanpa_field_wajib_ditolak(): void
    {
        $this->ajukan(ubah: ['nama_tamu' => null])->assertStatus(422);
        $this->ajukan(ubah: ['tanggal_kunjungan' => 'bukan-tanggal'])->assertStatus(422);
        // Estimasi keluar tidak boleh sebelum estimasi masuk.
        $this->ajukan(ubah: ['estimasi_keluar' => '2026-09-20T07:00:00'])->assertStatus(422);
        // Estimasi berpasangan: satu sisi saja ditolak.
        $this->ajukan(ubah: ['estimasi_keluar' => null])->assertStatus(422);
        // Tanpa estimasi DAN tanpa tanggal tidak ada yang bisa dijadwalkan.
        $this->ajukan(ubah: ['estimasi_masuk' => null, 'estimasi_keluar' => null])->assertStatus(422);
    }

    public function test_estimasi_tersimpan_dan_menentukan_tanggal_serta_kategori(): void
    {
        // DDL-2026-121: estimasi menjadi Waktu_Mulai/Selesai_Kunjungan,
        // Tanggal_Kunjungan diturunkan dari Estimasi Masuk, dan rentang
        // lebih dari 24 jam tercatat MITRA.
        $no = $this->ajukan(ubah: [
            'estimasi_masuk' => '2026-09-21T08:00:00',
            'estimasi_keluar' => '2026-09-23T17:00:00',
        ])->assertStatus(201)->json('result.no_transaksi');

        $header = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->first();
        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $no)->first();

        $this->assertSame('2026-09-21', substr((string) $header->Tanggal_Kunjungan, 0, 10));
        $this->assertSame('2026-09-21 08:00:00', substr((string) $detail->Waktu_Mulai_Kunjungan, 0, 19));
        $this->assertSame('2026-09-23 17:00:00', substr((string) $detail->Waktu_Selesai_Kunjungan, 0, 19));
        $this->assertSame(TipeTamu::KATEGORI_MITRA, $detail->Tamu_Kategori);
    }

    public function test_payload_lengkap_tidak_membawa_peringatan(): void
    {
        $this->ajukan()
            ->assertStatus(201)
            ->assertJsonPath('result.peringatan', []);
    }

    public function test_transisi_payload_lama_tetap_diterima_dengan_peringatan(): void
    {
        // Masa transisi (bawaan): pengajuan lama yang hanya bertanggal masih
        // diterima dan dianggap berlangsung seharian, tetapi mitra diberi tahu
        // apa yang nanti wajib.
        config(['services.visitor.tamu_b2b_api.wajib_lengkap' => false]);

        $isi = $this->ajukan(ubah: [
            'estimasi_masuk' => null,
            'estimasi_keluar' => null,
            'tanggal_kunjungan' => '2026-09-20',
        ])->assertStatus(201)->json('result');

        $this->assertNotEmpty($isi['peringatan']);
        $this->assertStringContainsString('estimasi_masuk', implode(' ', $isi['peringatan']));

        $detail = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $isi['no_transaksi'])->first();
        $this->assertSame('2026-09-20 00:00:00', substr((string) $detail->Waktu_Mulai_Kunjungan, 0, 19));
        $this->assertSame('2026-09-21 00:00:00', substr((string) $detail->Waktu_Selesai_Kunjungan, 0, 19));
        $this->assertSame(TipeTamu::KATEGORI_VISITOR, $detail->Tamu_Kategori);
    }

    public function test_transisi_tanpa_kendaraan_diterima_dengan_peringatan(): void
    {
        config(['services.visitor.tamu_b2b_api.wajib_lengkap' => false]);

        $peringatan = implode(' ', $this->ajukan(ubah: [
            'jenis_kendaraan' => null,
            'no_plat_kendaraan' => null,
        ])->assertStatus(201)->json('result.peringatan'));

        $this->assertStringContainsString('jenis_kendaraan', $peringatan);
        $this->assertStringContainsString('no_plat_kendaraan', $peringatan);
    }

    public function test_mode_wajib_menolak_payload_lama(): void
    {
        config(['services.visitor.tamu_b2b_api.wajib_lengkap' => true]);

        $this->ajukan(ubah: [
            'estimasi_masuk' => null,
            'estimasi_keluar' => null,
            'tanggal_kunjungan' => '2026-09-20',
        ])->assertStatus(422);

        $this->ajukan(ubah: ['no_plat_kendaraan' => null])->assertStatus(422);
        $this->ajukan(ubah: ['jenis_kendaraan' => null])->assertStatus(422);
    }

    public function test_mode_wajib_menolak_tamu_b2b_tanpa_kendaraan(): void
    {
        // Lolos FormRequest (field terisi) tetapi melanggar aturan tipe di
        // penulis: tamu B2B wajib berkendaraan.
        config(['services.visitor.tamu_b2b_api.wajib_lengkap' => true]);

        $this->ajukan(ubah: ['jenis_kendaraan' => 'Tidak Berkendara'])->assertStatus(422);
    }

    public function test_mode_wajib_menerima_payload_lengkap_tanpa_peringatan(): void
    {
        config(['services.visitor.tamu_b2b_api.wajib_lengkap' => true]);

        $this->ajukan()
            ->assertStatus(201)
            ->assertJsonPath('result.peringatan', []);
    }

    public function test_pengajuan_mitra_mengabari_antrean_tamu_b2b(): void
    {
        // DDL-2026-121: tamu mitra selalu B2B, jadi pengajuannya masuk antrean
        // ADMIN_TAMU_B2B, bukan antrean tamu Regular.
        config(['services.visitor.tamu_b2b.needs_approval' => true]);
        Bus::fake([PublishVisitorNotificationsJob::class]);

        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        Bus::assertDispatched(PublishVisitorNotificationsJob::class, function (PublishVisitorNotificationsJob $job) use ($no) {
            $peristiwa = collect($job->events)->firstWhere('no_transaksi', $no);

            return ($peristiwa['domain'] ?? null) === VisitorNotificationService::DOMAIN_TAMU_B2B;
        });
    }

    public function test_pengajuan_mitra_masuk_pool_tamu_b2b(): void
    {
        // Butuh data-change DDL-2026-121 (config TAMU_B2B + role ADMIN_TAMU_B2B).
        config(['services.visitor.tamu_b2b.needs_approval' => true]);

        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $this->assertSame(
            StatusKunjunganTamu::PENDING_APPROVAL,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->value('Status_Kunjungan'),
        );

        $pool = DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ar.Group_Reference_Id')
            ->where('ar.No_Transaksi', $no)
            ->distinct()
            ->pluck('rf.Role_Code')
            ->all();

        $this->assertSame(['ADMIN_TAMU_B2B'], $pool);
    }

    public function test_status_kunjungan_sendiri_terbaca(): void
    {
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $isi = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json();

        $this->assertSame($no, $isi['result']['no_transaksi']);
        $this->assertSame('Rudi Mitra', $isi['result']['nama_tamu']);

        // Tanggalnya dibalas sebagai TANGGAL, senada dengan berlaku_dari di
        // bawah. Kolomnya datetime, dan membalas '2026-09-20 00:00:00.000' di
        // sebelah '2026-09-20' memaksa mitra menebak dua bentuk dalam satu
        // respons.
        $this->assertSame('2026-09-20', $isi['result']['tanggal_kunjungan']);

        if ($this->butuhApproval()) {
            $this->assertSame('PENDING', $isi['result']['status']);
            $this->assertSame('PENDING', $isi['result']['status_kunjungan']);
            // Permit COKLAT baru DIAJUKAN setelah kunjungannya disetujui final.
            $this->assertNull($isi['result']['permit_coklat']);

            return;
        }

        // Tanpa approval kunjungan, kunjungannya langsung terjadwal — tetapi
        // izin gerbangnya TIDAK. Di sinilah `status` harus menolak ikut
        // APPROVED: mitra yang membacanya akan mengirim tamunya, dan palang
        // menahannya. Keadaan kunjungannya tetap terbaca, di kunci tersendiri.
        $this->assertSame('PENDING', $isi['result']['status']);
        $this->assertSame('APPROVED', $isi['result']['status_kunjungan']);

        $permit = $isi['result']['permit_coklat'];

        $this->assertNotNull($permit, 'Tamu B2B disetujui tanpa pengajuan permit COKLAT.');
        $this->assertSame('PENDING', $permit['status'], 'Izin gerbang terbit tanpa persetujuan siapa pun.');
        $this->assertStringStartsWith('PMT', (string) $permit['no_transaksi']);
        $this->assertSame('1 Hari', $permit['durasi']);
        $this->assertSame('2026-09-20', substr((string) $permit['berlaku_dari'], 0, 10));
        $this->assertSame('2026-09-20', substr((string) $permit['berlaku_sampai'], 0, 10));
    }

    public function test_kiriman_bersamaan_yang_kalah_di_indeks_unik_dijawab_sebagai_replay(): void
    {
        // Dua kiriman berkunci sama yang sama-sama lolos pemeriksaan replay:
        // yang kalah menabrak indeks unik idempotensi. Dulu 500; kini replay 200.
        // Mock dipasang SEBELUM permintaan pertama (route menyimpan controller).
        $asli = app(\App\Services\VISITOR\Guest\Visit\RegistrasiTamuService::class);
        $cari = 0;
        $simpan = 0;
        $this->mock(\App\Services\VISITOR\Guest\Visit\RegistrasiTamuService::class, function ($m) use ($asli, &$cari, &$simpan) {
            $m->shouldReceive('cariIdempoten')->andReturnUsing(function (...$a) use ($asli, &$cari) {
                return ++$cari === 2 ? null : $asli->cariIdempoten(...$a);
            });
            $m->shouldReceive('simpan')->andReturnUsing(function (...$a) use ($asli, &$simpan) {
                if (++$simpan === 2) {
                    $pdo = new \PDOException('duplicate key');
                    $pdo->errorInfo = ['23000', 2601, 'duplicate key'];
                    throw new \Illuminate\Database\QueryException('sqlsrv', 'insert', [], $pdo);
                }

                return $asli->simpan(...$a);
            });
        });

        $kunci = 'IDEM-'.Str::upper(Str::random(20));
        $pertama = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result.no_transaksi');
        $kedua = $this->ajukan(kunci: $kunci)->assertStatus(200);

        $this->assertSame($pertama, $kedua->json('result.no_transaksi'));
        $this->assertTrue($kedua->json('result.idempotent_replay'));
    }

    public function test_kunjungan_yang_jendelanya_berakhir_tidak_lagi_approved(): void
    {
        // APPROVED berarti tamu boleh diberangkatkan; kunjungan yang sudah
        // berakhir (proyeksi EXPIRED) dulu tetap APPROVED selamanya.
        $no = DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->join('N_VISITOR_Tamu_Efektif as e', 'e.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')->where('h.Flag_Approval', 'Y')->where('e.Status_Efektif', 'EXPIRED')
            ->value('h.No_Transaksi');
        if (! $no) {
            $this->markTestSkipped('Tidak ada kunjungan berproyeksi EXPIRED di DB uji.');
        }

        $isi = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json();

        $this->assertSame('EXPIRED', $isi['result']['status_kunjungan']);
        $this->assertSame('EXPIRED', $isi['result']['status']);
    }

    public function test_kosakata_internal_tidak_bocor_ke_mitra(): void
    {
        // Status_Kunjungan HCIS bernilai 'PENDING_APPROVAL' (DDL-2026-121; dulu
        // 'MENUNGGU_APPROVAL') — kosakata internal yang tidak pernah dijanjikan
        // ke mitra. Membocorkannya memaksa mereka menangani nilai tak
        // terdokumentasi dan mengunci kita dari menggantinya — dan kolom itu
        // memang baru saja berganti kosakata tanpa kontrak mitra ikut berubah.
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $this->assertContains(
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $no)->value('Status_Kunjungan'),
            [StatusKunjunganTamu::PENDING_APPROVAL, StatusKunjunganTamu::SCHEDULED],
            'Prasyarat berubah: kosakata internal Status_Kunjungan tidak lagi seperti yang diuji.',
        );

        $hasil = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json('result');
        $status = $hasil['status'];

        $this->assertContains(
            $hasil['status_kunjungan'],
            ['PENDING', 'APPROVED', 'REJECTED', 'REVOKED'],
            'status_kunjungan memakai kosakata di luar yang dijanjikan kontrak.',
        );

        // `status` kini GABUNGAN, sehingga EXPIRED ikut mungkin: izin gerbang
        // yang kedaluwarsa adalah alasan sah tamunya tidak boleh masuk.
        $this->assertContains($status, ['PENDING', 'APPROVED', 'REJECTED', 'REVOKED', 'EXPIRED']);
    }

    public function test_balasan_pengajuan_membawa_nomor_permitnya(): void
    {
        // Mitra memantau DUA dokumen: kunjungannya dan izin gerbangnya. Tanpa
        // nomor permit di balasan ini, satu-satunya cara menemukannya adalah
        // polling endpoint status — dan mitra yang belum melakukannya tidak
        // punya apa pun untuk dilacak.
        $isi = $this->ajukan()->assertStatus(201)->json('result');

        $this->assertArrayHasKey('no_transaksi_permit', $isi);

        if ($this->butuhApproval()) {
            // Permitnya baru diajukan setelah kunjungannya disetujui final.
            $this->assertNull($isi['no_transaksi_permit']);

            return;
        }

        $this->assertStringStartsWith('PMT', (string) $isi['no_transaksi_permit']);
        $this->assertSame(
            $isi['no_transaksi'],
            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $isi['no_transaksi_permit'])
                ->value('No_Transaksi_Tamu'),
            'Nomor permit yang dipulangkan bukan milik kunjungan ini.',
        );
    }

    public function test_kiriman_ulang_memulangkan_nomor_permit_yang_sama(): void
    {
        $kunci = 'API-'.Str::upper(Str::random(20));

        $pertama = $this->ajukan(kunci: $kunci)->assertStatus(201)->json('result');
        $ulang = $this->ajukan(kunci: $kunci)->assertStatus(200)->json('result');

        $this->assertTrue($ulang['idempotent_replay']);
        $this->assertSame($pertama['no_transaksi'], $ulang['no_transaksi']);
        $this->assertSame($pertama['no_transaksi_permit'], $ulang['no_transaksi_permit']);
    }

    /**
     * `status` menjawab "boleh masuk", bukan "kunjungannya tercatat".
     *
     * Ini pembeda yang membuat mitra mengirim tamunya terlalu cepat: registrasi
     * auto-approve sementara izin gerbangnya menunggu verifikator, sehingga dua
     * status yang masing-masing benar tetap menghasilkan satu kesimpulan yang
     * salah — dan tidak ada galat apa pun yang menandainya.
     */
    public function test_status_gabungan_mengikuti_izin_gerbang_setelah_kunjungan_disetujui(): void
    {
        if ($this->butuhApproval()) {
            $this->markTestSkipped('Kunjungan masih butuh approval; izin gerbangnya belum diajukan.');
        }

        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');
        $noPermit = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi_Tamu', $no)
            ->value('No_Transaksi');

        $this->assertNotNull($noPermit, 'Permit COKLAT tidak pernah diajukan.');

        // Izinnya disetujui — barulah tamunya benar-benar boleh masuk.
        DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noPermit)
            ->update(['Flag_Approval' => 'Y']);
        // Jendelanya dibuat masih berlaku: tanggal payload dipaku (20 Sep 2026),
        // dan sejak status izin dibaca dari permitStatusSql (audit H5) izin yang
        // jendelanya sudah lewat dengan benar terbaca EXPIRED, bukan APPROVED.
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')
            ->where('No_Transaksi', $noPermit)
            ->update([
                'Tanggal_Mulai_Berlaku' => now()->toDateString(),
                'Tanggal_Kadaluarsa' => now()->addDay()->toDateString(),
                'Waktu_Mulai_Berlaku' => now()->subHour(),
                'Waktu_Berakhir' => now()->addDay(),
            ]);

        $hasil = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json('result');

        $this->assertSame('APPROVED', $hasil['status']);
        $this->assertSame('APPROVED', $hasil['status_kunjungan']);

        // Dan sebaliknya: izin yang DITOLAK menutup pintu, walau kunjungannya
        // sendiri tetap tercatat disetujui.
        DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $noPermit)
            ->update(['Flag_Approval' => 'T']);

        $hasil = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json('result');

        $this->assertSame('REJECTED', $hasil['status']);
        $this->assertSame('APPROVED', $hasil['status_kunjungan']);
    }

    public function test_status_izin_gerbang_hanya_memakai_kosakata_yang_dijanjikan(): void
    {
        // permit_coklat.status adalah kontrak kedua pada balasan ini, dan
        // nilainya diturunkan dari DUA kolom internal sekaligus. Nilai internal
        // yang bocor ('AKTIF', 'MENUNGGU_APPROVAL') memaksa mitra menangani
        // kosakata yang tidak pernah didokumentasikan.
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        $permit = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json('result.permit_coklat');

        if ($permit === null) {
            $this->assertTrue($this->butuhApproval(), 'Kunjungan disetujui tetapi permitnya tidak pernah diajukan.');

            return;
        }

        $this->assertContains($permit['status'], ['PENDING', 'APPROVED', 'REJECTED', 'REVOKED', 'EXPIRED']);
    }

    /**
     * Pembacaan status SENGAJA tidak disaring per aplikasi (keputusan user).
     *
     * Dulu nomor milik mitra lain dibalas 404. Pelonggarannya disengaja dan
     * karena itu dijaga di sini: kalau suatu saat penyaring kepemilikan
     * dipasang kembali "demi keamanan", uji ini yang harus dibaca lebih dulu —
     * bukan ditemukan oleh mitra yang endpointnya mendadak berhenti menjawab.
     *
     * Yang HILANG karenanya diterima sadar: siapa pun yang memegang kredensial
     * API sah dan menebak nomor kunjungan dapat membaca nama tamu, instansi,
     * tanggal, dan referensi_mitra milik pelanggan lain.
     */
    public function test_kunjungan_mitra_lain_ikut_terbaca(): void
    {
        $no = $this->ajukan()->assertStatus(201)->json('result.no_transaksi');

        SuntikAplikasiApi::$idAplikasi = $this->buatAplikasi(self::PIC);

        $hasil = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json('result');

        $this->assertSame($no, $hasil['no_transaksi']);
        // Pemanggil tidak lagi bisa menyimpulkan bahwa 200 berarti miliknya
        // sendiri, jadi asal pendaftarannya wajib ikut dibalas.
        $this->assertSame('API', $hasil['sumber']);
    }

    public function test_registrasi_tamu_dari_layar_hcis_juga_terbaca(): void
    {
        // Registrasi dari layar HCIS tidak punya Id_Api_Aplikasi sama sekali.
        // Sebelum dilonggarkan, ia dibalas "tidak ditemukan" untuk nomor yang
        // jelas-jelas ada — dan itu yang membuat pemakainya menyangka datanya
        // hilang.
        $no = 'VSR-HCIS-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $no,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::PENDING_APPROVAL,
            'Needs_Approval' => 'Y',
            'Flag_Approval' => null,
            'Tanggal_Kunjungan' => '2026-09-20',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Sumber_Pengajuan' => 'HCIS',
            'Idempotency_Key' => 'HCIS-'.Str::upper(Str::random(20)),
        ]);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $no,
            'Nama_Lengkap_Tamu' => 'Tamu HCIS',
            'Tipe_Tamu' => 'REGULAR',
            'Jumlah_Tamu' => 1,
        ]);

        $hasil = $this->getJson(self::URL.'/'.$no)->assertStatus(200)->json('result');

        $this->assertSame('Tamu HCIS', $hasil['nama_tamu']);
        $this->assertSame('HCIS', $hasil['sumber']);
        // Tamu REGULAR tidak berpermit; ketiadaan izin di situ bukan penahanan,
        // jadi status-nya dinilai dari kunjungannya sendiri.
        $this->assertNull($hasil['permit_coklat']);
        $this->assertSame('PENDING', $hasil['status']);
        $this->assertSame('PENDING', $hasil['status_kunjungan']);
    }

    public function test_nomor_yang_memang_tidak_ada_tetap_dibalas_404(): void
    {
        // Pelonggarannya soal KEPEMILIKAN, bukan soal keberadaan. Tanpa penjaga
        // ini, "tidak disaring" mudah berubah jadi "tidak diperiksa".
        $this->getJson(self::URL.'/VSR-TIDAK-ADA-'.Str::upper(Str::random(6)))
            ->assertStatus(404);
    }

    /**
     * Kebijakan approval tamu adalah SETELAN, bukan tetapan.
     *
     * Di lingkungan yang menyalakannya, registrasi berhenti di antrean
     * verifikator; di lingkungan yang mematikannya, ia langsung terjadwal dan
     * permit COKLAT terbit seketika. Uji yang memakukan salah satunya akan
     * merah karena setelan, bukan karena cacat.
     *
     * Tamu mitra selalu B2B, jadi setelannya 'tamu_b2b' (DDL-2026-121; bawaan
     * menyala), bukan 'tamu' milik Regular.
     */
    private function butuhApproval(): bool
    {
        return (bool) app(VisitorApprovalPolicyService::class)->config('tamu_b2b')['needs_approval'];
    }

    /** @param array<string,mixed> $ubah */
    private function ajukan(array $ubah = [], ?string $kunci = null): TestResponse
    {
        // array_merge, bukan '+': nilai null yang sengaja dikirim harus MENIMPA
        // nilai baku, bukan diabaikan.
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
            // Estimasi Masuk/Keluar (DDL-2026-121). Tanggal kunjungannya
            // diturunkan dari estimasi_masuk: 2026-09-20.
            'estimasi_masuk' => '2026-09-20T08:00:00',
            'estimasi_keluar' => '2026-09-20T16:00:00',
            // Dua hal yang berbeda, dan kaburnya keduanya dulu membuat mitra
            // mengisi pic_tujuan dengan nama lokasi: pic_tujuan = KE MANA,
            // penanggung_jawab = SIAPA yang bertanggung jawab (wajib sejak
            // DDL-2026-135, sebab pintu ini tidak punya karyawan pengaju).
            'pic_tujuan' => 'Ruang Server Lantai 2',
            'penanggung_jawab' => 'Budi Santoso',
            'jenis_kendaraan' => 'Mobil',
            'no_plat_kendaraan' => 'BG 1234 MT',
            'referensi_mitra' => 'GUEST-'.Str::random(6),
        ];
    }

    private function buatAplikasi(?string $pic): int
    {
        return (int) DB::table('N_HRIS_Api_Aplikasi')->insertGetId([
            'Id_Grup_Fitur' => 4,
            'Nama_Aplikasi' => 'Uji Mitra Tamu '.Str::upper(Str::random(6)),
            'Api_Public' => 'pub-'.Str::random(20),
            'Api_Secret' => 'sec-'.Str::random(30),
            'Arah' => 'IN',
            'Kode_Karyawan_PIC' => $pic,
            'Created_At' => now(),
            'Flag_Aktif' => 'Y',
        ]);
    }
}
