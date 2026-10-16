<?php

namespace Tests\Feature\ESS\Attendance;

use App\Http\Controllers\ESS\Attendance\Legacy\AbsensiController;
use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappJob;
use App\Models\ESS\Employee\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Notifikasi approver izin harus lewat ANTREAN, bukan HTTP di dalam permintaan.
 *
 * ── Gejalanya ────────────────────────────────────────────────────────────────
 *
 * Submit izin tidak pernah memunculkan apa pun di Cloud Tasks. Sebabnya bukan
 * antrean yang rusak: jalur ini satu-satunya notifikasi approver di aplikasi
 * yang memanggil Whatsapp::send_message LANGSUNG, jadi memang tak pernah ada
 * job yang di-dispatch. Semua modul lain — cuti, shift, lembur, KPI, approval
 * izin, admin cuti — memakai WhatsappLogHelper::sendAndLog.
 *
 * ── Kenapa ini lebih dari sekadar rapi ───────────────────────────────────────
 *
 *   - tanpa percobaan ulang. SendWhatsappJob punya tries=3; panggilan langsung
 *     hanya punya WHATSAPP_RETRY_TIMES (baku 1) lalu menyerah, dan kegagalannya
 *     cuma jadi baris log yang tak dibaca siapa pun.
 *   - nomor HP tak divalidasi bentuknya di jalur langsung.
 *   - pengguna menunggu HTTP-nya: sampai 5 dtk koneksi + 10 dtk permintaan
 *     melekat pada respons halaman.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Job-nya DIANTREKAN, dan tidak ada satu pun HTTP keluar selama permintaan.
 * Keduanya ditegaskan bersama: mengantre sambil tetap menembak HTTP akan lolos
 * kalau hanya salah satunya diperiksa.
 */
class NotifikasiApproverIzinLewatAntreanTest extends TestCase
{
    use DatabaseTransactions;

    public function test_notifikasi_approver_diantrekan_dan_tak_ada_http_di_permintaan(): void
    {
        $approver = DB::table('Karyawan')
            ->where('HP', 'like', '62%')
            ->whereNotNull('UserID_Web')
            ->where('Aktif', 'Y')
            ->first(['Kode_Karyawan', 'Nama', 'HP', 'UserID_Web']);

        if (! $approver) {
            $this->markTestSkipped('Tak ada karyawan ber-HP 62… di data ini.');
        }

        $kodeApprover = trim((string) $approver->Kode_Karyawan);
        $kodeRequester = 'ZR'.substr(bin2hex(random_bytes(3)), 0, 6);

        DB::table('HRIS_Approval_Flow')->insert([
            'Kode_Karyawan_Requester' => $kodeRequester,
            'Kode_Karyawan' => $kodeApprover,
            'Division_Id' => 0,
            'level_Id' => 0,
            'order_flow' => 1,
        ]);

        // Payload memakai Auth::user()->karyawan->Nama sebagai nama pemohon.
        $user = User::query()->where('Id_Users', $approver->UserID_Web)->first();
        if (! $user || ! $user->karyawan) {
            $this->markTestSkipped('User contoh tak punya relasi karyawan.');
        }
        Auth::login($user);

        Queue::fake();
        Http::preventStrayRequests();

        $metode = new ReflectionMethod(AbsensiController::class, 'notifikasiApproverIzin');
        $metode->setAccessible(true);

        // Http::preventStrayRequests() melempar bila ada HTTP keluar yang tak
        // di-fake — jadi jalur lama (Whatsapp::send_message) akan MELEDAK di
        // sini, bukan lolos diam-diam.
        $metode->invoke(
            app(AbsensiController::class),
            $kodeRequester,
            'UJI-IZIN-'.strtoupper(bin2hex(random_bytes(3))),
            'sakit',
            '10 Sep 2026',
        );

        $this->assertGreaterThan(
            0,
            Queue::pushed(SendWhatsappJob::class)->count(),
            'Notifikasi approver izin tidak diantrekan. Selama ia memanggil WhatsApp '.
            'langsung, tak akan pernah ada dispatch di Cloud Tasks — dan tak ada '.
            'percobaan ulang ketika pengiriman gagal.',
        );
    }
}
