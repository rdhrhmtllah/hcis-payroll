<?php

namespace App\Http\Controllers\SYSTEM\Authentication\Web;

use App\Http\Controllers\Controller;
use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use RealRashid\SweetAlert\Facades\Alert;

class AuthController extends Controller
{
    public function login()
    {
        $appEnv = strtolower((string) config('services.project_config.app_env'));
        $appversion = config('services.project_config.app_version', '3.0.0');

        /* Sitekey hanya dikirim bila captcha memang diverifikasi. Di luar produksi
           prosesLogin() melewati verifikasi, jadi merender widget di sana cuma
           membuatnya gagal dan retry tanpa henti pada domain yang tak terdaftar. */
        $captchaEnabled = $appEnv === 'production';

        return Inertia::render('SYSTEM/Authentication/Login', [
            'captchaEnabled' => $captchaEnabled,
            'turnstileSiteKey' => $captchaEnabled ? config('services.turnstile.sitekey') : '',
            'version' => $appversion,
        ]);
    }

    public function prosesLogin(Request $request)
    {
        // dd(env('SALT_FRONT').$request->input('password').env('SALT_BACK'));
        try {
            $app_env = strtolower(config('services.project_config.app_env'));

            if ($app_env == 'production') {
                if (! $this->verifyCloudflareCaptcha($request)) {
                    Session::flash('error', 'Tolong tunggu security check selesai!');

                    return back()
                        ->withErrors([
                            'captcha' => 'Please complete the security check',
                        ])
                        ->withInput($request->except('password'));
                }
            }

            $userLogin = User::with('karyawan')->where('Username', $request->username)->first();

            if (! $userLogin) {
                Session::flash('error', 'Username tidak ditemukan!');

                return redirect()->back()->withInput($request->except('password'));
            }

            if ($app_env == 'production') {
                if (! Hash::check(env('SALT_FRONT').$request->password.env('SALT_BACK'), $userLogin->Password)) {
                    Session::flash('error', 'Username atau password salah!');

                    return redirect()->back()->withInput($request->except('password'));
                }
            } else {
                $salted = env('SALT_FRONT').$request->password.env('SALT_BACK');
                $match = Hash::check($salted, $userLogin->Password)
                    || Hash::check($request->password, $userLogin->Password)
                    || $userLogin->Password === $request->password;

                if (! $match) {
                    Session::flash('error', 'Password salah!');

                    return redirect()->back()->withInput($request->except('password'));
                }
            }

            $statusAktifKaryawan = strtoupper(trim((string) optional($userLogin->karyawan)->Aktif));

            if ($statusAktifKaryawan !== 'Y') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                Session::flash('error', 'Akun karyawan tidak aktif.');

                return redirect()->back()->withInput($request->except('password'));
            }

            Auth::login($userLogin);
            $request->session()->regenerate();

            User::where('Username', $userLogin->Username)->update([
                'angka_login' => DB::raw('COALESCE(angka_login, 0) + 1'),
            ]);

            Session::flash('success', 'Welcome back!');

            return redirect()->intended('/dashboard');
        } catch (\Throwable $e) {
            Log::channel('loginLog')->error('Terjadi kesalahan Login : '.$e->getMessage());
            Session::flash('error', 'Terjadi Error saat login!');

            return back();
        }
    }

    public function loginRe()
    {
        return redirect('/login');
    }

    private function verifyCloudflareCaptcha(Request $request): bool
    {
        $captchaResponse = $request->input('cf-turnstile-response');

        if (! $captchaResponse) {
            return false;
        }

        try {
            $client = new Client;
            $response = $client->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'form_params' => [
                    'secret' => config('services.turnstile.secret'),
                    'response' => $captchaResponse,
                    'remoteip' => $request->ip(),
                ],
                // Cegah request login menggantung bila jaringan ke Cloudflare lambat.
                'connect_timeout' => 3,
                'timeout' => 5,
            ]);

            $body = json_decode($response->getBody(), true);

            return $body['success'] ?? false;
        } catch (\Exception $e) {
            \Log::error('Cloudflare Turnstile Verification Failed: '.$e->getMessage());

            return false;
        }
    }

    public function reset()
    {
        $title = 'Reset Password';

        return view('resetPass', ['title' => $title]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'oldPass' => 'required',
            'newPass' => 'required|min:6',
        ]);

        try {
            DB::beginTransaction();

            // Cek password lama
            if (! Hash::check(env('SALT_FRONT').$request->oldPass.env('SALT_BACK'), $request->user()->Password)) {
                DB::rollBack();

                return response()->json(
                    [
                        'status' => 'error',
                        'message' => 'Password lama salah!',
                    ],
                    422,
                ); // 422 Unprocessable Entity (umum untuk validasi)
            }

            // Update password
            $request->user()->update([
                'Password' => Hash::make(env('SALT_FRONT').$request->newPass.env('SALT_BACK')),
            ]);

            DB::commit();

            return response()->json(
                [
                    'status' => 200,
                    'message' => 'Password berhasil diperbarui',
                ],
                200,
            );
        } catch (\Throwable $error) {
            DB::rollBack();

            Log::channel('loginLog')->error('Error ubah password resetPass', [
                'pesan' => $error->getMessage(),
            ]);

            return response()->json(
                [
                    'status' => 'error',
                    'message' => 'Terjadi kesalahan, silakan coba lagi',
                ],
                500,
            );
        }
    }

    public function changeData(Request $request)
    {
        // dd($request->all());
        $validation = $request->validate([
            'HP' => [
                'required',
                'min:12', // Minimal 12 digit (628 + 9 digit)
                'max:14', // Maksimal 14 digit (misal 628xxxxxxxxxx) - sesuaikan jika ada kemungkinan lebih panjang
                'regex:/^628[0-9]{8,11}$/', // Dimulai dengan 628, lalu 8 hingga 11 digit angka
            ],
        ]);
        DB::beginTransaction();
        try {
            $user = Karyawan::where('UserID_Web', $request->Id_Users)->first();

            // $isNamaChange = $validation['Nama'] !== $user->Nama;
            $isNo_HPChange = $validation['HP'] !== $user->No_Hp;

            if (! $isNo_HPChange) {
                Alert::Error('error', 'Tidak ada perubahan pada data anda!');
                DB::rollBack();

                return back();
            } else {
                Karyawan::where('UserID_Web', $request->Id_Users)->update($validation);
                Alert::Success('Success', 'Case Berhasil Disimpan');
                DB::commit();

                return back();
            }
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::channel('loginLog')->info('changeData Error', [
                'pesan' => $th->getMessage(),
            ]);
            Alert::Error('Error', 'Terjadi Kesalahan!');

            return back();
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        return redirect()->route('login');
    }
}
