<?php

namespace App\Services\LMS\GCS;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GcsService — Local Disk Fallback
 *
 * Saat ini menggunakan local storage (storage/app/public/lms/)
 * Bisa diganti ke GCS disk saat konfigurasi sudah tersedia.
 */
class GcsService
{
    protected string $disk;

    protected string $basePath;

    public function __construct()
    {
        $configuredDisk = config('services.lms.gcs.disk');

        // Prioritas: services.lms.gcs.disk -> gcs (jika terdefinisi) -> public
        if (is_string($configuredDisk) && trim($configuredDisk) !== '') {
            $this->disk = trim($configuredDisk);
        } elseif (config('filesystems.disks.gcs')) {
            $this->disk = 'gcs';
        } else {
            $this->disk = 'public';
        }

        $this->basePath = 'lms/berkas';
    }

    private function gcsLogger()
    {
        try {
            return Log::channel('LmsGcsService');
        } catch (\Throwable $e) {
            $fallback = Log::channel(config('logging.default', 'stack'));
            $fallback->error('LmsGcsService channel fallback', [
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * Upload file ke storage
     *
     * @param  string|null  $subFolder  sub-folder opsional (e.g. kategori ID)
     * @return array [media_key, type_file, file_path]
     */
    public function uploadFile(
        UploadedFile $file,
        ?string $subFolder = null,
    ): array {
        $log = $this->gcsLogger();
        $log->info('gcs.upload.start', [
            'disk' => $this->disk,
            'sub_folder' => $subFolder,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        if (! $file->isValid()) {
            $log->error('gcs.upload.invalid_file', [
                'error_code' => $file->getError(),
            ]);
            throw new \RuntimeException(
                'File upload tidak valid. Kode error upload: '.
                    $file->getError(),
            );
        }

        $temporaryPath = $file->getRealPath() ?: $file->getPathname();
        if (! is_string($temporaryPath) || trim($temporaryPath) === '') {
            $log->error('gcs.upload.empty_temp_path');
            throw new \RuntimeException(
                'File upload tidak memiliki temporary path yang valid.',
            );
        }

        $mediaKey = str_replace('-', '', Str::uuid()->toString());
        $extension =
            $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $typeFile =
            $file->getClientMimeType() ?:
            $file->getMimeType() ?:
            'application/octet-stream';

        $folder = $subFolder
            ? "{$this->basePath}/{$subFolder}"
            : $this->basePath;
        $folder = trim($folder, '/');

        $fileName = "{$mediaKey}.{$extension}";
        $filePath = null;

        $log->info('gcs.upload.store_as.begin', [
            'disk' => $this->disk,
            'folder' => $folder,
            'file_name' => $fileName,
        ]);

        try {
            $filePath = $file->storeAs($folder, $fileName, $this->disk);
            $log->info('gcs.upload.store_as.success', [
                'file_path' => $filePath,
            ]);
        } catch (\ValueError $e) {
            // Beberapa environment bisa mengembalikan realPath kosong saat upload file tertentu.
            if (stripos($e->getMessage(), 'Path cannot be empty') === false) {
                $log->error('gcs.upload.store_as.value_error', [
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }

            $log->warning('gcs.upload.store_as.path_empty_fallback', [
                'message' => $e->getMessage(),
            ]);

            $targetPath = ltrim("{$folder}/{$fileName}", '/');
            $stream = fopen($temporaryPath, 'rb');

            if ($stream === false) {
                throw new \RuntimeException(
                    'Gagal membaca file temporary untuk upload.',
                );
            }

            try {
                $stored = Storage::disk($this->disk)->put($targetPath, $stream);
            } finally {
                // Adapter tertentu bisa menutup stream secara internal.
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $log->info('gcs.upload.fallback_put.result', [
                'target_path' => $targetPath,
                'stored' => $stored,
            ]);

            if (! $stored) {
                throw new \RuntimeException(
                    "Gagal menyimpan file ke disk {$this->disk}.",
                );
            }

            $filePath = $targetPath;
        }

        if (! is_string($filePath) || trim($filePath) === '') {
            $log->error('gcs.upload.invalid_result_path', [
                'file_path' => $filePath,
            ]);
            throw new \RuntimeException('Path file hasil upload tidak valid.');
        }

        $log->info('gcs.upload.done', [
            'media_key' => $mediaKey,
            'file_path' => $filePath,
            'type_file' => $typeFile,
        ]);

        return [
            'media_key' => $mediaKey,
            'type_file' => $typeFile,
            'file_path' => $filePath,
        ];
    }

    /**
     * Get URL untuk file yang sudah diupload (mendukung Signed URL jika GCS)
     */
    public function getFileUrl(string $filePath): ?string
    {
        $disk = Storage::disk($this->disk);
        if (! $disk->exists($filePath)) {
            return null;
        }

        if ($this->disk === 'gcs') {
            try {
                return $disk->temporaryUrl($filePath, now()->addHours(2));
            } catch (\Exception $e) {
                $this->gcsLogger()->warning(
                    'gcs.getFileUrl.temporaryUrl_failed',
                    [
                        'file_path' => $filePath,
                        'message' => $e->getMessage(),
                    ],
                );
            }
        }

        return $disk->url($filePath);
    }

    /**
     * Cek keberadaan file dengan SATU round-trip storage saja.
     *
     * Berbeda dengan getFileMetadata() yang memanggil exists + mimeType + size +
     * lastModified + temporaryUrl (4-5 round-trip ke GCS), method ini hanya
     * memanggil exists(). Pakai ini bila pemanggil hanya butuh tahu file ada/tidak.
     *
     * @return array{disk:string, file_path:string, exists:bool}
     */
    public function fileExists(string $filePath): array
    {
        $exists = Storage::disk($this->disk)->exists($filePath);

        return [
            'disk' => $this->disk,
            'file_path' => $filePath,
            'exists' => $exists,
        ];
    }

    /**
     * Ambil metadata file dari storage.
     *
     * @return array<string, mixed>
     */
    public function getFileMetadata(string $filePath): array
    {
        $disk = Storage::disk($this->disk);
        $exists = $disk->exists($filePath);

        $metadata = [
            'disk' => $this->disk,
            'file_path' => $filePath,
            'exists' => $exists,
            'url' => null,
            'thumbnail_url' => null,
            'file_name' => basename($filePath),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION) ?: null,
            'mime_type' => null,
            'size_bytes' => null,
            'size_human' => null,
            'last_modified_ts' => null,
            'last_modified_at' => null,
            'is_image' => false,
        ];

        if (! $exists) {
            return $metadata;
        }

        if ($this->disk === 'gcs') {
            try {
                $metadata['url'] = $disk->temporaryUrl(
                    $filePath,
                    now()->addHours(2),
                );
            } catch (\Exception $e) {
                $this->gcsLogger()->warning(
                    'gcs.metadata.temporaryUrl_failed',
                    [
                        'file_path' => $filePath,
                        'message' => $e->getMessage(),
                    ],
                );
                $metadata['url'] = $disk->url($filePath);
            }
        } else {
            $metadata['url'] = $disk->url($filePath);
        }

        try {
            $metadata['mime_type'] = $disk->mimeType($filePath) ?: null;
        } catch (\Throwable $e) {
            $this->gcsLogger()->warning('gcs.metadata.mime_failed', [
                'disk' => $this->disk,
                'file_path' => $filePath,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $size = $disk->size($filePath);
            $metadata['size_bytes'] = is_numeric($size) ? (int) $size : null;
            $metadata['size_human'] =
                $metadata['size_bytes'] !== null
                    ? $this->formatBytes($metadata['size_bytes'])
                    : null;
        } catch (\Throwable $e) {
            $this->gcsLogger()->warning('gcs.metadata.size_failed', [
                'disk' => $this->disk,
                'file_path' => $filePath,
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $lastModified = $disk->lastModified($filePath);
            $metadata['last_modified_ts'] = is_numeric($lastModified)
                ? (int) $lastModified
                : null;
            $metadata['last_modified_at'] = $metadata['last_modified_ts']
                ? date('c', $metadata['last_modified_ts'])
                : null;
        } catch (\Throwable $e) {
            $this->gcsLogger()->warning('gcs.metadata.last_modified_failed', [
                'disk' => $this->disk,
                'file_path' => $filePath,
                'message' => $e->getMessage(),
            ]);
        }

        $isImage =
            is_string($metadata['mime_type']) &&
            strpos($metadata['mime_type'], 'image/') === 0;
        $metadata['is_image'] = $isImage;
        $metadata['thumbnail_url'] = $isImage ? $metadata['url'] : null;

        return $metadata;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes;
        $unitIndex = -1;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, 2).' '.$units[$unitIndex];
    }

    /**
     * Hapus file dari storage
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            return Storage::disk($this->disk)->delete($filePath);
        } catch (\Exception $e) {
            Log::error('GcsService deleteFile error: '.$e->getMessage());

            return false;
        }
    }
}
