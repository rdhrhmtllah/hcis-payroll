<?php

namespace Tests\Feature\KPI\Monitoring;

use App\Services\KPI\KpiTargetKoreksiService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penerapan koreksi target beserta jejaknya.
 *
 * Setelah koreksi, `N_KPI_Target_Koreksi` adalah SATU-SATUNYA tempat nilai
 * target asli tersimpan — `KPI_Pengajuan_Det` tidak punya kolom jejak sama
 * sekali. Karena itu perubahan tanpa jejak sama dengan menghapus kesepakatan
 * tanpa bekas, dan penjaga di bawah menuntut nilai LAMA yang tercatat, bukan
 * sekadar adanya baris.
 */
class KoreksiTargetTerapkanTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): KpiTargetKoreksiService
    {
        return app(KpiTargetKoreksiService::class);
    }

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
            ->select('det.Id_Pengajuan_Det', 'det.Nilai_Target')
            ->first();
    }

    private function sudahFinal(): ?int
    {
        // Hanya final yang masih berlaku yang mengunci; baris milik pengajuan
        // yang dibatalkan atau ditolak justru boleh dikoreksi.
        $id = DB::table('KPI_Update_Performance_Detail as upd')
            ->join('KPI_Update_Performance as up', 'up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
            ->where('up.Flag_Aktif', 'Y')
            ->where('up.Flag_Approval', 'Y')
            ->whereNull('up.Status')
            ->orderByDesc('upd.Id_Update_Performance_Detail')
            ->value('upd.Id_Pengajuan_Det');

        return $id ? (int) $id : null;
    }

    public function test_nilai_berubah_dan_jejaknya_menyimpan_nilai_lama(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $lama = (float) $t->Nilai_Target;
        $baru = $lama + 7;

        $hasil = $this->svc()->terapkan(
            [['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => $baru]],
            'salah ketik saat pengajuan',
            1,
        );

        $this->assertCount(1, $hasil['diterapkan']);
        $this->assertSame([], $hasil['ditolak']);

        $this->assertSame($baru, (float) DB::table('KPI_Pengajuan_Det')
            ->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)
            ->value('Nilai_Target'));

        $jejak = DB::table('N_KPI_Target_Koreksi')
            ->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)
            ->orderByDesc('Id_Target_Koreksi')
            ->first();

        $this->assertNotNull($jejak, 'Perubahan tanpa jejak berarti target aslinya hilang selamanya.');
        $this->assertSame($lama, (float) $jejak->Nilai_Target_Lama, 'Jejak harus menyimpan nilai SEBELUM koreksi.');
        $this->assertSame($baru, (float) $jejak->Nilai_Target_Baru);
        $this->assertSame('salah ketik saat pengajuan', $jejak->Sebab);
        $this->assertSame($hasil['no_batch'], $jejak->No_Batch);
    }

    public function test_gagal_tertutup_satu_ditolak_maka_nol_diterapkan(): void
    {
        $t = $this->targetHidup();
        $terkunci = $this->sudahFinal();

        if (! $t || ! $terkunci) {
            $this->markTestSkipped('Butuh satu target bersih dan satu yang sudah final.');
        }

        $lama = (float) $t->Nilai_Target;

        $hasil = $this->svc()->terapkan([
            ['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => $lama + 3],
            ['id_pengajuan_det' => $terkunci, 'nilai_baru' => 1],
        ], 'coba campuran', 1);

        $this->assertSame([], $hasil['diterapkan'], 'Ada yang ditolak, jadi TIDAK BOLEH ada yang diterapkan.');
        $this->assertCount(1, $hasil['ditolak']);
        $this->assertNull($hasil['no_batch']);

        $this->assertSame($lama, (float) DB::table('KPI_Pengajuan_Det')
            ->where('Id_Pengajuan_Det', $t->Id_Pengajuan_Det)
            ->value('Nilai_Target'));
    }

    public function test_sebagian_hanya_saat_diminta_eksplisit(): void
    {
        $t = $this->targetHidup();
        $terkunci = $this->sudahFinal();

        if (! $t || ! $terkunci) {
            $this->markTestSkipped('Butuh satu target bersih dan satu yang sudah final.');
        }

        $hasil = $this->svc()->terapkan([
            ['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => (float) $t->Nilai_Target + 3],
            ['id_pengajuan_det' => $terkunci, 'nilai_baru' => 1],
        ], 'campuran disengaja', 1, bolehSebagian: true);

        $this->assertCount(1, $hasil['diterapkan']);
        $this->assertCount(1, $hasil['ditolak']);
        $this->assertSame('SUDAH_FINAL', $hasil['ditolak'][0]['kode']);
    }

    public function test_sebab_kosong_ditolak(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $this->expectException(\InvalidArgumentException::class);

        $this->svc()->terapkan(
            [['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => 1]],
            '   ',
            1,
        );
    }

    /**
     * Koreksi tidak boleh melahirkan salah ketik berikutnya — tapi juga tidak
     * boleh memblokir. Nilai yang benar tetap milik pemilik prosesnya; itu
     * prinsip yang sama yang membuat kpi:cek-target-menyimpang tidak pernah
     * memperbaiki apa pun sendiri.
     */
    public function test_nilai_baru_yang_menyimpang_diperingatkan_tapi_tetap_diterapkan(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $saudara = DB::table('KPI_Pengajuan_Det as d')
            ->join('KPI_Pengajuan_Det as x', 'x.Id_Pengajuan_Detail', '=', 'd.Id_Pengajuan_Detail')
            ->where('d.Id_Pengajuan_Det', $t->Id_Pengajuan_Det)
            ->where('x.Id_Pengajuan_Det', '<>', $t->Id_Pengajuan_Det)
            ->where('x.Nilai_Target', '>', 0)
            ->count();

        if ($saudara === 0) {
            $this->markTestSkipped('Parameter ini tak punya periode lain berteget positif; median tak bermakna.');
        }

        $ekstrem = max(1.0, (float) $t->Nilai_Target) * 1000;

        $peringatan = $this->svc()->periksaMenyimpang((int) $t->Id_Pengajuan_Det, $ekstrem);

        $this->assertNotNull($peringatan, 'Nilai 1000x median harus diperingatkan.');
        $this->assertGreaterThan(10, $peringatan['rasio']);

        // Memperingatkan, BUKAN memblokir.
        $hasil = $this->svc()->terapkan(
            [['id_pengajuan_det' => (int) $t->Id_Pengajuan_Det, 'nilai_baru' => $ekstrem]],
            'disengaja',
            1,
        );

        $this->assertCount(1, $hasil['diterapkan']);
    }

    public function test_nilai_wajar_tidak_diperingatkan(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $median = DB::table('KPI_Pengajuan_Det as x')
            ->join('KPI_Pengajuan_Det as d', 'd.Id_Pengajuan_Detail', '=', 'x.Id_Pengajuan_Detail')
            ->where('d.Id_Pengajuan_Det', $t->Id_Pengajuan_Det)
            ->where('x.Id_Pengajuan_Det', '<>', $t->Id_Pengajuan_Det)
            ->avg('x.Nilai_Target');

        if (! $median || $median <= 0) {
            $this->markTestSkipped('Tak ada saudara sebaris berteget positif.');
        }

        $this->assertNull(
            $this->svc()->periksaMenyimpang((int) $t->Id_Pengajuan_Det, (float) $median),
            'Nilai sekitar median tidak boleh diperingatkan; peringatan palsu membuat peringatan diabaikan.',
        );
    }

    /**
     * Kelayakan harus diperiksa ULANG setelah barisnya dikunci.
     *
     * Pemeriksaan pertama terjadi di luar transaksi dan kuncinya hanya memegang
     * KPI_Pengajuan_Det, bukan tabel draf. Tanpa pemeriksaan kedua, draf yang
     * dikirim tepat di antara dua saat itu tidak akan terlihat — dan draf basi
     * itulah yang jadi alasan gerbangnya ada.
     *
     * Balapan sungguhan tak bisa dipentaskan di dalam satu transaksi uji, jadi
     * yang dibuktikan di sini adalah PERILAKUNYA: pemeriksaan kedua benar-benar
     * dijalankan dan hasilnya dihormati.
     */
    public function test_kelayakan_diperiksa_ulang_di_dalam_transaksi(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $id = (int) $t->Id_Pengajuan_Det;
        $lama = (float) $t->Nilai_Target;

        // Layak pada pemeriksaan PERTAMA, tidak layak pada pemeriksaan KEDUA.
        $svc = new class extends \App\Services\KPI\KpiTargetKoreksiService
        {
            public int $panggilan = 0;

            public function periksaKelayakan(array $idPengajuanDet): array
            {
                $this->panggilan++;

                if ($this->panggilan === 1) {
                    return parent::periksaKelayakan($idPengajuanDet);
                }

                return collect($idPengajuanDet)
                    ->mapWithKeys(fn ($id) => [(int) $id => [
                        'boleh' => false,
                        'kode' => 'ADA_DRAF',
                        'keterangan' => 'Draf muncul di tengah jalan.',
                        'pemegang_draf' => 'Seseorang',
                    ]])
                    ->all();
            }
        };

        $hasil = $svc->terapkan(
            [['id_pengajuan_det' => $id, 'nilai_baru' => $lama + 5]],
            'uji balapan',
            1,
        );

        $this->assertGreaterThanOrEqual(2, $svc->panggilan, 'Kelayakan harus diperiksa lagi setelah baris dikunci.');
        $this->assertSame([], $hasil['diterapkan'], 'Yang jadi tidak layak di tengah jalan TIDAK boleh diterapkan.');
        $this->assertSame('ADA_DRAF', $hasil['ditolak'][0]['kode']);

        $this->assertSame($lama, (float) DB::table('KPI_Pengajuan_Det')
            ->where('Id_Pengajuan_Det', $id)
            ->value('Nilai_Target'), 'Nilainya tidak boleh berubah.');

        $this->assertSame(0, DB::table('N_KPI_Target_Koreksi')
            ->where('Id_Pengajuan_Det', $id)
            ->where('Sebab', 'uji balapan')
            ->count(), 'Tidak ada yang diterapkan, jadi tidak boleh ada jejaknya.');
    }

    /**
     * Biaya pemeriksaan menyimpang harus TETAP, bukan per target.
     *
     * Endpoint koreksi menerima sampai 500 perubahan sekaligus. Biaya per-target
     * terhadap database yang jaraknya ~100 ms membuat satu simpan besar memakan
     * puluhan detik — dan itu tidak akan pernah tampak sebagai error, hanya
     * sebagai halaman yang menggantung.
     *
     * periksaKelayakan() di berkas yang sama sudah berbiaya tetap; ini menuntut
     * hal yang sama dari pemeriksaan menyimpang.
     */
    public function test_pemeriksaan_menyimpang_berbiaya_tetap(): void
    {
        $ids = DB::table('KPI_Pengajuan_Det')->limit(20)->pluck('Id_Pengajuan_Det');

        if ($ids->count() < 10) {
            $this->markTestSkipped('Butuh minimal 10 baris target untuk membuktikan biayanya tidak per-target.');
        }

        $perubahan = $ids->map(fn ($id) => ['id_pengajuan_det' => (int) $id, 'nilai_baru' => 999.0])->all();

        $n = 0;
        DB::listen(function () use (&$n) {
            $n++;
        });

        $this->svc()->periksaMenyimpangBanyak($perubahan);

        $this->assertLessThanOrEqual(
            4,
            $n,
            sprintf('Memakai %d kueri untuk %d target; biayanya harus tetap, bukan sebanding jumlah target.', $n, count($perubahan)),
        );
    }

    public function test_pemeriksaan_menyimpang_banyak_sepakat_dengan_yang_tunggal(): void
    {
        $t = $this->targetHidup();
        if (! $t) {
            $this->markTestSkipped('Tidak ada target bersih di data dev.');
        }

        $id = (int) $t->Id_Pengajuan_Det;
        $ekstrem = max(1.0, (float) $t->Nilai_Target) * 1000;

        $tunggal = $this->svc()->periksaMenyimpang($id, $ekstrem);
        $banyak = $this->svc()->periksaMenyimpangBanyak([['id_pengajuan_det' => $id, 'nilai_baru' => $ekstrem]]);

        // Satu aturan, dua bentuk pemanggilan: hasilnya wajib sama, kalau tidak
        // layar dan simpan bisa berselisih diam-diam.
        $this->assertSame($tunggal === null, ! isset($banyak[$id]));

        if ($tunggal !== null) {
            $this->assertEqualsWithDelta($tunggal['rasio'], $banyak[$id]['rasio'], 0.001);
            $this->assertEqualsWithDelta($tunggal['median'], $banyak[$id]['median'], 0.001);
        }
    }
}
