<?php

namespace Tests\Feature\SYSTEM;

use App\Services\SYSTEM\Governance\Approval\Shared\AnggotaApprovalResolver;
use App\Services\SYSTEM\Governance\Approval\Shared\NotifikasiKolamRole;
use App\Services\SYSTEM\Notification\NotificationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Pipa notif "kolam role" tinggal di SATU rumah.
 *
 * ── Kenapa dijaga ────────────────────────────────────────────────────────────
 *
 * CutiNotificationService dan KpiNotificationService memegang ~150 baris yang
 * sama, dan salah satunya — resolveCurrentRoleStep() — identik byte per byte.
 * Salinannya lahir dengan sengaja; komentar kelas KPI sampai sekarang menyebut
 * dirinya "Mirror pola CutiNotificationService".
 *
 * Cerminnya retak justru pada baris yang paling gampang terlewat: versi cuti
 * menyaring `Is_Deleted='T'` sebelum menandai notif selesai, versi KPI tidak
 * menyaring apa pun. Jadi setiap approve/reject KPI ikut menimpa Read_At
 * notifikasi yang sudah lama di-dismiss.
 *
 * Uji di sini menguji PERILAKU, bukan cuma bentuk: siarkan() dijalankan dengan
 * resolver anggota dan NotificationService palsu, dan langkah approval-nya
 * dipalsukan juga — seluruh pengulangannya bisa diperiksa tanpa satu pun kueri.
 */
class NotifikasiKolamRoleSatuRumahTest extends TestCase
{
    /** Kedua berkas yang dulu memegang salinannya sendiri. */
    private const BEKAS_PEMILIK_SALINAN = [
        'app/Services/ESS/Attendance/Leave/Notification/CutiNotificationService.php',
        'app/Services/KPI/Notification/KpiNotificationService.php',
    ];

    private const RUMAH = 'app/Services/SYSTEM/Governance/Approval/Shared/NotifikasiKolamRole.php';

    /** NotificationService palsu; catatannya dibaca lewat ->catatan. */
    private ?NotificationService $notifPalsu = null;

    /** Berapa kali penyusun muatan dipanggil — inti uji N+1 di bawah. */
    private int $penyusunDipanggil = 0;

    /** @return array<int, array<string, mixed>> muatan yang "terkirim" */
    private function terkirim(): array
    {
        return $this->notifPalsu->catatan ?? [];
    }

    /**
     * NotifikasiKolamRole dengan langkahBerjalan() dipalsukan, plus resolver
     * anggota dan NotificationService palsu. Nol kueri.
     *
     * @param  bool  $adaLangkah  false = tidak ada langkah ROLE yang berjalan
     */
    private function pipa(array $kodeAnggota, bool $adaLangkah = true): NotifikasiKolamRole
    {
        $this->penyusunDipanggil = 0;

        $anggota = new Collection(array_map(
            fn ($kode) => (object) ['Kode_Karyawan' => $kode, 'Nama' => 'Nama '.$kode],
            $kodeAnggota,
        ));

        $this->app->instance(AnggotaApprovalResolver::class, new class($anggota) extends AnggotaApprovalResolver
        {
            public function __construct(private Collection $daftar) {}

            public function dariRole(int $idRole): Collection
            {
                return $this->daftar;
            }
        });

        $this->notifPalsu = new class extends NotificationService
        {
            /** @var array<int, array<string, mixed>> */
            public array $catatan = [];

            public function upsertByKey(array $payload): ?int
            {
                $this->catatan[] = $payload;

                return count($this->catatan);
            }
        };

        $baris = $adaLangkah ? (object) [
            'id_approval_request' => 77,
            'No_Transaksi' => 'TRX-1',
            'Group_Reference_Id' => 12,
            'Request_Order_Flow' => 3,
            'role_code' => 'ROLE_X',
            'Nama_Approval' => 'Verifikator',
        ] : null;

        return new class($this->notifPalsu, $baris) extends NotifikasiKolamRole
        {
            private ?object $baris;

            public function __construct(NotificationService $notif, ?object $baris)
            {
                parent::__construct($notif);
                $this->baris = $baris;
            }

            public function langkahBerjalan(string $noTransaksi): ?object
            {
                return $this->baris;
            }
        };
    }

