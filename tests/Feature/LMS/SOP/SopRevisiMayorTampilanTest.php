<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * REVISI MAYOR HARUS TERASA SEKETIKA, TANPA MENUNGGU PEKERJA LATAR.
 *
 * Menerbitkan revisi hanya MENGANTREKAN rekonsiliasi (intent outbox → Cloud
 * Tasks). Rekonsiliasi itulah yang menyemai baris progres versi baru, membuka
 * kembali kewajiban yang sudah SELESAI, dan mengirim notifikasi. Selama
 * antreannya belum tersentuh — dan di lingkungan lokal ia memang tak pernah
 * tersentuh, karena Cloud Tasks memanggil balik URL publik — layar tidak boleh
 * berbohong kepada orang yang sedang membacanya.
 *
 * Cacat yang ditutup: pembacaan status item memakai baris progres versi LAIN
 * sebagai cadangan. Begitu versi baru terbit, karyawan belum punya baris untuk
 * versi itu, sehingga cadangan versi lama yang SELESAI terbaca sebagai "sudah
 * selesai". Satu baris cadangan mematikan tiga penanda sekaligus: persen
 * bertahan 100, item wajib terhitung tuntas, dan Perlu_Baca_Ulang justru FALSE
 * karena syaratnya "belum selesai".
 *
 * Terukur 10 Sep 2026 pada paket nyata: sesudah revisi mayor diterbitkan,
 * halaman detail masih melaporkan 100% dan nol penanda baca ulang.
 */
class SopRevisiMayorTampilanTest extends TestCase
{
    use DatabaseTransactions;

    private const KODE = 'UJI-REV-MAYOR';

    private function read(): SopProgressReadService
    {
        return app(SopProgressReadService::class);
    }

    /**
     * Dua id versi NYATA — kolom Id_Item_Versi_Aktif berkunci asing, jadi angka
     * karangan ditolak basis data.
     *
     * @return array{0:int,1:int}
     */
    private function duaVersi(): array
    {
        $ids = DB::table('N_SOP_Item_Versi')->orderBy('Id_SOP_Item_Versi')
            ->limit(2)->pluck('Id_SOP_Item_Versi')->all();

        $this->assertCount(2, $ids, 'Butuh dua baris N_SOP_Item_Versi untuk dipinjam fixture ini.');

        return [(int) $ids[0], (int) $ids[1]];
    }

