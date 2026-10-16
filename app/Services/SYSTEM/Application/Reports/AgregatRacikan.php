<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;

/**
 * Pabrik ukuran racikan pengguna — DENGAN PAGAR.
 *
 * Ukuran::class menyatakan dengan tegas bahwa agregatnya "ekspresi lengkap yang
 * KAMI tulis, bukan hasil rakitan agregasi × field dari pengguna", karena
 * pengguna yang bebas merakit menghasilkan angka yang salah tetapi tampak
 * benar. Kelas ini TIDAK melanggar aturan itu — ia memenuhinya:
 *
 *  - pengguna hanya memilih PASANGAN (kolom katalog, fungsi dari whitelist);
 *  - ekspresinya tetap dirakit di sini, dari `Dimensi::$select` milik katalog;
 *  - tak satu pun potongan teks dari pengguna masuk ke SQL.
 *
 * Pagarnya ada tiga lapis, dan ketiganya menjawab kesalahan yang berbeda:
 *
 *  1. FUNGSI PER TIPE. Kolom teks hanya boleh dihitung, bukan dijumlahkan —
 *     `SUM(Nama_Tamu)` bukan cuma tak berarti, ia galat basis data. Kolom
 *     tanggal boleh MIN/MAX (kunjungan pertama/terakhir) tetapi tidak SUM.
 *
 *  2. TOTALISASI OTOMATIS. Rata-rata, nilai terkecil/terbesar, dan COUNT
 *     DISTINCT semuanya rusak bila dijumlahkan lintas kelompok: rata-rata dari
 *     rata-rata bukan rata-rata, dan satu instansi yang muncul di dua kelompok
 *     terhitung dua kali. Ukuran seperti itu ditandai TOTAL_ABAIKAN, sehingga
 *     baris GRAND TOTAL menulis '-' alih-alih angka yang salah tapi meyakinkan.
 *
 *  3. LABEL MENYEBUT FUNGSINYA. "Jumlah Tamu" saja ambigu antara SUM dan COUNT;
 *     pada dataset yang grain-nya satu kunjungan, keduanya menjawab pertanyaan
 *     yang berbeda — orang versus kunjungan. Label bawaannya karena itu selalu
 *     membawa nama fungsinya kecuali pengguna menamainya sendiri.
 */
class AgregatRacikan
{
    public const COUNT = 'count';

    public const COUNT_DISTINCT = 'count_distinct';

    public const SUM = 'sum';

    public const AVG = 'avg';

    public const MIN = 'min';

    public const MAX = 'max';

    /** Awalan kunci racikan; dipakai juga validator untuk mengenalinya kembali. */
    public const AWALAN_KUNCI = 'racik__';

    /**
     * Fungsi yang masuk akal per tipe kolom katalog.
     *
     * Bukan sekadar "apa yang tidak error": MIN/MAX pada teks sah secara SQL
     * (abjad terkecil) tetapi tidak pernah menjawab pertanyaan siapa pun, dan
     * pada kolom sensitif ia justru membocorkan satu nilai utuh lewat laporan
     * yang seharusnya hanya berisi hitungan.
     */
    private const PER_TIPE = [
        'angka' => [self::COUNT, self::COUNT_DISTINCT, self::SUM, self::AVG, self::MIN, self::MAX],
        'tanggal' => [self::COUNT, self::COUNT_DISTINCT, self::MIN, self::MAX],
        'teks' => [self::COUNT, self::COUNT_DISTINCT],
    ];

    private const LABEL_FUNGSI = [
        self::COUNT => 'Jumlah',
        self::COUNT_DISTINCT => 'Jumlah Unik',
        self::SUM => 'Total',
        self::AVG => 'Rata-rata',
        self::MIN => 'Terkecil',
        self::MAX => 'Terbesar',
    ];

    /** Ukuran yang RUSAK bila dijumlahkan lintas kelompok. */
    private const TAK_BISA_DIJUMLAH = [self::AVG, self::MIN, self::MAX, self::COUNT_DISTINCT];

    /**
     * Fungsi yang boleh dipakai pada satu dimensi.
     *
     * @return list<string>
     */
    public static function fungsiUntuk(Dimensi $dimensi): array
    {
        return self::PER_TIPE[$dimensi->tipe] ?? self::PER_TIPE['teks'];
    }

    public static function diizinkan(Dimensi $dimensi, string $fungsi): bool
    {
        return in_array($fungsi, self::fungsiUntuk($dimensi), true);
    }

