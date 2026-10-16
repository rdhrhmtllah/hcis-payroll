<?php

namespace App\Services\LMS\SOP\Export;

use App\Services\LMS\SOP\Support\SopDokumenKelompok;
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
 * Satu baris = SATU DOKUMEN SOP.
 *
 * Modul: LMS · Submodul: SOP · Fitur: Export · Lapisan: Service.
 *
 * KENAPA DATASET KEDUA, BUKAN KOLOM TAMBAHAN DI 'kepatuhan'.
 *
 * Butiran 'kepatuhan' adalah karyawan × paket × item. Di sana sebuah dokumen
 * yang dipakai tiga paket dan ditugaskan ke dua ratus orang muncul ratusan
 * kali, dan — ini yang menentukan — dokumen yang BELUM masuk paket mana pun
 * tidak muncul sama sekali. Bukan nol, melainkan tak ada barisnya.
 *
 * Padahal justru dokumen itulah pekerjaan yang menunggu: sudah terdaftar,
 * sudah bernomor, belum sampai ke siapa pun. Tak ada saringan, tak ada
 * pengelompokan, dan tak ada ukuran apa pun di dataset kepatuhan yang bisa
 * memunculkannya, karena barisnya memang tidak pernah lahir. Satu-satunya cara
 * adalah berangkat dari N_SOP_Dokumen — rumah identitas itu sendiri.
 *
 * Alasan yang sama membuat SopDokumenKesehatanService lahir untuk dashboard;
 * dataset ini adalah pasangannya di jalur ekspor, dan aturan kelompoknya
 * dipinjam dari kelas yang sama (SopDokumenKelompok) supaya keduanya tak
 * pernah berselisih.
 *
 * SATU BARIS PER DOKUMEN DIJAGA KETAT. Setiap join di baseQuery() menuju
 * tabel master ber-primary-key tunggal, dan seluruhnya LEFT. Tidak ada join ke
 * paket, penugasan, atau View_Divisi_Sub_Divisi — view itu berbaris satu per
 * pasangan divisi×sub-divisi, sehingga departemen yang bernaung di dua divisi
 * akan menggandakan dokumennya. Kepemilikan karena itu dibaca dari
 * N_HRIS_Master_Departemen yang berkunci tunggal. Segala hitungan yang butuh
 * paket atau penugasan dikerjakan sebagai sub-kueri berkorelasi, bukan join.
 */
class SopDatasetDokumen implements DatasetLaporan, PenyediaOpsiSaringan
{
    public function kunci(): string
    {
        return 'dokumen';
    }

    public function label(): string
    {
        return 'Register & Tata Kelola Dokumen';
    }

    public function grain(): string
    {
        return 'Satu baris = satu dokumen aktif.';
    }

