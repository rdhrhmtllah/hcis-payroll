<?php

namespace Tests\Feature\KPI\Planning;

use App\Http\Controllers\KPI\Planning\Scheduling\PenjadwalanKpiController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Layar pemilihan peserta penjadwalan KPI.
 *
 * Dua cacat yang diperbaiki:
 * 1. Pengajuan DITOLAK ikut terhitung "sudah isi" sehingga karyawan terkunci,
 *    padahal justru butuh jendelanya dibuka lagi.
 * 2. Status tidak dipisah per jalur, sehingga karyawan yang jalur personalnya
 *    selesai tapi jalur timnya belum ikut terkunci.
 */
class KpiPenjadwalanPesertaTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function ambilDaftar(string $tahun, int $bulan): array
    {
        $request = Request::create('/api/v1/penjadwalan-kpi/current/karyawan', 'GET', [
            'tahun' => $tahun,
            'periode' => (string) $bulan,
        ]);

        $response = (new PenjadwalanKpiController)->getDataKaryawan($request);

        $this->assertSame(200, $response->getStatusCode(), 'Endpoint peserta gagal.');

        return json_decode($response->getContent(), true)['result'] ?? [];
    }

    /**
     * Realisasi hidup yang ditolak.
     *
     * $sudahDijadwalkan memilih apakah karyawannya SUDAH punya jadwal penilaian
     * perorangan aktif untuk periode itu. Pembedaan ini penting: karyawan yang
     * jendelanya masih terbuka memang tetap terkunci (mencegah jadwal ganda),
     * jadi dua keadaan itu tidak boleh tertukar dan membuat test lulus semu.
     */
    private function cariDitolak(bool $sudahDijadwalkan): ?object
    {
        $query = DB::table('KPI_Update_Performance as kup')
            ->join('KPI_Pengajuan as kp', 'kp.Id_Pengajuan', '=', 'kup.Id_Pengajuan')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'kup.Id_User')
            ->where('kup.Flag_Aktif', 'Y')
            ->whereNull('kup.Status')
            ->where('kup.Status_Pengajuan', 'ditolak')
            ->where('kp.Flag_Aktif', 'Y')
            ->where('k.Aktif', 'Y')
            ->select('k.Kode_Karyawan', 'k.Nama', 'kup.Periode');

        $punyaJadwal = function ($sub) {
            $sub->select(DB::raw(1))
                ->from('N_KPI_Master_Penjadwalan_Perorangan as np')
                ->join('KPI_Master_Penjadwalan as nm', 'nm.Id_Master_Penjadwalan', '=', 'np.Id_Master_Penjadwalan')
                ->whereColumn('np.Kode_Karyawan', 'k.Kode_Karyawan')
                ->whereColumn('nm.Periode', 'kup.Periode')
                ->where('np.Flag_Aktif', 'Y')
                ->where('nm.Flag_Aktif', 'Y')
                ->where('nm.Flag_Perorangan', 'Y')
                ->where('nm.Flag_Penilaian_KPI', 'Y');
        };

        return $sudahDijadwalkan
            ? $query->whereExists($punyaJadwal)->first()
            : $query->whereNotExists($punyaJadwal)->first();
    }

    public function test_pengajuan_ditolak_tidak_mengunci_karyawan(): void
    {
        $ditolak = $this->cariDitolak(false);

        if (! $ditolak) {
            $this->markTestSkipped('Tidak ada realisasi ditolak tanpa jadwal aktif di data dev.');
        }

        [$tahun, $bulan] = explode('-', $ditolak->Periode);
        $baris = collect($this->ambilDaftar($tahun, (int) $bulan))
            ->firstWhere('kode_karyawan', $ditolak->Kode_Karyawan);

        $this->assertNotNull($baris, "{$ditolak->Nama} tidak muncul di daftar peserta.");

        $this->assertFalse(
            $baris['is_locked'],
            "{$ditolak->Nama} terkunci padahal pengajuannya ditolak dan perlu dibuka lagi.",
        );
        $this->assertFalse(
            $baris['is_filled'],
            'Pengajuan ditolak tidak boleh dihitung sebagai sudah selesai.',
        );
        $this->assertSame('Perlu Perbaikan', $baris['status_kpi']);
    }

    /**
     * Karyawan yang ditolak TAPI jendelanya masih terbuka tetap terkunci — supaya
     * tidak dibuatkan jadwal ganda. Yang wajib berubah adalah alasannya: bukan
     * lagi "Selesai" (yang membuat admin mengira tidak ada pekerjaan), melainkan
     * "Sudah Dijadwalkan" disertai rincian jalur "Perlu Perbaikan".
     */
    public function test_ditolak_tapi_masih_terjadwal_bukan_selesai(): void
    {
        $ditolak = $this->cariDitolak(true);

        if (! $ditolak) {
            $this->markTestSkipped('Tidak ada realisasi ditolak yang jadwalnya masih terbuka.');
        }

        [$tahun, $bulan] = explode('-', $ditolak->Periode);
        $baris = collect($this->ambilDaftar($tahun, (int) $bulan))
            ->firstWhere('kode_karyawan', $ditolak->Kode_Karyawan);

        $this->assertNotNull($baris, "{$ditolak->Nama} tidak muncul di daftar peserta.");

        $this->assertNotSame(
            'Selesai',
            $baris['status_kpi'],
            "{$ditolak->Nama} ditolak tapi masih dilaporkan Selesai.",
        );
        $this->assertFalse($baris['is_filled'], 'Pengajuan ditolak bukan berarti selesai.');
        $this->assertSame('Sudah Dijadwalkan', $baris['status_kpi']);
        $this->assertTrue(
            collect($baris['jalur'])->contains(fn ($j) => $j['status_kpi'] === 'Perlu Perbaikan'),
            'Rincian jalur tidak menyebut bahwa ada yang perlu diperbaiki.',
        );
    }

    public function test_status_dipisah_per_jalur(): void
    {
        $ditolak = $this->cariDitolak(false) ?? $this->cariDitolak(true);

        if (! $ditolak) {
            $this->markTestSkipped('Tidak ada realisasi ditolak yang masih hidup di data dev.');
        }

        [$tahun, $bulan] = explode('-', $ditolak->Periode);
        $baris = collect($this->ambilDaftar($tahun, (int) $bulan))
            ->firstWhere('kode_karyawan', $ditolak->Kode_Karyawan);

        $this->assertIsArray($baris['jalur']);
        $this->assertNotEmpty($baris['jalur'], 'Rincian per jalur kosong.');

        foreach ($baris['jalur'] as $j) {
            $this->assertContains($j['jalur'], ['personal', 'tim']);
            $this->assertContains(
                $j['status_kpi'],
                ['Selesai', 'Sedang Diproses', 'Perlu Perbaikan', 'Belum Diisi'],
            );
        }

        $this->assertTrue(
            collect($baris['jalur'])->contains(fn ($j) => $j['status_kpi'] === 'Perlu Perbaikan'),
            'Jalur yang ditolak tidak ditandai "Perlu Perbaikan".',
        );
    }

    /** is_filled hanya benar bila SEMUA jalur karyawan itu selesai. */
    public function test_is_filled_hanya_bila_semua_jalur_selesai(): void
    {
        $periode = DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Status')
            ->orderByDesc('Periode')
            ->value('Periode');

        if (! $periode) {
            $this->markTestSkipped('Tidak ada realisasi hidup di data dev.');
        }

        [$tahun, $bulan] = explode('-', $periode);

        foreach ($this->ambilDaftar($tahun, (int) $bulan) as $baris) {
            $semuaSelesai = collect($baris['jalur'])->every(fn ($j) => $j['selesai'] === true);

            $this->assertSame(
                $semuaSelesai && count($baris['jalur']) > 0,
                $baris['is_filled'],
                "is_filled tidak konsisten dgn rincian jalur untuk {$baris['nama_karyawan']}.",
            );
        }
    }

    /** Karyawan tidak boleh muncul dua kali walau punya pengajuan personal & tim. */
    public function test_karyawan_tidak_tampil_ganda(): void
    {
        $baris = collect($this->ambilDaftar('2026', 6));
        $kode = $baris->pluck('kode_karyawan');

        $this->assertSame(
            $kode->unique()->count(),
            $kode->count(),
            'Ada karyawan tampil lebih dari sekali.',
        );
    }

    /** Pengajuan dari tahun lain tidak boleh ikut terjaring. */
    public function test_pengajuan_tahun_lain_tidak_ikut(): void
    {
        $tahunAda = DB::table('KPI_Master_Penjadwalan')
            ->whereNotNull('Tahun')
            ->distinct()
            ->pluck('Tahun');

        // Tahun yang pasti tidak punya jadwal apa pun.
        $tahunKosong = 1999;

        if ($tahunAda->contains($tahunKosong)) {
            $this->markTestSkipped('Tahun pembanding ternyata dipakai di data dev.');
        }

        $this->assertEmpty(
            $this->ambilDaftar((string) $tahunKosong, 6),
            'Masih ada karyawan terjaring untuk tahun tanpa jadwal sama sekali.',
        );
    }

    /**
     * Menyunting jadwal tidak boleh mengaktifkan kembali peserta yang sengaja
     * dinonaktifkan. Dulu seluruh peserta dihapus lalu disisipkan ulang dengan
     * Flag_Aktif='Y', jadi mengubah tanggal saja sudah menghapus hasil toggle.
     */
    public function test_menyunting_jadwal_tidak_mengaktifkan_ulang_peserta(): void
    {
        $jadwal = DB::table('KPI_Master_Penjadwalan as m')
            ->join('N_KPI_Master_Penjadwalan_Perorangan as p', 'p.Id_Master_Penjadwalan', '=', 'm.Id_Master_Penjadwalan')
            ->where('m.Flag_Perorangan', 'Y')
            ->where('m.Flag_Form_KPI', 'Y')
            ->select('m.*')
            ->first();

        if (! $jadwal) {
            $this->markTestSkipped('Tidak ada jadwal pengisian perorangan di data dev.');
        }

        $peserta = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->where('Id_Master_Penjadwalan', $jadwal->Id_Master_Penjadwalan)
            ->get();

        // Satu peserta sudah cukup: yang diuji adalah Flag_Aktif-nya bertahan.
        if ($peserta->isEmpty()) {
            $this->markTestSkipped('Jadwal terpilih tidak punya peserta.');
        }

        $dinonaktifkan = $peserta->first();

        DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->where('Id_Master_Penjadwalan_Perorangan', $dinonaktifkan->Id_Master_Penjadwalan_Perorangan)
            ->update(['Flag_Aktif' => 'T']);

        $user = User::whereNotNull('Id_Users')->first();

        if (! $user) {
            $this->markTestSkipped('Tidak ada user untuk bertindak sebagai admin.');
        }

        $this->actingAs($user);

        $request = Request::create('/x', 'PUT', [
            'Nama_Kegiatan' => $jadwal->Nama_Kegiatan,
            'Deskripsi' => $jadwal->Deskripsi ?: 'Deskripsi uji',
            'Jadwal_Mulai' => $jadwal->Jadwal_Mulai,
            'Jadwal_Akhir' => $jadwal->Jadwal_Akhir,
            'Jenis_Kegiatan' => 'pengisian',
            'Tahun' => (string) $jadwal->Tahun,
            'Periode' => null,
            'Flag_Perorangan' => 'Y',
            'Alasan_Pembukaan' => 'Penyesuaian tanggal jadwal untuk pengujian',
            'Peserta' => $peserta
                ->map(fn ($p) => [
                    'Kode_Karyawan' => $p->Kode_Karyawan,
                    'Custom_Periode' => $p->Start_Periode ? substr($p->Start_Periode, 5, 2) : null,
                ])
                ->values()
                ->all(),
        ]);

        $response = (new PenjadwalanKpiController)->update(
            $request,
            Hashids::connection('custom')->encode($jadwal->Id_Master_Penjadwalan),
        );

        $this->assertSame(200, $response->getStatusCode(), 'Update jadwal gagal: '.$response->getContent());

        $sesudah = DB::table('N_KPI_Master_Penjadwalan_Perorangan')
            ->where('Id_Master_Penjadwalan', $jadwal->Id_Master_Penjadwalan)
            ->where('Kode_Karyawan', $dinonaktifkan->Kode_Karyawan)
            ->first();

        $this->assertNotNull($sesudah, 'Peserta yang masih dikirim malah hilang.');
        $this->assertSame(
            'T',
            $sesudah->Flag_Aktif,
            'Peserta yang dinonaktifkan aktif kembali setelah jadwal disunting.',
        );

        $this->assertSame(
            $peserta->count(),
            DB::table('N_KPI_Master_Penjadwalan_Perorangan')
                ->where('Id_Master_Penjadwalan', $jadwal->Id_Master_Penjadwalan)
                ->count(),
            'Jumlah peserta berubah padahal daftarnya sama.',
        );
    }

    public function test_update_menolak_jenis_kegiatan_di_luar_daftar(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $user = User::whereNotNull('Id_Users')->first();
        $this->actingAs($user);

        $request = Request::create('/x', 'PUT', [
            'Nama_Kegiatan' => 'Uji',
            'Deskripsi' => 'Uji',
            'Jadwal_Mulai' => '2026-01-01',
            'Jadwal_Akhir' => '2026-01-31',
            'Jenis_Kegiatan' => 'entah-apa',
            'Tahun' => '2026',
            'Periode' => 1,
        ]);

        (new PenjadwalanKpiController)->update($request, Hashids::connection('custom')->encode(1));
    }

    /**
     * 'update' dan 'verifikasi' ditulis ke DB tapi tidak dibaca kode mana pun,
     * jadi keduanya tidak lagi diterima. Kalau suatu saat fiturnya dibangun,
     * test ini yang akan gagal lebih dulu dan mengingatkan.
     */
    public function test_jenis_yang_tidak_dibaca_kode_ditolak(): void
    {
        $user = User::whereNotNull('Id_Users')->first();

        if (! $user) {
            $this->markTestSkipped('Tidak ada user untuk bertindak sebagai admin.');
        }

        $this->actingAs($user);

        foreach (['update', 'verifikasi'] as $jenis) {
            $ditolak = false;

            try {
                (new PenjadwalanKpiController)->update(
                    Request::create('/x', 'PUT', [
                        'Nama_Kegiatan' => 'Uji',
                        'Deskripsi' => 'Uji',
                        'Jadwal_Mulai' => '2026-01-01',
                        'Jadwal_Akhir' => '2026-01-31',
                        'Jenis_Kegiatan' => $jenis,
                        'Tahun' => '2026',
                        'Periode' => 1,
                    ]),
                    Hashids::connection('custom')->encode(1),
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                $ditolak = true;
                $this->assertArrayHasKey('Jenis_Kegiatan', $e->errors());
            }

            $this->assertTrue($ditolak, "Jenis '{$jenis}' seharusnya sudah tidak diterima.");
        }
    }

    /** Dua jenis yang benar-benar dipakai harus tetap lolos validasi. */
    public function test_jenis_yang_dipakai_masih_diterima(): void
    {
        $user = User::whereNotNull('Id_Users')->first();

        if (! $user) {
            $this->markTestSkipped('Tidak ada user untuk bertindak sebagai admin.');
        }

        $this->actingAs($user);

        foreach (['pengisian', 'penilaian'] as $jenis) {
            try {
                (new PenjadwalanKpiController)->update(
                    Request::create('/x', 'PUT', [
                        'Nama_Kegiatan' => 'Uji Jenis Sah '.$jenis,
                        'Deskripsi' => 'Uji',
                        'Jadwal_Mulai' => '2026-01-01',
                        'Jadwal_Akhir' => '2026-01-31',
                        'Jenis_Kegiatan' => $jenis,
                        'Tahun' => '2026',
                        'Periode' => 1,
                    ]),
                    Hashids::connection('custom')->encode(1),
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->fail(
                    "Jenis '{$jenis}' tertolak validasi: ".json_encode($e->errors()),
                );
            } catch (\Throwable $e) {
                // Gagal karena sebab lain (mis. jadwal id 1 tidak ada) tidak relevan
                // di sini — yang diuji hanya lolosnya validasi Jenis_Kegiatan.
            }

            $this->assertTrue(true);
        }
    }

    public function test_update_menolak_periode_di_luar_1_sampai_12(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $user = User::whereNotNull('Id_Users')->first();
        $this->actingAs($user);

        $request = Request::create('/x', 'PUT', [
            'Nama_Kegiatan' => 'Uji',
            'Deskripsi' => 'Uji',
            'Jadwal_Mulai' => '2026-01-01',
            'Jadwal_Akhir' => '2026-01-31',
            'Jenis_Kegiatan' => 'penilaian',
            'Tahun' => '2026',
            'Periode' => 13,
        ]);

        (new PenjadwalanKpiController)->update($request, Hashids::connection('custom')->encode(1));
    }

    /** Jadwal global tanpa Periode dulu tersimpan sebagai bulan '00'. */
    public function test_store_menolak_periode_kosong_untuk_jadwal_global(): void
    {
        $user = User::whereNotNull('Id_Users')->first();
        $this->actingAs($user);

        $request = Request::create('/x', 'POST', [
            [
                'Nama_Kegiatan' => 'Uji Periode Kosong '.uniqid(),
                'Deskripsi' => 'Uji',
                'Jadwal_Mulai' => '2026-01-01',
                'Jadwal_Akhir' => '2026-01-31',
                'Jenis_Kegiatan' => 'penilaian',
                'Tahun' => '2026',
                'Flag_Perorangan' => 'T',
            ],
        ]);

        $response = (new PenjadwalanKpiController)->store($request);

        $this->assertSame(422, $response->getStatusCode(), 'Periode kosong seharusnya ditolak.');
        $this->assertStringContainsString('Periode wajib diisi', $response->getContent());
    }

    /**
     * Endpoint audit WAJIB baca-saja. Kalau ia sampai mengubah data, skor YTD
     * bergeser hanya karena seseorang membuka halaman.
     */
    public function test_endpoint_audit_baca_saja_dan_berbentuk_rapi(): void
    {
        $user = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('MasterPenjadwalan'));

        if (! $user) {
            $this->markTestSkipped('Tidak ada user dgn akses MasterPenjadwalan di data dev.');
        }

        $sebelumBaris = DB::table('KPI_Pengajuan_Det')->count();
        $sebelumTarget = (float) DB::table('KPI_Pengajuan_Det')->sum('Nilai_Target');

        $res = $this->actingAs($user)->getJson('/api/v1/penjadwalan-kpi/audit/target-sebelum-mulai');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'success',
            'status',
            'message',
            'result' => [
                'ringkasan' => [
                    'total_karyawan',
                    'total_baris',
                    'total_baris_bisa_dibersihkan',
                    'filter',
                ],
                'data',
            ],
        ]);

        $this->assertSame(
            $sebelumBaris,
            DB::table('KPI_Pengajuan_Det')->count(),
            'Endpoint audit menghapus baris.',
        );
        $this->assertSame(
            $sebelumTarget,
            (float) DB::table('KPI_Pengajuan_Det')->sum('Nilai_Target'),
            'Endpoint audit mengubah nilai target.',
        );

        foreach ($res->json('result.data') as $orang) {
            $this->assertArrayHasKey('bulan_mulai', $orang);
            $this->assertArrayHasKey('dampak_parameter', $orang);

            foreach ($orang['baris'] as $b) {
                $this->assertLessThan(
                    $orang['bulan_mulai'],
                    $b['periode'],
                    'Ada baris yang tidak lebih awal dari bulan mulai ikut dilaporkan.',
                );
            }
        }
    }

    public function test_endpoint_audit_menolak_tahun_ngawur(): void
    {
        $user = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('MasterPenjadwalan'));

        if (! $user) {
            $this->markTestSkipped('Tidak ada user dgn akses MasterPenjadwalan di data dev.');
        }

        $this->actingAs($user)
            ->getJson('/api/v1/penjadwalan-kpi/audit/target-sebelum-mulai?tahun=20xx')
            ->assertStatus(422);
    }

    private function ambilDaftarJadwal(array $params = []): array
    {
        $request = Request::create('/api/v1/penjadwalan-kpi/current', 'GET', $params);
        $response = (new PenjadwalanKpiController)->getDataCurrent($request);

        return [
            'status' => $response->getStatusCode(),
            'isi' => json_decode($response->getContent(), true),
        ];
    }

    /**
     * Halaman kosong bukan kesalahan. Dulu dibalas 404 sehingga frontend masuk
     * cabang catch dan meninggalkan total_page/total_data dalam keadaan lama —
     * kontrol paginasi menampilkan angka yang sudah tidak berlaku.
     */
    public function test_halaman_kosong_balas_200_bukan_404(): void
    {
        $hasil = $this->ambilDaftarJadwal(['page' => 9999, 'limit' => 10]);

        $this->assertSame(200, $hasil['status'], 'Halaman kosong seharusnya bukan 404.');
        $this->assertTrue($hasil['isi']['success']);
        $this->assertSame([], $hasil['isi']['result']);

        // total_data tetap menyebut jumlah sebenarnya, supaya paginasi bisa mundur.
        // Patokannya jumlah pada TAB yang diminta, bukan seluruh tabel: daftar
        // sekarang terbagi tab 'aktif'/'arsip', dan tanpa parameter status yang
        // dibuka adalah tab aktif (Flag_Arsip = 'T').
        $this->assertSame(
            DB::table('KPI_Master_Penjadwalan')->where('Flag_Arsip', 'T')->count(),
            $hasil['isi']['total_data'],
        );

        // Badge kedua tab ikut terkirim walau halamannya kosong, supaya frontend
        // tidak menampilkan hitungan basi saat pindah ke halaman di luar rentang.
        $this->assertArrayHasKey('tab_counts', $hasil['isi']);
    }

    public function test_pencarian_tanpa_hasil_balas_200_dgn_pesan(): void
    {
        $hasil = $this->ambilDaftarJadwal(['q' => 'zzz-tidak-mungkin-ada-zzz']);

        $this->assertSame(200, $hasil['status']);
        $this->assertSame([], $hasil['isi']['result']);
        $this->assertStringContainsString('pencarian', $hasil['isi']['message']);
    }

    /**
     * Klausa FORMAT(...) dilewati untuk kata tanpa angka. Hasilnya harus sama,
     * karena kolom tanggal/tahun/jam hanya berisi angka dan pemisah.
     */
    public function test_pencarian_teks_tetap_menemukan_nama_kegiatan(): void
    {
        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->whereRaw("Nama_Kegiatan NOT LIKE '%[0-9]%'")
            ->first();

        if (! $jadwal) {
            $this->markTestSkipped('Tidak ada Nama_Kegiatan tanpa angka di data dev.');
        }

        $kata = substr($jadwal->Nama_Kegiatan, 0, 4);
        $hasil = $this->ambilDaftarJadwal(['q' => $kata, 'limit' => 100]);

        $this->assertSame(200, $hasil['status']);
        $this->assertTrue(
            collect($hasil['isi']['result'])->contains(fn ($r) => str_contains($r['Nama_Kegiatan'], $kata)),
            "Pencarian teks '{$kata}' tidak menemukan jadwalnya.",
        );
    }

    /** Kata berangka tetap boleh mencocokkan tahun dan tanggal. */
    public function test_pencarian_berangka_masih_mencocokkan_tahun(): void
    {
        $tahun = DB::table('KPI_Master_Penjadwalan')->whereNotNull('Tahun')->value('Tahun');

        if (! $tahun) {
            $this->markTestSkipped('Tidak ada jadwal ber-Tahun di data dev.');
        }

        $hasil = $this->ambilDaftarJadwal(['q' => (string) $tahun, 'limit' => 100]);

        $this->assertSame(200, $hasil['status']);
        $this->assertNotEmpty(
            $hasil['isi']['result'],
            "Pencarian tahun {$tahun} tidak menemukan apa pun.",
        );
    }

    /** Validasi ketat dari perbaikan injeksi harus tetap berlaku. */
    public function test_validasi_periode_masih_ketat(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $request = Request::create('/api/v1/penjadwalan-kpi/current/karyawan', 'GET', [
            'tahun' => '2026',
            'periode' => "06' OR 1=1 --",
        ]);

        (new PenjadwalanKpiController)->getDataKaryawan($request);
    }
}
