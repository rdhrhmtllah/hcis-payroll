<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Services\KPI\PerformanceAppraisal\Katalog\PaLevelDikecualikan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Matriks dimensi penilaian × level jabatan.
 *
 * Inilah yang digambar deck sebagai "Matrix Dimensi Penilaian Kompetensi": Pramukarya hanya
 * dinilai Behavior, Staff dinilai Manajerial dan Core Value, Leaders ditambah Qualitative.
 *
 * MASALAH YANG DISELESAIKAN
 *
 * Tabelnya sudah terisi dan sudah DIPAKAI — ia yang menentukan dimensi apa yang muncul di
 * formulir seseorang. Tetapi tidak ada satu pun layar untuk membacanya, apalagi
 * mengubahnya. Satu-satunya cara HC menyesuaikannya adalah meminta orang menulis SQL, dan
 * perubahan sepenting ini berjalan tanpa jejak siapa yang memutuskannya.
 *
 * KENAPA MATRIKS, BUKAN DAFTAR BARIS
 *
 * 33 baris `(level, dimensi)` menyampaikan hal yang sama dengan kisi 12×4, tetapi tak
 * seorang pun bisa menjawab "apa bedanya Staff dan Leaders" dengan membaca daftar. Yang
 * ditanyakan orang tentang tabel ini selalu berbentuk perbandingan antar level, dan bentuk
 * daftar menyembunyikan justru itu.
 *
 * DISIMPAN PER LEVEL, SEKALIGUS SELURUH DIMENSINYA
 *
 * Sama seperti bobot aturan penilai: satu baris matriks hanya bermakna bila seluruh selnya
 * dilihat bersamaan. Menyimpan satu sel pada satu waktu membuat setiap penyimpanan tengah
 * jalan melewati keadaan yang tidak seorang pun maksudkan — misalnya level yang sesaat
 * tidak punya dimensi sama sekali.
 */
