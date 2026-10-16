<?php

namespace App\Services\VISITOR\Workflow\Reporting\Monitoring;

use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\StatusKunjunganTamu;
use App\Services\VISITOR\Guest\Visit\TipeTamu;
use App\Services\VISITOR\Guest\Visit\VisitorKehadiranQueryService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Notification\VisitorDetailToken;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Daftar "Tamu hari ini" — satu tabel berhalaman.
 *
 * Menggantikan tiga kelompok terpisah (harus datang, sedang di dalam, menginap)
 * dengan satu daftar berkolom Status. Penyaringan, pencarian, pengurutan, DAN
 * pemotongan halaman semuanya terjadi di SQL — bukan menarik seluruh baris lalu
 * memotongnya di PHP, yang membuat biayanya tumbuh seiring data walau yang
 * tampil tetap satu halaman.
 *
 * "Belum selesai" adalah himpunan bagian dari "sedang di dalam", jadi statusnya
 * diturunkan dari SATU ekspresi CASE, bukan dari penggabungan tiga kueri yang
 * kemudian perlu dedup.
 */
class VisitorPapanTamuService
{
    /** Kolom yang boleh diurutkan. Nilai dari permintaan tidak pernah masuk ORDER BY. */
    private const URUT = [
        'status' => 'urutan_status',
        'nama' => 'd.Nama_Lengkap_Tamu',
        'instansi' => 'd.Instansi_Atau_Asal',
        'pic' => 'k.Nama',
        'jumlah' => 'd.Jumlah_Tamu',
        'masuk' => 'masuk_sort',
        'keluar' => 'keluar_sort',
        'tanggal' => 'h.Tanggal_Kunjungan',
        'tipe' => 'd.Tipe_Tamu',
    ];

    private const BATAS_MAKSIMUM = 100;

    public function __construct(
        protected VisitorKehadiranQueryService $kehadiran,
        protected VisitorPermitQueryService $permitQuery,
    ) {}

