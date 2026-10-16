<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Pemberian hak akses halaman massal tidak boleh berbentuk perulangan bersarang.
 *
 * ── Skalanya, terukur ────────────────────────────────────────────────────────
 *
 * Bentuk lama: untuk TIAP karyawan satu kueri identitas, lalu untuk tiap
 * (karyawan × halaman) satu `exists()` plus satu `update`/`insert`.
 * Biayanya N × (1 + 2M).
 *
 * Pada basis data ini — 958 karyawan aktif, 103 halaman di menu:
 *
 *     3 halaman  ->  6.706 kueri
 *     10 halaman -> 20.118 kueri
 *
 * seluruhnya di dalam SATU transaksi.
 *
 * ── Kenapa itu lebih berbahaya daripada sekadar lambat ───────────────────────
 *
 * `HRIS_Page_Access` dibaca pada hampir setiap permintaan — 11.642 eksekusi
 * tercatat di dm_exec_query_stats — dan RCSI mati di basis data ini. Selama
 * transaksi itu berjalan, pengguna lain menunggu hanya untuk tahu halaman apa
 * yang boleh ia buka. Panjang transaksinya berbanding lurus dengan jumlah
 * karyawan dikali jumlah halaman.
 *
 * ── Dibuktikan setara, read-only ─────────────────────────────────────────────
 *
 * Pada 60 karyawan × 3 halaman, kedua bentuk menghasilkan himpunan yang sama
 * persis — pasangan yang sudah ada (16 = 16) dan peta identitas divisi/level
 * (60 = 60) — sementara fase bacanya turun dari 240 kueri menjadi 2
 * (47.458 ms -> 715 ms), dan UPDATE-nya dari 16 menjadi 7.
 */
class HakAksesHalamanMassalTest extends TestCase
{
    private const BERKAS = 'app/Http/Controllers/SYSTEM/Governance/Authorization/PageAccess/PageAccessController.php';

    private function badanUpdateAccess(): string
    {
        $isi = (string) file_get_contents(base_path(self::BERKAS));

        $cocok = preg_match('/^[ \t]*public function updateAccess\s*\(/m', $isi, $m, PREG_OFFSET_CAPTURE);
        $this->assertSame(1, $cocok, 'updateAccess() tidak ditemukan — namanya berubah?');

        $awal = (int) $m[0][1];
        $buka = strpos($isi, '{', $awal);
        $dalam = 0;

        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    $badan = substr($isi, $awal, $i - $awal + 1);

                    $this->assertStringContainsString(
                        'DB::beginTransaction()',
                        $badan,
                        'Irisan updateAccess() tidak berisi transaksi — kemungkinan yang '.
                        'teriris bukan metode yang hidup.',
                    );

                    return $badan;
                }
            }
        }

        $this->fail('Kurung kurawal updateAccess() tidak seimbang.');
    }

    public function test_tidak_ada_pemeriksaan_keberadaan_per_pasangan(): void
    {
        $badan = $this->badanUpdateAccess();

        $this->assertStringNotContainsString(
            '->exists()',
            $badan,
            "updateAccess() kembali memeriksa keberadaan baris satu per satu.\n".
            'Pada 958 karyawan × 3 halaman itu 2.874 perjalanan tambahan, semuanya '.
            'di dalam transaksi yang mengunci HRIS_Page_Access — tabel yang dibaca '.
            'hampir setiap permintaan. Muat pasangan yang sudah ada dengan SATU '.
            'kueri whereIn.',
        );
    }

    public function test_identitas_karyawan_dimuat_sekali_untuk_semua(): void
    {
        $badan = $this->badanUpdateAccess();

        $this->assertMatchesRegularExpression(
            '/->whereIn\(\s*\'a\.UserID_Web\'\s*,\s*\$idKaryawan\s*\)/',
            $badan,
            'Identitas divisi/level harus dimuat untuk SEMUA karyawan dalam satu '.
            'kueri. Bentuk lama memanggil first() sekali per karyawan.',
        );

        /*
         * Penjaga atas MAKNA: whereIn saja tidak cukup kalau ia masih berada di
         * dalam perulangan karyawan. Yang menentukan adalah tidak ada lagi
         * perulangan atas employeeIds yang membungkus kueri.
         */
        $this->assertStringNotContainsString(
            "foreach (\$validated['employeeIds'] as \$employeeId) {",
            $badan,
            'Perulangan atas employeeIds yang membungkus kueri kembali muncul. '.
            'Semua pekerjaan basis data harus di luar perulangan itu.',
        );
    }

    public function test_penulisan_massal_dipotong_agar_aman_dari_batas_parameter(): void
    {
        $badan = $this->badanUpdateAccess();

        /*
         * Yang dijaga SIFATNYA, bukan bentuknya.
         *
         * Versi pertama penjaga ini menuntut `array_chunk($barisBaru, N)`
         * secara harfiah, karena begitulah pemotongan ditulis saat itu. Lalu
         * pemotongan dipusatkan ke PenyisipanBerbatasParameter::sisipkan() --
         * yang menghitung ukuran potongan dari JUMLAH KOLOM, bukan angka yang
         * ditulis tangan dan bisa salah begitu satu kolom ditambahkan. Penjaga
         * lama menghukum perubahan yang justru lebih benar.
         *
         * Jadi yang diterima kini dua-duanya: helper terpusat, atau pemotongan
         * manual yang angkanya masih di bawah batas. Yang tetap ditolak adalah
         * penyisipan massal tanpa keduanya.
         */
        $lewatHelper = str_contains($badan, 'PenyisipanBerbatasParameter::sisipkan(');
        $manual = (bool) preg_match('/array_chunk\(\$barisBaru,\s*(\d+)\)/', $badan, $m);

        $this->assertTrue(
            $lewatHelper || $manual,
            'INSERT massal harus dipotong: SQL Server menolak di atas 2.100 '.
            'parameter per pernyataan. Pakai PenyisipanBerbatasParameter::sisipkan() '.
            '-- ia menghitung ukuran potongan dari jumlah kolom, jadi menambah '.
            'satu kolom tidak diam-diam melewati batas.',
        );

        if (! $lewatHelper) {
            $this->assertLessThanOrEqual(
                262,
                (int) ($m[1] ?? PHP_INT_MAX),
                'Potongan terlalu besar: delapan kolom dikali potongan ini melewati '.
                'batas 2.100 parameter SQL Server.',
            );
        }

        $this->assertMatchesRegularExpression(
            '/array_chunk\(\$idKaryawan,\s*\d+\)/',
            $badan,
            'Daftar karyawan pada whereIn juga harus dipotong — 958 nilai dalam '.
            'satu IN() aman, tetapi jumlah karyawan bukan tetapan.',
        );
    }

    public function test_pencabutan_juga_massal(): void
    {
        $badan = $this->badanUpdateAccess();

        $posisiRevoke = strpos($badan, "'revoke'");
        $this->assertNotFalse($posisiRevoke, 'Cabang revoke tidak ditemukan.');

        /*
         * Pencabutan tidak butuh identitas apa pun, jadi ia harus jadi satu
         * UPDATE. Kalau hanya cabang grant yang dibereskan, "cabut akses semua"
         * tetap membayar satu perjalanan per pasangan — dan menahan kunci yang
         * sama persis.
         */
        $this->assertMatchesRegularExpression(
            '/\'revoke\'\)\s*\{.{0,600}?whereIn\(\'Jenis_page\'/s',
            $badan,
            'Cabang revoke tidak memakai whereIn pada Jenis_page — ia masih '.
            'mencabut satu per satu.',
        );
    }
}
