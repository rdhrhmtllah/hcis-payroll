<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lembar penilaian SIMULASI — pengganti sementara HC Learn.
 *
 * ------------------------------------------------------------------------------------------
 * KENAPA INI ADA, DAN KENAPA IA HARUS MATI SENDIRI KELAK
 * ------------------------------------------------------------------------------------------
 *
 * Pengisian penilaian adalah milik HC Learn. HC Learn belum menyediakan satu endpoint pun,
 * sehingga seluruh pintu yang tersemai sekarang bertoken `SIM-PA-` dan tidak menunjuk ke
 * mana-mana: penilai menekan "Mulai Menilai" dan tidak terjadi apa-apa. Kelas ini menutup
 * lubang itu dengan lembar penilaian di dalam HCIS sendiri.
 *
 * Ia BUKAN jalur pengisian kedua yang sah. HC Learn kini hidup — penjadwalannya nyata sejak
 * 10 September 2026 — dan dua tempat mengisi penilaian yang sama berarti dua sumber kebenaran
 * atas skor orang, tanpa satu kolom pun yang bisa mengatakan mana yang dipakai. Karena itu
 * seluruh jalannya bergerbang pada DUA hal sekaligus: lingkungannya bukan production, dan
 * tokennya berawalan `SIM-PA-`. Dua-duanya, bukan salah satu.
 *
 * Gerbangnya BERDIRI SENDIRI, tidak lagi menumpang setelan simulasi penjadwalan yang sudah
 * dicabut. Menumpang setelan milik fitur lain berarti mencabut fitur itu diam-diam membuka
 * yang ini.
 *
 * ------------------------------------------------------------------------------------------
 * KONTRAKNYA SAMA PERSIS DENGAN CALLBACK HC LEARN
 * ------------------------------------------------------------------------------------------
 *
 * Kiriman dari sini masuk lewat `PaPayloadMapperService::terimaDanPetakan()` — method yang
 * sama yang dipanggil `LmsThirdPartyCallbackController::completePaPenilaian()` ketika HC Learn
 * nanti memanggil `POST /api/v1/pa/third-party/penilaian/complete` — alamat PA sendiri,
 * bukan lagi callback bersama LMS/SOP. Muatannya disusun dengan bentuk yang sama pula.
 *
 * Itu keputusan, bukan kebetulan. Simulasi yang menulis langsung ke tabel hasil akan menguji
 * dirinya sendiri: ia hijau justru pada bagian yang kelak diganti, dan diam pada bagian yang
 * akan dipakai selamanya. Dengan lewat pemeta, yang teruji sekarang adalah pemetaan muatan,
 * penilaian per butir, dan idempotensi kiriman ulang — persis yang akan dilalui HC Learn.
 *
 * Yang TIDAK teruji lewat sini: `LmsApiGuard` (tanda tangan, nonce, pemilihan modul), karena
 * pemakainya sudah masuk sebagai dirinya sendiri di HCIS. Dikatakan supaya hijaunya tidak
 * dibaca lebih luas daripada yang sebenarnya diuji.
 */
class PaSimulasiPengisianService
{
    /** Hanya token dengan awalan ini yang boleh diisi dari sini. */
    public const AWALAN_SIMULASI = 'SIM-PA-';

    /**
     * Jenis soal yang DIJAWAB DENGAN TEKS, bukan skor.
     *
     * Sejalan dengan `PaSimulasiHcLearnCommand::detailUntuk()`: yang menentukan sebenarnya
     * adalah ada tidaknya `Skor_Maksimal` yang sah, dan pada data nyata kedua jenis inilah yang
     * tidak pernah punya. Keduanya disebut supaya niatnya terbaca, tetapi yang MENEGAKKAN tetap
     * `Skor_Maksimal` — kalau suatu saat `uraian` diberi skala, ia akan berskor tanpa berkas ini
     * perlu disunting.
     */
    public const JENIS_TEKS = ['teks', 'uraian'];

    public function __construct(
        private readonly PaHcLearnScheduleService $jadwal,
        private readonly PaPayloadMapperService $pemeta,
    ) {}

