<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopMonitoringPeopleService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Penugasan atas paket PUBLIK: memisahkan kewajiban dari minat.
 *
 * Paket PUBLIK bisa dimiliki seseorang lewat dua jalan yang sama sekali
 * berbeda maknanya, dan keduanya menghasilkan baris yang bentuknya identik di
 * N_SOP_Penjadwalan_Detail:
 *
 *   MANDIRI — karyawan membuka SOP publik, lalu sistem mendaftarkannya sendiri
 *             (SopPendaftaranMandiri). Ini MINAT, bukan kewajiban.
 *   MANUAL / AUTO_RULE — admin (atau aturan) menugaskannya dengan sengaja.
 *             Ini KEWAJIBAN, dan tetap kewajiban walau belum dibuka.
 *
 * Yang membedakan keduanya adalah kolom `Sumber`. Gerbang di
 * terapkanSaringanPaket() dulu memakai proksi "sudah dibuka atau belum" — dan
 * proksi itu benar untuk MANDIRI tetapi salah untuk MANUAL: penugasan yang
 * dibuat admin secara sadar ikut terbuang dari seluruh laporan monitoring.
 * Akibatnya admin menugaskan seseorang, orang itu tak pernah muncul di daftar
 * tunggakan, dan tak ada apa pun yang memberi tahu bahwa penugasannya tidak
 * terpantau.
 *
 * Kedua sisi diuji di sini, karena memperbaiki sisi pertama dengan cara yang
 * merusak sisi kedua hanya menukar satu cacat dengan cacat lain.
 *
 * @see \App\Services\LMS\SOP\SopMonitoringPeopleService::terapkanSaringanPaket()
 */
class SopPenugasanPaketPublikTest extends TestCase
{
    /** Kueri penugasan dengan gerbang paket terpasang — persis seperti summary(). */
    private function detailLolosGerbang(): array
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T');

        app(SopMonitoringPeopleService::class)->terapkanSaringanPaket($q, [], 'p', 'pj', 'd');

