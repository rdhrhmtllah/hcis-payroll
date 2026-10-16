<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\VISITOR\Permit\VehiclePermit\VehiclePermitController;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Izin COKLAT tamu terbit ATAS NAMA PIC, tetapi bukan izin MILIK-nya.
 *
 * Bedanya bukan soal rapi-rapian. Pass Digital adalah kartu gerbang yang
 * benar-benar ditunjukkan orang di palang; izin tamu yang ikut masuk ke sana
 * menjadi kartu atas nama karyawan untuk kendaraan yang bukan miliknya. Di
 * development, tiga kartu seperti itu sudah terbit sebelum penjaga ini ada.
 *
 * Tiga permukaan, tiga jawaban yang BERBEDA, dan ketiganya harus benar
 * bersamaan — itulah kenapa ujinya satu berkas:
 *
 *  - Pass Digital  : TIDAK memuatnya (bukan kartu miliknya).
 *  - Riwayat       : MEMUATNYA, bertanda (jejak izin yang terbit atas namanya).
 *  - Pembatalan    : MENOLAK (izinnya terikat pada kunjungannya).
 *
 * Menyamakan ketiganya — ke arah mana pun — merusak salah satu dari ketiga
 * kebenaran itu.
 */
class IzinTamuBukanMilikPicTest extends TestCase
{
    use DatabaseTransactions;

    /** Kode karyawan NYATA di Web_HRIS development. */
    private const PIC = 'A1';

    public function test_pass_digital_tidak_memuat_izin_tamu(): void
    {
        $tamu = $this->buatPermitTamu();
        $sendiri = $this->buatPermitSendiri();

        $nomor = collect($this->panggil('getMyPermits', '/transaksipermit-my'))
            ->pluck('No_Transaksi')
            ->all();

        $this->assertNotContains(
            $tamu,
            $nomor,
            'Izin tamu jadi kartu Pass Digital atas nama PIC — kartu gerbang untuk kendaraan yang bukan miliknya.',
        );
        $this->assertContains($sendiri, $nomor, 'Izin milik karyawan sendiri ikut hilang dari kartunya.');
    }

    /**
     * Lencana Pass Digital wajib sama dengan jumlah kartu yang benar-benar ada.
     *
     * Kartu "Permit Aktif" dan lencana tab keduanya melompat ke Pass Digital,
     * sehingga angkanya milik tab ITU — bukan milik Riwayat, yang kini memuat
     * isi yang berbeda. Menghitung keduanya dari satu populasi menghasilkan
     * lencana "4" di atas daftar berisi 3, dan itu terbaca pemakainya sebagai
     * kartu yang hilang, bukan sebagai angka yang salah.
     */
    public function test_lencana_pass_digital_sama_dengan_jumlah_kartunya(): void
    {
        $this->buatPermitTamu('Y');
        $this->buatPermitSendiri('Y');

        $kartuAktif = collect($this->panggil('getMyPermits', '/transaksipermit-my'))
            ->where('Status_Permit', 'APPROVED')
            ->count();

        $request = Request::create('/transaksipermit-stats', 'GET');
        $request->setUserResolver(fn () => $this->user());
        $stats = json_decode(
            app(VehiclePermitController::class)->getStats($request)->getContent(),
            true,
        )['result'] ?? [];

        $this->assertSame(
            $kartuAktif,
            (int) ($stats['permit_aktif'] ?? -1),
            'Lencana Pass Digital berselisih dengan jumlah kartunya.',
        );
    }

