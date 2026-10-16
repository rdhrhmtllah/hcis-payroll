<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopDiff;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopNomorDokumen;
use App\Services\LMS\SOP\Support\SopNomorVersi;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopUnitKerja;
use App\Services\LMS\SOP\Support\SopVersiKeadaan;
use App\Services\SYSTEM\MasterData\Departemen\DepartemenService;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Dokumen SOP — satu-satunya tempat yang MEMBUAT baris N_SOP_Dokumen
 * untuk sebuah item.
 *
 * KENAPA TERPUSAT. Ada tiga penulis baris N_SOP_Item_Versi live:
 * SopItemVersionService::create(), ::cloneActiveTo(), dan promosi draft->live
 * di SopPaketDraftService. Kalau masing-masing membuat dokumennya sendiri,
 * pemetaan field-nya akan menyimpang diam-diam dan backfill Fase 1 meluruh satu
 * jalur pada satu waktu. Pemetaannya karena itu dikunci di sini dan SENGAJA
 * identik dengan SopDokumenBackfill — dokumen yang lahir hari ini harus tak
 * bisa dibedakan dari dokumen hasil backfill.
 *
 * FASE 2 MASIH 1 DOKUMEN = 1 ITEM. Berbagi dokumen antar paket adalah Fase 6,
 * lewat pemilih Dokumen di Builder — bukan efek samping penyalinan item.
 * Menyalakannya lebih awal membuat cloneActiveTo() menulis (dokumen, Versi_Ke=1)
 * yang kedua dan ditolak unique index terfilter 26B dengan error 2601.
 */
class SopDokumenService
{
    private string $table = 'N_SOP_Dokumen';

    /** Id dokumen milik item, atau null bila item tak bertaut dokumen. */
    public function forItem(int $idItem): ?int
    {
        $id = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->value('Id_SOP_Dokumen');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Buat dokumen dari sebuah baris item.
     *
     * Id_Versi_Aktif SENGAJA dibiarkan NULL. Pointer versi aktif hanya boleh
     * menunjuk versi PUBLISHED, dan dokumen yang baru lahir belum punya satu
     * pun — memasangnya lebih awal berarti menyajikan versi yang belum sah ke
     * karyawan, dan membuat draftnya tak bisa dihapus.
     */
    public function createForItem(object $item, int $userId): int
    {
        /* NOMOR RESMI TAK IKUT KALAU SUDAH DIPAKAI.
           Metode ini melayani dua pemanggil yang berlawanan keadaannya.
           Backfill item lama: nomornya belum dimiliki dokumen mana pun, jadi
           ikut dibawa — itu memang nomor SOP tersebut. Penyalinan paket:
           dokumennya sudah ada dan memegang nomor itu, sehingga menyalinnya
           melahirkan dua dokumen yang sama-sama mengaku "PERDIN-001".
           Seluruh 4 kelompok kembar di dev lahir persis begitu — dari satu
           penyalinan paket pada 8 Agu 2026, jam yang sama.
           Nomor yang bentrok DIKOSONGKAN, bukan membatalkan penyalinan:
           salinannya tetap sah, cuma menunggu nomornya sendiri. */
        $nomor = trim((string) ($item->Nomor_SOP_Resmi ?? ''));
        if ($nomor !== '') {
            $terpakai = DB::table($this->table)
                ->where('Nomor_SOP_Resmi', $nomor)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->exists();
            if ($terpakai) {
                $nomor = null;
            }
        }

        return DB::table($this->table)->insertGetId([
            'Nomor_SOP_Resmi' => $nomor ?: null,
            // N_SOP_Dokumen.Judul NOT NULL (26A) sementara Judul_Item nullable.
            // Fallback ini sama persis dengan SopDokumenBackfill::judulDokumen();
            // untuk judul terisi, dokumen menyalinnya apa adanya.
            'Judul' => trim((string) ($item->Judul_Item ?? '')) !== ''
                                    ? $item->Judul_Item
                                    : 'Dokumen tanpa judul',
            'Tipe' => $item->Tipe_Item,
            'Deskripsi' => $item->Deskripsi_Item ?? null,
            'Id_Versi_Aktif' => null,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => $userId,
            'Created_At' => now(),
        ], 'Id_SOP_Dokumen');
    }

    /**
     * Deklarasikan Dokumen SOP baru — TANPA item.
     *
     * Ini titik awal alur yang dituju: admin mendeklarasikan SOP lebih dulu di
     * halaman Master Dokumen, lalu memakainya di banyak Paket SOP lewat pemilih
     * Dokumen di Builder. Sebelum ini dokumen hanya bisa lahir sebagai efek
     * samping pembuatan item, sehingga tak ada cara mendeklarasikan SOP tanpa
     * langsung menempelkannya ke satu paket — dan itu membalik urutan yang
     * sebenarnya diinginkan.
     *
     * Id_Versi_Aktif sengaja NULL: dokumen yang baru dideklarasikan belum punya
     * versi PUBLISHED, dan pointer itu hanya boleh menunjuk versi yang sah.
     */
    /**
     * Nomor SOP resmi tak boleh dipakai dua dokumen.
     *
     * Nomor itulah rujukan kepatuhan yang dipakai di luar sistem — dikutip di
     * audit, ditempel di dinding, disebut dalam temuan. Dua dokumen bernomor
     * sama membuat pertanyaan "SOP PERDIN-001 versi berapa yang berlaku?"
     * punya lebih dari satu jawaban benar, dan tak ada cara memilih di antaranya.
     *
     * DITEGAKKAN DI SINI, bukan di controller: dokumen lahir dari DUA pintu —
     * Master Dokumen dan Builder (buatDokumenBaru) — dan aturan yang tinggal di
     * satu controller hanya menjaga satu pintu.
     *
     * Nomor kosong dilewatkan: sebagian dokumen memang belum bernomor, dan
     * memperlakukan kekosongan sebagai bentrok akan mengunci semuanya kecuali
     * yang pertama. Per 14 Agu 2026 ada 9 kelompok kembar di dev — aturan ini
     * TIDAK berlaku surut, hanya menghentikan yang berikutnya.
     *
     * @param  int|null  $kecuali  Dokumen yang sedang disunting; dirinya sendiri bukan bentrok.
     *
     * @throws \DomainException
     */
    public function pastikanNomorUnik(?string $nomor, ?int $kecuali = null): void
    {
        $nomor = trim((string) $nomor);
        if ($nomor === '') {
            return;
        }

        $bentrok = DB::table($this->table)
            ->where('Nomor_SOP_Resmi', $nomor)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->when($kecuali, fn ($q) => $q->where('Id_SOP_Dokumen', '<>', $kecuali))
            ->value('Judul');

        if ($bentrok !== null) {
            throw new \DomainException(
                'Nomor Dokumen "'.$nomor.'" sudah dipakai dokumen "'.$bentrok.'". '
                .'Satu nomor hanya boleh menunjuk satu dokumen — pakai nomor lain, '
                .'atau revisi dokumen yang sudah ada itu.');
        }
    }

    public function buat(array $data, int $userId): int
    {
        $this->pastikanFieldSesuaiTipe((string) ($data['Tipe'] ?? ''), $data);
        /* Nomor resmi TIDAK lagi diketik manusia — ia dirakit dari identitas
           setelah barisnya tersimpan (lihat segarkanNomorResmi). Yang lewat
           di sini hanyalah nilai dari jalur non-layar: backfill dan penyalinan
           paket, yang memang membawa penomoran lama. */
        $this->pastikanNomorUnik($data['Nomor_SOP_Resmi'] ?? null);
        $this->pastikanJenisSesuaiTipe((string) ($data['Tipe'] ?? ''), $data['Jenis_Dokumen_Id'] ?? null);
        $this->pastikanUnitSah($data['Divisi_Id'] ?? null, $data['Sub_Divisi_Id'] ?? null);
        $this->pastikanEntitasSah($data['Entitas_Pemilik_Id'] ?? null, 'pemilik');
        $this->pastikanEntitasSah($data['Entitas_Lingkup_Id'] ?? null, 'ruang lingkup');
        $this->pastikanApqcSesuaiTipe((string) ($data['Tipe'] ?? ''), $data['APQC_Id'] ?? null);

        $this->pastikanDepartemenSah($data['Departemen_Id'] ?? null);
        $data = $this->turunkanUnitDariDepartemen($data);
        $data['Divisi_Id'] = $this->divisiTurunan($data['Divisi_Id'] ?? null, $data['Sub_Divisi_Id'] ?? null);

        /* Nomor tak dikirim berarti pemanggil menyerahkannya ke sistem — jalur
           backfill dan penyalinan paket termasuk di dalamnya. */
        if (! array_key_exists('No_Dokumen', $data) || $data['No_Dokumen'] === null || $data['No_Dokumen'] === '') {
            $data['No_Dokumen'] = $this->saranNoDokumen($data);
        }
        $this->pastikanNoDokumenUnik($data['No_Dokumen'], $data);

        $id = DB::table($this->table)->insertGetId([
            'Nomor_SOP_Resmi' => $data['Nomor_SOP_Resmi'] ?? null,
            'Judul' => $data['Judul'],
            'Tipe' => $data['Tipe'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Jenis_Dokumen_Id' => $data['Jenis_Dokumen_Id'] ?? null,
            'Divisi_Id' => $data['Divisi_Id'] ?? null,
            'Sub_Divisi_Id' => $data['Sub_Divisi_Id'] ?? null,
            'Departemen_Id' => $data['Departemen_Id'] ?? null,
            'Entitas_Pemilik_Id' => $data['Entitas_Pemilik_Id'] ?? null,
            'Entitas_Lingkup_Id' => $data['Entitas_Lingkup_Id'] ?? null,
            'APQC_Id' => $data['APQC_Id'] ?? null,
            'No_Dokumen' => $data['No_Dokumen'] ?? null,
            'Id_Versi_Aktif' => null,
            'Flag_Aktif' => SopStatus::AKTIF,
            'Created_By' => $userId,
            'Created_At' => now(),
        ], 'Id_SOP_Dokumen');

        $this->jejak(
            'DOK_BUAT',
            /* ?? sebelum ?: — Nomor_SOP_Resmi memang boleh tak dikirim sama
               sekali, dan barisnya di atas sudah memperlakukannya begitu. */
            'Dokumen dibuat: '.($data['Judul'] ?? '(tanpa judul)').' · '
                .(($data['Nomor_SOP_Resmi'] ?? null) ?: 'tanpa nomor')
                .' · tipe '.($data['Tipe'] ?? '—').'.',
            $id,
            $userId,
        );

        $this->segarkanNomorResmi($id, $userId);

        return $id;
    }

    /**
     * Ubah identitas dokumen, lalu sebarkan ke item perujuknya.
     *
     * Penyebaran BUKAN opsional: identitas item adalah cerminan dokumen, jadi
     * mengubah salah satunya saja menghasilkan dua nama untuk satu dokumen
     * terkendali — penyakit yang justru diobati modul ini.
     */
    public function ubah(int $idDokumen, array $data, int $userId): bool
    {
        /* Dibaca UTUH lebih dulu, bukan sekadar exists(): nilai lamanya cuma
           hidup di baris ini, dan sesudah update ia tak ada di mana pun lagi.
           Inilah satu-satunya kesempatan merekamnya. */
        $lama = DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)
            ->first(['Nomor_SOP_Resmi', 'Judul', 'Tipe', 'Deskripsi',
                'Jenis_Dokumen_Id', 'Divisi_Id', 'Sub_Divisi_Id',
                'Entitas_Pemilik_Id', 'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen',
                'Departemen_Id']);
        if (! $lama) {
            return false;
        }

        $tipeBaru = (string) ($data['Tipe'] ?? $lama->Tipe);
        $this->pastikanFieldSesuaiTipe($tipeBaru, $data);

        $baru = [
            'Nomor_SOP_Resmi' => $data['Nomor_SOP_Resmi'] ?? null,
            'Judul' => $data['Judul'],
            'Tipe' => $data['Tipe'],
            'Deskripsi' => $data['Deskripsi'] ?? null,
        ];

        /* Tiga kolom tata kelola hanya ikut bila pemanggilnya memang
           mengirimnya. Memakai `?? null` seperti Deskripsi akan MENGHAPUS
           klasifikasi dan kepemilikan setiap kali ada pemanggil yang cuma
           mengubah judul — dan penghapusannya diam, tanpa satu pun galat. */
        foreach (['Jenis_Dokumen_Id', 'Divisi_Id', 'Sub_Divisi_Id',
            'Entitas_Pemilik_Id', 'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen',
            'Departemen_Id'] as $kolom) {
            if (array_key_exists($kolom, $data)) {
                $baru[$kolom] = $data[$kolom] !== '' ? $data[$kolom] : null;
            }
        }

        $this->pastikanJenisSesuaiTipe(
            $tipeBaru,
            array_key_exists('Jenis_Dokumen_Id', $baru) ? $baru['Jenis_Dokumen_Id'] : $lama->Jenis_Dokumen_Id,
        );
        if (array_key_exists('Departemen_Id', $baru)) {
            $this->pastikanDepartemenSah($baru['Departemen_Id']);
            /* Sub-divisi dan divisi adalah CERMINAN departemen sejak
               DDL-2026-027. Membiarkan nilai lama bertahan setelah
               departemennya berganti menyimpan pasangan yang tak pernah ada. */
            $baru = $this->turunkanUnitDariDepartemen($baru);
        }

        $divisiEfektif = array_key_exists('Divisi_Id', $baru) ? $baru['Divisi_Id'] : $lama->Divisi_Id;
        $subEfektif = array_key_exists('Sub_Divisi_Id', $baru) ? $baru['Sub_Divisi_Id'] : $lama->Sub_Divisi_Id;
        $this->pastikanUnitSah($divisiEfektif, $subEfektif);

        foreach (['Entitas_Pemilik_Id' => 'pemilik', 'Entitas_Lingkup_Id' => 'ruang lingkup'] as $kolom => $peran) {
            $this->pastikanEntitasSah(
                array_key_exists($kolom, $baru) ? $baru[$kolom] : $lama->{$kolom},
                $peran,
            );
        }

        $this->pastikanApqcSesuaiTipe(
            $tipeBaru,
            array_key_exists('APQC_Id', $baru) ? $baru['APQC_Id'] : $lama->APQC_Id,
        );

        /* Kombinasi yang BERLAKU setelah perubahan — bukan yang lama. Memeriksa
           kembar terhadap kombinasi lama akan meloloskan nomor yang bentrok di
           kombinasi barunya, dan sebaliknya. */
        $grupEfektif = [];
        foreach (self::KOLOM_GRUP_NOMOR as $kolom) {
            $grupEfektif[$kolom] = array_key_exists($kolom, $baru) ? $baru[$kolom] : $lama->{$kolom};
        }
        $this->pastikanNoDokumenUnik(
            array_key_exists('No_Dokumen', $baru) ? $baru['No_Dokumen'] : $lama->No_Dokumen,
            $grupEfektif,
            $idDokumen,
        );

        /* Divisi selalu diturunkan ulang dari Departemen yang berlaku. Ia kolom
           turunan sejak 22 Agu 2026, jadi membiarkan nilai lama bertahan setelah
           Departemennya berganti akan menyimpan pasangan yang tak pernah ada. */
        if (array_key_exists('Sub_Divisi_Id', $baru)) {
            $baru['Divisi_Id'] = $this->divisiTurunan($divisiEfektif, $subEfektif);
        }

        /* Diperiksa HANYA bila nomornya berubah. Sudah ada 9 kelompok dokumen
           bernomor kembar sebelum aturan ini dipasang; menolak mereka pada
           penyuntingan biasa berarti mengunci admin dari satu-satunya layar
           tempat kembarannya bisa dibereskan. */
        if (trim((string) $baru['Nomor_SOP_Resmi']) !== trim((string) $lama->Nomor_SOP_Resmi)) {
            $this->pastikanNomorUnik($baru['Nomor_SOP_Resmi'], $idDokumen);
        }

        DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)->update($baru + [
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        $this->sinkronIdentitasKeItem($idDokumen, $userId);

        /* Jejak audit menyebut NAMA, bukan id. "Jenis dokumen: 2 → 3" tidak
           menjelaskan apa pun kepada pembaca riwayat setahun kemudian, dan
           id-nya sendiri sudah tersimpan di barisnya. */
        $beda = $this->bedaNilai(
            $this->tampilanTataKelola((array) $lama),
            $this->tampilanTataKelola($baru),
        );
        $this->jejak(
            'DOK_UBAH',
            $this->kalimatBeda('Metadata dokumen', $beda),
            $idDokumen,
            $userId,
            null,
            ['perubahan' => $beda],
        );

        /* Identitas baru berarti nomor baru. Dirakit SESUDAH jejak audit
           supaya riwayat menyebut apa yang diubah manusia, bukan akibat
           turunannya. */
        $this->segarkanNomorResmi($idDokumen, $userId);

        return true;
    }

    /**
     * Tambah versi pada DOKUMEN — tanpa perlu item mana pun.
     *
     * Inilah inti konsep "Master SOP mengurus SOP utuh": berkas, masa berlaku,
     * dan pengesahan ditetapkan di master, lalu Paket SOP tinggal memakainya.
     * Sebelum ini versi wajib menempel pada item, sehingga SOP mustahil
     * disiapkan sebelum dipakai — urutan yang justru dibalik oleh alur ini.
     *
     * Baris versi ditulis dengan Id_SOP_Paket_Item NULL (butuh DDL 28) dan
     * Versi_Ke dihitung per DOKUMEN, sumbu yang sudah dipasang sejak Fase 2.
     */
    public function tambahVersi(int $idDokumen, array $data, int $userId): ?int
    {
        $dok = DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)->first(['Id_SOP_Dokumen', 'Tipe']);
        if (! $dok) {
            return null;
        }

        $this->pastikanFieldSesuaiTipe((string) $dok->Tipe, $data);

        /* Baku masa berlaku dilengkapi DI SINI, bukan hanya di layar: wizard
           bukan satu-satunya pintu ke jalur ini, dan aturan yang cuma dipasang
           di satu dari dua pintu setara adalah cacat yang sudah berulang di
           modul ini. Isian yang sudah ada tak pernah ditimpa. */
        $data = app(SopMasaBerlakuService::class)->lengkapiBaku($idDokumen, $data);

        /* PENOMORAN. Otomatis melanjutkan dari yang terakhir; versi PERTAMA
           sebuah dokumen bernomor 0 — ditampilkan sebagai "v00".
           (MAX ?? -1) + 1, bukan (MAX ?? 0) + 1: yang kedua membuat dokumen
           baru mulai dari 1 dan nomor 0 tak pernah terpakai sama sekali.

           Manual boleh melompat maupun mundur; yang tak boleh cuma kembar,
           sebab unique index terfilter (Id_SOP_Dokumen, Versi_Ke) menolaknya
           dengan galat 2601 yang tak menjelaskan apa-apa ke admin. Karena itu
           bentroknya ditangkap di sini, bukan dibiarkan sampai ke basis data. */
        /* NOMORNYA DIHITUNG DI BAWAH KUNCI DOKUMEN, bukan di sini.
           "Baca MAX lalu insert" tanpa kunci punya jendela balapan: dua admin
           yang menekan Tambah Versi pada dokumen yang sama dalam jendela itu
           membaca angka yang sama, dan yang kedua ditolak indeks unik sebagai
           galat mentah 2601 — kesalahan 500, bukan pesan yang bisa dikerjakan.

           Kuncinya pada baris DOKUMEN, bukan pada tabel versi: yang harus
           diserialkan adalah "menambah versi pada dokumen ini", dan baris
           dokumennya satu-satunya benda yang dimiliki bersama oleh dua
           penambahan itu. */
        /* BLOK PENANDA TANGAN. Kolom tunggal di bawah menjadi CERMIN dari
           penanda tangan pertama, bukan masukan yang berdiri sendiri: register
           kepatuhan, ekspor, dan penyaring "TANPA_PENGESAHAN" semuanya membaca
           kolom itu, dan membiarkannya kosong padahal blok terisi akan membuat
           SOP yang jelas sudah disahkan terhitung belum. */
        $blok = $this->rapikanBlokPengesah($data['Pengesah'] ?? []);
        $utama = $blok[0] ?? null;

        /* Form kosong dirapikan SEBELUM transaksi: konten yang salah ditolak
           tanpa menyisakan versi setengah jadi. Revisi baru tak mewarisinya —
           sama seperti berkas utama yang dipilih ulang tiap versi. */
        $lampiranSvc = app(SopVersiLampiranService::class);
        $lampiran = $lampiranSvc->rapikan($data['Lampiran'] ?? []);
        unset($data['Lampiran']);

        $idVersi = DB::transaction(function () use ($idDokumen, $data, $userId, $blok, $utama, $lampiranSvc, $lampiran) {
            /* Kunci baris dokumennya lebih dulu; penambahan versi kedua atas
               dokumen yang sama menunggu di sini, lalu membaca MAX yang sudah
               memuat versi pertama. */
            DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)
                ->lockForUpdate()->first(['Id_SOP_Dokumen']);

            $next = $this->nomorVersiBerikut($idDokumen, $data['Versi_Ke'] ?? null);
            if ($next === null) {
                return null;   // nomor manual bentrok
            }

            $id = $this->tulisVersiBaru($idDokumen, $data, $userId, $next, $blok, $utama);
            if ($id !== null && $lampiran !== []) {
                $lampiranSvc->simpan((int) $id, $lampiran, $userId);
            }

            return $id;
        });

        if ($idVersi === null) {
            return null;
        }

        return $idVersi;
    }

