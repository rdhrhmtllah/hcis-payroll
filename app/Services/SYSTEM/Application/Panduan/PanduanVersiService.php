<?php

namespace App\Services\SYSTEM\Application\Panduan;

use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Versioning berkas panduan (DDL-2026-149).
 *
 * Tiap unggahan menjadi satu versi; berkas lama TIDAK dihapus supaya bisa
 * dilihat dan diaktifkan lagi. Versi DRAF hanya terlihat admin sampai
 * diterbitkan. Versi aktif dicerminkan ke N_HRIS_Panduan (Path_File, dst.)
 * sehingga penyajian berkas ke pengguna tidak perlu tahu soal versi.
 */
class PanduanVersiService
{
    use BerkasPanduan;

    public const T_VERSI = 'N_HRIS_Panduan_Versi';

    /** Lama tanda "Diperbarui" tampil di Pusat Bantuan setelah versi baru aktif. */
    public const HARI_TANDA_BARU = 14;

    private ?bool $tersedia = null;

    public function tersedia(): bool
    {
        return $this->tersedia ??= Schema::hasTable(self::T_VERSI);
    }

    /**
     * Riwayat versi satu panduan, terbaru di atas.
     *
     * @return array{tersedia: bool, versi: list<array>}
     */
    public function daftar(int $idPanduan): array
    {
        $panduan = $this->temukanPanduan($idPanduan);
        if (! $this->tersedia()) {
            return ['tersedia' => false, 'versi' => []];
        }
        $this->pastikanAwal($panduan);
        $idAktif = (int) DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $idPanduan)->value('Id_Versi_Aktif');

