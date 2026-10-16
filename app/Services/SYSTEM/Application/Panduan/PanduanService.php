<?php

namespace App\Services\SYSTEM\Application\Panduan;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Governance\Authorization\Akses\KeputusanAkses;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Manajemen panduan (manual guide) lintas modul (DDL-2026-146).
 *
 * Satu PDF bisa dipetakan ke banyak halaman (Jenis_page), masing-masing dengan
 * halaman mulai di PDF. Yang melihat panduan sebuah halaman adalah pengguna yang
 * BISA MEMBUKA halaman itu (hak akses halaman yang sudah ada); Id_Role opsional
 * mempersempitnya ke satu role. Modul & fitur tidak disimpan — keduanya ikut
 * dari Jenis_page (keputusan user 29 Sep 2026).
 *
 * Berkas disajikan lewat endpoint HCIS sendiri (streaming, bergerbang per
 * permintaan), bukan tautan GCS publik: aksesnya diperiksa tiap kali dibuka dan
 * penampil PDF di peramban tidak butuh CORS ke bucket.
 */
class PanduanService
{
    use BerkasPanduan;

    public const T_PANDUAN = 'N_HRIS_Panduan';

    public const T_HALAMAN = 'N_HRIS_Panduan_Halaman';

    /** Pembatas role opsional per panduan (DDL-2026-148). */
    public const T_ROLE = 'N_HRIS_Panduan_Role';

    public const DISK = 'gcs';

    /** Batas unggahan (KB) — manual modul bisa puluhan halaman bergambar. */
    public const MAKS_KB = 30720;

    private const CACHE_PEMETAAN = 'system.panduan.pemetaan';

    private const CACHE_ROLE = 'system.panduan.pemetaan-role';

    public function __construct(private PanduanVersiService $versi) {}

    // ---------------------------------------------------------------------
    // Sisi pengguna
    // ---------------------------------------------------------------------

    /**
     * Panduan yang boleh dilihat pengguna ini di satu halaman.
     *
     * @return list<array{id: int, judul: string, deskripsi: ?string, versi: ?string, halaman_mulai: int, baru: bool, diperbarui: ?string, catatan: ?string}>
     */
    public function untukHalaman(object $user, string $jenisPage): array
    {
        if (! $this->bolehBukaHalaman($user, $jenisPage)) {
            return [];
        }

        $roleUser = $this->roleAktif($user);

        return collect($this->pemetaanAktif()[$jenisPage] ?? [])
            ->filter(fn (array $p) => $this->lolosRole($p, $roleUser))
            ->unique('id')
            ->map(fn (array $p) => [
                'id' => $p['id'],
                'judul' => $p['judul'],
                'deskripsi' => $p['deskripsi'],
                'versi' => $p['versi'],
                'halaman_mulai' => $p['halaman_mulai'] ?? 1,
                // Tanda "Diperbarui": versi aktif berganti (terbit/rollback) dalam
                // HARI_TANDA_BARU hari terakhir dan panduan punya lebih dari satu versi.
                'baru' => $p['berganti_versi'] && $p['aktif_sejak'] !== null
                    && now()->diffInDays(Carbon::parse($p['aktif_sejak']), true) < PanduanVersiService::HARI_TANDA_BARU,
                'diperbarui' => $p['aktif_sejak'],
                'catatan' => $p['catatan'],
            ])
            ->values()
            ->all();
    }

