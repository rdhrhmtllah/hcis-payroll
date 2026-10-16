<?php

namespace App\Services\ESS\Attendance\Calendar\Operational;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Mengambil bahan kalender operasional dari database dan menyerahkannya ke
 * PenentuTanggalTidakOperasional. Tidak memuat aturan bisnis — kalau ada
 * pertanyaan "kenapa tanggal ini terbit", jawabannya ada di kelas penentu.
 *
 * TIDAK final: uji kontrak menggantinya dengan subkelas anonim supaya uji HTTP
 * tidak menyentuh database.
 */
class KalenderOperasionalService
{
    public const CACHE_KUNCI_VERSI = 'kalender_operasional:versi';

    public function __construct(
        private readonly PenentuTanggalTidakOperasional $penentu = new PenentuTanggalTidakOperasional,
    ) {}

    public function unitDikenal(string $unit): bool
    {
        return is_array(config('kalender_operasional.unit.'.$unit));
    }

    /**
     * @return array{unit:string, hari_kerja_mingguan:list<int>, digenerate_at:string,
     *               tanggal_tidak_operasional:list<array<string,mixed>>}
     */
    public function untukRentang(string $unit, string $dari, string $sampai): array
    {
        $kunci = sprintf('kalender_operasional:v%d:%s:%s:%s', $this->versiCache(), $unit, $dari, $sampai);

        $tanggal = Cache::remember(
            $kunci,
            (int) config('kalender_operasional.cache_ttl_detik', 900),
            fn () => $this->hitung($unit, $dari, $sampai),
        );

        return [
            'unit' => $unit,
            'hari_kerja_mingguan' => array_values(array_map(
                'intval',
                (array) config('kalender_operasional.unit.'.$unit.'.hari_kerja_mingguan', []),
            )),
            'digenerate_at' => CarbonImmutable::now()->toIso8601String(),
            'tanggal_tidak_operasional' => $tanggal,
        ];
    }

    /**
     * Menaikkan versi cache. Dipakai perintah kalender:flush.
     *
     * Versi dipakai sebagai bagian kunci — bukan cache tag — supaya bekerja di
     * driver apa pun, termasuk file dan array.
     */
    public static function bersihkanCache(): int
    {
        $versi = (int) Cache::get(self::CACHE_KUNCI_VERSI, 1) + 1;
        Cache::forever(self::CACHE_KUNCI_VERSI, $versi);

        return $versi;
    }

