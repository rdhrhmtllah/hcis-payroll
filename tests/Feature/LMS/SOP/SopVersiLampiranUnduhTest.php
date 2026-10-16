<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Karyawan mengunduh form kosong dari kartu FORM.
 *
 * Gerbangnya sama dengan berkas utama (apiVersiKonten): karyawan harus berhak
 * atas paketnya, dan yang bisa diunduh hanya milik versi yang berlaku. Lampiran
 * milik versi lain atau yang sudah dilepas admin tidak boleh terjangkau lewat
 * id tebakan.
 */
class SopVersiLampiranUnduhTest extends TestCase
{
    use DatabaseTransactions;

    private function h($id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    /** @return object{user:User,paket:int,versi:int,lampiran:int,konten:int,idDok:int} */
    private function fixture(): object
    {
        $karyawan = DB::table('Karyawan as k')
            ->join('KPI_Users as u', 'u.Id_Users', '=', 'k.UserID_Web')
            ->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Absen')
            ->orderBy('k.Kode_Karyawan')
            ->first(['k.Kode_Karyawan', 'u.Id_Users']);
        $konten = DB::table('N_LMS_Master_Konten')
            ->where('Scope', 'SOP')->where('Flag_Aktif', 'Y')->where('Tipe_Konten', 'document')
            ->whereExists(fn ($q) => $q->from('N_LMS_Master_Konten_Berkas as b')
                ->whereColumn('b.Id_Master_Konten', 'N_LMS_Master_Konten.Id_Master_Konten')
                ->whereNotNull('b.File_Path'))
            ->orderBy('Id_Master_Konten')->limit(2)->pluck('Id_Master_Konten')->map(fn ($v) => (int) $v)->all();
        if (! $karyawan || count($konten) < 2) {
            $this->markTestSkipped('Butuh karyawan beruser dan dua konten SOP berberkas.');
        }

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'UJI UNDUH LAMPIRAN '.substr((string) microtime(true), -5), 'Tipe' => 'FORM'], 1);
        $v = (int) $svc->tambahVersi($idDok, [
            'Ringkasan_Perubahan' => 'Awal',
            'Id_Master_Konten' => $konten[0],
            'Lampiran' => [['Id_Master_Konten' => $konten[1]]],
        ], 1);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $v)->update([
            'Status' => SopStatus::VERSI_PUBLISHED,
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', 'FILING_REGISTERED')->value('Id_Tahap'),
        ]);

        $idPaket = DB::table('N_SOP_Master_Paket')->insertGetId([
            'Nama_Paket' => 'UJI PAKET UNDUH '.substr((string) microtime(true), -5),
            'Prioritas' => 'KRITIS', 'Mode_Urutan_Item' => 'BEBAS', 'Status' => SopStatus::PAKET_PUBLISHED,
            'Flag_Absensi' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Paket');
        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $idPaket, 'Id_SOP_Dokumen' => $idDok, 'Id_Item_Versi_Aktif' => $v,
            'Tipe_Item' => 'FORM', 'Judul_Item' => 'Formulir uji', 'Urutan' => 1, 'Flag_Wajib' => 'Y',
            'Flag_Highlight' => 'T', 'Flag_Watermark' => 'T', 'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ]);
        $idJadwal = DB::table('N_SOP_Penjadwalan')->insertGetId([
            'Id_SOP_Paket' => $idPaket, 'Sumber' => 'MANUAL', 'Tipe_Tenggat' => 'DEADLINE',
            'Flag_Reminder' => 'T', 'Flag_Cabut_Akses' => 'T', 'Tanggal_Assign' => now(),
            'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ], 'Id_SOP_Penjadwalan');
        DB::table('N_SOP_Penjadwalan_Detail')->insert([
            'Id_SOP_Penjadwalan' => $idJadwal, 'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Status_Paket' => 'PROGRESS', 'Flag_Cabut_Akses' => 'T', 'Jumlah_Reminder' => 0,
            'Flag_Aktif' => SopStatus::AKTIF, 'Created_At' => now(),
        ]);

        return (object) [
            'user' => User::where('Id_Users', $karyawan->Id_Users)->first(),
            'paket' => $idPaket,
            'versi' => $v,
            'lampiran' => (int) DB::table('N_SOP_Item_Versi_Lampiran')->where('Id_SOP_Item_Versi', $v)->value('Id_Lampiran'),
            'konten' => $konten[1],
            'kontenUtama' => $konten[0],
            'idDok' => $idDok,
        ];
    }

    private function url(object $f, ?int $versi = null, ?int $lampiran = null): string
    {
        return '/api/v1/sop/'.$this->h($f->paket).'/versi/'.$this->h($versi ?? $f->versi)
            .'/lampiran/'.$this->h($lampiran ?? $f->lampiran);
    }

    public function test_detail_paket_membawa_lampiran_item_form(): void
    {
        $f = $this->fixture();

        $items = $this->actingAs($f->user)->getJson('/api/v1/sop/'.$this->h($f->paket))
            ->assertOk()->json('result.items');
        $item = collect($items)->firstWhere('Tipe_Item', 'FORM');

        $this->assertNotNull($item);
        $this->assertCount(1, $item['Lampiran'] ?? []);
        $this->assertSame($this->h($f->lampiran), $item['Lampiran'][0]['Id_Lampiran']);
        $this->assertNotEmpty($item['Lampiran'][0]['Nama']);
    }

    public function test_lampiran_sah_mengembalikan_tautan_unduh(): void
    {
        $f = $this->fixture();

        $res = $this->actingAs($f->user)->getJson($this->url($f))->assertOk();

        $this->assertNotEmpty($res->json('result.url'));
    }

    public function test_lampiran_milik_versi_lain_ditolak(): void
    {
        $f = $this->fixture();
        $lain = DB::table('N_SOP_Item_Versi_Lampiran')->insertGetId([
            'Id_SOP_Item_Versi' => (int) app(SopDokumenService::class)
                ->tambahVersi($f->idDok, ['Ringkasan_Perubahan' => 'Draft lain'], 1),
            'Id_Master_Konten' => $f->konten, 'Urutan' => 0, 'Flag_Aktif' => 'Y', 'Created_At' => now(),
        ], 'Id_Lampiran');

        $this->actingAs($f->user)->getJson($this->url($f, lampiran: $lain))->assertNotFound()
            ->assertJsonPath('message', 'Form kosong tidak ditemukan.');
    }

    public function test_lampiran_yang_dilepas_ditolak(): void
    {
        $f = $this->fixture();
        DB::table('N_SOP_Item_Versi_Lampiran')->where('Id_Lampiran', $f->lampiran)->update(['Flag_Aktif' => 'T']);

        $this->actingAs($f->user)->getJson($this->url($f))->assertNotFound()
            ->assertJsonPath('message', 'Form kosong tidak ditemukan.');
    }

    public function test_versi_yang_sudah_digantikan_ditolak_410(): void
    {
        $f = $this->fixture();
        $baru = (int) app(SopDokumenService::class)->tambahVersi($f->idDok, ['Ringkasan_Perubahan' => 'Pengganti'], 1);
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $f->paket)->update(['Id_Item_Versi_Aktif' => $baru]);

        $this->actingAs($f->user)->getJson($this->url($f))->assertStatus(410);
    }

    /* ══ UNDUH SEMUA SEBAGAI ZIP ══════════════════════════════════════════
       Laporan user 6 Okt 2026: "Unduh Semua" membuka satu tab per berkas,
       browser memblokir sisanya, dan tak ada ZIP. Kini satu unduhan ZIP. */

    private function urlZip(object $f, ?int $versi = null): string
    {
        return '/api/v1/sop/'.$this->h($f->paket).'/versi/'.$this->h($versi ?? $f->versi).'/lampiran-zip';
    }

    /** GCS palsu berisi berkas kedua konten fixture, di jalur aslinya. */
    private function gcsPalsu(object $f): void
    {
        Storage::fake('gcs');
        foreach ([$f->konten, $f->kontenUtama] as $k) {
            $path = DB::table('N_LMS_Master_Konten_Berkas')->where('Id_Master_Konten', $k)->whereNotNull('File_Path')->value('File_Path');
            Storage::disk('gcs')->put($path, "isi berkas {$k}");
        }
    }

    public function test_unduh_semua_mengemas_seluruh_form_kosong_dalam_zip(): void
    {
        $f = $this->fixture();
        DB::table('N_SOP_Item_Versi_Lampiran')->insert([
            'Id_SOP_Item_Versi' => $f->versi, 'Id_Master_Konten' => $f->kontenUtama,
            'Urutan' => 1, 'Flag_Aktif' => 'Y', 'Created_At' => now(),
        ]);
        $this->gcsPalsu($f);

        $res = $this->actingAs($f->user)->get($this->urlZip($f))->assertOk();

        $this->assertStringContainsString('application/zip', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('.zip', (string) $res->headers->get('Content-Disposition'));

        $tmp = tempnam(sys_get_temp_dir(), 'zipuji');
        file_put_contents($tmp, $res->streamedContent());
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true, 'Isi unduhan bukan ZIP yang sah.');
        $this->assertSame(2, $zip->numFiles);
        $isi = [$zip->getFromIndex(0), $zip->getFromIndex(1)];
        $zip->close();
        unlink($tmp);
        $this->assertContains("isi berkas {$f->konten}", $isi);
        $this->assertContains("isi berkas {$f->kontenUtama}", $isi);
    }

    public function test_unduh_semua_tanpa_berkas_yang_tersedia_404(): void
    {
        $f = $this->fixture();
        Storage::fake('gcs');

        $this->actingAs($f->user)->getJson($this->urlZip($f))->assertNotFound()
            ->assertJsonPath('message', 'Belum ada form kosong yang bisa diunduh.');
    }

    public function test_unduh_semua_versi_yang_sudah_digantikan_410(): void
    {
        $f = $this->fixture();
        $baru = (int) app(SopDokumenService::class)->tambahVersi($f->idDok, ['Ringkasan_Perubahan' => 'Pengganti'], 1);
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $f->paket)->update(['Id_Item_Versi_Aktif' => $baru]);

        $this->actingAs($f->user)->getJson($this->urlZip($f))->assertStatus(410);
    }
}
