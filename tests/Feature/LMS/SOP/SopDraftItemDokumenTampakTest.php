<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Item pada paket TERBIT harus tetap memperlihatkan dokumennya.
 *
 * N_SOP_Paket_Draft_Item tak punya kolom Id_SOP_Dokumen sama sekali — tautannya
 * hidup di item LIVE. Selama daftar overlay tak membawanya, setiap item pada
 * paket terbit tampil di Builder seolah BELUM bertaut dokumen: kartunya kosong
 * dan layar menawarkan "Pilih Dokumen SOP yang sudah ada" untuk item yang jelas
 * sudah punya. Apa pun yang dipilih di sana tak punya tempat untuk disimpan,
 * jadi sesudah Simpan keadaannya kembali kosong — persis yang dilaporkan.
 */
class SopDraftItemDokumenTampakTest extends TestCase
{
    use DatabaseTransactions;

    public function test_daftar_overlay_membawa_dokumen_item_live(): void
    {
        /* Paket TERBIT yang itemnya bertaut dokumen. Working draftnya dibuat
           oleh items() sendiri lewat ensure(), jadi tak perlu disiapkan. */
        $paket = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->first(['p.Id_SOP_Paket', 'i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen']);

        if (! $paket) {
            $this->markTestSkipped('Tak ada paket terbit berisi item ber-dokumen.');
        }

        $rows = collect(app(SopPaketDraftService::class)->items((int) $paket->Id_SOP_Paket, 1));
        if ($rows->isEmpty()) {
            $this->markTestSkipped('Working draft paket itu kosong.');
        }

        $baris = $rows->first(fn ($r) => (int) ($r->Id_SOP_Paket_Item_Asal ?? 0) === (int) $paket->Id_SOP_Paket_Item);
        $this->assertNotNull($baris, 'Item live itu tak muncul di daftar overlay.');

        $this->assertNotEmpty($baris->Id_SOP_Dokumen ?? null,
            'Barisnya tak membawa dokumen — Builder akan menampilkannya seolah belum bertaut, '
            .'lalu menawarkan pemilih yang hasilnya tak bisa disimpan.');

        // Terkodekan seperti id lain: form mengirimnya balik apa adanya, dan id
        // mentah gagal decode di terapkanDokumen().
        $this->assertSame(
            (int) $paket->Id_SOP_Dokumen,
            (int) (Hashids::connection('custom')->decode($baris->Id_SOP_Dokumen)[0] ?? 0),
            'Dokumennya tak terkodekan, atau menunjuk dokumen yang salah.',
        );
    }
}
