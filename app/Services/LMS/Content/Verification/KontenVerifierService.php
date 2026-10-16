<?php

namespace App\Services\LMS\Content\Verification;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Verifikasi berkas yang sudah berada di object storage.
 *
 * ATURAN KERAS: verifikasiCepat() berjalan DI DALAM request, jadi ia hanya
 * boleh menyentuh metadata + beberapa KB pertama. Membaca berkas penuh di
 * sini berarti satu unggahan 50 MB menahan satu worker selama pemeriksaan —
 * pola yang persis membuat "Login timeout expired" di modul lain.
 *
 * Pemeriksaan yang menuntut berkas penuh (struktur zip OOXML, makro, virus)
 * dikerjakan verifikasiDalam() — dan itu berjalan di worker Cloud Task,
 * bukan di jalur permintaan.
 */
class KontenVerifierService
{
    /** Disk tempat unggahan mendarat sebelum lolos verifikasi. */
    public const DISK_KARANTINA = 'gcs_karantina';

    /** Disk penyajian — hanya berkas yang sudah lolos yang boleh ada di sini. */
    public const DISK_SAJI = 'gcs';

    /**
     * Rasio dekompresi maksimum sebuah zip. Dokumen Office nyata jarang
     * melewati 20x; angka 100 memberi ruang lebar sambil tetap menangkap
     * "zip bomb" yang mekar ribuan kali lipat.
     */
    private const RASIO_ZIP_MAKS = 100;

    /** Batas total hasil ekstraksi, jaring kedua untuk zip bomb. */
    private const EKSTRAK_MAKS_BYTE = 500 * 1048576;

    /** Batas piksel gambar — di atas ini, mendekodenya sendiri sudah berbahaya. */
    private const PIKSEL_MAKS = 100_000_000;

    private const DIMENSI_MAKS = 20000;

    /**
     * Berkas di dalam OOXML yang WAJIB ada, per ekstensi. Inilah yang
     * membedakan docx/xlsx/pptx satu sama lain — dan membedakan ketiganya
     * dari .jar atau .apk yang cuma diganti namanya. Byte awalnya identik
     * (semuanya zip), jadi pembedaan ini mustahil dilakukan verifikasiCepat().
     */
    private const PENANDA_OOXML = [
        'docx' => 'word/document.xml',
        'xlsx' => 'xl/workbook.xml',
        'pptx' => 'ppt/presentation.xml',
    ];

    /**
     * Jenis relasi OOXML yang membuat aplikasi Office MENARIK LALU MENJALANKAN
     * sesuatu dari alamat luar saat dokumennya dibuka.
     *
     * Ini jalur serangan dokumen Office yang paling lazim SETELAH makro dilarang:
     * dokumennya sendiri bersih — tak ada vbaProject.bin, strukturnya sempurna —
     * tapi settings.xml.rels menunjuk template .dotm di server penyerang, dan
     * makronya baru ikut saat dokumen dibuka. Pemeriksaan makro tak melihatnya
     * karena memang tak ada makro di dalam berkasnya.
     *
     * Yang SENGAJA tidak masuk daftar: 'hyperlink'. Tautan http di dalam dokumen
     * adalah hal normal dan hanya terbuka bila diklik manusia.
     */
    private const RELASI_TARIK_LUAR = [
        'attachedtemplate' => 'template',
        'oleobject' => 'objek tertanam',
        'frame' => 'bingkai dokumen',
        'subdocument' => 'sub-dokumen',
        'xlexternallinkpath' => 'tautan data',
    ];

    /** Batas baca per entri saat memeriksa isi bagian OOXML. */
    private const ENTRI_MAKS_BYTE = 2 * 1048576;

    /**
     * Nama objek PDF yang ditolak. Dicocokkan sebagai NAMA UTUH setelah escape
     * heksadesimalnya diterjemahkan, bukan sebagai potongan teks.
     */
    private const PDF_NAMA_TERLARANG = [
        'JavaScript' => 'PDF memuat JavaScript. Cetak ulang dokumen ke PDF tanpa formulir aktif.',
        'Launch' => 'PDF memuat perintah menjalankan program.',
        'EmbeddedFile' => 'PDF memuat berkas lampiran tertanam. Kirim lampirannya sebagai konten terpisah.',
        'EmbeddedFiles' => 'PDF memuat berkas lampiran tertanam. Kirim lampirannya sebagai konten terpisah.',
        'Encrypt' => 'PDF terkunci sandi sehingga tidak dapat dirender.',
        'RichMedia' => 'PDF memuat konten multimedia aktif.',
        'SubmitForm' => 'PDF memuat formulir yang mengirim data ke alamat luar.',
        'GoToR' => 'PDF merujuk berkas di luar dokumen.',
        'ImportData' => 'PDF menarik data dari berkas luar.',
    ];

