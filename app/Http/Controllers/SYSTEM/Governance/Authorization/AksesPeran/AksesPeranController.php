<?php

namespace App\Http\Controllers\SYSTEM\Governance\Authorization\AksesPeran;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Application\Database\SkemaTersedia;
use App\Services\SYSTEM\Governance\Authorization\Akses\AksesDitolak;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\ModeAkses;
use App\Services\SYSTEM\Governance\Authorization\Akses\SinkronHakRole;
use App\Services\SYSTEM\Governance\Authorization\Akses\SkemaRbac;
use App\Services\SYSTEM\Governance\Authorization\Akses\TingkatPeran;
use App\Services\SYSTEM\Governance\Authorization\AksesPeran\AksesPeranQueryService;
use App\Services\SYSTEM\Governance\Authorization\AksesPeran\KatalogAksesService;
use App\Services\SYSTEM\Governance\Authorization\AksesPeran\PengelolaanAksesService;
use App\Services\SYSTEM\Governance\Authorization\AksesPeran\TemplateCakupanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Layar "Akses & Peran" (RBAC Fase 2b) — baca-saja. Gerbang: `akses.peran`.
 * Lingkup modul selalu dari user yang meminta, bukan dari parameter.
 */
class AksesPeranController extends Controller
{
    public function __construct(private AksesPeranQueryService $data, private KeputusanAkses $keputusan) {}

    /** Lingkup: konteks modul halaman (bila ada), selain itu semua modul yang dikelola. */
    private function modul(Request $request): ?array
    {
        $konteks = $request->attributes->get('akses_modul');

        return $konteks !== null ? [(int) $konteks] : $this->keputusan->modulTerkelola($request->user());
    }

    /** Perubahan di halaman modul hanya untuk modul itu. */
    private function dalamModul(Request $request, mixed $grup, string $pesan): void
    {
        $konteks = $request->attributes->get('akses_modul');
        if ($konteks !== null && (int) $grup !== (int) $konteks) {
            throw new AksesDitolak($pesan);
        }
    }

    private function grupPeran(int $idRole): ?int
    {
        $g = DB::table('N_HRIS_Role_Functional')->where('Id_Role', $idRole)->value('Id_Grup_Fitur');

        return $g !== null ? (int) $g : null;
    }

    /** Daftar modul yang berhak dikelola pengguna saat ini (untuk switcher modul). */
    private function daftarModulNavigasi(Request $request): array
    {
        $user = $request->user();
        if (! $user) {
            return [];
        }
        $sa = $this->keputusan->apakahSystemAdmin($user);
        $grups = DB::table('N_HRIS_Master_Grup_Fitur')
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Kode_Grup_Fitur', ['ESS', 'KPI', 'LMS', 'VISITOR', 'DMS'])
            ->orderBy('Sort_Order')
            ->get(['Id_Grup_Fitur', 'Kode_Grup_Fitur', 'Nama_Grup_Fitur']);

        $list = [];
        if ($sa) {
            $list[] = [
                'kode' => 'system',
                'label' => 'Semua Modul (SYSTEM)',
                'nama' => 'System & Lintas Modul',
                'url' => '/system/akses-peran',
                'is_system' => true,
            ];
        }

        foreach ($grups as $g) {
            if ($sa || $this->keputusan->bolehKelolaModul($user, (int) $g->Id_Grup_Fitur)) {
                $list[] = [
                    'kode' => strtolower($g->Kode_Grup_Fitur),
                    'label' => $g->Kode_Grup_Fitur,
                    'nama' => $g->Nama_Grup_Fitur,
                    'url' => '/admin/'.strtolower($g->Kode_Grup_Fitur).'/akses-peran',
                    'is_system' => false,
                ];
            }
        }

        return $list;
    }

