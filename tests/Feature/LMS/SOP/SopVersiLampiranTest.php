<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopVersiLampiranService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Form kosong (lampiran unduhan) per versi dokumen FORM.
 *
 * Berkas utama versi FORM adalah contoh pengisian; karyawan juga butuh form
 * kosongnya untuk diisi sendiri — boleh beberapa per versi. Aturan ubahnya
 * sama dengan berkas: bebas selama draft, sesudah terbit masih boleh selama
 * dokumennya belum dijadwalkan ke karyawan.
 *
 * Spesifikasi: docs/superpowers/specs/2026-10-05-sop-lampiran-form-kosong-design.md
 */
class SopVersiLampiranTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    private function lampiran(): SopVersiLampiranService
    {
        return app(SopVersiLampiranService::class);
    }

    /** @return array{0:int,1:int} [idDokumen, idVersi] */
    private function versiForm(string $tipe = 'FORM'): array
    {
        $idDok = $this->svc()->buat([
            'Judul' => 'UJI LAMPIRAN '.substr((string) microtime(true), -5),
            'Tipe' => $tipe,
        ], 1);
        $idVersi = $this->svc()->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Awal'], 1);
        $this->assertNotNull($idVersi);

        return [$idDok, (int) $idVersi];
    }

    /** @return list<int> tiga konten SOP aktif berjenis dokumen */
    private function konten(): array
    {
        $ids = DB::table('N_LMS_Master_Konten')
            ->where('Scope', 'SOP')->where('Flag_Aktif', 'Y')->where('Tipe_Konten', 'document')
            ->orderBy('Id_Master_Konten')->limit(3)
            ->pluck('Id_Master_Konten')->map(fn ($v) => (int) $v)->all();
        if (count($ids) < 3) {
            $this->markTestSkipped('Butuh tiga konten SOP berjenis dokumen.');
        }

        return $ids;
    }

    private function kirim(array $ids): array
    {
        return array_map(fn ($id) => ['Id_Master_Konten' => $id], $ids);
    }

    private function jadikanTerbit(int $idVersi, string $status = SopStatus::VERSI_PUBLISHED, string $tahap = 'FILING_REGISTERED'): void
    {
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Status' => $status,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', $tahap)->value('Id_Tahap'),
        ]);
    }

    /** Sama dengan SopIzinUbahVersiTest::jadwalkan(): paket berisi dokumen ini, dijadwalkan ke satu karyawan. */
    private function jadwalkan(int $idDok): void
    {
        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'UJI JADWAL '.substr((string) microtime(true), -5),
            'Prioritas' => 'KRITIS', 'Mode_Urutan_Item' => 'BEBAS', 'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Absensi' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Paket');
        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket, 'Id_SOP_Dokumen' => $idDok, 'Tipe_Item' => 'FORM',
            'Judul_Item' => 'Uji', 'Urutan' => 1, 'Flag_Wajib' => 'Y', 'Flag_Highlight' => 'T',
            'Flag_Watermark' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ]);
        $idJadwal = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket, 'Sumber' => 'MANUAL', 'Tipe_Tenggat' => 'DEADLINE',
            'Flag_Reminder' => 'T', 'Flag_Cabut_Akses' => 'T', 'Tanggal_Assign' => now(),
            'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Penjadwalan');
        DB::table('N_SOP_Penjadwalan_Detail')->insert([
            'Id_SOP_Penjadwalan' => $idJadwal,
            'Kode_Karyawan' => DB::table('Karyawan')->where('Aktif', 'Y')->value('Kode_Karyawan'),
            'Status_Paket' => 'PROGRESS', 'Flag_Cabut_Akses' => 'T', 'Jumlah_Reminder' => 0,
            'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ]);
    }

    public function test_draft_menyimpan_beberapa_form_kosong_berurutan(): void
    {
        [$k1, $k2] = $this->konten();
        [, $v] = $this->versiForm();

        $this->assertNull($this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k2, $k1])], 1));

        $this->assertSame([$k2, $k1], $this->lampiran()->aktif($v));
    }

    public function test_simpan_ulang_menulis_selisih_dan_mencatat_jejak(): void
    {
        [$k1, $k2, $k3] = $this->konten();
        [, $v] = $this->versiForm();
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1, $k2])], 1);

        $this->assertNull($this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k2, $k3])], 1));

        $this->assertSame([$k2, $k3], $this->lampiran()->aktif($v));
        $this->assertSame('T', DB::table('N_SOP_Item_Versi_Lampiran')
            ->where('Id_SOP_Item_Versi', $v)->where('Id_Master_Konten', $k1)->value('Flag_Aktif'),
            'Lampiran yang dilepas harus dinonaktifkan, bukan dihapus.');

        $meta = json_decode((string) DB::table('N_SOP_Log')
            ->where('Id_SOP_Item_Versi', $v)->where('Aksi', 'VERSI_UBAH')
            ->orderByDesc('Id_SOP_Log')->value('Meta_JSON'), true);
        $this->assertSame([$k3], $meta['lampiran_masuk'] ?? null);
        $this->assertSame([$k1], $meta['lampiran_keluar'] ?? null);
    }

    public function test_larik_kosong_melepas_semua(): void
    {
        [$k1, $k2] = $this->konten();
        [, $v] = $this->versiForm();
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1, $k2])], 1);

        $this->assertNull($this->svc()->ubahVersi($v, ['Lampiran' => []], 1));

        $this->assertSame([], $this->lampiran()->aktif($v));
    }

    public function test_tanpa_kunci_lampiran_tidak_menyentuhnya(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm();
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1);

        $this->assertNull($this->svc()->ubahVersi($v, ['Ringkasan_Perubahan' => 'Hanya ringkasan'], 1));

        $this->assertSame([$k1], $this->lampiran()->aktif($v));
    }

    public function test_konten_kembar_ditolak(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('dua kali');
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1, $k1])], 1);
    }

    public function test_konten_tak_dikenal_ditolak(): void
    {
        [, $v] = $this->versiForm();

        $this->expectException(\DomainException::class);
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([999999999])], 1);
    }

    public function test_tipe_selain_form_menolak_lampiran(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm('DOKUMEN');

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('tidak berlaku');
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1);
    }

    public function test_terbit_belum_dijadwalkan_menerima_lampiran(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm();
        $this->jadikanTerbit($v);

        $this->assertNull($this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1));
        $this->assertSame([$k1], $this->lampiran()->aktif($v));
    }

    public function test_terbit_sudah_dijadwalkan_menolak_perubahan_tapi_menerima_daftar_sama(): void
    {
        [$k1, $k2] = $this->konten();
        [$idDok, $v] = $this->versiForm();
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1);
        $this->jadikanTerbit($v);
        $this->jadwalkan($idDok);

        $galat = $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1, $k2])], 1);
        $this->assertNotNull($galat);
        $this->assertStringContainsString('dijadwalkan', $galat);
        $this->assertSame([$k1], $this->lampiran()->aktif($v), 'Ditolak tapi tetap tertulis.');

        /* Formulir mengirim seluruh isian; daftar yang sama bukan perubahan. */
        $this->assertNull($this->svc()->ubahVersi($v, [
            'Lampiran' => $this->kirim([$k1]),
            'Ringkasan_Perubahan' => 'Koreksi',
        ], 1));
    }

    public function test_arsip_menolak_lampiran(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm();
        $this->jadikanTerbit($v, SopStatus::VERSI_OBSOLETE, 'OBSOLETE');

        $this->assertNotNull($this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1));
        $this->assertSame([], $this->lampiran()->aktif($v));
    }

    public function test_versi_baru_menerima_lampiran_sejak_lahir(): void
    {
        [$k1, $k2] = $this->konten();
        [$idDok] = $this->versiForm();

        $v2 = (int) $this->svc()->tambahVersi($idDok, [
            'Ringkasan_Perubahan' => 'Revisi',
            'Lampiran' => $this->kirim([$k1, $k2]),
        ], 1);

        $this->assertSame([$k1, $k2], $this->lampiran()->aktif($v2));
    }

    private function logTerakhir(int $v): object
    {
        return DB::table('N_SOP_Log')->where('Id_SOP_Item_Versi', $v)->where('Aksi', 'VERSI_UBAH')
            ->orderByDesc('Id_SOP_Log')->first(['Keterangan', 'Meta_JSON']);
    }

    /** Tinjauan akhir: simpanan yang hanya menambah form kosong tercatat "tanpa perubahan nilai". */
    public function test_simpan_hanya_lampiran_tercatat_sebagai_perubahan(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm();

        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1);

        $log = $this->logTerakhir($v);
        $this->assertStringNotContainsString('tanpa perubahan nilai', $log->Keterangan);
        $this->assertContains('Form kosong', array_column(json_decode($log->Meta_JSON, true)['perubahan'] ?? [], 'field'));
    }

    /** Tinjauan akhir: menggeser urutan saja tidak meninggalkan jejak apa pun. */
    public function test_menggeser_urutan_saja_tercatat(): void
    {
        [$k1, $k2] = $this->konten();
        [, $v] = $this->versiForm();
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1, $k2])], 1);

        $this->assertNull($this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k2, $k1])], 1));

        $this->assertSame([$k2, $k1], $this->lampiran()->aktif($v));
        $this->assertContains('Form kosong', array_column(
            json_decode($this->logTerakhir($v)->Meta_JSON, true)['perubahan'] ?? [], 'field'));
    }

    /**
     * Tinjauan akhir: berkas yang sudah terpasang lalu tak lagi lolos validasi
     * (mis. Scope-nya berubah) tak boleh membuat SETIAP simpanan versi itu
     * gagal — formulir selalu mengirim ulang daftar yang sama.
     */
    public function test_daftar_sama_tidak_divalidasi_ulang(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm();
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1);
        DB::table('N_LMS_Master_Konten')->where('Id_Master_Konten', $k1)->update(['Scope' => 'LMS']);

        $this->assertNull($this->svc()->ubahVersi($v, [
            'Lampiran' => $this->kirim([$k1]),
            'Ringkasan_Perubahan' => 'Hanya ringkasan',
        ], 1));
    }

    /* ══ LEWAT HTTP: wizard menyimpan dan membaca ════════════════════════ */

    public function test_wizard_menyimpan_dan_membaca_lampiran_dalam_satu_kueri(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }
        [$k1, $k2] = $this->konten();
        [$idDok, $v] = $this->versiForm();
        $h = fn ($id) => Hashids::connection('custom')->encode($id);

        $this->actingAs($admin)->putJson('/api/v1/sop/admin/dokumen/versi/'.$h($v), [
            'Lampiran' => [['Id_Master_Konten' => $h($k2)], ['Id_Master_Konten' => $h($k1)]],
        ])->assertOk();
        $this->assertSame([$k2, $k1], $this->lampiran()->aktif($v));

        $kueriLampiran = 0;
        DB::listen(function ($q) use (&$kueriLampiran) {
            if (str_contains(strtolower($q->sql), 'n_sop_item_versi_lampiran')) {
                $kueriLampiran++;
            }
        });

        $baris = collect(
            $this->actingAs($admin)
                ->getJson('/api/v1/sop/admin/dokumen/'.$h($idDok).'/versi?limit=50')
                ->assertOk()->json('result.rows')
        )->firstWhere('Id_SOP_Item_Versi', $h($v));

        $this->assertNotNull($baris);
        $this->assertSame([$h($k2), $h($k1)], array_column($baris['Lampiran'] ?? [], 'Id_Master_Konten'));
        $this->assertNotEmpty($baris['Lampiran'][0]['Judul'] ?? null);
        $this->assertLessThanOrEqual(1, $kueriLampiran, 'Lampiran dibaca per versi, bukan sekali per halaman.');
    }

    public function test_wizard_menolak_hash_konten_rusak(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }
        [, $v] = $this->versiForm();

        $this->actingAs($admin)->putJson('/api/v1/sop/admin/dokumen/versi/'
            .Hashids::connection('custom')->encode($v), [
                'Lampiran' => [['Id_Master_Konten' => 'bukan-hash!']],
            ])->assertStatus(422);
    }

    /** Modal "Lihat Detail" versi juga menyebut form kosongnya (permintaan user 5 Okt 2026). */
    public function test_detail_versi_membawa_lampiran(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }
        [$k1, $k2] = $this->konten();
        [, $v] = $this->versiForm();
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k2, $k1])], 1);
        $h = fn ($id) => Hashids::connection('custom')->encode($id);

        $lampiran = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/versi/'.$h($v).'/detail')
            ->assertOk()->json('result.Lampiran');

        $this->assertSame([$h($k2), $h($k1)], array_column($lampiran ?? [], 'Id_Master_Konten'));
        $this->assertNotEmpty($lampiran[0]['Judul'] ?? null);
    }

    /* ══ TABEL BELUM ADA (DDL-2026-158 belum dijalankan) ═════════════════
       Kode bisa tayang lebih dulu daripada DDL-nya di production. Halaman
       yang membaca lampiran tak boleh runtuh karenanya; yang hilang hanya
       fiturnya. */

    private int $kueriLampiran = 0;

    /** Anggap tabelnya belum ada, dan hitung kueri yang masih menyentuhnya. */
    private function tanpaTabel(): void
    {
        $palsu = \Mockery::mock(SopVersiLampiranService::class)->makePartial();
        $palsu->shouldReceive('tersedia')->andReturn(false);
        $this->app->instance(SopVersiLampiranService::class, $palsu);

        DB::listen(function ($q) {
            if (str_contains(strtolower($q->sql), 'n_sop_item_versi_lampiran')) {
                $this->kueriLampiran++;
            }
        });
    }

    public function test_tanpa_tabel_simpan_versi_tetap_jalan(): void
    {
        [, $v] = $this->versiForm();
        $this->tanpaTabel();

        $this->assertNull($this->svc()->ubahVersi($v, ['Ringkasan_Perubahan' => 'Tetap bisa', 'Lampiran' => []], 1));
        $this->assertSame([], app(SopVersiLampiranService::class)->untukVersi([$v]));
        $this->assertSame([], app(SopVersiLampiranService::class)->aktif($v));
        $this->assertSame(0, $this->kueriLampiran, 'Tabel yang belum ada tetap dikueri.');
    }

    public function test_tanpa_tabel_menambah_lampiran_ditolak_dengan_jelas(): void
    {
        [$k1] = $this->konten();
        [, $v] = $this->versiForm();
        $this->tanpaTabel();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('belum aktif');
        $this->svc()->ubahVersi($v, ['Lampiran' => $this->kirim([$k1])], 1);
    }

    public function test_tanpa_tabel_riwayat_versi_tetap_terbuka(): void
    {
        $admin = User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopDokumenPage'));
        if (! $admin) {
            $this->markTestSkipped('Belum ada user dengan akses menuSopDokumenPage.');
        }
        [$idDok] = $this->versiForm();
        $this->tanpaTabel();

        $baris = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/dokumen/'.Hashids::connection('custom')->encode($idDok).'/versi?limit=50')
            ->assertOk()->json('result.rows');

        $this->assertSame([], $baris[0]['Lampiran'] ?? null);
        $this->assertSame(0, $this->kueriLampiran, 'Tabel yang belum ada tetap dikueri.');
    }
}
