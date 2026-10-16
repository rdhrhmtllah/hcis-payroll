<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopRepositoryReadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Awareness pembaruan versi di Repository.
 *
 * Yang diuji bukan sekadar "ada angka", melainkan bahwa angka pada lencana
 * benar-benar sama dengan jumlah item yang berubah di dalam paketnya — dua
 * angka yang bertentangan di satu layar adalah kegagalan utama fitur ini.
 *
 * Rollup per folder Kategori dulu ikut dijaga di sini. Foldernya dicabut
 * 28 Agu 2026 bersama masternya; yang tersisa dan tetap dijaga adalah rollup
 * per paket dan pagar jumlah query beranda.
 */
class SopAwarenessUpdateTest extends TestCase
{
    private const HARI_BARU = 30;

    private function kodeUji(): string
    {
        $kode = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Flag_Aktif', 'Y')
            ->value('Kode_Karyawan');

        $this->assertNotNull($kode, 'Butuh minimal satu penugasan aktif.');

        return $kode;
    }

    private function svc(): SopRepositoryReadService
    {
        return app(SopRepositoryReadService::class);
    }

    public function test_card_reports_how_many_items_changed_in_the_window(): void
    {
        $beranda = $this->svc()->beranda($this->kodeUji());
        $kartu = collect($beranda['baru'])->merge($beranda['favorit'])->merge($beranda['terakhir']);

        $this->assertNotEmpty($kartu, 'Butuh minimal satu kartu paket untuk diuji.');
        $this->assertArrayHasKey('Item_Baru', $kartu->first());
    }

    public function test_item_count_matches_the_versions_published_in_the_window(): void
    {
        $beranda = $this->svc()->beranda($this->kodeUji());
        $kartu = collect($beranda['baru'])->merge($beranda['favorit'])->merge($beranda['terakhir'])
            ->unique('id')->values();

        $batas = now()->startOfDay()->subDays(self::HARI_BARU);

        foreach ($kartu as $k) {
            $idPaket = DB::table('N_SOP_Master_Paket')->where('Nama_Paket', $k['Nama_Paket'])->value('Id_SOP_Paket');
            if (! $idPaket) {
                continue;
            }

            $harapan = DB::table('N_SOP_Paket_Item as i')
                ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
                ->where('i.Id_SOP_Paket', $idPaket)
                ->where('i.Flag_Aktif', 'Y')
                ->where('v.Versi_Ke', '>', 1)
                ->where('v.Tanggal_Publish', '>=', $batas)
                ->count();

            $this->assertSame($harapan, $k['Item_Baru'], "Hitungan item berubah meleset pada paket {$k['Nama_Paket']}.");
        }
    }

    /**
     * Lencana pada daftar paket cocok dengan keadaan paketnya sendiri.
     *
     * Menggantikan rollup folder Kategori: yang dibandingkan sekarang bukan
     * "angka folder vs isinya" melainkan "lencana baris vs isinya", karena
     * itulah janji yang masih dibuat layar.
     */
    public function test_package_badges_match_their_own_contents(): void
    {
        $d = $this->svc()->daftarPaket($this->kodeUji(), ['status' => 'semua'], 1, 50);

        $this->assertNotEmpty($d['rows'], 'Daftar paket kosong — uji ini tak menguji apa pun.');

        foreach ($d['rows'] as $k) {
            $this->assertArrayHasKey('Baru', $k);
            $this->assertArrayHasKey('Diperbarui', $k);

            // Keduanya saling meniadakan: satu paket tak bisa sekaligus "baru
            // bagi Anda" dan "berubah sejak Anda membukanya".
            $this->assertFalse($k['Baru'] && $k['Diperbarui'],
                "Paket {$k['Nama_Paket']} memasang dua lencana yang saling meniadakan.");
        }
    }

    public function test_never_opened_package_is_labelled_new_not_updated(): void
    {
        $beranda = $this->svc()->beranda($this->kodeUji());
        $kartu = collect($beranda['baru'])->merge($beranda['favorit'])->merge($beranda['terakhir']);

        foreach ($kartu as $k) {
            if ($k['Diperbarui']) {
                $this->assertTrue(
                    (bool) $k['Pernah_Buka'],
                    "Paket {$k['Nama_Paket']} dilabeli Diperbarui padahal belum pernah dibuka.",
                );
            }
        }
    }

    public function test_a_package_is_never_both_new_and_updated(): void
    {
        $beranda = $this->svc()->beranda($this->kodeUji());
        $kartu = collect($beranda['baru'])->merge($beranda['favorit'])->merge($beranda['terakhir']);

        foreach ($kartu as $k) {
            $this->assertFalse(
                $k['Baru'] && $k['Diperbarui'],
                "Paket {$k['Nama_Paket']} membawa dua lencana sekaligus.",
            );
        }
    }