    /** SYSTEM → Akses & Peran: lintas modul, khusus System Admin (gerbang `system.admin`). */
    public function page(Request $request)
    {
        return Inertia::render('SYSTEM/Governance/Authorization/AksesPeran/AksesPeranPage', [
            'lingkup' => [
                'semua_modul' => true,
                'system_admin' => true,
                'modul' => null,
                'anda' => $this->anda(TingkatPeran::SYSTEM_ADMIN),
                'daftar_modul' => $this->daftarModulNavigasi($request),
            ],
        ]);
    }

    /** "Anda": tingkat & kemampuan pengguna di halaman ini — ditampilkan di kepala halaman. */
    private function anda(string $kode): array
    {
        $t = app(TingkatPeran::class);

        return [
            'tingkat' => $kode,
            'label' => $t->semua()[$kode]['Nama_Tingkat'] ?? $kode,
            'boleh_angkat' => $t->boleh($kode, TingkatPeran::ANGKAT),
            'boleh_ubah_matriks' => $t->boleh($kode, TingkatPeran::UBAH_MATRIKS),
            'boleh_tugaskan' => $t->boleh($kode, TingkatPeran::TUGASKAN),
        ];
    }

    /** {Modul} → Akses & Peran: terkunci ke modul itu; Katalog & tingkat tetap di SYSTEM. */
    public function pageModul(Request $request, string $modul)
    {
        $grup = DB::table('N_HRIS_Master_Grup_Fitur')->where('Id_Grup_Fitur', $request->attributes->get('akses_modul'))->first(['Kode_Grup_Fitur', 'Nama_Grup_Fitur']);

        return Inertia::render('SYSTEM/Governance/Authorization/AksesPeran/AksesPeranPage', [
            'lingkup' => [
                'semua_modul' => false,
                'system_admin' => false,
                'modul' => ['kode' => strtolower($modul), 'label' => $grup->Kode_Grup_Fitur ?? strtoupper($modul), 'nama' => $grup->Nama_Grup_Fitur ?? null],
                'anda' => $this->anda($this->keputusan->tingkatDiModul($request->user(), (int) $request->attributes->get('akses_modul'))),
                'daftar_modul' => $this->daftarModulNavigasi($request),
            ],
        ]);
    }

    public function ringkasan(Request $request)
    {
        return ResponseHelper::success($this->data->ringkasan($this->modul($request)), 'Ringkasan akses.');
    }

    public function peran(Request $request)
    {
        return ResponseHelper::success($this->data->daftarPeran($this->modul($request)), 'Daftar peran.');
    }

    public function detailPeran(Request $request, int $id)
    {
        $r = $this->data->detailPeran($id, $this->modul($request), $request->user());

        return $r ? ResponseHelper::success($r, 'Detail peran.') : ResponseHelper::error('Peran tidak ditemukan.', 404);
    }

    /** ?anggota=1 → anggota modul (peran/grant), dengan saringan; selain itu cari karyawan mana pun. */
    public function pengguna(Request $request)
    {
        if ($request->boolean('anggota')) {
            return ResponseHelper::success($this->data->daftarAnggota(
                $this->modul($request),
                (string) $request->query('cari', ''),
                $request->filled('peran') ? (int) $request->query('peran') : null,
                $request->query('tingkat'),
                max(1, (int) $request->query('halaman', 1)),
            ), 'Anggota modul.');
        }

        return ResponseHelper::success($this->data->cariPengguna((string) $request->query('cari', '')), 'Hasil pencarian.');
    }

    /** Pemilih karyawan bersaring untuk penugasan (banyak sekaligus). */
    public function pilihKaryawan(Request $request)
    {
        $saring = $request->only(['cari', 'divisi', 'departemen', 'lokasi', 'level', 'jabatan', 'status_kepegawaian', 'status']);

        return ResponseHelper::success($this->data->pilihKaryawan(
            $saring,
            $request->filled('id_role') ? (int) $request->query('id_role') : null,
            max(1, (int) $request->query('halaman', 1)),
            min(100, max(10, (int) $request->query('per_halaman', 25))),
            $request->boolean('semua_id'),
        ), 'Karyawan.');
    }

