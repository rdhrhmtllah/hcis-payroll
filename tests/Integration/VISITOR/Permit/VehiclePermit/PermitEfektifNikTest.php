<?php

namespace Tests\Integration\VISITOR\Permit\VehiclePermit;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * N_VISITOR_Permit_Efektif.NIK harus terisi dari Karyawan.No_Induk_Karyawan.
 *
 * Kolomnya ada di tabel sejak lama, tetapi tidak pernah ditulis oleh satu baris
 * kode pun: payload insert dan update di VisitorEfektifService tidak punya
 * kuncinya, dan tabelnya tidak punya trigger. Hasilnya kolom yang selalu NULL
 * tanpa satu pun error — proyeksi terlihat lengkap sampai ada yang membacanya.
 *
 * Uji ini menagih pengisiannya lewat efeknya di tabel, bukan lewat pemanggilan
 * method, supaya penambahan jalur sync kedua yang lupa mengisi NIK ikut
 * ketahuan.
 */
class PermitEfektifNikTest extends TestCase
{
    use DatabaseTransactions;

    public function test_nik_terisi_dari_no_induk_karyawan_pemohon(): void
    {
        [$kode, $induk] = $this->karyawanBerNomorInduk();

        $nomor = $this->buatPermit($kode);
        $this->sync($nomor);

        $this->assertSame($induk, $this->nik($nomor));
    }

    public function test_nik_bukan_disalin_dari_kode_karyawan(): void
    {
        // Penjaga arah salin. Kalau suatu saat NIK diisi dari
        // Kode_Karyawan_Pemohon — kolom yang kebetulan juga ada di payload dan
        // juga selalu terisi — uji pertama tetap perlu tahu bedanya.
        [$kode, $induk] = $this->karyawanBerNomorInduk();

        $this->assertNotSame(
            $kode,
            $induk,
            "Prasyarat gagal: karyawan {$kode} punya No_Induk_Karyawan yang sama persis dengan kodenya, sehingga uji ini tidak bisa membedakan keduanya.",
        );

        $nomor = $this->buatPermit($kode);
        $this->sync($nomor);

        $this->assertNotSame($kode, $this->nik($nomor));
    }

    public function test_nomor_induk_yang_kosong_disimpan_sebagai_null(): void
    {
        // 63 dari 965 baris Karyawan belum punya nomor induk. Yang kosong harus
        // jatuh ke NULL, bukan string kosong: dua bentuk "kosong" di satu kolom
        // membuat setiap penyaring dan laporan harus menangani keduanya, dan
        // yang lupa akan diam-diam melewatkan sebagian baris.
        $kode = $this->karyawanTanpaNomorInduk();

        $nomor = $this->buatPermit($kode);
        $this->sync($nomor);

        $this->assertNull($this->nik($nomor));
    }

    public function test_sync_ulang_mengisi_baris_lama_yang_nik_nya_masih_kosong(): void
    {
        // NIK ikut di payload update, bukan hanya insert. Kalau hanya insert,
        // baris yang terlanjur tersimpan sebelum perbaikan ini akan kosong
        // selamanya walaupun permitnya disetujui ulang.
        [$kode, $induk] = $this->karyawanBerNomorInduk();

        $nomor = $this->buatPermit($kode);
        $this->sync($nomor);

        DB::table('N_VISITOR_Permit_Efektif')
            ->where('No_Transaksi', $nomor)
            ->update(['NIK' => null]);

        $this->assertNull($this->nik($nomor), 'Prasyarat gagal: barisnya tidak berhasil dikosongkan.');

        $this->sync($nomor);

        $this->assertSame($induk, $this->nik($nomor));
    }

    public function test_baris_efektif_memang_dibuat_oleh_sync(): void
    {
        // Penjaga kerja-nyata. Kalau syncPermitEfektif() diam-diam berhenti
        // bekerja — misalnya karena isApprovalComplete() berubah arti — semua
        // uji di atas akan gagal dengan pesan "baris tidak ditemukan", bukan
        // lulus dengan angka sempurna. Uji ini menagihnya secara langsung
        // supaya sebabnya terbaca tanpa menebak.
        [$kode] = $this->karyawanBerNomorInduk();

        $nomor = $this->buatPermit($kode);

        $this->assertSame(
            0,
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->count(),
            'Baris efektif sudah ada sebelum sync dipanggil.',
        );

        $this->sync($nomor);

        $this->assertSame(
            1,
            DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->count(),
        );
    }

