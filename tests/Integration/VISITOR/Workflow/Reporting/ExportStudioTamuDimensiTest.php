<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Workflow\Reporting\Export\VisitorGuestDataset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Setiap dimensi dan ukuran Export Studio harus BENAR-BENAR bisa dijalankan.
 *
 * Dataset ini hanya daftar potongan SQL; tidak ada yang mengeksekusinya sampai
 * seseorang memilihnya di layar. Satu salah ketik karena itu tidak ketahuan
 * sampai pemakainya menyusun laporan, lalu dibalas galat basis data mentah yang
 * tidak menyebut dimensi mana penyebabnya.
 *
 * Penjaga ini menjalankan SEMUANYA — satu per satu, seperti yang dilakukan
 * Export Studio — sehingga yang rusak ketahuan saat menulisnya.
 */
class ExportStudioTamuDimensiTest extends TestCase
{
    use DatabaseTransactions;

    private function dataset(): VisitorGuestDataset
    {
        return app(VisitorGuestDataset::class);
    }

    public function test_setiap_dimensi_bisa_dikelompokkan(): void
    {
        $gagal = [];

        foreach ($this->dataset()->dimensi() as $dimensi) {
            try {
                $this->dataset()->baseQuery()
                    ->selectRaw($dimensi->select.' as nilai')
                    ->selectRaw('COUNT(*) as n')
                    ->groupByRaw($dimensi->select)
                    ->limit(5)
                    ->get();
            } catch (\Throwable $e) {
                $gagal[$dimensi->key] = substr($e->getMessage(), 0, 120);
            }
        }

        $this->assertSame([], $gagal, 'Ada dimensi yang tidak bisa dijalankan: '.json_encode($gagal));
    }

    public function test_setiap_ukuran_bisa_diagregasi(): void
    {
        $gagal = [];

        foreach ($this->dataset()->ukuran() as $ukuran) {
            try {
                $this->dataset()->baseQuery()->selectRaw($ukuran->agregat.' as nilai')->first();
            } catch (\Throwable $e) {
                $gagal[$ukuran->key] = substr($e->getMessage(), 0, 120);
            }
        }

        $this->assertSame([], $gagal, 'Ada ukuran yang tidak bisa dijalankan: '.json_encode($gagal));
    }

    public function test_setiap_saringan_bisa_dipakai_menyaring(): void
    {
        $gagal = [];

        foreach ($this->dataset()->saringan() as $saringan) {
            try {
                $this->dataset()->baseQuery()->whereRaw($saringan->kolom.' IS NOT NULL')->limit(1)->get();
            } catch (\Throwable $e) {
                $gagal[$saringan->key] = substr($e->getMessage(), 0, 120);
            }
        }

        $this->assertSame([], $gagal, 'Ada saringan yang tidak bisa dijalankan: '.json_encode($gagal));
    }

    /**
     * Sambungan baru TIDAK BOLEH melipatgandakan baris.
     *
     * Grain dataset ini satu transaksi kunjungan. Satu sambungan yang tidak
     * unik akan menggandakan SETIAP ukurannya — "Jumlah Tamu" jadi dua kali
     * lipat — dan angkanya tetap terlihat masuk akal, jadi tidak ada yang
     * curiga sampai dicocokkan dengan hitungan tangan.
     */
    public function test_grain_tetap_satu_baris_per_kunjungan(): void
    {
        $this->assertSame(
            DB::table('N_VISITOR_Tamu_Efektif')->count(),
            $this->dataset()->baseQuery()->count(),
            'Sambungan pada baseQuery melipatgandakan baris; seluruh ukuran ikut salah.',
        );
    }

