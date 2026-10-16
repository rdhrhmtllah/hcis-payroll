<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopJangkauanPublikService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jangkauan dokumen publik — lapis analisis tab Publik.
 *
 * YANG DIJAGA: lapis ini mengukur MINAT, bukan kepatuhan, dan sumbernya adalah
 * penugasan ber-Sumber 'MANDIRI'.
 *
 * N_SOP_Progress.Id_SOP_Penjadwalan_Detail NOT NULL, sehingga tak ada tempat
 * menyimpan jejak baca tanpa penugasan. Karyawan yang membuka SOP publik
 * karena itu didaftarkan sendiri oleh sistem (SopPendaftaranMandiri). Baris
 * MANDIRI itulah satu-satunya bukti minat sukarela yang dimiliki sistem —
 * kalau ia ikut terhitung sebagai kewajiban, kedua lapis dashboard sama-sama
 * salah.
 */
class SopJangkauanPublikTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopJangkauanPublikService
    {
        return app(SopJangkauanPublikService::class);
    }

    private function dokumenBaru(string $judul): int
    {
        return app(SopDokumenService::class)->buat([
            'Judul' => $judul.' '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);
    }

    /** Paket PUBLIK terbit berisi satu dokumen. */
    private function paketPublik(int $idDokumen): array
    {

        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'PAKET PUBLIK UJI '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Mode_Distribusi' => 'PUBLIK',
        ], 'Id_SOP_Paket');

        $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item uji',
            'Id_SOP_Dokumen' => $idDokumen,
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');

        return ['paket' => $idPaket, 'item' => $idItem];
    }

    /** Seorang karyawan mendaftar sendiri; $membuka menentukan apakah ia benar-benar membaca. */
    private function pembacaMandiri(array $p, string $kode, bool $membuka): void
    {
        $idJadwal = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $p['paket'],
            'Sumber' => 'MANDIRI',
            'Created_By' => null,
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Penjadwalan');

        $idDetail = DB::table('N_SOP_Penjadwalan_Detail')->insertGetId([
            'Id_SOP_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => $kode,
            'Status_Paket' => SopStatus::STATUS_PROGRESS,
            'Flag_Cabut_Akses' => 'T',
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Penjadwalan_Detail');

        DB::table('N_SOP_Progress')->insert([
            'Id_SOP_Penjadwalan_Detail' => $idDetail,
            'Id_SOP_Paket_Item' => $p['item'],
            'Kode_Karyawan' => $kode,
            'Tipe_Item' => 'DOKUMEN',
            'Status' => $membuka ? 'DIBUKA' : 'BELUM',
            'Durasi_Aktif_Detik' => 0,
            'Jumlah_Percobaan' => 0,
            /* Yang MEMBUKA, bukan sekadar terdaftar: pendaftaran mandiri
               terjadi pada percobaan akses pertama, jadi baris tanpa Waktu_Buka
               adalah orang yang mengetuk pintu lalu berbalik. */
            'Waktu_Buka' => $membuka ? now()->subDays(3) : null,
        ]);
    }

    private function duaKaryawan(): array
    {
        $kode = DB::table('Karyawan')->where('Aktif', SopStatus::AKTIF)
            ->limit(2)->pluck('Kode_Karyawan')->all();
        if (count($kode) < 2) {
            $this->markTestSkipped('Butuh minimal dua karyawan aktif.');
        }

        return $kode;
    }

    /* ── Jangkauan ──────────────────────────────────────────────────────── */

    public function test_menghitung_pembuka_bukan_sekadar_terdaftar(): void
    {
        [$a, $b] = $this->duaKaryawan();
        $p = $this->paketPublik($this->dokumenBaru('SOP PUBLIK JANGKAUAN'));
        $this->pembacaMandiri($p, $a, true);
        $this->pembacaMandiri($p, $b, false);

        $j = $this->svc()->jangkauan()['Jangkauan'];

        $this->assertGreaterThanOrEqual(2, $j['Tersentuh']);
        $this->assertGreaterThanOrEqual(1, $j['Membuka']);
        $this->assertLessThan($j['Tersentuh'], $j['Membuka'] + 1,
            'Yang membuka tak boleh melebihi yang tersentuh.');
    }

    public function test_penyebutnya_seluruh_karyawan_aktif(): void
    {
        /* "Publik" berarti tak ada yang dikecualikan dari haknya melihat.
           Penyebut yang lebih sempit membuat jangkauan tampak lebih baik
           daripada kenyataannya. */
        $aktif = DB::table('Karyawan')->where('Aktif', SopStatus::AKTIF)->count();

        $this->assertSame($aktif, $this->svc()->jangkauan()['Jangkauan']['Karyawan_Aktif']);
    }

    /* ── Terpopuler & diabaikan ─────────────────────────────────────────── */

    public function test_dokumen_tanpa_pembuka_masuk_daftar_diabaikan(): void
    {
        /* Temuan paling berguna di tab ini: dokumen yang sengaja dibuka untuk
           semua orang, lalu tak seorang pun membacanya. Tak ada mekanisme lain
           di sistem yang memunculkannya. */
        $id = $this->dokumenBaru('SOP PUBLIK TAK DISENTUH');
        $this->paketPublik($id);

        $judul = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Judul');
        $diabaikan = collect($this->svc()->jangkauan()['Diabaikan'])->pluck('Judul');

        $this->assertContains($judul, $diabaikan->all());
    }

    public function test_dokumen_yang_dibaca_keluar_dari_daftar_diabaikan(): void
    {
        [$a] = $this->duaKaryawan();
        $id = $this->dokumenBaru('SOP PUBLIK DIBACA');
        $this->pembacaMandiri($this->paketPublik($id), $a, true);

        $judul = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Judul');
        $j = $this->svc()->jangkauan();

        $this->assertNotContains($judul, collect($j['Diabaikan'])->pluck('Judul')->all());
        $this->assertContains($judul, collect($j['Terpopuler'])->pluck('Judul')->all());
    }

    public function test_paket_terbatas_tidak_ikut_dihitung(): void
    {
        /* Lapis ini khusus dokumen PUBLIK. Paket TERBATAS punya lapisnya
           sendiri, dan mencampurnya membuat kedua angka salah sekaligus. */
        $id = $this->dokumenBaru('SOP TERBATAS BUKAN PUBLIK');
        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'PAKET TERBATAS UJI '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Mode_Distribusi' => 'TERBATAS',
        ], 'Id_SOP_Paket');
        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => 'Item uji',
            'Id_SOP_Dokumen' => $id,
            'Flag_Aktif' => SopStatus::AKTIF,
        ]);

        $judul = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)->value('Judul');

        $this->assertNotContains($judul,
            collect($this->svc()->jangkauan()['Diabaikan'])->pluck('Judul')->all());
    }

    /* ── Bentuk payload ─────────────────────────────────────────────────── */

    public function test_membalas_seluruh_bagian_yang_digambar_layar(): void
    {
        /* Kunci yang berganti nama tanpa layar ikut berubah tak menimbulkan
           galat apa pun — panelnya cuma menampilkan nol. */
        $j = $this->svc()->jangkauan();

        foreach (['Jangkauan', 'Terpopuler', 'Diabaikan', 'Tren', 'Unit'] as $k) {
            $this->assertArrayHasKey($k, $j);
        }
        foreach (['Karyawan_Aktif', 'Tersentuh', 'Membuka', 'Persen', 'Dokumen_Publik'] as $k) {
            $this->assertArrayHasKey($k, $j['Jangkauan']);
        }
    }
}
