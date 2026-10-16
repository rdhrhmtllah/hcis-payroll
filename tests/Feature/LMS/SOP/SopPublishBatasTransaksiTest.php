<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\LMS\SOP\SopDokumenFanoutService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Batas transaksi publish — penjaga inti Fase 2.
 *
 * Yang diuji adalah STRUKTUR: apa yang berjalan di dalam transaksi dan apa yang
 * di luar. Durasi TIDAK diuji — data dev terlalu kecil untuk membuktikan apa pun
 * lewat waktu, dan angka wall-clock di sini tidak sah sebagai bukti.
 *
 * Sebelum Fase 2, rekonsiliasi() menyisir setiap penugasan x setiap item wajib
 * DI TEMPAT, dan karena publish memanggilnya dari dalam transaksi, seluruh
 * pemotongan di dalamnya lumpuh menjadi savepoint. Penjaga ini menuntut
 * pekerjaan itu pindah ke handler: yang tersisa di jalur panas hanyalah satu
 * INSERT intent.
 */
class SopPublishBatasTransaksiTest extends TestCase
{
    use DatabaseTransactions;

    /** Dokumen yang benar-benar dipakai item wajib aktif, dan paketnya punya penugasan hidup. */
    private function dokumenBerpenugasan(): ?int
    {
        $baris = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', 'Y')
            ->where('i.Flag_Wajib', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->where('d.Flag_Cabut_Akses', 'T')
            ->first(['i.Id_SOP_Dokumen']);

        return $baris ? (int) $baris->Id_SOP_Dokumen : null;
    }

    public function test_rekonsiliasi_hanya_menulis_intent_tidak_menyemai_progres(): void
    {
        $idDokumen = $this->dokumenBerpenugasan();
        if (! $idDokumen) {
            $this->markTestSkipped('Tak ada dokumen berpenugasan aktif di data dev.');
        }

        $progresSebelum = DB::table('N_SOP_Progress')->count();
        $outboxSebelum = SystemTaskOutbox::query()->count();

        $hasil = app(SopDokumenFanoutService::class)
            ->rekonsiliasi($idDokumen, 'VERSI_BARU', null);

        $this->assertSame(
            $outboxSebelum + 1,
            SystemTaskOutbox::query()->count(),
            'Tak ada baris intent yang ditulis — pekerjaannya akan hilang begitu '
            .'permintaan selesai.'
        );

        $this->assertSame(
            $progresSebelum,
            DB::table('N_SOP_Progress')->count(),
            'rekonsiliasi() masih menyemai progres DI TEMPAT. Seluruh penyisiran '
            .'karyawan harus pindah ke handler, di luar transaksi mana pun — '
            .'itulah yang menurunkan biaya transaksi publish dari O(item x karyawan) '
            .'menjadi O(item).'
        );

        $this->assertNotNull($hasil['outbox_id'] ?? null, 'outbox_id tidak dikembalikan.');
        $this->assertNotNull($hasil['task_id'] ?? null, 'task_id tidak dikembalikan.');
    }

    public function test_intent_untuk_peristiwa_yang_sama_tidak_digandakan(): void
    {
        $idDokumen = $this->dokumenBerpenugasan();
        if (! $idDokumen) {
            $this->markTestSkipped('Tak ada dokumen berpenugasan aktif di data dev.');
        }

        $svc = app(SopDokumenFanoutService::class);
        $a = $svc->rekonsiliasi($idDokumen, 'VERSI_BARU', null);
        $b = $svc->rekonsiliasi($idDokumen, 'VERSI_BARU', null);

        $this->assertSame(
            $a['outbox_id'],
            $b['outbox_id'],
            'Permintaan yang di-retry melahirkan intent kedua — karyawan akan '
            .'disisir dua kali dan diberi tahu dua kali untuk satu revisi.'
        );

        /* Baris progres TIDAK boleh ikut berganda. Ketika dedup menang, baris
           progres yang terlanjur dibuat akan yatim: tak ada handler yang
           menutupnya, dan panel Aktivitas menggantung di "queued" selamanya
           untuk pekerjaan yang sebenarnya sudah selesai. */
        $this->assertSame(
            $a['task_id'],
            $b['task_id'],
            'Baris progres kedua dibuat padahal intent-nya sama — panel Aktivitas '
            .'akan menggantung di "queued" selamanya.'
        );
    }

    public function test_alasan_berbeda_mendapat_intent_sendiri(): void
    {
        $idDokumen = $this->dokumenBerpenugasan();
        if (! $idDokumen) {
            $this->markTestSkipped('Tak ada dokumen berpenugasan aktif di data dev.');
        }

        $svc = app(SopDokumenFanoutService::class);
        $terbit = $svc->rekonsiliasi($idDokumen, 'VERSI_BARU', null);
        $rollback = $svc->rekonsiliasi($idDokumen, 'VERSI_ROLLBACK', null);

        /* Kunci idempotensi WAJIB memuat alasan. Tanpa itu, rollback ke versi
           yang pernah terbit menghasilkan kunci yang sudah terpakai, intent
           lama yang sudah COMPLETED dikembalikan, dan tak ada pekerjaan baru
           yang dijadwalkan — tanpa satu pun galat. Karyawan lalu tetap memegang
           kewajiban versi yang sudah dicabut. */
        $this->assertNotSame(
            $terbit['outbox_id'],
            $rollback['outbox_id'],
            'Rollback menelan intent penerbitan: kunci idempotensi tidak '
            .'membedakan alasan, sehingga pencabutan tak pernah dijadwalkan.'
        );
    }
}
