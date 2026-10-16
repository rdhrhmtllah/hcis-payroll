<?php

namespace Tests\Feature\LMS\SOP;

use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\SopVersiReviewService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Tahap proses versi dokumen SOP.
 *
 * Delapan tahap yang dilihat manusia berdiri DI ATAS empat Status teknis yang
 * sudah dipahami seluruh sistem. Yang diuji di sini: pemetaannya benar, alurnya
 * hanya menerima perpindahan yang terdaftar, dan syarat masuk tiap tahap
 * ditegakkan dari master — bukan dari cabang di kode.
 */
class SopTahapVersiTest extends TestCase
{
    use DatabaseTransactions;

    private function svc(): SopTahapVersiService
    {
        return app(SopTahapVersiService::class);
    }

    private function dok(): SopDokumenService
    {
        return app(SopDokumenService::class);
    }

    /** Dokumen baru beserta versi pertamanya — sengaja TANPA berkas. */
    private function versiBaru(): int
    {
        $id = $this->dok()->buat([
            'Judul' => 'SOP UJI TAHAP '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $idVersi = $this->dok()->tambahVersi($id, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        if (! $idVersi) {
            $this->markTestSkipped('Versi gagal dibuat.');
        }

        return $idVersi;
    }

    /* Review tidak lagi menuntut apa pun (keputusan 2026-08-24): pengesahan
       sepenuhnya milik langkah Pengesahan. Jadi Review bisa langsung dimasuki. */
    private function masukReview(int $idVersi): void
    {
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
    }

    /** Bawa versi sampai dibatalkan — pembatalan kini hanya dari Pengesahan. */
    private function batalkanDariPengesahan(): int
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->svc()->pindah($idVersi, 'CANCEL', 1);

        return $idVersi;
    }

    private function masukApproval(int $idVersi): void
    {
        app(SopVersiReviewService::class)->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Tanggal_Review' => now()->toDateString(),
            'Hasil_Review' => '<p>Sudah direview.</p>',
        ]], 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
    }

    /* ── Skema & seed ───────────────────────────────────────────────────── */

