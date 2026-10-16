<?php

namespace App\Console\Commands\LMS\Content\Verification;

use App\Services\LMS\Content\Verification\KontenVerifierService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Memeriksa ulang berkas Library Konten yang belum pernah melewati verifikasi.
 *
 * Dua sasaran:
 *   LAMA     — diunggah sebelum verifikasi ada. Objeknya SUDAH di bucket
 *              penyajian, jadi tak ada yang perlu dipromosikan.
 *   MENUNGGU — tersangkut di karantina karena dispatch Cloud Task gagal.
 *              Yang lolos dipromosikan seperti yang dilakukan webhook.
 *
 * Objek yang DITOLAK sengaja TIDAK dihapus secara default. Berkas LAMA adalah
 * konten produksi yang sudah dipakai; menghapusnya berdasarkan pemeriksa yang
 * baru diperkenalkan berarti satu positif palsu memusnahkan dokumen SOP asli.
 * Menandainya DITOLAK sudah cukup membuatnya tak terpakai di Builder, dan admin
 * bisa memutuskan sendiri. Pakai --hapus-ditolak bila memang diinginkan.
 */
class KontenVerifikasiUlang extends Command
{
    protected $signature = 'konten:verifikasi-ulang
        {--scope= : LMS atau SOP; kosong berarti keduanya}
        {--status=LAMA : status yang diproses — LAMA atau MENUNGGU}
        {--limit=200 : maksimum berkas per jalan}
        {--dry-run : hanya laporkan, jangan ubah apa pun}
        {--hapus-ditolak : hapus juga objek yang ditolak}';

    protected $description = 'Verifikasi ulang berkas Library Konten (LAMA / MENUNGGU)';

    public function handle(KontenVerifierService $verifier): int
    {
        $status = strtoupper((string) $this->option('status'));
        if (! in_array($status, ['LAMA', 'MENUNGGU'], true)) {
            $this->error('--status hanya menerima LAMA atau MENUNGGU.');

            return self::FAILURE;
        }

        $kering = (bool) $this->option('dry-run');
        $hapus = (bool) $this->option('hapus-ditolak');
        $disk = $status === 'LAMA'
            ? KontenVerifierService::DISK_SAJI
            : KontenVerifierService::DISK_KARANTINA;

        $q = DB::table('N_LMS_Master_Konten_Berkas as b')
            ->join('N_LMS_Master_Konten as m', 'm.Id_Master_Konten', '=', 'b.Id_Master_Konten')
            ->where('b.Status_Verifikasi', $status)
            ->whereNotNull('b.File_Path')
            ->orderBy('b.Id_Master_Konten_Berkas')
            ->limit((int) $this->option('limit'))
            ->select([
                'b.Id_Master_Konten_Berkas as id',
                'b.File_Path as path',
                'b.Ext as ext',
                'b.Type_File as mime',
                'm.Judul_Konten as judul',
                'm.Scope as scope',
            ]);

        if ($scope = $this->option('scope')) {
            $q->where('m.Scope', strtoupper($scope));
        }

        $baris = $q->get();

        if ($baris->isEmpty()) {
            $this->info("Tidak ada berkas berstatus {$status}.");

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Memeriksa %d berkas berstatus %s dari disk %s%s',
            $baris->count(), $status, $disk, $kering ? ' (dry-run)' : '',
        ));
        $this->newLine();

        $siap = 0;
        $tolak = 0;
        $lewat = 0;

        foreach ($baris as $b) {
            // Berkas lama boleh jadi tak punya Ext (kolomnya baru). Turunkan dari
            // nama objeknya — bukan dari Type_File, yang untuk baris lama justru
            // berasal dari klaim browser dan tak bisa dipercaya.
            $ext = strtolower((string) ($b->ext ?: pathinfo($b->path, PATHINFO_EXTENSION)));

            if ($ext === '') {
                $this->line("  <fg=yellow>?</>  #{$b->id} {$b->judul} — ekstensi tak diketahui, dilewati");
                $lewat++;

                continue;
            }

            $hasil = $verifier->verifikasiDalam($b->path, $ext, $disk);

            // Objek tak terjangkau bukan berkas yang ditolak. Statusnya
            // DIBIARKAN — supaya satu gangguan penyimpanan tidak mencap seluruh
            // konten produksi sebagai ditolak dalam satu kali jalan.
            if (! $hasil['ok'] && ! $hasil['terjangkau']) {
                $this->line("  <fg=yellow>?</>  #{$b->id} {$b->judul} — {$hasil['alasan']} (status dibiarkan)");
                $lewat++;

                continue;
            }

            if ($hasil['ok']) {
                $siap++;
                $this->line("  <fg=green>OK</> #{$b->id} {$b->judul} (.{$ext})");
                if (! $kering) {
                    // MENUNGGU masih di karantina — objeknya harus pindah dulu.
                    if ($status === 'MENUNGGU' && ! $verifier->promosikan($b->path)) {
                        $this->line('      <fg=red>promosi gagal, status dibiarkan MENUNGGU</>');
                        $siap--;
                        $lewat++;

                        continue;
                    }
                    $this->setStatus($b->id, 'SIAP', null, $ext);
                }
            } else {
                $tolak++;
                $this->line("  <fg=red>X</>  #{$b->id} {$b->judul} (.{$ext}) — {$hasil['alasan']}");
                if (! $kering) {
                    $this->setStatus($b->id, 'DITOLAK', $hasil['alasan'], $ext);
                    if ($hapus) {
                        $verifier->hapusObjek($b->path, $disk);
                    }
                }
            }
        }

        $this->newLine();
        $this->info(sprintf('Selesai — SIAP: %d · DITOLAK: %d · dilewati: %d', $siap, $tolak, $lewat));

        if ($tolak > 0 && ! $hapus && ! $kering) {
            $this->warn('Objek yang ditolak TIDAK dihapus. Tinjau dulu; jalankan dengan --hapus-ditolak bila memang mau dibuang.');
        }

        return self::SUCCESS;
    }

    private function setStatus(int $id, string $status, ?string $alasan, string $ext): void
    {
        DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $id)
            ->update([
                'Status_Verifikasi' => $status,
                'Alasan_Tolak' => $alasan ? mb_substr($alasan, 0, 500) : null,
                'Ext' => $ext,
                'Verified_At' => now(),
                'Updated_At' => now(),
            ]);
    }
}
