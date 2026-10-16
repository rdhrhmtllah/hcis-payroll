<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Tahap proses versi dokumen SOP.
 *
 * Delapan tahap yang dilihat manusia — Drafting, Review, Approval, Hold, Cancel,
 * Approved, Filing and Registered, Obsolete — berdiri DI ATAS empat Status
 * teknis yang sudah dipahami seluruh sistem (DRAFT/READY/PUBLISHED/OBSOLETE).
 *
 * KENAPA MELAPISI, BUKAN MENGGANTI. Kolom N_SOP_Item_Versi.Status dibaca rilis
 * paket, progres karyawan, monitoring, dan ekspor. Mengganti isinya dengan
 * delapan nilai memaksa 40-an pembaca berubah serentak, dan yang terlewat satu
 * pun akan diam-diam salah membaca keadaan versi. Pemetaannya disimpan sebagai
 * DATA di kolom Status_Teknis, sehingga tahap baru bisa ditambah tanpa satu
 * baris kode pun berubah.
 *
 * TRANSISI JUGA DATA. Alur persetujuan berubah lebih sering daripada perangkat
 * lunaknya; menuliskan "Review hanya boleh ke Approval" di dalam kode berarti
 * setiap perubahan kebijakan menunggu rilis.
 */
class SopTahapVersiService
{
    private string $table = 'N_SOP_Master_Tahap_Versi';

    private string $tableTransisi = 'N_SOP_Master_Tahap_Versi_Transisi';

    /**
     * Tahap yang ditempati versi lama yang belum ter-backfill.
     *
     * READY sengaja TIDAK ada di sini: CK_N_SOP_Versi_Status hanya mengenal
     * DRAFT/PUBLISHED/OBSOLETE, dan nol baris READY pernah ada di tabel ini.
     * Nilai itu hanya punya arti pada salinan kerja paket terbit.
     */
    /**
     * Kelompok field yang boleh dirujuk master.
     *
     * Sama alasannya dengan KOLOM_CAP: nama yang datang dari data tak pernah
     * dipercaya sebagai rujukan komponen begitu saja. Salah ketik di master
     * berakhir sebagai langkah tanpa kelompok — bukan layar yang mencari
     * komponen karangan.
     */
    private const KELOMPOK_SAH = ['PENGAJUAN', 'REVIEW', 'PENGESAHAN', 'TERBIT', 'TINJAUAN'];

    /** Nama langkah, dipakai bila master belum menyebutkannya. */
    private const NAMA_LANGKAH = [
        1 => 'Pengajuan',
        2 => 'Review',
        3 => 'Pengesahan',
        /* "Pemberkasan", bukan "Terbit & Sebar": langkah ini adalah tempat
           berkas final disiapkan, dan penerbitan hanyalah salah satu dari dua
           akhir yang mungkin — yang lain adalah pembatalan. Istilahnya
           mengikuti DML-2026-042. */
        4 => 'Pemberkasan',
        /* "Tinjauan Akhir", bukan "Review": langkah 2 sudah bernama Review dan
           mengurus catatan review. Dua kotak di rel yang sama berbunyi identik
           adalah cara termurah membuat admin salah membuka langkah. */
        5 => 'Tinjauan Akhir',
    ];

    /** Kolom tanggal yang boleh dicap otomatis oleh perpindahan tahap. */
    private const KOLOM_CAP = [
        'Tanggal_Permintaan',
        'Tanggal_Respon',
        'Tanggal_Akhir_Approval',
        'Tanggal_Obsolete',
    ];

    private const PETA_STATUS = [
        SopStatus::VERSI_DRAFT => 'DRAFTING',
        SopStatus::VERSI_PUBLISHED => 'FILING_REGISTERED',
        SopStatus::VERSI_OBSOLETE => 'OBSOLETE',
    ];

    /** Seluruh tahap aktif, terurut. */
    public function opsi(): array
    {
        return DB::table($this->table)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')
            ->get()
            ->map(fn ($t) => [
                'Id' => Hashids::connection('custom')->encode($t->Id_Tahap),
                'Kode_Tahap' => $t->Kode_Tahap,
                'Nama_Tahap' => $t->Nama_Tahap,
                'Status_Teknis' => $t->Status_Teknis,
                'Warna' => $t->Warna,
                'Deskripsi' => $t->Deskripsi,
                'Butuh_Berkas' => $t->Butuh_Berkas === SopStatus::AKTIF,
                'Butuh_Pengesahan' => $t->Butuh_Pengesahan === SopStatus::AKTIF,
                'Is_Terminal' => $t->Is_Terminal === SopStatus::AKTIF,
                'Langkah' => $t->Langkah !== null ? (int) $t->Langkah : null,
                'Kelompok' => in_array((string) $t->Kelompok, self::KELOMPOK_SAH, true)
                    ? $t->Kelompok : null,
            ])->all();
    }

    public function cariKode(string $kode): ?object
    {
        return DB::table($this->table)->where('Kode_Tahap', $kode)->first();
    }

    public function cari(int $id): ?object
    {
        return DB::table($this->table)->where('Id_Tahap', $id)->first();
    }

    /**
     * Tahap sebuah versi.
     *
     * Versi yang Tahap_Id-nya masih kosong DITURUNKAN dari Status-nya, bukan
     * dianggap tak bertahap. Tanpa ini, versi yang lahir sebelum kolomnya ada
     * akan tampak kehilangan keadaan setiap kali backfill belum menyentuhnya —
     * termasuk versi yang dibuat jalur lain setelah rilis ini.
     */
    public function tahapVersi(int $idVersi): ?object
    {
        $v = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Tahap_Id', 'Status']);
        if (! $v) {
            return null;
        }

        if ($v->Tahap_Id !== null) {
            return $this->cari((int) $v->Tahap_Id);
        }

        $kode = self::PETA_STATUS[$v->Status] ?? null;

        return $kode ? $this->cariKode($kode) : null;
    }