    /** @return callable penyusun muatan yang menghitung berapa kali dipanggil */
    private function penyusun(): callable
    {
        return function (object $langkah): array {
            $this->penyusunDipanggil++;

            return [
                'title' => 'Judul',
                'message' => 'Pesan',
                'module' => 'HCIS',
                'severity' => 'warning',
                'action_url' => '/mana-saja',
                'meta' => ['id_role' => (int) $langkah->Group_Reference_Id],
            ];
        };
    }

    public function test_penyusun_muatan_dipanggil_sekali_bukan_sekali_per_anggota(): void
    {
        /*
         * INI ALASAN BENTUK API-nya seperti sekarang. Judul dan pesan butuh
         * nama pengaju serta label jenis, dan kedua pemanggil mengambilnya
         * lewat kueri. Kalau penyusunnya dipanggil di dalam pengulangan
         * anggota, kolam berisi 20 admin berarti 20 kueri label — N+1 yang
         * lahir dari perapian kode, bukan dari kode lamanya.
         */
        $hasil = $this->pipa(['A1', 'A2', 'A3', 'A4', 'A5'])
            ->siarkan('TRX-1', 'CUTI_ROLE_REVIEW', 'stack', $this->penyusun());

        $this->assertSame(5, $hasil);
        $this->assertCount(5, $this->terkirim());
        $this->assertSame(
            1,
            $this->penyusunDipanggil,
            'Penyusun muatan dipanggil '.$this->penyusunDipanggil.' kali untuk 5 anggota. '.
            'Ia harus dipanggil SEKALI saja: pemanggil melakukan kueri label di dalamnya, '.
            'jadi memanggilnya per anggota mengubah satu kueri menjadi N.',
        );
    }

    public function test_penyusun_tidak_dipanggil_saat_kolam_kosong(): void
    {
        $hasil = $this->pipa([])->siarkan('TRX-1', 'CUTI_ROLE_REVIEW', 'stack', $this->penyusun());

        $this->assertSame(0, $hasil);
        $this->assertSame(
            0,
            $this->penyusunDipanggil,
            'Kolam kosong berarti tak ada yang dinotifikasi, jadi kueri labelnya pun '.
            'tidak boleh dijalankan.',
        );
    }

    public function test_tanpa_langkah_role_yang_berjalan_tidak_ada_apa_apa(): void
    {
        $hasil = $this->pipa(['A1'], adaLangkah: false)
            ->siarkan('TRX-1', 'CUTI_ROLE_REVIEW', 'stack', $this->penyusun());

        $this->assertSame(0, $hasil);
        $this->assertSame([], $this->terkirim());
        $this->assertSame(0, $this->penyusunDipanggil);
    }

    public function test_no_transaksi_kosong_ditolak_di_pintu(): void
    {
        foreach (['', '   '] as $kosong) {
            $hasil = $this->pipa(['A1'])->siarkan($kosong, 'CUTI_ROLE_REVIEW', 'stack', $this->penyusun());
            $this->assertSame(0, $hasil);
            $this->assertSame([], $this->terkirim());
        }
    }

    public function test_bentuk_kunci_notif_dipertahankan(): void
    {
        /*
         * Bentuk kunci ini BUKAN detail internal: selesaikan() mencocokkan
         * "{prefix}:{noTransaksi}:%" untuk membersihkan tray admin lain.
         * Mengubah urutan ruasnya membuat notif lama tak pernah tertutup dan
         * menumpuk selamanya di topbar.
         */
        $this->pipa(['ADM1'])->siarkan('TRX-9', 'KPI_ROLE_REVIEW', 'stack', $this->penyusun());

        $muatan = $this->terkirim()[0];

        $this->assertSame(
            'KPI_ROLE_REVIEW:TRX-9:3:12:ADM1',
            $muatan['notification_key'],
            'Urutannya wajib prefix:noTransaksi:orderFlow:idRole:kodeKaryawan — dua ruas '.
            'pertama itu yang dipakai selesaikan() untuk mencocokkan.',
        );

        $this->assertSame('KPI_ROLE_REVIEW', $muatan['type']);
        $this->assertSame('ADM1', $muatan['kode_karyawan']);
        $this->assertSame(NotificationService::DELIVERY_IN_APP, $muatan['channel']);
        $this->assertTrue($muatan['revive_deleted']);
        $this->assertSame('TRX-9', $muatan['reference_id']);
    }

