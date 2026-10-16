<?php

namespace App\Http\Controllers\LMS\Content;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\ESS\Activity\Center\UploadTaskService;
use App\Services\LMS\Bunny\BunnyStreamService;
use App\Services\LMS\Content\Verification\KontenPolicy;
use App\Services\LMS\Content\Verification\KontenVerifierService;
use App\Services\LMS\GCS\GcsService;
use App\Services\LMS\TrainingScheduleQueryService;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;
use Vinkla\Hashids\Facades\Hashids;

class ContentManagementController extends Controller
{
    public function __construct(private TrainingScheduleQueryService $trainingScheduleQueryService) {}

    /**
     * Scope library aktif dari route (default 'LMS').
     * Rute SOP mendaftar dengan ->defaults('scope','SOP') sehingga controller & FE
     * yang SAMA melayani dua halaman (LMS & SOP) dengan konten terisolasi.
     */
    private function currentScope(): string
    {
        $s = request()->route('scope');

        return in_array($s, ['LMS', 'SOP'], true) ? $s : 'LMS';
    }

    private function lmsLogger()
    {
        try {
            return Log::channel('KontenKategoriController');
        } catch (Throwable $e) {
            $fallback = Log::channel(config('logging.default', 'stack'));
            $fallback->error('KontenKategoriController channel fallback', [
                'message' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    //
    public function index()
    {
        $scope = $this->currentScope();

        return inertia('LMS/Content/ContentManagement/contentDashboard', [
            'scope' => $scope,
            'basePath' => $scope === 'SOP' ? '/sop/admin/konten' : '/lms/konten-kategori',
        ]);
    }

    public function createKategori(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required',
        ]);

        $user = Auth::user();

        try {
            $id = DB::table('N_LMS_Kategori_Konten')->insertGetId([
                'Nama_Kategori' => $request->nama_kategori,
                'Created_By' => $user->Id_Users,
                'Tanggal' => now(),
                'Jam' => now()->format('H:i:s'),
                'Flag_Aktif' => 'Y',
                'Scope' => $this->currentScope(),
            ]);

            return ResponseHelper::success(
                [
                    'id' => Hashids::connection('custom')->encode($id),
                    'message' => 'Kategori berhasil dibuat',
                ],
                'Kategori berhasil dibuat',
                201,
            );
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error createKategori: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat kategori', 500);
        }
    }

    public function rename(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'type' => 'required|in:category,file',
            'name' => 'required|string|max:255',
        ]);

        try {
            $realId = Hashids::connection('custom')->decode($request->id)[0] ?? (int) $request->id;
            $type = $request->type;
            $newName = trim($request->name);
            $user = Auth::user();

            if ($type === 'category') {
                $kategori = DB::table('N_LMS_Kategori_Konten')
                    ->where('Id_Kategori_Konten', $realId)
                    ->where('Flag_Aktif', 'Y')
                    ->where('Scope', $this->currentScope())
                    ->first();

                if (! $kategori) {
                    return ResponseHelper::error('Kategori tidak ditemukan.', 404);
                }

                if (strtolower(trim($kategori->Nama_Kategori)) === strtolower($newName)) {
                    return ResponseHelper::error('Nama kategori baru tidak boleh sama dengan nama saat ini.', 400);
                }

                $isDuplicate = DB::table('N_LMS_Kategori_Konten')
                    ->whereRaw('LOWER(Nama_Kategori) = ?', [strtolower($newName)])
                    ->where('Flag_Aktif', 'Y')
                    ->where('Id_Kategori_Konten', '!=', $realId)
                    ->exists();

                if ($isDuplicate) {
                    return ResponseHelper::error('Nama kategori sudah digunakan. Silakan gunakan nama lain.', 400);
                }

                DB::table('N_LMS_Kategori_Konten')
                    ->where('Id_Kategori_Konten', $realId)
                    ->update([
                        'Nama_Kategori' => $newName,
                        'Updated_At' => now(),
                        'Updated_By' => $user->Id_Users,
                    ]);
            } elseif ($type === 'file') {
                $konten = DB::table('N_LMS_Master_Konten')
                    ->where('Id_Master_Konten', $realId)
                    ->where('Flag_Aktif', 'Y')
                    ->where('Scope', $this->currentScope())
                    ->first();

                if (! $konten) {
                    return ResponseHelper::error('Konten tidak ditemukan.', 404);
                }

                if (strtolower(trim($konten->Judul_Konten)) === strtolower($newName)) {
                    return ResponseHelper::error('Nama konten baru tidak boleh sama dengan nama saat ini.', 400);
                }

                $isDuplicate = DB::table('N_LMS_Master_Konten')
                    ->whereRaw('LOWER(Judul_Konten) = ?', [strtolower($newName)])
                    ->where('Id_Kategori_Konten', $konten->Id_Kategori_Konten)
                    ->where('Flag_Aktif', 'Y')
                    ->where('Id_Master_Konten', '!=', $realId)
                    ->exists();

                if ($isDuplicate) {
                    return ResponseHelper::error(
                        'Nama konten ini sudah digunakan di kategori yang sama. Silakan gunakan nama lain.',
                        400,
                    );
                }

                DB::table('N_LMS_Master_Konten')
                    ->where('Id_Master_Konten', $realId)
                    ->update([
                        'Judul_Konten' => $newName,
                    ]);
            }

            return ResponseHelper::success(['message' => 'Nama berhasil diubah'], 'Nama berhasil diubah', 200);
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error rename: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengubah nama: '.$e->getMessage(), 500);
        }
    }

