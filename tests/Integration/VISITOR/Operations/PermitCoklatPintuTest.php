<?php

namespace Tests\Integration\VISITOR\Operations;

use App\Http\Controllers\VISITOR\Guest\Visit\GuestRegistrationController;
use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use App\Services\VISITOR\Guest\Visit\AturanFormTamu;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\VISITOR\MenerbitkanPermitCoklat;
use Tests\Support\VISITOR\PermintaanRegistrasiTamu;
use Tests\TestCase;

/**
 * Setiap pintu yang menulis proyeksi TAMU wajib ikut mengurus permit coklatnya.
 *
 * Ada lima. Menutup empat di antaranya tidak menyalakan error apa pun; yang
 * terjadi adalah tamu tanpa permit di gerbang, atau permit yang tetap hidup
 * sesudah kunjungannya dicabut. Satu tes per pintu, supaya pintu yang terlewat
 * ketahuan sebagai kegagalan yang menyebut namanya sendiri.
 *
 * Daftar pintunya didapat dari penelusuran seluruh pemanggil syncTamuEfektif /
 * revokeTamuEfektif. Kalau kelak muncul pemanggil baru, berkas ini yang harus
 * bertambah lebih dulu.
 */
class PermitCoklatPintuTest extends TestCase
{
    use DatabaseTransactions;
    use MenerbitkanPermitCoklat;
    use PermintaanRegistrasiTamu;

    /**
     * Kode karyawan NYATA di Web_HRIS development.
     *
     * Kode karangan tidak bisa dipakai: syncTamuEfektif() menulis Nama_PIC yang
     * NOT NULL di N_VISITOR_Tamu_Efektif, dan namanya di-resolve lewat JOIN ke
     * tabel Karyawan. PIC yang tidak ada membuat seluruh registrasi gagal 500 —
     * kegagalan yang menyamar sebagai bug fitur, padahal fixture-nya yang salah.
     */
    private const PIC = 'A1';

    public function test_pintu_1_auto_approve_saat_submit_mengajukan_permit(): void
    {
        // Registrasi B2B memakai modul approval 'tamu_b2b' sejak DDL-2026-121.
        $config = app(VisitorApprovalPolicyService::class)->config('tamu_b2b');

        if ((bool) $config['needs_approval']) {
            $this->markTestSkipped('Approval tamu B2B aktif; jalur auto-approve tidak dapat dicapai dari sini.');
        }

        $nomor = $this->daftarkanLewatController();

        // Kunjungannya boleh langsung terjadwal; izin gerbangnya tidak. Yang
        // ditagih di sini adalah ADANYA pengajuan permit, bukan permit hidup.
        $this->assertNotNull(
            $this->nomorPermitCoklat($nomor),
            'Registrasi tamu B2B tidak mengajukan permit COKLAT sama sekali.',
        );
    }

    public function test_pintu_2_approval_final_menerbitkan_permit(): void
    {
        $nomor = $this->buatTamu();

        // Meniru titik akhir approveTamu(): proyeksi tamu ditulis, permitnya
        // diajukan. Yang ditagih uji ini hasil akhirnya — tamu efektif TIDAK
        // BOLEH ada tanpa pengajuan permit coklatnya.
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        $noPermit = $this->ajukanPermitCoklat($nomor);

        $this->assertNotNull(DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->first());
        $this->assertNotNull($noPermit, 'Tamu efektif tanpa pengajuan permit coklat.');
    }

    public function test_pintu_3_pic_menghapus_tamu_mencabut_permitnya(): void
    {
        $nomor = $this->buatTamuEfektifPenuh();
        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Id_Transaksi_Tamu');

        $request = Request::create('/tamu/'.$id, 'DELETE');
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);
        app(GuestRegistrationController::class)->destroy($request, (string) $id);