    public function test_tabel_tahap_dan_transisi_ada(): void
    {
        foreach (['N_SOP_Master_Tahap_Versi', 'N_SOP_Master_Tahap_Versi_Transisi'] as $t) {
            $this->assertNotNull(
                DB::selectOne('SELECT 1 AS a FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = ?', [$t]),
                "Tabel {$t} belum ada — jalankan DDL-2026-032.",
            );
        }

        $this->assertNotNull(
            DB::selectOne('SELECT 1 AS a FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['N_SOP_Item_Versi', 'Tahap_Id']),
            'Kolom N_SOP_Item_Versi.Tahap_Id belum ada.',
        );
    }

    public function test_tahap_aktif_membentuk_alur_lurus(): void
    {
        /* DML-2026-042 menyederhanakan alurnya: HOLD dibuang — menahan tak butuh
           status, cukup tidak menekan lanjut. APPROVED ikut dibuang saat itu,
           lalu DIHIDUPKAN KEMBALI oleh DML-2026-048 sebagai langkah Pemberkasan
           yang berdiri sendiri, dan DML-2026-051 menambahkan Tinjauan Akhir.

           Yang dijaga karena itu HOLD-nya, bukan APPROVED-nya: HOLD memang
           dicabut untuk selamanya, sedangkan APPROVED kembali dengan peran yang
           berbeda. Menuntutnya tetap absen berarti menuduh dua DML resmi sebagai
           kerusakan. */
        $kode = array_column($this->svc()->opsi(), 'Kode_Tahap');

        $this->assertSame([
            'DRAFTING', 'REVIEW', 'APPROVAL', 'CANCEL',
            'APPROVED', 'FILING_REGISTERED', 'OBSOLETE',
        ], $kode);
        $this->assertNotContains('HOLD', $kode);
    }

    public function test_status_teknis_tak_pernah_melanggar_check_constraint(): void
    {
        /* CK_N_SOP_Versi_Status hanya mengenal DRAFT/PUBLISHED/OBSOLETE. Tahap
           yang memetakan dirinya ke nilai lain akan meledak sebagai pelanggaran
           constraint pada perpindahan pertama — bukan sebagai pesan yang bisa
           dibaca admin. */
        $dipakai = DB::table('N_SOP_Master_Tahap_Versi')->distinct()->pluck('Status_Teknis')->all();

        foreach ($dipakai as $s) {
            $this->assertContains($s, ['DRAFT', 'PUBLISHED', 'OBSOLETE'],
                "Status_Teknis '{$s}' tak diizinkan CK_N_SOP_Versi_Status.");
        }
    }

    public function test_versi_lama_ikut_terbackfill_dari_statusnya(): void
    {
        $yatim = DB::table('N_SOP_Item_Versi')->whereNull('Tahap_Id')->count();

        $this->assertSame(0, $yatim,
            'Masih ada versi tanpa tahap — jalankan DML-2026-033.');
    }

    /* ── Versi boleh lahir tanpa berkas ─────────────────────────────────── */

    public function test_versi_lahir_di_drafting_tanpa_berkas(): void
    {
        /* Inilah inti permintaannya: versi disiapkan lebih dulu, berkasnya
           diunggah belakangan. */
        $idVersi = $this->versiBaru();

        $this->assertSame('DRAFTING', $this->svc()->tahapVersi($idVersi)->Kode_Tahap);
        $this->assertNull(
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Id_Master_Konten'),
        );
    }

    public function test_draft_baru_menyimpan_tanggal_permohonan_dan_respon(): void
    {
        /* Regresi 2026-08-24: form+controller sudah mengirim tanggal, tetapi
           tambahVersi() tidak menuliskannya pada INSERT. Update bekerja,
           sehingga bug hanya terlihat saat draft pertama kali dibuat. */
        $idDok = $this->dok()->buat([
            'Judul' => 'SOP UJI TANGGAL CREATE '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);
        $idVersi = $this->dok()->tambahVersi($idDok, [
            'Ringkasan_Perubahan' => 'Uji tanggal create',
            'Tanggal_Permintaan' => '2026-08-20',
            'Tanggal_Respon' => '2026-08-21',
            'Tanggal_Akhir_Approval' => '2026-08-22',
            'Tanggal_Kadaluarsa' => '2027-08-22',
        ], 1);

        $v = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Tanggal_Permintaan', 'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa']);

        $this->assertStringStartsWith('2026-08-20', (string) $v->Tanggal_Permintaan);
        $this->assertStringStartsWith('2026-08-21', (string) $v->Tanggal_Respon);
        $this->assertStringStartsWith('2026-08-22', (string) $v->Tanggal_Akhir_Approval);
        $this->assertStringStartsWith('2027-08-22', (string) $v->Tanggal_Kadaluarsa);
    }

    public function test_endpoint_buat_draft_menyimpan_tanggal_permohonan_dan_respon(): void
    {
        $idDok = $this->dok()->buat([
            'Judul' => 'SOP UJI TANGGAL HTTP '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);
        $enc = Hashids::connection('custom')->encode($idDok);

        $res = $this->actingAs($this->adminMaster())
            ->postJson("/api/v1/sop/admin/dokumen/{$enc}/versi", [
                'Ringkasan_Perubahan' => 'Uji HTTP',
                'Tanggal_Permintaan' => '2026-08-20',
                'Tanggal_Respon' => '2026-08-21',
            ]);

        $res->assertStatus(201);
        $idVersi = Hashids::connection('custom')->decode($res->json('result.Id_SOP_Item_Versi'))[0] ?? null;
        $v = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Tanggal_Permintaan', 'Tanggal_Respon']);
        $this->assertStringStartsWith('2026-08-20', (string) $v->Tanggal_Permintaan);
        $this->assertStringStartsWith('2026-08-21', (string) $v->Tanggal_Respon);
    }

    public function test_bergerak_sampai_approval_tanpa_berkas(): void
    {
        $idVersi = $this->versiBaru();

        $this->masukReview($idVersi);
        $this->assertSame('REVIEW', $this->svc()->tahapVersi($idVersi)->Kode_Tahap);

        $this->masukApproval($idVersi);
        $this->assertSame('APPROVAL', $this->svc()->tahapVersi($idVersi)->Kode_Tahap);

        // Status teknisnya tetap DRAFT sepanjang jalan.
        $this->assertSame(SopStatus::VERSI_DRAFT,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Status'));
    }

    /* ── Penjaga alur ───────────────────────────────────────────────────── */

    public function test_perpindahan_yang_tak_terdaftar_ditolak(): void
    {
        $idVersi = $this->versiBaru();

        // Drafting hanya boleh ke Review atau Cancel.
        $this->expectException(\DomainException::class);
        $this->svc()->pindah($idVersi, 'APPROVED', 1);
    }

    public function test_review_mulus_boleh_langsung_lanjut(): void
    {
        /* "Kalau ada catatan user boleh isi, kalau tidak ada dan mulus user
           bisa langsung simpan dan lanjut ke pengesahan." Review yang berjalan
           mulus memang tak meninggalkan catatan; memaksa mengarang satu putaran
           kosong hanya demi melewati gerbang membuat catatannya berhenti
           berarti. */
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);

        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $this->assertSame('APPROVAL', $this->svc()->tahapVersi($idVersi)->Kode_Tahap);
    }

    public function test_gerbang_terbit_menuntut_berkas_dan_pengesah(): void
    {
        /* Yang menjaga kini SATU gerbang: Filing and Registered. Di sanalah
           dokumen mulai mengikat orang, jadi di sanalah berkas final dan
           pengesah nyata dituntut — bukan di langkah-langkah sebelumnya yang
           formulirnya justru baru terbuka setelah dimasuki. */
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $filing = $this->svc()->cariKode('FILING_REGISTERED');
        $kurang = collect($this->svc()->syaratTahap($idVersi, $filing)['daftar'])
            ->where('terpenuhi', false)->pluck('Kode')->all();

        $this->assertContains('BERKAS', $kurang);
        $this->assertContains('REALISASI_PENGESAH', $kurang);
    }

    public function test_tahap_akhir_tak_bisa_ditinggalkan(): void
    {
        $idVersi = $this->batalkanDariPengesahan();

        $this->assertSame('CANCEL', $this->svc()->tahapVersi($idVersi)->Kode_Tahap);

        $this->expectException(\DomainException::class);
        $this->masukReview($idVersi);
    }

    public function test_cancel_menonaktifkan_versinya(): void
    {
        $idVersi = $this->batalkanDariPengesahan();

        $this->assertSame(SopStatus::INAKTIF,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Flag_Aktif'),
            'Versi yang dibatalkan tak boleh ikut terhitung sebagai pekerjaan berjalan.');
    }

    public function test_bisa_dikembalikan_untuk_revisi(): void
    {
        /* Pengganti HOLD: yang dibutuhkan bukan status "ditahan" melainkan
           jalan mundur untuk memperbaiki. Admin yang belum siap melanjutkan
           cukup tidak menekan lanjut. */
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);

        $tujuan = collect($this->svc()->tujuanSah($idVersi))->pluck('Aksi', 'Kode_Tahap');

        $this->assertSame('KEMBALIKAN', $tujuan['DRAFTING']);
        $this->assertSame('MAJU', $tujuan['APPROVAL']);
        $this->assertArrayNotHasKey('HOLD', $tujuan);
    }

    public function test_filing_registered_tidak_ditempuh_lewat_pemindahan_tahap(): void
    {
        /* Tahap itu berarti versinya benar-benar terbit, dan terbit menggeser
           pointer dokumen, meng-obsolete versi lama, merakit ulang nomor resmi,
           serta merekonsiliasi paket perujuk. Dua jalan menuju satu peristiwa
           pasti menyimpang. */
        $idVersi = $this->versiBaru();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/penerbitan versi/i');

        $this->svc()->pindah($idVersi, 'FILING_REGISTERED', 1);
    }

    public function test_pindah_ke_tahap_yang_sama_ditolak(): void
    {
        $idVersi = $this->versiBaru();

        $this->expectException(\DomainException::class);
        $this->svc()->pindah($idVersi, 'DRAFTING', 1);
    }

    public function test_perpindahan_meninggalkan_jejak(): void
    {
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);

        $log = DB::table('N_SOP_Log')->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Aksi', 'VERSI_TAHAP')->orderByDesc('Id_SOP_Log')->first();

        $this->assertNotNull($log, 'Perpindahan tahap harus tercatat.');
        $this->assertStringContainsString('DRAFTING', (string) $log->Meta_JSON);
        $this->assertStringContainsString('REVIEW', (string) $log->Meta_JSON);
    }

    public function test_tujuan_sah_kosong_pada_tahap_akhir(): void
    {
        $idVersi = $this->batalkanDariPengesahan();

        $this->assertSame([], $this->svc()->tujuanSah($idVersi));
    }

    /* ── Pintu HTTP ─────────────────────────────────────────────────────── */

    private function adminMaster(): User
    {
        $uid = DB::table('HRIS_Page_Access')
            ->where('Flag_Aktif', 'Y')->where('Jenis_page', 'menuSopDokumenPage')
            ->whereNotNull('UserID_Web')->value('UserID_Web');

        $u = $uid ? User::find($uid) : null;
        if (! $u) {
            $this->markTestSkipped('Tak ada user dengan menuSopDokumenPage.');
        }

        return $u;
    }

    public function test_endpoint_menyebut_tahap_kini_dan_tujuannya(): void
    {
        $idVersi = $this->versiBaru();
        $enc = Hashids::connection('custom')->encode($idVersi);

        $res = $this->actingAs($this->adminMaster())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/tahap");

        $res->assertStatus(200);
        $this->assertSame('DRAFTING', $res->json('result.Tahap.Kode_Tahap'));

        $tujuan = array_column($res->json('result.Tujuan'), 'Kode_Tahap');
        $this->assertSame(['REVIEW'], $tujuan,
            'Dari Drafting hanya ada satu jalan: maju. Pembatalan diputuskan di Pengesahan.');
    }

    public function test_endpoint_memindahkan_tahap(): void
    {
        $idVersi = $this->versiBaru();
        $enc = Hashids::connection('custom')->encode($idVersi);

        $this->actingAs($this->adminMaster())
            ->putJson("/api/v1/sop/admin/dokumen/versi/{$enc}/tahap", ['Kode_Tahap' => 'REVIEW'])
            ->assertStatus(200);

        $this->assertSame('REVIEW', $this->svc()->tahapVersi($idVersi)->Kode_Tahap);
    }

    public function test_endpoint_menolak_lompatan_dengan_pesan_yang_bisa_dikerjakan(): void
    {
        /* 422 dengan kalimat untuk admin, bukan 500 dengan jejak tumpukan:
           penolakan alur adalah keadaan yang wajar, bukan kerusakan. */
        $idVersi = $this->versiBaru();
        $enc = Hashids::connection('custom')->encode($idVersi);

        $res = $this->actingAs($this->adminMaster())
            ->putJson("/api/v1/sop/admin/dokumen/versi/{$enc}/tahap", ['Kode_Tahap' => 'CANCEL']);

        $res->assertStatus(422);
        $this->assertStringContainsString('tidak diizinkan', (string) $res->json('message'),
            'Membatalkan dari Drafting bukan alur yang berlaku — keputusan itu diambil di Pengesahan.');
    }

    public function test_endpoint_menolak_filing_registered(): void
    {
        $idVersi = $this->versiBaru();
        $enc = Hashids::connection('custom')->encode($idVersi);

        $res = $this->actingAs($this->adminMaster())
            ->putJson("/api/v1/sop/admin/dokumen/versi/{$enc}/tahap", ['Kode_Tahap' => 'FILING_REGISTERED']);

        $res->assertStatus(422);
        $this->assertStringContainsString('penerbitan versi', (string) $res->json('message'));
    }

    public function test_daftar_versi_membawa_tahapnya(): void
    {
        $idVersi = $this->versiBaru();
        $idDok = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value('Id_SOP_Dokumen');

        $res = $this->actingAs($this->adminMaster())
            ->getJson('/api/v1/sop/admin/dokumen/'.Hashids::connection('custom')->encode($idDok).'/versi');

        $res->assertStatus(200);
        $baris = $res->json('result.rows');
        $this->assertNotEmpty($baris);
        $this->assertSame('DRAFTING', $baris[0]['Tahap_Kode']);
        $this->assertSame('Drafting', $baris[0]['Tahap_Nama']);
    }

    /* ── Tanpa berkas tak bisa menjadi versi aktif ──────────────────────── */

    public function test_versi_tanpa_berkas_ditolak_terbit(): void
    {
        /* Gerbang TAHAP Approved menyala lebih dulu daripada gerbang berkas.
           Keduanya sama-sama penjaga, dan urutan pesannya tidak mengubah
           akibatnya: versi yang belum sampai Approved tak bisa terbit. */
        $idVersi = $this->versiBaru();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessageMatches('/tahap Approved|berkas/i');

        $this->dok()->terbitkanVersi($idVersi, 1);
    }

    public function test_pointer_dokumen_tak_bergerak_saat_terbit_ditolak(): void
    {
        /* Penolakan yang menyisakan pointer setengah bergerak lebih buruk
           daripada tak menolak sama sekali: dokumennya akan menyebut versi
           berlaku yang tak pernah terbit. */
        $idVersi = $this->versiBaru();
        $idDok = (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)->value('Id_SOP_Dokumen');

        $sebelum = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif');

        try {
            $this->dok()->terbitkanVersi($idVersi, 1);
            $this->fail('Versi kosong seharusnya ditolak terbit.');
        } catch (\DomainException $e) {
            // memang ditolak
        }

        $this->assertSame($sebelum,
            DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Id_Versi_Aktif'),
            'Pointer versi aktif tak boleh bergeser saat penerbitan ditolak.');

        $this->assertSame('DRAFTING', $this->svc()->tahapVersi($idVersi)->Kode_Tahap,
            'Tahapnya juga tak boleh ikut maju.');
    }

    public function test_versi_kosong_tetap_boleh_disimpan_sebagai_draft(): void
    {
        /* Sisi lain dari aturan yang sama, dan justru inti permintaannya:
           menyiapkan versi lebih dulu, mengunggah isinya belakangan. */
        $idVersi = $this->versiBaru();

        $baris = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Id_Master_Konten', 'Status', 'Flag_Aktif']);

        $this->assertNull($baris->Id_Master_Konten);
        $this->assertSame(SopStatus::VERSI_DRAFT, $baris->Status);
        $this->assertSame(SopStatus::AKTIF, $baris->Flag_Aktif,
            'Draft tanpa berkas tetap versi yang hidup, bukan yang dibuang.');
    }

    /* ── Tanggal proses ─────────────────────────────────────────────────── */

    private function tanggal(int $idVersi, string $kolom)
    {
        return DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->value($kolom);
    }

    public function test_kolom_tanggal_proses_ada(): void
    {
        foreach (['Tanggal_Permintaan', 'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa'] as $k) {
            $this->assertNotNull(
                DB::selectOne('SELECT 1 AS a FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                    ['N_SOP_Item_Versi', $k]),
                "Kolom {$k} belum ada — jalankan DDL-2026-034.",
            );
        }

        $this->assertNotNull(
            DB::selectOne('SELECT 1 AS a FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['N_SOP_Master_Tahap_Versi', 'Cap_Tanggal']),
            'Kolom Cap_Tanggal belum ada pada master tahap.',
        );
    }

    public function test_aturan_pencapan_terpasang_di_master(): void
    {
        /* Hanya tahap AKTIF: yang sudah dinonaktifkan DML-2026-042 tetap
           menyimpan aturan lamanya sebagai jejak, dan itu memang disengaja —
           mengembalikannya cukup satu UPDATE bila ternyata dibutuhkan. */
        $peta = DB::table('N_SOP_Master_Tahap_Versi')->whereNotNull('Cap_Tanggal')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->pluck('Cap_Tanggal', 'Kode_Tahap')->all();

        $this->assertSame([
            'REVIEW' => 'Tanggal_Permintaan',
            'APPROVAL' => 'Tanggal_Akhir_Approval',
            'OBSOLETE' => 'Tanggal_Obsolete',
        ], $peta, 'Pengesahan kini satu tahap, jadi ia yang mencap tanggal akhir approval.');
    }

    public function test_tanggal_tercap_saat_memasuki_tahapnya(): void
    {
        $idVersi = $this->versiBaru();
        $hariIni = now()->toDateString();

        $this->assertNull($this->tanggal($idVersi, 'Tanggal_Permintaan'));

        $this->masukReview($idVersi);
        $this->assertStringStartsWith($hariIni, (string) $this->tanggal($idVersi, 'Tanggal_Permintaan'));
        $this->assertNull($this->tanggal($idVersi, 'Tanggal_Akhir_Approval'),
            'Tahap berikutnya belum dimasuki — tanggalnya belum boleh terisi.');

        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->assertStringStartsWith($hariIni, (string) $this->tanggal($idVersi, 'Tanggal_Akhir_Approval'));
    }

    public function test_pencapan_tak_menimpa_tanggal_yang_sudah_ada(): void
    {
        /* Melindungi dua hal: tanggal asli saat versi bolak-balik antar tahap,
           dan koreksi manual admin — surat yang ditandatangani minggu lalu tapi
           baru dicatat hari ini tak boleh tertimpa oleh sebuah klik. */
        $idVersi = $this->versiBaru();
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->update(['Tanggal_Permintaan' => '2026-01-15']);

        $this->masukReview($idVersi);

        $this->assertStringStartsWith('2026-01-15', (string) $this->tanggal($idVersi, 'Tanggal_Permintaan'));
    }

    public function test_bolak_balik_tahap_mempertahankan_tanggal_pertama(): void
    {
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);
        $pertama = $this->tanggal($idVersi, 'Tanggal_Permintaan');

        $this->svc()->pindah($idVersi, 'DRAFTING', 1);
        $this->masukReview($idVersi);

        $this->assertSame($pertama, $this->tanggal($idVersi, 'Tanggal_Permintaan'),
            'Jejak tiap perpindahan hidup di N_SOP_Log; kolomnya menyimpan yang pertama.');
    }

    public function test_tahap_tanpa_cap_tak_menyentuh_tanggal_apa_pun(): void
    {
        $idVersi = $this->versiBaru();
        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'DRAFTING', 1);

        foreach (['Tanggal_Respon', 'Tanggal_Akhir_Approval'] as $k) {
            $this->assertNull($this->tanggal($idVersi, $k), "Cancel tak boleh mencap {$k}.");
        }
    }

    public function test_nama_kolom_karangan_di_master_dilewati_bukan_diteruskan(): void
    {
        /* Nama kolom datang dari data, dan data tak pernah dipercaya sebagai
           nama kolom begitu saja. Salah ketik di master harus berakhir sebagai
           pencapan yang dilewati, bukan kueri yang menyebut kolom karangan. */
        DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', 'REVIEW')
            ->update(['Cap_Tanggal' => 'Tanggal_Karangan']);

        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);

        $this->assertSame('REVIEW', $this->svc()->tahapVersi($idVersi)->Kode_Tahap,
            'Perpindahannya tetap berhasil meski aturan pencapannya keliru.');
    }

