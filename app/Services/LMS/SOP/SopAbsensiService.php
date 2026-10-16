<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Presensi kehadiran SOP — gerbang sebelum paket boleh dikerjakan.
 *
 * KENAPA ADA
 * `N_SOP_Master_Paket.Flag_Absensi` sudah bisa dinyalakan admin sejak lama, dan
 * halaman detail menampilkan spanduk "Presensi Kehadiran Wajib" — tapi tombolnya
 * mati dan tak ada apa pun yang menegakkannya. Toggle yang menjanjikan sesuatu
 * lalu membiarkannya lewat lebih buruk daripada tak ada toggle sama sekali:
 * admin mengira SOP-nya terkunci, karyawan membaca kata "wajib" lalu melewatinya.
 *
 * BENTUK DATA
 * Check-in dicatat pada tingkat PAKET, bukan item — `Id_SOP_Paket_Item` diisi NULL.
 * UNIQUE (Detail, Item, Output) memperlakukan NULL sebagai nilai yang sama di SQL
 * Server, sehingga satu penugasan hanya bisa punya satu baris 'In'. Idempotensi
 * datang dari skema, bukan dari pengecekan di aplikasi yang bisa balapan.
 */
class SopAbsensiService
{
    /** Di atas ini koordinat dianggap terlalu kabur untuk disebut bukti lokasi. */
    private const AKURASI_MERAGUKAN_M = 500;

    /**
     * Apakah paket ini menuntut presensi, dan apakah karyawan sudah melakukannya.
     *
     * @return array{wajib:bool, sudah:bool, detail_id:?int, absensi:?object, sesi:?array}
     */
    public function status(string $kode, int $idPaket): array
    {
        $paket = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->first(['Flag_Absensi']);
        $wajib = $paket && $paket->Flag_Absensi === SopStatus::AKTIF;

        $detailId = $this->detailId($kode, $idPaket);
        if (! $detailId) {
            return ['wajib' => $wajib, 'sudah' => false, 'detail_id' => null, 'absensi' => null, 'sesi' => null];
        }

        $row = DB::table('N_SOP_Absensi')
            ->where('Id_SOP_Penjadwalan_Detail', $detailId)
            ->whereNull('Id_SOP_Paket_Item')
            ->where('Output', 'In')
            ->first();

        return [
            'wajib' => $wajib,
            'sudah' => (bool) $row,
            'detail_id' => (int) $detailId,
            'absensi' => $row ? $this->rapikan($row) : null,
            'sesi' => $row ? $this->sesi($detailId, $row->Checktime) : null,
        ];
    }

    /**
     * Sesi kehadiran: dari check-in sampai paket tuntas.
     *
     * SENGAJA TANPA CHECK-OUT. LMS memakai pasangan In/Out, tapi di SOP tindakan
     * yang menandai "selesai berada di sini" sudah ada dan lebih bermakna:
     * menuntaskan seluruh item wajib. Meminta orang menekan tombol keluar lagi
     * cuma menambah langkah yang bisa terlupakan — dan sesi yang tak pernah
     * ditutup justru merusak datanya sendiri.
     *
     * Durasi TIDAK disimpan sebagai kolom: ia turunan murni dari dua stempel
     * waktu yang sudah ada, dan menyalinnya berarti membuka peluang menyimpang.
     */
    private function sesi(int $detailId, $mulai): array
    {
        $d = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan_Detail', $detailId)
            ->first(['Status_Paket', 'Tanggal_Selesai']);

        $tuntas = $d && $d->Status_Paket === SopStatus::STATUS_SELESAI;
        $mulaiC = Carbon::parse($mulai);
        $selesaiC = $tuntas && $d->Tanggal_Selesai ? Carbon::parse($d->Tanggal_Selesai) : null;

        // Jam server bisa bergeser, atau paket ditandai selesai oleh rekonsiliasi
        // sebelum baris presensi tersimpan. Durasi negatif lebih membingungkan
        // daripada tak ada durasi, jadi dijepit ke nol.
        $durasi = $selesaiC ? max(0, $mulaiC->diffInSeconds($selesaiC, false)) : null;

        return [
            'mulai' => $mulaiC->toIso8601String(),
            'selesai' => $selesaiC ? $selesaiC->toIso8601String() : null,
            'berjalan' => ! $selesaiC,
            'durasi_detik' => $durasi,
        ];
    }

    /**
     * Apakah HALAMAN paket ini harus dikunci untuk karyawan ini?
     *
     * Bedanya dengan boleh(): di sini penugasan WAJIB ada. Paket PUBLIK yang
     * kebetulan menyalakan Flag_Absensi tetap boleh dibaca pembaca umum —
     * mereka tak punya baris penjadwalan untuk menampung presensi, jadi
     * mengunci mereka berarti mengunci selamanya tanpa jalan keluar.
     */
    public function terkunci(string $kode, int $idPaket): bool
    {
        $s = $this->status($kode, $idPaket);

        return $s['wajib'] && $s['detail_id'] !== null && ! $s['sudah'];
    }

