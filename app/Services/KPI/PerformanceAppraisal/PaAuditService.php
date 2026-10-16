<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lini masa audit satu siklus — siapa mengubah apa, kapan, dan kenapa.
 *
 * TIDAK ADA TABEL BARU, DAN ITU BUKAN JALAN PINTAS
 *
 * Modul ini sejak awal MENONAKTIFKAN alih-alih menghapus, dan menuntut alasan pada tiap
 * perubahan yang menggeser nilai orang. Akibatnya riwayatnya sudah tersimpan sebagai baris
 * yang masih ada: penugasan yang dibatalkan menyimpan `Cancel_Reason`, penyimpangan yang
 * dicabut tetap berbaris dengan `Alasan_Perubahan`, kalibrasi punya tabel riwayatnya
 * sendiri lengkap dengan nama pelaku yang dibekukan.
 *
 * Membuat tabel riwayat baru di atas itu akan menyalin data yang sudah ada ke tempat
 * kedua, dan dua salinan riwayat yang sama pasti berselisih suatu hari.
 *
 * SATU HAL YANG MEMANG TIDAK TERSIMPAN, DAN DISEBUT TERANG-TERANGAN
 *
 * Perubahan konfigurasi — nama siklus, periode, nilai master — hanya menyisakan
 * `Updated_At`/`Updated_By`. **Nilai lamanya hilang.** Lini masa ini melaporkan bahwa
 * perubahan terjadi dan siapa pelakunya, tetapi tidak bisa mengatakan dari apa menjadi
 * apa. Menyembunyikan batas itu jauh lebih berbahaya daripada menyebutkannya: pembaca
 * yang mengira ini riwayat lengkap akan menyimpulkan tidak ada perubahan pada hal-hal
 * yang justru tidak terekam.
 *
 * DIGABUNG DI SQL, BUKAN DI MEMORI
 *
 * Penggabungan lewat `UNION ALL` membuat pengurutan dan paginasinya benar secara global.
 * Mengambil sebagian dari tiap sumber lalu mengurutkannya di PHP akan menghasilkan
 * "halaman 2" yang melewatkan kejadian — dan yang terlewat justru yang paling tua, yaitu
 * yang paling sering dicari orang saat menelusuri sesuatu.
 */
class PaAuditService
{
    public const MAKS_PER_HALAMAN = 100;

    /*
     | Batas baris MENTAH yang diambil sebelum dikelompokkan.
     |
     | Pengelompokan harus terjadi sebelum pemenggalan halaman — kelompok yang dipenggal
     | lebih dulu akan terbelah antar halaman dan jumlahnya salah di kedua sisi. Karena itu
     | barisnya diambil sekaligus, dan karena itu pula ia harus berbatas: satu siklus besar
     | bisa punya ribuan kejadian, dan pengambilan tanpa batas adalah cara paling sunyi
     | untuk menghabiskan memori.
     |
     | Ketika batas ini tersentuh, `dipangkas` menyala — pembaca diberi tahu bahwa yang
     | dilihatnya bukan seluruhnya, bukan dibiarkan menyimpulkan sendiri.
     */
    private const MAKS_BARIS_MENTAH = 5000;

    /** Jenis kejadian yang bisa disaring. */
    public const JENIS = [
        'PENUGASAN_DIBATALKAN',
        'OVERRIDE_PENILAI',
        'OVERRIDE_PILAR',
        'OVERRIDE_SYARAT',
        'KALIBRASI',
        'PESERTA_DIKECUALIKAN',
        /* Dua jenis yang ditambahkan 2 September 2026, keduanya menutup kebutaan yang
           sama: peristiwanya SUDAH tersimpan, hanya tidak pernah dibaca.

           `HASIL_DITERBITKAN` per orang — `SIKLUS_DITERBITKAN` bersumber dari kolom yang
           hanya terisi bila penerbitan ditutup, dan itu ditolak selama masih ada peserta
           tertahan; pada siklus yang tak pernah tuntas, penerbitan tidak meninggalkan
           jejak sama sekali.

           `BOBOT_PILAR_DIUBAH` — alasannya diwajibkan sesudah kunci justru karena
           menggeser nilai akhir setiap peserta pada level itu, lalu tak pernah tampil. */
        'HASIL_DITERBITKAN',
        'BOBOT_PILAR_DIUBAH',
        'SIKLUS_DIKUNCI',
        'SIKLUS_DITERBITKAN',
    ];

