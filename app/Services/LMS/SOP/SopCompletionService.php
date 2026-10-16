<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopPendaftaranMandiri;
use App\Services\LMS\SOP\Support\SopRollupBaris;
use App\Services\LMS\SOP\Support\SopSequenceGate;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Completion engine SOP (aksi karyawan).
 * buka()/konfirmasi() → update Progress → recompute Status_Paket → bump
 * Coverage_Rollup → tulis ledger Kepatuhan_Riwayat saat paket SELESAI.
 *
 * IDEMPOTEN: increment rollup HANYA pada transisi status nyata (buka sekali,
 * konfirmasi sekali). Aman dipanggil ulang (mis. retry jaringan).
 * IDOR: resolusi selalu lewat penugasan aktif milik karyawan itu sendiri.
 */
class SopCompletionService
{
    /**
     * Ambang lulus cadangan untuk item KUIS yang Nilai_Minimum-nya kosong
     * (data lama; Builder kini mewajibkan pengisiannya).
     */
    private const AMBANG_LULUS_BAWAAN = 70;

    private array $selesaiStatuses = [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI];

    private array $openedStatuses = [SopStatus::ITEM_DIBUKA, SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI];

    /**
     * Resolusi konteks progress utk (karyawan × item).
     *
     * $muatProgress = false melewatkan SELECT baris progress. Barisnya memang
     * tak dipakai pemanggil yang menulis lewat conditional update (simpanHalaman):
     * di sana nilai lamanya dihitung di dalam UPDATE itu sendiri, jadi membacanya
     * lebih dulu hanya menambah satu kueri pada jalur terpanas modul ini.
     * buka()/konfirmasi() tetap memuatnya seperti sebelumnya.
     *
     * @return array|string array[item, detailId, versiId, progress] atau string kode-error.
     */
    private function resolve(string $kode, int $idItem, bool $muatProgress = true)
    {
        $item = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $idItem)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first(['Id_SOP_Paket_Item', 'Id_SOP_Paket', 'Id_Item_Versi_Aktif', 'Tipe_Item', 'Flag_Wajib']);
        if (! $item) {
            return 'ITEM_NOT_FOUND';
        }

        // Paket PUBLIK boleh dibuka tanpa penugasan, tapi progres berkunci pada
        // Id_SOP_Penjadwalan_Detail — tanpa baris itu kemajuan membaca tak punya
        // tempat disimpan dan tombol "Tandai Selesai" selalu ditolak. Jalur kuis
        // sudah lama memecahkannya lewat pendaftaran MANDIRI; di sini dipakai
        // helper yang sama supaya kedua jalur tak menyimpang. Paket TERBATAS
        // tetap mengembalikan NOT_ASSIGNED.
        $detailId = SopPendaftaranMandiri::pastikanDetailId($kode, (int) $item->Id_SOP_Paket);
        if (! $detailId) {
            return 'NOT_ASSIGNED';
        }

        $versiId = $item->Id_Item_Versi_Aktif ? (int) $item->Id_Item_Versi_Aktif : null;

        $progress = null;
        if ($muatProgress) {
            $q = DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->where('Id_SOP_Paket_Item', $idItem);
            $versiId === null ? $q->whereNull('Id_SOP_Item_Versi') : $q->where('Id_SOP_Item_Versi', $versiId);
            $progress = $q->first();
        }