    /**
     * Gerbang yang dipanggil mesin completion & dispatch kuis.
     * true = boleh lanjut.
     */
    public function boleh(string $kode, int $idPaket): bool
    {
        $s = $this->status($kode, $idPaket);

        return ! $s['wajib'] || $s['sudah'];
    }

    /**
     * Rekam check-in.
     *
     * @param  string  $fotoBase64  data URI hasil tangkapan kamera
     * @param  float|null  $akurasi  meter, dari Geolocation API
     * @return array{ok:bool, message:string, absensi:?array}
     */
    public function checkin(string $kode, int $idPaket, string $fotoBase64, ?float $lat, ?float $lng, ?float $akurasi): array
    {
        $detailId = $this->detailId($kode, $idPaket);
        if (! $detailId) {
            return ['ok' => false, 'message' => 'dokumen ini tidak ditugaskan kepada Anda.', 'absensi' => null];
        }

        $paket = DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $idPaket)->first(['Flag_Absensi', 'Nama_Paket']);
        if (! $paket || $paket->Flag_Absensi !== SopStatus::AKTIF) {
            return ['ok' => false, 'message' => 'dokumen ini tidak memerlukan presensi.', 'absensi' => null];
        }

        // Sudah pernah → kembalikan yang lama, jangan buat ganda. Menekan tombol
        // dua kali karena jaringan lambat tak boleh berakhir jadi galat.
        $ada = DB::table('N_SOP_Absensi')
            ->where('Id_SOP_Penjadwalan_Detail', $detailId)
            ->whereNull('Id_SOP_Paket_Item')
            ->where('Output', 'In')
            ->first();
        if ($ada) {
            return ['ok' => true, 'message' => 'Anda sudah check-in sebelumnya.', 'absensi' => $this->rapikan($ada)];
        }

        [$path, $alasan] = $this->simpanFoto($fotoBase64, $kode, $idPaket);
        if (! $path) {
            return ['ok' => false, 'message' => $alasan, 'absensi' => null];
        }

        try {
            $id = DB::table('N_SOP_Absensi')->insertGetId([
                'Id_SOP_Penjadwalan_Detail' => $detailId,
                'Id_SOP_Paket_Item' => null, // NULL = tingkat paket
                'Kode_Karyawan' => $kode,
                'Checktime' => now(),
                'Output' => 'In',
                'File_Path' => $path,
                'Latitude' => $lat,
                'Longitude' => $lng,
                'Akurasi_Meter' => $akurasi,
                'User_Agent' => substr((string) request()->userAgent(), 0, 255),
                'IP_Address' => substr((string) request()->ip(), 0, 45),
                'Created_At' => now(),
            ], 'Id_SOP_Absensi');
        } catch (\Throwable $e) {
            // Balapan dua permintaan: UNIQUE yang menangkapnya, bukan aplikasi.
            Log::warning('SOP absensi insert: '.$e->getMessage());
            $ulang = DB::table('N_SOP_Absensi')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->whereNull('Id_SOP_Paket_Item')->where('Output', 'In')->first();

            return $ulang
                ? ['ok' => true, 'message' => 'Anda sudah check-in.', 'absensi' => $this->rapikan($ulang)]
                : ['ok' => false, 'message' => 'Gagal menyimpan presensi.', 'absensi' => null];
        }

        // Jejak admin — N_SOP_Log sebelumnya hanya ditulis aksi monitoring.
        DB::table('N_SOP_Log')->insert([
            'Id_SOP_Paket' => $idPaket,
            'Aksi' => 'ABSEN_IN',
            'Kode_Karyawan' => $kode,
            'Keterangan' => 'Check-in presensi dokumen "'.$paket->Nama_Paket.'".',
            'Meta_JSON' => json_encode(['lat' => $lat, 'lng' => $lng, 'akurasi_m' => $akurasi]),
            'Created_At' => now(),
        ]);

        $row = DB::table('N_SOP_Absensi')->where('Id_SOP_Absensi', $id)->first();

