<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Support\SopPerhatianDokumen;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Ringkasan Master Dokumen: biayanya tak boleh tumbuh bersama katalognya.
 *
 * APA YANG SEBENARNYA RUSAK. `ringkasanTahap()` memuat SELURUH dokumen aktif
 * tanpa limit, lalu menembakkan `whereIn(<semua id>)` dua kali. Yang tumbuh
 * bukan JUMLAH pernyataannya — itu tetap lima berapa pun dokumennya — melainkan
 * JUMLAH PARAMETER di dalam satu pernyataan. Di sekitar dokumen ke-2100 ia
 * menembus plafon parameter SQL Server dan gagal KERAS: halaman admin berhenti
 * bekerja, bukan sekadar melambat.
 *
 * KARENA ITU YANG DIUKUR PARAMETERNYA, bukan cuma cacahan pernyataannya.
 * Penjaga yang hanya menghitung pernyataan akan HIJAU pada kode lama — ia
 * mengukur dimensi yang memang tak pernah bergerak, dan sesudah itu ia cuma
 * memberi rasa aman. Cacahan pernyataan tetap ikut ditegaskan, tapi sebagai
 * pelengkap, bukan sebagai penjaganya.
 *
 * PENJAGA KERJA-NYATA. Permintaan yang ditolak 403 menghasilkan angka yang
 * sempurna pada kedua dimensi. Karena itu tiap pengukuran menuntut 200, dan
 * angkanya sendiri dituntut BERGERAK sebanyak dokumen yang disisipkan — kalau
 * ringkasannya tak melihat dokumen baru itu, ia sedang menghitung sesuatu yang
 * lain.
 *
 * DAN ANGKANYA TAK BOLEH BERGESER. Optimasi yang diam-diam mengubah definisi
 * satu pil lebih buruk daripada lambat: admin tak punya cara tahu. Karena itu
 * tiap pil dihitung ulang di sini dengan bentuk LAMA — muat semua lalu
 * kelompokkan di PHP — dan dituntut sama persis dengan yang dikirim endpoint.
 */
class SopRingkasanDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private const ALAMAT = '/api/v1/sop/admin/dokumen/current';

    private function adminAtauSkip(): User
    {
        $u = User::whereNotNull('Id_Users')->get()
            ->first(fn ($x) => $x->memilikiJabatan('menuSopDokumenPage'));
        if (! $u) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopDokumenPage di data dev.');
        }

        return $u;
    }

    /**
     * Dokumen uji yang SENGAJA berumur tua.
     *
     * Daftarnya diurut Created_At menurun, jadi dokumen ber-tanggal hari ini
     * akan mendorong seluruh isi halaman pertama keluar. Halaman yang berganti
     * isi mengubah kueri penerjemah nama (jenis, unit, entitas, departemen)
     * yang memang dilewati bila kolomnya kosong — dan pengukuran jadi
     * membandingkan dua halaman yang berbeda, bukan dua ukuran katalog.
     * Tanggal lampau menaruhnya di halaman terakhir sehingga yang berubah
     * hanya UKURAN katalognya.
     */
    private function sisipkanDokumen(int $jumlah): array
    {
        $ids = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $ids[] = (int) DB::table('N_SOP_Dokumen')->insertGetId([
                'Judul' => 'UJI RINGKASAN '.$i.' '.substr((string) microtime(true), -6),
                'Tipe' => 'DOKUMEN',
                'Flag_Aktif' => SopStatus::AKTIF,
                'Created_By' => 1,
                'Created_At' => '2000-01-01 00:00:00',
            ], 'Id_SOP_Dokumen');
        }

        return $ids;
    }

    // ── Biaya ────────────────────────────────────────────────────────────────

    public function test_ringkasan_tidak_menumpuk_parameter_saat_dokumen_bertambah(): void
    {
        $admin = $this->adminAtauSkip();

        /* Pemanasan LEBIH DULU. Permintaan pertama mengisi cache (setting,
           menu) sehingga ia menerbitkan kueri yang tak diterbitkan permintaan
           berikutnya — tanpa ini, selisihnya mengukur cache yang dingin, bukan
           katalog yang membesar. */
        $this->actingAs($admin)->getJson(self::ALAMAT)->assertStatus(200);

        $stat = ['n' => 0, 'maks' => 0];
        DB::listen(function ($q) use (&$stat) {
            $stat['n']++;
            $stat['maks'] = max($stat['maks'], count($q->bindings));
        });

        $ukur = function () use ($admin, &$stat): array {
            $stat = ['n' => 0, 'maks' => 0];
            $res = $this->actingAs($admin)->getJson(self::ALAMAT);
            /* PENJAGA KERJA-NYATA: 403 juga menghasilkan angka yang stabil. */
            $res->assertStatus(200);

            return [
                'n' => $stat['n'],
                'maks' => $stat['maks'],
                'ringkasan' => $res->json('result.ringkasan'),
            ];
        };

        $sebelum = $ukur();
        $this->assertIsArray($sebelum['ringkasan'], 'Muatan tak membawa ringkasan sama sekali.');
        $this->assertGreaterThan(0, $sebelum['n'], 'Tak satu pun kueri tercatat — pengukurnya tak terpasang.');

        $this->sisipkanDokumen(25);

        $sesudah = $ukur();

        /* Ringkasannya BENAR-BENAR melihat dokumen baru itu. Tanpa penegasan
           ini, ringkasan yang menghitung himpunan kosong lulus dua penjaga di
           bawahnya dengan angka sempurna. */
        $this->assertSame(
            (int) $sebelum['ringkasan']['total'] + 25,
            (int) $sesudah['ringkasan']['total'],
            'Ringkasan tak menghitung 25 dokumen yang baru disisipkan.'
        );
        $this->assertSame(
            (int) $sebelum['ringkasan']['BARU'] + 25,
            (int) $sesudah['ringkasan']['BARU'],
            'Dokumen tanpa versi apa pun seharusnya menghuni pil BARU.'
        );

        /* PENJAGANYA. Bentuk lama menaruh satu parameter per dokumen di dalam
           satu pernyataan; angka ini naik persis sebanyak dokumen yang
           disisipkan, dan di sekitar 2100 ia berhenti bisa dijalankan. */
        $this->assertSame(
            $sebelum['maks'],
            $sesudah['maks'],
            "Parameter terbanyak dalam satu pernyataan naik dari {$sebelum['maks']} ke "
            ."{$sesudah['maks']} setelah 25 dokumen ditambahkan. Ringkasan masih memuat "
            .'seluruh dokumen lalu whereIn semua id — pada 2100 dokumen itu menembus '
            .'plafon parameter SQL Server dan gagal keras, bukan sekadar lambat.'
        );

        $this->assertSame(
            $sebelum['n'],
            $sesudah['n'],
            "Jumlah kueri naik dari {$sebelum['n']} ke {$sesudah['n']} setelah 25 dokumen "
            .'ditambahkan — ada jalur yang menembak sekali per dokumen.'
        );
    }

    // ── Angka ────────────────────────────────────────────────────────────────

    /**
     * Tiap pil dihitung ulang dengan bentuk LAMA, lalu dituntut sama.
     *
     * Ini yang menahan optimasi dari menggeser arti satu pil tanpa ada yang
     * melihatnya: angka di layar tetap muncul, hanya saja menghitung hal lain.
     */
    public function test_angka_pil_sama_dengan_perhitungan_per_dokumen(): void
    {
        $admin = $this->adminAtauSkip();

        /* Dokumen tambahan supaya pil BARU dijamin tak nol — perbandingan
           antar-nol tak membuktikan apa pun. */
        $this->sisipkanDokumen(3);

        $ringkas = $this->actingAs($admin)->getJson(self::ALAMAT)
            ->assertStatus(200)->json('result.ringkasan');

        $this->assertSame($this->hitungManual(), array_map('intval', $ringkas),
            'Angka pil bergeser dari perhitungan per-dokumen yang menjadi acuannya.');
    }

    /** Keempat tahap saling lepas dan menutup seluruh populasi aktifnya. */
    public function test_empat_tahap_saling_lepas_dan_menjumlah_ke_total(): void
    {
        $admin = $this->adminAtauSkip();

        $r = $this->actingAs($admin)->getJson(self::ALAMAT)
            ->assertStatus(200)->json('result.ringkasan');

        $this->assertSame(
            (int) $r['BARU'] + (int) $r['DRAFT'] + (int) $r['SIAP'] + (int) $r['DIPAKAI'],
            (int) $r['total'],
            'Ada dokumen aktif yang tak menghuni tahap mana pun, atau menghuni dua sekaligus.'
        );

        $this->assertSame((int) $r['BARU'] + (int) $r['DRAFT'], (int) $r['BELUM_LENGKAP'],
            'BELUM_LENGKAP bukan lagi gabungan dua tahap pertama.');

        /* PERHATIAN hanya berlaku bagi dokumen yang PUNYA versi berlaku, jadi
           ia tak bisa melebihi SIAP + DIPAKAI. */
        $this->assertLessThanOrEqual((int) $r['SIAP'] + (int) $r['DIPAKAI'], (int) $r['PERHATIAN'],
            'PERHATIAN melampaui populasi dokumen yang punya versi berlaku.');

        /* NONAKTIF berdiri di luar total: populasinya justru yang dikeluarkan. */
        $this->assertSame(
            DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)
                ->whereNotNull('Nonaktif_At')->count(),
            (int) $r['NONAKTIF'],
        );
    }

    // ── Urutan PERHATIAN lewat JOIN ─────────────────────────────────────────

    /**
     * JOIN pengurut tak boleh menggandakan baris.
     *
     * Pointer d.Id_Versi_Aktif menunjuk satu baris pada kunci N_SOP_Item_Versi,
     * jadi LEFT JOIN-nya aman — tapi "seharusnya aman" bukan penjaga. Kalau
     * pointernya suatu hari menunjuk kolom yang tak unik, dokumen yang sama
     * muncul berkali-kali di daftarnya sementara `total` (dihitung sebelum
     * join) tetap melaporkan angka lama.
     */
    public function test_urutan_perhatian_tidak_menggandakan_baris(): void
    {
        $admin = $this->adminAtauSkip();

        $hasil = $this->actingAs($admin)
            ->getJson(self::ALAMAT.'?tahap=PERHATIAN&limit=100')
            ->assertStatus(200)->json('result');

        $rows = $hasil['rows'];
        if ($rows === []) {
            $this->markTestSkipped('Tak ada dokumen di pintu PERHATIAN pada data dev.');
        }

        $ids = array_column($rows, 'Id_SOP_Dokumen');
        $this->assertSame(count($ids), count(array_unique($ids)),
            'Dokumen yang sama muncul lebih dari sekali — join pengurut menggandakan baris.');

        $this->assertSame(min((int) $hasil['total'], 100), count($rows),
            'Jumlah baris halaman tak lagi sepadan dengan totalnya.');
    }

    /**
     * Urutan pintu PERHATIAN identik dengan subkueri berkorelasi yang dibuang.
     *
     * Dibandingkan terhadap SQL LAMA yang dijalankan di sini, bukan terhadap
     * `Tinjau_Tanggal` di muatan: keduanya sengaja menjawab pertanyaan yang
     * berbeda. Pengurut memakai tanggal TERDEKAT dari dua kolom, sedangkan
     * `Tinjau_Tanggal` menyebut yang paling BERAT bila keduanya sudah lewat —
     * jadi daftar yang benar pun tak akan monoton menurut kolom itu.
     */
    public function test_urutan_perhatian_identik_dengan_subkueri_yang_digantikan(): void
    {
        $admin = $this->adminAtauSkip();

        $rows = $this->actingAs($admin)
            ->getJson(self::ALAMAT.'?tahap=PERHATIAN&limit=100')
            ->assertStatus(200)->json('result.rows');

        if (count($rows) < 2) {
            $this->markTestSkipped('Kurang dari dua baris di pintu PERHATIAN pada data dev.');
        }

        $didapat = array_map(
            fn ($r) => (int) (Hashids::connection('custom')->decode($r['Id_SOP_Dokumen'])[0] ?? 0),
            $rows,
        );

        /* BENTUK LAMA, apa adanya: subkueri berkorelasi ber-CROSS APPLY. */
        $diharapkan = SopPerhatianDokumen::terapkanTinjau(
            DB::table('N_SOP_Dokumen as d')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->whereNull('d.Nonaktif_At')
        )
            ->orderBy(DB::raw(
                '(SELECT MIN(t.x) FROM N_SOP_Item_Versi vp
                    CROSS APPLY (VALUES (vp.Tanggal_Review_Berikutnya), (vp.Tanggal_Kadaluarsa)) AS t(x)
                   WHERE vp.Id_SOP_Item_Versi = d.Id_Versi_Aktif)'
            ))
            ->orderByDesc('d.Id_SOP_Dokumen')
            ->limit(100)
            ->pluck('d.Id_SOP_Dokumen')
            ->map(fn ($x) => (int) $x)
            ->all();

        $this->assertSame($diharapkan, $didapat,
            'Urutan pintu PERHATIAN bergeser: pengurut lewat LEFT JOIN tak menghitung '
            .'nilai yang sama dengan subkueri berkorelasi yang digantikannya.');
    }

    // ── Acuan: bentuk lama, ditulis ulang di sini ────────────────────────────

    /**
     * Perhitungan pil dengan bentuk SEBELUM optimasi.
     *
     * Sengaja memuat seluruh dokumen lalu mengelompokkan di PHP: itu memang
     * bentuk yang dibuang dari kode produksi, dan justru karena itu ia jadi
     * acuan yang berdiri sendiri di sini.
     *
     * @return array<string,int>
     */
    private function hitungManual(): array
    {
        $rows = DB::table('N_SOP_Dokumen as d')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->whereNull('d.Nonaktif_At')
            ->get(['d.Id_SOP_Dokumen', 'd.Id_Versi_Aktif']);

        $nonaktif = DB::table('N_SOP_Dokumen as d')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('d.Nonaktif_At')
            ->count();

        $ids = $rows->pluck('Id_SOP_Dokumen')->all();

        $adaVersi = $ids === [] ? collect() : DB::table('N_SOP_Item_Versi')
            ->whereIn('Id_SOP_Dokumen', $ids)->where('Flag_Aktif', SopStatus::AKTIF)
            ->select('Id_SOP_Dokumen', DB::raw('COUNT(*) n'))
            ->groupBy('Id_SOP_Dokumen')->pluck('n', 'Id_SOP_Dokumen');

        $dipakai = $ids === [] ? collect() : DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Dokumen', $ids)->where('Flag_Aktif', SopStatus::AKTIF)
            ->select('Id_SOP_Dokumen', DB::raw('COUNT(DISTINCT Id_SOP_Paket) n'))
            ->groupBy('Id_SOP_Dokumen')->pluck('n', 'Id_SOP_Dokumen');

        $r = ['total' => $rows->count(), 'BARU' => 0, 'DRAFT' => 0, 'SIAP' => 0, 'DIPAKAI' => 0,
            'PERHATIAN' => 0, 'BELUM_LENGKAP' => 0, 'NONAKTIF' => $nonaktif];

        foreach ($rows as $d) {
            $r[$d->Id_Versi_Aktif === null
                ? ((int) ($adaVersi[$d->Id_SOP_Dokumen] ?? 0) > 0 ? 'DRAFT' : 'BARU')
                : ((int) ($dipakai[$d->Id_SOP_Dokumen] ?? 0) > 0 ? 'DIPAKAI' : 'SIAP')]++;
        }

        $r['BELUM_LENGKAP'] = $r['BARU'] + $r['DRAFT'];

        $r['PERHATIAN'] = SopPerhatianDokumen::terapkanTinjau(
            DB::table('N_SOP_Dokumen as d')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->whereNull('d.Nonaktif_At')
        )->count();

        return $r;
    }
}
