<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Penjadwalan HC Learn untuk Performance Appraisal: membuatkan pintu bagi tiap penilai.
 *
 * SATU PENUGASAN = SATU PINTU. Pintu itulah yang menjawab "siapa menilai siapa pada kategori
 * apa", sehingga HC Learn tidak perlu tahu apa pun tentang peserta yang dinilai.
 *
 * ── Bentuknya mengikuti kontrak mereka, bukan kenyamanan kita ─────────────────
 *
 * Sampai 10 September 2026 layanan ini menjadwalkan SATU PER SATU dan tokennya dikarang
 * sendiri, karena HC Learn memang belum menyediakan jalurnya. Kontraknya kini ada, dan
 * bentuknya berbeda pada dua hal yang menentukan:
 *
 *  1. MASSAL. Satu kiriman memuat satu master ujian dan sampai seribu karyawan. Menjadwalkan
 *     3.580 penilai satu per satu berarti 3.580 permintaan ke sistem luar; dengan
 *     pengelompokan ini ia menjadi belasan.
 *
 *  2. ASINKRON. `store` menjawab 202 dan menitipkan pekerjaannya ke antrean mereka —
 *     tokennya BELUM ADA saat jawaban itu diterima. Karena itu penjadwalan di sini berhenti
 *     pada "sudah dititipkan", dan `tarikStatus()` yang mengisi pintunya kemudian.
 *
 * Konsekuensinya pada layar disebutkan apa adanya: sesudah "Buka Pengisian", penilai belum
 * langsung punya tautan. Menyembunyikan itu di balik penantian di dalam permintaan HTTP akan
 * menghasilkan permintaan yang menggantung menit-menit lalu mati kehabisan waktu, dan
 * meninggalkan sebagian penilai tanpa pintu tanpa ada yang tahu berapa banyak.
 *
 * ── Kenapa process_id disimpan, bukan sekadar dipakai sekali ──────────────────
 *
 * Kiriman yang terputus di tengah — jaringan, deploy, tab ditutup — meninggalkan pekerjaan
 * yang SUDAH masuk antrean mereka. Tanpa process_id tersimpan, satu-satunya cara memastikan
 * nasibnya adalah mengirim ulang, dan mengirim ulang berarti menerbitkan pintu kedua untuk
 * orang yang sudah punya.
 *
 * ── Yang tidak disentuh ───────────────────────────────────────────────────────
 *
 * Layanan ini hanya menulis `N_PA_Assignment_Integration`. Ia tidak memanggil
 * `SopKuisDispatchService` maupun `SopPenjadwalanService`, dan tidak menulis satu tabel
 * `N_LMS_*` atau `N_SOP_*` pun. Cara paling andal untuk tidak merusak modul lain adalah
 * tidak menyentuhnya.
 */
class PaHcLearnScheduleService
{
    /** Berapa baris integrasi ditulis per potongan. Dijaga kecil supaya transaksinya pendek. */
    private const POTONGAN = 200;

    /**
     * Subjek per kiriman penjadwalan — sengaja jauh di bawah MAKS_SUBJEK (1.000).
     *
     * Dokumentasi HC Learn mengizinkan seribu, tetapi pada 8 Oktober 2026 HC Learn dev
     * menolak kiriman ±1.000 subjek ujian 100166 dengan HTTP 500, sementara kiriman 150 subjek
     * pada ujian yang sama diterima semua. Satu kiriman yang ditolak menggagalkan seluruh
     * isinya, jadi potongan kecil juga memperkecil yang harus dijadwalkan ulang.
     */
    private const SUBJEK_PER_KIRIMAN = 150;

