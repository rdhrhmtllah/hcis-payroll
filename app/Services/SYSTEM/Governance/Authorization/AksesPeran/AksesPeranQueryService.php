<?php

namespace App\Services\SYSTEM\Governance\Authorization\AksesPeran;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Akses\AksesDitolak;
use App\Services\SYSTEM\Governance\Authorization\Akses\AturanDelegasi;
use App\Services\SYSTEM\Governance\Authorization\Akses\AuditAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use App\Services\SYSTEM\Governance\Authorization\Akses\TingkatPeran;
use App\Services\SYSTEM\Governance\Authorization\Scope\CakupanKaryawan;
use App\Services\SYSTEM\Governance\Authorization\Shared\HibahTataKelola;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Data baca-saja layar "Akses & Peran" (RBAC Fase 2b).
 *
 * $modul = daftar Id_Grup_Fitur yang dikelola peminta; null = semua (System
 * Admin). Setiap kueri menyaringnya di sini, bukan di layar — admin KPI tidak
 * boleh bisa membaca peran ESS hanya dengan menebak id.
 */
class AksesPeranQueryService
{
    public function __construct(
        private KeputusanAkses $keputusan,
        private AturanDelegasi $aturan,
        private CakupanKaryawan $cakupan,
        private ModeAkses $mode,
    ) {}

    /** Jalankan pemeriksaan AturanDelegasi sebagai bendera; null = boleh, string = alasan tolak. */
    private function alasanTolak(callable $periksa): ?string
    {
        try {
            $periksa();

            return null;
        } catch (AksesDitolak $e) {
            return $e->getMessage();
        }
    }

    private function saringModul(Builder $q, ?array $modul, string $kolom): Builder
    {
        return $modul === null ? $q : $q->whereIn($kolom, $modul === [] ? [-1] : $modul);
    }

    public function ringkasan(?array $modul): array
    {
        $grup = $this->saringModul(DB::table('N_HRIS_Master_Grup_Fitur as g'), $modul, 'g.Id_Grup_Fitur')
            ->where('g.Flag_Aktif', 'Y')->orderBy('g.Sort_Order')
            ->get(['g.Id_Grup_Fitur', 'g.Kode_Grup_Fitur', 'g.Nama_Grup_Fitur']);

        $peran = $this->saringModul(DB::table('N_HRIS_Role_Functional as rf'), $modul, 'rf.Id_Grup_Fitur')
            ->where('rf.Flag_Aktif', 'Y');
        $penugasan = $this->saringModul(
            DB::table('N_HRIS_Role_User as ru')->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                ->where('ru.Flag_Aktif', 'Y')->where('rf.Flag_Aktif', 'Y'),
            $modul,
            'rf.Id_Grup_Fitur',
        );
        $adaJenis = SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role');

        $perModul = $grup->map(function ($g) use ($peran, $penugasan, $adaJenis) {
            return [
                'id' => (int) $g->Id_Grup_Fitur,
                'kode' => $g->Kode_Grup_Fitur,
                'nama' => $g->Nama_Grup_Fitur,
                'jumlah_peran' => (clone $peran)->where('rf.Id_Grup_Fitur', $g->Id_Grup_Fitur)->count(),
                'jumlah_penugasan' => (clone $penugasan)->where('rf.Id_Grup_Fitur', $g->Id_Grup_Fitur)->count(),
                'jumlah_admin' => $adaJenis
                    ? (clone $penugasan)->where('rf.Id_Grup_Fitur', $g->Id_Grup_Fitur)->where('rf.Jenis_Role', 'ADMIN')->count()
                    : 0,
            ];
        })->values()->all();

        $jenis = $adaJenis
            ? (clone $peran)->selectRaw('rf.Jenis_Role as jenis, COUNT(*) as n')->groupBy('rf.Jenis_Role')
                ->pluck('n', 'jenis')->map(fn ($v) => (int) $v)->all()
            : [];

        $batas = now()->addDays(30);
        $segera = SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai')
            ? (clone $penugasan)->whereBetween('ru.Berlaku_Sampai', [now(), $batas])->count()
            : 0;

        return [
            'modul' => $perModul,
            'jenis_role' => $jenis,
            'penugasan_segera_kedaluwarsa' => $segera,
            'audit_7_hari' => $this->kueriAudit($modul)?->where('a.Waktu', '>=', now()->subDays(7))->count() ?? 0,
            'perlu_perhatian' => $this->perluPerhatian($peran, $penugasan, $adaJenis) + ['peran_kembar' => $this->kelompokKembar($modul)],
            'statistik' => $this->statistik($modul, $penugasan, $adaJenis),
        ];
    }

    /**
     * Angka dasbor Ringkasan: sebaran penugasan per tingkat, peran terbanyak pemegangnya,
     * sebaran masa berlaku, serta tren & jenis perubahan 30 hari terakhir.
     */
    private function statistik(?array $modul, Builder $penugasan, bool $adaJenis): array
    {
        $tingkat = SkemaRbac::punya('tingkat')
            ? ($adaJenis ? "COALESCE(rf.Kode_Tingkat, CASE WHEN rf.Jenis_Role = 'ADMIN' THEN 'ADMIN' ELSE 'PENGGUNA' END)" : "COALESCE(rf.Kode_Tingkat, 'PENGGUNA')")
            : ($adaJenis ? "CASE WHEN rf.Jenis_Role = 'ADMIN' THEN 'ADMIN' ELSE 'PENGGUNA' END" : "'PENGGUNA'");
        $perTingkat = (clone $penugasan)->selectRaw("{$tingkat} as t, COUNT(*) as n")->groupBy(DB::raw($tingkat))
            ->pluck('n', 't')->map(fn ($v) => (int) $v)->all();

        $teratas = (clone $penugasan)->selectRaw('rf.Role_Code as kode, rf.Role_Name as nama, COUNT(DISTINCT ru.UserId_Web) as n')
            ->groupBy('rf.Role_Code', 'rf.Role_Name')->orderByDesc('n')->orderBy('rf.Role_Name')->limit(8)->get()
            ->map(fn ($r) => ['kode' => $r->kode, 'nama' => $r->nama, 'jumlah' => (int) $r->n])->all();

        $masa = ['tanpa_batas' => 0, 'lebih_90' => 0, 'hari_31_90' => 0, 'maks_30' => 0];
        if (SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai')) {
            $h30 = now()->addDays(30);
            $h90 = now()->addDays(90);
            $masa = [
                'tanpa_batas' => (clone $penugasan)->whereNull('ru.Berlaku_Sampai')->count(),
                'lebih_90' => (clone $penugasan)->where('ru.Berlaku_Sampai', '>', $h90)->count(),
                'hari_31_90' => (clone $penugasan)->where('ru.Berlaku_Sampai', '>', $h30)->where('ru.Berlaku_Sampai', '<=', $h90)->count(),
                'maks_30' => (clone $penugasan)->where('ru.Berlaku_Sampai', '<=', $h30)->count(),
            ];
        }

        $mulai = now()->subDays(29)->startOfDay();
        $audit = $this->kueriAudit($modul)?->where('a.Waktu', '>=', $mulai);
        $perHari = $audit ? (clone $audit)->selectRaw('CAST(a.Waktu AS date) as d, COUNT(*) as n')
            ->groupBy(DB::raw('CAST(a.Waktu AS date)'))->pluck('n', 'd')
            ->mapWithKeys(fn ($n, $d) => [substr((string) $d, 0, 10) => (int) $n]) : collect();
        $tren = collect(range(0, 29))->map(function ($i) use ($mulai, $perHari) {
            $d = $mulai->copy()->addDays($i)->toDateString();

            return ['tanggal' => $d, 'jumlah' => $perHari[$d] ?? 0];
        })->all();

        return [
            'pemegang_unik' => (clone $penugasan)->distinct()->count('ru.UserId_Web'),
            'per_tingkat' => $perTingkat,
            'peran_teratas' => $teratas,
            'masa_berlaku' => $masa,
            'tren_audit' => $tren,
            'aksi_audit' => $audit ? (clone $audit)->selectRaw('a.Aksi as k, COUNT(*) as n')->groupBy('a.Aksi')
                ->pluck('n', 'k')->map(fn ($v) => (int) $v)->all() : [],
        ];
    }