    /**
     * Tulis baris versi baru + blok pengesah + jejaknya.
     *
     * Dipisah dari tambahVersi() semata agar seluruhnya berada DI DALAM satu
     * transaksi berkunci bersama perhitungan nomornya. Nomor yang dihitung di
     * luar transaksi yang menuliskannya adalah jendela balapan, dan yang kalah
     * balapan menerima galat 2601 mentah.
     */
    private function tulisVersiBaru(
        int $idDokumen, array $data, int $userId, int $next, array $blok, ?array $utama
    ): int {
        $idVersi = DB::table('N_SOP_Item_Versi')->insertGetId([
            'Id_SOP_Paket_Item' => null,
            'Id_SOP_Dokumen' => $idDokumen,
            'Versi_Ke' => $next,
            'Status' => SopStatus::PAKET_DRAFT,
            /* Tahap awal. Dibaca dari master supaya id-nya tak dihafal kode —
               ia berbeda di setiap environment. */
            'Tahap_Id' => DB::table('N_SOP_Master_Tahap_Versi')
                ->where('Kode_Tahap', 'DRAFTING')->value('Id_Tahap'),
            'Id_Master_Konten' => $data['Id_Master_Konten'] ?? null,
            'Id_Master_Ujian' => $data['Id_Master_Ujian'] ?? null,
            /* SALINAN nama ujian, bukan sumber kebenaran — nama resmi tetap
               milik HC Learn. Ditulis di sini karena inilah satu-satunya saat
               namanya diketahui: admin baru saja memilihnya dari daftar. Tanpa
               ini, kuis yang disiapkan di Master sebelum dipakai paket mana pun
               tak punya nama tersimpan sama sekali, dan layar cuma bisa
               menyebutnya "ujian tertaut". */
            'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
            'Ringkasan_Perubahan' => $data['Ringkasan_Perubahan'] ?? null,
            'Tanggal_Berlaku' => $data['Tanggal_Berlaku'] ?? null,
            /* Tanggal identitas proses WAJIB ikut pada jalur CREATE, bukan hanya
               UPDATE. Form dan controller sudah mengirimnya sejak DDL-2026-034,
               tetapi tanpa empat baris ini draft baru selalu menyimpan NULL;
               baru setelah dibuka dan disimpan ulang tanggalnya muncul. */
            'Tanggal_Permintaan' => $data['Tanggal_Permintaan'] ?? null,
            'Tanggal_Respon' => $data['Tanggal_Respon'] ?? null,
            'Tanggal_Akhir_Approval' => $data['Tanggal_Akhir_Approval'] ?? null,
            'Tanggal_Kadaluarsa' => $data['Tanggal_Kadaluarsa'] ?? null,
            'Tanggal_Review_Berikutnya' => $data['Tanggal_Review_Berikutnya'] ?? null,
            /* Cermin baris pertama blok; bila blok kosong, masukan tunggal
               masih diterima apa adanya — jalur Builder dan berkas memakainya. */
            'Disahkan_Oleh' => $utama['Nama'] ?? ($data['Disahkan_Oleh'] ?? null),
            'Jabatan_Pengesah' => $utama['Jabatan'] ?? ($data['Jabatan_Pengesah'] ?? null),
            /* RUJUKAN, pendamping snapshot di atas. Dua kolom sebelumnya tetap
               yang ditampilkan: kalau nama dan jabatan ditarik lewat join saat
               dibaca, SOP yang sudah sah akan berubah sendiri catatannya
               begitu pengesahnya naik jabatan atau resign. */
            'Kode_Karyawan_Pengesah' => $utama['Kode_Karyawan'] ?? ($data['Kode_Karyawan_Pengesah'] ?? null),
            'Tanggal_Pengesahan' => $data['Tanggal_Pengesahan'] ?? null,
            'Nomor_Pengesahan' => $data['Nomor_Pengesahan'] ?? null,
            /* Sifat revisi. Default MAYOR ('Y'): kalau ragu, membaca ulang
               itu murah, sedangkan melewatkan revisi yang penting tidak.
               Admin yang lupa memilih tak boleh menghasilkan diam-diam
               "tak usah baca". */
            'Wajib_Baca_Ulang' => ($data['Wajib_Baca_Ulang'] ?? 'Y') === 'T' ? 'T' : 'Y',
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Item_Versi');

        $idVersi = (int) $idVersi;

        $this->simpanBlokPengesah($idVersi, $blok, $userId);

        $this->jejak(
            'VERSI_BUAT',
            'Versi '.str_pad((string) $next, 2, '0', STR_PAD_LEFT).' dibuat sebagai draft'
                .($blok === [] ? ' tanpa pengesah.' : ' dengan '.count($blok).' pengesah.'),
            $idDokumen,
            $userId,
            $idVersi,
            ['versi_ke' => $next, 'pengesah' => array_column($blok, 'Nama')],
        );

        return $idVersi;
    }

    /**
     * Kembalikan versi lama menjadi versi yang berlaku (rollback).
     *
     * KENAPA BUKAN "TERBITKAN LAGI". Sampai sebelum ini, versi OBSOLETE ikut
     * menawarkan tombol terbit, dan menekannya memang memundurkan pointer —
     * tapi lewat jalur publish, yang mencatat jejaknya sebagai "versi baru
     * diterbitkan" dan melewati rekonsiliasi. Jejak audit yang menyatakan
     * kebalikan dari yang terjadi lebih buruk daripada tak ada jejak.
     *
     * Jalur ini memakai SopItemVersionService::rollback() yang memang dibuat
     * untuknya: fan-out pointer ke semua item perujuk, rekonsiliasi beralasan
     * VERSI_ROLLBACK, dan catatan audit yang menyebut cakupannya.
     *
     * KONSEKUENSINYA BERAT DAN HARUS DISENGAJA (keputusan produk 2026-08-09):
     * satu rollback membuka ulang kewajiban karyawan di SEMUA paket perujuk.
     * Itulah sebabnya pemanggilnya wajib memperlihatkan dampaknya lebih dulu.
     *
     * @throws \DomainException
     */
    /**
     * Versi PENGGANTI yang bisa dipilih saat membatalkan versi terbit.
     *
     * Diurut dari terbaru ke terlama — yang paling mungkin dipilih ada di
     * paling atas. Versi batal tak ikut: ia bukan sesuatu yang bisa
     * dikembalikan menjadi berlaku.
     *
     * @return array<int, array>
     */
    public function kandidatPengganti(int $idVersi): array
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Id_SOP_Dokumen', 'Versi_Ke']);
        if (! $versi || $versi->Id_SOP_Dokumen === null) {
            return [];
        }

        return DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)
            ->where('Id_SOP_Item_Versi', '<>', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Status', SopStatus::VERSI_OBSOLETE)
            ->orderByDesc('Versi_Ke')
            ->get(['Id_SOP_Item_Versi', 'Versi_Ke', 'Tanggal_Berlaku', 'Tanggal_Publish', 'Disahkan_Oleh'])
            ->map(fn ($v) => [
                'Id_SOP_Item_Versi' => Hashids::connection('custom')->encode($v->Id_SOP_Item_Versi),
                'Versi_Ke' => (int) $v->Versi_Ke,
                'Tanggal_Berlaku' => $v->Tanggal_Berlaku,
                'Tanggal_Publish' => $v->Tanggal_Publish,
                'Disahkan_Oleh' => $v->Disahkan_Oleh,
            ])->values()->all();
    }

    /**
     * Batalkan versi yang SUDAH TERBIT, lalu kembalikan versi penggantinya.
     *
     * KENAPA ADA. Admin menerbitkan versi lalu sadar berkasnya salah. Tanpa
     * jalur ini satu-satunya obat adalah menerbitkan revisi pengganti — dan
     * revisi itu mengonsumsi satu nomor untuk kekeliruan berumur beberapa
     * menit, sementara yang keliru menjadi OBSOLETE dan nomornya terkunci
     * selamanya.
     *
     * BATASNYA DITEGAKKAN DI TAHAP, BUKAN DI SINI. Syarat BELUM_BEREDAR pada
     * tahap CANCEL (DML-2026-064) yang menolak pembatalan versi yang sudah
     * sampai ke orang; pindah() memeriksanya bersama syarat lain. Menuliskan
     * ulang aturannya di sini berarti dua rumus untuk satu pertanyaan.
     *
     * @param  int|null  $idPengganti  versi yang dikembalikan menjadi berlaku;
     *                                 null = dokumen ditinggalkan tanpa versi berlaku
     * @return string|null pesan galat, atau null bila berhasil
     */
    public function batalkanVersiTerbit(int $idVersi, int $userId, ?int $idPengganti = null): ?string
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Id_SOP_Item_Versi', 'Id_SOP_Dokumen', 'Status', 'Versi_Ke']);

        if (! $versi || $versi->Id_SOP_Dokumen === null) {
            return 'Versi tidak ditemukan.';
        }
        if ($versi->Status !== SopStatus::VERSI_PUBLISHED) {
            return 'Jalur ini hanya untuk versi yang sedang berlaku. '
                .'Draft dibatalkan lewat wizardnya, dan versi arsip sudah berakhir.';
        }

        /* Pengganti harus milik dokumen yang sama. Tanpa syarat ini, satu
           permintaan bisa memindahkan versi dokumen LAIN menjadi berlaku di
           sini — dan pointer yang menyeberang dokumen adalah kerusakan yang
           tak terlihat sampai ada yang membuka isinya. */
        if ($idPengganti !== null) {
            $sah = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idPengganti)
                ->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->where('Status', SopStatus::VERSI_OBSOLETE)
                ->exists();
            if (! $sah) {
                return 'Versi pengganti tak dikenali pada dokumen ini.';
            }
        }

