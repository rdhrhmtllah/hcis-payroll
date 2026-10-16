<?php

namespace App\Services\KPI\PerformanceAppraisal\Katalog;

/**
 * Aturan pemetaan satu-satu antara bagian ujian dan kompetensi wajib satu level.
 *
 * Murni: tidak menyentuh basis data, tidak menyentuh jaringan. Aturannya cukup halus untuk
 * pantas diuji sendiri, dan cukup sering dipanggil — layar, gerbang simpan, dan gerbang
 * salin ke siklus — untuk pantas punya satu penulis saja.
 */
class PaBijeksiLabel
{
    /**
     * @param  list<array<string, mixed>>  $daftarBagian  bagian yang ADA di ujian sekarang
     * @param  list<int>  $kompetensiWajibId  kompetensi wajib level tersebut
     * @param  array<string, int>  $label  [kunci bagian => PA_Competency_Id]
     * @return list<array{jenis: string, pesan: string}> kosong berarti sah
     */
    public static function periksa(array $daftarBagian, array $kompetensiWajibId, array $label): array
    {
        $masalah = [];

        /* Kekembaran diperiksa PALING DULU dan mengembalikan lebih awal. Selama pasangan
           (alat tes, bank soal) ambigu, keempat pemeriksaan di bawahnya tidak bermakna —
           dua bagian berbeda menunjuk baris yang sama, sehingga "kompetensi kurang" yang
           dilaporkannya akan menyesatkan admin ke arah yang salah. */
        $kembar = PaBagianKatalog::kembar($daftarBagian);

        if ($kembar !== []) {
            return [[
                'jenis' => 'BAGIAN_KEMBAR',
                'pesan' => sprintf(
                    'Ujian ini memuat %d bagian yang identitasnya kembar: %s. HC Learn tidak '
                    .'memberi id tersendiri per bagian, jadi bagian yang memakai bank soal '
                    .'yang sama di dalam satu alat tes tidak bisa dibedakan. Minta HC Learn '
                    .'memisahkan bank soalnya sebelum ujian ini diikat.',
                    count($kembar),
                    implode(', ', array_slice($kembar, 0, 8)),
                ),
            ]];
        }

        $kunciBagian = array_map(fn ($b) => PaBagianKatalog::kunci($b), $daftarBagian);
        $wajib = array_values(array_unique(array_map('intval', $kompetensiWajibId)));

        /* Bagian yang dilabeli tetapi sudah tidak ada di ujian. Diperiksa sebelum cacah:
           tanpa ini, bagian yang hilang hanya muncul sebagai kompetensi yang kurang, dan
           admin akan mencari-cari label yang sebenarnya masih ada. */
        $labelYatim = array_values(array_diff(array_keys($label), $kunciBagian));

        if ($labelYatim !== []) {
            $masalah[] = [
                'jenis' => 'BAGIAN_HILANG',
                'pesan' => sprintf(
                    'Bagian yang sudah dilabeli tidak ada lagi di ujian: %s. Kemungkinan besar '
                    .'dinonaktifkan di HC Learn setelah ikatan ini dibuat.',
                    implode(', ', array_slice($labelYatim, 0, 8)),
                ),
            ];
        }

        if (count($kunciBagian) !== count($wajib)) {
            $masalah[] = [
                'jenis' => 'JUMLAH_TAK_SEPADAN',
                'pesan' => sprintf(
                    'Ujian ini punya %d bagian, sedangkan level ini menuntut %d kompetensi. '
                    .'Karena satu bagian mewakili tepat satu kompetensi, keduanya harus sama. '
                    .'Bisa karena bagian ditambah atau dikurangi di HC Learn, bisa juga karena '
                    .'matriks kompetensi level ini berubah di sisi kita.',
                    count($kunciBagian),
                    count($wajib),
                ),
            ];
        }

        /* Hanya label pada bagian yang MASIH ADA yang dihitung menutupi kompetensi.
           Menghitung label yatim membuat sel yang bagiannya hilang tetap tampak lengkap. */
        $terpakai = [];

        foreach ($label as $kunci => $kompetensiId) {
            if (in_array((string) $kunci, $kunciBagian, true)) {
                $terpakai[] = (int) $kompetensiId;
            }
        }

        $terpakai = array_values(array_unique($terpakai));
        $kurang = array_values(array_diff($wajib, $terpakai));
        $lebih = array_values(array_diff($terpakai, $wajib));

        if ($kurang !== []) {
            $masalah[] = [
                'jenis' => 'KOMPETENSI_KURANG',
                'pesan' => sprintf(
                    '%d kompetensi wajib level ini belum dipegang bagian mana pun.',
                    count($kurang),
                ),
            ];
        }

        if ($lebih !== []) {
            $masalah[] = [
                'jenis' => 'KOMPETENSI_LEBIH',
                'pesan' => sprintf(
                    '%d kompetensi yang dilabelkan tidak berlaku di level ini.',
                    count($lebih),
                ),
            ];
        }

        return $masalah;
    }
}
