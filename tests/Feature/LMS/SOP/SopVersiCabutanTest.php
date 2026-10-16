<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\SopSecurePdfAccessService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Versi yang sudah dicabut: riwayatnya tetap, isinya tidak.
 *
 * Keputusan produk 2026-08-12. Dokumen terkendali yang sudah digantikan disimpan
 * supaya bisa DITELUSURI, bukan supaya bisa DIPAKAI — berkas versi lama yang
 * tetap bisa diunduh adalah cara paling mudah seseorang mengerjakan prosedur
 * yang sudah tak berlaku, dan mencegah itu justru alasan seluruh mesin dokumen
 * terkendali ini ada.
 *
 * Yang TETAP terlihat karyawan: nomor versi, tanggal berlaku, pengesah,
 * ringkasan perubahan, dan status bacanya sendiri. Yang hilang hanya isinya.
 *
 * Aturannya harus berdiri di SEMUA pintu menuju isi — endpoint konten dan
 * pembaca PDF aman — bukan di tombolnya saja. Menyembunyikan tombol hanya
 * memindahkan pintunya ke tempat yang tak terlihat.
 */
class SopVersiCabutanTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Penugasan aktif atas item ber-dokumen yang dokumennya sudah pernah direvisi.
     *
     * $syarat menyaring lebih jauh, dan itu bukan kemewahan: sebagian uji di
     * bawah butuh paket yang tak mengunci presensi atau versi cabutan yang
     * berkasnya berbeda. Tanpa penyaringan, kandidat PERTAMA yang tak memenuhi
     * syarat membuat ujinya dilewati — dan uji yang dilewati terbaca sebagai
     * uji yang lulus.
     */
    private function skenarioAtauSkip(?callable $syarat = null): object
    {
        $baris = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->whereNotNull('i.Id_Item_Versi_Aktif')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->get(['i.Id_SOP_Paket', 'i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen',
                'i.Id_Item_Versi_Aktif', 'd.Kode_Karyawan', 'u.Id_Users as user_id']);

        foreach ($baris as $b) {
            $semuaCabutan = DB::table('N_SOP_Item_Versi')
                ->where('Id_SOP_Dokumen', $b->Id_SOP_Dokumen)
                ->where('Id_SOP_Item_Versi', '!=', $b->Id_Item_Versi_Aktif)
                ->where('Status', 'OBSOLETE')
                ->get(['Id_SOP_Item_Versi', 'Id_Master_Konten']);

            foreach ($semuaCabutan as $cabutan) {
                $b->cabutan = $cabutan;
                if ($syarat === null || $syarat($b)) {
                    return $b;
                }
            }
        }

        $this->markTestSkipped('Tak ada penugasan atas dokumen yang punya versi cabutan yang sesuai.');
    }

    /** Paket yang tak menutup lebih dulu dengan 423, supaya aturan yang diuji sempat jalan. */
    private function tanpaGerbangPresensi(): callable
    {
        return fn ($b) => ! app(\App\Services\LMS\SOP\SopAbsensiService::class)
            ->terkunci($b->Kode_Karyawan, (int) $b->Id_SOP_Paket);
    }

    /* ── pintu 1: keputusannya sendiri ─────────────────────────────── */

    public function test_versi_cabutan_bukan_versi_yang_berlaku(): void
    {
        $s = $this->skenarioAtauSkip();
        $read = app(SopProgressReadService::class);

        $this->assertTrue(
            $read->versiBerlakuDiPaket((int) $s->Id_SOP_Paket, (int) $s->Id_Item_Versi_Aktif),
            'Versi aktif tak dikenali berlaku — seluruh dokumennya jadi tak bisa dibuka.',
        );
        $this->assertFalse(
            $read->versiBerlakuDiPaket((int) $s->Id_SOP_Paket, (int) $s->cabutan->Id_SOP_Item_Versi),
            'Versi cabutan masih dihitung berlaku.',
        );
    }

    /**
     * Dan keterlihatannya TIDAK ikut dicabut.
     *
     * Kalau versiVisibleInPaket ikut menolak, yang hilang bukan cuma tombolnya
     * melainkan barisnya — dan karyawan kehilangan bukti bahwa ia pernah membaca
     * revisi sebelumnya. Dua pertanyaan berbeda, dan hanya satu yang jawabannya
     * berubah.
     */
    public function test_riwayatnya_tetap_terlihat(): void
    {
        $s = $this->skenarioAtauSkip();

        $this->assertTrue(
            app(SopProgressReadService::class)
                ->versiVisibleInPaket($s->Kode_Karyawan, (int) $s->Id_SOP_Paket, (int) $s->cabutan->Id_SOP_Item_Versi),
            'Versi cabutan ikut hilang dari riwayat — bukti pernah membaca ikut terhapus.',
        );
    }

    /* ── pintu 2: payload riwayat ──────────────────────────────────── */

    public function test_payload_riwayat_menyebut_versi_cabutan_tapi_tak_menawarkannya(): void
    {
        $s = $this->skenarioAtauSkip();

        $rows = collect(app(SopProgressReadService::class)
            ->itemVersiHistory($s->Kode_Karyawan, (int) $s->Id_SOP_Paket, (int) $s->Id_SOP_Paket_Item));

        $cabutan = $rows->first(fn ($r) => $r['Id_SOP_Item_Versi']
            === Hashids::connection('custom')->encode((int) $s->cabutan->Id_SOP_Item_Versi));

        if (! $cabutan) {
            $this->markTestSkipped('Versi cabutan tak muncul di riwayat item itu.');
        }

        $this->assertFalse($cabutan['Bisa_Dibuka'], 'Riwayat masih menawarkan isi versi cabutan.');
        $this->assertFalse($cabutan['Bisa_Pratinjau'], 'Pratinjau versi cabutan masih ditawarkan.');

        // Yang justru harus tetap ada — kalau ikut hilang, barisnya tak berguna.
        $this->assertArrayHasKey('Status_Baca', $cabutan);
        $this->assertArrayHasKey('Ringkasan_Perubahan', $cabutan);
        $this->assertArrayHasKey('Tanggal_Berlaku', $cabutan);

        $aktif = $rows->first(fn ($r) => $r['Bisa_Dibuka'] === true);
        $this->assertNotNull($aktif, 'Tak ada satu pun versi yang bisa dibuka — dokumennya jadi mati total.');
        $this->assertTrue($aktif['Is_Aktif']);
    }

    /* ── pintu 3: endpoint konten ──────────────────────────────────── */

    public function test_endpoint_konten_menolak_versi_cabutan(): void
    {
        $s = $this->skenarioAtauSkip($this->tanpaGerbangPresensi());

        // APP_URL repo ini tanpa skema; tanpa ini setiap permintaan uji meleset
        // dari route-nya dan berbalas 404. Lihat SopFormBerkasTest.
        URL::forceRootUrl('http://localhost');

        $enc = fn ($id) => Hashids::connection('custom')->encode((int) $id);
        $res = $this->actingAs(User::find($s->user_id))
            ->getJson("/api/v1/sop/{$enc($s->Id_SOP_Paket)}/versi/{$enc($s->cabutan->Id_SOP_Item_Versi)}/konten");

        // 423 = gerbang presensi menutup lebih dulu; aturan yang diuji tak
        // pernah sempat dijalankan, jadi hasilnya tak berarti apa-apa.
        if ($res->status() === 423) {
            $this->markTestSkipped('Paketnya mensyaratkan presensi.');
        }

        $res->assertStatus(410);
    }

    public function test_endpoint_konten_tetap_melayani_versi_yang_berlaku(): void
    {
        $s = $this->skenarioAtauSkip($this->tanpaGerbangPresensi());

        URL::forceRootUrl('http://localhost');

        $enc = fn ($id) => Hashids::connection('custom')->encode((int) $id);
        $res = $this->actingAs(User::find($s->user_id))
            ->getJson("/api/v1/sop/{$enc($s->Id_SOP_Paket)}/versi/{$enc($s->Id_Item_Versi_Aktif)}/konten");

        if ($res->status() === 423) {
            $this->markTestSkipped('Paketnya mensyaratkan presensi.');
        }

        // Penjaga baru tak boleh ikut menutup versi yang justru harus dibaca.
        $res->assertOk();
    }

    /* ── pintu 4: pembaca PDF aman ─────────────────────────────────── */

    /**
     * Pintu terakhir menuju isi dokumen. Menutup endpoint konten saja tak cukup:
     * berkas versi lama tetap terbaca lewat sini oleh siapa pun yang pernah
     * menyimpan id berkasnya.
     */
    public function test_pembaca_pdf_aman_menolak_berkas_versi_cabutan(): void
    {
        /* Berkas yang sama dipakai kedua versi berarti tak ada yang bisa
           dibedakan — dan menolaknya justru akan menutup versi yang berlaku. */
        $s = $this->skenarioAtauSkip(function ($b) {
            if (! $b->cabutan->Id_Master_Konten) {
                return false;
            }

            /* Kontennya tak boleh dipakai versi mana pun yang MASIH BERLAKU —
               di dokumen mana pun, bukan cuma di item ini. canAccess() bekerja
               per BERKAS, jadi satu berkas yang juga menjadi isi versi terbitan
               dokumen lain memang boleh dibaca, dan menolaknya justru menutup
               dokumen yang sah.

               Sebelumnya syaratnya cuma membandingkan dengan versi aktif item
               ini sendiri. Di data dev satu contoh PDF menjadi isi belasan
               dokumen sekaligus, sehingga tes ini terpilih pada berkas yang
               aksesnya memang sah lalu gagal seolah ada kebocoran. */
            $adaYangBerlaku = DB::table('N_SOP_Item_Versi')
                ->where('Id_Master_Konten', $b->cabutan->Id_Master_Konten)
                ->where('Id_SOP_Item_Versi', '!=', $b->cabutan->Id_SOP_Item_Versi)
                ->where('Status', '!=', 'OBSOLETE')
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->exists();

            return ! $adaYangBerlaku;
        });

        $berkasLama = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten', $s->cabutan->Id_Master_Konten)
            ->value('Id_Master_Konten_Berkas');

        if (! $berkasLama) {
            $this->markTestSkipped('Versi cabutannya tak punya berkas.');
        }

        $this->assertFalse(
            app(SopSecurePdfAccessService::class)->canAccess($s->Kode_Karyawan, (int) $berkasLama),
            'Berkas versi cabutan masih terbaca lewat pembaca PDF aman.',
        );
    }
}
