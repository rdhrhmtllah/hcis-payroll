<?php

namespace Tests\Feature\ESS\Attendance;

use App\Services\ESS\Attendance\Processing\PerencanaAntreanAbsen;
use ReflectionClass;
use Tests\TestCase;

/**
 * Satu pintu ke N_HRIS_SP_PLAN_ABSEN_REFRESH_QUEUE, dan urutannya dipatok.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * Tiga perintah artisan memanggil prosedur yang sama, dan sebelum dipusatkan
 * ketiganya menuliskan sendiri EXEC sembilan parameter POSISIONAL.
 *
 * Itu bagian paling berbahaya untuk disalin. Lima dari sembilan nilainya
 * bertipe string dan berdampingan — UserIDList, RefreshMode, Request_Source,
 * Requested_By, Mode. Tertukar satu pasang tidak menghasilkan galat: SQL Server
 * menerimanya, dan sistem merencanakan hal yang berbeda dari yang dimaksud,
 * diam-diam.
 *
 * Karena itu urutan argumennya dibangun oleh satu metode MURNI dan diadu
 * langsung dengan urutan yang tertulis di SQL-nya — dua sisi kontrak yang sama,
 * diperiksa terhadap satu sama lain, tanpa menyentuh basis data.
 */
class PerencanaAntreanAbsenSatuPintuTest extends TestCase
{
    /** Urutan yang dituntut prosedurnya. Mengubah ini berarti mengubah kontrak. */
    private const URUTAN = [
        'KodePerusahaan',
        'TglAwal',
        'TglAkhir',
        'UserIDList',
        'RefreshMode',
        'Request_Source',
        'Requested_By',
        'Priority',
        'Mode',
    ];

    private function sql(): string
    {
        $r = new ReflectionClass(PerencanaAntreanAbsen::class);

        return (string) $r->getConstant('SQL');
    }

    public function test_urutan_parameter_di_sql_sesuai_kontrak(): void
    {
        preg_match_all('/@(\w+)\s*=\s*\?/', $this->sql(), $m);

        $this->assertSame(
            self::URUTAN,
            $m[1],
            'Urutan parameter di SQL berubah. Nilai-nilainya diikat POSISIONAL, jadi '.
            'menggeser satu nama menggeser semua nilai sesudahnya — tanpa galat.',
        );
    }

    public function test_urutan_nilai_sama_dengan_urutan_sql(): void
    {
        /*
         * Nilainya sengaja dibuat saling bisa dibedakan. Kalau dua argumen
         * tertukar, penegasan ini menunjukkan PERSIS pasangan mana.
         */
        $nilai = PerencanaAntreanAbsen::parameter(
            'kode-perusahaan',
            'tgl-awal',
            'tgl-akhir',
            'user-id-list',
            'refresh-mode',
            'request-source',
            'requested-by',
            7,
            'mode',
        );

        $this->assertSame(
            [
                'kode-perusahaan',
                'tgl-awal',
                'tgl-akhir',
                'user-id-list',
                'refresh-mode',
                'request-source',
                'requested-by',
                7,
                'mode',
            ],
            $nilai,
        );

        $this->assertCount(
            count(self::URUTAN),
            $nilai,
            'Jumlah nilai harus sama dengan jumlah tanda tanya di SQL.',
        );
    }

    public function test_tanggal_boleh_null_untuk_mode_rolling(): void
    {
        $nilai = PerencanaAntreanAbsen::parameter(
            '001', null, null, null, 'FORCE', 'S', 'R', 3, PerencanaAntreanAbsen::MODE_ROLLING,
        );

        $this->assertNull($nilai[1]);
        $this->assertNull($nilai[2]);
        $this->assertSame('ROLLING_7_DAYS', $nilai[8]);
    }

    /** @return array<string, array{0: ?string, 1: string}> */
    public static function kasusRefreshMode(): array
    {
        return [
            'nilai sah lolos apa adanya' => ['FORCE', 'FORCE'],
            'huruf kecil dinaikkan' => ['force', 'FORCE'],
            'spasi dibuang' => ['  DIRTY_ONLY  ', 'DIRTY_ONLY'],
            'missing only' => ['MISSING_ONLY', 'MISSING_ONLY'],
            'salah ketik jatuh ke baku' => ['FORCEE', 'MISSING_OR_DIRTY'],
            'kosong jatuh ke baku' => ['', 'MISSING_OR_DIRTY'],
            'null jatuh ke baku' => [null, 'MISSING_OR_DIRTY'],
            'nilai asing jatuh ke baku' => ['DROP', 'MISSING_OR_DIRTY'],
        ];
    }

    /**
     * @dataProvider kasusRefreshMode
     */
    public function test_refresh_mode_dinormalkan_bukan_ditolak(?string $masuk, string $harap): void
    {
        /*
         * Sengaja MEMAAFKAN. Perintah ini dijalankan penjadwal; menolak karena
         * satu opsi salah ketik berarti satu malam tanpa perencanaan sama
         * sekali. SP-nya sendiri melempar RAISERROR untuk nilai di luar daftar,
         * jadi normalisasi ini yang mencegahnya sampai ke sana.
         */
        $this->assertSame($harap, PerencanaAntreanAbsen::normalkanRefreshMode($masuk));
    }