    /**
     * Tahap yang boleh dituju dari tahap sekarang.
     *
     * @return array<int, array>
     */
    public function tujuanSah(int $idVersi): array
    {
        $kini = $this->tahapVersi($idVersi);
        if (! $kini || $kini->Is_Terminal === SopStatus::AKTIF) {
            return [];
        }

        /* Di-cast int: driver sqlsrv mengembalikan id sebagai STRING, dan
           perbandingan ketat terhadap hasil decode hashid (int) tak akan pernah
           cocok — daftar tujuan tampak kosong tanpa satu pun galat. */
        $ids = DB::table($this->tableTransisi)
            ->where('Dari_Tahap_Id', $kini->Id_Tahap)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->pluck('Ke_Tahap_Id')
            ->map(fn ($v) => (int) $v)
            ->all();

        if ($ids === []) {
            return [];
        }

        $tujuan = array_values(array_filter(
            $this->opsi(),
            fn ($t) => in_array(Hashids::connection('custom')->decode($t['Id'])[0] ?? 0, $ids, true),
        ));

        /* Syarat ikut dikirim bersama tujuannya, bukan lewat panggilan kedua:
           layar menggambar checklist di samping tiap pilihan, dan memisahkannya
           berarti daftar tujuan sempat tampil tanpa tahu mana yang bisa
           ditempuh. */
        /* Baris master diambil SEKALI untuk semua tujuan. cariKode() di dalam
           perulangan berarti satu kueri per tujuan, dan syaratTahap() hanya
           membutuhkan Id_Tahap serta dua kolom Butuh_* yang sudah ada di sini. */
        $baris = DB::table($this->table)
            ->whereIn('Kode_Tahap', array_column($tujuan, 'Kode_Tahap'))
            ->get(['Kode_Tahap', 'Id_Tahap', 'Butuh_Berkas', 'Butuh_Pengesahan'])
            ->keyBy('Kode_Tahap');

        foreach ($tujuan as &$t) {
            $m = $baris[$t['Kode_Tahap']] ?? null;
            $t['Syarat'] = $m ? $this->syaratTahap($idVersi, $m) : [];
            /* MAKSUD sebuah perpindahan, bukan sekadar arahnya.

               Daftar tujuan yang netral memaksa admin menebak akibat tiap
               pilihan: "Drafting" tak menyebut bahwa itu mengembalikan versi
               untuk direvisi, dan "Cancel" tak menyebut bahwa versinya
               berakhir di situ. Maksud dikirim dari sini supaya layar bisa
               menamai aksinya dan menuliskan konfirmasi yang menyebut
               akibatnya — tanpa layar menghafal kode tahap satu per satu.

               MAJU tak muncul sebagai tombol tersendiri: ia sudah ditangani
               "Simpan & Lanjut". Terbit pun bukan perpindahan tahap biasa —
               pointer dokumen, versi lama, nomor resmi, dan paket perujuk ikut
               bergerak, jadi ia diarahkan ke pratinjau terbit.

               TAHAN sudah tidak ada sejak DML-2026-042. Menahan pekerjaan tak
               butuh status: admin yang belum siap melanjutkan cukup tidak
               menekan lanjut, dan versinya memang tinggal di tahap itu. */
            $langkahKini = $kini->Langkah !== null ? (int) $kini->Langkah : null;
            $langkahTujuan = $t['Langkah'];

            $t['Aksi'] = match (true) {
                $t['Kode_Tahap'] === 'FILING_REGISTERED' => 'TERBITKAN',
                $t['Kode_Tahap'] === 'CANCEL' => 'BATALKAN',
                $langkahKini !== null && $langkahTujuan !== null
                    && $langkahTujuan < $langkahKini => 'KEMBALIKAN',
                $langkahKini !== null && $langkahTujuan !== null
                    && $langkahTujuan > $langkahKini => 'MAJU',
                default => 'PINDAH',
            };
        }

        return $tujuan;
    }

    /**
     * Alur untuk versi yang BELUM LAHIR — bentuknya sama persis dengan
     * alurVersi(), sehingga layar tak punya dua jalur.
     *
     * KENAPA AKSINYA IKUT DI SINI. Sampai 2026-08-27 endpoint template hanya
     * mengirim rangka langkah, dan layar terpaksa mengarang aksinya sendiri:
     * ia memaku "Simpan Draft Versi" untuk versi tanpa id. Akibatnya admin
     * menghadapi jembatan yang tak dimintanya — satu klik untuk menyimpan,
     * lalu satu klik lagi untuk lanjut, padahal ia masuk ke wizard ini memang
     * untuk mendefinisikan versi dan meneruskannya.
     *
     * Lebih pokok lagi: label yang dirakit layar adalah sumber kebenaran kedua,
     * dan sisa wizard ini justru dibangun untuk menghindarinya. Begitu tahap
     * pertama dinamai ulang di master, kalimat rakitan layar ikut basi tanpa
     * ada yang tahu.
     *
     * @return array{Langkah: array, Langkah_Aktif: int, Aksi_Utama: array, Aksi_Lain: array, Tujuan: array}
     */
    public function alurTemplate(): array
    {
        $tujuan = $this->tujuanTemplate();

        $langkah = $this->templateLangkah();
        foreach ($langkah as &$l) {
            /* Versi baru berdiri di langkah 1; sisanya terkunci sampai draftnya
               lahir dan punya tahap sungguhan. */
            $l['Terkunci'] = $l['Nomor'] > 1;
            $l['Lengkap'] = $l['Nomor'] === 1;
            $l['Kurang'] = $l['Nomor'] === 1 ? $this->syaratKurang($tujuan) : [];
        }
        unset($l);

        return [
            'Langkah' => $langkah,
            'Langkah_Aktif' => 1,
            'Aksi_Utama' => $this->aksiUtama($tujuan),
            /* Bentuknya dijaga sama persis dengan alurVersi() — layar tak punya
               dua jalur. Versi yang belum lahir masih jauh dari penerbitan,
               jadi tak ada aksi antar-langkah maupun aksi lain. */
            'Aksi_Antar' => null,
            'Aksi_Lain' => [],
            'Tujuan' => $tujuan,
        ];
    }

