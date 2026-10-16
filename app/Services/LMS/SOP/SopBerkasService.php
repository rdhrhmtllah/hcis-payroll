<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\GCS\GcsService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service Berkas Versi SOP (upload GCS + render PDF).
 * Pola 3-fase (reuse dari LMS KontenKategoriController):
 *   1) uploadTicket()  → signed PUT URL (browser upload langsung)
 *   2) browser PUT ke GCS
 *   3) finalize()      → insert baris + dispatch render (di controller, setelah commit)
 */
class SopBerkasService
{
    private string $table = 'N_SOP_Item_Versi_Berkas';

    /**
     * OFFSET id render agar path GCS SOP tidak tabrakan dengan berkas LMS.
     * SecurePdfPageRenderService menulis ke lms/secure-pages/{berkasId}/...;
     * SOP & LMS punya IDENTITY terpisah (sama-sama mulai 1), jadi tanpa offset
     * bisa saling menimpa. Sisi reader (LEAD) WAJIB pakai offset yang sama.
     */
    public const RENDER_ID_OFFSET = 900000000;

    /** ID yang dipakai saat memanggil render engine (anti-tabrakan). */
    public function renderId(int $sopBerkasId): int
    {
        return self::RENDER_ID_OFFSET + $sopBerkasId;
    }

    /** Bangun path GCS + signed PUT URL (60 mnt). Return ['gcs_path','upload_url']. */
    public function uploadTicket(int $idVersi, string $fileName, string $mime): array
    {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'bin';
        $safe = Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) ?: 'file';
        $gcsPath = "sop/berkas/{$idVersi}/".Str::uuid()."-{$safe}.{$ext}";

        $uploadUrl = Storage::disk('gcs')->temporaryUrl($gcsPath, now()->addMinutes(60), [
            'method' => 'PUT',
            'contentType' => $mime,
        ]);

        return ['gcs_path' => $gcsPath, 'upload_url' => $uploadUrl];
    }

    /**
     * Finalize: insert baris berkas. Return ['id'=>int, 'perlu_render'=>bool].
     * PDF → Status_Render PENDING (perlu render). Selain itu → DONE.
     */
    public function finalize(int $idVersi, array $data): array
    {
        $mime = strtolower($data['mime_type'] ?? '');
        $isPdf = str_contains($mime, 'pdf');
        $isVideo = ($data['storage_provider'] ?? 'gcs') === 'bunny';

        // SATU berkas per versi: berkas lama diganti (nonaktifkan + hapus objek GCS lama best-effort)
        $lama = DB::table($this->table)
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->get();
        foreach ($lama as $o) {
            DB::table($this->table)
                ->where('Id_SOP_Item_Versi_Berkas', $o->Id_SOP_Item_Versi_Berkas)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF]);
            if (! empty($o->File_Path)) {
                try {
                    Storage::disk('gcs')->delete($o->File_Path);
                } catch (\Throwable $e) { /* best-effort */
                }
            }
        }

        $id = DB::table($this->table)->insertGetId([
            'Id_SOP_Item_Versi' => $idVersi,
            'Media_Key' => str_replace('-', '', Str::uuid()->toString()),
            'Nama_Berkas' => $data['nama_berkas'] ?? null,
            'Type_File' => $data['mime_type'] ?? null,
            'Ukuran_Byte' => $data['ukuran_byte'] ?? null,
            'Storage_Provider' => $isVideo ? 'bunny' : 'gcs',
            'File_Path' => $isVideo ? null : ($data['gcs_path'] ?? null),
            'Provider_Media_Id' => $isVideo ? ($data['provider_media_id'] ?? null) : null,
            'Status_Render' => $isPdf ? 'PENDING' : 'DONE',
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_At' => now(),
        ], 'Id_SOP_Item_Versi_Berkas');

        return ['id' => $id, 'perlu_render' => $isPdf];
    }

    /** Daftar berkas satu versi (Id encoded + signed URL baca). */
    public function byVersi(int $idVersi)
    {
        $gcs = new GcsService;

        return DB::table($this->table)
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('Id_SOP_Item_Versi_Berkas')
            ->get()
            ->map(function ($b) use ($gcs) {
                $b->preview_url = $b->File_Path ? $gcs->getFileUrl($b->File_Path) : null;
                $b->Id_SOP_Item_Versi_Berkas = Hashids::connection('custom')->encode($b->Id_SOP_Item_Versi_Berkas);

                return $b;
            });
    }

    /** Ambil baris berkas mentah (untuk webhook render). */
    public function raw(int $id)
    {
        return DB::table($this->table)->where('Id_SOP_Item_Versi_Berkas', $id)->first();
    }

    /** Update status render (hanya kolom yang relevan). */
    public function setRenderStatus(int $id, string $status, ?int $totalHalaman = null, ?string $error = null): void
    {
        $payload = ['Status_Render' => $status];
        if ($status === 'DONE') {
            $payload['Total_Halaman'] = $totalHalaman;
            $payload['Error_Message'] = null;
        } elseif ($status === 'FAILED') {
            $payload['Error_Message'] = $error ? substr($error, 0, 1000) : 'Render gagal';
        }
        DB::table($this->table)->where('Id_SOP_Item_Versi_Berkas', $id)->update($payload);
    }
}