    /**
     * Kartu "Menunggu Review" mengikuti DAFTAR YANG DIMUAT LAYAR.
     *
     * Versi pertama penjaga ini membandingkan angkanya dengan
     * /transaksipermit-list, sebab dulu itulah yang memuat Riwayat. Kedua tab
     * layar ini — Riwayat dan Pass Digital — kini sama-sama dimuat dari
     * /transaksipermit-my, sementara penjaganya tetap menatap endpoint lama:
     * ia hijau terus sambil layarnya berselisih dengan kartunya.
     *
     * Yang terbaca pemakainya pada 25 Sep 2026: "Menunggu Review 1" di atas
     * daftar tanpa satu pun baris menunggu. Yang dihitung adalah permit coklat
     * turunan kunjungan tamu — terbit atas nama PIC, tidak pernah muncul di
     * daftar mana pun di layar itu, dan tak ada yang bisa ia lakukan padanya.
     *
     * Karena itu penjaganya membandingkan dengan endpoint yang BENAR-BENAR
     * dipanggil layarnya.
     */
    public function test_angka_kartu_mengikuti_daftar_yang_dimuat_layar(): void
    {
        $tamu = $this->buatPermitTamu();
        $sendiri = $this->buatPermitSendiri();

        $daftar = collect($this->panggil('getMyPermits', '/transaksipermit-my'));
        $pending = $daftar->where('Status_Permit', 'PENDING')->count();

        $request = Request::create('/transaksipermit-stats', 'GET');
        $request->setUserResolver(fn () => $this->user());
        $stats = json_decode(
            app(VehiclePermitController::class)->getStats($request)->getContent(),
            true,
        )['result'] ?? [];

        $this->assertSame($pending, (int) ($stats['menunggu_approve'] ?? -1));
        $this->assertContains($sendiri, $daftar->pluck('No_Transaksi')->all());
        $this->assertNotContains(
            $tamu,
            $daftar->pluck('No_Transaksi')->all(),
            'Prasyarat gagal: izin tamu ikut ke daftar, jadi ujinya tak lagi menguji perselisihan populasinya.',
        );
    }

    /**
     * Sebutir izin tamu yang menunggu tidak boleh menyalakan kartunya.
     *
     * Bentuk paling murni dari bugnya: satu-satunya permit yang menunggu adalah
     * izin turunan kunjungan tamu. Perbandingan agregat di atas masih bisa
     * lolos kalau kebetulan ada pengajuan lain yang juga menunggu; yang ini
     * tidak.
     */
    public function test_izin_tamu_menunggu_tidak_menyalakan_kartu_menunggu_review(): void
    {
        $this->buatPermitTamu();

        $request = Request::create('/transaksipermit-stats', 'GET');
        $request->setUserResolver(fn () => $this->user());
        $stats = json_decode(
            app(VehiclePermitController::class)->getStats($request)->getContent(),
            true,
        )['result'] ?? [];

        $this->assertSame(
            collect($this->panggil('getMyPermits', '/transaksipermit-my'))
                ->where('Status_Permit', 'PENDING')
                ->count(),
            (int) ($stats['menunggu_approve'] ?? -1),
            'Kartu menghitung izin tamu yang menunggu; pemohon membaca angka yang tak punya barisnya.',
        );
    }

    /**
     * /transaksipermit-list masih memuatnya — dan itu memang tugasnya.
     *
     * CATATAN: endpoint ini BUKAN yang dipanggil tab Riwayat (layar memakai
     * /transaksipermit-my). Jangan membaca uji ini sebagai bukti bahwa izin
     * tamu tampil di layar pemohon; ia menjaga jejaknya tetap bisa diambil
     * lewat jalur yang memang memuat seluruh izin atas nama PIC.
     */
    public function test_riwayat_tetap_memuat_izin_tamu(): void
    {
        $tamu = $this->buatPermitTamu();

        $baris = collect($this->panggil('getList', '/transaksipermit-list'));

        $this->assertContains(
            $tamu,
            $baris->pluck('No_Transaksi')->all(),
            'Izin tamu hilang dari riwayat; PIC kehilangan satu-satunya jejak izin yang terbit atas namanya.',
        );
    }

    public function test_barisnya_membawa_asal_usul_supaya_bisa_ditandai(): void
    {
        // Tanpa kolom ini layar tidak punya cara membedakan izin tamu dari izin
        // kendaraan pribadi — keduanya tampil identik, bersebelahan.
        $tamu = $this->buatPermitTamu();

        $baris = collect($this->panggil('getList', '/transaksipermit-list'))
            ->firstWhere('No_Transaksi', $tamu);

        $this->assertNotNull($baris);
        $this->assertNotEmpty($baris['No_Transaksi_Tamu'] ?? null);
        $this->assertSame('Tamu Uji Riwayat', $baris['Nama_Tamu'] ?? null);
        $this->assertSame('PT Uji Riwayat', $baris['Instansi_Tamu'] ?? null);
    }

    public function test_izin_tamu_tidak_bisa_dibatalkan_dari_layar_permit_karyawan(): void
    {
        // Membatalkannya sendirian meninggalkan kunjungan yang tetap terjadwal
        // tanpa izin gerbang — dua dokumen yang bercerai tanpa satu pun galat.
        $tamu = $this->buatPermitTamu();
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $tamu)
            ->value('Id_Transaksi_Permit');

