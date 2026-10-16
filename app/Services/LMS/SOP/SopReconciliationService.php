<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopRollupBaris;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopVersiItem;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliation engine SOP (jantung "living document").
 * reconcilePaket(): untuk tiap karyawan ter-assign, pastikan ada baris Progress
 * untuk SETIAP item wajib pada VERSI AKTIF terkini. Bila muncul kewajiban baru
 * (item/versi baru) pada paket yang tadinya SELESAI → BUKA KEMBALI + ledger.
 *
 * IDEMPOTEN: guard "exists" mencegah reseed/rollup ganda. Aman dipanggil ulang.
 * Dipicu: publish versi baru (VERSI_BARU), tambah item wajib ke paket published
 * (ITEM_BARU), atau manual (MANUAL).
 */
class SopReconciliationService
{
    private array $confirmed = [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI];

    /**
     * Jumlah karyawan per transaksi.
     *
     * ANGKANYA HARUS LEBIH KECIL DARI PAKET NYATA, kalau tidak pemotongannya
     * tak pernah terjadi. Nilai lama 100 melampaui SELURUH populasi di sini —
     * paket terbesar 57 penugasan — sehingga semuanya tetap masuk satu
     * transaksi dan seluruh alasan memecahnya (lihat reconcilePaket) gugur
     * tanpa suara. Terlihat 12 Agt 2026: rollback dokumen pada paket 57 orang
     * x 8 item wajib menahan lock N_SOP_Progress cukup lama sampai beranda
     * karyawan berbalas "Lock request time out period exceeded".
     *
     * Bisa disetel lewat sop_reconcile_chunk bila ternyata masih kepanjangan —
     * atau dinaikkan lagi kalau ongkos per transaksi yang jadi masalah. Yang
     * TAK boleh: mengembalikannya ke angka di atas ukuran paket terbesar.
     */
    private const CHUNK_DETAIL = 10;

    /** Jeda antar potongan (mikrodetik) — lihat alasannya di reconcilePaket(). */
    private const JEDA_ANTAR_POTONGAN_US = 50_000;

    private function chunkDetail(): int
    {
        $n = \App\Services\LMS\SOP\Support\SopSetting::int('sop_reconcile_chunk', self::CHUNK_DETAIL);

        return $n > 0 ? $n : self::CHUNK_DETAIL;
    }

