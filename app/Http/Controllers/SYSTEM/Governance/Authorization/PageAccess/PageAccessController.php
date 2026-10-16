<?php

namespace App\Http\Controllers\SYSTEM\Governance\Authorization\PageAccess;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\PageAccess\PageAccessAudit;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PageAccessController extends Controller
{
    public function index(Request $request)
    {
        return inertia('SYSTEM/Governance/Authorization/PageAccess/PagesAccess', [
            // RBAC Fase 5: banner "Kini dikelola di Akses & Peran".
            'modulBaru' => app(ModeAkses::class)->namaModulBaru(),
            // Layar SYSTEM: tombol ke Akses & Peran SYSTEM hanya bagi System Admin.
            'tautanAksesPeran' => app(KeputusanAkses::class)->apakahSystemAdmin($request->user()) ? '/system/akses-peran' : null,
        ]);
    }

    /**
     * Mengambil data awal untuk halaman Page Access.
     * Termasuk daftar karyawan dan halaman yang dapat dikelola oleh admin.
     */
    public function getInitialData()
    {
        try {
            $admin = Auth::user();
            $adminId = $admin->Id_Users;

            // 1-2. Halaman yang boleh dikelola admin ini.
            $manageablePages = $this->halamanKelolaan((int) $adminId, ($admin->Is_System_Admin ?? null) === 'Y');

            $eligiblePages = collect($manageablePages)->pluck('id')->unique()->toArray();
            // dd($eligiblePages);
            // 3. Dapatkan daftar semua karyawan beserta akses yang sudah mereka miliki
            $employeesWithAccess = DB::table('Karyawan as k')
                ->join('KPI_Users as u', 'k.UserID_Web', '=', 'u.Id_Users')
                ->leftJoin('HRIS_Page_Access as pa', 'k.UserID_Web', '=', 'pa.UserID_Web')
                ->leftJoin('N_HRIS_Menus as m', 'pa.Jenis_page', '=', 'm.Jenis_page')
                ->join('View_Divisi_Sub_Divisi as vdsd', 'k.ID_Divisi_Sub_Divisi', '=', 'vdsd.ID_DIVISI_SUB_DIVISI')
                ->join(
                    'View_Golongan_Sub_Golongan_Level_Jabatan as vglj',
                    'k.Id_Level_Jabatan',
                    '=',
                    'vglj.ID_Level_Jabatan',
                )
                ->where('pa.Flag_Aktif', 'Y')
                ->where('k.Aktif', 'Y')
                // ->where(function($q) use ($eligiblePages) {
                //     $q->whereIn('m.id_menu', $eligiblePages)
                //     ->orWhereNull('m.id_menu');
                // })
                ->select(
                    'k.UserID_Web as id',
                    'k.Nama as nama',
                    'k.Kode_Karyawan as kode',
                    'vdsd.nama_divisi as divisi',
                    'vglj.nama_level as level',
                    'm.id_menu as page_id',
                    'm.Nama_Menu as page_nama',
                )
                ->get();
            // dd($employeesWithAccess->count());

            $groupedEmployees = $employeesWithAccess
                ->groupBy('id')
                ->map(function ($items) use ($eligiblePages) {
                    $employee = $items->first();

                    return [
                        'id' => $employee->id,
                        'nama' => $employee->nama,
                        'kode' => $employee->kode,
                        'divisi' => $employee->divisi,
                        'level' => $employee->level,

                        'page_access' => $items
                            ->whereNotNull('page_id')
                            ->filter(function ($page) use ($eligiblePages) {
                                return in_array($page->page_id, $eligiblePages);
                            })
                            ->map(function ($page) {
                                return [
                                    'id' => $page->page_id,
                                    'nama' => $page->page_nama,
                                ];
                            })
                            ->values()
                            ->all(),
                    ];
                })
                ->values();

            return ResponseHelper::success(
                [
                    'allPages' => $manageablePages,
                    'employeePageAccess' => $groupedEmployees,
                ],
                'Data awal berhasil dimuat.',
            );
        } catch (\Exception $e) {
            Log::error('Error getInitialData PageAccess: '.$e->getMessage());

            return ResponseHelper::error('Gagal memuat data awal.', 500);
        }
    }

    /**
     * Mencari karyawan untuk dropdown.
     */
    public function searchEmployees(Request $request)
    {
        $query = $request->input('query', '');

        $employees = DB::table('Karyawan')
            ->where('Aktif', 'Y')
            ->where(function ($q) use ($query) {
                $q->where('Nama', 'LIKE', "%{$query}%")->orWhere('Kode_Karyawan', 'LIKE', "%{$query}%");
            })
            ->select('UserID_Web as id', 'Nama as nama', 'Kode_Karyawan as kode')
            ->limit(20)
            ->get();

        return ResponseHelper::success($employees, 'Karyawan ditemukan.');
    }

