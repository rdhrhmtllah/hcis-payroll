<?php

namespace App\Services\LMS\SOP;

use App\Jobs\LMS\Training\Scheduling\ProcessBulkPenjadwalan;
use App\Services\LMS\SOP\Support\SopRollupBaris;
use App\Services\LMS\SOP\Support\SopSebabCabut;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service Penjadwalan SOP (distribusi + PUBLISH ENGINE).
 *
 * assign() = mata rantai admin↔karyawan:
 *   header N_SOP_Penjadwalan → detail per karyawan → SEED N_SOP_Progress
 *   (item wajib × versi aktif) → increment N_SOP_Coverage_Rollup.
 *
 * Assign manual (daftar Kode_Karyawan) berjalan BERBASIS HIMPUNAN di dalam satu
 * transaksi: bulk insert detail, lalu progress diturunkan lewat INSERT ... SELECT
 * yang menjoin balik ke detail — bukan perulangan per karyawan.
 * Target berjumlah besar tetap dipecah di hulu oleh ProsesBulkAssignSop.
 */
class SopPenjadwalanService
{
    /** Ukuran chunk bulkCabut — pagar parameter whereIn SQL Server, bukan titik potong. */
    private const MAKS_BULK_CABUT = 500;

    /** Cari karyawan aktif untuk target picker. */
    public function searchKaryawan(?string $q, int $limit = 20)
    {
        $query = DB::table('Karyawan')->where('Aktif', 'Y')
            ->select('Kode_Karyawan', 'Nama', 'Kode_Jabatan');
        if (! empty($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('Nama', 'LIKE', "%{$q}%")->orWhere('Kode_Karyawan', 'LIKE', "%{$q}%");
            });
        }