    /** @return array ['seeded'=>int, 'reopened'=>int, 'digantikan'=>int] */
    /**
     * @param  bool  $tanpaNotifikasi  Fase 6 — tahan notifikasi per paket.
     *                                 Dipakai fan-out dokumen: satu revisi dokumen bersama bisa
     *                                 menyentuh sampai 6 paket untuk SATU karyawan, dan mengirim satu
     *                                 notifikasi per paket membuatnya terasa spam. Pemanggil yang
     *                                 menahannya WAJIB mengirim notifikasi gabungan sendiri —
     *                                 menahannya tanpa mengganti berarti karyawan tak diberi tahu sama
     *                                 sekali bahwa kewajibannya dibuka ulang.
     *                                 Jalur lain (Publish Paket, MANUAL) tak berubah: mereka memang per paket.
     * @param  \Closure|null  $onProgress  Fase 2 — kait progres per POTONGAN, dipanggil
     *                                     ($sudah, $totalPenugasan) sesudah tiap potongan
     *                                     (berhasil maupun gagal). OPSIONAL dan berbaku null:
     *                                     seluruh pemanggil lama berperilaku persis seperti
     *                                     sebelumnya. Reconcile tak boleh gagal hanya karena
     *                                     pelaporan progres gagal, jadi lemparannya ditelan
     *                                     dan dicatat — pekerjaannya sendiri sudah commit.
     */
    public function reconcilePaket(
        int $idPaket,
        string $alasan = 'MANUAL',
        ?int $userId = null,
        bool $tanpaNotifikasi = false,
        ?\Closure $onProgress = null,
    ): array {
        $items = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Wajib', SopStatus::AKTIF)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->get(['Id_SOP_Paket_Item', 'Id_Item_Versi_Aktif', 'Tipe_Item']);

        $details = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->get(['d.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan', 'd.Status_Paket']);

        if ($items->isEmpty() || $details->isEmpty()) {
            return ['seeded' => 0, 'reopened' => 0, 'digantikan' => 0];
        }

        $stat = ['seeded' => 0, 'reopened' => 0, 'digantikan' => 0, 'gagal' => 0];
        $reopenedKodes = [];

        /*
         * Satu transaksi PER POTONGAN karyawan, bukan satu transaksi untuk semuanya.
         *
         * Badan kerjanya bersarang detail x item, dan tiap pasangan menerbitkan
         * sekitar tiga pernyataan. Terukur pada paket dengan 34 penugasan dan 3 item
         * wajib: 308 query, 16,3 detik — seluruhnya dalam SATU transaksi. Pada skala
         * 2000 karyawan x 10 item wajib itu menjadi puluhan ribu query dan belasan
         * menit sambil menahan lock di N_SOP_Progress, N_SOP_Penjadwalan_Detail, dan
         * N_SOP_Coverage_Rollup. Basis data ini berjalan tanpa RCSI, jadi pembaca
         * ikut terblokir — persis pola insiden "Login timeout expired".
         *
         * Memecahnya aman karena reconcile memang idempoten: penjagaan exists()
         * mencegah seed dan rollup ganda, sehingga potongan yang sudah commit tidak
         * dikerjakan lagi saat dijalankan ulang. Karena itu pula potongan yang gagal
         * dicatat lalu dilewati, bukan membatalkan potongan yang sudah sah —
         * mengikuti pola ProsesBulkAssignSop dan bulkCabut.
         *
         * SATU HAL YANG SEMPAT MENGGUGURKAN SEMUA INI: ukuran potongannya dulu 100,
         * lebih besar dari paket terbesar yang ada (57). Pemotongannya karena itu
         * tak pernah benar-benar terjadi — kodenya berbentuk memotong, perilakunya
         * tetap satu transaksi untuk semua orang, dan tak ada satu pun tanda bahwa
         * penjagaan ini sedang tidak bekerja. Lihat CHUNK_DETAIL.
         */
        foreach ($details->chunk($this->chunkDetail()) as $i => $potongan) {
            try {
                $this->reconcilePotongan($items, $potongan, $idPaket, $alasan, $stat, $reopenedKodes);
            } catch (\Throwable $e) {
                $stat['gagal'] += $potongan->count();
                Log::error('SOP reconcile reconcilePaket gagal', ['id_paket' => $idPaket, 'potongan' => $i, 'exception' => $e]);
            }

            /*
             * Dilaporkan SEBELUM jeda, bukan sesudah: potongan ini sudah commit,
             * dan menunda kabarnya selama jeda hanya membuat panel tertinggal
             * satu potongan dari kenyataan. Potongan yang gagal tetap dihitung
             * maju — angkanya adalah "sudah disisir", bukan "sudah berhasil";
             * yang berhasil dan yang gagal dipisahkan lewat $stat['gagal'].
             */
            if ($onProgress) {
                try {
                    $onProgress(
                        min(($i + 1) * $this->chunkDetail(), $details->count()),
                        $details->count(),
                    );
                } catch (\Throwable $e) {
                    Log::warning('SOP reconcile: kait progres gagal', ['id_paket' => $idPaket, 'potongan' => $i, 'exception' => $e]);
                }
            }

            /*
             * Jeda satu detak di antara potongan.
             *
             * Melepas lock saja tak cukup bila potongan berikutnya langsung
             * merebutnya kembali: pembaca yang sedang antre tak pernah kebagian
             * giliran, dan dari sisinya tak ada bedanya dengan satu transaksi
             * panjang. Jeda sekecil ini tak terasa pada pekerjaan yang memang
             * berjalan di latar, tapi cukup untuk memberi jalan.
             *
             * Dilewati pada potongan terakhir — tak ada yang perlu diberi jalan
             * sesudahnya.
             */
            if (($i + 1) * $this->chunkDetail() < $details->count()) {
                usleep(self::JEDA_ANTAR_POTONGAN_US);
            }
        }

        return $this->tutupReconcile($idPaket, $alasan, $userId, $stat, $reopenedKodes, $tanpaNotifikasi);
    }