    /**
     * @param  array{jenis?:string|null, participant_id?:int|null, q?:string|null,
     *               hanya_keputusan_manusia?:bool, halaman?:int, per_halaman?:int}  $saringan
     * @return array<string, mixed>
     */
    public function lini(int $cycleId, array $saringan = []): array
    {
        $siklus = DB::table('N_PA_Cycle')->where('Id_PA_Cycle', $cycleId)
            ->first(['Id_PA_Cycle', 'Kode_Cycle', 'Nama_Cycle', 'Status_Cycle']);

        if ($siklus === null) {
            throw new RuntimeException("Siklus {$cycleId} tidak ditemukan.");
        }

        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $diminta = (int) ($saringan['per_halaman'] ?? 25);
        $perHalaman = max(1, min($diminta, self::MAKS_PER_HALAMAN));

        $dasar = fn () => $this->gabungan($cycleId, $saringan);

        $mentah = DB::query()->fromSub($dasar(), 'x')
            // Terbaru lebih dulu; kunci kedua supaya kejadian sedetik tidak berpindah
            // halaman antar permintaan.
            ->orderByDesc('waktu')
            ->orderByDesc('urut')
            ->limit(self::MAKS_BARIS_MENTAH + 1)
            ->get();

        $terpangkas = $mentah->count() > self::MAKS_BARIS_MENTAH;
        $mentah = $mentah->take(self::MAKS_BARIS_MENTAH);

        $kelompok = $this->kelompokkan($mentah);

        $total = count($kelompok);
        $baris = collect(array_slice($kelompok, ($halaman - 1) * $perHalaman, $perHalaman));

        $nama = $this->namaPelaku($baris->pluck('aktor_id')->filter()->unique()->all());

        return [
            'siklus' => (array) $siklus,
            'kejadian' => $baris->map(fn ($b) => [
                'waktu' => $b->waktu,

                /* SATU BARIS = SATU PERISTIWA, bukan satu orang.

                   Sebelum 7 September 2026 tiap sasaran menjadi barisnya sendiri. Pada
                   siklus sungguhan itu berarti 99 baris "Peserta dikecualikan" yang identik
                   kecuali namanya — dan tiga keputusan manusia yang justru dicari
                   tenggelam di antaranya. Komentar di berkas ini sudah menyebut bahayanya;
                   yang belum ada obatnya. */
                'jumlah' => $b->jumlah,
                'waktu_awal' => $b->waktu_awal,
                'waktu_akhir' => $b->waktu_akhir,

                /* Sasaran yang diwakili, dibatasi. Daftar penuh 99 nama di dalam satu baris
                   hanya memindahkan masalahnya ke dalam. Yang perlu dibaca sekilas adalah
                   "siapa saja kira-kira", dan sisanya dihitung. */
                'sasaran_contoh' => $b->sasaran_contoh,
                'sasaran_lain' => $b->sasaran_lain,

                'jenis' => $b->jenis,
                /* Dikembalikan, bukan hanya diterima sebagai saringan. Tanpa ini layar
                   bisa MENYARING per peserta tetapi tidak bisa membuat tautan "lihat
                   kejadian orang ini" dari sebuah baris — saringan yang hanya bisa diisi
                   dari luar, tak pernah dari apa yang sedang dilihat. Kosong untuk
                   kejadian setingkat siklus. */
                'participant_id' => $b->participant_id === null ? null : (int) $b->participant_id,
                'sasaran' => $b->sasaran,
                'sebab' => $b->sebab,
                'rincian' => $b->rincian,
                'aktor_id' => $b->aktor_id === null ? null : (int) $b->aktor_id,
                /* Nama dibekukan menang atas hasil join. Kalibrasi menyimpan namanya
                   sendiri (`Performed_By_Nama`) justru karena pelaku yang sudah keluar
                   dari perusahaan membuat join-nya kosong — persis pada baris yang paling
                   perlu dijelaskan. */
                'aktor_nama' => $b->aktor_nama
                    ?: ($b->aktor_id === null ? null : ($nama[(int) $b->aktor_id] ?? null)),
                /* Kejadian tanpa pelaku adalah ATURAN YANG MENYALA, bukan keputusan
                   seseorang — misalnya 99 pengecualian FREELANCE yang lahir sekaligus saat
                   siklus disiapkan. Ditandai, bukan disembunyikan: ia konteks yang sah,
                   tetapi menenggelamkan tiga keputusan manusia di balik sembilan puluh
                   sembilan baris yang identik membuat audit ini kehilangan gunanya. */
                'oleh_sistem' => $b->aktor_id === null,
                'aktor_tidak_diketahui' => $b->aktor_id === null && $b->aktor_nama === null,
            ])->all(),
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => $total,
            'total_halaman' => (int) ceil($total / $perHalaman),
            'dipangkas' => $diminta > self::MAKS_PER_HALAMAN || $terpangkas,
            'jenis_tersedia' => self::JENIS,

            /* Cacah keduanya SELALU dikirim, tak peduli saringannya. Layar yang hanya
               menampilkan keputusan manusia tetap harus bisa mengatakan "dan 99 lainnya
               lahir dari aturan" — kalau tidak, orang mengira tidak ada yang terjadi.

               Karena itu dihitung dari saringan TANPA pemisah manusia/sistem, bukan dari
               `$total` yang sudah tersaring: memakai `$total` membuat cacah sistem menjadi
               nol persis ketika penyaringnya menyala, yaitu ketika angka itu paling
               dibutuhkan. */
            'cacah' => $this->cacahPelaku($cycleId, $saringan),

            /* Batas dinyatakan DI DALAM balasannya, bukan hanya di dokumen. Pembaca yang
               mengira ini riwayat lengkap akan menyimpulkan tidak ada perubahan pada
               hal-hal yang justru tidak terekam. */
            'batas' => [
                'konfigurasi_tanpa_nilai_lama' => true,
                'catatan' => 'Perubahan konfigurasi siklus dan master hanya menyisakan waktu '
                    .'dan pelakunya; nilai sebelum perubahan tidak disimpan, sehingga lini '
                    .'masa ini tidak bisa mengatakan dari apa menjadi apa untuk keduanya.',
            ],
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $saringan
     */
    /**
     * Menggabungkan kejadian sejenis yang lahir dari SATU tindakan.
     *
     * KUNCINYA: jenis, pelaku, rincian, sebab, dan TANGGALNYA.
     *
     * Tanggal ikut supaya dua tindakan serupa pada hari berbeda tidak melebur menjadi satu
     * baris — yang dicari orang di audit hampir selalu "kapan", dan kelompok yang membentang
     * lintas hari berhenti bisa menjawabnya. Sebaliknya, menit TIDAK ikut: satu penyiapan
     * massal yang berjalan melewati pergantian menit akan terbelah menjadi dua baris yang
     * tidak berarti apa-apa bagi pembacanya — terukur pada siklus uji, 99 pengecualian
     * terpecah 53 dan 46 hanya karena jam menunjukkan 06:41 lalu 06:42.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $mentah
     * @return list<object>
     */
    private function kelompokkan($mentah): array
    {
        $peta = [];

        foreach ($mentah as $b) {
            $kunci = implode('|', [
                $b->jenis,
                (string) ($b->aktor_id ?? ''),
                (string) ($b->aktor_nama ?? ''),
                (string) ($b->rincian ?? ''),
                (string) ($b->sebab ?? ''),
                substr((string) $b->waktu, 0, 10),
            ]);

            if (! isset($peta[$kunci])) {
                /* Baris PERTAMA menjadi wakilnya, dan karena urutannya terbaru-dulu, ia
                   membawa waktu terbaru. Kolom lamanya dipertahankan apa adanya supaya
                   pembaca yang sudah ada — dialog audit di halaman siklus — tetap bekerja
                   tanpa disentuh. */
                $peta[$kunci] = (object) ((array) $b + [
                    'jumlah' => 0,
                    'waktu_awal' => $b->waktu,
                    'waktu_akhir' => $b->waktu,
                    'sasaran_contoh' => [],
                    'sasaran_lain' => 0,
                ]);
            }

            $g = $peta[$kunci];
            $g->jumlah++;
            $g->waktu_awal = $b->waktu;

            if ($b->sasaran !== null && $b->sasaran !== '') {
                if (count($g->sasaran_contoh) < 5) {
                    $g->sasaran_contoh[] = $b->sasaran;
                } else {
                    $g->sasaran_lain++;
                }
            }
        }

        foreach ($peta as $g) {
            /* `participant_id` hanya berarti pada kelompok berisi SATU orang. Membiarkannya
               terisi pada kelompok berisi 99 akan membuat tautan "lihat kejadian orang ini"
               menunjuk satu orang yang kebetulan pertama — tautan yang benar bentuknya dan
               salah isinya. */
            if ($g->jumlah > 1) {
                $g->participant_id = null;
                $g->sasaran = null;
            }
        }

        return array_values($peta);
    }

    private function gabungan(int $cycleId, array $saringan)
    {
        $sumber = [
            $this->penugasanDibatalkan($cycleId),
            $this->override($cycleId, 'N_PA_Participant_Rater_Override', 'OVERRIDE_PENILAI'),
            $this->override($cycleId, 'N_PA_Participant_Pillar_Override', 'OVERRIDE_PILAR'),
            $this->override($cycleId, 'N_PA_Participant_Requirement_Override', 'OVERRIDE_SYARAT'),
            $this->kalibrasi($cycleId),
            $this->pesertaDikecualikan($cycleId),
            $this->hasilDiterbitkan($cycleId),
            $this->bobotPilarDiubah($cycleId),
            $this->siklus($cycleId),
        ];

        $q = array_shift($sumber);

        foreach ($sumber as $s) {
            $q->unionAll($s);
        }

        $luar = DB::query()->fromSub($q, 'g');

        if (! empty($saringan['jenis'])) {
            $luar->where('jenis', $saringan['jenis']);
        }

        if (! empty($saringan['participant_id'])) {
            $luar->where('participant_id', (int) $saringan['participant_id']);
        }

        /* Baku MENAMPILKAN semuanya. Menyaring diam-diam akan membuat orang menyimpulkan
           tidak ada kejadian pada hal yang justru disembunyikan — kesalahan yang jauh
           lebih mahal daripada halaman yang ramai. */
        if (! empty($saringan['hanya_keputusan_manusia'])) {
            $luar->whereNotNull('aktor_id');
        }

        $cari = trim((string) ($saringan['q'] ?? ''));

        if ($cari !== '') {
            $luar->where(function ($w) use ($cari) {
                $w->where('sasaran', 'LIKE', '%'.$cari.'%')
                    ->orWhere('sebab', 'LIKE', '%'.$cari.'%');
            });
        }

        return $luar;
    }

    private function penugasanDibatalkan(int $cycleId)
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->whereNotNull('a.Cancelled_At')
            ->selectRaw("
                a.Cancelled_At as waktu, a.Id_PA_Assignment as urut,
                'PENUGASAN_DIBATALKAN' as jenis,
                p.Id_PA_Participant as participant_id,
                CONCAT(p.Nama_Karyawan_Saat_Cycle, ' — penilai ', a.Nama_Penilai_Saat_Cycle) as sasaran,
                a.Cancel_Reason as sebab,
                CONCAT('Penugasan #', a.Id_PA_Assignment) as rincian,
                a.Cancelled_By as aktor_id,
                CAST(NULL AS nvarchar(150)) as aktor_nama
            ");
    }

    private function override(int $cycleId, string $tabel, string $jenis)
    {
        return DB::table($tabel.' as o')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'o.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->selectRaw("
                COALESCE(o.Updated_At, o.Created_At) as waktu,
                o.PA_Participant_Id as urut,
                '{$jenis}' as jenis,
                p.Id_PA_Participant as participant_id,
                p.Nama_Karyawan_Saat_Cycle as sasaran,
                o.Alasan_Perubahan as sebab,
                CASE WHEN o.Flag_Aktif = 'Y' THEN 'Berlaku' ELSE 'Dicabut' END as rincian,
                COALESCE(o.Updated_By, o.Created_By) as aktor_id,
                CAST(NULL AS nvarchar(150)) as aktor_nama
            ");
    }

    private function kalibrasi(int $cycleId)
    {
        return DB::table('N_PA_Calibration_History as h')
            ->join('N_PA_Result as r', 'r.Id_PA_Result', '=', 'h.PA_Result_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->selectRaw("
                h.Performed_At as waktu, h.Id_PA_Calibration_History as urut,
                'KALIBRASI' as jenis,
                p.Id_PA_Participant as participant_id,
                p.Nama_Karyawan_Saat_Cycle as sasaran,
                h.Alasan as sebab,
                CONCAT('Grade ', COALESCE(h.Kode_Grade_Sebelum, '-'), ' menjadi ',
                       COALESCE(h.Kode_Grade_Sesudah, '-')) as rincian,
                h.Performed_By as aktor_id,
                h.Performed_By_Nama as aktor_nama
            ");
    }

    /**
     * Penerbitan hasil PER ORANG.
     *
     * Sebelum 2 September 2026 satu-satunya jenis penerbitan adalah `SIKLUS_DITERBITKAN`,
     * yang bersumber dari `N_PA_Cycle.Published_At` — dan kolom itu hanya terisi bila
     * `tutupPenerbitan()` berhasil, sedangkan ia DITOLAK selama masih ada peserta
     * tertahan. Pada siklus yang tidak pernah tuntas — keadaan normal, bukan pengecualian
     * — tindakan paling berkonsekuensi bagi seorang karyawan tidak meninggalkan satu baris
     * pun di audit.
     *
     * `Published_At`/`Published_By` per hasil sudah ditulis sejak awal. Yang kurang bukan
     * datanya, melainkan pembacaannya.
     */
    private function hasilDiterbitkan(int $cycleId)
    {
        return DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNotNull('r.Published_At')
            ->selectRaw("
                r.Published_At as waktu,
                r.Id_PA_Result as urut,
                'HASIL_DITERBITKAN' as jenis,
                p.Id_PA_Participant as participant_id,
                p.Nama_Karyawan_Saat_Cycle as sasaran,
                CAST(NULL AS nvarchar(500)) as sebab,
                CAST('Hasil diterbitkan ke karyawan' AS varchar(60)) as rincian,
                r.Published_By as aktor_id,
                CAST(NULL AS nvarchar(150)) as aktor_nama
            ");
    }

    /**
     * Perubahan bobot pilar per siklus (K23).
     *
     * `PaCyclePillarWeightService::simpan()` MEWAJIBKAN alasan sesudah siklus dikunci,
     * justru karena perubahan ini menggeser nilai akhir SETIAP peserta pada level itu.
     * Alasannya tersimpan rapi di `Alasan_Perubahan` — lalu tidak pernah sampai ke layar
     * audit, karena tidak ada jenis yang membacanya. Aturan yang menuntut alasan tetapi
     * tidak pernah menampilkannya hanya memindahkan beban ke orang yang mengetiknya.
     *
     * Setingkat LEVEL, bukan peserta: `participant_id` sengaja kosong.
     */
    private function bobotPilarDiubah(int $cycleId)
    {
        return DB::table('N_PA_Cycle_Pillar_Weight_Snapshot as w')
            ->leftJoin('N_PA_Level as l', 'l.ID_Level', '=', 'w.ID_Level')
            ->where('w.PA_Cycle_Id', $cycleId)
            ->whereNotNull('w.Alasan_Perubahan')
            ->selectRaw("
                COALESCE(w.Updated_At, w.Snapshot_At) as waktu,
                w.Id_PA_Cycle_Pillar_Weight_Snapshot as urut,
                'BOBOT_PILAR_DIUBAH' as jenis,
                CAST(NULL AS int) as participant_id,
                COALESCE(l.Nama_Level, CAST(w.ID_Level AS nvarchar(50))) as sasaran,
                w.Alasan_Perubahan as sebab,
                CAST('Bobot pilar level diubah' AS varchar(60)) as rincian,
                COALESCE(w.Updated_By, w.Snapshot_By) as aktor_id,
                CAST(NULL AS nvarchar(150)) as aktor_nama
            ");
    }

    private function pesertaDikecualikan(int $cycleId)
    {
        return DB::table('N_PA_Participant as p')
            /* Pengecualian dari daftar pra-kecuali adalah keputusan ADMIN, tetapi baris peserta
               yang dibentuk darinya tidak membawa pelaku — lini audit dulu mencatatnya "Sistem
               HCIS". Pelakunya diambil dari baris pra-kecuali; pengecualian otomatis (freelance,
               resign) tidak punya baris itu dan tetap tercatat sebagai sistem. */
            ->leftJoin('N_PA_Cycle_Pre_Exclusion as x', function ($j) {
                $j->on('x.PA_Cycle_Id', '=', 'p.PA_Cycle_Id')
                    ->on('x.Kode_Perusahaan', '=', 'p.Kode_Perusahaan')
                    ->on('x.Kode_Karyawan', '=', 'p.Kode_Karyawan')
                    ->where('x.Flag_Aktif', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIKECUALIKAN')
            ->whereNotNull('p.Alasan_Dikecualikan')
            ->selectRaw("
                COALESCE(p.Updated_At, p.Snapshot_At) as waktu,
                p.Id_PA_Participant as urut,
                'PESERTA_DIKECUALIKAN' as jenis,
                p.Id_PA_Participant as participant_id,
                p.Nama_Karyawan_Saat_Cycle as sasaran,
                p.Alasan_Dikecualikan as sebab,
                CAST('Dikecualikan dari siklus' AS varchar(60)) as rincian,
                COALESCE(p.Updated_By, p.Snapshot_By, x.Created_By) as aktor_id,
                CAST(NULL AS nvarchar(150)) as aktor_nama
            ");
    }

    private function siklus(int $cycleId)
    {
        $kunci = DB::table('N_PA_Cycle as k')
            ->where('k.Id_PA_Cycle', $cycleId)
            ->whereNotNull('k.Locked_At')
            ->selectRaw("
                k.Locked_At as waktu, k.Id_PA_Cycle as urut,
                'SIKLUS_DIKUNCI' as jenis,
                CAST(NULL AS int) as participant_id,
                k.Nama_Cycle as sasaran,
                CAST(NULL AS nvarchar(500)) as sebab,
                CAST('Aturan penilaian dibekukan' AS varchar(60)) as rincian,
                k.Locked_By as aktor_id,
                CAST(NULL AS nvarchar(150)) as aktor_nama
            ");

        return $kunci->unionAll(
            DB::table('N_PA_Cycle as k')
                ->where('k.Id_PA_Cycle', $cycleId)
                ->whereNotNull('k.Published_At')
                ->selectRaw("
                    k.Published_At as waktu, k.Id_PA_Cycle as urut,
                    'SIKLUS_DITERBITKAN' as jenis,
                    CAST(NULL AS int) as participant_id,
                    k.Nama_Cycle as sasaran,
                    CAST(NULL AS nvarchar(500)) as sebab,
                    CAST('Penerbitan ditutup' AS varchar(60)) as rincian,
                    k.Published_By as aktor_id,
                    CAST(NULL AS nvarchar(150)) as aktor_nama
                ")
        );
    }

    /**
     * Cacah keputusan manusia dan kejadian sistem dalam SATU kueri.
     *
     * @param  array<string, mixed>  $saringan
     * @return array{keputusan_manusia:int, oleh_sistem:int}
     */
    private function cacahPelaku(int $cycleId, array $saringan): array
    {
        $tanpaPemisah = $saringan;
        unset($tanpaPemisah['hanya_keputusan_manusia']);

        $baris = DB::query()
            ->fromSub($this->gabungan($cycleId, $tanpaPemisah), 'c')
            ->selectRaw('CASE WHEN aktor_id IS NULL THEN 1 ELSE 0 END as sistem, COUNT(*) as jumlah')
            ->groupByRaw('CASE WHEN aktor_id IS NULL THEN 1 ELSE 0 END')
            ->pluck('jumlah', 'sistem');

        return [
            'keputusan_manusia' => (int) ($baris[0] ?? 0),
            'oleh_sistem' => (int) ($baris[1] ?? 0),
        ];
    }

    /**
     * @param  list<mixed>  $ids
     * @return array<int, string>
     */
    private function namaPelaku(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /* NAMA orangnya, bukan `Username`.
           Sampai 2 September 2026 method ini memulangkan `Username` sementara kalibrasi
           membekukan `Nama` — sehingga satu `aktor_id` yang sama muncul sebagai dua orang
           berbeda di SATU layar audit: baris kalibrasi berbunyi "Ridho", baris lainnya
           "NOIR". Audit yang menampilkan dua identitas untuk satu pelaku bukan sekadar
           tidak rapi; ia membuat penelusuran tidak bisa dipercaya.

           Urutannya: nama karyawan lebih dulu (itu yang dibekukan kalibrasi dan yang
           dikenali orang), lalu nama pada akun KPI, dan `Username` hanya sebagai jalan
           terakhir supaya barisnya tidak pernah kehilangan pelaku sama sekali. */
        return DB::table('KPI_Users as u')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'u.Kode_Users')
            ->whereIn('u.Id_Users', array_map('intval', $ids))
            ->get(['u.Id_Users', 'u.Username', 'u.Nama as nama_akun', 'k.Nama as nama_karyawan'])
            ->mapWithKeys(fn ($r) => [
                (int) $r->Id_Users => (string) ($r->nama_karyawan ?: ($r->nama_akun ?: $r->Username)),
            ])
            ->all();
    }
}
