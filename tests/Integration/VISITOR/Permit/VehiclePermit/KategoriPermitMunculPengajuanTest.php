<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MemesanSlotInspeksi;
use Tests\TestCase;

/**
 * Kategori permit yang boleh muncul di form pengajuan karyawan.
 *
 * Sejak kategori COKLAT lahir untuk izin tamu, form "Buat Baru" menawarkan
 * kategori yang tidak pernah boleh diajukan karyawan. Penandanya
 * `Flag_Muncul_Pengajuan`, dan yang dijaga berkas ini ada dua — keduanya
 * gagal tanpa menyalakan galat apa pun kalau rusak:
 *
 *  1. Dropdown-nya menyaring. Kalau tidak, pemohon memilih kategori izin tamu
 *     dan permitnya terbit dari pintu yang salah.
 *  2. SERVER-nya ikut menyaring. Penyaring yang hanya ada di dropdown
 *     dilewati siapa pun yang mengirim id kategori langsung ke endpoint-nya.
 *
 * NULL dibaca sebagai boleh tampil: kategori yang lahir dari jalur lain tidak
 * boleh hilang diam-diam dari form hanya karena kolomnya terlewat diisi.
 */
class KategoriPermitMunculPengajuanTest extends TestCase
{
    use DatabaseTransactions;
    use MemesanSlotInspeksi;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PEMOHON = 'A1';

    protected function setUp(): void
    {
        parent::setUp();

        // A1 tercatat resign di dev (3 Sep 2025). Di sini ia diperlakukan masih
        // bekerja; perubahannya ikut dibatalkan bersama transaksi uji.
        DB::table('Karyawan')->where('Kode_Karyawan', self::PEMOHON)->update(['Tanggal_Resign' => null]);
    }

    public function test_kategori_bertanda_tidak_muncul_hilang_dari_pilihan(): void
    {
        $tersembunyi = $this->buatKategori('T');
        $tampil = $this->buatKategori('Y');

        $id = $this->idKategoriPilihan();

        $this->assertContains($tampil, $id, 'Kategori bertanda muncul justru hilang dari pilihan.');
        $this->assertNotContains($tersembunyi, $id, 'Kategori yang disembunyikan masih ditawarkan ke pemohon.');
    }

    public function test_kategori_tanpa_penanda_tetap_muncul(): void
    {
        // Arah bawaannya sengaja begitu: kategori yang belum pernah dipikirkan
        // lebih baik terlihat dan bisa dicabut admin, daripada hilang diam-diam
        // dan membuat pemohon menyimpulkan permitnya tidak bisa diajukan.
        $tanpaPenanda = $this->buatKategori(null);

        $this->assertContains($tanpaPenanda, $this->idKategoriPilihan());
    }

    public function test_coklat_tidak_pernah_ditawarkan_ke_karyawan(): void
    {
        // Izin sehari milik tamu; ia terbit lewat registrasi tamu dan endpoint
        // mitra, tidak pernah lewat form pengajuan permit karyawan.
        $coklat = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
            ->where('Nama_Kategori', 'COKLAT')
            ->value('Id_Kategori_Permit');

        $this->assertNotNull($coklat, 'Prasyarat berubah: kategori COKLAT tidak ada di master.');
        $this->assertNotContains((int) $coklat, $this->idKategoriPilihan());
    }

    public function test_server_menolak_kategori_yang_disembunyikan(): void
    {
        // Penjaga utama. Kalau ini merah, penyaringnya hanya kosmetik: siapa
        // pun yang mengirim id kategori langsung ke endpoint tetap lolos.
        $tersembunyi = $this->buatKategori('T');

        $isi = $this->ajukan($tersembunyi);

        $this->assertSame('VISITOR_PERMIT_MASTER_INACTIVE', $isi['error_code'] ?? null, json_encode($isi));
    }

    public function test_kategori_yang_muncul_tetap_bisa_diajukan(): void
    {
        // Pasangan uji di atas. Tanpa ini, penyaring yang menolak SEMUA
        // kategori akan tetap hijau dan form-nya diam-diam mati total.
        $tampil = $this->buatKategori('Y');

        $isi = $this->ajukan($tampil);

        $this->assertNotSame(
            'VISITOR_PERMIT_MASTER_INACTIVE',
            $isi['error_code'] ?? null,
            'Kategori yang boleh muncul ikut ditolak: '.json_encode($isi),
        );
    }

    /** @return array<int,int> */
    private function idKategoriPilihan(): array
    {
        $isi = json_decode(app(VehiclePermitController::class)->getMasterData()->getContent(), true);

        return array_map(
            static fn (array $baris): int => (int) $baris['Id_Kategori_Permit'],
            $isi['result']['kategoris'] ?? [],
        );
    }

    /** @return array<string,mixed> */
    private function ajukan(int $idKategori): array
    {
        $master = json_decode(app(VehiclePermitController::class)->getMasterData()->getContent(), true)['result'];

        $request = Request::create('/transaksipermit', 'POST', [
            'plat_prefix' => $master['plat_codes'][0] ?? 'BG',
            'plat_angka' => (string) random_int(10000, 99999),
            'plat_suffix' => 'KP',
            'id_vehicle_type' => $master['vehicle_types'][0]['Id_Vehicle_Type'] ?? null,
            'id_vehicle_model' => $master['models'][0]['Id_Vehicle_Model'] ?? null,
            'id_vehicle_color' => $master['colors'][0]['Id_Master_Color'] ?? null,
            'tahun_kendaraan' => 2021,
            'id_kategori_permit' => $idKategori,
            'keterangan_pengajuan' => 'Uji penanda kategori',
        ] + $this->slotInspeksiUji(), [], [
            // Ketiga berkas ini WAJIB pada pengajuan permit: izin akses fisik
            // tidak diterbitkan tanpa bukti kendaraan, STNK, dan SIM. Uji ini
            // menguji penanda kategori, bukan aturan berkasnya — jadi
            // berkasnya dipenuhi apa adanya supaya yang diuji benar-benar
            // sampai ke kode yang dimaksud.
            'foto_kendaraan' => UploadedFile::fake()->image('kendaraan.jpg'),
            'foto_stnk' => UploadedFile::fake()->image('stnk.jpg'),
            'foto_sim' => UploadedFile::fake()->image('sim.jpg'),
        ]);
        $request->headers->set('Idempotency-Key', 'KAT-'.Str::upper(Str::random(20)));
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PEMOHON]);

        return json_decode(app(VehiclePermitController::class)->store($request)->getContent(), true) ?? [];
    }

    private function buatKategori(?string $penanda): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->insertGetId([
            'Nama_Kategori' => 'UJI '.Str::upper(Str::random(8)),
            'Deskripsi_Akses' => 'Kategori uji penanda muncul',
            'Kode_Warna_UI' => '#6366F1',
            'Flag_Aktif' => 'Y',
            'Flag_Muncul_Pengajuan' => $penanda,
        ]);
    }
}
