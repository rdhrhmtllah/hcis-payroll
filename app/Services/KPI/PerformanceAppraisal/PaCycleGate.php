<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Satu-satunya tempat yang menjawab "operasi ini masih boleh tidak, mengingat status
 * siklusnya sekarang".
 *
 * Empat aturan yang sebelumnya hanya hidup sebagai kalimat di README bermuara ke sini:
 * salinan aturan membeku saat kunci, paket soal tidak boleh diganti sesudah kunci,
 * override per peserta tidak boleh ditulis sesudah kunci, dan payload tidak boleh
 * diterima sesudah siklus ditutup.
 *
 * KENAPA SATU TEMPAT, BUKAN EMPAT PEMERIKSAAN
 *
 * Keempatnya akan dipanggil dari tempat yang berbeda-beda, dan bentuk kegagalannya
 * sama: satu jalur lupa memeriksa, lalu aturan yang dijanjikan skema diam-diam tidak
 * berlaku di jalur itu. Modul SOP di proyek ini sudah pernah kena persis itu — aturan
 * terpasang di satu dari dua pintu yang setara, dan celahnya baru ketahuan berhari-hari
 * kemudian. Delapan pintu jauh lebih mudah bocor daripada satu.
 *
 * TARUHANNYA LEBIH BESAR DARIPADA KELIHATANNYA
 *
 * Lapisan salinan siklus ADA justru karena janji "aturan membeku saat dikunci". Kalau
 * janji itu tidak ditegakkan, seluruh alasan keberadaan lapisan kedua runtuh sementara
 * skemanya tetap terlihat rapi. Gerbang ini yang membuat janji itu benar.
 */
class PaCycleGate
{
    /* ---- Operasi yang digerbangi ---- */

    public const SUNTING_SALINAN = 'SUNTING_SALINAN';

    public const SUNTING_BOBOT_PILAR = 'SUNTING_BOBOT_PILAR';

    public const PILIH_PAKET_SOAL = 'PILIH_PAKET_SOAL';

    public const SUNTING_PESERTA = 'SUNTING_PESERTA';

    public const TULIS_OVERRIDE = 'TULIS_OVERRIDE';

    public const BUAT_PENUGASAN = 'BUAT_PENUGASAN';

    /**
     * Menambah penilai pada peserta yang siklusnya SUDAH BERJALAN.
     *
     * Dipisahkan dari BUAT_PENUGASAN karena keduanya pertanyaan berbeda. Pembentukan
     * massal memang milik penyiapan: menjalankannya di tengah siklus akan melahirkan
     * ribuan penugasan baru sekaligus. Menambah SATU penilai untuk satu orang adalah hal
     * lain — itu yang dibutuhkan ketika penilai resign atau pindah unit dan harus diganti.
     *
     * Aman terhadap angka yang sudah ada: peserta yang ditambahi penilai otomatis
     * terhitung belum lengkap lagi, sehingga hasilnya TERTAHAN sampai penilai barunya
     * mengisi — bukan berubah diam-diam. Hasil yang sudah TERBIT dijaga terpisah di
     * `tugaskan()`.
     */
    public const TAMBAH_PENILAI = 'TAMBAH_PENILAI';

    /**
     * Membatalkan satu penugasan.
     *
     * Sebelumnya menumpang BUAT_PENUGASAN, sehingga mustahil sesudah siklus dibuka —
     * padahal justru di situ kebutuhannya muncul: peserta resign, penilai pindah unit.
     * Tanpa jalan resmi, satu-satunya jalan adalah SQL manual, dan di sana `Cancel_Reason`,
     * `Cancelled_By`, serta pencabutan tokennya semua terlewat.
     *
     * CLOSED dan PUBLISHED tetap ditutup: di sana admin sedang memverifikasi atau sudah
     * menerbitkan, dan mengubah masukannya membatalkan yang sedang dilihat orang. Bila
     * memang perlu, siklusnya dibuka lagi — CLOSED->OPEN sudah diizinkan dan meninggalkan
     * jejak.
     */
    public const BATALKAN_PENUGASAN = 'BATALKAN_PENUGASAN';

