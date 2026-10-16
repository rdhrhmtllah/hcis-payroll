<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\SignatureHelper;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * SATU-SATUNYA tempat kontrak penjadwalan HC Learn diterjemahkan.
 *
 * ── Kenapa dipisah dari layanan yang memakainya ───────────────────────────────
 *
 * `PaHcLearnScheduleService` mengurus PEKERJAAN KITA: penugasan mana yang belum punya pintu,
 * barisnya ditulis ke mana, apa yang boleh dicetak ulang. Kelas ini mengurus BENTUK MEREKA:
 * nama field, bentuk balasan, dan kosakata statusnya. Dua hal yang berubah karena sebab
 * berbeda, dan menyatukannya membuat setiap penyesuaian kontrak menyentuh logika kita.
 *
 * ── Yang perlu diketahui tentang kontraknya ───────────────────────────────────
 *
 * Diserahkan HC Learn 9 September 2026, lima endpoint di grup Penjadwalan.
 *
 *  1. `store` MASSAL DAN ASINKRON. Satu kiriman = satu master ujian + banyak karyawan
 *     (maksimal 1000), dijawab **202** dan dititipkan ke antrean `hcl-penjadwalanpa`.
 *     Tokennya BELUM ADA saat jawaban itu diterima; yang kembali hanya `process_id`.
 *
 *  2. `status` dibaca berulang sampai `SELESAI`. Memanggilnya tidak mengubah apa pun.
 *     SELESAI TIDAK berarti semuanya berhasil — tiap baris punya statusnya sendiri.
 *
 *  3. Yang disimpan pemanggil adalah **`api_ref`**, 32 heksadesimal. `Id_Ujian_Token`
 *     numerik sengaja tidak pernah dikirim: bilangan berurutan bisa ditebak, dan memegang
 *     satu berarti bisa mencoba tetangganya. Seluruh endpoint token menerima `api_ref`.
 *
 *  4. `Id_Integrasi` adalah **`Id_PA_Assignment_Integration` kita**, bukan id penugasan.
 *     Dokumentasi mereka menyebutnya "angka milik HCIS sendiri, dikembalikan apa adanya",
 *     jadi artinya memang milik kita — dan `PaApiGuard` menuntut angka itu kembali pada
 *     callback, menolak 403 bila tidak cocok.
 *
 *     BUKAN id penugasan, karena satu penugasan boleh punya beberapa baris integrasi:
 *     paket soalnya diganti, pintunya dicetak ulang. Id penugasan tidak pernah bisa
 *     menunjuk token yang mana.
 *
 *     Tiap elemen menghasilkan satu token, jadi karyawan yang sama boleh muncul berkali-kali
 *     selama `Id_Integrasi`-nya berbeda — persis yang terjadi pada penilai yang menilai
 *     beberapa orang sekaligus.
 *
 *  5. `store` MENOLAK 422 di muka bila ada `Kode_Karyawan` yang belum dikenal HC Learn.
 *     Karena itu `periksaKaryawan()` dipanggil lebih dulu: kegagalannya ketahuan sekarang,
 *     bukan lima menit kemudian lewat polling atas kiriman yang tak pernah masuk.
 *
 * ── Metode HTTP ikut ditandatangani ───────────────────────────────────────────
 *
 * Muatan tanda tangan memuat metodenya di baris pertama. Menandatangani sebagai POST lalu
 * mengirim PUT menghasilkan 401 "Invalid Signature" — galat yang menuduh kuncinya, bukan
 * metodenya. `extend-time` hanya menerima PUT dan `delete` hanya DELETE, jadi keduanya
 * memakai penanda tangan yang sesuai.
 */
class PaHcLearnJadwalContract
{
    /** Batas subjek per kiriman menurut dokumentasi mereka. */
    public const MAKS_SUBJEK = 1000;

    private const STORE = 'api/v1/pa/penjadwalan/store';

    private const STATUS = 'api/v1/pa/penjadwalan/status/';

    private const UPDATE = 'api/v1/pa/update/penjadwalan/';

    private const HAPUS = 'api/v1/pa/delete/penjadwalan/';

    /* SENGAJA di luar prefix /pa — begitu adanya di dokumentasi mereka. */
    private const PERPANJANG = 'api/v1/extend-time/penjadwalan/';

