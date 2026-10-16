<?php

namespace App\Services\LMS\SOP;

use App\Models\SYSTEM\Application\Outbox\Models\SystemTaskOutbox;
use App\Services\SYSTEM\TaskProcessing\PermanentTaskException;
use App\Services\SYSTEM\TaskProcessing\RetryableTaskException;
use App\Services\SYSTEM\TaskProcessing\TaskHandler;
use Illuminate\Support\Facades\DB;

/**
 * Rekonsiliasi SOP di luar transaksi mana pun — Fase 2.
 *
 * DI SINILAH pemotongan milik SopReconciliationService akhirnya bekerja.
 * Sebelum fase ini, jalur publish memanggilnya dari DALAM transaksi, sehingga
 * DB::transaction() per potongan menjadi SAVE TRANSACTION dan tak melepas satu
 * lock pun — dan usleep antar-potongan justru menahan lock lebih lama.
 *
 * SATU KELAS UNTUK DUA TIPE. Pemekaran dokumen ke paket dan penggabungan
 * notifikasi harus punya satu penulis; dua kelas akan menyimpang, dan
 * penggabungan notifikasi adalah hal pertama yang pecah ketika itu terjadi.
 */
class SopReconcileHandler implements TaskHandler
{
    /**
     * Berapa langkah progres yang dimiliki SATU paket.
     *
     * Skalanya sengaja tetap "paket", bukan "penugasan". Total_Records pada baris
     * task ditulis pemanggil sebagai JUMLAH PAKET (SopDokumenFanoutService) atau 1
     * (jalur publish paket), dan ActivityCenterService::reconcileLabel() membaca
     * angka itu untuk teks "N paket". Melaporkan progres dalam satuan penugasan
     * akan membuat baris task menyebut jumlah paket dan jumlah orang dengan nama
     * yang sama.
     *
     * Yang juga tak bisa dipakai: satuan penugasan lintas paket. Jumlah penugasan
     * paket berikutnya baru diketahui saat paket itu mulai disisir, jadi penyebutnya
     * belum ada ketika paket pertama berjalan — dan penyebut yang berubah di tengah
     * jalan membuat persen mundur.
     *
     * Karena itu tiap paket diberi irisan yang sama besar, dan potongan di dalamnya
     * mengisi irisan itu: persen tetap satu sumbu yang menaik dari 0 ke 100 lintas
     * seluruh paket, sementara satuan yang dilaporkan tetap paket. Kelipatan 100
     * dipilih supaya pembagian bulat di bumpProgress() tidak kehilangan resolusi.
     *
     * Konsekuensinya Processed_Records sempat berisi angka berskala (150 dari 300)
     * selagi task berjalan; kolom itu tidak ditampilkan panel Aktivitas — yang
     * dibaca hanya Percent — dan selesai() menyetelnya kembali ke Total_Records.
     */
    private const SKALA = 100;

    /** Bagian irisan satu paket yang sudah disisir, 0..SKALA. */
    private static function irisan(int $sudah, int $dari): int
    {
        return (int) floor(max(0, min($sudah, $dari)) / max(1, $dari) * self::SKALA);
    }

    public function __construct(
        private SopDokumenFanoutService $fanout,
        private SopReconciliationService $reconcile,
        private SopReconcileTaskService $task,
        private SopNotificationService $notif,
    ) {}

    public function handle(SystemTaskOutbox $outbox, array $payload): ?array
    {
        $taskId = (string) ($payload['task_id'] ?? '');
        $alasan = (string) ($payload['alasan'] ?? 'MANUAL');
        $userId = $payload['user_id'] ?? null;

        try {
            $hasil = $outbox->Task_Type === 'SOP_RECONCILE_PAKET'
                ? $this->satuPaket((int) ($payload['id_paket'] ?? 0), $alasan, $userId, $taskId)
                : $this->satuDokumen($payload, $alasan, $userId, $taskId);
        } catch (PermanentTaskException $e) {
            if ($taskId !== '') {
                $this->task->gagal($taskId, $e->getMessage());
            }
            throw $e;
        } catch (\Throwable $e) {
            /* Sementara: jangan tutup baris task sebagai gagal — Cloud Tasks akan
               mencoba lagi, dan menutupnya sekarang membuat panel melaporkan
               kegagalan atas pekerjaan yang masih akan berhasil. */
            throw new RetryableTaskException($e->getMessage(), 0, $e);
        }

        if ($taskId !== '') {
            $this->task->selesai($taskId, $hasil);
        }

        return $hasil;
    }

