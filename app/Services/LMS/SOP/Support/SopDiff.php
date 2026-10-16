<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Pembanding nilai lama vs baru untuk jejak audit SOP.
 *
 * KENAPA TERPUSAT. "Item X diubah" tanpa menyebut apa yang berubah bukan jejak
 * audit — ia cuma stempel waktu. Yang menjawab pertanyaan sebenarnya ("kenapa
 * mode aksesnya jadi VIEW_ONLY?") adalah pasangan lama→baru per kolom.
 *
 * Master Dokumen sudah melakukannya sejak awal; Builder tidak. Menyalin
 * logikanya ke sana akan melahirkan dua bentuk Meta_JSON yang berbeda untuk
 * pertanyaan yang sama, dan layar riwayat harus tahu keduanya. Satu tempat,
 * satu bentuk.
 *
 * BENTUKNYA: [['field' => 'Judul', 'lama' => '...', 'baru' => '...'], ...]
 */
class SopDiff
{
    /** Label kolom yang dipakai bersama seluruh modul. */
    public const LABEL = [
        // Dokumen & versi
        'Nomor_SOP_Resmi' => 'Nomor Dokumen',
        'Judul' => 'Judul',
        'Tipe' => 'Tipe',
        'Deskripsi' => 'Deskripsi',
        'Jenis_Dokumen_Id' => 'Jenis dokumen',
        /* Divisi dan sub-divisi dijadikan SATU baris jejak: keduanya adalah satu
           keputusan kepemilikan, dan memisahkannya menghasilkan dua baris yang
           salah satunya selalu tampak berubah tanpa sebab. */
        'Divisi_Id' => 'Unit pemilik',
        'Departemen_Id' => 'Departemen pemilik',
        'Entitas_Pemilik_Id' => 'Entitas pemilik',
        'Entitas_Lingkup_Id' => 'Ruang lingkup',
        'APQC_Id' => 'Proses APQC',
        'No_Dokumen' => 'Nomor dokumen',
        'Versi_Ke' => 'Nomor versi',
        'Id_Master_Konten' => 'Berkas',
        'Id_Master_Ujian' => 'Ujian',
        'Nama_Ujian' => 'Nama ujian',
        'Lampiran' => 'Form kosong',
        'Ringkasan_Perubahan' => 'Ringkasan perubahan',
        'Tanggal_Berlaku' => 'Tanggal berlaku',
        'Tanggal_Permintaan' => 'Tanggal permintaan',
        'Tanggal_Respon' => 'Tanggal respon',
        'Tanggal_Akhir_Approval' => 'Tanggal akhir approval',
        'Tanggal_Kadaluarsa' => 'Tanggal kedaluwarsa',
        'Tanggal_Obsolete' => 'Tanggal obsolete',
        'Tanggal_Pengesahan' => 'Tanggal pengesahan',
        'Nomor_Pengesahan' => 'Nomor pengesahan',
        'Disahkan_Oleh' => 'Pengesah',
        'Jabatan_Pengesah' => 'Jabatan pengesah',

        // Paket
        'Nama_Paket' => 'Nama paket',
        'Kode_Paket' => 'Kode paket',
        'Kode_Karyawan_Owner' => 'Pemilik',
        'Tujuan_Ruang_Lingkup' => 'Tujuan & ruang lingkup',
        'Prioritas' => 'Prioritas',
        'Estimasi_Waktu_Baca_Menit' => 'Estimasi waktu baca',
        'Mode_Urutan_Item' => 'Mode urutan',
        'Mode_Distribusi' => 'Mode distribusi',
        'Flag_Absensi' => 'Presensi wajib',
        'Cover_Path' => 'Cover',

        // Item & section
        'Judul_Item' => 'Judul item',
        'Deskripsi_Item' => 'Deskripsi item',
        'Tipe_Item' => 'Tipe item',
        'Mode_Akses' => 'Mode akses',
        'URL_Tautan' => 'URL tautan',
        'Label_Tautan' => 'Label tautan',
        'Konten_Teks' => 'Isi teks',
        'Jenis_Quiz' => 'Jenis kuis',
        'Nilai_Minimum' => 'Nilai minimum',
        'Flag_Wajib' => 'Wajib',
        'Flag_Highlight' => 'Ditandai penting',
        'Flag_Camera' => 'Kamera wajib',
        'Urutan' => 'Urutan',
        'Nama_Section' => 'Nama section',
        'Deskripsi_Section' => 'Deskripsi section',
    ];

    /**
     * Kolom yang berubah antara dua kumpulan nilai.
     *
     * Hanya kunci yang ADA di $baru yang dibandingkan: pemanggil biasanya
     * mengirim payload update, dan kolom yang tak ia sentuh memang bukan
     * perubahan. Perbandingannya lewat string supaya 1 dan '1' — bentuk yang
     * sama dari sumber berbeda (form vs DB) — tidak terbaca sebagai perubahan.
     */
    public static function antara(array $lama, array $baru): array
    {
        $beda = [];
        foreach ($baru as $kolom => $nilaiBaru) {
            $nilaiLama = $lama[$kolom] ?? null;
            if ((string) ($nilaiLama ?? '') === (string) ($nilaiBaru ?? '')) {
                continue;
            }

            $beda[] = [
                'field' => self::LABEL[$kolom] ?? $kolom,
                'lama' => $nilaiLama === null || $nilaiLama === '' ? null : (string) $nilaiLama,
                'baru' => $nilaiBaru === null || $nilaiBaru === '' ? null : (string) $nilaiBaru,
            ];
        }

        return $beda;
    }

    /**
     * Satu kalimat untuk kolom Keterangan.
     *
     * "Disimpan tanpa perubahan nilai" ditulis apa adanya, bukan disembunyikan:
     * seseorang menekan simpan tanpa mengubah apa pun adalah fakta audit yang
     * sah — dan tanpa barisnya, jejaknya akan tampak seolah ia tak pernah
     * membuka layar itu.
     */
    public static function kalimat(string $awalan, array $beda): string
    {
        if ($beda === []) {
            return $awalan.' disimpan tanpa perubahan nilai.';
        }

        return $awalan.' diubah: '.implode(', ', array_column($beda, 'field')).'.';
    }
}
