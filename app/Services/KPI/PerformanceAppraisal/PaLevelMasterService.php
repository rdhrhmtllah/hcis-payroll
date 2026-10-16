<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * MASTER LEVEL PA (DDL-2026-183/185) — satu-satunya pintu ke `N_PA_Level`.
 *
 * Seluruh matriks PA (bobot pilar, komposisi penilai, kompetensi, soal, kurva, pengecualian)
 * menunjuk `N_PA_Level`, bukan `HRIS_Level`. Isinya dua jenis:
 *
 *   HRIS  salinan `HRIS_Level` dengan ID yang SAMA — disalin `sinkronHris()`; nama/urutannya
 *         mengikuti HRIS dan tidak disunting dari PA.
 *   PA    level KHUSUS PA (mis. OFFICER SPECIALIST), ID >= 1.000.000, wajib punya level dasar
 *         HRIS. Tidak pernah muncul di HRIS.
 *
 * Level karyawan di HRIS (`Karyawan` → view level-jabatan) TETAP dibaca dari HRIS; level PA-nya
 * baru berbeda bila dikoreksi lewat Koreksi Level PA (`PaLevelPaResolver`).
 */
class PaLevelMasterService
{
    public const SUMBER_HRIS = 'HRIS';

    public const SUMBER_PA = 'PA';

    public const ID_PA_PERTAMA = 1000000;

    /* Penanda di `N_PA_Question_Binding_Master.Catatan`: ikatan soal ini SALINAN dari level dasar
       saat level PA dibuat, mapping kompetensinya belum ditinjau. Hilang saat admin menyimpan
       mapping sel itu (`PaQuestionBindingMasterService::simpan`). Format: penanda + ID level asal. */
    public const TANDA_SALINAN_IKATAN = 'SALINAN_LEVEL_PA:';

    /** Sekali per proses: sinkron HRIS cukup dicek sekali, bukan pada setiap kueri. */
    private static bool $tersinkron = false;

    /**
     * Menyalin level HRIS yang belum ada ke `N_PA_Level` (ID sama) dan menyamakan nama/urutan
     * level berasal-HRIS. Idempoten dan murah: satu kueri bila tidak ada yang berubah.
     *
     * @return array{ditambah:int, diperbarui:int}
     */
    public static function sinkronHris(bool $paksa = false): array
    {
        if (self::$tersinkron && ! $paksa) {
            return ['ditambah' => 0, 'diperbarui' => 0];
        }

        $hris = DB::table('HRIS_Level')->get(['ID_Level', 'Keterangan', 'Level_Hierarchy']);
        $pa = DB::table('N_PA_Level')->where('Sumber', self::SUMBER_HRIS)->get(['ID_Level', 'Nama_Level', 'Urutan'])->keyBy(fn ($r) => (int) $r->ID_Level);
        $sekarang = FormatTanggalHelper::getCurrentTime();
        $hasil = ['ditambah' => 0, 'diperbarui' => 0];

        foreach ($hris as $h) {
            $id = (int) $h->ID_Level;
            $nama = trim((string) $h->Keterangan);
            $urutan = (float) ($h->Level_Hierarchy ?? 0);
            $ada = $pa[$id] ?? null;

            if ($ada === null) {
                DB::table('N_PA_Level')->insert([
                    'ID_Level' => $id, 'Nama_Level' => $nama, 'Sumber' => self::SUMBER_HRIS,
                    'ID_Level_HRIS_Dasar' => $id, 'Urutan' => $urutan, 'Flag_Aktif' => 'Y', 'Created_At' => $sekarang,
                ]);
                $hasil['ditambah']++;
            } elseif ($ada->Nama_Level !== $nama || (float) $ada->Urutan !== $urutan) {
                DB::table('N_PA_Level')->where('ID_Level', $id)->update(['Nama_Level' => $nama, 'Urutan' => $urutan, 'Updated_At' => $sekarang]);
                $hasil['diperbarui']++;
            }
        }

        self::$tersinkron = true;

        return $hasil;
    }

