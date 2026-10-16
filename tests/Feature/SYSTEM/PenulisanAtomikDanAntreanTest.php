<?php

namespace Tests\Feature\SYSTEM;

use App\Jobs\KPI\Notification\SendKpiNotifWhatsappJob;
use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappJob;
use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappMessage;
use ReflectionClass;
use Tests\TestCase;

/**
 * Dua jaminan yang mudah hilang tanpa ada yang menyadarinya.
 *
 * Keduanya temuan Fase 12, dan keduanya berbagi satu sifat: kalau rusak, tak
 * ada yang gagal dan tak ada yang berteriak. Kerusakannya baru terlihat sebagai
 * data separuh jalan atau halaman yang menggantung — jauh dari penyebabnya.
 */
class PenulisanAtomikDanAntreanTest extends TestCase
{
    /**
     * deleteRole() menulis TIGA kali, dan yang ketiga permanen.
     *
     * Dua soft delete (Role_Functional, Role_Scope) lalu satu DELETE sungguhan
     * (Role_Permission). Gagal di tengah meninggalkan role yang masih
     * Flag_Aktif='Y' tetapi permission-nya sudah lenyap: role yang tampak hidup
     * dengan izin kosong. Ia tak melaporkan apa pun — pemakainya hanya
     * menemukan akses yang diam-diam tak berlaku.
     */
    public function test_delete_role_menulis_dalam_satu_transaksi(): void
    {
        $isi = (string) file_get_contents(
            base_path('app/Http/Controllers/SYSTEM/Governance/Authorization/AdminRoleManagerController.php'),
        );

        $awal = strpos($isi, 'public function deleteRole(');
        $this->assertNotFalse($awal, 'deleteRole() tidak ditemukan — namanya berubah?');

        /* Dipotong sampai metode berikutnya supaya transaksi milik metode LAIN
           tidak keliru dihitung sebagai milik deleteRole. */
        $berikut = strpos($isi, 'public function ', $awal + 20);
        $badan = substr($isi, $awal, $berikut === false ? null : $berikut - $awal);

        $this->assertStringContainsString(
            'DB::transaction(',
            $badan,
            'deleteRole() menulis tiga tabel — termasuk satu DELETE permanen — di luar transaksi.',
        );

        foreach (['N_HRIS_Role_Functional', 'N_HRIS_Role_Scope', 'N_HRIS_Role_Permission'] as $tabel) {
            $this->assertStringContainsString($tabel, $badan, "Penulisan ke {$tabel} hilang dari deleteRole().");
        }
    }

    /**
     * Job WhatsApp WAJIB menunda dirinya sampai commit — pada koneksi APA PUN.
     *
     * Jaminan ini dulu bersandar pada `after_commit => true` milik koneksi
     * cloudtasks saja. Baku di config/queue.php adalah
     * `env('QUEUE_CONNECTION', 'sync')`, dan `sync` menjalankan job INLINE di
     * dalam transaksi yang sedang terbuka. HTTP WhatsApp memakan 10-20 detik;
     * dengan RCSI mati di Web_HRIS, transaksi yang menahan sekian detik membuat
     * pembacanya menunggu selama itu juga — terukur pada percobaan Fase 12:
     * penahan 7 detik, korban menunggu 5.103 ms. Itulah "Login timeout expired"
     * yang dulu diperbaiki, dan ia bisa kembali hanya karena satu baris .env
     * hilang.
     *
     * DIPERIKSA PADA INSTANS, bukan pada properti bawaan kelas. Trait Queueable
     * sudah mendeklarasikan $afterCommit (null), dan PHP menolak kelas yang
     * menimpanya dengan nilai bawaan berbeda — jadi jaminannya dipasang lewat
     * $this->afterCommit() di konstruktor. Memeriksa getDefaultProperties()
     * akan selalu melihat null, dan lulus atau gagal karena alasan yang salah.
     */
    public function test_job_whatsapp_menunda_sampai_commit(): void
    {
        foreach ($this->contohJob() as $nama => $job) {
            $this->assertTrue(
                $job->afterCommit === true,
                "{$nama} tak menunda sampai commit. Pada koneksi sync ia mengirim WhatsApp INLINE "
                .'di dalam transaksi yang terbuka, menahan kunci selama HTTP berlangsung.',
            );
        }
    }

    /**
     * Sisi negatifnya: tanpa konstruktor, nilainya HARUS masih kosong.
     *
     * Tanpa pemeriksaan ini, uji di atas tetap hijau seandainya seseorang
     * kelak menyetel $afterCommit lewat cara lain yang tak berlaku pada semua
     * koneksi — dan kita tak akan tahu bahwa jaminannya berpindah tempat.
     */
    public function test_jaminannya_datang_dari_konstruktor(): void
    {
        foreach ([SendWhatsappJob::class, SendWhatsappMessage::class, SendKpiNotifWhatsappJob::class] as $nama) {
            $tanpaKonstruktor = (new ReflectionClass($nama))->newInstanceWithoutConstructor();

            $this->assertNotTrue(
                $tanpaKonstruktor->afterCommit,
                "{$nama} sudah true tanpa konstruktor — periksa ulang dari mana jaminannya datang.",
            );
        }
    }

    /** @return array<string, object> */
    private function contohJob(): array
    {
        return [
            'SendWhatsappJob' => new SendWhatsappJob('K1', 'Budi', '628123456789', 'TRX-1', []),
            'SendWhatsappMessage' => new SendWhatsappMessage('628123456789', 'halo'),
            'SendKpiNotifWhatsappJob' => new SendKpiNotifWhatsappJob('K1', 'Budi', '628123456789', 'TRX-1', []),
        ];
    }
}
