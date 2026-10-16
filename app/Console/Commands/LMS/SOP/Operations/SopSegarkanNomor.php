<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menulis ulang Nomor_SOP_Resmi dari kolom identitasnya.
 *
 * KENAPA PERINTAH INI ADA. Nomor dokumen dirakit dari tujuh segmen, tetapi
 * hasilnya DISIMPAN di Nomor_SOP_Resmi — kolom yang dibaca 45 berkas
 * (monitoring, ekspor, repository, dataset kepatuhan) dan dicerminkan ke
 * N_SOP_Paket_Item. Penulisannya dipicu peristiwa: dokumen lahir, identitasnya
 * diubah, atau versi berlakunya berganti.
 *
 * Mengubah MASTER tidak memicu satu pun dari ketiganya. Mengganti kode entitas,
 * kode departemen, kode jenis dokumen, atau kode APQC akan membuat nomor yang
 * DIRAKIT layar berbeda dari nomor yang TERSIMPAN — dua nomor untuk satu
 * dokumen, dan yang tersimpan itulah yang dibaca hampir semua laporan.
 *
 * Perintah ini menutup selisih itu. Ia tak memilih nomor apa pun sendiri:
 * seluruhnya lewat SopDokumenService::segarkanNomorResmi(), penulis tunggal
 * yang sama dengan jalur biasa, termasuk penyebarannya ke item paket.
 */
class SopSegarkanNomor extends Command
{
    protected $signature = 'sop:segarkan-nomor
        {--dry-run : Perlihatkan apa yang akan berubah tanpa menulis apa pun}
        {--entitas= : Batasi pada dokumen yang PEMILIKnya berkode ini, misalnya EHG}';

    protected $description = 'Menulis ulang Nomor_SOP_Resmi dari kolom identitas (perlu setelah kode master berubah)';

    public function handle(SopDokumenService $dokumen): int
    {
        $kering = (bool) $this->option('dry-run');
        $kodeEntitas = trim((string) $this->option('entitas'));

        $q = DB::table('N_SOP_Dokumen as d')->where('d.Flag_Aktif', SopStatus::AKTIF);

        if ($kodeEntitas !== '') {
            $idEntitas = DB::table('N_HRIS_Master_Entitas')
                ->whereRaw('UPPER(Kode_Entitas) = ?', [strtoupper($kodeEntitas)])
                ->value('Id_Entitas');

            if (! $idEntitas) {
                $this->error("Entitas berkode {$kodeEntitas} tidak ada.");

                return self::FAILURE;
            }

            $q->where('d.Entitas_Pemilik_Id', $idEntitas);
        }

        $rows = $q->orderBy('d.Id_SOP_Dokumen')
            ->get(['d.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi']);

        if ($rows->isEmpty()) {
            $this->info('Tak ada dokumen yang cocok.');

            return self::SUCCESS;
        }

        $this->line($rows->count().' dokumen diperiksa'.($kering ? ' (dry-run, tak ada yang ditulis)' : '').'.');
        $this->line('');

        $berubah = 0;
        $takBisa = 0;
        $baris = [];

        foreach ($rows as $d) {
            $id = (int) $d->Id_SOP_Dokumen;
            $lama = $d->Nomor_SOP_Resmi;

            /* Dirakit lewat jalur yang sama dengan penulis sesungguhnya, jadi
               yang diperlihatkan dry-run adalah yang benar-benar akan ditulis —
               bukan tiruan rumusnya. */
            $baru = $dokumen->nomorLengkap(
                (array) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)
                    ->first(['Jenis_Dokumen_Id', 'Entitas_Pemilik_Id', 'Departemen_Id',
                        'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen'])
                + ['Revisi' => $dokumen->revisiBerlaku($id)]
            );

            if ($baru === null) {
                /* Identitasnya belum lengkap. TIDAK dikosongkan — 16 dokumen
                   lama menyimpan penomoran ketik tangan yang masih beredar. */
                $takBisa++;

                continue;
            }

            if ($baru === $lama) {
                continue;
            }

            $berubah++;
            $baris[] = [$id, mb_substr((string) $d->Judul, 0, 34), (string) $lama, $baru];

            if (! $kering) {
                /* Updated_By = 0 menandai "ditulis sistem, bukan seseorang".
                   Kolom itu tak ber-FK, dan memakai id admin mana pun akan
                   mengaku-ngaku bahwa ada orang yang menyunting dokumen ini. */
                $dokumen->segarkanNomorResmi($id, 0);
            }
        }

        if ($baris !== []) {
            $this->table(['Id', 'Judul', 'Nomor lama', 'Nomor baru'], $baris);
        }

        $this->line('');
        $this->info($berubah.' dokumen '.($kering ? 'AKAN berubah' : 'diperbarui').'.');

        if ($takBisa > 0) {
            /* Dilaporkan, tak ditelan: dokumen yang nomornya tak bisa dirakit
               adalah tepat dokumen yang nomornya paling mungkin salah. */
            $this->warn($takBisa.' dokumen dilewati karena identitasnya belum lengkap — nomor lamanya dibiarkan.');
        }

        return self::SUCCESS;
    }
}
