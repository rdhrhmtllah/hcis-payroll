<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Http\Controllers\VISITOR\Workflow\Reporting\LaporanVisitorController;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Monitoring Tamu dibatasi MATRIKS, bukan kunci halaman.
 *
 * Sesudah Jadwal Kedatangan Tamu dilebur ke sini, halamannya dihibahkan lewat
 * role — jauh lebih banyak orang bisa membukanya. Yang menahan datanya kini
 * satu-satunya: matriks departemen × kategori tamu.
 *
 * Dua sisi diuji bersama, sebab menjaga salah satunya saja tidak berarti:
 *
 *  - Yang BERHAK harus benar-benar melihat barisnya. Penjaga yang hanya
 *    menagih "tidak terlihat" tetap hijau seandainya penyaringnya membuang
 *    segalanya, dan kebocoran berubah jadi kebutaan tanpa ada yang tahu.
 *  - Yang TIDAK berhak tidak boleh melihatnya, walau ia memegang kunci
 *    halamannya — sebab kunci itulah jalan pintas yang sengaja ditutup.
 */
class MonitoringTamuLingkupMatriksTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hanya_kategori_yang_berhak_yang_terlihat(): void
    {
        $reguler = $this->buatTamu(TipeTamu::REGULAR, null);
        $b2b = $this->buatTamu(TipeTamu::B2B, 'PROCUREMENT');

        // Pemakai yang hanya berhak atas tamu REGULAR.
        $user = $this->penggunaDenganHak(['REGULAR']);
        $nomor = $this->daftarTamu($user);

        $this->assertContains($reguler, $nomor, 'Yang berhak justru tidak melihat barisnya; penyaringnya membuang segalanya.');
        $this->assertNotContains($b2b, $nomor, 'Tamu B2B bocor ke pemakai yang hanya berhak REGULAR.');
    }

    public function test_hak_b2b_melihat_unitnya_saja(): void
    {
        $proc = $this->buatTamu(TipeTamu::B2B, 'PROCUREMENT');
        $log = $this->buatTamu(TipeTamu::B2B, 'LOGISTIC');

        $user = $this->penggunaDenganHak(['B2B_PROCUREMENT']);
        $nomor = $this->daftarTamu($user, 'B2B');

        $this->assertContains($proc, $nomor);
        $this->assertNotContains($log, $nomor, 'Unit B2B lain ikut terlihat; matriks tidak membelah per unit.');
    }

    public function test_kunci_halaman_bukan_lagi_jalan_pintas(): void
    {
        // Inti perubahannya. Sebelumnya pemegang kunci melihat SEMUA tanpa
        // matriks; dua mekanisme untuk satu pertanyaan membuat keduanya saling
        // tidak tahu, dan kunci yang dihibahkan tangan lalu terlupakan adalah
        // sebab visitorJadwalTamuPage berakhir nol pemegang.
        $b2b = $this->buatTamu(TipeTamu::B2B, 'PROCUREMENT');

        $user = $this->penggunaDenganHak(['REGULAR']);
        DB::table('HRIS_Page_Access')->insert([
            'Kode_Perusahaan' => '001',
            'ID_Level' => 5,
            'ID_Divisi' => 22,
            'Jenis_page' => 'visitorMonitoringTamuPage',
            'UserID_Web' => $user->Id_Users,
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
        ]);

        $this->assertNotContains(
            $b2b,
            $this->daftarTamu($user, 'B2B'),
            'Kunci halaman masih membuka kategori yang tidak diberikan matriks.',
        );
    }

    /**
     * Matriks berlaku di DETAIL juga, bukan hanya di daftarnya.
     *
     * Daftar yang tersaring tanpa detail yang tersaring hanya menyembunyikan
     * barisnya. Nomor transaksinya tetap beredar di notifikasi, tautan, dan
     * tangkapan layar, dan satu permintaan langsung ke endpoint detail
     * memulangkan seluruh isi kunjungan yang kategorinya tidak pernah
     * diberikan — termasuk nomor identitas tamunya.
     */
    public function test_detail_kategori_di_luar_hak_tidak_dilayani(): void
    {
        $reguler = $this->buatTamu(TipeTamu::REGULAR, null);
        $b2b = $this->buatTamu(TipeTamu::B2B, 'PROCUREMENT');

        $user = $this->penggunaDenganHak(['REGULAR']);

        // Kontrol positif: yang berhak memang terlayani. Tanpa ini, penyaring
        // yang menolak segalanya membuat asersi di bawah hijau tanpa arti.
        $this->assertSame(200, $this->detailTamu($user, $reguler)->getStatusCode());

        $this->assertSame(
            404,
            $this->detailTamu($user, $b2b, 'B2B')->getStatusCode(),
            'Detail kunjungan B2B terlayani bagi pemakai yang hanya berhak REGULAR.',
        );
    }

    public function test_tanpa_hak_apa_pun_daftarnya_kosong(): void
    {
        $this->buatTamu(TipeTamu::REGULAR, null);

        $user = $this->penggunaDenganHak([]);

        $this->assertSame([], $this->daftarTamu($user));
    }

    // ---------------------------------------------------------------- helpers

    private function detailTamu(object $user, string $noTransaksi, ?string $tipeRute = null): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/laporanvisitor-detail-tamu/'.$noTransaksi, 'GET');
        $request->setUserResolver(fn () => $user);

        if ($tipeRute !== null) {
            $request->attributes->set(TipeTamu::ATRIBUT, $tipeRute);
        }

        return app(LaporanVisitorController::class)->getDetailTamu($request, $noTransaksi);
    }

    /** @return array<int, string> */
    private function daftarTamu(object $user, ?string $tipeRute = null): array
    {
        $request = Request::create('/laporanvisitor-tamu', 'GET', [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
            'limit' => 200,
        ]);
        $request->setUserResolver(fn () => $user);

        if ($tipeRute !== null) {
            $request->attributes->set(TipeTamu::ATRIBUT, $tipeRute);
        }

        $isi = json_decode(app(LaporanVisitorController::class)->getLaporanTamu($request)->getContent(), true);
        $baris = $isi['result']['data'] ?? $isi['result'] ?? [];

        return array_column($baris, 'No_Transaksi');
    }

    /**
     * Pengguna dengan role baru yang haknya persis seperti diminta.
     *
     * Role dibuat sendiri, bukan menumpang yang sudah ada: role nyata bisa
     * berubah haknya kapan saja lewat DML, dan uji yang menumpanginya akan
     * merah karena keputusan bisnis orang lain — bukan karena kodenya rusak.
     *
     * @param  array<int, string>  $kategori
     */
    private function penggunaDenganHak(array $kategori): object
    {
        $kodeRole = 'UJI_MTX_'.Str::upper(Str::random(8));

        $idRole = (int) DB::table('N_HRIS_Role_Functional')->insertGetId([
            'Role_Code' => $kodeRole,
            'Role_Name' => 'Departemen Uji Matriks (Tamu)',
            'Id_Grup_Fitur' => 4,
            'Flag_Aktif' => 'Y',
        ]);

        foreach ($kategori as $k) {
            DB::table('N_VISITOR_Matriks_Tamu')->insert([
                'Role_Id' => $idRole,
                'Kategori_Tamu' => $k,
                'Flag_Lihat' => 'Y',
                'Flag_Ajukan' => 'T',
                'Flag_Aktif' => 'Y',
                'Created_By' => 'UJI',
            ]);
        }

        // Pengguna yang belum punya role apa pun, supaya haknya benar-benar
        // hanya yang barusan diberikan.
        $idUser = DB::table('KPI_Users as u')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))->from('N_HRIS_Role_User as ru')
                    ->whereColumn('ru.UserId_Web', 'u.Id_Users')->where('ru.Flag_Aktif', 'Y');
            })
            ->orderBy('u.Id_Users')
            ->value('u.Id_Users');

        $this->assertNotNull($idUser, 'Tidak ada pengguna tanpa role untuk diuji.');

        DB::table('N_HRIS_Role_User')->insert([
            'UserId_Web' => (int) $idUser,
            'Id_Role' => $idRole,
            'Assigned_At' => now(),
            'Assigned_By' => 'UJI',
            'Flag_Aktif' => 'Y',
        ]);

        return (object) ['Id_Users' => (int) $idUser];
    }

    private function buatTamu(string $tipe, ?string $unit): string
    {
        $nomor = 'VSR-MTX-'.Str::upper(Str::random(8));
        $pic = (string) DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => $pic,
            'Status_Kunjungan' => 'SCHEDULED',
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $pic,
            'Idempotency_Key' => 'MTX-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Uji Matriks',
            'Tipe_Tamu' => $tipe,
            'B2B_Unit' => $unit,
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Uji lingkup matriks',
            'Jumlah_Tamu' => 1,
        ]);

        return $nomor;
    }
}