    public function updateKategori(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'nama_kategori' => 'required',
        ]);

        try {
            $realId = Hashids::connection('custom')->decode($request->id)[0] ?? (int) $request->id;
            $user = Auth::user();

            DB::table('N_LMS_Kategori_Konten')
                ->where('Id_Kategori_Konten', $realId)
                ->where('Scope', $this->currentScope())
                ->update([
                    'Nama_Kategori' => $request->nama_kategori,
                    'Updated_At' => now(),
                    'Updated_By' => $user->Id_Users,
                ]);

            return ResponseHelper::success(
                [
                    'message' => 'Kategori berhasil diupdate',
                ],
                'Kategori berhasil diupdate',
                200,
            );
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error updateKategori: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengupdate kategori', 500);
        }
    }

    public function deleteKategori(Request $request)
    {
        $request->validate([
            'id' => 'required',
        ]);

        try {
            $realId = Hashids::connection('custom')->decode($request->id)[0] ?? (int) $request->id;
            $user = Auth::user();

            $contentIds = DB::table('N_LMS_Master_Konten')
                ->where('Id_Kategori_Konten', $realId)
                ->where('Flag_Aktif', 'Y')
                ->where('Scope', $this->currentScope())
                ->pluck('Id_Master_Konten')
                ->toArray();

            $mediaRecords = collect();
            $berkasRecords = collect();

            if (! empty($contentIds)) {
                $mediaRecords = DB::table('N_LMS_Master_Konten_Media')->whereIn('Id_Master_Konten', $contentIds)->get();

                $berkasRecords = DB::table('N_LMS_Master_Konten_Berkas')
                    ->whereIn('Id_Master_Konten', $contentIds)
                    ->get();
            }

            DB::beginTransaction();

            if (! empty($contentIds)) {
                DB::table('N_LMS_Master_Konten')
                    ->whereIn('Id_Master_Konten', $contentIds)
                    ->where('Flag_Aktif', 'Y')
                    ->update([
                        'Flag_Aktif' => 'T',
                    ]);
            }

            DB::table('N_LMS_Kategori_Konten')
                ->where('Id_Kategori_Konten', $realId)
                ->where('Scope', $this->currentScope())
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => now(),
                    'Updated_By' => $user->Id_Users,
                ]);

            DB::commit();

            if ($mediaRecords->isNotEmpty()) {
                $bunnyService = new BunnyStreamService;
                foreach ($mediaRecords as $media) {
                    if ($media->Storage_Provider === 'bunny' && $media->Provider_Media_Id) {
                        try {
                            $bunnyService->deleteVideo($media->Provider_Media_Id);
                        } catch (Throwable $e) {
                            Log::error(
                                "Gagal menghapus video Bunny ID: {$media->Provider_Media_Id}. Error: ".
                                    $e->getMessage(),
                            );
                        }
                    }
                }
            }

            if ($berkasRecords->isNotEmpty()) {
                foreach ($berkasRecords as $berkas) {
                    if ($berkas->File_Path) {
                        try {
                            \Illuminate\Support\Facades\Storage::disk('gcs')->delete($berkas->File_Path);
                        } catch (Throwable $e) {
                            Log::error(
                                "Gagal menghapus file GCS Path: {$berkas->File_Path}. Error: ".$e->getMessage(),
                            );
                        }
                    }
                }
            }

            return ResponseHelper::success(
                [
                    'message' => 'Kategori berhasil dihapus',
                    'deleted_content_count' => count($contentIds),
                ],
                'Kategori berhasil dihapus',
                200,
            );
        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('KontenKategoriController')->error('Error deleteKategori: '.$e->getMessage());

            return ResponseHelper::error('Gagal menghapus kategori', 500);
        }
    }

    public function getKategori(Request $request)
    {
        try {
            $getKategori = DB::table('N_LMS_Kategori_Konten')
                ->join('Karyawan', 'N_LMS_Kategori_Konten.Created_By', '=', 'Karyawan.Userid_Web')
                ->where('N_LMS_Kategori_Konten.Flag_Aktif', 'Y')
                ->where('N_LMS_Kategori_Konten.Scope', $this->currentScope())
                ->select(
                    'N_LMS_Kategori_Konten.Id_Kategori_Konten',
                    'N_LMS_Kategori_Konten.Nama_Kategori',
                    'N_LMS_Kategori_Konten.Tanggal',
                    'Karyawan.Nama as Created_By',
                )
                ->get();

            /* Hitungan konten diambil SEKALI untuk seluruh kategori, bukan satu
               kueri per kategori. Kategori yang belum punya konten tidak muncul
               di hasil kelompok; dibaca sebagai 0 — jawaban yang sama dengan
               count() atas himpunan kosong. */
            $idKategori = $getKategori->pluck('Id_Kategori_Konten')->map(fn ($v) => (int) $v)->unique()->values();

            $jumlahKonten = $idKategori->isEmpty() ? [] : DB::table('N_LMS_Master_Konten')
                ->whereIn('Id_Kategori_Konten', $idKategori->all())
                ->where('Flag_Aktif', 'Y')
                ->groupBy('Id_Kategori_Konten')
                ->selectRaw('Id_Kategori_Konten as kunci, COUNT(*) as jumlah')
                ->pluck('jumlah', 'kunci')
                ->mapWithKeys(fn ($n, $id) => [(int) $id => (int) $n])
                ->all();

            $kategoris = $getKategori->map(function ($kategori) use ($jumlahKonten) {
                $contentCount = $jumlahKonten[(int) $kategori->Id_Kategori_Konten] ?? 0;

                return [
                    'id' => Hashids::connection('custom')->encode($kategori->Id_Kategori_Konten),
                    'type' => 'category',
                    'created_by' => $kategori->Created_By,
                    'name' => $kategori->Nama_Kategori,
                    'contentCount' => $contentCount,
                    'status' => 'LIVE',
                    'metadata' => [
                        'createdDate' => $kategori->Tanggal ?? '-',
                    ],
                ];
            });

            return ResponseHelper::success(
                [
                    'data' => $kategoris,
                ],
                'Kategori berhasil diambil',
                200,
            );
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error getKategori: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil kategori', 500);
        }
    }

    /**
     * Get konten berdasarkan kategori ID
     */
    public function getKontenByKategori($id)
    {
        try {
            $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

            $kontens = DB::table('N_LMS_Master_Konten as k')
                ->where('k.Id_Kategori_Konten', $realId)
                ->where('k.Flag_Aktif', 'Y')
                ->where('k.Scope', $this->currentScope())
                ->leftJoin('N_LMS_Master_Konten_Berkas as b', 'k.Id_Master_Konten', '=', 'b.Id_Master_Konten')
                ->leftJoin('N_LMS_Master_Konten_Media as m', 'k.Id_Master_Konten', '=', 'm.Id_Master_Konten')
                ->select(
                    'k.Id_Master_Konten',
                    'k.Id_Kategori_Konten',
                    'k.Judul_Konten',
                    'k.Tipe_Konten',
                    'k.Flag_Third_Party',
                    'k.Tanggal',
                    // Berkas columns
                    'b.Id_Master_Konten_Berkas',
                    'b.Media_Key as Berkas_Media_Key',
                    'b.Type_File as Berkas_Type_File',
                    'b.File_Path',
                    'b.Status_Render',
                    'b.Total_Halaman',
                    'b.Error_Message',
                    'b.Status_Verifikasi',
                    'b.Alasan_Tolak',
                    'b.Ext',
                    'b.Ukuran_Byte',
                    // Media columns
                    'm.Id_Master_Konten_Media',
                    'm.Media_Key as Media_Media_Key',
                    'm.Type_File as Media_Type_File',
                    'm.Provider_Media_Id',
                    'm.Storage_Provider',
                )
                ->get();

            $gcsService = new GcsService;

            /* Layanan Bunny dibangun HANYA saat ada baris video pihak ketiga
               yang benar-benar membutuhkannya — lihat pemakaiannya di bawah,
               yang seluruhnya berada di dalam satu cabang bersyarat.
               Membangunnya di muka membuat kategori BERISI DOKUMEN SAJA ikut
               gagal 500 di lingkungan tanpa BUNNY_API_KEY, sebab konstruktornya
               menugaskan config yang null ke properti bertipe string.
               Kredensial video seharusnya tak pernah jadi syarat untuk memilih
               berkas PDF. */
            $bunny = null;
            $bunnyService = function () use (&$bunny) {
                return $bunny ??= new BunnyStreamService;
            };

            $result = $kontens->map(function ($konten) use ($gcsService, $bunnyService) {
                $item = [
                    'id' => Hashids::connection('custom')->encode($konten->Id_Master_Konten),
                    'categoryId' => Hashids::connection('custom')->encode($konten->Id_Kategori_Konten),
                    'name' => $konten->Judul_Konten,
                    'type' => $konten->Tipe_Konten ?? 'file',
                    'fileType' => $konten->Tipe_Konten ?? 'file',
                    'status' => 'LIVE',
                    'flagThirdParty' => $konten->Flag_Third_Party,
                    // Status verifikasi berkas. Video (tanpa baris berkas) tak
                    // punya status karena tak melewati penyimpanan kita.
                    'statusVerifikasi' => $konten->Status_Verifikasi,
                    'alasanTolak' => $konten->Alasan_Tolak,
                    'metadata' => [
                        'uploadedAt' => $konten->Tanggal ?? '-',
                    ],
                ];

                if ($konten->Flag_Third_Party === 'Y' && $konten->Provider_Media_Id) {
                    $item['mediaKey'] = $konten->Media_Media_Key;
                    $item['providerMediaId'] = $konten->Provider_Media_Id;
                    $item['storageProvider'] = $konten->Storage_Provider;
                    $item['type'] = 'video';
                    $item['fileType'] = 'video';

                    $videoMetadata = $bunnyService()->getVideoMetadata((string) $konten->Provider_Media_Id);

                    if (is_array($videoMetadata)) {
                        $thumbnailFileName = $videoMetadata['thumbnail_file_name'] ?? 'thumbnail.jpg';

                        $item['thumbnailUrl'] = route('lms.konten-kategori.thumbnail', [
                            'videoId' => (string) $konten->Provider_Media_Id,
                            'fileName' => (string) $thumbnailFileName,
                        ]);
                        $item['previewUrl'] = $item['thumbnailUrl'];
                        $item['embedUrl'] = $videoMetadata['embed_url'] ?? null;
                        $item['playbackUrl'] = $videoMetadata['hls_url'] ?? null;

                        $item['metadata'] = array_merge($item['metadata'], [
                            'statusCode' => $videoMetadata['status_code'] ?? null,
                            'encodeProgress' => $videoMetadata['encode_progress'] ?? null,
                            'durationSeconds' => $videoMetadata['length_seconds'] ?? null,
                            'storageSizeBytes' => $videoMetadata['storage_size_bytes'] ?? null,
                            'resolution' => ($videoMetadata['width'] ?? null) && ($videoMetadata['height'] ?? null)
                                    ? $videoMetadata['width'].'x'.$videoMetadata['height']
                                    : null,
                            'framerate' => $videoMetadata['framerate'] ?? null,
                            'availableResolutions' => $videoMetadata['available_resolutions'] ?? null,
                            'views' => $videoMetadata['views'] ?? null,
                        ]);
                    }
                } elseif ($konten->File_Path) {
                    $item['mediaKey'] = $konten->Berkas_Media_Key;
                    $item['filePath'] = $konten->File_Path;

                    /*
                     * Tipe_Konten hanya membedakan document/image/video, sehingga
                     * PDF dan docx sama-sama "document" dan tak terbedakan di
                     * antarmuka. Ekstensinyalah yang menentukan apakah konten ini
                     * bisa dibaca di aplikasi atau hanya bisa diunduh — dan
                     * keputusan itu diambil di server, bukan ditebak komponen Vue.
                     */
                    $ext = strtolower((string) ($konten->Ext ?: pathinfo($konten->File_Path, PATHINFO_EXTENSION)));
                    $item['ext'] = $ext;
                    $item['extLabel'] = KontenPolicy::label($ext);
                    $item['tampilan'] = KontenPolicy::tampilan($ext);

                    $isPdf = $item['tampilan'] === 'viewer';

                    if ($item['tampilan'] === 'unduh') {
                        /*
                         * Dokumen Office: tak ada thumbnail yang bisa dibuat dan
                         * tak ada pratinjau yang boleh dibuka, jadi memanggil
                         * metadata GCS di sini adalah empat round-trip untuk
                         * URL yang justru tak boleh dipakai. Ukurannya sudah
                         * tercatat saat verifikasi.
                         */
                        $item['fileUrl'] = null;
                        $item['thumbnailUrl'] = null;
                        $item['previewUrl'] = null;

                        $item['metadata'] = array_merge($item['metadata'], [
                            'typeFile' => $konten->Berkas_Type_File,
                            'extension' => $ext,
                            'sizeBytes' => $konten->Ukuran_Byte ? (int) $konten->Ukuran_Byte : null,
                        ]);
                    } elseif ($isPdf) {
                        // PDF: di list tidak butuh url/size/thumbnail dari GCS (viewer pakai
                        // handshake sendiri). Cukup cek keberadaan = 1 GCS call (bukan 4).
                        $exists = $gcsService->fileExists($konten->File_Path)['exists'] ?? false;

                        $item['fileUrl'] = null;
                        $item['thumbnailUrl'] = null;
                        $item['previewUrl'] = null;

                        $item['renderStatus'] = $konten->Status_Render ?: 'UNKNOWN';
                        $item['metadata'] = array_merge($item['metadata'], [
                            'typeFile' => $konten->Berkas_Type_File,
                            'fileExists' => $exists,
                            'renderStatus' => $konten->Status_Render ?: 'UNKNOWN',
                            'totalPages' => (int) ($konten->Total_Halaman ?? 0),
                            'renderError' => $konten->Error_Message,
                        ]);
                    } else {
                        // Non-PDF (gambar/dll): butuh metadata penuh untuk thumbnail/preview.
                        $fileMetadata = $gcsService->getFileMetadata($konten->File_Path);

                        $item['fileUrl'] = $fileMetadata['url'] ?? null;
                        $item['thumbnailUrl'] = $fileMetadata['thumbnail_url'] ?? null;
                        $item['previewUrl'] = $item['thumbnailUrl'];

                        $item['metadata'] = array_merge($item['metadata'], [
                            'typeFile' => $konten->Berkas_Type_File ?? ($fileMetadata['mime_type'] ?? null),
                            'fileName' => $fileMetadata['file_name'] ?? null,
                            'extension' => $fileMetadata['extension'] ?? null,
                            'sizeBytes' => $fileMetadata['size_bytes'] ?? null,
                            'sizeHuman' => $fileMetadata['size_human'] ?? null,
                            'lastModifiedAt' => $fileMetadata['last_modified_at'] ?? null,
                        ]);
                    }
                }

                return $item;
            });

            return ResponseHelper::success(['data' => $result], 'Konten berhasil diambil', 200);
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error getKontenByKategori: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil konten', 500);
        }
    }

    /**
     * Lazy-load metadata penuh sebuah berkas konten (url/size/mime) saat dibutuhkan,
     * mis. ketika admin membuka panel detail PDF. Memisahkan biaya GCS metadata
     * (4 round-trip) dari list konten yang harus ringan.
     */
    public function getKontenBerkasMetadata($id)
    {
        try {
            $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

            $berkas = DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten', $realId)
                ->first();

            if (! $berkas || ! $berkas->File_Path) {
                return ResponseHelper::error('Berkas tidak ditemukan', 404);
            }

            $meta = (new GcsService)->getFileMetadata($berkas->File_Path);

            return ResponseHelper::success([
                'fileUrl' => $meta['url'] ?? null,
                'previewUrl' => $meta['url'] ?? null,
                'metadata' => [
                    'typeFile' => $berkas->Type_File ?? ($meta['mime_type'] ?? null),
                    'fileName' => $meta['file_name'] ?? null,
                    'extension' => $meta['extension'] ?? null,
                    'sizeBytes' => $meta['size_bytes'] ?? null,
                    'sizeHuman' => $meta['size_human'] ?? null,
                    'lastModifiedAt' => $meta['last_modified_at'] ?? null,
                ],
            ], 'Metadata berkas berhasil diambil', 200);
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error getKontenBerkasMetadata: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil metadata berkas', 500);
        }
    }

    /**
     * URL unduh sebuah berkas konten.
     *
     * Dokumen Office tidak punya penyaji di aplikasi ini — reader kita merender
     * PDF menjadi gambar per halaman dan tak mengerti format lain. Satu-satunya
     * cara "membuka" docx/xlsx/pptx karena itu adalah mengunduhnya, dan URL-nya
     * ditandatangani dengan paksaan attachment + octet-stream supaya tak ada
     * peramban yang tergoda merendernya di dalam tab.
     *
     * Berkas yang belum lolos verifikasi tidak dilayani: objeknya memang masih
     * di karantina dan tak ada di bucket penyajian.
     */
    public function unduhBerkas($id)
    {
        try {
            $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

            $berkas = DB::table('N_LMS_Master_Konten_Berkas as b')
                ->join('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'b.Id_Master_Konten')
                ->where('b.Id_Master_Konten', $realId)
                ->where('k.Scope', $this->currentScope())
                ->first(['b.File_Path', 'b.Ext', 'b.Status_Verifikasi', 'k.Judul_Konten']);

            if (! $berkas || ! $berkas->File_Path) {
                return ResponseHelper::error('Berkas tidak ditemukan', 404);
            }

            if (! in_array((string) $berkas->Status_Verifikasi, ['SIAP', 'LAMA'], true)) {
                return ResponseHelper::error('Berkas belum lolos verifikasi sehingga belum bisa diunduh.', 422);
            }

            $ext = strtolower((string) ($berkas->Ext ?: pathinfo($berkas->File_Path, PATHINFO_EXTENSION)));
            $namaUnduh = trim(preg_replace('/\s+/', ' ', (string) $berkas->Judul_Konten));
            $namaUnduh = ($namaUnduh !== '' ? $namaUnduh : 'konten').($ext !== '' ? '.'.$ext : '');

            // filename* (RFC 5987) menjaga nama non-ASCII; filename= untuk peramban lama.
            $disposition = sprintf(
                'attachment; filename="%s"; filename*=UTF-8\'\'%s',
                preg_replace('/[^\x20-\x7E]/', '_', str_replace('"', '', $namaUnduh)),
                rawurlencode($namaUnduh),
            );

            $url = Storage::disk(KontenVerifierService::DISK_SAJI)->temporaryUrl(
                $berkas->File_Path,
                now()->addMinutes(15),
                [
                    // Nilai query dirangkai apa adanya oleh SigningHelper, jadi
                    // encoding-nya harus dikerjakan di sini — kalau tidak, yang
                    // ditandatangani berbeda dari yang dikirim peramban.
                    'responseDisposition' => rawurlencode($disposition),
                    'responseType' => 'application/octet-stream',
                ],
            );

            return ResponseHelper::success([
                'url' => $url,
                'fileName' => $namaUnduh,
            ], 'URL unduh berhasil dibuat', 200);
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error unduhBerkas: '.$e->getMessage());

            return ResponseHelper::error('Gagal membuat URL unduh', 500);
        }
    }

    /**
     * Proxy thumbnail Bunny
     */
    public function getBunnyThumbnail(Request $request, $videoId, $fileName)
    {
        $libraryId = (string) config('services.lms.bunny.library_id', '');
        $configuredHost = trim((string) config('services.lms.bunny.cdn_host', ''));
        $defaultHost = "vz-{$libraryId}.b-cdn.net";

        $cdnHosts = array_values(array_filter(array_unique([$configuredHost, $defaultHost])));

        $safeVideoId = trim((string) $videoId, '/');
        $safeFileName = trim((string) $fileName, '/');

        $dynamicReferer = $request->getScheme().'://'.$request->getHost();

        $triedUrls = [];
        $candidateFileNames = [$safeFileName];

        try {
            $bunnyService = new BunnyStreamService;
            $videoMetadata = $bunnyService->getVideoMetadata($safeVideoId);
            $actualFileName = trim((string) data_get($videoMetadata, 'thumbnail_file_name', ''), '/');
            if ($actualFileName !== '' && ! in_array($actualFileName, $candidateFileNames, true)) {
                $candidateFileNames[] = $actualFileName;
            }
        } catch (Throwable $e) {
        }

        foreach ($candidateFileNames as $candidateFileName) {
            $encodedFileName = implode('/', array_map('rawurlencode', explode('/', $candidateFileName)));

            foreach ($cdnHosts as $cdnHost) {
                $cdnUrl = "https://{$cdnHost}/{$safeVideoId}/{$encodedFileName}";
                $triedUrls[] = $cdnUrl;

                $response = Http::withHeaders([
                    'Referer' => $dynamicReferer,
                    'Accept' => 'image/*,*/*',
                ])->get($cdnUrl);

                if ($response->successful()) {
                    $contentType = $response->header('Content-Type', 'image/jpeg');

                    return response($response->body())
                        ->header('Content-Type', $contentType)
                        ->header('Cache-Control', 'public, max-age=86400');
                }
            }
        }

        return ResponseHelper::error('Image not found at BunnyCDN', 404);
    }

    // Metode FE -> SERVER -> BUNNY
    // public function uploadKonten(Request $request)
    // {
    //     $log = $this->lmsLogger();
    //     $traceId = (string) Str::uuid();

    //     // Decode category ID if hashed
    //     $payload = $request->all();
    //     if (isset($payload['id_kategori_konten'])) {
    //         $payload['id_kategori_konten'] = Hashids::connection('custom')->decode($payload['id_kategori_konten'])[0] ?? (int) $payload['id_kategori_konten'];
    //     }
    //     $request->replace($payload);

    //     $request->validate([
    //         "judul_content" => "required|string|max:255", // Fixed field name from frontend if needed
    //         "id_kategori_konten" => "required|integer",
    //         "tipe_konten" => "required|string|in:video,pdf,image,document",
    //         "file" => "required|file|max:204800", // max 200MB
    //     ]);

    //     $judulKonten = $request->input("judul_content") ?: $request->input("judul_konten");

    //     DB::beginTransaction();
    //     try {
    //         $isVideo = $request->tipe_konten === "video";
    //         $flagThirdParty = $isVideo ? "Y" : "T";

    //         // 1. Insert ke N_LMS_Master_Konten
    //         $kontenId = DB::table("N_LMS_Master_Konten")->insertGetId([
    //             "Id_Kategori_Konten" => $request->id_kategori_konten,
    //             "Judul_Konten" => $judulKonten,
    //             "Tipe_Konten" => $request->tipe_konten,
    //             "Tanggal" => now()->format("Y-m-d"),
    //             "Jam" => now()->format("H:i:s"),
    //             "Flag_Aktif" => "Y",
    //             "Flag_Third_Party" => $flagThirdParty,
    //         ]);

    //         if ($isVideo) {
    //             $bunnyService = new BunnyStreamService();
    //             $uploadRequest = new Request();
    //             $uploadRequest->merge([
    //                 "title" => $judulKonten,
    //                 "trace_id" => $traceId,
    //             ]);
    //             $uploadRequest->files->set("video", $request->file("file"));

    //             $bunnyResult = $bunnyService->uploadVideo($uploadRequest);
    //             $bunnyData = json_decode($bunnyResult->getContent(), true);

    //             if (!isset($bunnyData["success"]) || !$bunnyData["success"]) {
    //                 throw new Exception("Gagal upload video ke Bunny: " . ($bunnyData["message"] ?? "Unknown error"));
    //             }

    //             $mediaKey = str_replace("-", "", Str::uuid()->toString());

    //             DB::table("N_LMS_Master_Konten_Media")->insert([
    //                 "Id_Master_Konten" => $kontenId,
    //                 "Media_Key" => $mediaKey,
    //                 "Type_File" => $request->file("file")->getClientMimeType(),
    //                 "Provider_Media_Id" => $bunnyData["video_id"],
    //                 "Storage_Provider" => "bunny",
    //             ]);
    //         } else {
    //             $gcsService = new GcsService();
    //             $uploadResult = $gcsService->uploadFile(
    //                 $request->file("file"),
    //                 (string) $request->id_kategori_konten,
    //             );

    //             DB::table("N_LMS_Master_Konten_Berkas")->insert([
    //                 "Id_Master_Konten" => $kontenId,
    //                 "Media_Key" => $uploadResult["media_key"],
    //                 "Type_File" => $uploadResult["type_file"],
    //                 "File_Path" => $uploadResult["file_path"],
    //             ]);
    //         }

    //         DB::commit();

    //         return ResponseHelper::success(
    //             [
    //                 "message" => "Konten berhasil diupload",
    //                 "konten_id" => Hashids::connection('custom')->encode($kontenId),
    //             ],
    //             "Konten berhasil diupload",
    //             201,
    //         );
    //     } catch (Throwable $e) {
    //         DB::rollBack();
    //         Log::error("Upload Konten Error: " . $e->getMessage());
    //         return ResponseHelper::error("Gagal mengupload konten: " . $e->getMessage(), 500);
    //     }
    // }

    /**
     * TAHAP 1: Minta Tiket (Bunny Signature / GCS Signed URL)
     * Request ini dipanggil untuk SETIAP file dalam antrean Vue
     */
    public function getUploadTicket(Request $request)
    {
        $log = $this->lmsLogger();

        // 1. Decode ID Kategori
        $payload = $request->all();
        if (isset($payload['id_kategori_konten'])) {
            $payload['id_kategori_konten'] =
                Hashids::connection('custom')->decode($payload['id_kategori_konten'])[0] ??
                (int) $payload['id_kategori_konten'];
            $request->replace($payload);
        }

        $validated = $request->validate([
            'judul_content' => 'required|string|max:255', // Ini adalah Custom Name dari Admin
            'file_name' => 'required|string|max:255',
            'id_kategori_konten' => 'required|integer',
            'size' => 'nullable|integer|min:1',
        ]);

        /*
         * `tipe_konten` dan `mime_type` SENGAJA tidak lagi diterima dari klien.
         *
         * Sebelumnya keduanya dipakai apa adanya: untuk menyusun path, sebagai
         * contentType signed URL, dan akhirnya tersimpan sebagai Type_File —
         * kolom yang jadi dasar gerbang "item DOKUMEN wajib PDF" di Builder.
         * Artinya satu field JSON cukup untuk menembus gerbang itu.
         *
         * Sekarang server menurunkan semuanya dari ekstensi, dan ekstensi itu
         * sendiri dibuktikan terhadap isi berkas saat finalize.
         */
        $scope = $this->currentScope();
        $ext = KontenPolicy::normalisasiExt($validated['file_name']);
        $p = KontenPolicy::untukExt($scope, $ext);

        if (! $p) {
            return ResponseHelper::error(KontenPolicy::pesanTolak($ext, $scope), 422);
        }

        // Ukuran yang DISEBUT klien — dipakai menolak lebih awal supaya orang tak
        // menunggu transfer 2 GB selesai baru ditolak. Angka sebenarnya diperiksa
        // ulang dari metadata objek saat finalize, jadi klaim ini boleh bohong.
        $ukuranKlaim = (int) ($validated['size'] ?? 0);
        if ($ukuranKlaim > 0 && $ukuranKlaim > $p['maks']) {
            return ResponseHelper::error(sprintf(
                'Ukuran berkas melebihi batas %d MB untuk %s.',
                (int) round($p['maks'] / 1048576),
                $p['label'],
            ), 422);
        }

        $ticketKey = str_replace('-', '', Str::uuid()->toString());
        $kategoriId = (int) $validated['id_kategori_konten'];

        // --- JALUR VIDEO (BUNNY) ---
        if ($p['storage'] === 'bunny') {
            $libraryId = config('services.lms.bunny.library_id');
            $apiKey = config('services.lms.bunny.api_key');
            $collectionId = config('services.lms.bunny.collection_id');

            /*
             * Kredensial diperiksa SEBELUM memanggil Bunny.
             *
             * Tanpa ini, api_key yang null dikirim sebagai header "AccessKey:
             * null", Bunny membalas 401, dan pengguna menerima "Gagal membuat
             * objek video di Bunny" — pesan yang menuduh Bunny padahal
             * environment kitalah yang belum diisi. Membedakan keduanya adalah
             * selisih antara perbaikan satu baris .env dan setengah jam menebak.
             *
             * Nilai kunci TIDAK pernah dicatat; hanya keberadaannya.
             */
            if (blank($libraryId) || blank($apiKey)) {
                Log::error('bunny.config.invalid', [
                    'sumber' => 'ContentManagementController@'.__FUNCTION__,
                    'library_id_present' => ! blank($libraryId),
                    'api_key_present' => ! blank($apiKey),
                ]);

                return ResponseHelper::error(
                    'Konfigurasi Bunny tidak lengkap (library_id/api_key). Hubungi admin sistem.',
                    500
                );
            }

            // Buat video dengan Judul Kustom dari Admin
            $createResponse = Http::withHeaders([
                'AccessKey' => $apiKey,
                'Content-Type' => 'application/json',
            ])->post("https://video.bunnycdn.com/library/{$libraryId}/videos", [
                'title' => $validated['judul_content'],
                'collectionId' => $collectionId,
            ]);

            if (! $createResponse->successful()) {
                /* Balasan penyedia DICATAT, bukan ditelan. Kegagalan pihak
                   ketiga tanpa status dan tanpa badan balasan memaksa orang
                   menebak antara kunci salah, library salah, kuota habis, dan
                   jaringan — empat sebab dengan satu pesan yang sama. */
                Log::error('bunny.video.create.failed', [
                    'sumber' => 'ContentManagementController@'.__FUNCTION__,
                    'status' => $createResponse->status(),
                    'body' => mb_substr($createResponse->body(), 0, 500),
                    'library_id' => $libraryId,
                ]);

                return ResponseHelper::error(
                    'Gagal membuat objek video di Bunny ('.$createResponse->status().').',
                    500
                );
            }

            $videoId = $createResponse->json('guid');
            $expirationTime = time() + 3600;
            $signature = hash('sha256', $libraryId.$apiKey.$expirationTime.$videoId);

            // guid disimpan di tiket, bukan dikembalikan lalu dipercaya lagi saat
            // finalize: tanpa ini klien bisa menautkan video Bunny milik orang lain.
            $this->simpanTiket($ticketKey, $scope, $kategoriId, 'bunny', null, $videoId, $p, $ukuranKlaim);

            return response()->json([
                'success' => true,
                'storage_provider' => 'bunny',
                'ticket_key' => $ticketKey,
                'video_id' => $videoId,
                'library_id' => $libraryId,
                'expiration_time' => $expirationTime,
                'signature' => $signature,
            ]);
        }

        // --- JALUR BERKAS (GCS) ---
        $safeFileName = Str::slug(pathinfo($validated['file_name'], PATHINFO_FILENAME)) ?: 'berkas';

        /*
         * Awalan mengikuti scope. Sebelumnya "lms/konten/" ditulis mati — benar
         * sewaktu Library hanya milik LMS, tapi sejak SOP memakai controller yang
         * sama lewat ->defaults('scope','SOP'), seluruh berkas SOP ikut mendarat
         * di bawah lms/ baik di bucket karantina maupun bucket penyajian.
         *
         * Objek LAMA tidak dipindahkan, dan itu disengaja: File_Path disimpan
         * per baris, jadi yang sudah ada tetap terbaca di tempatnya. Memindahkan
         * objek produksi demi kerapian penataan berarti mempertaruhkan konten
         * yang sedang dipakai untuk sesuatu yang tak dilihat pengguna.
         */
        $prefixScope = strtoupper($scope) === 'SOP' ? 'sop/konten' : 'lms/konten';
        $gcsPath = "{$prefixScope}/{$kategoriId}/".Str::uuid()."-{$safeFileName}.{$ext}";

        /*
         * x-goog-content-length-range ikut DITANDATANGANI, sehingga GCS sendiri
         * menolak PUT yang ukurannya di luar rentang — sebelum byte-nya terkirim
         * habis, bukan sesudah. Konsekuensinya browser WAJIB mengirim header ini
         * (dan contentType) persis seperti yang dikembalikan di sini; berbeda
         * sedikit saja, tanda tangannya tidak cocok dan GCS membalas 403.
         */
        $rentangUkuran = '0,'.$p['maks'];
        // Mendarat di bucket KARANTINA, bukan bucket penyajian. Objeknya baru
        // dipindahkan setelah verifikasi struktur lolos; sebelum itu tak ada satu
        // pun jalur di aplikasi ini yang bisa menjangkaunya.
        $signedUrl = Storage::disk(KontenVerifierService::DISK_KARANTINA)->temporaryUrl($gcsPath, now()->addMinutes(60), [
            'method' => 'PUT',
            'contentType' => $p['mime'], // dari kebijakan server, bukan dari klien
            'headers' => ['x-goog-content-length-range' => $rentangUkuran],
        ]);

        $this->simpanTiket($ticketKey, $scope, $kategoriId, 'gcs', $gcsPath, null, $p, $ukuranKlaim);

        return response()->json([
            'success' => true,
            'storage_provider' => 'gcs',
            'ticket_key' => $ticketKey,
            'signed_url' => $signedUrl,
            // Yang WAJIB dikirim browser apa adanya saat PUT.
            'content_type' => $p['mime'],
            'headers' => ['x-goog-content-length-range' => $rentangUkuran],
            'maks_byte' => $p['maks'],
            'tipe_konten' => $p['tipe'],
        ]);
    }

    /**
     * Catat tiket unggah. Baris inilah satu-satunya sumber kebenaran tentang
     * berkas yang sedang diunggah; finalize membacanya alih-alih memercayai
     * apa pun yang dikirim ulang oleh browser.
     */
    private function simpanTiket(
        string $ticketKey,
        string $scope,
        int $kategoriId,
        string $storage,
        ?string $gcsPath,
        ?string $providerMediaId,
        array $p,
        int $ukuranKlaim,
    ): void {
        DB::table('N_LMS_Upload_Ticket')->insert([
            'Ticket_Key' => $ticketKey,
            'Scope' => $scope,
            'Id_Kategori_Konten' => $kategoriId,
            'Storage' => $storage,
            'Gcs_Path' => $gcsPath,
            'Provider_Media_Id' => $providerMediaId,
            'Ext' => $p['ext'],
            'Mime' => $p['mime'],
            'Maks_Byte' => $p['maks'],
            'Ukuran_Klaim' => $ukuranKlaim ?: null,
            // Kepemilikan dikunci ke Id_Users: Kode_Karyawan bisa NULL untuk akun
            // tanpa data karyawan, dan pemeriksaan yang melewati NULL sama saja
            // dengan tidak ada pemeriksaan.
            'Id_Users' => Auth::id(),
            'Kode_Karyawan' => $this->requestedByKode(),
            'Expired_At' => now()->addMinutes(60), // sama dengan masa berlaku signed URL
            'Created_At' => now(),
        ]);
    }

    /**
     * TAHAP 1.5: Fungsi Pendukung Batal (WAJIB ADA)
     * Menghapus cangkang video di Bunny jika user klik 'X' di widget
     */
    public function deleteUploadTicket(Request $request)
    {
        /*
         * Dulu endpoint ini menerima `video_id` apa pun dan langsung
         * menghapusnya dari library Bunny — tanpa memeriksa siapa yang meminta
         * atau apakah video itu memang miliknya. Karena library Bunny dipakai
         * BERSAMA LMS dan SOP, siapa pun yang punya akses Library bisa
         * menghapus video mana saja milik modul lain hanya dengan menebak guid.
         *
         * Sekarang yang diterima adalah kunci tiket: guid-nya dibaca dari baris
         * tiket yang server tulis sendiri, dan tiketnya harus milik pemanggil
         * serta berada di scope yang sama.
         */
        $validated = $request->validate(['ticket_key' => 'required|string|size:32']);

        $tiket = DB::table('N_LMS_Upload_Ticket')
            ->where('Ticket_Key', $validated['ticket_key'])
            ->first();

        if (! $tiket) {
            return ResponseHelper::error('Tiket unggah tidak dikenal.', 422);
        }
        if (strcasecmp((string) $tiket->Scope, $this->currentScope()) !== 0) {
            return ResponseHelper::error('Tiket unggah bukan milik library ini.', 403);
        }
        if ((int) $tiket->Id_Users !== (int) Auth::id()) {
            return ResponseHelper::error('Tiket unggah bukan milik Anda.', 403);
        }
        if ($tiket->Storage !== 'bunny' || empty($tiket->Provider_Media_Id)) {
            return ResponseHelper::error('Tiket ini bukan unggahan video.', 422);
        }
        if ($tiket->Used_At !== null) {
            // Tiket yang sudah difinalisasi berarti videonya kini konten sungguhan.
            // Membatalkannya lewat jalur ini akan menghapus media milik konten
            // yang sudah tercatat — penghapusan konten punya jalurnya sendiri.
            return ResponseHelper::error('Unggahan ini sudah selesai; batalkan lewat hapus konten.', 409);
        }

        $libraryId = config('services.lms.bunny.library_id');

        $response = Http::withHeaders([
            'AccessKey' => config('services.lms.bunny.api_key'),
            'Accept' => 'application/json',
        ])->delete("https://video.bunnycdn.com/library/{$libraryId}/videos/{$tiket->Provider_Media_Id}");

        // Tiket ditutup apa pun hasil panggilan ke Bunny: ia tak boleh dipakai
        // lagi untuk finalize setelah pembatalan diminta.
        DB::table('N_LMS_Upload_Ticket')
            ->where('Id_Upload_Ticket', $tiket->Id_Upload_Ticket)
            ->update(['Used_At' => now()]);

        return response()->json(['success' => $response->successful()]);
    }

    /**
     * TAHAP 2: Catat ke Database
     * Fungsi ini akan dipanggil otomatis oleh Vue setiap kali 1 file sukses upload
     */
    /** Kode_Karyawan admin saat ini (untuk Requested_By task). */
    private function requestedByKode(): ?string
    {
        return optional(optional(Auth::user())->karyawan)->Kode_Karyawan;
    }

    /**
     * Mulai task upload async → catat baris di N_HRIS_Async_Tasks (Task_Type=
     * 'upload') agar progres muncul di Pusat Aktivitas sejak transfer byte mulai.
     * Dipanggil browser SEBELUM transfer byte (TUS/GCS). Kembalikan export_id.
     */
    public function startUploadTask(Request $request, UploadTaskService $uploads)
    {
        $validated = $request->validate([
            'judul_content' => 'required|string|max:255',
            'file_name' => 'required|string|max:255',
            'size' => 'nullable|integer',
        ]);

        // Format & tipe diturunkan dari ekstensi lewat kebijakan yang sama dengan
        // penerbitan tiket, supaya label di Pusat Aktivitas tak pernah berbeda
        // dari apa yang sebenarnya tersimpan.
        $ext = KontenPolicy::normalisasiExt($validated['file_name']);
        $p = KontenPolicy::untukExt($this->currentScope(), $ext);
        $tipe = $p['tipe'] ?? 'document';
        $format = $tipe === 'video' ? 'video' : ($ext === 'pdf' ? 'pdf' : 'document');

        $exportId = $uploads->startUpload([
            'judul' => $validated['judul_content'],
            'tipe_konten' => $tipe,
            'mime' => $p['mime'] ?? null,
            'format' => $format,
            'size' => $validated['size'] ?? null,
            'scope' => $this->currentScope(),
        ], $this->requestedByKode());

        return ResponseHelper::success(['export_id' => $exportId], 'Task upload dibuat', 201);
    }

    /** Heartbeat progres transfer byte dari browser. */
    public function progressUploadTask(Request $request, UploadTaskService $uploads, string $exportId)
    {
        $percent = (int) $request->input('percent', 0);
        $uploads->updateProgress($exportId, $percent);

        return ResponseHelper::success(null, 'OK');
    }

    /**
     * Tandai task upload gagal saat transfer byte putus / gagal SEBELUM finalize
     * (mis. gagal ambil tiket, error TUS/GCS). Dipanggil browser agar task tidak
     * menggantung 'processing' di Pusat Aktivitas.
     */
    public function failUploadTask(Request $request, UploadTaskService $uploads, string $exportId)
    {
        $message = (string) $request->input('message', 'Upload gagal.');
        $uploads->markFailed($exportId, $message);

        return ResponseHelper::success(null, 'OK');
    }

    public function uploadKonten(Request $request)
    {
        $log = $this->lmsLogger();

        // Dekode hashid id_kategori_konten dihapus dari sini: kategorinya kini
        // dibaca dari baris tiket, jadi klien tak mengirimkannya lagi.

        /*
         * Kontraknya kini cuma DUA hal: kunci tiket dan judul.
         *
         * `gcs_path`, `mime_type`, `tipe_konten`, dan `provider_media_id` dulu
         * datang dari klien. `gcs_path` yang paling berbahaya: ia divalidasi
         * hanya sebagai `string` lalu disimpan apa adanya sebagai File_Path,
         * tanpa dibandingkan dengan tiket mana pun dan tanpa diperiksa
         * keberadaannya — jadi klien bisa menautkan objek mana saja di bucket
         * ke baris kontennya sendiri. Semua nilai itu sekarang dibaca dari
         * baris tiket yang ditulis server.
         */
        $validated = $request->validate([
            'judul_content' => 'required|string|max:255',
            'ticket_key' => 'required|string|size:32',
            'export_id' => 'nullable|string|max:64',
        ]);

        $exportIdAwal = $validated['export_id'] ?? null;
        $klaim = $this->klaimTiket($validated['ticket_key'], $this->currentScope());

        if ($klaim['error'] !== null) {
            if ($exportIdAwal) {
                app(UploadTaskService::class)->markFailed((string) $exportIdAwal, $klaim['error']);
            }

            return ResponseHelper::error($klaim['error'], $klaim['code']);
        }

        $tiket = $klaim['tiket'];

        $isVideo = $tiket->Storage === 'bunny';
        $verifier = app(KontenVerifierService::class);

        /*
         * Pembuktian isi berkas dilakukan SEBELUM transaksi dibuka: ia menyentuh
         * jaringan (metadata + 4 KB pertama dari GCS) dan tak boleh menahan lock
         * selama round-trip. Berkas penuh tidak pernah dibaca di sini.
         */
        $hasil = ['ukuran' => null, 'mime' => $tiket->Mime];
        if (! $isVideo) {
            $hasil = $verifier->verifikasiCepat($tiket->Gcs_Path, $tiket->Ext, KontenVerifierService::DISK_KARANTINA, (string) $tiket->Scope);

            if (! $hasil['ok']) {
                // Berkas yang gagal verifikasi tidak disimpan sebagai barang bukti;
                // menyimpannya berarti memelihara koleksi berkas berbahaya di
                // bucket produksi. Yang dicatat hanya alasannya.
                $verifier->hapusObjek($tiket->Gcs_Path, KontenVerifierService::DISK_KARANTINA);
                $log->warning('Konten ditolak verifikasi cepat', [
                    'path' => $tiket->Gcs_Path,
                    'ext' => $tiket->Ext,
                    'alasan' => $hasil['alasan'],
                    'oleh' => $this->requestedByKode(),
                ]);

                if ($exportIdAwal) {
                    app(UploadTaskService::class)->markFailed((string) $exportIdAwal, $hasil['alasan']);
                }

                return ResponseHelper::error($hasil['alasan'], 422);
            }
        }

        DB::beginTransaction();
        try {

            // Id berkas yang perlu dibuatkan Cloud Task VERIFIKASI. Cloud Task
            // DIBUAT SETELAH commit (bukan di dalam transaksi) → lihat di bawah.
            // null = tidak perlu (jalur video Bunny).
            $dispatchVerifikasiBerkasId = null;

            // Simpan data utama (Judul Kustom Admin masuk ke sini)
            $kontenId = DB::table('N_LMS_Master_Konten')->insertGetId([
                'Id_Kategori_Konten' => (int) $tiket->Id_Kategori_Konten,
                'Judul_Konten' => $validated['judul_content'],
                // Diturunkan dari ekstensi lewat KontenPolicy: 'document' | 'image' | 'video'.
                'Tipe_Konten' => KontenPolicy::untukExt($tiket->Scope, $tiket->Ext)['tipe'],
                'Tanggal' => now()->format('Y-m-d'),
                'Jam' => now()->format('H:i:s'),
                'Flag_Aktif' => 'Y',
                'Flag_Third_Party' => $isVideo ? 'Y' : 'T',
                'Scope' => $tiket->Scope,
            ]);

            if ($isVideo) {
                DB::table('N_LMS_Master_Konten_Media')->insert([
                    'Id_Master_Konten' => $kontenId,
                    'Media_Key' => str_replace('-', '', Str::uuid()->toString()),
                    'Type_File' => $tiket->Mime,
                    'Provider_Media_Id' => $tiket->Provider_Media_Id,
                    'Storage_Provider' => 'bunny',
                ]);
            } else {
                $berkasId = DB::table('N_LMS_Master_Konten_Berkas')->insertGetId([
                    'Id_Master_Konten' => $kontenId,
                    'Media_Key' => str_replace('-', '', Str::uuid()->toString()),
                    // Type_File kini berasal dari kebijakan server yang sudah
                    // dicocokkan dengan byte awal berkas — bukan dari klaim klien.
                    // Gerbang "item DOKUMEN wajib PDF" di Builder bersandar pada
                    // kolom ini, jadi di sinilah jaminannya benar-benar dibuat.
                    'Type_File' => $hasil['mime'],
                    'File_Path' => $tiket->Gcs_Path,
                    'Ukuran_Byte' => $hasil['ukuran'],
                    'Ext' => $tiket->Ext,
                    // Byte awalnya sudah cocok, tapi struktur dalamnya belum
                    // diperiksa — dan sampai itu terjadi konten ini tidak boleh
                    // dipilih di Builder.
                    'Status_Verifikasi' => 'MENUNGGU',
                    'Status_Render' => 'PENDING',
                ]);

                // Cloud Task (panggilan gRPC ke GCP) TIDAK dijalankan di dalam
                // transaksi: ia menahan lock selama round-trip jaringan, dan bila
                // commit gagal setelahnya akan jadi phantom-task. Cukup tandai;
                // dibuat SETELAH commit (lihat bawah).
                $dispatchVerifikasiBerkasId = $berkasId;
            }

            DB::commit();

            /*
             * Cloud Task VERIFIKASI dibuat SETELAH commit: barisnya dipastikan
             * sudah tersimpan (tak ada phantom-task), dan createTask (gRPC) tak
             * lagi menahan transaksi.
             *
             * Render PDF TIDAK lagi di-dispatch dari sini. Berkasnya masih di
             * bucket karantina, sedangkan mesin render membaca dari bucket
             * penyajian — jadi render harus menunggu promosi. Yang men-dispatch
             * render sekarang adalah webhook verifikasi, setelah objeknya pindah.
             *
             * Bila dispatch gagal, konten tetap 'MENUNGGU' — tak bisa dipakai,
             * tapi juga tak hilang: konten:verifikasi-ulang dapat memungutnya.
             */
            if ($dispatchVerifikasiBerkasId !== null) {
                try {
                    $cloudTask = new \App\Services\SYSTEM\TaskProcessing\CloudTaskService;
                    $queueName = config('services.project_config.gcp.queue_pdf', 'lms-pdf-queue');

                    $cloudTask->dispatch(
                        url: $this->resolveCloudTaskExecuteUrl('/api/v1/tasks/konten/verifikasi'),
                        queue: $queueName,
                        payload: ['berkas_id' => $dispatchVerifikasiBerkasId],
                        taskType: 'KONTEN_VERIFIKASI',
                        referenceId: $dispatchVerifikasiBerkasId,
                        referenceNo: 'KONTEN-'.$kontenId,
                    );
                } catch (\Throwable $e) {
                    \Log::error('Gagal membuat Cloud Task KONTEN_VERIFIKASI (setelah commit)', [
                        'berkas_id' => $dispatchVerifikasiBerkasId,
                        'konten_id' => $kontenId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $isPdf = ! $isVideo && $tiket->Ext === 'pdf';

            /*
             * Task Pusat Aktivitas TETAP terbuka di sini — untuk semua berkas,
             * bukan cuma PDF. Berkasnya masih di karantina; menutupnya sekarang
             * berarti memberi tahu admin "selesai" untuk konten yang belum tentu
             * boleh dipakai. Yang menutupnya: webhook verifikasi (non-PDF) atau
             * webhook render (PDF). Video Bunny satu-satunya yang langsung tutup.
             */
            if ($exportIdAwal) {
                app(UploadTaskService::class)->markUploaded(
                    (string) $exportIdAwal,
                    $isPdf,
                    $isVideo ? null : ($berkasId ?? null),
                    (int) $tiket->Id_Kategori_Konten,
                    $this->currentScope(),
                );
            }

            return ResponseHelper::success([
                'konten_id' => Hashids::connection('custom')->encode($kontenId),
                'is_pdf' => $isPdf,
                // Berkas apa pun kini melewati verifikasi dulu, jadi 'PENDING'
                // untuk semuanya — bukan hanya PDF seperti sebelumnya.
                'render_status' => $isVideo ? null : 'PENDING',
                'status_verifikasi' => $isVideo ? null : 'MENUNGGU',
            ], 'Berhasil disimpan', 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            $log->error('Simpan Konten DB Error: '.$e->getMessage());

            // Tandai task gagal agar tidak menggantung di Pusat Aktivitas.
            if ($exportIdAwal) {
                app(UploadTaskService::class)->markFailed((string) $exportIdAwal, $e->getMessage());
            }

            return ResponseHelper::error('Gagal simpan ke database', 500);
        }
    }

    /**
     * Klaim tiket unggah — sekali pakai, milik pengunggahnya, belum kedaluwarsa,
     * dan scope-nya cocok dengan halaman yang memanggil.
     *
     * Klaimnya berupa UPDATE bersyarat, bukan "baca lalu tulis": dua permintaan
     * finalize yang tiba bersamaan dengan kunci yang sama hanya menghasilkan
     * satu baris terpengaruh, jadi konten tak pernah tercatat dua kali.
     *
     * Selalu mengembalikan ARRAY berbentuk sama, tidak pernah campur objek dan
     * array: `isset($obj['kunci'])` pada stdClass melempar Error di PHP 8, bukan
     * mengembalikan false, sehingga pemanggil yang membedakan keduanya lewat
     * akses indeks akan meledak alih-alih menangani galatnya.
     *
     * @return array{tiket:?object, error:?string, code:int}
     */
    private function klaimTiket(string $ticketKey, string $scope): array
    {
        $tolak = fn (string $pesan, int $kode) => ['tiket' => null, 'error' => $pesan, 'code' => $kode];

        $tiket = DB::table('N_LMS_Upload_Ticket')->where('Ticket_Key', $ticketKey)->first();

        if (! $tiket) {
            return $tolak('Tiket unggah tidak dikenal. Silakan unggah ulang.', 422);
        }

        // Scope diperiksa supaya admin Library SOP tak bisa menyelesaikan tiket
        // yang diterbitkan halaman LMS, dan sebaliknya.
        if (strcasecmp((string) $tiket->Scope, $scope) !== 0) {
            return $tolak('Tiket unggah bukan milik library ini.', 403);
        }

        if ((int) $tiket->Id_Users !== (int) Auth::id()) {
            return $tolak('Tiket unggah bukan milik Anda.', 403);
        }

        if ($tiket->Used_At !== null) {
            return $tolak('Tiket unggah sudah dipakai.', 409);
        }

        $now = now();
        $terklaim = DB::table('N_LMS_Upload_Ticket')
            ->where('Ticket_Key', $ticketKey)
            ->whereNull('Used_At')
            ->where('Expired_At', '>', $now)
            ->update(['Used_At' => $now]);

        if ($terklaim !== 1) {
            // Gagal setelah lolos pemeriksaan di atas berarti salah satu dari dua
            // hal: tiketnya kedaluwarsa, atau permintaan lain menang balapan.
            // Expired_At datang sebagai string dari query builder — diurai dulu,
            // karena membandingkan string dengan Carbon selalu memberi hasil salah.
            $kedaluwarsa = \Illuminate\Support\Carbon::parse($tiket->Expired_At)->lessThanOrEqualTo($now);

            return $kedaluwarsa
                ? $tolak('Tiket unggah kedaluwarsa. Silakan unggah ulang.', 422)
                : $tolak('Tiket unggah sudah dipakai.', 409);
        }

        return ['tiket' => $tiket, 'error' => null, 'code' => 200];
    }

    /**
     * URL absolut webhook Cloud Task.
     *
     * @param  string  $path  jalur webhook; ada dua sekarang — render PDF dan
     *                        verifikasi konten — sehingga tak lagi ditulis mati.
     */
    private function resolveCloudTaskExecuteUrl(string $path = '/api/v1/tasks/lms/process-pdf'): string
    {
        // Aturannya dipindahkan ke CloudTaskService supaya pemanggil lain —
        // webhook verifikasi salah satunya — tak menulis versinya sendiri.
        return CloudTaskService::absoluteUrl($path);
    }

    /**
     * Render ulang sebuah berkas PDF dari Content Dashboard.
     *
     * Menerima Id_Master_Konten (ter-hash, = item.id di list konten), mencari berkas
     * PDF-nya, lalu re-dispatch Cloud Task render. Aman dipanggil berulang berkat lock
     * anti-spam (Cache db). Tidak menghapus file/record — hanya mengantrikan ulang.
     */
    /**
     * Antrikan ulang verifikasi berkas yang tersangkut.
     *
     * Hanya untuk berkas yang MASIH di karantina (MENUNGGU / TERTUNDA_PEMINDAIAN)
     * — biasanya karena dispatch Cloud Task gagal saat unggah. Berkas DITOLAK
     * tidak bisa diulang: objeknya sudah dihapus, jadi tak ada yang tersisa untuk
     * diperiksa; jalan satu-satunya adalah mengunggah ulang.
     */
    public function verifikasiUlangBerkas($id)
    {
        $kontenId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

        $berkas = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten', $kontenId)
            ->first(['Id_Master_Konten_Berkas', 'Status_Verifikasi']);

        if (! $berkas) {
            return ResponseHelper::error('Berkas konten tidak ditemukan', 404);
        }

        if (! in_array($berkas->Status_Verifikasi, ['MENUNGGU', 'TERTUNDA_PEMINDAIAN'], true)) {
            return $berkas->Status_Verifikasi === 'DITOLAK'
                ? ResponseHelper::error('Berkas ini sudah ditolak dan objeknya dihapus. Unggah ulang berkasnya.', 422)
                : ResponseHelper::error('Berkas ini tidak sedang menunggu verifikasi.', 422);
        }

        $berkasId = (int) $berkas->Id_Master_Konten_Berkas;
        $lockKey = 'konten_verifikasi_'.$berkasId;

        /*
         * add() = "pasang kalau belum ada", dan itu ATOMIK: di SQL Server
         * Laravel menjalankan INSERT ke tabel cache lalu menangkap pelanggaran
         * primary key. Yang kalah balapan dapat false.
         *
         * Bentuk sebelumnya - has() lalu put() - bukan kunci sama sekali,
         * melainkan cek-lalu-tindak dengan DUA lubang:
         *
         *   1. Dua permintaan bersamaan sama-sama melihat "belum terkunci",
         *      lalu KEDUANYA men-dispatch Cloud Task untuk berkas yang sama.
         *   2. Kuncinya baru dipasang SESUDAH dispatch selesai, sehingga
         *      jendela balapannya selebar satu panggilan jaringan ke Google -
         *      ratusan milidetik, bukan mikrodetik.
         *
         * Dilepas lagi di catch supaya dispatch yang gagal tidak mengunci
         * berkasnya selama 300 detik. Itu perilaku lama: dulu kunci memang
         * tidak pernah terpasang kalau dispatch melempar.
         */
        if (! \Illuminate\Support\Facades\Cache::store('database')->add($lockKey, true, 300)) {
            return ResponseHelper::error('Berkas ini baru saja diantrikan. Tunggu sebentar.', 429);
        }

        try {
            (new \App\Services\SYSTEM\TaskProcessing\CloudTaskService)->dispatch(
                url: $this->resolveCloudTaskExecuteUrl('/api/v1/tasks/konten/verifikasi'),
                queue: config('services.project_config.gcp.queue_pdf', 'lms-pdf-queue'),
                payload: ['berkas_id' => $berkasId],
                taskType: 'KONTEN_VERIFIKASI_RETRY',
                referenceId: $berkasId,
                referenceNo: 'KONTEN-'.$kontenId,
            );

            return ResponseHelper::success(['status_verifikasi' => 'MENUNGGU'], 'Berkas diantrikan ulang untuk verifikasi.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::store('database')->forget($lockKey);
            $this->lmsLogger()->error('Verifikasi ulang berkas error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengantrikan verifikasi ulang.', 500);
        }
    }

    public function renderUlangBerkas($id)
    {
        $log = $this->lmsLogger();

        $kontenId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

        $berkas = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten', $kontenId)
            ->first();

        if (! $berkas) {
            return ResponseHelper::error('Berkas konten tidak ditemukan', 404);
        }

        $isPdf = str_contains(strtolower((string) ($berkas->Type_File ?? '')), 'pdf');
        if (! $isPdf) {
            return ResponseHelper::error('Hanya berkas PDF yang bisa dirender ulang', 422);
        }

        $berkasId = (int) $berkas->Id_Master_Konten_Berkas;

        // Lock anti double-dispatch (shared antar instance via database cache).
        $lockKey = 'lms_pdf_converting_'.$berkasId;
        /*
         * add() = "pasang kalau belum ada", dan itu ATOMIK: di SQL Server
         * Laravel menjalankan INSERT ke tabel cache lalu menangkap pelanggaran
         * primary key. Yang kalah balapan dapat false.
         *
         * Bentuk sebelumnya - has() lalu put() - bukan kunci sama sekali,
         * melainkan cek-lalu-tindak dengan DUA lubang:
         *
         *   1. Dua permintaan bersamaan sama-sama melihat "belum terkunci",
         *      lalu KEDUANYA men-dispatch Cloud Task untuk berkas yang sama.
         *   2. Kuncinya baru dipasang SESUDAH dispatch selesai, sehingga
         *      jendela balapannya selebar satu panggilan jaringan ke Google -
         *      ratusan milidetik, bukan mikrodetik.
         *
         * Dilepas lagi di catch supaya dispatch yang gagal tidak mengunci
         * berkasnya selama 300 detik. Itu perilaku lama: dulu kunci memang
         * tidak pernah terpasang kalau dispatch melempar.
         */
        if (! \Illuminate\Support\Facades\Cache::store('database')->add($lockKey, true, 300)) {
            return ResponseHelper::error('Konten ini sedang dalam antrian/proses render.', 429);
        }

        try {
            $cloudTask = new \App\Services\SYSTEM\TaskProcessing\CloudTaskService;
            $queue = config('services.project_config.gcp.queue_pdf', 'lms-pdf-queue');
            $webhookUrl = $this->resolveCloudTaskExecuteUrl();

            $cloudTask->dispatch(
                url: $webhookUrl,
                queue: $queue,
                payload: ['berkas_id' => $berkasId],
                taskType: 'LMS_PDF_RENDER_RETRY',
                referenceId: $berkasId,
                referenceNo: 'KONTEN-'.$kontenId,
            );

            DB::table('N_LMS_Master_Konten_Berkas')
                ->where('Id_Master_Konten_Berkas', $berkasId)
                ->update(['Status_Render' => 'PENDING', 'Error_Message' => null]);

            return ResponseHelper::success(['render_status' => 'PENDING'], 'Konten diantrikan ulang untuk render.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Cache::store('database')->forget($lockKey);
            $log->error('Render ulang berkas error: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengantrikan render ulang.', 500);
        }
    }

    /**
     * Soft delete multiple konten by IDs.
     */
    /**
     * Get content usage tracking - which training and scheduling use this content
     * GET /api/content/{contentId}/usage-tracking
     */
    public function getContentUsage($id)
    {
        try {
            $realId = Hashids::connection('custom')->decode($id)[0] ?? (int) $id;

            // Check if content exists
            $konten = DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $realId)->first();
            if (! $konten) {
                return ResponseHelper::error('Konten tidak ditemukan', 404);
            }

            $usage = $this->buildContentUsageData($realId);

            return ResponseHelper::success(
                [
                    'content' => $usage['content'],
                    'trainings' => $usage['trainings'],
                    'penjadwalan' => $usage['penjadwalan'],
                    'total_trainings' => count($usage['trainings']),
                    'total_penjadwalan' => count($usage['penjadwalan']),
                ],
                'Data usage berhasil diambil',
                200,
            );
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error getContentUsage: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data usage', 500);
        }
    }

    /**
     * Batch usage tracking for multiple content IDs.
     * POST /api/content/usage-tracking-batch
     */
    public function getContentUsageBatch(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
        ]);

        try {
            $rawIds = $request->input('ids', []);
            $realIds = collect($rawIds)
                ->map(function ($id) {
                    return Hashids::connection('custom')->decode($id)[0] ?? (int) $id;
                })
                ->filter(function ($id) {
                    return is_numeric($id) && (int) $id > 0;
                })
                ->unique()
                ->values()
                ->toArray();

            if (empty($realIds)) {
                return ResponseHelper::success(
                    [
                        'per_content' => [],
                        'trainings' => [],
                        'penjadwalan' => [],
                        'total_trainings' => 0,
                        'total_penjadwalan' => 0,
                    ],
                    'Data usage berhasil diambil',
                    200,
                );
            }

            $trainingMap = [];
            $penjadwalanMap = [];
            $perContent = [];

            foreach ($realIds as $realId) {
                $kontenExists = DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $realId)->exists();
                if (! $kontenExists) {
                    continue;
                }

                $usage = $this->buildContentUsageData((int) $realId);

                $perContent[] = [
                    'content' => $usage['content'],
                    'trainings' => $usage['trainings'],
                    'penjadwalan' => $usage['penjadwalan'],
                    'total_trainings' => count($usage['trainings']),
                    'total_penjadwalan' => count($usage['penjadwalan']),
                ];

                foreach ($usage['trainings'] as $training) {
                    $trainingId = (int) ($training['id'] ?? 0);
                    if ($trainingId <= 0) {
                        continue;
                    }

                    if (! isset($trainingMap[$trainingId])) {
                        $trainingMap[$trainingId] = $training;
                        $trainingMap[$trainingId]['modules'] = [];
                    }

                    $trainingMap[$trainingId]['scheduling_count'] = max(
                        (int) ($trainingMap[$trainingId]['scheduling_count'] ?? 0),
                        (int) ($training['scheduling_count'] ?? 0),
                    );

                    $existingModuleIds = collect($trainingMap[$trainingId]['modules'])->pluck('id')->toArray();
                    foreach ($training['modules'] ?? [] as $module) {
                        if (! in_array($module['id'], $existingModuleIds, true)) {
                            $trainingMap[$trainingId]['modules'][] = $module;

                            continue;
                        }

                        foreach ($trainingMap[$trainingId]['modules'] as &$existingModule) {
                            if ((int) $existingModule['id'] !== (int) $module['id']) {
                                continue;
                            }
                            $existingActivityIds = collect($existingModule['activities'] ?? [])
                                ->pluck('id')
                                ->toArray();
                            foreach ($module['activities'] ?? [] as $act) {
                                if (! in_array($act['id'], $existingActivityIds, true)) {
                                    $existingModule['activities'][] = $act;
                                }
                            }
                            break;
                        }
                        unset($existingModule);
                    }
                }

                foreach ($usage['penjadwalan'] as $row) {
                    $assignmentId = (int) ($row['id'] ?? 0);
                    if ($assignmentId <= 0) {
                        continue;
                    }
                    if (! isset($penjadwalanMap[$assignmentId])) {
                        $penjadwalanMap[$assignmentId] = $row;
                    }
                }
            }

            return ResponseHelper::success(
                [
                    'per_content' => array_values($perContent),
                    'trainings' => array_values($trainingMap),
                    'penjadwalan' => array_values($penjadwalanMap),
                    'total_trainings' => count($trainingMap),
                    'total_penjadwalan' => count($penjadwalanMap),
                ],
                'Data usage batch berhasil diambil',
                200,
            );
        } catch (Exception $e) {
            Log::channel('KontenKategoriController')->error('Error getContentUsageBatch: '.$e->getMessage());

            return ResponseHelper::error('Gagal mengambil data usage batch', 500);
        }
    }

    private function buildContentUsageData(int $realId): array
    {
        $content = DB::table('N_LMS_Master_Konten')
            ->where('Id_Master_Konten', $realId)
            ->select('Id_Master_Konten', 'Judul_Konten')
            ->first();

        if (! $content) {
            return [
                'content' => null,
                'trainings' => [],
                'penjadwalan' => [],
            ];
        }

        // Ambil struktur training-module-activity yang benar-benar pakai konten ini
        $usageRows = DB::table('N_LMS_Materi_Det as mdet')
            ->join('N_LMS_Materi_Detail as md', 'mdet.Id_Materi_Detail', '=', 'md.Id_Materi_Detail')
            ->join('N_LMS_Materi as m', 'md.Id_Master_Training', '=', 'm.Id_Materi')
            ->leftJoin('N_LMS_Master_Aktivitas as act', 'mdet.Id_Master_Aktivitas', '=', 'act.Id_Master_Aktivitas')
            ->where('mdet.Id_Master_Konten', $realId)
            ->where('mdet.Flag_Aktif', 'Y')
            ->where('md.Flag_Aktif', 'Y')
            ->where('m.Flag_Aktif', 'Y')
            ->select(
                'm.Id_Materi',
                'm.Judul_Training as Nama_Training',
                'md.Id_Materi_Detail',
                'md.Nama_Sub',
                'md.Urutan_Sub_Training',
                'mdet.Id_Materi_Det',
                'mdet.Keterangan_Level',
                'mdet.Urutan_Materi',
                'act.Nama_Aktivitas',
                'act.Kode_Aktivitas',
            )
            ->orderBy('m.Id_Materi')
            ->orderBy('md.Urutan_Sub_Training')
            ->orderBy('mdet.Urutan_Materi')
            ->get();

        if ($usageRows->isEmpty()) {
            return [
                'content' => [
                    'id' => (int) $content->Id_Master_Konten,
                    'id_hash' => Hashids::connection('custom')->encode((int) $content->Id_Master_Konten),
                    'name' => $content->Judul_Konten,
                ],
                'trainings' => [],
                'penjadwalan' => [],
            ];
        }

        $materiIds = $usageRows->pluck('Id_Materi')->unique()->values()->toArray();

        // Susun nested data training -> modules -> activities
        $trainingMap = [];
        foreach ($usageRows as $row) {
            $trainingId = (int) $row->Id_Materi;
            $moduleId = (int) $row->Id_Materi_Detail;

            if (! isset($trainingMap[$trainingId])) {
                $trainingMap[$trainingId] = [
                    'id' => $trainingId,
                    'id_hash' => Hashids::connection('custom')->encode($trainingId),
                    'name' => $row->Nama_Training,
                    'modules' => [],
                    'scheduling_count' => 0,
                ];
            }

            if (! isset($trainingMap[$trainingId]['modules'][$moduleId])) {
                $trainingMap[$trainingId]['modules'][$moduleId] = [
                    'id' => $moduleId,
                    'name' => $row->Nama_Sub,
                    'activities' => [],
                ];
            }

            $trainingMap[$trainingId]['modules'][$moduleId]['activities'][] = [
                'id' => (int) $row->Id_Materi_Det,
                'name' => $row->Keterangan_Level,
                'type' => $row->Kode_Aktivitas,
                'type_label' => $row->Nama_Aktivitas,
            ];
        }

        // Hitung jumlah penjadwalan aktif per training (sesuai pola controller lain)
        $schedulingCountByTraining = DB::table('N_LMS_Penjadwalan as p')
            ->join('N_LMS_Master_Paket_Detail as mpd', 'p.Id_Master_Paket', '=', 'mpd.Id_Master_Paket')
            ->whereIn('mpd.Id_Master_Training', $materiIds)
            ->select('mpd.Id_Master_Training', DB::raw('COUNT(DISTINCT p.Id_Penjadwalan) as total'))
            ->groupBy('mpd.Id_Master_Training');
        $this->trainingScheduleQueryService->applyActiveFilter($schedulingCountByTraining, 'p');
        $schedulingCountByTraining = $schedulingCountByTraining->get()->keyBy('Id_Master_Training');

        $trainingData = collect($trainingMap)
            ->map(function ($training) use ($schedulingCountByTraining) {
                $trainingId = $training['id'];
                $training['modules'] = array_values($training['modules']);
                $training['scheduling_count'] = (int) ($schedulingCountByTraining[$trainingId]->total ?? 0);

                return $training;
            })
            ->values()
            ->toArray();

        // Get penjadwalan data that reference these trainings
        $penjadwalanList = DB::table('N_LMS_Penjadwalan as p')
            ->join('N_LMS_Master_Paket_Detail as mpd', 'p.Id_Master_Paket', '=', 'mpd.Id_Master_Paket')
            ->leftJoin('N_LMS_Materi as m', 'mpd.Id_Master_Training', '=', 'm.Id_Materi')
            ->leftJoin('Karyawan as k', 'p.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('N_LMS_Penjadwalan_Detail as pdl', 'p.Id_Penjadwalan', '=', 'pdl.Id_Penjadwalan')
            ->leftJoin('N_LMS_Penjadwalan_Det as pdet', 'pdl.Id_Penjadwalan_Detail', '=', 'pdet.Id_Penjadwalan_Detail')
            ->whereIn('mpd.Id_Master_Training', $materiIds)
            ->select(
                'p.Id_Penjadwalan',
                'p.Id_Master_Paket',
                'p.Kode_Karyawan',
                'p.Waktu_Enroll as Tanggal_Mulai',
                'p.Flag_Selesai',
                'm.Id_Materi',
                'm.Judul_Training as Training_Name',
                'k.Nama as Assigned_To_Name',
                DB::raw('MIN(pdet.Waktu_Mulai_Akses) as Jadwal_Mulai_Akses'),
                DB::raw('MAX(pdet.Waktu_Batas_Akses) as Jadwal_Selesai_Akses'),
            )
            ->groupBy(
                'p.Id_Penjadwalan',
                'p.Id_Master_Paket',
                'p.Kode_Karyawan',
                'p.Waktu_Enroll',
                'p.Flag_Selesai',
                'm.Id_Materi',
                'm.Judul_Training',
                'k.Nama',
            )
            ->orderBy('p.Id_Penjadwalan', 'desc');
        $this->trainingScheduleQueryService->applyActiveFilter($penjadwalanList, 'p');
        $penjadwalanList = $penjadwalanList->get();

        $paketIds = $penjadwalanList->pluck('Id_Master_Paket')->filter()->unique()->values()->toArray();
        $participantCountByPaket = [];
        if (! empty($paketIds)) {
            $participantCountByPaket = DB::table('N_LMS_Penjadwalan as p')
                ->whereIn('p.Id_Master_Paket', $paketIds)
                ->select('p.Id_Master_Paket', DB::raw('COUNT(DISTINCT p.Kode_Karyawan) as peserta_count'))
                ->groupBy('p.Id_Master_Paket');
            $this->trainingScheduleQueryService->applyActiveFilter($participantCountByPaket, 'p');
            $participantCountByPaket = $participantCountByPaket->pluck('peserta_count', 'Id_Master_Paket')->toArray();
        }

        $penjadwalanMap = [];
        foreach ($penjadwalanList as $pj) {
            $assignmentId = (int) $pj->Id_Penjadwalan;
            if ($assignmentId <= 0) {
                continue;
            }

            if (! isset($penjadwalanMap[$assignmentId])) {
                $pesertaCount = (int) ($participantCountByPaket[$pj->Id_Master_Paket] ?? 0);
                $penjadwalanMap[$assignmentId] = [
                    'id' => $assignmentId,
                    'id_hash' => Hashids::connection('custom')->encode($assignmentId),
                    'training_id' => (int) $pj->Id_Materi,
                    'training_id_hash' => Hashids::connection('custom')->encode((int) $pj->Id_Materi),
                    'training_name' => $pj->Training_Name,
                    'assigned_to_kode' => $pj->Kode_Karyawan,
                    'assigned_to_name' => $pj->Assigned_To_Name,
                    'peserta_count' => $pesertaCount,
                    'assigned_at' => $pj->Tanggal_Mulai,
                    'jadwal_mulai' => $pj->Jadwal_Mulai_Akses ?: $pj->Tanggal_Mulai,
                    'jadwal_selesai' => $pj->Jadwal_Selesai_Akses,
                    'status' => $pj->Flag_Selesai === 'Y' ? 'Selesai' : 'Aktif',
                ];
            }
        }

        return [
            'content' => [
                'id' => (int) $content->Id_Master_Konten,
                'id_hash' => Hashids::connection('custom')->encode((int) $content->Id_Master_Konten),
                'name' => $content->Judul_Konten,
            ],
            'trainings' => $trainingData,
            'penjadwalan' => array_values($penjadwalanMap),
        ];
    }

    /**
     * Siapa saja yang masih memakai konten-konten ini.
     *
     * Tiga tabel, dan ketiganya harus ikut — bukan hanya yang milik modul yang
     * kebetulan sedang membuka layar. Library konten dipakai bersama LMS dan
     * SOP; penjaga yang hanya melihat satu sisi akan meloloskan penghapusan
     * berkas milik sisi yang lain, dan itu persis bentuk kerusakan yang paling
     * sulit ditelusuri karena gejalanya muncul di modul yang tak melakukan
     * apa-apa.
     *
     * @param  list<int>  $ids
     * @return list<string> keterangan pemakaian, kosong bila tak dipakai
     */
    private function kontenMasihDipakai(array $ids): array
    {
        $pesan = [];

        $versiSop = DB::table('N_SOP_Item_Versi')
            ->whereIn('Id_Master_Konten', $ids)
            ->where('Flag_Aktif', 'Y')
            ->count();
        if ($versiSop > 0) {
            $pesan[] = "{$versiSop} versi dokumen";
        }

        $draftSop = DB::table('N_SOP_Paket_Draft_Item_Versi')
            ->whereIn('Id_Master_Konten', $ids)
            ->count();
        if ($draftSop > 0) {
            $pesan[] = "{$draftSop} draft paket dokumen";
        }

        $materiLms = DB::table('N_LMS_Materi_Det')
            ->whereIn('Id_Master_Konten', $ids)
            ->where('Flag_Aktif', 'Y')
            ->count();
        if ($materiLms > 0) {
            $pesan[] = "{$materiLms} materi LMS";
        }

        return $pesan;
    }

    public function deleteKonten(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'id_kategori_konten' => 'nullable|string',
        ]);

        $ids = array_map(function ($id) {
            return Hashids::connection('custom')->decode($id)[0] ?? (int) $id;
        }, $request->input('ids', []));

        try {
            $query = DB::table('N_LMS_Master_Konten')->whereIn('Id_Master_Konten', $ids)->where('Flag_Aktif', 'Y')->where('Scope', $this->currentScope());

            if ($request->filled('id_kategori_konten')) {
                $realCatId =
                    Hashids::connection('custom')->decode($request->id_kategori_konten)[0] ??
                    (int) $request->id_kategori_konten;
                $query->where('Id_Kategori_Konten', $realCatId);
            }

            /* ID YANG BENAR-BENAR BOLEH DISENTUH, dihitung SEBELUM apa pun
               dihapus.

               Sebelumnya soft delete memakai $query yang tersaring Scope,
               sementara penghapusan berkas fisik memakai $ids MENTAH dari
               request. Karena berkasnya dihapus PERMANEN dari GCS/Bunny
               sedangkan barisnya cuma soft delete, memasukkan id konten milik
               LMS lewat endpoint ber-scope SOP tidak menghapus barisnya —
               scope tak cocok — tapi tetap melenyapkan berkasnya. Tak ada
               gejala apa pun di layar; materi pelatihan LMS hanya berubah jadi
               tautan mati. */
            $idsDalamScope = $query->pluck('Id_Master_Konten')->map(fn ($x) => (int) $x)->all();

            if (empty($idsDalamScope)) {
                return ResponseHelper::success(['deleted_count' => 0], 'Tidak ada konten yang dihapus', 200);
            }

            /* KONTEN YANG MASIH DIPAKAI TIDAK BOLEH DIHAPUS.
               Penghapusan berkas di bawah tak bisa dibatalkan, dan versi SOP
               yang sudah terbit adalah catatan yang sudah diteken: membuat
               berkasnya lenyap berarti auditor yang bertanya "apa yang dibaca
               karyawan" dijawab dengan berkas yang tak ada. */
            $pemakai = $this->kontenMasihDipakai($idsDalamScope);
            if ($pemakai !== []) {
                return ResponseHelper::error(
                    'Konten masih dipakai dan tidak bisa dihapus: '.implode('; ', $pemakai)
                    .'. Lepaskan pemakaiannya lebih dulu.',
                    422,
                );
            }

            $deletedCount = DB::table('N_LMS_Master_Konten')
                ->whereIn('Id_Master_Konten', $idsDalamScope)
                ->update([
                    'Flag_Aktif' => 'T',
                ]);

            // Berkas fisik dihapus HANYA untuk konten yang tadi benar-benar
            // dinonaktifkan — bukan untuk seluruh id yang dikirim pemanggil.
            $mediaRecords = DB::table('N_LMS_Master_Konten_Media')->whereIn('Id_Master_Konten', $idsDalamScope)->get();

            $berkasRecords = DB::table('N_LMS_Master_Konten_Berkas')->whereIn('Id_Master_Konten', $idsDalamScope)->get();

            /* Bunny dibangun SAAT DIBUTUHKAN saja. Sebelumnya ia dibangun tanpa
               syarat, dan konstruktornya melempar bila BUNNY api key tak
               dikonfigurasi — sehingga menghapus konten PDF pun gagal dengan
               500 di lingkungan yang memang tak memakai Bunny sama sekali. */
            $bunnyService = null;
            foreach ($mediaRecords as $media) {
                if ($media->Storage_Provider === 'bunny' && $media->Provider_Media_Id) {
                    try {
                        ($bunnyService ??= new BunnyStreamService)->deleteVideo($media->Provider_Media_Id);
                    } catch (Throwable $e) {
                        Log::error(
                            "Gagal menghapus video Bunny ID: {$media->Provider_Media_Id}. Error: ".$e->getMessage(),
                        );
                    }
                }
            }

            foreach ($berkasRecords as $berkas) {
                if ($berkas->File_Path) {
                    try {
                        \Illuminate\Support\Facades\Storage::disk('gcs')->delete($berkas->File_Path);
                    } catch (Throwable $e) {
                        Log::error("Gagal menghapus file GCS Path: {$berkas->File_Path}. Error: ".$e->getMessage());
                    }
                }
            }

            return ResponseHelper::success(['deleted_count' => $deletedCount], 'Konten berhasil dihapus', 200);
        } catch (Throwable $e) {
            return ResponseHelper::galatServer($e, 'Gagal menghapus konten: '.$e->getMessage());
        }
    }
}
