<?php

namespace App\Services\SYSTEM\MasterData\KontakBantuan;

use App\Services\SYSTEM\MasterData\GrupFitur\KodeGrupFitur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Kontak bantuan resmi, GLOBAL lintas modul (DDL-2026-144).
 *
 * Satu tabel untuk semua modul; tiap baris berlingkup satu fitur di satu modul
 * (Kode_Grup_Fitur + Kode_Fitur, mis. VISITOR/PERMIT). Kontak diisi admin dari
 * halaman master modulnya sendiri — sengaja kontak RESMI, bukan nomor pribadi
 * pemegang role yang diambil otomatis (keputusan user 28 Sep 2026).
 */
class KontakBantuanService
{
    public const TABEL = 'N_HRIS_Master_Kontak_Bantuan';

    /**
     * Cadangan bila tabel belum ada / belum berisi: PIC yang dulu tertulis di
     * InertiaShellService::buildHelpItems. Isinya dipindah ke tabel oleh
     * DML-2026-150; daftar ini hanya jaring pengaman sebelum DML itu jalan.
     */
    public const CADANGAN = [
        ['Kode_Grup_Fitur' => 'ESS', 'Kode_Fitur' => 'UMUM', 'Nama_Kontak' => 'PIC HCIS', 'Keterangan' => 'Admin HCIS Absensi dan Perizinan', 'No_HP' => null, 'Email' => 'hc.ops@evomanufacturingindonesia.co.id', 'Jam_Layanan' => null],
        ['Kode_Grup_Fitur' => 'KPI', 'Kode_Fitur' => 'UMUM', 'Nama_Kontak' => 'PIC KPI', 'Keterangan' => 'Admin KPI', 'No_HP' => null, 'Email' => 'learning.development@evomanufacturingindonesia.co.id', 'Jam_Layanan' => null],
        ['Kode_Grup_Fitur' => 'LMS', 'Kode_Fitur' => 'UMUM', 'Nama_Kontak' => 'PIC LMS', 'Keterangan' => 'Admin LMS', 'No_HP' => null, 'Email' => 'learning.development@evomanufacturingindonesia.co.id', 'Jam_Layanan' => null],
        ['Kode_Grup_Fitur' => 'SYSTEM', 'Kode_Fitur' => 'IT', 'Nama_Kontak' => 'PIC IT HCIS', 'Keterangan' => 'Admin IT HCIS', 'No_HP' => null, 'Email' => 'it.hcis@evomanufacturingindonesia.co.id', 'Jam_Layanan' => null],
    ];

    /** Sebutan modul yang dikenal pengguna (judul "Bantuan …"). */
    private const SEBUTAN_MODUL = ['ESS' => 'HCIS', 'VISITOR' => 'VMS', 'SYSTEM' => 'IT HCIS'];

    /**
     * Label fitur; '' = bantuan modul secara umum (tanpa label fitur).
     * SYSTEM/IT adalah bantuan umum modul "IT HCIS", jadi tidak diberi label.
     */
    private const LABEL_FITUR = ['UMUM' => '', 'IT' => ''];