    /**
     * Rakit satu Ukuran dari pasangan yang SUDAH divalidasi.
     *
     * @param  string|null  $label  nama dari pengguna; kosong = label bawaan
     */
    public static function buat(Dimensi $dimensi, string $fungsi, ?string $label = null): Ukuran
    {
        if (! self::diizinkan($dimensi, $fungsi)) {
            throw new \InvalidArgumentException(
                "Fungsi '{$fungsi}' tidak berlaku untuk kolom '{$dimensi->key}' bertipe {$dimensi->tipe}.",
            );
        }

        $judul = trim((string) $label);

        return new Ukuran(
            key: self::kunci($dimensi->key, $fungsi),
            label: $judul !== '' ? $judul : self::LABEL_FUNGSI[$fungsi].' '.$dimensi->label,
            agregat: self::ekspresi($dimensi->select, $fungsi),
            // AVG pada kolom angka tetap angka; MIN/MAX pada tanggal tetap
            // tanggal. Memaksa 'angka' membuat tanggal tercetak sebagai bilangan.
            format: self::format($dimensi, $fungsi),
            // Kolom sensitif menular ke ukurannya HANYA bila nilainya bisa
            // terbaca utuh. Hitungan tidak membocorkan apa pun, jadi COUNT atas
            // NIK tetap aman; MIN/MAX-nya tidak — tetapi teks memang tidak
            // pernah menawarkan keduanya.
            sensitif: $dimensi->sensitif && ! in_array($fungsi, [self::COUNT, self::COUNT_DISTINCT], true),
            totalisasi: in_array($fungsi, self::TAK_BISA_DIJUMLAH, true)
                ? Ukuran::TOTAL_ABAIKAN
                : Ukuran::TOTAL_JUMLAH,
        );
    }

    /** Kunci ukuran racikan; berawalan supaya tak pernah bentrok dengan katalog. */
    public static function kunci(string $kolom, string $fungsi): string
    {
        return self::AWALAN_KUNCI.$fungsi.'__'.$kolom;
    }

    private static function ekspresi(string $select, string $fungsi): string
    {
        return match ($fungsi) {
            // COUNT kolom, bukan COUNT(*): baris yang nilainya NULL memang tidak
            // punya apa pun untuk dihitung, dan COUNT(*) diam-diam menghitungnya.
            self::COUNT => "COUNT({$select})",
            self::COUNT_DISTINCT => "COUNT(DISTINCT {$select})",
            self::SUM => "SUM({$select})",
            // Dipaksa desimal: AVG pada kolom bilangan bulat di SQL Server
            // membuang pecahannya — rata-rata 2,8 orang tercetak 2, dan tidak
            // ada galat yang menandainya.
            self::AVG => "AVG(CAST({$select} AS decimal(18,2)))",
            self::MIN => "MIN({$select})",
            self::MAX => "MAX({$select})",
            default => throw new \InvalidArgumentException("Fungsi agregat tidak dikenal: {$fungsi}"),
        };
    }

    private static function format(Dimensi $dimensi, string $fungsi): string
    {
        if (in_array($fungsi, [self::COUNT, self::COUNT_DISTINCT], true)) {
            return 'angka';
        }

        if ($dimensi->tipe === 'tanggal') {
            return $dimensi->format === 'tanggal_jam' ? 'tanggal_jam' : 'tanggal';
        }

        return 'angka';
    }

    /**
     * Katalog fungsi untuk layar: apa yang boleh dipilih pada tiap kolom.
     *
     * Dikirim dari SERVER supaya layar tidak menebak sendiri. Menebaknya berarti
     * ada dua definisi "fungsi yang sah", dan keduanya akan menyimpang.
     *
     * @param  Dimensi[]  $dimensi
     * @return array<string,mixed>
     */
    public static function katalog(array $dimensi): array
    {
        $perKolom = [];

        foreach ($dimensi as $dim) {
            $perKolom[$dim->key] = self::fungsiUntuk($dim);
        }

        return [
            'fungsi' => array_map(
                static fn (string $kunci, string $label) => [
                    'value' => $kunci,
                    'label' => $label,
                    'bisa_dijumlah' => ! in_array($kunci, self::TAK_BISA_DIJUMLAH, true),
                ],
                array_keys(self::LABEL_FUNGSI),
                array_values(self::LABEL_FUNGSI),
            ),
            'per_kolom' => $perKolom,
        ];
    }
}
