<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Penyimpangan per peserta (K32) — jalur tulisnya.
 *
 * Tiga tabel override berdiri sejak DDL-2026-074 dan sampai hari ini **hanya punya
 * pembaca**. Rancangan sparse yang dibela panjang lebar saat desain belum pernah bisa
 * dipakai, karena tak seorang pun bisa membuat satu baris pun.
 *
 * SPARSE, DAN ITU SEBABNYA PENJAGANYA BEKERJA BEGINI
 *
 * Override normalnya KOSONG. Yang tersimpan hanya yang benar-benar menyimpang dari
 * aturan level. Konsekuensinya: menilai sah atau tidaknya satu override **tidak bisa**
 * dilakukan dengan melihat barisnya sendiri — ia harus dilihat setelah digabung dengan
 * salinan siklus.
 *
 * Contoh yang membuatnya nyata: aturan level memberi Atasan 45, Self 10, Rekan 15,
 * Bawahan 30. Menulis satu override "Atasan 50" tampak wajar sendirian, tetapi
 * gabungannya menjadi 105. Karena itu penulisan di sini SELALU diikuti pembacaan ulang
 * lewat resolver, lalu diperiksa `PaBobotGuard` — penjaga yang sama yang dipakai mesin
 * perhitungan. Kalau tidak berjumlah 100, seluruh transaksinya dibatalkan.
 *
 * SATU PINTU TETAP SATU PINTU
 *
 * Service ini MENULIS ketiga tabel override, tetapi tidak pernah MEMBACANYA untuk
 * menentukan aturan efektif — itu tetap milik `PaParticipantRuleResolver` (K32), dan
 * dijaga `ResolverSatuPintuTest`. Yang dibaca di sini hanya untuk menolak override yang
 * tidak mengubah apa pun.
 *
 * SEBAB WAJIB, DAN SKEMA YANG MENJAMINNYA
 *
 * `Alasan_Perubahan` NOT NULL di ketiga tabel. Override tanpa sebab adalah peserta yang
 * diperlakukan berbeda dari sesama levelnya tanpa ada yang bisa menjelaskan kenapa.
 */
