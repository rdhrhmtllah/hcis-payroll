<?php

namespace Tests\Support\VISITOR;

use App\Http\Requests\VISITOR\Guest\Visit\SimpanTamuRequest;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun permintaan registrasi tamu yang BERTIPE BENAR dan sudah tervalidasi.
 *
 * GuestRegistrationController::store() menerima SimpanTamuRequest, bukan
 * Illuminate\Http\Request. Perbedaannya bukan formalitas: aturan masukan hidup
 * di FormRequest itu, sehingga permintaan biasa akan melewati controller tanpa
 * satu pun aturan dijalankan — uji yang menuntut penolakan jadi hijau palsu,
 * dan uji yang menuntut keberhasilan mati oleh TypeError.
 *
 * Lima berkas uji menyusun permintaannya sendiri dengan cara yang sama persis.
 * Dikumpulkan di sini supaya perubahan kontrak berikutnya cukup disentuh satu
 * kali — pola kegagalan "aturan hanya terpasang di salah satu pintu" berlaku
 * untuk uji juga.
 */
trait PermintaanRegistrasiTamu
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  string|object  $pic  Kode karyawan, ATAU objek pemakai apa adanya.
     *
     * Dua bentuk diterima karena pemanggilnya memang berbeda: sebagian uji
     * hanya punya kode karyawannya, sebagian lain sudah memegang model User
     * lengkap yang dipakai juga untuk memeriksa notifikasi. Memaksa satu bentuk
     * membuat pemanggil menyusun ulang objeknya — dan objek karangan itulah
     * yang biasanya kehilangan field yang justru diperiksa controller.
     *
     * `$tipe` meniru middleware `visitor.tipe-tamu:<TIPE>` (DDL-2026-121): tipe
     * tamu ditetapkan RUTE, isian `tipe_tamu` diabaikan. Null = tanpa penetapan,
     * yang dibaca REGULAR seperti rute /transaksitamu.
     */
    protected function permintaanRegistrasiTamu(
        array $payload,
        string|object $pic,
        ?string $idempotencyKey = null,
        ?string $tipe = null,
    ): SimpanTamuRequest {
        $url = $tipe !== null && strtoupper($tipe) === TipeTamu::B2B ? '/transaksi-tamu-b2b' : '/transaksitamu';
        if ($tipe !== null && strtoupper($tipe) === TipeTamu::B2B) {
            // Dipakai controller hanya bila pengaju berhak lebih dari satu unit.
            $payload['b2b_unit'] ??= 'LOGISTIC';
        }
        $request = SimpanTamuRequest::create($url, 'POST', $payload);

        // Dipasang SEBELUM validateResolved(): aturan isian (kendaraan & plat
        // wajib untuk B2B) dipilih dari tipe ini.
        if ($tipe !== null) {
            $request->attributes->set(TipeTamu::ATRIBUT, TipeTamu::sah($tipe));
        }

        $request->headers->set(
            'Idempotency-Key',
            $idempotencyKey ?? 'UJI-'.strtoupper(bin2hex(random_bytes(10))),
        );
        $pengguna = is_string($pic) ? (object) ['Kode_Karyawan' => $pic] : $pic;

        // Pengajuan B2B wajib dari departemen pemilik unit (matriks, DDL-2026-122).
        // PIC uji diberi role TAMU_DEPT_LOGISTIC di dalam transaksi uji, sehingga
        // unitnya terkunci LOGISTIC dan ikut ter-rollback.
        if ($tipe !== null && strtoupper($tipe) === TipeTamu::B2B) {
            $pengguna = $this->jadikanPengajuB2b($pengguna);
        }

        $request->setUserResolver(fn () => $pengguna);

        // Container + redirector wajib dipasang sebelum validateResolved():
        // FormRequest membangun validator lewat container, dan kegagalan
        // validasi pada permintaan non-JSON mencoba melakukan redirect.
        $request->setContainer(app())->setRedirector(app('redirect'));

        // Dijalankan EKSPLISIT. Di luar siklus HTTP tidak ada yang memanggilnya,
        // sehingga tanpa baris ini aturan FormRequest tidak pernah dieksekusi
        // dan uji penolakan berhenti menguji apa pun.
        $request->validateResolved();

        return $request;
    }

    /**
     * Beri PIC keanggotaan role TAMU_DEPT_LOGISTIC dan pastikan objek
     * penggunanya membawa Id_Users (= N_HRIS_Role_User.UserId_Web).
     */
    protected function jadikanPengajuB2b(object $pengguna): object
    {
        $idUser = $pengguna->Id_Users ?? DB::table('Karyawan')
            ->where('Kode_Karyawan', $pengguna->Kode_Karyawan ?? null)
            ->whereNotNull('UserID_Web')
            ->value('UserID_Web');

        if (! $idUser) {
            throw new \RuntimeException('PIC uji tidak punya UserID_Web; pengajuan B2B butuh role departemen.');
        }

        $idRole = DB::table('N_HRIS_Role_Functional')->where('Role_Code', 'TAMU_DEPT_LOGISTIC')->value('Id_Role');

        if (! $idRole) {
            throw new \RuntimeException('Role TAMU_DEPT_LOGISTIC belum ada; jalankan data-change.sql DDL-2026-122.');
        }

        $sudah = DB::table('N_HRIS_Role_User')
            ->where('UserId_Web', $idUser)
            ->where('Id_Role', $idRole)
            ->where('Flag_Aktif', 'Y')
            ->exists();

        if (! $sudah) {
            DB::table('N_HRIS_Role_User')->insert([
                'UserId_Web' => $idUser,
                'Id_Role' => $idRole,
                'Assigned_At' => now(),
                'Assigned_By' => 'UJI',
                'Flag_Aktif' => 'Y',
            ]);
        }

        if (! isset($pengguna->Id_Users)) {
            $pengguna = (object) array_merge((array) $pengguna, ['Id_Users' => (int) $idUser]);
        }

        return $pengguna;
    }
}
