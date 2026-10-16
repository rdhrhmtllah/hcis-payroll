<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Services\SYSTEM\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pemberitahuan modul Performance Appraisal — in-app saja (ketentuan B9).
 *
 * KENAPA INI ADA
 *
 * Sampai 1 September 2026 modul PA tidak mengirim satu pun pemberitahuan. Akibatnya
 * seluruh rancangan di atasnya berdiri di atas asumsi yang tidak pernah dipenuhi:
 * penilai dianggap tahu ia punya tugas, padahal tak ada yang pernah memberitahunya. Layar
 * "terlambat" yang menghitung hari dengan cermat kehilangan artinya kalau orangnya tidak
 * pernah diberi tahu sejak awal.
 *
 * DUA JEBAKAN YANG SUDAH PERNAH MEMAKAN KORBAN DI MODUL LAIN
 *
 * 1. `Module` HARUS `'KPI'`. `NotificationService::normalizeModule()` memetakan apa pun
 *    yang tidak dikenalnya menjadi `'HCIS'` TANPA memberi tahu — jadi `'PA'` akan
 *    tersimpan, tidak pernah muncul di tempat yang diharapkan, dan uji tabel tetap hijau.
 *    `'KPI'` juga yang benar secara isi: PA adalah submodul KPI.
 *
 * 2. `source` HARUS `MAIN_NOTIF`. Topbar dan modal notifikasi menyaringnya; nilai lain
 *    tersimpan rapi di tabel dan TIDAK PERNAH terlihat siapa pun.
 *
 * DIKIRIM SESUDAH COMMIT, TIDAK PERNAH DI DALAM TRANSAKSI
 *
 * Menulis ke `N_HRIS_Notification` di dalam transaksi yang panjang menahan kunci pada
 * tabel yang di-polling topbar setiap menit oleh setiap orang yang sedang membuka HCIS.
 * Modul lain sudah pernah membayar mahal untuk itu — kolam koneksi habis dan seluruh
 * sistem menjawab "Login timeout expired". Karena itu pemanggil WAJIB memakai
 * `DB::afterCommit()` atau memanggil service ini di luar transaksinya.
 *
 * IDEMPOTEN LEWAT `Notification_Key`
 *
 * Semua pengiriman memakai `upsertByKey`, sehingga menjalankan ulang perintah pengingat
 * tidak menumpuk pemberitahuan yang sama.
 *
 * Dua pengingat memakai satuan waktu yang BERBEDA di kuncinya, dan itu disengaja:
 * `PA_TENGGAT_DEKAT` memakai TANGGAL karena jendelanya cuma tiga hari sehingga ia
 * terbatas sendiri, dan urgensinya memang berubah tiap hari. `PA_TERLAMBAT` memakai
 * PEKAN ISO karena keterlambatan tidak punya batas atas — kunci harian membuat satu
 * penugasan yang tertinggal sebulan melahirkan tiga puluh baris.
 *
 * Pekan ISO ditulis `date('o-\WW')`, memakai `o` dan BUKAN `Y`. Dengan `Y`, 31 Desember
 * 2024 dan 2 Januari 2024 sama-sama menghasilkan `2024-W01`, sehingga pengingat akhir
 * Desember dianggap duplikat pengingat awal Januari dan tidak pernah terkirim selama
 * sepekan — setiap tahun, tanpa satu pun galat.
 */
class PaNotificationService
{
    /** Wajib. Nilai lain tersimpan tetapi tak pernah muncul di topbar. */
    public const SUMBER = 'MAIN_NOTIF';

    /** Wajib 'KPI'. 'PA' akan diam-diam berubah menjadi 'HCIS'. */
    public const MODUL = 'KPI';

    public const TYPE_DITUGASKAN = 'PA_DITUGASKAN';

    public const TYPE_TENGGAT_DEKAT = 'PA_TENGGAT_DEKAT';

    public const TYPE_TERLAMBAT = 'PA_TERLAMBAT';

    public const TYPE_HASIL_TERBIT = 'PA_HASIL_TERBIT';