    public function opsiSaringKaryawan()
    {
        return ResponseHelper::success($this->data->opsiSaringKaryawan(), 'Pilihan saringan karyawan.');
    }

    /** Isi beberapa peran (halaman × aksi) untuk wizard penugasan. */
    public function isiPeran(Request $request)
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->query('ids', []))));
        abort_if(count($ids) > 20, 422, 'Paling banyak 20 peran.');

        return ResponseHelper::success($this->data->isiPeran($ids, $this->modul($request)), 'Isi peran.');
    }

    /** Peta balik halaman → peran & orang. */
    public function petaHalaman(Request $request)
    {
        return ResponseHelper::success($this->data->petaHalaman($this->modul($request), trim((string) $request->query('cari', ''))), 'Peta halaman.');
    }

    /** Pengguna yang berhak mengakses suatu halaman (via peran & via grant langsung). */
    public function orangHalaman(Request $request)
    {
        $halaman = trim((string) $request->query('halaman', ''));
        abort_if($halaman === '', 422, 'Halaman wajib diisi.');
        $r = $this->data->orangHalaman($halaman, $this->modul($request), $request->user());

        return $r ? ResponseHelper::success($r, 'Pengguna halaman.') : ResponseHelper::error('Halaman tidak ditemukan.', 404);
    }

    public function detailPengguna(Request $request, int $id)
    {
        $r = $this->data->detailPengguna($id, $this->modul($request), $request->user());

        return $r ? ResponseHelper::success($r, 'Akses pengguna.') : ResponseHelper::error('Pengguna tidak ditemukan.', 404);
    }

    public function telusuri(Request $request, int $id)
    {
        $halaman = trim((string) $request->query('halaman', ''));
        abort_if($halaman === '', 422, 'Halaman wajib diisi.');
        $r = $this->data->telusuri($id, $halaman, $this->modul($request));

        return $r ? ResponseHelper::success($r, 'Jalur akses.') : ResponseHelper::error('Pengguna atau halaman tidak ditemukan dalam lingkup Anda.', 404);
    }

    public function admin(Request $request)
    {
        return ResponseHelper::success($this->data->admin($this->modul($request), $request->user()), 'Admin & delegasi.');
    }

    public function audit(Request $request)
    {
        return ResponseHelper::success(
            $this->data->audit($this->modul($request), max(1, (int) $request->query('halaman', 1)), 20, [
                'aksi' => $request->query('aksi'), 'cari' => $request->query('cari'),
                'dari' => $request->query('dari'), 'sampai' => $request->query('sampai'),
                'peran' => $request->filled('peran') ? (int) $request->query('peran') : null,
            ]),
            'Jejak audit akses.',
        );
    }

    /* ── Tab Katalog (RBAC Fase 3a) — khusus System Admin ── */

    private function wajibSystemAdmin(Request $request): void
    {
        abort_unless($this->keputusan->apakahSystemAdmin($request->user()), 403, 'Katalog akses khusus System Admin.');
    }

    public function katalog(Request $request, KatalogAksesService $katalog)
    {
        $this->wajibSystemAdmin($request);

        return ResponseHelper::success($katalog->daftar(), 'Katalog modul.');
    }

    /** RBAC Fase 5c: kesiapan pensiun jalur lama per modul (sinkron kering). */
    public function kesiapan(Request $request, KatalogAksesService $katalog)
    {
        $this->wajibSystemAdmin($request);

        try {
            return ResponseHelper::success($katalog->kesiapan(), 'Kesiapan modul.');
        } catch (\App\Services\SYSTEM\Governance\Authorization\Akses\SinkronSedangBerjalan $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        }
    }

    /** Master tingkat peran (DDL-2026-181) — System Admin. */
    public function tingkat(Request $request, KatalogAksesService $katalog)
    {
        $this->wajibSystemAdmin($request);

        return ResponseHelper::success($katalog->tingkat(), 'Tingkat peran.');
    }

    public function ubahKemampuanTingkat(Request $request, KatalogAksesService $katalog, string $kode)
    {
        $this->wajibSystemAdmin($request);
        $v = $request->validate([
            'boleh_angkat' => ['sometimes', 'boolean'],
            'boleh_ubah_matriks' => ['sometimes', 'boolean'],
            'boleh_tugaskan' => ['sometimes', 'boolean'],
            'alasan' => self::ALASAN,
        ]);

        return ResponseHelper::success($katalog->ubahKemampuanTingkat($kode, $v, trim($v['alasan'])), 'Kemampuan tingkat diperbarui.');
    }

    public function ubahTingkatPeran(Request $request, KatalogAksesService $katalog, int $id)
    {
        $this->wajibSystemAdmin($request);
        $v = $request->validate(['tingkat' => ['required', 'string', 'max:20'], 'alasan' => self::ALASAN]);

        return ResponseHelper::success($katalog->ubahTingkatPeran($id, $v['tingkat'], trim($v['alasan'])), 'Tingkat peran diperbarui.');
    }

    public function ubahMode(Request $request, KatalogAksesService $katalog, int $grup)
    {
        $this->wajibSystemAdmin($request);
        $v = $request->validate([
            'mode' => ['required', 'in:LAMA,BAYANGAN,BARU'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        return ResponseHelper::success(
            $katalog->ubahMode($grup, $v['mode'], trim($v['alasan']), (int) $request->user()->Id_Users),
            'Mode modul diperbarui.',
        );
    }

    public function selisih(Request $request, KatalogAksesService $katalog)
    {
        $this->wajibSystemAdmin($request);
        $modul = $request->integer('modul') ?: null;

        return ResponseHelper::success($katalog->selisih($modul, max(1, $request->integer('halaman', 1))), 'Selisih aturan lama vs baru.');
    }

    public function bersihkanSelisih(Request $request, KatalogAksesService $katalog)
    {
        $this->wajibSystemAdmin($request);
        $v = $request->validate([
            'modul' => ['required', 'integer'],
            'alasan' => ['required', 'string', 'min:5', 'max:500'],
            'sampai' => ['nullable', 'date'],
        ]);

        return ResponseHelper::success(
            ['dihapus' => $katalog->bersihkanSelisih((int) $v['modul'], trim($v['alasan']), $v['sampai'] ?? null)],
            'Selisih dibersihkan.',
        );
    }

    public function sinkron(Request $request, SinkronHakRole $sinkron)
    {
        $this->wajibSystemAdmin($request);

        try {
            return ResponseHelper::success($sinkron->jalankan(false, 'UI'), 'Hak peran disinkronkan.');
        } catch (\App\Services\SYSTEM\Governance\Authorization\Akses\SinkronSedangBerjalan $e) {
            return ResponseHelper::error($e->getMessage(), 409);
        }
    }

    /* ── Tombol ubah (RBAC Fase 3b) — aturan di AturanDelegasi ── */

    private function kelola(callable $kerja)
    {
        try {
            return $kerja();
        } catch (AksesDitolak $e) {
            return ResponseHelper::error($e->getMessage(), 403);
        }
    }

    private const ALASAN = ['required', 'string', 'min:5', 'max:500'];

    public function tugaskan(Request $request, PengelolaanAksesService $kelola)
    {
        $v = $request->validate([
            'id_user' => ['required', 'integer'],
            'id_role' => ['required', 'integer'],
            'cakupan' => ['nullable', 'array'],
            'cakupan.*.jenis' => ['required', 'string', 'max:20'],
            'cakupan.*.nilai' => ['nullable', 'string', 'max:100'],
            'tanggung_jawab' => ['nullable', 'array'],
            'tanggung_jawab.*' => ['string', 'in:AKSES,APPROVAL,KEANGGOTAAN'],
            'berlaku_sampai' => ['nullable', 'date', 'after:today'],
            'alasan' => self::ALASAN,
        ]);

        return $this->kelola(function () use ($kelola, $request, $v) {
            $this->dalamModul($request, $this->grupPeran((int) $v['id_role']), 'Peran ini bukan milik modul halaman ini.');

            return ResponseHelper::success($kelola->tugaskan(
                $request->user(), (int) $v['id_user'], (int) $v['id_role'], $v['cakupan'] ?? [], $v['tanggung_jawab'] ?? [],
                $v['berlaku_sampai'] ?? null, trim($v['alasan']),
            ), 'Peran ditugaskan.');
        });
    }

    /**
     * Banyak karyawan × banyak peran. Tiap pasangan diperiksa & diaudit sendiri
     * (aturan sama dengan penugasan tunggal); yang ditolak dilaporkan tanpa
     * menggagalkan yang lain. Super Admin tidak digabung dengan tingkat lain.
     */
    public function tugaskanMassal(Request $request, PengelolaanAksesService $kelola)
    {
        $v = $request->validate([
            'id_users' => ['required', 'array', 'min:1', 'max:'.AksesPeranQueryService::MAKS_MASSAL],
            'id_users.*' => ['integer', 'distinct'],
            'id_role' => ['required_without:id_roles', 'integer'],
            'id_roles' => ['required_without:id_role', 'array', 'min:1', 'max:20'],
            'id_roles.*' => ['integer', 'distinct'],
            'cakupan' => ['nullable', 'array'],
            'cakupan.*.jenis' => ['required', 'string', 'max:20'],
            'cakupan.*.nilai' => ['nullable', 'string', 'max:100'],
            'tanggung_jawab' => ['nullable', 'array'],
            'tanggung_jawab.*' => ['string', 'in:AKSES,APPROVAL,KEANGGOTAAN'],
            'berlaku_sampai' => ['nullable', 'date', 'after:today'],
            'alasan' => self::ALASAN,
        ]);
        $idRoles = array_map('intval', $v['id_roles'] ?? [$v['id_role']]);
        abort_if(count($idRoles) * count($v['id_users']) > AksesPeranQueryService::MAKS_MASSAL, 422,
            'Paling banyak '.AksesPeranQueryService::MAKS_MASSAL.' pasangan karyawan × peran sekali simpan.');

        return $this->kelola(function () use ($kelola, $request, $v, $idRoles) {
            $peran = DB::table('N_HRIS_Role_Functional')->whereIn('Id_Role', $idRoles)
                ->selectRaw('Id_Role, Role_Name, Id_Grup_Fitur, '.(SkemaTersedia::kolom('N_HRIS_Role_Functional', 'Jenis_Role') ? 'Jenis_Role' : 'NULL as Jenis_Role')
                    .', '.(SkemaRbac::punya('tingkat') ? 'Kode_Tingkat' : 'NULL as Kode_Tingkat'))
                ->get()->keyBy(fn ($r) => (int) $r->Id_Role);
            $tingkat = app(TingkatPeran::class);
            $setingkatAdmin = fn ($r) => $r && ($tingkat->setingkatAdmin($r->Kode_Tingkat) || $r->Jenis_Role === 'ADMIN');
            $superAdmin = $peran->filter(fn ($r) => $r->Kode_Tingkat === 'SUPER_ADMIN')->count();
            abort_if($superAdmin && $superAdmin < count($idRoles), 422, 'Peran Super Admin tidak digabung dengan peran Admin atau Pengguna.');
            foreach ($idRoles as $idRole) {
                $g = $peran[$idRole]->Id_Grup_Fitur ?? null;
                $this->dalamModul($request, $g !== null ? (int) $g : null, 'Peran ini bukan milik modul halaman ini.');
            }
            $nama = DB::table('Karyawan')->whereIn('UserID_Web', $v['id_users'])->pluck('Nama', 'UserID_Web');
            $berhasil = [];
            $gagal = [];
            foreach ($idRoles as $idRole) {
                $r = $peran[$idRole] ?? null;
                // Tanggung jawab hanya untuk admin; batas waktu tak berlaku bagi pool approval.
                $tj = $setingkatAdmin($r) ? ($v['tanggung_jawab'] ?? []) : [];
                $sampai = $r && $r->Jenis_Role === 'POOL_APPROVAL' ? null : ($v['berlaku_sampai'] ?? null);
                foreach ($v['id_users'] as $idUser) {
                    $baris = ['id_user' => (int) $idUser, 'nama' => $nama[$idUser] ?? "User #{$idUser}",
                        'id_role' => $idRole, 'peran' => $r->Role_Name ?? "Peran #{$idRole}"];
                    try {
                        $kelola->tugaskan($request->user(), (int) $idUser, $idRole, $v['cakupan'] ?? [], $tj, $sampai, trim($v['alasan']));
                        $berhasil[] = $baris;
                    } catch (AksesDitolak $e) {
                        $gagal[] = $baris + ['alasan' => $e->getMessage()];
                    } catch (HttpExceptionInterface $e) {
                        $gagal[] = $baris + ['alasan' => $e->getMessage() ?: 'Ditolak.'];
                    }
                }
            }

            return ResponseHelper::success(['berhasil' => $berhasil, 'gagal' => $gagal],
                count($berhasil).' penugasan dibuat'.($gagal ? ', '.count($gagal).' dilewati.' : '.'));
        });
    }

    /* ── Template cakupan (DDL-2026-184) ── */

    public function templateCakupan(Request $request, TemplateCakupanService $template)
    {
        $konteks = $request->attributes->get('akses_modul');

        return ResponseHelper::success([
            'tersedia' => $template->tersedia(),
            'bisa_buat' => $template->tersedia() && $template->bolehKelola($request->user(), $konteks !== null ? (int) $konteks : null),
            'data' => $template->daftar($request->user(), $this->modul($request)),
        ], 'Template cakupan.');
    }

    public function simpanTemplateCakupan(Request $request, TemplateCakupanService $template, ?int $id = null)
    {
        $v = $request->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:300'],
            'syarat' => ['required', 'array', 'min:1', 'max:200'],
            'syarat.*.jenis' => ['required', 'string', 'max:20'],
            'syarat.*.nilai' => ['nullable', 'string', 'max:100'],
        ]);
        $konteks = $request->attributes->get('akses_modul');

        return $this->kelola(function () use ($template, $request, $v, $id, $konteks) {
            $baru = $template->simpan($request->user(), $id, $v['nama'], $v['keterangan'] ?? null,
                $konteks !== null ? (int) $konteks : null, $v['syarat']);

            return ResponseHelper::success(['id' => $baru], $id ? 'Template diperbarui.' : 'Template dibuat.');
        });
    }

    public function nonaktifkanTemplateCakupan(Request $request, TemplateCakupanService $template, int $id)
    {
        $v = $request->validate(['alasan' => self::ALASAN]);

        return $this->kelola(function () use ($template, $request, $id, $v) {
            $template->nonaktifkan($request->user(), $id, trim($v['alasan']));

            return ResponseHelper::success(null, 'Template dinonaktifkan.');
        });
    }

    public function cabutPenugasan(Request $request, PengelolaanAksesService $kelola, int $id)
    {
        $v = $request->validate(['alasan' => self::ALASAN]);

        return $this->kelola(function () use ($kelola, $request, $id, $v) {
            $role = DB::table('N_HRIS_Role_User')->where('Id', $id)->value('Id_Role');
            $this->dalamModul($request, $role !== null ? $this->grupPeran((int) $role) : null, 'Peran ini bukan milik modul halaman ini.');
            $kelola->cabut($request->user(), $id, trim($v['alasan']));

            return ResponseHelper::success(null, 'Peran dicabut.');
        });
    }

    /** Perpanjang/ubah batas waktu penugasan (null = tanpa batas). */
    public function ubahBatas(Request $request, PengelolaanAksesService $kelola, int $id)
    {
        $v = $request->validate(['berlaku_sampai' => ['nullable', 'date', 'after:today'], 'alasan' => self::ALASAN]);

        return $this->kelola(function () use ($kelola, $request, $id, $v) {
            $role = DB::table('N_HRIS_Role_User')->where('Id', $id)->value('Id_Role');
            $this->dalamModul($request, $role !== null ? $this->grupPeran((int) $role) : null, 'Peran ini bukan milik modul halaman ini.');

            return ResponseHelper::success($kelola->ubahBatas($request->user(), $id, $v['berlaku_sampai'] ?? null, trim($v['alasan'])), 'Batas waktu diperbarui.');
        });
    }

    public function beriGrant(Request $request, PengelolaanAksesService $kelola)
    {
        $v = $request->validate([
            'id_user' => ['required', 'integer'],
            'jenis_page' => ['required', 'string', 'max:100'],
            'berlaku_sampai' => ['nullable', 'date', 'after:today'],
            'alasan' => self::ALASAN,
        ]);

        return $this->kelola(function () use ($kelola, $request, $v) {
            $this->dalamModul($request, app(ModeAkses::class)->modulHalaman($v['jenis_page']), 'Halaman ini bukan milik modul halaman ini.');
            $kelola->beriGrant($request->user(), (int) $v['id_user'], $v['jenis_page'], $v['berlaku_sampai'] ?? null, trim($v['alasan']));

            return ResponseHelper::success(null, 'Akses halaman diberikan.');
        });
    }

    public function cabutGrant(Request $request, PengelolaanAksesService $kelola)
    {
        $v = $request->validate(['id_user' => ['required', 'integer'], 'jenis_page' => ['required', 'string', 'max:100'], 'alasan' => self::ALASAN]);

        return $this->kelola(function () use ($kelola, $request, $v) {
            $this->dalamModul($request, app(ModeAkses::class)->modulHalaman($v['jenis_page']), 'Halaman ini bukan milik modul halaman ini.');
            $kelola->cabutGrant($request->user(), (int) $v['id_user'], $v['jenis_page'], trim($v['alasan']));

            return ResponseHelper::success(null, 'Akses halaman dicabut.');
        });
    }

    public function ubahHak(Request $request, PengelolaanAksesService $kelola, int $id)
    {
        $v = $request->validate([
            'hak' => ['present', 'array'],
            'hak.*.jenis_page' => ['required', 'string', 'max:100'],
            'hak.*.aksi' => ['present', 'array'],
            'hak.*.aksi.*' => ['string', 'max:20'],
            'alasan' => self::ALASAN,
            'versi' => ['nullable', 'string', 'max:64'],
        ]);

        return $this->kelola(function () use ($kelola, $request, $id, $v) {
            $this->dalamModul($request, $this->grupPeran($id), 'Peran ini bukan milik modul halaman ini.');
            $kelola->ubahHak($request->user(), $id, $v['hak'], trim($v['alasan']), $v['versi'] ?? null);

            return ResponseHelper::success(null, 'Hak peran diperbarui.');
        });
    }

    public function pratinjau(Request $request, PengelolaanAksesService $kelola)
    {
        $v = $request->validate([
            'jenis' => ['required', 'in:penugasan,grant,hak'],
            'id_user' => ['nullable', 'integer'],
            'id_role' => ['nullable', 'integer'],
            'jenis_page' => ['nullable', 'string', 'max:100'],
            'hak' => ['nullable', 'array'],
        ]);

        return $this->kelola(function () use ($kelola, $request, $v) {
            if (! empty($v['id_role'])) {
                $this->dalamModul($request, $this->grupPeran((int) $v['id_role']), 'Peran ini bukan milik modul halaman ini.');
            } elseif (! empty($v['jenis_page'])) {
                $this->dalamModul($request, app(ModeAkses::class)->modulHalaman($v['jenis_page']), 'Halaman ini bukan milik modul halaman ini.');
            }

            return ResponseHelper::success($kelola->pratinjau($request->user(), $v), 'Pratinjau dampak.');
        });
    }
}