    private const PERIKSA_KARYAWAN = 'api/v1/pa/checking/karyawan';

    private const DAFTAR_KARYAWAN = 'api/v1/pa/add-new/karyawan';

    /**
     * Batas per permintaan untuk KEDUA alamat karyawan, menurut dokumentasi mereka.
     *
     * Berbeda dari MAKS_SUBJEK: penjadwalan menerima seribu, pemeriksaan dan pendaftaran
     * karyawan hanya dua ratus. Memakai angka penjadwalan di sini dijawab 422 — dan pada
     * pemeriksaan, kodenya ada di query string, sehingga yang lebih dulu putus justru
     * panjang URL-nya, dengan galat yang tidak menyebut jumlah sama sekali.
     */
    private const MAKS_KARYAWAN = 200;

    /**
     * Apakah karyawan-karyawan ini sudah dikenal HC Learn.
     *
     * @param  list<string>  $kodeKaryawan
     * @return array<string, array{ada:bool, siap:bool, alasan:string, keterangan:string}>
     */
    public function periksaKaryawan(array $kodeKaryawan): array
    {
        $kode = array_values(array_unique(array_filter(array_map('trim', $kodeKaryawan))));

        if ($kode === []) {
            return [];
        }

        $peta = [];

        foreach (array_chunk($kode, self::MAKS_KARYAWAN) as $potongan) {
            /* Parameter query WAJIB terurut abjad sebelum ditandatangani: server mereka
               mengurutkannya lebih dulu saat memverifikasi, sehingga urutan yang berbeda
               dijawab 401 "Invalid Signature". */
            $ep = self::PERIKSA_KARYAWAN.'?kode_karyawan='.rawurlencode(implode(',', $potongan));
            $isi = $this->hasil($this->kirim('GET', $ep), 'memeriksa karyawan');

            foreach ((array) ($isi['data'] ?? []) as $b) {
                $peta[(string) ($b['dicari'] ?? '')] = [
                    'ada' => (bool) ($b['ada'] ?? false),
                    'siap' => (bool) ($b['siap_dipakai'] ?? false),
                    'alasan' => (string) ($b['alasan'] ?? 'TIDAK_DIKETAHUI'),
                    'keterangan' => (string) ($b['keterangan'] ?? ''),
                ];
            }
        }

        return $peta;
    }

    /**
     * Mendaftarkan karyawan yang belum dikenal HC Learn.
     *
     * Idempoten: kode yang sudah ada dilaporkan `sudah_ada` dan dilewati. Jawabannya dapat
     * berstatus 207; itu sukses sebagian, jadi rincian gagal harus tetap dibawa ke admin.
     *
     * @param  list<array<string, mixed>>  $karyawan
     * @return array{ringkasan:array<string,int>, tersimpan:list<array<string,mixed>>, sudah_ada:list<array<string,mixed>>, gagal:list<array<string,mixed>>}
     */
    public function daftarkanKaryawan(array $karyawan): array
    {
        $karyawan = array_values($karyawan);
        $gabung = [
            'ringkasan' => ['diminta' => 0, 'tersimpan' => 0, 'sudah_ada' => 0, 'gagal' => 0],
            'tersimpan' => [],
            'sudah_ada' => [],
            'gagal' => [],
        ];

        foreach (array_chunk($karyawan, self::MAKS_KARYAWAN) as $potongan) {
            $isi = $this->hasil(
                $this->kirim('POST', self::DAFTAR_KARYAWAN, ['data' => $potongan]),
                'mendaftarkan karyawan',
            );

            foreach (array_keys($gabung['ringkasan']) as $k) {
                $gabung['ringkasan'][$k] += (int) ($isi['ringkasan'][$k] ?? 0);
            }

            foreach (['tersimpan', 'sudah_ada', 'gagal'] as $k) {
                $gabung[$k] = array_merge($gabung[$k], array_values((array) ($isi[$k] ?? [])));
            }
        }

        return $gabung;
    }

