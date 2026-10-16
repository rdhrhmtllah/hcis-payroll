<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopPaketVersionService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dunia peraga SOP — dokumen, paket, pembaca, dan sejarahnya.
 *
 * KENAPA ADA: dashboard dan grafik analitik butuh SEJARAH, bukan sekadar baris
 * data. Tren perlu peristiwa ledger yang tersebar berhari-hari; Umur
 * Keterlambatan perlu tenggat yang sudah lewat dengan berbagai umur; Heatmap
 * perlu penugasan yang menyebar di banyak departemen; dan lapis Kesehatan
 * Dokumen perlu piramid jenis yang timpang, proses APQC yang kosong, serta
 * versi yang berhenti di tengah jalan. Basis dev yang baru dipakai menguji
 * fitur tak akan pernah punya bentuk itu secara alami.
 *
 * DITULIS ULANG 2026-08-27. Versi sebelumnya lahir sebelum Master Dokumen ada:
 * ia menyisipkan N_SOP_Item_Versi langsung ke Id_SOP_Paket_Item dan TIDAK
 * PERNAH membuat satu pun baris N_SOP_Dokumen. Dijalankan hari ini, ia mengisi
 * paket dan penugasan tetapi meninggalkan lapis dokumen kosong — dashboard
 * menampilkan "0 dokumen" di atas 1.500 baris progres.
 *
 * SIKLUS HIDUP DOKUMEN LEWAT SERVICE SUNGGUHAN, TIDAK DILOMPATI:
 *
 *   buat() → tambahVersi() → pindah(REVIEW) → pindah(APPROVAL)
 *          → ubahVersi() isi pengesah → pindah(APPROVED) → terbitkanVersi()
 *
 * Setiap penjaga ikut dilalui, termasuk penjaga yang menolak penerbitan dari
 * tahap selain Pemberkasan. Kalau seeding ini lolos, itu sekaligus bukti
 * alurnya utuh.
 *
 * MESIN DISTRIBUSI DITULIS LANGSUNG KE TABEL, dan batas itu disengaja.
 * SopPenjadwalanService melakukan fan-out dan dapat mengantre pekerjaan;
 * menugaskan ratusan karyawan lewat jalur itu membuat hasil seeding bergantung
 * pada worker yang berjalan. Untuk data peraga, itu menukar kepastian dengan
 * kemurnian yang tak dibutuhkan siapa pun.
 *
 * URUTAN PENTING: dokumen diterbitkan SEBELUM paket dibuat. terbitkanVersi()
 * memanggil rekonsiliasi ke seluruh paket perujuk; menerbitkan saat dokumen
 * belum dipakai paket mana pun membuat rekonsiliasi itu tak punya pekerjaan,
 * sehingga tak ada antrean yang perlu ditunggu.
 *
 * PAGAR YANG DIPEGANG:
 *  - Setiap objek diberi awalan [DEMO] — pada Nama_Paket DAN Judul dokumen.
 *    Itulah satu-satunya jangkar penghapusan; perintah ini TIDAK PERNAH
 *    menyentuh baris yang tak berasal darinya.
 *  - `--bersihkan` mengembalikan basis data ke keadaan semula.
 *  - Ditolak di production kecuali dipaksa: data peraga yang bocor akan tampil
 *    sebagai kewajiban SOP sungguhan di layar karyawan.
 *  - Acak deterministik (mt_srand tetap) supaya dua kali jalan = bentuk sama.
 */
class SopSeedDemo extends Command
{
    protected $signature = 'sop:seed-demo
        {--bersihkan : Hapus seluruh data peraga [DEMO] lalu keluar}
        {--hari=90 : Rentang sejarah yang dibuat ke belakang}
        {--paksa : Izinkan berjalan di lingkungan production}';

    protected $description = 'Bangun (atau hapus) dunia peraga [DEMO]: dokumen, paket, pembaca, dan sejarahnya';

    private const TANDA = '[DEMO]';

    /**
     * Pola LIKE untuk TANDA.
     *
     * MSSQL memperlakukan `[...]` di LIKE sebagai KELAS KARAKTER: pola '[DEMO]%'
     * berarti "satu huruf D/E/M/O lalu apa saja" — bukan teks literal "[DEMO]".
     * Akibatnya pemeriksaan duplikat maupun penghapusan sama-sama meleset diam-diam.
     * Kurung buka harus di-escape sebagai '[[]'.
     */
    private const POLA = '[[]DEMO]%';

    /**
     * Katalog 40 dokumen.
     *
     * Sebarannya DIRANCANG, bukan diacak: piramid harus timpang seperti
     * perusahaan sungguhan (Prosedur & Instruksi Kerja jauh lebih banyak
     * daripada Kebijakan), dan sebagian proses APQC harus KOSONG supaya panel
     * lubang tata kelola punya sesuatu untuk dilaporkan.
     *
     * jenis  : Kode_Jenis di N_SOP_Master_Jenis_Dokumen
     * apqc   : Kode_Proses di N_SOP_Master_APQC, atau null (tanpa proses)
     * dept   : indeks departemen dalam daftar yang terbaca saat jalan, atau
     *          null (tanpa pemilik unit — sengaja, itu temuan)
     */
    private const KATALOG = [
        // -- Kebijakan (4) — puncak piramid, sedikit dan berumur panjang
        ['Kebijakan Mutu Perusahaan', 'KEBIJAKAN', 0, 0],
        ['Kebijakan Keselamatan & Kesehatan Kerja', 'KEBIJAKAN', 1, 1],
        ['Kebijakan Perlindungan Data Pribadi', 'KEBIJAKAN', 2, 2],
        ['Kebijakan Anti Penyuapan', 'KEBIJAKAN', null, null],

        // -- Surat Keputusan (3)
        ['SK Struktur Organisasi', 'SURAT_KEPUTUSAN', 0, 3],
        ['SK Penetapan Tim Tanggap Darurat', 'SURAT_KEPUTUSAN', 1, 1],
        ['SK Jam Kerja & Shift', 'SURAT_KEPUTUSAN', 3, 3],

        // -- Prosedur (12) — badan piramid yang paling tebal
        ['Prosedur Penerimaan Barang', 'PROSEDUR', 4, 4],
        ['Prosedur Pengendalian Dokumen', 'PROSEDUR', 0, 0],
        ['Prosedur Audit Internal', 'PROSEDUR', 0, 0],
        ['Prosedur Penanganan Keluhan Pelanggan', 'PROSEDUR', 5, 5],
        ['Prosedur Rekrutmen & Seleksi', 'PROSEDUR', 3, 3],
        ['Prosedur Pengadaan Barang & Jasa', 'PROSEDUR', 4, 4],
        ['Prosedur Pemeliharaan Mesin', 'PROSEDUR', 6, 6],
        ['Prosedur Penanganan Limbah', 'PROSEDUR', 1, 1],
        ['Prosedur Kas Kecil & Reimburse', 'PROSEDUR', 7, 7],
        ['Prosedur Backup & Pemulihan Data', 'PROSEDUR', 2, 2],
        ['Prosedur Kalibrasi Alat Ukur', 'PROSEDUR', 6, 6],
        ['Prosedur Penanganan Produk Tidak Sesuai', 'PROSEDUR', 5, null],

        // -- Instruksi Kerja (9) — makin ke dasar makin teknis dan makin banyak
        ['IK Pemakaian Alat Pelindung Diri', 'INSTRUKSI_KERJA', 1, 1],
        ['IK Pengoperasian Forklift', 'INSTRUKSI_KERJA', 6, 6],
        ['IK Pembersihan Ruang Produksi', 'INSTRUKSI_KERJA', 5, 5],
        ['IK Penggunaan Sistem Absensi', 'INSTRUKSI_KERJA', 3, 3],
        ['IK Penanganan Tumpahan Bahan Kimia', 'INSTRUKSI_KERJA', 1, 1],
        ['IK Pengemasan Produk Jadi', 'INSTRUKSI_KERJA', 5, 5],
        ['IK Serah Terima Shift', 'INSTRUKSI_KERJA', 6, 6],
        ['IK Pengecekan Suhu Gudang', 'INSTRUKSI_KERJA', 4, 4],
        ['IK Pelaporan Insiden Keamanan Siber', 'INSTRUKSI_KERJA', 2, 2],

        // -- Standar (4)
        ['Standar Mutu Bahan Baku', 'STANDAR', 5, 5],
        ['Standar Kebersihan Area Kerja', 'STANDAR', 1, 1],
        ['Standar Penamaan Berkas Digital', 'STANDAR', 2, null],
        ['Standar Waktu Layanan Internal', 'STANDAR', 0, 0],

        // -- Formulir (6) — dasar piramid, tipe FORM
        ['Formulir Permintaan Cuti', 'FORMULIR', 3, 3],
        ['Formulir Laporan Ketidaksesuaian', 'FORMULIR', 5, 5],
        ['Formulir Serah Terima Aset', 'FORMULIR', 4, 4],
        ['Formulir Izin Kerja Panas', 'FORMULIR', 1, 1],
        ['Formulir Permintaan Pengadaan', 'FORMULIR', 4, 4],
        ['Formulir Evaluasi Pemasok', 'FORMULIR', 4, null],

        // -- Manual (2)
        ['Manual Sistem Manajemen Mutu', 'MANUAL', 0, 0],
        ['Manual Pengguna Aplikasi HCIS', 'MANUAL', 2, 2],
    ];

