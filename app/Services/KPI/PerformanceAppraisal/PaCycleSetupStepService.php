<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penyiapan siklus sebagai alur berlangkah, bukan satu tombol.
 *
 * MASALAH YANG DISELESAIKAN
 *
 * "Siapkan" dahulu satu tombol yang langsung membentuk ratusan baris peserta memakai
 * setelan yang tinggal di halaman MASTER GLOBAL — sama untuk semua siklus. Admin tidak
 * pernah bisa menjawab "apa setelan siklus A, dan apa setelan siklus B", karena tidak ada
 * yang menyimpan jawabannya per siklus.
 *
 * Layanan ini memecahnya menjadi empat pekerjaan yang masing-masing MILIK SIKLUS, bisa
 * disimpan setengah jalan, dan punya urutan yang terbaca.
 *
 * POSISI DISIMPAN, BUKAN DISIMPULKAN
 *
 * `langkahAktif()` membaca `N_PA_Cycle.Langkah_Setup_Aktif` — fakta yang ditulis ketika
 * admin berpindah — dan hanya jatuh ke langkah pertama yang belum selesai bila kolomnya
 * masih kosong. Menyimpulkan posisi dari kelengkapan isi terdengar lebih pintar dan selalu
 * berakhir sama: admin yang menyimpan langkah 3 dilempar kembali ke langkah 1 karena
 * langkah 1 kebetulan masih kosong dan sengaja dilewati.
 *
 * URUTAN LANGKAH BUKAN SEKADAR TAMPILAN
 *
 * Peserta ditaruh TERAKHIR karena penyiapan peserta membaca ketiga setelan sebelumnya.
 * Menjalankannya lebih dulu menghasilkan peserta yang lahir dari bobot dan aturan yang
 * kemudian diubah — dan tidak ada yang memberi tahu bahwa barisnya sudah basi.
 */
class PaCycleSetupStepService
{
    /**
     * Langkah penyiapan, berurut.
     *
     * `gerbang` menyebut operasi `PaCycleGate` yang menjaga langkahnya. Dipakai untuk
     * memberi tahu layar mana yang masih boleh disunting pada status siklus sekarang,
     * SEBELUM admin membukanya — penolakan yang baru muncul sesudah tombol simpan ditekan
     * adalah cara paling melelahkan untuk menyampaikan aturan.
     */
    private const LANGKAH = [
        [
            'kode' => 'BOBOT_PILAR',
            'konfigurasi' => true,
            'nama' => 'Bobot Penilaian',
            'ringkas' => 'Pembagian KPI lawan Kompetensi, per level jabatan.',
            'ikon' => 'sliders2',
            'gerbang' => PaCycleGate::SUNTING_BOBOT_PILAR,
        ],
        [
            'kode' => 'PAKET_SOAL',
            'konfigurasi' => true,
            'nama' => 'Pengaturan KPI',
            'ringkas' => 'Paket soal yang dipakai siklus ini.',
            'ikon' => 'ui-checks',
            'gerbang' => PaCycleGate::PILIH_PAKET_SOAL,
        ],
        [
            'kode' => 'ATURAN_PENILAI',
            'konfigurasi' => true,
            'nama' => 'Aturan Penilai',
            'ringkas' => 'Siapa menilai siapa, dan berapa bobot tiap kategori.',
            'ikon' => 'diagram-3',
            'gerbang' => PaCycleGate::BUAT_PENUGASAN,
        ],
        [
            'kode' => 'PESERTA',
            'konfigurasi' => true,
            'nama' => 'Peserta',
            'ringkas' => 'Siapa ikut dinilai, dan siapa dikecualikan.',
            'ikon' => 'people',
            'gerbang' => PaCycleGate::SUNTING_PESERTA,

            /* Cacat yang sama dengan Langkah 5, dan ditemukan dari keluhan yang sama:
               `SUNTING_PESERTA` berhenti di PREPARED — menyiapkan ulang peserta sesudah
               siklus dikunci memang tidak boleh — tetapi MENGECUALIKAN SATU ORANG justru
               paling dibutuhkan di sana, dan `KECUALIKAN_PESERTA` di server memang
               mengizinkan LOCKED dan OPEN. Layarnya mematikan keduanya sekaligus. */
            'gerbang_tambahan' => ['kecualikan_peserta' => PaCycleGate::KECUALIKAN_PESERTA],
        ],
        [
            /* Langkah kelima, dan ia HARUS terpisah dari PESERTA.

               Baris `N_PA_Assignment` baru ada SESUDAH penyiapan berjalan; sebelum itu tidak
               ada apa pun untuk ditinjau. Menggabungkannya ke langkah Peserta berarti separuh
               isinya kosong sampai tombolnya ditekan, lalu berubah mendadak — dan admin tidak
               punya cara tahu bahwa masih ada pekerjaan sesudahnya.

               Yang dikerjakan di sini bukan menyusun ATURAN penilai (itu Langkah 3), melainkan
               memeriksa hasil penerapannya per orang: siapa yang penilainya kurang, siapa yang
               penilainya sudah resign, dan siapa yang perlu disesuaikan sendiri. */
            'kode' => 'PENUGASAN',

            /* BUKAN konfigurasi. Ia peninjauan hasil penerapan aturan, dan hasilnya baru ada
               sesudah penyiapan berjalan. Membedakannya penting untuk pertanyaan "apakah baris
               ini masih harus kembali ke wizard": jawabannya soal setelan yang belum diisi,
               bukan soal penugasan yang belum ditinjau. */
            'konfigurasi' => false,
            'nama' => 'Penugasan Penilai',
            'ringkas' => 'Siapa menilai siapa, per orang. Perbaiki yang kurang sebelum siklus dikunci.',
            'ikon' => 'person-check',
            'gerbang' => PaCycleGate::BUAT_PENUGASAN,

            /* GERBANG KEDUA, dan pemisahannya bukan kerapian.
               |
               | `BUAT_PENUGASAN` hanya berlaku di DRAFT/PREPARED: membentuk ulang penugasan
               | massal sesudah siklus dikunci memang tidak boleh. Tetapi MENGGANTI SATU
               | PENILAI justru paling dibutuhkan di sana — penilai resign, pindah unit, atau
               | ditolak HC Learn — dan `TAMBAH_PENILAI`/`BATALKAN_PENUGASAN` di sisi server
               | sudah sengaja mengizinkan LOCKED dan OPEN sejak keduanya dipisahkan.
               |
               | Layarnya tidak ikut. Sampai 10 September 2026 seluruh tombol langkah ini
               | dimatikan oleh satu bendera yang membaca `BUAT_PENUGASAN`, sehingga siklus
               | yang sudah dikunci menjadi jalan buntu: kesiapan HC Learn menyebut satu
               | penilai yang sudah resign, dan tidak ada satu tombol pun yang bisa ditekan
               | untuk membereskannya. Pintu yang dibuka di server tetapi tidak di layar sama
               | saja dengan pintu yang tidak pernah dibuka. */
            'gerbang_tambahan' => ['ubah_penilai' => PaCycleGate::TAMBAH_PENILAI],
        ],
    ];