    /**
     * Manifes rusak tidak boleh menggagalkan SELURUH ekspor.
     *
     * OPENJSON melempar pada teks yang bukan JSON. Tanpa penjaga ISJSON(), satu
     * baris warisan cukup untuk membuat setiap laporan tamu gagal dimuat —
     * dengan galat yang tidak menyebut-nyebut manifes sama sekali.
     */
    /**
     * Manifes rusak kini DICEGAH, bukan sekadar ditoleransi.
     *
     * Bentuk awal uji ini menyisipkan '{ini bukan json' lalu memastikan ekspor
     * tetap jalan. Sejak CHECK constraint
     * CK_N_EMI_VISITOR_Transaksi_Tamu_Detail_Daftar_Anggota_Json dipasang,
     * keadaan itu tidak bisa lagi dibentuk — basis datanya menolak lebih dulu.
     *
     * Yang dijaga sekarang adalah dua hal yang benar-benar berlaku: penolakan
     * itu nyata, dan dataset tetap tenang menghadapi manifes yang KOSONG —
     * satu-satunya bentuk "tidak ada daftar" yang masih mungkin ada, dan yang
     * memang dimiliki seluruh registrasi lama.
     */
    public function test_manifes_rusak_ditolak_basis_data(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->buatKunjunganDenganManifes('{ini bukan json');
    }

    public function test_manifes_kosong_tidak_menggagalkan_dataset(): void
    {
        $nomor = $this->buatKunjunganDenganManifes(null);

        $hasil = $this->dataset()->baseQuery()
            ->where('e.No_Transaksi', $nomor)
            ->selectRaw('rb.Daftar_Anggota as daftar')
            ->first();

        $this->assertNotNull($hasil, 'Kunjungan hilang dari dataset gara-gara manifes kosong.');
        $this->assertNull($hasil->daftar);
    }

    public function test_manifes_sah_terbaca_sebagai_daftar_nama(): void
    {
        $nomor = $this->buatKunjunganDenganManifes(json_encode([
            ['nama' => 'Budi Santoso'],
            ['nama' => 'Andi Wijaya'],
        ]));

        $hasil = $this->dataset()->baseQuery()
            ->where('e.No_Transaksi', $nomor)
            ->selectRaw('rb.Daftar_Anggota as daftar, rb.Jumlah_Anggota as jumlah')
            ->first();

        $this->assertSame('Budi Santoso, Andi Wijaya', $hasil->daftar);
        $this->assertSame(2, (int) $hasil->jumlah);
    }

    /**
     * Opsi saringan harus memakai kosakata yang benar-benar diproduksi datanya.
     *
     * Daftar lamanya menawarkan 'CHECKED_IN' dan 'CHECKED_OUT' — nilai yang
     * tidak pernah ditulis siapa pun ke Status_Efektif. Memilihnya memulangkan
     * laporan kosong, dan kosong itu terbaca sebagai "memang tidak ada
     * kunjungannya", bukan sebagai pilihan yang mustahil.
     */
    public function test_opsi_saringan_status_tidak_menawarkan_nilai_yang_mustahil(): void
    {
        $opsi = collect($this->dataset()->opsiSaringan()['status'])->pluck('value')->all();

        $this->assertNotContains('CHECKED_IN', $opsi);
        $this->assertNotContains('CHECKED_OUT', $opsi);

        $nyata = DB::table('N_VISITOR_Tamu_Efektif')->distinct()->pluck('Status_Efektif')->filter()->all();

        foreach ($nyata as $nilai) {
            $this->assertContains(
                $nilai,
                $opsi,
                "Status '{$nilai}' ada di data tetapi tidak bisa dipilih di Export Studio.",
            );
        }
    }

    /**
     * Kosakata siklus di ekspor WAJIB sama dengan milik monitoring.
     *
     * Dua layar yang menamai keadaan yang sama dengan istilah berbeda memaksa
     * pembacanya menerjemahkan sendiri — dan terjemahan itulah yang keliru.
     */
    public function test_kosakata_siklus_sama_dengan_monitoring(): void
    {
        $dimensi = collect($this->dataset()->dimensi())->firstWhere('key', 'siklus_kunjungan');
        $this->assertNotNull($dimensi, 'Dimensi keadaan kunjungan hilang dari Export Studio.');

        $milikMonitoring = (new VisitorKehadiranQueryService)->siklusKunjunganSql('h', 'e', 'td');

        $this->assertSame(
            $milikMonitoring,
            $dimensi->select,
            'Ekspor memakai ekspresi siklus sendiri; ia akan menyimpang dari monitoring tanpa ada yang tahu.',
        );
    }

