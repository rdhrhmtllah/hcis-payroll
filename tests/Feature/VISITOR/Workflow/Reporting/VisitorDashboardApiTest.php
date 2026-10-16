<?php

namespace Tests\Feature\VISITOR\Workflow\Reporting;

use App\Models\ESS\Employee\Models\User;
use App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardOperasionalService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Kontrak HTTP read-only, aman dijalankan pada database development bersama. */
class VisitorDashboardApiTest extends TestCase
{
    private const BASE = 'http://localhost/api/v1/visitor/dashboard';

    private function admin(): User
    {
        $userId = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')
            ->where('Jenis_page', 'visitorLaporanPage')
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        $user = $userId ? User::find($userId) : null;
        if (! $user) {
            $this->markTestSkipped('Tak ada user aktif dengan visitorLaporanPage di database development.');
        }

        return $user;
    }

    public function test_tanpa_autentikasi_ditolak(): void
    {
        $this->getJson(self::BASE.'/sekarang')->assertStatus(401);
    }

    public function test_sekarang_punya_kontrak_stabil_walau_data_kosong(): void
    {
        $this->actingAs($this->admin())
            ->getJson(self::BASE.'/sekarang')
            ->assertOk()
            ->assertJsonStructure([
                'result' => [
                    'di_dalam_area' => ['orang', 'transaksi', 'overdue', 'daftar_perlu_ditutup', 'daftar_perlu_ditutup_total'],
                    'terjadwal_belum_datang' => ['orang', 'transaksi'],
                    'antrian' => ['pending', 'umur', 'kadaluarsa', 'daftar', 'daftar_total'],
                    'dihitung_pada',
                ],
            ]);
    }

    /**
     * Penjaga sumber data kehadiran — DIBALIK pada 2026-08-25.
     *
     * Versi sebelumnya menuntut kebalikannya: keadaan area TIDAK BOLEH membaca
     * N_VISITOR_Tamu_Efektif, "karena tabel itu tidak pernah ditulis oleh
     * check-in/check-out". Premis itu setengah benar. Check-in/check-out HCIS
     * memang tidak menulisnya — tetapi APLIKASI PALANG di gerbang menulis
     * Waktu_Aktual_Masuk/Keluar langsung ke sana, dan syncTamuEfektif sengaja
     * melewati kedua kolom itu. Akibat penjaga lama, keadaan area buta terhadap
     * setiap orang yang benar-benar melewati gerbang.
     *
     * Yang dijaga sekarang adalah aturan yang benar: kehadiran dibaca dari
     * baris efektif dengan kolom detail sebagai cadangan, dan TIDAK PERNAH dari
     * Status_Kunjungan — kolom itu status daur hidup pengajuan, bukan kehadiran.
     */
    public function test_kueri_keadaan_area_membaca_kehadiran_dari_tabel_efektif(): void
    {
        $sql = app(VisitorDashboardOperasionalService::class)->queryDiDalamArea()->toSql();

        $this->assertStringContainsString('N_EMI_VISITOR_Transaksi_Tamu', $sql);
        $this->assertStringContainsString('N_EMI_VISITOR_Transaksi_Tamu_Detail', $sql);
        $this->assertStringContainsString('N_VISITOR_Tamu_Efektif', $sql);
        $this->assertStringNotContainsString('Status_Kunjungan] = ', $sql);
    }

    /** Kolom teks bebas tidak boleh menyusup jadi jam jatuh tempo. */
    public function test_kueri_tidak_menyentuh_waktu_estimasi(): void
    {
        $sql = app(VisitorDashboardOperasionalService::class)->queryDiDalamArea()->toSql();

        $this->assertStringNotContainsString('Waktu_Estimasi_Kunjungan', $sql);
    }

    /** Setiap baris antrian membawa identitas, bukan hanya nomor transaksi. */
    public function test_daftar_antrian_membawa_identitas(): void
    {
        $daftar = $this->actingAs($this->admin())
            ->getJson(self::BASE.'/sekarang?segarkan=1')
            ->assertOk()
            ->json('result.antrian.daftar');

        if ($daftar === []) {
            $this->markTestSkipped('Tak ada antrian approval visitor di database development.');
        }

        foreach ($daftar as $baris) {
            $this->assertArrayHasKey('jenis', $baris);
            $this->assertContains($baris['jenis'], ['TAMU', 'PERMIT']);
            $this->assertArrayHasKey('judul', $baris);
            $this->assertArrayHasKey('pemohon', $baris);
            $this->assertArrayHasKey('approver', $baris);
        }
    }

