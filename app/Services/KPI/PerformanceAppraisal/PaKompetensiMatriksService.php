<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Matriks Kompetensi per KOMPETENSI dan tingkatnya (DDL-2026-175) — satu pintu.
 *
 * SUMBER KEBENARAN. "Kompetensi apa yang dinilai di level ini, dan tingkat berapa yang
 * dituntut" dijawab `N_PA_Competency_Level_Requirement`, bukan lagi penjabaran "jenis wajib →
 * seluruh kompetensi aktif jenis itu". Penjabaran lama memaksa uji esai 8 Okt menonaktifkan
 * CV04 untuk SEMUA level hanya supaya level 4/5 bisa memakai CV01–CV03.
 *
 * KOMPATIBEL KE BELAKANG. `N_PA_Competency_Requirement` (per jenis) tetap ada dan DISELARASKAN
 * setiap matriks disimpan: jenis wajib bila sedikitnya satu kompetensinya dituntut di level itu.
 * Resolver aturan peserta, override per peserta, dan salinan per jenis tidak perlu berubah.
 *
 * SIKLUS. Salinan per siklus (`N_PA_Cycle_Competency_Level_Snapshot`) dibuat bersama salinan
 * per jenis saat siklus dibuat, termasuk bunyi tingkat dan Expected Behaviour-nya. Siklus tanpa
 * salinan (dibuat sebelum DDL-2026-175) membaca matriks master.
 *
 * Tahap ini ACUAN TAMPILAN: tingkat tidak ikut menghitung nilai.
 */
class PaKompetensiMatriksService
{
    /* ------------------------------------------------------------------ *
     * Baca: kompetensi wajib                                              *
     * ------------------------------------------------------------------ */

