<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sisi peserta — apa yang dilihat 958 orang.
 *
 * SATU ATURAN YANG MENGATASI SEMUA ATURAN LAIN DI BERKAS INI
 *
 * Lingkupnya ditentukan dari USER YANG LOGIN, tidak pernah dari parameter. Tidak ada satu
 * pun method di sini yang menerima `participantId` atau `kodeKaryawan` dari pemanggil.
 * Endpoint yang menerima id peserta lalu mempercayainya adalah cara paling lazim membuat
 * orang bisa membaca hasil penilaian rekannya hanya dengan mengganti angka di alamat —
 * dan tidak ada yang menyadarinya sampai seseorang mencoba.
 *
 * HANYA HASIL YANG SUDAH TERBIT
 *
 * `DIHITUNG` berarti angkanya sudah ada tetapi belum diterbitkan. Menampilkannya kepada
 * karyawan meniadakan seluruh gunanya langkah penerbitan: admin kehilangan kesempatan
 * mengkalibrasi sebelum orang melihat angkanya. `TERTAHAN` apalagi — sistem menolak
 * memberi angka, dan menunjukkannya berarti menyodorkan sesuatu yang tidak sah.
 *
 * PESERTA TIDAK PERNAH MELIHAT NILAI PER PENILAI
 *
 * Prinsip 4 rancangan: karyawan hanya melihat kesimpulan akhir. Radar per kompetensi dan
 * nilai akhir, ya; "atasan memberi 90, bawahan memberi 80", tidak pernah. Itu bukan
 * kelalaian melainkan yang menghapus kebutuhan aturan "minimal N penilai sebelum skor
 * boleh ditampilkan" — tidak ada agregat kecil yang dipertontonkan kepada subjeknya.
 * Karena itu method di sini SENGAJA tidak mengembalikan `N_PA_Result_Rater_Category`,
 * sementara jalur admin mengembalikannya.
 */
class PaPesertaService
{
    public function __construct(
        /* Dipakai memutuskan apakah lembar penilaian simulasi sedang berlaku. Tidak melingkar:
           layanan penjadwalan tidak mengenal layanan ini. */
        private readonly PaHcLearnScheduleService $jadwal,
    ) {}

    /** Ambang "deadline dekat" pada dashboard, dalam hari. */
    public const AMBANG_DEKAT_HARI = 3;

    public const MAKS_PER_HALAMAN = 100;

    /**
     | STATUS SIKLUS YANG TUGASNYA BENAR-BENAR BISA DIKERJAKAN.
     |
     | Antrean tugas seseorang hanya boleh memuat pekerjaan yang MUNGKIN ia kerjakan. Pengisian
     | dibuka pada `OPEN` dan hanya di sana; `LOCKED` belum membukanya, `CLOSED` sudah
     | menutupnya, dan `DRAFT`/`PREPARED` susunan penilainya bahkan masih bisa berubah.
     |
     | Sebelumnya tidak ada saringan status sama sekali, dan akibatnya bukan sekadar baris
     | berlebih. Tugas dari siklus yang sudah DITUTUP ikut masuk penyebut, sehingga:
     |
     |   - `progres_persen` mustahil mencapai 100 selamanya, karena pembilangnya tidak akan
     |     pernah bertambah — formulirnya sudah tidak bisa dibuka;
     |   - `terlambat` menghitungnya sebagai keterlambatan yang masih berjalan, padahal tidak
     |     ada tindakan apa pun yang bisa menghapusnya. Lencana merah permanen untuk pekerjaan
     |     yang tidak lagi menjadi pekerjaan siapa pun.
     |
     | Catatan kelas ini sendiri sudah menyebut aturannya untuk kasus lain: "penyebut yang
     | memuatnya membuat 100% mustahil selamanya". Yang kurang hanyalah memberlakukannya pada
     | status siklus.
     */
    public const STATUS_SIKLUS_DAPAT_DIISI = ['OPEN'];

    /**
     * Urutan kronologis siklus: kapan PERIODE YANG DINILAI berakhir, bukan tahun lalu id.
     * Dua siklus setahun bisa dibuat dalam urutan apa pun (siklus Jan–Jun dibuat sebelum
     * siklus Jan–Mar), dan "tren dibanding sebelumnya" lalu membandingkan dengan siklus yang
     * keliru. Siklus tanpa periode jatuh ke akhir tahunnya.
     */
    public const URUTAN_PERIODE = 'COALESCE(k.Periode_Dinilai_Sampai, DATEFROMPARTS(k.Tahun, 12, 31))';

    /**
     * Kolom baris tugas. Satu daftar untuk daftar tugas lama dan wizard, karena `barisTugas()`
     * membaca kolom-kolom ini; dua salinan daftar berarti satu layar kehilangan tautan lembar
     * begitu kolom ditambah di salinan yang lain.
     */
    public const KOLOM_TUGAS = [
        'a.Id_PA_Assignment', 'a.Status_Penugasan', 'a.Due_At', 'a.Started_At', 'a.Finished_At',
        'c.Kode_Kategori', 'c.Nama_Kategori',
        'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
        'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle',
        'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun',
        'i.Id_Ujian_Token', 'i.Link_Ujian', 'i.Short_Token', 'i.Akses_Otp',
        'i.Status_Integrasi',
    ];

    /**
     * Nama kategori penilai yang dilihat karyawan di halaman PA Saya. Master menyebut PEER
     * "Rekan Sejawat"; di halaman ini istilah yang dipakai sehari-hari adalah "Peer".
     * Hanya untuk halaman ini — layar admin tetap memakai nama master.
     */
    public static function namaKategoriPenilai(?string $kode, ?string $nama): string
    {
        return $kode === 'PEER' ? 'Peer' : (string) ($nama ?? $kode);
    }

