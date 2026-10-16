<?php

namespace App\Services\SYSTEM\Organization;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Relasi organisasi — siapa atasan, bawahan, dan rekan kerja siapa.
 *
 * KENAPA INI PENTING
 *
 * `PaAssignmentBuilderService` menurunkan calon penilai dari sini. Selama tabelnya kosong,
 * usulan ATASAN, PEER, dan BAWAHAN selalu nihil dan hanya penilaian diri yang bisa terbentuk
 * — persis keadaan sekarang: 859 peserta, nol penugasan.
 *
 * KENAPA TIDAK ADA TOMBOL "IMPOR SEMUA"
 *
 * Ada tiga sumber calon di database, dan ketiganya SALING BERTENTANGAN — kesepakatannya
 * 28,6%, 60%, dan 58,8%. Dua sumber yang berbeda sebanyak itu tidak mungkin dua-duanya
 * menggambarkan struktur yang sama. Salah satunya juga melahirkan 89.190 relasi PEER dengan
 * kelompok sampai 100 orang; "rekan kerja" sebanyak itu tidak bermakna sebagai penilai.
 *
 * Relasi yang salah menghasilkan orang dinilai oleh atasan yang keliru, dengan hasil yang
 * tetap terlihat wajar. Kesalahan yang tidak menampakkan diri adalah yang paling mahal.
 * Karena itu penulisan di sini SELALU per-baris dan selalu meninggalkan `Sumber_Data`:
 * setahun lagi harus bisa dijawab dari mana satu relasi berasal.
 *
 * ARAHNYA DISIMPAN DUA KALI, DAN ITU DISENGAJA
 *
 * "A atasan B" dan "B bawahan A" ditulis sebagai dua baris. Menyimpannya sekali lalu
 * membalik arah saat membaca terlihat hemat, tetapi setiap pembaca harus ingat membaliknya —
 * dan yang lupa akan mendapat daftar yang tampak benar dengan isi terbalik. Dua baris membuat
 * pertanyaan "siapa bawahan saya" dijawab kueri yang sama bentuknya dengan "siapa atasan saya".
 */
class OrganizationRelationService
{
    /* Batas operasi massal. Bukan angka bulat yang enak dilihat: 200 pasangan sudah
       menghasilkan 400 baris (tiap relasi ditulis dua arah), dan itu batas wajar untuk satu
       tindakan yang harus bisa ditinjau ulang admin sebelum ditekan. */
    private const BATAS_MASSAL = 200;

    /* Jaring rekan kerja tumbuh KUADRATIK: 25 orang = 600 relasi. Batasnya jauh lebih
       ketat, dan alasannya di dokumentasi `jaringPeer()`. */
    private const BATAS_PEER = 25;

    /* Batas jalur BERBASIS SARINGAN, dan sengaja jauh lebih longgar dari BATAS_MASSAL.

       Keduanya membatasi hal yang berbeda. BATAS_MASSAL membatasi daftar NAMA yang harus
       ditinjau admin satu per satu; 200 nama sudah di ambang bisa dibaca. Jalur saringan
       tidak menyodorkan nama sama sekali — yang ditinjau admin adalah SARINGAN dan
       JUMLAHNYA ("289 orang di divisi PRODUCTION SUPPORT"), dan itu tetap bisa ditimbang
       berapa pun angkanya.

       1000 dipilih karena seluruh karyawan aktif berjumlah 958: plafonnya melingkupi
       kemungkinan terbesar yang masuk akal, sambil tetap menahan kecelakaan kalau
       populasinya kelak melonjak. */
    private const BATAS_SARINGAN = 1000;

    /* SATU PASANG ORANG HANYA BOLEH PUNYA SATU JENIS HUBUNGAN.
       Diputuskan pemilik produk: yang sudah menjadi atasan tidak bisa menjadi bawahan, dan
       tidak bisa pula menjadi rekan sejawat. Sebelumnya hanya ATASAN dan BAWAHAN yang saling
       meniadakan; PEER bebas menumpang di atas hubungan hierarkis, dan pasangan yang sekaligus
       atasan dan rekan sejawat akan menilai dua kali dengan bobot dua kategori berbeda.

       Aturannya BUKAN "jenis berbeda selalu bertentangan". Satu hubungan disimpan sebagai DUA
       baris — "A atasan B" dan "B bawahan A" — jadi baris kebalikannya jenisnya memang berbeda
       dan justru wajib ada. Yang dipakai: himpunan SETARA dari satu penulisan.

           menulis (R, S->T)  =>  setara: { (R, S->T), (KEBALIKAN[R], T->S) }

       Apa pun di antara S dan T yang berada DI LUAR himpunan itu adalah pertentangan.

       Contoh yang membedakannya:
           menulis (BAWAHAN, B->A) saat ada (ATASAN, A->B)  -> setara, LOLOS (ini kebalikannya)
           menulis (BAWAHAN, A->B) saat ada (ATASAN, A->B)  -> di luar, DITOLAK
           menulis (PEER,    A->B) saat ada (ATASAN, A->B)  -> di luar, DITOLAK */

    /* Arah yang SALING MENIADAKAN untuk satu pasang orang.
       Nilainya sama dengan KEBALIKAN dikurangi PEER, dan itu kebetulan yang menyesatkan —
       artinya berbeda, jadi keduanya sengaja tidak digabung:
         KEBALIKAN   = baris yang WAJIB IKUT ditulis  ("A atasan B" -> "B bawahan A")
         BERLAWANAN  = baris yang TIDAK BOLEH ADA     ("A atasan B" -> "A bawahan B" mustahil)
       PEER tidak masuk sini. Apakah rekan kerja seharusnya juga meniadakan atasan/bawahan
       adalah pertanyaan yang belum dijawab siapa pun, dan menebaknya di sini akan menolak
       data yang mungkin sah. */
    private const BERLAWANAN = [
        'ATASAN' => 'BAWAHAN',
        'BAWAHAN' => 'ATASAN',
    ];

    /* Hasil `sisipkanSatu()`. Dulu ia hanya memulangkan 0/1, dan itu tidak cukup begitu ada
       alasan KETIGA untuk tidak menulis: pemanggil harus bisa membedakan "sudah ada" (bukan
       kegagalan) dari "berlawanan arah" (harus dikatakan). */
    private const SISIP_BERLAWANAN = -1;

    private const SISIP_SUDAH_ADA = 0;

    private const SISIP_DIBUAT = 1;

    /** Peta id jenis relasi -> barisnya. Tabelnya sekecil ini, dibaca sekali per permintaan. */
    private ?array $jenisById = null;

    /** Relasi berpasangan: menulis yang satu WAJIB menulis kebalikannya. */
    private const KEBALIKAN = [
        'ATASAN' => 'BAWAHAN',
        'BAWAHAN' => 'ATASAN',
        'PEER' => 'PEER',
    ];

    /**
     * Menyaring baris yang BERLAKU pada satu tanggal — bukan sekadar yang belum dicabut.
     *
     * Halaman ini menawarkan "Berlaku Dari" dan "Berlaku Sampai", menyimpannya, lalu
     * mengabaikannya di setiap pembacaan. `PaAssignmentBuilderService` TIDAK mengabaikannya.
     * Jadi relasi yang sudah lewat masa berlakunya tetap tampil di Inspector dan bagan
     * sebagai struktur yang berlaku, sementara mesin penugasan sudah tidak melihatnya —
     * dan selisih itu tidak menampakkan diri di mana pun.
     *
     * Ini dipakai untuk pertanyaan "siapa atasan saya SEKARANG". Tabel manajemen sengaja
     * tidak memakainya: di sana baris di luar masa berlaku tetap ditampilkan, tetapi
     * DIBERI TANDA.
     */
    private function berlakuPada($q, string $alias = 'r', ?string $acuan = null)
    {
        $acuan ??= now()->format('Y-m-d');

        return $q->where(function ($w) use ($alias, $acuan) {
            $w->whereNull("{$alias}.Berlaku_Dari")->orWhere("{$alias}.Berlaku_Dari", '<=', $acuan);
        })->where(function ($w) use ($alias, $acuan) {
            $w->whereNull("{$alias}.Berlaku_Sampai")->orWhere("{$alias}.Berlaku_Sampai", '>=', $acuan);
        });
    }

    /**
     * Keadaan sekarang: berapa relasi per jenis, dan berapa karyawan yang tercakup.
     *
     * Cakupan dihitung terhadap karyawan AKTIF, bukan terhadap seluruh baris relasi. Relasi
     * yang menunjuk orang yang sudah keluar tetap tersimpan sebagai riwayat, tetapi ia tidak
     * menambah cakupan — dan angka cakupan yang menghitungnya akan melaporkan struktur yang
     * lebih lengkap daripada kenyataannya.
     *
     * @return array<string, mixed>
     */
    public function ringkasan(): array
    {
        $karyawanAktif = DB::table('Karyawan')->where('Aktif', 'Y')->count();

        $jenis = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Flag_Aktif', 'Y')
            ->orderBy('Urutan')
            ->get([
                'Id_Organization_Relationship_Type as id',
                'Kode_Relasi as kode',
                'Nama_Relasi as nama',
                'Deskripsi as deskripsi',
                'Flag_Relasi_Setara as setara',
                'Flag_Pembentuk_Struktur_Organisasi as struktur',
            ]);

        /* Cacah dan cakupan per jenis, SATU kueri. Menanyakannya per jenis berarti tujuh
           perjalanan ke database untuk satu tabel.

           Dihitung atas relasi yang BERLAKU HARI INI, sama dengan yang dibaca mesin
           penugasan. Angka cakupan yang menghitung relasi kedaluwarsa akan melaporkan
           struktur yang lebih lengkap daripada yang benar-benar dipakai. */
        $cacah = $this->berlakuPada(
            DB::table('N_HRIS_Organization_Relationship as r')
                ->join('Karyawan as k', function ($j) {
                    $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Subjek')
                        ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Subjek');
                })
                ->where('r.Flag_Aktif', 'Y')
                ->whereNull('r.Cancelled_At')
                ->where('k.Aktif', 'Y'),
        )
            ->groupBy('r.Id_Organization_Relationship_Type')
            ->get([
                'r.Id_Organization_Relationship_Type as tipe_id',
                DB::raw('COUNT(*) as baris'),
                DB::raw('COUNT(DISTINCT r.Kode_Karyawan_Subjek) as subjek'),
            ])
            ->keyBy('tipe_id');

        $baris = $jenis->map(fn ($t) => [
            'id' => (int) $t->id,
            'kode' => $t->kode,
            'nama' => $t->nama,
            'deskripsi' => $t->deskripsi,
            'setara' => $t->setara === 'Y',
            'pembentuk_struktur' => $t->struktur === 'Y',
            'baris' => (int) ($cacah[$t->id]->baris ?? 0),
            'karyawan_tercakup' => (int) ($cacah[$t->id]->subjek ?? 0),
            'cakupan_persen' => $karyawanAktif === 0 ? 0.0 : round(
                ((int) ($cacah[$t->id]->subjek ?? 0)) / $karyawanAktif * 100, 1,
            ),
        ])->all();

        $belumDicabut = DB::table('N_HRIS_Organization_Relationship')
            ->where('Flag_Aktif', 'Y')->whereNull('Cancelled_At')->count();

        $berlakuHariIni = $this->berlakuPada(
            DB::table('N_HRIS_Organization_Relationship as r')
                ->where('r.Flag_Aktif', 'Y')->whereNull('r.Cancelled_At'),
        )->count();

        return [
            'karyawan_aktif' => $karyawanAktif,

            /* Yang berlaku HARI INI — bukan sekadar yang belum dicabut. Inilah yang dilihat
               mesin penugasan, jadi inilah angka yang boleh disebut "relasi aktif". */
            'total_relasi' => $berlakuHariIni,

            /* Selisihnya tetap disebut, tidak disembunyikan: baris yang masih hidup tetapi
               di luar masa berlakunya ada di tabel dan bisa dicabut, hanya tidak dipakai. */
            'total_belum_dicabut' => $belumDicabut,
            'total_di_luar_masa_berlaku' => max(0, $belumDicabut - $berlakuHariIni),

            'jenis' => $baris,
            'anomali' => $this->anomali(),
        ];
    }

