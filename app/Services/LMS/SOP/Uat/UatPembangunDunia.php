<?php

namespace App\Services\LMS\SOP\Uat;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\SopPaketDraftService;
use App\Services\LMS\SOP\SopPaketService;
use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\SopSectionService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Membangun dunia SOP untuk lapis PERAMBAN, dan mencatat tiap baris ke jurnal.
 *
 * KENAPA TERPISAH DARI UatDunia MILIK TEST BACKEND, padahal isinya mirip. Umur
 * keduanya berbeda secara mendasar: uji backend hidup di dalam satu transaksi
 * dan lenyap sendiri saat rollback, sedangkan peramban memukul server lewat
 * HTTP di proses lain — tak ada transaksi yang bisa dibatalkan, sehingga tiap
 * baris harus dicatat saat lahir supaya bisa dihapus belakangan. Menyatukannya
 * berarti satu di antaranya membawa mesin yang tak pernah ia pakai, dan mesin
 * yang tak pernah dipakai adalah mesin yang diam-diam berhenti bekerja.
 *
 * KENAPA MEMBUAT SENDIRI, BUKAN MEMINJAM PENUGASAN YANG ADA. Membaca dokumen
 * menulis waktu buka dan menggeser status paket. Meminjam penugasan karyawan
 * sungguhan berarti mengubah rekam kepatuhan orang yang tak pernah membuka
 * apa pun — dan itu tak bisa dipulihkan jurnal, karena yang terjadi pembaruan,
 * bukan penyisipan.
 */
class UatPembangunDunia
{
    private const PENGESAH = [
        ['Peran' => 'Disiapkan oleh', 'Nama' => 'Penyiap Uji UAT', 'Jabatan' => 'Staff'],
        ['Peran' => 'Disahkan oleh', 'Nama' => 'Pengesah Uji UAT', 'Jabatan' => 'Dept. Head'],
    ];

