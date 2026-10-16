<?php

namespace Tests\Feature\ESS\Attendance;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Services\ESS\Attendance\Leave\Personal\IzinCancellationService;
use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Skenario pembatalan transaksi izin/cuti/terlambat oleh admin (/izinAdmin).
 *
 * FOKUS UTAMA: pembatalan cuti yang SUDAH DISETUJUI. Saldo cuti dipotong dari dompet
 * saat approval final, jadi membatalkan tanpa mengembalikan saldo = jatah cuti karyawan
 * hangus diam-diam. Test ini mengunci: saldo kembali persis, tidak dobel, dan ledger
 * tetap konsisten (invarian yang dijaga `php artisan cuti:verify`).
 *
 * KARAKTER (mengikuti CutiBalanceEngineCharacterizationTest):
 *   - Env-gated (skip default) — menulis ke DB dev:
 *       RUN_REAL_IZIN_CANCEL_TEST=true php artisan test --filter IzinCancellation
 *   - Membuat transaksi + dompet UJI sendiri (No_Transaksi & wallet bertanda khusus),
 *     memakai tanggal jauh di masa depan agar tidak mengusik absensi nyata.
 *   - Self-cleaning: tearDown menghapus semua yang dibuatnya.
 */
class IzinCancellationTest extends TestCase
{
    private IzinCancellationService $svc;

    private TransactionService $trx;

    /** Karyawan nyata (view admin INNER JOIN Karyawan, jadi kode sintetis tak akan tampil). */
    private string $kode;

    private string $admin;

    private string $tag;

    /** @var array<int,string> */
    private array $noTransaksi = [];

    /** @var array<int,int> */
    private array $wallets = [];

    /**
     * Dompet milik karyawan uji yang SUDAH ADA sebelum test. Apa pun di luar daftar ini
     * lahir dari test (termasuk dompet hutang yang dibuat otomatis oleh
     * TransactionService saat saldo tak cukup) dan wajib ikut dibersihkan — kalau tidak,
     * test meninggalkan hutang hantu di data karyawan.
     *
     * @var array<int,int>
     */
    private array $walletsAwal = [];

    private const PERUSAHAAN = '001';

    private const ALASAN = 'Salah input data, dibatalkan admin';

