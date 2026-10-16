<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Services\KPI\PerformanceAppraisal\Balancing\PaBalancingStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Tahap "Kesimpulan Kualitatif" di penutupan siklus: admin merangkum jawaban kualitatif
 * para penilai (Strength, Development Area, ...) menjadi satu catatan resmi per kategori.
 *
 * KENAPA TAHAP SENDIRI, SEBELUM BALANCING
 *
 * Sesi Balancing mengambil catatan admin yang AKTIF sebagai isi awal kualitatif di board
 * forum (PaBalancingSesiService, T3). Kesimpulan yang belum ditulis berarti forum mulai dari
 * kotak kosong. Tahap ini TIDAK mengunci apa pun: menerbitkan tanpa kesimpulan tetap sah.
 *
 * Penyimpanan memakai PaResultNoteService apa adanya (catatan lama dinonaktifkan, bukan
 * dihapus), sehingga riwayatnya ikut terbaca di Riwayat Perhitungan.
 */
class PaKesimpulanKualitatifService
{
    public const MAKS_PER_HALAMAN = 100;

    public const BELUM = 'BELUM';

    public const SUDAH = 'SUDAH';

    public function __construct(
        private readonly PaParticipantReadService $pembaca,
        private readonly PaTeksKaya $teksKaya,
    ) {}

    /**
     * Cacah seluruh siklus untuk kepala tahap dan chip saringan.
     *
     * `belum` hanya yang MASIH BISA ditulis. Hasil TERBIT tanpa kesimpulan dihitung terpisah
     * (`terkunci`): catatannya tak bisa diubah lagi, dan menghitungnya sebagai "belum" membuat
     * tahap ini tak pernah bisa selesai.
     *
     * @return array{total:int, sudah:int, belum:int, terkunci:int, terakhir:array{pada:string, oleh_id:int|null}|null}
     */
    public function ringkas(int $cycleId): array
    {
        /* SATU kueri untuk ketiga angka. SQL Server menolak SUM(CASE WHEN EXISTS ...) langsung,
           jadi penandanya dihitung di tabel turunan lalu dijumlah di luar — agregat atas KOLOM,
           bukan atas subkueri. Sampai 14 Okt 2026 ini tiga kueri terpisah yang masing-masing
           menilai ulang predikat jawaban kualitatif untuk seluruh siklus. */
        $ada = $this->sqlAdaCatatan();
        $hitung = DB::query()->fromSub(
            $this->dasar($cycleId)->selectRaw(
                "CASE WHEN {$ada} THEN 1 ELSE 0 END AS sudah, "
                ."CASE WHEN r.Status_Hasil = 'TERBIT' AND NOT {$ada} THEN 1 ELSE 0 END AS kunci"
            ),
            'x',
        )->selectRaw('COUNT(*) AS total, SUM(sudah) AS sudah, SUM(kunci) AS kunci')->first();

        $total = (int) ($hitung->total ?? 0);
        $sudah = (int) ($hitung->sudah ?? 0);
        $terkunci = (int) ($hitung->kunci ?? 0);

        $terakhir = DB::table('N_PA_Result_Note as n')
            ->join('N_PA_Result as r', 'r.Id_PA_Result', '=', 'n.PA_Result_Id')
            ->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('p.PA_Cycle_Id', $cycleId)
            ->whereNull('n.PA_Assignment_Id')
            ->orderByDesc('n.Created_At')
            ->first(['n.Created_At', 'n.Created_By']);

        return [
            'total' => $total,
            'sudah' => $sudah,
            'belum' => $total - $sudah - $terkunci,
            'terkunci' => $terkunci,
            'terakhir' => $terakhir === null ? null : [
                'pada' => substr((string) $terakhir->Created_At, 0, 19),
                'oleh_id' => $terakhir->Created_By === null ? null : (int) $terakhir->Created_By,
            ],
        ];
    }

