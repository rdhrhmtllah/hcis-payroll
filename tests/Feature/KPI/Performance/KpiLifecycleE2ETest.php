<?php

namespace Tests\Feature\KPI\Performance;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Uji alur KPI Update Performance dari ujung ke ujung memakai rute HTTP asli
 * dan data dev. Semua penulisan dibungkus DatabaseTransactions sehingga
 * di-rollback otomatis — tidak ada baris yang tertinggal di database.
 */
class KpiLifecycleE2ETest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /** Leader yang punya anggota tim sekaligus akses halaman tim. */
    private function cariLeader(int $maksAnggota = 40): ?User
    {
        $kandidat = DB::table('KPI_Pengajuan')
            ->where('Flag_Team', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->select('Id_User_Sender', DB::raw('COUNT(DISTINCT Id_User) as anggota'))
            ->groupBy('Id_User_Sender')
            ->havingRaw('COUNT(DISTINCT Id_User) BETWEEN 1 AND ?', [$maksAnggota])
            ->orderByDesc(DB::raw('COUNT(DISTINCT Id_User)'))
            ->get();

        foreach ($kandidat as $k) {
            $user = User::find($k->Id_User_Sender);
            if ($user && $user->memilikiJabatan('UpdatePerformanceTeam')) {
                return $user;
            }
        }

        return null;
    }

    private function hash($id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    // ---------------------------------------------------------------- listing

    public function test_tiga_tab_listing_tim_konsisten_dan_tidak_tumpang_tindih(): void
    {
        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $ambil = function (string $url) use ($leader) {
            $res = $this->actingAs($leader)->getJson($url.'?limit=500&page=1');
            $this->assertContains($res->status(), [200, 404], "Status tak terduga di {$url}: ".$res->status());

            return $res->status() === 200 ? collect($res->json('result') ?? []) : collect();
        };

        $toUpdate = $ambil('/api/v1/update-performance/list-Kpi-team');
        $onGoing = $ambil('/api/v1/update-performance/list-Kpi-team/on-progress');
        $rejected = $ambil('/api/v1/update-performance/list-Kpi-team/rejected');

        $kunci = fn ($rows) => collect($rows)
            ->filter(fn ($r) => is_array($r) && isset($r['id_user']))
            ->map(fn ($r) => $r['id_user'].'|'.($r['periode_raw'] ?? '?'))
            ->values();

        $kToUpdate = $kunci($toUpdate);
        $kOnGoing = $kunci($onGoing);
        $kRejected = $kunci($rejected);

        // Tidak boleh ada pasangan (anggota, periode) yang sama muncul di 2 tab.
        $this->assertEmpty(
            $kToUpdate->intersect($kOnGoing)->all(),
            'Pasangan anggota+periode muncul di To Update sekaligus On Going.',
        );
        $this->assertEmpty(
            $kToUpdate->intersect($kRejected)->all(),
            'Pasangan anggota+periode muncul di To Update sekaligus Returned.',
        );
        $this->assertEmpty(
            $kOnGoing->intersect($kRejected)->all(),
            'Pasangan anggota+periode muncul di On Going sekaligus Returned.',
        );

        // Tidak boleh ada baris kembar di dalam satu tab.
        foreach (['To Update' => $kToUpdate, 'On Going' => $kOnGoing, 'Returned' => $kRejected] as $nama => $k) {
            $this->assertSame(
                $k->count(),
                $k->unique()->count(),
                "Ada baris duplikat (anggota+periode) di tab {$nama}.",
            );
        }

        // Setiap baris wajib membawa periode + hash jadwal supaya navigasi tepat sasaran.
        foreach ($toUpdate as $row) {
            $this->assertNotEmpty($row['periode'] ?? null, 'Baris To Update tanpa label periode.');
            $this->assertNotEmpty($row['periode_raw'] ?? null, 'Baris To Update tanpa periode_raw.');
            $this->assertNotEmpty($row['schedule_hash'] ?? null, 'Baris To Update tanpa schedule_hash.');
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}$/', $row['periode_raw']);
        }
    }

    public function test_widget_cocok_dgn_jumlah_anggota_unik_di_tiap_tab(): void
    {
        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $ambil = function (string $url) use ($leader) {
            $res = $this->actingAs($leader)->getJson($url.'?limit=500&page=1');

            return $res->status() === 200 ? collect($res->json('result') ?? []) : collect();
        };

        $unik = fn ($rows) => collect($rows)
            ->filter(fn ($r) => is_array($r) && isset($r['id_user']))
            ->pluck('id_user')->unique()->count();

        $widget = $this->actingAs($leader)
            ->getJson('/api/v1/update-performance/list-Kpi-team/widget-count');
        $widget->assertStatus(200);
        $w = $widget->json('result');
        $this->assertIsArray($w, 'Widget tidak mengembalikan objek ringkasan.');

        $this->assertSame(
            $unik($ambil('/api/v1/update-performance/list-Kpi-team')),
            (int) $w['to_update'],
            'Widget to_update tidak sama dgn jumlah anggota unik di tab To Update.',
        );
        $this->assertSame(
            $unik($ambil('/api/v1/update-performance/list-Kpi-team/on-progress')),
            (int) $w['on_progress'],
            'Widget on_progress tidak sama dgn jumlah anggota unik di tab On Going.',
        );
        $this->assertSame(
            $unik($ambil('/api/v1/update-performance/list-Kpi-team/rejected')),
            (int) $w['rejected'],
            'Widget rejected tidak sama dgn jumlah anggota unik di tab Returned.',
        );
    }

    // ----------------------------------------------------------------- detail

    public function test_halaman_detail_membuka_periode_sesuai_jadwal_yang_diklik(): void
    {
        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $rows = collect(
            $this->actingAs($leader)
                ->getJson('/api/v1/update-performance/list-Kpi-team?limit=500&page=1')
                ->json('result') ?? [],
        )->filter(fn ($r) => is_array($r) && ! empty($r['schedule_hash']));

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tidak ada baris To Update di data dev.');
        }

        foreach ($rows->take(5) as $row) {
            $res = $this->actingAs($leader)
                ->get("/update-performance/team/{$row['id_user']}?schedule={$row['schedule_hash']}");

            $this->assertSame(200, $res->status(), "Detail gagal dibuka untuk {$row['nama_lengkap']}.");

            $props = $res->viewData('page')['props'] ?? [];
            $idJadwal = Hashids::connection('custom')->decode($row['schedule_hash'])[0] ?? null;
            $jadwal = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $idJadwal)
                ->first();

            // Halaman harus membuka jadwal yang diklik, bukan jadwal lain.
            // Periode diturunkan dari IdMasterPenjadwalan, jadi prop inilah kuncinya.
            $this->assertSame(
                $row['schedule_hash'],
                $props['IdMasterPenjadwalan'] ?? null,
                "Halaman detail membuka jadwal lain untuk periode {$jadwal->Periode}.",
            );
            $this->assertSame(
                (string) $jadwal->Jadwal_Mulai,
                (string) ($props['jadwalMulai'] ?? ''),
                'Rentang jadwal yang dikirim ke halaman tidak cocok dgn jadwal yang diklik.',
            );
            $this->assertSame(
                (string) $jadwal->Jadwal_Akhir,
                (string) ($props['jadwalAkhir'] ?? ''),
                'Rentang jadwal yang dikirim ke halaman tidak cocok dgn jadwal yang diklik.',
            );

            // Dan endpoint parameter yang dipanggil halaman itu harus memakai
            // periode jadwal tsb — inilah bug "draft hilang" yang diperbaiki.
            $draft = $this->actingAs($leader)->postJson(
                "/api/v1/update-performance/temporary-actual-performance/by-team/{$row['id_user']}",
                ['Id_Master_Penjadwalan' => $row['schedule_hash']],
            );
            if ($draft->status() === 200 && ($h = $draft->json('result.header'))) {
                $this->assertSame(
                    $jadwal->Periode,
                    $h['Periode'] ?? null,
                    'Draft yang termuat bukan milik periode jadwal yang dibuka.',
                );
            }
        }
    }

    public function test_parameter_dan_draft_mendarat_di_pengajuan_yang_sama(): void
    {
        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $rows = collect(
            $this->actingAs($leader)
                ->getJson('/api/v1/update-performance/list-Kpi-team?limit=500&page=1')
                ->json('result') ?? [],
        )->filter(fn ($r) => is_array($r) && ! empty($r['schedule_hash']));

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tidak ada baris To Update di data dev.');
        }

        foreach ($rows->take(5) as $row) {
            $payload = [
                'Id_User' => $row['id_user'],
                'Id_Master_Penjadwalan' => $row['schedule_hash'],
            ];

            $item = $this->actingAs($leader)
                ->postJson('/api/v1/update-performance/list-Kpi/by-user', $payload);

            $this->assertContains(
                $item->status(),
                [200, 404],
                "Endpoint parameter error untuk {$row['nama_lengkap']}: ".$item->status(),
            );

            if ($item->status() !== 200) {
                continue;
            }

            // Parameter yang tampil harus milik tahun periode yang dibuka.
            $idUser = Hashids::connection('custom')->decode($row['id_user'])[0];
            $tahun = (int) substr($row['periode_raw'], 0, 4);

            $pengajuan = DB::table('KPI_Pengajuan as kp')
                ->join('KPI_Master_Penjadwalan as mp', 'kp.Id_Penjadwalan', '=', 'mp.Id_Master_Penjadwalan')
                ->where('kp.Id_User', $idUser)
                ->whereNull('kp.Status')
                ->where('kp.Flag_Aktif', 'Y')
                ->where('kp.Flag_Approval', 'Y')
                ->where('mp.Tahun', $tahun)
                ->orderBy('kp.Id_Pengajuan', 'DESC')
                ->select('kp.*')
                ->first();

            $this->assertNotNull(
                $pengajuan,
                "Parameter tampil tapi pengajuan tahun {$tahun} tak ditemukan untuk {$row['nama_lengkap']}.",
            );

            // Draft dibaca dari pengajuan yang sama.
            $draft = $this->actingAs($leader)->postJson(
                "/api/v1/update-performance/temporary-actual-performance/by-team/{$row['id_user']}",
                ['Id_Master_Penjadwalan' => $row['schedule_hash']],
            );

            $this->assertContains(
                $draft->status(),
                [200, 404],
                "Endpoint draft error untuk {$row['nama_lengkap']}: ".$draft->status(),
            );

            if ($draft->status() === 200) {
                $header = $draft->json('result.header');
                if ($header && isset($header['Periode'])) {
                    $this->assertSame(
                        $row['periode_raw'],
                        $header['Periode'],
                        "Draft yang termuat milik periode lain untuk {$row['nama_lengkap']}.",
                    );
                }
            }
        }
    }

    // ------------------------------------------------------------ jalur tulis

    /**
     * Inti bug "draft hilang": simpan draft memakai jadwal yang dibuka, lalu
     * baca kembali dengan jadwal yang sama — isinya harus utuh, dan barisnya
     * harus tertulis dgn periode/pengirim/flag yang benar.
     * Semua penulisan di-rollback oleh DatabaseTransactions.
     */
    public function test_simpan_draft_tim_lalu_baca_kembali_utuh(): void
    {
        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $rows = collect(
            $this->actingAs($leader)
                ->getJson('/api/v1/update-performance/list-Kpi-team?limit=500&page=1')
                ->json('result') ?? [],
        )->filter(fn ($r) => is_array($r) && ! empty($r['schedule_hash']));

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tidak ada baris To Update di data dev.');
        }

        $row = $rows->first();

        $items = $this->actingAs($leader)->postJson('/api/v1/update-performance/list-Kpi/by-user', [
            'Id_User' => $row['id_user'],
            'Id_Master_Penjadwalan' => $row['schedule_hash'],
        ]);
        $items->assertStatus(200);

        $details = collect($items->json('result.details') ?? [])
            ->filter(fn ($d) => ! empty($d['is_input_period']))
            ->values();

        if ($details->isEmpty()) {
            $this->markTestSkipped('Anggota ini tidak punya parameter yg bisa diisi pada periode tsb.');
        }

        // Nilai unik supaya tidak mungkin cocok secara kebetulan.
        $nilai = [];
        $kpiData = $details->map(function ($d, $i) use (&$nilai) {
            $angka = 71 + $i;
            $nilai[$d['idPengajuanDet']] = $angka;

            return [
                'Id_Pengajuan' => $d['idPengajuan'],
                'Id_Pengajuan_Detail' => $d['idPengajuanDetail'],
                'Id_Pengajuan_Det' => $d['idPengajuanDet'],
                'is_input_period' => 'true',
                'Actual' => $angka,
            ];
        })->all();

        // Pastikan jalur yang diuji benar-benar INSERT.
        //
        // Yang dijaga di bawah adalah ISI baris draft saat pertama kali ditulis.
        // Kalau anggota yang terpilih kebetulan sudah punya draft aktif, controller
        // dengan benar meng-UPDATE dan tidak ada INSERT sama sekali — lalu uji ini
        // gagal karena hal yang bukan cacat. Sejak keanggotaan tim ditentukan
        // roster, anggota mana yang muncul paling atas memang berubah, jadi
        // prasyaratnya dibuat pasti alih-alih diserahkan pada kebetulan.
        $idUserTerpilih = Hashids::connection('custom')->decode($row['id_user'])[0];
        $periodeTerpilih = DB::table('KPI_Master_Penjadwalan')
            ->where('Id_Master_Penjadwalan', Hashids::connection('custom')->decode($row['schedule_hash'])[0])
            ->value('Periode');

        DB::table('KPI_Update_Performance_Sementara')
            ->where('Id_User', $idUserTerpilih)
            ->where('Periode', $periodeTerpilih)
            ->where('Flag_Aktif', 'Y')
            ->update(['Flag_Aktif' => 'T']);

        // Rekam SQL yang dijalankan. Kredensial dev bisa saja read-only untuk
        // tabel draft; kalau begitu INSERT-nya tetap bisa diperiksa isinya
        // walau eksekusinya ditolak server.
        $insert = null;
        DB::listen(function ($q) use (&$insert) {
            if ($insert === null && stripos($q->sql, 'insert into [KPI_Update_Performance_Sementara]') === 0) {
                $insert = $q;
            }
        });

        $simpan = $this->actingAs($leader)->postJson(
            "/api/v1/update-performance/store/temporary/{$row['id_user']}",
            [
                'kpi_data' => $kpiData,
                'current_step' => 1,
                'Id_Master_Penjadwalan' => $row['schedule_hash'],
            ],
        );

        $idUser = Hashids::connection('custom')->decode($row['id_user'])[0];
        $idJadwal = Hashids::connection('custom')->decode($row['schedule_hash'])[0];

        // --- Isi INSERT harus benar, apa pun hasil eksekusinya.
        // DB::listen hanya menyala untuk query yang sukses, jadi bila server
        // menolak (mis. kredensial read-only) SQL-nya diambil dari pesan galat.
        if ($insert !== null) {
            preg_match('/\(([^)]*)\)\s*values/i', $insert->sql, $m);
            $kolomMentah = $m[1] ?? '';
            $nilaiMentah = array_slice($insert->bindings, 0, substr_count($kolomMentah, ',') + 1);
        } else {
            $pesan = (string) $simpan->json('message');
            $cocok = preg_match(
                '/insert into \[KPI_Update_Performance_Sementara\] \(([^)]*)\) values \((.*)\)/s',
                $pesan,
                $m,
            );
            $this->assertSame(1, $cocok, 'Tidak ada INSERT draft yang dijalankan: '.$pesan);
            $kolomMentah = $m[1];
            $nilaiMentah = array_map('trim', str_getcsv(rtrim(trim($m[2]), ')')));
        }

        $kolom = collect(explode(',', $kolomMentah))
            ->map(fn ($c) => trim($c, " \t[]"))
            ->values();

        $this->assertCount(
            $kolom->count(),
            $nilaiMentah,
            'Jumlah kolom dan nilai pada INSERT draft tidak sepadan.',
        );
        $nilaiKolom = $kolom->combine($nilaiMentah);

        $this->assertSame(
            $row['periode_raw'],
            (string) $nilaiKolom['Periode'],
            'INSERT draft memakai periode yang salah.',
        );
        $this->assertSame(
            (int) $idJadwal,
            (int) $nilaiKolom['Id_Penjadwalan'],
            'INSERT draft tidak menunjuk jadwal yang dibuka.',
        );
        $this->assertSame(
            (int) $idUser,
            (int) $nilaiKolom['Id_User'],
            'INSERT draft ditulis untuk karyawan yang salah.',
        );
        $this->assertSame(
            (int) $leader->Id_Users,
            (int) $nilaiKolom['Id_User_Sender'],
            'Id_User_Sender draft bukan leader yang menyimpan.',
        );
        $this->assertSame('Y', (string) $nilaiKolom['Flag_Team'], 'Draft jalur tim tidak berstempel Flag_Team.');
        $this->assertSame('Y', (string) $nilaiKolom['Flag_Aktif'], 'Draft tidak ditulis sebagai baris hidup.');
        $this->assertSame(
            'drafted',
            (string) $nilaiKolom['Status_Pengajuan'],
            'Status_Pengajuan draft tidak "drafted".',
        );

        // Simpan draft membalas 201 saat berhasil, jadi patokannya rentang 2xx —
        // bukan 200 persis. Kredensial DB dev sempat read-only untuk tabel ini;
        // kalau itu terjadi lagi, isi INSERT tetap sudah diverifikasi di atas.
        if ($simpan->status() < 200 || $simpan->status() >= 300) {
            $this->assertStringContainsString(
                'INSERT permission was denied',
                $simpan->getContent(),
                'Simpan draft gagal bukan karena izin: '.$simpan->getContent(),
            );
            $this->markTestSkipped(
                'Kredensial DB dev read-only untuk KPI_Update_Performance_Sementara; '.
                'isi INSERT sudah diverifikasi, baca-ulang tidak bisa diuji di sini.',
            );
        }

        // --- Baca kembali lewat endpoint yang dipakai halaman
        $baca = $this->actingAs($leader)->postJson(
            "/api/v1/update-performance/temporary-actual-performance/by-team/{$row['id_user']}",
            ['Id_Master_Penjadwalan' => $row['schedule_hash']],
        );

        $this->assertSame(200, $baca->status(), 'Draft yang baru disimpan tidak terbaca kembali (bug "draft hilang").');
        $this->assertSame(
            $row['periode_raw'],
            $baca->json('result.header.Periode'),
            'Draft yang terbaca milik periode lain.',
        );

        $terbaca = collect($baca->json('result.details') ?? []);
        $this->assertNotEmpty($terbaca, 'Draft terbaca tanpa satu pun detail.');

        foreach ($terbaca as $d) {
            $key = $d['idPengajuanDet'] ?? $d['Id_Pengajuan_Det'] ?? null;
            if ($key !== null && isset($nilai[$key])) {
                $this->assertEquals(
                    $nilai[$key],
                    $d['actual'] ?? $d['Nilai_Actual'] ?? null,
                    'Nilai actual yang terbaca berbeda dgn yang disimpan.',
                );
            }
        }
    }

    /**
     * Menyimpan draft memakai jadwal yang sudah tutup harus ditolak,
     * sesuai keputusan "ketatkan jalur baca/tulis".
     */
    public function test_simpan_draft_dgn_jadwal_tutup_ditolak(): void
    {
        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        $rows = collect(
            $this->actingAs($leader)
                ->getJson('/api/v1/update-performance/list-Kpi-team?limit=500&page=1')
                ->json('result') ?? [],
        )->filter(fn ($r) => is_array($r) && ! empty($r['schedule_hash']));

        if ($rows->isEmpty()) {
            $this->markTestSkipped('Tidak ada baris To Update di data dev.');
        }

        $tutup = DB::table('KPI_Master_Penjadwalan')
            ->where('Jadwal_Akhir', '<', now('Asia/Jakarta'))
            ->orderByDesc('Id_Master_Penjadwalan')
            ->first();

        if (! $tutup) {
            $this->markTestSkipped('Tidak ada jadwal yg sudah tutup di data dev.');
        }

        $row = $rows->first();
        $res = $this->actingAs($leader)->postJson(
            "/api/v1/update-performance/store/temporary/{$row['id_user']}",
            [
                'kpi_data' => [[
                    'Id_Pengajuan' => 'x',
                    'Id_Pengajuan_Detail' => 'x',
                    'Id_Pengajuan_Det' => 'x',
                    'is_input_period' => 'true',
                    'Actual' => 10,
                ]],
                'current_step' => 1,
                'Id_Master_Penjadwalan' => $this->hash($tutup->Id_Master_Penjadwalan),
            ],
        );

        $this->assertSame(
            404,
            $res->status(),
            'Jadwal yang sudah tutup masih menerima penyimpanan draft.',
        );
    }

    /**
     * Jalur by-pass: endpoint parameter dan endpoint draft harus memilih
     * Id_Pengajuan yang sama. Tanpa orderBy/select yang seragam, karyawan yang
     * punya pengajuan individu dan tim di tahun sama akan menampilkan parameter
     * dari pengajuan A tetapi draft dari pengajuan B.
     */
    public function test_bypass_parameter_dan_draft_memilih_pengajuan_sama(): void
    {
        $leader = $this->cariLeader();
        if (! $leader) {
            $this->markTestSkipped('Tidak ada leader dgn tim aktif di data dev.');
        }

        // Prioritaskan karyawan yg punya >1 pengajuan hidup di tahun sama —
        // justru di sanalah dua endpoint bisa berpisah.
        $rawan = DB::table('KPI_Pengajuan as p')
            ->join('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->select('p.Id_User', 'j.Tahun', DB::raw('COUNT(*) as n'))
            ->groupBy('p.Id_User', 'j.Tahun')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($rawan->isEmpty()) {
            $this->markTestSkipped('Tidak ada karyawan dgn >1 pengajuan hidup di tahun yang sama.');
        }

        $jadwal = DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Penilaian_KPI', 'Y')
            ->orderByDesc('Id_Master_Penjadwalan')
            ->first();

        $this->assertNotNull($jadwal, 'Tidak ada jadwal penilaian aktif.');

        $diperiksa = 0;
        foreach ($rawan->take(6) as $r) {
            $hashUser = $this->hash($r->Id_User);
            $hashJadwal = $this->hash($jadwal->Id_Master_Penjadwalan);

            $items = $this->actingAs($leader)->postJson(
                '/api/v1/update-performance/list-Kpi-by-pass/by-user',
                ['Id_User' => $hashUser, 'Id_Master_Penjadwalan' => $hashJadwal],
            );

            $draft = $this->actingAs($leader)->postJson(
                "/api/v1/update-performance/temporary-actual-performance/by-pass/{$hashUser}",
                ['Id_Master_Penjadwalan' => $hashJadwal],
            );

            $this->assertContains($items->status(), [200, 404], 'Endpoint parameter by-pass error.');
            $this->assertContains($draft->status(), [200, 404], 'Endpoint draft by-pass error.');

            if ($items->status() !== 200 || $draft->status() !== 200) {
                continue;
            }

            $detail = collect($items->json('result.details') ?? [])->first();
            $headerDraft = $draft->json('result.header');

            if (! $detail || ! isset($detail['idPengajuan']) || ! isset($headerDraft['Id_Pengajuan'])) {
                continue;
            }

            // Kedua endpoint mengembalikan Id_Pengajuan dalam bentuk hash, jadi
            // dinormalkan dulu ke angka sebelum dibandingkan.
            $normalkan = function ($nilai) {
                if (is_numeric($nilai)) {
                    return (int) $nilai;
                }

                return Hashids::connection('custom')->decode((string) $nilai)[0] ?? null;
            };

            $dariItems = $normalkan($detail['idPengajuan']);
            $dariDraft = $normalkan($headerDraft['Id_Pengajuan']);

            $this->assertNotNull($dariItems, 'Id_Pengajuan dari endpoint parameter tidak terbaca.');
            $this->assertSame(
                $dariItems,
                $dariDraft,
                "Parameter dan draft by-pass memilih pengajuan berbeda untuk Id_User={$r->Id_User}.",
            );
            $diperiksa++;
        }

        $this->assertTrue(true, "Pasangan by-pass terverifikasi: {$diperiksa}");
    }

    // ------------------------------------------------------------- keamanan

    /**
     * Halaman "my performance" hanya boleh menampilkan KPI milik sendiri.
     * Menukar hashid di URL dgn milik karyawan lain tidak boleh membocorkan data.
     */
    public function test_dashboard_pribadi_tidak_membocorkan_kpi_karyawan_lain(): void
    {
        $kandidat = DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Pengajuan', 'disetujui')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->select('Id_User', 'Id_Pengajuan', 'Id_Update_Performance')
            ->orderByDesc('Id_Update_Performance')
            ->limit(60)
            ->get()
            ->unique('Id_User')
            ->values();

        if ($kandidat->count() < 2) {
            $this->markTestSkipped('Butuh minimal 2 karyawan dgn KPI disetujui.');
        }

        $penyerang = null;
        foreach ($kandidat as $k) {
            $u = User::find($k->Id_User);
            if ($u && $u->memilikiJabatan('dashboardStaff')) {
                $penyerang = ['user' => $u, 'row' => $k];
                break;
            }
        }

        if (! $penyerang) {
            $this->markTestSkipped('Tidak ada karyawan dgn akses dashboardStaff.');
        }

        $korban = $kandidat->first(fn ($k) => (int) $k->Id_User !== (int) $penyerang['row']->Id_User);
        $this->assertNotNull($korban, 'Tidak ada karyawan kedua sebagai pembanding.');

        $res = $this->actingAs($penyerang['user'])->getJson(sprintf(
            '/api/v1/dashboard-my-performance/detail/%s/%s/false',
            $this->hash($korban->Id_Pengajuan),
            $this->hash($korban->Id_Update_Performance),
        ));

        if ($res->status() === 200) {
            $isi = $res->json('result');
            $this->assertTrue(
                empty($isi) || empty($isi['details'] ?? $isi),
                'Dashboard pribadi membocorkan KPI karyawan lain (IDOR).',
            );
        } else {
            $this->assertContains(
                $res->status(),
                [400, 403, 404],
                'Status tak terduga saat mengakses KPI karyawan lain: '.$res->status(),
            );
        }
    }

    // -------------------------------------------------------------- integritas

    public function test_tidak_ada_pengajuan_yatim_atau_tanpa_jadwal(): void
    {
        $tanpaJadwal = DB::table('KPI_Pengajuan')
            ->whereNull('Id_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->count();

        $this->assertSame(0, $tanpaJadwal, 'Ada pengajuan hidup tanpa Id_Penjadwalan — akan hilang kena inner join.');

        $yatim = DB::table('KPI_Pengajuan as p')
            ->leftJoin('KPI_Master_Penjadwalan as j', 'p.Id_Penjadwalan', '=', 'j.Id_Master_Penjadwalan')
            ->whereNotNull('p.Id_Penjadwalan')
            ->whereNull('j.Id_Master_Penjadwalan')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNull('p.Status')
            ->count();

        $this->assertSame(0, $yatim, 'Ada pengajuan hidup menunjuk jadwal yang tidak ada.');
    }

    public function test_detail_performance_tidak_yatim_dan_bobot_masuk_akal(): void
    {
        $detailYatim = DB::table('KPI_Update_Performance_Detail as d')
            ->leftJoin('KPI_Update_Performance as h', 'd.Id_Update_Performance', '=', 'h.Id_Update_Performance')
            ->whereNull('h.Id_Update_Performance')
            ->count();

        $this->assertSame(0, $detailYatim, 'Ada baris detail update performance tanpa header.');

        // Header hidup & disetujui wajib punya minimal 1 detail.
        $tanpaDetail = DB::table('KPI_Update_Performance as h')
            ->leftJoin('KPI_Update_Performance_Detail as d', 'h.Id_Update_Performance', '=', 'd.Id_Update_Performance')
            ->where('h.Flag_Aktif', 'Y')
            ->where('h.Flag_Approval', 'Y')
            ->whereNull('d.Id_Update_Performance')
            ->count();

        $this->assertSame(0, $tanpaDetail, 'Ada performance disetujui tanpa satu pun baris detail.');
    }

    /**
     * Invarian yang benar-benar mengikat: dalam satu parameter set yang MASIH HIDUP,
     * satu karyawan hanya boleh punya satu performance hidup per periode.
     *
     * Catatan: ada baris performance lama yang masih Flag_Aktif='Y' padahal
     * pengajuan induknya sudah dinonaktifkan (tergantikan parameter set baru).
     * Baris itu tidak ikut skor karena dashboard menyaring lewat Id_Pengajuan_Detail
     * milik pengajuan hidup, jadi tidak dianggap pelanggaran di sini —
     * lihat test_baris_performance_tertinggal_tidak_bertambah.
     */
    public function test_tidak_ada_dua_performance_hidup_dalam_pengajuan_yang_masih_hidup(): void
    {
        $bentrok = DB::table('KPI_Update_Performance as h')
            ->join('KPI_Pengajuan as p', 'h.Id_Pengajuan', '=', 'p.Id_Pengajuan')
            ->where('h.Flag_Aktif', 'Y')
            ->where('p.Flag_Aktif', 'Y')
            ->whereNull('p.Status')
            ->whereIn('h.Status_Pengajuan', ['menunggu', 'disetujui'])
            ->select('h.Id_User', 'h.Periode', DB::raw('COUNT(*) as n'))
            ->groupBy('h.Id_User', 'h.Periode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        // Baseline data dev per 2026-07-28: 1 pelanggaran diketahui —
        // Id_User=637 periode 2026-05 punya dua pengajuan hidup sekaligus
        // (individu Id_Pengajuan=1439 dan tim Id_Pengajuan=2071), Mei terisi di
        // keduanya: satu 'menunggu' (skor 100) dan satu 'disetujui' (skor 92,52).
        // Belum merusak skor karena YTD hanya menghitung parameter yang punya
        // realisasi DISETUJUI, tapi akan menghitung Mei dua kali begitu yang
        // 'menunggu' ikut disetujui. Angka di bawah sengaja dikunci supaya
        // kasus BARU langsung menggagalkan test.
        $this->assertLessThanOrEqual(
            1,
            $bentrok->count(),
            'Muncul pelanggaran baru (>1 performance hidup dalam pengajuan hidup): '.$bentrok->take(5)->toJson(),
        );
    }

    /**
     * Baris performance yang induk pengajuannya sudah nonaktif adalah warisan data
     * lama. Test ini mengunci jumlahnya supaya perubahan kode tidak menambah yang baru.
     */
    public function test_baris_performance_tertinggal_tidak_bertambah(): void
    {
        $tertinggal = DB::table('KPI_Update_Performance as h')
            ->join('KPI_Pengajuan as p', 'h.Id_Pengajuan', '=', 'p.Id_Pengajuan')
            ->where('h.Flag_Aktif', 'Y')
            ->where('p.Flag_Aktif', '<>', 'Y')
            ->count();

        $this->assertLessThanOrEqual(
            123,
            $tertinggal,
            "Jumlah performance hidup yg induk pengajuannya mati naik jadi {$tertinggal} (baseline 123).",
        );
    }

    /**
     * Bila satu karyawan+periode punya lebih dari satu performance disetujui,
     * yang ber-Id terbesar (dipakai dashboard) harus yang induknya masih hidup.
     * Kalau terbalik, dashboard akan menampilkan skor dari parameter set usang.
     */
    public function test_performance_terbaru_selalu_milik_pengajuan_yang_hidup(): void
    {
        $bentrok = DB::table('KPI_Update_Performance')
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Pengajuan', 'disetujui')
            ->where('Flag_Approval', 'Y')
            ->whereNull('Status')
            ->select('Id_User', 'Periode')
            ->groupBy('Id_User', 'Periode')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $salah = [];
        foreach ($bentrok as $b) {
            $terbaru = DB::table('KPI_Update_Performance as h')
                ->join('KPI_Pengajuan as p', 'h.Id_Pengajuan', '=', 'p.Id_Pengajuan')
                ->where('h.Flag_Aktif', 'Y')
                ->where('h.Status_Pengajuan', 'disetujui')
                ->where('h.Flag_Approval', 'Y')
                ->whereNull('h.Status')
                ->where('h.Id_User', $b->Id_User)
                ->where('h.Periode', $b->Periode)
                ->orderByDesc('h.Id_Update_Performance')
                ->select('h.Id_Update_Performance', 'p.Flag_Aktif as induk')
                ->first();

            if ($terbaru && $terbaru->induk !== 'Y') {
                $salah[] = "user={$b->Id_User} periode={$b->Periode} header={$terbaru->Id_Update_Performance}";
            }
        }

        $this->assertEmpty(
            $salah,
            'Header terbaru justru milik pengajuan mati: '.implode('; ', array_slice($salah, 0, 5)),
        );
    }
}
