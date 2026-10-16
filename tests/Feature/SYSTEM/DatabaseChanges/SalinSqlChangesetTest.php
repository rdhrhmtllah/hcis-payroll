<?php

namespace Tests\Feature\SYSTEM\DatabaseChanges;

use App\Models\SYSTEM\DatabaseChanges\Models\SystemDatabaseChange;
use App\Services\SYSTEM\DatabaseChanges\DatabaseChangeDashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Menyalin SQL changeset harus bekerja di Windows, bukan hanya di Linux.
 *
 * Penjaga jalur artifact membandingkan hasil realpath() dengan base_path().'/'.
 * Di Windows realpath() mengembalikan backslash, sehingga perbandingan itu
 * SELALU gagal dan setiap berkas yang sah ditolak — berkasnya ada, tetapi
 * layar berkata "Data yang diminta tidak ditemukan". Di Linux ia lolos karena
 * pemisahnya memang '/', jadi cacatnya tidak pernah terlihat di produksi dan
 * hanya mematikan mesin pengembangan.
 *
 * Uji ini berjalan di kedua sistem: yang ditagih adalah berkas yang BENAR-BENAR
 * ada harus terbaca, bukan bentuk pemisah jalurnya.
 */
class SalinSqlChangesetTest extends TestCase
{
    use DatabaseTransactions;

    public function test_changeset_yang_berkasnya_ada_bisa_dibaca(): void
    {
        $change = $this->changesetDenganBerkas();
        $hasil = app(DatabaseChangeDashboardService::class)->sql($change);

        $this->assertSame($change->Kode_Change, $hasil['change_id']);
        $this->assertNotSame('', trim($hasil['content']), 'Isi SQL kosong.');
    }