    /**
     * Penugasan dibatalkan.
     *
     * Sampai 2 September 2026 pembatalan tidak memberi tahu siapa pun. Terukur pada uji
     * rantai penuh: 16 pemberitahuan lahir saat penugasan dibentuk, NOL saat enam di
     * antaranya dibatalkan. Penilai tetap memegang undangan dan tautannya, tetap
     * mengerjakannya, dan — sebelum penjaga di `PaPayloadMapperService` dipasang — sistem
     * tetap menerimanya.
     *
     * Kedua perbaikan itu berpasangan dan tidak boleh dipisah: penjaga saja akan menolak
     * pekerjaan orang tanpa pernah memberitahunya lebih dulu, dan itu lebih buruk
     * daripada menerima diam-diam.
     */
    public const TYPE_DIBATALKAN = 'PA_DIBATALKAN';

    /**
     * Kiriman HC Learn tersimpan tetapi butirnya tidak cocok dengan paket soal, sehingga
     * penugasannya tidak naik. Tanpa pemberitahuan ini admin baru tahu bila kebetulan membuka
     * Monitoring Cycle, sementara penilainya mengira sudah selesai.
     */
    public const TYPE_KIRIMAN_TERTAHAN = 'PA_KIRIMAN_TERTAHAN';

    /** Alamat layar peserta; satu-satunya tempat penerima bisa menindaklanjuti. */
    public const TUJUAN_PESERTA = '/performance-appraisal/saya';

    /** Monitoring Cycle, tempat kiriman tertahan dipetakan ulang. */
    public const TUJUAN_PEMANTAUAN = '/performance-appraisal/pemantauan';

    /**
     * Berapa hari pengingat bertahan sebelum berhenti ditampilkan.
     *
     * Hanya untuk PENGINGAT (tenggat mendekat dan terlambat), yang memang digantikan
     * terbitan berikutnya. Pemberitahuan sekali-jalan — penugasan baru dan hasil terbit —
     * TIDAK diberi masa berlaku: keduanya pengumuman, bukan tagihan, dan orang berhak
     * menemukannya kembali berminggu-minggu kemudian.
     *
     * Pembacanya sudah menghormati `Expired_At` (`whereNull` ATAU `>= now()`), jadi ini
     * nol perubahan di hilir.
     */
    public const HARI_BERLAKU_PENGINGAT = 30;

    public function __construct(
        private readonly NotificationService $notifikasi,
    ) {}

    /**
     * Memberi tahu para penilai bahwa mereka mendapat penugasan baru.
     *
     * @param  list<int>  $assignmentIds
     */
    public function penugasanDibentuk(array $assignmentIds): int
    {
        if ($assignmentIds === []) {
            return 0;
        }

        $baris = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->whereIn('a.Id_PA_Assignment', $assignmentIds)
            ->where('a.Flag_Aktif', 'Y')
            ->get([
                'a.Id_PA_Assignment', 'a.Kode_Karyawan_Penilai', 'a.Due_At',
                'p.Nama_Karyawan_Saat_Cycle', 'p.Kode_Karyawan',
                'k.Nama_Cycle',
            ]);

        $muatan = [];

        foreach ($baris as $b) {
            // Penilaian diri disebut apa adanya. "Anda ditugaskan menilai Budi" ketika
            // Budi adalah dirinya sendiri terbaca seperti kekeliruan sistem.
            $diriSendiri = $b->Kode_Karyawan_Penilai === $b->Kode_Karyawan;

            $muatan[] = $this->muatan(
                kodeKaryawan: $b->Kode_Karyawan_Penilai,
                type: self::TYPE_DITUGASKAN,
                kunci: self::TYPE_DITUGASKAN.':'.$b->Id_PA_Assignment,
                judul: $diriSendiri ? 'Penilaian diri Anda dibuka' : 'Anda ditugaskan menilai',
                pesan: $diriSendiri
                    ? sprintf('Penilaian diri pada %s sudah bisa diisi.%s', $b->Nama_Cycle, $this->tenggat($b->Due_At))
                    : sprintf('Anda diminta menilai %s pada %s.%s',
                        $b->Nama_Karyawan_Saat_Cycle, $b->Nama_Cycle, $this->tenggat($b->Due_At)),
                referensi: (string) $b->Id_PA_Assignment,
                prioritas: 3,
            );
        }

        return $this->kirim($muatan, 'penugasanDibentuk');
    }

