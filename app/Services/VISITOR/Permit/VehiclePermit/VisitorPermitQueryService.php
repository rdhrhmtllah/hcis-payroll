<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Query builder & helper bersama untuk domain Permit Kendaraan, dipakai oleh
 * ApprovalVisitorController (legacy), VerifikatorVisitorController, dan
 * LaporanVisitorController. Sebelumnya method-method ini diduplikasi terpisah
 * di ApprovalVisitorController dan VerifikatorVisitorController dengan sedikit
 * perbedaan field (yang menyebabkan LaporanVisitorController drift dan salah kolom).
 * Konsolidasi ke sini supaya bug fix/perubahan skema cukup di satu tempat.
 */
class VisitorPermitQueryService
{
    public function basePermitQuery(): Builder
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('Karyawan as k', 'h.Kode_Karyawan_Pemohon', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_Divisi_Sub_Divisi')
            // Join lewat ID_Level_Jabatan, bukan tabel 'Jabatan' (tidak ada) / 'jb.ID_Jabatan' (bug pra-eksisting
            // yang ditemukan saat konsolidasi - dulu selalu SQL error "Invalid object name 'Jabatan'").
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as jb', 'k.ID_Level_Jabatan', '=', 'jb.ID_Level_Jabatan')
            ->leftJoin('N_EMI_VISITOR_Master_Kategori_Permit as mk', 'd.Id_Master_Kategori_Permit', '=', 'mk.Id_Kategori_Permit')
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Model as vm', 'd.Id_Master_Model', '=', 'vm.Id_Vehicle_Model')
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Type as vt', 'vm.Id_Master_Tipe', '=', 'vt.Id_Vehicle_Type')
            ->leftJoin('N_EMI_VISITOR_Master_Vehicle_Color as vc', 'd.Id_Master_Warna', '=', 'vc.Id_Master_Color')
            ->leftJoin('N_EMI_VISITOR_Master_Durasi_Permit as md', 'd.Id_Master_Durasi', '=', 'md.Id_Durasi_Permit')
            // Baris akses efektif: sumber sebab pencabutan. Dipasang di sini,
            // bukan di tiap pemanggil, supaya meja verifikasi DAN monitoring
            // sama-sama bisa menampilkan siapa yang mencabut, kapan, dan
            // kenapa — satu kueri, satu kebenaran.
            ->leftJoin('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Flag_Aktif', 'Y')
            ->select(
                'h.Id_Transaksi_Permit as Id',
                'h.Id_Transaksi_Permit',
                'h.No_Transaksi',
                'h.Flag_Approval',
                DB::raw($this->permitStatusSql().' as Status_Permit'),
                'h.Created_At as Tanggal_Pengajuan',
                'h.Kode_Karyawan_Pemohon',
                // Nomor induk karyawan, bukan NIK KTP. Satu-satunya sumbernya
                // Karyawan.No_Induk_Karyawan; proyeksi permit efektif menyalinnya
                // dari sini supaya tidak ada jalur kedua yang bisa berbeda isi.
                'k.No_Induk_Karyawan as NIK',
                // Permit mitra tidak diajukan karyawan mana pun, sehingga JOIN ke
                // Karyawan tidak menghasilkan nama. Nama aplikasinya yang dipakai.
                //
                // Diselesaikan DI SINI, satu tempat, bukan dicabangkan di tiap
                // pemanggil: proyeksi gerbang, meja verifikator, dan monitoring
                // semuanya membaca kueri ini, dan Nama_Pemohon bersifat NOT NULL
                // di proyeksi — pemanggil yang lupa mencabangkan akan gagal
                // menyisipkan, bukan menampilkan nama yang keliru.
                //
                // Sub-kueri diulang, bukan merujuk alias Nama_Mitra di bawah:
                // SQL Server tidak mengizinkan alias SELECT dipakai dalam SELECT
                // yang sama. Untuk permit karyawan sub-kueri ini menghasilkan
                // NULL, jadi COALESCE jatuh ke k.Nama dan perilakunya tidak
                // berubah sedikit pun.
                DB::raw('COALESCE(k.Nama, (SELECT TOP 1 ap2.Nama_Aplikasi FROM N_HRIS_Api_Aplikasi ap2 WHERE ap2.Id_Api_Aplikasi = h.Id_Api_Aplikasi)) as Nama_Pemohon'),
                // 'HP', bukan 'No_HP' - dikonfirmasi via INFORMATION_SCHEMA (bug pra-eksisting lain
                // yang ditemukan saat konsolidasi, dulu selalu throw "Invalid column name 'No_HP'").
                // Siapa yang dibaca admin permit di antrean.
                //
                // Pada permit tamu, yang relevan bagi SHE Admin bukan nama
                // aplikasi mitra ('B2B') maupun kunci internalnya ('API#233'),
                // melainkan ORANG YANG AKAN DATANG ke gerbang. Nama aplikasi
                // tidak pernah muncul di pos jaga, dan kunci internalnya membuat
                // admin mencari-cari karyawan yang tidak pernah ada.
                //
                // Diputuskan DI SINI, satu kali, bukan di tiap komponen: ada
                // enam tempat yang menampilkan identitas pemohon, dan aturan
                // yang hanya terpasang di sebagian akan menyisakan 'API#233' di
                // tempat yang terlupa.
                //
                // Kolom Nama_Pemohon di atas TIDAK diubah: ia dipakai proyeksi
                // gerbang sebagai penanggung jawab izin, dan di sana yang benar
                // memang karyawan/aplikasi pengajunya, bukan tamunya.
                DB::raw(
                    "CASE WHEN h.Kode_Karyawan_Pemohon LIKE '"
                        .VisitorPermitApiService::PREFIX_PEMOHON_APLIKASI
                    ."%' THEN 'Y' ELSE 'T' END as Pemohon_Adalah_Aplikasi"
                ),
                DB::raw("CASE WHEN ISNULL(LTRIM(RTRIM(d.Nama_Tamu)), '') <> '' THEN 'Y' ELSE 'T' END as Permit_Untuk_Tamu"),
                DB::raw("COALESCE(NULLIF(LTRIM(RTRIM(d.Nama_Tamu)), ''), k.Nama, (SELECT TOP 1 ap3.Nama_Aplikasi FROM N_HRIS_Api_Aplikasi ap3 WHERE ap3.Id_Api_Aplikasi = h.Id_Api_Aplikasi), '-') as Nama_Pemohon_Tampil"),
                DB::raw("CASE WHEN ISNULL(LTRIM(RTRIM(d.Nama_Tamu)), '') <> '' THEN ISNULL(NULLIF(LTRIM(RTRIM(d.Instansi_Tamu)), ''), 'Tamu') ELSE ISNULL(h.Kode_Karyawan_Pemohon, '-') END as Label_Kode_Pemohon"),
                // Permit tamu tidak membawa keterangan tambahan di barisnya:
                // instansinya sudah tampil sebagai lencana di sebelah nama, dan
                // penanggung jawab izinnya dibaca di modal review - bukan di
                // daftar, yang dipindai admin untuk mencari SIAPA YANG DATANG.
                //
                // Departemen hanya bermakna bagi permit karyawan; menampilkan
                // departemen PIC pada baris tamu membuatnya terbaca seolah
                // tamunya berasal dari divisi itu.
                DB::raw("CASE WHEN ISNULL(LTRIM(RTRIM(d.Nama_Tamu)), '') <> '' THEN '' ELSE ISNULL(vd.nama_divisi, '-') END as Keterangan_Pemohon_Tampil"),
                'k.HP as No_HP_Pemohon',
                'vd.nama_divisi as Departemen',
                'jb.nama_jabatan as Jabatan',
                'd.Id_Permit_Detail',
                'd.Tahun_Kendaraan',
                // Id mentahnya, bukan hanya namanya: meja verifikator mengisi
                // ulang formulir inspeksinya dari sini, dan dropdown master
                // butuh id — bukan teks yang harus dicocokkan balik.
                'd.Id_Master_Model',
                // Id kategori mentah: dialog koreksi verifikator mengisi ulang dropdown-nya dari sini.
                'd.Id_Master_Kategori_Permit',
                'd.Id_Master_Warna',
                'd.Tanggal_Mulai_Berlaku',
                'd.Tanggal_Mulai_Berlaku as Tanggal_Berlaku',
                'd.Tanggal_Kadaluarsa',
                'd.Keterangan_Pengajuan',
                'd.Keterangan_Pengajuan as Keterangan',
                // Master durasi hanya mengenal hitungan bulan. Izin sehari milik
                // tamu tidak punya barisnya di sana dan menyimpan durasinya
                // sebagai snapshot teks; tanpa COALESCE, proyeksi gerbang
                // menerima Nama_Durasi NULL dan permitnya tampil tanpa masa
                // berlaku di mana-mana.
                DB::raw('COALESCE(md.Nama_Durasi, d.Snapshot_Nama_Durasi) as Nama_Durasi'),
                'mk.Nama_Kategori',
                'mk.Kode_Warna_UI as Kode_Warna',
                'mk.Deskripsi_Akses as Deskripsi_Kategori',
                // Kendaraan tamu tidak berasal dari master model — registrasi
                // tamu hanya punya teks bebas. Tanpa fallback ini satpam
                // kehilangan satu-satunya petunjuk kendaraan apa yang datang.
                DB::raw('COALESCE(vt.Nama_Tipe_Kendaraan, d.Jenis_Kendaraan_Tamu) as Nama_Vehicle_Type'),
                'vm.Nama_Model_Kendaraan as Nama_Model_Manual',
                'vc.Nama_Warna as Warna_Manual',
                DB::raw($this->platNomorSql('d').' as No_Plat_Kendaraan'),
                DB::raw('DATEDIFF(day, GETDATE(), d.Tanggal_Kadaluarsa) as Sisa_Hari'),
                DB::raw("ISNULL(pe.Status_Efektif, 'TANPA_AKSES') as Status_Efektif"),
                'pe.Alasan_Status as Alasan_Pencabutan',
                'pe.Dibatalkan_Oleh_Nama as Dicabut_Oleh',
                'pe.Dibatalkan_At as Dicabut_Pada',
                // Pengajuan dari aplikasi mitra B2B. Sumber_Pengajuan yang
                // membedakannya dari pengajuan karyawan; jendela dan identitas
                // tamu hanya terisi pada pengajuan mitra dan tetap NULL pada
                // permit karyawan yang berdurasi bulan.
                DB::raw("ISNULL(h.Sumber_Pengajuan, 'HCIS') as Sumber_Pengajuan"),
                'h.Referensi_Mitra',
                // Tautan ke kunjungan yang melahirkan permit ini. NULL pada
                // permit kendaraan karyawan maupun permit mitra biasa; ia yang
                // membedakan izin sehari milik tamu dari keduanya.
                'h.No_Transaksi_Tamu',
                DB::raw($this->wajibInspeksiSql('d').' as Wajib_Inspeksi_Kendaraan'),
                'd.Waktu_Mulai_Berlaku',
                'd.Waktu_Berakhir',
                'd.Nama_Tamu',
                'd.Instansi_Tamu',
                // Dokumen yang dicocokkan satpam di pos: 'KTP 3201...', 'No DO
                // DO-Pertama'. Tersimpan sejak pengajuan tetapi tidak pernah
                // ikut terbaca, sehingga verifikator memutuskan tanpa tahu
                // surat apa yang dibawa tamunya.
                'd.No_Identitas_Tamu',
                'd.Jenis_Identitas_Tamu',
                // Kontak tamunya sendiri, bukan kontak PIC. Hanya terisi pada
                // permit yang lahir dari registrasi kunjungan — pengajuan mitra
                // lewat /permit-tamu memang tidak mengirim nomor telepon.
                //
                // Sub-kueri, bukan JOIN kedelapan: relasinya
                // satu-ke-paling-banyak-satu, dan JOIN yang meleset menggandakan
                // baris permit — gejalanya "permit terhitung dua kali" di kuota
                // dan daftar, bukan galat.
                DB::raw('(SELECT TOP 1 td.Nomor_Handphone
                            FROM N_EMI_VISITOR_Transaksi_Tamu_Detail td
                           WHERE td.No_Transaksi = h.No_Transaksi_Tamu) as No_HP_Tamu'),
                // Sub-kueri, bukan JOIN. Kueri ini sudah menggabung tujuh tabel,
                // dan JOIN kedelapan yang meleset akan menggandakan baris permit —
                // gejalanya "permit terhitung dua kali" di kuota dan daftar,
                // bukan galat.
                DB::raw('(SELECT TOP 1 ap.Nama_Aplikasi FROM N_HRIS_Api_Aplikasi ap WHERE ap.Id_Api_Aplikasi = h.Id_Api_Aplikasi) as Nama_Mitra'),
            );
    }