    /**
     * Badan reconcile untuk satu potongan karyawan, dalam transaksinya sendiri.
     *
     * BENTUKNYA BERBASIS HIMPUNAN, BUKAN PER PASANGAN.
     *
     * Bentuk lama menembak satu UPDATE dan satu exists() untuk TIAP pasangan
     * (detail x item). Terekam pada suite 9 September 2026: 650x UPDATE dan
     * 650x exists() dalam satu uji, dan pola yang sama muncul di tiga berkas
     * uji berbeda karena semuanya bermuara ke sini. Pada 2.000 karyawan x 10
     * item itu 40.000 pernyataan — di dalam transaksi yang menahan lock
     * N_SOP_Progress, pada basis data tanpa RCSI.
     *
     * Kini: satu kueri memuat SELURUH baris yang sudah ada untuk potongan ini,
     * penandaan DIGANTIKAN dikirim satu pernyataan per ITEM (bukan per
     * pasangan), dan baris yang kurang disisipkan sekaligus.
     *
     * KENAPA PRA-AMBIL AMAN DI SINI. Pra-ambil berbahaya bila perulangan
     * MENULIS baris yang juga dibacanya — iterasi berikutnya akan melewatkan
     * baris yang baru lahir dan menyisipkannya dua kali. Di sini tiap iterasi
     * memegang pasangan (detail, item, versi) yang berbeda, jadi tak ada
     * iterasi yang membaca hasil iterasi lain. Kunci yang baru dibuat tetap
     * dicatat ke peta $ada sebagai penjagaan terhadap item kembar dalam satu
     * potongan.
     */
    private function reconcilePotongan(
        $items,
        $details,
        int $idPaket,
        string $alasan,
        array &$stat,
        array &$reopenedKodes,
    ): void {
        DB::transaction(function () use ($items, $details, $idPaket, $alasan, &$stat, &$reopenedKodes) {
            $idDetail = collect($details)->pluck('Id_SOP_Penjadwalan_Detail')->map(fn ($v) => (int) $v)->all();
            $idItem = collect($items)->pluck('Id_SOP_Paket_Item')->map(fn ($v) => (int) $v)->all();

            if ($idDetail === [] || $idItem === []) {
                return;
            }

            /* SATU kueri menggantikan satu exists() per pasangan. Potongannya
               CHUNK_DETAIL orang, jadi jumlah parameter di kedua whereIn tetap
               jauh di bawah batas 2.100 SQL Server. */
            $ada = [];
            foreach (DB::table('N_SOP_Progress')
                ->whereIn('Id_SOP_Penjadwalan_Detail', $idDetail)
                ->whereIn('Id_SOP_Paket_Item', $idItem)
                ->get(['Id_SOP_Penjadwalan_Detail', 'Id_SOP_Paket_Item', 'Id_SOP_Item_Versi']) as $r) {
                $ada[$this->kunciProgres(
                    (int) $r->Id_SOP_Penjadwalan_Detail,
                    (int) $r->Id_SOP_Paket_Item,
                    $r->Id_SOP_Item_Versi === null ? null : (int) $r->Id_SOP_Item_Versi,
                )] = true;
            }

            /* Versi lama yang belum dikonfirmasi -> DIGANTIKAN.
               Satu pernyataan per ITEM, bukan per pasangan: predikatnya sudah
               menyaring detail lewat whereIn, dan jumlah baris terdampak yang
               dikembalikan UPDATE tetap sama persis. */
            foreach ($items as $it) {
                $versi = $it->Id_Item_Versi_Aktif ? (int) $it->Id_Item_Versi_Aktif : null;
                if ($versi === null) {
                    continue;
                }

                $stat['digantikan'] += DB::table('N_SOP_Progress')
                    ->whereIn('Id_SOP_Penjadwalan_Detail', $idDetail)
                    ->where('Id_SOP_Paket_Item', $it->Id_SOP_Paket_Item)
                    ->where('Id_SOP_Item_Versi', '<>', $versi)
                    ->whereIn('Status', [SopStatus::ITEM_BELUM, SopStatus::ITEM_DIBUKA])
                    ->update(['Status' => SopStatus::ITEM_DIGANTIKAN, 'Updated_At' => now()]);
            }

            /* Baris versi aktif yang belum ada dikumpulkan dulu, disisipkan
               sekali. Rollup sasaran ikut dijumlahkan per versi supaya
               bumpTarget dipanggil sekali per versi, bukan sekali per baris. */
            $barisBaru = [];
            $adaKewajibanBaru = [];
            $tambahTarget = [];

            foreach ($details as $d) {
                foreach ($items as $it) {
                    $versi = $it->Id_Item_Versi_Aktif ? (int) $it->Id_Item_Versi_Aktif : null;
                    $kunci = $this->kunciProgres(
                        (int) $d->Id_SOP_Penjadwalan_Detail,
                        (int) $it->Id_SOP_Paket_Item,
                        $versi,
                    );

                    if (isset($ada[$kunci])) {
                        continue;
                    }
                    $ada[$kunci] = true;

                    $barisBaru[] = [
                        'Id_SOP_Penjadwalan_Detail' => $d->Id_SOP_Penjadwalan_Detail,
                        'Id_SOP_Paket_Item' => $it->Id_SOP_Paket_Item,
                        'Id_SOP_Item_Versi' => $versi,
                        'Kode_Karyawan' => $d->Kode_Karyawan,
                        'Tipe_Item' => $it->Tipe_Item,
                        'Status' => SopStatus::ITEM_BELUM,
                        'Durasi_Aktif_Detik' => 0,
                        'Jumlah_Percobaan' => 0,
                        'Created_At' => now(),
                    ];

                    if ($versi !== null) {
                        $tambahTarget[$versi] = ($tambahTarget[$versi] ?? 0) + 1;
                    }
                    $adaKewajibanBaru[(int) $d->Id_SOP_Penjadwalan_Detail] = true;
                    $stat['seeded']++;
                }
            }

            if ($barisBaru !== []) {
                PenyisipanBerbatasParameter::sisipkan('N_SOP_Progress', $barisBaru);
            }

            foreach ($tambahTarget as $versi => $jumlah) {
                $this->bumpTarget($idPaket, (int) $versi, (int) $jumlah);
            }

            /* Hitung ulang status paket tetap PER DETAIL: keputusannya bergantung
               pada seluruh isi paket milik orang itu, bukan pada satu baris. */
            foreach ($details as $d) {
                $lama = $d->Status_Paket;
                $baru = $this->recompute($d->Id_SOP_Penjadwalan_Detail, $idPaket, $lama);

                // buka kembali: paket tadinya SELESAI, kini ada kewajiban baru
                if (($adaKewajibanBaru[(int) $d->Id_SOP_Penjadwalan_Detail] ?? false)
                    && $lama === SopStatus::STATUS_SELESAI
                    && $baru !== SopStatus::STATUS_SELESAI) {
                    $this->ledgerReopen($d->Id_SOP_Penjadwalan_Detail, $idPaket, $d->Kode_Karyawan, $alasan, $baru);
                    $stat['reopened']++;
                    $reopenedKodes[] = $d->Kode_Karyawan;
                }
            }
        });
    }

