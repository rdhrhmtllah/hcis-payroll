<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use App\Services\SYSTEM\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi IN_APP ke seluruh anggota "kolam role" pada langkah approval ROLE.
 *
 * ── Kenapa ini punya rumah ───────────────────────────────────────────────────
 *
 * Tiga hal tersalin utuh antara CutiNotificationService dan
 * KpiNotificationService — ~150 baris, dan salah satunya BYTE-PER-BYTE:
 *
 *   resolveCurrentRoleStep()          identik persis (30 baris)
 *   notifyRolePoolForCurrentStep()    identik kecuali delapan nilai domain
 *   resolveRolePoolNotifications()    identik kecuali SATU saringan
 *
 * Salinan kedua lahir dengan sengaja — komentar kelas KpiNotificationService
 * masih menyebut dirinya "Mirror pola CutiNotificationService". Persoalannya
 * bukan niatnya, melainkan bahwa cermin itu langsung retak: saringan yang
 * hilang di bawah ini sudah membuat keduanya berperilaku beda.
 *
 * ── Saringan Is_Deleted yang hilang di sisi KPI ──────────────────────────────
 *
 * selesaikan() dulu ditulis dua kali. Versi cuti menyaring `Is_Deleted='T'`
 * (di tabel ini 'T' = belum dihapus, 'Y' = sudah di-dismiss — lihat
 * NotificationService yang menulis 'T' saat membuat dan 'Y' saat men-dismiss).
 * Versi KPI TIDAK menyaring apa pun.
 *
 * Akibatnya setiap kali sebuah transaksi KPI di-approve/reject, UPDATE-nya juga
 * menyentuh notifikasi yang sudah lama di-dismiss dan MENIMPA Read_At mereka
 * dengan waktu sekarang — jejak "kapan sebenarnya dibaca" hilang, dan barisnya
 * ikut terkunci percuma (RCSI mati di basis data ini, jadi baris yang di-UPDATE
 * menahan pembaca).
 *
 * Bentuk cuti yang dipakai di sini: lebih benar sekaligus lebih murah.
 *
 * ── Yang SENGAJA tetap milik pemanggil ───────────────────────────────────────
 *
 * Judul, pesan, module, action_url, dan isi meta adalah bahasa domain
 * masing-masing. siarkan() memanggil penyusun muatannya SEKALI saja — setelah
 * kolamnya terbukti tidak kosong — supaya pemanggil bebas melakukan kueri label
 * di dalamnya tanpa berubah menjadi N+1 sepanjang jumlah anggota.
 */
