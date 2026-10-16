<?php

namespace App\Services\VISITOR\Guest\Visit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Foto kendaraan tamu B2B (opsional, satu berkas).
 *
 * Diunggah SESUDAH baris tamu ter-commit dan di luar transaksi: unggahan
 * jaringan yang menahan transaksi registrasi akan menahan lock nomor
 * transaksi dan antrean approval. Kegagalan unggah tidak membatalkan
 * pengajuan — pemanggil melaporkannya ke pengguna.
 *
 * Dibaca verifikator tamu B2B dan meja permit sebagai rujukan inspeksi;
 * tidak disalin ke N_EMI_VISITOR_Transaksi_Permit_Dokumen.
 */
class FotoKendaraanTamuService
{
    public const DISK = 'gcs';

    public function simpan(string $noTransaksi, UploadedFile $file): string
    {
        $ekstensi = strtolower($file->extension() ?: 'jpg'); // dari ISI berkas (MIME), bukan nama kiriman klien
        $ekstensi = preg_replace('/[^a-z0-9]/', '', $ekstensi) ?: 'jpg';
        // Nama unik per unggahan: menimpa path yang sama membuat tautan
        // bertanda tangan lama yang masih terbuka menampilkan foto baru.
        $path = "visitor/tamu/{$noTransaksi}/foto-kendaraan-".Str::uuid()->toString().".{$ekstensi}";

        $sumber = $file->getRealPath() ?: $file->getPathname();
        $stream = is_string($sumber) && is_file($sumber) ? fopen($sumber, 'rb') : false;

        if ($stream === false) {
            throw new \RuntimeException('Berkas foto kendaraan tidak dapat dibaca.');
        }

        try {
            $tersimpan = Storage::disk(self::DISK)->put($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $tersimpan) {
            throw new \RuntimeException('Foto kendaraan gagal disimpan.');
        }

        $lama = DB::table(RegistrasiTamuService::T_DETAIL)
            ->where('No_Transaksi', $noTransaksi)
            ->value('Path_Foto_Kendaraan');

        $diubah = DB::table(RegistrasiTamuService::T_DETAIL)
            ->where('No_Transaksi', $noTransaksi)
            ->update(['Path_Foto_Kendaraan' => $path]);

        if ($diubah === 0) {
            $this->hapusDiam($path);

            throw new \DomainException('Transaksi tamu tidak ditemukan.');
        }

        if ($lama && $lama !== $path) {
            $this->hapusDiam($lama);
        }

        return $path;
    }

    /** Tautan bertanda tangan 60 menit; null bila tidak ada foto atau GCS menolak. */
    public function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return Storage::disk(self::DISK)->temporaryUrl($path, now()->addMinutes(60));
        } catch (\Throwable $e) {
            Log::warning('VISITOR_FOTO_KENDARAAN_URL_FAILED', ['path' => $path, 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function hapusDiam(string $path): void
    {
        try {
            Storage::disk(self::DISK)->delete($path);
        } catch (\Throwable $e) {
            Log::warning('VISITOR_FOTO_KENDARAAN_CLEANUP_FAILED', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }
}
