<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Kontrol dokumen SOP — register kesehatan legal tiap dokumen yang sedang berlaku.
 *
 * KENAPA ADA
 * `N_SOP_Item_Versi` menyimpan seluruh kolom pengesahan (Disahkan_Oleh /
 * Nomor_Pengesahan / Tanggal_Pengesahan) dan kesehatan render berkasnya, tapi tak
 * ada satu layar pun yang bisa menjawab "SOP mana yang terbit tanpa pengesah" atau
 * "berkas mana yang gagal dirender" — dua pertanyaan pertama saat audit. Service
 * ini yang membukanya.
 *
 * MASA BERLAKU SUDAH TAK ADA DI SINI. Konsep kadaluarsa & review berikutnya
 * dicabut 15 Agu 2026: SOP berlaku sampai ada revisi versi baru, titik. Register
 * ini dulu punya lima ember yang lahir dari dua tanggal itu; yang tersisa dua,
 * dan keduanya menunjuk keadaan yang benar-benar salah, bukan tanggal yang belum
 * diisi.
 *
 * DUA ATURAN YANG MENENTUKAN KEBENARAN ANGKANYA
 *  1. Hanya versi AKTIF yang diaudit (`i.Id_Item_Versi_Aktif = v.Id_SOP_Item_Versi`).
 *     Tanpa ini yang teraudit versi usang dan angka dampaknya salah.
 *  2. Perbandingan tanggal memakai rentang/inequality biasa — tanpa whereDate(),
 *     yang menghasilkan CAST(col AS DATE) non-sargable.
 */
class SopDocumentControlService
{
    public const BUCKET = ['TANPA_PENGESAHAN', 'RENDER_GAGAL'];

    /** Awalan kunci cache ringkasan; lihat kunciRingkasan(). */
    public const CACHE_RINGKASAN_PREFIX = 'sop_mon_dok_ringkas_';

    /**
     * Kunci cache ringkasan. Dipublikasikan supaya pemanggil yang perlu
     * membuangnya (mis. command sop:dokumen-baseline, yang tanpa ini akan
     * membandingkan dua nilai cache yang sama dan melapor "identik" tanpa
     * pernah memeriksa) tak perlu menyusun ulang string yang sama di tempat lain.
     */
    public static function kunciRingkasan(): string
    {
        return self::CACHE_RINGKASAN_PREFIX.'v2';
    }

    /** Hitungan per ember untuk chip filter + kartu triase di Ringkasan. */
    public function ringkasan(): array
    {
        return Cache::store('database')->remember(
            self::kunciRingkasan(),
            600,
            function () {
                $r = $this->baseVersiAktif()->selectRaw(
                    "SUM(CASE WHEN v.Disahkan_Oleh IS NULL OR LTRIM(RTRIM(v.Disahkan_Oleh)) = '' THEN 1 ELSE 0 END) as tanpa_pengesahan,
                     COUNT(*) as total",
                )->first();

                // Render gagal ditanyakan terpisah: join berkas akan menggandakan baris
                // versi yang punya >1 berkas dan merusak seluruh hitungan di atas.
                $renderGagal = $this->baseVersiAktif()
                    ->whereNotNull('v.Id_Master_Konten')
                    ->whereExists(fn ($s) => $this->renderGagalDiLibrary($s))
                    ->count();

                return [
                    'total' => (int) ($r->total ?? 0),
                    'tanpa_pengesahan' => (int) ($r->tanpa_pengesahan ?? 0),
                    'render_gagal' => $renderGagal,
                ];
            },
        );
    }