    /**
     * Nama aksi yang membuat /OpenAction berbahaya.
     *
     * /OpenAction SENDIRI tidak berbahaya dan sengaja tidak lagi ditolak begitu
     * saja: dari 37 kemunculannya pada 995 PDF nyata yang diuji, 35 hanya
     * mengatur zoom halaman pertama (`[3 0 R /FitH null]`) dan 2 menunjuk
     * /S /GoTo. Nol yang menjalankan sesuatu. Menolaknya tanpa melihat isinya
     * berarti membuang 3,6% dokumen yang sehat.
     *
     * Yang diperiksa adalah AKSI yang menempel padanya. Karena jangkauannya
     * terkunci beberapa ratus byte sesudah sebuah nama panjang yang langka,
     * singkatan /JS aman dipakai di sini — hal yang mustahil dilakukan pada
     * pemindaian seluruh berkas.
     */
    private const PDF_AKSI_EKSEKUSI = ['JS', 'JavaScript', 'Launch', 'ImportData', 'SubmitForm', 'GoToR', 'Rendition', 'Movie', 'URI'];

    /** Jangkauan penelusuran isi /OpenAction, sekaligus besar tumpang-tindih antar blok. */
    private const PDF_JENDELA_AKSI = 512;

    /**
     * Periksa objek yang baru diunggah: ukuran nyata + tanda tangan byte awal.
     *
     * Yang dijamin method ini: berkasnya ada, ukurannya benar, dan byte
     * awalnya konsisten dengan ekstensi yang diterbitkan servernya sendiri.
     * Yang TIDAK dijaminnya: docx/xlsx/pptx sama-sama zip, jadi ketiganya tak
     * terbedakan di sini — begitu pula .jar yang dinamai .docx.
     *
     * @return array{ok:bool, alasan:?string, ukuran:int, mime:?string}
     */
    public function verifikasiCepat(string $path, string $ext, string $disk = 'gcs', string $scope = 'LMS'): array
    {
        $p = KontenPolicy::untukExt($scope, $ext);
        if (! $p) {
            return $this->gagal('Format berkas tidak diizinkan.');
        }

        $storage = Storage::disk($disk);

        try {
            if (! $storage->exists($path)) {
                return $this->gagal('Unggahan tidak selesai — berkasnya tidak ditemukan di penyimpanan.');
            }

            $ukuran = (int) $storage->size($path);
        } catch (\Throwable $e) {
            Log::error('KontenVerifier: gagal membaca metadata objek', [
                'path' => $path, 'pesan' => $e->getMessage(),
            ]);

            return $this->gagal('Berkas tidak dapat diperiksa. Coba unggah ulang.');
        }

        if ($ukuran <= 0) {
            return $this->gagal('Berkas kosong.');
        }

        // Batas ukuran sudah ditegakkan GCS lewat x-goog-content-length-range
        // yang ikut ditandatangani. Pemeriksaan ini adalah lapis kedua: ia
        // menangkap keadaan saat header itu gagal terpasang (mis. CORS bucket
        // belum diperbarui) alih-alih membiarkannya lolos diam-diam.
        if ($ukuran > $p['maks']) {
            return $this->gagal(sprintf(
                'Ukuran berkas %s melebihi batas %s untuk %s.',
                $this->byteTerbaca($ukuran),
                $this->byteTerbaca($p['maks']),
                $p['label'],
            ), $ukuran);
        }

        $head = $this->bacaHead($storage, $path);
        if ($head === null) {
            return $this->gagal('Isi berkas tidak dapat dibaca. Coba unggah ulang.', $ukuran);
        }

        if ($alasan = KontenPolicy::deteksiBerbahaya($head)) {
            return $this->gagal($alasan, $ukuran);
        }

        if ($alasan = KontenPolicy::cocokMagic($head, $ext)) {
            return $this->gagal($alasan, $ukuran);
        }

        return [
            'ok' => true,
            'alasan' => null,
            'ukuran' => $ukuran,
            'mime' => $p['mime'],
        ];
    }

