<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopGerbangPublikKuis;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service Master Paket SOP (Builder — level paket).
 * Menangani CRUD identitas paket + transisi status (publish/archive).
 * Item & versi ditangani service terpisah (SopItemService/SopItemVersionService).
 */
class SopPaketService
{
    private string $table = 'N_SOP_Master_Paket';

    /**
     * Kolom yang mewakili tiap sumbu pengelompokan rail Builder.
     *
     * CERMIN dari `SUMBU_PAKET` di resources/js/…/Governance/Admin/sumbuPaket.js
     * — layar menawarkan sumbu dari daftar itu, server menolak apa pun di luar
     * daftar ini. Keduanya harus diubah bersama; sumbu yang ditawarkan layar
     * tapi tak dikenal server berakhir sebagai rail kosong tanpa pesan.
     */
    private const SUMBU_GRUP = [
        'prioritas' => 'p.Prioritas',
        'status' => 'p.Status',
        'distribusi' => 'p.Mode_Distribusi',
    ];

    /** Batas menit tiap ember estimasi. null = tak berbatas di sisi itu. */
    private const EMBER_ESTIMASI = [
        'pendek' => [null, 10],
        'sedang' => [11, 30],
        'panjang' => [31, null],
    ];

    /** Daftar bersih dari nilai skalar, null, atau anggota kosong. */
    private function daftar($v): array
    {
        if ($v === null || $v === '' || $v === []) {
            return [];
        }

        return array_values(array_filter(
            is_array($v) ? $v : [$v],
            fn ($x) => $x !== null && $x !== '' && $x !== false,
        ));
    }

    /**
     * Batas bawah tanggal untuk ember terbit. Null = ember tak berbatas bawah.
     *
     * ATURAN TANGGAL TINGGAL DI SATU TEMPAT, yaitu di sini. Layar mengirim
     * KEADAAN ("kuartal berjalan"), bukan dua tanggal hasil hitungannya
     * sendiri: dua salinan aturan tanggal akan menyimpang pada pergantian
     * kuartal, pada zona waktu peramban yang berbeda, atau ketika satu di
     * antaranya diperbaiki dan yang lain tidak.
     */
    private function batasEmberTerbit(string $ember): ?string
    {
        $kini = now();

        return match ($ember) {
            '7hari' => $kini->copy()->subDays(7)->toDateString(),
            '30hari' => $kini->copy()->subDays(30)->toDateString(),
            'kuartal' => $kini->copy()->firstOfQuarter()->toDateString(),
            'tahun' => $kini->copy()->startOfYear()->toDateString(),
            default => null,
        };
    }