    /**
     * Membandingkan Master Level PA dengan `HRIS_Level` TANPA mengubah apa pun — dasar tombol
     * "Periksa & Sinkronkan HRIS". Di produksi level HRIS bisa berbeda dari dev; admin perlu
     * melihat keadaannya, bukan hanya percaya sinkron otomatis.
     *
     *   belum  level HRIS yang belum tersalin ke PA (sinkron menambahkannya)
     *   beda   nama/urutan berbeda dari HRIS (sinkron menyamakannya)
     *   hilang level berasal-HRIS yang sudah TIDAK ADA di HRIS — tidak dihapus otomatis karena
     *          matriks/siklus bisa masih menunjuknya; disebut beserta jumlah pemakaiannya.
     *
     * @return array{sinkron:bool, jumlah_hris:int, tersalin:int, belum:list<array<string,mixed>>, beda:list<array<string,mixed>>, hilang:list<array<string,mixed>>}
     */
    public function periksaHris(): array
    {
        $hris = DB::table('HRIS_Level')->get(['ID_Level', 'Keterangan', 'Level_Hierarchy'])->keyBy(fn ($r) => (int) $r->ID_Level);
        $pa = DB::table('N_PA_Level')->where('Sumber', self::SUMBER_HRIS)->get(['ID_Level', 'Nama_Level', 'Urutan'])->keyBy(fn ($r) => (int) $r->ID_Level);

        $belum = $beda = $hilang = [];
        foreach ($hris as $id => $h) {
            $p = $pa[$id] ?? null;
            if ($p === null) {
                $belum[] = ['id' => $id, 'nama' => trim((string) $h->Keterangan)];
            } elseif ($p->Nama_Level !== trim((string) $h->Keterangan) || (float) $p->Urutan !== (float) ($h->Level_Hierarchy ?? 0)) {
                $beda[] = ['id' => $id, 'nama_pa' => $p->Nama_Level, 'nama_hris' => trim((string) $h->Keterangan),
                    'urutan_pa' => (float) $p->Urutan, 'urutan_hris' => (float) ($h->Level_Hierarchy ?? 0)];
            }
        }
        foreach ($pa as $id => $p) {
            if (! isset($hris[$id])) {
                $hilang[] = ['id' => $id, 'nama' => $p->Nama_Level, 'dipakai' => $this->jumlahPemakaian($id)];
            }
        }

        return [
            'sinkron' => $belum === [] && $beda === [],
            'jumlah_hris' => $hris->count(),
            'tersalin' => $hris->count() - count($belum),
            'belum' => $belum,
            'beda' => $beda,
            'hilang' => $hilang,
        ];
    }

    /** Baris matriks master dan peserta siklus yang masih menunjuk level ini. */
    private function jumlahPemakaian(int $id): int
    {
        $n = DB::table('N_PA_Participant')->where('ID_Level_Saat_Cycle', $id)->count();
        foreach (['N_PA_Pillar_Weight', 'N_PA_Rater_Policy', 'N_PA_Competency_Requirement', 'N_PA_Question_Binding_Master', 'N_PA_Curve_Group_Level'] as $t) {
            $n += DB::table($t)->where('ID_Level', $id)->count();
        }

        return $n + DB::table('N_PA_Karyawan_Level_PA')->where('ID_Level_PA', $id)->where('Flag_Aktif', 'Y')->count();
    }

    /** Untuk uji: sinkron berikutnya membaca ulang HRIS. */
    public static function lupakan(): void
    {
        self::$tersinkron = false;
    }