    /**
     * Pemeriksaan mendalam — menuntut SELURUH isi berkas, jadi hanya boleh
     * dipanggil dari worker, tidak pernah dari jalur permintaan.
     *
     * Di sinilah docx dibedakan dari xlsx dan dari .jar yang cuma diganti
     * namanya: byte awal ketiganya identik (semuanya zip), sehingga daftar isi
     * arsipnya — yang letaknya di AKHIR berkas — adalah satu-satunya pembeda.
     *
     * Membedakan DUA kegagalan yang sama sekali berlainan lewat `terjangkau`:
     *
     *   terjangkau=false — objeknya tak bisa diambil (hilang, GCS sedang
     *                      bermasalah, kredensial salah). Ini persoalan
     *                      infrastruktur; berkasnya TIDAK boleh dicap DITOLAK.
     *                      Satu gangguan GCS sesaat kalau tidak dibedakan akan
     *                      menandai konten produksi yang sehat sebagai ditolak.
     *   terjangkau=true   — isinya sudah dibaca dan memang tak lolos.
     *
     * @return array{ok:bool, alasan:?string, terjangkau:bool}
     */
    public function verifikasiDalam(string $path, string $ext, string $disk = self::DISK_KARANTINA): array
    {
        $ext = strtolower($ext);
        $tmp = $this->unduhSementara($path, $disk);

        if ($tmp === null) {
            return [
                'ok' => false,
                'terjangkau' => false,
                'alasan' => 'Berkas tidak dapat diambil dari penyimpanan untuk diperiksa.',
            ];
        }

        try {
            $alasan = match (true) {
                isset(self::PENANDA_OOXML[$ext]) => $this->periksaOoxml($tmp, $ext),
                $ext === 'pdf' => $this->periksaPdf($tmp),
                in_array($ext, ['jpg', 'jpeg', 'png'], true) => $this->periksaGambar($tmp, $ext),
                default => null, // format tanpa struktur yang bisa diperiksa
            };

            return ['ok' => $alasan === null, 'alasan' => $alasan, 'terjangkau' => true];
        } catch (\Throwable $e) {
            Log::error('KontenVerifier: verifikasi dalam meledak', [
                'path' => $path, 'ext' => $ext, 'pesan' => $e->getMessage(),
            ]);

            // Berkasnya SUDAH terbaca — yang meledak adalah pemeriksa saat
            // membongkarnya. Itu properti berkasnya, bukan gangguan sesaat, jadi
            // ia ditolak dan bukan diulang selamanya.
            return [
                'ok' => false,
                'terjangkau' => true,
                'alasan' => 'Berkas tidak dapat diperiksa strukturnya.',
            ];
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Pindai objek dengan antivirus.
     *
     * Lapisan untuk berkas yang FORMATNYA SAH tapi isinya jahat — yang tak bisa
     * ditangkap allowlist maupun pemeriksaan struktur, karena docx ber-malware
     * tetap docx yang sempurna secara struktur.
     *
     * Objeknya TIDAK diunduh di sini. Yang dikirim adalah signed GET URL, dan
     * layanan pemindai yang menariknya sendiri: byte-nya melintas sekali
     * (GCS→pemindai) alih-alih dua kali (GCS→Laravel→pemindai), dan proses ini
     * tak perlu menahan berkas 50 MB di memori sekadar jadi perantara.
     *
     * @return array{status:'BERSIH'|'KOTOR'|'TAK_TERSEDIA', tanda_tangan:?string, alasan:?string}
     */
    public function pindaiVirus(string $path, string $disk = self::DISK_KARANTINA): array
    {
        $cfg = config('services.konten.av');
        $url = trim((string) ($cfg['url'] ?? ''));

        if ($url === '') {
            return $this->avTakTersedia('Layanan pemindai belum dikonfigurasi (KONTEN_AV_URL kosong).');
        }

        try {
            $signed = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15));
        } catch (\Throwable $e) {
            return $this->avTakTersedia('Gagal membuat URL objek untuk dipindai: '.$e->getMessage());
        }

        try {
            $token = $this->tokenOidc($url);
        } catch (\Throwable $e) {
            Log::error('KontenVerifier: gagal membuat token OIDC untuk pemindai', ['pesan' => $e->getMessage()]);

            return $this->avTakTersedia('Gagal mengautentikasi ke layanan pemindai.');
        }

        try {
            $res = Http::withToken($token)
                ->timeout((int) ($cfg['timeout'] ?? 180))
                ->acceptJson()
                ->post(rtrim($url, '/').'/scan', [
                    'url' => $signed,
                    'maks_byte' => (int) ($cfg['maks_byte'] ?? 50 * 1048576),
                ]);
        } catch (\Throwable $e) {
            return $this->avTakTersedia('Layanan pemindai tidak terjangkau: '.$e->getMessage());
        }

        if (! $res->successful()) {
            // Termasuk 503 dari pemindai saat clamd belum siap. Ini BUKAN
            // "bersih" — pemindai yang tak bisa memindai harus terlihat gagal.
            return $this->avTakTersedia('Layanan pemindai menjawab HTTP '.$res->status().'.');
        }

        $data = $res->json();

        if (! array_key_exists('bersih', (array) $data)) {
            return $this->avTakTersedia('Jawaban layanan pemindai tidak dikenali.');
        }

        if ($data['bersih'] === true) {
            return ['status' => 'BERSIH', 'tanda_tangan' => null, 'alasan' => null];
        }

        $tanda = (string) ($data['tanda_tangan'] ?? 'tidak diketahui');

        return [
            'status' => 'KOTOR',
            'tanda_tangan' => $tanda,
            'alasan' => "Berkas terdeteksi mengandung program berbahaya ({$tanda}).",
        ];
    }