    /**
     * Karyawan aktif untuk disasar penugasan massal.
     *
     * DIURUTKAN, bukan diacak. Sasaran yang berganti tiap jalan membuat dua
     * hasil uji beban tak bisa dibandingkan — perbedaan angkanya bisa datang
     * dari orang yang berbeda, bukan dari perubahan yang sedang diukur.
     *
     * Populasi dev hanya 58 karyawan aktif, sedangkan ambang async penjadwalan
     * 30. Meminta di bawah 31 berarti seluruh uji diam-diam mengukur jalur
     * inline, lalu melaporkannya seolah jalur antrean ikut terbukti.
     *
     * @return list<string>
     */
    public function pilihKaryawanAktif(int $jumlah): array
    {
        return DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->whereNotNull('Kode_Karyawan')
            ->orderBy('Kode_Karyawan')
            ->limit(max(1, $jumlah))
            ->pluck('Kode_Karyawan')
            ->map(fn ($k) => trim((string) $k))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $judulItem
     * @param  bool  $terbitkan  false = paket berhenti di DRAFT dan tak ditugaskan
     * @param  bool  $revisiTertunda  true = sesudah terbit, siapkan satu revisi dokumen yang menunggu rilis
     * @return array{jalan:string, tanda:string, dokumen:list<int>, versi:list<int>, paket:int, paket_hash:string, item:list<int>, penjadwalan:int}
     */
    public function bangun(
        int $userId,
        string|array $kodeKaryawan,
        array $judulItem = ['Dokumen Pertama', 'Dokumen Kedua'],
        string $modeUrutan = 'BEBAS',
        bool $terbitkan = true,
        bool $revisiTertunda = false,
    ): array {
        UatGerbang::pastikanTerbuka();

        $jurnal = UatJurnal::mulai();
        $tanda = 'UAT-AUTO-'.Str::lower(Str::random(6));

        $konten = $this->kontenDokumenCocok();
        if (! $konten) {
            throw new RuntimeException(
                'Tidak ada berkas bertipe cocok untuk dokumen di database ini, sehingga '
                .'dunia uji tak bisa dibangun.'
            );
        }

        /* ---- dokumen + versi terbit, SATU PER ITEM.
           Bukan satu dokumen dipakai bersama. Sejak identitas item mengikuti
           dokumennya, menyimpan item dari Builder menulis ulang judulnya dari
           judul dokumen — dua item yang menumpang satu dokumen berubah menjadi
           dua baris bernama sama begitu salah satunya disunting, dan uji yang
           membedakan item lewat judul kehilangan pegangannya di tengah jalan.
           Ini juga bentuk yang wajar: satu item SOP memang satu dokumen. */
        $dokSvc = app(SopDokumenService::class);
        $dokumen = [];
        $versi = [];

        foreach (array_values($judulItem) as $i => $judul) {
            $nomor = "{$tanda}-".($i + 1);

            $idDok = $dokSvc->buat([
                'Judul' => "{$tanda} {$judul}",
                'Nomor_SOP_Resmi' => $nomor,
                'Tipe' => 'DOKUMEN',
                'Deskripsi' => 'Dibangun otomatis untuk lapis peramban UAT.',
            ], $userId);
            $jurnal->catat('N_SOP_Dokumen', $idDok);

            $idVersi = (int) $dokSvc->tambahVersi($idDok, [
                'Id_Master_Konten' => $konten,
                'Ringkasan_Perubahan' => 'Versi awal dunia uji peramban.',
                'Tanggal_Berlaku' => now()->subMonth()->toDateString(),
                'Pengesah' => self::PENGESAH,
            ], $userId);
            $jurnal->catat('N_SOP_Item_Versi', $idVersi);

            foreach (DB::table('N_SOP_Item_Versi_Pengesah')->where('Id_SOP_Item_Versi', $idVersi)
                ->pluck('Id_SOP_Item_Versi_Pengesah') as $p) {
                $jurnal->catat('N_SOP_Item_Versi_Pengesah', (int) $p);
            }

            $dokSvc->terbitkanVersi($idVersi, $userId);

            $dokumen[] = (int) $idDok;
            $versi[] = $idVersi;
        }

        // ---- paket + section + item
        $paket = app(SopPaketService::class)->create([
            'Nama_Paket' => "{$tanda} Paket",
            'Mode_Urutan_Item' => $modeUrutan,
        ], $userId);
        $jurnal->catat('N_SOP_Master_Paket', $paket);

        $section = app(SopSectionService::class)->create($paket, [
            'Nama_Section' => "{$tanda} Section",
        ], $userId);
        $jurnal->catat('N_SOP_Paket_Section', $section);

        $item = [];
        foreach (array_values($judulItem) as $i => $judul) {
            $id = app(SopItemService::class)->create($paket, [
                'Id_SOP_Section' => $section,
                'Tipe_Item' => 'DOKUMEN',
                'Judul_Item' => "{$tanda} {$judul}",
                'Id_SOP_Dokumen' => $dokumen[$i],
                'Nomor_SOP_Resmi' => "{$tanda}-".($i + 1),
                'Flag_Wajib' => true,
            ], $userId);

            /* Penunjuk versi aktif disamakan dengan versi dokumen yang terbit.
               Di aplikasi hal ini dikerjakan fan-out saat dokumen terbit; di
               sini itemnya lahir SESUDAH terbit, jadi tak ada fan-out yang
               menyusulinya dan pembacaan yang menjoin lewat penunjuk itu tak
               akan menemukan apa-apa. */
            DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $id)
                ->update(['Id_Item_Versi_Aktif' => $versi[$i]]);

            $jurnal->catat('N_SOP_Paket_Item', (int) $id);
            $item[] = (int) $id;
        }

        /* Paket boleh berhenti di DRAFT. Skenario Builder (menyusun urutan,
           menandai wajib) memang dikerjakan pada paket yang BELUM terbit; pada
           paket yang sudah terbit, Builder menulis ke overlay draft — tabel
           lain, dengan id bertoken — sehingga uji yang sama akan mengukur jalur
           yang bukan jalur yang dijanjikan skenarionya. */
        $penjadwalan = 0;
        $sasaran = [];

        if ($terbitkan) {
            app(SopPaketService::class)->updateStatus(
                $paket, SopStatus::PAKET_PUBLISHED, $userId, 'Terbit dari dunia uji peramban.');

            /* Satu kode maupun daftar sama-sama diterima. Uji peramban menugaskan
               satu orang; uji beban menugaskan puluhan sekaligus supaya jalur
               ANTREAN (ambang async 30) benar-benar tersentuh. */
            $sasaran = array_values(array_filter(array_unique(array_map(
                fn ($k) => trim((string) $k), (array) $kodeKaryawan
            ))));

            // ---- penjadwalan
            $hasil = app(SopPenjadwalanService::class)->assign($paket, $sasaran, [
                'nama_batch' => "{$tanda} Batch",
                'sumber' => 'MANUAL',
            ], $userId);

            $penjadwalan = (int) ($hasil['penjadwalan_id'] ?? 0);
            $jurnal->catat('N_SOP_Penjadwalan', $penjadwalan);

            foreach (DB::table('N_SOP_Penjadwalan_Detail')->where('Id_SOP_Penjadwalan', $penjadwalan)
                ->pluck('Id_SOP_Penjadwalan_Detail') as $d) {
                $jurnal->catat('N_SOP_Penjadwalan_Detail', (int) $d);

                /* Progres yang tersemai saat assign ikut dicatat SEKARANG. Baris
                   yang lahir belakangan — saat karyawan membuka dokumen di
                   peramban — dibersihkan lewat penugasannya, bukan lewat jurnal:
                   jurnal hanya tahu apa yang ada saat ia menulis. */
                foreach (DB::table('N_SOP_Progress')->where('Id_SOP_Penjadwalan_Detail', $d)
                    ->pluck('Id_SOP_Progress') as $pr) {
                    $jurnal->catat('N_SOP_Progress', (int) $pr);
                }
            }

            $this->catatPemberitahuan($jurnal, $paket);
        }

        if ($terbitkan && $revisiTertunda) {
            $this->siapkanRevisiTertunda($jurnal, $paket, $item[0], $konten, $userId);
        }

        return [
            'jalan' => $jurnal->id(),
            'tanda' => $tanda,
            'dokumen' => $dokumen,
            'versi' => $versi,
            'paket' => $paket,
            /* Builder mengenali paket lewat hashid pada ?paket=, bukan lewat id
               mentah. Tanpa ini spesifikasi harus menyusuri daftar paket dan
               mengklik yang judulnya cocok — panjang, dan gagal begitu daftarnya
               berhalaman. */
            'paket_hash' => Hashids::connection('custom')->encode($paket),
            'item' => $item,
            /* WAJIB, bukan tambahan. Endpoint pembaca
               /api/v1/sop/{idPaket}/item/{idItem}/versi menerima hashid,
               sedangkan 'item' berisi id mentah. Pembangkit beban yang memakai id
               mentah akan selalu menerima 404 — dan 404 yang cepat terbaca
               sebagai "pembaca dokumen ternyata ringan", kesimpulan yang persis
               terbalik dari kenyataannya. */
            'item_hash' => array_map(
                fn ($id) => Hashids::connection('custom')->encode($id), $item
            ),
            'penjadwalan' => $penjadwalan,
            'karyawan' => $sasaran,
        ];
    }

