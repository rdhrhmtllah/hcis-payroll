<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopVersiItem;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Otorisasi akses secure-PDF untuk modul SOP.
 * Backend reader reuse sistem secure-PDF LMS (gambar per-halaman + watermark);
 * otorisasinya khusus SOP: karyawan boleh membuka sebuah berkas bila ia
 * PUNYA PENUGASAN AKTIF pada paket yang memuat item aktif dengan versi BERLAKU
 * yang mereferensi konten berkas tersebut.
 *
 * Versi cabutan tidak lagi termasuk (keputusan produk 2026-08-12). Riwayatnya
 * tetap terlihat di halaman SOP — nomor, tanggal, pengesah, dan status baca —
 * tapi berkasnya tidak: dokumen terkendali yang sudah digantikan disimpan untuk
 * ditelusuri, bukan untuk dipakai.
 */
class SopSecurePdfAccessService
{
    /** Decode hashid berkas → id asli. */
    public function decodeBerkasId(string $berkasId): ?int
    {
        if (empty($berkasId) || $berkasId === 'undefined' || $berkasId === 'null') {
            return null;
        }
        $d = Hashids::connection('custom')->decode($berkasId);
        if (! empty($d)) {
            return (int) $d[0];
        }
        $d2 = Hashids::decode($berkasId);
        if (! empty($d2)) {
            return (int) $d2[0];
        }
        if (is_numeric($berkasId) && (int) $berkasId > 0) {
            return (int) $berkasId;
        }

        return null;
    }

    /**
     * Pintu ADMIN — terpisah, dan sengaja tidak lewat canAccess().
     *
     * canAccess() adalah lensa KARYAWAN: ia menuntut versi berstatus PUBLISHED
     * pada paket yang ditugaskan (atau publik) dan presensinya tak mengunci.
     * Admin Master Dokumen justru membuka yang tak pernah lolos syarat itu —
     * draft yang sedang disusun dan versi cabutan yang sedang ditinjau — jadi
     * memakai pintu karyawan berarti pratinjau admin selalu 403.
     *
     * Yang dijaga di sini bukan "berhak membaca SOP ini", melainkan batas
     * modulnya: berkas harus milik konten ber-Scope SOP. Kewenangan halamannya
     * sudah diperiksa gerbang rute (menuSopDokumenPage / menuSopBuilderPage);
     * pemeriksaan ini mencegah rute admin SOP dipakai memanen berkas LMS.
     */
    public function bolehAdmin(int $realBerkasId): bool
    {
        return DB::table('N_LMS_Master_Konten_Berkas as b')
            ->join('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'b.Id_Master_Konten')
            ->where('b.Id_Master_Konten_Berkas', $realBerkasId)
            ->where('k.Scope', 'SOP')
            ->exists();
    }

    public function canAccess(string $kode, int $realBerkasId): bool
    {
        $idKonten = DB::table('N_LMS_Master_Konten_Berkas')
            ->where('Id_Master_Konten_Berkas', $realBerkasId)
            ->value('Id_Master_Konten');
        if (! $idKonten) {
            return false;
        }

        /* Item ditelusuri lewat DOKUMEN, bukan lewat v.Id_SOP_Paket_Item.
           FK itu tunggal dan menunjuk item yang kebetulan membuat versinya,
           sehingga karyawan yang ditugaskan ke paket LAIN yang menyajikan
           dokumen yang sama akan ditolak — salah tolak, bukan salah izinkan.

           Cabang FK dipertahankan sebagai jalur mundur untuk versi
           ber-Id_SOP_Dokumen NULL (NULL = NULL bernilai NULL, bukan true). */
        $paketDitugaskan = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', fn ($j) => SopVersiItem::tautkan($j, 'i', 'v'))
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Penjadwalan_Detail as d', function ($j) use ($kode) {
                $j->on('d.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
                    ->where('d.Kode_Karyawan', '=', $kode)
                    ->where('d.Flag_Aktif', '=', SopStatus::AKTIF)
                    ->where('d.Flag_Cabut_Akses', '=', 'T');
            })
            ->where('v.Id_Master_Konten', $idKonten)
            /* Hanya berkas milik versi yang BERLAKU. Pembaca PDF aman adalah
               pintu terakhir menuju isi dokumen, jadi menutup versi cabutan di
               endpoint konten saja tak cukup: berkas versi lama tetap terbaca
               lewat sini oleh siapa pun yang menyimpan id berkasnya. */
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->pluck('i.Id_SOP_Paket');

        /* Gerbang presensi ikut berlaku di SINI, bukan hanya di halaman. Token
           PDF aman adalah pintu terakhir menuju isi dokumen; tanpa pemeriksaan
           ini, tautan token yang pernah dibagikan tetap membuka halaman PDF
           walau halaman SOP-nya sudah dikunci.

           DIPERIKSA PER PAKET, bukan atas paket pertama yang ditemukan. Versi
           sebelumnya memakai value() dan menyerah pada satu paket saja: bila
           paket itu terkunci presensi, akses yang sah lewat paket lain tak
           pernah dicoba — persis kekeliruan yang sudah diperingatkan docblock
           lewatJalurPublik(). Penelusuran lewat dokumen memperbesar himpunan
           kandidat, sehingga kekeliruan itu jadi makin sering terpicu. */
        $absensi = app(SopAbsensiService::class);
        foreach ($paketDitugaskan as $idPaket) {
            if (! $absensi->terkunci($kode, (int) $idPaket)) {
                return true;
            }
        }

        // Penugasan bukan satu-satunya jalan masuk. Paket PUBLIK memang dibuka
        // untuk semua karyawan oleh paketBolehDiakses(), dan halaman SOP-nya
        // sudah menampilkan dokumen itu — tanpa cabang ini pembaca PDF menolak
        // dengan 403 pada paket yang halamannya justru mempersilakan masuk.
        return $this->lewatJalurPublik($kode, $idKonten);
    }