    /**
     * Enam paket, masing-masing memainkan peran BERBEDA di dashboard.
     *
     * `dok` = berapa dokumen yang masuk paket ini. Jumlah keseluruhan (27)
     * sengaja pas dengan berapa dokumen yang benar-benar TERBIT — 40 katalog
     * dikurangi 7 yang macet dan 6 Formulir yang tertahan jalan buntu
     * pengesahan. Paket yang tak kebagian dokumen akan dilewati, dan paket
     * kosong di dashboard terbaca sebagai kesalahan.
     * `tugas` = apakah paket ini punya penjadwalan.
     *
     * Paket tanpa penjadwalan itu bukan kelalaian: itulah yang membuat
     * dokumen di dalamnya berkelompok TERBUKA — beredar, tetapi tak
     * diwajibkan siapa pun.
     */
    private const PAKET = [
        ['nama' => 'Keselamatan Kerja & APD', 'prioritas' => 'KRITIS',
            'dok' => 5, 'distribusi' => 'TERBATAS', 'tugas' => true],
        ['nama' => 'Pengendalian Mutu Produksi', 'prioritas' => 'TINGGI',
            'dok' => 5, 'distribusi' => 'TERBATAS', 'tugas' => true],
        ['nama' => 'Tata Tertib & Kepegawaian', 'prioritas' => 'NORMAL',
            'dok' => 5, 'distribusi' => 'TERBATAS', 'tugas' => true],
        ['nama' => 'Keamanan Informasi', 'prioritas' => 'TINGGI',
            'dok' => 4, 'distribusi' => 'TERBATAS', 'tugas' => true],
        // Publik BER-penugasan yang belum pernah dibuka: memberi angka pada
        // "tersaring gerbang publik" supaya panelnya bisa dinilai.
        ['nama' => 'Pengenalan SOP untuk Karyawan Baru', 'prioritas' => 'NORMAL',
            'dok' => 4, 'distribusi' => 'PUBLIK', 'tugas' => 'belum-dibuka'],
        // Terbit tetapi belum ditugaskan ke siapa pun.
        ['nama' => 'Referensi Keuangan & Pengadaan', 'prioritas' => 'NORMAL',
            'dok' => 4, 'distribusi' => 'TERBATAS', 'tugas' => false],
    ];

    /**
     * Tahap tempat versi peraga berhenti, beserta umurnya.
     *
     * Dokumen yang versinya berhenti sebelum terbit TIDAK BISA masuk paket —
     * pointer versi aktifnya belum ada. Karena itu ketujuhnya sekaligus
     * menjadi isi kelompok "Belum Beredar", persis seperti di dunia nyata:
     * yang macet di meja redaksi memang tak sampai ke siapa pun.
     *
     * INDEKSNYA TERSEBAR LINTAS JENIS, bukan diambil dari ekor katalog.
     * Mengambil ekor membuat seluruh Formulir dan Manual macet sekaligus —
     * dasar piramid mati 100%, bentuk yang tak pernah terjadi di perusahaan
     * mana pun dan membuat panelnya terbaca sebagai kesalahan data.
     *
     * Formulir sengaja TIDAK dipilih di sini: tipe FORM sudah tertahan sendiri
     * oleh jalan buntu pengesahan (lihat bangunDokumen), jadi memacetkannya
     * lagi hanya menghitung dua kali sebab yang sama.
     */
    private const MACET = [
        // indeks katalog => tahap tempat ia berhenti, dan sudah berapa lama
        3 => ['tahap' => 'DRAFTING', 'umur' => 70],   // Kebijakan
        6 => ['tahap' => 'REVIEW', 'umur' => 48],     // Surat Keputusan
        13 => ['tahap' => 'DRAFTING', 'umur' => 31],  // Prosedur
        18 => ['tahap' => 'APPROVAL', 'umur' => 26],  // Prosedur
        24 => ['tahap' => 'REVIEW', 'umur' => 12],    // Instruksi Kerja
        30 => ['tahap' => 'DRAFTING', 'umur' => 5],   // Standar
        39 => ['tahap' => 'APPROVAL', 'umur' => 9],   // Manual
    ];

    private array $konten = [];

    private array $departemen = [];

    private array $apqc = [];

    private array $jenis = [];

    /** departemenId => ['divisi' => ?int, 'sub' => ?int] */
    private array $unit = [];

    private array $entitas = [];

