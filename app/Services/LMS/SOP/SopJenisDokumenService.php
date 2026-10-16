<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Jenis Dokumen SOP — tingkat hirarki mutu.
 *
 * Kebijakan → Prosedur → Instruksi Kerja → Formulir. Sumbu ini menjawab
 * "dokumen ini tingkat apa dalam piramid mutu", dan sengaja BERBEDA dari dua
 * sumbu yang sudah ada:
 *
 *   N_SOP_Dokumen.Tipe   — berkasnya apa, dan apa yang diminta dari karyawan.
 *
 * Aturan pasangan jenis↔tipe hidup sebagai BARIS di
 * N_SOP_Master_Jenis_Dokumen_Tipe_Item, bukan sebagai enum di kode. Alasannya
 * bukan estetika normalisasi: daftar tingkat mutu adalah hal yang diubah bagian
 * mutu (menambah "Manual Mutu" di puncak, "Rekaman" di dasar), dan aturan yang
 * ditulis di kode menuntut rilis untuk setiap perubahan kebijakan.
 */
class SopJenisDokumenService
{
    private string $table = 'N_SOP_Master_Jenis_Dokumen';

    private string $tableTipe = 'N_SOP_Master_Jenis_Dokumen_Tipe_Item';

    /** List paginated + search. Mengembalikan [rows(encoded), total]. */
    public function paginate(?string $search, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $query = DB::table($this->table)->where('Flag_Aktif', SopStatus::AKTIF);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('Nama_Jenis', 'LIKE', "%{$search}%")
                    ->orWhere('Kode_Jenis', 'LIKE', "%{$search}%")
                    ->orWhere('Kode_Jenis_Dokumen', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();

        $rows = $query->orderBy('Level_Hirarki')->orderBy('Urutan')
            ->offset($offset)->limit($limit)->get();

        $ids = $rows->pluck('Id_SOP_Jenis_Dokumen')->all();
        $tipe = $this->tipePerJenis($ids);
        $pakai = $this->jumlahPemakaiPerJenis($ids);

        $rows = $rows->map(function ($item) use ($tipe, $pakai) {
            $id = (int) $item->Id_SOP_Jenis_Dokumen;
            $item->Tipe_Item = $tipe[$id] ?? [];
            $item->Jumlah_Dokumen = $pakai[$id] ?? 0;
            $item->Id_SOP_Jenis_Dokumen = Hashids::connection('custom')->encode($id);

            return $item;
        });

        return [$rows, $total];
    }

    /**
     * Opsi untuk dropdown. Bila $tipe diisi, hanya jenis yang MENERIMA tipe itu
     * yang dikembalikan — inilah yang membuat layar tak pernah menawarkan
     * pasangan yang nanti ditolak server.
     */
    public function opsi(?string $tipe = null): array
    {
        $rows = DB::table($this->table.' as j')
            ->where('j.Flag_Aktif', SopStatus::AKTIF)
            ->when($tipe, fn ($q) => $q->whereExists(fn ($s) => $s
                ->from($this->tableTipe.' as m')
                ->whereColumn('m.Jenis_Dokumen_Id', 'j.Id_SOP_Jenis_Dokumen')
                ->where('m.Tipe_Item', $tipe)
                ->where('m.Flag_Aktif', SopStatus::AKTIF)))
            ->orderBy('j.Level_Hirarki')->orderBy('j.Urutan')
            ->get(['j.Id_SOP_Jenis_Dokumen', 'j.Kode_Jenis', 'j.Kode_Jenis_Dokumen',
                'j.Nama_Jenis', 'j.Level_Hirarki', 'j.Deskripsi']);

        $tipePerJenis = $this->tipePerJenis($rows->pluck('Id_SOP_Jenis_Dokumen')->all());

        return $rows->map(fn ($r) => [
            'Id' => Hashids::connection('custom')->encode($r->Id_SOP_Jenis_Dokumen),
            'Kode_Jenis' => $r->Kode_Jenis,
            'Kode_Jenis_Dokumen' => $r->Kode_Jenis_Dokumen,
            'Nama_Jenis' => $r->Nama_Jenis,
            'Level_Hirarki' => (int) $r->Level_Hirarki,
            'Deskripsi' => $r->Deskripsi,
            'Tipe_Item' => $tipePerJenis[(int) $r->Id_SOP_Jenis_Dokumen] ?? [],
        ])->all();
    }

    /** Huruf besar, tanpa spasi. Kosong tetap null — bukan string kosong. */
    private function bersihkanKodeDokumen($kode): ?string
    {
        $kode = strtoupper(trim((string) ($kode ?? '')));

        return $kode === '' ? null : $kode;
    }

    /**
     * Kode jenis dokumen tak boleh dipakai dua jenis.
     *
     * Dua jenis yang mencetak kode sama membuat nomor dokumen AMBIGU: tak ada
     * lagi cara membaca "SOP/ENB/IT/..." kembali menjadi satu jenis tertentu.
     * Filtered unique index menjaga hal yang sama di tingkat basis data;
     * penjaga ini ada supaya pesannya bisa dibaca manusia.
     *
     * @throws \DomainException
     */
    private function pastikanKodeDokumenUnik($kode, ?int $kecuali = null): void
    {
        $kode = $this->bersihkanKodeDokumen($kode);
        if ($kode === null) {
            return;
        }

        $bentrok = DB::table($this->table)
            ->where('Kode_Jenis_Dokumen', $kode)
            ->when($kecuali, fn ($q) => $q->where('Id_SOP_Jenis_Dokumen', '<>', $kecuali))
            ->value('Nama_Jenis');

        if ($bentrok !== null) {
            throw new \DomainException(
                'Kode "'.$kode.'" sudah dipakai jenis "'.$bentrok.'". Kode ini '
                .'menjadi segmen pertama nomor dokumen, jadi satu kode hanya '
                .'boleh menunjuk satu jenis.');
        }
    }

    /** Baris jenis apa adanya (belum di-encode). Null bila tak ada/nonaktif. */
    public function cari(int $id, bool $hanyaAktif = true): ?object
    {
        return DB::table($this->table)
            ->where('Id_SOP_Jenis_Dokumen', $id)
            ->when($hanyaAktif, fn ($q) => $q->where('Flag_Aktif', SopStatus::AKTIF))
            ->first();
    }

    /**
     * Apakah jenis ini sah dipasangkan dengan tipe item tersebut?
     *
     * Jenis nonaktif selalu tidak sah: menonaktifkan jenis tanpa menutup pintu
     * pemakaian barunya hanya menyembunyikannya dari dropdown, sementara
     * pemanggil yang mengirim id lama tetap lolos.
     */
    public function pasanganSah(int $jenisId, string $tipe): bool
    {
        if (! $this->cari($jenisId)) {
            return false;
        }

        return DB::table($this->tableTipe)
            ->where('Jenis_Dokumen_Id', $jenisId)
            ->where('Tipe_Item', $tipe)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->exists();
    }

    /** Tipe item yang sah bagi satu jenis. */
    public function tipeSah(int $jenisId): array
    {
        return DB::table($this->tableTipe)
            ->where('Jenis_Dokumen_Id', $jenisId)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Tipe_Item')
            ->pluck('Tipe_Item')->all();
    }

    public function create(array $data, int $userId): int
    {
        $this->pastikanKodeUnik($data['Kode_Jenis']);
        $this->pastikanKodeDokumenUnik($data['Kode_Jenis_Dokumen'] ?? null);

        $id = DB::table($this->table)->insertGetId([
            'Kode_Jenis' => strtoupper(trim($data['Kode_Jenis'])),
            'Kode_Jenis_Dokumen' => $this->bersihkanKodeDokumen($data['Kode_Jenis_Dokumen'] ?? null),
            'Nama_Jenis' => $data['Nama_Jenis'],
            'Level_Hirarki' => (int) $data['Level_Hirarki'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Urutan' => (int) ($data['Urutan'] ?? $data['Level_Hirarki']),
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => $userId,
            'Created_At' => now(),
        ], 'Id_SOP_Jenis_Dokumen');

        $this->setTipeItem($id, $data['Tipe_Item'] ?? [], $userId);

        return $id;
    }

    public function update(int $id, array $data, int $userId): bool
    {
        if (! $this->cari($id)) {
            return false;
        }

        $this->pastikanKodeUnik($data['Kode_Jenis'], $id);
        $this->pastikanKodeDokumenUnik($data['Kode_Jenis_Dokumen'] ?? null, $id);

        DB::table($this->table)->where('Id_SOP_Jenis_Dokumen', $id)->update([
            'Kode_Jenis' => strtoupper(trim($data['Kode_Jenis'])),
            /* Kode cetak SENGAJA boleh diubah, tak seperti kode departemen.
               Kepanjangan resmi tiap jenis belum ditetapkan saat master ini
               lahir, dan mengunci kodenya berarti mengunci tebakan. */
            'Kode_Jenis_Dokumen' => $this->bersihkanKodeDokumen($data['Kode_Jenis_Dokumen'] ?? null),
            'Nama_Jenis' => $data['Nama_Jenis'],
            'Level_Hirarki' => (int) $data['Level_Hirarki'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Urutan' => (int) ($data['Urutan'] ?? $data['Level_Hirarki']),
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        if (array_key_exists('Tipe_Item', $data)) {
            $this->setTipeItem($id, $data['Tipe_Item'] ?? [], $userId);
        }

        return true;
    }

    /**
     * Susun ulang seluruh hirarki jenis dokumen secara sekuensial.
     * Mengatur Level_Hirarki dan Urutan secara otomatis agar rapi tanpa duplikat (1..N).
     *
     * @param  int[]  $orderedIds
     */
    public function reorder(array $orderedIds, int $userId): void
    {
        foreach ($orderedIds as $index => $id) {
            $seq = $index + 1;
            DB::table($this->table)
                ->where('Id_SOP_Jenis_Dokumen', $id)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->update([
                    'Level_Hirarki' => $seq,
                    'Urutan' => $seq,
                    'Updated_By' => $userId,
                    'Updated_At' => now(),
                ]);
        }
    }

    /**
     * Nonaktifkan jenis.
     *
     * @return int jumlah dokumen aktif yang masih memakainya; 0 berarti berhasil.
     *
     * Menolak saat masih dipakai, bukan sekadar menyembunyikannya dari dropdown:
     * dokumen yang jenisnya nonaktif akan tampil dengan badge yang tak bisa
     * dipilih lagi saat disunting, dan admin tak punya cara mengetahui kenapa.
     */
    public function softDelete(int $id, int $userId): int
    {
        $dipakai = DB::table('N_SOP_Dokumen')
            ->where('Jenis_Dokumen_Id', $id)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->count();

        if ($dipakai > 0) {
            return $dipakai;
        }

        DB::table($this->table)->where('Id_SOP_Jenis_Dokumen', $id)->update([
            'Flag_Aktif' => SopStatus::INAKTIF,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        DB::table($this->tableTipe)->where('Jenis_Dokumen_Id', $id)
            ->update(['Flag_Aktif' => SopStatus::INAKTIF]);

        return 0;
    }

    /**
     * Samakan daftar tipe item sah dengan yang dikirim.
     *
     * Baris yang dicabut DINONAKTIFKAN, bukan dihapus: unique index memakai
     * (Jenis, Tipe) tanpa Flag_Aktif, jadi menghidupkan kembali pasangan yang
     * sama harus lewat update — bukan insert baru yang akan bentrok.
     *
     * @param  string[]  $tipe
     */
    public function setTipeItem(int $jenisId, array $tipe, int $userId): void
    {
        $tipe = array_values(array_unique(array_filter(
            array_map(fn ($t) => strtoupper(trim((string) $t)), $tipe),
            fn ($t) => in_array($t, SopTipeItem::SEMUA, true),
        )));

        $ada = DB::table($this->tableTipe)->where('Jenis_Dokumen_Id', $jenisId)
            ->pluck('Flag_Aktif', 'Tipe_Item')->all();

        foreach ($tipe as $t) {
            if (! array_key_exists($t, $ada)) {
                DB::table($this->tableTipe)->insert([
                    'Jenis_Dokumen_Id' => $jenisId,
                    'Tipe_Item' => $t,
                    'Flag_Aktif' => SopStatus::AKTIF,
                    'Created_By' => $userId,
                    'Created_At' => now(),
                ]);
            } elseif ($ada[$t] !== SopStatus::AKTIF) {
                DB::table($this->tableTipe)
                    ->where('Jenis_Dokumen_Id', $jenisId)->where('Tipe_Item', $t)
                    ->update(['Flag_Aktif' => SopStatus::AKTIF]);
            }
        }

        $dicabut = array_diff(array_keys($ada), $tipe);
        if ($dicabut) {
            DB::table($this->tableTipe)
                ->where('Jenis_Dokumen_Id', $jenisId)
                ->whereIn('Tipe_Item', $dicabut)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF]);
        }
    }

    /** @throws \DomainException */
    private function pastikanKodeUnik(string $kode, ?int $kecuali = null): void
    {
        $kode = strtoupper(trim($kode));

        $bentrok = DB::table($this->table)
            ->where('Kode_Jenis', $kode)
            ->when($kecuali, fn ($q) => $q->where('Id_SOP_Jenis_Dokumen', '<>', $kecuali))
            ->value('Nama_Jenis');

        if ($bentrok !== null) {
            throw new \DomainException(
                'Kode jenis "'.$kode.'" sudah dipakai "'.$bentrok.'". '
                .'Kode adalah rujukan tetap yang dipakai integrasi dan seed, '
                .'jadi satu kode hanya boleh menunjuk satu jenis.');
        }
    }

    /** @return array<int, string[]> */
    private function tipePerJenis(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        $out = [];
        foreach (DB::table($this->tableTipe)
            ->whereIn('Jenis_Dokumen_Id', $ids)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Tipe_Item')
            ->get(['Jenis_Dokumen_Id', 'Tipe_Item']) as $r) {
            $out[(int) $r->Jenis_Dokumen_Id][] = $r->Tipe_Item;
        }

        return $out;
    }

    /** @return array<int, int> */
    private function jumlahPemakaiPerJenis(array $ids): array
    {
        if (! $ids) {
            return [];
        }

        /* Alias eksplisit — lihat catatan yang sama di EntitasService. Tanpa
           alias, "COUNT(*)" dipakai sebagai nama properti hasil dan meledak
           begitu ada baris yang cocok; selama hitungannya nol, bug ini tak
           pernah terlihat. */
        return DB::table('N_SOP_Dokumen')
            ->whereIn('Jenis_Dokumen_Id', $ids)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('Jenis_Dokumen_Id')
            ->selectRaw('Jenis_Dokumen_Id as id_jenis, COUNT(*) as n')
            ->pluck('n', 'id_jenis')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
