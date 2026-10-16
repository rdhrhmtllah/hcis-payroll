<?php

namespace App\Services\VISITOR\Workflow\Reporting\Export;

class VisitorSystemTemplates
{
    public static function semua(): array
    {
        return [
            'visitor-tamu-rinci' => self::rinci(
                'visitor-tamu-rinci',
                'Buku Tamu Efektif',
                'Rincian kunjungan tamu yang telah efektif, termasuk lifecycle dan waktu aktual.',
                'tamu',
                ['no_transaksi', 'tanggal_kunjungan', 'nama_tamu', 'instansi_asal', 'nama_pic', 'divisi_pic', 'keperluan', 'tujuan_detail', 'jumlah_tamu', 'status', 'waktu_masuk', 'waktu_keluar']
            ),
            'visitor-tamu-ringkas' => self::ringkas(
                'visitor-tamu-ringkas',
                'Ringkasan Kunjungan',
                'Jumlah transaksi dan jumlah tamu per tanggal, status, dan divisi PIC.',
                'tamu',
                ['tanggal_kunjungan', 'status', 'divisi_pic'],
                ['jumlah_kunjungan', 'jumlah_tamu']
            ),
            'visitor-permit-rinci' => self::rinci(
                'visitor-permit-rinci',
                'Permit Efektif',
                'Rincian permit kendaraan efektif beserta pemohon, kendaraan, masa berlaku, dan lifecycle.',
                'permit',
                ['no_transaksi', 'nama_pemohon', 'departemen', 'no_plat', 'kategori_permit', 'tipe_kendaraan', 'model_kendaraan', 'tanggal_mulai', 'tanggal_kadaluarsa', 'sisa_hari', 'status']
            ),
            'visitor-permit-ringkas' => self::ringkas(
                'visitor-permit-ringkas',
                'Ringkasan Permit',
                'Jumlah permit per status, kategori, dan departemen.',
                'permit',
                ['status', 'kategori_permit', 'departemen'],
                ['jumlah_permit']
            ),
        ];
    }

    public static function untuk(string $dataset): array
    {
        return array_filter(
            self::semua(),
            fn (array $template) => $template['dataset'] === $dataset
        );
    }

    private static function rinci(string $kode, string $nama, string $deskripsi, string $dataset, array $kolom): array
    {
        return self::template($kode, $nama, $deskripsi, $dataset, 'rinci', [], [], $kolom);
    }

    private static function ringkas(string $kode, string $nama, string $deskripsi, string $dataset, array $dimensi, array $ukuran): array
    {
        return self::template($kode, $nama, $deskripsi, $dataset, 'ringkasan', $dimensi, $ukuran, []);
    }

    private static function template(string $kode, string $nama, string $deskripsi, string $dataset, string $mode, array $dimensi, array $ukuran, array $kolom): array
    {
        return [
            'kode' => $kode,
            'nama' => $nama,
            'deskripsi' => $deskripsi,
            'dataset' => $dataset,
            'mode' => $mode,
            'definisi' => [
                'versi' => 1,
                'dataset' => $dataset,
                'mode' => $mode,
                'dimensi' => $dimensi,
                'ukuran' => $ukuran,
                'kolom' => $kolom,
                'saringan' => [],
                'urut' => [],
                'opsi' => ['grand_total' => false],
                'bagan' => null,
                'format_bawaan' => 'xlsx',
            ],
        ];
    }
}