    /**
     * Kompetensi aktif yang dituntut per "level|jenis" dari MATRIKS MASTER.
     *
     * @return Collection<string, Collection<int, object>> objek: id, kode, nama, PA_Competency_Type_Id, Kode_Competency
     */
    public function perLevelJenis(): Collection
    {
        return DB::table('N_PA_Competency_Level_Requirement as m')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'm.PA_Competency_Id')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
            ->where('m.Flag_Aktif', 'Y')
            ->where('c.Flag_Aktif', 'Y')
            ->where('t.Flag_Aktif', 'Y')
            ->orderBy('c.Urutan_Tampil')
            ->orderBy('c.Kode_Competency')
            ->get(['m.ID_Level', 'c.PA_Competency_Type_Id', 'c.Id_PA_Competency as id', 'c.Kode_Competency as kode',
                'c.Nama_Competency as nama', 'c.Kode_Competency'])
            ->groupBy(fn ($r) => $r->ID_Level.'|'.$r->PA_Competency_Type_Id);
    }

    /**
     * Sama dengan `perLevelJenis()`, tetapi dari SALINAN siklus bila ada.
     *
     * @return Collection<string, Collection<int, object>>
     */
    public function perLevelJenisSiklus(int $cycleId): Collection
    {
        if (! $this->siklusPunyaSalinan($cycleId)) {
            return $this->perLevelJenis();
        }

        return DB::table('N_PA_Cycle_Competency_Level_Snapshot as s')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 's.PA_Competency_Id')
            ->where('s.PA_Cycle_Id', $cycleId)
            ->orderBy('c.Urutan_Tampil')
            ->orderBy('c.Kode_Competency')
            ->get(['s.ID_Level', 'c.PA_Competency_Type_Id', 'c.Id_PA_Competency as id', 'c.Kode_Competency as kode',
                'c.Nama_Competency as nama', 'c.Kode_Competency'])
            ->groupBy(fn ($r) => $r->ID_Level.'|'.$r->PA_Competency_Type_Id);
    }

    /** @return list<int> id kompetensi yang dituntut di satu level (master). */
    public function idWajibLevel(int $levelId): array
    {
        return $this->perLevelJenis()
            ->filter(fn ($g, $k) => str_starts_with((string) $k, $levelId.'|'))
            ->flatten(1)
            ->pluck('id')
            ->map(fn ($x) => (int) $x)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    public function siklusPunyaSalinan(int $cycleId): bool
    {
        return DB::table('N_PA_Cycle_Competency_Level_Snapshot')->where('PA_Cycle_Id', $cycleId)->exists();
    }

    /* ------------------------------------------------------------------ *
     * Baca: perilaku yang dituntut                                        *
     * ------------------------------------------------------------------ */

    /**
     * Tingkat dan Expected Behaviour yang DITUNTUT untuk level orang itu, per kompetensi.
     * Siklus bersalinan membaca salinannya; selain itu matriks master. Kompetensi tanpa tingkat
     * tidak dikembalikan — pemanggil memakai Expected Behaviour umum kompetensi itu.
     *
     * @param  list<int>|null  $competencyIds  null = semua
     * @return array<int, array{tingkat:int, nama_tingkat:string, expected_behaviour:?string}>
     */
    public function perilakuDituntut(?int $cycleId, ?int $levelId, ?array $competencyIds = null): array
    {
        if ($levelId === null) {
            return [];
        }

        $kolomId = 'PA_Competency_Id';

        if ($cycleId !== null && $this->siklusPunyaSalinan($cycleId)) {
            $q = DB::table('N_PA_Cycle_Competency_Level_Snapshot')
                ->where('PA_Cycle_Id', $cycleId)->where('ID_Level', $levelId)->whereNotNull('Tingkat')
                ->select(['PA_Competency_Id', 'Tingkat', 'Nama_Tingkat', 'Expected_Behaviour']);
        } else {
            $q = DB::table('N_PA_Competency_Level_Requirement as m')
                ->join('N_PA_Competency_Proficiency as p', 'p.Id_PA_Competency_Proficiency', '=', 'm.PA_Competency_Proficiency_Id')
                ->where('m.Flag_Aktif', 'Y')->where('m.ID_Level', $levelId)
                ->select(['m.PA_Competency_Id', 'p.Tingkat', 'p.Nama_Tingkat', 'p.Expected_Behaviour']);
            $kolomId = 'm.PA_Competency_Id';
        }

        if ($competencyIds !== null) {
            if ($competencyIds === []) {
                return [];
            }
            $q->whereIn($kolomId, array_map('intval', $competencyIds));
        }

        $hasil = [];
        foreach ($q->get() as $r) {
            $hasil[(int) $r->PA_Competency_Id] = [
                'tingkat' => (int) $r->Tingkat,
                'nama_tingkat' => (string) $r->Nama_Tingkat,
                'expected_behaviour' => $r->Expected_Behaviour,
            ];
        }

        return $hasil;
    }

    /**
     * Menimpa `expected_behaviour` baris-baris tampilan dengan perilaku tingkat yang dituntut.
     * Baris tanpa tingkat dibiarkan memakai Expected Behaviour umum.
     *
     * @param  iterable<array<string, mixed>|object>  $baris
     * @return list<array<string, mixed>>
     */
    public function terapkanPerilaku(iterable $baris, ?int $cycleId, ?int $levelId, string $kunciId = 'id', string $kunciPerilaku = 'expected_behaviour'): array
    {
        $baris = collect($baris)->map(fn ($r) => (array) $r)->values();
        $perilaku = $this->perilakuDituntut($cycleId, $levelId, $baris->pluck($kunciId)->filter()->map(fn ($x) => (int) $x)->all());

        return $baris->map(function ($r) use ($perilaku, $kunciId, $kunciPerilaku) {
            $p = $perilaku[(int) ($r[$kunciId] ?? 0)] ?? null;
            // Kuncinya SELALU ada (null = belum bertingkat) supaya bentuk respons tetap.
            $r['tingkat_dituntut'] = null;
            $r['nama_tingkat_dituntut'] = null;
            /* Expected Behaviour HANYA dari tingkat yang dituntut level ini (keputusan user 10 Okt
               2026). Expected Behaviour yang menempel langsung di kompetensi tidak dipakai lagi —
               tanpa tingkat, perilakunya memang belum ditetapkan, bukan diwakili teks umum. */
            $r[$kunciPerilaku] = null;
            if ($p !== null) {
                $r['tingkat_dituntut'] = $p['tingkat'];
                $r['nama_tingkat_dituntut'] = $p['nama_tingkat'];
                $r[$kunciPerilaku] = trim((string) $p['expected_behaviour']) !== '' ? $p['expected_behaviour'] : null;
            }

            return $r;
        })->all();
    }

    /**
     * Seperti `terapkanPerilaku()`, untuk baris yang hanya membawa KODE kompetensi.
     *
     * @param  iterable<array<string, mixed>|object>  $baris
     * @return list<array<string, mixed>>
     */
    public function terapkanPerilakuPerKode(iterable $baris, ?int $cycleId, ?int $levelId, string $kunciKode, string $kunciPerilaku): array
    {
        $baris = collect($baris)->map(fn ($r) => (array) $r)->values();
        $kode = $baris->pluck($kunciKode)->filter()->unique()->values()->all();

        if ($levelId === null || $kode === []) {
            return $baris->map(fn ($r) => array_merge($r, ['tingkat_dituntut' => null, 'nama_tingkat_dituntut' => null, $kunciPerilaku => null]))->all();
        }

        $idPerKode = DB::table('N_PA_Competency')->whereIn('Kode_Competency', $kode)
            ->pluck('Id_PA_Competency', 'Kode_Competency')->map(fn ($x) => (int) $x);
        $perilaku = $this->perilakuDituntut($cycleId, $levelId, $idPerKode->values()->all());

        return $baris->map(function ($r) use ($idPerKode, $perilaku, $kunciKode, $kunciPerilaku) {
            $p = $perilaku[$idPerKode[$r[$kunciKode] ?? ''] ?? 0] ?? null;
            // Kuncinya SELALU ada (null = belum bertingkat) supaya bentuk respons tetap.
            $r['tingkat_dituntut'] = null;
            $r['nama_tingkat_dituntut'] = null;
            /* Expected Behaviour HANYA dari tingkat yang dituntut level ini (keputusan user 10 Okt
               2026). Expected Behaviour yang menempel langsung di kompetensi tidak dipakai lagi —
               tanpa tingkat, perilakunya memang belum ditetapkan, bukan diwakili teks umum. */
            $r[$kunciPerilaku] = null;
            if ($p !== null) {
                $r['tingkat_dituntut'] = $p['tingkat'];
                $r['nama_tingkat_dituntut'] = $p['nama_tingkat'];
                $r[$kunciPerilaku] = trim((string) $p['expected_behaviour']) !== '' ? $p['expected_behaviour'] : null;
            }

            return $r;
        })->all();
    }

    /** @return array{0:?int, 1:?int} [cycle_id, id_level] milik satu hasil. */
    public function konteksHasil(int $resultId): array
    {
        $p = DB::table('N_PA_Result as r')->join('N_PA_Participant as p', 'p.Id_PA_Participant', '=', 'r.PA_Participant_Id')
            ->where('r.Id_PA_Result', $resultId)->first(['p.PA_Cycle_Id', 'p.ID_Level_Saat_Cycle']);

        return $p === null ? [null, null] : [(int) $p->PA_Cycle_Id, $p->ID_Level_Saat_Cycle === null ? null : (int) $p->ID_Level_Saat_Cycle];
    }

    /* ------------------------------------------------------------------ *
     * Siklus                                                              *
     * ------------------------------------------------------------------ */

    /** Menyalin matriks master satu level ke siklus. Idempoten. */
    public function salinKeSiklus(int $cycleId, int $levelId, ?int $olehUserId = null): int
    {
        $baris = DB::table('N_PA_Competency_Level_Requirement as m')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'm.PA_Competency_Id')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
            ->leftJoin('N_PA_Competency_Proficiency as p', function ($j) {
                $j->on('p.Id_PA_Competency_Proficiency', '=', 'm.PA_Competency_Proficiency_Id')->where('p.Flag_Aktif', '=', 'Y');
            })
            ->where('m.ID_Level', $levelId)->where('m.Flag_Aktif', 'Y')
            ->where('c.Flag_Aktif', 'Y')->where('t.Flag_Aktif', 'Y')
            ->whereNotExists(fn ($q) => $q->from('N_PA_Cycle_Competency_Level_Snapshot as s')
                ->whereColumn('s.PA_Competency_Id', 'm.PA_Competency_Id')
                ->where('s.PA_Cycle_Id', $cycleId)->where('s.ID_Level', $levelId))
            ->get(['m.PA_Competency_Id', 'p.Id_PA_Competency_Proficiency', 'p.Tingkat', 'p.Nama_Tingkat', 'p.Expected_Behaviour']);

        foreach ($baris->chunk(100) as $potongan) {
            DB::table('N_PA_Cycle_Competency_Level_Snapshot')->insert($potongan->map(fn ($r) => [
                'PA_Cycle_Id' => $cycleId,
                'ID_Level' => $levelId,
                'PA_Competency_Id' => (int) $r->PA_Competency_Id,
                'PA_Competency_Proficiency_Id' => $r->Id_PA_Competency_Proficiency,
                'Tingkat' => $r->Tingkat,
                'Nama_Tingkat' => $r->Nama_Tingkat,
                'Expected_Behaviour' => $r->Expected_Behaviour,
                'Snapshot_By' => $olehUserId,
            ])->values()->all());
        }

        return $baris->count();
    }

    /* ------------------------------------------------------------------ *
     * Master: matriks                                                     *
     * ------------------------------------------------------------------ */

    /**
     * Bahan layar Matriks Kompetensi: level (kecuali tanpa nama), kompetensi aktif berkelompok
     * jenis beserta pilihan tingkatnya, dan isi sel.
     *
     * @return array<string, mixed>
     */
    public function matriks(): array
    {
        $kompetensi = DB::table('N_PA_Competency as c')
            ->join('N_PA_Competency_Type as t', 't.Id_PA_Competency_Type', '=', 'c.PA_Competency_Type_Id')
            ->where('c.Flag_Aktif', 'Y')->where('t.Flag_Aktif', 'Y')
            ->orderBy('t.Urutan_Tampil')->orderBy('c.Urutan_Tampil')->orderBy('c.Kode_Competency')
            ->get(['c.Id_PA_Competency as id', 'c.Kode_Competency as kode', 'c.Nama_Competency as nama',
                't.Id_PA_Competency_Type as jenis_id', 't.Kode_Type as jenis_kode', 't.Nama_Type as jenis_nama']);

        $tingkat = DB::table('N_PA_Competency_Proficiency')->where('Flag_Aktif', 'Y')
            ->orderBy('Tingkat')
            ->get(['Id_PA_Competency_Proficiency as id', 'PA_Competency_Id', 'Tingkat as tingkat', 'Nama_Tingkat as nama', 'Expected_Behaviour as expected_behaviour'])
            ->groupBy('PA_Competency_Id');

        $sel = DB::table('N_PA_Competency_Level_Requirement')->where('Flag_Aktif', 'Y')
            ->get(['ID_Level', 'PA_Competency_Id', 'PA_Competency_Proficiency_Id']);

        $dikecualikan = array_map('intval', \App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan::daftar());

        PaLevelMasterService::sinkronHris();

        return [
            'level' => DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')->orderBy('Urutan')->orderBy('ID_Level')
                ->get(['ID_Level as id', 'Nama_Level as nama'])
                ->map(fn ($l) => ['id' => (int) $l->id, 'nama' => $l->nama, 'dikecualikan' => in_array((int) $l->id, $dikecualikan, true)])
                ->values()->all(),
            'kompetensi' => $kompetensi->map(fn ($c) => [
                'id' => (int) $c->id, 'kode' => $c->kode, 'nama' => $c->nama,
                'jenis_id' => (int) $c->jenis_id, 'jenis_kode' => $c->jenis_kode, 'jenis_nama' => $c->jenis_nama,
                'tingkat' => ($tingkat[$c->id] ?? collect())->map(fn ($t) => ['id' => (int) $t->id, 'tingkat' => (int) $t->tingkat, 'nama' => $t->nama,
                    'expected_behaviour' => $t->expected_behaviour])->values()->all(),
            ])->values()->all(),
            'sel' => $sel->map(fn ($s) => [
                'id_level' => (int) $s->ID_Level,
                'kompetensi_id' => (int) $s->PA_Competency_Id,
                'tingkat_id' => $s->PA_Competency_Proficiency_Id === null ? null : (int) $s->PA_Competency_Proficiency_Id,
            ])->values()->all(),
        ];
    }

    /**
     * Menyimpan SELURUH baris satu level: [kompetensi_id => tingkat_id|null]. Kompetensi yang tidak
     * disebut = tidak dinilai di level itu. Matriks per jenis diselaraskan dalam transaksi yang sama.
     *
     * @param  array<int|string, int|string|null>  $isi
     * @return array{level:int, dinilai:int, peringatan:list<string>}
     */
    public function simpanLevel(int $levelId, array $isi, ?int $olehUserId = null): array
    {
        if (! DB::table('N_PA_Level')->where('ID_Level', $levelId)->exists()) {
            throw new RuntimeException("Level {$levelId} tidak ada.");
        }

        $isi = collect($isi)->mapWithKeys(fn ($t, $k) => [(int) $k => ($t === null || $t === '' ? null : (int) $t)]);

        $aktif = DB::table('N_PA_Competency')->where('Flag_Aktif', 'Y')->whereIn('Id_PA_Competency', $isi->keys()->all() ?: [0])
            ->pluck('Id_PA_Competency')->map(fn ($x) => (int) $x)->all();

        if (($asing = $isi->keys()->diff($aktif)->values()->all()) !== []) {
            throw new RuntimeException('Kompetensi tidak dikenal atau nonaktif: #'.implode(', #', $asing).'.');
        }

        /* Tidak ada lagi "dinilai umum" (keputusan user 10 Okt 2026): SETIAP kompetensi yang dinilai,
           termasuk Qualitative Question, WAJIB menyebut tingkat yang dituntut. */
        $info = DB::table('N_PA_Competency')->whereIn('Id_PA_Competency', $isi->keys()->all() ?: [0])
            ->get(['Id_PA_Competency as id', 'Kode_Competency as kode', 'Nama_Competency as nama'])
            ->keyBy(fn ($r) => (int) $r->id);
        $tanpaTingkat = $isi->filter(fn ($tid) => $tid === null)
            ->keys()->map(fn ($kid) => trim($info[$kid]->kode.' '.$info[$kid]->nama))->all();
        if ($tanpaTingkat !== []) {
            throw new RuntimeException('Pilih tingkat yang dituntut untuk: '.implode(', ', $tanpaTingkat).'. Kompetensi yang belum punya tingkat dibuatkan tingkatnya dulu di Master Kompetensi.');
        }

        foreach ($isi as $kid => $tid) {
            if ($tid !== null && ! DB::table('N_PA_Competency_Proficiency')
                ->where('Id_PA_Competency_Proficiency', $tid)->where('PA_Competency_Id', $kid)->where('Flag_Aktif', 'Y')->exists()) {
                throw new RuntimeException("Tingkat #{$tid} bukan milik kompetensi #{$kid}.");
            }
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();

        DB::transaction(function () use ($levelId, $isi, $olehUserId, $sekarang) {
            $lama = DB::table('N_PA_Competency_Level_Requirement')->where('ID_Level', $levelId)->where('Flag_Aktif', 'Y')
                ->get(['Id_PA_Competency_Level_Requirement', 'PA_Competency_Id', 'PA_Competency_Proficiency_Id'])
                ->keyBy(fn ($r) => (int) $r->PA_Competency_Id);

            foreach ($lama as $kid => $r) {
                if (! $isi->has($kid)) {
                    DB::table('N_PA_Competency_Level_Requirement')->where('Id_PA_Competency_Level_Requirement', $r->Id_PA_Competency_Level_Requirement)
                        ->update(['Flag_Aktif' => 'T', 'Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);
                } elseif ((int) ($r->PA_Competency_Proficiency_Id ?? 0) !== (int) ($isi[$kid] ?? 0)) {
                    DB::table('N_PA_Competency_Level_Requirement')->where('Id_PA_Competency_Level_Requirement', $r->Id_PA_Competency_Level_Requirement)
                        ->update(['PA_Competency_Proficiency_Id' => $isi[$kid], 'Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);
                }
            }

            foreach ($isi as $kid => $tid) {
                if (! $lama->has($kid)) {
                    DB::table('N_PA_Competency_Level_Requirement')->insert([
                        'ID_Level' => $levelId, 'PA_Competency_Id' => $kid, 'PA_Competency_Proficiency_Id' => $tid,
                        'Flag_Aktif' => 'Y', 'Created_By' => $olehUserId,
                    ]);
                }
            }

            $this->selaraskanJenis($levelId, $olehUserId, $sekarang);
        });

        return [
            'level' => $levelId,
            'dinilai' => $isi->count(),
            'peringatan' => $this->peringatanIkatan($levelId),
        ];
    }

    /** Jenis wajib di level ini = jenis yang sedikitnya satu kompetensinya dituntut. */
    private function selaraskanJenis(int $levelId, ?int $olehUserId, $sekarang): void
    {
        $jenisDipakai = DB::table('N_PA_Competency_Level_Requirement as m')
            ->join('N_PA_Competency as c', 'c.Id_PA_Competency', '=', 'm.PA_Competency_Id')
            ->where('m.ID_Level', $levelId)->where('m.Flag_Aktif', 'Y')
            ->distinct()->pluck('c.PA_Competency_Type_Id')->map(fn ($x) => (int) $x)->all();

        $ada = DB::table('N_PA_Competency_Requirement')->where('ID_Level', $levelId)
            ->get(['Id_PA_Competency_Requirement', 'PA_Competency_Type_Id', 'Flag_Aktif'])
            ->keyBy(fn ($r) => (int) $r->PA_Competency_Type_Id);

        foreach ($ada as $tid => $r) {
            $harus = in_array($tid, $jenisDipakai, true) ? 'Y' : 'T';
            if ($r->Flag_Aktif !== $harus) {
                DB::table('N_PA_Competency_Requirement')->where('Id_PA_Competency_Requirement', $r->Id_PA_Competency_Requirement)
                    ->update(['Flag_Aktif' => $harus, 'Flag_Wajib' => 'Y', 'Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);
            }
        }

        foreach ($jenisDipakai as $tid) {
            if (! $ada->has($tid)) {
                DB::table('N_PA_Competency_Requirement')->insert([
                    'ID_Level' => $levelId, 'PA_Competency_Type_Id' => $tid, 'Flag_Wajib' => 'Y', 'Flag_Aktif' => 'Y',
                    'Catatan' => 'Diselaraskan dari Matriks Kompetensi per kompetensi', 'Created_By' => $olehUserId,
                ]);
            }
        }
    }

    /** @return list<string> */
    public function peringatanIkatan(int $levelId): array
    {
        $ikatan = DB::table('N_PA_Question_Binding_Master')->where('ID_Level', $levelId)->where('Flag_Aktif', 'Y')
            ->max('Jumlah_Bagian');

        if ($ikatan === null) {
            return [];
        }

        $wajib = count($this->idWajibLevel($levelId));

        return (int) $ikatan === $wajib ? [] : [sprintf(
            'Level ini kini menuntut %d kompetensi, sedangkan ujian yang terikat di Matriks Soal punya %d bagian. '
            .'Periksa Matriks Soal (1 bagian ujian = 1 kompetensi).',
            $wajib, (int) $ikatan,
        )];
    }

    /* ------------------------------------------------------------------ *
     * Master: tingkat per kompetensi                                      *
     * ------------------------------------------------------------------ */

    /** @return list<array<string, mixed>> */
    public function tingkat(int $competencyId): array
    {
        return DB::table('N_PA_Competency_Proficiency')->where('PA_Competency_Id', $competencyId)->where('Flag_Aktif', 'Y')
            ->orderBy('Tingkat')
            ->get(['Id_PA_Competency_Proficiency as id', 'Tingkat as tingkat', 'Nama_Tingkat as nama', 'Expected_Behaviour as expected_behaviour'])
            ->map(fn ($t) => (array) $t + ['dipakai_level' => DB::table('N_PA_Competency_Level_Requirement')
                ->where('PA_Competency_Proficiency_Id', $t->id)->where('Flag_Aktif', 'Y')->count()])
            ->map(fn ($t) => ['id' => (int) $t['id'], 'tingkat' => (int) $t['tingkat']] + $t)
            ->values()->all();
    }

    /**
     * Menyimpan SELURUH tingkat satu kompetensi. Baris ber-`id` diperbarui, tanpa `id` ditambah,
     * yang tidak disebut dinonaktifkan — kecuali masih dituntut matriks: itu ditolak, supaya sel
     * matriks tidak diam-diam kehilangan tingkatnya.
     *
     * @param  list<array{id?:int|null, tingkat:int, nama:string, expected_behaviour?:string|null}>  $daftar
     * @return list<array<string, mixed>>
     */
    public function simpanTingkat(int $competencyId, array $daftar, ?int $olehUserId = null): array
    {
        if (! DB::table('N_PA_Competency')->where('Id_PA_Competency', $competencyId)->exists()) {
            throw new RuntimeException("Kompetensi #{$competencyId} tidak ada.");
        }

        $bersih = [];
        foreach ($daftar as $i => $t) {
            $nomor = (int) ($t['tingkat'] ?? 0);
            $nama = trim((string) ($t['nama'] ?? ''));
            if ($nomor < 1 || $nomor > 20) {
                throw new RuntimeException('Nomor tingkat baris '.($i + 1).' harus 1 sampai 20.');
            }
            if ($nama === '') {
                throw new RuntimeException("Nama tingkat {$nomor} wajib diisi.");
            }
            if (mb_strlen($nama) > 150) {
                throw new RuntimeException("Nama tingkat {$nomor} terlalu panjang (maks 150 karakter).");
            }
            $eb = trim((string) ($t['expected_behaviour'] ?? ''));
            if (mb_strlen($eb) > 4000) {
                throw new RuntimeException("Expected Behaviour tingkat {$nomor} terlalu panjang (maks 4000 karakter).");
            }
            if (isset($bersih[$nomor])) {
                throw new RuntimeException("Nomor tingkat {$nomor} dipakai dua kali.");
            }
            $bersih[$nomor] = ['id' => isset($t['id']) && $t['id'] !== '' && $t['id'] !== null ? (int) $t['id'] : null,
                'nama' => $nama, 'eb' => $eb === '' ? null : $eb];
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();

        DB::transaction(function () use ($competencyId, $bersih, $olehUserId, $sekarang) {
            $lama = DB::table('N_PA_Competency_Proficiency')->where('PA_Competency_Id', $competencyId)->where('Flag_Aktif', 'Y')
                ->get(['Id_PA_Competency_Proficiency', 'Tingkat'])->keyBy(fn ($r) => (int) $r->Id_PA_Competency_Proficiency);
            $dipertahankan = collect($bersih)->pluck('id')->filter()->map(fn ($x) => (int) $x)->all();

            foreach ($lama as $id => $r) {
                if (in_array($id, $dipertahankan, true)) {
                    continue;
                }
                $dipakai = DB::table('N_PA_Competency_Level_Requirement as m')->join('N_PA_Level as l', 'l.ID_Level', '=', 'm.ID_Level')
                    ->where('m.PA_Competency_Proficiency_Id', $id)->where('m.Flag_Aktif', 'Y')->pluck('l.Nama_Level')->all();
                if ($dipakai !== []) {
                    throw new RuntimeException(sprintf('Tingkat %d masih dituntut di level %s. Ubah Matriks Kompetensi lebih dulu.',
                        $r->Tingkat, implode(', ', $dipakai)));
                }
                DB::table('N_PA_Competency_Proficiency')->where('Id_PA_Competency_Proficiency', $id)
                    ->update(['Flag_Aktif' => 'T', 'Updated_At' => $sekarang, 'Updated_By' => $olehUserId]);
            }

            /* Dua langkah supaya penukaran nomor (1↔2) tidak menabrak indeks unik: nomor lama
               dipindah ke ruang negatif dulu, lalu ditulis ke nomor akhirnya. */
            foreach ($bersih as $nomor => $t) {
                if ($t['id'] !== null) {
                    if (! $lama->has($t['id'])) {
                        throw new RuntimeException("Tingkat #{$t['id']} bukan milik kompetensi ini.");
                    }
                    DB::table('N_PA_Competency_Proficiency')->where('Id_PA_Competency_Proficiency', $t['id'])->update(['Tingkat' => -$nomor]);
                }
            }

            foreach ($bersih as $nomor => $t) {
                if ($t['id'] !== null) {
                    DB::table('N_PA_Competency_Proficiency')->where('Id_PA_Competency_Proficiency', $t['id'])->update([
                        'Tingkat' => $nomor, 'Nama_Tingkat' => $t['nama'], 'Expected_Behaviour' => $t['eb'],
                        'Updated_At' => $sekarang, 'Updated_By' => $olehUserId,
                    ]);
                } else {
                    DB::table('N_PA_Competency_Proficiency')->insert([
                        'PA_Competency_Id' => $competencyId, 'Tingkat' => $nomor, 'Nama_Tingkat' => $t['nama'],
                        'Expected_Behaviour' => $t['eb'], 'Flag_Aktif' => 'Y', 'Created_By' => $olehUserId,
                    ]);
                }
            }
        });

        return $this->tingkat($competencyId);
    }
}
