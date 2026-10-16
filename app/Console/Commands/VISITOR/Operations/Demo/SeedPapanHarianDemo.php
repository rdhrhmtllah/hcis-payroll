<?php

namespace App\Console\Commands\VISITOR\Operations\Demo;

use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Data contoh untuk Papan Harian Visitor.
 *
 * SENGAJA berupa artisan command, BUKAN changeset di database/dml/. Berkas di
 * sana antre untuk dieksekusi ke produksi; data karangan yang ikut terbawa ke
 * sana adalah bencana yang tidak bisa ditarik balik.
 *
 * Seluruh baris ditandai Idempotency_Key berawalan SEEDDEMO- sehingga
 * pembersihannya pasti dan tidak pernah menyentuh data sungguhan. Penandanya
 * tidak tampil di layar, jadi papannya tetap terlihat wajar.
 *
 * Waktu dibangun relatif terhadap hari berjalan, bukan tanggal mati, supaya
 * papannya benar kapan pun perintah ini dijalankan.
 */
class SeedPapanHarianDemo extends Command
{
    protected $signature = 'visitor:seed-papan-harian
                            {--bersihkan : hapus data contoh saja, tanpa membuat yang baru}';

    protected $description = 'Isi data contoh Papan Harian Visitor (khusus pengembangan)';

    private const PENANDA = 'SEEDDEMO-';

    /** Basis data yang boleh disentuh. Selain ini, perintah menolak jalan. */
    private const BASIS_DATA_AMAN = ['Web_HRIS'];

    /**
     * Waktu masuk/keluar yang akan dituliskan SESUDAH baris efektif terbentuk.
     *
     * Kehadiran tidak dicatat HCIS: aplikasi palang di gerbang yang menulis
     * Waktu_Aktual_Masuk/Keluar langsung ke N_VISITOR_Tamu_Efektif, dan
     * syncTamuEfektif sengaja tidak menyentuh kedua kolom itu. Seed ini meniru
     * urutan yang sama — buat transaksi, sinkronkan, baru palang menandai —
     * supaya datanya berperilaku persis seperti di produksi.
     *
     * @var array<string,array{0:?\DateTimeInterface,1:?\DateTimeInterface}>
     */
    private array $tandaPalang = [];

    public function handle(): int
    {
        if (! $this->lingkunganAman()) {
            return self::FAILURE;
        }

        $dihapus = $this->bersihkan();
        $this->line("Data contoh lama dihapus: {$dihapus} transaksi.");

        if ($this->option('bersihkan')) {
            return self::SUCCESS;
        }

        $jumlahTamu = $this->seedTamu();
        $jumlahRiwayat = $this->seedRiwayat();
        $jumlahPermit = $this->seedPermit();

        $this->sinkronkanCounter();
        $this->sinkronkanEfektif();
        $this->terapkanTandaPalang();
        $this->buangCacheDashboard();

        $this->line("Riwayat 14 hari: {$jumlahRiwayat} kunjungan.");

        $this->info("Selesai. {$jumlahTamu} kunjungan tamu dan {$jumlahPermit} permit dibuat.");
        $this->line('Buka /laporanvisitor pada tab "Papan harian".');

        return self::SUCCESS;
    }

    private function lingkunganAman(): bool
    {
        $basis = DB::selectOne('SELECT DB_NAME() AS n')->n;

        if (app()->environment('production')) {
            $this->error('Perintah ini tidak boleh dijalankan pada lingkungan production.');

            return false;
        }

        if (! in_array($basis, self::BASIS_DATA_AMAN, true)) {
            $this->error("Basis data '{$basis}' tidak ada dalam daftar aman; perintah dihentikan.");

            return false;
        }

        $this->line("Target: {$basis} (".app()->environment().')');

        return true;
    }

