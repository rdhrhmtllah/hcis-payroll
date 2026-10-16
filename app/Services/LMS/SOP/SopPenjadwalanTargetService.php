<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Penargetan penjadwalan SOP — tiga mode: pilih orang, pilih unit organisasi,
 * tempel daftar kode.
 *
 * Berbeda dari SopMonitoringPeopleService: opsi & kandidat di sini mencakup
 * SEMUA karyawan aktif (monitoring di-gate EXISTS penugasan — di sini justru
 * orang yang BELUM pernah ditugasi yang paling penting). Pola join organisasinya
 * sama: View_Divisi_Sub_Divisi + View_Golongan_Sub_Golongan_Level_Jabatan +
 * Cabang_Untuk_Gaji.
 *
 * Prinsip mode unit: daftar kode TIDAK pernah dimaterialisasi di klien.
 * Filter diterjemahkan jadi predikat SQL; resolusi ke kode terjadi SEKALI di
 * server saat dispatch (resolveTargets) dan di-snapshot ke payload task supaya
 * retry tak me-resolve ulang terhadap data organisasi yang mungkin berubah.
 */
class SopPenjadwalanTargetService
{
    /** ID level PHL — mengikuti konvensi LMS (LmsPenjadwalanTrainingController). */
    private const LEVEL_PHL = 21;

    /** Pagar parameter whereIn SQL Server — bongkah, bukan titik potong data. */
    private const CHUNK_KODE = 500;

    /** Pagar wajar untuk satu kali tempel; kelebihannya dilaporkan, tak dibuang diam-diam. */
    private const MAKS_TEMPEL = 1000;

    /* ================= OPSI FILTER ================= */

    /**
     * Seluruh karyawan aktif, untuk pemilih orang di layar.
     *
     * Dimuat sekali lalu disaring di sisi layar, bukan lewat pencarian ke
     * server: HcisSelect menyaring lokal, dan menambahkan jalur pencarian
     * jarak jauh ke komponen kit bersama menyentuh puluhan halaman lain demi
     * satu pemakai. Populasinya memang kecil — ratusan, bukan puluhan ribu.
     *
     * Kalau suatu saat headcount tumbuh sampai daftar ini terasa berat, yang
     * benar adalah menambah pencarian jarak jauh ke HcisSelect sebagai
     * inisiatif tersendiri, bukan menambal di sini dengan plafon senyap.
     *
     * @return array{items:array,total:int}
     */
    public function daftarOrang(): array
    {
        return Cache::store('database')->remember('sop_pj_daftarorang', 1800, function () {
            $rows = DB::table('Karyawan as k')
                ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->where('k.Aktif', 'Y')
                ->orderBy('k.Nama')
                ->get([
                    'k.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan',
                    'div.nama_sub_divisi as Departemen', 'lvl.nama_jabatan as Jabatan',
                ]);

            return ['items' => $rows->all(), 'total' => $rows->count()];
        });
    }

