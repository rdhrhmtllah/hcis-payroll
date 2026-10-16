<?php

namespace Tests\Feature\SYSTEM\Notification;

use App\Services\SYSTEM\Notification\NotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Predikat teks notifikasi harus dibandingkan dengan tipe kolomnya.
 *
 * ── Kenapa ini penting justru di sini ────────────────────────────────────────
 *
 * pdo_sqlsrv mengirim setiap string PHP sebagai `nvarchar(4000)`. Kolom tabel
 * ini varchar/char, jadi SQL Server harus meng-CONVERT KOLOMNYA — dan kolom
 * yang dibungkus fungsi TIDAK BISA di-seek.
 *
 * Tabel ini SUDAH punya IX_Notification_Unread_Shell yang kuncinya cocok
 * sempurna dengan predikat topbar. Sebelum perbaikan, indeks itu tercatat
 * 4 seek melawan 34.331 scan: indeks yang tepat, tak pernah dipakai.
 *
 *     production, predikat asli : 432 logical reads -> 6
 *     salinan lokal             : 123 logical reads -> 6
 *
 * Jalur ini dipukul setiap pengguna di setiap halaman, jadi penghematannya
 * dikalikan seluruh lalu lintas.
 *
 * ── Yang dijaga ──────────────────────────────────────────────────────────────
 *
 * Bentuk SQL-nya, bukan angka bacanya: angka baca berubah mengikuti data dan
 * akan membuat penjaga ini rapuh. Yang tetap adalah aturannya — tiap predikat
 * atas kolom teks WAJIB membawa CAST ke tipe kolom itu. Satu `->where()` polos
 * yang kembali masuk akan mengembalikan scan-nya tanpa suara.
 *
 * Tipe di daftar bawah sengaja ditulis ulang di sini, bukan diimpor dari
 * service-nya: penjaga yang membaca konstanta yang sama dengan yang dijaganya
 * akan tetap hijau ketika konstanta itu yang salah.
 */
class PredikatNotifikasiBertipeTest extends TestCase
{
    use DatabaseTransactions;

    /** Tipe SEBENARNYA di katalog — diperiksa lagi oleh uji kedua di bawah. */
    private const HARUS_BERTIPE = [
        'Kode_Karyawan' => 'varchar(50)',
        'Is_Deleted' => 'char(1)',
        'Is_Read' => 'char(1)',
        'Channel' => 'varchar(20)',
        'source' => 'varchar(100)',
    ];

    public function test_setiap_predikat_teks_membawa_cast_ke_tipe_kolomnya(): void
    {
        $svc = app(NotificationService::class);

        $metode = new ReflectionMethod($svc, 'activeUnreadQuery');
        $metode->setAccessible(true);

        $sql = $metode->invoke($svc, 'A1', ['source' => 'DASH_PERSON'])->toSql();

        foreach (self::HARUS_BERTIPE as $kolom => $tipe) {
            $this->assertStringContainsString(
                "[{$kolom}] = CAST(? AS {$tipe})",
                $sql,
                "Predikat [{$kolom}] dikirim tanpa CAST. pdo_sqlsrv akan mengirimnya sebagai ".
                'nvarchar, SQL Server meng-CONVERT kolomnya, dan IX_Notification_Unread_Shell '.
                "kembali di-SCAN alih-alih di-seek.\n\nSQL: ".$sql,
            );

            $this->assertStringNotContainsString(
                "[{$kolom}] = ?",
                $sql,
                "Masih ada perbandingan polos untuk [{$kolom}] — CAST-nya terlewat di salah satu cabang.",
            );
        }
    }

    /** Tipe yang di-CAST harus SAMA dengan tipe kolomnya di katalog. */
    public function test_tipe_yang_dicast_sama_dengan_katalog(): void
    {
        $katalog = [];

        foreach (DB::select("
            SELECT c.name, t.name AS tipe, c.max_length
              FROM sys.columns c
              JOIN sys.types t ON t.user_type_id = c.user_type_id
             WHERE c.object_id = OBJECT_ID('N_HRIS_Notification')") as $c) {
            $panjang = in_array($c->tipe, ['varchar', 'char'], true) ? '('.$c->max_length.')' : '';
            $katalog[$c->name] = $c->tipe.$panjang;
        }

        $this->assertNotEmpty($katalog, 'Katalog kolom kosong — uji ini tak menguji apa pun.');

        foreach (self::HARUS_BERTIPE as $kolom => $tipe) {
            $this->assertSame(
                $katalog[$kolom] ?? null,
                $tipe,
                "CAST untuk [{$kolom}] memakai {$tipe} sementara kolomnya ".
                ($katalog[$kolom] ?? '(tidak ada)').'. CAST yang lebih pendek dari kolomnya '.
                'MEMOTONG nilai tanpa galat — perbandingan jadi salah, bukan sekadar lambat.',
            );
        }
    }
}