    private function bersihkan(): int
    {
        $total = 0;

        $nomorTamu = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('Idempotency_Key', 'LIKE', self::PENANDA.'%')
            ->pluck('No_Transaksi');

        // Permit COKLAT turunan kunjungan tidak membawa Idempotency_Key sama
        // sekali — ia dikenali dari tautannya ke kunjungan demo. Tanpa cabang
        // kedua ini, pembersihan meninggalkan izin gerbang yang kunjungannya
        // sudah lenyap, dan sisa itu permanen: baris proyeksi tidak punya
        // penanda apa pun yang bisa mengenalinya sendiri.
        $nomorPermit = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where(function ($q) use ($nomorTamu) {
                $q->where('Idempotency_Key', 'LIKE', self::PENANDA.'%');

                if ($nomorTamu->isNotEmpty()) {
                    $q->orWhereIn('No_Transaksi_Tamu', $nomorTamu);
                }
            })
            ->pluck('No_Transaksi');

        // Permit lebih dulu: ia yang menunjuk kunjungannya, bukan sebaliknya.
        foreach ([
            ['N_EMI_VISITOR_Transaksi_Permit', 'N_EMI_VISITOR_Transaksi_Permit_Detail', $nomorPermit],
            ['N_EMI_VISITOR_Transaksi_Tamu', 'N_EMI_VISITOR_Transaksi_Tamu_Detail', $nomorTamu],
        ] as [$header, $detail, $nomor]) {
            if ($nomor->isEmpty()) {
                continue;
            }

            // Alur persetujuannya ikut dihapus. Permit demo kini benar-benar
            // melewati approval, jadi meninggalkan barisnya berarti antrean
            // verifikator terus menampilkan pengajuan yang sudah tidak ada.
            DB::table('HRIS_Approval_Request')->whereIn('No_Transaksi', $nomor)->delete();

            // Proyeksi ikut dihapus, bukan hanya transaksinya. Baris efektif
            // tidak punya Idempotency_Key sehingga tidak bisa dikenali sendiri:
            // begitu transaksinya lenyap, sisa proyeksinya jadi yatim dan
            // permanen. Sejak permit coklat, sisa itu terlihat di monitoring
            // permit sebagai izin akses yang tidak berpangkal ke mana pun.
            DB::table('N_VISITOR_Permit_Efektif')->whereIn('No_Transaksi', $nomor)->delete();
            DB::table('N_VISITOR_Tamu_Efektif')->whereIn('No_Transaksi', $nomor)->delete();

            // Anak-anak baris detail lebih dulu. Keduanya ber-FK ke
            // Id_Detail_Permit, jadi menghapus detailnya lebih dulu ditolak
            // basis data — dan galatnya menyebut nama constraint, bukan
            // "pembersihan demo gagal". Checklist baru muncul di sini sejak
            // permit COKLAT ikut membawa pertanyaan inspeksinya.
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Checklist')->whereIn('No_Transaksi', $nomor)->delete();
            DB::table('N_EMI_VISITOR_Transaksi_Permit_Dokumen')->whereIn('No_Transaksi', $nomor)->delete();

            DB::table($detail)->whereIn('No_Transaksi', $nomor)->delete();
            $total += DB::table($header)->whereIn('No_Transaksi', $nomor)->delete();
        }

        return $total;
    }