    /**
     * Satu-satunya definisi "permit ini punya kendaraan untuk diinspeksi".
     *
     * Verifikator mengisi data kendaraan dan menjawab checklist fisik pada
     * permit COKLAT, dan keduanya diwajibkan sebelum permitnya disetujui. Tamu
     * pejalan kaki tidak membawa apa pun untuk diperiksa; mewajibkannya berarti
     * izin masuknya tidak akan pernah bisa disetujui.
     *
     * Plat diperiksa LEBIH DULU daripada teks jenis kendaraan: 29 dari 83 baris
     * tamu di development tidak berplat padahal hanya 3 yang menyatakan tidak
     * berkendara, jadi menyandarkan keputusan pada teksnya saja akan
     * membebaskan inspeksi bagi tamu yang sebenarnya datang mengemudi.
     *
     * Ekspresinya hidup di SATU tempat dan dipakai dua pembaca — kolom di
     * basePermitQuery() yang dibaca layar, dan penjaga di jalur persetujuan.
     * Dua definisi yang berbeda tipis membuat tombol Setujui menyala sementara
     * servernya menolak, tanpa satu pun pesan yang menjelaskan sebabnya.
     */
    public function wajibInspeksiSql(string $alias = 'd'): string
    {
        return "CASE
            WHEN LTRIM(RTRIM(CONCAT(ISNULL({$alias}.Plat_Nomor_Prefix, ''), ISNULL({$alias}.Plat_Nomor_Angka, ''), ISNULL({$alias}.Plat_Nomor_Suffix, '')))) <> '' THEN 'Y'
            WHEN {$alias}.Id_Master_Model IS NOT NULL THEN 'Y'
            WHEN ISNULL({$alias}.Jenis_Kendaraan_Tamu, '') <> '' AND UPPER({$alias}.Jenis_Kendaraan_Tamu) NOT LIKE '%TIDAK%' THEN 'Y'
            ELSE 'T'
        END";
    }

