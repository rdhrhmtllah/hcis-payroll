<?php

namespace App\Services\LMS\Content\Verification;

/**
 * Kebijakan format berkas Library Konten (dipakai bersama LMS + SOP).
 *
 * KENAPA DI KODE, BUKAN DI TABEL SETTING
 * Allowlist yang bisa diubah lewat UI adalah allowlist yang bisa diubah
 * penyerang yang sudah berhasil masuk sebagai admin. Daftar ini juga
 * merupakan keputusan keamanan, jadi perubahannya harus melewati review
 * dan tercatat di git — bukan berubah diam-diam lewat satu form.
 *
 * YANG DITOLAK DAN ALASANNYA
 *   docm/xlsm/pptm : makro = eksekusi kode di komputer karyawan yang mengunduh
 *   doc/xls/ppt    : OLE2 — ketiganya identik lewat magic bytes, jadi tak bisa
 *                    diverifikasi, dan makro lawasnya tak terdeteksi
 *   svg/html/htm   : dieksekusi sebagai markup bila terbuka inline (XSS)
 *   zip + segala eksekutabel
 */
class KontenPolicy
{
    private const MB = 1048576;

    /**
     * Peta kebijakan per ekstensi.
     *
     * magic  : daftar tanda tangan byte awal; salah satu cocok = lolos.
     *          Kosong berarti berkas tak melewati penyimpanan kita (Bunny),
     *          jadi tak ada byte yang bisa diperiksa di sini.
     * storage: 'gcs' diverifikasi server; 'bunny' ditangani penyedia.
     */
    private const FORMAT = [
        'pdf' => [
            'mime' => 'application/pdf',
            'maks' => 50 * self::MB,
            'tipe' => 'document',
            'storage' => 'gcs',
            'magic' => ['%PDF-'],
            // Sebagian penghasil PDF menyisipkan baris kosong sebelum header
            // dan berkasnya tetap sah. Hanya PDF yang diberi kelonggaran ini —
            // PNG/JPEG/zip dengan byte di depannya memang rusak.
            'toleransi_spasi' => true,
            'label' => 'PDF',
        ],
        'docx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'maks' => 50 * self::MB,
            'tipe' => 'document',
            'storage' => 'gcs',
            'magic' => ["PK\x03\x04"],
            'label' => 'Word (docx)',
        ],
        'xlsx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'maks' => 50 * self::MB,
            'tipe' => 'document',
            'storage' => 'gcs',
            'magic' => ["PK\x03\x04"],
            'label' => 'Excel (xlsx)',
        ],
        'pptx' => [
            'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'maks' => 50 * self::MB,
            'tipe' => 'document',
            'storage' => 'gcs',
            'magic' => ["PK\x03\x04"],
            'label' => 'PowerPoint (pptx)',
        ],
        'jpg' => [
            'mime' => 'image/jpeg',
            'maks' => 10 * self::MB,
            'tipe' => 'image',
            'storage' => 'gcs',
            'magic' => ["\xFF\xD8\xFF"],
            'label' => 'JPEG',
        ],
        'jpeg' => [
            'mime' => 'image/jpeg',
            'maks' => 10 * self::MB,
            'tipe' => 'image',
            'storage' => 'gcs',
            'magic' => ["\xFF\xD8\xFF"],
            'label' => 'JPEG',
        ],
        'png' => [
            'mime' => 'image/png',
            'maks' => 10 * self::MB,
            'tipe' => 'image',
            'storage' => 'gcs',
            'magic' => ["\x89PNG\r\n\x1A\n"],
            'label' => 'PNG',
        ],
        'mp4' => [
            'mime' => 'video/mp4',
            'maks' => 2048 * self::MB,
            'tipe' => 'video',
            'storage' => 'bunny',
            'magic' => [],
            'label' => 'Video MP4',
        ],
        'mov' => [
            'mime' => 'video/quicktime',
            'maks' => 2048 * self::MB,
            'tipe' => 'video',
            'storage' => 'bunny',
            'magic' => [],
            'label' => 'Video MOV',
        ],
        'mkv' => [
            'mime' => 'video/x-matroska',
            'maks' => 2048 * self::MB,
            'tipe' => 'video',
            'storage' => 'bunny',
            'magic' => [],
            'label' => 'Video MKV',
        ],
        'webm' => [
            'mime' => 'video/webm',
            'maks' => 2048 * self::MB,
            'tipe' => 'video',
            'storage' => 'bunny',
            'magic' => [],
            'label' => 'Video WebM',
        ],
    ];

    /**
     * Ekstensi yang DITOLAK dengan pesan khusus. Tanpa peta ini, semuanya
     * jatuh ke pesan generik "format tidak diizinkan" dan admin tak tahu
     * harus berbuat apa — padahal untuk kasus di bawah ini jalan keluarnya
     * jelas (simpan ulang tanpa makro / konversi ke format modern).
     */
    private const DITOLAK = [
        'docm' => 'Berkas Word ber-makro tidak diizinkan. Simpan ulang sebagai .docx.',
        'xlsm' => 'Berkas Excel ber-makro tidak diizinkan. Simpan ulang sebagai .xlsx.',
        'pptm' => 'Berkas PowerPoint ber-makro tidak diizinkan. Simpan ulang sebagai .pptx.',
        'doc' => 'Format Word lawas tidak diizinkan. Simpan ulang sebagai .docx.',
        'xls' => 'Format Excel lawas tidak diizinkan. Simpan ulang sebagai .xlsx.',
        'ppt' => 'Format PowerPoint lawas tidak diizinkan. Simpan ulang sebagai .pptx.',
        'svg' => 'SVG tidak diizinkan karena dapat memuat skrip. Pakai PNG atau JPG.',
        'html' => 'Berkas HTML tidak diizinkan.',
        'htm' => 'Berkas HTML tidak diizinkan.',
        'zip' => 'Arsip ZIP tidak diizinkan. Unggah berkasnya satu per satu.',
        'rar' => 'Arsip RAR tidak diizinkan. Unggah berkasnya satu per satu.',
    ];

    /**
     * Ekstensi yang dilarang khusus untuk sebuah scope.
     * LMS dibatasi hanya untuk format PDF dan Video (MP4, MOV, MKV, WebM),
     * sedangkan SOP mendukung seluruh format dokumen & gambar modern.
     *
     * @var array<string, string[]>
     */
    private const LARANGAN_SCOPE = [
        'LMS' => ['docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png'],
        'SOP' => [],
    ];

    /**
     * Bagaimana sebuah format boleh disajikan.
     *
     * Ini keputusan keamanan, bukan pilihan tampilan — karena itu tempatnya di
     * sini, satu berkas dengan allowlist, bukan tersebar sebagai tebakan di
     * komponen Vue. Aplikasi hanya punya dua penyaji: reader per-halaman untuk
     * PDF dan pemutar Bunny untuk video. Di luar itu, satu-satunya cara "melihat"
     * dokumen adalah menyerahkannya ke penampil pihak ketiga — dan dokumen SOP
     * perusahaan tidak dikirim ke luar hanya demi pratinjau.
     *
     *   viewer — dirender server jadi gambar per halaman (PDF)
     *   inline — aman ditampilkan apa adanya oleh peramban (gambar)
     *   putar  — pemutar Bunny (video); pada item FORM tetap dipaksa unduh
     *   unduh  — TIDAK bisa dilihat di aplikasi, hanya diunduh
     *
     * Ekstensi yang tak terdaftar jatuh ke 'unduh'. Defaultnya sengaja yang
     * paling tertutup: format baru yang belum dipikirkan tidak boleh langsung
     * mendapat izin tampil hanya karena seseorang lupa menambahkannya di sini.
     */
    private const TAMPILAN = [
        'pdf' => 'viewer',
        'jpg' => 'inline',
        'jpeg' => 'inline',
        'png' => 'inline',
        'mp4' => 'putar',
        'mov' => 'putar',
        'mkv' => 'putar',
        'webm' => 'putar',
    ];

    /** Berapa byte awal yang dibaca untuk memeriksa tanda tangan. */
    public const BYTE_HEAD = 4096;

    /** Cara penyajian sebuah ekstensi: viewer | inline | putar | unduh. */
    public static function tampilan(string $ext): string
    {
        return self::TAMPILAN[strtolower($ext)] ?? 'unduh';
    }

    /** Nama format yang bisa dibaca admin, mis. "Excel (xlsx)". */
    public static function label(string $ext): string
    {
        return self::FORMAT[strtolower($ext)]['label'] ?? strtoupper($ext);
    }

    /**
     * Ekstensi dari nama berkas, dinormalisasi.
     *
     * Mengambil segmen TERAKHIR — "laporan.pdf.exe" menghasilkan "exe", bukan
     * "pdf". Karakter selain huruf/angka dibuang supaya trik seperti
     * "berkas.pdf%00.exe" atau "berkas.pdf " tidak lolos.
     */
    public static function normalisasiExt(string $namaBerkas): string
    {
        $ext = pathinfo($namaBerkas, PATHINFO_EXTENSION);

        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', (string) $ext));
    }

    /** Kebijakan untuk sebuah ekstensi pada scope tertentu, atau null bila tak diizinkan. */
    public static function untukExt(string $scope, string $ext): ?array
    {
        $ext = strtolower($ext);
        if (! isset(self::FORMAT[$ext])) {
            return null;
        }
        if (in_array($ext, self::LARANGAN_SCOPE[strtoupper($scope)] ?? [], true)) {
            return null;
        }

        return self::FORMAT[$ext] + ['ext' => $ext];
    }

    /** Pesan penolakan yang menjelaskan jalan keluarnya, atau pesan generik. */
    public static function pesanTolak(string $ext, string $scope = 'LMS'): string
    {
        $ext = strtolower($ext);
        $larangan = self::LARANGAN_SCOPE[strtoupper($scope)] ?? [];

        if (in_array($ext, $larangan, true)) {
            if (strtoupper($scope) === 'LMS') {
                return "Format .{$ext} belum didukung di LMS. Saat ini LMS hanya mendukung berkas PDF dan Video (MP4, MOV, MKV, WebM).";
            }
        }

        return self::DITOLAK[$ext]
            ?? 'Format berkas ini tidak diizinkan. Yang diterima: '.implode(', ', self::extDiizinkan($scope)).'.';
    }

    /** Daftar ekstensi yang diizinkan pada sebuah scope (untuk atribut accept & pesan). */
    public static function extDiizinkan(string $scope): array
    {
        $larangan = self::LARANGAN_SCOPE[strtoupper($scope)] ?? [];

        return array_values(array_diff(array_keys(self::FORMAT), $larangan));
    }

    /** Batas ukuran per ekstensi, untuk dikirim ke browser bersama tiket. */
    public static function batasPerExt(string $scope): array
    {
        $out = [];
        foreach (self::extDiizinkan($scope) as $ext) {
            $out[$ext] = self::FORMAT[$ext]['maks'];
        }

        return $out;
    }

    /**
     * Apakah byte awal cocok dengan ekstensi yang diklaim.
     * Kembalikan null bila cocok, atau alasan penolakan.
     */
    public static function cocokMagic(string $head, string $ext): ?string
    {
        $p = self::FORMAT[strtolower($ext)] ?? null;
        if (! $p || empty($p['magic'])) {
            return null; // tak ada byte untuk diperiksa (jalur Bunny)
        }

        foreach ($p['magic'] as $tanda) {
            if (self::adaTandaTangan($head, $tanda, ! empty($p['toleransi_spasi']))) {
                return null;
            }
        }

        return "Isi berkas bukan {$p['label']} meskipun namanya berakhiran .{$ext}.";
    }

    /**
     * Pemeriksaan negatif atas byte awal — dijalankan untuk SEMUA berkas,
     * termasuk yang tanda tangannya sudah cocok.
     *
     * Alasannya: tanda tangan yang cocok tidak menutup kemungkinan berkas
     * polyglot, dan beberapa jenis berbahaya tak punya tanda tangan sama
     * sekali (HTML, skrip shell). Kembalikan null bila aman.
     */
    public static function deteksiBerbahaya(string $head): ?string
    {
        if (str_starts_with($head, 'MZ')) {
            return 'Berkas ini adalah program Windows, bukan dokumen.';
        }
        if (str_starts_with($head, "\x7FELF")) {
            return 'Berkas ini adalah program Linux, bukan dokumen.';
        }
        if (str_starts_with($head, '#!')) {
            return 'Berkas ini adalah skrip, bukan dokumen.';
        }
        // OLE2: format lawas doc/xls/ppt. Ekstensinya sudah ditolak, tapi
        // pemeriksaan ini menangkap yang menyamar sebagai .docx.
        if (str_starts_with($head, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return 'Format Office lawas tidak diizinkan. Simpan ulang sebagai .docx/.xlsx/.pptx.';
        }

        // Pola pendek sengaja dihindari. "<%" misalnya cuma dua byte, sehingga
        // pada berkas biner sepanjang 1 KB ia muncul secara kebetulan pada
        // sekitar 1,5% berkas — itu berarti menolak unggahan yang sah.
        $awal = strtolower(substr($head, 0, 1024));
        foreach (['<!doctype', '<html', '<script', '<svg', '<?php'] as $pola) {
            if (str_contains($awal, $pola)) {
                return 'Berkas memuat markup atau skrip yang dapat dieksekusi.';
            }
        }

        return null;
    }

    /**
     * Tanda tangan pada offset 0 — dan hanya untuk format yang menyatakannya,
     * boleh didahului spasi/baris kosong.
     *
     * Isi lain sebelum tanda tangan tidak pernah ditoleransi; di situlah
     * berkas polyglot bersembunyi.
     */
    private static function adaTandaTangan(string $head, string $tanda, bool $toleransiSpasi): bool
    {
        if (str_starts_with($head, $tanda)) {
            return true;
        }
        if (! $toleransiSpasi) {
            return false;
        }
        $tanpaSpasi = ltrim($head, " \t\r\n\0");

        return $tanpaSpasi !== $head && str_starts_with($tanpaSpasi, $tanda);
    }

    private static function daftarTerbaca(): string
    {
        return implode(', ', array_keys(self::FORMAT));
    }
}
