<?php

namespace App\Services\SYSTEM\Audit\Activity;

/**
 * Merekam SETIAP kueri yang dijalankan aplikasi, beserta rute yang menjalankannya.
 *
 * Inilah potongan yang tak bisa diberikan sisi basis data. dm_exec_query_stats
 * tahu sebuah kueri mahal dan sering, tetapi sama sekali tidak tahu endpoint
 * mana yang menjalankannya — sedangkan yang ingin diperbaiki orang adalah
 * FITURNYA, bukan kalimat SQL-nya. Atribusi itu hanya ada di sisi aplikasi.
 *
 * KENAPA BERKAS, BUKAN TABEL
 *
 * Tiga alasan, dan ketiganya praktis:
 *
 *   1. Menulis ke basis data yang sedang diukur berarti mengukur diri sendiri.
 *      Tiap permintaan akan menambah INSERT-nya sendiri ke dalam angka.
 *   2. Tabel baru menuntut DDL; rekaman sesi uji tidak layak menjadi skema
 *      permanen — ia hidup beberapa menit lalu tak berguna.
 *   3. Penambahan baris ke berkas dengan LOCK_EX aman terhadap permintaan yang
 *      bersamaan. Akumulasi di cache TIDAK: baca-ubah-tulis dari dua permintaan
 *      serentak saling menimpa, dan kehilangannya justru saat beban paling
 *      ramai — tepat keadaan yang paling ingin direkam.
 *
 * Yang disimpan hanya SQL berparameter (bindings TIDAK ikut), jadi tak ada
 * nilai milik orang yang tertulis ke berkas.
 */
class PerekamKueriPerRute
{
    private const BERKAS_AKTIF = 'sesi-aktif.txt';

    /*
     * Sesi TERAKHIR diingat terpisah dari sesi yang sedang berjalan.
     *
     * Alur wajarnya "mulai → jalankan fitur → BERHENTI → baca laporannya".
     * Kalau laporannya hanya bisa dibaca selama sesinya masih berjalan, tombol
     * berhenti justru menghapus hasil yang baru saja dikumpulkan — dan itu
     * persis yang terjadi pada percobaan pertama.
     */
    private const BERKAS_TERAKHIR = 'sesi-terakhir.txt';

    /** Batas baris per sesi, supaya satu sesi yang terlupakan tak menggerus disk. */
    private const MAKS_BARIS = 20000;

    public function __construct(
        protected GerbangDiagnostikRealtime $gerbang,
    ) {}

    /**
     * Penunjuk sesi disimpan sebagai BERKAS, bukan cache.
     *
     * phpunit.xml memasang CACHE_DRIVER=array, sehingga sesi yang ditulis oleh
     * `php artisan diagnostik:rekam mulai` sama sekali tak terlihat oleh proses
     * uji — perekamannya diam dan tak ada yang memberi tahu kenapa. Terukur:
     * satu suite penuh menghasilkan 0 baris. Berkas menyeberangi proses dan
     * driver cache apa pun.
     */
    public function sesiAktif(): ?string
    {
        return $this->bacaPenunjuk(self::BERKAS_AKTIF);
    }

    /** Merekam hanya bila gerbang dev terbuka DAN sesi sedang berjalan. */
    public function sedangMerekam(): bool
    {
        return $this->sesiAktif() !== null && $this->gerbang->boleh();
    }

    public function mulai(): array
    {
        $id = now()->format('Ymd-His');
        @mkdir(dirname($this->berkas($id)), 0775, true);
        @file_put_contents($this->berkas($id), '');
        $this->tulisPenunjuk(self::BERKAS_AKTIF, $id);
        $this->tulisPenunjuk(self::BERKAS_TERAKHIR, $id);

        return ['sesi' => $id, 'mulai' => now()->format('Y-m-d H:i:s')];
    }

    public function berhenti(): void
    {
        $this->tulisPenunjuk(self::BERKAS_AKTIF, null);
    }

    public function hapus(string $id): void
    {
        @unlink($this->berkas($id));
        if ($this->sesiAktif() === $id) {
            $this->berhenti();
        }
        if ($this->sesiTerakhir() === $id) {
            $this->tulisPenunjuk(self::BERKAS_TERAKHIR, null);
        }
    }

