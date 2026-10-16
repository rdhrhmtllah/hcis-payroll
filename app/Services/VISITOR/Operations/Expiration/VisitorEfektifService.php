<?php

namespace App\Services\VISITOR\Operations\Expiration;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Permit\VehiclePermit\KartuNfcPemohon;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VisitorEfektifService
{
    /** Penanda baris akses sementara menuju pos inspeksi. */
    public const DURASI_AKSES_INSPEKSI = 'Akses Inspeksi';

    /** Toleransi datang lebih awal / keluar lebih lambat di sekitar slot. */
    public const TOLERANSI_AKSES_MENIT = 15;

    /**
     * Kategori yang ditampilkan palang selama akses sementara.
     *
     * Kategori asli (HIJAU/MERAH) SENGAJA tidak dipakai di tahap ini: petugas
     * yang membaca layarnya akan mengira permitnya sudah terbit, padahal
     * verifikator belum menyentuhnya. Nilai ini ditimpa kategori sebenarnya
     * oleh syncPermitEfektif() begitu permitnya disetujui.
     */
    public const KATEGORI_AKSES_INSPEKSI = 'AKSES INSPEKSI - BELUM DISETUJUI';

    /** Baris permit sungguhan: sudah disetujui, berlaku penuh. */
    public const JENIS_PERMIT = 'PERMIT';

    /** Baris akses sementara menuju pos inspeksi; belum diputus siapa pun. */
    public const JENIS_INSPEKSI = 'INSPEKSI';

    public function __construct(
        protected VisitorPermitQueryService $permitQuery,
        protected KartuNfcPemohon $kartuNfc,
    ) {}

    private ?bool $adaKolomNfcInbio = null;

    /**
     * Kolom NFC_Inbio (DDL-2026-153): nomor kartu yang dibaca palang, dari
     * kartu ID Card karyawan pemohon. Hanya untuk izin yang dipegang karyawan
     * itu sendiri; izin tamu/mitra dipegang orang lain, jadi kartu karyawan
     * PIC/pengajunya tidak boleh ikut membuka palang untuknya.
     *
     * Dikembalikan sebagai pasangan kolom => nilai, atau KOSONG bila kolomnya
     * belum ada di basis data ini: penerbitan izin tidak boleh gagal hanya
     * karena DDL pelengkap belum dijalankan.
     *
     * @return array<string, ?string>
     */
    private function kolomNfcInbio(?string $kodeKaryawan, bool $dipegangKaryawan = true): array
    {
        $this->adaKolomNfcInbio ??= Schema::hasColumn('N_VISITOR_Permit_Efektif', 'NFC_Inbio');
        if (! $this->adaKolomNfcInbio) {
            return [];
        }

        return ['NFC_Inbio' => $dipegangKaryawan ? $this->kartuNfc->nfcInbio($kodeKaryawan) : null];
    }

    /**
     * Akses palang SEMENTARA untuk menemui SHE saat inspeksi, diterbitkan
     * ketika pemohon memesan slot — jauh sebelum permitnya diputus.
     *
     * Sengaja memakai JENDELA JAM, bukan status baru. Tabel ini dibaca aplikasi
     * palang di luar repo dan belasan pembaca di dalamnya, dan hampir semuanya
     * berangkat dari "ada baris berarti akses berlaku". Status baru menuntut
     * setiap pembaca itu diperiksa ulang, dan kalau aturan palang ternyata
     * sesederhana "ada barisnya dan tidak dibatalkan", pengajuan yang belum
     * disetujui langsung dapat akses penuh tanpa satu pun galat.
     *
     * Jendela sempit membuatnya aman dengan sendirinya: lewat jamnya, akses
     * mati tanpa ada yang perlu membersihkannya. Pemohon yang tidak datang
     * tidak meninggalkan pintu menganga.
     *
     * Masa berlaku SEBENARNYA ditulis kemudian oleh syncPermitEfektif() saat
     * permitnya disetujui; baris ini ditimpa apa adanya.
     */
    public function terbitkanAksesInspeksi(string $noTransaksi, CarbonInterface $mulaiSlot, CarbonInterface $selesaiSlot): void
    {
        $header = $this->permitQuery->basePermitQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return;
        }

