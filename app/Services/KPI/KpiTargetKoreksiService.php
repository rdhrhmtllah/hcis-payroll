<?php

namespace App\Services\KPI;

use Illuminate\Support\Facades\DB;

/**
 * Pemilik TUNGGAL aturan koreksi nilai target KPI.
 *
 * Baik pemeriksa kelayakan (yang mematikan sel di layar) maupun jalur simpan
 * memanggil fungsi yang sama. Aturan ini tidak boleh disalin ke controller
 * atau ke frontend: cacat yang paling sering berulang di proyek ini adalah
 * aturan yang terpasang di sebagian pintu saja, lalu kedua pintu berselisih
 * diam-diam.
 *
 * Granularitasnya per `Id_Pengajuan_Det` — satu parameter pada satu periode.
 * Bukan per orang dan bukan per bulan: seseorang bisa punya Parameter A bulan
 * Mei yang sudah dinilai sementara Parameter B bulan Mei belum.
 */
class KpiTargetKoreksiService
{
    /**
     * @param  array<int>  $idPengajuanDet
     * @return array<int, array{boleh: bool, kode: string, keterangan: string, pemegang_draf: ?string}>
     */
    public function periksaKelayakan(array $idPengajuanDet): array
    {
        $ids = array_values(array_unique(array_map('intval', $idPengajuanDet)));

        if ($ids === []) {
            return [];
        }

        $target = DB::table('KPI_Pengajuan_Det as det')
            ->join('KPI_Pengajuan_Detail as pd', 'pd.Id_Pengajuan_Detail', '=', 'det.Id_Pengajuan_Detail')
            ->join('KPI_Pengajuan as p', 'p.Id_Pengajuan', '=', 'pd.Id_Pengajuan')
            ->whereIn('det.Id_Pengajuan_Det', $ids)
            ->get([
                'det.Id_Pengajuan_Det',
                'p.Flag_Aktif',
                'p.Flag_Approval',
                'p.Status',
            ])
            ->keyBy('Id_Pengajuan_Det');

        // Satu kueri per jenis penghalang, bukan satu per target: layar
        // enforcement memeriksa ratusan sel sekaligus, dan biaya per-baris
        // terhadap database yang jauh tumbuh mengikuti jumlah selnya.
        //
        // Baris detail TIDAK pernah dihapus saat pengajuannya dibatalkan,
        // ditolak, atau digantikan — hanya header-nya yang berganti flag. Jadi
        // yang mengunci hanya header yang masih berlaku: hidup (Flag_Aktif='Y',
        // Status NULL) dan disetujui atau masih menunggu. Yang ditolak tidak
        // mengunci: kiriman ulangnya dihitung terhadap Nilai_Target terbaru.
        $final = DB::table('KPI_Update_Performance_Detail as upd')
            ->join('KPI_Update_Performance as up', 'up.Id_Update_Performance', '=', 'upd.Id_Update_Performance')
            ->whereIn('upd.Id_Pengajuan_Det', $ids)
            ->where('up.Flag_Aktif', 'Y')
            ->whereNull('up.Status')
            ->where(fn ($q) => $q->whereNull('up.Flag_Approval')->orWhere('up.Flag_Approval', 'Y'))
            ->pluck('upd.Id_Pengajuan_Det')
            ->flip();

        // Draf yang sudah dipindah jadi pengajuan (Flag_Pindah='Y') atau
        // dibatalkan (header Flag_Aktif='T') tetap meninggalkan barisnya di
        // sini; keduanya bukan draf yang sedang diisi siapa pun.
        $draf = DB::table('KPI_Update_Performance_Detail_Sementara as s')
            ->join('KPI_Update_Performance_Sementara as hs', 'hs.Id_Update_Performance_Sementara', '=', 's.Id_Update_Performance_Sementara')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 's.Kode_Karyawan')
            ->whereIn('s.Id_Pengajuan_Det', $ids)
            ->where('hs.Flag_Aktif', 'Y')
            ->where(fn ($q) => $q->whereNull('hs.Flag_Pindah')->orWhere('hs.Flag_Pindah', '<>', 'Y'))
            ->pluck('k.Nama', 's.Id_Pengajuan_Det');