    /**
     * Memberi tahu penilai bahwa penugasannya dibatalkan.
     *
     * Dipanggil SESUDAH pembatalan tersimpan, bukan sebelumnya — memberi tahu lebih dulu
     * lalu gagal menyimpan menghasilkan pemberitahuan yang berbohong.
     *
     * Barisnya dibaca TANPA `Flag_Aktif = 'Y'`, berbeda dari `penugasanDibentuk()`.
     * Justru itu intinya: pembatalan mematikan barisnya, jadi menyaring yang aktif akan
     * membuat method ini selalu menemukan nol baris dan mengirim nol pemberitahuan —
     * hijau, senyap, dan sama sekali tidak bekerja.
     *
     * @param  list<int>  $assignmentIds
     */
    public function penugasanDibatalkan(array $assignmentIds): int
    {
        if ($assignmentIds === []) {
            return 0;
        }

        $baris = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->whereIn('a.Id_PA_Assignment', $assignmentIds)
            ->get([
                'a.Id_PA_Assignment', 'a.Kode_Karyawan_Penilai', 'a.Cancel_Reason',
                'a.Status_Penugasan',
                'p.Nama_Karyawan_Saat_Cycle', 'p.Kode_Karyawan',
                'k.Nama_Cycle',
            ]);

        $muatan = [];

        foreach ($baris as $b) {
            $diriSendiri = $b->Kode_Karyawan_Penilai === $b->Kode_Karyawan;

            /* Sebabnya ikut disampaikan bila ada. Pembatalan tanpa keterangan membuat
               orang mengira pekerjaannya hilang karena sistem rusak — dan yang paling
               lazim, sebabnya justru tidak ada hubungannya dengan dia: pesertanya
               dikecualikan dari siklus. */
            $sebab = $b->Cancel_Reason === null ? '' : ' Sebab: '.$b->Cancel_Reason;

            $muatan[] = $this->muatan(
                kodeKaryawan: $b->Kode_Karyawan_Penilai,
                type: self::TYPE_DIBATALKAN,
                kunci: self::TYPE_DIBATALKAN.':'.$b->Id_PA_Assignment,
                judul: $diriSendiri ? 'Penilaian diri Anda dibatalkan' : 'Penugasan penilaian dibatalkan',
                pesan: $diriSendiri
                    ? sprintf('Penilaian diri Anda pada %s dibatalkan dan tidak perlu diisi.%s',
                        $b->Nama_Cycle, $sebab)
                    : sprintf('Anda tidak perlu lagi menilai %s pada %s.%s',
                        $b->Nama_Karyawan_Saat_Cycle, $b->Nama_Cycle, $sebab),
                referensi: (string) $b->Id_PA_Assignment,
                // Lebih tinggi daripada penugasan baru: ini menghentikan pekerjaan yang
                // mungkin sedang dikerjakan, jadi terlambat membacanya berarti tenaga
                // yang benar-benar terbuang.
                prioritas: 2,
            );
        }

        return $this->kirim($muatan, 'penugasanDibatalkan');
    }