    /**
     * @param  array<string,mixed>  $opsi
     * @return array<string,mixed>
     */
    public function daftar(array $opsi = []): array
    {
        $halaman = max((int) ($opsi['page'] ?? 1), 1);
        $batas = min(max((int) ($opsi['limit'] ?? 10), 1), self::BATAS_MAKSIMUM);
        $cari = trim((string) ($opsi['cari'] ?? ''));

        $tipe = isset($opsi['tipe']) && $opsi['tipe'] !== null ? TipeTamu::sah((string) $opsi['tipe']) : null;
        $kategori = array_key_exists('kategori', $opsi) && is_array($opsi['kategori']) ? $opsi['kategori'] : null;
        $dasar = $this->dasar($cari, $tipe, $kategori);

        // Dihitung dari kueri yang SAMA dengan yang dipotong; menghitung dari
        // kueri lain membuat total dan isi halaman bisa berselisih.
        $total = (clone $dasar)->count();

        [$kolomUrut, $arah] = $this->urutan($opsi);

        $baris = $dasar
            ->select(
                'h.Id_Transaksi_Tamu',
                'h.No_Transaksi',
                DB::raw('CONVERT(varchar(10), h.Tanggal_Kunjungan, 23) as Tanggal_Kunjungan'),
                'd.Nama_Lengkap_Tamu',
                'd.Instansi_Atau_Asal',
                'd.Jumlah_Tamu',
                // REGULAR dan B2B menempuh perjalanan yang sama sekali berbeda —
                // yang satu berjalan kaki, yang lain membawa kendaraan dan izin
                // gerbang. Tanpa kolom ini keduanya tampil identik di papan.
                DB::raw("ISNULL(d.Tipe_Tamu, 'REGULAR') as Tipe_Tamu"),
                'd.Tamu_Kategori',
                DB::raw('CONVERT(varchar(16), d.Waktu_Mulai_Kunjungan, 120) as Estimasi_Masuk'),
                DB::raw('CONVERT(varchar(16), d.Waktu_Selesai_Kunjungan, 120) as Estimasi_Keluar'),
                // Asal pendaftaran. Kunjungan dari aplikasi mitra tidak bisa
                // ditelepon-balik seperti kunjungan yang dientri sendiri oleh
                // karyawan, jadi admin perlu tahu ia sedang berurusan dengan apa.
                DB::raw("CASE WHEN h.Id_Api_Aplikasi IS NULL THEN 'HCIS' ELSE 'MITRA' END as Sumber_Kunjungan"),
                DB::raw('(SELECT TOP 1 ap.Nama_Aplikasi FROM N_HRIS_Api_Aplikasi ap '
                    .'WHERE ap.Id_Api_Aplikasi = h.Id_Api_Aplikasi) as Nama_Mitra'),
                // Manifes rombongan. Sudah tersimpan sejak registrasi tetapi
                // tidak pernah dirender di mana pun: satpam dan admin hanya
                // melihat angka "3" tanpa tahu siapa saja yang datang.
                'd.Daftar_Anggota_Json',
                DB::raw("ISNULL(k.Nama, '-') as Nama_PIC"),
                DB::raw('CONVERT(varchar(19), '.$this->kehadiran->masukSql().', 120) as Waktu_Aktual_Masuk'),
                DB::raw('CONVERT(varchar(19), '.$this->kehadiran->keluarSql().', 120) as Waktu_Aktual_Keluar'),
                DB::raw($this->statusSql().' as Status_Kehadiran'),
                // Izin gerbang turunan kunjungan ini. Dibutuhkan tautan "Lihat
                // Izin Gerbang" pada barisnya — dan sekalian menjawab kenapa
                // sebuah kunjungan yang terjadwal tetap tertahan di palang.
                DB::raw('(SELECT TOP 1 p.No_Transaksi FROM N_EMI_VISITOR_Transaksi_Permit p '
                    ."WHERE p.No_Transaksi_Tamu = h.No_Transaksi AND p.Flag_Aktif = 'Y') as No_Transaksi_Permit"),
                DB::raw('(SELECT TOP 1 p.Id_Transaksi_Permit FROM N_EMI_VISITOR_Transaksi_Permit p '
                    ."WHERE p.No_Transaksi_Tamu = h.No_Transaksi AND p.Flag_Aktif = 'Y') as Id_Transaksi_Permit"),
                DB::raw($this->permitQuery->statusPermitKunjunganSql('h.No_Transaksi', 'IZIN').' as Status_Permit_Coklat'),
                DB::raw($this->urutanStatusSql().' as urutan_status'),
                DB::raw($this->kehadiran->masukSql().' as masuk_sort'),
                DB::raw($this->kehadiran->keluarSql().' as keluar_sort'),
            )
            ->orderBy($kolomUrut, $arah)
            // Kunci kedua yang unik: tanpa itu baris berperingkat sama bisa
            // berpindah antar halaman dan orang hilang dari paginasi.
            ->orderBy('h.Id_Transaksi_Tamu', $arah)
            ->forPage($halaman, $batas)
            ->get()
            ->map(function ($row) {
                unset($row->urutan_status, $row->masuk_sort, $row->keluar_sort);

                // Langsung ke meja tamu, bukan lewat URL gabungan lama. Yang
                // lama masih hidup sebagai pengalihan, tetapi menautkan ke sana
                // menambah satu lompatan pada SETIAP baris papan — dan
                // pengalihannya memilih domain dari query string yang justru
                // berasal dari sini.
                $row->Tautan_Detail = TipeTamu::urlVerifikator($row->Tipe_Tamu ?? null).'?detail='
                    .VisitorDetailToken::buat(VisitorNotificationService::DOMAIN_TAMU, (int) $row->Id_Transaksi_Tamu);

                // Tautan ke izin gerbangnya, bila kunjungan ini memang punya.
                // Tokennya dibuat dari Id PERMIT, bukan Id kunjungan: keduanya
                // sama-sama integer dan tertukar tanpa satu pun galat — yang
                // terbuka hanyalah transaksi milik orang lain.
                $row->Tautan_Permit = $row->Id_Transaksi_Permit
                    ? '/verifikator-visitor/permit?detail='
                        .VisitorDetailToken::buat(
                            VisitorNotificationService::DOMAIN_PERMIT,
                            (int) $row->Id_Transaksi_Permit,
                        )
                    : null;
                unset($row->Id_Transaksi_Permit);

                // Manifes dikirim sebagai ARRAY, bukan teks JSON mentah: layar
                // yang harus mem-parse sendiri akan menampilkan tanda kurung
                // siku begitu isinya tak terduga.
                $row->Daftar_Anggota = $this->manifes($row->Daftar_Anggota_Json ?? null);
                unset($row->Daftar_Anggota_Json);

                return $row;
            })
            ->all();

        return [
            'baris' => $baris,
            'paginasi' => [
                'total' => $total,
                'current_page' => $halaman,
                'per_page' => $batas,
                'total_pages' => (int) ceil($total / $batas),
            ],
        ];
    }

