<?php

namespace Tests\Integration\VISITOR\Workflow\Reporting;

use App\Services\VISITOR\Workflow\Reporting\Export\VisitorPermitDataset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Export Studio Permit harus menyebut PEMEGANG izin, dan tahu soal jendela jam.
 *
 * Dataset ini hanya daftar potongan SQL; tidak ada yang mengeksekusinya sampai
 * seseorang memilihnya di layar. Satu salah ketik karena itu baru ketahuan saat
 * pemakainya menyusun laporan — dibalas galat basis data mentah yang tidak
 * menyebut dimensi mana penyebabnya. Penjaga ini menjalankan semuanya.
 */
class ExportStudioPermitDimensiTest extends TestCase
{
    use DatabaseTransactions;

    private function dataset(): VisitorPermitDataset
    {
        return app(VisitorPermitDataset::class);
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

    public function test_setiap_ukuran_dan_saringan_bisa_dijalankan(): void
    {
        $gagal = [];

        foreach ($this->dataset()->ukuran() as $ukuran) {
            try {
                $this->dataset()->baseQuery()->selectRaw($ukuran->agregat.' as nilai')->first();
            } catch (\Throwable $e) {
                $gagal['ukuran:'.$ukuran->key] = substr($e->getMessage(), 0, 120);
            }
        }

        foreach ($this->dataset()->saringan() as $saringan) {
            try {
                $this->dataset()->baseQuery()->whereRaw($saringan->kolom.' IS NOT NULL')->limit(1)->get();
            } catch (\Throwable $e) {
                $gagal['saringan:'.$saringan->key] = substr($e->getMessage(), 0, 120);
            }
        }

        $this->assertSame([], $gagal, json_encode($gagal));
    }

    public function test_grain_tetap_satu_baris_per_permit(): void
    {
        // Sambungan yang tidak unik menggandakan SETIAP ukuran sambil tetap
        // memulangkan angka yang terlihat masuk akal.
        // Dibandingkan dengan populasi yang SAMA. Sejak DDL-2026-132 baseQuery
        // sengaja membuang baris akses sementara — ia lahir saat pemohon
        // memesan slot inspeksi, jauh sebelum ada keputusan untuk dilaporkan —
        // jadi menagihnya sama dengan seluruh isi tabel akan menuduh penyaring
        // yang justru diminta.
        $this->assertSame(
            DB::table('N_VISITOR_Permit_Efektif')->where('Jenis_Akses', 'PERMIT')->count(),
            $this->dataset()->baseQuery()->count(),
            'Sambungan pada baseQuery melipatgandakan baris; seluruh ukuran ikut salah.',
        );
    }

    /**
     * Pemegang izin, bukan pemohonnya — DAN sebaliknya untuk izin karyawan.
     *
     * Dua arah diuji bersamaan karena penjaga yang terlalu bersemangat akan
     * mengosongkan nama pada izin karyawan biasa, dan itu tidak akan terlihat
     * oleh uji yang hanya memeriksa izin tamu.
     */
    public function test_pemegang_izin_dibedakan_dari_pemohonnya(): void
    {
        [$tamu, $karyawan] = $this->buatDuaPermit();

        $dimensi = collect($this->dataset()->dimensi())->firstWhere('key', 'nama_pemegang');
        $this->assertNotNull($dimensi, 'Dimensi pemegang izin hilang dari Export Studio.');

        $hasil = $this->dataset()->baseQuery()
            ->whereIn('e.No_Transaksi', [$tamu, $karyawan])
            ->selectRaw('e.No_Transaksi, '.$dimensi->select.' as pemegang, e.Nama_Pemohon as pemohon')
            ->get()
            ->keyBy('No_Transaksi');

        $this->assertSame('Bagas Pengunjung', $hasil[$tamu]->pemegang);
        $this->assertNotSame($hasil[$tamu]->pemohon, $hasil[$tamu]->pemegang);

        // Izin karyawan: pemegang = pemohonnya sendiri, bukan '-' atau kosong.
        $this->assertSame($hasil[$karyawan]->pemohon, $hasil[$karyawan]->pemegang);
    }

    /**
     * Sisa masa berlaku menghormati JENDELA JAM.
     *
     * Izin yang berakhir pukul 08:00 hari ini sudah mati saat laporannya
     * disusun siang hari, tetapi hitungan berbasis tanggal memulangkan "sisa 0"
     * — sama persis dengan izin yang masih hidup sampai tengah malam.
     */
    public function test_sisa_masa_berlaku_membaca_jendela_jam(): void
    {
        [$tamu] = $this->buatDuaPermit();

        DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $tamu)
            ->update([
                'Tanggal_Kadaluarsa' => now()->toDateString(),
                'Waktu_Berakhir' => now()->subHours(2),
            ]);

        $dimensi = collect($this->dataset()->dimensi())->firstWhere('key', 'sisa_jam');
        $sisa = $this->dataset()->baseQuery()
            ->where('e.No_Transaksi', $tamu)
            ->selectRaw($dimensi->select.' as sisa')
            ->value('sisa');

        $this->assertLessThan(0, (int) $sisa, 'Izin yang sudah lewat jamnya masih terbaca belum berakhir.');
    }