    /**
     * Satu baris per PERMINTAAN, bukan per kueri.
     *
     * Per kueri akan menulis puluhan baris untuk satu klik; ringkasannya sudah
     * dilakukan di memori selagi permintaan berjalan, jadi berkasnya tetap
     * kecil dan penulisannya tetap satu kali.
     *
     * Permintaan yang TIDAK menjalankan satu kueri pun tidak ditulis sama
     * sekali (pemanggilnya yang menyaring). Baris kosong tidak menambah apa
     * pun pada analisis dan hanya menyulitkan membaca yang berisi.
     *
     * @param  array<string,mixed>  $baris
     */
    public function catat(array $baris): void
    {
        $id = $this->sesiAktif();
        if ($id === null) {
            return;
        }

        $berkas = $this->berkas($id);
        if (is_file($berkas) && filesize($berkas) > 20 * 1024 * 1024) {
            return;   // sesi terlupakan; berhenti menulis diam-diam
        }

        @file_put_contents(
            $berkas,
            json_encode($baris, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n",
            FILE_APPEND | LOCK_EX,
        );
    }

    /**
     * Ringkasan per RUTE, dengan kueri di dalamnya.
     *
     * @return array<string,mixed>
     */
    public function sesiTerakhir(): ?string
    {
        return $this->bacaPenunjuk(self::BERKAS_TERAKHIR);
    }

    protected function bacaPenunjuk(string $nama): ?string
    {
        $f = storage_path('app/diagnostik/'.$nama);
        if (! is_file($f)) {
            return null;
        }

        $isi = trim((string) @file_get_contents($f));

        return $isi !== '' ? $isi : null;
    }

    protected function tulisPenunjuk(string $nama, ?string $nilai): void
    {
        $f = storage_path('app/diagnostik/'.$nama);
        @mkdir(dirname($f), 0775, true);

        if ($nilai === null) {
            @unlink($f);

            return;
        }

        @file_put_contents($f, $nilai);
    }

    public function laporan(?string $id = null, int $maksRute = 30): array
    {
        $id ??= $this->sesiAktif() ?? $this->sesiTerakhir();
        if ($id === null || ! is_file($this->berkas($id))) {
            return [
                'sesi' => null,
                'berjalan' => false,
                'total' => ['permintaan' => 0, 'kueri' => 0, 'ms' => 0, 'baris_terbaca' => 0],
                'rute' => [],
            ];
        }

        $rute = [];
        $totPermintaan = 0;
        $totKueri = 0;
        $totMs = 0.0;

        $fh = fopen($this->berkas($id), 'r');
        $n = 0;
        while (($garis = fgets($fh)) !== false && $n < self::MAKS_BARIS) {
            $n++;
            $b = json_decode($garis, true);
            if (! is_array($b) || ! isset($b['rute'])) {
                continue;
            }

            $k = $b['rute'];
            $rute[$k] ??= [
                'rute' => $k,
                'permintaan' => 0,
                'kueri' => 0,
                'ms' => 0.0,
                'ms_maks' => 0.0,
                'kueri_terbanyak' => 0,
                'daftar' => [],
            ];

            $rute[$k]['permintaan']++;
            $rute[$k]['kueri'] += (int) ($b['kueri'] ?? 0);
            $rute[$k]['ms'] += (float) ($b['ms'] ?? 0);
            $rute[$k]['ms_maks'] = max($rute[$k]['ms_maks'], (float) ($b['ms'] ?? 0));
            // Puncak kueri dalam SATU permintaan: inilah tanda N+1, dan ia
            // hilang begitu angkanya dirata-ratakan.
            $rute[$k]['kueri_terbanyak'] = max($rute[$k]['kueri_terbanyak'], (int) ($b['kueri'] ?? 0));

            foreach ((array) ($b['sql'] ?? []) as $sql => $d) {
                $rute[$k]['daftar'][$sql] ??= ['sql' => $sql, 'n' => 0, 'ms' => 0.0];
                $rute[$k]['daftar'][$sql]['n'] += (int) ($d['n'] ?? 0);
                $rute[$k]['daftar'][$sql]['ms'] += (float) ($d['ms'] ?? 0);
            }

            $totPermintaan++;
            $totKueri += (int) ($b['kueri'] ?? 0);
            $totMs += (float) ($b['ms'] ?? 0);
        }
        fclose($fh);

        foreach ($rute as &$r) {
            $r['ms'] = round($r['ms'], 1);
            $r['ms_maks'] = round($r['ms_maks'], 1);
            $r['kueri_per_permintaan'] = $r['permintaan'] > 0
                ? round($r['kueri'] / $r['permintaan'], 1)
                : 0;

            usort($r['daftar'], static fn ($a, $b) => $b['ms'] <=> $a['ms']);
            $r['daftar'] = array_map(static function ($d) {
                $d['ms'] = round($d['ms'], 1);

                return $d;
            }, array_slice($r['daftar'], 0, 8));
        }
        unset($r);

        // Diurutkan menurut WAKTU DB total, bukan jumlah permintaan: rute yang
        // sering tapi murah bukan yang perlu diperbaiki.
        usort($rute, static fn ($a, $b) => $b['ms'] <=> $a['ms']);

        return [
            'sesi' => $id,
            'berjalan' => $this->sesiAktif() === $id,
            'total' => [
                'permintaan' => $totPermintaan,
                'kueri' => $totKueri,
                'ms' => round($totMs, 1),
                'baris_terbaca' => $n,
            ],
            'rute' => array_slice($rute, 0, $maksRute),
        ];
    }

    protected function berkas(string $id): string
    {
        return storage_path('app/diagnostik/rekaman-'.preg_replace('/[^0-9A-Za-z\-]/', '', $id).'.jsonl');
    }
}
