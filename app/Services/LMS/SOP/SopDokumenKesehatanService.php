<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SaringanMonitoring;
use App\Services\LMS\SOP\Support\SopDokumenKelompok;
use App\Services\LMS\SOP\Support\SopGerbangPaket;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Kesehatan dokumen SOP — lapis dokumen pada dashboard monitoring.
 *
 * SATUANNYA SATU DOKUMEN, SATU BARIS. Itu bukan detail teknis melainkan
 * seluruh alasan service ini ada.
 *
 * Sebelum ini, angka dokumen di monitoring datang dari
 * SopDocumentControlService::baseVersiAktif(), yang berangkat dari
 * N_SOP_Item_Versi lalu JOIN ke N_SOP_Paket_Item dan N_SOP_Master_Paket.
 * Butirannya karena itu BARIS PAKET, bukan dokumen:
 *
 *   - satu dokumen yang dipakai tiga paket terhitung TIGA KALI — dan fan-out
 *     Fase 3 memang sengaja memasang pointer versi aktif pada semua item
 *     perujuk, jadi penggandaan itu bukan kebetulan melainkan konsekuensi;
 *   - dokumen yang belum masuk paket mana pun TIDAK TERLIHAT SAMA SEKALI —
 *     bukan nol, tetapi tak ada barisnya;
 *   - yang terbaca adalah Judul_Item & Nomor_SOP_Resmi milik item paket,
 *     yakni CERMINAN, bukan identitas dokumen yang jadi sumber kebenaran.
 *
 * Service ini berangkat dari N_SOP_Dokumen — rumah identitas itu sendiri.
 *
 * TIGA KELOMPOK, SALING LEPAS, MENJUMLAH TEPAT KE TOTAL. Syarat itu dipegang
 * keras: angka yang tak menjumlah adalah sumber keraguan yang tak pernah
 * selesai, dan dashboard yang diragukan tak dipakai siapa pun.
 */
class SopDokumenKesehatanService
{
    /* KOSAKATA KELOMPOK — dipinjam, bukan didefinisikan ulang.
       Layar ini dan Export Studio dulu memakai dua kosakata berbeda untuk
       klasifikasi yang sama (FUNGSIONAL/PUBLIK di sini, WAJIB/TERBUKA di sana),
       sehingga dokumen yang sama disebut berbeda di dua layar. Nilainya kini
       hanya ada di SopDokumenKelompok; alias di bawah dipertahankan supaya
       pemanggil lama tak perlu diubah serentak. */
    public const DITUGASKAN = SopDokumenKelompok::DITUGASKAN;

    public const BEREDAR = SopDokumenKelompok::BEREDAR;

    public const BELUM_BEREDAR = SopDokumenKelompok::BELUM_BEREDAR;

    /**
     * Sumber penugasan yang berarti KEWAJIBAN.
     *
     * MANDIRI sengaja di luar daftar: ia lahir dari karyawan yang membuka SOP
     * publik, bukan dari seorang admin atau aturan yang mewajibkannya.
     *
     * Dipinjam dari SopGerbangPaket supaya daftar ini tak bisa menyimpang dari
     * gerbang yang memakainya.
     */
    public const SUMBER_KEWAJIBAN = SopGerbangPaket::SUMBER_KEWAJIBAN;

    /** Urutan tampil; juga urutan prioritas klasifikasi (ditugaskan menang). */
    public const KELOMPOK = [self::DITUGASKAN, self::BEREDAR, self::BELUM_BEREDAR];

    use SaringanMonitoring;

    public function __construct(private SopMonitoringPeopleService $people) {}

    /**
     * Seluruh isi lapis dokumen dalam SATU panggilan.
     *
     * Dipisah menjadi enam permintaan akan membuat kartu komposisi tergambar
     * lebih dulu, lalu piramid menyusul dengan total yang sekilas berbeda —
     * dan admin membaca selisih sesaat itu sebagai data yang tak konsisten.
     */
    public function kesehatan(array $f = []): array
    {
        /* DI-CACHE SEPERTI SELURUH PANEL MONITORING LAIN.
           Panel ini sengaja membundel delapan potongan menjadi satu panggilan
           (lihat alasannya di atas), dan harganya 18 kueri sekali jalan —
           terbanyak di antara seluruh endpoint monitoring. Ia satu-satunya yang
           tak pernah di-cache, sehingga tiap pembukaan layar membayar penuh.

           TTL 600 detik menyamai momentum dan peringkat unit; kuncinya lewat
           mungkinCache() sehingga saringan ikut dinormalkan dan tak melahirkan
           kunci berbeda untuk saringan yang sebenarnya sama. */
        return $this->mungkinCache('sop_dok_kesehatan', 600, $f, function () use ($f) {
            $dok = $this->klasifikasi($f);

            return [
                'Komposisi' => $this->komposisi($dok),
                'Sebaran' => $this->sebaran($dok),
                'Versi_Menggantung' => $this->versiMenggantung(),
                'Antrean' => $this->antrean($dok),
                'Tersaring_Gerbang_Publik' => $this->tersaringGerbangPublik($f),
                'Lead_Time' => $this->leadTime(),
                'Coverage_Dokumen' => $this->coverageDokumen(),
                'Coverage_Paket' => $this->coveragePaket(),
            ];
        });
    }