    /**
     * Daftar level PA, berurut `Urutan`.
     *
     * @return list<array{id:int, nama:string, sumber:string, id_level_dasar:?int, nama_level_dasar:?string, urutan:float, aktif:bool, dipakai:int}>
     */
    public function daftar(bool $denganNonaktif = false): array
    {
        /* SENGAJA tanpa sinkron: tab ini menampilkan keadaan apa adanya, dan `periksaHris()`
           memberi tahu bila ada yang tertinggal. Halaman matriks tetap menyinkronkan otomatis. */

        $dipakai = DB::table('N_PA_Karyawan_Level_PA')->where('Flag_Aktif', 'Y')
            ->groupBy('ID_Level_PA')->selectRaw('ID_Level_PA as l, COUNT(*) as n')->pluck('n', 'l');

        return DB::table('N_PA_Level as l')
            ->leftJoin('N_PA_Level as d', 'd.ID_Level', '=', 'l.ID_Level_HRIS_Dasar')
            ->when(! $denganNonaktif, fn ($q) => $q->where('l.Flag_Aktif', 'Y'))
            ->orderBy('l.Urutan')->orderBy('l.ID_Level')
            ->get(['l.ID_Level', 'l.Nama_Level', 'l.Sumber', 'l.ID_Level_HRIS_Dasar', 'd.Nama_Level as Nama_Dasar', 'l.Urutan', 'l.Flag_Aktif'])
            ->map(fn ($r) => [
                'id' => (int) $r->ID_Level,
                'nama' => (string) $r->Nama_Level,
                'sumber' => (string) $r->Sumber,
                'id_level_dasar' => $r->ID_Level_HRIS_Dasar === null ? null : (int) $r->ID_Level_HRIS_Dasar,
                'nama_level_dasar' => $r->Sumber === self::SUMBER_PA ? $r->Nama_Dasar : null,
                'urutan' => (float) $r->Urutan,
                'aktif' => $r->Flag_Aktif === 'Y',
                'dipakai' => (int) ($dipakai[$r->ID_Level] ?? 0),
            ])->all();
    }

    /**
     * Menambah level KHUSUS PA. Bila `$salinMatriks`, isi matriks level dasarnya disalin sebagai
     * titik awal (bobot pilar, komposisi penilai, kebutuhan kompetensi, ikatan soal, grup kurva) —
     * admin lalu menyesuaikannya di tiap matriks.
     *
     * @return array{id:int, disalin:array<string, int>}
     */
    public function tambah(string $nama, int $idLevelDasar, float $urutan, bool $salinMatriks, ?int $olehUserId = null): array
    {
        $nama = mb_strtoupper(trim($nama));
        $this->periksaNama($nama);
        self::sinkronHris();

        $dasar = DB::table('N_PA_Level')->where('ID_Level', $idLevelDasar)->where('Sumber', self::SUMBER_HRIS)->first();
        if ($dasar === null) {
            throw new RuntimeException('Level dasar harus level HRIS yang ada.');
        }

        return DB::transaction(function () use ($nama, $idLevelDasar, $urutan, $salinMatriks, $olehUserId) {
            $id = max(self::ID_PA_PERTAMA, (int) DB::table('N_PA_Level')->where('Sumber', self::SUMBER_PA)->lockForUpdate()->max('ID_Level') + 1);
            DB::table('N_PA_Level')->insert([
                'ID_Level' => $id, 'Nama_Level' => $nama, 'Sumber' => self::SUMBER_PA, 'ID_Level_HRIS_Dasar' => $idLevelDasar,
                'Urutan' => $urutan, 'Flag_Aktif' => 'Y', 'Created_At' => FormatTanggalHelper::getCurrentTime(), 'Created_By' => $olehUserId,
            ]);

            return ['id' => $id, 'disalin' => $salinMatriks ? $this->salinMatriks($idLevelDasar, $id, $olehUserId) : []];
        });
    }

    /** Mengubah nama/urutan level khusus PA. Level HRIS tidak disunting dari sini. */
    public function ubah(int $id, string $nama, float $urutan, ?int $olehUserId = null): void
    {
        $level = $this->levelPa($id);
        $nama = mb_strtoupper(trim($nama));
        $this->periksaNama($nama, $id);
        DB::table('N_PA_Level')->where('ID_Level', $level->ID_Level)->update([
            'Nama_Level' => $nama, 'Urutan' => $urutan, 'Updated_At' => FormatTanggalHelper::getCurrentTime(), 'Updated_By' => $olehUserId,
        ]);
    }

    /** Menonaktifkan / mengaktifkan level khusus PA. Ditolak selama masih dipakai koreksi aktif. */
    public function aturAktif(int $id, bool $aktif, ?int $olehUserId = null): void
    {
        $level = $this->levelPa($id);
        if (! $aktif && DB::table('N_PA_Karyawan_Level_PA')->where('ID_Level_PA', $id)->where('Flag_Aktif', 'Y')->exists()) {
            throw new RuntimeException("Level {$level->Nama_Level} masih dipakai Koreksi Level PA aktif — cabut koreksinya dulu.");
        }
        DB::table('N_PA_Level')->where('ID_Level', $id)->update([
            'Flag_Aktif' => $aktif ? 'Y' : 'T', 'Updated_At' => FormatTanggalHelper::getCurrentTime(), 'Updated_By' => $olehUserId,
        ]);
    }

