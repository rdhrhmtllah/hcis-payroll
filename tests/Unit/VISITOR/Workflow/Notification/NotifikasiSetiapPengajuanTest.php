<?php

namespace Tests\Unit\VISITOR\Workflow\Notification;

use PHPUnit\Framework\TestCase;

/**
 * Setiap pengajuan Visitor wajib memberitahu antrean verifikatornya.
 *
 * Empat dari enam jalur pengajuan dulu tidak memberitahu siapa pun: registrasi
 * tamu B2B lewat API, permit mitra lewat API, permit COKLAT turunan kunjungan,
 * dan permit COKLAT yang terbit saat verifikator menyetujui kunjungan.
 * Pengajuannya masuk antrean dan menunggu sampai ada yang kebetulan membuka
 * mejanya — tanpa galat, tanpa gejala, sampai ada tamu tertahan di gerbang.
 *
 * Penjaga ini kasar dengan sengaja: yang ditagih adalah KEBERADAAN
 * pemberitahuannya, bukan isinya. Kekasaran itu yang membuatnya bertahan ketika
 * berkasnya disusun ulang.
 */
class NotifikasiSetiapPengajuanTest extends TestCase
{
    /**
     * Berkas yang MEMBUAT alur approval Visitor.
     *
     * Daftar ini ditagih persis. Pintu kelima yang muncul kelak harus menabrak
     * penjaga ini lebih dulu, bukan diam-diam menambah antrean yang tidak
     * diberitahukan.
     *
     * @var list<string>
     */
    private const PEMBUAT_ALUR = [
        'app/Http/Controllers/VISITOR/Permit/VehiclePermit/PermitTamuApiController.php',
        'app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php',
        'app/Services/VISITOR/Guest/Visit/RegistrasiTamuService.php',
        'app/Services/VISITOR/Permit/VehiclePermit/PermitCoklatTamuService.php',
    ];

    /**
     * Berkas yang benar-benar mengirim pemberitahuannya.
     *
     * Bukan selalu berkas yang sama dengan pembuat alurnya: dua service di atas
     * membuat alurnya DI DALAM transaksi, sedangkan notifikasi wajib dikirim
     * SESUDAH commit — menulis N_HRIS_Notification sambil memegang transaksi
     * pengajuan menahan lock tabel yang di-poll top-bar setiap pengguna.
     * Nomornya karena itu dipulangkan ke pemanggil, dan pemanggil yang
     * memberitahukannya.
     *
     * @var list<string>
     */
    private const PENGIRIM = [
        'app/Http/Controllers/VISITOR/Permit/VehiclePermit/PermitTamuApiController.php',
        'app/Http/Controllers/VISITOR/Permit/VehiclePermit/VehiclePermitController.php',
        'app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php',
        'app/Http/Controllers/VISITOR/Guest/Visit/TamuB2BApiController.php',
        'app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php',
    ];

    /**
     * Service yang membuat alur di dalam transaksi — DILARANG mengirim sendiri.
     *
     * @var list<string>
     */
    private const DI_DALAM_TRANSAKSI = [
        'app/Services/VISITOR/Guest/Visit/RegistrasiTamuService.php',
        'app/Services/VISITOR/Permit/VehiclePermit/PermitCoklatTamuService.php',
    ];

    public function test_daftar_pembuat_alur_approval_tidak_bertambah_diam_diam(): void
    {
        $ditemukan = [];

        foreach ($this->berkasProduksi() as $berkas) {
            if (str_contains((string) file_get_contents($berkas), '->createFlow(')) {
                $ditemukan[] = $this->relatif($berkas);
            }
        }

        sort($ditemukan);
        $diharapkan = self::PEMBUAT_ALUR;
        sort($diharapkan);

        $this->assertSame(
            $diharapkan,
            $ditemukan,
            'Pembuat alur approval berubah. Pintu baru wajib ikut memberitahu antreannya, '
                .'lalu didaftarkan di penjaga ini.',
        );
    }

    public function test_setiap_pengirim_benar_benar_menerbitkan_notifikasi_pengajuan(): void
    {
        foreach (self::PENGIRIM as $berkas) {
            $isi = $this->isi($berkas);

            $this->assertStringContainsString(
                'PublishVisitorNotificationsJob',
                $isi,
                $berkas.' tidak menerbitkan notifikasi apa pun.',
            );
            $this->assertStringContainsString(
                "'kind' => 'submission'",
                $isi,
                $berkas.' tidak menerbitkan notifikasi PENGAJUAN; keputusan saja tidak cukup.',
            );
        }
    }

    public function test_pengajuan_b2b_memberitahu_antrean_b2b(): void
    {
        // DDL-2026-121: tamu B2B punya pool verifikator sendiri (ADMIN_TAMU_B2B).
        // Pintu B2B yang masih memakai DOMAIN_TAMU mengirim pengajuannya ke
        // meja Regular — terkirim, tetapi ke orang yang tidak memegang kuncinya.
        foreach ([
            'app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php',
            'app/Http/Controllers/VISITOR/Guest/Visit/TamuB2BApiController.php',
            'app/Http/Controllers/VISITOR/Workflow/Verification/VerifikatorVisitorController.php',
        ] as $berkas) {
            $this->assertStringContainsString(
                'VisitorNotificationService::DOMAIN_TAMU_B2B',
                $this->isi($berkas),
                $berkas.' tidak mengenal domain notifikasi TAMU_B2B.',
            );
        }
    }

    public function test_pembuat_alur_di_dalam_transaksi_tidak_mengirim_sendiri(): void
    {
        foreach (self::DI_DALAM_TRANSAKSI as $berkas) {
            $this->assertStringNotContainsString(
                'PublishVisitorNotificationsJob',
                $this->isi($berkas),
                $berkas.' mengirim notifikasi dari dalam transaksi pengajuan; '
                    .'ia menahan lock N_HRIS_Notification yang di-poll top-bar setiap pengguna.',
            );
        }
    }

    public function test_nomor_pengajuannya_dipulangkan_supaya_pemanggil_bisa_memberitahu(): void
    {
        // Tanpa nilai balik ini, pemanggil tidak punya cara mengetahui apa yang
        // baru saja diajukan — dan notifikasinya tidak akan pernah terkirim.
        $this->assertStringContainsString(
            "'no_transaksi_permit' => \$noPermit",
            $this->isi('app/Services/VISITOR/Guest/Visit/RegistrasiTamuService.php'),
        );
        $this->assertStringContainsString(
            'return $noPermit;',
            $this->isi('app/Services/VISITOR/Permit/VehiclePermit/PermitCoklatTamuService.php'),
        );
    }

    private function akar(): string
    {
        return dirname(__DIR__, 5);
    }

    private function isi(string $relatif): string
    {
        $path = $this->akar().'/'.$relatif;

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return list<string> */
    private function berkasProduksi(): array
    {
        $hasil = [];

        foreach (['app/Http', 'app/Services'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->akar().'/'.$dir, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $berkas) {
                if ($berkas->isFile() && $berkas->getExtension() === 'php') {
                    $hasil[] = $berkas->getPathname();
                }
            }
        }

        return $hasil;
    }

    private function relatif(string $berkas): string
    {
        return str_replace('\\', '/', substr($berkas, strlen($this->akar()) + 1));
    }
}
