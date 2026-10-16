<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menyunting keikutsertaan satu peserta — jalur manual di samping penyiapan otomatis.
 *
 * Penyiapan siklus menentukan status awal setiap orang. Service ini untuk yang tidak bisa
 * diputuskan mesin: karyawan yang sedang cuti panjang, yang baru masuk dua minggu lalu,
 * atau yang levelnya memang belum punya aturan dan admin memilih mengecualikannya
 * ketimbang menunggu masternya dibereskan.
 *
 * TIGA STATUS, DAN HANYA SATU YANG BISA DIPILIH BEBAS
 *
 *  - `DIKECUALIKAN` — keputusan manusia. Selalu boleh, selalu wajib beralasan.
 *  - `DIIKUTKAN` — hanya bila levelnya PUNYA salinan aturan penilai. Tanpa itu, orangnya
 *    akan masuk siklus tanpa aturan siapa menilai siapa, dan itu bukan keikutsertaan
 *    melainkan lubang yang baru ketahuan saat penugasan dibentuk.
 *  - `TERTUNDA` — bukan pilihan, melainkan KEADAAN: artinya "levelnya belum bisa
 *    diresolusi". Menetapkannya dengan tangan berarti mengarang penghalang yang tidak
 *    ada, dan gerbang penguncian akan menolak siklusnya karena penghalang karangan itu.
 *    Karena itu service ini menolaknya sebagai tujuan.
 *
 * MENGECUALIKAN ORANG YANG SUDAH PUNYA PENUGASAN
 *
 * Penugasannya ikut dibatalkan, dan itu bukan kerapian melainkan syarat. Penugasan yang
 * ditinggalkan hidup akan tetap muncul di daftar tugas penilainya, menagih pekerjaan
 * untuk orang yang sudah tidak ada di siklus — dan penilai yang mengerjakannya akan
 * mengira dirinya membantu, padahal hasilnya tidak akan dipakai siapa pun.
 *
 * DUA ARAH, DUA GERBANG
 *
 * Sejak 6 September 2026 pengecualian dijaga `KECUALIKAN_PESERTA` (terbuka sampai `OPEN`),
 * sedangkan pengikutan kembali tetap `SUNTING_PESERTA` (berhenti di `PREPARED`). Bukan
 * ketidaksimetrisan yang kelupaan: mengeluarkan orang justru dibutuhkan sesudah pengisian
 * dibuka, sementara MENGIKUTKAN orang di sana melahirkan peserta tanpa satu pun penilai —
 * pembentukan penugasan sudah berhenti, dan ia tidak punya cara mendapatkannya.
 */
class PaParticipantEditService
{
    public const STATUS_DIIKUTKAN = 'DIIKUTKAN';

    public const STATUS_DIKECUALIKAN = 'DIKECUALIKAN';

    public const STATUS_TERTUNDA = 'TERTUNDA';

