<?php

namespace App\Services\VISITOR\Workflow\Reporting\Monitoring;

use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;

/**
 * Saringan tab All Monitoring permit — SATU definisi untuk daftar dan untuk
 * "pilih semua yang sesuai filter" lalu cabut akses massal.
 *
 * Sebelum 30 Sep 2026 pencabutan massal menyusun saringannya sendiri dan
 * tidak mengenal asal permit maupun rentang tanggal: admin yang menyaring
 * "permit kunjungan tamu" lalu memilih semua ikut mencabut permit karyawan
 * yang aktif. Masa berlakunya pun dibaca per tanggal, bukan per jam seperti
 * daftarnya. Yang dicabut harus persis yang terlihat.
 *
 * Alias yang dibutuhkan kueri pemanggil: h (header permit), d (detail),
 * k (Karyawan pemohon), mk (kategori), vt (tipe kendaraan), pe (proyeksi
 * efektif).
 */
class SaringanMonitoringPermit
{
    /**
     * Akhir masa berlaku yang JUJUR: jendela jam lebih dulu, kolom tanggal
     * sebagai cadangan. Izin yang berakhir pukul 08:00 hari ini sudah mati
     * saat layarnya dibuka siang hari — sama dengan penjadwal kedaluwarsa.
     */
    public const AKHIR_SQL = 'COALESCE(d.Waktu_Berakhir, DATEADD(day, 1, CAST(d.Tanggal_Kadaluarsa AS datetime2(0))))';

    public const KUNCI = ['jenis_permit', 'asal', 'jenis_kendaraan', 'status', 'mode_tanggal', 'tanggal_dari', 'tanggal_sampai', 'cari'];

    public function __construct(private VisitorPermitQueryService $permitQuery) {}

    /** @param array<string, mixed> $s nilai saringan dari layar (lihat KUNCI) */
    public function terapkan(Builder $query, array $s): Builder
    {
        $jenisPermit = trim((string) ($s['jenis_permit'] ?? ''));
        $asal = strtoupper(trim((string) ($s['asal'] ?? '')));
        $jenisKendaraan = trim((string) ($s['jenis_kendaraan'] ?? ''));
        $status = (string) ($s['status'] ?? '');
        $modeTanggal = ($s['mode_tanggal'] ?? '') === 'approve' ? 'approve' : 'berlaku';
        $tanggalDari = self::tanggal($s['tanggal_dari'] ?? null);
        $tanggalSampai = self::tanggal($s['tanggal_sampai'] ?? null);
        $cari = trim((string) ($s['cari'] ?? ''));
        $akhir = self::AKHIR_SQL;

        if ($jenisPermit !== '') {
            $query->where('mk.Nama_Kategori', $jenisPermit);
        }

        // ASAL izin — karyawan, tamu, atau mitra. Berbeda dari kategori:
        // kategori COKLAT juga sah dipakai permit karyawan biasa.
        if ($asal === 'TAMU') {
            $query->whereNotNull('h.No_Transaksi_Tamu');
        } elseif ($asal === 'MITRA') {
            // Izin turunan kunjungan mitra membawa Sumber_Pengajuan 'API' juga;
            // tanpa pengecualian ini ia terhitung sebagai TAMU dan MITRA.
            $query->whereNull('h.No_Transaksi_Tamu')
                ->whereRaw("UPPER(ISNULL(h.Sumber_Pengajuan, 'HCIS')) = 'API'");
        } elseif ($asal !== '') {
            $query->whereNull('h.No_Transaksi_Tamu')
                ->whereRaw("UPPER(ISNULL(h.Sumber_Pengajuan, 'HCIS')) <> 'API'");
        }

        if ($jenisKendaraan !== '') {
            $query->where('vt.Nama_Tipe_Kendaraan', 'LIKE', "%{$jenisKendaraan}%");
        }

        // Penyaring masa berlaku tidak memuat yang dicabut: aksesnya sudah
        // tidak ada. Yang dicabut dilihat lewat penyaring "dicabut" sendiri.
        if (in_array($status, ['aktif', 'segera', 'hampir', 'kadaluarsa'], true)) {
            $query->whereRaw('NOT ('.$this->permitQuery->dicabutSql().')');
        }

        if ($status === 'aktif') {
            $query->whereRaw("{$akhir} >= GETDATE()");
        } elseif ($status === 'segera' || $status === 'hampir') {
            $query->whereRaw("{$akhir} >= GETDATE()")
                ->whereRaw("{$akhir} <= DATEADD(day, 30, GETDATE())");
        } elseif ($status === 'kadaluarsa') {
            $query->whereRaw("{$akhir} < GETDATE()");
        }

        if ($tanggalDari || $tanggalSampai) {
            if ($modeTanggal === 'approve') {
                // Kapan permitnya DISETUJUI; baris lama dibaca dari proyeksi.
                $disetujui = 'COALESCE(h.Approved_At, pe.Disetujui_At)';
                if ($tanggalDari) {
                    $query->whereRaw("{$disetujui} >= ?", [$tanggalDari.' 00:00:00']);
                }
                if ($tanggalSampai) {
                    $query->whereRaw("{$disetujui} <= ?", [$tanggalSampai.' 23:59:59']);
                }
            } else {
                // Masa berlaku yang BERIRISAN dengan rentang: permit setahun
                // yang sedang berlaku pada minggu itu tetap harus muncul.
                $mulai = 'COALESCE(d.Waktu_Mulai_Berlaku, CAST(d.Tanggal_Mulai_Berlaku AS datetime2(0)))';
                if ($tanggalDari) {
                    $query->whereRaw("{$akhir} >= ?", [$tanggalDari.' 00:00:00']);
                }
                if ($tanggalSampai) {
                    $query->whereRaw("{$mulai} <= ?", [$tanggalSampai.' 23:59:59']);
                }
            }
        }

        if ($cari !== '') {
            $query->where(function ($q) use ($cari) {
                $q->where('k.Nama', 'LIKE', "%{$cari}%")
                    ->orWhere('h.No_Transaksi', 'LIKE', "%{$cari}%")
                    // Plat dirakit dari tiga ruas; mencarinya per ruas akan
                    // meleset untuk kata kunci yang melintasi batas ruas.
                    ->orWhereRaw($this->permitQuery->platNomorSql('d').' LIKE ?', ["%{$cari}%"]);
            });
        }

        return $query;
    }

    /** 'YYYY-MM-DD', atau null bila kosong / tak terbaca. */
    public static function tanggal(mixed $nilai): ?string
    {
        $teks = trim((string) $nilai);
        if ($teks === '') {
            return null;
        }

        try {
            return Carbon::parse($teks)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
