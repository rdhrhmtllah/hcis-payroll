<?php

namespace Tests\Feature\SYSTEM;

use App\Services\SYSTEM\Application\Transactions\TransactionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tiga cacat yang ditemukan dari log uji manual 10 September 2026.
 *
 * Ketiganya punya bentuk yang sama: GAGAL DALAM DIAM. Tidak ada yang meledak di
 * depan pengguna, jadi satu-satunya tempat keberadaannya terlihat adalah log —
 * dan di sana pun tenggelam di antara ratusan baris.
 */
class TemuanLogTest extends TestCase
{
    /**
     * Riwayat approver shift hilang karena membaca kolom yang tidak ada.
     *
     * View N_HRIS_VW_Approval_Tracking_Generic memakai Detail_Notes dan
     * Flow_Catatan; tidak ada kolom 'Notes'. Membacanya melempar "Undefined
     * property", tertangkap catch, lalu SELURUH riwayat dipulangkan sebagai
     * koleksi kosong — layar tidak menampilkan galat, riwayatnya sekadar tidak
     * ada, dan itu terbaca seperti "belum ada yang memproses". 21 kejadian
     * dalam satu hari uji.
     */
    public function test_pemetaan_riwayat_approver_hanya_membaca_kolom_yang_ada(): void
    {
        $kolom = collect(DB::select(
            'SELECT c.name FROM sys.columns c WHERE c.object_id = OBJECT_ID(?)',
            ['N_HRIS_VW_Approval_Tracking_Generic'],
        ))->pluck('name');

        if ($kolom->isEmpty()) {
            $this->markTestSkipped('View tracking generic tidak ada di lingkungan ini.');
        }

        $this->assertFalse(
            $kolom->contains('Notes'),
            'View kini punya kolom Notes — prasyarat uji ini berubah, periksa ulang pemetaannya.',
        );
        $this->assertTrue(
            $kolom->contains('Detail_Notes'),
            'Detail_Notes hilang dari view; pemetaan riwayat approver akan kosong lagi.',
        );

        $isi = file_get_contents(base_path('app/Services/ESS/Attendance/Shift/Request/ShiftRequestService.php'));

        $this->assertStringNotContainsString(
            '$track->Notes',
            $isi,
            'Pemetaan membaca $track->Notes lagi. Kolom itu tidak ada di view, dan galatnya '.
            'tertelan catch sehingga seluruh riwayat approver dipulangkan kosong tanpa satu pun '.
            'tanda di layar.',
        );
    }

    /**
     * Export ditandai "gagal permanen" untuk berkas yang sudah berhasil diunggah.
     *
     * Flysystem menutup resource yang diberikan kepadanya; fclose() kedua
     * melempar TypeError SESUDAH unggahannya selesai.
     */
    public function test_penutupan_stream_export_tahan_terhadap_stream_yang_sudah_ditutup(): void
    {
        $isi = file_get_contents(
            base_path('app/Services/SYSTEM/Application/Exports/AbstractExportService.php'),
        );

        $posisi = strpos($isi, 'Storage::disk(self::DISK)->put($relativePath, $stream);');
        $this->assertNotFalse($posisi, 'Jalur unggah export tidak ditemukan.');

        $blok = substr($isi, $posisi, 700);

        $this->assertStringContainsString(
            'is_resource($stream)',
            $blok,
            'fclose() dipanggil tanpa memeriksa resource-nya masih hidup. Flysystem sudah '.
            'menutupnya, jadi job export ditandai gagal permanen padahal berkasnya sudah terunggah.',
        );
    }

    /**
     * Peringatan "Prefix tidak dikenal" harus berarti apa yang dikatakannya.
     *
     * Setiap approval KPI menuliskannya, padahal KPI memang punya mesin
     * persetujuannya sendiri dan tidak menitipkan apa pun ke service ini.
     * Derau itu membuat prefix yang BENAR-BENAR asing tenggelam.
     */
    public function test_transaksi_kpi_tidak_lagi_dianggap_prefix_asing(): void
    {
        $svc = app(TransactionService::class);

        Log::spy();

        $this->assertTrue($svc->handleFinalApprove('AKPI-09-2026-0008', 'H45'));
        $this->assertTrue($svc->handleNextApprove('PKPI09-26-0104', 'H45'));

        Log::shouldNotHaveReceived('channel');
    }

    public function test_prefix_yang_benar_benar_asing_tetap_diperingatkan(): void
    {
        $svc = app(TransactionService::class);

        $this->assertFalse(
            $svc->handleFinalApprove('ZZZ-09-2026-0001', 'H45'),
            'Prefix asing tidak lagi ditolak — peringatannya kehilangan gunanya.',
        );
    }
}
