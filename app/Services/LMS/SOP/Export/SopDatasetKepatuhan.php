<?php

namespace App\Services\LMS\SOP\Export;

use App\Services\LMS\SOP\SopPrioritasService;
use App\Services\LMS\SOP\Support\SopGerbangPaket;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopVersiTanggal;
use App\Services\SYSTEM\Application\Reports\Kontrak\DatasetLaporan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Dimensi;
use App\Services\SYSTEM\Application\Reports\Kontrak\Lingkup;
use App\Services\SYSTEM\Application\Reports\Kontrak\PenyediaOpsiSaringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Saringan;
use App\Services\SYSTEM\Application\Reports\Kontrak\Ukuran;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Satu baris = satu (karyawan × penugasan paket × item).
 *
 * TIGA ATURAN BISNIS DIPATRI DI baseQuery() DAN TIDAK BISA DIMATIKAN TEMPLATE:
 *
 *  1. d.Flag_Aktif = 'Y'
 *  2. d.Flag_Cabut_Akses = 'T' — penugasan yang aksesnya dicabut bukan lagi
 *     kewajiban karyawan; seluruh layar Monitoring sudah mengecualikannya.
 *  3. Join ke N_SOP_Progress adalah LEFT. Item yang belum pernah dibuka TIDAK
 *     punya baris progress; inner join menghapusnya dari laporan sehingga
 *     tunggakan lenyap dan kepatuhan terbaca lebih baik daripada kenyataannya.
 *
 * Divisi, departemen, dan jabatan tidak ada sebagai kolom di tabel Karyawan;
 * ketiganya datang dari dua view, sama seperti yang dipakai
 * SopMonitoringPeopleService. Lokasi kerja adalah Cabang_Untuk_Gaji.
 *
 * ATURAN KEEMPAT, DITAMBAHKAN BERSAMA LAPIS DOKUMEN & VERSI: seluruh join ke
 * N_SOP_Dokumen, master-masternya, dan N_SOP_Item_Versi WAJIB LEFT.
 *
 * Item SOP tidak selalu berupa dokumen — kuis, tautan, dan konten bebas hidup
 * di tabel yang sama dengan Id_SOP_Dokumen kosong. Item berdokumen pun belum
 * tentu punya versi aktif; justru itulah salah satu temuan yang dicari orang
 * di laporan ini. Satu saja join di antaranya dijadikan INNER, baris-baris itu
 * lenyap dari laporan, dan kepatuhan terbaca LEBIH BAIK daripada kenyataannya
 * — kegagalan yang tak menimbulkan galat dan tak terlihat sampai ada yang
 * membandingkan totalnya dengan layar Monitoring.
 */
class SopDatasetKepatuhan implements DatasetLaporan, PenyediaOpsiSaringan
{
    public function kunci(): string
    {
        return 'kepatuhan';
    }

    public function label(): string
    {
        return 'Kepatuhan & Penugasan Dokumen';
    }

    public function grain(): string
    {
        return 'Satu baris = satu karyawan × paket × item.';
    }

