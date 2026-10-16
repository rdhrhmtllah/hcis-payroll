<?php

namespace App\Services\KPI\Notification;

use App\Services\ESS\Attendance\Leave\Notification\CutiNotificationService;
use App\Services\SYSTEM\Governance\Approval\Shared\ApprovalService;
use App\Services\SYSTEM\Governance\Approval\Shared\NotifikasiKolamRole;
use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\SYSTEM\Notification\WhatsappNotificationMirrorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * KpiNotificationService
 *
 * Notifikasi IN-APP (topbar) untuk approval KPI yang masuk ke step VERIFIKATOR
 * ROLE/GROUP (pool admin). Mirror pola CutiNotificationService::notifyRolePoolForCurrentStep
 * tetapi konteks KPI:
 *   - judul/message KPI (parameter vs performance)
 *   - action_url ke halaman Verifikasi KPI.
 *
 * Dipanggil dari ApprovalService saat step beralih ke role pool (KPI_PARAM_STD /
 * KPI_PERF_STD -> step ROLE_KPI_VERIF). Tanpa ini, member role pool tidak menerima
 * notifikasi karena pola serial (Current_Approver) tidak berlaku untuk group.
 */
class KpiNotificationService
{
    public const TYPE_ROLE_REVIEW = 'KPI_ROLE_REVIEW';

    public const TYPE_PERF_REJECTED = 'KPI_PERF_REJECTED';

    public const TYPE_PARAM_REJECTED = 'KPI_PARAM_REJECTED';

    public const META_SOURCE = 'MAIN_NOTIF';

    public function __construct(protected NotificationService $notificationService) {}

    /**
     * Tentukan sebuah No_Transaksi milik domain yang mana, sekaligus ambil
     * barisnya.
     *
     * Resolusi lewat LOOKUP TABEL, bukan prefiks nomor. Prefiksnya memang
     * berbeda hari ini ('PKPI...' untuk parameter, 'AKPI-...' untuk performance,
     * nol tumpang tindih), tapi prefiks adalah konvensi penamaan yang bisa
     * berubah kapan saja; keberadaan baris di tabel tidak.
     *
     * Baris yang dikembalikan harus memuat Id_User, Id_User_Sender, dan
     * Flag_Team, karena itulah yang dibaca resolveRejectionTarget() untuk
     * memutuskan notifikasi dikirim ke leader atau ke karyawannya sendiri.
     *
     * @return array{0: ?string, 1: ?object} ['parameter'|'performance', baris] atau [null, null]
     */
    protected function resolveRejectionDomain(string $noTransaksi): array
    {
        $noTransaksi = trim($noTransaksi);
        if ($noTransaksi === '') {
            return [null, null];
        }

        $parameter = DB::table('KPI_Pengajuan')
            ->where('No_Transaksi', $noTransaksi)
            ->orderByDesc('Id_Pengajuan')
            ->first(['Id_Pengajuan', 'No_Transaksi', 'Id_User', 'Id_User_Sender', 'Flag_Team', 'Id_Penjadwalan']);

        if ($parameter) {
            return ['parameter', $parameter];
        }

        $performance = DB::table('KPI_Update_Performance')
            ->where('No_Transaksi', $noTransaksi)
            ->orderByDesc('Id_Update_Performance')
            ->first(['Id_Update_Performance', 'No_Transaksi', 'Id_User', 'Id_User_Sender', 'Flag_Team', 'Periode']);

        if ($performance) {
            return ['performance', $performance];
        }

        return [null, null];
    }

    /**
     * Label periode untuk badan notifikasi.
     *
     * Parameter KPI berlaku SETAHUN penuh, jadi yang bermakna adalah tahun
     * jadwalnya. Update Performance berlaku per bulan, jadi yang bermakna
     * adalah kolom Periode-nya sendiri.
     */
    protected function labelPeriodePenolakan(?string $domain, object $pengajuan): string
    {
        if ($domain === 'parameter') {
            $tahun = DB::table('KPI_Master_Penjadwalan')
                ->where('Id_Master_Penjadwalan', $pengajuan->Id_Penjadwalan ?? null)
                ->value('Tahun');

            return $tahun ? 'Tahun: '.$tahun : '';
        }

        $periode = trim((string) ($pengajuan->Periode ?? ''));

        return $periode !== '' ? 'Periode: '.$periode : '';
    }

    /**
     * Halaman tujuan saat notifikasi diklik: modul yang benar, dan varian tim
     * bila yang menerima adalah leader — dialah yang harus merevisi.
     */
    protected function tautanPenolakan(?string $domain, bool $isTeam): string
    {
        if ($domain === 'parameter') {
            return $isTeam ? '/form-kpi/team' : '/form-kpi';
        }

        return $isTeam ? '/update-performance/team' : '/update-performance';
    }