        return ['ok' => true, 'message' => 'Presensi tercatat. Selamat membaca.', 'absensi' => $this->rapikan($row)];
    }

    /** Bukti presensi seorang karyawan (permukaan admin). */
    public function bukti(string $kode, ?int $idPaket = null): \Illuminate\Support\Collection
    {
        $q = DB::table('N_SOP_Absensi as a')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'a.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('a.Kode_Karyawan', $kode);

        if ($idPaket) {
            $q->where('p.Id_SOP_Paket', $idPaket);
        }

        return $q->orderByDesc('a.Checktime')->limit(100)
            ->get(['a.*', 'p.Nama_Paket'])
            ->map(fn ($r) => (object) array_merge((array) $this->rapikan($r), ['Nama_Paket' => $r->Nama_Paket]));
    }

    /* ================= INTERNAL ================= */

    private function detailId(string $kode, int $idPaket): ?int
    {
        $id = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF)
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->value('d.Id_SOP_Penjadwalan_Detail');

        return $id ? (int) $id : null;
    }

    /** Simpan foto wajah ke GCS. Mengikuti pola resolveCover() di Builder. */
    /**
     * Simpan foto wajah ke GCS.
     *
     * @return array{0:?string, 1:?string} [path, alasan gagal]
     */
    private function simpanFoto(string $dataUri, string $kode, int $idPaket): array
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $dataUri, $m)) {
            return [null, 'Foto tidak terbaca. Ulangi pengambilan foto.'];
        }

        $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
        $bin = base64_decode(substr($dataUri, strpos($dataUri, ',') + 1)) ?: '';
        if ($bin === '' || strlen($bin) < 1024) {
            return [null, 'Foto tidak terbaca. Ulangi pengambilan foto.']; // < 1 KB pasti bukan foto
        }

        if ($this->punyaExif($bin)) {
            return [null, 'Foto harus diambil langsung dari kamera, bukan dari galeri. Ulangi pengambilan foto.'];
        }

        $path = 'sop/absensi/'.now()->format('Y/m').'/'.Str::slug($kode.'-'.$idPaket).'-'.Str::uuid().'.'.$ext;

        try {
            Storage::disk('gcs')->put($path, $bin);
        } catch (\Throwable $e) {
            Log::error('SOP absensi simpanFoto gagal', ['exception' => $e]);

            return [null, 'Gagal menyimpan foto presensi. Coba lagi.'];
        }

        return [$path, null];
    }

    /**
     * Apakah JPEG ini membawa segmen EXIF?
     *
     * Dipakai menolak foto galeri. Tangkapan kamera lewat <canvas>.toDataURL()
     * SELALU polos — peramban menulis JFIF (APP0) tanpa APP1/Exif. Sebaliknya
     * berkas hasil jepretan kamera ponsel hampir selalu membawa EXIF (Make,
     * Model, DateTimeOriginal). Jadi kehadiran EXIF adalah penanda kuat bahwa
     * gambarnya berkas lama yang diunggah, bukan wajah yang baru saja dijepret.
     *
     * Segmen ditelusuri lewat header-nya, BUKAN strpos('Exif') polos: kata itu
     * bisa muncul kebetulan di dalam data piksel dan akan menolak foto yang sah.
     */
    private function punyaExif(string $bin): bool
    {
        if (strncmp($bin, "\xFF\xD8", 2) !== 0) {
            return false;
        } // bukan JPEG

        $n = strlen($bin);
        $i = 2;
        while ($i + 4 <= $n) {
            if ($bin[$i] !== "\xFF") {
                return false;
            } // struktur tak dikenali — jangan menuduh
            $marker = ord($bin[$i + 1]);

            // Penanda tanpa muatan (padding, RSTn)
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD8)) {
                $i += 2;

                continue;
            }
            // SOS/EOI: setelah ini data piksel, tak ada lagi metadata
            if ($marker === 0xDA || $marker === 0xD9) {
                return false;
            }

            $len = (ord($bin[$i + 2]) << 8) | ord($bin[$i + 3]);
            if ($len < 2) {
                return false;
            }

            if ($marker === 0xE1 && substr($bin, $i + 4, 4) === 'Exif') {
                return true;
            }

            $i += 2 + $len;
        }

        return false;
    }

    /** Bentuk seragam untuk API + penanda kualitas lokasi. */
    private function rapikan(object $r): object
    {
        $akurasi = $r->Akurasi_Meter !== null ? (float) $r->Akurasi_Meter : null;

        return (object) [
            'Id_SOP_Absensi' => $r->Id_SOP_Absensi,
            'Checktime' => $r->Checktime ? Carbon::parse($r->Checktime)->format('Y-m-d H:i') : null,
            'Output' => $r->Output,
            'Latitude' => $r->Latitude !== null ? (float) $r->Latitude : null,
            'Longitude' => $r->Longitude !== null ? (float) $r->Longitude : null,
            'Akurasi_Meter' => $akurasi,
            // Koordinat kabur tetap disimpan, tapi ditandai — bukti yang tak bisa
            // dinilai kualitasnya lebih berbahaya daripada tak ada bukti.
            'Lokasi_Meragukan' => $akurasi !== null && $akurasi > self::AKURASI_MERAGUKAN_M,
            'Ada_Foto' => ! empty($r->File_Path),
            'File_Path' => $r->File_Path,
            'User_Agent' => $r->User_Agent ?? null,
            'IP_Address' => $r->IP_Address ?? null,
        ];
    }

    /** URL bertanda tangan foto bukti (15 menit) — jangan pernah kirim path mentah ke UI. */
    public function fotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(15));
        } catch (\Throwable $e) {
            Log::error('SOP absensi fotoUrl gagal', ['exception' => $e]);

            return null;
        }
    }
}