    /**
     * Keadaan yang mustahil atau merusak, kalau ada.
     *
     * Diperiksa SETIAP kali ringkasan dibaca, bukan sekali saat impor: relasi bisa masuk
     * lewat jalur lain, dan karyawan bisa keluar sesudah relasinya dibuat. Pemeriksaan yang
     * hanya berjalan saat menulis akan melaporkan keadaan berbulan lalu.
     *
     * @return array<string, mixed>
     */
    private function anomali(): array
    {
        $dasar = fn () => DB::table('N_HRIS_Organization_Relationship as r')
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At');

        return [
            /* Orang jadi atasan dirinya sendiri. Bukan sekadar aneh: ia akan diusulkan
               menilai dirinya lewat kategori ATASAN, di samping SELF — dan bobot keduanya
               dijumlahkan seolah dua orang berbeda. */
            'relasi_ke_diri_sendiri' => $dasar()
                ->whereColumn('r.Kode_Karyawan_Subjek', 'r.Kode_Karyawan_Terkait')
                ->whereColumn('r.Kode_Perusahaan_Subjek', 'r.Kode_Perusahaan_Terkait')
                ->count(),

            /* Relasi yang menunjuk karyawan tidak aktif. Penugasannya akan terbentuk dan
               tidak akan pernah diisi. */
            'menunjuk_karyawan_nonaktif' => $dasar()
                ->leftJoin('Karyawan as k', function ($j) {
                    $j->on('k.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                        ->on('k.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
                })
                ->where(fn ($q) => $q->whereNull('k.Kode_Karyawan')->orWhere('k.Aktif', '<>', 'Y'))
                ->count(),

            /* Pasangan yang tidak punya kebalikannya. Salah satu arah akan menjawab
               "tidak ada" pada pertanyaan yang arah lainnya menjawab "ada". */
            'pasangan_timpang' => $this->cacahPasanganTimpang(),

            /* Pasangan yang tercatat atasan DAN bawahan sekaligus. Penulisan sekarang
               menolaknya, tetapi penjaga itu TIDAK berlaku surut — baris yang sudah ada
               sebelum aturannya dipasang tetap di sana, dan hanya terlihat kalau dicari. */
            'arah_bertentangan' => $this->cacahArahBertentangan(),

            /* Pasang yang memegang lebih dari satu jenis hubungan. Aturan "satu pasang
               satu relasi" berlaku sejak sekarang dan TIDAK surut; yang lama hanya
               terlihat kalau dicari. */
            'relasi_ganda' => $this->cacahPasanganBerelasiGanda(),
        ];
    }

    /**
     * Pasang orang yang tercatat ATASAN dan BAWAHAN sekaligus — keadaan yang mustahil.
     *
     * Dihitung per PASANG, bukan per baris: satu pertentangan menghasilkan dua baris, dan
     * melaporkan "2 pertentangan" untuk satu pasang orang membuat angkanya tidak bisa
     * dipakai menakar apa pun.
     */
    /**
     * Pasang orang yang memegang LEBIH DARI SATU jenis hubungan — keadaan yang kini mustahil.
     *
     * Berbeda dari `cacahArahBertentangan()` yang hanya melihat ATASAN+BAWAHAN pada arah yang
     * sama. Sejak aturan "satu pasang satu relasi", PEER yang menumpang di atas hubungan
     * hierarkis juga pertentangan — dan data yang lahir SEBELUM aturannya ada masih memuatnya.
     * Penjaga penulisan tidak berlaku surut; yang lama hanya terlihat kalau dicari.
     *
     * Dihitung per PASANG tak berarah, bukan per baris: satu pertentangan menghasilkan beberapa
     * baris, dan melaporkan barisnya membuat angkanya tidak bisa dipakai menakar apa pun.
     */
    private function cacahPasanganBerelasiGanda(): int
    {
        /* Dihitung DI DATABASE, bukan dengan menarik seluruh tabel ke PHP.

           Bentuk sebelumnya memuat setiap baris relasi aktif ke memori untuk dikelompokkan
           di sana. Pada pengembangan itu 7.008 baris dan ~1,2 detik — untuk satu angka, di
           dalam ringkasan yang dibaca ulang SETIAP kali halaman ini menyimpan sesuatu.

           Satu hubungan disimpan sebagai dua baris berjenis berbeda, jadi jenisnya
           DINORMALKAN dulu: ATASAN dan BAWAHAN pada satu pasang adalah hubungan yang SAMA.
           Pasangannya juga dinormalkan supaya (A,B) dan (B,A) menjadi satu kunci. */
        $sql = <<<'SQL'
            SELECT COUNT(*) AS n
            FROM (
                SELECT p.a, p.b
                FROM (
                    SELECT
                        CASE WHEN CONCAT(r.Kode_Perusahaan_Subjek, '|', r.Kode_Karyawan_Subjek)
                                  <= CONCAT(r.Kode_Perusahaan_Terkait, '|', r.Kode_Karyawan_Terkait)
                             THEN CONCAT(r.Kode_Perusahaan_Subjek, '|', r.Kode_Karyawan_Subjek)
                             ELSE CONCAT(r.Kode_Perusahaan_Terkait, '|', r.Kode_Karyawan_Terkait)
                        END AS a,
                        CASE WHEN CONCAT(r.Kode_Perusahaan_Subjek, '|', r.Kode_Karyawan_Subjek)
                                  <= CONCAT(r.Kode_Perusahaan_Terkait, '|', r.Kode_Karyawan_Terkait)
                             THEN CONCAT(r.Kode_Perusahaan_Terkait, '|', r.Kode_Karyawan_Terkait)
                             ELSE CONCAT(r.Kode_Perusahaan_Subjek, '|', r.Kode_Karyawan_Subjek)
                        END AS b,
                        CASE WHEN t.Kode_Relasi = 'BAWAHAN' THEN 'ATASAN' ELSE t.Kode_Relasi END AS jenis
                    FROM N_HRIS_Organization_Relationship r
                    JOIN N_HRIS_Organization_Relationship_Type t
                      ON t.Id_Organization_Relationship_Type = r.Id_Organization_Relationship_Type
                    WHERE r.Flag_Aktif = 'Y' AND r.Cancelled_At IS NULL
                ) p
                GROUP BY p.a, p.b
                HAVING COUNT(DISTINCT p.jenis) > 1
            ) q
            SQL;

        return (int) (DB::selectOne($sql)->n ?? 0);
    }

    private function cacahArahBertentangan(): int
    {
        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')
            ->pluck('Id_Organization_Relationship_Type', 'Kode_Relasi')
            ->all();

        if (! isset($tipe['ATASAN'], $tipe['BAWAHAN'])) {
            return 0;
        }

        return DB::table('N_HRIS_Organization_Relationship as a')
            ->join('N_HRIS_Organization_Relationship as b', function ($j) use ($tipe) {
                $j->on('b.Kode_Perusahaan_Subjek', '=', 'a.Kode_Perusahaan_Subjek')
                    ->on('b.Kode_Karyawan_Subjek', '=', 'a.Kode_Karyawan_Subjek')
                    ->on('b.Kode_Perusahaan_Terkait', '=', 'a.Kode_Perusahaan_Terkait')
                    ->on('b.Kode_Karyawan_Terkait', '=', 'a.Kode_Karyawan_Terkait')
                    ->where('b.Id_Organization_Relationship_Type', $tipe['BAWAHAN'])
                    ->where('b.Flag_Aktif', 'Y')
                    ->whereNull('b.Cancelled_At');
            })
            ->where('a.Id_Organization_Relationship_Type', $tipe['ATASAN'])
            ->where('a.Flag_Aktif', 'Y')
            ->whereNull('a.Cancelled_At')
            ->count();
    }

    private function cacahPasanganTimpang(): int
    {
        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')
            ->pluck('Id_Organization_Relationship_Type', 'Kode_Relasi')
            ->all();

        if (! isset($tipe['ATASAN'], $tipe['BAWAHAN'])) {
            return 0;
        }

        return DB::table('N_HRIS_Organization_Relationship as a')
            ->leftJoin('N_HRIS_Organization_Relationship as b', function ($j) use ($tipe) {
                $j->on('b.Kode_Perusahaan_Subjek', '=', 'a.Kode_Perusahaan_Terkait')
                    ->on('b.Kode_Karyawan_Subjek', '=', 'a.Kode_Karyawan_Terkait')
                    ->on('b.Kode_Perusahaan_Terkait', '=', 'a.Kode_Perusahaan_Subjek')
                    ->on('b.Kode_Karyawan_Terkait', '=', 'a.Kode_Karyawan_Subjek')
                    ->where('b.Id_Organization_Relationship_Type', $tipe['BAWAHAN'])
                    ->where('b.Flag_Aktif', 'Y');
            })
            ->where('a.Id_Organization_Relationship_Type', $tipe['ATASAN'])
            ->where('a.Flag_Aktif', 'Y')
            ->whereNull('a.Cancelled_At')
            ->whereNull('b.Id_Organization_Relationship')
            ->count();
    }

    /**
     * Menelusuri relasi, berhalaman.
     *
     * @return array<string, mixed>
     */
    public function daftar(array $saringan = []): array
    {
        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $per = min(100, max(10, (int) ($saringan['per_halaman'] ?? 25)));

        $q = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
            ->leftJoin('Karyawan as s', function ($j) {
                $j->on('s.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Subjek')
                    ->on('s.Kode_Karyawan', '=', 'r.Kode_Karyawan_Subjek');
            })
            ->leftJoin('Karyawan as o', function ($j) {
                $j->on('o.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                    ->on('o.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
            })
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At');

        if (! empty($saringan['tipe_id'])) {
            $q->where('r.Id_Organization_Relationship_Type', (int) $saringan['tipe_id']);
        }

        if (! empty($saringan['cari'])) {
            $kata = trim((string) $saringan['cari']);
            $q->where(function ($w) use ($kata) {
                $w->where('s.Nama', 'like', "%{$kata}%")
                    ->orWhere('o.Nama', 'like', "%{$kata}%")
                    ->orWhere('r.Kode_Karyawan_Subjek', 'like', "{$kata}%")
                    ->orWhere('r.Kode_Karyawan_Terkait', 'like', "{$kata}%");
            });
        }

        if (! empty($saringan['hanya_anomali'])) {
            /* Disaring di SQL, bukan sesudah pengambilan: menyaring di memori pada tabel
               yang kelak berisi ribuan baris hanya menyaring halaman yang sedang dilihat. */
            $q->where(function ($w) {
                $w->whereColumn('r.Kode_Karyawan_Subjek', 'r.Kode_Karyawan_Terkait')
                    ->orWhereNull('o.Kode_Karyawan')
                    ->orWhere('o.Aktif', '<>', 'Y');
            });
        }

        $total = (clone $q)->count();

        /* Urutan WAJIB deterministik. `s.Nama` dan `t.Urutan` penuh seri — ratusan baris
           berbagi nama subjek yang sama — dan SQL Server tidak menjanjikan urutan yang sama
           dua kali untuk baris yang seri. Dengan OFFSET/FETCH akibatnya bukan kosmetik:
           satu baris bisa muncul di halaman 2 lalu hilang dari halaman 3, dan admin yang
           menelusuri per halaman tidak akan pernah melihatnya. Id-nya jadi pemutus seri. */
        $baris = $q->orderBy('s.Nama')
            ->orderBy('t.Urutan')
            ->orderBy('r.Id_Organization_Relationship')
            ->forPage($halaman, $per)
            ->get([
                'r.Id_Organization_Relationship as id',
                't.Kode_Relasi as kode_relasi',
                't.Nama_Relasi as nama_relasi',
                'r.Kode_Perusahaan_Subjek as perusahaan_subjek',
                'r.Kode_Karyawan_Subjek as kode_subjek',
                's.Nama as nama_subjek',
                'r.Kode_Perusahaan_Terkait as perusahaan_terkait',
                'r.Kode_Karyawan_Terkait as kode_terkait',
                'o.Nama as nama_terkait',
                'o.Aktif as terkait_aktif',
                'r.Berlaku_Dari as berlaku_dari',
                'r.Berlaku_Sampai as berlaku_sampai',
                'r.Flag_Utama as utama',
                'r.Sumber_Data as sumber',
                'r.Catatan as catatan',
            ])
            ->map(function ($r) {
                $hariIni = now()->format('Y-m-d');
                $dari = $r->berlaku_dari === null ? null : substr((string) $r->berlaku_dari, 0, 10);
                $sampai = $r->berlaku_sampai === null ? null : substr((string) $r->berlaku_sampai, 0, 10);

                return (array) $r + [
                    'ke_diri_sendiri' => $r->kode_subjek === $r->kode_terkait
                        && $r->perusahaan_subjek === $r->perusahaan_terkait,
                    'terkait_nonaktif' => ($r->terkait_aktif ?? 'T') !== 'Y',

                    /* Tabel ini sengaja menampilkan yang di luar masa berlaku juga — ia
                       tabel pengelolaan, bukan potret struktur hari ini. Tetapi barisnya
                       DIBERI TANDA: tanpa itu, relasi kedaluwarsa terbaca sebagai berlaku,
                       padahal mesin penugasan sudah tidak melihatnya. */
                    'belum_berlaku' => $dari !== null && $dari > $hariIni,
                    'sudah_lewat' => $sampai !== null && $sampai < $hariIni,
                ];
            })
            ->all();

        return [
            'baris' => $baris,
            'total' => $total,
            'halaman' => $halaman,
            'per_halaman' => $per,
        ];
    }

    /**
     * Menambahkan satu relasi BESERTA kebalikannya.
     *
     * @return array<string, mixed>
     */
    public function tambah(array $data, ?int $olehUserId = null): array
    {
        $kodeRelasi = strtoupper(trim((string) ($data['kode_relasi'] ?? '')));

        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', $kodeRelasi)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_Organization_Relationship_Type', 'Kode_Relasi', 'Nama_Relasi']);

        if ($tipe === null) {
            throw new RuntimeException("Jenis relasi '{$kodeRelasi}' tidak dikenal atau tidak aktif.");
        }

        $subjek = $this->pastikanKaryawan($data['perusahaan_subjek'] ?? '', $data['kode_subjek'] ?? '', 'Subjek');
        $terkait = $this->pastikanKaryawan($data['perusahaan_terkait'] ?? '', $data['kode_terkait'] ?? '', 'Pihak terkait');

        if ($subjek->Kode_Perusahaan === $terkait->Kode_Perusahaan
            && $subjek->Kode_Karyawan === $terkait->Kode_Karyawan) {
            /* Ditolak, bukan disimpan lalu ditandai anomali. Orang yang jadi atasan dirinya
               sendiri akan diusulkan menilai dirinya lewat kategori ATASAN di samping SELF,
               dan bobot keduanya dijumlahkan seolah dua orang berbeda. */
            throw new RuntimeException('Seseorang tidak bisa menjadi relasi bagi dirinya sendiri.');
        }

        $mulai = $data['berlaku_dari'] ?? now()->format('Y-m-d');
        $sampai = $data['berlaku_sampai'] ?? null;

        if ($sampai !== null && $sampai < $mulai) {
            throw new RuntimeException('Tanggal berakhir mendahului tanggal mulai.');
        }

        $kebalikan = self::KEBALIKAN[$kodeRelasi] ?? null;

        $tipeKebalikan = $kebalikan === null ? null : DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', $kebalikan)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Organization_Relationship_Type');

