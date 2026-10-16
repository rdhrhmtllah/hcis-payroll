<?php

namespace Tests\Integration\VISITOR\Workflow\Verification;

use App\Http\Controllers\VISITOR\Workflow\Verification\VerifikatorVisitorController;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kedua daftar verifikator, pada KEDUA modenya.
 *
 * Mode `riwayat` memakai ORDER BY yang berbeda dari mode `antrian`, dan versi
 * tamu-nya disalin dari versi permit — termasuk `h.Updated_At`, kolom yang ada
 * di header permit tetapi TIDAK ADA di header tamu. Akibatnya seluruh riwayat
 * tamu membalas 500, dan tak ada uji yang menyentuh mode itu sehingga cacatnya
 * lolos sampai ke layar.
 *
 * Uji ini menagih keempat kombinasi, bukan hanya jalur yang kebetulan dipakai.
 * Sejak DDL-2026-121 daftar tamu juga disaring tipe rute (Regular/B2B) dan
 * saringan status riwayat membaca status tampil turunan — dua jalur SQL baru
 * yang ikut ditagih di sini.
 */
class VerifikatorDaftarModeTest extends TestCase
{
    use DatabaseTransactions;

    private const PIC = 'A1';

    /**
     * @return array<string,array{0:string,1:string,2:?string,3:?string}>
     */
    public static function kombinasi(): array
    {
        return [
            'permit antrian' => ['getPermitList', 'antrian', null, null],
            'permit riwayat' => ['getPermitList', 'riwayat', null, null],
            'tamu antrian' => ['getTamuList', 'antrian', null, null],
            'tamu riwayat' => ['getTamuList', 'riwayat', null, null],
            'tamu b2b antrian' => ['getTamuList', 'antrian', TipeTamu::B2B, null],
            'tamu b2b riwayat' => ['getTamuList', 'riwayat', TipeTamu::B2B, null],
            'tamu riwayat status turunan' => ['getTamuList', 'riwayat', null, StatusKunjunganTamu::CHECKED_OUT],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('kombinasi')]
    public function test_daftar_verifikator_terbaca_pada_kedua_mode(string $method, string $mode, ?string $tipe, ?string $status): void
    {
        $query = ['mode' => $mode, 'limit' => 5];
        if ($status !== null) {
            $query['status'] = $status;
        }

        $response = app(VerifikatorVisitorController::class)->{$method}($this->request($query, $tipe));
        $isi = json_decode($response->getContent(), true);

        $this->assertSame(
            200,
            $response->getStatusCode(),
            "{$method} mode {$mode} gagal: ".($isi['message'] ?? $response->getContent()),
        );
        $this->assertTrue($isi['success'] ?? false);
    }

    public function test_antrian_tamu_hanya_memuat_tipe_rute_dan_status_pending(): void
    {
        $penanda = 'DMT'.Str::upper(Str::random(8));
        $regularPending = $this->buatTamu($penanda, TipeTamu::REGULAR, StatusKunjunganTamu::PENDING_APPROVAL);
        $regularTerjadwal = $this->buatTamu($penanda, TipeTamu::REGULAR, StatusKunjunganTamu::SCHEDULED);
        $b2bPending = $this->buatTamu($penanda, TipeTamu::B2B, StatusKunjunganTamu::PENDING_APPROVAL);

        $regular = $this->nomorDiDaftar(['mode' => 'antrian', 'search' => $penanda], null);
        $b2b = $this->nomorDiDaftar(['mode' => 'antrian', 'search' => $penanda], TipeTamu::B2B);

        $this->assertSame([$regularPending], $regular, 'Antrean Regular memuat baris tipe lain atau yang sudah diputus.');
        $this->assertSame([$b2bPending], $b2b, 'Antrean B2B memuat baris tipe lain.');
        $this->assertNotContains($regularTerjadwal, $regular);
    }

    public function test_riwayat_tamu_membuang_pending_dan_menyaring_tipe(): void
    {
        $penanda = 'DMT'.Str::upper(Str::random(8));
        $this->buatTamu($penanda, TipeTamu::REGULAR, StatusKunjunganTamu::PENDING_APPROVAL);
        $ditolak = $this->buatTamu($penanda, TipeTamu::REGULAR, StatusKunjunganTamu::REJECTED);
        $ditarik = $this->buatTamu($penanda, TipeTamu::REGULAR, StatusKunjunganTamu::WITHDRAWN);
        $b2bDicabut = $this->buatTamu($penanda, TipeTamu::B2B, StatusKunjunganTamu::REVOKED);

        $regular = $this->nomorDiDaftar(['mode' => 'riwayat', 'search' => $penanda, 'limit' => 50], null);
        sort($regular);
        $harapan = [$ditolak, $ditarik];
        sort($harapan);

        $this->assertSame($harapan, $regular);
        $this->assertSame(
            [$b2bDicabut],
            $this->nomorDiDaftar(['mode' => 'riwayat', 'search' => $penanda], TipeTamu::B2B),
        );
        // Saringan status riwayat memakai kode baru.
        $this->assertSame(
            [$ditolak],
            $this->nomorDiDaftar(['mode' => 'riwayat', 'search' => $penanda, 'status' => StatusKunjunganTamu::REJECTED], null),
        );
    }

    public function test_statistik_verifikator_terbaca_per_tipe(): void
    {
        // getStats() kini menerima Request: angka tamu mengikuti tipe rute.
        foreach ([null, TipeTamu::B2B] as $tipe) {
            $response = app(VerifikatorVisitorController::class)->getStats($this->request([], $tipe));

            $this->assertSame(200, $response->getStatusCode(), 'Statistik tipe '.($tipe ?? 'REGULAR').' gagal: '.$response->getContent());
        }
    }

    // ---------------------------------------------------------------- helpers

    /**
     * `$tipe` meniru middleware visitor.tipe-tamu:<TIPE>; null = rute Regular.
     */
    private function request(array $query, ?string $tipe): Request
    {
        $request = Request::create('/verifikator-visitor', 'GET', $query);

        if ($tipe !== null) {
            $request->attributes->set(TipeTamu::ATRIBUT, $tipe);
        }

        return $request;
    }

    /** @return list<string> */
    private function nomorDiDaftar(array $query, ?string $tipe): array
    {
        $response = app(VerifikatorVisitorController::class)->getTamuList($this->request($query + ['limit' => 50], $tipe));
        $isi = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode(), 'Daftar tamu gagal: '.($isi['message'] ?? $response->getContent()));

        return array_values(array_map(fn (array $baris) => $baris['No_Transaksi'], $isi['result'] ?? []));
    }