class NotifikasiKolamRole
{
    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Langkah ROLE yang sedang pending untuk sebuah transaksi.
     *
     * Generik: lewat HRIS_Approval_Request + N_HRIS_Jenis_Approval
     * (Source_Type='ROLE'), tanpa menyebut modul mana pun. Sebuah langkah
     * dianggap "berjalan" kalau tidak ada langkah ber-Request_Order_Flow lebih
     * kecil yang belum disetujui.
     */
    public function langkahBerjalan(string $noTransaksi): ?object
    {
        return DB::table('HRIS_Approval_Request as ar')
            ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'ar.Id_Jenis_Approval')
            ->where('ar.No_Transaksi', $noTransaksi)
            ->whereNull('ar.Flag_Approval')
            ->where('ar.Flag_Group', 'Y')
            ->where('ja.Source_Type', 'ROLE')
            ->where('ja.Flag_Aktif', 'Y')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('HRIS_Approval_Request as prev')
                    ->whereColumn('prev.No_Transaksi', 'ar.No_Transaksi')
                    ->whereColumn('prev.Request_Order_Flow', '<', 'ar.Request_Order_Flow')
                    ->where(function ($w) {
                        $w->whereNull('prev.Flag_Approval')->orWhere('prev.Flag_Approval', '<>', 'Y');
                    });
            })
            ->orderBy('ar.Request_Order_Flow', 'asc')
            ->select(
                'ar.id as id_approval_request',
                'ar.No_Transaksi',
                'ar.Group_Reference_Id',
                'ar.Request_Order_Flow',
                'ja.Source_Reference as role_code',
                'ja.Nama_Approval',
            )
            ->first();
    }

    /**
     * Sebar notif ke seluruh anggota kolam role pada langkah yang sedang
     * berjalan. No-op bila langkahnya bukan ROLE atau kolamnya kosong.
     *
     * @param  string  $prefixKunci  dipakai untuk Notification_Key DAN kolom
     *                               type — keduanya memang bernilai sama di
     *                               kedua pemanggil (CUTI_ROLE_REVIEW /
     *                               KPI_ROLE_REVIEW), dan selesaikan()
     *                               mencocokkan awalan yang sama.
     * @param  callable  $susunMuatan  fn(object $langkah): array — dipanggil
     *                                 SEKALI, mengembalikan bagian muatan yang
     *                                 sama untuk semua anggota (title, message,
     *                                 module, severity, action_url, meta).
     *                                 kode_karyawan, type, notification_key dan
     *                                 sisa pipa diisi di sini.
     * @return int jumlah notif yang terkirim atau di-refresh
     */
    public function siarkan(
        string $noTransaksi,
        string $prefixKunci,
        string $kanalLog,
        callable $susunMuatan,
    ): int {
        $noTransaksi = trim($noTransaksi);
        if ($noTransaksi === '') {
            return 0;
        }

        try {
            $langkah = $this->langkahBerjalan($noTransaksi);
            if (! $langkah) {
                return 0;
            }

            $anggota = app(AnggotaApprovalResolver::class)->dariRole((int) $langkah->Group_Reference_Id);
            if ($anggota->isEmpty()) {
                Log::channel($kanalLog)->info('Kolam role kosong saat notif approval', [
                    'prefix' => $prefixKunci,
                    'no_transaksi' => $noTransaksi,
                    'id_role' => $langkah->Group_Reference_Id,
                    'role_code' => $langkah->role_code ?? null,
                ]);

                return 0;
            }

            /*
             * Dipanggil di sini — sesudah kolam terbukti berisi, sebelum
             * pengulangan dimulai. Pemanggil boleh melakukan kueri label di
             * dalamnya; itu tetap satu kueri, bukan satu per anggota.
             */
            $muatan = $susunMuatan($langkah);
            $terkirim = 0;

            foreach ($anggota as $orang) {
                $kodeAdmin = trim((string) ($orang->Kode_Karyawan ?? ''));
                if ($kodeAdmin === '') {
                    continue;
                }

                $kunciNotif = implode(':', [
                    $prefixKunci,
                    $noTransaksi,
                    (int) ($langkah->Request_Order_Flow ?? 0),
                    (int) $langkah->Group_Reference_Id,
                    $kodeAdmin,
                ]);

                try {
                    $id = $this->notificationService->upsertByKey(array_merge($muatan, [
                        'kode_karyawan' => $kodeAdmin,
                        'type' => $prefixKunci,
                        'channel' => NotificationService::DELIVERY_IN_APP,
                        'notification_key' => $kunciNotif,
                        'revive_deleted' => true,
                        'reference_id' => $noTransaksi,
                        'created_by' => 'SYSTEM_APPROVAL',
                        'priority' => 2,
                    ]));

                    if ($id) {
                        $terkirim++;
                    }
                } catch (\Throwable $e) {
                    // Satu anggota gagal tidak boleh menghentikan anggota lain.
                    Log::warning('Notif kolam role gagal untuk satu anggota', [
                        'prefix' => $prefixKunci,
                        'no_transaksi' => $noTransaksi,
                        'kode_admin' => $kodeAdmin,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return $terkirim;
        } catch (\Throwable $e) {
            Log::warning('Notif kolam role gagal', [
                'prefix' => $prefixKunci,
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Tandai seluruh notif kolam role untuk transaksi ini sebagai selesai
     * (dibaca + di-dismiss), supaya tray admin lain ikut bersih setelah salah
     * satu dari mereka approve/reject.
     *
     * Saringan Is_Deleted='T' itu WAJIB — lihat catatan kelas. Tanpa saringan
     * itu UPDATE-nya menimpa Read_At notifikasi yang sudah lama di-dismiss.
     *
     * @return int jumlah baris yang ditandai
     */
    public function selesaikan(string $noTransaksi, string $prefixKunci): int
    {
        $noTransaksi = trim($noTransaksi);
        if ($noTransaksi === '') {
            return 0;
        }

        try {
            return DB::table('N_HRIS_Notification')
                ->where('Notification_Key', 'like', "{$prefixKunci}:{$noTransaksi}:%")
                ->where('Is_Deleted', 'T')
                ->update([
                    'Is_Read' => 'Y',
                    'Is_Deleted' => 'Y',
                    'Read_At' => now(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('Penyelesaian notif kolam role gagal', [
                'prefix' => $prefixKunci,
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