    /** Pembaca PHP untuk ekspresi yang sama; jangan menghitungnya ulang sendiri. */
    public function wajibInspeksiKendaraan(string $noTransaksi): bool
    {
        $nilai = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail as d')
            ->where('d.No_Transaksi', $noTransaksi)
            ->value(DB::raw($this->wajibInspeksiSql('d')));

        return $nilai === 'Y';
    }

    /**
     * Satu-satunya definisi "permit ini sudah dicabut".
     *
     * Sengaja EXISTS, bukan kolom dari JOIN: ekspresi status dipakai belasan
     * kueri yang tidak semuanya menggabung tabel efektif, dan menuntut mereka
     * ikut menggabungnya berarti setiap pemanggil baru bisa lupa lalu gagal
     * dengan galat "invalid column". UQ_Permit_Efektif_NoTransaksi membuat
     * pencariannya index seek, bukan pemindaian.
     */
    public function dicabutSql(string $alias = 'h'): string
    {
        return "EXISTS (SELECT 1 FROM N_VISITOR_Permit_Efektif pe_c WHERE pe_c.No_Transaksi = {$alias}.No_Transaksi AND pe_c.Status_Efektif = 'DIBATALKAN')";
    }

    /**
     * Permit milik karyawan yang hari kerja terakhirnya (Tanggal_Resign) sudah
     * lewat. Aksesnya berlaku sampai akhir hari resign dan berhenti esoknya.
     *
     * Hanya permit KARYAWAN: izin tamu (No_Transaksi_Tamu) dan permit mitra
     * (Sumber_Pengajuan 'API') hanya menumpang nama PIC; PIC yang resign tidak
     * boleh mematikan izin orang lain.
     *
     * Tanggal_Resign saja yang menentukan, bukan Karyawan.Aktif — keputusan user
     * 3 Okt 2026; HR tidak selalu menonaktifkan barisnya.
     */
    public function pemohonResignSql(string $h = 'h'): string
    {
        return "({$h}.No_Transaksi_Tamu IS NULL AND ISNULL({$h}.Sumber_Pengajuan, 'HCIS') <> 'API'"
            .' AND EXISTS (SELECT 1 FROM Karyawan k_rs WHERE k_rs.Kode_Karyawan = '.$h.'.Kode_Karyawan_Pemohon'
            .' AND k_rs.Tanggal_Resign IS NOT NULL AND CAST(k_rs.Tanggal_Resign AS date) < '.$this->tanggalHariIniSql().'))';
    }

