<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;

/**
 * Penonaktifan Dokumen — DDL-2026-055.
 *
 * APA YANG PENONAKTIFAN LAKUKAN, DAN APA YANG TIDAK. Ia menutup dokumen bagi
 * pemakaian BARU: dokumen berhenti ditawarkan di pemilih Document Builder,
 * sehingga tak bisa lagi dipasang ke paket. Ia TIDAK menyentuh item paket,
 * TIDAK mencabut penjadwalan, dan TIDAK menutup progres siapa pun. Kewajiban
 * yang sudah terbit tetap berjalan sampai adminnya melepas sendiri.
 *
 * Karena itu Flag_Aktif TIDAK dibalik. Flag_Aktif dokumen ditanyakan 28 kueri,
 * tiga di antaranya jalur baca karyawan (SopProgressReadService,
 * SopRepositoryReadService, SopJangkauanPublikService); membaliknya akan
 * mencabut dokumen dari paket yang sedang berjalan — persis kebalikan dari
 * keputusan di atas. Statusnya hidup di Nonaktif_At: NULL berarti aktif.
 *
 * Polanya bukan hal baru. Pemilih dokumen di Builder sudah memakainya untuk
 * tipe yang dimatikan: yang tertutup adalah pembuatan, bukan yang terlanjur
 * terpasang.
 */
class SopDokumenNonaktifService
{
    /** Ambang panjang catatan. Cukup untuk satu kalimat sebab yang bisa dibaca orang lain enam bulan lagi. */
    public const CATATAN_MIN = 10;

    public const CATATAN_MAKS = 500;

    public function __construct(private SopDokumenFanoutService $fanout) {}