    /**
     * Terapkan filter list ke query (dipakai paginate, statusCounts, grupCounts).
     *
     * $filters: search, status[], prioritas[], distribusi[],
     *           absensi(Y/T), estimasi(ember), terbit(ember), tgl_dari, tgl_sampai.
     *
     * $abaikan = kunci sumbu yang saringannya TIDAK diterapkan. Dipakai saat
     * menghitung sebaran sebuah sumbu: rail Prioritas yang menghormati saringan
     * prioritasnya sendiri hanya akan pernah menampilkan satu baris, yaitu yang
     * sudah dipilih — sehingga tak ada lagi jalan untuk berpindah tingkat.
     */
    private function applyFilters($query, array $filters, array $abaikan = []): void
    {
        $pakai = fn (string $kunci) => ! in_array($kunci, $abaikan, true);

        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('p.Nama_Paket', 'LIKE', "%{$s}%")
                    ->orWhere('p.Kode_Paket', 'LIKE', "%{$s}%");
            });
        }

        foreach (['status' => 'p.Status',
            'prioritas' => 'p.Prioritas', 'distribusi' => 'p.Mode_Distribusi'] as $kunci => $kolom) {
            if (! $pakai($kunci)) {
                continue;
            }
            $nilai = $this->daftar($filters[$kunci] ?? null);
            if ($nilai) {
                $query->whereIn($kolom, $nilai);
            }
        }

        if ($pakai('departemen') && ! empty($filters['departemen'])) {
            $deps = $this->daftar($filters['departemen']);
            $adaKosong = in_array('__kosong__', $deps, true) || in_array('Tanpa Pemilik', $deps, true) || in_array('Tanpa pemilik', $deps, true);
            $numericIds = array_values(array_filter($deps, 'is_numeric'));
            $nameStrings = array_values(array_filter($deps, fn ($x) => ! is_numeric($x) && ! in_array($x, ['__kosong__', 'Tanpa Pemilik', 'Tanpa pemilik'], true)));

            $query->whereExists(function ($sub) use ($adaKosong, $numericIds, $nameStrings) {
                $sub->select(DB::raw(1))
                    ->from('N_SOP_Paket_Item as i')
                    ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
                    ->whereColumn('i.Id_SOP_Paket', 'p.Id_SOP_Paket')
                    ->where('i.Flag_Aktif', SopStatus::AKTIF)
                    ->where('dok.Flag_Aktif', SopStatus::AKTIF)
                    ->where(function ($w) use ($adaKosong, $numericIds, $nameStrings) {
                        $first = true;
                        if ($adaKosong) {
                            $w->whereNull('dok.Departemen_Id');
                            $first = false;
                        }
                        if (! empty($numericIds)) {
                            if ($first) {
                                $w->whereIn('dok.Departemen_Id', $numericIds);
                                $first = false;
                            } else {
                                $w->orWhereIn('dok.Departemen_Id', $numericIds);
                            }
                        }
                        if (! empty($nameStrings)) {
                            $subQuery = function ($dSub) use ($nameStrings) {
                                $dSub->select(DB::raw(1))
                                    ->from('N_HRIS_Master_Departemen as dpt')
                                    ->whereColumn('dpt.Id_Departemen', 'dok.Departemen_Id')
                                    ->whereIn('dpt.Nama_Departemen', $nameStrings);
                            };
                            if ($first) {
                                $w->whereExists($subQuery);
                            } else {
                                $w->orWhereExists($subQuery);
                            }
                        }
                    });
            });
        }

        if ($pakai('jenis') && ! empty($filters['jenis'])) {
            $jns = $this->daftar($filters['jenis']);
            $adaKosong = in_array('__kosong__', $jns, true) || in_array('Tanpa Jenis', $jns, true) || in_array('Tanpa jenis', $jns, true) || in_array('Tanpa Dokumen', $jns, true);
            $numericIds = array_values(array_filter($jns, 'is_numeric'));
            $nameStrings = array_values(array_filter($jns, fn ($x) => ! is_numeric($x) && ! in_array($x, ['__kosong__', 'Tanpa Jenis', 'Tanpa jenis', 'Tanpa Dokumen'], true)));

            $query->whereExists(function ($sub) use ($adaKosong, $numericIds, $nameStrings) {
                $sub->select(DB::raw(1))
                    ->from('N_SOP_Paket_Item as i')
                    ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
                    ->whereColumn('i.Id_SOP_Paket', 'p.Id_SOP_Paket')
                    ->where('i.Flag_Aktif', SopStatus::AKTIF)
                    ->where('dok.Flag_Aktif', SopStatus::AKTIF)
                    ->where(function ($w) use ($adaKosong, $numericIds, $nameStrings) {
                        $first = true;
                        if ($adaKosong) {
                            $w->whereNull('dok.Jenis_Dokumen_Id');
                            $first = false;
                        }
                        if (! empty($numericIds)) {
                            if ($first) {
                                $w->whereIn('dok.Jenis_Dokumen_Id', $numericIds);
                                $first = false;
                            } else {
                                $w->orWhereIn('dok.Jenis_Dokumen_Id', $numericIds);
                            }
                        }
                        if (! empty($nameStrings)) {
                            $subQuery = function ($jSub) use ($nameStrings) {
                                $jSub->select(DB::raw(1))
                                    ->from('N_SOP_Master_Jenis_Dokumen as mjd')
                                    ->whereColumn('mjd.Id_SOP_Jenis_Dokumen', 'dok.Jenis_Dokumen_Id')
                                    ->whereIn('mjd.Nama_Jenis', $nameStrings);
                            };
                            if ($first) {
                                $w->whereExists($subQuery);
                            } else {
                                $w->orWhereExists($subQuery);
                            }
                        }
                    });
            });
        }

        if ($pakai('apqc') && ! empty($filters['apqc'])) {
            $apqcs = $this->daftar($filters['apqc']);
            $adaKosong = in_array('__kosong__', $apqcs, true) || in_array('Tanpa Proses', $apqcs, true) || in_array('Tanpa proses', $apqcs, true);
            $numericIds = array_values(array_filter($apqcs, 'is_numeric'));
            $nameStrings = array_values(array_filter($apqcs, fn ($x) => ! is_numeric($x) && ! in_array($x, ['__kosong__', 'Tanpa Proses', 'Tanpa proses'], true)));

            $query->whereExists(function ($sub) use ($adaKosong, $numericIds, $nameStrings) {
                $sub->select(DB::raw(1))
                    ->from('N_SOP_Paket_Item as i')
                    ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
                    ->whereColumn('i.Id_SOP_Paket', 'p.Id_SOP_Paket')
                    ->where('i.Flag_Aktif', SopStatus::AKTIF)
                    ->where('dok.Flag_Aktif', SopStatus::AKTIF)
                    ->where(function ($w) use ($adaKosong, $numericIds, $nameStrings) {
                        $first = true;
                        if ($adaKosong) {
                            $w->whereNull('dok.APQC_Id');
                            $first = false;
                        }
                        if (! empty($numericIds)) {
                            if ($first) {
                                $w->whereIn('dok.APQC_Id', $numericIds);
                                $first = false;
                            } else {
                                $w->orWhereIn('dok.APQC_Id', $numericIds);
                            }
                        }
                        if (! empty($nameStrings)) {
                            $subQuery = function ($aSub) use ($nameStrings) {
                                $aSub->select(DB::raw(1))
                                    ->from('N_SOP_Master_APQC as map')
                                    ->whereColumn('map.Id_SOP_APQC', 'dok.APQC_Id')
                                    ->whereIn('map.Nama_Proses', $nameStrings);
                            };
                            if ($first) {
                                $w->whereExists($subQuery);
                            } else {
                                $w->orWhereExists($subQuery);
                            }
                        }
                    });
            });
        }

        if (! empty($filters['absensi'])) {
            $query->where('p.Flag_Absensi', $filters['absensi']);
        }

        if ($pakai('estimasi') && ! empty($filters['estimasi'])) {
            $ember = $filters['estimasi'];
            if ($ember === 'tanpa') {
                /* "Tanpa estimasi" mencakup NULL DAN nol. Kolomnya boleh
                   berisi 0 dari paket lama yang disimpan sebelum ladangnya
                   opsional, dan 0 menit bukan perkiraan — ia ketiadaan. */
                $query->where(function ($q) {
                    $q->whereNull('p.Estimasi_Waktu_Baca_Menit')
                        ->orWhere('p.Estimasi_Waktu_Baca_Menit', '<=', 0);
                });
            } elseif (isset(self::EMBER_ESTIMASI[$ember])) {
                [$min, $max] = self::EMBER_ESTIMASI[$ember];
                $query->where('p.Estimasi_Waktu_Baca_Menit', '>', 0);
                if ($min !== null) {
                    $query->where('p.Estimasi_Waktu_Baca_Menit', '>=', $min);
                }
                if ($max !== null) {
                    $query->where('p.Estimasi_Waktu_Baca_Menit', '<=', $max);
                }
            }
        }

        if ($pakai('terbit') && ! empty($filters['terbit'])) {
            if ($filters['terbit'] === 'belum') {
                $query->whereNull('p.Tanggal_Publish');
            } elseif ($batas = $this->batasEmberTerbit($filters['terbit'])) {
                if ($awal = $this->awalHari($batas)) {
                    $query->where('p.Tanggal_Publish', '>=', $awal);
                }
            }
        }

        if (! empty($filters['tgl_dari']) && ($awal = $this->awalHari($filters['tgl_dari']))) {
            $query->where('p.Tanggal_Publish', '>=', $awal);
        }
        if (! empty($filters['tgl_sampai']) && ($awal = $this->awalHari($filters['tgl_sampai']))) {
            $query->where('p.Tanggal_Publish', '<', $awal->copy()->addDay());
        }
    }

    /**
     * Tengah malam awal hari dari sebuah nilai tanggal; null bila tak terbaca.
     *
     * Dipakai untuk menyaring `Tanggal_Publish` sebagai RENTANG, bukan lewat
     * whereDate(). whereDate() membungkus kolomnya dalam CAST(... AS DATE)
     * sehingga indeks atas kolom itu tak bisa dipakai — perbandingan rentang
     * membiarkan kolomnya telanjang di satu sisi.
     *
     * Batas atasnya sengaja `< hari berikutnya`, bukan `<= hari yang sama`:
     * Tanggal_Publish adalah datetime2 (kolom waktu diisi now() saat paket
     * diterbitkan, dan ditampilkan sampai jam-menit di timeline), sehingga
     * `<= '2026-09-01'` berarti `<= 2026-09-01 00:00:00` dan membuang SELURUH
     * paket yang terbit pada hari itu setelah tengah malam. Bentuk setengah
     * terbuka inilah yang menjaga hasilnya tetap sama dengan whereDate().
     *
     * Nilainya datang dari query string tanpa validasi, jadi yang tak terbaca
     * dilaporkan lalu saringannya dilewati — sebelumnya nilai semacam itu
     * menjadi galat konversi di basis data.
     */
    private function awalHari($nilai): ?Carbon
    {
        try {
            return Carbon::parse($nilai)->startOfDay();
        } catch (\Throwable $e) {
            Log::warning('SOP paket: saringan Tanggal_Publish diabaikan, nilai tanggal tak terbaca', [
                'nilai' => is_scalar($nilai) ? (string) $nilai : gettype($nilai),
            ]);

            return null;
        }
    }

    /** List paket + cover (paginated, ter-filter). Return [rows(encoded), total]. */
    public function paginate(array $filters, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $selects = [
            'p.Id_SOP_Paket',
            'p.Kode_Paket',
            'p.Nama_Paket',
            'p.Prioritas',
            'p.Status',
            'p.Mode_Urutan_Item',
            'p.Mode_Distribusi',
            'p.Tanggal_Publish',
            'p.Cover_Path',
            'p.Cover_Position',
            'p.Flag_Absensi',
            'p.Estimasi_Waktu_Baca_Menit',
        ];
        if (SkemaTersedia::kolom($this->table, 'Versi_Paket_Aktif')) {
            $selects[] = 'p.Versi_Paket_Aktif';
        }

        $query = DB::table("{$this->table} as p")
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->select($selects);

        $this->applyFilters($query, $filters);

        $total = $query->count();

        // Urutan
        switch ($filters['sort'] ?? 'newest') {
            case 'oldest':    $query->orderBy('p.Id_SOP_Paket', 'ASC');
                break;
            case 'nama':      $query->orderBy('p.Nama_Paket', 'ASC');
                break;
            case 'prioritas':
                /* Urutan dibaca dari master, bukan ditebak dari nama. CASE
                   bernama yang dulu di sini menaruh tingkat apa pun selain
                   KRITIS/TINGGI ke kelompok "sisanya" — artinya tingkat baru
                   yang justru lebih mendesak mendarat di bawah Normal. */
                [$sqlUrut, $bindUrut] = app(SopPrioritasService::class)->urutanCase('p.Prioritas');
                $query->orderByRaw($sqlUrut.', p.Id_SOP_Paket DESC', $bindUrut);
                break;
            case 'estimasi_pendek':
            case 'estimasi_panjang':
                /* Paket tanpa estimasi selalu di BAWAH, di kedua arah. Ia bukan
                   nilai terkecil melainkan nilai yang belum ada, dan SQL Server
                   mengurutkan NULL paling awal pada ASC — sehingga "estimasi
                   terpendek" tanpa penjaga ini membuka daftar dengan deretan
                   paket yang justru belum punya perkiraan sama sekali. */
                $arah = $filters['sort'] === 'estimasi_pendek' ? 'ASC' : 'DESC';
                $query->orderByRaw(
                    'CASE WHEN p.Estimasi_Waktu_Baca_Menit IS NULL OR p.Estimasi_Waktu_Baca_Menit <= 0 THEN 1 ELSE 0 END ASC, '
                    ."p.Estimasi_Waktu_Baca_Menit {$arah}, p.Id_SOP_Paket DESC"
                );
                break;
            default:          $query->orderBy('p.Id_SOP_Paket', 'DESC');
                break;
        }

        $rawRows = $query->offset($offset)->limit($limit)->get();
        $rawIds = $rawRows->pluck('Id_SOP_Paket')->all();

        $sectionCounts = empty($rawIds) ? collect() : DB::table('N_SOP_Paket_Section')
            ->whereIn('Id_SOP_Paket', $rawIds)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('Id_SOP_Paket')
            ->select('Id_SOP_Paket', DB::raw('COUNT(*) as total_sec'))
            ->pluck('total_sec', 'Id_SOP_Paket');

        $itemStats = empty($rawIds) ? collect() : DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket', $rawIds)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('Id_SOP_Paket')
            ->select(
                'Id_SOP_Paket',
                DB::raw('COUNT(*) as total_item'),
                DB::raw("SUM(CASE WHEN Flag_Wajib = 'Y' THEN 1 ELSE 0 END) as total_wajib"),
                DB::raw('COUNT(DISTINCT Id_SOP_Dokumen) as total_dokumen'),
                DB::raw("SUM(CASE WHEN Tipe_Item = 'KUIS' THEN 1 ELSE 0 END) as total_kuis"),
                DB::raw("SUM(CASE WHEN Tipe_Item = 'VIDEO' THEN 1 ELSE 0 END) as total_video")
            )
            ->get()
            ->keyBy('Id_SOP_Paket');

        $tipeItemsMap = empty($rawIds) ? collect() : DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket', $rawIds)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->select('Id_SOP_Paket', 'Tipe_Item')
            ->get()
            ->groupBy('Id_SOP_Paket')
            ->map(fn ($group) => $group->pluck('Tipe_Item')->values()->all());

        $draftRevisi = empty($rawIds) ? collect() : DB::table('N_SOP_Paket_Draft')
            ->whereIn('Id_SOP_Paket', $rawIds)
            ->pluck('Id_SOP_Paket')
            ->flip();

        $rows = $rawRows->map(function ($item) use ($sectionCounts, $itemStats, $tipeItemsMap, $draftRevisi) {
            $rawId = $item->Id_SOP_Paket;
            $stats = $itemStats->get($rawId);

            $item->Total_Section = (int) ($sectionCounts[$rawId] ?? 0);
            $item->Total_Item = (int) ($stats->total_item ?? 0);
            $item->Total_Wajib = (int) ($stats->total_wajib ?? 0);
            $item->Total_Dokumen = (int) ($stats->total_dokumen ?? 0);
            $item->Total_Kuis = (int) ($stats->total_kuis ?? 0);
            $item->Total_Video = (int) ($stats->total_video ?? 0);
            $item->Tipe_Items = $tipeItemsMap[$rawId] ?? [];
            $item->Has_Draft_Revisi = isset($draftRevisi[$rawId]);

            $item->Cover_Url = $this->coverUrl($item->Cover_Path ?? null);
            unset($item->Cover_Path);
            $item->Id_SOP_Paket = Hashids::connection('custom')->encode($item->Id_SOP_Paket);

            return $item;
        });

        return [$rows, $total];
    }

    /** Hitung jumlah paket per-status (hormati filter non-status) untuk badge tab. */
    public function statusCounts(array $filters): array
    {
        $query = DB::table("{$this->table} as p")->where('p.Flag_Aktif', SopStatus::AKTIF);
        $this->applyFilters($query, $filters, ['status']);

        $rows = $query->groupBy('p.Status')
            ->select('p.Status', DB::raw('COUNT(*) as jml'))
            ->pluck('jml', 'Status');

        $draft = (int) ($rows['DRAFT'] ?? 0);
        $published = (int) ($rows['PUBLISHED'] ?? 0);
        $archived = (int) ($rows['ARCHIVED'] ?? 0);

        $totalDokumen = DB::table('N_SOP_Paket_Item')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereNotNull('Id_SOP_Dokumen')
            ->distinct()
            ->count('Id_SOP_Dokumen');

        $totalDraftRevisi = DB::table('N_SOP_Paket_Draft')->count();

        return [
            'ALL' => $draft + $published + $archived,
            'DRAFT' => $draft,
            'PUBLISHED' => $published,
            'ARCHIVED' => $archived,
            'TOTAL_DOKUMEN' => $totalDokumen,
            'TOTAL_REVISI' => $totalDraftRevisi,
        ];
    }

    /** Sumbu yang boleh diminta untuk hitungan kelompok rail. */
    public function sumbuGrupDikenal(string $sumbu): bool
    {
        return isset(self::SUMBU_GRUP[$sumbu]) || in_array($sumbu, SopIdentitasDokumenService::SUMBU, true);
    }

    /**
     * Sebaran paket pada satu sumbu, untuk rail Builder.
     *
     * MENGHORMATI SELURUH SARINGAN KECUALI SARINGAN SUMBU ITU SENDIRI. Rail
     * adalah alat untuk BERPINDAH antar kelompok; kalau ia ikut disaring oleh
     * kelompok yang sedang dipilih, ia menyusut jadi satu baris dan jalan
     * kembalinya hilang. Polanya sama persis dengan statusCounts, yang sejak
     * awal mengabaikan saringan status demi badge tab-nya.
     *
     * Angka di sini SENGAJA dihitung atas seluruh baris yang cocok, bukan atas
     * halaman yang sedang tampil. Bentuk lama menghitungnya di layar dari sepuluh
     * baris halaman berjalan, sehingga kelompok berisi empat puluh paket bisa
     * tertulis "3" dan di halaman kedua lenyap sama sekali.
     *
     * @return array<int, array{nilai: string|null, nama: string|null, total: int}>
     */
    public function grupCounts(array $filters, string $sumbu): array
    {
        if (in_array($sumbu, SopIdentitasDokumenService::SUMBU, true)) {
            $q = DB::table("{$this->table} as p")->where('p.Flag_Aktif', SopStatus::AKTIF);
            $this->applyFilters($q, $filters, [$sumbu]);
            $ids = $q->pluck('p.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();
            $rekap = (new SopIdentitasDokumenService)->rekap($ids, $sumbu);

            return array_map(fn ($g) => [
                'nilai' => $g['nama'],
                'nama' => $g['nama'],
                'total' => (int) $g['total'],
            ], $rekap['grup']);
        }

        if (! isset(self::SUMBU_GRUP[$sumbu])) {
            return [];
        }

        $kolom = self::SUMBU_GRUP[$sumbu];
        $query = DB::table("{$this->table} as p")->where('p.Flag_Aktif', SopStatus::AKTIF);

        $select = [DB::raw("{$kolom} as nilai"), DB::raw('COUNT(*) as total')];
        $group = [$kolom];

        $this->applyFilters($query, $filters, [$sumbu]);

        $rows = $query->select($select)->groupBy($group)->get();

        return $rows->map(function ($r) {
            $nilai = $r->nilai;

            return [
                'nilai' => $nilai === null || $nilai === '' ? null : (string) $nilai,
                'nama' => $r->nama ?? null,
                'total' => (int) $r->total,
            ];
        })->all();
    }

    /**
     * Struktur pohon folder (Departemen -> Jenis Dokumen) untuk Pohon Folder Builder.
     *
     * Menghitung sebaran paket di seluruh departemen dan jenis dokumen yang terkandung
     * di dalam dokumen paket, dengan tetap menghormati filter global lain (search, prioritas,
     * status, distribusi, dll.).
     *
     * @return array<int, array{id: string, nama: string, kode: string|null, kosong: bool, total: int, children: array}>
     */
    public function treeCounts(array $filters): array
    {
        $q = DB::table("{$this->table} as p")->where('p.Flag_Aktif', SopStatus::AKTIF);
        $this->applyFilters($q, $filters, ['departemen', 'jenis']);
        $paketIds = $q->pluck('p.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();

        if (empty($paketIds)) {
            return [];
        }

        $rows = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'dok.Departemen_Id')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->whereIn('i.Id_SOP_Paket', $paketIds)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('dok.Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->get([
                'i.Id_SOP_Paket',
                'dok.Departemen_Id', 'dpt.Nama_Departemen', 'dpt.Kode_Departemen',
                'dok.Jenis_Dokumen_Id', 'jd.Nama_Jenis', 'jd.Kode_Jenis_Dokumen', 'jd.Level_Hirarki',
            ]);

        $deptMap = [];
        $paketDepts = [];

        foreach ($rows as $r) {
            $idPaket = (int) $r->Id_SOP_Paket;
            $deptNama = $r->Nama_Departemen ?: 'Tanpa Pemilik';
            $deptKosong = empty($r->Nama_Departemen);
            $jenisNama = $r->Nama_Jenis ?: 'Tanpa Jenis';
            $jenisKosong = empty($r->Nama_Jenis);

            if (! isset($deptMap[$deptNama])) {
                $deptMap[$deptNama] = [
                    'id' => 'dept_'.md5($deptNama),
                    'nama' => $deptNama,
                    'kode' => $r->Kode_Departemen,
                    'kosong' => $deptKosong,
                    'pakets' => [],
                    'sub' => [],
                ];
            }
            $deptMap[$deptNama]['pakets'][$idPaket] = true;

            if (! isset($deptMap[$deptNama]['sub'][$jenisNama])) {
                $deptMap[$deptNama]['sub'][$jenisNama] = [
                    'id' => 'jns_'.md5($deptNama.'_'.$jenisNama),
                    'nama' => $jenisNama,
                    'kode' => $r->Kode_Jenis_Dokumen,
                    'level' => $r->Level_Hirarki !== null ? (int) $r->Level_Hirarki : 99,
                    'kosong' => $jenisKosong,
                    'pakets' => [],
                ];
            }
            $deptMap[$deptNama]['sub'][$jenisNama]['pakets'][$idPaket] = true;
            $paketDepts[$idPaket] = true;
        }

        // Tangani paket yang belum memiliki item dokumen sama sekali
        $unattached = array_diff($paketIds, array_keys($paketDepts));
        if (! empty($unattached)) {
            $deptNama = 'Tanpa Pemilik';
            if (! isset($deptMap[$deptNama])) {
                $deptMap[$deptNama] = [
                    'id' => 'dept_'.md5($deptNama),
                    'nama' => $deptNama,
                    'kode' => null,
                    'kosong' => true,
                    'pakets' => [],
                    'sub' => [],
                ];
            }
            foreach ($unattached as $uId) {
                $deptMap[$deptNama]['pakets'][$uId] = true;
                if (! isset($deptMap[$deptNama]['sub']['Tanpa Dokumen'])) {
                    $deptMap[$deptNama]['sub']['Tanpa Dokumen'] = [
                        'id' => 'jns_'.md5($deptNama.'_tanpa_dok'),
                        'nama' => 'Tanpa Dokumen',
                        'kode' => null,
                        'level' => 999,
                        'kosong' => true,
                        'pakets' => [],
                    ];
                }
                $deptMap[$deptNama]['sub']['Tanpa Dokumen']['pakets'][$uId] = true;
            }
        }

        $tree = [];
        foreach ($deptMap as $d) {
            $children = [];
            foreach ($d['sub'] as $s) {
                $children[] = [
                    'id' => $s['id'],
                    'nama' => $s['nama'],
                    'kode' => $s['kode'],
                    'level' => $s['level'],
                    'kosong' => $s['kosong'],
                    'total' => count($s['pakets']),
                ];
            }
            usort($children, fn ($a, $b) => [$a['kosong'], $a['level'], $a['nama']] <=> [$b['kosong'], $b['level'], $b['nama']]);

            $tree[] = [
                'id' => $d['id'],
                'nama' => $d['nama'],
                'kode' => $d['kode'],
                'kosong' => $d['kosong'],
                'total' => count($d['pakets']),
                'children' => $children,
            ];
        }

        usort($tree, fn ($a, $b) => [$a['kosong'], $a['nama']] <=> [$b['kosong'], $b['nama']]);

        return $tree;
    }

    /** Detail satu paket (untuk editor). Return object|null (Id encoded). */
    public function find(int $id)
    {
        $row = DB::table("{$this->table} as p")
            ->where('p.Id_SOP_Paket', $id)
            ->select('p.*')
            ->first();

        if ($row) {
            $row->Id_SOP_Paket = Hashids::connection('custom')->encode($row->Id_SOP_Paket);
            // URL cover bertanda tangan (20 menit) bila ada Cover_Path
            $row->Cover_Url = $this->coverUrl($row->Cover_Path ?? null);
        }

        return $row;
    }

    /** Signed URL cover (null bila kosong / gagal). */
    public function coverUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            Log::error('SOP paket coverUrl gagal', ['exception' => $e]);

            return null;
        }
    }

    /** Buat paket baru (status DRAFT). Return Id (int). */
    public function create(array $data, int $userId): int
    {
        return DB::table($this->table)->insertGetId([
            'Kode_Paket' => $data['Kode_Paket'] ?? null,
            'Nama_Paket' => $data['Nama_Paket'],
            'Kode_Karyawan_Owner' => $data['Kode_Karyawan_Owner'] ?? null,
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Tujuan_Ruang_Lingkup' => $data['Tujuan_Ruang_Lingkup'] ?? null,
            'Prioritas' => $data['Prioritas'] ?? app(SopPrioritasService::class)->kodeBawaan(),
            'Estimasi_Waktu_Baca_Menit' => $data['Estimasi_Waktu_Baca_Menit'] ?? null,
            'Mode_Urutan_Item' => $data['Mode_Urutan_Item'] ?? 'BEBAS',
            // default TERBATAS: keterbukaan harus dipilih sadar, bukan kebetulan
            'Mode_Distribusi' => $data['Mode_Distribusi'] ?? 'TERBATAS',
            'Cover_Path' => $data['Cover_Path'] ?? null,
            'Cover_Position' => $data['Cover_Position'] ?? null,
            'Flag_Absensi' => ($data['Flag_Absensi'] ?? false) ? 'Y' : 'T',
            'Status' => SopStatus::PAKET_DRAFT,
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket');
    }

    /**
     * Deep-clone paket → paket DRAFT baru (sections + items + versi aktif). Return id baru/null.
     * Cover TIDAK disalin (hindari hapus objek GCS bersama saat cover clone diganti).
     * Versi disalin hanya versi AKTIF (bukan seluruh histori) — via SopItemService::cloneRow.
     */
    public function clonePaket(int $id, int $userId, SopItemService $itemSvc, SopItemVersionService $versiSvc): ?int
    {
        $src = DB::table($this->table)->where('Id_SOP_Paket', $id)->where('Flag_Aktif', SopStatus::AKTIF)->first();
        if (! $src) {
            return null;
        }

        $newPaket = DB::table($this->table)->insertGetId([
            'Kode_Paket' => null,
            'Nama_Paket' => mb_substr((string) $src->Nama_Paket, 0, 190).' (Salinan)',
            'Kode_Karyawan_Owner' => $src->Kode_Karyawan_Owner,
            'Deskripsi' => $src->Deskripsi,
            'Tujuan_Ruang_Lingkup' => $src->Tujuan_Ruang_Lingkup,
            'Prioritas' => $src->Prioritas,
            'Estimasi_Waktu_Baca_Menit' => $src->Estimasi_Waktu_Baca_Menit,
            'Mode_Urutan_Item' => $src->Mode_Urutan_Item,
            'Mode_Distribusi' => $src->Mode_Distribusi ?? 'TERBATAS',
            'Cover_Path' => null,
            'Flag_Absensi' => $src->Flag_Absensi,
            'Status' => SopStatus::PAKET_DRAFT,
            'Created_By' => $userId,
            'Created_At' => now(),
            'Flag_Aktif' => SopStatus::AKTIF,
        ], 'Id_SOP_Paket');

        // sections (remap id lama → baru)
        $map = [];
        $sections = DB::table('N_SOP_Paket_Section')->where('Id_SOP_Paket', $id)->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        foreach ($sections as $s) {
            $newSec = DB::table('N_SOP_Paket_Section')->insertGetId([
                'Id_SOP_Paket' => $newPaket,
                'Nama_Section' => $s->Nama_Section,
                'Deskripsi' => $s->Deskripsi,
                'Urutan' => $s->Urutan,
                'Created_By' => $userId,
                'Created_At' => now(),
                'Flag_Aktif' => SopStatus::AKTIF,
            ], 'Id_SOP_Section');
            $map[$s->Id_SOP_Section] = $newSec;
        }

        // items (+ versi aktif) via cloneRow
        $items = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Paket', $id)->where('Flag_Aktif', SopStatus::AKTIF)->orderBy('Urutan')->get();
        foreach ($items as $it) {
            $newSection = $it->Id_SOP_Section ? ($map[$it->Id_SOP_Section] ?? null) : null;
            $itemSvc->cloneRow($it, $newPaket, $newSection, $userId, $versiSvc);
        }

        return $newPaket;
    }

    /** Update meta paket. Cover_Path hanya ditimpa bila key-nya disertakan. */
    public function update(int $id, array $data, int $userId): void
    {
        $payload = [
            'Kode_Paket' => $data['Kode_Paket'] ?? null,
            'Nama_Paket' => $data['Nama_Paket'],
            'Kode_Karyawan_Owner' => $data['Kode_Karyawan_Owner'] ?? null,
            'Deskripsi' => $data['Deskripsi'] ?? null,
            'Tujuan_Ruang_Lingkup' => $data['Tujuan_Ruang_Lingkup'] ?? null,
            'Prioritas' => $data['Prioritas'] ?? app(SopPrioritasService::class)->kodeBawaan(),
            'Estimasi_Waktu_Baca_Menit' => $data['Estimasi_Waktu_Baca_Menit'] ?? null,
            'Mode_Urutan_Item' => $data['Mode_Urutan_Item'] ?? 'BEBAS',
            // default TERBATAS: keterbukaan harus dipilih sadar, bukan kebetulan
            'Mode_Distribusi' => $data['Mode_Distribusi'] ?? 'TERBATAS',
            'Flag_Absensi' => ($data['Flag_Absensi'] ?? false) ? 'Y' : 'T',
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ];
        if (array_key_exists('Cover_Path', $data)) {
            $payload['Cover_Path'] = $data['Cover_Path'];
        }
        if (array_key_exists('Cover_Position', $data)) {
            $payload['Cover_Position'] = $data['Cover_Position'];
        }
        DB::table($this->table)->where('Id_SOP_Paket', $id)->update($payload);
    }

    /** Ambil Cover_Path mentah (untuk hapus objek lama saat diganti). */
    public function coverPath(int $id): ?string
    {
        return DB::table($this->table)->where('Id_SOP_Paket', $id)->value('Cover_Path');
    }

    /**
     * Ubah status paket (DRAFT→PUBLISHED / →ARCHIVED).
     * CATATAN: publish di sini baru menandai status. Reconciliation (seed progress,
     * rollup, notif) menyusul di ProcessSopPublish — pekerjaan lanjutan.
     */
    /**
     * Item ber-versi WAJIB punya versi berlaku sebelum paketnya terbit.
     *
     * Aturan ini sudah ada di jalur publish yang satunya —
     * SopPaketDraftService::validationErrors(), yang menolak dengan "Item X
     * belum memiliki versi Siap Rilis". Jalur INI tidak memilikinya, dan
     * itulah yang meloloskan sebuah item WAJIB tanpa versi ke paket terbit
     * pada 3 Agustus: lima karyawan menerima item yang mustahil diselesaikan,
     * sehingga kepatuhan mereka tertahan permanen tanpa satu pun layar
     * melaporkan sebabnya.
     *
     * Pola yang sama sudah dua kali muncul di modul ini: aturan yang hidup di
     * satu pemanggil, sementara pemanggil lain memakai jalurnya sendiri.
     * Pintu yang tak terjaga selalu yang lebih sering dilewati.
     *
     * @throws \DomainException
     */
    private function pastikanItemSiapTerbit(int $idPaket): void
    {
        $tanpaVersi = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('Tipe_Item', SopTipeItem::VERSIONED)
            ->whereNull('Id_Item_Versi_Aktif')
            ->pluck('Judul_Item');

        if ($tanpaVersi->isEmpty()) {
            return;
        }

        /* Judulnya disebut, bukan cuma jumlahnya: admin harus tahu item MANA
           yang harus dibereskan, bukan disuruh mencarinya sendiri di paket
           yang bisa berisi puluhan item. */
        throw new \DomainException(
            'Paket belum bisa diterbitkan. Item berikut belum punya versi berlaku: '
            .$tanpaVersi->map(fn ($j) => '"'.($j ?: 'tanpa judul').'"')->implode(', ')
            .'. Lampirkan berkasnya lalu terbitkan versinya lebih dulu.');
    }

    /**
     * Paket PUBLIK tak boleh terbit membawa item KUIS.
     *
     * Aturannya sendiri tinggal di SopGerbangPublikKuis; yang ada di sini
     * hanyalah cara membaca keadaan paket LIVE. Pintu satunya (publish lewat
     * working draft) membaca tabel draft, dan keduanya memanggil penilai yang
     * sama — bukan menyalin syaratnya, sebab syarat yang disalin akan
     * menyimpang pada salinan yang terlewat. Itu sudah dua kali terjadi di
     * modul ini.
     *
     * @throws \DomainException
     */
    private function pastikanBukanPublikBerkuis(int $idPaket): void
    {
        $paket = DB::table($this->table)->where('Id_SOP_Paket', $idPaket)
            ->first(['Mode_Distribusi']);
        if (! $paket) {
            return;
        }   // ketiadaannya diurus pemanggil

        $judulKuis = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Tipe_Item', SopTipeItem::KUIS)
            ->pluck('Judul_Item')
            ->all();

        if (SopGerbangPublikKuis::dilanggar($paket->Mode_Distribusi ?? null, $judulKuis)) {
            throw new \DomainException(SopGerbangPublikKuis::pesan($judulKuis));
        }
    }

    public function updateStatus(int $id, string $status, int $userId, ?string $ringkasanPerubahan = null): void
    {
        if ($status === SopStatus::PAKET_PUBLISHED) {
            $this->pastikanItemSiapTerbit($id);
            $this->pastikanBukanPublikBerkuis($id);
            app(SopPaketVersionService::class)->purgeUnreleasedDraftArtifacts($id);
        }

        $payload = [
            'Status' => $status,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ];
        if ($status === SopStatus::PAKET_PUBLISHED) {
            $payload['Tanggal_Publish'] = now();
        }
        DB::table($this->table)->where('Id_SOP_Paket', $id)->update($payload);

        if ($status === SopStatus::PAKET_PUBLISHED) {
            app(SopPaketVersionService::class)->release($id, 'PAKET_PUBLISH', $userId, null, $ringkasanPerubahan);
        }
    }

    /** Soft delete. */
    public function softDelete(int $id, int $userId): void
    {
        DB::table($this->table)->where('Id_SOP_Paket', $id)->update([
            'Flag_Aktif' => SopStatus::INAKTIF,
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);
    }

    /**
     * Kosongkan isi paket DRAFT — seluruh section & penempatan item dinonaktifkan.
     *
     * KENAPA "KOSONGKAN", BUKAN "KEMBALIKAN".
     * Paket terbit menyunting lewat salinan kerja, jadi membuangnya benar-benar
     * mengembalikan keadaan sebelumnya. Paket DRAFT tidak punya salinan itu —
     * suntingannya langsung menulis tabel yang sama. Tak ada keadaan "sebelum"
     * untuk dituju, dan paket DRAFT tak pernah bisa datang dari paket terbit
     * (tak ada jalan turun status). Jadi satu-satunya arti jujur dari "mulai
     * ulang" di sini adalah: isinya dikosongkan, identitas paketnya tetap.
     *
     * YANG TIDAK DISENTUH, dan ini yang paling penting: Dokumen SOP beserta
     * versinya. Dokumen milik bersama — satu dokumen bisa dipakai banyak paket,
     * dan yang mengelolanya Master Dokumen. Item di sini hanyalah PENEMPATAN
     * dokumen pada paket. Mengosongkan paket melepas penempatannya; dokumennya
     * tetap ada dan tetap bisa dipakai lagi.
     *
     * Ditulis sebagai dua UPDATE himpunan, bukan perulangan per baris: jumlah
     * kueri tak boleh tumbuh mengikuti besarnya paket.
     *
     * @return array{section:int,item:int} jumlah baris yang dinonaktifkan
     */
    public function kosongkan(int $id, int $userId): array
    {
        $stempel = ['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_By' => $userId, 'Updated_At' => now()];

        return DB::transaction(function () use ($id, $stempel) {
            /* Item lebih dulu. SopSectionService::delete() menolak section yang
               masih berisi item, dan urutan terbalik akan meninggalkan section
               hidup berisi item mati — struktur yang tak pernah bisa dibuat
               lewat layar. */
            $item = DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $id)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->update($stempel);

            $section = DB::table('N_SOP_Paket_Section')
                ->where('Id_SOP_Paket', $id)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->update($stempel);

            return ['section' => (int) $section, 'item' => (int) $item];
        });
    }
}
