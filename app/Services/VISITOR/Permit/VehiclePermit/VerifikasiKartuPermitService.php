<?php

namespace App\Services\VISITOR\Permit\VehiclePermit;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keadaan terkini sebuah kartu permit dari kode QR-nya.
 *
 * Satu sumber untuk semua pintu verifikasi: pemindai di dalam HCIS (karyawan
 * & admin) dan API cek kartu publik yang dipanggil layanan terpisah. Keadaan
 * dibaca dari basis data, bukan dari kertas: kartu asli yang sudah dicabut
 * tetap terbaca "tidak berlaku" walau cetakannya masih mulus.
 */
class VerifikasiKartuPermitService
{
    public function __construct(
        protected VisitorPermitQueryService $permitQuery,
        protected KartuPermitQr $kartuQr,
    ) {}

    /**
     * Baris permit milik kode kartu ini.
     *
     * @return array{dikenali: bool, permit: object|null}
     */
    public function cari(string $token): array
    {
        $id = $this->kartuQr->bacaId($token);

        if ($id === null) {
            return ['dikenali' => false, 'permit' => null];
        }

        $permit = DB::table('N_EMI_VISITOR_Transaksi_Permit as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_Pemohon')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_Divisi_Sub_Divisi', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'h.No_Transaksi')
            ->where('h.Id_Transaksi_Permit', $id)
            ->where('h.Flag_Aktif', 'Y')
            ->select(
                'h.Id_Transaksi_Permit',
                'h.No_Transaksi',
                'h.Flag_Approval',
                'h.Kode_Karyawan_Pemohon',
                'h.No_Transaksi_Tamu',
                'h.Sumber_Pengajuan',
                'k.Tanggal_Resign',
                'd.Snapshot_Kategori_Permit as Nama_Kategori',
                'd.Tanggal_Mulai_Berlaku',
                'd.Tanggal_Kadaluarsa',
                'd.Plat_Nomor_Prefix',
                'd.Plat_Nomor_Angka',
                'd.Plat_Nomor_Suffix',
                DB::raw($this->permitQuery->platNomorSql('d').' as No_Plat_Kendaraan'),
                DB::raw('ISNULL(k.Nama, h.Kode_Karyawan_Pemohon) as Nama_Pemohon'),
                DB::raw("ISNULL(vd.nama_divisi, '-') as Departemen"),
                'pe.Status_Efektif',
                'pe.Alasan_Status',
                'pe.Dibatalkan_At',
            )
            ->first();

        return ['dikenali' => true, 'permit' => $permit];
    }

    /**
     * @return array<string, mixed>
     */
    public function keadaan(?object $permit): array
    {
        if (! $permit) {
            return [
                'sah' => false,
                'judul' => 'KARTU TIDAK DIKENALI',
                'status' => 'TIDAK_DIKENALI',
                'sebab' => 'Permit pada kartu ini sudah tidak ada di HCIS.',
                'dicek' => $this->dicek(),
            ];
        }

        $tanggal = fn ($nilai) => blank($nilai) ? '-' : Carbon::parse($nilai)->translatedFormat('d M Y');
        $dasar = [
            'id' => (int) $permit->Id_Transaksi_Permit,
            'plat' => $permit->No_Plat_Kendaraan ?: '-',
            'kategori' => mb_strtoupper((string) ($permit->Nama_Kategori ?: 'PERMIT AKSES')),
            'pemegang' => $permit->Nama_Pemohon ?: '-',
            'departemen' => $permit->Departemen ?: '-',
            'sejak' => $tanggal($permit->Tanggal_Mulai_Berlaku),
            'sampai' => $tanggal($permit->Tanggal_Kadaluarsa),
            'no_transaksi' => $permit->No_Transaksi,
            'dicek' => $this->dicek(),
        ];

        // Urutan cabang penting: pencabutan TIDAK menyentuh Flag_Approval, jadi
        // ia harus diperiksa lebih dulu — kalau tidak, kartu yang sudah dicabut
        // terbaca "aktif" dan justru meloloskan kendaraan yang seharusnya ditolak.
        if (($permit->Status_Efektif ?? null) === 'DIBATALKAN') {
            return $dasar + [
                'sah' => false,
                'judul' => 'KARTU TIDAK BERLAKU',
                'status' => 'DICABUT',
                'sebab' => trim('Akses dicabut'.($permit->Dibatalkan_At ? ' pada '.$tanggal($permit->Dibatalkan_At) : '').'. '.($permit->Alasan_Status ?? '')),
            ];
        }

        // Hari kerja terakhir sudah lewat: kartunya ikut berakhir walau tanggal
        // kedaluwarsanya belum tiba. Hanya permit milik karyawan sendiri — izin
        // tamu dan permit mitra cuma menumpang nama PIC.
        $milikKaryawan = blank($permit->No_Transaksi_Tamu ?? null)
            && strtoupper((string) ($permit->Sumber_Pengajuan ?? 'HCIS')) !== 'API';
        if ($milikKaryawan && filled($permit->Tanggal_Resign ?? null)
            && Carbon::parse($permit->Tanggal_Resign)->endOfDay()->isPast()) {
            return $dasar + [
                'sah' => false,
                'judul' => 'KARTU TIDAK BERLAKU',
                // Status yang sudah dikenal layanan cek kartu publik di luar repo ini.
                'status' => 'KEDALUWARSA',
                'sebab' => 'Pemegang kartu sudah resign; hari kerja terakhirnya '.$tanggal($permit->Tanggal_Resign).'.',
                // Status kepegawaian bukan urusan publik.
                'sebab_publik' => 'Masa berlaku kartu ini sudah berakhir.',
            ];
        }

        if ($permit->Flag_Approval !== 'Y') {
            return $dasar + [
                'sah' => false,
                'judul' => 'KARTU TIDAK BERLAKU',
                'status' => $permit->Flag_Approval === 'T' ? 'DITOLAK' : 'BELUM DISETUJUI',
                'sebab' => 'Permit ini belum pernah berlaku, jadi kartunya tidak sah.',
            ];
        }

        if (filled($permit->Tanggal_Kadaluarsa) && Carbon::parse($permit->Tanggal_Kadaluarsa)->endOfDay()->isPast()) {
            return $dasar + [
                'sah' => false,
                'judul' => 'KARTU TIDAK BERLAKU',
                'status' => 'KEDALUWARSA',
                'sebab' => 'Masa berlakunya habis pada '.$tanggal($permit->Tanggal_Kadaluarsa).'.',
            ];
        }

        return $dasar + [
            'sah' => true,
            'judul' => 'KARTU SAH',
            'status' => 'AKTIF',
            'sebab' => null,
        ];
    }

