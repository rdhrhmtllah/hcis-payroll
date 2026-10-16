<?php

namespace App\Services\SYSTEM\Application\Reports;

/**
 * Definisi laporan — seluruh nilainya adalah KUNCI KATALOG, tak pernah SQL.
 *
 * Itu sebabnya injeksi mustahil secara konstruksi: tidak ada satu pun jalur
 * dari sini menuju query yang membawa string bebas dari pengguna. Kunci yang
 * tak dikenal ditolak validator, bukan di-escape.
 */
class DefinisiLaporan
{
    public function __construct(
        public readonly string $dataset,
        public readonly string $mode,
        public readonly array $dimensi,
        public readonly array $ukuran,
        public readonly array $kolom,
        public readonly array $saringan,
        public readonly array $urut,
        /**
         * Ukuran racikan pengguna: [{kolom, fungsi, label}].
         *
         * Isinya tetap KUNCI KATALOG dan nama fungsi dari whitelist — tidak
         * pernah SQL. Perakitan ekspresinya milik AgregatRacikan, sehingga
         * jaminan "tak ada string bebas pengguna yang sampai ke query" tetap
         * berlaku persis seperti sebelum fitur ini ada.
         */
        public readonly array $agregat = [],
        public readonly array $opsi = [],
        public readonly ?array $bagan = null,
        public readonly string $formatBawaan = 'xlsx',
        public readonly int $versi = 1,
    ) {}

    public function denganUrut(array $urut): self
    {
        return new self(
            dataset: $this->dataset,
            mode: $this->mode,
            dimensi: $this->dimensi,
            ukuran: $this->ukuran,
            kolom: $this->kolom,
            saringan: $this->saringan,
            urut: $urut,
            agregat: $this->agregat,
            opsi: $this->opsi,
            bagan: $this->bagan,
            formatBawaan: $this->formatBawaan,
            versi: $this->versi,
        );
    }

    public static function dariArray(array $a): self
    {
        $rawSaringan = array_values((array) ($a['saringan'] ?? []));
        $cleanSaringan = array_values(array_filter($rawSaringan, function ($s) {
            if (! is_array($s)) {
                return false;
            }
            $field = (string) ($s['field'] ?? '');
            $op = (string) ($s['op'] ?? '');
            $nilai = $s['nilai'] ?? null;
            if ($field === '' || $op === '') {
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
        }));

        $agregat = array_values(array_filter(
            (array) ($a['agregat'] ?? []),
            static fn ($item) => is_array($item) && ($item['kolom'] ?? '') !== '' && ($item['fungsi'] ?? '') !== '',
        ));

        /*
         * Kunci ukuran racikan IKUT masuk daftar `ukuran` di sini, sekali.
         *
         * Tanpa langkah ini agregat racikan hanya bertambah di KATALOG, bukan di
         * daftar kolom yang dipilih — jadi ia lolos seluruh validasi, tidak
         * menimbulkan galat apa pun, dan sama sekali tidak muncul di hasil.
         * Pemakainya menambahkan agregat, menekan Pratinjau, dan mendapat tabel
         * yang persis sama seperti sebelumnya.
         *
         * Dilakukan di sini, bukan di layar, supaya SETIAP pemanggil — layar,
         * template tersimpan, pemanggilan langsung — mendapat perilaku yang sama.
         */
        $kunciRacikan = array_map(
            static fn (array $item) => AgregatRacikan::kunci(
                (string) $item['kolom'],
                strtolower((string) $item['fungsi']),
            ),
            $agregat,
        );

        return new self(
            dataset: (string) ($a['dataset'] ?? ''),
            mode: strtolower((string) ($a['mode'] ?? 'ringkasan')),
            dimensi: array_values((array) ($a['dimensi'] ?? [])),
            ukuran: array_values(array_unique(array_merge(
                array_values((array) ($a['ukuran'] ?? [])),
                $kunciRacikan,
            ))),
            kolom: array_values((array) ($a['kolom'] ?? [])),
            saringan: $cleanSaringan,
            urut: array_values((array) ($a['urut'] ?? [])),
            agregat: $agregat,
            opsi: (array) ($a['opsi'] ?? []),
            bagan: isset($a['bagan']) && is_array($a['bagan']) ? $a['bagan'] : null,
            formatBawaan: (string) ($a['format_bawaan'] ?? 'xlsx'),
            versi: (int) ($a['versi'] ?? 1),
        );
    }

    public function toArray(): array
    {
        return [
            'versi' => $this->versi,
            'dataset' => $this->dataset,
            'mode' => $this->mode,
            'dimensi' => $this->dimensi,
            'ukuran' => $this->ukuran,
            'kolom' => $this->kolom,
            'saringan' => $this->saringan,
            'urut' => $this->urut,
            'agregat' => $this->agregat,
            'opsi' => $this->opsi,
            'bagan' => $this->bagan,
            'format_bawaan' => $this->formatBawaan,
        ];
    }
}