    /**
     * Peserta yang AKAN terbit (DIHITUNG) tetapi belum punya kesimpulan padahal punya jawaban
     * kualitatif — pengingat sebelum menerbitkan, karena sesudah terbit kesimpulannya tidak
     * bisa ditulis lagi. `$participantIds` null = seluruh siklus.
     *
     * @param  list<int>|null  $participantIds
     * @return array{belum:int, contoh:list<string>}
     */
    public function belumSebelumTerbit(int $cycleId, ?array $participantIds = null): array
    {
        $baris = $this->dasar($cycleId)->where('r.Status_Hasil', 'DIHITUNG')
            ->whereRaw('NOT '.$this->sqlAdaCatatan())
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->get(['p.Id_PA_Participant', 'p.Nama_Karyawan_Saat_Cycle']);

        if ($participantIds !== null) {
            // Diiris di PHP: daftar terpilih bisa melewati batas 2.100 parameter SQL Server.
            $pilih = array_flip(array_map('intval', $participantIds));
            $baris = $baris->filter(fn ($b) => isset($pilih[(int) $b->Id_PA_Participant]));
        }

        return [
            'belum' => $baris->count(),
            'contoh' => $baris->take(5)->pluck('Nama_Karyawan_Saat_Cycle')->values()->all(),
        ];
    }