        $hasil = [];

        foreach ($ids as $id) {
            $hasil[$id] = $this->nilaiSatu($id, $target->get($id), isset($final[$id]), $draf);
        }

        return $hasil;
    }

    /**
     * Urutan pemeriksaan disengaja: penghalang yang paling tak bisa ditawar
     * dilaporkan lebih dulu, supaya keterangan yang sampai ke admin adalah
     * alasan yang paling menentukan, bukan yang kebetulan diperiksa pertama.
     */
    private function nilaiSatu(int $id, $baris, bool $adaFinal, $draf): array
    {
        if (! $baris) {
            return $this->tolak('TIDAK_DITEMUKAN', 'Target tidak ditemukan.');
        }

        if ($adaFinal) {
            return $this->tolak('SUDAH_FINAL', 'Sudah dinilai; targetnya tidak bisa diubah lagi.');
        }

        if ($draf->has($id)) {
            $nama = $draf->get($id);

            return $this->tolak(
                'ADA_DRAF',
                'Sedang diisi'.($nama ? ' oleh '.$nama : '').'; minta drafnya dikirim atau dibatalkan dulu.',
                $nama,
            );
        }

        $hidup = ($baris->Flag_Aktif ?? null) === 'Y'
            && ($baris->Flag_Approval ?? null) === 'Y'
            && ($baris->Status ?? null) === null;

        if (! $hidup) {
            return $this->tolak('PENGAJUAN_TIDAK_HIDUP', 'Pengajuan parameternya tidak aktif atau belum disetujui.');
        }

        return ['boleh' => true, 'kode' => 'BOLEH', 'keterangan' => '', 'pemegang_draf' => null];
    }

    private function tolak(string $kode, string $keterangan, ?string $pemegang = null): array
    {
        return ['boleh' => false, 'kode' => $kode, 'keterangan' => $keterangan, 'pemegang_draf' => $pemegang];
    }

    /**
     * Terapkan koreksi.
     *
     * GAGAL-TERTUTUP: bila ada satu saja yang ditolak, TIDAK ADA yang
     * diterapkan — kecuali pemanggil meminta $bolehSebagian secara eksplisit.
     * Hasil separuh harus jadi keputusan sadar, bukan efek samping. Pola ini
     * mengikuti kpi:rapikan-target-sebelum-mulai, yang melapor dulu dan
     * mengubah data hanya bila --terapkan diberikan.
     *
     * @param  array<array{id_pengajuan_det: int, nilai_baru: float}>  $perubahan
     * @return array{
     *     diterapkan: array<array{id_pengajuan_det: int, nilai_lama: float, nilai_baru: float}>,
     *     ditolak: array<array{id_pengajuan_det: int, kode: string, keterangan: string, pemegang_draf: ?string}>,
     *     no_batch: ?string
     * }
     */
    public function terapkan(
        array $perubahan,
        string $sebab,
        int $idUserAktor,
        bool $bolehSebagian = false,
    ): array {
        $sebab = trim($sebab);

        if ($sebab === '') {
            throw new \InvalidArgumentException('Sebab koreksi wajib diisi.');
        }

        $kelayakan = $this->periksaKelayakan(array_column($perubahan, 'id_pengajuan_det'));

        $lolos = [];
        $ditolak = [];

        foreach ($perubahan as $p) {
            $id = (int) $p['id_pengajuan_det'];
            $status = $kelayakan[$id] ?? $this->tolak('TIDAK_DITEMUKAN', 'Target tidak ditemukan.');

            if ($status['boleh']) {
                $lolos[$id] = (float) $p['nilai_baru'];

                continue;
            }

            $ditolak[] = [
                'id_pengajuan_det' => $id,
                'kode' => $status['kode'],
                'keterangan' => $status['keterangan'],
                'pemegang_draf' => $status['pemegang_draf'],
            ];
        }

        if (($ditolak !== [] && ! $bolehSebagian) || $lolos === []) {
            return ['diterapkan' => [], 'ditolak' => $ditolak, 'no_batch' => null];
        }

        $noBatch = 'KT'.now()->format('ymdHis').strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $diterapkan = [];

        DB::transaction(function () use (&$lolos, $sebab, $idUserAktor, $noBatch, &$diterapkan, &$ditolak) {
            // Nilai lama dibaca DI DALAM transaksi dan dikunci: jejak harus
            // merekam angka yang benar-benar tergantikan, bukan angka yang
            // terbaca sebelum barisnya dikunci.
            $lama = DB::table('KPI_Pengajuan_Det')
                ->whereIn('Id_Pengajuan_Det', array_keys($lolos))
                ->lockForUpdate()
                ->pluck('Nilai_Target', 'Id_Pengajuan_Det');

            // Kelayakan DIPERIKSA ULANG setelah barisnya terkunci.
            //
            // Pemeriksaan pertama terjadi di luar transaksi, dan kunci di sini
            // hanya memegang KPI_Pengajuan_Det — bukan tabel draf maupun final.
            // Tanpa pemeriksaan kedua, seseorang yang mengirim draf tepat di
            // antara dua saat itu akan mendapat target berubah di bawahnya, dan
            // draf basi itulah yang justru jadi alasan gerbang ini ada.
            $ulang = $this->periksaKelayakan(array_keys($lolos));

            foreach ($ulang as $id => $status) {
                if ($status['boleh']) {
                    continue;
                }

                unset($lolos[$id]);
                $ditolak[] = [
                    'id_pengajuan_det' => $id,
                    'kode' => $status['kode'],
                    'keterangan' => $status['keterangan'],
                    'pemegang_draf' => $status['pemegang_draf'],
                ];
            }

            $jejak = [];

            foreach ($lolos as $id => $nilaiBaru) {
                $nilaiLama = (float) ($lama[$id] ?? 0);

                DB::table('KPI_Pengajuan_Det')
                    ->where('Id_Pengajuan_Det', $id)
                    ->update(['Nilai_Target' => $nilaiBaru]);

                $jejak[] = [
                    'No_Batch' => $noBatch,
                    'Id_Pengajuan_Det' => $id,
                    'Nilai_Target_Lama' => $nilaiLama,
                    'Nilai_Target_Baru' => $nilaiBaru,
                    'Sebab' => $sebab,
                    'Id_User_Aktor' => $idUserAktor,
                    'Waktu' => now(),
                ];

                $diterapkan[] = [
                    'id_pengajuan_det' => $id,
                    'nilai_lama' => $nilaiLama,
                    'nilai_baru' => $nilaiBaru,
                ];
            }

            if ($jejak !== []) {
                DB::table('N_KPI_Target_Koreksi')->insert($jejak);
            }
        });

        // Pemeriksaan ulang di dalam transaksi bisa menggugurkan SEMUANYA.
        // Memulangkan no_batch untuk batch yang tak menulis apa pun membuat
        // pemanggil menyimpan nomor yang tidak menunjuk baris jejak mana pun.
        return [
            'diterapkan' => $diterapkan,
            'ditolak' => $ditolak,
            'no_batch' => $diterapkan === [] ? null : $noBatch,
        ];
    }

    /**
     * Peringatkan bila nilai BARU justru menyimpang jauh dari saudara
     * sebarisnya, supaya koreksi tidak melahirkan salah ketik berikutnya.
     *
     * Aturannya sama persis dengan kpi:cek-target-menyimpang: median seluruh
     * periode pada parameter yang sama, menyimpang bila rasionya melewati
     * $faktor ke atas atau ke bawah.
     *
     * MEMPERINGATKAN, tidak memblokir. Nilai yang benar hanya diketahui pemilik
     * prosesnya — prinsip yang sama yang membuat perintah itu tidak pernah
     * memperbaiki apa pun sendiri.
     *
     * @return array{median: float, rasio: float}|null null bila wajar
     */
    public function periksaMenyimpang(int $idPengajuanDet, float $nilaiBaru, float $faktor = 10.0): ?array
    {
        $hasil = $this->periksaMenyimpangBanyak(
            [['id_pengajuan_det' => $idPengajuanDet, 'nilai_baru' => $nilaiBaru]],
            $faktor,
        );

        return $hasil[$idPengajuanDet] ?? null;
    }

    /**
     * Versi banyak-sekaligus, dan SATU-SATUNYA tempat aturannya tinggal.
     *
     * Biayanya TETAP: dua kueri berapa pun jumlah targetnya. Bentuk per-target
     * membuat satu simpan 500 perubahan mengeluarkan 1.000 kueri, dan terhadap
     * database yang jaraknya ~100 ms itu bukan error melainkan halaman yang
     * menggantung — jenis kerusakan yang tidak pernah melapor.
     *
     * @param  array<array{id_pengajuan_det: int, nilai_baru: float}>  $perubahan
     * @return array<int, array{median: float, rasio: float}> hanya yang menyimpang
     */
    public function periksaMenyimpangBanyak(array $perubahan, float $faktor = 10.0): array
    {
        $faktor = max(2.0, $faktor);

        $nilaiBaruPerId = [];
        foreach ($perubahan as $p) {
            $nilaiBaruPerId[(int) $p['id_pengajuan_det']] = (float) $p['nilai_baru'];
        }

        if ($nilaiBaruPerId === []) {
            return [];
        }

        $ids = array_keys($nilaiBaruPerId);

        $detailPerId = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Det', $ids)
            ->pluck('Id_Pengajuan_Detail', 'Id_Pengajuan_Det');

        if ($detailPerId->isEmpty()) {
            return [];
        }

        $saudara = DB::table('KPI_Pengajuan_Det')
            ->whereIn('Id_Pengajuan_Detail', $detailPerId->values()->unique()->all())
            ->get(['Id_Pengajuan_Det', 'Id_Pengajuan_Detail', 'Nilai_Target'])
            ->groupBy('Id_Pengajuan_Detail');

        $hasil = [];

        foreach ($nilaiBaruPerId as $id => $nilaiBaru) {
            $idDetail = $detailPerId->get($id);

            if (! $idDetail) {
                continue;
            }

            // Baris yang sedang dikoreksi DIKELUARKAN dari mediannya: kalau ia
            // ikut, nilai salah ketik yang hendak diperbaiki justru mengangkat
            // mediannya sendiri dan menyamarkan penyimpangannya.
            $nilai = $saudara->get($idDetail, collect())
                ->reject(fn ($r) => (int) $r->Id_Pengajuan_Det === $id)
                ->map(fn ($r) => (float) $r->Nilai_Target)
                ->all();

            $median = $this->median(array_values($nilai));

            if ($median <= 0 || $nilaiBaru <= 0) {
                continue; // median nol -> rasio tidak bermakna
            }

            $rasio = $nilaiBaru / $median;

            if ($rasio > $faktor || $rasio < 1 / $faktor) {
                $hasil[$id] = ['median' => $median, 'rasio' => $rasio];
            }
        }

        return $hasil;
    }

    private function median(array $nilai): float
    {
        sort($nilai);
        $n = count($nilai);

        if ($n === 0) {
            return 0.0;
        }

        $tengah = intdiv($n, 2);

        return $n % 2 === 1
            ? (float) $nilai[$tengah]
            : ((float) $nilai[$tengah - 1] + (float) $nilai[$tengah]) / 2;
    }
}
