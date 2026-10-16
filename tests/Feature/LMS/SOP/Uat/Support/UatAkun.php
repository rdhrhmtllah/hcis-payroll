<?php

namespace Tests\Feature\LMS\SOP\Uat\Support;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopTeamService;
use Illuminate\Support\Facades\DB;

/**
 * Menemukan akun uji untuk skenario UAT di database dev.
 *
 * TIDAK MEMBUAT KARYAWAN BARU, dan itu keputusan yang disengaja. Data karyawan
 * bersandar pada struktur organisasi, level jabatan, relasi atasan, dan hak
 * akses yang tersebar di banyak tabel warisan. Karyawan yang dikarang sebagian
 * akan lolos pembuatan lalu membuat skenario LAIN berbohong — misalnya
 * "kepatuhan tim" yang hijau karena timnya memang kosong.
 *
 * Hak akses dibaca lewat User::memilikiJabatan(), bukan dengan query sendiri ke
 * tabel akses. Kelayakan akses di sini ditentukan dua sistem sekaligus (peran
 * baru dan warisan divisi/level); menyalin logikanya ke dalam test berarti
 * membuat salinan yang akan menyimpang diam-diam, dan test akses yang memakai
 * aturan aksesnya sendiri tidak menguji apa pun.
 *
 * Bila akun yang cocok tidak ada, test DI-SKIP dengan pesan yang menyebut apa
 * yang kurang — mengikuti pola SopMasterDokumenPageTest. Membiarkannya merah
 * akan menormalkan suite merah, dan kegagalan sungguhan tenggelam di dalamnya.
 */
class UatAkun
{
    public const HALAMAN_ADMIN = [
        'menuSopKontenPage' => 'sop/admin/konten',
        'menuSopDokumenPage' => 'sop/admin/dokumen',
        'menuSopBuilderPage' => 'sop/admin/builder',
        'menuSopPenjadwalanPage' => 'sop/admin/penjadwalan',
        'menuSopMonitoringPage' => 'sop/admin/monitoring',
        'menuSopKebijakanPage' => 'sop/admin/kebijakan',
    ];

    /** Hasil penelusuran dipakai ulang lintas test: memindai user itu mahal. */
    private static array $memo = [];

    /**
     * Admin SOP: user yang memiliki SELURUH enam hak akses halaman SOP.
     *
     * Sengaja menuntut ketujuhnya, bukan satu saja. Skenario alur penuh
     * berpindah dari Master Dokumen ke Builder ke Penjadwalan; admin yang hanya
     * punya sebagian akan gagal di tengah dengan 403 yang terlihat seperti bug
     * fitur.
     */
    public static function admin(): ?User
    {
        return self::memo('admin', function () {
            foreach (self::kandidat() as $u) {
                foreach (array_keys(self::HALAMAN_ADMIN) as $page) {
                    if (! $u->memilikiJabatan($page)) {
                        continue 2;
                    }
                }

                return $u;
            }

            return null;
        });
    }

    /**
     * Kode karyawan seorang user.
     *
     * Lewat relasi `karyawan`, sama seperti yang dilakukan aplikasi
     * (SopTeamController::kode, SopSayaController). Tabel user adalah KPI_Users
     * dan TIDAK punya kolom Kode_Karyawan — yang ada Kode_Users, yang bukan hal
     * yang sama. Membacanya langsung dari model menghasilkan null untuk setiap
     * user, dan penyaring yang menolak semua orang tampak seperti "tidak ada
     * kandidat" alih-alih seperti kekeliruan.
     */
    public static function kodeKaryawan(User $u): ?string
    {
        $kode = optional($u->karyawan)->Kode_Karyawan;

        return $kode ? trim((string) $kode) : null;
    }

    /**
     * Karyawan biasa: tertaut data karyawan, dan TIDAK punya satu pun hak akses
     * halaman admin SOP.
     */
    public static function karyawanBiasa(): ?User
    {
        return self::memo('karyawan', function () {
            foreach (self::kandidat() as $u) {
                if (! self::kodeKaryawan($u)) {
                    continue;
                }
                foreach (array_keys(self::HALAMAN_ADMIN) as $page) {
                    if ($u->memilikiJabatan($page)) {
                        continue 2;
                    }
                }

                return $u;
            }

            return null;
        });
    }

    /**
     * Leader dengan minimal $minAnggota bawahan pada HRIS_Karyawan_Team.
     *
     * Menuntut lebih dari satu anggota karena skenario 039 membuktikan BATAS
     * lingkup: dengan satu anggota, "leader hanya melihat anggotanya" tak bisa
     * dibedakan dari "leader melihat satu orang yang kebetulan benar".
     */
    public static function leader(int $minAnggota = 2): ?string
    {
        return self::memo("leader:$minAnggota", function () use ($minAnggota) {
            $baris = DB::table('HRIS_Karyawan_Team')
                ->select('Kode_Karyawan_Team')
                ->groupBy('Kode_Karyawan_Team')
                ->havingRaw('COUNT(DISTINCT Kode_Karyawan) >= ?', [$minAnggota])
                ->limit(1)
                ->value('Kode_Karyawan_Team');

            return $baris ? trim((string) $baris) : null;
        });
    }

    /** @return list<string> kode anggota tim seorang leader */
    public static function anggotaTim(string $leader): array
    {
        return DB::table('HRIS_Karyawan_Team')
            ->where('Kode_Karyawan_Team', $leader)
            ->pluck('Kode_Karyawan')
            ->map(fn ($x) => trim((string) $x))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Kode karyawan yang BUKAN anggota tim leader tersebut — bahan uji batas
     * lingkup untuk skenario 039.
     */
    public static function luarTim(string $leader): ?string
    {
        $anggota = self::anggotaTim($leader);

        $kode = DB::table('N_SOP_Penjadwalan_Detail')
            ->whereNotIn('Kode_Karyawan', $anggota ?: ['-'])
            ->where('Kode_Karyawan', '<>', $leader)
            ->limit(1)
            ->value('Kode_Karyawan');

        return $kode ? trim((string) $kode) : null;
    }

    public static function adalahLeader(string $kode): bool
    {
        return app(SopTeamService::class)->isLeader($kode);
    }

    /**
     * Kandidat user untuk dipindai.
     *
     * Batasnya ada sebagai jaring pengaman, bukan karena tabelnya besar —
     * KPI_Users hanya berisi puluhan baris. Yang mahal adalah memilikiJabatan(),
     * yang menembak database per user; kalau suatu hari tabel ini tumbuh, batas
     * ini yang mencegah tiap test menahan koneksi jauh lebih lama daripada
     * perlunya. Server ini punya RCSI mati, jadi pembaca lain antre di belakang
     * apa pun yang berjalan lama.
     *
     * @return iterable<User>
     */
    private static function kandidat(): iterable
    {
        return User::query()
            ->with('karyawan')
            ->whereNotNull('Id_Users')
            ->orderBy('Id_Users')
            ->limit(400)
            ->get();
    }

    private static function memo(string $kunci, callable $cari)
    {
        if (! array_key_exists($kunci, self::$memo)) {
            self::$memo[$kunci] = $cari();
        }

        return self::$memo[$kunci];
    }

    public static function lupakan(): void
    {
        self::$memo = [];
    }
}
