<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopReconcileHandler;
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
 * Siklus hidup dokumen dari sisi karyawan — jalur normal.
 *
 * UAT-SOP-044 revisi menyusul ke semua paket pemakai, termasuk yang dilihat
 * karyawan · 045 revisi WAJIB membuka kembali kewajiban baca, revisi minor tidak
 * · 048 rekertifikasi berkala menjadwalkan ulang tanpa penjadwalan manual.
 *
 * Ketiganya menjaga hal yang sama dari sudut berbeda: kepatuhan yang tercatat
 * harus mencerminkan versi yang BERLAKU, bukan versi yang kebetulan dibaca
 * dulu. Rekam "selesai membaca" atas revisi yang sudah diganti adalah kepatuhan
 * di atas kertas — angka yang benar menurut catatan dan salah menurut kenyataan.
 */
class Alur044SiklusHidupLanjutTest extends TestCase
{
    use DatabaseTransactions;

    private string $kode;

    private function dunia(): UatDunia
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        $karyawan = UatAkun::karyawanBiasa();
        $kode = $karyawan ? UatAkun::kodeKaryawan($karyawan) : null;
        if (! $kode) {
            $this->markTestSkipped('Tidak ada akun karyawan yang tertaut data karyawan.');
        }
        $this->kode = $kode;