    /**
     * Mengingatkan penilai yang tenggatnya tinggal beberapa hari.
     *
     * Kuncinya memuat TANGGAL, sehingga pengingat boleh berulang tiap hari tetapi hanya
     * sekali per hari — menjalankan perintahnya dua kali dalam sehari tidak menghasilkan
     * dua pemberitahuan.
     */
    public function tenggatMendekat(int $ambangHari = PaPesertaService::AMBANG_DEKAT_HARI): int
    {
        $hariIni = date('Y-m-d');

        $baris = $this->penugasanBelumSelesai()
            ->whereRaw('a.Due_At >= CAST(GETDATE() AS date)')
            ->whereRaw('a.Due_At <= DATEADD(day, ?, CAST(GETDATE() AS date))', [$ambangHari])
            ->get($this->kolomPenugasan());

        $muatan = [];

        foreach ($baris as $b) {
            $sisa = $this->selisihHari($b->Due_At);

            $muatan[] = $this->muatan(
                kodeKaryawan: $b->Kode_Karyawan_Penilai,
                type: self::TYPE_TENGGAT_DEKAT,
                kunci: self::TYPE_TENGGAT_DEKAT.':'.$b->Id_PA_Assignment.':'.$hariIni,
                judul: $sisa === 0 ? 'Penilaian jatuh tempo hari ini' : "Penilaian tinggal {$sisa} hari",
                pesan: sprintf('Penilaian untuk %s pada %s belum selesai. Tenggat %s.',
                    $b->Nama_Karyawan_Saat_Cycle, $b->Nama_Cycle, $this->tanggalPanjang($b->Due_At)),
                referensi: (string) $b->Id_PA_Assignment,
                // Naik seiring mendekatnya tenggat. Yang jatuh tempo hari ini tidak boleh
                // tenggelam di antara yang masih tiga hari lagi.
                prioritas: $sisa === 0 ? 1 : 2,
                keparahan: 'warning',
                berlakuSampai: $this->masaBerlakuPengingat(),
                pertahankanStatusBaca: true,
            );
        }

        return $this->kirim($muatan, 'tenggatMendekat');
    }

    /**
     * Memberi tahu penilai yang tenggatnya sudah lewat.
     */
    public function sudahTerlambat(): int
    {
        /* Dihitung SEKALI di sini, bukan per baris di dalam perulangan. Batch yang
           melintasi tengah malam Minggu ke Senin akan terbelah ke dua ember pekan dan
           melahirkan baris kembar untuk penugasan yang terproses sesudah pukul 00.00.
           Penjadwalnya jalan 01.45 jadi lintasan itu mustahil sekarang — tetapi asimetri
           semacam ini yang biasanya tersalin ke tempat berikutnya. */
        $pekanIni = date('o-\WW');

        $baris = $this->penugasanBelumSelesai()
            // Ambangnya `<`, bukan `<=`: tenggat DATE berarti sampai AKHIR hari itu, dan
            // menegur orang pada hari yang masih miliknya adalah cara cepat membuat
            // pemberitahuan ini diabaikan seterusnya.
            ->whereRaw('a.Due_At < CAST(GETDATE() AS date)')
            ->get($this->kolomPenugasan());

        $muatan = [];

        foreach ($baris as $b) {
            $telat = abs($this->selisihHari($b->Due_At));

            $muatan[] = $this->muatan(
                kodeKaryawan: $b->Kode_Karyawan_Penilai,
                type: self::TYPE_TERLAMBAT,
                /* MINGGUAN, bukan harian — dan ini perbedaan yang menentukan.
                   Kunci ber-tanggal berarti satu penugasan yang terlambat tiga puluh hari
                   melahirkan TIGA PULUH baris. `PA_TENGGAT_DEKAT` terbatas sendiri karena
                   jendelanya cuma tiga hari; keterlambatan tidak punya batas atas.
                   Sisi manusianya terasa lebih dulu daripada sisi mesinnya: penilai membuka
                   topbar dan melihat tiga puluh baris "Penilaian terlambat" untuk SATU
                   penugasan yang sama. Menegur orang tiap hari untuk hal yang sama adalah
                   cara tercepat membuat pemberitahuan diabaikan — alasan yang sama dengan
                   ambang `<` di atas. */
                kunci: self::TYPE_TERLAMBAT.':'.$b->Id_PA_Assignment.':'.$pekanIni,
                judul: sprintf('Penilaian terlambat %d hari', $telat),
                pesan: sprintf('Penilaian untuk %s pada %s melewati tenggat %s dan belum selesai.',
                    $b->Nama_Karyawan_Saat_Cycle, $b->Nama_Cycle, $this->tanggalPanjang($b->Due_At)),
                referensi: (string) $b->Id_PA_Assignment,
                prioritas: 1,
                keparahan: 'danger',
                berlakuSampai: $this->masaBerlakuPengingat(),
                pertahankanStatusBaca: true,
            );
        }

        return $this->kirim($muatan, 'sudahTerlambat');
    }