    /**
     * Siapkan satu revisi dokumen yang MENUNGGU rilis pada paket terbit.
     *
     * Tanpa ini, layar penerbitan paket berbunyi "Tak ada revisi dokumen yang
     * ikut terbit" dan tak menawarkan pilihan sifat revisi sama sekali — yang
     * berarti bagian paling penting dari skenario 019 tak pernah tersentuh.
     *
     * Menyusunnya lewat Builder di peramban akan jauh lebih panjang dan
     * menyeret unggah berkas ke dalam uji yang bukan tentang unggah berkas.
     * Yang diuji peramban adalah LAYAR PENERBITANNYA, bukan cara revisinya lahir.
     */
    private function siapkanRevisiTertunda(
        UatJurnal $jurnal,
        int $paket,
        int $itemAsal,
        int $konten,
        int $userId,
    ): void {
        $svc = app(SopPaketDraftService::class);

        $draft = $svc->ensure($paket, $userId);
        if (! $draft) {
            throw new RuntimeException(
                'Overlay draft Builder tidak tersedia di database ini, sehingga revisi '
                .'tertunda tak bisa disiapkan.'
            );
        }

        $idDraft = (int) $draft->Id_SOP_Paket_Draft;
        $jurnal->catat('N_SOP_Paket_Draft', $idDraft);

        foreach (DB::table('N_SOP_Paket_Draft_Section')->where('Id_SOP_Paket_Draft', $idDraft)
            ->pluck('Id_SOP_Draft_Section') as $s) {
            $jurnal->catat('N_SOP_Paket_Draft_Section', (int) $s);
        }

        $draftItem = null;
        foreach (DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Paket_Draft', $idDraft)
            ->get(['Id_SOP_Draft_Item', 'Id_SOP_Paket_Item_Asal']) as $di) {
            $jurnal->catat('N_SOP_Paket_Draft_Item', (int) $di->Id_SOP_Draft_Item);

            if ((int) $di->Id_SOP_Paket_Item_Asal === $itemAsal) {
                $draftItem = (int) $di->Id_SOP_Draft_Item;
            }
        }

        if (! $draftItem) {
            throw new RuntimeException(
                "Salinan kerja item {$itemAsal} tak ditemukan di overlay draft."
            );
        }

        /* Pengesahan diisi di sini. Sejak penjaga celah 013 berdiri, versi tanpa
           pengesah ditolak saat terbit — dan penolakan itu akan muncul sebagai
           kegagalan di layar penerbitan, seolah layarnya yang rusak. */
        $idVersi = $svc->createVersion($draftItem, [
            'Id_Master_Konten' => $konten,
            'Ringkasan_Perubahan' => 'Revisi tertunda untuk lapis peramban UAT.',
            'Tanggal_Berlaku' => now()->toDateString(),
            'Disahkan_Oleh' => self::PENGESAH[1]['Nama'],
            'Jabatan_Pengesah' => self::PENGESAH[1]['Jabatan'],
            'Tanggal_Pengesahan' => now()->toDateString(),
        ], $userId);

        if (! $idVersi) {
            throw new RuntimeException('Versi draft gagal dibuat pada overlay Builder.');
        }

        $jurnal->catat('N_SOP_Paket_Draft_Item_Versi', (int) $idVersi);

        /* Hanya versi SIAP RILIS yang dihitung layar penerbitan. Draft yang
           belum ditandai siap tak ikut, dan tanpa langkah ini pratinjaunya
           kosong persis seperti kalau revisinya tak pernah dibuat. */
        $svc->setVersionReady((int) $idVersi, $userId, true);
    }

