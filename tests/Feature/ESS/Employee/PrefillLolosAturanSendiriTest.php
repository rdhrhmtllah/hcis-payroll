<?php

namespace Tests\Feature\ESS\Employee;

use App\Services\ESS\Employee\DataCollection\PengirimanDataService;
use App\Services\ESS\Employee\DataCollection\SkemaPengumpulanData;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Nilai yang DIPREFILL wajib lolos aturan validasinya sendiri.
 *
 * Prefill yang menyerahkan nilai tak sah adalah bentuk kegagalan paling buruk:
 * kotaknya terlihat sudah terisi dan benar, sehingga karyawan tidak
 * menyentuhnya — lalu ditolak server dengan pesan yang menyuruh mengetik
 * persis apa yang sudah tertera di layar.
 *
 * Yang benar-benar terjadi sebelum penjaga ini ada, diukur atas 30 karyawan:
 *
 *     no_hp               30/30 gagal   '6285269805413'  aturannya minta ^08…
 *     nik_ktp             29/30 gagal   '2'              bukan 16 digit
 *     jenis_kelamin       26/30 gagal   'Laki-Laki'      skema minta L / P
 *     pendidikan_terakhir  1/30 gagal   'S1'             daftar memakai D4/S1
 *
 * Nomor HP paling berbahaya karena TIGA lapis sepakat berbohong: layar
 * memotong '62' sehingga tampak benar, galatFormat() memakai pemotong yang
 * sama sehingga diam, dan hanya server yang menolak.
 *
 * Baris no_hp itu ternyata punya sebab yang lebih dalam, dan diperbaiki dari
 * sisi yang lain pada 4 Sep 2026: yang menyimpang bukan data masternya
 * melainkan aturan fitur ini. Seluruh sistem memakai '62…' — 958 dari 958
 * Karyawan.HP, dan pengirim WhatsApp — jadi pola '^08' dibalik jadi '^62'.
 * Prefill berhenti membengkokkan data rumah; penjaga di bawah tetap berlaku,
 * hanya bentuk yang dituntutnya yang ikut berubah.
 *
 * Aturannya sederhana dan tidak boleh dilunakkan: prefill mengisi kotak HANYA
 * dengan nilai yang akan diterima server. Nilai yang tak bisa dinormalkan
 * dibuang — kotak kosong itu jujur, kotak terisi salah itu menipu.
 */
class PrefillLolosAturanSendiriTest extends TestCase
{
    use DatabaseTransactions;

    private function skema(): SkemaPengumpulanData
    {
        return app(SkemaPengumpulanData::class);
    }

    private function layanan(): PengirimanDataService
    {
        return app(PengirimanDataService::class);
    }

    /** Karyawan nyata, bukan fixture — bentuk data master itulah yang diuji. */
    private function karyawanNyata(int $jumlah): array
    {
        return DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->orderBy('Kode_Karyawan')
            ->limit($jumlah)
            ->pluck('Kode_Karyawan')
            ->all();
    }

    public function test_setiap_nilai_prefill_lolos_aturannya_sendiri(): void
    {
        $skema = $this->skema();
        $aturan = $skema->aturanValidasi(false);
        $kode = $this->karyawanNyata(40);

        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan aktif di data dev.');
        }

        $gagal = [];

        foreach ($kode as $k) {
            foreach ($this->layanan()->ambilDataMaster($k) as $field => $nilai) {
                if (! isset($aturan[$field])) {
                    continue;
                }

                $v = Validator::make([$field => $nilai], [$field => $aturan[$field]]);

                if ($v->fails()) {
                    $gagal[] = sprintf('%s pada %s = %s (%s)',
                        $field, $k, var_export($nilai, true), $v->errors()->first($field));
                }
            }
        }

