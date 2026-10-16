<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kalibrasi Penilai — penilai mengubah skornya sendiri per kompetensi, dan jawaban esainya
 * per butir (sejak DDL-2026-119 r2), sebelum mengirim. Satu tindakan simpan boleh memuat
 * keduanya dengan satu alasan.
 *
 * BUKAN kalibrasi forum (PaCalibrationService), yang menggeser grade akhir sebuah hasil.
 * Keduanya sengaja tidak berbagi tabel maupun nama, supaya riwayat audit tidak pernah
 * mencampur "forum menurunkan grade" dengan "penilai merevisi skornya".
 *
 * Aturannya (spec §5.2) diperiksa di DALAM transaksi dengan lockForUpdate pada baris
 * penugasan: penilai bisa menekan kirim, atau admin melepas paksa, tepat saat kalibrasi
 * disimpan. Tanpa kunci, revisi bisa mendarat pada lembar yang sudah beku.
 */
class PaKalibrasiPenilaiService
{
    public const PANJANG_ALASAN = 500;

    public const PANJANG_JAWABAN = 5000;

    /** Selisih di bawah ini dianggap "tidak berubah"; skornya disimpan dua desimal. */
    private const TOLERANSI = 0.005;

    public function __construct(
        private readonly PaCycleGate $gate,
        private readonly PaSkorEfektif $skor,
        private readonly PaTeksKaya $teksKaya,
    ) {}

    /**
     * @param  object  $penilai  Karyawan yang login: Kode_Perusahaan, Kode_Karyawan
     * @param  list<array{competency_id:int|string, skor:int|float|string}>  $perubahan
     * @param  list<array{kode_soal:string, id_master_soal?:int|string|null, jawaban:string}>  $perubahanJawaban
     * @return array{kalibrasi_id:int, assignment_id:int, cycle_id:int, kategori:string, diubah:int, diubah_jawaban:int}
     */
    public function simpan(
        object $penilai,
        int $assignmentId,
        array $perubahan,
        ?string $alasan,
        ?int $olehUserId,
        array $perubahanJawaban = [],
    ): array {
        $alasan = trim((string) $alasan);

        if ($alasan === '' || mb_strlen($alasan) > self::PANJANG_ALASAN) {
            throw new PaKalibrasiPenilaiException(
                'Alasan kalibrasi wajib diisi, paling panjang '.self::PANJANG_ALASAN.' karakter.',
                PaKalibrasiPenilaiException::ALASAN_WAJIB,
                422,
            );
        }

        return DB::transaction(function () use ($penilai, $assignmentId, $perubahan, $alasan, $olehUserId, $perubahanJawaban) {
            $tugas = DB::table('N_PA_Assignment as a')
                ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
                ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
                ->where('a.Id_PA_Assignment', $assignmentId)
                ->where('a.Flag_Aktif', 'Y')
                ->lockForUpdate()
                ->first([
                    'a.Id_PA_Assignment', 'a.Status_Penugasan', 'a.Kode_Perusahaan_Penilai',
                    'a.Kode_Karyawan_Penilai', 'p.PA_Cycle_Id', 'c.Kode_Kategori', 'c.Flag_Boleh_Kalibrasi',
                ]);

            // Milik orang lain dijawab SAMA dengan yang tidak ada: keberadaannya tidak boleh bocor.
            if ($tugas === null || ! $this->milik($tugas, $penilai)) {
                throw new PaKalibrasiPenilaiException(
                    'Penugasan tidak ditemukan.',
                    PaKalibrasiPenilaiException::PENUGASAN_TIDAK_DITEMUKAN,
                    404,
                );
            }

            if (! $this->gate->boleh(PaCycleGate::KALIBRASI_PENILAI, (int) $tugas->PA_Cycle_Id)) {
                throw new PaKalibrasiPenilaiException(
                    'Kalibrasi Penilai hanya bisa dilakukan selama pengisian siklus masih terbuka.',
                    PaKalibrasiPenilaiException::SIKLUS_TIDAK_TERBUKA,
                    409,
                );
            }

            if ($tugas->Flag_Boleh_Kalibrasi !== 'Y') {
                throw new PaKalibrasiPenilaiException(
                    'Skor pada kategori penilai ini tidak bisa dikalibrasi.',
                    PaKalibrasiPenilaiException::KALIBRASI_DITUTUP,
                    403,
                );
            }

            if ($tugas->Status_Penugasan !== PaStatusPenugasan::TERKUMPUL) {
                throw new PaKalibrasiPenilaiException(
                    'Lembar ini berstatus '.$tugas->Status_Penugasan.'. Kalibrasi hanya untuk lembar '
                    .'yang sudah terisi dan belum dikirim.',
                    PaKalibrasiPenilaiException::LEMBAR_BEKU,
                    409,
                );
            }

            $rincian = $this->rincian($assignmentId, $perubahan);
            $rincianJawaban = $this->rincianJawaban($assignmentId, $perubahanJawaban);

            if ($rincian === [] && $rincianJawaban === []) {
                throw new PaKalibrasiPenilaiException(
                    'Tidak ada skor maupun jawaban yang berubah dari yang sekarang berlaku.',
                    PaKalibrasiPenilaiException::TIDAK_ADA_PERUBAHAN,
                    422,
                );
            }

            $kepalaId = (int) DB::table('N_PA_Kalibrasi_Penilai')->insertGetId([
                'PA_Assignment_Id' => $assignmentId,
                'Alasan' => $alasan,
                'Created_By' => $olehUserId,
            ], 'Id_PA_Kalibrasi_Penilai');

            if ($rincian !== []) {
                DB::table('N_PA_Kalibrasi_Penilai_Detail')->insert(array_map(
                    fn (array $r) => $r + ['PA_Kalibrasi_Penilai_Id' => $kepalaId],
                    $rincian,
                ));
            }

            // Satu per baris: teks panjang dikalikan jumlah butir cepat menembus batas parameter.
            foreach ($rincianJawaban as $r) {
                DB::table('N_PA_Kalibrasi_Penilai_Jawaban')->insert($r + ['PA_Kalibrasi_Penilai_Id' => $kepalaId]);
            }

            return [
                'kalibrasi_id' => $kepalaId,
                'assignment_id' => $assignmentId,
                'cycle_id' => (int) $tugas->PA_Cycle_Id,
                'kategori' => (string) $tugas->Kode_Kategori,
                'diubah' => count($rincian),
                'diubah_jawaban' => count($rincianJawaban),
            ];
        });
    }