    /**
     * Daftar peserta yang punya jawaban kualitatif. Saringan dan paginasi di server.
     *
     * @param  array{status?:string|null, q?:string|null, id_divisi?:int|null, id_level?:int|null,
     *               halaman?:int, per_halaman?:int, dengan_opsi?:mixed}  $saringan
     * @return array<string, mixed>
     */
    public function daftar(int $cycleId, array $saringan = []): array
    {
        $perHalaman = max(1, min((int) ($saringan['per_halaman'] ?? 25), self::MAKS_PER_HALAMAN));
        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $q = $this->dasar($cycleId);

        $status = strtoupper(trim((string) ($saringan['status'] ?? '')));

        if ($status === self::SUDAH) {
            $q->whereRaw($this->sqlAdaCatatan());
        } elseif ($status === self::BELUM) {
            $q->whereRaw('NOT '.$this->sqlAdaCatatan())->where('r.Status_Hasil', '<>', 'TERBIT');
        }

        if (! empty($saringan['id_divisi'])) {
            $q->where('p.ID_Divisi_Saat_Cycle', (int) $saringan['id_divisi']);
        }

        if (! empty($saringan['id_level'])) {
            $q->where('p.ID_Level_Saat_Cycle', (int) $saringan['id_level']);
        }

        $cari = trim((string) ($saringan['q'] ?? ''));

        if ($cari !== '') {
            $q->where(fn ($w) => $w->where('p.Nama_Karyawan_Saat_Cycle', 'LIKE', '%'.$cari.'%')
                ->orWhere('p.Kode_Karyawan', 'LIKE', $cari.'%'));
        }

        // Tanpa saringan, totalnya PERSIS total ringkasan — tidak perlu menilai predikatnya lagi.
        $ringkas = $this->ringkas($cycleId);
        $tanpaSaringan = $status === '' && empty($saringan['id_divisi']) && empty($saringan['id_level']) && $cari === '';
        $total = $tanpaSaringan ? $ringkas['total'] : (clone $q)->count();
        $baris = $q->orderBy('p.Nama_Karyawan_Saat_Cycle')->orderBy('p.Id_PA_Participant')
            ->forPage($halaman, $perHalaman)
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Level_Saat_Cycle', 'p.Nama_Jabatan_Saat_Cycle',
                'r.Id_PA_Result', 'r.Status_Hasil',
            ]);

        $pid = $baris->pluck('Id_PA_Participant')->map(fn ($v) => (int) $v)->all();
        $rid = $baris->pluck('Id_PA_Result')->map(fn ($v) => (int) $v)->all();
        $jawaban = $this->cacahJawaban($pid);
        $catatan = $this->cacahCatatan($rid);
        $balancing = $this->ikutBalancing($pid);
        $nama = $this->namaPengguna(array_column($catatan, 'oleh'));

        return [
            'peserta' => $baris->map(function ($r) use ($jawaban, $catatan, $balancing, $nama) {
                $j = $jawaban[(int) $r->Id_PA_Participant] ?? ['kategori' => [], 'penilai' => 0, 'jawaban' => 0];
                $c = $catatan[(int) $r->Id_PA_Result] ?? ['kategori' => [], 'pada' => null, 'oleh' => null];
                $kategori = array_values(array_unique([...$j['kategori'], ...$c['kategori']]));

                return [
                    'participant_id' => (int) $r->Id_PA_Participant,
                    'kode_karyawan' => $r->Kode_Karyawan,
                    'nama' => $r->Nama_Karyawan_Saat_Cycle,
                    'divisi' => $r->Nama_Divisi_Saat_Cycle,
                    'level' => $r->Nama_Level_Saat_Cycle,
                    'jabatan' => $r->Nama_Jabatan_Saat_Cycle,
                    'status_hasil' => $r->Status_Hasil,
                    'terkunci' => $r->Status_Hasil === 'TERBIT',
                    'jumlah_penilai' => $j['penilai'],
                    'jumlah_jawaban' => $j['jawaban'],
                    'kategori_total' => count($kategori),
                    'kategori_tersimpul' => count(array_intersect($kategori, $c['kategori'])),
                    'status' => $c['kategori'] === [] ? self::BELUM : self::SUDAH,
                    'ikut_balancing' => isset($balancing[(int) $r->Id_PA_Participant]),
                    'terakhir' => $c['pada'] === null ? null
                        : ['pada' => $c['pada'], 'oleh' => $c['oleh'] === null ? 'Sistem' : ($nama[$c['oleh']] ?? "Pengguna #{$c['oleh']}")],
                ];
            })->values()->all(),
            'total' => $total,
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'ringkas' => $ringkas,
        ] + (empty($saringan['dengan_opsi']) ? [] : ['opsi' => $this->opsi($cycleId)]);
    }

    /**
     * Bahan kerja satu peserta: jawaban penilai per kategori kualitatif dan catatan admin
     * yang sedang berlaku.
     *
     * @return array<string, mixed>
     */
    public function ruangKerja(int $participantId): array
    {
        $p = DB::table('N_PA_Participant as p')
            ->leftJoin('N_PA_Result as r', function ($j) {
                $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('r.Flag_Aktif', 'Y');
            })
            ->where('p.Id_PA_Participant', $participantId)
            ->first([
                'p.Id_PA_Participant', 'p.PA_Cycle_Id', 'p.Kode_Karyawan', 'p.Nama_Karyawan_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Level_Saat_Cycle', 'p.Nama_Jabatan_Saat_Cycle',
                'r.Id_PA_Result', 'r.Status_Hasil',
            ]);

        if ($p === null) {
            throw new RuntimeException("Peserta {$participantId} tidak ada.");
        }

        $kamus = $this->kamusKualitatif();
        $perNama = collect($kamus)->keyBy('nama');
        $kategori = [];

        foreach ($this->pembaca->kualitatifPeserta($participantId)['jawaban'] as $j) {
            $k = $perNama->get((string) ($j['Nama_Competency'] ?? ''));
            $teks = trim((string) ($j['Jawaban_Teks'] ?? '')); // jawaban-mentah: sudah efektif (kualitatifPeserta → jawabanNaratif).

            if ($k === null || $teks === '') {
                continue;
            }

            $kategori[$k['kode']] ??= $k + ['jawaban' => [], 'pertanyaan' => [], 'catatan' => ''];

            /* Bunyi soal yang dijawab, sekali per soal — admin menyimpulkan jawaban tanpa tahu
               pertanyaannya adalah menebak (defect UAT 8 Okt). */
            $soal = trim(strip_tags((string) ($j['Pertanyaan'] ?? '')));
            if ($soal !== '' && ! in_array($soal, $kategori[$k['kode']]['pertanyaan'], true)) {
                $kategori[$k['kode']]['pertanyaan'][] = $soal;
            }

            $kategori[$k['kode']]['jawaban'][] = [
                'kategori_penilai' => $j['Kode_Kategori'] ?? null,
                'nama_kategori' => $j['Nama_Kategori'] ?? null,
                'penilai' => $j['Nama_Penilai_Saat_Cycle'] ?? null,
                'pertanyaan' => $j['Pertanyaan'] ?? null,
                'html' => $this->teksKaya->bersihkan($this->teksKaya->dariEsaiHcLearn($teks)),
            ];
        }

        if ($p->Id_PA_Result !== null) {
            $catatan = DB::table('N_PA_Result_Note as n')
                ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'n.PA_Competency_Id')
                ->where('n.PA_Result_Id', $p->Id_PA_Result)->where('n.Flag_Aktif', 'Y')->whereNull('n.PA_Assignment_Id')
                ->orderBy('n.Urutan_Tampil')
                ->get(['k.Kode_Competency', 'n.Isi'])
                ->groupBy('Kode_Competency');

            foreach ($catatan as $kode => $isi) {
                $k = $kamus[$kode] ?? null;

                if ($k === null) {
                    continue;
                }

                $kategori[$kode] ??= $k + ['jawaban' => [], 'pertanyaan' => []];
                $kategori[$kode]['catatan'] = $isi->pluck('Isi')->implode('');
            }
        }

        $kategori = collect($kategori)->sortBy('urut')->map(fn ($k) => array_diff_key($k, ['urut' => 1]))->values()->all();

        return [
            'peserta' => [
                'participant_id' => (int) $p->Id_PA_Participant,
                'cycle_id' => (int) $p->PA_Cycle_Id,
                'kode_karyawan' => $p->Kode_Karyawan,
                'nama' => $p->Nama_Karyawan_Saat_Cycle,
                'divisi' => $p->Nama_Divisi_Saat_Cycle,
                'level' => $p->Nama_Level_Saat_Cycle,
                'jabatan' => $p->Nama_Jabatan_Saat_Cycle,
                'status_hasil' => $p->Status_Hasil,
            ],
            // Hasil terbit tidak bisa diubah catatannya (PaResultNoteService menolak).
            'terkunci' => $p->Status_Hasil === 'TERBIT',
            'punya_hasil' => $p->Id_PA_Result !== null,
            'kategori' => $kategori,
        ];
    }

    /* ------------------------------------------------------------------ */

    /** Peserta DIIKUTKAN yang punya baris hasil dan sedikitnya satu jawaban kualitatif. */
    private function dasar(int $cycleId)
    {
        return DB::table('N_PA_Participant as p')
            ->join('N_PA_Result as r', function ($j) {
                $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')->where('r.Flag_Aktif', 'Y');
            })
            ->where('p.PA_Cycle_Id', $cycleId)
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Status_Peserta', 'DIIKUTKAN')
            ->whereRaw($this->sqlAdaJawaban());
    }

    /**
     * Predikat "peserta ini punya jawaban kualitatif" — SATU-SATUNYA definisi di modul.
     *
     * Dipakai tahap ini DAN antrean "Ulasan Qualitative Questions" di dashboard admin
     * (PaAdminDashboardService). Sampai 8 Oktober 2026 antrean itu memegang salinannya
     * sendiri yang menghitung jawaban teks APA PUN — termasuk komentar pada butir berskor —
     * tanpa saringan jenis KUALITATIF dan tanpa saringan integrasi aktif, sehingga angka
     * antrean dan isi tahap ini berselisih untuk orang yang sama.
     *
     * @param  string  $aliasPeserta  Alias N_PA_Participant pada kueri pemanggil.
     */
    public static function sqlAdaJawabanKualitatif(string $aliasPeserta = 'p'): string
    {
        return "EXISTS (SELECT 1
                  FROM N_PA_Assignment a
                  JOIN N_PA_Assignment_Integration i ON i.PA_Assignment_Id = a.Id_PA_Assignment AND i.Flag_Aktif = 'Y'
                  JOIN N_PA_Assignment_Payload pl ON pl.PA_Assignment_Integration_Id = i.Id_PA_Assignment_Integration
                  JOIN N_PA_Assignment_Score sc ON sc.PA_Assignment_Payload_Id = pl.Id_PA_Assignment_Payload AND sc.Flag_Aktif = 'Y'
                  JOIN N_PA_Competency k ON k.Id_PA_Competency = sc.PA_Competency_Id
                  JOIN N_PA_Competency_Type t ON t.Id_PA_Competency_Type = k.PA_Competency_Type_Id
                 WHERE a.PA_Participant_Id = {$aliasPeserta}.Id_PA_Participant
                   AND a.Flag_Aktif = 'Y'
                   AND ".PaKompetensiKualitatif::sqlBarisSkor('sc', 't').'
                   AND '.PaSkorEfektif::sqlAdaJawaban('sc').')';
    }

    /**
     * Predikat "hasil ini sudah punya kesimpulan admin" (catatan aktif ber-`PA_Assignment_Id`
     * NULL). Dibagi dengan dashboard admin untuk alasan yang sama dengan predikat di atas.
     *
     * @param  string  $aliasHasil  Alias N_PA_Result pada kueri pemanggil.
     */
    public static function sqlAdaKesimpulan(string $aliasHasil = 'r'): string
    {
        return "EXISTS (SELECT 1 FROM N_PA_Result_Note nt
                 WHERE nt.PA_Result_Id = {$aliasHasil}.Id_PA_Result AND nt.Flag_Aktif = 'Y' AND nt.PA_Assignment_Id IS NULL)";
    }

    private function sqlAdaJawaban(): string
    {
        return self::sqlAdaJawabanKualitatif('p');
    }

    private function sqlAdaCatatan(): string
    {
        return self::sqlAdaKesimpulan('r');
    }

    /** @return array<string, array{kode:string, nama:string, deskripsi:?string, urut:int}> */
    private function kamusKualitatif(): array
    {
        return DB::table('N_PA_Competency as k')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->whereRaw(PaKompetensiKualitatif::sqlKompetensi('k', 't'))->where('k.Flag_Aktif', 'Y')
            ->orderBy('k.Urutan_Tampil')
            ->get(['k.Kode_Competency', 'k.Nama_Competency', 'k.Deskripsi', 'k.Urutan_Tampil'])
            ->mapWithKeys(fn ($k) => [$k->Kode_Competency => [
                'kode' => $k->Kode_Competency, 'nama' => $k->Nama_Competency,
                'deskripsi' => $k->Deskripsi, 'urut' => (int) $k->Urutan_Tampil,
            ]])->all();
    }

    /** @return array<int, array{kategori:list<string>, penilai:int, jawaban:int}> */
    private function cacahJawaban(array $pid): array
    {
        if ($pid === []) {
            return [];
        }

        $hasil = [];

        DB::table('N_PA_Assignment as a')
            ->join('N_PA_Assignment_Integration as i', function ($j) {
                $j->on('i.PA_Assignment_Id', '=', 'a.Id_PA_Assignment')->where('i.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Assignment_Payload as pl', 'pl.PA_Assignment_Integration_Id', '=', 'i.Id_PA_Assignment_Integration')
            ->join('N_PA_Assignment_Score as sc', function ($j) {
                $j->on('sc.PA_Assignment_Payload_Id', '=', 'pl.Id_PA_Assignment_Payload')->where('sc.Flag_Aktif', 'Y');
            })
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'sc.PA_Competency_Id')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'k.PA_Competency_Type_Id')
            ->whereIn('a.PA_Participant_Id', $pid)
            ->where('a.Flag_Aktif', 'Y')
            ->whereRaw(PaKompetensiKualitatif::sqlBarisSkor('sc', 't'))
            ->whereRaw(PaSkorEfektif::sqlAdaJawaban('sc'))
            ->groupBy('a.PA_Participant_Id', 'k.Kode_Competency')
            ->get([
                'a.PA_Participant_Id as pid', 'k.Kode_Competency as kode',
                DB::raw('COUNT(DISTINCT a.Id_PA_Assignment) as penilai'), DB::raw('COUNT(*) as jawaban'),
            ])
            ->each(function ($r) use (&$hasil) {
                $h = &$hasil[(int) $r->pid];
                $h ??= ['kategori' => [], 'penilai' => 0, 'jawaban' => 0];
                $h['kategori'][] = $r->kode;
                $h['penilai'] = max($h['penilai'], (int) $r->penilai);
                $h['jawaban'] += (int) $r->jawaban;
            });

        return $hasil;
    }

    /** @return array<int, array{kategori:list<string>, pada:?string, oleh:?int}> */
    private function cacahCatatan(array $rid): array
    {
        if ($rid === []) {
            return [];
        }

        $hasil = [];

        DB::table('N_PA_Result_Note as n')
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'n.PA_Competency_Id')
            ->whereIn('n.PA_Result_Id', $rid)->where('n.Flag_Aktif', 'Y')->whereNull('n.PA_Assignment_Id')
            ->orderBy('n.Created_At')
            ->get(['n.PA_Result_Id', 'k.Kode_Competency', 'n.Created_At', 'n.Created_By'])
            ->each(function ($r) use (&$hasil) {
                $h = &$hasil[(int) $r->PA_Result_Id];
                $h ??= ['kategori' => [], 'pada' => null, 'oleh' => null];
                $h['kategori'][] = $r->Kode_Competency;
                $h['pada'] = substr((string) $r->Created_At, 0, 19);
                $h['oleh'] = $r->Created_By === null ? null : (int) $r->Created_By;
            });

        foreach ($hasil as &$h) {
            $h['kategori'] = array_values(array_unique($h['kategori']));
        }

        return $hasil;
    }

    /** @return array<int, true> */
    private function ikutBalancing(array $pid): array
    {
        return $pid === [] ? [] : DB::table('N_PA_Balancing_Peserta as bp')
            ->join('N_PA_Balancing_Sesi as s', 's.Id_PA_Balancing_Sesi', '=', 'bp.PA_Balancing_Sesi_Id')
            ->whereIn('bp.PA_Participant_Id', $pid)->where('s.Status_Sesi', '<>', PaBalancingStatus::DIBATALKAN)
            ->distinct()->pluck('bp.PA_Participant_Id')->mapWithKeys(fn ($v) => [(int) $v => true])->all();
    }

    /** @return array{divisi: list<array{value:int,label:string}>, level: list<array{value:int,label:string}>} */
    private function opsi(int $cycleId): array
    {
        $opsi = fn (string $id, string $nama) => DB::table('N_PA_Participant')
            ->where('PA_Cycle_Id', $cycleId)->where('Flag_Aktif', 'Y')->where('Status_Peserta', 'DIIKUTKAN')
            ->whereNotNull($id)->groupBy($id, $nama)->orderBy($nama)
            ->get([$id.' as id', $nama.' as nama'])
            ->map(fn ($r) => ['value' => (int) $r->id, 'label' => (string) ($r->nama ?: "#{$r->id}")])->values()->all();

        return ['divisi' => $opsi('ID_Divisi_Saat_Cycle', 'Nama_Divisi_Saat_Cycle'), 'level' => $opsi('ID_Level_Saat_Cycle', 'Nama_Level_Saat_Cycle')];
    }

    /** @return array<int, string> */
    private function namaPengguna(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($v) => $v !== null)));

        return $ids === [] ? [] : DB::table('KPI_Users')->whereIn('Id_Users', $ids)->get(['Id_Users', 'Nama', 'Username'])
            ->mapWithKeys(fn ($u) => [(int) $u->Id_Users => trim((string) ($u->Nama ?? '')) ?: (string) $u->Username])->all();
    }
}