    /**
     * Tujuan sah dari tahap AWAL, tanpa versi.
     *
     * Kembarannya tujuanSah(), dengan satu perbedaan yang disengaja: seluruh
     * syarat dilaporkan BELUM terpenuhi. Versi yang belum ada memang belum
     * punya berkas, pengesah, maupun tanggal — melaporkannya terpenuhi berarti
     * menawarkan tombol yang pasti ditolak sesudah ditekan.
     *
     * @return array<int, array>
     */
    private function tujuanTemplate(): array
    {
        $awal = $this->cariKode('DRAFTING');
        if (! $awal) {
            return [];
        }

        $ids = DB::table($this->tableTransisi)
            ->where('Dari_Tahap_Id', $awal->Id_Tahap)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->pluck('Ke_Tahap_Id')
            ->map(fn ($v) => (int) $v)
            ->all();
        if ($ids === []) {
            return [];
        }

        $tujuan = array_values(array_filter(
            $this->opsi(),
            fn ($t) => in_array(Hashids::connection('custom')->decode($t['Id'])[0] ?? 0, $ids, true),
        ));

        $baris = DB::table($this->table)
            ->whereIn('Kode_Tahap', array_column($tujuan, 'Kode_Tahap'))
            ->get(['Kode_Tahap', 'Id_Tahap'])
            ->keyBy('Kode_Tahap');

        $langkahAwal = $awal->Langkah !== null ? (int) $awal->Langkah : null;

        foreach ($tujuan as &$t) {
            $m = $baris[$t['Kode_Tahap']] ?? null;
            $daftar = $m
                ? $this->syaratMaster((int) $m->Id_Tahap)
                    ->map(fn ($x) => ['Kode' => $x->Kode_Syarat, 'Nama' => $x->Nama_Syarat, 'terpenuhi' => false])
                    ->all()
                : [];
            $t['Syarat'] = ['daftar' => $daftar];

            /* Aturan penamaan aksi SAMA PERSIS dengan tujuanSah(). Ditulis ulang
               di sini, bukan dipanggil, karena yang di sana bergantung pada
               tahap sebuah versi yang belum ada. */
            $t['Aksi'] = match (true) {
                $t['Kode_Tahap'] === 'FILING_REGISTERED' => 'TERBITKAN',
                $t['Kode_Tahap'] === 'CANCEL' => 'BATALKAN',
                $langkahAwal !== null && $t['Langkah'] !== null
                    && $t['Langkah'] < $langkahAwal => 'KEMBALIKAN',
                $langkahAwal !== null && $t['Langkah'] !== null
                    && $t['Langkah'] > $langkahAwal => 'MAJU',
                default => 'PINDAH',
            };
        }
        unset($t);

        return $tujuan;
    }

    /**
     * Empat langkah yang digambar layar, murni dari master tahap.
     *
     * Dipakai pula oleh form versi BARU yang belum punya id. Tanpa endpoint
     * template ini, layar harus menghafal empat langkahnya sendiri — persis
     * sumber kebenaran kedua yang rancangan ini ada untuk menghindarinya.
     *
     * @return array<int, array>
     */
    public function templateLangkah(): array
    {
        $tahap = DB::table($this->table)
            ->whereNotNull('Langkah')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')
            ->get();

        $out = [];
        foreach ($tahap->groupBy('Langkah') as $nomor => $anggota) {
            $nomor = (int) $nomor;
            $kelompok = trim((string) ($anggota->first()->Kelompok ?? ''));
            if (! in_array($kelompok, self::KELOMPOK_SAH, true)) {
                $kelompok = null;
            }

            $out[] = [
                'Nomor' => $nomor,
                'Nama' => self::NAMA_LANGKAH[$nomor] ?? ('Langkah '.$nomor),
                'Kelompok' => $kelompok,
                'Tahap' => $anggota->pluck('Kode_Tahap')->all(),
                'Keadaan' => 'belum',
                'Tanggal' => null,
            ];
        }

        return $out;
    }