    public function pemohonSudahResign(string $kodeKaryawan): bool
    {
        return DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->whereNotNull('Tanggal_Resign')
            ->whereRaw('CAST(Tanggal_Resign AS date) < '.$this->tanggalHariIniSql())
            ->exists();
    }

    /**
     * Status permit.
     *
     * 'REVOKED' HARUS jadi cabang pertama: pencabutan tidak menyentuh
     * Flag_Approval sama sekali, sehingga permit yang sudah dicabut tetap
     * memenuhi syarat cabang APPROVED. Kalau urutannya terbalik, akses yang
     * sudah dihentikan tetap terbaca aktif di mana-mana.
     */
    /**
     * "Hari ini" menurut zona waktu BISNIS, bukan menurut jam mesin basis data.
     *
     * ── Kenapa bukan CAST(GETDATE() AS date) ─────────────────────────────────
     *
     * GETDATE() memulangkan jam lokal mesin SQL Server. Aplikasi ini berpikir
     * dalam Asia/Jakarta (config app.timezone), sedangkan mesin basis datanya
     * belum tentu — container uji lokal berjalan UTC. Terukur 10 September 2026
     * pukul 03.48 WIB: PHP menyebut hari ini 2026-09-10 sementara
     * CAST(GETDATE() AS date) masih 2026-09-09.
     *
     * Akibatnya masa berlaku permit dinilai pada batas hari yang salah: permit
     * yang kedaluwarsa "kemarin" menurut aplikasi masih dihitung APPROVED oleh
     * basis data, dan jatah kuota pemohonnya belum dilepas — selama tujuh jam
     * tiap hari, tanpa galat apa pun. Tiga uji kuota permit menangkapnya, tetapi
     * hanya bila suite kebetulan dijalankan antara tengah malam dan pukul tujuh.
     *
     * Menyebut zonanya secara eksplisit membuat jawabannya sama di mesin mana
     * pun. AT TIME ZONE tersedia sejak SQL Server 2016.
     */
    public function tanggalHariIniSql(): string
    {
        return "CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'SE Asia Standard Time' AS date)";
    }