        $dibuat = DB::transaction(function () use (
            $tipe, $tipeKebalikan, $subjek, $terkait, $mulai, $sampai, $data, $olehUserId
        ) {
            $n = 0;
            $bentrok = null;

            $hasil = $this->sisipkanSatu(
                (int) $tipe->Id_Organization_Relationship_Type,
                $subjek, $terkait, $mulai, $sampai, $data, $olehUserId, $bentrok,
            );

            /* Satu nama disebut admin, jadi jawabannya penolakan yang menjelaskan — bukan
               "berhasil, 0 baris", yang terbaca seperti tersimpan. */
            if ($hasil === self::SISIP_BERLAWANAN) {
                throw new RuntimeException(sprintf(
                    '%s sudah tercatat sebagai %s bagi %s. Satu pasang orang tidak bisa '
                    .'sekaligus atasan dan bawahan — cabut relasi yang ada dulu.',
                    $terkait->Nama,
                    $bentrok,
                    $subjek->Nama,
                ));
            }

            $n += max(0, $hasil);

            if ($tipeKebalikan !== null) {
                /* Arah kebalikannya ditulis di transaksi yang SAMA. Ditulis terpisah, satu
                   kegagalan meninggalkan pasangan timpang: "siapa bawahan saya" menjawab
                   tidak ada, sementara "siapa atasan saya" menjawab ada. */
                $n += max(0, $this->sisipkanSatu(
                    (int) $tipeKebalikan,
                    $terkait, $subjek, $mulai, $sampai, $data, $olehUserId,
                ));
            }

            return $n;
        });