    /**
     * Memberi tahu peserta bahwa hasil penilaiannya sudah terbit.
     *
     * @param  list<int>  $resultIds
     */
    public function hasilTerbit(array $resultIds): int
    {
        if ($resultIds === []) {
            return 0;
        }

        $baris = DB::table('N_PA_Result as r')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->whereIn('r.Id_PA_Result', $resultIds)
            ->where('r.Status_Hasil', PaResultPublishService::STATUS_TERBIT)
            ->get(['r.Id_PA_Result', 'p.Kode_Karyawan', 'k.Nama_Cycle']);

        $muatan = [];

        foreach ($baris as $b) {
            $muatan[] = $this->muatan(
                kodeKaryawan: $b->Kode_Karyawan,
                type: self::TYPE_HASIL_TERBIT,
                kunci: self::TYPE_HASIL_TERBIT.':'.$b->Id_PA_Result,
                judul: 'Hasil penilaian Anda sudah terbit',
                /* Nilainya TIDAK disebut di sini. Pemberitahuan muncul di topbar dan
                   pratinjaunya bisa terbaca orang lain yang kebetulan melihat layar;
                   grade seseorang bukan sesuatu yang layak tampil di sana. */
                pesan: sprintf('Hasil penilaian %s sudah bisa dilihat.', $b->Nama_Cycle),
                referensi: (string) $b->Id_PA_Result,
                prioritas: 2,
                keparahan: 'success',
            );
        }

        return $this->kirim($muatan, 'hasilTerbit');
    }