    /**
     * Mengeluarkan satu peserta dari siklus yang SUDAH BERJALAN.
     *
     * Dipisahkan dari SUNTING_PESERTA karena keduanya pertanyaan berbeda. SUNTING_PESERTA
     * menjaga daftar peserta sebagai daftar — termasuk mengikutkan orang kembali, dan itu
     * memang hanya bermakna sebelum kunci: mengikutkan orang di tengah siklus melahirkan
     * peserta tanpa satu pun penilai, sementara pembentukan penugasan berhenti di PREPARED.
     * Ia akan duduk di siklus selamanya menunggu angka yang tidak pernah bisa dihitung.
     *
     * MENGELUARKAN orang adalah hal lain. Peserta resign, dipindah ke perusahaan lain, atau
     * cuti panjang — semuanya muncul justru sesudah pengisian dibuka. Tanpa jalan resmi,
     * penugasannya tetap hidup di daftar tugas para penilainya, menagih pekerjaan untuk
     * orang yang tidak akan dinilai, dan satu-satunya jalan keluar adalah SQL manual.
     *
     * Pengeluarannya membawa serta pembatalan penugasan — mekanismenya sudah ada di
     * `PaParticipantEditService::batalkanPenugasan()` dan lewat `BATALKAN_PENUGASAN` yang
     * memang sudah terbuka sampai OPEN.
     *
     * CLOSED dan PUBLISHED tetap ditutup, sama alasannya dengan BATALKAN_PENUGASAN.
     */
    public const KECUALIKAN_PESERTA = 'KECUALIKAN_PESERTA';

    /**
     * Mencetak ULANG pintu HC Learn milik satu penugasan.
     *
     * Inilah pemicu `pa/reassign` dalam kontrak HC Learn. Yang dibutuhkan ketika paket soal
     * satu sel disalin ulang (soal tertukar, bobot salah, ikatan menunjuk butir yang sudah
     * dicabut) atau ketika tokennya sendiri rusak: penilai yang sama, sel yang sama, ujian
     * yang diterbitkan ulang.
     *
     * BUKAN `PILIH_PAKET_SOAL`, dan pemisahan itu bukan kerapian. Operasi itu dipakai tujuh
     * tempat termasuk penyuntingan ikatan soal, sehingga melonggarkannya sampai `OPEN`
     * berarti ISI soal bisa berubah selagi orang menjawabnya. Yang ini tidak menyentuh satu
     * butir pun — ia hanya menerbitkan ulang pintunya.
     *
     * `CLOSED` dan `PUBLISHED` tetap ditutup, sama alasannya dengan BATALKAN_PENUGASAN.
     * Penjaga per orang — penugasan yang sudah dikerjakan, hasil yang sudah terbit — ada di
     * servicenya, bukan di matriks ini: matriks ini menjawab "status siklus mengizinkan apa".
     */
    public const CETAK_ULANG_PINTU = 'CETAK_ULANG_PINTU';

    public const TERIMA_PAYLOAD = 'TERIMA_PAYLOAD';

    public const HITUNG_HASIL = 'HITUNG_HASIL';

    public const KALIBRASI = 'KALIBRASI';

    public const TERBITKAN = 'TERBITKAN';

    /**
     * Membuka hasil untuk LEADER di halaman PA Tim — bukan untuk karyawan yang dinilai.
     *
     * Sengaja sekendur `TERBITKAN` (OPEN, CLOSED): keduanya menjawab pertanyaan yang sama,
     * "bolehkah angka dilihat di luar admin", hanya kepada pembaca yang berbeda. Siapa yang
     * boleh melihat apa bukan urusan status siklus, melainkan penanda per hasil.
     */
    public const RILIS_LEADER = 'RILIS_LEADER';

    /**
     * Kalibrasi Penilai: penilai mengubah skornya sendiri per kompetensi sebelum mengirim.
     *
     * Hanya OPEN. Berbeda dari KALIBRASI (forum admin atas grade akhir) yang juga menerima
     * CLOSED: penutupan pengisian melepas seluruh lembar TERKUMPUL menjadi SELESAI, jadi
     * sesudah CLOSED memang tidak ada lembar yang boleh dikalibrasi penilainya.
     */
    public const KALIBRASI_PENILAI = 'KALIBRASI_PENILAI';

    /**
     * Perpindahan status siklus itu sendiri.
     *
     * Ketiganya baru pada 2 September 2026, dan lahir dari satu temuan: sampai hari itu
     * `kunci()` adalah SATU-SATUNYA perpindahan status yang tidak digerbangi, sementara
     * `OPEN` dan `CLOSED` sama sekali tidak punya penulis di kode produksi — hanya
     * perintah data contoh yang menulisnya. Empat operasi inti (terima payload, hitung,
     * kalibrasi, terbitkan) bergerbang pada dua status yang tak terjangkau, sehingga
     * modulnya tidak bisa dijalani sampai selesai lewat aplikasi.
     *
     * Perpindahan status kini diperlakukan sebagai operasi bergerbang seperti yang lain,
     * bukan sebagai `update()` biasa. Itu yang membuat aturannya terbaca di satu tempat.
     */
    public const KUNCI_SIKLUS = 'KUNCI_SIKLUS';

