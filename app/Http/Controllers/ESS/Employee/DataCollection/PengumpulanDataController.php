<?php

namespace App\Http\Controllers\ESS\Employee\DataCollection;

use App\Http\Controllers\Controller;
use App\Services\ESS\Employee\DataCollection\CakupanKampanye;
use App\Services\ESS\Employee\DataCollection\KonfigurasiKampanye;
use App\Services\ESS\Employee\DataCollection\PengirimanDataService;
use App\Services\ESS\Employee\DataCollection\SkemaPengumpulanData;
use App\Services\ESS\Employee\DataCollection\StatusKaryawanResolver;
use App\Services\ESS\Employee\DataCollection\StatusPengumpulanService;
use App\Services\ESS\Employee\DataCollection\SudahTerkirimException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PengumpulanDataController extends Controller
{
    public function __construct(
        private SkemaPengumpulanData $skema,
        private StatusKaryawanResolver $resolver,
        private StatusPengumpulanService $status,
        private PengirimanDataService $pengiriman,
        private KonfigurasiKampanye $konfigurasi,
        private CakupanKampanye $cakupan,
    ) {}

    public function index(Request $request)
    {
        if ($tolak = $this->tolakBilaPintuTertutup($request)) {
            return $tolak;
        }

        $kode = $this->kodeKaryawan();
        $status = $this->status->untuk($kode);

        if ($status['status'] === 'SELESAI') {
            return redirect()->route('pengumpulanData.ringkasan');
        }

        $draft = $this->status->ambilDraft($kode);
        $dataMaster = $this->pengiriman->ambilDataMaster($kode);

        return inertia('ESS/Employee/DataCollection/Wizard', [
            'skema' => $this->skema->untukUi($this->resolver->isPhl($kode)),
            'status' => $status,
            'draft' => $draft,
            'dataMaster' => $dataMaster,
            'pilihanDivisi' => $this->pilihanDivisi(),
            'namaKaryawan' => $this->namaUntukJawaban(array_merge($dataMaster, (array) ($draft['data'] ?? []))),
        ]);
    }

    public function ringkasan()
    {
        $kode = $this->kodeKaryawan();

        $baris = DB::table('N_HRIS_Pengumpulan_Data_Submission')
            ->where('Kode_Karyawan', $kode)
            ->where('Flag_Aktif', 'Y')
            ->first(['Data_Json', 'Submitted_At']);

        if (! $baris) {
            return redirect()->route('pengumpulanData.index');
        }

        $jawaban = json_decode($baris->Data_Json, true);

        return inertia('ESS/Employee/DataCollection/Ringkasan', [
            'skema' => $this->skema->untukUi($this->resolver->isPhl($kode)),
            'jawaban' => $jawaban,
            'dikirimPada' => (string) $baris->Submitted_At,
            'namaKaryawan' => $this->namaUntukJawaban((array) $jawaban),
        ]);
    }

    /**
     * Pencarian karyawan untuk pertanyaan bertipe 'cari_karyawan' (atasan PHL).
     *
     * Hanya yang benar-benar ditanyai atasannya (PHL, dan pertanyaannya tidak
     * dimatikan lewat env) yang boleh mencari —
     * endpoint ini tidak boleh menjadi pintu belakang daftar seluruh karyawan.
     * Nilai yang disimpan Kode_Karyawan; server menegakkannya lewat aturan
     * 'exists' di config, pencarian ini hanya memudahkan memilihnya.
     */
    public function cariKaryawan(Request $request): JsonResponse
    {
        if ($tolak = $this->tolakBilaPintuTertutup($request)) {
            return $tolak;
        }

        $kode = $this->kodeKaryawan();

        // Dibaca dari skema, bukan sekadar "PHL atau bukan": pertanyaan atasan
        // yang dimatikan lewat env ikut menutup pencariannya.
        $adaPertanyaanCari = collect($this->skema->fieldBerlaku($this->resolver->isPhl($kode)))
            ->contains(fn (array $def) => ($def['tipe'] ?? null) === 'cari_karyawan');

        if (! $adaPertanyaanCari) {
            return response()->json(['message' => 'Pertanyaan ini tidak berlaku untuk Anda.'], 403);
        }

        // 'q[]=…' datang sebagai array; dibaca sebagai kosong, bukan 500.
        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';

        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $pola = '%'.str_replace(['[', '%', '_'], ['[[]', '[%]', '[_]'], mb_substr($q, 0, 50)).'%';

        $hasil = $this->kueriKaryawanBerketerangan()
            ->where('k.Aktif', 'Y')
            ->whereNull('k.Tanggal_Resign')
            // Hanya level yang boleh menjadi atasan; sama dengan aturan server.
            ->whereIn('vg.ID_Level', $this->skema->levelAtasan())
            ->where('k.Kode_Karyawan', '<>', $kode)
            ->where(fn ($w) => $w->where('k.Nama', 'like', $pola)->orWhere('k.Kode_Karyawan', 'like', $pola))
            // Kode yang diketik persis naik ke atas: 'A2' tidak boleh tenggelam
            // di bawah A20..A29 lalu terpotong batas 20 baris.
            ->orderByRaw('CASE WHEN k.Kode_Karyawan = ? THEN 0 ELSE 1 END', [$q])
            ->orderBy('k.Nama')
            ->limit(20)
            ->get()
            ->map(fn ($r) => $this->bentukKaryawan($r))
            ->values();

        return response()->json(['data' => $hasil]);
    }

    /**
     * Nama untuk Kode_Karyawan yang menjadi jawaban 'cari_karyawan', supaya
     * layar menampilkan nama atasan, bukan kodenya.
     *
     * @return array<string, array{kode: string, nama: string, keterangan: string}>
     */
    private function namaUntukJawaban(array $jawaban): array
    {
        $kodeList = collect($this->skema->semuaField())
            ->filter(fn (array $def) => ($def['tipe'] ?? null) === 'cari_karyawan')
            ->keys()
            ->map(fn (string $kunci) => $jawaban[$kunci] ?? null)
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->unique()
            ->values()
            ->all();

        if ($kodeList === []) {
            return [];
        }

        return $this->kueriKaryawanBerketerangan()
            ->whereIn('k.Kode_Karyawan', $kodeList)
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->Kode_Karyawan => $this->bentukKaryawan($r)])
            ->all();
    }

    /**
     * Karyawan beserta sub divisi dan jabatannya — itu yang membedakan dua
     * orang bernama sama, bukan kodenya yang tidak dihafal siapa pun.
     *
     * Kode_Karyawan kembar lintas perusahaan; GROUP BY, bukan DISTINCT, karena
     * SQL Server melarang ORDER BY ekspresi di atas DISTINCT.
     */
    private function kueriKaryawanBerketerangan(): \Illuminate\Database\Query\Builder
    {
        return DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vg', 'vg.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->select('k.Kode_Karyawan', 'k.Nama', 'vds.nama_divisi', 'vds.nama_sub_divisi', 'vg.nama_jabatan')
            ->groupBy('k.Kode_Karyawan', 'k.Nama', 'vds.nama_divisi', 'vds.nama_sub_divisi', 'vg.nama_jabatan');
    }

    /** @return array{kode: string, nama: string, keterangan: string} */
    private function bentukKaryawan(object $r): array
    {
        $unit = trim((string) ($r->nama_sub_divisi ?: $r->nama_divisi));

        return [
            'kode' => (string) $r->Kode_Karyawan,
            'nama' => trim((string) $r->Nama),
            'keterangan' => implode(' - ', array_filter([$unit, trim((string) $r->nama_jabatan)])),
        ];
    }

    public function simpanDraft(Request $request): JsonResponse
    {
        if ($tolak = $this->tolakBilaPintuTertutup($request)) {
            return $tolak;
        }

        $validated = $request->validate([
            'data' => ['present', 'array'],
            'langkah' => ['required', 'integer', 'min:0', 'max:4'],
        ]);

        $this->status->simpanDraft($this->kodeKaryawan(), $validated['data'], $validated['langkah']);

        return response()->json(['ok' => true]);
    }

    /**
     * Dipanggil saat modal benar-benar tampil. Penandanya disimpan di tabel,
     * bukan di browser — supaya bisa diurus dan direset dari sisi sistem.
     */
    public function tampil(Request $request): JsonResponse
    {
        if ($tolak = $this->tolakBilaPintuTertutup($request)) {
            return $tolak;
        }

        $this->status->catatTampil($this->kodeKaryawan(), $request->session()->getId());

        return response()->json(['ok' => true]);
    }

    public function tunda(Request $request): JsonResponse
    {
        if ($tolak = $this->tolakBilaPintuTertutup($request)) {
            return $tolak;
        }

        return response()->json($this->status->catatTunda($this->kodeKaryawan()));
    }

    public function kirim(Request $request)
    {
        if ($tolak = $this->tolakBilaPintuTertutup($request)) {
            return $tolak;
        }

        // Tombol yang terkunci di wizard bisa dilewati dengan POST langsung;
        // pernyataan kebenaran data harus dituntut di sini juga.
        $request->validate(
            ['konfirmasi' => ['accepted']],
            ['konfirmasi.accepted' => 'Centang pernyataan kebenaran data sebelum mengirim.'],
        );

        try {
            $this->pengiriman->kirim(
                $this->kodeKaryawan(),
                (array) $request->input('data', []),
                ['ip' => $request->ip(), 'user_agent' => (string) $request->userAgent()],
            );
        } catch (SudahTerkirimException $e) {
            return $request->expectsJson()
                ? response()->json(['message' => $e->getMessage()], 409)
                : redirect()->route('pengumpulanData.ringkasan');
        }

        return redirect()->route('pengumpulanData.ringkasan');
    }

    private function kodeKaryawan(): string
    {
        return (string) (Auth::user()?->karyawan?->Kode_Karyawan ?? '');
    }

    /**
     * Sakelar mati harus menutup PINTU, bukan sekadar menyembunyikan modal.
     * Tanpa penjagaan ini, siapa pun masih bisa mengetik URL /pengumpulan-data
     * dan mengirim data saat kampanye sengaja dimatikan.
     *
     * Cakupan lokasi dijaga di tempat yang sama persis, dan itu disengaja.
     * Sakelar dan cakupan sama-sama menjawab satu pertanyaan — "apakah pintunya
     * terbuka untuk orang ini?" — dan memisahkannya ke dua penjaga berarti
     * menunggu hari ketika endpoint baru hanya diberi salah satunya.
     */
    private function tolakBilaPintuTertutup(Request $request)
    {
        $kode = $this->kodeKaryawan();

        // Syarat yang sama dengan penyaring modal di HandleInertiaRequests.
        // Tanpa dua yang terakhir, akun tanpa data karyawan bisa menulis baris
        // ber-Kode_Karyawan kosong (bila kampanye dibuka untuk semua lokasi),
        // dan orang yang dikecualikan admin tetap bisa mengirim lewat URL.
        if ($this->konfigurasi->aktif()
            && $kode !== ''
            && $this->cakupan->mencakup($kode)
            && ! $this->status->untuk($kode)['is_dikecualikan']) {
            return null;
        }

        $pesan = 'Pengumpulan data sedang tidak dibuka.';

        return $request->expectsJson()
            ? response()->json(['message' => $pesan], 403)
            : abort(403, $pesan);
    }

    /**
     * Daftar departemen diambil dari master divisi, bukan diketik bebas.
     * Di-cache karena isinya nyaris statis dan ini jalur render halaman.
     */
    private function pilihanDivisi(): array
    {
        return Cache::remember('pengumpulan-data:pilihan-divisi', now()->addHours(6), function (): array {
            return DB::table('View_Divisi_Sub_Divisi')
                ->selectRaw('COALESCE(NULLIF(nama_sub_divisi, \'\'), nama_divisi) AS nama')
                ->orderBy('nama')
                ->pluck('nama')
                ->filter()
                ->unique()
                ->values()
                ->all();
        });
    }
}
