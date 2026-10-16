<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Menandai satu kategori penilai SENGAJA tidak diikutkan bagi satu peserta.
 *
 * MASALAH YANG DITUTUP (UAT 13 Okt 2026)
 *
 * Peserta yang penilainya di bawah minimal (Andre: satu rekan sejawat, minimal dua) lolos
 * dikunci. Kategorinya lalu gugur diam-diam saat dihitung, porsinya dibagikan ke kategori
 * lain, dan admin baru tahu sesudah hasilnya terbit — atau tidak pernah. Kini penguncian
 * ditahan sampai setiap kekurangan diputuskan: ditambah penilainya, atau ditandai di sini.
 *
 * APA YANG DILAKUKAN TANDA INI
 *
 * 1. Kategorinya dicabut bagi peserta itu lewat override `Flag_Dipakai = 'T'` (K32) dengan
 *    alasan wajib — jalur penyimpangan yang sudah ada, dibaca resolver yang sama dengan
 *    mesin hitung, jadi tidak ada aturan kedua.
 * 2. Bobot kategori lain dibagi ulang proporsional dengan `PaBobotGuard::redistribusi` —
 *    rumus yang sama dengan pengguguran saat dihitung, jadi angkanya tidak bergeser dari
 *    yang selama ini terjadi diam-diam; bedanya kini tercatat dan disengaja.
 * 3. Penugasan yang tersisa di kategori itu dibatalkan. Kategori yang tidak dihitung tidak
 *    boleh tetap meminta orang mengisi lembar yang tidak dipakai.
 *
 * Penanda di awal `Alasan_Perubahan` / `Cancel_Reason` membedakan baris milik tanda ini
 * dari penyimpangan manual, supaya pembatalan tanda hanya mengembalikan yang ia buat.
 * Peserta yang sudah punya penyimpangan penilai manual ditolak: membagi ulang di atas
 * bobot yang diatur tangan akan menimpa keputusan orang lain tanpa bisa dikembalikan.
 */
class PaKategoriTidakDiikutkanService
{
    public const TANDA_TIDAK_DIIKUTKAN = '[TIDAK_DIIKUTKAN] ';

    public const TANDA_BAGI_ULANG = '[BAGI_ULANG] ';

    public function __construct(
        private readonly PaParticipantOverrideService $override,
        private readonly PaParticipantRuleResolver $resolver,
        private readonly PaBobotGuard $bobot,
        private readonly PaAssignmentBuilderService $builder,
    ) {}

    /** Alasan seperti yang ditulis admin, tanpa penanda internal. */
    public static function alasanTanpaTanda(?string $alasan): ?string
    {
        if ($alasan === null) {
            return null;
        }

        foreach ([self::TANDA_TIDAK_DIIKUTKAN, self::TANDA_BAGI_ULANG] as $tanda) {
            if (str_starts_with($alasan, $tanda)) {
                return substr($alasan, strlen($tanda));
            }
        }

        return $alasan;
    }

    /**
     * Bahan tampilan hasil satu peserta: bobot aturan LEVEL per kategori (sebelum dibagi
     * ulang) dan kategori yang ditandai tidak diikutkan beserta alasannya. Tanpa ini, tabel
     * "Bobot yang berlaku" hanya melihat aturan sesudah override — kategori yang dicabut
     * lenyap dari layar, dan bobot hasil bagi ulang tampil seolah rencana awalnya.
     *
     * Daftar, bukan peta berkunci kode kategori: bentuk respons tidak boleh bergantung pada
     * kategori yang kebetulan dimiliki level peserta (kontrak fixture membandingkan kunci).
     *
     * @return array{bobot_level: list<array{kode_kategori:string, bobot_persen:float}>, tidak_diikutkan: list<array{kode_kategori:string, nama_kategori:string, alasan:string|null}>}
     */
    public static function rincianPeserta(int $participantId): array
    {
        $snapshotId = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $participantId)
            ->value('PA_Cycle_Rater_Policy_Snapshot_Id');