    /**
     * Token identitas untuk memanggil layanan Cloud Run lain.
     *
     * Memakai OIDC service-account, bukan token bersama di env: token bersama
     * adalah rahasia tambahan yang harus dirotasi dan bisa bocor lewat log,
     * sementara identitas Cloud Run sudah divalidasi platformnya di depan
     * kontainer sebelum permintaan sampai ke aplikasi.
     */
    private function tokenOidc(string $audience): string
    {
        $keyPath = config('filesystems.disks.gcs.key_file_path');
        $keyRaw = config('filesystems.disks.gcs.key_file');

        $jsonKey = null;
        if (! empty($keyPath) && is_file($keyPath)) {
            $jsonKey = json_decode((string) file_get_contents($keyPath), true);
        } elseif (! empty($keyRaw)) {
            $jsonKey = is_string($keyRaw) ? json_decode($keyRaw, true) : $keyRaw;
        }

        if (is_array($jsonKey)) {
            $creds = new \Google\Auth\Credentials\ServiceAccountCredentials(null, $jsonKey, null, $audience);
        } else {
            // Di Cloud Run tanpa berkas kunci, metadata server yang menerbitkan.
            $creds = \Google\Auth\ApplicationDefaultCredentials::getIdTokenCredentials($audience);
        }

        $token = $creds->fetchAuthToken();

        if (empty($token['id_token'])) {
            throw new \RuntimeException('Token OIDC tidak diterbitkan.');
        }

        return $token['id_token'];
    }

    private function avTakTersedia(string $alasan): array
    {
        Log::warning('KontenVerifier: pemindaian antivirus tidak tersedia', ['alasan' => $alasan]);

        return ['status' => 'TAK_TERSEDIA', 'tanda_tangan' => null, 'alasan' => $alasan];
    }