    public const STATUS_BELUM = 'BELUM';

    public const STATUS_DRAFT = 'DRAFT';

    public const STATUS_SELESAI = 'SELESAI';

    public function __construct(
        private readonly PaCycleGate $gate,
    ) {}

    /**
     * Kemampuan langkah yang HIDUP LEBIH LAMA daripada `boleh_disunting`.
     *
     * Satu langkah bukan satu izin. "Boleh disunting" menjawab apakah setelannya masih boleh
     * disusun ulang; kemampuan di sini menjawab apakah satu perbaikan kecil masih boleh
     * dikerjakan — mengganti satu penilai, mengecualikan satu peserta. Gerbang di server sudah
     * lama membedakan keduanya; layarnya yang belum, dan akibatnya siklus terkunci menjadi
     * jalan buntu bagi persoalan yang justru baru muncul sesudah terkunci.
     *
     * Langkah tanpa gerbang tambahan tidak memulangkan kunci apa pun, jadi layar yang membaca
     * kunci yang tidak ada jatuh ke `boleh_disunting` — perilaku lama, tidak berubah.
     *
     * @param  array<string, mixed>  $def
     * @return array<string, bool>
     */
    private function kemampuan(array $def, int $cycleId, bool $boleh): array
    {
        $hasil = [];

        foreach (($def['gerbang_tambahan'] ?? []) as $nama => $gerbang) {
            $hasil[$nama] = $this->gate->boleh($gerbang, $cycleId);
        }

        return $hasil;
    }

    /**
     * Kode langkah KONFIGURASI, yaitu yang menentukan apakah setelan siklus sudah lengkap.
     *
     * @return list<string>
     */
    public static function kodeKonfigurasi(): array
    {
        return array_column(
            array_values(array_filter(self::LANGKAH, fn ($d) => $d['konfigurasi'])),
            'kode',
        );
    }

    /**
     * Ringkasan kelengkapan KONFIGURASI dari baris `N_PA_Cycle_Setup_Step` satu siklus.
     *
     * KENAPA DI SINI, BUKAN DI PEMANGGILNYA
     *
     * Sampai 4 September 2026 daftar siklus menghitungnya sendiri dengan aturan yang berbeda:
     * ia MENGECUALIKAN `PESERTA` tetapi IKUT MENGHITUNG `PENUGASAN`, sementara totalnya
     * memakai "jumlah langkah dikurangi satu". Dua aturan yang berselisih tentang langkah mana
     * yang dimaksud.
     *
     * Akibatnya tetap: `PENUGASAN` tidak pernah ditandai selesai oleh siapa pun, sehingga
     * hitungannya mentok di 3 dari 4 SELAMANYA. Admin yang sudah menyelesaikan seluruh setelan
     * tetap dibilang "1 langkah belum selesai" dan terus didorong kembali ke wizard, tanpa
     * pernah ada cara menyelesaikannya. Dibuktikan pada siklus CONTOHSAJA: empat langkah
     * SELESAI, hitungannya 3/4.
     *
     * @param  iterable<object>  $barisLangkah  baris dengan `Kode_Langkah` dan `Status_Langkah`
     * @return array{selesai:int, total:int, lengkap:bool}
     */
    public static function ringkasKonfigurasi(iterable $barisLangkah): array
    {
        $kode = self::kodeKonfigurasi();
        $selesai = 0;

        foreach ($barisLangkah as $b) {
            if (in_array($b->Kode_Langkah, $kode, true) && $b->Status_Langkah === self::STATUS_SELESAI) {
                $selesai++;
            }
        }

        $total = count($kode);

        return [
            'selesai' => $selesai,
            'total' => $total,
            'lengkap' => $selesai >= $total,
        ];
    }