    public function test_analitik_punya_kontrak_stabil(): void
    {
        $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=2026-08-01&sampai=2026-08-24')
            ->assertOk()
            ->assertJsonStructure([
                'result' => [
                    'tren', 'sebaran_jam', 'permit_kategori', 'permit_tren',
                    'kesehatan_data' => ['total', 'sudah_masuk', 'tanpa_waktu_keluar', 'tanpa_identitas', 'tanpa_hp', 'persen_tanpa_waktu_keluar'],
                    'dihitung_pada',
                ],
            ]);
    }

    public function test_analitik_menyaring_ranah_di_server(): void
    {
        // Bukan sekadar tidak dirender: blok ranah tetangga tidak boleh ikut
        // dihitung, karena keduanya sama-sama menyapu tabel transaksi dan
        // separuh biayanya akan terbuang untuk muatan yang dibuang.
        $tamu = $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=2026-08-01&sampai=2026-08-24&domain=tamu')
            ->assertOk()
            ->json('result');

        $permit = $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=2026-08-01&sampai=2026-08-24&domain=permit')
            ->assertOk()
            ->json('result');

        $this->assertArrayHasKey('tren', $tamu);
        $this->assertArrayNotHasKey('permit_tren', $tamu);
        $this->assertArrayNotHasKey('permit_kategori', $tamu);

        $this->assertArrayHasKey('permit_tren', $permit);
        $this->assertArrayNotHasKey('tren', $permit);
        $this->assertArrayNotHasKey('sebaran_jam', $permit);
    }

    public function test_tren_permit_mengirim_hari_kosong_sebagai_nol(): void
    {
        // Kalau hari tanpa peristiwa dilewati, sumbu waktunya merapat dan dua
        // pengajuan berjarak sepekan terbaca seolah terjadi berturut-turut.
        $tren = $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=2026-08-12&sampai=2026-08-25&domain=permit')
            ->assertOk()
            ->json('result.permit_tren');

        $this->assertCount(14, $tren, 'Jumlah titik harus sama dengan jumlah hari pada rentang.');
        $this->assertSame('2026-08-12', $tren[0]['tanggal']);
        $this->assertSame('2026-08-25', $tren[13]['tanggal']);

        foreach ($tren as $titik) {
            foreach (['diajukan', 'disetujui', 'ditolak'] as $seri) {
                $this->assertIsInt($titik[$seri]);
            }
        }
    }

    public function test_penolakan_ditanggalkan_pada_hari_keputusan_bukan_pengajuan(): void
    {
        // Permit yang ditolak TIDAK punya Approved_At; hanya Updated_At yang
        // terisi. Menyamakan ketiga peristiwa ke satu kolom tanggal akan
        // menaruh penolakan pada hari pengajuannya.
        $service = app(\App\Services\VISITOR\Workflow\Reporting\Dashboard\VisitorDashboardAnalitikService::class);

        $ditolak = \Illuminate\Support\Facades\DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'T')
            ->whereNotNull('Updated_At')
            ->orderByDesc('Updated_At')
            ->first();

        if (! $ditolak) {
            $this->markTestSkipped('Tidak ada permit yang ditolak pada basis data ini.');
        }

        $hari = substr((string) $ditolak->Updated_At, 0, 10);
        $tren = collect($service->permitTren($hari, $hari))->firstWhere('tanggal', $hari);

