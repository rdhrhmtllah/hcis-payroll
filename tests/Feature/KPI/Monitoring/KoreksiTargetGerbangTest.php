<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Services\KPI\KpiTargetKoreksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang kelayakan koreksi target.
 *
 * Target hanya boleh dikoreksi bila belum dinilai DAN belum ada drafnya.
 * Syarat draf itu bukan kehati-hatian berlebih: finalisasi menyalin Score dari
 * draf tanpa menghitung ulang, sehingga draf yang dibiarkan hidup akan membawa
 * achievement yang dihitung terhadap target LAMA ke record final — tanpa error
 * dan tanpa gejala.
 */
class KoreksiTargetGerbangTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): KpiTargetKoreksiService
    {
        return app(KpiTargetKoreksiService::class);
    }

    /** Target hidup: pengajuan aktif+disetujui, tanpa final, tanpa draf. */
    private function targetHidup(): ?object
    {
        return DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail as upd')
                ->whereColumn('upd.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail_Sementara as s')
                ->whereColumn('s.Id_Pengajuan_Det', 'det.Id_Pengajuan_Det'))
            ->orderByDesc('det.Id_Pengajuan_Det')
            ->select('det.Id_Pengajuan_Det', 'det.Nilai_Target', 'p.Id_Pengajuan')
            ->first();
    }

    public function test_target_bersih_boleh_dikoreksi(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det]);

        $this->assertTrue($hasil[(int) $t->Id_Pengajuan_Det]['boleh']);
        $this->assertSame('BOLEH', $hasil[(int) $t->Id_Pengajuan_Det]['kode']);
    }

    public function test_ditolak_ketika_draf_ada_dan_menyebut_pemegangnya(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $karyawan = DB::table('Karyawan')->whereNotNull('Nama')->first();
        $header = DB::table('KPI_Update_Performance_Sementara')
            ->orderByDesc('Id_Update_Performance_Sementara')
            ->first();

        if (! $karyawan || ! $header) {
            $this->markTestSkipped('Data dev tidak punya karyawan/header draf untuk disandarkan.');
        }

        // Header terbaru bisa saja sudah dipindah atau dibatalkan; draf di
        // bawah header seperti itu memang tidak mengunci.
        DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Update_Performance_Sementara', $header->Id_Update_Performance_Sementara)
            ->update(['Flag_Aktif' => 'Y', 'Flag_Pindah' => null]);

        DB::table('KPI_Update_Performance_Detail_Sementara')->insert([
            'Id_Update_Performance_Sementara' => $header->Id_Update_Performance_Sementara,
            'Id_Pengajuan_Det' => $t->Id_Pengajuan_Det,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nilai_Actual' => 1,
            'Score' => 1,
            'Achievement' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det])[(int) $t->Id_Pengajuan_Det];

        $this->assertFalse($hasil['boleh']);
        $this->assertSame('ADA_DRAF', $hasil['kode']);
        $this->assertSame(
            $karyawan->Nama,
            $hasil['pemegang_draf'],
            'Penolakan ADA_DRAF harus menyebut pemegangnya; tanpa itu admin hanya melihat tombol mati dan tak tahu siapa yang dikejar.',
        );
    }

    public function test_ditolak_ketika_sudah_ada_final(): void
    {
        $sudahFinal = DB::table('KPI_Update_Performance_Detail as upd')
            ->join('KPI_Update_Performance as up', 'up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
            ->where('up.Flag_Aktif', 'Y')
            ->where('up.Flag_Approval', 'Y')
            ->whereNull('up.Status')
            ->orderByDesc('upd.Id_Update_Performance_Detail')
            ->value('upd.Id_Pengajuan_Det');

        if (! $sudahFinal) {
            $this->markTestSkipped('Tidak ada detail final yang hidup di data dev.');
        }

        $hasil = $this->svc()->periksaKelayakan([(int) $sudahFinal])[(int) $sudahFinal];

        $this->assertFalse($hasil['boleh']);
        $this->assertSame('SUDAH_FINAL', $hasil['kode']);
    }

    /**
     * Satu target yang hanya punya SATU baris final di seluruh riwayatnya,
     * tanpa draf, pada pengajuan parameter yang hidup. Dengan begitu nasib
     * target itu sepenuhnya ditentukan oleh flag header yang diubah uji.
     */
    private function targetBerfinalTunggal(): ?object
    {
        return DB::table('KPI_Update_Performance_Detail as upd')
            ->join('KPI_Pengajuan_Det as det', 'det.Id_Pengajuan_Det', '=', 'upd.Id_Pengajuan_Det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail as lain')
                ->whereColumn('lain.Id_Pengajuan_Det', 'upd.Id_Pengajuan_Det')
                ->whereColumn('lain.Id_Update_Performance_Detail', '<>', 'upd.Id_Update_Performance_Detail'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('KPI_Update_Performance_Detail_Sementara as s')
                ->whereColumn('s.Id_Pengajuan_Det', 'upd.Id_Pengajuan_Det'))
            ->orderByDesc('upd.Id_Update_Performance_Detail')
            ->select('upd.Id_Pengajuan_Det', 'upd.Id_Update_Performance')
            ->first();
    }

    /**
     * Kasus nyata dari admin: update performance bulan itu DIBATALKAN supaya
     * targetnya bisa dikoreksi, tetapi sel tetap terkunci "Sudah dinilai"
     * karena baris detailnya tidak ikut terhapus.
     *
     * @dataProvider headerYangTidakMengunci
     */
    public function test_final_yang_tidak_berlaku_tidak_mengunci(array $flag, string $kasus): void
    {
        $t = $this->targetBerfinalTunggal();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target berfinal tunggal di data dev.');
        }

        DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', $t->Id_Update_Performance)
            ->update($flag);

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det])[(int) $t->Id_Pengajuan_Det];

        $this->assertTrue($hasil['boleh'], "Target dengan final {$kasus} harus bisa dikoreksi, didapat {$hasil['kode']}.");
    }

    public static function headerYangTidakMengunci(): array
    {
        return [
            'dibatalkan' => [['Flag_Aktif' => 'T', 'Status' => 'Y', 'Flag_Approval' => 'Y'], 'dibatalkan'],
            'ditolak dan menunggu revisi' => [['Flag_Aktif' => 'Y', 'Status' => null, 'Flag_Approval' => 'T'], 'ditolak'],
            'riwayat yang dinonaktifkan' => [['Flag_Aktif' => 'T', 'Status' => null, 'Flag_Approval' => 'Y'], 'tidak aktif'],
        ];
    }

    /**
     * Penjaga arah sebaliknya: yang sedang ditinjau approver dihitung terhadap
     * target lama, jadi mengubah target di bawahnya tetap dilarang.
     */
    public function test_final_yang_menunggu_approval_tetap_mengunci(): void
    {
        $t = $this->targetBerfinalTunggal();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target berfinal tunggal di data dev.');
        }

        DB::table('KPI_Update_Performance')
            ->where('Id_Update_Performance', $t->Id_Update_Performance)
            ->update(['Flag_Aktif' => 'Y', 'Status' => null, 'Flag_Approval' => null]);

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det])[(int) $t->Id_Pengajuan_Det];

        $this->assertFalse($hasil['boleh']);
        $this->assertSame('SUDAH_FINAL', $hasil['kode']);
    }

    /**
     * Draf yang sudah dipindah jadi pengajuan atau dibatalkan tetap
     * meninggalkan baris detailnya. Tanpa saringan header, membatalkan final
     * hanya memindahkan kuncinya dari "Sudah dinilai" ke "Sedang diisi".
     *
     * @dataProvider drafYangTidakMengunci
     */
    public function test_draf_yang_tidak_hidup_tidak_mengunci(array $flagHeaderDraf): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $karyawan = DB::table('Karyawan')->whereNotNull('Nama')->first();
        $header = DB::table('KPI_Update_Performance_Sementara')
            ->orderByDesc('Id_Update_Performance_Sementara')
            ->first();

        if (! $karyawan || ! $header) {
            $this->markTestSkipped('Data dev tidak punya karyawan/header draf untuk disandarkan.');
        }

        DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_Update_Performance_Sementara', $header->Id_Update_Performance_Sementara)
            ->update($flagHeaderDraf);

        DB::table('KPI_Update_Performance_Detail_Sementara')->insert([
            'Id_Update_Performance_Sementara' => $header->Id_Update_Performance_Sementara,
            'Id_Pengajuan_Det' => $t->Id_Pengajuan_Det,
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nilai_Actual' => 1,
            'Score' => 1,
            'Achievement' => 1,
            'Flag_Aktif' => 'Y',
        ]);

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det])[(int) $t->Id_Pengajuan_Det];

        $this->assertTrue($hasil['boleh'], "Draf yang tidak hidup tidak boleh mengunci, didapat {$hasil['kode']}.");
    }

    public static function drafYangTidakMengunci(): array
    {
        return [
            'sudah dipindah jadi pengajuan' => [['Flag_Aktif' => 'Y', 'Flag_Pindah' => 'Y']],
            'dibatalkan' => [['Flag_Aktif' => 'T', 'Flag_Pindah' => null]],
        ];
    }

    public function test_ditolak_ketika_pengajuan_tidak_hidup(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        DB::table('KPI_Pengajuan')->where('Id_Pengajuan', $t->Id_Pengajuan)->update(['Flag_Aktif' => 'T']);

        $hasil = $this->svc()->periksaKelayakan([(int) $t->Id_Pengajuan_Det])[(int) $t->Id_Pengajuan_Det];

        $this->assertFalse($hasil['boleh']);
        $this->assertSame('PENGAJUAN_TIDAK_HIDUP', $hasil['kode']);
    }

    public function test_id_yang_tidak_ada_dilaporkan_bukan_dilewati(): void
    {
        $hasil = $this->svc()->periksaKelayakan([999999999]);

        $this->assertArrayHasKey(999999999, $hasil, 'Id tak dikenal harus tetap muncul di hasil, bukan hilang diam-diam.');
        $this->assertSame('TIDAK_DITEMUKAN', $hasil[999999999]['kode']);
    }

    /**
     * Payload monitoring WAJIB membawa alamat tiap target.
     *
     * Kelayakan dinilai per (parameter x periode), jadi layar butuh
     * id_pengajuan_det untuk menunjuk sel mana yang hendak dikoreksi. Tanpa itu
     * seluruh fitur ini punya angkanya tapi tidak punya alamatnya — dan
     * kehilangannya tidak menimbulkan error apa pun, hanya tombol yang tak
     * pernah bisa dipakai.
     *
     * Diuji lewat PAYLOAD sungguhan, bukan dengan memindai teks sumbernya:
     * pemindai teks lolos oleh perapian format dan tetap hijau meski
     * proyeksinya rusak.
     */
    public function test_payload_monitoring_membawa_alamat_target(): void
    {
        $idPengajuan = DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->orderByDesc('det.Id_Pengajuan_Det')
            ->value('pd.Id_Pengajuan');

        if (! $idPengajuan) {
            $this->markTestSkipped('Tidak ada pengajuan bertarget di data dev.');
        }

        $res = app(\App\Http\Controllers\KPI\Monitoring\Enforcement\KpiEnforcementController::class)
            ->getSubmissionDetail(new \Illuminate\Http\Request(['id_pengajuan' => (int) $idPengajuan]));

        $this->assertSame(200, $res->getStatusCode(), 'Detail pengajuan gagal dimuat: '.$res->getContent());

        $isi = json_decode($res->getContent(), true);
        $target = collect($isi['result']['parameters'] ?? [])
            ->flatMap(fn ($p) => $p['targets'] ?? [])
            ->values();

        if ($target->isEmpty()) {
            $this->markTestSkipped('Pengajuan ini tidak punya baris target.');
        }

        $this->assertTrue(
            $target->every(fn ($t) => array_key_exists('id_pengajuan_det', $t)),
            'Setiap target di payload monitoring harus membawa id_pengajuan_det.',
        );
        $this->assertTrue(
            $target->every(fn ($t) => is_int($t['id_pengajuan_det']) && $t['id_pengajuan_det'] > 0),
            'id_pengajuan_det harus integer positif, bukan null atau string.',
        );

        // Alamatnya harus benar-benar menunjuk baris yang ada, bukan sekadar terisi.
        $ada = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Det', $target->pluck('id_pengajuan_det')->all())
            ->count();

        $this->assertSame($target->count(), $ada, 'Ada id_pengajuan_det di payload yang tidak menunjuk baris mana pun.');
    }
}
