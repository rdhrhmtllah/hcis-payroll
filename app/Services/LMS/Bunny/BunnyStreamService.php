<?php

namespace App\Services\LMS\Bunny;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class BunnyStreamService
{
    protected string $libraryId;

    protected string $apiKey;

    protected string $endpoint;

    protected string $cdnHost;

    protected ?string $tokenKey;

    protected string $tokenMode;

    protected int $tokenTtlSeconds;

    public function __construct()
    {
        /*
         * Dicasting ke string, dan itu bukan kerapian.
         *
         * api_key sengaja TIDAK punya nilai default di config/services.php
         * (alasannya ditulis di sana), jadi di environment yang belum diisi ia
         * bernilai null. Menugaskan null ke properti bertipe `string` melempar
         * TypeError DI KONSTRUKTOR — sebelum penjaga `blank($apiKey)` di
         * createVideo() sempat berjalan. Akibatnya pesan "Konfigurasi Bunny
         * tidak lengkap" yang ditulis khusus untuk keadaan ini tidak pernah
         * bisa tampil, dan yang sampai ke pengguna hanyalah 500 tanpa sebab.
         *
         * Dengan cast, null menjadi '' dan blank('') bernilai true, sehingga
         * penjaganya hidup kembali.
         */
        $this->libraryId = (string) config('services.lms.bunny.library_id');
        $this->apiKey = (string) config('services.lms.bunny.api_key');
        $this->endpoint = rtrim((string) config('services.lms.bunny.endpoint', 'https://video.bunnycdn.com'), '/');

        $configuredHost = trim((string) config('services.lms.bunny.cdn_host', ''));
        $this->cdnHost = $configuredHost !== '' ? $configuredHost : "vz-{$this->libraryId}.b-cdn.net";

        $configuredTokenKey = trim((string) config('services.lms.bunny.token_key', ''));
        $this->tokenKey = $configuredTokenKey !== '' ? $configuredTokenKey : null;
        $this->tokenMode = (string) config('services.lms.bunny.token_mode', 'basic');
        $this->tokenTtlSeconds = (int) config('services.lms.bunny.token_ttl_seconds', 3600);
    }