    /**
     * Pindahkan objek dari karantina ke bucket penyajian.
     *
     * Memakai copy sisi-server GCS lewat klien nativenya, BUKAN readStream ke
     * writeStream lewat Laravel: yang terakhir menarik seluruh berkas masuk ke
     * aplikasi lalu mendorongnya keluar lagi — dua kali transfer untuk sesuatu
     * yang bisa dikerjakan GCS sendiri tanpa satu byte pun lewat sini.
     */
    public function promosikan(string $path): bool
    {
        try {
            $sumber = $this->bucketNative(self::DISK_KARANTINA);
            $tujuan = $this->bucketNative(self::DISK_SAJI);

            $sumber->object($path)->copy($tujuan, ['name' => $path]);
            $sumber->object($path)->delete();

            return true;
        } catch (\Throwable $e) {
            Log::error('KontenVerifier: gagal mempromosikan objek', [
                'path' => $path, 'pesan' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Hapus objek yang gagal verifikasi.
     *
     * Berkas yang ditolak TIDAK disimpan sebagai barang bukti: menyimpannya
     * berarti memelihara koleksi berkas berbahaya di bucket produksi. Yang
     * dicatat hanya alasannya, sebagai teks.
     */
    public function hapusObjek(string $path, string $disk = 'gcs'): void
    {
        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $e) {
            // Objek yatim jauh lebih ringan daripada permintaan yang gagal
            // karena pembersihan — biarkan penyapu/lifecycle yang mengurus.
            Log::warning('KontenVerifier: gagal menghapus objek yang ditolak', [
                'path' => $path, 'pesan' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Baca beberapa KB pertama saja. readStream menarik byte secara bertahap,
     * jadi fread sebanyak BYTE_HEAD tidak menyeret seluruh berkas.
     */
    private function bacaHead($storage, string $path): ?string
    {
        $h = null;
        try {
            $h = $storage->readStream($path);
            if (! is_resource($h)) {
                return null;
            }
            $head = fread($h, KontenPolicy::BYTE_HEAD);

            return $head === false ? null : $head;
        } catch (\Throwable $e) {
            Log::error('KontenVerifier: gagal membaca head objek', [
                'path' => $path, 'pesan' => $e->getMessage(),
            ]);

            return null;
        } finally {
            if (is_resource($h)) {
                fclose($h);
            }
        }
    }

    // ── Pemeriksaan per keluarga format ──────────────────────────────────

    /**
     * OOXML (docx/xlsx/pptx) sesungguhnya adalah arsip zip. Yang diperiksa:
     * arsipnya utuh, isinya sesuai ekstensi, tak memuat makro, tak memuat
     * entri ber-path berbahaya, dan tak mekar tak wajar saat dibuka.
     */
    private function periksaOoxml(string $berkas, string $ext): ?string
    {
        $zip = new \ZipArchive;

        // CHECKCONS memaksa ZipArchive memvalidasi konsistensi arsipnya, bukan
        // sekadar membuka apa yang bisa dibuka.
        if ($zip->open($berkas, \ZipArchive::CHECKCONS) !== true) {
            return 'Berkas bukan arsip Office yang sah.';
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false) {
                return 'Berkas tidak memiliki [Content_Types].xml — bukan dokumen Office.';
            }

            $penanda = self::PENANDA_OOXML[$ext];
            if ($zip->locateName($penanda) === false) {
                return "Isi berkas tidak sesuai ekstensi .{$ext} (tidak ditemukan {$penanda}).";
            }

            $totalAsli = 0;
            $totalTerkompres = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $s = $zip->statIndex($i);
                if ($s === false) {
                    return 'Arsip memuat entri yang tidak dapat dibaca.';
                }
                $nama = (string) $s['name'];

                // Entri yang keluar dari direktori tujuan saat diekstrak.
                if (str_contains($nama, '..') || str_starts_with($nama, '/') || preg_match('#^[A-Za-z]:#', $nama)) {
                    return 'Arsip memuat entri dengan path berbahaya.';
                }

                if (preg_match('#(^|/)vba(Project|Data)\.bin$#i', $nama)) {
                    return 'Berkas memuat makro. Simpan ulang tanpa makro.';
                }

                if (preg_match('#\.(exe|dll|scr|com|bat|cmd|js|vbs|ps1|jar|msi)$#i', $nama)) {
                    return 'Arsip memuat berkas yang dapat dieksekusi.';
                }

                $totalAsli += (int) $s['size'];
                $totalTerkompres += (int) $s['comp_size'];

                if ($totalAsli > self::EKSTRAK_MAKS_BYTE) {
                    return 'Berkas mekar terlalu besar saat dibuka (dugaan zip bomb).';
                }
            }

            if ($totalTerkompres > 0 && ($totalAsli / $totalTerkompres) > self::RASIO_ZIP_MAKS) {
                return 'Rasio kompresi berkas tidak wajar (dugaan zip bomb).';
            }

            // Baru SEKARANG isinya dibuka. Pemeriksaan di atas — path, ukuran,
            // rasio — sengaja mendahului: membaca isi arsip yang belum terbukti
            // waras adalah cara membuka zip bomb dengan tangan sendiri.
            return $this->periksaIsiOoxml($zip);
        } finally {
            $zip->close();
        }
    }

    /**
     * Isi bagian-bagian OOXML: apa yang ditarik dokumen ini dari luar saat dibuka.
     *
     * Tiga hal yang dicari, dan ketiganya lolos dari pemeriksaan makro karena
     * memang tak ada makro di dalam berkasnya:
     *
     *   relasi eksternal — template/objek yang ditarik dari server penyerang
     *                      (remote template injection)
     *   medan DDE        — perintah ke aplikasi lain, dieksekusi Word tanpa VBA
     *   objek tertanam   — program yang dibungkus sebagai OLE Package
     *
     * Ditambah DOCTYPE/ENTITY, yang tak pernah ada di OOXML sah dan merupakan
     * bahan baku serangan entitas XML terhadap apa pun yang membongkarnya.
     */
    private function periksaIsiOoxml(\ZipArchive $zip): ?string
    {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $s = $zip->statIndex($i);
            if ($s === false) {
                continue;
            }
            $nama = (string) $s['name'];

            if (str_ends_with(strtolower($nama), '.rels')) {
                $isi = $this->bacaEntri($zip, $nama);
                if ($isi !== null && ($alasan = $this->periksaRelasi($isi))) {
                    return $alasan;
                }

                continue;
            }

            if (str_ends_with(strtolower($nama), '.xml')) {
                $isi = $this->bacaEntri($zip, $nama);
                if ($isi !== null && ($alasan = $this->periksaBagianXml($isi))) {
                    return $alasan;
                }

                continue;
            }

            if (preg_match('#(^|/)embeddings/#i', $nama)) {
                $isi = $this->bacaEntri($zip, $nama);
                if ($isi !== null && ($alasan = $this->periksaObjekTertanam($isi))) {
                    return $alasan;
                }
            }
        }

        return null;
    }

    /**
     * Relasi ke alamat luar.
     *
     * Yang ditolak hanya target BER-JARINGAN. Target `file:///C:\...` sengaja
     * dibiarkan: hampir setiap dokumen yang lahir dari template perusahaan
     * membawa attachedTemplate ke Normal.dotm di komputer penulisnya, dan path
     * itu tak berarti apa-apa di komputer lain. Menolaknya berarti menolak
     * sebagian besar dokumen kantor yang sah.
     */
    private function periksaRelasi(string $isi): ?string
    {
        if (! preg_match_all('#<Relationship\b[^>]*>#i', $isi, $m)) {
            return null;
        }

        foreach ($m[0] as $tag) {
            if (! preg_match('#TargetMode\s*=\s*"External"#i', $tag)) {
                continue;
            }
            if (! preg_match('#Type\s*=\s*"[^"]*/([A-Za-z0-9]+)"#i', $tag, $t)) {
                continue;
            }

            $jenis = self::RELASI_TARIK_LUAR[strtolower($t[1])] ?? null;
            if ($jenis === null) {
                continue;
            }

            if (! preg_match('#Target\s*=\s*"([^"]*)"#i', $tag, $g)) {
                continue;
            }

            $target = trim(html_entity_decode($g[1], ENT_QUOTES | ENT_XML1));
            $jauh = str_starts_with($target, '\\\\')
                || (preg_match('#^([A-Za-z][A-Za-z0-9+.-]*):#', $target, $skema) && strtolower($skema[1]) !== 'file');

            if ($jauh) {
                return sprintf(
                    'Dokumen menarik %s dari alamat luar (%s) saat dibuka. Simpan ulang dokumen tanpa tautan eksternal.',
                    $jenis,
                    mb_strimwidth($target, 0, 80, '…'),
                );
            }
        }

        return null;
    }

    /** Isi satu bagian XML: medan DDE dan deklarasi entitas. */
    private function periksaBagianXml(string $isi): ?string
    {
        if (preg_match('#<!\s*(DOCTYPE|ENTITY)\b#i', $isi)) {
            return 'Berkas memuat deklarasi entitas XML yang tidak lazim pada dokumen Office.';
        }

        // DDEAUTO berjalan sendiri begitu dokumen dibuka; DDE biasa berjalan
        // setelah satu klik. Keduanya dicocokkan HANYA di dalam medan instruksi,
        // supaya kata "DDE" yang kebetulan tertulis di badan dokumen tak ikut
        // tertangkap.
        if (preg_match('#<w:instrText[^>]*>[^<]*\bDDE(AUTO)?\b#i', $isi)
            || preg_match('#w:instr\s*=\s*"[^"]*\bDDE(AUTO)?\b#i', $isi)) {
            return 'Dokumen memuat perintah DDE yang menjalankan aplikasi lain. Hapus medan tersebut lalu simpan ulang.';
        }

        return null;
    }

    /**
     * Objek OLE yang tertanam di dalam dokumen.
     *
     * Objek Excel/Word tertanam adalah hal wajar dan dibiarkan. Yang dicari
     * adalah OLE Package — pembungkus yang membawa berkas apa pun, termasuk
     * program — yang menuliskan nama berkas aslinya di awal aliran, dalam ASCII
     * maupun UTF-16.
     */
    private function periksaObjekTertanam(string $isi): ?string
    {
        $eksekutabel = '(exe|dll|scr|com|bat|cmd|vbs|js|jse|wsf|ps1|jar|msi|hta|lnk|reg|cpl)';
        $kepala = substr($isi, 0, 8192);

        foreach ([$kepala, str_replace("\0", '', $kepala)] as $cuplikan) {
            if (preg_match('#[\w\-. ]+\.'.$eksekutabel.'\b#i', $cuplikan, $n)) {
                return sprintf('Objek tertanam di dalam dokumen memuat program (%s).', mb_strimwidth(trim($n[0]), 0, 60, '…'));
            }
        }

        if (str_starts_with($isi, 'MZ') || str_contains($isi, 'This program cannot be run in DOS mode')) {
            return 'Objek tertanam di dalam dokumen berisi program yang dapat dijalankan.';
        }

        return null;
    }

    /** Baca satu entri arsip, dibatasi supaya entri raksasa tak ikut masuk memori. */
    private function bacaEntri(\ZipArchive $zip, string $nama): ?string
    {
        $h = @$zip->getStream($nama);
        if (! is_resource($h)) {
            return null;
        }

        try {
            $isi = stream_get_contents($h, self::ENTRI_MAKS_BYTE);

            return $isi === false ? null : $isi;
        } finally {
            fclose($h);
        }
    }

    /**
     * PDF: menolak yang memuat aksi otomatis, JavaScript, lampiran tertanam,
     * atau terenkripsi.
     *
     * Pencocokannya bukan pencarian teks biasa. Spesifikasi PDF mengizinkan
     * setiap huruf nama objek ditulis sebagai escape heksadesimal — /JavaScript
     * boleh ditulis /J#61vaScript, dan pembaca PDF memperlakukan keduanya sama.
     * Jadi nama objeknya diambil utuh dulu, escape-nya diterjemahkan, baru
     * dibandingkan. Pencarian teks polos akan dilewati begitu saja oleh trik itu.
     *
     * Yang TETAP tidak diperiksa, dan alasannya: token pendek seperti /JS dan
     * /AA. Pada berkas 50 MB, tiga byte tertentu muncul secara kebetulan di
     * dalam aliran terkompresi berkali-kali — menolak dokumen SOP asli karena
     * kebetulan itu jauh lebih merugikan daripada meloloskan PDF yang memakai
     * bentuk pendeknya. Lapisan penutupnya bukan pemeriksa ini, melainkan fakta
     * bahwa PDF item DOKUMEN dirender menjadi gambar per halaman di server,
     * sehingga tak pernah dieksekusi peramban karyawan.
     */
    private function periksaPdf(string $berkas): ?string
    {
        $h = @fopen($berkas, 'rb');
        if ($h === false) {
            return 'Isi PDF tidak dapat dibaca.';
        }

        try {
            $ekor = '';
            while (! feof($h)) {
                $blok = fread($h, 1048576);
                if ($blok === false) {
                    break;
                }

                // Ekor blok sebelumnya ikut disambung supaya nama yang terpotong
                // tepat di batas blok tetap terbaca utuh.
                $jendela = $ekor.$blok;

                if (preg_match_all('#/((?:[A-Za-z0-9]|\#[0-9A-Fa-f]{2})+)#', $jendela, $m, PREG_OFFSET_CAPTURE)) {
                    foreach ($m[1] as [$mentah, $posisi]) {
                        $nama = $this->namaPdf($mentah);

                        if (isset(self::PDF_NAMA_TERLARANG[$nama])) {
                            return self::PDF_NAMA_TERLARANG[$nama];
                        }

                        if ($nama === 'OpenAction' && ($aksi = $this->aksiOtomatisPdf($jendela, $posisi))) {
                            return "PDF menjalankan aksi otomatis saat dibuka (/{$aksi}).";
                        }
                    }
                }

                // Tumpang-tindih sebesar jendela aksi, supaya /OpenAction yang
                // muncul di ujung blok tetap punya isi untuk ditelusuri pada
                // putaran berikutnya — bukan diperiksa setengah lalu diloloskan.
                $ekor = substr($jendela, -self::PDF_JENDELA_AKSI);
            }

            return null;
        } finally {
            fclose($h);
        }
    }

    /** Terjemahkan escape heksadesimal sebuah nama objek PDF (/J#61vaScript → /JavaScript). */
    private function namaPdf(string $mentah): string
    {
        if (! str_contains($mentah, '#')) {
            return $mentah;
        }

        return (string) preg_replace_callback(
            '/\#([0-9A-Fa-f]{2})/',
            fn ($x) => chr(hexdec($x[1])),
            $mentah,
        );
    }

    /**
     * Apa yang dijalankan /OpenAction pada posisi ini — atau null bila ia hanya
     * menentukan tampilan halaman pertama, yang merupakan pemakaian normalnya.
     */
    private function aksiOtomatisPdf(string $jendela, int $posisi): ?string
    {
        // $posisi menunjuk tepat SESUDAH garis miring /OpenAction, jadi nama itu
        // sendiri sudah berada di luar jendela — tak ada yang perlu dilewati.
        $isi = substr($jendela, $posisi, self::PDF_JENDELA_AKSI);

        if (! preg_match_all('#/((?:[A-Za-z0-9]|\#[0-9A-Fa-f]{2})+)#', $isi, $m)) {
            return null;
        }

        foreach ($m[1] as $mentah) {
            $nama = $this->namaPdf($mentah);
            if (in_array($nama, self::PDF_AKSI_EKSEKUSI, true)) {
                return $nama;
            }
        }

        return null;
    }

    /**
     * Gambar: memastikan isinya benar-benar gambar dengan jenis yang sesuai
     * ekstensinya, dan ukurannya masuk akal.
     *
     * Batas dimensi bukan soal estetika: gambar 60000x60000 berukuran beberapa
     * ratus KB di disk akan menjadi belasan gigabyte begitu ada yang mencoba
     * mendekodenya — bom dekompresi yang menjatuhkan proses yang membukanya.
     */
    private function periksaGambar(string $berkas, string $ext): ?string
    {
        $info = @getimagesize($berkas);
        if ($info === false) {
            return 'Berkas bukan gambar yang dapat dibaca.';
        }

        [$lebar, $tinggi, $jenis] = $info;
        $diharapkan = $ext === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;

        if ($jenis !== $diharapkan) {
            return "Isi gambar tidak sesuai ekstensi .{$ext}.";
        }
        if ($lebar > self::DIMENSI_MAKS || $tinggi > self::DIMENSI_MAKS) {
            return "Dimensi gambar {$lebar}x{$tinggi} melampaui batas ".self::DIMENSI_MAKS.' piksel.';
        }
        if (($lebar * $tinggi) > self::PIKSEL_MAKS) {
            return 'Jumlah piksel gambar tidak wajar (dugaan bom dekompresi).';
        }

        return null;
    }

    // ── Utilitas ─────────────────────────────────────────────────────────

    /**
     * Salin objek ke berkas sementara. ZipArchive dan getimagesize menuntut
     * path lokal — keduanya tak bisa bekerja di atas stream jaringan.
     */
    private function unduhSementara(string $path, string $disk): ?string
    {
        $sumber = null;
        $tujuan = null;
        $tmp = tempnam(sys_get_temp_dir(), 'kv_');

        if ($tmp === false) {
            return null;
        }

        try {
            $sumber = Storage::disk($disk)->readStream($path);
            if (! is_resource($sumber)) {
                @unlink($tmp);

                return null;
            }
            $tujuan = fopen($tmp, 'wb');
            if ($tujuan === false) {
                @unlink($tmp);

                return null;
            }
            stream_copy_to_stream($sumber, $tujuan);

            return $tmp;
        } catch (\Throwable $e) {
            Log::error('KontenVerifier: gagal mengunduh objek untuk diperiksa', [
                'path' => $path, 'pesan' => $e->getMessage(),
            ]);
            @unlink($tmp);

            return null;
        } finally {
            if (is_resource($sumber)) {
                fclose($sumber);
            }
            if (is_resource($tujuan)) {
                fclose($tujuan);
            }
        }
    }

    /** Bucket lewat klien native GCS — untuk operasi yang tak ada di Flysystem. */
    private function bucketNative(string $disk): \Google\Cloud\Storage\Bucket
    {
        $cfg = config("filesystems.disks.{$disk}");
        $opsi = ['projectId' => $cfg['project_id'] ?? null];

        // Lokal memakai path berkas kunci; Cloud Run menyuntik JSON-nya sebagai string.
        if (! empty($cfg['key_file_path'])) {
            $opsi['keyFilePath'] = $cfg['key_file_path'];
        } elseif (! empty($cfg['key_file'])) {
            $opsi['keyFile'] = is_string($cfg['key_file'])
                ? json_decode($cfg['key_file'], true)
                : $cfg['key_file'];
        }

        return (new \Google\Cloud\Storage\StorageClient($opsi))->bucket($cfg['bucket']);
    }

    private function gagal(string $alasan, int $ukuran = 0): array
    {
        return ['ok' => false, 'alasan' => $alasan, 'ukuran' => $ukuran, 'mime' => null];
    }

    private function byteTerbaca(int $byte): string
    {
        if ($byte >= 1048576) {
            return round($byte / 1048576, 1).' MB';
        }

        return max(1, (int) round($byte / 1024)).' KB';
    }
}
