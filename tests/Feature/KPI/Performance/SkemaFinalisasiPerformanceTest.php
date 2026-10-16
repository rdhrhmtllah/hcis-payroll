<?php

namespace Tests\Feature\KPI\Performance;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Setiap kolom yang ditulis finalisasi KPI harus benar-benar ada di skema.
 *
 * ── Kenapa penjaga ini ada ──────────────────────────────────────────────────
 *
 * DDL-2026-093 menambahkan kolom Metode_Cap, dan kodenya menstempel kolom itu
 * pada setiap finalisasi. Keduanya punya URUTAN PENGIKAT: DDL dulu, baru kode.
 * Ketika kodenya berjalan di lingkungan yang DDL-nya belum dijalankan,
 * kegagalannya tidak muncul saat rilis, tidak muncul saat halaman dibuka, dan
 * tidak muncul saat draft disimpan — ia baru meledak di detik terakhir, saat
 * seseorang menekan Finalisasi:
 *
 *   FINALISASI_PERFORMANCE_ERROR: Invalid column name 'Metode_Cap'
 *
 * Terjadi di lingkungan lokal 10 September 2026 (status DDL-093 saat itu:
 * development = NOT_APPLICABLE).
 *
 * Penjaga ini memeriksa BENTUK SKEMA, bukan perilaku, dan itu disengaja: yang
 * dijaga adalah kesenjangan antara kode dan database, dan kesenjangan itu tidak
 * bisa dilihat dari kodenya saja.
 *
 * Kalau kelak ada kolom baru yang ikut ditulis finalisasi, tambahkan namanya di
 * sini bersamaan dengan DDL-nya — daftar yang tidak diperbarui hanya memindahkan
 * kejutannya ke pengguna berikutnya.
 */
class SkemaFinalisasiPerformanceTest extends TestCase
{
    /**
     * Kolom yang ditulis processPerformanceTransactionByTeam() dan kembarannya.
     *
     * @var array<int,string>
     */
    private const KOLOM_WAJIB = [
        'No_Transaksi',
        'No_Transaksi_Ref',
        'Id_Pengajuan',
        'Id_Penjadwalan',
        'Periode',
        'Total_Score',
        'Total_Score_Achievement',
        'Total_Bobot',
        'Total_Score_Raw',
        'Total_Score_Achievement_Raw',
        'Flag_Capped',
        'Metode_Cap',
        'Cap_Min_Applied',
        'Cap_Max_Applied',
        'Id_Performance',
        'Id_User_Sender',
        'Id_User',
        'Tanggal',
        'Jam',
        'Status_Pengajuan',
        'Flag_Aktif',
        'Flag_Team',
    ];

    public function test_semua_kolom_yang_ditulis_finalisasi_ada_di_skema(): void
    {
        $ada = collect(DB::select(
            'SELECT c.name FROM sys.columns c WHERE c.object_id = OBJECT_ID(?)',
            ['KPI_Update_Performance'],
        ))->pluck('name')->map(fn ($n) => strtolower($n));

        $hilang = collect(self::KOLOM_WAJIB)
            ->reject(fn ($k) => $ada->contains(strtolower($k)))
            ->values();

        $this->assertTrue(
            $hilang->isEmpty(),
            'Kolom yang ditulis finalisasi tidak ada di KPI_Update_Performance: '.$hilang->implode(', ').'. '.
            'Jalankan DDL yang bersangkutan lebih dulu — kalau tidak, finalisasi KPI gagal '.
            'tepat saat pengguna menekan tombolnya, bukan sebelumnya.',
        );
    }

    public function test_metode_cap_terkunci_pada_nilai_yang_dikenal(): void
    {
        $check = DB::selectOne(
            'SELECT definition FROM sys.check_constraints
              WHERE parent_object_id = OBJECT_ID(?) AND name = ?',
            ['KPI_Update_Performance', 'CK_KPI_Update_Performance_Metode_Cap'],
        );

        $this->assertNotNull(
            $check,
            'CK_KPI_Update_Performance_Metode_Cap tidak ada. Tanpa itu, salah ketik pada penanda '.
            'metode cap tersimpan diam-diam dan backfill kelak tak bisa membedakan cap lama dari cap baru.',
        );

        foreach (['PARAMETER', 'TOTAL'] as $nilai) {
            $this->assertStringContainsString($nilai, (string) $check->definition);
        }
    }
}
