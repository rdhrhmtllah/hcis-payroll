<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Jejak perubahan role/akses (RBAC Fase 1, DDL-2026-164). Toleran tabel belum
 * ada: mengembalikan false tanpa melempar, supaya kode bisa rilis sebelum DDL.
 */
class AuditAkses
{
    public const TABEL = 'N_HRIS_Akses_Audit';

    public function catat(
        string $subjekJenis,
        ?string $subjekId,
        string $aksi,
        mixed $sebelum,
        mixed $sesudah,
        ?int $targetIdUser = null,
        ?string $alasan = null,
        string $sumber = 'UI',
        ?int $olehIdUser = null,
    ): bool {
        if (! $this->tabelAda()) {
            return false;
        }

        DB::table(self::TABEL)->insert([
            'Waktu' => now(),
            'Oleh_Id_User' => $olehIdUser ?? Auth::id(),
            'Target_Id_User' => $targetIdUser,
            'Subjek_Jenis' => mb_substr($subjekJenis, 0, 20),
            'Subjek_Id' => $subjekId !== null ? mb_substr($subjekId, 0, 100) : null,
            'Aksi' => mb_substr($aksi, 0, 50),
            'Nilai_Sebelum' => $this->teks($sebelum),
            'Nilai_Sesudah' => $this->teks($sesudah),
            'Alasan' => $alasan !== null ? mb_substr($alasan, 0, 500) : null,
            'Sumber' => mb_substr($sumber, 0, 30),
        ]);

        return true;
    }

    protected function tabelAda(): bool
    {
        return SkemaTersedia::tabel(self::TABEL);
    }

    private function teks(mixed $nilai): ?string
    {
        if ($nilai === null || is_string($nilai)) {
            return $nilai;
        }

        return json_encode($nilai, JSON_UNESCAPED_UNICODE);
    }
}
