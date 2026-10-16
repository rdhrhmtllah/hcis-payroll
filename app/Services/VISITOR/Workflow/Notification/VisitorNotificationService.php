<?php

namespace App\Services\VISITOR\Workflow\Notification;

use App\Services\SYSTEM\Notification\NotificationService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Notifikasi in-app modul VISITOR.
 *
 * Dua peristiwa, dua arah yang berlawanan:
 *
 *  - PENGAJUAN masuk antrean -> ke pemegang role verifikator domainnya
 *    (ADMIN_PERMIT / ADMIN_TAMU), supaya ada yang tahu harus memprosesnya.
 *  - KEPUTUSAN verifikator -> ke PEMOHON transaksinya, supaya ia tahu hasilnya
 *    tanpa harus membuka halaman dan memeriksa sendiri.
 *
 * Arah kedua punya satu pengecualian yang disengaja: pengajuan lewat API tidak
 * berpemohon manusia, dan menuliskan notifikasi untuknya menghasilkan baris
 * yang tidak pernah terbaca siapa pun. Lihat pemohonDapatDikabari().
 */
class VisitorNotificationService
{
    public const DOMAIN_TAMU = 'TAMU';

    /** Tamu B2B: tabel sama dengan TAMU, pool verifikator & halaman sendiri. */
    public const DOMAIN_TAMU_B2B = 'TAMU_B2B';

    public const DOMAIN_PERMIT = 'PERMIT';

    public const MODULE = 'VISITOR';

    public const TYPE_PENGAJUAN_BARU = 'VISITOR_PENGAJUAN_BARU';

    public const TYPE_KEPUTUSAN = 'VISITOR_KEPUTUSAN';

    public const KEPUTUSAN_DISETUJUI = 'DISETUJUI';

    public const KEPUTUSAN_DITOLAK = 'DITOLAK';

    public const KEPUTUSAN_DIBATALKAN = 'DIBATALKAN';

    /** Bukan keputusan akhir: petugas memindah jadwal inspeksi pemohon. */
    public const KEPUTUSAN_JADWAL_DIPINDAH = 'JADWAL_DIPINDAH';

    /** Bukan keputusan permit: SHE mencatat tamu B2B tidak datang pada jadwal kunjungannya. */
    public const KEPUTUSAN_TAMU_TIDAK_HADIR = 'TAMU_TIDAK_HADIR';

    /** Bukan keputusan siapa pun: batas approval permit lewat tanpa diputus. */
    public const KEPUTUSAN_KEDALUWARSA = 'KEDALUWARSA';

    /**
     * Halaman verifikator, PER DOMAIN (DML-2026-105).
     *
     * URL gabungan lamanya masih hidup sebagai pengalihan, tetapi menautkan ke
     * sana menambah satu lompatan pada setiap notifikasi — dan pengalihannya
     * memilih domain dari query string, yang justru berasal dari sini.
     */
    public const ACTION_URL_PER_DOMAIN = [
        self::DOMAIN_PERMIT => '/verifikator-visitor/permit',
        self::DOMAIN_TAMU => '/verifikator-visitor/tamu',
        self::DOMAIN_TAMU_B2B => '/verifikator-visitor/b2b',
    ];

    /**
     * WAJIB 'MAIN_NOTIF': AppTopbar.vue dan AppNotificationModal.vue keduanya
     * meminta source='MAIN_NOTIF'. Nilai lain tetap tersimpan di tabel tetapi
     * tidak pernah sampai ke lonceng maupun Pusat Notifikasi — gagal senyap.
     * Asal-usul peristiwa dibawa kolom Type + meta.event_type, bukan source.
     */
    public const META_SOURCE = 'MAIN_NOTIF';

    private const KEY_PREFIX = 'visitor_pengajuan_baru';

    private const KEY_PREFIX_KEPUTUSAN = 'visitor_keputusan';