    public function dimensi(): array
    {
        return [
            new Dimensi('nomor_sop', 'No. Dokumen Resmi', 'dok.Nomor_SOP_Resmi', 'teks', 'identitas'),
            new Dimensi('no_dokumen', 'No. Dokumen', 'dok.No_Dokumen', 'angka', 'identitas'),
            new Dimensi('judul', 'Judul Dokumen', 'dok.Judul', 'teks', 'identitas'),
            new Dimensi('tipe', 'Tipe Dokumen', 'dok.Tipe', 'teks', 'identitas'),
            new Dimensi('deskripsi', 'Deskripsi', 'dok.Deskripsi', 'teks', 'identitas'),

            /*
             * KELOMPOK — kolom terpenting di dataset ini.
             *
             * Wajib / Terbuka / Belum Beredar, ketiganya saling lepas dan
             * menutupi seluruh dokumen, sehingga jumlah per kelompok selalu
             * tepat sama dengan jumlah dokumen. Aturannya tidak ditulis di
             * sini melainkan dipinjam dari SopDokumenKelompok — kelas yang
             * sama dengan yang dipakai dashboard.
             *
             * Nilainya GLOBAL: menyaring departemen membuang barisnya, tak
             * pernah mengubah labelnya. Konsekuensi yang harus diketahui
             * pembaca: bila dashboard sedang tersaring, total per kelompok di
             * sana bisa berbeda dengan berkas ini. Itu keputusan sadar, bukan
             * cacat — kolom yang berubah arti mengikuti saringan tak bisa
             * dipakai membandingkan dua berkas ekspor.
             */
            new Dimensi('kelompok', 'Kelompok Peredaran', SopDokumenKelompok::sqlKelompok(), 'teks', 'peredaran'),
            new Dimensi('paket_perujuk', 'Jumlah Paket Perujuk', 'COALESCE(pkt.Jml, 0)', 'angka', 'peredaran'),
            new Dimensi('versi_menggantung', 'Versi Menggantung', 'COALESCE(gtg.Jml, 0)', 'angka', 'peredaran'),

            new Dimensi('jenis_dokumen', 'Jenis Dokumen', 'jd.Nama_Jenis', 'teks', 'klasifikasi'),
            new Dimensi('kode_jenis', 'Kode Jenis', 'jd.Kode_Jenis_Dokumen', 'teks', 'klasifikasi'),
            new Dimensi('level_hirarki', 'Level Hirarki', 'jd.Level_Hirarki', 'angka', 'klasifikasi'),
            new Dimensi('apqc', 'Proses APQC', 'ap.Nama_Proses', 'teks', 'klasifikasi'),
            new Dimensi('kode_apqc', 'Kode APQC', 'ap.Kode_Proses', 'teks', 'klasifikasi'),

            new Dimensi('dept_pemilik', 'Departemen Pemilik', 'dpt.Nama_Departemen', 'teks', 'kepemilikan'),
            new Dimensi('kode_dept', 'Kode Departemen', 'dpt.Kode_Departemen', 'teks', 'kepemilikan'),
            new Dimensi('entitas_pemilik', 'Entitas Pemilik', 'ent.Nama_Entitas', 'teks', 'kepemilikan'),
            new Dimensi('entitas_lingkup', 'Ruang Lingkup', 'lgk.Nama_Entitas', 'teks', 'kepemilikan'),

            /* ── Versi aktif ──────────────────────────────────────────────
               Lewat dok.Id_Versi_Aktif, dan pointer itu hanya boleh menunjuk
               versi yang sudah sah terbit. Dokumen yang draftnya masih digarap
               di wizard karena itu tampil dengan kolom versi kosong — bukan
               dengan angka draft yang belum berlaku bagi siapa pun. */
            new Dimensi('versi_ke', 'Versi Ke', 'vr.Versi_Ke', 'angka', 'versi'),
            new Dimensi('status_versi', 'Status Versi', 'vr.Status', 'teks', 'versi'),
            new Dimensi('tahap_versi', 'Tahap Versi', 'th.Nama_Tahap', 'teks', 'versi'),
            new Dimensi('langkah_versi', 'Langkah Wizard', 'th.Langkah', 'angka', 'versi'),
            new Dimensi('kelompok_tahap', 'Kelompok Tahap', 'th.Kelompok', 'teks', 'versi'),
            new Dimensi('ringkasan_perubahan', 'Ringkasan Perubahan', 'vr.Ringkasan_Perubahan', 'teks', 'versi'),
            new Dimensi('baca_ulang', 'Wajib Baca Ulang', 'vr.Wajib_Baca_Ulang', 'teks', 'versi', false, 'ya_tidak'),

            new Dimensi('status_masa_berlaku', 'Status Masa Berlaku', SopVersiTanggal::sqlStatusMasaBerlaku('vr'), 'teks', 'masa_berlaku'),
            new Dimensi('status_review', 'Status Review', SopVersiTanggal::sqlStatusReview('vr'), 'teks', 'masa_berlaku'),
            new Dimensi('tgl_berlaku', 'Tgl Berlaku', 'vr.Tanggal_Berlaku', 'tanggal', 'masa_berlaku', false, 'tanggal'),
            new Dimensi('tgl_kadaluarsa', 'Tgl Kedaluwarsa', 'vr.Tanggal_Kadaluarsa', 'tanggal', 'masa_berlaku', false, 'tanggal'),
            new Dimensi('tgl_review_berikutnya', 'Tgl Review Berikutnya', 'vr.Tanggal_Review_Berikutnya', 'tanggal', 'masa_berlaku', false, 'tanggal'),
            new Dimensi('tgl_obsolete', 'Tgl Obsolete', 'vr.Tanggal_Obsolete', 'tanggal', 'masa_berlaku', false, 'tanggal'),

            /* ── Jejak pengesahan & siklus persetujuan ────────────────────
               Empat tanggal yang dicap wizard di sepanjang alur: kapan versi
               diminta, kapan direspon, kapan approval terakhir turun, kapan
               ia disahkan. Inilah yang dipakai mengukur berapa lama sebuah
               revisi SOP sebenarnya berjalan. */
            new Dimensi('nomor_pengesahan', 'Nomor Pengesahan', 'vr.Nomor_Pengesahan', 'teks', 'pengesahan'),
            new Dimensi('pengesah', 'Pengesah', 'vr.Disahkan_Oleh', 'teks', 'pengesahan'),
            new Dimensi('jabatan_pengesah', 'Jabatan Pengesah', 'vr.Jabatan_Pengesah', 'teks', 'pengesahan'),
            new Dimensi('tgl_pengesahan', 'Tgl Pengesahan', 'vr.Tanggal_Pengesahan', 'tanggal', 'pengesahan', false, 'tanggal'),
            new Dimensi('tgl_permintaan', 'Tgl Permintaan', 'vr.Tanggal_Permintaan', 'tanggal', 'pengesahan', false, 'tanggal'),
            new Dimensi('tgl_respon', 'Tgl Respon', 'vr.Tanggal_Respon', 'tanggal', 'pengesahan', false, 'tanggal'),
            new Dimensi('tgl_akhir_approval', 'Tgl Akhir Approval', 'vr.Tanggal_Akhir_Approval', 'tanggal', 'pengesahan', false, 'tanggal'),
            new Dimensi('tgl_publish', 'Tgl Publish', 'vr.Tanggal_Publish', 'tanggal', 'pengesahan', false, 'tanggal_jam'),

            new Dimensi('dibuat_pada', 'Dokumen Dibuat', 'dok.Created_At', 'tanggal', 'jejak', false, 'tanggal_jam'),
            new Dimensi('diubah_pada', 'Dokumen Diubah', 'dok.Updated_At', 'tanggal', 'jejak', false, 'tanggal_jam'),
        ];
    }

