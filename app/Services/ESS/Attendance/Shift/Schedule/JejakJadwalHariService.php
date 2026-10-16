<?php

namespace App\Services\ESS\Attendance\Shift\Schedule;

use App\Services\SYSTEM\Governance\Authorization\Shared\AccessContext;
use App\Services\SYSTEM\Governance\Authorization\Shared\FunctionalRole;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat jadwal — jejak shift & status hari satu karyawan pada
 * satu tanggal.
 *
 * Hasil akhir diambil dari N_HRIS_FN_GetStatusHariRange, fungsi yang sama
 * dengan kalender, supaya panel tak pernah menjawab berbeda dari kalender.
 * Lapisan & linimasa disusun ulang dari tabel sumber yang dibaca fungsi itu:
 *  - shift: HRIS_Shift_Sementara (tanggal itu) menang atas
 *    HRIS_Shift_Per_Karyawan (Periode terakhir <= tanggal yang punya detail
 *    untuk hari itu);
 *  - status hari (prioritas, angka kecil menang): 1 Konversi Lembur DAY_OFF,
 *    2 N_HRIS_Status_Hari_Karyawan, 3 hari libur nasional, 4 cuti bersama,
 *    5 hari OFF bawaan shift permanen, 99 masuk.
 *
 * Murni baca; jumlah kueri tetap, berapa pun panjang riwayatnya.
 */
class JejakJadwalHariService
{
    private const KODE_PERUSAHAAN = '001';

    /** Banyaknya shift permanen lampau yang ikut ditampilkan sebagai riwayat. */
    private const RIWAYAT_PERMANEN = 6;

    private const SEBAB_STATUS = [
        'OFF-KONVERSI_LEMBUR' => 'KONVERSI_LEMBUR',
        'OFF-Hari_Libur' => 'HARI_LIBUR',
        'OFF-Cuti_Bersama' => 'CUTI_BERSAMA',
        'OFF-Default_Shift' => 'SHIFT_DEFAULT_OFF',
    ];

    /** DATEPART(WEEKDAY) dengan @@DATEFIRST 7 (bawaan SQL Server). */
    private const NAMA_HARI = [1 => 'Minggu', 2 => 'Senin', 3 => 'Selasa', 4 => 'Rabu', 5 => 'Kamis', 6 => 'Jumat', 7 => 'Sabtu'];

    /** Pelaku yang tak terekam (impor/input langsung tanpa transaksi) disebut terang-terangan. */
    public const PELAKU_TAK_TERCATAT = 'Tidak tercatat';

    private const ALASAN_NONAKTIF = [
        'Replaced by new temporary shift' => 'Digantikan shift sementara yang lebih baru',
        'Replaced by permanent shift' => 'Terhapus otomatis karena shift permanen baru dipasang',
    ];

    /**
     * @return array{kategori: string, sebab: string}
     */
    public static function sebabStatus(?string $statusFungsi, bool $adaStatusHariAktif): array
    {
        $nilai = trim((string) $statusFungsi);
        if (isset(self::SEBAB_STATUS[$nilai])) {
            return ['kategori' => 'LIBUR', 'sebab' => self::SEBAB_STATUS[$nilai]];
        }

        $kategori = str_starts_with(strtoupper($nilai), 'OFF') ? 'LIBUR' : 'MASUK';
        // Nilai mentah (OFF/WORK/...) hanya lahir dari baris status hari; 'WORK'
        // tanpa baris aktif = bawaan fungsi.
        $sebab = $adaStatusHariAktif ? 'STATUS_HARI' : ($kategori === 'LIBUR' ? 'STATUS_HARI' : 'BAWAAN');

        return ['kategori' => $kategori, 'sebab' => $sebab];
    }

    /**
     * Penjelasan manusiawi untuk hasil akhir satu tanggal.
     *
     * @param  array<string, mixed>  $k  konteks: shift, shift_default, hari, konversi_asal, konversi_no,
     *                                   status_pelaku, status_waktu, status_transaksi, status_rentang,
     *                                   status_keterangan, libur_nasional, cuti_bersama, tertimpa[]
     * @return array{judul: string, rincian: array<int, string>}
     */
    public static function penjelasan(string $sebab, string $kategori, array $k): array
    {
        $isi = fn (string $kunci) => trim((string) ($k[$kunci] ?? ''));
        $rincian = [];

        switch ($sebab) {
            case 'KONVERSI_LEMBUR':
                $judul = 'Libur pengganti lembur';
                if ($isi('konversi_asal') !== '') {
                    $rincian[] = 'Mengganti lembur tanggal '.$isi('konversi_asal').'.';
                }
                if ($isi('konversi_no') !== '') {
                    $rincian[] = 'Dari pengajuan '.$isi('konversi_no').'.';
                }
                break;
            case 'STATUS_HARI':
                $judul = $kategori === 'LIBUR' ? 'Libur yang ditetapkan khusus' : 'Masuk kerja yang ditetapkan khusus';
                if ($isi('status_pelaku') !== '' || $isi('status_transaksi') !== '') {
                    $rincian[] = 'Ditetapkan oleh '.($isi('status_pelaku') ?: self::PELAKU_TAK_TERCATAT)
                        .($isi('status_waktu') !== '' ? ' pada '.$isi('status_waktu') : '')
                        .($isi('status_transaksi') !== '' ? ' lewat '.$isi('status_transaksi') : '').'.';
                }
                if ($isi('status_rentang') !== '') {
                    $rincian[] = 'Berlaku '.$isi('status_rentang').'.';
                }
                if ($isi('status_keterangan') !== '') {
                    $rincian[] = 'Keterangan: '.$isi('status_keterangan');
                }
                break;
            case 'HARI_LIBUR':
                $judul = 'Hari libur nasional';
                $rincian[] = ($isi('libur_nasional') ?: 'Tercatat di kalender hari libur').'.';
                break;
            case 'CUTI_BERSAMA':
                $judul = 'Cuti bersama';
                $rincian[] = ($isi('cuti_bersama') ?: 'Termasuk peserta cuti bersama').'.';
                break;
            case 'SHIFT_DEFAULT_OFF':
                $judul = 'Libur bawaan shift';
                $rincian[] = 'Shift permanen '.($isi('shift_default') ?: '—').' menetapkan setiap hari '.($isi('hari') ?: 'ini').' sebagai hari libur.';
                break;
            default:
                $judul = 'Hari kerja biasa';
                $rincian[] = 'Masuk sesuai shift '.($isi('shift') ?: '—').'.';
        }

        $tertimpa = array_values(array_filter((array) ($k['tertimpa'] ?? [])));
        if ($tertimpa !== []) {
            $rincian[] = 'Aturan lain yang tertimpa: '.implode('; ', $tertimpa).'.';
        }

        return ['judul' => $judul, 'rincian' => $rincian];
    }

