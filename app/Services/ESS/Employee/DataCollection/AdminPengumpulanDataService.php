<?php

namespace App\Services\ESS\Employee\DataCollection;

use App\Jobs\ESS\Employee\DataCollection\KirimPengingatPengumpulanDataJob;
use Illuminate\Support\Facades\DB;

/**
 * Sisi HR: memantau, membaca, dan mengurus pengecualian/buka kunci.
 *
 * Populasi target selalu dihitung dari tabel Karyawan (aktif, belum resign),
 * BUKAN dari tabel status — karyawan yang belum pernah menyentuh formulir sama
 * sekali tidak punya baris status, dan mereka justru orang yang paling perlu
 * ditagih. Menghitung dari tabel status akan membuat mereka lenyap dari laporan.
 *
 * Populasi itu lalu disempitkan oleh CakupanKampanye: bila kampanye dibuka
 * untuk sebagian lokasi kerja saja, lokasi yang belum dibuka tidak ikut
 * dihitung, tidak ikut ditagih, dan tidak ikut terekspor. Penyempitan itu
 * dipanggil di SETIAP kueri populasi di berkas ini — angka yang bocor satu
 * kueri saja membuat persentase progres berbohong.
 */
class AdminPengumpulanDataService
{
    public function __construct(
        private SkemaPengumpulanData $skema,
        private StatusPengumpulanService $status,
        private KonfigurasiKampanye $konfigurasi,
        private CakupanKampanye $cakupan,
    ) {}

