<?php

namespace App\Services\VISITOR\Workflow\Reporting\Export;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Tautan berkas VMS di hasil export — bertanda tangan, berlaku 1 hari.
 *
 * Keputusan user 30 Sep 2026 (opsi 2): foto pemeriksaan dan foto kendaraan
 * tamu ikut diekspor sebagai tautan yang bisa dibuka TANPA login HCIS
 * (berkasnya bisa dikirim ke pihak luar), tetapi:
 *
 *  - Tautannya lewat domain HCIS, bukan URL GCS. Berkasnya dialirkan server
 *    (TautanBerkasController), jadi nama bucket dan path objek tidak pernah
 *    terlihat — termasuk setelah tautannya diklik.
 *  - Isinya ID berkas, bukan path; tanda tangan APP_KEY mencegah ID-nya
 *    diganti untuk membuka berkas lain.
 *  - Berlaku MASA_BERLAKU_JAM sejak export dibuat.
 *
 * Ditandatangani RELATIF lalu diberi awalan APP_URL: export bisa berjalan di
 * antrean (tanpa request) atau dibuka lewat host lain (tunnel), dan tanda
 * tangan absolut akan gagal begitu host-nya berbeda sedikit saja.
 */
class TautanBerkasVisitor
{
    public const MASA_BERLAKU_JAM = 24;

    public const RUTE = 'visitor.berkas.tautan';

    /** Dokumen permit (N_EMI_VISITOR_Transaksi_Permit_Dokumen.Id_Permit_Dokumen). */
    public const DOKUMEN_PERMIT = 'dokumen-permit';

    /** Foto kendaraan tamu (N_EMI_VISITOR_Transaksi_Tamu_Detail.Id_Transaksi_Tamu_Detail). */
    public const FOTO_TAMU = 'foto-tamu';

    public const JENIS = [self::DOKUMEN_PERMIT, self::FOTO_TAMU];

    public function buat(string $jenis, string $id): ?string
    {
        if (! in_array($jenis, self::JENIS, true) || ! ctype_digit($id)) {
            return null;
        }

        $relatif = URL::temporarySignedRoute(
            self::RUTE,
            now()->addHours(self::MASA_BERLAKU_JAM),
            ['jenis' => $jenis, 'id' => $id],
            absolute: false,
        );

        return rtrim((string) config('app.url'), '/').$relatif;
    }

    /** 'sah' | 'kedaluwarsa' | 'palsu' — pasangan relatif dari buat(). */
    public function periksa(Request $request): string
    {
        if (! URL::hasCorrectSignature($request, absolute: false)) {
            return 'palsu';
        }

        return URL::signatureHasNotExpired($request) ? 'sah' : 'kedaluwarsa';
    }

    /**
     * Path objek GCS dan nama unduhan untuk satu berkas, atau null bila
     * berkasnya tidak (lagi) ada.
     *
     * @return array{path: string, nama: string}|null
     */
    public function berkas(string $jenis, int $id): ?array
    {
        if ($jenis === self::DOKUMEN_PERMIT) {
            $dok = DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')
                ->where('Id_Permit_Dokumen', $id)
                ->first(['No_Transaksi', 'Jenis_Dokumen', 'Path_File_URL']);

            $path = trim((string) ($dok->Path_File_URL ?? ''));

            return $path === '' ? null : [
                'path' => $path,
                'nama' => $dok->Jenis_Dokumen.'-'.$dok->No_Transaksi.$this->ekstensi($path),
            ];
        }

        if ($jenis === self::FOTO_TAMU) {
            $foto = DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')
                ->where('Id_Transaksi_Tamu_Detail', $id)
                ->first(['No_Transaksi', 'Path_Foto_Kendaraan']);

            $path = trim((string) ($foto->Path_Foto_Kendaraan ?? ''));

            return $path === '' ? null : [
                'path' => $path,
                'nama' => 'FOTO_KENDARAAN-'.$foto->No_Transaksi.$this->ekstensi($path),
            ];
        }

        return null;
    }

    private function ekstensi(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return $ext !== '' ? '.'.$ext : '';
    }
}
