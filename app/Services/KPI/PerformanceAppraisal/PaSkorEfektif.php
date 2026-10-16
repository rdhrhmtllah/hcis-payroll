<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA pembaca skor butir untuk keperluan NILAI, dan jawaban esai untuk keperluan TEKS.
 *
 * Skor efektif (penugasan P, kompetensi K) = `Skor_Sesudah` Kalibrasi Penilai terbaru untuk
 * (P, K) bila ada; bila tidak, `Skor` asli dari HC Learn pada payload terpeta terakhir P.
 * Jawaban efektif (penugasan P, kode soal S, id soal Q) mengikuti aturan yang sama lewat
 * `N_PA_Kalibrasi_Penilai_Jawaban` (sejak DDL-2026-119 r2; id soal sejak r3). Jawaban format
 * per soal tinggal di `N_PA_Assignment_Score_Esai` (DDL-2026-140).
 * `N_PA_Assignment_Score` tidak pernah ditimpa: ia tetap catatan apa yang dikirim.
 *
 * KENAPA SATU PINTU. Skor dibaca di beberapa service, dan aturan "pakai yang terbaru" yang
 * hanya dipasang di satu dari dua jalur setara adalah bug yang sudah berulang di proyek ini.
 * `SkorEfektifSatuPintuTest` menolak berkas lain yang membaca kolom `Skor` langsung.
 *
 * KENAPA BERKUNCI (PENUGASAN, KOMPETENSI), BUKAN ID BARIS SKOR. `petakanUlang()` menonaktifkan
 * baris skor lama dan menyisipkan yang baru dengan `Mapper_Version` lebih tinggi. Kalibrasi
 * yang menempel ke id baris akan hilang tanpa jejak begitu kirimannya dipetakan ulang.
 */
class PaSkorEfektif
{
    /** Batas 2.100 parameter SQL Server; 500 menyisakan ruang bagi parameter lain. */
    private const POTONGAN = 500;

    public function __construct(
        private readonly PaTeksKaya $teksKaya,
    ) {}