    /**
     * Halaman milik pemohon, tempat detail transaksinya dibuka.
     */
    private const HALAMAN_PEMOHON = [
        self::DOMAIN_PERMIT => '/transaksipermit',
        self::DOMAIN_TAMU => '/transaksitamu',
        self::DOMAIN_TAMU_B2B => '/transaksi-tamu-b2b',
    ];

    /**
     * Role penerima antrean verifikasi, PER DOMAIN.
     *
     * Sejak antreannya dipecah (DML-2026-108), permit dan tamu punya pool
     * verifikator sendiri. Satu role untuk keduanya berarti orang yang tidak
     * berwenang atas sebuah domain tetap menerima notifikasinya — lalu membuka
     * tautan yang dibalas 403 oleh halamannya.
     *
     * Kode role ini HARUS sama dengan Source_Reference pada config approval
     * masing-masing. Bila berbeda, notifikasinya terkirim ke orang yang bukan
     * approver — antreannya nyata, penerimanya salah, dan tidak ada galat yang
     * menunjukkannya. Penjaganya di RoleNotifikasiVerifikatorTest.
     */
    public const ROLE_PER_DOMAIN = [
        self::DOMAIN_PERMIT => 'ADMIN_PERMIT',
        self::DOMAIN_TAMU => 'ADMIN_TAMU',
        self::DOMAIN_TAMU_B2B => 'ADMIN_TAMU_B2B',
    ];

    public function __construct(private NotificationService $notificationService) {}

