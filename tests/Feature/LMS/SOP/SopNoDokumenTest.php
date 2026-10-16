<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nomor urut dokumen (`N_SOP_Dokumen.No_Dokumen`).
 *
 * BUKAN nomor versi. Nomor versi bergerak sepanjang siklus revisi di
 * N_SOP_Item_Versi; nomor dokumen ditetapkan sekali di master dan tidak ikut
 * naik saat versi baru terbit.
 *
 * Deretnya berjalan per KOMBINASI lima field identitas: jenis dokumen, entitas
 * pemilik, departemen pemilik, ruang lingkup, dan proses APQC.
 */
class SopNoDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function jenis(string $kode): int
    {
        $id = DB::table('N_SOP_Master_Jenis_Dokumen')->where('Kode_Jenis', $kode)->value('Id_SOP_Jenis_Dokumen');
        if (! $id) {
            $this->markTestSkipped("Seed jenis {$kode} belum ada.");
        }

        return (int) $id;
    }

    private function entitas(string $kode): int
    {
        $id = DB::table('N_HRIS_Master_Entitas')->where('Kode_Entitas', $kode)->value('Id_Entitas');
        if (! $id) {
            $this->markTestSkipped("Seed entitas {$kode} belum ada.");
        }

        return (int) $id;
    }

    public function test_kolom_no_dokumen_ada_dan_nullable(): void
    {
        $kolom = DB::selectOne(
            'SELECT DATA_TYPE, IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['N_SOP_Dokumen', 'No_Dokumen']
        );

        $this->assertNotNull($kolom,
            'Kolom No_Dokumen belum ada — jalankan database/ddl/DDL-2026-026-sop-dokumen-no-dokumen/forward.sql');
        $this->assertSame('int', $kolom->DATA_TYPE,
            'Disimpan INT, bukan teks: sebagai teks "10" lebih kecil dari "9" dan MAX() ikut salah.');
        $this->assertSame('YES', $kolom->IS_NULLABLE);
    }

    /**
     * Deret nomor dokumen mulai dari SATU.
     *
     * Uji ini dulu menuntut nol, dengan alasan "sama seperti nomor versi".
     * Keduanya memang bertetangga di nomor resmi — .../05/00 — tetapi menghitung
     * hal yang berbeda: segmen keenam menjawab "dokumen keberapa dalam kombinasi
     * ini", segmen ketujuh menjawab "sudah berapa kali direvisi". Tak ada dokumen
     * ke-nol; revisi ke-nol justru keadaan normal setiap terbitan pertama.
     */
    public function test_dokumen_pertama_di_kombinasinya_bernomor_satu(): void
    {
        $grup = ['Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'), 'Entitas_Pemilik_Id' => $this->entitas('ENB')];

        $id = $this->svc()->buat(['Judul' => 'SOP UJI NOMOR PERTAMA', 'Tipe' => 'DOKUMEN'] + $grup, 1);

        $this->assertSame(1, (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('No_Dokumen'),
            'Nomor urut dokumen dimulai dari 01, bukan 00.');
    }

    public function test_dokumen_berikutnya_di_kombinasi_sama_naik_satu(): void
    {
        $grup = ['Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'), 'Entitas_Pemilik_Id' => $this->entitas('EMI')];

        $this->svc()->buat(['Judul' => 'SOP UJI DERET A', 'Tipe' => 'DOKUMEN'] + $grup, 1);
        $id2 = $this->svc()->buat(['Judul' => 'SOP UJI DERET B', 'Tipe' => 'DOKUMEN'] + $grup, 1);

        $this->assertSame(2, (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id2)->value('No_Dokumen'));
    }

    public function test_kombinasi_berbeda_memulai_deretnya_sendiri(): void
    {
        /* Inilah inti aturannya: yang membedakan deret bukan hanya jenis,
           melainkan kombinasi lima field identitas. */
        $dasar = ['Jenis_Dokumen_Id' => $this->jenis('KEBIJAKAN'), 'Entitas_Pemilik_Id' => $this->entitas('ENB')];

        $this->svc()->buat(['Judul' => 'SOP UJI KOMBINASI A', 'Tipe' => 'DOKUMEN'] + $dasar, 1);

        $lain = ['Jenis_Dokumen_Id' => $this->jenis('KEBIJAKAN'), 'Entitas_Pemilik_Id' => $this->entitas('GMN')];
        $id = $this->svc()->buat(['Judul' => 'SOP UJI KOMBINASI B', 'Tipe' => 'DOKUMEN'] + $lain, 1);

        $this->assertSame(1, (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('No_Dokumen'),
            'Entitas pemilik berbeda berarti kombinasi berbeda, jadi deretnya mulai dari 01 lagi.');
    }

    /**
     * Saran sistem TIDAK PERNAH mengeluarkan nol, di kombinasi mana pun.
     *
     * Penjaga ini menyapu kombinasi yang benar-benar ada di basis data, bukan
     * satu contoh buatan. Cacat aslinya cuma menyentuh kombinasi yang MASIH
     * KOSONG — cabang `$maks === null` — dan kombinasi seperti itu tak muncul
     * di uji yang selalu membuat dokumennya lebih dulu.
     */
    public function test_saran_tidak_pernah_nol(): void
    {
        $kombinasi = DB::table('N_SOP_Dokumen')
            ->select('Jenis_Dokumen_Id', 'Entitas_Pemilik_Id', 'Departemen_Id', 'Entitas_Lingkup_Id', 'APQC_Id')
            ->distinct()->limit(20)->get();

        /* Kombinasi yang dijamin belum berpenghuni: inilah cabang yang dulu
           menjawab nol. */
        $kombinasi->push((object) [
            'Jenis_Dokumen_Id' => 999999, 'Entitas_Pemilik_Id' => 999999,
            'Departemen_Id' => 999999, 'Entitas_Lingkup_Id' => 999999, 'APQC_Id' => 999999,
        ]);

        foreach ($kombinasi as $k) {
            $this->assertGreaterThanOrEqual(1, $this->svc()->saranNoDokumen((array) $k),
                'Nomor urut dokumen dimulai dari 01 — nol milik segmen revisi.');
        }
    }

    public function test_nomor_kustom_dihormati(): void
    {
        // FORMULIR hanya menerima tipe FORM — pasangan itu dijaga penjaga lain.
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI NOMOR KUSTOM',
            'Tipe' => 'FORM',
            'Jenis_Dokumen_Id' => $this->jenis('FORMULIR'),
            'No_Dokumen' => 7,
        ], 1);

        $this->assertSame(7, (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('No_Dokumen'),
            'Penomoran lama yang sudah beredar tak boleh tergerus saran sistem.');
    }

    public function test_nomor_kembar_dalam_kombinasi_sama_ditolak(): void
    {
        $grup = ['Jenis_Dokumen_Id' => $this->jenis('INSTRUKSI_KERJA'), 'Entitas_Pemilik_Id' => $this->entitas('ENB')];

        $this->svc()->buat(['Judul' => 'SOP UJI KEMBAR PERTAMA', 'Tipe' => 'DOKUMEN', 'No_Dokumen' => 5] + $grup, 1);

        $this->expectException(\DomainException::class);

        $this->svc()->buat(['Judul' => 'SOP UJI KEMBAR KEDUA', 'Tipe' => 'DOKUMEN', 'No_Dokumen' => 5] + $grup, 1);
    }

    public function test_nomor_sama_di_kombinasi_berbeda_diperbolehkan(): void
    {
        $a = ['Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'), 'Entitas_Pemilik_Id' => $this->entitas('ENB')];
        $b = ['Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'), 'Entitas_Pemilik_Id' => $this->entitas('EMI')];

        $this->svc()->buat(['Judul' => 'SOP UJI SAMA A', 'Tipe' => 'DOKUMEN', 'No_Dokumen' => 3] + $a, 1);
        $id = $this->svc()->buat(['Judul' => 'SOP UJI SAMA B', 'Tipe' => 'DOKUMEN', 'No_Dokumen' => 3] + $b, 1);

        $this->assertSame(3, (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('No_Dokumen'));
    }

    public function test_saran_menghitung_dokumen_nonaktif_juga(): void
    {
        /* MAX + 1 termasuk yang nonaktif, bukan "nomor bebas terkecil":
           mendaur ulang nomor milik dokumen yang dinonaktifkan membuat dua
           dokumen pernah menyandang nomor yang sama, dan yang sudah tercetak
           di luar sistem tidak ikut berubah. */
        $grup = ['Jenis_Dokumen_Id' => $this->jenis('FORMULIR'), 'Entitas_Pemilik_Id' => $this->entitas('GMN')];

        $id = $this->svc()->buat(['Judul' => 'SOP UJI NONAKTIF', 'Tipe' => 'FORM', 'No_Dokumen' => 4] + $grup, 1);
        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->update(['Flag_Aktif' => 'T']);

        $this->assertSame(5, $this->svc()->saranNoDokumen($grup),
            'Nomor milik dokumen nonaktif tidak boleh didaur ulang.');
    }

    public function test_grup_belum_diklasifikasi_menemukan_anggotanya_sendiri(): void
    {
        /* whereNull, bukan where(=, null): perbandingan dengan NULL di SQL tak
           pernah bernilai benar, sehingga grup yang seluruh fieldnya kosong tak
           akan pernah menemukan anggotanya. */
        $sebelum = $this->svc()->saranNoDokumen([]);

        $this->svc()->buat(['Judul' => 'SOP UJI GRUP KOSONG', 'Tipe' => 'DOKUMEN'], 1);

        $this->assertSame($sebelum + 1, $this->svc()->saranNoDokumen([]));
    }

    public function test_mengubah_kombinasi_memeriksa_kembar_di_kombinasi_baru(): void
    {
        $a = ['Jenis_Dokumen_Id' => $this->jenis('KEBIJAKAN'), 'Entitas_Pemilik_Id' => $this->entitas('ENB')];
        $b = ['Jenis_Dokumen_Id' => $this->jenis('KEBIJAKAN'), 'Entitas_Pemilik_Id' => $this->entitas('EMI')];

        $this->svc()->buat(['Judul' => 'SOP UJI PINDAH TUJUAN', 'Tipe' => 'DOKUMEN', 'No_Dokumen' => 9] + $b, 1);
        $id = $this->svc()->buat(['Judul' => 'SOP UJI PINDAH ASAL', 'Tipe' => 'DOKUMEN', 'No_Dokumen' => 9] + $a, 1);

        // Memindahkannya ke kombinasi B membuat nomor 9 bentrok di sana.
        $this->expectException(\DomainException::class);

        $this->svc()->ubah($id, [
            'Judul' => 'SOP UJI PINDAH ASAL',
            'Tipe' => 'DOKUMEN',
            'No_Dokumen' => 9,
        ] + $b, 1);
    }

    public function test_nomor_tidak_ikut_naik_saat_versi_baru_terbit(): void
    {
        $id = $this->svc()->buat([
            'Judul' => 'SOP UJI NOMOR TETAP',
            'Tipe' => 'DOKUMEN',
            'Jenis_Dokumen_Id' => $this->jenis('PROSEDUR'),
            'No_Dokumen' => 2,
        ], 1);

        $this->svc()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);

        $this->assertSame(2, (int) DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('No_Dokumen'),
            'Nomor dokumen melekat di master; siklus versi berjalan sendiri.');
    }
}