    /** Status yang boleh diminta lewat jalur manual. `TERTUNDA` sengaja tidak ada. */
    public const STATUS_DAPAT_DIPILIH = [self::STATUS_DIIKUTKAN, self::STATUS_DIKECUALIKAN];

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaAssignmentBuilderService $penugasan,
    ) {}

    /**
     * Mengubah status keikutsertaan satu peserta.
     *
     * @return array<string, mixed>
     */
    public function ubahStatus(
        int $participantId,
        string $status,
        string $alasan,
        int $olehUserId,
    ): array {
        $status = strtoupper(trim($status));
        $alasan = trim($alasan);

        if (! in_array($status, self::STATUS_DAPAT_DIPILIH, true)) {
            throw new RuntimeException(sprintf(
                "Status '%s' tidak bisa ditetapkan manual. Yang bisa: %s. %s",
                $status,
                implode(', ', self::STATUS_DAPAT_DIPILIH),
                $status === self::STATUS_TERTUNDA
                    ? 'TERTUNDA bukan pilihan melainkan keadaan — artinya level orang ini '
                        .'belum punya aturan penilai. Perbaiki aturan levelnya, atau '
                        .'kecualikan orangnya.'
                    : '',
            ));
        }

        /* DUA GERBANG, KARENA DUA ARAHNYA BUKAN SATU PERTANYAAN.

           Mengeluarkan orang dibutuhkan justru sesudah pengisian dibuka — peserta resign,
           dipindah, cuti panjang — dan penugasannya yang ditinggalkan hidup akan menagih
           pekerjaan untuk orang yang tidak akan dinilai. Karena itu `KECUALIKAN_PESERTA`
           terbuka sampai OPEN.

           MENGIKUTKAN kembali tetap berhenti di PREPARED, dan itu bukan kelupaan:
           pembentukan penugasan (`BUAT_PENUGASAN`) memang berhenti di sana, sehingga orang
           yang diikutkan di tengah siklus lahir tanpa satu pun penilai dan tidak punya cara
           mendapatkannya. Ia akan menggantung menunggu angka yang tak pernah bisa dihitung
           — dan itu lebih buruk daripada tetap dikecualikan. */
        // Satu-satunya pengecualian gerbang: lihat bolehDikecualikanSesudahDitutup().
        if (! ($status === self::STATUS_DIKECUALIKAN && $this->bolehDikecualikanSesudahDitutup($participantId))) {
            $this->gate->pastikanBolehUntukPeserta(
                $status === self::STATUS_DIKECUALIKAN
                    ? PaCycleGate::KECUALIKAN_PESERTA
                    : PaCycleGate::SUNTING_PESERTA,
                $participantId,
            );
        }

        return DB::transaction(function () use ($participantId, $status, $alasan, $olehUserId) {
            $peserta = DB::table('N_PA_Participant')
                ->where('Id_PA_Participant', $participantId)
                ->lockForUpdate()
                ->first([
                    'Id_PA_Participant', 'PA_Cycle_Id', 'Nama_Karyawan_Saat_Cycle',
                    'ID_Level_Saat_Cycle', 'Status_Peserta',
                    'PA_Cycle_Rater_Policy_Snapshot_Id', 'Flag_Aktif',
                ]);

            if ($peserta === null) {
                throw new RuntimeException("Peserta {$participantId} tidak ada.");
            }

            if ($peserta->Status_Peserta === $status) {
                // Bukan galat. Dua orang menekan tombol yang sama, atau satu orang
                // menekannya dua kali — keduanya bukan kesalahan yang perlu diteriakkan.
                return [
                    'participant_id' => $participantId,
                    'status_lama' => $status,
                    'status_baru' => $status,
                    'berubah' => false,
                    'penugasan_dibatalkan' => 0,
                    'pesan' => 'Status peserta sudah seperti itu; tidak ada yang diubah.',
                ];
            }

            if ($status === self::STATUS_DIKECUALIKAN && $alasan === '') {
                throw new RuntimeException(
                    'Mengecualikan peserta wajib beralasan. Enam bulan kemudian, satu-satunya '
                    .'penjelasan kenapa orang ini tidak dinilai adalah baris ini.'
                );
            }

            if ($status === self::STATUS_DIIKUTKAN) {
                $this->pastikanLevelPunyaAturan($peserta);
            }

            if ($status === self::STATUS_DIKECUALIKAN) {
                $this->pastikanHasilnyaBelumTerbit($peserta);
            }

            $dibatalkan = $status === self::STATUS_DIKECUALIKAN
                ? $this->batalkanPenugasan($peserta, $alasan, $olehUserId)
                : 0;

            DB::table('N_PA_Participant')
                ->where('Id_PA_Participant', $participantId)
                ->update([
                    'Status_Peserta' => $status,
                    /* Alasan hanya bermakna pada pengecualian. Saat orangnya diikutkan
                       kembali, alasan lamanya DIBERSIHKAN — meninggalkannya membuat baris
                       yang berbunyi "diikutkan" sekaligus "dikecualikan karena cuti", dan
                       pembacanya tidak punya cara tahu mana yang berlaku. */
                    'Alasan_Dikecualikan' => $status === self::STATUS_DIKECUALIKAN ? $alasan : null,
                    'Updated_At' => now()->format('Y-m-d H:i:s'),
                    'Updated_By' => $olehUserId,
                ]);

            return [
                'participant_id' => $participantId,
                'nama' => $peserta->Nama_Karyawan_Saat_Cycle,
                'status_lama' => $peserta->Status_Peserta,
                'status_baru' => $status,
                'berubah' => true,
                'penugasan_dibatalkan' => $dibatalkan,
                'pesan' => $dibatalkan > 0
                    ? "Peserta dikecualikan, dan {$dibatalkan} penugasan penilaian ikut "
                        .'dibatalkan supaya tidak menagih pekerjaan yang tidak akan dipakai.'
                    : 'Status peserta diperbarui.',
            ];
        });
    }

    /**
     * Mengubah status banyak peserta sekaligus.
     *
     * Dijalankan satu per satu lewat `ubahStatus()`, BUKAN sebagai satu UPDATE massal:
     * tiap baris punya penjaganya sendiri (level yang punya aturan, penugasan yang harus
     * ikut dibatalkan), dan UPDATE massal akan melewati semuanya diam-diam.
     *
     * @param  list<int>  $participantIds
     * @return array<string, mixed>
     */
    public function ubahStatusBanyak(
        array $participantIds,
        string $status,
        string $alasan,
        int $olehUserId,
    ): array {
        $berhasil = [];
        $gagal = [];

        foreach (array_unique($participantIds) as $id) {
            try {
                $berhasil[] = $this->ubahStatus((int) $id, $status, $alasan, $olehUserId);
            } catch (RuntimeException $e) {
                /* Yang gagal dikumpulkan, bukan menghentikan sisanya. Menghentikan seluruh
                   proses karena satu orang berarti admin harus menebak sudah sampai mana
                   — dan mengulang dari awal atas baris yang sudah berhasil. */
                $gagal[] = ['participant_id' => (int) $id, 'sebab' => $e->getMessage()];
            }
        }

        return [
            'diminta' => count(array_unique($participantIds)),
            'berhasil' => count($berhasil),
            'gagal' => count($gagal),
            'rincian_gagal' => $gagal,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Peserta hanya boleh DIIKUTKAN bila levelnya punya salinan aturan penilai.
     *
     * Pemeriksaannya lewat kolom penunjuk di baris pesertanya sendiri, bukan dengan
     * membaca tabel salinan — tabel itu milik `PaParticipantRuleResolver` (K32).
     */
    private function pastikanLevelPunyaAturan(object $peserta): void
    {
        if ($peserta->PA_Cycle_Rater_Policy_Snapshot_Id !== null) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Peserta %s tidak bisa diikutkan: levelnya belum punya salinan aturan penilai '
            .'pada siklus ini, sehingga tidak ada yang menentukan siapa menilai siapa. '
            .'Lengkapi aturan penilai untuk level itu lalu siapkan ulang siklusnya, atau '
            .'kecualikan orang ini.',
            $peserta->Nama_Karyawan_Saat_Cycle,
        ));
    }

    /**
     * Hasil yang sudah TERBIT tidak boleh dikecualikan.
     *
     * Cerminan penjaga yang sama di `PaAssignmentBuilderService::tugaskan()`. Sesudah
     * terbit, orangnya sudah melihat nilainya; mengeluarkannya dari siklus berarti angka
     * itu lenyap dari riwayatnya tanpa ada yang pernah mengatakannya. Yang benar adalah
     * membatalkan penerbitannya lebih dulu, lewat jalur yang punya jejaknya sendiri.
     *
     * Penjaga ini berlaku pada SEMUA status siklus, bukan hanya yang berjalan: gerbangnya
     * menjawab "status siklus mengizinkan apa", sedangkan ini pertanyaan tentang satu
     * orang, dan hasil bisa terbit lebih dulu daripada siklusnya.
     */
    /**
     * SESUDAH PENGISIAN DITUTUP, YANG TIDAK PUNYA ANGKA BOLEH DIKELUARKAN (14 Okt 2026).
     *
     * `tutupPenerbitan()` menolak selama ada hasil tertahan atau belum dihitung, sementara
     * `KECUALIKAN_PESERTA` berhenti di OPEN. Peserta yang tertahan PERMANEN — tanpa akun KPI,
     * misalnya — membuat siklus tidak pernah bisa PUBLISHED kecuali admin menemukan jalan
     * memutar: buka pengisian lagi, kecualikan, tutup lagi. Pada siklus ribuan orang,
     * orang seperti itu hampir pasti ada.
     *
     * Jadi di CLOSED pengecualian diizinkan, tetapi HANYA untuk hasil yang memang tidak punya
     * angka (TERTAHAN / BELUM_DIHITUNG). Hasil yang sudah dihitung, terbit, atau dibaca leader
     * tetap tertutup — mengeluarkannya berarti menghapus angka yang sudah ada. Alasannya
     * tetap wajib dan tercatat seperti pengecualian biasa.
     */
    private function bolehDikecualikanSesudahDitutup(int $participantId): bool
    {
        $peserta = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->leftJoin('N_PA_Result as r', function ($j) {
                $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('r.Flag_Aktif', '=', 'Y');
            })
            ->where('p.Id_PA_Participant', $participantId)
            ->first(['c.Status_Cycle', 'r.Status_Hasil', 'r.Published_Leader_At']);

        return $peserta !== null
            && $peserta->Status_Cycle === 'CLOSED'
            && in_array($peserta->Status_Hasil, ['TERTAHAN', 'BELUM_DIHITUNG'], true)
            && $peserta->Published_Leader_At === null;
    }

    private function pastikanHasilnyaBelumTerbit(object $peserta): void
    {
        $terbit = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $peserta->Id_PA_Participant)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Hasil', 'TERBIT')
            ->exists();

        if (! $terbit) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Hasil peserta %s sudah TERBIT. Mengecualikannya sekarang menghapus nilai yang '
            .'sudah dilihat orang dari riwayatnya tanpa pemberitahuan. Batalkan '
            .'penerbitannya lebih dulu bila memang perlu dikeluarkan.',
            $peserta->Nama_Karyawan_Saat_Cycle,
        ));
    }

    /**
     * Membatalkan seluruh penugasan aktif milik peserta ini.
     *
     * Lewat `PaAssignmentBuilderService::batalkan()`, bukan UPDATE langsung: pembatalan
     * punya urusannya sendiri — menonaktifkan baris supaya penilai yang sama bisa
     * ditugaskan lagi kelak, dan menghitung ulang bobot dalam kategorinya. Menyalin
     * sepotong ke sini akan membuat pintu kedua yang harus dijaga sama persis.
     */
    private function batalkanPenugasan(object $peserta, string $alasan, int $olehUserId): int
    {
        /* Yang SUDAH DIISI dilewati, bukan dibatalkan. `batalkan()` menolaknya (jawaban yang
           sudah masuk tidak boleh dibuang), dan sebelum 13 Okt 2026 penolakan itu menggagalkan
           SELURUH pengecualian: peserta yang satu saja penilainya sudah mengisi tidak bisa
           dikecualikan sama sekali. Jawabannya tetap tercatat; pesertanya tidak dihitung. */
        $penugasan = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $peserta->Id_PA_Participant)
            ->where('Flag_Aktif', 'Y')
            ->where('Status_Penugasan', '<>', 'DIBATALKAN')
            ->whereNotIn('Status_Penugasan', PaStatusPenugasan::SUDAH_DIKERJAKAN)
            ->pluck('Id_PA_Assignment');

        foreach ($penugasan as $id) {
            $this->penugasan->batalkan(
                (int) $id,
                sprintf('Peserta dikecualikan dari siklus: %s', $alasan),
                $olehUserId,
            );
        }

        return $penugasan->count();
    }
}
