<?php

namespace App\Http\Controllers\VISITOR\Workflow\Reporting\Export;

use App\Http\Controllers\Controller;
use App\Services\VISITOR\Workflow\Reporting\Export\TautanBerkasVisitor;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Membuka berkas VMS dari tautan export — tanpa login, hanya dengan tanda
 * tangan yang sah dan belum kedaluwarsa (TautanBerkasVisitor).
 *
 * Berkasnya DIALIRKAN dari GCS lewat server ini, bukan dialihkan: pengalihan
 * ke URL bertanda tangan GCS akan memperlihatkan nama bucket dan path objek
 * di bilah alamat, persis yang ingin disembunyikan.
 */
class TautanBerkasController extends Controller
{
    private const DISK = 'gcs';

    /** Kebijakan yang sama untuk setiap balasan: jangan disimpan, jangan diindeks. */
    private const HEADER_PRIVASI = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function __construct(private TautanBerkasVisitor $tautan) {}

    public function tampil(Request $request, string $jenis, string $id): Response|StreamedResponse
    {
        $keadaan = $this->tautan->periksa($request);

        if ($keadaan === 'kedaluwarsa') {
            return $this->pesan(410, 'Tautan sudah kedaluwarsa', 'Tautan berkas dari export hanya berlaku '
                .TautanBerkasVisitor::MASA_BERLAKU_JAM.' jam. Silakan export ulang untuk mendapatkan tautan baru.');
        }

        if ($keadaan !== 'sah') {
            return $this->pesan(403, 'Tautan tidak sah', 'Tautan berkas ini tidak dikenali. Pastikan tautannya disalin utuh dari file export.');
        }

        $berkas = ctype_digit($id) ? $this->tautan->berkas($jenis, (int) $id) : null;

        if (! $berkas) {
            return $this->pesan(404, 'Berkas tidak ditemukan', 'Berkas ini sudah tidak tersedia.');
        }

        try {
            $disk = Storage::disk(self::DISK);
            $aliran = $disk->readStream($berkas['path']);
            if (! $aliran) {
                return $this->pesan(404, 'Berkas tidak ditemukan', 'Berkas ini sudah tidak tersedia.');
            }

            $tipe = $this->tipe($disk, $berkas['path']);
        } catch (\Throwable $e) {
            Log::warning('VISITOR_TAUTAN_BERKAS_GAGAL', ['jenis' => $jenis, 'id' => $id, 'error' => $e->getMessage()]);

            return $this->pesan(404, 'Berkas tidak ditemukan', 'Berkas ini sudah tidak tersedia.');
        }

        return new StreamedResponse(function () use ($aliran) {
            fpassthru($aliran);
            if (is_resource($aliran)) {
                fclose($aliran);
            }
        }, 200, self::HEADER_PRIVASI + [
            'Content-Type' => $tipe,
            'Content-Disposition' => 'inline; filename="'.addslashes($berkas['nama']).'"',
        ]);
    }

    private function tipe($disk, string $path): string
    {
        try {
            $tipe = $disk->mimeType($path);
            if ($tipe) {
                return $tipe;
            }
        } catch (\Throwable) {
            // jatuh ke tebakan dari ekstensi
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    private function pesan(int $status, string $judul, string $isi): Response
    {
        $html = '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e($judul).'</title></head>'
            .'<body style="font-family:system-ui,sans-serif;max-width:32rem;margin:15vh auto;padding:0 1rem;color:#0f172a">'
            .'<h1 style="font-size:1.25rem">'.e($judul).'</h1><p style="color:#475569">'.e($isi).'</p></body></html>';

        return response($html, $status, self::HEADER_PRIVASI + ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
