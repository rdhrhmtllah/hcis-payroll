<?php

namespace Tests\Feature\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\AdminPengumpulanDataService;
use App\Services\ESS\Employee\DataCollection\PengirimanDataService;
use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminPengumpulanDataServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function admin(): AdminPengumpulanDataService
    {
        return app(AdminPengumpulanDataService::class);
    }

    private function kodeAktif(int $lewati = 0): string
    {
        $kode = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->orderBy('Kode_Karyawan')
            ->skip($lewati)
            ->limit(1)
            ->value('Kode_Karyawan');

        $this->assertNotNull($kode, 'Butuh cukup karyawan aktif di DB untuk uji ini.');

        DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', $kode)->delete();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $kode)->delete();
        app(StatusPengumpulanService::class)->bersihkanCache($kode);

        return $kode;
    }

    private function dataLengkap(array $ganti = []): array
    {
        return array_merge([
            'nama_lengkap' => 'Budi Santoso',
            'nik_ktp' => '3201234567890123',
            'tanggal_lahir' => '1995-04-17',
            'jenis_kelamin' => 'L',
            'email' => 'budi@evonusabersaudara.co.id',
            'no_hp' => '6281234567890',
            'departemen' => 'IT',
            'pendidikan_terakhir' => 'S1',
            'status_pernikahan' => 'Menikah',
            // Wajib menyertai 'Menikah' sejak versi skema 3.
            'jumlah_tanggungan' => '2',
            'darurat_nama' => 'Siti Aminah',
            'darurat_hubungan' => 'Pasangan',
            'darurat_no_hp' => '6281298765432',
        ], $ganti);
    }

    public function test_ringkasan_menghitung_seluruh_karyawan_aktif_sebagai_target(): void
    {
        // Sejak kampanye bisa dibatasi lokasi, "seluruh karyawan aktif" hanya
        // berlaku bila cakupannya dibuka untuk semua lokasi. Tanpa ini uji ini
        // bergantung pada setelan cakupan di DB tempat ia dijalankan (dev
        // dibatasi ke EMI: 54 lawan 957). Target berlokasi dijaga
        // CakupanLokasiPengumpulanDataTest.
        DB::table('N_HRIS_Pengumpulan_Data_Config')->update(['Cakupan_Lokasi' => null]);
        app(\App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye::class)->bersihkanCache();

        $targetSebenarnya = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->count();

        $r = $this->admin()->ringkasan();

        $this->assertSame($targetSebenarnya, $r['total']);
        $this->assertSame($r['total'], $r['sudah'] + $r['belum'] + $r['dikecualikan']);
    }

    public function test_karyawan_tanpa_baris_status_tetap_terhitung_belum(): void
    {
        $kode = $this->kodeAktif();

        $daftar = $this->admin()->daftar(['cari' => $kode]);
        $baris = collect($daftar['items'])->firstWhere('Kode_Karyawan', $kode);

        $this->assertNotNull($baris, 'Karyawan tanpa baris status harus tetap muncul di daftar.');
        $this->assertSame('BELUM', $baris['status']);
    }

    public function test_pengiriman_memindahkan_hitungan_dari_belum_ke_sudah(): void
    {
        $kode = $this->kodeAktif();
        $sebelum = $this->admin()->ringkasan();

        app(PengirimanDataService::class)->kirim($kode, $this->dataLengkap());

        $sesudah = $this->admin()->ringkasan();

        $this->assertSame($sebelum['sudah'] + 1, $sesudah['sudah']);
        $this->assertSame($sebelum['belum'] - 1, $sesudah['belum']);
    }

    public function test_detail_menandai_field_yang_berbeda_dari_data_lama(): void
    {
        $kode = $this->kodeAktif();
        app(PengirimanDataService::class)->kirim($kode, $this->dataLengkap(['nama_lengkap' => 'NAMA YANG PASTI BERBEDA XYZ']));

        $detail = $this->admin()->detail($kode);

        $this->assertTrue($detail['perbedaan']['nama_lengkap'], 'Nama yang diubah harus ditandai berbeda.');
        // status_pernikahan tidak punya padanan di tabel induk -> tidak boleh ikut ditandai.
        $this->assertArrayNotHasKey('status_pernikahan', $detail['perbedaan']);
    }

    public function test_pengecualian_menyembunyikan_orang_dari_hitungan_belum_dan_tercatat_di_audit(): void
    {
        $kode = $this->kodeAktif();
        $sebelum = $this->admin()->ringkasan();

        $this->admin()->aturPengecualian($kode, true, 'Direksi', 'UJI-ADMIN');

        $sesudah = $this->admin()->ringkasan();

        $this->assertSame($sebelum['dikecualikan'] + 1, $sesudah['dikecualikan']);
        $this->assertSame($sebelum['belum'] - 1, $sesudah['belum']);

        $audit = DB::table('N_HRIS_Pengumpulan_Data_Audit')
            ->where('Kode_Karyawan_Target', $kode)
            ->where('Aksi', 'TAMBAH_PENGECUALIAN')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('UJI-ADMIN', $audit->Dilakukan_Oleh);
    }

    public function test_buka_kunci_memungkinkan_kiriman_baru_tanpa_menghapus_yang_lama(): void
    {
        $kode = $this->kodeAktif();
        $pengiriman = app(PengirimanDataService::class);
        $pengiriman->kirim($kode, $this->dataLengkap());

        $this->admin()->bukaKunci($kode, 'Salah ketik NIK', 'UJI-ADMIN');

        $pengiriman->kirim($kode, $this->dataLengkap(['nik_ktp' => '3209999999999999']));

        $semua = DB::table('N_HRIS_Pengumpulan_Data_Submission')
            ->where('Kode_Karyawan', $kode)
            ->orderBy('Id')
            ->get();

        $this->assertCount(2, $semua, 'Kiriman lama harus tetap ada sebagai riwayat.');
        $this->assertSame('T', $semua[0]->Flag_Aktif);
        $this->assertSame('Y', $semua[1]->Flag_Aktif);
        $this->assertSame('3209999999999999', json_decode($semua[1]->Data_Json, true)['nik_ktp']);
    }

    public function test_sakelar_kampanye_bisa_diubah_dan_tercatat(): void
    {
        $this->admin()->aturSakelar(true, 'UJI-ADMIN');
        $this->assertTrue(app(\App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye::class)->aktif());

        $this->admin()->aturSakelar(false, 'UJI-ADMIN');
        $this->assertFalse(app(\App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye::class)->aktif());

        $this->assertTrue(
            DB::table('N_HRIS_Pengumpulan_Data_Audit')->where('Aksi', 'UBAH_SAKELAR')->exists(),
        );
    }

    public function test_pemeriksa_kualitas_menyorot_nik_kembar(): void
    {
        $a = $this->kodeAktif(0);
        $b = $this->kodeAktif(1);

        $pengiriman = app(PengirimanDataService::class);
        $pengiriman->kirim($a, $this->dataLengkap(['nik_ktp' => '3211111111111111']));
        $pengiriman->kirim($b, $this->dataLengkap(['nik_ktp' => '3211111111111111']));

        // Dicari temuan MILIK uji ini, bukan temuan PERTAMA. Database dev berisi
        // kiriman orang lain, dan kalau di antaranya ada NIK kembar sungguhan,
        // temuan itulah yang muncul lebih dulu — uji jadi merah padahal
        // fiturnya justru bekerja benar.
        $temuan = collect($this->admin()->pemeriksaKualitas())
            ->where('jenis', 'NIK_KEMBAR');

        $milikUji = $temuan->firstWhere('nilai', '3211111111111111');

        $this->assertNotNull(
            $milikUji,
            'NIK yang dipakai dua orang harus tersorot. Yang tersorot: '
                .$temuan->pluck('nilai')->implode(', '),
        );
        $this->assertCount(2, $milikUji['karyawan']);
    }
}