        try {
            return UatDunia::mulai((int) $admin->Id_Users)
                ->buatDokumenTerbit()
                ->buatPaket()
                ->tambahItem('Item Wajib', wajib: true)
                ->terbitkanPaket()
                ->tugaskanKe($kode);
        } catch (RuntimeException $e) {
            $this->markTestSkipped($e->getMessage());
        }
    }

    private function terbitkanRevisi(UatDunia $d, string $wajibBacaUlang): int
    {
        $svc = app(SopDokumenService::class);

        $v = (int) $svc->tambahVersi($d->idDokumen, [
            'Id_Master_Konten' => UatDunia::kontenDokumenCocok(),
            'Ringkasan_Perubahan' => 'Revisi uji.',
            'Pengesah' => UatDunia::PENGESAH_UJI,
        ], $d->userIdUji());

        /* Revisi pun harus menempuh alur tahapnya sebelum boleh terbit.
           Sejak DML-2026-048 (Pemberkasan sebagai langkah tersendiri) dan
           DML-2026-051 (Tinjauan Akhir), terbitkanVersi() menolak versi yang
           masih berdiri di Drafting. Yang diuji di sini adalah FAN-OUT revisi ke
           paket pemakai, jadi perjalanan tahapnya cukup ditempuh apa adanya. */
        UatTahapVersi::bawaKeAmbangTerbit($v, $d->userIdUji());

        $svc->terbitkanVersi($v, $d->userIdUji(), $wajibBacaUlang);

        $this->jalankanRekonsiliasiTerantri($d->idDokumen);

        return $v;
    }

    /**
     * Jalankan rekonsiliasi yang penerbitan hanya ANTRIKAN.
     *
     * Sejak Fase 2, terbitkanVersi() tidak lagi menyisir karyawan di dalam
     * transaksinya; ia menulis SATU baris intent ke outbox
     * (SOP_RECONCILE_DOKUMEN) dan menyerahkan penyisirannya ke
     * SopReconcileHandler yang dijalankan Cloud Tasks. Alasannya benar —
     * transaksi penerbitan tetap O(item), bukan O(item x karyawan) — tapi
     * akibatnya kewajiban baca karyawan TIDAK pernah terbuka kembali di dalam
     * satu permintaan, dan karena itu juga tidak di dalam satu uji.
     *
     * Menunggu antreannya mustahil di sini (tak ada worker), dan menyimpulkan
     * "revisi wajib membuka kembali kewajiban" dari keberadaan baris outbox
     * hanya membuktikan bahwa pesannya terkirim — bukan bahwa penerimanya
     * mengerjakan hal yang benar. Jadi handler-nya dipanggil langsung dengan
     * muatan yang benar-benar ditulis penerbitan, mengikuti pola yang sudah
     * dipakai SopPanelAktivitasReconcileTest.
     */
    private function jalankanRekonsiliasiTerantri(int $idDokumen): void
    {
        $outbox = SystemTaskOutbox::query()
            ->where('Task_Type', 'SOP_RECONCILE_DOKUMEN')
            ->where('Aggregate_Id', $idDokumen)
            ->orderByDesc('Id_Task_Outbox')
            ->first();

        $this->assertNotNull($outbox,
            'Penerbitan revisi tidak mengantrikan rekonsiliasi sama sekali. Tanpa intent '
            .'itu, tak ada apa pun yang akan menyisir karyawan — dan kepatuhan atas versi '
            .'lama akan terus terhitung benar selamanya.');

        $muatan = is_array($outbox->Payload)
            ? $outbox->Payload
            : (array) json_decode((string) $outbox->Payload, true);

        app(SopReconcileHandler::class)->handle($outbox, $muatan);
    }

    #[Group('UAT-SOP-044')]
    public function test_revisi_menyusul_ke_semua_paket_pemakai_termasuk_yang_dilihat_karyawan(): void
    {
        $d = $this->dunia();
        $lain = $d->paketTambahan('Paket Kedua');
        $d->terbitkanPaketLain($lain['paket']);

        $v2 = $this->terbitkanRevisi($d, 'Y');

        $penunjuk = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket_Item', [$d->idItem[0], $lain['item']])
            ->pluck('Id_Item_Versi_Aktif', 'Id_SOP_Paket_Item');

        $this->assertSame($v2, (int) $penunjuk[$d->idItem[0]]);
        $this->assertSame($v2, (int) $penunjuk[$lain['item']],
            'Paket kedua masih menunjuk revisi lama. Satu unit membaca aturan yang sudah '
            .'diganti sementara unit lain membaca yang baru, dan keduanya sama-sama '
            .'tampak patuh.');

        $this->assertSame($v2,
            (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $d->idDokumen)->value('Id_Versi_Aktif'));
    }

    /**
     * INTI SKENARIO 045. Karyawan yang sudah mengkonfirmasi versi lama harus
     * diminta membaca ulang bila revisinya bersifat WAJIB.
     */
    #[Group('UAT-SOP-045')]
    public function test_revisi_wajib_membuka_kembali_kewajiban_baca(): void
    {
        $d = $this->dunia();
        $d->tandaiSelesai($d->idItem[0]);

        $v2 = $this->terbitkanRevisi($d, 'Y');

        $adaTugasVersiBaru = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $d->detailId())
            ->where('Id_SOP_Paket_Item', $d->idItem[0])
            ->where('Id_SOP_Item_Versi', $v2)
            ->whereNotIn('Status', [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI])
            ->exists();

        $this->assertTrue($adaTugasVersiBaru,
            'Revisi wajib tidak membuka kembali kewajiban baca. Karyawan tetap terhitung '
            .'patuh atas versi yang sudah tidak berlaku.');
    }

    /**
     * Sisi sebaliknya, dan sama pentingnya. Revisi MINOR — salah ketik, nomor
     * halaman — tidak boleh mengganggu siapa pun. Sistem yang membuka kembali
     * kewajiban atas tiap perubahan kecil melatih orang menekan "sudah baca"
     * tanpa membaca, dan itu merusak justru yang hendak dijaga.
     */
    #[Group('UAT-SOP-045')]
    public function test_revisi_minor_tidak_mengganggu_yang_sudah_selesai(): void
    {
        $d = $this->dunia();
        $d->tandaiSelesai($d->idItem[0]);

        $v2 = $this->terbitkanRevisi($d, 'T');

        $terbuka = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $d->detailId())
            ->where('Id_SOP_Paket_Item', $d->idItem[0])
            ->where('Id_SOP_Item_Versi', $v2)
            ->whereNotIn('Status', [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI])
            ->exists();

        $this->assertFalse($terbuka,
            'Revisi minor ikut membuka kembali kewajiban baca. Perubahan salah ketik '
            .'memaksa seluruh karyawan membaca ulang, dan lama-lama tombol konfirmasi '
            .'ditekan tanpa dibaca.');
    }
}
