<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;

/**
 * Dataset asli + ukuran racikan pengguna, sebagai satu katalog.
 *
 * Bungkus, bukan perubahan pada validator dan runner. Alasannya bukan sekadar
 * hemat perubahan: begitu ukuran racikan masuk lewat `ukuran()`, SELURUH jalur
 * di belakangnya memperlakukannya persis seperti ukuran katalog — divalidasi
 * dengan aturan yang sama, dirakit dengan kode yang sama, ditotalkan dengan
 * aturan yang sama. Tidak ada cabang "kalau racikan" yang bisa menua sendiri
 * dan menyimpang dari saudaranya.
 *
 * Konsekuensi yang disengaja: ukuran racikan yang tidak lolos pabriknya tidak
 * pernah sampai ke sini. Yang melewati pintu ini sudah sah menurut definisi.
 */
class DatasetDenganAgregatRacikan implements DatasetLaporan, PenyediaOpsiSaringan
{
    /** @param Ukuran[] $racikan */
    public function __construct(
        private readonly DatasetLaporan $asli,
        private readonly array $racikan,
    ) {}

    /**
     * Rakit dari definisi pengguna; entri yang tidak sah DIABAIKAN di sini.
     *
     * Penolakannya tetap milik validator — ia yang menyusun pesan galat untuk
     * pemakainya. Kalau pabrik ini ikut melempar, permintaan yang sama akan
     * dibalas dua jenis kegagalan yang berbeda tergantung mana yang jalan
     * lebih dulu.
     *
     * @param  array<int,array<string,mixed>>  $agregat
     */
    public static function dari(DatasetLaporan $asli, array $agregat): DatasetLaporan
    {
        if ($agregat === []) {
            return $asli;
        }

        $dimensi = [];
        foreach ($asli->dimensi() as $dim) {
            $dimensi[$dim->key] = $dim;
        }

        $racikan = [];

        foreach ($agregat as $item) {
            $kolom = (string) ($item['kolom'] ?? '');
            $fungsi = strtolower((string) ($item['fungsi'] ?? ''));

            if (! isset($dimensi[$kolom]) || ! AgregatRacikan::diizinkan($dimensi[$kolom], $fungsi)) {
                continue;
            }

            $ukuran = AgregatRacikan::buat($dimensi[$kolom], $fungsi, $item['label'] ?? null);
            // Kunci kembar (kolom+fungsi yang sama dipilih dua kali) dilipat,
            // bukan digandakan: dua kolom dengan judul dan isi identik hanya
            // membuat pembacanya mengira ada yang berbeda.
            $racikan[$ukuran->key] = $ukuran;
        }

        return $racikan === [] ? $asli : new self($asli, array_values($racikan));
    }

    public function kunci(): string
    {
        return $this->asli->kunci();
    }

    public function label(): string
    {
        return $this->asli->label();
    }

    public function grain(): string
    {
        return $this->asli->grain();
    }

    public function dimensi(): array
    {
        return $this->asli->dimensi();
    }

    public function ukuran(): array
    {
        return array_merge($this->asli->ukuran(), $this->racikan);
    }

    public function saringan(): array
    {
        return $this->asli->saringan();
    }

    public function baseQuery(): Builder
    {
        return $this->asli->baseQuery();
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        return $this->asli->terapkanLingkup($q, $lingkup);
    }

    public function opsiSaringan(): array
    {
        return $this->asli instanceof PenyediaOpsiSaringan ? $this->asli->opsiSaringan() : [];
    }
}