    /**
     * Daftar kode langkah, berurut. Satu-satunya sumber urutan.
     *
     * @return list<string>
     */
    public static function kodeLangkah(): array
    {
        return array_column(self::LANGKAH, 'kode');
    }

    /**
     * Keadaan penyiapan satu siklus: tiap langkah, posisi admin, dan apa yang menghalangi.
     *
     * @return array<string, mixed>
     */
    public function keadaan(int $cycleId): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first([
                'Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Tahun', 'Status_Cycle',
                'Langkah_Setup_Aktif', 'PA_Cycle_Sumber_Id',
            ]);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        $tersimpan = DB::table('N_PA_Cycle_Setup_Step')
            ->where('PA_Cycle_Id', $cycleId)
            ->get(['Kode_Langkah', 'Status_Langkah', 'Ringkasan', 'Disimpan_At', 'Disimpan_By'])
            ->keyBy('Kode_Langkah');

        $this->selaraskanFaktaLangkah($cycleId, $tersimpan);

        $tersimpan = DB::table('N_PA_Cycle_Setup_Step')
            ->where('PA_Cycle_Id', $cycleId)
            ->get(['Kode_Langkah', 'Status_Langkah', 'Ringkasan', 'Disimpan_At', 'Disimpan_By'])
            ->keyBy('Kode_Langkah');

        $langkah = [];

        foreach (self::LANGKAH as $urut => $def) {
            $baris = $tersimpan->get($def['kode']);
            $status = $baris->Status_Langkah ?? self::STATUS_BELUM;

            /* Boleh-disunting dibaca dari gerbang siklus, bukan dari status langkahnya
               sendiri. Langkah yang sudah SELESAI tetap boleh dibuka lagi selama siklusnya
               masih mengizinkan — menyiapkan siklus bukan pekerjaan sekali jalan, dan
               mengunci langkah begitu ia disimpan memaksa admin membuang seluruh siklus
               hanya untuk memperbaiki satu angka. */
            $boleh = $this->gate->boleh($def['gerbang'], $cycleId);

            /* URUT, TIDAK BOLEH DILOMPATI. Tiap langkah menuliskan salinannya sendiri dari
               master, dan salinan itu jadi dasar langkah berikutnya — paket soal disusun
               atas level yang bobotnya sudah ditetapkan, aturan penilai atas paket yang
               sudah dipilih. Membuka langkah 3 sebelum langkah 1 selesai berarti menyetel
               sesuatu di atas dasar yang belum ada.

               Yang dilarang hanya MELOMPAT KE DEPAN. Kembali ke langkah yang sudah selesai
               tetap boleh, karena seluruh langkah sebelumnya menurut definisi juga sudah
               selesai — dan menyiapkan siklus memang bukan pekerjaan sekali jalan. */
            /* Langkah yang DIRINYA SENDIRI sudah selesai selalu boleh dibuka, walau ada
               langkah sebelumnya yang belum. Bukan kelonggaran: salinannya memang sudah
               tertulis, jadi tidak ada yang dilompati — dan keadaan itu lahir sendiri
               ketika cetak biru master disalin otomatis saat siklus dibuat. Menguncinya
               berarti admin tidak bisa MEMERIKSA setelan yang sudah jadi. */
            $belumSelesai = $status === self::STATUS_SELESAI
                ? null
                : $this->langkahSebelumnyaYangBelumSelesai($langkah);

            $langkah[] = [
                'urut' => $urut + 1,
                'kode' => $def['kode'],
                'nama' => $def['nama'],
                'ringkas' => $def['ringkas'],
                'ikon' => $def['ikon'],
                'status' => $status,
                'boleh_disunting' => $boleh,
                'alasan_terkunci' => $boleh ? null : $this->alasanTerkunci($def, $siklus->Status_Cycle),

                /* Kemampuan yang HIDUP LEBIH LAMA daripada `boleh_disunting`. Langkah yang
                   tidak punya gerbang kedua memulangkannya sama dengan yang pertama, jadi
                   layar boleh membacanya tanpa memeriksa langkah mana yang sedang dilihat. */
                'kemampuan' => $this->kemampuan($def, $cycleId, $boleh),

                /* Dipisahkan dari `boleh_disunting`: yang itu menjawab "status siklusnya
                   mengizinkan?", yang ini menjawab "gilirannya sudah tiba?". Menggabungkan
                   keduanya membuat layar tidak bisa membedakan siklus yang terkunci dari
                   langkah yang belum tiba gilirannya, dan keterangannya jadi salah pada
                   salah satu dari dua keadaan itu. */
                'dapat_dibuka' => $belumSelesai === null,
                'alasan_urutan' => $belumSelesai === null ? null : sprintf(
                    'Selesaikan langkah %d (%s) lebih dulu. Tiap langkah menuliskan '
                    .'salinannya sendiri dari master, dan langkah ini menyusun setelannya '
                    .'di atas salinan itu.',
                    $belumSelesai['urut'],
                    $belumSelesai['nama'],
                ),

                'ringkasan' => $baris->Ringkasan ?? null,
                'disimpan_pada' => $baris->Disimpan_At ?? null,
                'disimpan_oleh' => $baris->Disimpan_By ?? null,
            ];
        }

