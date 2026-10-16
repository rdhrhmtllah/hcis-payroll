<?php

namespace Tests\Unit\VISITOR\Permit\VehiclePermit;

use PHPUnit\Framework\TestCase;

/**
 * Permit COKLAT tamu hanya boleh punya SATU penerbit.
 *
 * Ada TIGA pintu yang menerbitkannya — registrasi tamu yang auto-approve,
 * persetujuan tamu oleh verifikator, dan penyuntingan data tamu — dan repo ini
 * sudah berulang kali kehilangan aturan karena ia hanya terpasang di salah satu
 * jalur yang setara. Penjaga ini menagih bahwa kolom tautannya, `No_Transaksi_Tamu`,
 * hanya ditulis PermitCoklatTamuService.
 *
 * Sekaligus menagih bahwa penulisan LANGSUNG ke proyeksi gerbang tidak kembali:
 * izin akses fisik yang lahir tanpa melewati transaksi permit adalah izin yang
 * tidak pernah disetujui siapa pun, dan tidak ada galat yang menyalakannya.
 */
class PermitCoklatSatuPenulisTest extends TestCase
{
    private const PENULIS_SAH = 'app/Services/VISITOR/Permit/VehiclePermit/PermitCoklatTamuService.php';

    /**
     * Boleh menulis proyeksi permit: keduanya jalur sinkron/pencabutan umum yang
     * memang bekerja untuk SEMUA sumber permit, bukan jalur penerbitan tamu.
     *
     * @var list<string>
     */
    private const PENULIS_PROYEKSI_SAH = [
        'app/Services/VISITOR/Operations/Expiration/VisitorEfektifService.php',
    ];

    public function test_kolom_tautan_hanya_ditulis_satu_berkas(): void
    {
        $pelanggar = [];

        foreach ($this->berkasProduksi() as $berkas) {
            $relatif = $this->relatif($berkas);

            if ($relatif === self::PENULIS_SAH) {
                continue;
            }

            $isi = (string) file_get_contents($berkas);

            // Menyasar PENULISAN saja. Pembacaan dan penyaringan
            // (`whereNull('No_Transaksi_Tamu')` pada kuota, `where(...)` pada
            // pencarian) memang tersebar dan bukan sasaran penjaga ini.
            if (preg_match("/'No_Transaksi_Tamu'\s*=>/", $isi)) {
                $pelanggar[] = $relatif;
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Tautan permit↔tamu ditulis di luar PermitCoklatTamuService:\n".implode("\n", $pelanggar),
        );
    }

    public function test_tidak_ada_lagi_penulisan_langsung_izin_tamu_ke_gerbang(): void
    {
        // Pola lama: baris proyeksi bersumber TAMU disisipkan langsung sebagai
        // AKTIF, tanpa transaksi permit dan tanpa alur persetujuan.
        $pelanggar = [];

        foreach ($this->berkasProduksi() as $berkas) {
            $relatif = $this->relatif($berkas);

            if (in_array($relatif, self::PENULIS_PROYEKSI_SAH, true)) {
                continue;
            }

            $isi = (string) file_get_contents($berkas);

            if (preg_match("/DB::table\(\s*'N_VISITOR_Permit_Efektif'\s*\)\s*->\s*insert\(/", $isi)) {
                $pelanggar[] = $relatif;
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            "Proyeksi permit disisipkan di luar VisitorEfektifService:\n".implode("\n", $pelanggar),
        );
    }

    public function test_snapshot_checklist_hanya_ditulis_satu_berkas(): void
    {
        // Bukan khusus COKLAT, tetapi COKLAT-lah yang membuatnya punya TIGA
        // pemanggil: permit karyawan, permit mitra, dan permit turunan tamu.
        // saveAnswers() meng-UPDATE baris ini, jadi pintu yang menyusun
        // insert-nya sendiri akan menghasilkan bentuk baris yang berbeda tipis —
        // dan jawabannya berhenti tersimpan tanpa satu pun galat.
        $penulisSah = 'app/Services/VISITOR/Permit/VehiclePermit/VisitorPermitChecklistService.php';
        $pelanggar = [];

        foreach ($this->berkasProduksi() as $berkas) {
            $relatif = $this->relatif($berkas);

            if ($relatif === $penulisSah) {
                continue;
            }

            $isi = (string) file_get_contents($berkas);

            if (preg_match("/DB::table\(\s*'N_EMI_VISITOR_Transaksi_Permit_Checklist'\s*\)\s*->\s*insert\(/", $isi)) {
                $pelanggar[] = $relatif;
            }
        }

        $this->assertSame(
            [],
            $pelanggar,
            'Snapshot checklist ditulis di luar VisitorPermitChecklistService:
'.implode('
', $pelanggar),
        );
    }

    public function test_ketiga_pintu_memakai_penerbit_yang_sama(): void
    {
        $pintu = [
            // Registrasi tamu (layar HCIS maupun API mitra) lewat sini.
            'app/Services/VISITOR/Guest/Visit/RegistrasiTamuService.php',
            // Persetujuan tamu oleh verifikator.
            'app/Services/VISITOR/Workflow/Approval/VisitorApprovalDecisionService.php',
            // Penyuntingan data tamu.
            'app/Http/Controllers/VISITOR/Guest/Visit/GuestRegistrationController.php',
        ];

        foreach ($pintu as $berkas) {
            $penuh = $this->akar().'/'.$berkas;

            $this->assertFileExists($penuh);
            $this->assertStringContainsString(
                'permitCoklatTamu',
                (string) file_get_contents($penuh),
                $berkas.' tidak memakai PermitCoklatTamuService.',
            );
        }
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

    private function akar(): string
    {
        return dirname(__DIR__, 5);
    }

    private function relatif(string $berkas): string
    {
        return str_replace('\\', '/', substr($berkas, strlen($this->akar()) + 1));
    }
}