    /**
     * Sehari yang wajar di lobi: sebagian sudah pulang, sebagian masih di dalam,
     * sebagian belum datang, satu tertinggal dari kemarin.
     */
    private function seedTamu(): int
    {
        $hariIni = Carbon::today();
        $urutan = $this->urutanBerikutnya('N_EMI_VISITOR_Transaksi_Tamu', 'VSR');

        $daftar = [
            // Sudah datang dan sudah pulang.
            ['Rizky Ramadhan', 'PT Sinar Mas Agro', 'B7', 'Pengiriman dokumen kontrak', 1, '08:00', '09:25', 'BA', '1204', 'RZ', 'Mobil'],
            ['Dewi Anggraini', 'Bank Mandiri KCP Palembang', 'A2', 'Presentasi produk perbankan', 2, '09:05', '11:40', null, null, null, null],

            // Masih di dalam.
            ['Bambang Sutrisno', 'CV Karya Teknik', 'A1', 'Perbaikan unit genset', 3, '08:15', null, 'BG', '4471', 'TK', 'Pick-up'],
            ['Hendra Wijaya', 'PT Trakindo Utama', 'A4', 'Servis berkala alat berat', 2, '09:40', null, 'BG', '9012', 'HW', 'Mobil'],
            ['Siti Nurhaliza', 'Dinas Lingkungan Hidup', 'A3', 'Inspeksi pengelolaan limbah', 2, '10:05', null, null, null, null, null],

            // Terjadwal hari ini, belum datang.
            ['Ahmad Fauzi', 'PT Pupuk Sriwidjaja', 'A5', 'Koordinasi pengiriman bahan baku', 1, null, null, 'BG', '1188', 'AF', 'Mobil'],
            ['Maya Kusuma', 'Universitas Sriwijaya', 'B6', 'Kunjungan studi mahasiswa', 4, null, null, null, null, null, null],
            ['Joko Prasetyo', 'PT Astra Otoparts', 'A38', 'Audit pemasok tahunan', 2, null, null, 'B', '2290', 'JP', 'Mobil'],
            ['Lina Marlina', 'PT Asuransi Jasindo', 'A2', 'Peninjauan klaim kendaraan', 1, null, null, null, null, null, null],
        ];

        $baris = 0;

        foreach ($daftar as $i => [$nama, $instansi, $pic, $keperluan, $jumlah, $masuk, $keluar, $platPrefix, $platAngka, $platSuffix, $kendaraan]) {
            $this->buatTamu(
                nomor: $this->nomor('VSR', $urutan + $baris),
                nama: $nama,
                instansi: $instansi,
                pic: $pic,
                keperluan: $keperluan,
                jumlah: $jumlah,
                tanggal: $hariIni,
                masuk: $masuk ? $hariIni->copy()->setTimeFromTimeString($masuk) : null,
                keluar: $keluar ? $hariIni->copy()->setTimeFromTimeString($keluar) : null,
                estimasi: $masuk ? null : $this->estimasi($i),
                plat: [$platPrefix, $platAngka, $platSuffix],
                kendaraan: $kendaraan,
                status: StatusKunjunganTamu::SCHEDULED,
            );
            $baris++;
        }

        // Tertinggal dari kemarin: masuk sore, tidak pernah tercatat keluar.
        // Baris seperti inilah yang paling perlu terlihat oleh admin.
        $this->buatTamu(
            nomor: $this->nomor('VSR', $urutan + $baris++),
            nama: 'Agus Salim',
            instansi: 'CV Mitra Konstruksi',
            pic: 'A1',
            keperluan: 'Pekerjaan perbaikan atap gudang',
            jumlah: 2,
            tanggal: $hariIni->copy()->subDay(),
            masuk: $hariIni->copy()->subDay()->setTime(16, 20),
            keluar: null,
            estimasi: null,
            plat: ['BG', '3376', 'MK'],
            kendaraan: 'Pick-up',
            status: StatusKunjunganTamu::SCHEDULED,
        );

        // Belum disetujui: tidak boleh bisa di-check-in.
        $this->buatTamu(
            nomor: $this->nomor('VSR', $urutan + $baris++),
            nama: 'Fitri Handayani',
            instansi: 'PT Sucofindo',
            pic: 'A4',
            keperluan: 'Sertifikasi mutu produk',
            jumlah: 1,
            tanggal: $hariIni,
            masuk: null,
            keluar: null,
            estimasi: '14:00',
            plat: [null, null, null],
            kendaraan: null,
            status: StatusKunjunganTamu::PENDING_APPROVAL,
            needsApproval: 'Y',
        );

        // Dibatalkan: bukti penyaring papan bekerja — tidak boleh muncul.
        $this->buatTamu(
            nomor: $this->nomor('VSR', $urutan + $baris++),
            nama: 'Rudi Hartono',
            instansi: 'PT Wahana Sejahtera',
            pic: 'A5',
            keperluan: 'Penawaran jasa kebersihan',
            jumlah: 1,
            tanggal: $hariIni,
            masuk: null,
            keluar: null,
            estimasi: '10:00',
            plat: [null, null, null],
            kendaraan: null,
            status: StatusKunjunganTamu::REVOKED,
        );

        return $baris;
    }

