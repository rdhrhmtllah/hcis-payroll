<?php

namespace App\Http\Middleware;

use App\Helpers\ResponseHelper;
use App\Models\ESS\Employee\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang callback Performance Appraisal.
 *
 * ------------------------------------------------------------------------------------------
 * KENAPA PINTU SENDIRI, BUKAN MENUMPANG LmsApiGuard
 * ------------------------------------------------------------------------------------------
 *
 * Sampai gerbang ini ada, HC Learn memakai SATU alamat callback untuk Training, SOP Kuis, dan
 * PA. Asal sebuah kiriman karena itu tidak bisa dibaca dari alamatnya, dan LmsApiGuard harus
 * menebaknya dari token: tiga kueri ke tiga tabel integrasi pada SETIAP callback, ditambah
 * satu cabang `CALLBACK_TOKEN_AMBIGU` untuk token yang cocok di lebih dari satu modul — yang
 * dimenangkan LMS "demi kompatibilitas". Cabang itu ada karena penulisnya tahu tebakan ini
 * bisa salah.
 *
 * Di atasnya masih ada satu lagi: penyaring bersama menuntut `Score`, dan penilaian 360 tidak
 * pernah punya satu angka akhir. Controller sampai harus mencabang ke PA SEBELUM validasi,
 * karena kalau tidak, kiriman PA yang sah ditolak dengan pesan tentang field yang memang tak
 * pernah diminta darinya. Setiap modul berikutnya menambah cabang di DUA tempat sekaligus.
 *
 * Alamat sendiri menghapus seluruh tebakan itu. Yang dipertahankan justru bagian yang tidak
 * boleh berbeda: seluruh separuh keamanannya disalin apa adanya dari LmsApiGuard — urutan
 * pemeriksaan, string kanonik, algoritma, toleransi waktu. Bagi HC Learn ini URL berbeda
 * dengan kode yang sama persis.
 *
 * ------------------------------------------------------------------------------------------
 * KENAPA NONCE DAN LOG TETAP DI TABEL N_LMS_*
 * ------------------------------------------------------------------------------------------
 *
 * Bukan karena malas memisahkan. Nonce adalah RUANG BERSAMA: kalau PA punya tabel nonce
 * sendiri, sebuah permintaan bertanda tangan sah yang sudah dipakai di gerbang LMS masih
 * bisa diputar ulang ke gerbang PA — dan sebaliknya. Dua ruang nonce membuat perlindungan
 * replay lebih lemah daripada satu, bukan lebih kuat.
 *
 * Tabelnya juga bukan data bisnis LMS melainkan perkakas gerbang API, dan SOP sudah lebih
 * dulu menulisinya lewat jalur yang sama. Kredensial aplikasinya pun satu: memaksa HC Learn
 * memegang kunci kedua hanya untuk alamat kedua adalah beban tanpa hasil.
 */
class PaApiGuard
{
    /** Selisih waktu yang masih diterima, dalam detik. Sama dengan LmsApiGuard. */
    private const TOLERANSI_DETIK = 60;