    private function versiCache(): int
    {
        return (int) Cache::get(self::CACHE_KUNCI_VERSI, 1);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function hitung(string $unit, string $dari, string $sampai): array
    {
        $libur = $this->ambilLibur($dari, $sampai);
        $cutiBersama = $this->ambilCutiBersama($dari, $sampai);
        $karyawanHo = $this->ambilKaryawanUnit($unit);
        $pengecualian = $this->ambilPengecualian(array_column($cutiBersama, 'id'));

        return $this->penentu->tentukan(
            $libur,
            $cutiBersama,
            $karyawanHo,
            $pengecualian,
            $dari,
            $sampai,
            CarbonImmutable::now()->toDateString(),
        );
    }

    /**
     * Kode_Perusahaan sengaja diabaikan, konsisten dengan seluruh pemakai
     * HRIS_Hari_Libur yang sudah ada.
     *
     * @return list<array{tanggal:?string, nama:?string}>
     */
    private function ambilLibur(string $dari, string $sampai): array
    {
        return DB::table('HRIS_Hari_Libur')
            ->whereBetween('Tanggal', [$dari.' 00:00:00', $sampai.' 23:59:59'])
            ->orderBy('Tanggal')
            ->get(['Tanggal', 'Nama_Hari_Libur'])
            ->map(fn ($baris) => [
                'tanggal' => $baris->Tanggal,
                'nama' => $baris->Nama_Hari_Libur,
            ])
            ->all();
    }

    /**
     * Hanya baris yang beririsan rentang. Flag_Executed tidak ikut menyaring —
     * lihat PenentuTanggalTidakOperasional::cutiBersamaBerlaku.
     *
     * @return list<array<string,mixed>>
     */
    private function ambilCutiBersama(string $dari, string $sampai): array
    {
        return DB::table('N_HRIS_Cuti_Bersama')
            ->where('Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('Flag_Cancelled')->orWhere('Flag_Cancelled', '<>', 'Y'))
            ->whereDate('Tanggal_Mulai', '<=', $sampai)
            ->whereRaw('COALESCE(Tanggal_Selesai, Tanggal_Mulai) >= ?', [$dari])
            ->orderBy('Id_Cuti_Bersama')
            ->get([
                'Id_Cuti_Bersama', 'Nama_Cuti_Bersama', 'Selected_Dates',
                'Tanggal_Mulai', 'Tanggal_Selesai', 'Flag_Aktif', 'Flag_Cancelled',
                'Filter_Kode_Karyawan', 'Filter_Cabang', 'Filter_Divisi', 'Filter_Sub_Divisi',
            ])
            ->map(fn ($baris) => [
                'id' => (int) $baris->Id_Cuti_Bersama,
                'nama' => $baris->Nama_Cuti_Bersama,
                'selected_dates' => $baris->Selected_Dates,
                'tanggal_mulai' => $baris->Tanggal_Mulai,
                'tanggal_selesai' => $baris->Tanggal_Selesai,
                'flag_aktif' => $baris->Flag_Aktif,
                'flag_cancelled' => $baris->Flag_Cancelled,
                'filter_kode_karyawan' => $baris->Filter_Kode_Karyawan,
                'filter_cabang' => $baris->Filter_Cabang,
                'filter_divisi' => $baris->Filter_Divisi,
                'filter_sub_divisi' => $baris->Filter_Sub_Divisi,
            ])
            ->all();
    }

    /**
     * Daftar karyawan unit yang diminta. Disaring pola cabang di database, jadi
     * yang dibawa ke memori kecil — bukan seluruh karyawan.
     *
     * Tanggal_Resign disaring di PHP dengan empty(): kolomnya bertipe longgar
     * dan empty() adalah aturan yang sudah dipakai cutiBersamaService.
     *
     * @return list<array{kode:string, cabang:?string, divisi:?string, sub_divisi:?string}>
     */
    private function ambilKaryawanUnit(string $unit): array
    {
        $pola = (array) config('kalender_operasional.unit.'.$unit.'.pola_cabang', []);
        if ($pola === []) {
            return [];
        }

        // ID_Divisi / ID_Sub_Divisi TIDAK ada di tabel Karyawan; keduanya datang
        // dari View_Divisi_Sub_Divisi lewat k.ID_Divisi_Sub_Divisi. Join yang sama
        // dipakai cutiBersamaService, dan nilai itulah yang tersimpan di
        // Filter_Divisi / Filter_Sub_Divisi.
        return DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_DIVISI_SUB_DIVISI')
            ->where('k.Aktif', 'Y')
            ->where(function ($q) use ($pola) {
                foreach ($pola as $satu) {
                    $q->orWhere('k.Cabang_Untuk_Gaji', 'like', $satu);
                }
            })
            ->get(['k.Kode_Karyawan', 'k.Cabang_Untuk_Gaji', 'vd.ID_Divisi', 'vd.ID_Sub_Divisi', 'k.Tanggal_Resign'])
            ->reject(fn ($baris) => ! empty($baris->Tanggal_Resign))
            ->map(fn ($baris) => [
                'kode' => (string) $baris->Kode_Karyawan,
                'cabang' => $baris->Cabang_Untuk_Gaji,
                'divisi' => $baris->ID_Divisi === null ? null : (string) $baris->ID_Divisi,
                'sub_divisi' => $baris->ID_Sub_Divisi === null ? null : (string) $baris->ID_Sub_Divisi,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $idCutiBersama
     * @return array<int, array<string, true>>
     */
    private function ambilPengecualian(array $idCutiBersama): array
    {
        if ($idCutiBersama === []) {
            return [];
        }

        $peta = [];
        foreach (
            DB::table('N_HRIS_Cuti_Bersama_Exclusion')
                ->whereIn('Id_Cuti_Bersama', $idCutiBersama)
                ->get(['Id_Cuti_Bersama', 'Kode_Karyawan']) as $baris
        ) {
            $kode = strtoupper(trim((string) $baris->Kode_Karyawan));
            if ($kode !== '') {
                $peta[(int) $baris->Id_Cuti_Bersama][$kode] = true;
            }
        }

        return $peta;
    }
}
