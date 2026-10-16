<?php

namespace App\Services\SYSTEM\Application\Reports;

use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Definisi + dataset + lingkup -> satu Query Builder.
 *
 * SELURUH ekspresi SQL berasal dari katalog, tak satu pun dari pengguna. Yang
 * dibawa pengguna hanyalah KUNCI dan NILAI saringan; kunci dicocokkan ke
 * katalog (yang tak cocok dibuang di sini dan ditolak validator lebih dulu),
 * nilai selalu jadi binding.
 *
 * Agregasi diturunkan ke GROUP BY di SQL Server, bukan dihitung di PHP setelah
 * menarik seluruh baris — perbedaannya ratusan baris hasil versus puluhan ribu
 * baris mentah di memori worker.
 */
class ReportCompiler
{
    public function susun(DefinisiLaporan $d, DatasetLaporan $ds, Lingkup $lingkup): Builder
    {
        $dimensi = $this->petakan($ds->dimensi());
        $ukuran = $this->petakan($ds->ukuran());
        $saringan = $this->petakan($ds->saringan());

        $q = $ds->terapkanLingkup($ds->baseQuery(), $lingkup);

        foreach ($d->saringan as $s) {
            if (! is_array($s) || ! isset($saringan[$s['field'] ?? ''])) {
                continue;
            }
            $op = (string) ($s['op'] ?? '');
            $nilai = $s['nilai'] ?? null;
            if (! $this->apakahSaringanLengkap($op, $nilai)) {
                continue;
            }
            $this->terapkanSatuSaringan($q, $saringan[$s['field']]->kolom, $op, $nilai);
        }

        if ($d->mode === 'rinci') {
            $pilih = [];
            foreach ($d->kolom as $k) {
                if (isset($dimensi[$k])) {
                    $pilih[] = DB::raw($dimensi[$k]->select.' AS ['.$k.']');
                }
            }
            $q->select($pilih);
            $this->terapkanUrut($q, $d->urut, $d->kolom);

            return $q;
        }

        $pilih = [];
        foreach ($d->dimensi as $k) {
            if (isset($dimensi[$k])) {
                $pilih[] = DB::raw($dimensi[$k]->select.' AS ['.$k.']');
                $q->groupBy(DB::raw($dimensi[$k]->select));
            }
        }
        foreach ($d->ukuran as $k) {
            if (isset($ukuran[$k])) {
                $pilih[] = DB::raw($ukuran[$k]->agregat.' AS ['.$k.']');
            }
        }
        $q->select($pilih);
        $this->terapkanUrut($q, $d->urut, array_merge($d->dimensi, $d->ukuran));

        return $q;
    }

    private function terapkanSatuSaringan(Builder $q, string $kolom, string $op, $nilai): void
    {
        $kolomRaw = DB::raw($kolom);

        match ($op) {
            'salah_satu' => $q->whereIn($kolomRaw, (array) $nilai),
            'antara' => $q->whereBetween($kolomRaw, [((array) $nilai)[0] ?? null, ((array) $nilai)[1] ?? null]),
            'mengandung' => $q->where($kolomRaw, 'like', '%'.self::amankanLike(is_array($nilai) ? '' : (string) $nilai).'%'),
            'tidak_sama' => $q->where($kolomRaw, '!=', is_array($nilai) ? ($nilai[0] ?? null) : $nilai),
            'lebih_besar' => $q->where($kolomRaw, '>', is_array($nilai) ? ($nilai[0] ?? null) : $nilai),
            'lebih_besar_sama' => $q->where($kolomRaw, '>=', is_array($nilai) ? ($nilai[0] ?? null) : $nilai),
            'lebih_kecil' => $q->where($kolomRaw, '<', is_array($nilai) ? ($nilai[0] ?? null) : $nilai),
            'lebih_kecil_sama' => $q->where($kolomRaw, '<=', is_array($nilai) ? ($nilai[0] ?? null) : $nilai),
            default => $q->where($kolomRaw, '=', is_array($nilai) ? ($nilai[0] ?? null) : $nilai),
        };
    }

    /**
     * Menetralkan wildcard LIKE di dalam nilai yang diketik pengguna.
     *
     * Tanpa ini, mengetik "100%" pada saringan "mengandung" mencari apa pun
     * yang diawali "100", dan "_" cocok dengan sembarang satu karakter — hasil
     * yang terlihat masuk akal tapi bukan yang diminta. Dipakai kurung siku
     * (cara SQL Server) supaya tidak perlu klausa ESCAPE yang tak bisa
     * disisipkan lewat Query Builder.
     */
    public static function amankanLike(string $teks): string
    {
        return str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], $teks);
    }

    private function apakahSaringanLengkap(string $op, mixed $nilai): bool
    {
        if ($op === '') {
            return false;
        }

        if (is_array($nilai)) {
            if ($nilai === []) {
                return false;
            }
            if ($op === 'antara') {
                $dari = $nilai[0] ?? null;
                $sampai = $nilai[1] ?? null;

                return $dari !== null && $dari !== '' && $sampai !== null && $sampai !== '';
            }

            return array_filter($nilai, fn ($v) => $v !== null && $v !== '') !== [];
        }

        if ($nilai === null) {
            return false;
        }

        return is_string($nilai) ? trim($nilai) !== '' : true;
    }

    private function terapkanUrut(Builder $q, array $urut, array $kunciDipilih): void
    {
        foreach ($urut as $u) {
            $key = is_array($u) ? ($u['key'] ?? null) : null;

            // Mengurut berdasarkan kunci yang tidak dipilih akan merujuk alias
            // yang tak ada di SELECT. Dibuang diam-diam, bukan digagalkan:
            // validator sudah menolaknya lebih dulu di jalur simpan.
            if (! is_string($key) || ! in_array($key, $kunciDipilih, true)) {
                continue;
            }

            $arah = (is_array($u) && ($u['arah'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
            $q->orderBy(DB::raw('['.$key.']'), $arah);
        }
    }

    /** @return array<string, object> */
    private function petakan(array $daftar): array
    {
        $peta = [];
        foreach ($daftar as $item) {
            $peta[$item->key] = $item;
        }

        return $peta;
    }
}