    /**
     * Lembar penilaian satu penugasan, siap dirender.
     *
     * @return array<string, mixed>
     */
    public function lembar(string $token, int $userId): array
    {
        $tugas = $this->tugasMilikSaya($token, $userId);

        $butir = DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $tugas->paket_id)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get([
                'Id_Jenis_Soal', 'Kode_Soal_Sumber', 'Nama_Bagian_Sumber', 'Jenis_Soal',
                'Skor_Maksimal', 'Bobot_Penilaian', 'Urutan_Tampil',
            ]);

        if ($butir->isEmpty()) {
            throw new RuntimeException(
                'Paket soal penugasan ini tidak punya satu butir pun yang aktif, sehingga tidak '
                .'ada yang bisa dinilai. Hubungi administrator siklus.'
            );
        }

        return [
            'token' => $tugas->token,
            /* TERKUMPUL ikut terhitung sudah selesai DI LEMBAR INI. Yang ditanyakan tanda
               ini adalah "apakah lembar ini masih perlu diisi", dan lembar yang sudah
               terkumpul jawabannya tidak - yang belum hanyalah pengirimannya, dan itu
               tombol di halaman PA Saya, bukan di sini. Tanpa ini penilai yang membuka
               kembali tautannya akan disuguhi formulir kosong seolah isiannya hilang. */
            'sudah_selesai' => in_array($tugas->status, PaStatusPenugasan::SUDAH_DIKERJAKAN, true),
            'penugasan' => [
                'id' => (int) $tugas->id,
                'status' => $tugas->status,
                'due_at' => $tugas->due_at,
                'kategori' => $tugas->kode_kategori,
                'nama_kategori' => $tugas->nama_kategori,
                'diri_sendiri' => $tugas->kode_peserta === $tugas->kode_penilai,
            ],
            'peserta' => [
                'kode_karyawan' => $tugas->kode_peserta,
                'nama' => $tugas->nama_peserta,
                'jabatan' => $tugas->jabatan_peserta,
                'divisi' => $tugas->divisi_peserta,
            ],
            'siklus' => [
                'id' => (int) $tugas->cycle_id,
                'kode' => $tugas->kode_cycle,
                'nama' => $tugas->nama_cycle,
            ],
            'butir' => $butir->map(fn ($b) => [
                'kode_soal' => $this->kodeSoal($b),
                'pertanyaan' => (string) ($b->Nama_Bagian_Sumber ?: $b->Id_Jenis_Soal),
                'jenis' => (string) $b->Jenis_Soal,
                'skor_maksimal' => $this->maksimalSah($b->Skor_Maksimal),
                'bobot' => $b->Bobot_Penilaian === null ? null : (float) $b->Bobot_Penilaian,

                /* Layar TIDAK menyimpulkan sendiri butir mana yang berskor. Aturannya sama
                   dengan yang dipakai saat kiriman disusun di bawah; dua tempat yang
                   menyimpulkannya sendiri akan berselisih, dan yang berselisih di sini
                   membuat butir dikirim tanpa skor lalu ditolak pemeta. */
                'berskor' => $this->berskor($b->Jenis_Soal, $b->Skor_Maksimal),
            ])->all(),
        ];
    }

    /**
     * Menerima jawaban dan meneruskannya lewat kontrak callback HC Learn.
     *
     * @param  array<string, mixed>  $jawaban  kode soal => skor angka atau teks
     * @return array<string, mixed>
     */
    public function kirim(string $token, int $userId, array $jawaban): array
    {
        $tugas = $this->tugasMilikSaya($token, $userId);

        $butir = DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $tugas->paket_id)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get(['Id_Jenis_Soal', 'Kode_Soal_Sumber', 'Jenis_Soal', 'Skor_Maksimal', 'Bobot_Penilaian']);

        $detail = [];
        $kurang = [];

        foreach ($butir as $b) {
            $kode = $this->kodeSoal($b);
            $isi = $jawaban[$kode] ?? null;
            $maks = $this->maksimalSah($b->Skor_Maksimal);

            $satu = [
                'Kode_Soal' => $kode,
                'Jenis_Soal' => $b->Jenis_Soal,
                'Bobot_Penilaian' => $b->Bobot_Penilaian,
            ];

            if ($this->berskor($b->Jenis_Soal, $b->Skor_Maksimal)) {
                $skor = ($isi === null || $isi === '') ? null : (float) $isi;

                if ($skor === null) {
                    $kurang[] = $kode;

                    continue;
                }

                /* Batasnya ditegakkan DI SINI JUGA, bukan hanya di pemeta. Pemeta memang
                   menolak skor yang melebihi maksimal — tetapi penolakan itu datang sebagai
                   kegagalan seluruh kiriman sesudah orangnya menekan Kirim, sementara di sini
                   ia masih bisa dikatakan sebagai butir yang mana. */
                if ($skor < 0 || $skor > $maks) {
                    throw new RuntimeException(
                        "Nilai untuk {$kode} harus antara 0 dan {$maks}."
                    );
                }

                $satu['Skor'] = $skor;
                $satu['Skor_Maksimal'] = $maks;
            } else {
                $teks = trim((string) ($isi ?? ''));

                if ($teks === '') {
                    $kurang[] = $kode;

                    continue;
                }

                $satu['Jawaban_Teks'] = $teks;
            }

            $detail[] = $satu;
        }

        if ($kurang !== []) {
            throw new RuntimeException(sprintf(
                'Masih ada %d butir yang belum diisi: %s.',
                count($kurang),
                implode(', ', array_slice($kurang, 0, 5)).(count($kurang) > 5 ? ', …' : ''),
            ));
        }

        /* Bentuk muatannya SAMA dengan yang akan dikirim HC Learn — lihat
           `LmsThirdPartyCallbackController::completePaPenilaian()` untuk field wajibnya.
           `Id_Ujian_Nilai_Akhir` diberi awalan simulasi supaya jejaknya bisa dibedakan dan
           dibersihkan `pa:simulasi-hclearn bersihkan`. */
        $muatan = [
            'Kode_Karyawan' => $tugas->kode_penilai,
            'Id_Penjadwalan_Integrasi' => (int) $tugas->integrasi_id,
            'Id_Ujian_Token' => $tugas->token,
            'Id_Ujian_Nilai_Akhir' => 'SIM-'.$tugas->integrasi_id,
            'Detail' => $detail,
        ];

        $hasil = $this->pemeta->terimaDanPetakan($tugas->token, $muatan, $userId);

        return [
            'token' => $tugas->token,
            'baru' => (bool) ($hasil['baru'] ?? false),
            'butir_dikirim' => count($detail),

            /* Kiriman ulang yang isinya sama dijawab berhasil TANPA baris kedua — perilaku
               pemeta, bukan kelonggaran di sini. Dibedakan supaya layar bisa mengatakan
               "sudah pernah terkirim" alih-alih mengaku baru menyimpan. */
            'pesan' => ($hasil['baru'] ?? false)
                ? 'Penilaian terkirim dan sudah dipetakan.'
                : 'Penilaian ini sudah pernah terkirim dengan isi yang sama.',
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Penugasan di balik token — dan HANYA bila memang milik orang yang sedang login.
     *
     * Tokennya SENDIRI bukan izin. Di HC Learn ia memang berperan sebagai kredensial, tetapi di
     * sini pemakainya sudah masuk sebagai dirinya sendiri, sehingga token yang diterima begitu
     * saja berarti siapa pun yang menebak atau melihat satu token bisa menilai atas nama orang
     * lain. Kepemilikannya diperiksa terhadap `Kode_Karyawan_Penilai`, bukan terhadap tokennya.
     */
    private function tugasMilikSaya(string $token, int $userId): object
    {
        /* Gerbangnya BERDIRI SENDIRI sejak simulasi penjadwalan dicabut (10 September
           2026). Sebelumnya ia menumpang `sedangDisimulasikan()` milik penjadwalan —
           menumpang setelan milik fitur lain berarti mencabut fitur itu diam-diam membuka
           yang ini. Yang diperiksa sekarang lingkungannya sendiri, dan lingkungan tidak
           bisa salah tersalin dari `.env` staging. */
        if (app()->isProduction()) {
            throw new RuntimeException(
                'Lembar penilaian simulasi tidak aktif di production. Pengisian penilaian '
                .'dilakukan di HC Learn.'
            );
        }

        if (! str_starts_with($token, self::AWALAN_SIMULASI)) {
            throw new RuntimeException(
                'Token ini bukan token simulasi, sehingga tidak boleh diisi dari sini. '
                .'Penilaian dengan token sungguhan dikerjakan di HC Learn.'
            );
        }

        $saya = DB::table('Karyawan')
            ->where('UserID_Web', $userId)
            ->where('Aktif', 'Y')
            ->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        if ($saya === null) {
            throw new RuntimeException(
                'Akun Anda belum tersambung ke data karyawan yang aktif.'
            );
        }

        $tugas = DB::table('N_PA_Assignment_Integration as i')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->join('N_PA_Rater_Category as k', 'k.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('i.Id_Ujian_Token', $token)
            ->where('i.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'Y')
            ->first([
                'i.Id_PA_Assignment_Integration as integrasi_id',
                'i.Id_Ujian_Token as token',
                'i.PA_Cycle_Question_Package_Id as paket_id',
                'a.Id_PA_Assignment as id',
                'a.Status_Penugasan as status',
                'a.Due_At as due_at',
                'a.Kode_Perusahaan_Penilai as pt_penilai',
                'a.Kode_Karyawan_Penilai as kode_penilai',
                'p.Kode_Karyawan as kode_peserta',
                'p.Nama_Karyawan_Saat_Cycle as nama_peserta',
                'p.Nama_Jabatan_Saat_Cycle as jabatan_peserta',
                'p.Nama_Divisi_Saat_Cycle as divisi_peserta',
                'c.Id_PA_Cycle as cycle_id',
                'c.Kode_Cycle as kode_cycle',
                'c.Nama_Cycle as nama_cycle',
                'c.Status_Cycle as status_cycle',
                'k.Kode_Kategori as kode_kategori',
                'k.Nama_Kategori as nama_kategori',
            ]);

        if ($tugas === null) {
            throw new RuntimeException('Penugasan untuk token ini tidak ditemukan.');
        }

        /* Dicocokkan PERUSAHAAN dan KARYAWAN sekaligus: kunci karyawan di HRIS memang komposit,
           dan mencocokkan kodenya saja akan menyamakan dua orang bernomor sama dari perusahaan
           berbeda — tepat pada pemeriksaan yang menentukan siapa boleh menilai siapa. */
        if ($tugas->pt_penilai !== $saya->Kode_Perusahaan
            || $tugas->kode_penilai !== $saya->Kode_Karyawan) {
            throw new RuntimeException(
                'Penugasan ini bukan milik Anda. Setiap orang hanya bisa mengisi penilaian yang '
                .'ditugaskan kepadanya.'
            );
        }

        if ($tugas->status === 'DIBATALKAN') {
            throw new RuntimeException('Penugasan ini sudah dibatalkan.');
        }

        /* Jendela pengisian ditegakkan lewat STATUS SIKLUS, sejalan dengan
           `PaPesertaService::STATUS_SIKLUS_DAPAT_DIISI`. Siklus yang belum dibuka atau sudah
           ditutup tidak boleh menerima penilaian lewat pintu belakang ini. */
        if (! in_array($tugas->status_cycle, PaPesertaService::STATUS_SIKLUS_DAPAT_DIISI, true)) {
            throw new RuntimeException(sprintf(
                'Siklus %s berstatus %s, bukan OPEN. Pengisian hanya bisa dilakukan selama '
                .'jendela penilaiannya terbuka.',
                $tugas->kode_cycle,
                $tugas->status_cycle,
            ));
        }

        return $tugas;
    }

    /**
     * Kode soal yang dikirim HC Learn — `Kode_Soal_Sumber`, kunci yang dipakai pemeta sejak
     * 10 September 2026. `Id_Jenis_Soal` adalah id BAGIAN; mengirimnya sebagai kode membuat
     * kiriman simulasi tidak terpetakan pada setiap butir yang kedua nilainya berbeda.
     */
    private function kodeSoal(object $butir): string
    {
        return (string) ($butir->Kode_Soal_Sumber ?? $butir->Id_Jenis_Soal);
    }

    private function maksimalSah(mixed $nilai): ?float
    {
        $n = $nilai === null ? null : (float) $nilai;

        return ($n === null || $n <= 0) ? null : $n;
    }

    private function berskor(mixed $jenis, mixed $skorMaksimal): bool
    {
        if (in_array(strtolower((string) $jenis), self::JENIS_TEKS, true)) {
            return false;
        }

        return $this->maksimalSah($skorMaksimal) !== null;
    }
}