class PaParticipantOverrideService
{
    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaParticipantRuleResolver $resolver,
        private readonly PaBobotGuard $bobot,
    ) {}

    /**
     * Menyimpan SELURUH override penilai satu peserta sekaligus.
     *
     * Sengaja seluruhnya, bukan per baris. Bobot antar kategori wajib berjumlah 100
     * SESUDAH digabung dengan salinan siklus, dan jumlah itu hanya bisa diperiksa ketika
     * seluruh penyimpangannya diketahui. Menyimpan satu baris pada satu waktu berarti
     * setiap penyimpanan di tengah pekerjaan pasti melanggar aturannya sendiri.
     *
     * Daftar KOSONG berarti mencabut seluruh override — peserta kembali ke aturan levelnya.
     *
     * @param  list<array{
     *     pa_rater_category_id:int, alasan_perubahan:string,
     *     flag_dipakai?:string, bobot_persen?:float|null, min_rater?:int|null,
     *     maks_rater?:int|null, min_rater_selesai?:int|null
     * }>  $daftar
     * @return array{participant_id:int, tersimpan:int, dicabut:int, bobot_efektif:array<string, float>}
     */
    public function simpanOverridePenilai(int $participantId, array $daftar, ?int $olehUserId = null): array
    {
        $peserta = $this->pesertaYangBolehDisimpangkan($participantId);
        $aturanAsal = $this->resolver->raterRules($participantId);

        return DB::transaction(function () use ($participantId, $daftar, $aturanAsal, $olehUserId) {
            $dicabut = $this->nonaktifkan('N_PA_Participant_Rater_Override', $participantId, $olehUserId);

            $baris = [];
            $terlihat = [];

            foreach ($daftar as $d) {
                $kategoriId = (int) ($d['pa_rater_category_id'] ?? 0);

                if ($kategoriId <= 0) {
                    throw new RuntimeException('Setiap override wajib menyebut kategori penilai.');
                }

                if (isset($terlihat[$kategoriId])) {
                    throw new RuntimeException(
                        "Kategori penilai #{$kategoriId} disebut lebih dari sekali; unique index "
                        .'hanya mengizinkan satu override aktif per kategori.'
                    );
                }

                $terlihat[$kategoriId] = true;

                $dipakai = ($d['flag_dipakai'] ?? 'Y') === 'T' ? 'T' : 'Y';

                // Override yang MENCABUT kategori tidak perlu bobot; yang mengubahnya wajib
                // punya. CHECK di skema menuntut hal yang sama, tetapi ditolak di sini lebih
                // dulu supaya pesannya menjelaskan, bukan sekadar melanggar constraint.
                if ($dipakai === 'Y' && ($d['bobot_persen'] ?? null) === null) {
                    throw new RuntimeException(
                        "Override kategori #{$kategoriId} mengubah aturan, jadi Bobot_Persen wajib diisi. "
                        .'Untuk mencabut kategori, kirim flag_dipakai = "T".'
                    );
                }

                $baris[] = [
                    'PA_Participant_Id' => $participantId,
                    'PA_Rater_Category_Id' => $kategoriId,
                    'PA_Cycle_Rater_Policy_Detail_Snapshot_Id' => $d['pa_cycle_rater_policy_detail_snapshot_id'] ?? null,
                    'Flag_Dipakai' => $dipakai,
                    'Bobot_Persen' => $dipakai === 'Y' ? (float) $d['bobot_persen'] : null,
                    'Min_Rater' => $dipakai === 'Y' ? (int) ($d['min_rater'] ?? 1) : null,
                    'Maks_Rater' => $d['maks_rater'] ?? null,
                    'Min_Rater_Selesai' => $d['min_rater_selesai'] ?? null,
                    'Alasan_Perubahan' => $this->alasan($d['alasan_perubahan'] ?? null),
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $olehUserId,
                    'Created_At' => FormatTanggalHelper::getCurrentTime(),
                ];
            }

            if ($baris !== []) {
                DB::table('N_PA_Participant_Rater_Override')->insert($baris);
            }

            $efektif = $this->periksaBobotEfektif($participantId, $aturanAsal);

            return [
                'participant_id' => $participantId,
                'tersimpan' => count($baris),
                'dicabut' => $dicabut,
                'bobot_efektif' => $efektif,
            ];
        });
    }

    /**
     * Menyimpan penyimpangan bobot pilar satu peserta.
     *
     * @return array{participant_id:int, bobot_kpi:float, bobot_kompetensi:float}
     */
    public function simpanOverridePilar(
        int $participantId,
        float $bobotKpi,
        float $bobotKompetensi,
        string $alasan,
        ?int $olehUserId = null,
    ): array {
        $this->pesertaYangBolehDisimpangkan($participantId);

        $this->bobot->pastikanSeratus(
            ['KPI' => $bobotKpi, 'KOMPETENSI' => $bobotKompetensi],
            "override bobot pilar peserta {$participantId}"
        );

        $asal = $this->resolver->pillarWeight($participantId);

        // Override yang sama persis dengan aturan levelnya bukan penyimpangan — ia hanya
        // baris yang harus dibaca orang tanpa memberi tahu apa pun, dan membuat peserta
        // ini tampak menyimpang di layar penyimpangan padahal tidak.
        if (abs($asal['bobot_kpi'] - $bobotKpi) < 0.001
            && abs($asal['bobot_kompetensi'] - $bobotKompetensi) < 0.001) {
            throw new RuntimeException(
                "Bobot yang dikirim sama dengan aturan level peserta ini ({$asal['bobot_kpi']} / "
                ."{$asal['bobot_kompetensi']}). Override yang tidak mengubah apa pun hanya "
                .'membuatnya tampak menyimpang padahal tidak.'
            );
        }

        $alasan = $this->alasan($alasan);

        DB::transaction(function () use ($participantId, $bobotKpi, $bobotKompetensi, $alasan, $olehUserId) {
            $this->nonaktifkan('N_PA_Participant_Pillar_Override', $participantId, $olehUserId);

            DB::table('N_PA_Participant_Pillar_Override')->insert([
                'PA_Participant_Id' => $participantId,
                'Bobot_KPI' => $bobotKpi,
                'Bobot_Kompetensi' => $bobotKompetensi,
                'Alasan_Perubahan' => $alasan,
                'Flag_Aktif' => 'Y',
                'Created_By' => $olehUserId,
                'Created_At' => FormatTanggalHelper::getCurrentTime(),
            ]);
        });

        return [
            'participant_id' => $participantId,
            'bobot_kpi' => $bobotKpi,
            'bobot_kompetensi' => $bobotKompetensi,
        ];
    }

    /**
     * Menyimpan SELURUH penyimpangan jenis kompetensi satu peserta.
     *
     * @param  list<array{pa_competency_type_id:int, alasan_perubahan:string,
     *                   flag_dipakai?:string, flag_wajib?:string}>  $daftar
     * @return array{participant_id:int, tersimpan:int, dicabut:int}
     */
    public function simpanOverrideSyarat(int $participantId, array $daftar, ?int $olehUserId = null): array
    {
        $this->pesertaYangBolehDisimpangkan($participantId);

        return DB::transaction(function () use ($participantId, $daftar, $olehUserId) {
            $dicabut = $this->nonaktifkan('N_PA_Participant_Requirement_Override', $participantId, $olehUserId);

            $baris = [];
            $terlihat = [];

            foreach ($daftar as $d) {
                $typeId = (int) ($d['pa_competency_type_id'] ?? 0);

                if ($typeId <= 0) {
                    throw new RuntimeException('Setiap override wajib menyebut jenis kompetensi.');
                }

                if (isset($terlihat[$typeId])) {
                    throw new RuntimeException("Jenis kompetensi #{$typeId} disebut lebih dari sekali.");
                }

                $terlihat[$typeId] = true;

                $baris[] = [
                    'PA_Participant_Id' => $participantId,
                    'PA_Competency_Type_Id' => $typeId,
                    'PA_Cycle_Competency_Requirement_Snapshot_Id' => $d['pa_cycle_competency_requirement_snapshot_id'] ?? null,
                    'Flag_Dipakai' => ($d['flag_dipakai'] ?? 'Y') === 'T' ? 'T' : 'Y',
                    'Flag_Wajib' => ($d['flag_wajib'] ?? null) === null
                        ? null
                        : (($d['flag_wajib'] === 'Y') ? 'Y' : 'T'),
                    'Alasan_Perubahan' => $this->alasan($d['alasan_perubahan'] ?? null),
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $olehUserId,
                    'Created_At' => FormatTanggalHelper::getCurrentTime(),
                ];
            }

            if ($baris !== []) {
                DB::table('N_PA_Participant_Requirement_Override')->insert($baris);
            }

            return [
                'participant_id' => $participantId,
                'tersimpan' => count($baris),
                'dicabut' => $dicabut,
            ];
        });
    }

    /**
     * Mencabut seluruh penyimpangan satu peserta — ia kembali ke aturan levelnya.
     *
     * @return array{participant_id:int, dicabut:array<string, int>}
     */
    public function cabutSeluruhOverride(int $participantId, ?int $olehUserId = null): array
    {
        $this->pesertaYangBolehDisimpangkan($participantId);

        return DB::transaction(function () use ($participantId, $olehUserId) {
            $dicabut = [
                'penilai' => $this->nonaktifkan('N_PA_Participant_Rater_Override', $participantId, $olehUserId),
                'pilar' => $this->nonaktifkan('N_PA_Participant_Pillar_Override', $participantId, $olehUserId),
                'syarat' => $this->nonaktifkan('N_PA_Participant_Requirement_Override', $participantId, $olehUserId),
            ];

            if (array_sum($dicabut) === 0) {
                throw new RuntimeException(
                    "Peserta {$participantId} tidak punya penyimpangan apa pun untuk dicabut."
                );
            }

            return ['participant_id' => $participantId, 'dicabut' => $dicabut];
        });
    }

    /* ------------------------------------------------------------------ *
     * Penjaga                                                             *
     * ------------------------------------------------------------------ */

    /**
     * Bobot penilai EFEKTIF — salinan siklus digabung override, dibaca lewat resolver.
     *
     * Dibaca ulang lewat resolver, bukan dihitung sendiri di sini. Menghitungnya sendiri
     * berarti membuat penggabung kedua yang suatu hari pasti berselisih dengan
     * penggabung yang dipakai mesin perhitungan — dan selisihnya baru terlihat sebagai
     * nilai yang salah, bukan sebagai galat.
     *
     * @param  array<string, array<string, mixed>>  $aturanAsal
     * @return array<string, float>
     */
    private function periksaBobotEfektif(int $participantId, array $aturanAsal): array
    {
        // Memo resolver dilupakan supaya ia membaca keadaan SESUDAH penulisan barusan,
        // bukan yang dihafalnya sebelum transaksi ini.
        $this->resolver->lupakanMemo();

        $efektif = $this->resolver->raterRules($participantId);

        if ($efektif === []) {
            throw new RuntimeException(
                "Sesudah override, peserta {$participantId} tidak punya satu pun kategori penilai. "
                .'Peserta tanpa kategori tidak akan pernah bisa dinilai.'
            );
        }

        $bobot = [];

        foreach ($efektif as $kode => $a) {
            $bobot[$kode] = $a['bobot_persen'];
        }

        // Penjaga yang SAMA dengan yang dipakai mesin perhitungan. Satu override yang
        // tampak wajar sendirian bisa membuat gabungannya menjadi 105.
        $this->bobot->pastikanSeratus(
            $bobot,
            sprintf(
                'penilai efektif peserta %d sesudah override (aturan levelnya: %s)',
                $participantId,
                implode(', ', array_map(
                    fn ($k, $a) => "{$k}={$a['bobot_persen']}",
                    array_keys($aturanAsal),
                    $aturanAsal
                ))
            )
        );

        return $bobot;
    }

    private function pesertaYangBolehDisimpangkan(int $participantId): object
    {
        $this->gate->pastikanBolehUntukPeserta(PaCycleGate::TULIS_OVERRIDE, $participantId);

        $peserta = DB::table('N_PA_Participant')
            ->where('Id_PA_Participant', $participantId)
            ->first(['Id_PA_Participant', 'PA_Cycle_Id', 'Status_Peserta', 'Flag_Aktif']);

        if ($peserta === null) {
            throw new RuntimeException("Peserta {$participantId} tidak ada.");
        }

        if ($peserta->Flag_Aktif !== 'Y') {
            throw new RuntimeException("Peserta {$participantId} sudah tidak aktif.");
        }

        if ($peserta->Status_Peserta !== 'DIIKUTKAN') {
            throw new RuntimeException(
                "Peserta {$participantId} berstatus {$peserta->Status_Peserta}. Menyimpangkan "
                .'aturan bagi orang yang tidak dinilai tidak menghasilkan apa pun.'
            );
        }

        return $peserta;
    }

    private function nonaktifkan(string $tabel, int $participantId, ?int $olehUserId): int
    {
        return DB::table($tabel)
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->update([
                'Flag_Aktif' => 'T',
                'Updated_At' => FormatTanggalHelper::getCurrentTime(),
                'Updated_By' => $olehUserId,
            ]);
    }

    private function alasan(mixed $nilai): string
    {
        $teks = trim((string) $nilai);

        if ($teks === '') {
            throw new RuntimeException(
                'Setiap penyimpangan wajib beralasan. Tanpa itu, peserta ini diperlakukan '
                .'berbeda dari sesama levelnya tanpa ada yang bisa menjelaskan kenapa.'
            );
        }

        return $teks;
    }
}
