<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\SYSTEM\TaskProcessing\CloudTaskService;
use App\Services\SYSTEM\TaskProcessing\TaskOutboxService;
use Illuminate\Support\Facades\DB;

/**
 * Fan-out revisi dokumen — Fase 3 Master Dokumen SOP.
 *
 * MASALAH YANG DITUTUP. Sebelum ini, menerbitkan revisi lewat Builder paket P1
 * hanya merekonsiliasi P1 (SopAdminBuilderController:493, dipicu "Publish
 * Paket"). Kalau dokumen yang sama juga dipakai P2..P5, karyawan di sana tetap
 * membaca versi lama dan TIDAK ADA satu pun jalur yang memberi tahu mereka.
 * publish() sendiri tak pernah merekonsiliasi apa pun — ia hanya menggeser
 * status versi, pointer aktif, dan integrasi ujian.
 *
 * NO-OP HARI INI, DAN ITU DISENGAJA. Data dev sepenuhnya 1 dokumen = 1 item
 * (invarian yang dijaga sop:dokumen-verify), jadi paketPerujuk() selalu
 * mengembalikan satu paket dan keluarannya identik dengan sebelum fase ini.
 * Mesinnya dibangun selagi tenang supaya Fase 6 — saat berbagi benar-benar
 * menyala — tidak melahirkan mesin baru dan beban nyata pada saat yang sama.
 */