    /** Calon penanda tangan: karyawan SUNGGUHAN, bukan nama ketikan. */
    private array $pengesah = [];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('paksa')) {
            $this->error('Ditolak di production. Data peraga akan tampil sebagai kewajiban sungguhan bagi karyawan.');
            $this->line('Gunakan --paksa hanya bila Anda benar-benar bermaksud demikian.');

            return self::FAILURE;
        }

        if ($this->option('bersihkan')) {
            return $this->bersihkan();
        }

        if ($this->adaSisa()) {
            $this->warn('Data peraga sudah ada. Jalankan --bersihkan lebih dulu bila ingin membuat ulang.');

            return self::FAILURE;
        }

        if (($galat = $this->siapkanMaster()) !== null) {
            $this->error($galat);

            return self::FAILURE;
        }

        $hari = max(30, (int) $this->option('hari'));
        mt_srand(20260827); // deterministik: dua kali jalan menghasilkan bentuk sama

        $karyawan = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->where('k.Aktif', 'Y')
            ->get(['k.Kode_Karyawan', 'div.nama_sub_divisi as dept']);

        if ($karyawan->count() < 5) {
            $this->error('Karyawan aktif terlalu sedikit ('.$karyawan->count().') untuk membuat sebaran yang bermakna.');

            return self::FAILURE;
        }

        $this->info('Membuat dunia peraga: '.count(self::KATALOG).' dokumen, '
            .count(self::PAKET).' paket, '.$karyawan->count().' karyawan tersedia, rentang '.$hari.' hari.');

        $stat = ['dokumen' => 0, 'terbit' => 0, 'macet' => 0, 'buntu' => 0, 'paket' => 0,
            'penjadwalan' => 0, 'detail' => 0, 'mandiri' => 0, 'progress' => 0, 'ledger' => 0];

        /* Dokumen DI LUAR transaksi besar: tiap penerbitan memanggil
           rekonsiliasi dan menulis log sendiri, dan membungkus ratusan
           panggilan service dalam satu transaksi panjang menahan kunci pada
           tabel yang juga dibaca layar admin selama seeding berjalan. */
        $terbit = $this->bangunDokumen($hari, $stat);

        $bagian = $this->bagiKePaket($terbit);

        /* SATU TRANSAKSI PER PAKET, bukan satu untuk semuanya.
           Dua sebabnya, dan keduanya nyata di basis ini:

           1. RCSI mati di Web_HRIS, jadi pembaca antre di belakang penulis.
              Satu transaksi yang memuat seluruh penugasan menahan pembacaan
              selama menit-menit ia berjalan — termasuk layar admin yang sedang
              dibuka orang.
           2. Paket muncul BERTAHAP. Dengan satu transaksi besar, dashboard
              menampilkan nol paket sepanjang seeding lalu enam sekaligus di
              detik terakhir; yang menunggu tak punya cara membedakan "sedang
              berjalan" dari "gagal". */
        foreach (self::PAKET as $i => $def) {
            $isi = $bagian[$i] ?? [];
            if ($isi === []) {
                continue;
            }

            DB::transaction(function () use ($i, $def, $isi, $karyawan, $hari, &$stat) {
                $idPaket = $this->buatPaket($def, $hari);
                $items = $this->buatItem($idPaket, $isi, $hari);
                $stat['paket']++;

                /* Snapshot versi paket. Paket berstatus PUBLISHED tanpa satu pun
                   baris N_SOP_Paket_Versi adalah keadaan yang mustahil lahir
                   dari layar: panel versi menyatakan "belum ada versi" tepat di
                   atas paket yang mengaku terbit. Dipanggil lewat release()
                   yang sama dengan tombol terbit, bukan INSERT tangan. */
                app(SopPaketVersionService::class)->release(
                    $idPaket, 'PAKET_PUBLISH', 1, null, 'Rilis awal paket peraga.',
                );

                if ($def['tugas'] === false) {
                    return;
                }

                $target = $this->irisTarget($karyawan, $i);

                foreach ($this->rencanaBatch($i, $hari) as $batch) {
                    $orang = $target->slice($batch['mulai'], $batch['jumlah']);
                    if ($orang->isEmpty()) {
                        continue;
                    }

                    $hasil = $this->buatBatch($idPaket, $items, $orang, $batch, $def['tugas']);
                    $stat['penjadwalan']++;
                    $stat['detail'] += $hasil['detail'];
                    $stat['progress'] += $hasil['progress'];
                    $stat['ledger'] += $hasil['ledger'];
                }

                if ($def['distribusi'] === 'PUBLIK') {
                    $hasil = $this->kohortMandiri($idPaket, $items, $karyawan);
                    $stat['mandiri'] += $hasil['detail'];
                    $stat['progress'] += $hasil['progress'];
                }
            });

            $this->line('  paket siap: '.$def['nama'].' ('.count($isi).' dokumen)');
        }

        // Cache analitik menyimpan hasil belasan menit — tanpa ini grafik masih
        // kosong beberapa menit setelah seeding dan terlihat seperti gagal.
        Cache::store('database')->flush();

        $this->newLine();
        $this->info('Selesai.');
        foreach ($stat as $k => $v) {
            $this->line('  '.str_pad($k, 14).$v);
        }
        $this->newLine();
        $this->line('Hapus kapan saja: php artisan sop:seed-demo --bersihkan');

        return self::SUCCESS;
    }

    /* ================= MASTER ================= */

    /** Bahan yang harus ada sebelum apa pun dibuat. Ketiadaannya dilaporkan, bukan dilewati. */
    private function siapkanMaster(): ?string
    {
        $this->jenis = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', 'Y')->pluck('Id_SOP_Jenis_Dokumen', 'Kode_Jenis')->all();
        if ($this->jenis === []) {
            return 'Master jenis dokumen kosong. Jalankan seed jenis dokumen lebih dulu.';
        }

        $this->apqc = DB::table('N_SOP_Master_APQC')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->pluck('Id_SOP_APQC')->all();
        if ($this->apqc === []) {
            return 'Master proses APQC kosong.';
        }

        /* Departemen yang PUNYA sub-divisi diutamakan.
           Sebagian departemen di master (PPIC, IT) ber-Sub_Divisi_Id NULL, dan
           dokumen yang dimiliki mereka otomatis lahir tanpa Divisi/Sub-Divisi.
           Itu keadaan master yang sah, tetapi memakainya di data peraga membuat
           seperempat dokumen tampak "identitasnya belum lengkap" karena sebab
           yang tak ada hubungannya dengan alur SOP.

           Yang tanpa sub-divisi tetap dipakai sebagai cadangan bila yang punya
           kurang dari delapan — dunia peraga yang tak jadi terbangun lebih
           buruk daripada unit yang kosong. */
        $berunit = DB::table('N_HRIS_Master_Departemen')
            ->where('Flag_Aktif', 'Y')->whereNotNull('Sub_Divisi_Id')
            ->orderBy('Urutan')->limit(8)->get(['Id_Departemen', 'Sub_Divisi_Id']);

        $dept = $berunit->count() >= 8 ? $berunit : DB::table('N_HRIS_Master_Departemen')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->limit(8)
            ->get(['Id_Departemen', 'Sub_Divisi_Id']);

        $this->departemen = $dept->pluck('Id_Departemen')->all();

        /* Divisi diturunkan dari sub-divisi milik departemennya, bukan dipilih
           sendiri: pastikanUnitSah() menolak pasangan divisi/sub-divisi yang
           tak ada di View_Divisi_Sub_Divisi, dan memilih keduanya secara bebas
           akan menghasilkan pasangan karangan yang ditolak di tengah seeding.

           Departemen yang Sub_Divisi_Id-nya NULL dibiarkan tanpa unit — itu
           keadaan nyata di master, bukan kekurangan seeder. */
        $peta = DB::table('View_Divisi_Sub_Divisi')
            ->whereIn('ID_Sub_Divisi', $dept->pluck('Sub_Divisi_Id')->filter()->all())
            ->pluck('ID_Divisi', 'ID_Sub_Divisi');

        foreach ($dept as $d) {
            $sub = $d->Sub_Divisi_Id ? (int) $d->Sub_Divisi_Id : null;
            $this->unit[(int) $d->Id_Departemen] = [
                'sub' => $sub,
                'divisi' => $sub !== null && isset($peta[$sub]) ? (int) $peta[$sub] : null,
            ];
        }

        $this->entitas = DB::table('N_HRIS_Master_Entitas')
            ->where('Flag_Aktif', 'Y')->orderBy('Urutan')->pluck('Id_Entitas')->all();
        if (count($this->entitas) < 2) {
            return 'Master entitas terlalu sedikit ('.count($this->entitas).') untuk membedakan pemilik dan ruang lingkup.';
        }

        /* Penanda tangan dari MASTER KARYAWAN, lewat kueri yang sama persis
           dengan yang dipakai SopPengesahPicker. Nama ketikan lepas membuat
           Pengesah_Terverifikasi selalu false, dan blok pengesah di layar
           tampak seperti data yang belum ditautkan ke siapa-siapa. */
        $ambilPengesah = fn (?array $kata) => DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('v.nama_jabatan')
            ->when($kata !== null, fn ($w) => $w->where(function ($x) use ($kata) {
                foreach ($kata as $t) {
                    $x->orWhere('v.nama_jabatan', 'LIKE', "%{$t}%");
                }
            }))
            ->orderBy('k.Kode_Karyawan')
            ->limit(12)
            ->get(['k.Kode_Karyawan', 'k.Nama', 'v.nama_jabatan as Jabatan'])
            ->all();

        /* Diutamakan yang berjabatan penyelia ke atas. Mengambil 12 karyawan
           pertama menurut kode menghasilkan "Disahkan oleh: OPERATOR BOILER" —
           datanya sah, tetapi blok pengesahnya terbaca sebagai salah orang dan
           membuat seluruh dokumen peraga tampak tak dipikirkan. Kalau tak ada
           yang cocok, daftar apa adanya tetap dipakai: dunia peraga yang tak
           jadi terbangun lebih buruk daripada jabatan yang kurang pas. */
        $this->pengesah = $ambilPengesah(['MANAGER', 'DIRECTOR', 'DIREKTUR', 'HEAD', 'KEPALA', 'SUPERVISOR', 'SPV'])
            ?: $ambilPengesah(null);
        if (count($this->pengesah) < 2) {
            return 'Tak cukup karyawan berjabatan untuk dijadikan penanda tangan.';
        }

        /* Berkas dari Library Konten yang benar-benar ada: terbitkanVersi()
           menuntutnya lewat pastikanIsiSah(), dan menautkan id karangan akan
           membuat penerbitan gagal di tengah seeding. Hanya PDF: contoh
           pengisian FORM wajib PDF sejak 10 Okt 2026, dan 'document' juga
           mencakup .docx/.xlsx. */
        $this->konten = DB::table('N_LMS_Master_Konten as k')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'k.Id_Master_Konten')
            ->where('k.Flag_Aktif', 'Y')->where('k.Tipe_Konten', 'document')
            ->where(fn ($q) => $q->where('b.Type_File', 'like', '%pdf%')->orWhere('b.File_Path', 'like', '%.pdf'))
            ->distinct()->pluck('k.Id_Master_Konten')->all();
        if (count($this->konten) < 3) {
            return 'Library Konten tak punya cukup berkas PDF aktif ('
                .count($this->konten).'). Unggah beberapa berkas lebih dulu.';
        }

        return null;
    }

    private function adaSisa(): bool
    {
        return DB::table('N_SOP_Master_Paket')->where('Nama_Paket', 'LIKE', self::POLA)->exists()
            || DB::table('N_SOP_Dokumen')->where('Judul', 'LIKE', self::POLA)->exists();
    }

    /* ================= DOKUMEN ================= */

    /**
     * Bangun seluruh katalog lewat service sungguhan.
     *
     * @return array<int, array{dokumen:int, versi:int, judul:string, nomor:?string, tipe:string}>
     */
    private function bangunDokumen(int $hari, array &$stat): array
    {
        $dok = app(SopDokumenService::class);
        $tahap = app(SopTahapVersiService::class);

        /* Indeks yang macet ditetapkan di master MACET — tetap, bukan diacak:
           yang macet karena itu selalu dokumen yang sama pada dua kali jalan,
           sehingga sebuah temuan bisa ditelusuri ulang. */
        $terbit = [];
        $bar = $this->output->createProgressBar(count(self::KATALOG));
        $bar->start();

        foreach (self::KATALOG as $i => [$judul, $kodeJenis, $iApqc, $iDept]) {
            $tipe = $kodeJenis === 'FORMULIR' ? 'FORM' : 'DOKUMEN';
            $umur = $hari + 20 - (int) floor($i * ($hari / max(1, count(self::KATALOG))));

            $idDept = $iDept === null ? null : ($this->departemen[$iDept] ?? null);
            $unit = $idDept !== null ? ($this->unit[$idDept] ?? []) : [];

            $identitas = [
                'Jenis_Dokumen_Id' => $this->jenis[$kodeJenis] ?? null,
                'APQC_Id' => $iApqc === null ? null : ($this->apqc[$iApqc] ?? null),
                'Departemen_Id' => $idDept,
            ] + $this->entitasUntuk($kodeJenis, $i);

            $idDok = $dok->buat([
                'Judul' => self::TANDA.' '.$judul,
                /* Nomor urut diminta lewat jalur yang sama dengan layar
                   (saranNoDokumen), bukan dibiarkan kosong. Tanpa ini SELURUH
                   dokumen lahir dengan No_Dokumen = 0, dan Nomor_SOP_Resmi
                   hanya kebetulan unik selama tak ada dua dokumen berbagi
                   jenis + entitas + departemen. Begitu ada, nomor resminya
                   kembar — cacat yang di modul ini sudah pernah terjadi. */
                'No_Dokumen' => $dok->saranNoDokumen($identitas),
                'Tipe' => $tipe,
                'Deskripsi' => 'Dokumen peraga. Aman dihapus dengan sop:seed-demo --bersihkan.',
                /* Divisi & sub-divisi MENGIKUTI departemennya, bukan dipilih
                   sendiri: keduanya harus berpasangan sah di
                   View_Divisi_Sub_Divisi, dan dokumen yang unitnya bertentangan
                   dengan departemennya adalah data yang tak pernah lahir dari
                   layar. */
                'Divisi_Id' => $unit['divisi'] ?? null,
                'Sub_Divisi_Id' => $unit['sub'] ?? null,
            ] + $identitas, 1);
            $stat['dokumen']++;

            $idVersi = $dok->tambahVersi($idDok, [
                'Ringkasan_Perubahan' => 'Terbit awal — dokumen peraga.',
            ], 1);

            if (! $idVersi) {
                $bar->advance();

                continue;
            }

            /* Umur dimundurkan LANGSUNG ke tabel. Service selalu mencap now(),
               dan tanpa langkah ini seluruh sejarah menumpuk di hari seeding —
               "versi tertua 0 hari" pada setiap tahap, yang tak menggambarkan
               apa pun. */
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
                ->update(['Created_At' => now()->subDays($umur)]);
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)
                ->update(['Created_At' => now()->subDays($umur)]);

            if (isset(self::MACET[$i])) {
                $this->hentikanDiTahap($tahap, $idVersi, self::MACET[$i]);
                $stat['macet']++;
                $bar->advance();

                continue;
            }

            /* TIPE TANPA BLOK PENGESAHAN TAK BISA TERBIT — dan itu keadaan
               nyata, bukan kekurangan seeder.

               PROFIL_TIPE menetapkan blok "pengesahan" HANYA untuk tipe
               DOKUMEN; FORM, VIDEO, dan KUIS sengaja tak punya, sehingga
               ubahVersi() menolak blok pengesah untuk tipe-tipe itu. Sementara
               itu syarat tahap REALISASI_PENGESAH berlaku untuk SEMUA tipe.
               Dua aturan yang benar sendiri-sendiri, tetapi bertemu sebagai
               jalan buntu: versi FORM tertahan selamanya di Pengesahan.

               Seeder ini MEMBIARKANNYA tertahan, bukan menyiasatinya dengan
               mengubah tipenya. Hasilnya dunia peraga memuat bukti gap itu,
               dan dashboard menampilkannya sebagai dokumen yang tak pernah
               sampai ke siapa pun. */
            if (! $this->punyaBlokPengesahan($tipe)) {
                $tahap->pindah($idVersi, 'REVIEW', 1);
                $tahap->pindah($idVersi, 'APPROVAL', 1);
                $this->mundurkanTanggalProses($idVersi, $umur, terbit: false);
                $stat['buntu']++;
                $bar->advance();

                continue;
            }

            $this->terbitkan($dok, $tahap, $idVersi, $umur);
            $stat['terbit']++;

            $baris = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)
                ->first(['Judul', 'Nomor_SOP_Resmi']);
            $terbit[] = [
                'dokumen' => $idDok, 'versi' => $idVersi, 'tipe' => $tipe,
                'jenis' => $kodeJenis,
                'judul' => $baris->Judul ?? $judul, 'nomor' => $baris->Nomor_SOP_Resmi ?? null,
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        return $terbit;
    }

    /**
     * Entitas pemilik & ruang lingkup, ditentukan dari JENIS dokumennya.
     *
     * Bukan diacak: dokumen arah — Kebijakan, Surat Keputusan, Manual —
     * berlaku untuk seluruh grup, sedangkan Prosedur, Instruksi Kerja,
     * Standar, dan Formulir hidup di satu perusahaan operasional. Sebaran yang
     * mengikuti aturan itu membuat saringan entitas di layar menghasilkan
     * potongan yang masuk akal; sebaran acak membuatnya menghasilkan campuran
     * yang tak bisa dijelaskan siapa pun.
     *
     * @return array{Entitas_Pemilik_Id: int, Entitas_Lingkup_Id: int}
     */
    private function entitasUntuk(string $kodeJenis, int $i): array
    {
        $grup = $this->entitas[0];
        $operasional = array_slice($this->entitas, 1) ?: [$grup];
        $milik = $operasional[$i % count($operasional)];

        $tingkatGrup = in_array($kodeJenis, ['KEBIJAKAN', 'SURAT_KEPUTUSAN', 'MANUAL'], true);

        return $tingkatGrup
            ? ['Entitas_Pemilik_Id' => $grup, 'Entitas_Lingkup_Id' => $grup]
            : ['Entitas_Pemilik_Id' => $milik, 'Entitas_Lingkup_Id' => $milik];
    }

    /**
     * Apakah tipe ini mengenal blok pengesahan?
     *
     * Dibaca dari SopTipeItem, bukan dihafal di sini: daftar tipe berubah
     * lebih sering daripada seeder ini dibaca ulang.
     */
    private function punyaBlokPengesahan(string $tipe): bool
    {
        return SopTipeItem::blokBerlaku($tipe, SopTipeItem::BLOK_PENGESAHAN);
    }

    /**
     * Jalankan versi sampai terbit — SELURUH tahap dilalui, tak satu pun
     * dilompati. Inilah bagian yang membuat data peraga ini juga berfungsi
     * sebagai pemeriksaan alur: kalau salah satu penjaga menolak, seeding
     * berhenti di sini dan menyebutkan sebabnya.
     */
    private function terbitkan(SopDokumenService $dok, SopTahapVersiService $tahap, int $idVersi, int $umur): void
    {
        $tahap->pindah($idVersi, 'REVIEW', 1);
        $tahap->pindah($idVersi, 'APPROVAL', 1);

        /* SELURUH field identitas dikirim ulang, bukan hanya yang berubah.
           ubahVersi() memetakan sebagian besar kolom dengan `$data['X'] ?? null`,
           sehingga muatan sebagian DIAM-DIAM MENGOSONGKAN sisanya — termasuk
           Ringkasan_Perubahan yang baru saja diisi tambahVersi(). Dua kolom
           (Id_Master_Konten dan Nomor_Pengesahan) sudah dikecualikan lewat
           array_key_exists karena pernah menggigit; sisanya belum. */
        $dok->ubahVersi($idVersi, [
            'Ringkasan_Perubahan' => 'Terbit awal — dokumen peraga.',
            'Pengesah' => $this->blokPengesah($idVersi),
            'Tanggal_Berlaku' => now()->subDays(max(1, $umur - 10))->toDateString(),
        ], 1);

        $tahap->pindah($idVersi, 'APPROVED', 1);

        // Berkas final dari Library Konten — dituntut pastikanIsiSah().
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Id_Master_Konten' => $this->konten[mt_rand(0, count($this->konten) - 1)],
        ]);

        $dok->terbitkanVersi($idVersi, 1);

        $this->mundurkanTanggalProses($idVersi, $umur, terbit: true);
    }

    /**
     * Blok penanda tangan dari karyawan SUNGGUHAN.
     *
     * Dua peran, dua orang berbeda — dan keduanya membawa Kode_Karyawan.
     * Tanpa kode itu Pengesah_Terverifikasi selalu false, dan blok pengesah di
     * layar tampak seperti nama yang diketik lepas tanpa tertaut ke siapa pun.
     *
     * Pemilihannya deterministik terhadap id versi, bukan acak: dua kali jalan
     * menghasilkan penanda tangan yang sama, sehingga sebuah tampilan bisa
     * dibandingkan antar-seeding.
     */
    private function blokPengesah(int $idVersi): array
    {
        $n = count($this->pengesah);
        $penyusun = $this->pengesah[$idVersi % $n];
        $pengesah = $this->pengesah[($idVersi + 1 + (int) floor($n / 2)) % $n];

        /* "Disahkan oleh" DILETAKKAN PERTAMA, dan itu bukan soal selera.
           SopDokumenService menyalin kolom cermin Disahkan_Oleh /
           Jabatan_Pengesah / Kode_Karyawan_Pengesah dari `$blok[0]` — baris
           pertama, apa pun perannya. Menaruh penyusun di depan membuat register
           kontrol dokumen menyebut PENYUSUN sebagai pengesahnya, dan tak ada
           galat apa pun yang menandainya. */
        return [
            [
                'Peran' => 'Disahkan oleh',
                'Nama' => $pengesah->Nama,
                'Jabatan' => $pengesah->Jabatan,
                'Kode_Karyawan' => $pengesah->Kode_Karyawan,
            ],
            [
                'Peran' => 'Disusun oleh',
                'Nama' => $penyusun->Nama,
                'Jabatan' => $penyusun->Jabatan,
                'Kode_Karyawan' => $penyusun->Kode_Karyawan,
            ],
        ];
    }

    /**
     * Mundurkan tanggal proses supaya membentuk urutan yang masuk akal.
     *
     * KENAPA PERLU: pindah() mencap Tanggal_Permintaan dan
     * Tanggal_Akhir_Approval dengan now(). Tanpa langkah ini setiap dokumen —
     * termasuk yang dibuat "90 hari lalu" — berbunyi "diminta hari ini,
     * disetujui hari ini, terbit tiga bulan lalu". Urutan yang mustahil itu
     * langsung terbaca di layar versi dan membuat seluruh data peraga tampak
     * karangan.
     *
     * Jaraknya dibuat berbeda-beda: ada yang diproses seminggu, ada yang
     * sebulan. Jarak yang seragam menghasilkan grafik durasi berbentuk satu
     * garis tegak.
     */
    private function mundurkanTanggalProses(int $idVersi, int $umur, bool $terbit): void
    {
        $lahir = now()->subDays($umur);
        $jedaMinta = 2 + ($idVersi % 6);        // 2-7 hari sesudah versi lahir
        $jedaRespon = $jedaMinta + 3 + ($idVersi % 9);
        $jedaSetuju = $jedaRespon + 2 + ($idVersi % 5);

        $ubah = [
            'Created_At' => $lahir,
            'Tanggal_Permintaan' => $lahir->copy()->addDays($jedaMinta)->toDateString(),
            'Tanggal_Respon' => $lahir->copy()->addDays($jedaRespon)->toDateString(),
            'Tanggal_Akhir_Approval' => $lahir->copy()->addDays($jedaSetuju)->toDateString(),
        ];

        if ($terbit) {
            $publish = $lahir->copy()->addDays($jedaSetuju + 1 + ($idVersi % 4));
            $ubah['Tanggal_Publish'] = $publish->gt(now()) ? now()->subDay() : $publish;
            /* Siapa yang menerbitkan. Kolomnya dibaca register kontrol dokumen;
               dibiarkan kosong, kolom "diterbitkan oleh" di sana selalu tanda
               hubung. */
            /* Penerbit sengaja BUKAN orang yang sama dengan pengesah utama:
               satu nama yang muncul sebagai penyusun, pengesah, sekaligus
               penerbit membuat jejak audit tampak seperti satu orang
               mengesahkan pekerjaannya sendiri. */
            $n = count($this->pengesah);
            $ubah['Kode_Karyawan_Publisher'] = $this->pengesah[($idVersi + 2) % $n]->Kode_Karyawan;
        }

        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update($ubah);
    }

    /** Bawa versi sampai tahap tertentu lalu tinggalkan di sana. */
    private function hentikanDiTahap(SopTahapVersiService $tahap, int $idVersi, array $def): void
    {
        $jalur = ['DRAFTING' => [], 'REVIEW' => ['REVIEW'], 'APPROVAL' => ['REVIEW', 'APPROVAL']];

        foreach ($jalur[$def['tahap']] ?? [] as $ke) {
            $tahap->pindah($idVersi, $ke, 1);
        }

        /* Umur DIMUNDURKAN SESUDAH perpindahan, bukan sebelumnya: pindah()
           mencap tanggal proses dengan now(), dan cap itu akan menimpa umur
           yang sudah dipasang lebih dulu.

           Hanya tanggal yang MEMANG sudah tercap ikut dimundurkan. Versi yang
           berhenti di Drafting belum pernah diminta direview; mengisi
           Tanggal_Permintaan-nya akan membuat layar menyatakan sebuah
           peristiwa yang tak pernah terjadi. */
        $lahir = now()->subDays($def['umur']);
        $ubah = ['Created_At' => $lahir];

        if ($def['tahap'] !== 'DRAFTING') {
            $ubah['Tanggal_Permintaan'] = $lahir->copy()->addDays(2 + ($idVersi % 6))->toDateString();
        }
        if ($def['tahap'] === 'APPROVAL') {
            $ubah['Tanggal_Respon'] = $lahir->copy()->addDays(6 + ($idVersi % 9))->toDateString();
            $ubah['Tanggal_Akhir_Approval'] = $lahir->copy()->addDays(9 + ($idVersi % 5))->toDateString();
        }

        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update($ubah);
    }

    /* ================= PAKET ================= */

    /**
     * Bagi dokumen terbit ke paket secara SELANG-SELING lintas jenis.
     *
     * Membagi berurutan katalog membuat satu paket berisi Kebijakan semua dan
     * paket lain Standar semua — akibatnya tiap tingkat piramid jatuh utuh ke
     * satu kelompok, dan batang bertumpuk di dashboard tak pernah benar-benar
     * bertumpuk. Yang ingin dibaca justru sebaliknya: dari 12 Prosedur, berapa
     * yang wajib dan berapa yang cuma terbuka.
     *
     * Pembagian bergilir (round-robin) atas daftar yang sudah dikelompokkan
     * per jenis memberi tiap paket campuran tingkat piramid, seperti paket SOP
     * sungguhan yang memang memuat kebijakan, prosedurnya, dan instruksi
     * kerjanya sekaligus.
     *
     * @param  array<int, array>  $terbit
     * @return array<int, array<int, array>>
     */
    private function bagiKePaket(array $terbit): array
    {
        /* Digilir per jenis: ambil satu dari Kebijakan, satu dari Prosedur,
           satu dari IK, dan seterusnya — baru putaran berikutnya. */
        $perJenis = [];
        foreach ($terbit as $d) {
            $perJenis[$d['jenis']][] = $d;
        }

        $antre = [];
        while ($perJenis !== []) {
            foreach ($perJenis as $kode => $daftar) {
                $antre[] = array_shift($perJenis[$kode]);
                if ($perJenis[$kode] === []) {
                    unset($perJenis[$kode]);
                }
            }
        }

        $bagian = [];
        $ambil = 0;
        foreach (self::PAKET as $i => $def) {
            $bagian[$i] = array_slice($antre, $ambil, $def['dok']);
            $ambil += count($bagian[$i]);
        }

        return $bagian;
    }

    private function buatPaket(array $def, int $hari): int
    {

        return DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => self::TANDA.' '.$def['nama'],
            'Deskripsi' => 'Paket peraga. Aman dihapus dengan sop:seed-demo --bersihkan.',
            'Prioritas' => $def['prioritas'],
            'Mode_Urutan_Item' => 'BEBAS',
            'Mode_Distribusi' => $def['distribusi'],
            'Status' => 'PUBLISHED',
            'Estimasi_Waktu_Baca_Menit' => 10 + mt_rand(0, 20),
            'Flag_Absensi' => 'T',
            'Tanggal_Publish' => now()->subDays($hari + 3),
            'Created_At' => now()->subDays($hari + 5),
            'Flag_Aktif' => 'Y',
        ], 'Id_SOP_Paket');
    }

    /**
     * Item paket yang MENUNJUK DOKUMEN, bukan paragraf lepas.
     *
     * Judul_Item dan Nomor_SOP_Resmi diisi dari dokumennya sebagai CERMINAN —
     * pola yang sama dengan yang dipakai fan-out Fase 6. Identitasnya tetap
     * milik N_SOP_Dokumen; salinan di sini hanya melayani sembilan jalur baca
     * besar yang membacanya langsung.
     */
    private function buatItem(int $idPaket, array $dokumen, int $hari): array
    {
        /* SECTION WAJIB ADA, dan ini bukan kelengkapan kosmetik.
           SopBuilder.vue hanya punya SATU perulangan item, dan letaknya di
           DALAM perulangan section: `v-for="it in itemsOf(sec.Id_SOP_Section)"`
           dengan `itemsOf` menyaring `Id_SOP_Section === sectionId`. Item yang
           tak bersection karena itu tak punya wadah untuk digambar — paketnya
           terbuka, tak ada galat, tetapi isinya kosong. */
        $idSection = DB::table('N_SOP_Paket_Section')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Nama_Section' => 'Dokumen Wajib Baca',
            'Deskripsi' => 'Seluruh dokumen dalam paket peraga ini.',
            'Urutan' => 1,
            'Created_At' => now()->subDays($hari + 5),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Section');

        $items = [];
        foreach ($dokumen as $n => $d) {
            $idItem = DB::table('N_SOP_Paket_Item')->insertGetId([
                'Id_SOP_Paket' => $idPaket,
                'Id_SOP_Section' => $idSection,
                'Tipe_Item' => $d['tipe'],
                'Judul_Item' => $d['judul'],
                'Nomor_SOP_Resmi' => $d['nomor'],
                'Id_SOP_Dokumen' => $d['dokumen'],
                'Id_Item_Versi_Aktif' => $d['versi'],
                'Urutan' => $n + 1,
                'Flag_Wajib' => 'Y',
                'Flag_Highlight' => 'T',
                'Created_At' => now()->subDays($hari + 5),
                'Flag_Aktif' => 'Y',
            ], 'Id_SOP_Paket_Item');

            $items[] = ['item' => $idItem, 'versi' => $d['versi'], 'tipe' => $d['tipe']];
        }

        return $items;
    }

    /**
     * Irisan karyawan per paket — digeser & dipotong berbeda tiap paket supaya
     * sebaran departemen tidak seragam (heatmap butuh kontras untuk bermakna).
     */
    private function irisTarget($karyawan, int $i)
    {
        $urut = $karyawan->sortBy(fn ($k) => md5(($k->dept ?? 'zz').$i))->values();

        return $urut->slice(0, (int) ceil($urut->count() * [0.28, 0.22, 0.3, 0.18, 0.12, 0.1][$i % 6]));
    }

    /**
     * Rencana batch per paket: campuran tenggat lewat (mengisi keempat keranjang
     * umur keterlambatan) dan tenggat masih berjalan.
     *
     * `lewat` = berapa hari lalu tenggatnya jatuh; null = tanpa tenggat.
     */
    private function rencanaBatch(int $i, int $hari): array
    {
        $rencana = [
            // paket 0 (KRITIS) — punya tunggakan tua, kasus terburuk yang harus terlihat
            [['mulai' => 0, 'jumlah' => 18, 'assign' => $hari, 'lewat' => 120, 'rasioSelesai' => 0.4],
                ['mulai' => 18, 'jumlah' => 22, 'assign' => 60, 'lewat' => 40, 'rasioSelesai' => 0.6],
                ['mulai' => 40, 'jumlah' => 24, 'assign' => 20, 'lewat' => 5, 'rasioSelesai' => 0.75]],
            // paket 1 (TINGGI)
            [['mulai' => 0, 'jumlah' => 20, 'assign' => 75, 'lewat' => 45, 'rasioSelesai' => 0.55],
                ['mulai' => 20, 'jumlah' => 24, 'assign' => 30, 'lewat' => 12, 'rasioSelesai' => 0.7]],
            // paket 2 (NORMAL) — sebagian besar sehat
            [['mulai' => 0, 'jumlah' => 26, 'assign' => 50, 'lewat' => null, 'rasioSelesai' => 0.85],
                ['mulai' => 26, 'jumlah' => 18, 'assign' => 15, 'lewat' => 3, 'rasioSelesai' => 0.5]],
            // paket 3 (TINGGI) — baru berjalan
            [['mulai' => 0, 'jumlah' => 16, 'assign' => 25, 'lewat' => null, 'rasioSelesai' => 0.45],
                ['mulai' => 16, 'jumlah' => 12, 'assign' => 8, 'lewat' => null, 'rasioSelesai' => 0.2]],
            // paket 4 (PUBLIK, belum dibuka) — satu batch kecil, semuanya BELUM
            [['mulai' => 0, 'jumlah' => 9, 'assign' => 18, 'lewat' => null, 'rasioSelesai' => 0.0]],
            [],
        ];

        return $rencana[$i % 6];
    }

    /**
     * @param  bool|string  $mode  true = normal; 'belum-dibuka' = seluruhnya BELUM
     *                             dan tak satu pun progres punya Waktu_Buka
     */
    private function buatBatch(int $idPaket, array $items, $orang, array $b, $mode): array
    {
        $tglAssign = now()->subDays($b['assign']);
        $adaTenggat = $b['lewat'] !== null;
        $belumDibuka = $mode === 'belum-dibuka';

        $idPj = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket,
            'Nama_Batch' => self::TANDA.' Angkatan '.$tglAssign->translatedFormat('d M Y'),
            'Keterangan' => 'Batch peraga.',
            'Sumber' => 'MANUAL',
            'Tipe_Tenggat' => $adaTenggat ? 'DEADLINE' : 'SAMPAI_DICABUT',
            'Tanggal_Deadline' => $adaTenggat ? now()->subDays($b['lewat'])->startOfDay() : null,
            'Flag_Reminder' => 'T',
            'Flag_Cabut_Akses' => 'T',
            'Tanggal_Assign' => $tglAssign,
            'Created_At' => $tglAssign,
            'Flag_Aktif' => 'Y',
        ], 'Id_SOP_Penjadwalan');

        $nDetail = 0;
        $nProgress = 0;
        $nLedger = 0;

        foreach ($orang as $k) {
            $selesai = ! $belumDibuka && mt_rand(1, 100) <= (int) round($b['rasioSelesai'] * 100);

            $statusPaket = $belumDibuka
                ? 'BELUM'
                : ($selesai ? 'SELESAI' : (mt_rand(0, 1) ? 'PROGRESS' : 'BELUM'));

            $idDetail = DB::table('N_SOP_Penjadwalan_Detail')->insertGetId([
                'Id_SOP_Penjadwalan' => $idPj,
                'Kode_Karyawan' => $k->Kode_Karyawan,
                'Status_Paket' => $statusPaket,
                'Flag_Cabut_Akses' => 'T',
                'Created_At' => $tglAssign,
                'Flag_Aktif' => 'Y',
            ], 'Id_SOP_Penjadwalan_Detail');
            $nDetail++;

            // Sebaran jarak buka→konfirmasi: sengaja mengisi KELIMA keranjang
            // distribusi waktu, termasuk yang "kilat" (< 1 jam) supaya detektor
            // Konfirmasi Kilat punya bahan yang bisa diperiksa.
            $polaJeda = [12, 40, 180, 900, 2600, 6200, 13000]; // menit
            $rows = [];
            foreach ($items as $idx => $it) {
                $itemSelesai = ! $belumDibuka && ($statusPaket === 'SELESAI'
                    || ($statusPaket === 'PROGRESS' && $idx < (int) floor(count($items) / 2)));

                $buka = null;
                $konfirmasi = null;
                if (! $belumDibuka && ($itemSelesai || $statusPaket === 'PROGRESS')) {
                    $buka = $tglAssign->copy()->addDays(mt_rand(0, max(1, $b['assign'] - 1)))->addMinutes(mt_rand(0, 600));
                    if ($buka->gt(now())) {
                        $buka = now()->subHours(mt_rand(1, 72));
                    }
                }
                if ($itemSelesai && $buka) {
                    // ~6% "cap jempol": konfirmasi hitungan detik setelah dibuka.
                    // Ini yang memberi detektor Konfirmasi Kilat bahan untuk
                    // diperiksa — tanpa kasus mencurigakan, panelnya selalu kosong
                    // dan fiturnya tak bisa didemokan sama sekali.
                    $konfirmasi = mt_rand(1, 100) <= 6
                        ? $buka->copy()->addSeconds(mt_rand(3, 14))
                        : $buka->copy()->addMinutes($polaJeda[mt_rand(0, count($polaJeda) - 1)]);

                    if ($konfirmasi->gt(now())) {
                        $konfirmasi = now()->subHours(mt_rand(1, 24));
                    }
                }

                $rows[] = [
                    'Id_SOP_Penjadwalan_Detail' => $idDetail,
                    'Id_SOP_Paket_Item' => $it['item'],
                    'Id_SOP_Item_Versi' => $it['versi'],
                    'Kode_Karyawan' => $k->Kode_Karyawan,
                    'Tipe_Item' => $it['tipe'],
                    'Status' => $itemSelesai ? 'DIKONFIRMASI' : ($buka ? 'DIBUKA' : 'BELUM'),
                    'Durasi_Aktif_Detik' => $konfirmasi ? mt_rand(30, 1800) : 0,
                    'Jumlah_Percobaan' => 0,
                    'Waktu_Buka' => $buka,
                    'Waktu_Konfirmasi' => $konfirmasi,
                    'Created_At' => $tglAssign,
                ];
            }
            foreach (array_chunk($rows, 200) as $potong) {
                DB::table('N_SOP_Progress')->insert($potong);
                $nProgress += count($potong);
            }

            // Ledger: inilah yang menghidupkan grafik Tren. Waktu peristiwa
            // TERSEBAR di sepanjang rentang, bukan menumpuk di hari seeding —
            // deret waktu yang semua titiknya hari ini tak menggambarkan apa pun.
            if ($statusPaket === 'SELESAI') {
                $waktu = $tglAssign->copy()->addDays(mt_rand(1, max(2, $b['assign'])));
                if ($waktu->gt(now())) {
                    $waktu = now()->subHours(mt_rand(2, 48));
                }

                DB::table('N_SOP_Kepatuhan_Riwayat')->insert([
                    'Id_SOP_Penjadwalan_Detail' => $idDetail,
                    'Id_SOP_Paket' => $idPaket,
                    'Kode_Karyawan' => $k->Kode_Karyawan,
                    'Peristiwa' => 'SELESAI',
                    'Persen_Saat_Itu' => 100,
                    'Waktu_Peristiwa' => $waktu,
                    'Created_At' => $waktu,
                ]);
                $nLedger++;

                // Sebagian kecil dibuka ulang (versi baru terbit) — memberi grafik
                // tren garis "dibuka lagi" yang tak nol, seperti kepatuhan nyata.
                if (mt_rand(1, 100) <= 12) {
                    $ulang = $waktu->copy()->addDays(mt_rand(2, 20));
                    if ($ulang->lt(now())) {
                        DB::table('N_SOP_Kepatuhan_Riwayat')->insert([
                            'Id_SOP_Penjadwalan_Detail' => $idDetail,
                            'Id_SOP_Paket' => $idPaket,
                            'Kode_Karyawan' => $k->Kode_Karyawan,
                            'Peristiwa' => 'DIBUKA_LAGI',
                            'Alasan' => 'VERSI_BARU',
                            'Waktu_Peristiwa' => $ulang,
                            'Created_At' => $ulang,
                        ]);
                        $nLedger++;
                    }
                }
            }
        }

        $this->rollup($idPaket, $idPj, $items, $nDetail);

        return ['detail' => $nDetail, 'progress' => $nProgress, 'ledger' => $nLedger];
    }

    /**
     * Kohort pembaca MANDIRI pada paket publik.
     *
     * KENAPA HARUS ADA. N_SOP_Progress berkunci pada Id_SOP_Penjadwalan_Detail
     * yang NOT NULL, sehingga karyawan yang membuka SOP publik didaftarkan
     * sendiri oleh sistem (SopPendaftaranMandiri, Sumber='MANDIRI'). Baris
     * itulah SATU-SATUNYA jejak minat sukarela yang dimiliki sistem, dan
     * seluruh tab analisis Publik dibangun di atasnya. Tanpa kohort ini tab
     * tersebut menampilkan nol di setiap angka — bukan karena tak ada yang
     * membaca, melainkan karena dunia peraga tak pernah punya pembacanya.
     *
     * SEBARANNYA SENGAJA TIMPANG. Sebagian dokumen dibaca banyak orang,
     * sebagian sedikit, dan setidaknya satu TIDAK SAMA SEKALI — panel "dokumen
     * yang diabaikan" hanya bermakna kalau memang ada yang diabaikan.
     */
    private function kohortMandiri(int $idPaket, array $items, $karyawan): array
    {
        if ($items === []) {
            return ['detail' => 0, 'progress' => 0];
        }

        /* Dokumen TERAKHIR sengaja tak diberi pembaca sama sekali. */
        $bisaDibaca = array_slice($items, 0, max(1, count($items) - 1));

        $peminat = $karyawan->sortBy(fn ($k) => md5('mandiri'.$k->Kode_Karyawan))
            ->take(120)->values();

        $nDetail = 0;
        $nProgress = 0;

        foreach ($peminat as $urut => $k) {
            /* Ditemukan bertahap sepanjang 60 hari, bukan serempak: tren minat
               yang seluruh titiknya jatuh di satu hari tak menggambarkan apa
               pun. */
            $daftar = now()->subDays(60 - (int) floor($urut / 2))->addMinutes(mt_rand(0, 600));
            if ($daftar->gt(now())) {
                $daftar = now()->subHours(mt_rand(1, 48));
            }

            $idPj = DB::table('N_SOP_Penjadwalan')->insertGetId([
                'Id_SOP_Paket' => $idPaket,
                'Nama_Batch' => self::TANDA.' Pendaftaran mandiri',
                'Keterangan' => 'Karyawan membuka dokumen publik ini sendiri.',
                'Sumber' => 'MANDIRI',
                'Tipe_Tenggat' => 'SAMPAI_DICABUT',
                /* Created_By NULL — tak ada admin yang menugaskan. Sama persis
                   dengan yang ditulis SopPendaftaranMandiri di jalur nyata. */
                'Created_By' => null,
                'Tanggal_Assign' => $daftar,
                'Created_At' => $daftar,
                'Flag_Aktif' => SopStatus::AKTIF,
            ], 'Id_SOP_Penjadwalan');

            $idDetail = DB::table('N_SOP_Penjadwalan_Detail')->insertGetId([
                'Id_SOP_Penjadwalan' => $idPj,
                'Kode_Karyawan' => $k->Kode_Karyawan,
                'Status_Paket' => SopStatus::STATUS_PROGRESS,
                'Flag_Cabut_Akses' => 'T',
                'Created_At' => $daftar,
                'Flag_Aktif' => SopStatus::AKTIF,
            ], 'Id_SOP_Penjadwalan_Detail');
            $nDetail++;

            /* Makin ke bawah daftar dokumen, makin sedikit yang membacanya —
               orang berhenti di tengah, seperti membaca apa pun yang tak wajib. */
            $rows = [];
            foreach ($bisaDibaca as $idx => $it) {
                if (mt_rand(1, 100) > max(15, 95 - $idx * 25)) {
                    continue;
                }

                $buka = $daftar->copy()->addHours(mt_rand(0, 72));
                if ($buka->gt(now())) {
                    $buka = now()->subHours(mt_rand(1, 24));
                }

                $rows[] = [
                    'Id_SOP_Penjadwalan_Detail' => $idDetail,
                    'Id_SOP_Paket_Item' => $it['item'],
                    'Id_SOP_Item_Versi' => $it['versi'],
                    'Kode_Karyawan' => $k->Kode_Karyawan,
                    'Tipe_Item' => $it['tipe'],
                    /* DIBUKA, bukan DIKONFIRMASI: tak ada yang mewajibkannya,
                       jadi tak ada yang perlu dikonfirmasi. */
                    'Status' => 'DIBUKA',
                    'Durasi_Aktif_Detik' => mt_rand(20, 900),
                    'Jumlah_Percobaan' => 0,
                    'Waktu_Buka' => $buka,
                    'Waktu_Konfirmasi' => null,
                    'Created_At' => $daftar,
                ];
            }

            foreach (array_chunk($rows, 200) as $potong) {
                DB::table('N_SOP_Progress')->insert($potong);
                $nProgress += count($potong);
            }
        }

        return ['detail' => $nDetail, 'progress' => $nProgress];
    }

    /** Rollup coverage supaya tab Paket ikut menampilkan angka yang masuk akal. */
    private function rollup(int $idPaket, int $idPj, array $items, int $nDetail): void
    {
        foreach ($items as $it) {
            $dikonfirmasi = DB::table('N_SOP_Progress as g')
                ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'g.Id_SOP_Penjadwalan_Detail')
                ->where('d.Id_SOP_Penjadwalan', $idPj)
                ->where('g.Id_SOP_Item_Versi', $it['versi'])
                ->where('g.Status', 'DIKONFIRMASI')->count();

            $ada = DB::table('N_SOP_Coverage_Rollup')
                ->where('Id_SOP_Paket', $idPaket)->where('Id_SOP_Item_Versi', $it['versi'])->exists();

            if ($ada) {
                DB::table('N_SOP_Coverage_Rollup')
                    ->where('Id_SOP_Paket', $idPaket)->where('Id_SOP_Item_Versi', $it['versi'])
                    ->update([
                        'Total_Target' => DB::raw('Total_Target + '.$nDetail),
                        'Jumlah_Dikonfirmasi' => DB::raw('Jumlah_Dikonfirmasi + '.$dikonfirmasi),
                        'Updated_At' => now(),
                    ]);

                continue;
            }

            DB::table('N_SOP_Coverage_Rollup')->insert([
                'Id_SOP_Paket' => $idPaket,
                'Id_SOP_Item_Versi' => $it['versi'],
                'Total_Target' => $nDetail,
                'Jumlah_Dibuka' => 0,
                'Jumlah_Dikonfirmasi' => $dikonfirmasi,
                'Persen_Dikonfirmasi' => $nDetail > 0 ? round($dikonfirmasi / $nDetail * 100) : 0,
                'Updated_At' => now(),
            ]);
        }
    }

    /* ================= PEMBERSIHAN ================= */

    private function bersihkan(): int
    {
        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Nama_Paket', 'LIKE', self::POLA)->pluck('Id_SOP_Paket');
        $dokumen = DB::table('N_SOP_Dokumen')
            ->where('Judul', 'LIKE', self::POLA)->pluck('Id_SOP_Dokumen');

        if ($paket->isEmpty() && $dokumen->isEmpty()) {
            $this->info('Tak ada data peraga yang perlu dihapus.');

            return self::SUCCESS;
        }

        $stat = [];
        DB::transaction(function () use ($paket, $dokumen, &$stat) {
            $pj = DB::table('N_SOP_Penjadwalan')->whereIn('Id_SOP_Paket', $paket)->pluck('Id_SOP_Penjadwalan');
            $det = DB::table('N_SOP_Penjadwalan_Detail')->whereIn('Id_SOP_Penjadwalan', $pj)->pluck('Id_SOP_Penjadwalan_Detail');
            $item = DB::table('N_SOP_Paket_Item')->whereIn('Id_SOP_Paket', $paket)->pluck('Id_SOP_Paket_Item');

            // Urutan penghapusan mengikuti arah ketergantungan, dari daun ke akar.
            $stat['progress'] = DB::table('N_SOP_Progress')->whereIn('Id_SOP_Penjadwalan_Detail', $det)->delete();
            $stat['ledger'] = DB::table('N_SOP_Kepatuhan_Riwayat')->whereIn('Id_SOP_Penjadwalan_Detail', $det)->delete();
            DB::table('N_SOP_Penjadwalan_Integrasi')->whereIn('Id_SOP_Penjadwalan_Detail', $det)->delete();
            $stat['detail'] = DB::table('N_SOP_Penjadwalan_Detail')->whereIn('Id_SOP_Penjadwalan_Detail', $det)->delete();
            $stat['penjadwalan'] = DB::table('N_SOP_Penjadwalan')->whereIn('Id_SOP_Penjadwalan', $pj)->delete();
            $stat['rollup'] = DB::table('N_SOP_Coverage_Rollup')->whereIn('Id_SOP_Paket', $paket)->delete();
            DB::table('N_SOP_Log')->whereIn('Id_SOP_Paket', $paket)->delete();
            /* Snapshot versi paket menunjuk BARIS ITEM, jadi ia harus jatuh
               sebelum itemnya — kalau tidak, kunci asing menolak dan
               penghapusan gagal justru pada paket yang paling lengkap. */
            DB::table('N_SOP_Paket_Versi_Item')->whereIn('Id_SOP_Paket_Versi',
                DB::table('N_SOP_Paket_Versi')->whereIn('Id_SOP_Paket', $paket)->select('Id_SOP_Paket_Versi'))->delete();
            $stat['paket_versi'] = DB::table('N_SOP_Paket_Versi')->whereIn('Id_SOP_Paket', $paket)->delete();
            $stat['item'] = DB::table('N_SOP_Paket_Item')->whereIn('Id_SOP_Paket_Item', $item)->delete();
            $stat['section'] = DB::table('N_SOP_Paket_Section')->whereIn('Id_SOP_Paket', $paket)->delete();
            $stat['paket'] = DB::table('N_SOP_Master_Paket')->whereIn('Id_SOP_Paket', $paket)->delete();

            /* Dokumen dihapus TERAKHIR, dan pointer versi aktifnya dilepas
               lebih dulu: N_SOP_Dokumen.Id_Versi_Aktif menunjuk baris versi
               yang hendak kita hapus, jadi menghapus versi lebih dulu akan
               ditolak kunci asing. */
            $versi = DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Dokumen', $dokumen)
                ->pluck('Id_SOP_Item_Versi');
            DB::table('N_SOP_Dokumen')->whereIn('Id_SOP_Dokumen', $dokumen)
                ->update(['Id_Versi_Aktif' => null]);

            DB::table('N_SOP_Item_Versi_Pengesah')->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            DB::table('N_SOP_Item_Versi_Review_Lampiran')
                ->whereIn('Id_Review', DB::table('N_SOP_Item_Versi_Review')
                    ->whereIn('Id_SOP_Item_Versi', $versi)->select('Id_Review'))->delete();
            DB::table('N_SOP_Item_Versi_Review')->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            DB::table('N_SOP_Item_Versi_Berkas')->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            $stat['versi'] = DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            DB::table('N_SOP_Log')->whereIn('Id_SOP_Dokumen', $dokumen)->delete();
            $stat['dokumen'] = DB::table('N_SOP_Dokumen')->whereIn('Id_SOP_Dokumen', $dokumen)->delete();
        });

        // Notifikasi peraga tak pernah dibuat (seeder tak memanggil layanan notifikasi),
        // jadi tak ada yang perlu dibersihkan di sana.
        Cache::store('database')->flush();

        $this->info('Data peraga dihapus.');
        foreach ($stat as $k => $v) {
            $this->line('  '.str_pad($k, 14).$v);
        }

        return self::SUCCESS;
    }
}