    protected function setUp(): void
    {
        parent::setUp();

        if (! filter_var(env('RUN_REAL_IZIN_CANCEL_TEST', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_REAL_IZIN_CANCEL_TEST=true untuk menjalankan test pembatalan izin (menulis DB dev).');
        }

        $this->svc = app(IzinCancellationService::class);
        $this->trx = app(TransactionService::class);
        $this->tag = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        // Karyawan NYATA (view admin INNER JOIN Karyawan) tapi yang TIDAK punya dompet
        // cuti aktif. Penting: debitBalanceWithDebt memotong FIFO dari SEMUA dompet
        // jenis 1&2 milik karyawan — kalau memakai karyawan yang punya dompet asli,
        // test akan mendebit saldo sungguhan dan angka assert jadi tidak deterministik.
        $karyawan = DB::table('Karyawan as k')
            ->whereNotNull('k.Nama')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('HRIS_Buku_Cuti as b')
                    ->whereColumn('b.Kode_Karyawan', 'k.Kode_Karyawan')
                    ->whereIn('b.Id_Jenis_Cuti', [1, 2]);
            })
            ->orderBy('k.Kode_Karyawan')
            ->first();

        if (! $karyawan) {
            $this->markTestSkipped('Butuh 1 karyawan tanpa dompet cuti aktif agar saldo uji deterministik.');
        }

        $this->kode = $karyawan->Kode_Karyawan;
        $this->admin = $karyawan->Kode_Karyawan;

        $this->walletsAwal = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $this->kode)
            ->pluck('Id_Buku_Cuti')
            ->all();
    }

    protected function tearDown(): void
    {
        foreach ($this->noTransaksi as $no) {
            DB::table('N_HRIS_Log_Buku_Cuti')->where('No_Transaksi_Ref', $no)->delete();

            DB::table('Transaksi_Cuti_Det')->where('No_Transaksi', $no)->delete();
            DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $no)->delete();
            DB::table('Transaksi_Cuti')->where('No_Transaksi', $no)->delete();

            DB::table('Transaksi_Sakit_Izin_Det')->where('No_Transaksi', $no)->delete();
            DB::table('Transaksi_Sakit_Izin_Detail')->where('No_Transaksi', $no)->delete();
            DB::table('Transaksi_Sakit_Izin')->where('No_Transaksi', $no)->delete();

            DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')->where('No_Transaksi', $no)->delete();
            DB::table('Transaksi_Terlambat_Pulang_Cepat')->where('No_Transaksi', $no)->delete();
        }

        // Semua dompet karyawan uji yang lahir SELAMA test — termasuk dompet hutang yang
        // dibuat sendiri oleh TransactionService, yang tidak pernah masuk $this->wallets.
        $baru = DB::table('HRIS_Buku_Cuti')
            ->where('Kode_Karyawan', $this->kode)
            ->when($this->walletsAwal, fn ($q) => $q->whereNotIn('Id_Buku_Cuti', $this->walletsAwal))
            ->pluck('Id_Buku_Cuti')
            ->all();

        $buang = array_values(array_unique(array_merge($this->wallets, $baru)));

        if ($buang) {
            DB::table('N_HRIS_Log_Buku_Cuti')->whereIn('Id_Buku_Cuti', $buang)->delete();
            DB::table('HRIS_Buku_Cuti')->whereIn('Id_Buku_Cuti', $buang)->delete();
        }

        parent::tearDown();
    }

    // ---------------------------------------------------------------- helpers

    private function sisaCuti(): float
    {
        return (float) Karyawan::where('Kode_Karyawan', $this->kode)->first()->sisa_cuti();
    }

    /** Dompet cuti uji: aktif & belum kedaluwarsa, agar bisa didebit FIFO. */
    private function buatDompet(float $saldo): int
    {
        $id = DB::table('HRIS_Buku_Cuti')->insertGetId([
            'Kode_Karyawan' => $this->kode,
            'Id_Jenis_Cuti' => 1,
            'Periode' => now()->year,
            'tipe_cuti' => 'Cuti Tahunan',
            'notes' => 'UJI_PEMBATALAN_'.$this->tag,
            'tanggal_diberikan' => now()->subDays(10)->toDateString(),
            'tanggal_expired' => now()->addYear()->toDateString(),
            'Activated_At' => now()->subDays(10)->toDateString(),
            'jumlah_awal_cuti' => $saldo,
            'sisa_cuti' => $saldo,
            'jumlah_terpakai' => 0,
            'Flag_Aktif' => 'Y',
            'created_at' => now(),
            'updated_at' => now(),
        ], 'Id_Buku_Cuti');

        $this->wallets[] = $id;

        return $id;
    }

    /**
     * Buat transaksi cuti. $approved=true meniru approval final: Flag_Approval='Y'
     * + debit saldo lewat jalur produksi (debitBalanceWithDebt), sama seperti
     * TransactionService::processCuti().
     */
    private function buatCuti(int $hari, bool $approved, int $idJenisCuti = 1): string
    {
        $no = 'CT'.$this->tag.'-'.count($this->noTransaksi);
        $this->noTransaksi[] = $no;

        DB::table('Transaksi_Cuti')->insert([
            'Kode_Perusahaan' => self::PERUSAHAAN,
            'No_Transaksi' => $no,
            'Tanggal' => now(),
            'Jam' => now()->format('H:i:s'),
            'Status' => null,
            'UserID' => 'UJI',
        ]);

        DB::table('Transaksi_Cuti_Detail')->insert([
            'Kode_Perusahaan' => self::PERUSAHAAN,
            'No_Transaksi' => $no,
            'Kode_Karyawan' => $this->kode,
            'Jenis' => 'cuti',
            'Tanggal_Cuti_Dari' => now()->addDays(200),
            'Tanggal_Cuti_Sampai' => now()->addDays(200 + $hari - 1),
            'Alasan' => 'Cuti uji pembatalan',
            'Flag_Approval' => $approved ? 'Y' : null,
            'Id_Jenis_Cuti' => $idJenisCuti,
        ]);

        $urut = DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $no)->value('Urut_Oto');

        for ($i = 0; $i < $hari; $i++) {
            DB::table('Transaksi_Cuti_Det')->insert([
                'Kode_Perusahaan' => self::PERUSAHAAN,
                'No_Transaksi' => $no,
                'Kode_Karyawan' => $this->kode,
                'Urut_Detail' => $urut,
                'Tanggal_Cuti' => now()->addDays(200 + $i),
            ]);
        }

        if ($approved) {
            $jenis = DB::table('N_HRIS_Jenis_Cuti')->where('Id_Jenis_Cuti', $idJenisCuti)->first();
            if ($jenis && $jenis->Memotong_Saldo_Cuti === 'Y') {
                $this->trx->debitBalanceWithDebt(
                    $this->kode, $idJenisCuti, (float) $hari, $no,
                    'PENGAJUAN_CUTI', 'Potong Cuti Approved', 'SYSTEM', $jenis->Nama_Jenis_Cuti,
                );
            }
        }

        return $no;
    }

    private function buatIzin(bool $approved): string
    {
        $no = 'IS'.$this->tag.'-'.count($this->noTransaksi);
        $this->noTransaksi[] = $no;

        DB::table('Transaksi_Sakit_Izin')->insert([
            'Kode_Perusahaan' => self::PERUSAHAAN,
            'No_Transaksi' => $no,
            'Tanggal' => now(),
            'Jam' => now()->format('H:i:s'),
            'Status' => null,
            'UserID' => 'UJI',
        ]);

        DB::table('Transaksi_Sakit_Izin_Detail')->insert([
            'Kode_Perusahaan' => self::PERUSAHAAN,
            'No_Transaksi' => $no,
            'Kode_Karyawan' => $this->kode,
            'Jenis' => 'izinFull',
            'Tanggal_Sakit_Izin_Dari' => now()->addDays(200),
            'Tanggal_Sakit_Izin_Sampai' => now()->addDays(200),
            'Alasan' => 'Izin uji pembatalan',
            'Flag_Approval' => $approved ? 'Y' : null,
        ]);

        $urut = DB::table('Transaksi_Sakit_Izin_Detail')->where('No_Transaksi', $no)->value('Urut_Oto');

        DB::table('Transaksi_Sakit_Izin_Det')->insert([
            'Kode_Perusahaan' => self::PERUSAHAAN,
            'No_Transaksi' => $no,
            'Kode_Karyawan' => $this->kode,
            'Urut_Detail' => $urut,
            'Tanggal_Sakit_Izin' => now()->addDays(200),
        ]);

        return $no;
    }

    private function buatTerlambat(bool $approved): string
    {
        $no = 'TP'.$this->tag.'-'.count($this->noTransaksi);
        $this->noTransaksi[] = $no;

        DB::table('Transaksi_Terlambat_Pulang_Cepat')->insert([
            'Kode_Perusahaan' => self::PERUSAHAAN,
            'No_Transaksi' => $no,
            'Tanggal' => now(),
            'Jam' => now()->format('H:i:s'),
            'Status' => null,
            'UserID' => 'UJI',
        ]);

        DB::table('Transaksi_Terlambat_Pulang_Cepat_Detail')->insert([
            'Kode_Perusahaan' => self::PERUSAHAAN,
            'No_Transaksi' => $no,
            'Kode_Karyawan' => $this->kode,
            'Jenis' => 'terlambat',
            'Tanggal_Masuk_Pulang' => now()->addDays(200),
            'Jam' => '08:30:00',
            'Alasan' => 'Terlambat uji pembatalan',
            'Flag_Approval' => $approved ? 'Y' : null,
        ]);

        return $no;
    }

    private function batalkan(string $no, ?string $alasan = null): array
    {
        $fp = $this->svc->buildPreview($no)['preview']['fingerprint'];

        return $this->svc->execute($no, $alasan ?? self::ALASAN, $fp, $this->admin);
    }

    private function headerCuti(string $no): object
    {
        return DB::table('Transaksi_Cuti')->where('No_Transaksi', $no)->first();
    }

    /** Baris ledger pengembalian saldo untuk transaksi ini. */
    private function barisPembatalan(string $no): \Illuminate\Support\Collection
    {
        return collect(DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('No_Transaksi_Ref', $no)
            ->where('Kategori_Transaksi', TransactionService::KATEGORI_PEMBATALAN)
            ->get());
    }

    // ---------------------------------------------------------------- skenario

    public function test_izin_pending_dibatalkan_tercatat_pelaku_dan_alasannya(): void
    {
        $no = $this->buatIzin(approved: false);

        $out = $this->batalkan($no);

        $this->assertTrue($out['ok'], 'Pembatalan izin pending harus berhasil');

        $h = DB::table('Transaksi_Sakit_Izin')->where('No_Transaksi', $no)->first();
        $this->assertSame('Y', $h->Status);
        $this->assertSame('Y', $h->Flag_Cancelled_By_Admin);
        $this->assertSame(self::ALASAN, $h->Cancel_Reason);
        $this->assertSame($this->admin, $h->Cancelled_By);
        $this->assertNotNull($h->Cancelled_By_Nama);
        $this->assertNotNull($h->Cancelled_At, 'Cancelled_At adalah penanda pembatalan admin');
    }

    public function test_transaksi_dibatalkan_tetap_terlihat_di_view_admin_tapi_hilang_dari_view_biasa(): void
    {
        $no = $this->buatIzin(approved: false);

        $this->assertTrue(
            DB::table('N_HRIS_VW_All_Transactions')->where('No_Transaksi', $no)->exists(),
            'Sebelum dibatalkan harus tampil di view biasa',
        );

        $this->batalkan($no);

        $adminRow = DB::table('N_HRIS_VW_All_Transactions_Admin')->where('No_Transaksi', $no)->first();

        $this->assertNotNull($adminRow, 'Transaksi dibatalkan HARUS tetap terlihat di /izinAdmin');
        $this->assertSame('Y', $adminRow->Status_Header);
        $this->assertSame(self::ALASAN, $adminRow->Cancel_Reason);
        $this->assertNotNull($adminRow->Cancelled_By_Nama);

        $this->assertFalse(
            DB::table('N_HRIS_VW_All_Transactions')->where('No_Transaksi', $no)->exists(),
            'Inbox approver & dashboard harus tetap bersih dari transaksi yang dibatalkan',
        );
    }

    public function test_cuti_sudah_disetujui_bisa_dibatalkan_dan_saldo_dikembalikan_persis(): void
    {
        $this->buatDompet(saldo: 10);
        $sebelum = $this->sisaCuti();

        $no = $this->buatCuti(hari: 3, approved: true);

        $this->assertSame(
            $sebelum - 3,
            $this->sisaCuti(),
            'Prasyarat: approval final memotong 3 hari dari saldo',
        );

        $out = $this->batalkan($no);

        $this->assertTrue($out['ok'], 'Cuti yang SUDAH DISETUJUI harus bisa dibatalkan admin');
        $this->assertSame(3.0, (float) $out['result']['refund']['refunded']);
        $this->assertSame($sebelum, $this->sisaCuti(), 'Saldo cuti harus kembali persis seperti semula');
        $this->assertSame('Y', $this->headerCuti($no)->Status);
    }

    public function test_pengembalian_saldo_menulis_jurnal_berpasangan_dan_rantai_ledger_tetap_utuh(): void
    {
        $idBuku = $this->buatDompet(saldo: 10);
        $no = $this->buatCuti(hari: 2, approved: true);

        $this->batalkan($no);

        $rows = collect(DB::table('N_HRIS_Log_Buku_Cuti')
            ->where('Id_Buku_Cuti', $idBuku)->orderBy('Id_Log')->get());

        $debit = $rows->firstWhere('Jenis_Mutasi', 'DEBIT');
        $credit = $rows->firstWhere('Kategori_Transaksi', TransactionService::KATEGORI_PEMBATALAN);

        $this->assertNotNull($debit);
        $this->assertNotNull($credit, 'Pengembalian saldo wajib meninggalkan jejak di ledger');
        $this->assertSame(-2.0, (float) $debit->Jumlah, 'DEBIT disimpan negatif');
        $this->assertSame(2.0, (float) $credit->Jumlah, 'CREDIT disimpan positif');

        // Kategori WAJIB mengandung ROLLBACK: getHutangTerpakai() mengecualikan kredit
        // '%ROLLBACK%' dari hutang GROSS, jadi hutang cuti yang dibatalkan tidak ikut
        // membebani kuota pinjam karyawan.
        $this->assertStringContainsString('ROLLBACK', $credit->Kategori_Transaksi);
        $this->assertSame('pembatalan_cuti', $credit->Source_Category);

        // Invarian yang dijaga `php artisan cuti:verify`.
        $prev = null;
        foreach ($rows as $r) {
            $this->assertEqualsWithDelta(
                (float) $r->Saldo_Sebelum + (float) $r->Jumlah,
                (float) $r->Saldo_Sesudah,
                0.001,
                'WALLET_ROW_MATH: Saldo_Sesudah harus = Saldo_Sebelum + Jumlah',
            );
            if ($prev !== null) {
                $this->assertEqualsWithDelta(
                    (float) $prev,
                    (float) $r->Saldo_Sebelum,
                    0.001,
                    'WALLET_CHAIN_BREAK: rantai saldo antar baris harus nyambung',
                );
            }
            $prev = $r->Saldo_Sesudah;
        }

        $walletSisa = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBuku)->value('sisa_cuti');
        $this->assertEqualsWithDelta(
            (float) $prev,
            (float) $walletSisa,
            0.001,
            'WALLET_TIP_DRIFT: ujung ledger harus sama dengan sisa_cuti dompet',
        );
    }

    public function test_pengembalian_saldo_memulihkan_jumlah_terpakai_tanpa_menggelembungkan_jumlah_awal(): void
    {
        $idBuku = $this->buatDompet(saldo: 10);
        $awal = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBuku)->first();

        $no = $this->buatCuti(hari: 4, approved: true);
        $this->batalkan($no);

        $akhir = DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBuku)->first();

        $this->assertEqualsWithDelta((float) $awal->sisa_cuti, (float) $akhir->sisa_cuti, 0.001);
        $this->assertEqualsWithDelta((float) $awal->jumlah_terpakai, (float) $akhir->jumlah_terpakai, 0.001);
        $this->assertEqualsWithDelta(
            (float) $awal->jumlah_awal_cuti,
            (float) $akhir->jumlah_awal_cuti,
            0.001,
            'Refund bukan akrual — jumlah_awal_cuti tidak boleh naik (itu bug creditBalance)',
        );
    }

    public function test_cuti_yang_belum_disetujui_dibatalkan_tanpa_pengembalian_saldo(): void
    {
        $this->buatDompet(saldo: 10);
        $sebelum = $this->sisaCuti();

        $no = $this->buatCuti(hari: 3, approved: false);

        $out = $this->batalkan($no);

        $this->assertTrue($out['ok']);
        $this->assertSame(0.0, (float) $out['result']['refund']['refunded'], 'Belum pernah didebit → tidak ada yang dikembalikan');
        $this->assertCount(0, $this->barisPembatalan($no));
        $this->assertSame($sebelum, $this->sisaCuti());
    }

    public function test_membatalkan_dua_kali_ditolak_dan_saldo_tidak_dikembalikan_dobel(): void
    {
        $this->buatDompet(saldo: 10);
        $no = $this->buatCuti(hari: 3, approved: true);

        $fp = $this->svc->buildPreview($no)['preview']['fingerprint'];
        $pertama = $this->svc->execute($no, self::ALASAN, $fp, $this->admin);
        $this->assertTrue($pertama['ok']);

        $saldoSetelahSekali = $this->sisaCuti();

        $kedua = $this->svc->execute($no, self::ALASAN, $fp, $this->admin);

        $this->assertFalse($kedua['ok']);
        $this->assertSame(409, $kedua['code']);
        $this->assertSame($saldoSetelahSekali, $this->sisaCuti(), 'Saldo tidak boleh bertambah dua kali');
        $this->assertCount(1, $this->barisPembatalan($no), 'Hanya boleh ada satu baris pengembalian di ledger');
    }

    public function test_fingerprint_basi_ditolak_dan_tidak_mengubah_apa_pun(): void
    {
        $this->buatDompet(saldo: 10);
        $no = $this->buatCuti(hari: 2, approved: true);
        $saldo = $this->sisaCuti();

        $out = $this->svc->execute($no, self::ALASAN, 'fingerprint-basi', $this->admin);

        $this->assertFalse($out['ok']);
        $this->assertSame(409, $out['code']);
        $this->assertNull($this->headerCuti($no)->Status, 'Transaksi tidak boleh ikut dibatalkan');
        $this->assertSame($saldo, $this->sisaCuti(), 'Saldo tidak boleh berubah');
        $this->assertCount(0, $this->barisPembatalan($no));
    }

    public function test_fingerprint_berubah_saat_approver_bertindak_setelah_preview(): void
    {
        $no = $this->buatCuti(hari: 2, approved: false);

        $fpLama = $this->svc->buildPreview($no)['preview']['fingerprint'];

        // Approver menyetujui setelah admin membuka modal konfirmasi.
        DB::table('Transaksi_Cuti_Detail')->where('No_Transaksi', $no)->update(['Flag_Approval' => 'Y']);

        $out = $this->svc->execute($no, self::ALASAN, $fpLama, $this->admin);

        $this->assertFalse($out['ok'], 'Dampak pembatalan berubah drastis antara pending dan approved — harus ditolak');
        $this->assertSame(409, $out['code']);
        $this->assertNull($this->headerCuti($no)->Status);
    }

    public function test_alasan_terlalu_pendek_ditolak(): void
    {
        $no = $this->buatIzin(approved: false);

        $out = $this->batalkan($no, 'salah');

        $this->assertFalse($out['ok']);
        $this->assertSame(422, $out['code']);
        $this->assertNull(DB::table('Transaksi_Sakit_Izin')->where('No_Transaksi', $no)->value('Status'));
    }

    public function test_terlambat_yang_sudah_disetujui_bisa_dibatalkan_tanpa_menyentuh_saldo_cuti(): void
    {
        $this->buatDompet(saldo: 10);
        $saldo = $this->sisaCuti();

        $no = $this->buatTerlambat(approved: true);
        $out = $this->batalkan($no);

        $this->assertTrue($out['ok']);
        $this->assertSame(0.0, (float) $out['result']['refund']['refunded']);
        $this->assertSame('Y', DB::table('Transaksi_Terlambat_Pulang_Cepat')->where('No_Transaksi', $no)->value('Status'));
        $this->assertSame($saldo, $this->sisaCuti(), 'Terlambat tidak pernah memotong saldo cuti');
    }

    public function test_jenis_cuti_yang_tidak_memotong_saldo_tidak_dikembalikan(): void
    {
        $jenis = DB::table('N_HRIS_Jenis_Cuti')
            ->where('Memotong_Saldo_Cuti', '!=', 'Y')
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $jenis) {
            $this->markTestSkipped('Tidak ada jenis cuti non-pemotong saldo untuk diuji.');
        }

        $this->buatDompet(saldo: 10);
        $saldo = $this->sisaCuti();

        $no = $this->buatCuti(hari: 2, approved: true, idJenisCuti: (int) $jenis->Id_Jenis_Cuti);

        $out = $this->batalkan($no);

        $this->assertTrue($out['ok']);
        $this->assertSame(0.0, (float) $out['result']['refund']['refunded'], 'Tak pernah didebit → jangan "dikembalikan"');
        $this->assertSame($saldo, $this->sisaCuti());
    }

    public function test_preview_menandai_dompet_kedaluwarsa_dan_saldo_tidak_bisa_dipakai_lagi(): void
    {
        // Dompet HIDUP saat cuti disetujui (dompet kedaluwarsa tak pernah bisa didebit —
        // FIFO memfilter tanggal_expired >= hari ini), lalu kedaluwarsa BELAKANGAN.
        // Saat dibatalkan, hari cuti dikembalikan ke dompet ASAL — bukan dialihkan ke
        // dompet aktif, sebab itu menciptakan saldo dari udara: hari itu memang sudah
        // hangus dengan atau tanpa pembatalan. Admin harus diberi tahu lewat preview.
        $idBuku = $this->buatDompet(saldo: 10);

        $no = $this->buatCuti(hari: 2, approved: true);

        DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBuku)->update([
            'tanggal_expired' => now()->subDays(3)->toDateString(),
        ]);

        $preview = $this->svc->buildPreview($no)['preview'];

        $this->assertNotNull($preview['refund']);
        $this->assertTrue($preview['refund']['ada_expired'], 'Preview wajib memperingatkan dompet kedaluwarsa');

        $saldoSebelum = $this->sisaCuti();
        $out = $this->batalkan($no);

        $this->assertTrue($out['ok']);
        $this->assertSame(2.0, (float) $out['result']['refund']['refunded'], 'Ledger tetap dikembalikan ke dompet asal');
        $this->assertSame($saldoSebelum, $this->sisaCuti(), 'Tapi hari kedaluwarsa memang tak bisa dipakai lagi');

        $this->assertEqualsWithDelta(
            10.0,
            (float) DB::table('HRIS_Buku_Cuti')->where('Id_Buku_Cuti', $idBuku)->value('sisa_cuti'),
            0.001,
            'Dompet asal pulih penuh di ledger, meski harinya tak lagi bisa dipakai',
        );
    }

    public function test_preview_transaksi_yang_sudah_dibatalkan_ditandai(): void
    {
        $no = $this->buatIzin(approved: false);
        $this->batalkan($no);

        $preview = $this->svc->buildPreview($no)['preview'];

        $this->assertTrue($preview['sudah_dibatalkan']);
        $this->assertNotNull($preview['dibatalkan_oleh']);
    }

    public function test_nomor_transaksi_tak_dikenal_ditolak(): void
    {
        $this->assertFalse($this->svc->buildPreview('XX99-99-9999')['ok']);

        $out = $this->svc->execute('XX99-99-9999', self::ALASAN, 'apa saja', $this->admin);
        $this->assertFalse($out['ok']);
        $this->assertSame(404, $out['code']);

        $hilang = $this->svc->execute('CT99-99-9999', self::ALASAN, 'apa saja', $this->admin);
        $this->assertFalse($hilang['ok']);
        $this->assertSame(404, $hilang['code']);
    }
}