    public function test_setiap_nilai_sah_diakui(): void
    {
        foreach (PerencanaAntreanAbsen::REFRESH_MODE_SAH as $mode) {
            $this->assertSame(
                $mode,
                PerencanaAntreanAbsen::normalkanRefreshMode($mode),
                "Nilai {$mode} ada di daftar sah tetapi tidak lolos normalisasi.",
            );
        }
    }

    /** @return array<string, array{0: int, 1: int, 2: int}> */
    public static function kasusPrioritas(): array
    {
        return [
            'dalam rentang' => [5, 4, 5],
            'batas bawah' => [1, 4, 1],
            'batas atas' => [9, 4, 9],
            'di bawah batas' => [0, 4, 4],
            'negatif' => [-3, 4, 4],
            'di atas batas' => [10, 4, 4],
            'baku milik rolling berbeda' => [99, 3, 3],
        ];
    }

    /**
     * @dataProvider kasusPrioritas
     */
    public function test_prioritas_dijepit_ke_baku_pemanggil(int $masuk, int $baku, int $harap): void
    {
        $this->assertSame($harap, PerencanaAntreanAbsen::normalkanPrioritas($masuk, $baku));
    }

    public function test_hanya_satu_tempat_yang_memanggil_prosedurnya(): void
    {
        $ditemukan = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path(), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $berkas) {
            if ($berkas->getExtension() !== 'php') {
                continue;
            }

            $isi = (string) file_get_contents($berkas->getPathname());
            if (str_contains($isi, 'N_HRIS_SP_PLAN_ABSEN_REFRESH_QUEUE')) {
                $ditemukan[] = str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], $berkas->getPathname());
            }
        }

        $this->assertSame(
            ['app/Services/ESS/Attendance/Processing/PerencanaAntreanAbsen.php'],
            $ditemukan,
            "Prosedur perencanaan dipanggil dari lebih dari satu tempat:\n  "
            .implode("\n  ", $ditemukan)."\n"
            .'Setiap pemanggil kedua berarti satu urutan parameter lagi yang harus '
            .'dijaga, dan tertukarnya tidak menghasilkan galat.',
        );
    }

    public function test_opsi_ketiga_perintah_tidak_berubah(): void
    {
        /*
         * Baris N_HRIS_Scheduler_Configs memanggil perintah ini dengan opsi
         * yang dituliskan lengkap. Mengubah nama opsi berarti baris penjadwal
         * yang sudah ada gagal — dan gagalnya hanya terlihat di scheduler.log,
         * bukan di mana pun yang biasa dilihat orang.
         */
        $wajib = [
            'HrisAbsenPlanCustomCommand' => [
                'hris:absen-plan-custom', '--kode_perusahaan', '--tgl_awal', '--tgl_akhir',
                '--user_id_list', '--refresh_mode', '--request_source', '--requested_by', '--priority',
            ],
            'HrisAbsenPlanCutoffCommand' => [
                'hris:absen-plan-cutoff', '--kode_perusahaan', '--user_id_list',
                '--refresh_mode', '--request_source', '--requested_by', '--priority',
            ],
            'HrisAbsenPlanRollingCommand' => [
                'hris:absen-plan-rolling', '--kode_perusahaan', '--refresh_mode', '--priority',
            ],
        ];

        foreach ($wajib as $kelas => $opsi) {
            $isi = (string) file_get_contents(base_path(
                "app/Console/Commands/ESS/Attendance/Processing/{$kelas}.php",
            ));

            foreach ($opsi as $o) {
                $penanda = str_starts_with($o, '--') ? '{'.$o : $o;
                $this->assertStringContainsString(
                    $penanda,
                    $isi,
                    "{$kelas} kehilangan {$o}. Baris penjadwal yang memakainya akan gagal.",
                );
            }

            $this->assertStringNotContainsString(
                'EXEC dbo.',
                $isi,
                "{$kelas} kembali menulis pemanggilan prosedurnya sendiri.",
            );
        }
    }

    public function test_rolling_memakai_mode_yang_membuat_sp_menghitung_tanggalnya(): void
    {
        $rolling = (string) file_get_contents(base_path(
            'app/Console/Commands/ESS/Attendance/Processing/HrisAbsenPlanRollingCommand.php',
        ));

        $this->assertStringContainsString('MODE_ROLLING', $rolling);
        $this->assertStringNotContainsString(
            'MODE_RENTANG',
            $rolling,
            'Rolling tidak mengirim tanggal; memakai MODE_RENTANG membuat SP menolak '.
            'dengan "TglAwal dan TglAkhir wajib diisi".',
        );

        foreach (['HrisAbsenPlanCustomCommand', 'HrisAbsenPlanCutoffCommand'] as $kelas) {
            $isi = (string) file_get_contents(base_path(
                "app/Console/Commands/ESS/Attendance/Processing/{$kelas}.php",
            ));
            $this->assertStringContainsString(
                'MODE_RENTANG',
                $isi,
                "{$kelas} mengirim tanggal, jadi ia harus memakai MODE_RENTANG.",
            );
        }
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(6000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
