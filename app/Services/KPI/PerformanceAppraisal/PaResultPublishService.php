<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingPenjaga;
use App\Services\KPI\PerformanceAppraisal\Kurva\PaKurvaService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penerbitan hasil — langkah yang membuat seluruh modul ini ada gunanya.
 *
 * Sebelum ini, hasil bisa dihitung tetapi tidak pernah bisa dilihat karyawan:
 * `Published_At` dan `Published_By` tidak pernah ditulis siapa pun. Gerbang `TERBITKAN`
 * sudah ada sejak awal dan tidak dipakai satu baris kode pun.
 *
 * DUA LANGKAH, DAN ITU MENGHINDARI SATU JEBAKAN
 *
 * `terbitkan()` menerbitkan HASIL — karyawan langsung bisa melihatnya. Siklusnya tetap
 * `CLOSED`.
 *
 * `tutupPenerbitan()` memindahkan SIKLUS ke `PUBLISHED`, dan itu langkah terpisah karena
 * `PUBLISHED` adalah pintu satu arah: gerbang tidak mengizinkan operasi apa pun
 * sesudahnya. Menyatukan keduanya berarti satu peserta yang tertahan akan terkunci
 * selamanya begitu tombol terbit ditekan — tak bisa dihitung ulang, tak bisa diterbitkan.
 *
 * Dengan dipisah, tak seorang pun terhalang: hasil yang siap langsung terlihat, dan
 * siklus baru ditutup ketika benar-benar tidak ada yang tersisa.
 *
 * YANG TERTAHAN TIDAK PERNAH DITERBITKAN
 *
 * `TERTAHAN` berarti sistem MENOLAK memberi angka — karena KPI-nya tidak ada, bobotnya
 * menyimpang, atau penilainya kurang dari ambang. Menerbitkannya berarti menunjukkan
 * kepada karyawan sesuatu yang sistemnya sendiri bilang tidak sah.
 */
class PaResultPublishService
{
    public const STATUS_DIHITUNG = 'DIHITUNG';

    public const STATUS_TERBIT = 'TERBIT';

    public const STATUS_TERTAHAN = 'TERTAHAN';

    public const STATUS_BELUM = 'BELUM_DIHITUNG';

    public const SIKLUS_PUBLISHED = 'PUBLISHED';