    /**
     * Kode_Karyawan pemegang role verifikator domain itu, keanggotaan aktif.
     *
     * DISTINCT bukan hiasan: satu orang bisa punya lebih dari satu baris
     * keanggotaan pada role yang sama, dan tanpa ini ia menerima notif kembar.
     *
     * @return array<int,string>
     */
    public function penerimaAdmin(string $domain): array
    {
        $this->pastikanDomain($domain);

        $role = self::ROLE_PER_DOMAIN[$domain];

        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', function ($join) use ($role) {
                $join->on('rf.Id_Role', '=', 'ru.Id_Role')
                    ->where('rf.Role_Code', '=', $role)
                    ->where('rf.Flag_Aktif', '=', 'Y');
            })
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
            ->where('ru.Flag_Aktif', '=', 'Y')
            ->whereNotNull('k.Kode_Karyawan')
            ->distinct()
            ->pluck('k.Kode_Karyawan')
            ->map(fn ($kode) => trim((string) $kode))
            ->filter(fn ($kode) => $kode !== '')
            ->values()
            ->all();
    }

    /**
     * Terbitkan notifikasi "pengajuan baru menunggu verifikasi" ke seluruh Admin SHE.
     *
     * Dedup lewat Notification_Key, sehingga submit ulang / idempotent replay atas
     * No_Transaksi yang sama tidak melahirkan baris kedua.
     *
     * @param  array<string,mixed>  $ctx
     * @return int jumlah penerima
     */
    public function notifikasiPengajuanBaru(string $domain, string $noTransaksi, array $ctx = []): int
    {
        $this->pastikanDomain($domain);

        $penerima = $this->penerimaAdmin($domain);
        if ($penerima === []) {
            return 0;
        }

        // Id numerik dipakai tautan detail di halaman verifikator.
        $transaksi = $this->transaksi($domain, $noTransaksi);
        $idTransaksi = $transaksi['id'] ?? 0;

        $this->notificationService->upsertManyByKey(array_map(
            fn (string $kode) => $this->bangunPayload($kode, $idTransaksi, $domain, $noTransaksi, $ctx),
            $penerima,
        ));

        return count($penerima);
    }

    /**
     * Terbitkan notifikasi keputusan verifikator ke PEMOHON transaksi.
     *
     * Pemohon dibaca dari baris transaksinya, bukan dari request: yang menekan
     * tombol adalah verifikator. Satu lookup itu sekaligus mengambil Id numerik
     * yang dipakai tautan detail.
     *
     * @param  array<string,mixed>  $ctx
     * @return int 1 bila terkirim, 0 bila transaksi/pemohon tak ditemukan
     */
    public function notifikasiKeputusan(
        string $domain,
        string $noTransaksi,
        string $keputusan,
        array $ctx = [],
    ): int {
        $this->pastikanDomain($domain);
        $this->pastikanKeputusan($keputusan);

        $transaksi = $this->transaksi($domain, $noTransaksi);
        if ($transaksi === null) {
            return 0;
        }

        if (! $this->pemohonDapatDikabari($transaksi, $domain, $noTransaksi)) {
            return 0;
        }

        $this->notificationService->upsertByKey($this->bangunPayloadKeputusan(
            $transaksi['kode_karyawan'],
            $transaksi['id'],
            $domain,
            $noTransaksi,
            $keputusan,
            $ctx,
        ));

        return 1;
    }

    /**
     * Pemohon + Id numerik transaksi, atau null bila tidak ditemukan.
     *
     * `adalah_karyawan` ikut dibaca DI SINI sebagai subkueri EXISTS, bukan lewat
     * kueri kedua: pembatalan massal memanggil metode ini seratus kali per job,
     * dan satu kueri tambahan per panggilan pada basis data jarak jauh menambah
     * detik-detik yang tidak terlihat siapa pun saat menuliskannya.
     *
     * @return array{kode_karyawan:string,id:int,adalah_karyawan:bool}|null
     */
    private function transaksi(string $domain, string $noTransaksi): ?array
    {
        [$tabel, $kolomId, $kolomPemohon] = $domain === self::DOMAIN_PERMIT
            ? ['N_EMI_VISITOR_Transaksi_Permit', 'Id_Transaksi_Permit', 'Kode_Karyawan_Pemohon']
            : ['N_EMI_VISITOR_Transaksi_Tamu', 'Id_Transaksi_Tamu', 'Kode_Karyawan_PIC'];

        $row = DB::table($tabel.' as t')
            ->where('t.No_Transaksi', $noTransaksi)
            ->first([
                't.'.$kolomId,
                't.'.$kolomPemohon,
                // EXISTS, bukan JOIN: Kode_Karyawan tidak unik sendirian (PK-nya
                // komposit dengan Kode_Perusahaan), sehingga JOIN melipatgandakan
                // barisnya. Yang ditanyakan memang cuma ADA atau TIDAK.
                DB::raw(
                    'CASE WHEN EXISTS ('
                        .'SELECT 1 FROM Karyawan k WHERE k.Kode_Karyawan = t.'.$kolomPemohon
                    .") THEN 'Y' ELSE 'T' END as Pemohon_Adalah_Karyawan"
                ),
            ]);

        if ($row === null) {
            return null;
        }

        $kode = trim((string) ($row->{$kolomPemohon} ?? ''));
        if ($kode === '') {
            return null;
        }

        return [
            'kode_karyawan' => $kode,
            'id' => (int) $row->{$kolomId},
            'adalah_karyawan' => ($row->Pemohon_Adalah_Karyawan ?? 'T') === 'Y',
        ];
    }

    /**
     * Apakah pemohon transaksi ini orang yang bisa menerima notifikasi?
     *
     * N_HRIS_Notification tidak punya foreign key ke Karyawan dan kolom
     * Kode_Karyawan-nya varchar biasa, jadi menulis notifikasi untuk pemohon
     * yang bukan orang BERHASIL — tanpa galat, tanpa job yang gagal. Barisnya
     * tersimpan rapi dan tidak pernah muncul di lonceng siapa pun, karena tidak
     * ada yang login sebagai 'API#233'. Kegagalan sejenis itu baru ketahuan
     * kalau ada yang kebetulan menghitung isi tabelnya.
     *
     * Dua keadaan ditolak, dan bedanya penting:
     *
     *  - PENANDA APLIKASI ('API#<id>') — wajar, dan karena itu diam. Permit
     *    mitra memang diajukan aplikasi, bukan orang; mitra dirancang mengetahui
     *    keputusannya lewat endpoint status, bukan lewat lonceng HCIS.
     *  - Kode yang bukan aplikasi tetapi juga bukan karyawan — TIDAK wajar. Itu
     *    pemohon sungguhan yang kehilangan kabar keputusannya, jadi dicatat
     *    supaya ada jejak yang bisa ditelusuri, bukan hilang sama diamnya.
     *
     * @param  array{kode_karyawan:string,id:int,adalah_karyawan:bool}  $transaksi
     */
    private function pemohonDapatDikabari(array $transaksi, string $domain, string $noTransaksi): bool
    {
        if (str_starts_with($transaksi['kode_karyawan'], VisitorPermitApiService::PREFIX_PEMOHON_APLIKASI)) {
            return false;
        }

        if ($transaksi['adalah_karyawan']) {
            return true;
        }

        Log::warning('VISITOR_NOTIF_KEPUTUSAN_PEMOHON_TIDAK_DIKENAL', [
            'domain' => $domain,
            'no_transaksi' => $noTransaksi,
            'kode_karyawan' => $transaksi['kode_karyawan'],
        ]);

        return false;
    }

    /**
     * Kunci dedup notifikasi: satu baris per (domain, transaksi, penerima).
     */
    public static function kunciNotifikasi(string $domain, string $noTransaksi, string $kodeKaryawan): string
    {
        return self::KEY_PREFIX.':'.$domain.':'.$noTransaksi.':'.$kodeKaryawan;
    }

    /**
     * Bangun payload upsert untuk satu penerima.
     *
     * @param  array<string,mixed>  $ctx
     * @return array<string,mixed>
     */
    public function bangunPayload(
        string $kodeKaryawan,
        int $idTransaksi,
        string $domain,
        string $noTransaksi,
        array $ctx = [],
    ): array {
        $this->pastikanDomain($domain);
        $isPermit = $domain === self::DOMAIN_PERMIT;

        return [
            'kode_karyawan' => $kodeKaryawan,
            'type' => self::TYPE_PENGAJUAN_BARU,
            'title' => $isPermit
                ? 'Pengajuan Permit Kendaraan Baru'
                : ($domain === self::DOMAIN_TAMU_B2B ? 'Pengajuan Tamu B2B Baru' : 'Pengajuan Tamu Baru'),
            'message' => $isPermit
                ? $this->pesanPermit($noTransaksi, $ctx)
                : $this->pesanTamu($noTransaksi, $ctx),
            'module' => self::MODULE,
            'severity' => 'info',
            // Tanpa Id yang sah, tautan tetap membuka halaman verifikator —
            // lebih baik daripada tautan detail yang pasti gagal dibuka.
            'action_url' => $idTransaksi > 0
                ? self::ACTION_URL_PER_DOMAIN[$domain].'?detail='.VisitorDetailToken::buat(self::domainToken($domain), $idTransaksi)
                    .'&jenis='.($isPermit ? 'permit' : 'tamu')
                : self::ACTION_URL_PER_DOMAIN[$domain],
            'channel' => NotificationService::DELIVERY_IN_APP,
            'notification_key' => self::kunciNotifikasi($domain, $noTransaksi, $kodeKaryawan),
            'reference_id' => $noTransaksi,
            'created_by' => 'SYSTEM_VISITOR',
            'priority' => 2,
            'meta' => [
                'source' => self::META_SOURCE,
                'event_type' => self::TYPE_PENGAJUAN_BARU,
                'domain' => $domain,
                'no_transaksi' => $noTransaksi,
            ],
        ];
    }

    /**
     * Bangun payload keputusan (disetujui / ditolak / dibatalkan) untuk pemohon.
     *
     * `action_url` menautkan langsung ke detail transaksi di halaman pemohon.
     * Menempelkan Id milik orang lain tidak membocorkan apa pun: endpoint
     * `show()` kedua controller sudah menyaring pemohonnya sendiri.
     *
     * @param  array<string,mixed>  $ctx
     * @return array<string,mixed>
     */
    public function bangunPayloadKeputusan(
        string $kodeKaryawan,
        int $idTransaksi,
        string $domain,
        string $noTransaksi,
        string $keputusan,
        array $ctx = [],
    ): array {
        $this->pastikanDomain($domain);
        $this->pastikanKeputusan($keputusan);

        $subjek = match ($domain) {
            self::DOMAIN_PERMIT => 'Permit Kendaraan',
            self::DOMAIN_TAMU_B2B => 'Kunjungan Tamu B2B',
            default => 'Kunjungan Tamu',
        };
        $kataKerja = match ($keputusan) {
            self::KEPUTUSAN_JADWAL_DIPINDAH => '— Jadwal Inspeksi Dipindah',
            self::KEPUTUSAN_TAMU_TIDAK_HADIR => '— Tamu Tidak Hadir',
            default => ucfirst(strtolower($keputusan)),
        };

        return [
            'kode_karyawan' => $kodeKaryawan,
            'type' => self::TYPE_KEPUTUSAN,
            'title' => $subjek.' '.$kataKerja,
            'message' => $this->pesanKeputusan($subjek, $keputusan, $noTransaksi, $ctx),
            'module' => self::MODULE,
            'severity' => match ($keputusan) {
                self::KEPUTUSAN_DISETUJUI => 'success',
                self::KEPUTUSAN_DITOLAK => 'danger',
                self::KEPUTUSAN_JADWAL_DIPINDAH => 'info',
                self::KEPUTUSAN_TAMU_TIDAK_HADIR => 'warning',
                default => 'warning',
            },
            'action_url' => self::HALAMAN_PEMOHON[$domain].'?detail='.VisitorDetailToken::buat(self::domainToken($domain), $idTransaksi),
            'channel' => NotificationService::DELIVERY_IN_APP,
            // Pemindahan jadwal bisa terjadi berkali-kali; tiap booking baru adalah
            // kabar baru. Kunci bersama membuat kabar kedua tak terlihat bila yang
            // pertama sudah dihapus pemohon (upsertByKey tidak menghidupkannya).
            'notification_key' => self::kunciKeputusan($domain, $noTransaksi, $keputusan, $kodeKaryawan)
                .($keputusan === self::KEPUTUSAN_JADWAL_DIPINDAH && ! empty($ctx['id_booking']) ? ':'.(int) $ctx['id_booking'] : ''),
            'reference_id' => $noTransaksi,
            'created_by' => 'SYSTEM_VISITOR',
            'priority' => 2,
            'meta' => [
                'source' => self::META_SOURCE,
                'event_type' => self::TYPE_KEPUTUSAN,
                'domain' => $domain,
                'keputusan' => $keputusan,
                'no_transaksi' => $noTransaksi,
                'id_transaksi' => $idTransaksi,
            ],
        ];
    }

    /**
     * Kunci dedup keputusan: satu baris per (domain, transaksi, keputusan, penerima).
     */
    public static function kunciKeputusan(
        string $domain,
        string $noTransaksi,
        string $keputusan,
        string $kodeKaryawan,
    ): string {
        return self::KEY_PREFIX_KEPUTUSAN.':'.$domain.':'.$noTransaksi.':'.$keputusan.':'.$kodeKaryawan;
    }

    /**
     * @param  array<string,mixed>  $ctx
     */
    private function pesanKeputusan(string $subjek, string $keputusan, string $noTransaksi, array $ctx): string
    {
        $pesan = $subjek.' No. '.$noTransaksi;

        $plat = $this->teks($ctx, 'plat_nomor');
        if ($plat !== '') {
            $pesan .= ' ('.$plat.')';
        }

        $pesan .= match ($keputusan) {
            self::KEPUTUSAN_DISETUJUI => ' sudah disetujui dan berlaku.',
            self::KEPUTUSAN_DITOLAK => ' ditolak.',
            self::KEPUTUSAN_JADWAL_DIPINDAH => ' dipindah petugas ke jadwal inspeksi baru'
                .($this->teks($ctx, 'jadwal_baru') !== '' ? ': '.$this->teks($ctx, 'jadwal_baru') : '').'.',
            self::KEPUTUSAN_TAMU_TIDAK_HADIR => ' — tamu dicatat tidak hadir pada jadwal kunjungannya oleh petugas SHE.',
            self::KEPUTUSAN_KEDALUWARSA => ' tidak diputus sampai batas waktunya dan kini kedaluwarsa. Silakan ajukan permit baru bila masih diperlukan.',
            default => ' dibatalkan setelah sempat berlaku.',
        };

        $alasan = $this->teks($ctx, 'alasan');
        if ($alasan !== '') {
            $pesan .= ' Alasan: '.$alasan;
        }

        return $pesan;
    }

    private function pastikanDomain(string $domain): void
    {
        if (! in_array($domain, [self::DOMAIN_TAMU, self::DOMAIN_TAMU_B2B, self::DOMAIN_PERMIT], true)) {
            throw new \InvalidArgumentException("Domain notifikasi Visitor tidak dikenal: {$domain}");
        }
    }

    /**
     * Domain token tautan detail. Tamu B2B memakai token TAMU: barisnya di
     * tabel yang sama, dan showTamu() membaca token dengan domain TAMU di
     * kedua halaman — penyaring tipenya yang memisahkan, bukan tokennya.
     */
    public static function domainToken(string $domain): string
    {
        return $domain === self::DOMAIN_TAMU_B2B ? self::DOMAIN_TAMU : $domain;
    }

    private function pastikanKeputusan(string $keputusan): void
    {
        $sah = [self::KEPUTUSAN_DISETUJUI, self::KEPUTUSAN_DITOLAK, self::KEPUTUSAN_DIBATALKAN, self::KEPUTUSAN_JADWAL_DIPINDAH, self::KEPUTUSAN_TAMU_TIDAK_HADIR, self::KEPUTUSAN_KEDALUWARSA];

        if (! in_array($keputusan, $sah, true)) {
            throw new \InvalidArgumentException("Keputusan Visitor tidak dikenal: {$keputusan}");
        }
    }

    /**
     * @param  array<string,mixed>  $ctx
     */
    private function pesanTamu(string $noTransaksi, array $ctx): string
    {
        $pemohon = $this->teks($ctx, 'nama_pemohon', 'Seorang karyawan');
        $tamu = $this->teks($ctx, 'nama_tamu', 'tamu');
        $instansi = $this->teks($ctx, 'instansi');

        $subjek = $instansi === '' ? $tamu : $tamu.' ('.$instansi.')';

        return $pemohon.' mengajukan kunjungan tamu '.$subjek.'. No. '.$noTransaksi.'.';
    }

    /**
     * @param  array<string,mixed>  $ctx
     */
    private function pesanPermit(string $noTransaksi, array $ctx): string
    {
        $pemohon = $this->teks($ctx, 'nama_pemohon', 'Seorang karyawan');
        $kategori = $this->teks($ctx, 'kategori');
        $plat = $this->teks($ctx, 'plat_nomor');

        $pesan = $pemohon.' mengajukan permit kendaraan';
        if ($kategori !== '') {
            $pesan .= ' '.$kategori;
        }
        if ($plat !== '') {
            $pesan .= ' untuk '.$plat;
        }

        return $pesan.'. No. '.$noTransaksi.'.';
    }

    /**
     * @param  array<string,mixed>  $ctx
     */
    private function teks(array $ctx, string $key, string $default = ''): string
    {
        $value = trim((string) ($ctx[$key] ?? ''));

        return $value === '' ? $default : $value;
    }
}