        $request = Request::create('/transaksipermit/'.$id.'/cancel', 'POST');
        $request->setUserResolver(fn () => $this->user());

        $respons = app(VehiclePermitController::class)->cancel($request, (string) $id);

        $this->assertSame(404, $respons->getStatusCode());
        $this->assertNull(
            DB::table('N_EMI_VISITOR_Transaksi_Permit')->where('No_Transaksi', $tamu)->value('Flag_Approval'),
            'Izin tamu ikut terbatalkan dari layar permit karyawan.',
        );
    }

    public function test_izin_sendiri_tetap_bisa_dibatalkan(): void
    {
        // Penjaga yang terlalu lebar akan mengunci pembatalan untuk semua orang,
        // dan itu tidak akan terlihat oleh uji yang hanya memeriksa penolakan.
        $sendiri = $this->buatPermitSendiri();
        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('No_Transaksi', $sendiri)
            ->value('Id_Transaksi_Permit');

        $request = Request::create('/transaksipermit/'.$id.'/cancel', 'POST');
        $request->setUserResolver(fn () => $this->user());

        $respons = app(VehiclePermitController::class)->cancel($request, (string) $id);

        $this->assertSame(200, $respons->getStatusCode());
    }

    /** @return array<int,array<string,mixed>> */
    private function panggil(string $metode, string $url): array
    {
        $request = Request::create($url, 'GET');
        $request->setUserResolver(fn () => $this->user());

        $isi = json_decode(
            app(VehiclePermitController::class)->{$metode}($request)->getContent(),
            true,
        );

        $hasil = $isi['result'] ?? [];

        return $hasil['data'] ?? $hasil;
    }

    private function user(): User
    {
        $userId = DB::table('Karyawan')->where('Kode_Karyawan', self::PIC)->value('UserID_Web');
        $user = User::find($userId);

        $this->assertNotNull($user, 'Karyawan '.self::PIC.' tidak punya baris login; fixture perlu ditinjau.');

        return $user;
    }

    /** Izin gerbang milik seorang TAMU, terdaftar atas nama PIC. */
    private function buatPermitTamu(?string $flagApproval = null): string
    {
        return $this->buatPermit('VSR-UJI-'.Str::upper(Str::random(8)), $flagApproval);
    }

    /** Izin kendaraan milik karyawan itu sendiri. */
    private function buatPermitSendiri(?string $flagApproval = null): string
    {
        return $this->buatPermit(null, $flagApproval);
    }

    private function buatPermit(?string $noTransaksiTamu, ?string $flagApproval = null): string
    {
        $nomor = 'PMT-UJI-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'No_Transaksi_Tamu' => $noTransaksiTamu,
            'Kode_Karyawan_Pemohon' => self::PIC,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => $flagApproval,
            'Sumber_Pengajuan' => $noTransaksiTamu ? 'API' : 'HCIS',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Updated_At' => now(),
            'Idempotency_Key' => 'UJI-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'Id_Transaksi_Permit' => $id,
            'No_Transaksi' => $nomor,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'UJ',
            'Nama_Tamu' => $noTransaksiTamu ? 'Tamu Uji Riwayat' : null,
            'Instansi_Tamu' => $noTransaksiTamu ? 'PT Uji Riwayat' : null,
        ]);

        // Langkah approval yang menggantung. Tanpa baris ini permitStatusSql()
        // memulangkan 'NO_FLOW', bukan 'PENDING': ia membedakan "menunggu
        // keputusan" dari "tidak punya alur sama sekali". Fixture tanpa alur
        // membuat angka 'menunggu approve' selalu nol, dan penjaga yang
        // menyamakannya dengan isi daftar lulus tanpa menguji apa pun.
        if ($flagApproval === null) {
            DB::table('HRIS_Approval_Request')->insert([
                'No_Transaksi' => $nomor,
                'Kode_Karyawan' => self::PIC,
                'Request_Order_Flow' => 1,
                'Approver_Karyawan_Kode' => self::PIC,
                'Flag_Approval' => null,
                'created_at' => now(),
                'update_at' => now(),
            ]);
        }

        return $nomor;
    }
}
