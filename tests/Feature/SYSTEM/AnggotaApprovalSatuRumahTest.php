<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * "Siapa anggota langkah approval ini" dijawab di satu rumah.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * Pertanyaan ini dijawab EMPAT KALI di tempat berbeda, dalam dua pasang yang
 * masing-masing identik baris demi baris. Dan duplikasi itu bukan sekadar
 * boros — ia MENYEMBUNYIKAN CACAT: cabang non-ROLE di kedua salinan menyaring
 * kolom `Group_Reference_Id` dan men-join `Kode_Karyawan` pada tabel
 * N_HRIS_HC_Approval, padahal tabel itu tidak punya kolom bernama demikian.
 * Nama yang benar adalah `Group_Id` dan `Kode_Karyawan_Approver`.
 *
 * Artinya cabang itu MUSTAHIL berjalan: SQL Server menolaknya dengan
 * "Invalid column name", dan pemanggilnya tidak membungkus try/catch.
 *
 * Bukan cabang mati, pula: tujuh dari sebelas jenis approval bukan ROLE, dan
 * jenis "HC Ops" memang menunjuk tabel itu. Cacatnya bertahan justru karena
 * kodenya tersalin di dua controller dan jalur non-ROLE tak pernah dijalankan
 * siapa pun.
 */
class AnggotaApprovalSatuRumahTest extends TestCase
{
    private const RUMAH = 'app/Services/SYSTEM/Governance/Approval/Shared/AnggotaApprovalResolver.php';

    /**
     * Keempat berkas yang dulu memegang salinannya sendiri, dan lewat mana
     * masing-masing sekarang sampai ke rumah bersamanya.
     *
     * Dua controller memanggil resolver-nya langsung. Kedua notification
     * service TIDAK lagi menyebut resolver itu: sesudah pipa notif kolam role
     * ikut dipusatkan, merekalah yang memanggil NotifikasiKolamRole, dan
     * NotifikasiKolamRole yang memanggil resolver. Yang dijaga tetap sama —
     * tidak boleh ada salinan lokal — hanya perantaranya yang bertambah satu.
     */
    private const BEKAS_PEMILIK_SALINAN = [
        'app/Http/Controllers/KPI/Approval/Performance/PerformanceApprovalController.php' => 'AnggotaApprovalResolver::class',
        'app/Http/Controllers/KPI/Approval/Workflow/KpiApprovalController.php' => 'AnggotaApprovalResolver::class',
        'app/Services/ESS/Attendance/Leave/Notification/CutiNotificationService.php' => 'NotifikasiKolamRole::class',
        'app/Services/KPI/Notification/KpiNotificationService.php' => 'NotifikasiKolamRole::class',
    ];

    private function rumah(): string
    {
        return (string) file_get_contents(base_path(self::RUMAH));
    }

    public function test_tidak_ada_lagi_salinan_di_pemanggil(): void
    {
        foreach (self::BEKAS_PEMILIK_SALINAN as $berkas => $perantara) {
            $isi = (string) file_get_contents(base_path($berkas));

            $this->assertDoesNotMatchRegularExpression(
                '/function (resolveGroupMembers|resolveRoleMembers)\s*\(/',
                $isi,
                "{$berkas} memegang salinannya sendiri lagi. Empat salinan itu yang ".
                'menyembunyikan cacat nama kolom selama ini.',
            );

            $this->assertStringContainsString(
                $perantara,
                $isi,
                "{$berkas} tidak lagi sampai ke rumah bersamanya lewat {$perantara}.",
            );
        }
    }

    public function test_cabang_non_role_memakai_nama_kolom_yang_benar(): void
    {
        $isi = $this->rumah();

        /*
         * Inti PR ini. Kedua nama lama membuat cabangnya melempar
         * "Invalid column name" setiap kali dijalankan; keduanya diperiksa
         * terpisah supaya pesan gagalnya menunjuk yang mana.
         */
        $this->assertStringContainsString(
            "->where('Group_Id',",
            $isi,
            'Cabang non-ROLE harus menyaring Group_Id. N_HRIS_HC_Approval tidak '.
            'punya kolom Group_Reference_Id — memakainya membuat cabang ini selalu gagal.',
        );

        $this->assertStringContainsString(
            "'N_HRIS_HC_Approval.Kode_Karyawan_Approver'",
            $isi,
            'Join harus ke Kode_Karyawan_Approver. N_HRIS_HC_Approval tidak punya '.
            'kolom Kode_Karyawan.',
        );

        $this->assertDoesNotMatchRegularExpression(
            "/->where\('Group_Reference_Id',/",
            $isi,
            'Nama kolom yang salah kembali dipakai untuk menyaring N_HRIS_HC_Approval.',
        );

        $this->assertDoesNotMatchRegularExpression(
            "/'N_HRIS_HC_Approval\.Kode_Karyawan'/",
            $isi,
            'Join ke kolom yang tidak ada kembali dipakai.',
        );
    }

    public function test_kedua_metode_tetap_berbeda_dan_alasannya_tertulis(): void
    {
        $isi = $this->rumah();

        /*
         * dariStepApproval() dan dariRole() menjawab pertanyaan yang mirip
         * dengan saringan berbeda, dan perbedaannya SENGAJA. Tanpa alasan yang
         * tertulis, pembaca berikutnya akan menganggapnya kelalaian lalu
         * menyatukannya — dan itu mengubah siapa yang menerima notifikasi
         * approval.
         */
        $this->assertStringContainsString(
            "->where('rf.Role_Code', \$jenisApproval->Source_Reference)",
            $isi,
            'dariStepApproval() kehilangan saringan Role_Code. Satu role bisa dipakai '.
            'beberapa jenis approval; tanpa saringan itu langkahnya menyapu anggota '.
            'jenis lain.',
        );

        $this->assertStringContainsString(
            "->whereNull('k.Tanggal_Resign')",
            $isi,
            'dariRole() kehilangan penyaring karyawan resign.',
        );

        $this->assertStringContainsString(
            '->distinct()',
            $isi,
            'dariRole() kehilangan distinct(). Pasangan (UserId_Web, Id_Role) di '.
            'N_HRIS_Role_User tidak unik — terukur 9 kelompok kembar.',
        );

        $this->assertStringContainsString(
            'JANGAN digabung tanpa keputusan produk',
            $isi,
            'Alasan kedua metode dibiarkan berbeda harus tertulis, kalau tidak ia akan '.
            '"dirapikan" oleh orang berikutnya.',
        );
    }
}