    public static function alasanNonaktif(?string $alasan): ?string
    {
        if ($alasan === null || trim($alasan) === '') {
            return null;
        }

        return self::ALASAN_NONAKTIF[trim($alasan)] ?? trim($alasan);
    }

    /**
     * Kolom pelaku di tabel jadwal tak seragam: Id_Users ("4") atau Kode_Karyawan ("H45").
     *
     * @return array{0: string, 1: string}|null
     */
    public static function jenisPelaku(?string $nilai): ?array
    {
        $nilai = trim((string) $nilai);
        if ($nilai === '') {
            return null;
        }

        return ctype_digit($nilai) ? ['id', $nilai] : ['kode', $nilai];
    }

    public static function labelTransaksi(?string $no, ?string $jenisTransaksi, ?string $flagAdmin): string
    {
        $no = trim((string) $no);
        if ($no === '') {
            return 'Tanpa transaksi (input langsung)';
        }
        if (str_starts_with($no, 'KL')) {
            return 'Konversi Lembur';
        }
        if (str_starts_with($no, 'TSH')) {
            return $flagAdmin === 'Y' ? 'Status Hari oleh admin' : 'Status Hari oleh leader';
        }
        if (str_starts_with($no, 'TH')) {
            return 'Tukar Hari';
        }
        if ($jenisTransaksi === 'SELF_SHIFT') {
            return 'Pengajuan karyawan (Self Shift)';
        }

        return $flagAdmin === 'Y' ? 'Admin Shift' : 'Leader Shift';
    }

    /**
     * Aturan akses yang sama dengan daftar tim di Leader/Admin Shift
     * (ShiftRequestService::getLeaderTeamMembers), dalam satu `exists()`.
     */
    public function bolehDilihatLeader(string $leaderKode, string $kodeKaryawan, FunctionalRole $role, ?AccessContext $context): bool
    {
        if ($leaderKode === $kodeKaryawan) {
            return true;
        }

        $q = DB::table('Karyawan')
            ->where('Karyawan.Kode_Karyawan', $kodeKaryawan)
            ->where('Karyawan.Aktif', 'Y');

        if ($context && $context->isAdmin() && $role->can('SHIFT_BYPASS_APPROVAL')) {
            $role->applyScope($q);
        } else {
            $q->join('HRIS_Karyawan_Team as kt', 'Karyawan.Kode_Karyawan', '=', 'kt.Kode_Karyawan')
                ->where('kt.Kode_Karyawan_Team', $leaderKode);
        }
        $role->applyScope($q);

        return $q->exists();
    }

