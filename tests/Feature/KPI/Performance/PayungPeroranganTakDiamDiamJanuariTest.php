<?php

namespace Tests\Feature\KPI\Performance;

use App\Services\KPI\KpiStartPeriodeResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payung perorangan tidak boleh diam-diam memberi Januari.
 *
 * resolve() memakai Start_Periode perorangan bila ada; kalau tidak, ia jatuh ke
 * `Periode` jadwal payung. Pada jadwal PERORANGAN, Periode biasanya NULL, dan
 * normalkan(null) menghasilkan bulan 1. Akibatnya setiap anggota tim yang tidak
 * ada di roster payung itu mulai dari Januari — tanpa error dan tanpa gejala.
 *
 * Itu bukan hipotesis. Komentar di FormKpiController menyebut korbannya:
 * PKPI08-26-0054 dan -0055 (Dhea dan Anita) tersimpan mulai 2026-01 lewat jalur
 * ini, sementara Amelia yang ada di roster tersimpan benar mulai 2026-02.
 *
 * Selama lubang ini terbuka, jadwal perorangan HARUS dilarang jadi payung tim —
 * dan larangan itulah yang membuat halaman Form KPI Tim tak bisa dibuka ketika
 * admin hanya menugaskan orang lewat jadwal perorangan. Menutup lubangnya di
 * sini menghapus alasan larangan itu ada.
 */
class PayungPeroranganTakDiamDiamJanuariTest extends TestCase
{
    use DatabaseTransactions;

    private function resolver(): KpiStartPeriodeResolver
    {
        return app(KpiStartPeriodeResolver::class);
    }

    /** Jadwal perorangan baru: Periode NULL, persis bentuk yang melahirkan Januari. */
    /**
     * Tutup jadwal tim GLOBAL yang sedang berjalan, di dalam transaksi uji.
     *
     * `bolehJadiPayungTim()` memang hanya menerima jadwal perorangan KETIKA tak
     * ada jadwal global yang sedang berjalan — itu aturannya, bukan kelalaian:
     * selama ada jadwal global sehat, halaman tim sudah terbuka lewat jalur itu.
     * Tanpa langkah ini, uji di bawah menuntut lebih keras daripada aturannya
     * dan hasilnya ditentukan oleh apa yang kebetulan ada di data dev hari itu —
     * merah di mesin yang sedang punya jadwal global berjalan, hijau di mesin
     * yang tidak. Semua perubahan di sini ikut dibatalkan bersama transaksinya.
     *
     * @return int jumlah jadwal global yang ditutup
     */
    private function tutupJadwalGlobalYangBerjalan(): int
    {
        $now = date('Y-m-d');

        return DB::table('KPI_Master_Penjadwalan')
            ->where('Flag_Aktif', 'Y')
            ->where('Flag_Form_KPI', 'Y')
            ->where(fn ($q) => $q->where('Flag_Perorangan', '<>', 'Y')->orWhereNull('Flag_Perorangan'))
            ->whereDate('Jadwal_Mulai', '<=', $now)
            ->whereDate('Jadwal_Akhir', '>=', $now)
            ->update(['Jadwal_Akhir' => now()->subDays(2)->format('Y-m-d H:i:s')]);
    }