        return $q->pluck('d.Id_SOP_Penjadwalan_Detail')->map(fn ($v) => (int) $v)->all();
    }

    /**
     * Baris penugasan pada paket PUBLIK yang cocok dengan kriteria yang diberikan.
     *
     * @param  callable  $ubah  penyempit tambahan
     * @return int[] Id_SOP_Penjadwalan_Detail
     */
    private function detailPaketPublik(callable $ubah): array
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('p.Mode_Distribusi', 'PUBLIK')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T');

        $ubah($q);

        return $q->pluck('d.Id_SOP_Penjadwalan_Detail')->map(fn ($v) => (int) $v)->all();
    }

    /** Belum pernah dibuka sama sekali: status masih BELUM dan tak ada Waktu_Buka. */
    private function belumTersentuh($q): void
    {
        $q->where('d.Status_Paket', SopStatus::STATUS_BELUM)
            ->whereNotExists(fn ($s) => $s->select(DB::raw(1))
                ->from('N_SOP_Progress as pr')
                ->whereColumn('pr.Id_SOP_Penjadwalan_Detail', 'd.Id_SOP_Penjadwalan_Detail')
                ->whereNotNull('pr.Waktu_Buka'));
    }

    /**
     * Penugasan yang dibuat SENGAJA tetap terhitung walau belum dibuka.
     *
     * Inilah cacatnya: seluruh baris ini dulu hilang dari monitoring.
     */
    public function test_penugasan_eksplisit_atas_paket_publik_tetap_terhitung(): void
    {
        $eksplisit = $this->detailPaketPublik(function ($q) {
            $q->whereIn('pj.Sumber', ['MANUAL', 'AUTO_RULE']);
            $this->belumTersentuh($q);
        });

        /* Penjaga kerja-nyata. Tanpa ini, habisnya data uji membuat pernyataan
           "himpunan kosong termuat di mana pun" lulus tanpa menguji apa pun. */
        $this->assertNotEmpty(
            $eksplisit,
            'Tak ada penugasan MANUAL/AUTO_RULE atas paket PUBLIK yang belum dibuka di basis data ini, '
            .'sehingga uji ini tidak membuktikan apa-apa. Sediakan datanya, jangan longgarkan ujinya.'
        );

        $lolos = $this->detailLolosGerbang();
        $hilang = array_values(array_diff($eksplisit, $lolos));

        $this->assertSame([], $hilang, sprintf(
            'Ada %d dari %d penugasan eksplisit atas paket PUBLIK yang tidak terhitung monitoring. '
            .'Penugasan yang dibuat admin adalah kewajiban sejak dibuat, bukan sejak dibuka. Detail: %s',
            count($hilang), count($eksplisit), implode(', ', array_slice($hilang, 0, 10))
        ));
    }

    /**
     * Sisi sebaliknya: pendaftaran MANDIRI yang tak pernah dibuka tetap DI LUAR.
     *
     * Memperbaiki cacat di atas dengan membuka gerbangnya lebar-lebar akan
     * mencampur kembali orang yang sekadar penasaran ke dalam angka kepatuhan.
     * Uji ini yang menahannya.
     */
    public function test_pendaftaran_mandiri_yang_tak_pernah_dibuka_tetap_di_luar(): void
    {
        $mandiri = $this->detailPaketPublik(function ($q) {
            $q->where('pj.Sumber', 'MANDIRI');
            $this->belumTersentuh($q);
        });

        $this->assertNotEmpty(
            $mandiri,
            'Tak ada pendaftaran MANDIRI atas paket PUBLIK yang belum dibuka, sehingga uji ini vakum.'
        );

        $lolos = $this->detailLolosGerbang();
        $bocor = array_values(array_intersect($mandiri, $lolos));

        $this->assertSame([], $bocor, sprintf(
            'Ada %d pendaftaran MANDIRI yang belum pernah dibuka ikut terhitung sebagai kewajiban. '
            .'Gerbangnya terlalu longgar: minat bukan kewajiban. Detail: %s',
            count($bocor), implode(', ', array_slice($bocor, 0, 10))
        ));
    }

    /**
     * Kartu "Tersaring Gerbang Publik" harus menghitung PERSIS yang dibuang gerbang.
     *
     * Aturannya hidup di dua tempat yang bentuk kuerinya berlawanan: gerbang di
     * terapkanSaringanPaket() menyatakan siapa yang MASUK, penghitung di
     * SopDokumenKesehatanService::tersaringGerbangPublik() menyatakan siapa yang
     * KELUAR. Keduanya tak bisa berbagi kode, jadi yang menahannya tetap sepakat
     * hanyalah uji ini.
     *
     * Bila keduanya menyimpang, kartunya melaporkan "N penugasan tersaring"
     * untuk penugasan yang sebenarnya terhitung — justru kebohongan senyap yang
     * menjadi alasan kartu itu dibuat.
     */
    public function test_penghitung_tersaring_sepakat_dengan_gerbangnya(): void
    {
        $semuaPublik = $this->detailPaketPublik(fn ($q) => null);

        $this->assertNotEmpty($semuaPublik, 'Tak ada penugasan pada paket PUBLIK; uji ini vakum.');

        // Yang DIBUANG gerbang = seluruh penugasan publik dikurangi yang lolos.
        $dibuangGerbang = array_values(array_diff($semuaPublik, $this->detailLolosGerbang()));

        // Yang DILAPORKAN kartu sebagai tersaring.
        $dilaporkan = $this->detailPaketPublik(function ($q) {
            $q->whereNotIn('pj.Sumber', ['MANUAL', 'AUTO_RULE']);
            $this->belumTersentuh($q);
        });

        sort($dibuangGerbang);
        sort($dilaporkan);

        $this->assertSame($dibuangGerbang, $dilaporkan,
            'Gerbang paket publik dan kartu "Tersaring Gerbang Publik" tidak lagi bicara tentang '
            .'himpunan yang sama. Salah satunya diubah tanpa yang lain — perbaiki keduanya, '
            .'bukan ujinya.');
    }
}