    /**
     * `KOLOM_TUGAS` ditambah penanda kiriman tertahan.
     *
     * KIRIMAN TERTAHAN: HC Learn sudah mengirim jawaban FINAL, tetapi penugasannya tidak naik
     * ke TERKUMPUL karena butirnya tidak cocok dengan paket soal (lihat
     * `PaPayloadMapperService`, `kontrakLengkap`). Kiriman itu menunggu admin di "Kiriman
     * perlu ditinjau". Tanpa penanda ini penilai yang sudah mengisi melihat "Belum dimulai"
     * dan mengira jawabannya hilang.
     *
     * @return array<int, mixed>
     */
    public static function kolomTugas(): array
    {
        return [...self::KOLOM_TUGAS, DB::raw(
            "CASE WHEN a.Status_Penugasan IN ('".PaStatusPenugasan::BELUM_MULAI."', '".PaStatusPenugasan::BERJALAN."')
                AND EXISTS (SELECT 1 FROM N_PA_Assignment_Payload y
                    WHERE y.PA_Assignment_Integration_Id = i.Id_PA_Assignment_Integration
                    AND y.Status_Skor = '".PaPayloadMapperService::SKOR_FINAL."')
                THEN 'Y' ELSE 'T' END AS Flag_Kiriman_Tertahan"
        )];
    }

    /**
     * Enam penghitung dashboard beserta progres pengisian.
     *
     * Yang dihitung adalah penugasan tempat orang ini menjadi PENILAI — bukan tempat ia
     * dinilai. Keduanya mudah tertukar: yang pertama adalah pekerjaannya, yang kedua
     * hasilnya.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(int $userId, ?int $cycleId = null): array
    {
        $saya = $this->karyawan($userId);

        $baris = $this->kueriPenugasan($saya, $cycleId)
            ->get(['a.Status_Penugasan', 'a.Due_At']);

        $total = $baris->count();
        $belum = 0;
        $berjalan = 0;
        $selesai = 0;
        $terkirim = 0;
        $dekat = 0;
        $terlambat = 0;

        foreach ($baris as $b) {
            /* 'selesai' = SUDAH DIISI (`SUDAH_DIKERJAKAN`): TERKUMPUL dan MENUNGGU_VERIFIKASI
               ikut. Yang diukur pekerjaan MENGISI; menghitung lembar terkumpul sebagai belum
               membuat orang yang baru menuntaskannya melihat 0%. MENUNGGU_VERIFIKASI dulu tidak
               masuk ember mana pun, sehingga persentasenya tidak pernah bisa 100%.

               Pengirimannya dihitung TERPISAH ('sudah_terkirim'): "terisi" bukan "selesai",
               dan layar wajib bisa mengatakan masih ada yang belum dikirim. */
            if (in_array($b->Status_Penugasan, PaStatusPenugasan::SUDAH_DIKERJAKAN, true)) {
                $selesai++;
            } elseif ($b->Status_Penugasan === PaStatusPenugasan::BERJALAN) {
                $berjalan++;
            } elseif ($b->Status_Penugasan === PaStatusPenugasan::BELUM_MULAI) {
                $belum++;
            }

            if ($b->Status_Penugasan === PaStatusPenugasan::SELESAI) {
                $terkirim++;
            }

            // Lembar yang sudah diisi dijaga `keterlambatan()` sendiri; satu penjaga saja.
            $k = $this->keterlambatan($b);

