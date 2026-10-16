<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopCompletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * simpanHalaman() adalah jalur TERPANAS modul SOP: dipanggil tiap pindah
 * halaman DAN tiap 60 detik untuk SETIAP pembaca yang membuka reader PDF.
 *
 * Bentuk lamanya menempuh resolve() → ensureProgress() → bacaProgressTerkunci()
 * → UPDATE, sekitar enam pernyataan, salah satunya SELECT ber-UPDLOCK/HOLDLOCK.
 * Kunci itu tak pernah bisa bekerja: simpanHalaman() TIDAK berada di dalam
 * transaksi, dan di autocommit SQL Server melepas kuncinya begitu pernyataannya
 * selesai. Jadi yang tersisa hanyalah biayanya — pada tabel terpanas modul ini.
 *
 * PERINGATAN UNTUK PEMBACA BERIKUTNYA. Penjaga jumlah-kueri TIDAK BOLEH berdiri
 * sendiri: panggilan yang menolak di gerbang (ITEM_NOT_FOUND / NOT_ASSIGNED)
 * menerbitkan lebih SEDIKIT kueri lagi dan penjaganya lulus tanpa menguji apa
 * pun. Karena itu tiap pengukuran di sini berpasangan dengan penegasan bahwa
 * nilai balikannya 'OK' DAN barisnya benar-benar berubah.
 */
class SopSimpanHalamanTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Baris progress yang benar-benar dapat dicapai simpanHalaman().
     *
     * Syaratnya mengikuti resolve() apa adanya:
     *  - itemnya aktif, dan versi progress = versi AKTIF item (termasuk kasus
     *    dua-duanya NULL — kunci utama tugas ini);
     *  - penugasannya aktif dan aksesnya belum dicabut;
     *  - penugasan itu PERSIS yang akan dipilih SopPendaftaranMandiri::detailAktif(),
     *    yaitu Id terbesar di antara penugasan aktif orang tsb pada paket yang
     *    sama. Tanpa syarat terakhir ini, layanan menulis ke baris LAIN dan
     *    penjaganya gagal karena fiksturnya, bukan karena kodenya.
     */
    private function progressSiapPakai(?bool $versiNull = null): ?object
    {
        $q = DB::table('N_SOP_Progress as pr')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket_Item', '=', 'pr.Id_SOP_Paket_Item')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'pr.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('i.Flag_Aktif', 'Y')
            ->whereColumn('pj.Id_SOP_Paket', 'i.Id_SOP_Paket')
            ->whereRaw('ISNULL(pr.Id_SOP_Item_Versi, -1) = ISNULL(i.Id_Item_Versi_Aktif, -1)')
            ->where('d.Flag_Aktif', 'Y')
            ->where('d.Flag_Cabut_Akses', 'T')
            ->whereRaw("d.Id_SOP_Penjadwalan_Detail = (
                SELECT MAX(d2.Id_SOP_Penjadwalan_Detail)
                  FROM N_SOP_Penjadwalan_Detail d2
                  JOIN N_SOP_Penjadwalan pj2 ON pj2.Id_SOP_Penjadwalan = d2.Id_SOP_Penjadwalan
                 WHERE pj2.Id_SOP_Paket = pj.Id_SOP_Paket
                   AND d2.Kode_Karyawan = d.Kode_Karyawan
                   AND d2.Flag_Aktif = 'Y'
                   AND d2.Flag_Cabut_Akses = 'T')");

        if ($versiNull === true) {
            $q->whereNull('pr.Id_SOP_Item_Versi');
        } elseif ($versiNull === false) {
            $q->whereNotNull('pr.Id_SOP_Item_Versi');
        }

        return $q->orderBy('pr.Id_SOP_Progress')
            ->first([
                'pr.Id_SOP_Progress',
                'pr.Id_SOP_Paket_Item',
                'pr.Id_SOP_Item_Versi',
                'pr.Halaman_Terakhir',
                'pr.Durasi_Aktif_Detik',
                'd.Kode_Karyawan',
            ]);
    }

    private function barisProgress(int $idProgress): object
    {
        return DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $idProgress)->first();
    }

    /**
     * Tiga kueri: SELECT item, SELECT penugasan aktif, lalu SATU conditional
     * UPDATE. Tak ada lagi SELECT progress dan tak ada lagi SELECT berkunci.
     */
    public function test_simpan_halaman_paling_banyak_tiga_kueri_pada_baris_yang_sudah_ada(): void
    {
        $p = $this->progressSiapPakai();
        if (! $p) {
            $this->markTestSkipped('Tak ada baris N_SOP_Progress yang terjangkau simpanHalaman() di data dev.');
        }

        $halamanAwal = (int) ($p->Halaman_Terakhir ?? 0);
        $durasiAwal = (int) ($p->Durasi_Aktif_Detik ?? 0);
        $halamanBaru = $halamanAwal + 5;

        $pernyataan = 0;
        DB::listen(function () use (&$pernyataan) {
            $pernyataan++;
        });

        $hasil = app(SopCompletionService::class)
            ->simpanHalaman($p->Kode_Karyawan, (int) $p->Id_SOP_Paket_Item, $halamanBaru, 30);

        $terhitung = $pernyataan; // dibekukan: penegasan di bawah ikut terhitung.

        // ── PENJAGA KERJA-NYATA ────────────────────────────────────────────
        // Panggilan yang ditolak di gerbang menerbitkan lebih sedikit kueri
        // lagi; tanpa dua penegasan ini penjaga bentuk di bawah tak berarti.
        $this->assertSame('OK', $hasil, 'simpanHalaman menolak fikstur; pengukuran kueri jadi tak berarti.');

        $sesudah = $this->barisProgress((int) $p->Id_SOP_Progress);
        $this->assertSame(
            $halamanBaru,
            (int) $sesudah->Halaman_Terakhir,
            'Barisnya tidak tersentuh — UPDATE mengenai nol baris (kunci detail/item/versi tak cocok).'
        );
        $this->assertSame(
            $durasiAwal + 30,
            (int) $sesudah->Durasi_Aktif_Detik,
            'Durasi tidak bertambah — UPDATE tidak menyentuh baris yang dimaksud.'
        );

        // ── PENJAGA BENTUK ─────────────────────────────────────────────────
        $this->assertLessThanOrEqual(
            3,
            $terhitung,
            "simpanHalaman menerbitkan {$terhitung} kueri pada jalur baris-sudah-ada. "
            .'Baca-ubah-tulis (resolve → ensureProgress → bacaProgressTerkunci → UPDATE) '
            .'belum diganti conditional update tunggal.'
        );
    }

    /**
     * Halaman MAJU SAJA. Nilainya dihitung di dalam UPDATE atas isi kolom yang
     * sedang tersimpan, bukan atas salinan yang dibaca lebih dulu — itulah yang
     * membuat dua flush beriringan tak bisa saling menimpa dengan nilai basi.
     */
    public function test_halaman_tidak_mundur_dan_durasi_terakumulasi(): void
    {
        $p = $this->progressSiapPakai();
        if (! $p) {
            $this->markTestSkipped('Tak ada baris N_SOP_Progress yang terjangkau simpanHalaman() di data dev.');
        }

        $halamanAwal = (int) ($p->Halaman_Terakhir ?? 0);
        $durasiAwal = (int) ($p->Durasi_Aktif_Detik ?? 0);
        $tinggi = $halamanAwal + 5;
        $rendah = $halamanAwal + 3;

        $svc = app(SopCompletionService::class);
        $kode = $p->Kode_Karyawan;
        $idItem = (int) $p->Id_SOP_Paket_Item;

        $this->assertSame('OK', $svc->simpanHalaman($kode, $idItem, $tinggi, 30));
        $this->assertSame('OK', $svc->simpanHalaman($kode, $idItem, $rendah, 20));

        $baris = $this->barisProgress((int) $p->Id_SOP_Progress);

        $this->assertSame(
            $tinggi,
            (int) $baris->Halaman_Terakhir,
            "Halaman mundur ke {$rendah}. Penanda halaman harus MAX(lama, baru), bukan timpa."
        );
        $this->assertSame(
            $durasiAwal + 50,
            (int) $baris->Durasi_Aktif_Detik,
            'Durasi tidak terakumulasi: dua laporan 30 + 20 detik harus bertambah, bukan saling menimpa.'
        );
    }

    /** Delta dipotong di 600 detik per laporan; nilai negatif diabaikan. */
    public function test_durasi_dipotong_dan_nilai_negatif_diabaikan(): void
    {
        $p = $this->progressSiapPakai();
        if (! $p) {
            $this->markTestSkipped('Tak ada baris N_SOP_Progress yang terjangkau simpanHalaman() di data dev.');
        }

        $durasiAwal = (int) ($p->Durasi_Aktif_Detik ?? 0);
        $halaman = (int) ($p->Halaman_Terakhir ?? 0) + 1;

        $svc = app(SopCompletionService::class);
        $svc->simpanHalaman($p->Kode_Karyawan, (int) $p->Id_SOP_Paket_Item, $halaman, 99999);
        $svc->simpanHalaman($p->Kode_Karyawan, (int) $p->Id_SOP_Paket_Item, $halaman, -50);

        $this->assertSame(
            $durasiAwal + 600,
            (int) $this->barisProgress((int) $p->Id_SOP_Progress)->Durasi_Aktif_Detik,
            'Cap 600 detik / lantai 0 hilang saat baca-ubah-tulis diganti conditional update.'
        );
    }

    /**
     * Id_SOP_Item_Versi boleh NULL, dan `= NULL` tak pernah cocok di SQL.
     * Bila predikatnya tidak menjadi IS NULL, UPDATE-nya mengenai nol baris,
     * jalur pembuatan ditempuh sia-sia, dan penanda halaman diam-diam tak
     * pernah tersimpan untuk item tanpa versi aktif.
     */
    public function test_item_tanpa_versi_aktif_tetap_tersimpan(): void
    {
        $p = $this->progressSiapPakai(true);
        if (! $p) {
            $this->markTestSkipped('Tak ada baris progress ber-Id_SOP_Item_Versi NULL yang terjangkau di data dev.');
        }

        $halamanBaru = (int) ($p->Halaman_Terakhir ?? 0) + 7;

        $this->assertSame('OK', app(SopCompletionService::class)
            ->simpanHalaman($p->Kode_Karyawan, (int) $p->Id_SOP_Paket_Item, $halamanBaru, 10));

        $baris = $this->barisProgress((int) $p->Id_SOP_Progress);

        $this->assertSame(
            $halamanBaru,
            (int) $baris->Halaman_Terakhir,
            'Baris ber-versi NULL tak tersentuh: predikat versi kemungkinan memakai "= NULL", bukan IS NULL.'
        );
        $this->assertNull(
            DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', DB::table('N_SOP_Progress')
                    ->where('Id_SOP_Progress', $p->Id_SOP_Progress)
                    ->value('Id_SOP_Penjadwalan_Detail'))
                ->where('Id_SOP_Paket_Item', $p->Id_SOP_Paket_Item)
                ->whereNull('Id_SOP_Item_Versi')
                ->where('Id_SOP_Progress', '<>', $p->Id_SOP_Progress)
                ->value('Id_SOP_Progress'),
            'Baris progress kembar terbentuk: jalur pembuatan ditempuh padahal barisnya sudah ada.'
        );
    }
}