        // Toleransi datang lebih awal dan keluar setelah inspeksi selesai.
        // Tanpa ini, pemohon yang tiba lima menit lebih cepat ditolak palang
        // dan menelepon SHE — persis kerepotan yang hendak dihilangkan.
        $mulai = Carbon::parse($mulaiSlot)->subMinutes(self::TOLERANSI_AKSES_MENIT);
        $berakhir = Carbon::parse($selesaiSlot)->addMinutes(self::TOLERANSI_AKSES_MENIT);

        $now = now();
        $isi = [
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $header->Kode_Karyawan_Pemohon,
            'NIK' => trim((string) ($header->NIK ?? '')) ?: null,
            'Nama_Pemohon' => $header->Nama_Pemohon,
            'No_HP_Pemohon' => $header->No_HP_Pemohon,
            'Departemen' => $header->Departemen,
            'Jabatan' => $header->Jabatan,
            'No_Plat_Kendaraan' => $header->No_Plat_Kendaraan,
            // Kategori ASLI, bukan kalimat status. Palang tetap perlu tahu zona
            // sebenarnya, dan kolom kategori berhenti merangkap dua makna —
            // dashboard yang mengelompok per kategori tidak lagi memunculkan
            // ember hantu berisi kalimat.
            'Nama_Kategori_Permit' => $header->Nama_Kategori,
            'Nama_Vehicle_Type' => $header->Nama_Vehicle_Type,
            'Nama_Model_Kendaraan' => $header->Nama_Model_Manual,
            'Warna_Kendaraan' => $header->Warna_Manual,
            'Tahun_Kendaraan' => $header->Tahun_Kendaraan,
            // Penanda di kolom yang memang dibaca petugas & palang. Tanpa ini,
            // akses sementara tak bisa dibedakan dari permit yang sudah terbit.
            'Nama_Durasi' => self::DURASI_AKSES_INSPEKSI,
            // Penanda sesungguhnya. Sebelum DDL-2026-132 pembedanya hanya teks
            // di kolom di atas, dan kalimat bukan kontrak.
            'Jenis_Akses' => self::JENIS_INSPEKSI,
            'Deskripsi_Akses' => 'Akses sementara menuju pos inspeksi SHE. Berlaku hanya pada jam slot inspeksi.',
            'Tanggal_Mulai_Berlaku' => $mulai->toDateString(),
            'Tanggal_Kadaluarsa' => $berakhir->toDateString(),
            'Waktu_Mulai_Berlaku' => $mulai,
            'Waktu_Berakhir' => $berakhir,
            'Sumber_Permit' => 'KARYAWAN',
            'Status_Efektif' => 'AKTIF',
            'Flag_Batal' => 'T',
            'Flag_Sync' => 'T',
            'Updated_At' => $now,
        ] + $this->kolomNfcInbio($header->Kode_Karyawan_Pemohon);