    public function ringkasan(): array
    {
        $query = DB::table('Karyawan as k')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Status as s', 's.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign');

        $this->cakupan->terapkan($query);

        $baris = $query
            ->selectRaw("
                COUNT(*) AS total,
                SUM(CASE WHEN ISNULL(s.Is_Dikecualikan,'T') = 'Y' THEN 1 ELSE 0 END) AS dikecualikan,
                SUM(CASE WHEN ISNULL(s.Is_Dikecualikan,'T') <> 'Y' AND ISNULL(s.Status,'BELUM') = 'SELESAI' THEN 1 ELSE 0 END) AS sudah,
                SUM(CASE WHEN ISNULL(s.Is_Dikecualikan,'T') <> 'Y' AND ISNULL(s.Status,'BELUM') <> 'SELESAI' THEN 1 ELSE 0 END) AS belum,
                SUM(CASE WHEN ISNULL(s.Is_Dikecualikan,'T') <> 'Y' AND ISNULL(s.Status,'BELUM') = 'TERKUNCI' THEN 1 ELSE 0 END) AS terkunci
            ")
            ->first();

        $total = (int) $baris->total;
        $sudah = (int) $baris->sudah;

        return [
            'total' => $total,
            'sudah' => $sudah,
            'belum' => (int) $baris->belum,
            'terkunci' => (int) $baris->terkunci,
            'dikecualikan' => (int) $baris->dikecualikan,
            'persen' => $total > 0 ? round($sudah / $total * 100, 1) : 0.0,
            'kampanye_aktif' => $this->konfigurasi->aktif(),
            'cakupan_lokasi' => $this->cakupan->lokasiTerpilih(),
            'di_luar_cakupan' => $this->hitungDiLuarCakupan(),
            'per_departemen' => $this->perDepartemen(),
        ];
    }

    /**
     * Berapa orang yang sengaja ditinggalkan oleh cakupan, dan berapa di
     * antaranya yang tertinggal BUKAN karena keputusan.
     *
     * Karyawan tanpa Cabang_Untuk_Gaji tidak pernah masuk cakupan mana pun, dan
     * karena ia juga tidak muncul di daftar, ia akan lenyap dari layar admin
     * tanpa jejak. Angka ini ada supaya kehilangan itu bersuara — lubang datanya
     * milik master data, dan hanya HR yang bisa menutupnya.
     *
     * Hanya dihitung saat cakupan benar-benar dipakai: kampanye yang terbuka
     * untuk semua lokasi tidak meninggalkan siapa pun, jadi kueri ini pun tidak
     * dijalankan.
     *
     * @return array{total:int, tanpa_lokasi:int}
     */
    private function hitungDiLuarCakupan(): array
    {
        if ($this->cakupan->terbukaUntukSemuaLokasi()) {
            return ['total' => 0, 'tanpa_lokasi' => 0];
        }

        $semua = DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign');

        $dalam = (clone $semua);
        $this->cakupan->terapkan($dalam);

        $tanpaLokasi = (clone $semua)
            ->where(function ($q) {
                $q->whereNull('k.Cabang_Untuk_Gaji')
                    ->orWhereRaw("LTRIM(RTRIM(k.Cabang_Untuk_Gaji)) = ''");
            })
            ->count();

        return [
            'total' => $semua->count() - $dalam->count(),
            'tanpa_lokasi' => $tanpaLokasi,
        ];
    }

    private function perDepartemen(): array
    {
        $query = DB::table('Karyawan as k')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Status as s', 's.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where(function ($q) {
                $q->where('s.Is_Dikecualikan', '<>', 'Y')->orWhereNull('s.Is_Dikecualikan');
            });

        $this->cakupan->terapkan($query);

        return $query
            ->groupBy(DB::raw("ISNULL(vds.nama_divisi, '(Tanpa Divisi)')"))
            ->selectRaw("
                ISNULL(vds.nama_divisi, '(Tanpa Divisi)') AS departemen,
                COUNT(*) AS total,
                SUM(CASE WHEN ISNULL(s.Status,'BELUM') = 'SELESAI' THEN 1 ELSE 0 END) AS sudah
            ")
            ->orderBy('departemen')
            ->get()
            ->map(fn ($r) => [
                'departemen' => $r->departemen,
                'total' => (int) $r->total,
                'sudah' => (int) $r->sudah,
                'belum' => (int) $r->total - (int) $r->sudah,
            ])
            ->all();
    }

    /**
     * @param  array{cari?:string, status?:string, departemen?:string, lokasi?:string, halaman?:int, per_halaman?:int}  $filter
     */
    public function daftar(array $filter = []): array
    {
        $perHalaman = min(max((int) ($filter['per_halaman'] ?? 25), 5), 200);
        $halaman = max((int) ($filter['halaman'] ?? 1), 1);

        $query = DB::table('Karyawan as k')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Status as s', 's.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign');

        // Cakupan dulu, baru saringan. Keduanya menyaring lokasi, tetapi hanya
        // yang pertama menentukan siapa yang wajib mengisi — memilih satu lokasi
        // di dropdown TIDAK memperluas kampanye ke lokasi yang belum dibuka.
        $this->cakupan->terapkan($query);
        $this->saringLokasi($query, $filter['lokasi'] ?? '');

        if (! empty($filter['cari'])) {
            $cari = '%'.$filter['cari'].'%';
            $query->where(function ($q) use ($cari) {
                $q->where('k.Nama', 'like', $cari)
                    ->orWhere('k.Kode_Karyawan', 'like', $cari)
                    ->orWhere('k.No_Induk_Karyawan', 'like', $cari);
            });
        }

        if (! empty($filter['departemen'])) {
            $query->where('vds.nama_divisi', $filter['departemen']);
        }

        $pilih = $filter['status'] ?? '';
        if ($pilih === 'DIKECUALIKAN') {
            $query->where('s.Is_Dikecualikan', 'Y');
        } elseif ($pilih !== '') {
            $query->where(function ($q) {
                $q->where('s.Is_Dikecualikan', '<>', 'Y')->orWhereNull('s.Is_Dikecualikan');
            });

            if ($pilih === 'SELESAI') {
                $query->where('s.Status', 'SELESAI');
            } elseif ($pilih === 'TERKUNCI') {
                $query->where('s.Status', 'TERKUNCI');
            } elseif ($pilih === 'BELUM') {
                $query->where(function ($q) {
                    $q->whereNull('s.Status')->orWhere('s.Status', 'BELUM');
                });
            }
        }

        $total = (clone $query)->count();

        // ORDER BY wajib deterministik: tanpa pemecah seri, orang bisa berpindah
        // antar halaman dan seolah menghilang dari daftar.
        $items = $query
            ->orderBy('k.Nama')
            ->orderBy('k.Kode_Karyawan')
            ->forPage($halaman, $perHalaman)
            ->get([
                'k.Kode_Karyawan',
                'k.Nama',
                'k.No_Induk_Karyawan',
                'vds.nama_divisi as departemen',
                's.Status',
                's.Jumlah_Tunda',
                's.Is_Dikecualikan',
                's.Selesai_At',
                's.Dibuka_Kunci_At',
            ])
            ->map(fn ($r) => [
                'Kode_Karyawan' => $r->Kode_Karyawan,
                'nama' => $r->Nama,
                'nik_karyawan' => $r->No_Induk_Karyawan,
                'departemen' => $r->departemen ?? '(Tanpa Divisi)',
                'status' => ($r->Is_Dikecualikan ?? 'T') === 'Y' ? 'DIKECUALIKAN' : ($r->Status ?? 'BELUM'),
                'jumlah_tunda' => (int) ($r->Jumlah_Tunda ?? 0),
                'selesai_at' => $r->Selesai_At,
                'pernah_dibuka_kunci' => $r->Dibuka_Kunci_At !== null,
            ])
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total_halaman' => (int) ceil($total / $perHalaman),
        ];
    }

    public function detail(string $kodeKaryawan): array
    {
        $baris = DB::table('N_HRIS_Pengumpulan_Data_Submission')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id', 'Data_Json', 'Snapshot_Lama_Json', 'Versi_Skema', 'Submitted_At', 'Submitted_Ip']);

        $karyawan = DB::table('Karyawan')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->first(['Nama', 'No_Induk_Karyawan']);

        if (! $baris) {
            return [
                'kode_karyawan' => $kodeKaryawan,
                'nama' => $karyawan->Nama ?? '',
                'ada' => false,
                'jawaban' => [],
                'lama' => [],
                'perbedaan' => [],
                'riwayat' => $this->riwayat($kodeKaryawan),
            ];
        }

        $jawaban = json_decode($baris->Data_Json, true) ?: [];
        $lama = json_decode($baris->Snapshot_Lama_Json ?? '{}', true) ?: [];

        return [
            'kode_karyawan' => $kodeKaryawan,
            'nama' => $karyawan->Nama ?? '',
            'nik_karyawan' => $karyawan->No_Induk_Karyawan ?? '',
            'ada' => true,
            'versi_skema' => (int) $baris->Versi_Skema,
            'dikirim_pada' => $baris->Submitted_At,
            'dikirim_dari' => $baris->Submitted_Ip,
            'jawaban' => $jawaban,
            'lama' => $lama,
            'perbedaan' => $this->hitungPerbedaan($jawaban, $lama),
            'riwayat' => $this->riwayat($kodeKaryawan),
        ];
    }

    /**
     * Hanya field yang PUNYA padanan di sistem induk yang bisa dibandingkan.
     * Field seperti status_pernikahan dan kontak darurat memang tidak ada di sana —
     * itu justru alasan data ini dikumpulkan — jadi tidak ikut ditandai.
     */
    private function hitungPerbedaan(array $jawaban, array $lama): array
    {
        $beda = [];

        foreach ($lama as $kunci => $nilaiLama) {
            if (! array_key_exists($kunci, $jawaban)) {
                continue;
            }

            $beda[$kunci] = $this->normalkan($nilaiLama) !== $this->normalkan($jawaban[$kunci]);
        }

        return $beda;
    }

    private function normalkan(mixed $nilai): string
    {
        $teks = trim((string) ($nilai ?? ''));

        // Tanggal dari SQL Server datang sebagai '1995-04-17 00:00:00.000',
        // sedangkan jawaban user '1995-04-17'. Tanpa penyeragaman ini, setiap
        // tanggal lahir akan tampak "berbeda" padahal sama persis.
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]/', $teks, $cocok)) {
            return $cocok[1];
        }

