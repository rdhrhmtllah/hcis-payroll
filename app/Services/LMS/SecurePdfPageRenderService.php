<?php

namespace App\Services\LMS;

use App\Services\LMS\GCS\GcsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SecurePdfPageRenderService
{
    private const RENDER_VERSION = 'v4';

    private const MANIFEST_CACHE_TTL_SECONDS = 86400;

    private const PAGE_CACHE_TTL_SECONDS = 7200;

    private const PAGE_EXISTS_CACHE_TTL_SECONDS = 86400;

    private const PAGE_COMPRESSION_QUALITY = 84;

    private const PAGE_RENDER_DENSITY = 144;

    // Watermark: teks 3 baris diagonal, tiled rapi
    private const WATERMARK_FONT_SIZE = 28;

    private const WATERMARK_OPACITY = 0.22;

    private const WATERMARK_ANGLE = -30;

    public function __construct(private readonly GcsService $gcsService = new GcsService) {}

    /**
     * Ambil manifest (jumlah halaman + dimensi). Di-cache 24 jam.
     */
    public function getManifest(int $berkasId, string $filePath): array
    {
        $cacheKey = $this->manifestCacheKey($berkasId, $filePath);

        return Cache::store('database')->remember($cacheKey, self::MANIFEST_CACHE_TTL_SECONDS, function () use ($filePath) {
            $localPdf = $this->downloadSourcePdfToTemp($filePath);
            $imagick = null;

            try {
                $imagick = new \Imagick;
                $imagick->pingImage($localPdf);

                $totalPages = $imagick->getNumberImages();
                $pages = [];

                foreach ($imagick as $index => $frame) {
                    $pages[] = [
                        'page' => $index + 1,
                        'width' => (int) $frame->getImageWidth(),
                        'height' => (int) $frame->getImageHeight(),
                        'aspect_ratio' => $frame->getImageHeight() > 0
                            ? round($frame->getImageWidth() / $frame->getImageHeight(), 6)
                            : 0.75,
                    ];
                }

                return [
                    'total_pages' => $totalPages,
                    'pages' => $pages,
                    'version' => self::RENDER_VERSION,
                ];
            } finally {
                $this->destroyImagick($imagick);
                $this->deleteTempFile($localPdf);
            }
        });
    }

    /**
     * Pastikan halaman sudah dirender ke storage. Return metadata rendered page.
     * Render di-cache di disk storage (GCS/local) — tidak download GCS berulang kali.
     */
    public function ensurePageRendered(int $berkasId, string $filePath, int $pageNumber, ?array $manifest = null): array
    {
        $manifest = $manifest ?: $this->getManifest($berkasId, $filePath);
        $pageMeta = collect($manifest['pages'] ?? [])->firstWhere('page', $pageNumber);

        if (! $pageMeta) {
            throw new RuntimeException('Halaman tidak ditemukan.');
        }

        $storagePath = $this->pageStoragePath($berkasId, $pageNumber);
        $disk = Storage::disk($this->storageDiskName());

        // Cache hasil exists() agar tidak hit GCS tiap request halaman.
        // Hanya cache kondisi "sudah ada" (true) — kalau belum ada, biarkan
        // jalur render di bawah berjalan dan exists() akan true setelahnya.
        $existsCacheKey = 'lms_pdf_page_exists_'.hash('sha256', $this->storageDiskName().'|'.$storagePath);
        $alreadyRendered = (bool) Cache::store('database')->get($existsCacheKey, false);

        if ($alreadyRendered || $disk->exists($storagePath)) {
            Cache::store('database')->put($existsCacheKey, true, self::PAGE_EXISTS_CACHE_TTL_SECONDS);

            return [
                'disk' => $this->storageDiskName(),
                'path' => $storagePath,
                'content_type' => 'image/jpeg',
                'width' => (int) $pageMeta['width'],
                'height' => (int) $pageMeta['height'],
            ];
        }

        // Fallback on-demand: halaman belum ter-render (mis. Cloud Task belum jalan).
        // Idealnya halaman sudah di-pre-render oleh renderAllPages() via Cloud Task.
        $localPdf = $this->downloadSourcePdfToTemp($filePath);

        try {
            return $this->renderPageFromLocalPdf($localPdf, $berkasId, $pageNumber, $pageMeta);
        } finally {
            $this->deleteTempFile($localPdf);
        }
    }

    /**
     * Render SEMUA halaman dari source PDF ke storage dalam satu jalur.
     * Source PDF di-download HANYA SEKALI — dipakai oleh Cloud Task (pre-render).
     *
     * @return int Jumlah halaman yang dirender.
     */
    public function renderAllPages(int $berkasId, string $filePath): int
    {
        $manifest = $this->getManifest($berkasId, $filePath);
        $totalPages = (int) ($manifest['total_pages'] ?? 0);

        if ($totalPages < 1) {
            throw new RuntimeException('Dokumen tidak memiliki halaman yang dapat dirender.');
        }

        $disk = Storage::disk($this->storageDiskName());
        $localPdf = $this->downloadSourcePdfToTemp($filePath);

        try {
            foreach (($manifest['pages'] ?? []) as $pageMeta) {
                $pageNumber = (int) ($pageMeta['page'] ?? 0);
                if ($pageNumber < 1) {
                    continue;
                }

                $storagePath = $this->pageStoragePath($berkasId, $pageNumber);

                // Lewati halaman yang sudah ada (mis. re-run task setelah gagal sebagian).
                if ($disk->exists($storagePath)) {
                    $this->markPageRenderedInCache($storagePath);

                    continue;
                }

                $this->renderPageFromLocalPdf($localPdf, $berkasId, $pageNumber, $pageMeta);
            }

            return $totalPages;
        } finally {
            $this->deleteTempFile($localPdf);
        }
    }

    /**
     * Render satu halaman dari file PDF lokal (sudah di-download) ke storage.
     * Pakai pdftoppm (poppler) bila tersedia — JAUH lebih cepat & ringan dari
     * Imagick+Ghostscript. Fallback ke Imagick bila poppler tidak ada.
     * Tidak men-download source PDF — caller yang bertanggung jawab atas itu.
     */
    private function renderPageFromLocalPdf(string $localPdf, int $berkasId, int $pageNumber, array $pageMeta): array
    {
        $storagePath = $this->pageStoragePath($berkasId, $pageNumber);
        $disk = Storage::disk($this->storageDiskName());
        $localImage = $this->makeTempFilePath('lms_pdf_page_', '.jpg');

        try {
            [$renderedWidth, $renderedHeight] = $this->popplerAvailable()
                ? $this->renderPageWithPoppler($localPdf, $pageNumber, $localImage)
                : $this->renderPageWithImagick($localPdf, $pageNumber, $localImage);

            $stream = fopen($localImage, 'rb');
            if ($stream === false) {
                throw new RuntimeException('Gagal membaca hasil render halaman.');
            }

            try {
                $disk->put($storagePath, $stream, [
                    'visibility' => 'private',
                    'ContentType' => 'image/jpeg',
                    'CacheControl' => 'private, no-store, no-cache, must-revalidate',
                ]);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $this->markPageRenderedInCache($storagePath);

            return [
                'disk' => $this->storageDiskName(),
                'path' => $storagePath,
                'content_type' => 'image/jpeg',
                'width' => (int) $renderedWidth,
                'height' => (int) $renderedHeight,
            ];
        } finally {
            $this->deleteTempFile($localImage);
        }
    }

    /**
     * Render 1 halaman pakai pdftoppm (poppler). Sangat cepat.
     *
     * @return array{0:int,1:int} [width, height]
     */
    private function renderPageWithPoppler(string $localPdf, int $pageNumber, string $localImage): array
    {
        // pdftoppm menambahkan suffix sendiri; pakai prefix tanpa ekstensi.
        $prefix = $localImage.'_pp';

        $cmd = sprintf(
            'pdftoppm -jpeg -r %d -f %d -l %d -jpegopt quality=%d -singlefile %s %s 2>&1',
            self::PAGE_RENDER_DENSITY,
            $pageNumber,
            $pageNumber,
            self::PAGE_COMPRESSION_QUALITY,
            escapeshellarg($localPdf),
            escapeshellarg($prefix),
        );

        exec($cmd, $output, $exitCode);

        $produced = $prefix.'.jpg';
        if ($exitCode !== 0 || ! is_file($produced)) {
            // Fallback ke Imagick bila poppler gagal pada PDF ini.
            return $this->renderPageWithImagick($localPdf, $pageNumber, $localImage);
        }

        @rename($produced, $localImage);

        $size = @getimagesize($localImage);

        return [(int) ($size[0] ?? 0), (int) ($size[1] ?? 0)];
    }

    /**
     * Render 1 halaman pakai Imagick + Ghostscript (fallback).
     *
     * @return array{0:int,1:int} [width, height]
     */
    private function renderPageWithImagick(string $localPdf, int $pageNumber, string $localImage): array
    {
        $imagick = null;
        try {
            $imagick = new \Imagick;
            $imagick->setResolution(self::PAGE_RENDER_DENSITY, self::PAGE_RENDER_DENSITY);
            $imagick->readImage($localPdf.'['.($pageNumber - 1).']');
            $imagick->setIteratorIndex(0);
            $imagick->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $imagick->setImageBackgroundColor('white');
            $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(self::PAGE_COMPRESSION_QUALITY);
            $imagick->stripImage();

            $w = $imagick->getImageWidth();
            $h = $imagick->getImageHeight();
            $imagick->writeImage($localImage);

            return [(int) $w, (int) $h];
        } finally {
            $this->destroyImagick($imagick);
        }
    }

    /**
     * Cek ketersediaan pdftoppm (di-cache per proses).
     */
    private function popplerAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }

        if (! function_exists('exec')) {
            return $available = false;
        }

        $which = stripos(PHP_OS, 'WIN') === 0 ? 'where pdftoppm' : 'command -v pdftoppm';
        @exec($which.' 2>&1', $out, $code);

        return $available = ($code === 0 && ! empty($out));
    }

    /**
     * Tandai di DB cache bahwa halaman sudah ter-render, agar request berikutnya
     * tidak perlu hit GCS exists().
     */
    private function markPageRenderedInCache(string $storagePath): void
    {
        $existsCacheKey = 'lms_pdf_page_exists_'.hash('sha256', $this->storageDiskName().'|'.$storagePath);
        Cache::store('database')->put($existsCacheKey, true, self::PAGE_EXISTS_CACHE_TTL_SECONDS);
    }

    /**
     * Baca raw bytes halaman tanpa watermark (untuk admin mode / Flag_Download=Y).
     * Base JPEG di-cache ke LOCAL DISK (bukan Laravel cache) — andal & cepat untuk
     * blob biner, dan konsisten antar request web.
     */
    public function readRenderedPageBlob(array $rendered): string
    {
        $localCachePath = $this->localBlobCachePath($rendered['disk'], $rendered['path']);

        if (is_file($localCachePath)) {
            $cached = @file_get_contents($localCachePath);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $disk = Storage::disk($rendered['disk']);
        $stream = $disk->readStream($rendered['path']);
        if (! is_resource($stream)) {
            throw new RuntimeException('Gagal membaca cache halaman dokumen.');
        }

        $blob = stream_get_contents($stream);
        fclose($stream);

        if ($blob === false || $blob === '') {
            throw new RuntimeException('Blob halaman kosong.');
        }

        $this->writeLocalBlobCache($localCachePath, $blob);

        return $blob;
    }

    /**
     * Path cache lokal untuk base JPEG. Disimpan di storage/app/lms-page-cache/.
     */
    private function localBlobCachePath(string $disk, string $remotePath): string
    {
        $dir = storage_path('app/lms-page-cache');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir.DIRECTORY_SEPARATOR.hash('sha256', $disk.'|'.$remotePath).'.jpg';
    }

    /**
     * Tulis cache lokal secara atomik (tmp + rename) agar tidak ada file korup
     * saat dibaca request lain bersamaan.
     *
     * Di Cloud Run direktori ini berada di tmpfs (RAM). Untuk mencegah memory
     * menumpuk hingga OOM, terapkan eviction sederhana berbasis jumlah file:
     * bila sudah melebihi batas, hapus file paling lama (LRU kasar via mtime).
     */
    private function writeLocalBlobCache(string $path, string $blob): void
    {
        $this->evictLocalBlobCacheIfNeeded(dirname($path));

        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (@file_put_contents($tmp, $blob) !== false) {
            @rename($tmp, $path);
        } else {
            @unlink($tmp);
        }
    }

    /**
     * Eviction kasar: jaga jumlah file cache base <= config pdf_cache_max_files.
     * Bila melebihi, hapus 20% file tertua. Cegah tmpfs penuh di Cloud Run.
     */
    private function evictLocalBlobCacheIfNeeded(string $dir): void
    {
        $maxFiles = (int) config('services.lms.pdf_cache_max_files', 200);
        if ($maxFiles < 1) {
            $maxFiles = 200;
        }

        $files = @glob($dir.DIRECTORY_SEPARATOR.'*.jpg') ?: [];
        if (count($files) < $maxFiles) {
            return;
        }

        // Urutkan dari paling lama diakses.
        usort($files, static fn ($a, $b) => (@filemtime($a) ?: 0) <=> (@filemtime($b) ?: 0));

        $removeCount = (int) ceil(count($files) * 0.2);
        foreach (array_slice($files, 0, $removeCount) as $old) {
            @unlink($old);
        }
    }

    /**
     * Buat blob halaman dengan watermark personal di-burn (server-side Imagick).
     * Cache per {berkasId}:{page}:{userId} selama 2 jam — tidak download GCS berulang kali.
     */
    public function createWatermarkedPageBlob(array $rendered, array $watermark): string
    {
        // Ambil base image — pakai cache layer local agar tidak ke GCS berulang.
        $baseBlob = $this->readRenderedPageBlob($rendered);

        // Burn watermark pakai GD (jauh lebih ringan dari Imagick untuk overlay teks).
        return $this->applyWatermarkGd($baseBlob, $watermark);
    }

    /**
     * Burn watermark tiled diagonal ke base JPEG pakai GD (hemat RAM/CPU).
     * Tiap titik grid: teks 3 baris (property / user / ip + date), rotasi -30°.
     */
    private function applyWatermarkGd(string $baseBlob, array $watermark): string
    {
        $line1 = (string) data_get($watermark, 'property', '');
        $line2 = (string) data_get($watermark, 'user', '');
        $line3 = trim((string) data_get($watermark, 'ip', '').'  '.(string) data_get($watermark, 'date', ''));
        $lines = array_values(array_filter([$line1, $line2, $line3], fn ($s) => $s !== ''));

        $base = @imagecreatefromstring($baseBlob);
        if ($base === false) {
            // Base tidak bisa dibaca GD — kembalikan apa adanya agar tidak gagal total.
            return $baseBlob;
        }

        // Tidak ada teks watermark → kembalikan base re-encode.
        if ($lines === []) {
            return $this->gdToJpegBlob($base);
        }

        $imgWidth = imagesx($base);
        $imgHeight = imagesy($base);
        $fontPath = $this->resolveFontPath();

        // Tanpa font TTF, GD tidak bisa render teks rotasi dengan rapi → skip watermark
        // daripada menghasilkan watermark jelek. (Di Cloud Run font tersedia.)
        if ($fontPath === '' || ! function_exists('imagettftext')) {
            return $this->gdToJpegBlob($base);
        }

        $fontSize = (int) max(14, min(self::WATERMARK_FONT_SIZE, (int) ($imgWidth / 28)));
        $lineGap = (int) ($fontSize * 1.7);
        $blockH = $lineGap * count($lines);

        $stepX = (int) max(220, (int) ($imgWidth / 2.5));
        $stepY = (int) max(120, (int) ($imgHeight / 3.5));

        // Buat layer overlay transparan, gambar teks tegak, lalu rotasi sekali.
        $overlay = imagecreatetruecolor($imgWidth, $imgHeight);
        imagealphablending($overlay, false); // tulis alpha apa adanya saat menggambar
        imagesavealpha($overlay, true);
        $transparent = imagecolorallocatealpha($overlay, 0, 0, 0, 127);
        imagefill($overlay, 0, 0, $transparent);
        imagealphablending($overlay, true);  // aktifkan blending saat menulis teks

        // Warna teks abu dengan alpha (GD alpha: 0 opaque..127 transparan).
        $alpha = (int) round(127 * (1 - self::WATERMARK_OPACITY));
        $textColor = imagecolorallocatealpha($overlay, 70, 70, 70, $alpha);

        // Grid mulai dari -stepX/-stepY agar tepi tetap tertutup setelah rotasi.
        for ($gx = -$stepX; $gx < $imgWidth + $stepX; $gx += $stepX) {
            for ($gy = -$stepY; $gy < $imgHeight + $stepY; $gy += $stepY) {
                $blockTop = $gy - $blockH / 2;
                foreach ($lines as $li => $line) {
                    $yPos = (int) ($blockTop + ($li + 1) * $lineGap);
                    // Center teks: hitung lebar bbox lalu geser.
                    $bbox = imagettfbbox($fontSize, 0, $fontPath, $line);
                    $textW = abs($bbox[2] - $bbox[0]);
                    $xPos = (int) ($gx - $textW / 2);
                    imagettftext($overlay, $fontSize, 0, $xPos, $yPos, $textColor, $fontPath, $line);
                }
            }
        }

        // Rotasi overlay (background transparan) lalu tempel di tengah base.
        $rotated = imagerotate($overlay, self::WATERMARK_ANGLE, $transparent);
        imagesavealpha($rotated, true);
        imagedestroy($overlay);

        $rotW = imagesx($rotated);
        $rotH = imagesy($rotated);
        $dstX = (int) (($imgWidth - $rotW) / 2);
        $dstY = (int) (($imgHeight - $rotH) / 2);

        // Blend overlay (dengan alpha) ke base.
        imagealphablending($base, true);
        imagecopy($base, $rotated, $dstX, $dstY, 0, 0, $rotW, $rotH);
        imagedestroy($rotated);

        return $this->gdToJpegBlob($base);
    }

    /**
     * Encode resource GD ke JPEG blob lalu bebaskan resource.
     */
    private function gdToJpegBlob(\GdImage $img): string
    {
        ob_start();
        imagejpeg($img, null, self::PAGE_COMPRESSION_QUALITY);
        $blob = (string) ob_get_clean();
        imagedestroy($img);

        return $blob;
    }

    /**
     * Cari font TEKS yang valid untuk watermark. JANGAN pakai font icon
     * (Iconly/FontAwesome) — glyph-nya simbol, bukan huruf, jadi watermark
     * tak terbaca. Urutan: font proyek → DejaVu (Cloud Run) → Arial (Windows lokal).
     */
    private function resolveFontPath(): string
    {
        static $resolved = null;
        if ($resolved !== null) {
            return $resolved;
        }

        $candidates = [
            // Font teks yang sengaja ditaruh di proyek (kalau ada).
            public_path('fonts/DejaVuSans-Bold.ttf'),
            public_path('fonts/Arial-Bold.ttf'),
            public_path('fonts/Inter-Bold.ttf'),
            // DejaVu dari paket fonts-dejavu-core (Cloud Run / Debian).
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            // Arial Windows (dev lokal).
            'C:\\Windows\\Fonts\\arialbd.ttf',
            'C:\\Windows\\Fonts\\arial.ttf',
        ];

        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return $resolved = $path;
            }
        }

        return $resolved = '';
    }

    private function downloadSourcePdfToTemp(string $filePath): string
    {
        $metadata = $this->gcsService->getFileMetadata($filePath);
        if (! ($metadata['exists'] ?? false)) {
            throw new RuntimeException('File PDF sumber tidak ditemukan.');
        }

        $disk = Storage::disk($metadata['disk']);
        $stream = $disk->readStream($filePath);
        if (! is_resource($stream)) {
            throw new RuntimeException('Gagal membaca file PDF sumber.');
        }

        $tempPath = $this->makeTempFilePath('lms_pdf_src_', '.pdf');

        $target = fopen($tempPath, 'wb');
        if ($target === false) {
            fclose($stream);
            @unlink($tempPath);
            throw new RuntimeException('Gagal membuka file sementara PDF.');
        }

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($stream);
            fclose($target);
        }

        return $tempPath;
    }

    private function makeTempFilePath(string $prefix, string $suffix): string
    {
        $base = tempnam(sys_get_temp_dir(), $prefix);
        if ($base === false) {
            throw new RuntimeException('Gagal membuat file sementara.');
        }
        $path = $base.$suffix;
        // tempnam membuat file tanpa suffix — hapus dulu supaya suffix bisa dipakai
        @unlink($base);

        return $path;
    }

    private function storageDiskName(): string
    {
        return config('services.lms.gcs.disk') ?: (config('filesystems.disks.gcs') ? 'gcs' : 'local');
    }

    private function pageStoragePath(int $berkasId, int $pageNumber): string
    {
        return 'lms/secure-pages/'.$berkasId.'/'.self::RENDER_VERSION.'/page-'.$pageNumber.'.jpg';
    }

    private function manifestCacheKey(int $berkasId, string $filePath): string
    {
        return 'lms_secure_pdf_manifest_'.$berkasId.'_'.hash('sha256', $filePath.'|'.self::RENDER_VERSION);
    }

    private function deleteTempFile(?string $path): void
    {
        if (is_string($path) && $path !== '' && file_exists($path)) {
            @unlink($path);
        }
    }

    /**
     * Bebaskan resource Imagick dengan aman. Selalu dipanggil di blok finally
     * agar memory tidak bocor di FPM/Cloud Run worker walau terjadi exception.
     */
    private function destroyImagick(?\Imagick $imagick): void
    {
        if ($imagick instanceof \Imagick) {
            try {
                $imagick->clear();
                $imagick->destroy();
            } catch (\Throwable) {
                // resource mungkin sudah dibebaskan — abaikan.
            }
        }
    }
}