    /**
     * Kontak aktif untuk satu fitur, siap tampil.
     *
     * @return list<array{nama: string, keterangan: ?string, no_hp: ?string, email: ?string,
     *                    jam_layanan: ?string, tautan_wa: ?string, tautan_telepon: ?string, tautan_email: ?string}>
     */
    public function daftar(string $kodeGrupFitur, string $kodeFitur): array
    {
        try {
            $baris = DB::table(self::TABEL)
                ->whereIn(DB::raw('UPPER(Kode_Grup_Fitur)'), KodeGrupFitur::varian($kodeGrupFitur))
                ->where('Kode_Fitur', $kodeFitur)
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Tampil')
                ->orderBy('Nama_Kontak')
                ->get(['Nama_Kontak', 'Keterangan', 'No_HP', 'Email', 'Jam_Layanan']);
        } catch (\Throwable $e) {
            // Informasi, bukan syarat: tabel yang belum ada (DDL belum dijalankan)
            // tidak boleh menggagalkan halaman pemakainya.
            Log::warning('Kontak bantuan tidak bisa dibaca', [
                'grup' => $kodeGrupFitur,
                'fitur' => $kodeFitur,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return $baris->map(fn ($k) => $this->bentuk($k))->values()->all();
    }

    /**
     * Semua kontak aktif untuk Pusat Bantuan, SATU KELOMPOK PER MODUL
     * ("Bantuan VMS", "Bantuan HCIS", ...), dengan bagian per fitur di
     * dalamnya (Umum dulu, lalu Permit, Tamu, ...). Modul halaman yang sedang
     * dibuka ditandai `relevan` dan ditaruh paling atas. Keputusan user
     * 29 Sep 2026: daftar per modul, rincian fitur di dalam modal.
     *
     * @return list<array{kunci: string, kode_grup: string, judul: string, subjudul: string,
     *                    relevan: bool, jumlah: int, fitur: list<string>,
     *                    bagian: list<array{kode_fitur: string, label: string, jumlah: int, kontak: list<array>}>}>
     */
    public function kelompokPusatBantuan(?string $jenisPage = null): array
    {
        $kunciKelompok = fn ($k) => KodeGrupFitur::kanonik($k->Kode_Grup_Fitur).'|'.strtoupper(trim((string) $k->Kode_Fitur));

        try {
            $semua = DB::table(self::TABEL)
                ->orderBy('Urutan_Tampil')
                ->orderBy('Nama_Kontak')
                ->get(['Kode_Grup_Fitur', 'Kode_Fitur', 'Nama_Kontak', 'Keterangan', 'No_HP', 'Email', 'Jam_Layanan', 'Flag_Aktif']);
        } catch (\Throwable $e) {
            Log::warning('Kontak bantuan Pusat Bantuan tidak bisa dibaca', ['error' => $e->getMessage()]);
            $semua = collect();
        }

        // Cadangan hanya untuk modul·fitur yang belum punya baris sama sekali di
        // tabel. Baris nonaktif ikut dihitung: yang sengaja dimatikan admin tidak
        // dihidupkan lagi oleh cadangan.
        $adaDiTabel = $semua->map($kunciKelompok)->unique()->flip();
        $baris = $semua->where('Flag_Aktif', 'Y')->values()->concat(
            collect(self::CADANGAN)->map(fn (array $k) => (object) $k)->reject(fn ($k) => $adaDiTabel->has($kunciKelompok($k)))
        );

        $modul = $this->modul();
        $modulHalaman = $jenisPage ? $this->modulHalaman($jenisPage) : null;

        return $baris
            ->groupBy(fn ($k) => KodeGrupFitur::kanonik($k->Kode_Grup_Fitur))
            ->map(function ($kontakModul, string $grup) use ($modul, $modulHalaman) {
                $bagian = $kontakModul
                    ->groupBy(fn ($k) => strtoupper(trim((string) $k->Kode_Fitur)))
                    ->map(fn ($kontak, string $fitur) => [
                        'kode_fitur' => $fitur,
                        'label' => $this->labelFitur($fitur) ?: 'Umum',
                        'jumlah' => $kontak->count(),
                        'kontak' => $kontak->map(fn ($k) => $this->bentuk($k))->values()->all(),
                        '_urut' => [$this->labelFitur($fitur) === '' ? 0 : 1, $this->labelFitur($fitur)],
                    ])
                    ->sortBy('_urut')
                    ->map(function (array $b) {
                        unset($b['_urut']);

                        return $b;
                    })
                    ->values();

                return [
                    'kunci' => strtolower($grup),
                    'kode_grup' => $grup,
                    'judul' => 'Bantuan '.(self::SEBUTAN_MODUL[$grup] ?? $grup),
                    'subjudul' => $grup === 'SYSTEM' ? 'Sistem & akun HCIS' : ($modul[$grup]['nama'] ?? $grup),
                    'relevan' => $modulHalaman !== null && $grup === $modulHalaman,
                    'jumlah' => $kontakModul->count(),
                    // Nama fitur (selain Umum) untuk ringkasan di daftar.
                    'fitur' => $bagian->pluck('kode_fitur')->map(fn ($f) => $this->labelFitur($f))->filter()->values()->all(),
                    'bagian' => $bagian->all(),
                    '_urut' => $modul[$grup]['urutan'] ?? 99,
                ];
            })
            ->sort(fn ($a, $b) => [$b['relevan'], $a['_urut'], $a['judul']] <=> [$a['relevan'], $b['_urut'], $b['judul']])
            ->map(function (array $k) {
                unset($k['_urut']);

                return $k;
            })
            ->values()
            ->all();
    }

    /** '' untuk UMUM (bantuan modul secara umum). */
    private function labelFitur(string $kodeFitur): string
    {
        return self::LABEL_FITUR[$kodeFitur] ?? ucwords(strtolower(str_replace('_', ' ', $kodeFitur)));
    }

    /** @return array<string, array{nama: string, urutan: int}> */
    private function modul(): array
    {
        try {
            return DB::table('N_HRIS_Master_Grup_Fitur')
                ->get(['Kode_Grup_Fitur', 'Nama_Grup_Fitur', 'Sort_Order'])
                ->mapWithKeys(fn ($r) => [KodeGrupFitur::kanonik($r->Kode_Grup_Fitur) => [
                    'nama' => (string) ($r->Nama_Grup_Fitur ?: $r->Kode_Grup_Fitur),
                    'urutan' => $r->Sort_Order !== null ? (int) $r->Sort_Order : 99,
                ]])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function modulHalaman(string $jenisPage): ?string
    {
        try {
            $kode = DB::table('N_HRIS_Grup_Fitur_Map as m')
                ->join('N_HRIS_Master_Grup_Fitur as g', 'g.Id_Grup_Fitur', '=', 'm.Id_Grup_Fitur')
                ->where('m.Jenis_page', $jenisPage)
                ->where('m.Flag_Aktif', 'Y')
                ->value('g.Kode_Grup_Fitur');
        } catch (\Throwable) {
            return null;
        }

        return $kode ? KodeGrupFitur::kanonik($kode) : null;
    }

    private function bentuk(object $k): array
    {
        $hp = trim((string) $k->No_HP) ?: null;
        $email = trim((string) $k->Email) ?: null;
        $wa = $hp ? self::nomorInternasional($hp) : null;

        return [
            'nama' => (string) $k->Nama_Kontak,
            'keterangan' => trim((string) $k->Keterangan) ?: null,
            'no_hp' => $hp,
            'email' => $email,
            'jam_layanan' => trim((string) $k->Jam_Layanan) ?: null,
            'tautan_wa' => $wa ? 'https://wa.me/'.$wa : null,
            'tautan_telepon' => $wa ? 'tel:+'.$wa : null,
            'tautan_email' => $email ? 'mailto:'.$email : null,
        ];
    }

    /** "0812-3456 789" / "+62 812..." / "62812..." -> "62812..." (format wa.me). */
    public static function nomorInternasional(string $nomor): ?string
    {
        $angka = preg_replace('/\D+/', '', $nomor) ?? '';

        if ($angka === '') {
            return null;
        }

        if (str_starts_with($angka, '0')) {
            $angka = '62'.substr($angka, 1);
        }

        return $angka;
    }
}
