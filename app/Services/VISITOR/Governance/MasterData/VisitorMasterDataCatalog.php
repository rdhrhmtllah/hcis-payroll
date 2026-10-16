<?php

namespace App\Services\VISITOR\Governance\MasterData;

use App\Services\SYSTEM\MasterData\KontakBantuan\KontakBantuanService;
use InvalidArgumentException;

final class VisitorMasterDataCatalog
{
    /** Kode_Fitur kontak bantuan permit di tabel kontak global. */
    public const FITUR_KONTAK_PERMIT = 'PERMIT';

    public const KEYS = [
        'kategori-permit',
        'plat-kendaraan',
        'vehicle-type',
        'vehicle-model',
        'vehicle-color',
        'durasi-permit',
        'approval-question',
        'purpose',
        'kontak-bantuan',
    ];

    /**
     * Domain pemakai tiap master.
     *
     * Diturunkan dari pemakaian NYATA di kode, bukan dari namanya:
     *
     *   - purpose        -> hanya GuestRegistrationController.
     *   - plat-kendaraan -> registrasi tamu DAN pengajuan permit.
     *   - vehicle-type   -> registrasi tamu DAN pengajuan permit; kolom Akses
     *                       pada masternya sudah lebih dulu mengakuinya.
     *   - sisanya        -> pengajuan permit dan checklist verifikasinya.
     *
     * 'bersama' muncul di KEDUA halaman. Barisnya sama persis — yang berbeda
     * hanya pintu masuknya; admin tamu tidak perlu membuka halaman permit
     * sekadar menambah kode plat baru. Konsekuensinya memang disadari:
     * menyunting dari halaman mana pun memengaruhi kedua domain, dan itu sifat
     * datanya, bukan kelalaian pemisahan.
     */
    public const DOMAIN = [
        'kategori-permit' => 'permit',
        'plat-kendaraan' => 'bersama',
        'vehicle-type' => 'bersama',
        'vehicle-model' => 'permit',
        'vehicle-color' => 'permit',
        'durasi-permit' => 'permit',
        'approval-question' => 'permit',
        'purpose' => 'tamu',
        // Hanya dibaca halaman permit karyawan (pengajuan & detail).
        'kontak-bantuan' => 'permit',
    ];

    /**
     * Kunci master milik satu domain, termasuk yang dipakai bersama.
     *
     * Satu-satunya tempat pemetaannya dibaca: rute, gerbang API, dan katalog
     * yang dikirim ke layar semuanya memanggil ini. Daftar yang disalin ke
     * salah satunya akan menua sendiri dan menghasilkan halaman yang
     * menampilkan master yang endpointnya justru menolaknya.
     *
     * @return list<string>
     */
    public static function keysDomain(string $domain): array
    {
        return array_values(array_filter(
            self::KEYS,
            static fn (string $key): bool => in_array(self::DOMAIN[$key] ?? 'bersama', [$domain, 'bersama'], true),
        ));
    }