    public function ukuran(): array
    {
        $adaVersi = 'CASE WHEN vr.Id_SOP_Item_Versi IS NULL THEN 0 ELSE 1 END';
        $tanpaVersi = 'CASE WHEN vr.Id_SOP_Item_Versi IS NULL THEN 1 ELSE 0 END';

        /*
         * COUNT(*) DI SINI MENGHITUNG DOKUMEN, dan itu benar — butirannya
         * memang satu dokumen satu baris. Bandingkan dengan dataset kepatuhan,
         * yang COUNT(*)-nya menghitung item. Perbedaan inilah alasan kedua
         * dataset tidak dilebur.
         *
         * Kolom keenam menentukan nasib ukuran di baris GRAND TOTAL: hanya
         * yang benar-benar aditif yang boleh dijumlah. Persentase dan
         * COUNT(DISTINCT) tidak.
         */
        return [
            new Ukuran('jml_dokumen', 'Jumlah Dokumen', 'COUNT(*)', 'angka'),
            new Ukuran('jml_ada_versi', 'Punya Versi Aktif', "SUM({$adaVersi})", 'angka'),

            /* Dokumen terdaftar yang belum berdiri di atas versi sah mana pun.
               Bukan sekadar sel kosong: selama pointer versi aktifnya kosong,
               tak ada isi yang bisa disajikan ke karyawan. */
            new Ukuran('jml_tanpa_versi', 'Tanpa Versi Aktif', "SUM({$tanpaVersi})", 'angka'),

            new Ukuran('jml_kadaluarsa', 'Versi Kedaluwarsa', 'SUM('.SopVersiTanggal::sqlKadaluarsa('vr').')', 'angka'),
            new Ukuran('jml_review_jatuh_tempo', 'Review Jatuh Tempo', 'SUM('.SopVersiTanggal::sqlReviewJatuhTempo('vr').')', 'angka'),

            /* Lubang tata kelola: dokumen yang tak terklasifikasi atau tak
               bertuan. Yang tak bertuan tak punya siapa pun yang bertanggung
               jawab merevisinya ketika masa berlakunya habis. */
            new Ukuran('jml_tanpa_jenis', 'Tanpa Jenis', 'SUM(CASE WHEN dok.Jenis_Dokumen_Id IS NULL THEN 1 ELSE 0 END)', 'angka'),
            new Ukuran('jml_tanpa_apqc', 'Tanpa Proses APQC', 'SUM(CASE WHEN dok.APQC_Id IS NULL THEN 1 ELSE 0 END)', 'angka'),
            new Ukuran('jml_tanpa_pemilik', 'Tanpa Departemen Pemilik', 'SUM(CASE WHEN dok.Departemen_Id IS NULL THEN 1 ELSE 0 END)', 'angka'),
            new Ukuran('jml_tanpa_nomor', 'Tanpa Nomor Resmi', "SUM(CASE WHEN dok.Nomor_SOP_Resmi IS NULL OR dok.Nomor_SOP_Resmi = '' THEN 1 ELSE 0 END)", 'angka'),

            /* Keduanya membaca tabel turunan yang sudah ter-agregat per dokumen
               di baseQuery(), bukan sub-kueri di dalam fungsi agregat. Bentuk
               itu bukan selera: sub-kueri berkorelasi di dalam SUM/AVG membuat
               kolom yang dikorelasikan harus ikut GROUP BY, dan begitu laporan
               dikelompokkan per jenis atau per departemen, kueri gagal. */
            new Ukuran('paket_perujuk_rata', 'Paket Perujuk (rata-rata)', 'AVG(CAST(COALESCE(pkt.Jml, 0) AS float))', 'angka', false, Ukuran::TOTAL_ABAIKAN),
            new Ukuran('jml_versi_menggantung', 'Versi Menggantung', 'SUM(COALESCE(gtg.Jml, 0))', 'angka'),

            new Ukuran('persen_beredar', 'Persen Beredar',
                'CAST(SUM('.SopDokumenKelompok::sqlSudahBeredar().') * 100.0 / NULLIF(COUNT(*), 0) AS decimal(5,1))',
                'persen', false, Ukuran::TOTAL_ABAIKAN),
        ];
    }

