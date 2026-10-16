<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master APQC — klasifikasi proses bisnis dokumen SOP.
 *
 * APQC Process Classification Framework menjawab "dokumen ini memetakan proses
 * bisnis yang mana", dan itu sumbu KEEMPAT pada identitas dokumen, berbeda dari
 * tiga yang sudah ada:
 *
 *   N_SOP_Dokumen.Tipe        — berkasnya apa (DOKUMEN/FORM/VIDEO/KUIS).
 *   N_SOP_Master_Jenis_Dokumen — tingkat hirarki mutu (Kebijakan…Formulir).
 *
 * Aturan pasangan APQC↔tipe item hidup sebagai BARIS di
 * N_SOP_Master_APQC_Tipe_Item, meniru SopJenisDokumenService — bukan enum di
 * kode. Konsekuensinya yang paling berguna: KEWAJIBAN mengisi APQC diturunkan
 * dari pemetaan itu, jadi admin mengubah kewajiban lewat master, tanpa rilis.
 */
class SopApqcService
{
    private string $table = 'N_SOP_Master_APQC';

    private string $tableTipe = 'N_SOP_Master_APQC_Tipe_Item';

    /** List paginated + search. Mengembalikan [rows(encoded), total]. */
    public function paginate(?string $search, int $page, int $limit): array
    {
        $query = DB::table($this->table)->where('Flag_Aktif', SopStatus::AKTIF);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('Nama_Proses', 'LIKE', "%{$search}%")
                    ->orWhere('Kode_Proses', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();

        $rows = $query->orderBy('Urutan')->orderBy('Kode_Proses')
            ->offset(($page - 1) * $limit)->limit($limit)->get();

        $ids = $rows->pluck('Id_SOP_APQC')->all();
        $tipe = $this->tipePerApqc($ids);
        $pakai = $this->jumlahPemakai($ids);

        $rows = $rows->map(function ($r) use ($tipe, $pakai) {
            $id = (int) $r->Id_SOP_APQC;
            $r->Tipe_Item = $tipe[$id] ?? [];
            $r->Jumlah_Dokumen = $pakai[$id] ?? 0;
            $r->Id_SOP_APQC = Hashids::connection('custom')->encode($id);

            return $r;
        });

        return [$rows, $total];
    }

    /**
     * Opsi dropdown. Bila $tipe diisi, hanya APQC yang MENERIMA tipe itu yang
     * dikembalikan — itulah yang membuat layar tak pernah menawarkan pasangan
     * yang nanti ditolak server.
     */
    public function opsi(?string $tipe = null): array
    {
        $rows = DB::table($this->table.' as a')
            ->where('a.Flag_Aktif', SopStatus::AKTIF)
            ->when($tipe, fn ($q) => $q->whereExists(fn ($s) => $s
                ->from($this->tableTipe.' as m')
                ->whereColumn('m.APQC_Id', 'a.Id_SOP_APQC')
                ->where('m.Tipe_Item', $tipe)
                ->where('m.Flag_Aktif', SopStatus::AKTIF)))
            ->orderBy('a.Urutan')->orderBy('a.Kode_Proses')
            ->get(['a.Id_SOP_APQC', 'a.Kode_Proses', 'a.Nama_Proses', 'a.Deskripsi']);

        return $rows->map(fn ($r) => [
            'Id' => Hashids::connection('custom')->encode($r->Id_SOP_APQC),
            'Kode_Proses' => $r->Kode_Proses,
            'Nama_Proses' => $r->Nama_Proses,
            'Deskripsi' => $r->Deskripsi,
        ])->all();
    }

    /** Baris APQC apa adanya. Null bila tak ada atau nonaktif. */
    public function cari(int $id, bool $hanyaAktif = true): ?object
    {
        return DB::table($this->table)
            ->where('Id_SOP_APQC', $id)
            ->when($hanyaAktif, fn ($q) => $q->where('Flag_Aktif', SopStatus::AKTIF))
            ->first();
    }

    /**
     * Apakah APQC ini sah dipasangkan dengan tipe item tersebut?
     *
     * APQC nonaktif selalu tidak sah: menonaktifkannya tanpa menutup pintu
     * pemakaian baru hanya menyembunyikannya dari dropdown, sementara pemanggil
     * yang mengirim id lama tetap lolos.
     */
    public function pasanganSah(int $apqcId, string $tipe): bool
    {
        if (! $this->cari($apqcId)) {
            return false;
        }

        return DB::table($this->tableTipe)
            ->where('APQC_Id', $apqcId)
            ->where('Tipe_Item', $tipe)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->exists();
    }

    /** Tipe item yang sah bagi satu APQC. */
    public function tipeSah(int $apqcId): array
    {
        return DB::table($this->tableTipe)
            ->where('APQC_Id', $apqcId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Tipe_Item')
            ->pluck('Tipe_Item')->all();
    }

    public function create(array $data, int $userId): int
    {
        $this->pastikanKodeUnik($data['Kode_Proses']);

        $id = DB::table($this->table)->insertGetId([
            'Kode_Proses' => strtoupper(trim($data['Kode_Proses'])),
            'Nama_Proses' => $data['Nama_Proses'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Urutan' => (int) ($data['Urutan'] ?? 0),
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => $userId,
            'Created_At' => now(),
        ], 'Id_SOP_APQC');

        $this->setTipeItem($id, $data['Tipe_Item'] ?? [], $userId);

        return $id;
    }

    public function update(int $id, array $data, int $userId): bool
    {
        if (! $this->cari($id)) {
            return false;
        }

        $this->pastikanKodeUnik($data['Kode_Proses'], $id);

        DB::table($this->table)->where('Id_SOP_APQC', $id)->update([
            'Kode_Proses' => strtoupper(trim($data['Kode_Proses'])),
            'Nama_Proses' => $data['Nama_Proses'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Urutan' => (int) ($data['Urutan'] ?? 0),
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        if (array_key_exists('Tipe_Item', $data)) {
            $this->setTipeItem($id, $data['Tipe_Item'] ?? [], $userId);
        }

        return true;
    }

    /**
     * Nonaktifkan APQC.
     *
     * @return int jumlah dokumen aktif yang masih memakainya; 0 berarti berhasil.
     */
    public function softDelete(int $id, int $userId): int
    {
        $dipakai = $this->jumlahPemakai([$id])[$id] ?? 0;

        if ($dipakai > 0) {
            return $dipakai;
        }

        DB::table($this->table)->where('Id_SOP_APQC', $id)->update([
            'Flag_Aktif' => SopStatus::INAKTIF,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        DB::table($this->tableTipe)->where('APQC_Id', $id)
            ->update(['Flag_Aktif' => SopStatus::INAKTIF]);

        return 0;
    }

    /**
     * Samakan daftar tipe item sah dengan yang dikirim.
     *
     * Baris yang dicabut DINONAKTIFKAN, bukan dihapus: unique index memakai
     * (APQC, Tipe) tanpa Flag_Aktif, jadi menghidupkan kembali pasangan yang
     * sama harus lewat update — bukan insert baru yang akan bentrok.
     *
     * @param  string[]  $tipe
     */
    public function setTipeItem(int $apqcId, array $tipe, int $userId): void
    {
        $tipe = array_values(array_unique(array_filter(
            array_map(fn ($t) => strtoupper(trim((string) $t)), $tipe),
            fn ($t) => in_array($t, SopTipeItem::SEMUA, true),
        )));

        $ada = DB::table($this->tableTipe)->where('APQC_Id', $apqcId)
            ->pluck('Flag_Aktif', 'Tipe_Item')->all();

        foreach ($tipe as $t) {
            if (! array_key_exists($t, $ada)) {
                DB::table($this->tableTipe)->insert([
                    'APQC_Id' => $apqcId,
                    'Tipe_Item' => $t,
                    'Flag_Aktif' => SopStatus::AKTIF,
                    'Created_By' => $userId,
                    'Created_At' => now(),
                ]);
            } elseif ($ada[$t] !== SopStatus::AKTIF) {
                DB::table($this->tableTipe)
                    ->where('APQC_Id', $apqcId)->where('Tipe_Item', $t)
                    ->update(['Flag_Aktif' => SopStatus::AKTIF]);
            }
        }

        $dicabut = array_diff(array_keys($ada), $tipe);
        if ($dicabut) {
            DB::table($this->tableTipe)
                ->where('APQC_Id', $apqcId)
                ->whereIn('Tipe_Item', $dicabut)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF]);
        }
    }

    /** @throws \DomainException */
    private function pastikanKodeUnik(string $kode, ?int $kecuali = null): void
    {
        $kode = strtoupper(trim($kode));

        $bentrok = DB::table($this->table)
            ->where('Kode_Proses', $kode)
            ->when($kecuali, fn ($q) => $q->where('Id_SOP_APQC', '<>', $kecuali))
            ->value('Nama_Proses');

        if ($bentrok !== null) {
            throw new \DomainException(
                'Nomor proses "'.$kode.'" sudah dipakai "'.$bentrok.'". '
                .'Nomor APQC adalah rujukan tetap yang dikutip di audit, '
                .'jadi satu nomor hanya boleh menunjuk satu proses.');
        }
    }

    /** @return array<int, string[]> */
    private function tipePerApqc(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $out = [];
        foreach (DB::table($this->tableTipe)
            ->whereIn('APQC_Id', $ids)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Tipe_Item')
            ->get(['APQC_Id', 'Tipe_Item']) as $r) {
            $out[(int) $r->APQC_Id][] = $r->Tipe_Item;
        }

        return $out;
    }

    /**
     * Berapa dokumen aktif memakai tiap APQC.
     *
     * Alias eksplisit pada COUNT(*): `pluck(DB::raw('COUNT(*)'), ...)` memakai
     * teks "COUNT(*)" sebagai nama properti hasil dan meledak begitu ADA baris
     * yang cocok — bug yang tak pernah terlihat selama hitungannya nol.
     *
     * @return array<int, int>
     */
    private function jumlahPemakai(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        return DB::table('N_SOP_Dokumen')
            ->whereIn('APQC_Id', $ids)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('APQC_Id')
            ->selectRaw('APQC_Id as id_apqc, COUNT(*) as n')
            ->pluck('n', 'id_apqc')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