    /**
     * Daftar langkah sebuah versi, beserta keadaan dan tanggalnya.
     *
     * URUTAN DAN ISI LANGKAH DITURUNKAN DARI MASTER, bukan ditulis di sini:
     * tahap yang ditambah admin cukup menyatakan ia duduk di langkah berapa.
     * Tahap tanpa `Langkah` — Hold, Cancel, Obsolete — sengaja tak memunculkan
     * langkah sendiri; ketiganya keadaan yang menempel pada langkah berjalan.
     *
     * @return array<int, array>
     */
    public function langkahVersi(int $idVersi): array
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Created_At', 'Tanggal_Publish', 'Tanggal_Permintaan',
                'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Obsolete']);
        if (! $versi) {
            return [];
        }

        $kini = $this->tahapVersi($idVersi);
        $langkahKini = $this->langkahKini($kini, $versi);
        $master = DB::table($this->table)
            ->whereNotNull('Langkah')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')
            ->get()
            ->groupBy('Langkah');

        $out = $this->templateLangkah();
        foreach ($out as &$langkah) {
            $anggota = $master[$langkah['Nomor']] ?? collect();
            $langkah['Keadaan'] = $this->keadaanLangkah(
                $langkah['Nomor'], $langkahKini, $kini, $anggota);
            $langkah['Tanggal'] = $this->tanggalLangkah($langkah['Nomor'], $anggota, $versi);
        }

        /* Ringkasan langkah yang sudah mengerut: admin tetap bisa membaca apa
           yang terjadi tanpa membukanya kembali. Semuanya dihitung sekali di
           sini — bukan satu query per langkah dari layar. */
        $pengesah = DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();
        $review = DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)->count();
        $punyaBerkas = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where(fn ($q) => $q->whereNotNull('Id_Master_Konten')->orWhereNotNull('Id_Master_Ujian'))
            ->exists();
        $terbit = $langkahKini !== null && $langkahKini >= 5;

        $dok = DB::table('N_SOP_Item_Versi as v')
            ->leftJoin('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->first(['d.Tipe']);
        $tipe = (string) ($dok->Tipe ?? '');
        $pakaiPengesahan = $tipe === '' || SopTipeItem::blokBerlaku($tipe, SopTipeItem::BLOK_PENGESAHAN);
        // Kelengkapan menagih yang WAJIB saja; FORM boleh tanpa pengesah.
        $wajibPengesahan = $tipe === '' || SopTipeItem::blokWajib($tipe, SopTipeItem::BLOK_PENGESAHAN);

        foreach ($out as &$langkah) {
            $langkah['Ringkasan'] = match ($langkah['Kelompok']) {
                'PENGAJUAN' => $review === 0 && $pengesah === 0 ? 'baru diajukan' : null,
                'REVIEW' => $review > 0
                    ? $review.' putaran review'
                    : (($langkahKini ?? 0) > $langkah['Nomor'] ? 'tanpa catatan review' : 'belum ada catatan'),
                'PENGESAHAN' => $pakaiPengesahan ? ($pengesah.' pengesah') : 'pengesahan tidak diperlukan',
                'TERBIT' => $punyaBerkas ? 'berkas final siap' : 'berkas final belum ada',
                'TINJAUAN' => $terbit ? 'sudah terbit' : 'menunggu diterbitkan',
                default => null,
            };

            /* Kelengkapan dikirim sebagai FAKTA, bukan disimpulkan layar dari
               kalimat ringkasan. Layar yang mengurai "2 putaran review" dengan
               regex akan diam-diam salah begitu kalimatnya diperhalus, dan
               salahnya tak terlihat sampai ada yang membuka langkah keliru.

               Pengajuan selalu lengkap begitu versinya tersimpan: yang menahan
               kelanjutannya adalah syarat transisi, bukan isian langkah ini. */
            $langkah['Lengkap'] = match ($langkah['Kelompok']) {
                'PENGAJUAN' => true,
                /* Langkah yang SUDAH DILEWATI adalah langkah selesai, apa pun
                   jumlah catatannya. Syarat CATATAN_REVIEW dicabut DML-2026-042
                   karena review yang berjalan mulus — dan perpanjangan — memang
                   tak meninggalkan catatan. Yang tersisa cuma kalimatnya, dan
                   "0 putaran review" terbaca admin sebagai pekerjaan yang
                   tertinggal padahal gerbangnya sudah lama terbuka. */
                'REVIEW' => $review > 0 || ($langkahKini ?? 0) > $langkah['Nomor'],
                'PENGESAHAN' => ! $wajibPengesahan || $pengesah > 0,
                'TERBIT' => $punyaBerkas,
                /* Tinjauan Akhir selesai HANYA ketika versinya benar-benar
                   terbit. Membacanya saja tak menyelesaikan apa pun. */
                'TINJAUAN' => $terbit,
                default => true,
            };
        }

        return $out;
    }

    /**
     * Seluruh yang dibutuhkan wizard untuk menggambar dirinya, dalam satu
     * panggilan — TERMASUK langkah mana yang harus dibuka.
     *
     * KENAPA SERVER YANG MEMUTUSKAN. Selama layar ikut memutuskan, ada dua
     * sumber kebenaran: server menegakkan syarat transisi, layar menebak
     * langkah dari kelengkapan isi. Keduanya pasti menyimpang, dan
     * menyimpangnya tak terlihat sampai admin menemukan dirinya di langkah
     * yang salah — persis bug yang rancangan ini ada untuk menutupnya.
     *
     * @return array{Langkah: array, Langkah_Aktif: int, Aksi_Utama: array, Aksi_Lain: array, Tujuan: array}
     */
    public function alurVersi(int $idVersi): array
    {
        $aktif = $this->langkahBerjalan($idVersi);
        $tujuan = $this->tujuanSah($idVersi);
        $kurang = $this->syaratKurang($tujuan);

        $utama = $this->aksiUtama($tujuan);
        $antar = $this->aksiAntarLangkah($utama, $aktif);

        $langkah = $this->langkahVersi($idVersi);
        foreach ($langkah as &$l) {
            /* Terkunci itu POSISIONAL. Langkah yang sudah lewat tetap terbuka:
               mengoreksi ringkasan perubahan sesudah masuk Review adalah hal
               wajar, dan mengunci masa lalu memaksa admin membatalkan versi
               hanya untuk memperbaiki satu kalimat.

               SATU PENGECUALIAN: langkah tujuan penerbitan boleh dibuka sebelum
               ditempati. Ia memang ada untuk DILIHAT sebelum diterbitkan —
               menguncinya sampai versinya terbit berarti rekap yang hanya bisa
               dibaca sesudah terlambat. Pengecualian ini sempit dengan sengaja:
               hanya berlaku ketika aksi utamanya TERBITKAN, jadi langkah maju
               yang lain tetap terkunci seperti semula. */
            $l['Terkunci'] = $l['Nomor'] > $aktif
                && ! ($antar !== null && $l['Nomor'] === $antar['Ke_Langkah']);
            /* Yang kurang hanya disebut pada langkah BERJALAN. Menempelkannya
               ke langkah terkunci akan berbunyi seperti tuntutan atas langkah
               itu, padahal ia syarat untuk MENINGGALKAN langkah sekarang. */
            $l['Kurang'] = $l['Nomor'] === $aktif ? $kurang : [];
        }
        unset($l);

        return [
            'Langkah' => $langkah,
            'Langkah_Aktif' => $aktif,
            'Aksi_Utama' => $utama,
            'Aksi_Antar' => $antar,
            'Aksi_Lain' => $this->aksiLain($tujuan),
            'Tujuan' => $tujuan,
        ];
    }

    /**
     * Aksi yang memindahkan LAYAR, bukan tahap.
     *
     * Ada satu tempat yang membutuhkannya: sebelum menerbitkan. Penerbitan
     * menggeser pointer dokumen, meng-obsolete versi lama, merakit ulang nomor
     * resmi, dan merekonsiliasi paket perujuk — dan itu semua tak bisa ditarik.
     * Karena itu di antara Pemberkasan dan penerbitan berdiri satu langkah yang
     * hanya untuk dibaca, dan tombol di Pemberkasan mengantar ke sana alih-alih
     * langsung menerbitkan.
     *
     * KENAPA DARI SERVER. Layar boleh memutuskan langkah mana yang sedang
     * dibuka — itu memang miliknya — tetapi tidak boleh merakit kalimat
     * tombolnya. Nama "Tinjauan Akhir" datang dari master, sama seperti nama
     * tahap; merakitnya di klien membuatnya basi diam-diam begitu dinamai ulang.
     *
     * @return array{Jenis: string, Label: string, Ke_Langkah: int}|null
     */
    private function aksiAntarLangkah(array $utama, int $aktif): ?array
    {
        $ke = $utama['Ke_Langkah'] ?? null;
        if (($utama['Jenis'] ?? null) !== 'TERBITKAN' || $ke === null || $ke <= $aktif) {
            return null;
        }

        return [
            'Jenis' => 'LANJUT_LANGKAH',
            'Label' => 'Simpan & Lanjut ke '.(self::NAMA_LANGKAH[$ke] ?? ('Langkah '.$ke)),
            'Ke_Langkah' => (int) $ke,
        ];
    }

    /**
     * Syarat yang belum terpenuhi untuk meninggalkan tahap sekarang.
     *
     * Dibaca dari tujuan maju yang sah, bukan dihitung ulang: daftar yang
     * dilihat admin dan daftar yang menegakkan transisi harus satu benda.
     *
     * @return array<int, string>
     */
    private function syaratKurang(array $tujuan): array
    {
        $kurang = [];
        foreach ($tujuan as $t) {
            if (! in_array($t['Aksi'] ?? '', ['MAJU', 'TERBITKAN'], true)) {
                continue;
            }
            foreach ($t['Syarat']['daftar'] ?? [] as $s) {
                if (! ($s['terpenuhi'] ?? false)) {
                    $kurang[] = $s['Nama'];
                }
            }
        }

        return array_values(array_unique($kurang));
    }

    /**
     * SATU aksi utama, sudah bernama lengkap dengan akibatnya.
     *
     * Layar TIDAK merakit kalimat "Simpan & Lanjut ke X" sendiri: begitu tahap
     * dinamai ulang di master — dan itu memang terjadi, Approved baru saja
     * menjadi Pemberkasan — kalimat rakitan layar ikut basi tanpa ada yang
     * tahu.
     *
     * Ambigu (lebih dari satu tujuan maju yang sah) sengaja jatuh ke SIMPAN.
     * Memilihkan satu di antara dua jalan yang sama-sama sah bukan wewenang
     * sebuah tombol simpan.
     *
     * @return array{Jenis: string, Label: string, Ke_Tahap: ?string, Syarat_Kurang: array}
     */
    private function aksiUtama(array $tujuan): array
    {
        $simpan = ['Jenis' => 'SIMPAN', 'Label' => 'Simpan', 'Ke_Tahap' => null,
            'Ke_Langkah' => null, 'Syarat_Kurang' => []];

        $maju = array_values(array_filter(
            $tujuan,
            fn ($t) => in_array($t['Aksi'] ?? '', ['MAJU', 'TERBITKAN'], true),
        ));
        if (count($maju) !== 1) {
            return $simpan;
        }

        $t = $maju[0];
        $kurang = array_values(array_map(
            fn ($s) => $s['Nama'],
            array_filter($t['Syarat']['daftar'] ?? [], fn ($s) => ! ($s['terpenuhi'] ?? false)),
        ));

        /* SYARAT KURANG TIDAK LAGI MENURUNKAN TOMBOL JADI "SIMPAN" (2026-08-27).
           Sampai sebelum ini, syarat yang belum terpenuhi membuat aksinya jatuh
           ke Simpan biasa, dengan alasan "menawarkan tombol lanjut yang pasti
           ditolak berarti penolakan datang sesudah admin menekannya".

           Alasan itu benar ketika tombolnya belum ikut menyimpan. Ia sudah
           tidak benar: SIMPAN_LANJUT menyimpan DULU lalu memindahkan, sehingga
           syaratnya diperiksa terhadap data SESUDAH simpan. Seluruh syarat yang
           ada — berkas, pengesah, tiga tanggal — dipasok dari formulir yang
           sedang dibuka, jadi justru klik itulah yang memenuhinya.

           Yang lama menciptakan klik ganda yang persis dikeluhkan: admin
           mengetik pengesah, tombolnya masih "Simpan" karena server menilai
           data TERSIMPAN, ia menekan Simpan, barulah tombol lanjut muncul dan
           ia menekannya lagi. Tombol yang menggambarkan masa lalu sementara
           kliknya bertindak atas masa kini.

           Yang kurang tetap DILAPORKAN — layar menampilkannya sebagai
           keterangan di samping tombol. Penegakannya tak pernah menjadi tugas
           tombol: pindah() dan terbitkanVersi() tetap menolak, dengan pesan
           yang menyebut apa yang kurang. */
        return $t['Aksi'] === 'TERBITKAN'
            ? ['Jenis' => 'TERBITKAN', 'Label' => 'Terbitkan',
                'Ke_Tahap' => $t['Kode_Tahap'], 'Ke_Langkah' => $t['Langkah'],
                'Syarat_Kurang' => $kurang]
            : ['Jenis' => 'SIMPAN_LANJUT', 'Label' => 'Simpan & Lanjut ke '.$t['Nama_Tahap'],
                'Ke_Tahap' => $t['Kode_Tahap'], 'Ke_Langkah' => $t['Langkah'],
                'Syarat_Kurang' => $kurang];
    }

    /**
     * Aksi yang BERBEDA MAKSUDNYA — mundur dan batal — masing-masing dinamai
     * akibatnya, bukan nama tahap tujuannya. "Drafting" tak menyebut bahwa itu
     * mengembalikan versi untuk direvisi, dan "Cancel" tak menyebut bahwa
     * versinya berakhir di situ.
     *
     * @return array<int, array{Jenis: string, Label: string, Ke_Tahap: string}>
     */
    private function aksiLain(array $tujuan): array
    {
        $out = [];
        foreach ($tujuan as $t) {
            $out[] = match ($t['Aksi'] ?? '') {
                'KEMBALIKAN' => ['Jenis' => 'KEMBALIKAN',
                    'Label' => 'Kembalikan ke '.$t['Nama_Tahap'], 'Ke_Tahap' => $t['Kode_Tahap']],
                'BATALKAN' => ['Jenis' => 'BATALKAN',
                    'Label' => 'Batalkan Versi', 'Ke_Tahap' => $t['Kode_Tahap']],
                default => null,
            };
        }

        return array_values(array_filter($out));
    }

    /**
     * Nomor langkah tempat versi BERDIRI — bukan langkah yang isinya kurang.
     *
     * Perbedaan itu yang dulu melahirkan gejala "selalu balik ke Review".
     * Layar memilih langkah pertama yang `Lengkap`-nya false, dan karena
     * Pengajuan selalu dilaporkan lengkap, ia tak pernah berhenti di
     * Pengajuan — versi yang baru disimpan di langkah 1 langsung melompat ke
     * langkah 2 atau 3, dan melompat lagi setiap kali admin menyimpan.
     *
     * Sekarang langkah yang dibuka POSISIONAL. `Lengkap` tetap dilaporkan
     * karena ia fakta yang jujur, tetapi hanya sebagai keterangan.
     */
    public function langkahBerjalan(int $idVersi): int
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Created_At', 'Tanggal_Publish', 'Tanggal_Permintaan',
                'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Obsolete']);
        if (! $versi) {
            return 1;
        }

        return $this->langkahKini($this->tahapVersi($idVersi), $versi) ?? 1;
    }

    /**
     * Langkah tempat tahap tanpa nomor menempel.
     *
     * Hold/Cancel adalah KEADAAN, bukan kotak langkah. Keduanya menempel pada
     * langkah terakhir yang benar, diturunkan dari tanggal proses yang sudah
     * tercap. Obsolete selalu menutup langkah Terbit & Sebar.
     */
    private function langkahKini(?object $kini, object $versi): ?int
    {
        if (! $kini) {
            return null;
        }
        /* Approved MENUTUP langkah Pengesahan dan MEMBUKA Terbit & Sebar.
           Menahan layar di langkah 3 setelah persetujuan tuntas membuat admin
           tak pernah diarahkan mengunggah dokumen final. */
        if ($kini->Kode_Tahap === 'APPROVED') {
            return 4;
        }
        if ($kini->Langkah !== null) {
            return (int) $kini->Langkah;
        }
        if ($kini->Kode_Tahap === 'OBSOLETE') {
            /* Langkah TERAKHIR, yang sejak DML-2026-051 bernomor 5. Angka mati
               di sini pernah membuat versi obsolete berhenti di langkah yang
               salah begitu pemetaan langkah bergeser. */
            return array_key_last(self::NAMA_LANGKAH);
        }
        if (filled($versi->Tanggal_Akhir_Approval ?? null)
            || filled($versi->Tanggal_Respon ?? null)) {
            return 3;
        }
        if (filled($versi->Tanggal_Permintaan ?? null)) {
            return 2;
        }

        return 1;
    }

    /** Keadaan satu langkah terhadap tahap yang sedang ditempati. */
    private function keadaanLangkah(int $nomor, ?int $kini, ?object $tahapKini, $anggota): string
    {
        if ($kini === null) {
            return 'belum';
        }
        if ($nomor < $kini) {
            return 'selesai';
        }
        if ($nomor > $kini) {
            return 'belum';
        }

        if ($tahapKini?->Kode_Tahap === 'HOLD') {
            return 'hold';
        }
        if ($tahapKini?->Kode_Tahap === 'CANCEL') {
            return 'cancel';
        }
        if ($tahapKini?->Kode_Tahap === 'OBSOLETE') {
            return 'selesai';
        }

        /* Approved menutup langkah Pengesahan; Filing and Registered menutup
           langkah Terbit & Sebar. Tahap pertama dalam kelompok masih berjalan. */
        $kode = $anggota->pluck('Kode_Tahap')->all();
        $terakhir = end($kode);

        return $tahapKini && $tahapKini->Kode_Tahap === $terakhir
            && count($kode) > 1 ? 'selesai'
            : ($tahapKini?->Kode_Tahap === 'FILING_REGISTERED' ? 'selesai' : 'berjalan');
    }

    /**
     * Tanggal ringkas sebuah langkah.
     *
     * Satu langkah bisa punya lebih dari satu tahap/cap (Approval lalu
     * Approved). Yang ditampilkan adalah cap TERAKHIR yang sudah terisi:
     * sebelum tuntas terlihat tanggal respon, sesudah tuntas menjadi tanggal
     * akhir approval. Tetap data-driven; tak ada nama tahap di sini.
     */
    private function tanggalLangkah(int $nomor, $anggota, object $versi)
    {
        $tanggal = null;
        foreach ($anggota as $tahap) {
            $kolom = trim((string) ($tahap->Cap_Tanggal ?? ''));
            if ($kolom !== '' && in_array($kolom, self::KOLOM_CAP, true)
                && filled($versi->{$kolom} ?? null)) {
                $tanggal = $versi->{$kolom};
            }
        }
        if ($tanggal !== null) {
            return $tanggal;
        }

        return $nomor === 1 ? ($versi->Created_At ?? null) : ($versi->Tanggal_Publish ?? null);
    }

    /**
     * Syarat menuju sebuah tahap, beserta status terpenuhinya.
     *
     * Syarat adalah BARIS DATA, bukan kolom Butuh_X yang bertambah selamanya.
     * Layar dan penjaga perpindahan membaca daftar yang sama.
     *
     * @return array{daftar: array<int,array>, berkas: array, pengesahan: array}
     */
    /**
     * Keadaan syarat sebuah versi, dihitung SEKALI per permintaan.
     *
     * tujuanSah() memanggil syaratTahap() untuk setiap tujuan, dan tiap
     * panggilan dulu mengulang empat kueri yang jawabannya persis sama —
     * satu layar bisa menembak 20+ kueri hanya untuk menggambar checklist.
     * Cache ini hidup selama satu permintaan HTTP saja; tak ada keadaan yang
     * dibawa antar permintaan, jadi tak ada jawaban basi.
     */
    private array $cacheNilai = [];

    /**
     * Lupakan nilai syarat yang sudah di-cache untuk sebuah versi.
     *
     * DIBUTUHKAN karena cache di atas hanya benar selama tak ada yang MENGUBAH
     * data di tengah permintaan. Urutan "periksa syarat, penuhi syaratnya,
     * periksa lagi" terjadi setiap kali satu proses menjalankan lebih dari satu
     * perpindahan — dan pada urutan itu pemeriksaan kedua membaca jawaban dari
     * sebelum syaratnya dipenuhi, lalu menolak perpindahan yang sebenarnya sah.
     *
     * Ditemukan 2026-08-27 oleh sop:seed-demo: pindah(APPROVAL) → isi pengesah
     * → pindah(APPROVED) ditolak dengan "Lengkapi: Realisasi pengesah" padahal
     * pengesahnya baru saja tersimpan. Lewat layar hal ini tak pernah terlihat,
     * sebab tiap langkah di sana adalah permintaan HTTP tersendiri dengan
     * instance service yang baru.
     */
    public function lupakanNilai(?int $idVersi = null): void
    {
        if ($idVersi === null) {
            $this->cacheNilai = [];

            return;
        }

        unset($this->cacheNilai[$idVersi]);
    }

    private function nilaiSyarat(int $idVersi): array
    {
        if (isset($this->cacheNilai[$idVersi])) {
            return $this->cacheNilai[$idVersi];
        }

        $v = DB::table('N_SOP_Item_Versi as v')
            ->leftJoin('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->first(['v.Id_Master_Konten', 'v.Id_Master_Ujian', 'v.Disahkan_Oleh', 'd.Tipe',
                'v.Tanggal_Berlaku', 'v.Tanggal_Kadaluarsa', 'v.Tanggal_Review_Berikutnya']);

        $tipe = (string) ($v->Tipe ?? '');

        $adaIsi = $v && (($tipe === 'KUIS')
            ? filled($v->Id_Master_Ujian)
            : filled($v->Id_Master_Konten));
        /* Pengesahan kembali SATU BLOK — konsep rencana terpisah dibatalkan
           (2026-08-24). Baris RENCANA legacy tetap tak dihitung; baris yang
           ditulis jalur sekarang semuanya REALISASI.
           Tipe non-dokumen (FORM, VIDEO, KUIS) tidak memakai blok pengesahan
           (SopTipeItem::blokBerlaku) sehingga syarat pengesahan dianggap terpenuhi. */
        /* Syarat menanyakan WAJIB: FORM punya blok pengesahan (opsional, sejak
           5 Okt 2026) dan tak boleh tertahan karenanya. */
        $adaPengesah = ($tipe !== '' && ! SopTipeItem::blokWajib($tipe, SopTipeItem::BLOK_PENGESAHAN))
            || DB::table('N_SOP_Item_Versi_Pengesah')
                ->where('Id_SOP_Item_Versi', $idVersi)->where('Jenis', 'REALISASI')
                ->where('Flag_Aktif', SopStatus::AKTIF)->exists()
            || filled($v->Disahkan_Oleh ?? null);
        $adaReview = DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)->exists();

        return $this->cacheNilai[$idVersi] = [
            'BERKAS' => $adaIsi,
            'REALISASI_PENGESAH' => $adaPengesah,
            'CATATAN_REVIEW' => $adaReview,
            /* Tiga tanggal masa berlaku (DDL-2026-049). Gerbangnya dipasang di
               FILING_REGISTERED — saat versi MENINGGALKAN Pemberkasan menuju
               terbit, bukan saat draft disimpan. Draft boleh setengah jadi;
               yang tidak boleh setengah jadi adalah dokumen yang mulai
               mengikat orang. */
            'TANGGAL_BERLAKU' => filled($v->Tanggal_Berlaku ?? null),
            'TANGGAL_KADALUARSA' => filled($v->Tanggal_Kadaluarsa ?? null),
            'TANGGAL_REVIEW_BERIKUTNYA' => filled($v->Tanggal_Review_Berikutnya ?? null),
            /* BELUM BEREDAR — syarat untuk MEMBATALKAN versi yang sudah terbit.
               Nomor revisi dikonsumsi saat versinya sampai ke orang, bukan saat
               diterbitkan. Selama belum ada yang menerima atau membacanya,
               membatalkannya hanya mengoreksi kekeliruan beberapa menit lalu.
               Sesudahnya, membatalkan berarti menyatakan dokumen yang sudah
               diikuti orang "tak pernah terbit" — dan itulah yang OBSOLETE ada
               untuk menanganinya.

               Draft memenuhi syarat ini dengan sendirinya: ia memang belum
               pernah sampai ke siapa pun. Jadi memasangnya pada tahap CANCEL
               tak mengubah apa pun bagi pembatalan draft, dan hanya menggigit
               pada yang sudah terbit — persis yang dimaksud. */
            'BELUM_BEREDAR' => ! $this->sudahBeredar($idVersi),
        ];
    }

    /**
     * Apakah versi ini sudah sampai ke orang?
     *
     * Lima rujukan hilir, sama persis dengan pengaman DML-2026-063: begitu
     * salah satunya ada, revisi itu sudah beredar. Log sengaja tak dihitung —
     * jejak audit mencatat bahwa sesuatu terjadi, bukan bahwa seseorang
     * menerimanya.
     */
    private function sudahBeredar(int $idVersi): bool
    {
        /* MASUK PAKET != SAMPAI KE ORANG.

           Dua rujukan di bawah harus melewati induknya lebih dulu. Admin
           menyusun paket dalam keadaan DRAFT — dokumennya sudah terpasang
           sebagai item, tapi belum satu pun karyawan bisa melihatnya. Menghitung
           pemasangan itu sebagai "sudah beredar" membuat versi yang baru saja
           salah unggah menolak dibatalkan dengan alasan yang tidak benar, dan
           admin tak punya cara membuktikan sebaliknya dari layar.

           Tiga rujukan lainnya (progres, penjadwalan, rekap) tak perlu disaring:
           ketiganya hanya lahir sesudah paketnya terbit. */
        return DB::table('N_SOP_Progress')->where('Id_SOP_Item_Versi', $idVersi)->exists()
            || DB::table('N_SOP_Paket_Item as pi')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pi.Id_SOP_Paket')
                ->where('pi.Id_Item_Versi_Aktif', $idVersi)
                ->where('p.Status', SopStatus::PAKET_PUBLISHED)
                ->exists()
            || DB::table('N_SOP_Penjadwalan_Integrasi')->where('Id_SOP_Item_Versi', $idVersi)->exists()
            || DB::table('N_SOP_Coverage_Rollup')->where('Id_SOP_Item_Versi', $idVersi)->exists()
            || DB::table('N_SOP_Paket_Versi_Item as pvi')
                ->join('N_SOP_Paket_Versi as pv', 'pv.Id_SOP_Paket_Versi', '=', 'pvi.Id_SOP_Paket_Versi')
                ->where('pvi.Id_SOP_Item_Versi', $idVersi)
                ->where('pv.Status', SopStatus::PAKET_PUBLISHED)
                ->exists();
    }

    /** Syarat master per tahap, juga dibaca sekali untuk seluruh permintaan. */
    private array $cacheSyaratMaster = [];

    private function syaratMaster(int $idTahap)
    {
        if (! isset($this->cacheSyaratMaster[$idTahap])) {
            $this->cacheSyaratMaster[$idTahap] = DB::table('N_SOP_Master_Tahap_Versi_Syarat')
                ->where('Tahap_Id', $idTahap)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->orderBy('Urutan')->get(['Kode_Syarat', 'Nama_Syarat']);
        }

        return $this->cacheSyaratMaster[$idTahap];
    }

    public function syaratTahap(int $idVersi, object $tahap): array
    {
        $nilai = $this->nilaiSyarat($idVersi);
        $adaIsi = $nilai['BERKAS'];
        $adaPengesah = $nilai['REALISASI_PENGESAH'];

        $rows = $this->syaratMaster((int) $tahap->Id_Tahap);

        /* Jalur kompatibilitas untuk environment yang DDL-039/DML-040-nya belum
           diterapkan. Begitu baris generik ada, ia sepenuhnya berwenang. */
        if ($rows->isEmpty()) {
            $fallback = [];
            if ($tahap->Butuh_Berkas === SopStatus::AKTIF) {
                $fallback[] = (object) ['Kode_Syarat' => 'BERKAS', 'Nama_Syarat' => 'Berkas final'];
            }
            if ($tahap->Butuh_Pengesahan === SopStatus::AKTIF) {
                $fallback[] = (object) ['Kode_Syarat' => 'REALISASI_PENGESAH', 'Nama_Syarat' => 'Realisasi pengesah'];
            }
            $rows = collect($fallback);
        }

        $daftar = $rows->map(fn ($s) => [
            'Kode' => $s->Kode_Syarat,
            'Nama' => $s->Nama_Syarat,
            /* Kode syarat karangan tak pernah dianggap terpenuhi. Ia terlihat
               merah di checklist dan menghalangi transisi, bukan dilewati diam. */
            'terpenuhi' => $nilai[$s->Kode_Syarat] ?? false,
        ])->all();

        return [
            'daftar' => $daftar,
            /* Kontrak lama dipertahankan untuk konsumen yang belum pindah. */
            'berkas' => ['butuh' => collect($daftar)->contains('Kode', 'BERKAS'), 'terpenuhi' => $adaIsi],
            'pengesahan' => ['butuh' => collect($daftar)->contains('Kode', 'REALISASI_PENGESAH'), 'terpenuhi' => $adaPengesah],
        ];
    }

    /**
     * Pindahkan versi ke tahap lain.
     *
     * Menegakkan tiga hal, semuanya dari DATA — bukan dari cabang di kode:
     *   1. transisinya terdaftar di N_SOP_Master_Tahap_Versi_Transisi
     *   2. bila tahap tujuan menuntut berkas, isinya sah untuk tipe dokumennya
     *   3. bila menuntut pengesahan, blok pengesahnya terisi
     *
     * FILING AND REGISTERED TIDAK DITANGANI DI SINI. Tahap itu berarti versinya
     * benar-benar terbit, dan terbit punya akibat yang jauh melampaui satu
     * kolom: pointer dokumen bergeser, versi lama menjadi obsolete, nomor resmi
     * dirakit ulang, dan seluruh paket perujuk direkonsiliasi. Pemanggil
     * diarahkan ke jalur terbit yang sudah ada supaya tak ada dua jalan menuju
     * satu peristiwa.
     *
     * @throws \DomainException
     */
    public function pindah(int $idVersi, string $kodeTujuan, int $userId): object
    {
        /* Nilai syarat dibaca ULANG di tiap perpindahan. Satu proses yang
           menjalankan beberapa perpindahan berselang isian — persis yang
           dilakukan sop:seed-demo dan jalur batch mana pun — akan memeriksa
           syarat terhadap jawaban dari sebelum isiannya masuk. */
        $this->lupakanNilai($idVersi);

        $kini = $this->tahapVersi($idVersi);
        if (! $kini) {
            throw new \DomainException('Versi tidak ditemukan.');
        }

        $tujuan = $this->cariKode($kodeTujuan);
        if (! $tujuan || $tujuan->Flag_Aktif !== SopStatus::AKTIF) {
            throw new \DomainException('Tahap tujuan tidak dikenali atau sudah dinonaktifkan.');
        }

        if ($tujuan->Kode_Tahap === 'FILING_REGISTERED') {
            throw new \DomainException(
                'Filing and Registered ditempuh lewat penerbitan versi, bukan '
                .'pemindahan tahap — di sanalah pointer dokumen, versi lama, '
                .'nomor resmi, dan paket perujuk ikut disesuaikan.');
        }

        $this->pastikanTransisiSah($kini, $tujuan);
        $this->pastikanSyaratTerpenuhi($idVersi, $tujuan);

        $ubah = $this->capTanggal($idVersi, $tujuan) + [
            'Tahap_Id' => $tujuan->Id_Tahap,
            /* Status teknis ikut, karena itulah yang dibaca sisa sistem. */
            'Status' => $tujuan->Status_Teknis,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ];
        if ($tujuan->Nonaktifkan_Versi === SopStatus::AKTIF) {
            $ubah['Flag_Aktif'] = SopStatus::INAKTIF;
        }

        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update($ubah);

        $idDok = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->value('Id_SOP_Dokumen');

        SopLog::catat(
            'VERSI_TAHAP',
            "Tahap versi: {$kini->Nama_Tahap} → {$tujuan->Nama_Tahap}.",
            null, null, $idVersi, null,
            [
                'id_dokumen' => $idDok ? (int) $idDok : null,
                'oleh_id_users' => $userId,
                'dari' => $kini->Kode_Tahap,
                'ke' => $tujuan->Kode_Tahap,
            ],
        );

        return $tujuan;
    }

    /**
     * Cap tanggal saat memasuki sebuah tahap.
     *
     * Tahap mana mencap tanggal mana dibaca dari master (`Cap_Tanggal`), bukan
     * dari cabang di kode: admin yang menambah tahap bisa sekaligus menyatakan
     * tanggal apa yang tercap, tanpa satu baris kode berubah.
     *
     * HANYA BILA MASIH KOSONG. Aturan itu melindungi dua hal sekaligus:
     *   - tanggal asli, saat versi bolak-balik antar tahap
     *   - koreksi manual admin — surat yang ditandatangani minggu lalu tapi baru
     *     dicatat hari ini tak boleh tertimpa oleh sebuah klik
     * Seluruh perpindahan tetap tercatat utuh di N_SOP_Log, jadi jejaknya tak
     * hilang meski kolomnya hanya menyimpan yang pertama.
     *
     * @return array<string, string> kolom => tanggal, kosong bila tak mencap
     */
    private function capTanggal(int $idVersi, object $tujuan): array
    {
        $kolom = trim((string) ($tujuan->Cap_Tanggal ?? ''));
        if ($kolom === '') {
            return [];
        }

        /* Nama kolom datang dari data, dan data tak pernah dipercaya sebagai
           nama kolom begitu saja: hanya yang terdaftar di sini yang boleh
           dituliskan. Salah ketik di master berakhir sebagai pencapan yang
           dilewati, bukan sebagai kueri yang menyebut kolom karangan. */
        if (! in_array($kolom, self::KOLOM_CAP, true)) {
            return [];
        }

        $sudahAda = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)->value($kolom);

        return $sudahAda === null ? [$kolom => now()->toDateString()] : [];
    }

    /** @throws \DomainException */
    private function pastikanTransisiSah(object $kini, object $tujuan): void
    {
        if ((int) $kini->Id_Tahap === (int) $tujuan->Id_Tahap) {
            throw new \DomainException('Versi ini sudah berada di tahap tersebut.');
        }

        if ($kini->Is_Terminal === SopStatus::AKTIF) {
            throw new \DomainException(
                "\"{$kini->Nama_Tahap}\" adalah tahap akhir — lanjutkan dengan versi baru.");
        }

        $ada = DB::table($this->tableTransisi)
            ->where('Dari_Tahap_Id', $kini->Id_Tahap)
            ->where('Ke_Tahap_Id', $tujuan->Id_Tahap)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->exists();

        if (! $ada) {
            throw new \DomainException(
                "Perpindahan dari \"{$kini->Nama_Tahap}\" ke \"{$tujuan->Nama_Tahap}\" "
                .'tidak diizinkan alur yang berlaku.');
        }
    }

    /**
     * Syarat masuk sebuah tahap.
     *
     * Dibaca dari master, bukan ditulis di sini: admin bisa mengendurkan atau
     * mengetatkan alur tanpa rilis. Yang tetap adalah artinya — tahap yang
     * menuntut berkas tak bisa dimasuki oleh versi kosong.
     *
     * @throws \DomainException
     */
    private function pastikanSyaratTerpenuhi(int $idVersi, object $tujuan): void
    {
        $syarat = $this->syaratTahap($idVersi, $tujuan)['daftar'] ?? [];
        $kurang = collect($syarat)->where('terpenuhi', false)->pluck('Nama')->all();

        if ($kurang !== []) {
            throw new \DomainException(
                "\"{$tujuan->Nama_Tahap}\" belum bisa dimasuki. Lengkapi: "
                .implode(', ', $kurang).'.');
        }
    }
}
