<?php

namespace App\Services\SYSTEM\MasterData\Departemen;

use App\Services\LMS\SOP\Support\SopUnitKerja;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Kode Departemen — acuan kepemilikan dokumen dan segmen nomor dokumen.
 *
 * KENAPA MASTER SENDIRI, BUKAN KOLOM DI HRIS_Sub_Divisi. Daftar kode perusahaan
 * lebih rinci daripada struktur yang tersimpan: 52 kode versus 32 sub-divisi.
 * Sebagian kode adalah tingkat di bawahnya yang memang tak punya baris sendiri
 * (WFP/DFP di bawah Production; RWH/PFWH/CSWH/GWH di bawah Warehouse; MTP/MTA
 * di bawah Modern Trade). Menempelkan kode ke sub-divisi akan membuat 35 dari 52
 * kode mustahil dipakai sampai seseorang menambah baris ke tabel legacy — dan
 * mengubah skema legacy adalah tugas terpisah dengan review sendiri.
 *
 * BINDING KE SUB-DIVISI OPSIONAL, dan itu bukan kelalaian. Ia hanya dipakai
 * untuk menurunkan Sub_Divisi_Id/Divisi_Id pada dokumen, supaya jalur pelaporan
 * yang menyambung karyawan lewat ID_Divisi_Sub_Divisi — SopMonitoringPeopleService
 * dan SopPenjadwalanTargetService — tetap punya sesuatu untuk di-join.
 *
 * BINDING TIDAK PERNAH DITEBAK. Seed hanya memasangkan nama yang cocok PERSIS
 * (17 dari 52). Pencocokan mirip pernah diukur dan hasilnya berbahaya:
 * "Key Account" 70% mirip "ACCOUNTING", "Learning and Development" 76% mirip
 * "MARKETING DEVELOPMENT". Pemetaan yang salah akan tercetak di nomor dokumen.
 */
class DepartemenService
{
    private string $table = 'N_HRIS_Master_Departemen';

    public const AKTIF = 'Y';

    /** Opsi dropdown, terurut. Label siap tampil "KODE — Nama". */
    public function opsi(): array
    {
        return DB::table($this->table)
            ->where('Flag_Aktif', self::AKTIF)
            ->orderBy('Urutan')->orderBy('Kode_Departemen')
            ->get(['Id_Departemen', 'Kode_Departemen', 'Nama_Departemen', 'Sub_Divisi_Id'])
            ->map(fn ($r) => [
                'Id' => Hashids::connection('custom')->encode($r->Id_Departemen),
                'Kode_Departemen' => $r->Kode_Departemen,
                'Nama_Departemen' => $r->Nama_Departemen,
                'Terikat' => $r->Sub_Divisi_Id !== null,
            ])->all();
    }

    /** Baris departemen apa adanya. Null bila tak ada atau nonaktif. */
    public function cari(int $id, bool $hanyaAktif = true): ?object
    {
        return DB::table($this->table)
            ->where('Id_Departemen', $id)
            ->when($hanyaAktif, fn ($q) => $q->where('Flag_Aktif', self::AKTIF))
            ->first();
    }

    public function ada(int $id): bool
    {
        return $this->cari($id) !== null;
    }

    /**
     * Sub-divisi yang terikat pada sebuah departemen, atau null.
     *
     * Inilah jembatan ke data karyawan. Departemen tanpa binding mengembalikan
     * null, dan dokumen yang memakainya sengaja dibiarkan tanpa Sub_Divisi_Id —
     * bukan diisi tebakan.
     */
    public function subDivisiTerikat(int $id): ?int
    {
        $v = $this->cari($id, false)?->Sub_Divisi_Id;

        return $v === null ? null : (int) $v;
    }

    /** Label pendek untuk badge dan segmen nomor: kodenya. */
    public function kode(?int $id): ?string
    {
        return $id ? ($this->cari($id, false)?->Kode_Departemen) : null;
    }

    /**
     * Nama untuk sekumpulan id sekaligus.
     *
     * @return array<int, object>
     */
    public function peta(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        return DB::table($this->table)
            ->whereIn('Id_Departemen', $ids)
            ->get(['Id_Departemen', 'Kode_Departemen', 'Nama_Departemen'])
            ->keyBy('Id_Departemen')
            ->all();
    }

    /* ── Pengelolaan (halaman SYSTEM, gerbang system.admin) ──────────────── */

