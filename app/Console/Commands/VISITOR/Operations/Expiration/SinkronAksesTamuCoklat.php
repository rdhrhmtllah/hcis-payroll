<?php

namespace App\Console\Commands\VISITOR\Operations\Expiration;

use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menyelaraskan akses gerbang tamu dengan jendela permit COKLAT-nya — sekali
 * jalan, untuk data yang disetujui SEBELUM aturan 29 Sep 2026.
 *
 * Sejak aturan itu, syncPermitEfektif() ikut menyinkronkan baris tamunya dan
 * N_VISITOR_Tamu_Efektif memakai jendela permit yang disetujui admin
 * (VisitorEfektifService::jendelaAksesTamu). Permit yang disetujui sebelumnya
 * masih membawa jendela estimasi kunjungan, sehingga kunjungan yang izinnya
 * diperpanjang admin terbaca "batal" dan akses gerbang tamunya berakhir
 * terlalu cepat.
 *
 * Cakupan (sempit dan bisa diulang):
 *  - tabel yang ditulis: N_VISITOR_Tamu_Efektif saja, lewat syncTamuEfektif();
 *  - kunjungan dengan permit COKLAT aktif & disetujui yang jendelanya BELUM
 *    berakhir, dan jendela akses tamunya masih berbeda dari jendela permit.
 *
 * Jalankan dengan --dry-run lebih dulu untuk melihat daftar dan jumlahnya;
 * --kunjungan=VSR... membatasi ke baris yang sudah diperiksa. Baris akses
 * yang sudah EXPIRED akan HIDUP LAGI bila jendela permitnya belum habis —
 * periksa daftarnya sebelum menjalankan tanpa --kunjungan.
 */
class SinkronAksesTamuCoklat extends Command
{
    protected $signature = 'visitor:sinkron-akses-tamu-coklat
        {--dry-run : Tampilkan saja, tanpa menulis}
        {--kunjungan=* : Batasi ke nomor kunjungan tertentu (boleh berulang)}';

    protected $description = 'Selaraskan jendela akses gerbang tamu dengan jendela permit COKLAT yang disetujui (sekali jalan).';

    public function handle(VisitorEfektifService $efektif): int
    {
        $baris = DB::table('N_EMI_VISITOR_Transaksi_Permit as p')
            ->join('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'p.No_Transaksi')
            ->join('N_VISITOR_Tamu_Efektif as te', 'te.No_Transaksi', '=', 'p.No_Transaksi_Tamu')
            ->whereNotNull('p.No_Transaksi_Tamu')
            ->where('p.Flag_Aktif', 'Y')
            ->where('p.Flag_Approval', 'Y')
            ->whereNotNull('d.Waktu_Mulai_Berlaku')
            ->whereNotNull('d.Waktu_Berakhir')
            ->where('d.Waktu_Berakhir', '>=', now())
            ->where(function ($q) {
                $q->whereNull('te.Waktu_Estimasi_Keluar')
                    ->orWhereColumn('te.Waktu_Estimasi_Keluar', '<>', 'd.Waktu_Berakhir')
                    ->orWhereNull('te.Waktu_Estimasi_Masuk')
                    ->orWhereColumn('te.Waktu_Estimasi_Masuk', '<>', 'd.Waktu_Mulai_Berlaku');
            })
            ->when($this->option('kunjungan'), fn ($q, $nomor) => $q->whereIn('p.No_Transaksi_Tamu', $nomor))
            ->orderBy('p.No_Transaksi_Tamu')
            ->get([
                'p.No_Transaksi as Permit',
                'p.No_Transaksi_Tamu as Kunjungan',
                'te.Status_Efektif as Status_Akses',
                'te.Waktu_Estimasi_Masuk as Akses_Mulai',
                'te.Waktu_Estimasi_Keluar as Akses_Berakhir',
                'd.Waktu_Mulai_Berlaku as Permit_Mulai',
                'd.Waktu_Berakhir as Permit_Berakhir',
            ]);

        $this->table(
            ['Permit', 'Kunjungan', 'Status akses', 'Akses sekarang', 'Menjadi (jendela permit)'],
            $baris->map(fn ($r) => [
                $r->Permit,
                $r->Kunjungan,
                $r->Status_Akses,
                substr((string) $r->Akses_Mulai, 0, 16).' s.d. '.substr((string) $r->Akses_Berakhir, 0, 16),
                substr((string) $r->Permit_Mulai, 0, 16).' s.d. '.substr((string) $r->Permit_Berakhir, 0, 16),
            ])->all(),
        );
        $this->info($baris->count().' kunjungan perlu diselaraskan.');

        if ($this->option('dry-run') || $baris->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($baris as $r) {
            $efektif->syncTamuEfektif($r->Kunjungan);
        }

        $this->info('Selesai: '.$baris->count().' baris N_VISITOR_Tamu_Efektif diselaraskan.');

        return self::SUCCESS;
    }
}