    // ---------------------------------------------------------------- helpers

    private function nik(string $nomor): ?string
    {
        $baris = DB::table('N_VISITOR_Permit_Efektif')->where('No_Transaksi', $nomor)->first();

        $this->assertNotNull($baris, "Baris efektif untuk {$nomor} tidak dibuat.");

        return $baris->NIK;
    }

    private function sync(string $nomor): void
    {
        app(VisitorEfektifService::class)->syncPermitEfektif($nomor);
    }

    /**
     * Dibaca dari master, bukan ditulis sebagai angka mutlak: nomor induk
     * karyawan berubah di luar kendali uji ini, dan fixture yang mengunci satu
     * nilai akan gagal bukan karena kodenya salah.
     *
     * @return array{0: string, 1: string} [Kode_Karyawan, No_Induk_Karyawan]
     */
    private function karyawanBerNomorInduk(): array
    {
        $baris = DB::table('Karyawan')
            ->whereNotNull('No_Induk_Karyawan')
            ->whereRaw("LTRIM(RTRIM(No_Induk_Karyawan)) <> ''")
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'No_Induk_Karyawan']);

        $this->assertNotNull(
            $baris,
            'Tidak ada satu pun Karyawan dengan No_Induk_Karyawan terisi. Uji ini akan lulus tanpa membuktikan apa pun, jadi sengaja digagalkan.',
        );

        return [trim($baris->Kode_Karyawan), trim($baris->No_Induk_Karyawan)];
    }

    /**
     * Karyawan (PHL) dengan No_Induk_Karyawan kosong. Development sudah tidak
     * punya satu pun sejak 6 Okt 2026, jadi bila tak ada, satu karyawan
     * dikosongkan nomor induknya di dalam transaksi uji (di-rollback). Diisi
     * string kosong, bukan NULL, supaya cabang "kosong jatuh ke NULL" teruji.
     */
    private function karyawanTanpaNomorInduk(): string
    {
        $baris = DB::table('Karyawan')
            ->where(fn ($q) => $q->whereNull('No_Induk_Karyawan')->orWhereRaw("LTRIM(RTRIM(No_Induk_Karyawan)) = ''"))
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan']);

        if (! $baris) {
            $baris = DB::table('Karyawan')->orderBy('Kode_Karyawan')->first(['Kode_Karyawan']);
            $this->assertNotNull($baris, 'Tabel Karyawan kosong.');
            DB::table('Karyawan')->where('Kode_Karyawan', $baris->Kode_Karyawan)->update(['No_Induk_Karyawan' => '']);
        }

        return trim($baris->Kode_Karyawan);
    }

    private function buatPermit(string $kodeKaryawan): string
    {
        $nomor = 'PMT-NIK-'.Str::upper(Str::random(10));

        $id = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
            'No_Transaksi' => $nomor,
            'Kode_Karyawan_Pemohon' => $kodeKaryawan,
            'Flag_Aktif' => 'Y',
            'Flag_Approval' => 'Y',
            'Approved_At' => now()->subDay(),
            'Created_At' => now()->subDays(2),
            'Created_By' => $kodeKaryawan,
            'Idempotency_Key' => 'NIK-'.Str::upper(Str::random(20)),
        ]);

        DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insert([
            'No_Transaksi' => $nomor,
            'Id_Transaksi_Permit' => $id,
            'Plat_Nomor_Prefix' => 'BG',
            'Plat_Nomor_Angka' => (string) random_int(100000, 999999),
            'Plat_Nomor_Suffix' => 'NK',
            'Tanggal_Mulai_Berlaku' => now()->subDay()->toDateString(),
            'Tanggal_Kadaluarsa' => now()->addMonths(3)->toDateString(),
        ]);

        return $nomor;
    }
}