    /** @return array{0: \Illuminate\Support\Collection, 1: int} */
    public function paginate(?string $search, int $page, int $limit): array
    {
        $query = DB::table($this->table)->where('Flag_Aktif', self::AKTIF);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('Nama_Departemen', 'LIKE', "%{$search}%")
                    ->orWhere('Kode_Departemen', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();

        $rows = $query->orderBy('Urutan')->orderBy('Kode_Departemen')
            ->offset(($page - 1) * $limit)->limit($limit)->get();

        $namaSub = $this->namaSubDivisi($rows->pluck('Sub_Divisi_Id')->all());
        $pakai = $this->jumlahPemakai($rows->pluck('Id_Departemen')->all());

        $rows = $rows->map(function ($r) use ($namaSub, $pakai) {
            $id = (int) $r->Id_Departemen;
            $r->Sub_Divisi_Nama = $r->Sub_Divisi_Id ? ($namaSub[(int) $r->Sub_Divisi_Id] ?? null) : null;
            $r->Sub_Divisi_Id = $r->Sub_Divisi_Id !== null ? (int) $r->Sub_Divisi_Id : null;
            $r->Jumlah_Dokumen = $pakai[$id] ?? 0;
            $r->Id_Departemen = Hashids::connection('custom')->encode($id);

            return $r;
        });

        return [$rows, $total];
    }

    public function create(array $data, int $userId): int
    {
        $this->pastikanKodeUnik($data['Kode_Departemen']);

        return DB::table($this->table)->insertGetId([
            'Kode_Departemen' => strtoupper(trim($data['Kode_Departemen'])),
            'Nama_Departemen' => $data['Nama_Departemen'],
            'Sub_Divisi_Id' => $this->subDivisiSah($data['Sub_Divisi_Id'] ?? null),
            'Urutan' => (int) ($data['Urutan'] ?? 0),
            'Flag_Aktif' => self::AKTIF,
            'Created_By' => $userId,
            'Created_At' => now(),
        ], 'Id_Departemen');
    }

    public function update(int $id, array $data, int $userId): bool
    {
        if (! $this->cari($id)) {
            return false;
        }

        $this->pastikanKodeUnik($data['Kode_Departemen'], $id);

        DB::table($this->table)->where('Id_Departemen', $id)->update([
            /* Kode TIDAK ikut diubah: ia menjadi segmen nomor dokumen yang sudah
               tercetak, dan memindahkannya berarti memindahkan arti nomor yang
               sudah beredar tanpa satu pun jejak. */
            'Nama_Departemen' => $data['Nama_Departemen'],
            'Sub_Divisi_Id' => $this->subDivisiSah($data['Sub_Divisi_Id'] ?? null),
            'Urutan' => (int) ($data['Urutan'] ?? 0),
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        return true;
    }

    /**
     * Nonaktifkan departemen.
     *
     * @return int jumlah dokumen aktif yang masih memakainya; 0 berarti berhasil.
     */
    public function softDelete(int $id, int $userId): int
    {
        $dipakai = $this->jumlahPemakai([$id])[$id] ?? 0;

        if ($dipakai > 0) {
            return $dipakai;
        }

        DB::table($this->table)->where('Id_Departemen', $id)->update([
            'Flag_Aktif' => 'T',
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        return 0;
    }

    /** @throws \DomainException */
    private function subDivisiSah($subDivisiId): ?int
    {
        if ($subDivisiId === null || $subDivisiId === '') {
            return null;
        }

        if (! SopUnitKerja::subDivisiAda((int) $subDivisiId)) {
            throw new \DomainException('Sub-divisi yang dipilih tidak ditemukan.');
        }

        return (int) $subDivisiId;
    }

    /** @throws \DomainException */
    private function pastikanKodeUnik(string $kode, ?int $kecuali = null): void
    {
        $kode = strtoupper(trim($kode));

        $bentrok = DB::table($this->table)
            ->where('Kode_Departemen', $kode)
            ->when($kecuali, fn ($q) => $q->where('Id_Departemen', '<>', $kecuali))
            ->value('Nama_Departemen');

        if ($bentrok !== null) {
            throw new \DomainException(
                'Kode departemen "'.$kode.'" sudah dipakai "'.$bentrok.'". '
                .'Kode ini menjadi segmen nomor dokumen, jadi satu kode hanya '
                .'boleh menunjuk satu departemen.');
        }
    }

    /** @return array<int, string> */
    private function namaSubDivisi(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        return DB::table('HRIS_Sub_Divisi')
            ->whereIn('ID_Sub_Divisi', $ids)
            ->pluck('Keterangan', 'ID_Sub_Divisi')
            ->mapWithKeys(fn ($v, $k) => [(int) $k => $v])
            ->all();
    }

    /**
     * Berapa dokumen aktif memakai tiap departemen.
     *
     * Alias eksplisit pada COUNT(*) — `pluck(DB::raw('COUNT(*)'), ...)` memakai
     * teks "COUNT(*)" sebagai nama properti hasil dan meledak begitu ada baris
     * yang cocok.
     *
     * @return array<int, int>
     */
    private function jumlahPemakai(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        return DB::table('N_SOP_Dokumen')
            ->whereIn('Departemen_Id', $ids)
            ->where('Flag_Aktif', self::AKTIF)
            ->groupBy('Departemen_Id')
            ->selectRaw('Departemen_Id as id_dep, COUNT(*) as n')
            ->pluck('n', 'id_dep')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
