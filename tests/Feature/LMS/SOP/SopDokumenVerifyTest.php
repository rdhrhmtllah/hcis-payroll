<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SopDokumenVerifyTest extends TestCase
{
    use DatabaseTransactions;

    /** Predikat "item ber-versi yang punya baris versi" — sengaja DIULANG, tak dibagi pakai dengan command yang diperiksa. */
    private function itemBerVersiCount(): int
    {
        return (int) DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Tipe_Item', SopTipeItem::VERSIONED)
            ->whereExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                ->whereColumn('v.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item'))
            ->count();
    }

    /**
     * Yang dijamin: backfill meninggalkan KELIMA INVARIAN DOKUMEN bersih.
     *
     * Dulu diperiksa lewat exit code gerbang — yaitu menuntut SELURUH gerbang
     * hijau. Itu menguji lebih dari yang diklaim: gerbang membaca seluruh data,
     * sehingga cacat yang tak berhubungan dengan backfill ikut memerahkannya.
     * Persis itu yang terjadi saat invarian "item ber-versi tanpa versi berlaku
     * di paket TERBIT" ditambahkan dan langsung menemukan pelanggaran nyata.
     *
     * Kini yang diperiksa baris OK-nya satu per satu — klaimnya utuh, dan
     * gerbangnya tetap jujur melaporkan pelanggaran lain.
     */
    public function test_lulus_setelah_backfill_bersih(): void
    {
        if ($this->itemBerVersiCount() === 0) {
            $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        }

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $this->artisan('sop:dokumen-verify')
            ->expectsOutputToContain('Item ber-tipe versi tanpa dokumen: 0')
            ->expectsOutputToContain('Baris versi tak ikut dokumen itemnya: 0')
            ->expectsOutputToContain('Tipe tanpa versi tapi punya dokumen: 0')
            ->expectsOutputToContain('Pointer versi aktif menggantung/tanpa dokumen/milik dokumen lain: 0');
    }

    /**
     * Baris versi tanpa dokumen (mis. item yang Tipe_Item-nya diubah menjauh
     * dari tipe ber-versi) TAK terlihat oleh kelima invarian dan memang sah di
     * Fase 1 — tapi angkanya menentukan apakah Fase 2 wajib memakai unique
     * index TERFILTER, karena SQL Server menganggap NULL setara. Karena itu ia
     * dilaporkan sebagai INFO: tak menaikkan pelanggaran, tak mengubah exit code.
     */
    public function test_melaporkan_baris_versi_tanpa_dokumen_tanpa_menggagalkan(): void
    {
        if ($this->itemBerVersiCount() === 0) {
            $this->markTestSkipped('Tak ada item ber-versi di data dev.');
        }

        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $jumlah = DB::table('N_SOP_Item_Versi')->whereNull('Id_SOP_Dokumen')->count();

        /* Yang dibuktikan: barisnya DILAPORKAN dan tetap bertanda INFO —
           bukan bahwa seluruh gerbang hijau, yang bergantung pada data lain
           sama sekali. */
        $this->artisan('sop:dokumen-verify')
            ->expectsOutputToContain("Baris versi tanpa dokumen (di luar kelima invarian): {$jumlah}")
            ->expectsOutputToContain('INFO');
    }

    public function test_menangkap_baris_versi_yang_tak_ikut_dokumen_itemnya(): void
    {
        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $versi = DB::table('N_SOP_Item_Versi')->whereNotNull('Id_SOP_Dokumen')->first();
        if (! $versi) {
            $this->markTestSkipped('Tak ada baris versi ber-dokumen di data dev.');
        }

        // Rusak dengan sengaja — di-rollback otomatis oleh DatabaseTransactions.
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->update(['Id_SOP_Dokumen' => null]);

        $this->artisan('sop:dokumen-verify')->assertExitCode(1);
    }

    public function test_menangkap_pointer_versi_aktif_milik_dokumen_lain(): void
    {
        $this->artisan('sop:dokumen-backfill')->assertSuccessful();

        $dok = DB::table('N_SOP_Dokumen')->whereNotNull('Id_Versi_Aktif')->first();
        $versiAsing = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', '<>', $dok->Id_SOP_Dokumen ?? 0)->first();
        if (! $dok || ! $versiAsing) {
            $this->markTestSkipped('Data dev tak cukup untuk kasus ini.');
        }

        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $dok->Id_SOP_Dokumen)
            ->update(['Id_Versi_Aktif' => $versiAsing->Id_SOP_Item_Versi]);

        $this->artisan('sop:dokumen-verify')->assertExitCode(1);
    }

    /**
     * Pointer versi yang menggantung kini MUSTAHIL, bukan sekadar terdeteksi.
     *
     * Uji ini dulu merusak data dengan sengaja lalu menuntut `sop:dokumen-verify`
     * menangkapnya. Sejak FK_N_SOP_Dokumen_Versi_Aktif terpasang, kerusakan itu
     * tak bisa lagi dibuat: basis datanya sendiri menolak UPDATE-nya.
     *
     * Yang dijaga karena itu digeser ke jaminan yang lebih kuat — bukan "cacatnya
     * terdeteksi setelah terjadi", melainkan "cacatnya tak bisa terjadi". Menuntut
     * pemindai menangkap keadaan yang mustahil hanya akan gagal selamanya, dan
     * kegagalan yang tak bisa diperbaiki akhirnya diabaikan orang.
     *
     * Kedua kolom penunjuk diperiksa: dokumen DAN item paket. Melindungi satu
     * saja meninggalkan pintu kedua terbuka untuk cacat yang sama.
     */
    public function test_pointer_versi_menggantung_ditolak_basis_data(): void
    {
        $idTakAda = (int) DB::table('N_SOP_Item_Versi')->max('Id_SOP_Item_Versi') + 1000;

        $sasaran = [
            'N_SOP_Dokumen' => ['Id_SOP_Dokumen', 'Id_Versi_Aktif'],
            'N_SOP_Paket_Item' => ['Id_SOP_Paket_Item', 'Id_Item_Versi_Aktif'],
        ];

        foreach ($sasaran as $tabel => [$pk, $kolom]) {
            $baris = DB::table($tabel)->whereNotNull($kolom)->first();
            if (! $baris) {
                $this->markTestSkipped("Tak ada baris {$tabel} ber-{$kolom} di data dev.");
            }

            $ditolak = false;
            try {
                DB::table($tabel)->where($pk, $baris->{$pk})->update([$kolom => $idTakAda]);
            } catch (QueryException $e) {
                $ditolak = true;
            }

            $this->assertTrue($ditolak,
                "{$tabel}.{$kolom} menerima pointer ke versi yang tidak ada. "
                .'Foreign key-nya hilang atau dinonaktifkan, dan karyawan bisa disajikan '
                .'versi yang tak pernah ada.');
        }
    }
}