        $this->assertSame('DIBATALKAN', $this->permit($nomor)->Status_Efektif);
    }

    public function test_pintu_4_verifikator_mencabut_tamu_mencabut_permitnya(): void
    {
        $nomor = $this->buatTamuEfektifPenuh();

        // Tamunya B2B: pencabutan hanya lewat meja B2B. Atribut ini meniru
        // middleware visitor.tipe-tamu:B2B; tanpa itu rute dibaca Regular dan
        // baris B2B dibalas 404 (DDL-2026-121).
        $request = Request::create('/verifikator-b2b/'.$nomor.'/revoke', 'POST', [
            'alasan' => 'Kunjungan dibatalkan pihak perusahaan.',
        ]);
        $request->attributes->set(TipeTamu::ATRIBUT, TipeTamu::B2B);
        $request->setUserResolver(fn () => (object) ['Kode_Karyawan' => self::PIC]);

        app(VerifikatorVisitorController::class)->revokeTamu($request, $nomor);

        // Status PERMIT tidak berubah kosakatanya; hanya status kunjungan.
        $this->assertSame('DIBATALKAN', $this->permit($nomor)->Status_Efektif);
        $this->assertSame(
            'DIBATALKAN',
            DB::table('N_VISITOR_Tamu_Efektif')->where('No_Transaksi', $nomor)->value('Status_Efektif'),
        );
        // Sejak DDL-2026-121 pencabutan juga tercatat di header.
        $this->assertSame(
            StatusKunjunganTamu::REVOKED,
            DB::table('N_EMI_VISITOR_Transaksi_Tamu')->where('No_Transaksi', $nomor)->value('Status_Kunjungan'),
        );
    }

    public function test_pintu_5_seeder_demo_ikut_menerbitkan_permit_coklat(): void
    {
        // Papan demo dipakai memperagakan modul. Tanpa permit coklat, peragaan
        // itu menunjukkan keadaan yang tidak mungkin terjadi di produksi.
        Artisan::call('visitor:seed-papan-harian');

        // Hanya kunjungan yang persetujuannya sudah final. Kunjungan yang masih
        // menunggu approval memang belum boleh punya izin gerbang — itu justru
        // keadaan yang change ini tegakkan.
        $tamuDemo = DB::table('N_EMI_VISITOR_Transaksi_Tamu')
            ->where('Idempotency_Key', 'LIKE', 'SEEDDEMO-%')
            ->where('Flag_Approval', 'Y')
            ->pluck('No_Transaksi');

        $this->assertNotEmpty($tamuDemo, 'Seeder demo tidak menghasilkan tamu disetujui apa pun.');

        $tanpaPermit = $tamuDemo->reject(function ($no) {
            $noPermit = $this->nomorPermitCoklat($no);

            return $noPermit
                && DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->exists();
        });

        $this->assertCount(
            0,
            $tanpaPermit,
            'Seeder demo menerbitkan tamu tanpa permit coklat: '.$tanpaPermit->implode(', '),
        );
    }

    public function test_pembersihan_seeder_tidak_meninggalkan_permit_yatim(): void
    {
        Artisan::call('visitor:seed-papan-harian');
        Artisan::call('visitor:seed-papan-harian', ['--bersihkan' => true]);

        // Hanya yang masih AKTIF. Baris berformat lama — permit COKLAT dari
        // sebelum ia punya transaksi sendiri — sudah ditutup DDL-2026-103 dan
        // tidak dapat memberi akses kepada siapa pun; menagihnya lenyap berarti
        // uji ini tidak akan pernah hijau di basis data yang pernah memakainya.
        $yatim = DB::table('N_VISITOR_Permit_Efektif as pe')
            ->where('pe.Sumber_Permit', 'TAMU')
            ->where('pe.Status_Efektif', 'AKTIF')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('N_EMI_VISITOR_Transaksi_Permit as p')
                    ->join('N_EMI_VISITOR_Transaksi_Tamu as h', 'h.No_Transaksi', '=', 'p.No_Transaksi_Tamu')
                    ->whereColumn('p.No_Transaksi', 'pe.No_Transaksi');
            })
            ->count();

        $this->assertSame(0, $yatim, 'Ada permit coklat yang transaksi tamunya sudah tidak ada.');
    }

    /**
     * Baris gerbang milik permit coklat sebuah KUNJUNGAN.
     *
     * Penerjemahan nomornya sengaja lewat tautan, bukan disamakan: sejak permit
     * coklat punya transaksi sendiri, mencari proyeksi dengan nomor kunjungan
     * selalu menghasilkan nol baris — dan uji yang menuntut "tidak ada permit"
     * akan hijau tanpa membuktikan apa pun.
     */
    private function permit(string $nomorTamu): object
    {
        $noPermit = $this->nomorPermitCoklat($nomorTamu);
        $this->assertNotNull($noPermit, "Permit coklat untuk {$nomorTamu} tidak pernah diajukan.");

        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $noPermit)->first();
        $this->assertNotNull($baris, "Permit coklat {$noPermit} tidak ada di proyeksi.");

        return $baris;
    }

    private function daftarkanLewatController(): string
    {
        // DDL-2026-121: tipe ditetapkan rute (argumen terakhir), bukan isian
        // tipe_tamu; B2B wajib berkendaraan + plat, dan Estimasi Masuk tidak
        // boleh sebelum hari ini.
        $besok = now()->addDay();
        $kendaraan = DB::table('N_EMI_VISITOR_Master_Vehicle_Type')
            ->where('Flag_Aktif', 'Y')
            ->where(function ($query) {
                $query->whereNull('Akses')->orWhereIn('Akses', ['ALL', 'TAMU']);
            })
            ->orderBy('Nama_Tipe_Kendaraan')
            ->pluck('Nama_Tipe_Kendaraan')
            ->first(fn ($nama) => ! AturanFormTamu::tanpaKendaraan($nama));
        $plat = DB::table('N_EMI_VISITOR_Master_Plat_Kendaraan')->where('Flag_Aktif', 'Y')->value('Kode_Plat');

        $this->assertNotNull($kendaraan, 'Master tipe kendaraan aktif untuk tamu tidak tersedia.');
        $this->assertNotNull($plat, 'Master plat aktif tidak tersedia.');

        $request = $this->permintaanRegistrasiTamu([
            'nama_tamu' => 'Tamu Pintu',
            'keperluan' => 'Meeting',
            'pic_tujuan' => 'HRD',
            'jumlah_tamu' => 1,
            'daftar_anggota' => [],
            'instansi_asal' => 'PT Uji',
            'jenis_kendaraan' => $kendaraan,
            'plat_prefix' => $plat,
            'plat_angka' => (string) random_int(1000, 9999),
            // Kode belakang wajib bagi tamu B2B di form web (29 Sep 2026).
            'plat_suffix' => 'PT',
            'waktu_mulai_kunjungan' => $besok->copy()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'waktu_selesai_kunjungan' => $besok->copy()->setTime(17, 0)->format('Y-m-d H:i:s'),
        ], self::PIC, 'PTU-'.Str::upper(Str::random(20)), TipeTamu::B2B);

        $isi = json_decode(app(GuestRegistrationController::class)->store($request)->getContent(), true);

        $this->assertNotEmpty($isi['result']['no_transaksi'] ?? null, 'Registrasi tamu gagal: '.json_encode($isi));

        return $isi['result']['no_transaksi'];
    }

    private function buatTamu(): string
    {
        $nomor = 'VSR-PTU-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => StatusKunjunganTamu::SCHEDULED,
            'Flag_Approval' => 'Y',
            'Tanggal_Kunjungan' => '2026-09-10',
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'PTU-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            'Nama_Lengkap_Tamu' => 'Tamu Pintu',
            'Tipe_Tamu' => 'B2B',
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Mobil',
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(10000, 99999),
            'Plat_Nomor_Suffix' => 'PT',
            'Waktu_Mulai_Kunjungan' => '2026-09-10 08:00:00',
            'Waktu_Selesai_Kunjungan' => '2026-09-10 17:00:00',
        ]);

        return $nomor;
    }

    private function buatTamuEfektifPenuh(): string
    {
        $nomor = $this->buatTamu();
        app(VisitorEfektifService::class)->syncTamuEfektif($nomor);
        $this->terbitkanPermitCoklat($nomor, self::PIC);

        return $nomor;
    }
}
