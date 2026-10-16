<?php

namespace Tests\Feature\KPI\Performance;

use App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Form KPI tim tidak boleh mati gara-gara lead bukan peserta jadwal.
 *
 * getDataCurrent() melayani form INDIVIDU maupun form TIM. Form tim
 * memanggilnya sekali pada pemuatan awal, SEBELUM ada anggota dicentang — dan
 * axios menghilangkan array kosong dari query string, sehingga permintaannya
 * tiba tanpa `kode_karyawan` sama sekali. Pada jadwal perorangan, kode lama
 * lalu mencari Start_Periode milik pengguna yang LOGIN; kalau lead-nya bukan
 * peserta, seluruh form gagal dengan 404 "Data Periode Tidak Ditemukan" —
 * padahal endpoint ini cuma mengambil daftar rujukan.
 */
class FormTimJadwalPeroranganTidak404Test extends TestCase
{
    use DatabaseTransactions;

    public function test_bukan_peserta_jadwal_perorangan_tidak_lagi_404(): void
    {
        [$idJadwal, $peserta, $bukanPeserta] = $this->siapkanJadwalPerorangan();

        $this->masukSebagai($bukanPeserta);
        $response = $this->panggil($idJadwal);

        $this->assertSame(
            200,
            $response->getStatusCode(),
            'Lead yang bukan peserta masih menerima 404 — form tim akan mati saat dibuka.',
        );

        $isi = json_decode($response->getContent(), true);
        foreach (['dataTarget', 'dataBSCKategori', 'dataFormula'] as $kunci) {
            $this->assertArrayHasKey($kunci, $isi['result']);
        }

        $this->assertNotSame($peserta, $bukanPeserta, 'Prasyarat gagal: dua kode karyawan harus berbeda.');
    }

    public function test_peserta_tetap_memakai_bulan_mulainya_sendiri(): void
    {
        // Perilaku form INDIVIDU tidak boleh ikut berubah: bulan mulai peserta
        // adalah miliknya sendiri, bukan yang paling awal di antara semua
        // peserta. Kalau ini bergeser, target yang seharusnya belum aktif
        // untuk seseorang jadi ikut tampil.
        [$idJadwal, $peserta] = $this->siapkanJadwalPerorangan();

        $milikSendiri = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->where('Id_Master_Penjadwalan', $idJadwal)
            ->where('Kode_Karyawan', $peserta)
            ->value('Start_Periode');

        $palingAwal = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->where('Id_Master_Penjadwalan', $idJadwal)
            ->min('Start_Periode');

        $this->assertNotSame(
            $milikSendiri,
            $palingAwal,
            'Fixture tidak menguji apa pun: bulan mulai peserta harus BERBEDA dari yang paling awal.',
        );

        $this->masukSebagai($peserta);

        $this->assertSame(200, $this->panggil($idJadwal)->getStatusCode());
    }