    /**
     * Riwayat 14 hari ke belakang — rentang baku dashboard.
     *
     * Tanpa ini tren, sebaran jam, dan kesehatan data hanya memuat satu titik
     * dan chart-nya tidak menunjukkan apa pun. Bentuknya sengaja tidak rata:
     * akhir pekan sepi, kedatangan menumpuk pagi dan selepas makan siang, dan
     * sebagian kecil sengaja cacat datanya supaya blok Kesehatan Data punya
     * sesuatu untuk dilaporkan.
     */
    private function seedRiwayat(): int
    {
        $urutan = $this->urutanBerikutnya('N_EMI_VISITOR_Transaksi_Tamu', 'VSR');
        $baris = 0;

        for ($mundur = 13; $mundur >= 1; $mundur--) {
            $tanggal = Carbon::today()->subDays($mundur);
            $akhirPekan = in_array($tanggal->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true);
            $jumlahHari = $akhirPekan ? random_int(0, 2) : random_int(3, 7);

            for ($n = 0; $n < $jumlahHari; $n++) {
                $jam = $this->jamKedatangan();
                $masuk = $tanggal->copy()->setTime($jam, random_int(0, 59));

                // Sebagian kecil tidak pernah tercatat keluar — itu justru yang
                // dilaporkan blok Kesehatan Data sebagai celah pencatatan.
                $lupaKeluar = random_int(1, 100) <= 12;
                $keluar = $lupaKeluar
                    ? null
                    : $masuk->copy()->addMinutes(random_int(35, 240));

                $cacatData = random_int(1, 100) <= 15;

                $this->buatTamu(
                    nomor: $this->nomor('VSR', $urutan + $baris),
                    nama: $this->namaAcak(),
                    instansi: $this->instansiAcak(),
                    pic: $this->picAcak(),
                    keperluan: $this->keperluanAcak(),
                    jumlah: random_int(1, 5),
                    tanggal: $tanggal,
                    masuk: $masuk,
                    keluar: $keluar,
                    estimasi: null,
                    plat: random_int(1, 100) <= 60
                        ? [['BG', 'BA', 'B', 'BS'][random_int(0, 3)], (string) random_int(1000, 9999), $this->hurufAcak()]
                        : [null, null, null],
                    kendaraan: ['Mobil', 'Motor', 'Pick-up'][random_int(0, 2)],
                    status: StatusKunjunganTamu::SCHEDULED,
                    tanpaIdentitas: $cacatData,
                );
                $baris++;
            }
        }

        return $baris;
    }

    /** Menumpuk 08–10 dan 13–14, seperti lalu lintas tamu sungguhan. */
    private function jamKedatangan(): int
    {
        $ember = [8, 8, 9, 9, 9, 10, 10, 11, 13, 13, 14, 14, 15, 16];

        return $ember[random_int(0, count($ember) - 1)];
    }

    private function namaAcak(): string
    {
        $depan = ['Andi', 'Budi', 'Citra', 'Dian', 'Eka', 'Fajar', 'Gita', 'Hadi', 'Indra', 'Julia',
            'Kurnia', 'Lestari', 'Marwan', 'Nadia', 'Oscar', 'Putri', 'Rahmat', 'Sari', 'Taufik', 'Wulan'];
        $belakang = ['Pratama', 'Wijaya', 'Santoso', 'Nugroho', 'Halim', 'Saputra', 'Maulana',
            'Kusuma', 'Setiawan', 'Rahayu', 'Firmansyah', 'Utami'];

        return $depan[random_int(0, count($depan) - 1)].' '.$belakang[random_int(0, count($belakang) - 1)];
    }

    private function instansiAcak(): string
    {
        $daftar = ['PT Semen Baturaja', 'PT Pupuk Sriwidjaja', 'CV Karya Teknik', 'PT Trakindo Utama',
            'Bank Mandiri', 'Dinas Lingkungan Hidup', 'PT Sucofindo', 'Universitas Sriwijaya',
            'PT Astra Otoparts', 'CV Mitra Konstruksi', 'PT Pertamina Patra Niaga', 'PT Telkom Indonesia'];

        return $daftar[random_int(0, count($daftar) - 1)];
    }

    private function keperluanAcak(): string
    {
        $daftar = ['Pengiriman barang', 'Rapat koordinasi', 'Perbaikan peralatan', 'Audit pemasok',
            'Presentasi vendor', 'Inspeksi K3', 'Wawancara kandidat', 'Survei lokasi', 'Penawaran jasa'];

        return $daftar[random_int(0, count($daftar) - 1)];
    }

    private function picAcak(): string
    {
        $daftar = ['A1', 'A2', 'A3', 'A4', 'A5', 'A38', 'B6', 'B7'];

        return $daftar[random_int(0, count($daftar) - 1)];
    }

    private function hurufAcak(): string
    {
        $huruf = 'ABCDEFGHJKLMNPRSTUWXYZ';

        return $huruf[random_int(0, 21)].$huruf[random_int(0, 21)];
    }