    private function buatTamu(string $penanda, string $tipe, string $status): string
    {
        $nomor = 'VSR-DMT-'.Str::upper(Str::random(8));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Tamu')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_PIC' => self::PIC,
            'Status_Kunjungan' => $status,
            'Needs_Approval' => 'Y',
            'Flag_Approval' => match ($status) {
                StatusKunjunganTamu::PENDING_APPROVAL => null,
                StatusKunjunganTamu::REJECTED, StatusKunjunganTamu::WITHDRAWN => 'T',
                default => 'Y',
            },
            'Tanggal_Kunjungan' => now()->addDay()->toDateString(),
            'Flag_Aktif' => 'Y',
            'Created_At' => now(),
            'Created_By' => self::PIC,
            'Idempotency_Key' => 'DMT-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Tamu_Detail')->insert([
            'Id_Transaksi_Tamu' => $id,
            'No_Transaksi' => $nomor,
            // Penanda di nama supaya pencarian hanya menemukan baris uji ini.
            'Nama_Lengkap_Tamu' => 'Tamu '.$penanda,
            'Tipe_Tamu' => $tipe,
            'Instansi_Atau_Asal' => 'PT Uji',
            'Deksripsi_Keperluan' => 'Meeting',
            'Jumlah_Tamu' => 1,
            'Jenis_Kendaraan' => 'Tidak Berkendara',
            'Waktu_Mulai_Kunjungan' => now()->addDay()->setTime(8, 0)->format('Y-m-d H:i:s'),
            'Waktu_Selesai_Kunjungan' => now()->addDay()->setTime(17, 0)->format('Y-m-d H:i:s'),
        ]);

        return $nomor;
    }
}