        return ['item' => $item, 'detailId' => (int) $detailId, 'versiId' => $versiId, 'progress' => $progress];
    }

    /**
     * Baris progress milik konteks ini, dikunci sampai transaksi selesai.
     * Dibuatkan bila belum ada (item opsional/ditambah belakangan).
     *
     * Status DIBACA ULANG di sini, tidak memakai $ctx['progress'].
     *
     * resolve() berjalan DI LUAR transaksi, jadi objek yang dibawanya sudah
     * usang begitu request lain menyelesaikan item yang sama. Gerbang idempoten
     * di buka()/konfirmasi() memeriksa status dari objek itu, sehingga dua
     * request beriringan sama-sama membaca "BELUM", sama-sama lolos gerbang,
     * dan sama-sama memanggil bumpRollup. UPDATE keduanya memang mengantre pada
     * X-lock milik yang pertama, tapi keputusan lolos-tidaknya sudah diambil di
     * PHP jauh sebelum itu — jadi penghitungnya tetap naik dua kali untuk satu
     * orang dan Persen_Dikonfirmasi bisa melampaui 100.
     *
     * lockForUpdate() bukan sekadar baca ulang: tanpa kunci, dua transaksi masih
     * bisa sama-sama menyelesaikan SELECT-nya sebelum salah satunya menulis.
     * Kuncinya ditahan sampai commit sehingga pembacaan status ikut terserialkan.
     *
     * Baris baru tetap bisa balapan karena kunci tak bisa memesan baris yang
     * belum ada; di situ UQ_N_SOP_Progress yang menengahi, dan pelanggarannya
     * diperlakukan sebagai "sudah dibuatkan request lain" lalu dibaca ulang.
     * Terverifikasi bahwa transaksi tidak doomed setelah galat 2627, jadi pola
     * ini aman di dalam DB::transaction.
     */
    private function ensureProgress(array $ctx, string $kode): object
    {
        if ($prog = $this->bacaProgressTerkunci($ctx)) {
            return $prog;
        }

        try {
            DB::table('N_SOP_Progress')->insert([
                'Id_SOP_Penjadwalan_Detail' => $ctx['detailId'],
                'Id_SOP_Paket_Item' => $ctx['item']->Id_SOP_Paket_Item,
                'Id_SOP_Item_Versi' => $ctx['versiId'],
                'Kode_Karyawan' => $kode,
                'Tipe_Item' => $ctx['item']->Tipe_Item,
                'Status' => SopStatus::ITEM_BELUM,
                'Durasi_Aktif_Detik' => 0,
                'Jumlah_Percobaan' => 0,
                'Created_At' => now(),
            ]);
        } catch (QueryException $e) {
            // 2627 = pelanggaran UNIQUE/PK, 2601 = pelanggaran index unik.
            if (! in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw $e;
            }
        }

        $prog = $this->bacaProgressTerkunci($ctx);
        if (! $prog) {
            // Bukan balapan: barisnya benar-benar tak terbentuk.
            throw new \RuntimeException('Baris progress dokumen gagal dibuat untuk '.$kode);
        }

        return $prog;
    }

    /** SELECT progress konteks ini dengan kunci baris; null bila belum ada. */
    private function bacaProgressTerkunci(array $ctx): ?object
    {
        $q = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $ctx['detailId'])
            ->where('Id_SOP_Paket_Item', $ctx['item']->Id_SOP_Paket_Item)
            ->lockForUpdate();

        $ctx['versiId'] === null
            ? $q->whereNull('Id_SOP_Item_Versi')
            : $q->where('Id_SOP_Item_Versi', $ctx['versiId']);

        return $q->first();
    }

    /** Tandai item DIBUKA (buka pertama). Return status akhir. */
    public function buka(string $kode, int $idItem)
    {
        $ctx = $this->resolve($kode, $idItem);
        if (is_string($ctx)) {
            return $ctx;
        }

        // Penegakan prasyarat: item terkunci (SEKUENSIAL) tak boleh dibuka via API.
        if (SopSequenceGate::isLocked($kode, (int) $ctx['item']->Id_SOP_Paket, $idItem)) {
            return 'TERKUNCI';
        }

        // Gerbang presensi. Ditegakkan di SINI, bukan hanya di UI: spanduk
        // "Presensi Kehadiran Wajib" tak ada artinya kalau endpoint-nya tetap
        // menerima permintaan langsung.
        if (! app(SopAbsensiService::class)->boleh($kode, (int) $ctx['item']->Id_SOP_Paket)) {
            return 'BELUM_ABSEN';
        }

        return DB::transaction(function () use ($ctx, $kode) {
            $prog = $this->ensureProgress($ctx, $kode);
            if (in_array($prog->Status, $this->openedStatuses, true)) {
                return 'OK'; // sudah dibuka → no-op
            }

            DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $prog->Id_SOP_Progress)->update([
                'Status' => SopStatus::ITEM_DIBUKA,
                'Waktu_Buka' => $prog->Waktu_Buka ?? now(),
                'Updated_At' => now(),
            ]);

            if ($ctx['versiId'] !== null) {
                $this->bumpRollup((int) $ctx['item']->Id_SOP_Paket, $ctx['versiId'], 'Jumlah_Dibuka');
            }

            $this->recomputeStatusPaket($ctx['detailId']);

            return 'OK';
        });
    }

    /** Konfirmasi/selesaikan item. Return status akhir. */
    public function konfirmasi(string $kode, int $idItem, ?float $persen = 100, ?string $ip = null, ?string $ua = null)
    {
        $ctx = $this->resolve($kode, $idItem);
        if (is_string($ctx)) {
            return $ctx;
        }

        // Penegakan prasyarat: item terkunci (SEKUENSIAL) tak boleh dikonfirmasi via API.
        if (SopSequenceGate::isLocked($kode, (int) $ctx['item']->Id_SOP_Paket, $idItem)) {
            return 'TERKUNCI';
        }

        if (! app(SopAbsensiService::class)->boleh($kode, (int) $ctx['item']->Id_SOP_Paket)) {
            return 'BELUM_ABSEN';
        }

        return DB::transaction(function () use ($ctx, $kode, $persen, $ip, $ua) {
            $prog = $this->ensureProgress($ctx, $kode);

            if (in_array($prog->Status, $this->selesaiStatuses, true)) {
                return 'OK'; // sudah selesai → idempoten no-op
            }

            $wasOpened = in_array($prog->Status, $this->openedStatuses, true);

            // DOKUMEN = DIKONFIRMASI (ada acknowledge); tipe lain = SELESAI
            $target = $ctx['item']->Tipe_Item === 'DOKUMEN'
                ? SopStatus::ITEM_DIKONFIRMASI
                : SopStatus::ITEM_SELESAI;

            $update = [
                'Status' => $target,
                'Persen_Selesai' => $persen,
                'Waktu_Buka' => $prog->Waktu_Buka ?? now(),
                'Waktu_Konfirmasi' => now(),
                'Updated_At' => now(),
            ];
            // Metadata konfirmasi (IP + perangkat) — kecuali PARAGRAF/informational
            if ($ctx['item']->Tipe_Item !== SopTipeItem::PARAGRAF) {
                $update['Konfirmasi_IP'] = $ip !== null ? substr($ip, 0, 45) : null;
                $update['Konfirmasi_User_Agent'] = $ua !== null ? substr($ua, 0, 255) : null;
            }

            DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $prog->Id_SOP_Progress)->update($update);

            if ($ctx['versiId'] !== null) {
                if (! $wasOpened) {
                    $this->bumpRollup((int) $ctx['item']->Id_SOP_Paket, $ctx['versiId'], 'Jumlah_Dibuka');
                }
                $this->bumpRollup((int) $ctx['item']->Id_SOP_Paket, $ctx['versiId'], 'Jumlah_Dikonfirmasi');
            }

            $this->recomputeStatusPaket($ctx['detailId']);

            return 'OK';
        });
    }

    /**
     * Simpan penanda halaman terakhir dibaca (untuk resume reader PDF).
     * Maju saja (GREATEST). Tak mengubah Status. Return 'OK' / kode-error.
     *
     * Ini jalur TERPANAS modul: dipanggil tiap pindah halaman DAN tiap 60 detik
     * per pembaca. Karena itu bentuknya conditional update — satu pernyataan yang
     * menghitung nilai barunya sendiri, bukan baca-ubah-tulis.
     *
     * Sebelumnya jalur ini menempuh ensureProgress() → bacaProgressTerkunci(),
     * yang memasang UPDLOCK/HOLDLOCK. simpanHalaman() TIDAK berada di dalam
     * transaksi, jadi di autocommit kunci itu dilepas begitu pernyataannya
     * selesai: ia tak pernah melindungi apa pun, tetapi tetap dipasang pada
     * tabel terpanas, setiap 60 detik, untuk setiap pembaca.
     *
     * bacaProgressTerkunci() sendiri TETAP dipakai buka()/konfirmasi(): keduanya
     * berjalan di dalam transaksi dan memang butuh serialisasi.
     */
    public function simpanHalaman(string $kode, int $idItem, int $halaman, int $durasiDetik = 0)
    {
        $ctx = $this->resolve($kode, $idItem, false);
        if (is_string($ctx)) {
            return $ctx;
        }

        /*
         * Waktu baca aktif — akumulasi DELTA dari reader (detik saat tab terlihat,
         * di-flush per pindah halaman / 60 dtk). Ini penulis pertama kolom
         * Durasi_Aktif_Detik; sebelumnya kolom itu selalu 0 karena tak ada
         * penulisnya sama sekali.
         *
         * Delta di-cap 600 dtk per laporan: flush normal ≤60 dtk, jadi apa pun di
         * atas 10 menit berarti klien yang mengarang angka — dipotong, bukan
         * ditolak, supaya laporan jujur yang menumpang di request telat tak hangus.
         */
        $tambah = max(0, min($durasiDetik, 600));

        if ($this->majukanHalaman($ctx, $halaman, $tambah) === 0) {
            // Nol baris terpengaruh = barisnya memang belum ada. Baru di sinilah
            // jalur pembuatan (yang mahal dan berkunci) ditempuh, lalu UPDATE-nya
            // diulang SEKALI. Tak ada loop: bila yang kedua pun nol, barisnya
            // dihapus di antara keduanya dan tak ada yang perlu disimpan.
            $this->ensureProgress($ctx, $kode);
            $this->majukanHalaman($ctx, $halaman, $tambah);
        }

        return 'OK';
    }

    /**
     * Conditional update penanda halaman + akumulasi durasi.
     *
     * @return int jumlah baris terpengaruh (0 = baris progressnya belum ada).
     */
    private function majukanHalaman(array $ctx, int $halaman, int $tambah): int
    {
        /*
         * Halaman tak pernah mundur: CASE-nya dihitung DI DALAM basis data atas
         * nilai yang sedang tersimpan, sehingga dua flush beriringan tak bisa
         * saling menimpa dengan nilai basi — itulah yang dulu dijaga oleh baca-
         * ubah-tulis, dan di sini dijaga tanpa kunci sama sekali.
         *
         * Id_SOP_Item_Versi boleh NULL, dan `= NULL` tak pernah cocok — predikatnya
         * harus IS NULL. Bentuk kuncinya identik dengan resolve()/ensureProgress()
         * supaya baris yang dipilih ketiganya persis sama.
         */
        $sql = 'UPDATE N_SOP_Progress
                   SET Halaman_Terakhir   = CASE WHEN ISNULL(Halaman_Terakhir, 0) < ?
                                                 THEN ? ELSE Halaman_Terakhir END,
                       Durasi_Aktif_Detik = ISNULL(Durasi_Aktif_Detik, 0) + ?,
                       Waktu_Buka         = ISNULL(Waktu_Buka, SYSDATETIME()),
                       Updated_At         = SYSDATETIME()
                 WHERE Id_SOP_Penjadwalan_Detail = ?
                   AND Id_SOP_Paket_Item = ?
                   AND '.($ctx['versiId'] === null ? 'Id_SOP_Item_Versi IS NULL' : 'Id_SOP_Item_Versi = ?');

        $bind = [
            $halaman,
            $halaman,
            $tambah,
            $ctx['detailId'],
            (int) $ctx['item']->Id_SOP_Paket_Item,
        ];
        if ($ctx['versiId'] !== null) {
            $bind[] = $ctx['versiId'];
        }

        return DB::update($sql, $bind);
    }

    /**
     * Simpan hasil kuis dari callback HC Learn ke progress VERSI SPESIFIK
     * (versi token dari integrasi, bukan versi aktif terkini — supaya callback basi
     * tak menyelesaikan versi test yang baru). Return 'LULUS'|'BELUM_LULUS'|'ALREADY_DONE'.
     */
    /**
     * @param  string|null  $token  Id_Ujian_Token sesi ujian. Dipakai sebagai kunci
     *                              anti-dobel: satu token = satu sesi = satu hasil. HC Learn melakukan
     *                              retry (terbukti di log: satu token, tiga panggilan), dan tanpa ini
     *                              callback berulang atas hasil TIDAK LULUS menaikkan Jumlah_Percobaan
     *                              berkali-kali — dengan Maks_Percobaan = 1, peserta kehilangan jatah
     *                              tanpa pernah ujian ulang.
     */
    public function submitKuisResult(int $detailId, int $idItem, ?int $idVersi, int $idPaket, string $kode, string $tipe, float $score, ?float $nilaiMin, ?string $token = null): string
    {
        /*
         * Ambang lulus tak boleh kosong. Sebelumnya Nilai_Minimum NULL berarti
         * "lulus otomatis" — skor 0 pun lolos, dan itu diam-diam meloloskan orang
         * yang belum paham hanya karena admin lupa mengisi satu kolom.
         *
         * Builder sekarang mewajibkannya untuk item KUIS ber-ujian, tapi data lama
         * bisa saja masih kosong, jadi di sini dipakai ambang bawaan sebagai jaring
         * pengaman — dicatat supaya konfigurasinya diperbaiki, bukan didiamkan.
         */
        if ($nilaiMin === null) {
            $nilaiMin = self::AMBANG_LULUS_BAWAAN;
            Log::warning('SOP kuis: Nilai_Minimum kosong, memakai ambang bawaan', [
                'item' => $idItem,
                'ambang' => $nilaiMin,
            ]);
        }
        $lulus = $score >= $nilaiMin;

        return DB::transaction(function () use ($detailId, $idItem, $idVersi, $idPaket, $kode, $tipe, $score, $lulus, $token) {
            $q = DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->where('Id_SOP_Paket_Item', $idItem);
            $idVersi === null ? $q->whereNull('Id_SOP_Item_Versi') : $q->where('Id_SOP_Item_Versi', $idVersi);
            $prog = $q->first();

            if (! $prog) {
                $pid = DB::table('N_SOP_Progress')->insertGetId([
                    'Id_SOP_Penjadwalan_Detail' => $detailId,
                    'Id_SOP_Paket_Item' => $idItem,
                    'Id_SOP_Item_Versi' => $idVersi,
                    'Kode_Karyawan' => $kode,
                    'Tipe_Item' => $tipe,
                    'Status' => SopStatus::ITEM_BELUM,
                    'Durasi_Aktif_Detik' => 0,
                    'Jumlah_Percobaan' => 0,
                    'Created_At' => now(),
                ], 'Id_SOP_Progress');
                $prog = DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $pid)->first();
            }

            // Hasil token ini sudah dibukukan → callback ulang, bukan percobaan baru.
            // Dicek SEBELUM status, supaya retry atas hasil TIDAK LULUS pun tertahan.
            if ($token !== null && (string) ($prog->Id_Ujian_Token_Hasil ?? '') === $token) {
                return 'DUPLIKAT';
            }

            if (in_array($prog->Status, $this->selesaiStatuses, true)) {
                return 'ALREADY_DONE'; // idempoten (callback ganda)
            }

            $wasOpened = in_array($prog->Status, $this->openedStatuses, true);

            /*
             * Nilai yang BERLAKU = hasil percobaan TERAKHIR.
             * Dengan MAKS_PERCOBAAN = 1 hanya ada satu percobaan, jadi "terakhir"
             * dan "tertinggi" identik — dipilih yang paling sederhana, dan riwayat
             * per-percobaan sengaja tidak disimpan.
             * CATATAN: bila plafon percobaan dinaikkan di atas 1, kebijakan ini
             * perlu ditinjau ulang — mengulang lalu dapat skor lebih rendah akan
             * MENURUNKAN nilai yang tercatat.
             */
            $jumlahPercobaan = (int) ($prog->Jumlah_Percobaan ?? 0) + 1;
            $percobaanFinalGagal = ! $lulus
                && SopKuisDispatchService::MAKS_PERCOBAAN > 0
                && $jumlahPercobaan >= SopKuisDispatchService::MAKS_PERCOBAAN;
            $hasilFinal = $lulus || $percobaanFinalGagal;

            $update = [
                'Skor_Quiz' => $score,
                'Status_Lulus' => $lulus ? 'Y' : 'T',
                'Jumlah_Percobaan' => $jumlahPercobaan,
                'Id_Ujian_Token_Hasil' => $token,
                'Waktu_Buka' => $prog->Waktu_Buka ?? now(),
                'Updated_At' => now(),
            ];

            // Percobaan terakhir menutup alur item. Hasil lulus/gagal tetap
            // dibedakan oleh Status_Lulus dan Skor_Quiz.
            if ($hasilFinal) {
                $update['Status'] = SopStatus::ITEM_SELESAI;
                $update['Persen_Selesai'] = 100;
                $update['Waktu_Konfirmasi'] = now();
            }
            DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $prog->Id_SOP_Progress)->update($update);

            if ($hasilFinal && $idVersi !== null) {
                if (! $wasOpened) {
                    $this->bumpRollup($idPaket, $idVersi, 'Jumlah_Dibuka');
                }
                $this->bumpRollup($idPaket, $idVersi, 'Jumlah_Dikonfirmasi');
            }

            $this->recomputeStatusPaket($detailId);
            if ($lulus) {
                return 'LULUS';
            }

            return $percobaanFinalGagal ? 'TIDAK_LULUS_FINAL' : 'BELUM_LULUS';
        });
    }

    /**
     * Pulihkan hasil kuis final yang sudah tercatat tetapi belum menutup item.
     * Ini menjadi jaring pengaman bila callback diproses instance lama atau
     * terputus setelah menyimpan nilai dan jumlah percobaan.
     */
    public function reconcileFinalQuizAttempts(int $detailId): int
    {
        $maks = SopKuisDispatchService::MAKS_PERCOBAAN;
        if ($maks <= 0) {
            return 0;
        }

        /*
         * Pra-cek baca-saja sebelum membuka transaksi.
         *
         * Ini jaring pengaman yang hampir selalu tak menemukan apa pun, tetapi
         * dipanggil dari detailBundle() — jalur BACA yang dijalankan setiap kali
         * halaman detail SOP dibuka. Tanpa pra-cek, setiap pembacaan itu membuka
         * transaksi dan memasang lockForUpdate; pada SQL Server, Laravel
         * menerbitkannya sebagai with(rowlock, updlock, holdlock), yaitu range lock
         * serializable — dipasang hanya untuk mendapati tak ada baris yang cocok.
         *
         * Pengecekannya memakai syarat yang SAMA persis dengan kueri di dalam
         * transaksi, jadi tak ada kasus yang jadi terlewat: bila ada yang cocok,
         * jalur berkunci tetap ditempuh dan pemeriksaannya diulang di sana.
         */
        $adaCalon = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->where('pr.Id_SOP_Penjadwalan_Detail', $detailId)
            ->where('i.Tipe_Item', SopTipeItem::KUIS)
            ->where('pr.Jumlah_Percobaan', '>=', $maks)
            ->whereIn('pr.Status_Lulus', ['Y', 'T', 'N'])
            ->whereNotIn('pr.Status', $this->selesaiStatuses)
            ->exists();

        if (! $adaCalon) {
            return 0;
        }

        return DB::transaction(function () use ($detailId, $maks) {
            $rows = DB::table('N_SOP_Progress as pr')
                ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
                ->where('pr.Id_SOP_Penjadwalan_Detail', $detailId)
                ->where('i.Tipe_Item', SopTipeItem::KUIS)
                ->where('pr.Jumlah_Percobaan', '>=', $maks)
                ->whereIn('pr.Status_Lulus', ['Y', 'T', 'N'])
                ->whereNotIn('pr.Status', $this->selesaiStatuses)
                ->lockForUpdate()
                ->get([
                    'pr.Id_SOP_Progress',
                    'pr.Id_SOP_Item_Versi',
                    'pr.Status',
                    'i.Id_SOP_Paket',
                ]);

            foreach ($rows as $row) {
                DB::table('N_SOP_Progress')
                    ->where('Id_SOP_Progress', $row->Id_SOP_Progress)
                    ->update([
                        'Status' => SopStatus::ITEM_SELESAI,
                        'Persen_Selesai' => 100,
                        'Waktu_Konfirmasi' => DB::raw('COALESCE(Waktu_Konfirmasi, GETDATE())'),
                        'Updated_At' => now(),
                    ]);

                if ($row->Id_SOP_Item_Versi !== null) {
                    if (! in_array($row->Status, $this->openedStatuses, true)) {
                        $this->bumpRollup((int) $row->Id_SOP_Paket, (int) $row->Id_SOP_Item_Versi, 'Jumlah_Dibuka');
                    }
                    $this->bumpRollup((int) $row->Id_SOP_Paket, (int) $row->Id_SOP_Item_Versi, 'Jumlah_Dikonfirmasi');
                }
            }

            if ($rows->isNotEmpty()) {
                $this->recomputeStatusPaket($detailId);
            }

            return $rows->count();
        });
    }

    /**
     * Hitung ulang Status_Paket dari item WAJIB. Bila transisi ke SELESAI →
     * set Tanggal_Selesai + tulis ledger SELESAI (sekali).
     */
    private function recomputeStatusPaket(int $detailId): void
    {
        $detail = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('d.Id_SOP_Penjadwalan_Detail', $detailId)
            ->first(['d.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan', 'd.Status_Paket', 'pj.Id_SOP_Paket']);
        if (! $detail) {
            return;
        }

        // item-driven: tiap item wajib dihitung SEKALI, dicocokkan ke progress versi AKTIF
        $agg = DB::table('N_SOP_Paket_Item as i')
            ->leftJoin('N_SOP_Progress as pr', function ($j) use ($detailId) {
                $j->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item')
                    ->where('pr.Id_SOP_Penjadwalan_Detail', '=', $detailId)
                    ->on(DB::raw('ISNULL(pr.Id_SOP_Item_Versi,-1)'), '=', DB::raw('ISNULL(i.Id_Item_Versi_Aktif,-1)'));
            })
            ->where('i.Id_SOP_Paket', $detail->Id_SOP_Paket)
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

        $lama = $detail->Status_Paket;

        $payload = ['Status_Paket' => $baru, 'Updated_At' => now()];

        // Sesuai docblock method ini: Tanggal_Selesai hanya disentuh pada TRANSISI.
        // Menulisnya tanpa syarat membuat setiap konfirmasi item berikutnya
        // menggeser tanggal penyelesaian yang sudah tercatat.
        if ($baru !== $lama) {
            $payload['Tanggal_Selesai'] = $baru === SopStatus::STATUS_SELESAI ? now() : null;
        }

        DB::table('N_SOP_Penjadwalan_Detail')->where('Id_SOP_Penjadwalan_Detail', $detailId)->update($payload);

        // ledger hanya saat transisi BARU ke SELESAI
        if ($baru === SopStatus::STATUS_SELESAI && $lama !== SopStatus::STATUS_SELESAI) {
            $this->tulisLedger($detail, 100);
        }
    }

    /** Append ledger append-only + snapshot kewajiban. */
    private function tulisLedger(object $detail, float $persen): void
    {
        $snapshot = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $detail->Id_SOP_Paket)
            ->where('Flag_Wajib', SopStatus::AKTIF)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->get(['Id_SOP_Paket_Item', 'Judul_Item', 'Id_Item_Versi_Aktif']);

        DB::table('N_SOP_Kepatuhan_Riwayat')->insert([
            'Id_SOP_Penjadwalan_Detail' => $detail->Id_SOP_Penjadwalan_Detail,
            'Id_SOP_Paket' => $detail->Id_SOP_Paket,
            'Kode_Karyawan' => $detail->Kode_Karyawan,
            'Peristiwa' => SopStatus::RWT_SELESAI,
            'Alasan' => null,
            'Snapshot_JSON' => json_encode(['wajib' => $snapshot], JSON_UNESCAPED_UNICODE),
            'Persen_Saat_Itu' => $persen,
            'Waktu_Peristiwa' => now(),
            'Created_At' => now(),
        ]);
    }

    /** Increment atomik kolom rollup + recompute Persen_Dikonfirmasi. */
    private function bumpRollup(int $idPaket, int $idVersi, string $kolom): void
    {
        // Barisnya dijamin ada tanpa memicu duplicate-key saat dua konfirmasi
        // pertama atas versi item baru berjalan beriringan. Nilai awal 0 karena
        // penambahannya dikerjakan UPDATE di bawah, baik baris baru maupun lama.
        SopRollupBaris::pastikan($idPaket, $idVersi, 0);

        DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $idPaket)->where('Id_SOP_Item_Versi', $idVersi)
            ->update([$kolom => DB::raw("{$kolom} + 1"), 'Updated_At' => now()]);

        // recompute persen (Dikonfirmasi / Total_Target)
        DB::table('N_SOP_Coverage_Rollup')
            ->where('Id_SOP_Paket', $idPaket)->where('Id_SOP_Item_Versi', $idVersi)
            ->update(['Persen_Dikonfirmasi' => DB::raw(
                'CASE WHEN Total_Target > 0 THEN CAST(Jumlah_Dikonfirmasi AS DECIMAL(9,2)) / Total_Target * 100 ELSE 0 END'
            )]);
    }
}