    /**
     * Lengkapi jurnal dengan baris progres yang lahir SETELAH dunia dibangun.
     *
     * Membuka dokumen di peramban menyisipkan baris progres baru yang tak
     * mungkin diketahui jurnal saat ia ditulis. Tanpa langkah ini, pembersihan
     * gagal pada foreign key — dan gagal separuh jalan adalah keadaan yang
     * paling sulit dibereskan tangan.
     */
    public function lengkapiJurnal(string $idJalan, int $penjadwalan, int $paket = 0): int
    {
        $jurnal = UatJurnal::muat($idJalan);
        $n = 0;

        /* SELURUH penjadwalan milik paket ini, bukan hanya yang disebut.
           Uji beban memanggil assign() lagi di tengah jalan, dan tiap panggilan
           melahirkan header penjadwalan BARU. Menyapu satu header saja
           meninggalkan header kedua beserta seluruh detailnya di luar jurnal —
           dan pembersihan lalu berhenti pada foreign key saat mencoba menghapus
           paketnya. */
        $idPenjadwalan = collect([$penjadwalan])->filter();

        if ($paket > 0) {
            $idPenjadwalan = $idPenjadwalan
                ->merge(DB::table('N_SOP_Penjadwalan')->where('Id_SOP_Paket', $paket)
                    ->pluck('Id_SOP_Penjadwalan'))
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values();

            foreach ($idPenjadwalan as $p) {
                $jurnal->catat('N_SOP_Penjadwalan', (int) $p);
            }
        }

        $detail = DB::table('N_SOP_Penjadwalan_Detail')
            ->whereIn('Id_SOP_Penjadwalan', $idPenjadwalan->all() ?: [0])
            ->pluck('Id_SOP_Penjadwalan_Detail');

        foreach ($detail as $d) {
            $jurnal->catat('N_SOP_Penjadwalan_Detail', (int) $d);

            foreach (['N_SOP_Progress' => 'Id_SOP_Progress'] as $tabel => $pk) {
                foreach (DB::table($tabel)->where('Id_SOP_Penjadwalan_Detail', $d)->pluck($pk) as $id) {
                    $jurnal->catat($tabel, (int) $id);
                    $n++;
                }
            }
        }

        if ($paket > 0) {
            $n += $this->sapuTurunanPaket($jurnal, $paket);
        }

        return $n;
    }