    public function dimensi(): array
    {
        return [
            new Dimensi('kode', 'Kode Karyawan', 'd.Kode_Karyawan', 'teks', 'identitas'),
            new Dimensi('nama', 'Nama Karyawan', 'k.Nama', 'teks', 'identitas'),
            new Dimensi('no_induk', 'No. Induk', 'k.No_Induk_Karyawan', 'teks', 'identitas'),
            new Dimensi('lokasi', 'Lokasi Kerja', 'k.Cabang_Untuk_Gaji', 'teks', 'identitas'),
            new Dimensi('divisi', 'Divisi', 'div.nama_divisi', 'teks', 'identitas'),
            new Dimensi('departemen', 'Departemen', 'div.nama_sub_divisi', 'teks', 'identitas'),
            new Dimensi('jabatan', 'Jabatan', 'lvl.nama_jabatan', 'teks', 'identitas'),

            new Dimensi('paket', 'Nama Paket Dokumen', 'mp.Nama_Paket', 'teks', 'paket'),
            new Dimensi('kode_paket', 'Kode Paket', 'mp.Kode_Paket', 'teks', 'paket'),
            new Dimensi('distribusi', 'Tipe Distribusi', "CASE WHEN mp.Mode_Distribusi = 'TERBATAS' THEN 'Tertutup (Ditugaskan)' ELSE 'Publik' END", 'teks', 'paket'),
            new Dimensi('sumber', 'Sumber Penugasan', "CASE WHEN pj.Sumber = 'MANDIRI' THEN 'Pendaftaran Mandiri' WHEN pj.Sumber = 'AUTO_RULE' THEN 'Aturan Otomatis' ELSE 'Penugasan Admin' END", 'teks', 'paket'),
            new Dimensi('prioritas', 'Prioritas', 'mp.Prioritas', 'teks', 'paket'),
            new Dimensi('status_paket', 'Status Paket', 'd.Status_Paket', 'teks', 'paket'),
            new Dimensi('tgl_assign', 'Tgl Assign', 'pj.Tanggal_Assign', 'tanggal', 'paket', false, 'tanggal'),
            new Dimensi('tenggat', 'Tenggat',
                // Aturan bisnis, bukan format: penugasan tanpa tipe DEADLINE memang
                // tidak punya tenggat, dan berkas lama menuliskannya apa adanya
                // alih-alih mengosongkannya. Tempatnya di katalog, bukan di template.
                "CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' AND pj.Tanggal_Deadline IS NOT NULL"
                ." THEN CONVERT(varchar(10), pj.Tanggal_Deadline, 120) ELSE 'Tanpa tenggat' END",
                'teks', 'paket'),

            new Dimensi('section', 'Section', 'ps.Nama_Section', 'teks', 'item'),
            new Dimensi('urutan', 'Urutan', 'pi.Urutan', 'angka', 'item'),

            /*
             * IDENTITAS DIBACA DARI SUMBERNYA, CERMINAN HANYA CADANGAN.
             *
             * Judul dan nomor juga tersimpan di N_SOP_Paket_Item, tetapi di
             * sana keduanya CERMINAN: ditimpa dari N_SOP_Dokumen setiap kali
             * item disimpan. Cerminan bisa menyimpang — pada baris lama yang
             * belum pernah disunting ulang sejak dokumennya berganti nomor,
             * laporan menyebut nomor yang sudah tidak berlaku, dan tak ada
             * apa pun di berkas yang menandainya.
             *
             * COALESCE, bukan dok.* polos: item yang memang bukan dokumen
             * (kuis, tautan, konten bebas) tak punya baris di N_SOP_Dokumen,
             * dan membacanya langsung akan mengosongkan judulnya sama sekali.
             */
            new Dimensi('item', 'Judul Item', 'COALESCE(dok.Judul, pi.Judul_Item)', 'teks', 'item'),
            new Dimensi('nomor_sop', 'No. Dokumen Resmi', 'COALESCE(dok.Nomor_SOP_Resmi, pi.Nomor_SOP_Resmi)', 'teks', 'item'),
            new Dimensi('tipe_item', 'Tipe Item', 'pi.Tipe_Item', 'teks', 'item'),
            new Dimensi('wajib', 'Wajib', 'pi.Flag_Wajib', 'teks', 'item', false, 'ya_tidak'),

            /* ── Lapis dokumen: identitas resmi di balik item ──────────────
               Dibaca dari N_SOP_Dokumen dan master-masternya, bukan dari
               apa pun yang tersalin ke paket. Dokumen yang sama dipakai
               beberapa paket karena itu selalu terbaca sama di sini. */
            new Dimensi('no_dokumen', 'No. Dokumen', 'dok.No_Dokumen', 'angka', 'dokumen'),
            new Dimensi('tipe_dokumen', 'Tipe Dokumen', 'dok.Tipe', 'teks', 'dokumen'),
            new Dimensi('jenis_dokumen', 'Jenis Dokumen', 'jd.Nama_Jenis', 'teks', 'dokumen'),
            new Dimensi('kode_jenis', 'Kode Jenis', 'jd.Kode_Jenis_Dokumen', 'teks', 'dokumen'),
            new Dimensi('level_hirarki', 'Level Hirarki', 'jd.Level_Hirarki', 'angka', 'dokumen'),
            new Dimensi('apqc', 'Proses APQC', 'ap.Nama_Proses', 'teks', 'dokumen'),
            new Dimensi('kode_apqc', 'Kode APQC', 'ap.Kode_Proses', 'teks', 'dokumen'),
            new Dimensi('dept_pemilik', 'Departemen Pemilik', 'dpt.Nama_Departemen', 'teks', 'dokumen'),

            /* ── Lapis versi aktif yang sedang disajikan ke karyawan ───────
               Semuanya lewat pi.Id_Item_Versi_Aktif, yakni versi yang BENAR
               -BENAR dibaca orang — bukan versi terbaru, dan bukan draft yang
               sedang digarap di wizard. */
            new Dimensi('versi_ke', 'Versi Ke', 'vr.Versi_Ke', 'angka', 'versi'),
            new Dimensi('status_versi', 'Status Versi', 'vr.Status', 'teks', 'versi'),
            new Dimensi('tahap_versi', 'Tahap Versi', 'th.Nama_Tahap', 'teks', 'versi'),
            new Dimensi('langkah_versi', 'Langkah Wizard', 'th.Langkah', 'angka', 'versi'),
            new Dimensi('kelompok_tahap', 'Kelompok Tahap', 'th.Kelompok', 'teks', 'versi'),
            new Dimensi('tgl_berlaku', 'Tgl Berlaku', 'vr.Tanggal_Berlaku', 'tanggal', 'versi', false, 'tanggal'),
            new Dimensi('tgl_kadaluarsa', 'Tgl Kedaluwarsa', 'vr.Tanggal_Kadaluarsa', 'tanggal', 'versi', false, 'tanggal'),
            new Dimensi('tgl_review_berikutnya', 'Tgl Review Berikutnya', 'vr.Tanggal_Review_Berikutnya', 'tanggal', 'versi', false, 'tanggal'),

            /* Keadaan, bukan tanggal. Inilah kolom yang sebenarnya dicari
               orang saat bertanya "mana yang bermasalah" — lihat catatan di
               SopVersiTanggal soal kenapa 'Tanpa versi aktif' berdiri sendiri
               dan tidak dilebur jadi sel kosong. */
            new Dimensi('status_masa_berlaku', 'Status Masa Berlaku', SopVersiTanggal::sqlStatusMasaBerlaku('vr'), 'teks', 'versi'),
            new Dimensi('status_review', 'Status Review', SopVersiTanggal::sqlStatusReview('vr'), 'teks', 'versi'),

            new Dimensi('nomor_pengesahan', 'Nomor Pengesahan', 'vr.Nomor_Pengesahan', 'teks', 'versi'),
            new Dimensi('pengesah', 'Pengesah', 'vr.Disahkan_Oleh', 'teks', 'versi'),
            new Dimensi('jabatan_pengesah', 'Jabatan Pengesah', 'vr.Jabatan_Pengesah', 'teks', 'versi'),
            new Dimensi('tgl_pengesahan', 'Tgl Pengesahan', 'vr.Tanggal_Pengesahan', 'tanggal', 'versi', false, 'tanggal'),
            new Dimensi('baca_ulang', 'Wajib Baca Ulang', 'vr.Wajib_Baca_Ulang', 'teks', 'versi', false, 'ya_tidak'),

            // COALESCE, sama seperti jalur lama: item tanpa rekam progress belum
            // pernah disentuh, bukan data hilang. Tanpa ini keadaan terpenting
            // (belum dibaca) tertulis '-' dan tak bisa disaring sama sekali,
            // karena '= BELUM' tidak pernah cocok dengan NULL.
            //
            // Grupnya 'baca', bukan 'versi'. Ini status BACAAN seorang karyawan
            // atas sebuah item, dan sejak grup 'versi' benar-benar berisi versi
            // ia jadi satu-satunya penghuni yang salah kandang. Yang berubah
            // hanya pengelompokan di layar; kuncinya tetap 'status_item'
            // sehingga template yang sudah tersimpan tak tersentuh.
            new Dimensi('status_item', 'Status Item', "COALESCE(pr.Status, '".SopStatus::ITEM_BELUM."')", 'teks', 'baca'),
            new Dimensi('persen_item', 'Progress Item', 'pr.Persen_Selesai', 'angka', 'baca'),
            new Dimensi('waktu_buka', 'Waktu Buka', 'pr.Waktu_Buka', 'tanggal', 'baca', false, 'tanggal_jam'),
            new Dimensi('waktu_konfirmasi', 'Waktu Konfirmasi', 'pr.Waktu_Konfirmasi', 'tanggal', 'baca', false, 'tanggal_jam'),
            new Dimensi('bulan_konfirmasi', 'Bulan Konfirmasi', "FORMAT(pr.Waktu_Konfirmasi, 'yyyy-MM')", 'teks', 'baca'),

            new Dimensi('skor_kuis', 'Skor Kuis', 'pr.Skor_Quiz', 'angka', 'kuis'),
            new Dimensi('percobaan', 'Percobaan', 'pr.Jumlah_Percobaan', 'angka', 'kuis'),
            new Dimensi('lulus', 'Lulus', 'pr.Status_Lulus', 'teks', 'kuis', false, 'ya_tidak'),

            new Dimensi('ip', 'IP Konfirmasi', 'pr.Konfirmasi_IP', 'teks', 'atestasi', true),
            new Dimensi('perangkat', 'Perangkat', 'pr.Konfirmasi_User_Agent', 'teks', 'atestasi', true),
        ];
    }