    public function handle(Request $request, Closure $next): Response
    {
        try {
            if (config('app.env') === 'production' && ! $request->secure()) {
                return ResponseHelper::error('HTTPS Required', 403);
            }

            $publicKey = $request->header('X-HC-Public');
            $signature = $request->header('X-HC-Signature');
            $timestamp = $request->header('X-HC-Timestamp');
            $nonce = $request->header('X-HC-Nonce');
            $bodyHash = $request->header('X-HC-Body-Hash');

            if (! $publicKey || ! $signature || ! $timestamp || ! $nonce || ! $bodyHash) {
                return ResponseHelper::error('Security headers incomplete', 401);
            }

            if (abs(time() - strtotime($timestamp)) > self::TOLERANSI_DETIK) {
                return ResponseHelper::error('Request expired', 401);
            }

            $app = DB::table('N_LMS_Api_Aplikasi')
                ->where('Api_Public', $publicKey)
                ->where('Flag_Aktif', 'Y')
                ->first();

            if (! $app) {
                return ResponseHelper::error('Unauthorized application', 401);
            }

            if (DB::table('N_LMS_Api_Nonces')->where('Nonce', $nonce)->exists()) {
                return ResponseHelper::error('Duplicate request', 401);
            }

            $method = strtoupper($request->method());
            $path = '/'.ltrim($request->route()->uri(), '/');
            $query = $request->getQueryString() ?? '';
            $body = $request->getContent() ?? '';

            if (hash('sha256', $body) !== $bodyHash) {
                return response()->json(['message' => 'Body hash invalid'], 401);
            }

            $payloadTandaTangan = implode("\n", [
                $method, $path, $query, $timestamp, $nonce, $bodyHash, $publicKey,
            ]);

            if (! hash_equals(hash_hmac('sha512', $payloadTandaTangan, $app->Api_Secret), $signature)) {
                $this->logAttempt($app->Id_Api_Aplikasi, $path, 401, 'T');

                return ResponseHelper::error('Invalid signature', 401);
            }

            $isWhitelisted = DB::table('N_LMS_Akses_Endpoint as ae')
                ->join('N_LMS_Endpoint_Api as ep', 'ep.Id_Endpoint_Api', '=', 'ae.Id_Endpoint_Api')
                ->where('ae.Id_Api_Aplikasi', $app->Id_Api_Aplikasi)
                ->where('ep.Url_Route', $path)
                ->where('ep.Method', $method)
                ->where('ep.Flag_Aktif', 'Y')
                ->where('ae.Flag_Aktif', 'Y')
                ->exists();

            if (! $isWhitelisted) {
                $this->logAttempt($app->Id_Api_Aplikasi, $path, 403, 'Y');

                return ResponseHelper::error('Endpoint not authorized for this application', 403);
            }

            // ── Identitas kiriman ──────────────────────────────────────────────
            $bodyData = json_decode($body, true);
            $bodyData = is_array($bodyData) ? $bodyData : [];

            $kodeKaryawan = isset($bodyData['Kode_Karyawan'])
                ? strtoupper(trim((string) $bodyData['Kode_Karyawan']))
                : null;
            /* `Api_Reference` LEBIH DULU, `Id_Ujian_Token` sebagai cadangan.
               |
               | Kontrak callback final HC Learn menyatakan id numerik SENGAJA tidak pernah
               | dikirim — bilangan berurutan bisa ditebak, dan memegang satu berarti bisa
               | mencoba tetangganya, yang tak lain hasil penilaian karyawan lain. Yang datang
               | hanya `Api_Reference`, 32 heksadesimal acak.
               |
               | Sampai 10 September 2026 gerbang ini menuntut `Id_Ujian_Token`, sehingga
               | SETIAP kiriman sungguhan dijawab 422 atas field yang tak pernah ada.
               | Cadangannya dipertahankan untuk simulator pengisian lokal dan data lama. */
            $pengenal = $bodyData['Api_Reference'] ?? $bodyData['Id_Ujian_Token'] ?? null;
            $pengenal = $pengenal === null ? null : trim((string) $pengenal);

            /* Namanya `Id_Integrasi` — sama dengan yang HCIS kirim saat menjadwalkan, supaya
               tidak ada penggantian nama di tengah jalan. `Id_Penjadwalan_Integrasi` tetap
               diterima karena klien callback HC Learn sudah terlanjur mengisi field itu untuk
               Training dan SOP; memaksa mereka mengganti nama menambah pekerjaan tanpa
               menambah kejelasan apa pun. */
            $idIntegrasi = $bodyData['Id_Integrasi'] ?? $bodyData['Id_Penjadwalan_Integrasi'] ?? null;

            foreach ([
                'Kode_Karyawan' => $kodeKaryawan,
                'Api_Reference' => $pengenal,
                'Id_Integrasi' => $idIntegrasi,
            ] as $nama => $nilai) {
                if ($nilai === null || $nilai === '') {
                    $this->logAttempt($app->Id_Api_Aplikasi, $path, 422, 'Y');

                    return ResponseHelper::error("{$nama} is required in body", 422);
                }
            }

            /* SATU tabel, bukan tiga. Alamat ini hanya melayani PA, jadi tidak ada yang perlu
               ditebak — dan tidak ada kemungkinan token milik modul lain diam-diam diterima
               di sini. */
            $cari = fn (string $kolom) => DB::table('N_PA_Assignment_Integration as i')
                ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
                ->where("i.{$kolom}", $pengenal)
                ->where('i.Flag_Aktif', 'Y')
                ->orderByDesc('i.Id_PA_Assignment_Integration')
                ->first([
                    'i.Id_PA_Assignment_Integration', 'i.PA_Assignment_Id',
                    'i.PA_Cycle_Question_Package_Id',
                    'a.Kode_Karyawan_Penilai', 'a.Status_Penugasan',
                ]);

            /* Api_Ref dicoba lebih dulu: itu jalur production. Mendahulukan token membuat
               kiriman sungguhan bisa mendarat di baris simulasi bila nilainya kebetulan
               bertabrakan — keduanya kolom teks pada tabel yang sama. */
            $integrasi = $cari('Api_Ref') ?? $cari('Id_Ujian_Token');

            if (! $integrasi) {
                $this->logAttempt($app->Id_Api_Aplikasi, $path, 404, 'Y');

                return ResponseHelper::error('Api_Reference tidak dikenali di Performance Appraisal', 404);
            }

            /* KEPEMILIKAN DIPERIKSA — jalur lama tidak memeriksanya sama sekali.
               Di pintu bersama, kiriman PA hanya dicari lewat token; siapa pun yang memegang
               kredensial integrasi bisa mengirim penilaian atas nama penilai mana pun asal
               tokennya benar. Token memang sudah mewakili satu penugasan, tetapi kiriman yang
               menyebut penilai LAIN adalah tanda ada yang keliru di seberang — dan hasil
               penilaian bukan tempat untuk memaafkan ketidakcocokan identitas diam-diam. */
            $penilai = strtoupper(trim((string) $integrasi->Kode_Karyawan_Penilai));

            if ($penilai !== $kodeKaryawan) {
                $this->logAttempt($app->Id_Api_Aplikasi, $path, 403, 'Y');

                return ResponseHelper::error(
                    'Kode_Karyawan tidak cocok dengan penilai pemilik token ini',
                    403,
                );
            }

            if ((string) $idIntegrasi !== (string) $integrasi->Id_PA_Assignment_Integration) {
                $this->logAttempt($app->Id_Api_Aplikasi, $path, 403, 'Y');

                return ResponseHelper::error(
                    'Id_Integrasi tidak cocok dengan token yang dikirim',
                    403,
                );
            }

            /* Pengguna web-nya diresolusi SEBISANYA, dan ketiadaannya BUKAN alasan menolak —
               di sinilah gerbang ini sengaja berbeda dari LmsApiGuard, yang menjawab 422 bila
               penilai tak punya akun web. Yang sedang dikirim adalah penilaian yang sudah
               terlanjur dikerjakan orang; membuangnya karena akun webnya belum dibuat berarti
               menghapus pekerjaan yang tidak bisa diminta ulang. Id-nya hanya dipakai sebagai
               jejak "disimpan oleh siapa", dan jejak yang kosong masih lebih baik daripada
               penilaian yang hilang. */
            $userId = null;
            $karyawan = DB::table('Karyawan')
                ->whereRaw('UPPER(LTRIM(RTRIM(Kode_Karyawan))) = ?', [$kodeKaryawan])
                ->first(['UserID_Web']);

            if ($karyawan && ! empty($karyawan->UserID_Web)) {
                $user = User::find($karyawan->UserID_Web);

                if ($user) {
                    Auth::setUser($user);
                    $request->setUserResolver(fn () => $user);
                    $userId = $user->getAuthIdentifier();
                }
            }

            $request->attributes->set('pa_integrasi', $integrasi);
            $request->attributes->set('pa_kode_karyawan', $kodeKaryawan);
            $request->attributes->set('pa_user_id', $userId);

            DB::table('N_LMS_Api_Nonces')->insert([
                'Id_Api_Aplikasi' => $app->Id_Api_Aplikasi,
                'Nonce' => $nonce,
                'Expired_At' => now()->addMinutes(5),
            ]);

            $this->logAttempt($app->Id_Api_Aplikasi, $path, 200, 'Y');
            Log::info('CALLBACK_MODUL', ['modul' => 'PA', 'api_reference' => $pengenal]);

            return $next($request);
        } catch (\Throwable $e) {
            Log::error('PA_API_GUARD_ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ResponseHelper::error('Security System Failure', 500);
        }
    }

    private function logAttempt(int $appId, string $path, int $code, string $valid): void
    {
        DB::table('N_LMS_Api_Logs')->insert([
            'Id_Api_Aplikasi' => $appId,
            'Endpoint' => $path,
            'Status_Code' => $code,
            'Signature_Valid' => $valid,
            'Created_At' => now(),
        ]);
    }
}
