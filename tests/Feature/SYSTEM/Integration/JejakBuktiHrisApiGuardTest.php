<?php

namespace Tests\Feature\SYSTEM\Integration;

use Tests\TestCase;

/**
 * Registry generik N_HRIS_Api_* harus memberi bukti yang setara dengan registry
 * callback HCLEARN, sambil tetap memakai kontrak nilai milik tabelnya sendiri.
 *
 * Bug lama sama persis dengan LmsApiGuard: status 200 dipaku sebelum controller
 * dijalankan dan enam penolakan paling awal tidak menghasilkan baris apa pun.
 * Uji nyata juga menemukan dua jebakan skema yang tidak terlihat dari nama
 * kolom: Arah hanya varchar(3) + CHECK IN/OUT, sedangkan Sumber_Error hanya
 * menerima NONE/NETWORK/SIGNATURE/NONCE/AUTH/SERVER/CLIENT.
 */
class JejakBuktiHrisApiGuardTest extends TestCase
{
    private const GUARD = 'app/Http/Middleware/HrisApiGuard.php';

    private function isi(): string
    {
        return file_get_contents(base_path(self::GUARD));
    }

    /** @test */
    public function status_dicatat_dari_response_setelah_controller_dijalankan(): void
    {
        $isi = $this->isi();

        $this->assertStringNotContainsString('logAttempt(', $isi);
        $this->assertStringContainsString('$status = $response->getStatusCode();', $isi);

        $handle = substr($isi, strpos($isi, 'public function handle('));
        $handle = substr($handle, 0, strpos($handle, 'private function periksa('));

        $this->assertSame(
            preg_match_all('/^\s*return /m', $handle),
            substr_count($handle, '$this->catat('),
            'Setiap jalur keluar handle() harus melewati catat().',
        );
    }

    /** @test */
    public function nilai_kategori_sesuai_check_constraint_yang_ada(): void
    {
        $isi = $this->isi();

        $this->assertStringContainsString("'arah' => 'IN'", $isi);
        $this->assertStringNotContainsString("'arah' => 'INBOUND'", $isi);

        foreach (['AUTH', 'SIGNATURE', 'NONCE', 'CLIENT', 'SERVER', 'NONE'] as $nilai) {
            $this->assertStringContainsString("'{$nilai}'", $isi);
        }

        foreach (['GERBANG', 'APLIKASI'] as $nilaiTakSah) {
            $this->assertStringNotContainsString(
                "'{$nilaiTakSah}'",
                $isi,
                "$nilaiTakSah bukan nilai yang diizinkan CK_N_HRIS_Api_Log_Src.",
            );
        }
    }

    /** @test */
    public function identitas_grup_hash_payload_dan_correlation_id_ikut_dicatat(): void
    {
        $isi = $this->isi();

        foreach ([
            "\$jejak['app'] = (int) \$app->Id_Api_Aplikasi;",
            "\$jejak['grup'] = (int) \$app->Id_Grup_Fitur;",
            "'Payload_Hash' => hash('sha256', (string) \$request->getContent()),",
            "\$response->headers->set('X-HC-Correlation-Id', \$correlationId);",
        ] as $jangkar) {
            $this->assertStringContainsString($jangkar, $isi);
        }
    }

    /** @test */
    public function pola_whitelist_tetap_dipisahkan_dari_jalur_hmac(): void
    {
        $isi = $this->isi();

        $this->assertStringContainsString("['pola' => \$polaRute, 'tandaTangan' => \$path]", $isi);
        $this->assertStringContainsString("->where('ep.Url_Route', \$polaRute)", $isi);
        $this->assertStringContainsString('$payload = $method."\\n".$path.', $isi);
    }

    /** @test */
    public function kegagalan_mencatat_tidak_menggagalkan_api(): void
    {
        $catat = substr($this->isi(), strpos($this->isi(), 'private function catat('));

        $this->assertMatchesRegularExpression(
            '/DB::table\(\x27N_HRIS_Api_Log\x27\)->insert\(\[.*?\]\);\s*\}\s*catch \(\\\\Throwable/s',
            $catat,
        );
    }

    /** @test */
    public function pesan_exception_tidak_bocor_ke_log_berkas(): void
    {
        $isi = $this->isi();

        $this->assertStringNotContainsString(
            "'message' => \$e->getMessage()",
            $isi,
            'QueryException::getMessage() menyertakan SQL + binding yang bisa memuat token; '.
            'file log cukup menyimpan kelas + kode error dengan correlation ID sebagai jangkar.',
        );
        $this->assertStringContainsString(
            "'exception' => \$e::class",
            $isi,
        );
    }
}