    /**
     * Satu baris per dokumen, sudah berlabel kelompoknya.
     *
     * Dihitung SEKALI lalu dipakai seluruh potongan di atas. Menghitung ulang
     * per potongan berarti sub-kueri yang sama berkali-kali — dan lebih buruk
     * lagi, sekian peluang definisi kelompok menyimpang satu sama lain.
     *
     * TIGA KUERI SEDERHANA, PENGGABUNGAN DI PHP. Rancangan pertama menanam
     * EXISTS ke dalam sebuah CASE, yang menuntut sub-kueri diubah jadi SQL
     * mentah berikut nilainya. Itu dibatalkan: menjahit binding ke dalam teks
     * SQL rapuh pada nilai yang memuat tanda tanya — tanda tanya di dalam
     * nilai yang baru disisipkan akan tergantikan binding berikutnya — dan
     * kerapuhan itu duduk tepat di jalur yang menerima query string.
     *
     * Jumlah dokumen berorde puluhan sampai ratusan, jadi menggabungkan di
     * PHP tak menimbulkan persoalan yang layak ditukar dengan risiko itu.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function klasifikasi(array $f = [])
    {
        $fungsional = $this->idFungsional($f);
        $beredar = $this->idBeredar();

        return DB::table('N_SOP_Dokumen as dok')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as j', 'j.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Master_APQC as a', 'a.Id_SOP_APQC', '=', 'dok.APQC_Id')
            ->leftJoin('N_HRIS_Master_Departemen as dp', 'dp.Id_Departemen', '=', 'dok.Departemen_Id')
            ->where('dok.Flag_Aktif', SopStatus::AKTIF)
            /* Dokumen yang sudah dipensiunkan keluar dari populasi kesehatan
               (DDL-2026-055). Ia tak lagi bisa diperbaiki maupun dipasang, jadi
               menghitungnya hanya menambah temuan yang tak bisa ditindaklanjuti
               siapa pun. Penonaktifan TIDAK membalik Flag_Aktif, jadi saringan
               ini tak tergantikan oleh baris di atasnya. */
            ->whereNull('dok.Nonaktif_At')
            ->get([
                'dok.Id_SOP_Dokumen', 'dok.Judul', 'dok.Nomor_SOP_Resmi', 'dok.Tipe',
                'dok.Jenis_Dokumen_Id', 'dok.APQC_Id', 'dok.Departemen_Id',
                'j.Nama_Jenis', 'j.Kode_Jenis_Dokumen', 'j.Level_Hirarki',
                'a.Kode_Proses', 'a.Nama_Proses',
                'dp.Nama_Departemen',
            ])
            ->map(function ($d) use ($fungsional, $beredar) {
                $id = (int) $d->Id_SOP_Dokumen;
                /* FUNGSIONAL MENANG. Satu dokumen bisa dijadwalkan di paket A dan
                   cuma dibuka di paket B; yang menentukan adalah ada tidaknya
                   KEWAJIBAN, di mana pun. Urutan inilah yang menegakkannya —
                   bukan sebuah if di layar. */
                $d->Kelompok = isset($fungsional[$id]) ? self::DITUGASKAN
                    : (isset($beredar[$id]) ? self::BEREDAR : self::BELUM_BEREDAR);

                return $d;
            });
    }

    /**
     * Id dokumen yang punya penugasan aktif, sebagai peta untuk isset().
     *
     * MENGIKUTI GERBANG YANG SAMA dengan lapis kepatuhan
     * (SopMonitoringPeopleService::terapkanSaringanPaket): penugasan pada
     * paket PUBLIK hanya dihitung bila karyawannya sudah pernah membukanya.
     *
     * Gerbang itu punya cacat yang sudah dikenal — penugasan pada paket publik
     * yang belum dibuka siapa pun hilang dari seluruh laporan. Mengabaikannya
     * di sini akan membuat dashboard menyebut angka yang berbeda dari tab
     * Karyawan, dan dua angka untuk satu hal adalah kerusakan yang lebih besar
     * daripada cacat yang sudah tercatat. Jalan tengahnya: ikuti gerbangnya,
     * lalu LAPORKAN yang tersaring sebagai angka bernama —
     * lihat tersaringGerbangPublik().
     *
     * @return array<int, true>
     */
    private function idFungsional(array $f): array
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('i.Id_SOP_Dokumen')
            /* HANYA penugasan yang benar-benar MEWAJIBKAN.
               N_SOP_Progress.Id_SOP_Penjadwalan_Detail NOT NULL, jadi tak ada
               tempat menyimpan jejak baca tanpa penugasan. Karena itu karyawan
               yang membuka SOP PUBLIK didaftarkan sendiri oleh sistem
               (SopPendaftaranMandiri, Sumber='MANDIRI', Created_By NULL).
               Tanpa penyaring ini, dokumen publik BERPINDAH ke kelompok
               Fungsional begitu satu orang membacanya sukarela — "wajib"
               berhenti berarti wajib. */
            ->whereIn('pj.Sumber', self::SUMBER_KEWAJIBAN);

        /* RENTANG TANGGAL DILEPAS DI SINI, saringan lain tetap.
           Kelompok sebuah dokumen (fungsional / publik / belum beredar) adalah
           sifatnya SEKARANG, bukan sifatnya pada sebuah jendela waktu. Dengan
           rentang ikut menggigit, dokumen yang ditugaskan enam bulan lalu
           berpindah jadi "belum beredar" begitu layar disetel ke 30 hari
           terakhir — dan seluruh lapis dokumen (komposisi, sebaran, antrean
           "sah tapi tak beredar", kalimat penyebut) ikut berbohong bersamanya.

           Yang memang berjendela adalah AKTIVITASNYA — penyelesaian, laju,
           peringkat unit — dan itu dihitung di tempat lain. */
        $tanpaTanggal = $f;
        unset($tanpaTanggal['date_start'], $tanpaTanggal['date_end'],
            $tanpaTanggal['dari'], $tanpaTanggal['sampai'], $tanpaTanggal['date_mode']);

        $this->people->terapkanSaringanPaket($q, $tanpaTanggal, 'p', 'pj', 'd');
        $this->people->terapkanSaringanOrang($q, $tanpaTanggal, 'd');

        return $q->distinct()->pluck('i.Id_SOP_Dokumen')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /**
     * Id dokumen yang ada di setidaknya satu paket TERBIT.
     *
     * Tanpa saringan organisasi: "beredar" adalah sifat dokumen terhadap
     * paket, bukan terhadap sekelompok karyawan. Menyaringnya dengan
     * departemen akan membuat dokumen yang sama berpindah kelompok hanya
     * karena saringan digeser.
     *
     * @return array<int, true>
     */
    private function idBeredar(): array
    {
        /* Predikatnya TIDAK ditulis ulang di sini melainkan dipinjam dari
           SopDokumenKelompok, karena dataset Export Studio menanam aturan yang
           sama sebagai ekspresi SELECT. Dua penulisan untuk satu aturan adalah
           awal dari dua angka untuk satu hal. */
        return DB::table('N_SOP_Dokumen as dok')
            ->where('dok.Flag_Aktif', SopStatus::AKTIF)
            // Lihat catatan di klasifikasi(): dokumen nonaktif keluar dari populasi.
            ->whereNull('dok.Nonaktif_At')
            ->whereRaw(SopDokumenKelompok::sqlBeredar('dok'))
            ->pluck('dok.Id_SOP_Dokumen')
            ->mapWithKeys(fn ($id) => [(int) $id => true])->all();
    }

    /** Tiga angka yang menjumlah tepat ke total. */
    private function komposisi($dok): array
    {
        $total = $dok->count();
        $per = $dok->countBy('Kelompok');

        $baris = [];
        foreach (self::KELOMPOK as $k) {
            $n = (int) ($per[$k] ?? 0);
            $baris[] = [
                'Kelompok' => $k,
                'Nama' => self::nama($k),
                'Keterangan' => self::keterangan($k),
                'Jumlah' => $n,
                'Persen' => $total > 0 ? round($n * 100 / $total, 1) : 0.0,
            ];
        }

        return ['Total' => $total, 'Baris' => $baris];
    }

    /**
     * Katalog sumbu sebaran — SATU kartu, banyak cara membelah populasi yang sama.
     *
     * Sebelum ini jenis dokumen dan proses APQC berdiri sebagai dua kartu
     * terpisah, sementara kepemilikan unit sudah dihitung tetapi tak pernah
     * tergambar sama sekali. Tiga kartu untuk satu pertanyaan — "dokumen kita
     * bertumpuk di mana?" — memakan tinggi layar tanpa menambah jawaban, dan
     * dashboard yang panjang berhenti dibaca sebelum bagian bawahnya. Sumbunya
     * bertambah, kartunya berkurang.
     *
     * 'Param' adalah nama saringan di halaman Master Dokumen. Ia ada supaya
     * tautan "buka daftarnya" benar-benar menyaring: piramid jenis sebelumnya
     * mengirim id MENTAH ke ?jenis=, padahal halaman itu men-decode hashid,
     * sehingga saringannya batal tanpa satu pun tanda dan admin melihat SELURUH
     * dokumen di layar yang menjanjikan satu tingkat saja. Nilai tautannya kini
     * disiapkan di sini (medan 'Nilai' pada tiap baris), bukan dikarang layar.
     */
    public const SUMBU_SEBARAN = [
        'jenis' => [
            'Nama' => 'Jenis Dokumen',
            'Ikon' => 'bar-chart-steps',
            'Satuan' => 'tingkat',
            'Keterangan' => 'Tingkat hirarki mutu — kebijakan di puncak, instruksi kerja di dasar.',
            'Param' => 'jenis',
        ],
        'apqc' => [
            'Nama' => 'Proses APQC',
            'Ikon' => 'diagram-3',
            'Satuan' => 'proses',
            'Keterangan' => 'Proses bisnis yang dilayani dokumen, termasuk yang belum punya satu pun.',
            'Param' => 'apqc',
        ],
        'unit' => [
            'Nama' => 'Unit Pemilik',
            'Ikon' => 'building',
            'Satuan' => 'unit',
            'Keterangan' => 'Departemen yang bertanggung jawab merevisinya.',
            'Param' => 'departemen',
        ],
        'tipe' => [
            'Nama' => 'Tipe Isi',
            'Ikon' => 'collection',
            'Satuan' => 'tipe',
            'Keterangan' => 'Bentuk isinya — dokumen baca, formulir, video, atau kuis.',
            'Param' => 'tipe',
        ],
        'paket' => [
            'Nama' => 'Paket Penugasan',
            'Ikon' => 'box-seam',
            'Satuan' => 'paket',
            'Keterangan' => 'Berapa dokumen yang dibawa tiap paket, dan mana yang masih kosong.',
            'Param' => null,
        ],
    ];

    /**
     * Seluruh sumbu dihitung SEKALIGUS, bukan satu per permintaan.
     *
     * Empat dari lima sumbu hanya mengelompokkan koleksi yang sudah ada di
     * tangan; hanya sumbu paket yang menambah kueri. Menghitung semuanya di
     * muka membuat perpindahan sumbu berlangsung seketika — tanpa permintaan,
     * tanpa pemuat berputar — dan itulah seluruh alasan lima kartu diringkas
     * jadi satu. Satu permintaan per pergantian sumbu akan menukar tinggi layar
     * dengan jeda, yang bukan pertukaran yang lebih baik.
     */
    public function sebaran($dok): array
    {
        $data = [
            'jenis' => $this->sebaranJenis($dok),
            'apqc' => $this->sebaranApqc($dok),
            'unit' => $this->sebaranUnit($dok),
            'tipe' => $this->sebaranTipe($dok),
            'paket' => $this->sebaranPaket($dok),
        ];

        $sumbu = [];
        foreach (self::SUMBU_SEBARAN as $kunci => $meta) {
            $sumbu[] = ['Kunci' => $kunci] + $meta + ['Baris' => count($data[$kunci]['Baris'])];
        }

        return ['Total' => $dok->count(), 'Sumbu' => $sumbu, 'Data' => $data];
    }

    /**
     * Bentuk baris yang SAMA untuk semua sumbu.
     *
     * Layar tak boleh bercabang per sumbu: satu cabang saja akan tumbuh jadi
     * lima tampilan yang perlahan menyimpang, dan sumbu keenam nanti akan
     * menuntut cabang keenam. Bawaannya ditulis lengkap supaya medan yang tak
     * relevan bagi sebuah sumbu tetap ADA sebagai null, bukan hilang.
     */
    private function barisSebaran(array $inti, $anggota): array
    {
        return array_replace(
            ['Id' => null, 'Nilai' => null, 'Kode' => null, 'Nama' => '', 'Label' => null, 'Tanpa' => false],
            $inti,
            ['Total' => $anggota->count()],
            $this->pecahKelompok($anggota),
        );
    }

    /**
     * Pembungkus satu sumbu.
     *
     * 'Menjumlah' menyatakan apakah seluruh batang menjumlah tepat ke total
     * dokumen. Itu bukan hiasan: sumbu paket TIDAK menjumlah, dan sumbu yang
     * tak menjumlah tanpa mengatakannya adalah persis sumber keraguan yang
     * membuat dashboard berhenti dipercaya.
     */
    private function bungkusSumbu(array $baris, bool $menjumlah = true, ?string $catatan = null): array
    {
        return [
            'Baris' => $baris,
            'Kosong' => count(array_filter($baris, fn ($b) => $b['Total'] === 0 && ! $b['Tanpa'])),
            'Menjumlah' => $menjumlah,
            'Catatan' => $catatan,
        ];
    }

    /**
     * Jenis dokumen — piramid mutu, urut menurut tingkat hirarki.
     *
     * Jenis yang NOL tetap ditampilkan. Tingkat piramid yang kosong justru
     * temuan paling penting di sini; menyembunyikannya karena tak ada datanya
     * membuat ketimpangan tak pernah terlihat.
     */
    private function sebaranJenis($dok): array
    {
        $master = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Level_Hirarki')
            ->get(['Id_SOP_Jenis_Dokumen', 'Nama_Jenis', 'Kode_Jenis_Dokumen', 'Level_Hirarki']);

        $baris = [];
        foreach ($master as $m) {
            $id = (int) $m->Id_SOP_Jenis_Dokumen;
            $baris[] = $this->barisSebaran([
                'Id' => $id,
                'Nilai' => $this->sandi($id),
                'Kode' => $m->Kode_Jenis_Dokumen,
                'Nama' => $m->Nama_Jenis,
                'Label' => 'Tk. '.(int) $m->Level_Hirarki,
            ], $dok->where('Jenis_Dokumen_Id', $m->Id_SOP_Jenis_Dokumen));
        }

        /* Dokumen tanpa jenis TIDAK dibuang diam-diam — ia justru pekerjaan
           yang menunggu. Baris ini hanya muncul bila memang ada isinya, dan
           tautannya memakai sandi 'BELUM' yang sudah dikenal Master Dokumen. */
        $tanpa = $dok->whereNull('Jenis_Dokumen_Id');
        if ($tanpa->count() > 0) {
            $baris[] = $this->barisSebaran([
                'Nilai' => 'BELUM', 'Nama' => 'Belum berjenis', 'Label' => 'Lain', 'Tanpa' => true,
            ], $tanpa);
        }

        return $this->bungkusSumbu($baris);
    }

    /**
     * Sebaran proses APQC — termasuk yang KOSONG.
     *
     * Proses tanpa satu pun SOP adalah lubang tata kelola, dan lubang tak bisa
     * dilihat dari daftar yang hanya memuat yang terisi.
     */
    private function sebaranApqc($dok): array
    {
        $master = DB::table('N_SOP_Master_APQC')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')
            ->get(['Id_SOP_APQC', 'Kode_Proses', 'Nama_Proses']);

        $baris = [];
        foreach ($master as $m) {
            $id = (int) $m->Id_SOP_APQC;
            $baris[] = $this->barisSebaran([
                'Id' => $id,
                'Nilai' => $this->sandi($id),
                'Kode' => $m->Kode_Proses,
                'Nama' => $m->Nama_Proses,
            ], $dok->where('APQC_Id', $m->Id_SOP_APQC));
        }

        $tanpa = $dok->whereNull('APQC_Id');
        if ($tanpa->count() > 0) {
            $baris[] = $this->barisSebaran([
                'Nilai' => 'BELUM', 'Nama' => 'Belum dipetakan ke proses', 'Tanpa' => true,
            ], $tanpa);
        }

        return $this->bungkusSumbu($baris);
    }

    /**
     * Kepemilikan unit. Yang tak bertuan disebut lebih dulu.
     *
     * Dokumen tanpa pemilik unit tak punya siapa pun yang bertanggung jawab
     * merevisinya — itu keadaan yang menuntut tindakan, bukan sekadar sel
     * kosong di tabel. Karena itu barisnya berdiri PALING ATAS di sini,
     * berbeda dari sumbu lain yang menaruh baris "tanpa" di ujung.
     *
     * Hanya unit yang benar-benar memiliki dokumen yang muncul: daftar seluruh
     * departemen perusahaan akan didominasi baris nol dari unit yang memang tak
     * pernah menerbitkan SOP, dan itu bukan temuan melainkan kebisingan.
     */
    private function sebaranUnit($dok): array
    {
        $baris = [];

        $tanpa = $dok->whereNull('Departemen_Id');
        if ($tanpa->count() > 0) {
            $baris[] = $this->barisSebaran([
                'Nilai' => 'BELUM', 'Nama' => 'Tanpa pemilik unit', 'Tanpa' => true,
            ], $tanpa);
        }

        $unit = $dok->whereNotNull('Departemen_Id')
            ->groupBy('Departemen_Id')
            ->map(fn ($anggota) => $this->barisSebaran([
                'Id' => (int) $anggota->first()->Departemen_Id,
                'Nilai' => $this->sandi((int) $anggota->first()->Departemen_Id),
                'Nama' => $anggota->first()->Nama_Departemen ?: 'Departemen tak dikenal',
            ], $anggota))
            ->sortByDesc('Total')
            ->values()
            ->all();

        return $this->bungkusSumbu(array_merge($baris, $unit));
    }

    /**
     * Tipe isi — bentuk dokumennya, bukan perannya.
     *
     * Hanya tipe yang benar-benar terpakai yang muncul. Tipe tanpa dokumen di
     * sini bukan lubang tata kelola melainkan pilihan bentuk yang kebetulan tak
     * dipakai, jadi menampilkannya sebagai baris nol akan meniru bentuk temuan
     * untuk sesuatu yang tak menuntut apa pun.
     */
    private function sebaranTipe($dok): array
    {
        $baris = $dok->groupBy('Tipe')
            ->map(fn ($anggota, $tipe) => $this->barisSebaran([
                'Nilai' => (string) $tipe,
                'Kode' => (string) $tipe,
                'Nama' => ucfirst(strtolower((string) $tipe)),
            ], $anggota))
            ->sortByDesc('Total')
            ->values()
            ->all();

        return $this->bungkusSumbu($baris);
    }

    /**
     * Sebaran per paket — berapa dokumen yang dibawa tiap paket.
     *
     * SUMBU INI TIDAK MENJUMLAH KE TOTAL, dan itu sifat datanya, bukan cacat:
     * satu dokumen boleh dipakai beberapa paket sekaligus — fan-out Fase 3
     * memang dirancang begitu. Angka gandanya karena itu DISEBUT, bukan
     * disembunyikan atau diam-diam dibagi rata; pembaca yang menjumlahkan
     * batangnya sendiri lalu menemukan hasilnya melebihi total akan berhenti
     * mempercayai seluruh kartu.
     *
     * Paket DRAFT ikut ditampilkan dengan lencananya. Dokumen yang hanya duduk
     * di paket draft tetap terhitung "belum beredar", dan baris inilah yang
     * menjelaskan sebabnya — membuang paket draft akan membuat dokumen itu
     * tampak tak dipakai paket mana pun sama sekali.
     *
     * Paket KOSONG juga ikut: paket tanpa satu dokumen pun adalah pekerjaan
     * yang berhenti di tengah, dan itu hanya terlihat kalau barisnya ada.
     */
    private function sebaranPaket($dok): array
    {
        $pakets = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('Status', [SopStatus::PAKET_DRAFT, SopStatus::PAKET_PUBLISHED])
            ->get(['Id_SOP_Paket', 'Nama_Paket', 'Kode_Paket', 'Status']);

        if ($pakets->isEmpty()) {
            return $this->bungkusSumbu([]);
        }

        /* Peta id => baris dokumen. Isi paket yang menunjuk dokumen di luar
           populasi (nonaktif, atau Flag_Aktif='T') sengaja dilewati: ia tak
           boleh menambah tinggi batang yang mengaku menghitung dokumen hidup. */
        $byId = $dok->mapWithKeys(fn ($d) => [(int) $d->Id_SOP_Dokumen => $d]);

        $isi = DB::table('N_SOP_Paket_Item')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->whereIn('Id_SOP_Paket', $pakets->pluck('Id_SOP_Paket')->all())
            ->get(['Id_SOP_Paket', 'Id_SOP_Dokumen']);

        /* Satu paket bisa memuat DUA item yang menunjuk dokumen yang sama —
           keadaan yang memang terjadi dan sudah tercatat. Dipasangkan lewat
           himpunan supaya dokumen itu terhitung sekali per paket; tanpa ini
           batangnya menghitung ITEM sedangkan judulnya menjanjikan DOKUMEN. */
        $pasangan = [];
        $perPaket = [];
        $paketPerDok = [];
        foreach ($isi as $r) {
            $idPaket = (int) $r->Id_SOP_Paket;
            $idDok = (int) $r->Id_SOP_Dokumen;
            $kunci = $idPaket.':'.$idDok;
            if (isset($pasangan[$kunci]) || ! $byId->has($idDok)) {
                continue;
            }
            $pasangan[$kunci] = true;
            $perPaket[$idPaket][] = $byId->get($idDok);
            $paketPerDok[$idDok] = ($paketPerDok[$idDok] ?? 0) + 1;
        }

        $baris = [];
        foreach ($pakets as $p) {
            $id = (int) $p->Id_SOP_Paket;
            $baris[] = $this->barisSebaran([
                'Id' => $id,
                'Kode' => $p->Kode_Paket,
                'Nama' => $p->Nama_Paket,
                'Label' => $p->Status === SopStatus::PAKET_PUBLISHED ? null : 'Draft',
            ], collect($perPaket[$id] ?? []));
        }

        $baris = collect($baris)->sortByDesc('Total')->values()->all();

        $lepas = $dok->reject(fn ($d) => isset($paketPerDok[(int) $d->Id_SOP_Dokumen]));
        if ($lepas->count() > 0) {
            $baris[] = $this->barisSebaran([
                'Nilai' => null, 'Nama' => 'Belum masuk paket mana pun', 'Tanpa' => true,
            ], $lepas);
        }

        $ganda = count(array_filter($paketPerDok, fn ($n) => $n > 1));
        $tempat = array_sum($paketPerDok);

        return $this->bungkusSumbu(
            $baris,
            $ganda === 0,
            $ganda === 0 ? null : $ganda.' dokumen dipakai lebih dari satu paket, jadi '
                .$dok->count().' dokumen menempati '.$tempat.' tempat. '
                .'Menjumlahkan seluruh batang tidak menghasilkan jumlah dokumen.'
        );
    }

    /**
     * Bobot tiap jenis temuan — URUTAN KERJANYA, bukan urutan abjad.
     *
     * Diurut menurut biaya MEMBIARKANNYA, bukan menurut mudahnya dikerjakan:
     *
     *  - Versi yang menunggu tanda tangan menahan seluruh rantai di belakangnya;
     *    tiap hari tambahan adalah hari orang membaca versi lama.
     *  - Dokumen yang sudah SAH tetapi tak masuk paket mana pun adalah kerja
     *    yang sudah selesai lalu berhenti sebelum sampai — kerugian terbesar
     *    per satuan usaha yang sudah dikeluarkan.
     *  - Proses tanpa satu SOP pun adalah lubang yang tak seorang pun sadari,
     *    karena ketiadaan tak pernah muncul di daftar mana pun.
     *  - Identitas yang belum lengkap menghambat pencarian dan revisi, tetapi
     *    tak menghentikan siapa pun hari ini.
     */
    private const BOBOT = [
        'MENUNGGU_SAH' => 1,
        'TERBIT_TAK_BEREDAR' => 2,
        'MACET_REVIEW' => 3,
        'PROSES_KOSONG' => 4,
        'MACET_DRAFTING' => 5,
        'TANPA_PEMILIK' => 6,
        'TANPA_PROSES' => 7,
    ];

    /**
     * Antrean kerja — daftar yang MENYEBUT NAMANYA.
     *
     * Menggantikan empat penghitung yang dulu berdiri di kartu "Yang Menunggu
     * Dikerjakan". Angka tanpa nama tak bisa ditindaklanjuti: "9 dokumen tanpa
     * pemilik unit" menyisakan seluruh pekerjaan berburu kepada pembacanya, dan
     * angka yang tak menunjuk apa pun berhenti menjadi pekerjaan lalu menjadi
     * keluhan.
     *
     * Tiap baris membawa empat hal yang dibutuhkan untuk bertindak: APA yang
     * kurang, PADA apa, SUDAH BERAPA LAMA, dan ke mana harus pergi.
     *
     * @return array<int, array>
     */
    public function antrean($dok, int $batas = 30): array
    {
        $baris = array_merge(
            $this->antreanVersiMacet(),
            $this->antreanTerbitTakBeredar($dok),
            $this->antreanProsesKosong($dok),
            $this->antreanIdentitasKurang($dok),
        );

        /* Bobot lebih dulu, lalu umur MENURUN di dalam bobot yang sama: yang
           paling lama terbengkalai naik ke atas kelompoknya sendiri. */
        usort($baris, function ($a, $b) {
            $w = (self::BOBOT[$a['Jenis']] ?? 99) <=> (self::BOBOT[$b['Jenis']] ?? 99);

            return $w !== 0 ? $w : (($b['Umur_Hari'] ?? 0) <=> ($a['Umur_Hari'] ?? 0));
        });

        /* Pemotongan DILAPORKAN, tak pernah diam-diam: daftar yang terpotong
           tanpa tanda terbaca sebagai "cuma segini pekerjaannya". */
        return ['Baris' => array_slice($baris, 0, $batas), 'Total' => count($baris)];
    }

    /**
     * Versi yang berhenti sebelum terbit, disebut satu per satu.
     *
     * Tahap Pengesahan dan Pemberkasan dipisahkan dari Drafting/Review: yang
     * pertama menunggu ORANG LAIN bertindak, yang kedua menunggu penulisnya
     * sendiri. Keduanya pekerjaan, tetapi bukan pekerjaan orang yang sama.
     */
    private function antreanVersiMacet(): array
    {
        $rows = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Master_Tahap_Versi as t', 't.Id_Tahap', '=', 'v.Tahap_Id')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            // Lihat catatan di klasifikasi(): dokumen nonaktif keluar dari populasi.
            ->whereNull('d.Nonaktif_At')
            ->where('v.Status', SopStatus::VERSI_DRAFT)
            ->whereNotNull('t.Langkah')
            ->orderBy('v.Created_At')
            ->get(['v.Id_SOP_Item_Versi', 'v.Versi_Ke', 'v.Created_At',
                'd.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi',
                't.Kode_Tahap', 't.Nama_Tahap', 't.Langkah']);

        $out = [];
        foreach ($rows as $r) {
            $menungguOrangLain = in_array($r->Kode_Tahap, ['APPROVAL', 'APPROVED'], true);

            $out[] = [
                'Jenis' => $menungguOrangLain ? 'MENUNGGU_SAH'
                    : ($r->Kode_Tahap === 'REVIEW' ? 'MACET_REVIEW' : 'MACET_DRAFTING'),
                'Nama_Jenis' => $menungguOrangLain ? 'Menunggu pengesahan' : 'Berhenti di '.$r->Nama_Tahap,
                'Judul' => $r->Judul,
                'Nomor' => $r->Nomor_SOP_Resmi,
                'Keterangan' => $menungguOrangLain
                    ? 'Versi '.$r->Versi_Ke.' menunggu tanda tangan di '.$r->Nama_Tahap.'.'
                    : 'Versi '.$r->Versi_Ke.' berhenti di '.$r->Nama_Tahap.' dan belum dilanjutkan.',
                'Umur_Hari' => $this->umurHari($r->Created_At),
                'Id_Dokumen' => $this->sandi((int) $r->Id_SOP_Dokumen),
                'Id_Versi' => $this->sandi((int) $r->Id_SOP_Item_Versi),
            ];
        }

        return $out;
    }

    /**
     * Sudah sah, tetapi tak sampai ke siapa pun.
     *
     * Bukan sekadar "belum beredar": yang disorot di sini adalah dokumen yang
     * versinya SUDAH TERBIT — seluruh kerja penyusunan dan pengesahan sudah
     * dikeluarkan, lalu berhenti satu langkah sebelum sampai.
     */
    private function antreanTerbitTakBeredar($dok): array
    {
        $kandidat = $dok->where('Kelompok', self::BELUM_BEREDAR)->pluck('Id_SOP_Dokumen')->all();
        if ($kandidat === []) {
            return [];
        }

        $rows = DB::table('N_SOP_Dokumen as d')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->whereIn('d.Id_SOP_Dokumen', $kandidat)
            ->get(['d.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi', 'v.Versi_Ke', 'v.Tanggal_Publish']);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'Jenis' => 'TERBIT_TAK_BEREDAR',
                'Nama_Jenis' => 'Sah tapi tak beredar',
                'Judul' => $r->Judul,
                'Nomor' => $r->Nomor_SOP_Resmi,
                'Keterangan' => 'Versi '.$r->Versi_Ke.' sudah terbit tetapi belum masuk paket mana pun, '
                    .'jadi belum sampai ke siapa pun.',
                'Umur_Hari' => $this->umurHari($r->Tanggal_Publish),
                'Id_Dokumen' => $this->sandi((int) $r->Id_SOP_Dokumen),
                'Id_Versi' => null,
            ];
        }

        return $out;
    }

    /**
     * Proses APQC yang belum punya satu SOP pun.
     *
     * Satu-satunya baris yang menamai PROSES, bukan dokumen — dan itu memang
     * bentuk temuannya: ketiadaan tak pernah muncul di daftar dokumen mana pun,
     * jadi ia harus disebut dari sisi masternya.
     */
    private function antreanProsesKosong($dok): array
    {
        $terpakai = $dok->pluck('APQC_Id')->filter()->unique()->all();

        $rows = DB::table('N_SOP_Master_APQC')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->when($terpakai !== [], fn ($q) => $q->whereNotIn('Id_SOP_APQC', $terpakai))
            ->orderBy('Urutan')
            ->get(['Kode_Proses', 'Nama_Proses']);

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'Jenis' => 'PROSES_KOSONG',
                'Nama_Jenis' => 'Proses tanpa dokumen',
                'Judul' => $r->Nama_Proses,
                'Nomor' => $r->Kode_Proses,
                'Keterangan' => 'Proses bisnis ini belum punya satu dokumen pun.',
                'Umur_Hari' => null,
                'Id_Dokumen' => null,
                'Id_Versi' => null,
            ];
        }

        return $out;
    }

    /** Identitas yang belum lengkap — menghambat pencarian dan revisi. */
    private function antreanIdentitasKurang($dok): array
    {
        $out = [];

        foreach ($dok->whereNull('Departemen_Id') as $d) {
            $out[] = [
                'Jenis' => 'TANPA_PEMILIK',
                'Nama_Jenis' => 'Tanpa pemilik unit',
                'Judul' => $d->Judul,
                'Nomor' => $d->Nomor_SOP_Resmi,
                'Keterangan' => 'Belum ada unit yang bertanggung jawab merevisinya.',
                'Umur_Hari' => null,
                'Id_Dokumen' => $this->sandi((int) $d->Id_SOP_Dokumen),
                'Id_Versi' => null,
            ];
        }

        foreach ($dok->whereNull('APQC_Id') as $d) {
            $out[] = [
                'Jenis' => 'TANPA_PROSES',
                'Nama_Jenis' => 'Tanpa proses APQC',
                'Judul' => $d->Judul,
                'Nomor' => $d->Nomor_SOP_Resmi,
                'Keterangan' => 'Belum dipetakan ke proses bisnis mana pun.',
                'Umur_Hari' => null,
                'Id_Dokumen' => $this->sandi((int) $d->Id_SOP_Dokumen),
                'Id_Versi' => null,
            ];
        }

        return $out;
    }

    /** Hashid koneksi custom — bentuk id yang sama dengan yang diterima rute. */
    private function sandi(int $id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    private function umurHari($waktu): ?int
    {
        return $waktu
            ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($waktu)->startOfDay())
            : null;
    }

    /**
     * Versi yang berhenti sebelum terbit, per tahap, beserta umur tertuanya.
     *
     * TIDAK ikut saringan organisasi: ini keadaan dokumen di meja redaksi,
     * bukan keadaan penugasan pada sekelompok orang. Menyaringnya dengan
     * departemen karyawan akan menjawab pertanyaan yang tak seorang pun
     * ajukan.
     */
    private function versiMenggantung(): array
    {
        $rows = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Master_Tahap_Versi as t', 't.Id_Tahap', '=', 'v.Tahap_Id')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Status', SopStatus::VERSI_DRAFT)
            ->whereNotNull('t.Langkah')
            ->groupBy('t.Id_Tahap', 't.Kode_Tahap', 't.Nama_Tahap', 't.Warna', 't.Urutan', 't.Langkah')
            ->orderBy('t.Urutan')
            ->selectRaw('t.Kode_Tahap, t.Nama_Tahap, t.Warna, t.Langkah,
                         COUNT(*) as Jumlah, MIN(v.Created_At) as Tertua')
            ->get();

        $total = 0;
        $baris = [];
        foreach ($rows as $r) {
            $total += (int) $r->Jumlah;
            $baris[] = [
                'Kode_Tahap' => $r->Kode_Tahap,
                'Nama_Tahap' => $r->Nama_Tahap,
                'Warna' => $r->Warna,
                'Langkah' => (int) $r->Langkah,
                'Jumlah' => (int) $r->Jumlah,
                'Umur_Hari' => $r->Tertua
                    ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($r->Tertua)->startOfDay())
                    : null,
            ];
        }

        return ['Total' => $total, 'Baris' => $baris];
    }

    /**
     * Penugasan yang HILANG karena gerbang paket publik.
     *
     * Angka ini ada supaya gerbang itu berhenti bekerja diam-diam. Selama ia
     * tak pernah disebut, penugasan pada paket publik yang belum dibuka
     * siapa pun lenyap dari seluruh laporan tanpa satu pun tanda — dan
     * seseorang suatu hari akan menyimpulkan penugasannya tak pernah dibuat.
     */
    private function tersaringGerbangPublik(array $f): array
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('p.Mode_Distribusi', 'PUBLIK')
            /* Cerminan terbalik dari gerbang di terapkanSaringanPaket(): angka ini
               hanya bermakna bila ia menghitung PERSIS yang gerbang itu buang.
               Penugasan MANUAL/AUTO_RULE tidak lagi dibuang — ia kewajiban sejak
               ditugaskan — sehingga melaporkannya sebagai "tersaring" akan
               membuat kartu ini berbohong tentang hal yang justru menjadi
               alasan keberadaannya.

               Syaratnya tersalin di sini, bukan diturunkan dari gerbangnya,
               karena bentuk kueri keduanya berlawanan. Yang menjaga keduanya
               tetap sepakat adalah uji, bukan kode bersama:
               @see SopPenugasanPaketPublikTest::test_penghitung_tersaring_sepakat_dengan_gerbangnya() */
            ->whereNotIn('pj.Sumber', ['MANUAL', 'AUTO_RULE'])
            ->where('d.Status_Paket', SopStatus::STATUS_BELUM)
            ->whereNotExists(function ($s) {
                $s->select(DB::raw(1))->from('N_SOP_Progress as pr')
                    ->whereColumn('pr.Id_SOP_Penjadwalan_Detail', 'd.Id_SOP_Penjadwalan_Detail')
                    ->whereNotNull('pr.Waktu_Buka');
            });

        $this->people->terapkanSaringanPaketRingkas($q, $f, 'p');
        $this->people->terapkanSaringanOrang($q, $f, 'd');

        return [
            'Penugasan' => (clone $q)->count(),
            'Paket' => (clone $q)->distinct()->count('p.Id_SOP_Paket'),
        ];
    }

    /** Tiga angka kelompok untuk satu himpunan dokumen. */
    private function pecahKelompok($anggota): array
    {
        $per = $anggota->countBy('Kelompok');

        return [
            'Fungsional' => (int) ($per[self::DITUGASKAN] ?? 0),
            'Publik' => (int) ($per[self::BEREDAR] ?? 0),
            'Belum_Beredar' => (int) ($per[self::BELUM_BEREDAR] ?? 0),
        ];
    }

    /**
     * Lead time penerbitan SOP dari Tanggal_Respon (atau pengajuan/Created_At)
     * sampai Versi 1 resmi terbit (Tanggal_Publish).
     */
    private function leadTime(): array
    {
        $rows = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as j', 'j.Id_SOP_Jenis_Dokumen', '=', 'd.Jenis_Dokumen_Id')
            ->leftJoin('N_HRIS_Master_Departemen as dp', 'dp.Id_Departemen', '=', 'd.Departemen_Id')
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            // Lihat catatan di klasifikasi(): dokumen nonaktif keluar dari populasi.
            ->whereNull('d.Nonaktif_At')
            /* TERBITAN PERTAMA dicari lewat tanggalnya, BUKAN lewat Versi_Ke.
               Sebelum ini barisnya disaring dengan `Versi_Ke = 1`, padahal
               nomorVersiBerikut() memulai dari NOL — versi pertama sebuah
               dokumen bernomor 0, dan `1` justru menunjuk versi KEDUA. Di
               basis data ini akibatnya 42 versi bernomor 0 (29 di antaranya
               terbit) tak satu pun terhitung, dan kartunya menampilkan 0 hari
               selamanya tanpa satu pun tanda bahwa ia sedang menghitung
               himpunan kosong. Perbandingan tanggal tak bergantung pada
               kesepakatan penomoran yang bisa berubah. */
            ->whereNotNull('v.Tanggal_Publish')
            ->orderBy('v.Tanggal_Publish')
            ->get([
                'd.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi',
                'j.Nama_Jenis', 'j.Kode_Jenis_Dokumen', 'dp.Nama_Departemen',
                'v.Created_At', 'v.Tanggal_Respon', 'v.Tanggal_Permintaan', 'v.Tanggal_Publish',
            ])
            /* Satu baris per dokumen: yang terbit paling awal. Terurut menaik
               di atas, jadi yang pertama masuk adalah yang menang. */
            ->unique('Id_SOP_Dokumen')
            ->values();

        $durasiList = [];
        $baris = [];
        $distribusi = [
            'kurang_7' => 0,
            '7_sampai_14' => 0,
            '15_sampai_30' => 0,
            'lebih_30' => 0,
        ];

        foreach ($rows as $r) {
            $start = $r->Tanggal_Respon ?: ($r->Tanggal_Permintaan ?: $r->Created_At);
            if (! $start || ! $r->Tanggal_Publish) {
                continue;
            }

            try {
                $tglStart = Carbon::parse($start)->startOfDay();
                $tglEnd = Carbon::parse($r->Tanggal_Publish)->startOfDay();
                $hari = max(0, (int) $tglStart->diffInDays($tglEnd));
            } catch (\Throwable) {
                continue;
            }

            $durasiList[] = $hari;

            if ($hari < 7) {
                $distribusi['kurang_7']++;
            } elseif ($hari <= 14) {
                $distribusi['7_sampai_14']++;
            } elseif ($hari <= 30) {
                $distribusi['15_sampai_30']++;
            } else {
                $distribusi['lebih_30']++;
            }

            $baris[] = [
                'Id_Dokumen' => $this->sandi((int) $r->Id_SOP_Dokumen),
                'Judul' => $r->Judul,
                'Nomor' => $r->Nomor_SOP_Resmi,
                'Jenis' => $r->Nama_Jenis ?: '—',
                'Kode_Jenis' => $r->Kode_Jenis_Dokumen ?: '—',
                'Departemen' => $r->Nama_Departemen ?: '—',
                'Tanggal_Mulai' => $start,
                'Tanggal_Publish' => $r->Tanggal_Publish,
                'Hari' => $hari,
            ];
        }

        usort($baris, fn ($a, $b) => $b['Hari'] <=> $a['Hari']);

        $total = count($durasiList);
        $rerata = $total > 0 ? round(array_sum($durasiList) / $total, 1) : 0;
        $min = $total > 0 ? min($durasiList) : 0;
        $max = $total > 0 ? max($durasiList) : 0;

        return [
            'Total_Dokumen_Terbit' => $total,
            'Rerata_Hari' => $rerata,
            'Tercepat_Hari' => $min,
            'Terlama_Hari' => $max,
            'Distribusi' => $distribusi,
            'Daftar' => array_slice($baris, 0, 10),
        ];
    }

    /**
     * Coverage per dokumen: target penugasan vs konfirmasi pembacaan.
     */
    private function coverageDokumen(): array
    {
        $docs = DB::table('N_SOP_Dokumen as d')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as j', 'j.Id_SOP_Jenis_Dokumen', '=', 'd.Jenis_Dokumen_Id')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            // Lihat catatan di klasifikasi(): dokumen nonaktif keluar dari populasi.
            ->whereNull('d.Nonaktif_At')
            ->get([
                'd.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi',
                'j.Nama_Jenis', 'j.Kode_Jenis_Dokumen',
            ]);

        if ($docs->isEmpty()) {
            return [
                'Total_Dokumen' => 0,
                'Dokumen_Ditugaskan' => 0,
                'Rerata_Coverage' => 0,
                'Sebaran' => ['Tinggi' => 0, 'Sedang' => 0, 'Rendah' => 0, 'Nol' => 0],
                'Tertinggi' => [],
                'Terendah' => [],
            ];
        }

        $rollups = DB::table('N_SOP_Paket_Item as pi')
            ->join('N_SOP_Coverage_Rollup as r', 'r.Id_SOP_Item_Versi', '=', 'pi.Id_Item_Versi_Aktif')
            ->where('pi.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('pi.Id_SOP_Dokumen')
            ->selectRaw('pi.Id_SOP_Dokumen,
                SUM(r.Total_Target) as Target,
                SUM(r.Jumlah_Dikonfirmasi) as Selesai'
            )
            ->get()
            ->keyBy('Id_SOP_Dokumen');

        $totalDok = $docs->count();
        $dokDitugaskan = 0;
        $totalTarget = 0;
        $totalSelesai = 0;
        $sebaran = ['Tinggi' => 0, 'Sedang' => 0, 'Rendah' => 0, 'Nol' => 0];
        $daftar = [];

        foreach ($docs as $d) {
            $r = $rollups->get($d->Id_SOP_Dokumen);
            $t = (int) ($r->Target ?? 0);
            $s = (int) ($r->Selesai ?? 0);
            $persen = $t > 0 ? round(($s * 100) / $t, 1) : 0;

            if ($t > 0) {
                $dokDitugaskan++;
                $totalTarget += $t;
                $totalSelesai += $s;

                if ($persen >= 80) {
                    $sebaran['Tinggi']++;
                } elseif ($persen >= 50) {
                    $sebaran['Sedang']++;
                } elseif ($persen > 0) {
                    $sebaran['Rendah']++;
                } else {
                    $sebaran['Nol']++;
                }
            }

            $daftar[] = [
                'Id_Dokumen' => $this->sandi((int) $d->Id_SOP_Dokumen),
                'Judul' => $d->Judul,
                'Nomor' => $d->Nomor_SOP_Resmi,
                'Jenis' => $d->Nama_Jenis ?: '—',
                'Kode_Jenis' => $d->Kode_Jenis_Dokumen ?: '—',
                'Target' => $t,
                'Selesai' => $s,
                'Persen' => $persen,
            ];
        }

        $rerata = $totalTarget > 0 ? round(($totalSelesai * 100) / $totalTarget, 1) : 0;
        $daftarAktif = collect($daftar)->where('Target', '>', 0);

        return [
            'Total_Dokumen' => $totalDok,
            'Dokumen_Ditugaskan' => $dokDitugaskan,
            'Rerata_Coverage' => $rerata,
            'Sebaran' => $sebaran,
            'Tertinggi' => $daftarAktif->sortByDesc('Persen')->values()->slice(0, 5)->all(),
            'Terendah' => $daftarAktif->sortBy('Persen')->values()->slice(0, 5)->all(),
        ];
    }

    /**
     * Coverage per paket penugasan.
     */
    private function coveragePaket(): array
    {
        $pakets = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->get([
                'p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Kode_Paket', 'p.Prioritas',
            ]);

        if ($pakets->isEmpty()) {
            return [
                'Total_Paket' => 0,
                'Paket_Ditugaskan' => 0,
                'Rerata_Coverage' => 0,
                'Sebaran' => ['Tinggi' => 0, 'Sedang' => 0, 'Rendah' => 0, 'Nol' => 0],
                'Tertinggi' => [],
                'Terendah' => [],
            ];
        }

        $paketIds = $pakets->pluck('Id_SOP_Paket')->all();

        $stats = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->whereIn('pj.Id_SOP_Paket', $paketIds)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw('pj.Id_SOP_Paket,
                COUNT(*) as Target,
                SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as Selesai,
                SUM(CASE WHEN d.Status_Paket = ? THEN 1 ELSE 0 END) as Progress',
                [SopStatus::STATUS_SELESAI, SopStatus::STATUS_PROGRESS]
            )
            ->get()
            ->keyBy('Id_SOP_Paket');

        $totalPaket = $pakets->count();
        $paketAktif = 0;
        $totalTarget = 0;
        $totalSelesai = 0;
        $sebaran = ['Tinggi' => 0, 'Sedang' => 0, 'Rendah' => 0, 'Nol' => 0];
        $daftar = [];

        foreach ($pakets as $p) {
            $st = $stats->get($p->Id_SOP_Paket);
            $t = (int) ($st->Target ?? 0);
            $s = (int) ($st->Selesai ?? 0);
            $prog = (int) ($st->Progress ?? 0);
            $persen = $t > 0 ? round(($s * 100) / $t, 1) : 0;

            if ($t > 0) {
                $paketAktif++;
                $totalTarget += $t;
                $totalSelesai += $s;

                if ($persen >= 80) {
                    $sebaran['Tinggi']++;
                } elseif ($persen >= 50) {
                    $sebaran['Sedang']++;
                } elseif ($persen > 0) {
                    $sebaran['Rendah']++;
                } else {
                    $sebaran['Nol']++;
                }
            }

            $daftar[] = [
                'Id_Paket' => $this->sandi((int) $p->Id_SOP_Paket),
                'Nama' => $p->Nama_Paket,
                'Kode' => $p->Kode_Paket,
                'Prioritas' => $p->Prioritas,
                'Target' => $t,
                'Selesai' => $s,
                'Progress' => $prog,
                'Persen' => $persen,
            ];
        }

        $rerata = $totalTarget > 0 ? round(($totalSelesai * 100) / $totalTarget, 1) : 0;
        $daftarAktif = collect($daftar)->where('Target', '>', 0);

        return [
            'Total_Paket' => $totalPaket,
            'Paket_Ditugaskan' => $paketAktif,
            'Rerata_Coverage' => $rerata,
            'Sebaran' => $sebaran,
            'Tertinggi' => $daftarAktif->sortByDesc('Persen')->values()->slice(0, 5)->all(),
            'Terendah' => $daftarAktif->sortBy('Persen')->values()->slice(0, 5)->all(),
        ];
    }

    public static function nama(string $kelompok): string
    {
        return [
            self::DITUGASKAN => 'Ditugaskan',
            self::BEREDAR => 'Beredar',
            self::BELUM_BEREDAR => 'Belum Beredar',
        ][$kelompok] ?? $kelompok;
    }

    public static function keterangan(string $kelompok): string
    {
        return [
            self::DITUGASKAN => 'Ditugaskan admin atau aturan — kepatuhannya diukur',
            self::BEREDAR => 'Beredar untuk dibaca, tetapi tak diwajibkan siapa pun',
            self::BELUM_BEREDAR => 'Sah di master, tetapi belum sampai ke siapa pun',
        ][$kelompok] ?? '';
    }
}