    public const BUKA_PENGISIAN = 'BUKA_PENGISIAN';

    public const TUTUP_PENGISIAN = 'TUTUP_PENGISIAN';

    /**
     * Kurva grade (siklus bermetode KURVA): membekukan rata-rata, simpangan baku, dan pita
     * absolut per grup level. HANYA saat CLOSED: kurva adalah satu-satunya tempat nilai
     * seseorang bergantung pada nilai orang lain, jadi ia dibekukan sesudah jendela pengisian
     * tertutup — mengunci selagi OPEN membekukan kurva dari sebagian peserta saja, dan yang
     * menyusul menerima pita yang tidak ikut mereka bentuk.
     */
    public const KUNCI_KURVA = 'KUNCI_KURVA';

    /** Membuka kurva terkunci — wajib alasan, ditolak service bila sudah ada hasil terbit. */
    public const BUKA_KURVA = 'BUKA_KURVA';

    /** Sesi Balancing dan hitung ulangnya: hanya sesudah pengisian ditutup. */
    public const BALANCING = 'BALANCING';

    /**
     * Status siklus tempat tiap operasi masih diizinkan.
     *
     * Dibaca sebagai daftar putih, bukan daftar larangan: status yang tidak disebut
     * berarti dilarang. Itu disengaja — aturan yang bekerja dengan menyebut larangan
     * akan diam-diam mengizinkan status baru yang kelak ditambahkan.
     *
     * @var array<string, list<string>>
     */
    private const IZIN = [
        /* Persiapan. Membeku begitu siklus dikunci. */
        self::SUNTING_SALINAN => ['DRAFT', 'PREPARED'],
        self::PILIH_PAKET_SOAL => ['DRAFT', 'PREPARED'],
        self::SUNTING_PESERTA => ['DRAFT', 'PREPARED'],
        self::TULIS_OVERRIDE => ['DRAFT', 'PREPARED'],
        self::BUAT_PENUGASAN => ['DRAFT', 'PREPARED'],
        self::TAMBAH_PENILAI => ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN'],
        self::BATALKAN_PENUGASAN => ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN'],
        self::KECUALIKAN_PESERTA => ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN'],
        self::CETAK_ULANG_PINTU => ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN'],

        /* PENGECUALIAN YANG DISENGAJA (K23). Bobot pilar satu-satunya salinan siklus
           yang TIDAK ikut beku saat kunci: admin boleh mengubah pembagian KPI vs
           kompetensi bahkan setelah semua orang selesai menilai, karena itu tidak
           menyentuh siapa menilai siapa. Berhenti di CLOSED, bukan di LOCKED. */
        self::SUNTING_BOBOT_PILAR => ['DRAFT', 'PREPARED', 'LOCKED', 'OPEN', 'CLOSED'],

        /* Pengisian. Diterima selama jendela terbuka, dan masih diterima saat CLOSED
           supaya hasil yang dikirim HC Learn tepat sebelum penutupan tidak hilang.
           Berhenti di PUBLISHED: hasil yang sudah diterbitkan tidak boleh berubah
           diam-diam. */
        self::TERIMA_PAYLOAD => ['OPEN', 'CLOSED'],

        /*
         | Pascapengisian — DAN SELAMA PENGISIAN, per orang.
         |
         | Ketiganya semula `['CLOSED']` saja. Akibatnya nilai satu orang bergantung pada orang
         | lain: peserta yang penilainya lengkap sejak minggu pertama tetap menunggu sampai
         | jendela ditutup untuk SEMUA, dan lima orang yang telat menahan delapan ratus yang
         | sudah beres.
         |
         | Kebergantungan itu tidak pernah ada di matematikanya. Grade dicari di pita ABSOLUT
         | (`Nilai_Dari <= n < Nilai_Sampai`), kuadran HAV dari dua pita absolut, dan kalibrasi
         | bekerja pada satu hasil. Tidak ada distribusi paksa, peringkat, maupun kurva — nilai
         | seseorang tetap sama entah nol atau delapan ratus orang lain sudah selesai. Yang
         | mengikat mereka hanyalah tiga baris ini: gerbang PROSES, bukan gerbang kebenaran.
         |
         | `OPEN` kini diterima, TETAPI status siklus bukan lagi satu-satunya syarat. Menghitung
         | selagi jendela terbuka menuntut peserta itu sendiri sudah lengkap — lihat
         | `PaResultCalculationService::pastikanPesertaLengkap()`. Syarat kedua itu sengaja TIDAK
         | ditaruh di matriks ini: matriks ini menjawab "status siklus mengizinkan apa", dan
         | menyelipkan pertanyaan tentang satu orang ke dalamnya membuat gerbang yang jawabannya
         | berbeda-beda untuk siklus yang sama.
         |
         | Operasi MASSAL tingkat siklus tetap menuntut `CLOSED`; penjaganya ada di masing-masing
         | method, bukan di sini.
         */
        self::HITUNG_HASIL => ['OPEN', 'CLOSED'],
        self::KALIBRASI => ['OPEN', 'CLOSED'],
        self::TERBITKAN => ['OPEN', 'CLOSED'],
        self::RILIS_LEADER => ['OPEN', 'CLOSED'],
        self::KALIBRASI_PENILAI => ['OPEN'],
        self::KUNCI_KURVA => ['CLOSED'],
        self::BUKA_KURVA => ['OPEN', 'CLOSED'],
        self::BALANCING => ['CLOSED'],

        /* Perpindahan status.

           `KUNCI_SIKLUS` hanya dari `PREPARED`, dan itu memperbaiki cacat nyata: sebelum
           2 September 2026 `kunci()` tidak digerbangi sama sekali, sehingga siklus CLOSED
           yang hasilnya SUDAH TERBIT bisa dikembalikan ke LOCKED. Dibuktikan dengan
           menjalankannya, bukan dengan membaca kode. Digabung dengan ketiadaan jalur
           kembali, satu penekanan tombol yang salah sasaran mematikan siklus selamanya.

           `BUKA_PENGISIAN` sengaja menerima `CLOSED`, bukan hanya `LOCKED`. Menutup
           pengisian terlalu cepat adalah kekeliruan yang WAJAR — penilai minta perpanjangan,
           atau ada yang ketinggalan. Aturan yang tidak menyediakan jalan kembali untuk
           kekeliruan yang wajar hanya memindahkan perbaikannya ke SQL manual, dan di sanalah
           kerusakan yang sesungguhnya terjadi. Berhenti di `PUBLISHED`: hasil yang sudah
           sampai ke karyawan tidak boleh berubah karena ada jawaban susulan.

           TIDAK ADA "buka kunci" (LOCKED -> PREPARED), dan itu keputusan, bukan kelupaan.
           Mengunci membekukan aturan penilai dan melahirkan penugasan; membukanya kembali
           berarti mencabut aturan yang sudah dipakai orang menilai. Kalau kelak memang
           dibutuhkan, ia butuh rancangannya sendiri — bukan satu baris tambahan di sini. */
        self::KUNCI_SIKLUS => ['PREPARED'],
        self::BUKA_PENGISIAN => ['LOCKED', 'CLOSED'],
        self::TUTUP_PENGISIAN => ['OPEN'],
    ];

