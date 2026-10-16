<?php

namespace Tests\Feature\SYSTEM;

use Tests\TestCase;

/**
 * Panel Activity Center menarik keempat jenis tugas dalam SATU kueri.
 *
 * Bentuk yang dijaga di sini gampang sekali kembali, dan itu bukan kekhawatiran
 * yang dibuat-buat: menambah "sumber" baru ke panel ini terasa seperti menyalin
 * satu blok kueri yang sudah ada dan mengganti Task_Type-nya. Empat sumber
 * pertama memang lahir persis begitu.
 *
 * KENAPA ITU MAHAL, padahal kuerinya sepele. Tabelnya kecil (129 baris) dan
 * dm_exec_query_stats mencatat kueri itu 0,7 ms di sisi server. Yang dibayar
 * pengguna bukan eksekusinya melainkan PERJALANANNYA: satu bolak-balik ke basis
 * data di lingkungan ini TERUKUR 54 ms (SELECT 1 kosong, p50 dari 30
 * percobaan). Empat sumber = empat perjalanan untuk satu tabel yang sama.
 *
 * Terukur pada perubahan yang menyertai penjaga ini:
 *
 *     sebelum : 7 kueri, ~440 ms
 *     sesudah : 4 kueri, ~270 ms
 *
 * dan keluaran panel dibuktikan IDENTIK byte-per-byte untuk tujuh lingkup
 * pengguna, pada list(5), list(20), maupun summary().
 *
 * Panel ini juga dipanggil dua kali per tampilan — list() untuk isinya dan
 * summary() untuk badge topbar — sehingga bentuk lama membayar delapan
 * perjalanan. Itulah sebabnya kueri tersebut tercatat 44.452 eksekusi di
 * dm_exec_query_stats, salah satu yang paling sering di seluruh server.
 */
class ActivityCenterSatuKueriTest extends TestCase
{
    private const BERKAS = 'app/Services/ESS/Activity/Center/ActivityCenterService.php';

    private function isi(): string
    {
        return (string) file_get_contents(base_path(self::BERKAS));
    }

    public function test_tidak_ada_kueri_daftar_per_task_type_yang_terpisah(): void
    {
        $isi = $this->isi();

        /*
         * Penanda bentuk lama: satu kueri ke tabel tugas yang MEMBATASI 20 baris
         * sambil menyaring satu Task_Type. Bentuk baru tidak punya limit(20)
         * sama sekali — batas per tipe dikerjakan ROW_NUMBER di dalam subkueri.
         */
        preg_match_all(
            '/DB::table\(AbstractExportService::TABLE\)(.{0,400}?)->limit\(20\)/s',
            $isi,
            $m,
        );

        $this->assertCount(
            0,
            $m[0],
            "Kueri daftar per Task_Type muncul lagi.\n".
            'Setiap satu menambah satu perjalanan penuh (54 ms terukur) ke tabel '.
            'yang sudah ditarik oleh tugasAsync(). Tambahkan Task_Type-nya ke '.
            'TIPE_TUGAS, jangan menyalin bloknya.',
        );
    }

    public function test_batas_dua_puluh_masih_berlaku_per_task_type(): void
    {
        $isi = $this->isi();

        /*
         * Ini penjaga atas MAKNA, bukan atas bentuk. Menyatukan empat kueri
         * dengan whereIn + limit 80 akan tampak setara dan lulus uji di atas,
         * padahal tidak setara: satu tipe yang ramai bisa mendorong tipe lain
         * keluar dari hasil sepenuhnya, dan panel diam-diam berhenti
         * menampilkan seluruh kategori.
         */
        $this->assertMatchesRegularExpression(
            '/ROW_NUMBER\(\)\s+OVER\s*\(\s*PARTITION BY Task_Type\s+ORDER BY Id_Export DESC\s*\)/i',
            $isi,
            'Batas 20 baris harus dihitung PER Task_Type lewat ROW_NUMBER. '.
            'Tanpa partisi itu, satu tipe yang ramai menenggelamkan tipe lain.',
        );

        $this->assertStringContainsString(
            "->where('_urut', '<=', 20)",
            $isi,
            'Penyaring hasil ROW_NUMBER hilang — subkuerinya menarik seluruh tabel.',
        );
    }

    public function test_daftar_tipe_tugas_lengkap(): void
    {
        $isi = $this->isi();

        /*
         * TIPE_TUGAS harus memuat setiap tipe yang dipakai pemanggil
         * tugasAsync(). Satu yang tertinggal tidak akan menghasilkan galat —
         * panel hanya berhenti menampilkan kategori itu, diam-diam.
         */
        preg_match_all('/\$this->tugasAsync\(\s*([^)]+?)\s*\)/', $isi, $m);
        $this->assertNotEmpty($m[1], 'Tidak ada pemanggil tugasAsync() — bentuknya berubah?');

        $awal = strpos($isi, 'private const TIPE_TUGAS = [');
        $this->assertNotFalse($awal, 'Konstanta TIPE_TUGAS tidak ditemukan.');
        $daftar = substr($isi, $awal, strpos($isi, '];', $awal) - $awal);

        foreach (array_unique($m[1]) as $tipe) {
            $this->assertStringContainsString(
                trim($tipe, " \t"),
                $daftar,
                "Task_Type {$tipe} dipanggil lewat tugasAsync() tetapi tidak ada di ".
                'TIPE_TUGAS, jadi kueri tunggalnya tidak pernah mengambil baris itu '.
                'dan kategorinya hilang dari panel tanpa galat apa pun.',
            );
        }
    }
}
