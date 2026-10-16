<?php

namespace Tests\Integration\VISITOR\Permit\Inspection;

use App\Services\VISITOR\Permit\Inspection\JadwalInspeksiService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dua orang berebut kursi terakhir sebuah slot inspeksi.
 *
 * Uji-uji lain di modul ini berjalan berurutan pada SATU koneksi, jadi semuanya
 * hijau bahkan kalau penguncian dicopot: pemesan kedua selalu melihat baris
 * pemesan pertama karena yang pertama sudah selesai. Yang berbahaya justru yang
 * TIDAK pernah terjadi berurutan — dua transaksi hidup bersamaan, keduanya
 * menghitung "terisi 0 dari 1", keduanya lolos, dan satu slot berkuota satu
 * terjual dua kali tanpa satu pun galat.
 *
 * Yang menutupnya adalah `lockForUpdate()` pada penghitungan ulang di dalam
 * `pesan()`. Pada SQL Server ia dikompilasi menjadi
 * `with(rowlock,updlock,holdlock)`: HOLDLOCK memasang kunci RENTANG, sehingga
 * bukan hanya baris yang ada yang terkunci, tetapi juga baris yang BELUM ada —
 * tepat yang dibutuhkan, sebab pesaingnya hendak MENYISIPKAN baris ke rentang
 * itu. Tanpa HOLDLOCK, menghitung nol baris tidak mengunci apa pun.
 *
 * Karena itu ujinya memakai DUA KONEKSI sungguhan dan sengaja tidak memakai
 * DatabaseTransactions — transaksi pembungkus milik harness justru akan
 * memblokir koneksi kedua dan menyembunyikan apa yang hendak dibuktikan.
 * Bersih-bersihnya dilakukan sendiri di tearDown.
 */
class RebutanSlotInspeksiTest extends TestCase
{
    private const POS = 'Pos Uji Rebutan Slot';

    /** Senin, jauh dari data dev mana pun. */
    private const HARI = '2027-01-04';

    private const JAM = self::HARI.' 08:00';

    private const KEDUA = 'sqlsrv_rebutan';

    private JadwalInspeksiService $jadwal;

    private int $idPos;

    private int $idShift;