    /**
     * Pungut baris yang lahir dari PENERBITAN paket di peramban.
     *
     * Menerbitkan paket dari Builder membuat rilis paket, membekukan isinya,
     * dan menaikkan versi tiap dokumen yang ikut — semuanya sesudah jurnal
     * ditulis, jadi tak satu pun tercatat. Pembersihan lalu berhenti di tengah
     * pada foreign key: dokumennya tak bisa dihapus karena versi yang baru saja
     * lahir masih menunjuknya, dan yang tersisa adalah keadaan separuh-terhapus
     * yang paling sulit dibereskan tangan.
     *
     * Disapu dari PAKETNYA, bukan dari nama: versi dan rilis tidak menyandang
     * penanda UAT-AUTO di kolom mana pun.
     */
    private function sapuTurunanPaket(UatJurnal $jurnal, int $paket): int
    {
        /* Pagar. Menyapu "segala yang menunjuk paket ini" hanya aman selama
           paketnya memang milik dunia uji; menjalankannya pada paket sungguhan
           akan mendaftarkan isi paket orang untuk dihapus. */
        $nama = (string) DB::table('N_SOP_Master_Paket')->where('Id_SOP_Paket', $paket)->value('Nama_Paket');

        if (! str_starts_with($nama, 'UAT-AUTO-')) {
            throw new RuntimeException(
                "Paket {$paket} bukan milik dunia uji (nama: '{$nama}'), jadi turunannya tidak disapu."
            );
        }

        $n = 0;
        $catat = function (string $tabel, $id) use ($jurnal, &$n) {
            $jurnal->catat($tabel, (int) $id);
            $n++;
        };

        // Rilis paket beserta isinya yang dibekukan.
        foreach (DB::table('N_SOP_Paket_Versi')->where('Id_SOP_Paket', $paket)
            ->pluck('Id_SOP_Paket_Versi') as $pv) {
            foreach (DB::table('N_SOP_Paket_Versi_Item')->where('Id_SOP_Paket_Versi', $pv)
                ->pluck('Id_SOP_Paket_Versi_Item') as $pvi) {
                $catat('N_SOP_Paket_Versi_Item', $pvi);
            }
            $catat('N_SOP_Paket_Versi', $pv);
        }

        // Overlay draft — dibuang saat publish, tapi bisa tersisa bila publish gagal.
        foreach (DB::table('N_SOP_Paket_Draft')->where('Id_SOP_Paket', $paket)
            ->pluck('Id_SOP_Paket_Draft') as $pd) {
            foreach (DB::table('N_SOP_Paket_Draft_Item')->where('Id_SOP_Paket_Draft', $pd)
                ->pluck('Id_SOP_Draft_Item') as $di) {
                foreach (DB::table('N_SOP_Paket_Draft_Item_Versi')->where('Id_SOP_Draft_Item', $di)
                    ->pluck('Id_SOP_Draft_Item_Versi') as $div) {
                    $catat('N_SOP_Paket_Draft_Item_Versi', $div);
                }
                $catat('N_SOP_Paket_Draft_Item', $di);
            }
            foreach (DB::table('N_SOP_Paket_Draft_Section')->where('Id_SOP_Paket_Draft', $pd)
                ->pluck('Id_SOP_Draft_Section') as $ds) {
                $catat('N_SOP_Paket_Draft_Section', $ds);
            }
            $catat('N_SOP_Paket_Draft', $pd);
        }

        /* Versi dokumen yang ikut naik saat paket terbit. Ditelusuri lewat
           dokumen yang disajikan item paket ini — dokumen itu memang lahir
           bersama dunia uji, jadi seluruh versinya milik dunia uji juga. */
        $dokumen = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $paket)
            ->whereNotNull('Id_SOP_Dokumen')->pluck('Id_SOP_Dokumen')->unique();