    /**
     * Badan rekonsiliasi() yang lama, dipindah apa adanya — kini di luar transaksi.
     *
     * SATU notifikasi per karyawan untuk satu revisi dokumen — keputusan produk
     * user 2026-08-10. 58 dari 59 karyawan ditugaskan ke lebih dari satu paket
     * (maksimum 6), jadi tanpa penggabungan satu revisi dokumen bersama bisa
     * menghasilkan 6 notifikasi sekaligus untuk satu orang. Karena itu
     * reconcilePaket() dipanggil dengan tanpaNotifikasi: true dan kodenya
     * dikumpulkan dulu.
     */
    private function satuDokumen(array $payload, string $alasan, ?int $userId, string $taskId): array
    {
        $idDokumen = (int) ($payload['id_dokumen'] ?? 0);
        $kecualiPaket = isset($payload['kecuali_paket']) && $payload['kecuali_paket'] !== null
            ? (int) $payload['kecuali_paket']
            : null;

        $judul = $idDokumen > 0
            ? DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->value('Judul')
            : null;

        if ($idDokumen <= 0 || $judul === null) {
            /* Dokumennya sudah tidak ada. Mencobanya lagi tak akan pernah berhasil,
               jadi jangan biarkan Cloud Tasks mengulang sampai Max_Attempts habis. */
            throw new PermanentTaskException("Dokumen SOP #{$idDokumen} tidak ditemukan — intent rekonsiliasi dibatalkan.");
        }

        $paket = $this->fanout->paketPerujuk($idDokumen);
        if ($kecualiPaket !== null) {
            $paket = array_values(array_filter($paket, fn ($p) => $p !== $kecualiPaket));
        }

        $kodeGabungan = [];
        $paketTersentuh = [];
        $stat = ['seeded' => 0, 'reopened' => 0, 'digantikan' => 0, 'gagal' => 0];
        $total = max(1, count($paket));

        foreach ($paket as $i => $idPaket) {
            $hasilPaket = $this->reconcile->reconcilePaket(
                $idPaket,
                $alasan,
                $userId,
                tanpaNotifikasi: true,
                onProgress: $taskId !== ''
                    ? fn (int $sudah, int $dari) => $this->task->bumpProgress(
                        $taskId,
                        $i * self::SKALA + self::irisan($sudah, $dari),
                        $total * self::SKALA,
                    )
                    : null,
            );

            $paketTersentuh[] = $idPaket;
            foreach ($hasilPaket['reopened_kodes'] ?? [] as $kode) {
                $kodeGabungan[] = $kode;
            }
            foreach (['seeded', 'reopened', 'digantikan', 'gagal'] as $k) {
                $stat[$k] += (int) ($hasilPaket[$k] ?? 0);
            }

            if ($taskId !== '') {
                $this->task->bumpProgress($taskId, ($i + 1) * self::SKALA, $total * self::SKALA);
            }
        }

        $kodeGabungan = array_values(array_unique($kodeGabungan));
        $diberitahu = 0;

        if ($kodeGabungan !== [] && $paketTersentuh !== []) {
            $nama = DB::table('N_SOP_Master_Paket')
                ->whereIn('Id_SOP_Paket', $paketTersentuh)
                ->orderBy('Nama_Paket')->pluck('Nama_Paket')->all();

            $this->notif->notifDokumenDiperbarui(
                $kodeGabungan,
                (int) $paketTersentuh[0],
                (string) $judul,
                $nama,
            );

            $diberitahu = count($kodeGabungan);
        }

        return [
            'id_dokumen' => $idDokumen,
            'judul' => (string) $judul,
            'paket' => count($paketTersentuh),
            'paket_ids' => $paketTersentuh,
            'seeded' => $stat['seeded'],
            'reopened' => $stat['reopened'],
            'digantikan' => $stat['digantikan'],
            'gagal' => $stat['gagal'],
            'diberitahu' => $diberitahu,
        ];
    }

    /**
     * Satu paket saja — dipicu paket berubah status menjadi PUBLISHED.
     * Tak ada pemekaran, jadi tak ada yang perlu digabung: notifikasinya
     * dikirim reconcilePaket() sendiri (tanpaNotifikasi: false).
     */
    private function satuPaket(int $idPaket, string $alasan, ?int $userId, string $taskId): array
    {
        $nama = $idPaket > 0
            ? DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket')
            : null;

        if ($idPaket <= 0 || $nama === null) {
            throw new PermanentTaskException("Paket SOP #{$idPaket} tidak ditemukan — intent rekonsiliasi dibatalkan.");
        }

        $hasilPaket = $this->reconcile->reconcilePaket(
            $idPaket,
            $alasan,
            $userId,
            tanpaNotifikasi: false,
            onProgress: $taskId !== ''
                ? fn (int $sudah, int $dari) => $this->task->bumpProgress($taskId, self::irisan($sudah, $dari), self::SKALA)
                : null,
        );

        if ($taskId !== '') {
            $this->task->bumpProgress($taskId, self::SKALA, self::SKALA);
        }

        return [
            'id_paket' => $idPaket,
            'nama_paket' => (string) $nama,
            'paket' => 1,
            'paket_ids' => [$idPaket],
            'seeded' => (int) ($hasilPaket['seeded'] ?? 0),
            'reopened' => (int) ($hasilPaket['reopened'] ?? 0),
            'digantikan' => (int) ($hasilPaket['digantikan'] ?? 0),
            'gagal' => (int) ($hasilPaket['gagal'] ?? 0),
            'diberitahu' => count($hasilPaket['reopened_kodes'] ?? []),
        ];
    }
}
