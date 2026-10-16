<?php

namespace App\Services\SYSTEM\Governance\Authorization\Akses;

use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mode keputusan akses per modul (RBAC Fase 3a, DDL-2026-168):
 * LAMA (default), BAYANGAN, BARU. Tanpa tabel/baris = LAMA.
 *
 * JALUR PANAS: dipanggil di setiap gerbang route dan setiap menu sidebar.
 * Karena itu (review Fase 3a — sempat menambah ±300 ms per request):
 *  - tanpa Schema::hasTable (di prod skemanya dibaca ulang tiap request);
 *    tabel dibaca langsung di dalam try/catch;
 *  - mode & peta halaman disimpan di cache lintas-request, dibuang saat mode
 *    diubah (lupakan());
 *  - hanya baris NON-LAMA yang disimpan; bila tak ada, keputusan langsung
 *    LAMA tanpa memuat peta halaman sama sekali.
 */
class ModeAkses
{
    public const LAMA = 'LAMA';

    public const BAYANGAN = 'BAYANGAN';

    public const BARU = 'BARU';

    private const KUNCI_MODE = 'rbac:mode-modul';

    private const KUNCI_PETA = 'rbac:peta-halaman-modul';

    private const DETIK_CACHE = 600;

    /** @var array<int, string>|null hanya modul NON-LAMA */
    private static ?array $modeModul = null;

    /** @var array<string, int>|null halaman (ternormalisasi) → Id_Grup_Fitur pemilik */
    private static ?array $modulHalaman = null;

    /** Kosongkan cache dalam proses (uji / akhir request). */
    public static function lupakanCache(): void
    {
        self::$modeModul = null;
        self::$modulHalaman = null;
    }

    /** Buang cache dalam proses DAN lintas-request — wajib setelah mode diubah. */
    public static function lupakan(): void
    {
        self::lupakanCache();
        Cache::forget(self::KUNCI_MODE);
        Cache::forget(self::KUNCI_PETA);
    }

    /** @return array<int, string> modul yang modenya BUKAN LAMA */
    public function semua(): array
    {
        return self::$modeModul ??= Cache::remember(self::KUNCI_MODE, self::DETIK_CACHE, function () {
            try {
                return DB::table('N_HRIS_Akses_Mode_Modul')->where('Mode', '<>', self::LAMA)->pluck('Mode', 'Id_Grup_Fitur')
                    ->mapWithKeys(fn ($m, $g) => [(int) $g => strtoupper(trim((string) $m))])->all();
            } catch (\Throwable) {
                return []; // DDL-2026-168 belum dijalankan → semua LAMA
            }
        });
    }

    /**
     * Nama modul yang kini BARU — untuk banner layar lama (RBAC Fase 5).
     * Tanpa modul BARU tak ada query tambahan.
     *
     * @return list<string>
     */
    public function namaModulBaru(): array
    {
        $baru = array_keys(array_filter($this->semua(), fn ($m) => $m === self::BARU));

        return $baru === [] ? [] : DB::table('N_HRIS_Master_Grup_Fitur')->whereIn('Id_Grup_Fitur', $baru)
            ->orderBy('Nama_Grup_Fitur')->pluck('Nama_Grup_Fitur')->all();
    }

    public function modeModul(?int $idGrup): string
    {
        $mode = $idGrup === null ? self::LAMA : ($this->semua()[$idGrup] ?? self::LAMA);

        // Rem darurat (RBAC_MODE_BARU_DIIZINKAN=false): modul BARU kembali diputuskan
        // aturan lama, dengan selisih tetap dicatat (BAYANGAN). Matriksnya tetap
        // milik admin — sinkron melewati modul yang pernah BARU.
        return $mode === self::BARU && ! config('authorization.mode_baru_diizinkan', true) ? self::BAYANGAN : $mode;
    }

    /**
     * Modul pemilik halaman — SATU aturan untuk seluruh RBAC: Id_Grup_Fitur
     * terkecil di antara baris map AKTIF milik grup AKTIF. Halaman tanpa
     * baris map tak punya pemilik (selalu LAMA).
     */
    public function modulHalaman(string $halaman): ?int
    {
        self::$modulHalaman ??= Cache::remember(self::KUNCI_PETA, self::DETIK_CACHE, function () {
            $peta = [];
            $baris = DB::table('N_HRIS_Grup_Fitur_Map as m')
                ->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'm.Id_Grup_Fitur')
                ->where('m.Flag_Aktif', 'Y')->where('g.Flag_Aktif', 'Y')
                ->orderBy('m.Id_Grup_Fitur')
                ->get(['m.Jenis_page', 'm.Id_Grup_Fitur']);
            foreach ($baris as $r) {
                $peta[HibahTataKelola::normalisasi((string) $r->Jenis_page)] ??= (int) $r->Id_Grup_Fitur;
            }

            return $peta;
        });

        return self::$modulHalaman[HibahTataKelola::normalisasi($halaman)] ?? null;
    }

    /**
     * Modul yang pernah masuk BARU (audit UBAH_MODE). Sejak itu matriks hak
     * perannya milik admin: sinkron dari hibah tak lagi menyentuhnya, juga
     * setelah mundur ke BAYANGAN/LAMA (review Fase 4 — mundur tak boleh
     * menghapus suntingan matriks). Bukan jalur panas: dipakai sinkron saja.
     *
     * @return list<int>
     */
    public function modulPernahBaru(): array
    {
        if (! SkemaRbac::punya('audit')) {
            return [];
        }

        return DB::table(AuditAkses::TABEL)->where('Subjek_Jenis', 'MODE')->where('Aksi', 'UBAH_MODE')
            ->where('Nilai_Sesudah', self::BARU)->distinct()->pluck('Subjek_Id')
            ->map(fn ($v) => (int) $v)->all();
    }

    public function modeHalaman(string $halaman): string
    {
        return $this->semua() === [] ? self::LAMA : $this->modeModul($this->modulHalaman($halaman));
    }
}
