<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopNomorVersi;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service Versi Item SOP (Builder — level versi).
 * Versi hanya untuk item ber-file (DOKUMEN/FORM/VIDEO). Publish versi:
 * set PUBLISHED + obsolete versi lama + geser pointer Id_Item_Versi_Aktif.
 *
 * CATATAN: seed progress / reset kepatuhan saat versi baru publish adalah
 * pekerjaan ProcessSopPublish (reconciliation) — di luar service ini.
 */
class SopItemVersionService
{
    private string $table = 'N_SOP_Item_Versi';

    /**
     * Semua versi satu item (terbaru dulu) + nama konten library. Id encoded.
     *
     * @param  bool  $hanyaAktif  Kembalikan HANYA versi yang berlaku.
     *                            Panel Builder memakainya saat lahir: yang ingin
     *                            dilihat orang begitu editor item dibuka adalah
     *                            "versi mana yang sedang berlaku", bukan seluruh
     *                            riwayatnya. Riwayat lengkap — beserta blok
     *                            pengesah tiap versinya — baru diambil kalau
     *                            accordionnya dibuka.
     */
    public function byItem(int $idItem, bool $hanyaAktif = false)
    {
        $idDokumen = app(SopDokumenService::class)->forItem($idItem);

        $rows = DB::table("{$this->table} as v")
            ->when($hanyaAktif, fn ($q) => $q->where('v.Status', SopStatus::PAKET_PUBLISHED))
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            /* Pembuat & pengubah terakhir. Created_By/Updated_By sudah ikut turun
               sejak dulu lewat v.*, tapi sebagai ANGKA Id_Users — yang tak
               memberi tahu siapa pun apa pun. Nama diambil dari Karyawan bila
               akunnya tertaut, dengan Username sebagai jaring: akun layanan dan
               akun lama tak punya baris Karyawan, dan baris jejak yang kosong
               namanya lebih buruk daripada baris yang menyebut usernamenya. */
            ->leftJoin('KPI_Users as ub', 'ub.Id_Users', '=', 'v.Created_By')
            ->leftJoin('Karyawan as kb', 'kb.UserID_Web', '=', 'ub.Id_Users')
            ->leftJoin('KPI_Users as uu', 'uu.Id_Users', '=', 'v.Updated_By')
            ->leftJoin('Karyawan as ku', 'ku.UserID_Web', '=', 'uu.Id_Users')
            /* Item ber-dokumen dibaca lewat DOKUMENnya; item tanpa dokumen
               (versi yatim milik item yang tipenya diubah menjauh dari tipe
               ber-versi — 18 baris di dev) tetap lewat itemnya. Memaksa mereka
               lewat dokumen akan menyembunyikan baris yang masih ada. */
            ->when($idDokumen !== null,
                fn ($q) => $q->where('v.Id_SOP_Dokumen', $idDokumen),
                fn ($q) => $q->where('v.Id_SOP_Paket_Item', $idItem))
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('v.Versi_Ke')
            ->select('v.*', 'k.Judul_Konten as Konten_Nama', 'k.Tipe_Konten as Konten_Tipe',
                DB::raw('COALESCE(kb.Nama, ub.Username) as Dibuat_Oleh_Nama'),
                DB::raw('COALESCE(ku.Nama, uu.Username) as Diubah_Oleh_Nama'))
            ->get();

        /* BLOK PENANDA TANGAN IKUT DIBAWA.
           Tanpa ini panel versi Builder cuma punya kolom cermin Disahkan_Oleh —
           yang isinya baris PERTAMA blok — sehingga versi yang disahkan tiga
           orang tampil seolah disahkan satu orang, dan PERANNYA hilang sama
           sekali: "Disetujui oleh" terbaca sebagai "Disahkan". Master Dokumen
           sudah membaca bloknya sejak awal; yang tertinggal pembacanya di sini.

           blokPengesah() dipakai apa adanya, bukan disalin: di dalamnya ada
           aturan mundur yang membaca kolom tunggal sebagai blok satu baris,
           supaya versi lama tak perlu diisi mundur. */
        $blok = app(SopDokumenService::class)->blokPengesah(
            $rows->pluck('Id_SOP_Item_Versi')->map(fn ($x) => (int) $x)->all(),
            $rows,
        );

        // Jenis berkas + form kosong per versi — sebelum id di-hash di bawah.
        app(SopVersiLampiranService::class)->lengkapiIsi($rows);

        return $rows->map(function ($v) use ($blok) {
            $v->Pengesah = $blok[(int) $v->Id_SOP_Item_Versi] ?? [];
            $v->Id_SOP_Item_Versi = Hashids::connection('custom')->encode($v->Id_SOP_Item_Versi);

            return $v;
        });
    }