    public function filterOptions(): array
    {
        return Cache::store('database')->remember('sop_pj_filteropts', 1800, function () {
            $base = fn () => DB::table('Karyawan as k')->where('k.Aktif', 'Y');

            $div = fn (string $col) => $base()
                ->join('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
                ->whereNotNull("div.{$col}")->where("div.{$col}", '<>', '')
                ->distinct()->orderBy("div.{$col}")->pluck("div.{$col}");

            $lvl = fn (string $col) => $base()
                ->join('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
                ->whereNotNull("lvl.{$col}")->where("lvl.{$col}", '<>', '')
                ->distinct()->orderBy("lvl.{$col}")->pluck("lvl.{$col}");

            return [
                'departemen' => $div('nama_sub_divisi'),
                'divisi' => $div('nama_divisi'),
                'level' => $lvl('nama_level'),
                'jabatan' => $lvl('nama_jabatan'),
                'lokasi' => $base()->whereNotNull('k.Cabang_Untuk_Gaji')->where('k.Cabang_Untuk_Gaji', '<>', '')
                    ->distinct()->orderBy('k.Cabang_Untuk_Gaji')->pluck('k.Cabang_Untuk_Gaji'),
                'tipe' => ['Semua', 'Karyawan', 'PHL'],
            ];
        });
    }

    /* ================= MODE 1: PILIH ORANG ================= */

    /**
     * Kandidat berpaginasi + kesadaran "sudah di-assign".
     *
     * @param  array  $f  q, dept[], divisi[], level[], jabatan[], lokasi[], tipe
     * @return array{0:\Illuminate\Support\Collection,1:int} [rows, total]
     */
    public function kandidat(array $f, int $page, int $limit, ?int $idPaket = null): array
    {
        $q = $this->baseKandidat($f);

        $total = (clone $q)->count('k.Kode_Karyawan');

        $select = [
            'k.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan', 'k.Cabang_Untuk_Gaji as Lokasi',
            'div.nama_divisi as Divisi', 'div.nama_sub_divisi as Departemen',
            'lvl.nama_level as Level', 'lvl.nama_jabatan as Jabatan',
        ];

        // Sudah_Assign utk paket terpilih — EXISTS berkorelasi, bukan join yang
        // menggandakan baris. Jumlah_Paket_Aktif memberi konteks beban orangnya.
        if ($idPaket) {
            $select[] = DB::raw(
                "(CASE WHEN EXISTS (SELECT 1 FROM N_SOP_Penjadwalan_Detail sd
                    JOIN N_SOP_Penjadwalan sp ON sp.Id_SOP_Penjadwalan = sd.Id_SOP_Penjadwalan
                    WHERE sd.Kode_Karyawan = k.Kode_Karyawan AND sp.Id_SOP_Paket = {$idPaket}
                      AND sd.Flag_Aktif = 'Y' AND sd.Flag_Cabut_Akses = 'T') THEN 1 ELSE 0 END) as Sudah_Assign",
            );
        }
        $select[] = DB::raw(
            "(SELECT COUNT(*) FROM N_SOP_Penjadwalan_Detail sd2
              WHERE sd2.Kode_Karyawan = k.Kode_Karyawan
                AND sd2.Flag_Aktif = 'Y' AND sd2.Flag_Cabut_Akses = 'T') as Jumlah_Paket_Aktif",
        );

        // Kode_Karyawan (bagian PK) jadi pemecah seri: tanpa kunci urut yang unik,
        // OFFSET/FETCH boleh mengembalikan baris kembar di satu halaman dan
        // melewatkan baris lain — karyawan bernama sama jadi tak pernah tampil.
        $rows = $q->orderBy('k.Nama')->orderBy('k.Kode_Karyawan')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get($select)
            ->map(function ($r) use ($idPaket) {
                $r->Sudah_Assign = $idPaket ? (bool) ($r->Sudah_Assign ?? 0) : false;
                $r->Jumlah_Paket_Aktif = (int) $r->Jumlah_Paket_Aktif;

                return $r;
            });