    /**
     * Isi proyeksi Efektif lewat service, bukan INSERT langsung.
     *
     * Dua blok permit di dashboard membaca N_VISITOR_Permit_Efektif, bukan tabel
     * transaksi; tanpa langkah ini chart-nya tetap kosong berapa pun permit yang
     * dibuat. Menulis proyeksinya sendiri berarti menduplikasi aturan sinkron
     * yang bisa berubah — biarkan service yang menentukannya.
     */
    private function sinkronkanEfektif(): void
    {
        $efektif = app(VisitorEfektifService::class);

        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Idempotency_Key', 'LIKE', self::PENANDA.'%')
            ->pluck('No_Transaksi');

        $tamu = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('Idempotency_Key', 'LIKE', self::PENANDA.'%')
            ->pluck('No_Transaksi');

        foreach ($permit as $no) {
            $efektif->syncPermitEfektif($no);
        }
        $permitTamu = app(PermitCoklatTamuService::class);

        foreach ($tamu as $no) {
            $efektif->syncTamuEfektif($no);

            // Setiap tamu efektif punya permit coklatnya. Tanpa baris ini papan
            // demo memperagakan keadaan yang tidak mungkin terjadi di produksi:
            // tamu terjadwal tanpa izin akses.
            //
            // Permitnya diajukan lewat service yang sama dengan jalur nyata,
            // lalu disetujui langsung: papan demo memperagakan HARI BERJALAN,
            // dan permit yang menggantung di antrean verifikator tidak akan
            // pernah muncul di sana.
            $noPermit = $permitTamu->sinkron($no);

            if ($noPermit) {
                $pic = (string) DB::table('N_EMI_VISITOR_Transaksi_Tamu')
                    ->where('No_Transaksi', $no)
                    ->value('Kode_Karyawan_PIC');

                $permitTamu->setujuiOtomatis($noPermit, $pic);
            }
        }

        $this->line('Proyeksi efektif disinkronkan: '.$permit->count().' permit, '.$tamu->count().' tamu.');
    }

    /**
     * Meniru aplikasi palang di gerbang: menulis waktu aktual ke baris efektif
     * yang sudah terbentuk. Dijalankan SESUDAH sinkronisasi, karena barisnya
     * baru ada setelah itu.
     */
    private function terapkanTandaPalang(): void
    {
        $ditandai = 0;

        foreach ($this->tandaPalang as $nomor => [$masuk, $keluar]) {
            $ditandai += DB::table('N_VISITOR_Tamu_Efektif')
                ->where('No_Transaksi', $nomor)
                ->update([
                    'Waktu_Aktual_Masuk' => $masuk,
                    'Waktu_Aktual_Keluar' => $keluar,
                ]);
        }

        $this->line("Palang menandai {$ditandai} kunjungan (masuk/keluar).");

        if ($ditandai < count($this->tandaPalang)) {
            $this->warn(
                'Sebagian tanda tidak terpasang: baris efektifnya belum ada. '.
                'Periksa syncTamuEfektif — tanpa baris itu, kehadiran tidak akan terlihat.'
            );
        }
    }

    /**
     * Analitik dashboard di-cache 10 menit dan keadaan sekarang 1 menit. Tanpa
     * dibuang, layar menampilkan chart kosong yang basi dan seed ini terlihat
     * seolah tidak bekerja.
     */
    private function buangCacheDashboard(): void
    {
        Cache::forget('visitor.dashboard.sekarang');
        Cache::forget(
            'visitor.dashboard.analitik.'
            .Carbon::today()->subDays(13)->toDateString().'.'
            .Carbon::today()->toDateString(),
        );

        $this->line('Cache dashboard dibuang.');
    }

    private function estimasi(int $i): string
    {
        return ['13:00', '13:30', '15:00', '16:00'][$i % 4];
    }