        $versi = DB::table(self::T_VERSI)
            ->where('Id_Panduan', $idPanduan)
            ->orderByDesc('No_Versi')
            ->get()
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Versi,
                'no' => (int) $r->No_Versi,
                'label' => $r->Label_Versi,
                'catatan' => $r->Catatan_Perubahan,
                'nama_file' => $r->Nama_File_Asli,
                'ukuran_byte' => $r->Ukuran_Byte !== null ? (int) $r->Ukuran_Byte : null,
                'status' => $r->Status,
                'aktif' => (int) $r->Id_Versi === $idAktif,
                'diterbitkan_at' => $r->Diterbitkan_At,
                'diterbitkan_by' => $r->Diterbitkan_By,
                'dibuat_at' => $r->Created_At,
                'dibuat_by' => $r->Created_By,
            ])
            ->all();

        return ['tersedia' => true, 'versi' => $versi];
    }

    /**
     * Versi 1 untuk panduan baru, langsung terbit dan aktif. Dipanggil
     * PanduanService::buat di dalam transaksinya.
     */
    public function catatVersiPertama(int $idPanduan, string $path, UploadedFile $berkas, ?string $label): void
    {
        if (! $this->tersedia()) {
            return;
        }

        $idVersi = $this->sisipkan($idPanduan, 1, $path, $berkas->getClientOriginalName(), $berkas->getSize(), $label, null, 'TERBIT');
        $this->tunjukAktif($idPanduan, $idVersi, $path, $berkas->getClientOriginalName(), $berkas->getSize(), $label);
    }

    /**
     * Unggah versi baru: sebagai DRAF, atau langsung terbit bila $terbitkan.
     */
    public function unggahVersi(int $idPanduan, UploadedFile $berkas, ?string $label, ?string $catatan, bool $terbitkan): array
    {
        $panduan = $this->temukanPanduan($idPanduan);
        $this->wajibTersedia();
        $this->pastikanAwal($panduan);

        $path = $this->unggah($berkas);
        try {
            DB::transaction(function () use ($idPanduan, $berkas, $label, $catatan, $terbitkan, $path) {
                // Kunci baris panduan: dua unggahan serentak tidak boleh mendapat No_Versi sama.
                DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $idPanduan)->lockForUpdate()->first();
                $no = (int) DB::table(self::T_VERSI)->where('Id_Panduan', $idPanduan)->max('No_Versi') + 1;

                $idVersi = $this->sisipkan(
                    $idPanduan, $no, $path, $berkas->getClientOriginalName(), $berkas->getSize(),
                    $this->teksAtauNull($label), $this->teksAtauNull($catatan), 'DRAF',
                );
                if ($terbitkan) {
                    $this->aktifkanDalamTransaksi($idPanduan, $idVersi);
                }
            });
        } catch (\Throwable $e) {
            $this->hapusBerkasDiam($path);
            throw $e;
        }

        $this->lupakanCache();

        return $this->daftar($idPanduan);
    }

    /**
     * Jadikan satu versi aktif: menerbitkan draf, atau mengembalikan versi
     * lama (rollback). Versi yang sedang aktif tetap tersimpan sebagai riwayat.
     */
    public function aktifkan(int $idPanduan, int $idVersi): array
    {
        $this->temukanPanduan($idPanduan);
        $this->wajibTersedia();

        DB::transaction(fn () => $this->aktifkanDalamTransaksi($idPanduan, $idVersi));
        $this->lupakanCache();

        return $this->daftar($idPanduan);
    }

    /** Hapus draf beserta berkasnya. Versi yang pernah terbit tidak bisa dihapus. */
    public function hapusDraf(int $idPanduan, int $idVersi): array
    {
        $this->temukanPanduan($idPanduan);
        $this->wajibTersedia();

        $versi = $this->temukanVersi($idPanduan, $idVersi);
        if ($versi->Status !== 'DRAF') {
            throw new DomainException('Hanya draf yang bisa dihapus; versi yang pernah terbit disimpan sebagai riwayat.');
        }

        DB::table(self::T_VERSI)->where('Id_Versi', $idVersi)->where('Status', 'DRAF')->delete();
        $this->hapusBerkasDiam((string) $versi->Path_File);

        return $this->daftar($idPanduan);
    }

    /** Berkas satu versi untuk pratinjau admin (termasuk draf). */
    public function berkas(int $idPanduan, int $idVersi): ?array
    {
        if (! $this->tersedia()) {
            return null;
        }
        $versi = DB::table(self::T_VERSI)->where('Id_Panduan', $idPanduan)->where('Id_Versi', $idVersi)->first();

        return $versi ? ['path' => (string) $versi->Path_File, 'nama' => (string) ($versi->Nama_File_Asli ?: 'panduan.pdf')] : null;
    }

    // ---------------------------------------------------------------------

    private function aktifkanDalamTransaksi(int $idPanduan, int $idVersi): void
    {
        $versi = $this->temukanVersi($idPanduan, $idVersi);
        $idAktif = DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $idPanduan)->lockForUpdate()->value('Id_Versi_Aktif');
        if ((int) $idAktif === $idVersi) {
            throw new DomainException('Versi ini sudah aktif.');
        }

        if ($versi->Status === 'DRAF') {
            DB::table(self::T_VERSI)->where('Id_Versi', $idVersi)->update([
                'Status' => 'TERBIT',
                'Diterbitkan_At' => now(),
                'Diterbitkan_By' => $this->pelaku(),
            ]);
        }

        $this->tunjukAktif($idPanduan, $idVersi, (string) $versi->Path_File, $versi->Nama_File_Asli, $versi->Ukuran_Byte, $versi->Label_Versi);
    }

    /** Arahkan panduan ke versi ini dan cerminkan berkasnya. */
    private function tunjukAktif(int $idPanduan, int $idVersi, string $path, ?string $nama, $ukuran, ?string $label): void
    {
        DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $idPanduan)->update([
            'Id_Versi_Aktif' => $idVersi,
            'Versi_Aktif_Sejak' => now(),
            'Path_File' => $path,
            'Nama_File_Asli' => $nama !== null ? mb_substr($nama, 0, 255) : null,
            'Ukuran_Byte' => $ukuran !== null ? (int) $ukuran : null,
            'Versi' => $label,
            'Updated_At' => now(),
            'Updated_By' => $this->pelaku(),
        ]);
    }

    private function sisipkan(int $idPanduan, int $no, string $path, ?string $nama, $ukuran, ?string $label, ?string $catatan, string $status): int
    {
        $terbit = $status === 'TERBIT';

        return (int) DB::table(self::T_VERSI)->insertGetId([
            'Id_Panduan' => $idPanduan,
            'No_Versi' => $no,
            'Label_Versi' => $label !== null ? mb_substr($label, 0, 30) : null,
            'Catatan_Perubahan' => $catatan !== null ? mb_substr($catatan, 0, 1000) : null,
            'Path_File' => $path,
            'Nama_File_Asli' => $nama !== null ? mb_substr($nama, 0, 255) : null,
            'Ukuran_Byte' => $ukuran !== null ? (int) $ukuran : null,
            'Status' => $status,
            'Diterbitkan_At' => $terbit ? now() : null,
            'Diterbitkan_By' => $terbit ? $this->pelaku() : null,
            'Created_By' => $this->pelaku(),
        ], 'Id_Versi');
    }

    /**
     * Panduan yang dibuat sebelum versioning ada belum punya riwayat: berkasnya
     * yang sekarang dicatat sebagai versi 1 (terbit, aktif) saat pertama disentuh.
     */
    private function pastikanAwal(object $panduan): void
    {
        if (DB::table(self::T_VERSI)->where('Id_Panduan', $panduan->Id_Panduan)->exists()) {
            return;
        }

        DB::transaction(function () use ($panduan) {
            DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $panduan->Id_Panduan)->lockForUpdate()->first();
            if (DB::table(self::T_VERSI)->where('Id_Panduan', $panduan->Id_Panduan)->exists()) {
                return;
            }

            $sejak = $panduan->Updated_At ?? $panduan->Created_At;
            $idVersi = (int) DB::table(self::T_VERSI)->insertGetId([
                'Id_Panduan' => $panduan->Id_Panduan,
                'No_Versi' => 1,
                'Label_Versi' => $panduan->Versi,
                'Path_File' => $panduan->Path_File,
                'Nama_File_Asli' => $panduan->Nama_File_Asli,
                'Ukuran_Byte' => $panduan->Ukuran_Byte,
                'Status' => 'TERBIT',
                'Diterbitkan_At' => $sejak,
                'Diterbitkan_By' => $panduan->Created_By,
                'Created_At' => $panduan->Created_At,
                'Created_By' => $panduan->Created_By,
            ], 'Id_Versi');

            // Bukan "baru diperbarui": waktu aktifnya ikut waktu lama, jejak Updated_* tidak disentuh.
            DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $panduan->Id_Panduan)->update([
                'Id_Versi_Aktif' => $idVersi,
                'Versi_Aktif_Sejak' => $sejak,
            ]);
        });
    }

    private function temukanPanduan(int $idPanduan): object
    {
        $row = DB::table(PanduanService::T_PANDUAN)->where('Id_Panduan', $idPanduan)->first();
        if (! $row) {
            throw new DomainException('Panduan tidak ditemukan.');
        }

        return $row;
    }

    private function temukanVersi(int $idPanduan, int $idVersi): object
    {
        $row = DB::table(self::T_VERSI)->where('Id_Panduan', $idPanduan)->where('Id_Versi', $idVersi)->first();
        if (! $row) {
            throw new DomainException('Versi tidak ditemukan.');
        }

        return $row;
    }

    private function wajibTersedia(): void
    {
        if (! $this->tersedia()) {
            throw new DomainException('Versioning panduan belum tersedia di basis data ini (DDL-2026-149 belum dijalankan).');
        }
    }

    private function lupakanCache(): void
    {
        PanduanService::lupakanCacheSemua();
    }
}
