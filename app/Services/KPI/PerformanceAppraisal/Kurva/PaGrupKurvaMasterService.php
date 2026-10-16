<?php

namespace App\Services\KPI\PerformanceAppraisal\Kurva;

use App\Services\KPI\PerformanceAppraisal\PaLevelMasterService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Master Grup Level Kurva: level mana yang dibandingkan dalam satu kurva grade.
 *
 * Disimpan sebagai SATU KONFIGURASI UTUH, bukan per grup: memindah satu level dari grup A ke B
 * adalah dua perubahan yang harus terjadi bersamaan, dan indeks unik "satu level satu grup
 * aktif" akan menolak langkah pertamanya bila dikerjakan satu per satu.
 */
class PaGrupKurvaMasterService
{
    /** @return array<string, mixed> */
    public function daftar(): array
    {
        $grup = DB::table('N_PA_Curve_Group')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')->orderBy('Id_PA_Curve_Group')
            ->get(['Id_PA_Curve_Group', 'Kode_Grup', 'Nama_Grup', 'Urutan_Tampil']);

        $anggota = DB::table('N_PA_Curve_Group_Level as gl')
            ->join('N_PA_Level as l', 'l.ID_Level', '=', 'gl.ID_Level')
            ->where('gl.Flag_Aktif', 'Y')
            ->orderBy('gl.ID_Level')
            ->get(['gl.PA_Curve_Group_Id', 'gl.ID_Level', 'l.Nama_Level as Keterangan'])
            ->groupBy('PA_Curve_Group_Id');

        $terpakai = DB::table('N_PA_Curve_Group_Level')->where('Flag_Aktif', 'Y')->pluck('ID_Level')->map(fn ($x) => (int) $x)->all();

        $siklus = DB::table('N_PA_Participant')->orderByDesc('PA_Cycle_Id')->value('PA_Cycle_Id');
        $cacah = $siklus === null ? [] : DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $siklus)->where('Status_Peserta', 'DIIKUTKAN')
            ->groupBy('ID_Level_Saat_Cycle')
            ->selectRaw('ID_Level_Saat_Cycle as l, COUNT(*) as n')
            ->orderBy('ID_Level_Saat_Cycle')
            ->get()
            // Daftar, bukan peta ber-kunci ID level: kunci dari data tidak bisa dijadikan kontrak.
            ->map(fn ($r) => ['level_id' => (int) $r->l, 'jumlah' => (int) $r->n])->values()->all();

        PaLevelMasterService::sinkronHris();

        return [
            'grup' => $grup->map(fn ($g) => [
                'id' => (int) $g->Id_PA_Curve_Group,
                'kode' => (string) $g->Kode_Grup,
                'nama' => (string) $g->Nama_Grup,
                'urutan' => (int) $g->Urutan_Tampil,
                'level' => collect($anggota->get($g->Id_PA_Curve_Group, []))
                    ->map(fn ($l) => ['id' => (int) $l->ID_Level, 'nama' => (string) $l->Keterangan])->values()->all(),
            ])->values()->all(),
            'level_tanpa_grup' => DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')->whereNotIn('ID_Level', $terpakai ?: [0])
                ->orderBy('Urutan')->orderBy('ID_Level')->get(['ID_Level', 'Nama_Level as Keterangan'])
                ->map(fn ($l) => ['id' => (int) $l->ID_Level, 'nama' => (string) $l->Keterangan])->values()->all(),
            'cacah_level' => $cacah,
        ];
    }

    /**
     * @param  list<array{kode:string, nama:string, level_ids:list<int>}>  $grup
     * @return array<string, mixed>
     */
    public function simpan(array $grup, ?int $olehUserId): array
    {
        $bersih = $this->periksa($grup);

        DB::transaction(function () use ($bersih, $olehUserId) {
            DB::table('N_PA_Curve_Group_Level')->where('Flag_Aktif', 'Y')->update(['Flag_Aktif' => 'T']);
            DB::table('N_PA_Curve_Group')->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now(), 'Updated_By' => $olehUserId]);

            foreach ($bersih as $i => $g) {
                $id = (int) DB::table('N_PA_Curve_Group')->insertGetId([
                    'Kode_Grup' => $g['kode'],
                    'Nama_Grup' => $g['nama'],
                    'Urutan_Tampil' => $i + 1,
                    'Created_By' => $olehUserId,
                ], 'Id_PA_Curve_Group');

                DB::table('N_PA_Curve_Group_Level')->insert(array_map(fn (int $l) => [
                    'PA_Curve_Group_Id' => $id,
                    'ID_Level' => $l,
                    'Created_By' => $olehUserId,
                ], $g['level_ids']));
            }
        });

        return $this->daftar();
    }

    /**
     * Publik: dipakai juga untuk grup SALINAN siklus (PaKurvaService::aturGrupSiklus) —
     * aturan grup di master dan di siklus harus satu, bukan dua salinan yang berselisih.
     *
     * @param  list<array<string, mixed>>  $grup
     * @return list<array{kode:string, nama:string, level_ids:list<int>}>
     */
    public function periksa(array $grup): array
    {
        $kodeDilihat = [];
        $levelDilihat = [];
        $ada = DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')->pluck('ID_Level')->map(fn ($x) => (int) $x)->flip();
        $bersih = [];

        foreach ($grup as $g) {
            $kode = strtoupper(trim((string) ($g['kode'] ?? '')));
            $nama = trim((string) ($g['nama'] ?? ''));
            $level = array_values(array_unique(array_map('intval', (array) ($g['level_ids'] ?? []))));

            if ($kode === '' || $nama === '') {
                throw new RuntimeException('Setiap grup wajib punya kode dan nama.');
            }

            if (isset($kodeDilihat[$kode])) {
                throw new RuntimeException("Kode grup {$kode} dipakai dua kali.");
            }

            if ($level === []) {
                throw new RuntimeException("Grup {$kode} belum berisi level. Grup tanpa anggota tidak punya kurva.");
            }

            foreach ($level as $l) {
                if (! $ada->has($l)) {
                    throw new RuntimeException("Level {$l} tidak ada di Master Level PA.");
                }

                if (isset($levelDilihat[$l])) {
                    throw new RuntimeException("Level {$l} dimasukkan ke dua grup ({$levelDilihat[$l]} dan {$kode}). Satu level hanya boleh di satu kurva.");
                }

                $levelDilihat[$l] = $kode;
            }

            $kodeDilihat[$kode] = true;
            $bersih[] = ['kode' => $kode, 'nama' => $nama, 'level_ids' => $level];
        }

        return $bersih;
    }
}
