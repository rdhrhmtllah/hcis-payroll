<?php

namespace Tests\Feature\LMS\SOP;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Gerbang skema Fase 2 Master Dokumen SOP.
 *
 * DDL modul SOP dijalankan MANUAL di SSMS (tak ada migration Laravel), jadi
 * test ini adalah satu-satunya hal yang membuktikan 26B benar-benar terpasang
 * sebelum kode mengandalkannya.
 */
class SopDokumenFase2Test extends TestCase
{
    use DatabaseTransactions;

    private function index(string $nama): ?object
    {
        return DB::selectOne("
            SELECT i.name, i.is_unique, i.has_filter, i.filter_definition
            FROM sys.indexes i
            WHERE i.object_id = OBJECT_ID('N_SOP_Item_Versi') AND i.name = ?", [$nama]);
    }

    /**
     * SUMBUNYA SUDAH BERGESER — DDL-2026-062.
     *
     * Sampai 30 Agu 2026 penjaganya bernama UX_N_SOP_Item_Versi_Dokumen_Urut
     * dan terfilter hanya pada `Id_SOP_Dokumen IS NOT NULL`. Artinya: nomor
     * versi unik di antara SELURUH versi sebuah dokumen, hidup maupun mati.
     *
     * DDL-2026-062 menggantinya dengan varian `_Hidup` yang menambahkan
     * `Flag_Aktif = 'Y'` ke filternya, karena draft yang DIBATALKAN ikut
     * mengunci nomornya selamanya: v01 dibatalkan berarti versi berikutnya
     * terpaksa v02, dan nomor resmi dokumen melompat dari …/00 ke …/02 sambil
     * menyebut "Revisi 01" yang tak pernah diterbitkan kepada siapa pun.
     *
     * Uji ini karena itu menagih indeks yang BENAR-BENAR berlaku sekarang;
     * menagih nama lama berarti menuntut aturan yang sengaja dicabut.
     */
    public function test_unique_index_dokumen_versi_terpasang_dan_terfilter(): void
    {
        $ix = $this->index('UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup');

        $this->assertNotNull($ix,
            'Jalankan database/ddl/DDL-2026-062-versi-unik-hanya-yang-hidup/forward.sql di SSMS lebih dulu.');
        $this->assertSame(1, (int) $ix->is_unique, 'Penjaga nomor versi harus UNIK.');
        $this->assertSame(1, (int) $ix->has_filter,
            'WAJIB terfilter: SQL Server menganggap NULL setara di unique index, jadi '
            .'baris (NULL, 1) yang kedua akan ditolak dengan error 2601.');
        $this->assertStringContainsString('Id_SOP_Dokumen', (string) $ix->filter_definition);
        $this->assertStringContainsString('Flag_Aktif', (string) $ix->filter_definition,
            'Tanpa Flag_Aktif di filternya, draft yang dibatalkan tetap mengunci nomornya '
            .'selamanya — persis keadaan yang dicabut DDL-2026-062.');
    }

    /**
     * KONTRAK DIBALIK OLEH DDL 28 — dan penundaannya dulu memang benar.
     *
     * Versi lama test ini menegaskan UX_N_SOP_Item_Versi_Urut dipertahankan,
     * karena ia satu-satunya pagar bagi baris ber-dokumen NULL. Alasan itu sah
     * selama versi WAJIB menempel pada item.
     *
     * Sejak Master SOP mengurus SOP utuh, versi boleh milik dokumen saja
     * (Id_SOP_Paket_Item NULL). Index itu UNIQUE (Id_SOP_Paket_Item, Versi_Ke)
     * dan SQL Server menganggap NULL SETARA — sehingga SOP KEDUA yang punya
     * versi tanpa item ditolak error 2601. Dari pagar, ia berubah jadi
     * penghalang; karena itu dibuang, dan sumbunya pindah ke
     * UX_N_SOP_Item_Versi_Dokumen_Urut yang terfilter.
     */
    public function test_index_lama_per_item_sudah_dibuang(): void
    {
        $this->assertNull($this->index('UX_N_SOP_Item_Versi_Urut'),
            'UX_N_SOP_Item_Versi_Urut wajib sudah dibuang (DDL 28). Selama ia ada, SOP kedua '
            .'yang punya versi tanpa item akan ditolak error 2601 karena SQL Server menganggap '
            .'NULL setara di unique index — fitur Master Dokumen mustahil dipakai lebih dari sekali.');

        $this->assertNotNull($this->index('UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup'),
            'Penggantinya wajib ada: keunikan versi kini bersumbu dokumen, dan sejak '
            .'DDL-2026-062 hanya di antara versi yang HIDUP.');

        /* Yang lama pun wajib SUDAH TURUN, bukan sekadar "boleh ada". Selama
           keduanya terpasang bersama, yang lama tetap menolak nomor versi yang
           dilepaskan oleh pembatalan draft — sehingga DDL-2026-062 terpasang
           separuh dan gejalanya persis sama seperti tak dijalankan sama sekali. */
        $this->assertNull($this->index('UX_N_SOP_Item_Versi_Dokumen_Urut'),
            'Indeks lama masih terpasang berdampingan; bagian 1 DDL-2026-062 baru '
            .'membuat penggantinya dan belum menjatuhkan yang lama.');
    }

    /**
     * INVARIAN YANG BENAR-BENAR BERLAKU: unik di antara versi HIDUP.
     *
     * Versi lama uji ini menuntut keunikan (dokumen, versi) tanpa menyaring
     * Flag_Aktif. Sejak DDL-2026-062 tuntutan itu keliru menurut rancangan,
     * bukan menurut kebetulan data: draft yang dibatalkan (Flag_Aktif='T')
     * MEMANG melepaskan nomornya untuk dipakai ulang, jadi satu dokumen yang
     * pernah dua kali membatalkan v01 akan punya tiga baris bernomor 1 dan
     * hanya satu di antaranya hidup. Per 1 Sep 2026 keadaan itu sudah nyata di
     * basis data dev pada dokumen 17053 dan 17054.
     *
     * Saringan Flag_Aktif di bawah karena itu bukan pelonggaran assertion,
     * melainkan penyalinan predikat indeksnya secara harfiah.
     */
    public function test_pasangan_dokumen_versi_unik_di_antara_versi_hidup(): void
    {
        $ganda = DB::select("
            SELECT Id_SOP_Dokumen, Versi_Ke, COUNT(*) c
            FROM   N_SOP_Item_Versi
            WHERE  Id_SOP_Dokumen IS NOT NULL AND Flag_Aktif = 'Y'
            GROUP BY Id_SOP_Dokumen, Versi_Ke
            HAVING COUNT(*) > 1");

        $this->assertCount(0, $ganda,
            'Ada nomor versi kembar di antara versi HIDUP — keadaan yang ditolak '
            .'UX_N_SOP_Item_Versi_Dokumen_Urut_Hidup, jadi barisnya lahir sebelum '
            .'indeksnya terpasang dan harus dibereskan.');

        /* PENJAGA KERJA-NYATA. Kueri di atas menghasilkan himpunan kosong juga
           ketika tak ada satu pun versi bertaut dokumen — hijau sempurna tanpa
           memeriksa apa pun. Yang diperiksa bukan hanya "tak ada kembar",
           melainkan "ada yang bisa kembar dan tetap tidak kembar". */
        $bertaut = DB::table('N_SOP_Item_Versi')
            ->whereNotNull('Id_SOP_Dokumen')->where('Flag_Aktif', 'Y')->count();

        $this->assertGreaterThan(0, $bertaut,
            'Tak ada satu pun versi hidup yang bertaut dokumen — uji ini tak membuktikan apa pun.');
    }

    /**
     * Sisi sebaliknya, dan justru inilah yang dibeli DDL-2026-062: nomor versi
     * yang kembar karena SALAH SATUNYA sudah dibatalkan bukan cacat data,
     * melainkan hasil yang dituju. Menuntutnya nol lagi berarti mengembalikan
     * aturan yang sengaja dicabut.
     */
    public function test_versi_dibatalkan_boleh_berbagi_nomor_dengan_versi_hidup(): void
    {
        /* Ditanyakan ke sys.indexes, bukan ke nama indeksnya, supaya penjaga ini
           tetap menyala kalau seseorang membangkitkan aturan lama dengan nama
           baru. Yang dicari: unique index atas (Id_SOP_Dokumen, Versi_Ke) yang
           predikatnya TIDAK menyebut Flag_Aktif. */
        $terlalu_ketat = DB::select("
            SELECT i.name, i.filter_definition
            FROM   sys.indexes i
            WHERE  i.object_id = OBJECT_ID('N_SOP_Item_Versi')
              AND  i.is_unique = 1
              AND  (i.filter_definition IS NULL OR i.filter_definition NOT LIKE '%Flag_Aktif%')
              AND  EXISTS (SELECT 1 FROM sys.index_columns ic JOIN sys.columns c
                             ON c.object_id = ic.object_id AND c.column_id = ic.column_id
                          WHERE ic.object_id = i.object_id AND ic.index_id = i.index_id
                            AND ic.key_ordinal > 0 AND c.name = 'Versi_Ke')");

        $this->assertCount(0, $terlalu_ketat,
            'Ada unique index atas Versi_Ke yang tak menyaring Flag_Aktif. Ia mengunci '
            .'nomor milik draft yang DIBATALKAN, sehingga v01 yang batal memaksa versi '
            .'berikutnya jadi v02 dan nomor resmi melompat ke "Revisi 01" yang tak pernah '
            .'terbit — keadaan yang sengaja dicabut DDL-2026-062.');
    }
}
