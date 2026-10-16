<?php

namespace App\Services\SYSTEM\Governance\Approval\Shared;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Satu rumah untuk aturan kedaluwarsa approval izin, cuti, dan terlambat/pulang cepat.
 *
 * ── Kenapa dipusatkan ────────────────────────────────────────────────────────
 *
 * Aturan yang sama dijalankan di beberapa tempat dengan pembacaan yang SALING
 * BERTENTANGAN atas satu nilai konfigurasi yang sama:
 *
 *   parseFlagApproval()  (disalin identik di IzinAtasanApprovalController dan
 *                        AbsensiController, 1.209 byte sama persis)
 *       batas null  ->  TIDAK ADA kedaluwarsa; flag dikembalikan apa adanya
 *
 *   AbsensiController, saat MENULIS Expired_At untuk cuti
 *       (int) (config(...batas_approve_cuti) ?? 0)   ->  batas 0 HARI
 *       Carbon::now()->addDays(0)                    ->  kedaluwarsa SEKETIKA
 *
 * Dan `batas_approve_cuti` memang null: config/services.php mengembalikan null
 * bila HCIS_APPROVE_CUTI_LIMIT tidak diset, dan di pengembangan ia tidak diset.
 * Jadi satu jalur bilang "cuti tidak pernah kedaluwarsa" sementara jalur lain
 * menuliskannya kedaluwarsa pada detik pengajuan dibuat.
 *
 * ── Mana yang benar, dan dari mana buktinya ──────────────────────────────────
 *
 * Data yang sudah ada memutuskannya: 122 baris approval bernomor CT di
 * HRIS_Approval_Request, SELURUHNYA ber-Expired_At NULL. Nol baris kedaluwarsa
 * seketika. Artinya jalur "tanpa batas" itulah yang sungguh berlaku, dan
 * addDays(0) adalah anomali yang belum pernah menghasilkan baris.
 *
 * Karena itu kedaluwarsaPada() mengembalikan NULL bila batasnya tidak diset —
 * bukan "sekarang". Kolom Expired_At yang NULL dibaca sebagai "tidak
 * kedaluwarsa" oleh SEMUA pembacanya (mis. AllTransactionsReadService,
 * HomepagePersonalDashboardService, VisitorApprovalPolicyService), jadi bentuk
 * itu memang bahasa yang sudah dipahami sistem.
 *
 * ── Kenapa endOfDay() ───────────────────────────────────────────────────────
 *
 * ApprovalFlowService — penulis approval terpusat — memakai
 * now()->addDays($limit)->endOfDay(). AbsensiController memakai addDays() tanpa
 * endOfDay, dan bekasnya terlihat di data: baris IS dan TP ber-Expired_At pada
 * jam sembarang (06:22:12, 15:12:33, …), sedangkan baris PM dan VS tepat pada
 * 00:00:00 — 23:59:59.999 yang dibulatkan tipe datetime SQL Server ke tengah
 * malam berikutnya.
 *
 * Kelas ini mengikuti yang terpusat. Akibatnya kedaluwarsa izin/TP bergeser
 * MAJU sampai satu hari (lebih longgar, tidak pernah lebih ketat), dan seluruh
 * seri akhirnya berbentuk sama.
 */
class AturanKedaluwarsaApproval
{
    /**
     * Batas hari untuk sebuah jenis transaksi; null berarti TIDAK ADA batas.
     *
     * Nol dan negatif juga dibaca sebagai "tidak ada batas", bukan "kedaluwarsa
     * seketika": ambang nol tidak pernah berarti kebijakan yang sengaja dipilih
     * di sistem ini — ia selalu jejak konfigurasi yang tidak diset.
     *
     * Ini MENYERAGAMKAN satu ketimpangan pada kode asal: cabang cuti memakai
     * perbandingan longgar ($batas == null, sehingga 0 ikut berarti "tanpa
     * batas") sedangkan cabang izin memakai perbandingan ketat ($batas === null,
     * sehingga 0 berarti "semua kedaluwarsa seketika"). Bedanya hanya muncul
     * bila HCIS_APPROVE_IZIN_LIMIT diset 0 — bacaan cuti yang dipilih, karena
     * bacaan satunya mengubah setiap pengajuan izin jadi kedaluwarsa.
     */
    public static function batasHari(?string $jenis): ?int
    {
        $kunci = self::adalahCuti($jenis)
            ? 'services.hcis.batas_approve_cuti'
            : 'services.hcis.batas_approve_izin';

        $nilai = config($kunci);
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $hari = (int) $nilai;

        return $hari > 0 ? $hari : null;
    }

    /**
     * Kapan approval jenis ini kedaluwarsa bila diajukan pada $saat;
     * null bila tidak pernah kedaluwarsa.
     */
    public static function kedaluwarsaPada(?string $jenis, ?CarbonInterface $saat = null): ?Carbon
    {
        $hari = self::batasHari($jenis);
        if ($hari === null) {
            return null;
        }

        return ($saat ? Carbon::instance($saat) : Carbon::now())->copy()->addDays($hari)->endOfDay();
    }

    /**
     * Terjemahkan Flag_Approval mentah menjadi status yang dipakai tampilan,
     * dengan memperhitungkan umur pengajuan.
     *
     * Urutan keputusannya dipertahankan apa adanya dari parseFlagApproval:
     *   1. tanpa batas             -> flag apa adanya (kosong jadi 'N')
     *   2. sudah disetujui ('Y')   -> 'Y', umur tidak lagi relevan
     *   3. masih kosong & lewat    -> 'P' (kedaluwarsa oleh sistem)
     *   4. sudah ada isinya        -> flag apa adanya
     */
    public static function statusFlag(?string $flag, mixed $tanggal, ?string $jenis): string
    {
        $hari = self::batasHari($jenis);

        /*
         * Urutan keputusan dipertahankan PERSIS seperti parseFlagApproval,
         * termasuk dua kehalusannya:
         *
         *   - `$flag ?? 'N'` hanya menangkap null. Flag berupa string KOSONG
         *     dikembalikan sebagai string kosong, bukan 'N'. Tampilan di hilir
         *     membedakan keduanya, jadi menormalkannya di sini akan mengubah
         *     apa yang dilihat pengguna tanpa ada yang meminta.
         *   - empty() dipakai apa adanya, bukan perbandingan eksplisit.
         */
        if ($hari === null) {
            return $flag ?? 'N';
        }

        /*
         * Cabang ini REDUNDAN, dan dipertahankan apa adanya. Flag 'Y' toh lolos
         * ke `! empty($flag)` di bawah dan dikembalikan sama saja; tidak ada
         * masukan yang membedakan keduanya. Dibiarkan karena memindahkan kode
         * bukan tempatnya menghapus cabang — tetapi dicatat supaya pembaca
         * berikutnya tidak mengira ia menanggung sesuatu.
         */
        if ($flag === 'Y') {
            return 'Y';
        }

        /*
         * diffInDays(..., false) menahan tandanya: pengajuan hari ini bernilai 0
         * dan pengajuan bertanggal depan bernilai negatif — keduanya belum lewat.
         */
        $umur = Carbon::parse($tanggal)->diffInDays(Carbon::now(), false);

        if (empty($flag) && $umur >= $hari) {
            return 'P';
        }

        if (! empty($flag)) {
            return $flag;
        }

        return $flag ?? 'N';
    }

    private static function adalahCuti(?string $jenis): bool
    {
        return strtolower(trim((string) $jenis)) === 'cuti';
    }
}