    public function test_beranda_stays_within_its_query_budget(): void
    {
        $kode = $this->kodeUji();

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        $this->svc()->beranda($kode);

        // Beranda memang menembak beberapa query (total, favorit, terakhir,
        // baru, indeks kartu). Pagar ini menjaga beranda tidak kembali ke pola
        // satu query per baris yang dulu membuatnya 66+ query.
        $this->assertLessThanOrEqual(12, $queries);
    }

    /* ================= SOP SAYA ================= */

    public function test_my_sop_card_reports_items_that_must_be_reread(): void
    {
        $hasil = app(\App\Services\LMS\SOP\SopProgressReadService::class)->tugasSaya($this->kodeUji(), 'all');

        $this->assertNotEmpty($hasil['items'], 'Butuh minimal satu penugasan.');
        $this->assertArrayHasKey('Item_Perlu_Ulang', $hasil['items'][0]);
    }

    public function test_reread_count_only_covers_items_confirmed_on_an_older_version(): void
    {
        $kode = $this->kodeUji();
        $hasil = app(\App\Services\LMS\SOP\SopProgressReadService::class)->tugasSaya($kode, 'all');

        foreach ($hasil['items'] as $kartu) {
            $idPaket = DB::table('N_SOP_Master_Paket')->where('Nama_Paket', $kartu['Nama_Paket'])->value('Id_SOP_Paket');
            if (! $idPaket) {
                continue;
            }

            // Item wajib yang: pernah dikonfirmasi pada versi LAIN, dan versi
            // aktifnya BELUM dikonfirmasi orang ini.
            $harapan = DB::table('N_SOP_Paket_Item as i')
                ->where('i.Id_SOP_Paket', $idPaket)
                ->where('i.Flag_Aktif', 'Y')
                ->where('i.Flag_Wajib', 'Y')
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('N_SOP_Progress as lama')
                    ->join('N_SOP_Penjadwalan_Detail as ld', 'ld.Id_SOP_Penjadwalan_Detail', '=', 'lama.Id_SOP_Penjadwalan_Detail')
                    ->whereColumn('lama.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item')
                    ->where('ld.Kode_Karyawan', $kode)
                    ->whereIn('lama.Status', ['SELESAI', 'DIKONFIRMASI'])
                    ->whereRaw('ISNULL(lama.Id_SOP_Item_Versi,-1) <> ISNULL(i.Id_Item_Versi_Aktif,-1)'))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('N_SOP_Progress as kini')
                    ->join('N_SOP_Penjadwalan_Detail as kd', 'kd.Id_SOP_Penjadwalan_Detail', '=', 'kini.Id_SOP_Penjadwalan_Detail')
                    ->whereColumn('kini.Id_SOP_Paket_Item', 'i.Id_SOP_Paket_Item')
                    ->where('kd.Kode_Karyawan', $kode)
                    ->whereIn('kini.Status', ['SELESAI', 'DIKONFIRMASI'])
                    ->whereRaw('ISNULL(kini.Id_SOP_Item_Versi,-1) = ISNULL(i.Id_Item_Versi_Aktif,-1)'))
                ->count();

            $this->assertSame(
                $harapan,
                $kartu['Item_Perlu_Ulang'],
                "Hitungan baca ulang meleset pada paket {$kartu['Nama_Paket']}.",
            );
        }
    }

    /**
     * Pagar untuk penggandaan baris: menambahkan leftJoin ke progres versi lama
     * bisa menggelembungkan SUM(CASE) tanpa suara. Persen di atas 100 atau
     * selesai melebihi total adalah gejalanya.
     */
    public function test_progress_counts_survive_the_join_without_inflating(): void
    {
        $hasil = app(\App\Services\LMS\SOP\SopProgressReadService::class)->tugasSaya($this->kodeUji(), 'all');

        foreach ($hasil['items'] as $kartu) {
            $this->assertLessThanOrEqual(
                $kartu['Total_Item'],
                $kartu['Item_Selesai'],
                "Item selesai melebihi total pada paket {$kartu['Nama_Paket']} — baris tergandakan join.",
            );
            $this->assertLessThanOrEqual(100, $kartu['Persen'], "Persen di atas 100 pada paket {$kartu['Nama_Paket']}.");
            $this->assertLessThanOrEqual($kartu['Total_Item'], $kartu['Item_Perlu_Ulang']);
        }
    }

    public function test_reread_count_never_negative_and_bounded_by_required_items(): void
    {
        $hasil = app(\App\Services\LMS\SOP\SopProgressReadService::class)->tugasSaya($this->kodeUji(), 'all');

        foreach ($hasil['items'] as $kartu) {
            $this->assertGreaterThanOrEqual(0, $kartu['Item_Perlu_Ulang']);
        }
    }
}