    /**
     * Menjadwalkan satu kiriman: satu master ujian, banyak subjek.
     *
     * @param  list<array{Id_Integrasi:int, Kode_Karyawan:string}>  $subjek
     * @return array{process_id:string, total_subjek:int, id_master_ujian:?int, nama_ujian:?string}
     */
    public function jadwalkan(string $idMasterUjian, string $waktuMulai, string $waktuAkhir, array $subjek): array
    {
        if ($subjek === []) {
            throw new RuntimeException('Tidak ada subjek yang dikirim ke HC Learn.');
        }

        if (count($subjek) > self::MAKS_SUBJEK) {
            throw new RuntimeException(sprintf(
                'Satu kiriman maksimal %d subjek menurut kontrak HC Learn; diterima %d.',
                self::MAKS_SUBJEK,
                count($subjek),
            ));
        }

        $muatan = [
            'Id_Master_Ujian' => $idMasterUjian,
            'Waktu_Mulai' => $waktuMulai,
            'Waktu_Akhir' => $waktuAkhir,
            'Id_Integrasi' => array_values($subjek),

            /* `Jenis_User` DIPATOK 'internal' oleh mereka dan nilainya diabaikan; dikirim
               apa adanya supaya bentuk permintaannya sama dengan contoh dokumentasi.
               `Flag_Camera` TIDAK dikirim: kamera mati secara bawaan di kanal PA, dan
               nilai selain tepat 'Y' ditolak 422. */
            'Jenis_User' => 'internal',
        ];

        $isi = $this->hasil($this->kirim('POST', self::STORE, $muatan), 'menjadwalkan penilaian');

        if (! isset($isi['process_id'])) {
            throw new RuntimeException('HC Learn tidak mengirim process_id pada jawaban penjadwalan.');
        }

        return [
            'process_id' => (string) $isi['process_id'],
            'total_subjek' => (int) ($isi['total_subjek'] ?? count($subjek)),
            'id_master_ujian' => isset($isi['id_master_ujian']) ? (int) $isi['id_master_ujian'] : null,
            'nama_ujian' => $isi['nama_ujian'] ?? null,
        ];
    }

    /**
     * Hasil satu kiriman. Boleh dipanggil berulang; tidak mengubah apa pun.
     *
     * @return array{status:string, ringkasan:array<string,int>, baris:list<array<string,mixed>>}
     */
    public function status(string $processId): array
    {
        $isi = $this->hasil($this->kirim('GET', self::STATUS.rawurlencode($processId)), 'membaca status penjadwalan');

        return [
            'status' => (string) ($isi['status'] ?? 'BERJALAN'),
            'ringkasan' => array_map('intval', (array) ($isi['ringkasan'] ?? [])),
            'baris' => array_values((array) ($isi['result'] ?? [])),
        ];
    }

    /**
     * Memperpanjang waktu SEKALIGUS menerbitkan ulang kodenya.
     *
     * Kode lama langsung tidak berlaku, dan kunci sesi perangkat ikut dilepas. Balasannya
     * membawa `kode_lama` supaya pesan yang telanjur dikirim ke penilai bisa dicabut.
     *
     * @return array<string, mixed>
     */
    public function perpanjang(string $apiRef, ?string $waktuMulai, ?string $waktuAkhir): array
    {
        $muatan = array_filter([
            'waktu_mulai' => $waktuMulai,
            'waktu_akhir' => $waktuAkhir,
        ], fn ($v) => $v !== null);

        if ($muatan === []) {
            throw new RuntimeException('Perpanjangan wajib menyebut waktu mulai atau waktu akhir.');
        }

        return $this->hasil(
            $this->kirim('PUT', self::PERPANJANG.rawurlencode($apiRef), $muatan),
            'memperpanjang waktu penjadwalan',
        );
    }

