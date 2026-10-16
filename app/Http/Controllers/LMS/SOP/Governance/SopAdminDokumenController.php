<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopApqcService;
use App\Services\LMS\SOP\SopDocumentControlService;
use App\Services\LMS\SOP\SopDokumenNonaktifService;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopJenisDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\SopVersiLampiranService;
use App\Services\LMS\SOP\SopVersiReviewService;
use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopNomorDokumen;
use App\Services\LMS\SOP\Support\SopPeranPengesah;
use App\Services\LMS\SOP\Support\SopPerhatianDokumen;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopTipeItemAktif;
use App\Services\LMS\SOP\Support\SopUnitKerja;
use App\Services\LMS\SOP\Support\SopVersiKeadaan;
use App\Services\LMS\SOP\Support\SopVersiTanggal;
use App\Services\SYSTEM\MasterData\Departemen\DepartemenService;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Dokumen SOP — titik awal alur pembuatan SOP.
 *
 * ALURNYA: admin mendeklarasikan SOP di sini LEBIH DULU, lalu memakainya di
 * banyak Paket SOP lewat pemilih Dokumen di Builder. Sebelum halaman ini,
 * dokumen hanya bisa lahir sebagai efek samping pembuatan item — tak ada cara
 * mendeklarasikan SOP tanpa langsung menempelkannya ke satu paket, yang
 * membalik urutan yang sebenarnya diinginkan.
 *
 * BEDA DENGAN REGISTER DI MONITORING: yang ini master data (deklarasi, ubah
 * identitas, lihat siapa memakai). Register di Monitoring menjawab pertanyaan
 * kepatuhan ("mana yang kedaluwarsa") dan memakai angka yang sama sekali lain.
 * Keduanya tidak boleh menampilkan angka kepatuhan yang sama dua kali.
 */
class SopAdminDokumenController extends Controller
{
    public function __construct(private SopDokumenService $dokumen) {}

    private function decode($id): ?int
    {
        $d = Hashids::connection('custom')->decode($id);

        return $d[0] ?? null;
    }

    private function encode($id): ?string
    {
        return $id ? Hashids::connection('custom')->encode((int) $id) : null;
    }

    /**
     * Id konten Library — menerima hashid MAUPUN id mentah.
     *
     * Berkas datang dari beberapa produsen: pemilih Library (hashid), riwayat
     * versi (hashid), dan sesekali kode yang meneruskan id apa adanya. Menolak
     * salah satunya menghasilkan 422 "Konten tidak valid" yang tak menyebut
     * apa yang salah — dua kali sudah kegagalan itu memakan waktu untuk
     * ditelusuri, dan keduanya cuma soal bentuk.
     *
     * Longgar di sini aman: rutenya digerbangi check.jabatan, dan id yang tak
     * cocok apa pun bentuknya tetap tak menemukan baris. KontenKategoriController
     * — yang justru menerbitkan id-id ini — sudah memakai pola yang sama.
     *
     * @return int|null null hanya bila tak ada bentuk yang bisa dibaca
     */
    /**
     * Bentuk lampiran (form kosong) untuk layar admin: id konten di-hash,
     * urutan tampil dipertahankan.
     *
     * @param  iterable<object>  $baris  hasil SopVersiLampiranService::untukVersi() untuk satu versi
     */
    private function bentukLampiran(iterable $baris): array
    {
        return collect($baris)->map(fn ($l) => [
            'Id_Lampiran' => (int) $l->Id_Lampiran,
            'Id_Master_Konten' => Hashids::connection('custom')->encode($l->Id_Master_Konten),
            'Judul' => $l->Judul,
            'Ext' => $l->Ext,
            'Urutan' => (int) $l->Urutan,
        ])->values()->all();
    }

    /**
     * Petakan hashid konten pada kiriman `Lampiran` menjadi id mentah.
     *
     * @return string|null pesan galat bila ada yang tak terbaca
     */
    private function decodeLampiran(array &$data): ?string
    {
        if (! array_key_exists('Lampiran', $data)) {
            return null;
        }

        foreach ($data['Lampiran'] ?? [] as $i => $l) {
            $k = $this->decodeKonten($l['Id_Master_Konten'] ?? null);
            if (! $k) {
                return 'Form kosong yang dipilih tak dikenali. Pilih ulang dari Library.';
            }
            $data['Lampiran'][$i] = ['Id_Master_Konten' => $k];
        }
        $data['Lampiran'] = array_values($data['Lampiran'] ?? []);

        return null;
    }

    private function decodeKonten($id): ?int
    {
        $hash = Hashids::connection('custom')->decode((string) $id)[0] ?? null;
        if ($hash !== null) {
            return (int) $hash;
        }

        return ctype_digit((string) $id) && (int) $id > 0 ? (int) $id : null;
    }

    public function index()
    {
        return Inertia::render('LMS/SOP/Governance/Admin/SopMasterDokumen');
    }