    /**
     * Daftar yang bisa langsung ditindaklanjuti di Ringkasan (maks. 10 per jenis):
     * penugasan habis ≤ 30 hari, peran tanpa pemegang, admin tanpa tanggung jawab Akses.
     */
    private function perluPerhatian(Builder $peran, Builder $penugasan, bool $adaJenis): array
    {
        $orang = fn (Builder $q) => $q->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')->limit(10)
            ->get(['ru.Id', 'ru.UserId_Web', 'k.Nama', 'rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name', ...(SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai') ? ['ru.Berlaku_Sampai'] : [])])
            ->map(fn ($r) => ['id_penugasan' => (int) $r->Id, 'id_user' => (int) $r->UserId_Web, 'nama' => $r->Nama, 'id_role' => (int) $r->Id_Role,
                'kode' => $r->Role_Code, 'nama_peran' => $r->Role_Name, 'berlaku_sampai' => $r->Berlaku_Sampai ?? null])->values()->all();

        return [
            'segera_kedaluwarsa' => SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai')
                ? $orang((clone $penugasan)->whereBetween('ru.Berlaku_Sampai', [now(), now()->addDays(30)])->orderBy('ru.Berlaku_Sampai'))
                : [],
            'peran_tanpa_pemegang' => (clone $peran)
                ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User as x')->whereColumn('x.Id_Role', 'rf.Id_Role')->where('x.Flag_Aktif', 'Y'))
                ->orderBy('rf.Role_Name')->limit(10)->get(['rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name'])
                ->map(fn ($r) => ['id' => (int) $r->Id_Role, 'kode' => $r->Role_Code, 'nama' => $r->Role_Name])->values()->all(),
            'admin_tanpa_akses' => $adaJenis && SkemaTersedia::tabel('N_HRIS_Role_User_Tanggung_Jawab')
                ? $orang((clone $penugasan)->where('rf.Jenis_Role', 'ADMIN')
                    ->whereNotExists(fn ($q) => $q->from('N_HRIS_Role_User_Tanggung_Jawab as tj')->whereColumn('tj.Id_Role_User', 'ru.Id')
                        ->where('tj.Kode', 'AKSES')->where('tj.Flag_Aktif', 'Y'))->orderBy('k.Nama'))
                : [],
        ];
    }

    public function daftarPeran(?array $modul): array
    {
        $daftar = $this->daftarPeranDasar($modul);
        // Peran "kembar": hak (halaman × aksi) persis sama dengan peran lain — kandidat digabung.
        // Pool approval tak dibandingkan: identitasnya alur/matriks (mis. N_VISITOR_Matriks_Tamu,
        // Group_Reference_Id approval), bukan hak halamannya.
        $sidik = $this->sidikHak(array_column(array_filter($daftar, fn ($p) => $p['jenis'] !== 'POOL_APPROVAL' && $p['tingkat'] !== TingkatPeran::SUPER_ADMIN), 'id'));
        $perSidik = [];
        foreach ($daftar as $p) {
            if (isset($sidik[$p['id']])) {
                $perSidik[$p['tingkat'].'|'.$sidik[$p['id']]][] = ['id' => $p['id'], 'kode' => $p['kode'], 'nama' => $p['nama']];
            }
        }

        // Dibandingkan sesama tingkat: Super Admin & Admin wajar berhak halaman sama (bedanya wewenang).
        return array_map(fn ($p) => $p + ['kembar' => isset($sidik[$p['id']])
            ? array_values(array_filter($perSidik[$p['tingkat'].'|'.$sidik[$p['id']]], fn ($k) => $k['id'] !== $p['id'])) : []], $daftar);
    }

    /** Sidik hak per peran (md5 dari halaman#aksi terurut); peran tanpa hak tidak disertakan. */
    private function sidikHak(array $ids): array
    {
        if ($ids === [] || ! SkemaTersedia::tabel('N_HRIS_Role_Hak')) {
            return [];
        }

        return DB::table('N_HRIS_Role_Hak')->whereIn('Id_Role', $ids)->where('Flag_Aktif', 'Y')
            ->get(['Id_Role', 'Jenis_page', 'Id_Aksi'])->groupBy('Id_Role')
            ->map(fn ($b) => md5($b->map(fn ($h) => strtolower(trim((string) $h->Jenis_page)).'#'.$h->Id_Aksi)->unique()->sort()->implode(',')))
            ->mapWithKeys(fn ($v, $k) => [(int) $k => $v])->all();
    }

    /** Kelompok peran berhak identik (untuk Ringkasan). */
    public function kelompokKembar(?array $modul): array
    {
        return collect($this->daftarPeran($modul))->filter(fn ($p) => $p['kembar'] !== [])
            ->groupBy(fn ($p) => collect([$p['id'], ...array_column($p['kembar'], 'id')])->sort()->implode('-'))
            ->map(fn ($g) => ['halaman' => $g->first()['halaman'], 'peran' => $g->map(fn ($p) => ['id' => $p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'], 'pemegang' => $p['pemegang']])->values()->all()])
            ->sortByDesc(fn ($k) => count($k['peran']))->values()->all();
    }

    /**
     * Wewenang bisnis kontekstual untuk peran (menjelaskan tugas nyata, alur persetujuan,
     * kuasa administratif, atau hak menu operasional secara gamblang bagi Dept Head).
     */
    /**
     * Daftar kemampuan fitur bisnis yang dimiliki oleh peran (DDL-2026-194).
     *
     * @return list<array{kode_fitur: string, nama_fitur: string, kode_kemampuan: string, nama_kemampuan: string, jenis_aksi: string, cakupan_tersedia: string, flag_wajib_approval: string}>
     */
    public function kemampuanPeran(int $idRole): array
    {
        if (! SkemaTersedia::tabel('N_HRIS_Role_Kemampuan')) {
            return [];
        }

        return DB::table('N_HRIS_Role_Kemampuan as rk')
            ->join('N_HRIS_Fitur_Kemampuan as fk', 'fk.Kode_Kemampuan', '=', 'rk.Kode_Kemampuan')
            ->join('N_HRIS_Fitur as f', 'f.Kode_Fitur', '=', 'fk.Kode_Fitur')
            ->where('rk.Id_Role', $idRole)
            ->where('rk.Flag_Aktif', 'Y')
            ->where('fk.Flag_Aktif', 'Y')
            ->where('f.Flag_Aktif', 'Y')
            ->select(
                'f.Kode_Fitur as kode_fitur',
                'f.Nama_Fitur as nama_fitur',
                'fk.Kode_Kemampuan as kode_kemampuan',
                'fk.Nama_Kemampuan as nama_kemampuan',
                'fk.Jenis_Aksi as jenis_aksi',
                'fk.Cakupan_Tersedia as cakupan_tersedia',
                'fk.Flag_Wajib_Approval as flag_wajib_approval'
            )
            ->orderBy('f.Kode_Fitur')
            ->orderBy('fk.Sort_Order')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function wewenangBisnis(int $idRole, string $kodeRole, string $jenisRole, string $tingkat, ?int $idGrup): array
    {
        $modulKode = $idGrup !== null ? DB::table('N_HRIS_Master_Grup_Fitur')->where('Id_Grup_Fitur', $idGrup)->value('Kode_Grup_Fitur') : null;

        if ($jenisRole === 'POOL_APPROVAL') {
            // Label bersih untuk alur dan jenis tamu
            $namaJalurMap = [
                'PERMIT' => 'Izin Masuk Kendaraan (Permit)',
                'TAMU' => 'Izin Masuk Tamu Umum & Vendor',
                'TAMU_B2B' => 'Kunjungan Mitra Bisnis (B2B)',
                'KPI_PERF_STD' => 'Penilaian Kinerja Tahunan (KPI)',
            ];
            $labelKategoriMap = [
                'REGULAR' => 'Tamu Reguler',
                'VENDOR' => 'Vendor / Kontraktor',
                'B2B_PROCUREMENT' => 'Tamu Pengadaan (Procurement)',
                'B2B_LOGISTIC' => 'Tamu Logistik (Pengiriman)',
            ];

            // Ambil konfigurasi alur persetujuan terdaftar dari N_HRIS_Approval_Config (Menu & Approval Matrix)
            $configs = DB::table('N_HRIS_Approval_Config_Detail as acd')
                ->join('N_HRIS_Approval_Config as ac', 'ac.Id', '=', 'acd.Id_Approval_Config')
                ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'acd.Id_Jenis_Approval')
                ->where('ja.Source_Reference', $kodeRole)
                ->where('ac.Flag_Aktif', 'Y')
                ->where('acd.Flag_Aktif', 'Y')
                ->select('ac.Id as config_id', 'ac.Kode_Config', 'ac.Nama_Config', 'acd.Approval_Order')
                ->get();

            $jalurList = [];
            foreach ($configs as $c) {
                $allSteps = DB::table('N_HRIS_Approval_Config_Detail as acd')
                    ->join('N_HRIS_Jenis_Approval as ja', 'ja.Id_Jenis_Approval', '=', 'acd.Id_Jenis_Approval')
                    ->where('acd.Id_Approval_Config', $c->config_id)
                    ->where('acd.Flag_Aktif', 'Y')
                    ->orderBy('acd.Approval_Order')
                    ->select('acd.Approval_Order', 'ja.Nama_Approval', 'ja.Source_Reference')
                    ->get()
                    ->map(fn ($s) => [
                        'urutan' => (int) $s->Approval_Order,
                        'nama' => $s->Nama_Approval,
                        'adalah_peran_ini' => $s->Source_Reference === $kodeRole,
                    ])
                    ->values()
                    ->all();

                $jalurList[] = [
                    'nama_jalur' => $namaJalurMap[$c->Kode_Config] ?? $c->Nama_Config,
                    'kode_jalur' => $c->Kode_Config,
                    'tahap_ke' => (int) $c->Approval_Order,
                    'total_tahap' => count($allSteps),
                    'langkah' => $allSteps,
                ];
            }

            if (empty($jalurList)) {
                $jalurFallback = match ($kodeRole) {
                    'ADMIN_HC_CUTI_VERIFIKATOR' => [
                        [
                            'nama_jalur' => 'Pengajuan Cuti & Izin Karyawan',
                            'kode_jalur' => 'CUTI_HC_OPS',
                            'tahap_ke' => 2,
                            'total_tahap' => 2,
                            'langkah' => [
                                ['urutan' => 1, 'nama' => 'Atasan Langsung', 'adalah_peran_ini' => false],
                                ['urutan' => 2, 'nama' => 'Verifikator HC Cuti', 'adalah_peran_ini' => true],
                            ],
                        ],
                    ],
                    'SHE_ADMIN' => [
                        [
                            'nama_jalur' => 'Izin Masuk K3 & Area Terbatas (SHE)',
                            'kode_jalur' => 'SHE_PERMIT_FLOW',
                            'tahap_ke' => 1,
                            'total_tahap' => 1,
                            'langkah' => [
                                ['urutan' => 1, 'nama' => 'Admin SHE', 'adalah_peran_ini' => true],
                            ],
                        ],
                    ],
                    default => [],
                };
                if (! empty($jalurFallback)) {
                    $jalurList = $jalurFallback;
                }
            }

            $tamu = DB::table('N_VISITOR_Matriks_Tamu')->where('Role_Id', $idRole)->where('Flag_Aktif', 'Y')
                ->select('Kategori_Tamu', 'Flag_Lihat', 'Flag_Ajukan')->get()
                ->map(fn ($t) => [
                    'kategori' => $t->Kategori_Tamu,
                    'kategori_label' => $labelKategoriMap[$t->Kategori_Tamu] ?? $t->Kategori_Tamu,
                    'bisa_lihat' => $t->Flag_Lihat === 'Y',
                    'bisa_ajukan' => $t->Flag_Ajukan === 'Y',
                    'aksi_label' => $t->Flag_Ajukan === 'Y' ? 'Lihat & Ajukan' : 'Lihat Jadwal',
                    'label' => ($labelKategoriMap[$t->Kategori_Tamu] ?? $t->Kategori_Tamu).' ('.($t->Flag_Ajukan === 'Y' ? 'Lihat & Ajukan' : 'Lihat Jadwal').')',
                ])
                ->all();

            $isDeptTamu = str_starts_with($kodeRole, 'TAMU_DEPT_');
            $deptNama = match ($kodeRole) {
                'TAMU_DEPT_GA' => 'General Affair (GA)',
                'TAMU_DEPT_LOGISTIC' => 'Logistik (Gudang Bahan)',
                'TAMU_DEPT_PROCUREMENT' => 'Pengadaan (Procurement)',
                'TAMU_DEPT_QC_FG' => 'QC Finish Goods',
                'TAMU_DEPT_QC_INC' => 'QC Incoming',
                'TAMU_DEPT_WAREHOUSE' => 'Gudang (Warehouse)',
                default => 'Unit Terkait',
            };

            $judul = $isDeptTamu
                ? 'PIC Penerima Tamu: '.$deptNama
                : match ($kodeRole) {
                    'ADMIN_PERMIT' => 'Verifikator Permit Kendaraan',
                    'ADMIN_TAMU' => 'Verifikator Tamu Umum & Vendor',
                    'ADMIN_TAMU_B2B' => 'Verifikator Tamu Mitra B2B',
                    'ADMIN_HC_CUTI_VERIFIKATOR' => 'Verifikator Cuti & Izin Karyawan',
                    'ROLE_KPI_VERIF' => 'Verifikator Penilaian Kinerja KPI',
                    'SHE_ADMIN' => 'Verifikator Izin K3 / SHE',
                    default => 'Wewenang Alur Persetujuan',
                };

            $deskripsi = match ($kodeRole) {
                'ADMIN_HC_CUTI_VERIFIKATOR' => 'Memvalidasi dan menyetujui formulir cuti serta izin karyawan.',
                'ROLE_KPI_VERIF' => 'Memvalidasi evaluasi capaian target penilaian kinerja tahunan.',
                'ADMIN_PERMIT' => 'Memvalidasi dan menerbitkan permit masuk kendaraan proyek dan operasional.',
                'ADMIN_TAMU' => 'Memvalidasi dan memberikan izin masuk tamu umum serta vendor di pos keamanan.',
                'ADMIN_TAMU_B2B' => 'Memvalidasi permohonan kunjungan tamu rekanan bisnis (B2B).',
                'SHE_ADMIN' => 'Memvalidasi kepatuhan keselamatan kerja (K3) untuk izin masuk area khusus.',
                default => ($isDeptTamu
                    ? 'Melihat jadwal kedatangan tamu yang berkunjung ke unit '.$deptNama.'.'
                    : 'Memproses persetujuan dokumen transaksi di modul '.($modulKode ?: 'HCIS').'.'),
            };

            return [
                'tipe' => 'POOL_APPROVAL',
                'judul' => $judul,
                'deskripsi' => $deskripsi,
                'jalur_approval' => $jalurList,
                'kategori_tamu' => $tamu,
                'poin' => [], // Bersihkan poin-poin panjang agar tidak membingungkan
            ];
        }

        if ($tingkat === TingkatPeran::SUPER_ADMIN) {
            return [
                'tipe' => 'SUPER_ADMIN',
                'judul' => 'Wewenang Penuh Bidang (Dept Head)',
                'deskripsi' => 'Sebagai Kepala Bidang / Pemilik Modul '.($modulKode ?: 'HCIS').', peran ini memegang kendali tertinggi seluruh sistem operasional di bidangnya.',
                'alur_approval' => [],
                'kategori_tamu' => [],
                'poin' => [
                    'Akses penuh otomatis ke seluruh menu, fitur, dan data modul tanpa batasan matriks.',
                    'Berwenang mengangkat dan mencabut PIC Pengelola (Admin Modul).',
                    'Mengatur penugasan seluruh peran staf dan verifikator di modul ini.',
                    'Memantau seluruh jejak audit dan riwayat perubahan akses di divisinya.',
                ],
            ];
        }

        if ($tingkat === TingkatPeran::ADMIN || $jenisRole === 'ADMIN') {
            return [
                'tipe' => 'ADMIN',
                'judul' => 'Kuasa Pengelolaan & Delegasi Staf',
                'deskripsi' => 'Sebagai staf PIC Pengelola yang ditunjuk oleh Dept Head, peran ini membantu mengelola pembagian peran dan akses staf di modul '.($modulKode ?: 'HCIS').'.',
                'alur_approval' => [],
                'kategori_tamu' => [],
                'poin' => [
                    'Menugaskan peran pelaksana operasional dan verifikator alur approval kepada staf.',
                    'Membatasi wewenang staf berdasarkan unit kerja (Cakupan Divisi / Lokasi / PHL).',
                    'Memberikan akses langsung ke menu tertentu dengan batas waktu (Temporary Grant).',
                    'Mengoperasikan menu dan fitur administratif modul.',
                ],
            ];
        }

        $deskripsiAkses = match ($kodeRole) {
            'HC_OPS_ADMIN' => 'Staf operasional HC: mengelola transaksi kehadiran harian, jadwal shift, dan pembaruan data karyawan.',
            'GA_PHL_ADMIN' => 'Staf pengelola PHL: mengurus pencatatan absensi dan operasional tenaga kerja harian lepas.',
            'HCIS_LEADER' => 'Pimpinan Unit / Atasan: memantau data anggota tim dan memproses pengajuan bawahan langsung.',
            default => 'Staf Pelaksana: menggunakan fitur dan menu yang ditentukan untuk menyelesaikan tugas operasional harian.',
        };

        return [
            'tipe' => 'AKSES',
            'judul' => 'Hak Akses Menu & Fitur Operasional',
            'deskripsi' => $deskripsiAkses,
            'alur_approval' => [],
            'kategori_tamu' => [],
            'poin' => [
                'Membuka halaman dan menu modul yang terdaftar dalam paket hak akses.',
                'Melakukan aksi kerja (View, Create, Edit, Cetak) sesuai konfigurasi wewenang.',
                'Akses data dibatasi oleh unit kerja (cakupan) yang ditetapkan saat penugasan.',
            ],
        ];
    }

    /**
     * Isi beberapa peran (halaman × aksi) — ringan, untuk wizard penugasan.
     *
     * @return list<array{id: int, kode: string, nama: string, halaman: list<array>}>
     */
    public function isiPeran(array $ids, ?array $modul): array
    {
        $peran = $this->saringModul(DB::table('N_HRIS_Role_Functional as rf'), $modul, 'rf.Id_Grup_Fitur')
            ->whereIn('rf.Id_Role', $ids ?: [-1])
            ->get(['rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name', 'rf.Id_Grup_Fitur',
                ...(SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role') ? ['rf.Jenis_Role'] : []),
                ...(SkemaRbac::punya('tingkat') ? ['rf.Kode_Tingkat'] : [])]);
        $tingkat = app(TingkatPeran::class);
        $hak = SkemaTersedia::tabel('N_HRIS_Role_Hak')
            ? DB::table('N_HRIS_Role_Hak as h')
                ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
                ->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'h.Jenis_page')
                ->whereIn('h.Id_Role', $peran->pluck('Id_Role'))->where('h.Flag_Aktif', 'Y')
                ->orderBy('m.Header_Label')->orderBy('m.Nama_Menu')
                ->get(['h.Id_Role', 'h.Jenis_page', 'm.Nama_Menu', 'm.Header_Label', 'a.Nama_Aksi'])->groupBy('Id_Role')
            : collect();

        return $peran->map(function ($r) use ($tingkat, $hak) {
            $t = $tingkat->dariPeran($r);
            $wewenang = $this->wewenangBisnis((int) $r->Id_Role, $r->Role_Code, $r->Jenis_Role ?? 'AKSES', $t, $r->Id_Grup_Fitur !== null ? (int) $r->Id_Grup_Fitur : null);
            $isSuper = $t === TingkatPeran::SUPER_ADMIN && $r->Id_Grup_Fitur !== null;
            $halaman = $isSuper
                ? $this->halamanSuperAdmin((int) $r->Id_Grup_Fitur)
                : collect($hak->get($r->Id_Role, []))->groupBy(fn ($h) => strtolower(trim((string) $h->Jenis_page)))
                    ->map(fn ($b) => ['jenis_page' => $b->first()->Jenis_page, 'nama_menu' => $b->first()->Nama_Menu ?? $b->first()->Jenis_page,
                        'header' => $b->first()->Header_Label, 'aksi' => $b->pluck('Nama_Aksi')->unique()->values()->all()])
                    ->values()->all();

            return [
                'id' => (int) $r->Id_Role,
                'kode' => $r->Role_Code,
                'nama' => $r->Role_Name,
                'otomatis' => $isSuper,
                'wewenang_bisnis' => $wewenang,
                'daftar_kemampuan' => $this->kemampuanPeran((int) $r->Id_Role),
                'halaman' => $halaman,
            ];
        })->values()->all();
    }

    /** Halaman yang otomatis terbuka bagi Super Admin modul (tanpa SYSTEM_ONLY). */
    private function halamanSuperAdmin(int $modul): array
    {
        $khususSystem = $this->keputusan->halamanKhususSystemAdmin();

        return DB::table('N_HRIS_Grup_Fitur_Map as g')->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'g.Jenis_page')
            ->where('g.Id_Grup_Fitur', $modul)->where('g.Flag_Aktif', 'Y')
            ->orderBy('m.Header_Label')->orderBy('m.Nama_Menu')
            ->get(['g.Jenis_page', 'm.Nama_Menu', 'm.Header_Label'])
            ->unique(fn ($h) => HibahTataKelola::normalisasi((string) $h->Jenis_page))
            ->reject(fn ($h) => in_array(HibahTataKelola::normalisasi((string) $h->Jenis_page), $khususSystem, true))
            ->map(fn ($h) => ['jenis_page' => $h->Jenis_page, 'nama_menu' => $h->Nama_Menu ?? $h->Jenis_page,
                'header' => $h->Header_Label, 'aksi' => ['SEMUA AKSI']])
            ->values()->all();
    }

    /**
     * Peta balik: setiap halaman modul → peran yang membukanya, jumlah orang lewat
     * peran, dan jumlah akses langsung.
     */
    public function petaHalaman(?array $modul, string $cari = ''): array
    {
        if (! SkemaTersedia::tabel('N_HRIS_Role_Hak')) {
            return [];
        }
        $hak = $this->saringModul(DB::table('N_HRIS_Role_Hak as h')->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'h.Id_Role'), $modul, 'rf.Id_Grup_Fitur')
            ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
            ->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'h.Jenis_page')
            ->where('h.Flag_Aktif', 'Y')->where('rf.Flag_Aktif', 'Y')
            ->when($cari !== '', fn ($q) => $q->where(fn ($w) => $w->where('m.Nama_Menu', 'like', "%{$cari}%")->orWhere('h.Jenis_page', 'like', "%{$cari}%")))
            ->get(['h.Jenis_page', 'm.Nama_Menu', 'm.Header_Label', 'rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name', 'a.Nama_Aksi',
                ...(SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role') ? ['rf.Jenis_Role'] : [])]);
        $superRoles = collect($this->daftarPeranDasar($modul))->filter(fn ($p) => $p['tingkat'] === TingkatPeran::SUPER_ADMIN && $p['id_grup'] !== null);
        $idPeran = $hak->pluck('Id_Role')->concat($superRoles->pluck('id'))->unique()->values();
        $pemegang = SkemaRbac::saringPenugasanAktif(DB::table('N_HRIS_Role_User as ru')->whereIn('ru.Id_Role', $idPeran->all() ?: [-1])->where('ru.Flag_Aktif', 'Y'), 'ru.')
            ->get(['ru.Id_Role', 'ru.UserId_Web'])->groupBy('Id_Role')->map(fn ($b) => $b->pluck('UserId_Web')->unique()->values());
        $grant = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')->whereNotNull('UserID_Web')
            ->when(SkemaTersedia::kolom('HRIS_Page_Access', 'Berlaku_Sampai'), fn ($q) => $q->where(fn ($w) => $w->whereNull('Berlaku_Sampai')->orWhere('Berlaku_Sampai', '>', now())))
            ->selectRaw('LOWER(LTRIM(RTRIM(Jenis_page))) as p, COUNT(DISTINCT UserID_Web) as n')->groupBy(DB::raw('LOWER(LTRIM(RTRIM(Jenis_page)))'))->pluck('n', 'p');

        // Super Admin modul: otomatis membuka semua halaman modulnya (bukan dari matriks).
        $tingkat = app(TingkatPeran::class);
        $super = collect($this->daftarPeranDasar($modul))->filter(fn ($p) => $p['tingkat'] === TingkatPeran::SUPER_ADMIN && $p['id_grup'] !== null)
            ->groupBy(fn ($p) => (int) $p['id_grup']);
        $idSuper = $super->flatten(1)->pluck('id')->all();
        $khususSystem = $this->keputusan->halamanKhususSystemAdmin();
        $mode = app(ModeAkses::class);

        return $hak->groupBy(fn ($h) => strtolower(trim((string) $h->Jenis_page)))
            ->map(function ($b, $kunci) use ($pemegang, $grant, $super, $idSuper, $khususSystem, $mode) {
                $first = $b->first();
                $b = $b->reject(fn ($h) => in_array((int) $h->Id_Role, $idSuper, true));
                $otomatis = in_array(HibahTataKelola::normalisasi($kunci), $khususSystem, true) ? collect()
                    : collect($super->get($mode->modulHalaman($kunci), []))->map(fn ($p) => [
                        'id' => $p['id'], 'kode' => $p['kode'], 'nama' => $p['nama'], 'jenis' => 'ADMIN',
                        'tingkat' => 'SUPER_ADMIN',
                        'aksi' => ['SEMUA AKSI'], 'pemegang' => $p['pemegang'], 'otomatis' => true,
                    ]);
                $peran = $b->groupBy('Id_Role')->map(fn ($r) => [
                    'id' => (int) $r->first()->Id_Role, 'kode' => $r->first()->Role_Code, 'nama' => $r->first()->Role_Name,
                    'jenis' => $r->first()->Jenis_Role ?? 'AKSES',
                    'aksi' => $r->pluck('Nama_Aksi')->unique()->values()->all(),
                    'pemegang' => count($pemegang->get($r->first()->Id_Role, [])),
                    'otomatis' => false,
                ])->sortBy('nama')->values()->concat($otomatis)->values();

                return [
                    'jenis_page' => $first?->Jenis_page ?? $kunci,
                    'nama_menu' => $first?->Nama_Menu ?? $first?->Jenis_page ?? $kunci,
                    'header' => $first?->Header_Label,
                    'peran' => $peran->all(),
                    'orang' => $peran->flatMap(fn ($r) => $pemegang->get($r['id'], []))->unique()->count(),
                    'grant_langsung' => (int) ($grant[$kunci] ?? 0),
                ];
            })->sortBy([['header', 'asc'], ['nama_menu', 'asc']])->values()->all();
    }

    /**
     * Rincian pengguna yang memiliki akses ke suatu halaman (via peran dan via grant langsung).
     */
    public function orangHalaman(string $halaman, ?array $modul, ?User $peminta = null): ?array
    {
        $norm = HibahTataKelola::normalisasi($halaman);
        $menu = DB::table('N_HRIS_Menus')->whereRaw("LOWER(REPLACE(LTRIM(RTRIM(Jenis_page)), ' ', '')) = ?", [$norm])
            ->first(['Jenis_page', 'Nama_Menu', 'Header_Label']);
        if (! $menu) {
            return null;
        }

        $halamanModul = $this->halamanModul($modul);
        if ($halamanModul !== null && ! in_array($norm, $halamanModul, true)) {
            return null;
        }

        // 1. Pengguna via peran
        $ru = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Hak as h', 'h.Id_Role', '=', 'ru.Id_Role')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
            ->whereRaw("LOWER(REPLACE(LTRIM(RTRIM(h.Jenis_page)), ' ', '')) = ?", [$norm])
            ->where('ru.Flag_Aktif', 'Y')
            ->where('h.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->tap(fn ($q) => SkemaRbac::saringPenugasanAktif($q, 'ru.'))
            ->select('k.UserID_Web as id_user', 'k.Nama as nama', 'k.Kode_Karyawan as kode_karyawan', 'rf.Role_Name as nama_peran', 'rf.Role_Code as kode_peran', 'rf.Id_Role as id_role')
            ->get();

        // Sertakan pemegang Super Admin jika halaman milik modulnya
        $mode = app(ModeAkses::class);
        $modulId = $mode->modulHalaman($norm);
        $khususSystem = $this->keputusan->halamanKhususSystemAdmin();
        if ($modulId !== null && ! in_array($norm, $khususSystem, true)) {
            $superRoles = DB::table('N_HRIS_Role_Functional')->where('Id_Grup_Fitur', $modulId)->where('Flag_Aktif', 'Y')->get()
                ->filter(fn ($r) => app(TingkatPeran::class)->dariPeran($r) === TingkatPeran::SUPER_ADMIN);
            if ($superRoles->isNotEmpty()) {
                $superRu = DB::table('N_HRIS_Role_User as ru')
                    ->join('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
                    ->whereIn('ru.Id_Role', $superRoles->pluck('Id_Role'))
                    ->where('ru.Flag_Aktif', 'Y')
                    ->tap(fn ($q) => SkemaRbac::saringPenugasanAktif($q, 'ru.'))
                    ->select('k.UserID_Web as id_user', 'k.Nama as nama', 'k.Kode_Karyawan as kode_karyawan', 'ru.Id_Role as id_role')
                    ->get();
                foreach ($superRu as $s) {
                    $rObj = $superRoles->firstWhere('Id_Role', $s->id_role);
                    $ru->push((object) [
                        'id_user' => $s->id_user,
                        'nama' => $s->nama,
                        'kode_karyawan' => $s->kode_karyawan,
                        'nama_peran' => $rObj->Role_Name ?? 'Super Admin',
                        'kode_peran' => $rObj->Role_Code ?? 'SUPER_ADMIN',
                        'id_role' => $s->id_role,
                    ]);
                }
            }
        }

        $orangPeran = $ru->groupBy('id_user')->map(fn ($b) => [
            'id_user' => (int) $b->first()->id_user,
            'nama' => $b->first()->nama,
            'kode_karyawan' => $b->first()->kode_karyawan,
            'peran' => $b->map(fn ($x) => ['id' => (int) $x->id_role, 'kode' => $x->kode_peran, 'nama' => $x->nama_peran])->unique('id')->values()->all(),
        ])->sortBy('nama')->values()->all();

        // 2. Pengguna via grant langsung
        $adaKolom = SkemaTersedia::kolom('HRIS_Page_Access', 'Berlaku_Sampai');
        $grantQ = DB::table('HRIS_Page_Access as pa')
            ->join('Karyawan as k', 'k.UserID_Web', '=', 'pa.UserID_Web')
            ->whereRaw("LOWER(REPLACE(LTRIM(RTRIM(pa.Jenis_page)), ' ', '')) = ?", [$norm])
            ->where('pa.Flag_Aktif', 'Y');
        if ($adaKolom) {
            $grantQ->where(fn ($w) => $w->whereNull('pa.Berlaku_Sampai')->orWhere('pa.Berlaku_Sampai', '>', now()));
        }
        $grantRows = $grantQ->select('k.UserID_Web as id_user', 'k.Nama as nama', 'k.Kode_Karyawan as kode_karyawan', 'pa.Id_Page_Access', 'pa.Alasan as alasan', ...($adaKolom ? ['pa.Berlaku_Sampai as berlaku_sampai'] : []))->get();

        $orangGrant = $grantRows->map(fn ($g) => [
            'id_user' => (int) $g->id_user,
            'id_grant' => (int) ($g->Id_Page_Access ?? 0),
            'nama' => $g->nama,
            'kode_karyawan' => $g->kode_karyawan,
            'alasan' => $g->alasan,
            'berlaku_sampai' => $g->berlaku_sampai ?? null,
            'bisa_cabut' => $peminta !== null && (int) $peminta->Id_Users !== (int) $g->id_user
                && $this->alasanTolak(fn () => $this->aturan->periksaGrant($peminta, (string) $menu->Jenis_page, (int) $g->id_user, false)) === null,
        ])->sortBy('nama')->values()->all();

        return [
            'jenis_page' => $menu->Jenis_page,
            'nama_menu' => $menu->Nama_Menu ?? $menu->Jenis_page,
            'header' => $menu->Header_Label,
            'orang_peran' => $orangPeran,
            'orang_grant' => $orangGrant,
        ];
    }

    private function daftarPeranDasar(?array $modul): array
    {
        $adaJenis = SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role');
        $adaHak = SkemaTersedia::tabel('N_HRIS_Role_Hak');

        return $this->saringModul(DB::table('N_HRIS_Role_Functional as rf'), $modul, 'rf.Id_Grup_Fitur')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
            ->where('rf.Flag_Aktif', 'Y')
            ->orderBy('g.Sort_Order')->orderBy('rf.Role_Name')
            ->selectRaw('rf.Id_Role as id, rf.Role_Code as kode, rf.Role_Name as nama, rf.Description as deskripsi, g.Kode_Grup_Fitur as modul')
            ->selectRaw($adaJenis ? 'rf.Jenis_Role as jenis' : "'AKSES' as jenis")
            ->selectRaw("(SELECT COUNT(*) FROM N_HRIS_Role_User ru WHERE ru.Id_Role = rf.Id_Role AND ru.Flag_Aktif = 'Y') as pemegang")
            ->selectRaw($adaHak
                ? "(SELECT COUNT(DISTINCT h.Jenis_page) FROM N_HRIS_Role_Hak h WHERE h.Id_Role = rf.Id_Role AND h.Flag_Aktif = 'Y') as halaman"
                : '0 as halaman')
            ->selectRaw('rf.Id_Grup_Fitur as id_grup')
            ->selectRaw(SkemaRbac::punya('tingkat') ? 'rf.Kode_Tingkat as kode_tingkat' : 'NULL as kode_tingkat')
            ->get()
            ->map(function ($r) {
                $tingkat = app(TingkatPeran::class)->dariPeran((object) [
                    'Kode_Tingkat' => $r->kode_tingkat, 'Role_Code' => $r->kode, 'Jenis_Role' => $r->jenis, 'Id_Grup_Fitur' => $r->id_grup,
                ]);
                $isSuper = $tingkat === TingkatPeran::SUPER_ADMIN && $r->id_grup !== null;
                $halamanCount = $isSuper
                    ? max((int) $r->halaman, count($this->halamanSuperAdmin((int) $r->id_grup)))
                    : (int) $r->halaman;

                return array_merge((array) $r, [
                    'lama' => TingkatPeran::peranLama($r->kode) && $r->jenis === 'ADMIN',
                    'id' => (int) $r->id,
                    'pemegang' => (int) $r->pemegang,
                    'halaman' => $halamanCount,
                    'tingkat' => $tingkat,
                ]);
            })
            ->values()->all();
    }

    public function detailPeran(int $idRole, ?array $modul, ?User $peminta = null): ?array
    {
        $peran = collect($this->daftarPeran($modul))->firstWhere('id', $idRole);
        if (! $peran) {
            return null;
        }

        $kolomAksi = DB::table('N_HRIS_Klasifikasi_Aksi')->orderBy('Id_Klasifikasi_Actions')->pluck('Nama_Aksi')->all();
        $matriks = SkemaTersedia::tabel('N_HRIS_Role_Hak')
            ? DB::table('N_HRIS_Role_Hak as h')
                ->join('N_HRIS_Klasifikasi_Aksi as a', 'a.Id_Klasifikasi_Actions', '=', 'h.Id_Aksi')
                ->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'h.Jenis_page')
                ->where('h.Id_Role', $idRole)->where('h.Flag_Aktif', 'Y')
                ->orderBy('m.Header_Label')->orderBy('m.Nama_Menu')
                ->get(['h.Jenis_page', 'm.Nama_Menu', 'm.Header_Label', 'a.Nama_Aksi'])
                ->groupBy('Jenis_page')
                ->map(fn (Collection $b, $page) => [
                    'jenis_page' => $page,
                    'nama_menu' => $b->first()->Nama_Menu ?? $page,
                    'header' => $b->first()->Header_Label,
                    'aksi' => $b->pluck('Nama_Aksi')->unique()->values()->all(),
                ])->values()->all()
            : [];

        $hakLama = collect($matriks)->map(fn ($m) => ['jenis_page' => $m['jenis_page'], 'aksi' => $m['aksi']])->all();
        $terkunci = $peminta ? $this->alasanTolak(fn () => $this->aturan->periksaUbahHak($peminta, $idRole, $hakLama, $hakLama)) : 'Baca-saja.';
        // Super Admin modul: semua halaman modul × semua aksi, otomatis — matriks tak berlaku.
        $otomatis = $peran['tingkat'] === TingkatPeran::SUPER_ADMIN && $peran['id_grup'] !== null;
        if ($otomatis) {
            $matriks = array_map(fn ($h) => ['aksi' => $kolomAksi] + $h, $this->halamanSuperAdmin((int) $peran['id_grup']));
            $terkunci = 'Super Admin otomatis berhak penuh atas semua halaman modulnya; matriks tidak dipakai.';
        }

        // array_merge, bukan +: daftar pemegang menimpa angka 'pemegang' dari daftarPeran.
        $wewenangBisnis = $this->wewenangBisnis($idRole, $peran['kode'], $peran['jenis'], $peran['tingkat'], $peran['id_grup']);
        $daftarKemampuan = $this->kemampuanPeran($idRole);

        return array_merge($peran, [
            'otomatis' => $otomatis,
            'wewenang_bisnis' => $wewenangBisnis,
            'daftar_kemampuan' => $daftarKemampuan,
            'halaman' => $otomatis ? count($matriks) : $peran['halaman'],
            'bisa_ubah_hak' => $terkunci === null,
            'alasan_terkunci' => $terkunci,
            // Halaman yang boleh ditambahkan ke matriks: milik modul peran ini.
            'halaman_modul' => $terkunci === null ? $this->halamanModulPeran($idRole, $peminta) : [],
            'jumlah_pemegang' => $peran['pemegang'],
            'kolom_aksi' => $kolomAksi,
            'matriks' => $matriks,
            // Konkurensi optimistis: dikirim balik saat simpan matriks.
            'versi' => SkemaTersedia::tabel('N_HRIS_Role_Hak') ? app(PengelolaanAksesService::class)->versiHak($idRole) : null,
            'pemegang' => $this->penugasan(fn ($q) => $q->where('ru.Id_Role', $idRole)),
        ]);
    }

    private function halamanModulPeran(int $idRole, User $peminta): array
    {
        $grup = DB::table('N_HRIS_Role_Functional')->where('Id_Role', $idRole)->value('Id_Grup_Fitur');
        $sa = $this->keputusan->apakahSystemAdmin($peminta);

        return DB::table('N_HRIS_Menus')->orderBy('Header_Label')->orderBy('Nama_Menu')->get(['Jenis_page', 'Nama_Menu', 'Header_Label'])
            ->unique('Jenis_page')
            ->filter(fn ($m) => $this->mode->modulHalaman((string) $m->Jenis_page) === ($grup !== null ? (int) $grup : -1)
                && ($sa || ! HibahTataKelola::apakahHalamanTataKelola((string) $m->Jenis_page)))
            ->map(fn ($m) => ['jenis_page' => $m->Jenis_page, 'nama_menu' => $m->Nama_Menu, 'header' => $m->Header_Label])
            ->values()->all();
    }

    /** @param callable(Builder): Builder $saring */
    private function penugasan(callable $saring): array
    {
        $adaKedaluwarsa = SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai');
        $q = DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
            ->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'ru.UserId_Web')
            ->where('ru.Flag_Aktif', 'Y')->where('rf.Flag_Aktif', 'Y')
            ->orderBy('k.Nama')
            ->selectRaw('ru.Id as id_penugasan, ru.UserId_Web as id_user, k.Nama as nama, k.Kode_Karyawan as kode_karyawan, rf.Id_Role as id_role, rf.Role_Code as kode, rf.Role_Name as nama_peran, g.Kode_Grup_Fitur as modul, ru.Assigned_At as sejak')
            ->selectRaw($adaKedaluwarsa ? 'ru.Berlaku_Sampai as berlaku_sampai' : 'NULL as berlaku_sampai');
        $baris = $saring($q)->get();
        $ids = $baris->pluck('id_penugasan')->map(fn ($v) => (int) $v)->all();

        $cakupan = $ids && SkemaTersedia::tabel('N_HRIS_Role_User_Cakupan')
            ? DB::table('N_HRIS_Role_User_Cakupan')->whereIn('Id_Role_User', $ids)->where('Flag_Aktif', 'Y')
                ->get(['Id_Role_User', 'Jenis_Cakupan', 'Nilai'])->groupBy('Id_Role_User')
            : collect();
        $tj = $ids && SkemaTersedia::tabel('N_HRIS_Role_User_Tanggung_Jawab')
            ? DB::table('N_HRIS_Role_User_Tanggung_Jawab')->whereIn('Id_Role_User', $ids)->where('Flag_Aktif', 'Y')
                ->get(['Id_Role_User', 'Kode'])->groupBy('Id_Role_User')
            : collect();

        return $baris->map(fn ($b) => [
            'id_penugasan' => (int) $b->id_penugasan,
            'id_user' => (int) $b->id_user,
            'nama' => $b->nama,
            'kode_karyawan' => $b->kode_karyawan,
            'id_role' => (int) $b->id_role,
            'kode' => $b->kode,
            'nama_peran' => $b->nama_peran,
            'modul' => $b->modul,
            'sejak' => $b->sejak,
            'berlaku_sampai' => $b->berlaku_sampai,
            'cakupan' => $this->cakupan->labelkan(collect($cakupan->get($b->id_penugasan, []))
                ->map(fn ($c) => ['jenis' => $c->Jenis_Cakupan, 'nilai' => $c->Nilai])->values()->all() ?: [['jenis' => 'SEMUA', 'nilai' => null]]),
            'tanggung_jawab' => collect($tj->get($b->id_penugasan, []))->pluck('Kode')->values()->all(),
        ])->values()->all();
    }

    public function cariPengguna(string $cari): array
    {
        $cari = trim($cari);
        if (mb_strlen($cari) < 2) {
            return [];
        }

        return DB::table('Karyawan')
            ->where('Aktif', 'Y')->whereNotNull('UserID_Web')
            ->where(fn ($q) => $q->where('Nama', 'like', "%{$cari}%")->orWhere('Kode_Karyawan', 'like', "%{$cari}%"))
            ->orderBy('Nama')->limit(20)
            ->get(['UserID_Web', 'Nama', 'Kode_Karyawan'])
            ->map(fn ($k) => ['id_user' => (int) $k->UserID_Web, 'nama' => $k->Nama, 'kode_karyawan' => $k->Kode_Karyawan])
            ->all();
    }

    /**
     * Pemilih karyawan untuk penugasan (bisa banyak sekaligus): saringan divisi,
     * departemen, lokasi, level, jabatan, status kepegawaian, dan — bila peran
     * sasaran diketahui — status memegang peran itu. Wewenang atas tiap karyawan
     * tetap diperiksa server saat menugaskan.
     *
     * @return array{data: list<array<string, mixed>>, total: int, halaman: int, per_halaman: int}|array{id_user: list<int>}
     */
    public function pilihKaryawan(array $saring, ?int $idRole = null, int $halaman = 1, int $perHalaman = 25, bool $semuaId = false): array
    {
        $memegang = fn ($w) => $w->from('N_HRIS_Role_User as ru')->whereColumn('ru.UserId_Web', 'k.UserID_Web')
            ->where('ru.Id_Role', $idRole)->where('ru.Flag_Aktif', 'Y')
            ->when(SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai'), fn ($q) => $q->where(fn ($b) => $b->whereNull('ru.Berlaku_Sampai')->orWhere('ru.Berlaku_Sampai', '>', now())));

        $q = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as g', 'g.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')->whereNotNull('k.UserID_Web');
        $cari = trim((string) ($saring['cari'] ?? ''));
        if ($cari !== '') {
            $q->where(fn ($w) => $w->where('k.Nama', 'like', "%{$cari}%")->orWhere('k.Kode_Karyawan', 'like', "%{$cari}%"));
        }
        foreach (['divisi' => 'd.ID_Divisi', 'departemen' => 'k.ID_Divisi_Sub_Divisi', 'level' => 'g.ID_Level', 'jabatan' => 'g.ID_Jabatan'] as $k => $kolom) {
            $nilai = array_values(array_filter((array) ($saring[$k] ?? []), fn ($v) => $v !== null && $v !== ''));
            if ($nilai !== []) {
                $q->whereIn($kolom, $nilai);
            }
        }
        $lokasi = array_values(array_filter((array) ($saring['lokasi'] ?? []), fn ($v) => $v !== null && $v !== ''));
        if ($lokasi !== []) {
            $q->whereIn(DB::raw('LTRIM(RTRIM(k.Lokasi))'), $lokasi);
        }
        $status = array_values(array_filter((array) ($saring['status_kepegawaian'] ?? []), fn ($v) => $v !== null && $v !== ''));
        if ($status !== []) {
            $q->whereIn('k.Status_Kepegawaian', $status);
        }
        if ($idRole !== null && in_array($saring['status'] ?? null, ['belum', 'sudah'], true)) {
            $saring['status'] === 'belum' ? $q->whereNotExists($memegang) : $q->whereExists($memegang);
        }

        if ($semuaId) {
            // "Pilih semua hasil saringan" — tanpa yang sudah memegang peran sasaran,
            // dibatasi agar satu penugasan massal tetap wajar.
            $baris = (clone $q)->when($idRole !== null, fn ($b) => $b->whereNotExists($memegang))
                ->orderBy('k.Nama')->limit(self::MAKS_MASSAL)->get(['k.UserID_Web', 'k.Nama'])
                ->map(fn ($r) => ['id_user' => (int) $r->UserID_Web, 'nama' => $r->Nama]);

            return ['id_user' => $baris->pluck('id_user')->all(), 'karyawan' => $baris->all()];
        }

        $total = (clone $q)->count();
        $data = $q->orderBy('k.Nama')->offset(($halaman - 1) * $perHalaman)->limit($perHalaman)
            ->selectRaw('k.UserID_Web as id_user, k.Nama as nama, k.Kode_Karyawan as kode_karyawan, d.nama_divisi as divisi, d.nama_sub_divisi as departemen')
            ->selectRaw('LTRIM(RTRIM(k.Lokasi)) as lokasi, g.nama_level as level, g.nama_jabatan as jabatan, k.Status_Kepegawaian as status_kepegawaian')
            ->when($idRole !== null, fn ($b) => $b->selectRaw(
                "CASE WHEN EXISTS (SELECT 1 FROM N_HRIS_Role_User x WHERE x.UserId_Web = k.UserID_Web AND x.Id_Role = ? AND x.Flag_Aktif = 'Y'"
                .(SkemaTersedia::kolom('N_HRIS_Role_User', 'Berlaku_Sampai') ? ' AND (x.Berlaku_Sampai IS NULL OR x.Berlaku_Sampai > GETDATE())' : '')
                .') THEN 1 ELSE 0 END as memegang', [$idRole]))
            ->get()
            ->map(fn ($r) => [
                'id_user' => (int) $r->id_user, 'nama' => $r->nama, 'kode_karyawan' => $r->kode_karyawan,
                'divisi' => $r->divisi !== null ? trim((string) $r->divisi) : null, 'departemen' => $r->departemen !== null ? trim((string) $r->departemen) : null,
                'lokasi' => $r->lokasi ?: null, 'level' => $r->level, 'jabatan' => $r->jabatan,
                'status_kepegawaian' => $r->status_kepegawaian ?: null, 'memegang' => (bool) ($r->memegang ?? false),
            ])->all();

        return ['data' => $data, 'total' => $total, 'halaman' => $halaman, 'per_halaman' => $perHalaman];
    }

    public const MAKS_MASSAL = 200;

    /** Pilihan saringan pemilih karyawan. Departemen membawa id divisinya agar bisa disaring berjenjang. */
    public function opsiSaringKaryawan(): array
    {
        $nilai = $this->cakupan->pilihanNilai();
        $departemen = DB::table('View_Divisi_Sub_Divisi')->orderBy('nama_divisi')->orderBy('nama_sub_divisi')
            ->get(['ID_DIVISI_SUB_DIVISI', 'ID_Divisi', 'nama_sub_divisi'])->unique('ID_DIVISI_SUB_DIVISI')
            ->map(fn ($d) => ['nilai' => (string) $d->ID_DIVISI_SUB_DIVISI, 'label' => trim((string) $d->nama_sub_divisi), 'divisi' => (string) $d->ID_Divisi])
            ->values()->all();
        $status = DB::table('Karyawan')->where('Aktif', 'Y')->whereNotNull('Status_Kepegawaian')->where('Status_Kepegawaian', '<>', '')
            ->distinct()->orderBy('Status_Kepegawaian')->pluck('Status_Kepegawaian')
            ->map(fn ($v) => ['nilai' => (string) $v, 'label' => (string) $v])->values()->all();

        return [
            'divisi' => $nilai['DIVISI'], 'departemen' => $departemen, 'lokasi' => $nilai['LOKASI'],
            'level' => $nilai['LEVEL'], 'jabatan' => $nilai['JABATAN'], 'status_kepegawaian' => $status,
        ];
    }

    /**
     * Anggota modul: karyawan yang memegang peran aktif di modul ini atau grant
     * langsung atas halamannya — titik awal tab Pengguna (bukan layar kosong).
     *
     * @return array{data: list<array<string, mixed>>, total: int, halaman: int, per_halaman: int}
     */
    public function daftarAnggota(?array $modul, string $cari = '', ?int $idRole = null, ?string $tingkat = null, int $halaman = 1, int $perHalaman = 20): array
    {
        $tingkatPeran = app(TingkatPeran::class);
        $kolom = ['ru.Id', 'ru.UserId_Web', 'rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name', 'rf.Id_Grup_Fitur'];
        if (SkemaRbac::punya('model_baru')) {
            $kolom[] = 'rf.Jenis_Role';
        }
        if (SkemaRbac::punya('tingkat')) {
            $kolom[] = 'rf.Kode_Tingkat';
        }
        $penugasan = $this->saringModul(DB::table('N_HRIS_Role_User as ru')->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->where('ru.Flag_Aktif', 'Y')->where('rf.Flag_Aktif', 'Y'), $modul, 'rf.Id_Grup_Fitur')
            ->tap(fn ($q) => SkemaRbac::saringPenugasanAktif($q, 'ru.'))->get($kolom)->groupBy('UserId_Web');
        $halamanModul = $this->halamanModul($modul);
        $grant = DB::table('HRIS_Page_Access')->where('Flag_Aktif', 'Y')
            ->when($halamanModul !== null, fn ($q) => $q->whereIn(DB::raw('LOWER(LTRIM(RTRIM(Jenis_page)))'), $halamanModul ?: ['-']))
            ->selectRaw('UserID_Web, COUNT(DISTINCT Jenis_page) as n')->groupBy('UserID_Web')->pluck('n', 'UserID_Web');

        $ids = $penugasan->keys()->merge($grant->keys())->map(fn ($v) => (int) $v)->unique()->values();
        $orang = DB::table('Karyawan')->whereIn('UserID_Web', $ids->all() ?: [-1])->get(['UserID_Web', 'Nama', 'Kode_Karyawan'])->keyBy('UserID_Web');
        $cari = mb_strtolower(trim($cari));

        $baris = $ids->map(function ($id) use ($penugasan, $grant, $orang, $tingkatPeran) {
            $k = $orang->get($id);
            $peran = collect($penugasan->get($id, []))->map(fn ($r) => [
                'id_penugasan' => (int) $r->Id, 'id_role' => (int) $r->Id_Role, 'kode' => $r->Role_Code, 'nama' => $r->Role_Name,
                'tingkat' => $tingkatPeran->dariPeran($r),
            ])->sortByDesc(fn ($p) => $tingkatPeran->peringkat($p['tingkat']))->values()->all();

            return [
                'id_user' => $id, 'nama' => $k->Nama ?? "User #{$id}", 'kode_karyawan' => $k->Kode_Karyawan ?? null,
                'peran' => $peran, 'jumlah_grant' => (int) ($grant[$id] ?? 0),
                'tingkat' => $tingkatPeran->tertinggi(array_column($peran, 'tingkat')),
            ];
        })
            ->filter(fn ($b) => $cari === '' || str_contains(mb_strtolower($b['nama']), $cari) || str_contains(mb_strtolower((string) $b['kode_karyawan']), $cari))
            ->filter(fn ($b) => $idRole === null || in_array($idRole, array_column($b['peran'], 'id_role'), true))
            ->filter(fn ($b) => $tingkat === null || $tingkat === '' || $b['tingkat'] === strtoupper($tingkat))
            ->sortBy([fn ($a, $b) => $tingkatPeran->peringkat($b['tingkat']) <=> $tingkatPeran->peringkat($a['tingkat']), fn ($a, $b) => strcmp($a['nama'], $b['nama'])])
            ->values();

        return ['data' => $baris->forPage($halaman, $perHalaman)->values()->all(), 'total' => $baris->count(), 'halaman' => $halaman, 'per_halaman' => $perHalaman];
    }

    /** Halaman (Jenis_page) milik modul-modul ini; null = semua. */
    private function halamanModul(?array $modul): ?array
    {
        return $modul === null ? null : DB::table('N_HRIS_Grup_Fitur_Map')->whereIn('Id_Grup_Fitur', $modul ?: [-1])
            ->pluck('Jenis_page')->map(fn ($p) => strtolower(trim((string) $p)))->unique()->values()->all();
    }

    public function detailPengguna(int $idUser, ?array $modul, ?User $peminta = null): ?array
    {
        $user = User::where('Id_Users', $idUser)->first();
        if (! $user) {
            return null;
        }
        $karyawan = DB::table('Karyawan')->where('UserID_Web', $idUser)->first(['Nama', 'Kode_Karyawan']);
        $halamanModul = $this->halamanModul($modul);
        $dalamModul = fn ($page) => $halamanModul === null || in_array(strtolower(trim((string) $page)), $halamanModul, true);

        $adaKolom = SkemaTersedia::kolom('HRIS_Page_Access', 'Berlaku_Sampai');
        $grant = DB::table('HRIS_Page_Access as pa')
            ->leftJoin('N_HRIS_Menus as m', 'm.Jenis_page', '=', 'pa.Jenis_page')
            ->where('pa.UserID_Web', $idUser)->where('pa.Flag_Aktif', 'Y')
            ->orderBy('m.Header_Label')->orderBy('m.Nama_Menu')
            ->selectRaw('pa.Jenis_page as jenis_page, m.Nama_Menu as nama_menu, m.Header_Label as header')
            ->selectRaw($adaKolom ? 'pa.Alasan as alasan, pa.Berlaku_Sampai as berlaku_sampai' : 'NULL as alasan, NULL as berlaku_sampai')
            ->get()->filter(fn ($g) => $dalamModul($g->jenis_page))
            // Data lama punya baris kembar (beda huruf besar/kecil) — satu halaman satu baris.
            ->unique(fn ($g) => strtolower(trim((string) $g->jenis_page)))
            ->map(fn ($g) => (array) $g)->values()->all();

        $efektif = DB::table('N_HRIS_Menus')->orderBy('Header_Label')->orderBy('Nama_Menu')
            ->get(['Jenis_page', 'Nama_Menu', 'Header_Label'])
            ->filter(fn ($m) => $dalamModul($m->Jenis_page) && $this->keputusan->boleh($user, (string) $m->Jenis_page))
            ->map(fn ($m) => ['jenis_page' => $m->Jenis_page, 'nama_menu' => $m->Nama_Menu, 'header' => $m->Header_Label])
            ->unique('jenis_page')->values()->all();

        $peran = $this->penugasan(fn ($q) => $this->saringModul($q->where('ru.UserId_Web', $idUser), $modul, 'rf.Id_Grup_Fitur'));
        $kelola = $peminta ? $this->benderaKelola($peminta, $idUser, $modul) : ['bisa_kelola' => false];
        foreach ($peran as &$p) {
            $p['kedaluwarsa'] = $p['berlaku_sampai'] !== null && strtotime((string) $p['berlaku_sampai']) < time();
            $p['bisa_cabut'] = $kelola['bisa_kelola'] && $this->alasanTolak(fn () => $this->aturan->periksaCabut(
                $peminta, (object) ['UserId_Web' => $idUser, 'Id_Role' => $p['id_role']],
            )) === null;
        }
        unset($p);
        foreach ($grant as &$g) {
            $g['kedaluwarsa'] = ($g['berlaku_sampai'] ?? null) !== null && strtotime((string) $g['berlaku_sampai']) < time();
            $g['bisa_cabut'] = $kelola['bisa_kelola'] && $this->alasanTolak(fn () => $this->aturan->periksaGrant($peminta, (string) $g['jenis_page'], $idUser, false)) === null; // sama dengan cabutGrant
        }
        unset($g);

        return $kelola + [
            'id_user' => $idUser,
            'nama' => $karyawan->Nama ?? $user->Username,
            'kode_karyawan' => $karyawan->Kode_Karyawan ?? null,
            // Status System Admin hanya untuk peminta yang juga System Admin.
            'system_admin' => $modul === null ? $this->keputusan->apakahSystemAdmin($user) : null,
            'peran' => $peran,
            'grant_langsung' => $grant,
            'halaman_efektif' => $efektif,
        ];
    }

    /**
     * Apa yang boleh dilakukan $peminta terhadap $idUser (RBAC Fase 3b). Daftar
     * yang ditawarkan sudah tersaring AturanDelegasi; server tetap memeriksa
     * ulang saat simpan.
     */
    private function benderaKelola(User $peminta, int $idUser, ?array $modul): array
    {
        $sa = $this->keputusan->apakahSystemAdmin($peminta);
        // Hanya modul yang boleh DIUBAH (tanggung jawab AKSES), bukan sekadar dilihat,
        // dan hanya bila sasaran bertingkat lebih rendah di modul itu.
        $tingkat = app(TingkatPeran::class);
        $aktor = fn (int $m) => $this->keputusan->tingkatDiModul($peminta, $m);
        // Di halaman modul ($modul = [id]) pilihan terkunci ke modul itu — juga bagi System Admin.
        $modulBoleh = $sa ? $modul : array_values(array_filter($this->keputusan->modulKelolaAkses($peminta) ?? [], fn ($m) => ($modul === null || in_array((int) $m, $modul, true))
            && $this->cakupan->adminMencakup($peminta, (int) $m, $idUser)
            && $tingkat->peringkat($this->keputusan->tingkatSasaranDiModul($idUser, (int) $m)) < $tingkat->peringkat($aktor((int) $m))));
        $bisa = (int) $peminta->Id_Users !== $idUser && ($sa || $modulBoleh !== []);
        if (! $bisa) {
            return ['bisa_kelola' => false, 'peran_tersedia' => [], 'halaman_tersedia' => [], 'cakupan_tersedia' => []];
        }

        $kolom = ['rf.Id_Role', 'rf.Role_Code', 'rf.Role_Name', 'rf.Id_Grup_Fitur', 'g.Kode_Grup_Fitur'];
        if (SkemaRbac::punya('model_baru')) {
            $kolom[] = 'rf.Jenis_Role';
        }
        if (SkemaRbac::punya('tingkat')) {
            $kolom[] = 'rf.Kode_Tingkat';
        }
        $peran = DB::table('N_HRIS_Role_Functional as rf')->leftJoin('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'rf.Id_Grup_Fitur')
            ->where('rf.Flag_Aktif', 'Y')
            ->when($modulBoleh !== null, fn ($q) => $q->whereIn('rf.Id_Grup_Fitur', $modulBoleh ?: [-1]))
            ->orderBy('g.Sort_Order')->orderBy('rf.Role_Name')
            ->get($kolom)
            // Ditawarkan hanya yang boleh: peran admin → tingkat pengubah boleh mengangkat, peringkatnya
            // lebih rendah, dan tak membawa halaman SYSTEM_ONLY; peran pengguna → boleh menugaskan.
            ->filter(function ($r) use ($sa, $tingkat, $aktor) {
                if ($sa) {
                    return true;
                }
                $t = $tingkat->dariPeran($r);
                $a = $aktor((int) $r->Id_Grup_Fitur);

                return $tingkat->setingkatAdmin($t)
                    ? $tingkat->boleh($a, TingkatPeran::ANGKAT)
                        && $tingkat->peringkat($t) < $tingkat->peringkat($a) && $this->aturan->bolehAngkatAdmin($r)
                    : $tingkat->boleh($a, TingkatPeran::TUGASKAN);
            })
            ->map(fn ($r) => ['id' => (int) $r->Id_Role, 'kode' => $r->Role_Code, 'nama' => $r->Role_Name, 'modul' => $r->Kode_Grup_Fitur,
                'lama' => TingkatPeran::peranLama($r->Role_Code) && $tingkat->setingkatAdmin($tingkat->dariPeran($r)),
                'jenis' => $r->Jenis_Role ?? 'AKSES', 'tingkat' => $tingkat->dariPeran($r)])
            ->values()->all();

        $sistemSaja = rescue(fn () => DB::table('N_HRIS_Menu_Delegation_Policy')->where('Flag_Aktif', 'Y')->where('Policy_Mode', 'SYSTEM_ONLY')
            ->pluck('Jenis_page')->map(fn ($p) => HibahTataKelola::normalisasi($p))->all(), [], false);
        $halaman = DB::table('N_HRIS_Menus')->orderBy('Header_Label')->orderBy('Nama_Menu')->get(['Jenis_page', 'Nama_Menu', 'Header_Label'])
            ->unique('Jenis_page')
            ->filter(fn ($m) => $sa
                // System Admin: semua halaman — di halaman modul, halaman modul itu saja.
                ? $modulBoleh === null || in_array($this->mode->modulHalaman((string) $m->Jenis_page), $modulBoleh, true)
                : in_array($this->mode->modulHalaman((string) $m->Jenis_page), $modulBoleh, true)
                    && ! HibahTataKelola::apakahHalamanTataKelola((string) $m->Jenis_page)
                    && ! in_array(HibahTataKelola::normalisasi((string) $m->Jenis_page), $sistemSaja, true))
            ->map(fn ($m) => ['jenis_page' => $m->Jenis_page, 'nama_menu' => $m->Nama_Menu, 'header' => $m->Header_Label])
            ->values()->all();

        // Cakupan ditandai modulnya (dialog menyaring sesuai peran yang dipilih);
        // modul null = berlaku untuk peran mana pun (System Admin).
        $kode = DB::table('N_HRIS_Master_Grup_Fitur')->pluck('Kode_Grup_Fitur', 'Id_Grup_Fitur');
        // TEMPLATE ditawarkan terpisah (daftar template), bukan sebagai jenis kosong.
        $semua = fn (?string $m) => array_map(fn ($j) => ['jenis' => $j, 'nilai' => null, 'modul' => $m], array_values(array_diff(CakupanKaryawan::JENIS, ['TEMPLATE'])));
        $cakupan = $sa ? $semua(null) : [];
        $tanpaBatas = $sa;
        foreach ($sa ? [] : $modulBoleh as $m) {
            $milik = $this->cakupan->cakupanAdmin($peminta, (int) $m);
            $tanpaBatas = $tanpaBatas || $milik === null;
            $cakupan = array_merge($cakupan, $milik === null
                ? $semua($kode[$m] ?? null)
                : array_map(fn ($c) => $c + ['modul' => $kode[$m] ?? null], $milik));
        }

        return [
            'bisa_kelola' => true,
            'peran_tersedia' => $peran,
            'halaman_tersedia' => $halaman,
            'cakupan_tersedia' => $this->cakupan->labelkan(array_values(array_unique($cakupan, SORT_REGULAR))),
            // Pengelola tanpa batas cakupan boleh memilih nilai konkret (bukan "milik pemegang").
            'nilai_cakupan' => $tanpaBatas ? $this->cakupan->pilihanNilai() : [],
            // Template cakupan (DDL-2026-184) — juga berisi nilai konkret, jadi syaratnya sama.
            'template_cakupan' => $tanpaBatas ? app(TemplateCakupanService::class)->daftar($peminta, $sa ? $modul : $modulBoleh) : [],
        ];
    }

    /**
     * Jalur akses $halaman milik user. Admin modul hanya boleh menelusuri halaman
     * di modulnya (selain itu null → 404), dan hanya melihat jalur lewat peran di
     * modulnya; status System Admin tidak dibuka kepadanya.
     */
    public function telusuri(int $idUser, string $halaman, ?array $modul = null): ?array
    {
        $user = User::where('Id_Users', $idUser)->first();
        if (! $user) {
            return null;
        }
        $hasil = $this->keputusan->telusuri($user, $halaman);
        if ($modul === null) {
            return $hasil;
        }
        if (! in_array(strtolower(trim($halaman)), $this->halamanModul($modul), true)) {
            return null;
        }
        $peranModul = DB::table('N_HRIS_Role_Functional')->whereIn('Id_Grup_Fitur', $modul ?: [-1])->pluck('Role_Code')
            ->flatMap(fn ($k) => ['HIBAH_ROLE:'.$k, 'HAK_PERAN:'.$k])->all();
        $saring = fn (array $jalur) => array_values(array_filter(
            $jalur,
            fn ($j) => in_array($j, ['GRANT_LANGSUNG', 'MENU_GLOBAL'], true) || in_array($j, $peranModul, true),
        ));
        $hasil['jalur'] = $saring($hasil['jalur']);
        if (isset($hasil['jalur_baru'])) {
            $hasil['jalur_baru'] = $saring($hasil['jalur_baru']);
        }

        return $hasil;
    }

    public function admin(?array $modul, ?User $peminta = null): array
    {
        $adaJenis = SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role');
        $baris = $this->penugasan(fn ($q) => $this->saringModul(
            $adaJenis ? $q->where('rf.Jenis_Role', 'ADMIN') : $q->whereRaw('1 = 0'),
            $modul,
            'rf.Id_Grup_Fitur',
        ));
        $kolom = ['Id_Role', 'Role_Code', 'Id_Grup_Fitur', ...($adaJenis ? ['Jenis_Role'] : []), ...(SkemaRbac::punya('tingkat') ? ['Kode_Tingkat'] : [])];
        $peran = DB::table('N_HRIS_Role_Functional')->whereIn('Id_Role', array_unique(array_column($baris, 'id_role')) ?: [-1])->get($kolom)->keyBy('Id_Role');
        $tingkat = app(TingkatPeran::class);

        return array_map(fn ($b) => $b + [
            'tingkat' => isset($peran[$b['id_role']]) ? $tingkat->dariPeran($peran[$b['id_role']]) : TingkatPeran::ADMIN,
            'bisa_cabut' => $peminta !== null && (int) $peminta->Id_Users !== $b['id_user']
                && $this->alasanTolak(fn () => $this->aturan->periksaCabut($peminta, (object) ['UserId_Web' => $b['id_user'], 'Id_Role' => $b['id_role']])) === null,
        ], $baris);
    }

    private function kueriAudit(?array $modul): ?Builder
    {
        if (! SkemaTersedia::tabel(AuditAkses::TABEL)) {
            return null;
        }
        $q = DB::table(AuditAkses::TABEL.' as a');
        if ($modul === null) {
            return $q;
        }

        // Admin modul: jejak yang ia buat; GRANT/KATALOG atas halaman di modulnya;
        // PENUGASAN atas user yang KINI aktif memegang peran di modulnya.
        $halaman = $this->halamanModul($modul);

        return $q->where(fn ($w) => $w->where('a.Oleh_Id_User', auth()->id())
            ->orWhere(fn ($g) => $g->whereIn('a.Subjek_Jenis', ['GRANT', 'KATALOG'])
                ->whereIn(DB::raw('LOWER(LTRIM(RTRIM(a.Subjek_Id)))'), $halaman ?: ['-']))
            ->orWhere(fn ($p) => $p->where('a.Subjek_Jenis', 'PENUGASAN')
                ->whereIn('a.Target_Id_User', DB::table('N_HRIS_Role_User as ru')
                    ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
                    ->where('ru.Flag_Aktif', 'Y')
                    ->whereIn('rf.Id_Grup_Fitur', $modul ?: [-1])->select('ru.UserId_Web')))
            // Perubahan hak/tingkat peran milik modul ini.
            ->orWhere(fn ($r) => $r->where('a.Subjek_Jenis', 'ROLE')
                ->whereIn('a.Subjek_Id', DB::table('N_HRIS_Role_Functional')->whereIn('Id_Grup_Fitur', $modul ?: [-1])->selectRaw('CAST(Id_Role AS varchar(20))'))));
    }

    /**
     * @param  array{aksi?: ?string, cari?: ?string, dari?: ?string, sampai?: ?string, peran?: ?int}  $saring
     */
    public function audit(?array $modul, int $halaman = 1, int $perHalaman = 20, array $saring = []): array
    {
        $q = $this->kueriAudit($modul);
        if (! $q) {
            return ['data' => [], 'total' => 0, 'halaman' => 1, 'per_halaman' => $perHalaman];
        }
        if (! empty($saring['aksi'])) {
            $q->where('a.Aksi', strtoupper($saring['aksi']));
        }
        if (! empty($saring['dari'])) {
            $q->where('a.Waktu', '>=', \Illuminate\Support\Carbon::parse($saring['dari'])->startOfDay());
        }
        if (! empty($saring['sampai'])) {
            $q->where('a.Waktu', '<=', \Illuminate\Support\Carbon::parse($saring['sampai'])->endOfDay());
        }
        if (! empty($saring['cari'])) {
            $cari = '%'.trim($saring['cari']).'%';
            $q->where(fn ($w) => $w->whereIn('a.Oleh_Id_User', DB::table('Karyawan')->where('Nama', 'like', $cari)->select('UserID_Web'))
                ->orWhereIn('a.Target_Id_User', DB::table('Karyawan')->where('Nama', 'like', $cari)->select('UserID_Web')));
        }
        if (! empty($saring['peran'])) {
            // Riwayat sebuah peran: perubahan hak/tingkatnya + penugasan yang menyebut kodenya.
            $kode = (string) DB::table('N_HRIS_Role_Functional')->where('Id_Role', (int) $saring['peran'])->value('Role_Code');
            $q->where(fn ($w) => $w->where(fn ($r) => $r->where('a.Subjek_Jenis', 'ROLE')->where('a.Subjek_Id', (string) (int) $saring['peran']))
                ->orWhere(fn ($p) => $p->where('a.Subjek_Jenis', 'PENUGASAN')->where('a.Nilai_Sesudah', 'like', '%"'.$kode.'"%')
                    ->whereRaw("ISNULL(a.Nilai_Sebelum, '') NOT LIKE ?", ['%"'.$kode.'"%']))
                ->orWhere(fn ($p) => $p->where('a.Subjek_Jenis', 'PENUGASAN')->where('a.Nilai_Sebelum', 'like', '%"'.$kode.'"%')
                    ->where('a.Nilai_Sesudah', 'not like', '%"'.$kode.'"%')));
        }
        $total = (clone $q)->count();
        // Subjek MODE menyimpan id modul; tampilkan kodenya (DMS, ESS, …).
        $kodeModul = DB::table('N_HRIS_Master_Grup_Fitur')->pluck('Kode_Grup_Fitur', 'Id_Grup_Fitur');
        $data = $q->leftJoin('Karyawan as ko', 'ko.UserID_Web', '=', 'a.Oleh_Id_User')
            ->leftJoin('Karyawan as kt', 'kt.UserID_Web', '=', 'a.Target_Id_User')
            ->orderByDesc('a.Id_Akses_Audit')
            ->offset(($halaman - 1) * $perHalaman)->limit($perHalaman)
            ->get(['a.Id_Akses_Audit', 'a.Waktu', 'a.Oleh_Id_User', 'ko.Nama as oleh', 'a.Target_Id_User', 'kt.Nama as target',
                'a.Subjek_Jenis', 'a.Subjek_Id', 'a.Aksi', 'a.Nilai_Sebelum', 'a.Nilai_Sesudah', 'a.Alasan', 'a.Sumber'])
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Akses_Audit, 'waktu' => $r->Waktu,
                'oleh' => $r->oleh, 'id_oleh' => $r->Oleh_Id_User !== null ? (int) $r->Oleh_Id_User : null,
                'target' => $r->target, 'id_target' => $r->Target_Id_User !== null ? (int) $r->Target_Id_User : null,
                'subjek_jenis' => $r->Subjek_Jenis, 'subjek_id' => $r->Subjek_Id, 'aksi' => $r->Aksi,
                'subjek_label' => $r->Subjek_Jenis === 'MODE' ? ($kodeModul[(int) $r->Subjek_Id] ?? null) : null,
                'sebelum' => $r->Nilai_Sebelum, 'sesudah' => $r->Nilai_Sesudah, 'alasan' => $r->Alasan, 'sumber' => $r->Sumber,
            ])->all();

        return ['data' => $data, 'total' => $total, 'halaman' => $halaman, 'per_halaman' => $perHalaman];
    }
}
