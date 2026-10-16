<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Siapa saja anggota sebuah langkah approval berbasis group/role.
 *
 * ── Kenapa ini punya rumah ───────────────────────────────────────────────────
 *
 * Pertanyaan ini dijawab EMPAT KALI di tempat berbeda, dalam dua pasang yang
 * masing-masing identik baris demi baris:
 *
 *   dariStepApproval()  <- PerformanceApprovalController, KpiApprovalController
 *   dariRole()          <- CutiNotificationService, KpiNotificationService
 *
 * Empat salinan berarti setiap koreksi hanya sampai ke sebagian pemanggil.
 *
 * ── Dua metode, dan JANGAN digabung tanpa keputusan produk ───────────────────
 *
 * Keduanya menjawab "siapa anggotanya", tetapi TIDAK setara, dan perbedaannya
 * bukan kelalaian:
 *
 *   dariStepApproval() menyaring `rf.Role_Code = Source_Reference` — satu role
 *   bisa dipakai beberapa jenis approval, dan tanpa saringan itu langkahnya
 *   menyapu anggota jenis lain.
 *
 *   dariRole() TIDAK punya saringan itu (pemanggilnya cuma tahu Id_Role),
 *   tetapi menambahkan `whereNull(Tanggal_Resign)` dan `distinct()`.
 *
 * Soal distinct(): pasangan (UserId_Web, Id_Role) di N_HRIS_Role_User TIDAK
 * unik — terukur 9 kelompok kembar, satu pengguna sampai muncul 4x untuk role
 * yang sama. Pada data sekarang saringan Flag_Aktif kebetulan menutupinya
 * (diperiksa: nol anggota ganda di keenam role yang diuji), jadi distinct() di
 * sini adalah PENGAMAN, bukan penambal bug yang sedang menyala. Ia tetap
 * dipertahankan apa adanya: menghapusnya berarti bertaruh pada kebetulan itu.
 *
 * Menyamakan kedua metode akan mengubah siapa yang menerima notifikasi
 * approval. Itu keputusan produk, bukan perapian kode.
 */
class AnggotaApprovalResolver
{
    /**
     * Anggota dari satu langkah approval CENTRAL — menghormati Source_Type-nya.
     *
     * ROLE  : anggota diambil dari role functional, disaring Role_Code.
     * lainnya: anggota diambil dari tabel group member (N_HRIS_HC_Approval).
     *
     * @return Collection<int, object>
     */
    public function dariStepApproval(object $next): Collection
    {
        $jenisApproval = DB::table('N_HRIS_Jenis_Approval')
            ->where('Id_Jenis_Approval', (int) ($next->Id_Jenis_Approval ?? 0))
            ->first();

        // ROLE-based group: anggota berasal dari role functional
        if ($jenisApproval && strtoupper((string) ($jenisApproval->Source_Type ?? '')) === 'ROLE') {
            return DB::table('N_HRIS_Role_Functional as rf')
                ->join('N_HRIS_Role_User as ru', 'ru.Id_Role', '=', 'rf.Id_Role')
                ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
                ->where('rf.Id_Role', (int) ($next->Group_Reference_Id ?? 0))
                ->where('rf.Role_Code', $jenisApproval->Source_Reference)
                ->where('rf.Flag_Aktif', 'Y')
                ->where('ru.Flag_Aktif', 'Y')
                ->where('k.Aktif', 'Y')
                ->select('k.Kode_Karyawan', 'k.Nama')
                ->get();
        }

        /*
         * Non-ROLE group: anggota dari tabel group member.
         *
         * DUA NAMA KOLOM DIPERBAIKI DI SINI, dan keduanya membuat cabang ini
         * MUSTAHIL berjalan sebelumnya:
         *
         *   Group_Reference_Id -> Group_Id
         *   Kode_Karyawan      -> Kode_Karyawan_Approver
         *
         * N_HRIS_HC_Approval tidak punya kolom bernama Group_Reference_Id
         * maupun Kode_Karyawan (diperiksa di INFORMATION_SCHEMA), jadi setiap
         * kali langkah approval ber-Source_Type selain 'ROLE' sampai ke sini,
         * SQL Server menolak dengan "Invalid column name" dan pemanggilnya —
         * yang tidak membungkus try/catch — ikut gagal.
         *
         * Ini BUKAN cabang mati: tujuh dari sebelas jenis approval bukan ROLE,
         * dan jenis "HC Ops" (#1 GROUP_TABLE, #9 TABLE) memang menunjuk tabel
         * ini. Di data sekarang ada 6 permintaan approval ber-Group_Reference_Id
         * = 100, dan Group_Id 100 punya 2 anggota — pasangannya cocok persis.
         *
         * Cacat ini tersembunyi justru karena kodenya tersalin di dua
         * controller dan tak pernah ada yang menjalankan jalur non-ROLE.
         */
        return DB::table('N_HRIS_HC_Approval')
            ->where('Group_Id', (int) ($next->Group_Reference_Id ?? 0))
            ->where('N_HRIS_HC_Approval.Flag_Aktif', 'Y')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'N_HRIS_HC_Approval.Kode_Karyawan_Approver')
            ->select('k.Kode_Karyawan', 'k.Nama')
            ->get();
    }

    /**
     * Anggota dari sebuah role, saat pemanggil hanya memegang Id_Role.
     *
     * Lihat catatan kelas di atas: ini BUKAN versi ringkas dari
     * dariStepApproval(). Ia tidak menyaring Role_Code, dan sebagai gantinya
     * membuang karyawan yang sudah resign serta men-dedup barisnya.
     *
     * @return Collection<int, object>
     */
    public function dariRole(int $idRole): Collection
    {
        if ($idRole <= 0) {
            return collect();
        }

        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
            ->where('rf.Id_Role', $idRole)
            ->where('rf.Flag_Aktif', 'Y')
            ->where('ru.Flag_Aktif', 'Y')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->select('k.Kode_Karyawan', 'k.Nama')
            ->distinct()
            ->get();
    }
}