        $bobotLevel = $snapshotId === null ? [] : DB::table('N_PA_Cycle_Rater_Policy_Detail_Snapshot')
            ->where('PA_Cycle_Rater_Policy_Snapshot_Id', $snapshotId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Kode_Kategori_Salinan', 'Bobot_Persen'])
            ->map(fn ($b) => ['kode_kategori' => $b->Kode_Kategori_Salinan, 'bobot_persen' => (float) $b->Bobot_Persen])
            ->values()
            ->all();

        $tidakDiikutkan = DB::table('N_PA_Participant_Rater_Override as o')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'o.PA_Rater_Category_Id')
            ->where('o.PA_Participant_Id', $participantId)
            ->where('o.Flag_Aktif', 'Y')
            ->where('o.Flag_Dipakai', 'T')
            ->get(['c.Kode_Kategori', 'c.Nama_Kategori', 'o.Alasan_Perubahan'])
            ->map(fn ($r) => [
                'kode_kategori' => $r->Kode_Kategori,
                'nama_kategori' => $r->Nama_Kategori,
                'alasan' => self::alasanTanpaTanda($r->Alasan_Perubahan),
            ])
            ->values()
            ->all();

        return ['bobot_level' => $bobotLevel, 'tidak_diikutkan' => $tidakDiikutkan];
    }

    /**
     * @return array{participant_id:int, kategori_id:int, dibatalkan:int, bobot_efektif:array<string, float>}
     */
    public function tandai(int $participantId, int $kategoriId, string $alasan, ?int $olehUserId = null): array
    {
        $alasan = trim($alasan);

        if ($alasan === '') {
            throw new RuntimeException(
                'Alasan wajib diisi. Kategori yang tidak diikutkan membuat peserta ini dinilai '
                .'berbeda dari sesama levelnya, dan itu harus bisa dijelaskan.'
            );
        }

        if (mb_strlen($alasan) > 400) {
            throw new RuntimeException('Alasan terlalu panjang; maksimal 400 karakter.');
        }

        $dicabut = $this->dicabutSekarang($participantId);
        $nama = $this->namaKategori($kategoriId);

        /* Idempoten: menandai ulang kategori yang sudah ditandai hanya meneruskan pembatalan
           penugasan yang mungkin tertinggal (misalnya karena gagal di tengah). */
        if (! isset($dicabut[$kategoriId])) {
            $dicabut[$kategoriId] = self::TANDA_TIDAK_DIIKUTKAN.$alasan;
            $efektif = DB::transaction(fn () => $this->terapkan($participantId, $dicabut, $olehUserId));
        } else {
            $efektif = $this->bobotEfektif($participantId);
        }

        /* DI LUAR transaksi override, dan sengaja: `batalkan()` mengirim pemberitahuan dan
           mengantrekan pekerjaan sesudah commit-nya sendiri. Menjalankannya di dalam
           transaksi lain berarti pemberitahuan terkirim sebelum tandanya pasti tersimpan. */
        $tugas = DB::table('N_PA_Assignment')
            ->where('PA_Participant_Id', $participantId)
            ->where('PA_Rater_Category_Id', $kategoriId)
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Assignment');

        foreach ($tugas as $id) {
            $this->builder->batalkan(
                (int) $id,
                self::TANDA_TIDAK_DIIKUTKAN."Kategori {$nama} sengaja tidak diikutkan: {$alasan}",
                $olehUserId,
            );
        }

        return [
            'participant_id' => $participantId,
            'kategori_id' => $kategoriId,
            'dibatalkan' => $tugas->count(),
            'bobot_efektif' => $efektif,
        ];
    }

    /**
     * Membatalkan tanda: kategori kembali dipakai, bobot kembali ke aturan level, dan
     * penugasan yang dibatalkan OLEH TANDA INI diaktifkan kembali.
     *
     * @return array{participant_id:int, kategori_id:int, dipulihkan:int}
     */
    public function batalkanTanda(int $participantId, int $kategoriId, ?int $olehUserId = null): array
    {
        $dicabut = $this->dicabutSekarang($participantId);

        if (! isset($dicabut[$kategoriId])) {
            throw new RuntimeException('Kategori ini tidak sedang ditandai tidak diikutkan bagi peserta ini.');
        }

        unset($dicabut[$kategoriId]);

        $dipulihkan = DB::transaction(function () use ($participantId, $kategoriId, $dicabut, $olehUserId) {
            $this->terapkan($participantId, $dicabut, $olehUserId);

            $calon = DB::table('N_PA_Assignment')
                ->where('PA_Participant_Id', $participantId)
                ->where('PA_Rater_Category_Id', $kategoriId)
                ->where('Flag_Aktif', 'T')
                ->where('Status_Penugasan', PaAssignmentBuilderService::STATUS_DIBATALKAN)
                // Bukan LIKE: di SQL Server `[` membuka kelas karakter dan `_` wildcard.
                ->whereRaw('LEFT(Cancel_Reason, ?) = ?', [strlen(self::TANDA_TIDAK_DIIKUTKAN), self::TANDA_TIDAK_DIIKUTKAN])
                /* Tanpa pembanding jam: jam pembatalan dan jam tanda ditulis dua jalur berbeda
                   dan tidak selalu berurutan. Penandanya cukup — yang sudah dipulihkan dulu
                   kembali aktif, jadi tidak terambil lagi. */
                ->get(['Id_PA_Assignment', 'Kode_Perusahaan_Penilai', 'Kode_Karyawan_Penilai']);

            $sekarang = FormatTanggalHelper::getCurrentTime();
            $jumlah = 0;

            foreach ($calon as $t) {
                // Penilai yang sudah ditugaskan lagi lewat jalan lain tidak digandakan.
                $sudahAda = DB::table('N_PA_Assignment')
                    ->where('PA_Participant_Id', $participantId)
                    ->where('PA_Rater_Category_Id', $kategoriId)
                    ->where('Kode_Perusahaan_Penilai', $t->Kode_Perusahaan_Penilai)
                    ->where('Kode_Karyawan_Penilai', $t->Kode_Karyawan_Penilai)
                    ->where('Flag_Aktif', 'Y')
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                DB::table('N_PA_Assignment')
                    ->where('Id_PA_Assignment', $t->Id_PA_Assignment)
                    ->update([
                        'Status_Penugasan' => PaAssignmentBuilderService::STATUS_BELUM_MULAI,
                        'Flag_Aktif' => 'Y',
                        'Cancel_Reason' => null,
                        'Cancelled_At' => null,
                        'Cancelled_By' => null,
                        'Updated_At' => $sekarang,
                        'Updated_By' => $olehUserId,
                    ]);
                $jumlah++;
            }

            if ($jumlah > 0) {
                $this->builder->hitungUlangBobot($participantId, $kategoriId, $olehUserId);
            }

            return $jumlah;
        });

        return ['participant_id' => $participantId, 'kategori_id' => $kategoriId, 'dipulihkan' => $dipulihkan];
    }

    /**
     * Menulis ulang SELURUH override penilai peserta dari daftar kategori yang dicabut.
     *
     * @param  array<int, string>  $dicabut  kategori_id => Alasan_Perubahan (sudah bertanda)
     * @return array<string, float>
     */
    private function terapkan(int $participantId, array $dicabut, ?int $olehUserId): array
    {
        // Kosongkan dulu supaya bobot dibagi dari ATURAN LEVEL, bukan dari hasil bagi
        // ulang sebelumnya — dua tanda berturut-turut tidak boleh saling menggandakan.
        $this->override->simpanOverridePenilai($participantId, [], $olehUserId);

        if ($dicabut === []) {
            return $this->bobotEfektif($participantId);
        }

        $this->resolver->lupakanMemo();
        $dasar = $this->resolver->raterRules($participantId);

        $dikenal = array_column($dasar, 'kategori_id');

        foreach (array_keys($dicabut) as $kategoriId) {
            if (! in_array((int) $kategoriId, $dikenal, true)) {
                throw new RuntimeException("Kategori penilai {$kategoriId} tidak berlaku bagi peserta ini.");
            }
        }

        $kodeDicabut = [];
        $sisa = [];

        foreach ($dasar as $kode => $a) {
            if (isset($dicabut[$a['kategori_id']])) {
                $kodeDicabut[] = $a['nama_kategori'];
            } else {
                $sisa[$kode] = $a;
            }
        }

        if ($sisa === []) {
            throw new RuntimeException(
                'Tidak bisa: semua kategori penilai peserta ini akan tidak diikutkan, dan peserta '
                .'tanpa satu kategori pun tidak akan pernah bisa dinilai. Kecualikan pesertanya, '
                .'atau tambah penilainya.'
            );
        }

        $bobotBaru = $this->bobot->redistribusi(
            array_map(fn ($a) => $a['bobot_persen'], $dasar),
            array_keys($sisa),
        );

        $daftar = [];

        foreach ($dicabut as $kategoriId => $alasan) {
            $daftar[] = [
                'pa_rater_category_id' => (int) $kategoriId,
                'flag_dipakai' => 'T',
                'alasan_perubahan' => $alasan,
            ];
        }

        $sebab = self::TANDA_BAGI_ULANG.'Bobot dibagi ulang karena '.implode(', ', $kodeDicabut).' tidak diikutkan.';

        foreach ($sisa as $kode => $a) {
            if (abs($bobotBaru[$kode] - $a['bobot_persen']) < 0.005) {
                continue;
            }

            $daftar[] = [
                'pa_rater_category_id' => $a['kategori_id'],
                'flag_dipakai' => 'Y',
                'bobot_persen' => $bobotBaru[$kode],
                'min_rater' => $a['min_rater'],
                'maks_rater' => $a['maks_rater'],
                'min_rater_selesai' => $a['min_rater_selesai'],
                'alasan_perubahan' => mb_substr($sebab, 0, 500),
            ];
        }

        return $this->override->simpanOverridePenilai($participantId, $daftar, $olehUserId)['bobot_efektif'];
    }

    /**
     * Kategori yang saat ini dicabut oleh tanda ini. Menolak peserta yang punya
     * penyimpangan penilai manual (lihat docblock kelas).
     *
     * @return array<int, string>
     */
    private function dicabutSekarang(int $participantId): array
    {
        $baris = DB::table('N_PA_Participant_Rater_Override')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->get(['PA_Rater_Category_Id', 'Flag_Dipakai', 'Alasan_Perubahan']);

        $dicabut = [];

        foreach ($baris as $r) {
            $alasan = (string) $r->Alasan_Perubahan;

            if (str_starts_with($alasan, self::TANDA_TIDAK_DIIKUTKAN) && $r->Flag_Dipakai === 'T') {
                $dicabut[(int) $r->PA_Rater_Category_Id] = $alasan;

                continue;
            }

            if (! str_starts_with($alasan, self::TANDA_BAGI_ULANG)) {
                throw new RuntimeException(
                    'Peserta ini sudah punya penyimpangan aturan penilai yang diatur manual. '
                    .'Membagi ulang bobot di atasnya akan menimpa keputusan itu; sesuaikan lewat '
                    .'penyimpangan aturannya.'
                );
            }
        }

        return $dicabut;
    }

    /** @return array<string, float> */
    private function bobotEfektif(int $participantId): array
    {
        $this->resolver->lupakanMemo();

        return array_map(fn ($a) => $a['bobot_persen'], $this->resolver->raterRules($participantId));
    }

    private function namaKategori(int $kategoriId): string
    {
        $nama = DB::table('N_PA_Rater_Category')->where('Id_PA_Rater_Category', $kategoriId)->value('Nama_Kategori');

        if ($nama === null) {
            throw new RuntimeException("Kategori penilai {$kategoriId} tidak ada.");
        }

        return (string) $nama;
    }
}