    /**
     * Status penugasan yang pintunya TIDAK boleh dicetak ulang.
     *
     * Ketiganya berarti penilainya sudah menyetorkan pekerjaannya. Menerbitkan ulang
     * kodenya sesudah itu membuang jawaban yang sudah ditulis orang — dan pada
     * `MENUNGGU_VERIFIKASI` bahkan membuang pekerjaan yang sedang diperiksa pihak lain.
     */
    private const STATUS_TAK_BOLEH_CETAK_ULANG = ['SELESAI', 'MENUNGGU_VERIFIKASI', 'TERKUMPUL'];

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaHcLearnJadwalContract $kontrak,
    ) {}

    /* ------------------------------------------------------------------ *
     * Menitipkan pekerjaannya                                             *
     * ------------------------------------------------------------------ */

    /**
     * Menjadwalkan seluruh penugasan satu siklus yang BELUM punya pintu.
     *
     * Idempoten: penugasan yang sudah punya baris integrasi aktif dilewati, bukan
     * digandakan. Itu bukan kebaikan hati — penjadwalan ribuan penilai ke sistem luar hampir
     * pasti tidak seluruhnya berhasil sekali jalan, dan satu-satunya perbaikan yang masuk
     * akal adalah menjalankannya ulang. Basis data pun menegakkannya lewat
     * `UX_PA_Assignment_Integration_Aktif`.
     *
     * @return array{diminta:int, dititipkan:int, dilewati:int, gagal:int, proses:list<string>, contoh_galat:list<string>}
     */
    /** @param  list<int>|null  $hanyaPenugasan  null = semua yang belum berpintu di siklus */
    public function jadwalkanSiklus(int $cycleId, ?int $olehUserId = null, int $batas = 0, ?array $hanyaPenugasan = null): array
    {
        $jendela = $this->jendelaSah($cycleId);

        $belum = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->leftJoin('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')
                    ->where('i.Flag_Aktif', '=', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('a.Flag_Aktif', 'Y')
            ->whereNull('i.Id_PA_Assignment_Integration')
            ->when($hanyaPenugasan !== null, fn ($q) => $q->whereIn('a.Id_PA_Assignment', $hanyaPenugasan ?: [0]))
            ->orderBy('a.Id_PA_Assignment')
            ->when($batas > 0, fn ($q) => $q->limit($batas))
            ->get([
                'a.Id_PA_Assignment as id',
                'a.Kode_Karyawan_Penilai as penilai',
                'a.PA_Rater_Category_Id as kategori_id',
                'p.ID_Level_Saat_Cycle as level_id',
            ]);

        $hasil = [
            'diminta' => $belum->count(),
            'dititipkan' => 0,
            'dilewati' => 0,
            'gagal' => 0,
            'proses' => [],
            'contoh_galat' => [],
        ];

        if ($belum->isEmpty()) {
            return $hasil;
        }

        /* Peta paket dimuat SEKALI untuk seluruh siklus. Isinya paling banyak belasan baris
           (level x kategori penilai) sementara penugasannya ribuan; menanyakannya per baris
           berarti ribuan kueri untuk jawaban yang seluruhnya muat di memori. */
        $paket = $this->petaPaket($cycleId);

        /* Karyawan diperiksa DI MUKA, sekali untuk seluruh kiriman. `store` menolak 422 bila
           ada satu saja yang belum dikenal HC Learn — dan penolakan itu membatalkan SELURUH
           kiriman, bukan barisnya saja. Menyaringnya lebih dulu membuat yang lain tetap
           berangkat, dan yang bermasalah punya sebab yang bisa dibaca. */
        $siap = $this->saringKaryawanSiap($belum->pluck('penilai')->all(), $hasil);

        $sekarang = FormatTanggalHelper::getCurrentTime();

        /* Dikelompokkan per MASTER UJIAN: satu kiriman HC Learn memuat tepat satu ujian. */
        $kelompok = [];

        foreach ($belum as $b) {
            /* Paket khusus kategori lebih dulu, lalu paket BAWAAN level (kategori NULL, berkunci
               "level|") — urutan yang sama dengan `PaQuestionBindingService::matriks()`. Tanpa
               cadangan ini langkah Paket Soal menyatakan sel tertutup oleh paket bawaan,
               sementara penjadwalan menolak setiap penugasannya "belum terikat paket soal". */
            $kunci = $b->level_id.'|'.$b->kategori_id;
            $sel = $paket[$kunci] ?? $paket[$b->level_id.'|'] ?? null;

            if ($sel === null || $sel['id_master_ujian'] === null) {
                $hasil['gagal']++;
                $this->catatGalat($hasil, sprintf(
                    'Penugasan %d: sel (level %s x kategori %s) belum terikat paket soal, jadi tidak ada ujian yang bisa dijadwalkan.',
                    $b->id, $b->level_id, $b->kategori_id,
                ));

                continue;
            }

            if (! isset($siap[trim((string) $b->penilai)])) {
                $hasil['gagal']++;

                continue;
            }

            $kelompok[$sel['id_master_ujian']]['paket_id'] = $sel['paket_id'];
            $kelompok[$sel['id_master_ujian']]['subjek'][] = [
                'assignment_id' => (int) $b->id,
                'kode' => trim((string) $b->penilai),
            ];
        }

        foreach ($kelompok as $idMasterUjian => $isi) {
            foreach (array_chunk($isi['subjek'], self::SUBJEK_PER_KIRIMAN) as $potongan) {
                /* BARISNYA LAHIR LEBIH DULU, baru kirimannya berangkat.
                   |
                   | `Id_Integrasi` yang kita kirim adalah `Id_PA_Assignment_Integration` —
                   | itulah yang `PaApiGuard` tuntut kembali pada callback. Angka itu belum
                   | ada sebelum barisnya ditulis, jadi urutannya tidak bisa dibalik.
                   |
                   | Kenapa BUKAN `Id_PA_Assignment`: satu penugasan boleh punya beberapa
                   | baris integrasi — paket soalnya diganti, pintunya dicetak ulang — dan id
                   | penugasan tidak pernah bisa menunjuk token yang mana. */
                $idBaris = $this->tulisBarisAntre($potongan, $isi['paket_id'], $jendela, $sekarang, $olehUserId);

                try {
                    $jawaban = $this->kontrak->jadwalkan(
                        (string) $idMasterUjian,
                        $jendela['dari'],
                        $jendela['sampai'],
                        array_map(fn ($s) => [
                            'Id_Integrasi' => $idBaris[$s['assignment_id']],
                            'Kode_Karyawan' => $s['kode'],
                        ], $potongan),
                    );

                    $this->tandaiDititipkan(array_values($idBaris), $jawaban, $sekarang, $olehUserId);

                    $hasil['dititipkan'] += count($potongan);
                    $hasil['proses'][] = $jawaban['process_id'];
                } catch (Throwable $e) {
                    /* DINONAKTIFKAN, bukan dihapus. Dihapus, tidak ada yang bisa menjawab
                       kenapa kirimannya gagal; dibiarkan aktif, pencarian "belum berpintu"
                       akan melewatinya selamanya dan penugasan itu tidak pernah dijadwalkan
                       ulang. Nonaktif memenuhi keduanya: jejaknya ada, dan percobaan
                       berikutnya melihatnya lagi sebagai pekerjaan yang belum selesai. */
                    $this->tandaiKirimanGagal(array_values($idBaris), $e->getMessage(), $sekarang, $olehUserId);

                    $hasil['gagal'] += count($potongan);
                    $this->catatGalat($hasil, sprintf('Ujian %s: %s', $idMasterUjian, $e->getMessage()));
                }
            }
        }

        Log::info('PA: penjadwalan dititipkan ke HC Learn', $hasil + ['cycle_id' => $cycleId, 'oleh' => $olehUserId]);

        return $hasil;
    }

    /**
     * Jendela pengisian siklus, setelah dipastikan sah untuk HC Learn.
     *
     * PUBLIK supaya tugas latar bisa menolak di MUKA, saat admin masih di layar — bukan
     * belakangan di dalam job, ketika satu-satunya tempat galatnya terbaca adalah panel aktivitas.
     *
     * @return array{dari:string, sampai:string}
     */
    public function jendelaSah(int $cycleId): array
    {
        $jendela = $this->jendelaSiklus($cycleId);

        /* Jendela yang kosong ditolak DI SINI, bukan diteruskan ke HC Learn. Mereka akan
           menolaknya juga, tetapi dengan kalimat tentang format tanggal — bukan tentang
           siklus yang belum diisi jendelanya, yang justru satu-satunya keterangan yang bisa
           dipakai admin untuk membereskannya. */
        if ($jendela['dari'] === null || $jendela['sampai'] === null) {
            throw new RuntimeException(
                "Siklus {$cycleId} belum punya jendela pengisian (Pengisian_Dari/Sampai). "
                .'HC Learn menuntut Waktu_Mulai dan Waktu_Akhir pada setiap penjadwalan. '
                .'Isi keduanya pada dialog pembukaan, atau lewat sunting siklus.'
            );
        }

        /* JENDELA YANG MUSTAHIL DITOLAK DI RUMAH SENDIRI.
           |
           | HC Learn menuntut `Waktu_Akhir` sesudah `Waktu_Mulai`, dan satu penolakan
           | membatalkan SELURUH kiriman — bukan barisnya saja. Membiarkannya berangkat berarti
           | ratusan penugasan tercatat GAGAL berbarengan atas sebab yang sudah bisa diketahui
           | sebelum permintaan pertama dibuat, dan admin membaca "HC Learn menolak" untuk
           | kesalahan yang sepenuhnya ada di data kita. */
        if (strtotime($jendela['sampai']) <= strtotime($jendela['dari'])) {
            throw new RuntimeException(sprintf(
                'Jendela pengisian siklus %d tidak sah: %s sampai %s. Tanggal selesai harus '
                .'SESUDAH tanggal mulai — HC Learn menolak jendela yang berdurasi nol atau '
                .'terbalik, dan penolakannya membatalkan seluruh kiriman. Perbaiki jendelanya '
                .'pada dialog pembukaan siklus, lalu jadwalkan lagi.',
                $cycleId,
                $jendela['dari'],
                $jendela['sampai'],
            ));
        }

        return $jendela;
    }

    /* ------------------------------------------------------------------ *
     * Mengambil hasilnya                                                  *
     * ------------------------------------------------------------------ */

    /**
     * Menarik hasil seluruh kiriman siklus ini yang barisnya masih ANTRE.
     *
     * Boleh dipanggil berkali-kali; memanggilnya tidak mengubah apa pun di sisi mereka.
     * SELESAI dari mereka TIDAK berarti semuanya berhasil — tiap baris membawa statusnya
     * sendiri, dan yang GAGAL menyimpan sebabnya supaya admin tidak perlu menebak.
     *
     * @return array{proses:int, terisi:int, masih_antre:int, gagal:int, contoh_galat:list<string>}
     */
    public function tarikStatus(int $cycleId, ?int $olehUserId = null): array
    {
        $proses = DB::table('N_PA_Assignment_Integration as i')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('i.Flag_Aktif', 'Y')
            ->where('i.Status_Integrasi', 'ANTRE')
            ->whereNotNull('i.Process_Id')
            ->distinct()
            ->pluck('i.Process_Id')
            ->all();

        $hasil = ['proses' => count($proses), 'terisi' => 0, 'masih_antre' => 0, 'gagal' => 0, 'contoh_galat' => []];

        foreach ($proses as $processId) {
            try {
                $status = $this->kontrak->status((string) $processId);

                foreach ($status['baris'] as $b) {
                    $ini = $this->tulisHasilSatu((string) $processId, $b, $olehUserId);
                    $hasil[$ini]++;
                }
            } catch (Throwable $e) {
                $this->catatGalat($hasil, sprintf('Proses %s: %s', $processId, $e->getMessage()));
            }
        }

        /* Jadwal yang baru saja bertautan bisa milik penugasan yang dibatalkan selagi ia masih
           antre — saat dibatalkan belum ada `Api_Ref` untuk dihapus. Sekaranglah saatnya. */
        if ($hasil['terisi'] > 0) {
            try {
                $this->hapusJadwalDibatalkan(cycleId: $cycleId, olehUserId: $olehUserId);
            } catch (Throwable $e) {
                Log::warning('PA: hapus jadwal dibatalkan sesudah tarik gagal', ['cycle_id' => $cycleId, 'galat' => $e->getMessage()]);
            }
        }

        return $hasil;
    }

    /* ------------------------------------------------------------------ *
     * Menghapus jadwal milik penugasan yang dibatalkan                    *
     * ------------------------------------------------------------------ */

    /** Penanda di `Pesan_Gagal` — `Status_Integrasi` hanya mengenal empat nilai (CK_PA_AsgIntg_Status). */
    public const TANDA_DIHAPUS = 'Dihapus di HC Learn: penugasan dibatalkan.';

    public const AWALAN_GAGAL_HAPUS = 'Gagal dihapus di HC Learn: ';

    /**
     * Menghapus jadwal HC Learn milik penugasan yang SUDAH DIBATALKAN.
     *
     * Tanpa ini penilai yang dibatalkan (peserta dikecualikan, penilai diganti, level
     * disegarkan) tetap memegang tautan aktif: ia bisa membuka dan mengisi lembarnya, lalu
     * kirimannya ditolak HCIS — pekerjaan yang sia-sia, dan tidak ada yang memberitahunya
     * sebelumnya. `hapus()` sudah ada di kontrak sejak awal, tetapi tidak pernah dipanggil.
     *
     * Yang dikerjakan: baris integrasi AKTIF yang penugasannya sudah nonaktif & DIBATALKAN.
     *   ada `Api_Ref`        dihapus di HC Learn, lalu barisnya dinonaktifkan.
     *   gagal dijadwalkan    tidak pernah ada di HC Learn — cukup dinonaktifkan.
     *   antre tanpa ref      ditunggu: dihapus saat hasilnya ditarik (`tarikStatus`).
     *   HC Learn menolak     baris tetap aktif dengan sebabnya — muncul di Langkah 5 untuk
     *                        dicoba lagi (mis. penilainya sudah mulai mengerjakan).
     *
     * Idempoten: baris yang sudah dinonaktifkan tidak diambil lagi.
     *
     * @param  list<int>|null  $assignmentIds  null = seluruh siklus `$cycleId`
     * @return array{dihapus:int, dinonaktifkan:int, menunggu:int, gagal:int, contoh_galat:list<string>}
     */
    public function hapusJadwalDibatalkan(?array $assignmentIds = null, ?int $cycleId = null, ?int $olehUserId = null): array
    {
        $hasil = ['dihapus' => 0, 'dinonaktifkan' => 0, 'menunggu' => 0, 'gagal' => 0, 'contoh_galat' => []];

        $baris = $this->dasarJadwalDibatalkan($assignmentIds, $cycleId)
            ->get(['i.Id_PA_Assignment_Integration as id', 'i.Api_Ref as api_ref', 'i.Status_Integrasi as status']);

        $sekarang = FormatTanggalHelper::getCurrentTime();
        $nonaktifkan = fn (int $id, string $pesan) => DB::table('N_PA_Assignment_Integration')
            ->where('Id_PA_Assignment_Integration', $id)
            ->update(['Flag_Aktif' => 'T', 'Pesan_Gagal' => $pesan, 'Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);

        foreach ($baris as $b) {
            $ref = trim((string) $b->api_ref);

            if ($ref === '') {
                if ($b->status === 'ANTRE') {
                    $hasil['menunggu']++;
                } else {
                    $nonaktifkan((int) $b->id, self::TANDA_DIHAPUS);
                    $hasil['dinonaktifkan']++;
                }

                continue;
            }

            try {
                $this->kontrak->hapus($ref);
                $nonaktifkan((int) $b->id, self::TANDA_DIHAPUS);
                $hasil['dihapus']++;
            } catch (Throwable $e) {
                DB::table('N_PA_Assignment_Integration')
                    ->where('Id_PA_Assignment_Integration', $b->id)
                    ->update([
                        'Pesan_Gagal' => mb_substr(self::AWALAN_GAGAL_HAPUS.$e->getMessage(), 0, 500),
                        'Updated_At' => $sekarang,
                        'Updated_By' => $olehUserId,
                    ]);
                $hasil['gagal']++;
                $this->catatGalat($hasil, $e->getMessage());
            }
        }

        if ($baris->isNotEmpty()) {
            Log::info('PA: jadwal HC Learn penugasan dibatalkan', $hasil + ['cycle_id' => $cycleId, 'penugasan' => $assignmentIds]);
        }

        return $hasil;
    }

    /**
     * Berapa jadwal milik penugasan yang dibatalkan yang masih hidup di HC Learn — dibaca
     * Langkah 5 supaya sisanya tidak diam-diam tertinggal.
     *
     * @return array{jumlah:int, gagal:int, contoh:?string}
     */
    public function ringkasanJadwalDibatalkan(int $cycleId): array
    {
        $baris = $this->dasarJadwalDibatalkan(null, $cycleId)->get(['i.Pesan_Gagal as pesan']);
        $gagal = $baris->filter(fn ($b) => str_starts_with((string) $b->pesan, self::AWALAN_GAGAL_HAPUS));

        return [
            'jumlah' => $baris->count(),
            'gagal' => $gagal->count(),
            'contoh' => $gagal->isEmpty() ? null : mb_substr((string) $gagal->first()->pesan, mb_strlen(self::AWALAN_GAGAL_HAPUS), 200),
        ];
    }

    private function dasarJadwalDibatalkan(?array $assignmentIds, ?int $cycleId)
    {
        return DB::table('N_PA_Assignment_Integration as i')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('i.Flag_Aktif', 'Y')
            ->where('a.Flag_Aktif', 'T')
            ->where('a.Status_Penugasan', 'DIBATALKAN')
            ->when($assignmentIds !== null, fn ($q) => $q->whereIn('a.Id_PA_Assignment', array_map('intval', $assignmentIds)))
            ->when($cycleId !== null, fn ($q) => $q->where('p.PA_Cycle_Id', $cycleId))
            ->orderBy('i.Id_PA_Assignment_Integration');
    }

    /* ------------------------------------------------------------------ *
     * Angka untuk layar                                                   *
     * ------------------------------------------------------------------ */

    /**
     * Berapa penugasan siklus ini yang sudah punya pintu, dan berapa yang belum.
     *
     * `antre` DIPISAH dari `belum`: keduanya sama-sama tanpa tautan, tetapi yang pertama
     * sudah dititipkan dan tinggal ditunggu, sementara yang kedua belum dikirim ke mana pun.
     * Menyatukannya membuat admin menekan "Jadwalkan" berulang atas pekerjaan yang sedang
     * berjalan — dan tiap tekanan berarti kiriman baru.
     *
     * @return array{total:int, terjadwal:int, antre:int, gagal:int, belum:int}
     */
    public function ringkasan(int $cycleId): array
    {
        $dasar = fn () => DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('a.Flag_Aktif', 'Y');

        $total = (int) $dasar()->count();

        $perStatus = $dasar()
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')
                    ->where('i.Flag_Aktif', '=', 'Y');
            })
            ->groupBy('i.Status_Integrasi')
            ->selectRaw('i.Status_Integrasi as nilai, COUNT(*) as jumlah')
            ->pluck('jumlah', 'nilai')
            ->all();

        $terjadwal = (int) ($perStatus['BERHASIL'] ?? 0) + (int) ($perStatus['SUDAH_ADA'] ?? 0);
        $antre = (int) ($perStatus['ANTRE'] ?? 0);
        $gagal = (int) ($perStatus['GAGAL'] ?? 0);

        return [
            'total' => $total,
            'terjadwal' => $terjadwal,
            'antre' => $antre,
            'gagal' => $gagal,
            'belum' => max(0, $total - $terjadwal - $antre - $gagal),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Mencetak ulang satu pintu                                           *
     * ------------------------------------------------------------------ */

    /**
     * Menerbitkan ULANG kode akses satu penugasan — `extend-time` dalam kontrak HC Learn.
     *
     * KENAPA EXTEND-TIME, BUKAN MENJADWALKAN LAGI. Menjadwalkan ulang menghasilkan pintu
     * KEDUA untuk penugasan yang sama; `extend-time` menerbitkan kode baru pada pintu yang
     * SUDAH ada, mematikan kode lamanya seketika, dan melepas kunci sesi perangkat supaya
     * kode baru bisa dipakai dari perangkat mana pun. Itu persis keadaan yang membuat admin
     * menekan tombol ini: penilai gagal masuk, atau jadwalnya telanjur lewat.
     *
     * BILA PAKET SELNYA BERUBAH, `update` dipanggil lebih dulu. Paket diambil dari SEL
     * penugasan ini, bukan dari baris integrasi lama: kalau paketnya baru saja disalin
     * ulang, justru yang lama itulah yang keliru.
     *
     * BARISNYA DIPERBARUI DI TEMPAT, tidak diganti baris baru. api_ref-nya tetap sama —
     * itulah identitas yang dipegang HC Learn — dan kode lamanya disimpan di jejak supaya
     * pesan yang telanjur dikirim ke penilai bisa dicabut.
     *
     * @return array{assignment_id:int, api_ref:string, short_token:?string, akses_otp:?string, link_ujian:?string, kode_lama:array<string,mixed>}
     */
    public function cetakUlangPintu(int $assignmentId, string $alasan, ?int $olehUserId = null): array
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new RuntimeException(
                'Menerbitkan ulang kode akses wajib beralasan. Penilai akan menerima kode baru '
                .'dan kode lamanya berhenti berlaku seketika; tanpa alasan tertulis, tidak ada '
                .'yang bisa menjelaskan kenapa kepadanya.'
            );
        }

        $this->gate->pastikanBolehUntukPenugasan(PaCycleGate::CETAK_ULANG_PINTU, $assignmentId);

        $penugasan = $this->penugasanUntukCetakUlang($assignmentId);

        $baris = DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $assignmentId)
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_PA_Assignment_Integration')
            ->first();

        if ($baris === null || $baris->Api_Ref === null) {
            throw new RuntimeException(
                "Penugasan {$assignmentId} belum punya pintu yang bisa dicetak ulang. "
                .($baris === null
                    ? 'Jadwalkan dulu penugasannya.'
                    : 'Pintunya masih menunggu HC Learn menyelesaikan antreannya — tarik statusnya lebih dulu.')
            );
        }

        $jendela = $this->jendelaSiklus((int) $penugasan->PA_Cycle_Id);

        /* Sama seperti penjadwalan: jendela kosong ditolak DI SINI. HC Learn juga menolaknya,
           tetapi dengan kalimat tentang waktu yang tidak dikirim — bukan tentang siklus yang
           belum diisi jendelanya. */
        if ($jendela['dari'] === null && $jendela['sampai'] === null) {
            throw new RuntimeException(
                'Siklus penugasan ini belum punya jendela pengisian, sedangkan perpanjangan '
                .'HC Learn menuntut sedikitnya satu di antara waktu mulai atau waktu akhir. '
                .'Isi Pengisian Dari dan Pengisian Sampai pada siklusnya lebih dulu.'
            );
        }

        $peta = $this->petaPaket((int) $penugasan->PA_Cycle_Id);
        $sel = $peta[$penugasan->ID_Level_Saat_Cycle.'|'.$penugasan->PA_Rater_Category_Id]
            ?? $peta[$penugasan->ID_Level_Saat_Cycle.'|'] ?? null; // cadangan paket bawaan level

        $sekarang = FormatTanggalHelper::getCurrentTime();

        /* Paket sel berubah -> alat tesnya disusun ulang lebih dulu. Menerbitkan kode baru
           tanpa ini akan memberi penilai tautan segar menuju soal yang sudah tidak berlaku. */
        if ($sel !== null && $sel['paket_id'] !== null && (int) $baris->PA_Cycle_Question_Package_Id !== (int) $sel['paket_id']) {
            $this->kontrak->perbarui((string) $baris->Api_Ref, [
                'id_master_ujian' => $sel['id_master_ujian'],
                'waktu_mulai' => $jendela['dari'],
                'waktu_akhir' => $jendela['sampai'],
            ]);
        }

        $baru = $this->kontrak->perpanjang((string) $baris->Api_Ref, $jendela['dari'], $jendela['sampai']);

        /* DIHITUNG SEKALI, dipakai dua kali: yang ditulis ke basis data DAN yang dipulangkan
           ke layar. Sampai 11 September 2026 keduanya berbeda sumber — kolomnya menyimpan
           tautan yang sudah diperbaiki, sementara layar menerima tautan MENTAH dari HC Learn.
           Admin lalu mengklik tautan yang berbunyi "Tautan Tidak Valid" tepat sesudah menekan
           cetak ulang, dan menyimpulkan pencetakannya gagal. Dua sumber untuk satu tautan
           adalah dua jawaban yang suatu hari berselisih — dan hari itu sudah datang. */
        $tautan = $this->tautanTerbaik($baris->Link_Ujian, $baru['link_ujian'] ?? null);

        $jejak = json_decode((string) $baris->Payload_Json, true) ?: [];
        $jejak['cetak_ulang'][] = [
            'pada' => $sekarang,
            'sebab' => $alasan,
            'oleh' => $olehUserId,
            'kode_lama' => [
                'short_token' => $baris->Short_Token,
                'akses_otp' => $baris->Akses_Otp,
            ],
        ];

        DB::table('N_PA_Assignment_Integration')
            ->where('Id_PA_Assignment_Integration', $baris->Id_PA_Assignment_Integration)
            ->update([
                'PA_Cycle_Question_Package_Id' => $sel['paket_id'] ?? $baris->PA_Cycle_Question_Package_Id,
                'Short_Token' => $baru['short_token'] ?? null,
                'Akses_Otp' => $baru['akses_otp'] ?? null,
                'Link_Ujian' => $tautan,
                'Waktu_Mulai' => $baru['waktu_mulai'] ?? $jendela['dari'],
                'Waktu_Akhir' => $baru['waktu_akhir'] ?? $jendela['sampai'],
                'Payload_Json' => json_encode($jejak, JSON_UNESCAPED_UNICODE),
                'Updated_At' => $sekarang,
                'Updated_By' => $olehUserId,
            ]);

        Log::info('PA: kode akses penugasan diterbitkan ulang', [
            'assignment_id' => $assignmentId,
            'api_ref' => $baris->Api_Ref,
            'sebab' => $alasan,
            'oleh' => $olehUserId,
        ]);

        return [
            'assignment_id' => $assignmentId,
            'api_ref' => (string) $baris->Api_Ref,
            'short_token' => $baru['short_token'] ?? null,
            'akses_otp' => $baru['akses_otp'] ?? null,
            'link_ujian' => $tautan,
            'kode_lama' => (array) ($baru['kode_lama'] ?? [
                'short_token' => $baris->Short_Token,
                'akses_otp' => $baris->Akses_Otp,
            ]),
        ];
    }

    /**
     * JADWALKAN ULANG satu penugasan: jadwal lamanya DIHAPUS di HC Learn, lalu dijadwalkan baru.
     *
     * Bedanya dengan cetak ulang: cetak ulang menerbitkan kode baru pada jadwal yang SAMA
     * (jawaban yang sudah diisi tetap ada). Jadwalkan ulang memulai dari NOL — untuk penilai
     * yang meninggalkan tes setengah jalan dan harus mengulang, atau jawabannya harus dibuang.
     * Karena itu boleh juga untuk yang SUDAH diisi (HC Learn mengizinkan hapusnya), dan di sisi
     * kita jawabannya ikut dinonaktifkan serta status penugasannya kembali BELUM_MULAI —
     * jawaban yang jadwalnya sudah dihapus tidak boleh tetap terhitung.
     *
     * Urutannya disengaja: HAPUS DULU di HC Learn. Bila HC Learn menolak, tidak ada yang
     * berubah di sini. Bila penjadwalan barunya yang gagal, penugasannya tinggal tanpa pintu
     * dan muncul sebagai "belum terjadwal" — bisa diulang lewat "Jadwalkan sisanya".
     *
     * @return array{assignment_id:int, jawaban_dihapus:bool, jadwal:array<string, mixed>, catatan:string}
     */
    public function jadwalkanUlangPintu(int $assignmentId, string $alasan, ?int $olehUserId = null): array
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 5) {
            throw new RuntimeException('Menjadwalkan ulang wajib beralasan (minimal 5 huruf) — jadwal dan jawaban lama dihapus, dan alasan ini satu-satunya jejaknya.');
        }

        $this->gate->pastikanBolehUntukPenugasan(PaCycleGate::CETAK_ULANG_PINTU, $assignmentId);
        $penugasan = $this->penugasanUntukCetakUlang($assignmentId, true);
        $cycleId = (int) $penugasan->PA_Cycle_Id;
        $this->jendelaSah($cycleId);

        $baris = DB::table('N_PA_Assignment_Integration')
            ->where('PA_Assignment_Id', $assignmentId)
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_PA_Assignment_Integration')
            ->first();

        if ($baris !== null && trim((string) $baris->Api_Ref) === '' && $baris->Status_Integrasi === 'ANTRE') {
            throw new RuntimeException('Jadwal penugasan ini masih diproses HC Learn — tunggu atau tarik statusnya dulu, lalu jadwalkan ulang.');
        }

        /* HAPUS DULU di HC Learn; galatnya diteruskan apa adanya dan tidak ada yang berubah. */
        if ($baris !== null && trim((string) $baris->Api_Ref) !== '') {
            $this->kontrak->hapus((string) $baris->Api_Ref);
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();
        $sudahDiisi = $penugasan->Status_Penugasan !== PaAssignmentBuilderService::STATUS_BELUM_MULAI;

        DB::transaction(function () use ($baris, $assignmentId, $alasan, $olehUserId, $sekarang, $sudahDiisi) {
            if ($baris !== null) {
                $jejak = json_decode((string) $baris->Payload_Json, true) ?: [];
                $jejak['jadwal_ulang'][] = ['pada' => $sekarang, 'sebab' => $alasan, 'oleh' => $olehUserId];
                DB::table('N_PA_Assignment_Integration')
                    ->where('Id_PA_Assignment_Integration', $baris->Id_PA_Assignment_Integration)
                    ->update([
                        'Flag_Aktif' => 'T',
                        'Pesan_Gagal' => mb_substr('Dijadwalkan ulang: '.$alasan, 0, 500),
                        'Payload_Json' => json_encode($jejak, JSON_UNESCAPED_UNICODE),
                        'Updated_At' => $sekarang,
                        'Updated_By' => $olehUserId,
                    ]);
            }

            if ($sudahDiisi) {
                /* Skor tersambung lewat kiriman (payload) milik baris integrasi penugasan ini. */
                DB::table('N_PA_Assignment_Score')
                    ->whereIn('PA_Assignment_Payload_Id', DB::table('N_PA_Assignment_Payload as pl')
                        ->join('N_PA_Assignment_Integration as i', 'i.Id_PA_Assignment_Integration', '=', 'pl.PA_Assignment_Integration_Id')
                        ->where('i.PA_Assignment_Id', $assignmentId)
                        ->select('pl.Id_PA_Assignment_Payload'))
                    ->where('Flag_Aktif', 'Y')
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);
                DB::table('N_PA_Assignment')
                    ->where('Id_PA_Assignment', $assignmentId)
                    ->update([
                        'Status_Penugasan' => PaAssignmentBuilderService::STATUS_BELUM_MULAI,
                        'Started_At' => null,
                        'Finished_At' => null,
                        'Updated_At' => $sekarang,
                        'Updated_By' => $olehUserId,
                    ]);
            }
        });

        $jadwal = $this->jadwalkanSiklus($cycleId, $olehUserId, 0, [$assignmentId]);
        $gagal = (int) ($jadwal['gagal'] ?? 0) + (int) ($jadwal['dilewati'] ?? 0) > 0 || (int) ($jadwal['dititipkan'] ?? 0) === 0;

        Log::info('PA: penugasan dijadwalkan ulang', [
            'assignment_id' => $assignmentId, 'api_ref_lama' => $baris->Api_Ref ?? null,
            'jawaban_dihapus' => $sudahDiisi, 'sebab' => $alasan, 'oleh' => $olehUserId,
        ]);

        return [
            'assignment_id' => $assignmentId,
            'jawaban_dihapus' => $sudahDiisi,
            'jadwal' => $jadwal,
            'catatan' => $gagal
                ? 'Jadwal lama sudah dihapus, tetapi jadwal baru belum terkirim'
                    .(($jadwal['contoh_galat'] ?? []) !== [] ? ': '.implode('; ', array_slice($jadwal['contoh_galat'], 0, 2)) : '')
                    .'. Ulangi lewat "Jadwalkan sisanya".'
                : 'Jadwal baru dikirim ke HC Learn; tautan akses muncul sesudah HC Learn memprosesnya.'
                    .($sudahDiisi ? ' Jawaban lama sudah dihapus — penilai mengisi dari awal.' : ''),
        ];
    }

    /* ------------------------------------------------------------------ *
     * Jalur dalam                                                         *
     * ------------------------------------------------------------------ */

    /**
     * Menyaring penilai yang benar-benar bisa dijadwalkan, dan mencatat sebab yang tidak.
     *
     * @param  list<string>  $kode
     * @param  array<string, mixed>  $hasil
     * @return array<string, true>
     */
    private function saringKaryawanSiap(array $kode, array &$hasil): array
    {
        $unik = array_values(array_unique(array_map('trim', $kode)));
        $siap = [];

        /* Pemotongannya milik KONTRAK, bukan di sini.
           |
           | Sampai 10 September 2026 daftarnya dipotong dua kali: seratus di sini, dua ratus
           | di kontraknya. Dua tempat yang memutuskan batas yang sama akan berselisih begitu
           | HC Learn menggeser angkanya — dan yang di sini justru tidak akan ikut diperbarui,
           | karena tidak ada yang mengira batas kontrak ditulis di luar kelas kontraknya. */
        foreach ($this->kontrak->periksaKaryawan($unik) as $k => $h) {
            if ($h['siap']) {
                $siap[$k] = true;

                continue;
            }

            $this->catatGalat($hasil, sprintf('Penilai %s tidak bisa dijadwalkan (%s): %s', $k, $h['alasan'], $h['keterangan']));
        }

        return $siap;
    }

    /**
     * Membuat baris ANTRE SEBELUM kirimannya berangkat, dan memulangkan id-nya.
     *
     * Urutannya tidak bisa dibalik: `Id_Integrasi` yang dikirim ke HC Learn adalah
     * `Id_PA_Assignment_Integration`, dan angka itu baru ada sesudah barisnya ditulis.
     *
     * `Process_Id` sengaja belum diisi di sini — ia baru diketahui dari jawaban `store`.
     * Baris ANTRE tanpa `Process_Id` karena itu berarti kirimannya belum sempat berangkat
     * atau jawabannya tidak sampai; keadaan itu terbaca apa adanya alih-alih menyamar
     * sebagai antrean yang sedang digarap.
     *
     * @param  list<array{assignment_id:int, kode:string}>  $subjek
     * @param  array{dari:?string, sampai:?string}  $jendela
     * @return array<int, int> peta id penugasan => id baris integrasi
     */
    private function tulisBarisAntre(array $subjek, ?int $paketId, array $jendela, string $sekarang, ?int $olehUserId): array
    {
        $peta = [];

        foreach (array_chunk($subjek, self::POTONGAN) as $potongan) {
            $baris = [];

            foreach ($potongan as $s) {
                $baris[] = [
                    'PA_Assignment_Id' => $s['assignment_id'],
                    'PA_Cycle_Question_Package_Id' => $paketId,
                    'Status_Integrasi' => 'ANTRE',
                    'Waktu_Mulai' => $jendela['dari'],
                    'Waktu_Akhir' => $jendela['sampai'],
                    'Payload_Json' => json_encode([
                        'dititipkan_pada' => $sekarang,

                        /* Jendela yang berlaku SAAT dititipkan. Bukan hiasan: sejak
                           penggeseran jendela di tengah siklus diizinkan, inilah
                           satu-satunya cara mengetahui pintu mana yang masih membawa
                           tanggal lama. */
                        'jendela_dari' => $jendela['dari'],
                        'jendela_sampai' => $jendela['sampai'],
                    ], JSON_UNESCAPED_UNICODE),
                    'Flag_Aktif' => 'Y',
                    'Created_At' => $sekarang,
                    'Created_By' => $olehUserId,
                ];
            }

            DB::table('N_PA_Assignment_Integration')->insert($baris);
        }

        /* Id-nya dibaca kembali lewat penugasannya, bukan dari nilai balik `insert()` —
           penyisipan massal tidak memulangkan id, dan menyisipkan satu per satu demi id
           berarti satu perjalanan per penilai. Barisnya baru saja lahir dan satu penugasan
           hanya boleh punya satu baris AKTIF, jadi pencarian ini tidak ambigu. */
        foreach (array_chunk(array_map(fn ($s) => $s['assignment_id'], $subjek), 500) as $potongan) {
            foreach (DB::table('N_PA_Assignment_Integration')
                ->whereIn('PA_Assignment_Id', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Id_PA_Assignment_Integration')
                ->get(['Id_PA_Assignment_Integration', 'PA_Assignment_Id']) as $r) {
                $peta[(int) $r->PA_Assignment_Id] = (int) $r->Id_PA_Assignment_Integration;
            }
        }

        return $peta;
    }

    /**
     * Menyimpan `process_id` pada baris yang kirimannya sudah diterima HC Learn.
     *
     * @param  list<int>  $idBaris
     * @param  array<string, mixed>  $jawaban
     */
    private function tandaiDititipkan(array $idBaris, array $jawaban, string $sekarang, ?int $olehUserId): void
    {
        foreach (array_chunk($idBaris, self::POTONGAN) as $potongan) {
            DB::table('N_PA_Assignment_Integration')
                ->whereIn('Id_PA_Assignment_Integration', $potongan)
                ->update([
                    'Process_Id' => $jawaban['process_id'],
                    'Payload_Json' => json_encode([
                        'process_id' => $jawaban['process_id'],
                        'id_master_ujian' => $jawaban['id_master_ujian'] ?? null,
                        'nama_ujian' => $jawaban['nama_ujian'] ?? null,
                        'dititipkan_pada' => $sekarang,
                    ], JSON_UNESCAPED_UNICODE),
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);
        }
    }

    /**
     * Menonaktifkan baris yang kirimannya tidak jadi berangkat.
     *
     * Sebabnya disimpan, dan barisnya diturunkan dari aktif supaya pencarian "belum
     * berpintu" melihatnya lagi pada percobaan berikutnya. Dibiarkan aktif, penugasan itu
     * tidak akan pernah dijadwalkan ulang — dan tidak ada satu pun gejala selain penilai
     * yang tak kunjung menerima tautannya.
     *
     * @param  list<int>  $idBaris
     */
    private function tandaiKirimanGagal(array $idBaris, string $sebab, string $sekarang, ?int $olehUserId): void
    {
        foreach (array_chunk($idBaris, self::POTONGAN) as $potongan) {
            DB::table('N_PA_Assignment_Integration')
                ->whereIn('Id_PA_Assignment_Integration', $potongan)
                ->update([
                    'Status_Integrasi' => 'GAGAL',
                    'Pesan_Gagal' => mb_substr($sebab, 0, 500),
                    'Flag_Aktif' => 'T',
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);
        }
    }

    /**
     * Menulis hasil satu subjek, dan memulangkan bucket hitungannya.
     *
     * @param  array<string, mixed>  $b
     * @return 'terisi'|'masih_antre'|'gagal'
     */
    private function tulisHasilSatu(string $processId, array $b, ?int $olehUserId): string
    {
        /* `Id_Integrasi` yang mereka kembalikan adalah `Id_PA_Assignment_Integration` —
           angka yang KITA kirim saat menjadwalkan, dipulangkan apa adanya. Membacanya
           sebagai id penugasan akan menyasar baris yang salah pada penugasan yang punya
           lebih dari satu baris integrasi, dan menyasar TIDAK ADA baris pada yang lain. */
        $integrationId = (int) ($b['Id_Integrasi'] ?? 0);
        $status = (string) ($b['status'] ?? 'ANTRE');

        if ($integrationId <= 0) {
            return 'gagal';
        }

        if ($status === 'ANTRE') {
            return 'masih_antre';
        }

        DB::table('N_PA_Assignment_Integration')
            ->where('Id_PA_Assignment_Integration', $integrationId)
            ->where('Process_Id', $processId)
            ->where('Flag_Aktif', 'Y')
            ->update(array_filter([
                'Status_Integrasi' => $status,
                'Api_Ref' => $b['api_ref'] ?? null,
                'Short_Token' => $b['short_token'] ?? null,
                'Akses_Otp' => $b['akses_otp'] ?? null,
                'Link_Ujian' => $b['link_ujian'] ?? null,
                'Waktu_Mulai' => $b['waktu_mulai'] ?? null,
                'Waktu_Akhir' => $b['waktu_akhir'] ?? null,

                /* Sebabnya HANYA ada pada GAGAL, dan disimpan apa adanya: hanya HC Learn
                   yang tahu kenapa satu subjek tidak jadi diterbitkan. */
                'Pesan_Gagal' => $status === 'GAGAL' ? ($b['pesan'] ?? 'Tidak disebutkan.') : null,
                'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                'Updated_By' => $olehUserId,
            ], fn ($v) => $v !== null));

        return $status === 'GAGAL' ? 'gagal' : 'terisi';
    }

    private function penugasanUntukCetakUlang(int $assignmentId, bool $bolehSudahDiisi = false): object
    {
        $penugasan = DB::table('N_PA_Assignment as a')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('a.Id_PA_Assignment', $assignmentId)
            ->where('a.Flag_Aktif', 'Y')
            ->first([
                'a.Id_PA_Assignment', 'a.Status_Penugasan', 'a.PA_Rater_Category_Id',
                'p.Id_PA_Participant', 'p.PA_Cycle_Id', 'p.Status_Peserta', 'p.ID_Level_Saat_Cycle',
            ]);

        if ($penugasan === null) {
            throw new RuntimeException("Penugasan {$assignmentId} tidak ada.");
        }

        if ($penugasan->Status_Penugasan === 'DIBATALKAN') {
            throw new RuntimeException(
                "Penugasan {$assignmentId} sudah dibatalkan; menerbitkan ulang kodenya tidak "
                .'menghidupkannya kembali. Tugaskan ulang penilainya bila memang perlu.'
            );
        }

        if ($penugasan->Status_Peserta !== 'DIIKUTKAN') {
            throw new RuntimeException(sprintf(
                'Penugasan %d milik peserta berstatus %s. Menerbitkan pintu bagi peserta yang tidak '
                .'diikutkan berarti mengundang penilaian yang tidak akan dipakai siapa pun.',
                $assignmentId,
                $penugasan->Status_Peserta,
            ));
        }

        if (! $bolehSudahDiisi && in_array($penugasan->Status_Penugasan, self::STATUS_TAK_BOLEH_CETAK_ULANG, true)) {
            throw new RuntimeException(sprintf(
                'Penugasan %d berstatus %s — penilainya sudah menyetorkan pekerjaannya. '
                .'Menerbitkan ulang kodenya sekarang membuang jawaban yang sudah ditulis '
                .'orang. Batalkan penugasannya lalu tugaskan ulang bila memang harus dinilai ulang.',
                $assignmentId,
                $penugasan->Status_Penugasan,
            ));
        }

        /* Cerminan penjaga yang sama pada penambahan penilai dan pengeluaran peserta.
           Sesudah terbit, angkanya sudah dilihat orang. */
        $sudahTerbit = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $penugasan->Id_PA_Participant)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Hasil', 'TERBIT')
            ->exists();

        if ($sudahTerbit) {
            throw new RuntimeException(
                "Hasil peserta penugasan {$assignmentId} sudah TERBIT. Menerbitkan ulang "
                .'kodenya sekarang membuat nilai yang sudah dilihat orang berubah kelak '
                .'tanpa pemberitahuan.'
            );
        }

        return $penugasan;
    }

    /**
     * Tautan yang BISA DIBUKA, bukan sekadar tautan yang terakhir dikirim.
     *
     * HC Learn memulangkan jalur yang BERBEDA pada dua endpoint mereka untuk pintu yang sama:
     * `status` penjadwalan memberi `/evo/rekrutmen?wo_aut=...&otp=...`, sedangkan `extend-time`
     * memberi `/evo/third-party?wo_aut=...&otp=...`. Token dan OTP-nya identik; yang berbeda
     * hanya jalurnya — dan yang kedua menjawab "tidak ditemukan" ketika dibuka penilai.
     *
     * Akibatnya nyata dan sunyi: mencetak ulang pintu BERHASIL, kode barunya sah, tetapi
     * tautan yang tersimpan menjadi tautan mati. Penilai mengklik, melihat halaman tak
     * ditemukan, dan admin menyimpulkan pencetakan ulangnya gagal — padahal yang gagal hanya
     * alamatnya. Terjadi pada 11 September 2026, dan dibuktikan dengan menyadap jawaban
     * mentah kedua endpoint itu.
     *
     * Karena itu JALUR diambil dari tautan LAMA — yang memang terbukti bisa dibuka — dan hanya
     * kode barunya yang dipasang. Ini penambalan atas cacat mitra, bukan aturan kita: begitu
     * `extend-time` mereka memulangkan jalur yang benar, keduanya sama dan method ini berhenti
     * mengubah apa pun dengan sendirinya. Bila tautan lama tidak ada atau hostnya berbeda,
     * yang dipakai tetap milik mereka apa adanya — menebak alamat sistem orang lain lebih
     * berbahaya daripada menyimpan alamat yang salah.
     */
    private function tautanTerbaik(?string $lama, ?string $baru): ?string
    {
        if ($baru === null || $lama === null) {
            return $baru;
        }

        $a = parse_url($lama);
        $b = parse_url($baru);

        if (($a['host'] ?? null) === null || ($a['host'] ?? null) !== ($b['host'] ?? null)) {
            return $baru;
        }

        if (($a['path'] ?? '') === '' || ($a['path'] ?? '') === ($b['path'] ?? '')) {
            return $baru;
        }

        return ($a['scheme'] ?? 'https').'://'.$a['host']
            .(isset($a['port']) ? ':'.$a['port'] : '')
            .$a['path']
            .(isset($b['query']) ? '?'.$b['query'] : '');
    }

    /**
     * Jendela pengisian siklus, dalam bentuk yang dimengerti HC Learn.
     *
     * `Pengisian_Sampai` DIBENTANGKAN KE AKHIR HARI, dan itu bukan kemudahan — itu perbaikan
     * cacat. Kedua tanggalnya diisi lewat pemilih TANGGAL (tanpa jam) di dua dialog siklus,
     * jadi tersimpan sebagai tengah malam. "Pengisian 10 September sampai 10 September" yang
     * dimaksud admin adalah SEHARI PENUH, tetapi yang terkirim dulu `00:00:00` pada kedua
     * ujungnya — jendela berdurasi nol. HC Learn menolaknya 422 dengan
     * `Waktu_Akhir: "harus sesudah Waktu_Mulai"`, dan karena satu penolakan membatalkan
     * seluruh kiriman, 400 penugasan siklus PA-2026-01 tercatat GAGAL sekaligus.
     *
     * Hanya yang BERJAM TENGAH MALAM yang dibentangkan. Bila suatu hari kolomnya benar-benar
     * berisi jam — misalnya ditutup pukul 17.00 — jam itu milik admin dan tidak boleh digeser
     * diam-diam menjadi tengah malam.
     *
     * @return array{dari:?string, sampai:?string}
     */
    public function jendelaSiklus(int $cycleId): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Id_PA_Cycle', $cycleId)
            ->first(['Pengisian_Dari', 'Pengisian_Sampai']);

        return [
            'dari' => $siklus?->Pengisian_Dari === null ? null : (string) $siklus->Pengisian_Dari,
            'sampai' => $siklus?->Pengisian_Sampai === null
                ? null
                : $this->akhirHariBilaTanggalSaja((string) $siklus->Pengisian_Sampai),
        ];
    }

    /** Tengah malam berarti "tanggal", dan tanggal penutup berakhir pada detik terakhirnya. */
    private function akhirHariBilaTanggalSaja(string $waktu): string
    {
        $cap = strtotime($waktu);

        if ($cap === false) {
            return $waktu;
        }

        return date('H:i:s', $cap) === '00:00:00'
            ? date('Y-m-d', $cap).' 23:59:59'
            : date('Y-m-d H:i:s', $cap);
    }

    /**
     * Peta sel siklus, berkunci "levelId|kategoriId".
     *
     * Kunci yang tidak ada berarti sel itu memang tidak terikat paket — dan itu dibiarkan
     * kosong, bukan diisi paket kategori lain. Menebaknya membuat penilai mengisi soal yang
     * tidak pernah ditetapkan untuknya, sementara nilainya tetap terhitung.
     *
     * @return array<string, array{paket_id:int, id_master_ujian:?string}>
     */
    private function petaPaket(int $cycleId): array
    {
        $peta = [];

        foreach (DB::table('N_PA_Cycle_Question_Package')
            ->where('PA_Cycle_Id', $cycleId)
            ->where('Flag_Aktif', 'Y')
            ->get(['ID_Level', 'PA_Rater_Category_Id', 'Id_PA_Cycle_Question_Package', 'Id_Master_Ujian']) as $r) {
            $peta[$r->ID_Level.'|'.$r->PA_Rater_Category_Id] = [
                'paket_id' => (int) $r->Id_PA_Cycle_Question_Package,
                'id_master_ujian' => $r->Id_Master_Ujian === null ? null : (string) $r->Id_Master_Ujian,
            ];
        }

        return $peta;
    }

    /**
     * Contoh galat dibatasi lima: daftar yang memuat tiga ribu baris yang sama tidak lebih
     * memberi tahu daripada lima, dan membuat jawabannya tidak bisa dibaca di layar.
     *
     * @param  array<string, mixed>  $hasil
     */
    private function catatGalat(array &$hasil, string $pesan): void
    {
        if (count($hasil['contoh_galat']) < 5) {
            $hasil['contoh_galat'][] = $pesan;
        }
    }
}