    /**
     * Apakah item yang sedang dibuka meminta watermark di-burn ke halamannya.
     *
     * Default AMAN: watermark tetap aktif kalau $idItem kosong (link/handshake
     * lama tanpa konteks item), tidak cocok dengan berkas yang diminta, ATAU
     * pemanggil ($kode) tidak punya akses sah ke paket pemilik item tsb —
     * mematikan watermark HANYA terjadi lewat kecocokan eksplisit item+berkas
     * DAN otorisasi nyata, supaya idItem asing/spoofed tak bisa dipakai
     * memadamkan watermark dokumen lain (lihat canAccess()/lewatJalurPublik()
     * untuk pola otorisasi yang sama, di sini disempitkan ke SATU paket milik
     * $idItem, bukan "paket mana pun yang memuat konten ini").
     *
     * Join ke N_SOP_Item_Versi sengaja BUKAN lewat Id_Item_Versi_Aktif, supaya
     * versi LAMA (non-aktif) dari dokumen yang sama ikut cocok — Flag_Watermark
     * melekat di level item, bukan per versi, jadi versi mana pun yang match
     * hasilnya sama.
     *
     * Tautnya lewat SopVersiItem, bukan ditulis di sini. Versi mengikuti DOKUMEN
     * sekarang, dan kolom lama Id_SOP_Paket_Item tak pernah lagi diisi; menaut
     * lewat kolom itu saja membuat join tak pernah cocok, lalu gerbang
     * default-aman di bawah menyalakan watermark untuk SETIAP dokumen apa pun
     * setelan adminnya. Itu bukan dugaan — persis begitu cacatnya berjalan
     * sampai 2026-09-01, tanpa satu pun galat yang menandainya.
     */
    public function watermarkAktif(string $kode, int $realBerkasId, ?int $idItem): bool
    {
        if (! $idItem) {
            return true;
        }

        $item = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', fn ($j) => SopVersiItem::tautkan($j, 'i', 'v'))
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('i.Id_SOP_Paket_Item', $idItem)
            ->where('b.Id_Master_Konten_Berkas', $realBerkasId)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->first(['i.Id_SOP_Paket', 'i.Flag_Watermark']);

        if (! $item) {
            return true;
        }

        if (! $this->punyaAksesPaket($kode, (int) $item->Id_SOP_Paket)) {
            return true;
        }

        return $this->watermarkFlagOn($item->Flag_Watermark);
    }