    public static function all(): array
    {
        return [
            'approval-question' => [
                'label' => 'Pertanyaan Verifikasi',
                'short_label' => 'Pertanyaan',
                'icon' => 'patch-question-fill',
                'description' => 'Checklist verifikasi yang muncul sesuai kategori permit dan tipe kendaraan.',
                'table' => 'N_EMI_VISITOR_Master_Approval_Question',
                'id' => 'Id_Approval_Question',
                'status' => 'Flag_Aktif',
                'order' => ['Id_Approval_Question'],
                'unique_by' => ['Teks_Pertanyaan', 'Target_Id_Kategori_Permit', 'Target_Id_Tipe_Kendaraan'],
                'fields' => [
                    self::field('Teks_Pertanyaan', 'Pertanyaan', 'textarea', true, 255),
                    self::relation('Target_Id_Kategori_Permit', 'Kategori Permit', 'kategori-permit', false),
                    self::relation('Target_Id_Tipe_Kendaraan', 'Tipe Kendaraan', 'vehicle-type', false),
                    self::select('Wajib_Diisi', 'Wajib Diisi', ['Y' => 'Ya', 'T' => 'Tidak'], true, 'Y'),
                ],
                'columns' => [
                    ['key' => 'Id_Approval_Question', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Teks_Pertanyaan', 'label' => 'Pertanyaan', 'width' => '420px'],
                    ['key' => 'Target_Id_Kategori_Permit_label', 'label' => 'Kategori', 'width' => '150px'],
                    ['key' => 'Target_Id_Tipe_Kendaraan_label', 'label' => 'Tipe Kendaraan', 'width' => '170px'],
                    ['key' => 'Wajib_Diisi', 'label' => 'Kewajiban', 'width' => '110px'],
                    ['key' => 'Usage_Count', 'label' => 'Dipakai', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'durasi-permit' => [
                'label' => 'Durasi Permit',
                'short_label' => 'Durasi',
                'icon' => 'calendar-range-fill',
                'description' => 'Pilihan masa berlaku permit. Flow aktif saat ini menggunakan satuan bulan.',
                'table' => 'N_EMI_VISITOR_Master_Durasi_Permit',
                'id' => 'Id_Durasi_Permit',
                'status' => 'Flag_Aktif',
                'order' => ['Urutan_Tampil', 'Jumlah_Angka'],
                'unique_by' => ['Jumlah_Angka', 'Satuan_Waktu'],
                'fields' => [
                    self::field('Nama_Durasi', 'Nama Durasi', 'text', true, 50),
                    self::number('Jumlah_Angka', 'Jumlah', true, 1, 120),
                    self::select('Satuan_Waktu', 'Satuan', ['MONTH' => 'Bulan'], true, 'MONTH'),
                    self::field('Keterangan_Durasi', 'Keterangan', 'text', false, 20),
                    self::number('Urutan_Tampil', 'Urutan Tampil', false, 0, 999, 0),
                ],
                'columns' => [
                    ['key' => 'Id_Durasi_Permit', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Nama_Durasi', 'label' => 'Nama'],
                    ['key' => 'Jumlah_Angka', 'label' => 'Jumlah'],
                    ['key' => 'Satuan_Waktu', 'label' => 'Satuan'],
                    ['key' => 'Keterangan_Durasi', 'label' => 'Keterangan'],
                    ['key' => 'Urutan_Tampil', 'label' => 'Urutan', 'align' => 'center'],
                    ['key' => 'Usage_Count', 'label' => 'Dipakai', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'kategori-permit' => [
                'label' => 'Kategori Permit',
                'short_label' => 'Kategori',
                'icon' => 'tags-fill',
                'description' => 'Jenis akses permit kendaraan dan identitas warna yang tampil di UI.',
                'table' => 'N_EMI_VISITOR_Master_Kategori_Permit',
                'id' => 'Id_Kategori_Permit',
                'status' => 'Flag_Aktif',
                'order' => ['Nama_Kategori'],
                'unique_by' => ['Nama_Kategori'],
                'fields' => [
                    self::field('Nama_Kategori', 'Nama Kategori', 'text', true, 50),
                    self::field('Deskripsi_Akses', 'Deskripsi Akses', 'textarea', false, 255),
                    self::field('Kode_Warna_UI', 'Warna UI', 'color', false, 10, '#6366F1'),
                    // Penanda tampil di form pengajuan permit karyawan. Kategori
                    // izin tamu (COKLAT) diterbitkan lewat registrasi tamu, jadi
                    // menawarkannya di sana hanya menyesatkan pemohon.
                    self::select('Flag_Muncul_Pengajuan', 'Muncul di Pengajuan Permit', ['Y' => 'Ya', 'T' => 'Tidak'], true, 'Y'),
                ],
                'columns' => [
                    ['key' => 'Id_Kategori_Permit', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Nama_Kategori', 'label' => 'Kategori'],
                    ['key' => 'Deskripsi_Akses', 'label' => 'Deskripsi'],
                    ['key' => 'Kode_Warna_UI', 'label' => 'Warna'],
                    ['key' => 'Flag_Muncul_Pengajuan', 'label' => 'Muncul di Pengajuan', 'align' => 'center'],
                    ['key' => 'Jumlah_Pertanyaan', 'label' => 'Pertanyaan Khusus', 'align' => 'center'],
                    ['key' => 'Usage_Count', 'label' => 'Dipakai', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'plat-kendaraan' => [
                'label' => 'Kode Plat Kendaraan',
                'short_label' => 'Kode Plat',
                'icon' => '123',
                'description' => 'Prefix wilayah plat yang dipakai pada registrasi tamu dan permit.',
                'table' => 'N_EMI_VISITOR_Master_Plat_Kendaraan',
                'id' => 'Id_Plat_Kendaraan',
                'status' => 'Flag_Aktif',
                'order' => ['Kode_Plat'],
                'unique_by' => ['Kode_Plat'],
                'uppercase' => ['Kode_Plat'],
                'fields' => [self::field('Kode_Plat', 'Kode Plat', 'text', true, 5)],
                'columns' => [
                    ['key' => 'Id_Plat_Kendaraan', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Kode_Plat', 'label' => 'Kode Plat'],
                    ['key' => 'Usage_Count', 'label' => 'Dipakai', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'kontak-bantuan' => [
                'label' => 'Kontak Bantuan Permit',
                'short_label' => 'Kontak Bantuan',
                'icon' => 'headset',
                'description' => 'Kontak resmi yang tampil di halaman pengajuan dan detail permit, untuk karyawan yang ingin berkonsultasi.',
                // Tabel GLOBAL (SYSTEM); halaman ini hanya melihat & menyentuh
                // baris VISITOR/PERMIT — lingkupnya dipasang otomatis oleh service.
                'table' => KontakBantuanService::TABEL,
                'scope' => ['Kode_Grup_Fitur' => 'VISITOR', 'Kode_Fitur' => self::FITUR_KONTAK_PERMIT],
                'jejak' => true,
                'id' => 'Id_Kontak_Bantuan',
                'status' => 'Flag_Aktif',
                'order' => ['Urutan_Tampil', 'Nama_Kontak'],
                'unique_by' => ['Nama_Kontak'],
                'fields' => [
                    self::field('Nama_Kontak', 'Nama Kontak', 'text', true, 100),
                    self::field('Keterangan', 'Bagian / Keterangan', 'text', false, 150),
                    self::field('No_HP', 'No. HP / WhatsApp', 'text', false, 20),
                    self::field('Email', 'Email', 'text', false, 150),
                    self::field('Jam_Layanan', 'Jam Layanan', 'text', false, 100),
                    self::number('Urutan_Tampil', 'Urutan Tampil', false, 0, 999, 0),
                ],
                'columns' => [
                    ['key' => 'Id_Kontak_Bantuan', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Nama_Kontak', 'label' => 'Nama', 'width' => '200px'],
                    ['key' => 'Keterangan', 'label' => 'Bagian', 'width' => '180px'],
                    ['key' => 'No_HP', 'label' => 'No. HP', 'width' => '150px'],
                    ['key' => 'Email', 'label' => 'Email', 'width' => '200px'],
                    ['key' => 'Jam_Layanan', 'label' => 'Jam Layanan', 'width' => '180px'],
                    ['key' => 'Urutan_Tampil', 'label' => 'Urutan', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'purpose' => [
                'label' => 'Keperluan Tamu',
                'short_label' => 'Keperluan',
                'icon' => 'chat-left-text-fill',
                'description' => 'Daftar keperluan standar untuk pendaftaran kunjungan tamu.',
                'table' => 'N_EMI_VISITOR_Master_Purpose',
                'id' => 'Id_Master_Purpose',
                'status' => 'Flag_Aktif',
                'order' => ['Nama_Keperluan'],
                'unique_by' => ['Nama_Keperluan'],
                'fields' => [self::field('Nama_Keperluan', 'Nama Keperluan', 'text', true, 100)],
                'columns' => [
                    ['key' => 'Id_Master_Purpose', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Nama_Keperluan', 'label' => 'Keperluan'],
                    ['key' => 'Usage_Count', 'label' => 'Dipakai', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'vehicle-color' => [
                'label' => 'Warna Kendaraan',
                'short_label' => 'Warna',
                'icon' => 'palette-fill',
                'description' => 'Pilihan warna kendaraan pada pengajuan permit.',
                'table' => 'N_EMI_VISITOR_Master_Vehicle_Color',
                'id' => 'Id_Master_Color',
                'status' => 'Flag_Aktif',
                'order' => ['Nama_Warna'],
                'unique_by' => ['Nama_Warna'],
                'fields' => [self::field('Nama_Warna', 'Nama Warna', 'text', true, 50)],
                'columns' => [
                    ['key' => 'Id_Master_Color', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Nama_Warna', 'label' => 'Warna'],
                    ['key' => 'Usage_Count', 'label' => 'Dipakai', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'vehicle-model' => [
                'label' => 'Model Kendaraan',
                'short_label' => 'Model',
                'icon' => 'car-front-fill',
                'description' => 'Model kendaraan yang dikelompokkan berdasarkan tipe kendaraan.',
                'table' => 'N_EMI_VISITOR_Master_Vehicle_Model',
                'id' => 'Id_Vehicle_Model',
                'status' => 'Flag_Aktif',
                'order' => ['Nama_Model_Kendaraan'],
                'unique_by' => ['Id_Master_Tipe', 'Nama_Model_Kendaraan'],
                'fields' => [
                    self::relation('Id_Master_Tipe', 'Tipe Kendaraan', 'vehicle-type', true),
                    self::field('Nama_Model_Kendaraan', 'Nama Model', 'text', true, 100),
                ],
                'columns' => [
                    ['key' => 'Id_Vehicle_Model', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Id_Master_Tipe_label', 'label' => 'Tipe'],
                    ['key' => 'Nama_Model_Kendaraan', 'label' => 'Model'],
                    ['key' => 'Usage_Count', 'label' => 'Dipakai', 'width' => '90px', 'align' => 'center'],
                ],
            ],
            'vehicle-type' => [
                'label' => 'Tipe Kendaraan',
                'short_label' => 'Tipe',
                'icon' => 'truck-front-fill',
                'description' => 'Jenis kendaraan dan konteks pemakaiannya pada flow Permit atau Tamu.',
                'table' => 'N_EMI_VISITOR_Master_Vehicle_Type',
                'id' => 'Id_Vehicle_Type',
                'status' => 'Flag_Aktif',
                'order' => ['Nama_Tipe_Kendaraan'],
                'unique_by' => ['Nama_Tipe_Kendaraan'],
                'uppercase' => ['Akses'],
                'fields' => [
                    self::field('Nama_Tipe_Kendaraan', 'Nama Tipe', 'text', true, 50),
                    self::select('Akses', 'Digunakan Untuk', ['ALL' => 'Permit & Tamu', 'PERMIT' => 'Permit', 'TAMU' => 'Tamu'], true, 'ALL'),
                ],
                'columns' => [
                    ['key' => 'Id_Vehicle_Type', 'label' => 'ID', 'width' => '72px', 'align' => 'center'],
                    ['key' => 'Nama_Tipe_Kendaraan', 'label' => 'Tipe Kendaraan'],
                    ['key' => 'Akses', 'label' => 'Penggunaan'],
                    ['key' => 'Jumlah_Model', 'label' => 'Jumlah Model', 'align' => 'center'],
                ],
            ],
        ];
    }

    public static function get(string $key): array
    {
        $definition = self::all()[$key] ?? null;
        if (! $definition) {
            throw new InvalidArgumentException('Master data Visitor tidak dikenal.');
        }

        return $definition;
    }

    /**
     * @param  string|null  $domain  'tamu' | 'permit'; null berarti seluruhnya.
     */
    public static function forFrontend(?string $domain = null): array
    {
        $definitions = self::all();
        $keys = $domain === null ? self::KEYS : self::keysDomain($domain);

        return array_map(
            fn (string $key) => [
                'key' => $key,
                'label' => $definitions[$key]['label'],
                'short_label' => $definitions[$key]['short_label'],
                'icon' => $definitions[$key]['icon'],
                'description' => $definitions[$key]['description'],
                'id_key' => $definitions[$key]['id'],
                'has_status' => $definitions[$key]['status'] !== null,
                'fields' => $definitions[$key]['fields'],
                'columns' => $definitions[$key]['columns'],
            ],
            $keys,
        );
    }

    private static function field(string $key, string $label, string $type, bool $required, int $max, mixed $default = ''): array
    {
        return compact('key', 'label', 'type', 'required', 'max', 'default');
    }

    private static function number(string $key, string $label, bool $required, int $min, int $max, mixed $default = ''): array
    {
        return compact('key', 'label', 'required', 'min', 'max', 'default') + ['type' => 'number'];
    }

    private static function select(string $key, string $label, array $options, bool $required, mixed $default = ''): array
    {
        return compact('key', 'label', 'options', 'required', 'default') + ['type' => 'select'];
    }

    private static function relation(string $key, string $label, string $relation, bool $required): array
    {
        return compact('key', 'label', 'relation', 'required') + ['type' => 'relation', 'default' => null];
    }
}