    public function test_peserta_tanpa_bulan_mulai_tidak_menghasilkan_periode_tidak_valid(): void
    {
        // Start_Periode boleh kosong (17 baris nyata di basis dev). Kode lama
        // menghasilkan '00' di sini, dan getListTarget() MENOLAK '00' dengan
        // "Periode tidak valid" — jadi form individu orang-orang itu menerima
        // objek galat alih-alih daftar target, tanpa 404 yang kelihatan.
        [$idJadwal] = $this->siapkanJadwalPerorangan();

        $tanpaBulan = $this->kodeKaryawanMana();
        DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
            'Id_Master_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => $tanpaBulan,
            'Start_Periode' => null,
            'Keterangan' => 'Uji',
        ]);

        $this->masukSebagai($tanpaBulan);
        $response = $this->panggil($idJadwal);
        $isi = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotSame('Periode tidak valid', $isi['message'] ?? null);
        $this->assertIsArray($isi['result']['dataTarget'] ?? null);
    }

    public function test_bulan_mulai_perorangan_menjawab_tiga_keadaan(): void
    {
        // Satu helper dipakai getDataCurrent() DAN getTargetBreakdown(); dulu
        // keduanya memuat salinan aturan yang sama dan bisa berselisih diam-diam.
        [$idJadwal, $peserta, $bukanPeserta] = $this->siapkanJadwalPerorangan();

        $metode = new \ReflectionMethod(FormKpiController::class, 'bulanMulaiPerorangan');
        $metode->setAccessible(true);
        $bulan = fn (?string $kode) => $metode->invoke(app(FormKpiController::class), $idJadwal, $kode);

        // 1. Peserta berbulan-mulai -> bulannya sendiri (form individu, tak berubah).
        $this->assertSame('06', $bulan($peserta));

        // 2. Bukan peserta -> paling awal di antara peserta (form tim).
        $this->assertSame('01', $bulan($bukanPeserta));

        // 3. Peserta tanpa bulan mulai -> '1', semua periode aktif.
        //
        // Orangnya WAJIB yang belum jadi peserta. kodeKaryawanMana() memulangkan
        // akun pertama, dan itu justru $kode[0] — peserta ber-Start_Periode
        // '2026-01'. Menyisipkan baris kedua untuknya membuat dua baris pada satu
        // (jadwal, karyawan), dan ->first() tanpa ORDER BY memulangkan yang lama:
        // ujinya membaca '01' sambil mengira sedang menguji baris tanpa bulan.
        //
        // $bukanPeserta sudah selesai dipakai di keadaan 2 di atas, jadi ia bisa
        // dipromosikan jadi peserta di sini tanpa menuntut karyawan keempat.
        DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
            'Id_Master_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => $bukanPeserta,
            'Start_Periode' => null,
            'Keterangan' => 'Uji',
        ]);
        $this->assertSame('1', $bulan($bukanPeserta));
    }

    public function test_id_penjadwalan_tak_terbaca_tetap_ditolak(): void
    {
        // Perbaikan ini tidak boleh melonggarkan validasi masukan: token yang
        // tidak bisa didekode tetap 400, bukan diam-diam dianggap jadwal apa pun.
        $this->masukSebagai($this->kodeKaryawanMana());

        $this->assertSame(400, $this->panggil('token-ngawur', mentah: true)->getStatusCode());
    }

    // ---------------------------------------------------------------- helpers

    private function panggil(string $id, bool $mentah = false)
    {
        $token = $mentah ? $id : Hashids::connection('custom')->encode($id);

        return app(FormKpiController::class)->getDataCurrent(
            Request::create('/api/v1/form-kpi/current', 'GET', ['Id_Penjadwalan' => $token]),
        );
    }

    /**
     * Kode karyawan milik setiap akun, DALAM SATU KUERI.
     *
     * Bentuk sebelumnya — $model::query()->get() lalu membaca ->karyawan tiap
     * baris — adalah N+1: 945 akun berarti 946 kueri. Terhadap DB dev yang
     * jaraknya ~138 ms per kueri, satu pemanggilan saja memakan ~140 detik,
     * dan berkas ini memanggilnya dua kali per test. Itulah, bukan logikanya,
     * yang membuat uji-uji di sini berjalan tiga menitan.
     *
     * @return \Illuminate\Support\Collection<int, string> [Id_Users => Kode_Karyawan]
     */
    private function kodeKaryawanPerAkun(): \Illuminate\Support\Collection
    {
        $model = config('auth.providers.users.model');
        $tabelUser = (new $model)->getTable();

        return DB::table($tabelUser.' as u')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
            ->whereNotNull('k.Kode_Karyawan')
            ->orderBy('u.Id_Users')
            ->pluck('k.Kode_Karyawan', 'u.Id_Users');
    }

    private function masukSebagai(string $kodeKaryawan): void
    {
        $idUser = $this->kodeKaryawanPerAkun()->search($kodeKaryawan);

        if ($idUser === false) {
            $this->markTestSkipped("Tidak ada akun untuk karyawan {$kodeKaryawan}.");
        }

        $model = config('auth.providers.users.model');
        $user = $model::find($idUser);

        if (! $user) {
            $this->markTestSkipped("Akun {$idUser} tidak bisa dimuat.");
        }

        Auth::setUser($user);
    }

    private function kodeKaryawanMana(): string
    {
        $kode = $this->kodeKaryawanPerAkun()->first();

        if (! $kode) {
            $this->markTestSkipped('Tidak ada akun yang tertaut ke karyawan.');
        }

        return $kode;
    }

    /**
     * Jadwal perorangan dengan DUA peserta berbulan-mulai berbeda, plus satu
     * karyawan yang sengaja bukan peserta.
     *
     * @return array{0:int,1:string,2:string}
     */
    private function siapkanJadwalPerorangan(): array
    {
        $kode = $this->kodeKaryawanPerAkun()->unique()->values();

        if ($kode->count() < 3) {
            $this->markTestSkipped('Butuh minimal tiga akun bertaut karyawan.');
        }

        $idJadwal = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Periode' => null,
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
        ]);

        // Peserta yang diuji sengaja BUKAN yang paling awal.
        DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
            ['Id_Master_Penjadwalan' => $idJadwal, 'Kode_Karyawan' => $kode[0], 'Start_Periode' => '2026-01', 'Keterangan' => 'Uji'],
            ['Id_Master_Penjadwalan' => $idJadwal, 'Kode_Karyawan' => $kode[1], 'Start_Periode' => '2026-06', 'Keterangan' => 'Uji'],
        ]);

        return [$idJadwal, $kode[1], $kode[2]];
    }
}