    /**
     * Hasil ini punya kategori penilai yang GUGUR: ada di aturan, tetapi tidak dihitung karena
     * pengisinya di bawah minimal (`Bobot_Terpakai` NULL). Kategori yang sengaja tidak
     * diikutkan tidak punya baris sama sekali, jadi tidak ikut tersaring — itu keputusan,
     * bukan kebetulan.
     *
     * Satu potongan SQL untuk penahanan terbit DAN keranjang Antrean, supaya keduanya
     * menyebut orang yang sama.
     */
    public static function sqlKategoriGugur(string $aliasHasil = 'res'): string
    {
        return "EXISTS (SELECT 1 FROM N_PA_Result_Rater_Category rcg
                 WHERE rcg.PA_Result_Id = {$aliasHasil}.Id_PA_Result
                   AND rcg.Flag_Aktif = 'Y'
                   AND rcg.Bobot_Terpakai IS NULL)";
    }

    /**
     * Kategori yang gugur per hasil, sebagai kalimat pendek ("Rekan Sejawat 1/2 mengisi").
     *
     * @param  list<int>  $resultIds
     * @return array<int, list<string>>
     */
    public static function kategoriGugur(array $resultIds): array
    {
        $hasil = [];

        foreach (array_chunk($resultIds, 1000) as $potongan) {
            $baris = DB::table('N_PA_Result_Rater_Category as rc')
                ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'rc.PA_Rater_Category_Id')
                ->whereIn('rc.PA_Result_Id', $potongan)
                ->where('rc.Flag_Aktif', 'Y')
                ->whereNull('rc.Bobot_Terpakai')
                ->orderBy('c.Id_PA_Rater_Category')
                ->get(['rc.PA_Result_Id', 'c.Nama_Kategori', 'rc.Jumlah_Rater_Mengisi', 'rc.Jumlah_Rater_Diharapkan']);

            foreach ($baris as $b) {
                $hasil[(int) $b->PA_Result_Id][] = sprintf(
                    '%s %d/%d mengisi',
                    $b->Nama_Kategori,
                    (int) $b->Jumlah_Rater_Mengisi,
                    (int) $b->Jumlah_Rater_Diharapkan,
                );
            }
        }

        return $hasil;
    }

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaNotificationService $pemberitahuan,
        private readonly PaKurvaService $kurva,
        private readonly PaBalancingPenjaga $balancing,
    ) {}

    /**
     * Menerbitkan seluruh hasil yang sudah dihitung pada satu siklus.
     *
     * Aman dijalankan berulang: yang sudah `TERBIT` dilewati, bukan diterbitkan dua kali.
     *
     * Hasil yang dihitung dengan KPI belum lengkap DILEWATI kecuali `$abaikanKpiBelumLengkap`
     * — penahanan yang sama dengan `terbitkanPeserta()`, lihat catatan di sana.
     *
     * Begitu pula hasil yang punya kategori penilai GUGUR (UAT 13 Okt 2026): dilewati dan
     * disebut namanya, kecuali `$abaikanKategoriGugur`. Pada ribuan peserta, kategori yang
     * gugur karena penilainya tak kunjung mengisi tidak akan terlihat kecuali disebut di sini.
     *
     * @return array{
     *     diterbitkan:int, sudah_terbit:int, tertahan:int, belum_dihitung:int,
     *     total_peserta:int, siap_ditutup:bool, dilewati_kpi_belum_lengkap:int,
     *     peserta_kpi_belum_lengkap:list<array{participant_id:int, nama:string, kpi_belum_disetujui:int}>
     * }
     */
    public function terbitkan(
        int $cycleId,
        ?int $olehUserId = null,
        ?array $participantIds = null,
        bool $abaikanKpiBelumLengkap = false,
        bool $abaikanKategoriGugur = false,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::TERBITKAN, $cycleId);
        $this->kurva->pastikanTerkunci($cycleId);
        // Publish menunggu Balancing tuntas: tanpa sesi terbuka, semua sesi FINAL sudah dihitung ulang.
        $this->balancing->pastikanTuntas($cycleId);

        $sebelum = $this->ringkasan($cycleId);

        if ($sebelum['dihitung'] === 0) {
            throw new RuntimeException(
                'Tidak ada satu pun hasil berstatus DIHITUNG untuk diterbitkan. '
                ."Tertahan: {$sebelum['tertahan']}, belum dihitung: {$sebelum['belum_dihitung']}. "
                .'Hitung dulu hasilnya, atau perbaiki sebab tertahannya.'
            );
        }

        $jam = FormatTanggalHelper::getCurrentTime();

        /* Id dikumpulkan SEBELUM update, karena sesudahnya baris-baris itu tak bisa lagi
           dibedakan dari hasil yang memang sudah terbit sejak penerbitan sebelumnya —
           dan memberi tahu orang untuk kedua kalinya lebih buruk daripada tidak sama
           sekali: ia mengajari orang mengabaikan pemberitahuan ini. */
        /* Peserta siklus dan pilihan admin diiris di PHP, bukan dengan dua `whereIn`
           bertumpuk: keduanya bisa ratusan id dan jumlahnya menembus batas 2.100 parameter
           SQL Server begitu "pilih semua" lintas halaman dipakai. */
        $peserta = $this->pesertaSiklus($cycleId)->map(fn ($id) => (int) $id)->all();

        if (! empty($participantIds)) {
            $peserta = array_values(array_intersect($peserta, array_map('intval', $participantIds)));
        }

        $baruTerbit = [];
        $kpiBelumLengkap = [];
        $kategoriGugur = [];

        foreach (array_chunk($peserta, 1000) as $potongan) {
            $baris = DB::table('N_PA_Result as r')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                ->whereIn('r.PA_Participant_Id', $potongan)
                ->where('r.Status_Hasil', self::STATUS_DIHITUNG)
                ->where('r.Flag_Aktif', 'Y')
                ->get(['r.Id_PA_Result', 'r.PA_Participant_Id', 'r.Jumlah_KPI_Belum_Disetujui', 'p.Nama_Karyawan_Saat_Cycle']);

            $gugur = $abaikanKategoriGugur ? [] : self::kategoriGugur($baris->pluck('Id_PA_Result')->map(fn ($id) => (int) $id)->all());

            foreach ($baris as $b) {
                /* PENAHANAN YANG SAMA DENGAN JALUR PER ORANG. Sampai 8 Oktober 2026 hanya
                   `terbitkanPeserta()` yang menahan hasil ber-KPI belum lengkap, sehingga tombol
                   massal menerbitkan — dan mengunci selamanya — angka yang ditolak jalur per
                   orang. Dua pintu ke tempat yang sama wajib berpenjaga sama. Yang ditahan
                   tidak diam-diam hilang: nama dan cacahnya dikembalikan supaya admin tahu
                   siapa yang tertinggal dan kenapa. */
                $belum = (int) ($b->Jumlah_KPI_Belum_Disetujui ?? 0);

                if ($belum > 0 && ! $abaikanKpiBelumLengkap) {
                    $kpiBelumLengkap[] = [
                        'participant_id' => (int) $b->PA_Participant_Id,
                        'nama' => (string) $b->Nama_Karyawan_Saat_Cycle,
                        'kpi_belum_disetujui' => $belum,
                    ];

                    continue;
                }

                if (isset($gugur[(int) $b->Id_PA_Result])) {
                    $kategoriGugur[] = [
                        'participant_id' => (int) $b->PA_Participant_Id,
                        'nama' => (string) $b->Nama_Karyawan_Saat_Cycle,
                        'kategori' => $gugur[(int) $b->Id_PA_Result],
                    ];

                    continue;
                }

                $baruTerbit[] = (int) $b->Id_PA_Result;
            }
        }

        if (empty($baruTerbit)) {
            throw new RuntimeException(match (true) {
                $kpiBelumLengkap !== [] => sprintf(
                    'Tidak ada hasil yang diterbitkan: %d hasil DIHITUNG dihitung ketika sebagian '
                    .'pengajuan KPI-nya belum disetujui (%s). Hasil terbit tidak bisa dihitung ulang, '
                    .'jadi selesaikan dulu persetujuan KPI-nya lalu hitung ulang — atau terbitkan '
                    .'dengan sadar bila angkanya memang sudah dianggap cukup.',
                    count($kpiBelumLengkap),
                    $this->daftarNama($kpiBelumLengkap),
                ),
                $kategoriGugur !== [] => sprintf(
                    'Tidak ada hasil yang diterbitkan: %d hasil punya kategori penilai yang tidak '
                    .'dihitung karena pengisinya di bawah minimal (%s). Tagih penilainya lalu hitung '
                    .'ulang, atau terbitkan dengan sadar bila angkanya memang sudah dianggap cukup.',
                    count($kategoriGugur),
                    $this->daftarNama($kategoriGugur),
                ),
                ! empty($participantIds) => 'Tidak ada peserta terpilih yang berstatus DIHITUNG dan siap diterbitkan.',
                default => 'Tidak ada satu pun hasil berstatus DIHITUNG untuk diterbitkan.',
            });
        }

        /*
         | HANYA YANG MASIH DIHITUNG, DAN HANYA YANG DITERBITKAN PANGGILAN INI YANG DIKABARI
         | (14 Okt 2026). Dulu UPDATE-nya tanpa syarat status: klik ganda — atau dua admin —
         | menimpa Published_At dan mengirim pemberitahuan "hasil terbit" dua kali ke setiap
         | orang. Per potongan, baris yang masih DIHITUNG dikunci lebih dulu; panggilan kedua
         | menunggu, lalu mendapati semuanya sudah TERBIT dan tidak mengabari siapa pun.
         */
        $terbitOlehIni = [];

        foreach (array_chunk($baruTerbit, 1000) as $potongan) {
            $terbitOlehIni = array_merge($terbitOlehIni, DB::transaction(function () use ($potongan, $jam, $olehUserId) {
                $milik = DB::table('N_PA_Result')
                    ->whereIn('Id_PA_Result', $potongan)
                    ->where('Status_Hasil', self::STATUS_DIHITUNG)
                    ->lockForUpdate()
                    ->pluck('Id_PA_Result')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if ($milik !== []) {
                    DB::table('N_PA_Result')
                        ->whereIn('Id_PA_Result', $milik)
                        ->update([
                            'Status_Hasil' => self::STATUS_TERBIT,
                            'Published_At' => $jam,
                            'Published_By' => $olehUserId,
                            'Updated_At' => $jam,
                            'Updated_By' => $olehUserId,
                        ]);
                }

                return $milik;
            }));
        }

        $diterbitkan = count($terbitOlehIni);
        $sesudah = $this->ringkasan($cycleId);

        // Di luar transaksi mana pun. Lihat catatan di PaNotificationService.
        foreach (array_chunk($terbitOlehIni, 1000) as $potongan) {
            $this->pemberitahuan->hasilTerbit($potongan);
        }

        return [
            'diterbitkan' => $diterbitkan,
            'sudah_terbit' => $sebelum['terbit'],
            'tertahan' => $sesudah['tertahan'],
            'belum_dihitung' => $sesudah['belum_dihitung'],
            'total_peserta' => $sesudah['total'],
            'siap_ditutup' => $sesudah['tertahan'] === 0 && $sesudah['belum_dihitung'] === 0
                && $sesudah['dihitung'] === 0,
            'dilewati_kpi_belum_lengkap' => count($kpiBelumLengkap),
            'peserta_kpi_belum_lengkap' => $kpiBelumLengkap,
            'dilewati_kategori_gugur' => count($kategoriGugur),
            'peserta_kategori_gugur' => $kategoriGugur,
        ];
    }

    /**
     * Nama untuk kalimat penolakan — dipangkas, karena "pilih semua" bisa membawa ratusan orang
     * dan pesan galat sepanjang itu tidak terbaca siapa pun.
     *
     * @param  list<array{nama:string}>  $peserta
     */
    private function daftarNama(array $peserta, int $batas = 10): string
    {
        $nama = array_column(array_slice($peserta, 0, $batas), 'nama');
        $sisa = count($peserta) - count($nama);

        return implode(', ', $nama).($sisa > 0 ? " dan {$sisa} lainnya" : '');
    }

    /**
     * Menerbitkan hasil satu orang.
     *
     * Dipakai untuk yang tertinggal: peserta yang tertahan, sebabnya diperbaiki, lalu
     * dihitung ulang setelah sisanya sudah terbit.
     *
     * @return array{participant_id:int, result_id:int, status:string}
     */
    public function terbitkanPeserta(
        int $participantId,
        ?int $olehUserId = null,
        bool $abaikanKpiBelumLengkap = false,
        bool $abaikanKategoriGugur = false,
    ): array {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::TERBITKAN, $participantId);
        $cycleId = (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id');
        $this->kurva->pastikanTerkunci($cycleId);
        // Publish menunggu Balancing tuntas: tanpa sesi terbuka, semua sesi FINAL sudah dihitung ulang.
        $this->balancing->pastikanTuntas($cycleId);

        $hasil = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Result', 'Status_Hasil', 'Alasan_Tertahan',
                'Jumlah_KPI_Belum_Disetujui', 'Nilai_KPI']);

        if ($hasil === null) {
            throw new RuntimeException("Peserta {$participantId} tidak punya baris hasil aktif.");
        }

        if ($hasil->Status_Hasil === self::STATUS_TERBIT) {
            throw new RuntimeException("Hasil peserta {$participantId} sudah terbit.");
        }

        if ($hasil->Status_Hasil === self::STATUS_TERTAHAN) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} TERTAHAN dan tidak boleh diterbitkan: "
                .($hasil->Alasan_Tertahan ?? 'tanpa alasan tercatat')
                .' Perbaiki sebabnya lalu hitung ulang; menerbitkannya berarti menunjukkan '
                .'kepada karyawan sesuatu yang sistemnya sendiri bilang tidak sah.'
            );
        }

        if ($hasil->Status_Hasil !== self::STATUS_DIHITUNG) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} berstatus {$hasil->Status_Hasil}; "
                .'hanya yang DIHITUNG yang bisa diterbitkan.'
            );
        }

        /*
         | KPI YANG BARU SEBAGIAN DISETUJUI — DITAHAN, TETAPI BISA DITIMPA.
         |
         | Perhitungan KPI sengaja TIDAK menahan hasil ketika sebagian periodenya belum
         | disetujui: ia menghitung dari yang sudah ada dan mencatat sisanya di
         | `Jumlah_KPI_Belum_Disetujui`. Itu keputusan yang benar untuk PERHITUNGAN — angkanya
         | tetap berguna, dan menahannya akan membuat seluruh siklus menunggu satu persetujuan
         | yang tertinggal.
         |
         | Untuk PENERBITAN, taruhannya lain sama sekali. Hasil yang sudah terbit tidak bisa
         | dihitung ulang — `hitung()` menolaknya, dan benar menolaknya, karena karyawan sudah
         | melihat angkanya. Jadi menerbitkan sekarang berarti mengunci selamanya angka yang
         | dihitung dari data yang belum utuh, dan persetujuan KPI yang menyusul besok tidak
         | akan pernah mengubahnya.
         |
         | Ditahan, BUKAN dilarang. Sebagian periode yang tak kunjung disetujui adalah keadaan
         | nyata, dan admin berhak memutuskan bahwa angkanya sudah cukup. Yang tidak boleh
         | adalah memutuskannya tanpa tahu.
         */
        $belum = (int) ($hasil->Jumlah_KPI_Belum_Disetujui ?? 0);

        if ($belum > 0 && ! $abaikanKpiBelumLengkap) {
            throw new RuntimeException(sprintf(
                'Nilai KPI peserta %d dihitung ketika %d pengajuan KPI-nya BELUM disetujui, '
                .'sehingga angkanya (%s) berasal dari periode yang belum utuh. Hasil yang '
                .'sudah terbit tidak bisa dihitung ulang, jadi persetujuan yang menyusul tidak '
                .'akan memperbaikinya. Selesaikan dulu persetujuan KPI-nya lalu hitung ulang — '
                .'atau terbitkan dengan sadar bila angkanya memang sudah dianggap cukup.',
                $participantId,
                $belum,
                $hasil->Nilai_KPI ?? '-',
            ));
        }

        /* KATEGORI GUGUR — ditahan dengan cara yang sama: boleh ditimpa, tidak boleh tanpa tahu.
           Sesudah terbit, penilai yang mengisi belakangan tidak lagi mengubah angkanya. */
        $gugur = self::kategoriGugur([(int) $hasil->Id_PA_Result])[(int) $hasil->Id_PA_Result] ?? [];

        if ($gugur !== [] && ! $abaikanKategoriGugur) {
            throw new PaKategoriGugurException(sprintf(
                'Kategori penilai berikut tidak dihitung pada hasil peserta %d karena pengisinya '
                .'di bawah minimal: %s. Bobotnya sudah dibagikan ke kategori lain. Tagih penilainya '
                .'lalu hitung ulang — atau terbitkan dengan sadar bila angkanya memang sudah dianggap cukup.',
                $participantId,
                implode(', ', $gugur),
            ));
        }

        $jam = FormatTanggalHelper::getCurrentTime();

        /* Bersyarat DIHITUNG: klik ganda tidak menimpa Published_At dan tidak mengabari dua kali. */
        $terbit = DB::table('N_PA_Result')
            ->where('Id_PA_Result', $hasil->Id_PA_Result)
            ->where('Status_Hasil', self::STATUS_DIHITUNG)
            ->update([
                'Status_Hasil' => self::STATUS_TERBIT,
                'Published_At' => $jam,
                'Published_By' => $olehUserId,
                'Updated_At' => $jam,
                'Updated_By' => $olehUserId,
            ]);

        if ($terbit === 0) {
            throw new RuntimeException("Hasil peserta {$participantId} sudah diterbitkan oleh proses lain.");
        }

        $this->pemberitahuan->hasilTerbit([(int) $hasil->Id_PA_Result]);

        return [
            'participant_id' => $participantId,
            'result_id' => (int) $hasil->Id_PA_Result,
            'status' => self::STATUS_TERBIT,

            /* Dilaporkan supaya jejaknya ada di jawaban, bukan hanya di kepala orang yang
               menekan tombolnya: terbit di atas KPI yang belum utuh adalah keputusan, dan
               keputusan pantas terlihat. */
            'kpi_belum_disetujui' => $belum,
            'diterbitkan_dengan_kpi_belum_lengkap' => $belum > 0,
        ];
    }

    /**
     * Merilis hasil satu siklus KE LEADER — bukan ke karyawan yang dinilai.
     *
     * DUA PINTU, SATU TIDAK MENGGANTIKAN YANG LAIN
     *
     * `Status_Hasil = 'TERBIT'` berarti satu hal saja: KARYAWAN boleh melihat hasilnya.
     * `Published_Leader_At` berarti LEADER boleh melihat hasil anggota timnya di halaman PA
     * Tim. Sampai 21 September 2026 keduanya dijaga penanda yang sama, sehingga tidak ada cara
     * memberi leader kesempatan meninjau lebih dulu tanpa sekaligus memberitahu orangnya.
     *
     * Tidak ada urutan yang dipaksakan (keputusan pemilik proses): admin boleh menerbitkan ke
     * karyawan tanpa pernah merilis ke leader. Hasil yang sudah `TERBIT` tetap terlihat leader
     * lewat aturan "penanda leader terisi ATAU sudah TERBIT", jadi siklus lama tidak berubah.
     *
     * TIDAK ADA PEMBERITAHUAN KE KARYAWAN di sini. Yang dibuka adalah layar leader; mengirim
     * notifikasi "hasil Anda terbit" pada langkah ini akan memberitahu orang yang justru belum
     * boleh melihatnya.
     *
     * Aman dijalankan berulang: yang sudah dirilis dilewati.
     *
     * @return array{dirilis:int, sudah_dirilis:int, belum_layak:int, total_peserta:int}
     */
    /**
     * Syarat rilis/terbit dengan pemeriksaan yang SAMA dengan aksinya, untuk layar. Tombol
     * dimatikan dan sebabnya disebut, alih-alih ditolak sesudah admin mengonfirmasi.
     *
     * @return array{boleh_rilis:bool, boleh_terbit:bool, alasan:?string}
     */
    public function syaratPublish(int $cycleId): array
    {
        $alasan = null;

        try {
            $this->kurva->pastikanTerkunci($cycleId);
            $this->balancing->pastikanTuntas($cycleId);
        } catch (RuntimeException $e) {
            $alasan = $e->getMessage();
        }

        $rilis = $alasan === null && $this->gate->boleh(PaCycleGate::RILIS_LEADER, $cycleId);
        $terbit = $alasan === null && $this->gate->boleh(PaCycleGate::TERBITKAN, $cycleId);

        if ($alasan === null && ! $terbit) {
            $alasan = 'Status siklus saat ini tidak mengizinkan penerbitan.';
        }

        return ['boleh_rilis' => $rilis, 'boleh_terbit' => $terbit, 'alasan' => $alasan];
    }

    public function rilisKeLeader(
        int $cycleId,
        ?int $olehUserId = null,
        ?array $participantIds = null,
        bool $abaikanKpiBelumLengkap = false,
        bool $abaikanKategoriGugur = false,
    ): array {
        $this->gate->pastikanBoleh(PaCycleGate::RILIS_LEADER, $cycleId);

        /* Yang dibaca leader adalah GRADE, dan pada siklus bermetode kurva grade belum resmi
           sebelum kurvanya dikunci. Syaratnya sama dengan penerbitan. */
        $this->kurva->pastikanTerkunci($cycleId);
        // Publish menunggu Balancing tuntas: tanpa sesi terbuka, semua sesi FINAL sudah dihitung ulang.
        $this->balancing->pastikanTuntas($cycleId);

        /* `pesertaSiklus()` mengembalikan Collection; `array_intersect()` hanya menerima array.
           Sampai 3 Oktober 2026 rilis peserta TERPILIH selalu jatuh dengan TypeError di sini,
           sementara rilis seluruh siklus lolos karena tidak pernah melewati baris ini. */
        $peserta = $this->pesertaSiklus($cycleId)->map(fn ($id) => (int) $id)->all();
        if (! empty($participantIds)) {
            $peserta = array_values(array_intersect($peserta, array_map('intval', $participantIds)));
            if (empty($peserta)) {
                throw new RuntimeException('Tidak ada peserta valid yang dipilih untuk dirilis ke leader.');
            }
        }

        $jam = FormatTanggalHelper::getCurrentTime();
        $dirilis = 0;

        /* PENAHANAN YANG SAMA DENGAN PENERBITAN (14 Okt 2026). Rilis ke leader juga MEMBEKUKAN
           hasil — `hitung()` menolak hasil yang sudah dirilis — jadi angka dari KPI yang belum
           utuh atau kategori penilai yang gugur yang dirilis massal ikut terkunci dan langsung
           dibaca leader. Yang TERBIT sudah lolos penahanan saat diterbitkan. */
        $kpiBelumLengkap = [];
        $kategoriGugur = [];
        $tahan = [];

        foreach (array_chunk($peserta, 1000) as $potongan) {
            $calon = DB::table('N_PA_Result as r')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
                ->whereIn('r.PA_Participant_Id', $potongan)
                ->where('r.Flag_Aktif', 'Y')
                ->where('r.Status_Hasil', self::STATUS_DIHITUNG)
                ->whereNull('r.Published_Leader_At')
                ->get(['r.Id_PA_Result', 'r.PA_Participant_Id', 'r.Jumlah_KPI_Belum_Disetujui', 'p.Nama_Karyawan_Saat_Cycle']);
            $gugur = $abaikanKategoriGugur ? [] : self::kategoriGugur($calon->pluck('Id_PA_Result')->map(fn ($id) => (int) $id)->all());

            foreach ($calon as $c) {
                if ((int) ($c->Jumlah_KPI_Belum_Disetujui ?? 0) > 0 && ! $abaikanKpiBelumLengkap) {
                    $kpiBelumLengkap[] = ['participant_id' => (int) $c->PA_Participant_Id, 'nama' => (string) $c->Nama_Karyawan_Saat_Cycle,
                        'kpi_belum_disetujui' => (int) $c->Jumlah_KPI_Belum_Disetujui];
                    $tahan[(int) $c->PA_Participant_Id] = true;
                } elseif (isset($gugur[(int) $c->Id_PA_Result])) {
                    $kategoriGugur[] = ['participant_id' => (int) $c->PA_Participant_Id, 'nama' => (string) $c->Nama_Karyawan_Saat_Cycle,
                        'kategori' => $gugur[(int) $c->Id_PA_Result]];
                    $tahan[(int) $c->PA_Participant_Id] = true;
                }
            }
        }

        $peserta = array_values(array_filter($peserta, fn ($id) => ! isset($tahan[$id])));

        // Dicicil: "pilih semua" lintas halaman bisa membawa ribuan id, dan SQL Server
        // menolak lebih dari 2.100 parameter dalam satu perintah.
        foreach (array_chunk($peserta, 1000) as $potongan) {
            $dirilis += DB::table('N_PA_Result')
                ->whereIn('PA_Participant_Id', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->whereIn('Status_Hasil', [self::STATUS_DIHITUNG, self::STATUS_TERBIT])
                ->whereNull('Published_Leader_At')
                ->update([
                    'Published_Leader_At' => $jam,
                    'Published_Leader_By' => $olehUserId,

                    /* Jejak pencabutan sebelumnya dikosongkan: ia menjelaskan keadaan yang sudah
                   tidak berlaku, dan membiarkannya membuat layar menampilkan sebab pencabutan
                   pada hasil yang justru sedang terbuka. */
                    'Unpublished_Leader_At' => null,
                    'Unpublished_Leader_By' => null,
                    'Alasan_Cabut_Leader' => null,
                    'Updated_At' => $jam,
                    'Updated_By' => $olehUserId,
                ]);
        }

        $ringkas = $this->ringkasan($cycleId);

        return [
            'dirilis' => $dirilis,
            'sudah_dirilis' => $this->cacahDirilisKeLeader($cycleId) - $dirilis,
            'belum_layak' => $ringkas['tertahan'] + $ringkas['belum_dihitung'],
            'total_peserta' => $ringkas['total'],
            'dilewati_kpi_belum_lengkap' => count($kpiBelumLengkap),
            'peserta_kpi_belum_lengkap' => $kpiBelumLengkap,
            'dilewati_kategori_gugur' => count($kategoriGugur),
            'peserta_kategori_gugur' => $kategoriGugur,
        ];
    }

    /**
     * Merilis hasil satu orang ke leadernya.
     *
     * @return array{participant_id:int, result_id:int, published_leader_at:string}
     */
    public function rilisKeLeaderPeserta(
        int $participantId,
        ?int $olehUserId = null,
        bool $abaikanKpiBelumLengkap = false,
        bool $abaikanKategoriGugur = false,
    ): array {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::RILIS_LEADER, $participantId);
        $cycleId = (int) DB::table('N_PA_Participant')->where('Id_PA_Participant', $participantId)->value('PA_Cycle_Id');
        $this->kurva->pastikanTerkunci($cycleId);
        // Publish menunggu Balancing tuntas: tanpa sesi terbuka, semua sesi FINAL sudah dihitung ulang.
        $this->balancing->pastikanTuntas($cycleId);

        $hasil = $this->hasilAktif($participantId);

        if ($hasil->Published_Leader_At !== null) {
            throw new RuntimeException("Hasil peserta {$participantId} sudah dirilis ke leader.");
        }

        if ($hasil->Status_Hasil === self::STATUS_TERTAHAN) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} TERTAHAN dan tidak boleh dirilis ke leader: "
                .($hasil->Alasan_Tertahan ?? 'tanpa alasan tercatat')
                .' Perbaiki sebabnya lalu hitung ulang.'
            );
        }

        if (! in_array($hasil->Status_Hasil, [self::STATUS_DIHITUNG, self::STATUS_TERBIT], true)) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} berstatus {$hasil->Status_Hasil}; "
                .'hanya yang sudah dihitung yang bisa dirilis ke leader.'
            );
        }

        // Penahanan yang sama dengan terbitkanPeserta(): rilis juga membekukan hasilnya.
        if ($hasil->Status_Hasil === self::STATUS_DIHITUNG) {
            $belum = (int) DB::table('N_PA_Result')->where('Id_PA_Result', $hasil->Id_PA_Result)->value('Jumlah_KPI_Belum_Disetujui');

            if ($belum > 0 && ! $abaikanKpiBelumLengkap) {
                throw new RuntimeException(sprintf(
                    'Nilai KPI peserta %d dihitung ketika %d pengajuan KPI-nya BELUM disetujui. Hasil yang '
                    .'dirilis ke leader tidak dihitung ulang, jadi persetujuan yang menyusul tidak akan '
                    .'memperbaikinya. Selesaikan persetujuannya lalu hitung ulang — atau rilis dengan sadar.',
                    $participantId, $belum,
                ));
            }

            $gugur = self::kategoriGugur([(int) $hasil->Id_PA_Result])[(int) $hasil->Id_PA_Result] ?? [];

            if ($gugur !== [] && ! $abaikanKategoriGugur) {
                throw new PaKategoriGugurException(sprintf(
                    'Kategori penilai berikut tidak dihitung pada hasil peserta %d karena pengisinya di bawah '
                    .'minimal: %s. Hasil yang dirilis ke leader tidak dihitung ulang. Tagih penilainya lalu '
                    .'hitung ulang — atau rilis dengan sadar bila angkanya memang sudah dianggap cukup.',
                    $participantId, implode(', ', $gugur),
                ));
            }
        }

        $jam = FormatTanggalHelper::getCurrentTime();

        DB::table('N_PA_Result')
            ->where('Id_PA_Result', $hasil->Id_PA_Result)
            ->update([
                'Published_Leader_At' => $jam,
                'Published_Leader_By' => $olehUserId,
                'Unpublished_Leader_At' => null,
                'Unpublished_Leader_By' => null,
                'Alasan_Cabut_Leader' => null,
                'Updated_At' => $jam,
                'Updated_By' => $olehUserId,
            ]);

        return [
            'participant_id' => $participantId,
            'result_id' => (int) $hasil->Id_PA_Result,
            'published_leader_at' => (string) $jam,
        ];
    }

    /**
     * Menarik kembali akses leader atas satu hasil — WAJIB BERSEBAB.
     *
     * Mencabut akses yang sudah diberikan adalah tindakan yang kelak ditanyakan orang, dan
     * jawabannya tidak boleh berupa tebakan. Karena itu alasannya disimpan bersama pelakunya.
     *
     * DITOLAK bila hasilnya sudah `TERBIT`: karyawannya sendiri sudah melihat angka itu,
     * sehingga menutupnya dari leader tidak menyembunyikan apa pun — ia hanya membuat leader
     * buta terhadap hal yang sudah diketahui bawahannya.
     *
     * @return array{participant_id:int, result_id:int, alasan:string}
     */
    public function cabutRilisLeader(int $participantId, ?string $alasan, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::RILIS_LEADER, $participantId);
        $alasan = trim((string) $alasan);

        if (mb_strlen($alasan) < 5 || mb_strlen($alasan) > 500) {
            throw new RuntimeException('Alasan mencabut rilis ke leader wajib diisi, 5 sampai 500 karakter.');
        }

        $hasil = $this->hasilAktif($participantId);

        /* URUTANNYA SENGAJA: "sudah terbit" diperiksa LEBIH DULU. Hasil yang sudah terbit dan
           tidak pernah dirilis ke leader memenuhi kedua syarat penolakan sekaligus, dan dari
           dua kalimat yang sama-sama benar, yang berguna adalah yang menjelaskan kenapa
           mencabut tidak akan menolong: leader tetap melihatnya lewat pintu yang lain. */
        if ($hasil->Status_Hasil === self::STATUS_TERBIT) {
            throw new RuntimeException(
                "Hasil peserta {$participantId} sudah terbit ke karyawannya, sehingga leader tetap "
                .'melihatnya. Mencabut rilis leader di sini tidak menyembunyikan apa pun.'
            );
        }

        if ($hasil->Published_Leader_At === null) {
            throw new RuntimeException("Hasil peserta {$participantId} belum pernah dirilis ke leader.");
        }

        $jam = FormatTanggalHelper::getCurrentTime();

        DB::table('N_PA_Result')
            ->where('Id_PA_Result', $hasil->Id_PA_Result)
            ->update([
                'Published_Leader_At' => null,
                'Published_Leader_By' => null,
                'Unpublished_Leader_At' => $jam,
                'Unpublished_Leader_By' => $olehUserId,
                'Alasan_Cabut_Leader' => $alasan,
                'Updated_At' => $jam,
                'Updated_By' => $olehUserId,
            ]);

        return [
            'participant_id' => $participantId,
            'result_id' => (int) $hasil->Id_PA_Result,
            'alasan' => $alasan,
        ];
    }

    /** Berapa hasil siklus ini yang penanda leadernya terisi. */
    public function cacahDirilisKeLeader(int $cycleId): int
    {
        return (int) DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')
            ->whereNotNull('r.Published_Leader_At')
            ->count();
    }

    private function hasilAktif(int $participantId): object
    {
        $hasil = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_PA_Result', 'Status_Hasil', 'Alasan_Tertahan', 'Published_Leader_At']);

        if ($hasil === null) {
            throw new RuntimeException("Peserta {$participantId} tidak punya baris hasil aktif.");
        }

        return $hasil;
    }

    /**
     * Memindahkan SIKLUS ke `PUBLISHED` — pintu satu arah.
     *
     * Ditolak selama masih ada yang belum selesai. Itu bukan kekakuan: sesudah
     * `PUBLISHED`, gerbang tidak mengizinkan operasi apa pun, sehingga peserta yang
     * tertinggal tidak akan pernah bisa dihitung ulang maupun diterbitkan.
     *
     * Menolaknya TIDAK menghalangi siapa pun: hasil yang siap sudah terbit dan sudah
     * bisa dilihat karyawan. Yang tertunda hanya penutupan administratifnya.
     *
     * @return array{cycle_id:int, status:string, published_at:string}
     */
    public function tutupPenerbitan(int $cycleId, ?int $olehUserId = null): array
    {
        $this->gate->pastikanBoleh(PaCycleGate::TERBITKAN, $cycleId);
        $this->kurva->pastikanTerkunci($cycleId);
        // Publish menunggu Balancing tuntas: tanpa sesi terbuka, semua sesi FINAL sudah dihitung ulang.
        $this->balancing->pastikanTuntas($cycleId);

        $r = $this->ringkasan($cycleId);

        if ($r['terbit'] === 0) {
            throw new RuntimeException(
                'Belum ada satu pun hasil yang terbit; siklus tanpa hasil terbit bukan '
                .'siklus yang selesai.'
            );
        }

        $tersisa = [];

        if ($r['dihitung'] > 0) {
            $tersisa[] = "{$r['dihitung']} sudah dihitung tetapi belum diterbitkan";
        }

        if ($r['tertahan'] > 0) {
            $tersisa[] = "{$r['tertahan']} tertahan";
        }

        if ($r['belum_dihitung'] > 0) {
            $tersisa[] = "{$r['belum_dihitung']} belum dihitung";
        }

        if ($tersisa !== []) {
            throw new RuntimeException(
                'Siklus belum bisa ditutup: '.implode(', ', $tersisa).'. '
                .'Sesudah PUBLISHED tidak ada operasi apa pun yang diizinkan lagi, sehingga '
                .'mereka tidak akan pernah bisa dihitung ulang maupun diterbitkan. '
                .($r['tertahan'] + $r['belum_dihitung'] > 0
                    ? 'Yang tertahan permanen (mis. tanpa akun KPI) bisa dikecualikan beralasan dari '
                        .'keranjang Antrean Tindak Lanjut — tombol "Kecualikan" pada barisnya. '
                    : '')
                .'Hasil yang sudah terbit TETAP terlihat karyawan selama ini — yang tertunda '
                .'hanya penutupan administratifnya.'
            );
        }

        $jam = FormatTanggalHelper::getCurrentTime();

        DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->update([
                'Status_Cycle' => self::SIKLUS_PUBLISHED,
                'Published_At' => $jam,
                'Published_By' => $olehUserId,
                'Updated_At' => $jam,
                'Updated_By' => $olehUserId,
            ]);

        $this->gate->lupakanMemo();

        return [
            'cycle_id' => $cycleId,
            'status' => self::SIKLUS_PUBLISHED,
            'published_at' => (string) $jam,
        ];
    }

    /**
     * Cacah hasil per status pada satu siklus.
     *
     * @return array{terbit:int, dihitung:int, tertahan:int, belum_dihitung:int, total:int}
     */
    public function ringkasan(int $cycleId): array
    {
        $baris = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('r.Flag_Aktif', 'Y')
            ->groupBy('r.Status_Hasil')
            ->select(['r.Status_Hasil', DB::raw('COUNT(*) AS jumlah')])
            ->get();

        $hasil = ['terbit' => 0, 'dihitung' => 0, 'tertahan' => 0, 'belum_dihitung' => 0, 'total' => 0];

        $peta = [
            self::STATUS_TERBIT => 'terbit',
            self::STATUS_DIHITUNG => 'dihitung',
            self::STATUS_TERTAHAN => 'tertahan',
            self::STATUS_BELUM => 'belum_dihitung',
        ];

        foreach ($baris as $b) {
            $hasil['total'] += (int) $b->jumlah;

            if (isset($peta[$b->Status_Hasil])) {
                $hasil[$peta[$b->Status_Hasil]] = (int) $b->jumlah;
            }
        }

        return $hasil;
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function pesertaSiklus(int $cycleId)
    {
        return DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Status_Peserta', 'DIIKUTKAN')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Participant');
    }
}