    /** Sekarang menurut zona waktu bisnis; pasangan waktu-penuh dari tanggalHariIniSql(). */
    public function waktuSekarangSql(): string
    {
        return "CAST(SYSDATETIMEOFFSET() AT TIME ZONE 'SE Asia Standard Time' AS datetime2)";
    }

    /**
     * Status permit yang masih PANTAS punya jadwal inspeksi.
     *
     * Booking hidup lebih lama daripada permitnya. Jendela approval yang lewat
     * tanpa diputus membuat permit terbaca EXPIRED di meja verifikator, tetapi
     * booking-nya tetap TERJADWAL: SHE menunggu orang yang sudah tidak mungkin
     * disetujui, slotnya tetap terhitung penuh bagi pemohon lain, dan kedua
     * layar bercerita berbeda tentang pengajuan yang sama.
     *
     * Dipakai bersama oleh agenda (saat membaca) dan penyapu (saat menutup),
     * supaya keduanya tidak bisa berbeda pendapat.
     *
     * @return array<int, string>
     */
    public const STATUS_PANTAS_BERJADWAL = ['PENDING', 'NO_FLOW', 'VALIDITY_REQUIRED', 'APPROVED'];

    /** Potongan SQL: permit pada `$alias` masih pantas punya jadwal inspeksi. */
    public function masihPantasBerjadwalSql(): string
    {
        $isian = "'".implode("','", self::STATUS_PANTAS_BERJADWAL)."'";

        return $this->permitStatusSql()." IN ({$isian})";
    }

    public function permitStatusSql(string $h = 'h', string $d = 'd'): string
    {
        // Cabang EXPIRED punya DUA ketelitian, dan yang menentukan adalah
        // ada-tidaknya jendela jam — bukan sumber permitnya. Permit berjendela
        // (coklat tamu dan mitra B2B) dinilai dari Waktu_Berakhir; permit
        // berdurasi bulan tetap dinilai per hari. Tanpa cabang jam, permit yang
        // jendelanya habis pukul 12:00 terbaca APPROVED di meja verifikator dan
        // monitoring sampai lewat tengah malam.
        $hariIni = $this->tanggalHariIniSql();
        $sekarang = $this->waktuSekarangSql();

        return 'CASE WHEN '.$this->dicabutSql($h)." THEN 'REVOKED'"
            // Ditolak tetap ditolak; selain itu pemohon yang resign mengakhiri permitnya.
            ." WHEN ISNULL({$h}.Flag_Approval, '') <> 'T' AND ".$this->pemohonResignSql($h)." THEN 'EXPIRED'"
            ." WHEN {$h}.Flag_Approval='Y' AND ({$d}.Tanggal_Mulai_Berlaku IS NULL OR {$d}.Tanggal_Kadaluarsa IS NULL) THEN 'VALIDITY_REQUIRED'"
            ." WHEN {$h}.Flag_Approval='Y' AND {$d}.Waktu_Berakhir IS NOT NULL AND {$d}.Waktu_Berakhir < {$sekarang} THEN 'EXPIRED'"
            ." WHEN {$h}.Flag_Approval='Y' AND {$d}.Waktu_Berakhir IS NULL AND {$d}.Tanggal_Kadaluarsa < {$hariIni} THEN 'EXPIRED'"
            ." WHEN {$h}.Flag_Approval='Y' THEN 'APPROVED'"
            ." WHEN {$h}.Flag_Approval='T' THEN 'REJECTED'"
            ." WHEN NOT EXISTS (SELECT 1 FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi={$h}.No_Transaksi) THEN 'NO_FLOW'"
            ." WHEN EXISTS (SELECT 1 FROM HRIS_Approval_Request ar WHERE ar.No_Transaksi={$h}.No_Transaksi AND ar.Flag_Approval IS NULL AND ar.Expired_At IS NOT NULL AND ar.Expired_At<{$sekarang}) THEN 'EXPIRED'"
            ." ELSE 'PENDING' END";
    }