    /**
     * Memperbarui penjadwalan: alat tes, jendela, atau setelan kamera.
     *
     * Hanya field yang dikirim yang berubah. Mengganti `id_master_ujian` menyusun ulang
     * daftar indikator dan membuang soal yang telanjur disajikan dari paket lama.
     *
     * @param  array<string, mixed>  $ubahan
     * @return array<string, mixed>
     */
    public function perbarui(string $apiRef, array $ubahan): array
    {
        $muatan = array_filter(
            $ubahan,
            fn ($v, $k) => $v !== null && in_array($k, ['id_master_ujian', 'waktu_mulai', 'waktu_akhir', 'flag_camera'], true),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($muatan === []) {
            throw new RuntimeException(
                'Tidak ada yang diubah. Kirim minimal satu di antara id_master_ujian, '
                .'waktu_mulai, waktu_akhir, atau flag_camera.'
            );
        }

        return $this->hasil(
            $this->kirim('PUT', self::UPDATE.rawurlencode($apiRef), $muatan),
            'memperbarui penjadwalan',
        );
    }

    /**
     * Menghapus penjadwalan beserta baris turunannya di sisi mereka.
     *
     * DITOLAK 422 bila pesertanya sudah mengerjakan — menghapusnya berarti membuang jawaban
     * dan nilai yang sudah terbentuk.
     *
     * @return array<string, mixed>
     */
    public function hapus(string $apiRef): array
    {
        return $this->hasil(
            $this->kirim('DELETE', self::HAPUS.rawurlencode($apiRef)),
            'menghapus penjadwalan',
        );
    }

    /* ------------------------------------------------------------------ *
     * Jalur bersama                                                       *
     * ------------------------------------------------------------------ */

    /**
     * Identitas penanda tangan: pengguna yang sedang masuk, ATAU akun layanan.
     *
     * HC Learn memverifikasi `X-App-ID` dan `X-Kode-Karyawan` sebagai PASANGAN — satu terisi
     * saja tetap dijawab 401 — jadi keduanya diambil dari sumber yang sama, tidak pernah
     * dicampur.
     *
     * KENAPA ADA LAPIS KEDUA
     *
     * Versi pertama hanya membaca `Auth::user()`. Di layar itu benar, tetapi PA juga punya
     * jalur tanpa sesi: `pa:jadwalkan-penilaian` dijalankan dari terminal atau penjadwal, dan
     * di sana tidak ada yang sedang masuk. Perintah itu berhenti dengan "Sambungan HC Learn
     * belum tersedia" — kalimat yang menuduh konfigurasi padahal konfigurasinya lengkap —
     * SEBELUM satu permintaan pun terkirim. Cacat yang sama pernah ditemukan di penerbitan
     * versi SOP; di sana jalannya keluar juga akun layanan, dan bentuknya sengaja disamakan.
     *
     * Bukan sekadar memilih pengguna mana pun yang punya `User_Key`: jejak audit di sisi HC
     * Learn akan menyebut orang yang tidak pernah menekan apa pun, dan begitu kunci orang itu
     * dicabut, penjadwalan dari terminal mati tanpa sebab yang kelihatan.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function identitas(): array
    {
        $pengguna = Auth::user();

        if ($pengguna !== null && ! empty($pengguna->User_Key) && ! empty($pengguna->Kode_Users)) {
            return [(string) $pengguna->User_Key, (string) $pengguna->Kode_Users];
        }

        $kunci = config('services.frans.hclearn_api.service_user_key');
        $kode = config('services.frans.hclearn_api.service_kode_users');

        return (! empty($kunci) && ! empty($kode))
            ? [(string) $kunci, (string) $kode]
            : [null, null];
    }

    /**
     * @param  array<string, mixed>  $muatan
     */
    private function kirim(string $metode, string $endpoint, array $muatan = []): Response
    {
        $domain = rtrim((string) config('services.frans.hclearn_api.domain'), '/');
        [$appId, $kodeKaryawan] = $this->identitas();

        if ($domain === '') {
            throw new RuntimeException(
                'Sambungan HC Learn belum tersedia: domainnya kosong. '
                .'Penjadwalan tidak bisa dikirim ke mana pun.'
            );
        }

        if ($appId === null) {
            /* Sebabnya DISEBUT terpisah dari domain kosong. Keduanya sama-sama "belum
               tersedia", tetapi yang satu dibereskan mengisi konfigurasi domain dan yang satu
               lagi mengisi akun layanan — pesan yang menggabungkannya mengirim orang membaca
               berkas yang salah. */
            throw new RuntimeException(
                'Permintaan ke HC Learn tidak bisa ditandatangani: tidak ada pengguna yang '
                .'sedang masuk, dan akun layanan (service_user_key + service_kode_users) '
                .'belum diisi. Keduanya wajib berpasangan.'
            );
        }

        $auth = match ($metode) {
            'GET' => SignatureHelper::get($endpoint, $appId, $kodeKaryawan),
            'POST' => SignatureHelper::store($endpoint, $muatan, $appId, $kodeKaryawan),
            'PUT' => SignatureHelper::put($endpoint, $muatan, $appId, $kodeKaryawan),
            'DELETE' => SignatureHelper::destroy($endpoint, $appId, $kodeKaryawan),
            default => throw new RuntimeException("Metode {$metode} tidak didukung."),
        };

        $url = $domain.'/'.$endpoint;

        /* Batas waktunya PANJANG dan itu disengaja: `store` menggarap sampai seribu subjek
           sebelum menjawab 202, dan memutusnya di tengah meninggalkan kiriman yang sudah
           masuk antrean mereka tanpa process_id di sisi kita — yang berarti tidak ada cara
           mengetahui nasibnya selain mengirim ulang, dan mengirim ulang berarti token kedua
           untuk orang yang sudah punya. */
        $klien = Http::withHeaders($auth['headers'])->timeout(120);

        try {
            return match ($metode) {
                'GET' => $klien->get($url),
                'POST' => $klien->post($url, $muatan),
                'PUT' => $klien->put($url, $muatan),
                'DELETE' => $klien->delete($url),
            };
        } catch (Throwable $e) {
            Log::error('PA: gagal menghubungi HC Learn', [
                'metode' => $metode,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Gagal menghubungi HC Learn: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Isi `result` dari balasan mereka, atau lemparan yang menyebut sebabnya.
     *
     * PESAN MEREKA DITERUSKAN APA ADANYA. Balasan 422 mereka menyebutkan hal yang hanya
     * mereka ketahui — "ada karyawan yang belum terdaftar", "paketnya belum punya alat tes",
     * "pesertanya sudah mengerjakan". Menggantinya dengan kalimat kita sendiri membuang
     * satu-satunya keterangan yang bisa dipakai admin untuk membereskannya.
     *
     * @return array<string, mixed>
     */
    private function hasil(Response $r, string $pekerjaan): array
    {
        $isi = $r->json();

        if ($r->successful()) {
            $result = $isi['result'] ?? null;

            return is_array($result) ? $result : [];
        }

        $pesan = is_array($isi) ? ($isi['message'] ?? null) : null;
        $rincian = $this->rincianGalat($isi);

        throw new RuntimeException(sprintf(
            'HC Learn menolak saat %s (HTTP %d)%s%s',
            $pekerjaan,
            $r->status(),
            $pesan === null ? '.' : ': '.$pesan,
            $rincian === '' ? '' : ' — '.$rincian,
        ));
    }

    /**
     * Rincian per field dari balasan 422 mereka.
     *
     * DULU DIBUANG, dan itu cacat yang mahal. `message` mereka pada 422 berbunyi "Permintaan
     * tidak lolos pemeriksaan" — kalimat yang sama untuk setiap sebab — sementara yang
     * menjelaskan bersarang di `meta.error.details`. Akibatnya 400 penugasan tercatat GAGAL
     * dengan satu kalimat yang tidak menunjuk apa pun, dan satu-satunya cara mengetahui
     * sebabnya adalah mengirim ulang sambil menyadap jaringan — yang memang harus saya lakukan
     * pada 10 September 2026 untuk menemukan bahwa yang ditolak adalah `Waktu_Akhir`.
     *
     * TIGA TEMPAT DICARI karena bentuk balasan mereka tidak seragam: `meta.error.details`
     * (dipakai penjadwalan PA hari ini), lalu `errors` dan `error` di tingkat atas.
     *
     * @param  mixed  $isi
     */
    private function rincianGalat($isi): string
    {
        if (! is_array($isi)) {
            return '';
        }

        $galat = $isi['meta']['error']['details']
            ?? $isi['errors']
            ?? $isi['error']
            ?? null;

        if (is_string($galat)) {
            return $galat;
        }

        if (! is_array($galat)) {
            return '';
        }

        $bagian = [];

        foreach ($galat as $field => $pesan) {
            $teks = is_array($pesan) ? implode('; ', array_map('strval', $pesan)) : (string) $pesan;
            $bagian[] = is_string($field) ? $field.': '.$teks : $teks;
        }

        /* Dipotong: `Pesan_Gagal` punya batas kolom, dan 400 baris yang sama tidak perlu
           membawa seluruh daftar field berkali-kali. */
        return mb_substr(implode(' | ', $bagian), 0, 400);
    }
}