        foreach ($dokumen as $dok) {
            foreach (DB::table('N_SOP_Item_Versi')->where('Id_SOP_Dokumen', $dok)
                ->pluck('Id_SOP_Item_Versi') as $v) {
                foreach (DB::table('N_SOP_Item_Versi_Pengesah')->where('Id_SOP_Item_Versi', $v)
                    ->pluck('Id_SOP_Item_Versi_Pengesah') as $p) {
                    $catat('N_SOP_Item_Versi_Pengesah', $p);
                }
                foreach (DB::table('N_SOP_Item_Versi_Berkas')->where('Id_SOP_Item_Versi', $v)
                    ->pluck('Id_SOP_Item_Versi_Berkas') as $b) {
                    $catat('N_SOP_Item_Versi_Berkas', $b);
                }
                $catat('N_SOP_Item_Versi', $v);
            }
        }

        // Jejak Builder pada paket ini.
        foreach (DB::table('N_SOP_Log')->where('Id_SOP_Paket', $paket)->pluck('Id_SOP_Log') as $l) {
            $catat('N_SOP_Log', $l);
        }

        /* Menerbitkan paket mengabari karyawannya "SOP Diperbarui" — lahir
           sesudah jurnal ditulis, sama seperti rilis dan versi di atas. */
        $n += $this->catatPemberitahuan($jurnal, $paket);

        return $n;
    }

    /**
     * Pemberitahuan in-app yang lahir dari paket dunia uji.
     *
     * Menugaskan paket menulis "SOP Baru Untuk Anda" ke lonceng karyawan, dan
     * menerbitkannya menulis "SOP Diperbarui" — keduanya di luar transaksi.
     * Tanpa dicatat, tiap jalan-uji meninggalkan pemberitahuan pada akun
     * karyawan SUNGGUHAN yang menunjuk paket yang sudah dihapus: ditekan tak
     * membuka apa-apa, dan jumlahnya bertambah setiap kali suite berjalan.
     *
     * Dikenali lewat Reference_Id paketnya, bukan lewat isi pesannya. Isi pesan
     * kebetulan memuat penanda UAT-AUTO sekarang; kalau formatnya berubah,
     * penyaring berbasis teks akan diam-diam berhenti menemukan apa pun.
     */
    private function catatPemberitahuan(UatJurnal $jurnal, int $paket): int
    {
        $n = 0;

        foreach (DB::table('N_HRIS_Notification')->where('Reference_Id', 'SOP-'.$paket)
            ->pluck('Id_Notification') as $id) {
            $jurnal->catat('N_HRIS_Notification', (int) $id);
            $n++;
        }

        return $n;
    }

    private function kontenDokumenCocok(): ?int
    {
        $id = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('d.Tipe', 'DOKUMEN')
            ->whereNotNull('v.Id_Master_Konten')
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->value('v.Id_Master_Konten');

        return $id ? (int) $id : null;
    }
}