    /** GET /current — daftar dokumen + jumlah paket pemakai. */
    public function getDataCurrent(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));
            $tipe = $request->query('tipe');
            $page = max(1, (int) $request->query('page', 1));
            $limit = min(100, max(5, (int) $request->query('limit', 25)));

            $tahap = $request->query('tahap');

            /* 'BELUM' diteruskan apa adanya sebagai penanda "belum diisi";
               selain itu jenis datang sebagai hashid dan unit sebagai id
               mentah, mengikuti asal masternya masing-masing. */
            $jenisQ = $request->query('jenis');
            $jenis = $jenisQ === 'BELUM' ? 'BELUM' : ($jenisQ ? $this->decode($jenisQ) : null);
            $divisiQ = $request->query('divisi');
            $divisi = $divisiQ === 'BELUM' ? 'BELUM' : ($divisiQ !== null && $divisiQ !== '' ? (int) $divisiQ : null);
            $subDivisiQ = $request->query('sub_divisi');
            $subDivisi = $subDivisiQ !== null && $subDivisiQ !== '' ? (int) $subDivisiQ : null;
            $entitasQ = $request->query('entitas');
            $entitas = $entitasQ === 'BELUM' ? 'BELUM' : ($entitasQ ? $this->decode($entitasQ) : null);
            /* RUANG LINGKUP — sumbu yang berbeda dari entitas pemilik meski
               masternya sama. Pemilik menjawab "siapa yang bertanggung jawab",
               lingkup menjawab "berlaku di mana", dan sebuah dokumen bisa
               dimiliki satu PT tetapi berlaku di seluruh grup. Tanpa penyaring
               sendiri, pertanyaan kedua hanya bisa dijawab dengan membuka
               dokumen satu per satu. */
            $lingkupQ = $request->query('lingkup');
            $lingkup = $lingkupQ === 'BELUM' ? 'BELUM' : ($lingkupQ ? $this->decode($lingkupQ) : null);
            $apqcQ = $request->query('apqc');
            $apqc = $apqcQ === 'BELUM' ? 'BELUM' : ($apqcQ ? $this->decode($apqcQ) : null);
            $depQ = $request->query('departemen');
            $dep = $depQ === 'BELUM' ? 'BELUM' : ($depQ ? $this->decode($depQ) : null);

            $base = DB::table('N_SOP_Dokumen as d')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                /* DOKUMEN NONAKTIF punya pintunya sendiri dan tak boleh bocor
                   ke pintu lain. Sejak DDL-2026-055 penonaktifan TIDAK lagi
                   membalik Flag_Aktif — sengaja, supaya paket yang sedang
                   berjalan tak kehilangan dokumennya — sehingga tanpa saringan
                   ini dokumen yang sudah dipensiunkan akan tetap muncul di
                   "Semua" seolah masih hidup. */
                ->when($tahap === 'NONAKTIF', fn ($w) => $w->whereNotNull('d.Nonaktif_At'))
                ->when($tahap !== 'NONAKTIF', fn ($w) => $w->whereNull('d.Nonaktif_At'))
                ->when($tipe, fn ($w) => $w->where('d.Tipe', $tipe))
                ->when($q !== '', fn ($w) => $w->where(function ($s) use ($q) {
                    $s->where('d.Judul', 'LIKE', "%{$q}%")
                        ->orWhere('d.Nomor_SOP_Resmi', 'LIKE', "%{$q}%");
                }))
                /* Saringan tata kelola. 'BELUM' bukan pemanis: 41 dokumen lahir
                   sebelum kolom ini ada, dan tanpa cara menemukannya, sisa
                   backfill hanya bisa dirasakan — tidak bisa dihitung. */
                ->when($jenis === 'BELUM', fn ($w) => $w->whereNull('d.Jenis_Dokumen_Id'))
                ->when($jenis !== null && $jenis !== 'BELUM', fn ($w) => $w->where('d.Jenis_Dokumen_Id', $jenis))
                /* Parameter `divisi` kini membawa id DEPARTEMEN (sub-divisi):
                   itulah satu-satunya tingkat yang dipakai operasional, dan
                   layar tak lagi menawarkan divisi. Nama parameternya
                   dipertahankan supaya tautan lama tidak putus. */
                ->when($divisi === 'BELUM', fn ($w) => $w->whereNull('d.Sub_Divisi_Id'))
                ->when($divisi !== null && $divisi !== 'BELUM', fn ($w) => $w->where('d.Sub_Divisi_Id', $divisi))
                ->when($subDivisi !== null, fn ($w) => $w->where('d.Sub_Divisi_Id', $subDivisi))
                /* Saringan departemen memakai kolomnya sendiri, bukan
                   Sub_Divisi_Id: 35 dari 52 kode tak punya sub-divisi, dan
                   menyaring lewat turunannya membuat dokumennya tak pernah
                   ketemu. */
                ->when($dep === 'BELUM', fn ($w) => $w->whereNull('d.Departemen_Id'))
                ->when($dep !== null && $dep !== 'BELUM', fn ($w) => $w->where('d.Departemen_Id', $dep))
                ->when($apqc === 'BELUM', fn ($w) => $w->whereNull('d.APQC_Id'))
                ->when($apqc !== null && $apqc !== 'BELUM', fn ($w) => $w->where('d.APQC_Id', $apqc))
                ->when($entitas === 'BELUM', fn ($w) => $w->whereNull('d.Entitas_Pemilik_Id'))
                ->when($entitas !== null && $entitas !== 'BELUM', fn ($w) => $w->where('d.Entitas_Pemilik_Id', $entitas))
                ->when($lingkup === 'BELUM', fn ($w) => $w->whereNull('d.Entitas_Lingkup_Id'))
                ->when($lingkup !== null && $lingkup !== 'BELUM', fn ($w) => $w->where('d.Entitas_Lingkup_Id', $lingkup))
                /* Saringan tahap kesiapan. Ditulis sebagai kondisi SQL, bukan
                   disaring di PHP setelah paging — menyaring setelah paging
                   membuat halaman berisi lebih sedikit baris dari yang
                   dijanjikan, dan total-nya berbohong. */
                ->when($tahap === 'BARU', fn ($w) => $w->whereNull('d.Id_Versi_Aktif')
                    ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                        ->whereColumn('v.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')->where('v.Flag_Aktif', SopStatus::AKTIF)))
                ->when($tahap === 'DRAFT', fn ($w) => $w->whereNull('d.Id_Versi_Aktif')
                    ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('N_SOP_Item_Versi as v')
                        ->whereColumn('v.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')->where('v.Flag_Aktif', SopStatus::AKTIF)))
                ->when($tahap === 'SIAP', fn ($w) => $w->whereNotNull('d.Id_Versi_Aktif')
                    ->whereNotExists(fn ($s) => $s->select(DB::raw(1))->from('N_SOP_Paket_Item as i')
                        ->whereColumn('i.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')->where('i.Flag_Aktif', SopStatus::AKTIF)))
                ->when($tahap === 'DIPAKAI', fn ($w) => $w->whereNotNull('d.Id_Versi_Aktif')
                    ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('N_SOP_Paket_Item as i')
                        ->whereColumn('i.Id_SOP_Dokumen', 'd.Id_SOP_Dokumen')->where('i.Flag_Aktif', SopStatus::AKTIF)))
                /* PERHATIAN = "tanggalnya menagih", dikembalikan ke arti
                   semula (31 Agu 2026).

                   Ia sempat berarti "versi berlaku tanpa pengesah" — penggantian
                   yang masuk akal saat masa berlaku dicabut 15 Agu, tapi masa
                   berlaku dihidupkan lagi 27 Agu (DDL-2026-049) dan definisinya
                   tak ikut kembali. Akibatnya pil ini mengukur hal lain daripada
                   yang dijanjikan namanya, dan karena angkanya 0 ia tak pernah
                   muncul — admin mengira ia sedang mengingatkan, padahal ia
                   diam karena sedang menghitung sesuatu yang lain.

                   "Tanpa pengesah" tidak hilang: ia tetap dikirim per baris
                   sebagai Tanpa_Pengesah dan berlencana di daftarnya. Ia cacat
                   per-dokumen yang perlu terlihat saat dibuka, bukan daftar
                   kerja yang dikejar tiap minggu. */
                ->when($tahap === 'PERHATIAN', fn ($w) => SopPerhatianDokumen::terapkanTinjau($w))
                /* BELUM LENGKAP menyatukan BARU dan DRAFT menjadi satu pintu.
                   Keduanya sudah lama dihitung di ringkasan tapi tak pernah
                   ditawarkan sebagai penyaring — jadi lima belas dokumen yang
                   belum berlaku tak bisa ditemukan sebagai kelompok sama
                   sekali. Dipisahkan dari PERHATIAN dengan sengaja: yang ini
                   pekerjaan menggantung tanpa tenggat, dan menumpuknya di pil
                   yang sama akan menenggelamkan yang benar-benar menagih. */
                ->when($tahap === 'BELUM_LENGKAP', fn ($w) => SopPerhatianDokumen::terapkanBelumLengkap($w));

            $total = (clone $base)->count();

            /* PENGURUTAN. "Paling banyak dipakai" dihitung lewat subkueri
               berkorelasi, bukan disortir di PHP setelah paging — menyortir
               setelah paging hanya mengurutkan halaman yang kebetulan
               terambil, dan hasilnya berbohong. */
            $urut = $request->query('urut', 'terbaru');

            $rows = $base
                ->when($urut === 'pemakai', fn ($w) => $w->orderByDesc(DB::raw(
                    "(SELECT COUNT(DISTINCT i.Id_SOP_Paket) FROM N_SOP_Paket_Item i
                       WHERE i.Id_SOP_Dokumen = d.Id_SOP_Dokumen AND i.Flag_Aktif = 'Y')"
                )))
                ->when($urut === 'judul', fn ($w) => $w->orderBy('d.Judul'))
                /* Di pintu NONAKTIF, "terbaru" berarti terakhir dinonaktifkan,
                   bukan terakhir dibuat. Dokumen yang baru saja dipensiunkan
                   adalah yang paling mungkin sedang dicari. Urutan pilihan
                   admin (judul / pemakai) tetap menang bila ia memilihnya. */
                ->when($tahap === 'NONAKTIF' && ! in_array($urut, ['pemakai', 'judul']),
                    fn ($w) => $w->orderByDesc('d.Nonaktif_At'))
                /* Di pintu PERHATIAN, "terbaru" tak ada gunanya: yang dicari
                   adalah yang paling menagih. Tanpa baris ini pil itu cuma
                   penyaring, bukan daftar kerja — admin masih harus membuka
                   satu per satu untuk tahu mana yang duluan. Tanggal terdekat
                   dari dua kolom, dan NULL tak boleh naik ke atas.

                   LEWAT JOIN, bukan subkueri berkorelasi. Bentuk lamanya
                   menjalankan satu SELECT ... CROSS APPLY ke N_SOP_Item_Versi
                   PER BARIS semata-mata untuk mengurutkan. Pointernya
                   (d.Id_Versi_Aktif) menunjuk satu baris pada kunci primer
                   N_SOP_Item_Versi, jadi LEFT JOIN tak bisa menggandakan
                   baris — dan dokumen tanpa versi tetap ikut karena LEFT.

                   MIN atas dua kolom ditulis sebagai CASE, bukan
                   "(SELECT MIN(t.x) FROM (VALUES ...) t(x))": tabel turunan
                   di SQL Server TIDAK boleh mengacu kolom kueri luar, jadi
                   bentuk itu gagal dibind tanpa CROSS APPLY — persis yang
                   sedang dibuang di sini. CASE-nya menirukan MIN apa adanya:
                   NULL diabaikan, dan NULL pada keduanya tetap NULL. */
                ->when($tahap === 'PERHATIAN' && ! in_array($urut, ['pemakai', 'judul']),
                    fn ($w) => $w->leftJoin('N_SOP_Item_Versi as va', 'va.Id_SOP_Item_Versi', '=', 'd.Id_Versi_Aktif')
                        ->orderBy(DB::raw(
                            '(CASE
                                WHEN va.Tanggal_Review_Berikutnya IS NULL THEN va.Tanggal_Kadaluarsa
                                WHEN va.Tanggal_Kadaluarsa IS NULL THEN va.Tanggal_Review_Berikutnya
                                WHEN va.Tanggal_Review_Berikutnya < va.Tanggal_Kadaluarsa THEN va.Tanggal_Review_Berikutnya
                                ELSE va.Tanggal_Kadaluarsa END)'
                        )))
                ->when($tahap !== 'NONAKTIF' && $tahap !== 'PERHATIAN'
                    && ($urut === 'terbaru' || ! in_array($urut, ['pemakai', 'judul'])),
                    fn ($w) => $w->orderByDesc('d.Created_At'))
                ->orderByDesc('d.Id_SOP_Dokumen')
                ->offset(($page - 1) * $limit)->limit($limit)
                ->get(['d.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi', 'd.Tipe',
                    'd.Deskripsi', 'd.Id_Versi_Aktif', 'd.Created_At',
                    'd.Jenis_Dokumen_Id', 'd.Divisi_Id', 'd.Sub_Divisi_Id',
                    'd.Entitas_Pemilik_Id', 'd.Entitas_Lingkup_Id', 'd.APQC_Id', 'd.No_Dokumen',
                    'd.Departemen_Id',
                    'd.Nonaktif_At', 'd.Nonaktif_Paket', 'd.Nonaktif_Orang']);

            $ids = $rows->pluck('Id_SOP_Dokumen')->all();

            /* Versi yang SEDANG berlaku. Diambil terpisah, bukan di-join,
               supaya kueri daftar tetap satu bentuk sederhana dan dokumen
               tanpa versi tak hilang dari hasil. */
            $berlaku = $ids === [] ? collect() : DB::table('N_SOP_Item_Versi')
                ->whereIn('Id_SOP_Dokumen', $ids)
                ->whereIn('Id_SOP_Item_Versi', $rows->pluck('Id_Versi_Aktif')->filter()->all() ?: [0])
                ->get(['Id_SOP_Dokumen', 'Versi_Ke', 'Disahkan_Oleh',
                    'Tanggal_Review_Berikutnya', 'Tanggal_Kadaluarsa'])
                ->keyBy('Id_SOP_Dokumen');

            /* Versi draft yang menunggu diterbitkan — inilah yang membuat SOP
               tampak "sudah diisi" padahal belum berlaku bagi siapa pun. */
            $draft = $ids === [] ? collect() : DB::table('N_SOP_Item_Versi')
                ->whereIn('Id_SOP_Dokumen', $ids)
                ->where('Status', SopStatus::PAKET_DRAFT)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->select('Id_SOP_Dokumen', DB::raw('COUNT(*) as n'))
                ->groupBy('Id_SOP_Dokumen')->pluck('n', 'Id_SOP_Dokumen');

            $pemakai = $ids === [] ? collect() : DB::table('N_SOP_Paket_Item')
                ->whereIn('Id_SOP_Dokumen', $ids)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->select('Id_SOP_Dokumen', DB::raw('COUNT(DISTINCT Id_SOP_Paket) as n'))
                ->groupBy('Id_SOP_Dokumen')->pluck('n', 'Id_SOP_Dokumen');

            $jumlahVersi = $ids === [] ? collect() : DB::table('N_SOP_Item_Versi')
                ->whereIn('Id_SOP_Dokumen', $ids)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->select('Id_SOP_Dokumen', DB::raw('COUNT(*) as n'))
                ->groupBy('Id_SOP_Dokumen')->pluck('n', 'Id_SOP_Dokumen');

            /* Nama jenis dan unit diambil sekali untuk seluruh halaman.
               Menerjemahkannya per baris akan melahirkan dua query per dokumen
               — 50 baris jadi 100 query untuk data yang hampir selalu sama. */
            $namaJenis = DB::table('N_SOP_Master_Jenis_Dokumen')
                ->whereIn('Id_SOP_Jenis_Dokumen', $rows->pluck('Jenis_Dokumen_Id')->filter()->unique()->all() ?: [0])
                ->get(['Id_SOP_Jenis_Dokumen', 'Nama_Jenis', 'Level_Hirarki', 'Kode_Jenis_Dokumen'])
                ->keyBy('Id_SOP_Jenis_Dokumen');

            $namaUnit = SopUnitKerja::nama(
                $rows->pluck('Divisi_Id')->all(),
                $rows->pluck('Sub_Divisi_Id')->all(),
            );

            /* Empat baris entitas diambil sekali untuk seluruh halaman. */
            $petaApqc = DB::table('N_SOP_Master_APQC')
                ->whereIn('Id_SOP_APQC', $rows->pluck('APQC_Id')->filter()->unique()->all() ?: [0])
                ->get(['Id_SOP_APQC', 'Kode_Proses', 'Nama_Proses'])
                ->keyBy('Id_SOP_APQC');

            $petaDepartemen = app(DepartemenService::class)->peta(
                $rows->pluck('Departemen_Id')->all(),
            );

            $petaEntitas = app(EntitasService::class)->peta(array_merge(
                $rows->pluck('Entitas_Pemilik_Id')->all(),
                $rows->pluck('Entitas_Lingkup_Id')->all(),
            ));

            return ResponseHelper::success([
                'rows' => $rows->map(function ($d) use ($pemakai, $jumlahVersi, $berlaku, $draft, $namaJenis, $namaUnit, $petaEntitas, $petaApqc, $petaDepartemen) {
                    $d->Jumlah_Paket_Pemakai = (int) ($pemakai[$d->Id_SOP_Dokumen] ?? 0);
                    $d->Jumlah_Versi = (int) ($jumlahVersi[$d->Id_SOP_Dokumen] ?? 0);
                    $d->Jumlah_Draft = (int) ($draft[$d->Id_SOP_Dokumen] ?? 0);
                    $d->Punya_Versi_Berlaku = $d->Id_Versi_Aktif !== null;

                    $v = $berlaku[$d->Id_SOP_Dokumen] ?? null;
                    $d->Versi_Berlaku_Ke = $v->Versi_Ke ?? null;
                    $d->Disahkan_Oleh = $v->Disahkan_Oleh ?? null;

                    /* TAHAP KESIAPAN — sumbu utama halaman ini.
                       Admin tak butuh daftar abjad; ia butuh tahu SOP mana yang
                       belum bekerja dan kenapa. Keempat tahap ini adalah daur
                       hidup sebenarnya, bukan label hias:
                         BARU    – identitas saja, belum ada berkas
                         DRAFT   – berkas ada, tapi belum diterbitkan
                         SIAP    – berlaku, tapi belum dipakai paket mana pun
                         DIPAKAI – berlaku dan sedang mengikat karyawan       */
                    /* NONAKTIF menggantikan tahap, bukan mendampinginya. Tahap
                       menjawab "sejauh mana dokumen ini siap bekerja"; sebuah
                       dokumen yang sudah dipensiunkan tak lagi menempuh daur
                       itu, dan menampilkannya sebagai "DIPAKAI" akan terbaca
                       seperti dokumen yang masih boleh dipasang. */
                    $d->Nonaktif = $d->Nonaktif_At !== null;
                    $d->Tahap = $d->Nonaktif
                        ? 'NONAKTIF'
                        : (! $d->Punya_Versi_Berlaku
                            ? ($d->Jumlah_Versi > 0 ? 'DRAFT' : 'BARU')
                            : ($d->Jumlah_Paket_Pemakai > 0 ? 'DIPAKAI' : 'SIAP'));

                    /* Dipisah dari tahap: SOP yang DIPAKAI tapi versinya tak
                       bertanda tangan adalah keadaan terburuk, dan ia harus
                       bisa muncul bersamaan dengan tahapnya, bukan
                       menggantikannya.

                       HANYA untuk tipe yang MEMANG punya blok pengesahan.
                       FORM, VIDEO, dan KUIS tidak memilikinya — profil tipe di
                       SopTipeItem sudah menyatakan itu, dan wizard-nya pun
                       tidak pernah menawarkan langkah pengesahan kepada mereka.
                       Tanpa pertanyaan ini, formulir yang sudah benar tetap
                       dicap merah "Tanpa Pengesah" atas kekurangan yang tak
                       mungkin dipenuhi siapa pun. */
                    $d->Tanpa_Pengesah = $d->Punya_Versi_Berlaku
                        /* WAJIB, bukan berlaku: pengesahan FORM opsional
                           (5 Okt 2026) — tak ada yang perlu ditagih. */
                        && SopTipeItem::blokWajib(
                            (string) ($d->Tipe ?? SopTipeItem::DOKUMEN),
                            SopTipeItem::BLOK_PENGESAHAN,
                        )
                        && trim((string) ($d->Disahkan_Oleh ?? '')) === '';

                    /* TANGGAL YANG MENAGIH — dinilai SERVER, bukan disimpulkan
                       layar. Layar yang menghitung sendiri "sudah lewat atau
                       belum" akan memakai jam browser: satu admin melihat
                       lencana merah dan yang lain tidak, untuk baris yang sama.
                       Ambangnya pun akan jadi angka kedua yang harus dijaga
                       sejalan dengan penyaringnya. */
                    $d->Tanggal_Review_Berikutnya = $v->Tanggal_Review_Berikutnya ?? null;
                    $d->Tanggal_Kadaluarsa = $v->Tanggal_Kadaluarsa ?? null;
                    $tinjau = $d->Punya_Versi_Berlaku
                        ? SopPerhatianDokumen::nilai($d->Tanggal_Review_Berikutnya, $d->Tanggal_Kadaluarsa)
                        : ['Keadaan' => null, 'Sebab' => null, 'Tanggal' => null];
                    $d->Tinjau_Keadaan = $tinjau['Keadaan'];
                    $d->Tinjau_Sebab = $tinjau['Sebab'];
                    $d->Tinjau_Tanggal = $tinjau['Tanggal'];

                    $j = $namaJenis[$d->Jenis_Dokumen_Id] ?? null;
                    $d->Jenis_Nama = $j->Nama_Jenis ?? null;
                    $d->Jenis_Kode = $j->Kode_Jenis_Dokumen ?? null;
                    $d->Jenis_Level = $j ? (int) $j->Level_Hirarki : null;
                    $d->Jenis_Dokumen_Id = $d->Jenis_Dokumen_Id
                        ? Hashids::connection('custom')->encode($d->Jenis_Dokumen_Id)
                        : null;

                    $d->Divisi_Nama = $d->Divisi_Id ? ($namaUnit['divisi'][$d->Divisi_Id] ?? null) : null;
                    $d->Sub_Divisi_Nama = $d->Sub_Divisi_Id ? ($namaUnit['sub'][$d->Sub_Divisi_Id] ?? null) : null;

                    /* MASTER KODE DEPARTEMEN yang menjadi acuan kepemilikan
                       sejak DDL-2026-027; sub-divisi hanya turunannya. Label
                       jatuh ke sub-divisi/divisi hanya untuk dokumen lama yang
                       lahir sebelum kolomnya ada. */
                    $dp = $d->Departemen_Id ? ($petaDepartemen[$d->Departemen_Id] ?? null) : null;
                    $d->Departemen_Kode = $dp->Kode_Departemen ?? null;
                    $d->Departemen_Nama = $dp->Nama_Departemen ?? null;
                    $d->Departemen_Id = $d->Departemen_Id
                        ? Hashids::connection('custom')->encode($d->Departemen_Id)
                        : null;

                    $d->Pemilik_Label = $dp
                        ? $dp->Kode_Departemen.' — '.$dp->Nama_Departemen
                        : ($d->Sub_Divisi_Nama
                            ? ($d->Divisi_Nama ? $d->Sub_Divisi_Nama.' · '.$d->Divisi_Nama : $d->Sub_Divisi_Nama)
                            : $d->Divisi_Nama);

                    /* Id MENTAH ditahan sebelum loop di bawah menimpanya dengan
                       hashid — kode cetak pemilik masih perlu menengok petanya
                       sesudah itu. */
                    $idPemilikMentah = $d->Entitas_Pemilik_Id;

                    foreach (['Entitas_Pemilik_Id' => 'Entitas_Pemilik', 'Entitas_Lingkup_Id' => 'Entitas_Lingkup'] as $kolom => $awalan) {
                        $e = $d->{$kolom} ? ($petaEntitas[$d->{$kolom}] ?? null) : null;
                        $d->{$awalan.'_Kode'} = $e->Kode_Entitas ?? null;
                        $d->{$awalan.'_Nama'} = $e->Nama_Entitas ?? null;
                        $d->{$awalan.'_Singkat'} = $e ? ($e->Nama_Singkat ?: $e->Kode_Entitas) : null;
                        $d->{$awalan.'_Warna'} = $e->Warna ?? null;
                        $d->{$kolom} = $d->{$kolom}
                            ? Hashids::connection('custom')->encode($d->{$kolom})
                            : null;
                    }

                    /* Kode CETAK untuk segmen pemilik (DDL-2026-056). Field
                       terpisah dari Entitas_Pemilik_Kode dengan sengaja: badge
                       memperlihatkan identitas entitasnya (EHG), nomor
                       memperlihatkan kode penomorannya (HOL). Nama yang berbeda
                       membuat pemakaian yang keliru terlihat saat dibaca, bukan
                       saat tercetak di dokumen resmi. */
                    $d->Entitas_Pemilik_Kode_Nomor = EntitasService::kodePemilik(
                        $idPemilikMentah ? ($petaEntitas[$idPemilikMentah] ?? null) : null
                    );

                    $a = $d->APQC_Id ? ($petaApqc[$d->APQC_Id] ?? null) : null;
                    $d->APQC_Kode = $a->Kode_Proses ?? null;
                    $d->APQC_Nama = $a->Nama_Proses ?? null;
                    $d->APQC_Id = $d->APQC_Id ? Hashids::connection('custom')->encode($d->APQC_Id) : null;

                    /* Nomor gabungan dirakit DI SINI, dari peta yang sudah
                       dipegang halaman ini — bukan lewat SopDokumenService,
                       yang akan menembak lima query untuk tiap baris. */
                    $segmen = [
                        'jenis' => $j->Kode_Jenis_Dokumen ?? null,
                        'entitas' => $d->Entitas_Pemilik_Kode_Nomor,
                        'departemen' => $d->Departemen_Kode,
                        'lingkup' => $d->Entitas_Lingkup_Kode,
                        'apqc' => $a->Kode_Proses ?? null,
                        'nomor' => $d->No_Dokumen,
                        'revisi' => $d->Punya_Versi_Berlaku ? $d->Versi_Berlaku_Ke : null,
                    ];
                    $d->Nomor_Lengkap = SopNomorDokumen::rakit($segmen);
                    $d->Nomor_Pratinjau = SopNomorDokumen::pratinjau($segmen);

                    $d->Id_SOP_Dokumen = Hashids::connection('custom')->encode($d->Id_SOP_Dokumen);
                    unset($d->Id_Versi_Aktif);

                    return $d;
                })->all(),
                'ringkasan' => $this->ringkasanTahap($tipe),
                'total' => $total,
                'page' => $page,
                'per_page' => $limit,
                'total_page' => (int) ceil($total / $limit),
                /* DUA DAFTAR, dan bedanya penting.

                   `tipe_opsi` tetap memuat SELURUH tipe ber-versi karena ia juga
                   mengisi penyaring daftar dokumen. Menyempitkannya berarti
                   dokumen bertipe yang baru saja dimatikan tak bisa dicari lagi
                   — data lama hilang dari pandangan admin, padahal yang ditutup
                   hanya pembuatannya.

                   `tipe_opsi_baru` yang menyempit: ia mengisi pemilih "Tipe" di
                   formulir deklarasi. Menawarkan tipe yang store() tolak berarti
                   admin mengisi satu formulir penuh lalu ditolak di tombol
                   terakhir. */
                'tipe_opsi' => SopTipeItem::VERSIONED,
                'tipe_opsi_baru' => SopTipeItemAktif::aktifVersioned(),
                'urut' => $urut,
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen getDataCurrent gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Hitungan per tahap kesiapan — dipakai chip filter di atas daftar.
     *
     * Dihitung atas SELURUH dokumen, bukan halaman yang sedang tampil: angka
     * yang hanya mewakili satu halaman akan membuat admin mengira masalahnya
     * lebih kecil daripada sebenarnya.
     */
    private function ringkasanTahap(?string $tipe): array
    {
        $aktif = SopStatus::AKTIF;

        /* SATU AGREGAT, bukan muat-semua lalu hitung di PHP.
           Bentuk lamanya memuat SELURUH dokumen aktif tanpa limit, lalu
           menembakkan whereIn(<semua id>) dua kali. Di data dev (53 dokumen)
           itu tak terasa; di sekitar dokumen ke-2100 ia menembus plafon 2100
           parameter SQL Server dan gagal KERAS — halaman admin berhenti
           bekerja, bukan sekadar melambat.

           Definisi tiap pil dipindahkan apa adanya, tanpa satu pun digeser:
             - dasar populasi: Flag_Aktif = 'Y' (+ Tipe bila disaring)
             - NONAKTIF : Nonaktif_At NOT NULL — populasinya justru yang
                          dikeluarkan dari tahap mana pun, karena dokumen yang
                          sudah dipensiunkan tak lagi menempuh daur hidupnya
             - total    : Nonaktif_At NULL (dasar keempat tahap di bawah)
             - BARU     : belum ada versi berlaku DAN belum ada versi aktif
                          sama sekali
             - DRAFT    : belum ada versi berlaku TAPI sudah ada versi aktif
             - SIAP     : ada versi berlaku, belum dipakai paket mana pun
             - DIPAKAI  : ada versi berlaku, dipakai paket aktif
           "COUNT(...) > 0" yang lama diterjemahkan menjadi EXISTS: keduanya
           menjawab pertanyaan yang sama, dan EXISTS berhenti pada baris
           pertama. COUNT(DISTINCT Id_SOP_Paket) > 0 juga cuma bertanya
           "ada atau tidak" — angkanya tak pernah dibaca. */
        /* BENDERA DIHITUNG DI TABEL TURUNAN, agregatnya di luar.
           SQL Server MENOLAK subkueri di dalam fungsi agregat: bentuk
           SUM(CASE WHEN ... EXISTS (...) ...) gagal dengan "Cannot perform an
           aggregate function on an expression containing an aggregate or a
           subquery" — halaman admin membalas 500, bukan sekadar melambat.
           EXISTS karena itu dihitung sebagai kolom 0/1 di dalam, lalu SUM
           bekerja atas kolom biasa di luar. */
        $sub = DB::table('N_SOP_Dokumen as d')
            ->where('d.Flag_Aktif', $aktif)
            ->when($tipe, fn ($w) => $w->where('d.Tipe', $tipe))
            ->selectRaw(
                'CASE WHEN d.Nonaktif_At IS NULL THEN 1 ELSE 0 END AS hidup,
                 CASE WHEN d.Id_Versi_Aktif IS NULL THEN 1 ELSE 0 END AS tanpa_versi_berlaku,
                 CASE WHEN EXISTS (SELECT 1 FROM N_SOP_Item_Versi v
                                    WHERE v.Id_SOP_Dokumen = d.Id_SOP_Dokumen
                                      AND v.Flag_Aktif = ?) THEN 1 ELSE 0 END AS ada_versi,
                 CASE WHEN EXISTS (SELECT 1 FROM N_SOP_Paket_Item p
                                    WHERE p.Id_SOP_Dokumen = d.Id_SOP_Dokumen
                                      AND p.Flag_Aktif = ?) THEN 1 ELSE 0 END AS dipakai',
                [$aktif, $aktif],
            );

        $agg = DB::query()->fromSub($sub, 'x')->selectRaw(
            'SUM(x.hidup) AS total,
             SUM(CASE WHEN x.hidup = 0 THEN 1 ELSE 0 END) AS nonaktif,
             SUM(CASE WHEN x.hidup = 1 AND x.tanpa_versi_berlaku = 1 AND x.ada_versi = 0 THEN 1 ELSE 0 END) AS baru,
             SUM(CASE WHEN x.hidup = 1 AND x.tanpa_versi_berlaku = 1 AND x.ada_versi = 1 THEN 1 ELSE 0 END) AS draft,
             SUM(CASE WHEN x.hidup = 1 AND x.tanpa_versi_berlaku = 0 AND x.dipakai = 0 THEN 1 ELSE 0 END) AS siap,
             SUM(CASE WHEN x.hidup = 1 AND x.tanpa_versi_berlaku = 0 AND x.dipakai = 1 THEN 1 ELSE 0 END) AS dipakai'
        )->first();

        /* SUM atas himpunan kosong menghasilkan NULL, bukan 0 — katalog kosong
           harus tetap terbaca sebagai angka, bukan null yang menular ke layar. */
        $r = [
            'total' => (int) ($agg->total ?? 0),
            'BARU' => (int) ($agg->baru ?? 0),
            'DRAFT' => (int) ($agg->draft ?? 0),
            'SIAP' => (int) ($agg->siap ?? 0),
            'DIPAKAI' => (int) ($agg->dipakai ?? 0),
            'PERHATIAN' => 0,
            'BELUM_LENGKAP' => 0,
            'NONAKTIF' => (int) ($agg->nonaktif ?? 0),
        ];

        /* "Berlaku tanpa pengesah" SENGAJA tak dihitung di sini lagi.

           Ia tetap dikirim per baris sebagai Tanpa_Pengesah dan berlencana di
           daftarnya — ia cacat per-dokumen yang perlu terlihat saat dibuka,
           bukan daftar kerja yang dikejar mingguan. Menghitungnya tanpa ada
           yang menampilkan angkanya hanya menambah muatan yang, beberapa bulan
           lagi, akan terbaca sebagai fitur yang lupa dipasang. */

        /* BELUM LENGKAP adalah dua tahap pertama digabung — dihitung dari
           angka yang baru saja dijumlahkan, bukan dari kueri kedua yang bisa
           menyimpang darinya. */
        $r['BELUM_LENGKAP'] = $r['BARU'] + $r['DRAFT'];

        /* PERHATIAN dihitung lewat PENYARING YANG SAMA dengan daftarnya, bukan
           diulang sebagai perbandingan tanggal di PHP. Dua tulisan untuk satu
           aturan adalah cara pil mengatakan "3" sementara daftarnya menampilkan
           lima baris — dan sesudah itu admin tak memercayai keduanya. */
        $r['PERHATIAN'] = SopPerhatianDokumen::terapkanTinjau(
            DB::table('N_SOP_Dokumen as d')
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->whereNull('d.Nonaktif_At')
                ->when($tipe, fn ($w) => $w->where('d.Tipe', $tipe))
        )->count();

        return $r;
    }

    /** GET /{id}/detail — paket pemakai + riwayat versi. */
    /**
     * GET /{id}/riwayat — jejak audit satu dokumen.
     *
     * Dibaca lewat IX_N_SOP_Log_Dokumen (Id_SOP_Dokumen, Created_At DESC), yang
     * terfilter WHERE Id_SOP_Dokumen IS NOT NULL: sebagian besar baris N_SOP_Log
     * tak bersumbu dokumen sama sekali.
     *
     * JEJAK BARU TERKUMPUL SEJAK PENCATATANNYA DIPASANG. Perubahan sebelum itu
     * tak pernah direkam di mana pun dan tak bisa dibuat ulang — nilai lamanya
     * hanya hidup di dalam baris log, jadi tak ada sumber untuk memulihkannya.
     * Layar WAJIB mengatakan itu; daftar kosong tanpa keterangan terbaca sebagai
     * "tak pernah ada perubahan", yang justru menyesatkan.
     */
    public function riwayat(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        if (! DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $decoded)->exists()) {
            return ResponseHelper::error('Dokumen tidak ditemukan', 404);
        }

        try {
            $limit = min(max((int) $request->input('limit', 80), 1), 200);

            $rows = DB::table('N_SOP_Log as l')
                ->leftJoin('KPI_Users as u', 'u.Id_Users', '=', 'l.Oleh_Id_Users')
                ->leftJoin('Karyawan as k', 'k.UserID_Web', '=', 'u.Id_Users')
                ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'l.Id_SOP_Item_Versi')
                ->where('l.Id_SOP_Dokumen', $decoded)
                ->orderByDesc('l.Created_At')
                ->orderByDesc('l.Id_SOP_Log')
                ->limit($limit)
                ->get([
                    'l.Id_SOP_Log', 'l.Aksi', 'l.Keterangan', 'l.Created_At', 'l.Meta_JSON',
                    'l.Id_SOP_Item_Versi', 'v.Versi_Ke',
                    'k.Nama as Oleh_Nama', 'u.Username as Oleh_Username',
                ]);

            /* Meta_JSON dibongkar DI SERVER, bukan di layar. Kalau tidak, setiap
               tampilan yang membacanya harus mengulang penguraian yang sama —
               dan yang mengulang paling cepat menyimpang. */
            $rows = $rows->map(function ($r) {
                $meta = $r->Meta_JSON ? json_decode($r->Meta_JSON, true) : null;
                $r->Perubahan = is_array($meta['perubahan'] ?? null) ? $meta['perubahan'] : [];
                $r->Pengesah_Masuk = is_array($meta['pengesah_masuk'] ?? null) ? $meta['pengesah_masuk'] : [];
                $r->Pengesah_Keluar = is_array($meta['pengesah_keluar'] ?? null) ? $meta['pengesah_keluar'] : [];
                $r->Sifat_Revisi = $meta['sifat_revisi'] ?? null;
                /* Versi_Ke dari join hilang untuk versi yang sudah DIHAPUS —
                   barisnya tak ada lagi. Nomor dari meta yang menutupnya, dan
                   itulah satu-satunya jejak nomor versi yang pernah dihapus. */
                if ($r->Versi_Ke === null && isset($meta['versi_ke'])) {
                    $r->Versi_Ke = (int) $meta['versi_ke'];
                }
                unset($r->Meta_JSON);

                return $r;
            });

            return ResponseHelper::success($rows, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen riwayat gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    public function detail($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $decoded)
                ->first(['Id_SOP_Dokumen', 'Nomor_SOP_Resmi', 'Judul', 'Tipe', 'Deskripsi', 'Id_Versi_Aktif',
                    'Jenis_Dokumen_Id', 'Divisi_Id', 'Sub_Divisi_Id',
                    'Entitas_Pemilik_Id', 'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen',
                    'Departemen_Id']);
            if (! $dok) {
                return ResponseHelper::error('Dokumen tidak ditemukan', 404);
            }

            /* Tata kelola: tingkat hirarki mutu dan unit penanggung jawab.
               Id-nya ikut dikirim (jenis sebagai hashid) karena layar yang sama
               memakainya untuk mengisi ulang form suntingan; namanya ikut
               supaya kartu detail tak perlu memanggil master lagi. */
            $jenis = $dok->Jenis_Dokumen_Id
                ? app(SopJenisDokumenService::class)->cari((int) $dok->Jenis_Dokumen_Id, false)
                : null;
            $dok->Jenis_Nama = $jenis->Nama_Jenis ?? null;
            $dok->Jenis_Kode = $jenis->Kode_Jenis_Dokumen ?? null;
            $dok->Jenis_Level = $jenis ? (int) $jenis->Level_Hirarki : null;
            $dok->Jenis_Aktif = $jenis ? $jenis->Flag_Aktif === SopStatus::AKTIF : null;
            $dok->Jenis_Dokumen_Id = $dok->Jenis_Dokumen_Id
                ? Hashids::connection('custom')->encode($dok->Jenis_Dokumen_Id)
                : null;

            $apqcRow = $dok->APQC_Id ? app(SopApqcService::class)->cari((int) $dok->APQC_Id, false) : null;
            $dok->APQC_Kode = $apqcRow->Kode_Proses ?? null;
            $dok->APQC_Nama = $apqcRow->Nama_Proses ?? null;
            $dok->APQC_Id = $dok->APQC_Id ? Hashids::connection('custom')->encode($dok->APQC_Id) : null;

            $dok->Divisi_Id = $dok->Divisi_Id !== null ? (int) $dok->Divisi_Id : null;
            $dok->Sub_Divisi_Id = $dok->Sub_Divisi_Id !== null ? (int) $dok->Sub_Divisi_Id : null;

            $depRow = $dok->Departemen_Id
                ? app(DepartemenService::class)->cari((int) $dok->Departemen_Id, false)
                : null;
            $dok->Departemen_Kode = $depRow->Kode_Departemen ?? null;
            $dok->Departemen_Nama = $depRow->Nama_Departemen ?? null;
            $dok->Departemen_Aktif = $depRow ? $depRow->Flag_Aktif === SopStatus::AKTIF : null;
            $dok->Departemen_Id = $dok->Departemen_Id
                ? Hashids::connection('custom')->encode($dok->Departemen_Id)
                : null;

            $dok->Pemilik_Label = $depRow
                ? $depRow->Kode_Departemen.' — '.$depRow->Nama_Departemen
                : SopUnitKerja::label($dok->Divisi_Id, $dok->Sub_Divisi_Id);

            /* Entitas pemilik dan ruang lingkup. Warnanya ikut dikirim supaya
               badge tak perlu menebak; bila kosong, layar jatuh ke temaAksen. */
            $entitasSvc = app(EntitasService::class);
            /* Kode CETAK pemilik (DDL-2026-056), diambil sebelum loop di bawah
               menimpa id-nya dengan hashid. Terpisah dari Entitas_Pemilik_Kode:
               badge memperlihatkan identitas entitas, nomor memperlihatkan kode
               penomorannya. */
            $dok->Entitas_Pemilik_Kode_Nomor = EntitasService::kodePemilik(
                $dok->Entitas_Pemilik_Id ? $entitasSvc->cari((int) $dok->Entitas_Pemilik_Id, false) : null
            );

            foreach (['Entitas_Pemilik_Id' => 'Entitas_Pemilik', 'Entitas_Lingkup_Id' => 'Entitas_Lingkup'] as $kolom => $awalan) {
                $e = $dok->{$kolom} ? $entitasSvc->cari((int) $dok->{$kolom}, false) : null;
                $dok->{$awalan.'_Kode'} = $e->Kode_Entitas ?? null;
                $dok->{$awalan.'_Nama'} = $e->Nama_Entitas ?? null;
                $dok->{$awalan.'_Singkat'} = $e ? ($e->Nama_Singkat ?: $e->Kode_Entitas) : null;
                $dok->{$awalan.'_Warna'} = $e->Warna ?? null;
                $dok->{$kolom} = $dok->{$kolom}
                    ? Hashids::connection('custom')->encode($dok->{$kolom})
                    : null;
            }

            /* Nomor gabungan. Dirakit dari kolom mentah SEBELUM id-nya
               di-encode di atas — karena itu segmennya diambil dari kode yang
               sudah diterjemahkan, bukan dari id. */
            $segmen = [
                'jenis' => $jenis->Kode_Jenis_Dokumen ?? null,
                'entitas' => $dok->Entitas_Pemilik_Kode_Nomor,
                'departemen' => $dok->Departemen_Kode,
                'lingkup' => $dok->Entitas_Lingkup_Kode,
                'apqc' => $apqcRow->Kode_Proses ?? null,
                'nomor' => $dok->No_Dokumen,
                'revisi' => $this->dokumen->revisiBerlaku($decoded),
            ];
            $dok->Nomor_Lengkap = SopNomorDokumen::rakit($segmen);
            $dok->Nomor_Pratinjau = SopNomorDokumen::pratinjau($segmen);
            $dok->Nomor_Segmen_Kosong = SopNomorDokumen::segmenKosong($segmen);

            $kontrol = app(SopDocumentControlService::class);
            $paket = $kontrol->paketPemakai($decoded);

            /* JUMLAH KARYAWAN TERIKAT per paket. Selama ini dampak revisi hanya
               tampil sebagai "4 paket" — angka tanpa berat. "88 orang" membuat
               admin paham taruhannya sebelum menekan Terbitkan. */
            $idPaket = array_map(fn ($p) => (int) $p->Id_SOP_Paket, $paket);
            $orang = $idPaket === [] ? collect() : DB::table('N_SOP_Penjadwalan as pj')
                ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
                ->whereIn('pj.Id_SOP_Paket', $idPaket)
                ->where('pj.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                /* Orang yang aksesnya sudah dicabut TIDAK ikut dihitung.
                   Pencabutan menyetel Flag_Cabut_Akses='Y' dan membiarkan
                   Flag_Aktif='Y' (SopPenjadwalanService::cabutBanyakDetail),
                   jadi tanpa saringan ini kartu detail melaporkan orang yang
                   sudah tak lagi memegang dokumen ini — dan angka itu dipakai
                   admin untuk memutuskan penonaktifan. */
                ->where('d.Flag_Cabut_Akses', 'T')
                ->select('pj.Id_SOP_Paket', DB::raw('COUNT(DISTINCT d.Kode_Karyawan) n'))
                ->groupBy('pj.Id_SOP_Paket')->pluck('n', 'Id_SOP_Paket');

            foreach ($paket as $p) {
                $p->Jumlah_Karyawan = (int) ($orang[$p->Id_SOP_Paket] ?? 0);
                $p->Id_SOP_Paket = Hashids::connection('custom')->encode($p->Id_SOP_Paket);
            }

            /* BERKAS versi yang sedang berlaku — supaya admin bisa memastikan
               isi dokumennya benar tanpa membuka Builder. Memakai layanan yang
               sama dengan Builder, jadi tak ada jalur otorisasi baru. */
            $berkas = null;
            if ($dok->Id_Versi_Aktif) {
                $payload = app(\App\Services\LMS\SOP\SopKontenViewService::class)->forVersi((int) $dok->Id_Versi_Aktif);
                $berkas = isset($payload['_error']) ? null : $payload;
            }

            $riwayat = $kontrol->riwayatVersi($decoded);
            $riwayat = $this->formatRiwayatVersiRows($riwayat, $dok->Tipe ?? null,
                app(SopDokumenService::class)->dokumenSudahDijadwalkan((int) $decoded));

            $dok->Id_SOP_Dokumen = Hashids::connection('custom')->encode($dok->Id_SOP_Dokumen);
            $dok->Id_Versi_Aktif = $dok->Id_Versi_Aktif ? Hashids::connection('custom')->encode($dok->Id_Versi_Aktif) : null;

            return ResponseHelper::success([
                'dokumen' => $dok,
                'berkas' => $berkas,
                'paket_pemakai' => $paket,
                'riwayat_versi' => $riwayat,
                /* Penjumlahan hitungan per-paket, jadi seseorang yang memegang
                   dokumen ini lewat dua paket terhitung dua kali. Itu benar
                   sebagai "penempatan", bukan sebagai "orang" — jangan pakai
                   angka ini untuk menjawab "berapa orang". Yang menjawab itu
                   SopDokumenNonaktifService::sebaran(), yang menghitung kepala
                   yang berbeda. */
                'total_karyawan' => (int) $orang->sum(),
                /* Jejak penonaktifan; null selama dokumennya masih aktif. */
                'nonaktif' => app(SopDokumenNonaktifService::class)->jejak($decoded),
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen detail gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Riwayat versi dokumen dengan paginasi database teroptimasi.
     */
    public function getRiwayatVersi(Request $request, string $id)
    {
        $decoded = Hashids::connection('custom')->decode($id)[0] ?? null;
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $dok = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $decoded)
                ->first(['Id_SOP_Dokumen', 'Tipe']);
            if (! $dok) {
                return ResponseHelper::error('Dokumen tidak ditemukan', 404);
            }

            $page = max(1, (int) $request->query('page', 1));
            $limit = max(1, min(50, (int) $request->query('limit', 5)));

            $kontrol = app(SopDocumentControlService::class);
            $paginated = $kontrol->riwayatVersiPaginated($decoded, $page, $limit);

            $paginated['rows'] = $this->formatRiwayatVersiRows($paginated['rows'], $dok->Tipe ?? null,
                app(SopDokumenService::class)->dokumenSudahDijadwalkan((int) $decoded));

            return ResponseHelper::success($paginated, 'Riwayat versi berhasil dimuat');
        } catch (\Exception $e) {
            Log::error('SOP dokumen getRiwayatVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Memperkaya baris riwayat versi dengan blok pengesah, metadata berkas, dan nama ujian.
     *
     * `$sudahDijadwalkan` dijawab SEKALI per dokumen oleh pemanggil: seluruh
     * baris di sini milik dokumen yang sama, dan menanyakannya per versi
     * melahirkan satu kueri per baris untuk jawaban yang sama.
     */
    private function formatRiwayatVersiRows(array $riwayat, ?string $dokTipe, bool $sudahDijadwalkan = true): array
    {
        if (empty($riwayat)) {
            return [];
        }

        /* Tahap diambil sekali untuk seluruh halaman: delapan baris master
           yang sama dipakai berulang, dan menerjemahkannya per versi akan
           melahirkan satu query per baris untuk data yang tak pernah berubah. */
        $petaTahap = DB::table('N_SOP_Master_Tahap_Versi')
            ->get(['Id_Tahap', 'Kode_Tahap', 'Nama_Tahap', 'Warna', 'Is_Terminal', 'Nonaktifkan_Versi']);
        $tahapPerKode = $petaTahap->keyBy('Kode_Tahap');
        $petaTahap = $petaTahap->keyBy('Id_Tahap');

        /* DARI TAHAP MANA SAJA SEBUAH VERSI BOLEH TERBIT.

           Layar pernah menghafal jawabannya sendiri — "tampilkan Pratinjau
           Terbit bila tahapnya APPROVAL" — dan hafalan itu basi begitu alurnya
           bergeser: sejak DML-2026-048, APPROVAL adalah langkah Pengesahan yang
           BELUM selesai, dan satu-satunya jalan ke Filing and Registered
           berangkat dari APPROVED (Pemberkasan). Akibatnya tombol terbit
           terpasang di langkah yang server justru tolak, sementara tombol yang
           benar — melanjutkan wizard — tersembunyi di baliknya.

           Jawabannya dibaca dari tabel transisi, sumber yang sama yang dipakai
           SopDokumenService::terbitkanVersi() untuk menolak. Satu kueri untuk
           seluruh halaman, dan kebijakan alur tetap bisa berubah sebagai data
           tanpa menunggu rilis layar.

           TAHAP SAJA BELUM CUKUP. Berdiri di Pemberkasan hanya berarti versi
           itu SEDANG mengerjakan langkah terakhir, bukan sudah menuntaskannya:
           berkas final, pengesah, dan tiga tanggal masa berlaku semuanya baru
           diisi DI langkah itu. Menawarkan terbit sebelum daftar itu penuh
           melahirkan versi yang menggantung — datanya kurang dibanding versi
           lain, padahal sudah mengikat karyawan. Syaratnya diperiksa di bawah,
           per versi, lewat service yang sama yang menegakkan transisinya. */
        $idFiling = ($tahapPerKode['FILING_REGISTERED'] ?? null)?->Id_Tahap;
        $kodeBolehTerbit = $idFiling === null ? [] : DB::table('N_SOP_Master_Tahap_Versi_Transisi as x')
            ->join('N_SOP_Master_Tahap_Versi as d', 'd.Id_Tahap', '=', 'x.Dari_Tahap_Id')
            ->where('x.Ke_Tahap_Id', $idFiling)
            ->where('x.Flag_Aktif', SopStatus::AKTIF)
            ->pluck('d.Kode_Tahap')
            ->all();

        $tahapSvc = app(SopTahapVersiService::class);

        $blok = $this->dokumen->blokPengesah(
            collect($riwayat)->pluck('Id_SOP_Item_Versi')->map(fn ($x) => (int) $x)->all(),
            $riwayat,
        );

        $kontenIds = collect($riwayat)->pluck('Id_Master_Konten')->filter()->unique()->all();

        /* Form kosong untuk seluruh baris halaman dalam SATU kueri. */
        $lampiranPerVersi = app(SopVersiLampiranService::class)
            ->untukVersi(collect($riwayat)->pluck('Id_SOP_Item_Versi'));
        $berkasKonten = $kontenIds === [] ? collect() : DB::table('N_LMS_Master_Konten_Berkas')
            ->whereIn('Id_Master_Konten', $kontenIds)
            ->get(['Id_Master_Konten', 'Type_File', 'File_Path'])
            ->keyBy('Id_Master_Konten');

        $namaKonten = $kontenIds === [] ? collect() : DB::table('N_LMS_Master_Konten')
            ->whereIn('Id_Master_Konten', $kontenIds)
            ->pluck('Judul_Konten', 'Id_Master_Konten');

        $tokenUjian = collect($riwayat)->pluck('Id_Master_Ujian')->filter()->unique()->all();
        $namaUjian = $tokenUjian === [] ? collect() : DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_Master_Ujian', $tokenUjian)
            ->whereNotNull('Nama_Ujian')
            ->pluck('Nama_Ujian', 'Id_Master_Ujian');

        foreach ($riwayat as $v) {
            $v->Nama_Ujian = $v->Nama_Ujian
                ?: ($v->Id_Master_Ujian ? ($namaUjian[$v->Id_Master_Ujian] ?? null) : null);
            $v->Punya_Ujian = ! empty($v->Id_Master_Ujian);

            $v->Punya_Berkas = ! empty($v->Id_Master_Konten);
            $bk = $berkasKonten[$v->Id_Master_Konten] ?? null;
            $v->Bisa_Pratinjau = SopAksesBerkas::bisaPratinjau(
                $dokTipe ?? null, $bk->Type_File ?? null, $bk->File_Path ?? null);

            /* ISI VERSI IKUT DIKIRIM UNTUK SEMUA KEADAAN, bukan hanya draft.
               Dulu keduanya dibuang pada versi non-draft — masuk akal selama
               wizard hanya bisa dibuka untuk draft. Sejak versi terbit boleh
               dibuka untuk perubahan terbatas (30 Agu 2026), pembuangan itu
               membuat kartu "Lampiran Berkas Prosedur" tampil KOSONG pada versi
               yang jelas punya berkas — layar melaporkan tak ada lampiran pada
               dokumen yang sedang dibaca karyawan.

               Bukan pelonggaran akses: Punya_Berkas dan Bisa_Pratinjau memang
               sudah dikirim untuk semua keadaan, dan penampilnya
               (/versi/{id}/konten) melayani versi mana pun. Yang menahan
               PENGGANTIANNYA adalah Field_Boleh_Ubah, bukan hilangnya kolom
               ini.

               TOKEN UJIAN TETAP DIBUANG pada versi non-draft. Alasannya lain
               sama sekali dan masih berlaku: Id_Master_Ujian adalah token HC
               Learn mentah — bukan hashid — dan ia pernah bocor ke layar,
               tampil apa adanya sebagai judul. Yang dibutuhkan layar untuk
               menampilkannya adalah Nama_Ujian, yang memang sudah dikirim. */
            if ($v->Status !== SopStatus::PAKET_DRAFT) {
                unset($v->Id_Master_Ujian);
            }

            $v->Konten_Nama = $v->Id_Master_Konten
                ? ($namaKonten[$v->Id_Master_Konten] ?? 'Berkas terlampir')
                : null;

            $v->Id_Master_Konten = $v->Id_Master_Konten
                ? Hashids::connection('custom')->encode($v->Id_Master_Konten)
                : null;

            /* Versi yang lahir sebelum kolom Tahap_Id ada dibaca dari
               Status-nya, bukan ditampilkan tanpa tahap. */
            $tahapId = $v->Tahap_Id ?? null;
            $t = $tahapId ? ($petaTahap[$tahapId] ?? null) : null;
            $v->Tahap_Kode = $t->Kode_Tahap ?? match ($v->Status) {
                SopStatus::VERSI_PUBLISHED => 'FILING_REGISTERED',
                SopStatus::VERSI_OBSOLETE => 'OBSOLETE',
                'CANCEL', 'DIBATALKAN', 'BATAL' => 'CANCEL',
                default => (($v->Flag_Aktif ?? null) === SopStatus::INAKTIF ? 'CANCEL' : 'DRAFTING'),
            };
            $t = $t ?? ($tahapPerKode[$v->Tahap_Kode] ?? null);
            $v->Tahap_Nama = $t->Nama_Tahap ?? ($v->Tahap_Kode === 'CANCEL' ? 'Dibatalkan' : null);
            $v->Tahap_Warna = $t->Warna ?? ($v->Tahap_Kode === 'CANCEL' ? '#ef4444' : null);

            /* KEADAAN VERSI SEBAGAI FAKTA, dijawab SopVersiKeadaan — bukan
               disimpulkan ulang tiap layar. Versi batal TETAP berstatus DRAFT
               (yang berpindah tahapnya), sehingga setiap `Status === 'DRAFT'`
               di layar meloloskannya: kartu arsip menawarkan Sunting dan Hapus.

               Baris fallback `Tahap_Kode === 'CANCEL'` tetap ikut karena
               Tahap_Kode di atas bisa DITURUNKAN dari Status untuk versi lama
               yang Tahap_Id-nya masih kosong — pada baris itu $t memang tak
               membawa Nonaktifkan_Versi. */
            $keadaan = SopVersiKeadaan::fakta($v->Status, $t, $sudahDijadwalkan);
            $v->Dibatalkan = $keadaan['Dibatalkan'] || $v->Tahap_Kode === 'CANCEL';
            $v->Boleh_Disunting = $keadaan['Boleh_Disunting'] && ! $v->Dibatalkan;

            /* IZIN PER FIELD ikut ke layar, supaya layar menggambar tepat apa
               yang server terima — bukan menawarkan isian lalu ditolak. Larik
               kosong berarti terkunci penuh; layar tak perlu menerjemahkan
               keadaan menjadi aturan sendiri.

               Baris ini dulu MENIMPA daftar itu menjadi kosong untuk versi
               batal — penjaga kedua atas pertanyaan yang sudah dijawab
               SopVersiKeadaan. Sejak arsip menerima pembetulan tanggal
               (21 Sep 2026), timpaan itu justru membuat layar mengunci apa yang
               server terima. Jawabannya kini satu: yang dari keadaan.
               Fallback CANCEL tetap dipakai untuk versi lama tanpa Tahap_Id,
               yang barisnya memang tak membawa Nonaktifkan_Versi. */
            $v->Beku_Isi = $keadaan['Beku_Isi'] || $v->Dibatalkan;
            $v->Field_Boleh_Ubah = $v->Dibatalkan
                ? SopVersiKeadaan::FIELD_ARSIP
                : $keadaan['Field_Boleh_Ubah'];

            /* CAP TULIS-BERSAMAAN. Dikirim ke layar, dikembalikan saat
               menyimpan, dibandingkan ubahVersi() sebelum menimpa apa pun.
               Jatuh ke Created_At: baris yang baru lahir belum punya
               Updated_At, dan cap kosong mematikan penjaganya justru pada versi
               yang paling mungkin sedang dikerjakan dua orang. */
            $v->Cap_Versi = ($v->Updated_At ?? null) ?: ($v->Created_At ?? null);

            unset($v->Tahap_Id);

            /* Hanya draft, hanya dari tahap yang mengarah ke terbit, dan hanya
               bila checklist tahap terbit sudah penuh. Ketiganya sama persis
               dengan yang ditegakkan terbitkanVersi() — tombolnya tak boleh
               menjanjikan apa yang gerbangnya tolak.

               tujuanSah() ditembak per versi, tapi HANYA untuk baris yang lolos
               dua saringan murah di depannya: satu dokumen lazimnya punya nol
               atau satu draft di langkah Pemberkasan, jadi ini bukan N+1 atas
               seluruh halaman. Nilai syaratnya sendiri di-cache service selama
               satu permintaan. */
            $v->Boleh_Terbit = false;
            $v->Kurang_Terbit = [];

            if ($v->Status === SopStatus::PAKET_DRAFT
                && in_array($v->Tahap_Kode, $kodeBolehTerbit, true)) {
                $terbit = collect($tahapSvc->tujuanSah((int) $v->Id_SOP_Item_Versi))
                    ->firstWhere('Kode_Tahap', 'FILING_REGISTERED');

                $v->Kurang_Terbit = collect($terbit['Syarat']['daftar'] ?? [])
                    ->reject(fn ($s) => ! empty($s['terpenuhi']))
                    ->pluck('Nama')->values()->all();

                $v->Boleh_Terbit = $terbit !== null && $v->Kurang_Terbit === [];
            }

            $v->Pengesah_Terverifikasi = ! empty($v->Kode_Karyawan_Pengesah);
            $v->Pengesah = $blok[(int) $v->Id_SOP_Item_Versi] ?? [];

            unset($v->Kode_Karyawan_Pengesah);

            $v->Lampiran = $this->bentukLampiran($lampiranPerVersi[(int) $v->Id_SOP_Item_Versi] ?? []);
            $v->Id_SOP_Item_Versi = Hashids::connection('custom')->encode($v->Id_SOP_Item_Versi);
        }

        return $riwayat;
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);

        /* SAKELAR TIPE — hanya di store(), bukan di validasi().
           validasi() dipakai bersama update(), dan dokumen lama yang tipenya
           kebetulan dimatikan harus tetap bisa disunting judul, deskripsi, dan
           kepemilikannya. Sakelar ini menutup KELAHIRAN bentuk baru, bukan
           membekukan yang sudah ada. */
        if (! SopTipeItemAktif::nyala($data['Tipe'])) {
            return ResponseHelper::error(SopTipeItemAktif::pesanMati($data['Tipe']), 422);
        }

        try {
            $id = $this->dokumen->buat($data, Auth::user()->Id_Users);

            return ResponseHelper::success(
                ['Id_SOP_Dokumen' => Hashids::connection('custom')->encode($id)],
                'Dokumen berhasil dideklarasikan!',
                201,
            );
        } catch (\DomainException $e) {
            // Nomor SOP bentrok — pesannya sudah ditulis untuk admin. Tanpa
            // cabang ini ia tertelan catch di bawah dan muncul sebagai
            // "Terjadi Kesalahan Sistem" yang tak bisa ditindaklanjuti.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('SOP dokumen store gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    public function update(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $this->validasi($request);

        try {
            /* Tipe tak boleh diubah bila dokumen sudah dipakai item: pembaca
               tiap tipe berbeda, dan mengubahnya akan membuat karyawan menemui
               pembaca yang tak bisa membuka isinya. */
            $tipeLama = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $decoded)->value('Tipe');
            $dipakai = DB::table('N_SOP_Paket_Item')->where('Id_SOP_Dokumen', $decoded)
                ->where('Flag_Aktif', SopStatus::AKTIF)->exists();

            if ($dipakai && $tipeLama !== $data['Tipe']) {
                return ResponseHelper::error(
                    'Tipe dokumen tak bisa diubah selama masih dipakai paket — pembaca tiap tipe berbeda.',
                    422,
                );
            }

            if (! $this->dokumen->ubah($decoded, $data, Auth::user()->Id_Users)) {
                return ResponseHelper::error('Dokumen tidak ditemukan', 404);
            }

            return ResponseHelper::success(null, 'Dokumen berhasil diperbarui!');
        } catch (\DomainException $e) {
            // Nomor SOP bentrok — diteruskan apa adanya, lihat store().
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('SOP dokumen update gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * Aturan bentuk data versi — dipakai storeVersi DAN updateVersi.
     *
     * Menyunting draft adalah menulis ulang isian yang sama; dua daftar
     * aturan terpisah untuk bentuk data yang sama pasti menyimpang, dan
     * yang menyimpang diam-diam adalah yang jarang dilewati.
     */
    private function aturanVersi(): array
    {
        return [

            /* Nomor versi. Kosong = otomatis (lanjut dari terakhir, mulai 0).
               Diisi = dipakai apa adanya; boleh melompat maupun mundur, yang
               tak boleh cuma kembar. */
            'Versi_Ke' => 'nullable|integer|min:0|max:999',
            /* Cap tulis-bersamaan: waktu Updated_At yang DIBACA layar saat
               memuat versinya. Dibandingkan ubahVersi() sebelum menimpa apa
               pun. Nullable dengan sengaja — jalur non-layar tak punya cap
               untuk dikirim, dan menolak mereka mematikan jalur yang tak
               pernah berebut dengan siapa pun. */
            'Updated_At_Dibaca' => 'nullable|string|max:64',
            'Id_Master_Konten' => 'nullable|string',
            'Id_Master_Ujian' => 'nullable|string|max:100',
            'Nama_Ujian' => 'nullable|string|max:255',
            'Ringkasan_Perubahan' => 'nullable|string|max:1000',
            'Tanggal_Berlaku' => 'nullable|date',
            /* Empat tanggal proses. Ketiga yang pertama dicap otomatis saat
               tahap bergeser, tetapi tetap bisa ditimpa: kenyataan sering
               mendahului catatan. Kadaluarsa tak punya pasangan tahap, jadi ia
               murni diketik. */
            'Tanggal_Permintaan' => 'nullable|date',
            'Tanggal_Respon' => 'nullable|date',
            'Tanggal_Akhir_Approval' => 'nullable|date',
            /* MASA BERLAKU DIMINTA LAGI sejak 2026-08-27. Pencabutannya
               2026-08-15 beralasan "tak ada satu pun aturan yang bertindak
               atasnya", dan itu benar saat itu. Yang berubah bukan pendapat
               melainkan fakta: kadaluarsa kini menjadi gerbang terbit, dan
               slice B/C memberinya status keberlakuan KEDALUWARSA, spanduk
               pembaca, serta notifikasi hitung mundur.

               Kadaluarsa TIDAK pernah meng-obsolete versi. OBSOLETE tetap milik
               satu sebab saja — terbitnya versi pengganti — sebab lensa karyawan
               menolak versi OBSOLETE, sehingga meng-obsolete versi kadaluarsa
               berarti memberi pembacanya 403, bukan peringatan.

               Lihat plans/sop/2026-08-27-masa-berlaku-versi-design.md §2.1 & §2.3. */
            'Tanggal_Kadaluarsa' => 'nullable|date',
            'Tanggal_Review_Berikutnya' => 'nullable|date',
            'Tanggal_Obsolete' => 'nullable|date',
            'Disahkan_Oleh' => 'nullable|string|max:150',
            'Jabatan_Pengesah' => 'nullable|string|max:150',
            'Tanggal_Pengesahan' => 'nullable|date',
            'Nomor_Pengesahan' => 'nullable|string|max:100',
            /* Rujukan ke master karyawan — pendamping snapshot di atas, bukan
               penggantinya. Boleh kosong: pengesah bisa orang di luar HRIS,
               dan kosong di sini adalah catatan yang jujur bahwa namanya tak
               terverifikasi terhadap master. */
            'Kode_Karyawan_Pengesah' => 'nullable|string|max:20',

            /* BLOK PENANDA TANGAN — pengesah boleh lebih dari satu orang,
               sebab blok tanda tangan di dokumen nyata hampir selalu punya
               beberapa baris. Ini catatan deklaratif, bukan alur persetujuan:
               tanda tangan basahnya ada di dokumen fisik. */
            'Pengesah' => 'nullable|array|max:10',
            /* Form kosong dokumen FORM. `sometimes`: kunci yang tak dikirim
               berarti "jangan sentuh", larik kosong berarti "lepas semua". */
            'Lampiran' => 'sometimes|array|max:20',
            'Lampiran.*.Id_Master_Konten' => 'required',
            /* Peran DIBATASI daftar tetap sejak nilainya perlu bisa
               dikelompokkan. Teks bebas sudah terbukti menghasilkan
               "Disetujui" dan "Disetujui oleh" sebagai dua kelompok
               berbeda, plus satu salah ketik yang ikut tersimpan. */
            'Pengesah.*.Peran' => ['nullable', Rule::in(SopPeranPengesah::SEMUA)],
            'Pengesah.*.Nama' => 'nullable|string|max:150',
            'Pengesah.*.Jabatan' => 'nullable|string|max:150',
            'Pengesah.*.Kode_Karyawan' => 'nullable|string|max:20',
        ];
    }

    /**
     * Rujukan karyawan diterima dari klien, jadi harus dibuktikan.
     * Rujukan menggantung lebih buruk daripada tak ada rujukan: ia mengaku
     * terverifikasi padahal tidak.
     *
     * Seluruh blok diperiksa dalam SATU kueri — blok sepuluh baris tak
     * boleh berarti sepuluh perjalanan ke basis data.
     */
    private function periksaRujukanPengesah(array $data): ?string
    {
        $kode = collect([$data['Kode_Karyawan_Pengesah'] ?? null])
            ->merge(collect($data['Pengesah'] ?? [])->pluck('Kode_Karyawan'))
            ->filter()->unique()->values();

        if ($kode->isEmpty()) {
            return null;
        }

        $sah = DB::table('Karyawan')->whereIn('Kode_Karyawan', $kode->all())
            ->where('Aktif', SopStatus::AKTIF)->pluck('Kode_Karyawan');
        $palsu = $kode->diff($sah);

        return $palsu->isEmpty() ? null
            : 'Karyawan pengesah tidak ditemukan: '.$palsu->implode(', ');
    }

    /**
     * Urutan tiga tanggal masa berlaku.
     *
     * DI SINI, bukan sebagai aturan `after:` di daftar validasi. Ketiganya boleh
     * kosong pada draft, dan `after:Tanggal_Berlaku` pada field nullable
     * diam-diam LOLOS begitu acuannya null — sehingga versi bisa menyimpan
     * kadaluarsa yang mendahului tanggal berlakunya sendiri, dan tak ada yang
     * tahu sampai ada yang membaca dua tanggal itu berdampingan.
     *
     * @return string|null null bila urutannya sah; kalau tidak, alasannya
     */
    private function periksaUrutanTanggal(array $d): ?string
    {
        $t = fn (string $k) => filled($d[$k] ?? null) ? strtotime((string) $d[$k]) : null;

        $berlaku = $t('Tanggal_Berlaku');
        $review = $t('Tanggal_Review_Berikutnya');
        $kadaluarsa = $t('Tanggal_Kadaluarsa');

        if ($berlaku && $kadaluarsa && $kadaluarsa <= $berlaku) {
            return 'Tanggal kadaluarsa harus setelah tanggal mulai berlaku.';
        }
        if ($berlaku && $review && $review <= $berlaku) {
            return 'Jadwal review berikutnya harus setelah tanggal mulai berlaku.';
        }
        if ($review && $kadaluarsa && $review > $kadaluarsa) {
            return 'Jadwal review berikutnya tidak boleh melewati tanggal kadaluarsa.';
        }

        return null;
    }

    /** POST /{id}/versi — tambah versi (DRAFT) pada dokumen. */
    public function storeVersi(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $request->validate($this->aturanVersi());

        if (($galat = $this->periksaUrutanTanggal($data)) !== null) {
            return ResponseHelper::error($galat, 422);
        }

        if (($galat = $this->periksaRujukanPengesah($data)) !== null) {
            return ResponseHelper::error($galat, 422);
        }

        // Konten dikirim sebagai hashid dari Library, sama seperti di Builder.
        if (! empty($data['Id_Master_Konten'])) {
            $k = $this->decodeKonten($data['Id_Master_Konten']);
            if (! $k) {
                return ResponseHelper::error(
                    'Berkas yang dipilih tak dikenali. Pilih ulang dari Library.', 422);
            }
            $data['Id_Master_Konten'] = $k;
        }

        if (($galat = $this->decodeLampiran($data)) !== null) {
            return ResponseHelper::error($galat, 422);
        }

        try {
            /* Bentrok nomor ditangkap SEBELUM menulis, supaya pesannya
               menyebut angkanya. Dibiarkan sampai basis data, yang muncul
               cuma galat unique index 2601 yang tak berarti bagi admin. */
            if (isset($data['Versi_Ke']) && $data['Versi_Ke'] !== null
                && $this->dokumen->nomorVersiTerpakai($decoded, (int) $data['Versi_Ke'])) {
                return ResponseHelper::error(
                    'Versi '.str_pad((string) $data['Versi_Ke'], 2, '0', STR_PAD_LEFT)
                    .' sudah ada pada dokumen ini. Pilih nomor lain.', 422);
            }

            $idVersi = DB::transaction(
                fn () => $this->dokumen->tambahVersi($decoded, $data, Auth::user()->Id_Users));
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP dokumen storeVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        /* GERBANG 404, BUKAN 422. tambahVersi() mengembalikan null pada dua
           jalur — dokumen tak ditemukan, dan nomor versi manual yang bentrok —
           dan KEDUANYA terjadi sebelum satu baris pun ditulis (satu-satunya
           penulis, tulisVersiBaru(), selalu mengembalikan int). Karena itu
           pemeriksaannya boleh berada di luar transaksi: yang di-commit adalah
           transaksi kosong. Dijadikan DomainException ia akan berubah menjadi
           422 diam-diam — status yang berbeda untuk klien yang sama. */
        if (! $idVersi) {
            return ResponseHelper::error('Dokumen tidak ditemukan atau nomor versi tak sah', 404);
        }

        return ResponseHelper::success(
            ['Id_SOP_Item_Versi' => Hashids::connection('custom')->encode($idVersi)],
            'Versi berhasil ditambahkan sebagai draft.',
            201,
        );
    }

    /**
     * GET /options/pengesah — cari karyawan untuk mengisi pengesah.
     *
     * Balikan: [{ Kode_Karyawan, Nama, Jabatan }]. Jabatan ikut karena itulah
     * yang membuat picker ini lebih dari sekadar penghemat ketikan: memilih
     * orang seharusnya sekaligus mengisi jabatannya, bukan menyisakan satu
     * kolom lagi untuk diisi tangan (dan salah).
     *
     * JALUR JABATAN TIDAK SEWAJAR TAMPAKNYA. Karyawan.Kode_Jabatan kosong
     * untuk SELURUH karyawan aktif, sehingga join ke HRIS_Jabatan selalu
     * menghasilkan kosong. Yang terisi adalah ID_Level_Jabatan, dan view-nya
     * punya DUA kolom yang mirip — ID_Level dan ID_Level_Jabatan. Hanya yang
     * kedua yang cocok; yang pertama menghasilkan nol kecocokan dari 58
     * karyawan tanpa memberi galat apa pun.
     */
    public function optionsPengesah(Request $request)
    {
        try {
            $q = trim((string) $request->query('q', ''));

            $rows = DB::table('Karyawan as k')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                    'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                ->where('k.Aktif', SopStatus::AKTIF)
                ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s
                    ->where('k.Nama', 'LIKE', "%{$q}%")
                    ->orWhere('k.Kode_Karyawan', 'LIKE', "%{$q}%")))
                ->orderBy('k.Nama')
                /* Pagar, bukan paginasi: picker ini dipakai dengan mengetik
                   nama, bukan dengan menyusuri daftar. Batasnya dilaporkan ke
                   klien supaya "tak ketemu" tak pernah tertukar dengan
                   "terpotong di angka 30". */
                ->limit(30)
                ->get(['k.Kode_Karyawan', 'k.Nama', 'v.nama_jabatan as Jabatan']);

            return ResponseHelper::success([
                'rows' => $rows,
                'tercakup' => $rows->count() < 30,
            ], 'OK');
        } catch (\Exception $e) {
            Log::error('SOP dokumen optionsPengesah gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /versi/{idVersi}/dampak — apa yang akan terjadi bila ini diterbitkan.
     *
     * Menerbitkan revisi dokumen mengubah apa yang dibaca karyawan di SEMUA
     * paket pemakainya seketika. Tombol yang mengirim langsung tanpa
     * memperlihatkan itu meminta admin menyetujui sesuatu yang tak ia lihat.
     *
     * Yang dilaporkan bukan cuma jumlah: paket mana, dari versi berapa ke versi
     * berapa, dan berapa orang di dalamnya. "3 paket" tak memberi tahu apa pun
     * tentang paket mana.
     */
    public function dampakVersi($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $versi = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $decoded)
                ->first(['Id_SOP_Item_Versi', 'Id_SOP_Dokumen', 'Versi_Ke', 'Status']);
            if (! $versi) {
                return ResponseHelper::error('Versi tidak ditemukan', 404);
            }

            $fanout = app(\App\Services\LMS\SOP\SopDokumenFanoutService::class);

            /* Versi tanpa dokumen tak punya dampak fan-out sama sekali —
               dilaporkan nol, bukan disembunyikan. */
            $dampak = $versi->Id_SOP_Dokumen !== null
                ? $fanout->hitungDampak((int) $versi->Id_SOP_Dokumen)
                : ['item' => 0, 'paket' => 0, 'penugasan' => 0];

            $rincian = $versi->Id_SOP_Dokumen !== null
                ? $fanout->rincianPerPaket((int) $versi->Id_SOP_Dokumen, (int) $versi->Versi_Ke)
                : [];

            $ambang = \App\Services\LMS\SOP\Support\SopSetting::int('penjadwalan_async_ambang', 30);

            return ResponseHelper::success([
                'Versi_Ke' => (int) $versi->Versi_Ke,
                'Status' => $versi->Status,
                'item' => $dampak['item'],
                'paket' => $dampak['paket'],
                'penugasan' => $dampak['penugasan'],
                'rincian' => $rincian,
                'ambang' => $ambang,
                /* Di atas ambang, rekonsiliasi masuk antrian — dan antrian
                   hanya berjalan bila worker hidup. Bila mati, pekerjaannya
                   TIDAK terjadi tanpa satu pun pesan galat. Admin harus tahu
                   itu SEBELUM menekan, bukan sesudah bingung. */
                'akan_antri' => $dampak['penugasan'] > $ambang,
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen dampakVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * PUT /versi/{idVersi} — ubah versi yang masih DRAFT.
     *
     * Validasinya sengaja sama persis dengan storeVersi: menyunting draft
     * adalah menulis ulang isian yang sama, dan dua daftar aturan yang berbeda
     * untuk bentuk data yang sama pasti menyimpang.
     */
    public function updateVersi(Request $request, $idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $request->validate($this->aturanVersi());

        if (($galat = $this->periksaUrutanTanggal($data)) !== null) {
            return ResponseHelper::error($galat, 422);
        }

        if (! empty($data['Id_Master_Konten'])) {
            $k = $this->decodeKonten($data['Id_Master_Konten']);
            if (! $k) {
                return ResponseHelper::error(
                    'Berkas yang dipilih tak dikenali. Pilih ulang dari Library.', 422);
            }
            $data['Id_Master_Konten'] = $k;
        }

        if (($galat = $this->decodeLampiran($data)) !== null) {
            return ResponseHelper::error($galat, 422);
        }

        if (($galat = $this->periksaRujukanPengesah($data)) !== null) {
            return ResponseHelper::error($galat, 422);
        }

        try {
            DB::transaction(function () use ($decoded, $data) {
                $alasan = $this->dokumen->ubahVersi($decoded, $data, Auth::user()->Id_Users);

                /* GERBANG PRASYARAT, bukan penanganan galat. Statusnya 422 —
                   sama dengan cabang DomainException di bawah — jadi alasannya
                   dilempar sebagai DomainException dan closure-nya yang
                   me-rollback. Pesan yang sampai ke admin tidak berubah. */
                if ($alasan !== null) {
                    throw new \DomainException($alasan);
                }
            });
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP dokumen updateVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(null, 'Versi draft berhasil diperbarui.');
    }

    /** DELETE /versi/{idVersi} — hapus versi yang masih DRAFT. */
    public function destroyVersi($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $alasan = $this->dokumen->hapusVersi($decoded, Auth::user()->Id_Users);
            if ($alasan !== null) {
                return ResponseHelper::error($alasan, 422);
            }

            return ResponseHelper::success(null, 'Versi draft dihapus.');
        } catch (\Exception $e) {
            Log::error('SOP dokumen destroyVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * POST /versi/{idVersi}/kembalikan — jadikan versi lama berlaku lagi.
     *
     * Dipisahkan dari terbitkan, bukan digabung. Keduanya memang menggeser
     * pointer, tapi maknanya berlawanan: satu memajukan revisi, satu
     * memundurkannya. Menyatukannya membuat jejak audit menyebut "versi baru
     * diterbitkan" untuk pengembalian — kebalikan dari yang terjadi.
     */
    public function kembalikanVersi($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            if (! $this->dokumen->kembalikanVersi($decoded, Auth::user()->Id_Users)) {
                return ResponseHelper::error('Versi tidak ditemukan atau tak bertaut dokumen', 404);
            }

            return ResponseHelper::success(null,
                'Versi lama diberlakukan kembali. Kewajiban karyawan di semua paket pemakai dibuka ulang.');
        } catch (\DomainException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('SOP dokumen kembalikanVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /konten/{id}/pemakai — dokumen mana saja yang sudah memakai berkas ini.
     *
     * KENAPA ADA. Satu berkas di Library melahirkan dokumen terkendali baru
     * setiap kali seseorang melampirkannya tanpa tahu sudah ada yang memakainya.
     * Di data dev, satu berkas ("SK HOL HC EHG 07 006 01 Istirahat...") menopang
     * LIMA BELAS dokumen di delapan paket. Itulah sumber judul dan nomor SOP
     * kembar yang terlihat di arsip — bukan orang salah mengetik nomor,
     * melainkan orang membuat dokumen baru untuk berkas yang sudah punya.
     *
     * Menegakkan keunikan nomor tak menyelesaikannya: orang akan mengarang
     * nomor keenam belas. Yang menghentikannya adalah memberi tahu SEBELUM
     * dokumen keenam belas lahir, dan menyediakan jalan memakai yang sudah ada.
     *
     * MEMBERI TAHU, BUKAN MELARANG. Ada alasan sah satu berkas menopang dua
     * dokumen — lampiran yang memang dipakai dua SOP berbeda. Melarang akan
     * menjebak yang benar; memberi tahu sudah cukup menghentikan yang tak
     * sengaja, dan keputusannya tetap sadar.
     */
    /**
     * GET /konten/{id}/berkas — pratinjau berkas sebuah konten, sebelum disimpan.
     *
     * KENAPA BUKAN /versi/{id}/konten YANG SUDAH ADA. Endpoint itu membaca
     * Id_Master_Konten yang TERSIMPAN di versi, dan di wizard Pemberkasan
     * berkasnya baru saja dipilih — belum disimpan. Memakainya di sana akan
     * memutar berkas LAMA (atau 404 bila versinya memang belum punya) sambil
     * mengaku menampilkan yang barusan dipilih; pratinjau yang menunjukkan
     * benda lain lebih buruk daripada tak ada pratinjau.
     *
     * Sebelum ini layar mengarang alamatnya sendiri — /api/v1/sop/konten/{id}/file,
     * rute yang tak pernah ada — sehingga tombol Pratinjau Berkas di langkah
     * Pemberkasan selalu berakhir 404.
     *
     * Isolasi Scope='SOP' ditegakkan SopKontenViewService::forKonten(), jalur
     * yang sama dipakai penampil versi dan reader karyawan.
     */
    public function berkasKonten(Request $request, $id)
    {
        $decoded = $this->decodeKonten($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $payload = app(\App\Services\LMS\SOP\SopKontenViewService::class)
                ->forKonten($decoded, $request->query('unduh') === '1');

            if (isset($payload['_error'])) {
                return ResponseHelper::error($payload['_error'], $payload['_code'] ?? 404);
            }

            return ResponseHelper::success($payload, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen berkasKonten gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    public function pemakaiKonten($id)
    {
        $decoded = $this->decodeKonten($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $dokumen = DB::table('N_SOP_Item_Versi as v')
                ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
                ->where('v.Id_Master_Konten', $decoded)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->distinct()
                ->get(['d.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi', 'd.Tipe', 'd.Id_Versi_Aktif']);

            if ($dokumen->isEmpty()) {
                return ResponseHelper::success(['rows' => [], 'total' => 0], 'OK');
            }

            $ids = $dokumen->pluck('Id_SOP_Dokumen')->all();

            /* Paket pemakai per dokumen, diambil SEKALI. Angka ini yang membuat
               pemberitahuannya bisa ditimbang: "sudah dipakai" tanpa menyebut di
               mana tak menolong siapa pun memutuskan. */
            $paket = DB::table('N_SOP_Paket_Item as i')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
                ->whereIn('i.Id_SOP_Dokumen', $ids)
                ->where('i.Flag_Aktif', SopStatus::AKTIF)
                ->select('i.Id_SOP_Dokumen', 'p.Nama_Paket')
                ->distinct()
                ->get()
                ->groupBy('Id_SOP_Dokumen');

            $rows = $dokumen->map(fn ($d) => [
                'Id_SOP_Dokumen' => Hashids::connection('custom')->encode($d->Id_SOP_Dokumen),
                'Judul' => $d->Judul,
                'Nomor_SOP_Resmi' => $d->Nomor_SOP_Resmi,
                'Tipe' => $d->Tipe,
                'Punya_Versi_Berlaku' => ! empty($d->Id_Versi_Aktif),
                'Paket' => collect($paket[$d->Id_SOP_Dokumen] ?? [])->pluck('Nama_Paket')->values()->all(),
            ])->values();

            return ResponseHelper::success(['rows' => $rows, 'total' => $rows->count()], 'OK');
        } catch (\Exception $e) {
            Log::error('SOP dokumen pemakaiKonten gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /versi/{idVersi}/konten — isi SEBUAH versi, termasuk versi lama.
     *
     * Sampai kini hanya versi yang sedang berlaku yang bisa dibuka dari Master.
     * Padahal justru versi lama yang perlu dilihat saat ada pertanyaan: "yang
     * dibaca karyawan bulan lalu itu isinya apa?" Arsip yang tak bisa dibuka
     * bukan arsip.
     *
     * ?unduh=1 memaksa bentuk unduhan alih-alih tontonan — dipakai tipe FORM,
     * yang memang untuk diisi, bukan dibaca di layar.
     *
     * Rute ini menunjuk layanan yang SAMA dengan Builder, bukan menyalinnya;
     * yang berbeda hanya gerbangnya. User Master tak punya menuSopBuilderPage
     * maupun menuSopKontenPage, sehingga kedua rute lama membalas 403.
     */
    /**
     * GET /api/v1/sop/admin/dokumen/versi/{idVersi}/detail
     * Detail utuh & mendalam sebuah versi dokumen untuk modal audit / viewer.
     */
    public function detailVersiLengkap($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $versi = DB::table('N_SOP_Item_Versi as v')
                ->leftJoin('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
                /* Pembuat & pengubah terakhir. Created_By/Updated_By ikut turun
                   lewat v.* sebagai ANGKA Id_Users, dan angka itu tak memberi
                   tahu pembaca apa pun — layar detail selama ini mencetaknya
                   apa adanya. Nama diambil dari Karyawan bila akunnya tertaut,
                   dengan Username sebagai jaring: akun layanan dan akun lama
                   tak punya baris Karyawan. Pola yang sama dipakai
                   SopItemVersionService::byItem(). */
                ->leftJoin('KPI_Users as ub', 'ub.Id_Users', '=', 'v.Created_By')
                ->leftJoin('Karyawan as kb', 'kb.UserID_Web', '=', 'ub.Id_Users')
                ->leftJoin('KPI_Users as uu', 'uu.Id_Users', '=', 'v.Updated_By')
                ->leftJoin('Karyawan as ku', 'ku.UserID_Web', '=', 'uu.Id_Users')
                ->where('v.Id_SOP_Item_Versi', $decoded)
                ->first([
                    'v.*',
                    'd.Id_SOP_Dokumen as Dokumen_Id_Asli',
                    'd.No_Dokumen',
                    'd.Nomor_SOP_Resmi',
                    'd.Judul as Dokumen_Judul',
                    'd.Tipe as Dokumen_Tipe',
                    'd.Deskripsi as Dokumen_Deskripsi',
                    'd.Departemen_Id',
                    'd.APQC_Id',
                    'd.Entitas_Pemilik_Id',
                    'd.Entitas_Lingkup_Id',
                    'd.Jenis_Dokumen_Id',
                    'd.Id_Versi_Aktif',
                    DB::raw('COALESCE(kb.Nama, ub.Username) as Dibuat_Oleh_Nama'),
                    DB::raw('COALESCE(ku.Nama, uu.Username) as Diubah_Oleh_Nama'),
                ]);

            if (! $versi) {
                return ResponseHelper::error('Versi dokumen tidak ditemukan', 404);
            }

            // Tahap info
            $petaTahap = DB::table('N_SOP_Master_Tahap_Versi')->get()->keyBy('Id_Tahap');
            $t = $versi->Tahap_Id ? ($petaTahap[$versi->Tahap_Id] ?? null) : null;
            $tahapKode = $t->Kode_Tahap ?? match ($versi->Status) {
                SopStatus::VERSI_PUBLISHED => 'FILING_REGISTERED',
                SopStatus::VERSI_OBSOLETE => 'OBSOLETE',
                'CANCEL', 'DIBATALKAN', 'BATAL' => 'CANCEL',
                default => (($versi->Flag_Aktif ?? null) === SopStatus::INAKTIF ? 'CANCEL' : 'DRAFTING'),
            };
            $t = $t ?? DB::table('N_SOP_Master_Tahap_Versi')->where('Kode_Tahap', $tahapKode)->first();

            // Signers matrix
            $pengesah = DB::table('N_SOP_Item_Versi_Pengesah')
                ->where('Id_SOP_Item_Versi', $decoded)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->orderBy('Urutan')
                ->get([
                    'Id_SOP_Item_Versi_Pengesah',
                    'Peran',
                    'Nama',
                    'Jabatan',
                    'Kode_Karyawan',
                    'Urutan',
                    'Jenis',
                ])
                ->all();

            // Content / File info
            $berkas = null;
            if ($versi->Id_Master_Konten) {
                try {
                    $payloadKonten = app(\App\Services\LMS\SOP\SopKontenViewService::class)->forVersi($decoded, false);
                    if (! isset($payloadKonten['_error'])) {
                        $berkas = $payloadKonten;
                    }
                } catch (\Throwable $e) {
                    /* Viewer konten opsional: kegagalannya membuat blok berkas
                       kosong, bukan menggagalkan halaman. Tetap dicatat —
                       kegagalan yang tak terlihat adalah kegagalan yang tak
                       pernah diperbaiki. */
                    Log::warning('SOP detail versi: konten tak dapat dimuat', [
                        'id_versi' => $decoded,
                        'exception' => $e,
                    ]);
                }
            }

            // Review rounds
            $review = app(SopVersiReviewService::class)->daftar($decoded);

            // Alur / Langkah
            $alur = null;
            try {
                $alur = app(SopTahapVersiService::class)->langkahVersi($decoded);
            } catch (\Throwable $e) {
                /* Alur tahap opsional: kegagalannya membuat blok langkah null,
                   bukan menggagalkan halaman. Tetap dicatat. */
                Log::warning('SOP detail versi: langkah tahap tak dapat dimuat', [
                    'id_versi' => $decoded,
                    'exception' => $e,
                ]);
            }

            // Departemen, Jenis Dokumen, Entitas labels
            /* Master dibaca lewat servisnya, BUKAN nama tabel yang ditulis di
               sini. Dua nama yang dulu ditulis tangan — N_HRIS_Departemen dan
               N_HRIS_Entitas — tidak pernah ada (aslinya berawalan Master_),
               dan karena keduanya dipanggil di tengah perakitan payload,
               SELURUH endpoint melempar 500 untuk setiap dokumen yang punya
               departemen. Layar tak menampilkan galat apa pun: ia diam-diam
               jatuh ke baris ringkas dari daftar riwayat, sehingga review,
               alur, dan blok pengesah tampak "memang kosong". Servisnya
               memegang nama tabel yang benar di satu tempat. */
            $dept = $versi->Departemen_Id
                ? (app(DepartemenService::class)->cari((int) $versi->Departemen_Id, false)->Nama_Departemen ?? null)
                : null;
            $entitas = $versi->Entitas_Pemilik_Id
                ? (app(EntitasService::class)->cari((int) $versi->Entitas_Pemilik_Id, false)->Nama_Entitas ?? null)
                : null;
            /* Kunci primernya Id_SOP_Jenis_Dokumen, bukan Id_Jenis_Dokumen —
               kekeliruan yang sama, dan penyebab 500 yang kedua di blok ini. */
            $jenisDok = $versi->Jenis_Dokumen_Id
                ? (app(SopJenisDokumenService::class)->cari((int) $versi->Jenis_Dokumen_Id, false)->Nama_Jenis ?? null)
                : null;

            $result = [
                'Id_SOP_Item_Versi' => Hashids::connection('custom')->encode($versi->Id_SOP_Item_Versi),
                'Id_SOP_Dokumen' => $versi->Dokumen_Id_Asli ? Hashids::connection('custom')->encode($versi->Dokumen_Id_Asli) : null,
                'Versi_Ke' => (int) $versi->Versi_Ke,
                'Status' => $versi->Status,
                'Flag_Aktif' => $versi->Flag_Aktif,
                'Ringkasan_Perubahan' => $versi->Ringkasan_Perubahan,
                'Tahap_Kode' => $tahapKode,
                'Tahap_Nama' => $t->Nama_Tahap ?? ($tahapKode === 'CANCEL' ? 'Dibatalkan' : null),
                'Tahap_Warna' => $t->Warna ?? ($tahapKode === 'CANCEL' ? '#ef4444' : null),
                'Tahap_Deskripsi' => $t->Deskripsi ?? null,
                'Created_At' => $versi->Created_At,
                'Created_By' => $versi->Created_By,
                'Dibuat_Oleh_Nama' => $versi->Dibuat_Oleh_Nama,
                'Updated_At' => $versi->Updated_At,
                'Updated_By' => $versi->Updated_By,
                'Diubah_Oleh_Nama' => $versi->Diubah_Oleh_Nama,
                /* Versi berlaku itu POINTER dokumen, bukan status versi. Sebuah
                   dokumen bisa punya lebih dari satu baris berstatus PUBLISHED
                   (versi lama yang belum sempat dijadikan OBSOLETE), dan layar
                   yang menyimpulkan "berlaku" dari statusnya saja akan menyebut
                   dua versi sekaligus sebagai versi yang sedang dipakai
                   karyawan. Yang menentukan cuma satu: d.Id_Versi_Aktif. */
                'Is_Versi_Aktif' => $versi->Id_Versi_Aktif !== null
                    && (int) $versi->Id_Versi_Aktif === (int) $versi->Id_SOP_Item_Versi,
                'Tanggal_Permintaan' => $versi->Tanggal_Permintaan,
                'Tanggal_Respon' => $versi->Tanggal_Respon,
                'Tanggal_Akhir_Approval' => $versi->Tanggal_Akhir_Approval,
                'Tanggal_Berlaku' => $versi->Tanggal_Berlaku,
                'Tanggal_Kadaluarsa' => $versi->Tanggal_Kadaluarsa,
                'Tanggal_Review_Berikutnya' => $versi->Tanggal_Review_Berikutnya,
                'Tanggal_Publish' => $versi->Tanggal_Publish,
                'Tanggal_Obsolete' => $versi->Tanggal_Obsolete ?? null,
                'Tanggal_Pengesahan' => $versi->Tanggal_Pengesahan,
                'Disahkan_Oleh' => $versi->Disahkan_Oleh,
                'Jabatan_Pengesah' => $versi->Jabatan_Pengesah,
                'Nomor_Pengesahan' => $versi->Nomor_Pengesahan,
                /* Tanggal sebagai KEADAAN, bukan sekadar tanggal. Ambangnya
                   dibaca dari SopVersiTanggal supaya modal dan laporan Export
                   Studio tak pernah menjawab berbeda untuk versi yang sama. */
                'Keadaan_Masa_Berlaku' => SopVersiTanggal::keadaanMasaBerlaku($versi->Tanggal_Kadaluarsa),
                'Keadaan_Review' => SopVersiTanggal::keadaanReview($versi->Tanggal_Review_Berikutnya),
                'Punya_Berkas' => filled($versi->Id_Master_Konten) || filled($versi->Id_Master_Ujian),
                'Id_Master_Konten' => $versi->Id_Master_Konten ? Hashids::connection('custom')->encode($versi->Id_Master_Konten) : null,
                'Id_Master_Ujian' => $versi->Id_Master_Ujian,
                'Nama_Ujian' => $versi->Nama_Ujian,
                'Dokumen' => [
                    'Id_SOP_Dokumen' => $versi->Dokumen_Id_Asli ? Hashids::connection('custom')->encode($versi->Dokumen_Id_Asli) : null,
                    'No_Dokumen' => $versi->No_Dokumen,
                    'Nomor_SOP_Resmi' => $versi->Nomor_SOP_Resmi,
                    'Judul' => $versi->Dokumen_Judul,
                    'Tipe' => $versi->Dokumen_Tipe,
                    'Deskripsi' => $versi->Dokumen_Deskripsi,
                    'Departemen_Nama' => $dept,
                    'Jenis_Dokumen_Nama' => $jenisDok,
                    'Entitas_Nama' => $entitas,
                ],
                'Pengesah' => $pengesah,
                'Berkas' => $berkas,
                /* Form kosong dokumen FORM — bentuknya sama dengan baris
                   riwayat versi supaya kedua layar tak menerjemahkan sendiri. */
                'Lampiran' => $this->bentukLampiran(
                    app(SopVersiLampiranService::class)->untukVersi([(int) $versi->Id_SOP_Item_Versi])[(int) $versi->Id_SOP_Item_Versi] ?? []),
                'Review' => $review,
                'Alur' => $alur,
            ];

            return ResponseHelper::success($result, 'Data Detail Versi Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen detailVersiLengkap gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    public function kontenVersi(Request $request, $idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            /* Versi harus milik dokumen. Tanpa syarat ini, rute di bawah gerbang
               Master bisa dipakai membaca versi item mana pun di sistem. */
            $sah = DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $decoded)
                ->whereNotNull('Id_SOP_Dokumen')->exists();
            if (! $sah) {
                return ResponseHelper::error('Versi tidak ditemukan', 404);
            }

            $payload = app(\App\Services\LMS\SOP\SopKontenViewService::class)
                ->forVersi($decoded, $request->query('unduh') === '1');

            if (isset($payload['_error'])) {
                return ResponseHelper::error($payload['_error'], $payload['_code'] ?? 404);
            }

            return ResponseHelper::success($payload, 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen kontenVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /versi/{idVersi}/batal-terbit — apa yang terjadi bila ini dibatalkan.
     *
     * Dipanggil SEBELUM dialognya digambar: admin harus melihat versi mana yang
     * akan menggantikan sebelum menekan apa pun, dan harus tahu lebih dulu bila
     * ternyata tak ada penggantinya sama sekali.
     */
    public function pratinjauBatalTerbit($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $tahapSvc = app(SopTahapVersiService::class);
            $cancel = $tahapSvc->cariKode('CANCEL');

            $syarat = $cancel ? $tahapSvc->syaratTahap($decoded, $cancel) : ['daftar' => []];
            $kurang = collect($syarat['daftar'] ?? [])
                ->reject(fn ($x) => ! empty($x['terpenuhi']))
                ->pluck('Nama')->values()->all();

            return ResponseHelper::success([
                'Boleh_Batal' => $kurang === [],
                'Kurang' => $kurang,
                'Kandidat' => $this->dokumen->kandidatPengganti($decoded),
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen pratinjauBatalTerbit gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /versi/{idVersi}/batal-terbit — batalkan versi berlaku yang belum beredar. */
    public function batalkanVersiTerbit(Request $request, $idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $request->validate(['Id_Pengganti' => 'nullable|string|max:64']);

        $pengganti = filled($data['Id_Pengganti'] ?? null)
            ? $this->decode($data['Id_Pengganti'])
            : null;

        if (filled($data['Id_Pengganti'] ?? null) && ! $pengganti) {
            return ResponseHelper::error('Versi pengganti tak dikenali.', 422);
        }

        try {
            $galat = $this->dokumen->batalkanVersiTerbit($decoded, Auth::user()->Id_Users, $pengganti);
            if ($galat) {
                return ResponseHelper::error($galat, 422);
            }

            return ResponseHelper::success(null, $pengganti
                ? 'Versi dibatalkan dan versi sebelumnya kembali berlaku.'
                : 'Versi dibatalkan. Dokumen ini kini tak punya versi yang berlaku.');
        } catch (\Exception $e) {
            Log::error('SOP dokumen batalkanVersiTerbit gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** POST /versi/{idVersi}/terbitkan — jadikan versi berlaku + sebarkan. */
    public function terbitkanVersi(Request $request, $idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        /* SIFAT REVISI. 'Y' (mayor) = semua wajib baca ulang, seperti
           selama ini. 'T' (minor/editorial) = penyelesaian karyawan dibawa.
           Nilai apa pun selain 'T' jatuh ke mayor — arah default yang aman:
           kalau ragu, membaca ulang itu murah, melewatkan revisi penting
           tidak. */
        $sifat = $request->input('Wajib_Baca_Ulang') === 'T' ? 'T' : 'Y';

        try {
            /* Aturan isi yang sah TIDAK diperiksa di sini lagi.
               Penerbitan punya dua pintu — Master SOP dan publish paket di
               Builder yang memanggil layanannya langsung — dan aturan yang
               tinggal di controller hanya menjaga satu. Kini dijaga
               SopDokumenService::pastikanIsiSah(), yang melempar
               DomainException dengan pesan yang sama.

               Alur berlangkah DITEGAKKAN DI TRANSISI TAHAP (pindah menuntut
               rencana → review → realisasi), BUKAN di gerbang publish. Gerbang
               publish akan memutus jalur lama (Builder/paket) dan tipe
               non-DOKUMEN yang memang tak punya pengesahan. */
            if (! $this->dokumen->terbitkanVersi($decoded, Auth::user()->Id_Users, $sifat)) {
                return ResponseHelper::error('Versi tidak ditemukan atau tak bertaut dokumen', 404);
            }

            return ResponseHelper::success(null, $sifat === 'T'
                ? 'Versi diterbitkan sebagai revisi minor — karyawan yang sudah selesai tetap terhitung selesai.'
                : 'Versi diterbitkan dan disebarkan ke semua paket pemakai.');
        } catch (\DomainException $e) {
            // Isi versi tak sah — pesannya sudah ditulis untuk admin.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('SOP dokumen terbitkanVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /{id}/sebaran-nonaktif — apa yang masih memegang dokumen ini.
     *
     * Dipanggil SEBELUM tombol nonaktif ditekan. Angkanya bukan hiasan: sejak
     * DDL-2026-055 penonaktifan tak lagi ditolak ketika dokumen sedang dipakai,
     * jadi satu-satunya yang tersisa untuk menahan admin dari keputusan yang
     * tak ia pahami adalah memperlihatkan siapa yang masih memegangnya.
     */
    public function sebaranNonaktif($id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            return ResponseHelper::success(
                app(SopDokumenNonaktifService::class)->sebaran($decoded),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP dokumen sebaranNonaktif gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * DELETE /{id} — menonaktifkan dokumen.
     *
     * DULU ini menolak dengan 422 begitu ada satu item paket aktif yang
     * memakainya, dengan alasan item perujuk akan kehilangan identitasnya.
     * Larangan itu DICABUT pada 2026-08-27, dan bersamanya alasannya juga
     * hilang: penonaktifan sekarang menulis Nonaktif_At, bukan membalik
     * Flag_Aktif, sehingga item yang sudah terpasang tetap membaca judul dan
     * nomornya seperti biasa. Yang tertutup hanyalah pemakaian BARU — dokumen
     * berhenti ditawarkan di pemilih Document Builder.
     *
     * Catatan WAJIB. Penonaktifan tak bisa dibatalkan lewat layar, dan enam
     * bulan lagi satu-satunya yang menjelaskan kenapa sebuah dokumen berhenti
     * ditawarkan adalah kalimat yang ditulis admin di sini.
     */
    public function destroy(Request $request, $id)
    {
        $decoded = $this->decode($id);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $request->validate([
            'catatan' => [
                'required', 'string',
                'min:'.SopDokumenNonaktifService::CATATAN_MIN,
                'max:'.SopDokumenNonaktifService::CATATAN_MAKS,
            ],
        ], [
            'catatan.required' => 'Catatan penonaktifan wajib diisi.',
            'catatan.min' => 'Catatan terlalu pendek — tulis sebabnya supaya bisa dibaca orang lain nanti.',
            'catatan.max' => 'Catatan terlalu panjang (maksimal '.SopDokumenNonaktifService::CATATAN_MAKS.' karakter).',
        ]);

        try {
            $sebaran = app(SopDokumenNonaktifService::class)->nonaktifkan(
                $decoded,
                Auth::user()->Id_Users,
                (string) $request->input('catatan'),
            );

            /* Pesan suksesnya membawa angkanya kembali. Admin baru saja
               menyetujui sesuatu yang tetap berjalan di belakangnya; menutupnya
               dengan "berhasil" saja menyembunyikan bagian yang justru perlu ia
               ingat. */
            $pesan = $sebaran['paket'] > 0
                ? "Dokumen dinonaktifkan. {$sebaran['paket']} paket dan {$sebaran['orang']} karyawan "
                    .'tetap memegangnya sampai Anda melepasnya sendiri.'
                : 'Dokumen dinonaktifkan.';

            return ResponseHelper::success($sebaran, $pesan);
        } catch (\RuntimeException $e) {
            // Keadaan yang tak mengizinkan — pesannya sudah ditulis untuk admin.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('SOP dokumen destroy gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/dokumen/saran-nomor
     *
     * Nomor urut berikutnya untuk sebuah kombinasi identitas. Dipanggil layar
     * setiap kali salah satu dari lima penentu kombinasi berubah, karena
     * berubahnya kombinasi berarti berubahnya deret.
     */
    public function saranNomor(Request $request)
    {
        try {
            $grup = [
                'Jenis_Dokumen_Id' => $this->idAtauNull($request->query('jenis')),
                'Entitas_Pemilik_Id' => $this->idAtauNull($request->query('entitas_pemilik')),
                'Departemen_Id' => $this->idAtauNull($request->query('departemen')),
                'Entitas_Lingkup_Id' => $this->idAtauNull($request->query('entitas_lingkup')),
                'APQC_Id' => $this->idAtauNull($request->query('apqc')),
            ];

            $nomor = $request->query('no_dokumen');
            $nomor = ($nomor === null || $nomor === '')
                ? $this->dokumen->saranNoDokumen($grup)
                : (int) $nomor;

            /* REVISI pada pratinjau: revisi dokumen yang sedang disunting bila
               sudah punya versi berlaku, selain itu nol — versi pertama yang
               nanti terbit memang bernomor 00. Menampilkan "??" di sini akan
               membuat setiap dokumen baru tampak cacat padahal belum ada yang
               salah. */
            $idDok = filled($request->query('dokumen')) ? $this->decode($request->query('dokumen')) : null;
            $revisi = $idDok ? $this->dokumen->revisiBerlaku($idDok) : null;

            $segmen = $this->dokumen->segmenNomor($grup + [
                'No_Dokumen' => $nomor,
                'Revisi' => $revisi ?? 0,
            ]);

            $dokTerakhir = $this->dokumen->dokumenTerakhirGrup($grup, $idDok);
            $dokTerakhirData = null;
            if ($dokTerakhir) {
                $nomorLengkapTerakhir = $dokTerakhir->Nomor_SOP_Resmi;
                if (! $nomorLengkapTerakhir && $dokTerakhir->No_Dokumen) {
                    $segmenTerakhir = $this->dokumen->segmenNomor($grup + [
                        'No_Dokumen' => (int) $dokTerakhir->No_Dokumen,
                        'Revisi' => 0,
                    ]);
                    $nomorLengkapTerakhir = SopNomorDokumen::pratinjau($segmenTerakhir);
                }

                $dokTerakhirData = [
                    'Id_SOP_Dokumen' => $this->encode((int) $dokTerakhir->Id_SOP_Dokumen),
                    'No_Dokumen' => (int) $dokTerakhir->No_Dokumen,
                    'Nomor_SOP_Resmi' => $nomorLengkapTerakhir,
                    'Judul' => $dokTerakhir->Judul,
                ];
            }

            return ResponseHelper::success([
                'No_Dokumen' => $this->dokumen->saranNoDokumen($grup),
                'Nomor_Lengkap' => SopNomorDokumen::rakit($segmen),
                'Nomor_Pratinjau' => SopNomorDokumen::pratinjau($segmen),
                'Segmen_Kosong' => SopNomorDokumen::segmenKosong($segmen),
                'Dokumen_Terakhir' => $dokTerakhirData,
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen saranNomor gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/dokumen/versi/{idVersi}/tahap
     *
     * Tahap yang sedang ditempati beserta tujuan yang boleh dilangkahi. Daftar
     * tujuan datang dari SERVER, bukan disusun layar: alurnya tersimpan sebagai
     * data dan berubah tanpa rilis, jadi layar yang menghafalnya akan menawarkan
     * langkah yang nanti ditolak.
     */
    public function tahapVersi($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $svc = app(SopTahapVersiService::class);
            $kini = $svc->tahapVersi($decoded);
            if (! $kini) {
                return ResponseHelper::error('Versi tidak ditemukan', 404);
            }

            return ResponseHelper::success([
                'Tahap' => [
                    'Kode_Tahap' => $kini->Kode_Tahap,
                    'Nama_Tahap' => $kini->Nama_Tahap,
                    'Warna' => $kini->Warna,
                    'Deskripsi' => $kini->Deskripsi,
                    'Is_Terminal' => $kini->Is_Terminal === SopStatus::AKTIF,
                ],
                'Tujuan' => $svc->tujuanSah($decoded),
            ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen tahapVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** PUT /api/v1/sop/admin/dokumen/versi/{idVersi}/tahap */
    public function pindahTahapVersi(Request $request, $idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        $data = $request->validate([
            'Kode_Tahap' => 'required|string|max:30',
        ]);

        try {
            $tahap = DB::transaction(fn () => app(SopTahapVersiService::class)
                ->pindah($decoded, $data['Kode_Tahap'], Auth::user()->Id_Users));
        } catch (\DomainException $e) {
            // Pesannya sudah ditulis untuk admin — alur, berkas, atau pengesah.
            return ResponseHelper::error($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('SOP dokumen pindahTahapVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(
            ['Kode_Tahap' => $tahap->Kode_Tahap, 'Nama_Tahap' => $tahap->Nama_Tahap],
            'Tahap versi dipindahkan ke '.$tahap->Nama_Tahap.'.',
        );
    }

    /**
     * GET /api/v1/sop/admin/dokumen/versi/{idVersi}/review
     *
     * Catatan review sebuah versi beserta lampirannya. URL lampiran sudah
     * ditandatangani di sini — layar tak pernah memegang path GCS mentah.
     */
    public function reviewVersi($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            return ResponseHelper::success(
                app(SopVersiReviewService::class)->daftar($decoded),
                'Data Ditemukan',
            );
        } catch (\Exception $e) {
            Log::error('SOP dokumen reviewVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * PUT /api/v1/sop/admin/dokumen/versi/{idVersi}/review
     *
     * Menulis ULANG seluruh catatan review versi ini. Form mengirim daftar yang
     * dimaksud; menambah saja akan meninggalkan review yang sudah dihapus admin.
     */
    public function simpanReviewVersi(Request $request, $idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        /* ARSIP TAK MENERIMA CATATAN BARU. Pintu ini sebelumnya tak punya
           penjaga status sama sekali — bukan karena longgar disengaja,
           melainkan karena "hanya draft yang bisa disunting" diandaikan sudah
           dijaga di tempat lain. Versi batal tetap berstatus DRAFT, jadi
           andaian itu tak pernah berlaku untuknya. */
        if ($alasan = $this->dokumen->terkunciPenuh($decoded)) {
            return ResponseHelper::error($alasan, 422);
        }

        $data = $request->validate([
            'Review' => 'nullable|array|max:50',
            'Review.*.Review_Ke' => 'nullable|integer|min:1|max:999',
            'Review.*.Tanggal_Review' => 'nullable|date',
            /* Isi disanitasi SERVER sebelum disimpan; batasnya longgar karena
               HTML kaya memang panjang, tetapi tetap ada supaya satu tempelan
               raksasa tak menghabiskan kolom. */
            'Review.*.Hasil_Review' => 'nullable|string|max:200000',
            'Review.*.Lampiran' => 'nullable|array|max:10',
            'Review.*.Lampiran.*.Berkas' => 'nullable|string|max:2800000',
            'Review.*.Lampiran.*.Nama_Berkas' => 'nullable|string|max:255',
            'Review.*.Lampiran.*.Tipe_Berkas' => 'nullable|string|max:50',
            'Review.*.Lampiran.*.Ukuran_Byte' => 'nullable|integer|min:0',
        ]);

        try {
            $n = DB::transaction(fn () => app(SopVersiReviewService::class)
                ->simpan($decoded, $data['Review'] ?? [], Auth::user()->Id_Users));
        } catch (\Throwable $e) {
            Log::error('SOP dokumen simpanReviewVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }

        return ResponseHelper::success(['Jumlah' => $n], 'Catatan review disimpan.');
    }

    /**
     * GET /api/v1/sop/admin/dokumen/langkah — template untuk versi baru.
     *
     * Mengirim BENTUK YANG SAMA dengan endpoint alur versi, aksi utama ikut di
     * dalamnya. Sebelum 2026-08-27 ia hanya mengirim rangka langkah, dan layar
     * mengarang aksinya sendiri — "Simpan Draft Versi" yang dipaku di klien.
     * Satu bentuk untuk dua keadaan berarti layar tak punya dua jalur, dan
     * label tahap tak pernah lagi dirakit di tempat yang tak mengetahuinya.
     */
    public function templateLangkahVersi()
    {
        try {
            return ResponseHelper::success(
                app(SopTahapVersiService::class)->alurTemplate(), 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen templateLangkahVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /**
     * GET /api/v1/sop/admin/dokumen/versi/{idVersi}/langkah
     *
     * Seluruh yang dibutuhkan layar untuk menggambar daftar langkah, dalam SATU
     * panggilan: langkah beserta keadaan dan tanggalnya, tahap yang ditempati,
     * dan tujuan yang sah lengkap dengan syarat serta status terpenuhinya.
     *
     * Dipisah menjadi beberapa panggilan akan membuat daftar langkah sempat
     * tergambar tanpa tahu mana yang bisa ditempuh — dan checklist yang muncul
     * belakangan terbaca sebagai perubahan keadaan, padahal hanya keterlambatan.
     */
    public function langkahVersi($idVersi)
    {
        $decoded = $this->decode($idVersi);
        if (! $decoded) {
            return ResponseHelper::error('ID tidak valid', 400);
        }

        try {
            $svc = app(SopTahapVersiService::class);
            $kini = $svc->tahapVersi($decoded);
            if (! $kini) {
                return ResponseHelper::error('Versi tidak ditemukan', 404);
            }

            /* alurVersi() sudah membawa Langkah, Langkah_Aktif, Aksi_Utama,
               Aksi_Lain, DAN Tujuan. Yang ditambahkan di sini hanya identitas
               tahap dan kontrak lama Syarat_Kini — keduanya masih dibaca
               konsumen yang belum pindah, dan membuangnya berarti memaksa
               Builder ikut berubah tanpa sebab. */
            return ResponseHelper::success(
                $svc->alurVersi($decoded) + [
                    'Tahap' => [
                        'Kode_Tahap' => $kini->Kode_Tahap,
                        'Nama_Tahap' => $kini->Nama_Tahap,
                        'Warna' => $kini->Warna,
                        'Langkah' => $kini->Langkah !== null ? (int) $kini->Langkah : null,
                        'Is_Terminal' => $kini->Is_Terminal === SopStatus::AKTIF,
                        /* Muatan inilah yang dibaca wizard (endpoint /langkah).
                           Sama sumbernya dengan SopDokumenService::dibatalkan(). */
                        'Dibatalkan' => $kini->Nonaktifkan_Versi === SopStatus::AKTIF,
                    ],
                    'Syarat_Kini' => $svc->syaratTahap($decoded, $kini),
                ], 'Data Ditemukan');
        } catch (\Exception $e) {
            Log::error('SOP dokumen langkahVersi gagal', ['exception' => $e]);

            return ResponseHelper::error('Terjadi Kesalahan Sistem', 500);
        }
    }

    /** Hashid -> int, atau null bila kosong/tak terbaca. */
    private function idAtauNull($v): ?int
    {
        return filled($v) ? $this->decode($v) : null;
    }

    /**
     * Bentuk data identitas dokumen.
     *
     * KEWAJIBAN jenis dan unit pemilik ditegakkan DI SINI — di pintu manusia —
     * bukan di service. Service juga melayani backfill dan penyalinan paket,
     * yang secara definisi tak punya jawaban untuk kedua field ini.
     *
     * Konsekuensi yang disengaja: menyunting dokumen lama lewat layar ini akan
     * memaksa klasifikasinya diisi. Itu jalur backfill terpandu untuk 41
     * dokumen yang lahir sebelum kolomnya ada — bukan efek samping.
     */
    private function validasi(Request $request): array
    {
        $tipe = (string) $request->input('Tipe');

        /* Wajib hanya bila tipe ini PUNYA jenis yang sah. Tipe KUIS sengaja tak
           dipetakan ke tingkat piramid mana pun (ia alat verifikasi pemahaman,
           bukan dokumen terkendali), dan mewajibkan pilihan dari daftar kosong
           akan membuatnya mustahil disimpan. */
        $adaJenis = $tipe !== '' && app(SopJenisDokumenService::class)->opsi($tipe) !== [];
        /* Kewajiban APQC DITURUNKAN dari master, bukan ditulis di sini: bila
           admin mencabut seluruh tipe dari setiap proses, APQC berhenti diminta
           tanpa satu baris kode pun berubah. */
        $adaApqc = $tipe !== '' && app(SopApqcService::class)->opsi($tipe) !== [];

        $data = $request->validate([
            'Judul' => 'required|string|max:255',
            'Tipe' => 'required|string|in:'.implode(',', SopTipeItem::VERSIONED),
            'Nomor_SOP_Resmi' => 'nullable|string|max:60',
            'Deskripsi' => 'nullable|string|max:1000',
            'Jenis_Dokumen_Id' => [$adaJenis ? 'required' : 'nullable', 'string'],
            /* Departemen diambil dari MASTER KODE DEPARTEMEN, bukan langsung
               dari sub-divisi HRIS. Kodenya menjadi segmen nomor dokumen, dan
               daftar kode perusahaan lebih rinci daripada struktur yang
               tersimpan — 52 kode versus 32 sub-divisi. Sub_Divisi_Id dan
               Divisi_Id kini kolom turunan yang dihitung server dari binding
               departemen, dan sengaja TIDAK diterima dari klien. */
            'Departemen_Id' => 'required|string',
            'Entitas_Pemilik_Id' => 'required|string',
            'Entitas_Lingkup_Id' => 'required|string',
            'APQC_Id' => [$adaApqc ? 'required' : 'nullable', 'string'],
            /* Boleh kosong: server yang menghitung saranannya. Batas atas
               999 — batas tiga digit No. Dokumen di nomor resmi
               (SopNomorDokumen::LEBAR_NOMOR).

               MULAI DARI SATU, bukan nol: tak ada dokumen ke-nol dalam sebuah
               kombinasi. Yang mulai dari nol adalah segmen REVISI di ujung
               nomor resmi, dan keduanya kebetulan bertetangga di sana. */
            'No_Dokumen' => 'nullable|integer|min:1|max:999',
        ], [
            'No_Dokumen.min' => 'Nomor urut dokumen dimulai dari 001 — yang mulai dari 00 adalah segmen revisi, bukan urutan dokumen.',
            'APQC_Id.required' => 'Proses APQC wajib dipilih — dokumen harus jelas memetakan proses bisnis yang mana.',
            'Jenis_Dokumen_Id.required' => 'Jenis dokumen wajib dipilih — ia menentukan tingkat dokumen ini dalam hirarki mutu.',
            'Departemen_Id.required' => 'Departemen pemilik wajib dipilih — kodenya menjadi salah satu segmen nomor dokumen.',
            'Entitas_Pemilik_Id.required' => 'Entitas pemilik wajib dipilih — dokumen harus jelas milik badan hukum mana.',
            'Entitas_Lingkup_Id.required' => 'Ruang lingkup wajib dipilih — dokumen harus jelas berlaku di mana.',
        ]);

        $data['Jenis_Dokumen_Id'] = filled($data['Jenis_Dokumen_Id'] ?? null)
            ? $this->decode($data['Jenis_Dokumen_Id'])
            : null;

        if ($adaJenis && ! $data['Jenis_Dokumen_Id']) {
            throw ValidationException::withMessages([
                'Jenis_Dokumen_Id' => 'Jenis dokumen yang dipilih tidak dikenali.',
            ]);
        }

        $data['APQC_Id'] = filled($data['APQC_Id'] ?? null) ? $this->decode($data['APQC_Id']) : null;
        if ($adaApqc && ! $data['APQC_Id']) {
            throw ValidationException::withMessages([
                'APQC_Id' => 'Proses APQC yang dipilih tidak dikenali.',
            ]);
        }

        $data['Departemen_Id'] = $this->decode($data['Departemen_Id']);
        if (! $data['Departemen_Id']) {
            throw ValidationException::withMessages([
                'Departemen_Id' => 'Departemen pemilik yang dipilih tidak dikenali.',
            ]);
        }

        foreach (['Entitas_Pemilik_Id' => 'Entitas pemilik', 'Entitas_Lingkup_Id' => 'Ruang lingkup'] as $kolom => $label) {
            $data[$kolom] = $this->decode($data[$kolom]);
            if (! $data[$kolom]) {
                throw ValidationException::withMessages([
                    $kolom => $label.' yang dipilih tidak dikenali.',
                ]);
            }
        }

        return $data;
    }
}