        $aktif = $this->langkahAktif($siklus->Langkah_Setup_Aktif, $langkah);

        return [
            'cycle' => [
                'id' => (int) $siklus->Id_PA_Cycle,
                'kode' => $siklus->Kode_Cycle,
                'nama' => $siklus->Nama_Cycle,
                'tahun' => (int) $siklus->Tahun,
                'status' => $siklus->Status_Cycle,
                'disalin_dari' => $siklus->PA_Cycle_Sumber_Id !== null
                    ? (int) $siklus->PA_Cycle_Sumber_Id
                    : null,
            ],
            'langkah' => $langkah,
            'langkah_aktif' => $aktif,
            'selesai' => count(array_filter($langkah, fn ($l) => $l['status'] === self::STATUS_SELESAI)),
            'total' => count($langkah),

            /* Penyiapan boleh dijalankan hanya bila SELURUH langkah selesai. Dikirim
               sebagai satu nilai supaya layar tidak perlu menyusun aturannya sendiri —
               dan supaya aturannya tidak berbeda antara layar dan server. */
            'siap_dijalankan' => $this->siapDijalankan($langkah, $siklus->Status_Cycle),
            'penghalang' => $this->penghalang($langkah, $siklus->Status_Cycle),
        ];
    }

    /**
     * Level yang dipakai karyawan aktif, beserta CAKUPANNYA di siklus ini.
     *
     * MENUTUP JEBAKAN URUTAN. Sampai sekarang layar langkah 2 (Paket Soal) menurunkan
     * daftar levelnya dari salinan aturan penilai — yang baru ada sesudah langkah 3
     * dijalankan. Akibatnya langkah 2 kosong sampai langkah 3 selesai, padahal nomornya
     * mengatakan sebaliknya, dan admin yang menurut urutannya benar justru melihat layar
     * kosong tanpa penjelasan.
     *
     * Daftar ini berdiri sendiri: ia dibaca dari karyawan aktif, bukan dari langkah mana
     * pun. Setiap langkah boleh dibuka kapan saja dan tetap tahu level apa yang ada.
     *
     * @return array<string, mixed>
     */
    public function levelSiklus(int $cycleId): array
    {
        /* Satu kueri, bukan satu per level. Menanyakan cakupan per baris berarti puluhan
           perjalanan ke database untuk halaman yang dibuka satu kali. */
        // Level PA (DDL-2026-177): koreksi per karyawan ikut dihitung, sama dengan pembentukan peserta.
        $lv = PaLevelPaResolver::sqlLevel();
        $dipakai = PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'), 'k', 'v', $this->tanggalAcuan($cycleId))
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', DB::raw($lv))
            ->where('k.Aktif', 'Y')
            ->whereNotIn(DB::raw($lv), PaLevelDikecualikan::daftar() ?: [0])
            ->groupBy(DB::raw($lv), 'l.Nama_Level')
            ->orderBy(DB::raw($lv))
            ->get([
                DB::raw("{$lv} as id_level"),
                'l.Nama_Level as nama_level',
                DB::raw('COUNT(DISTINCT k.Kode_Karyawan) as jumlah_karyawan'),
            ]);

        $punyaAturan = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->pluck('ID_Level')->map(fn ($x) => (int) $x)->flip();

        $punyaPaket = DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')
            ->pluck('ID_Level')->map(fn ($x) => (int) $x)->flip();

        $punyaBobot = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
            ->where('PA_Cycle_Id', $cycleId)
            ->pluck('ID_Level')->map(fn ($x) => (int) $x)->flip();

        $level = $dipakai->map(function ($r) use ($punyaAturan, $punyaPaket, $punyaBobot) {
            $id = (int) $r->id_level;

            return [
                'id_level' => $id,
                'nama_level' => $r->nama_level ?? "Level {$id}",
                'jumlah_karyawan' => (int) $r->jumlah_karyawan,
                'punya_bobot' => $punyaBobot->has($id),
                'punya_paket' => $punyaPaket->has($id),
                'punya_aturan' => $punyaAturan->has($id),
            ];
        })->all();

        return [
            'level' => $level,
            'total_karyawan' => array_sum(array_column($level, 'jumlah_karyawan')),
            'tanpa_bobot' => array_values(array_filter($level, fn ($l) => ! $l['punya_bobot'])),
            'tanpa_paket' => array_values(array_filter($level, fn ($l) => ! $l['punya_paket'])),
            'tanpa_aturan' => array_values(array_filter($level, fn ($l) => ! $l['punya_aturan'])),
        ];
    }

    /**
     * Mencari karyawan untuk dikecualikan, dengan penanda apakah ia SUDAH dikecualikan.
     *
     * Tanpa ini layar hanya bisa meminta admin MENGETIK kode karyawan. Kode yang salah
     * ketik diterima diam-diam — barisnya tersimpan, orangnya tidak pernah cocok dengan
     * siapa pun, dan pengecualiannya tidak berpengaruh apa-apa tanpa satu pun pesan.
     *
     * @return list<array<string, mixed>>
     */
    /**
     * Opsi filter organisasi untuk modal pencarian dan pengecualian karyawan.
     *
     * @return array<string, mixed>
     */
    public function opsiFilterKaryawan(): array
    {
        $divisi = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('vd.nama_divisi')
            ->where('vd.nama_divisi', '<>', '')
            ->distinct()
            ->orderBy('vd.nama_divisi')
            ->pluck('vd.nama_divisi')
            ->all();

        $departemen = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('vd.nama_sub_divisi')
            ->where('vd.nama_sub_divisi', '<>', '')
            ->distinct()
            ->orderBy('vd.nama_sub_divisi')
            ->pluck('vd.nama_sub_divisi')
            ->all();

        $lv = PaLevelPaResolver::sqlLevel();
        $level = PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'))
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', DB::raw($lv))
            ->where('k.Aktif', 'Y')
            ->whereNotIn(DB::raw($lv), PaLevelDikecualikan::daftar() ?: [0])
            ->distinct()
            ->orderBy(DB::raw($lv))
            ->get([DB::raw("{$lv} as id"), 'l.Nama_Level as nama'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'nama' => $r->nama ?? "Level {$r->id}"])
            ->all();

        $cabang = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('k.Cabang_Untuk_Gaji')
            ->where('k.Cabang_Untuk_Gaji', '<>', '')
            ->distinct()
            ->orderBy('k.Cabang_Untuk_Gaji')
            ->pluck('k.Cabang_Untuk_Gaji')
            ->all();

        return [
            'divisi' => $divisi,
            'departemen' => $departemen,
            'level' => $level,
            'cabang' => $cabang,
        ];
    }

    /**
     * Mencari karyawan untuk dikecualikan dengan dukungan filter organisasi & bulk pencocokan kode.
     *
     * @param  string|array<string, mixed>  $saringan
     * @return list<array<string, mixed>>
     */
    public function cariKaryawan(int $cycleId, string|array $saringan = '', int $batas = 1000): array
    {
        if (is_string($saringan)) {
            $saringan = ['q' => $saringan];
        }

        $kata = trim((string) ($saringan['q'] ?? ''));

        $normalisasi = function ($val): array {
            if (is_array($val)) {
                return array_values(array_filter(array_map('trim', $val), fn ($x) => $x !== '' && $x !== null));
            }
            if (is_string($val) && trim($val) !== '') {
                return [trim($val)];
            }
            if (is_numeric($val)) {
                return [$val];
            }

            return [];
        };

        $divisiList = $normalisasi($saringan['divisi'] ?? []);
        $departemenList = $normalisasi($saringan['departemen'] ?? []);
        $idLevelList = array_map('intval', $normalisasi($saringan['id_level'] ?? []));
        $cabangList = $normalisasi($saringan['cabang'] ?? []);
        $kodeList = $normalisasi($saringan['kode_list'] ?? []);

        $sudah = DB::table('N_PA_Cycle_Pre_Exclusion')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Kode_Perusahaan', 'Kode_Karyawan'])
            ->mapWithKeys(fn ($r) => [$r->Kode_Perusahaan.'|'.$r->Kode_Karyawan => true])
            ->all();

        $lv = PaLevelPaResolver::sqlLevel();
        $query = PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'), 'k', 'v', $this->tanggalAcuan($cycleId))
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', DB::raw($lv))
            ->leftJoin('View_Divisi_Sub_Divisi as vd',
                'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y');

        if (! empty($kodeList)) {
            $query->whereIn('k.Kode_Karyawan', $kodeList);
        } elseif (mb_strlen($kata) >= 1) {
            $query->where(function ($q) use ($kata) {
                $q->where('k.Nama', 'like', "%{$kata}%")
                    ->orWhere('k.Kode_Karyawan', 'like', "%{$kata}%");
            });
        }

        if (! empty($divisiList)) {
            $query->whereIn('vd.nama_divisi', $divisiList);
        }

        if (! empty($departemenList)) {
            $query->whereIn('vd.nama_sub_divisi', $departemenList);
        }

        if (! empty($idLevelList)) {
            $query->whereIn(DB::raw($lv), $idLevelList);
        }

        if (! empty($cabangList)) {
            $query->whereIn('k.Cabang_Untuk_Gaji', $cabangList);
        }

        return $query
            ->orderBy('k.Nama')
            ->limit(max(1, min($batas, 2000)))
            ->get([
                'k.Kode_Perusahaan as kode_perusahaan',
                'k.Kode_Karyawan as kode_karyawan',
                'k.Nama as nama',
                DB::raw("{$lv} as id_level"),
                'l.Nama_Level as nama_level',
                'vd.nama_divisi as nama_divisi',
                'vd.nama_sub_divisi as nama_departemen',
                'k.Cabang_Untuk_Gaji as cabang',
            ])
            ->map(fn ($r) => (array) $r + [
                'sudah_dikecualikan' => isset($sudah[$r->kode_perusahaan.'|'.$r->kode_karyawan]),
            ])
            ->all();
    }

    /**
     * Menyimpan keadaan satu langkah.
     *
     * Dipanggil oleh layanan yang benar-benar menulis setelannya (bobot pilar, paket soal,
     * aturan penilai), bukan oleh layar. Layar yang menandai langkahnya sendiri selesai
     * akan tetap menandainya selesai ketika penyimpanannya gagal.
     */
    public function tandai(
        int $cycleId,
        string $kodeLangkah,
        string $status,
        ?string $ringkasan = null,
        ?int $olehUserId = null,
    ): void {
        if (! in_array($kodeLangkah, self::kodeLangkah(), true)) {
            throw new RuntimeException("Langkah penyiapan '{$kodeLangkah}' tidak dikenal.");
        }

        if (! in_array($status, [self::STATUS_BELUM, self::STATUS_DRAFT, self::STATUS_SELESAI], true)) {
            throw new RuntimeException("Status langkah '{$status}' tidak dikenal.");
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();

        $ada = DB::table('N_PA_Cycle_Setup_Step')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Kode_Langkah', $kodeLangkah)
            ->value('Id_PA_Cycle_Setup_Step');

        if ($ada !== null) {
            DB::table('N_PA_Cycle_Setup_Step')
                ->where('Id_PA_Cycle_Setup_Step', $ada)
                ->update([
                    'Status_Langkah' => $status,
                    'Ringkasan' => $ringkasan,
                    'Disimpan_At' => $sekarang,
                    'Disimpan_By' => $olehUserId,
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);

            return;
        }

        DB::table('N_PA_Cycle_Setup_Step')->insert([
            'PA_Cycle_Id' => $cycleId,
            'Kode_Langkah' => $kodeLangkah,
            'Status_Langkah' => $status,
            'Ringkasan' => $ringkasan,
            'Disimpan_At' => $sekarang,
            'Disimpan_By' => $olehUserId,
            'Created_By' => $olehUserId,
        ]);
    }

    /**
     * Memindahkan posisi admin.
     *
     * Ditulis sebagai FAKTA. Tidak ada pemeriksaan "langkah sebelumnya harus selesai":
     * admin berhak melihat langkah 3 sebelum mengisi langkah 1, dan yang menahan penyiapan
     * berjalan adalah `siap_dijalankan`, bukan pembatasan gerak.
     */
    public function pindah(int $cycleId, string $kodeLangkah, ?int $olehUserId = null): void
    {
        if (! in_array($kodeLangkah, self::kodeLangkah(), true)) {
            throw new RuntimeException("Langkah penyiapan '{$kodeLangkah}' tidak dikenal.");
        }

        $this->pastikanGilirannyaTiba($cycleId, $kodeLangkah);

        $terpengaruh = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->update([
                'Langkah_Setup_Aktif' => $kodeLangkah,
                'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                'Updated_By' => $olehUserId,
            ]);

        if ($terpengaruh === 0) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }
    }

    /**
     * Menolak perpindahan ke langkah yang gilirannya belum tiba.
     *
     * Dijaga di SERVER, bukan hanya dengan mematikan tombol. Layar yang mematikan tombolnya
     * sendiri tetap bisa dilewati siapa pun yang mengirim permintaannya langsung — dan
     * langkah yang dilompati meninggalkan siklus dengan salinan setengah jadi yang baru
     * ketahuan jauh kemudian, saat penugasan dibentuk di atasnya.
     */
    public function pastikanGilirannyaTiba(int $cycleId, string $kodeLangkah): void
    {
        $keadaan = $this->keadaan($cycleId);

        foreach ($keadaan['langkah'] as $l) {
            if ($l['kode'] !== $kodeLangkah) {
                continue;
            }

            if ($l['dapat_dibuka']) {
                return;
            }

            throw new RuntimeException($l['alasan_urutan']);
        }

        throw new RuntimeException("Langkah penyiapan '{$kodeLangkah}' tidak dikenal.");
    }

    /**
     * Langkah TERAWAL yang belum selesai di antara yang sudah terkumpul.
     *
     * Dipanggil sambil menyusun daftarnya, jadi yang diperiksa memang hanya langkah
     * SEBELUM yang sedang disusun — tanpa perlu indeks maupun pencarian mundur.
     *
     * @param  list<array<string, mixed>>  $sudahDisusun
     * @return array<string, mixed>|null
     */
    private function langkahSebelumnyaYangBelumSelesai(array $sudahDisusun): ?array
    {
        foreach ($sudahDisusun as $l) {
            if ($l['status'] !== self::STATUS_SELESAI) {
                return $l;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Memeriksa dan menyelaraskan status langkah langsung dari snapshot faktual.
     *
     * Mengatasi kasus di mana data sudah lengkap tersalin/terkonfigurasi tetapi
     * penanda langkah di tabel N_PA_Cycle_Setup_Step belum sempat terisi.
     */
    private function selaraskanFaktaLangkah(int $cycleId, $tersimpan): void
    {
        // 1. BOBOT_PILAR
        if (! $tersimpan->has('BOBOT_PILAR') || $tersimpan->get('BOBOT_PILAR')->Status_Langkah !== self::STATUS_SELESAI) {
            $barisBobot = DB::table('N_PA_Cycle_Pillar_Weight_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->get(['ID_Level', 'Bobot_KPI', 'Bobot_Kompetensi']);
            if ($barisBobot->isNotEmpty()) {
                $belumGenap = $barisBobot->filter(
                    fn ($b) => abs(((float) $b->Bobot_KPI + (float) $b->Bobot_Kompetensi) - 100.0) > 0.01
                )->count();
                if ($belumGenap === 0) {
                    $this->tandai($cycleId, 'BOBOT_PILAR', self::STATUS_SELESAI, sprintf('%d level, bobot genap 100 semua.', $barisBobot->count()));
                }
            }
        }

        // 2. ATURAN_PENILAI
        if (! $tersimpan->has('ATURAN_PENILAI') || $tersimpan->get('ATURAN_PENILAI')->Status_Langkah !== self::STATUS_SELESAI) {
            $kepala = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_PA_Cycle_Rater_Policy_Snapshot');
            if ($kepala->isNotEmpty()) {
                $rincian = DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
                    ->whereIn('PA_Cycle_Rater_Policy_Snapshot_Id', $kepala->all())
                    ->where('Flag_Aktif', 'Y')
                    ->select('PA_Cycle_Rater_Policy_Snapshot_Id', DB::raw('SUM(Bobot_Persen) as total_bobot'))
                    ->groupBy('PA_Cycle_Rater_Policy_Snapshot_Id')
                    ->get();
                $belumGenap = $rincian->filter(fn ($r) => abs((float) $r->total_bobot - 100.0) > 0.01)->count();
                if ($belumGenap === 0 && $rincian->count() === $kepala->count()) {
                    $this->tandai($cycleId, 'ATURAN_PENILAI', self::STATUS_SELESAI, sprintf('%d level, bobot genap 100 semua.', $kepala->count()));
                }
            }
        }

        // 3. PAKET_SOAL
        if (! $tersimpan->has('PAKET_SOAL') || $tersimpan->get('PAKET_SOAL')->Status_Langkah !== self::STATUS_SELESAI) {
            $berpaket = DB::table('N_PA_Cycle_Question_Package')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Flag_Aktif', 'Y')
                ->distinct()
                ->pluck('ID_Level')
                ->map(fn ($x) => (int) $x)
                ->all();
            $dipakai = DB::table('N_PA_Cycle_Rater_Policy_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)
                ->where('Flag_Aktif', 'Y')
                ->distinct()
                ->pluck('ID_Level')
                ->map(fn ($x) => (int) $x)
                ->all();
            if ($dipakai !== []) {
                $kurang = array_values(array_diff($dipakai, $berpaket));
                /* "Tiap level punya SATU paket" belum berarti setiap sel penilaiannya tertutup.
                   Aturan selesai disamakan dengan penjaga kunci siklus (`selTanpaPaket`): sel
                   yang punya penugasan wajib berpaket khusus atau berpaket bawaan level. */
                $selKosong = $kurang === [] ? app(PaCyclePreparationService::class)->selTanpaPaket($cycleId) : [];
                if ($berpaket !== [] && $kurang === [] && $selKosong === []) {
                    $this->tandai($cycleId, 'PAKET_SOAL', self::STATUS_SELESAI, sprintf('%d level bersoal.', count($berpaket)));
                } elseif ($berpaket !== [] && $kurang === []) {
                    $this->tandai($cycleId, 'PAKET_SOAL', self::STATUS_DRAFT, sprintf('%d sel penilaian belum terikat paket soal.', count($selKosong)));
                } elseif ($berpaket !== []) {
                    $this->tandai($cycleId, 'PAKET_SOAL', self::STATUS_DRAFT, sprintf('%d level bersoal; %d level masih tanpa paket.', count($berpaket), count($kurang)));
                }
            }
        }

        $this->selaraskanPenugasan($cycleId, $tersimpan);
    }

    /**
     * Menyelaraskan status langkah PENUGASAN dengan keadaan penugasan yang sebenarnya.
     *
     * KENAPA BARU ADA SEKARANG
     *
     * Sebelum ini TIDAK ADA satu baris kode pun yang menandai langkah ini, sehingga di wizard
     * ia selamanya "BELUM" walaupun seluruh penugasan sudah dibuat dan diperiksa. Langkah yang
     * tidak pernah bisa selesai membuat wizardnya terbaca menggantung, dan admin tidak punya
     * cara tahu bahwa pekerjaannya sudah cukup untuk mengunci.
     *
     * SELESAI dituntut lebih dari sekadar "ada penugasan": tiap peserta DIIKUTKAN harus punya
     * penilai. Peserta tanpa penilai adalah peserta yang tidak akan pernah punya nilai, dan
     * menyatakannya "selesai" berarti menyembunyikan itu tepat sebelum pembekuan.
     */
    private function selaraskanPenugasan(int $cycleId, $tersimpan): void
    {
        $diikutkan = DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->count();

        if ($diikutkan === 0) {
            return;
        }

        $berpenilai = DB::table('N_PA_Participant as p')
            ->join('N_PA_Assignment as a', 'a.PA_Participant_Id', '=', 'p.Id_PA_Participant')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('p.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')
            ->distinct()
            ->count('p.Id_PA_Participant');

        if ($berpenilai === 0) {
            return;
        }

        $tanpa = $diikutkan - $berpenilai;

        $this->tandai(
            $cycleId,
            'PENUGASAN',
            $tanpa === 0 ? self::STATUS_SELESAI : self::STATUS_DRAFT,
            $tanpa === 0
                ? sprintf('%d peserta, semuanya sudah berpenilai.', $diikutkan)
                : sprintf('%d dari %d peserta belum punya penilai sama sekali.', $tanpa, $diikutkan),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $langkah
     */
    private function langkahAktif(?string $tersimpan, array $langkah): string
    {
        /* Yang tersimpan MENANG. Ini seluruh maksud kolomnya: posisi adalah tempat admin
           berada, bukan tempat yang menurut kelengkapan isi seharusnya ia berada. */
        if ($tersimpan !== null && in_array($tersimpan, self::kodeLangkah(), true)) {
            return $tersimpan;
        }

        foreach ($langkah as $l) {
            if ($l['status'] !== self::STATUS_SELESAI) {
                return $l['kode'];
            }
        }

        return $langkah[0]['kode'];
    }

    /**
     * @param  list<array<string, mixed>>  $langkah
     */
    private function siapDijalankan(array $langkah, string $statusCycle): bool
    {
        if (! in_array($statusCycle, ['DRAFT', 'PREPARED'], true)) {
            return false;
        }

        foreach ($langkah as $l) {
            if (in_array($l['kode'], ['PESERTA', 'PENUGASAN'], true)) {
                /* Keduanya tidak perlu SELESAI untuk MENJALANKAN penyiapan: PESERTA adalah
                   langkah yang menjalankannya, dan PENUGASAN baru bisa dikerjakan sesudah
                   penyiapan menghasilkan pesertanya. Menuntut keduanya selesai lebih dulu
                   membuat tombolnya tidak akan pernah menyala. */
                continue;
            }

            if ($l['status'] !== self::STATUS_SELESAI) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $langkah
     * @return list<string>
     */
    private function penghalang(array $langkah, string $statusCycle): array
    {
        $pesan = [];

        if (! in_array($statusCycle, ['DRAFT', 'PREPARED'], true)) {
            $pesan[] = "Siklus berstatus {$statusCycle}; penyiapan hanya berjalan pada DRAFT atau PREPARED.";

            return $pesan;
        }

        foreach ($langkah as $l) {
            if (in_array($l['kode'], ['PESERTA', 'PENUGASAN'], true)
                || $l['status'] === self::STATUS_SELESAI) {
                continue;
            }

            $pesan[] = $l['status'] === self::STATUS_DRAFT
                ? "Langkah {$l['urut']} — {$l['nama']} masih tersimpan sebagai draft."
                : "Langkah {$l['urut']} — {$l['nama']} belum diisi.";
        }

        return $pesan;
    }

    /**
     * @param  array<string, mixed>  $def
     */
    private function alasanTerkunci(array $def, string $statusCycle): string
    {
        $izin = $this->gate->statusUntukOperasi($def['gerbang']);

        return sprintf(
            '%s hanya bisa disunting saat siklus berstatus %s; sekarang %s.',
            $def['nama'],
            $izin === [] ? '—' : implode(' atau ', $izin),
            $statusCycle,
        );
    }

    /** Tanggal acuan koreksi Level PA: tanggal acuan organisasi siklus, cadangannya akhir periode. */
    private function tanggalAcuan(int $cycleId): ?string
    {
        $c = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)->first(['Tanggal_Acuan_Organisasi', 'Periode_Dinilai_Sampai']);
        $t = $c?->Tanggal_Acuan_Organisasi ?? $c?->Periode_Dinilai_Sampai;

        return $t === null ? null : substr((string) $t, 0, 10);
    }
}
