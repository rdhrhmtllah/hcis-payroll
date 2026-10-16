<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Models\ESS\Employee\Models\Karyawan;
use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\Uat\UatGerbang;
use Illuminate\Console\Command;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cetak cookie sesi untuk satu karyawan, agar peramban otomasi UAT bisa masuk
 * tanpa kata sandi.
 *
 * KENAPA PERINTAH, BUKAN RUTE. Cara lazim (dan yang dipakai Laravel Dusk) adalah
 * menambah endpoint HTTP yang bisa login sebagai siapa saja, digerbangi env.
 * Endpoint itu hidup selamanya di dalam aplikasi, dan satu-satunya yang
 * memisahkannya dari produksi adalah satu variabel lingkungan. Perintah artisan
 * tidak punya permukaan jaringan sama sekali: untuk memakainya orang butuh akses
 * shell ke mesinnya lebih dulu — dan pada titik itu ia sudah menguasai
 * segalanya, sehingga perintah ini tak menambah apa pun yang bisa direbut.
 *
 * KENAPA BUKAN KATA SANDI. Akun di basis data dev adalah salinan akun karyawan
 * sungguhan. Memakai kata sandinya untuk otomasi berarti memakai kata sandi
 * orang lain, dan itu tetap tak pantas sekalipun databasenya bukan produksi.
 *
 * Sesi yang dicetak berumur sependek setelan sesi aplikasi, dan hanya berlaku
 * pada mesin yang menyimpan berkas sesinya.
 */
class SopUatSesi extends Command
{
    protected $signature = 'sop:uat-sesi
        {nik : Kode_Karyawan yang akan diperankan}
        {--json : Cetak sebagai JSON untuk dibaca skrip}';

    protected $description = 'Cetak cookie sesi untuk otomasi UAT (tanpa kata sandi, tanpa rute pintas)';

    public function handle(): int
    {
        try {
            UatGerbang::pastikanTerbuka();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $nik = trim((string) $this->argument('nik'));

        $karyawan = Karyawan::where('Kode_Karyawan', $nik)->first();
        if (! $karyawan) {
            $this->error("Karyawan '{$nik}' tidak ditemukan.");

            return self::FAILURE;
        }

        $user = User::where('Id_Users', $karyawan->UserID_Web)->first();
        if (! $user) {
            $this->error(
                "Karyawan '{$nik}' tidak tertaut akun login (Karyawan.UserID_Web kosong "
                .'atau menunjuk user yang tak ada). Otomasi peramban tak bisa memerankannya.'
            );

            return self::FAILURE;
        }

        /* Store DIBUAT ULANG tiap pemanggilan, bukan dipakai ulang.
           app('session')->driver() mengembalikan instance tunggal yang di-cache
           manajernya. Dipanggil sekali per proses — seperti yang dilakukan
           Playwright — itu tak pernah jadi soal. Tapi uji beban mencetak puluhan
           sesi dalam SATU proses, dan store yang sama dipakai ulang membuat
           hanya sesi PERTAMA yang benar-benar tertulis: sisanya menghasilkan
           cookie yang bentuknya sah, berbeda satu sama lain, dan ditolak 401.
           Gejalanya menyamar sebagai "aplikasi menolak beban". */
        app('session')->forgetDrivers();

        $store = app('session')->driver();
        $store->setId(Str::random(40));
        $store->start();

        /* Kunci yang dibaca guard saat memulihkan sesi. Diminta dari guard-nya
           sendiri, bukan disusun tangan: bentuknya memuat sha1 nama kelas guard
           dan akan berubah diam-diam bila Laravel menggantinya. */
        $store->put(Auth::guard('web')->getName(), $user->getAuthIdentifier());
        $store->put('_token', Str::random(40));
        $store->save();

        $nama = config('session.cookie');

        /* Cookie Laravel dienkripsi DAN berawalan penanda nama cookie.
           Menyerahkan id sesi mentah menghasilkan cookie yang dibuang diam-diam
           oleh EncryptCookies, dan gejalanya adalah peramban yang selalu
           terlempar ke halaman login tanpa pesan apa pun. */
        $kunci = app('encrypter')->getKey();
        $nilai = app('encrypter')->encrypt(
            CookieValuePrefix::create($nama, $kunci).$store->getId(),
            false
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'nik' => $nik,
                'username' => $user->Username,
                'name' => $nama,
                'value' => $nilai,
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Sesi untuk {$nik} ({$user->Username})");
        $this->line("  cookie : {$nama}");
        $this->line("  nilai  : {$nilai}");

        return self::SUCCESS;
    }
}