    /**
     * Notif IN-APP (topbar) ke pengaju saat Update Performance ditolak approver /
     * verifikator. Dipanggil SETELAH commit — best-effort, tidak pernah melempar.
     *
     * Penerima: karyawan yang dinilai, plus leader pengaju bila pengajuan tim
     * (leader-lah yang harus merevisi).
     *
     * @param  array<int|string,string>  $perParamComments  keyed by Id_Update_Performance_Detail
     * @return int jumlah notif terkirim
     */
    public function notifyRequesterRejected(string $noTransaksi, string $globalReason, array $perParamComments = []): int
    {
        $noTransaksi = trim($noTransaksi);
        if ($noTransaksi === '') {
            return 0;
        }

        try {
            [$domain, $pengajuan] = $this->resolveRejectionDomain($noTransaksi);
            if (! $pengajuan) {
                return 0;
            }

            // Target notif = leader (tim) atau karyawan itu sendiri (personal).
            $target = $this->resolveRejectionTarget($pengajuan);
            if (! $target) {
                Log::channel('kpiApprovalController')->warning('Penolakan performance tanpa penerima notif', [
                    'no_transaksi' => $noTransaksi,
                    'domain' => $domain,
                ]);

                return 0;
            }

            $rejectedCount = count(array_filter($perParamComments, fn ($c) => trim((string) $c) !== ''));
            $alasan = trim($globalReason) !== '' ? trim($globalReason) : 'Tidak ada alasan yang dicantumkan.';

            // Konteks tim: sebut milik siapa KPI-nya (agar leader tak mengira KPI-nya sendiri).
            $konteksKaryawan = ($target->is_team && $target->nama_karyawan !== '')
                ? " a.n. {$target->nama_karyawan}"
                : '';

            $isParameter = $domain === 'parameter';

            // Label periode berbeda per domain: parameter berlaku setahun,
            // performance berlaku per bulan. Sebelumnya baris ini memakai
            // $periode yang tidak pernah didefinisikan di method ini.
            $periode = $this->labelPeriodePenolakan($domain, $pengajuan);
            $tipeNotif = $isParameter ? self::TYPE_PARAM_REJECTED : self::TYPE_PERF_REJECTED;

            $title = $isParameter
                ? 'Pengajuan Parameter KPI Ditolak'
                : 'Update Performance KPI Ditolak';

            $menuTujuan = $isParameter ? 'Form KPI' : 'Update Performance';

            $message = "No. Transaksi: {$noTransaksi}\n"
                .($periode !== '' ? $periode."\n" : '')
                .($konteksKaryawan !== '' ? 'Karyawan: '.$target->nama_karyawan."\n" : '')
                ."Alasan: {$alasan}\n\n"
                .($rejectedCount > 0
                    ? "{$rejectedCount} parameter perlu direvisi. "
                    : '')
                .'Silakan perbaiki melalui menu '.$menuTujuan.'.';

            // Pakai notification_key yang SAMA dengan mirror WA (template
            // hcis_tolak_pengajuan_kpi) agar notif domain (dibuat segera, andal walau
            // tanpa HP) dan notif mirror WA (dibuat saat WA terkirim) MENYATU jadi satu
            // baris di top-bar — bukan dobel.
            $mirror = app(WhatsappNotificationMirrorService::class);

            $count = 0;
            try {
                $id = $this->notificationService->upsertByKey([
                    'kode_karyawan' => $target->kode,
                    'type' => $tipeNotif,
                    'title' => $title,
                    'message' => $message,
                    'module' => 'KPI',
                    'severity' => 'warning',
                    'action_url' => $this->tautanPenolakan($domain, (bool) $target->is_team),
                    'channel' => NotificationService::DELIVERY_IN_APP,
                    'notification_key' => $mirror->notificationKeyFor($target->kode, 'hcis_tolak_pengajuan_kpi', $noTransaksi),
                    'revive_deleted' => true,
                    'reference_id' => $noTransaksi,
                    'created_by' => 'SYSTEM_APPROVAL',
                    'priority' => 2,
                    'meta' => [
                        'source' => self::META_SOURCE,
                        'event_type' => $tipeNotif,
                        'domain' => $domain,
                        'no_transaksi' => $noTransaksi,
                        'periode' => $periode,
                        'alasan' => $alasan,
                        'rejected_param_count' => $rejectedCount,
                        'is_team' => $target->is_team,
                        'nama_karyawan' => $target->nama_karyawan,
                    ],
                ]);
                if ($id) {
                    $count++;
                }
            } catch (\Throwable $e) {
                Log::warning('KpiNotificationService notifyRequesterRejected penerima gagal', [
                    'no_transaksi' => $noTransaksi,
                    'kode_karyawan' => $target->kode,
                    'error' => $e->getMessage(),
                ]);
            }

            return $count;
        } catch (\Throwable $e) {
            Log::warning('KpiNotificationService::notifyRequesterRejected gagal', [
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Kirim WA penolakan Update Performance ke SATU penerima — leader (bila pengajuan
     * tim) atau karyawan itu sendiri (personal) — memakai template Meta
     * `hcis_tolak_pengajuan_kpi` (bahasa id). Lihat resolveRejectionTarget().
     *
     * Variabel body template (urut atas -> bawah):
     *   1. Nama            -> nama penerima
     *   2. Jenis Pengajuan -> $jenisPengajuan (mis. "Update Performance KPI")
     *   3. No_Transaksi    -> $noTransaksi
     *   4. Nama Approver   -> $namaApprover (approver/verifikator yang menolak)
     *
     * Dipanggil SETELAH commit. Pengiriman WA sendiri lewat WhatsappLogHelper yang
     * mengantre via Cloud Tasks (after_commit) — request tak menunggu HTTP maupun
     * menahan lock DB. Best-effort: kegagalan resolve/enqueue tak pernah melempar.
     */
    public function notifyRequesterRejectedWhatsapp(string $noTransaksi, string $jenisPengajuan = 'Update Performance KPI', ?string $namaApprover = null): void
    {
        $noTransaksi = trim($noTransaksi);
        if ($noTransaksi === '') {
            return;
        }

        try {
            [$domain, $pengajuan] = $this->resolveRejectionDomain($noTransaksi);
            if (! $pengajuan) {
                return;
            }

            $target = $this->resolveRejectionTarget($pengajuan);
            if (! $target) {
                return;
            }

            $hp = trim((string) ($target->hp ?? ''));
            if ($hp === '') {
                return;
            }

            $approver = trim((string) $namaApprover) !== '' ? trim((string) $namaApprover) : 'Approver';
            $baseJenis = trim($jenisPengajuan) !== '' ? trim($jenisPengajuan) : 'Update Performance KPI';

            // Konteks tim: leader mengisikan KPI anggota → sebut "a.n. {nama karyawan}"
            // agar tidak terbaca seolah KPI milik leader sendiri.
            $jenis = ($target->is_team && $target->nama_karyawan !== '')
                ? $baseJenis.' a.n. '.$target->nama_karyawan
                : $baseJenis;

            $pesan = [
                'messaging_product' => 'whatsapp',
                'to' => $hp,
                'type' => 'template',
                'template' => [
                    'name' => 'hcis_tolak_pengajuan_kpi',
                    'language' => ['code' => 'id', 'policy' => 'deterministic'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => (string) ($target->nama ?? '-')],
                            ['type' => 'text', 'text' => $jenis],
                            ['type' => 'text', 'text' => $noTransaksi],
                            ['type' => 'text', 'text' => $approver],
                        ],
                    ]],
                ],
            ];

            try {
                \App\Helpers\WhatsappLogHelper::sendAndLog(
                    $target->kode,
                    $target->nama,
                    $hp,
                    $noTransaksi,
                    $pesan,
                );
            } catch (\Throwable $e) {
                Log::channel('kpiApprovalController')->warning('Gagal antre WA penolakan KPI', [
                    'no_transaksi' => $noTransaksi,
                    'kode_karyawan' => $target->kode ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('KpiNotificationService::notifyRequesterRejectedWhatsapp gagal', [
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Penerima penolakan Update Performance + konteksnya.
     *
     * Pengajuan TIM (Flag_Team='Y' & Id_User_Sender ada & != Id_User): target = LEADER
     * (Id_User_Sender) yang mengisi & merevisi; sertakan nama karyawan yang dinilai
     * untuk konteks "a.n.". Karyawan yang dinilai TIDAK dinotifikasi (tak bisa revisi
     * sendiri di alur tim).
     *
     * Pengajuan PERSONAL: target = karyawan itu sendiri (Id_User), tanpa konteks "a.n.".
     *
     * @return object|null { kode, nama, hp, is_team, nama_karyawan }
     */
    protected function resolveRejectionTarget(object $pengajuan): ?object
    {
        $idUser = $pengajuan->Id_User ?? null;
        $idSender = $pengajuan->Id_User_Sender ?? null;

        $isTeam = ($pengajuan->Flag_Team ?? null) === 'Y'
            && ! empty($idSender)
            && (string) $idSender !== (string) $idUser;

        $targetUserId = $isTeam ? $idSender : $idUser;
        if (empty($targetUserId)) {
            return null;
        }

        $target = DB::table('Karyawan')->where('UserID_Web', $targetUserId)->first(['Kode_Karyawan', 'Nama', 'HP']);
        if (! $target) {
            return null;
        }

        $namaKaryawan = '';
        if ($isTeam) {
            $namaKaryawan = (string) (DB::table('Karyawan')->where('UserID_Web', $idUser)->value('Nama') ?? '');
        }

        return (object) [
            'kode' => $target->Kode_Karyawan,
            'nama' => $target->Nama,
            'hp' => $target->HP,
            'is_team' => $isTeam,
            'nama_karyawan' => $namaKaryawan,
        ];
    }

    /**
     * Kirim notif IN_APP ke seluruh member kolam role bila langkah approval KPI
     * yang sedang berjalan bertipe ROLE. No-op bila bukan ROLE / role tanpa
     * member.
     *
     * Pipa-nya milik NotifikasiKolamRole dan dipakai bersama modul cuti; yang
     * tinggal di sini hanya bahasa domain KPI.
     *
     * @return int jumlah notif terkirim/di-refresh
     */
    public function notifyRolePoolForCurrentStep(string $noTransaksi): int
    {
        return app(NotifikasiKolamRole::class)->siarkan(
            $noTransaksi,
            self::TYPE_ROLE_REVIEW,
            'kpiApprovalController',
            function (object $langkah) use ($noTransaksi): array {
                [$requesterNama, $jenisLabel] = $this->resolveContextLabels($noTransaksi);

                return [
                    'title' => "{$jenisLabel} Menunggu Verifikasi",
                    'message' => "{$requesterNama} mengajukan {$jenisLabel} (No. {$noTransaksi}). Mohon diverifikasi.",
                    'module' => 'KPI',
                    'severity' => 'warning',
                    'action_url' => '/kpi-verifikasi',
                    'meta' => [
                        'source' => self::META_SOURCE,
                        'event_type' => self::TYPE_ROLE_REVIEW,
                        'no_transaksi' => $noTransaksi,
                        'requester_nama' => $requesterNama,
                        'jenis_kpi' => $jenisLabel,
                        'role_code' => $langkah->role_code ?? null,
                        'id_role' => (int) $langkah->Group_Reference_Id,
                        'id_approval_request' => (int) $langkah->id_approval_request,
                        'order_flow' => (int) ($langkah->Request_Order_Flow ?? 0),
                    ],
                ];
            },
        );
    }

    /**
     * Tandai semua notif kolam role KPI untuk transaksi sebagai selesai (dibaca
     * + di-dismiss) — dipanggil setelah verifikator approve/reject agar tray
     * admin lain ikut bersih.
     *
     * PERUBAHAN PERILAKU YANG DISENGAJA: versi lama di sini tidak menyaring
     * Is_Deleted sama sekali, jadi UPDATE-nya ikut menimpa Read_At notifikasi
     * yang sudah lama di-dismiss. Rumah bersamanya memakai bentuk cuti yang
     * menyaring Is_Deleted='T'. Alasan lengkapnya di NotifikasiKolamRole.
     */
    public function resolveRolePoolNotifications(string $noTransaksi): int
    {
        return app(NotifikasiKolamRole::class)->selesaikan($noTransaksi, self::TYPE_ROLE_REVIEW);
    }

    /**
     * Nama requester + label jenis (KPI Parameter / Update Performance) dari
     * domain yang sesuai (KPI_Pengajuan vs KPI_Update_Performance).
     *
     * @return array{0:string,1:string}
     */
    protected function resolveContextLabels(string $noTransaksi): array
    {
        // Performance?
        $perf = DB::table('KPI_Update_Performance as up')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'up.Id_User')
            ->where('up.No_Transaksi', $noTransaksi)
            ->select('k.Nama')
            ->first();
        if ($perf) {
            return [(string) ($perf->Nama ?? 'Karyawan'), 'Update Performance KPI'];
        }

        // Parameter (pengajuan).
        $param = DB::table('KPI_Pengajuan as p')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'p.Id_User')
            ->where('p.No_Transaksi', $noTransaksi)
            ->select('k.Nama')
            ->first();
        if ($param) {
            return [(string) ($param->Nama ?? 'Karyawan'), 'Pengajuan Parameter KPI'];
        }

        return ['Karyawan', 'KPI'];
    }
}