    public function ukuran(): array
    {
        $selesai = "CASE WHEN pr.Status IN ('".SopStatus::ITEM_SELESAI."','".SopStatus::ITEM_DIKONFIRMASI."') THEN 1 ELSE 0 END";

        // Kolom keenam (totalisasi) menentukan nasib ukuran ini di baris GRAND
        // TOTAL. Hanya yang benar-benar aditif yang boleh dijumlah: COUNT(*) dan
        // SUM(). COUNT(DISTINCT) menghitung ganda begitu satu orang atau satu
        // paket muncul di lebih dari satu kelompok, dan menjumlahkan rata-rata
        // atau persentase menghasilkan angka yang salah tapi tampak resmi.
        return [
            // COUNT(DISTINCT ...), bukan COUNT(*): grain tabel ini adalah ITEM.
            // COUNT(*) di sini menghitung item, bukan orang.
            new Ukuran('jml_karyawan', 'Jumlah Karyawan', 'COUNT(DISTINCT d.Kode_Karyawan)', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('jml_penugasan', 'Jumlah Penugasan', 'COUNT(DISTINCT d.Id_SOP_Penjadwalan_Detail)', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('jml_paket', 'Jumlah Paket', 'COUNT(DISTINCT mp.Id_SOP_Paket)', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('jml_item', 'Jumlah Item', 'COUNT(*)', 'angka'),
            new Ukuran('jml_selesai', 'Item Selesai', "SUM({$selesai})", 'angka'),
            new Ukuran('jml_belum', 'Item Belum', "SUM(CASE WHEN pr.Status IS NULL OR pr.Status = '".SopStatus::ITEM_BELUM."' THEN 1 ELSE 0 END)", 'angka'),
            new Ukuran('persen_selesai', 'Persen Selesai', "CAST(SUM({$selesai}) * 100.0 / NULLIF(COUNT(*), 0) AS decimal(5,1))", 'persen', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('durasi_baca_rata', 'Durasi Baca Rata-rata (detik)', 'AVG(CAST(pr.Durasi_Aktif_Detik AS float))', 'durasi', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('skor_kuis_rata', 'Skor Kuis Rata-rata', 'AVG(CAST(pr.Skor_Quiz AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),

            /* ── Lapis dokumen & versi ────────────────────────────────────
             *
             * LABELNYA MENYEBUT 'ITEM', BUKAN 'DOKUMEN', DAN ITU DISENGAJA.
             * Butiran tabel ini adalah karyawan × paket × item; SUM apa pun di
             * sini menghitung baris item, bukan dokumen. Menamainya "Dokumen
             * Kadaluarsa" akan menghasilkan angka yang jauh lebih besar dari
             * jumlah dokumen sebenarnya — benar secara aritmetika, menyesatkan
             * secara bisnis, dan tak seorang pun yang membuka berkasnya bisa
             * tahu. Untuk hitungan berbutir dokumen, dataset 'dokumen' yang
             * dipakai.
             */
            new Ukuran('jml_dokumen', 'Jumlah Dokumen', 'COUNT(DISTINCT dok.Id_SOP_Dokumen)', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('jml_item_tanpa_dokumen', 'Item Tanpa Dokumen', 'SUM(CASE WHEN pi.Id_SOP_Dokumen IS NULL THEN 1 ELSE 0 END)', 'angka'),

            /* Item yang disajikan ke karyawan tanpa versi aktif sama sekali.
               Bukan sekadar data kosong: pointer versi aktif hanya boleh
               menunjuk versi yang sudah sah terbit, jadi baris di sini adalah
               bacaan wajib yang tidak berdiri di atas versi mana pun. */
            new Ukuran('jml_item_tanpa_versi', 'Item Tanpa Versi Aktif', 'SUM(CASE WHEN pi.Id_Item_Versi_Aktif IS NULL THEN 1 ELSE 0 END)', 'angka'),
            new Ukuran('jml_item_kadaluarsa', 'Item Berversi Kedaluwarsa', 'SUM('.SopVersiTanggal::sqlKadaluarsa('vr').')', 'angka'),
            new Ukuran('jml_item_review_jatuh_tempo', 'Item Review Jatuh Tempo', 'SUM('.SopVersiTanggal::sqlReviewJatuhTempo('vr').')', 'angka'),
        ];
    }

    public function saringan(): array
    {
        return [
            new Saringan('periode', 'Periode Konfirmasi', 'pr.Waktu_Konfirmasi', 'tanggal', [
                'antara', 'sama_dengan', 'tidak_sama', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
            new Saringan('tgl_assign', 'Tanggal Assign', 'pj.Tanggal_Assign', 'tanggal', [
                'antara', 'sama_dengan', 'tidak_sama', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
            // COALESCE, bukan kolom polos: Mode_Distribusi NULL diperlakukan
            // PUBLIK di seluruh modul, jadi menyaring 'PUBLIK' pada kolom mentah
            // diam-diam membuang paket lama yang kolomnya belum terisi.
            new Saringan('distribusi', 'Tipe Distribusi', "COALESCE(mp.Mode_Distribusi, 'PUBLIK')", 'teks', [
                'sama_dengan', 'tidak_sama', 'salah_satu',
            ]),
            new Saringan('sumber', 'Sumber Penugasan', 'pj.Sumber', 'teks', [
                'sama_dengan', 'tidak_sama', 'salah_satu',
            ]),
            new Saringan('divisi', 'Divisi', 'div.nama_divisi', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama', 'mengandung',
            ]),
            new Saringan('departemen', 'Departemen', 'div.nama_sub_divisi', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama', 'mengandung',
            ]),
            new Saringan('lokasi', 'Lokasi Kerja', 'k.Cabang_Untuk_Gaji', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama', 'mengandung',
            ]),
            new Saringan('paket', 'Paket Dokumen', 'mp.Id_SOP_Paket', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('prioritas', 'Prioritas', 'mp.Prioritas', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('status_paket', 'Status Paket', 'd.Status_Paket', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('status_item', 'Status Item', "COALESCE(pr.Status, '".SopStatus::ITEM_BELUM."')", 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('wajib', 'Wajib', 'pi.Flag_Wajib', 'teks', [
                'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('lulus', 'Status Lulus', 'pr.Status_Lulus', 'teks', [
                'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('kode_karyawan', 'Karyawan', 'd.Kode_Karyawan', 'teks', [
                'sama_dengan', 'tidak_sama', 'salah_satu', 'mengandung',
            ]),

            /* ── Lapis dokumen ────────────────────────────────────────────
               Ber-ID, bukan bernama, sehingga dokumen yang judulnya diubah
               tidak diam-diam keluar dari template yang sudah tersimpan.
               Konsekuensinya opsiSaringan() WAJIB mengirim pasangan ID+label
               untuk ketiganya — mengirim teks ke kolom int membuat SQL Server
               menggagalkan seluruh kueri dengan galat konversi, bukan
               mengembalikan nol baris. */
            new Saringan('jenis_dokumen', 'Jenis Dokumen', 'dok.Jenis_Dokumen_Id', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('apqc', 'Proses APQC', 'dok.APQC_Id', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('dept_pemilik', 'Departemen Pemilik', 'dok.Departemen_Id', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('tipe_dokumen', 'Tipe Dokumen', 'dok.Tipe', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),

            /* ── Lapis versi aktif ────────────────────────────────────────── */
            new Saringan('status_versi', 'Status Versi', 'vr.Status', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('tahap_versi', 'Tahap Versi', 'th.Kode_Tahap', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('status_masa_berlaku', 'Status Masa Berlaku', SopVersiTanggal::sqlStatusMasaBerlaku('vr'), 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('status_review', 'Status Review', SopVersiTanggal::sqlStatusReview('vr'), 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('baca_ulang', 'Wajib Baca Ulang', 'vr.Wajib_Baca_Ulang', 'teks', [
                'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('tgl_berlaku', 'Tanggal Berlaku', 'vr.Tanggal_Berlaku', 'tanggal', [
                'antara', 'sama_dengan', 'tidak_sama', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
            new Saringan('tgl_kadaluarsa', 'Tanggal Kedaluwarsa', 'vr.Tanggal_Kadaluarsa', 'tanggal', [
                'antara', 'sama_dengan', 'tidak_sama', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
        ];
    }

    public function baseQuery(): Builder
    {
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as mp', 'mp.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->join('N_SOP_Paket_Item as pi', 'pi.Id_SOP_Paket', '=', 'mp.Id_SOP_Paket')
            ->leftJoin('N_SOP_Paket_Section as ps', 'ps.Id_SOP_Section', '=', 'pi.Id_SOP_Section')
            /*
             * Lapis dokumen & versi. SEMUANYA LEFT — lihat catatan kelas:
             * satu INNER di antaranya membuang item non-dokumen dan item tanpa
             * versi aktif dari laporan, dan justru item tanpa versi aktif yang
             * paling dicari orang di sini.
             */
            ->leftJoin('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'pi.Id_SOP_Dokumen')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Master_APQC as ap', 'ap.Id_SOP_APQC', '=', 'dok.APQC_Id')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'dok.Departemen_Id')
            // Versi AKTIF, yakni yang benar-benar disajikan ke karyawan —
            // bukan versi terbaru, dan bukan draft yang sedang digarap wizard.
            ->leftJoin('N_SOP_Item_Versi as vr', 'vr.Id_SOP_Item_Versi', '=', 'pi.Id_Item_Versi_Aktif')
            ->leftJoin('N_SOP_Master_Tahap_Versi as th', 'th.Id_Tahap', '=', 'vr.Tahap_Id')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'div.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'lvl.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            // LEFT, bukan INNER. Lihat catatan kelas.
            ->leftJoin('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on('pr.Id_SOP_Paket_Item', '=', 'pi.Id_SOP_Paket_Item');
            })
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->where('pi.Flag_Aktif', SopStatus::AKTIF)
            /*
             * SOP TERBATAS dihitung penuh sebagai penugasan resmi; SOP PUBLIK
             * hanya bila karyawan tercatat pernah membukanya.
             *
             * SENGAJA SAMA PERSIS dengan SopMonitoringPeopleService::
             * terapkanSaringanPaket(), yang dipakai tujuh tempat di lapisan
             * monitoring beserta export lama. Studio membaca angka yang sama
             * dengan layar; menyimpangkannya hanya di sini membuat dua pintu
             * yang berselisih atas data yang sama.
             *
             * Konsekuensi yang dulu diketahui dan kini SUDAH DIPERBAIKI:
             * penugasan admin atas paket PUBLIK yang belum pernah dibuka tidak
             * muncul di laporan mana pun -- tunggakan yang tak terlihat.
             * Perbaikannya ditempuh persis seperti yang dicatat di sini, yaitu
             * di terapkanSaringanPaket() supaya seluruh modul ikut berubah;
             * salinan ini menyusul agar kedua pintu tetap sepakat.
             *
             * Niat penugasan dibaca dari kolom Sumber, bukan ditebak dari
             * apakah karyawannya sudah membuka: MANUAL/AUTO_RULE adalah
             * kewajiban sejak ditugaskan, MANDIRI hanyalah minat.
             */
            ->where(fn ($w) => SopGerbangPaket::pasang($w, 'mp', 'pj', 'd'));
    }

    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        if ($lingkup->adminPenuh) {
            return $q;
        }

        // Daftar kosong berarti "tak ada anggota tim" -> nol baris, bukan
        // seluruh baris. whereIn dengan array kosong menghasilkan 0=1, yang
        // memang perilaku yang benar di sini.
        return $q->whereIn('d.Kode_Karyawan', $lingkup->anggotaTim);
    }

    /**
     * Pilihan isian saringan.
     *
     * Perhatikan `paket`: saringannya menyaring Id_SOP_Paket, jadi yang
     * dikirim harus ID. Daftar lama (dari endpoint monitoring) berisi
     * Nama_Paket, sehingga memilih satu paket mengirim teks
     * ke kolom int -- SQL Server menggagalkan seluruh query dengan galat
     * konversi, bukan mengembalikan nol baris.
     *
     * Daftar berstatus tetap (status paket, status item, sumber, distribusi)
     * datang dari konstanta SopStatus, bukan dari DISTINCT: nilai yang belum
     * pernah muncul di data tetap harus bisa dipilih.
     */
    public function opsiSaringan(): array
    {
        /*
         * KUNCI CACHE IKUT NAIK SETIAP KALI ISI DAFTAR BERUBAH BENTUK.
         *
         * Nilainya bertahan 1800 detik. Menambah saringan baru tanpa menaikkan
         * kuncinya berarti setengah jam pertama sesudah rilis, saringan-saringan
         * itu muncul di layar tanpa satu pun pilihan — dan yang membukanya akan
         * menyimpulkan datanya memang kosong, bukan bahwa daftarnya belum
         * segar. Naik ke v2 bersama lapis dokumen & versi, lalu ke v3 saat
         * saringan Kategori dicabut: tanpa kenaikan itu, setengah jam pertama
         * layar masih menawarkan saringan yang server sudah tak kenal.
         */
        return Cache::store('database')->remember('sop_studio_opsi_saringan:v3', 1800, function () {
            // ->all(), BUKAN (array) $daftar: casting sebuah Collection ke array
            // menghasilkan properti objeknya ('items', 'escapeWhenCastingToString'),
            // bukan isinya — daftar pilihan jadi dua entri sampah dan bukan
            // divisi mana pun.
            $teks = fn ($daftar) => array_values(array_map(
                fn ($v) => ['value' => (string) $v, 'label' => (string) $v],
                $daftar instanceof Collection ? $daftar->all() : (array) $daftar
            ));

            $div = fn (string $kolom) => DB::table('Karyawan as k')
                ->join('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->whereNotNull("div.{$kolom}")->where("div.{$kolom}", '<>', '')
                ->distinct()->orderBy("div.{$kolom}")->pluck("div.{$kolom}");

            $pasangan = fn ($rows, string $nilai, string $label) => array_values(array_map(
                fn ($r) => ['value' => (int) $r->{$nilai}, 'label' => (string) $r->{$label}],
                $rows->all()
            ));

            return [
                'divisi' => $teks($div('nama_divisi')),
                'departemen' => $teks($div('nama_sub_divisi')),
                'lokasi' => $teks(
                    DB::table('Karyawan')
                        ->whereNotNull('Cabang_Untuk_Gaji')->where('Cabang_Untuk_Gaji', '<>', '')
                        ->distinct()->orderBy('Cabang_Untuk_Gaji')->pluck('Cabang_Untuk_Gaji')
                ),

                'paket' => $pasangan(
                    DB::table('N_SOP_Master_Paket')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->whereNotNull('Nama_Paket')
                        ->orderBy('Nama_Paket')
                        ->get(['Id_SOP_Paket', 'Nama_Paket']),
                    'Id_SOP_Paket',
                    'Nama_Paket'
                ),
                'prioritas' => $teks(app(SopPrioritasService::class)->semua()->pluck('Kode')->all()),

                'status_paket' => $teks([
                    SopStatus::STATUS_BELUM,
                    SopStatus::STATUS_PROGRESS,
                    SopStatus::STATUS_SELESAI,
                ]),
                'status_item' => $teks([
                    SopStatus::ITEM_BELUM,
                    SopStatus::ITEM_DIBUKA,
                    SopStatus::ITEM_SELESAI,
                    SopStatus::ITEM_DIKONFIRMASI,
                    SopStatus::ITEM_DIGANTIKAN,
                ]),

                'distribusi' => [
                    ['value' => 'TERBATAS', 'label' => 'Tertutup (Ditugaskan)'],
                    ['value' => 'PUBLIK', 'label' => 'Publik'],
                ],
                'sumber' => [
                    ['value' => 'MANUAL', 'label' => 'Penugasan Admin'],
                    ['value' => 'MANDIRI', 'label' => 'Pendaftaran Mandiri'],
                    ['value' => 'AUTO_RULE', 'label' => 'Aturan Otomatis'],
                ],

                'wajib' => [
                    ['value' => SopStatus::AKTIF, 'label' => 'Wajib'],
                    ['value' => SopStatus::INAKTIF, 'label' => 'Opsional'],
                ],
                'lulus' => [
                    ['value' => SopStatus::AKTIF, 'label' => 'Lulus'],
                    ['value' => SopStatus::INAKTIF, 'label' => 'Belum Lulus'],
                ],

                /* ── Lapis dokumen ────────────────────────────────────────
                   Ber-ID karena saringannya menyaring kolom int. Seluruh
                   master ditampilkan, bukan hanya yang sudah terpakai: jenis
                   atau proses yang belum punya satu pun dokumen justru
                   temuannya, dan yang tak bisa dipilih tak bisa dibuktikan
                   kosong. */
                'jenis_dokumen' => $pasangan(
                    DB::table('N_SOP_Master_Jenis_Dokumen')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->orderBy('Level_Hirarki')->orderBy('Nama_Jenis')
                        ->get(['Id_SOP_Jenis_Dokumen', 'Nama_Jenis']),
                    'Id_SOP_Jenis_Dokumen',
                    'Nama_Jenis'
                ),
                'apqc' => $pasangan(
                    DB::table('N_SOP_Master_APQC')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->orderBy('Urutan')
                        ->get(['Id_SOP_APQC', 'Nama_Proses']),
                    'Id_SOP_APQC',
                    'Nama_Proses'
                ),
                'dept_pemilik' => $pasangan(
                    DB::table('N_HRIS_Master_Departemen')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->orderBy('Nama_Departemen')
                        ->get(['Id_Departemen', 'Nama_Departemen']),
                    'Id_Departemen',
                    'Nama_Departemen'
                ),
                'tipe_dokumen' => $teks(
                    DB::table('N_SOP_Dokumen')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->whereNotNull('Tipe')->where('Tipe', '<>', '')
                        ->distinct()->orderBy('Tipe')->pluck('Tipe')
                ),

                /* ── Lapis versi ──────────────────────────────────────────
                   Status versi dari konstanta, bukan DISTINCT: OBSOLETE yang
                   belum pernah muncul di data tetap harus bisa dipilih. */
                'status_versi' => $teks([
                    SopStatus::VERSI_DRAFT,
                    SopStatus::VERSI_READY,
                    SopStatus::VERSI_PUBLISHED,
                    SopStatus::VERSI_OBSOLETE,
                ]),
                'tahap_versi' => array_values(array_map(
                    fn ($r) => ['value' => (string) $r->Kode_Tahap, 'label' => (string) $r->Nama_Tahap],
                    DB::table('N_SOP_Master_Tahap_Versi')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->orderBy('Urutan')
                        ->get(['Kode_Tahap', 'Nama_Tahap'])->all()
                )),

                // Dirakit dari konstanta yang SAMA dengan CASE-nya; lihat
                // catatan di SopVersiTanggal soal kenapa dua daftar terpisah
                // menghasilkan nol baris tanpa galat.
                'status_masa_berlaku' => SopVersiTanggal::opsiMasaBerlaku(),
                'status_review' => SopVersiTanggal::opsiReview(),

                'baca_ulang' => [
                    ['value' => SopStatus::AKTIF, 'label' => 'Wajib Baca Ulang'],
                    ['value' => SopStatus::INAKTIF, 'label' => 'Tidak Wajib'],
                ],
            ];
        });
    }
}