        $this->assertSame([], $gagal,
            "Prefill menyerahkan nilai yang ditolak servernya sendiri:\n  ".implode("\n  ", array_slice($gagal, 0, 12)));
    }

    /**
     * Bentuk nomor HP di master adalah '62…', server menuntut '08…'.
     * Ini kasus yang menimpa SELURUH karyawan, jadi diuji sendiri.
     */
    public function test_nomor_hp_master_dinormalkan_ke_bentuk_server(): void
    {
        $kode = $this->karyawanNyata(1);
        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan aktif.');
        }

        $master = $this->layanan()->ambilDataMaster($kode[0]);

        if (! array_key_exists('no_hp', $master)) {
            $this->markTestSkipped('Karyawan ini tidak punya nomor HP di master.');
        }

        $this->assertMatchesRegularExpression('/^628[0-9]{8,12}$/', (string) $master['no_hp'],
            'Nomor HP prefill harus berbentuk 62… — bentuk yang dipakai seluruh sistem '.
            'dan yang dituntut aturan fitur ini sejak 4 Sep 2026.');
    }

    /**
     * Lolos aturan server saja belum cukup — bentuknya harus cocok dengan
     * kotaknya.
     *
     * HcisDatePicker memakai valueFormat 'YYYY-MM-DD'. Master menyimpan
     * datetime ('2025-06-03 00:00:00.000'), yang diterima aturan server tetapi
     * DITOLAK kotaknya sehingga tampil kosong. Prefill yang lolos server tapi
     * tak muncul di layar sama saja tidak ada.
     */
    public function test_tanggal_lahir_prefill_berbentuk_ymd(): void
    {
        $kode = DB::table('Karyawan')
            ->where('Aktif', 'Y')->whereNull('Tanggal_Resign')
            ->whereNotNull('Tgl_Lahir')
            ->value('Kode_Karyawan');

        if (! $kode) {
            $this->markTestSkipped('Tidak ada karyawan ber-Tgl_Lahir.');
        }

        $master = $this->layanan()->ambilDataMaster($kode);

        $this->assertArrayHasKey('tanggal_lahir', $master);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $master['tanggal_lahir'],
            'Kotak tanggal memakai YYYY-MM-DD; bentuk berjam akan tampil kosong.');
    }

    /**
     * Nilai yang tak bisa diselamatkan harus DIBUANG, bukan diserahkan apa
     * adanya. NIK '2' tidak mungkin jadi 16 digit; mengisikannya hanya membuat
     * orang mengira datanya sudah benar.
     */
    public function test_nilai_yang_tak_bisa_dinormalkan_dibuang(): void
    {
        $kode = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNull('Tanggal_Resign')
            ->whereRaw("LEN(LTRIM(RTRIM(ISNULL(NIK,'')))) <> 16")
            ->value('Kode_Karyawan');

        if (! $kode) {
            $this->markTestSkipped('Semua karyawan punya NIK 16 digit; kasusnya tak bisa dibentuk.');
        }

        $master = $this->layanan()->ambilDataMaster($kode);

        $this->assertArrayNotHasKey('nik_ktp', $master,
            'NIK master yang bukan 16 digit harus dibuang dari prefill, bukan diisikan ke kotak.');
    }

    /**
     * Snapshot audit TIDAK ikut dinormalkan.
     *
     * Snapshot merekam apa yang MEMANG ada di sistem induk pada detik
     * pengiriman. Menormalkannya berarti membuat rekaman yang lebih rapi
     * daripada kenyataannya, dan perbandingan "berbeda dari data lama" jadi
     * membandingkan dua hasil olahan — bukan olahan melawan kenyataan.
     *
     * Yang dibandingkan adalah nilai KOLOMNYA, bukan sekadar awalannya. Sejak
     * pola dibalik ke '^62' pada 4 Sep 2026, hasil normalisasi pun berawalan
     * '62' — sehingga memeriksa awalan saja akan lolos justru pada saat
     * snapshot mulai dinormalkan, persis kegagalan yang penjaga ini cegah.
     */
    public function test_snapshot_audit_tetap_mentah(): void
    {
        $baris = DB::table('Karyawan')
            ->where('Aktif', 'Y')->whereNull('Tanggal_Resign')
            ->whereRaw("LTRIM(RTRIM(ISNULL(HP,''))) <> ''")
            ->first(['Kode_Karyawan', 'HP']);

        if (! $baris) {
            $this->markTestSkipped('Tidak ada karyawan aktif ber-HP di data dev.');
        }

        $m = new \ReflectionMethod(PengirimanDataService::class, 'snapshotDataLama');
        $m->setAccessible(true);
        $snapshot = $m->invoke($this->layanan(), $baris->Kode_Karyawan);

        $this->assertSame(trim((string) $baris->HP), (string) ($snapshot['no_hp'] ?? ''),
            'Snapshot harus merekam nilai master APA ADANYA, bukan hasil normalisasi.');
    }
    /**
     * Sejak skema versi 4 'D4' dan 'S1' terpisah. Master yang menulis 'S1'
     * tidak boleh lagi dilebur jadi 'D4/S1' — nilai itu kini ditolak server.
     */
    public function test_pendidikan_d4_dan_s1_diteruskan_terpisah(): void
    {
        $m = new \ReflectionMethod(PengirimanDataService::class, 'normalkanUntukPrefill');
        $m->setAccessible(true);
        $layanan = $this->layanan();

        $this->assertSame('S1', $m->invoke($layanan, 'pendidikan_terakhir', 's1'));
        $this->assertSame('D4', $m->invoke($layanan, 'pendidikan_terakhir', 'D4'));
        // Gabungan lama tidak bisa ditebak ke salah satunya.
        $this->assertNull($m->invoke($layanan, 'pendidikan_terakhir', 'D4/S1'));
    }

}
