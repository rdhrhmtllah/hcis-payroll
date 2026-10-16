<?php

namespace App\Services\ESS\Employee\DataCollection;

use Illuminate\Validation\Rule;

/**
 * Sumber kebenaran tunggal untuk daftar pertanyaan.
 *
 * Dipakai dua arah: server memakai aturanValidasi() untuk memvalidasi kiriman,
 * Vue memakai untukUi() untuk merender wizard. Tidak ada daftar field yang
 * ditulis dua kali — menambah pertanyaan cukup menyunting config.
 */
class SkemaPengumpulanData
{
    public function versi(): int
    {
        return (int) config('pengumpulan_data.versi_skema', 1);
    }

    public function langkah(): array
    {
        return config('pengumpulan_data.langkah', []);
    }

    /**
     * Katalog pertanyaan. Field ber-'isian_teks' (atasan PHL) dibentuk di SINI
     * menjadi teks bebas selama pencarian atasan dimatikan — satu titik, supaya
     * validasi, wizard, endpoint pencarian (403 karena tak ada lagi field
     * 'cari_karyawan'), dan penyeragaman kode ikut berubah bersama-sama.
     */
    public function semuaField(): array
    {
        $pakaiPencarian = $this->atasanPakaiPencarian();

        return array_map(function (array $def) use ($pakaiPencarian) {
            $teks = $def['isian_teks'] ?? null;
            unset($def['isian_teks']);

            return ($teks && ! $pakaiPencarian) ? array_replace($def, $teks) : $def;
        }, config('pengumpulan_data.field', []));
    }

    /** Sakelar di config/services.php; baku mati (19 Sep 2026: atasan diisi teks bebas). */
    public function atasanPakaiPencarian(): bool
    {
        return (bool) config('services.hcis.pengumpulan_data.atasan_pakai_pencarian', false);
    }