    /**
     * Memberi tahu admin penanggung jawab siklus bahwa ada kiriman HC Learn yang tertahan.
     *
     * PENERIMANYA pembuat dan pengunci siklus (`Created_By`, `Locked_By`): keduanya admin
     * yang memegang siklus itu. Hak akses halaman Monitoring berbasis jabatan dan peran,
     * dan menyebar ke semua pemegangnya akan ikut menegur orang yang tidak mengurus siklus ini.
     *
     * SATU BARIS PER SIKLUS PER HARI. Paket soal yang salah ikat membuat seluruh kiriman
     * tertahan sekaligus; satu pemberitahuan per kiriman akan menenggelamkan topbar admin.
     * Kiriman berikutnya di hari yang sama memperbarui baris itu dan menandainya belum dibaca.
     */
    public function kirimanTertahan(int $payloadId): int
    {
        $b = DB::table('N_PA_Assignment_Payload as y')
            ->join('N_PA_Assignment_Integration as i', 'i.Id_PA_Assignment_Integration', '=', 'y.PA_Assignment_Integration_Id')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('y.Id_PA_Assignment_Payload', $payloadId)
            ->first([
                'k.Id_PA_Cycle', 'k.Nama_Cycle', 'k.Created_By', 'k.Locked_By',
                'a.Nama_Penilai_Saat_Cycle', 'p.Nama_Karyawan_Saat_Cycle',
            ]);

        if ($b === null) {
            return 0;
        }

        $penerima = DB::table('Karyawan')
            ->whereIn('UserID_Web', array_values(array_filter([$b->Created_By, $b->Locked_By])))
            ->where('Aktif', 'Y')
            ->distinct()
            ->pluck('Kode_Karyawan');

        if ($penerima->isEmpty()) {
            Log::warning('PA: kiriman tertahan tanpa admin siklus yang bisa diberi tahu', [
                'payload_id' => $payloadId,
                'cycle_id' => (int) $b->Id_PA_Cycle,
            ]);

            return 0;
        }

        $muatan = $penerima->map(fn ($kode) => $this->muatan(
            kodeKaryawan: (string) $kode,
            type: self::TYPE_KIRIMAN_TERTAHAN,
            kunci: self::TYPE_KIRIMAN_TERTAHAN.':'.$b->Id_PA_Cycle.':'.date('Y-m-d'),
            judul: 'Ada kiriman penilaian yang perlu ditinjau',
            pesan: sprintf(
                'Jawaban %s untuk %s di %s sudah masuk tetapi belum lengkap, jadi belum terhitung. '
                .'Buka Monitoring Cycle untuk memeriksanya.',
                $b->Nama_Penilai_Saat_Cycle,
                $b->Nama_Karyawan_Saat_Cycle,
                $b->Nama_Cycle,
            ),
            referensi: (string) $b->Id_PA_Cycle,
            prioritas: 2,
            keparahan: 'warning',
            tujuan: self::TUJUAN_PEMANTAUAN.'?cycle='.$b->Id_PA_Cycle.'&tahap=pengisian',
        ))->values()->all();

        return $this->kirim($muatan, 'kirimanTertahan');
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    private function penugasanBelumSelesai()
    {
        return DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('a.Flag_Aktif', 'Y')
            ->whereNotNull('a.Due_At')
            /* MENUNGGU_VERIFIKASI ikut dikecualikan: orangnya SUDAH mengirim, yang
               belum adalah pengesahan admin HC Learn. Menagihnya lagi berarti menagih
               pekerjaan yang sudah dilakukan, dan penerima pengingat yang salah alamat
               berhenti membaca pengingat berikutnya. */
            /* TERKUMPUL ikut dikecualikan dengan alasan yang sama seperti
               MENUNGGU_VERIFIKASI: orangnya SUDAH mengerjakan lembar ini. Menagihnya lagi
               berarti menagih pekerjaan yang sudah dilakukan, dan pengingat yang salah
               alamat membuat seluruh pengingat berhenti dipercaya. Yang masih perlu ia
               lakukan - menekan kirim - bukan urusan pengingat pengisian ini. */
            ->whereNotIn('a.Status_Penugasan', PaStatusPenugasan::TIDAK_DIHITUNG_TERLAMBAT)
            // Hanya siklus yang jendelanya memang sedang terbuka. Mengingatkan orang atas
            // siklus yang sudah ditutup berarti menagih pekerjaan yang tak bisa lagi
            // dikerjakannya.
            ->whereIn('k.Status_Cycle', ['OPEN']);
    }

    /**
     * @return list<string>
     */
    private function kolomPenugasan(): array
    {
        return [
            'a.Id_PA_Assignment', 'a.Kode_Karyawan_Penilai', 'a.Due_At',
            'p.Nama_Karyawan_Saat_Cycle', 'k.Nama_Cycle',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function muatan(
        string $kodeKaryawan,
        string $type,
        string $kunci,
        string $judul,
        string $pesan,
        string $referensi,
        int $prioritas,
        string $keparahan = 'info',
        ?string $berlakuSampai = null,
        bool $pertahankanStatusBaca = false,
        string $tujuan = self::TUJUAN_PESERTA,
    ): array {
        return array_filter([
            'kode_karyawan' => $kodeKaryawan,
            'type' => $type,
            'title' => $judul,
            'message' => $pesan,
            'module' => self::MODUL,
            'severity' => $keparahan,
            'action_url' => $tujuan,
            'created_by' => 'SYSTEM_PA',
            'priority' => $prioritas,
            'reference_id' => $referensi,
            'notification_key' => $kunci,
            'meta' => [
                'source' => self::SUMBER,
                'event_type' => $type,
                'reference_id' => $referensi,
            ],
            'expired_at' => $berlakuSampai,
            /* HANYA untuk pengingat, dan ini menutup lubang yang tidak terlihat dari
               jumlah baris.

               Kunci mingguan membuat pengingat hari kedua menemukan baris pekan yang sama
               dan meng-UPDATE-nya. Tetapi judulnya memuat angka hari yang BERUBAH tiap
               hari, sehingga `notificationContentChanged` menyatakan isinya berubah, dan
               tanpa penanda ini `buildUpdateColumns` mengembalikan `Is_Read` ke 'T'.
               Akibatnya penilai melihat baris yang sama menjadi belum-dibaca SETIAP HARI —
               jumlah barisnya turun, tegurannya tidak. Padahal berhenti menegur tiap hari
               itulah maksud kunci mingguannya.

               Angka harinya tetap diperbarui, jadi judulnya tetap akurat; yang bertahan
               hanya status bacanya, sampai kunci pekan berikutnya melahirkan baris yang
               memang baru. Pengumuman sekali-jalan tidak memakainya — keduanya ditulis
               sekali saja. */
            'preserve_read' => $pertahankanStatusBaca ?: null,
            /* Callback ini LOAD-BEARING; `array_filter()` telanjang memakai kebenaran-nilai
           dan akan membuang `priority` bila suatu saat bernilai 0, serta judul/pesan yang
           kosong — semuanya senyap.

           `expired_at` dan `preserve_read` adalah SATU-SATUNYA yang boleh null di sini.
           Kalau kelak ada parameter nullable baru, ia akan terbuang diam-diam dan
           `buildInsertColumns` memasang bawaannya tanpa satu pun tanda. */
        ], fn ($v) => $v !== null);
    }

    /**
     * Batas tampil pengingat.
     *
     * Tanpa ini, pemberitahuan PA tidak pernah kedaluwarsa dan tidak ada satu pun perintah
     * yang membersihkan `N_HRIS_Notification`. Pada siklus 958 peserta dengan tiga sampai
     * lima penilai per orang, penugasan yang tertinggal sebulan akan menumpuk ribuan baris
     * pada tabel yang di-polling topbar setiap menit oleh setiap orang yang membuka HCIS.
     */
    private function masaBerlakuPengingat(): string
    {
        return now()->addDays(self::HARI_BERLAKU_PENGINGAT)->format('Y-m-d H:i:s');
    }

    /**
     * @param  list<array<string, mixed>>  $muatan
     */
    private function kirim(array $muatan, string $asal): int
    {
        if ($muatan === []) {
            return 0;
        }

        try {
            $this->notifikasi->upsertManyByKey($muatan);

            return count($muatan);
        } catch (\Throwable $e) {
            /* Pemberitahuan yang gagal TIDAK boleh menggagalkan pekerjaan yang
               memicunya. Penugasan yang sudah terbentuk tetap sah walau pemberitahuannya
               tidak sampai; membatalkannya karena notifikasi gagal akan menukar kerugian
               kecil dengan kerugian besar. Dicatat supaya kegagalannya tidak sunyi. */
            Log::warning('PaNotificationService gagal mengirim', [
                'asal' => $asal,
                'jumlah' => count($muatan),
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Selisih hari kalender antara tenggat dan hari ini.
     *
     * DUA JAM YANG BERBEDA, DAN ITU DISENGAJA TETAPI PERLU DIKETAHUI
     *
     * Penyaring barisnya memakai `GETDATE()` milik SQL Server; angka di sini dihitung dari
     * `date()` milik PHP. Selama keduanya sepakat soal "hari ini", semuanya benar. Saat
     * tidak — beda zona waktu antara server aplikasi dan server basis data — penyaringnya
     * tetap memilih baris yang tepat sementara judulnya menyebut angka yang meleset satu
     * hari.
     *
     * Tidak disatukan karena menyatukannya berarti menarik seluruh baris ke PHP hanya
     * untuk menyaringnya, dan pemindai ini berjalan atas ribuan penugasan. Yang dilakukan
     * sebagai gantinya: uji menegaskan ANGKA di judulnya, bukan sekadar keberadaan
     * barisnya, sehingga perbedaan jam itu akan terlihat sebagai uji merah — bukan sebagai
     * keluhan orang setahun kemudian.
     */
    private function selisihHari(mixed $dueAt): int
    {
        $tenggat = new \DateTimeImmutable(substr((string) $dueAt, 0, 10));
        $hariIni = new \DateTimeImmutable(date('Y-m-d'));

        return (int) $hariIni->diff($tenggat)->format('%r%a');
    }

    private function tenggat(mixed $dueAt): string
    {
        return $dueAt === null ? '' : ' Tenggat '.$this->tanggalPanjang($dueAt).'.';
    }

    private function tanggalPanjang(mixed $dueAt): string
    {
        return (new \DateTimeImmutable(substr((string) $dueAt, 0, 10)))->format('d M Y');
    }
}