    private function bunnyLogger()
    {
        try {
            return Log::channel('LmsBunnyService');
        } catch (Throwable $e) {
            $fallback = Log::channel(config('logging.default', 'stack'));
            $fallback->error('LmsBunnyService channel fallback', [
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    // Upload Metode FE -> SERVER -> BUNNY
    // public function uploadVideo(Request $request)
    // {
    //     $log = $this->bunnyLogger();
    //     $traceId = (string) $request->input("trace_id", "");

    //     $log->info("bunny.upload.start", [
    //         "trace_id" => $traceId,
    //         "title" => $request->input("title"),
    //         "has_video" => $request->hasFile("video"),
    //     ]);

    //     try {
    //         $request->validate([
    //             "video" => "required|file|mimes:mp4,mov,avi|max:204800",
    //             "title" => "required|string",
    //             "collection_id" => "nullable|string",
    //         ]);
    //     } catch (Throwable $e) {
    //         $log->error("bunny.upload.validation_failed", [
    //             "trace_id" => $traceId,
    //             "message" => $e->getMessage(),
    //         ]);
    //         throw $e;
    //     }

    //     $libraryId = $this->libraryId;
    //     $apiKey = $this->apiKey;

    //     if (blank($libraryId) || blank($apiKey)) {
    //         $log->error("bunny.config.invalid", [
    //             "trace_id" => $traceId,
    //             "library_id_present" => !blank($libraryId),
    //             "api_key_present" => !blank($apiKey),
    //         ]);

    //         return response()->json(
    //             [
    //                 "success" => false,
    //                 "message" =>
    //                     "Konfigurasi Bunny tidak lengkap (library_id/api_key).",
    //             ],
    //             500,
    //         );
    //     }

    //     $createPayload = [
    //         "title" => $request->title,
    //     ];

    //     if ($request->filled("collection_id")) {
    //         $createPayload["collectionId"] = $request->collection_id;
    //     }

    //     $log->info("bunny.create_video.request", [
    //         "trace_id" => $traceId,
    //         "library_id" => $libraryId,
    //         "payload" => $createPayload,
    //     ]);

    //     try {
    //         $createResponse = Http::withHeaders([
    //             "AccessKey" => $apiKey,
    //             "Content-Type" => "application/json",
    //             "Accept" => "application/json",
    //         ])->post(
    //             "{$this->endpoint}/library/{$libraryId}/videos",
    //             $createPayload,
    //         );

    //         // dd($createResponse->body());
    //     } catch (Throwable $e) {
    //         $log->error("bunny.create_video.exception", [
    //             "trace_id" => $traceId,
    //             "message" => $e->getMessage(),
    //             "file" => $e->getFile(),
    //             "line" => $e->getLine(),
    //         ]);
    //         return response()->json(
    //             ["message" => "Exception saat membuat objek video di Bunny"],
    //             500,
    //         );
    //     }

    //     $log->info("bunny.create_video.response", [
    //         "trace_id" => $traceId,
    //         "status" => $createResponse->status(),
    //         "body" => $createResponse->json(),
    //     ]);

    //     if (!$createResponse->successful()) {
    //         $createBody = $createResponse->json();
    //         $providerMessage =
    //             data_get($createBody, "Message") ??
    //             (data_get($createBody, "message") ?? "Unknown Bunny error");

    //         $log->error("bunny.create_video.failed", [
    //             "trace_id" => $traceId,
    //             "status" => $createResponse->status(),
    //             "provider_message" => $providerMessage,
    //             "body" => $createBody,
    //         ]);

    //         return response()->json(
    //             [
    //                 "success" => false,
    //                 "message" =>
    //                     "Gagal membuat objek video di Bunny (" .
    //                     $createResponse->status() .
    //                     "): " .
    //                     $providerMessage,
    //                 "bunny_status" => $createResponse->status(),
    //                 "bunny_response" => $createBody,
    //             ],
    //             500,
    //         );
    //     }

    //     $videoId = $createResponse->json("guid");
    //     $log->info("bunny.create_video.success", [
    //         "trace_id" => $traceId,
    //         "video_id" => $videoId,
    //     ]);

    //     $videoFile = $request->file("video");
    //     $videoPath = $videoFile->getRealPath() ?: $videoFile->getPathname();

    //     $log->info("bunny.upload.stream.prepare", [
    //         "trace_id" => $traceId,
    //         "video_path" => $videoPath,
    //         "size" => $videoFile->getSize(),
    //         "mime" => $videoFile->getClientMimeType(),
    //     ]);

    //     if (!is_string($videoPath) || trim($videoPath) === "") {
    //         return response()->json(
    //             ["message" => "Path temporary video tidak valid"],
    //             500,
    //         );
    //     }

    //     $fileStream = fopen($videoPath, "rb");
    //     if ($fileStream === false) {
    //         $log->error("bunny.upload.stream.open_failed", [
    //             "trace_id" => $traceId,
    //             "video_path" => $videoPath,
    //         ]);
    //         return response()->json(
    //             ["message" => "Gagal membaca stream video"],
    //             500,
    //         );
    //     }

    //     try {
    //         $uploadResponse = Http::withHeaders([
    //             "AccessKey" => $apiKey,
    //         ])
    //             ->withBody($fileStream, "application/octet-stream")
    //             ->put(
    //                 "{$this->endpoint}/library/{$libraryId}/videos/{$videoId}",
    //             );
    //     } catch (Throwable $e) {
    //         $log->error("bunny.upload.exception", [
    //             "trace_id" => $traceId,
    //             "video_id" => $videoId,
    //             "message" => $e->getMessage(),
    //             "file" => $e->getFile(),
    //             "line" => $e->getLine(),
    //         ]);
    //         return response()->json(
    //             ["message" => "Exception saat upload binary video ke Bunny"],
    //             500,
    //         );
    //     } finally {
    //         if (is_resource($fileStream)) {
    //             fclose($fileStream);
    //         }
    //     }

    //     $log->info("bunny.upload.response", [
    //         "trace_id" => $traceId,
    //         "video_id" => $videoId,
    //         "status" => $uploadResponse->status(),
    //         "body" => $uploadResponse->json(),
    //     ]);

    //     if (!$uploadResponse->successful()) {
    //         $uploadBody = $uploadResponse->json();
    //         $providerMessage =
    //             data_get($uploadBody, "Message") ??
    //             (data_get($uploadBody, "message") ?? "Unknown Bunny error");

    //         $log->error("bunny.upload.failed", [
    //             "trace_id" => $traceId,
    //             "video_id" => $videoId,
    //             "status" => $uploadResponse->status(),
    //             "provider_message" => $providerMessage,
    //             "body" => $uploadBody,
    //         ]);

    //         return response()->json(
    //             [
    //                 "success" => false,
    //                 "message" =>
    //                     "Gagal mengupload file video ke Bunny (" .
    //                     $uploadResponse->status() .
    //                     "): " .
    //                     $providerMessage,
    //                 "bunny_status" => $uploadResponse->status(),
    //                 "bunny_response" => $uploadBody,
    //             ],
    //             500,
    //         );
    //     }

    //     return response()->json([
    //         "success" => true,
    //         "message" => "Video berhasil diupload",
    //         "video_id" => $videoId,
    //     ]);
    // }

    // Upload Metode FE -> SERVER (buat signature) -> FE upload langsung ke Bunny menggunakan TUS
    public function createTusSignature(Request $request)
    {
        $log = $this->bunnyLogger();
        $traceId = (string) $request->input('trace_id', '');

        $log->info('bunny.tus_signature.start', [
            'trace_id' => $traceId,
            'title' => $request->input('title'),
        ]);

        try {
            $request->validate([
                'title' => 'required|string',
                'collection_id' => 'nullable|string',
            ]);
        } catch (Throwable $e) {
            $log->error('bunny.tus_signature.validation_failed', [
                'trace_id' => $traceId,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;

        if (blank($libraryId) || blank($apiKey)) {
            $log->error('bunny.config.invalid', [
                'trace_id' => $traceId,
                'library_id_present' => ! blank($libraryId),
                'api_key_present' => ! blank($apiKey),
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Konfigurasi Bunny tidak lengkap (library_id/api_key).',
                ],
                500,
            );
        }

        $createPayload = [
            'title' => $request->title,
        ];

        if ($request->filled('collection_id')) {
            $createPayload['collectionId'] = $request->collection_id;
        }

        $log->info('bunny.create_video.request', [
            'trace_id' => $traceId,
            'library_id' => $libraryId,
            'payload' => $createPayload,
        ]);

        try {
            // TAHAP 1: Tetap memanggil API Bunny untuk membuat "ruang kosong" Video ID
            $createResponse = Http::withHeaders([
                'AccessKey' => $apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post("{$this->endpoint}/library/{$libraryId}/videos", $createPayload);
        } catch (Throwable $e) {
            $log->error('bunny.create_video.exception', [
                'trace_id' => $traceId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json(['message' => 'Exception saat membuat objek video di Bunny'], 500);
        }

        $log->info('bunny.create_video.response', [
            'trace_id' => $traceId,
            'status' => $createResponse->status(),
            'body' => $createResponse->json(),
        ]);

        if (! $createResponse->successful()) {
            $createBody = $createResponse->json();
            $providerMessage =
                data_get($createBody, 'Message') ?? (data_get($createBody, 'message') ?? 'Unknown Bunny error');

            $log->error('bunny.create_video.failed', [
                'trace_id' => $traceId,
                'status' => $createResponse->status(),
                'provider_message' => $providerMessage,
                'body' => $createBody,
            ]);

            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal membuat objek video di Bunny ('.$createResponse->status().'): '.$providerMessage,
                    'bunny_status' => $createResponse->status(),
                    'bunny_response' => $createBody,
                ],
                500,
            );
        }

        $videoId = $createResponse->json('guid');

        $log->info('bunny.create_video.success', [
            'trace_id' => $traceId,
            'video_id' => $videoId,
        ]);

        // ====================================================================
        // PERUBAHAN 2: Bagian stream fopen() dan HTTP PUT dihapus semua!
        // Diganti dengan logika pembuatan Signature TUS.
        // ====================================================================

        $log->info('bunny.generate_signature.start', [
            'trace_id' => $traceId,
            'video_id' => $videoId,
        ]);

        // Buat waktu kedaluwarsa token (misal: 1 jam dari sekarang)
        $expirationTime = time() + 3600;

        // Rumus Wajib BunnyCDN: sha256(library_id + api_key + expiration_time + video_id)
        $signature = hash('sha256', $libraryId.$apiKey.$expirationTime.$videoId);

        $log->info('bunny.generate_signature.success', [
            'trace_id' => $traceId,
            'video_id' => $videoId,
        ]);

        // PERUBAHAN 3: Kembalikan JSON berisi data yang dibutuhkan Vue.js
        return response()->json([
            'success' => true,
            'message' => 'Signature berhasil dibuat, siap untuk upload di frontend',
            'video_id' => $videoId,
            'library_id' => $libraryId,
            'expiration_time' => $expirationTime,
            'signature' => $signature,
        ]);
    }

    public function checkVideoStatus($videoId)
    {
        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;

        try {
            $response = Http::withHeaders([
                'AccessKey' => $apiKey,
                'Accept' => 'application/json',
            ])->get("{$this->endpoint}/library/{$libraryId}/videos/{$videoId}");

            if (! $response->successful()) {
                return response()->json(
                    [
                        'message' => 'Gagal mengecek status',
                        'http_status' => $response->status(),
                        'bunny_error' => $response->json(),
                    ],
                    $response->status(),
                );
            }

            $data = $response->json();

            return response()->json([
                'success' => true,
                'status_code' => $data['status'],
                'encode_progress' => $data['encodeProgress'],
                'is_ready' => $data['encodeProgress'] === 100 && ($data['status'] == 3 || $data['status'] == 4),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getCurrentCollections(Request $request)
    {
        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;

        $page = $request->input('page', 1);
        $itemsPerPage = $request->input('itemsPerPage', 100);

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Accept' => 'application/json',
        ])->get("{$this->endpoint}/library/{$libraryId}/collections", [
            'page' => $page,
            'itemsPerPage' => $itemsPerPage,
        ]);

        if (! $response->successful()) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal mengambil data collections',
                    'bunny_error' => $response->json(),
                ],
                $response->status(),
            );
        }

        return response()->json([
            'success' => true,
            'data' => $response->json(),
        ]);
    }

    public function createCollection(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("{$this->endpoint}/library/{$libraryId}/collections", [
            'name' => $request->name,
        ]);

        return response()->json($response->json(), $response->status());
    }

    public function updateVideo(Request $request, $videoId)
    {
        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;

        $request->validate([
            'title' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post("{$this->endpoint}/library/{$libraryId}/videos/{$videoId}", $request->all());

        return response()->json($response->json(), $response->status());
    }

    public function deleteVideo($videoId)
    {
        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;
        // dd($libraryId, $apiKey, $videoId);
        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Accept' => 'application/json',
        ])->delete("{$this->endpoint}/library/{$libraryId}/videos/{$videoId}");

        return response()->json($response->json(), $response->status());
    }

    public function listVideos(Request $request, $collectionId)
    {
        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;

        $page = $request->input('page', 1);
        $itemsPerPage = $request->input('itemsPerPage', 100);

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Accept' => 'application/json',
        ])->get("{$this->endpoint}/library/{$libraryId}/videos", [
            'collection' => $collectionId,
            'page' => $page,
            'itemsPerPage' => $itemsPerPage,
        ]);

        return response()->json($response->json(), $response->status());
    }

    public function getPlayVideo($videoId)
    {
        $video = $this->getVideoMetadata((string) $videoId);

        if ($video === null) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Video tidak ditemukan',
                ],
                404,
            );
        }

        return response()->json([
            'success' => true,
            'data' => [
                'title' => data_get($video, 'title'),
                'status' => data_get($video, 'status_code'),
                'encode_progress' => data_get($video, 'encode_progress'),
                'embed_url' => data_get($video, 'embed_url'),
                'thumbnail_url' => data_get($video, 'thumbnail_url'),
                'metadata' => $video,
            ],
        ]);
    }

    /**
     * Ambil metadata video dari Bunny Stream.
     *
     * @return array<string, mixed>|null
     */
    public function getVideoMetadata(string $videoId): ?array
    {
        $libraryId = $this->libraryId;
        $apiKey = $this->apiKey;

        if (blank($libraryId) || blank($apiKey) || trim($videoId) === '') {
            return null;
        }

        $response = Http::withHeaders([
            'AccessKey' => $apiKey,
            'Accept' => 'application/json',
        ])->get("{$this->endpoint}/library/{$libraryId}/videos/{$videoId}");

        if (! $response->successful()) {
            return null;
        }

        $video = $response->json();

        if (! is_array($video)) {
            return null;
        }

        $thumbnailUrl = $this->resolveBunnyThumbnailUrl($videoId, $video);
        $thumbnailCandidates = $this->resolveBunnyThumbnailCandidates($videoId, $video);

        return [
            'provider' => 'bunny',
            'library_id' => (string) $libraryId,
            'video_id' => (string) $videoId,
            'guid' => data_get($video, 'guid'),
            'title' => data_get($video, 'title'),
            'status_code' => data_get($video, 'status'),
            'encode_progress' => data_get($video, 'encodeProgress'),
            'is_ready' => data_get($video, 'encodeProgress') === 100,
            'length_seconds' => data_get($video, 'length'),
            'storage_size_bytes' => data_get($video, 'storageSize'),
            'width' => data_get($video, 'width'),
            'height' => data_get($video, 'height'),
            'framerate' => data_get($video, 'framerate'),
            'rotation' => data_get($video, 'rotation'),
            'available_resolutions' => data_get($video, 'availableResolutions'),
            'collection_id' => data_get($video, 'collectionId'),
            'date_uploaded' => data_get($video, 'dateUploaded'),
            'views' => data_get($video, 'views'),
            'thumbnail_file_name' => data_get($video, 'thumbnailFileName'),
            'thumbnail_url' => $thumbnailUrl,
            'thumbnail_candidates' => $thumbnailCandidates,
            'embed_url' => "https://iframe.mediadelivery.net/embed/{$libraryId}/{$videoId}?autoplay=true&loop=false&muted=false&preload=true&responsive=true",
            'hls_url' => "https://vz-{$libraryId}.b-cdn.net/{$videoId}/playlist.m3u8",
            'raw' => $video,
        ];
    }

    /**
     * Bangun URL thumbnail Bunny berdasarkan metadata yang tersedia.
     *
     * @param  array<string, mixed>  $video
     */
    private function resolveBunnyThumbnailUrl(string $videoId, array $video): string
    {
        $candidates = $this->resolveBunnyThumbnailCandidates($videoId, $video);

        return $candidates[0] ?? '';
    }

    /**
     * @param  array<string, mixed>  $video
     * @return array<int, string>
     */
    private function resolveBunnyThumbnailCandidates(string $videoId, array $video): array
    {
        $thumbnailFileName = data_get($video, 'thumbnailFileName');
        $thumbnailName =
            is_string($thumbnailFileName) && trim($thumbnailFileName) !== ''
                ? ltrim($thumbnailFileName, '/')
                : 'thumbnail.jpg';

        $path = "/{$videoId}/{$thumbnailName}";

        $candidates = [];
        $signedUrl = $this->buildSignedCdnUrl($path);
        if ($signedUrl !== '') {
            $candidates[] = $signedUrl;
        }

        $alternativeMode = strtolower($this->tokenMode) === 'basic' ? 'advanced' : 'basic';
        $altSignedUrl = $this->buildSignedCdnUrl($path, $alternativeMode);
        if ($altSignedUrl !== '' && ! in_array($altSignedUrl, $candidates, true)) {
            $candidates[] = $altSignedUrl;
        }

        foreach ($this->buildAdvancedTokenPathUrls($path) as $advancedUrl) {
            if (! in_array($advancedUrl, $candidates, true)) {
                $candidates[] = $advancedUrl;
            }
        }

        $publicUrl = "https://{$this->cdnHost}{$path}";
        if (! in_array($publicUrl, $candidates, true)) {
            $candidates[] = $publicUrl;
        }

        return $candidates;
    }

    /**
     * URL unduhan langsung sebuah video (berkas .mp4), bertanda tangan token CDN.
     *
     * Dipakai item FORM, yang kontraknya "diunduh dan tak pernah diputar".
     * Jalur tontonan biasa memakai embed/HLS, dan HLS adalah potongan-potongan
     * streaming — ia tak bisa dijadikan satu berkas untuk diunduh.
     *
     * PRASYARAT DI DASBOR BUNNY, bukan di kode:
     *   1. MP4 Fallback diaktifkan pada library — tanpa itu berkas play_*.mp4
     *      tidak pernah dibuat dan URL ini akan 404;
     *   2. Edge Rule "Force Download" pada pull zone, supaya peramban mengunduh
     *      alih-alih memutarnya di tab.
     *
     * @param  array|null  $resolusiTersedia  dari metadata Bunny ("240p,480p,720p")
     */
    public function mp4DownloadUrl(string $videoId, $resolusiTersedia = null): ?string
    {
        if (trim($videoId) === '') {
            return null;
        }

        $res = $this->resolusiTerbaik($resolusiTersedia);
        if ($res === null) {
            return null;
        }

        return $this->buildSignedCdnUrl("/{$videoId}/play_{$res}.mp4");
    }

    /**
     * Resolusi tertinggi yang benar-benar tersedia.
     *
     * Menebak "720p" tanpa memeriksa daftar akan menghasilkan 404 untuk video
     * yang hanya di-transcode sampai 480p — dan 404 itu baru terlihat oleh
     * karyawan saat mengklik unduh, bukan oleh admin saat mengunggah.
     */
    private function resolusiTerbaik($tersedia): ?string
    {
        $daftar = is_array($tersedia)
            ? $tersedia
            : array_filter(array_map('trim', explode(',', (string) $tersedia)));

        if (empty($daftar)) {
            return null;
        }

        $urut = ['2160p' => 6, '1440p' => 5, '1080p' => 4, '720p' => 3, '480p' => 2, '360p' => 1, '240p' => 0];
        $terbaik = null;
        foreach ($daftar as $r) {
            $r = strtolower(trim((string) $r));
            if (! isset($urut[$r])) {
                continue;
            }
            if ($terbaik === null || $urut[$r] > $urut[$terbaik]) {
                $terbaik = $r;
            }
        }

        return $terbaik;
    }

    private function buildSignedCdnUrl(string $path, ?string $mode = null): string
    {
        $normalizedPath = '/'.ltrim($path, '/');
        $baseUrl = "https://{$this->cdnHost}{$normalizedPath}";

        if ($this->tokenKey === null) {
            return $baseUrl;
        }

        $expires = time() + max(60, $this->tokenTtlSeconds);
        $effectiveMode = strtolower($mode ?? $this->tokenMode);

        if ($effectiveMode === 'advanced') {
            $hashableBase = $this->tokenKey.$normalizedPath.$expires;
            $token = hash('sha256', $hashableBase, true);
        } else {
            $hashableBase = $this->tokenKey.$normalizedPath.$expires;
            $token = md5($hashableBase, true);
        }

        $token = base64_encode($token);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);

        $query = http_build_query([
            'token' => $token,
            'expires' => $expires,
        ]);

        return $baseUrl.'?'.$query;
    }

    /**
     * Beberapa konfigurasi Bunny Advanced Token Auth membutuhkan token_path
     * atau format bcdn_token pada URL video assets.
     *
     * @return array<int, string>
     */
    private function buildAdvancedTokenPathUrls(string $path): array
    {
        if ($this->tokenKey === null) {
            return [];
        }

        $normalizedPath = '/'.ltrim($path, '/');
        $baseUrl = "https://{$this->cdnHost}{$normalizedPath}";
        $expires = time() + max(60, $this->tokenTtlSeconds);

        $videoId = trim((string) strtok(ltrim($normalizedPath, '/'), '/'));
        $tokenPath = $videoId !== '' ? "/{$videoId}/" : dirname($normalizedPath).'/';

        $parameterString = 'token_path='.urlencode($tokenPath);
        $hashableBase = $this->tokenKey.$normalizedPath.$expires.''.$parameterString;

        $token = hash('sha256', $hashableBase, true);
        $token = base64_encode($token);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);

        $queryUrl =
            $baseUrl.'?token='.urlencode($token).'&expires='.$expires.'&token_path='.urlencode($tokenPath);

        $pathBasedUrl =
            "https://{$this->cdnHost}/bcdn_token=".
            urlencode($token).
            '&expires='.
            $expires.
            '&token_path='.
            urlencode($tokenPath).
            $normalizedPath;

        return [$queryUrl, $pathBasedUrl];
    }
}
