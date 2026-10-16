<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\SopItemVersionService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\Feature\LMS\SOP\Uat\Support\UatTahapVersi;
use Tests\TestCase;

/**
 * Builder paket SOP — jalur normal.
 *
 * UAT-SOP-015 membuat paket · 017 menyisipkan dokumen terkendali · 018 menyalin
 * item ke paket lain dengan tautan yang tetap HIDUP.
 *
 * 018 adalah inti seluruh gagasan Master Dokumen, dan satu-satunya cara
 * membuktikannya adalah dengan MEREVISI dokumennya setelah disalin. Salinan
 * mati dan tautan hidup terlihat persis sama pada saat penyalinan; bedanya baru
 * muncul pada revisi berikutnya — dan di situlah dulu kesalahannya bersembunyi
 * sampai data produksi menyimpang sendiri.
 */
class Alur015BuilderPaketTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket()
                ->tambahItem('Item Asal');
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    #[Group('UAT-SOP-015')]
    public function test_paket_baru_lahir_sebagai_draft_dan_tertutup_secara_bawaan(): void
    {
        $d = $this->dunia();

        $p = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $d->idPaket)->first();

        $this->assertSame(SopStatus::PAKET_DRAFT, $p->Status,
            'Paket baru langsung terbit. Rakitan yang belum selesai akan tersaji ke '
            .'karyawan sebelum admin sempat memeriksanya.');
        $this->assertSame('TERBATAS', $p->Mode_Distribusi,
            'Paket baru terbuka untuk umum secara bawaan. Keterbukaan harus dipilih '
            .'sadar, bukan kebetulan.');
    }

    #[Group('UAT-SOP-015')]
    public function test_urutan_item_mengikuti_urutan_penambahan_dan_bisa_diubah(): void
    {
        $d = $this->dunia();
        $d->tambahItem('Item Kedua');

        $itemSvc = app(SopItemService::class);
        $urutAwal = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket_Item', $d->idItem)
            ->orderBy('Urutan')->pluck('Id_SOP_Paket_Item')->map(fn ($x) => (int) $x)->all();

        $this->assertSame($d->idItem, $urutAwal);

        /* reorder() memakai POSISI di dalam array sebagai Urutan ($i + 1);
           kunci 'urutan' tidak dibaca sama sekali. Menyertakannya menciptakan
           kesan uji ini menetapkan nomor urut padahal yang menentukan hanyalah
           susunannya. */
        $itemSvc->reorder([
            ['id' => $d->idItem[1]],
            ['id' => $d->idItem[0]],
        ]);

        $urutBaru = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket_Item', $d->idItem)
            ->orderBy('Urutan')->pluck('Id_SOP_Paket_Item')->map(fn ($x) => (int) $x)->all();

        $this->assertSame(array_reverse($d->idItem), $urutBaru,
            'Urutan tidak berubah setelah disusun ulang. Urutan baca yang dirancang admin '
            .'tidak sampai ke karyawan.');
    }

    #[Group('UAT-SOP-017')]
    public function test_item_menunjuk_dokumen_terkendali_bukan_menyalin_identitasnya(): void
    {
        $d = $this->dunia();

        $item = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $d->idItem[0])->first();
        $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $d->idDokumen)->first();

        $this->assertSame($d->idDokumen, (int) $item->Id_SOP_Dokumen,
            'Item tidak menunjuk dokumen terkendali mana pun, sehingga tata kelolanya '
            .'berdiri sendiri — persis keadaan yang Master Dokumen ada untuk mengakhiri.');
        $this->assertSame($dok->Nomor_SOP_Resmi, $item->Nomor_SOP_Resmi,
            'Nomor Dokumen pada item berbeda dari nomor pada dokumennya.');
    }

    /**
     * INTI SKENARIO 018. Setelah disalin, dokumennya DIREVISI — dan kedua paket
     * harus ikut. Memeriksa hanya "keduanya menunjuk dokumen yang sama" tidak
     * cukup: salinan mati pun menunjuk dokumen yang sama pada detik penyalinan.
     */
    #[Group('UAT-SOP-018')]
    public function test_item_yang_disalin_tetap_seiring_saat_dokumennya_direvisi(): void
    {
        $d = $this->dunia();
        $lain = $d->paketTambahan('Paket Tujuan');

        /* Id section diambil dari fixture, BUKAN dari SopSectionService::byPaket()
           — layanan itu meng-hash Id_SOP_Section sebelum mengembalikannya, dan
           meng-int-kan hasilnya menghasilkan angka sampah yang diterima apa
           adanya oleh copyToPaket. */
        $salinan = app(SopItemService::class)->copyToPaket(
            $d->idItem[0], $lain['paket'], $lain['section'],
            $d->userIdUji(), app(SopItemVersionService::class)
        );

        $this->assertNotNull($salinan, 'Penyalinan item ke paket lain gagal.');
        $this->assertSame($d->idDokumen,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $salinan)->value('Id_SOP_Dokumen'),
            'Salinan menunjuk dokumen yang berbeda — tautannya putus sejak lahir.');

        // Revisi dokumennya, lalu terbitkan.
        $svc = app(SopDokumenService::class);
        $v2 = (int) $svc->tambahVersi($d->idDokumen, [
            'Id_Master_Konten' => UatDunia::kontenDokumenCocok(),
            'Ringkasan_Perubahan' => 'Revisi setelah penyalinan.',
            'Pengesah' => UatDunia::PENGESAH_UJI,
        ], $d->userIdUji());

        /* Revisi ditempuh lewat alur tahapnya. Tanpa ini penerbitan ditolak di
           depan pintu (DML-2026-048), dan skenario 018 gagal karena revisinya
           tak pernah terjadi — bukan karena tautan salinannya putus, yang
           justru satu-satunya hal yang sedang dibuktikan di sini. */
        UatTahapVersi::bawaKeAmbangTerbit($v2, $d->userIdUji());

        $svc->terbitkanVersi($v2, $d->userIdUji());

        $penunjuk = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket_Item', [$d->idItem[0], $salinan])
            ->pluck('Id_Item_Versi_Aktif', 'Id_SOP_Paket_Item');

        $this->assertSame($v2, (int) $penunjuk[$d->idItem[0]],
            'Item asal tidak menyusul revisi terbaru.');
        $this->assertSame($v2, (int) $penunjuk[$salinan],
            'SALINAN tidak menyusul revisi. Revisi di paket asal tidak pernah sampai ke '
            .'paket yang memakai dokumen yang sama — dan itulah cacat yang membuat satu '
            .'berkas dilaporkan dengan tata kelola berbeda-beda di produksi.');
    }
}