    /**
     * Paket terbit + satu item wajib, ditugaskan ke satu karyawan yang SUDAH
     * menuntaskannya pada versi lama.
     *
     * @return array{paket:int,item:int,detail:int,versiLama:int,versiBaru:int}
     */
    private function duniaSetelahRevisi(bool $salinProgresKeVersiBaru): array
    {
        [$versiLama, $versiBaru] = $this->duaVersi();

        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'UJI REVISI MAYOR '.substr((string) microtime(true), -6),
            'Status' => SopStatus::PAKET_PUBLISHED,
            'Mode_Distribusi' => 'TERBATAS',
            'Mode_Urutan_Item' => 'BEBAS',
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Paket');

        /* Versi AKTIF item sudah menunjuk revisi BARU — itulah keadaan sesudah
           terbitkanVersi() menggeser pointernya. */
        $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Item' => SopTipeItem::DOKUMEN,
            'Judul_Item' => 'Prosedur yang baru direvisi',
            'Id_Item_Versi_Aktif' => $versiBaru,
            'Flag_Wajib' => SopStatus::AKTIF,
            'Urutan' => 1,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Paket_Item');

        $idPenjadwalan = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Tipe_Tenggat' => 'SAMPAI_DICABUT',
            'Sumber' => 'MANUAL',
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => 1,
            'Created_At' => now(),
        ], 'Id_SOP_Penjadwalan');

        /* Status_Paket sengaja masih SELESAI: inilah nilai basi yang ditinggalkan
           revisi sebelum rekonsiliasi sempat menghitung ulang. */
        $idDetail = DB::table('N_SOP_Penjadwalan_Detail')->insertGetId([
            'Id_SOP_Penjadwalan' => $idPenjadwalan,
            'Kode_Karyawan' => self::KODE,
            'Status_Paket' => SopStatus::STATUS_SELESAI,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Flag_Cabut_Akses' => 'T',
            'Created_At' => now(),
        ], 'Id_SOP_Penjadwalan_Detail');

        // Sudah tuntas pada versi LAMA.
        DB::table('N_SOP_Progress')->insert([
            'Id_SOP_Penjadwalan_Detail' => $idDetail,
            'Id_SOP_Paket_Item' => $idItem,
            'Id_SOP_Item_Versi' => $versiLama,
            'Kode_Karyawan' => self::KODE,
            'Tipe_Item' => SopTipeItem::DOKUMEN,
            'Status' => SopStatus::ITEM_DIKONFIRMASI,
            'Persen_Selesai' => 100,
            'Created_At' => now(),
        ]);

        /* Revisi MINOR: bawaPenyelesaian() menyalin baris selesai ke versi baru.
           Fixture ini menirukannya supaya uji penolakan di bawah benar-benar
           menguji cabang yang berbeda, bukan sekadar ketiadaan data. */
        if ($salinProgresKeVersiBaru) {
            DB::table('N_SOP_Progress')->insert([
                'Id_SOP_Penjadwalan_Detail' => $idDetail,
                'Id_SOP_Paket_Item' => $idItem,
                'Id_SOP_Item_Versi' => $versiBaru,
                'Kode_Karyawan' => self::KODE,
                'Tipe_Item' => SopTipeItem::DOKUMEN,
                'Status' => SopStatus::ITEM_DIKONFIRMASI,
                'Persen_Selesai' => 100,
                'Id_Versi_Asal' => $versiLama,
                'Created_At' => now(),
            ]);
        }

        return compact('idPaket', 'idItem', 'idDetail') + [
            'versiLama' => $versiLama,
            'versiBaru' => $versiBaru,
        ];
    }

    public function test_revisi_mayor_menurunkan_progres_tanpa_menunggu_rekonsiliasi(): void
    {
        $d = $this->duniaSetelahRevisi(salinProgresKeVersiBaru: false);

        $bundle = $this->read()->detailBundle(self::KODE, $d['idPaket'], true);
        $this->assertNotNull($bundle, 'Detail paket tidak terbaca.');

        $this->assertSame(
            0,
            (int) $bundle['progress']['persen'],
            'Progres masih menghitung penyelesaian versi LAMA sebagai selesai.',
        );
        $this->assertSame(0, (int) $bundle['progress']['item_selesai']);
    }

    public function test_item_yang_direvisi_ditandai_perlu_baca_ulang(): void
    {
        $d = $this->duniaSetelahRevisi(salinProgresKeVersiBaru: false);

        $item = collect($this->read()->detailBundle(self::KODE, $d['idPaket'], true)['items'])->first();

        $this->assertSame(SopStatus::ITEM_BELUM, $item['Status']);
        $this->assertTrue(
            (bool) $item['Perlu_Baca_Ulang'],
            'Penanda baca ulang mati — kartu item, banner detail, dan saringan SOP Saya semuanya ikut diam.',
        );
    }

    /**
     * Kartu di SOP Saya masuk ember "Perlu Baca Ulang", bukan "Perlu Dibaca".
     *
     * Ember lama `diperbarui` bersandar pada ledger DIBUKA_LAGI yang ditulis
     * rekonsiliasi — diam selama pekerja belum jalan. Ember ini dihitung dari
     * agregat yang sama yang mengisi kartu, jadi ia benar sejak revisi terbit.
     * Membiarkannya jatuh ke `perlu_dibaca` menyamarkan pekerjaan yang berbeda:
     * "belum pernah dibaca" versus "berubah sesudah Anda menyelesaikannya".
     */
    public function test_kartu_masuk_ember_perlu_baca_ulang_di_sop_saya(): void
    {
        $this->duniaSetelahRevisi(salinProgresKeVersiBaru: false);

        $hasil = $this->read()->tugasSaya(self::KODE, 'all');

        $this->assertSame(1, (int) ($hasil['counts']['perlu_ulang'] ?? 0), 'Ember "Perlu Baca Ulang" tak terisi.');
        $this->assertSame(0, (int) ($hasil['counts']['perlu_dibaca'] ?? 0), 'Kartunya justru jatuh ke ember "Perlu Dibaca".');

        $kartu = collect($hasil['items'])->first();
        $this->assertSame('perlu_ulang', $kartu['Grup']);
        $this->assertSame(1, (int) $kartu['Item_Perlu_Ulang']);
    }

    /**
     * UJI PENOLAKAN — revisi MINOR tidak boleh ikut membuka kembali.
     *
     * Tanpa ini, "selalu anggap belum selesai" pun lulus berkas ini, dan
     * perbaikannya berubah menjadi cacat yang berlawanan: perbaikan salah ketik
     * memaksa seluruh karyawan membaca ulang.
     */
    public function test_revisi_minor_tetap_terhitung_selesai(): void
    {
        $d = $this->duniaSetelahRevisi(salinProgresKeVersiBaru: true);

        $bundle = $this->read()->detailBundle(self::KODE, $d['idPaket'], true);
        $item = collect($bundle['items'])->first();

        $this->assertSame(100, (int) $bundle['progress']['persen']);
        $this->assertFalse(
            (bool) $item['Perlu_Baca_Ulang'],
            'Revisi minor menyalin penyelesaian ke versi baru — ia tak boleh diminta dibaca ulang.',
        );
    }
}