    /**
     * Status permit COKLAT milik sebuah kunjungan, diturunkan dari
     * permitStatusSql() — SATU sumber untuk semua layar tamu dan API mitra
     * (audit 27 Sep 2026, H4/H5). Dulu tiap layar menurunkannya sendiri dari
     * kolom mentah dengan urutan berbeda: izin yang jendelanya habis tampil
     * AKTIF selama penyapu belum jalan, dan permit ditolak tampil KEDALUWARSA.
     *
     * Kosakata keluaran mempertahankan kontrak layar yang ada:
     *  - KANONIK: nilai permitStatusSql apa adanya.
     *  - IZIN   : DICABUT / KEDALUWARSA / AKTIF / DITOLAK / MENUNGGU (monitoring, laporan, jadwal).
     *  - PROSES : DIBATALKAN / EXPIRED / AKTIF / TANPA_AKSES / DITOLAK / MENUNGGU_APPROVAL
     *             (riwayat tamu & meja verifikator).
     * NULL bila kunjungan itu tidak punya permit COKLAT aktif.
     *
     * @param  string  $kolomNoTamu  kolom nomor kunjungan di kueri luar, mis. 'h.No_Transaksi'
     */
    public function statusPermitKunjunganSql(string $kolomNoTamu, string $kosakata = 'KANONIK'): string
    {
        $peta = match ($kosakata) {
            'IZIN' => "CASE st.S WHEN 'REVOKED' THEN 'DICABUT' WHEN 'EXPIRED' THEN 'KEDALUWARSA'"
                ." WHEN 'APPROVED' THEN 'AKTIF' WHEN 'VALIDITY_REQUIRED' THEN 'AKTIF'"
                ." WHEN 'REJECTED' THEN 'DITOLAK' ELSE 'MENUNGGU' END",
            'PROSES' => "CASE st.S WHEN 'REVOKED' THEN 'DIBATALKAN' WHEN 'EXPIRED' THEN 'EXPIRED'"
                ." WHEN 'APPROVED' THEN CASE WHEN st.Ada_Gerbang = 1 THEN 'AKTIF' ELSE 'TANPA_AKSES' END"
                ." WHEN 'VALIDITY_REQUIRED' THEN 'TANPA_AKSES'"
                ." WHEN 'REJECTED' THEN 'DITOLAK' ELSE 'MENUNGGU_APPROVAL' END",
            default => 'st.S',
        };

        return "(SELECT TOP 1 {$peta} FROM (SELECT ".$this->permitStatusSql('pz', 'dz').' AS S,'
            .' CASE WHEN EXISTS (SELECT 1 FROM N_VISITOR_Permit_Efektif pg WHERE pg.No_Transaksi = pz.No_Transaksi) THEN 1 ELSE 0 END AS Ada_Gerbang'
            .' FROM N_EMI_VISITOR_Transaksi_Permit pz'
            .' LEFT JOIN N_EMI_VISITOR_Transaksi_Permit_Detail dz ON dz.No_Transaksi = pz.No_Transaksi'
            ." WHERE pz.No_Transaksi_Tamu = {$kolomNoTamu} AND pz.Flag_Aktif = 'Y') st)";
    }

    /**
     * Terjemahan status kanonik ke kosakata yang dijanjikan ke mitra (OpenAPI):
     * NO_FLOW dan VALIDITY_REQUIRED hanya bermakna di dalam HCIS → PENDING.
     */
    public static function statusUntukMitra(?string $kanonik): string
    {
        return match ($kanonik) {
            'APPROVED' => 'APPROVED',
            'REJECTED' => 'REJECTED',
            'REVOKED' => 'REVOKED',
            'EXPIRED' => 'EXPIRED',
            default => 'PENDING',
        };
    }

    public function platNomorSql(string $alias = 'd'): string
    {
        return "LTRIM(RTRIM(CONCAT(ISNULL({$alias}.Plat_Nomor_Prefix,''),' ',ISNULL({$alias}.Plat_Nomor_Angka,''),' ',ISNULL({$alias}.Plat_Nomor_Suffix,''))))";
    }