    public function test_muatan_domain_tidak_tertimpa_oleh_pipa(): void
    {
        $this->pipa(['ADM1'])->siarkan('TRX-1', 'CUTI_ROLE_REVIEW', 'stack', $this->penyusun());

        $muatan = $this->terkirim()[0];

        $this->assertSame('Judul', $muatan['title']);
        $this->assertSame('/mana-saja', $muatan['action_url']);
        $this->assertSame(
            ['id_role' => 12],
            $muatan['meta'],
            'meta milik domain harus lolos utuh; kalau pipa menimpanya, notifikasi '.
            'kehilangan konteks yang dibaca topbar.',
        );
    }

    public function test_anggota_tanpa_kode_dilewati_bukan_menggagalkan_sisanya(): void
    {
        $hasil = $this->pipa(['A1', '', '   ', 'A2'])
            ->siarkan('TRX-1', 'CUTI_ROLE_REVIEW', 'stack', $this->penyusun());

        $this->assertSame(2, $hasil);
        $this->assertSame(['A1', 'A2'], array_column($this->terkirim(), 'kode_karyawan'));
    }

    public function test_saringan_is_deleted_yang_dulu_hilang_di_sisi_kpi(): void
    {
        /*
         * INTI PERBAIKAN. Di tabel ini 'T' berarti belum dihapus dan 'Y'
         * berarti sudah di-dismiss (NotificationService menulis 'T' saat
         * membuat, 'Y' saat men-dismiss). Tanpa saringan ini UPDATE-nya juga
         * menyentuh baris yang sudah lama di-dismiss dan menimpa Read_At
         * mereka — sekaligus mengunci baris yang tak perlu, yang di basis data
         * ini berarti menahan pembaca karena RCSI-nya mati.
         *
         * selesaikan() menulis ke basis data, jadi yang diperiksa di sini
         * bentuknya, DAN hanya di dalam badan metode itu — supaya kemunculan
         * 'Is_Deleted' di tempat lain tidak membuat penjaga ini hijau palsu.
         */
        $isi = (string) file_get_contents(base_path(self::RUMAH));
        $mulai = strpos($isi, 'function selesaikan');

        $this->assertNotFalse($mulai, 'selesaikan() hilang dari rumah bersamanya.');

        $this->assertStringContainsString(
            "->where('Is_Deleted', 'T')",
            substr($isi, $mulai),
            "selesaikan() kehilangan saringan Is_Deleted='T'. Itu satu-satunya perbedaan ".
            'nyata antara kedua salinan lama, dan versi tanpa saringan menimpa Read_At '.
            'notifikasi yang sudah di-dismiss.',
        );
    }

    public function test_tidak_ada_lagi_salinan_di_kedua_service(): void
    {
        foreach (self::BEKAS_PEMILIK_SALINAN as $berkas) {
            $isi = (string) file_get_contents(base_path($berkas));

            $this->assertDoesNotMatchRegularExpression(
                '/function resolveCurrentRoleStep\s*\(/',
                $isi,
                "{$berkas} memegang salinan resolveCurrentRoleStep() lagi. Kueri itu ".
                'identik byte per byte di kedua service; salinan kedua berarti koreksi '.
                'berikutnya hanya sampai ke salah satunya.',
            );

            $this->assertStringNotContainsString(
                'HRIS_Approval_Request as ar',
                $isi,
                "{$berkas} kembali menulis kueri langkah approval-nya sendiri.",
            );

            $this->assertStringContainsString(
                'NotifikasiKolamRole::class',
                $isi,
                "{$berkas} tidak lagi memanggil rumah bersamanya.",
            );

            $this->assertStringNotContainsString(
                "'Notification_Key', 'like'",
                $isi,
                "{$berkas} kembali menulis pembersihan tray-nya sendiri — di situlah ".
                'saringan Is_Deleted hilang sebelumnya.',
            );
        }
    }

    public function test_berkas_penjaga_ini_tidak_kosong(): void
    {
        /*
         * Penjaga yang berkasnya terlanjur terpotong akan HIJAU tanpa menguji
         * apa pun. Pernah terjadi dua kali di repo ini, dan php -l lolos pada
         * berkas kosong.
         */
        $this->assertGreaterThan(6000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
        $this->assertGreaterThan(4000, (int) filesize(base_path(self::RUMAH)), 'Rumah bersamanya menyusut drastis.');
    }
}
