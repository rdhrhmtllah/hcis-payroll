<?php

namespace Tests\Feature\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\LMS\SOP\Uat\Support\UatAkun;
use Tests\Feature\LMS\SOP\Uat\Support\UatDunia;
use Tests\TestCase;

/**
 * UAT-SOP-047 — pemberitahuan penugasan baru, dan pengingat menjelang tenggat.
 *
 * SKENARIO INI SEMULA DITANDAI MANUAL, dengan alasan "pesan benar-benar sampai
 * ke ponsel". Alasan itu keliru: notifikasi SOP IN-APP saja — SopNotificationService
 * menulis ke N_HRIS_Notification lewat NotificationService, tanpa WhatsApp
 * maupun surel (dinyatakan pada docblock kelasnya). Tak ada ponsel yang terlibat,
 * jadi tak ada bagian yang mustahil diotomatiskan.
 *
 * Yang diperiksa BUKAN sekadar "ada baris notifikasi". Yang membuat notifikasi
 * berguna adalah isinya: menyebut paket yang mana, mengarah ke halaman yang
 * benar, dan tertuju pada karyawan yang benar. Notifikasi yang muncul dengan
 * judul kosong atau tautan salah tetap lolos uji yang hanya menghitung baris.
 *
 * N_HRIS_Notification SENGAJA di luar daftar-putih pembersih UAT — ia milik
 * seluruh HCIS, bukan milik SOP. Uji ini aman karena hidup di dalam transaksi
 * yang di-rollback, bukan karena pembersihnya menjangkaunya.
 */
class Alur047NotifikasiTest extends TestCase
{
    use DatabaseTransactions;

    private function dunia(): array
    {
        $admin = UatAkun::admin();
        if (! $admin) {
            $this->markTestSkipped('Tidak ada user dengan keenam hak akses halaman dokumen.');
        }

        $karyawan = UatAkun::karyawanBiasa();
        if (! $karyawan) {
            $this->markTestSkipped('Tidak ada karyawan biasa untuk ditugaskan.');
        }

        $kode = UatAkun::kodeKaryawan($karyawan);
        if (! $kode) {
            $this->markTestSkipped('Karyawan uji tak punya Kode_Karyawan.');
        }

        $dunia = UatDunia::mulai((int) $admin->Id_Users)
            ->buatDokumenTerbit()
            ->buatPaket()
            ->tambahItem('Dokumen Notifikasi')
            ->terbitkanPaket()
            ->tugaskanKe($kode);

        return [$dunia, $kode];
    }

    /** Notifikasi milik satu karyawan pada paket dunia uji. */
    private function notifikasi(string $kode, int $idPaket, string $type)
    {
        return DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $kode)
            ->where('Type', $type)
            ->where('Reference_Id', 'SOP-'.$idPaket)
            ->first();
    }

    #[Group('UAT-SOP-047')]
    public function test_penugasan_baru_melahirkan_pemberitahuan_dengan_isi_yang_benar(): void
    {
        [$dunia, $kode] = $this->dunia();

        $notif = $this->notifikasi($kode, $dunia->idPaket, 'SOP_ASSIGNED');

        $this->assertNotNull($notif,
            'Karyawan yang baru ditugaskan tidak menerima pemberitahuan apa pun. '
            .'Penugasan yang tak diberitahukan hanya akan ditemukan saat sudah terlambat.');

        $this->assertStringContainsString($dunia->nama('Paket'), (string) $notif->Message,
            'Pemberitahuannya tidak menyebut paket yang mana. Karyawan dengan beberapa '
            .'penugasan tak bisa tahu yang mana yang baru.');

        $this->assertSame('/sop/saya', $notif->Action_Url,
            'Tautannya tidak mengarah ke daftar tugas karyawan.');

        $this->assertSame('T', (string) $notif->Is_Read,
            'Pemberitahuan lahir dalam keadaan sudah terbaca, sehingga tak pernah menarik perhatian.');
    }

    /**
     * Satu kunci per (jenis, paket, karyawan) — penugasan ulang tidak menumpuk.
     *
     * Penjadwalan bisa dijalankan lagi untuk menambah orang, dan tanpa kunci
     * idempoten karyawan lama menerima pemberitahuan yang sama berkali-kali.
     * Notifikasi yang berulang tanpa sebab dilatih untuk diabaikan.
     */
    #[Group('UAT-SOP-047')]
    public function test_penugasan_diulang_tidak_menggandakan_pemberitahuan(): void
    {
        [$dunia, $kode] = $this->dunia();

        $svc = new SopNotificationService;
        $svc->notifPenugasan([$kode], $dunia->idPaket, $dunia->nama('Paket'));
        $svc->notifPenugasan([$kode], $dunia->idPaket, $dunia->nama('Paket'));

        $jumlah = DB::table('N_HRIS_Notification')
            ->where('Kode_Karyawan', $kode)
            ->where('Type', 'SOP_ASSIGNED')
            ->where('Reference_Id', 'SOP-'.$dunia->idPaket)
            ->count();

        $this->assertSame(1, $jumlah,
            "Pemberitahuan penugasan menumpuk ({$jumlah} baris) untuk satu paket yang sama.");
    }

    /**
     * Pengingat tenggat: isinya membedakan "akan jatuh tempo" dari "sudah lewat".
     *
     * Dua keadaan itu menuntut tindakan yang berbeda, dan pengingat yang
     * menyamakannya membuat karyawan tak bisa memilah mana yang mendesak.
     */
    #[Group('UAT-SOP-047')]
    public function test_pengingat_membedakan_menjelang_tenggat_dari_terlambat(): void
    {
        [$dunia, $kode] = $this->dunia();
        $idPaket = $dunia->idPaket;
        $nama = $dunia->nama('Paket');

        $svc = new SopNotificationService;

        $svc->notifReminder([[
            'kode' => $kode, 'id_paket' => $idPaket, 'nama' => $nama,
            'tenggat' => now()->addDays(3)->toDateString(), 'overdue' => false,
        ]]);

        $awal = $this->notifikasi($kode, $idPaket, 'SOP_REMINDER');
        $this->assertNotNull($awal, 'Pengingat menjelang tenggat tidak terkirim.');
        $this->assertMatchesRegularExpression('/jatuh tempo/i', (string) $awal->Message);
        $this->assertSame('warning', $awal->Severity);

        /* Karyawan yang sama, paket yang sama, kini TERLAMBAT. Kuncinya sama,
           jadi barisnya diperbarui — bukan ditambah. Yang harus berubah adalah
           isinya dan tingkat kegawatannya. */
        $svc->notifReminder([[
            'kode' => $kode, 'id_paket' => $idPaket, 'nama' => $nama,
            'tenggat' => now()->subDay()->toDateString(), 'overdue' => true,
        ]]);

        $lewat = $this->notifikasi($kode, $idPaket, 'SOP_REMINDER');
        $this->assertMatchesRegularExpression('/melewati tenggat/i', (string) $lewat->Message,
            'Pengingat masih berbunyi "akan jatuh tempo" padahal tenggatnya sudah lewat.');
        $this->assertSame('danger', $lewat->Severity,
            'Keterlambatan tidak dinaikkan tingkat kegawatannya, sehingga tenggelam di '
            .'antara pengingat biasa.');
    }
}
