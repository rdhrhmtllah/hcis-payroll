<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorPermitChecklistSelarasTest extends TestCase
{
    use DatabaseTransactions;

    private int $katA;

    private int $katB;

    private int $qUmum;

    private int $qA;

    private int $qB;

    protected function setUp(): void
    {
        parent::setUp();

        // Pertanyaan master lain dinonaktifkan dalam transaksi uji supaya
        // hitungan tidak bergantung pada isi master development.
        DB::table('N_EMI_VISITOR_Master_Approval_Question')->update(['Flag_Aktif' => 'T']);

        $this->katA = $this->kategori();
        $this->katB = $this->kategori();
        $this->qUmum = $this->pertanyaan('Umum', null);
        $this->qA = $this->pertanyaan('Khusus A', $this->katA);
        $this->qB = $this->pertanyaan('Khusus B', $this->katB);
    }

    public function test_pratinjau_menghitung_tanpa_menulis(): void
    {
        [$no] = $this->permitDenganChecklist($this->katA);
        $sebelum = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->get();

        $hasil = $this->svc()->pratinjauSelaras($no, $this->katB, null);

        $this->assertSame(1, $hasil['dipertahankan']);
        $this->assertSame([['teks' => 'Khusus A', 'terjawab' => true]], $hasil['dibuang']);
        $this->assertSame([['teks' => 'Khusus B']], $hasil['ditambah']);
        $this->assertEquals($sebelum, DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->get());
    }

    public function test_selaraskan_mempertahankan_jawaban_yang_masih_berlaku(): void
    {
        [$no, $idDetail] = $this->permitDenganChecklist($this->katA);

        $hasil = $this->svc()->selaraskanSnapshot($no, $idDetail, $this->katB, null);

        $this->assertSame(['dibuang' => 1, 'ditambah' => 1], $hasil);
        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)
            ->get()->keyBy(fn ($r) => (int) $r->Id_Master_Pertanyaan);

        $this->assertSame([$this->qUmum, $this->qB], $baris->keys()->sort()->values()->all());
        $this->assertSame('YA', $baris[$this->qUmum]->Jawaban_Admin);
        $this->assertNull($baris[$this->qB]->Jawaban_Admin);
    }

    public function test_selaraskan_tanpa_selisih_tidak_mengubah_apa_pun(): void
    {
        [$no, $idDetail] = $this->permitDenganChecklist($this->katA);

        $this->assertSame(['dibuang' => 0, 'ditambah' => 0], $this->svc()->selaraskanSnapshot($no, $idDetail, $this->katA, null));
    }

    private function svc(): VisitorPermitChecklistService
    {
        return app(VisitorPermitChecklistService::class);
    }

    private function kategori(): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Kategori_Permit')->insertGetId([
            'Nama_Kategori' => 'UJI '.Str::upper(Str::random(8)),
            'Flag_Aktif' => 'Y',
            'Flag_Muncul_Pengajuan' => 'Y',
        ]);
    }

    private function pertanyaan(string $teks, ?int $kategori): int
    {
        return (int) DB::table('N_EMI_VISITOR_Master_Approval_Question')->insertGetId([
            'Teks_Pertanyaan' => $teks,
            'Target_Id_Kategori_Permit' => $kategori,
            'Target_Id_Tipe_Kendaraan' => null,
            'Flag_Aktif' => 'Y',
        ]);
    }

    /** @return array{0:string,1:int} nomor permit, id detail — checklist terjawab 'YA' semua */
    private function permitDenganChecklist(int $kategori): array
    {
        $no = 'PMT-SLR-'.Str::upper(Str::random(8));
        $idHeader = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $no, 'Kode_Karyawan_Pemohon' => 'A1', 'Sumber_Pengajuan' => 'HCIS',
            'Flag_Aktif' => 'Y', 'Created_At' => now(), 'Created_By' => 'A1',
            'Idempotency_Key' => 'SLR-'.Str::upper(Str::random(20)),
        ]);
        $idDetail = (int) DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insertGetId([
            'Id_Transaksi_Permit' => $idHeader, 'No_Transaksi' => $no, 'Id_Master_Kategori_Permit' => $kategori,
        ]);
        $this->svc()->buatSnapshot($no, $idDetail, $kategori, null);
        DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->where('No_Transaksi', $no)->update(['Jawaban_Admin' => 'YA']);

        return [$no, $idDetail];
    }
}
