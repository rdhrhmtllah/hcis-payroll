<?php

namespace Tests\Feature\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\PaNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pemberitahuan Performance Appraisal.
 *
 * DUA PENEGASAN DI SINI TERLIHAT SEPELE DAN JUSTRU YANG PALING PENTING
 *
 * `Module` harus `'KPI'` dan `source` harus `'MAIN_NOTIF'`. Keduanya bukan soal rapi:
 * `NotificationService::normalizeModule()` memetakan apa pun yang tak dikenalnya menjadi
 * `'HCIS'` tanpa berkata apa-apa, dan topbar menyaring berdasarkan `source`. Salah salah
 * satunya menghasilkan baris yang tersimpan rapi di tabel dan **tidak pernah dilihat
 * siapa pun** — sementara uji yang hanya mencacah baris tetap hijau.
 *
 * Karena itu uji di bawah memeriksa NILAI kedua kolom itu, bukan sekadar keberadaan
 * barisnya.
 *
 * Yang diperiksa langsung ke tabel, bukan lewat pembaca, karena pembacanya memetakan
 * nama kunci dan pemetaan itu sudah sekali menyesatkan saya saat menelusuri manual.
 */
class NotificationTest extends TestCase
{
    use DatabaseTransactions;

    private int $cycleId;

    private int $participantId;

    private int $kategoriId;

    private array $stok = [];

    private object $penilai;

