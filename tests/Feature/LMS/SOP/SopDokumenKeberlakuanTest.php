<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\SopProgressReadService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Status keberlakuan dokumen diturunkan di server, bukan di browser.
 *
 * Alasannya bukan gaya: "dokumen ini masih berlaku" adalah klaim audit, dan
 * jam browser bisa salah, bergeser zona waktu, atau diubah pengguna.
 *
 * MASA BERLAKU DICABUT 15 Agu 2026. Sebuah versi berlaku sampai digantikan
 * versi yang lebih baru — tak ada lagi KEDALUWARSA maupun PERLU_TINJAU. Yang
 * tersisa dua aturan: versi yang sudah digantikan, dan versi yang tanggal
 * berlakunya belum tiba.
 */
class SopDokumenKeberlakuanTest extends TestCase
{
    private function statusDari(array $item): string
    {
        return app(SopProgressReadService::class)->keberlakuanItem($item)['status'];
    }

    private function hariLalu(int $n): string
    {
        return now()->subDays($n)->format('Y-m-d');
    }

    private function hariDepan(int $n): string
    {
        return now()->addDays($n)->format('Y-m-d');
    }

    /** Item versi aktif tanpa batasan tanggal apa pun. */
    private function itemAktif(array $ubah = []): array
    {
        return array_merge([
            'Id_Item_Versi_Aktif' => 10,
            'Id_SOP_Item_Versi' => 10,
            'Tanggal_Berlaku' => null,
        ], $ubah);
    }

    public function test_active_version_without_any_date_still_reports_a_status(): void
    {
        $this->assertSame('BERLAKU', $this->statusDari($this->itemAktif()));
    }

    public function test_document_effective_in_the_future_is_not_yet_valid(): void
    {
        $this->assertSame('BELUM_BERLAKU', $this->statusDari($this->itemAktif([
            'Tanggal_Berlaku' => $this->hariDepan(7),
        ])));
    }

    /**
     * Tanggal yang sudah lama lewat TIDAK lagi menggugurkan apa pun.
     *
     * Enam tanggal kadaluarsa masih tersimpan di data lama dan kolomnya sengaja
     * tidak dijatuhkan. Kalau suatu saat ada yang membacanya lagi, uji ini yang
     * merah lebih dulu — bukan karyawan yang tiba-tiba diminta membaca ulang.
     */
    public function test_tanggal_lama_tak_lagi_menggugurkan_keberlakuan(): void
    {
        $this->assertSame('BERLAKU', $this->statusDari($this->itemAktif([
            'Tanggal_Berlaku' => $this->hariLalu(400),
            'Tanggal_Kadaluarsa' => $this->hariLalu(1),
            'Tanggal_Review' => $this->hariLalu(30),
        ])));
    }

    public function test_superseded_version_outranks_every_date_rule(): void
    {
        $this->assertSame('VERSI_LAMA', $this->statusDari($this->itemAktif([
            'Id_SOP_Item_Versi' => 9,
            'Id_Item_Versi_Aktif' => 10,
            'Tanggal_Berlaku' => $this->hariLalu(2),
        ])));
    }

    public function test_effective_today_is_already_valid(): void
    {
        $this->assertSame('BERLAKU', $this->statusDari($this->itemAktif([
            'Tanggal_Berlaku' => now()->format('Y-m-d'),
        ])));
    }

    /** @return array{0:string,1:int} [kodeKaryawan, idPaket] */
    private function penugasanNyata(): array
    {
        $d = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as p', 'p.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('d.Flag_Aktif', 'Y')
            ->first(['d.Kode_Karyawan', 'p.Id_SOP_Paket']);

        $this->assertNotNull($d, 'Butuh minimal satu penugasan aktif di DB.');

        return [$d->Kode_Karyawan, (int) $d->Id_SOP_Paket];
    }

    public function test_detail_bundle_attaches_validity_to_every_document(): void
    {
        [$kode, $idPaket] = $this->penugasanNyata();

        $bundle = app(SopProgressReadService::class)->detailBundle($kode, $idPaket);

        $this->assertNotEmpty($bundle['items'], 'Paket uji harus punya item.');
        foreach ($bundle['items'] as $item) {
            $this->assertArrayHasKey('Keberlakuan', $item);
            $this->assertContains($item['Keberlakuan']['status'], [
                'BERLAKU', 'KEDALUWARSA', 'BELUM_BERLAKU', 'PERLU_TINJAU', 'VERSI_LAMA',
            ]);
        }
    }

    public function test_validity_derivation_touches_no_database(): void
    {
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        // Dipanggil sekali per item; kalau menyentuh DB, biayanya jadi N+1.
        app(SopProgressReadService::class)->keberlakuanItem($this->itemAktif([
            'Tanggal_Berlaku' => $this->hariLalu(1),
        ]));

        $this->assertSame(0, $queries, 'Turunan status harus murni, tanpa query.');
    }

    public function test_version_history_exposes_governance_fields_for_the_previous_version(): void
    {
        [$kode, $idPaket] = $this->penugasanNyata();
        $svc = app(SopProgressReadService::class);

        $bundle = $svc->detailBundle($kode, $idPaket);
        $item = collect($bundle['items'])->first(fn ($i) => ! empty($i['Ada_Riwayat']))
            ?: $bundle['items'][0];

        $riwayat = $svc->itemVersiHistory($kode, $idPaket, (int) $this->decodeId($item['Id_SOP_Paket_Item']));

        $this->assertIsArray($riwayat);
        $this->assertNotEmpty($riwayat, 'Item uji harus punya minimal satu versi.');

        foreach (['Tanggal_Berlaku', 'Disahkan_Oleh', 'Jabatan_Pengesah',
            'Tanggal_Pengesahan', 'Nomor_Pengesahan', 'Keberlakuan'] as $field) {
            $this->assertArrayHasKey($field, $riwayat[0], "Field {$field} belum ikut dikirim.");
        }
    }

    private function decodeId(string $hash): int
    {
        return (int) (\Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($hash)[0] ?? 0);
    }

    public function test_status_carries_a_reason_for_the_reader(): void
    {
        $hasil = app(SopProgressReadService::class)->keberlakuanItem($this->itemAktif([
            'Tanggal_Berlaku' => $this->hariDepan(3),
        ]));

        $this->assertArrayHasKey('alasan', $hasil);
        $this->assertNotSame('', (string) $hasil['alasan']);
    }
}
