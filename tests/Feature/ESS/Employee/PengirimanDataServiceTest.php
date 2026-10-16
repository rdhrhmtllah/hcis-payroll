<?php

namespace Tests\Feature\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\PengirimanDataService;
use App\Services\ESS\Employee\DataCollection\SkemaPengumpulanData;
use App\Services\ESS\Employee\DataCollection\StatusKaryawanResolver;
use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use App\Services\ESS\Employee\DataCollection\SudahTerkirimException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PengirimanDataServiceTest extends TestCase
{
    use DatabaseTransactions;

    private function service(): PengirimanDataService
    {
        return app(PengirimanDataService::class);
    }

    private function kodeNonPhl(): string
    {
        $kode = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where(fn ($w) => $w->where('lvl.ID_Level', '<>', 21)->orWhereNull('lvl.ID_Level'))
            ->value('k.Kode_Karyawan');

        $this->assertNotNull($kode, 'Butuh minimal satu karyawan non-PHL aktif di DB.');

        // Bersihkan jejak agar test tidak bergantung pada urutan jalan.
        DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Kode_Karyawan', $kode)->delete();
        DB::table('N_HRIS_Pengumpulan_Data_Status')->where('Kode_Karyawan', $kode)->delete();
        app(StatusPengumpulanService::class)->bersihkanCache($kode);

        return $kode;
    }

    private function dataLengkap(): array
    {
        return [
            'nama_lengkap' => 'Budi Santoso',
            'nik_ktp' => '3201234567890123',
            'tanggal_lahir' => '1995-04-17',
            'jenis_kelamin' => 'L',
            'email' => 'budi@evonusabersaudara.co.id',
            'no_hp' => '6281234567890',
            'departemen' => 'IT',
            'pendidikan_terakhir' => 'S1',
            'status_pernikahan' => 'Menikah',
            'jumlah_tanggungan' => '2',
            'darurat_nama' => 'Siti Aminah',
            'darurat_hubungan' => 'Pasangan',
            'darurat_no_hp' => '6281298765432',
        ];
    }

    /**
     * Yang menentukan tuntutan ini server, bukan browser. Kalau tuntutannya
     * hanya hidup di browser, kiriman yang dibuat di luar wizard lolos tanpa
     * angka ini.
     *
     * Ketiga status diuji, bukan hanya 'Menikah': anak tetap menjadi tanggungan
     * setelah bercerai atau ditinggal wafat, dan itulah dua keadaan yang paling
     * mudah terlewat karena kata 'tanggungan' terlanjur terbaca sebagai urusan
     * orang yang masih berpasangan.
     */
    public function test_jumlah_tanggungan_wajib_pada_setiap_status_kecuali_belum_menikah(): void
    {
        foreach (['Menikah', 'Cerai Hidup', 'Cerai Mati'] as $status) {
            $kode = $this->kodeNonPhl();

            $data = $this->dataLengkap();
            $data['status_pernikahan'] = $status;
            unset($data['jumlah_tanggungan']);

            try {
                $this->service()->kirim($kode, $data);
                $this->fail("Kiriman '{$status}' tanpa jumlah tanggungan seharusnya ditolak.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('jumlah_tanggungan', $e->errors(), $status);
            }
        }
    }

    /**
     * Kebalikannya juga harus dijaga: yang belum menikah tidak boleh ikut
     * dituntut, DAN angka yang tetap terkirim dari browser tidak boleh ikut
     * tersimpan. Nilai sisa dari pilihan yang sudah diganti adalah data yang
     * salah, bukan data tambahan.
     */
    public function test_jumlah_tanggungan_dibuang_bila_belum_menikah(): void
    {
        $kode = $this->kodeNonPhl();

        $data = $this->dataLengkap();
        $data['status_pernikahan'] = 'Belum Menikah';
        $data['jumlah_tanggungan'] = '5';

        $id = $this->service()->kirim($kode, $data);

        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertArrayNotHasKey('jumlah_tanggungan', $tersimpan);
    }

    /** Nol adalah jawaban yang sah, bukan isian kosong. */
    public function test_jumlah_tanggungan_nol_diterima(): void
    {
        $kode = $this->kodeNonPhl();

        $id = $this->service()->kirim($kode, ['jumlah_tanggungan' => '0'] + $this->dataLengkap());

        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertSame('0', (string) $tersimpan['jumlah_tanggungan']);
    }

    public function test_pengiriman_sah_menyimpan_json_dan_mengunci_status(): void
    {
        $kode = $this->kodeNonPhl();

        $id = $this->service()->kirim($kode, $this->dataLengkap());

        $baris = DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->first();

        $this->assertSame('Y', $baris->Flag_Aktif);
        // Versi diambil dari skema yang berlaku, bukan angka yang disalin ke
        // dalam uji: daftar pertanyaan memang naik versi saat isinya berubah.
        $this->assertSame(app(SkemaPengumpulanData::class)->versi(), (int) $baris->Versi_Skema);
        $this->assertSame('3201234567890123', json_decode($baris->Data_Json, true)['nik_ktp']);
        $this->assertNotNull($baris->Snapshot_Lama_Json);

        $this->assertSame('SELESAI', app(StatusPengumpulanService::class)->untuk($kode)['status']);
    }

    public function test_snapshot_dikunci_dengan_nama_field_kita(): void
    {
        $kode = $this->kodeNonPhl();

        $id = $this->service()->kirim($kode, $this->dataLengkap());
        $snapshot = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Snapshot_Lama_Json'),
            true,
        );

        // Kunci snapshot harus sama dengan kunci jawaban, supaya fase 4 tinggal
        // membandingkan dua peta — bukan menerjemahkan nama kolom lebih dulu.
        $this->assertArrayHasKey('nama_lengkap', $snapshot);
        $this->assertArrayHasKey('nik_ktp', $snapshot);
        $this->assertArrayHasKey('no_hp', $snapshot);
        $this->assertArrayHasKey('departemen', $snapshot);
    }

    public function test_nik_kurang_dari_enam_belas_digit_ditolak(): void
    {
        $kode = $this->kodeNonPhl();
        $data = $this->dataLengkap();
        $data['nik_ktp'] = '32012345';

        $this->expectException(ValidationException::class);
        $this->service()->kirim($kode, $data);
    }

    public function test_field_khusus_phl_dari_non_phl_tidak_ikut_tersimpan(): void
    {
        $kode = $this->kodeNonPhl();
        $data = $this->dataLengkap();
        $data['atasan_spv'] = 'DISUSUPKAN';
        $data['atasan_dept_head'] = 'DISUSUPKAN';
        // 'departemen' sudah ada di dataLengkap() dan sejak 14 Agt 2026 juga
        // khusus PHL — dikirim dari browser non-PHL, harus dibuang server.

        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertArrayNotHasKey('atasan_spv', $tersimpan);
        $this->assertArrayNotHasKey('atasan_dept_head', $tersimpan);
        $this->assertArrayNotHasKey('departemen', $tersimpan);

        // Yang berlaku untuk semua orang tetap tersimpan — penyaringan tidak
        // boleh kebablasan membuang isian yang sah.
        $this->assertSame('6281234567890', $tersimpan['no_hp']);
        $this->assertSame('Menikah', $tersimpan['status_pernikahan']);
    }

    public function test_pengiriman_kedua_ditolak(): void
    {
        $kode = $this->kodeNonPhl();
        $this->service()->kirim($kode, $this->dataLengkap());

        $this->expectException(SudahTerkirimException::class);
        $this->service()->kirim($kode, $this->dataLengkap());
    }

    /**
     * Katup darurat admin tidak boleh ikut tertutup oleh penjaga kirim-ganda.
     *
     * Penjaga TOCTOU sempat ditulis berdasarkan "sudah ada kiriman aktif".
     * Itu salah: bukaKunci() hanya mengembalikan Status ke 'BELUM' dan TIDAK
     * menyentuh Flag_Aktif pada tabel Submission — jadi orang yang baru
     * dibukakan kuncinya akan ditolak SELAMANYA, dan katup daruratnya mati
     * tanpa suara.
     *
     * Penjaga ini menuntut kedua sifat berlaku BERSAMAAN: kirim kedua ditolak,
     * tetapi kirim sesudah buka-kunci diterima.
     */
    public function test_kirim_ulang_setelah_buka_kunci_diterima(): void
    {
        $kode = $this->kodeNonPhl();
        $idPertama = $this->service()->kirim($kode, $this->dataLengkap());

        app(\App\Services\ESS\Employee\DataCollection\AdminPengumpulanDataService::class)
            ->bukaKunci($kode, 'Salah input, dibuka untuk diperbaiki', 'UJI');

        $idKedua = $this->service()->kirim($kode, $this->dataLengkap());

        $this->assertNotSame($idPertama, $idKedua, 'Kiriman sesudah buka-kunci harus menghasilkan baris baru.');

        $aktif = \Illuminate\Support\Facades\DB::table('N_HRIS_Pengumpulan_Data_Submission')
            ->where('Kode_Karyawan', $kode)
            ->where('Flag_Aktif', 'Y')
            ->count();

        $this->assertSame(1, $aktif, 'Tetap hanya boleh ada SATU kiriman aktif setelah kirim ulang.');
    }

    /**
     * Atasan PHL disimpan sebagai Kode_Karyawan dan dipakai sebagai relasi.
     * Dulu isiannya kotak teks bebas dan server hanya menuntut string <= 20,
     * sehingga 'asdf' tersimpan sebagai atasan.
     */
    public function test_atasan_phl_yang_bukan_kode_karyawan_aktif_ditolak(): void
    {
        $kode = $this->kodeNonPhl();
        $this->anggapPhl();
        $this->nyalakanPencarianAtasan();

        $data = $this->dataLengkap();
        $data['atasan_spv'] = 'BUKAN-KODE';
        $data['atasan_dept_head'] = 'asdf';

        try {
            $this->service()->kirim($kode, $data);
            $this->fail('Atasan yang bukan Kode_Karyawan aktif harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('atasan_spv', $e->errors());
            $this->assertArrayHasKey('atasan_dept_head', $e->errors());
        }
    }

    public function test_atasan_phl_berupa_kode_karyawan_aktif_diterima(): void
    {
        $kode = $this->kodeNonPhl();
        $this->anggapPhl();
        $this->nyalakanPencarianAtasan();

        $atasan = $this->karyawanBerlevel([3, 4, 5, 6, 7, 8, 9, 10, 11, 12], $kode)->limit(2)->pluck('k.Kode_Karyawan')->all();
        $this->assertCount(2, $atasan, 'Butuh dua karyawan aktif level 3-12 sebagai atasan.');

        $data = $this->dataLengkap();
        $data['atasan_spv'] = $atasan[0];
        $data['atasan_dept_head'] = $atasan[1];

        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertSame($atasan[0], $tersimpan['atasan_spv']);
        $this->assertSame($atasan[1], $tersimpan['atasan_dept_head']);
    }

    /**
     * Pertanyaan PHL yang dimatikan lewat env tidak dituntut dan jawabannya
     * dibuang — kalau hanya disembunyikan di wizard, Kirim akan menuntut isian
     * yang kotaknya tidak pernah ada.
     */
    public function test_pertanyaan_phl_yang_dimatikan_tidak_dituntut_dan_tidak_tersimpan(): void
    {
        $kode = $this->kodeNonPhl();
        $this->anggapPhl();
        config([
            'services.hcis.pengumpulan_data.pertanyaan_aktif.departemen' => false,
            'services.hcis.pengumpulan_data.pertanyaan_aktif.atasan_spv' => false,
            'services.hcis.pengumpulan_data.pertanyaan_aktif.atasan_dept_head' => false,
        ]);

        $data = $this->dataLengkap();
        $data['departemen'] = 'DISUSUPKAN';
        // atasan_spv / atasan_dept_head sengaja tidak dikirim: tidak boleh dituntut.

        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertArrayNotHasKey('departemen', $tersimpan);
        $this->assertArrayNotHasKey('atasan_spv', $tersimpan);
        $this->assertSame('Menikah', $tersimpan['status_pernikahan']);
    }

    /**
     * Atasan dibatasi ID_Level yang diizinkan (baku 3-12). Staff (level 2) atau
     * Freelance (21) yang dikirim lewat POST langsung harus ditolak, walau aktif.
     */
    public function test_atasan_di_luar_level_yang_diizinkan_ditolak(): void
    {
        $kode = $this->kodeNonPhl();
        $this->anggapPhl();
        $this->nyalakanPencarianAtasan();

        $staf = $this->karyawanBerlevel([2], $kode)->value('k.Kode_Karyawan');
        $manajer = $this->karyawanBerlevel([8, 10], $kode)->value('k.Kode_Karyawan');
        $this->assertNotNull($staf, 'Butuh karyawan aktif level 2.');
        $this->assertNotNull($manajer, 'Butuh karyawan aktif level 8/10.');

        $data = $this->dataLengkap();
        $data['atasan_spv'] = $staf;
        $data['atasan_dept_head'] = $manajer;

        try {
            $this->service()->kirim($kode, $data);
            $this->fail('Atasan level 2 seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('atasan_spv', $e->errors());
            $this->assertArrayNotHasKey('atasan_dept_head', $e->errors());
        }

        // Level dibaca dari config (env), bukan ditanam: mengizinkan level 2
        // membuat kiriman yang sama diterima.
        config(['services.hcis.pengumpulan_data.atasan_id_level' => [2, 8, 10]]);
        $this->assertIsInt($this->service()->kirim($kode, $data));
    }

    /** Draf lama atau kiriman dari luar wizard bisa membawa '01'. */
    public function test_jumlah_tanggungan_dengan_nol_di_depan_dirapikan_bukan_ditolak(): void
    {
        $kode = $this->kodeNonPhl();
        $data = $this->dataLengkap();
        $data['jumlah_tanggungan'] = '02';

        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'), true);

        $this->assertSame('2', (string) $tersimpan['jumlah_tanggungan']);
    }

    /**
     * SQL Server tidak membedakan besar-kecil huruf: 'r52' lolos 'exists'
     * untuk R52. Yang tersimpan harus bentuk resminya, supaya nama atasan
     * tetap bisa dicari kembali.
     */
    public function test_kode_atasan_disimpan_dalam_bentuk_resmi(): void
    {
        $kode = $this->kodeNonPhl();
        $this->anggapPhl();
        $this->nyalakanPencarianAtasan();

        $atasan = $this->karyawanBerlevel([8, 10], $kode)
            ->whereRaw("k.Kode_Karyawan COLLATE Latin1_General_CS_AS <> LOWER(k.Kode_Karyawan)")
            ->value('k.Kode_Karyawan');
        $this->assertNotNull($atasan, 'Butuh atasan level 8/10 yang kodenya berhuruf.');

        $data = $this->dataLengkap();
        $data['atasan_spv'] = strtolower($atasan);
        $data['atasan_dept_head'] = $atasan;

        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'), true);

        $this->assertSame($atasan, $tersimpan['atasan_spv']);
    }

    private function karyawanBerlevel(array $level, string $kecuali): \Illuminate\Database\Query\Builder
    {
        return DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where('k.Kode_Karyawan', '<>', $kecuali)
            ->whereIn('v.ID_Level', $level)
            ->orderBy('k.Kode_Karyawan');
    }

    /** Status PHL diturunkan dari data kepegawaian; untuk uji cukup dianggap. */
    private function anggapPhl(): void
    {
        $this->app->instance(StatusKaryawanResolver::class, new class extends StatusKaryawanResolver
        {
            public function isPhl(string $kodeKaryawan): bool
            {
                return true;
            }
        });
        $this->app->forgetInstance(PengirimanDataService::class);
    }

    /** Pencarian atasan disembunyikan sejak 19 Sep 2026; ujinya tetap menjaga jalur itu. */
    private function nyalakanPencarianAtasan(): void
    {
        config(['services.hcis.pengumpulan_data.atasan_pakai_pencarian' => true]);
    }

    /**
     * Atasan PHL kini ditulis sendiri (teks bebas): nama yang bukan kode
     * karyawan diterima dan tersimpan apa adanya, yang terlalu pendek ditolak.
     */
    public function test_atasan_phl_teks_bebas_diterima_apa_adanya(): void
    {
        $kode = $this->kodeNonPhl();
        $this->anggapPhl();

        $data = $this->dataLengkap();
        $data['atasan_spv'] = 'Bu';
        $data['atasan_dept_head'] = 'Pak Budi Santoso';

        try {
            $this->service()->kirim($kode, $data);
            $this->fail('Nama atasan kurang dari 3 huruf seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('atasan_spv', $e->errors());
            $this->assertArrayNotHasKey('atasan_dept_head', $e->errors());
        }

        $data['atasan_spv'] = 'Siti Rahayu (SPV Gudang)';
        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertSame('Siti Rahayu (SPV Gudang)', $tersimpan['atasan_spv']);
        $this->assertSame('Pak Budi Santoso', $tersimpan['atasan_dept_head']);
    }

    /**
     * 'D4/S1' dipisah pada skema versi 4. Kiriman BARU berisi gabungan lama
     * harus ditolak, sementara D4 dan S1 masing-masing diterima.
     */
    public function test_pendidikan_gabungan_d4_s1_ditolak_sejak_dipisah(): void
    {
        $kode = $this->kodeNonPhl();
        $data = $this->dataLengkap();
        $data['pendidikan_terakhir'] = 'D4/S1';

        try {
            $this->service()->kirim($kode, $data);
            $this->fail("'D4/S1' seharusnya ditolak sejak dipisah.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('pendidikan_terakhir', $e->errors());
        }

        $data['pendidikan_terakhir'] = 'D4';
        $id = $this->service()->kirim($kode, $data);
        $tersimpan = json_decode(
            DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Data_Json'),
            true,
        );

        $this->assertSame('D4', $tersimpan['pendidikan_terakhir']);
        $this->assertSame(4, (int) DB::table('N_HRIS_Pengumpulan_Data_Submission')->where('Id', $id)->value('Versi_Skema'));
    }

}
