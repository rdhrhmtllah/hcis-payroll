<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kiriman HC Learn yang MASUK tetapi tidak terpetakan penuh.
 *
 * ── Kenapa layanan ini ada ────────────────────────────────────────────────────
 *
 * Pemeta sudah lama memulangkan `tak_terpetakan`, `butir_hilang`, dan
 * `kontrak_lengkap` — tetapi hanya ke dalam badan respons callback. Yang membacanya HC
 * Learn; admin HCIS tidak pernah melihatnya di layar mana pun.
 *
 * Akibatnya terbukti pada 10 September 2026: penilaian pertama yang benar-benar diisi
 * orang masuk lengkap, tersimpan utuh, dijawab 200 — dan seluruh 13 butirnya mendarat tak
 * terpetakan karena kunci pemetaannya salah. Penugasannya tidak pernah ditandai selesai.
 * Tidak ada galat, tidak ada lencana merah, tidak ada baris di layar mana pun. Yang
 * memberi tahu akhirnya orangnya sendiri: "saya sudah isi, kok belum berubah".
 *
 * ── Dihitung ULANG, bukan dibaca dari catatan ─────────────────────────────────
 *
 * Keadaan tak lengkap TIDAK disimpan sebagai kolom. Ia dihitung dari yang tersimpan:
 * butir berskor yang kompetensinya kosong, dan butir paket yang kodenya tidak ada di
 * kiriman. Alasannya satu — begitu admin membetulkan ikatannya dan memetakan ulang,
 * catatan yang tersimpan akan basi sementara hitungan ini ikut benar dengan sendirinya.
 * Verdict yang membeku adalah verdict yang suatu hari berbohong.
 *
 * ── Yang SENGAJA tidak dianggap bermasalah ────────────────────────────────────
 *
 * `MENUNGGU_VERIFIKASI` memang datang tanpa butir — itu bagian kontraknya, bukan cacat.
 * Memasukkannya ke daftar ini membuat daftar penuh baris yang tidak menuntut pekerjaan
 * apa pun, dan yang benar-benar menuntut pekerjaan tenggelam di antaranya.
 */
class PaKirimanTinjauService
{
    public function __construct(
        private readonly PaPayloadMapperService $pemeta,
    ) {}

    /**
     * Daftar kiriman satu siklus yang perlu ditinjau.
     *
     * @return array{cycle_id:int, ringkasan:array<string,int>, baris:list<array<string,mixed>>}
     */
    public function daftar(int $cycleId): array
    {
        $payload = $this->payloadSiklus($cycleId);

        if ($payload === []) {
            return [
                'cycle_id' => $cycleId,
                'ringkasan' => ['kiriman' => 0, 'perlu_ditinjau' => 0],
                'baris' => [],
            ];
        }

        $takTerpetakan = $this->takTerpetakanPerPayload(array_keys($payload));
        $kodeMasuk = $this->kodePerPayload(array_keys($payload));
        $butirPaket = $this->butirPerPaket(array_column($payload, 'paket_id'));

        $baris = [];

        foreach ($payload as $id => $p) {
            $masuk = $kodeMasuk[$id] ?? [];
            $diharapkan = $butirPaket[$p['paket_id']] ?? [];

            /* Butir paket yang TIDAK ADA di kiriman. Butir tanpa kode ikut terhitung —
               ia mustahil dipenuhi kiriman mana pun, dan menyembunyikannya membuat paket
               yang separuh butirnya tak berkode tampak lengkap. */
            $hilang = [];

            foreach ($diharapkan as $kode) {
                if (! in_array($kode, $masuk, true)) {
                    $hilang[] = $kode;
                }
            }

            $tidakDikenal = $takTerpetakan[$id] ?? [];

            if ($tidakDikenal === [] && $hilang === []) {
                continue;
            }

            $baris[] = $p['identitas'] + [
                'payload_id' => $id,
                'tak_terpetakan' => $tidakDikenal,
                'butir_hilang' => $hilang,
                'butir_masuk' => count($masuk),
                'butir_diharapkan' => count($diharapkan),

                /* Saran tindakan disebut, bukan disimpulkan sendiri oleh admin. Dua sebab
                   yang berbeda menuntut dua pekerjaan yang berbeda, dan keduanya mudah
                   tertukar bila yang tampil hanya angka. */
                'saran' => $this->saran($tidakDikenal, $hilang),
            ];
        }

        return [
            'cycle_id' => $cycleId,
            'ringkasan' => ['kiriman' => count($payload), 'perlu_ditinjau' => count($baris)],
            'baris' => $baris,
        ];
    }