        return [
            'dibuat' => $dibuat,
            'kode_relasi' => $tipe->Kode_Relasi,
            'kebalikan' => $kebalikan,
        ];
    }

    /**
     * Mencabut satu relasi BESERTA kebalikannya. Dibatalkan, bukan dihapus.
     *
     * @return array<string, mixed>
     */
    public function cabut(int $id, ?string $alasan = null, ?int $olehUserId = null): array
    {
        $r = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
            ->where('r.Id_Organization_Relationship', $id)
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->first([
                'r.Id_Organization_Relationship', 't.Kode_Relasi',
                'r.Kode_Perusahaan_Subjek', 'r.Kode_Karyawan_Subjek',
                'r.Kode_Perusahaan_Terkait', 'r.Kode_Karyawan_Terkait',
            ]);

        if ($r === null) {
            throw new RuntimeException("Relasi {$id} tidak ditemukan atau sudah dicabut.");
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();
        $kebalikan = self::KEBALIKAN[$r->Kode_Relasi] ?? null;

        $dicabut = DB::transaction(function () use ($r, $kebalikan, $alasan, $olehUserId, $sekarang) {
            $ubah = [
                'Flag_Aktif' => 'T',
                'Cancelled_At' => $sekarang,
                'Cancelled_By' => $olehUserId,
                'Updated_At' => $sekarang,
                'Updated_By' => $olehUserId,
            ];

            /* Catatan hanya DITIMPA kalau ada alasan yang ditulis. Sebelumnya ia selalu
               ditulis, jadi mencabut tanpa mengisi alasan — jalur yang paling sering
               dipakai — menghapus catatan asal-usul relasi itu. Yang tersisa cuma baris
               nonaktif tanpa keterangan, persis pada baris yang paling perlu ditelusuri. */
            $alasanBersih = trim((string) ($alasan ?? ''));

            if ($alasanBersih !== '') {
                $ubah['Catatan'] = $alasanBersih;
            }

            $n = DB::table('N_HRIS_Organization_Relationship')
                ->where('Id_Organization_Relationship', $r->Id_Organization_Relationship)
                ->update($ubah);

            if ($kebalikan !== null) {
                $tipeKebalikan = DB::table('N_HRIS_Organization_Relationship_Type')
                    ->where('Kode_Relasi', $kebalikan)
                    ->value('Id_Organization_Relationship_Type');

                // Kebalikannya ikut dicabut. Membiarkannya berdiri sendiri menghasilkan
                // pasangan timpang -- keadaan yang justru sedang kita jaga agar nol.
                $n += DB::table('N_HRIS_Organization_Relationship')
                    ->where('Id_Organization_Relationship_Type', $tipeKebalikan)
                    ->where('Kode_Perusahaan_Subjek', $r->Kode_Perusahaan_Terkait)
                    ->where('Kode_Karyawan_Subjek', $r->Kode_Karyawan_Terkait)
                    ->where('Kode_Perusahaan_Terkait', $r->Kode_Perusahaan_Subjek)
                    ->where('Kode_Karyawan_Terkait', $r->Kode_Karyawan_Subjek)
                    ->where('Flag_Aktif', 'Y')
                    ->update($ubah);
            }

            return $n;
        });

        return ['dicabut' => $dicabut, 'kode_relasi' => $r->Kode_Relasi];
    }

    /**
     * Menambahkan relasi massal: 1 subjek ke banyak pihak terkait.
     *
     * @return array<string, mixed>
     */
    public function tambahMassal(array $data, ?int $olehUserId = null): array
    {
        $kodeRelasi = strtoupper(trim((string) ($data['kode_relasi'] ?? '')));

        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', $kodeRelasi)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_Organization_Relationship_Type', 'Kode_Relasi', 'Nama_Relasi']);

        if ($tipe === null) {
            throw new RuntimeException("Jenis relasi '{$kodeRelasi}' tidak dikenal atau tidak aktif.");
        }

        $subjek = $this->pastikanKaryawan($data['perusahaan_subjek'] ?? '', $data['kode_subjek'] ?? '', 'Subjek');
        $daftarTerkait = $data['daftar_terkait'] ?? [];

        if (! is_array($daftarTerkait) || empty($daftarTerkait)) {
            throw new RuntimeException('Daftar pihak terkait belum dipilih.');
        }

        /* Batasnya DITEGAKKAN, bukan cuma didokumentasikan. BATAS_MASSAL sebelumnya
           tidak pernah dirujuk satu kali pun, jadi satu panggilan bisa membawa 958
           orang — 1.916 baris, dan tidak ada yang menahannya. */
        if (count($daftarTerkait) > self::BATAS_MASSAL) {
            throw new RuntimeException(sprintf(
                'Satu operasi massal dibatasi %d orang, dikirim %d. Tiap relasi ditulis dua arah.',
                self::BATAS_MASSAL,
                count($daftarTerkait),
            ));
        }

        $mulai = $data['berlaku_dari'] ?? now()->format('Y-m-d');
        $sampai = $data['berlaku_sampai'] ?? null;

        if ($sampai !== null && $sampai < $mulai) {
            throw new RuntimeException('Tanggal berakhir mendahului tanggal mulai.');
        }

        $kebalikan = self::KEBALIKAN[$kodeRelasi] ?? null;
        $tipeKebalikan = $kebalikan === null ? null : DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', $kebalikan)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Organization_Relationship_Type');

        $dibuat = 0;
        $dilewati = 0;
        $berlawanan = [];

        DB::transaction(function () use (
            $tipe, $tipeKebalikan, $subjek, $daftarTerkait, $mulai, $sampai, $data, $olehUserId,
            &$dibuat, &$dilewati, &$berlawanan
        ) {
            foreach ($daftarTerkait as $t) {
                $perusahaanTerkait = $t['kode_perusahaan'] ?? $t['perusahaan'] ?? '';
                $kodeTerkait = $t['kode_karyawan'] ?? $t['kode'] ?? '';

                if (trim($perusahaanTerkait) === '' || trim($kodeTerkait) === '') {
                    continue;
                }

                // Jangan pasangkan dengan diri sendiri
                if ($subjek->Kode_Perusahaan === $perusahaanTerkait && $subjek->Kode_Karyawan === $kodeTerkait) {
                    $dilewati++;
                    continue;
                }

                try {
                    $terkait = $this->pastikanKaryawan($perusahaanTerkait, $kodeTerkait, 'Pihak terkait');
                } catch (\Throwable) {
                    $dilewati++;
                    continue;
                }

                $bentrok = null;
                $n = $this->sisipkanSatu(
                    (int) $tipe->Id_Organization_Relationship_Type,
                    $subjek,
                    $terkait,
                    $mulai,
                    $sampai,
                    $data,
                    $olehUserId,
                    $bentrok,
                );

                /* Di sini admin memilih banyak nama sekaligus, jadi satu yang bertentangan
                   tidak boleh membatalkan sisanya — tetapi ia WAJIB disebut dengan namanya.
                   "3 dilewati" tanpa nama tidak bisa ditindaklanjuti siapa pun. */
                if ($n === self::SISIP_BERLAWANAN) {
                    $berlawanan[] = $terkait->Nama.' (sudah '.$bentrok.')';
                    $dilewati++;

                    continue;
                }

                if ($n > 0) {
                    $dibuat++;
                    if ($tipeKebalikan !== null) {
                        $this->sisipkanSatu(
                            (int) $tipeKebalikan,
                            $terkait,
                            $subjek,
                            $mulai,
                            $sampai,
                            $data,
                            $olehUserId,
                        );
                    }

                } else {
                    $dilewati++;
                }
            }
        });

        return [
            'dibuat' => $dibuat,
            'dilewati' => $dilewati,

            /* Namanya dibawa sampai ke layar, dibatasi 10 supaya pesannya tetap terbaca —
               dan jumlah utuhnya tetap disebut, karena daftar yang dipotong tanpa angka
               akan terbaca sebagai daftar lengkap. */
            'berlawanan_arah' => count($berlawanan),
            'berlawanan_contoh' => array_slice($berlawanan, 0, 10),
            'kode_relasi' => $tipe->Kode_Relasi,
            'kebalikan' => $kebalikan,
        ];
    }

    /**
     * Himpunan kandidat karyawan — SATU-SATUNYA tempat saringan ini dirumuskan.
     *
     * Pencarian di layar dan penulisan berbasis saringan WAJIB memakai kueri yang sama.
     * Kalau keduanya dirumuskan terpisah, angka yang dikonfirmasi admin ("958 yang cocok")
     * dan himpunan yang benar-benar ditulis bisa menyimpang tanpa ada yang tahu — dan
     * penyimpangan itu hanya akan terlihat sebagai relasi yang entah dari mana.
     *
     * @param  array<string, mixed>  $saringan
     * @param  array{pt: string, kode: string}|null  $kecualikan  Subjeknya sendiri.
     */
    public function kandidatQuery(array $saringan, ?array $kecualikan = null): \Illuminate\Database\Query\Builder
    {
        $q = DB::table('Karyawan as k')
            ->leftJoin(
                'View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan',
                '=',
                'k.ID_Level_Jabatan',
            )
            ->leftJoin('HRIS_Level as l', 'l.ID_Level', '=', 'v.ID_Level')
            /* Tidak ada tabel `Departemen`, dan `Karyawan` tidak punya `Kode_Departemen`.
               Yang dimaksud "departemen" di layar ini adalah SUB-DIVISI. */
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y');

        $kata = trim((string) ($saringan['q'] ?? ''));
        if ($kata !== '') {
            $q->where(function ($w) use ($kata) {
                $w->where('k.Nama', 'like', "%{$kata}%")
                    ->orWhere('k.Kode_Karyawan', 'like', "%{$kata}%");
            });
        }

        foreach ([
            'divisi' => 'vd.nama_divisi',
            'departemen' => 'vd.nama_sub_divisi',
            'id_level' => 'v.ID_Level',
        ] as $kunci => $kolom) {
            if (empty($saringan[$kunci])) {
                continue;
            }

            $nilai = is_array($saringan[$kunci]) ? $saringan[$kunci] : [$saringan[$kunci]];
            $q->whereIn($kolom, $nilai);
        }

        /* Dirinya sendiri dikeluarkan, bukan ditawarkan lalu ditolak. */
        if ($kecualikan !== null) {
            $q->where(function ($w) use ($kecualikan) {
                $w->where('k.Kode_Perusahaan', '!=', $kecualikan['pt'])
                    ->orWhere('k.Kode_Karyawan', '!=', $kecualikan['kode']);
            });
        }

        return $q;
    }

    /**
     * Menandai kandidat: siapa yang sudah terhubung, dan siapa yang bertentangan.
     *
     * DULU INI DITULIS ULANG DI CONTROLLER, dan rumusannya sudah menyimpang. Controller
     * hanya menganggap ATASAN↔BAWAHAN saling meniadakan, sementara `sisipkanSatu()` sudah
     * lama memakai aturan yang lebih luas: satu pasang orang hanya boleh punya SATU jenis
     * hubungan, jadi PEER pun bertentangan dengan ATASAN.
     *
     * Akibatnya kotak centang terbuka untuk orang yang PASTI ditolak servernya, dan
     * penolakannya baru muncul sesudah dikirim — sebagai angka, tanpa nama. Di basis data
     * pengembangan ada 477 pasangan berelasi ganda, jadi ini bukan kemungkinan teoretis.
     *
     * Sekarang aturannya SATU, di sini, dipakai layar maupun penulisan. Yang boleh berbeda
     * antara keduanya hanyalah waktunya: layar memberi tahu lebih awal, server tetap yang
     * menolak.
     *
     * @param  list<object>  $baris  Hasil `kandidatQuery()`, masing-masing punya
     *                               `kode_perusahaan` dan `kode_karyawan`.
     * @return list<object>  Baris yang sama, ditambah `relasi_terpasang`, `sudah_terhubung`,
     *                       `bentrok_arah`, dan `bentrok_sebab`.
     */
    public function tandaiKandidat(array $baris, string $subjekPt, string $subjekKode, string $kodeDiminta): array
    {
        $kodeDiminta = strtoupper(trim($kodeDiminta));

        if ($baris === [] || trim($subjekPt) === '' || trim($subjekKode) === '') {
            return $baris;
        }

        /* Satu kueri untuk seluruh halaman, bukan satu per baris: relasi satu subjek
           jumlahnya kecil, jadi diambil sekaligus lalu dijodohkan di memori. KEDUA arah
           ikut, karena data bisa timpang — hanya satu sisi yang tercatat — dan membaca
           arah maju saja membuat layar menawarkan pilihan yang pasti ditolak servernya. */
        $rel = DB::table('N_HRIS_Organization_Relationship as r')
            ->join(
                'N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type',
                '=',
                'r.Id_Organization_Relationship_Type',
            )
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->where(function ($w) use ($subjekPt, $subjekKode) {
                $w->where(function ($x) use ($subjekPt, $subjekKode) {
                    $x->where('r.Kode_Perusahaan_Subjek', $subjekPt)
                        ->where('r.Kode_Karyawan_Subjek', $subjekKode);
                })->orWhere(function ($x) use ($subjekPt, $subjekKode) {
                    $x->where('r.Kode_Perusahaan_Terkait', $subjekPt)
                        ->where('r.Kode_Karyawan_Terkait', $subjekKode);
                });
            })
            ->get([
                'r.Kode_Perusahaan_Subjek as spt',
                'r.Kode_Karyawan_Subjek as skode',
                'r.Kode_Perusahaan_Terkait as tpt',
                'r.Kode_Karyawan_Terkait as tkode',
                't.Kode_Relasi as kode_relasi',
                't.Nama_Relasi as nama_relasi',
            ]);

        $terpasang = [];

        foreach ($rel as $r) {
            $maju = $r->spt === $subjekPt && $r->skode === $subjekKode;

            // Kuncinya SELALU orang lainnya, bukan subjeknya.
            $kunci = $maju ? $r->tpt.'|'.$r->tkode : $r->spt.'|'.$r->skode;

            $terpasang[$kunci][] = [
                'kode' => $r->kode_relasi,
                'nama' => $r->nama_relasi,
                'maju' => $maju,
            ];
        }

        /* Himpunan SETARA dari satu penulisan — cermin persis `sisipkanSatu()`:
             menulis (R, S->T)  =>  { (R, S->T), (KEBALIKAN[R], T->S) }
           Apa pun di antara pasangan itu yang berada DI LUAR himpunan ini adalah
           pertentangan. */
        $kebalikan = self::KEBALIKAN[$kodeDiminta] ?? null;

        return array_map(function ($b) use ($terpasang, $kodeDiminta, $kebalikan) {
            $milik = $terpasang[$b->kode_perusahaan.'|'.$b->kode_karyawan] ?? [];

            /* Hanya arah maju yang dijadikan label: "sudah Atasan" dibaca dari sisi subjek,
               dan menampilkan arah terbalik dengan kata yang sama akan terbaca terbalik. */
            $b->relasi_terpasang = array_values(array_map(
                static fn ($m) => $m['nama'] ?: $m['kode'],
                array_filter($milik, static fn ($m) => $m['maju']),
            ));

            $b->sudah_terhubung = false;
            $b->bentrok_arah = false;
            $b->bentrok_sebab = null;

            /* Tanpa jenis relasi yang diminta tidak ada yang bisa dinilai — dan menilai
               "semua bertentangan" pada keadaan itu akan mengunci seluruh daftar. */
            if ($kodeDiminta === '') {
                return $b;
            }

            foreach ($milik as $m) {
                if ($m['maju'] && $m['kode'] === $kodeDiminta) {
                    $b->sudah_terhubung = true;

                    break;
                }
            }

            foreach ($milik as $m) {
                $setara = ($m['maju'] && $m['kode'] === $kodeDiminta)
                    || (! $m['maju'] && $kebalikan !== null && $m['kode'] === $kebalikan);

                if ($setara) {
                    continue;
                }

                $b->bentrok_arah = true;
                $b->bentrok_sebab = $m['nama'] ?: $m['kode'];

                break;
            }

            return $b;
        }, $baris);
    }

    /**
     * Menulis relasi untuk SELURUH yang cocok dengan saringan, bukan untuk daftar nama.
     *
     * KENAPA JALUR TERPISAH
     *
     * "Pilih Semua" pada daftar yang dipangkas hanya memilih yang sedang tampil. Admin yang
     * bermaksud "seluruh divisi ini" mendapat 200 dari 289 dan tidak punya cara mengetahui
     * bedanya. Menaikkan plafon daftarnya tidak menyembuhkan: yang salah bukan angkanya,
     * melainkan bahwa maksud "semua yang cocok" diterjemahkan jadi "yang kebetulan termuat".
     *
     * Di sini maksudnya dikirim apa adanya — saringannya — dan server yang menyelesaikan
     * himpunannya. Jadi tidak ada lagi selisih diam-diam antara yang diniatkan dan yang
     * ditulis.
     *
     * PEER TIDAK LEWAT SINI. Jaringnya kuadratik: satu divisi 289 orang menghasilkan 83.232
     * baris. Batas 25 pada `peerCluster()` bukan kehati-hatian berlebihan, itu satu-satunya
     * hal yang menahannya.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function tambahMassalBerdasarSaringan(array $data, ?int $olehUserId = null): array
    {
        $kodeRelasi = strtoupper(trim((string) ($data['kode_relasi'] ?? '')));

        if ($kodeRelasi === 'PEER') {
            throw new RuntimeException(
                'Kelompok Peer tidak bisa dibentuk dari saringan: jaringnya tumbuh kuadratik. '
                .'Gunakan Peer Mesh Generator, maksimum '.self::BATAS_PEER.' orang.',
            );
        }

        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', $kodeRelasi)
            ->where('Flag_Aktif', 'Y')
            ->first(['Id_Organization_Relationship_Type', 'Kode_Relasi', 'Nama_Relasi']);

        if ($tipe === null) {
            throw new RuntimeException("Jenis relasi '{$kodeRelasi}' tidak dikenal atau tidak aktif.");
        }

        $subjek = $this->pastikanKaryawan($data['perusahaan_subjek'] ?? '', $data['kode_subjek'] ?? '', 'Subjek');
        $saringan = $data['saringan'] ?? [];

        /* Sedikitnya satu saringan WAJIB. Tanpa aturan ini, permintaan yang saringannya
           kosong — karena bug di layar, atau karena admin membersihkannya lalu lupa —
           berarti "seluruh perusahaan", dan itu bukan sesuatu yang boleh terjadi karena
           kelalaian. Kalau memang mau seluruh perusahaan, itu harus diucapkan. */
        $adaSaringan = false;
        foreach (['q', 'divisi', 'departemen', 'id_level'] as $k) {
            if (! empty($saringan[$k])) {
                $adaSaringan = true;
                break;
            }
        }

        if (! $adaSaringan) {
            throw new RuntimeException(
                'Pilih sedikitnya satu saringan (divisi, departemen, level, atau kata pencarian). '
                .'Tanpa saringan, permintaan ini berarti seluruh karyawan aktif.',
            );
        }

        $kandidat = $this->kandidatQuery($saringan, [
            'pt' => $subjek->Kode_Perusahaan,
            'kode' => $subjek->Kode_Karyawan,
        ])->get(['k.Kode_Perusahaan as pt', 'k.Kode_Karyawan as kode'])->all();

        $cocok = count($kandidat);

        if ($cocok === 0) {
            throw new RuntimeException('Tidak ada karyawan yang cocok dengan saringan tersebut.');
        }

        if ($cocok > self::BATAS_SARINGAN) {
            throw new RuntimeException(sprintf(
                'Saringan itu mencakup %d orang, di atas batas %d untuk satu operasi.',
                $cocok,
                self::BATAS_SARINGAN,
            ));
        }

        /* Jumlah yang DILIHAT admin harus sama dengan jumlah yang akan ditulis, dan
           diperiksa SEBELUM apa pun ditulis.

           Antara layar menghitung dan admin menekan, himpunannya bisa bergeser: karyawan
           masuk atau nonaktif, saringannya diubah di tab lain. Kalau bergeser, permintaannya
           ditolak — bukan dijalankan atas himpunan yang tidak pernah ia setujui. */
        $dikonfirmasi = $data['jumlah_dikonfirmasi'] ?? null;

        if ($dikonfirmasi !== null && (int) $dikonfirmasi !== $cocok) {
            throw new RuntimeException(sprintf(
                'Jumlahnya berubah sejak Anda melihatnya: layar menyebut %d, sekarang %d. '
                .'Tidak ada yang ditulis — periksa lagi saringannya.',
                (int) $dikonfirmasi,
                $cocok,
            ));
        }

        $kebalikan = self::KEBALIKAN[$kodeRelasi] ?? null;
        $tipeKebalikan = $kebalikan === null ? null : DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', $kebalikan)
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Organization_Relationship_Type');

        $mulai = $data['berlaku_dari'] ?? now()->format('Y-m-d');
        $sampai = $data['berlaku_sampai'] ?? null;

        if ($sampai !== null && $sampai < $mulai) {
            throw new RuntimeException('Tanggal berakhir mendahului tanggal mulai.');
        }

        /* Yang sudah ada dibaca SEKALI untuk seluruh himpunan, bukan sekali per orang.

           `sisipkanSatu()` memeriksa keberadaan per baris — 2 kueri per orang, 4 dengan
           kebalikannya. Untuk 289 orang itu 1.156 kueri; pada DB dev yang satu kuerinya
           ~165 ms, tiga menit hanya untuk memeriksa. Jalur ini himpunan, bukan baris. */
        $sudahAda = [];
        foreach (DB::table('N_HRIS_Organization_Relationship')
            ->where('Id_Organization_Relationship_Type', (int) $tipe->Id_Organization_Relationship_Type)
            ->where('Kode_Perusahaan_Subjek', $subjek->Kode_Perusahaan)
            ->where('Kode_Karyawan_Subjek', $subjek->Kode_Karyawan)
            ->where('Flag_Aktif', 'Y')
            ->whereNull('Cancelled_At')
            ->get(['Kode_Perusahaan_Terkait as pt', 'Kode_Karyawan_Terkait as kode']) as $r) {
            $sudahAda[$r->pt.'|'.$r->kode] = true;
        }

        /* Pertentangan arah, dibaca sebagai HIMPUNAN — bukan diperiksa per orang.
           Dua bentuknya sama-sama diambil: relasi berlawanan pada arah maju (S->T), dan
           relasi yang diminta pada arah terbalik (T->S). Yang kedua ikut karena data bisa
           timpang, dan memeriksa arah maju saja meloloskan pertentangan yang sudah tercatat
           di baris sebelahnya. */
        /* Sama seperti `sisipkanSatu()`: apa pun hubungan yang sudah ada di antara pasangan itu
           dan BUKAN bagian dari penulisan ini adalah pertentangan. Dikumpulkan sebagai himpunan
           kunci supaya pemeriksaannya tetap satu kueri, bukan satu per orang. */
        $bentrokKunci = [];

        foreach (DB::table('N_HRIS_Organization_Relationship as r')
            ->join(
                'N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type',
                '=',
                'r.Id_Organization_Relationship_Type',
            )
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->where(function ($w) use ($subjek) {
                $w->where(function ($x) use ($subjek) {
                    $x->where('r.Kode_Perusahaan_Subjek', $subjek->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Subjek', $subjek->Kode_Karyawan);
                })->orWhere(function ($x) use ($subjek) {
                    $x->where('r.Kode_Perusahaan_Terkait', $subjek->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Terkait', $subjek->Kode_Karyawan);
                });
            })
            ->get([
                't.Kode_Relasi as kode',
                'r.Kode_Perusahaan_Subjek as spt', 'r.Kode_Karyawan_Subjek as skode',
                'r.Kode_Perusahaan_Terkait as tpt', 'r.Kode_Karyawan_Terkait as tkode',
            ]) as $r) {
            $maju = $r->spt === $subjek->Kode_Perusahaan && $r->skode === $subjek->Kode_Karyawan;
            $lain = $maju ? $r->tpt.'|'.$r->tkode : $r->spt.'|'.$r->skode;

            $setara = ($maju && $r->kode === $kodeRelasi)
                || (! $maju && $kebalikan !== null && $r->kode === $kebalikan);

            if (! $setara) {
                $bentrokKunci[$lain] = true;
            }
        }


        $sudahAdaBalik = [];
        if ($tipeKebalikan !== null) {
            foreach (DB::table('N_HRIS_Organization_Relationship')
                ->where('Id_Organization_Relationship_Type', (int) $tipeKebalikan)
                ->where('Kode_Perusahaan_Terkait', $subjek->Kode_Perusahaan)
                ->where('Kode_Karyawan_Terkait', $subjek->Kode_Karyawan)
                ->where('Flag_Aktif', 'Y')
                ->whereNull('Cancelled_At')
                ->get(['Kode_Perusahaan_Subjek as pt', 'Kode_Karyawan_Subjek as kode']) as $r) {
                $sudahAdaBalik[$r->pt.'|'.$r->kode] = true;
            }
        }

        $dasar = [
            'Berlaku_Dari' => $mulai,
            'Berlaku_Sampai' => $sampai,
            'Flag_Utama' => ($data['utama'] ?? false) ? 'Y' : 'T',
            'Prioritas' => (int) ($data['prioritas'] ?? 1),

            /* Asal-usulnya dibedakan dari MANUAL: relasi yang lahir dari satu saringan
               punya sifat berbeda saat ditelusuri kembali, dan setahun lagi pertanyaan
               "kenapa 289 orang ini punya atasan yang sama" harus bisa dijawab. */
            'Sumber_Data' => $data['sumber'] ?? 'SARINGAN',
            'Referensi_Sumber' => $data['referensi'] ?? json_encode($saringan, JSON_UNESCAPED_UNICODE),
            'Catatan' => $data['catatan'] ?? null,
            'Flag_Aktif' => 'Y',
            'Created_By' => $olehUserId,
        ];

        $baris = [];
        $dilewati = 0;
        $berlawanan = 0;

        foreach ($kandidat as $c) {
            $kunci = $c->pt.'|'.$c->kode;

            if (isset($sudahAda[$kunci])) {
                $dilewati++;

                continue;
            }

            /* Dilewati, dan DIHITUNG TERPISAH dari "sudah terhubung". Keduanya sama-sama
               tidak ditulis, tetapi artinya berlawanan: yang satu berarti tidak ada yang
               perlu dikerjakan, yang lain berarti ada data yang perlu diperiksa orang. */
            if (isset($bentrokKunci[$kunci])) {
                $berlawanan++;

                continue;
            }

            $baris[] = $dasar + [
                'Id_Organization_Relationship_Type' => (int) $tipe->Id_Organization_Relationship_Type,
                'Kode_Perusahaan_Subjek' => $subjek->Kode_Perusahaan,
                'Kode_Karyawan_Subjek' => $subjek->Kode_Karyawan,
                'Kode_Perusahaan_Terkait' => $c->pt,
                'Kode_Karyawan_Terkait' => $c->kode,
            ];

            if ($tipeKebalikan !== null && ! isset($sudahAdaBalik[$kunci])) {
                $baris[] = $dasar + [
                    'Id_Organization_Relationship_Type' => (int) $tipeKebalikan,
                    'Kode_Perusahaan_Subjek' => $c->pt,
                    'Kode_Karyawan_Subjek' => $c->kode,
                    'Kode_Perusahaan_Terkait' => $subjek->Kode_Perusahaan,
                    'Kode_Karyawan_Terkait' => $subjek->Kode_Karyawan,
                ];
            }
        }

        $dibuat = 0;

        if ($baris !== []) {
            DB::transaction(function () use ($baris, &$dibuat) {
                /* 14 kolom. SQL Server berhenti di 2.100 parameter per pernyataan, jadi
                   potongannya 100 (1.400 parameter) — bukan 150 yang pas-pasan. */
                foreach (array_chunk($baris, 100) as $potong) {
                    DB::table('N_HRIS_Organization_Relationship')->insert($potong);
                    $dibuat += count($potong);
                }
            });
        }

        return [
            'cocok' => $cocok,
            'dibuat' => $dibuat,
            'orang_baru' => $cocok - $dilewati - $berlawanan,
            'dilewati' => $dilewati,
            'berlawanan_arah' => $berlawanan,
            'kode_relasi' => $tipe->Kode_Relasi,
            'kebalikan' => $kebalikan,
            'batas' => self::BATAS_SARINGAN,
        ];
    }

    /**
     * Membentuk kelompok Peer (Mesh) antar-anggota tim/kelompok.
     * Semua anggota saling terhubung sebagai PEER satu sama lain.
     *
     * @return array<string, mixed>
     */
    public function peerCluster(array $data, ?int $olehUserId = null): array
    {
        $anggota = $data['anggota'] ?? [];
        if (! is_array($anggota) || count($anggota) < 2) {
            throw new RuntimeException('Minimal 2 orang karyawan diperlukan untuk membentuk kelompok Peer.');
        }

        /* Kuadratik: 25 orang sudah 600 baris, 50 orang 2.450. Batas ini yang paling
           penting ditegakkan, dan justru ia yang paling lama tidak dirujuk. */
        if (count($anggota) > self::BATAS_PEER) {
            throw new RuntimeException(sprintf(
                'Kelompok Peer dibatasi %d orang, dikirim %d. Jaringnya tumbuh kuadratik: %d orang = %d baris.',
                self::BATAS_PEER,
                count($anggota),
                count($anggota),
                count($anggota) * (count($anggota) - 1),
            ));
        }

        $tipePeer = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', 'PEER')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Organization_Relationship_Type');

        if (! $tipePeer) {
            throw new RuntimeException("Tipe relasi 'PEER' tidak ditemukan di master.");
        }

        $mulai = $data['berlaku_dari'] ?? now()->format('Y-m-d');
        $sampai = $data['berlaku_sampai'] ?? null;

        // Aturan yang sama dengan jalur lain. Ia sempat tidak ada di sini saja.
        if ($sampai !== null && $sampai < $mulai) {
            throw new RuntimeException('Tanggal berakhir mendahului tanggal mulai.');
        }

        $totalPasangan = 0;
        $dilewati = 0;
        $sah = 0;
        $berlawanan = [];

        DB::transaction(function () use ($tipePeer, $anggota, $mulai, $sampai, $data, $olehUserId, &$totalPasangan, &$dilewati, &$sah, &$berlawanan) {
            $karyawanList = [];
            foreach ($anggota as $item) {
                $pt = $item['kode_perusahaan'] ?? $item['perusahaan'] ?? '';
                $kode = $item['kode_karyawan'] ?? $item['kode'] ?? '';
                if ($pt && $kode) {
                    try {
                        $karyawanList[] = $this->pastikanKaryawan($pt, $kode, 'Anggota');
                    } catch (\Throwable) {
                        /* Dihitung, bukan dibuang diam-diam: lihat 'total_dilewati'
                           pada balasan. Sebelumnya 20 dikirim dengan 5 yang sah tetap
                           terbaca sebagai "20 anggota terhubung". */
                        $dilewati++;
                    }
                }
            }

            $cacah = count($karyawanList);
            $sah = $cacah;

            for ($i = 0; $i < $cacah; $i++) {
                for ($j = $i + 1; $j < $cacah; $j++) {
                    $a = $karyawanList[$i];
                    $b = $karyawanList[$j];

                    $bentrok = null;
                    $n1 = $this->sisipkanSatu((int) $tipePeer, $a, $b, $mulai, $sampai, $data, $olehUserId, $bentrok);

                    /* Sejak aturan "satu pasang satu relasi", dua orang yang sudah
                       atasan–bawahan TIDAK bisa sekaligus menjadi rekan sejawat. Sebelumnya
                       pasangan seperti itu dilewati tanpa suara, dan admin membaca
                       "kelompok Peer terbentuk" untuk kelompok yang sebagian tidak terbentuk.
                       Yang ditolak sekarang disebut dengan nama. */
                    if ($n1 === self::SISIP_BERLAWANAN) {
                        $berlawanan[] = $a->Nama.' & '.$b->Nama.' (sudah '.$bentrok.')';

                        continue;
                    }

                    $n2 = $this->sisipkanSatu((int) $tipePeer, $b, $a, $mulai, $sampai, $data, $olehUserId);

                    if ($n1 > 0 || $n2 > 0) {
                        $totalPasangan++;
                    }
                }
            }
        });

        return [
            'total_terhubung' => $totalPasangan,
            'total_anggota' => $sah,
            'total_dikirim' => count($anggota),
            'total_dilewati' => $dilewati,
            'berlawanan_arah' => count($berlawanan),
            'berlawanan_contoh' => array_slice($berlawanan, 0, 10),
        ];
    }

    /**
     * Transfer struktur pelaporan: Mengalihkan bawahan dari Atasan Lama ke Atasan Baru.
     *
     * @return array<string, mixed>
     */
    public function transferStruktur(array $data, ?int $olehUserId = null): array
    {
        $lama = $this->pastikanKaryawan(
            $data['perusahaan_lama'] ?? '',
            $data['kode_lama'] ?? '',
            'Atasan lama',
        );

        $baru = $this->pastikanKaryawan(
            $data['perusahaan_baru'] ?? '',
            $data['kode_baru'] ?? '',
            'Atasan baru',
        );

        if ($lama->Kode_Perusahaan === $baru->Kode_Perusahaan && $lama->Kode_Karyawan === $baru->Kode_Karyawan) {
            throw new RuntimeException('Atasan lama dan atasan baru tidak boleh orang yang sama.');
        }

        $daftarBawahan = $data['daftar_bawahan'] ?? [];

        /* Alih tugas menulis dua arah untuk atasan baru, dan mencabut dua arah untuk
           atasan lama: empat baris per bawahan. Batasnya sama dengan operasi massal. */
        if (is_array($daftarBawahan) && count($daftarBawahan) > self::BATAS_MASSAL) {
            throw new RuntimeException(sprintf(
                'Alih tugas dibatasi %d bawahan sekali jalan, dikirim %d.',
                self::BATAS_MASSAL,
                count($daftarBawahan),
            ));
        }

        $cabutRelasiLama = (bool) ($data['cabut_lama'] ?? true);
        $alasan = $data['alasan'] ?? 'Alih tugas struktur / mutasi atasan';
        $mulai = $data['berlaku_dari'] ?? now()->format('Y-m-d');
        $sekarang = FormatTanggalHelper::getCurrentTime();

        $tipeAtasan = (int) DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', 'ATASAN')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Organization_Relationship_Type');

        $tipeBawahan = (int) DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Kode_Relasi', 'BAWAHAN')
            ->where('Flag_Aktif', 'Y')
            ->value('Id_Organization_Relationship_Type');

        /* Tanpa penjaga ini `(int) null` menjadi 0, dan alih tugas akan menulis baris
           bertipe 0 — ditolak FK kalau beruntung, diterima diam-diam kalau tidak. */
        if ($tipeAtasan === 0 || $tipeBawahan === 0) {
            throw new RuntimeException(
                "Jenis relasi 'ATASAN' atau 'BAWAHAN' tidak aktif di master; alih tugas tidak bisa dijalankan.",
            );
        }

        $dialihkan = 0;
        $berlawanan = [];
        $dilewatiTakLengkap = 0;
        $dilewatiAtasanSendiri = 0;
        $dilewatiTakDitemukan = 0;

        DB::transaction(function () use (
            $lama, $baru, $daftarBawahan, $cabutRelasiLama, $alasan, $mulai, $sekarang,
            $tipeAtasan, $tipeBawahan, $olehUserId, &$dialihkan, &$berlawanan,
            &$dilewatiTakLengkap, &$dilewatiAtasanSendiri, &$dilewatiTakDitemukan
        ) {
            foreach ($daftarBawahan as $b) {
                $pt = $b['kode_perusahaan'] ?? $b['perusahaan'] ?? '';
                $kode = $b['kode_karyawan'] ?? $b['kode'] ?? '';

                if (! $pt || ! $kode) {
                    $dilewatiTakLengkap++;

                    continue;
                }

                // Jangan pasangkan jika bawahan adalah atasan baru itu sendiri
                if ($baru->Kode_Perusahaan === $pt && $baru->Kode_Karyawan === $kode) {
                    $dilewatiAtasanSendiri++;

                    continue;
                }

                try {
                    $bawahanObj = $this->pastikanKaryawan($pt, $kode, 'Bawahan');
                } catch (\Throwable) {
                    $dilewatiTakDitemukan++;

                    continue;
                }

                /* 1. Relasi ke atasan BARU ditulis LEBIH DULU, sebelum yang lama dicabut.
                      Urutannya bukan selera: kalau bentrok, jalur ini `continue`, dan
                      mencabut duluan berarti bawahan itu keluar dari transfer TANPA atasan
                      sama sekali — kehilangan yang tidak dilaporkan ke siapa pun karena
                      barisnya dihitung sebagai "berlawanan", bukan sebagai "dicabut". */
                $payload = [
                    'sumber' => 'TRANSFER',
                    'catatan' => $alasan,
                    'utama' => true,
                ];

                /* ARAHNYA: kode relasi menerangkan peran PIHAK TERKAIT terhadap SUBJEK.
                   "baru adalah atasan bawahanObj" ditulis sebagai (bawahanObj, ATASAN, baru),
                   dan cerminnya (baru, BAWAHAN, bawahanObj). Sebelumnya keduanya tertukar,
                   sehingga alih tugas mencatat bawahan sebagai atasan dari atasan barunya —
                   konsisten dengan dirinya sendiri, jadi tidak ada gejala, tetapi terbalik
                   terhadap seluruh pembaca lain (Inspector, bagan, dan mesin penugasan PA). */
                $bentrok = null;
                $n1 = $this->sisipkanSatu(
                    $tipeAtasan, $bawahanObj, $baru, $mulai, null, $payload, $olehUserId, $bentrok,
                );

                if ($n1 === self::SISIP_BERLAWANAN) {
                    $berlawanan[] = $bawahanObj->Nama.' (sudah '.$bentrok.' bagi '.$baru->Nama.')';

                    continue;
                }

                $n2 = $this->sisipkanSatu($tipeBawahan, $baru, $bawahanObj, $mulai, null, $payload, $olehUserId);

                // 2. Baru sesudah penggantinya berdiri, relasi lama dicabut kalau diminta.
                if ($cabutRelasiLama) {
                    $ubah = [
                        'Flag_Aktif' => 'T',
                        'Cancelled_At' => $sekarang,
                        'Cancelled_By' => $olehUserId,
                        'Catatan' => "Dialihkan ke {$baru->Nama}: {$alasan}",
                        'Updated_At' => $sekarang,
                        'Updated_By' => $olehUserId,
                    ];

                    // "lama adalah atasan bawahanObj"
                    DB::table('N_HRIS_Organization_Relationship')
                        ->where('Id_Organization_Relationship_Type', $tipeAtasan)
                        ->where('Kode_Perusahaan_Subjek', $bawahanObj->Kode_Perusahaan)
                        ->where('Kode_Karyawan_Subjek', $bawahanObj->Kode_Karyawan)
                        ->where('Kode_Perusahaan_Terkait', $lama->Kode_Perusahaan)
                        ->where('Kode_Karyawan_Terkait', $lama->Kode_Karyawan)
                        ->where('Flag_Aktif', 'Y')
                        ->update($ubah);

                    // cerminnya: "bawahanObj adalah bawahan lama"
                    DB::table('N_HRIS_Organization_Relationship')
                        ->where('Id_Organization_Relationship_Type', $tipeBawahan)
                        ->where('Kode_Perusahaan_Subjek', $lama->Kode_Perusahaan)
                        ->where('Kode_Karyawan_Subjek', $lama->Kode_Karyawan)
                        ->where('Kode_Perusahaan_Terkait', $bawahanObj->Kode_Perusahaan)
                        ->where('Kode_Karyawan_Terkait', $bawahanObj->Kode_Karyawan)
                        ->where('Flag_Aktif', 'Y')
                        ->update($ubah);
                }

                if ($n1 > 0 || $n2 > 0) {
                    $dialihkan++;
                }
            }
        });

        return [
            'dialihkan' => $dialihkan,

            /* Dipecah per sebab, bukan satu angka gelap. "Atasan baru itu sendiri"
               memang wajar dilewati; dua yang lain adalah data yang perlu diperiksa. */
            'total_dikirim' => is_array($daftarBawahan) ? count($daftarBawahan) : 0,
            'total_dilewati' => $dilewatiTakLengkap + $dilewatiAtasanSendiri + $dilewatiTakDitemukan,
            'dilewati_data_tak_lengkap' => $dilewatiTakLengkap,
            'dilewati_atasan_baru_sendiri' => $dilewatiAtasanSendiri,
            'dilewati_tak_ditemukan' => $dilewatiTakDitemukan,
            'berlawanan_arah' => count($berlawanan),
            'berlawanan_contoh' => array_slice($berlawanan, 0, 10),
            'atasan_lama' => $lama->Nama,
            'atasan_baru' => $baru->Nama,
        ];
    }

    /**
     * Mencabut banyak relasi sekaligus secara berpasangan.
     *
     * @param  array<int>  $ids
     * @return array<string, mixed>
     */
    public function cabutMassal(array $ids, ?string $alasan = null, ?int $olehUserId = null): array
    {
        if (empty($ids)) {
            return ['dicabut' => 0, 'baris_dicabut' => 0, 'total_dikirim' => 0, 'total_dilewati' => 0];
        }

        if (count($ids) > self::BATAS_MASSAL) {
            throw new RuntimeException(sprintf(
                'Pencabutan massal dibatasi %d baris sekali jalan, dikirim %d.',
                self::BATAS_MASSAL,
                count($ids),
            ));
        }

        /* DUA angka, bukan satu, dan itu bukan kelebihan. `cabut()` memulangkan jumlah
           BARIS — dan satu relasi selalu dua baris. Menjumlahkannya lalu menyebutnya
           "relasi yang dicabut" membuat satu baris terpilih dilaporkan sebagai dua. */
        $relasiDicabut = 0;
        $barisDicabut = 0;
        $dilewati = 0;

        foreach ($ids as $id) {
            try {
                $res = $this->cabut((int) $id, $alasan, $olehUserId);
                $relasiDicabut++;
                $barisDicabut += (int) ($res['dicabut'] ?? 1);
            } catch (\Throwable) {
                /* Umumnya "sudah dicabut", dan itu memang bukan kegagalan — sering karena
                   admin memilih KEDUA arah satu pasangan, dan yang kedua sudah ikut tercabut
                   bersama yang pertama. Tetap dihitung: "50 dikirim, 3 dicabut" perlu bisa
                   dibedakan dari "50 dikirim, 50 dicabut". */
                $dilewati++;
            }
        }

        return [
            'dicabut' => $relasiDicabut,
            'baris_dicabut' => $barisDicabut,
            'total_dikirim' => count($ids),
            'total_dilewati' => $dilewati,
        ];
    }

    /**
     * Mendapatkan ekosistem relasi 360° lengkap untuk seorang karyawan (Inspector).
     *
     * @return array<string, mixed>
     */
    public function ekosistemKaryawan(string $perusahaan, string $kode): array
    {
        $k = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->leftJoin('HRIS_Level as l', 'l.ID_Level', '=', 'v.ID_Level')
            /* Departemen datang dari view yang SAMA, kolom `nama_sub_divisi`.
               Sebelumnya ini menggabung tabel `Departemen` lewat `k.Kode_Departemen` —
               tabelnya tidak ada, dan `Karyawan` pun tidak punya kolom itu; yang ada
               `ID_Divisi_Sub_Divisi`. Akibatnya seluruh endpoint ini memulangkan 422
               "Invalid object name 'Departemen'". */
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Kode_Perusahaan', $perusahaan)
            ->where('k.Kode_Karyawan', $kode)
            ->first([
                'k.Kode_Perusahaan as kode_perusahaan',
                'k.Kode_Karyawan as kode_karyawan',
                'k.Nama as nama',
                'k.Aktif as aktif',
                'l.Keterangan as nama_level',
                'vd.nama_divisi as divisi',
                'vd.nama_sub_divisi as departemen',
            ]);

        if ($k === null) {
            throw new RuntimeException("Karyawan '{$kode}' tidak ditemukan.");
        }

        /* Inspector menjawab "siapa atasan/peer/bawahan orang ini SEKARANG", jadi masa
           berlakunya disaring — sama seperti yang dibaca mesin penugasan. Tanpa itu, relasi
           yang sudah lewat tetap digambarkan sebagai berlaku dan admin menyimpulkan
           strukturnya lengkap padahal penugasannya tidak akan terbentuk. */
        $relasiList = $this->berlakuPada(
            DB::table('N_HRIS_Organization_Relationship as r')
                ->join('N_HRIS_Organization_Relationship_Type as t', 't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
                ->leftJoin('Karyawan as o', function ($j) {
                    $j->on('o.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                        ->on('o.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
                })
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vo', 'vo.ID_Level_Jabatan', '=', 'o.ID_Level_Jabatan')
                ->leftJoin('HRIS_Level as lo', 'lo.ID_Level', '=', 'vo.ID_Level')
                ->where('r.Kode_Perusahaan_Subjek', $perusahaan)
                ->where('r.Kode_Karyawan_Subjek', $kode)
                ->where('r.Flag_Aktif', 'Y')
                ->whereNull('r.Cancelled_At'),
        )
            ->get([
                'r.Id_Organization_Relationship as id',
                't.Kode_Relasi as kode_relasi',
                't.Nama_Relasi as nama_relasi',
                'r.Kode_Perusahaan_Terkait as kode_perusahaan',
                'r.Kode_Karyawan_Terkait as kode_karyawan',
                'o.Nama as nama',
                'o.Aktif as aktif',
                'lo.Keterangan as nama_level',
                'r.Berlaku_Dari as berlaku_dari',
                'r.Flag_Utama as utama',
            ]);

        $atasan = [];
        $peers = [];
        $bawahan = [];

        foreach ($relasiList as $r) {
            $item = [
                'id' => (int) $r->id,
                'kode_perusahaan' => $r->kode_perusahaan,
                'kode_karyawan' => $r->kode_karyawan,
                'nama' => $r->nama ?? $r->kode_karyawan,
                'aktif' => ($r->aktif ?? 'T') === 'Y',
                'nama_level' => $r->nama_level ?? '—',
                'utama' => $r->utama === 'Y',
                'berlaku_dari' => $r->berlaku_dari,
            ];

            if ($r->kode_relasi === 'ATASAN') {
                $atasan[] = $item;
            } elseif ($r->kode_relasi === 'PEER') {
                $peers[] = $item;
            } elseif ($r->kode_relasi === 'BAWAHAN') {
                $bawahan[] = $item;
            }
        }

        return [
            'profil' => (array) $k,
            'atasan' => $atasan,
            'peers' => $peers,
            'bawahan' => $bawahan,
        ];
    }

    /**
     * Mengambil daftar bawahan langsung dari seorang atasan.
     *
     * @return array<int, mixed>
     */
    public function daftarBawahanKaryawan(string $perusahaan, string $kode): array
    {
        return $this->berlakuPada(
            DB::table('N_HRIS_Organization_Relationship as r')
                ->join('N_HRIS_Organization_Relationship_Type as t', 't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
                ->join('Karyawan as o', function ($j) {
                    $j->on('o.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')
                        ->on('o.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait');
                })
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as vo', 'vo.ID_Level_Jabatan', '=', 'o.ID_Level_Jabatan')
                ->leftJoin('HRIS_Level as lo', 'lo.ID_Level', '=', 'vo.ID_Level')
                ->where('r.Kode_Perusahaan_Subjek', $perusahaan)
                ->where('r.Kode_Karyawan_Subjek', $kode)
                ->where('t.Kode_Relasi', 'BAWAHAN')
                ->where('r.Flag_Aktif', 'Y')
                ->whereNull('r.Cancelled_At')
                ->where('o.Aktif', 'Y'),
        )
            ->orderBy('o.Nama')
            ->get([
                'r.Id_Organization_Relationship as id',
                'o.Kode_Perusahaan as kode_perusahaan',
                'o.Kode_Karyawan as kode_karyawan',
                'o.Nama as nama',
                'lo.Keterangan as nama_level',
            ])
            ->all();
    }

    /**
     * Membentuk pohon hirarki lengkap satu unit departemen.
     *
     * @return array<string, mixed>
     */
    public function pohonDepartemen(string $departemen): array
    {
        $karyawan = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->leftJoin('HRIS_Level as l', 'l.ID_Level', '=', 'v.ID_Level')
            ->where('vd.nama_sub_divisi', $departemen)
            ->where('k.Aktif', 'Y')
            ->get([
                'k.Kode_Perusahaan as kode_perusahaan',
                'k.Kode_Karyawan as kode_karyawan',
                'k.Nama as nama',
                'l.Keterangan as nama_level',
                'vd.nama_divisi as divisi',
                'vd.nama_sub_divisi as departemen',
            ]);

        if ($karyawan->isEmpty()) {
            throw new RuntimeException("Tidak ada karyawan aktif di departemen '{$departemen}'.");
        }

        $kodeList = $karyawan->pluck('kode_karyawan')->all();

        $relasi = $this->berlakuPada(
            DB::table('N_HRIS_Organization_Relationship as r')
                ->join('N_HRIS_Organization_Relationship_Type as t', 't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
                ->where('t.Kode_Relasi', 'BAWAHAN')
                ->where('r.Flag_Aktif', 'Y')
                ->whereNull('r.Cancelled_At')
                ->whereIn('r.Kode_Karyawan_Subjek', $kodeList)
                ->whereIn('r.Kode_Karyawan_Terkait', $kodeList),
        )
            ->get([
                'r.Kode_Perusahaan_Subjek as spt',
                'r.Kode_Karyawan_Subjek as skode',
                'r.Kode_Perusahaan_Terkait as tpt',
                'r.Kode_Karyawan_Terkait as tkode',
            ]);

        /* DIKUNCI PADA PASANGAN (perusahaan, kode), bukan kode saja. Identitas karyawan di
           skema ini majemuk; memakai kode saja berarti dua orang berkode sama di perusahaan
           berbeda saling menempel — bagannya tetap tergambar rapi, dengan orang yang keliru
           di bawah atasan yang keliru. Pengembangan cuma punya satu perusahaan, jadi ini
           tidak akan pernah menunjukkan gejala di sini. */
        $childrenOf = [];
        $hasAtasan = [];
        foreach ($relasi as $r) {
            $childrenOf[$r->spt.'|'.$r->skode][] = $r->tpt.'|'.$r->tkode;
            $hasAtasan[$r->tpt.'|'.$r->tkode] = true;
        }

        $empMap = [];
        foreach ($karyawan as $emp) {
            $empMap[$emp->kode_perusahaan.'|'.$emp->kode_karyawan] = $emp;
        }

        $roots = [];
        foreach ($karyawan as $emp) {
            if (! isset($hasAtasan[$emp->kode_perusahaan.'|'.$emp->kode_karyawan])) {
                $roots[] = $emp;
            }
        }

        if (empty($roots)) {
            $roots = $karyawan->all();
        }

        $levelScore = function ($lvl) {
            $l = strtoupper((string) $lvl);
            if (str_contains($l, 'DIRECTOR') || str_contains($l, 'GENERAL MANAGER')) return 100;
            if (str_contains($l, 'MANAGER')) return 90;
            if (str_contains($l, 'HEAD') || str_contains($l, 'CHIEF')) return 80;
            if (str_contains($l, 'SUPERVISOR') || str_contains($l, 'SPV')) return 70;
            if (str_contains($l, 'OFFICER') || str_contains($l, 'LEAD')) return 60;
            if (str_contains($l, 'STAFF')) return 50;
            return 10;
        };

        usort($roots, function ($a, $b) use ($levelScore) {
            $sa = $levelScore($a->nama_level);
            $sb = $levelScore($b->nama_level);
            if ($sa !== $sb) return $sb - $sa;
            return strcmp((string) $a->nama, (string) $b->nama);
        });

        $visited = [];
        $buildNode = function ($emp) use (&$buildNode, &$visited, $childrenOf, $empMap) {
            $key = $emp->kode_perusahaan.'|'.$emp->kode_karyawan;
            if (isset($visited[$key])) {
                return null;
            }
            $visited[$key] = true;

            $node = [
                'id' => $key,
                'kode_perusahaan' => $emp->kode_perusahaan,
                'kode_karyawan' => $emp->kode_karyawan,
                'company' => $emp->kode_perusahaan,
                'code' => $emp->kode_karyawan,
                'name' => $emp->nama,
                'title' => $emp->nama_level ?: 'Anggota',
                'department' => $emp->departemen,
                'nik' => $emp->kode_karyawan,
                'avatar' => null,
                'children' => [],
            ];

            $childCodes = $childrenOf[$key] ?? [];
            foreach ($childCodes as $cCode) {
                if (isset($empMap[$cCode])) {
                    $childNode = $buildNode($empMap[$cCode]);
                    if ($childNode !== null) {
                        $node['children'][] = $childNode;
                    }
                }
            }

            return $node;
        };

        $childNodes = [];
        foreach ($roots as $rootEmp) {
            $cNode = $buildNode($rootEmp);
            if ($cNode !== null) {
                $childNodes[] = $cNode;
            }
        }

        $firstPt = $roots[0]->kode_perusahaan ?? '1';
        $deptRoot = [
            'id' => "dept-{$departemen}",
            'kode_perusahaan' => $firstPt,
            'kode_karyawan' => "DEPT-{$departemen}",
            'company' => $firstPt,
            'code' => "DEPT-{$departemen}",
            'name' => "DEPARTEMEN {$departemen}",
            'title' => "Unit Organisasi ({$karyawan->count()} Karyawan)",
            'department' => $departemen,
            'nik' => "DEPT",
            'avatar' => null,
            'is_department_root' => true,
            'children' => $childNodes,
        ];

        return [
            'mode' => 'department',
            'departemen' => $departemen,
            'total_karyawan' => $karyawan->count(),
            'pohon' => $deptRoot,
        ];
    }

    /**
     * Opsi filter organisasi untuk selector multi-level.
     *
     * @return array<string, mixed>
     */
    public function opsiFilter(): array
    {
        /* Diambil dari KARYAWAN AKTIF, bukan dari master divisi.

           Master memuat 20 divisi dan 32 sub-divisi, tetapi hanya 5 dan 10 di antaranya
           dipakai seseorang. Menawarkan sisanya sebagai saringan menghasilkan pilihan yang
           SELALU memulangkan nol baris — dan admin yang memilih "semua divisi" lalu melihat
           hasilnya sedikit akan menyimpulkan pencariannya rusak, bukan bahwa lima belas
           pilihan itu memang kosong. */
        $divisi = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('vd.nama_divisi')
            ->where('vd.nama_divisi', '<>', '')
            ->distinct()
            ->orderBy('vd.nama_divisi')
            ->pluck('vd.nama_divisi')
            ->all();

        /* Sumbernya view divisi, bukan tabel `Departemen` — tabel itu tidak ada. Yang
           dimaksud "departemen" di layar ini adalah SUB-DIVISI. */
        $departemen = DB::table('Karyawan as k')
            ->join('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('vd.nama_sub_divisi')
            ->where('vd.nama_sub_divisi', '<>', '')
            ->distinct()
            ->orderBy('vd.nama_sub_divisi')
            ->pluck('vd.nama_sub_divisi')
            ->all();

        $level = DB::table('HRIS_Level as l')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level', '=', 'l.ID_Level')
            ->join('Karyawan as k', 'k.ID_Level_Jabatan', '=', 'v.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereNotNull('l.Keterangan')
            ->distinct()
            ->orderBy('l.ID_Level')
            ->get(['l.ID_Level as id', 'l.Keterangan as nama'])
            ->all();

        return [
            'divisi' => $divisi,
            'departemen' => $departemen,
            'level' => $level,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    private function pastikanKaryawan(string $perusahaan, string $kode, string $sebutan): object
    {
        $perusahaan = trim($perusahaan);
        $kode = trim($kode);

        if ($perusahaan === '' || $kode === '') {
            throw new RuntimeException("{$sebutan} belum dipilih.");
        }

        $k = DB::table('Karyawan')
            ->where('Kode_Perusahaan', $perusahaan)
            ->where('Kode_Karyawan', $kode)
            ->first(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'Aktif']);

        if ($k === null) {
            throw new RuntimeException("{$sebutan} '{$kode}' tidak ada di data karyawan.");
        }

        if ($k->Aktif !== 'Y') {
            /* Relasi ke orang yang sudah keluar menghasilkan penugasan yang terbentuk dan
               tidak akan pernah diisi. Ditolak di depan, bukan dibiarkan lalu dihitung
               sebagai anomali kemudian. */
            throw new RuntimeException("{$sebutan} '{$k->Nama}' sudah tidak aktif sebagai karyawan.");
        }

        return $k;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    /**
     * Jenis relasi menurut id-nya, dibaca sekali per permintaan.
     *
     * `sisipkanSatu()` butuh KODE jenisnya untuk menilai pertentangan arah, dan ia hanya
     * menerima id. Menanyakannya per baris berarti satu perjalanan ke database untuk tabel
     * berisi tujuh baris.
     */
    private function jenis(int $id): ?object
    {
        if ($this->jenisById === null) {
            $this->jenisById = DB::table('N_HRIS_Organization_Relationship_Type')
                ->get(['Id_Organization_Relationship_Type as id', 'Kode_Relasi as kode', 'Nama_Relasi as nama'])
                ->keyBy('id')
                ->all();
        }

        return $this->jenisById[$id] ?? null;
    }

    /**
     * Menulis satu baris relasi, kalau boleh.
     *
     * @param  string|null  $bentrok  Diisi nama relasi yang menghalangi, bila hasilnya
     *                                SISIP_BERLAWANAN. Pemanggil WAJIB mengatakannya.
     * @return int  SISIP_DIBUAT | SISIP_SUDAH_ADA | SISIP_BERLAWANAN
     */
    private function sisipkanSatu(
        int $tipeId,
        object $subjek,
        object $terkait,
        string $mulai,
        ?string $sampai,
        array $data,
        ?int $olehUserId,
        ?string &$bentrok = null,
    ): int {
        $kode = $this->jenis($tipeId)?->kode;

        /* Kedua arah pasangan ini dibaca dalam SATU kueri — yang maju dan yang terbalik.
           Yang terbalik ikut diperiksa karena data bisa timpang: satu arah ada, kebalikannya
           tidak (impor lama, atau pencabutan yang cuma kena satu sisi). Memeriksa arah maju
           saja akan meloloskan pertentangan yang sudah tercatat di baris sebelahnya. */
        $pasangan = DB::table('N_HRIS_Organization_Relationship as r')
            ->join(
                'N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type',
                '=',
                'r.Id_Organization_Relationship_Type',
            )
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->where(function ($w) use ($subjek, $terkait) {
                $w->where(function ($x) use ($subjek, $terkait) {
                    $x->where('r.Kode_Perusahaan_Subjek', $subjek->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Subjek', $subjek->Kode_Karyawan)
                        ->where('r.Kode_Perusahaan_Terkait', $terkait->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Terkait', $terkait->Kode_Karyawan);
                })->orWhere(function ($x) use ($subjek, $terkait) {
                    $x->where('r.Kode_Perusahaan_Subjek', $terkait->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Subjek', $terkait->Kode_Karyawan)
                        ->where('r.Kode_Perusahaan_Terkait', $subjek->Kode_Perusahaan)
                        ->where('r.Kode_Karyawan_Terkait', $subjek->Kode_Karyawan);
                });
            })
            ->get([
                't.Kode_Relasi as kode',
                't.Nama_Relasi as nama',
                'r.Kode_Perusahaan_Subjek as spt',
                'r.Kode_Karyawan_Subjek as skode',
            ]);

        $maju = fn ($r) => $r->spt === $subjek->Kode_Perusahaan && $r->skode === $subjek->Kode_Karyawan;

        /* "Sudah ada" diperiksa LEBIH DULU. Baris yang persis sama bukan pertentangan, dan
           mendahulukan pemeriksaan pertentangan akan membuat operasi yang dijalankan dua kali
           berubah dari diam menjadi menuduh. */
        foreach ($pasangan as $r) {
            if ($maju($r) && $r->kode === $kode) {
                return self::SISIP_SUDAH_ADA;
            }
        }

        /* Himpunan SETARA: baris yang memang lahir dari penulisan yang sama.
           Apa pun di antara pasangan ini yang berada di luarnya adalah pertentangan. */
        $kebalikan = self::KEBALIKAN[$kode] ?? null;

        foreach ($pasangan as $r) {
            $setara = ($maju($r) && $r->kode === $kode)
                || (! $maju($r) && $kebalikan !== null && $r->kode === $kebalikan);

            if ($setara) {
                continue;
            }

            $bentrok = $r->nama;

            return self::SISIP_BERLAWANAN;
        }

        DB::table('N_HRIS_Organization_Relationship')->insert([
            'Id_Organization_Relationship_Type' => $tipeId,
            'Kode_Perusahaan_Subjek' => $subjek->Kode_Perusahaan,
            'Kode_Karyawan_Subjek' => $subjek->Kode_Karyawan,
            'Kode_Perusahaan_Terkait' => $terkait->Kode_Perusahaan,
            'Kode_Karyawan_Terkait' => $terkait->Kode_Karyawan,
            'Berlaku_Dari' => $mulai,
            'Berlaku_Sampai' => $sampai,
            'Flag_Utama' => ($data['utama'] ?? false) ? 'Y' : 'T',
            'Prioritas' => (int) ($data['prioritas'] ?? 1),

            /* Asal-usul SELALU dicatat. Setahun lagi harus bisa dijawab dari mana satu
               relasi berasal — terutama karena tiga sumber calon di sistem ini saling
               bertentangan, dan "siapa yang benar" hanya bisa ditelusuri lewat ini. */
            'Sumber_Data' => $data['sumber'] ?? 'MANUAL',
            'Referensi_Sumber' => $data['referensi'] ?? null,
            'Catatan' => $data['catatan'] ?? null,
            'Flag_Aktif' => 'Y',
            'Created_By' => $olehUserId,
        ]);

        return self::SISIP_DIBUAT;
    }
}