    /**
     * Menyalin isi matriks MASTER level dasar ke level baru. Hanya master — salinan siklus
     * (snapshot) tidak disentuh; siklus berikutnya menyalinnya sendiri saat disiapkan.
     *
     * @return array<string, int> jumlah baris per matriks
     */
    public function salinMatriks(int $dari, int $ke, ?int $olehUserId = null): array
    {
        $sekarang = FormatTanggalHelper::getCurrentTime();
        $hasil = [];

        $hasil['bobot_pilar'] = $this->salinBaris('N_PA_Pillar_Weight', 'Id_PA_Pillar_Weight', $dari, $ke, $sekarang, $olehUserId);
        $hasil['kompetensi'] = $this->salinBaris('N_PA_Competency_Requirement', 'Id_PA_Competency_Requirement', $dari, $ke, $sekarang, $olehUserId);
        $hasil['tingkat_kompetensi'] = $this->salinBaris('N_PA_Competency_Level_Requirement', 'Id_PA_Competency_Level_Requirement', $dari, $ke, $sekarang, $olehUserId);
        $hasil['ikatan_soal'] = $this->salinBaris('N_PA_Question_Binding_Master', 'Id_PA_Question_Binding_Master', $dari, $ke, $sekarang, $olehUserId);
        /* Ditandai "perlu ditinjau": mapping Officer belum tentu cocok untuk kompetensi level baru. */
        DB::table('N_PA_Question_Binding_Master')->where('ID_Level', $ke)->where('Flag_Aktif', 'Y')
            ->update(['Catatan' => self::TANDA_SALINAN_IKATAN.$dari]);
        $hasil['label_ikatan_soal'] = $this->salinLabelIkatan($dari, $ke, $olehUserId);
        $hasil['grup_kurva'] = $this->salinBaris('N_PA_Curve_Group_Level', 'Id_PA_Curve_Group_Level', $dari, $ke, $sekarang, $olehUserId);

        /* Komposisi penilai: kepala + rinciannya (rincian menunjuk id kepala yang baru). */
        $hasil['komposisi_penilai'] = 0;
        $namaBaru = (string) DB::table('N_PA_Level')->where('ID_Level', $ke)->value('Nama_Level');
        foreach (DB::table('N_PA_Rater_Policy')->where('ID_Level', $dari)->where('Flag_Aktif', 'Y')->where('Status_Policy', 'ACTIVE')->get() as $kepala) {
            $baru = (array) $kepala;
            unset($baru['Id_PA_Rater_Policy']);
            /* Kode unik per (Kode_Policy, Versi): level baru memulai kodenya sendiri, versi 1. */
            $baru = $this->jejak(array_merge($baru, [
                'ID_Level' => $ke,
                'Kode_Policy' => 'PA-RTR-L'.$ke,
                'Nama_Policy' => 'Aturan Penilai — '.$namaBaru,
                'Versi' => 1,
                'PA_Rater_Policy_Sumber_Id' => null,
                'Activated_At' => $sekarang,
                'Activated_By' => $olehUserId,
            ]), $sekarang, $olehUserId);
            $idBaru = (int) DB::table('N_PA_Rater_Policy')->insertGetId($baru, 'Id_PA_Rater_Policy');
            foreach (DB::table('N_PA_Rater_Policy_Detail')->where('PA_Rater_Policy_Id', $kepala->Id_PA_Rater_Policy)->where('Flag_Aktif', 'Y')->get() as $rinci) {
                $r = (array) $rinci;
                unset($r['Id_PA_Rater_Policy_Detail']);
                DB::table('N_PA_Rater_Policy_Detail')->insert($this->jejak(array_merge($r, ['PA_Rater_Policy_Id' => $idBaru]), $sekarang, $olehUserId));
            }
            $hasil['komposisi_penilai']++;
        }

        return $hasil;
    }