    private function buatPayungPerorangan(int $tahun): object
    {
        $id = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji payung perorangan',
            'Tahun' => $tahun,
            'Periode' => null,
            'Flag_Form_KPI' => 'Y',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDay()->format('Y-m-d H:i:s'),
            'Jadwal_Akhir' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        return DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $id)->first();
    }

    /** Karyawan yang TIDAK punya Start_Periode perorangan di tahun itu. */
    private function karyawanTanpaBulanMulai(int $tahun): ?string
    {
        return DB::table('Karyawan as k')
            ->whereNotNull('k.Kode_Karyawan')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('N_KPI_Master_Penjadwalan_Perorangan as p')
                ->join('KPI_Master_Penjadwalan as m', 'm.Id_Master_Penjadwalan', '=', 'p.Id_Master_Penjadwalan')
                ->whereColumn('p.Kode_Karyawan', 'k.Kode_Karyawan')
                ->whereNotNull('p.Start_Periode')
                ->where('m.Tahun', $tahun))
            ->value('k.Kode_Karyawan');
    }

    public function test_anggota_di_luar_roster_tidak_menerima_januari(): void
    {
        $tahun = 2026;
        $payung = $this->buatPayungPerorangan($tahun);
        $kode = $this->karyawanTanpaBulanMulai($tahun);

        if (! $kode) {
            $this->markTestSkipped('Semua karyawan punya bulan mulai perorangan; kasusnya tak bisa dibentuk.');
        }

        $hasil = $this->resolver()->resolve($kode, $payung);

        $this->assertNotSame(
            $tahun.'-01',
            $hasil['mulai'] ?? null,
            'Anggota di luar roster payung perorangan menerima Januari diam-diam — '.
            'inilah yang membuat Dhea dan Anita tersimpan mulai 2026-01.',
        );

        $this->assertFalse(
            $hasil['tercakup'] ?? true,
            'Ketidaktercakupan harus dinyatakan, bukan disamarkan jadi sebuah bulan.',
        );
        $this->assertSame('tidak_tercakup', $hasil['sumber'] ?? null);
    }

    public function test_anggota_di_dalam_roster_tetap_memakai_bulannya_sendiri(): void
    {
        $tahun = 2026;
        $payung = $this->buatPayungPerorangan($tahun);
        $kode = $this->karyawanTanpaBulanMulai($tahun);

        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan yang bisa dipakai.');
        }

        DB::table('N_KPI_Master_Penjadwalan_Perorangan')->insert([
            'Id_Master_Penjadwalan' => $payung->Id_Master_Penjadwalan,
            'Kode_Karyawan' => $kode,
            'Start_Periode' => $tahun.'-05',
            'Flag_Aktif' => 'Y',
            'Keterangan' => 'Uji',
        ]);

        $hasil = $this->resolver()->resolve($kode, $payung);

        $this->assertSame($tahun.'-05', $hasil['mulai']);
        $this->assertSame('perorangan', $hasil['sumber']);
        $this->assertTrue($hasil['tercakup'] ?? false);
    }

    /**
     * Payung GLOBAL tidak berubah perilakunya. Ia punya Periode sungguhan, jadi
     * jatuh ke sana memang benar — dan seluruh pengisian tim yang ada hari ini
     * bersandar pada itu.
     */
    public function test_payung_global_tetap_jatuh_ke_periodenya(): void
    {
        $tahun = 2026;
        $id = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji payung global',
            'Tahun' => $tahun,
            'Periode' => $tahun.'-03',
            'Flag_Form_KPI' => 'Y',
            'Flag_Perorangan' => 'T',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDay()->format('Y-m-d H:i:s'),
            'Jadwal_Akhir' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $global = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $id)->first();

        $kode = $this->karyawanTanpaBulanMulai($tahun);

        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan yang bisa dipakai.');
        }

        $hasil = $this->resolver()->resolve($kode, $global);

        $this->assertSame($tahun.'-03', $hasil['mulai']);
        $this->assertSame('global', $hasil['sumber']);
        $this->assertTrue($hasil['tercakup'] ?? false);
    }

    /**
     * Gerbang halaman Form KPI Tim harus terbuka dengan jadwal perorangan saja.
     *
     * Inilah keluhan yang memulai semuanya: admin menugaskan A, B, C lewat
     * jadwal perorangan, lalu halaman Form KPI Tim tidak bisa dibuka — dan baru
     * bisa setelah sebuah jadwal pengisian GLOBAL dibuka.
     *
     * Diuji lewat kueri payung yang sama dengan yang dipakai halaman dan jalur
     * simpan, supaya keempat pintu tidak bisa berselisih lagi.
     */
    public function test_jadwal_perorangan_boleh_jadi_payung_tim(): void
    {
        $tahun = 2026;
        $this->tutupJadwalGlobalYangBerjalan();
        $payung = $this->buatPayungPerorangan($tahun);

        // Diuji lewat aturan yang dipakai halaman DAN simpan, bukan lewat
        // jadwalTimQuery() yang memang sengaja tetap menolak perorangan dari
        // PEMILIHAN otomatis.
        $ctl = new \App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
        $m = new \ReflectionMethod($ctl, 'bolehJadiPayungTim');
        $m->setAccessible(true);

        $this->assertTrue(
            $m->invoke($ctl, $payung),
            'Jadwal perorangan masih ditolak sebagai payung tim; halaman Form KPI Tim '.
            'akan tetap tak bisa dibuka ketika hanya jadwal perorangan yang berjalan.',
        );
    }

    /**
     * Prasyarat yang membuat pembukaan itu aman.
     *
     * Membuka gerbang tanpa menutup lubang Januari akan menghidupkan kembali
     * bug yang dulu membuat larangan ini dipasang. Penjaga ini menuntut kedua
     * hal berlaku BERSAMAAN: payungnya diterima, DAN orang di luar rosternya
     * tidak menerima bulan apa pun.
     */
    public function test_payung_diterima_tetapi_luar_roster_tetap_tak_tercakup(): void
    {
        $tahun = 2026;
        $this->tutupJadwalGlobalYangBerjalan();
        $payung = $this->buatPayungPerorangan($tahun);
        $kode = $this->karyawanTanpaBulanMulai($tahun);

        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan yang bisa dipakai.');
        }

        $ctl = new \App\Http\Controllers\KPI\Performance\Parameter\FormKpiController;
        $m = new \ReflectionMethod($ctl, 'bolehJadiPayungTim');
        $m->setAccessible(true);

        $this->assertTrue($m->invoke($ctl, $payung), 'Payungnya harus diterima.');

        $hasil = $this->resolver()->resolve($kode, $payung);

        $this->assertFalse($hasil['tercakup'], 'Dan orang di luar roster tetap tidak boleh tercakup.');
        $this->assertNull($hasil['mulai']);
    }

    /**
     * Jadwal perorangan yang PUNYA Periode tetap boleh dijatuhi.
     *
     * Yang berbahaya adalah periode kosongnya, bukan sifat peroranganya. Di data
     * dev 21 dari 26 jadwal perorangan punya Periode sungguhan; mengunci mereka
     * semua akan mematikan kasus yang selama ini benar — dan itu sempat terjadi
     * pada versi pertama perbaikan ini.
     */
    public function test_payung_perorangan_berperiode_tetap_dipakai(): void
    {
        $tahun = 2026;
        $id = DB::table('KPI_Master_Penjadwalan')->insertGetId([
            'Nama_Kegiatan' => 'Uji perorangan berperiode',
            'Tahun' => $tahun,
            'Periode' => $tahun.'-04',
            'Flag_Form_KPI' => 'Y',
            'Flag_Perorangan' => 'Y',
            'Flag_Aktif' => 'Y',
            'Jadwal_Mulai' => now()->subDay()->format('Y-m-d H:i:s'),
            'Jadwal_Akhir' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);
        $payung = DB::table('KPI_Master_Penjadwalan')->where('Id_Master_Penjadwalan', $id)->first();

        $kode = $this->karyawanTanpaBulanMulai($tahun);

        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan yang bisa dipakai.');
        }

        $hasil = $this->resolver()->resolve($kode, $payung);

        $this->assertSame($tahun.'-04', $hasil['mulai'], 'Periode sungguhan harus tetap dipakai.');
        $this->assertTrue($hasil['tercakup']);
    }
}
