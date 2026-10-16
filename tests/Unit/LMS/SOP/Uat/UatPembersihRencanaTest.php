<?php

namespace Tests\Unit\LMS\SOP\Uat;

use App\Services\LMS\SOP\Uat\UatJurnal;
use App\Services\LMS\SOP\Uat\UatPembersih;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Rencana penghapusan dipisahkan dari pelaksanaannya, dan uji ini menguji
 * rencananya.
 *
 * Pemisahan itu bukan selera arsitektur. Perintah yang menghapus data harus bisa
 * diperiksa TANPA menghapus apa pun — kalau satu-satunya cara mengetahui apa yang
 * akan dihapus adalah dengan menjalankannya, maka tidak ada cara aman untuk
 * mengujinya sama sekali.
 */
class UatPembersihRencanaTest extends TestCase
{
    private string $sarang;

    /** Pengganti INFORMATION_SCHEMA supaya rencana bisa diuji tanpa database. */
    private $pk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sarang = sys_get_temp_dir().'/uat-rencana-'.bin2hex(random_bytes(4));
        mkdir($this->sarang, 0777, true);
        $this->pk = fn (string $tabel) => 'Id_'.$tabel;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->sarang.'/*') ?: [] as $f) {
            unlink($f);
        }
        @rmdir($this->sarang);
        parent::tearDown();
    }

    private function jurnal(array $isi): UatJurnal
    {
        $j = UatJurnal::mulai($this->sarang);
        foreach ($isi as $tabel => $ids) {
            foreach ($ids as $id) {
                $j->catat($tabel, $id);
            }
        }

        return $j;
    }

    public function test_rencana_memuat_tabel_kunci_dan_id_yang_dicatat(): void
    {
        $rencana = (new UatPembersih)->rencana(
            $this->jurnal(['N_SOP_Dokumen' => [3, 4]]),
            $this->pk
        );

        $this->assertCount(1, $rencana);
        $this->assertSame('N_SOP_Dokumen', $rencana[0]['tabel']);
        $this->assertSame('Id_N_SOP_Dokumen', $rencana[0]['kunci']);
        $this->assertSame([3, 4], $rencana[0]['id']);
    }

    /**
     * Inti seluruh kontrak keselamatan. Tabel tanpa ID menghasilkan DELETE tanpa
     * WHERE, dan DELETE tanpa WHERE pada database yang dipakai orang bekerja
     * adalah kerusakan yang tak bisa dibatalkan.
     */
    public function test_tabel_tanpa_id_tidak_pernah_masuk_rencana(): void
    {
        $j = UatJurnal::mulai($this->sarang);
        $j->catat('N_SOP_Dokumen', 1);

        $rencana = (new UatPembersih)->rencana($j, $this->pk);

        foreach ($rencana as $langkah) {
            $this->assertNotEmpty($langkah['id'],
                "Langkah untuk {$langkah['tabel']} tidak punya ID — itu DELETE tanpa WHERE.");
        }
    }

    public function test_rencana_mengikuti_urutan_anak_dulu_induk_belakangan(): void
    {
        $rencana = (new UatPembersih)->rencana(
            $this->jurnal([
                'N_SOP_Dokumen' => [1],
                'N_SOP_Progress' => [2],
                'N_SOP_Item_Versi' => [3],
            ]),
            $this->pk
        );

        $urut = array_column($rencana, 'tabel');

        $this->assertLessThan(array_search('N_SOP_Item_Versi', $urut), array_search('N_SOP_Progress', $urut));
        $this->assertLessThan(array_search('N_SOP_Dokumen', $urut), array_search('N_SOP_Item_Versi', $urut));
    }

    public function test_menolak_rencana_bila_kunci_utama_tak_bisa_ditentukan(): void
    {
        $this->expectException(RuntimeException::class);

        (new UatPembersih)->rencana(
            $this->jurnal(['N_SOP_Dokumen' => [1]]),
            fn (string $t) => null
        );
    }

    /**
     * Kunci gabungan berarti WHERE satu kolom akan menghapus baris yang bukan
     * milik jalan ini. Berhenti lebih baik daripada menebak.
     */
    public function test_menolak_kunci_utama_gabungan(): void
    {
        $this->expectException(RuntimeException::class);

        (new UatPembersih)->rencana(
            $this->jurnal(['N_SOP_Dokumen' => [1]]),
            fn (string $t) => ['Id_A', 'Id_B']
        );
    }

    public function test_jurnal_kosong_menghasilkan_rencana_kosong_bukan_kesalahan(): void
    {
        $rencana = (new UatPembersih)->rencana(UatJurnal::mulai($this->sarang), $this->pk);

        $this->assertSame([], $rencana);
    }
}
