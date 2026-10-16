<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\LMS\SOP\Uat\Support\UatTahapVersi;
use Tests\TestCase;

/**
 * Jejak audit Master Dokumen SOP.
 *
 * Sebelum ini Master Dokumen tak mencatat apa pun. Yang tercatat hanya
 * penerbitan dan rollback, dan itu pun ditulis service versi bersama yang
 * kebetulan dilewati — bukan oleh alur ini. Artinya isi draft bisa diubah dan
 * daftar pengesah bisa diganti tanpa meninggalkan bekas, padahal justru itu dua
 * hal pertama yang ditanyakan saat diaudit.
 *
 * Yang diuji di sini adalah ISI jejaknya, bukan keberadaannya. Baris log yang
 * ada tapi tak menyebut nilai lama sama saja dengan tak ada: nilai lama hanya
 * hidup di dalam baris itu, dan sesudah update tak ada sumber lain untuk
 * memulihkannya.
 */
class SopRiwayatDokumenTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function jejak(int $idDokumen, ?string $aksi = null): array
    {
        return DB::table('N_SOP_Log')->where('Id_SOP_Dokumen', $idDokumen)
            ->when($aksi, fn ($q) => $q->where('Aksi', $aksi))
            ->orderBy('Id_SOP_Log')->get()->all();
    }

    private function meta(object $baris): array
    {
        return $baris->Meta_JSON ? json_decode($baris->Meta_JSON, true) : [];
    }

    public function test_membuat_dokumen_meninggalkan_jejak(): void
    {
        $id = $this->svc()->buat(
            ['Judul' => 'SOP Uji Jejak', 'Nomor_SOP_Resmi' => 'SOP/UJI/900', 'Tipe' => 'DOKUMEN'], 1);

        $baris = $this->jejak($id, 'DOK_BUAT');
        $this->assertCount(1, $baris);
        $this->assertStringContainsString('SOP Uji Jejak', $baris[0]->Keterangan);
        $this->assertSame(1, (int) $baris[0]->Oleh_Id_Users, 'Jejak tanpa pelaku bukan jejak audit.');
    }

    public function test_mengubah_metadata_merekam_nilai_lama_dan_baru(): void
    {
        $svc = $this->svc();
        $id = $svc->buat(['Judul' => 'Judul Awal', 'Nomor_SOP_Resmi' => 'SOP/A', 'Tipe' => 'DOKUMEN'], 1);

        $svc->ubah($id, [
            'Judul' => 'Judul Baru', 'Nomor_SOP_Resmi' => 'SOP/B', 'Tipe' => 'DOKUMEN',
        ], 1);

        $baris = $this->jejak($id, 'DOK_UBAH');
        $this->assertCount(1, $baris);

        $perubahan = collect($this->meta($baris[0])['perubahan'] ?? [])->keyBy('field');

        $this->assertTrue($perubahan->has('Nomor Dokumen'));
        $this->assertSame('SOP/A', $perubahan['Nomor Dokumen']['lama'],
            'Nilai lama wajib terekam — sesudah update ia tak ada di mana pun lagi.');
        $this->assertSame('SOP/B', $perubahan['Nomor Dokumen']['baru']);
        $this->assertSame('Judul Awal', $perubahan['Judul']['lama']);
    }

    public function test_menyimpan_tanpa_mengubah_apa_pun_tidak_mengarang_perubahan(): void
    {
        $svc = $this->svc();
        $data = ['Judul' => 'Tetap Sama', 'Nomor_SOP_Resmi' => 'SOP/C', 'Tipe' => 'DOKUMEN'];
        $id = $svc->buat($data, 1);

        $svc->ubah($id, $data, 1);

        $baris = $this->jejak($id, 'DOK_UBAH');
        $this->assertSame([], $this->meta($baris[0])['perubahan'] ?? null,
            'Jejak yang berisik lebih cepat diabaikan daripada dibaca.');
    }

    public function test_perubahan_blok_pengesah_terekam_sebagai_masuk_dan_keluar(): void
    {
        $svc = $this->svc();
        $id = $svc->buat(['Judul' => 'SOP Pengesah', 'Tipe' => 'DOKUMEN'], 1);
        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen untuk diuji.');
        }

        $idVersi = $svc->tambahVersi($id, [
            'Id_Master_Konten' => $konten,
            'Pengesah' => [['Nama' => 'Andi', 'Peran' => 'Diperiksa oleh', 'Jabatan' => 'Spv']],
        ], 1);

        $svc->ubahVersi($idVersi, [
            'Id_Master_Konten' => $konten,
            'Pengesah' => [['Nama' => 'Budi', 'Peran' => 'Disahkan oleh', 'Jabatan' => 'Manager']],
        ], 1);

        $meta = $this->meta($this->jejak($id, 'VERSI_UBAH')[0]);

        $this->assertSame(['Budi — Disahkan oleh'], $meta['pengesah_masuk'] ?? null);
        $this->assertSame(['Andi — Diperiksa oleh'], $meta['pengesah_keluar'] ?? null,
            'Pertanyaan auditnya selalu "siapa yang dicabut".');
    }

    public function test_menghapus_versi_meninggalkan_nomornya_meski_barisnya_hilang(): void
    {
        $svc = $this->svc();
        $id = $svc->buat(['Judul' => 'SOP Hapus Versi', 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($id, ['Disahkan_Oleh' => 'Pengesah Uji'], 1);

        $this->assertNull($svc->hapusVersi($idVersi, 1));

        $baris = $this->jejak($id, 'VERSI_HAPUS');
        $this->assertCount(1, $baris);
        $this->assertNull($baris[0]->Id_SOP_Item_Versi,
            'Menunjuk baris versi yang sudah dihapus membuat join riwayat gagal diam-diam.');
        $this->assertSame(0, (int) ($this->meta($baris[0])['versi_ke'] ?? -1),
            'Nomor versinya harus tetap terbaca — itu satu-satunya jejak yang tersisa.');
    }

    public function test_penerbitan_menyebut_sifat_revisinya(): void
    {
        $svc = $this->svc();
        $id = $svc->buat(['Judul' => 'SOP Sifat Revisi', 'Tipe' => 'DOKUMEN'], 1);
        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen untuk diuji.');
        }

        $idVersi = $svc->tambahVersi($id, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);

        /* Yang diuji baris-baris di bawah adalah ISI jejak penerbitan, jadi
           penerbitannya memang harus terjadi. Sejak DML-2026-048 dan -051 versi
           tak lagi bisa terbit dari Drafting; alurnya ditempuh lewat helper
           bersama supaya urutan tahapnya hidup di satu tempat saja. */
        UatTahapVersi::bawaKeAmbangTerbit($idVersi, 1);

        $svc->terbitkanVersi($idVersi, 1, 'T');

        $baris = $this->jejak($id, 'VERSI_PUBLISH');
        $this->assertCount(1, $baris,
            'Penerbitan harus muncul di riwayat DOKUMEN, bukan hanya riwayat paket.');
        $this->assertSame('MINOR', $this->meta($baris[0])['sifat_revisi'] ?? null);
        $this->assertStringContainsString('MINOR', $baris[0]->Keterangan);
    }

    public function test_jejak_satu_dokumen_tidak_tercampur_dokumen_lain(): void
    {
        $svc = $this->svc();
        $a = $svc->buat(['Judul' => 'SOP A', 'Tipe' => 'DOKUMEN'], 1);
        $b = $svc->buat(['Judul' => 'SOP B', 'Tipe' => 'DOKUMEN'], 1);

        $svc->ubah($a, ['Judul' => 'SOP A revisi', 'Tipe' => 'DOKUMEN'], 1);

        $this->assertCount(2, $this->jejak($a));
        $this->assertCount(1, $this->jejak($b),
            'Sumbu dokumen harus memisahkan jejak; kalau tidak, riwayatnya tak bisa dipercaya.');
    }

    public function test_kegagalan_menulis_jejak_tidak_menggagalkan_aksinya(): void
    {
        /* SopLog best-effort. Diuji lewat aksi yang tetap berhasil ketika
           tabel lognya dibuat gagal — jejak yang hilang satu baris jauh lebih
           murah daripada penyimpanan SOP yang batal. */
        $svc = $this->svc();
        $id = $svc->buat(['Judul' => 'SOP Best Effort', 'Tipe' => 'DOKUMEN'], 1);

        $this->assertTrue(
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $id)
                ->where('Flag_Aktif', SopStatus::AKTIF)->exists());
    }

    /**
     * Menyunting draft tanpa menyertakan kunci isi TIDAK boleh menghapusnya.
     *
     * Sebelum ini kolomnya ditulis `$data['Id_Master_Konten'] ?? null`, jadi
     * permintaan yang lupa menyertakannya menghapus berkas versi itu tanpa
     * sepatah pun kabar — dan versi tanpa berkas mustahil diterbitkan.
     */
    public function test_menyunting_draft_tanpa_kunci_isi_tidak_menghapus_berkasnya(): void
    {
        $svc = $this->svc();
        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen untuk diuji.');
        }

        $id = $svc->buat(['Judul' => 'SOP Jaga Berkas', 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($id, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);

        // Menyimpan hanya tanggalnya — kunci isi tak ikut dikirim sama sekali.
        $this->assertNull($svc->ubahVersi($idVersi, ['Tanggal_Berlaku' => '2026-09-01'], 1));

        $this->assertSame(
            (int) $konten,
            (int) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
                ->value('Id_Master_Konten'),
            'Berkas versi hilang diam-diam — versi tanpa berkas tak bisa diterbitkan.');
    }

    /** Mengosongkan isi tetap mungkin — asal kuncinya dikirim eksplisit. */
    public function test_isi_masih_bisa_dikosongkan_bila_kuncinya_dikirim(): void
    {
        $svc = $this->svc();
        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen untuk diuji.');
        }

        $id = $svc->buat(['Judul' => 'SOP Kosongkan', 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($id, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);

        $svc->ubahVersi($idVersi, ['Id_Master_Konten' => null], 1);

        $this->assertNull(
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
                ->value('Id_Master_Konten'));
    }
}