        return $query->orderBy('Nama')->limit($limit)->get();
    }

    /**
     * Pagar atas opsi paket. Bukan paginasi: pencarian paket di wizard tetap
     * berjalan di sisi klien. Batas ini hanya menjaga response tidak pernah
     * tumbuh tanpa batas kalau master paket membengkak.
     */
    public const BATAS_OPSI_PAKET = 300;

    /**
     * Opsi paket PUBLISHED (untuk didistribusi). Detail dimuat setelah paket dipilih.
     *
     * @return array{items: \Illuminate\Support\Collection, terpotong: bool, batas: int}
     */
    public function paketPublished(int $batas = self::BATAS_OPSI_PAKET): array
    {
        // Ambil satu baris lebih dari batas: cukup untuk tahu masih ada sisa
        // tanpa menambah query COUNT terpisah.
        $rows = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->orderBy('p.Nama_Paket')
            ->limit($batas + 1)
            ->get([
                'p.Id_SOP_Paket',
                'p.Kode_Paket',
                'p.Nama_Paket',
                'p.Mode_Distribusi',
                'p.Prioritas',
                'p.Versi_Paket_Aktif',
                'p.Deskripsi',
                'p.Tujuan_Ruang_Lingkup',
                'p.Estimasi_Waktu_Baca_Menit',
            ]);

        $terpotong = $rows->count() > $batas;
        $taken = $rows->take($batas);
        $ids = $taken->pluck('Id_SOP_Paket')->all();

        $komposisi = ! empty($ids)
            ? DB::table('N_SOP_Paket_Item')
                ->whereIn('Id_SOP_Paket', $ids)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->groupBy('Id_SOP_Paket', 'Tipe_Item')
                ->selectRaw('Id_SOP_Paket, Tipe_Item, COUNT(*) as jml')
                ->get()
                ->groupBy('Id_SOP_Paket')
            : collect();

        $items = $taken
            ->map(function ($p) use ($komposisi) {
                $comp = $komposisi->get($p->Id_SOP_Paket, collect())->keyBy('Tipe_Item');
                $dokumenCount = (int) ($comp->get('DOKUMEN')->jml ?? 0);
                $kuisCount = (int) ($comp->get('KUIS')->jml ?? 0);
                $totalItem = (int) $comp->sum('jml');

                $p->Id_SOP_Paket = Hashids::connection('custom')->encode($p->Id_SOP_Paket);
                $p->Mode_Distribusi = $p->Mode_Distribusi ?? 'PUBLIK';
                $p->Versi_Terakhir = (int) ($p->Versi_Paket_Aktif ?: 1);
                $p->Deskripsi = $p->Deskripsi ?: $p->Tujuan_Ruang_Lingkup;
                $p->Estimasi = $p->Estimasi_Waktu_Baca_Menit ? (int) $p->Estimasi_Waktu_Baca_Menit : 10;
                $p->Total_Dokumen = $dokumenCount;
                $p->Total_Kuis = $kuisCount;
                $p->Total_Item = $totalItem;
                unset($p->Versi_Paket_Aktif);

                return $p;
            })
            ->values();

        return ['items' => $items, 'terpotong' => $terpotong, 'batas' => $batas];
    }

    /** Status draft dimuat hanya untuk paket yang sudah dipilih. */
    public function paketMemilikiDraft(int $idPaket): bool
    {
        return DB::table('N_SOP_Paket_Draft')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Status', SopStatus::VERSI_DRAFT)
            ->exists();
    }

    /** Daftar distribusi + coverage ringkas per paket. */
    public function listByPaket(?int $idPaket)
    {
        $q = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->select(
                'pj.Id_SOP_Penjadwalan', 'pj.Id_SOP_Paket', 'p.Nama_Paket',
                'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline', 'pj.Tanggal_Assign',
                DB::raw('(SELECT COUNT(*) FROM N_SOP_Penjadwalan_Detail d WHERE d.Id_SOP_Penjadwalan = pj.Id_SOP_Penjadwalan AND d.Flag_Aktif = \'Y\') as Total_Target'),
                DB::raw('(SELECT COUNT(*) FROM N_SOP_Penjadwalan_Detail d WHERE d.Id_SOP_Penjadwalan = pj.Id_SOP_Penjadwalan AND d.Status_Paket = \'SELESAI\') as Total_Selesai')
            );
        if ($idPaket) {
            $q->where('pj.Id_SOP_Paket', $idPaket);
        }

        return $q->orderByDesc('pj.Id_SOP_Penjadwalan')->get()->map(function ($r) {
            $r->Persen = $r->Total_Target > 0 ? round($r->Total_Selesai / $r->Total_Target * 100) : 0;
            $r->Id_SOP_Penjadwalan = Hashids::connection('custom')->encode($r->Id_SOP_Penjadwalan);
            $r->Id_SOP_Paket = Hashids::connection('custom')->encode($r->Id_SOP_Paket);

            return $r;
        });
    }

    /**
     * ASSIGN + seed progress + rollup.
     *
     * @param  int|null  $userId  Pembuat penjadwalan. NULL untuk penugasan yang tak
     *                            punya admin (pendaftaran mandiri SOP publik, Sumber='MANDIRI') —
     *                            rantai identitas kuis lalu jatuh ke akun layanan, lihat
     *                            SopKuisDispatchService::resolveIdentitas().
     * @return array ['penjadwalan_id'=>int, 'assigned'=>int, 'skipped'=>int]
     */
    public function assign(int $idPaket, array $kodeList, array $opts, ?int $userId): array
    {
        $res = DB::transaction(function () use ($idPaket, $kodeList, $opts, $userId) {
            $penjadwalanId = $this->buatHeader($idPaket, $opts, $userId);
            $hasil = $this->assignKeHeader($penjadwalanId, $idPaket, $kodeList, $userId);

            return array_merge(['penjadwalan_id' => $penjadwalanId], $hasil);
        });

        // notifikasi penugasan baru (setelah commit)
        if (! empty($res['targets'])) {
            $nama = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->value('Nama_Paket');
            (new SopNotificationService)->notifPenugasan($res['targets'], $idPaket, $nama ?? 'Paket Dokumen');
        }

        if ($res['assigned'] > 0) {
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'ASSIGN',
                "Paket ditugaskan ke {$res['assigned']} karyawan".($res['skipped'] > 0 ? " ({$res['skipped']} dilewati, sudah aktif)" : '').'.',
                $idPaket, null, null, null,
                [
                    'sumber' => $opts['sumber'] ?? 'MANUAL',
                    'kode_karyawan' => $res['targets'],
                    'id_penjadwalan' => $res['penjadwalan_id'],
                    'oleh_id_users' => $userId, // null = pendaftaran mandiri
                ],
            );
        }

        unset($res['targets']);

        return $res;
    }

    /** Insert header N_SOP_Penjadwalan. PEMANGGIL yang memegang transaksi. */
    public function buatHeader(int $idPaket, array $opts, ?int $userId): int
    {
        $data = [
            'Id_SOP_Paket' => $idPaket,
            'Nama_Batch' => $this->bersih($opts['nama_batch'] ?? null, 150),
            'Keterangan' => $this->bersih($opts['keterangan'] ?? null, 500),
            'Sumber' => $opts['sumber'] ?? 'MANUAL',
            'Tipe_Tenggat' => $opts['tipe_tenggat'] ?? 'SAMPAI_DICABUT',
            'Tanggal_Deadline' => $opts['tanggal_deadline'] ?? null,
            'Flag_Reminder' => ($opts['flag_reminder'] ?? false) ? 'Y' : 'T',
            'Irama_Reminder_Hari' => $opts['irama_reminder_hari'] ?? null,
            'Flag_Cabut_Akses' => 'T',
            'Tanggal_Assign' => now(),
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ];
        if (! empty($opts['id_assignment_rule'])) {
            $data['Id_SOP_Assignment_Rule'] = (int) $opts['id_assignment_rule'];
        }

        return DB::table('N_SOP_Penjadwalan')->insertGetId($data, 'Id_SOP_Penjadwalan');
    }

    /**
     * Inti assign: diff anti-duplikat → detail per karyawan → seed progress → rollup.
     *
     * TIGA pemakai: jalur sinkron (assign()), badan per-chunk job bulk async, dan
     * endpoint "tambah orang ke batch". Diff terhadap penugasan AKTIF paket inilah
     * yang membuat retry job idempoten — chunk yang sudah commit di-skip di run
     * berikutnya, bukan di-insert ulang (cacat ProcessBulkPenjadwalan LMS).
     *
     * ⚠ TIDAK membuka transaksi sendiri — PEMANGGIL yang memegangnya (hindari
     * kejutan nested-savepoint MSSQL). Semua pemanggil membungkusnya dengan
     * DB::transaction sendiri.
     *
     * @return array{assigned:int, skipped:int, targets:array}
     */
    public function assignKeHeader(
        int $penjadwalanId,
        int $idPaket,
        array $kodeList,
        ?int $userId,
        ?int $assignmentRuleId = null,
    ): array {
        // item wajib aktif + versi aktif (denormalisasi tipe untuk progress)
        $wajibItems = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Wajib', SopStatus::AKTIF)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->get(['Id_SOP_Paket_Item', 'Tipe_Item', 'Id_Item_Versi_Aktif']);

        // karyawan yang SUDAH ter-assign aktif ke paket ini → di-skip (anti dobel).
        // Kode unik di-chunk 500 agar diff tetap benar tanpa IN raksasa.
        $unik = array_values(array_unique($kodeList));
        $sudah = [];
        foreach (array_chunk($unik, 500) as $potong) {
            $sudah = array_merge($sudah, DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('pj.Id_SOP_Paket', $idPaket)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T')
                ->whereIn('d.Kode_Karyawan', $potong)
                ->pluck('d.Kode_Karyawan')->all());
        }

        $targets = array_values(array_diff($unik, $sudah));
        $skipped = count($unik) - count($targets);

        /*
         * BENTUK HIMPUNAN, BUKAN PERULANGAN.
         *
         * Bentuk lama mengirim satu insertGetId per karyawan ditambah satu insert
         * progress per karyawan: ±200 pernyataan untuk 100 orang, seluruhnya di
         * dalam SATU transaksi yang memegang kunci pada N_SOP_Penjadwalan_Detail
         * dan N_SOP_Progress selama itu. Basis data ini berjalan tanpa RCSI,
         * sehingga pembaca ikut mengantre di belakangnya.
         *
         * Id detail tak perlu dikembalikan: baris progress diturunkan dengan
         * menjoin balik lewat (Id_SOP_Penjadwalan, Kode_Karyawan). Bentuknya sama
         * dengan refactor cabut massal (122 → 8 kueri).
         */
        $saat = now();

        // 1. detail — disisipkan berkelompok. 8 kolom × 200 baris = 1.600 parameter,
        //    masih di bawah plafon 2.100 parameter per pernyataan SQL Server.
        $barisDetail = [];
        foreach ($targets as $kode) {
            $detailData = [
                'Id_SOP_Penjadwalan' => $penjadwalanId,
                'Kode_Karyawan' => $kode,
                'Status_Paket' => SopStatus::STATUS_BELUM,
                'Flag_Cabut_Akses' => 'T',
                'Created_By' => $userId,
                'Created_At' => $saat,
                'Flag_Aktif' => SopStatus::AKTIF,
            ];
            // Kolom hanya diisi bila aturan otomatis yang menugaskan; penugasan
            // MANUAL/MANDIRI tetap NULL supaya cabutMilikRule() tak menyentuhnya.
            if ($assignmentRuleId) {
                $detailData['Id_SOP_Assignment_Rule'] = $assignmentRuleId;
            }
            $barisDetail[] = $detailData;
        }

        foreach (array_chunk($barisDetail, 200) as $potong) {
            DB::table('N_SOP_Penjadwalan_Detail')->insert($potong);
        }

        /* 2. progress diturunkan lewat INSERT ... SELECT, bukan per orang.
         *
         * NOT EXISTS menjaga IDEMPOTENSI, yang menjadi syarat retry Cloud Tasks
         * (ProsesBulkAssignSop menjalankan ulang SELURUH chunk saat retry) —
         * bagian ini tidak boleh dihilangkan demi kesederhanaan. ISNULL(...,-1)
         * dipakai di kedua sisi karena Id_SOP_Item_Versi bernilai NULL untuk item
         * non-berkas, dan NULL = NULL tidak pernah benar di SQL.
         *
         * Saringan Flag_Aktif/Flag_Cabut_Akses pada d menjaga agar join balik
         * hanya menyentuh baris yang BARU disisipkan: karyawan yang aksesnya
         * pernah dicabut lolos diff di atas dan mendapat baris detail baru,
         * sementara baris lamanya masih ada di header yang sama.
         *
         * Potongan 400 kode: 4 parameter tetap + 400 kode = 404, jauh di bawah
         * plafon 2.100.
         */
        foreach (array_chunk($targets, 400) as $potong) {
            $ph = implode(',', array_fill(0, count($potong), '?'));
            DB::insert(
                "INSERT INTO N_SOP_Progress
                    (Id_SOP_Penjadwalan_Detail, Id_SOP_Paket_Item, Id_SOP_Item_Versi,
                     Kode_Karyawan, Tipe_Item, Status, Durasi_Aktif_Detik, Jumlah_Percobaan, Created_At)
                 SELECT d.Id_SOP_Penjadwalan_Detail, i.Id_SOP_Paket_Item, i.Id_Item_Versi_Aktif,
                        d.Kode_Karyawan, i.Tipe_Item, ?, 0, 0, ?
                   FROM N_SOP_Penjadwalan_Detail d
                   JOIN N_SOP_Paket_Item i
                     ON i.Id_SOP_Paket = ? AND i.Flag_Wajib = 'Y' AND i.Flag_Aktif = 'Y'
                  WHERE d.Id_SOP_Penjadwalan = ?
                    AND d.Flag_Aktif = 'Y'
                    AND d.Flag_Cabut_Akses = 'T'
                    AND d.Kode_Karyawan IN ({$ph})
                    AND NOT EXISTS (
                        SELECT 1 FROM N_SOP_Progress p
                         WHERE p.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail
                           AND p.Id_SOP_Paket_Item = i.Id_SOP_Paket_Item
                           AND ISNULL(p.Id_SOP_Item_Versi, -1) = ISNULL(i.Id_Item_Versi_Aktif, -1))",
                array_merge([SopStatus::ITEM_BELUM, $saat, $idPaket, $penjadwalanId], $potong)
            );
        }

        // rollup: Total_Target += jumlah target baru (hanya item ber-versi)
        if (count($targets) > 0) {
            foreach ($wajibItems as $it) {
                if (empty($it->Id_Item_Versi_Aktif)) {
                    continue;
                }
                $this->bumpRollup($idPaket, $it->Id_Item_Versi_Aktif, count($targets));
            }
        }

        return ['assigned' => count($targets), 'skipped' => $skipped, 'targets' => $targets];
    }

    /**
     * Ubah label/keterangan/tenggat/reminder sebuah batch. Header-level saja:
     * keterlambatan DITURUNKAN saat query, jadi seluruh detail otomatis ikut.
     *
     * Setiap bidang yang berubah dicatat terpisah ke N_SOP_Log dengan nilai
     * SEBELUM → SESUDAH — timeline yang cuma bilang "diubah" tak menjawab
     * pertanyaan yang sebenarnya diajukan admin ("tenggatnya dulu kapan?").
     *
     * @return array{ok:bool, message:string}
     */
    public function ubahDetail(int $penjadwalanId, array $opts, int $userId): array
    {
        $pj = DB::table('N_SOP_Penjadwalan')->where('Id_SOP_Penjadwalan', $penjadwalanId)->first();
        if (! $pj) {
            return ['ok' => false, 'message' => 'Penjadwalan tidak ditemukan.'];
        }

        $tipe = $opts['tipe_tenggat'] ?? 'SAMPAI_DICABUT';
        $deadline = $tipe === 'DEADLINE' ? ($opts['tanggal_deadline'] ?? null) : null;

        if ($tipe === 'DEADLINE') {
            if (! $deadline) {
                return ['ok' => false, 'message' => 'Tanggal tenggat wajib diisi.'];
            }
            if (\Illuminate\Support\Carbon::parse($deadline)->lt(\Illuminate\Support\Carbon::today())) {
                return ['ok' => false, 'message' => 'Tenggat tidak boleh di masa lalu.'];
            }
        }

        $namaBaru = $this->bersih($opts['nama_batch'] ?? null, 150);
        $ketBaru = $this->bersih($opts['keterangan'] ?? null, 500);

        DB::table('N_SOP_Penjadwalan')->where('Id_SOP_Penjadwalan', $penjadwalanId)->update([
            'Nama_Batch' => $namaBaru,
            'Keterangan' => $ketBaru,
            'Tipe_Tenggat' => $tipe,
            'Tanggal_Deadline' => $deadline, // NULL saat pindah ke SAMPAI_DICABUT
            'Flag_Reminder' => ($opts['flag_reminder'] ?? false) ? 'Y' : 'T',
            'Irama_Reminder_Hari' => $opts['irama_reminder_hari'] ?? null,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        $jejak = ['id_penjadwalan' => $penjadwalanId, 'oleh_id_users' => $userId];
        $tgl = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d M Y') : null;

        if ($namaBaru !== $pj->Nama_Batch) {
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'UBAH_LABEL',
                'Label batch: "'.($pj->Nama_Batch ?: 'tanpa label').'" → "'.($namaBaru ?: 'tanpa label').'".',
                (int) $pj->Id_SOP_Paket, null, null, null, $jejak,
            );
        }

        $tenggatBerubah = $tipe !== $pj->Tipe_Tenggat || $tgl($deadline) !== $tgl($pj->Tanggal_Deadline);
        if ($tenggatBerubah) {
            $lama = $pj->Tipe_Tenggat === 'DEADLINE' ? ('deadline '.$tgl($pj->Tanggal_Deadline)) : 'tanpa tenggat';
            $baru = $tipe === 'DEADLINE' ? ('deadline '.$tgl($deadline)) : 'tanpa tenggat';
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'UBAH_TENGGAT', "Tenggat: {$lama} → {$baru}.",
                (int) $pj->Id_SOP_Paket, null, null, null, $jejak,
            );
        }

        $remLama = $pj->Flag_Reminder === 'Y' ? "tiap {$pj->Irama_Reminder_Hari} hari" : 'nonaktif';
        $remBaru = ($opts['flag_reminder'] ?? false) ? "tiap {$opts['irama_reminder_hari']} hari" : 'nonaktif';
        if ($remLama !== $remBaru) {
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'UBAH_REMINDER', "Pengingat: {$remLama} → {$remBaru}.",
                (int) $pj->Id_SOP_Paket, null, null, null, $jejak,
            );
        }

        if ($ketBaru !== $pj->Keterangan) {
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'UBAH_KETERANGAN',
                'Keterangan: "'.($pj->Keterangan ?: 'tanpa keterangan').'" → "'.($ketBaru ?: 'tanpa keterangan').'".',
                (int) $pj->Id_SOP_Paket, null, null, null, $jejak,
            );
        }

        return ['ok' => true, 'message' => 'Penjadwalan diperbarui.'];
    }

    /** Rapikan input teks bebas: trim, kosong → NULL, potong ke batas kolom. */
    private function bersih(?string $v, int $maks): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : mb_substr($v, 0, $maks);
    }

    /**
     * Cabut akses banyak karyawan sekaligus dari SATU batch.
     * Memakai jalur per-detail yang sama dengan cabutAkses() — BUKAN UPDATE whereIn
     * polos, karena ledger DICABUT + mematikan token kuis adalah intinya.
     *
     * SELURUH kode diproses, berapa pun jumlahnya. Batas 500 tetap berlaku sebagai
     * pagar parameter SQL Server, tetapi kini menjadi ukuran CHUNK, bukan titik
     * potong: daftar dipecah lalu setiap potongan dikerjakan dalam transaksinya
     * sendiri. Sebelumnya array_slice memangkas diam-diam — admin memilih 900 orang
     * lewat "pilih semua lintas halaman", 500 tercabut, 400 sisanya tak tersentuh,
     * dan responsnya tetap terbaca sukses.
     *
     * Chunk yang gagal dicatat tanpa membatalkan chunk yang sudah commit, mengikuti
     * pola ProsesBulkAssignSop: pencabutan yang sudah sah tidak dianulir hanya
     * karena potongan berikutnya bermasalah.
     *
     * @return array{dicabut:int, diminta:int, gagal:array}
     */
    public function bulkCabut(
        int $penjadwalanId,
        array $kodes,
        int $userId,
        ?string $sebab = null,
        ?string $catatan = null,
    ): array {
        $diminta = count($kodes);
        $jejak = $this->jejakCabut($userId, $sebab, $catatan);

        // $subset null = tanpa penyaringan kode, yaitu "cabut seluruh batch".
        $baris = function (?array $subset) use ($penjadwalanId) {
            $q = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('d.Id_SOP_Penjadwalan', $penjadwalanId)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T');
            if ($subset !== null) {
                $q->whereIn('d.Kode_Karyawan', $subset);
            }

            return $q->get(['d.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan', 'pj.Id_SOP_Paket']);
        };

        // Daftar kosong berarti "cabut seluruh batch" — perilaku lama dipertahankan.
        $potongan = empty($kodes) ? [null] : array_chunk($kodes, self::MAKS_BULK_CABUT);

        $dicabut = 0;
        $gagal = [];
        $kodeTercabut = [];
        $idPaket = null;

        foreach ($potongan as $i => $subset) {
            $details = $baris($subset);
            if ($details->isEmpty()) {
                continue;
            }

            $idPaket ??= (int) $details->first()->Id_SOP_Paket;

            try {
                DB::transaction(function () use ($details, $userId, $jejak) {
                    $this->cabutBanyakDetail($details, $userId, null, $jejak);
                });
                $dicabut += $details->count();
                $kodeTercabut = array_merge($kodeTercabut, $details->pluck('Kode_Karyawan')->all());
            } catch (\Throwable $e) {
                Log::error('SOP penjadwalan bulkCabut gagal', ['potongan' => $i, 'exception' => $e]);
                $gagal[] = [
                    'potongan' => $i + 1,
                    'jumlah' => $details->count(),
                    'pesan' => mb_substr($e->getMessage(), 0, 200),
                ];
            }
        }

        if ($dicabut === 0 && empty($gagal)) {
            return ['dicabut' => 0, 'diminta' => $diminta, 'gagal' => []];
        }

        \App\Services\LMS\SOP\Support\SopLog::catat(
            'CABUT_AKSES',
            "Bulk cabut: {$dicabut} penugasan dicabut dari satu batch."
                .($gagal ? ' '.count($gagal).' potongan gagal diproses.' : ''),
            $idPaket, null, null, null,
            [
                'id_penjadwalan' => $penjadwalanId,
                'kode_karyawan' => $kodeTercabut,
                'diminta' => $diminta,
                'gagal' => $gagal,
                'oleh_id_users' => $userId,
            ],
        );

        return ['dicabut' => $dicabut, 'diminta' => $diminta, 'gagal' => $gagal];
    }

    /**
     * Cabut akses SOP untuk karyawan (offboarding / pindah divisi).
     * $idPaket null → cabut SEMUA penugasan aktif karyawan (offboarding penuh);
     * berisi → hanya paket tsb. Efek: Flag_Cabut_Akses='Y' (dikecualikan dari
     * seluruh perhitungan kepatuhan — tak lagi dianggap nunggak), token kuis
     * dimatikan, dan tulis ledger DICABUT. Kewajiban tak dihitung selesai —
     * memang dikecualikan. Idempoten: yang sudah dicabut dilewati.
     *
     * @return array ['dicabut'=>int]
     */
    public function cabutAkses(
        string $kode,
        ?int $idPaket,
        int $userId,
        ?string $sebab = null,
        ?string $catatan = null,
    ): array {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T');
        if ($idPaket) {
            $q->where('pj.Id_SOP_Paket', $idPaket);
        }

        $details = $q->get(['d.Id_SOP_Penjadwalan_Detail', 'pj.Id_SOP_Paket']);
        if ($details->isEmpty()) {
            return ['dicabut' => 0];
        }

        $jejak = $this->jejakCabut($userId, $sebab, $catatan);

        $count = 0;
        DB::transaction(function () use ($details, $kode, $userId, $jejak, &$count) {
            $this->cabutBanyakDetail($details, $userId, $kode, $jejak);
            $count = $details->count();
        });

        if ($count > 0) {
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'CABUT_AKSES',
                "Akses dokumen dicabut ({$count} penugasan; token kuis ikut dimatikan).",
                $idPaket, null, null, $kode,
                ['oleh_id_users' => $userId, 'sebab' => $jejak['Sebab'], 'catatan' => $jejak['Catatan']],
            );
        }

        return ['dicabut' => $count];
    }

    /**
     * Cabut akses untuk banyak karyawan sekaligus (bulk revocation).
     *
     * @param  array<string>  $kodeList
     * @return array ['dicabut' => int]
     */
    public function cabutAksesBanyak(
        array $kodeList,
        ?int $idPaket,
        int $userId,
        ?string $sebab = null,
        ?string $catatan = null,
    ): array {
        $kodeList = array_values(array_unique(array_filter(array_map('trim', $kodeList))));
        if (empty($kodeList)) {
            return ['dicabut' => 0];
        }

        $totalCount = 0;
        $jejak = $this->jejakCabut($userId, $sebab, $catatan);

        DB::transaction(function () use ($kodeList, $idPaket, $userId, $jejak, &$totalCount) {
            foreach (array_chunk($kodeList, 400) as $chunk) {
                $q = DB::table('N_SOP_Penjadwalan_Detail as d')
                    ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                    ->whereIn('d.Kode_Karyawan', $chunk)
                    ->where('d.Flag_Aktif', SopStatus::AKTIF)
                    ->where('d.Flag_Cabut_Akses', 'T');
                if ($idPaket) {
                    $q->where('pj.Id_SOP_Paket', $idPaket);
                }

                $details = $q->get(['d.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan', 'pj.Id_SOP_Paket']);
                if ($details->isNotEmpty()) {
                    $this->cabutBanyakDetail($details, $userId, null, $jejak);
                    $totalCount += $details->count();
                }
            }
        });

        if ($totalCount > 0) {
            \App\Services\LMS\SOP\Support\SopLog::catat(
                'CABUT_AKSES',
                "Akses dokumen dicabut secara massal ({$totalCount} penugasan untuk ".count($kodeList).' karyawan; token kuis ikut dimatikan).',
                $idPaket, null, null, null,
                ['oleh_id_users' => $userId, 'sebab' => $jejak['Sebab'], 'catatan' => $jejak['Catatan'], 'karyawan_count' => count($kodeList)],
            );
        }

        return ['dicabut' => $totalCount];
    }

    /**
     * Cabut hanya penugasan yang benar-benar dimiliki satu assignment rule.
     * Penugasan MANUAL/MANDIRI dan rule lain tidak dapat tersentuh oleh jalur ini.
     */
    public function cabutMilikRule(int $ruleId, int $idPaket, array $kodeList, ?int $userId): int
    {
        $kodeList = array_values(array_unique(array_filter(array_map('trim', $kodeList))));
        if (! $kodeList) {
            return 0;
        }

        $total = 0;
        foreach (array_chunk($kodeList, 400) as $chunk) {
            $details = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('d.Id_SOP_Assignment_Rule', $ruleId)
                ->where('pj.Id_SOP_Assignment_Rule', $ruleId)
                ->where('pj.Id_SOP_Paket', $idPaket)
                ->where('pj.Sumber', 'AUTO_RULE')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T')
                ->whereIn('d.Kode_Karyawan', $chunk)
                ->get(['d.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan', 'pj.Id_SOP_Paket']);

            // Sebab ditulis mesin: tak ada admin yang memilihnya, dan tanpa ini
            // pencabutan otomatis tak bisa dibedakan dari tindakan manual.
            $this->cabutBanyakDetail($details, $userId, null, [
                'Sebab' => 'ATURAN_OTOMATIS',
                'Catatan' => null,
                'Aktor_Id' => $userId,
                'Aktor_Nama' => null,
            ]);
            $total += $details->count();
        }

        return $total;
    }

    /**
     * Cabut satu detail: flag + matikan token kuis + ledger DICABUT.
     * Dipakai cabutAkses() (per orang lintas paket) dan bulkCabut() (per batch).
     * Pemanggil yang memegang transaksi.
     */
    private function cabutSatuDetail(object $d, string $kode, ?int $userId, array $jejak = []): void
    {
        $this->cabutBanyakDetail([$d], $userId, $kode, $jejak);
    }

    /**
     * Rakit jejak audit pencabutan: sebab, catatan, dan identitas pelaku.
     *
     * Nama pelaku DISALIN, bukan di-join saat dibaca. Akun bisa berganti nama
     * atau dinonaktifkan, dan catatan audit yang ikut berubah setiap kali master
     * user disunting bukan catatan audit.
     *
     * Sebab di luar daftar manual dibuang, bukan disimpan apa adanya: kolom
     * `Sebab` tak dijaga CHECK constraint, jadi gerbangnya harus di sini —
     * kalau tidak, nilai ngawur dari pemanggil mana pun akan mengendap di ledger.
     */
    private function jejakCabut(?int $userId, ?string $sebab, ?string $catatan): array
    {
        $catatan = is_string($catatan) ? trim($catatan) : null;

        return [
            'Sebab' => SopSebabCabut::sahManual($sebab) ? $sebab : null,
            'Catatan' => $catatan !== '' ? mb_substr((string) $catatan, 0, 500) : null,
            'Aktor_Id' => $userId,
            'Aktor_Nama' => $userId ? $this->namaPelaku($userId) : null,
        ];
    }

    /** Nama pelaku dari KPI_Users; null bila akunnya sudah tak ada. */
    private function namaPelaku(int $userId): ?string
    {
        $nama = DB::table('KPI_Users')->where('Id_Users', $userId)->value('Nama');

        return $nama ? mb_substr((string) $nama, 0, 100) : null;
    }

    /**
     * Cabut SEKUMPULAN detail sekaligus — berbasis himpunan, bukan per baris.
     *
     * KENAPA BUKAN PERULANGAN. Bentuk lama mengirim 6 kueri per orang: dua
     * update, satu insert, satu select progres, dan satu update rollup per baris
     * progres. Terukur 122 kueri untuk 20 orang. Yang mahal bukan kuerinya —
     * seluruhnya sepele — melainkan jumlah BOLAK-BALIKNYA: satu `SELECT 1` pun
     * berbiaya 72 ms ke basis data dev, sehingga 49 orang memakan 15,9 detik
     * DI DALAM SATU TRANSAKSI yang memegang kunci selama itu.
     *
     * Potongan bulkCabut berukuran 500. Dengan bentuk lama itu berarti ±3.050
     * bolak-balik berurutan dalam satu transaksi. Latensi yang lebih rendah di
     * produksi memperkecil angkanya, tapi tidak mengubah bentuknya.
     *
     * Sekarang: 4 kueri tetap + satu update rollup per (paket, versi) — dan
     * jumlah versi per paket itu satuan, bukan puluhan.
     *
     * $kodeTetap dipakai cabutAkses(), yang mencabut SATU orang lintas paket
     * sehingga kodenya tidak ada di barisnya.
     *
     * @param  iterable<object>  $details  butuh Id_SOP_Penjadwalan_Detail, Id_SOP_Paket, dan Kode_Karyawan bila $kodeTetap null
     */
    private function cabutBanyakDetail(
        iterable $details,
        ?int $userId,
        ?string $kodeTetap = null,
        array $jejak = [],
    ): void {
        $baris = collect($details)->values();
        if ($baris->isEmpty()) {
            return;
        }

        $idDetail = $baris->pluck('Id_SOP_Penjadwalan_Detail')->map(fn ($v) => (int) $v)->all();
        $saat = now();

        DB::table('N_SOP_Penjadwalan_Detail')
            ->whereIn('Id_SOP_Penjadwalan_Detail', $idDetail)
            ->update(['Flag_Cabut_Akses' => 'Y', 'Updated_By' => $userId, 'Updated_At' => $saat]);

        // matikan token kuis milik detail ini (baris integrasi tetap utk audit)
        DB::table('N_SOP_Penjadwalan_Integrasi')
            ->whereIn('Id_SOP_Penjadwalan_Detail', $idDetail)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_At' => $saat]);

        /* Ledger DICABUT, disisipkan berkelompok.
           Dipotong 200 baris: SQL Server membatasi 2.100 parameter per
           pernyataan, dan sembilan kolom × 200 baris masih jauh di bawahnya. */
        foreach ($baris->chunk(200) as $potongan) {
            DB::table('N_SOP_Kepatuhan_Riwayat')->insert(
                $potongan->map(fn ($d) => [
                    'Id_SOP_Penjadwalan_Detail' => $d->Id_SOP_Penjadwalan_Detail,
                    'Id_SOP_Paket' => $d->Id_SOP_Paket,
                    'Kode_Karyawan' => $kodeTetap ?? $d->Kode_Karyawan,
                    'Peristiwa' => SopStatus::RWT_DICABUT,
                    // `Alasan` tetap null: kelima nilai yang diizinkan
                    // CK_N_SOP_Rwt_Alasan seluruhnya menjelaskan pembukaan
                    // kembali, bukan pencabutan. Sebab pencabutan ditulis ke
                    // kolom `Sebab` yang memang dibuat untuk itu.
                    'Alasan' => null,
                    'Sebab' => $jejak['Sebab'] ?? null,
                    'Catatan' => $jejak['Catatan'] ?? null,
                    'Aktor_Id' => $jejak['Aktor_Id'] ?? $userId,
                    'Aktor_Nama' => $jejak['Aktor_Nama'] ?? null,
                    'Snapshot_JSON' => null,
                    'Persen_Saat_Itu' => null,
                    'Waktu_Peristiwa' => $saat,
                    'Created_At' => $saat,
                ])->all()
            );
        }

        $this->turunkanRollupBanyak($idDetail, $baris, $saat);
    }

    /**
     * Kurangi rollup item-level saat akses seorang karyawan dicabut.
     *
     * assignKeHeader() menaikkan Total_Target untuk setiap item wajib ber-versi,
     * tapi pencabutan tak pernah menurunkannya. Padahal seluruh angka paket-level
     * (coveragePaket, summary, People, List) konsisten mengecualikan
     * Flag_Cabut_Akses='Y', sehingga angka item-level dan paket-level di layar
     * yang sama permanen berbeda. Diperparah karena orang yang pernah dicabut
     * dianggap "belum ter-assign" saat di-assign ulang, menaikkan target lagi.
     *
     * Jumlah_Dikonfirmasi ikut diturunkan bila orang itu memang sudah
     * mengonfirmasi versi tersebut — kalau tidak, persen bisa melebihi 100.
     */
    /**
     * Turunkan rollup untuk SEKUMPULAN detail sekaligus.
     *
     * Dikelompokkan per (paket, versi item) lalu diturunkan sebanyak JUMLAHNYA
     * dalam satu update — bukan satu update per baris progres. Pengelompokan
     * harus memuat paketnya juga: cabutAkses() mencabut satu orang LINTAS PAKET,
     * dan versi dokumen yang sama bisa disajikan beberapa paket sekaligus.
     *
     * Pengurangannya dijepit di nol lewat CASE, meniru penjaga `Total_Target > 0`
     * pada bentuk lama: rollup yang jatuh negatif membuat persen kepatuhan
     * menjadi angka yang tak berarti dan tak pernah pulih sendiri.
     *
     * @param  list<int>  $idDetail
     */
    private function turunkanRollupBanyak(array $idDetail, \Illuminate\Support\Collection $baris, $saat): void
    {
        $paketPerDetail = $baris->mapWithKeys(
            fn ($d) => [(int) $d->Id_SOP_Penjadwalan_Detail => (int) $d->Id_SOP_Paket]
        );

        $selesai = [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI];

        $progress = DB::table('N_SOP_Progress')
            ->whereIn('Id_SOP_Penjadwalan_Detail', $idDetail)
            ->whereNotNull('Id_SOP_Item_Versi')
            ->get(['Id_SOP_Penjadwalan_Detail', 'Id_SOP_Item_Versi', 'Status']);

        $agregat = [];
        foreach ($progress as $p) {
            $paket = $paketPerDetail[(int) $p->Id_SOP_Penjadwalan_Detail] ?? null;
            if (! $paket) {
                continue;
            }

            $kunci = $paket.':'.$p->Id_SOP_Item_Versi;
            $agregat[$kunci] ??= ['paket' => $paket, 'versi' => $p->Id_SOP_Item_Versi, 'target' => 0, 'konfirmasi' => 0];
            $agregat[$kunci]['target']++;
            if (in_array($p->Status, $selesai, true)) {
                $agregat[$kunci]['konfirmasi']++;
            }
        }

        foreach ($agregat as $a) {
            $n = (int) $a['target'];
            $k = (int) $a['konfirmasi'];

            DB::table('N_SOP_Coverage_Rollup')
                ->where('Id_SOP_Paket', $a['paket'])
                ->where('Id_SOP_Item_Versi', $a['versi'])
                ->where('Total_Target', '>', 0)
                ->update([
                    'Total_Target' => DB::raw("CASE WHEN Total_Target > {$n} THEN Total_Target - {$n} ELSE 0 END"),
                    'Jumlah_Dikonfirmasi' => DB::raw("CASE WHEN Jumlah_Dikonfirmasi > {$k} THEN Jumlah_Dikonfirmasi - {$k} ELSE 0 END"),
                    'Persen_Dikonfirmasi' => DB::raw(
                        "CASE WHEN (CASE WHEN Total_Target > {$n} THEN Total_Target - {$n} ELSE 0 END) > 0 "
                        ."THEN CAST(CASE WHEN Jumlah_Dikonfirmasi > {$k} THEN Jumlah_Dikonfirmasi - {$k} ELSE 0 END AS DECIMAL(9,2)) "
                        ."/ (CASE WHEN Total_Target > {$n} THEN Total_Target - {$n} ELSE 0 END) * 100 ELSE 0 END"
                    ),
                    'Updated_At' => $saat,
                ]);
        }
    }

    private function turunkanRollupCabut(int $detailId, int $idPaket): void
    {
        $progress = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $detailId)
            ->whereNotNull('Id_SOP_Item_Versi')
            ->get(['Id_SOP_Item_Versi', 'Status']);

        $selesai = [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI];

        foreach ($progress as $p) {
            $sudahKonfirmasi = in_array($p->Status, $selesai, true);

            DB::table('N_SOP_Coverage_Rollup')
                ->where('Id_SOP_Paket', $idPaket)
                ->where('Id_SOP_Item_Versi', $p->Id_SOP_Item_Versi)
                ->where('Total_Target', '>', 0)
                ->update([
                    'Total_Target' => DB::raw('Total_Target - 1'),
                    'Jumlah_Dikonfirmasi' => $sudahKonfirmasi
                        ? DB::raw('CASE WHEN Jumlah_Dikonfirmasi > 0 THEN Jumlah_Dikonfirmasi - 1 ELSE 0 END')
                        : DB::raw('Jumlah_Dikonfirmasi'),
                    'Persen_Dikonfirmasi' => DB::raw(
                        'CASE WHEN (Total_Target - 1) > 0 THEN CAST('
                        .($sudahKonfirmasi
                            ? 'CASE WHEN Jumlah_Dikonfirmasi > 0 THEN Jumlah_Dikonfirmasi - 1 ELSE 0 END'
                            : 'Jumlah_Dikonfirmasi')
                        .' AS DECIMAL(9,2)) / (Total_Target - 1) * 100 ELSE 0 END'
                    ),
                    'Updated_At' => now(),
                ]);
        }
    }

    /**
     * Cek identitas HC Learn admin utk paket ber-KUIS.
     * Dipindah dari controller (dulu privat tolakBilaIdentitasKuisTakSah) supaya
     * bisa dipakai dua arah: assign() tetap MEMBLOK, /preflight memakainya advisori.
     *
     * @return array{status: 'OK'|'DITOLAK'|'TIDAK_TERTAUT'|'TANPA_KUIS', pesan: ?string}
     */
    public function cekIdentitasKuis(int $idPaket, $user): array
    {
        $adaKuis = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Tipe_Item', 'KUIS')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('v.Id_Master_Ujian')
            ->exists();
        if (! $adaKuis) {
            return ['status' => 'TANPA_KUIS', 'pesan' => null];
        }

        $appId = $user->User_Key ?? null;
        $kode = $user->Kode_Users ?? null;
        if (empty($appId) || empty($kode)) {
            return [
                'status' => 'TIDAK_TERTAUT',
                'pesan' => 'Paket ini memuat kuis, tetapi akun Anda belum tertaut integrasi HC Learn. '
                    .'Minta admin yang terintegrasi untuk menjadwalkannya.',
            ];
        }

        /*
         * Preflight NYATA ke HC Learn, bukan sekadar cek kolom terisi: bukti kasus
         * H45 vs A4 — User_Key sama persis, satu ditolak 401 satu diterima, karena
         * HC Learn memvalidasi pasangan X-App-ID + X-Kode-Karyawan.
         * Cache 300 dtk per pasangan; gangguan jaringan ≠ identitas salah → loloskan.
         */
        $ok = \Illuminate\Support\Facades\Cache::store('database')->remember(
            'sop_hclearn_ident_'.md5($appId.'|'.$kode),
            300,
            function () use ($appId, $kode) {
                try {
                    $endpoint = 'api/v1/penjadwalan/training/options';
                    $auth = \App\Helpers\SignatureHelper::get($endpoint, $appId, $kode);
                    $url = rtrim((string) config('services.frans.hclearn_api.domain'), '/').'/'.$endpoint;

                    return \Illuminate\Support\Facades\Http::withHeaders($auth['headers'])->timeout(15)->get($url)->successful();
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('SOP preflight HC Learn gagal: '.$e->getMessage());

                    return true; // jaringan bermasalah ≠ identitas salah
                }
            },
        );

        return $ok
            ? ['status' => 'OK', 'pesan' => null]
            : [
                'status' => 'DITOLAK',
                'pesan' => 'Paket ini memuat kuis, tetapi identitas HC Learn Anda ditolak. '
                    .'Penjadwalan dibatalkan agar karyawan tidak buntu saat memulai ujian.',
            ];
    }

    /** Increment atomik Total_Target rollup (upsert). */
    private function bumpRollup(int $idPaket, int $idVersi, int $delta): void
    {
        // Baris baru langsung menanam delta-nya, jadi tak perlu UPDATE lagi. Bila
        // balapan dimenangkan pihak lain, pastikan() mengembalikan false dan
        // alirannya jatuh ke UPDATE — delta ini tetap terhitung, tidak hilang.
        if (! SopRollupBaris::pastikan($idPaket, $idVersi, max(0, $delta))) {
            DB::table('N_SOP_Coverage_Rollup')
                ->where('Id_SOP_Paket', $idPaket)
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->update([
                    'Total_Target' => DB::raw("Total_Target + {$delta}"),
                    /*
                     * Persen WAJIB dihitung ulang bersama penyebutnya. Tiga penulis
                     * rollup lain (bumpTarget, unbumpConfirmed, bumpConfirmed) sudah
                     * melakukannya; hanya jalur assign yang tidak, sehingga persen
                     * tertinggal pada nilai lama yang lebih tinggi sampai ada
                     * konfirmasi berikutnya — dan nilai basi itu dikirim mentah ke UI
                     * oleh SopMonitoringService::detailPaket().
                     *
                     * Total_Target dijaga tak pernah negatif: delta bisa bernilai
                     * minus saat penugasan dicabut.
                     */
                    'Persen_Dikonfirmasi' => DB::raw(
                        "CASE WHEN (Total_Target + {$delta}) > 0
                              THEN CAST(Jumlah_Dikonfirmasi AS DECIMAL(9,2)) / (Total_Target + {$delta}) * 100
                              ELSE 0 END"
                    ),
                    'Updated_At' => now(),
                ]);
        }
    }

    /**
     * Kirim pengingat manual untuk seluruh karyawan dalam satu batch yang belum tuntas.
     */
    public function ingatkanBatch(int $penjadwalanId, ?string $pesanCustom = null, ?int $userId = null): array
    {
        $pj = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('pj.Id_SOP_Penjadwalan', $penjadwalanId)
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->first([
                'pj.Id_SOP_Penjadwalan',
                'pj.Id_SOP_Paket',
                'pj.Nama_Batch',
                'pj.Tanggal_Deadline',
                'p.Nama_Paket',
            ]);

        if (! $pj) {
            return ['sukses' => false, 'pesan' => 'Penjadwalan tidak ditemukan'];
        }

        $details = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->where('d.Id_SOP_Penjadwalan', $penjadwalanId)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->get(['d.Id_SOP_Penjadwalan_Detail', 'd.Kode_Karyawan']);

        if ($details->isEmpty()) {
            return [
                'sukses' => true,
                'total' => 0,
                'sent' => 0,
                'pesan' => 'Seluruh karyawan dalam batch ini sudah menyelesaikan penugasan.',
            ];
        }

        $kodes = $details->pluck('Kode_Karyawan')->all();
        $detailIds = $details->pluck('Id_SOP_Penjadwalan_Detail')->all();

        $today = \Illuminate\Support\Carbon::today();
        $deadline = $pj->Tanggal_Deadline ? \Illuminate\Support\Carbon::parse($pj->Tanggal_Deadline)->startOfDay() : null;
        $overdue = $deadline ? $deadline->lt($today) : false;

        $items = [];
        foreach ($kodes as $kd) {
            $items[] = [
                'kode' => $kd,
                'id_paket' => $pj->Id_SOP_Paket,
                'nama' => $pj->Nama_Paket,
                'tenggat' => $deadline ? $deadline->format('d M Y') : 'Tanpa Tenggat',
                'overdue' => $overdue,
            ];
        }

        $sent = app(SopNotificationService::class)->notifReminder($items);

        // Update timestamp & counter
        foreach (array_chunk($detailIds, 400) as $chunk) {
            DB::table('N_SOP_Penjadwalan_Detail')
                ->whereIn('Id_SOP_Penjadwalan_Detail', $chunk)
                ->update([
                    'Terakhir_Diingatkan_At' => now(),
                    'Jumlah_Reminder' => DB::raw('Jumlah_Reminder + 1'),
                    'Updated_At' => now(),
                ]);
        }

        // Catat di N_SOP_Log
        \App\Services\LMS\SOP\Support\SopLog::catat(
            'INGATKAN',
            "Pengingat manual dikirim ke {$sent} karyawan untuk batch '".($pj->Nama_Batch ?: $pj->Nama_Paket)."'.",
            $pj->Id_SOP_Paket,
            null,
            $penjadwalanId,
            null,
            [
                'total_karyawan' => count($kodes),
                'pesan_custom' => $pesanCustom,
                'kode_karyawan' => array_slice($kodes, 0, 50),
            ]
        );

        return [
            'sukses' => true,
            'total' => count($kodes),
            'sent' => $sent,
            'nama_batch' => $pj->Nama_Batch ?: $pj->Nama_Paket,
        ];
    }
}
