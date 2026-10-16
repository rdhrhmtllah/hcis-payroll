<?php

namespace App\Services\SYSTEM\Application\Reports\Kontrak;

/**
 * Satu kolom pengelompokan.
 *
 * `select` adalah ekspresi SQL utuh, bukan nama kolom. Itu yang membuat modul
 * seperti Pengumpulan Data — yang jawabannya hidup di dalam kolom JSON — tetap
 * muat tanpa mengubah mesin.
 */
class Dimensi
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $select,
        public readonly string $tipe,
        public readonly string $grup,
        public readonly bool $sensitif = false,
        /**
         * teks | angka | tanggal | tanggal_jam | ya_tidak | tautan:{kunci} — menentukan
         * penyajian nilai, bukan tipe SQL-nya. `tautan:{kunci}` mengubah ID berkas
         * menjadi tautan lewat TautanBerkasRegistry.
         */
        public readonly string $format = 'teks',
    ) {}
}