    /**
     * Register dokumen. Tiap baris membawa **dampak**: berapa karyawan sedang
     * teratestasi ke versi ini — angka itulah yang mengubah "dokumen kedaluwarsa"
     * dari catatan administratif jadi risiko yang terukur.
     *
     * @param  array  $f  bucket, q, prioritas
     * @return array{0:\Illuminate\Support\Collection,1:int}
     */
    public function register(array $f, int $page, int $limit): array
    {
        $q = $this->baseVersiAktif();

        if (! empty($f['prioritas'])) {
            $q->where('p.Prioritas', $f['prioritas']);
        }
        $cari = trim((string) ($f['q'] ?? ''));
        if (mb_strlen($cari) >= 2) {
            $q->where(function ($w) use ($cari) {
                $w->where('p.Nama_Paket', 'LIKE', "%{$cari}%")
                    ->orWhere('i.Judul_Item', 'LIKE', "%{$cari}%")
                    ->orWhere('i.Nomor_SOP_Resmi', 'LIKE', "%{$cari}%");
            });
        }

        $this->terapkanBucket($q, $f['bucket'] ?? null);

        $total = (clone $q)->count();

        /* Urutan lama menaruh yang paling dekat kadaluarsa di atas. Tanpa tanggal
           itu, yang paling perlu dilihat lebih dulu adalah versi tanpa pengesah —
           satu-satunya keadaan di register ini yang membuat dokumen tak sah. */
        $rows = $q->orderByRaw("CASE WHEN v.Disahkan_Oleh IS NULL OR LTRIM(RTRIM(v.Disahkan_Oleh)) = '' THEN 0 ELSE 1 END")
            ->orderBy('p.Nama_Paket')->orderBy('i.Judul_Item')
            ->offset(($page - 1) * $limit)->limit($limit)
            ->get([
                'v.Id_SOP_Item_Versi', 'v.Versi_Ke', 'v.Status',
                'v.Tanggal_Berlaku', 'v.Disahkan_Oleh', 'v.Jabatan_Pengesah', 'v.Nomor_Pengesahan', 'v.Tanggal_Pengesahan',
                // Id_SOP_Dokumen: sumbu pengelompokan halaman Master Dokumen.
                // Tanpa ini, satu dokumen yang dipakai N paket muncul N kali.
                'i.Id_SOP_Paket_Item', 'i.Id_SOP_Dokumen', 'i.Judul_Item', 'i.Tipe_Item', 'i.Nomor_SOP_Resmi', 'i.Flag_Wajib',
                'p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Prioritas',
            ]);

        return [$this->lekatkanDampakDanEmber($rows), $total];
    }

    /* ================= INTERNAL ================= */

    /**
     * Predikat "render berkasnya gagal", DIAMBIL DARI LIBRARY KONTEN.
     *
     * Dulu ini menengok N_SOP_Item_Versi_Berkas — tabel jalur unggah langsung
     * yang sudah pensiun sejak Builder mewajibkan konten dari library. Efeknya
     * penghitung "Render gagal" selalu 0 sekalipun ada render yang benar-benar
     * gagal, karena render yang sesungguhnya terjadi di tabel LMS. Sumbernya
     * kini mengikuti v.Id_Master_Konten, sama seperti yang dibaca reader.
     */
    private function renderGagalDiLibrary($s)
    {
        return $s->select(DB::raw(1))
            ->from('N_LMS_Master_Konten_Berkas as kb')
            ->whereColumn('kb.Id_Master_Konten', 'v.Id_Master_Konten')
            ->whereIn('kb.Status_Render', ['FAILED', 'GAGAL', 'ERROR']);
    }