    /**
     * Opsi saringan siklus = kosakata status tampil DDL-2026-121, persis.
     *
     * Kosakata lama (TERJADWAL, DI_DALAM, TIDAK_DATANG, ...) tidak lagi
     * diproduksi ekspresinya; menawarkannya memulangkan laporan kosong.
     */
    public function test_opsi_siklus_sama_dengan_status_tampil(): void
    {
        $opsi = collect($this->dataset()->opsiSaringan()['siklus_kunjungan'])->pluck('value')->all();

        $this->assertSame(StatusKunjunganTamu::TAMPIL, $opsi);
        foreach (['TERJADWAL', 'MENUNGGU_APPROVAL', 'DI_DALAM', 'SUDAH_KELUAR', 'TIDAK_DATANG', 'DITOLAK', 'DIBATALKAN'] as $lama) {
            $this->assertNotContains($lama, $opsi);
        }
    }

    /** Label opsi siklus terbaca manusia, bukan kode mentah (audit VMS 27 Sep, Low). */
    public function test_opsi_siklus_berlabel_manusia(): void
    {
        $opsi = collect($this->dataset()->opsiSaringan()['siklus_kunjungan'])->pluck('label', 'value')->all();

        $this->assertSame('Keluar Tak Tercatat', $opsi[StatusKunjunganTamu::KELUAR_TAK_TERCATAT]);
        $this->assertSame(StatusKunjunganTamu::LABEL, $opsi);
    }

    /** Dimensi & saringan baru DDL-2026-121: kategori tamu dan estimasi. */
    public function test_dimensi_kategori_dan_estimasi_tersedia(): void
    {
        $dimensi = collect($this->dataset()->dimensi())->pluck('key')->all();
        foreach (['tamu_kategori', 'estimasi_masuk', 'estimasi_keluar'] as $kunci) {
            $this->assertContains($kunci, $dimensi, "Dimensi {$kunci} hilang dari Export Studio.");
        }

        $this->assertContains('tamu_kategori', collect($this->dataset()->saringan())->pluck('key')->all());
        $this->assertSame(
            ['VISITOR', 'MITRA'],
            collect($this->dataset()->opsiSaringan()['tamu_kategori'])->pluck('value')->all(),
        );
    }

    public function test_kategori_dan_estimasi_terbaca_dari_detail(): void
    {
        $nomor = $this->buatKunjunganDenganManifes(null);
        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->where('No_Transaksi', $nomor)->update([
            'Tamu_Kategori' => 'MITRA',
            'Waktu_Mulai_Kunjungan' => now()->startOfDay()->addHours(8),
            'Waktu_Selesai_Kunjungan' => now()->addDays(2)->startOfDay()->addHours(17),
        ]);

        $pilih = collect($this->dataset()->dimensi())->keyBy('key');
        $hasil = $this->dataset()->baseQuery()
            ->where('e.No_Transaksi', $nomor)
            ->selectRaw($pilih['tamu_kategori']->select.' as kategori')
            ->selectRaw($pilih['estimasi_masuk']->select.' as masuk')
            ->selectRaw($pilih['estimasi_keluar']->select.' as keluar')
            ->first();

        $this->assertNotNull($hasil);
        $this->assertSame('MITRA', $hasil->kategori);
        $this->assertNotNull($hasil->masuk);
        $this->assertNotNull($hasil->keluar);
    }

    /** @param string|null $json Manifes apa adanya; null berarti tak pernah dikumpulkan. */
    private function buatKunjunganDenganManifes(?string $json): string
    {
        $nomor = 'VSR-EXP-'.Str::upper(Str::random(8));
        $pic = 'A1';

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => $pic,
            'Status_Kunjungan' => 'SCHEDULED',
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => now()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => $pic,
            'Idempotency_Key' => 'EXP-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Ekspor',
            'Tipe_Tamu' => 'REGULAR',
            'Jumlah_Tamu' => 3,
            'Daftar_Anggota_Json' => $json,
        ]);

        app(\App\Services\VISITOR\Operations\Expiration\VisitorEfektifService::class)->syncTamuEfektif($nomor);

        return $nomor;
    }
}