class SopDokumenFanoutService
{
    /** Item AKTIF yang merujuk dokumen ini. Item nonaktif sengaja dibuang: fan-out ke item mati membuka kewajiban hantu. */
    public function itemPerujuk(int $idDokumen): array
    {
        return DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Id_SOP_Paket_Item')
            ->pluck('Id_SOP_Paket_Item')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Paket unik dari item-item perujuk.
     *
     * UNIK ITU WAJIB, bukan kerapian: satu paket yang memuat dua item perujuk
     * dokumen yang sama akan direkonsiliasi dua kali — pekerjaan ganda, dan
     * (begitu notifikasi ikut) karyawan yang sama diberi tahu dua kali untuk
     * satu revisi.
     */
    public function paketPerujuk(int $idDokumen): array
    {
        return DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->orderBy('Id_SOP_Paket')
            ->pluck('Id_SOP_Paket')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Dampak sebelum tombol ditekan.
     *
     * Terutama penting untuk rollback: sejak keputusan produk 2026-08-09 ia
     * ikut fan-out dan MEMBUKA ULANG kewajiban karyawan di semua paket
     * perujuk — perilaku yang berkebalikan dengan rollback senyap sebelumnya.
     * Admin harus melihat angkanya lebih dulu, bukan menemukannya sesudahnya.
     */
    public function hitungDampak(int $idDokumen): array
    {
        $paket = $this->paketPerujuk($idDokumen);

        return [
            'item' => count($this->itemPerujuk($idDokumen)),
            'paket' => count($paket),
            'penugasan' => $paket === [] ? 0 : $this->hitungPenugasan($paket),
        ];
    }

    /**
     * Rincian per paket: naik DARI versi berapa KE versi berapa, dan berapa
     * orang terikat di dalamnya.
     *
     * hitungDampak() hanya menjawab "berapa banyak". Itu cukup untuk
     * memutuskan sinkron atau antri, tapi tidak cukup untuk meminta
     * persetujuan admin: "3 paket" tak memberi tahu paket mana, dan seorang
     * admin yang tak tahu apa yang akan berubah tak benar-benar menyetujui
     * apa pun.
     *
     * Versi "sekarang" dibaca per PAKET, bukan per dokumen, karena itulah yang
     * benar-benar disajikan paket itu hari ini — dan setelah tiga cacat yang
     * baru ditutup, kita tahu keduanya bisa berbeda.
     */
    public function rincianPerPaket(int $idDokumen, int $versiBaru): array
    {
        $paket = $this->paketPerujuk($idDokumen);
        if ($paket === []) {
            return [];
        }

        $orang = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->whereIn('pj.Id_SOP_Paket', $paket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw('pj.Id_SOP_Paket, COUNT(DISTINCT d.Kode_Karyawan) as n')
            ->pluck('n', 'Id_SOP_Paket');

        $sekarang = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->where('i.Id_SOP_Dokumen', $idDokumen)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('i.Id_SOP_Paket')
            ->selectRaw('i.Id_SOP_Paket, MAX(v.Versi_Ke) as versi')
            ->pluck('versi', 'Id_SOP_Paket');

        return DB::table('N_SOP_Master_Paket')
            ->whereIn('Id_SOP_Paket', $paket)
            ->orderBy('Nama_Paket')
            ->get(['Id_SOP_Paket', 'Nama_Paket'])
            ->map(fn ($p) => [
                'Nama_Paket' => $p->Nama_Paket,
                'Versi_Sekarang' => isset($sekarang[$p->Id_SOP_Paket]) ? (int) $sekarang[$p->Id_SOP_Paket] : null,
                'Versi_Baru' => $versiBaru,
                'Jumlah_Karyawan' => (int) ($orang[$p->Id_SOP_Paket] ?? 0),
            ])
            ->all();
    }

    /**
     * PENULIS INTENT — Fase 2. Metode ini tidak lagi mengerjakan apa pun.
     *
     * Sebelum fase ini ia menyisir seluruh karyawan di semua paket perujuk,
     * dan karena publish memanggilnya dari DALAM transaksi, biaya transaksinya
     * O(item x karyawan): pemotongan per-100 milik SopReconciliationService
     * berubah menjadi savepoint yang tak melepas satu lock pun, dan jeda
     * antar-potongan justru menahan lock lebih lama. Dengan RCSI mati di
     * Web_HRIS, setiap pembaca antre di belakangnya.
     *
     * Yang tersisa di sini adalah SATU INSERT: sebuah baris intent yang aman
     * berada di dalam transaksi pemanggil. Seluruh penyisiran pindah ke
     * SopReconcileHandler, yang berjalan di luar transaksi mana pun.
     *
     * INTENT DIPASANG PADA DOKUMEN, BUKAN PAKET (spec §3.4). Bila intent dibuat
     * per paket, keputusan produk 2026-08-10 — satu notifikasi per karyawan per
     * revisi dokumen — pecah, dan karyawan yang terdaftar di enam paket kembali
     * menerima enam notifikasi. Handler-lah yang memekarkan dokumen menjadi
     * paket-paketnya, sehingga penggabungan notifikasi tetap punya satu penulis.
     *
     * Ambang penjadwalan_async_ambang DICABUT dari jalur ini: tak ada lagi
     * keputusan sinkron-vs-antri, semuanya lewat antrean. Settingnya tetap
     * dipakai jalur penjadwalan lain.
     *
     * $kecualiPaket dipakai ketika pemanggil sudah merekonsiliasi paketnya
     * sendiri lewat alurnya sendiri; ia diteruskan ke handler apa adanya.
     */
    public function rekonsiliasi(int $idDokumen, string $alasan, ?int $userId, ?int $kecualiPaket = null): array
    {
        $paket = $this->paketPerujuk($idDokumen);
        if ($kecualiPaket !== null) {
            $paket = array_values(array_filter($paket, fn ($p) => $p !== $kecualiPaket));
        }

        if ($paket === []) {
            return ['outbox_id' => null, 'task_id' => null, 'paket' => 0];
        }

        $dok = DB::table('N_SOP_Dokumen')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->first(['Judul', 'Id_Versi_Aktif']);

        $judul = $dok->Judul ?? null;
        $idVersi = (int) ($dok->Id_Versi_Aktif ?? 0);

        $taskSvc = app(SopReconcileTaskService::class);

        /* Id disiapkan TANPA menulis baris. Barisnya baru dibuat setelah
           diketahui intent-nya memang baru — lihat catatan di bawah. */
        $taskId = $taskSvc->makeId();

        /* KUNCI IDEMPOTENSI MEMUAT ALASAN DAN EMBER MENIT, dan keduanya perlu.
           Kunci (dokumen, versi) saja menelan ROLLBACK: mengembalikan dokumen
           ke versi yang pernah terbit menghasilkan kunci yang sudah terpakai,
           create() mengembalikan baris lama yang kemungkinan sudah COMPLETED,
           dan TAK ADA pekerjaan baru yang dijadwalkan — tanpa satu pun galat.
           Karyawan lalu tetap memegang kewajiban versi yang sudah dicabut.

           Ember menit menjaga sisi sebaliknya: peristiwa yang benar-benar
           berulang (rollback lalu terbit lagi ke versi yang sama) tetap
           mendapat intent sendiri, sementara retry atas SATU permintaan —
           yang selalu terjadi dalam hitungan detik — tetap digabungkan. */
        $ember = now()->format('YmdHi');

        /* Dedup bawaan TaskOutboxService::create(): publish yang di-retry
           mengembalikan baris lama tanpa menulis apa pun, sehingga karyawan
           tidak disisir dua kali dan tidak diberi tahu dua kali untuk satu
           revisi. */
        $outbox = app(TaskOutboxService::class)->create([
            'Task_Type' => 'SOP_RECONCILE_DOKUMEN',
            'Aggregate_Type' => 'N_SOP_Dokumen',
            'Aggregate_Id' => $idDokumen,
            'Idempotency_Key' => "SOP_RECONCILE:{$idDokumen}:{$idVersi}:{$alasan}:{$ember}",
            'Queue_Name' => (string) config('queue.names.penjadwalan_training'),
            /* WAJIB lewat absoluteUrl(): APP_URL di lingkungan ini ditulis tanpa
               skema, dan URL tanpa skema ditolak Cloud Tasks secara SENYAP. */
            'Task_Url' => CloudTaskService::absoluteUrl('/api/v1/internal/task-outbox/execute'),
            'Payload' => [
                'id_dokumen' => $idDokumen,
                'id_versi' => $idVersi,
                'alasan' => $alasan,
                'user_id' => $userId,
                'kecuali_paket' => $kecualiPaket,
                'task_id' => $taskId,
            ],
            'Max_Attempts' => 3,
            'Created_By' => $userId,
            'Source_Module' => 'LMS',
            'Source_Feature' => 'SopRekonsiliasiDokumen',
        ]);

        /* BARIS PROGRES DIBUAT HANYA BILA INTENT-NYA MEMANG BARU.
           Ketika dedup menang, create() mengembalikan baris lama dan tak ada
           handler yang akan menutup baris progres baru — panel Aktivitas akan
           menggantung di "queued" selamanya untuk pekerjaan yang sebenarnya
           sudah selesai. Yang dipakai adalah task_id milik intent yang sudah
           ada, sehingga panel menunjuk pekerjaan yang benar. */
        if ($outbox->wasRecentlyCreated) {
            $taskSvc->mulai(
                ['id_dokumen' => $idDokumen, 'judul' => $judul ?? 'Dokumen', 'paket' => count($paket)],
                count($paket),
                null,
                $taskId,
            );
        } else {
            $lama = is_array($outbox->Payload) ? $outbox->Payload : (array) json_decode((string) $outbox->Payload, true);
            $taskId = (string) ($lama['task_id'] ?? $taskId);
        }

        return ['outbox_id' => $outbox->Id_Task_Outbox, 'task_id' => $taskId, 'paket' => count($paket)];
    }

    /** Penugasan aktif pada sekumpulan paket. */
    private function hitungPenugasan(array $idPaket): int
    {
        if ($idPaket === []) {
            return 0;
        }

        return (int) DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->whereIn('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->count();
    }
}