    /**
     * Opsi 'API' WAJIB ada walau datanya belum muncul.
     *
     * syncPermitEfektif() sudah menulisnya untuk permit aplikasi mitra, tetapi
     * belum ada yang terbit karena permit mitra baru bisa disetujui sejak masa
     * berlakunya ditetapkan verifikator. Daftar yang menunggu datanya muncul
     * dulu akan menyembunyikan SELURUH permit mitra pada hari pertama ia ada.
     */
    public function test_opsi_sumber_permit_memuat_seluruh_nilai_yang_mungkin(): void
    {
        $opsi = collect($this->dataset()->opsiSaringan()['sumber_permit'])->pluck('value')->all();

        foreach (['KARYAWAN', 'TAMU', 'API'] as $nilai) {
            $this->assertContains($nilai, $opsi);
        }

        $nyata = DB::table('N_VISITOR_Permit_Efektif')->distinct()->pluck('Sumber_Permit')->filter()->all();

        foreach ($nyata as $nilai) {
            $this->assertContains($nilai, $opsi, "Sumber '{$nilai}' ada di data tetapi tak bisa dipilih.");
        }
    }

    /** @return array{0:string,1:string} [permit tamu, permit karyawan] */
    private function buatDuaPermit(): array
    {
        return [
            $this->buatPermitEfektif(pemegang: 'Bagas Pengunjung', sumber: 'TAMU'),
            $this->buatPermitEfektif(pemegang: null, sumber: 'KARYAWAN'),
        ];
    }

    private function buatPermitEfektif(?string $pemegang, string $sumber): string
    {
        $nomor = 'PMT-EXP-'.Str::upper(Str::random(8));

        DB::table('N_VISITOR_Permit_Efektif')->insert([
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => 'A1',
            'Nama_Pemohon' => 'Pemohon Uji Ekspor',
            'Nama_Pemegang' => $pemegang,
            'Instansi_Pemegang' => $pemegang ? 'PT Uji Ekspor' : null,
            'Sumber_Permit' => $sumber,
            'Status_Efektif' => 'AKTIF',
            'Tanggal_Mulai_Berlaku' => now()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addDays(3)->toDateString(),
            'Waktu_Mulai_Berlaku' => $pemegang ? now()->startOfDay() : null,
            'Waktu_Berakhir' => $pemegang ? now()->addDays(3)->setTime(17, 0) : null,
            'Disetujui_Oleh_Nama' => 'Sistem Uji',
            'Disetujui_At' => now(),
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => now(),
            'Dibuat_Oleh_Nama' => 'Sistem Uji',
            'Updated_At' => now(),
            'Flag_Sync' => 'Y',
        ]);

        return $nomor;
    }
}