        return mb_strtolower($teks);
    }

    private function riwayat(string $kodeKaryawan): array
    {
        return DB::table('N_HRIS_Pengumpulan_Data_Submission')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->orderByDesc('Id')
            ->get(['Id', 'Versi_Skema', 'Flag_Aktif', 'Submitted_At'])
            ->map(fn ($r) => [
                'id' => (int) $r->Id,
                'versi_skema' => (int) $r->Versi_Skema,
                'aktif' => $r->Flag_Aktif === 'Y',
                'dikirim_pada' => $r->Submitted_At,
            ])
            ->all();
    }

    public function aturPengecualian(string $kodeKaryawan, bool $dikecualikan, ?string $alasan, string $oleh, ?string $ip = null): void
    {
        $this->pastikanBarisStatus($kodeKaryawan);

        $sebelum = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->value('Is_Dikecualikan');

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->update([
                'Is_Dikecualikan' => $dikecualikan ? 'Y' : 'T',
                'Dikecualikan_Oleh' => $dikecualikan ? $oleh : null,
                'Dikecualikan_At' => $dikecualikan ? now() : null,
                'Alasan_Pengecualian' => $dikecualikan ? $alasan : null,
                'Updated_At' => now(),
            ]);

        $this->status->bersihkanCache($kodeKaryawan);

        $this->catatAudit(
            $dikecualikan ? 'TAMBAH_PENGECUALIAN' : 'HAPUS_PENGECUALIAN',
            $oleh,
            $kodeKaryawan,
            $sebelum ?? 'T',
            $dikecualikan ? 'Y' : 'T',
            $alasan,
            $ip,
        );
    }

    /**
     * Buka kunci mengembalikan status ke BELUM tetapi TIDAK mereset jatah
     * penundaan. Karyawan yang jatahnya sudah habis akan langsung menghadapi
     * modal terkunci — dan itu memang yang diinginkan, karena buka kunci
     * diberikan justru supaya kesalahannya segera diperbaiki.
     */
    public function bukaKunci(string $kodeKaryawan, ?string $alasan, string $oleh, ?string $ip = null): void
    {
        $this->pastikanBarisStatus($kodeKaryawan);

        DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->update([
                'Status' => 'BELUM',
                'Selesai_At' => null,
                'Dibuka_Kunci_Oleh' => $oleh,
                'Dibuka_Kunci_At' => now(),
                'Alasan_Buka_Kunci' => $alasan,
                'Updated_At' => now(),
            ]);

        $this->status->bersihkanCache($kodeKaryawan);

        $this->catatAudit('BUKA_KUNCI', $oleh, $kodeKaryawan, 'SELESAI', 'BELUM', $alasan, $ip);
    }

    public function aturSakelar(bool $aktif, string $oleh, ?string $ip = null): void
    {
        $sebelum = $this->konfigurasi->aktif() ? 'Y' : 'T';

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update([
            'Is_Aktif' => $aktif ? 'Y' : 'T',
            'Updated_Oleh' => $oleh,
            'Updated_At' => now(),
        ]);

        $this->konfigurasi->bersihkanCache();

        $this->catatAudit('UBAH_SAKELAR', $oleh, null, $sebelum, $aktif ? 'Y' : 'T', null, $ip);
    }

    public function simpanPengumuman(array $teks, string $oleh, ?string $ip = null): void
    {
        DB::table('N_HRIS_Pengumpulan_Data_Config')->update([
            'Judul_Pengumuman' => $teks['judul'],
            'Teks_Pengumuman' => $teks['teks'],
            'Label_Tombol_Isi' => $teks['label_tombol_isi'],
            'Label_Tombol_Tunda' => $teks['label_tombol_tunda'],
            'Updated_Oleh' => $oleh,
            'Updated_At' => now(),
        ]);

        $this->konfigurasi->bersihkanCache();

        $this->catatAudit('UBAH_PENGUMUMAN', $oleh, null, null, $teks['judul'], null, $ip);
    }

    /**
     * Menyorot baris yang akan menyusahkan saat data dibawa ke Coretax.
     * Sengaja TIDAK memblokir pengiriman — orang tidak boleh terjebak gara-gara
     * kesalahan orang lain; yang salah cukup diberitahukan ke HR.
     */
    public function pemeriksaKualitas(): array
    {
        $aktif = DB::table('N_HRIS_Pengumpulan_Data_Submission as sub')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'sub.Kode_Karyawan')
            ->where('sub.Flag_Aktif', 'Y')
            ->get(['sub.Kode_Karyawan', 'k.Nama', 'sub.Data_Json']);

        $temuan = [];
        $indeks = ['nik_ktp' => [], 'email' => [], 'no_hp' => []];

        foreach ($aktif as $baris) {
            $data = json_decode($baris->Data_Json, true) ?: [];

            $nik = (string) ($data['nik_ktp'] ?? '');
            if ($nik !== '' && ! preg_match('/^\d{16}$/', $nik)) {
                $temuan[] = [
                    'jenis' => 'NIK_TIDAK_16_DIGIT',
                    'nilai' => $nik,
                    'karyawan' => [['kode' => $baris->Kode_Karyawan, 'nama' => $baris->Nama]],
                ];
            }

            foreach (array_keys($indeks) as $kunci) {
                $nilai = mb_strtolower(trim((string) ($data[$kunci] ?? '')));
                if ($nilai !== '') {
                    $indeks[$kunci][$nilai][] = ['kode' => $baris->Kode_Karyawan, 'nama' => $baris->Nama];
                }
            }
        }

        $label = ['nik_ktp' => 'NIK_KEMBAR', 'email' => 'EMAIL_KEMBAR', 'no_hp' => 'HP_KEMBAR'];

        foreach ($indeks as $kunci => $peta) {
            foreach ($peta as $nilai => $pemakai) {
                if (count($pemakai) > 1) {
                    // (string) wajib: PHP mengubah kunci array yang berupa angka
                    // menjadi integer, dan NIK berawalan nol akan kehilangan
                    // angka depannya begitu dikirim ke layar.
                    $temuan[] = ['jenis' => $label[$kunci], 'nilai' => (string) $nilai, 'karyawan' => $pemakai];
                }
            }
        }

        return $temuan;
    }

    /**
     * Kirim pengingat ke semua yang belum mengisi.
     *
     * Memakai upsertByKey dengan kunci ber-tanggal: menekan tombol dua kali di
     * hari yang sama tidak menghasilkan dua notifikasi bagi orang yang sama.
     *
     * Hanya notifikasi DALAM sistem. WhatsApp sengaja tidak dipanggil di sini —
     * repo ini pernah mengalami "Login timeout expired" massal persis karena
     * panggilan HTTP WhatsApp berjalan di dalam transaksi DB; kalau nanti
     * diperlukan, kirimnya lewat antrean setelah commit.
     */
    public function kirimPengingat(string $oleh, ?string $ip = null): int
    {
        $query = DB::table('Karyawan as k')
            ->leftJoin('N_HRIS_Pengumpulan_Data_Status as s', 's.Kode_Karyawan', '=', 'k.Kode_Karyawan')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->where(function ($q) {
                $q->where('s.Is_Dikecualikan', '<>', 'Y')->orWhereNull('s.Is_Dikecualikan');
            })
            ->where(function ($q) {
                $q->whereNull('s.Status')->orWhere('s.Status', '<>', 'SELESAI');
            });

        // Menagih orang yang formulirnya bahkan tidak terbuka untuknya adalah
        // cara tercepat membuat pengingat diabaikan seluruh kantor.
        $this->cakupan->terapkan($query);

        $sasaran = $query->pluck('k.Kode_Karyawan');

        $tanggal = now()->format('Y-m-d');
        $terkirim = $sasaran->count();

        // Penaburan dipindah ke latar, dan DIPOTONG.
        //
        // Sebelumnya upsertByKey() dipanggil sekali per orang di dalam
        // permintaan admin ini — 2 kueri masing-masing. Diukur pada populasi
        // dev: 952 sasaran => 1.904 kueri dalam SATU permintaan sinkron,
        // sekitar 9,5 detik pada latensi produksi, sambil menahan satu slot
        // PHP-FPM. Satu klik yang salah waktu bisa menyeret layanan lain.
        //
        // Potongan 100 dipilih supaya tak satu pun job memegang koneksi lama:
        // 200 kueri per job, bukan 1.904 sekaligus.
        $sasaran->chunk(100)->each(function ($potongan) use ($tanggal) {
            KirimPengingatPengumpulanDataJob::dispatch($potongan->values()->all(), $tanggal);
        });

        $this->catatAudit('KIRIM_PENGINGAT', $oleh, null, null, (string) $terkirim, "{$terkirim} penerima", $ip);

        return $terkirim;
    }

    public function audit(int $batas = 100): array
    {
        return DB::table('N_HRIS_Pengumpulan_Data_Audit as a')
            ->leftJoin('Karyawan as k', 'k.Kode_Karyawan', '=', 'a.Kode_Karyawan_Target')
            ->orderByDesc('a.Id')
            ->limit($batas)
            ->get([
                'a.Id', 'a.Aksi', 'a.Kode_Karyawan_Target', 'k.Nama as nama_target',
                'a.Dilakukan_Oleh', 'a.Nilai_Sebelum', 'a.Nilai_Sesudah', 'a.Keterangan', 'a.Created_At',
            ])
            ->map(fn ($r) => [
                'id' => (int) $r->Id,
                'aksi' => $r->Aksi,
                'target' => $r->Kode_Karyawan_Target,
                'nama_target' => $r->nama_target,
                'oleh' => $r->Dilakukan_Oleh,
                'sebelum' => $r->Nilai_Sebelum,
                'sesudah' => $r->Nilai_Sesudah,
                'keterangan' => $r->Keterangan,
                'pada' => $r->Created_At,
            ])
            ->all();
    }

    public function pilihanDepartemen(): array
    {
        $query = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereNotNull('vds.nama_divisi');

        // Dropdown departemen menyaring DAFTAR, jadi isinya harus sama luasnya
        // dengan daftar itu: departemen yang seluruh orangnya di luar cakupan
        // hanya akan menghasilkan tabel kosong bila tetap ditawarkan.
        $this->cakupan->terapkan($query);

        return $query
            ->distinct()
            ->orderBy('vds.nama_divisi')
            ->pluck('vds.nama_divisi')
            ->all();
    }

    /**
     * Seluruh lokasi kerja yang ada pada karyawan aktif — SENGAJA tanpa
     * penyempitan cakupan. Daftar ini mengisi dua hal sekaligus: kotak centang
     * cakupan (yang justru harus menawarkan lokasi yang belum dibuka) dan
     * dropdown saringan tabel.
     *
     * @return string[]
     */
    public function pilihanLokasi(): array
    {
        return DB::table('Karyawan as k')
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            ->whereNotNull('k.Cabang_Untuk_Gaji')
            ->whereRaw("LTRIM(RTRIM(k.Cabang_Untuk_Gaji)) <> ''")
            ->distinct()
            ->orderByRaw('UPPER(LTRIM(RTRIM(k.Cabang_Untuk_Gaji)))')
            ->selectRaw('UPPER(LTRIM(RTRIM(k.Cabang_Untuk_Gaji))) AS lokasi')
            ->pluck('lokasi')
            ->all();
    }

    /**
     * Menyimpan cakupan. Daftar kosong mengembalikan kampanye ke seluruh lokasi.
     *
     * Tidak menyentuh satu baris status pun: cakupan adalah gelombang
     * pembukaan, bukan pengecualian per orang. Yang perlu dibuang hanyalah
     * cache konfigurasi, sehingga pergeserannya berlaku pada permintaan
     * berikutnya — termasuk bagi orang yang sedang membuka halaman.
     *
     * @param  string[]  $lokasi
     * @return string[] bentuk kanonis yang benar-benar tersimpan
     */
    public function aturCakupan(array $lokasi, string $oleh, ?string $ip = null): array
    {
        $sebelum = $this->cakupan->lokasiTerpilih();
        $sesudah = CakupanKampanye::normalkanDaftar($lokasi);

        DB::table('N_HRIS_Pengumpulan_Data_Config')->update([
            'Cakupan_Lokasi' => $sesudah === [] ? null : json_encode($sesudah),
            'Updated_Oleh' => $oleh,
            'Updated_At' => now(),
        ]);

        $this->konfigurasi->bersihkanCache();

        $this->catatAudit(
            'UBAH_CAKUPAN',
            $oleh,
            null,
            $this->ringkasCakupan($sebelum),
            $this->ringkasCakupan($sesudah),
            sprintf('%d lokasi → %d lokasi', count($sebelum), count($sesudah)),
            $ip,
        );

        return $sesudah;
    }

    /**
     * Kolom audit hanya varchar(200). Daftar lokasi yang panjang dipenggal di
     * sini, bukan dibiarkan dipenggal diam-diam oleh basis data.
     *
     * @param  string[]  $lokasi
     */
    private function ringkasCakupan(array $lokasi): string
    {
        if ($lokasi === []) {
            return 'SEMUA LOKASI';
        }

        $teks = implode(', ', $lokasi);

        return mb_strlen($teks) <= 200 ? $teks : mb_substr($teks, 0, 197).'...';
    }

    /**
     * Saringan tampilan. Nilai yang tidak dikenal diabaikan, bukan dipakai
     * membentuk kueri — dan karena pembandingnya dinormalkan, '  emi ' dari URL
     * tetap menemukan orangnya.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function saringLokasi($query, string $lokasi): void
    {
        $normal = CakupanKampanye::normalkan($lokasi);

        if ($normal === '') {
            return;
        }

        $query->whereRaw('UPPER(LTRIM(RTRIM(k.Cabang_Untuk_Gaji))) = ?', [$normal]);
    }

    private function pastikanBarisStatus(string $kodeKaryawan): void
    {
        $ada = DB::table('N_HRIS_Pengumpulan_Data_Status')
            ->where('Kode_Karyawan', $kodeKaryawan)
            ->exists();

        if (! $ada) {
            DB::table('N_HRIS_Pengumpulan_Data_Status')->insert([
                'Kode_Karyawan' => $kodeKaryawan,
                'Status' => 'BELUM',
                'Jumlah_Tunda' => 0,
                'Is_Dikecualikan' => 'T',
                'Created_At' => now(),
            ]);
        }
    }

    private function catatAudit(
        string $aksi,
        string $oleh,
        ?string $target,
        ?string $sebelum,
        ?string $sesudah,
        ?string $keterangan,
        ?string $ip,
    ): void {
        DB::table('N_HRIS_Pengumpulan_Data_Audit')->insert([
            'Aksi' => $aksi,
            'Kode_Karyawan_Target' => $target,
            'Dilakukan_Oleh' => $oleh,
            'Nilai_Sebelum' => $sebelum,
            'Nilai_Sesudah' => $sesudah,
            'Keterangan' => $keterangan,
            'Ip_Address' => $ip,
            'Created_At' => now(),
        ]);
    }
}