    public function baseTamuQuery(): Builder
    {
        $kehadiran = new VisitorKehadiranQueryService;

        return DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'h.No_Transaksi', '=', 'e.No_Transaksi')
            ->leftJoin('Karyawan as k', 'h.Kode_Karyawan_PIC', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'k.ID_Divisi_Sub_Divisi', '=', 'vd.ID_Divisi_Sub_Divisi')
            ->where('h.Flag_Aktif', 'Y')
            ->select(
                'h.Id_Transaksi_Tamu as Id',
                'h.Id_Transaksi_Tamu',
                'h.No_Transaksi',
                'h.Kode_Karyawan_PIC',
                'h.Tanggal_Kunjungan',
                'h.Status_Kunjungan as Status_Pengajuan',
                // Status tampil satu definisi (StatusKunjunganTamu).
                DB::raw($kehadiran->siklusKunjunganSql().' as Status_Kunjungan'),
                'h.Needs_Approval',
                'h.Created_At',
                // Karyawan bila kunjungan ini punya pengaju internal; nama
                // penanggung jawab dari mitra bila tidak (DDL-2026-135).
                // Gerbang butuh NAMA untuk dihubungi, bukan kode - itulah
                // sebabnya Nama_PIC tetap NOT NULL meski kodenya boleh kosong.
                DB::raw('COALESCE(k.Nama, d.Nama_Penanggung_Jawab) as Nama_PIC'),
                'vd.nama_divisi as Divisi_PIC',
                'd.Nama_Lengkap_Tamu as Nama_Tamu',
                'd.Tipe_Tamu',
                'd.Tamu_Kategori',
                'd.B2B_Unit',
                'd.Path_Foto_Kendaraan',
                'd.Instansi_Atau_Asal as Instansi_Asal',
                'd.Nomor_Handphone as No_HP',
                'd.Deksripsi_Keperluan as Keperluan',
                'd.Keterangan_Tujuan_Detail as PIC_Tujuan',
                'd.Keterangan_Tambahan as Keterangan',
                'd.Jumlah_Tamu',
                'd.Daftar_Anggota_Json',
                // Lewat servisnya, bukan COALESCE yang diketik ulang: jamnya dan
                // asal-usulnya di bawah harus memilih sumber yang sama persis.
                DB::raw($kehadiran->masukSql().' as Waktu_Aktual_Masuk'),
                DB::raw($kehadiran->keluarSql().' as Waktu_Aktual_Keluar'),
                // Asal-usul jam kehadiran, supaya monitoring bisa membedakan
                // kartu yang terbaca dari palang yang dibukakan satpam karena
                // kartunya gagal - dan dari jam yang dicatat manual di HCIS.
                DB::raw($kehadiran->sumberMasukSql().' as Sumber_Masuk'),
                DB::raw($kehadiran->sumberKeluarSql().' as Sumber_Keluar'),
                DB::raw($kehadiran->bypassMasukOlehSql().' as Bypass_Masuk_Oleh'),
                DB::raw($kehadiran->bypassKeluarOlehSql().' as Bypass_Keluar_Oleh'),
                'd.No_Identitas',
                'd.Jenis_Identitas',
                'd.Waktu_Estimasi_Kunjungan',
                // Estimasi Masuk / Keluar (DDL-2026-121). Jendela awal permit
                // COKLAT diturunkan dari sini oleh PermitCoklatTamuService.
                'd.Waktu_Mulai_Kunjungan',
                'd.Waktu_Selesai_Kunjungan',
                'k.HP as No_HP_PIC',
                DB::raw($this->platNomorSql('d').' as No_Plat_Kendaraan'),
                'd.Jenis_Kendaraan',
                DB::raw($this->statusPermitKunjunganSql('h.No_Transaksi', 'PROSES').' as Status_Permit_Coklat'),
                DB::raw(
                    "(SELECT TOP 1 p.Id_Transaksi_Permit
                        FROM N_EMI_VISITOR_Transaksi_Permit p
                       WHERE p.No_Transaksi_Tamu = h.No_Transaksi
                         AND p.Flag_Aktif = 'Y') as Id_Transaksi_Permit",
                ),
                DB::raw(
                    "(SELECT TOP 1 p.No_Transaksi
                        FROM N_EMI_VISITOR_Transaksi_Permit p
                       WHERE p.No_Transaksi_Tamu = h.No_Transaksi
                         AND p.Flag_Aktif = 'Y') as No_Transaksi_Permit",
                ),
            );
    }

    public function getDokumens(string $noTransaksi)
    {
        $dokumens = DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')
            ->where('No_Transaksi', $noTransaksi)
            ->select(
                'Id_Permit_Dokumen',
                'Id_Detail_Permit',
                'No_Transaksi',
                'Jenis_Dokumen',
                'Path_File_URL',
                'Path_File_URL as File_Path',
                'Jenis_Dokumen as File_Name',
            )
            ->get();

        return $dokumens->map(function ($doc) {
            $path = $doc->Path_File_URL ?? '';
            $url = null;

            if ($path) {
                // 1. Generasi Signed Temporary URL GCS (berlaku 60 menit)
                try {
                    $url = Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(60));
                } catch (\Throwable $e) {
                    try {
                        // Metadata database adalah sumber keberadaan dokumen.
                        // Hindari exists() remote per dokumen yang menggandakan
                        // latency detail; URL biasa tidak membutuhkan round-trip.
                        $url = Storage::disk('gcs')->url($path);
                    } catch (\Throwable $e2) {
                        Log::warning('VISITOR_PERMIT_DOCUMENT_URL_FAILED', [
                            'no_transaksi' => $doc->No_Transaksi,
                            'document_id' => $doc->Id_Permit_Dokumen,
                            'error' => $e2->getMessage(),
                        ]);
                    }
                }

                // Fallback terakhir hanya untuk metadata legacy local-storage.
                if (! $url) {
                    $cleanPath = ltrim(preg_replace('/^\/?(public\/|storage\/)?/', '', $path), '/');
                    $url = url("storage/{$cleanPath}");
                }
            }

            $doc->File_Url = $url;

            return $doc;
        });
    }

    public function getPermitChecklists(string $noTransaksi)
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist as c')
            ->leftJoin('N_EMI_VISITOR_Master_Approval_Question as q', 'c.Id_Master_Pertanyaan', '=', 'q.Id_Approval_Question')
            ->where('c.No_Transaksi', $noTransaksi)
            ->select(
                'c.Id_Permit_Checklist',
                'c.Id_Master_Pertanyaan',
                'c.Id_Master_Pertanyaan as question_id',
                'c.Snapshot_Pertanyaan',
                'c.Snapshot_Pertanyaan as Pertanyaan',
                'c.Jawaban_Admin',
                'c.Jawaban_Admin as Jawaban',
                'c.Catatan_Admin',
                'c.Catatan_Admin as Catatan',
                'q.Wajib_Diisi',
            )
            ->get();
    }

    public function getApprovals(string $noTransaksi)
    {
        return DB::table('HRIS_Approval_Request as ar')
            ->leftJoin('Karyawan as k', 'ar.Approver_Karyawan_Kode', '=', 'k.Kode_Karyawan')
            ->leftJoin('Karyawan as approved_by', 'ar.Approved_By_Kode', '=', 'approved_by.Kode_Karyawan')
            ->leftJoin('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
            ->leftJoin('N_HRIS_Role_Functional as rf', function ($join) {
                $join->on('rf.Id_Role', '=', 'ar.Group_Reference_Id')->where('rf.Flag_Aktif', 'Y');
            })
            ->where('ar.No_Transaksi', $noTransaksi)
            ->orderBy('ar.Request_Order_Flow')
            ->select(
                'ar.*',
                'ja.Nama_Approval',
                'ja.Source_Type',
                'ja.Source_Reference',
                'approved_by.Nama as Nama_Approved_By',
                'k.Nama as Nama_Approver_Karyawan',
                'rf.Role_Name as Nama_Role_Functional',
                DB::raw('CASE WHEN ar.Flag_Approval IS NULL AND ar.Expired_At IS NOT NULL AND ar.Expired_At < GETDATE() THEN 1 ELSE 0 END as Is_Expired'),
                DB::raw('COALESCE(approved_by.Nama, k.Nama, rf.Role_Name, ja.Nama_Approval) as Nama_Approver'),
                DB::raw('CASE WHEN ar.Request_Order_Flow = (SELECT MIN(ar2.Request_Order_Flow) FROM HRIS_Approval_Request ar2 WHERE ar2.No_Transaksi=ar.No_Transaksi AND ar2.Flag_Approval IS NULL) THEN 1 ELSE 0 END as is_current'),
            )
            ->get();
    }

    public function isApprovalComplete(string $noTransaksi): bool
    {
        return DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->whereNull('Flag_Approval')
            ->doesntExist();
    }

    public function isFinalPendingApproval(string $noTransaksi): bool
    {
        return DB::table('HRIS_Approval_Request')
            ->where('No_Transaksi', $noTransaksi)
            ->whereNull('Flag_Approval')
            ->count() === 1;
    }

    public function validatePermitChecklist(string $noTransaksi, bool $checklistRequired): void
    {
        if (! $checklistRequired) {
            return;
        }

        $missing = DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')
            ->leftJoin('N_EMI_VISITOR_Master_Approval_Question as q', 'N_EMI_VISITOR_Transaksi_Permit_Checklist.Id_Master_Pertanyaan', '=', 'q.Id_Approval_Question')
            ->where('No_Transaksi', $noTransaksi)
            ->where(fn ($q) => $q->whereNull('q.Wajib_Diisi')->orWhere('q.Wajib_Diisi', 'Y'))
            ->where(fn ($q) => $q->whereNull('Jawaban_Admin')->orWhere('Jawaban_Admin', ''))
            ->count();

        if ($missing > 0) {
            throw new \Exception('Checklist pemeriksaan wajib dilengkapi sebelum permit disetujui.');
        }
    }
}