    /**
     * Versi PUBLIK: hanya yang boleh dilihat siapa pun yang memindai kartu.
     *
     * Tanpa nama, NIK, departemen, nomor transaksi, maupun Id — layanan cek
     * kartu publik berada di luar HCIS, dan apa pun yang ia terima dianggap
     * sudah terbuka ke internet. Plat disamarkan: cukup untuk mencocokkan
     * kendaraan di depan mata, tidak cukup untuk menelusuri pemiliknya.
     *
     * @return array<string, mixed>
     */
    public function keadaanPublik(string $token): array
    {
        ['dikenali' => $dikenali, 'permit' => $permit] = $this->cari($token);

        if (! $dikenali) {
            return [
                'sah' => false,
                'status' => 'TIDAK_DIKENALI',
                'judul' => 'KARTU TIDAK DIKENALI',
                'sebab' => 'Kode pada kartu ini tidak dikenali. Kartu patut dicurigai palsu.',
                'kategori' => null,
                'plat' => null,
                'berlaku_sejak' => null,
                'berlaku_sampai' => null,
                'dicek' => $this->dicek(),
            ];
        }

        $k = $this->keadaan($permit);

        return [
            'sah' => (bool) $k['sah'],
            'status' => str_replace(' ', '_', (string) $k['status']),
            'judul' => $k['judul'],
            // Alasan pencabutan bisa memuat catatan internal; publik cukup tahu sebabnya.
            'sebab' => match ($k['status']) {
                'DICABUT' => 'Akses kartu ini sudah dicabut.',
                'KEDALUWARSA' => $k['sebab_publik'] ?? $k['sebab'],
                'AKTIF' => null,
                'TIDAK_DIKENALI' => 'Permit pada kartu ini sudah tidak ada.',
                default => 'Permit ini belum pernah berlaku, jadi kartunya tidak sah.',
            },
            'kategori' => $permit ? $k['kategori'] : null,
            'plat' => $permit ? self::samarkanPlat($permit->Plat_Nomor_Prefix, $permit->Plat_Nomor_Angka, $permit->Plat_Nomor_Suffix) : null,
            'berlaku_sejak' => $permit ? $k['sejak'] : null,
            'berlaku_sampai' => $permit ? $k['sampai'] : null,
            'dicek' => $k['dicek'],
        ];
    }

    /** "BG 1234 XY" -> "BG 12•• XY": prefix & suffix utuh, dua digit terakhir disamarkan. */
    public static function samarkanPlat(?string $prefix, ?string $angka, ?string $suffix): ?string
    {
        $angka = trim((string) $angka);
        $samar = $angka === '' ? '' : (mb_strlen($angka) <= 2
            ? str_repeat('•', mb_strlen($angka))
            : mb_substr($angka, 0, mb_strlen($angka) - 2).'••');

        $hasil = trim(preg_replace('/\s+/', ' ', trim((string) $prefix).' '.$samar.' '.trim((string) $suffix)));

        return $hasil === '' ? null : $hasil;
    }

    public function dicek(): string
    {
        return Carbon::now()->translatedFormat('d M Y H:i');
    }
}
