<?php

namespace App\Console\Commands\SYSTEM\Application\Maintenance;

use App\Services\SYSTEM\Audit\Activity\GerbangDiagnostikRealtime;
use App\Services\SYSTEM\Audit\Activity\PerekamKueriPerRute;
use Illuminate\Console\Command;

/**
 * Mengendalikan sesi rekaman dari terminal, supaya bisa MENGAPIT suite uji.
 *
 * Tombol di layar bagus untuk menyisir fitur dengan tangan, tetapi menyisir
 * dengan tangan itu lambat dan bocor — yang terlewat tidak ketahuan terlewat.
 * Suite uji sudah menjalankan hampir setiap controller dan service yang punya
 * uji, jadi sekali jalan ia memberi profil yang jauh lebih luas:
 *
 *   php artisan diagnostik:rekam mulai
 *   DIAGNOSTIK_REKAM_UJI=true php artisan test
 *   php artisan diagnostik:rekam berhenti
 *   php artisan diagnostik:laporan --simpan
 *
 * Sesinya sengaja TIDAK dimulai otomatis oleh uji itu sendiri: kalau tiap
 * berkas uji memulai sesinya sendiri, tiap berkas akan menimpa yang
 * sebelumnya dan yang tersisa hanya berkas terakhir.
 */
class DiagnostikRekam extends Command
{
    protected $signature = 'diagnostik:rekam
                            {aksi=status : mulai | berhenti | status | hapus}';

    protected $description = 'Mulai/hentikan sesi rekaman kueri (HANYA lingkungan pengembangan).';

    public function handle(GerbangDiagnostikRealtime $gerbang, PerekamKueriPerRute $perekam): int
    {
        $tertutup = $gerbang->alasanTertutup();
        if ($tertutup !== null) {
            $this->error('Diagnostik tertutup: '.$tertutup);

            return self::FAILURE;
        }

        return match ((string) $this->argument('aksi')) {
            'mulai' => $this->mulai($perekam),
            'berhenti' => $this->berhenti($perekam),
            'hapus' => $this->hapus($perekam),
            default => $this->status($perekam),
        };
    }

    private function mulai(PerekamKueriPerRute $p): int
    {
        $r = $p->mulai();
        $this->info("Sesi {$r['sesi']} dimulai pada {$r['mulai']}.");
        $this->newLine();
        $this->line('Untuk ikut merekam dari suite uji, jalankan ujinya dengan:');
        $this->line('  DIAGNOSTIK_REKAM_UJI=true php artisan test');
        $this->newLine();
        $this->line('Lalu: php artisan diagnostik:rekam berhenti && php artisan diagnostik:laporan --simpan');

        return self::SUCCESS;
    }

    private function berhenti(PerekamKueriPerRute $p): int
    {
        $sesi = $p->sesiAktif();
        $p->berhenti();

        if ($sesi === null) {
            $this->warn('Tidak ada sesi yang berjalan.');

            return self::SUCCESS;
        }

        $l = $p->laporan($sesi);
        $this->info("Sesi {$sesi} dihentikan.");
        $this->line("  {$l['total']['permintaan']} permintaan/uji · {$l['total']['kueri']} kueri · {$l['total']['ms']} ms di basis data");

        return self::SUCCESS;
    }

    private function hapus(PerekamKueriPerRute $p): int
    {
        $sesi = $p->sesiAktif() ?? $p->sesiTerakhir();
        if ($sesi === null) {
            $this->warn('Tidak ada rekaman.');

            return self::SUCCESS;
        }

        $p->hapus($sesi);
        $this->info("Rekaman {$sesi} dihapus.");

        return self::SUCCESS;
    }

    private function status(PerekamKueriPerRute $p): int
    {
        $aktif = $p->sesiAktif();
        $terakhir = $p->sesiTerakhir();

        $this->line('berjalan : '.($aktif ?? '-'));
        $this->line('terakhir : '.($terakhir ?? '-'));

        if ($terakhir !== null) {
            $l = $p->laporan($terakhir);
            $this->line("isinya   : {$l['total']['permintaan']} permintaan/uji · {$l['total']['kueri']} kueri · {$l['total']['ms']} ms");
        }

        return self::SUCCESS;
    }
}
