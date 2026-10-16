<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service Section Paket SOP (Builder — level section).
 * Paket → Section → Item. Progres tetap dihitung per item (section hanya pengelompokan).
 */
class SopSectionService
{
    private string $table = 'N_SOP_Paket_Section';

    /** Section satu paket (urut). Id encoded. */
    public function byPaket(int $idPaket)
    {
        return DB::table($this->table)
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')
            ->get()
            ->map(function ($s) {
                $s->Id_SOP_Section = Hashids::connection('custom')->encode($s->Id_SOP_Section);

                return $s;
            });
    }

    /** Buat section (urut di akhir). Return Id (int). */
    public function create(int $idPaket, array $data, int $userId): int
    {
        $max = DB::table($this->table)->where('Id_SOP_Paket', $idPaket)->max('Urutan') ?? 0;

        return DB::table($this->table)->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Nama_Section' => $data['Nama_Section'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Urutan' => $max + 1,
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Section');
    }

    public function update(int $id, array $data, int $userId): void
    {
        DB::table($this->table)->where('Id_SOP_Section', $id)->update([
            'Nama_Section' => $data['Nama_Section'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);
    }

    /** Simpan ulang urutan section. */
    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $i => $id) {
            DB::table($this->table)->where('Id_SOP_Section', $id)->update(['Urutan' => $i + 1]);
        }
    }

    /** Soft delete section — hanya bila tak ada item aktif di dalamnya. Return true bila terhapus. */
    public function delete(int $id, int $userId): bool
    {
        $itemCount = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Section', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->count();
        if ($itemCount > 0) {
            return false;
        }

        DB::table($this->table)->where('Id_SOP_Section', $id)->update([
            'Flag_Aktif' => SopStatus::INAKTIF,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        return true;
    }

    /** Salin section + seluruh item-nya ke dalam paket yang sama (di akhir urutan). Return id section baru/null. */
    public function cloneById(int $sectionId, int $userId, SopItemService $itemSvc, SopItemVersionService $versiSvc): ?int
    {
        $src = DB::table($this->table)->where('Id_SOP_Section', $sectionId)->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $src) {
            return null;
        }

        $max = DB::table($this->table)->where('Id_SOP_Paket', $src->Id_SOP_Paket)->max('Urutan') ?? 0;
        $newSec = DB::table($this->table)->insertGetId([
            'Id_SOP_Paket' => $src->Id_SOP_Paket,
            'Nama_Section' => mb_substr((string) ($src->Nama_Section ?? 'Section'), 0, 190).' (Salinan)',
            'Deskripsi' => $src->Deskripsi,
            'Urutan' => $max + 1,
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Section');

        $items = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Section', $sectionId)->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        foreach ($items as $it) {
            $itemSvc->cloneRow($it, (int) $src->Id_SOP_Paket, $newSec, $userId, $versiSvc);
        }

        return $newSec;
    }

    /** Jumlah section aktif + jumlah yang KOSONG (tanpa item aktif) — untuk gating publish. */
    public function readiness(int $idPaket): array
    {
        $sections = DB::table($this->table)
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->pluck('Id_SOP_Section');

        /*
         * Satu COUNT per section diganti satu kueri yang menghitung seluruhnya.
         * Section yang tidak punya item sama sekali tidak muncul di hasil, jadi
         * ketidakhadirannya sendiri yang menandai "kosong".
         */
        $isiPerSection = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Section', $sections->all())
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('Id_SOP_Section')
            /* Alias EKSPLISIT. pluck(DB::raw('COUNT(*)'), ...) memakai teks
               "COUNT(*)" sebagai nama properti hasil, dan SQL Server tidak
               mengembalikan kolom bernama begitu -- galatnya baru muncul saat
               ADA baris yang cocok, bukan saat hitungannya nol. */
            ->selectRaw('Id_SOP_Section, COUNT(*) AS jumlah')
            ->pluck('jumlah', 'Id_SOP_Section');

        $empty = 0;
        foreach ($sections as $sid) {
            if ((int) ($isiPerSection[$sid] ?? 0) === 0) {
                $empty++;
            }
        }

        return ['total' => $sections->count(), 'empty' => $empty];
    }
}
