<?php

namespace App\Services\KPI;

use Carbon\Carbon;

/**
 * Satu-satunya penentu "siapa yang masih pegawai untuk periode KPI ini".
 *
 * ── Kenapa kelas ini ada ────────────────────────────────────────────────────
 *
 * Pertanyaan "siapa yang wajib mengisi KPI bulan X" sebelumnya dijawab di tiga
 * tempat dengan tiga hasil berbeda:
 *
 *   - universe monitoring  -> `k.Aktif = 'Y'`   (status HARI INI)
 *   - kandidat Auto Cap    -> tidak menyaring kepegawaian sama sekali
 *   - sasaran notifikasi   -> tidak menyaring kepegawaian sama sekali
 *
 * Diukur di produksi untuk Juli 2026 (16 Sep 2026): Auto Cap memulangkan 121
 * orang, monitoring 90. Seluruh selisihnya karyawan non-aktif — 16 resign
 * SEBELUM Juli (tidak boleh di-enforce sama sekali), 9 resign di tengah Juli,
 * dan 10 resign baru Agustus. Yang sepuluh terakhir bekerja penuh sepanjang
 * Juli, 24 dari kelompok non-aktif itu bahkan tercatat mengisi KPI Mei/Juni,
 * tetapi mereka lenyap dari laporan Juli semata-mata karena `k.Aktif` membaca
 * status hari ini. Artinya laporan Juli menyusut sendiri setiap ada orang
 * resign — angka historis yang berubah surut tanpa jejak.
 *
 * ── Aturannya ───────────────────────────────────────────────────────────────
 *
 * Seseorang masih pegawai untuk sebuah periode bila ia masih aktif SEKARANG,
 * atau tanggal resign-nya jatuh pada/sesudah HARI TERAKHIR periode itu. Dengan
 * kata lain: ia harus bekerja SEPANJANG periode yang dinilai.
 *
 * Keputusan pemilik proses (16 Sep 2026): yang resign di TENGAH periode —
 * misalnya 15 Juli — tidak perlu lagi ditampilkan untuk Juli, karena bulannya
 * tidak ia jalani penuh. Yang resign tepat pada hari terakhir tetap masuk: ia
 * bekerja penuh, dan angkanya tetap dibutuhkan.
 *
 * Non-aktif TANPA `Tanggal_Resign` sengaja diperlakukan sebagai bukan pegawai:
 * tanpa tanggal, ia tidak bisa ditempatkan di sumbu waktu, dan menebak-nebak
 * di sini berarti menaruh orang yang salah ke dalam daftar yang bisa di-enforce.
 *
 * ── Kenapa batasnya diambil dari periode TERAWAL dalam scope ────────────────
 *
 * Scope monitoring tidak selalu satu bulan: submission bekerja per TAHUN dan
 * mode YTD bekerja atas DAFTAR bulan Jan..N. Untuk scope gabungan seperti itu,
 * menuntut orangnya bertahan sampai akhir rentang akan menghapus justru orang
 * yang angkanya sudah masuk ke dalam angka gabungan tersebut — cacat yang sama
 * yang dijaga PerformanceTrackingYtdTest. Karena itu batasnya adalah akhir
 * periode TERAWAL: cukup satu periode utuh untuk tetap tercatat, dan untuk
 * scope satu bulan (Performance Tracking & Auto Cap) ia otomatis berarti
 * "harus bekerja sepanjang bulan itu".
 *
 * ── Yang SENGAJA tidak memakai aturan ini ───────────────────────────────────
 *
 * Notifikasi (`kpi:notif-update`) memakai `Aktif='Y'` polos. Sebuah pengingat
 * adalah tindakan kepada orang SEKARANG, bukan laporan tentang sebuah periode:
 * mengirim WhatsApp ke mantan karyawan tidak menjadi benar hanya karena
 * periodenya benar.
 */
final class KpiKepegawaianPeriode
{
    /**
     * Terapkan penyaring "masih pegawai sepanjang periode" ke builder mana pun
     * yang sudah menyertakan tabel Karyawan dengan alias $alias.
     *
     * @template TQuery of \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     *
     * @param  TQuery  $query
     * @param  Carbon  $batas  hari terakhir periode yang harus dijalani penuh
     * @return TQuery
     */
    public static function terapkan($query, Carbon $batas, string $alias = 'k')
    {
        $batas = $batas->copy()->startOfDay();

        return $query->where(function ($q) use ($alias, $batas) {
            $q->where($alias.'.Aktif', 'Y')
                ->orWhere(function ($w) use ($alias, $batas) {
                    $w->whereNotNull($alias.'.Tanggal_Resign')
                        ->where($alias.'.Tanggal_Resign', '>=', $batas);
                });
        });
    }

    /**
     * Hari TERAKHIR dari periode terawal sebuah scope monitoring/export.
     *
     * Menerima bentuk yang benar-benar dipakai pemanggil:
     *   '2026'        (scope submission = tahun)      -> 2026-01-31
     *   '2026-07'     (scope penilaian = bulan)       -> 2026-07-31
     *   '2026-07-22'  (sudah tanggal penuh)           -> 2026-07-31
     *   ['2026-01', …, '2026-08']  (scope YTD)        -> 2026-01-31
     *
     * @param  string|array<int,string>  $scope
     */
    public static function batasDariScope(string|array $scope): Carbon
    {
        if (is_array($scope)) {
            $nilai = array_values(array_filter($scope, fn ($v) => $v !== null && $v !== ''));
            sort($nilai);
            $scope = $nilai[0] ?? '';
        }

        $scope = trim((string) $scope);

        if ($scope === '') {
            // Scope tak terbaca: mundurkan batas sejauh mungkin supaya penyaring
            // ini tidak pernah MENGHILANGKAN orang secara diam-diam. Menyempit
            // karena salah baca jauh lebih berbahaya daripada melebar.
            return Carbon::create(1900, 1, 1)->endOfMonth()->startOfDay();
        }

        if (preg_match('/^\d{4}$/', $scope)) {
            return Carbon::create((int) $scope, 1, 1)->endOfMonth()->startOfDay();
        }

        if (preg_match('/^(\d{4})-(\d{2})$/', $scope, $m)) {
            return Carbon::create((int) $m[1], (int) $m[2], 1)->endOfMonth()->startOfDay();
        }

        return Carbon::parse($scope)->endOfMonth()->startOfDay();
    }
}