    /**
     * Riwayat Kalibrasi Penilai atas satu peserta, terbaru lebih dulu — untuk layar admin.
     *
     * Kepala dibaca lebih dulu, rinciannya menyusul: tindakan yang hanya mengubah esai tidak
     * punya baris skor, dan JOIN wajib ke rincian skor akan menghilangkannya dari riwayat.
     *
     * @return list<array<string, mixed>>
     */
    public function riwayatPeserta(int $participantId): array
    {
        $kepala = DB::table('N_PA_Kalibrasi_Penilai as k')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'k.PA_Assignment_Id')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('a.PA_Participant_Id', $participantId)
            ->orderByDesc('k.Id_PA_Kalibrasi_Penilai')
            ->get([
                'k.Id_PA_Kalibrasi_Penilai', 'k.PA_Assignment_Id', 'k.Alasan', 'k.Created_At', 'k.Created_By',
                'a.Kode_Karyawan_Penilai', 'a.Nama_Penilai_Saat_Cycle', 'c.Kode_Kategori', 'c.Nama_Kategori',
            ]);

        if ($kepala->isEmpty()) {
            return [];
        }

        $ids = $kepala->pluck('Id_PA_Kalibrasi_Penilai')->map(fn ($x) => (int) $x)->all();

        $skor = DB::table('N_PA_Kalibrasi_Penilai_Detail as d')
            ->join('N_PA_Competency as m', 'm.Id_PA_Competency', '=', 'd.PA_Competency_Id')
            ->whereIn('d.PA_Kalibrasi_Penilai_Id', $ids)
            ->orderBy('m.Kode_Competency')
            ->get([
                'd.PA_Kalibrasi_Penilai_Id', 'd.PA_Competency_Id', 'm.Kode_Competency', 'm.Nama_Competency',
                'd.Skor_Sebelum', 'd.Skor_Sesudah', 'd.Skor_Maksimal',
            ])
            ->groupBy('PA_Kalibrasi_Penilai_Id');

        $jawaban = DB::table('N_PA_Kalibrasi_Penilai_Jawaban as j')
            ->leftJoin('N_PA_Competency as m', 'm.Id_PA_Competency', '=', 'j.PA_Competency_Id')
            ->whereIn('j.PA_Kalibrasi_Penilai_Id', $ids)
            ->orderBy('j.Kode_Soal_Sumber')
            ->orderBy('j.Id_Master_Soal')
            ->get([
                'j.PA_Kalibrasi_Penilai_Id', 'j.Kode_Soal_Sumber', 'j.Id_Master_Soal', 'j.PA_Competency_Id', 'm.Nama_Competency',
                'j.Jawaban_Sebelum', 'j.Jawaban_Sesudah',
            ])
            ->groupBy('PA_Kalibrasi_Penilai_Id');

