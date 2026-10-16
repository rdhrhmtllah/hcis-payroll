<?php

namespace Tests\Feature\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fase 6 Master Dokumen SOP — berbagi dokumen antar paket.
 *
 * Keputusan produk user 2026-08-10:
 *  1. Identitas (judul & nomor SOP) ikut dokumen sepenuhnya.
 *  2. Salin item ke paket lain = berbagi dokumen otomatis.
 *  3. Notifikasi revisi digabung per dokumen.
 */
class SopDokumenBerbagiTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Kelima invarian sop:dokumen-verify memeriksa BARIS VERSI. Item ber-tipe
     * versi yang belum punya satu pun baris versi karena itu tak terlihat oleh
     * satu pun di antaranya — dan backfill Fase 1 juga melewatkannya, karena ia
     * berkunci pada item YANG PUNYA versi.
     *
     * Selama identitas masih di item, itu tak berakibat apa-apa. Begitu Fase 6
     * memindahkan identitas ke dokumen, item semacam itu KEHILANGAN JUDULNYA.
     * Di data dev ada dua: KUIS #28 dan #98, keduanya tanpa baris versi.
     */
    public function test_setiap_item_ber_tipe_versi_punya_dokumen(): void
    {
        $yatim = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNull('Id_SOP_Dokumen')
            ->get(['Id_SOP_Paket_Item', 'Tipe_Item', 'Judul_Item']);

        $daftar = $yatim->map(fn ($i) => "#{$i->Id_SOP_Paket_Item} {$i->Tipe_Item} '{$i->Judul_Item}'")
            ->implode(', ');

        $this->assertCount(0, $yatim,
            "Ada {$yatim->count()} item ber-tipe versi tanpa dokumen ({$daftar}). Kelima "
            .'invarian lama memeriksa BARIS VERSI, jadi item tanpa versi tak terlihat oleh '
            .'mereka — dan begitu identitas pindah ke dokumen, item itu kehilangan judulnya.');
    }

    /**
     * Gerbang verify wajib IKUT menangkapnya.
     *
     * Item yang dipakai di sini sengaja dibuat TANPA baris versi. Melepas
     * tautan item yang PUNYA versi akan memicu invarian lain ("baris versi tak
     * ikut dokumen itemnya") sehingga verify merah karena alasan yang berbeda —
     * dan celah sebenarnya tetap tak teruji.
     */
    public function test_verify_menangkap_item_ber_tipe_versi_tanpa_versi_dan_tanpa_dokumen(): void
    {
        $contoh = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first();
        if (! $contoh) {
            $this->markTestSkipped('Tak ada item ber-tipe versi di data dev.');
        }

        DB::table('N_SOP_Paket_Item')->insert([
            'Id_SOP_Paket' => $contoh->Id_SOP_Paket,
            'Tipe_Item' => SopTipeItem::KUIS,
            'Judul_Item' => 'KUIS TANPA VERSI TANPA DOKUMEN',
            'Id_SOP_Dokumen' => null,
            'Urutan' => 9998,
            'Flag_Wajib' => $contoh->Flag_Wajib,
            'Flag_Highlight' => $contoh->Flag_Highlight,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ]);

        $this->artisan('sop:dokumen-verify')->assertExitCode(1);
    }

    // ---------------------------------------------------------------------
    // Identitas ikut dokumen (keputusan produk #1)
    // ---------------------------------------------------------------------

    /**
     * Identitas disebarkan ke item, bukan dibaca lewat join di 10+ jalur baca.
     *
     * Prinsipnya sama dengan Id_Item_Versi_Aktif: pertahankan kolom di item
     * sebagai CERMINAN yang disinkronkan sistem, sehingga seluruh jalur baca
     * (monitoring, export, register, snapshot, kuis, analytics, people) berjalan
     * tanpa diubah satu baris pun. Menulis ulang 10+ kueri hanya untuk membaca
     * kolom yang sama adalah 10+ peluang regresi baru.
     */
    public function test_ubah_identitas_dokumen_menyebar_ke_semua_item_perujuk(): void
    {
        $item = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen di data dev.');
        }

        $paketLain = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $item->Id_SOP_Paket)
            ->value('Id_SOP_Paket');
        if (! $paketLain) {
            $this->markTestSkipped('Tak ada paket lain.');
        }

        $kedua = DB::table('N_SOP_Paket_Item')->insertGetId([
            'Id_SOP_Paket' => $paketLain,
            'Tipe_Item' => $item->Tipe_Item,
            'Judul_Item' => 'JUDUL LAMA',
            'Nomor_SOP_Resmi' => 'NO-LAMA',
            'Id_SOP_Dokumen' => $item->Id_SOP_Dokumen,
            'Urutan' => 9997,
            'Flag_Wajib' => $item->Flag_Wajib,
            'Flag_Highlight' => $item->Flag_Highlight,
            'Created_By' => 1,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket_Item');

        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $item->Id_SOP_Dokumen)
            ->update(['Judul' => 'JUDUL RESMI BARU', 'Nomor_SOP_Resmi' => 'NO-BARU']);

        app(\App\Services\LMS\SOP\SopDokumenService::class)
            ->sinkronIdentitasKeItem((int) $item->Id_SOP_Dokumen, 1);

        foreach ([(int) $item->Id_SOP_Paket_Item, (int) $kedua] as $id) {
            $r = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $id)
                ->first(['Judul_Item', 'Nomor_SOP_Resmi']);

            $this->assertSame('JUDUL RESMI BARU', $r->Judul_Item,
                'Satu dokumen = satu nama di mana pun. Item yang tertinggal dengan judul lama '
                .'menghasilkan dua nama untuk satu dokumen terkendali — penyakit yang justru '
                .'diobati proyek ini.');
            $this->assertSame('NO-BARU', $r->Nomor_SOP_Resmi);
        }
    }

    public function test_sinkron_identitas_tidak_menyentuh_item_dokumen_lain(): void
    {
        $dua = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->distinct()
            ->limit(2)->get(['Id_SOP_Paket_Item', 'Id_SOP_Dokumen', 'Judul_Item']);
        if ($dua->count() < 2 || $dua[0]->Id_SOP_Dokumen === $dua[1]->Id_SOP_Dokumen) {
            $this->markTestSkipped('Butuh dua item dari dokumen berbeda.');
        }

        $judulLain = $dua[1]->Judul_Item;

        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $dua[0]->Id_SOP_Dokumen)
            ->update(['Judul' => 'HANYA DOKUMEN INI']);
        app(\App\Services\LMS\SOP\SopDokumenService::class)
            ->sinkronIdentitasKeItem((int) $dua[0]->Id_SOP_Dokumen, 1);

        $this->assertSame($judulLain,
            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $dua[1]->Id_SOP_Paket_Item)->value('Judul_Item'),
            'Sinkronisasi wajib berhenti pada item yang merujuk dokumen ITU saja.');
    }

    // ---------------------------------------------------------------------
    // Salin ke paket lain = berbagi dokumen (keputusan produk #2)
    // ---------------------------------------------------------------------

    private function paketDraftLain(int $kecuali): ?object
    {
        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Status', SopStatus::PAKET_DRAFT)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_SOP_Paket', '!=', $kecuali)
            ->first(['Id_SOP_Paket']);
        if (! $paket) {
            return null;
        }

        $section = DB::table('N_SOP_Paket_Section')
            ->where('Id_SOP_Paket', $paket->Id_SOP_Paket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->value('Id_SOP_Section');
        if (! $section) {
            return null;
        }

        $paket->Id_SOP_Section = (int) $section;

        return $paket;
    }

    public function test_salin_ke_paket_lain_berbagi_dokumen_yang_sama(): void
    {
        $src = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->whereNotNull('Id_Item_Versi_Aktif')
            ->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item ber-dokumen dgn versi aktif.');
        }

        $tujuan = $this->paketDraftLain((int) $src->Id_SOP_Paket);
        if (! $tujuan) {
            $this->markTestSkipped('Tak ada paket DRAFT bersection.');
        }

        $versiSebelum = DB::table('N_SOP_Item_Versi')->count();

        $baru = app(\App\Services\LMS\SOP\SopItemService::class)->copyToPaket(
            (int) $src->Id_SOP_Paket_Item, (int) $tujuan->Id_SOP_Paket, $tujuan->Id_SOP_Section,
            1, app(\App\Services\LMS\SOP\SopItemVersionService::class),
        );
        $this->assertNotNull($baru);

        $item = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $baru)
            ->first(['Id_SOP_Dokumen', 'Id_Item_Versi_Aktif']);

        $this->assertSame((int) $src->Id_SOP_Dokumen, (int) $item->Id_SOP_Dokumen,
            'Keputusan produk #2: salin ke paket lain BERBAGI dokumen sumber, sehingga revisi '
            .'menyebar ke semua paket. Itulah tujuan awal proyek ini.');

        $this->assertSame($versiSebelum, DB::table('N_SOP_Item_Versi')->count(),
            'Tak boleh ada baris versi baru. Item salinan mewarisi riwayat versi dokumennya; '
            .'menulis Versi_Ke=1 pada dokumen yang sudah punya versi 1 ditolak unique index '
            .'terfilter 26B dengan error 2601.');
    }

    public function test_pointer_item_salinan_hanya_menunjuk_versi_published(): void
    {
        $src = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->whereNotNull('Id_Item_Versi_Aktif')
            ->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item ber-dokumen dgn versi aktif.');
        }

        $tujuan = $this->paketDraftLain((int) $src->Id_SOP_Paket);
        if (! $tujuan) {
            $this->markTestSkipped('Tak ada paket DRAFT bersection.');
        }

        $baru = app(\App\Services\LMS\SOP\SopItemService::class)->copyToPaket(
            (int) $src->Id_SOP_Paket_Item, (int) $tujuan->Id_SOP_Paket, $tujuan->Id_SOP_Section,
            1, app(\App\Services\LMS\SOP\SopItemVersionService::class),
        );

        $pointer = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $baru)->value('Id_Item_Versi_Aktif');
        if ($pointer === null) {
            $this->markTestSkipped('Dokumen sumber belum punya versi PUBLISHED.');
        }

        $this->assertSame(SopStatus::PAKET_PUBLISHED,
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $pointer)->value('Status'),
            'Pointer versi aktif hanya boleh menunjuk versi PUBLISHED — menunjuk DRAFT berarti '
            .'menyajikan versi yang belum sah ke karyawan.');
    }

    /**
     * "Duplikat" (di paket yang SAMA) sengaja TIDAK berbagi.
     *
     * Keputusan produk user menyebut "salin ke paket lain" secara spesifik.
     * Dua item berbagi dokumen di dalam SATU paket juga memunculkan pertanyaan
     * yang belum terjawab: rollup hanya menyimpan satu baris angka per
     * (paket, versi), sehingga angkanya hanya bisa diatribusikan ke salah satu
     * item (lihat SopMonitoringService::ITEM_PAKET_DOKUMEN). Sampai itu
     * diputuskan, Duplikat tetap membuat dokumen sendiri.
     */
    public function test_duplikat_di_paket_sama_tetap_membuat_dokumen_sendiri(): void
    {
        $src = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
        if (! $src) {
            $this->markTestSkipped('Tak ada item ber-dokumen.');
        }

        $baru = app(\App\Services\LMS\SOP\SopItemService::class)
            ->cloneById((int) $src->Id_SOP_Paket_Item, 1, app(\App\Services\LMS\SOP\SopItemVersionService::class));
        $this->assertNotNull($baru);

        $this->assertNotSame((int) $src->Id_SOP_Dokumen,
            (int) DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $baru)->value('Id_SOP_Dokumen'),
            'Duplikat di paket yang sama membuat dokumen sendiri — keputusan produk hanya '
            .'menyebut salin ke paket LAIN.');
    }

    // ---------------------------------------------------------------------
    // Pemilih Dokumen di Builder
    // ---------------------------------------------------------------------

    public function test_opsi_dokumen_melaporkan_jumlah_paket_pemakai(): void
    {
        $admin = \App\Models\ESS\Employee\Models\User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopBuilderPage'));
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage.');
        }

        $item = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen.');
        }

        $r = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/builder/dokumen/options')
            ->assertOk()
            /* Dulu 'dipotong': daftarnya berplafon 50 dan pemotongannya wajib
               dilaporkan. Sejak commit 19b8fc39 endpoint ini berpaginasi
               sungguhan, dan paginasi ITULAH pelaporannya — total_data menyebut
               seluruh populasi, jadi tak ada lagi yang terpotong diam-diam.
               Bentuk baru yang dijaga, bukan bendera yang sudah tak punya
               pekerjaan. */
            ->assertJsonStructure([
                'result' => ['rows', 'total', 'pagination' => ['current_page', 'per_page', 'total_page', 'total_data']],
            ])
            ->json('result.rows');

        $this->assertNotEmpty($r);
        foreach ($r as $d) {
            $this->assertArrayHasKey('Jumlah_Paket_Pemakai', $d,
                'Angka ini bukan hiasan: memilih dokumen yang sudah dipakai paket lain berarti '
                .'item ini ikut bergerak tiap kali dokumen itu direvisi. Admin harus melihatnya '
                .'SEBELUM memilih.');
            $this->assertFalse(ctype_digit((string) $d['Id_SOP_Dokumen']),
                'Id dokumen wajib ter-encode, bukan id DB mentah.');
        }
    }

    public function test_opsi_dokumen_bisa_dicari(): void
    {
        $admin = \App\Models\ESS\Employee\Models\User::whereNotNull('Id_Users')->get()
            ->first(fn ($u) => $u->memilikiJabatan('menuSopBuilderPage'));
        if (! $admin) {
            $this->markTestSkipped('Tak ada user dgn akses menuSopBuilderPage.');
        }

        $judul = DB::table('N_SOP_Dokumen')->where('Flag_Aktif', SopStatus::AKTIF)->value('Judul');
        if (! $judul) {
            $this->markTestSkipped('Tak ada dokumen.');
        }

        $potongan = mb_substr($judul, 0, 4);

        $rows = $this->actingAs($admin)
            ->getJson('/api/v1/sop/admin/builder/dokumen/options?q='.urlencode($potongan))
            ->assertOk()->json('result.rows');

        $this->assertNotEmpty($rows, "Pencarian '{$potongan}' harus menemukan dokumen yang judulnya memuatnya.");
    }

    /**
     * Pulang-pergi id dokumen antara daftar item Builder dan penyimpanan.
     *
     * Form Builder mengirim balik Id_SOP_Dokumen apa adanya, dan
     * terapkanDokumen() men-decode-nya sebagai Hashid. Kalau byPaket()
     * mengirimkan id DB mentah, SETIAP simpan item ber-dokumen akan dibalas
     * 422 — dan gejalanya muncul di jalur paling ramai di Builder.
     */
    public function test_id_dokumen_pada_daftar_item_builder_terenkode_dan_bisa_didecode(): void
    {
        $item = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen.');
        }

        $baris = collect(app(\App\Services\LMS\SOP\SopItemService::class)->byPaket((int) $item->Id_SOP_Paket))
            ->firstWhere('Id_SOP_Paket_Item', \Vinkla\Hashids\Facades\Hashids::connection('custom')
                ->encode($item->Id_SOP_Paket_Item));

        $this->assertNotNull($baris);
        $this->assertNotEmpty($baris->Id_SOP_Dokumen);
        $this->assertFalse(ctype_digit((string) $baris->Id_SOP_Dokumen),
            'Id_SOP_Dokumen wajib ter-encode seperti id lain di daftar item Builder.');

        $this->assertSame((int) $item->Id_SOP_Dokumen,
            (int) (\Vinkla\Hashids\Facades\Hashids::connection('custom')->decode($baris->Id_SOP_Dokumen)[0] ?? 0),
            'Id yang dikirim ke form wajib bisa di-decode kembali ke dokumen yang sama.');
    }

    // ---------------------------------------------------------------------
    // Notifikasi digabung per dokumen (keputusan produk #3)
    // ---------------------------------------------------------------------

    /**
     * Kontrak penahanan notifikasi. Fan-out dokumen menahan notifikasi
     * per-paket lalu mengirim SATU notifikasi gabungan; supaya itu mungkin,
     * reconcilePaket() wajib MENGEMBALIKAN kode karyawan yang dibuka ulang.
     * Tanpa itu, menahan notifikasi berarti karyawan tak diberi tahu sama
     * sekali bahwa kewajibannya dibuka ulang — jauh lebih buruk daripada spam.
     */
    public function test_reconcile_mengembalikan_kode_yang_dibuka_ulang(): void
    {
        $paket = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->value('pj.Id_SOP_Paket');
        if (! $paket) {
            $this->markTestSkipped('Tak ada paket berpenugasan.');
        }

        $stat = app(\App\Services\LMS\SOP\SopReconciliationService::class)
            ->reconcilePaket((int) $paket, 'MANUAL', 1, tanpaNotifikasi: true);

        $this->assertArrayHasKey('reopened_kodes', $stat,
            'Pemanggil yang menahan notifikasi butuh daftar ini untuk mengirim notifikasi '
            .'gabungannya. Menahan tanpa mengganti = karyawan tak diberi tahu sama sekali.');
        $this->assertIsArray($stat['reopened_kodes']);
        $this->assertSame(array_values(array_unique($stat['reopened_kodes'])), $stat['reopened_kodes'],
            'Daftar wajib unik — satu karyawan tak boleh dihitung dua kali dalam satu paket.');
    }

    public function test_fanout_melaporkan_berapa_karyawan_diberitahu(): void
    {
        $item = DB::table('N_SOP_Paket_Item')
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->first();
        if (! $item) {
            $this->markTestSkipped('Tak ada item ber-dokumen.');
        }

        $hasil = app(\App\Services\LMS\SOP\SopDokumenFanoutService::class)
            ->rekonsiliasi((int) $item->Id_SOP_Dokumen, 'MANUAL', 1);

        /* BENTUKNYA BERUBAH DI FASE 2, MAKSUDNYA TIDAK.
           Dulu fan-out mengerjakan sendiri lalu melaporkan 'sinkron'/'antri'/
           'diberitahu'. Sejak seluruh penyisiran pindah ke handler outbox, ia
           tak lagi tahu berapa orang yang akhirnya diberi tahu — angka itu baru
           ada setelah handler selesai, dan tempatnya di baris progres.

           Yang dijaga uji ini tetap sama persis: admin WAJIB punya bekas untuk
           menelusuri pekerjaan yang berjalan di latar. Bekas itu kini dua
           tautan — baris outbox yang durable, dan baris progres yang tampil di
           panel Aktivitas. Tanpa keduanya, pekerjaan yang tak berjalan menjadi
           tak terlihat, dan itulah kegagalan yang uji ini cegah. */
        foreach (['outbox_id', 'task_id', 'paket'] as $k) {
            $this->assertArrayHasKey($k, $hasil,
                "Fan-out wajib melaporkan '{$k}'. Pekerjaannya kini berjalan di latar, "
                .'dan tanpa bekas ini admin tak punya apa pun untuk menelusurinya '
                .'ketika ia tidak berjalan.');
        }

        $this->assertIsInt($hasil['paket']);
        $this->assertNotNull($hasil['outbox_id'],
            'Intent tak tertulis — pekerjaannya hilang begitu permintaan selesai.');
        $this->assertNotNull($hasil['task_id'],
            'Tak ada baris progres — panel Aktivitas tak akan menampilkan apa pun.');
    }
}