    /** Buat versi DRAFT baru (Versi_Ke = terakhir + 1). Return Id (int). */
    public function create(int $idItem, array $data, int $userId): int
    {
        // Dokumen dipastikan ADA lebih dulu: sejak Fase 2 sumbu penomoran versi
        // adalah dokumen, jadi item tanpa dokumen tak punya sumbu penomoran.
        $idDokumen = app(SopDokumenService::class)->ensureForItem($idItem, $userId);

        /* SUMBU PENOMORAN = DOKUMEN. Selama 1 dokumen = 1 item hasilnya identik
           dengan penomoran per item — itulah yang membuat perpindahan ini bisa
           dilakukan tanpa perubahan perilaku. Begitu Fase 6 menyalakan berbagi,
           inilah yang mencegah dua paket memproduksi "Versi 3" berbeda isi pada
           satu dokumen terkendali (dan ditolak unique index terfilter 26B).

           Item tanpa dokumen (tipe tak ber-versi) mundur ke sumbu lama — satu-
           satunya sumbu yang tersisa untuk mereka. */
        /* Versi PERTAMA bernomor 0 ("v00"), bukan 1: (MAX ?? -1) + 1.
           Dengan (MAX ?? 0) + 1, nomor 0 tak akan pernah terpakai sama sekali
           dan penomoran otomatis di sini menyimpang dari Master SOP. */
        /* Sumbu DOKUMEN memakai penulis tunggal SopNomorVersi: aturan "versi
           batal melepaskan nomornya" harus dijawab sama di setiap pintu, dan
           saringannya juga menjadi predikat indeks unik terfilter
           (DDL-2026-062). Sumbu ITEM warisan tetap apa adanya — penomorannya
           soal lain, dan tak berbagi indeks itu. */
        if ($idDokumen !== null) {
            $next = SopNomorVersi::berikutnya($idDokumen);
        } else {
            $maks = DB::table($this->table)->where('Id_SOP_Paket_Item', $idItem)->max('Versi_Ke');
            $next = $maks === null ? 0 : ((int) $maks) + 1;
        }

        return DB::table($this->table)->insertGetId([
            'Id_SOP_Paket_Item' => $idItem,
            'Id_SOP_Dokumen' => $idDokumen,
            'Versi_Ke' => $next,
            'Status' => SopStatus::PAKET_DRAFT, // 'DRAFT'
            /* Versi baru lahir di tahap paling awal — dan sengaja BOLEH tanpa
               berkas. Itulah maksud tahapan ini: menyiapkan versi lebih dulu,
               mengunggah isinya belakangan. */
            'Tahap_Id' => $this->idTahap('DRAFTING'),
            'Id_Master_Konten' => $data['Id_Master_Konten'] ?? null, // referensi library (item file)
            'Id_Master_Ujian' => $data['Id_Master_Ujian'] ?? null,  // referensi ujian HC Learn (item KUIS)
            'Nama_Ujian' => $data['Nama_Ujian'] ?? null,       // salinan nama, agar layar tak menampilkan token
            'Ringkasan_Perubahan' => $data['Ringkasan_Perubahan'] ?? null,
            'Tanggal_Berlaku' => $data['Tanggal_Berlaku'] ?? null,
            'Disahkan_Oleh' => $data['Disahkan_Oleh'] ?? null,
            'Jabatan_Pengesah' => $data['Jabatan_Pengesah'] ?? null,
            'Tanggal_Pengesahan' => $data['Tanggal_Pengesahan'] ?? null,
            'Nomor_Pengesahan' => $data['Nomor_Pengesahan'] ?? null,
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Item_Versi');
    }

    /**
     * Id tahap dari kodenya.
     *
     * Dibaca dari master, tidak dihafal: tahap adalah data, dan menuliskan
     * id-nya sebagai angka di kode akan menunjuk baris yang berbeda di setiap
     * environment. Null bila masternya belum di-seed — versi tetap terbit,
     * hanya tahapnya yang tak terisi, dan itu dibaca sebagai turunan Status.
     */
    private function idTahap(string $kode): ?int
    {
        $id = DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', $kode)->value('Id_Tahap');

        return $id === null ? null : (int) $id;
    }

    /* setReady() DIHAPUS, bukan sekadar tak dipakai.

       Ia menulis Status = 'READY' ke N_SOP_Item_Versi, dan kolom itu dijaga
       CK_N_SOP_Versi_Status yang hanya mengenal DRAFT, PUBLISHED, dan OBSOLETE —
       jadi setiap pemanggilannya berakhir sebagai pelanggaran constraint, bukan
       sebagai nilai balik false. Nol baris READY pernah ada di tabel ini.

       "Siap rilis" memang bukan keadaan tabel utama: ia hanya punya arti pada
       salinan kerja paket terbit, dan di sana disimpan oleh
       SopPaketDraftService::setVersionReady() ke N_SOP_Paket_Draft_Item_Versi,
       yang tidak dijaga constraint itu. Membiarkan metode ini berdiri
       mengundang pemanggil berikutnya menempuh jalan yang tak pernah bisa
       sampai. */

    /**
     * Publish versi: PUBLISHED + Tanggal_Publish + Publisher (auto dari Auth),
     * obsolete-kan versi PUBLISHED lama pada item yang sama, lalu geser pointer aktif.
     */
    public function publish(int $idVersi, int $userId): void
    {
        $versi = DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)->first();
        if (! $versi) {
            return;
        }

        /* FAN-OUT (Fase 3). Yang disentuh bukan lagi satu item, melainkan SEMUA
           item yang merujuk dokumen versi ini. Selama 1 dokumen = 1 item
           daftarnya berisi satu dan hasilnya identik dengan sebelum fase ini.

           Versi tanpa dokumen (versi yatim milik item yang tipenya diubah
           menjauh dari tipe ber-versi) mundur ke itemnya sendiri — satu-satunya
           sumbu yang tersisa untuk mereka. */
        $idDokumen = $versi->Id_SOP_Dokumen !== null ? (int) $versi->Id_SOP_Dokumen : null;
        $itemTersentuh = $idDokumen !== null
            ? app(SopDokumenFanoutService::class)->itemPerujuk($idDokumen)
            : [(int) $versi->Id_SOP_Paket_Item];

        DB::transaction(function () use ($versi, $idVersi, $userId, $idDokumen, $itemTersentuh) {
            /* Obsolete versi published lama — berkunci DOKUMEN. Satu dokumen
               terkendali hanya boleh punya SATU versi PUBLISHED; kalau
               kuncinya tetap item, dua paket bisa menunjuk revisi berbeda dan
               keduanya sama-sama tampak sah. */
            $lama = DB::table($this->table)
                ->where('Status', SopStatus::PAKET_PUBLISHED)
                ->where('Id_SOP_Item_Versi', '<>', $idVersi);
            $idDokumen !== null
                ? $lama->where('Id_SOP_Dokumen', $idDokumen)
                : $lama->where('Id_SOP_Paket_Item', $versi->Id_SOP_Paket_Item);
            /* Tahap ikut bergeser bersama Status. Membiarkannya tertinggal
               membuat layar menyebut "Filing and Registered" untuk versi yang
               sudah digantikan — dua sumber untuk satu keadaan. */
            /* Tanggal digantikan dicap DI SINI, bukan hanya di pemindahan
               tahap: inilah jalur yang sebenarnya dipakai — versi menjadi
               obsolete karena penerusnya terbit, bukan karena ada yang menekan
               tombol. Ditulis terpisah dengan whereNull supaya versi yang sudah
               punya tanggal tak tertimpa. */
            (clone $lama)->whereNull('Tanggal_Obsolete')
                ->update(['Tanggal_Obsolete' => now()->toDateString()]);

            $lama->update([
                'Status' => 'OBSOLETE',
                'Tahap_Id' => $this->idTahap('OBSOLETE'),
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            // publish versi ini
            DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)->update([
                'Status' => SopStatus::PAKET_PUBLISHED,
                'Tahap_Id' => $this->idTahap('FILING_REGISTERED'),
                'Tanggal_Publish' => now(),
                'Kode_Karyawan_Publisher' => Auth::user()?->karyawan?->Kode_Karyawan,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            // FAN-OUT POINTER: semua item perujuk, bukan hanya pemicunya
            DB::table('N_SOP_Paket_Item')
                ->whereIn('Id_SOP_Paket_Item', $itemTersentuh)
                ->update(['Id_Item_Versi_Aktif' => $idVersi, 'Updated_By' => $userId, 'Updated_At' => now()]);

            // PROTEKSI VERSI (item KUIS): nonaktifkan integrasi ujian versi LAMA item ini.
            // Efek: launch()/checkpoint hanya melayani versi aktif → tak ada yang bisa
            // MULAI/reuse token versi obsolete. Peserta yang SUDAH TERLANJUR mulai tetap
            // bisa menyelesaikan karena callback me-resolve token apa adanya (lihat
            // LmsApiGuard: lookup by Id_Ujian_Token, tanpa filter Flag_Aktif).
            // Ikut melebar ke semua item perujuk: kalau tidak, paket lain masih
            // bisa memulai ujian versi obsolete lewat integrasinya sendiri.
            DB::table('N_SOP_Penjadwalan_Integrasi')
                ->whereIn('Id_SOP_Paket_Item', $itemTersentuh)
                ->where('Id_SOP_Item_Versi', '<>', $idVersi)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_At' => now()]);

            $this->bawaPenyelesaian($idVersi, $itemTersentuh);
        });

        // RECONCILIATION: reseed progress versi baru + reopen kewajiban SELESAI + ledger DIBUKA_LAGI
        $idPaket = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $versi->Id_SOP_Paket_Item)
            ->value('Id_SOP_Paket');
        // Versi item yang dipublish di Builder menjadi bagian draft kerja paket.
        // Release paket + reconcile karyawan baru dilakukan saat admin klik Publish Paket.

        \App\Services\LMS\SOP\Support\SopLog::catat(
            'VERSI_PUBLISH',
            /* Pesan lama berbunyi "kewajiban di-reconcile" padahal publish()
               tak pernah memanggil rekonsiliasi — dibantah komentar dua baris
               di atasnya sendiri. Jejak audit adalah bukti kepatuhan; bukti
               yang menyatakan sesuatu terjadi padahal tidak lebih buruk
               daripada tak ada jejak sama sekali. */
            /* Sifat revisinya ikut disebut di peristiwa yang SAMA, bukan sebagai
               baris log kedua: mayor dan minor adalah keputusan tentang
               penerbitan ini, dan memisahkannya membuat riwayat memuat dua
               entri untuk satu tindakan. Nilainya sudah tertulis di baris versi
               sebelum publish() dipanggil, jadi yang dibaca di sini adalah
               pilihan admin yang sebenarnya. */
            'Versi item dipublikasikan sebagai revisi '
                .(($versi->Wajib_Baca_Ulang ?? 'Y') === 'T' ? 'MINOR' : 'MAYOR')
                .'. Pointer aktif digeser pada '.count($itemTersentuh)
                .' item perujuk dokumen. Rekonsiliasi kewajiban dijalankan terpisah saat Publish Paket.',
            $idPaket ? (int) $idPaket : null,
            (int) $versi->Id_SOP_Paket_Item,
            $idVersi,
            null,
            /* id_dokumen ikut supaya penerbitan muncul di riwayat DOKUMEN, bukan
               hanya di riwayat paket. Dokumen yang dipakai banyak paket akan
               kehilangan peristiwa terpentingnya kalau jejaknya cuma bersumbu
               paket. */
            [
                'oleh_id_users' => $userId,
                'id_dokumen' => $idDokumen,
                'sifat_revisi' => ($versi->Wajib_Baca_Ulang ?? 'Y') === 'T' ? 'MINOR' : 'MAYOR',
                'versi_ke' => $versi->Versi_Ke !== null ? (int) $versi->Versi_Ke : null,
            ],
        );
    }

    /**
     * REVISI MINOR: bawa penyelesaian karyawan dari versi sebelumnya.
     *
     * Penyelesaian dicatat per (item x versi). Begitu pointer pindah, baris
     * progres versi lama tak lagi cocok dan karyawan otomatis terhitung belum
     * selesai — benar untuk revisi mayor, tapi memaksa ratusan orang membaca
     * ulang karena satu kop surat berubah bukan cuma merepotkan: ia mengikis
     * arti "dibuka lagi" itu sendiri.
     *
     * Yang dibawa HANYA yang benar-benar sudah selesai. Yang belum selesai
     * dibiarkan apa adanya — mereka memang belum membaca apa pun.
     *
     * Barisnya DISALIN, bukan dipindah, dan bukan pula dengan melonggarkan
     * join penyelesaian. Join itu dipakai recompute kepatuhan, monitoring, dan
     * export; melonggarkannya mengubah arti "selesai" di seluruh modul
     * sekaligus, untuk semua revisi termasuk yang mayor.
     *
     * Id_Versi_Asal adalah buktinya: kelak bisa dibedakan mana penyelesaian
     * yang dikerjakan sungguhan dan mana yang dibawa. Tanpa itu, revisi minor
     * jadi cara diam-diam menyatakan orang sudah membaca sesuatu yang tak
     * pernah mereka buka.
     */
    private function bawaPenyelesaian(int $idVersi, array $itemTersentuh): void
    {
        $minor = DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)
            ->value('Wajib_Baca_Ulang');
        if ($minor !== SopStatus::INAKTIF) {
            return;
        }   // 'T' = minor; selain itu mayor

        $selesai = [SopStatus::STATUS_SELESAI, SopStatus::ITEM_DIKONFIRMASI];

        $sumber = DB::table('N_SOP_Progress')
            ->whereIn('Id_SOP_Paket_Item', $itemTersentuh)
            ->where('Id_SOP_Item_Versi', '<>', $idVersi)
            ->whereIn('Status', $selesai)
            ->get();

        foreach ($sumber as $p) {
            /* Sudah punya baris di versi baru berarti karyawan ini sudah
               menyentuhnya sendiri — jangan ditimpa oleh salinan. */
            $ada = DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $p->Id_SOP_Penjadwalan_Detail)
                ->where('Id_SOP_Paket_Item', $p->Id_SOP_Paket_Item)
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->exists();
            if ($ada) {
                continue;
            }

            $baris = (array) $p;
            unset($baris['Id_SOP_Progress']);
            $baris['Id_SOP_Item_Versi'] = $idVersi;
            $baris['Id_Versi_Asal'] = $p->Id_SOP_Item_Versi;
            $baris['Created_At'] = now();
            $baris['Updated_At'] = now();

            DB::table('N_SOP_Progress')->insert($baris);
        }
    }

    /**
     * Rollback: aktifkan kembali versi lama (OBSOLETE/PUBLISHED lain) jadi versi aktif.
     *
     * SEJAK FASE 3 IA TIDAK LAGI SILENT. Keputusan produk user 2026-08-09: rollback
     * ikut fan-out — pointer digeser pada SEMUA item perujuk dokumen, dan kewajiban
     * karyawan direkonsiliasi lewat jalur terantri yang sama dengan publish. Itu
     * membalik perilaku lama yang sengaja tak membuka ulang progres karyawan.
     */
    public function rollback(int $idVersi, int $userId): void
    {
        $target = DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)->first();
        if (! $target) {
            return;
        }

        $idDokumen = $target->Id_SOP_Dokumen !== null ? (int) $target->Id_SOP_Dokumen : null;
        $fanout = app(SopDokumenFanoutService::class);
        $itemTersentuh = $idDokumen !== null
            ? $fanout->itemPerujuk($idDokumen)
            : [(int) $target->Id_SOP_Paket_Item];

        DB::transaction(function () use ($target, $idVersi, $userId, $idDokumen, $itemTersentuh) {
            // obsolete-kan versi PUBLISHED aktif lain — berkunci DOKUMEN
            $lama = DB::table($this->table)
                ->where('Status', SopStatus::PAKET_PUBLISHED)
                ->where('Id_SOP_Item_Versi', '<>', $idVersi);
            $idDokumen !== null
                ? $lama->where('Id_SOP_Dokumen', $idDokumen)
                : $lama->where('Id_SOP_Paket_Item', $target->Id_SOP_Paket_Item);
            /* Tahap ikut bergeser bersama Status. Membiarkannya tertinggal
               membuat layar menyebut "Filing and Registered" untuk versi yang
               sudah digantikan — dua sumber untuk satu keadaan. */
            /* Tanggal digantikan dicap DI SINI, bukan hanya di pemindahan
               tahap: inilah jalur yang sebenarnya dipakai — versi menjadi
               obsolete karena penerusnya terbit, bukan karena ada yang menekan
               tombol. Ditulis terpisah dengan whereNull supaya versi yang sudah
               punya tanggal tak tertimpa. */
            (clone $lama)->whereNull('Tanggal_Obsolete')
                ->update(['Tanggal_Obsolete' => now()->toDateString()]);

            $lama->update([
                'Status' => 'OBSOLETE',
                'Tahap_Id' => $this->idTahap('OBSOLETE'),
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            /* AKTIFKAN KEMBALI VERSI TARGET.

               Tahap dan Tanggal_Obsolete ikut mundur bersama Status, persis
               seperti pada publish(). Sempat tidak: barisnya hanya menulis
               Status, sehingga versi yang kembali berlaku tetap bertahap
               OBSOLETE dan tetap membawa tanggal digantikannya. Akibatnya versi
               yang sedang mengikat karyawan tampil sebagai arsip di stepper,
               riwayat menyebutnya "digantikan pada" tanggal yang sudah lewat,
               dan Status tak lagi sejalan dengan Status_Teknis tahapnya —
               dua sumber untuk satu keadaan, yang seluruh rancangan tahap ini
               ada untuk mencegahnya.

               Sebabnya klasik: satu aturan ditulis di dua pintu (publish dan
               rollback) dan hanya satu yang dirawat. */
            DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)->update([
                'Status' => SopStatus::PAKET_PUBLISHED,
                'Tahap_Id' => $this->idTahap('FILING_REGISTERED'),
                'Tanggal_Obsolete' => null,
                'Tanggal_Publish' => now(),
                'Kode_Karyawan_Publisher' => Auth::user()?->karyawan?->Kode_Karyawan,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);

            // FAN-OUT POINTER: semua item perujuk dokumen, bukan hanya pemicunya
            DB::table('N_SOP_Paket_Item')
                ->whereIn('Id_SOP_Paket_Item', $itemTersentuh)
                ->update(['Id_Item_Versi_Aktif' => $idVersi, 'Updated_By' => $userId, 'Updated_At' => now()]);
        });
        /* REKONSILIASI — KEPUTUSAN PRODUK USER 2026-08-09, MEMBALIK PERILAKU LAMA.
           Rollback dulu SENGAJA senyap: karyawan yang sudah selesai membaca tidak
           dibuka ulang. Sejak satu dokumen bisa dipakai banyak paket, senyap
           berarti paket-paket menyimpang diam-diam tanpa jejak — persis masalah
           yang Master Dokumen dibuat untuk menyelesaikan.

           Konsekuensinya berat dan harus disengaja: satu klik rollback membuka
           ulang kewajiban karyawan di SEMUA paket perujuk. Endpoint pratinjau
           dampak (Task 5) menampilkan angkanya sebelum tombol ditekan. */
        $rekon = $idDokumen !== null
            ? $fanout->rekonsiliasi($idDokumen, 'VERSI_ROLLBACK', $userId)
            : ['outbox_id' => null, 'task_id' => null, 'paket' => 0];

        /* Jejaknya menyebut CAKUPAN, bukan sekadar "terjadi". Sejak Fase 2
           rekonsiliasi TIDAK lagi terjadi di sini: yang tertulis hanyalah satu
           baris intent, dan pekerjaannya berjalan di handler outbox. Nomor
           intent dan id baris progres karena itu ikut dicatat — keduanya
           satu-satunya bekas yang bisa ditelusuri admin bila progres karyawan
           ternyata tak tersemai (mis. dispatcher outbox tak berjalan). */
        \App\Services\LMS\SOP\Support\SopLog::catat(
            'VERSI_ROLLBACK',
            'Versi item di-rollback. Pointer digeser pada '.count($itemTersentuh)
                .' item perujuk; rekonsiliasi '.(int) ($rekon['paket'] ?? 0)
                .' paket diantrikan (task '.($rekon['task_id'] ?? '-').').',
            null,
            (int) $target->Id_SOP_Paket_Item,
            $idVersi,
            null,
            [
                'oleh_id_users' => $userId,
                'id_dokumen' => $idDokumen,
                'paket_diantrikan' => (int) ($rekon['paket'] ?? 0),
                'outbox_id' => $rekon['outbox_id'] ?? null,
                'task_id' => $rekon['task_id'] ?? null,
            ],
        );
    }

    /**
     * Salin versi AKTIF item sumber ke item baru sebagai v1 berstatus DRAFT
     * (untuk clone/ubah-tipe).
     *
     * Id_Item_Versi_Aktif item baru SENGAJA dibiarkan NULL. Pointer itu hanya
     * boleh menunjuk versi PUBLISHED, dan seluruh penulisnya (publish(),
     * rollback(), SopPaketVersionService::activatePendingItemVersions())
     * memasangnya satu tarikan napas dengan promosi ke PUBLISHED. Mengarahkannya
     * ke baris DRAFT membuat pembaca hilir — detailBundle, endpoint berkas,
     * dispatch kuis — menyajikan versi yang belum disahkan sebagai versi yang
     * berlaku, dan membuat versi draft itu tak bisa dihapus lagi (destroyDraft).
     *
     * Alurnya memang bertahap: admin "Tandai Siap" (DRAFT→READY), lalu rilis
     * paket mempromosikan READY→PUBLISHED sekaligus memasang pointer-nya.
     */
    public function cloneActiveTo(int $srcItemId, int $newItemId, int $userId): void
    {
        $pointer = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $srcItemId)->value('Id_Item_Versi_Aktif');
        if (! $pointer) {
            return;
        }
        $src = DB::table($this->table)->where('Id_SOP_Item_Versi', $pointer)->first();
        if (! $src) {
            return;
        }

        /* Item baru sudah mendapat dokumennya SENDIRI di cloneRow(); di sini
           kita hanya mengikutinya. ensureForItem() aman dipanggil dua kali.
           Dokumen sumber SENGAJA tidak dipakai ulang: berbagi adalah Fase 6,
           dan menyalakannya di sini membuat baris (dokumen, Versi_Ke=1) kedua
           ditolak unique index terfilter 26B dengan error 2601. */
        $idDokumen = app(SopDokumenService::class)->ensureForItem($newItemId, $userId);

        $newVersiId = DB::table($this->table)->insertGetId([
            'Id_SOP_Paket_Item' => $newItemId,
            'Id_SOP_Dokumen' => $idDokumen,
            'Versi_Ke' => 1,
            'Status' => SopStatus::PAKET_DRAFT,
            'Id_Master_Konten' => $src->Id_Master_Konten,
            'Id_Master_Ujian' => $src->Id_Master_Ujian ?? null,
            'Ringkasan_Perubahan' => $src->Ringkasan_Perubahan,
            'Tanggal_Berlaku' => $src->Tanggal_Berlaku,
            'Disahkan_Oleh' => $src->Disahkan_Oleh,
            'Jabatan_Pengesah' => $src->Jabatan_Pengesah,
            'Tanggal_Pengesahan' => $src->Tanggal_Pengesahan,
            'Nomor_Pengesahan' => $src->Nomor_Pengesahan,
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Item_Versi');
    }

    /** Nonaktifkan semua versi item + kosongkan pointer aktif (dipakai saat ubah tipe file→non-file). */
    public function deactivateAll(int $idItem, int $userId): void
    {
        DB::table($this->table)->where('Id_SOP_Paket_Item', $idItem)->update([
            'Flag_Aktif' => SopStatus::INAKTIF, 'Updated_By' => $userId, 'Updated_At' => now(),
        ]);
        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)
            ->update(['Id_Item_Versi_Aktif' => null, 'Updated_By' => $userId, 'Updated_At' => now()]);
    }

    /**
     * Hapus (soft) versi DRAFT saja, dan hanya jika bukan versi aktif.
     * Return true bila terhapus, false bila tidak memenuhi syarat.
     */
    public function destroyDraft(int $idVersi, int $userId): bool
    {
        $v = DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)->first();
        if (! $v) {
            return false;
        }
        if ($v->Status !== SopStatus::PAKET_DRAFT) {
            return false;
        }

        $aktif = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $v->Id_SOP_Paket_Item)
            ->value('Id_Item_Versi_Aktif');
        if ((int) $aktif === (int) $idVersi) {
            return false;
        }

        DB::table($this->table)->where('Id_SOP_Item_Versi', $idVersi)->update([
            'Flag_Aktif' => SopStatus::INAKTIF,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        return true;
    }
}