    /**
     * Paket yang memakai dokumen ini — Fase 5.
     *
     * Menjawab pertanyaan yang selama ini tak bisa dijawab admin di mana pun:
     * "kalau saya merevisi dokumen ini, siapa saja yang terdampak?" Sejak
     * fan-out Fase 3, jawabannya bukan lagi satu paket.
     *
     * SENGAJA TIDAK MENYARING PUBLISHED, berbeda dari register(). Paket DRAFT
     * akan terbit membawa dokumen ini, jadi ia bagian dari jawaban. Statusnya
     * ikut dikembalikan supaya UI bisa membedakannya — menyembunyikannya dengan
     * cara membuangnya dari daftar akan membuat admin merevisi dokumen sambil
     * mengira dampaknya lebih kecil daripada yang sebenarnya.
     */
    public function paketPemakai(int $idDokumen): array
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Id_SOP_Dokumen', $idDokumen)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->orderBy('p.Nama_Paket')
            ->get(['p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Status'])
            ->all();
    }

    /** Riwayat versi dokumen — terbaru dulu, lengkap dengan kolom pengesahan. */
    public function riwayatVersi(int $idDokumen): array
    {
        return DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->orderByDesc('Versi_Ke')
            ->get([
                'Id_SOP_Item_Versi', 'Versi_Ke', 'Status', 'Ringkasan_Perubahan',
                'Tanggal_Berlaku', 'Disahkan_Oleh', 'Jabatan_Pengesah', 'Nomor_Pengesahan', 'Tanggal_Pengesahan',
                /* Empat tanggal proses (DDL-2026-034). Ikut dibaca di KEDUA
                   kueri: yang satu melayani detail, yang lain daftar berpaginasi,
                   dan pemformat barisnya sama — kolom yang hanya ada di salah
                   satunya berakhir sebagai "Undefined property". */
                'Tanggal_Permintaan', 'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa',
                'Tanggal_Review_Berikutnya',
                'Tanggal_Obsolete',
                /* Dipakai layar hanya untuk membedakan pengesah yang dipilih
                   dari master karyawan dengan yang diketik lepas. Kodenya
                   sendiri tak ditampilkan. */
                'Kode_Karyawan_Pengesah',
                'Tanggal_Publish',
                'Created_At',
                'Created_By',
                /* Kembar dengan kueri berpaginasi di bawah — lihat catatan di
                   sana. Penanda tulis-bersamaan yang hanya ada di salah satu
                   kueri berarti separuh layar menyimpan tanpa perlindungan. */
                'Updated_At',
                /* WAJIB ikut: layar menandai versi yang isinya belum dilampirkan.
                   Tanpa kedua kolom ini, penanda itu membaca undefined dan
                   menampilkan "tanpa berkas" pada SETIAP versi — termasuk yang
                   jelas punya berkas. Label yang salah lebih buruk daripada
                   tidak ada label. */
                'Id_Master_Konten', 'Id_Master_Ujian',
                /* Salinan nama ujian (DDL 29) — supaya layar bisa menyebut
                   ujian apa tanpa memanggil HC Learn pada tiap pembacaan. */
                'Nama_Ujian',
                /* WAJIB ikut, sama alasannya dengan dua kolom di atas:
                   formatRiwayatVersiRows() membaca kolom ini untuk menandai
                   tahap proses, dan baris yang tak membawanya melempar
                   "Undefined property" — bukan sekadar kehilangan label. */
                'Tahap_Id',
                'Flag_Aktif',
            ])
            ->all();
    }

    /** Riwayat versi dokumen terpaginasi dari database untuk optimasi performa. */
    public function riwayatVersiPaginated(int $idDokumen, int $page = 1, int $limit = 5): array
    {
        $query = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDokumen);

        $total = $query->count();
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $totalPage = (int) ceil($total / $limit) ?: 1;

        $rows = (clone $query)
            ->orderByDesc('Versi_Ke')
            ->forPage($page, $limit)
            ->get([
                'Id_SOP_Item_Versi', 'Versi_Ke', 'Status', 'Ringkasan_Perubahan',
                'Tanggal_Berlaku', 'Disahkan_Oleh', 'Jabatan_Pengesah', 'Nomor_Pengesahan', 'Tanggal_Pengesahan',
                /* Empat tanggal proses (DDL-2026-034). Ikut dibaca di KEDUA
                   kueri: yang satu melayani detail, yang lain daftar berpaginasi,
                   dan pemformat barisnya sama — kolom yang hanya ada di salah
                   satunya berakhir sebagai "Undefined property". */
                'Tanggal_Permintaan', 'Tanggal_Respon', 'Tanggal_Akhir_Approval', 'Tanggal_Kadaluarsa',
                'Tanggal_Review_Berikutnya',
                'Tanggal_Obsolete',
                'Kode_Karyawan_Pengesah',
                'Tanggal_Publish',
                'Created_At',
                'Created_By',
                /* PENANDA TULIS-BERSAMAAN. Dikirim ke layar, dikembalikan saat
                   menyimpan, lalu dibandingkan ubahVersi(). Tanpa itu dua admin
                   pada satu draft saling menimpa tanpa satu pun tanda — dan
                   wizard versi berumur lima langkah, jadi jendelanya bukan
                   hitungan detik. */
                'Updated_At',
                'Id_Master_Konten', 'Id_Master_Ujian',
                'Nama_Ujian',
                'Tahap_Id',
                'Flag_Aktif',
            ])
            ->all();

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $limit,
            'total_page' => $totalPage,
        ];
    }

    /** Versi AKTIF saja — inti kebenaran register ini. */
    private function baseVersiAktif()
    {
        return DB::table('N_SOP_Item_Versi as v')
            /* Pasangan item<->versi ditentukan HANYA oleh pointer versi aktif.
               Syarat kedua yang dulu ada di sini —
               i.Id_SOP_Paket_Item = v.Id_SOP_Paket_Item — REDUNDAN pada data
               1:1 (kalau item menunjuk versi itu, FK-nya pasti item itu juga)
               sekaligus MERUSAK begitu dokumen dibagi: fan-out Fase 3 memasang
               pointer pada semua item perujuk, tapi FK versi tetap menunjuk
               satu item saja — sehingga item berbagi LENYAP dari register.
               Register inilah yang ditumbuhkan jadi halaman Master Dokumen di
               Fase 5; baris yang hilang di sini berarti dokumen terkendali yang
               tak pernah terlihat.

               Aman dibuang, bukan diganti OR: sop:dokumen-verify menjaga
               invarian "pointer versi aktif tidak menggantung / tidak milik
               dokumen lain" tetap nol pelanggaran. */
            ->join('N_SOP_Paket_Item as i', 'i.Id_Item_Versi_Aktif', '=', 'v.Id_SOP_Item_Versi')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_PUBLISHED);
    }

    private function terapkanBucket($q, ?string $bucket): void
    {
        switch ($bucket) {
            case 'TANPA_PENGESAHAN':
                $q->where(fn ($w) => $w->whereNull('v.Disahkan_Oleh')->orWhere('v.Disahkan_Oleh', ''));
                break;
            case 'RENDER_GAGAL':
                $q->whereNotNull('v.Id_Master_Konten')
                    ->whereExists(fn ($s) => $this->renderGagalDiLibrary($s));
                break;
        }
    }

    /**
     * Lengkapi baris halaman ini dengan dampak (dari rollup) + kesehatan berkas,
     * masing-masing satu query untuk seluruh halaman.
     */
    private function lekatkanDampakDanEmber($rows)
    {
        if ($rows->isEmpty()) {
            return $rows;
        }

        $ids = $rows->pluck('Id_SOP_Item_Versi')->all();

        $dampak = DB::table('N_SOP_Coverage_Rollup')
            ->whereIn('Id_SOP_Item_Versi', $ids)
            ->groupBy('Id_SOP_Item_Versi')
            ->selectRaw('Id_SOP_Item_Versi, SUM(Total_Target) as target, SUM(Jumlah_Dikonfirmasi) as dikonfirmasi')
            ->get()->keyBy('Id_SOP_Item_Versi');

        // Status render per baris, lewat library (v.Id_Master_Konten). Satu konten
        // bisa dipakai banyak versi SOP, jadi pemetaannya versi -> konten -> berkas
        // dan bukan lagi versi -> berkas langsung.
        $berkas = DB::table('N_SOP_Item_Versi as v')
            ->join('N_LMS_Master_Konten_Berkas as kb', 'kb.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->whereIn('v.Id_SOP_Item_Versi', $ids)
            ->get(['v.Id_SOP_Item_Versi', 'kb.Status_Render', 'kb.Total_Halaman', 'kb.Error_Message'])
            ->groupBy('Id_SOP_Item_Versi');

        $enc = fn ($id) => Hashids::connection('custom')->encode($id);

        return $rows->map(function ($r) use ($dampak, $berkas, $enc) {
            $d = $dampak[$r->Id_SOP_Item_Versi] ?? null;
            $b = ($berkas[$r->Id_SOP_Item_Versi] ?? collect())->first();

            $r->Terdampak_Target = (int) ($d->target ?? 0);
            $r->Terdampak_Dikonfirmasi = (int) ($d->dikonfirmasi ?? 0);
            $r->Status_Render = $b->Status_Render ?? null;
            $r->Total_Halaman = $b->Total_Halaman ?? null;
            $r->Error_Message = $b->Error_Message ?? null;

            $ember = [];
            if (empty(trim((string) $r->Disahkan_Oleh))) {
                $ember[] = 'TANPA_PENGESAHAN';
            }
            if (in_array((string) $r->Status_Render, ['FAILED', 'GAGAL', 'ERROR'], true)) {
                $ember[] = 'RENDER_GAGAL';
            }
            $r->Bucket = $ember;

            $r->Id_SOP_Item_Versi = $enc($r->Id_SOP_Item_Versi);
            $r->Id_SOP_Paket = $enc($r->Id_SOP_Paket);
            $r->Id_SOP_Paket_Item = $enc($r->Id_SOP_Paket_Item);
            /* Ikut di-encode seperti id lain — kalau tidak, ia membocorkan id
               DB mentah ke klien DAN endpoint detail (yang men-decode Hashid)
               tak akan bisa memuatnya. NULL dipertahankan NULL: versi yatim
               memang tak punya dokumen, dan meng-encode null menghasilkan
               token palsu yang tampak sah. */
            $r->Id_SOP_Dokumen = $r->Id_SOP_Dokumen !== null ? $enc($r->Id_SOP_Dokumen) : null;
            foreach (['Tanggal_Berlaku', 'Tanggal_Pengesahan'] as $c) {
                $r->$c = $r->$c ? Carbon::parse($r->$c)->format('Y-m-d') : null;
            }

            return $r;
        });
    }
}