        return $kepala->map(function ($k) use ($skor, $jawaban) {
            $id = (int) $k->Id_PA_Kalibrasi_Penilai;

            return [
                'kalibrasi_id' => $id,
                'assignment_id' => (int) $k->PA_Assignment_Id,
                'penilai' => ['kode_karyawan' => (string) $k->Kode_Karyawan_Penilai, 'nama' => (string) $k->Nama_Penilai_Saat_Cycle],
                'kategori' => ['kode' => (string) $k->Kode_Kategori, 'nama' => (string) $k->Nama_Kategori],
                'alasan' => (string) $k->Alasan,
                'pada' => substr((string) $k->Created_At, 0, 19),
                'oleh' => $k->Created_By === null ? null : (int) $k->Created_By,
                'perubahan' => collect($skor->get($id, []))->map(fn ($d) => [
                    'competency_id' => (int) $d->PA_Competency_Id,
                    'kode' => (string) $d->Kode_Competency,
                    'nama' => (string) $d->Nama_Competency,
                    'skor_sebelum' => (float) $d->Skor_Sebelum,
                    'skor_sesudah' => (float) $d->Skor_Sesudah,
                    'skor_maksimal' => (float) $d->Skor_Maksimal,
                ])->values()->all(),
                'perubahan_jawaban' => collect($jawaban->get($id, []))->map(fn ($j) => [
                    'kode_soal' => (string) $j->Kode_Soal_Sumber,
                    'id_master_soal' => $j->Id_Master_Soal === null ? null : (int) $j->Id_Master_Soal,
                    'competency_id' => $j->PA_Competency_Id === null ? null : (int) $j->PA_Competency_Id,
                    'nama' => $j->Nama_Competency,
                    'jawaban_sebelum' => (string) $j->Jawaban_Sebelum,
                    'jawaban_sesudah' => (string) $j->Jawaban_Sesudah,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * Rincian yang benar-benar berubah, dengan Skor_Sebelum = skor efektif saat ini.
     *
     * @param  list<array{competency_id:int|string, skor:int|float|string}>  $perubahan
     * @return list<array{PA_Competency_Id:int, Skor_Sebelum:float, Skor_Sesudah:float, Skor_Maksimal:float}>
     */
    private function rincian(int $assignmentId, array $perubahan): array
    {
        $butir = collect($this->skor->butirBerskor([$assignmentId])[$assignmentId] ?? [])
            ->filter(fn ($b) => $b->PA_Competency_Id !== null)
            ->keyBy('PA_Competency_Id');

        $rincian = [];
        $dilihat = [];

        foreach ($perubahan as $p) {
            $kompetensiId = (int) ($p['competency_id'] ?? 0);
            $b = $butir->get($kompetensiId);

            if ($b === null) {
                throw new PaKalibrasiPenilaiException(
                    "Kompetensi {$kompetensiId} tidak ada di lembar ini, atau butirnya tidak berskor.",
                    PaKalibrasiPenilaiException::KOMPETENSI_TIDAK_DI_LEMBAR,
                    422,
                );
            }

            if (isset($dilihat[$kompetensiId])) {
                throw new RuntimeException("Kompetensi {$kompetensiId} dikirim lebih dari sekali.");
            }

            $dilihat[$kompetensiId] = true;
            $skor = $p['skor'] ?? null;

            if (! is_numeric($skor) || (float) $skor < 0 || (float) $skor > $b->Skor_Maksimal) {
                throw new PaKalibrasiPenilaiException(
                    'Skor harus di antara 0 dan '.$this->angka($b->Skor_Maksimal).'.',
                    PaKalibrasiPenilaiException::SKOR_DI_LUAR_RENTANG,
                    422,
                );
            }

            $baru = round((float) $skor, 2);

            if (abs($baru - $b->Skor) < self::TOLERANSI) {
                continue;
            }

            $rincian[] = [
                'PA_Competency_Id' => $kompetensiId,
                'Skor_Sebelum' => $b->Skor,
                'Skor_Sesudah' => $baru,
                'Skor_Maksimal' => $b->Skor_Maksimal,
            ];
        }

        return $rincian;
    }

    /**
     * Rincian jawaban esai yang benar-benar berubah, dengan Jawaban_Sebelum = jawaban
     * efektif saat ini. Hanya butir esai yang sudah dijawab di lembar ini yang bisa diubah,
     * dan jawaban tidak boleh dikosongkan: butir kosong terbaca "tidak dijawab" dan bisa
     * menahan hasil orang yang dinilai.
     *
     * Keduanya disimpan sebagai HTML AMAN (PaTeksKaya): masukan penilai disaring di sini,
     * dan teks asli HC Learn yang menjadi `Jawaban_Sebelum` di-escape per baris. Kosong dan
     * panjang dihitung dari teks polosnya, sehingga `<p><br></p>` tetap terhitung kosong.
     *
     * @param  list<array{kode_soal:string, id_master_soal?:int|string|null, jawaban:string}>  $perubahan
     * @return list<array{Kode_Soal_Sumber:string, Id_Master_Soal:?int, PA_Competency_Id:?int, Jawaban_Sebelum:string, Jawaban_Sesudah:string}>
     */
    private function rincianJawaban(int $assignmentId, array $perubahan): array
    {
        if ($perubahan === []) {
            return [];
        }

        /* Kuncinya kode soal DITAMBAH id soal. Pada format per soal satu bagian memuat
           beberapa soal esai, dan revisi yang hanya menyebut kode bagian tidak menunjuk soal
           mana pun — ditolak, bukan diterapkan ke soal pertama. */
        $butir = collect($this->skor->jawabanNaratif([$assignmentId])[$assignmentId] ?? [])
            ->keyBy(fn ($b) => PaSkorEfektif::kunciJawaban($b['kode_soal'], $b['id_master_soal']));

        $rincian = [];
        $dilihat = [];

        foreach ($perubahan as $p) {
            $kode = trim((string) ($p['kode_soal'] ?? ''));
            $idSoal = is_numeric($p['id_master_soal'] ?? null) ? (int) $p['id_master_soal'] : null;
            $kunci = PaSkorEfektif::kunciJawaban($kode, $idSoal);
            $b = $butir->get($kunci);
            $label = $idSoal === null ? $kode : "{$kode} nomor {$idSoal}";

            if ($b === null) {
                throw new PaKalibrasiPenilaiException(
                    "Soal {$label} tidak ada di lembar ini, atau belum dijawab.",
                    PaKalibrasiPenilaiException::SOAL_TIDAK_DI_LEMBAR,
                    422,
                );
            }

            if (isset($dilihat[$kunci])) {
                throw new RuntimeException("Soal {$label} dikirim lebih dari sekali.");
            }

            $dilihat[$kunci] = true;
            // Masukan tanpa tag sama sekali diperlakukan seperti teks polos HC Learn (paragraf per
            // baris) supaya teks yang tidak diubah tidak terbaca sebagai perubahan.
            $masuk = (string) ($p['jawaban'] ?? '');
            $baru = $this->teksKaya->bersihkan(str_contains($masuk, '<') ? $masuk : $this->teksKaya->dariTeksPolos($masuk));

            if ($baru === '') {
                throw new PaKalibrasiPenilaiException(
                    'Jawaban esai tidak boleh dikosongkan.',
                    PaKalibrasiPenilaiException::JAWABAN_KOSONG,
                    422,
                );
            }

            if (mb_strlen($this->teksKaya->teksPolos($baru)) > self::PANJANG_JAWABAN) {
                throw new PaKalibrasiPenilaiException(
                    'Jawaban esai paling panjang '.self::PANJANG_JAWABAN.' karakter.',
                    PaKalibrasiPenilaiException::JAWABAN_TERLALU_PANJANG,
                    422,
                );
            }

            // Dibandingkan dalam bentuk yang sama-sama sudah disaring: editor mengirim teks polos
            // yang belum diubah sebagai `<p>…</p>`, dan itu bukan perubahan.
            $sekarang = $this->teksKaya->bersihkan($b['jawaban_html']);

            if ($baru === $sekarang) {
                continue;
            }

            $rincian[] = [
                'Kode_Soal_Sumber' => $kode,
                'Id_Master_Soal' => $idSoal,
                'PA_Competency_Id' => $b['competency_id'],
                'Jawaban_Sebelum' => $sekarang,
                'Jawaban_Sesudah' => $baru,
            ];
        }

        return $rincian;
    }

    private function milik(object $tugas, object $penilai): bool
    {
        $sama = fn ($a, $b) => strtoupper(trim((string) $a)) === strtoupper(trim((string) $b));

        return $sama($tugas->Kode_Perusahaan_Penilai, $penilai->Kode_Perusahaan)
            && $sama($tugas->Kode_Karyawan_Penilai, $penilai->Kode_Karyawan);
    }

    private function angka(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ',');
    }
}