    /**
     * Manifes rombongan sebagai daftar nama yang siap ditampilkan.
     *
     * JSON yang tidak terbaca dipulangkan sebagai daftar KOSONG, bukan dilempar:
     * satu baris rusak dari registrasi lama tidak boleh menjatuhkan seluruh
     * papan harian — dan papan yang gagal dimuat jauh lebih merugikan daripada
     * satu rombongan yang namanya tidak tampil.
     *
     * @return list<array{nama:string}>
     */
    private function manifes(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $isi = json_decode($json, true);

        if (! is_array($isi)) {
            return [];
        }

        $hasil = [];

        foreach ($isi as $anggota) {
            $nama = is_array($anggota)
                ? trim((string) ($anggota['nama'] ?? ''))
                : trim((string) $anggota);

            if ($nama !== '') {
                $hasil[] = ['nama' => $nama];
            }
        }

        return $hasil;
    }

    /**
     * Yang perlu dipantau hari ini: terjadwal hari ini dan belum masuk, ATAU
     * sudah masuk dan belum keluar (berapa pun tanggal kunjungannya — tamu yang
     * tertinggal dari hari sebelumnya justru yang paling perlu terlihat).
     */
    /** @param  list<string>|null  $kategori  matriks kategori pemakai; null = tanpa penyaring */
    private function dasar(string $cari, ?string $tipe = null, ?array $kategori = null): Builder
    {
        // "Hari ini" = rentang estimasinya beririsan dengan hari ini: kunjungan
        // MITRA berhari-hari dan kunjungan yang melewati tengah malam ikut.
        [$irisan, $ikatan] = $this->kehadiran->beririsanHariSql(now()->toDateString());

        $query = DB::table('N_EMI_VISITOR_Transaksi_Tamu as h')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Tamu_Detail as d', 'd.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'e.No_Transaksi', '=', 'h.No_Transaksi')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'h.Kode_Karyawan_PIC')
            ->where('h.Flag_Aktif', 'Y')
            ->whereNotIn('h.Status_Kunjungan', [StatusKunjunganTamu::PENDING_APPROVAL, StatusKunjunganTamu::REJECTED, StatusKunjunganTamu::WITHDRAWN, StatusKunjunganTamu::REVOKED])
            ->where(function ($q) use ($irisan, $ikatan) {
                $q->where(function ($sub) use ($irisan, $ikatan) {
                    $sub->whereRaw($irisan, $ikatan)
                        ->whereRaw($this->kehadiran->belumMasukSql());
                })->orWhereRaw($this->kehadiran->diDalamSql());
            });

        if ($tipe !== null) {
            TipeTamu::saring($query, $tipe);
        }

        if ($kategori !== null) {
            MatriksTamuService::saring($query, $kategori);
        }

        if ($cari !== '') {
            // Mencakup seluruh kolom yang tampil, termasuk plat yang dirakit
            // dari tiga ruas dan status yang merupakan ekspresi turunan.
            $query->where(function ($q) use ($cari) {
                $suka = '%'.$cari.'%';
                $q->where('d.Nama_Lengkap_Tamu', 'LIKE', $suka)
                    ->orWhere('d.Instansi_Atau_Asal', 'LIKE', $suka)
                    ->orWhere('h.No_Transaksi', 'LIKE', $suka)
                    ->orWhere('k.Nama', 'LIKE', $suka)
                    ->orWhere('d.Deksripsi_Keperluan', 'LIKE', $suka)
                    ->orWhereRaw($this->permitQuery->platNomorSql('d').' LIKE ?', [$suka])
                    // Tipe dan manifes rombongan ikut dicari: admin sering
                    // mencari nama orang yang datang MENYERTAI tamu utama, dan
                    // nama itu hanya ada di manifesnya.
                    ->orWhere('d.Tipe_Tamu', 'LIKE', $suka)
                    ->orWhere('d.Daftar_Anggota_Json', 'LIKE', $suka)
                    ->orWhereRaw($this->statusSql().' LIKE ?', [$suka]);
            });
        }

        return $query;
    }

    /**
     * Satu ekspresi, dipakai untuk menampilkan, mencari, DAN mengurutkan.
     *
     * "Belum selesai" (dulu "Menginap") = masuk pada hari sebelumnya dan belum
     * tercatat keluar. Sebutan menginap menyesatkan: sebagian besar barisnya
     * bukan tamu yang benar-benar bermalam, melainkan kunjungan yang palangnya
     * tak pernah mencatat keluar.
     */
    private function statusSql(): string
    {
        $masuk = $this->kehadiran->masukSql();
        $keluar = $this->kehadiran->keluarSql();

        // Kunjungan yang sudah berakhir di gerbang tanpa jam keluar: sama dengan
        // siklus KELUAR_TAK_TERCATAT, bukan "belum selesai" atau "di dalam".
        return "CASE
            WHEN {$masuk} IS NOT NULL AND {$keluar} IS NULL AND e.Status_Efektif = 'EXPIRED' THEN 'Keluar tak tercatat'
            WHEN {$masuk} IS NOT NULL AND {$keluar} IS NULL
                 AND CAST({$masuk} AS date) < CAST(GETDATE() AS date) THEN 'Belum selesai'
            WHEN {$masuk} IS NOT NULL AND {$keluar} IS NULL THEN 'Di dalam'
            ELSE 'Belum datang' END";
    }

    /** Urutan kepentingan, bukan abjad: yang belum selesai dulu, baru yang di dalam. */
    private function urutanStatusSql(): string
    {
        $masuk = $this->kehadiran->masukSql();
        $keluar = $this->kehadiran->keluarSql();

        return "CASE
            WHEN {$masuk} IS NOT NULL AND {$keluar} IS NULL AND e.Status_Efektif = 'EXPIRED' THEN 3
            WHEN {$masuk} IS NOT NULL AND {$keluar} IS NULL
                 AND CAST({$masuk} AS date) < CAST(GETDATE() AS date) THEN 0
            WHEN {$masuk} IS NOT NULL AND {$keluar} IS NULL THEN 1
            ELSE 2 END";
    }

    /**
     * @param  array<string,mixed>  $opsi
     * @return array{0:string,1:string}
     */
    private function urutan(array $opsi): array
    {
        $kunci = (string) ($opsi['urut'] ?? 'status');
        $kolom = self::URUT[$kunci] ?? self::URUT['status'];
        $arah = strtolower((string) ($opsi['arah'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        return [DB::raw($kolom), $arah];
    }
}