    /** @var array<int, string> memo status per siklus, per instance */
    private array $memo = [];

    /**
     * Memo peserta -> siklus.
     *
     * Tanpa ini, menghitung satu siklus berisi ratusan peserta menanyakan "peserta ini
     * milik siklus mana" sekali per orang - pertanyaan yang jawabannya sudah diketahui
     * pemanggilnya.
     *
     * @var array<int, int>
     */
    private array $memoPeserta = [];

    /**
     * Melempar bila operasi tidak diizinkan. Inilah bentuk yang seharusnya dipakai
     * pemanggil — mengembalikan boolean mengundang orang lupa memeriksanya.
     */
    public function pastikanBoleh(string $operasi, int $cycleId): void
    {
        $status = $this->status($cycleId);

        if ($this->bolehPadaStatus($operasi, $status)) {
            return;
        }

        $izin = implode(', ', self::IZIN[$operasi]);

        throw new RuntimeException(
            "Operasi {$operasi} tidak diizinkan saat siklus berstatus {$status}. "
            ."Hanya boleh pada: {$izin}."
        );
    }

    public function boleh(string $operasi, int $cycleId): bool
    {
        return $this->bolehPadaStatus($operasi, $this->status($cycleId));
    }

    /**
     * Versi untuk pemanggil yang memegang peserta, bukan siklus. Disediakan supaya
     * tidak ada yang tergoda mencari sendiri siklusnya lalu lupa memanggil gerbangnya.
     */
    public function pastikanBolehUntukPeserta(string $operasi, int $participantId): void
    {
        $this->pastikanBoleh($operasi, $this->cycleIdPeserta($participantId));
    }