    /**
     * Kunci satu baris progres.
     *
     * Id_SOP_Item_Versi boleh NULL (item tanpa versi aktif), dan NULL harus
     * punya kunci sendiri yang tak pernah bertabrakan dengan versi 0.
     */
    private function kunciProgres(int $detailId, int $itemId, ?int $versi): string
    {
        return $detailId.'|'.$itemId.'|'.($versi === null ? 'x' : $versi);
    }

    /** Notifikasi + jejak setelah seluruh potongan selesai. */
    private function tutupReconcile(
        int $idPaket,
        string $alasan,
        ?int $userId,
        array $stat,
        array $reopenedKodes,
        bool $tanpaNotifikasi = false,
    ): array {
        // notifikasi "diperbarui" ke karyawan yang kewajibannya dibuka kembali
        if (! empty($reopenedKodes) && ! $tanpaNotifikasi) {
            $nama = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket');
            /* Item ditautkan lewat DOKUMEN (mundur ke FK untuk versi ber-dokumen
               NULL) — situs join versi->item yang sama seperti di Fase 4.
               Dengan FK saja, paket yang menyajikan dokumen bersama tak
               menemukan Ringkasan_Perubahan apa pun (versinya "milik" item di
               paket lain), sehingga notifikasi "SOP diperbarui" terkirim tanpa
               menyebutkan apa yang berubah — persis informasi yang membuat
               karyawan tahu bagian mana yang harus dibaca ulang. */
            $ring = DB::table('N_SOP_Item_Versi as v')
                ->join('N_SOP_Paket_Item as i', fn ($j) => SopVersiItem::tautkan($j, 'i', 'v'))
                ->where('i.Id_SOP_Paket', $idPaket)
                ->where('v.Status', SopStatus::PAKET_PUBLISHED)
                ->whereNotNull('v.Ringkasan_Perubahan')
                ->orderByDesc('v.Tanggal_Publish')
                ->value('v.Ringkasan_Perubahan');
            (new SopNotificationService)->notifDiperbarui(array_unique($reopenedKodes), $idPaket, $nama ?? 'Paket Dokumen', $ring);
        }

        if (array_sum($stat) > 0) {
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'RECONCILE',
                "Reconcile paket ({$alasan}): {$stat['seeded']} seed, {$stat['reopened']} dibuka ulang, {$stat['digantikan']} digantikan"
                    .($stat['gagal'] > 0 ? ", {$stat['gagal']} karyawan GAGAL diproses" : '').'.',
                $idPaket, null, null, null,
                array_merge($stat, ['alasan' => $alasan, 'oleh_id_users' => $userId]),
            );
        }