    /** @var array<int,string> */
    private array $permit = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::HARI.' 06:00'));
        $this->jadwal = app(JadwalInspeksiService::class);

        /* Koneksi kedua = salinan konfigurasi koneksi utama. Ia harus benar-benar
           soket lain, bukan koneksi yang sama dengan nama lain, supaya kuncinya
           saling terasa. */
        config(['database.connections.'.self::KEDUA => config('database.connections.'.config('database.default'))]);

        /* Pos baru, jadi pola milik dev tidak perlu disentuh sama sekali:
           pesan() mencocokkan slot pada (pos, waktu mulai), sehingga pos lain
           tidak pernah ikut terpilih. */
        $this->idShift = (int) DB::table(JadwalInspeksiService::T_SHIFT)->insertGetId([
            'Nama_Shift' => 'Shift Uji Rebutan', 'Jam_Mulai' => '07:00', 'Jam_Selesai' => '15:00',
            'Urutan' => 99, 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);

        $this->idPos = (int) DB::table(JadwalInspeksiService::T_POS)->insertGetId([
            'Nama_Pos' => self::POS, 'Keterangan_Lokasi' => 'Gerbang uji', 'Flag_Aktif' => 'Y', 'Created_By' => 'UJI',
        ]);

        // SATU kursi saja — supaya "kursi terakhir" itu kursi pertama juga.
        DB::table('N_VISITOR_Inspeksi_Pola')->insert([
            'Id_Pos_Inspeksi' => $this->idPos,
            'Id_Shift_Inspeksi' => $this->idShift,
            'Hari_Minggu' => 1,
            'Jam_Mulai' => '08:00',
            'Jam_Selesai' => '08:30',
            'Durasi_Menit' => 30,
            'Kuota_Per_Slot' => 1,
            'Flag_Aktif' => 'Y',
            'Created_By' => 'UJI',
        ]);

        $this->permit = [$this->permitMenunggu(), $this->permitMenunggu()];
    }

    protected function tearDown(): void
    {
        foreach ([self::KEDUA, config('database.default')] as $koneksi) {
            try {
                while (DB::connection($koneksi)->transactionLevel() > 0) {
                    DB::connection($koneksi)->rollBack();
                }
            } catch (\Throwable) {
                // Koneksi kedua boleh saja tak pernah dipakai.
            }
        }

        DB::table('N_VISITOR_Inspeksi_Booking')->whereIn('No_Transaksi_Permit', $this->permit)->delete();
        DB::table('N_VISITOR_Permit_Efektif')->whereIn('No_Transaksi', $this->permit)->delete();
        DB::table('N_EMI_VISITOR_Transaksi_Permit')->whereIn('No_Transaksi', $this->permit)->delete();
        DB::table('N_VISITOR_Inspeksi_Pola')->where('Id_Pos_Inspeksi', $this->idPos)->delete();
        DB::table(JadwalInspeksiService::T_POS)->where('Id_Pos_Inspeksi', $this->idPos)->delete();
        DB::table(JadwalInspeksiService::T_SHIFT)->where('Id_Shift_Inspeksi', $this->idShift)->delete();

        DB::purge(self::KEDUA);
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Pemesan kedua tidak boleh bisa BERJALAN selagi yang pertama belum selesai.
     *
     * Ia tidak harus ditolak dengan pesan "penuh" — pada titik ini baris pesaing
     * memang belum di-commit dan belum boleh terbaca. Yang wajib: ia MENUNGGU,
     * lalu menyerah karena batas waktu kunci. Kalau ia justru lolos memesan,
     * artinya kuncinya tidak memasang rentang dan slot berkuota satu baru saja
     * terjual dua kali.
     */
    public function test_pemesan_kedua_terkunci_selagi_yang_pertama_belum_selesai(): void
    {
        DB::beginTransaction();
        $this->jadwal->pesan($this->permit[0], $this->idPos, self::JAM, 'UJI-A');

        // Tanpa batas ini, uji yang gagal akan MENGGANTUNG, bukan memerah.
        DB::connection(self::KEDUA)->statement('SET LOCK_TIMEOUT 4000');

        $kedua = clone $this->jadwal;
        $asli = config('database.default');
        config(['database.default' => self::KEDUA]);
        DB::setDefaultConnection(self::KEDUA);

        try {
            DB::beginTransaction();
            $kedua->pesan($this->permit[1], $this->idPos, self::JAM, 'UJI-B');

            $this->fail('Pemesan kedua lolos selagi transaksi pertama masih terbuka: satu kursi terjual dua kali.');
        } catch (QueryException $e) {
            /* "Lock request time out period exceeded" (galat 1222) = ia MENUNGGU
               kunci pemesan pertama sampai batas waktu, bukan gagal karena
               sebab lain. Di basis ini RCSI mati, jadi yang lebih dulu memblokir
               justru pembacaan ketersediaannya — tetap bukti yang sama: pemesan
               kedua tidak bisa berjalan bersamaan. */
            $this->assertStringContainsString(
                'Lock request time out',
                $e->getMessage(),
                'Gagal, tetapi bukan karena menunggu kunci: '.$e->getMessage(),
            );
        } finally {
            while (DB::connection(self::KEDUA)->transactionLevel() > 0) {
                DB::connection(self::KEDUA)->rollBack();
            }

            DB::setDefaultConnection($asli);
            config(['database.default' => $asli]);
            DB::rollBack();
        }
    }

    /**
     * Penghitungan ulangnya WAJIB memakai kunci rentang.
     *
     * Uji dua koneksi di atas tidak bisa membuktikan ini sendirian, dan itu
     * sudah dibuktikan: dengan `lockForUpdate()` dicopot pun ia tetap hijau,
     * sebab pada saat pemesan kedua berjalan, yang pertama SUDAH menyisipkan
     * barisnya — pembacaannya lalu tertahan kunci baris biasa. Yang berbahaya
     * adalah jendela yang lebih sempit: A menghitung, B menghitung, baru
     * keduanya menyisipkan. Tak ada cara memaksa urutan itu lewat API publik,
     * jadi yang dijaga di sini adalah SEBABNYA — hint kuncinya benar-benar
     * terkirim ke SQL Server.
     *
     * `with(rowlock,updlock,holdlock)`: HOLDLOCK-lah yang mengunci baris yang
     * BELUM ada, dan hanya itu yang menghentikan dua penghitungan nol berbarengan.
     */
    public function test_penghitungan_kursi_dikirim_dengan_kunci_rentang(): void
    {
        $kueri = [];
        DB::listen(function ($q) use (&$kueri) {
            $kueri[] = $q->sql;
        });

        DB::transaction(fn () => $this->jadwal->pesan($this->permit[0], $this->idPos, self::JAM, 'UJI-A'));

        $penghitung = array_values(array_filter(
            $kueri,
            fn (string $sql) => str_contains($sql, 'N_VISITOR_Inspeksi_Booking')
                && str_contains($sql, 'count(*)')
                && str_contains($sql, 'Id_Pos_Inspeksi'),
        ));

        $this->assertNotEmpty($penghitung, 'Kursi tidak dihitung ulang sama sekali sebelum menyisipkan booking.');
        $this->assertStringContainsString(
            'with(rowlock,updlock,holdlock)',
            $penghitung[0],
            'Penghitungan kursi berjalan tanpa kunci rentang: dua pemesan bisa sama-sama melihat kursi terakhir masih kosong.',
        );
    }

    /**
     * Setelah yang pertama commit, yang kedua ditolak dengan sebab yang benar.
     *
     * Pasangan wajib dari uji di atas: mengunci tanpa pernah melepas juga
     * "tidak pernah menjual dua kali", dan uji pertama sendirian tidak bisa
     * membedakannya.
     */
    public function test_setelah_yang_pertama_commit_yang_kedua_ditolak_karena_penuh(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan($this->permit[0], $this->idPos, self::JAM, 'UJI-A'));

        try {
            DB::transaction(fn () => $this->jadwal->pesan($this->permit[1], $this->idPos, self::JAM, 'UJI-B'));
            $this->fail('Slot berkuota satu menerima pemesan kedua.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('penuh', mb_strtolower($e->getMessage()));
        }

        $this->assertSame(
            1,
            DB::table('N_VISITOR_Inspeksi_Booking')
                ->where('Id_Pos_Inspeksi', $this->idPos)
                ->where('Status', JadwalInspeksiService::TERJADWAL)
                ->count(),
        );
    }

    /**
     * Kursinya kembali dijual setelah dilepas — dan boleh diambil orang lain.
     *
     * Pembatalan yang tidak melepas kursi tidak menyalakan galat apa pun; ia
     * hanya membuat slot tampak penuh selamanya, dan tak seorang pun tahu
     * kursinya sebenarnya kosong.
     */
    public function test_kursi_yang_dilepas_bisa_diambil_pemesan_lain(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan($this->permit[0], $this->idPos, self::JAM, 'UJI-A'));
        $this->jadwal->batalkan($this->permit[0], 'Dibatalkan pemohon.', 'UJI-A');

        DB::transaction(fn () => $this->jadwal->pesan($this->permit[1], $this->idPos, self::JAM, 'UJI-B'));

        $terjadwal = DB::table('N_VISITOR_Inspeksi_Booking')
            ->where('Id_Pos_Inspeksi', $this->idPos)
            ->where('Status', JadwalInspeksiService::TERJADWAL)
            ->pluck('No_Transaksi_Permit')
            ->all();

        $this->assertSame([$this->permit[1]], $terjadwal, 'Kursi yang dilepas tidak berpindah bersih ke pemesan berikutnya.');
    }

    /**
     * Basis data menolak satu permit memegang dua kursi hidup sekaligus.
     *
     * Penjaganya indeks unik tersaring `UX_VISITOR_Inspeksi_Booking_Aktif`
     * (`No_Transaksi_Permit` WHERE `Status='TERJADWAL'`). Ia lapisan terakhir:
     * seluruh jalur di aplikasi memang mematikan booking lama sebelum memesan
     * yang baru, tetapi satu jalur baru yang lupa melakukannya akan berhenti di
     * sini, bukan diam-diam menggandakan kursi.
     */
    public function test_satu_permit_tidak_bisa_memegang_dua_kursi_hidup(): void
    {
        DB::transaction(fn () => $this->jadwal->pesan($this->permit[0], $this->idPos, self::JAM, 'UJI-A'));

        $this->expectException(QueryException::class);

        DB::table('N_VISITOR_Inspeksi_Booking')->insert([
            'No_Transaksi_Permit' => $this->permit[0],
            'Id_Pos_Inspeksi' => $this->idPos,
            'Id_Shift_Inspeksi' => $this->idShift,
            'Snapshot_Nama_Pos' => self::POS,
            'Waktu_Mulai' => Carbon::parse(self::HARI.' 09:00'),
            'Waktu_Selesai' => Carbon::parse(self::HARI.' 09:30'),
            'Status' => JadwalInspeksiService::TERJADWAL,
            'Created_At' => now(),
            'Created_By' => 'UJI',
        ]);
    }

    /** Permit menunggu keputusan, disalin dari baris dev sebagai cetakan kolom. */
    private function permitMenunggu(): string
    {
        $contoh = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->whereNull('No_Transaksi_Tamu')
            ->orderByDesc('Id_Transaksi_Permit')
            ->first();

        $this->assertNotNull($contoh, 'Butuh minimal satu permit karyawan di dev sebagai cetakan kolom wajib.');

        $baris = (array) $contoh;
        unset($baris['Id_Transaksi_Permit']);
        $nomor = 'UJI-RBT-'.strtoupper(substr(uniqid(), -8));
        $baris['No_Transaksi'] = $nomor;
        $baris['Flag_Approval'] = null;
        $baris['Flag_Aktif'] = 'Y';
        $baris['Idempotency_Key'] = null;
        $baris['Kode_Kartu'] = null;

        DB::table('N_EMI_VISITOR_Transaksi_Permit')->insert($baris);

        return $nomor;
    }
}
