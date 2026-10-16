<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Riwayat self-shift: pelengkap satu halaman diambil sekali, bukan per baris.
 *
 * `getHistory()` menembak tiga kueri untuk tiap transaksi di halaman — rincian
 * tanggal dan satu `exists` ke tabel approval — lalu `getHistoryStats()`
 * menembak tiga hitungan untuk masing-masing dua tabel. Satu halaman 10
 * transaksi berarti **33 kueri**, dan `limit` datang dari permintaan sehingga
 * biayanya naik lurus mengikuti yang diminta.
 *
 * Sesudah diambil berkelompok: **33 → 11** kueri (halaman 2: 25 → 7).
 *
 * Tiga sisi dijaga di sini. Jumlah kueri saja bisa ditipu: kalau kunci
 * pencocokan hasil massal meleset, `details` jadi kosong dan
 * `has_approval_action` jadi `false` untuk semua orang — dan jumlah kuerinya
 * justru terlihat membaik. Karena itu tiap nilai yang dilaporkan diadu dengan
 * bentuk lamanya, dijalankan ulang di dalam uji ini.
 */
class RiwayatSelfShiftKueriTest extends TestCase
{
    use DatabaseTransactions;

    private const RUTE = '/self-shift/history';

    private string $kodeKaryawan;

    private int $idUser;

    protected function setUp(): void
    {
        parent::setUp();

        $aktor = User::query()->where('Id_Users', 4)->first();

        if (! $aktor || ! $aktor->karyawan?->Kode_Karyawan) {
            $this->markTestSkipped('User uji tanpa data karyawan di data dev.');
        }

        $this->kodeKaryawan = (string) $aktor->karyawan->Kode_Karyawan;
        $this->idUser = (int) $aktor->Id_Users;

        $this->withoutMiddleware();
        Auth::login($aktor);
    }

    public function test_pelengkap_riwayat_tidak_diambil_per_transaksi(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $items = $res->json('result.items') ?? [];
        $jumlah = count($items);
        if ($jumlah < 3) {
            $this->markTestSkipped("Hanya {$jumlah} transaksi; terlalu sedikit untuk membedakan sekali dari per-baris.");
        }

        /* Tiga jangkar, masing-masing dulu berjalan sekali per transaksi. */
        $jangkar = [
            'N_HRIS_Transaksi_Shift_Det' => 0,
            'N_HRIS_Transaksi_Tukar_Hari_Detail' => 0,
            'HRIS_Approval_Request_Detail' => 0,
        ];

        foreach ($kueri as $sql) {
            foreach (array_keys($jangkar) as $tabel) {
                if (stripos($sql, $tabel) !== false) {
                    $jangkar[$tabel]++;
                }
            }
        }

        foreach ($jangkar as $tabel => $n) {
            /* Ambangnya RELATIF terhadap isi halaman, bukan angka mati yang
               akan basi begitu datanya berubah. Bentuk massal menghasilkan
               paling banyak satu kueri per tabel; bentuk lama menghasilkan
               satu per transaksi yang cocok jenisnya. */
            $this->assertLessThan(
                $jumlah,
                $n,
                "Kueri ke {$tabel} berjalan {$n}x untuk {$jumlah} transaksi — biayanya ikut tumbuh per baris. "
                .'Rincian dan penanda tindakan approval harus diambil berkelompok, bukan di dalam foreach.'
            );
        }
    }

    /**
     * Sisi kedua: rincian dan penanda approval harus tetap menempel pada
     * transaksi yang benar.
     */
    public function test_rincian_dan_penanda_approval_masih_benar(): void
    {
        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $items = $res->json('result.items') ?? [];
        if ($items === []) {
            $this->markTestSkipped('Riwayat self-shift kosong di data dev.');
        }

        $adaTindakan = 0;
        $adaBanyakTanggal = 0;

        foreach ($items as $baris) {
            foreach (['No_Transaksi', 'transaction_type', 'tanggal_count', 'has_approval_action'] as $medan) {
                $this->assertArrayHasKey($medan, $baris, "Baris riwayat kehilangan medan '{$medan}'.");
            }

            $no = $baris['No_Transaksi'];

            /* Bentuk LAMA dijalankan ulang di sini — itulah yang diadu dengan
               jawaban bentuk massal. */
            if ($baris['transaction_type'] === 'SHIFT') {
                $rincian = DB::table('N_HRIS_Transaksi_Shift_Det as det')
                    ->join('N_HRIS_Transaksi_Shift_Detail as d', 'det.Id_Detail', '=', 'd.Id')
                    ->whereNull('det.Status')
                    ->whereNull('d.Status')
                    ->where('det.No_Transaksi', $no)
                    ->count();
            } else {
                $rincian = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
                    ->where('d.No_Transaksi', $no)
                    ->count();
            }

            $this->assertSame(
                $rincian,
                (int) $baris['tanggal_count'],
                "Transaksi {$no} melaporkan {$baris['tanggal_count']} tanggal padahal tabelnya berisi {$rincian}. "
                .'Kunci pencocokan hasil massal kemungkinan meleset.'
            );

            $tindakan = DB::table('HRIS_Approval_Request_Detail as d')
                ->join('HRIS_Approval_Request as ar', 'd.Id_Approval_Request', '=', 'ar.id')
                ->where('ar.No_Transaksi', $no)
                ->whereNotNull('d.Flag_Approval')
                ->exists();

            $this->assertSame(
                $tindakan,
                (bool) $baris['has_approval_action'],
                "Transaksi {$no} melaporkan has_approval_action=".var_export($baris['has_approval_action'], true)
                .' padahal tabel approval berkata '.var_export($tindakan, true).'.'
            );

            if ($tindakan) {
                $adaTindakan++;
            }
            if ($rincian > 1) {
                $adaBanyakTanggal++;
            }
        }

        /* Premisnya diperiksa lebih dulu. Kalau tak ada satu pun transaksi yang
           sudah ditindak, penegasan `has_approval_action` di atas cocok hanya
           karena kedua belah pihak sama-sama `false` — persis gejala kunci yang
           meleset. Begitu juga rincian: kalau semuanya satu tanggal, kekeliruan
           pengelompokan tak akan terlihat. */
        $this->assertGreaterThan(
            0,
            $adaTindakan,
            'Tak ada satu pun transaksi yang sudah ditindak approver di halaman ini, jadi penegasan '
            .'has_approval_action tidak membuktikan apa pun. Pilih data dev yang punya transaksi tertindak.'
        );

        $this->assertGreaterThan(
            0,
            $adaBanyakTanggal,
            'Semua transaksi di halaman ini hanya punya satu tanggal, jadi kekeliruan pengelompokan '
            .'rincian tak akan terlihat. Pilih data dev yang punya transaksi bertanggal jamak.'
        );
    }