    /**
     * Penunjuk stok penilai — properti instans, BUKAN `static` di dalam method.
     *
     * `static` di dalam method bertahan seumur PROSES, bukan seumur satu uji, sehingga
     * penunjuknya terus merangkak antar uji dan stoknya habis di tengah jalan — dengan
     * kegagalan yang menunjuk ke "stok habis", bukan ke sebab sebenarnya.
     */
    private int $penunjukPenilai = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stok = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->limit(6)
            ->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'ID_Level_Jabatan'])
            ->all();

        $this->assertGreaterThanOrEqual(3, count($this->stok), 'Uji ini butuh beberapa karyawan.');

        $this->kategoriId = (int) DB::table('N_PA_Rater_Category')
            ->where('Kode_Kategori', 'SELF')->value('Id_PA_Rater_Category');

        $this->penunjukPenilai = 1;
        $this->cycleId = $this->buatSiklus('OPEN');
        $this->participantId = $this->buatPeserta();
        $this->penilai = $this->stok[1];
    }

    /* ------------------------------------------------------------------ *
     * Dua kolom yang menentukan terlihat atau tidak                       *
     * ------------------------------------------------------------------ */

    public function test_modul_dan_source_membuat_pemberitahuan_benar_benar_terlihat(): void
    {
        $id = $this->buatPenugasan(now()->subDays(2)->format('Y-m-d'));

        $this->service()->sudahTerlambat();

        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$id]));

        $baris = $this->notif(PaNotificationService::TYPE_TERLAMBAT, $id);

        /* 'PA' akan DIAM-DIAM menjadi 'HCIS' lewat normalizeModule(), dan barisnya tidak
           akan muncul di tempat yang diharapkan. 'KPI' juga yang benar secara isi: PA
           adalah submodul KPI. */
        $this->assertSame('KPI', $baris->Module);

        // Topbar dan modal menyaring kolom ini. Nilai lain tersimpan dan tak terlihat.
        $this->assertSame(PaNotificationService::SUMBER, $baris->source);

        // Tanpa tujuan, penerimanya tahu ada sesuatu tetapi tidak tahu harus ke mana.
        $this->assertSame(PaNotificationService::TUJUAN_PESERTA, $baris->Action_Url);
        $this->assertSame($this->penilai->Kode_Karyawan, $baris->Kode_Karyawan);

        /* ANGKA HARINYA, bukan hanya keberadaan barisnya.
           Penyaringnya memakai GETDATE() milik SQL Server, sedangkan judulnya dihitung
           dari date() milik PHP — dua jam yang berbeda. Selama keduanya sepakat soal
           "hari ini" semuanya benar; saat tidak, penyaringnya memilih baris yang TEPAT
           dan judulnya menyebut angka yang SALAH. Tanpa penegasan ini, tidak ada uji yang
           bisa melihatnya. */
        $this->assertStringContainsString('2 hari', $baris->Title);
    }

    /* ------------------------------------------------------------------ *
     * Aturan tenggat — sama dengan yang dipakai layar                     *
     * ------------------------------------------------------------------ */

    public function test_tenggat_hari_ini_masuk_mendekat_bukan_terlambat(): void
    {
        $id = $this->buatPenugasan(now()->format('Y-m-d'));

        $this->service()->sudahTerlambat();
        $this->service()->tenggatMendekat();

        /* Tenggat DATE berarti sampai AKHIR hari itu. Menegur orang pada hari yang masih
           miliknya adalah cara cepat membuat pemberitahuan ini diabaikan seterusnya.

           Kedua arah ditegaskan atas baris yang SAMA: bukan terlambat, TAPI mendekat.
           Yang kedua sekaligus jadi kontrol positifnya. */
        $this->assertSame(0, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$id]));
        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_TENGGAT_DEKAT, [$id]));

        /* Tepat di percabangan `$sisa === 0`. Mencacah barisnya saja membiarkan
           `selisihHari()` salah satu hari tanpa satu pun uji melihatnya — dan yang muncul
           di layar orang adalah "Penilaian tinggal 0 hari" untuk sesuatu yang jatuh tempo
           hari ini. Ini juga satu-satunya tempat isi PA_TENGGAT_DEKAT diperiksa. */
        $baris = $this->notif(PaNotificationService::TYPE_TENGGAT_DEKAT, $id);

        $this->assertStringContainsString('hari ini', mb_strtolower($baris->Title));
        // Yang jatuh tempo hari ini tidak boleh tenggelam di antara yang masih tiga hari.
        $this->assertSame(1, (int) $baris->Priority);
    }

    public function test_penugasan_selesai_dan_dibatalkan_tidak_ditagih(): void
    {
        $selesai = $this->buatPenugasan(now()->subDays(5)->format('Y-m-d'), 'SELESAI');
        $batal = $this->buatPenugasan(now()->subDays(5)->format('Y-m-d'), 'DIBATALKAN');

        /* KONTROL POSITIF, dan bukan basa-basi.
           `kirim()` menangkap Throwable lalu mengembalikan 0, jadi angka nol TIDAK
           membedakan "tersaring dengan benar" dari "upsert meledak dan ditelan". Tanpa
           baris yang HARUS ditagih di sebelahnya, uji ini tetap hijau seandainya seluruh
           pengiriman rusak besok. */
        $hidup = $this->buatPenugasan(now()->subDays(5)->format('Y-m-d'));

        $this->service()->sudahTerlambat();

        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$hidup]),
            'Yang masih berjalan HARUS ditagih; kalau tidak, nol di bawah tak berarti apa-apa.');
        $this->assertSame(0, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$selesai, $batal]));
    }

    public function test_siklus_tertutup_tidak_menagih_pekerjaan_yang_tak_bisa_dikerjakan(): void
    {
        $id = $this->buatPenugasan(now()->subDays(3)->format('Y-m-d'));

        $this->service()->sudahTerlambat();
        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$id]),
            'Prasyarat: siklus OPEN menagih.');

        DB::table('N_HRIS_Notification')
            ->where('Type', PaNotificationService::TYPE_TERLAMBAT)
            ->where('Reference_Id', (string) $id)->delete();

        DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $this->cycleId)
            ->update(['Status_Cycle' => 'CLOSED']);

        // Menagih penilaian pada siklus yang jendelanya sudah ditutup berarti meminta
        // orang mengerjakan sesuatu yang sistemnya sendiri tidak lagi menerimanya.
        $this->service()->sudahTerlambat();
        $this->assertSame(0, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$id]));
    }

    public function test_penugasan_tanpa_tenggat_tidak_pernah_ditagih(): void
    {
        $tanpaTenggat = $this->buatPenugasan(null);
        // Kontrol positif; lihat catatan di uji selesai/dibatalkan.
        $bertenggat = $this->buatPenugasan(now()->subDays(3)->format('Y-m-d'));

        $this->service()->sudahTerlambat();

        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$bertenggat]));
        $this->assertSame(0, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$tanpaTenggat]));
    }

    /* ------------------------------------------------------------------ *
     * Idempoten                                                           *
     * ------------------------------------------------------------------ */

    public function test_dijalankan_ulang_tidak_menumpuk_pemberitahuan(): void
    {
        $id = $this->buatPenugasan(now()->subDays(4)->format('Y-m-d'));

        $this->service()->sudahTerlambat();
        $this->service()->sudahTerlambat();
        $this->service()->sudahTerlambat();

        /* Kuncinya memuat tanggal, jadi pengingat boleh berulang tiap HARI tetapi hanya
           sekali per hari. Perintah yang hanya aman dijalankan sekali akan menakuti orang
           untuk menjalankannya ulang sesudah gagal separuh jalan. */
        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$id]));
    }

    /* ------------------------------------------------------------------ *
     * Penugasan dan penerbitan                                            *
     * ------------------------------------------------------------------ */

    public function test_penugasan_baru_memberi_tahu_penilainya(): void
    {
        $id = $this->buatPenugasan(now()->addDays(10)->format('Y-m-d'));

        $this->assertSame(1, $this->service()->penugasanDibentuk([$id]));

        /* Dipanggil DUA KALI. Docblock service menjanjikan seluruh pengiriman idempoten,
           tetapi sebelumnya hanya jalur pengingat yang dibuktikan. Justru dua jalur lain
           yang paling mungkin terpanggil ulang di produksi: `hasilTerbit()` dipanggil dari
           DUA tempat di PaResultPublishService, dan `penugasanDibentuk()` dari jalur yang
           boleh dijalankan ulang. */
        $this->service()->penugasanDibentuk([$id]);

        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_DITUGASKAN, [$id]),
            'Pengiriman ulang tidak boleh menumpuk.');

        $baris = $this->notif(PaNotificationService::TYPE_DITUGASKAN, $id);

        $this->assertSame($this->penilai->Kode_Karyawan, $baris->Kode_Karyawan);
        $this->assertStringContainsString('ditugaskan', mb_strtolower($baris->Title));
        // Tenggatnya disebut; pemberitahuan tanpa tenggat memaksa orang membuka layar
        // hanya untuk tahu apakah ini mendesak.
        $this->assertStringContainsString('Tenggat', $baris->Message);
    }

    public function test_penilaian_diri_disebut_apa_adanya(): void
    {
        // Penilai = pesertanya sendiri. "Anda ditugaskan menilai Budi" ketika Budi adalah
        // dirinya sendiri terbaca seperti kekeliruan sistem.
        $peserta = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama_Karyawan_Saat_Cycle']);

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Kode_Perusahaan_Penilai' => $peserta->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $peserta->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $peserta->Nama_Karyawan_Saat_Cycle,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => 'BELUM_MULAI',
        ]);

        $id = (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Kode_Karyawan_Penilai', $peserta->Kode_Karyawan)
            ->value('Id_PA_Assignment');

        /* Nilai baliknya ditegaskan, bukan dibuang. `kirim()` menelan Throwable dan
           mengembalikan 0; tanpa penegasan ini, pengiriman yang gagal muncul sebagai
           "Attempt to read property on null" satu baris di bawah — merah yang benar,
           tetapi menunjuk ke tempat yang salah dan memakan satu putaran untuk mengerti. */
        $this->assertSame(1, $this->service()->penugasanDibentuk([$id]));

        $baris = $this->notif(PaNotificationService::TYPE_DITUGASKAN, $id);

        $this->assertStringContainsString('diri', mb_strtolower($baris->Title));
        $this->assertStringNotContainsString(
            $peserta->Nama_Karyawan_Saat_Cycle,
            $baris->Message,
            'Penilaian diri tidak menyebut namanya sendiri sebagai orang yang dinilai.'
        );
    }

    public function test_hasil_terbit_memberi_tahu_peserta_tanpa_menyebut_nilainya(): void
    {
        $resultId = $this->buatHasil('TERBIT', 82.5, 'B');

        $this->assertSame(1, $this->service()->hasilTerbit([$resultId]));

        // Jalur ini dipanggil dari DUA tempat di PaResultPublishService; ulangannya wajib
        // tidak menumpuk.
        $this->service()->hasilTerbit([$resultId]);

        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_HASIL_TERBIT, [$resultId]));

        $baris = $this->notif(PaNotificationService::TYPE_HASIL_TERBIT, $resultId);

        $peserta = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $this->participantId)->value('Kode_Karyawan');

        // Yang diberi tahu PESERTA, bukan penilainya.
        $this->assertSame($peserta, $baris->Kode_Karyawan);

        /* Nilai dan grade TIDAK disebut. Pratinjau pemberitahuan muncul di topbar dan bisa
           terbaca orang lain yang kebetulan melihat layar; grade seseorang bukan sesuatu
           yang layak tampil di sana. */
        $this->assertStringNotContainsString('82', $baris->Message);
        $this->assertStringNotContainsString('82', $baris->Title);
        $this->assertDoesNotMatchRegularExpression('/\bB\b/', $baris->Message);
    }

    public function test_hasil_yang_belum_terbit_tidak_memberi_tahu_siapa_pun(): void
    {
        $resultId = $this->buatHasil('DIHITUNG', 82.5, 'B');

        // Memberi tahu sebelum terbit meniadakan seluruh guna langkah penerbitan.
        $this->assertSame(0, $this->service()->hasilTerbit([$resultId]));
        $this->assertSame(0, $this->cacahNotif(PaNotificationService::TYPE_HASIL_TERBIT, [$resultId]));

        /* KONTROL POSITIF atas baris yang SAMA. Tanpa ini, nol di atas tetap hijau
           seandainya hasilTerbit() rusak total — `kirim()` menelan Throwable dan
           mengembalikan 0. */
        DB::table('N_PA_Result')->where('Id_PA_Result', $resultId)
            ->update(['Status_Hasil' => 'TERBIT']);

        $this->assertSame(1, $this->service()->hasilTerbit([$resultId]));
        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_HASIL_TERBIT, [$resultId]));
    }

    public function test_daftar_kosong_dan_id_yang_tidak_ada_sama_sama_tidak_mengirim(): void
    {
        /* Dilingkupi ke `PA_%`, BUKAN cacah seluruh tabel.
           `N_HRIS_Notification` memuat ribuan baris dan di-polling topbar setiap menit
           oleh setiap orang yang membuka HCIS. Cacah global punya dua kerugian dan nol
           keuntungan: nilainya bisa berubah karena sesi LAIN yang commit di antara dua
           cacahan, dan RCSI mati di Web_HRIS sehingga COUNT(*) seluruh tabel dari dalam
           transaksi bisa antre di belakang penulis lalu gagal sebagai "Lock request time
           out" — di uji yang tidak ada urusannya dengan lock. */
        $sebelum = $this->cacahSemuaPa();

        $this->assertSame(0, $this->service()->penugasanDibentuk([]));
        $this->assertSame(0, $this->service()->hasilTerbit([]));

        /* Id yang TIDAK ADA, bukan hanya daftar kosong.
           Daftar kosong tidak bisa membuat uji ini merah: mencabut gerbang `=== []` pun,
           `whereIn` atas daftar kosong tetap memulangkan nol baris. Id hantu menempuh
           jalur kueri yang sungguhan, sehingga uji ini punya kemungkinan gagal — dan
           penjaga yang tidak punya kemungkinan gagal bukan penjaga. */
        $hantuAssignment = (int) DB::table('N_PA_Assignment')->max('Id_PA_Assignment') + 99999;
        $hantuHasil = (int) DB::table('N_PA_Result')->max('Id_PA_Result') + 99999;

        $this->assertSame(0, $this->service()->penugasanDibentuk([$hantuAssignment]));
        $this->assertSame(0, $this->service()->hasilTerbit([$hantuHasil]));

        $this->assertSame($sebelum, $this->cacahSemuaPa());
    }

    /* ------------------------------------------------------------------ *
     * Menahan banjir                                                      *
     * ------------------------------------------------------------------ */

    public function test_pengingat_terlambat_berkunci_mingguan_bukan_harian(): void
    {
        $id = $this->buatPenugasan(now()->subDays(20)->format('Y-m-d'));

        $this->service()->sudahTerlambat();

        $kunci = (string) $this->notif(PaNotificationService::TYPE_TERLAMBAT, $id)->Notification_Key;

        /* Kunci HARIAN berarti satu penugasan yang terlambat tiga puluh hari melahirkan
           tiga puluh baris — di tabel yang di-polling topbar tiap menit oleh semua orang.
           Sisi manusianya lebih dulu terasa: penilai melihat tiga puluh baris "terlambat"
           untuk SATU penugasan yang sama, lalu berhenti membaca semuanya. */
        /* Dibandingkan dengan EMBER pekannya, bukan sekadar bentuknya.
           Pola `/:\d{4}-W\d{2}$/` akan lulus sama persis untuk `date('Y-\WW')` — yaitu
           tepat bug tahun-ISO yang dihindari service. `isoFormat('GGGG-[W]WW')` adalah
           implementasi yang BERBEDA dari `date('o-\WW')`, jadi perbandingan ini bukan
           tautologi: kalau salah satunya salah, keduanya berselisih. */
        $pekan = \Carbon\Carbon::now()->isoFormat('GGGG-[W]WW');

        $this->assertStringEndsWith(
            ':'.$pekan,
            $kunci,
            "Kunci pengingat keterlambatan harus berember pekan ISO {$pekan}. Didapat: {$kunci}"
        );
    }

    public function test_pengingat_kedaluwarsa_tetapi_pengumuman_tidak(): void
    {
        $idTerlambat = $this->buatPenugasan(now()->subDays(3)->format('Y-m-d'));
        $this->service()->sudahTerlambat();

        $pengingat = $this->notif(PaNotificationService::TYPE_TERLAMBAT, $idTerlambat);

        // Pengingat digantikan terbitan berikutnya, jadi ia boleh berhenti tampil.
        $this->assertNotNull($pengingat->Expired_At, 'Pengingat wajib punya batas tampil.');

        $idBaru = $this->buatPenugasan(now()->addDays(10)->format('Y-m-d'));
        $this->service()->penugasanDibentuk([$idBaru]);

        /* Pengumuman sekali-jalan TIDAK kedaluwarsa. Penugasan baru dan hasil terbit bukan
           tagihan melainkan kabar, dan orang berhak menemukannya kembali berminggu-minggu
           kemudian — termasuk ketika ia baru kembali dari cuti panjang. */
        $this->assertNull(
            $this->notif(PaNotificationService::TYPE_DITUGASKAN, $idBaru)->Expired_At,
            'Pengumuman penugasan tidak boleh kedaluwarsa.'
        );
    }

    public function test_pengingat_yang_sudah_dibaca_tidak_menegur_ulang(): void
    {
        $id = $this->buatPenugasan(now()->subDays(3)->format('Y-m-d'));

        $this->service()->sudahTerlambat();

        $sebelum = $this->notif(PaNotificationService::TYPE_TERLAMBAT, $id);

        // Penilai membacanya.
        DB::table('N_HRIS_Notification')->where('Id_Notification', $sebelum->Id_Notification)
            ->update(['Is_Read' => 'Y', 'Read_At' => now()]);

        /* Hari berikutnya disimulasikan lewat DATA, bukan lewat jam — dan itu yang benar,
           bukan kompromi. `preserve_read` menyala pada tiga syarat saja: kunci yang sama,
           judul yang berbeda, dan penanda itu ada di muatannya. Jam tidak ada di antara
           ketiganya; di produksi ia hanya SEBAB judulnya berbeda.

           Menggesernya dengan Carbon::setTestNow() justru akan menipu: token pekan
           (`date`), saringan tenggat (`GETDATE()`), dan `selisihHari()` semuanya memakai
           jam yang TIDAK digerakkan Carbon, sehingga ujinya tampak menjelajahi waktu tanpa
           menjelajah apa pun. */
        DB::table('N_PA_Assignment')->where('Id_PA_Assignment', $id)
            ->update(['Due_At' => now()->subDays(4)->format('Y-m-d')]);

        $this->service()->sudahTerlambat();

        $sesudah = $this->notif(PaNotificationService::TYPE_TERLAMBAT, $id);

        /* Dua prasyarat SEBELUM penegasan utama. Kalau kelak kuncinya dibuat bergantung
           pada Due_At, jalan kedua akan menyisipkan baris BARU ber-Is_Read='T' bawaan, lalu
           penegasan di bawah memungut baris yang lain dan uji ini merah-hijau bergantian
           tanpa sebab yang berubah. */
        $this->assertSame($sebelum->Notification_Key, $sesudah->Notification_Key,
            'Kunci harus tetap; kalau tidak, penegasan di bawah menguji baris yang lain.');
        $this->assertSame(1, $this->cacahNotif(PaNotificationService::TYPE_TERLAMBAT, [$id]));

        // Judulnya DIPERBARUI supaya angkanya tetap akurat...
        $this->assertNotSame($sebelum->Title, $sesudah->Title,
            'Judul harus berubah; kalau tidak, penjaga di bawah tidak menyentuh apa pun.');
        $this->assertStringContainsString('4 hari', $sesudah->Title);

        // ...tetapi yang sudah dibaca TETAP terbaca. Tanpa ini, penilai melihat baris yang
        // sama menyala kembali setiap hari, dan berhenti membaca semuanya.
        $this->assertSame('Y', $sesudah->Is_Read);
    }

    /* ------------------------------------------------------------------ *
     * Bangunan uji                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Satu baris pemberitahuan milik uji INI, dikenali lewat Reference_Id.
     *
     * `first()` tanpa `ORDER BY` di SQL Server TIDAK punya urutan yang dijamin. Begitu dev
     * memuat satu saja pemberitahuan PA sungguhan — yang justru tujuan modul ini — pencarian
     * yang hanya menyaring `Type` bisa memulangkan baris milik orang asing, lalu uji
     * menegaskan nama dan pesan terhadapnya. Kegagalannya akan menuduh service membocorkan
     * nama orang lain, dan tak ada apa pun di pesan gagalnya yang menunjuk sebab sebenarnya.
     */
    private function notif(string $type, int $referensi): object
    {
        $baris = DB::table('N_HRIS_Notification')
            ->where('Type', $type)
            ->where('Reference_Id', (string) $referensi)
            ->first();

        $this->assertNotNull($baris, "Pemberitahuan {$type} untuk referensi {$referensi} tidak ada.");

        return $baris;
    }

    /**
     * Cacah SELURUH pemberitahuan PA — dilingkupi prefiks, bukan seluruh tabel.
     *
     * `[_]` meng-escape garis bawah, yang di LIKE berarti "satu karakter apa pun".
     */
    private function cacahSemuaPa(): int
    {
        return (int) DB::table('N_HRIS_Notification')
            ->where('Type', 'like', 'PA[_]%')
            ->count();
    }

    /**
     * Cacah pemberitahuan untuk penugasan/hasil yang dibuat uji INI saja.
     *
     * `sudahTerlambat()` dan `tenggatMendekat()` menyapu SELURUH basis data — memang
     * begitu maksudnya, keduanya pemindai harian. Tetapi menuntut angka mutlak dari
     * nilai baliknya berarti uji ini hijau hanya selama dev kebetulan tidak punya siklus
     * OPEN bertenggat — yaitu tepat sampai seseorang membuat satu, yang justru tujuan
     * seluruh modul ini.
     *
     * Karena itu yang ditegaskan adalah BARIS milik uji ini, dikenali lewat Reference_Id.
     *
     * @param  list<int>  $referensi
     */
    private function cacahNotif(string $type, array $referensi): int
    {
        return (int) DB::table('N_HRIS_Notification')
            ->where('Type', $type)
            ->whereIn('Reference_Id', array_map('strval', $referensi))
            ->count();
    }

    private function service(): PaNotificationService
    {
        return $this->app->make(PaNotificationService::class);
    }

    private function buatSiklus(string $status): int
    {
        /* HURUF saja, tanpa angka. `bin2hex` menghasilkan heksadesimal yang bisa memuat
           "82", dan nama siklus ini ikut masuk ke pesan pemberitahuan — sehingga uji
           "nilai tidak disebut" akan menuduh servicenya membocorkan nilai sekitar sekali
           dari empat puluh jalan, padahal yang bocor nama buatan uji itu sendiri.
           Uji yang merah sepersekian waktu tanpa sebab yang berubah adalah uji yang akan
           diabaikan orang. */
        $nama = 'Siklus Uji Notifikasi '.substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);

        DB::table('N_PA_Cycle')->insert([
            'Kode_Cycle' => 'UJI-NOTIF-'.substr(bin2hex(random_bytes(4)), 0, 8),
            'Nama_Cycle' => $nama,
            'Tahun' => (int) date('Y'),
            'Status_Cycle' => $status,
        ]);

        return (int) DB::table('N_PA_Cycle')->where('Nama_Cycle', $nama)->value('Id_PA_Cycle');
    }

    private function buatPeserta(): int
    {
        $k = $this->stok[0];
        $levelId = (int) DB::table('HRIS_Level')->orderBy('ID_Level')->value('ID_Level');

        DB::table('N_PA_Participant')->insert([
            'PA_Cycle_Id' => $this->cycleId,
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'Nama_Karyawan_Saat_Cycle' => $k->Nama,
            'ID_Level_Jabatan_Saat_Cycle' => $k->ID_Level_Jabatan,
            'ID_Level_Saat_Cycle' => $levelId,
            'Status_Peserta' => 'DIIKUTKAN',
        ]);

        return (int) DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $this->cycleId)
            ->where('Kode_Karyawan', $k->Kode_Karyawan)
            ->value('Id_PA_Participant');
    }

    private function buatPenugasan(?string $dueAt, string $status = 'BELUM_MULAI'): int
    {
        /* Penilai BERBEDA tiap panggilan: UX_PA_Assignment_Peserta_Penilai unik per
           (peserta, penilai, kategori) dan menyaring Flag_Aktif, bukan status — jadi
           penilai yang sama dua kali bentrok walau salah satunya dibatalkan. */
        $penilai = $this->stok[$this->penunjukPenilai] ?? null;
        $this->assertNotNull($penilai, 'Stok penilai uji habis; tambah limit di setUp().');
        $this->penunjukPenilai++;

        DB::table('N_PA_Assignment')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Kode_Perusahaan_Penilai' => $penilai->Kode_Perusahaan,
            'Kode_Karyawan_Penilai' => $penilai->Kode_Karyawan,
            'Nama_Penilai_Saat_Cycle' => $penilai->Nama,
            'PA_Rater_Category_Id' => $this->kategoriId,
            'Bobot_Dalam_Kategori' => 100,
            'Status_Penugasan' => $status,
            'Due_At' => $dueAt,
        ]);

        return (int) DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $this->participantId)
            ->where('Kode_Karyawan_Penilai', $penilai->Kode_Karyawan)
            ->value('Id_PA_Assignment');
    }

    private function buatHasil(string $status, float $skor, string $grade): int
    {
        DB::table('N_PA_Result')->insert([
            'PA_Participant_Id' => $this->participantId,
            'Final_Score' => $skor,
            'Kode_Grade_Final' => $grade,
            'Kode_Grade_Sistem' => $grade,
            'Status_Hasil' => $status,
        ]);

        return (int) DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $this->participantId)
            ->value('Id_PA_Result');
    }
}
