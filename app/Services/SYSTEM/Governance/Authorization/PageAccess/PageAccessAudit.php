<?php

namespace App\Services\SYSTEM\Governance\Authorization\PageAccess;

use App\Models\ESS\Employee\Models\User;
use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use App\Services\SYSTEM\Audit\Numbering\AdminLogNumberService;
use Illuminate\Support\Facades\DB;

/**
 * Jejak Page Access Setup ke HRIS_Transaksi_Log_Admin(_Detail/_Det): satu
 * header, satu detail per karyawan yang berubah, satu det per (karyawan,
 * halaman) dengan nilai SEBELUM dan SESUDAH (Y/T). Dipanggil di dalam transaksi updateAccess — gagal mencatat berarti
 * perubahan aksesnya ikut batal.
 */
class PageAccessAudit
{
    public const JENIS = 'PAGE_ACCESS';

    /**
     * @param  array<int, list<string>>  $sebelum
     * @param  array<int, list<string>>  $sesudah
     */
    public function catat(User $admin, string $aksi, array $jenisHalaman, array $sebelum, array $sesudah): string
    {
        $no = app(AdminLogNumberService::class)->generate();
        $now = now();
        $ids = array_values(array_unique(array_map('intval', array_merge(array_keys($sebelum), array_keys($sesudah)))));

        $kodeAdmin = DB::table('Karyawan')->where('UserID_Web', $admin->Id_Users)->value('Kode_Karyawan')
            ?? (string) ($admin->Username ?? $admin->Id_Users);
        DB::table('HRIS_Transaksi_Log_Admin')->insert([
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $no, 'Tanggal' => $now,
            'Jam' => $now->format('H:i'), 'UserID' => $kodeAdmin,
        ]);

        $kode = [];
        foreach (array_chunk($ids, 500) as $p) {
            $kode += DB::table('Karyawan')->whereIn('UserID_Web', $p)->pluck('Kode_Karyawan', 'UserID_Web')->all();
        }

        $deskripsi = mb_substr(strtoupper($aksi).' '.implode(',', $jenisHalaman), 0, 255);
        PenyisipanBerbatasParameter::sisipkan('HRIS_Transaksi_Log_Admin_Detail', array_map(fn ($id) => [
            'Kode_Perusahaan' => '001', 'No_Transaksi' => $no,
            'Kode_Karyawan' => mb_substr((string) ($kode[$id] ?? $id), 0, 20),
            'Jenis' => self::JENIS, 'Deskripsi' => $deskripsi,
        ], $ids));

        $urut = DB::table('HRIS_Transaksi_Log_Admin_Detail')->where('No_Transaksi', $no)
            ->pluck('Urut_Oto', 'Kode_Karyawan')->all();

        // Satu baris per (karyawan, halaman): nilai Y/T sebelum dan sesudah.
        // Bukan daftar halaman yang digabung — kolomnya hanya 255 karakter
        // dan pemberian belasan halaman akan terpotong diam-diam.
        $baris = [];
        foreach ($ids as $id) {
            $urutDetail = $urut[mb_substr((string) ($kode[$id] ?? $id), 0, 20)] ?? null;
            foreach ($jenisHalaman as $page) {
                $baris[] = [
                    'Kode_Perusahaan' => '001', 'No_Transaksi' => $no,
                    'Field_Diubah' => mb_substr('HRIS_Page_Access.'.$page, 0, 255), 'Deskripsi' => $deskripsi,
                    'Nilai_Awal' => in_array($page, $sebelum[$id] ?? [], true) ? 'Y' : 'T',
                    'Nilai_Setelah' => in_array($page, $sesudah[$id] ?? [], true) ? 'Y' : 'T',
                    'Urut_Detail' => $urutDetail,
                ];
            }
        }
        PenyisipanBerbatasParameter::sisipkan('HRIS_Transaksi_Log_Admin_Det', $baris);

        return $no;
    }
}
