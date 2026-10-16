<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

use Illuminate\Database\Query\Builder;

/**
 * Satu dataset = satu query yang bisa di-GROUP BY.
 *
 * Bukan batasan gaya: inilah yang membuat agregasi turun ke SQL Server alih-alih
 * menarik puluhan ribu baris ke memori worker. Modul yang butuh prefetch harus
 * menyatakannya sebagai subquery/CTE di dalam baseQuery(), bukan sebagai
 * rangkaian query yang dijahit di PHP.
 */
interface DatasetLaporan
{
    public function kunci(): string;

    public function label(): string;

    /** Satu kalimat yang menjelaskan arti satu baris. Tampil di UI. */
    public function grain(): string;

    /** @return Dimensi[] */
    public function dimensi(): array;

    /** @return Ukuran[] */
    public function ukuran(): array;

    /** @return Saringan[] */
    public function saringan(): array;

    public function baseQuery(): Builder;

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder;
}