class PaCompetencyRequirementService
{
    /**
     * Matriks lengkap, beserta cacah karyawan per level.
     *
     * Cacah karyawan ikut dikirim karena ia yang mengubah kesalahan konfigurasi menjadi
     * sesuatu yang terasa: "level ini tanpa dimensi" berbeda jauh artinya ketika levelnya
     * kosong dan ketika levelnya berisi 391 orang.
     *
     * @return array<string, mixed>
     */
    public function matriks(): array
    {
        $dimensi = DB::table('N_PA_Competency_Type')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan_Tampil')
            ->get(['Id_PA_Competency_Type as id', 'Kode_Type as kode', 'Nama_Type as nama'])
            ->map(fn ($d) => (array) $d)
            ->all();

        /* `pluck()` dengan ekspresi mentah TIDAK bekerja: ia mencari kolom bernama harfiah
           "COUNT(*)" pada hasilnya, gagal diam-diam, dan memulangkan nol untuk semuanya.
           Cacah yang selalu nol membuat penjaga di bawah tidak akan pernah menyala. */
        $cacahKompetensi = DB::table('N_PA_Competency')
            ->where('Flag_Aktif', 'Y')
            ->groupBy('PA_Competency_Type_Id')
            ->get(['PA_Competency_Type_Id', DB::raw('COUNT(*) as jumlah')])
            ->pluck('jumlah', 'PA_Competency_Type_Id')
            ->all();

        foreach ($dimensi as &$d) {
            // Dimensi tanpa satu pun kompetensi tidak menghasilkan butir soal apa pun.
            // Mencentangnya terasa seperti menambah penilaian, padahal tidak menambah apa-apa.
            $d['jumlah_kompetensi'] = (int) ($cacahKompetensi[$d['id']] ?? 0);
        }
        unset($d);

        $syarat = DB::table('N_PA_Competency_Requirement')
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Competency_Requirement', 'ID_Level', 'PA_Competency_Type_Id', 'Flag_Wajib'])
            ->groupBy('ID_Level');

        /* Cacah karyawan per level, SATU kueri. Menanyakannya per baris berarti belasan
           perjalanan ke database untuk halaman yang dibuka sekali. */
        // Level PA (DDL-2026-177): koreksi per karyawan ikut dihitung.
        $lvPa = PaLevelPaResolver::sqlLevel();
        $karyawan = PaLevelPaResolver::gabung(DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan'))
            ->where('k.Aktif', 'Y')
            ->groupBy(DB::raw($lvPa))
            ->get([DB::raw("{$lvPa} as ID_Level"), DB::raw('COUNT(DISTINCT k.Kode_Karyawan) as jumlah')])
            ->pluck('jumlah', 'ID_Level')
            ->all();

        PaLevelMasterService::sinkronHris();
        $level = DB::table('N_PA_Level')->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')->orderBy('ID_Level')
            ->get(['ID_Level as id_level', 'Nama_Level as nama_level'])
            ->map(function ($l) use ($dimensi, $syarat, $karyawan) {
                $punya = ($syarat[$l->id_level] ?? collect())->keyBy('PA_Competency_Type_Id');

                $sel = [];
                foreach ($dimensi as $d) {
                    $baris = $punya->get($d['id']);
                    $sel[$d['kode']] = [
                        'id' => $baris?->Id_PA_Competency_Requirement,
                        'aktif' => $baris !== null,
                        'wajib' => ($baris->Flag_Wajib ?? 'Y') === 'Y',
                    ];
                }

                return [
                    'id_level' => (int) $l->id_level,
                    'nama_level' => $l->nama_level,
                    'jumlah_karyawan' => (int) ($karyawan[$l->id_level] ?? 0),
                    // Ditandai supaya layar bisa menjelaskan kenapa barisnya kosong,
                    // alih-alih tampak seperti konfigurasi yang terlupa.
                    'dikecualikan_pa' => PaLevelDikecualikan::apakah($l->id_level),
                    'alasan_dikecualikan' => PaLevelDikecualikan::alasan($l->id_level),
                    'sel' => $sel,
                    'jumlah_dimensi' => count(array_filter($sel, fn ($s) => $s['aktif'])),
                ];
            })
            ->all();

        /* Level yang BERISI ORANG tetapi tidak punya satu dimensi pun. Orang di level ini
           akan membuka formulir penilaian yang kosong — tidak ada galat, tidak ada pesan,
           hanya layar tanpa pertanyaan. */
        $bahaya = array_values(array_filter(
            $level,
            /* Level yang dikecualikan DIBEBASKAN dari alarm ini: nol dimensi padanya
               bukan kekeliruan, dan penjaga yang selalu berteriak berhenti dibaca — lebih
               buruk daripada tidak ada penjaga sama sekali. */
            fn ($l) => $l['jumlah_dimensi'] === 0
                && $l['jumlah_karyawan'] > 0
                && ! PaLevelDikecualikan::apakah($l['id_level']),
        ));

        return [
            'dimensi' => $dimensi,
            'level' => $level,
            'level_berisi_tanpa_dimensi' => $bahaya,
        ];
    }

    /**
     * Menyimpan satu baris matriks: dimensi apa saja yang berlaku pada satu level.
     *
     * @param  list<string>  $kodeDimensi  kode dimensi yang dicentang
     * @return array<string, mixed>
     */
    public function simpan(int $levelId, array $kodeDimensi, ?int $olehUserId = null): array
    {
        $level = DB::table('N_PA_Level')->where('ID_Level', $levelId)->first(['ID_Level', 'Nama_Level as Keterangan']);

        if ($level === null) {
            throw new RuntimeException("Level {$levelId} tidak ditemukan.");
        }

        $dimensi = DB::table('N_PA_Competency_Type')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_PA_Competency_Type', 'Kode_Type')
            ->all();

        $diminta = [];

        foreach (array_unique($kodeDimensi) as $kode) {
            if (! isset($dimensi[$kode])) {
                throw new RuntimeException("Dimensi penilaian '{$kode}' tidak dikenal.");
            }

            $diminta[$kode] = $dimensi[$kode];
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();

        $hasil = DB::transaction(function () use ($levelId, $diminta, $sekarang, $olehUserId) {
            $aktif = DB::table('N_PA_Competency_Requirement')
                ->where('ID_Level', $levelId)
                ->where('Flag_Aktif', 'Y')
                ->pluck('Id_PA_Competency_Requirement', 'PA_Competency_Type_Id')
                ->all();

            $ditambah = 0;
            $dicabut = 0;

            foreach ($diminta as $typeId) {
                if (isset($aktif[$typeId])) {
                    continue;
                }

                /* Baris yang dulu dicabut DIHIDUPKAN kembali, bukan disisipkan baru.
                   Menyisipkan baru meninggalkan dua baris untuk pasangan yang sama dan
                   membuat "sejak kapan level ini dinilai Kualitatif" punya dua jawaban. */
                $lama = DB::table('N_PA_Competency_Requirement')
                    ->where('ID_Level', $levelId)
                    ->where('PA_Competency_Type_Id', $typeId)
                    ->value('Id_PA_Competency_Requirement');

                if ($lama !== null) {
                    DB::table('N_PA_Competency_Requirement')
                        ->where('Id_PA_Competency_Requirement', $lama)
                        ->update([
                            'Flag_Aktif' => 'Y',
                            'Updated_At' => $sekarang,
                            'Updated_By' => $olehUserId,
                        ]);
                } else {
                    DB::table('N_PA_Competency_Requirement')->insert([
                        'ID_Level' => $levelId,
                        'PA_Competency_Type_Id' => $typeId,
                        'Flag_Wajib' => 'Y',
                        'Flag_Aktif' => 'Y',
                        'Created_By' => $olehUserId,
                    ]);
                }

                $ditambah++;
            }

            foreach ($aktif as $typeId => $id) {
                if (in_array((int) $typeId, array_map('intval', $diminta), true)) {
                    continue;
                }

                DB::table('N_PA_Competency_Requirement')
                    ->where('Id_PA_Competency_Requirement', $id)
                    ->update([
                        'Flag_Aktif' => 'T',
                        'Updated_At' => $sekarang,
                        'Updated_By' => $olehUserId,
                    ]);

                $dicabut++;
            }

            return ['ditambah' => $ditambah, 'dicabut' => $dicabut];
        });

        return $hasil + [
            'id_level' => $levelId,
            'nama_level' => $level->Keterangan,
            'jumlah_dimensi' => count($diminta),

            /* Peringatan, bukan penolakan. Level tanpa dimensi kadang memang disengaja —
               level yang tidak ikut dinilai sama sekali. Yang tidak boleh terjadi adalah
               level tanpa dimensi yang BERISI ORANG, dan itu terlihat dari angka ini. */
            'peringatan' => $diminta === []
                ? sprintf(
                    'Level %s kini tanpa dimensi penilaian. Bila ada karyawan di level ini, '
                    .'formulir penilaian mereka akan kosong tanpa satu pun pesan.',
                    $level->Keterangan,
                )
                : null,
        ];
    }
}
