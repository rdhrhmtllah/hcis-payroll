<?php

namespace App\Services\VISITOR\Guest\Visit;

use App\Services\SYSTEM\Application\Transactions\TransactionNumberService;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitCoklatTamuService;
use App\Services\VISITOR\Permit\VehiclePermit\PlatNomorKendaraan;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya penulis registrasi tamu.
 *
 * Ada DUA pintu yang membuat baris tamu: layar HCIS (GuestRegistrationController)
 * dan API mitra B2B (TamuB2BApiController). Selama keduanya menyusun insert-nya
 * sendiri, setiap aturan baru hanya akan terpasang di salah satunya — pola
 * kegagalan yang sudah berulang di repo ini. Karena itu bentuk baris, pembuatan
 * alur approval, dan penerbitan proyeksi hidup di sini saja.
 *
 * Yang TIDAK ada di sini dan memang milik pemanggil: validasi payload,
 * pemeriksaan master data, notifikasi, dan pemetaan galat ke bentuk respons.
 */
class RegistrasiTamuService
{
    public const T_HEADER = 'N_EMI_VISITOR_Transaksi_Tamu';

    public const T_DETAIL = 'N_EMI_VISITOR_Transaksi_Tamu_Detail';

    /**
     * Lebar TERSEMPIT sepanjang rantai penyimpanan, per field masukan.
     *
     * Kolom transaksi bukan satu-satunya yang harus dimuati: baris yang sama
     * diproyeksikan ke N_VISITOR_Tamu_Efektif saat approval, dan beberapa
     * kolom di sana lebih sempit daripada asalnya —  'keterangan' disimpan di
     * kolom 'text' tak terbatas lalu diproyeksikan ke varchar(500),
     * 'no_identitas' 100 karakter lalu 50.
     *
     * Akibat tanpa penjaga ini bukan sekadar galat, melainkan jalan buntu:
     * registrasinya tersimpan mulus, lalu APPROVAL-nya yang meledak dengan
     * "String or binary data would be truncated". Pengajuannya sudah berada di
     * sistem dan tidak akan pernah bisa disetujui siapa pun, sementara
     * verifikator hanya melihat galat umum yang tak menyebut field mana pun.
     *
     * @var array<string, int>
     */
    private const LEBAR_TERSEMPIT = [
        'nama_tamu' => 150,
        'instansi_asal' => 150,
        'no_hp' => 20,
        'keperluan' => 250,
        'pic_tujuan' => 100,
        'nama_penanggung_jawab' => 150,
        'keterangan' => 500,
        'no_identitas' => 50,
        'jenis_identitas' => 20,
        'jenis_kendaraan' => 50,
    ];

    public function __construct(
        protected VisitorApprovalPolicyService $visitorApprovalPolicy,
        protected VisitorEfektifService $visitorEfektif,
        protected DaftarAnggotaRombongan $daftarAnggota,
        protected PermitCoklatTamuService $permitCoklatTamu,
        protected PlatNomorKendaraan $platNomor,
    ) {}

    /**
     * Nomor transaksi tamu berikutnya.
     *
     * Counter atomik, diambil SEBELUM transaksi bisnis dibuka supaya lock baris
     * counter dilepas segera dan tidak ikut tertahan selama insert dan
     * pembuatan approval flow.
     */
    public function nomorTransaksiBaru(): string
    {
        $base = 'VSR'.Carbon::now()->format('my').'-';

        return app(TransactionNumberService::class)->generate(
            $base,
            function () use ($base) {
                $last = DB::table(self::T_HEADER)
                    ->where('No_Transaksi', 'LIKE', $base.'%')
                    ->orderByDesc('No_Transaksi')
                    ->value('No_Transaksi');

                return $last ? (int) substr($last, -4) : 0;
            },
            4,
        );
    }

    /**
     * Registrasi yang sudah pernah diterima dengan kunci idempotensi yang sama.
     *
     * Penyaring Id_Api_Aplikasi ikut dibandingkan karena dua mitra bisa berbagi
     * penjangkar yang sama: tanpa itu, keduanya saling menerima nomor transaksi
     * milik yang lain.
     *
     * PIC boleh kosong (DDL-2026-135): kunjungan mitra tidak punya karyawan
     * pendaftar, dan di sana Id_Api_Aplikasi-lah yang memisahkan ruang kuncinya.
     */
    public function cariIdempoten(?string $kodeKaryawanPic, string $idempotencyKey, ?int $idAplikasi = null): ?object
    {
        return DB::table(self::T_HEADER)
            ->when(filled($kodeKaryawanPic), fn ($q) => $q->where('Kode_Karyawan_PIC', $kodeKaryawanPic))
            ->when(blank($kodeKaryawanPic), fn ($q) => $q->whereNull('Kode_Karyawan_PIC'))
            ->where('Idempotency_Key', $idempotencyKey)
            ->when($idAplikasi !== null, fn ($q) => $q->where('Id_Api_Aplikasi', $idAplikasi))
            ->when($idAplikasi === null, fn ($q) => $q->whereNull('Id_Api_Aplikasi'))
            ->first();
    }