    public function test_endpoint_membalas_200_untuk_changeset_yang_berkasnya_ada(): void
    {
        $response = $this->sql($this->changesetDenganBerkas());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_berkas_yang_belum_ada_dijelaskan_apa_adanya(): void
    {
        // Changeset bisa lahir di branch lain sehingga berkasnya belum ada di
        // worktree ini. Itu bukan soal izin, dan pesannya tidak boleh berbohong.
        $change = $this->changesetTanpaBerkas();

        if (! $change) {
            $this->markTestSkipped('Semua changeset berkasnya ada di worktree ini.');
        }

        $response = $this->sql($change);
        $isi = json_decode($response->getContent(), true);

        $this->assertStringContainsString('belum ada di worktree', $isi['message'] ?? '');
    }

    public function test_kegagalan_artifact_tidak_memakai_404(): void
    {
        // normalizeApiError() di frontend MEMBUANG data.message pada 404 dan
        // selalu menampilkan "Data yang diminta tidak ditemukan", sehingga sebab
        // sebenarnya tidak pernah terbaca pengguna. Cabang 422 yang meneruskan
        // pesannya.
        $change = $this->changesetTanpaBerkas();

        if (! $change) {
            $this->markTestSkipped('Semua changeset berkasnya ada di worktree ini.');
        }

        $this->assertSame(422, $this->sql($change)->getStatusCode());
    }

    /**
     * @dataProvider jalurNakal
     */
    public function test_jalur_di_luar_ddl_dan_dml_tetap_ditolak(string $jalur): void
    {
        $this->expectExceptionMessage('Path artifact tidak diizinkan.');

        $this->safeArtifactPath($jalur);
    }

    /** @return array<string, array{0:string}> */
    public static function jalurNakal(): array
    {
        return [
            'keluar lewat titik-dua' => ['database/ddl/../../.env'],
            'berkas rahasia di akar' => ['.env'],
            'folder lain' => ['storage/logs/laravel.log'],
            'mirip tapi bukan' => ['database/ddlx/forward.sql'],
        ];
    }

    public function test_jalur_sah_diterima(): void
    {
        // Penjaga yang menolak SEMUANYA juga "aman" — dan itu persis cacat yang
        // sedang diperbaiki. Jadi sisi positifnya wajib ikut ditagih.
        $change = $this->changesetDenganBerkas();

        $this->assertNotSame('', $this->safeArtifactPath($change->Artifact_Path));
    }

    // ------------------------------------------------------------ bulk copy

    public function test_salinan_gabungan_selalu_urut_pendaftaran_walau_id_dikirim_acak(): void
    {
        // Inilah inti fitur ini. Kalau urutannya mengikuti urutan id yang
        // dikirim layar, salah urut bisa lahir hanya dari cara pengguna
        // mengklik — dan SQL yang dieksekusi terbalik tidak selalu gagal
        // dengan galat, kadang hanya menghasilkan keadaan yang salah.
        $ids = $this->idBerkasAda(4);

        if (count($ids) < 2) {
            $this->markTestSkipped('Butuh minimal dua changeset yang berkasnya ada.');
        }

        $acak = array_reverse($ids);
        $hasil = app(DatabaseChangeDashboardService::class)->bulkSql($acak);

        $this->assertNotSame($ids, $acak, 'Fixture tidak menguji apa pun: urutan kirim harus berbeda.');
        $this->assertSame($ids, array_column($hasil['included'], 'id'));
    }

    public function test_salinan_gabungan_memuat_nomor_urut_dan_waktu_dibuat(): void
    {
        // Diminta supaya urutan eksekusi bisa diperiksa mata sebelum ditempel.
        $hasil = app(DatabaseChangeDashboardService::class)->bulkSql($this->idBerkasAda(2));

        $this->assertStringContainsString('Daftar isi:', $hasil['content']);
        $this->assertStringContainsString('dibuat ', $hasil['content']);
        $this->assertMatchesRegularExpression('/\[1\/\d+\]\s+#\d+/', $hasil['content']);

        foreach ($hasil['included'] as $baris) {
            $this->assertNotNull($baris['created_at']);
        }
    }

    public function test_berkas_yang_tidak_terbaca_dilewati_dan_dilaporkan(): void
    {
        // Melewatinya diam-diam membuat pengguna mengira sudah menyalin
        // semuanya, lalu ada changeset yang tidak pernah dieksekusi.
        $hilang = $this->changesetTanpaBerkas();

        if (! $hilang) {
            $this->markTestSkipped('Semua changeset berkasnya ada di worktree ini.');
        }

        $ids = array_merge($this->idBerkasAda(1), [$hilang->Id_Database_Change]);
        $hasil = app(DatabaseChangeDashboardService::class)->bulkSql($ids);

        $this->assertSame([$hilang->Kode_Change], array_column($hasil['skipped'], 'change_id'));
        $this->assertStringContainsString('DILEWATI', $hasil['content']);
        $this->assertNotContains($hilang->Id_Database_Change, array_column($hasil['included'], 'id'));
    }

    public function test_batas_maksimum_ditegakkan(): void
    {
        $this->expectExceptionMessage('Maksimal '.DatabaseChangeDashboardService::BATAS_BULK);

        app(DatabaseChangeDashboardService::class)
            ->bulkSql(range(1, DatabaseChangeDashboardService::BATAS_BULK + 1));
    }

    public function test_id_kembar_tidak_menggandakan_isi(): void
    {
        $ids = $this->idBerkasAda(1);
        $hasil = app(DatabaseChangeDashboardService::class)->bulkSql([$ids[0], $ids[0], $ids[0]]);

        $this->assertSame(1, $hasil['total']);
    }

    // ---------------------------------------------------------------- helpers

    /** @return array<int,int> id changeset yang berkasnya ada, urut pendaftaran */
    private function idBerkasAda(int $jumlah): array
    {
        return SystemDatabaseChange::query()
            ->orderBy('Id_Database_Change')
            ->get()
            ->filter(fn ($c) => file_exists(base_path(str_replace('/', DIRECTORY_SEPARATOR, (string) $c->Artifact_Path))))
            ->take($jumlah)
            ->pluck('Id_Database_Change')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();
    }

    private function sql(SystemDatabaseChange $change)
    {
        return app(\App\Http\Controllers\SYSTEM\DatabaseChanges\DatabaseChangeDashboardController::class)
            ->sql($change);
    }

    private function safeArtifactPath(string $jalur): string
    {
        $metode = new ReflectionMethod(DatabaseChangeDashboardService::class, 'safeArtifactPath');
        $metode->setAccessible(true);

        return $metode->invoke(app(DatabaseChangeDashboardService::class), $jalur);
    }

    private function changesetDenganBerkas(): SystemDatabaseChange
    {
        $change = SystemDatabaseChange::all()->first(
            fn ($c) => file_exists(base_path(str_replace('/', DIRECTORY_SEPARATOR, (string) $c->Artifact_Path))),
        );

        if (! $change) {
            $this->markTestSkipped('Tidak ada changeset yang berkasnya tersedia.');
        }

        return $change;
    }

    private function changesetTanpaBerkas(): ?SystemDatabaseChange
    {
        return SystemDatabaseChange::all()->first(
            fn ($c) => ! file_exists(base_path(str_replace('/', DIRECTORY_SEPARATOR, (string) $c->Artifact_Path))),
        );
    }
}
