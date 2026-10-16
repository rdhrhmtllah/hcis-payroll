<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopPaketVersionService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Revisi yang sedang berjalan tak boleh menahan rilis pertama paket.
 *
 * Laporan user 5 Okt 2026 (paket 7181 di dev): paket berisi satu dokumen FORM
 * yang v00-nya sudah terbit, tetapi publish ditolak "Masih ada versi item
 * berstatus Draft". Yang terhitung adalah v01 — revisi yang sedang menunggu
 * pengesahan di Master Dokumen. Penjaga itu warisan dari sebelum alur revisi
 * dokumen ada: dulu versi draft berarti itemnya belum punya isi untuk
 * dirilis, sekarang dokumen yang direvisi tetap punya versi terbit yang sah.
 *
 * Aturannya kini: item belum siap rilis hanya bila dokumennya belum punya
 * versi TERBIT atau SIAP RILIS sama sekali.
 */
class SopPublishPaketRevisiDraftTest extends TestCase
{
    use DatabaseTransactions;

    /** @return array{0:int,1:string} [idPaket, judulDokumen] */
    private function paketDenganDokumen(bool $punyaVersiTerbit): array
    {
        $svc = app(SopDokumenService::class);
        $judul = 'UJI PUBLISH REVISI '.substr((string) microtime(true), -5);
        $idDok = $svc->buat(['Judul' => $judul, 'Tipe' => 'FORM'], 1);
        $v0 = (int) $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Awal'], 1);
        if ($punyaVersiTerbit) {
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v0)->update([
                'Status' => SopStatus::VERSI_PUBLISHED,
                'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', 'FILING_REGISTERED')->value('Id_Tahap'),
            ]);
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->update(['Id_Versi_Aktif' => $v0]);
            // Revisi berikutnya yang masih draft.
            $svc->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Revisi berjalan'], 1);
        }

        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'UJI PAKET PUBLISH '.substr((string) microtime(true), -5),
            'Prioritas' => 'KRITIS', 'Mode_Urutan_Item' => 'BEBAS', 'Status' => SopStatus::PAKET_DRAFT,
            'Flag_Absensi' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Paket');
        $idSection = DB::table('N_SOP_Paket_Section')->insertGetId([
            'Id_SOP_Paket' => $idPaket, 'Nama_Section' => 'Umum', 'Urutan' => 1,
            'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Section');
        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket, 'Id_SOP_Section' => $idSection, 'Id_SOP_Dokumen' => $idDok,
            'Id_Item_Versi_Aktif' => $punyaVersiTerbit ? $v0 : null,
            'Tipe_Item' => 'FORM', 'Judul_Item' => $judul, 'Urutan' => 1, 'Flag_Wajib' => 'Y',
            'Flag_Highlight' => 'T', 'Flag_Watermark' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ]);

        return [$idPaket, $judul];
    }

    public function test_revisi_berjalan_tidak_dihitung_belum_siap_rilis(): void
    {
        [$idPaket] = $this->paketDenganDokumen(punyaVersiTerbit: true);

        $ringkasan = app(SopPaketVersionService::class)->pendingChanges($idPaket)['summary'];

        $this->assertSame(0, $ringkasan['draft_item_versions'],
            'Dokumen yang sudah terbit dan sedang direvisi tetap siap dirilis lewat versi terbitnya.');
    }

    public function test_dokumen_tanpa_versi_terbit_tetap_menahan_rilis(): void
    {
        [$idPaket] = $this->paketDenganDokumen(punyaVersiTerbit: false);

        $ringkasan = app(SopPaketVersionService::class)->pendingChanges($idPaket)['summary'];

        $this->assertSame(1, $ringkasan['draft_item_versions']);
    }

    public function test_penolakan_menyebut_dokumen_yang_belum_siap(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopBuilderPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopBuilderPage.');
        }
        [$idPaket, $judul] = $this->paketDenganDokumen(punyaVersiTerbit: false);

        $this->actingAs($admin)
            ->patchJson('/api/v1/sop/admin/builder/paket/status/'.Hashids::connection('custom')->encode($idPaket), [
                'status' => SopStatus::PAKET_PUBLISHED,
                'Ringkasan_Perubahan' => 'Rilis pertama',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, $judul));
    }
}