    /**
     * Sisi ketiga: tiga angka statistik kini dihitung dalam satu lintasan per
     * tabel lewat `count(distinct case ...)`.
     *
     * Jebakannya ada di 'pending'. Bentuk lamanya `whereNull OR != 'Y'`, dan
     * `<> 'Y'` sendirian di dalam `CASE` akan membuang baris NULL diam-diam —
     * angkanya mengecil tanpa ada yang gagal. Uji ini menjalankan ulang bentuk
     * lama itu dan mengadunya.
     */
    public function test_statistik_sama_dengan_hitungan_terpisah(): void
    {
        $res = $this->getJson(self::RUTE);
        $res->assertOk();

        $stats = $res->json('result.stats');
        $this->assertIsArray($stats, 'Jawaban riwayat kehilangan blok stats.');

        $harapan = $this->statistikBentukLama();

        if ($harapan['total'] === 0) {
            $this->markTestSkipped('Tidak ada transaksi self-shift untuk user uji di data dev.');
        }

        $this->assertSame($harapan['total'], (int) $stats['total'], 'Angka total menyimpang.');
        $this->assertSame(
            $harapan['pending'],
            (int) $stats['pending'],
            "Angka pending menyimpang. Dugaan pertama: `<> 'Y'` di dalam CASE membuang baris ber-Flag_Selesai NULL."
        );
        $this->assertSame($harapan['approved'], (int) $stats['approved'], 'Angka approved menyimpang.');

        /* Premis: kalau pending dan approved sama-sama nol, ketiga penegasan di
           atas lolos tanpa menyentuh cabang CASE mana pun. */
        $this->assertGreaterThan(
            0,
            $harapan['pending'] + $harapan['approved'],
            'Pending dan approved sama-sama nol, jadi tak satu pun cabang CASE teruji.'
        );
    }

    /**
     * Bentuk lama: enam kueri terpisah, satu per angka per tabel.
     *
     * @return array{total:int,pending:int,approved:int}
     */
    private function statistikBentukLama(): array
    {
        $shift = fn () => DB::table('N_HRIS_Transaksi_Shift as h')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $this->kodeKaryawan)
            ->whereNull('d.Status')
            ->whereNull('h.Status')
            ->where('h.Created_By', $this->idUser)
            ->where('Jenis_Transaksi', 'SELF_SHIFT');

        $tukar = fn () => DB::table('N_HRIS_Transaksi_Tukar_Hari as h')
            ->join('N_HRIS_Transaksi_Tukar_Hari_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $this->kodeKaryawan)
            ->where('h.Created_By', $this->idUser)
            ->whereNull('h.Status');

        $belumSelesai = function ($q) {
            $q->whereNull('h.Flag_Selesai')->orWhere('h.Flag_Selesai', '!=', 'Y');
        };

        return [
            'total' => $shift()->distinct()->count('h.No_Transaksi')
                + $tukar()->distinct()->count('h.No_Transaksi'),
            'pending' => $shift()->where($belumSelesai)->distinct()->count('h.No_Transaksi')
                + $tukar()->where($belumSelesai)->distinct()->count('h.No_Transaksi'),
            'approved' => $shift()->where('h.Flag_Selesai', 'Y')->distinct()->count('h.No_Transaksi')
                + $tukar()->where('h.Flag_Selesai', 'Y')->distinct()->count('h.No_Transaksi'),
        ];
    }
}