    /**
     * Butir berskor per penugasan, dengan `Skor` sudah efektif.
     *
     * Setiap butir: `PA_Competency_Id` (?int), `Skor` (float, efektif), `Skor_Asli` (float),
     * `Skor_Maksimal` (float), `Bobot_Sumber` (?float), `Terkalibrasi` (bool). Hanya butir
     * ber-`Skor` tidak NULL dan `Skor_Maksimal` > 0 — bentuk yang sama dengan yang dipakai
     * perhitungan sejak awal.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, Collection<int, object>>
     */
    public function butirBerskor(array $assignmentIds): array
    {
        $assignmentIds = $this->bersihkan($assignmentIds);

        if ($assignmentIds === []) {
            return [];
        }

        $payloadKePenugasan = $this->payloadTerakhir($assignmentIds);

        if ($payloadKePenugasan === []) {
            return [];
        }

        $kalibrasi = $this->kalibrasiTerbaru(array_values(array_unique($payloadKePenugasan)));
        $hasil = [];

        foreach (array_chunk(array_keys($payloadKePenugasan), self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Assignment_Score')
                ->whereIn('PA_Assignment_Payload_Id', $potongan)
                ->where('Flag_Aktif', 'Y')
                ->whereNotNull('Skor')
                ->where('Skor_Maksimal', '>', 0)
                ->orderBy('PA_Assignment_Payload_Id')
                ->orderBy('Urutan_Tampil')
                ->orderBy('Id_PA_Assignment_Score')
                ->get(['PA_Assignment_Payload_Id', 'PA_Competency_Id', 'Skor', 'Skor_Maksimal', 'Bobot_Sumber']);

            foreach ($baris as $b) {
                $assignmentId = $payloadKePenugasan[(int) $b->PA_Assignment_Payload_Id];
                $kompetensi = $b->PA_Competency_Id === null ? null : (int) $b->PA_Competency_Id;
                $revisi = $kompetensi === null ? null : ($kalibrasi[$assignmentId][$kompetensi] ?? null);

                $hasil[$assignmentId][] = (object) [
                    'PA_Competency_Id' => $kompetensi,
                    'Skor' => $revisi ?? (float) $b->Skor,
                    'Skor_Asli' => (float) $b->Skor,
                    'Skor_Maksimal' => (float) $b->Skor_Maksimal,
                    'Bobot_Sumber' => $b->Bobot_Sumber === null ? null : (float) $b->Bobot_Sumber,
                    'Terkalibrasi' => $revisi !== null,
                ];
            }
        }

        return array_map(fn (array $butir) => collect($butir), $hasil);
    }

    /**
     * `Skor_Sesudah` dari tindakan kalibrasi TERBARU per (penugasan, kompetensi).
     *
     * "Terbaru" = `Id_PA_Kalibrasi_Penilai` terbesar. Tabelnya hanya ditambah, dan
     * penyimpanan dikunci `lockForUpdate` pada baris penugasan, jadi dua tindakan atas
     * penugasan yang sama tidak pernah berjalan bersamaan dan id naik mengikuti urutannya.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, array<int, float>>
     */
    public function kalibrasiTerbaru(array $assignmentIds): array
    {
        $hasil = [];
        $assignmentIds = $this->bersihkan($assignmentIds);
        $batas = $this->diterimaTerakhir($assignmentIds);

        foreach (array_chunk($assignmentIds, self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Kalibrasi_Penilai_Detail as d')
                ->join('N_PA_Kalibrasi_Penilai as k', 'k.Id_PA_Kalibrasi_Penilai', '=', 'd.PA_Kalibrasi_Penilai_Id')
                ->whereIn('k.PA_Assignment_Id', $potongan)
                ->orderBy('k.PA_Assignment_Id')
                ->orderBy('d.PA_Competency_Id')
                ->orderByDesc('k.Id_PA_Kalibrasi_Penilai')
                ->get(['k.PA_Assignment_Id', 'k.Created_At', 'd.PA_Competency_Id', 'd.Skor_Sesudah']);

            foreach ($baris as $b) {
                if ($this->kalibrasiBasi($b, $batas)) {
                    continue;
                }

                $hasil[(int) $b->PA_Assignment_Id][(int) $b->PA_Competency_Id] ??= (float) $b->Skor_Sesudah;
            }
        }

        return $hasil;
    }

    /**
     * Kiriman terpeta TERAKHIR per penugasan: kapan diterima.
     *
     * KALIBRASI YANG LEBIH TUA DARI KIRIMAN INI GUGUR (14 Okt 2026). Kalibrasi hanya boleh
     * pada lembar TERKUMPUL, jadi ia selalu dibuat SESUDAH kiriman yang sedang berlaku. Kiriman
     * terpeta yang lebih baru berarti penilainya mengisi ULANG — biasanya lewat "Akses ulang"
     * di Pemantauan — dan revisi atas isian lamanya tidak boleh diam-diam menimpa isian baru.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, string>
     */
    private function diterimaTerakhir(array $assignmentIds): array
    {
        $hasil = [];

        foreach (array_chunk($assignmentIds, self::POTONGAN) as $potongan) {
            DB::table('N_PA_Assignment_Payload as p')
                ->join('N_PA_Assignment_Integration as i', 'i.Id_PA_Assignment_Integration', '=', 'p.PA_Assignment_Integration_Id')
                ->whereIn('i.PA_Assignment_Id', $potongan)
                ->where('p.Flag_Terpeta', 'Y')
                ->groupBy('i.PA_Assignment_Id')
                ->selectRaw('i.PA_Assignment_Id, MAX(p.Diterima_At) AS diterima')
                ->get()
                ->each(function ($r) use (&$hasil) {
                    if ($r->diterima !== null) {
                        $hasil[(int) $r->PA_Assignment_Id] = (string) $r->diterima;
                    }
                });
        }

        return $hasil;
    }

    /** @param  array<int, string>  $batas */
    private function kalibrasiBasi(object $baris, array $batas): bool
    {
        $diterima = $batas[(int) $baris->PA_Assignment_Id] ?? null;

        return $diterima !== null && $baris->Created_At !== null
            && strtotime((string) $baris->Created_At) < strtotime($diterima);
    }

    /**
     * Jawaban naratif pada payload terpeta terakhir tiap penugasan.
     *
     * Satu jawaban adalah satu SOAL bila HC Learn mengirim `Esai[]` (anak di
     * `N_PA_Assignment_Score_Esai`, sejak DDL-2026-140), atau satu BAGIAN bila kirimannya
     * format lama (`Jawaban_Teks` pada baris skor). Bagian yang punya anak esai tidak lagi
     * memakai teks bagiannya.
     *
     * `jawaban` = teks POLOS efektif (untuk pembaca yang menampilkan teks biasa);
     * `jawaban_html` = HTML aman efektif — revisi Kalibrasi Penilai apa adanya, atau teks asli
     * HC Learn yang di-escape per baris; `jawaban_asli` = teks HC Learn, tidak pernah berubah.
     * `pertanyaan` dan `id_master_soal` hanya terisi pada format per soal.
     *
     * Ditaruh di sini, bukan di pemanggil, karena "payload mana yang berlaku" adalah
     * pengetahuan yang sama dengan butir berskor — dua salinan aturan itu akan berselisih.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, list<array{kode_soal:string, id_master_soal:?int, pertanyaan:?string, per_soal:bool, competency_id:?int, jawaban:string, jawaban_html:string, jawaban_asli:string, terkalibrasi:bool}>>
     */
    public function jawabanNaratif(array $assignmentIds): array
    {
        $payloadKePenugasan = $this->payloadTerakhir($this->bersihkan($assignmentIds));

        if ($payloadKePenugasan === []) {
            return [];
        }

        $revisi = $this->jawabanTerbaru(array_values(array_unique($payloadKePenugasan)));
        $hasil = [];

        foreach (array_chunk(array_keys($payloadKePenugasan), self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Assignment_Score as s')
                ->whereIn('s.PA_Assignment_Payload_Id', $potongan)
                ->where('s.Flag_Aktif', 'Y')
                ->whereRaw(self::sqlAdaJawaban('s'))
                ->orderBy('s.PA_Assignment_Payload_Id')
                ->orderBy('s.Urutan_Tampil')
                ->get(['s.Id_PA_Assignment_Score', 's.PA_Assignment_Payload_Id', 's.Kode_Soal_Sumber', 's.PA_Competency_Id', 's.Jawaban_Teks']);

            $esai = $this->esaiPerBaris($baris->pluck('Id_PA_Assignment_Score')->all());

            foreach ($baris as $b) {
                $assignmentId = $payloadKePenugasan[(int) $b->PA_Assignment_Payload_Id];
                $kode = (string) $b->Kode_Soal_Sumber;
                $kompetensi = $b->PA_Competency_Id === null ? null : (int) $b->PA_Competency_Id;

                foreach ($this->jawabanBaris($b, $esai) as $j) {
                    $baru = $revisi[$assignmentId][self::kunciJawaban($kode, $j['id'])] ?? null;

                    $hasil[$assignmentId][] = [
                        'kode_soal' => $kode,
                        'id_master_soal' => $j['id'],
                        'pertanyaan' => $j['pertanyaan'],
                        'per_soal' => $j['per_soal'],
                        'competency_id' => $kompetensi,
                        'jawaban' => $baru === null ? $j['teks'] : $this->teksKaya->teksPolos($baru),
                        'jawaban_html' => $baru ?? $j['html'],
                        'jawaban_asli' => $j['teks'],
                        'terkalibrasi' => $baru !== null,
                    ];
                }
            }
        }

        return $hasil;
    }

    /**
     * `Jawaban_Sesudah` (HTML aman) dari tindakan kalibrasi TERBARU per (penugasan, kunci
     * jawaban). Kuncinya `kunciJawaban(kode soal, Id_Master_Soal)`: format lama berkunci kode
     * soal saja, format per soal ditambah id soalnya.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, array<string, string>>
     */
    public function jawabanTerbaru(array $assignmentIds): array
    {
        $hasil = [];
        $assignmentIds = $this->bersihkan($assignmentIds);
        $batas = $this->diterimaTerakhir($assignmentIds);

        foreach (array_chunk($assignmentIds, self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Kalibrasi_Penilai_Jawaban as j')
                ->join('N_PA_Kalibrasi_Penilai as k', 'k.Id_PA_Kalibrasi_Penilai', '=', 'j.PA_Kalibrasi_Penilai_Id')
                ->whereIn('k.PA_Assignment_Id', $potongan)
                ->orderBy('k.PA_Assignment_Id')
                ->orderByDesc('k.Id_PA_Kalibrasi_Penilai')
                ->get(['k.PA_Assignment_Id', 'k.Created_At', 'j.Kode_Soal_Sumber', 'j.Id_Master_Soal', 'j.Jawaban_Sesudah']);

            foreach ($baris as $b) {
                // Sama dengan skor: revisi atas isian yang sudah digantikan kiriman baru gugur.
                if ($this->kalibrasiBasi($b, $batas)) {
                    continue;
                }

                $kunci = self::kunciJawaban((string) $b->Kode_Soal_Sumber, $b->Id_Master_Soal === null ? null : (int) $b->Id_Master_Soal);
                $hasil[(int) $b->PA_Assignment_Id][$kunci] ??= (string) $b->Jawaban_Sesudah;
            }
        }

        return $hasil;
    }

    /**
     * Menimpa `Jawaban_Teks` pada baris milik pembaca lain dengan jawaban efektif.
     *
     * Untuk pembaca yang kuerinya sendiri harus tetap utuh (join ke butir paket, kategori,
     * penilai): mereka memilih `Id_PA_Assignment`, `Id_PA_Assignment_Score`,
     * `Kode_Soal_Sumber`, dan `Jawaban_Teks`, menyaring barisnya dengan `sqlAdaJawaban()`,
     * lalu menyerahkannya ke sini.
     *
     * Baris bagian yang punya anak esai DIURAIKAN menjadi satu baris per soal. Hasilnya:
     * `Jawaban_Teks` = teks polos efektif, `Jawaban_Html` = HTML aman efektif,
     * `Jawaban_Asli` = teks HC Learn, `Id_Master_Soal` dan `Pertanyaan` (null pada format
     * lama). Jawaban kosong dibuang.
     *
     * @param  iterable<object>  $baris
     * @return Collection<int, object>
     */
    public function timpaJawaban(iterable $baris): Collection
    {
        $baris = collect($baris);

        if ($baris->isEmpty()) {
            return $baris;
        }

        $revisi = $this->jawabanTerbaru($baris->pluck('Id_PA_Assignment')->all());
        $esai = $this->esaiPerBaris($baris->pluck('Id_PA_Assignment_Score')->filter()->all());

        return $baris->flatMap(function (object $b) use ($revisi, $esai) {
            return array_map(function (array $j) use ($b, $revisi) {
                $r = clone $b;
                $baru = $revisi[(int) $b->Id_PA_Assignment][self::kunciJawaban((string) $b->Kode_Soal_Sumber, $j['id'])] ?? null;
                $r->Id_Master_Soal = $j['id'];
                $r->Pertanyaan = $j['pertanyaan'];
                $r->Jawaban_Asli = $j['teks'];
                $r->Jawaban_Teks = $baru === null ? $j['teks'] : $this->teksKaya->teksPolos($baru);
                $r->Jawaban_Html = $baru ?? $j['html'];
                $r->Jawaban_Terkalibrasi = $baru !== null;

                return $r;
            }, $this->jawabanBaris($b, $esai));
        })->values();
    }

    /**
     * Kunci satu jawaban di dalam satu penugasan: kode soal (bagian), ditambah id soal pada
     * format per soal. Dipakai penyimpan kalibrasi DAN pembacanya, jadi hanya ada satu bentuk.
     */
    public static function kunciJawaban(string $kodeSoal, ?int $idMasterSoal): string
    {
        return $kodeSoal."\x1F".($idMasterSoal ?? '');
    }

    /**
     * Potongan SQL "baris skor ini punya jawaban teks", untuk pembaca yang hanya perlu tahu
     * ADA-TIDAKNYA jawaban (cakupan jenis kualitatif, dasbor admin). Jawaban format per soal
     * tinggal di anak esai, sehingga memeriksa `Jawaban_Teks` bagian saja akan melewatkannya.
     */
    public static function sqlAdaJawaban(string $aliasSkor): string
    {
        return "(({$aliasSkor}.Jawaban_Teks IS NOT NULL AND {$aliasSkor}.Jawaban_Teks <> '')"
            .' OR EXISTS (SELECT 1 FROM N_PA_Assignment_Score_Esai esai_ada'
            ." WHERE esai_ada.PA_Assignment_Score_Id = {$aliasSkor}.Id_PA_Assignment_Score"
            ." AND esai_ada.Jawaban_Teks IS NOT NULL AND esai_ada.Jawaban_Teks <> ''))";
    }

    /**
     * Anak esai berjawab per baris skor, urut `Urutan_Tampil`.
     *
     * @param  list<int|string>  $scoreIds
     * @return array<int, list<object>>
     */
    private function esaiPerBaris(array $scoreIds): array
    {
        $hasil = [];

        foreach (array_chunk($this->bersihkan($scoreIds), self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Assignment_Score_Esai')
                ->whereIn('PA_Assignment_Score_Id', $potongan)
                ->whereNotNull('Jawaban_Teks')
                ->where('Jawaban_Teks', '<>', '')
                ->orderBy('PA_Assignment_Score_Id')
                ->orderBy('Urutan_Tampil')
                ->get(['PA_Assignment_Score_Id', 'Id_Master_Soal', 'Pertanyaan', 'Jawaban_Teks']);

            foreach ($baris as $e) {
                $hasil[(int) $e->PA_Assignment_Score_Id][] = $e;
            }
        }

        return $hasil;
    }

    /**
     * Jawaban asli sebuah baris skor: satu per anak esai bila ada, atau teks bagiannya.
     *
     * @param  array<int, list<object>>  $esai
     * @return list<array{id:?int, pertanyaan:?string, per_soal:bool, teks:string, html:string}>
     */
    private function jawabanBaris(object $b, array $esai): array
    {
        $anak = $esai[(int) ($b->Id_PA_Assignment_Score ?? 0)] ?? [];

        if ($anak !== []) {
            /* Isi `Esai[]` datang sebagai HTML Quill apa adanya (sejak 28 Sep 2026) atau teks
               polos (kiriman lebih lama). Disimpan mentah, dibersihkan di sini: `html` untuk
               layar yang menggambar teks kaya, `teks` untuk layar dan catatan berteks biasa. */
            return array_map(function (object $e) {
                $html = $this->teksKaya->dariEsaiHcLearn((string) $e->Jawaban_Teks);

                return [
                    'id' => $e->Id_Master_Soal === null ? null : (int) $e->Id_Master_Soal,
                    'pertanyaan' => $e->Pertanyaan === null
                        ? null
                        : $this->teksKaya->teksPolos($this->teksKaya->dariEsaiHcLearn((string) $e->Pertanyaan)),
                    'per_soal' => true,
                    'teks' => $this->teksKaya->teksPolos($html),
                    'html' => $html,
                ];
            }, $anak);
        }

        // Format lama: `Jawaban_Teks` bagian selalu teks polos dari HC Learn.
        $teks = (string) ($b->Jawaban_Teks ?? '');

        return trim($teks) === '' ? [] : [[
            'id' => null, 'pertanyaan' => null, 'per_soal' => false,
            'teks' => $teks, 'html' => $this->teksKaya->dariTeksPolos($teks),
        ]];
    }

    /**
     * Payload terpeta TERAKHIR per penugasan. Satu penugasan bisa punya beberapa payload
     * bila kiriman berbeda isi datang dua kali, dan yang berlaku adalah yang terakhir.
     * Aturannya sama persis dengan pemuat lama di PaResultCalculationService, supaya
     * perpindahan ke sini tidak mengubah hasil siapa pun.
     *
     * @param  list<int>  $assignmentIds
     * @return array<int, int> Id_PA_Assignment_Payload => Id_PA_Assignment
     */
    private function payloadTerakhir(array $assignmentIds): array
    {
        $peta = [];

        foreach (array_chunk($assignmentIds, self::POTONGAN) as $potongan) {
            $baris = DB::table('N_PA_Assignment_Payload as p')
                ->join('N_PA_Assignment_Integration as i',
                    'i.Id_PA_Assignment_Integration', '=', 'p.PA_Assignment_Integration_Id')
                ->whereIn('i.PA_Assignment_Id', $potongan)
                ->where('p.Flag_Terpeta', 'Y')
                ->orderBy('i.PA_Assignment_Id')
                ->orderByDesc('p.Id_PA_Assignment_Payload')
                ->get(['p.Id_PA_Assignment_Payload', 'i.PA_Assignment_Id']);

            $sudah = [];

            foreach ($baris as $b) {
                $a = (int) $b->PA_Assignment_Id;

                if (isset($sudah[$a])) {
                    continue;
                }

                $sudah[$a] = true;
                $peta[(int) $b->Id_PA_Assignment_Payload] = $a;
            }
        }

        return $peta;
    }

    /**
     * @param  array<int|string>  $ids
     * @return list<int>
     */
    private function bersihkan(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $x) => $x > 0)));
    }
}
