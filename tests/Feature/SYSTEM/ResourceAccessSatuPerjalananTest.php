<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Menyimpan resource access: satu pemeriksaan untuk semua kode, satu transaksi.
 *
 * ── Yang diperbaiki ──────────────────────────────────────────────────────────
 *
 * saveResourceAccess() melakukan cek-lalu-tulis PER KODE di dalam loop: satu
 * SELECT dan satu tulis untuk masing-masing. Untuk satu klik "simpan" berisi N
 * kode, itu 2N perjalanan ke basis data — dan di sistem ini perjalanan yang
 * mahal, bukan kuerinya.
 *
 * Yang lebih serius: tidak ada transaksi sama sekali. Gagal di kode ke-5 dari
 * 10 meninggalkan peran dengan setengah set izin, dan admin tidak diberi tahu
 * mana yang mendarat. Di tabel OTORISASI.
 *
 * ── Kenapa transaksinya hanya membungkus penulisan ───────────────────────────
 *
 * RCSI mati di basis data ini, jadi baris yang di-UPDATE menahan pembaca.
 * Membungkus pemeriksaan sekalian akan menahan kunci selama seluruh loop
 * pemeriksaan — memperbaiki keutuhan dengan mengorbankan hal lain.
 *
 * Balapan dengan permintaan lain memang tetap mungkin, dan itu ditangani di
 * lapisan yang tepat: UQ_NHRIS_RRA_RoleContextTypeCode menolak baris kembar,
 * transaksinya batal seluruhnya, dan admin mendapat galat alih-alih separuh set
 * izin. Lihat juga DDL-2026-087 untuk tabel sekerabatnya yang BELUM punya
 * penjaga setara.
 *
 * Uji ini tekstual: metodenya endpoint besar yang menyentuh basis data. Yang
 * dijaga bukan gaya kode melainkan ketiga sifat di atas.
 */
class ResourceAccessSatuPerjalananTest extends TestCase
{
    private const BERKAS = 'app/Http/Controllers/SYSTEM/Governance/Authorization/SystemAuthController.php';

    private function badan(): string
    {
        $isi = (string) file_get_contents(base_path(self::BERKAS));

        $cocok = preg_match(
            '/\n[ \t]*public function saveResourceAccess\s*\(/',
            $isi,
            $m,
            PREG_OFFSET_CAPTURE,
        );
        $this->assertSame(1, $cocok, 'saveResourceAccess() hilang.');

        $buka = strpos($isi, '{', $m[0][1]);
        $dalam = 0;
        for ($i = $buka; $i < strlen($isi); $i++) {
            if ($isi[$i] === '{') {
                $dalam++;
            } elseif ($isi[$i] === '}') {
                $dalam--;
                if ($dalam === 0) {
                    return substr($isi, $m[0][1], $i - $m[0][1] + 1);
                }
            }
        }

        $this->fail('Kurung saveResourceAccess() tidak seimbang.');
    }

    public function test_pemeriksaan_dilakukan_sekali_untuk_semua_kode(): void
    {
        /*
         * INTI PERBAIKANNYA. Satu whereIn menggantikan N kali ->first().
         */
        $badan = $this->badan();

        $this->assertStringContainsString(
            "->whereIn('Resource_Code'",
            $badan,
            'Pemeriksaan kembali dilakukan per kode. Untuk N kode itu N perjalanan '.
            'tambahan, dan perjalanan yang mahal di sistem ini, bukan kuerinya.',
        );

        $this->assertSame(
            0,
            preg_match_all("/->first\(\['Id_Role_Resource_Access'\]\)/", $badan),
            'Pemeriksaan per-kode kembali.',
        );
    }

    public function test_penulisan_dibungkus_transaksi(): void
    {
        $badan = $this->badan();

        $this->assertMatchesRegularExpression(
            '/DB::transaction\(function \(\) use \(\$barisBaru, \$perluDiperbarui\)/',
            $badan,
            'Penulisan tidak lagi dibungkus transaksi. Gagal di tengah meninggalkan '.
            'peran dengan setengah set izin.',
        );

        /*
         * Transaksinya harus dimulai SESUDAH pemeriksaan, bukan sebelumnya:
         * RCSI mati, jadi kunci yang ditahan selama loop pemeriksaan menahan
         * pembaca lain.
         */
        $posWhereIn = strpos($badan, "->whereIn('Resource_Code'");
        $posTransaksi = strpos($badan, 'DB::transaction(');

        $this->assertNotFalse($posWhereIn);
        $this->assertNotFalse($posTransaksi);
        $this->assertLessThan(
            $posTransaksi,
            $posWhereIn,
            'Transaksi dimulai sebelum pemeriksaan selesai — kunci ditahan lebih lama '.
            'dari yang diperlukan.',
        );
    }

    public function test_kedua_kumpulan_dipotong_terhadap_batas_parameter(): void
    {
        /*
         * SQL Server menolak lebih dari 2.100 parameter, dan galatnya menyamar
         * sebagai galat basis data biasa. Dua tempat bisa melewatinya di sini:
         * whereIn atas daftar kode, dan insert massal barisnya.
         */
        $badan = $this->badan();

        $this->assertStringContainsString(
            '$normalizedCodes->chunk(',
            $badan,
            'Daftar kode tidak lagi dipotong sebelum masuk whereIn.',
        );

        /*
         * Bentuknya berubah, sifatnya menguat. Dulu: array_chunk($barisBaru, 150),
         * benar untuk 9-10 kolom tetapi hanya selama jumlah kolomnya tidak berubah.
         * Sekarang ukuran potongan dihitung DARI barisnya, jadi aritmetikanya tak
         * bisa basi — lihat PenyisipanBerbatasParameter dan penjaganya sendiri,
         * InfrastrukturSqlServerSatuRumahTest.
         */
        $this->assertStringContainsString(
            'PenyisipanBerbatasParameter::sisipkan(',
            $badan,
            'Insert massal tidak lagi dipotong terhadap batas parameter.',
        );
    }

    public function test_hitungan_created_dan_updated_tetap_dilaporkan(): void
    {
        /*
         * Layarnya menampilkan kedua angka ini. Mengganti seluruh blok dengan
         * satu upsert akan lebih ringkas tetapi menghapus keduanya — kontrak
         * respons ikut berubah tanpa ada yang memintanya.
         */
        $badan = $this->badan();

        $this->assertStringContainsString('$created = count($barisBaru);', $badan);
        $this->assertStringContainsString('$updated = count($perluDiperbarui);', $badan);
        $this->assertStringContainsString("'created' => \$created,", $badan);
        $this->assertStringContainsString("'updated' => \$updated,", $badan);
    }

    public function test_rute_tulis_ini_tidak_lagi_tercatat_tanpa_transaksi(): void
    {
        /*
         * Penjaga bentuk: penulisan di dalam metode ini harus berada di dalam
         * blok DB::transaction, bukan tersebar sesudahnya.
         */
        $badan = $this->badan();
        $posTransaksi = strpos($badan, 'DB::transaction(');

        foreach (['PenyisipanBerbatasParameter::sisipkan(', '->update($payload)'] as $tulis) {
            $pos = strpos($badan, $tulis, $posTransaksi);
            $this->assertNotFalse(
                $pos,
                "Penulisan {$tulis} tidak ditemukan sesudah pembuka transaksi.",
            );
        }
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(4000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
