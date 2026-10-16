<?php

namespace App\Http\Requests\VISITOR\Guest\Visit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload registrasi tamu B2B dari aplikasi mitra.
 *
 * Tidak ada kode karyawan di sini, dan itu disengaja: PIC penanggung jawab
 * melekat pada pendaftaran aplikasi. Kalau boleh dikirim pemanggil, siapa pun
 * yang memegang kunci API bisa menggantungkan tanggung jawab kunjungan pada
 * karyawan mana pun yang ia tebak kodenya.
 */
class AjukanTamuB2BApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sepenuhnya di tangan hris.api.guard (HMAC + nonce +
        // whitelist endpoint per aplikasi); tidak ada sesi pengguna di sini.
        return true;
    }

    /**
     * Masa transisi (services.visitor.tamu_b2b_api.wajib_lengkap = false):
     * pengajuan lama yang hanya bertanggal dan tanpa kendaraan masih diterima,
     * dengan `peringatan` di balasannya. Mode wajib: aturan tamu B2B penuh —
     * sama dengan layar HCIS (AturanFormTamu), karena keduanya menulis baris
     * yang sama.
     */
    public static function wajibLengkap(): bool
    {
        return (bool) config('services.visitor.tamu_b2b_api.wajib_lengkap', false);
    }

    public function rules(): array
    {
        $wajib = self::wajibLengkap();

        return [
            'Idempotency-Key' => ['nullable', 'string', 'max:100'],
            'nama_tamu' => ['required', 'string', 'max:150'],
            'instansi' => ['required', 'string', 'max:150'],
            'keperluan' => ['required', 'string', 'max:250'],
            // Diturunkan dari estimasi_masuk bila dikirim; wajib hanya bagi
            // pengajuan lama (tanpa estimasi) selama masa transisi.
            'tanggal_kunjungan' => [$wajib ? 'nullable' : 'required_without:estimasi_masuk', 'nullable', 'date_format:Y-m-d'],
            'estimasi_masuk' => [$wajib ? 'required' : 'nullable', 'required_with:estimasi_keluar', 'date'],
            'estimasi_keluar' => [$wajib ? 'required' : 'nullable', 'required_with:estimasi_masuk', 'date', 'after:estimasi_masuk'],
            // Opsional: bila kosong, tujuan jatuh ke PIC yang melekat pada
            // pendaftaran aplikasi mitra.
            'pic_tujuan' => ['nullable', 'string', 'max:100'],
            // Siapa yang bertanggung jawab atas kunjungan ini di pihak mitra.
            // WAJIB: pintu ini tidak punya karyawan pengaju, jadi tanpa nama
            // ini kunjungannya sampai ke gerbang tanpa seorang pun yang bisa
            // dihubungi petugas.
            'penanggung_jawab' => ['required', 'string', 'max:150'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            // 50, bukan 100: kolom proyeksi N_VISITOR_Tamu_Efektif lebih
            // sempit daripada kolom transaksinya, dan yang menentukan adalah
            // yang tersempit.
            'no_identitas' => ['nullable', 'string', 'max:50'],
            'jenis_identitas' => ['nullable', 'string', 'max:20'],
            'jenis_kendaraan' => [$wajib ? 'required' : 'nullable', 'string', 'max:50'],
            'no_plat_kendaraan' => [$wajib ? 'required' : 'nullable', 'string', 'max:50'],
            'keterangan' => ['nullable', 'string', 'max:500'],
            'referensi_mitra' => ['nullable', 'string', 'max:100'],
            'daftar_anggota' => ['nullable', 'array'],
            'daftar_anggota.*.nama' => ['required', 'string', 'max:150'],
            'daftar_anggota.*.jenis_identitas' => ['nullable', 'string', 'max:20'],
            // Batas yang sama dengan identitas tamu utama: isinya hal yang
            // sama, dan dua batas berbeda untuk satu konsep hanya
            // membingungkan pengisi form. Anggota terpanjang yang
            // tersimpan hari ini 20 karakter.
            'daftar_anggota.*.no_identitas' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'Idempotency-Key' => trim((string) $this->header('Idempotency-Key')),
        ]);
    }

    public function messages(): array
    {
        return [
            'tanggal_kunjungan.date_format' => 'Tanggal kunjungan harus berformat YYYY-MM-DD.',
            'estimasi_keluar.after' => 'Estimasi keluar harus setelah estimasi masuk.',
        ];
    }
}