    private function buatTamu(
        string $nomor,
        string $nama,
        string $instansi,
        string $pic,
        string $keperluan,
        int $jumlah,
        Carbon $tanggal,
        ?Carbon $masuk,
        ?Carbon $keluar,
        ?string $estimasi,
        array $plat,
        ?string $kendaraan,
        string $status,
        string $needsApproval = 'T',
        bool $tanpaIdentitas = false,
    ): void {
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => $pic,
            'Status_Kunjungan' => $status,
            'Tanggal_Kunjungan' => $tanggal->toDateString(),
            'Needs_Approval' => $needsApproval,
            'Flag_Approval' => $needsApproval === 'Y' ? null : 'Y',
            'Flag_Aktif' => 'Y',
            'Created_At' => $tanggal->copy()->subDay()->setTime(14, 10),
            'Created_By' => $pic,
            'Idempotency_Key' => self::PENANDA.strtoupper(str_replace('-', '', $nomor)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Tamu' => $id,
            'Nama_Lengkap_Tamu' => $nama,
            // Seeder mendemonstrasikan alur tamu dengan Permit COKLAT, jadi
            // fixture-nya harus bertipe B2B; REGULAR sengaja tidak berpermit.
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => $instansi,
            'Nomor_Handphone' => $tanpaIdentitas ? null : '08'.random_int(1111111111, 9999999999),
            'Deksripsi_Keperluan' => $keperluan,
            'Jumlah_Tamu' => $jumlah,
            // Kolom detail sengaja dibiarkan kosong: itu jalur catatan manual,
            // bukan jalur palang. Waktunya ditulis ke baris efektif nanti.
            'Waktu_Aktual_Masuk' => null,
            'Waktu_Aktual_Keluar' => null,
            'Keterangan_Tujuan_Detail' => 'Ruang '.['Rapat A', 'Rapat B', 'Lobi Utama', 'Workshop'][random_int(0, 3)],
            'Jenis_Kendaraan' => $kendaraan,
            'Plat_Nomor_Prefix' => $plat[0],
            'Plat_Nomor_Angka' => $plat[1],
            'Plat_Nomor_Suffix' => $plat[2],
            'Waktu_Estimasi_Kunjungan' => $estimasi,
            // Arsip kolom waktu lama dibiarkan kosong. Permit COKLAT kini
            // dipaku satu hari dari Tanggal_Kunjungan.
            'Waktu_Mulai_Kunjungan' => null,
            'Waktu_Selesai_Kunjungan' => null,
            'Jenis_Identitas' => $tanpaIdentitas ? null : 'KTP',
            'No_Identitas' => $tanpaIdentitas ? null : (string) random_int(1600000000000000, 1699999999999999),
        ]);