        // Kode karyawan yang dibuka ulang dikembalikan supaya pemanggil yang
        // menahan notifikasi punya bahan untuk notifikasi gabungannya.
        $stat['reopened_kodes'] = array_values(array_unique($reopenedKodes));

        return $stat;
    }

    /** Hitung ulang Status_Paket dari item wajib (item-driven, versi aktif). */
    /**
     * @param  string|null  $lamaDiketahui  Status_Paket yang sudah dipegang pemanggil.
     *                                      Diserahkan agar recompute tidak membacanya
     *                                      ulang: pada reconcile, pemanggilnya sudah
     *                                      memuat kolom itu bersama daftar detail,
     *                                      dan pembacaan ulang berarti satu kueri
     *                                      per karyawan untuk nilai yang sudah ada
     *                                      di tangan (terekam 434x dalam satu jalan
     *                                      uji).
     */
    private function recompute(int $detailId, int $idPaket, ?string $lamaDiketahui = null): string
    {
        $agg = DB::table('N_SOP_Paket_Item as i')
            ->leftJoin('N_SOP_Progress as pr', function ($j) use ($detailId) {
                $j->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item')
                    ->where('pr.Id_SOP_Penjadwalan_Detail', '=', $detailId)
                    ->on(DB::raw('ISNULL(pr.Id_SOP_Item_Versi,-1)'), '=', DB::raw('ISNULL(i.Id_Item_Versi_Aktif,-1)'));
            })
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Wajib', SopStatus::AKTIF)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->selectRaw("COUNT(*) as total,
                SUM(CASE WHEN pr.Status IN ('SELESAI','DIKONFIRMASI') THEN 1 ELSE 0 END) as selesai")
            ->first();

        $total = (int) ($agg->total ?? 0);
        $selesai = (int) ($agg->selesai ?? 0);

        if ($total > 0 && $selesai >= $total) {
            $baru = SopStatus::STATUS_SELESAI;
        } elseif ($selesai > 0) {
            $baru = SopStatus::STATUS_PROGRESS;
        } else {
            $baru = SopStatus::STATUS_BELUM;
        }

        $lama = $lamaDiketahui ?? DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan_Detail', $detailId)
            ->value('Status_Paket');

        $payload = ['Status_Paket' => $baru, 'Updated_At' => now()];

        /*
         * Tanggal_Selesai hanya ditulis pada TRANSISI, bukan setiap kali recompute
         * dipanggil. reconcilePaket() berjalan pada SETIAP publish paket dan
         * menyentuh seluruh detail; menulis now() tanpa syarat membuat tanggal
         * penyelesaian historis setiap karyawan bergeser ke hari publish, walau
         * status mereka tidak berubah sama sekali. Itu merusak jejak audit
         * kepatuhan, kolom "Tgl Selesai" di Monitoring, dan saringan rentang
         * date_mode=selesai secara permanen.
         */
        if ($baru !== $lama) {
            $payload['Tanggal_Selesai'] = $baru === SopStatus::STATUS_SELESAI ? now() : null;
        }

        DB::table('N_SOP_Penjadwalan_Detail')->where('Id_SOP_Penjadwalan_Detail', $detailId)->update($payload);

        return $baru;
    }

    /** Ledger DIBUKA_LAGI + snapshot. */
    private function ledgerReopen(int $detailId, int $idPaket, string $kode, string $alasan, string $status): void
    {
        $snapshot = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Wajib', SopStatus::AKTIF)->where('Flag_Aktif', SopStatus::AKTIF)
            ->get(['Id_SOP_Paket_Item', 'Judul_Item', 'Id_Item_Versi_Aktif']);

        DB::table('N_SOP_Kepatuhan_Riwayat')->insert([
            'Id_SOP_Penjadwalan_Detail' => $detailId,
            'Id_SOP_Paket' => $idPaket,
            'Kode_Karyawan' => $kode,
            'Peristiwa' => SopStatus::RWT_DIBUKA_LAGI,
            /*
             * Whitelist mengikuti CK_N_SOP_Rwt_Alasan. Builder mengirim
             * 'PAKET_PUBLISH' yang tidak ada di sana, sehingga SEMUA reopen akibat
             * publish tercatat 'MANUAL' — alasan sebenarnya hilang dari ledger dan
             * audit tak bisa membedakan publish dari tindakan manual admin.
             * Dipetakan ke 'STRUKTUR' karena publish paket memang mengubah struktur
             * kewajiban; nilai lain di luar whitelist tetap jatuh ke 'MANUAL'.
             */
            'Alasan' => $this->alasanLedger($alasan),
            'Snapshot_JSON' => json_encode(['wajib' => $snapshot], JSON_UNESCAPED_UNICODE),
            'Persen_Saat_Itu' => null,
            'Waktu_Peristiwa' => now(),
            'Created_At' => now(),
        ]);
    }

    /**
     * Petakan alasan reconcile ke nilai yang diterima CK_N_SOP_Rwt_Alasan.
     *
     * PAKET_PUBLISH dari Builder tidak ada di whitelist constraint, jadi harus
     * diterjemahkan — bukan dijatuhkan ke MANUAL yang menghapus jejak sebabnya.
     */
    private function alasanLedger(string $alasan): string
    {
        $sah = ['ITEM_BARU', 'VERSI_BARU', 'STRUKTUR', 'ONBOARDING', 'MANUAL'];
        if (in_array($alasan, $sah, true)) {
            return $alasan;
        }

        return $alasan === 'PAKET_PUBLISH' ? 'STRUKTUR' : 'MANUAL';
    }

    /** Increment Total_Target rollup (upsert). */
    private function bumpTarget(int $idPaket, int $idVersi, int $jumlah = 1): void
    {
        if ($jumlah < 1) {
            return;
        }

        // Baris baru langsung menanam Total_Target sebanyak yang disemai, jadi tak
        // perlu UPDATE lagi. Bila balapan dimenangkan pihak lain, pastikan()
        // mengembalikan false dan alirannya jatuh ke UPDATE di bawah — sasaran ini
        // tetap terhitung.
        if (SopRollupBaris::pastikan($idPaket, $idVersi, $jumlah)) {
            return;
        }

        DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $idPaket)->where('Id_SOP_Item_Versi', $idVersi)
            ->update([
                'Total_Target' => DB::raw('Total_Target + '.$jumlah),
                'Persen_Dikonfirmasi' => DB::raw(
                    'CASE WHEN (Total_Target + '.$jumlah.') > 0 THEN CAST(Jumlah_Dikonfirmasi AS DECIMAL(9,2)) / (Total_Target + '.$jumlah.') * 100 ELSE 0 END'
                ),
                'Updated_At' => now(),
            ]);
    }
}