    /**
     * Memberikan atau mencabut akses halaman.
     */
    public function updateAccess(Request $request)
    {
        $validated = $request->validate([
            'all' => 'boolean',
            'employeeIds' => ['nullable', 'array', 'required_unless:all,true'],
            'pageIds' => 'required|array',
            'action' => 'required|in:grant,revoke',
        ]);

        $admin = Auth::user();
        $adminId = (int) $admin->Id_Users;
        $systemAdmin = ($admin->Is_System_Admin ?? null) === 'Y';

        // Hanya halaman kelolaan admin ini (sama dengan yang tampil di layar).
        $kelolaan = $this->halamanKelolaan($adminId, $systemAdmin)->pluck('Jenis_page', 'id');
        $diminta = array_values(array_unique(array_map('intval', $validated['pageIds'])));
        $luar = array_diff($diminta, array_map('intval', $kelolaan->keys()->all()));
        if ($luar !== []) {
            return ResponseHelper::error('Anda tidak berwenang mengelola sebagian halaman yang dipilih.', 403);
        }

        // Halaman tata kelola akses hanya boleh diberikan/dicabut System Admin.
        if (! $systemAdmin && $kelolaan->only($diminta)->contains(fn ($p) => HibahTataKelola::apakahHalamanTataKelola((string) $p))) {
            return ResponseHelper::error('Halaman pengatur akses hanya dapat dikelola System Admin.', 403);
        }

        // Tidak boleh mengubah akses diri sendiri.
        if (! ($validated['all'] ?? false) && in_array($adminId, array_map('intval', $validated['employeeIds'] ?? []), true)) {
            return ResponseHelper::error('Anda tidak dapat mengubah akses diri sendiri.', 422);
        }

        DB::beginTransaction();
        try {
            $jenisHalaman = DB::table('N_HRIS_Menus')->whereIn('id_menu', $validated['pageIds'])
                ->pluck('Jenis_page')->unique()->values()->all();

            // Kandidat: karyawan aktif ber-user, dipilih eksplisit atau semua.
            $kandidat = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('UserID_Web')
                ->where('UserID_Web', '<>', $adminId)
                ->when(! ($validated['all'] ?? false), fn ($q) => $q->whereIn('UserID_Web', $validated['employeeIds'] ?? []))
                ->pluck('UserID_Web')->map(fn ($v) => (int) $v)->unique()->values()->all();

            // Target = kandidat yang benar-benar berubah, dilihat dari baris AKTIF.
            $sebelum = $this->aksesAktif($kandidat, $jenisHalaman);
            $target = array_values(array_filter($kandidat, fn ($id) => $validated['action'] === 'grant'
                ? count($sebelum[$id] ?? []) < count($jenisHalaman)
                : ! empty($sebelum[$id])));

            if ($target === []) {
                DB::rollBack();

                return ResponseHelper::error($validated['action'] === 'grant'
                    ? 'Seluruh karyawan terpilih sudah memiliki akses halaman ini.'
                    : 'Seluruh karyawan terpilih memang tidak memiliki akses halaman ini.', 422);
            }
            $validated['employeeIds'] = $target;
            $pageJenis = collect($jenisHalaman);
            // dd($validated['employeeIds']);
            /*
             * SATU PERJALANAN UNTUK SEMUA, bukan dua perjalanan per pasangan.
             *
             * Bentuk lama bersarang: untuk TIAP karyawan satu kueri identitas,
             * lalu untuk tiap (karyawan x halaman) satu exists() plus satu
             * update/insert. Biayanya N x (1 + 2M).
             *
             * Itu bukan angka teoretis. Diukur pada basis data ini: 958 karyawan
             * aktif dan 103 halaman di menu, jadi "beri akses ke semua" untuk
             * tiga halaman = 6.706 kueri, dan untuk sepuluh halaman = 20.118 --
             * SELURUHNYA di dalam satu transaksi.
             *
             * Yang membuatnya berbahaya bukan lamanya, melainkan APA yang
             * dikunci. HRIS_Page_Access dibaca pada hampir setiap permintaan
             * (11.642 eksekusi tercatat di dm_exec_query_stats), dan RCSI mati
             * di basis data ini -- jadi selama transaksi itu berjalan, pengguna
             * lain menunggu untuk sekadar tahu halaman apa yang boleh ia buka.
             *
             * Bentuk baru: satu kueri identitas, satu kueri pasangan-yang-sudah-
             * ada, UPDATE dikelompokkan per (ID_Divisi, ID_Level) -- terukur 27
             * kombinasi berbeda, berapa pun jumlah karyawannya -- dan satu
             * INSERT massal berpotongan.
             */
            $idKaryawan = array_values(array_unique($validated['employeeIds']));
            $jenisHalaman = $pageJenis->unique()->values()->all();
            $waktu = now();

            if ($validated['action'] === 'revoke') {
                /* Pencabutan tidak butuh identitas apa pun: satu UPDATE menutup
                   seluruh pasangan sekaligus. */
                foreach (array_chunk($idKaryawan, 500) as $potongan) {
                    DB::table('HRIS_Page_Access')
                        ->whereIn('UserID_Web', $potongan)
                        ->whereIn('Jenis_page', $jenisHalaman)
                        ->update(['Flag_Aktif' => 'T', 'Updated_At' => $waktu]);
                }
            } else {
                /* Identitas divisi & level SEMUA karyawan dalam satu perjalanan.
                   Kolomnya dipilih eksplisit: bentuk lama memakai first() tanpa
                   select sehingga menarik seluruh kolom dua view lebar, padahal
                   yang dipakai hanya dua. */
                $identitas = DB::table('Karyawan as a')
                    ->join('View_Divisi_Sub_Divisi as b', 'a.ID_Divisi_Sub_Divisi', '=', 'b.ID_DIVISI_SUB_DIVISI')
                    ->join(
                        'View_Golongan_Sub_Golongan_Level_Jabatan as c',
                        'a.ID_Level_Jabatan',
                        '=',
                        'c.ID_Level_Jabatan',
                    )
                    ->whereIn('a.UserID_Web', $idKaryawan)
                    ->select('a.UserID_Web', 'b.ID_Divisi', 'c.ID_Level')
                    ->get()
                    ->keyBy('UserID_Web');

                /* Pasangan yang SUDAH ada -- menggantikan seluruh exists(). */
                $sudahAda = [];
                foreach (array_chunk($idKaryawan, 500) as $potongan) {
                    DB::table('HRIS_Page_Access')
                        ->whereIn('UserID_Web', $potongan)
                        ->whereIn('Jenis_page', $jenisHalaman)
                        ->select('UserID_Web', 'Jenis_page')
                        ->get()
                        ->each(function ($baris) use (&$sudahAda) {
                            $sudahAda[$baris->UserID_Web.'|'.$baris->Jenis_page] = true;
                        });
                }

                $grup = [];
                $barisBaru = [];
                foreach ($idKaryawan as $employeeId) {
                    $emp = $identitas[$employeeId] ?? null;
                    /* Karyawan yang identitasnya tak resolve tetap diproses dengan
                       nilai null -- sama persis dengan bentuk lama, yang membaca
                       properti dari first() yang null dan menuliskan null juga. */
                    $idDivisi = $emp->ID_Divisi ?? null;
                    $idLevel = $emp->ID_Level ?? null;

                    foreach ($jenisHalaman as $jenisPage) {
                        if (isset($sudahAda[$employeeId.'|'.$jenisPage])) {
                            $kunci = $idDivisi.'|'.$idLevel;
                            $grup[$kunci]['ID_Divisi'] = $idDivisi;
                            $grup[$kunci]['ID_Level'] = $idLevel;
                            $grup[$kunci]['ids'][$employeeId] = $employeeId;
                        } else {
                            $barisBaru[] = [
                                'UserID_Web' => $employeeId,
                                'Jenis_page' => $jenisPage,
                                'ID_Divisi' => $idDivisi,
                                'ID_Level' => $idLevel,
                                'Kode_Perusahaan' => '001',
                                'Created_At' => $waktu,
                                'Updated_At' => $waktu,
                                'Flag_Aktif' => 'Y',
                            ];
                        }
                    }
                }

                /* Satu UPDATE per kombinasi (divisi, level). Karyawan sekelompok
                   berbagi nilai yang sama, jadi menyaring lewat whereIn atas
                   SELURUH jenis halaman aman: pasangan yang belum ada memang
                   tidak cocok dengan baris mana pun. */
                foreach ($grup as $g) {
                    foreach (array_chunk(array_values($g['ids']), 500) as $potongan) {
                        DB::table('HRIS_Page_Access')
                            ->whereIn('UserID_Web', $potongan)
                            ->whereIn('Jenis_page', $jenisHalaman)
                            ->update([
                                'ID_Divisi' => $g['ID_Divisi'],
                                'ID_Level' => $g['ID_Level'],
                                'Kode_Perusahaan' => '001',
                                'Updated_At' => $waktu,
                                'Flag_Aktif' => 'Y',
                                // Beri ulang dari layar lama = tanpa batas (review Fase 3b I3).
                            ] + (\App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac::punya('kedaluwarsa_grant') ? ['Berlaku_Sampai' => null] : []));
                    }
                }

                /* Ukuran potongan dihitung DARI barisnya, bukan dari konstanta.
                   Konstanta 250 memang benar untuk delapan kolom (2.000 parameter),
                   tetapi ia benar hanya selama jumlah kolomnya tidak berubah —
                   satu kolom tambahan menjadikannya 2.250 dan menembus batas. */
                PenyisipanBerbatasParameter::sisipkan('HRIS_Page_Access', $barisBaru);
            }

            $sesudah = $this->aksesAktif($validated['employeeIds'], $jenisHalaman);
            app(PageAccessAudit::class)->catat(
                Auth::user(),
                $validated['action'],
                $jenisHalaman,
                array_intersect_key($sebelum, array_flip($validated['employeeIds'])) + array_fill_keys($validated['employeeIds'], []),
                $sesudah + array_fill_keys($validated['employeeIds'], []),
            );
            app(AuditAkses::class)->catat(
                'GRANT',
                implode(',', $jenisHalaman),
                $validated['action'],
                ['jumlah_karyawan' => count($validated['employeeIds'])],
                ['halaman' => $jenisHalaman, 'karyawan' => array_values($validated['employeeIds'])],
            );

            DB::commit();

            return ResponseHelper::success(null, 'Akses berhasil diperbarui.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updateAccess PageAccess: '.$e->getMessage());

            return ResponseHelper::error('Gagal memperbarui akses.', 500);
        } catch (\Throwable $e) {
            /* Jaring \Error: TypeError dan kerabatnya bukan turunan \Exception,
               jadi tanpa klausa ini transaksi menggantung terbuka. Dilempar ulang
               supaya handler global tetap mencatat stack trace-nya dan responsnya
               tak berubah sedikit pun. */
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Halaman AKTIF per user di antara $jenisHalaman, berpotongan 500 user
     * (batas 2.100 parameter SQL Server).
     *
     * @return array<int, list<string>>
     */
    private function aksesAktif(array $idUser, array $jenisHalaman): array
    {
        $hasil = [];
        foreach (array_chunk($idUser, 500) as $potongan) {
            DB::table('HRIS_Page_Access')
                ->whereIn('UserID_Web', $potongan)
                ->whereIn('Jenis_page', $jenisHalaman)
                ->where('Flag_Aktif', 'Y')
                ->get(['UserID_Web', 'Jenis_page'])
                ->each(function ($b) use (&$hasil) {
                    $hasil[(int) $b->UserID_Web][] = (string) $b->Jenis_page;
                });
        }

        return array_map(fn ($l) => array_values(array_unique($l)), $hasil);
    }

    /** Halaman yang boleh dikelola admin ini (lewat N_HRIS_Role_Konten_Access). */
    private function halamanKelolaan(int $adminId, bool $systemAdmin = false): \Illuminate\Support\Collection
    {
        // System Admin mengelola seluruh menu, tanpa bergantung pada baris
        // kelolaan (N_HRIS_Role_Konten_Access) — di dev ia tak punya satu pun.
        if ($systemAdmin) {
            return DB::table('N_HRIS_Menus as m')
                ->select('m.id_menu as id', 'm.Nama_Menu as nama', 'm.Header_Label as module', 'm.Jenis_page')
                ->distinct()
                ->orderBy('m.Header_Label')
                ->orderBy('m.Nama_Menu')
                ->get();
        }

        $grup = DB::table('HRIS_Page_Access as pa')
            ->join('N_HRIS_Role_Konten_Access as rka', 'pa.Id_Page_Access', '=', 'rka.Id_Page_Access')
            ->where('pa.Flag_Aktif', 'Y')
            ->where('rka.Flag_Aktif', 'Y')
            ->where('pa.UserID_Web', $adminId)
            ->whereNotNull('rka.Id_Grup_Fitur')
            ->pluck('rka.Id_Grup_Fitur')
            ->unique();

        return DB::table('N_HRIS_Grup_Fitur_Map as gfm')
            ->join('N_HRIS_Menus as m', 'gfm.Jenis_page', '=', 'm.Jenis_page')
            ->whereIn('gfm.Id_Grup_Fitur', $grup)
            ->select('m.id_menu as id', 'm.Nama_Menu as nama', 'm.Header_Label as module', 'm.Jenis_page')
            ->distinct()
            ->orderBy('m.Header_Label')
            ->orderBy('m.Nama_Menu')
            ->get();
    }

    /*
     * generateNoTransaksi() DIBUANG di sini.
     *
     * Ia generator KETIGA untuk HRIS_Transaksi_Log_Admin, dan yang paling
     * berbahaya dari ketiganya: tanpa lock apa pun, dan urutannya dibaca lewat
     * orderByDesc(RIGHT(No_Transaksi, 4)) yang non-sargable. Dua penulisan
     * bersamaan mendapat nomor yang sama.
     *
     * Ia tidak dipanggil satu kali pun — tetapi kode mati yang tampak siap pakai
     * adalah ranjau: pemakai pertamanya tidak akan curiga. Penomoran tabel ini
     * hanya lewat AdminLogNumberService (counter atomik per-prefix).
     */
}