    /**
     * Simpan satu registrasi tamu beserta alur dan proyeksinya.
     *
     * @param  array<string,mixed>  $data
     * @return array{no_transaksi: string, id: int, needs_approval: bool, no_transaksi_permit: string|null}
     */
    public function simpan(array $data): array
    {
        $tipe = TipeTamu::sah((string) ($data['tipe_tamu'] ?? TipeTamu::REGULAR));
        $modulApproval = TipeTamu::modulApproval($tipe);
        $config = $this->visitorApprovalPolicy->config($modulApproval);
        $needsApproval = (bool) $config['needs_approval'];
        [$masuk, $keluar] = $this->rentangEstimasi($data);

        // DUA PERAN yang selama ini menyamar jadi satu kolom.
        //
        // $kodePic  = PEMILIK kunjungan: karyawan yang mendaftarkannya. Boleh
        //             kosong, dan memang kosong pada kunjungan dari aplikasi
        //             mitra - di sana tidak ada karyawan yang mengajukan.
        // $kodeAlur = JANGKAR APPROVAL: dari siapa rantai persetujuan disusun.
        //             TIDAK boleh kosong bila modulnya menuntut approval, sebab
        //             createFlow() membangun rantainya dari hierarki orang ini.
        //             Pada pintu mitra ia diisi PIC registry aplikasi - orang
        //             yang memang menangani integrasi itu.
        //
        // Menyatukan keduanya membuat seluruh kunjungan mitra tercatat atas
        // nama si penjangkar, seolah dialah yang mendaftarkan tamunya.
        $kodePic = filled($data['kode_karyawan_pic'] ?? null)
            ? (string) $data['kode_karyawan_pic']
            : null;

        $kodeAlur = (string) ($data['kode_karyawan_approval'] ?? $kodePic ?? '');

        // Jejak audit: APA yang membuat baris ini, bukan siapa yang menjangkar
        // approvalnya. Keduanya sempat disamakan, dan hasilnya kunjungan mitra
        // tercatat 'dibuat oleh A1' — seolah orang itu yang mengetiknya.
        //
        // Nama penanggung jawab sengaja TIDAK dipakai di sini: ia klaim dari
        // pihak luar yang belum terverifikasi, sedangkan kolom ini berisi fakta
        // yang bisa dipertanggungjawabkan. Namanya sudah punya kolom sendiri.
        $dibuatOleh = (string) ($data['dibuat_oleh'] ?? $kodePic ?? $kodeAlur);
        $noTransaksi = (string) ($data['no_transaksi'] ?? $this->nomorTransaksiBaru());
        $idAplikasi = $data['id_api_aplikasi'] ?? null;

        // Manifes menyimpan apa adanya: rombongan / anggota tambahan saja.
        // Tamu utama sudah tersimpan di kolom Nama_Lengkap_Tamu, sehingga
        // tidak disisipkan lagi ke JSON agar tidak terjadi duplikasi data.
        $manifesMemuatTamuUtama = (bool) ($data['manifes_memuat_tamu_utama'] ?? false);

        $daftarAnggota = $manifesMemuatTamuUtama
            ? $this->daftarAnggota->susun((string) $data['nama_tamu'], (array) ($data['daftar_anggota'] ?? []))
            : $this->daftarAnggota->susunApaAdanya((array) ($data['daftar_anggota'] ?? []));

        // Penjaga lebar dipasang di penulis, bukan hanya di FormRequest.
        // Aturan yang hanya hidup di salah satu pintu adalah pola kegagalan
        // yang sudah berulang di modul ini: batas 'keterangan' sempat ada di
        // pintu mitra dan TIDAK ADA di layar web, padahal keduanya menulis
        // baris yang sama. Di sini ia berlaku untuk pintu mana pun, termasuk
        // yang belum ditulis.
        foreach (self::LEBAR_TERSEMPIT as $field => $lebar) {
            $nilai = $data[$field] ?? null;

            if (is_string($nilai) && mb_strlen($nilai) > $lebar) {
                throw new \InvalidArgumentException(
                    "Isian '{$field}' melebihi {$lebar} karakter."
                );
            }
        }

        // Plat dinormalkan di SINI, bukan di masing-masing pemanggil.
        //
        // Kedua pintu sebelumnya menyusunnya sendiri dan menghasilkan bentuk
        // yang berbeda untuk kendaraan yang sama: layar web menyimpan masukan
        // apa adanya (prefix tak dibesarkan huruf meski yang diperiksa ke tabel
        // master adalah versi huruf besarnya, dan suffix tak disentuh sama
        // sekali), sedangkan API mitra memecah satu string. Selama bentuknya
        // ditentukan pemanggil, aturan baru hanya terpasang di salah satunya.
        $plat = $this->platNomor->normalkan(
            $data['no_plat_kendaraan'] ?? null,
            $data['plat_prefix'] ?? null,
            $data['plat_angka'] ?? null,
            $data['plat_suffix'] ?? null,
        );

        // Kolom tabel tamu varchar(5). Menolak di sini mengubah galat truncate
        // SQL Server — yang sampai ke pemakainya tanpa menyebut field mana —
        // menjadi sebab yang bisa dibaca, dan mencegah plat tersimpan separuh.
        if ($bagian = $this->platNomor->muat($plat, PlatNomorKendaraan::LEBAR_TAMU)) {
            throw new \InvalidArgumentException(
                "Bagian plat nomor '{$bagian}' melebihi ".PlatNomorKendaraan::LEBAR_TAMU.' karakter.'
            );
        }

        // Aturan tipe ditegakkan di penulis, bukan hanya di FormRequest: API
        // mitra tidak memakai FormRequest layar, dan aturan yang hanya hidup
        // di salah satu pintu adalah pola kegagalan yang berulang di modul ini.
        AturanFormTamu::periksa(
            $tipe,
            $masuk,
            $keluar,
            $data['jenis_kendaraan'] ?? null,
            $plat,
            (bool) ($data['longgar_b2b'] ?? false),
        );

        $unitB2b = null;
        if ($tipe === TipeTamu::B2B) {
            if (blank($data['b2b_unit'] ?? null)) {
                throw new \InvalidArgumentException('Unit pengaju tamu B2B (Procurement/Logistic) wajib ditetapkan.');
            }
            $unitB2b = MatriksTamuService::unitSah((string) $data['b2b_unit']);
        }

        $noPermit = null;

        $headerId = DB::transaction(function () use ($unitB2b, $data, $tipe, $modulApproval, $masuk, $keluar, $kodePic, $kodeAlur, $dibuatOleh, $noTransaksi, $idAplikasi, $needsApproval, $daftarAnggota, $manifesMemuatTamuUtama, $plat, &$noPermit) {
            $header = [
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan_PIC' => $kodePic,
                'Status_Kunjungan' => $needsApproval ? StatusKunjunganTamu::PENDING_APPROVAL : StatusKunjunganTamu::SCHEDULED,
                'Flag_Approval' => $needsApproval ? null : 'Y',
                // Selalu diturunkan dari Estimasi Masuk; kueri dan indeks lama
                // tetap membaca kolom ini.
                'Tanggal_Kunjungan' => $masuk->toDateString(),
                'Flag_Aktif' => 'Y',
                'Created_At' => now(),
                'Created_By' => $dibuatOleh,
                'Idempotency_Key' => $data['idempotency_key'],
                // Penanda sumber ditulis eksplisit pada KEDUA pintu, bukan
                // diserahkan ke backfill sekali jalan: baris baru yang lahir
                // tanpa penanda membuat laporan pemisah mitra salah diam-diam.
                'Sumber_Pengajuan' => $idAplikasi !== null ? 'API' : 'HCIS',
                'Id_Api_Aplikasi' => $idAplikasi,
                'Referensi_Mitra' => $data['referensi_mitra'] ?? null,
            ];

            if ($needsApproval) {
                $header['Needs_Approval'] = 'Y';
            }

            $headerId = DB::table(self::T_HEADER)->insertGetId($header);

            DB::table(self::T_DETAIL)->insert([
                'Id_Transaksi_Tamu' => $headerId,
                'No_Transaksi' => $noTransaksi,
                'Nama_Lengkap_Tamu' => $data['nama_tamu'],
                'Tipe_Tamu' => $tipe,
                'Tamu_Kategori' => TipeTamu::kategori($masuk, $keluar),
                // Unit pengaju B2B (DDL-2026-122) — dikunci pemanggil dari
                // matriks departemen (layar) atau dipaku PROCUREMENT (API).
                'B2B_Unit' => $unitB2b,
                'Instansi_Atau_Asal' => $data['instansi_asal'] ?? null,
                'Nomor_Handphone' => $data['no_hp'] ?? null,
                'Deksripsi_Keperluan' => $data['keperluan'] ?? null,
                // Manifes tetap satu-satunya sumber jumlah, tetapi cara
                // menghitungnya bergantung pada bentuknya - lihat jumlahOrang().
                'Jumlah_Tamu' => $this->daftarAnggota->jumlahOrang($daftarAnggota, $manifesMemuatTamuUtama),
                'Daftar_Anggota_Json' => $this->daftarAnggota->keJson($daftarAnggota),
                'Keterangan_Tujuan_Detail' => $data['pic_tujuan'] ?? null,
                // Siapa yang BERTANGGUNG JAWAB atas kunjungan ini - berbeda dari
                // pic_tujuan, yang menjawab siapa yang hendak DITEMUI. Wajib
                // pada pintu mitra, sebab di sana tidak ada karyawan pengaju.
                'Nama_Penanggung_Jawab' => $data['nama_penanggung_jawab'] ?? null,
                'Keterangan_Tambahan' => $data['keterangan'] ?? null,
                'Jenis_Kendaraan' => $data['jenis_kendaraan'] ?? null,
                'Plat_Nomor_Prefix' => $plat['prefix'],
                'Plat_Nomor_Angka' => $plat['angka'],
                'Plat_Nomor_Suffix' => $plat['suffix'],
                // Estimasi Masuk / Estimasi Keluar. Waktu_Estimasi_Kunjungan
                // dipensiunkan; kolomnya arsip data lama.
                'Waktu_Mulai_Kunjungan' => $masuk,
                'Waktu_Selesai_Kunjungan' => $keluar,
                'No_Identitas' => $data['no_identitas'] ?? null,
                'Jenis_Identitas' => $data['jenis_identitas'] ?? null,
            ]);

            if ($needsApproval) {
                // Regular → pool ADMIN_TAMU (config TAMU); B2B → pool
                // ADMIN_TAMU_B2B (config TAMU_B2B).
                $this->visitorApprovalPolicy->createFlow($modulApproval, $noTransaksi, $kodeAlur);

                return $headerId;
            }

            // Proyeksi ini menentukan akses tamu di gerbang, sehingga harus
            // commit atau rollback bersama header/detail; bukan side effect
            // best-effort.
            $this->visitorEfektif->syncTamuEfektif($noTransaksi);

            // Kunjungannya boleh langsung terjadwal, izin gerbangnya tidak:
            // permit COKLAT diajukan di sini dan baru hidup setelah verifikator
            // menyetujuinya. Nomornya dipulangkan supaya pemanggil bisa
            // menyerahkannya ke mitra; null berarti kunjungan ini memang tidak
            // berpermit (tamu REGULAR), dan itu bukan kegagalan.
            $noPermit = $this->permitCoklatTamu->sinkron($noTransaksi);

            return $headerId;
        });

        return [
            'no_transaksi' => $noTransaksi,
            'id' => (int) $headerId,
            'needs_approval' => $needsApproval,
            // NULL ketika kunjungannya masih menunggu approval — permitnya baru
            // diajukan setelah kunjungan itu sendiri disetujui final.
            'no_transaksi_permit' => $noPermit,
            'tipe_tamu' => $tipe,
        ];
    }

    /**
     * Estimasi Masuk & Keluar.
     *
     * Pemanggil yang hanya mengirim tanggal (API mitra selama masa transisi,
     * data lama) mendapat rentang satu hari kalender [tanggal 00:00, +1 hari) —
     * persis jendela yang dipakai permit dan expiry sebelum estimasi ada.
     *
     * @param  array<string,mixed>  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rentangEstimasi(array $data): array
    {
        $mulai = $data['waktu_mulai_kunjungan'] ?? null;
        $selesai = $data['waktu_selesai_kunjungan'] ?? null;

        if (filled($mulai) && filled($selesai)) {
            return [Carbon::parse($mulai), Carbon::parse($selesai)];
        }

        if (filled($mulai) xor filled($selesai)) {
            throw new \InvalidArgumentException('Estimasi Masuk dan Estimasi Keluar wajib diisi berpasangan.');
        }

        if (blank($data['tanggal_kunjungan'] ?? null)) {
            throw new \InvalidArgumentException('Estimasi Masuk dan Estimasi Keluar wajib diisi.');
        }

        $masuk = Carbon::parse($data['tanggal_kunjungan'])->startOfDay();

        return [$masuk, $masuk->copy()->addDay()];
    }
}
