<?php

namespace Tests\Feature\SYSTEM;

use App\Console\Commands\SYSTEM\Application\Maintenance\PenjadwalPeriksa;
use Tests\TestCase;

/**
 * Pemeriksa penjadwal: logikanya murni, dan perintahnya tidak boleh menulis.
 *
 * ── Cacat yang melatarbelakanginya ───────────────────────────────────────────
 *
 * Jadwal HCIS hidup di tabel N_HRIS_Scheduler_Configs, dan Console\Kernel
 * mendaftarkannya apa adanya lewat $schedule->command($config->Command).
 * Laravel tidak memeriksa keberadaan perintahnya saat MENDAFTAR; kegagalannya
 * baru muncul saat cron menyala, sebagai "Command is not defined" di
 * storage/logs/scheduler.log — dan tidak ke mana-mana lagi.
 *
 * Terukur pada Web_HRIS: dari 20 entri aktif, TIGA menunjuk perintah yang tidak
 * ada di branch mana pun. Salah satunya, sop:recert-scan, perintahnya dihapus
 * bersama pencabutan masa berlaku SOP — tetapi barisnya masih Flag_Aktif='Y',
 * sehingga siapa pun yang membuka tabel itu membaca "resertifikasi berjalan tiap
 * malam 01:15". Ia tidak pernah berjalan.
 *
 * ── Kenapa ujinya begini ─────────────────────────────────────────────────────
 *
 * Jawaban sesungguhnya ada di BASIS DATA dan berbeda antar lingkungan, jadi uji
 * yang membacanya akan menjawab pertanyaan yang salah sekaligus mengunci suite
 * pada koneksi bersama. Yang diuji di sini: bagian yang MURNI (memisahkan nama
 * perintah dari opsinya, dan memaklumi prefix), ditambah pemeriksaan tekstual
 * bahwa perintahnya tetap hanya-baca.
 */
class PenjadwalPeriksaTest extends TestCase
{
    private const BERKAS = 'app/Console/Commands/SYSTEM/Application/Maintenance/PenjadwalPeriksa.php';

    public function test_nama_perintah_dipisahkan_dari_opsinya(): void
    {
        /*
         * Kolom Command menyimpan perintah LENGKAP beserta opsinya. Membandingkan
         * seluruh isi kolom dengan daftar perintah terdaftar akan menandai SETIAP
         * entri beropsi sebagai yatim — sembilan dari dua puluh entri di Web_HRIS
         * membawa opsi.
         */
        $this->assertSame('hris:absen-plan-rolling', PenjadwalPeriksa::namaPerintah(
            'hris:absen-plan-rolling --kode_perusahaan=001 --refresh_mode=MISSING_OR_DIRTY --priority=3'));

        $this->assertSame('sop:recert-scan', PenjadwalPeriksa::namaPerintah('sop:recert-scan'));
        $this->assertSame('sop:recert-scan', PenjadwalPeriksa::namaPerintah('  sop:recert-scan  '));
        $this->assertSame('', PenjadwalPeriksa::namaPerintah(''));
        $this->assertSame('', PenjadwalPeriksa::namaPerintah('   '));
    }

    public function test_prefix_yang_dimaklumi_hanya_cocok_di_awal(): void
    {
        /*
         * Fitur yang ditahan hidup di branch lain, jadi perintahnya memang tidak
         * ada di checkout ini — itu bukan cacat. Tetapi "dimaklumi" harus berarti
         * berawalan, bukan mengandung: kalau tidak, prefix "pa:" akan memaklumi
         * "sop:pa:apa-pun" dan menyembunyikan yatim yang sesungguhnya.
         */
        $this->assertTrue(PenjadwalPeriksa::dimaklumi('pa:sapu-tertahan', ['pa:']));
        $this->assertTrue(PenjadwalPeriksa::dimaklumi('pa:ingatkan-penilai', ['lain:', 'pa:']));

        $this->assertFalse(PenjadwalPeriksa::dimaklumi('sop:recert-scan', ['pa:']));
        $this->assertFalse(PenjadwalPeriksa::dimaklumi('sop:pa:sesuatu', ['pa:']),
            'Prefix harus dicocokkan di AWAL, bukan di mana saja.');
        $this->assertFalse(PenjadwalPeriksa::dimaklumi('pa:apa-pun', []));
        $this->assertFalse(PenjadwalPeriksa::dimaklumi('pa:apa-pun', ['']),
            'Prefix kosong tidak boleh memaklumi segalanya.');
    }

    public function test_perintahnya_hanya_membaca(): void
    {
        $isi = (string) file_get_contents(base_path(self::BERKAS));

        foreach (['->insert(', '->update(', '->delete(', '->upsert(', 'DB::statement('] as $tulis) {
            $this->assertStringNotContainsString($tulis, $isi,
                "Pemeriksa penjadwal menulis ke basis data ({$tulis}). Ia alat periksa; "
                .'menonaktifkan entri adalah keputusan manusia lewat perubahan DML.');
        }
    }

    public function test_hanya_entri_aktif_yang_diperiksa(): void
    {
        /*
         * Entri Flag_Aktif='T' memang sengaja dimatikan; melaporkannya sebagai
         * yatim hanya menghasilkan derau yang membuat laporan ini diabaikan.
         */
        $isi = (string) file_get_contents(base_path(self::BERKAS));

        $this->assertMatchesRegularExpression(
            "/->where\(\s*'Flag_Aktif'\s*,\s*'Y'\s*\)/",
            $isi,
            'Pemeriksa tidak lagi membatasi diri pada entri aktif.');
    }

    public function test_peringatan_lintas_lingkungan_tidak_hilang(): void
    {
        /*
         * Entri yang yatim di pengembangan bisa sah di produksi, dan sebaliknya.
         * Tanpa kalimat ini laporannya akan dibaca sebagai daftar hapus.
         */
        $isi = (string) file_get_contents(base_path(self::BERKAS));

        $this->assertStringContainsString('Jalankan di keduanya sebelum menonaktifkan', $isi,
            'Peringatan lintas lingkungan hilang dari keluaran perintah.');
    }
}