    /**
     * Menyalin LABEL kompetensi per bagian (`N_PA_Question_Binding_Master_Detail`) dari ikatan
     * soal level dasar ke ikatan level baru yang sel-nya sama (kategori penilai + ujian).
     *
     * Tanpa label, ikatan yang tersalin tidak berguna: usulan master di Langkah 2 siklus gugur
     * ("Ujian … belum dipetakan ke kompetensi") dan impornya ditolak (UAT 13 Okt 2026). Hanya
     * sel yang BELUM berlabel yang diisi, jadi aman diulang dan tidak menimpa pelabelan admin.
     */
    public function salinLabelIkatan(int $dari, int $ke, ?int $olehUserId = null): int
    {
        $sekarang = FormatTanggalHelper::getCurrentTime();
        $kunci = fn ($b) => ($b->PA_Rater_Category_Id ?? '').'|'.$b->Id_Master_Ujian;
        $asal = DB::table('N_PA_Question_Binding_Master')->where('ID_Level', $dari)->where('Flag_Aktif', 'Y')->get()->keyBy($kunci);
        $n = 0;

        foreach (DB::table('N_PA_Question_Binding_Master')->where('ID_Level', $ke)->where('Flag_Aktif', 'Y')->get() as $tujuan) {
            $sumber = $asal[$kunci($tujuan)] ?? null;
            if ($sumber === null || DB::table('N_PA_Question_Binding_Master_Detail')
                ->where('PA_Question_Binding_Master_Id', $tujuan->Id_PA_Question_Binding_Master)->where('Flag_Aktif', 'Y')->exists()) {
                continue;
            }
            foreach (DB::table('N_PA_Question_Binding_Master_Detail')->where('PA_Question_Binding_Master_Id', $sumber->Id_PA_Question_Binding_Master)->where('Flag_Aktif', 'Y')->get() as $d) {
                $baru = (array) $d;
                unset($baru['Id_PA_Question_Binding_Master_Detail']);
                $baru['PA_Question_Binding_Master_Id'] = $tujuan->Id_PA_Question_Binding_Master;
                DB::table('N_PA_Question_Binding_Master_Detail')->insert($this->jejak($baru, $sekarang, $olehUserId));
                $n++;
            }
        }

        return $n;
    }

    /* ------------------------------------------------------------------ */

    private function salinBaris(string $tabel, string $pk, int $dari, int $ke, string $sekarang, ?int $olehUserId): int
    {
        $n = 0;
        $q = DB::table($tabel)->where('ID_Level', $dari);
        if (Schema::hasColumn($tabel, 'Flag_Aktif')) {
            $q->where('Flag_Aktif', 'Y');
        }
        foreach ($q->get() as $b) {
            $baru = (array) $b;
            unset($baru[$pk]);
            DB::table($tabel)->insert($this->jejak(array_merge($baru, ['ID_Level' => $ke]), $sekarang, $olehUserId));
            $n++;
        }

        return $n;
    }

    /** Kolom jejak baris salinan: dibuat sekarang, oleh siapa; jejak ubah dikosongkan. */
    private function jejak(array $baris, string $sekarang, ?int $olehUserId): array
    {
        foreach (['Created_At' => $sekarang, 'Created_By' => $olehUserId, 'Updated_At' => null, 'Updated_By' => null] as $k => $v) {
            if (array_key_exists($k, $baris)) {
                $baris[$k] = $v;
            }
        }

        return $baris;
    }

    private function levelPa(int $id): object
    {
        $level = DB::table('N_PA_Level')->where('ID_Level', $id)->first();
        if ($level === null) {
            throw new RuntimeException("Level {$id} tidak ada.");
        }
        if ($level->Sumber !== self::SUMBER_PA) {
            throw new RuntimeException("Level {$level->Nama_Level} berasal dari HRIS — ubah di HRIS, bukan di PA.");
        }

        return $level;
    }

    private function periksaNama(string $nama, ?int $kecuali = null): void
    {
        if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
            throw new RuntimeException('Nama level 3–100 huruf.');
        }
        if (DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')->where('Nama_Level', $nama)
            ->when($kecuali !== null, fn ($q) => $q->where('ID_Level', '<>', $kecuali))->exists()) {
            throw new RuntimeException("Level {$nama} sudah ada.");
        }
    }
}
