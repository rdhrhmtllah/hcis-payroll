<?php

namespace Tests\Feature\SYSTEM;

use App\Http\Controllers\SYSTEM\Governance\Authorization\AdminRoleManagerController;
use App\Http\Controllers\SYSTEM\Governance\Authorization\SystemAuthController;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tidak ada metode publik tanpa rute di controller otorisasi.
 *
 * ── Kenapa justru di sini ────────────────────────────────────────────────────
 *
 * AdminRoleManagerController menyimpan getPermissionMatrix() sepanjang 126
 * baris yang TIDAK terpasang rute mana pun — satu-satunya dari 22 metode
 * publiknya, dan satu-satunya di kedua controller ini (SystemAuthController:
 * 34 dari 35 terpasang, sisanya konstruktor). Front-end hanya memanggil
 * /api/v1/system-auth/permission-matrix.
 *
 * Ia bukan sekadar kode mati. Ia salinan BASI dari implementasi yang hidup,
 * dari masa sebelum lapisan delegasi per-grup ditambahkan. Yang hilang di
 * salinan itu:
 *
 *   - pemeriksaan $request->user() dan balasan 401
 *   - AuthorizationScopeResolver::resolve() beserta 403 bagi admin tanpa
 *     grup kelolaan
 *   - saringan whereIn('gfm.Id_Grup_Fitur', $manageableGroups) pada daftar
 *     pengguna DAN pada daftar menu
 *
 * Grup rutenya memang sudah dijaga middleware jabatan, jadi ini BUKAN lubang
 * yang sedang menganga — tanpa rute, ia tak bisa dipanggil sama sekali.
 * Bahayanya lain: ia duduk di berkas yang 21 metode lainnya hidup, tampak
 * selesai, dan berjarak satu baris rute dari menjadi nyata. Siapa pun yang
 * kelak "menghidupkan" endpoint matrix di role-manager akan mengekspor versi
 * tanpa delegasi.
 *
 * Rute dibaca dari router Laravel yang sesungguhnya, bukan dari teks berkas
 * rute: gaya pendaftaran apa pun ikut terhitung.
 */
class OtorisasiTanpaMetodeMatiTest extends TestCase
{
    private const CONTROLLER = [
        AdminRoleManagerController::class,
        SystemAuthController::class,
    ];

    /** @return array<string, list<string>> kelas => metode yang terpasang rute */
    private function metodeTerpasang(): array
    {
        $peta = [];

        foreach (Route::getRoutes() as $rute) {
            $aksi = $rute->getAction('controller');
            if (! is_string($aksi) || ! str_contains($aksi, '@')) {
                continue;
            }

            [$kelas, $metode] = explode('@', $aksi, 2);
            $peta[$kelas][] = $metode;
        }

        return $peta;
    }

    /** @return list<string> metode publik yang dideklarasikan kelas itu sendiri */
    private function metodePublik(string $kelas): array
    {
        $keluar = [];

        foreach ((new ReflectionClass($kelas))->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
            if ($m->getDeclaringClass()->getName() !== $kelas) {
                continue;   // warisan dari Controller dasar, bukan endpoint
            }
            if (str_starts_with($m->getName(), '__')) {
                continue;
            }
            $keluar[] = $m->getName();
        }

        return $keluar;
    }

    public function test_tidak_ada_metode_publik_tanpa_rute(): void
    {
        $terpasang = $this->metodeTerpasang();

        foreach (self::CONTROLLER as $kelas) {
            $publik = $this->metodePublik($kelas);
            $punyaRute = $terpasang[$kelas] ?? [];
            $yatim = array_values(array_diff($publik, $punyaRute));

            $this->assertNotEmpty(
                $publik,
                "{$kelas} tidak punya metode publik sama sekali — refleksinya salah sasaran.",
            );

            $this->assertSame(
                [],
                $yatim,
                "Metode publik tanpa rute di {$kelas}: ".implode(', ', $yatim).".\n".
                'Di controller otorisasi, metode tanpa rute berbahaya bukan karena bisa '.
                'dipanggil — justru karena tidak bisa, jadi ia tak pernah ikut diperbarui '.
                'ketika aturan delegasi diperketat, lalu terlihat siap dipakai. Pasang '.
                'rutenya (dan lengkapi penjaganya) atau hapus.',
            );
        }
    }

    public function test_matriks_izin_yang_hidup_tetap_membawa_delegasi_per_grup(): void
    {
        /*
         * Yang dijaga di sini bukan gaya kode melainkan empat hal yang HILANG di
         * salinan matinya. Kalau salah satunya lenyap dari versi hidup, admin
         * ber-grup-kelolaan bisa melihat — dan memberi — akses di luar grupnya.
         */
        $isi = (string) file_get_contents(base_path(
            'app/Http/Controllers/SYSTEM/Governance/Authorization/SystemAuthController.php',
        ));

        $awal = strpos($isi, 'public function getPermissionMatrix(');
        $this->assertNotFalse($awal, 'getPermissionMatrix() yang hidup hilang.');

        $badan = substr($isi, $awal, 9000);

        $this->assertStringContainsString(
            'AuthorizationScopeResolver::resolve($actor)',
            $badan,
            'Matriks izin berhenti menghitung grup kelolaan pemanggilnya.',
        );

        $this->assertStringContainsString(
            "['message' => 'Unauthorized'], 401",
            $badan,
            'Pemeriksaan pemanggil tak dikenal hilang.',
        );

        $this->assertStringContainsString(
            'Anda tidak memiliki group kelolaan untuk matrix.',
            $badan,
            'Penolakan 403 bagi admin tanpa grup kelolaan hilang — tanpa itu ia melihat '.
            'seluruh matriks, bukan nol baris.',
        );

        $this->assertSame(
            2,
            substr_count($badan, "whereIn('gfm.Id_Grup_Fitur', \$manageableGroups)"),
            'Saringan grup harus ada DUA: pada daftar pengguna dan pada daftar menu. '.
            'Menyaring salah satunya saja membuat dropdown menu membocorkan modul yang '.
            'tak boleh dikelola.',
        );
    }

    public function test_salinan_mati_tidak_kembali(): void
    {
        $isi = (string) file_get_contents(base_path(
            'app/Http/Controllers/SYSTEM/Governance/Authorization/AdminRoleManagerController.php',
        ));

        $this->assertStringNotContainsString(
            'function getPermissionMatrix',
            $isi,
            'Salinan matriks izin yang tanpa delegasi kembali ke AdminRoleManagerController.',
        );
    }

    public function test_berkas_penjaga_tidak_kosong(): void
    {
        $this->assertGreaterThan(4000, (int) filesize(__FILE__), 'Berkas penjaga menyusut drastis.');
    }
}