    /**
     * Field yang berlaku untuk seorang karyawan: sudah menyaring field khusus
     * PHL dan pertanyaan yang dimatikan lewat env.
     *
     * Disaring di SINI, bukan di wizard, supaya satu sakelar menutup ketiga
     * jalurnya sekaligus: kotaknya hilang, tidak dituntut saat Kirim, dan
     * jawaban yang tetap terkirim tidak ikut tersimpan.
     */
    public function fieldBerlaku(bool $isPhl): array
    {
        return array_filter(
            $this->semuaField(),
            fn (array $def, string $kunci) => ($isPhl || ! ($def['khusus_phl'] ?? false)) && $this->pertanyaanAktif($kunci),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** Sakelar di config/services.php (hcis.pengumpulan_data); tanpa entri = aktif. */
    public function pertanyaanAktif(string $kunci): bool
    {
        return (bool) config("services.hcis.pengumpulan_data.pertanyaan_aktif.{$kunci}", true);
    }

    /**
     * Apakah sebuah pertanyaan muncul, mengingat jawaban yang sudah ada.
     *
     * Syaratnya dibaca dari 'tampil_jika' di config — satu tulisan yang dipakai
     * browser (menyembunyikan kotaknya) dan server (tidak menuntut, dan membuang
     * jawabannya). Kalau keduanya menyimpulkan sendiri-sendiri, yang paling
     * mungkin terjadi adalah kotaknya hilang di layar tetapi tetap dituntut saat
     * Kirim — dan tidak ada isian yang bisa memuaskannya.
     */
    public function bidangTampil(array $def, array $jawaban): bool
    {
        $syarat = $def['tampil_jika'] ?? null;

        if (! $syarat) {
            return true;
        }

        $nilai = $jawaban[$syarat['field']] ?? null;

        return in_array($nilai, (array) $syarat['nilai'], strict: true);
    }

    /**
     * Field yang benar-benar berlaku untuk SATU kiriman: sudah menyaring field
     * khusus PHL sekaligus pertanyaan bersyarat yang syaratnya tidak terpenuhi.
     */
    public function fieldBerlakuUntukJawaban(bool $isPhl, array $jawaban): array
    {
        return array_filter(
            $this->fieldBerlaku($isPhl),
            fn (array $def) => $this->bidangTampil($def, $jawaban),
        );
    }

    /**
     * Aturan validasi.
     *
     * $jawaban = null berarti SELURUH katalog pertanyaan — itu yang dipakai
     * ekspor dan halaman admin, yang memang harus melihat semua kolom. Yang
     * memvalidasi sebuah kiriman wajib mengoper jawabannya, supaya pertanyaan
     * bersyarat tidak ikut dituntut kepada orang yang tidak ditanyai.
     */
    public function aturanValidasi(bool $isPhl, ?array $jawaban = null): array
    {
        $berlaku = $jawaban === null
            ? $this->fieldBerlaku($isPhl)
            : $this->fieldBerlakuUntukJawaban($isPhl, $jawaban);

        $aturan = [];

        foreach ($berlaku as $kunci => $def) {
            $aturan[$kunci] = $def['aturan'];

            // Atasan harus karyawan aktif pada level yang diizinkan. Pencarian
            // di wizard menyaring hal yang sama, tetapi POST langsung
            // melewatinya — yang mengikat tetap aturan ini.
            if (($def['tipe'] ?? null) === 'cari_karyawan') {
                $aturan[$kunci][] = Rule::exists('Karyawan', 'Kode_Karyawan')
                    ->where('Aktif', 'Y')
                    ->whereNull('Tanggal_Resign')
                    ->whereIn('ID_Level_Jabatan', fn ($q) => $q
                        ->select('ID_Level_Jabatan')
                        ->from('View_Golongan_Sub_Golongan_Level_Jabatan')
                        ->whereIn('ID_Level', $this->levelAtasan()));
            }
        }

        return $aturan;
    }

    /** ID_Level yang boleh dipilih sebagai SPV / Dept. Head (config/services.php). */
    public function levelAtasan(): array
    {
        return (array) config('services.hcis.pengumpulan_data.atasan_id_level', [3, 4, 5, 6, 7, 8, 9, 10, 11, 12]);
    }

    /**
     * Kalimat galat untuk Validator, berbahasa Indonesia.
     *
     * Gabungan kalimat umum per aturan dan kalimat khusus per field ('pesan'),
     * yang khusus menimpa yang umum. Tanpa ini yang sampai ke karyawan adalah
     * bawaan Laravel — berbahasa Inggris, menyebut nama kolom mentah, dan
     * memuat potongan aturan seperti '-15 years'.
     */
    public function pesanValidasi(): array
    {
        $pesan = [];

        foreach ((array) config('pengumpulan_data.pesan_umum', []) as $aturan => $kalimat) {
            $pesan[$aturan] = $kalimat;
        }

        foreach ($this->semuaField() as $kunci => $def) {
            foreach ((array) ($def['pesan'] ?? []) as $aturan => $kalimat) {
                $pesan["{$kunci}.{$aturan}"] = $kalimat;
            }
        }

        return $pesan;
    }

    /**
     * Nama field sebagaimana dibaca orang. ':attribute' pada kalimat galat
     * diisi dari sini, bukan dari nama kolom seperti 'nik_ktp'.
     */
    public function atributValidasi(): array
    {
        $atribut = [];

        foreach ($this->semuaField() as $kunci => $def) {
            $atribut[$kunci] = $def['label'] ?? $kunci;
        }

        return $atribut;
    }

    public function untukUi(bool $isPhl): array
    {
        $berlaku = $this->fieldBerlaku($isPhl);

        $semua = $this->semuaField();

        $langkah = array_map(function (array $l) use ($berlaku, $semua) {
            $l['field'] = array_values(array_filter(
                $l['field'],
                fn (string $kunci) => array_key_exists($kunci, $berlaku),
            ));

            // Judul langkah mengikuti isinya. Kalau seluruh pertanyaan khusus
            // PHL di langkah ini tersaring habis, judul "Info Pekerjaan"
            // menjanjikan kolom yang tidak akan muncul — yang tersisa cuma
            // pendidikan dan keluarga. Diputuskan dari field yang BENAR-BENAR
            // tampil, bukan dari status PHL-nya, supaya sakelar per pertanyaan
            // ikut terjawab tanpa aturan kedua.
            $adaPhl = collect($l['field'])->contains(fn (string $k) => $semua[$k]['khusus_phl'] ?? false);

            if (! $adaPhl) {
                $l['judul'] = $l['judul_tanpa_phl'] ?? $l['judul'];
                $l['deskripsi'] = $l['deskripsi_tanpa_phl'] ?? $l['deskripsi'];
            }

            unset($l['judul_tanpa_phl'], $l['deskripsi_tanpa_phl']);

            return $l;
        }, $this->langkah());

        // Aturan validasi sengaja TIDAK dikirim ke UI: aturan di browser hanya
        // hiasan, yang mengikat adalah server. Mengirimnya cuma menambah berat.
        //
        // 'tampil_jika' justru HARUS ikut: itu bukan validasi, itu yang
        // menentukan kotaknya muncul atau tidak di layar.
        $field = array_map(
            fn (array $def) => collect($def)->except('aturan', 'khusus_phl', 'pesan')->all(),
            $berlaku,
        );

        return [
            'versi' => $this->versi(),
            'langkah' => array_values($langkah),
            'field' => $field,
        ];
    }
}