        try {
            return DB::transaction(function () use ($versi, $idVersi, $userId, $idPengganti) {
                DB::table($this->table)->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)
                    ->lockForUpdate()->first(['Id_SOP_Dokumen']);

                /* Pointer dilepas LEBIH DULU. FK_N_SOP_Dokumen_Versi_Aktif
                   (DDL-2026-062) tak menghalangi, tapi meninggalkan pointer
                   menunjuk versi batal adalah keadaan yang setiap pembaca
                   laporan harus menebak artinya. */
                DB::table($this->table)->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)
                    ->update(['Id_Versi_Aktif' => null, 'Updated_By' => $userId, 'Updated_At' => now()]);

                app(SopTahapVersiService::class)->pindah($idVersi, 'CANCEL', $userId);

                if ($idPengganti !== null) {
                    $this->kembalikanVersi($idPengganti, $userId);
                } else {
                    /* POINTER ITEM IKUT DILEPAS.

                       kembalikanVersi() menggeser pointer setiap item perujuk
                       ke versi pengganti; tanpa pengganti tak ada yang
                       menggesernya, dan item paket tetap menunjuk versi yang
                       baru saja dibatalkan. Paketnya lalu terbit membawa versi
                       mati sebagai isinya — kerusakan yang tak terlihat sampai
                       ada karyawan membuka dokumennya.

                       NULL adalah keadaan yang sah dan sudah dikenal kolomnya:
                       artinya sama dengan pointer dokumen yang barusan
                       dikosongkan — belum ada versi yang berlaku. */
                    DB::table('N_SOP_Paket_Item')
                        ->whereIn('Id_SOP_Paket_Item', app(SopDokumenFanoutService::class)
                            ->itemPerujuk((int) $versi->Id_SOP_Dokumen))
                        ->update(['Id_Item_Versi_Aktif' => null, 'Updated_By' => $userId, 'Updated_At' => now()]);
                }

                /* Segmen Revisi ikut bergerak: nomor resmi dokumen sekarang
                   menyebut versi pengganti — atau berlubang bila tak ada. */
                $this->segarkanNomorResmi((int) $versi->Id_SOP_Dokumen, $userId);

                $this->jejak(
                    'VERSI_BATAL_TERBIT',
                    'Versi '.str_pad((string) $versi->Versi_Ke, 2, '0', STR_PAD_LEFT)
                        .' yang sedang berlaku dibatalkan sebelum beredar'
                        .($idPengganti !== null ? ', versi sebelumnya dikembalikan.' : '; dokumen tak lagi punya versi berlaku.'),
                    (int) $versi->Id_SOP_Dokumen,
                    $userId,
                    $idVersi,
                    ['versi_ke' => (int) $versi->Versi_Ke, 'pengganti' => $idPengganti],
                );

                return null;
            });
        } catch (\DomainException $e) {
            /* Termasuk penolakan syarat BELUM_BEREDAR — pesannya sudah ditulis
               untuk admin, jadi diteruskan apa adanya. */
            return $e->getMessage();
        }
    }

    public function kembalikanVersi(int $idVersi, int $userId): bool
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Id_SOP_Item_Versi', 'Id_SOP_Dokumen', 'Status']);
        if (! $versi || $versi->Id_SOP_Dokumen === null) {
            return false;
        }

        if ($versi->Status === SopStatus::PAKET_PUBLISHED) {
            throw new \DomainException('Versi ini memang sedang berlaku, jadi tak ada yang perlu dikembalikan.');
        }
        if ($versi->Status === SopStatus::PAKET_DRAFT) {
            throw new \DomainException(
                'Versi draft belum pernah berlaku, jadi bukan dikembalikan melainkan diterbitkan. '
                .'Pakai Pratinjau terbit.');
        }

        /* Isinya tetap wajib sah. Versi lama bisa saja berkasnya sudah dicabut
           dari Library sejak ia dipensiunkan, dan mengembalikannya berarti
           menyajikan SOP kosong ke karyawan. */
        $this->pastikanIsiSah($idVersi, terbitBaru: false);

        app(SopItemVersionService::class)->rollback($idVersi, $userId);

        /* Pointer dokumen ikut dikembalikan. rollback() menggeser pointer ITEM
           tapi tak menyentuh Id_Versi_Aktif dokumennya — lubang yang sama
           dengan yang pernah ada di jalur publish, dan akibatnya sama:
           dokumen menyebut versi lain yang berlaku daripada yang disajikan. */
        DB::table($this->table)->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)->update([
            'Id_Versi_Aktif' => $idVersi,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        /* Segmen Revisi ikut mundur bersama pointernya. */
        $this->segarkanNomorResmi((int) $versi->Id_SOP_Dokumen, $userId);

        /* Rekonsiliasi TIDAK dipanggil di sini: rollback() sudah
           melakukannya sendiri dengan alasan VERSI_ROLLBACK. Memanggilnya
           lagi berarti menyisir karyawan yang sama dua kali untuk satu
           peristiwa. */

        return true;
    }

    /**
     * Versi kosong atau salah jenis tak boleh diterbitkan.
     *
     * Karyawan akan membuka SOP yang tak berisi apa-apa, atau pemutar video
     * yang isinya PDF — kegagalan yang baru ketahuan di sisi mereka, jauh
     * setelah admin merasa pekerjaannya selesai.
     *
     * Melempar, bukan mengembalikan false: pemanggilnya sudah memakai false
     * untuk arti lain ("dokumen tak ditemukan"), dan menyatukan keduanya
     * membuat admin menerima pesan yang salah tentang apa yang perlu ia
     * perbaiki. Kedua pintu penerbitan sudah menangkap DomainException.
     *
     * @param  bool  $terbitBaru  false untuk "kembalikan versi": versi itu pernah
     *                            sah berlaku, jadi aturan yang lahir sesudahnya
     *                            (contoh pengisian FORM wajib PDF) tidak ditagih
     *                            surut.
     *
     * @throws \DomainException
     */
    private function pastikanIsiSah(int $idVersi, bool $terbitBaru = true): void
    {
        $v = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->leftJoin('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->first(['v.Id_Master_Konten', 'v.Id_Master_Ujian', 'd.Tipe', 'k.Tipe_Konten', 'b.Type_File', 'b.File_Path']);

        if (! $v) {
            return;
        }   // ketiadaannya diurus pemanggil

        if ($v->Tipe === SopTipeItem::KUIS) {
            if (empty($v->Id_Master_Ujian)) {
                throw new \DomainException(
                    'Versi kuis ini belum menautkan ujian. Pilih ujian HC Learn dulu.');
            }

            return;
        }

        if (empty($v->Id_Master_Konten)) {
            throw new \DomainException(
                'Versi ini belum punya berkas. Pilih konten dari Library dulu.');
        }

        if (! SopTipeItem::kontenCocok($v->Tipe, $v->Tipe_Konten)) {
            throw new \DomainException(
                'Dokumen bertipe '.$v->Tipe.' tak bisa memakai berkas jenis "'
                .$v->Tipe_Konten.'". Pilih berkas yang sesuai tipenya.');
        }

        /* Contoh pengisian FORM wajib PDF (keputusan user 10 Okt 2026): hanya
           PDF yang bisa ditahan "hanya lihat" lewat pembaca aman. Jenis konten
           Library tak cukup — 'document' mencakup .docx/.xlsx juga. */
        if ($terbitBaru && $v->Tipe === SopTipeItem::FORM
            && ! SopAksesBerkas::adalahPdf($v->Type_File, $v->File_Path)) {
            throw new \DomainException(
                'Contoh pengisian formulir wajib berupa PDF. Ganti berkasnya dengan PDF; '
                .'form kosong untuk diisi karyawan (Word/Excel) dipasang sebagai lampiran.');
        }

        $this->pastikanAdaPengesah($idVersi);
    }

    /**
     * Versi tanpa blok penanda tangan tak boleh terbit.
     *
     * Yang membedakan dokumen TERKENDALI dari sekadar berkas adalah adanya
     * orang yang bertanggung jawab menandatanganinya. Versi yang terbit tanpa
     * pengesah adalah aturan kerja yang tak seorang pun akui — dan karyawan
     * tetap diminta mengkonfirmasi telah membacanya.
     *
     * DIPERIKSA DI pastikanIsiSah(), bukan di controller, karena penerbitan
     * punya dua pintu: Master Dokumen lewat controllernya, dan publish paket di
     * Builder yang memanggil terbitkanVersi() langsung. Aturan yang tinggal di
     * satu controller hanya menjaga satu pintu.
     *
     * TIDAK BERLAKU SURUT. Versi yang sudah PUBLISHED tak pernah melewati
     * metode ini lagi, dan memang tak boleh: 35 dari 37 versi terbit yang ada
     * saat aturan ini dipasang tidak punya pengesah, dan menolak mereka
     * belakangan berarti mencabut dokumen yang sedang dipakai orang bekerja.
     *
     * Dua sumber diperiksa karena keduanya sah: baris blok penanda tangan, dan
     * kolom cermin Disahkan_Oleh yang dipakai versi-versi lama sebelum bloknya
     * ada.
     *
     * @throws \DomainException
     */
    /** Field yang dimiliki tiap blok profil. */
    private const FIELD_BLOK = [
        SopTipeItem::BLOK_NOMOR_RESMI => ['Nomor_SOP_Resmi'],
        SopTipeItem::BLOK_PENGESAHAN => [
            'Nomor_Pengesahan', 'Disahkan_Oleh', 'Jabatan_Pengesah',
            'Tanggal_Pengesahan', 'Kode_Karyawan_Pengesah',
            /* Blok tanda tangan datang sebagai ARRAY dari Builder, bukan kolom
               skalar. Penjaga yang hanya mengenal skalar meninggalkan jalur
               "buat dokumen baru" di Builder terbuka lebar — pintu kedua yang
               justru paling sering dipakai. */
            'Pengesah',
        ],
        /* Form kosong dokumen FORM datang sebagai larik, seperti blok pengesah. */
        SopTipeItem::BLOK_LAMPIRAN => ['Lampiran'],
    ];

    /**
     * Tolak field milik blok yang tidak berlaku bagi tipe ini.
     *
     * Menyembunyikan field di layar saja tidak cukup: Builder, impor, dan
     * endpoint yang belum ada tetap bisa menuliskannya. Persis begitulah 47%
     * versi FORM mendapat nomor pengesahan yang tak pernah dibutuhkan,
     * sementara 0% punya nomor SOP resmi — bedanya cuma apa yang disodorkan
     * layar, bukan apa yang dibutuhkan.
     *
     * Nilai KOSONG dibiarkan lolos. Yang dilarang MENGISInya, bukan
     * menyebutnya: payload lama yang masih mengirim kunci kosong tidak boleh
     * ditolak, karena itu memutus jalur yang tak melakukan kesalahan apa pun.
     *
     * @throws \DomainException
     */
    private function pastikanFieldSesuaiTipe(string $tipe, array $data): void
    {
        foreach (self::FIELD_BLOK as $blok => $fields) {
            if (SopTipeItem::blokBerlaku($tipe, $blok)) {
                continue;
            }

            foreach ($fields as $f) {
                if (! array_key_exists($f, $data)) {
                    continue;
                }

                /* Array (blok tanda tangan) dan skalar diperiksa berbeda:
                   (string) pada array menghasilkan "Array" dan notice, sehingga
                   blok KOSONG pun akan tertuduh. */
                $terisi = is_array($data[$f])
                    ? $data[$f] !== []
                    : trim((string) $data[$f]) !== '';

                if ($terisi) {
                    throw new \DomainException(
                        'Field "'.$f.'" tidak berlaku untuk tipe '.$tipe.'.'
                    );
                }
            }
        }
    }

    /**
     * Jenis dokumen harus sah bagi tipe item yang sedang berlaku.
     *
     * DITEGAKKAN DI SINI, bukan di controller, dengan alasan yang sama seperti
     * pastikanNomorUnik(): dokumen lahir dari DUA pintu — Master Dokumen dan
     * Builder (buatDokumenBaru) — dan aturan yang tinggal di satu controller
     * hanya menjaga satu pintu.
     *
     * KEWAJIBAN mengisi TIDAK ada di sini. Itu urusan pintu manusia. Service
     * juga melayani backfill dan penyalinan paket, yang secara definisi tak
     * punya jawaban untuk field ini — menolaknya di sini akan mematikan jalur
     * yang sah demi menegakkan aturan yang bukan miliknya.
     *
     * @throws \DomainException
     */
    private function pastikanJenisSesuaiTipe(string $tipe, $jenisId): void
    {
        if ($jenisId === null || $jenisId === '') {
            return;
        }

        $svc = app(SopJenisDokumenService::class);
        $jenis = $svc->cari((int) $jenisId);

        if (! $jenis) {
            throw new \DomainException(
                'Jenis dokumen yang dipilih tidak ada atau sudah dinonaktifkan.');
        }

        if (! $svc->pasanganSah((int) $jenisId, $tipe)) {
            $sah = $svc->tipeSah((int) $jenisId);

            throw new \DomainException(
                'Jenis "'.$jenis->Nama_Jenis.'" tidak berlaku untuk tipe '.$tipe.'. '
                .($sah
                    ? 'Jenis ini hanya menerima tipe '.implode(', ', $sah).'.'
                    : 'Jenis ini belum punya satu pun tipe item yang sah — atur dulu di Master Jenis Dokumen.'));
        }
    }

    /* ── Nomor urut dokumen (No_Dokumen) ──────────────────────────────────
     *
     * BUKAN nomor versi. Nomor versi bergerak sepanjang siklus revisi di
     * N_SOP_Item_Versi; nomor dokumen ditetapkan sekali di master dan tidak ikut
     * naik saat versi baru terbit.
     *
     * Deretnya berjalan per KOMBINASI lima field identitas — jenis dokumen,
     * entitas pemilik, departemen pemilik, ruang lingkup, dan proses APQC.
     * Dokumen dengan kombinasi berbeda memulai deretnya sendiri dari 00.
     */

    /** Kolom pembentuk grup deret, dalam satu tempat supaya tak ada dua daftar. */
    private const KOLOM_GRUP_NOMOR = [
        'Jenis_Dokumen_Id', 'Entitas_Pemilik_Id', 'Departemen_Id',
        'Entitas_Lingkup_Id', 'APQC_Id',
    ];

    /**
     * Nomor berikutnya yang disarankan untuk sebuah kombinasi.
     *
     * MAX + 1, dan sengaja MENGHITUNG DOKUMEN NONAKTIF JUGA. Memakai "nomor
     * bebas terkecil" akan mendaur ulang nomor milik dokumen yang dinonaktifkan,
     * sehingga ada dua dokumen yang pernah menyandang nomor yang sama — dan
     * yang sudah tercetak di luar sistem tidak ikut berubah.
     *
     * Grup yang seluruh fieldnya kosong tetap grup yang sah: 40 dokumen lama
     * berada di sana, dan mereka memang satu deret bersama sampai
     * diklasifikasikan.
     *
     * DERETNYA MULAI DARI SATU. Nomor urut dokumen menjawab "dokumen keberapa
     * dalam kombinasi ini", dan tidak ada dokumen ke-nol. Ini berbeda dari
     * segmen terakhir nomor resmi — revisi — yang memang mulai dari nol karena
     * ia menghitung berapa kali dokumennya sudah direvisi, dan terbitan pertama
     * belum pernah direvisi. Dua segmen bersebelahan dengan dua aturan yang
     * berbeda; jangan menyamakan keduanya "supaya konsisten".
     */
    public function saranNoDokumen(array $grup): int
    {
        $maks = $this->kueriGrup($grup)->max('No_Dokumen');

        return $maks === null ? 1 : ((int) $maks) + 1;
    }

    /**
     * Dokumen terakhir yang terdaftar pada sebuah kombinasi identitas.
     */
    public function dokumenTerakhirGrup(array $grup, ?int $kecuali = null): ?object
    {
        return $this->kueriGrup($grup)
            ->whereNotNull('No_Dokumen')
            ->when($kecuali, fn ($q) => $q->where('Id_SOP_Dokumen', '<>', $kecuali))
            ->orderByDesc('No_Dokumen')
            ->first(['Id_SOP_Dokumen', 'Nomor_SOP_Resmi', 'Judul', 'No_Dokumen']);
    }

    /**
     * Nomor urut tak boleh kembar DALAM SATU KOMBINASI.
     *
     * Nomor yang berulang berhenti menjadi penanda: pertanyaan "dokumen nomor
     * 03 yang mana?" jadi punya lebih dari satu jawaban benar. Pesannya menyebut
     * judul pemakainya, mengikuti pastikanNomorUnik() untuk Nomor SOP resmi —
     * admin butuh tahu harus ke mana, bukan sekadar bahwa ia salah.
     *
     * TIDAK ada unique index di skema, dan itu disengaja: empat dari lima kolom
     * pembentuk grup nullable, sedangkan SQL Server memperlakukan NULL sebagai
     * nilai yang sama di unique index — 40 dokumen lama yang kelimanya kosong
     * akan saling bertabrakan sejak hari pertama.
     *
     * @throws \DomainException
     */
    private function pastikanNoDokumenUnik($nomor, array $grup, ?int $kecuali = null): void
    {
        if ($nomor === null || $nomor === '') {
            return;
        }

        $bentrok = $this->kueriGrup($grup)
            ->where('No_Dokumen', (int) $nomor)
            ->when($kecuali, fn ($q) => $q->where('Id_SOP_Dokumen', '<>', $kecuali))
            ->value('Judul');

        if ($bentrok !== null) {
            throw new \DomainException(
                'Nomor dokumen '.SopNomorDokumen::angka($nomor, SopNomorDokumen::LEBAR_NOMOR)
                .' sudah dipakai "'.$bentrok.'" pada kombinasi identitas yang sama. '
                .'Pakai nomor lain, atau ubah salah satu penentu kombinasinya.');
        }
    }

    /** Kueri dokumen yang berada pada kombinasi identitas yang sama. */
    private function kueriGrup(array $grup)
    {
        $q = DB::table($this->table);

        foreach (self::KOLOM_GRUP_NOMOR as $kolom) {
            $nilai = $grup[$kolom] ?? null;
            /* whereNull, bukan where(=, null): perbandingan dengan NULL di SQL
               tak pernah bernilai benar, sehingga grup "belum diklasifikasi"
               tak akan pernah menemukan anggotanya sendiri. */
            $nilai === null || $nilai === ''
                ? $q->whereNull($kolom)
                : $q->where($kolom, $nilai);
        }

        return $q;
    }

    /* ── Nomor dokumen gabungan ──────────────────────────────────────────── */

    /**
     * Terjemahkan id identitas menjadi tujuh segmen nomor.
     *
     * Sekali query per master, bukan sekali per segmen: pemanggil tunggal
     * (detail, pratinjau form) memang hanya butuh satu dokumen. Daftar TIDAK
     * memakai jalur ini — ia sudah memegang peta seluruh halaman, dan memanggil
     * ini per baris akan melahirkan lima query untuk tiap dokumen.
     *
     * @param  array  $identitas  kolom mentah N_SOP_Dokumen + 'Revisi'
     */
    public function segmenNomor(array $identitas): array
    {
        $id = fn ($k) => filled($identitas[$k] ?? null) ? (int) $identitas[$k] : null;

        $jenisId = $id('Jenis_Dokumen_Id');
        $apqcId = $id('APQC_Id');
        $depId = $id('Departemen_Id');

        return [
            'jenis' => $jenisId
                ? DB::table('N_SOP_Master_Jenis_Dokumen')
                    ->where('Id_SOP_Jenis_Dokumen', $jenisId)->value('Kode_Jenis_Dokumen')
                : null,
            /* Segmen PEMILIK memakai kode cetak (DDL-2026-056); segmen LINGKUP
               di bawah tetap memakai Kode_Entitas. Keduanya menunjuk master
               yang sama tetapi artinya berbeda: pemilik menyebut badan hukum,
               lingkup menyatakan cakupan — dan "seluruh entitas" bukan nama
               sebuah perusahaan. */
            'entitas' => EntitasService::kodePemilik(
                app(EntitasService::class)->cari((int) ($id('Entitas_Pemilik_Id') ?? 0), false)
            ),
            'departemen' => $depId ? app(DepartemenService::class)->kode($depId) : null,
            'lingkup' => app(EntitasService::class)->cari((int) ($id('Entitas_Lingkup_Id') ?? 0), false)?->Kode_Entitas,
            'apqc' => $apqcId
                ? DB::table('N_SOP_Master_APQC')->where('Id_SOP_APQC', $apqcId)->value('Kode_Proses')
                : null,
            'nomor' => $identitas['No_Dokumen'] ?? null,
            'revisi' => $identitas['Revisi'] ?? null,
        ];
    }

    /**
     * Tuliskan nomor rakitan ke Nomor_SOP_Resmi, lalu sebarkan ke item.
     *
     * NOMOR SOP RESMI *ADALAH* NOMOR RAKITAN — bukan nomor kedua di sebelahnya.
     * Karena itu ia disimpan, bukan sekadar dihitung saat ditampilkan: kolom
     * ini dibaca 45 berkas (monitoring, ekspor, repository, detail karyawan,
     * dataset kepatuhan), dan merakitnya hanya di layar admin akan membuat
     * seluruh sisanya kosong.
     *
     * DIPANGGIL DI SETIAP TITIK YANG MENGGESER SALAH SATU SEGMEN: saat dokumen
     * lahir, saat identitasnya diubah, dan saat versi berlaku berganti —
     * sebab segmen Revisi ikut bergerak di sana.
     *
     * TIDAK PERNAH MENGOSONGKAN. Selama nomornya belum bisa dirakit utuh,
     * nilai yang sudah ada dibiarkan apa adanya: 16 dokumen lama menyimpan
     * penomoran yang diketik tangan dan masih beredar di luar sistem.
     * Menimpanya dengan null berarti menghapus satu-satunya jejak nomor itu.
     *
     * @return ?string nomor yang tertulis, atau null bila belum bisa dirakit
     */
    public function segarkanNomorResmi(int $idDokumen, int $userId): ?string
    {
        $dok = DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)
            ->first(['Nomor_SOP_Resmi', 'Jenis_Dokumen_Id', 'Entitas_Pemilik_Id',
                'Departemen_Id', 'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen']);
        if (! $dok) {
            return null;
        }

        $nomor = $this->nomorLengkap((array) $dok + ['Revisi' => $this->revisiBerlaku($idDokumen)]);
        if ($nomor === null || $nomor === $dok->Nomor_SOP_Resmi) {
            return $nomor;
        }

        DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)->update([
            'Nomor_SOP_Resmi' => $nomor,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        /* Kolom item adalah cerminan dengan SATU penulis. Tanpa penyebaran ini,
           daftar paket dan monitoring akan menyebut nomor yang sudah tak
           dipakai lagi oleh dokumennya. */
        $this->sinkronIdentitasKeItem($idDokumen, $userId);

        return $nomor;
    }

    /** Nomor lengkap, atau null bila ada segmen yang belum terisi. */
    public function nomorLengkap(array $identitas): ?string
    {
        return SopNomorDokumen::rakit($this->segmenNomor($identitas));
    }

    /** Bentuk yang memperlihatkan lubangnya sebagai "??". */
    public function pratinjauNomor(array $identitas): string
    {
        return SopNomorDokumen::pratinjau($this->segmenNomor($identitas));
    }

    /**
     * Revisi yang berlaku bagi sebuah dokumen.
     *
     * Versi AKTIF, bukan versi terakhir: yang tercetak di dokumen resmi adalah
     * revisi yang sedang berlaku, sementara draft yang belum terbit belum
     * berlaku bagi siapa pun. Null bila belum ada satu pun versi terbit —
     * nomornya memang belum final saat itu.
     */
    public function revisiBerlaku(int $idDokumen): ?int
    {
        $v = DB::table($this->table.' as d')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
            ->where('d.Id_SOP_Dokumen', $idDokumen)
            ->value('v.Versi_Ke');

        return $v === null ? null : (int) $v;
    }

    /**
     * Departemen pemilik harus ada dan masih aktif.
     *
     * @throws \DomainException
     */
    private function pastikanDepartemenSah($departemenId): void
    {
        if ($departemenId === null || $departemenId === '') {
            return;
        }

        if (! app(DepartemenService::class)->ada((int) $departemenId)) {
            throw new \DomainException(
                'Departemen pemilik yang dipilih tidak ada atau sudah dinonaktifkan.');
        }
    }

    /**
     * Turunkan Sub_Divisi_Id dari binding departemen.
     *
     * Sejak DDL-2026-027 yang menjadi acuan kepemilikan adalah KODE DEPARTEMEN,
     * bukan sub-divisi. Sub-divisi tetap diisi bila departemennya punya binding,
     * karena jalur pelaporan yang menyambung karyawan lewat ID_Divisi_Sub_Divisi
     * — SopMonitoringPeopleService dan SopPenjadwalanTargetService — masih
     * membutuhkannya.
     *
     * Departemen tanpa binding meninggalkan sub-divisi KOSONG, bukan tebakan:
     * 35 dari 52 kode memang tak punya pasangan di struktur yang tersimpan.
     */
    private function turunkanUnitDariDepartemen(array $data): array
    {
        if (! array_key_exists('Departemen_Id', $data)) {
            return $data;
        }

        $dep = $data['Departemen_Id'];
        $sub = ($dep === null || $dep === '')
            ? null
            : app(DepartemenService::class)->subDivisiTerikat((int) $dep);

        $data['Sub_Divisi_Id'] = $sub;
        /* Divisi ikut dihitung ulang di sini, bukan dibiarkan memakai nilai
           lama. Membiarkannya menyimpan pasangan divisi-lama/sub-baru yang tak
           pernah ada di struktur — dan penjaga pasanganAda() akan menolak
           penyuntingan yang sebenarnya sah. */
        $data['Divisi_Id'] = $sub === null ? null : SopUnitKerja::divisiTunggal($sub);

        return $data;
    }

    /**
     * APQC harus sah bagi tipe item yang sedang berlaku.
     *
     * Mekanismenya sama persis dengan jenis dokumen, dan itu disengaja: pemetaan
     * APQC↔tipe item adalah DATA, sehingga admin yang menentukan APQC mana
     * berlaku untuk tipe apa — dan kewajiban mengisinya ikut turun dari situ,
     * tanpa satu baris kode pun berubah.
     *
     * @throws \DomainException
     */
    private function pastikanApqcSesuaiTipe(string $tipe, $apqcId): void
    {
        if ($apqcId === null || $apqcId === '') {
            return;
        }

        $svc = app(SopApqcService::class);
        $apqc = $svc->cari((int) $apqcId);

        if (! $apqc) {
            throw new \DomainException(
                'Proses APQC yang dipilih tidak ada atau sudah dinonaktifkan.');
        }

        if (! $svc->pasanganSah((int) $apqcId, $tipe)) {
            $sah = $svc->tipeSah((int) $apqcId);

            throw new \DomainException(
                'Proses APQC "'.$apqc->Kode_Proses.' '.$apqc->Nama_Proses.'" tidak berlaku untuk tipe '.$tipe.'. '
                .($sah
                    ? 'Proses ini hanya menerima tipe '.implode(', ', $sah).'.'
                    : 'Proses ini belum punya satu pun tipe item yang sah — atur dulu di Master APQC.'));
        }
    }

    /**
     * Unit pemilik harus benar-benar ada, dan sub-divisinya harus benar-benar
     * berada di bawah divisinya.
     *
     * Sub-divisi tanpa divisi ditolak: kepemilikan "milik sub-divisi X" tanpa
     * menyebut divisinya tidak bisa dibaca balik oleh laporan mana pun, karena
     * View_Divisi_Sub_Divisi menyimpan sub-divisi sebagai pasangan, bukan
     * sebagai entitas berdiri sendiri.
     *
     * @throws \DomainException
     */
    private function pastikanUnitSah($divisiId, $subDivisiId): void
    {
        $divisiId = $divisiId === '' ? null : $divisiId;
        $subDivisiId = $subDivisiId === '' ? null : $subDivisiId;

        if ($subDivisiId !== null && ! SopUnitKerja::subDivisiAda((int) $subDivisiId)) {
            throw new \DomainException('Departemen pemilik yang dipilih tidak ditemukan.');
        }

        if ($divisiId === null) {
            return;
        }

        if (! SopUnitKerja::divisiAda((int) $divisiId)) {
            throw new \DomainException('Divisi pemilik yang dipilih tidak ditemukan.');
        }

        if ($subDivisiId !== null && ! SopUnitKerja::pasanganAda((int) $divisiId, (int) $subDivisiId)) {
            throw new \DomainException(
                'Departemen itu bukan bagian dari divisi yang dipilih.');
        }
    }

    /**
     * Divisi yang disimpan bersama sebuah Departemen.
     *
     * Sejak 22 Agu 2026 admin hanya memilih Departemen; Divisi menjadi TURUNAN.
     * Nilainya diisi hanya bila departemen itu punya induk tunggal — 30 dari 32
     * departemen. Untuk dua yang bernaung di dua divisi sekaligus, kolomnya
     * dibiarkan NULL, bukan ditebak: menebak berarti menulis fakta yang tak
     * pernah dinyatakan siapa pun, lalu dibaca laporan sebagai kebenaran.
     *
     * Divisi yang dikirim eksplisit tetap dihormati — jalur backfill dan
     * penyalinan paket boleh membawa pasangannya sendiri.
     */
    private function divisiTurunan($divisiId, $subDivisiId)
    {
        if ($divisiId !== null && $divisiId !== '') {
            return $divisiId;
        }

        if ($subDivisiId === null || $subDivisiId === '') {
            return null;
        }

        return SopUnitKerja::divisiTunggal((int) $subDivisiId);
    }

    /**
     * Entitas harus ada dan masih aktif.
     *
     * KEWAJIBAN mengisi tidak ada di sini, sama seperti jenis dokumen dan unit
     * kerja: service ini juga melayani backfill dan penyalinan paket yang tak
     * punya jawaban untuk field ini.
     *
     * @throws \DomainException
     */
    private function pastikanEntitasSah($entitasId, string $peran): void
    {
        if ($entitasId === null || $entitasId === '') {
            return;
        }

        if (! app(EntitasService::class)->ada((int) $entitasId)) {
            throw new \DomainException(
                'Entitas '.$peran.' yang dipilih tidak ada atau sudah dinonaktifkan.');
        }
    }

    /**
     * Salin baris dengan id tata kelola diganti nama, khusus untuk jejak audit.
     * Kolom yang tak ada dibiarkan apa adanya.
     */
    private function tampilanTataKelola(array $baris): array
    {
        if (array_key_exists('Jenis_Dokumen_Id', $baris)) {
            $jenis = $baris['Jenis_Dokumen_Id']
                ? app(SopJenisDokumenService::class)->cari((int) $baris['Jenis_Dokumen_Id'], false)
                : null;
            $baris['Jenis_Dokumen_Id'] = $jenis->Nama_Jenis ?? null;
        }

        if (array_key_exists('Departemen_Id', $baris)) {
            $baris['Departemen_Id'] = app(DepartemenService::class)->kode(
                ($baris['Departemen_Id'] ?? null) ? (int) $baris['Departemen_Id'] : null,
            );
        }

        if (array_key_exists('APQC_Id', $baris)) {
            $a = ($baris['APQC_Id'] ?? null)
                ? app(SopApqcService::class)->cari((int) $baris['APQC_Id'], false)
                : null;
            $baris['APQC_Id'] = $a ? $a->Kode_Proses.' '.$a->Nama_Proses : null;
        }

        foreach (['Entitas_Pemilik_Id', 'Entitas_Lingkup_Id'] as $kolom) {
            if (array_key_exists($kolom, $baris)) {
                $baris[$kolom] = app(EntitasService::class)->label(
                    ($baris[$kolom] ?? null) ? (int) $baris[$kolom] : null,
                );
            }
        }

        if (array_key_exists('Divisi_Id', $baris) || array_key_exists('Sub_Divisi_Id', $baris)) {
            $label = SopUnitKerja::label(
                ($baris['Divisi_Id'] ?? null) ? (int) $baris['Divisi_Id'] : null,
                ($baris['Sub_Divisi_Id'] ?? null) ? (int) $baris['Sub_Divisi_Id'] : null,
            );
            unset($baris['Sub_Divisi_Id']);
            $baris['Divisi_Id'] = $label;
        }

        return $baris;
    }

    /** Tipe item pemilik sebuah versi. */
    private function tipeVersi(int $idVersi): string
    {
        return (string) DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->value('d.Tipe');
    }

    private function pastikanAdaPengesah(int $idVersi): void
    {
        /* Tipe yang tidak mengenal pengesahan lolos lebih awal.
         *
         * Tanpa gerbang ini, FORM/VIDEO/KUIS jadi mustahil diterbitkan begitu
         * blok tanda tangannya dibersihkan: penjaga menuntut sesuatu yang
         * profil tipenya sendiri nyatakan tidak berlaku. Pesannya pun keliru —
         * ia bicara soal "dokumen terkendali" kepada orang yang sedang
         * menerbitkan video. */
        $tipe = $this->tipeVersi($idVersi);

        /* WAJIB, bukan sekadar berlaku: FORM menerima blok pengesahan sejak
           5 Okt 2026, tetapi tak wajib mengisinya. */
        if ($tipe !== '' && ! SopTipeItem::blokWajib($tipe, SopTipeItem::BLOK_PENGESAHAN)) {
            return;
        }

        /* Kolom Jenis dipertahankan sebagai kompatibilitas DDL-2026-039;
           seluruh jalur final menulis REALISASI. */
        $adaBlok = DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Jenis', 'REALISASI')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->exists();

        if ($adaBlok) {
            return;
        }

        $cermin = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->value('Disahkan_Oleh');

        if (trim((string) $cermin) !== '') {
            return;
        }

        throw new \DomainException(
            'Versi ini belum punya pengesah. Isi blok penanda tangan '
            .'(Disiapkan/Diperiksa/Disetujui/Disahkan oleh) sebelum menerbitkannya — '
            .'dokumen terkendali harus punya orang yang bertanggung jawab atasnya.');
    }

    /**
     * Ubah versi yang masih DRAFT.
     *
     * HANYA DRAFT, dan itu bukan kehati-hatian berlebihan. Versi PUBLISHED
     * adalah catatan yang sudah diteken dan sudah dibaca karyawan; mengubah
     * isinya membuat rekam kepatuhan mereka berbunyi "selesai membaca v03"
     * sementara v03 diam-diam menjadi dokumen lain. Versi OBSOLETE lebih parah:
     * mengeditnya menulis ulang sejarah, dan auditor yang bertanya "apa yang
     * dibaca karyawan bulan lalu" dijawab dengan karangan belakangan.
     *
     * Untuk memperbaiki versi yang sudah terbit, jalannya menerbitkan revisi
     * MINOR — koreksinya tercatat alih-alih disembunyikan, dan karyawan yang
     * sudah selesai tetap terhitung selesai.
     *
     * @return string|null null bila berhasil; kalau gagal, alasannya
     */
    public function ubahVersi(int $idVersi, array $data, int $userId): ?string
    {
        $this->pastikanFieldSesuaiTipe($this->tipeVersi($idVersi), $data);

        /* Seluruh kolom yang bisa disunting ikut dibaca, bukan hanya yang
           dipakai validasi: sesudah update nilai lamanya lenyap, dan draft yang
           diubah tanpa jejak adalah tepat celah yang riwayat ini ada untuk
           menutupnya. */
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first([
                'Id_SOP_Item_Versi', 'Id_SOP_Dokumen', 'Status', 'Versi_Ke',
                'Id_Master_Konten', 'Id_Master_Ujian', 'Nama_Ujian', 'Ringkasan_Perubahan',
                'Tanggal_Berlaku',
                'Tanggal_Permintaan', 'Tanggal_Respon', 'Tanggal_Akhir_Approval',
                'Tanggal_Kadaluarsa', 'Tanggal_Review_Berikutnya', 'Tanggal_Obsolete',
                'Tanggal_Pengesahan', 'Nomor_Pengesahan',
                'Updated_At', 'Created_At',
            ]);

        if (! $versi || $versi->Id_SOP_Dokumen === null) {
            return 'Versi tidak ditemukan.';
        }

        /* Sama alasannya dengan tambahVersi(). Diletakkan sesudah $versi
           terbaca karena id dokumennya datang dari baris itu. */
        $data = app(SopMasaBerlakuService::class)
            ->lengkapiBaku((int) $versi->Id_SOP_Dokumen, $data);
        /* IZIN PER FIELD, BUKAN PER VERSI.
           Sampai 30 Agu 2026 aturannya hanya "DRAFT boleh, selain itu tidak" —
           kasarnya tepat, tapi salah di dua arah. Versi terbit terkunci TOTAL,
           padahal memperpanjang masa berlaku, menggeser jadwal review,
           membetulkan ringkasan, atau memperbaiki salah ketik pengesah tak
           melahirkan dokumen yang berbeda; memaksanya lewat revisi baru hanya
           melahirkan versi yang isinya sama persis. Sebaliknya versi batal LOLOS,
           sebab Status-nya memang masih DRAFT.

           Daftarnya dijawab SopVersiKeadaan — satu tempat, dibaca penjaga ini
           dan dikirim ke layar, sehingga layar tak menawarkan isian yang server
           tolak. */
        $tahap = $this->tahapVersiRow($idVersi);
        $terbit = $versi->Status === SopStatus::VERSI_PUBLISHED;
        $sudahDijadwalkan = $terbit && $this->dokumenSudahDijadwalkan((int) $versi->Id_SOP_Dokumen);
        $izin = SopVersiKeadaan::fieldBolehUbah($versi->Status, $tahap, $sudahDijadwalkan);

        if ($izin === []) {
            return SopVersiKeadaan::alasanTerkunci($versi->Status, $tahap);
        }

        /* Isi versi terbit terkunci karena SUDAH ADA yang dijadwalkan
           membacanya — sebut itu, bukan sekadar "tak bisa diubah", supaya
           admin tahu kenapa versi yang kemarin masih bisa diganti kini tidak. */
        if ($sudahDijadwalkan) {
            foreach (SopVersiKeadaan::FIELD_ISI as $field) {
                if (property_exists($versi, $field) && array_key_exists($field, $data)
                    && $this->nilaiBerbeda($versi->{$field}, $data[$field])) {
                    return (self::LABEL_FIELD[$field] ?? $field).' tak bisa diganti lagi: dokumen ini sudah '
                        .'dijadwalkan ke karyawan lewat paket. Terbitkan versi baru untuk menggantinya.';
                }
            }
        }

        /* Arsip menerima tanggal saja; field lain ditolak dengan alasan
           ARSIPNYA, bukan dengan saran menerbitkan versi baru. */
        $alasanBeku = SopVersiKeadaan::bekuIsi($versi->Status, $tahap)
            ? SopVersiKeadaan::alasanTerkunci($versi->Status, $tahap)
            : null;

        /* FORM KOSONG adalah field semu seperti blok pengesah: tak punya
           kolom di baris versi, jadi izinnya ditanyakan di sini lalu kuncinya
           dibuang sebelum pemeriksaan per kolom. Daftar yang SAMA bukan
           perubahan — formulir selalu mengirim seluruh isian. */
        $lampiranSvc = app(SopVersiLampiranService::class);
        $lampiranBaru = null;
        $lampiranLama = [];
        if (array_key_exists('Lampiran', $data)) {
            /* Daftar SAMA dibandingkan sebelum divalidasi: berkas yang sudah
               terpasang lalu tak lagi lolos validasi (Scope atau jenisnya
               berubah di Library) tak boleh membuat setiap simpanan versi ini
               gagal, padahal yang diubah admin hanya tanggal. */
            $lampiranLama = $lampiranSvc->aktif($idVersi);
            $kiriman = array_map(
                fn ($x) => (int) (is_array($x) ? ($x['Id_Master_Konten'] ?? 0) : $x),
                array_values(is_array($data['Lampiran']) ? $data['Lampiran'] : []),
            );
            if ($kiriman === $lampiranLama) {
                $lampiranBaru = null;
            } elseif (! in_array('Lampiran', $izin, true)) {
                return $sudahDijadwalkan
                    ? 'Form kosong tak bisa diganti lagi: dokumen ini sudah dijadwalkan ke karyawan lewat '
                        .'paket. Terbitkan versi baru untuk menggantinya.'
                    : ($alasanBeku ?? SopVersiKeadaan::alasanTerkunci($versi->Status, $tahap));
            } else {
                $lampiranBaru = $lampiranSvc->rapikan($data['Lampiran']);
            }
            unset($data['Lampiran']);
        }

        if ($galat = $this->periksaIzinField($versi, $data, $izin, $alasanBeku)) {
            return $galat;
        }

        /* TULIS-BERSAMAAN. Pemanggil boleh mengirim cap waktu yang ia BACA;
           kalau barisnya sudah bergeser sejak itu, simpanannya ditolak alih-alih
           menimpa pekerjaan orang lain tanpa satu pun tanda. Wizard versi
           berumur lima langkah — berkas, matriks pengesah, tiga tanggal — jadi
           jendelanya bukan hitungan detik.

           GAGAL KE ARAH "TERIMA" bila capnya tak dikirim. Jalur non-layar
           (backfill, penyalinan paket, perintah artisan) tak punya cap untuk
           dikirim, dan menolak mereka berarti mematikan jalur yang tak pernah
           berebut dengan siapa pun. Perlindungannya berlaku bagi yang ikut
           serta — dan yang ikut serta adalah satu-satunya layar tempat dua
           admin bisa bertemu di satu draft. */
        $cap = $data['Updated_At_Dibaca'] ?? null;
        if (filled($cap) && ! $this->capMasihSama($this->capVersi($versi), (string) $cap)) {
            return 'Versi ini baru saja diubah orang lain. Muat ulang halaman dulu supaya '
                .'perubahan mereka tidak tertimpa, lalu ulangi suntingan Anda.';
        }

        /* Nomor boleh diganti selama draft. Bentroknya diperiksa dengan
           MENGECUALIKAN baris ini sendiri — tanpa itu, menyimpan tanpa mengubah
           nomor pun akan ditolak sebagai kembar dengan dirinya sendiri. */
        $nomor = $data['Versi_Ke'] ?? null;
        if ($nomor !== null && $nomor !== '' && (int) $nomor !== (int) $versi->Versi_Ke) {
            /* Saringan hidup, sama dengan nomorVersiBerikut() dan indeks unik
               terfilter di basis data. Tanpa ini nomor yang sudah dibebaskan
               indeks tetap ditolak layar, dan admin menghadapi penolakan yang
               tak bisa ia jelaskan: angkanya kelihatan bebas, tapi ada. */
            $bentrok = SopNomorVersi::terpakai(
                (int) $versi->Id_SOP_Dokumen, (int) $nomor, $idVersi);
            if ($bentrok) {
                return 'Versi '.str_pad((string) (int) $nomor, 2, '0', STR_PAD_LEFT)
                    .' sudah ada pada dokumen ini. Pilih nomor lain.';
            }
        }

        $blok = $this->rapikanBlokPengesah($data['Pengesah'] ?? []);
        $utama = $blok[0] ?? null;

        // Blok lama dibaca SEBELUM transaksi menghapusnya.
        $pengesahLama = $this->petaPengesah($idVersi);

        $lampiranJejak = ['masuk' => [], 'keluar' => []];
        DB::transaction(function () use ($idVersi, $data, $userId, $nomor, $versi, $blok, $utama, $izin, $lampiranSvc, $lampiranBaru, &$lampiranJejak) {
            $ubah = [
                'Versi_Ke' => $nomor === null || $nomor === ''
                    ? $versi->Versi_Ke : (int) $nomor,
                /* DIPERTAHANKAN bila kuncinya tak dikirim, bukan dijadikan
                   null. Isi versi adalah satu-satunya kolom yang kehilangannya
                   membuat versi mustahil diterbitkan, dan permintaan yang lupa
                   menyertakannya dulu menghapusnya tanpa sepatah pun kabar.
                   Mengosongkannya tetap bisa — kirim kuncinya bernilai null. */
                'Id_Master_Konten' => array_key_exists('Id_Master_Konten', $data)
                    ? $data['Id_Master_Konten'] : $versi->Id_Master_Konten,
                'Id_Master_Ujian' => array_key_exists('Id_Master_Ujian', $data)
                    ? $data['Id_Master_Ujian'] : $versi->Id_Master_Ujian,
                'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
                'Ringkasan_Perubahan' => $data['Ringkasan_Perubahan'] ?? null,
                'Tanggal_Berlaku' => $data['Tanggal_Berlaku'] ?? null,
                /* Empat tanggal proses. Ketiganya yang dicap tahap tetap boleh
                   ditimpa di sini — pencapan hanya mengisi yang masih kosong,
                   dan kenyataan sering mendahului catatan. */
                'Tanggal_Permintaan' => $data['Tanggal_Permintaan'] ?? null,
                'Tanggal_Respon' => $data['Tanggal_Respon'] ?? null,
                'Tanggal_Akhir_Approval' => $data['Tanggal_Akhir_Approval'] ?? null,
                'Tanggal_Kadaluarsa' => $data['Tanggal_Kadaluarsa'] ?? null,
                'Tanggal_Review_Berikutnya' => $data['Tanggal_Review_Berikutnya'] ?? null,
                'Disahkan_Oleh' => $utama['Nama'] ?? ($data['Disahkan_Oleh'] ?? null),
                'Jabatan_Pengesah' => $utama['Jabatan'] ?? ($data['Jabatan_Pengesah'] ?? null),
                'Kode_Karyawan_Pengesah' => $utama['Kode_Karyawan'] ?? ($data['Kode_Karyawan_Pengesah'] ?? null),
                /* DIPERTAHANKAN bila kuncinya tak dikirim, bukan dijadikan null.
                   Sejak 2026-08-25 formulir versi berhenti meminta keduanya —
                   tanggalnya sudah dijawab Tanggal_Akhir_Approval. Dengan
                   `?? null`, menyunting draft lama akan MENGHAPUS nomor SK yang
                   tercetak di dokumen fisiknya, dan sembilan belas versi masih
                   menyimpannya. Mengosongkannya tetap bisa — kirim kuncinya
                   bernilai null, sama seperti Id_Master_Konten. */
                'Tanggal_Pengesahan' => array_key_exists('Tanggal_Pengesahan', $data)
                    ? $data['Tanggal_Pengesahan'] : $versi->Tanggal_Pengesahan,
                'Nomor_Pengesahan' => array_key_exists('Nomor_Pengesahan', $data)
                    ? $data['Nomor_Pengesahan'] : $versi->Nomor_Pengesahan,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ];

            /* HANYA KOLOM YANG DIIZINKAN yang benar-benar ditulis.
               Larik di atas dirakit lengkap dan sebagian besar isinya jatuh ke
               `?? null` — untuk DRAFT itu benar, sebab formulirnya memang
               mengirim seluruh isian. Untuk versi TERBIT, formulirnya parsial:
               menulis seluruh larik akan MENGOSONGKAN kolom yang tak dikirim,
               dan itu cacat yang berdiri sendiri, terpisah dari soal izinnya.

               Penjaga di depan sudah menolak perubahan yang tak diizinkan; ini
               menjaga yang tak diubah tetap utuh. */
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
                ->update($this->kolomYangBolehDitulis(
                    $ubah, $izin, $data, $versi->Status === SopStatus::PAKET_DRAFT));

            /* Blok ditulis ULANG, bukan ditambahi: menyunting berarti daftar
               penanda tangan yang dikirim adalah daftar yang dimaksud. Menambah
               saja akan meninggalkan baris yang sudah dihapus admin.

               Dilewati sama sekali bila blok pengesah tak boleh diubah —
               menghapus lalu menulis ulang daftar yang sama tetap menghapus
               baris yang seharusnya tak tersentuh. */
            if (in_array('Pengesah', $izin, true)) {
                DB::table('N_SOP_Item_Versi_Pengesah')
                    ->where('Id_SOP_Item_Versi', $idVersi)
                    ->where('Jenis', 'REALISASI')
                    ->delete();
                $this->simpanBlokPengesah($idVersi, $blok, $userId);
            }

            if ($lampiranBaru !== null) {
                $lampiranJejak = $lampiranSvc->simpan($idVersi, $lampiranBaru, $userId);
            }
        });

        /*
         * Nilai syarat tahap dilupakan setelah versinya berubah.
         *
         * SopTahapVersiService menyimpan hasil nilaiSyarat() selama satu
         * permintaan, dan isinya persis yang baru saja ditulis di atas:
         * REALISASI_PENGESAH, BERKAS, dan ketiga tanggal masa berlaku. Tanpa
         * baris ini, menyimpan lalu membaca alurVersi() dalam permintaan yang
         * sama menjawab dengan keadaan SEBELUM penyimpanan — wizard melaporkan
         * syarat yang sudah dipenuhi sebagai masih kurang, dan tombol majunya
         * tetap terkunci.
         *
         * Terbukti langsung: sesudah menyimpan pengesah, instance yang SEGAR
         * menjawab Syarat_Kurang = [] sementara instance yang sama menjawab
         * ["Realisasi pengesah"].
         *
         * lupakanNilai() sudah lama tersedia untuk ini; yang tidak ada adalah
         * pemanggilnya.
         */
        app(SopTahapVersiService::class)->lupakanNilai($idVersi);

        $beda = $this->bedaNilai((array) $versi, [
            'Versi_Ke' => $nomor === null || $nomor === '' ? $versi->Versi_Ke : (int) $nomor,
            'Id_Master_Konten' => array_key_exists('Id_Master_Konten', $data)
                ? $data['Id_Master_Konten'] : $versi->Id_Master_Konten,
            'Id_Master_Ujian' => array_key_exists('Id_Master_Ujian', $data)
                ? $data['Id_Master_Ujian'] : $versi->Id_Master_Ujian,
            'Nama_Ujian' => $data['Nama_Ujian'] ?? null,
            'Ringkasan_Perubahan' => $data['Ringkasan_Perubahan'] ?? null,
            'Tanggal_Berlaku' => $data['Tanggal_Berlaku'] ?? null,
            'Tanggal_Permintaan' => $data['Tanggal_Permintaan'] ?? null,
            'Tanggal_Respon' => $data['Tanggal_Respon'] ?? null,
            'Tanggal_Akhir_Approval' => $data['Tanggal_Akhir_Approval'] ?? null,
            'Tanggal_Kadaluarsa' => $data['Tanggal_Kadaluarsa'] ?? null,
            'Tanggal_Review_Berikutnya' => $data['Tanggal_Review_Berikutnya'] ?? null,
            'Tanggal_Pengesahan' => array_key_exists('Tanggal_Pengesahan', $data)
                ? $data['Tanggal_Pengesahan'] : $versi->Tanggal_Pengesahan,
            'Nomor_Pengesahan' => array_key_exists('Nomor_Pengesahan', $data)
                ? $data['Nomor_Pengesahan'] : $versi->Nomor_Pengesahan,
        ]);

        /* Pengesah dicatat sebagai MASUK dan KELUAR, bukan "daftar berubah".
           Pertanyaan auditnya selalu "siapa yang dicabut" — dan itu tak
           terjawab oleh dua daftar utuh yang harus dibandingkan sendiri. */
        $pengesahBaru = $this->petaPengesah($idVersi);
        $masuk = array_values(array_diff($pengesahBaru, $pengesahLama));
        $keluar = array_values(array_diff($pengesahLama, $pengesahBaru));

        /* Form kosong ikut daftar perubahan — termasuk bila yang berubah hanya
           urutannya — supaya kalimatnya tak berbunyi "tanpa perubahan nilai"
           tepat ketika form kosong baru saja ditambah. */
        if ($lampiranBaru !== null) {
            $beda = array_merge($beda, $this->bedaNilai(
                ['Lampiran' => implode(', ', $lampiranLama)],
                ['Lampiran' => implode(', ', array_column($lampiranBaru, 'Id_Master_Konten'))],
            ));
        }

        $label = 'Versi '.str_pad((string) $versi->Versi_Ke, 2, '0', STR_PAD_LEFT);
        $kalimat = $this->kalimatBeda($label, $beda);
        if ($masuk || $keluar) {
            $kalimat .= ' Pengesah: '.(count($masuk) ? '+'.count($masuk) : '')
                .(count($masuk) && count($keluar) ? ' ' : '')
                .(count($keluar) ? '−'.count($keluar) : '').'.';
        }
        if ($lampiranJejak['masuk'] || $lampiranJejak['keluar']) {
            $kalimat .= ' Form kosong: '.trim(
                ($lampiranJejak['masuk'] ? '+'.count($lampiranJejak['masuk']) : '').' '
                .($lampiranJejak['keluar'] ? '−'.count($lampiranJejak['keluar']) : '')).'.';
        }

        $this->jejak('VERSI_UBAH', $kalimat, (int) $versi->Id_SOP_Dokumen, $userId, $idVersi, [
            'perubahan' => $beda,
            'pengesah_masuk' => $masuk,
            'pengesah_keluar' => $keluar,
            'lampiran_masuk' => $lampiranJejak['masuk'],
            'lampiran_keluar' => $lampiranJejak['keluar'],
        ]);

        return null;
    }

    /**
     * Hapus versi DRAFT.
     *
     * Alasan yang sama dengan ubahVersi: yang sudah terbit adalah catatan, dan
     * catatan tak dihapus. Yang obsolete adalah sejarah.
     *
     * @return string|null null bila berhasil; kalau gagal, alasannya
     */
    public function hapusVersi(int $idVersi, int $userId): ?string
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Id_SOP_Item_Versi', 'Id_SOP_Dokumen', 'Status', 'Versi_Ke']);

        if (! $versi || $versi->Id_SOP_Dokumen === null) {
            return 'Versi tidak ditemukan.';
        }
        if ($versi->Status !== SopStatus::PAKET_DRAFT) {
            return 'Hanya versi draft yang bisa dihapus. Versi yang sudah diterbitkan '
                .'adalah catatan kepatuhan, dan yang sudah dicabut adalah riwayatnya.';
        }

        /* Versi batal TETAP berstatus DRAFT, jadi penjaga di atas meloloskannya.
           Menghapusnya berarti membuang jejak keputusan membatalkan — justru
           bagian yang paling ditanyakan saat diaudit. Arsip dibaca, bukan
           dibersihkan. */
        if ($this->dibatalkan($idVersi)) {
            return self::PESAN_ARSIP;
        }

        /* Draft seharusnya tak pernah ditunjuk pointer aktif maupun punya
           progres — pointer hanya menunjuk versi PUBLISHED. Diperiksa toh:
           kalau invariannya pernah jebol, menghapusnya diam-diam akan membuat
           item menunjuk baris yang tak ada lagi. */
        $ditunjuk = DB::table('N_SOP_Paket_Item')->where('Id_Item_Versi_Aktif', $idVersi)->exists()
            || DB::table('N_SOP_Dokumen')->where('Id_Versi_Aktif', $idVersi)->exists();
        if ($ditunjuk) {
            return 'Versi ini sedang ditunjuk sebagai versi berlaku, jadi tak bisa dihapus.';
        }

        if (DB::table('N_SOP_Progress')->where('Id_SOP_Item_Versi', $idVersi)->exists()) {
            return 'Sudah ada karyawan yang tercatat membaca versi ini, jadi tak bisa dihapus.';
        }

        // Dicatat SEBELUM barisnya hilang: sesudah delete tak ada yang bisa dibaca.
        $pengesah = $this->petaPengesah($idVersi);

        DB::transaction(function () use ($idVersi) {
            /* Blok penanda tangan menggantung lewat foreign key — jatuh lebih
               dulu sebelum versinya sendiri dihapus. */
            DB::table('N_SOP_Item_Versi_Pengesah')
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->delete();
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->delete();
        });

        /* Id_SOP_Item_Versi SENGAJA tidak diisi pada baris log ini: barisnya
           sudah tak ada, dan menunjuk baris yang hilang membuat join riwayat
           gagal diam-diam. Nomor versinya cukup, dan itu yang dibaca manusia. */
        $this->jejak(
            'VERSI_HAPUS',
            'Versi '.str_pad((string) $versi->Versi_Ke, 2, '0', STR_PAD_LEFT)
                .' (draft) dihapus.',
            (int) $versi->Id_SOP_Dokumen,
            $userId,
            null,
            ['versi_ke' => (int) $versi->Versi_Ke, 'pengesah' => $pengesah],
        );

        return null;
    }

    /**
     * Nomor versi berikutnya untuk sebuah dokumen.
     *
     * $manual null  -> otomatis: lanjut dari yang terakhir, mulai dari 0
     * $manual angka -> dipakai apa adanya, KECUALI sudah terpakai (null)
     *
     * Nomornya tetap INT. Menyimpannya sebagai teks agar bisa "00" akan
     * merusak urutan riwayat: secara leksikal "10" lebih kecil daripada "9",
     * sehingga MAX(), ORDER BY, dan unique index semuanya ikut salah. Dua digit
     * adalah urusan tampilan, bukan urusan penyimpanan.
     */
    /**
     * Pesan tunggal untuk setiap penolakan atas versi yang sudah dibatalkan.
     *
     * Satu kalimat, dipakai ketiga pintu mutasi. Tiga kalimat berbeda untuk
     * satu aturan akan menyimpang, dan admin yang menemuinya di pintu berbeda
     * akan mengira ia menghadapi tiga aturan.
     */
    public const PESAN_ARSIP = 'Versi ini sudah dibatalkan dan tersimpan sebagai arsip. '
        .'Buat versi baru untuk melanjutkan.';

    /**
     * Apakah versi ini sudah dibatalkan?
     *
     * DIBACA DARI TAHAP, BUKAN DARI STATUS. Versi yang dibatalkan tetap
     * berstatus DRAFT — yang berpindah adalah tahapnya — sehingga setiap
     * penjaga yang ditulis `Status === 'DRAFT'` diam-diam meloloskannya.
     *
     * Yang ditanya adalah SIFAT tahapnya (Nonaktifkan_Versi), bukan namanya.
     * Menuliskan 'CANCEL' sebagai kode berarti setiap perubahan kebijakan alur
     * menunggu rilis, padahal seluruh rancangan tahap ini justru ada supaya
     * alurnya bisa berubah sebagai data.
     */
    public function dibatalkan(int $idVersi): bool
    {
        return SopVersiKeadaan::dibatalkan($this->tahapVersiRow($idVersi));
    }

    /**
     * Alasan versi ini tak menerima perubahan apa pun — atau null bila menerima.
     *
     * Dipakai pintu-pintu di LUAR ubahVersi() yang juga menulis milik sebuah
     * versi: catatan review, dan apa pun yang menyusul. Sebelumnya pintu-pintu
     * itu hanya menanyakan `dibatalkan()`, sehingga versi OBSOLETE lolos —
     * padahal ia riwayat, dan riwayat dibaca bukan diubah. Satu pertanyaan
     * dijawab dua rumus adalah cara cacat berikutnya lahir.
     */
    public function terkunciPenuh(int $idVersi): ?string
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Status']);
        if (! $versi) {
            return null;   // ketiadaan barisnya urusan pemanggil, bukan urusan izin
        }

        $tahap = $this->tahapVersiRow($idVersi);

        // Ditanyakan pada keadaan, BUKAN pada panjang daftar izin. Sejak arsip
        // menerima pembetulan tanggal (21 Sep 2026) daftar itu tak lagi kosong,
        // dan menyimpulkan "terkunci" darinya akan diam-diam membuka pintu ini.
        return SopVersiKeadaan::bekuIsi($versi->Status, $tahap)
            ? SopVersiKeadaan::alasanTerkunci($versi->Status, $tahap)
            : null;
    }

    /**
     * Apakah cap waktu yang dibaca pemanggil masih sama dengan yang tersimpan?
     *
     * Dibandingkan sebagai WAKTU, bukan sebagai teks: driver sqlsrv
     * mengembalikan datetime2 dengan tujuh angka pecahan detik, sementara yang
     * melewati JSON lalu kembali dari layar kerap sudah dipangkas. Perbandingan
     * teks apa adanya akan menolak setiap simpanan — dan penjaga yang selalu
     * menyala sama tak bergunanya dengan penjaga yang tak pernah menyala.
     */
    /**
     * Saring larik update menjadi kolom yang benar-benar boleh ditulis.
     *
     * Tiga kolom cerminan pengesah (Disahkan_Oleh, Jabatan_Pengesah,
     * Kode_Karyawan_Pengesah) ikut kunci 'Pengesah': ketiganya berasal dari
     * baris pertama blok, bukan dari isian tersendiri, jadi izinnya satu.
     *
     * @param  array<string,mixed>  $ubah
     * @param  array<int,string>  $izin
     * @return array<string,mixed>
     */
    private function kolomYangBolehDitulis(array $ubah, array $izin, array $data, bool $formulirPenuh): array
    {
        $selalu = ['Updated_By', 'Updated_At'];
        $cermin = ['Disahkan_Oleh', 'Jabatan_Pengesah', 'Kode_Karyawan_Pengesah'];

        $boleh = array_merge($izin, $selalu);
        if (in_array('Pengesah', $izin, true)) {
            $boleh = array_merge($boleh, $cermin);
        }

        $hasil = array_intersect_key($ubah, array_flip($boleh));

        if ($formulirPenuh) {
            return $hasil;   // DRAFT: kontrak lama dipertahankan apa adanya
        }

        /* FORMULIR PARSIAL: kunci yang TAK DIKIRIM berarti "jangan sentuh",
           bukan "kosongkan".
           Larik update dirakit dengan `?? null`, dan itu benar untuk DRAFT yang
           formulirnya memang mengirim seluruh isian. Pada versi terbit
           formulirnya sempit — menyimpan ringkasan saja akan MENGHAPUS tanggal
           kadaluarsa yang tak ikut terkirim. Ditemukan saat menjalankan
           penjaganya: simpanan pertama mengisi tanggal, simpanan kedua
           mengosongkannya kembali tanpa satu pun galat. */
        foreach (array_keys($hasil) as $kolom) {
            if (in_array($kolom, $selalu, true)) {
                continue;
            }
            if (in_array($kolom, $cermin, true)) {
                if (! array_key_exists('Pengesah', $data)) {
                    unset($hasil[$kolom]);
                }

                continue;
            }
            if (! array_key_exists($kolom, $data)) {
                unset($hasil[$kolom]);
            }
        }

        return $hasil;
    }

    /**
     * Tolak field yang tak diizinkan keadaan versinya, dan tanggal yang sudah lewat.
     *
     * DIABAIKAN BILA NILAINYA SAMA. Formulir mengirim SELURUH isian, bukan hanya
     * yang berubah; menolak setiap field terlarang yang ikut terkirim akan
     * membuat menyimpan menjadi mustahil, dan penjaga yang membuat pekerjaan
     * mustahil akan dimatikan orang, bukan dipatuhi.
     *
     * TANGGAL YANG SUDAH LEWAT dulu ikut ditolak di sini. Aturan itu dicabut
     * 21 Sep 2026: fitur ini juga mencatat dokumen yang sudah berjalan di
     * kertas, dan di sana tanggal masa lalu adalah isian yang normal — salah
     * ketiknya baru ketahuan setelah tersimpan.
     *
     * KALIMAT PENOLAKAN menyebut sebab yang benar. Pada arsip, "terbitkan versi
     * baru" adalah saran yang mustahil dijalankan; yang berlaku di sana adalah
     * alasan arsipnya.
     *
     * @param  array<int,string>  $izin
     */
    private function periksaIzinField(object $versi, array $data, array $izin, ?string $alasanBeku = null): ?string
    {
        $label = self::LABEL_FIELD;

        foreach ($data as $field => $baru) {
            /* Blok pengesah tak punya kolom tunggal untuk dibandingkan; ia
               diperiksa lewat kunci 'Pengesah' saja. */
            if ($field === 'Pengesah') {
                if (! in_array('Pengesah', $izin, true) && $baru !== null && $baru !== []) {
                    return $alasanBeku ?? 'Blok pengesahan tak bisa diubah pada versi ini.';
                }

                continue;
            }

            if (! property_exists($versi, $field)) {
                continue;   // bukan kolom versi (cap tulis-bersamaan, dsb.)
            }

            $lama = $versi->{$field};
            if (! $this->nilaiBerbeda($lama, $baru)) {
                continue;
            }

            if (! in_array($field, $izin, true)) {
                return $alasanBeku
                    ?? ($label[$field] ?? $field).' tak bisa diubah pada versi ini. '
                        .'Perubahan sebesar itu ditempuh dengan menerbitkan versi baru.';
            }

        }

        return null;
    }

    /**
     * Sudahkah dokumen ini dijadwalkan ke karyawan?
     *
     * Ya bila ada item paket AKTIF yang merujuknya dan paket itu punya
     * penjadwalan dengan setidaknya satu karyawan aktif — hitungan yang sama
     * dengan SopDokumenFanoutService::hitungDampak(). Penugasan yang aksesnya
     * sudah dicabut tetap dihitung: karyawannya pernah dijadwalkan membaca isi
     * ini, dan menggantinya diam-diam menghapus apa yang pernah ia baca.
     */
    public function dokumenSudahDijadwalkan(int $idDokumen): bool
    {
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('i.Id_SOP_Dokumen', $idDokumen)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->exists();
    }

    /** Beda yang berarti, bukan beda tipe. '' dan null adalah hal yang sama di sini. */
    private function nilaiBerbeda($lama, $baru): bool
    {
        $rapikan = static fn ($v) => $v === null || $v === '' ? null : (string) $v;

        return $rapikan($lama) !== $rapikan($baru);
    }

    /**
     * Cap tulis-bersamaan sebuah baris versi.
     *
     * Updated_At JATUH KE Created_At. Baris yang baru lahir belum pernah
     * disunting, jadi Updated_At-nya NULL — dan cap kosong membuat penjaga
     * tulis-bersamaan dilewati justru pada versi yang paling baru dibuat,
     * yaitu yang paling mungkin sedang dikerjakan dua orang sekaligus.
     * Created_At selalu ada.
     */
    private function capVersi(object $versi): ?string
    {
        return ($versi->Updated_At ?? null) ?: ($versi->Created_At ?? null);
    }

    private function capMasihSama(?string $tersimpan, string $dibaca): bool
    {
        if ($tersimpan === null) {
            return true;   // tak ada cap tersimpan berarti tak ada yang bisa basi
        }

        try {
            return Carbon::parse($tersimpan)->equalTo(Carbon::parse($dibaca));
        } catch (\Throwable) {
            /* Cap yang tak terbaca bukan bukti bentrok. Menolaknya berarti satu
               masukan cacat mengunci draftnya bagi semua orang. */
            return true;
        }
    }

    /**
     * Baris tahap milik sebuah versi — bahan bagi SopVersiKeadaan.
     *
     * LEFT JOIN, bukan JOIN: versi lama yang Tahap_Id-nya masih kosong harus
     * tetap menghasilkan jawaban (null → "tidak dibatalkan"), bukan lenyap dari
     * hasil dan diam-diam menjawab hal yang sama lewat jalan yang berbeda.
     */
    private function tahapVersiRow(int $idVersi): ?object
    {
        return DB::table('N_SOP_Item_Versi as v')
            ->leftJoin('N_SOP_Master_Tahap_Versi as t', 't.Id_Tahap', '=', 'v.Tahap_Id')
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->first(['t.Kode_Tahap', 't.Nonaktifkan_Versi', 't.Is_Terminal']);
    }

    /**
     * Nomor versi berikutnya.
     *
     * MENGHITUNG HANYA VERSI YANG HIDUP. Versi yang dibatalkan melepaskan
     * nomornya: draft yang batal tak pernah menjadi revisi yang diterbitkan,
     * jadi ia tak boleh mengonsumsi nomor revisi. Tanpa ini, v01 yang batal
     * membuat versi berikutnya bernomor v02 — dan nomor resmi dokumen melompat
     * dari …/00 ke …/02, menyebut Revisi 01 yang tak pernah sampai ke siapa pun.
     *
     * OBSOLETE TIDAK IKUT DILEPAS. Tahap itu tak menonaktifkan versinya
     * (Nonaktifkan_Versi='T'), sehingga nomor yang PERNAH benar-benar terbit
     * terkunci selamanya — memang harus, karena ia sudah beredar.
     *
     * Saringan yang sama dipakai indeks unik terfilter di basis data
     * (DDL-2026-062). Keduanya harus menjawab sama; kalau tidak, layar
     * menawarkan nomor yang basis data tolak.
     */
    private function nomorVersiBerikut(int $idDokumen, $manual): ?int
    {
        if ($manual === null || $manual === '') {
            return SopNomorVersi::berikutnya($idDokumen);
        }

        $nomor = (int) $manual;
        if ($nomor < 0) {
            return null;
        }

        return SopNomorVersi::terpakai($idDokumen, $nomor) ? null : $nomor;
    }

    /* ══ JEJAK AUDIT ═══════════════════════════════════════════════════════
       Master Dokumen sebelumnya tak mencatat apa pun. Yang tercatat hanya
       penerbitan dan rollback, dan itu pun ditulis oleh service versi bersama
       yang kebetulan dilewati — bukan oleh alur ini. Artinya isi draft bisa
       diubah dan daftar pengesah bisa diganti tanpa meninggalkan bekas,
       padahal justru itu dua hal pertama yang ditanyakan saat diaudit.

       Yang dicatat adalah NILAI LAMA DAN BARU, bukan sekadar "diubah".
       Catatan yang cuma menyebut "metadata diubah" memindahkan pekerjaan
       menebak ke orang yang membacanya, dan pada saat itu nilai lamanya sudah
       tak ada di mana pun. */

    /** Label yang dibaca manusia untuk kolom mentah. */
    private const LABEL_FIELD = [
        'Nomor_SOP_Resmi' => 'Nomor Dokumen',
        'Judul' => 'Judul',
        'Tipe' => 'Tipe',
        'Deskripsi' => 'Deskripsi',
        'Versi_Ke' => 'Nomor versi',
        'Id_Master_Konten' => 'Berkas',
        'Id_Master_Ujian' => 'Ujian',
        'Nama_Ujian' => 'Nama ujian',
        'Lampiran' => 'Form kosong',
        'Ringkasan_Perubahan' => 'Ringkasan perubahan',
        'Tanggal_Berlaku' => 'Tanggal berlaku',
        'Tanggal_Permintaan' => 'Tanggal permintaan',
        'Tanggal_Respon' => 'Tanggal respon',
        'Tanggal_Akhir_Approval' => 'Tanggal akhir approval',
        'Tanggal_Kadaluarsa' => 'Tanggal kedaluwarsa',
        'Tanggal_Review_Berikutnya' => 'Jadwal review berikutnya',
        'Tanggal_Obsolete' => 'Tanggal obsolete',
        'Tanggal_Pengesahan' => 'Tanggal pengesahan',
        'Nomor_Pengesahan' => 'Nomor pengesahan',
    ];

    private function jejak(
        string $aksi,
        string $keterangan,
        int $idDokumen,
        int $userId,
        ?int $idVersi = null,
        array $meta = [],
    ): void {
        SopLog::catat($aksi, $keterangan, null, null, $idVersi, null, array_merge($meta, [
            'oleh_id_users' => $userId,
            'id_dokumen' => $idDokumen,
        ]));
    }

    /**
     * Bandingkan keadaan lama dan baru; kembalikan HANYA yang berubah.
     *
     * Perbandingannya longgar dan itu disengaja: nilai dari basis data pulang
     * sebagai teks, nilai dari formulir datang sebagai teks atau angka, dan
     * null dengan string kosong sama-sama berarti "kosong" bagi admin.
     * Membandingkannya ketat akan melaporkan perubahan yang tak pernah terjadi
     * — dan jejak audit yang berisik lebih cepat diabaikan daripada dibaca.
     */
    /* Diteruskan ke SopDiff, bukan disalin ke sana. Builder kini menghasilkan
       diff untuk pertanyaan yang sama, dan dua bentuk Meta_JSON untuk satu
       pertanyaan memaksa layar riwayat mengenali keduanya — persis cara satu
       aturan pelan-pelan jadi dua aturan yang menyimpang. */
    private function bedaNilai(array $lama, array $baru): array
    {
        return SopDiff::antara($lama, $baru);
    }

    /** Ringkas daftar perubahan jadi satu kalimat untuk kolom Keterangan. */
    private function kalimatBeda(string $awalan, array $beda): string
    {
        return SopDiff::kalimat($awalan, $beda);
    }

    /** Blok pengesah sebagai daftar teks "Nama — Peran", untuk dibandingkan. */
    private function petaPengesah(int $idVersi): array
    {
        return DB::table('N_SOP_Item_Versi_Pengesah')
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Jenis', 'REALISASI')
            ->orderBy('Urutan')
            ->get(['Nama', 'Peran'])
            ->map(fn ($p) => trim(($p->Nama ?? '—').' — '.($p->Peran ?? 'tanpa peran')))
            ->all();
    }

    /** Apakah nomor itu sudah dipakai dokumen ini? Dipakai controller untuk pesan galat. */
    /**
     * Apakah nomor versi ini sudah dipakai?
     *
     * PINTU KELIMA. Metode ini dipakai controller untuk menangkap bentrok
     * SEBELUM menulis, supaya pesannya menyebut angkanya alih-alih memuntahkan
     * galat unique index 2601. Maksudnya benar — tapi ia menghitung versi yang
     * DIBATALKAN sebagai pemakai, sehingga admin ditolak dengan "Versi 01 sudah
     * ada" tepat pada nomor yang baru saja dilepas oleh pembatalan, dan yang
     * diusulkan sendiri oleh muatan wizard.
     *
     * Didelegasikan ke SopNomorVersi supaya jawabannya sama dengan penomoran
     * otomatis DAN dengan predikat indeks unik terfilter (DDL-2026-062).
     * Tiga tempat yang menjawab pertanyaan sama harus memakai satu rumus;
     * inilah yang tersisa saat empat lainnya sudah disatukan.
     */
    public function nomorVersiTerpakai(int $idDokumen, int $nomor): bool
    {
        return SopNomorVersi::terpakai($idDokumen, $nomor);
    }

    /**
     * Bersihkan blok penanda tangan dari klien menjadi bentuk yang boleh masuk.
     *
     * Baris tanpa nama dibuang tanpa suara — itu baris kosong yang tertinggal
     * di formulir, bukan penanda tangan. Urutan ditetapkan di sini, bukan
     * dipercayakan pada klien: urutan blok membawa makna (yang menyiapkan bukan
     * yang menyetujui), dan urutan yang bolong atau kembar akan menampilkan
     * peran pada baris yang salah.
     */
    private function rapikanBlokPengesah($pengesah): array
    {
        if (! is_array($pengesah)) {
            return [];
        }

        $bersih = [];
        foreach ($pengesah as $p) {
            if (! is_array($p)) {
                continue;
            }
            $nama = trim((string) ($p['Nama'] ?? ''));
            if ($nama === '') {
                continue;
            }

            $bersih[] = [
                'Peran' => trim((string) ($p['Peran'] ?? '')) ?: null,
                'Nama' => $nama,
                'Jabatan' => trim((string) ($p['Jabatan'] ?? '')) ?: null,
                'Kode_Karyawan' => trim((string) ($p['Kode_Karyawan'] ?? '')) ?: null,
                'Urutan' => count($bersih) + 1,
            ];
        }

        return $bersih;
    }

    /** Tulis ulang blok penanda tangan sebuah versi. */
    /**
     * Menyimpan blok pengesah.
     *
     * SopTahapVersiService menyimpan hasil nilaiSyarat() selama satu permintaan,
     * dan REALISASI_PENGESAH adalah salah satu isinya. Tanpa pembatalan di sini,
     * menyimpan pengesah lalu membaca alurVersi() dalam permintaan yang sama
     * memberi jawaban dari SEBELUM penyimpanan: wizard tetap melaporkan
     * "Realisasi pengesah" kurang padahal barisnya sudah tertulis, dan tombol
     * majunya tetap terkunci.
     *
     * Terbukti langsung: sesudah simpan, instance yang SEGAR menjawab
     * Syarat_Kurang = [], instance yang sama menjawab ["Realisasi pengesah"].
     *
     * lupakanNilai() memang disediakan untuk ini, tetapi sebelum perbaikan ini
     * tak ada satu pun penulis yang memanggilnya. Pembatalannya dipasang di
     * ubahVersi(), bukan di sini: metode ini keluar lebih awal saat bloknya
     * kosong, sedangkan blok kosong justru berarti admin MENGHAPUS seluruh
     * pengesah — perubahan yang sama perlunya dibatalkan dari cache.
     */
    private function simpanBlokPengesah(int $idVersi, array $blok, int $userId): void
    {
        if ($blok === []) {
            return;
        }

        DB::table('N_SOP_Item_Versi_Pengesah')->insert(array_map(
            fn ($p) => $p + [
                /* Jalur lama dan form pengesahan selalu menulis tanda tangan
                   NYATA. Ditulis eksplisit, bukan hanya mengandalkan default
                   basis data, supaya salinan lintas environment tetap membawa
                   artinya. */
                'Jenis' => 'REALISASI',
                'Id_SOP_Item_Versi' => $idVersi,
                'Created_By' => $userId,
                'Created_At' => now(),
                'Flag_Aktif' => SopStatus::AKTIF,
            ],
            $blok,
        ));
    }

    /**
     * Blok penanda tangan untuk sekumpulan versi, dikunci per Id_SOP_Item_Versi.
     *
     * ATURAN MUNDUR: versi tanpa baris di tabel blok dibaca dari kolom
     * tunggalnya sebagai blok berisi satu baris. Itulah yang membuat versi lama
     * dan versi buatan Builder tak perlu diisi mundur sama sekali — keduanya
     * tetap menampilkan pengesahnya, hanya tanpa peran.
     */
    public function blokPengesah(array $idVersi, $versiRows = null): array
    {
        if ($idVersi === []) {
            return [];
        }

        /* Blok yang ditampilkan bersama versi adalah tanda tangan NYATA.
           Rencana punya pintunya sendiri dan tak boleh tercampur di sini —
           kalau tercampur, kartu versi akan menyebut orang yang belum
           menandatangani sebagai pengesah. */
        $blok = DB::table('N_SOP_Item_Versi_Pengesah')
            ->whereIn('Id_SOP_Item_Versi', $idVersi)
            ->where('Jenis', 'REALISASI')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Id_SOP_Item_Versi')->orderBy('Urutan')
            ->get(['Id_SOP_Item_Versi', 'Peran', 'Nama', 'Jabatan', 'Kode_Karyawan'])
            ->groupBy('Id_SOP_Item_Versi')
            ->map(fn ($g) => $g->map(fn ($p) => [
                'Peran' => $p->Peran,
                'Nama' => $p->Nama,
                'Jabatan' => $p->Jabatan,
                'Terverifikasi' => ! empty($p->Kode_Karyawan),
            ])->values()->all())
            ->all();

        foreach ($versiRows ?? [] as $v) {
            $id = (int) ($v->Id_SOP_Item_Versi ?? 0);
            if ($id === 0 || isset($blok[$id])) {
                continue;
            }
            if (empty($v->Disahkan_Oleh)) {
                continue;
            }

            $blok[$id] = [[
                'Peran' => null,
                'Nama' => $v->Disahkan_Oleh,
                'Jabatan' => $v->Jabatan_Pengesah ?? null,
                'Terverifikasi' => ! empty($v->Kode_Karyawan_Pengesah),
            ]];
        }

        return $blok;
    }

    /**
     * Matriks pengesah sebuah versi dalam bentuk yang bisa DIISIKAN ke formulir.
     *
     * BEDA DARI blokPengesah(), dan bedanya bukan gaya. blokPengesah() melayani
     * TAMPILAN: ia sengaja membuang Kode_Karyawan dan menggantinya dengan
     * boolean Terverifikasi, karena kartu versi tak perlu tahu kode karyawannya.
     * Formulir justru perlu: tanpa kode itu, baris yang disalin kehilangan
     * tautannya ke karyawan dan diam-diam turun menjadi "tidak terverifikasi" —
     * matriks tampak penuh, padahal isinya jadi teks bebas.
     *
     * Dibuat sebagai fungsi kedua, bukan dengan menambah kolom pada yang
     * pertama: blokPengesah() punya empat pemanggil lain yang semuanya
     * menampilkan, dan mengubah bentuknya menyeret tampilan yang tak diminta
     * berubah.
     *
     * `Aktif` menyertai tiap baris supaya layar bisa MENGATAKAN bahwa seorang
     * pengesah lama sudah tak aktif, bukan diam-diam membuangnya. Baris yang
     * hilang tanpa keterangan membuat matriks tampak lengkap padahal ada peran
     * yang kosong, dan admin tak punya petunjuk siapa yang dulu mengisinya.
     *
     * @return array<int, array{Peran: ?string, Nama: ?string, Jabatan: ?string, Kode_Karyawan: ?string, Aktif: bool}>
     */
    public function saranPengesah(int $idVersi): array
    {
        return DB::table('N_SOP_Item_Versi_Pengesah as p')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'p.Kode_Karyawan')
            ->where('p.Id_SOP_Item_Versi', $idVersi)
            ->where('p.Jenis', 'REALISASI')
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('p.Urutan')->orderBy('p.Id_SOP_Item_Versi_Pengesah')
            ->get(['p.Peran', 'p.Nama', 'p.Jabatan', 'p.Kode_Karyawan', 'k.Aktif'])
            ->map(fn ($p) => [
                'Peran' => $p->Peran,
                'Nama' => $p->Nama,
                'Jabatan' => $p->Jabatan,
                'Kode_Karyawan' => $p->Kode_Karyawan,
                /* Baris tanpa Kode_Karyawan (7 dari 61 di dev) ditulis sebagai
                   teks bebas dan tak pernah tertaut karyawan; ia bukan "tak
                   aktif", ia memang tak pernah terverifikasi. Keduanya
                   dibedakan supaya layar tak menuduhnya keluar dari perusahaan. */
                'Aktif' => $p->Kode_Karyawan === null ? null : ($p->Aktif === SopStatus::AKTIF),
            ])
            ->all();
    }

    /**
     * Terbitkan versi dokumen: jadikan versi berlaku, dan sebarkan.
     *
     * Menumpang jalur yang sudah terbukti sejak Fase 3 —
     * SopItemVersionService::publish() sudah berkunci DOKUMEN, sehingga ia
     * meng-obsolete versi lama dokumen ini, menggeser pointer pada SEMUA item
     * perujuk, dan mematikan integrasi ujian versi lama sekaligus. Yang
     * ditambahkan di sini hanya pointer Id_Versi_Aktif pada dokumennya, yang
     * dipakai Master SOP dan pemilih di Builder untuk tahu versi mana berlaku.
     */
    /**
     * @param  int|null  $kecualiPaket  Paket yang SUDAH direkonsiliasi pemanggil
     *                                  lewat alurnya sendiri (Builder). Master
     *                                  mengirim null: tak ada yang sudah.
     */
    public function terbitkanVersi(int $idVersi, int $userId, ?string $wajibBacaUlang = null, ?int $kecualiPaket = null): bool
    {
        $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
            ->first(['Id_SOP_Item_Versi', 'Id_SOP_Dokumen', 'Id_Master_Konten', 'Status']);
        if (! $versi || $versi->Id_SOP_Dokumen === null) {
            return false;
        }

        /* HANYA DRAFT YANG BISA DITERBITKAN.
           Versi OBSOLETE dulu ikut bisa, sebab syaratnya cuma "bukan
           PUBLISHED". Menerbitkannya bukan penerbitan melainkan
           PENGEMBALIAN: pointer mundur ke revisi lama, jejak auditnya
           berbunyi "versi baru diterbitkan" padahal yang terjadi
           sebaliknya, dan rekonsiliasi kewajiban yang jadi bagian rollback
           dilewati sama sekali.

           Aturan produknya sudah tegas di tempat lain — rollbackVersi()
           sengaja dimatikan dengan pesan "pengembalian dilakukan lewat
           revisi dan publish baru". Syarat lama di sini tak sengaja
           membuka pintu yang di sana justru dikunci. */
        if ($versi->Status !== SopStatus::PAKET_DRAFT) {
            throw new \DomainException(
                'Hanya versi draft yang bisa diterbitkan. Untuk mengembalikan isi versi '
                .'lama, buat revisi baru dari isinya — pengembalian yang tercatat sebagai '
                .'penerbitan akan menyesatkan jejak auditnya.');
        }

        /* TERBIT HANYA DARI TAHAP YANG MEMANG MENGARAH KE SANA.
           Sampai sebelum ini, penerbitan sama sekali tak memeriksa tahap: versi
           yang masih menunggu tanda tangan bisa diterbitkan lewat pemanggilan
           langsung, dan langkah Pemberkasan — tempat berkas finalnya dipasang —
           terlewati begitu saja.

           Aturannya TETAP tinggal di N_SOP_Master_Tahap_Versi_Transisi, bukan
           di sini. Menuliskan "hanya dari APPROVED" sebagai kode berarti setiap
           perubahan kebijakan alur menunggu rilis, padahal seluruh rancangan
           tahap ini justru ada supaya alur bisa berubah sebagai data.

           Diperiksa SEBELUM pastikanIsiSah(): versi tanpa berkas yang juga
           salah tahap harus mendengar sebab yang benar, bukan dikirim mencari
           berkas untuk penerbitan yang memang belum boleh terjadi. */
        $tahapSvc = app(SopTahapVersiService::class);
        $bolehTerbit = collect($tahapSvc->tujuanSah($idVersi))
            ->contains('Kode_Tahap', 'FILING_REGISTERED');
        if (! $bolehTerbit) {
            $kini = $tahapSvc->tahapVersi($idVersi);
            throw new \DomainException(
                'Versi ini belum boleh diterbitkan dari tahap "'
                .($kini->Nama_Tahap ?? 'sekarang')
                .'". Selesaikan pengesahannya lebih dulu, lalu terbitkan dari langkah Pemberkasan.');
        }

        /* ISI WAJIB SAH. Berkas final baru dituntut saat Pemberkasan,
           sedangkan realisasi pengesah sudah dituntut untuk masuk ke sana. */
        $this->pastikanIsiSah($idVersi);

        /* Sifat revisi ditetapkan DI SINI, bukan saat versinya dibuat: admin
           baru bisa menimbang "perlu dibaca ulang atau tidak" setelah melihat
           siapa saja yang terdampak, dan itu terjadi di pratinjau terbit.

           Hanya ditulis bila memang dikirim — tanpa itu, nilai yang sudah
           tersimpan (atau default 'Y') dibiarkan apa adanya. */
        if ($wajibBacaUlang !== null) {
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)
                ->update(['Wajib_Baca_Ulang' => $wajibBacaUlang === 'T' ? 'T' : 'Y']);
        }

        /* SATU TRANSAKSI DARI PUBLISH SAMPAI REKONSILIASI.
           publish() sendiri sudah transaksional, tetapi tiga langkah sesudahnya
           — menggeser pointer dokumen, merakit ulang nomor resmi, dan
           merekonsiliasi paket perujuk — dulu berjalan di luar transaksi mana
           pun. Gagal di tengah meninggalkan keadaan yang tak dijaga siapa pun:
           versi sudah PUBLISHED sementara pointer dokumen masih menunjuk revisi
           lama, atau nomor resmi menyebut revisi yang bukan lagi yang berlaku,
           atau paket belum direkonsiliasi. Semuanya invarian yang tak ditegakkan
           basis data (audit 30 Agu 2026), jadi tak ada yang akan menangkapnya.

           KUNCI DOKUMEN DIAMBIL LEBIH DULU. Aturan "satu dokumen, satu versi
           PUBLISHED" ditegakkan urutan operasi di dalam publish(), dengan SELECT
           biasa. Yang menyelamatkannya selama ini kebetulan isolasi — RCSI mati,
           jadi pembaca antre di belakang penulis. Kunci eksplisit di sini
           membuatnya tak lagi bergantung pada kebetulan itu. */
        return DB::transaction(function () use ($idVersi, $userId, $versi, $kecualiPaket) {
            DB::table($this->table)->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)
                ->lockForUpdate()->first(['Id_SOP_Dokumen']);

            return $this->jalankanPenerbitan($idVersi, $userId, $versi, $kecualiPaket);
        });
    }

    /**
     * Empat langkah penerbitan, dijalankan pemanggilnya di dalam satu transaksi
     * berkunci. Dipisah semata supaya batas transaksinya terlihat sebagai satu
     * baris, bukan tenggelam di antara empat operasi berkomentar panjang.
     */
    private function jalankanPenerbitan(int $idVersi, int $userId, object $versi, ?int $kecualiPaket): bool
    {
        app(SopItemVersionService::class)->publish($idVersi, $userId);

        DB::table($this->table)->where('Id_SOP_Dokumen', $versi->Id_SOP_Dokumen)->update([
            'Id_Versi_Aktif' => $idVersi,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        /* Segmen Revisi baru saja berganti — nomor resmi ikut dirakit ulang
           dan disebarkan ke item, supaya monitoring dan ekspor tidak menyebut
           revisi yang sudah tak berlaku. */
        $this->segarkanNomorResmi((int) $versi->Id_SOP_Dokumen, $userId);

        /* REKONSILIASI KE SEMUA PAKET PERUJUK.
           Sebelum ini, menerbitkan revisi menggeser pointer di semua paket
           lalu berhenti — tak seorang pun direkonsiliasi, termasuk paket
           pemicunya. Akibatnya Status_Paket karyawan tetap tertulis SELESAI
           padahal versi yang harus mereka baca sudah berganti: angka
           kepatuhan benar menurut catatan, salah menurut kenyataan.

           Sejak Fase 2, rekonsiliasi() hanya MENULIS INTENT — satu baris
           outbox — sehingga transaksi penerbitan ini tetap O(item), bukan
           O(item x karyawan). Penyisiran karyawan dan notifikasi gabungan
           (satu per karyawan per revisi) dikerjakan SopReconcileHandler di luar
           transaksi. Nilai baliknya sengaja tak dibaca di sini: jejaknya sudah
           dicatat di jalur pemanggil, dan penerbitan tak boleh gagal karena
           angka yang belum ada saat commit. */
        app(SopDokumenFanoutService::class)->rekonsiliasi(
            (int) $versi->Id_SOP_Dokumen, 'VERSI_BARU', $userId, $kecualiPaket);

        return true;
    }

    /**
     * Sebarkan identitas dokumen ke SEMUA item yang merujuknya — Fase 6.
     *
     * KENAPA DISEBARKAN, BUKAN DI-JOIN. Judul & nomor SOP item dibaca oleh 10+
     * kueri di 8 service (monitoring, export rinci, register kontrol dokumen,
     * snapshot rilis, kuis, analytics, people, completion). Menulis ulang
     * semuanya jadi COALESCE(d.Judul, i.Judul_Item) berarti 10+ peluang regresi
     * baru hanya untuk membaca kolom yang isinya sama.
     *
     * Prinsip yang dipakai di sini persis sama dengan yang menyelamatkan
     * migrasi ini sejak awal: `Id_Item_Versi_Aktif` DIPERTAHANKAN sebagai
     * cerminan yang disinkronkan sistem, sehingga sembilan jalur baca besar
     * berjalan tanpa diubah sama sekali. Identitas mengikuti pola yang sama —
     * kolom item menjadi cerminan, dokumen menjadi sumber kebenarannya.
     *
     * Konsekuensi yang harus disengaja: kolom item adalah data turunan dengan
     * SATU penulis. Sejak Fase 6, form item tak lagi punya field Judul & Nomor
     * SOP untuk tipe ber-versi, jadi satu-satunya yang menulisnya adalah
     * penyebaran ini.
     *
     * Return: jumlah item yang tersentuh.
     */
    public function sinkronIdentitasKeItem(int $idDokumen, int $userId): int
    {
        $dok = DB::table($this->table)->where('Id_SOP_Dokumen', $idDokumen)
            ->first(['Judul', 'Nomor_SOP_Resmi', 'Deskripsi']);
        if (! $dok) {
            return 0;
        }

        return DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->update([
                'Judul_Item' => $dok->Judul,
                'Nomor_SOP_Resmi' => $dok->Nomor_SOP_Resmi,
                'Updated_By' => $userId,
                'Updated_At' => now(),
            ]);
    }

    /**
     * Pastikan item ber-versi punya dokumen; kembalikan id dokumennya.
     *
     * Mengembalikan null bila item tak ada ATAU tipenya tak ber-versi.
     * PARAGRAF/TAUTAN/ABSENSI tak boleh dipaksa punya dokumen: 18 baris versi
     * yatim di dev justru milik item yang tipenya pernah diubah menjauh dari
     * tipe ber-versi, dan itu keadaan yang sah.
     *
     * Aman dipanggil berkali-kali untuk item yang sama.
     */
    public function ensureForItem(int $idItem, int $userId): ?int
    {
        $item = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)->first();
        if (! $item || ! SopTipeItem::isVersioned($item->Tipe_Item)) {
            return null;
        }
        if ($item->Id_SOP_Dokumen !== null) {
            return (int) $item->Id_SOP_Dokumen;
        }

        $idDokumen = $this->createForItem($item, $userId);

        DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket_Item', $idItem)
            ->update(['Id_SOP_Dokumen' => $idDokumen]);

        return $idDokumen;
    }
}
