<?php

namespace App\Services\SYSTEM\Application\Panduan;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan berkas PDF panduan dan jejak pelaku — dipakai bersama
 * PanduanService (panduan) dan PanduanVersiService (versi berkasnya).
 */
trait BerkasPanduan
{
    private function unggah(UploadedFile $berkas): string
    {
        // Nama unik per unggahan: menimpa path yang sama membuat penampil yang
        // masih terbuka menampilkan campuran versi lama dan baru.
        $path = 'panduan/'.now()->format('Y/m').'/'.Str::uuid()->toString().'.pdf';

        $sumber = $berkas->getRealPath() ?: $berkas->getPathname();
        $stream = fopen($sumber, 'rb');
        if ($stream === false) {
            throw new \RuntimeException('Berkas panduan tidak dapat dibaca.');
        }

        try {
            if (! Storage::disk(PanduanService::DISK)->put($path, $stream)) {
                throw new \RuntimeException('Berkas panduan gagal disimpan.');
            }
        } finally {
            is_resource($stream) && fclose($stream);
        }

        return $path;
    }

    private function hapusBerkasDiam(string $path): void
    {
        try {
            Storage::disk(PanduanService::DISK)->delete($path);
        } catch (\Throwable) {
            // Berkas yatim lebih baik daripada menggagalkan permintaan.
        }
    }

    private function teksAtauNull(?string $nilai): ?string
    {
        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : $nilai;
    }

    private function pelaku(): string
    {
        $user = Auth::user();

        return (string) ($user?->Kode_Karyawan ?? $user?->karyawan?->Kode_Karyawan ?? Auth::id() ?? 'SISTEM');
    }
}