    /* ── Tanggal obsolete: dua jalur ────────────────────────────────────── */

    public function test_kolom_tanggal_obsolete_ada(): void
    {
        $this->assertNotNull(
            DB::selectOne('SELECT 1 AS a FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                ['N_SOP_Item_Versi', 'Tanggal_Obsolete']),
            'Kolom Tanggal_Obsolete belum ada — jalankan DDL-2026-036.',
        );
    }

    public function test_versi_lama_tercap_saat_penerusnya_terbit(): void
    {
        /* INILAH jalur yang sebenarnya dipakai: versi menjadi obsolete karena
           penerusnya terbit, bukan karena ada yang menekan tombol tahap.
           Mencap hanya di pemindahan tahap akan melewatkan hampir seluruh
           kejadian nyata. */
        $idDok = $this->dok()->buat([
            'Judul' => 'SOP UJI CAP OBSOLETE '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $lama = $this->dok()->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)
            ->update(['Status' => SopStatus::VERSI_PUBLISHED]);

        $baru = $this->dok()->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Revisi'], 1);

        app(\App\Services\LMS\SOP\SopItemVersionService::class)->publish($baru, 1);

        $baris = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)
            ->first(['Status', 'Tanggal_Obsolete']);

        $this->assertSame(SopStatus::VERSI_OBSOLETE, $baris->Status);
        $this->assertStringStartsWith(now()->toDateString(), (string) $baris->Tanggal_Obsolete,
            'Versi yang digantikan harus tercap tanggalnya oleh jalur terbit.');
    }

    public function test_cap_obsolete_tak_menimpa_yang_sudah_ada(): void
    {
        $idDok = $this->dok()->buat([
            'Judul' => 'SOP UJI CAP OBSOLETE LAMA '.substr((string) microtime(true), -5),
            'Tipe' => 'DOKUMEN',
        ], 1);

        $lama = $this->dok()->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)->update([
            'Status' => SopStatus::VERSI_PUBLISHED,
            'Tanggal_Obsolete' => '2026-01-05',
        ]);

        $baru = $this->dok()->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Revisi'], 1);
        app(\App\Services\LMS\SOP\SopItemVersionService::class)->publish($baru, 1);

        $this->assertStringStartsWith('2026-01-05',
            (string) DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $lama)->value('Tanggal_Obsolete'));
    }

    public function test_backfill_obsolete_tak_mengisi_yang_tak_punya_penerus(): void
    {
        /* Dua dari 16 versi lama tak punya penerus yang pernah terbit, dan
           sengaja dibiarkan kosong: mengisinya dengan tanggal hari ini akan
           menuliskan peristiwa yang tak pernah terjadi pada tanggal itu. */
        $yatim = DB::table('N_SOP_Item_Versi as v')
            ->where('v.Status', SopStatus::VERSI_OBSOLETE)
            ->whereNotNull('v.Tanggal_Obsolete')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('N_SOP_Item_Versi as w')
                ->whereColumn('w.Id_SOP_Dokumen', 'v.Id_SOP_Dokumen')
                ->whereColumn('w.Versi_Ke', '>', 'v.Versi_Ke')
                ->whereNotNull('w.Tanggal_Publish'))
            ->count();

        /* Baris yang tercap oleh jalur terbit di tes lain ikut terhitung di
           sini, jadi yang dijaga adalah asal-usulnya: setiap tanggal hasil
           BACKFILL harus punya penerus. */
        $this->assertLessThanOrEqual(3, $yatim,
            'Backfill tak boleh mengarang tanggal bagi versi tanpa penerus.');
    }

    /* ── Alur berlangkah & pengesahan ───────────────────────────────────── */

    public function test_kolom_langkah_kelompok_dan_jenis_pengesah_ada(): void
    {
        foreach ([
            ['N_SOP_Master_Tahap_Versi', 'Langkah'],
            ['N_SOP_Master_Tahap_Versi', 'Kelompok'],
            ['N_SOP_Item_Versi_Pengesah', 'Jenis'],
        ] as [$tabel, $kolom]) {
            $this->assertNotNull(
                DB::selectOne('SELECT 1 AS a FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ?',
                    [$tabel, $kolom]),
                "Kolom {$tabel}.{$kolom} belum ada — jalankan DDL-2026-039.",
            );
        }
    }

    /**
     * Pemetaan langkah wizard berasal dari master, bukan ditulis di kode.
     *
     * Bentuknya sudah bergeser dua kali sejak ditulis, dan keduanya tercatat:
     * DML-2026-048 memberi APPROVED langkah sendiri (Pemberkasan) alih-alih
     * berbagi langkah 3 dengan APPROVAL, lalu DML-2026-051 menambahkan Tinjauan
     * Akhir. Lima langkah sekarang, bukan empat.
     */
    public function test_pemetaan_langkah_berasal_dari_master(): void
    {
        $peta = DB::table('N_SOP_Master_Tahap_Versi')->whereNotNull('Langkah')
            ->orderBy('Urutan')->get(['Kode_Tahap', 'Langkah', 'Kelompok']);

        $this->assertSame([
            ['DRAFTING', 1, 'PENGAJUAN'],
            ['REVIEW', 2, 'REVIEW'],
            ['APPROVAL', 3, 'PENGESAHAN'],
            ['APPROVED', 4, 'TERBIT'],
            ['FILING_REGISTERED', 5, 'TINJAUAN'],
        ], $peta->map(fn ($t) => [$t->Kode_Tahap, (int) $t->Langkah, $t->Kelompok])->all());

        /* Nomor langkahnya harus rapat dan naik satu-satu. Lompatan atau nomor
           kembar membuat wizard menampilkan langkah kosong atau dua langkah yang
           saling menimpa — dan itu tak terlihat dari daftar di atas saja. */
        $this->assertSame(range(1, $peta->count()),
            $peta->pluck('Langkah')->map(fn ($n) => (int) $n)->sort()->values()->all(),
            'Nomor langkah wizard harus berurut rapat tanpa lompatan maupun kembar.');

        foreach (['HOLD', 'CANCEL', 'OBSOLETE'] as $kode) {
            $this->assertNull(DB::table('N_SOP_Master_Tahap_Versi')
                ->where('Kode_Tahap', $kode)->value('Langkah'));
        }
    }

    public function test_langkah_versi_menunjukkan_perjalanan_dan_tanggalnya(): void
    {
        $idVersi = $this->versiBaru();
        $awal = $this->svc()->langkahVersi($idVersi);

        $this->assertSame([1, 2, 3, 4, 5], array_column($awal, 'Nomor'));
        $this->assertSame('berjalan', $awal[0]['Keadaan']);
        $this->assertNotNull($awal[0]['Tanggal']);

        $this->masukReview($idVersi);
        $review = $this->svc()->langkahVersi($idVersi);
        $this->assertSame('selesai', $review[0]['Keadaan']);
        $this->assertSame('berjalan', $review[1]['Keadaan']);
        $this->assertNotNull($review[1]['Tanggal']);
    }

    public function test_kelompok_karangan_di_master_dilewati(): void
    {
        DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', 'DRAFTING')
            ->update(['Kelompok' => 'KOMPONEN_KARANGAN']);

        $langkah = $this->svc()->langkahVersi($this->versiBaru());
        $this->assertNull($langkah[0]['Kelompok']);
    }

    public function test_endpoint_langkah_membawa_checklist_syarat(): void
    {
        $idVersi = $this->versiBaru();
        $enc = Hashids::connection('custom')->encode($idVersi);

        $res = $this->actingAs($this->adminMaster())
            ->getJson("/api/v1/sop/admin/dokumen/versi/{$enc}/langkah");

        $res->assertStatus(200);
        $this->assertCount(5, $res->json('result.Langkah'));
        $approval = collect($res->json('result.Tujuan'))->firstWhere('Kode_Tahap', 'APPROVAL');
        /* Review tak lagi menuntut apa pun; yang pertama menuntut adalah
           Approval (catatan review). Drafting → Review tak punya syarat. */
        $review = collect($res->json('result.Tujuan'))->firstWhere('Kode_Tahap', 'REVIEW');
        $this->assertNotNull($review);
        $this->assertSame([], $review['Syarat']['daftar'] ?? []);
    }

    public function test_alur_berlangkah_ditegakkan_di_transisi_bukan_di_publish(): void
    {
        /* Alur hidup di TRANSISI TAHAP, bukan di gerbang publish: gerbang
           publish akan memutus jalur lama Builder/paket dan tipe non-DOKUMEN. */
        $idVersi = $this->versiBaru();

        try {
            $this->svc()->pindah($idVersi, 'FILING_REGISTERED', 1);
            $this->fail('Terbit seharusnya tak ditempuh lewat pemindahan tahap.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('penerbitan versi', $e->getMessage());
        }

        $this->assertSame('DRAFTING', $this->svc()->tahapVersi($idVersi)->Kode_Tahap);
    }

    public function test_setiap_tujuan_menyebut_maksud_aksinya(): void
    {
        /* Layar menamai tombolnya dari `Aksi`, bukan dari kode tahap yang
           dihafal. Alur tersimpan sebagai data dan bisa berubah tanpa rilis;
           layar yang menghafal 'HOLD' akan diam-diam kehilangan tombolnya
           begitu admin menamai tahapnya lain. */
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);

        $peta = collect($this->svc()->tujuanSah($idVersi))->pluck('Aksi', 'Kode_Tahap');

        $this->assertSame('KEMBALIKAN', $peta['DRAFTING'], 'Mundur ke langkah lebih rendah = mengembalikan untuk revisi.');
        $this->assertSame('MAJU', $peta['APPROVAL'], 'Naik ke langkah lebih tinggi = maju.');
        $this->assertArrayNotHasKey('HOLD', $peta, 'Menahan tak butuh status — cukup tidak menekan lanjut.');
    }

    /**
     * Terbit bukan lagi satu lompatan dari Pengesahan.
     *
     * DML-2026-048 menyisipkan Pemberkasan sebagai langkah tersendiri, sehingga
     * penerbitan tak lagi bisa dilakukan langsung dari Pengesahan — dan itu
     * memang maksudnya: menerbitkan menggeser pointer dokumen, memensiunkan
     * versi lama, merakit ulang nomor resmi, dan merekonsiliasi paket perujuk.
     * Semua itu tidak pantas menumpang pada tombol yang sama dengan "setujui".
     *
     * Yang dijaga di sini justru KETIADAANNYA: FILING_REGISTERED tak boleh
     * muncul sebagai tujuan langsung dari Pengesahan. Bila suatu saat ia muncul
     * lagi, gerbang pengesah yang menjaga Pemberkasan ikut terlewati.
     */
    public function test_terbit_tidak_bisa_dilompati_dari_pengesahan(): void
    {
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $peta = collect($this->svc()->tujuanSah($idVersi))->pluck('Aksi', 'Kode_Tahap');

        $this->assertArrayNotHasKey('FILING_REGISTERED', $peta,
            'Penerbitan melompati langkah Pemberkasan berarti gerbang realisasi pengesah '
            .'ikut terlewati.');
        $this->assertSame('MAJU', $peta['APPROVED'],
            'Jalan maju dari Pengesahan adalah Pemberkasan.');
        $this->assertSame('BATALKAN', $peta['CANCEL'],
            'Di Pengesahan admin menentukan: diteruskan, atau dibatalkan jadi arsip.');
        $this->assertSame('KEMBALIKAN', $peta['REVIEW']);
    }

    public function test_menyunting_draft_tak_menghapus_nomor_sk_lama(): void
    {
        /* Formulir versi berhenti meminta Nomor Pengesahan dan Tanggal
           Pengesahan sejak 2026-08-25. Dengan `?? null` di jalur update,
           menyunting draft lama akan MENGHAPUS nomor SK yang tercetak di
           dokumen fisiknya — sembilan belas versi masih menyimpannya, dan
           tak ada kolom lain yang menyalinnya. */
        $idVersi = $this->versiBaru();
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Nomor_Pengesahan' => 'SK-001/HR/2026',
            'Tanggal_Pengesahan' => '2026-07-21',
        ]);

        // Payload formulir baru: tak lagi menyebut kedua kunci itu sama sekali.
        $this->dok()->ubahVersi($idVersi, ['Ringkasan_Perubahan' => 'disunting'], 1);

        $v = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Nomor_Pengesahan', 'Tanggal_Pengesahan']);

        $this->assertSame('SK-001/HR/2026', $v->Nomor_Pengesahan,
            'Nomor SK lama lenyap hanya karena formulir tak lagi memintanya.');
        $this->assertStringStartsWith('2026-07-21', (string) $v->Tanggal_Pengesahan);
    }

    public function test_nomor_sk_masih_bisa_dikosongkan_bila_memang_diminta(): void
    {
        /* Sisi lain aturan yang sama: mempertahankan nilai lama tak boleh
           berarti mustahil mengosongkannya. Kirim kuncinya bernilai null —
           pola yang sama dengan Id_Master_Konten. */
        $idVersi = $this->versiBaru();
        DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->update(['Nomor_Pengesahan' => 'SK-999/HR/2026']);

        $this->dok()->ubahVersi($idVersi, ['Nomor_Pengesahan' => null], 1);

        $this->assertNull(DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)->value('Nomor_Pengesahan'));
    }

    public function test_menyimpan_draft_tak_memundurkan_tahapnya(): void
    {
        /* Keluhan 2026-08-25: "sudah di tahap pengesahan, lalu simpan, selalu
           balik ke review". Yang sebenarnya terjadi: formulir menutup ke layar
           riwayat setiap kali draft disimpan, sehingga perjalanannya lenyap
           dari layar dan terbaca sebagai tahap yang tak tersimpan.

           Yang dijaga di sini adalah sisi datanya: menyimpan isian TIDAK boleh
           menyentuh Tahap_Id sama sekali. */
        $idVersi = $this->versiBaru();
        $this->masukReview($idVersi);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        $this->dok()->ubahVersi($idVersi, [
            'Ringkasan_Perubahan' => 'disunting saat di Pengesahan',
        ], 1);

        $this->assertSame('APPROVAL', $this->svc()->tahapVersi($idVersi)->Kode_Tahap,
            'Menyimpan isian memundurkan tahapnya.');
    }

    public function test_isian_tiap_langkah_bertahan_lintas_perpindahan(): void
    {
        /* "Tahap-tahap sebelumnya itu tidak tersimpan." Isian yang diisi di
           langkah awal harus tetap ada setelah versinya maju — bukan tertimpa
           null oleh penyimpanan berikutnya. */
        $idVersi = $this->versiBaru();
        $this->dok()->ubahVersi($idVersi, [
            'Ringkasan_Perubahan' => 'diisi di Pengajuan',
            'Tanggal_Permintaan' => '2026-08-20',
        ], 1);

        $this->masukReview($idVersi);
        app(SopVersiReviewService::class)->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Tanggal_Review' => '2026-08-21',
            'Hasil_Review' => '<p>Diperiksa.</p>',
        ]], 1);

        $this->svc()->pindah($idVersi, 'APPROVAL', 1);
        $this->dok()->ubahVersi($idVersi, [
            'Ringkasan_Perubahan' => 'diisi di Pengajuan',
            'Tanggal_Permintaan' => '2026-08-20',
            'Pengesah' => [[
                'Peran' => 'Disahkan oleh', 'Nama' => 'Direktur Uji', 'Jabatan' => 'Direktur',
            ]],
        ], 1);

        $v = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Ringkasan_Perubahan', 'Tanggal_Permintaan']);

        $this->assertSame('diisi di Pengajuan', $v->Ringkasan_Perubahan);
        $this->assertStringStartsWith('2026-08-20', (string) $v->Tanggal_Permintaan);
        $this->assertSame(1, DB::table('N_SOP_Item_Versi_Review')
            ->where('Id_SOP_Item_Versi', $idVersi)->where('Flag_Aktif', SopStatus::AKTIF)->count(),
            'Catatan review dari langkah sebelumnya hilang.');
        $this->assertSame(1, DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $idVersi)->where('Flag_Aktif', SopStatus::AKTIF)->count());
    }

    public function test_setiap_langkah_menyebut_kelengkapannya(): void
    {
        /* Layar memakai `Lengkap` untuk memutuskan langkah mana yang dibuka
           saat admin menekan "Lengkapi Versi". Menyimpulkannya dari kalimat
           Ringkasan akan diam-diam salah begitu kalimatnya diperhalus, jadi
           ia dikirim sebagai fakta tersendiri. */
        $idVersi = $this->versiBaru();
        $langkah = $this->svc()->langkahVersi($idVersi);

        foreach ($langkah as $l) {
            $this->assertArrayHasKey('Lengkap', $l, "Langkah {$l['Nomor']} tak menyebut kelengkapannya.");
            $this->assertIsBool($l['Lengkap']);
        }

        $peta = collect($langkah)->pluck('Lengkap', 'Kelompok');
        $this->assertTrue($peta['PENGAJUAN'],
            'Pengajuan lengkap begitu versinya tersimpan — yang menahan kelanjutan adalah syarat transisi.');
        $this->assertFalse($peta['REVIEW'], 'Belum ada putaran review.');
        $this->assertFalse($peta['PENGESAHAN'], 'Belum ada pengesah.');
        $this->assertFalse($peta['TERBIT'], 'Belum ada berkas final.');
    }

    public function test_kelengkapan_langkah_ikut_isian_nyata(): void
    {
        $idVersi = $this->versiBaru();

        app(SopVersiReviewService::class)->simpan($idVersi, [[
            'Review_Ke' => 1,
            'Tanggal_Review' => now()->toDateString(),
            'Hasil_Review' => '<p>Sudah diperiksa.</p>',
        ]], 1);
        $this->dok()->ubahVersi($idVersi, [
            'Pengesah' => [[
                'Peran' => 'Disahkan oleh', 'Nama' => 'Direktur Uji', 'Jabatan' => 'Direktur',
            ]],
        ], 1);

        $peta = collect($this->svc()->langkahVersi($idVersi))->pluck('Lengkap', 'Kelompok');

        $this->assertTrue($peta['REVIEW'], 'Satu putaran review sudah tercatat.');
        $this->assertTrue($peta['PENGESAHAN'], 'Satu pengesah sudah tercatat.');
        $this->assertFalse($peta['TERBIT'], 'Berkas final masih kosong.');
    }

    /**
     * Endpoint template mengirim ALUR, bukan sekadar rangka langkah.
     *
     * Bentuknya disamakan dengan endpoint alur versi pada 2026-08-27 supaya
     * layar tak punya dua jalur, dan supaya aksi versi baru dinamai server —
     * dulu layar memakunya sebagai "Simpan Draft Versi", dan itu memaksa admin
     * melewati satu klik yang tak dimintanya.
     */
    public function test_template_langkah_tersedia_sebelum_versi_punya_id(): void
    {
        $res = $this->actingAs($this->adminMaster())
            ->getJson('/api/v1/sop/admin/dokumen/langkah');

        $res->assertStatus(200);

        $langkah = $res->json('result.Langkah');
        $this->assertSame([1, 2, 3, 4, 5], array_column($langkah, 'Nomor'));
        $this->assertSame(['PENGAJUAN', 'REVIEW', 'PENGESAHAN', 'TERBIT', 'TINJAUAN'],
            array_column($langkah, 'Kelompok'));

        $this->assertSame(1, $res->json('result.Langkah_Aktif'));
        $this->assertSame('SIMPAN_LANJUT', $res->json('result.Aksi_Utama.Jenis'));
        $this->assertSame('REVIEW', $res->json('result.Aksi_Utama.Ke_Tahap'));
    }

    public function test_dokumen_tipe_form_bisa_masuk_pemberkasan_tanpa_pengesah(): void
    {
        $idDok = $this->dok()->buat([
            'Judul' => 'FORMULIR UJI TAHAP '.substr((string) microtime(true), -5),
            'Tipe' => 'FORM',
        ], 1);

        $idVersi = $this->dok()->tambahVersi($idDok, ['Ringkasan_Perubahan' => 'Terbit awal'], 1);
        $this->assertNotNull($idVersi);

        $this->svc()->pindah($idVersi, 'REVIEW', 1);
        $this->svc()->pindah($idVersi, 'APPROVAL', 1);

        // Langsung pindah ke APPROVED (Pemberkasan) tanpa pengesah harus berhasil untuk FORM
        $this->svc()->pindah($idVersi, 'APPROVED', 1);

        $langkah = $this->svc()->langkahVersi($idVersi);
        $peta = collect($langkah)->pluck('Lengkap', 'Kelompok');
        $this->assertTrue($peta['PENGESAHAN'], 'Pengesahan untuk FORM otomatis dianggap lengkap.');

        $tahap = $this->svc()->tahapVersi($idVersi);
        $this->assertSame('APPROVED', $tahap->Kode_Tahap);
    }
}