    /**
     * Boleh-tidaknya pengguna membuka berkas satu panduan: ia harus melihatnya
     * di SETIDAKNYA satu halaman yang bisa ia buka (dan memegang salah satu
     * role pembatasnya bila diatur). Tanpa ini Id berurutan cukup untuk
     * mengunduh manual modul lain.
     */
    public function bolehBukaBerkas(object $user, int $idPanduan): bool
    {
        $roleUser = $this->roleAktif($user);

        foreach ($this->pemetaanAktif() as $jenisPage => $daftar) {
            foreach ($daftar as $p) {
                if ($p['id'] !== $idPanduan) {
                    continue;
                }
                if (! $this->lolosRole($p, $roleUser)) {
                    continue;
                }
                if ($this->bolehBukaHalaman($user, (string) $jenisPage)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array{path: string, nama: string}|null */
    public function berkas(int $idPanduan): ?array
    {
        $row = DB::table(self::T_PANDUAN)->where('Id_Panduan', $idPanduan)->where('Flag_Aktif', 'Y')->first();

        return $row ? ['path' => (string) $row->Path_File, 'nama' => (string) ($row->Nama_File_Asli ?: 'panduan.pdf')] : null;
    }

    /** Berkas panduan apa pun statusnya (pratinjau admin). */
    public function berkasAdmin(int $idPanduan): ?array
    {
        $row = DB::table(self::T_PANDUAN)->where('Id_Panduan', $idPanduan)->first();

        return $row ? ['path' => (string) $row->Path_File, 'nama' => (string) ($row->Nama_File_Asli ?: 'panduan.pdf')] : null;
    }

    /**
     * Respons PDF inline, di-stream dari disk. Satu tempat untuk pintu pengguna
     * dan pintu admin.
     *
     * @param  array{path: string, nama: string}  $berkas
     */
    public function sajikanPdf(array $berkas): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $stream = Storage::disk(self::DISK)->readStream($berkas['path']);
        } catch (\Throwable $e) {
            Log::error('Berkas panduan tidak bisa dibaca', ['path' => $berkas['path'], 'error' => $e->getMessage()]);
            $stream = null;
        }

        if (! $stream) {
            return \App\Helpers\ResponseHelper::errorWithCode('Berkas panduan tidak bisa dibuka.', 'SYSTEM_PANDUAN_FILE_MISSING', 404);
        }

        $nama = preg_replace('/[^\w .\-]/u', '_', $berkas['nama']) ?: 'panduan.pdf';

        return response()->stream(function () use ($stream) {
            fpassthru($stream);
            is_resource($stream) && fclose($stream);
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nama.'"',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ---------------------------------------------------------------------
    // Sisi admin (System Admin)
    // ---------------------------------------------------------------------

    public function daftar(?string $cari = null, ?string $status = null, ?string $kondisi = null): array
    {
        $jumlah = DB::table(self::T_HALAMAN)
            ->where('Flag_Aktif', 'Y')
            ->groupBy('Id_Panduan')
            ->selectRaw('Id_Panduan, COUNT(*) as n')
            ->pluck('n', 'Id_Panduan');
        $jumlahRole = $this->tabelRoleAda()
            ? DB::table(self::T_ROLE)->where('Flag_Aktif', 'Y')->groupBy('Id_Panduan')->selectRaw('Id_Panduan, COUNT(*) as n')->pluck('n', 'Id_Panduan')
            : collect();
        $adaVersi = $this->versi->tersedia();
        $noAktif = $adaVersi
            ? DB::table(self::T_PANDUAN.' as p')->join(PanduanVersiService::T_VERSI.' as v', 'v.Id_Versi', '=', 'p.Id_Versi_Aktif')->pluck('v.No_Versi', 'p.Id_Panduan')
            : collect();
        $jumlahDraf = $adaVersi
            ? DB::table(PanduanVersiService::T_VERSI)->where('Status', 'DRAF')->groupBy('Id_Panduan')->selectRaw('Id_Panduan, COUNT(*) as n')->pluck('n', 'Id_Panduan')
            : collect();

        $query = DB::table(self::T_PANDUAN)
            ->when(filled($cari), fn ($q) => $q->where(fn ($s) => $s
                ->where('Judul', 'like', '%'.$cari.'%')
                ->orWhere('Deskripsi', 'like', '%'.$cari.'%')
                ->orWhere('Versi', 'like', '%'.$cari.'%')
                ->orWhere('Nama_File_Asli', 'like', '%'.$cari.'%')))
            ->when($status === 'aktif' || $status === '1' || $status === 'true' || $status === 'Y', fn ($q) => $q->where('Flag_Aktif', 'Y'))
            ->when($status === 'nonaktif' || $status === '0' || $status === 'false' || $status === 'T', fn ($q) => $q->where('Flag_Aktif', 'T'))
            ->orderByDesc('Flag_Aktif')
            ->orderBy('Judul');

        $daftar = $query->get()->map(fn ($r) => [
            'id' => (int) $r->Id_Panduan,
            'judul' => $r->Judul,
            'deskripsi' => $r->Deskripsi,
            'versi' => $r->Versi,
            'nama_file' => $r->Nama_File_Asli,
            'ukuran_byte' => $r->Ukuran_Byte !== null ? (int) $r->Ukuran_Byte : null,
            'aktif' => $r->Flag_Aktif === 'Y',
            'jumlah_halaman_terpetakan' => (int) ($jumlah[$r->Id_Panduan] ?? 0),
            'jumlah_role' => (int) ($jumlahRole[$r->Id_Panduan] ?? 0),
            'no_versi' => isset($noAktif[$r->Id_Panduan]) ? (int) $noAktif[$r->Id_Panduan] : null,
            'jumlah_draf' => (int) ($jumlahDraf[$r->Id_Panduan] ?? 0),
            'diperbarui' => $r->Updated_At ?? $r->Created_At,
        ]);

        if ($kondisi === 'draf') {
            $daftar = $daftar->filter(fn ($p) => ($p['jumlah_draf'] ?? 0) > 0);
        } elseif ($kondisi === 'terpetakan') {
            $daftar = $daftar->filter(fn ($p) => ($p['jumlah_halaman_terpetakan'] ?? 0) > 0);
        } elseif ($kondisi === 'belum_terpetakan') {
            $daftar = $daftar->filter(fn ($p) => ($p['jumlah_halaman_terpetakan'] ?? 0) === 0);
        }

        return $daftar->values()->all();
    }

    /**
     * Panduan WAJIB dipasang di minimal satu halaman; role opsional membatasi
     * siapa yang melihatnya di halaman-halaman itu (keputusan user 29 Sep 2026).
     *
     * @param  array{judul: string, deskripsi?: ?string, versi?: ?string}  $data
     * @param  list<array{jenis_page: string, halaman_mulai?: ?int}>  $halaman  halaman yang memakai panduan ini
     * @param  list<int>  $role  pembatas role; kosong = semua yang bisa membuka halamannya
     */
    public function buat(array $data, UploadedFile $berkas, array $halaman = [], array $role = []): array
    {
        // Periksa sasaran SEBELUM mengunggah: jangan tinggalkan berkas yatim.
        [$halaman, $role] = $this->normalisasiSasaran($halaman, $role);
        $path = $this->unggah($berkas);

        try {
            $id = DB::transaction(function () use ($data, $berkas, $path, $halaman, $role) {
                $id = (int) DB::table(self::T_PANDUAN)->insertGetId([
                    'Judul' => trim($data['judul']),
                    'Deskripsi' => $this->teksAtauNull($data['deskripsi'] ?? null),
                    'Versi' => $this->teksAtauNull($data['versi'] ?? null),
                    'Path_File' => $path,
                    'Nama_File_Asli' => mb_substr($berkas->getClientOriginalName(), 0, 255),
                    'Ukuran_Byte' => $berkas->getSize(),
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $this->pelaku(),
                ], 'Id_Panduan');
                $this->versi->catatVersiPertama($id, $path, $berkas, $this->teksAtauNull($data['versi'] ?? null));
                $this->sinkronHalaman($id, $halaman);
                $this->sinkronRole($id, $role);

                return $id;
            });
        } catch (\Throwable $e) {
            $this->hapusBerkasDiam($path);
            throw $e;
        }

        $this->lupakanCache();

        return $this->satu($id);
    }

    /**
     * Ubah keterangan, halaman, dan role. Berkas TIDAK diubah di sini: berkas
     * baru selalu lewat PanduanVersiService (versi baru, riwayat tersimpan).
     *
     * @param  list<array{jenis_page: string, halaman_mulai?: ?int}>|null  $halaman  null (bersama $role null) = halaman & role tidak diubah
     * @param  list<int>|null  $role
     */
    public function ubah(int $id, array $data, ?array $halaman = null, ?array $role = null): array
    {
        $this->temukan($id);
        $aturSasaran = $halaman !== null || $role !== null;
        if ($aturSasaran) {
            [$halaman, $role] = $this->normalisasiSasaran($halaman ?? [], $role ?? []);
        }
        $ubah = [
            'Judul' => trim($data['judul']),
            'Deskripsi' => $this->teksAtauNull($data['deskripsi'] ?? null),
            'Updated_At' => now(),
            'Updated_By' => $this->pelaku(),
        ];

        DB::transaction(function () use ($id, $ubah, $aturSasaran, $halaman, $role) {
            DB::table(self::T_PANDUAN)->where('Id_Panduan', $id)->update($ubah);
            if ($aturSasaran) {
                $this->sinkronHalaman($id, $halaman);
                $this->sinkronRole($id, $role);
            }
        });

        $this->lupakanCache();

        return $this->satu($id);
    }

    public function ubahStatus(int $id, bool $aktif): array
    {
        $this->temukan($id);
        DB::table(self::T_PANDUAN)->where('Id_Panduan', $id)->update([
            'Flag_Aktif' => $aktif ? 'Y' : 'T',
            'Updated_At' => now(),
            'Updated_By' => $this->pelaku(),
        ]);
        $this->lupakanCache();

        return $this->satu($id);
    }

    /** Pemetaan satu panduan, berikut label halaman, modul, dan role. */
    public function daftarHalaman(int $idPanduan): array
    {
        $this->temukan($idPanduan);

        return DB::table(self::T_HALAMAN.' as ph')
            ->leftJoin('N_HRIS_Menus as m', function ($j) {
                $j->on('m.Jenis_page', '=', 'ph.Jenis_page')->where('m.Kode_Perusahaan', '001');
            })
            ->leftJoin('N_HRIS_Grup_Fitur_Map as gm', 'gm.Jenis_page', '=', 'ph.Jenis_page')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'gm.Id_Grup_Fitur')
            ->leftJoin('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'ph.Id_Role')
            ->where('ph.Id_Panduan', $idPanduan)
            ->orderBy('ph.Urutan_Tampil')
            ->orderBy('m.Nama_Menu')
            ->select(
                'ph.Id_Panduan_Halaman', 'ph.Jenis_page', 'ph.Halaman_Mulai', 'ph.Id_Role', 'ph.Urutan_Tampil', 'ph.Flag_Aktif',
                'm.Nama_Menu', 'm.Header_Label', 'g.Kode_Grup_Fitur', 'r.Role_Code', 'r.Role_Name',
            )
            ->get()
            ->unique('Id_Panduan_Halaman')
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Panduan_Halaman,
                'jenis_page' => $r->Jenis_page,
                'nama_halaman' => $r->Nama_Menu ?: $r->Jenis_page,
                'modul' => $r->Kode_Grup_Fitur,
                'fitur' => $r->Header_Label,
                'halaman_mulai' => $r->Halaman_Mulai !== null ? (int) $r->Halaman_Mulai : null,
                'id_role' => $r->Id_Role !== null ? (int) $r->Id_Role : null,
                'role' => $r->Role_Name ?: $r->Role_Code,
                'urutan' => (int) $r->Urutan_Tampil,
                'aktif' => $r->Flag_Aktif === 'Y',
            ])
            ->values()
            ->all();
    }

    /** Role pembatas satu panduan. */
    public function daftarRole(int $idPanduan): array
    {
        $this->temukan($idPanduan);
        if (! $this->tabelRoleAda()) {
            return [];
        }

        return DB::table(self::T_ROLE.' as pr')
            ->leftJoin('N_HRIS_Role_Functional as r', 'r.Id_Role', '=', 'pr.Id_Role')
            ->where('pr.Id_Panduan', $idPanduan)
            ->orderBy('pr.Urutan_Tampil')
            ->orderBy('r.Role_Name')
            ->get(['pr.Id_Panduan_Role', 'pr.Id_Role', 'pr.Halaman_Mulai', 'pr.Urutan_Tampil', 'pr.Flag_Aktif', 'r.Role_Code', 'r.Role_Name'])
            ->map(fn ($r) => [
                'id' => (int) $r->Id_Panduan_Role,
                'id_role' => (int) $r->Id_Role,
                'role' => $r->Role_Name ?: ($r->Role_Code ?: 'Role #'.$r->Id_Role),
                'kode' => $r->Role_Code,
                'aktif' => $r->Flag_Aktif === 'Y',
            ])
            ->all();
    }

    /**
     * Halaman dan role pembatas satu panduan untuk form ubah.
     *
     * @return array{halaman: array, role: array}
     */
    public function sasaran(int $idPanduan): array
    {
        return [
            'halaman' => $this->daftarHalaman($idPanduan),
            'role' => $this->daftarRole($idPanduan),
        ];
    }

    /**
     * Halaman wajib (minimal satu); role opsional.
     *
     * @return array{0: list<array>, 1: list<array>}
     */
    private function normalisasiSasaran(array $halaman, array $role): array
    {
        $halaman = $this->normalisasiHalaman($halaman);
        if (! $halaman) {
            throw new DomainException('Pilih minimal satu halaman yang memakai panduan ini.');
        }

        return [$halaman, $this->normalisasiRole($role)];
    }

    /**
     * @param  list<int|string>  $role
     * @return list<array{Id_Role: int, Urutan_Tampil: int}>
     */
    private function normalisasiRole(array $role): array
    {
        $hasil = [];
        foreach (array_values($role) as $i => $idRole) {
            if ($idRole === null || $idRole === '') {
                continue;
            }
            $hasil[(int) $idRole] ??= ['Id_Role' => (int) $idRole, 'Urutan_Tampil' => $i];
        }

        if ($hasil) {
            if (! $this->tabelRoleAda()) {
                throw new DomainException('Pembatas role belum tersedia di basis data ini (DDL-2026-148 belum dijalankan).');
            }
            if (DB::table('N_HRIS_Role_Functional')->whereIn('Id_Role', array_keys($hasil))->count() !== count($hasil)) {
                throw new DomainException('Role tidak dikenal.');
            }
        }

        return array_values($hasil);
    }

    /**
     * Samakan role pembatas dengan daftar dari form (pola sama dengan halaman).
     *
     * @param  list<array{Id_Role: int, Urutan_Tampil: int}>  $role
     */
    private function sinkronRole(int $idPanduan, array $role): void
    {
        if (! $this->tabelRoleAda()) {
            return; // normalisasiRole sudah menolak daftar berisi; kosong = tak ada yang disinkronkan
        }

        $tetap = DB::table(self::T_ROLE)
            ->where('Id_Panduan', $idPanduan)
            ->whereIn('Id_Role', array_column($role, 'Id_Role') ?: [0])
            ->get(['Id_Panduan_Role', 'Id_Role'])
            ->mapWithKeys(fn ($r) => [(int) $r->Id_Role => (int) $r->Id_Panduan_Role]);

        DB::table(self::T_ROLE)
            ->where('Id_Panduan', $idPanduan)
            ->whereNotIn('Id_Panduan_Role', $tetap->values()->all() ?: [0])
            ->delete();

        foreach ($role as $r) {
            $idBaris = $tetap->get($r['Id_Role']);
            if ($idBaris !== null) {
                DB::table(self::T_ROLE)
                    ->where('Id_Panduan_Role', $idBaris)
                    ->update($r + ['Flag_Aktif' => 'Y', 'Updated_At' => now(), 'Updated_By' => $this->pelaku()]);
            } else {
                DB::table(self::T_ROLE)->insert($r + [
                    'Id_Panduan' => $idPanduan,
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $this->pelaku(),
                ]);
            }
        }
    }

    /**
     * Periksa dan rapikan daftar halaman dari form: halaman harus ada di daftar
     * menu dan satu halaman hanya sekali. Pembatas role ada di tingkat panduan
     * (N_HRIS_Panduan_Role), jadi kolom Id_Role per halaman selalu NULL.
     *
     * @return list<array{Jenis_page: string, Halaman_Mulai: ?int, Id_Role: null, Urutan_Tampil: int}>
     */
    private function normalisasiHalaman(array $halaman): array
    {
        $hasil = [];
        foreach (array_values($halaman) as $i => $h) {
            $jenisPage = trim((string) ($h['jenis_page'] ?? ''));
            if ($jenisPage === '') {
                continue;
            }
            if (isset($hasil[$jenisPage])) {
                throw new DomainException("Halaman {$jenisPage} dipilih lebih dari sekali.");
            }
            $mulai = $h['halaman_mulai'] ?? null;
            $hasil[$jenisPage] = [
                'Jenis_page' => $jenisPage,
                'Halaman_Mulai' => $mulai === null || $mulai === '' ? null : max(1, (int) $mulai),
                'Id_Role' => null,
                'Urutan_Tampil' => (int) ($h['urutan'] ?? $i),
            ];
        }

        if ($hasil) {
            $dikenal = DB::table('N_HRIS_Menus')->whereIn('Jenis_page', array_keys($hasil))->distinct()->pluck('Jenis_page')->all();
            $asing = array_diff(array_keys($hasil), $dikenal);
            if ($asing) {
                throw new DomainException('Halaman tidak dikenal di daftar menu: '.implode(', ', $asing).'.');
            }
        }

        return array_values($hasil);
    }

    /**
     * Samakan pemetaan panduan dengan daftar dari form, per halaman: yang masih
     * dipilih diperbarui (jejak pembuatnya tetap), yang baru ditambahkan, yang
     * tidak lagi dipilih dihapus.
     *
     * @param  list<array{Jenis_page: string, Halaman_Mulai: ?int, Id_Role: ?int, Urutan_Tampil: int}>  $halaman
     */
    private function sinkronHalaman(int $idPanduan, array $halaman): void
    {
        // Satu baris lama per halaman yang masih dipilih; sisanya dibuang.
        $tetap = DB::table(self::T_HALAMAN)
            ->where('Id_Panduan', $idPanduan)
            ->whereIn('Jenis_page', array_column($halaman, 'Jenis_page') ?: [''])
            ->orderBy('Id_Panduan_Halaman')
            ->get(['Id_Panduan_Halaman', 'Jenis_page'])
            ->unique('Jenis_page')
            ->mapWithKeys(fn ($r) => [$r->Jenis_page => (int) $r->Id_Panduan_Halaman]);

        DB::table(self::T_HALAMAN)
            ->where('Id_Panduan', $idPanduan)
            ->whereNotIn('Id_Panduan_Halaman', $tetap->values()->all() ?: [0])
            ->delete();

        foreach ($halaman as $h) {
            $idBaris = $tetap->get($h['Jenis_page']);
            if ($idBaris !== null) {
                DB::table(self::T_HALAMAN)
                    ->where('Id_Panduan_Halaman', $idBaris)
                    ->update($h + ['Flag_Aktif' => 'Y', 'Updated_At' => now(), 'Updated_By' => $this->pelaku()]);
            } else {
                DB::table(self::T_HALAMAN)->insert($h + [
                    'Id_Panduan' => $idPanduan,
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $this->pelaku(),
                ]);
            }
        }
    }

    /** Pilihan untuk editor sasaran: halaman menu (berikut modul) dan role aktif. */
    public function opsi(): array
    {
        $halaman = DB::table('N_HRIS_Menus as m')
            ->leftJoin('N_HRIS_Grup_Fitur_Map as gm', 'gm.Jenis_page', '=', 'm.Jenis_page')
            ->leftJoin('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'gm.Id_Grup_Fitur')
            ->where('m.Kode_Perusahaan', '001')
            ->whereNotNull('m.Url_Menu')
            ->orderBy('g.Kode_Grup_Fitur')
            ->orderBy('m.Header_Label')
            ->orderBy('m.Nama_Menu')
            ->get(['m.Jenis_page', 'm.Nama_Menu', 'm.Header_Label', 'g.Kode_Grup_Fitur'])
            ->unique('Jenis_page')
            ->map(fn ($r) => [
                'value' => $r->Jenis_page,
                'label' => $r->Nama_Menu ?: $r->Jenis_page,
                'modul' => $r->Kode_Grup_Fitur ?: '-',
                'fitur' => $r->Header_Label,
            ])
            ->values()
            ->all();

        $role = DB::table('N_HRIS_Role_Functional')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Role_Name')
            ->get(['Id_Role', 'Role_Code', 'Role_Name'])
            ->map(fn ($r) => ['value' => (int) $r->Id_Role, 'label' => $r->Role_Name ?: $r->Role_Code, 'kode' => $r->Role_Code])
            ->all();

        return ['halaman' => $halaman, 'role' => $role, 'role_tersedia' => $this->tabelRoleAda()];
    }

    // ---------------------------------------------------------------------
    // Pembantu
    // ---------------------------------------------------------------------

    /**
     * Seluruh pemetaan aktif (panduan aktif), dikelompokkan per Jenis_page.
     * Di-cache: dibaca di setiap perpindahan halaman oleh tombol Panduan.
     *
     * @return array<string, list<array{id: int, judul: string, deskripsi: ?string, versi: ?string, halaman_mulai: ?int, id_role: ?int}>>
     */
    private function pemetaanAktif(): array
    {
        try {
            return $this->bacaPemetaanAktif();
        } catch (QueryException $e) {
            // Tabel belum dibuat (DDL-2026-146 belum dijalankan di lingkungan ini):
            // topbar di SEMUA halaman memanggil ini, jadi jangan sampai 500.
            Log::warning('Pemetaan panduan tidak terbaca', ['error' => $e->getMessage()]);

            return [];
        }
    }

    private function bacaPemetaanAktif(): array
    {
        return Cache::remember(self::CACHE_PEMETAAN, 600, function () {
            $adaVersi = $this->versi->tersedia();

            // Info versi aktif per panduan (tanda "Diperbarui" + catatan perubahan).
            $infoVersi = $adaVersi
                ? DB::table(self::T_PANDUAN.' as p')
                    ->leftJoin(PanduanVersiService::T_VERSI.' as v', 'v.Id_Versi', '=', 'p.Id_Versi_Aktif')
                    ->where('p.Flag_Aktif', 'Y')
                    ->selectRaw('p.Id_Panduan, p.Versi_Aktif_Sejak, v.Catatan_Perubahan, (SELECT COUNT(*) FROM '
                        .PanduanVersiService::T_VERSI." x WHERE x.Id_Panduan = p.Id_Panduan AND x.Status = 'TERBIT') as jumlah_terbit")
                    ->get()
                    ->keyBy(fn ($r) => (int) $r->Id_Panduan)
                : collect();

            return DB::table(self::T_HALAMAN.' as ph')
                ->join(self::T_PANDUAN.' as p', 'p.Id_Panduan', '=', 'ph.Id_Panduan')
                ->where('ph.Flag_Aktif', 'Y')
                ->where('p.Flag_Aktif', 'Y')
                ->orderBy('ph.Urutan_Tampil')
                ->orderBy('p.Judul')
                ->get(['ph.Jenis_page', 'ph.Halaman_Mulai', 'ph.Id_Role', 'p.Id_Panduan', 'p.Judul', 'p.Deskripsi', 'p.Versi'])
                ->groupBy('Jenis_page')
                ->map(fn ($baris) => $baris->map(function ($r) use ($infoVersi) {
                    $v = $infoVersi->get((int) $r->Id_Panduan);

                    return [
                        'id' => (int) $r->Id_Panduan,
                        'judul' => (string) $r->Judul,
                        'deskripsi' => $r->Deskripsi,
                        'versi' => $r->Versi,
                        'halaman_mulai' => $r->Halaman_Mulai !== null ? (int) $r->Halaman_Mulai : null,
                        'id_role' => $r->Id_Role !== null ? (int) $r->Id_Role : null,
                        'aktif_sejak' => $v?->Versi_Aktif_Sejak !== null ? Carbon::parse($v->Versi_Aktif_Sejak)->toIso8601String() : null,
                        'catatan' => $v?->Catatan_Perubahan,
                        'berganti_versi' => $v !== null && (int) $v->jumlah_terbit > 1,
                    ];
                })->values()->all())
                ->all();
        });
    }

    /**
     * Role pembatas per panduan: Id_Panduan => list Id_Role. Panduan tanpa
     * entri = tanpa pembatas. Di-cache bersama pemetaan halaman.
     *
     * @return array<int, list<int>>
     */
    private function rolePembatas(): array
    {
        try {
            return Cache::remember(self::CACHE_ROLE, 600, fn () => DB::table(self::T_ROLE)
                ->where('Flag_Aktif', 'Y')
                ->get(['Id_Panduan', 'Id_Role'])
                ->groupBy(fn ($r) => (int) $r->Id_Panduan)
                ->map(fn ($baris) => $baris->map(fn ($r) => (int) $r->Id_Role)->values()->all())
                ->all());
        } catch (QueryException $e) {
            // DDL-2026-148 belum dijalankan: belum ada pembatas role, bukan galat.
            Log::warning('Pembatas role panduan tidak terbaca', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Lolos saringan role: tanpa pembatas, atau pengguna memegang salah satu
     * role pembatas panduan. `id_role` per halaman (kolom lama DDL-146) tetap
     * dihormati bila kebetulan terisi.
     *
     * @param  array{id: int, id_role?: ?int}  $p
     * @param  list<int>  $roleUser
     */
    private function lolosRole(array $p, array $roleUser): bool
    {
        if (($p['id_role'] ?? null) !== null && ! in_array($p['id_role'], $roleUser, true)) {
            return false;
        }
        $pembatas = $this->rolePembatas()[$p['id']] ?? [];

        return ! $pembatas || array_intersect($pembatas, $roleUser) !== [];
    }

    private function tabelRoleAda(): bool
    {
        return Schema::hasTable(self::T_ROLE);
    }

    public function lupakanCache(): void
    {
        self::lupakanCacheSemua();
    }

    public static function lupakanCacheSemua(): void
    {
        Cache::forget(self::CACHE_PEMETAAN);
        Cache::forget(self::CACHE_ROLE);
    }

    private function bolehBukaHalaman(object $user, string $jenisPage): bool
    {
        return $user instanceof User && app(KeputusanAkses::class)->boleh($user, $jenisPage);
    }

    /** @return list<int> */
    private function roleAktif(object $user): array
    {
        $idWeb = $user->Id_Users ?? null;
        if ($idWeb === null) {
            return [];
        }

        return DB::table('N_HRIS_Role_User as ru')
            ->join('N_HRIS_Role_Functional as rf', 'rf.Id_Role', '=', 'ru.Id_Role')
            ->where('ru.UserId_Web', $idWeb)
            ->where('ru.Flag_Aktif', 'Y')
            ->where('rf.Flag_Aktif', 'Y')
            ->pluck('ru.Id_Role')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private function temukan(int $id): object
    {
        $row = DB::table(self::T_PANDUAN)->where('Id_Panduan', $id)->first();
        if (! $row) {
            throw new DomainException('Panduan tidak ditemukan.');
        }

        return $row;
    }

    private function satu(int $id): array
    {
        return collect($this->daftar())->firstWhere('id', $id) ?? [];
    }
}