    /** @return array<string, mixed>|null */
    public function susun(string $kodeKaryawan, string $tanggal): ?array
    {
        $tgl = Carbon::parse($tanggal)->toDateString();
        // DATEPART(WEEKDAY) bergantung @@DATEFIRST — dihitung di SQL Server, sama dengan fungsinya.
        $karyawan = DB::table('Karyawan')->where('Kode_Karyawan', $kodeKaryawan)
            ->selectRaw('Kode_Karyawan, Nama, DATEPART(WEEKDAY, CAST(? AS date)) AS Hari_Ke', [$tgl])
            ->first();
        if (! $karyawan) {
            return null;
        }
        $hari = (int) $karyawan->Hari_Ke;

        $hasil = DB::selectOne('SELECT * FROM N_HRIS_FN_GetStatusHariRange(?, ?, ?, ?)', [self::KODE_PERUSAHAAN, $kodeKaryawan, $tgl, $tgl]);

        $sementara = DB::table('HRIS_Shift_Sementara as s')
            ->leftJoin('HRIS_Shift_Kerja as sk', 'sk.ID_Shift', '=', 's.ID_Shift')
            ->where('s.Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('s.Kode_Karyawan', $kodeKaryawan)
            ->where('s.Tanggal', $tgl)
            ->orderByDesc('s.created_at')->orderByDesc('s.Urut')
            ->get(['s.ID_Shift', 'sk.Nama', 's.Flag_Aktif', 's.No_Transaksi_Reference', 's.created_at', 's.Nonaktif_By', 's.Nonaktif_At', 's.Nonaktif_Reason']);

        $permanen = DB::table('HRIS_Shift_Per_Karyawan as p')
            ->leftJoin('HRIS_Shift_Kerja as sk', 'sk.ID_Shift', '=', 'p.ID_Shift')
            ->where('p.Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('p.Kode_Karyawan', $kodeKaryawan)
            ->where('p.Periode', '<=', $tgl)
            ->orderByDesc('p.Periode')->orderByDesc('p.Urut')
            ->limit(self::RIWAYAT_PERMANEN)
            ->selectRaw('p.ID_Shift, sk.Nama, p.Periode, p.Flag_Aktif, p.No_Transaksi_Reference, p.created_at, p.Nonaktif_By, p.Nonaktif_At, p.Nonaktif_Reason,'
                .' CASE WHEN EXISTS (SELECT 1 FROM HRIS_Shift_Kerja_Detail skd WHERE skd.ID_Shift = p.ID_Shift AND skd.Hari = ?) THEN 1 ELSE 0 END AS Punya_Hari,'
                .' (SELECT TOP 1 skd.Flag_Default_Off FROM HRIS_Shift_Kerja_Detail skd WHERE skd.ID_Shift = p.ID_Shift AND skd.Hari = ?) AS Default_Off', [$hari, $hari])
            ->get();

        $statusHari = DB::table('N_HRIS_Status_Hari_Karyawan')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Tgl_Mulai', '<=', $tgl)
            ->where(fn ($q) => $q->whereNull('Tgl_Selesai')->orWhere('Tgl_Selesai', '>=', $tgl))
            ->where(fn ($q) => $q->whereNull('Hari')->orWhere('Hari', $hari))
            // Urutan pemenang sama dengan fungsi: rentang tertutup dulu, lalu terbaru.
            ->orderByRaw('CASE WHEN Tgl_Selesai IS NULL THEN 2 ELSE 1 END')
            ->orderByDesc('Tgl_Mulai')->orderByDesc('Created_At')
            ->get(['Id', 'Status_Hari', 'Hari', 'Tgl_Mulai', 'Tgl_Selesai', 'Flag_Aktif', 'No_Transaksi_Reference', 'Keterangan', 'Created_At', 'Created_By', 'Updated_At', 'Updated_By']);

        $konversi = DB::table('N_HRIS_Konversi_Lembur_Efektif')
            ->where('Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Tanggal_Off', $tgl)
            ->where('Jenis_Konversi', 'DAY_OFF')
            ->orderByDesc('Id')
            ->get(['No_Transaksi_Reference', 'Tanggal_Lembur_Asal', 'Flag_Aktif', 'Created_At', 'Created_By']);

        $libur = DB::table('HRIS_Hari_Libur')
            ->whereRaw('RTRIM(Kode_Perusahaan) = ?', [self::KODE_PERUSAHAAN])
            ->whereRaw('CAST(Tanggal AS date) = ?', [$tgl])
            ->value('Nama_Hari_Libur');

        $menunggu = $this->pengajuanMenunggu($kodeKaryawan, $tgl);

        // Shift penentu "libur bawaan" — aturan PERSIS blok shift_eff di
        // N_HRIS_FN_GetStatusHari_Inline (permanen saja, tanpa saringan Flag_Aktif).
        $shiftBawaan = DB::table('HRIS_Shift_Per_Karyawan as spk')
            ->join('HRIS_Shift_Kerja_Detail as skd', 'skd.ID_Shift', '=', 'spk.ID_Shift')
            ->leftJoin('HRIS_Shift_Kerja as sk', 'sk.ID_Shift', '=', 'spk.ID_Shift')
            ->where('spk.Kode_Perusahaan', self::KODE_PERUSAHAAN)
            ->where('spk.Kode_Karyawan', $kodeKaryawan)
            ->where('spk.Periode', '<=', $tgl)
            ->where('skd.Hari', $hari)
            ->orderByDesc('spk.Periode')->orderByDesc('spk.ID_Shift')
            ->first(['sk.Nama', 'skd.Flag_Default_Off', 'spk.Flag_Aktif']);

        $nomor = collect([$sementara, $permanen, $statusHari, $konversi])->flatten(1)->pluck('No_Transaksi_Reference')
            ->merge($menunggu->pluck('no'))->filter()->unique()->values()->all();
        $transaksi = $this->petaTransaksi($nomor);
        $pelaku = $this->petaPelaku(collect([
            $sementara->pluck('Nonaktif_By'), $permanen->pluck('Nonaktif_By'),
            $statusHari->pluck('Created_By'), $statusHari->pluck('Updated_By'),
            $konversi->pluck('Created_By'), collect($transaksi)->pluck('created_by'),
        ])->flatten()->all());

        $statusAktif = $statusHari->firstWhere('Flag_Aktif', 'Y');
        $sebab = self::sebabStatus($hasil->StatusHari ?? null, $statusAktif !== null);
        $permanenBerlaku = $permanen->first(fn ($p) => $p->Flag_Aktif === 'Y' && (int) $p->Punya_Hari === 1);
        $sementaraBerlaku = $sementara->firstWhere('Flag_Aktif', 'Y');
        $konversiAktif = $konversi->firstWhere('Flag_Aktif', 'Y');
        $defaultOff = $shiftBawaan && $shiftBawaan->Flag_Default_Off === 'Y';
        $cutiBersama = $sebab['sebab'] === 'CUTI_BERSAMA' ? $this->namaCutiBersama($tgl) : null;
        $waktu = fn ($v) => $v ? Carbon::parse($v)->format('d-m-Y H:i') : '';
        $txLabel = fn (?string $no) => $no ? ($transaksi[$no]['label'] ?? self::labelTransaksi($no, null, null)).' · '.$no : self::labelTransaksi(null, null, null);

        // Aturan berprioritas lebih rendah yang sebenarnya ada tapi kalah.
        $prioritas = ['KONVERSI_LEMBUR' => 1, 'STATUS_HARI' => 2, 'HARI_LIBUR' => 3, 'CUTI_BERSAMA' => 4, 'SHIFT_DEFAULT_OFF' => 5, 'BAWAAN' => 99];
        $menang = $prioritas[$sebab['sebab']] ?? 99;
        $tertimpa = array_filter([
            $konversiAktif && $menang < 1 ? 'libur pengganti lembur' : null,
            $statusAktif && $menang < 2 ? 'status hari khusus ('.($statusAktif->Status_Hari === 'OFF' ? 'libur' : 'masuk').')' : null,
            $libur !== null && $menang < 3 ? 'hari libur nasional ('.$libur.')' : null,
            $defaultOff && $menang < 5 ? 'libur bawaan shift '.($shiftBawaan->Nama ?? '') : null,
        ]);

        $penjelasan = self::penjelasan($sebab['sebab'], $sebab['kategori'], [
            'shift' => $hasil->Nama_Shift ?? null,
            'shift_default' => $shiftBawaan->Nama ?? null,
            'hari' => self::NAMA_HARI[$hari] ?? null,
            'konversi_asal' => $konversiAktif ? Carbon::parse($konversiAktif->Tanggal_Lembur_Asal)->format('d-m-Y') : null,
            'konversi_no' => $konversiAktif->No_Transaksi_Reference ?? null,
            'status_pelaku' => $statusAktif ? ($this->namaPelaku($statusAktif->Created_By, $pelaku)
                ?? ($statusAktif->No_Transaksi_Reference && isset($transaksi[$statusAktif->No_Transaksi_Reference]) ? $this->namaPelaku($transaksi[$statusAktif->No_Transaksi_Reference]['created_by'], $pelaku) : null)) : null,
            'status_waktu' => $statusAktif ? $waktu($statusAktif->Created_At) : null,
            'status_transaksi' => $statusAktif ? $txLabel($statusAktif->No_Transaksi_Reference) : null,
            'status_rentang' => $statusAktif ? $this->rentangStatus($statusAktif) : null,
            'status_keterangan' => $statusAktif->Keterangan ?? null,
            'libur_nasional' => $libur,
            'cuti_bersama' => $cutiBersama,
            'tertimpa' => array_values($tertimpa),
        ]);

        // Asal shift yang dipakai: kapan & oleh siapa dipasang.
        $asalShift = $sementaraBerlaku ?? $permanenBerlaku;
        if ($asalShift) {
            $adaTx = ! empty($asalShift->No_Transaksi_Reference);
            $txTeks = $adaTx ? ' lewat '.$txLabel($asalShift->No_Transaksi_Reference) : '';
            $oleh = $adaTx && isset($transaksi[$asalShift->No_Transaksi_Reference])
                ? $this->namaPelaku($transaksi[$asalShift->No_Transaksi_Reference]['created_by'], $pelaku)
                : null;
            $olehTeks = $oleh ? ' oleh '.$oleh : ($adaTx ? '' : ' — pemasangnya tidak tercatat (input langsung/impor, tanpa transaksi)');

            $penjelasan['rincian'][] = 'Shift '.($asalShift->Nama ?? '—').' ('.($sementaraBerlaku ? 'sementara' : 'permanen').') dipasang '
                .$waktu($asalShift->created_at)
                .$txTeks.$olehTeks.'.';

            // Catatan bila ada shift permanen lain yang dibuat lebih baru tapi periodenya mendahului
            if (! $sementaraBerlaku && $permanenBerlaku) {
                $txLebihBaru = $permanen->first(function ($p) use ($permanenBerlaku) {
                    return $p !== $permanenBerlaku
                        && $p->created_at > $permanenBerlaku->created_at
                        && $p->Periode < $permanenBerlaku->Periode;
                });
                if ($txLebihBaru) {
                    $penjelasan['rincian'][] = 'Catatan: Shift '.($permanenBerlaku->Nama ?? '').' dibuat pada '
                        .$waktu($permanenBerlaku->created_at).' untuk periode mulai '.Carbon::parse($permanenBerlaku->Periode)->format('d-m-Y')
                        .', sehingga tetap berlaku melewati shift '.($txLebihBaru->Nama ?? '').' (periode '.Carbon::parse($txLebihBaru->Periode)->format('d-m-Y').').';
                }
            }
        }

        $peringatan = [];
        if ($sebab['sebab'] === 'SHIFT_DEFAULT_OFF' && $sementaraBerlaku) {
            $peringatan[] = 'Hari ini dihitung libur karena shift PERMANEN-nya libur di hari ini, walau ada shift sementara '
                .($sementaraBerlaku->Nama ?? '').'. Hubungi admin HCIS bila ini tidak sesuai.';
        }
        // Fungsi status hari memilih shift permanen terakhir yang MENGATUR hari ini, tanpa
        // memeriksa Flag_Aktif — bisa jatuh ke shift lama yang sudah dinonaktifkan.
        if ($shiftBawaan && $shiftBawaan->Flag_Aktif !== 'Y') {
            $peringatan[] = 'Hari libur/masuk dihitung dari shift permanen '.($shiftBawaan->Nama ?? '').' yang sudah TIDAK aktif'
                .($permanenBerlaku ? ' (shift permanen yang berlaku: '.($permanenBerlaku->Nama ?? '').')' : '')
                .'. Ini keterbatasan aturan sistem saat ini — laporkan ke admin HCIS.';
        } elseif ($shiftBawaan && $permanenBerlaku && ($shiftBawaan->Flag_Default_Off === 'Y') !== ($permanenBerlaku->Default_Off === 'Y')) {
            // Beberapa shift permanen aktif bertanggal mulai sama: jam kerja diambil dari
            // Urut terbesar, hari libur dari ID_Shift terbesar — hasilnya bisa bertentangan.
            $peringatan[] = 'Ada lebih dari satu shift permanen aktif yang berlaku mulai tanggal yang sama. Jam kerja memakai '
                .($permanenBerlaku->Nama ?? '').', tetapi hari libur/masuk memakai '.($shiftBawaan->Nama ?? '')
                .' — keduanya berbeda untuk hari ini. Minta admin HCIS merapikan shift permanen karyawan ini.';
        }

        return [
            'karyawan' => ['kode' => $karyawan->Kode_Karyawan, 'nama' => $karyawan->Nama],
            'tanggal' => $tgl,
            'hasil' => [
                'shift_id' => $hasil->ID_Shift ?? null,
                'shift_nama' => $hasil->Nama_Shift ?? null,
                'jam_masuk' => $this->jam($hasil->Jam_Masuk ?? null),
                'jam_keluar' => $this->jam($hasil->Jam_Keluar ?? null),
                'sumber_shift' => $hasil->JENIS_SHIFT_DIPILIH ?? null,
                'status_hari' => $hasil->StatusHari ?? null,
                'kategori' => $sebab['kategori'],
                'sebab' => $sebab['sebab'],
                'penjelasan' => $penjelasan,
            ],
            'lapisan' => $this->lapisan($sebab['sebab'], $sementaraBerlaku, $permanenBerlaku, $statusAktif, $konversi, $libur, $transaksi, $defaultOff, $shiftBawaan->Nama ?? null, $cutiBersama),
            'linimasa' => $this->susunLinimasa($sementara, $permanen, $statusHari, $konversi, $menunggu, $transaksi, $pelaku, $permanenBerlaku),
            'peringatan' => $peringatan,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function lapisan(string $sebab, ?object $sementara, ?object $permanen, ?object $statusAktif, Collection $konversi, ?string $libur, array $transaksi, bool $defaultOff = false, ?string $shiftBawaan = null, ?string $cutiBersama = null): array
    {
        $sumber = fn (?string $no) => $no ? ['no' => $no, 'label' => $transaksi[$no]['label'] ?? self::labelTransaksi($no, null, null)] : null;
        $konversiAktif = $konversi->firstWhere('Flag_Aktif', 'Y');

        return [
            [
                'kelompok' => 'STATUS', 'kode' => 'KONVERSI_LEMBUR', 'judul' => 'Libur pengganti lembur',
                'ada' => $konversiAktif !== null, 'berlaku' => $sebab === 'KONVERSI_LEMBUR',
                'keterangan' => $konversiAktif ? 'Dari lembur tanggal '.Carbon::parse($konversiAktif->Tanggal_Lembur_Asal)->toDateString() : null,
                'transaksi' => $sumber($konversiAktif->No_Transaksi_Reference ?? null),
            ],
            [
                'kelompok' => 'STATUS', 'kode' => 'STATUS_HARI', 'judul' => 'Status hari khusus',
                'ada' => $statusAktif !== null, 'berlaku' => $sebab === 'STATUS_HARI',
                'keterangan' => $statusAktif ? trim(($statusAktif->Status_Hari === 'OFF' ? 'Libur' : 'Masuk').($statusAktif->Keterangan ? ' — '.$statusAktif->Keterangan : '')) : null,
                'transaksi' => $sumber($statusAktif->No_Transaksi_Reference ?? null),
            ],
            [
                'kelompok' => 'STATUS', 'kode' => 'HARI_LIBUR', 'judul' => 'Hari libur nasional',
                'ada' => $libur !== null, 'berlaku' => $sebab === 'HARI_LIBUR', 'keterangan' => $libur, 'transaksi' => null,
            ],
            [
                'kelompok' => 'STATUS', 'kode' => 'CUTI_BERSAMA', 'judul' => 'Cuti bersama',
                'ada' => $sebab === 'CUTI_BERSAMA', 'berlaku' => $sebab === 'CUTI_BERSAMA', 'keterangan' => $cutiBersama, 'transaksi' => null,
            ],
            [
                'kelompok' => 'STATUS', 'kode' => 'SHIFT_DEFAULT_OFF', 'judul' => 'Hari libur bawaan shift',
                'ada' => $defaultOff || $sebab === 'SHIFT_DEFAULT_OFF', 'berlaku' => $sebab === 'SHIFT_DEFAULT_OFF',
                'keterangan' => $shiftBawaan ? 'Shift permanen '.$shiftBawaan.' libur di hari ini' : null, 'transaksi' => null,
            ],
            [
                'kelompok' => 'SHIFT', 'kode' => 'SHIFT_SEMENTARA', 'judul' => 'Shift sementara',
                'ada' => $sementara !== null, 'berlaku' => $sementara !== null,
                'keterangan' => $sementara->Nama ?? null, 'transaksi' => $sumber($sementara->No_Transaksi_Reference ?? null),
            ],
            [
                'kelompok' => 'SHIFT', 'kode' => 'SHIFT_PERMANEN', 'judul' => 'Shift permanen',
                'ada' => $permanen !== null, 'berlaku' => $permanen !== null && $sementara === null,
                'keterangan' => $permanen ? ($permanen->Nama ?? '').' sejak '.Carbon::parse($permanen->Periode)->toDateString() : null,
                'transaksi' => $sumber($permanen->No_Transaksi_Reference ?? null),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function susunLinimasa(Collection $sementara, Collection $permanen, Collection $statusHari, Collection $konversi, Collection $menunggu, array $transaksi, array $pelaku, ?object $permanenBerlaku = null): array
    {
        $acara = [];
        $tx = fn (?string $no) => $no ? ['no' => $no, 'label' => $transaksi[$no]['label'] ?? self::labelTransaksi($no, null, null)] : ['no' => null, 'label' => self::labelTransaksi(null, null, null)];
        $olehTx = fn (?string $no) => $no && isset($transaksi[$no]) ? $this->namaPelaku($transaksi[$no]['created_by'], $pelaku) : null;

        // 1. Shift Sementara: lebur pasangan nonaktif + pasang di waktu sama jadi satu event "Ganti / Perbarui"
        $semAsc = $sementara->sortBy(fn ($s) => [$s->created_at, $s->Urut ?? 0])->values();
        $semTerganti = []; // index lama => index baru yang menggantikannya

        for ($i = 1; $i < $semAsc->count(); $i++) {
            $curr = $semAsc[$i];
            for ($j = $i - 1; $j >= 0; $j--) {
                if (isset($semTerganti[$j])) {
                    continue;
                }
                $prev = $semAsc[$j];
                if ($prev->Flag_Aktif !== 'Y' && $prev->Nonaktif_At !== null) {
                    $selisih = abs(Carbon::parse($prev->Nonaktif_At)->diffInSeconds(Carbon::parse($curr->created_at)));
                    $isReplaced = $prev->Nonaktif_Reason === 'Replaced by new temporary shift';
                    if ($isReplaced || $selisih <= 120) {
                        $semTerganti[$j] = $i;
                        break;
                    }
                }
            }
        }

        // Cek apakah ada shift sementara yang dinonaktifkan karena dipasang shift permanen baru
        $semDigantiPermanen = []; // index sementara => object permanen
        foreach ($semAsc as $j => $s) {
            if ($s->Flag_Aktif !== 'Y' && ! isset($semTerganti[$j]) && $s->Nonaktif_At !== null) {
                if ($s->Nonaktif_Reason === 'Replaced by permanent shift') {
                    foreach ($permanen as $p) {
                        $selisih = abs(Carbon::parse($s->Nonaktif_At)->diffInSeconds(Carbon::parse($p->created_at)));
                        if ($selisih <= 120) {
                            $semDigantiPermanen[$j] = $p;
                            break;
                        }
                    }
                }
            }
        }

        foreach ($semAsc as $idx => $s) {
            $prevIdx = array_search($idx, $semTerganti, true);
            if ($prevIdx !== false) {
                $prev = $semAsc[$prevIdx];
                $namaPrev = $prev->Nama ?? $prev->ID_Shift;
                $namaCurr = $s->Nama ?? $s->ID_Shift;
                $txLama = $prev->No_Transaksi_Reference ? ' ('.$prev->No_Transaksi_Reference.')' : '';
                if ($namaPrev === $namaCurr) {
                    $aksi = 'PERBARUI';
                    $judul = 'Shift sementara '.$namaCurr.' diperbarui';
                } else {
                    $aksi = 'GANTI';
                    $judul = "Shift sementara diganti: {$namaPrev} → {$namaCurr}";
                }
                $alasan = "Menggantikan pengajuan sebelumnya{$txLama}";
                $acara[] = [
                    'waktu' => $s->created_at,
                    'aksi' => $aksi,
                    'lapisan' => 'SHIFT_SEMENTARA',
                    'judul' => $judul,
                    'pelaku' => $olehTx($s->No_Transaksi_Reference),
                    'transaksi' => $tx($s->No_Transaksi_Reference),
                    'alasan' => $alasan,
                ];
            } else {
                $acara[] = [
                    'waktu' => $s->created_at,
                    'aksi' => 'PASANG',
                    'lapisan' => 'SHIFT_SEMENTARA',
                    'judul' => 'Shift sementara '.($s->Nama ?? $s->ID_Shift).' dipasang',
                    'pelaku' => $olehTx($s->No_Transaksi_Reference),
                    'transaksi' => $tx($s->No_Transaksi_Reference),
                    'alasan' => null,
                ];
            }

            // Jika dinonaktifkan tanpa digantikan oleh shift sementara atau permanen lain (misal dibatalkan):
            if ($s->Flag_Aktif !== 'Y' && ! isset($semTerganti[$idx]) && ! isset($semDigantiPermanen[$idx])) {
                $acara[] = [
                    'waktu' => $s->Nonaktif_At,
                    'aksi' => 'NONAKTIF',
                    'lapisan' => 'SHIFT_SEMENTARA',
                    'judul' => 'Shift sementara '.($s->Nama ?? $s->ID_Shift).' dinonaktifkan',
                    'pelaku' => $this->namaPelaku($s->Nonaktif_By, $pelaku),
                    'transaksi' => $tx($s->No_Transaksi_Reference),
                    'alasan' => self::alasanNonaktif($s->Nonaktif_Reason),
                ];
            }
        }

        // 2. Shift Permanen
        $permAsc = $permanen->sortBy(fn ($p) => [Carbon::parse($p->Periode)->format('Y-m-d'), $p->created_at])->values();
        $permTerganti = []; // index lama => index baru
        for ($i = 1; $i < $permAsc->count(); $i++) {
            $curr = $permAsc[$i];
            for ($j = $i - 1; $j >= 0; $j--) {
                if (isset($permTerganti[$j])) {
                    continue;
                }
                $prev = $permAsc[$j];
                if ($prev->Flag_Aktif !== 'Y' && $prev->Nonaktif_At !== null) {
                    $selisih = abs(Carbon::parse($prev->Nonaktif_At)->diffInSeconds(Carbon::parse($curr->created_at)));
                    if ($selisih <= 120) {
                        $permTerganti[$j] = $i;
                        break;
                    }
                }
            }
        }

        foreach ($permAsc as $idx => $p) {
            $isBerlakuTanggalIni = $permanenBerlaku
                && $p->ID_Shift == $permanenBerlaku->ID_Shift
                && $p->Periode == $permanenBerlaku->Periode;

            $alasan = (int) $p->Punya_Hari === 1 ? null : 'Shift ini tidak mengatur hari ini, jadi tidak dipakai untuk tanggal ini';

            $prevIdx = array_search($idx, $permTerganti, true);
            $prevP = $prevIdx !== false ? $permAsc[$prevIdx] : null;

            $sTerganti = null;
            foreach ($semDigantiPermanen as $j => $permObj) {
                if ($permObj === $p) {
                    $sTerganti = $semAsc[$j];
                    break;
                }
            }

            $dibuatTeks = $p->created_at ? ' · Dibuat '.Carbon::parse($p->created_at)->format('d-m-Y H:i') : '';

            if ($prevP !== null) {
                $namaPrev = $prevP->Nama ?? $prevP->ID_Shift;
                $namaCurr = $p->Nama ?? $p->ID_Shift;
                $txLama = $prevP->No_Transaksi_Reference ? ' ('.$prevP->No_Transaksi_Reference.')' : '';
                $aksi = $namaPrev === $namaCurr ? 'PERBARUI' : 'GANTI';
                $judul = $namaPrev === $namaCurr
                    ? 'Shift permanen '.$namaCurr.' diperbarui (berlaku mulai '.Carbon::parse($p->Periode)->toDateString().')'
                    : "Shift permanen diganti: {$namaPrev} → {$namaCurr} (berlaku mulai ".Carbon::parse($p->Periode)->toDateString().')';
                $alasan = 'Menggantikan shift permanen sebelumnya'.$txLama.$dibuatTeks;
            } elseif ($sTerganti !== null) {
                $txLama = $sTerganti->No_Transaksi_Reference ? ' ('.$sTerganti->No_Transaksi_Reference.')' : '';
                $aksi = 'GANTI';
                $judul = 'Shift permanen '.($p->Nama ?? $p->ID_Shift).' berlaku mulai '.Carbon::parse($p->Periode)->toDateString();
                $alasan = 'Menggantikan shift sementara '.($sTerganti->Nama ?? $sTerganti->ID_Shift).$txLama.$dibuatTeks;
            } elseif ($isBerlakuTanggalIni) {
                $aksi = 'BERLAKU';
                $judul = 'Shift permanen '.($p->Nama ?? $p->ID_Shift).' berlaku mulai '.Carbon::parse($p->Periode)->toDateString();
                $alasan = 'Shift aktif yang menaungi tanggal ini (periode mulai '.Carbon::parse($p->Periode)->toDateString().')'.$dibuatTeks;
            } else {
                $aksi = 'LAMPAU';
                $judul = 'Shift permanen '.($p->Nama ?? $p->ID_Shift).' berlaku mulai '.Carbon::parse($p->Periode)->toDateString();
                $alasan = (int) $p->Punya_Hari === 1
                    ? 'Pernah berlaku mulai '.Carbon::parse($p->Periode)->toDateString().' (terlewati oleh periode shift berikutnya)'.$dibuatTeks
                    : 'Shift ini tidak mengatur hari ini, jadi tidak dipakai untuk tanggal ini';
            }

            // Waktu urutan: gunakan tanggal efektif Periode agar linimasa tersusun kronologis sesuai tanggal berlaku
            $waktu = Carbon::parse($p->Periode)->format('Y-m-d').' '.($p->created_at ? Carbon::parse($p->created_at)->format('H:i:s') : '00:00:00');

            $acara[] = [
                'waktu' => $waktu,
                'aksi' => $aksi,
                'lapisan' => 'SHIFT_PERMANEN',
                'judul' => $judul,
                'pelaku' => $olehTx($p->No_Transaksi_Reference),
                'transaksi' => $tx($p->No_Transaksi_Reference),
                'alasan' => $alasan,
            ];

            if ($p->Flag_Aktif !== 'Y' && ! isset($permTerganti[$idx])) {
                $acara[] = [
                    'waktu' => $p->Nonaktif_At,
                    'aksi' => 'NONAKTIF',
                    'lapisan' => 'SHIFT_PERMANEN',
                    'judul' => 'Shift permanen '.($p->Nama ?? $p->ID_Shift).' dinonaktifkan',
                    'pelaku' => $this->namaPelaku($p->Nonaktif_By, $pelaku),
                    'transaksi' => $tx($p->No_Transaksi_Reference),
                    'alasan' => self::alasanNonaktif($p->Nonaktif_Reason),
                ];
            }
        }

        // 3. Status Hari: lebur pembaruan yang menonaktifkan status hari lama
        $statAsc = $statusHari->sortBy(fn ($h) => [$h->Created_At, $h->Id])->values();
        $statTerganti = []; // index lama => index baru

        for ($i = 1; $i < $statAsc->count(); $i++) {
            $curr = $statAsc[$i];
            for ($j = $i - 1; $j >= 0; $j--) {
                if (isset($statTerganti[$j])) {
                    continue;
                }
                $prev = $statAsc[$j];
                if ($prev->Flag_Aktif !== 'Y' && $prev->Updated_At !== null) {
                    $selisih = abs(Carbon::parse($prev->Updated_At)->diffInSeconds(Carbon::parse($curr->Created_At)));
                    if ($selisih <= 120) {
                        $statTerganti[$j] = $i;
                        break;
                    }
                }
            }
        }

        foreach ($statAsc as $idx => $h) {
            $label = $h->Status_Hari === 'OFF' ? 'libur' : 'masuk';
            $rentang = $this->rentangStatus($h);
            $prevIdx = array_search($idx, $statTerganti, true);

            if ($prevIdx !== false) {
                $prev = $statAsc[$prevIdx];
                $labelPrev = $prev->Status_Hari === 'OFF' ? 'libur' : 'masuk';
                $txLama = $prev->No_Transaksi_Reference ? ' ('.$prev->No_Transaksi_Reference.')' : '';
                if ($prev->Status_Hari === $h->Status_Hari) {
                    $aksi = 'PERBARUI';
                    $judul = "Status hari {$label} diperbarui ({$rentang})";
                } else {
                    $aksi = 'GANTI';
                    $judul = 'Status hari diganti: '.ucfirst($labelPrev).' → '.ucfirst($label)." ({$rentang})";
                }
                $alasan = "Menggantikan status hari sebelumnya{$txLama}".($h->Keterangan ? ' — '.$h->Keterangan : '');
                $acara[] = [
                    'waktu' => $h->Created_At,
                    'aksi' => $aksi,
                    'lapisan' => 'STATUS_HARI',
                    'judul' => $judul,
                    'pelaku' => $this->namaPelaku($h->Created_By, $pelaku) ?? $olehTx($h->No_Transaksi_Reference),
                    'transaksi' => $tx($h->No_Transaksi_Reference),
                    'alasan' => $alasan,
                ];
            } else {
                $acara[] = [
                    'waktu' => $h->Created_At,
                    'aksi' => 'PASANG',
                    'lapisan' => 'STATUS_HARI',
                    'judul' => "Status hari ditetapkan {$label} ({$rentang})",
                    'pelaku' => $this->namaPelaku($h->Created_By, $pelaku) ?? $olehTx($h->No_Transaksi_Reference),
                    'transaksi' => $tx($h->No_Transaksi_Reference),
                    'alasan' => $h->Keterangan,
                ];
            }

            if ($h->Flag_Aktif !== 'Y' && ! isset($statTerganti[$idx])) {
                $acara[] = [
                    'waktu' => $h->Updated_At,
                    'aksi' => 'NONAKTIF',
                    'lapisan' => 'STATUS_HARI',
                    'judul' => "Status hari {$label} dinonaktifkan",
                    'pelaku' => $this->namaPelaku($h->Updated_By, $pelaku),
                    'transaksi' => $tx($h->No_Transaksi_Reference),
                    'alasan' => null,
                ];
            }
        }

        // 4. Konversi Lembur
        foreach ($konversi as $k) {
            $acara[] = ['waktu' => $k->Created_At, 'aksi' => $k->Flag_Aktif === 'Y' ? 'PASANG' : 'NONAKTIF', 'lapisan' => 'KONVERSI_LEMBUR',
                'judul' => $k->Flag_Aktif === 'Y' ? 'Libur pengganti dari konversi lembur' : 'Libur pengganti dari konversi lembur (sudah dibatalkan)',
                'pelaku' => $this->namaPelaku($k->Created_By, $pelaku), 'transaksi' => $tx($k->No_Transaksi_Reference), 'alasan' => null];
        }

        // 5. Pengajuan Menunggu
        foreach ($menunggu as $m) {
            $acara[] = ['waktu' => $m['waktu'], 'aksi' => 'MENUNGGU', 'lapisan' => $m['lapisan'],
                'judul' => $m['judul'], 'pelaku' => $olehTx($m['no']), 'transaksi' => $tx($m['no']),
                'alasan' => 'Belum berlaku — menunggu persetujuan'];
        }

        foreach ($acara as &$a) {
            $a['waktu'] = $a['waktu'] ? Carbon::parse($a['waktu'])->format('Y-m-d H:i') : null;
            $a['pelaku'] = $a['pelaku'] ?: self::PELAKU_TAK_TERCATAT;
        }
        unset($a);

        usort($acara, fn ($x, $y) => strcmp((string) $y['waktu'], (string) $x['waktu']));

        return $acara;
    }

    private function rentangStatus(object $h): string
    {
        $mulai = Carbon::parse($h->Tgl_Mulai)->format('d-m-Y');
        $selesai = $h->Tgl_Selesai ? Carbon::parse($h->Tgl_Selesai)->format('d-m-Y') : null;
        $setiap = $h->Hari ? 'setiap hari '.(self::NAMA_HARI[(int) $h->Hari] ?? $h->Hari).' ' : '';

        if ($selesai === $mulai) {
            return 'hanya tanggal ini';
        }

        return $setiap.($selesai ? "dari {$mulai} s/d {$selesai}" : "mulai {$mulai} tanpa batas akhir");
    }

    /** Nama cuti bersama yang meliputi tanggal ini (fungsi status hari sudah memastikan karyawannya ikut). */
    private function namaCutiBersama(string $tgl): ?string
    {
        $r = DB::selectOne(
            "SELECT TOP 1 Nama_Cuti_Bersama FROM N_HRIS_Cuti_Bersama WHERE Flag_Aktif = 'Y' AND ("
            .' (Selected_Dates IS NOT NULL AND ISJSON(Selected_Dates) = 1 AND EXISTS (SELECT 1 FROM OPENJSON(Selected_Dates) WHERE TRY_CAST([value] AS date) = ?))'
            .' OR ((Selected_Dates IS NULL OR ISJSON(Selected_Dates) = 0) AND ? BETWEEN Tanggal_Mulai AND Tanggal_Selesai))'
            .' ORDER BY Id_Cuti_Bersama DESC',
            [$tgl, $tgl],
        );

        return $r->Nama_Cuti_Bersama ?? null;
    }

    /**
     * Pengajuan untuk tanggal ini yang belum berlaku (belum diterapkan, tak ditolak, tak dibatalkan).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function pengajuanMenunggu(string $kodeKaryawan, string $tgl): Collection
    {
        $tunda = "ISNULL(det.Flag_Applied, '') <> 'Y' AND ISNULL(det.Flag_Approval, '') <> 'T' AND ISNULL(d.Flag_Approval, '') <> 'T'";

        $shift = DB::table('N_HRIS_Transaksi_Shift_Det as det')
            ->join('N_HRIS_Transaksi_Shift_Detail as d', 'd.Id', '=', 'det.Id_Detail')
            ->join('N_HRIS_Transaksi_Shift as h', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('HRIS_Shift_Kerja as sk', 'sk.ID_Shift', '=', 'det.Id_Shift_Diminta')
            ->where('d.Kode_Karyawan', $kodeKaryawan)->where('det.Tanggal_Shift', $tgl)
            ->whereNull('h.Status')->whereNull('d.Status')->whereNull('det.Status')->whereRaw($tunda)
            ->get(['h.No_Transaksi', 'h.Created_At', 'sk.Nama'])
            ->map(fn ($r) => ['no' => $r->No_Transaksi, 'waktu' => $r->Created_At, 'lapisan' => 'SHIFT', 'judul' => 'Pengajuan ganti shift ke '.($r->Nama ?? '—')]);

        $status = DB::table('N_HRIS_Transaksi_Status_Hari_Det as det')
            ->join('N_HRIS_Transaksi_Status_Hari_Detail as d', 'd.Id', '=', 'det.Id_Detail')
            ->join('N_HRIS_Transaksi_Status_Hari as h', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('d.Kode_Karyawan', $kodeKaryawan)->where('det.Tanggal', $tgl)
            ->whereNull('h.Status')->whereNull('d.Status')->whereNull('det.Status')->whereRaw($tunda)
            ->get(['h.No_Transaksi', 'h.Created_At', 'det.Status_Hari'])
            ->map(fn ($r) => ['no' => $r->No_Transaksi, 'waktu' => $r->Created_At, 'lapisan' => 'STATUS_HARI', 'judul' => 'Pengajuan status hari '.($r->Status_Hari === 'OFF' ? 'libur' : 'masuk')]);

        $tukar = DB::table('N_HRIS_Transaksi_Tukar_Hari_Detail as d')
            ->join('N_HRIS_Transaksi_Tukar_Hari as h', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->where('h.Kode_Karyawan', $kodeKaryawan)
            ->where(fn ($q) => $q->where('d.Tanggal_Bekerja', $tgl)->orWhere('d.Tanggal_Off', $tgl))
            ->whereNull('h.Status')->whereNull('d.Status')
            ->whereRaw("ISNULL(d.Flag_Applied, '') <> 'Y' AND ISNULL(d.Flag_Approval, '') <> 'T'")
            ->get(['h.No_Transaksi', 'h.Created_At', 'd.Tanggal_Bekerja', 'd.Tanggal_Off'])
            ->map(fn ($r) => ['no' => $r->No_Transaksi, 'waktu' => $r->Created_At, 'lapisan' => 'STATUS_HARI',
                'judul' => 'Pengajuan tukar hari: tanggal ini menjadi '.(Carbon::parse($r->Tanggal_Off)->toDateString() === $tgl ? 'libur' : 'masuk')]);

        return $shift->concat($status)->concat($tukar)->values();
    }

    /**
     * Pengaju & jenis tiap nomor transaksi rujukan, sekali kueri per tabel.
     *
     * @param  array<int, string>  $nomor
     * @return array<string, array{label: string, created_by: ?string}>
     */
    private function petaTransaksi(array $nomor): array
    {
        $peta = [];
        $kelompok = ['N_HRIS_Transaksi_Shift' => [], 'N_HRIS_Transaksi_Status_Hari' => [], 'N_HRIS_Transaksi_Tukar_Hari' => [], 'N_HRIS_Transaksi_Konversi_Lembur' => []];
        foreach ($nomor as $no) {
            $tabel = match (true) {
                str_starts_with($no, 'TSH') => 'N_HRIS_Transaksi_Status_Hari',
                str_starts_with($no, 'TH') => 'N_HRIS_Transaksi_Tukar_Hari',
                str_starts_with($no, 'KL') => 'N_HRIS_Transaksi_Konversi_Lembur',
                default => 'N_HRIS_Transaksi_Shift',
            };
            $kelompok[$tabel][] = $no;
        }

        foreach ($kelompok as $tabel => $daftar) {
            if ($daftar === []) {
                continue;
            }
            $kolom = match ($tabel) {
                'N_HRIS_Transaksi_Shift', 'N_HRIS_Transaksi_Status_Hari' => ['No_Transaksi', 'Created_By', 'Jenis_Transaksi', 'Flag_Admin'],
                'N_HRIS_Transaksi_Tukar_Hari' => ['No_Transaksi', 'Created_By', 'Jenis_Transaksi'],
                default => ['No_Transaksi', 'Created_By'],
            };
            foreach (array_chunk($daftar, 500) as $potongan) {
                foreach (DB::table($tabel)->whereIn('No_Transaksi', $potongan)->get($kolom) as $r) {
                    $peta[$r->No_Transaksi] = [
                        'label' => self::labelTransaksi($r->No_Transaksi, $r->Jenis_Transaksi ?? null, $r->Flag_Admin ?? null),
                        'created_by' => $r->Created_By !== null ? (string) $r->Created_By : null,
                    ];
                }
            }
        }

        return $peta;
    }

    /**
     * Nama untuk sederet nilai pelaku (Id_Users atau Kode_Karyawan), sekali kueri.
     *
     * @return array<string, string> berkunci 'id:4' / 'kode:H45'
     */
    private function petaPelaku(array $nilai): array
    {
        $id = [];
        $kode = [];
        foreach ($nilai as $v) {
            $p = self::jenisPelaku($v === null ? null : (string) $v);
            if ($p) {
                $p[0] === 'id' ? $id[(int) $p[1]] = true : $kode[$p[1]] = true;
            }
        }
        if ($id === [] && $kode === []) {
            return [];
        }

        $baris = DB::table('Karyawan')
            ->where(function ($q) use ($id, $kode) {
                if ($id !== []) {
                    $q->orWhereIn('UserID_Web', array_keys($id));
                }
                if ($kode !== []) {
                    $q->orWhereIn('Kode_Karyawan', array_keys($kode));
                }
            })
            ->get(['UserID_Web', 'Kode_Karyawan', 'Nama']);

        $peta = [];
        foreach ($baris as $r) {
            if ($r->UserID_Web !== null && isset($id[(int) $r->UserID_Web])) {
                $peta['id:'.(int) $r->UserID_Web] = (string) $r->Nama;
            }
            if (isset($kode[$r->Kode_Karyawan])) {
                $peta['kode:'.$r->Kode_Karyawan] = (string) $r->Nama;
            }
        }

        return $peta;
    }

    private function namaPelaku(?string $nilai, array $peta): ?string
    {
        $p = self::jenisPelaku($nilai);
        if (! $p) {
            return null;
        }

        return $peta[$p[0].':'.($p[0] === 'id' ? (int) $p[1] : $p[1])] ?? ($p[0] === 'id' ? 'Pengguna #'.$p[1] : $p[1]);
    }

    private function jam($v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }

        return substr((string) $v, 0, 5);
    }
}