    public function saringan(): array
    {
        return [
            /* Berbasis KODE, bukan label yang tampil. Label boleh diperhalus
               kelak tanpa mematahkan template yang sudah tersimpan. */
            new Saringan('kelompok', 'Kelompok Peredaran', SopDokumenKelompok::sqlKelompokKode(), 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('jenis_dokumen', 'Jenis Dokumen', 'dok.Jenis_Dokumen_Id', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('apqc', 'Proses APQC', 'dok.APQC_Id', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('dept_pemilik', 'Departemen Pemilik', 'dok.Departemen_Id', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('entitas_pemilik', 'Entitas Pemilik', 'dok.Entitas_Pemilik_Id', 'angka', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
            new Saringan('tipe', 'Tipe Dokumen', 'dok.Tipe', 'teks', [
                'salah_satu', 'sama_dengan', 'tidak_sama',
            ]),
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
                'antara', 'sama_dengan', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
            new Saringan('tgl_kadaluarsa', 'Tanggal Kedaluwarsa', 'vr.Tanggal_Kadaluarsa', 'tanggal', [
                'antara', 'sama_dengan', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
            new Saringan('tgl_review_berikutnya', 'Tanggal Review Berikutnya', 'vr.Tanggal_Review_Berikutnya', 'tanggal', [
                'antara', 'sama_dengan', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
            new Saringan('tgl_pengesahan', 'Tanggal Pengesahan', 'vr.Tanggal_Pengesahan', 'tanggal', [
                'antara', 'sama_dengan', 'lebih_besar_sama', 'lebih_kecil_sama',
            ]),
            new Saringan('nomor_sop', 'No. Dokumen Resmi', 'dok.Nomor_SOP_Resmi', 'teks', [
                'sama_dengan', 'tidak_sama', 'mengandung',
            ]),
            new Saringan('judul', 'Judul Dokumen', 'dok.Judul', 'teks', [
                'mengandung', 'sama_dengan', 'tidak_sama',
            ]),
        ];
    }

    public function baseQuery(): Builder
    {
        return DB::table('N_SOP_Dokumen as dok')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Master_APQC as ap', 'ap.Id_SOP_APQC', '=', 'dok.APQC_Id')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'dok.Departemen_Id')
            ->leftJoin('N_HRIS_Master_Entitas as ent', 'ent.Id_Entitas', '=', 'dok.Entitas_Pemilik_Id')
            ->leftJoin('N_HRIS_Master_Entitas as lgk', 'lgk.Id_Entitas', '=', 'dok.Entitas_Lingkup_Id')
            ->leftJoin('N_SOP_Item_Versi as vr', 'vr.Id_SOP_Item_Versi', '=', 'dok.Id_Versi_Aktif')
            ->leftJoin('N_SOP_Master_Tahap_Versi as th', 'th.Id_Tahap', '=', 'vr.Tahap_Id')

            /*
             * EMPAT TABEL TURUNAN, SEMUANYA BERKUNCI Id_SOP_Dokumen DAN
             * SEMUANYA BERBARIS PALING BANYAK SATU PER DOKUMEN.
             *
             * Itu syarat mutlak di sini: butiran dataset ini satu dokumen satu
             * baris, dan sebuah join yang mengembalikan dua baris untuk satu
             * dokumen akan menggandakan dokumen itu di SELURUH laporan —
             * termasuk di COUNT(*), yang lalu menyebut angka lebih besar dari
             * jumlah dokumen yang sebenarnya ada. DISTINCT pada dua yang
             * pertama dan GROUP BY pada dua berikutnya yang menjaganya.
             *
             * Bentuk join, bukan sub-kueri di dalam SELECT: SQL Server
             * melarang sub-kueri di GROUP BY, sedangkan mesin laporan menaruh
             * ekspresi dimensi apa adanya ke sana. Lihat catatan panjang di
             * SopDokumenKelompok.
             */
            ->leftJoin(
                DB::raw(SopDokumenKelompok::derivedWajib().' AS '.SopDokumenKelompok::ALIAS_WAJIB),
                SopDokumenKelompok::ALIAS_WAJIB.'.Id_SOP_Dokumen', '=', 'dok.Id_SOP_Dokumen'
            )
            ->leftJoin(
                DB::raw(SopDokumenKelompok::derivedBeredar().' AS '.SopDokumenKelompok::ALIAS_BEREDAR),
                SopDokumenKelompok::ALIAS_BEREDAR.'.Id_SOP_Dokumen', '=', 'dok.Id_SOP_Dokumen'
            )
            ->leftJoin(DB::raw(self::derivedPaket().' AS pkt'), 'pkt.Id_SOP_Dokumen', '=', 'dok.Id_SOP_Dokumen')
            ->leftJoin(DB::raw(self::derivedMenggantung().' AS gtg'), 'gtg.Id_SOP_Dokumen', '=', 'dok.Id_SOP_Dokumen')
            /*
             * SATU-SATUNYA ATURAN BISNIS YANG DIPATRI: dokumen nonaktif tidak
             * ikut. Itu sama dengan yang dipegang dashboard, dan tanpanya
             * register memuat dokumen yang sudah dicabut dari peredaran
             * sebagai kalau-kalau ia masih berlaku.
             *
             * Tidak ada gerbang lain. Berbeda dengan dataset kepatuhan yang
             * menahan tiga aturan, dataset ini memang dimaksudkan menampilkan
             * SELURUH register — termasuk yang belum beredar, karena justru
             * itulah yang tak bisa dilihat dari mana pun sebelumnya.
             */
            ->where('dok.Flag_Aktif', SopStatus::AKTIF);
    }

    /**
     * Lingkup non-admin.
     *
     * Seorang leader tidak berhak membaca seluruh register perusahaan hanya
     * karena register itu bukan data pribadi siapa pun. Yang boleh ia lihat
     * adalah dokumen yang menjadi KEWAJIBAN anggota timnya — sama persis
     * dengan batas yang berlaku baginya di dataset kepatuhan, hanya diproyeksi
     * ke butiran dokumen.
     *
     * Daftar anggota kosong berarti nol baris, bukan seluruh baris. Sub-kueri
     * dengan whereIn kosong menghasilkan 0=1, dan itu memang perilaku yang
     * benar di sini.
     */
    public function terapkanLingkup(Builder $q, Lingkup $lingkup): Builder
    {
        if ($lingkup->adminPenuh) {
            return $q;
        }

        return $q->whereExists(function ($sub) use ($lingkup) {
            $sub->select(DB::raw(1))
                ->from('N_SOP_Penjadwalan_Detail as tim_d')
                ->join('N_SOP_Penjadwalan as tim_pj', 'tim_pj.Id_SOP_Penjadwalan', '=', 'tim_d.Id_SOP_Penjadwalan')
                ->join('N_SOP_Paket_Item as tim_i', 'tim_i.Id_SOP_Paket', '=', 'tim_pj.Id_SOP_Paket')
                ->whereColumn('tim_i.Id_SOP_Dokumen', 'dok.Id_SOP_Dokumen')
                ->where('tim_d.Flag_Aktif', SopStatus::AKTIF)
                ->where('tim_d.Flag_Cabut_Akses', SopStatus::INAKTIF)
                ->where('tim_i.Flag_Aktif', SopStatus::AKTIF)
                ->whereIn('tim_d.Kode_Karyawan', $lingkup->anggotaTim);
        });
    }

    /**
     * Berapa paket aktif yang merujuk dokumen ini.
     *
     * COUNT(DISTINCT Id_SOP_Paket), bukan COUNT(*): satu paket boleh memuat
     * dokumen yang sama lebih dari sekali (misalnya di dua section), dan yang
     * ditanyakan adalah berapa PAKET yang merujuknya.
     */
    private static function derivedPaket(): string
    {
        $aktif = SopStatus::AKTIF;

        return "(SELECT pkt_i.Id_SOP_Dokumen, COUNT(DISTINCT pkt_i.Id_SOP_Paket) AS Jml
                 FROM N_SOP_Paket_Item AS pkt_i
                 WHERE pkt_i.Flag_Aktif = '{$aktif}'
                   AND pkt_i.Id_SOP_Dokumen IS NOT NULL
                 GROUP BY pkt_i.Id_SOP_Dokumen)";
    }

    /**
     * Berapa versi dokumen ini yang berhenti sebelum terbit.
     *
     * Definisinya SAMA dengan versiMenggantung() di dashboard: versi aktif
     * berstatus DRAFT yang sudah masuk salah satu langkah wizard. Draft tanpa
     * langkah belum benar-benar berjalan, jadi ia bukan pekerjaan yang
     * tertahan — memasukkannya akan membuat angka ini selalu tampak buruk dan
     * karenanya cepat diabaikan orang.
     */
    private static function derivedMenggantung(): string
    {
        $aktif = SopStatus::AKTIF;
        $draft = SopStatus::VERSI_DRAFT;

        return "(SELECT gtg_v.Id_SOP_Dokumen, COUNT(*) AS Jml
                 FROM N_SOP_Item_Versi AS gtg_v
                 JOIN N_SOP_Master_Tahap_Versi AS gtg_t ON gtg_t.Id_Tahap = gtg_v.Tahap_Id
                 WHERE gtg_v.Flag_Aktif = '{$aktif}'
                   AND gtg_v.Status = '{$draft}'
                   AND gtg_t.Langkah IS NOT NULL
                   AND gtg_v.Id_SOP_Dokumen IS NOT NULL
                 GROUP BY gtg_v.Id_SOP_Dokumen)";
    }

    /**
     * Pilihan isian saringan.
     *
     * Master ditampilkan UTUH, bukan hanya yang sudah terpakai. Jenis dokumen
     * atau proses APQC yang belum punya satu pun dokumen adalah lubang tata
     * kelola, dan lubang tak bisa dibuktikan kosong bila pilihannya tak pernah
     * muncul di layar.
     */
    public function opsiSaringan(): array
    {
        return Cache::store('database')->remember('sop_studio_opsi_dokumen:v1', 1800, function () {
            $pasangan = fn ($rows, string $nilai, string $label) => array_values(array_map(
                fn ($r) => ['value' => (int) $r->{$nilai}, 'label' => (string) $r->{$label}],
                $rows instanceof Collection ? $rows->all() : (array) $rows
            ));

            return [
                'kelompok' => array_values(array_map(
                    fn ($k) => ['value' => $k, 'label' => SopDokumenKelompok::LABEL[$k]],
                    [SopDokumenKelompok::DITUGASKAN, SopDokumenKelompok::BEREDAR, SopDokumenKelompok::BELUM_BEREDAR]
                )),

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
                'entitas_pemilik' => $pasangan(
                    DB::table('N_HRIS_Master_Entitas')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->orderBy('Urutan')->orderBy('Nama_Entitas')
                        ->get(['Id_Entitas', 'Nama_Entitas']),
                    'Id_Entitas',
                    'Nama_Entitas'
                ),

                'tipe' => array_values(array_map(
                    fn ($v) => ['value' => (string) $v, 'label' => (string) $v],
                    DB::table('N_SOP_Dokumen')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->whereNotNull('Tipe')->where('Tipe', '<>', '')
                        ->distinct()->orderBy('Tipe')->pluck('Tipe')->all()
                )),

                'status_versi' => array_values(array_map(
                    fn ($v) => ['value' => $v, 'label' => $v],
                    [SopStatus::VERSI_DRAFT, SopStatus::VERSI_READY, SopStatus::VERSI_PUBLISHED, SopStatus::VERSI_OBSOLETE]
                )),
                'tahap_versi' => array_values(array_map(
                    fn ($r) => ['value' => (string) $r->Kode_Tahap, 'label' => (string) $r->Nama_Tahap],
                    DB::table('N_SOP_Master_Tahap_Versi')
                        ->where('Flag_Aktif', SopStatus::AKTIF)
                        ->orderBy('Urutan')
                        ->get(['Kode_Tahap', 'Nama_Tahap'])->all()
                )),

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