        if ($masuk !== null || $keluar !== null) {
            $this->tandaPalang[$nomor] = [$masuk, $keluar];
        }
    }

    /**
     * Permit dibuat mengelilingi hari ini supaya tiap kelompok di papan terisi:
     * satu berakhir hari ini, dua mendekati, sisanya masih panjang.
     */
    private function seedPermit(): int
    {
        $hariIni = Carbon::today();
        $urutan = $this->urutanBerikutnya('N_EMI_VISITOR_Transaksi_Permit', 'PMT');

        $daftar = [
            ['A1', 'BG', '1102', 'AA', 3, 1, 1, $hariIni->copy()->subMonths(6), $hariIni->copy()],
            ['A2', 'BA', '2233', 'DW', 1, 2, 1, $hariIni->copy()->subMonths(3), $hariIni->copy()->addDays(3)],
            ['A3', 'BG', '4455', 'HD', 2, 4, 1, $hariIni->copy()->subMonths(2), $hariIni->copy()->addDays(6)],
            ['A4', 'B', '7788', 'AJ', 3, 3, 2, $hariIni->copy()->subMonth(), $hariIni->copy()->addMonths(2)],
            ['A5', 'BG', '9911', 'AM', 4, 1, 2, $hariIni->copy()->subDays(10), $hariIni->copy()->addMonths(5)],
            ['B6', 'BS', '3344', 'AN', 2, 6, 1, $hariIni->copy()->subDays(4), $hariIni->copy()->addMonths(1)],
            // Disetujui tetapi tanggalnya tidak pernah diisi: sah di atas kertas,
            // tetapi tidak pernah benar-benar berlaku.
            ['B7', 'BG', '5566', 'AG', 1, 5, 1, null, null],
        ];

        $baris = 0;

        foreach ($daftar as [$pemohon, $prefix, $angka, $suffix, $model, $warna, $kategori, $mulai, $kadaluarsa]) {
            $this->buatPermit($this->nomor('PMT', $urutan + $baris), $pemohon, [$prefix, $angka, $suffix], $model, $warna, $kategori, $mulai, $kadaluarsa, 'Y');
            $baris++;
        }

        // Masih menunggu approval: tidak boleh muncul di papan.
        $this->buatPermit($this->nomor('PMT', $urutan + $baris), 'A38', ['BH', '6677', 'AY'], 2, 2, 1, null, null, null);
        $baris++;

        return $baris;
    }

    private function buatPermit(
        string $nomor,
        string $pemohon,
        array $plat,
        int $model,
        int $warna,
        int $kategori,
        ?Carbon $mulai,
        ?Carbon $kadaluarsa,
        ?string $flagApproval,
    ): void {
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => $pemohon,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => $flagApproval,
            'Approved_At' => $flagApproval === 'Y' ? ($mulai?->copy() ?? Carbon::today()->subDays(3)) : null,
            'Approved_By' => $flagApproval === 'Y' ? 'A1' : null,
            'Created_At' => ($mulai?->copy()->subDays(2)) ?? Carbon::today()->subDays(5),
            'Created_By' => $pemohon,
            'Idempotency_Key' => self::PENANDA.strtoupper(str_replace('-', '', $nomor)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => $plat[0],
            'Plat_Nomor_Angka' => $plat[1],
            'Plat_Nomor_Suffix' => $plat[2],
            'Id_Master_Model' => $model,
            'Id_Master_Warna' => $warna,
            'Tahun_Kendaraan' => random_int(2016, 2025),
            'Id_Master_Kategori_Permit' => $kategori,
            'Snapshot_Kategori_Permit' => $kategori === 1 ? 'HIJAU' : 'MERAH',
            'Tanggal_Mulai_Berlaku' => $mulai?->toDateString(),
            'Tanggal_Kadaluarsa' => $kadaluarsa?->toDateString(),
            'Keterangan_Pengajuan' => 'Kendaraan operasional harian.',
        ]);
    }

    /**
     * Dorong HRIS_Counter agar tidak tertinggal di belakang nomor yang sudah
     * dipakai.
     *
     * No_Transaksi lahir dari counter atomik yang HANYA menyemai diri sekali
     * dari MAX. Menyisipkan baris langsung ke tabel transaksi melewatinya,
     * sehingga pengajuan berikutnya menabrak nomor yang sudah ada dan SELURUH
     * pengajuan tamu maupun permit gagal — dengan galat yang tidak menyebut
     * sebabnya sama sekali.
     *
     * Counter hanya pernah DINAIKKAN. Menurunkannya berarti mendaur ulang nomor
     * yang pernah terpakai; melompati nomor tidak berbahaya, mengulanginya
     * berbahaya.
     */
    private function sinkronkanCounter(): void
    {
        foreach ([
            ['VSR', 'N_EMI_VISITOR_Transaksi_Tamu'],
            ['PMT', 'N_EMI_VISITOR_Transaksi_Permit'],
        ] as [$seri, $tabel]) {
            $prefix = $seri.Carbon::now()->format('my').'-';

            $maksimum = (int) substr(
                (string) DB::table($tabel)->where('No_Transaksi', 'LIKE', $prefix.'%')->max('No_Transaksi'),
                -4,
            );

            if ($maksimum === 0) {
                continue;
            }

            $baris = DB::table('HRIS_Counter')->where('prefix', $prefix)->first();

            if ($baris === null) {
                DB::table('HRIS_Counter')->insert(['prefix' => $prefix, 'next_val' => $maksimum]);
                $this->line("Counter {$prefix} dibuat pada {$maksimum}.");

                continue;
            }

            if ((int) $baris->next_val < $maksimum) {
                DB::table('HRIS_Counter')->where('prefix', $prefix)->update(['next_val' => $maksimum]);
                $this->line("Counter {$prefix} dinaikkan dari {$baris->next_val} ke {$maksimum}.");
            }
        }
    }

    private function urutanBerikutnya(string $tabel, string $prefix): int
    {
        $basis = $prefix.Carbon::now()->format('my').'-';
        $terakhir = DB::table($tabel)
            ->where('No_Transaksi', 'LIKE', $basis.'%')
            ->orderByDesc('No_Transaksi')
            ->value('No_Transaksi');

        return $terakhir ? ((int) substr($terakhir, -4)) + 1 : 1;
    }

    private function nomor(string $prefix, int $urutan): string
    {
        return $prefix.Carbon::now()->format('my').'-'.str_pad((string) $urutan, 4, '0', STR_PAD_LEFT);
    }
}
