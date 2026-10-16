<?php

namespace App\Services\LMS\SOP\Support;

use Illuminate\Support\Facades\DB;

/**
 * Penjaga lingkup paket untuk mutasi Builder.
 *
 * Endpoint reorder menerima daftar id item beserta id section tujuannya, lalu
 * menuliskan Id_SOP_Section apa adanya. Tanpa penjaga ini, satu payload buatan
 * bisa memindahkan item paket A ke section paket B: kolom Id_SOP_Paket item itu
 * tetap A sementara section-nya menunjuk B, dan barisnya jadi yatim di kedua
 * paket. Kerusakannya permanen dan tak terlihat dari UI mana pun.
 *
 * Alih-alih menuntut klien mengirim id paket (mengubah kontrak API dan seluruh
 * pemanggil di frontend), lingkup DITURUNKAN dari id yang dikirim: seluruh id
 * wajib bermuara pada SATU paket yang sama. Payload campuran ditolak.
 *
 * Kelas ini juga menjadi satu-satunya titik lingkup Builder, sehingga penyempitan
 * berikutnya — misalnya per-kategori atau per-pemilik saat model peran sudah
 * diputuskan — cukup ditambahkan di sini, bukan disebar ke tiap endpoint.
 */
class SopBuilderScope
{
    /**
     * Id paket yang dimiliki BERSAMA oleh semua id item.
     *
     * @param  int[]  $ids
     * @return int|null null bila daftar kosong, ada id tak dikenal, atau id
     *                  tersebar di lebih dari satu paket.
     */
    public static function paketDariItem(array $ids, bool $draft): ?int
    {
        return self::paketTunggal(
            $ids,
            fn (array $unik) => $draft
                ? DB::table('N_SOP_Paket_Draft_Item as i')
                    ->join('N_SOP_Paket_Draft as d', 'd.Id_SOP_Paket_Draft', '=', 'i.Id_SOP_Paket_Draft')
                    ->whereIn('i.Id_SOP_Draft_Item', $unik)
                    ->get(['i.Id_SOP_Draft_Item as id', 'd.Id_SOP_Paket as paket'])
                : DB::table('N_SOP_Paket_Item')
                    ->whereIn('Id_SOP_Paket_Item', $unik)
                    ->get(['Id_SOP_Paket_Item as id', 'Id_SOP_Paket as paket'])
        );
    }

    /**
     * Id paket yang dimiliki BERSAMA oleh semua id section.
     *
     * @param  int[]  $ids
     * @return int|null lihat catatan pada paketDariItem().
     */
    public static function paketDariSection(array $ids, bool $draft): ?int
    {
        return self::paketTunggal(
            $ids,
            fn (array $unik) => $draft
                ? DB::table('N_SOP_Paket_Draft_Section as s')
                    ->join('N_SOP_Paket_Draft as d', 'd.Id_SOP_Paket_Draft', '=', 's.Id_SOP_Paket_Draft')
                    ->whereIn('s.Id_SOP_Draft_Section', $unik)
                    ->get(['s.Id_SOP_Draft_Section as id', 'd.Id_SOP_Paket as paket'])
                : DB::table('N_SOP_Paket_Section')
                    ->whereIn('Id_SOP_Section', $unik)
                    ->get(['Id_SOP_Section as id', 'Id_SOP_Paket as paket'])
        );
    }

    /**
     * Paket tunggal yang menaungi seluruh item DAN seluruh section tujuannya.
     *
     * Dipakai reorder item: memindahkan item antar section hanya sah bila kedua
     * sisi berada di paket yang sama. Section null diabaikan — item draft yang
     * belum masuk section memang mengirim null.
     *
     * @param  int[]  $idItem
     * @param  array<int|null>  $idSection
     */
    public static function paketDariItemDanSection(array $idItem, array $idSection, bool $draft): ?int
    {
        $paketItem = self::paketDariItem($idItem, $draft);
        if ($paketItem === null) {
            return null;
        }

        $sectionTerisi = array_values(array_filter($idSection, fn ($id) => $id !== null && $id !== ''));
        if (empty($sectionTerisi)) {
            return $paketItem;
        }

        return self::paketDariSection($sectionTerisi, $draft) === $paketItem ? $paketItem : null;
    }

    /**
     * Menyusutkan daftar id menjadi satu paket, atau null bila tidak bulat.
     *
     * Id tak dikenal ikut menggagalkan — payload yang menyebut baris tak ada
     * tidak boleh diteruskan ke UPDATE, sebab id itu bisa saja milik paket lain
     * yang kebetulan sudah terhapus.
     *
     * @param  int[]  $ids
     * @param  callable(array):\Illuminate\Support\Collection  $ambil
     */
    private static function paketTunggal(array $ids, callable $ambil): ?int
    {
        $unik = array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => (int) $id > 0))));
        if (empty($unik)) {
            return null;
        }

        $rows = $ambil($unik);
        if ($rows->count() !== count($unik)) {
            return null;
        } // ada id tak dikenal

        $paket = $rows->pluck('paket')->unique();

        return $paket->count() === 1 ? (int) $paket->first() : null;
    }
}