        $this->assertGreaterThan(0, $tren['ditolak'], "Penolakan tidak terhitung pada {$hari}.");
    }

    /** Rentang kosong bukan error: blok kosong, tetap 200. */
    public function test_rentang_tanpa_data_tetap_berhasil(): void
    {
        $result = $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=1990-01-01&sampai=1990-01-02')
            ->assertOk()
            ->json('result');

        $this->assertSame([], $result['tren']);
        $this->assertSame(0, $result['kesehatan_data']['total']);
        // JSON menormalkan 0.0 menjadi 0, jadi bandingkan nilainya, bukan tipenya.
        $this->assertEqualsWithDelta(0, $result['kesehatan_data']['persen_tanpa_waktu_keluar'], 0.001);
    }

    /**
     * stats laporan tamu dibaca dari sumber lifecycle yang benar (header +
     * detail), bukan dari salinan efektif yang tak pernah ditulis check-out.
     *
     * Sebelum perbaikan kedua angka ini selalu 0. Penjaga yang menggigit di
     * sini adalah `expired`, karena `sudah_keluar` masih 0 di kedua sisi
     * selama belum ada tamu yang check-out di database development.
     */
    public function test_stats_laporan_tamu_konsisten_dengan_sumber_lifecycle(): void
    {
        $dari = '2026-01-01';
        $sampai = '2026-12-31';

        $stats = $this->actingAs($this->admin())
            ->getJson("http://localhost/laporanvisitor-tamu?date_from={$dari}&date_to={$sampai}")
            ->assertOk()
            ->json('stats');

        // DDL-2026-121: /laporanvisitor-tamu adalah rute Regular, jadi stats-nya
        // hanya menghitung baris Regular (NULL = warisan Regular).
        $base = fn () => DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->join('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->whereRaw("(d.Tipe_Tamu IS NULL OR d.Tipe_Tamu = 'REGULAR')")
            ->whereRaw('h.Tanggal_Kunjungan >= ?', [$dari])
            ->whereRaw('h.Tanggal_Kunjungan < DATEADD(day, 1, CAST(? AS date))', [$sampai]);

        // DIPERBAIKI 2026-08-25. Versi sebelumnya menghitung harapannya dari
        // kolom Detail saja, lalu menuntut endpoint mengikutinya — sehingga
        // justru mengunci kebutaan terhadap aplikasi palang, yang menulis
        // kehadiran ke N_VISITOR_Tamu_Efektif dan tidak pernah menyentuh Detail.
        //
        // SQL di bawah sengaja ditulis tangan, tidak memanggil helper yang
        // dipakai implementasi: uji yang meminjam kode yang diujinya akan hijau
        // apa pun yang terjadi.
        $gerbang = fn () => $base()->leftJoin(
            'N_VISITOR_Tamu_Efektif as e',
            'e.No_Transaksi',
            '=',
            'h.No_Transaksi',
        );

        $sudahKeluar = $gerbang()
            ->whereRaw('(e.Waktu_Aktual_Keluar IS NOT NULL OR d.Waktu_Aktual_Keluar IS NOT NULL)')
            ->count();
        // DDL-2026-121: 'expired' = status tampil CANCELLED — tanpa masuk dan
        // keluar, Estimasi Keluar (fallback Tanggal_Kunjungan + 1 hari) sudah
        // lewat, dan bukan keputusan akhir tersimpan / proyeksi yang dicabut.
        $tidakDatang = $gerbang()
            ->whereRaw('(e.Waktu_Aktual_Masuk IS NULL AND d.Waktu_Aktual_Masuk IS NULL)')
            ->whereRaw('(e.Waktu_Aktual_Keluar IS NULL AND d.Waktu_Aktual_Keluar IS NULL)')
            ->whereRaw('COALESCE(d.Waktu_Selesai_Kunjungan, DATEADD(day, 1, CAST(h.Tanggal_Kunjungan AS datetime2(0)))) < SYSDATETIME()')
            ->whereRaw("(h.Status_Kunjungan IS NULL OR h.Status_Kunjungan NOT IN ('PENDING_APPROVAL', 'REJECTED', 'WITHDRAWN', 'REVOKED'))")
            ->whereRaw("(e.Status_Efektif IS NULL OR e.Status_Efektif <> 'DIBATALKAN')")
            ->count();

        $this->assertSame($sudahKeluar, (int) $stats['sudah_keluar']);
        $this->assertSame($tidakDatang, (int) $stats['expired']);
    }

    /** Rentang terbalik atau tak sah dirapikan, bukan diteruskan ke SQL. */
    public function test_rentang_tidak_sah_dirapikan(): void
    {
        $this->actingAs($this->admin())
            ->getJson(self::BASE.'/analitik?dari=bukan-tanggal&sampai=')
            ->assertOk();
    }
}