    /**
     * Sebaran dokumen SAAT INI: berapa paket, berapa orang, berapa penugasan,
     * dan paket mana saja.
     *
     * ORANG dan PENUGASAN sengaja dua angka, bukan satu. Seorang karyawan bisa
     * memegang dokumen yang sama lewat dua paket; menjumlahkan hitungan
     * per-paket akan menghitungnya dua kali dan melaporkan jangkauan yang lebih
     * besar daripada kenyataannya. `orang` adalah kepala yang berbeda,
     * `penugasan` adalah penempatannya. Ketika keduanya berbeda, layar wajib
     * menyebutkan keduanya.
     */
    public function sebaran(int $idDokumen): array
    {
        $idPaket = $this->fanout->paketPerujuk($idDokumen);

        if ($idPaket === []) {
            return ['paket' => 0, 'orang' => 0, 'penugasan' => 0, 'rincian' => []];
        }

        $penugasan = DB::table('N_SOP_Penjadwalan as pj')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
            ->whereIn('pj.Id_SOP_Paket', $idPaket)
            ->where('pj.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            /* WAJIB. Pencabutan akses menyetel Flag_Cabut_Akses='Y' dan
               MEMBIARKAN Flag_Aktif='Y' (SopPenjadwalanService::cabutBanyakDetail),
               jadi menyaring Flag_Aktif saja ikut menghitung orang yang
               dokumennya sudah ditarik. 'T' di kolom ini berarti "belum
               dicabut". */
            ->where('d.Flag_Cabut_Akses', 'T');

        /* Dua agregat dari bentuk kueri yang sama, dijalankan terpisah karena
           COUNT(DISTINCT) lintas-paket tak bisa lahir dari GROUP BY per paket. */
        $orang = (int) (clone $penugasan)->distinct()->count('d.Kode_Karyawan');
        $totalPenugasan = (int) (clone $penugasan)->count();

        $perPaket = (clone $penugasan)
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw('pj.Id_SOP_Paket, COUNT(DISTINCT d.Kode_Karyawan) as n')
            ->pluck('n', 'Id_SOP_Paket');

        $nama = DB::table('N_SOP_Master_Paket')
            ->whereIn('Id_SOP_Paket', $idPaket)
            ->pluck('Nama_Paket', 'Id_SOP_Paket');

        $rincian = [];
        foreach ($idPaket as $id) {
            $rincian[] = [
                'Nama_Paket' => $nama[$id] ?? '(paket tanpa nama)',
                'Jumlah_Karyawan' => (int) ($perPaket[$id] ?? 0),
            ];
        }

        /* Paket terberat lebih dulu: yang paling banyak orangnya adalah yang
           paling menentukan keputusan admin. */
        usort($rincian, fn ($a, $b) => $b['Jumlah_Karyawan'] <=> $a['Jumlah_Karyawan']);

        return [
            'paket' => count($idPaket),
            'orang' => $orang,
            'penugasan' => $totalPenugasan,
            'rincian' => $rincian,
        ];
    }

    /**
     * Menonaktifkan dokumen.
     *
     * Mengembalikan cuplikan yang tersimpan, atau melempar bila keadaannya tak
     * mengizinkan. Cuplikannya DIBEKUKAN di sini, bukan dihitung ulang saat
     * dibaca: justru karena paket tetap berjalan sesudah ini, angkanya akan
     * berubah — dan "kondisi terakhir saat dinonaktifkan" hanya benar bila
     * direkam pada detik ini.
     */
    public function nonaktifkan(int $idDokumen, int $userId, string $catatan): array
    {
        $catatan = trim($catatan);

        $dok = DB::table('N_SOP_Dokumen')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->first(['Id_SOP_Dokumen', 'Judul', 'Flag_Aktif', 'Nonaktif_At']);

        if (! $dok) {
            throw new \RuntimeException('Dokumen tidak ditemukan.');
        }

        /* Sudah mati lewat jalur mana pun — baik jejak baru maupun Flag_Aktif
           warisan — tak boleh dinonaktifkan lagi. Menimpanya akan menghapus
           jejak penonaktifan yang pertama, yang justru bagian yang berharga. */
        if ($dok->Nonaktif_At !== null || $dok->Flag_Aktif !== SopStatus::AKTIF) {
            throw new \RuntimeException('Dokumen ini sudah nonaktif.');
        }

        $sebaran = $this->sebaran($idDokumen);

        DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDokumen)->update([
            'Nonaktif_At' => now(),
            'Nonaktif_By' => $userId,
            'Nonaktif_Catatan' => mb_substr($catatan, 0, self::CATATAN_MAKS),
            'Nonaktif_Paket' => $sebaran['paket'],
            'Nonaktif_Orang' => $sebaran['orang'],
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        /* Keterangan log memuat angkanya, bukan hanya judulnya. Log yang
           berbunyi "dokumen dinonaktifkan" tak memberi tahu apa pun tentang
           seberapa berat keputusannya saat itu diambil. */
        SopLog::catat(
            'DOK_NONAKTIF',
            'Dokumen dinonaktifkan: '.($dok->Judul ?: '(tanpa judul)').'. '
                ."Saat itu masih dipakai {$sebaran['paket']} paket dan menjangkau {$sebaran['orang']} karyawan. "
                ."Catatan: {$catatan}",
            null, null, null, null,
            [
                'oleh_id_users' => $userId,
                'id_dokumen' => $idDokumen,
                'catatan' => $catatan,
                'sebaran' => [
                    'paket' => $sebaran['paket'],
                    'orang' => $sebaran['orang'],
                    'penugasan' => $sebaran['penugasan'],
                ],
            ],
        );

        return $sebaran;
    }

    /**
     * Jejak penonaktifan satu dokumen, siap ditampilkan.
     *
     * Mengembalikan null bila dokumennya masih aktif. Dokumen yang dimatikan
     * lewat jalur LAMA (Flag_Aktif='T', sebelum DDL-2026-055) tetap dilaporkan
     * nonaktif, hanya saja jejaknya kosong — dan kekosongan itu dinyatakan,
     * bukan disamarkan jadi nol. Nol berarti "tak ada seorang pun terdampak";
     * null berarti "tak ada yang pernah mencatatnya".
     */
    public function jejak(int $idDokumen): ?array
    {
        $r = DB::table('N_SOP_Dokumen as d')
            ->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 'd.Nonaktif_By')
            ->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
            ->where('d.Id_SOP_Dokumen', $idDokumen)
            ->first([
                'd.Flag_Aktif', 'd.Nonaktif_At', 'd.Nonaktif_Catatan',
                'd.Nonaktif_Paket', 'd.Nonaktif_Orang',
                'k.Nama as Oleh_Nama', 'u.Username as Oleh_Username',
            ]);

        if (! $r) {
            return null;
        }

        $nonaktif = $r->Nonaktif_At !== null || $r->Flag_Aktif !== SopStatus::AKTIF;
        if (! $nonaktif) {
            return null;
        }

        return [
            'Nonaktif_At' => $r->Nonaktif_At,
            'Nonaktif_Oleh' => $r->Oleh_Nama ?: ($r->Oleh_Username ?: null),
            'Nonaktif_Catatan' => $r->Nonaktif_Catatan,
            'Nonaktif_Paket' => $r->Nonaktif_Paket !== null ? (int) $r->Nonaktif_Paket : null,
            'Nonaktif_Orang' => $r->Nonaktif_Orang !== null ? (int) $r->Nonaktif_Orang : null,
            /* Penanda bagi layar: baris warisan tak punya jejak sama sekali,
               dan layar harus mengatakannya alih-alih menggambar papan kosong
               yang terbaca seperti data yang hilang. */
            'Tanpa_Jejak' => $r->Nonaktif_At === null,
        ];
    }
}