    /** Perbandingan murni Y/T -> boolean, diekstrak agar bisa diuji tanpa DB. */
    public function watermarkFlagOn(?string $flag): bool
    {
        return $flag !== SopStatus::INAKTIF;
    }

    /**
     * Otorisasi $kode terhadap SATU paket ($idPaket) tertentu — mirror dua
     * cabang canAccess()/lewatJalurPublik() (penugasan aktif TIDAK terkunci,
     * ATAU paket publik TIDAK terkunci), tapi diikat ke paket pemilik item
     * yang sedang diminta, bukan "paket mana pun yang memuat konten ini".
     * Item lain yang kebetulan berbagi Id_Master_Konten tidak bisa dipakai
     * memalsukan akses ke paket ini.
     */
    private function punyaAksesPaket(string $kode, int $idPaket): bool
    {
        $ditugaskan = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Penjadwalan_Detail as d', function ($j) use ($kode) {
                $j->on('d.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
                    ->where('d.Kode_Karyawan', '=', $kode)
                    ->where('d.Flag_Aktif', '=', SopStatus::AKTIF)
                    ->where('d.Flag_Cabut_Akses', '=', 'T');
            })
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->exists();

        if ($ditugaskan && ! app(SopAbsensiService::class)->terkunci($kode, $idPaket)) {
            return true;
        }

        // Jalur publik: paket PUBLISHED, bukan TERBATAS. NULL diperlakukan
        // PUBLIK, sejalan dengan lewatJalurPublik()/paketBolehDiakses().
        $publik = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Id_SOP_Paket', $idPaket)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where(function ($q) {
                $q->whereNull('p.Mode_Distribusi')->orWhere('p.Mode_Distribusi', '<>', 'TERBATAS');
            })
            ->exists();

        if ($publik && ! app(SopAbsensiService::class)->terkunci($kode, $idPaket)) {
            return true;
        }

        return false;
    }

    /**
     * Konten terjangkau lewat paket PUBLIK yang presensinya tidak mengunci.
     *
     * Sengaja TIDAK menyentuh penugasan: inilah jalur karyawan yang membaca SOP
     * dari Perpustakaan tanpa pernah ditugaskan. Paket TERBATAS tak pernah ikut
     * terpilih, jadi kerahasiaannya utuh.
     *
     * Pemeriksaan presensi dilakukan PER PAKET, bukan atas paket pertama yang
     * ditemukan: satu konten bisa dipakai beberapa paket, dan menyerah pada
     * paket pertama yang terkunci akan menolak akses yang sebenarnya sah lewat
     * paket lain yang tak mensyaratkan presensi.
     */
    private function lewatJalurPublik(string $kode, int $idKonten): bool
    {
        $kandidat = DB::table('N_SOP_Item_Versi as v')
            // Lewat dokumen, dengan jalur mundur FK — alasan sama seperti canAccess().
            ->join('N_SOP_Paket_Item as i', fn ($j) => SopVersiItem::tautkan($j, 'i', 'v'))
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('v.Id_Master_Konten', $idKonten)
            // Sama seperti canAccess(): versi cabutan tak lagi dibagikan isinya.
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            // NULL diperlakukan PUBLIK, sejalan dengan SopProgressReadService::paketBolehDiakses().
            ->where(function ($q) {
                $q->whereNull('p.Mode_Distribusi')->orWhere('p.Mode_Distribusi', '<>', 'TERBATAS');
            })
            ->distinct()
            ->pluck('p.Id_SOP_Paket');

        $absensi = app(SopAbsensiService::class);
        foreach ($kandidat as $idPaket) {
            if (! $absensi->terkunci($kode, (int) $idPaket)) {
                return true;
            }
        }

        return false;
    }
}
