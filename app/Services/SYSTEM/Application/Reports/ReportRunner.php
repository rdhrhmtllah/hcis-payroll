<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan definisi dan mengembalikan bentuk yang SAMA PERSIS dengan dataset
 * legacy (title, generated_at, columns, rows) supaya penulis CSV/XLSX/PDF yang
 * sudah ada bisa dipakai tanpa perubahan.
 *
 * Pratinjau layar memanggil compiler yang sama dengan $batas kecil. Itu jaminan
 * terpenting fitur ini: pratinjau BUKAN pendekatan dari berkas, ia berkas yang
 * sama dijalankan lebih awal.
 */
class ReportRunner
{
    private const MASK = '—';

    /** Nilai kosong ditulis '-', mengikuti berkas export yang sudah ada. */
    private const KOSONG = '-';

    /**
     * Tiap berapa baris progres dilaporkan saat menyusun berkas.
     *
     * Bukan sekadar kosmetik bar: pemanggil memakai laporan ini untuk mengecek
     * pembatalan. Tanpa denyut di tengah pengambilan data, tombol "Batalkan"
     * baru berpengaruh setelah SELURUH baris terlanjur ditarik.
     */
    private const TIK_PROGRES = 500;

    public function __construct(private ReportCompiler $compiler) {}

    /**
     * Menjalankan pratinjau dengan paginasi dan pencarian server-side langsung di Database.
     *
     * $hitungTotal boleh dimatikan saat berpindah halaman: menghitung ulang
     * total tiap paginasi berarti dua kali pindai penuh untuk informasi yang
     * tidak berubah. Layar menyimpan total dari permintaan pertama.
     */
    public function jalankanPratinjau(
        DefinisiLaporan $d,
        DatasetLaporan $ds,
        Lingkup $lingkup,
        string $judul,
        int $halaman = 1,
        int $perHalaman = 24,
        ?string $pencarian = null,
        bool $hitungTotal = true,
    ): array {
        $kunciTerpilih = $d->mode === 'rinci' ? $d->kolom : array_merge($d->dimensi, $d->ukuran);
        $meta = $this->metaKolom($ds, $kunciTerpilih);

        $q = $this->compiler->susun($d, $ds, $lingkup);

        // Jika ada pencarian, bungkus query terkompilasi ke dalam subquery
        // agar pencarian lintas kolom dan agregasi GROUP BY berjalan efisien di SQL Server
        if ($pencarian !== null && trim($pencarian) !== '') {
            $term = '%'.ReportCompiler::amankanLike(trim($pencarian)).'%';
            $innerQ = clone $q;
            $innerQ->orders = null; // Derived table di SQL Server tidak boleh punya ORDER BY tanpa TOP/OFFSET
            $baseSql = $innerQ->toSql();
            $q = DB::table(DB::raw("({$baseSql}) AS [sub]"))->mergeBindings($innerQ);
            $q->where(function ($subQ) use ($kunciTerpilih, $term) {
                foreach ($kunciTerpilih as $idx => $col) {
                    if ($idx === 0) {
                        $subQ->where(DB::raw("[sub].[{$col}]"), 'like', $term);
                    } else {
                        $subQ->orWhere(DB::raw("[sub].[{$col}]"), 'like', $term);
                    }
                }
            });
            // Terapkan pengurutan pada wrapper subquery
            if (! empty($d->urut)) {
                foreach ($d->urut as $u) {
                    $k = is_array($u) ? ($u['key'] ?? null) : null;
                    if ($k && in_array($k, $kunciTerpilih, true)) {
                        $arah = (is_array($u) && ($u['arah'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
                        $q->orderBy(DB::raw("[sub].[{$k}]"), $arah);
                    }
                }
            }
        }

        $total = $hitungTotal ? $this->hitungTotal($q) : null;

        // Pastikan ada ORDER BY untuk kepatuhan OFFSET / FETCH SQL Server
        if (empty($q->orders)) {
            $kolomPertama = $kunciTerpilih[0] ?? null;
            if ($kolomPertama !== null) {
                $q->orderBy(DB::raw('['.$kolomPertama.']'), 'asc');
            }
        }

        $halaman = max(1, $halaman);
        $perHalaman = max(1, min(500, $perHalaman));
        $offset = ($halaman - 1) * $perHalaman;

        $rawRows = $q->offset($offset)->limit($perHalaman)->get()->all();
        $baris = array_map(fn ($r) => $this->formatBaris((array) $r, $meta), $rawRows);

        $catatan = [];
        $baris = $this->samarkanKolomSensitif($baris, $meta, $lingkup, $catatan);

        return [
            'title' => $judul,
            'generated_at' => Carbon::now()->format('Y-m-d H:i'),
            'columns' => $this->kolomKeluaran($kunciTerpilih, $meta),
            'rows' => $baris,
            'total' => $total,
            'page' => $halaman,
            'per_page' => $perHalaman,
            'total_pages' => $total === null ? null : (int) ceil($total / $perHalaman),
            'catatan' => $catatan,
        ];
    }

    /**
     * @param  int|null  $batas  plafon baris; baris ke-($batas+1) hanya dipakai
     *                           untuk mendeteksi pemotongan, tidak ikut berkas.
     * @param  callable|null  $lapor  fn(int $diproses, int $total): void — dipanggil
     *                                tiap TIK_PROGRES baris. Boleh melempar untuk
     *                                menghentikan proses (mis. job dibatalkan).
     */
    public function jalankan(
        DefinisiLaporan $d,
        DatasetLaporan $ds,
        Lingkup $lingkup,
        string $judul,
        ?int $batas = null,
        ?callable $lapor = null,
    ): array {
        $kunciTerpilih = $d->mode === 'rinci' ? $d->kolom : array_merge($d->dimensi, $d->ukuran);
        $meta = $this->metaKolom($ds, $kunciTerpilih);

        $q = $this->compiler->susun($d, $ds, $lingkup);

        // Total dihitung lebih dulu HANYA bila ada yang mendengarkan progres:
        // satu agregat tambahan per job latar wajar, tapi percuma bila tak ada
        // bar yang bergerak.
        $totalPerkiraan = 0;
        if ($lapor !== null) {
            $totalPerkiraan = $this->hitungTotal($q);
            if ($batas !== null) {
                $totalPerkiraan = min($totalPerkiraan, $batas);
            }
        }

        if ($batas !== null) {
            $q->limit($batas + 1);   // +1 untuk mendeteksi pemotongan
        }

        // cursor(), bukan get(): berkas rinci bergrain karyawan x paket x item
        // bisa berpuluh ribu baris, dan menahan seluruh hasil mentah DI SAMPING
        // salinan terformatnya adalah cara termudah membuat worker kehabisan
        // memori tepat sebelum berkas jadi.
        $baris = [];
        $diproses = 0;
        foreach ($q->cursor() as $r) {
            $baris[] = $this->formatBaris((array) $r, $meta);
            $diproses++;

            if ($lapor !== null && $diproses % self::TIK_PROGRES === 0) {
                $lapor($diproses, $totalPerkiraan);
            }
        }

        $terpotong = $batas !== null && count($baris) > $batas;
        if ($terpotong) {
            $baris = array_slice($baris, 0, $batas);
        }

        $catatan = [];

        // Kolomnya TETAP ADA, isinya di-mask. Menghapus kolom mengubah bentuk
        // hasil, sehingga pivot yang dibangun di atas template yang sama rusak
        // untuk sebagian orang saja -- mode gagal yang paling sulit dilacak.
        $baris = $this->samarkanKolomSensitif($baris, $meta, $lingkup, $catatan);

        if ($terpotong) {
            $catatan[] = "Laporan dipotong pada {$batas} baris teratas. Persempit saringan agar seluruh data ikut terbawa.";
        }

        // Grand total DIMATIKAN saat data terpotong: total atas potongan adalah
        // angka salah yang tampak benar.
        if (! $terpotong && ($d->opsi['grand_total'] ?? false) && $d->mode === 'ringkasan' && $baris !== []) {
            [$barisTotal, $diabaikan] = $this->grandTotal($baris, $d->dimensi, $d->ukuran, $meta);
            $baris[] = $barisTotal;

            if ($diabaikan !== []) {
                $catatan[] = 'GRAND TOTAL tidak diisi untuk '.implode(', ', $diabaikan)
                    .' — rata-rata, persentase, dan hitungan unik tidak sah dijumlahkan antar kelompok.';
            }
        }

        return [
            'title' => $judul,
            'generated_at' => Carbon::now()->format('Y-m-d H:i'),
            'columns' => $this->kolomKeluaran($kunciTerpilih, $meta),
            'rows' => $baris,
            'catatan' => $catatan,
        ];
    }

    /**
     * Menjalankan laporan sebagai aliran baris untuk writer yang memang dapat
     * menulis bertahap (saat ini CSV). Berbeda dari jalankan(), hasil query dan
     * salinan terformat tidak pernah ditahan seluruhnya di memori.
     *
     * `stream_state` adalah object agar jumlah/catatan yang baru diketahui saat
     * Generator dikonsumsi dapat dibaca renderer dan lifecycle job sesudahnya.
     */
    public function jalankanStreaming(
        DefinisiLaporan $d,
        DatasetLaporan $ds,
        Lingkup $lingkup,
        string $judul,
        ?int $batas = null,
        ?callable $lapor = null,
    ): array {
        $kunciTerpilih = $d->mode === 'rinci' ? $d->kolom : array_merge($d->dimensi, $d->ukuran);
        $meta = $this->metaKolom($ds, $kunciTerpilih);
        $q = $this->compiler->susun($d, $ds, $lingkup);

        $totalPerkiraan = 0;
        if ($lapor !== null) {
            $totalPerkiraan = $this->hitungTotal($q);
            if ($batas !== null) {
                $totalPerkiraan = min($totalPerkiraan, $batas);
            }
        }

        if ($batas !== null) {
            $q->limit($batas + 1);
        }

        $disembunyikan = $this->kolomSensitifTersembunyi($meta, $lingkup);
        $state = (object) [
            'count' => 0,
            'catatan' => $disembunyikan === []
                ? []
                : [count($disembunyikan).' kolom disembunyikan sesuai hak akses.'],
        ];

        $baris = (function () use ($q, $meta, $disembunyikan, $state, $batas, $lapor, $totalPerkiraan, $d) {
            $diproses = 0;
            $terpotong = false;
            $punyaBaris = false;
            $jumlah = [];

            foreach ($q->cursor() as $raw) {
                if ($batas !== null && $diproses >= $batas) {
                    $terpotong = true;
                    break;
                }

                $row = $this->formatBaris((array) $raw, $meta);
                foreach ($disembunyikan as $kunci) {
                    $row[$kunci] = self::MASK;
                }

                if (($d->opsi['grand_total'] ?? false) && $d->mode === 'ringkasan') {
                    foreach ($d->ukuran as $kunci) {
                        if (($meta[$kunci]['totalisasi'] ?? Ukuran::TOTAL_JUMLAH) === Ukuran::TOTAL_JUMLAH) {
                            $jumlah[$kunci] = ($jumlah[$kunci] ?? 0.0) + (float) ($row[$kunci] ?? 0);
                        }
                    }
                }

                $diproses++;
                $state->count++;
                $punyaBaris = true;

                if ($lapor !== null && $diproses % self::TIK_PROGRES === 0) {
                    $lapor($diproses, $totalPerkiraan);
                }

                yield $row;
            }

            if ($terpotong) {
                $state->catatan[] = "Laporan dipotong pada {$batas} baris teratas. Persempit saringan agar seluruh data ikut terbawa.";
            }

            if (! $terpotong && $punyaBaris && ($d->opsi['grand_total'] ?? false) && $d->mode === 'ringkasan') {
                $total = [];
                foreach ($d->dimensi as $index => $kunci) {
                    $total[$kunci] = $index === 0 ? 'GRAND TOTAL' : '';
                }

                $diabaikan = [];
                foreach ($d->ukuran as $kunci) {
                    if (($meta[$kunci]['totalisasi'] ?? Ukuran::TOTAL_JUMLAH) !== Ukuran::TOTAL_JUMLAH) {
                        $total[$kunci] = self::KOSONG;
                        $diabaikan[] = $meta[$kunci]['label'] ?? $kunci;
                    } else {
                        $total[$kunci] = $this->angkaRingkas($jumlah[$kunci] ?? 0, 2);
                    }
                }

                if ($diabaikan !== []) {
                    $state->catatan[] = 'GRAND TOTAL tidak diisi untuk '.implode(', ', $diabaikan)
                        .' â€” rata-rata, persentase, dan hitungan unik tidak sah dijumlahkan antar kelompok.';
                }

                $state->count++;
                yield $total;
            }
        })();

        return [
            'title' => $judul,
            'generated_at' => Carbon::now()->format('Y-m-d H:i'),
            'columns' => $this->kolomKeluaran($kunciTerpilih, $meta),
            'rows' => $baris,
            'catatan' => [],
            'stream_state' => $state,
        ];
    }

    /**
     * Menghitung jumlah baris hasil di sisi Database.
     *
     * Query dibungkus derived table karena hasil ber-GROUP BY dan beralias
     * tidak bisa langsung di-COUNT di SQL Server.
     */
    private function hitungTotal(Builder $q): int
    {
        $qHitung = clone $q;
        $qHitung->orders = null; // ORDER BY dilarang di derived table tanpa TOP/OFFSET

        return (int) DB::table(DB::raw("({$qHitung->toSql()}) AS [cnt_total]"))
            ->mergeBindings($qHitung)
            ->count();
    }

    /** @param  array<int, string>  $catatan  diisi bila ada kolom yang disamarkan */
    private function samarkanKolomSensitif(array $baris, array $meta, Lingkup $lingkup, array &$catatan): array
    {
        $disembunyikan = $this->kolomSensitifTersembunyi($meta, $lingkup);

        if ($disembunyikan === []) {
            return $baris;
        }

        foreach ($baris as &$r) {
            foreach ($disembunyikan as $k) {
                $r[$k] = self::MASK;
            }
        }
        unset($r);

        $catatan[] = count($disembunyikan).' kolom disembunyikan sesuai hak akses.';

        return $baris;
    }

    /** @return string[] */
    private function kolomSensitifTersembunyi(array $meta, Lingkup $lingkup): array
    {
        if ($lingkup->bolehFieldTerbatas) {
            return [];
        }

        return array_keys(array_filter($meta, fn (array $kolom) => $kolom['sensitif']));
    }

    /**
     * `numerik` ikut ke penulis berkas.
     *
     * Tanpa penanda ini penulis XLSX menuliskan SEMUA sel sebagai teks, dan
     * PivotTable di Excel tidak bisa menjumlah kolom teks — padahal berkas
     * rinci memang dibuat untuk di-pivot.
     */
    private function kolomKeluaran(array $kunciTerpilih, array $meta): array
    {
        return array_values(array_map(
            fn ($k) => [
                'key' => $k,
                'label' => $meta[$k]['label'],
                'width' => $meta[$k]['width'],
                'center' => $meta[$k]['center'],
                'numerik' => $meta[$k]['numerik'],
                // Penulis XLSX menjadikannya hyperlink yang bisa diklik.
                'tautan' => str_starts_with((string) ($meta[$k]['format'] ?? ''), 'tautan:'),
            ],
            $kunciTerpilih
        ));
    }

    /**
     * Menyajikan nilai mentah SQL Server menjadi bentuk yang dibaca manusia.
     *
     * Bukan kosmetik: berkas lama sudah menuliskan '-' untuk nilai kosong dan
     * memotong datetime ke menit. Tanpa lapisan ini, mesin baru menghasilkan
     * berkas yang datanya benar tapi tampilannya berbeda, dan setiap rumus
     * Excel yang dibangun di atas berkas lama ikut berubah artinya.
     */
    private function formatBaris(array $r, array $meta): array
    {
        foreach ($meta as $k => $m) {
            if (! array_key_exists($k, $r)) {
                continue;
            }

            $r[$k] = $this->formatNilai($r[$k], $m['format']);
        }

        return $r;
    }

    private function formatNilai($nilai, string $format): string
    {
        if ($nilai === null || $nilai === '') {
            return self::KOSONG;
        }

        // `tautan:{kunci}` — nilai SQL-nya ID berkas; tautannya dibuat modul
        // pemiliknya lewat TautanBerkasRegistry (bertanda tangan & berjangka).
        if (str_starts_with($format, 'tautan:')) {
            return app(TautanBerkasRegistry::class)->buat(substr($format, 7), (string) $nilai) ?? self::KOSONG;
        }

        return match ($format) {
            'ya_tidak' => in_array((string) $nilai, ['Y', '1', 'true'], true) ? 'Ya' : 'Tidak',
            'tanggal' => ($t = $this->keTanggal($nilai)) ? $t->format('Y-m-d') : (string) $nilai,
            'tanggal_jam' => ($t = $this->keTanggal($nilai)) ? $t->format('Y-m-d H:i') : (string) $nilai,
            // AVG() SQL Server mengembalikan float panjang; 137.42857142857 detik
            // adalah angka benar yang tak terbaca. Dibulatkan, TIDAK diberi
            // satuan atau pemisah ribuan, supaya tetap bisa dibaca Excel sebagai
            // angka.
            'angka' => $this->angkaRingkas($nilai, 2),
            'persen' => $this->angkaRingkas($nilai, 1),
            'durasi' => $this->angkaRingkas($nilai, 0),
            default => (string) $nilai,
        };
    }

    private function angkaRingkas($nilai, int $desimal): string
    {
        if (! is_numeric($nilai)) {
            return (string) $nilai;
        }

        // Titik desimal, bukan koma, dan tanpa pemisah ribuan: berkas ini dibaca
        // mesin (Excel, pivot) sebelum dibaca orang.
        $teks = number_format(round((float) $nilai, $desimal), $desimal, '.', '');

        // Pemangkasan nol HANYA di belakang titik desimal. Memangkasnya tanpa
        // syarat mengubah 100 menjadi 1 — kesalahan yang tidak menimbulkan galat
        // apa pun dan hanya terlihat kalau angkanya kebetulan diperiksa.
        if (str_contains($teks, '.')) {
            $teks = rtrim(rtrim($teks, '0'), '.');
        }

        return ($teks === '' || $teks === '-') ? '0' : $teks;
    }

    private function keTanggal($nilai): ?Carbon
    {
        try {
            return Carbon::parse((string) $nilai);
        } catch (\Throwable $e) {
            // Nilai yang tak bisa diurai dikembalikan apa adanya oleh pemanggil,
            // bukan digagalkan: satu baris cacat tak boleh membatalkan laporan.
            return null;
        }
    }

    /**
     * @return array{0: array<string, string>, 1: string[]} [baris total, label ukuran yang tak dijumlahkan]
     */
    private function grandTotal(array $baris, array $dimensi, array $ukuran, array $meta): array
    {
        $total = [];
        foreach ($dimensi as $i => $k) {
            $total[$k] = $i === 0 ? 'GRAND TOTAL' : '';
        }

        $diabaikan = [];
        foreach ($ukuran as $k) {
            if (($meta[$k]['totalisasi'] ?? Ukuran::TOTAL_JUMLAH) !== Ukuran::TOTAL_JUMLAH) {
                $total[$k] = self::KOSONG;
                $diabaikan[] = $meta[$k]['label'] ?? $k;

                continue;
            }

            $total[$k] = $this->angkaRingkas(
                array_sum(array_map(fn ($r) => (float) ($r[$k] ?? 0), $baris)),
                2
            );
        }

        return [$total, $diabaikan];
    }

    /** @return array<string, array{label:string, width:int, center:bool, sensitif:bool, format:string, numerik:bool, totalisasi:string}> */
    private function metaKolom(DatasetLaporan $ds, array $kunci): array
    {
        $semua = [];
        foreach ($ds->dimensi() as $dim) {
            $semua[$dim->key] = [
                'label' => $dim->label,
                'width' => str_starts_with($dim->format, 'tautan:') ? 16 : 20,
                'center' => false,
                'sensitif' => $dim->sensitif,
                'format' => $dim->format,
                'numerik' => $dim->tipe === 'angka',
                'totalisasi' => Ukuran::TOTAL_ABAIKAN,
            ];
        }
        foreach ($ds->ukuran() as $u) {
            $semua[$u->key] = [
                'label' => $u->label,
                'width' => 12,
                'center' => true,
                'sensitif' => $u->sensitif,
                // Format ukuran diambil dari katalog, bukan dipaksa 'angka'.
                // Selama ia dipaksa, 'persen' dan 'durasi' yang dideklarasikan
                // dataset tidak pernah berlaku sama sekali.
                'format' => $u->format,
                'numerik' => true,
                'totalisasi' => $u->totalisasi,
            ];
        }

        $meta = [];
        foreach ($kunci as $k) {
            $meta[$k] = $semua[$k] ?? [
                'label' => $k,
                'width' => 20,
                'center' => false,
                'sensitif' => false,
                'format' => 'teks',
                'numerik' => false,
                'totalisasi' => Ukuran::TOTAL_ABAIKAN,
            ];
        }

        return $meta;
    }
}