        return [$rows, $total];
    }

    /* ================= MODE 2: PILIH UNIT ================= */

    /**
     * Pratinjau dampak SEBELUM assign — dari saringan unit ATAU daftar kode.
     *
     * @return array{total:int, baru:int, sudah_assign:int, contoh_sudah:array}
     */
    public function hitungRingkas(array $f, ?array $kodes, int $idPaket): array
    {
        $kosong = ['total' => 0, 'baru' => 0, 'sudah_assign' => 0, 'contoh_sudah' => []];

        // Sudah aktif di paket ini — predikat EXISTS berkorelasi, bukan
        // materialisasi daftar kode lalu IN raksasa.
        $sudahDiPaket = function ($q) use ($idPaket) {
            return $q->whereExists(function ($s) use ($idPaket) {
                $s->select(DB::raw(1))
                    ->from('N_SOP_Penjadwalan_Detail as sd')
                    ->join('N_SOP_Penjadwalan as sp', 'sp.Id_SOP_Penjadwalan', '=', 'sd.Id_SOP_Penjadwalan')
                    ->whereColumn('sd.Kode_Karyawan', 'k.Kode_Karyawan')
                    ->where('sp.Id_SOP_Paket', $idPaket)
                    ->where('sd.Flag_Aktif', SopStatus::AKTIF)
                    ->where('sd.Flag_Cabut_Akses', 'T');
            });
        };

        // ── Dari saringan unit: predikat murni, daftar kode tak pernah dipindahkan ──
        if ($kodes === null) {
            $total = $this->baseKandidat($f)->count('k.Kode_Karyawan');
            if ($total === 0) {
                return $kosong;
            }

            $qSudah = $sudahDiPaket($this->baseKandidat($f));
            $sudah = (clone $qSudah)->count('k.Kode_Karyawan');

            return [
                'total' => $total,
                'baru' => max(0, $total - $sudah),
                'sudah_assign' => $sudah,
                'contoh_sudah' => $qSudah->orderBy('k.Nama')->limit(10)->pluck('k.Nama')->all(),
            ];
        }

        // ── Dari daftar kode: dipecah per bongkah supaya SELURUH kode terhitung.
        // Bongkah adalah pagar parameter whereIn SQL Server, bukan titik potong data:
        // assign() memproses semua kode, jadi pratinjau harus ikut menghitung semua.
        $unik = array_values(array_unique(array_filter(array_map('trim', $kodes))));
        if (empty($unik)) {
            return $kosong;
        }

        $total = 0;
        $sudah = 0;
        $contoh = [];
        foreach (array_chunk($unik, self::CHUNK_KODE) as $potong) {
            $bongkah = fn () => DB::table('Karyawan as k')
                ->where('k.Aktif', 'Y')
                ->whereIn('k.Kode_Karyawan', $potong);

            $total += $bongkah()->count('k.Kode_Karyawan');

            $qSudah = $sudahDiPaket($bongkah());
            $sudah += (clone $qSudah)->count('k.Kode_Karyawan');

            if (count($contoh) < 10) {
                $contoh = array_merge(
                    $contoh,
                    $qSudah->orderBy('k.Nama')->limit(10 - count($contoh))->pluck('k.Nama')->all(),
                );
            }
        }

        if ($total === 0) {
            return $kosong;
        }

        return [
            'total' => $total,
            'baru' => max(0, $total - $sudah),
            'sudah_assign' => $sudah,
            'contoh_sudah' => $contoh,
        ];
    }

    /**
     * Materialisasi saringan → daftar kode. Dipanggil SEKALI saat dispatch;
     * hasilnya di-snapshot ke payload task (retry tak me-resolve ulang).
     */
    public function resolveTargets(array $f): array
    {
        return $this->baseKandidat($f)->orderBy('k.Kode_Karyawan')
            ->pluck('k.Kode_Karyawan')->all();
    }

    /* ================= MODE 3: TEMPEL DAFTAR ================= */

    /**
     * Validasi daftar tempel (NIK / kode karyawan, pemisah bebas: koma/spasi/baris).
     *
     * @return array{valid:array, tidak_ditemukan:array, nonaktif:array, duplikat:array, sudah_assign:array}
     */
    public function validasiTempel(string $teks, ?int $idPaket = null): array
    {
        $mentah = preg_split('/[\s,;]+/', trim($teks)) ?: [];
        $mentah = array_values(array_filter(array_map('trim', $mentah)));

        $duplikat = array_values(array_unique(array_diff_assoc($mentah, array_unique($mentah))));
        $unik = array_values(array_unique($mentah));

        // Kelebihan tempelan dilaporkan lewat `terpotong` — kalau hanya dipotong,
        // kode yang dibuang tak masuk kategori mana pun dan lenyap tanpa jejak.
        $terpotong = max(0, count($unik) - self::MAKS_TEMPEL);
        $unik = array_slice($unik, 0, self::MAKS_TEMPEL);

        if (empty($unik)) {
            return [
                'valid' => [], 'tidak_ditemukan' => [], 'nonaktif' => [],
                'duplikat' => [], 'sudah_assign' => [], 'terpotong' => $terpotong,
            ];
        }

        // Cocokkan ke Kode_Karyawan ATAU No_Induk_Karyawan — orang menempel NIK
        // dari HRIS lain sama seringnya dengan kode internal.
        $ketemu = collect();
        foreach (array_chunk($unik, 400) as $potong) {
            $ketemu = $ketemu->merge(DB::table('Karyawan')
                ->where(function ($w) use ($potong) {
                    $w->whereIn('Kode_Karyawan', $potong)->orWhereIn('No_Induk_Karyawan', $potong);
                })
                ->get(['Kode_Karyawan', 'No_Induk_Karyawan', 'Nama', 'Aktif']));
        }

        $peta = [];
        foreach ($ketemu as $k) {
            $peta[strtoupper((string) $k->Kode_Karyawan)] = $k;
            if (! empty($k->No_Induk_Karyawan)) {
                $peta[strtoupper((string) $k->No_Induk_Karyawan)] = $k;
            }
        }

        $valid = [];
        $nonaktif = [];
        $tidakDitemukan = [];
        $sudahKode = [];
        foreach ($unik as $input) {
            $k = $peta[strtoupper($input)] ?? null;
            if (! $k) {
                $tidakDitemukan[] = $input;

                continue;
            }
            if (($k->Aktif ?? 'T') !== 'Y') {
                $nonaktif[] = ['input' => $input, 'nama' => $k->Nama];

                continue;
            }
            $valid[$k->Kode_Karyawan] = ['kode' => $k->Kode_Karyawan, 'nama' => $k->Nama];
        }
        $valid = array_values($valid); // dedupe bila NIK & kode orang sama ditempel dua-duanya

        if ($idPaket && $valid) {
            $kodeValid = array_column($valid, 'kode');
            foreach (array_chunk($kodeValid, 400) as $potong) {
                $sudahKode = array_merge($sudahKode, DB::table('N_SOP_Penjadwalan_Detail as d')
                    ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                    ->where('pj.Id_SOP_Paket', $idPaket)
                    ->where('d.Flag_Aktif', SopStatus::AKTIF)
                    ->where('d.Flag_Cabut_Akses', 'T')
                    ->whereIn('d.Kode_Karyawan', $potong)
                    ->pluck('d.Kode_Karyawan')->all());
            }
        }

        return [
            'valid' => $valid,
            'tidak_ditemukan' => $tidakDitemukan,
            'nonaktif' => $nonaktif,
            'duplikat' => $duplikat,
            'sudah_assign' => array_values(array_filter($valid, fn ($v) => in_array($v['kode'], $sudahKode, true))),
            'terpotong' => $terpotong,
        ];
    }

    /* ================= INTERNAL ================= */

    /** Query dasar kandidat: karyawan aktif + saringan organisasi sebagai predikat. */
    private function baseKandidat(array $f)
    {
        $q = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y');

        if (! empty($f['dept'])) {
            $q->whereIn('div.nama_sub_divisi', (array) $f['dept']);
        }
        if (! empty($f['divisi'])) {
            $q->whereIn('div.nama_divisi', (array) $f['divisi']);
        }
        if (! empty($f['level'])) {
            $q->whereIn('lvl.nama_level', (array) $f['level']);
        }
        if (! empty($f['jabatan'])) {
            $q->whereIn('lvl.nama_jabatan', (array) $f['jabatan']);
        }
        if (! empty($f['lokasi'])) {
            $q->whereIn('k.Cabang_Untuk_Gaji', (array) $f['lokasi']);
        }

        // PHL = Id_Level 21 (konvensi yang sama dengan picker LMS)
        if (($f['tipe'] ?? 'Semua') === 'PHL') {
            $q->where('lvl.Id_Level', self::LEVEL_PHL);
        } elseif (($f['tipe'] ?? 'Semua') === 'Karyawan') {
            $q->where(fn ($w) => $w->where('lvl.Id_Level', '<>', self::LEVEL_PHL)->orWhereNull('lvl.Id_Level'));
        }

        $cari = trim((string) ($f['q'] ?? ''));
        if (mb_strlen($cari) >= 2) {
            $q->where(function ($w) use ($cari) {
                $w->where('k.Nama', 'LIKE', "%{$cari}%")
                    ->orWhere('k.Kode_Karyawan', 'LIKE', "%{$cari}%")
                    ->orWhere('k.No_Induk_Karyawan', 'LIKE', "%{$cari}%");
            });
        }

        return $q;
    }
}
