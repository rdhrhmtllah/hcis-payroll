<?php

namespace Tests\Integration\VISITOR\Workflow\ProductionHardening;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorProductionHardeningIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_development_schema_has_lifecycle_and_idempotency_contract(): void
    {
        $columns = DB::table('INFORMATION_SCHEMA.COLUMNS')
            ->where(function ($query) {
                $query->where(function ($nested) {
                    $nested->where('TABLE_NAME', 'N_VISITOR_Tamu_Efektif')
                        ->whereIn('COLUMN_NAME', ['Waktu_Aktual_Masuk', 'Waktu_Aktual_Keluar']);
                })->orWhere(function ($nested) {
                    $nested->whereIn('TABLE_NAME', [
                        'N_EMI_VISITOR_Transaksi_Permit',
                        'N_EMI_VISITOR_Transaksi_Tamu',
                    ])->where('COLUMN_NAME', 'Idempotency_Key');
                });
            })
            ->get()
            ->keyBy(fn ($row) => $row->TABLE_NAME.'.'.$row->COLUMN_NAME);

        $this->assertCount(4, $columns);
        $this->assertSame('datetime2', $columns['N_VISITOR_Tamu_Efektif.Waktu_Aktual_Masuk']->DATA_TYPE);
        $this->assertSame('datetime2', $columns['N_VISITOR_Tamu_Efektif.Waktu_Aktual_Keluar']->DATA_TYPE);
        $this->assertSame(100, (int) $columns['N_EMI_VISITOR_Transaksi_Permit.Idempotency_Key']->CHARACTER_MAXIMUM_LENGTH);
        $this->assertSame(100, (int) $columns['N_EMI_VISITOR_Transaksi_Tamu.Idempotency_Key']->CHARACTER_MAXIMUM_LENGTH);

        $indexes = DB::table('sys.indexes')
            ->whereIn('name', [
                'UX_VISITOR_Permit_Requester_Idempotency',
                'UX_VISITOR_Tamu_Requester_Idempotency',
            ])->get()->keyBy('name');

        $this->assertCount(2, $indexes);
        foreach ($indexes as $index) {
            $this->assertSame(1, (int) $index->is_unique);
            $this->assertSame(1, (int) $index->has_filter);
        }
    }

    /** @dataProvider idempotencyTableProvider */
    public function test_idempotency_key_is_unique_per_requester(
        string $table,
        string $requesterColumn,
    ): void {
        $key = 'integration-'.Str::uuid();

        DB::table($table)->insert([
            'No_Transaksi' => 'UJI-'.Str::uuid(),
            $requesterColumn => 'UJI-A',
            'Idempotency_Key' => $key,
        ]);
        DB::table($table)->insert([
            'No_Transaksi' => 'UJI-'.Str::uuid(),
            $requesterColumn => 'UJI-B',
            'Idempotency_Key' => $key,
        ]);

        $this->expectException(QueryException::class);
        DB::table($table)->insert([
            'No_Transaksi' => 'UJI-'.Str::uuid(),
            $requesterColumn => 'UJI-A',
            'Idempotency_Key' => $key,
        ]);
    }

    public static function idempotencyTableProvider(): array
    {
        return [
            ['N_EMI_VISITOR_Transaksi_Permit', 'Kode_Karyawan_Pemohon'],
            ['N_EMI_VISITOR_Transaksi_Tamu', 'Kode_Karyawan_PIC'],
        ];
    }

    public function test_guest_query_prefers_external_effective_lifecycle(): void
    {
        $noTransaksi = 'UJI-'.Str::upper(Str::random(12));
        $headerId = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_PIC' => 'UJI-PIC',
            'Tanggal_Kunjungan' => '2026-08-23',
            'Status_Kunjungan' => 'SCHEDULED',
            'Flag_Aktif' => 'Y',
        ], 'Id_Transaksi_Tamu');

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $headerId,
            'No_Transaksi' => $noTransaksi,
            'Nama_Lengkap_Tamu' => 'Tamu Integrasi',
            'Waktu_Aktual_Masuk' => null,
        ]);

        DB::table('N_VISITOR_Tamu_Efektif')->insert([
            'No_Transaksi' => $noTransaksi,
            'Nama_Tamu' => 'Tamu Integrasi',
            'Kode_Karyawan_PIC' => 'UJI-PIC',
            'Nama_PIC' => 'PIC Integrasi',
            'Tanggal_Kunjungan' => '2026-08-23',
            // AKTIF, bukan CHECKED_IN: sejak DDL-2026-121 status tampil diturunkan
            // dari jam palang, bukan disalin dari Status_Efektif.
            'Status_Efektif' => 'AKTIF',
            'Waktu_Aktual_Masuk' => '2026-08-23 09:15:00',
            'Disetujui_Oleh_Nama' => 'Approver Integrasi',
            'Disetujui_At' => '2026-08-23 08:00:00',
            'Riwayat_Approval' => '[]',
            'Dibuat_At' => '2026-08-23 07:00:00',
            'Dibuat_Oleh_Nama' => 'PIC Integrasi',
            'Updated_At' => '2026-08-23 09:15:00',
        ]);

        $row = app(VisitorPermitQueryService::class)
            ->baseTamuQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();

        $this->assertSame('SCHEDULED', $row->Status_Pengajuan);
        $this->assertSame('CHECKED_IN', $row->Status_Kunjungan);
        $this->assertStringStartsWith('2026-08-23 09:15:00', (string) $row->Waktu_Aktual_Masuk);
    }

    public function test_approved_permit_without_dates_requires_validity_remediation(): void
    {
        $noTransaksi = 'UJI-'.Str::upper(Str::random(12));
        $headerId = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => 'UJI-PEMOHON',
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
        ], 'Id_Transaksi_Permit');

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $headerId,
            'No_Transaksi' => $noTransaksi,
            'Tanggal_Mulai_Berlaku' => null,
            'Tanggal_Kadaluarsa' => null,
        ]);

        $row = app(VisitorPermitQueryService::class)
            ->basePermitQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();

        $this->assertSame('VALIDITY_REQUIRED', $row->Status_Permit);
    }
}