    /**
     * Memetakan ulang satu kiriman — dipakai sesudah ikatan soalnya dibetulkan.
     *
     * Aman diulang: pemeta menonaktifkan baris lama dan menulis baris baru dengan versi
     * pemeta berikutnya. Siklusnya diperiksa supaya payload milik siklus lain tidak bisa
     * disentuh dari layar siklus ini.
     *
     * @return array<string, mixed>
     */
    public function petakanUlang(int $cycleId, int $payloadId, ?int $olehUserId = null): array
    {
        $milik = DB::table('N_PA_Assignment_Payload as y')
            ->join('N_PA_Assignment_Integration as i', 'i.Id_PA_Assignment_Integration', '=', 'y.PA_Assignment_Integration_Id')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->where('y.Id_PA_Assignment_Payload', $payloadId)
            ->value('p.PA_Cycle_Id');

        if ($milik === null) {
            throw new RuntimeException("Kiriman {$payloadId} tidak ada.");
        }

        if ((int) $milik !== $cycleId) {
            throw new RuntimeException(
                "Kiriman {$payloadId} bukan milik siklus {$cycleId}."
            );
        }

        return $this->pemeta->petakan($payloadId, $olehUserId);
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Kiriman FINAL satu siklus, berkunci id payload.
     *
     * @return array<int, array{paket_id:?int, identitas:array<string,mixed>}>
     */
    private function payloadSiklus(int $cycleId): array
    {
        $hasil = [];

        foreach (DB::table('N_PA_Assignment_Payload as y')
            ->join('N_PA_Assignment_Integration as i', 'i.Id_PA_Assignment_Integration', '=', 'y.PA_Assignment_Integration_Id')
            ->join('N_PA_Assignment as a', 'a.Id_PA_Assignment', '=', 'i.PA_Assignment_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'a.PA_Participant_Id')
            ->leftJoin('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'a.PA_Rater_Category_Id')
            ->where('p.PA_Cycle_Id', $cycleId)

            /* Hanya penugasan yang MASIH berlaku: peserta aktif dan diikutkan, penugasan aktif
               dan tidak dibatalkan — basis yang sama dengan pemantauan pengisian. Kiriman
               milik penugasan yang sudah dicabut atau peserta yang dinonaktifkan tidak akan
               ikut dihitung apa pun hasil pemetaannya; memasukkannya membuat siklus nonaktif
               #10244 melaporkan 49 dari 98 kiriman "perlu ditinjau" yang tak bisa ditindaklanjuti. */
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->where('a.Flag_Aktif', 'Y')
            ->where('a.Status_Penugasan', '<>', PaStatusPenugasan::DIBATALKAN)

            /* MENUNGGU_VERIFIKASI memang datang tanpa butir — itu kontraknya. */
            ->where('y.Status_Skor', PaPayloadMapperService::SKOR_FINAL)
            ->orderByDesc('y.Diterima_At')
            ->get([
                'y.Id_PA_Assignment_Payload as payload_id',
                'y.Diterima_At', 'y.Status_Skor', 'y.Nama_Tes', 'y.Total_Nilai',
                'i.PA_Cycle_Question_Package_Id as paket_id',
                'a.Id_PA_Assignment', 'a.Status_Penugasan',
                'a.Kode_Karyawan_Penilai', 'a.Nama_Penilai_Saat_Cycle',
                'c.Kode_Kategori',
                'p.Kode_Karyawan as kode_peserta', 'p.Nama_Karyawan_Saat_Cycle as nama_peserta',
            ]) as $r) {
            $hasil[(int) $r->payload_id] = [
                'paket_id' => $r->paket_id === null ? null : (int) $r->paket_id,
                'identitas' => [
                    'assignment_id' => (int) $r->Id_PA_Assignment,
                    'status_penugasan' => (string) $r->Status_Penugasan,
                    'diterima_at' => (string) $r->Diterima_At,
                    'nama_tes' => $r->Nama_Tes,
                    'total_nilai' => $r->Total_Nilai === null ? null : (float) $r->Total_Nilai,
                    'kode_penilai' => trim((string) $r->Kode_Karyawan_Penilai),
                    'nama_penilai' => (string) $r->Nama_Penilai_Saat_Cycle,
                    'kategori' => (string) ($r->Kode_Kategori ?? ''),
                    'kode_peserta' => trim((string) $r->kode_peserta),
                    'nama_peserta' => (string) $r->nama_peserta,
                ],
            ];
        }

        return $hasil;
    }

    /**
     * Kode butir yang TERSIMPAN tetapi tidak menemukan kompetensinya.
     *
     * @param  list<int>  $payloadId
     * @return array<int, list<string>>
     */
    private function takTerpetakanPerPayload(array $payloadId): array
    {
        $hasil = [];

        foreach (DB::table('N_PA_Assignment_Score')
            ->whereIn('PA_Assignment_Payload_Id', $payloadId)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('PA_Competency_Id')
            ->orderBy('Urutan_Tampil')
            ->get(['PA_Assignment_Payload_Id as id', 'Kode_Soal_Sumber']) as $r) {
            $hasil[(int) $r->id][] = (string) $r->Kode_Soal_Sumber;
        }

        return $hasil;
    }

    /**
     * Seluruh kode butir yang masuk pada tiap kiriman.
     *
     * @param  list<int>  $payloadId
     * @return array<int, list<string>>
     */
    private function kodePerPayload(array $payloadId): array
    {
        $hasil = [];

        foreach (DB::table('N_PA_Assignment_Score')
            ->whereIn('PA_Assignment_Payload_Id', $payloadId)
            ->where('Flag_Aktif', 'Y')
            ->get(['PA_Assignment_Payload_Id as id', 'Kode_Soal_Sumber']) as $r) {
            $hasil[(int) $r->id][] = (string) $r->Kode_Soal_Sumber;
        }

        return $hasil;
    }

    /**
     * Butir yang DIHARAPKAN tiap paket, dengan bentuk kunci yang sama dengan pemeta.
     *
     * @param  list<?int>  $paketId
     * @return array<int, list<string>>
     */
    private function butirPerPaket(array $paketId): array
    {
        $id = array_values(array_unique(array_filter($paketId, fn ($x) => $x !== null)));

        if ($id === []) {
            return [];
        }

        $hasil = [];

        foreach (DB::table('N_PA_Cycle_Question_Package_Item')
            ->whereIn('PA_Cycle_Question_Package_Id', $id)
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get([
                'PA_Cycle_Question_Package_Id as paket',
                'Id_PA_Cycle_Question_Package_Item as butir',
                'Kode_Soal_Sumber as kode',
            ]) as $r) {
            /* Bentuk kunci butir tanpa kode DISAMAKAN dengan pemeta. Kalau berbeda, layar
               ini melaporkan "hilang" untuk butir yang menurut pemeta hadir — dua jawaban
               atas satu pertanyaan, dan admin mengejar pekerjaan yang tidak ada. */
            $hasil[(int) $r->paket][] = ($r->kode === null || $r->kode === '')
                ? '(butir tanpa kode #'.$r->butir.')'
                : (string) $r->kode;
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $takTerpetakan
     * @param  list<string>  $hilang
     */
    private function saran(array $takTerpetakan, array $hilang): string
    {
        if ($takTerpetakan !== [] && $hilang !== []) {
            return 'Kode soal yang dikirim HC Learn tidak sama dengan kode pada paket siklus ini. '
                .'Periksa ikatan paket soal di Langkah 2, betulkan, lalu petakan ulang kiriman ini.';
        }

        if ($takTerpetakan !== []) {
            return 'HC Learn mengirim butir yang kodenya tidak dikenali paket siklus ini. '
                .'Nilainya TERSIMPAN tetapi belum masuk ke kompetensi mana pun.';
        }

        return 'Sebagian butir paket tidak dikirim HC Learn. Penilaian ini belum bisa dianggap '
            .'lengkap; tanyakan ke HC Learn bila butirnya memang ada di paket mereka.';
    }
}