            if ($k['terlambat']) {
                $terlambat++;
            } elseif ($k['sisa_hari'] !== null && $k['sisa_hari'] <= self::AMBANG_DEKAT_HARI) {
                $dekat++;
            }
        }

        return [
            'karyawan' => [
                'kode_karyawan' => $saya->Kode_Karyawan,
                'nama' => $saya->Nama,
            ],
            'total_assignment' => $total,
            'belum_dimulai' => $belum,
            'sedang_dikerjakan' => $berjalan,
            'sudah_selesai' => $selesai,
            'sudah_terkirim' => $terkirim,
            'belum_dikirim' => $selesai - $terkirim,
            'deadline_dekat' => $dekat,
            'terlambat' => $terlambat,
            // Persentase dihitung di sini, bukan di layar. Layar yang menghitung sendiri
            // akan membulatkan dengan caranya sendiri, dan dua angka berbeda untuk satu
            // gagasan mustahil dijelaskan.
            'progres_persen' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
            'ambang_dekat_hari' => self::AMBANG_DEKAT_HARI,

            /* Tugas yang DISARING keluar, beserta sebabnya.
               Menyaring tanpa mengatakannya adalah bentuk kebohongan yang lain: orang yang
               ingat pernah punya delapan tugas Atasan akan mengira tugasnya hilang. */
            'di_luar_jendela' => $this->tugasDiLuarJendela($saya, $cycleId),
        ];
    }

    /**
     | Tugas yang ada tetapi siklusnya tidak sedang membuka pengisian.
     |
     | Dikelompokkan per status siklus karena sebabnya berbeda dan tindakannya pun berbeda:
     | `LOCKED` berarti "tunggu dibuka", `CLOSED` berarti "sudah lewat, tidak ada lagi yang
     | bisa dikerjakan". Menggabungkannya menjadi satu angka membuat keduanya tak terbedakan.
     |
     | @return array{total:int, per_status:array<string, int>}
     */
    private function tugasDiLuarJendela(object $saya, ?int $cycleId): array
    {
        $q = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('a.Kode_Perusahaan_Penilai', $saya->Kode_Perusahaan)
            ->where('a.Kode_Karyawan_Penilai', $saya->Kode_Karyawan)
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')
            ->whereNotIn('k.Status_Cycle', self::STATUS_SIKLUS_DAPAT_DIISI);

        if ($cycleId !== null) {
            $q->where('p.PA_Cycle_Id', $cycleId);
        }

        $per = $q->groupBy('k.Status_Cycle')
            ->selectRaw('k.Status_Cycle as status, COUNT(*) as jumlah')
            ->pluck('jumlah', 'status')
            ->mapWithKeys(fn ($n, $k) => [(string) $k => (int) $n])
            ->all();

        return ['total' => array_sum($per), 'per_status' => $per];
    }

    /**
     * Progres penilaian saya, DIKELOMPOKKAN per kategori penilai.
     *
     * KENAPA DI SERVER, BUKAN DI LAYAR
     *
     * `daftarAssignment()` berpaginasi 25 baris. Mengelompokkan yang sudah termuat berarti
     * mengelompokkan sepotong lalu melabelinya sebagai keseluruhan — kelompok "BAWAHAN 3 tugas"
     * yang sebenarnya 40, tanpa satu pun tanda bahwa angkanya sepotong.
     *
     * PROGRESNYA BINER PER PENUGASAN, DAN ITU BUKAN PILIHAN MALAS
     *
     * Formulirnya diisi di HC Learn, bukan di HCIS. `BERJALAN` hanya berarti "form sudah
     * dibuka"; `SELESAI` datang lewat callback. HCIS TIDAK punya cara tahu sudah berapa butir
     * yang dijawab, jadi memberi kredit separuh untuk BERJALAN berarti menampilkan angka yang
     * dikarang sendiri. BERJALAN dilaporkan sebagai cacah tersendiri, bukan potongan bar.
     *
     * TOTALNYA BUKAN RATA-RATA PERSENTASE KATEGORI
     *
     * Keduanya sama-sama mencapai 100% hanya bila semua kategori 100%. Bedanya di tengah:
     * 1 dari 1 selesai di ATASAN ditambah 0 dari 40 di BAWAHAN akan terbaca 50% kalau
     * dirata-rata, padahal yang selesai 1 dari 41.
     *
     * KATEGORI TANPA TUGAS TIDAK MUNCUL
     *
     * Bukan ditampilkan 0%. STAFF hanya dinilai SELF dan ATASAN; menampilkan PEER dan BAWAHAN
     * sebagai 0% memberi kelompok yang tidak bisa ia kerjakan, dan membuat 100% tak tercapai.
     *
     * Penugasan DIBATALKAN dan tidak aktif tidak ikut dihitung — itu sudah ditegakkan
     * `kueriPenugasan()`, dan penyebut yang memuatnya membuat 100% mustahil selamanya.
     *
     * @return array<string, mixed>
     */
    public function progresPerKategori(int $userId, ?int $cycleId = null): array
    {
        $saya = $this->karyawan($userId);

        /* Barisnya ditarik, lalu diringkas di PHP. Alasannya bukan kemalasan: aturan
           keterlambatan punya penyesuaian akhir-hari yang halus (`Due_At` bertipe DATE, jadi
           tenggat berarti SAMPAI AKHIR hari itu). Menuliskannya ulang sebagai SQL berarti dua
           salinan satu aturan, dan yang satu akan bergeser tanpa yang lain.

           Jumlahnya aman: ini penugasan SATU orang sebagai penilai — puluhan, bukan ribuan.
           `ringkasan()` sudah memakai pola yang sama. */
        $baris = $this->kueriPenugasan($saya, $cycleId)
            ->get(['a.Status_Penugasan', 'a.Due_At', 'c.Kode_Kategori', 'c.Nama_Kategori']);

        $urutan = $this->urutanKategori();

        $kelompok = [];

        foreach ($baris as $b) {
            $kode = (string) $b->Kode_Kategori;

            if (! isset($kelompok[$kode])) {
                $kelompok[$kode] = [
                    'kode_kategori' => $kode,
                    'nama_kategori' => self::namaKategoriPenilai($kode, $b->Nama_Kategori),
                    'total' => 0,
                    'selesai' => 0,
                    'terkirim' => 0,
                    'berjalan' => 0,
                    'belum_mulai' => 0,
                    'terlambat' => 0,
                    'deadline_dekat' => 0,
                ];
            }

            $kelompok[$kode]['total']++;

            /* Sama dengan `ringkasan()`: 'selesai' = sudah diisi (`SUDAH_DIKERJAKAN`),
               pengirimannya dihitung terpisah. */
            if (in_array($b->Status_Penugasan, PaStatusPenugasan::SUDAH_DIKERJAKAN, true)) {
                $kelompok[$kode]['selesai']++;
            } elseif ($b->Status_Penugasan === PaStatusPenugasan::BERJALAN) {
                $kelompok[$kode]['berjalan']++;
            } elseif ($b->Status_Penugasan === PaStatusPenugasan::BELUM_MULAI) {
                $kelompok[$kode]['belum_mulai']++;
            }

            if ($b->Status_Penugasan === PaStatusPenugasan::SELESAI) {
                $kelompok[$kode]['terkirim']++;
            }

            $k = $this->keterlambatan($b);

            if ($k['terlambat']) {
                $kelompok[$kode]['terlambat']++;
            } elseif ($k['sisa_hari'] !== null && $k['sisa_hari'] <= self::AMBANG_DEKAT_HARI) {
                $kelompok[$kode]['deadline_dekat']++;
            }
        }

        $kelompok = array_map(function (array $g) {
            /* Persentase dihitung di sini, bukan di layar — sama seperti `ringkasan()`. Layar
               yang membulatkan sendiri menghasilkan dua angka berbeda untuk satu gagasan. */
            $g['persen'] = $g['total'] > 0 ? (int) round($g['selesai'] / $g['total'] * 100) : 0;
            $g['tuntas'] = $g['total'] > 0 && $g['selesai'] === $g['total'];

            return $g;
        }, $kelompok);

        /* SELF paling depan: ia yang paling sering terlewat, dan ia satu-satunya yang tidak
           bisa didelegasikan. Sisanya mengikuti urutan tampil kebijakan. */
        uasort($kelompok, function (array $a, array $b) use ($urutan) {
            if ($a['kode_kategori'] === 'SELF') {
                return -1;
            }

            if ($b['kode_kategori'] === 'SELF') {
                return 1;
            }

            $ua = $urutan[$a['kode_kategori']] ?? PHP_INT_MAX;
            $ub = $urutan[$b['kode_kategori']] ?? PHP_INT_MAX;

            return $ua <=> $ub ?: strcmp($a['kode_kategori'], $b['kode_kategori']);
        });

        $total = $baris->count();
        $selesai = array_sum(array_column($kelompok, 'selesai'));

        return [
            'kategori' => array_values($kelompok),
            'total' => $total,
            'selesai' => $selesai,
            'berjalan' => array_sum(array_column($kelompok, 'berjalan')),
            'belum_mulai' => array_sum(array_column($kelompok, 'belum_mulai')),
            'terlambat' => array_sum(array_column($kelompok, 'terlambat')),
            'deadline_dekat' => array_sum(array_column($kelompok, 'deadline_dekat')),

            /* Dari TOTAL, bukan rata-rata persentase kategori. Lihat catatan metode. */
            'persen' => $total > 0 ? (int) round($selesai / $total * 100) : 0,
            'tuntas' => $total > 0 && $selesai === $total,
            'ambang_dekat_hari' => self::AMBANG_DEKAT_HARI,
        ];
    }

    /**
     * Urutan tampil kategori penilai.
     *
     * Diambil dari data, BUKAN dikeraskan di kode: kalau admin menata ulang urutannya,
     * layar penilai harus ikut, bukan menampilkan urutan lain yang kebetulan ditulis
     * programmer.
     *
     * SUMBER UTAMANYA `N_PA_Rater_Category.Urutan_Pengisian` (DDL-2026-119), yang juga
     * menata langkah wizard. Panel progres dan wizard wajib menampilkan urutan yang sama;
     * dua sumber urutan pada satu halaman terbaca sebagai dua aturan.
     *
     * Kategori yang belum diberi urutan di master jatuh ke belakang, dan di antara mereka
     * urutan lama dipertahankan: MIN `Urutan_Tampil` kebijakan penilai lintas level, karena
     * satu penilai bekerja atas orang-orang dari beberapa level sekaligus.
     *
     * @return array<string, int>
     */
    private function urutanKategori(): array
    {
        $master = DB::table('N_PA_Rater_Category')
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('Urutan_Pengisian')
            ->pluck('Urutan_Pengisian', 'Kode_Kategori')
            ->map(fn ($u) => (int) $u)
            ->all();

        $cadangan = DB::table('N_PA_Rater_Policy_Detail as d')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->where('d.Flag_Aktif', 'Y')
            ->groupBy('c.Kode_Kategori')
            ->get(['c.Kode_Kategori as kode', DB::raw('MIN(d.Urutan_Tampil) as urutan')])
            ->mapWithKeys(fn ($r) => [$r->kode => (int) $r->urutan])
            ->all();

        $dasar = $master === [] ? 0 : max($master);
        $hasil = $master;

        foreach ($cadangan as $kode => $urutan) {
            $hasil[$kode] ??= $dasar + 1000 + $urutan;
        }

        return $hasil;
    }

    /**
     * Satu halaman penugasan tempat orang ini menjadi penilai.
     *
     * @param  array{status?:string|null, q?:string|null, cycle_id?:int|null,
     *               halaman?:int, per_halaman?:int}  $saringan
     * @return array<string, mixed>
     */
    public function daftarAssignment(int $userId, array $saringan = []): array
    {
        $saya = $this->karyawan($userId);

        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $diminta = (int) ($saringan['per_halaman'] ?? 25);
        $perHalaman = max(1, min($diminta, self::MAKS_PER_HALAMAN));

        $dasar = function () use ($saya, $saringan) {
            $q = $this->kueriPenugasan($saya, $saringan['cycle_id'] ?? null);

            /* Saringan kategori. Ada supaya tombol "Lihat 12 tugas Atasan" pada panel progres
               benar-benar menyaring — tombol yang mengaku menyaring lalu menampilkan semuanya
               lebih buruk daripada tidak ada tombol. */
            if (! empty($saringan['kategori'])) {
                $q->where('c.Kode_Kategori', $saringan['kategori']);
            }

            if (! empty($saringan['status'])) {
                $q->where('a.Status_Penugasan', $saringan['status']);
            }

            $cari = trim((string) ($saringan['q'] ?? ''));

            if ($cari !== '') {
                $q->where('p.Nama_Karyawan_Saat_Cycle', 'LIKE', '%'.$cari.'%');
            }

            return $q;
        };

        $total = (int) $dasar()->count();

        /* Simulator pengisian lokal hanya hidup di luar production. Ditanyakan SEKALI
           untuk seluruh halaman, bukan per baris: jawabannya sama untuk semuanya. */
        $simulasiLokal = ! app()->isProduction();

        $baris = $dasar()
            // Tenggat terdekat lebih dulu; yang tanpa tenggat jatuh ke belakang. Kunci
            // utama terakhir supaya baris tidak berpindah halaman antar permintaan.
            ->orderByRaw('CASE WHEN a.Due_At IS NULL THEN 1 ELSE 0 END')
            ->orderBy('a.Due_At')
            ->orderBy('a.Id_PA_Assignment')
            ->offset(($halaman - 1) * $perHalaman)
            ->limit($perHalaman)
            ->get(self::kolomTugas())
            ->map(fn ($b) => $this->barisTugas($b, $saya, $simulasiLokal))
            ->values()
            ->all();

        return [
            'assignment' => $baris,
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => $total,
            'total_halaman' => (int) ceil($total / $perHalaman),
            'dipangkas' => $diminta > self::MAKS_PER_HALAMAN,
        ];
    }

    /**
     * Satu baris tugas siap tampil: keterlambatan, alamat lembar, kode akses, dan penanda
     * penilaian diri. Dipakai daftar tugas lama dan wizard, supaya aturan "tautan lembar
     * datang dari HC Learn, simulator lokal hanya cadangan di luar production" tidak pernah
     * punya dua salinan.
     *
     * @return array<string, mixed>
     */
    public function barisTugas(object $b, object $saya, bool $simulasiLokal): array
    {
        $k = $this->keterlambatan($b);

        // Kolom bantu kueri; yang dikirim adalah `kiriman_tertahan` yang sudah ditafsirkan.
        $tertahan = ($b->Flag_Kiriman_Tertahan ?? 'T') === 'Y';
        unset($b->Flag_Kiriman_Tertahan);

        return (array) $b + [
            'terlambat' => $k['terlambat'],
            'hari_terlambat' => $k['hari_terlambat'],
            'sisa_hari' => $k['sisa_hari'],

            /*
             | ALAMAT LEMBAR PENILAIANNYA — null bila memang tidak ada.
             |
             | Layar memakai field ini untuk memutuskan apakah tombol "Mulai Menilai"
             | menyala. Sebelumnya field ini TIDAK PERNAH dikirim sama sekali padahal
             | dibaca layar: tombolnya menyala karena tokennya ada, ditekan, lalu tidak
             | terjadi apa-apa. Token dan tujuan bukan hal yang sama.
             |
             | DATANG DARI HC LEARN, tidak lagi ditebak. Sejak kontrak penjadwalan
             | mereka ada (10 September 2026), `Link_Ujian` dipulangkan bersama
             | short_token dan OTP-nya — menyusunnya sendiri di sini akan mengirim
             | penilai ke halaman yang bukan miliknya.
             |
             | Jalur simulator lokal DIPERTAHANKAN sebagai cadangan untuk token
             | SIM- lama yang belum tergantikan, dan hanya di luar production.
             */
            'url_ujian' => $b->Link_Ujian
                ?? ($simulasiLokal
                    && $b->Id_Ujian_Token !== null
                    && str_starts_with((string) $b->Id_Ujian_Token, PaSimulasiPengisianService::AWALAN_SIMULASI)
                        /* JALUR RELATIF, bukan `url()`. Pembantu itu menyusun alamat
                           dari `APP_URL`, dan di lingkungan ini nilainya menunjuk host
                           lain daripada yang sedang dibuka — penilai akan dilempar ke
                           domain yang bukan tempat ia login, lalu mendarat di halaman
                           masuk. */
                        ? '/performance-appraisal/nilai/'.$b->Id_Ujian_Token
                        : null),

            /* Kode yang diketik penilai. Dikirim apa adanya supaya layar bisa
               menampilkannya untuk dibacakan lewat telepon ketika tautannya tidak
               sampai — keadaan yang paling sering terjadi. */
            'short_token' => $b->Short_Token,
            'akses_otp' => $b->Akses_Otp,

            /* Pintu yang masih ANTRE bukan pintu yang gagal: HC Learn sudah menerima
               kirimannya dan sedang menggarap antreannya. Layar wajib bisa
               membedakan keduanya, kalau tidak admin akan menjadwalkan ulang
               pekerjaan yang sedang berjalan. */
            'status_pintu' => $b->Status_Integrasi,
            // Penilaian diri ditandai, bukan disembunyikan: layar menampilkannya
            // sebagai "Diri Sendiri", dan tanpa penanda ini ia terbaca seperti
            // orang lain yang kebetulan senama.
            'diri_sendiri' => $b->Kode_Karyawan === $saya->Kode_Karyawan,
            'kiriman_tertahan' => $tertahan,
        ];
    }

    /**
     * Hasil penilaian MILIK orang ini — hanya yang sudah terbit.
     *
     * Tidak memuat nilai per kategori penilai. Lihat catatan kelas.
     *
     * @return array<string, mixed>
     */
    public function hasilSaya(int $userId, ?int $cycleId = null): array
    {
        $saya = $this->karyawan($userId);

        $q = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('p.Kode_Perusahaan', $saya->Kode_Perusahaan)
            ->where('p.Kode_Karyawan', $saya->Kode_Karyawan)
            ->where('r.Flag_Aktif', 'Y')
            // HANYA yang terbit. Lihat catatan kelas.
            ->where('r.Status_Hasil', PaResultPublishService::STATUS_TERBIT);

        if ($cycleId !== null) {
            $q->where('p.PA_Cycle_Id', $cycleId);
        }

        // Terbaru = periode dinilai yang berakhir paling akhir; sama dengan urutan riwayat.
        $hasil = $q->orderByRaw(self::URUTAN_PERIODE.' DESC')
            ->orderByDesc('r.Published_At')
            ->orderByDesc('k.Id_PA_Cycle')
            ->first([
                'r.Id_PA_Result', 'p.Id_PA_Participant',
                'p.Nama_Karyawan_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
                'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Divisi_Saat_Cycle',
                'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun',
                'r.Nilai_KPI', 'r.Nilai_Kompetensi', 'r.Final_Score',
                'r.Kode_Grade_Final', 'r.Published_At',
                // Bobot yang SUNGGUH dipakai menghitung angka ini, bukan anggapan layar.
                'r.Bobot_KPI_Terpakai', 'r.Bobot_Kompetensi_Terpakai',
                /* Penandanya saja, BUKAN alasannya. Tanpa ini layar peserta menampilkan
                   nilai akhir 81,50 bersanding dengan grade "A" — sementara pita A mulai
                   dari 90 — dan tidak punya satu pun bahan untuk menjelaskan kenapa.
                   Angka yang bertentangan dengan gradenya sendiri, tanpa keterangan,
                   terbaca sebagai sistem yang keliru menghitung.

                   Alasan kalibrasinya sengaja TIDAK ikut: ia ditulis untuk audit dan bisa
                   memuat pertimbangan yang bukan urusan orang yang dinilai. */
                'r.Flag_Dikalibrasi',
            ]);

        if ($hasil === null) {
            return [
                'tersedia' => false,
                // Dibedakan dari galat: belum terbit adalah keadaan yang sah dan sering,
                // dan layar harus mengatakannya begitu — bukan menampilkan kegagalan.
                'pesan' => 'Hasil penilaian Anda belum diterbitkan.',
                'hasil' => null,
                /* Kuncinya tetap ada meski kosong. Dua cabang yang berbeda SUSUNAN
                   kuncinya memaksa layar memeriksa keberadaan field sebelum membacanya,
                   dan pemeriksaan itu pasti terlupa di satu tempat. */
                'kompetensi' => [],
                'catatan' => [],
            ];
        }

        $resultId = (int) $hasil->Id_PA_Result;

        return [
            'tersedia' => true,
            'pesan' => null,
            'hasil' => (array) $hasil + [
                'dikalibrasi' => $hasil->Flag_Dikalibrasi === 'Y',
                'Nama_Grade_Final' => $this->namaGrade((int) $hasil->Id_PA_Participant, $hasil->Kode_Grade_Final),
            ],
            /* Kuadran HAV (9-box) TIDAK dikirim ke karyawan sejak 6 Oktober 2026: penempatan
               talenta adalah bahan pembahasan HC dan leader, bukan bagian hasil yang dibaca
               orangnya. Jalur admin dan leader tetap membawanya. */
            // Hanya baris AGREGAT lintas kategori. Rincian per kategori penilai sengaja
            // tidak disertakan — itu jalur admin.
            'kompetensi' => PaKompetensiSiklus::gabung(DB::table('N_PA_Result_Competency as rk')
                ->join('N_PA_Result as hr', 'hr.Id_PA_Result', '=', 'rk.PA_Result_Id')
                ->join('N_PA_Participant as hp', 'hp.Id_PA_Participant', '=', 'hr.PA_Participant_Id')
                ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'rk.PA_Competency_Id'), 'c', 'hp.PA_Cycle_Id')
                ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
                ->where('rk.PA_Result_Id', $resultId)
                ->whereNull('rk.PA_Rater_Category_Id')
                ->where('rk.Flag_Aktif', 'Y')
                ->orderBy('t.Urutan_Tampil')
                ->orderBy('c.Urutan_Tampil')
                /* `Flag_Radar` dikirim, TIDAK disaring di sini. Baris ini dipakai dua kali:
                   sebagai sumbu radar dan sebagai rincian per kompetensi. Menyaringnya di
                   kueri akan melenyapkan kompetensi non-radar dari rincian juga — padahal
                   ia tetap dinilai dan nilainya tetap harus terbaca. Layar yang memilih. */
                ->get(['c.Kode_Competency', 'c.Nama_Competency', 'c.Deskripsi', 'c.Expected_Behaviour', 't.Kode_Type', 't.Nama_Type',
                    'rk.Nilai', PaKompetensiSiklus::kolom('Nilai_Ambang', 'c'), PaKompetensiSiklus::kolom('Flag_Radar', 'c')])
                // Expected Behaviour sesuai tingkat yang dituntut untuk level orang ini (DDL-2026-175).
                ->pipe(fn ($rows) => app(PaKompetensiMatriksService::class)->terapkanPerilakuPerKode(
                    $rows, ...app(PaKompetensiMatriksService::class)->konteksHasil($resultId), kunciKode: 'Kode_Competency', kunciPerilaku: 'Expected_Behaviour')),
            'catatan' => $this->kesimpulanKualitatif($resultId),
        ];
    }

    /**
     * Riwayat penilaian lintas siklus — tren empat periode pada mockup.
     *
     * Batasnya sama dengan `hasilSaya()`: hanya yang TERBIT. Riwayat yang memuat hasil
     * belum terbit akan membocorkan lewat pintu belakang persis yang dijaga di pintu
     * depan — dan justru pada layar yang paling jarang diperiksa orang.
     *
     * @return array<string, mixed>
     */
    public function riwayatSaya(int $userId): array
    {
        $saya = $this->karyawan($userId);

        $baris = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('p.Kode_Perusahaan', $saya->Kode_Perusahaan)
            ->where('p.Kode_Karyawan', $saya->Kode_Karyawan)
            ->where('r.Flag_Aktif', 'Y')
            ->where('r.Status_Hasil', PaResultPublishService::STATUS_TERBIT)
            // Terlama lebih dulu: tren dibaca kiri ke kanan, dan membalikkannya di layar
            // adalah satu langkah yang mudah terlupa di salah satu dari dua layar.
            ->orderByRaw(self::URUTAN_PERIODE)
            ->orderBy('r.Published_At')
            ->orderBy('k.Id_PA_Cycle')
            ->get([
                'k.Id_PA_Cycle', 'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun',
                'p.Id_PA_Participant', 'p.Nama_Jabatan_Saat_Cycle', 'p.Nama_Level_Saat_Cycle',
                'r.Nilai_KPI', 'r.Nilai_Kompetensi', 'r.Final_Score',
                'r.Kode_Grade_Final', 'r.Published_At',
                'r.Bobot_KPI_Terpakai', 'r.Bobot_Kompetensi_Terpakai',
            ])
            // Satu kueri per periode; seseorang hanya punya segelintir siklus terbit.
            ->map(fn ($b) => array_diff_key((array) $b, ['Id_PA_Participant' => true]) + [
                'Nama_Grade_Final' => $this->namaGrade((int) $b->Id_PA_Participant, $b->Kode_Grade_Final),
            ])
            ->values()
            ->all();

        /* Kolom decimal SQL Server tiba sebagai STRING lewat driver ini ("82.50", bukan
           82.5). PHP 8 membandingkan string numerik secara NUMERIK, jadi max() di bawah
           tetap memilih yang benar - masalahnya bukan urutan melainkan TIPE: max()
           mengembalikan string, sedangkan rata_rata lahir sebagai float, sehingga dua
           field sejenis terkirim dengan tipe berbeda. Yang menerimanya adalah JavaScript,
           dan di sana "9.00" > "82.50" bernilai true. Dicetak ke float sekali di sini,
           supaya perbandingan itu tidak pernah sampai ke layar. */
        $skor = array_map(
            fn ($n) => (float) $n,
            array_values(array_filter(
                array_column($baris, 'Final_Score'),
                fn ($n) => $n !== null,
            )),
        );

        return [
            'periode' => $baris,
            'jumlah_periode' => count($baris),
            'rata_rata' => $skor === [] ? null : round(array_sum($skor) / count($skor), 2),
            'terbaik' => $skor === [] ? null : round(max($skor), 2),
            /* Selisih terhadap periode SEBELUMNYA, bukan terhadap rata-rata. "Naik 3,2
               dari periode lalu" adalah yang ditanyakan orang; jaraknya dari rata-rata
               tidak menjawab apa pun. Butuh dua periode, dan satu periode bukan kesalahan
               melainkan orang baru. */
            'perubahan_terakhir' => count($skor) >= 2
                ? round($skor[count($skor) - 1] - $skor[count($skor) - 2], 2)
                : null,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Kesimpulan kualitatif resmi, satu baris per pertanyaan kualitatif yang ADA di hasil ini.
     *
     * HANYA KESIMPULAN (`PA_Assignment_Id` NULL). Observasi mentah tiap penilai dulu ikut
     * terkirim bersama penanda `dari_penilai` dan disembunyikan layar — tetapi isinya tetap
     * sampai ke peramban orang yang dinilai dan terbaca lewat alat pengembang. Tanpa nama pun,
     * kalimat seorang rekan sering cukup untuk menebak penulisnya. Yang tidak boleh terbaca
     * tidak dikirim.
     *
     * Pertanyaan yang hanya punya observasi penilai tetap muncul dengan `Isi` NULL: layar perlu
     * tahu pertanyaan itu ada di siklus ini supaya bisa berkata "belum ada kesimpulan",
     * tanpa menampilkan kartu kosong untuk pertanyaan yang memang tidak pernah ditanyakan.
     *
     * @return list<array{Kode_Type:string, Nama_Type:string, Isi:?string, dari_penilai:false}>
     */
    public function kesimpulanKualitatif(int $resultId): array
    {
        $baris = DB::table('N_PA_Result_Note as n')
            ->join('N_PA_Competency as t', 't.Id_PA_Competency', '=', 'n.PA_Competency_Id')
            ->where('n.PA_Result_Id', $resultId)
            ->where('n.Flag_Aktif', 'Y')
            ->orderBy('t.Urutan_Tampil')
            ->orderBy('n.Urutan_Tampil')
            ->get([
                'n.PA_Competency_Id', 't.Kode_Competency', 't.Nama_Competency',
                // Isi hanya DIAMBIL bila kesimpulan; observasi penilai tidak pernah dibaca ke PHP.
                DB::raw('CASE WHEN n.PA_Assignment_Id IS NULL THEN n.Isi END AS Isi'),
            ]);

        $hasil = [];
        $bagian = [];

        foreach ($baris as $r) {
            $id = (int) $r->PA_Competency_Id;
            $hasil[$id] ??= [
                'Kode_Type' => (string) $r->Kode_Competency,
                'Nama_Type' => (string) $r->Nama_Competency,
                'Isi' => null,
                'dari_penilai' => false,
            ];

            /* Admin boleh menulis beberapa butir kesimpulan per pertanyaan; SEMUANYA dibawa.
               Butir teks polos dibungkus paragraf supaya tetap terpisah setelah digabung. */
            $isi = trim((string) $r->Isi);

            if ($isi !== '') {
                $bagian[$id][] = str_starts_with($isi, '<') ? $isi : '<p>'.e($isi).'</p>';
            }
        }

        foreach ($bagian as $id => $daftar) {
            $hasil[$id]['Isi'] = implode('', $daftar);
        }

        return array_values($hasil);
    }

    /**
     * Nama grade TERBIT seseorang, dari pita yang berlaku di siklusnya — bukan master hidup
     * dan bukan teks karangan layar.
     *
     * Siklus KURVA: pita kurva BEKU grup peserta (`N_PA_Cycle_Curve_Band`), tempat grade itu
     * lahir. Siklus PITA: salinan pita siklus (`N_PA_Cycle_Score_Range_Snapshot`). Kodenya
     * selalu `Kode_Grade_Final` — yang diterbitkan, termasuk hasil kalibrasi. Kode yang tidak
     * ditemukan di kedua tempat mengembalikan null; layar lalu menampilkan kodenya saja.
     */
    public function namaGrade(int $participantId, ?string $kode): ?string
    {
        if ($kode === null || $kode === '') {
            return null;
        }

        $kurva = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle_Curve_Group_Level as gl', function ($j) {
                $j->on('gl.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')
                    ->on('gl.ID_Level', '=', 'p.ID_Level_Saat_Cycle')
                    ->where('gl.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Cycle_Curve as k', function ($j) {
                $j->on('k.PA_Cycle_Curve_Group_Id', '=', 'gl.PA_Cycle_Curve_Group_Id')
                    ->where('k.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Cycle_Curve_Band as b', 'b.PA_Cycle_Curve_Id', '=', 'k.Id_PA_Cycle_Curve')
            ->where('p.Id_PA_Participant', $participantId)
            ->where('b.Kode_Range', $kode)
            ->value('b.Nama_Range');

        if ($kurva !== null) {
            return (string) $kurva;
        }

        $pita = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle_Score_Range_Snapshot as s', 's.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')
            ->where('p.Id_PA_Participant', $participantId)
            ->where('s.Score_Type', 'FINAL')
            ->where('s.Score_Usage', 'GRADE')
            ->where('s.Flag_Aktif', 'Y')
            ->where('s.Kode_Range', $kode)
            ->value('s.Nama_Range');

        return $pita === null ? null : (string) $pita;
    }

    /**
     * Penugasan tempat orang ini menjadi PENILAI.
     *
     * Disaring `Kode_Perusahaan` DAN `Kode_Karyawan` sekaligus, karena kunci karyawan di
     * HRIS memang komposit — menyaring kodenya saja akan mencampur orang bernomor sama
     * dari perusahaan berbeda.
     *
     * Publik karena PaWizardPenilaianService memakai lingkup yang SAMA; lingkup "tugas saya"
     * yang ditulis dua kali akan berselisih, dan yang berselisih menentukan apa yang dilihat
     * penilai tentang pekerjaannya sendiri.
     */
    public function kueriPenugasan(object $saya, ?int $cycleId)
    {
        $q = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->leftJoin('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')
                    ->where('i.Flag_Aktif', 'Y');
            })
            ->where('a.Kode_Perusahaan_Penilai', $saya->Kode_Perusahaan)
            ->where('a.Kode_Karyawan_Penilai', $saya->Kode_Karyawan)
            ->where('a.Flag_Aktif', 'Y')
            // Penugasan yang dibatalkan bukan pekerjaan siapa pun lagi.
            ->where('a.Status_Penugasan', '<>', 'DIBATALKAN')

            /* Dan siklus yang jendelanya tidak terbuka juga bukan. Ditegakkan DI SINI karena
               ketiga jalur — ringkasan, progres per kategori, dan daftar tugas — melewati
               method ini; menaruhnya di masing-masing berarti tiga salinan yang cepat atau
               lambat berselisih, dan yang berselisih di sini menentukan angka yang dilihat
               958 orang tentang pekerjaannya sendiri. */
            ->whereIn('k.Status_Cycle', self::STATUS_SIKLUS_DAPAT_DIISI);

        if ($cycleId !== null) {
            $q->where('p.PA_Cycle_Id', $cycleId);
        }

        return $q;
    }

    /**
     * @return array{terlambat:bool, hari_terlambat:int|null, sisa_hari:int|null}
     */
    private function keterlambatan(object $penugasan): array
    {
        $kosong = ['terlambat' => false, 'hari_terlambat' => null, 'sisa_hari' => null];

        /* TERKUMPUL sejajar dengan SELESAI di sini. Yang diukur keterlambatan MENGISI, dan
           lembar terkumpul sudah terisi penuh - yang belum hanya pengirimannya. Menandainya
           terlambat berarti melekatkan lencana merah pada orang yang justru sudah
           menyelesaikan bagiannya, dan lencana yang salah alamat membuat seluruh lencana
           berhenti dipercaya. */
        if ($penugasan->Due_At === null
            || in_array($penugasan->Status_Penugasan, ['SELESAI', 'TERKUMPUL', 'DIBATALKAN'], true)) {
            return $kosong;
        }

        /* `Due_At` bertipe DATE, bukan datetime. strtotime('2026-09-06') jatuh di TENGAH
           MALAM, sehingga tanpa penyesuaian ini orang yang tenggatnya hari ini sudah
           ditandai terlambat sejak pukul 00:00 - pada hari yang justru masih miliknya,
           dengan lencana merah bertuliskan "Terlambat 0 hari". Tenggat tanggal berarti
           SAMPAI AKHIR hari itu. */
        $mentah = (string) $penugasan->Due_At;
        $tenggat = strtotime(strlen($mentah) <= 10 ? $mentah.' 23:59:59' : $mentah);

        if ($tenggat === false) {
            return $kosong;
        }

        $selisih = $tenggat - time();

        return $selisih < 0
            ? ['terlambat' => true, 'hari_terlambat' => $this->hariKalender($mentah), 'sisa_hari' => null]
            : ['terlambat' => false, 'hari_terlambat' => null, 'sisa_hari' => (int) floor($selisih / 86400)];
    }

    /**
     * Selisih HARI KALENDER antara tenggat dan hari ini.
     *
     * Bukan selisih jam dibagi 24. Tenggat kemarin sore yang lewat sembilan belas jam
     * menghasilkan nol kalau dibagi 24 - dan lencana merah bertuliskan "Terlambat 0 hari"
     * adalah cara cepat membuat orang berhenti mempercayai layarnya. Manusia menghitung
     * keterlambatan dalam hari kalender: tenggat kemarin berarti terlambat satu hari.
     */
    private function hariKalender(string $tanggalTenggat): int
    {
        $tenggat = new \DateTimeImmutable(substr($tanggalTenggat, 0, 10));
        $hariIni = new \DateTimeImmutable(date('Y-m-d'));

        return max(0, (int) $tenggat->diff($hariIni)->days);
    }

    /**
     * Karyawan milik user yang sedang login.
     *
     * Inilah satu-satunya sumber lingkup di berkas ini. Tidak ada method yang menerima
     * identitas peserta dari pemanggil.
     */
    /* ------------------------------------------------------------------ *
     * PENGIRIMAN SATU PINTU                                               *
     * ------------------------------------------------------------------ */

    /**
     * Kemajuan pengumpulan saya, per siklus.
     *
     * Diteruskan ke `PaRaterSubmitService` alih-alih dihitung ulang di sini. Aturan "kapan
     * boleh kirim" harus punya satu penulis: layar yang menghidupkan tombolnya dan server
     * yang menerima tekanannya wajib menjawab pertanyaan yang sama dengan cara yang sama,
     * kalau tidak, tombol yang hidup akan bertemu penolakan yang tidak bisa dijelaskan
     * kepada orang yang menekannya.
     *
     * @return list<array<string, mixed>>
     */
    public function kemajuanPengiriman(int $userId, ?int $cycleId = null): array
    {
        $saya = $this->karyawan($userId);

        return app(PaRaterSubmitService::class)->kemajuan(
            (string) $saya->Kode_Perusahaan,
            (string) $saya->Kode_Karyawan,
            $cycleId,
        );
    }

    /**
     * Saya mengirim SELURUH penilaian saya di satu siklus.
     *
     * @return array{cycle_id:int, dilepas:int, peserta_diantrekan:int}
     */
    public function kirimSemua(int $userId, int $cycleId): array
    {
        $saya = $this->karyawan($userId);

        return app(PaRaterSubmitService::class)->kirimSemua(
            (string) $saya->Kode_Perusahaan,
            (string) $saya->Kode_Karyawan,
            $cycleId,
            $userId,
        );
    }

    /**
     * Karyawan aktif milik user yang login — satu-satunya sumber lingkup "saya".
     *
     * Publik karena PaWizardPenilaianService dan PaWizardPenilaianController memakainya;
     * menurunkan identitas dari user yang login di dua tempat berarti dua aturan tentang
     * siapa "saya".
     */
    public function karyawan(int $userId): object
    {
        $k = DB::table('Karyawan')
            ->where('UserID_Web', $userId)
            ->where('Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama']);

        if ($k === null) {
            throw new RuntimeException(
                'Akun Anda belum tersambung ke data karyawan yang aktif, sehingga penilaian '
                .'Anda tidak bisa ditemukan. Hubungi HC untuk menyambungkannya.'
            );
        }

        return $k;
    }
}