        $ada = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noTransaksi)->first(['Status_Efektif', 'Jenis_Akses']);

        if ($ada) {
            // Jadwal yang dipindah menggeser jendelanya, bukan menambah baris —
            // tetapi hanya baris INSPEKSI yang belum dicabut. Akses yang sudah
            // DIBATALKAN orang tidak dihidupkan lagi diam-diam, dan izin PERMIT
            // yang sudah terbit tidak diturunkan jadi akses sementara (M4).
            if ($ada->Status_Efektif === 'DIBATALKAN' || $ada->Jenis_Akses !== self::JENIS_INSPEKSI) {
                return;
            }

            DB::table('N_VISITOR_Permit_Efektif')
                ->where('No_Transaksi', $noTransaksi)
                ->update($isi);

            return;
        }

        // Kolom persetujuan WAJIB isi di tabel ini, padahal akses inspeksi
        // memang belum disetujui siapa pun. Ditulis apa adanya sebagai
        // keterangan sistem, bukan nama orang — petugas yang membaca barisnya
        // harus langsung tahu ini bukan permit yang sudah diputus.
        DB::table('N_VISITOR_Permit_Efektif')->insert($isi + [
            'Disetujui_Oleh_Nama' => self::DURASI_AKSES_INSPEKSI.' (belum disetujui)',
            'Disetujui_At' => $now,
            'Riwayat_Approval' => json_encode([
                'jenis' => 'AKSES_INSPEKSI',
                'catatan' => 'Akses sementara menuju pos inspeksi SHE; permit belum diputus.',
            ]),
            'Dibuat_At' => $header->Tanggal_Pengajuan ?? $now,
            'Dibuat_Oleh_Nama' => $header->Nama_Pemohon,
            'Updated_Oleh_Nama' => self::DURASI_AKSES_INSPEKSI,
        ]);
    }

    /**
     * Status baris yang boleh ditimpa sinkronisasi.
     *
     * 'AKTIF' selalu boleh. 'DIBATALKAN' TIDAK PERNAH: pencabutan adalah
     * keputusan orang, dan sinkronisasi susulan tidak boleh menghidupkannya
     * kembali diam-diam.
     *
     * 'EXPIRED' boleh, TETAPI hanya bila jendela yang baru dihitung memang
     * belum berakhir. Dua kejadian nyata bertemu di sini dan keduanya butuh
     * jawaban yang berbeda:
     *
     * - PIC mengubah tanggal kunjungan yang sudah lewat menjadi besok. Tanpa
     *   kebangkitan ini, barisnya tetap EXPIRED dengan jendela lama, gerbang
     *   tidak pernah tahu, dan tamunya ditolak di pos tanpa satu pun galat.
     *   Penyapu kedaluwarsa berjalan tiap lima menit, jadi baris yang harinya
     *   lewat memang hampir pasti sudah EXPIRED saat disunting.
     * - Tamu sudah check-out sehingga permit coklatnya diakhiri lebih awal,
     *   lalu PIC menyunting hal lain pada kunjungan yang sama. Jendelanya
     *   tetap yang lama dan sudah berakhir, jadi barisnya TIDAK dibangkitkan —
     *   akses tamu yang sudah pulang tidak terbuka lagi.
     *
     * Syaratnya membatasi dirinya sendiri: kalau jendela barunya sudah lewat,
     * penyapu kedaluwarsa toh akan mengembalikannya ke EXPIRED lima menit
     * kemudian. Yang dicegah di sini adalah jeda di antaranya.
     *
     * @return array<int, string>
     */
    private function statusBolehDitimpa(mixed $waktuBerakhir, mixed $tanggalAkhir): array
    {
        $belumBerakhir = match (true) {
            filled($waktuBerakhir) => Carbon::parse($waktuBerakhir)->isFuture(),
            filled($tanggalAkhir) => Carbon::parse($tanggalAkhir)->endOfDay()->isFuture(),
            // Tanpa jendela sama sekali, tidak ada dasar untuk membangkitkan.
            default => false,
        };

        return $belumBerakhir ? ['AKTIF', 'EXPIRED'] : ['AKTIF'];
    }

    /**
     * Matikan akses inspeksi sementara (pengajuan ditolak / dibatalkan).
     *
     * Ditandai EXPIRED, BUKAN DIBATALKAN. Cabang 'REVOKED' pada permitStatusSql
     * diperiksa paling awal dan hanya melihat Status_Efektif = 'DIBATALKAN';
     * memakai status itu akan membuat pengajuan yang ditolak terbaca "Dicabut"
     * di seluruh layar — sebab yang salah untuk kejadian yang benar.
     */
    public function akhiriAksesInspeksi(string $noTransaksi): int
    {
        return DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noTransaksi)
            ->where('Jenis_Akses', self::JENIS_INSPEKSI)
            ->where('Status_Efektif', 'AKTIF')
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Waktu_Berakhir' => now(),
                // Bagi palang ini pembatalan: Flag_Batal 'Y' + Flag_Sync 'T'
                // supaya ditarik ulang. Status_Efektif sengaja EXPIRED, bukan
                // DIBATALKAN, karena cabang 'REVOKED' di layar HCIS membaca
                // status itu dan pengajuan yang ditolak akan terbaca "Dicabut".
                'Flag_Batal' => 'Y',
                'Flag_Sync' => 'T',
                'Updated_At' => now(),
            ]);
    }

    /**
     * Precondition: caller must only invoke this once approval is genuinely complete
     * (or from the auto-approve branch). Enforced here too as a defensive no-op guard —
     * belt-and-suspenders on top of the caller's own `isApprovalComplete()` check, not a
     * replacement for it.
     */
    public function syncPermitEfektif(string $noTransaksi): void
    {
        if (! $this->permitQuery->isApprovalComplete($noTransaksi)) {
            return;
        }

        $header = $this->permitQuery->basePermitQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();

        if (! $header || ! $header->Tanggal_Mulai_Berlaku || ! $header->Tanggal_Kadaluarsa) {
            return;
        }

        $existing = DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        $now = now();
        $row = [
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Kode_Karyawan_Pemohon' => $header->Kode_Karyawan_Pemohon,
            // Disalin dari Karyawan.No_Induk_Karyawan lewat basePermitQuery().
            // Ikut di-update, bukan hanya di-insert: baris lama yang terlanjur
            // kosong ikut terisi begitu permitnya di-sync ulang.
            'NIK' => trim((string) ($header->NIK ?? '')) ?: null,
            'Nama_Pemohon' => $header->Nama_Pemohon,
            'No_HP_Pemohon' => $header->No_HP_Pemohon,
            'Departemen' => $header->Departemen,
            'Jabatan' => $header->Jabatan,
            'Keterangan_Pengajuan' => $header->Keterangan_Pengajuan,
            'No_Plat_Kendaraan' => $header->No_Plat_Kendaraan,
            'Nama_Kategori_Permit' => $header->Nama_Kategori,
            'Deskripsi_Akses' => $header->Deskripsi_Kategori,
            'Kode_Warna_UI' => $header->Kode_Warna,
            'Nama_Vehicle_Type' => $header->Nama_Vehicle_Type,
            'Nama_Model_Kendaraan' => $header->Nama_Model_Manual,
            'Warna_Kendaraan' => $header->Warna_Manual,
            'Tahun_Kendaraan' => $header->Tahun_Kendaraan,
            'Nama_Durasi' => $header->Nama_Durasi,
            'Tanggal_Mulai_Berlaku' => $header->Tanggal_Mulai_Berlaku,
            'Tanggal_Kadaluarsa' => $header->Tanggal_Kadaluarsa,
            // Sumber izin di proyeksi mengikuti sumber PENGAJUAN-nya. Ditulis
            // eksplisit di setiap sync, bukan mengandalkan backfill sekali jalan,
            // supaya baris yang di-sync ulang ikut diluruskan.
            // Permit turunan kunjungan tamu dikenali dari tautannya, dan
            // tautan itu diperiksa LEBIH DULU: permit COKLAT yang lahir dari
            // registrasi mitra membawa Sumber_Pengajuan 'API' juga, sehingga
            // urutan terbalik membuatnya terhitung sebagai permit mitra biasa —
            // dan sapuan "tamu sudah keluar" berhenti menemukannya.
            'Sumber_Permit' => $header->No_Transaksi_Tamu
                ? 'TAMU'
                : (strtoupper((string) ($header->Sumber_Pengajuan ?? 'HCIS')) === 'API'
                    ? 'API'
                    : 'KARYAWAN'),
            // Terisi hanya pada permit mitra; permit karyawan berdurasi bulan
            // tidak punya jendela jam dan nilainya tetap NULL.
            'Waktu_Mulai_Berlaku' => $header->Waktu_Mulai_Berlaku ?? null,
            'Waktu_Berakhir' => $header->Waktu_Berakhir ?? null,
            // Identitas pemegang izin. Tanpa ini satpam melihat izin atas nama
            // karyawan PIC dan tidak punya petunjuk siapa yang sebenarnya datang.
            'Nama_Pemegang' => $header->Nama_Tamu ?? null,
            'Instansi_Pemegang' => $header->Instansi_Tamu ?? null,
            'Status_Efektif' => 'AKTIF',
            // Persetujuan mengubah baris akses sementara menjadi permit
            // sungguhan (DDL-2026-132). Tanpa baris ini, baris yang lahir dari
            // pemesanan slot tetap bertanda INSPEKSI selamanya dan lenyap dari
            // dashboard, export, dan daftar pencabutan massal.
            'Jenis_Akses' => self::JENIS_PERMIT,
            // Pasangan wajib Status_Efektif: 'AKTIF' berarti belum dibatalkan.
            // Ditulis eksplisit, bukan diserahkan ke DEFAULT ('T') kolomnya,
            // supaya baris yang di-sync ulang ikut diluruskan dan supaya
            // perilakunya tidak bergantung pada default constraint yang belum
            // tentu ada di setiap environment.
            'Flag_Batal' => 'T',
            // WAJIB ikut di jalur UPDATE, bukan cuma INSERT: aplikasi palang
            // membaca tabel ini dan melewati baris yang sudah 'Y'. Perubahan
            // yang tidak menyalakan ulang penanda ini tidak akan pernah
            // ditarik — gerbang terus memakai data lama tanpa satu pun galat.
            'Flag_Sync' => 'T',
            'Updated_At' => $now,
        ];

        // Ikut di jalur UPDATE juga: kartu yang diganti sejak pengajuan ikut
        // terbawa, dan Flag_Sync 'T' di atas membuat palang menariknya ulang.
        $row += $this->kolomNfcInbio($header->Kode_Karyawan_Pemohon, $row['Sumber_Permit'] === 'KARYAWAN');

        $meta = $this->resolveApprovalMeta($noTransaksi);

        if ($existing) {
            // Metadata approval ikut ditulis ulang: baris yang lahir dari
            // pemesanan slot (INSPEKSI) membawa penyetuju sementara "Akses
            // Inspeksi (belum disetujui)", dan tanpa ini penanda itu terbawa ke
            // permit yang sudah disetujui — di palang maupun di ekspor.
            DB::table('N_VISITOR_Permit_Efektif')
                ->where('No_Transaksi', $noTransaksi)
                ->whereIn('Status_Efektif', $this->statusBolehDitimpa($row['Waktu_Berakhir'] ?? null, $row['Tanggal_Kadaluarsa'] ?? null))
                ->update(array_merge($row, [
                    'Disetujui_Oleh_Nama' => $meta['disetujui_oleh_nama'],
                    'Disetujui_At' => $meta['disetujui_at'],
                    'Riwayat_Approval' => $meta['riwayat_approval'],
                    'Updated_Oleh_Nama' => $meta['disetujui_oleh_nama'],
                ]));
        } else {
            DB::table('N_VISITOR_Permit_Efektif')->insert(array_merge($row, [
                'Disetujui_Oleh_Nama' => $meta['disetujui_oleh_nama'],
                'Disetujui_At' => $meta['disetujui_at'],
                'Riwayat_Approval' => $meta['riwayat_approval'],
                'Dibuat_At' => $header->Tanggal_Pengajuan,
                'Dibuat_Oleh_Nama' => $header->Nama_Pemohon,
                'Updated_Oleh_Nama' => $meta['disetujui_oleh_nama'],
            ]));
        }

        // Permit COKLAT turunan kunjungan: jendelanya juga jendela akses
        // TAMU-nya (lihat jendelaAksesTamu). Disinkronkan dari sini karena
        // setiap pintu yang mengubah jendela permit — persetujuan, persetujuan
        // otomatis, penyuntingan kunjungan — berakhir di metode ini.
        if (! empty($header->No_Transaksi_Tamu)) {
            $this->syncTamuEfektif($header->No_Transaksi_Tamu);
        }
    }

    /**
     * Precondition: caller must only invoke this once approval is genuinely complete
     * (or from the auto-approve branch). Enforced here too as a defensive no-op guard —
     * belt-and-suspenders on top of the caller's own `isApprovalComplete()` check, not a
     * replacement for it.
     */
    public function syncTamuEfektif(string $noTransaksi): void
    {
        if (! $this->permitQuery->isApprovalComplete($noTransaksi)) {
            return;
        }

        $header = $this->permitQuery->baseTamuQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();

        if (! $header) {
            return;
        }

        $existing = DB::table('N_VISITOR_Tamu_Efektif')
            ->where('No_Transaksi', $noTransaksi)
            ->first();

        [$aksesMasuk, $aksesKeluar] = $this->jendelaAksesTamu(
            $noTransaksi,
            $header->Waktu_Mulai_Kunjungan ?? null,
            $header->Waktu_Selesai_Kunjungan ?? null,
        );

        $now = now();
        $row = [
            'Kode_Perusahaan' => '001',
            'No_Transaksi' => $noTransaksi,
            'Nama_Tamu' => $header->Nama_Tamu,
            'Tipe_Tamu' => $header->Tipe_Tamu ?? 'REGULAR',
            'Instansi_Asal' => $header->Instansi_Asal,
            'No_HP' => $header->No_HP,
            'Keperluan' => $header->Keperluan,
            'Keterangan_Tujuan_Detail' => $header->PIC_Tujuan,
            'Keterangan_Tambahan' => $header->Keterangan,
            'No_Identitas' => $header->No_Identitas,
            'Jenis_Identitas' => $header->Jenis_Identitas,
            'Kode_Karyawan_PIC' => $header->Kode_Karyawan_PIC,
            'Nama_PIC' => $header->Nama_PIC,
            'Divisi_PIC' => $header->Divisi_PIC,
            'Jenis_Kendaraan' => $header->Jenis_Kendaraan,
            'No_Plat_Kendaraan' => $header->No_Plat_Kendaraan,
            'Tanggal_Kunjungan' => $header->Tanggal_Kunjungan,
            'Waktu_Estimasi_Kunjungan' => $header->Waktu_Estimasi_Kunjungan,
            // DDL-2026-126: palang hanya membaca tabel ini, jadi rentang
            // estimasi, kategori VISITOR/MITRA, dan unit B2B wajib ikut —
            // tanpa itu palang tidak tahu MITRA berlaku lebih dari sehari.
            // Dinamai 'Estimasi' agar tak tertukar dengan Waktu_Aktual_* palang.
            'Waktu_Estimasi_Masuk' => $aksesMasuk,
            'Waktu_Estimasi_Keluar' => $aksesKeluar,
            'Tamu_Kategori' => $header->Tamu_Kategori ?? null,
            'B2B_Unit' => $header->B2B_Unit ?? null,
            'Path_Foto_Kendaraan' => $header->Path_Foto_Kendaraan ?? null,
            'Jumlah_Tamu' => $header->Jumlah_Tamu,
            // NULL berarti registrasi lama yang daftarnya tidak pernah dikumpulkan.
            'Daftar_Anggota_Json' => $header->Daftar_Anggota_Json ?? null,
            'Status_Efektif' => 'AKTIF',
            // Pasangan wajib Status_Efektif: 'AKTIF' berarti belum dibatalkan.
            // Ditulis eksplisit, bukan diserahkan ke DEFAULT ('T') kolomnya,
            // supaya baris yang di-sync ulang ikut diluruskan dan supaya
            // perilakunya tidak bergantung pada default constraint yang belum
            // tentu ada di setiap environment.
            'Flag_Batal' => 'T',
            // WAJIB ikut di jalur UPDATE, bukan cuma INSERT: aplikasi palang
            // membaca tabel ini dan melewati baris yang sudah 'Y'. Perubahan
            // yang tidak menyalakan ulang penanda ini tidak akan pernah
            // ditarik — gerbang terus memakai data lama tanpa satu pun galat.
            'Flag_Sync' => 'T',
            'Updated_At' => $now,
        ];

        if ($existing) {
            DB::table('N_VISITOR_Tamu_Efektif')
                ->where('No_Transaksi', $noTransaksi)
                ->whereIn('Status_Efektif', $this->statusBolehDitimpa($row['Waktu_Estimasi_Keluar'] ?? null, $row['Tanggal_Kunjungan'] ?? null))
                ->update($row);

            return;
        }

        $meta = $this->resolveApprovalMeta($noTransaksi);

        DB::table('N_VISITOR_Tamu_Efektif')->insert(array_merge($row, [
            'Disetujui_Oleh_Nama' => $meta['disetujui_oleh_nama'],
            'Disetujui_At' => $meta['disetujui_at'],
            'Riwayat_Approval' => $meta['riwayat_approval'],
            'Dibuat_At' => $header->Created_At,
            'Dibuat_Oleh_Nama' => $header->Nama_PIC,
            'Updated_Oleh_Nama' => $meta['disetujui_oleh_nama'],
        ]));
    }

    /**
     * Jendela akses gerbang seorang tamu.
     *
     * Bila kunjungannya punya permit COKLAT yang SUDAH DISETUJUI, jendela
     * Mulai–Berakhir permit itulah yang berlaku — itu yang ditetapkan
     * verifikator saat memeriksa tamunya, dan bisa lebih panjang (tamu proyek)
     * atau digeser dari tanggal kunjungan yang diajukan. Tanpa permit yang
     * disetujui, jendelanya estimasi masuk–keluar dari pengajuan kunjungan.
     *
     * Permintaan user 29 Sep 2026: izin tamu tidak boleh terbaca kedaluwarsa
     * selama jendela dari admin belum habis — baik di gerbang, di status
     * kunjungan, maupun di halaman Pengajuan Tamu.
     *
     * @return array{0: mixed, 1: mixed}
     */
    public function jendelaAksesTamu(string $noTransaksiTamu, mixed $estimasiMasuk, mixed $estimasiKeluar): array
    {
        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit as p')
            ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'p.No_Transaksi')
            ->where('p.No_Transaksi_Tamu', $noTransaksiTamu)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNotNull('d.Waktu_Mulai_Berlaku')
            ->whereNotNull('d.Waktu_Berakhir')
            ->orderByDesc('p.Id_Transaksi_Permit')
            ->first(['d.Waktu_Mulai_Berlaku', 'd.Waktu_Berakhir']);

        return $permit
            ? [$permit->Waktu_Mulai_Berlaku, $permit->Waktu_Berakhir]
            : [$estimasiMasuk, $estimasiKeluar];
    }

    /**
     * Akhiri permit coklat lebih awal.
     *
     * Berbeda dari revoke: ini bukan pembatalan melainkan masa berlaku yang
     * habis lebih cepat, sehingga Flag_Batal tetap 'T' dan kolom Dibatalkan_*
     * tetap NULL — tidak ada yang membatalkan apa pun.
     *
     * Argumennya nomor KUNJUNGAN, bukan nomor permit: pemanggilnya adalah
     * check-out tamu, yang tidak punya alasan tahu nomor permit mana yang
     * terbit untuknya. Penerjemahannya dikerjakan di sini, satu tempat.
     */
    public function akhiriPermitCoklat(string $noTransaksiTamu, string $alasan): int
    {
        $noPermit = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi_Tamu', $noTransaksiTamu)
            ->where('Flag_Aktif', 'Y')
            ->value('No_Transaksi');

        if (! $noPermit) {
            return 0;
        }

        return DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $noPermit)
            ->where('Status_Efektif', 'AKTIF')
            ->where('Sumber_Permit', 'TAMU')
            // HANYA izin yang berlaku satu tanggal — pasangan aturan yang sama
            // di sapuan visitor:expire-efektif. Sejak verifikator boleh
            // menetapkan jendela berapa hari pun, tamu proyek yang pulang di
            // sore hari PERTAMA akan kehilangan izinnya untuk sisa harinya, dan
            // baru mengetahuinya besok pagi di gerbang. Pulang pada penghujung
            // hari adalah perilaku normal izin berhari-hari.
            //
            // Tanda kurungnya WAJIB: whereRaw tidak membungkus apa pun, dan AND
            // mengikat lebih erat daripada OR — tanpanya penyaring nomor
            // transaksi ikut terlepas dan permit orang lain ikut diakhiri.
            ->whereRaw(
                '(Waktu_Berakhir IS NULL
                  OR CAST(Waktu_Mulai_Berlaku AS date)
                     = CAST(DATEADD(second, -1, Waktu_Berakhir) AS date))',
            )
            ->update([
                'Status_Efektif' => 'EXPIRED',
                'Alasan_Status' => $alasan,
                'Updated_At' => now(),
                'Updated_Oleh_Nama' => 'Sistem (Auto-Expire)',
                'Flag_Sync' => 'T',
            ]);
    }

    public function revokePermitEfektif(string $noTransaksi, ?string $alasan, string $olehKode): int
    {
        // Hanya izin PERMIT: akses INSPEKSI sementara milik pengajuan yang belum
        // diputus bukan izin yang bisa dicabut — mencabutnya membuat permit yang
        // kemudian disetujui tak pernah sampai ke palang (audit 27 Sep, M4).
        return $this->revokeEfektifRow('N_VISITOR_Permit_Efektif', $noTransaksi, $alasan, $olehKode, self::JENIS_PERMIT);
    }

    /**
     * @param  array<int, string>  $noTransaksi
     * @return array<int, string> transaksi yang aktif saat batch diproses
     */
    public function revokePermitEfektifMany(array $noTransaksi, ?string $alasan, string $olehKode): array
    {
        $targets = array_values(array_unique(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            $noTransaksi,
        ))));

        if ($targets === []) {
            return [];
        }

        $namaOleh = DB::table('Karyawan')->where('Kode_Karyawan', $olehKode)->value('Nama') ?? $olehKode;
        $now = now();
        $revoked = [];

        // 500 menjaga jumlah parameter jauh di bawah batas 2.100 SQL Server.
        foreach (array_chunk($targets, 500) as $chunk) {
            $active = DB::table('N_VISITOR_Permit_Efektif')
                ->whereIn('No_Transaksi', $chunk)
                ->where('Status_Efektif', 'AKTIF')
                ->where('Jenis_Akses', self::JENIS_PERMIT)
                ->pluck('No_Transaksi')
                ->all();

            if ($active === []) {
                continue;
            }

            DB::table('N_VISITOR_Permit_Efektif')
                ->whereIn('No_Transaksi', $active)
                ->where('Status_Efektif', 'AKTIF')
                ->where('Jenis_Akses', self::JENIS_PERMIT)
                ->update([
                    'Status_Efektif' => 'DIBATALKAN',
                    'Flag_Batal' => 'Y',
                    'Alasan_Status' => $alasan,
                    'Dibatalkan_Oleh_Nama' => $namaOleh,
                    'Dibatalkan_At' => $now,
                    'Updated_At' => $now,
                    'Updated_Oleh_Nama' => $namaOleh,
                    'Flag_Sync' => 'T',
                ]);

            array_push($revoked, ...$active);
        }

        return $revoked;
    }

    public function revokeTamuEfektif(string $noTransaksi, ?string $alasan, string $olehKode): int
    {
        // Pencabutan kini juga dicatat di header (REVOKED). Sebelum DDL-2026-121
        // hanya proyeksi yang berubah, sehingga header tetap "terjadwal" dan
        // pencabutan tak bisa dibedakan dari penolakan di laporan.
        return DB::transaction(function () use ($noTransaksi, $alasan, $olehKode) {
            $affected = $this->revokeEfektifRow('N_VISITOR_Tamu_Efektif', $noTransaksi, $alasan, $olehKode);

            if ($affected > 0) {
                DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                    ->where('No_Transaksi', $noTransaksi)
                    ->where('Status_Kunjungan', StatusKunjunganTamu::SCHEDULED)
                    ->update(['Status_Kunjungan' => StatusKunjunganTamu::REVOKED]);
            }

            return $affected;
        });
    }

    private function revokeEfektifRow(string $table, string $noTransaksi, ?string $alasan, string $olehKode, ?string $jenisAkses = null): int
    {
        $namaOleh = DB::table('Karyawan')->where('Kode_Karyawan', $olehKode)->value('Nama') ?? $olehKode;
        $now = now();

        // Status_Efektif='AKTIF' is folded into this same update's WHERE clause (not a
        // separate preceding exists() check) so the guard-and-update is one atomic SQL
        // statement. $affected === 0 means no-op: either no row for this No_Transaksi,
        // or it's already non-AKTIF — both are safe to ignore. Callers that need to
        // distinguish a real revoke from a no-op should inspect the returned count.
        return DB::table($table)
            ->where('No_Transaksi', $noTransaksi)
            ->where('Status_Efektif', 'AKTIF')
            ->when($jenisAkses !== null, fn ($q) => $q->where('Jenis_Akses', $jenisAkses))
            ->update([
                'Status_Efektif' => 'DIBATALKAN',
                'Flag_Batal' => 'Y',
                'Alasan_Status' => $alasan,
                'Dibatalkan_Oleh_Nama' => $namaOleh,
                'Dibatalkan_At' => $now,
                'Updated_At' => $now,
                'Updated_Oleh_Nama' => $namaOleh,
                'Flag_Sync' => 'T',
            ]);
    }

    /**
     * Resolve final-step approver + full step history from HRIS_Approval_Request.
     * Empty result (no rows) means auto-approve — no human approver involved.
     *
     * @return array{disetujui_oleh_nama: string, disetujui_at: \Illuminate\Support\Carbon, riwayat_approval: string}
     */
    private function resolveApprovalMeta(string $noTransaksi): array
    {
        $rows = DB::table('HRIS_Approval_Request as ar')
            ->leftJoin('Karyawan as k', 'ar.Approved_By_Kode', '=', 'k.Kode_Karyawan')
            ->where('ar.No_Transaksi', $noTransaksi)
            ->orderBy('ar.Request_Order_Flow')
            ->select('ar.Request_Order_Flow', 'ar.Tanggal_Approval', 'k.Nama as Nama_Approver')
            ->get();

        if ($rows->isEmpty()) {
            return [
                'disetujui_oleh_nama' => 'Sistem (Auto-Approve)',
                'disetujui_at' => now(),
                'riwayat_approval' => '[]',
            ];
        }

        $last = $rows->last();

        $riwayat = $rows->map(fn ($r) => [
            'urutan' => (int) $r->Request_Order_Flow,
            'nama' => $r->Nama_Approver ?? 'Tidak diketahui',
            'tanggal' => (string) $r->Tanggal_Approval,
        ])->values()->all();

        return [
            'disetujui_oleh_nama' => $last->Nama_Approver ?? 'Tidak diketahui',
            'disetujui_at' => $last->Tanggal_Approval,
            'riwayat_approval' => json_encode($riwayat),
        ];
    }
}
