<?php

namespace Tests\Support\VISITOR;

use Illuminate\Support\Facades\DB;

/**
 * Pemakai uji yang boleh melihat SELURUH kategori tamu.
 *
 * Sejak penggabungan Jadwal Kedatangan Tamu ke Monitoring Tamu, laporan tamu
 * disaring MATRIKS (`N_VISITOR_Matriks_Tamu`) — bukan lagi kunci halaman.
 * Uji yang memanggil controller langsung dengan pengguna sembarang karena itu
 * memulangkan daftar kosong, dan kegagalannya menyamar jadi "fitur rusak"
 * padahal yang kurang izinnya.
 *
 * Hak diberikan lewat ROLE, bukan dengan menyuntik baris matriks baru: itulah
 * jalur yang dipakai aplikasi sungguhan, sehingga uji ini ikut membuktikan
 * jalur izinnya — bukan hanya melewatinya.
 */
trait MelihatSeluruhKategoriTamu
{
    protected function beriHakLihatSeluruhKategoriTamu(?int $userIdWeb): void
    {
        if ($userIdWeb === null) {
            return;
        }

        $idRole = DB::table('N_HRIS_Role_Functional')
            ->where('Role_Code', 'ADMIN_TAMU')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Role');

        $this->assertNotNull($idRole, 'Role ADMIN_TAMU tidak ada; uji ini kehilangan dasarnya.');

        $punya = DB::table('N_HRIS_Role_User')
            ->where('UserId_Web', $userIdWeb)
            ->where('Id_Role', $idRole)
            ->exists();

        if (! $punya) {
            // (UserId_Web, Id_Role) TIDAK unik di tabel ini, jadi penjaga di
            // atas wajib — tanpa itu uji yang dijalankan berulang meninggalkan
            // baris kembar.
            DB::table('N_HRIS_Role_User')->insert([
                'UserId_Web' => $userIdWeb,
                'Id_Role' => $idRole,
                'Assigned_At' => now(),
                'Assigned_By' => 'UJI',
                'Flag_Aktif' => 'Y',
            ]);
        }

        DB::table('N_HRIS_Role_User')
            ->where('UserId_Web', $userIdWeb)
            ->where('Id_Role', $idRole)
            ->update(['Flag_Aktif' => 'Y']);
    }

    /** Pengguna nyata yang boleh melihat seluruh kategori tamu. */
    protected function penggunaLihatSemuaTamu(string $kodeKaryawan): object
    {
        $idUser = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)->value('UserID_Web');

        $this->assertNotNull($idUser, "Karyawan {$kodeKaryawan} tidak tertaut ke pengguna web.");

        $this->beriHakLihatSeluruhKategoriTamu((int) $idUser);

        return (object) ['Id_Users' => (int) $idUser, 'Kode_Karyawan' => $kodeKaryawan];
    }

    /**
     * Pengguna nyata yang melihat seluruh kategori tamu DAN hanya memegang
     * kunci halaman yang disebut. Endpoint papan/antrean/analitik bersama
     * memeriksa ulang kunci per ranah, jadi objek polos tanpa
     * memilikiJabatan() ditolak (gagal-tertutup).
     *
     * @param  list<string>  $kunci
     */
    protected function penggunaBerkunci(array $kunci): object
    {
        $kode = (string) DB::table('Karyawan')->whereNotNull('UserID_Web')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');
        $dasar = $this->penggunaLihatSemuaTamu($kode);

        return new class($dasar->Id_Users, $kode, $kunci)
        {
            public function __construct(public int $Id_Users, public string $Kode_Karyawan, private array $kunci) {}

            public function memilikiJabatan(string $page): bool
            {
                return in_array($page, $this->kunci, true);
            }
        };
    }
}