    /**
     * Versi untuk pemanggil yang memegang penugasan — jalur callback HC Learn.
     */
    public function pastikanBolehUntukPenugasan(string $operasi, int $assignmentId): void
    {
        $cycleId = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('a.Id_PA_Assignment', $assignmentId)
            ->value('p.PA_Cycle_Id');

        if ($cycleId === null) {
            throw new RuntimeException("Penugasan {$assignmentId} tidak ditemukan.");
        }

        $this->pastikanBoleh($operasi, (int) $cycleId);
    }

    /**
     * Daftar operasi yang masih boleh pada satu siklus. Dipakai layar untuk menonaktifkan
     * tombol, supaya larangan terlihat sebelum diklik, bukan sesudah.
     *
     * @return list<string>
     */
    public function operasiYangBoleh(int $cycleId): array
    {
        return $this->operasiUntukStatus($this->status($cycleId));
    }

    /**
     * Versi untuk pemanggil yang SUDAH memegang statusnya.
     *
     * Layar daftar siklus sudah membaca Status_Cycle di kueri utamanya. Memintanya
     * menanyakan gerbang per baris berarti satu kueri tambahan untuk jawaban yang
     * sudah ada di tangan. Matriksnya tetap satu - hanya sumber statusnya yang berbeda.
     *
     * @return list<string>
     */
    public function operasiUntukStatus(string $status): array
    {
        return array_values(array_filter(
            array_keys(self::IZIN),
            fn (string $op): bool => $this->bolehPadaStatus($op, $status)
        ));
    }

    /**
     * Kebalikan `operasiUntukStatus()`: status mana saja yang masih membolehkan satu
     * operasi.
     *
     * Dipakai untuk menjelaskan larangan, bukan menegakkannya. Layar yang hanya bisa
     * berkata "tidak boleh" memaksa admin menebak apa yang harus ia lakukan supaya boleh;
     * dengan daftar ini ia bisa berkata "hanya saat DRAFT atau PREPARED, sekarang LOCKED".
     *
     * @return list<string>
     */
    public function statusUntukOperasi(string $operasi): array
    {
        return self::IZIN[$operasi] ?? [];
    }

    public function lupakanMemo(): void
    {
        $this->memo = [];
        $this->memoPeserta = [];
    }

    private function bolehPadaStatus(string $operasi, string $status): bool
    {
        if (! array_key_exists($operasi, self::IZIN)) {
            // Operasi yang tak dikenal DITOLAK, bukan diizinkan. Salah ketik nama
            // operasi harus gagal keras, bukan diam-diam melewati gerbang.
            throw new RuntimeException("Operasi {$operasi} tidak dikenal gerbang siklus.");
        }

        return in_array($status, self::IZIN[$operasi], true);
    }

    /**
     | Status siklus, dimemokan.
     |
     | PUBLIK sejak 4 September 2026. Gerbang ini adalah satu-satunya pemilik pengetahuan status
     | siklus di modul PA — memaksa pemanggil membaca `N_PA_Cycle.Status_Cycle` sendiri hanya
     | untuk bertanya "sudah terkunci belum" akan melahirkan sumber kedua yang pasti menyimpang
     | dari gerbangnya sendiri.
     |
     | Membacanya BUKAN pengganti `pastikanBoleh()`. Yang menentukan boleh atau tidak tetap
     | matriks izin; ini hanya untuk membedakan "ditolak" dari "sudah berada di sana".
     */
    public function status(int $cycleId): string
    {
        if (! array_key_exists($cycleId, $this->memo)) {
            $status = DB::table('N_PA_Cycle')
                ->where('Id_PA_Cycle', $cycleId)
                ->value('Status_Cycle');

            if ($status === null) {
                throw new RuntimeException("Siklus PA {$cycleId} tidak ditemukan.");
            }

            $this->memo[$cycleId] = (string) $status;
        }

        return $this->memo[$cycleId];
    }

    private function cycleIdPeserta(int $participantId): int
    {
        if (isset($this->memoPeserta[$participantId])) {
            return $this->memoPeserta[$participantId];
        }

        $cycleId = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $participantId)
            ->value('PA_Cycle_Id');

        if ($cycleId === null) {
            throw new RuntimeException("Peserta PA {$participantId} tidak ditemukan.");
        }

        return $this->memoPeserta[$participantId] = (int) $cycleId;
    }
}
