<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopGerbangPaket;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopVersiItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Service BACA sisi karyawan — mengisi kotak-hitam endpoint dummy.
 * Bentuk kembalian PERSIS CONTRACT_API.md (frontend tak berubah).
 *
 * Visibilitas Repository (MVP): semua paket PUBLISHED (global), ditandai
 * "Wajib" bila karyawan punya penugasan aktif. Scope-rule beririsan = Fase-2.
 * Status_Paket dihitung on-the-fly dari Progress (belum ada completion engine).
 */
class SopProgressReadService
{
    public function __construct(private SopCompletionService $completion) {}

    private function enc($id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    /* ================= REPOSITORY ================= */

    /** List paket PUBLISHED. Return [rows, total]. */
    public function repositoryList(string $kode, array $f, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $base = DB::table('N_SOP_Master_Paket as p')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF);

        /*
         * Gerbang visibilitas. Sebelum ada Mode_Distribusi, SEMUA paket published
         * terbaca seluruh karyawan — penjadwalan cuma menambah penanda "wajib",
         * bukan membatasi akses. Sekarang:
         *   PUBLIK   → terbuka untuk semua
         *   TERBATAS → hanya yang punya penugasan aktif & belum dicabut
         * Paket lama di-backfill PUBLIK, jadi tak ada yang tiba-tiba hilang.
         */
        $base->where(function ($q) use ($kode) {
            $q->where('p.Mode_Distribusi', 'PUBLIK')
                ->orWhereNull('p.Mode_Distribusi') // jaga-jaga bila skrip 15 belum dijalankan
                ->orWhereExists(function ($s) use ($kode) {
                    $s->select(DB::raw(1))
                        ->from('N_SOP_Penjadwalan_Detail as d')
                        ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                        ->whereColumn('pj.Id_SOP_Paket', 'p.Id_SOP_Paket')
                        ->where('d.Kode_Karyawan', $kode)
                        ->where('d.Flag_Aktif', SopStatus::AKTIF)
                        ->where('d.Flag_Cabut_Akses', 'T');
                });
        });

        if (! empty($f['q'])) {
            $base->where('p.Nama_Paket', 'LIKE', '%'.$f['q'].'%');
        }
        if (! empty($f['prioritas'])) {
            $base->where('p.Prioritas', $f['prioritas']);
        }
        $total = (clone $base)->count();

        $rows = $base->orderByDesc('p.Id_SOP_Paket')
            ->offset($offset)->limit($limit)
            ->get(['p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Prioritas', 'p.Cover_Path']);

        // paket yang WAJIB bagi karyawan (punya penugasan aktif)
        $wajibIds = $this->paketWajibIds($kode);

        $mapped = $rows->map(function ($p) use ($wajibIds) {
            return [
                'Id_SOP_Paket' => $this->enc($p->Id_SOP_Paket),
                'Nama_Paket' => $p->Nama_Paket,
                'Prioritas' => $p->Prioritas,
                'Cover_Url' => null, // TODO: signed URL dari Cover_Path bila cover diunggah
                'Is_Wajib' => in_array((int) $p->Id_SOP_Paket, $wajibIds, true),
                'Versi_Badge' => null,
            ];
        })->values();

        return [$mapped, $total];
    }

    /** Id paket (int) yang punya penugasan aktif utk karyawan. */
    private function paketWajibIds(string $kode): array
    {
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where(fn ($w) => SopGerbangPaket::pasang($w, 'p', 'pj', 'd'))
            ->pluck('pj.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();
    }

    /* ================= DETAIL ================= */

    /**
     * Detail bundle + silabus + status per item utk karyawan.
     *
     * @param  bool  $bypassGate  Lewati gerbang visibilitas. HANYA untuk permukaan admin
     *                            (monitoring), supaya admin bisa memeriksa paket TERBATAS/ARCHIVED yang
     *                            masih tertugas ke seseorang. Default false menjaga seluruh pemanggil
     *                            karyawan tetap identik — jangan pernah menyalakannya di route learner.
     */
    public function detailBundle(string $kode, int $idPaket, bool $bypassGate = false): ?array
    {
        $paket = DB::table('N_SOP_Master_Paket as p')
            ->leftJoin('Karyawan as ow', 'ow.Kode_Karyawan', '=', 'p.Kode_Karyawan_Owner')
            ->where('p.Id_SOP_Paket', $idPaket)
            ->first(['p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Prioritas', 'p.Mode_Urutan_Item',
                'p.Flag_Absensi', 'p.Cover_Path', 'p.Cover_Position', 'p.Deskripsi', 'p.Tujuan_Ruang_Lingkup', 'p.Estimasi_Waktu_Baca_Menit',
                'p.Kode_Paket', 'p.Kode_Karyawan_Owner', 'p.Tanggal_Publish', 'p.Updated_At', 'p.Status',
                'p.Mode_Distribusi', 'p.Flag_Aktif',
                'ow.Nama as Owner_Nama']);

        if (! $paket) {
            return null;
        }

        // detail penugasan karyawan utk paket ini (kalau ada) — dipakai gerbang
        // akses di bawah, jadi harus dihitung lebih dulu.
        $detailId = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->value('d.Id_SOP_Penjadwalan_Detail');

        /*
         * Gerbang akses. Sebelumnya method ini TIDAK memeriksa apa pun: siapa saja
         * yang tahu hashid-nya bisa membuka paket mana pun, termasuk yang masih
         * DRAFT/ARCHIVED. Penyaringan di repositoryList tak ada gunanya kalau
         * halaman detailnya masih bisa dibuka langsung lewat URL.
         */
        if (! $bypassGate && ! $this->paketBolehDiakses($kode, $idPaket)) {
            return null;
        }

        // Callback dapat datang melalui instance aplikasi lain. Bila hasil final
        // sudah tercatat tetapi status item tertinggal, pulihkan sebelum dibaca.
        if ($detailId) {
            $this->completion->reconcileFinalQuizAttempts((int) $detailId);
        }

        $releaseSnapshot = ! $bypassGate ? app(SopPaketVersionService::class)->releasedSnapshot($idPaket) : null;

        // Section release resmi. Jika snapshot v2 belum ada (paket lama), fallback ke tabel aktif.
        if ($releaseSnapshot) {
            $sections = collect($releaseSnapshot['sections'])
                ->map(fn ($s) => [
                    'Id_SOP_Section' => $this->enc($s['Id_SOP_Section']),
                    'Nama_Section' => $s['Nama_Section'],
                    'Deskripsi' => $s['Deskripsi'],
                    'Urutan' => (int) $s['Urutan'],
                ])->values();
        } else {
            $sections = DB::table('N_SOP_Paket_Section')
                ->where('Id_SOP_Paket', $idPaket)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->orderBy('Urutan')
                ->get(['Id_SOP_Section', 'Nama_Section', 'Deskripsi', 'Urutan'])
                ->map(fn ($s) => [
                    'Id_SOP_Section' => $this->enc($s->Id_SOP_Section),
                    'Nama_Section' => $s->Nama_Section,
                    'Deskripsi' => $s->Deskripsi,
                    'Urutan' => (int) $s->Urutan,
                ])->values();
        }
        // status progress per (item × versi) — hindari tabrakan bila item punya >1 versi.
        // Simpan seluruh baris progres agar bisa tampilkan skor/durasi/timestamp di detail.
        $statusMap = [];
        $fallbackItem = []; // progres per item, cadangan saat nomor versi bergeser
        if ($detailId) {
            foreach (DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->get(['Id_SOP_Paket_Item', 'Id_SOP_Item_Versi', 'Status', 'Persen_Selesai', 'Waktu_Buka',
                    'Waktu_Konfirmasi', 'Skor_Quiz', 'Status_Lulus', 'Jumlah_Percobaan', 'Durasi_Aktif_Detik',
                    'Halaman_Terakhir', 'Id_Ujian_Token_Hasil']) as $pr) {
                $statusMap[$pr->Id_SOP_Paket_Item.':'.($pr->Id_SOP_Item_Versi ?? 'null')] = $pr;

                /*
                 * Cadangan per-item, dipakai bila kunci ber-versi tak ketemu.
                 *
                 * Progres ditulis pada versi AKTIF saat dikerjakan, sedangkan daftar
                 * item di bawah dibaca dari SNAPSHOT rilis paket. Selama ada revisi
                 * yang versinya sudah terbit tapi paketnya belum di-Publish ulang,
                 * kedua nomor itu sah berbeda — dan tanpa cadangan ini penyelesaian
                 * yang sah tampil sebagai "Belum". Terbukti pada paket 23 item 88
                 * (snapshot v83, live v86): item dikonfirmasi tetapi halaman detail
                 * menampilkannya belum dikerjakan, sehingga persen tak pernah 100.
                 *
                 * Baris berstatus selesai diprioritaskan supaya penyelesaian tak
                 * tertimpa baris versi lain yang masih BELUM.
                 */
                $item = (int) $pr->Id_SOP_Paket_Item;
                $sudah = in_array($pr->Status, [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI], true);
                if ($sudah || ! isset($fallbackItem[$item])) {
                    $fallbackItem[$item] = $pr;
                }
            }
        }

        /*
         * Sesi ujian yang SUDAH dicetak untuk karyawan ini. Dipakai menurunkan
         * state "menunggu hasil": peserta sudah berangkat ke HC Learn, tapi
         * callback hasilnya belum tiba. Tanpa state ini kartunya tetap "Belum"
         * dan peserta mengira ujiannya tidak terekam.
         */
        $sesiKuis = [];
        if ($detailId) {
            foreach (DB::table('N_SOP_Penjadwalan_Integrasi')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->whereNotNull('Id_Ujian_Token')
                ->get(['Id_SOP_Paket_Item', 'Id_SOP_Item_Versi', 'Id_Ujian_Token']) as $ig) {
                $sesiKuis[$ig->Id_SOP_Paket_Item.':'.($ig->Id_SOP_Item_Versi ?? 'null')] = $ig;
            }
        }

        // Item release resmi. Builder boleh punya draft, user tetap membaca snapshot paket terakhir.
        if ($releaseSnapshot) {
            /* Mode_Akses dari tabel hidup, bukan dari snapshot. Gerbang unduh
               (apiVersiKonten) membaca N_SOP_Paket_Item; kalau layar membaca
               snapshot, keduanya bisa berselisih — tombol unduh tampil lalu
               ditolak, atau disembunyikan padahal boleh. Selisih itu nyata
               setelah DML-2026-165 mengubah izin FORM tanpa rilis ulang paket. */
            $modeAksesHidup = DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket', $idPaket)
                ->pluck('Mode_Akses', 'Id_SOP_Paket_Item');

            $items = collect($releaseSnapshot['items'])
                ->map(fn ($it) => (object) [
                    'Id_SOP_Paket_Item' => (int) $it['Id_SOP_Paket_Item'],
                    'Id_SOP_Section' => ! empty($it['Id_SOP_Section']) ? (int) $it['Id_SOP_Section'] : null,
                    'Tipe_Item' => $it['Tipe_Item'] ?? null,
                    'Judul_Item' => $it['Judul_Item'] ?? null,
                    'Deskripsi_Item' => $it['Deskripsi_Item'] ?? null,
                    'Nomor_SOP_Resmi' => $it['Nomor_SOP_Resmi'] ?? null,
                    'Konten_Teks' => $it['Konten_Teks'] ?? null,
                    'URL_Tautan' => $it['URL_Tautan'] ?? null,
                    'Label_Tautan' => $it['Label_Tautan'] ?? null,
                    'Mode_Akses' => $modeAksesHidup[(int) $it['Id_SOP_Paket_Item']] ?? ($it['Mode_Akses'] ?? null),
                    'Jenis_Quiz' => $it['Jenis_Quiz'] ?? null,
                    'Nilai_Minimum' => $it['Nilai_Minimum'] ?? null,
                    'Flag_Wajib' => ! empty($it['Wajib']) ? SopStatus::AKTIF : SopStatus::INAKTIF,
                    'Flag_Highlight' => $it['Flag_Highlight'] ?? SopStatus::INAKTIF,
                    'Id_Item_Versi_Aktif' => $it['Id_SOP_Item_Versi'] ?? null,
                    'Versi_Ke' => $it['Versi_Item'] ?? null,
                    'Konten_Nama' => $it['Konten_Nama'] ?? null,
                    'Konten_Tipe' => $it['Konten_Tipe'] ?? null,
                    'Ringkasan_Perubahan' => $it['Ringkasan_Perubahan'] ?? null,
                    'Tanggal_Berlaku' => $it['Tanggal_Berlaku'] ?? null,
                    'Disahkan_Oleh' => $it['Disahkan_Oleh'] ?? null,
                    'Jabatan_Pengesah' => $it['Jabatan_Pengesah'] ?? null,
                    'Tanggal_Pengesahan' => $it['Tanggal_Pengesahan'] ?? null,
                    'Nomor_Pengesahan' => $it['Nomor_Pengesahan'] ?? null,
                    'Kode_Karyawan_Publisher' => $it['Kode_Karyawan_Publisher'] ?? null,
                    'Publisher_Nama' => $it['Publisher_Nama'] ?? null,
                ]);
        } else {
            $items = DB::table('N_SOP_Paket_Item as i')
                ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
                ->leftJoin('N_LMS_Master_Konten as kn', 'kn.Id_Master_Konten', '=', 'v.Id_Master_Konten')
                ->leftJoin('Karyawan as pub', 'pub.Kode_Karyawan', '=', 'v.Kode_Karyawan_Publisher')
                ->where('i.Id_SOP_Paket', $idPaket)
                ->where('i.Flag_Aktif', SopStatus::AKTIF)
                ->orderBy('i.Urutan')
                ->get(['i.Id_SOP_Paket_Item', 'i.Id_SOP_Section', 'i.Tipe_Item', 'i.Judul_Item', 'i.Deskripsi_Item',
                    'i.Nomor_SOP_Resmi', 'i.Konten_Teks', 'i.URL_Tautan', 'i.Label_Tautan', 'i.Mode_Akses',
                    'i.Jenis_Quiz', 'i.Nilai_Minimum', 'i.Flag_Wajib', 'i.Flag_Highlight', 'i.Id_Item_Versi_Aktif', 'v.Versi_Ke',
                    'kn.Judul_Konten as Konten_Nama', 'kn.Tipe_Konten as Konten_Tipe',
                    'v.Ringkasan_Perubahan', 'v.Tanggal_Berlaku',
                    'v.Disahkan_Oleh', 'v.Jabatan_Pengesah', 'v.Tanggal_Pengesahan', 'v.Nomor_Pengesahan',
                    'v.Kode_Karyawan_Publisher', 'pub.Nama as Publisher_Nama']);
        }

        /* Versi ikut dokumen, struktur tetap dari snapshot. Dipanggil untuk
           KEDUA jalur: jalur langsung sudah membaca versi hidup, dan menyaring
           di sini akan membuat dua jalur berbeda perlakuan tanpa alasan. */
        $items = $this->selaraskanVersiHidup($items);
        /*
         * Jenis berkas asli per versi aktif.
         *
         * Kolom yang selama ini turun ke browser tak bisa menjawabnya: Konten_Tipe
         * bernilai 'document' untuk PDF maupun .xlsx, dan Konten_Nama adalah
         * Judul_Konten — judul, tanpa ekstensi. Padahal kartu item harus tahu
         * SEBELUM tombolnya ditekan apakah berkas ini bisa ditampilkan.
         *
         * Ditarik terpisah, bukan sebagai join, karena item bisa datang dari
         * snapshot rilis (JSON) yang tak menyimpan mime sama sekali — satu query
         * ini melayani kedua jalur dengan hasil yang sama.
         */
        $berkasByVersi = $this->berkasVersi($items->pluck('Id_Item_Versi_Aktif'));

        /* Form kosong item FORM — satu kueri untuk seluruh paket. */
        $lampiranByVersi = app(SopVersiLampiranService::class)->untukVersi($items->pluck('Id_Item_Versi_Aktif'));

        /*
         * Jumlah versi published/obsolete per item — dasar flag "ada riwayat",
         * yaitu yang menentukan apakah karyawan ditawari melihat versi terdahulu
         * sama sekali.
         *
         * Dihitung pada DUA sumbu. Sejak versi dimiliki dokumen, barisnya
         * ber-Id_SOP_Paket_Item NULL, sehingga menghitung lewat FK itu saja
         * menjawab "1" untuk dokumen yang sudah enam versi — dan riwayatnya
         * tak pernah ditawarkan.
         */
        $itemIds = $items->pluck('Id_SOP_Paket_Item')->filter()->values();
        $dokByItem = $itemIds->isEmpty()
            ? collect()
            : DB::table('N_SOP_Paket_Item')
                ->whereIn('Id_SOP_Paket_Item', $itemIds)
                ->whereNotNull('Id_SOP_Dokumen')
                ->pluck('Id_SOP_Dokumen', 'Id_SOP_Paket_Item');

        $hitungVersi = fn (string $kolom, $nilai) => $nilai->isEmpty()
            ? collect()
            : DB::table('N_SOP_Item_Versi')
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->whereIn('Status', [SopStatus::PAKET_PUBLISHED, 'OBSOLETE'])
                ->whereIn($kolom, $nilai->all())
                ->groupBy($kolom)
                ->selectRaw($kolom.', COUNT(*) as c')
                ->pluck('c', $kolom);

        $verCounts = $hitungVersi('Id_SOP_Paket_Item', $itemIds);
        $verCountsDok = $hitungVersi('Id_SOP_Dokumen', $dokByItem->values());

        $selesaiStatuses = [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI];
        $sekuensial = $paket->Mode_Urutan_Item === 'SEKUENSIAL';
        $adaWajibBelumSelesai = false; // untuk gerbang sekuensial
        $totalWajib = 0;
        $selesaiWajib = 0;

        $itemsOut = [];
        foreach ($items as $it) {
            $verId = $it->Id_Item_Versi_Aktif ? (int) $it->Id_Item_Versi_Aktif : null;
            $berkas = $verId ? ($berkasByVersi[$verId] ?? null) : null;
            /*
             * STATUS DIBACA DARI VERSI YANG BERLAKU, TITIK.
             *
             * Sebelum ini baris progres versi LAIN dipakai sebagai cadangan
             * untuk menentukan status — dan cadangan itulah yang membuat revisi
             * mayor tak terasa apa pun. Begitu versi baru terbit, karyawan
             * belum punya baris untuk versi itu, sehingga cadangan versi lama
             * yang berstatus SELESAI terbaca sebagai "sudah selesai": persennya
             * bertahan di 100, item wajibnya terhitung tuntas, dan
             * `Perlu_Baca_Ulang` justru menjadi FALSE karena syaratnya
             * "belum selesai". Satu baris cadangan mematikan tiga penanda
             * sekaligus.
             *
             * Rekonsiliasi memang menyemai baris untuk versi baru dan
             * membereskan semuanya — tetapi ia berjalan di pekerja latar, dan
             * selama antreannya belum tersentuh layar berbohong kepada orang
             * yang sedang membacanya. Yang ditampilkan sekarang diturunkan
             * langsung dari keadaan, bukan menunggu ada yang menuliskannya.
             *
             * Revisi MINOR tidak ikut terdampak: bawaPenyelesaian() menyalin
             * baris selesai ke versi baru, jadi baris versi aktifnya memang ada
             * dan tetap terbaca selesai.
             *
             * Diverifikasi 10 Sep 2026 sebelum diubah: NOL baris N_SOP_Progress
             * yang Id_SOP_Item_Versi-nya NULL, sehingga tak ada penyelesaian
             * lama yang ikut terbuka kembali oleh pengetatan ini.
             */
            $progAktif = $statusMap[$it->Id_SOP_Paket_Item.':'.($verId ?? 'null')] ?? null;

            /* Baris dari versi lain — HANYA untuk menjawab "pernah tuntas
               sebelumnya", tidak pernah untuk menentukan status sekarang. */
            $progLain = $fallbackItem[(int) $it->Id_SOP_Paket_Item] ?? null;

            $prog = $progAktif;
            $status = $progAktif->Status ?? SopStatus::ITEM_BELUM;
            $wajib = $it->Flag_Wajib === SopStatus::AKTIF; // 'Y'
            $isSelesai = in_array($status, $selesaiStatuses, true);

            /*
             * Pernah dituntaskan pada versi LAIN dari item yang sama.
             *
             * $fallbackItem memprioritaskan baris berstatus selesai, jadi cukup
             * diperiksa di sana: bila item ini "Belum" pada versi aktif tetapi
             * cadangannya selesai, artinya karyawan sudah menuntaskan revisi
             * sebelumnya dan yang diminta sekarang adalah BACA ULANG — bukan
             * langkah baru yang belum pernah disentuh.
             */
            $bacaUlang = ! $isSelesai
                && $progLain
                && in_array($progLain->Status, $selesaiStatuses, true);

            /*
             * Terkunci: mode sekuensial & ada item wajib SEBELUMNYA yang belum
             * selesai — TAPI tak pernah untuk item yang sudah selesai sendiri.
             *
             * Gerbang urutan memaksa orang mengerjakan berurutan. Item yang SUDAH
             * dituntaskan tak punya apa pun untuk digerbangi; menguncinya hanya
             * menyembunyikan hasil kerja yang sah di balik ajakan mengerjakan
             * sesuatu yang sudah dikerjakan. Terlihat begitu satu dokumen di
             * tengah paket direvisi: statusnya balik ke BELUM, lalu SELURUH item
             * sesudahnya — yang sudah rampung — ikut terkunci lagi, lengkap
             * dengan lencana "Selesai" di sebelah gembok yang membantahnya.
             *
             * SopSequenceGate — pintu PENEGAKANNYA — memang tak pernah mengunci
             * kasus ini: ia membaca progres per item, jadi penyelesaian di versi
             * mana pun terhitung. Jadi tampilan inilah yang selama ini lebih
             * ketat daripada aturan yang sebenarnya berlaku.
             */
            $terkunci = $sekuensial && $adaWajibBelumSelesai && ! $isSelesai;

            if ($wajib) {
                $totalWajib++;
                if ($isSelesai) {
                    $selesaiWajib++;
                }
                if (! $isSelesai) {
                    $adaWajibBelumSelesai = true;
                }
            }

            $itemsOut[] = [
                'Id_SOP_Paket_Item' => $this->enc($it->Id_SOP_Paket_Item),
                'Id_SOP_Section' => $it->Id_SOP_Section ? $this->enc($it->Id_SOP_Section) : null,
                'Tipe_Item' => $it->Tipe_Item,
                'Judul_Item' => $it->Judul_Item,
                'Deskripsi_Item' => $it->Deskripsi_Item,
                'Nomor_SOP_Resmi' => $it->Nomor_SOP_Resmi,
                'Konten_Teks' => $it->Konten_Teks,     // PARAGRAF (HTML tersanitasi)
                'URL_Tautan' => $it->URL_Tautan,
                'Label_Tautan' => $it->Label_Tautan,
                'Mode_Akses' => $it->Mode_Akses,
                'Jenis_Quiz' => $it->Jenis_Quiz,
                'Nilai_Minimum' => $it->Nilai_Minimum !== null ? (float) $it->Nilai_Minimum : null,
                'Status' => $status,
                'Wajib' => $wajib,
                'Terkunci' => $terkunci,
                /* Dibaca ulang, bukan dikerjakan pertama kali. Dipakai kartu item
                   untuk menyebut apa yang sebenarnya diminta — "Lanjut dari sini"
                   pada dokumen yang sudah pernah dituntaskan salah menceritakan
                   keadaan. */
                'Perlu_Baca_Ulang' => $bacaUlang,
                /* !== null, BUKAN cek falsy — lihat catatan yang sama di
                   SopPaketVersionService. Di sini akibatnya sampai ke layar
                   karyawan: versi 00 tampil sebagai tanpa versi. */
                'Versi' => $it->Versi_Ke !== null ? (int) $it->Versi_Ke : null,
                'Id_Item_Versi_Aktif' => $verId ? $this->enc($verId) : null,
                'Konten_Nama' => $it->Konten_Nama,
                'Konten_Tipe' => $it->Konten_Tipe,
                // Jenis berkas sebenarnya — dasar tombol "Lihat" pada item FORM.
                'Konten_Mime' => $berkas->Type_File ?? null,
                'Konten_Ext' => SopAksesBerkas::ekstensi($berkas->Ext ?? null, $berkas->File_Path ?? null),
                'Ukuran_File' => isset($berkas->Ukuran_Byte) ? (int) $berkas->Ukuran_Byte : null,
                'Bisa_Pratinjau' => SopAksesBerkas::bisaPratinjau(
                    $it->Tipe_Item,
                    $berkas->Type_File ?? null,
                    $berkas->File_Path ?? null,
                ),
                /* Aturan yang sama dengan gerbang apiVersiKonten, supaya layar
                   tak menebak dari Mode_Akses saja: contoh pengisian FORM
                   non-PDF selalu boleh diunduh apa pun Mode_Akses-nya. */
                'Boleh_Unduh' => SopAksesBerkas::bolehUnduh(
                    $it->Mode_Akses,
                    $it->Tipe_Item,
                    $berkas->Type_File ?? null,
                    $berkas->File_Path ?? null,
                ),
                /* Form kosong untuk diisi karyawan; berkas di atas adalah
                   contoh pengisiannya. Hanya dari versi yang berlaku. */
                'Lampiran' => $it->Tipe_Item === 'FORM'
                    ? collect($lampiranByVersi[(int) $verId] ?? [])->map(fn ($l) => [
                        'Id_Lampiran' => $this->enc($l->Id_Lampiran),
                        'Nama' => $l->Judul,
                        'Ext' => SopAksesBerkas::ekstensi($l->Ext ?? null, $l->File_Path ?? null),
                        'Ukuran' => $l->Ukuran_Byte !== null ? (int) $l->Ukuran_Byte : null,
                    ])->all()
                    : [],
                'Ada_Riwayat' => max(
                    (int) ($verCounts[$it->Id_SOP_Paket_Item] ?? 0),
                    (int) ($verCountsDok[$dokByItem[$it->Id_SOP_Paket_Item] ?? ''] ?? 0),
                ) > 1,
                'Flag_Highlight' => $it->Flag_Highlight,
                // progres per item (bila ditugaskan)
                'Persen_Item' => $prog && $prog->Persen_Selesai !== null ? (float) $prog->Persen_Selesai : null,
                'Skor_Quiz' => $prog && $prog->Skor_Quiz !== null ? (float) $prog->Skor_Quiz : null,
                'Status_Lulus' => $prog->Status_Lulus ?? null,
                'Jumlah_Percobaan' => $prog ? (int) $prog->Jumlah_Percobaan : 0,
                // Sesi ujian sudah dicetak tapi hasilnya belum masuk → "menunggu hasil".
                'Kuis_Menunggu' => $it->Tipe_Item === 'KUIS'
                    && isset($sesiKuis[$it->Id_SOP_Paket_Item.':'.($verId ?? 'null')])
                    && ! $isSelesai
                    && (string) ($prog->Id_Ujian_Token_Hasil ?? '') !== (string) $sesiKuis[$it->Id_SOP_Paket_Item.':'.($verId ?? 'null')]->Id_Ujian_Token,
                // Plafon percobaan ikut dikirim supaya UI bisa menulis "1 dari 2",
                // bukan cuma "1x" yang tak memberi tahu apakah masih boleh mengulang.
                'Maks_Percobaan' => \App\Services\LMS\SOP\SopKuisDispatchService::MAKS_PERCOBAAN,
                'Durasi_Detik' => $prog ? (int) $prog->Durasi_Aktif_Detik : 0,
                'Halaman_Terakhir' => $prog && $prog->Halaman_Terakhir !== null ? (int) $prog->Halaman_Terakhir : null,
                'Waktu_Buka' => $prog && $prog->Waktu_Buka ? Carbon::parse($prog->Waktu_Buka)->format('Y-m-d H:i') : null,
                'Waktu_Konfirmasi' => $prog && $prog->Waktu_Konfirmasi ? Carbon::parse($prog->Waktu_Konfirmasi)->format('Y-m-d H:i') : null,
                // dokumen resmi (versi aktif)
                'Ringkasan_Perubahan' => $it->Ringkasan_Perubahan,
                'Tanggal_Berlaku' => $it->Tanggal_Berlaku ? Carbon::parse($it->Tanggal_Berlaku)->format('Y-m-d') : null,
                'Disahkan_Oleh' => $it->Disahkan_Oleh,
                'Jabatan_Pengesah' => $it->Jabatan_Pengesah,
                'Tanggal_Pengesahan' => $it->Tanggal_Pengesahan ? Carbon::parse($it->Tanggal_Pengesahan)->format('Y-m-d') : null,
                'Nomor_Pengesahan' => $it->Nomor_Pengesahan,
                'Kode_Karyawan_Publisher' => $it->Kode_Karyawan_Publisher,
                'Publisher_Nama' => $it->Publisher_Nama,
            ];
        }

        // Status keberlakuan ditempel di server, bukan diturunkan di browser —
        // lihat keberlakuanItem(). Murni, jadi tidak menambah query.
        foreach ($itemsOut as $i => $row) {
            $itemsOut[$i]['Keberlakuan'] = $this->keberlakuanItem($row);
        }

        $persen = $totalWajib > 0 ? round($selesaiWajib / $totalWajib * 100) : 0;

        // banner "diperbarui" bila penugasan karyawan pernah DIBUKA_LAGI & belum tuntas
        $banner = null;
        if ($detailId && $persen < 100) {
            $reopened = DB::table('N_SOP_Kepatuhan_Riwayat')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->where('Peristiwa', SopStatus::RWT_DIBUKA_LAGI)->exists();
            if ($reopened) {
                /* Ditautkan lewat DOKUMEN juga: versi milik dokumen ber-
                   Id_SOP_Paket_Item NULL, sehingga join tunggal membuat justru
                   ringkasan perubahan yang PALING relevan — revisi dokumen yang
                   memicu banner ini — tak pernah terbaca. */
                $ring = DB::table('N_SOP_Item_Versi as v')
                    ->join('N_SOP_Paket_Item as i', fn ($j) => SopVersiItem::tautkan($j, 'i', 'v'))
                    ->where('i.Id_SOP_Paket', $idPaket)
                    ->where('v.Status', SopStatus::PAKET_PUBLISHED)
                    ->whereNotNull('v.Ringkasan_Perubahan')
                    ->orderByDesc('v.Tanggal_Publish')
                    ->value('v.Ringkasan_Perubahan');
                $banner = 'dokumen ini diperbarui — mohon baca ulang bagian yang berubah.'
                    .($ring ? ' '.$ring : '');
            }
        }

        // konteks penugasan (tenggat, assign, selesai) — null bila SOP dibuka dari Repository
        $penugasan = null;
        if ($detailId) {
            $pen = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('d.Id_SOP_Penjadwalan_Detail', $detailId)
                ->first(['pj.Tipe_Tenggat', 'pj.Tanggal_Deadline', 'pj.Tanggal_Assign', 'pj.Sumber',
                    'd.Tanggal_Selesai', 'd.Status_Paket']);
            if ($pen) {
                $tenggat = ($pen->Tipe_Tenggat === 'DEADLINE' && $pen->Tanggal_Deadline)
                    ? Carbon::parse($pen->Tanggal_Deadline) : null;
                $penugasan = [
                    'Tipe_Tenggat' => $pen->Tipe_Tenggat,
                    'Tenggat' => $tenggat ? $tenggat->format('Y-m-d') : null,
                    'Is_Terlambat' => $tenggat ? ($tenggat->lt(Carbon::today()) && $persen < 100) : false,
                    'Hari_Tersisa' => $tenggat ? Carbon::today()->diffInDays($tenggat->copy()->startOfDay(), false) : null,
                    'Tanggal_Assign' => $pen->Tanggal_Assign ? Carbon::parse($pen->Tanggal_Assign)->format('Y-m-d') : null,
                    'Tanggal_Selesai' => $pen->Tanggal_Selesai ? Carbon::parse($pen->Tanggal_Selesai)->format('Y-m-d H:i') : null,
                    'Status_Paket' => $pen->Status_Paket,
                    'Sumber' => $pen->Sumber,
                ];
            }
        }

        $legalitas = $this->legalitasBundle($kode, $idPaket, $detailId ? (int) $detailId : null, $paket, $itemsOut, $penugasan, $persen);

        return [
            'paket' => [
                'Id_SOP_Paket' => $this->enc($paket->Id_SOP_Paket),
                'Kode_Paket' => $paket->Kode_Paket,
                'Nama_Paket' => $paket->Nama_Paket,
                'Prioritas' => $paket->Prioritas,
                'Mode_Urutan' => $paket->Mode_Urutan_Item,
                'Flag_Absensi' => $paket->Flag_Absensi,
                'Status' => $paket->Status,
                'Cover_Url' => (new SopPaketService)->coverUrl($paket->Cover_Path ?? null),
                'Cover_Position' => $paket->Cover_Position,
                'Deskripsi' => $paket->Deskripsi,
                'Tujuan_Ruang_Lingkup' => $paket->Tujuan_Ruang_Lingkup,
                'Estimasi_Waktu_Baca_Menit' => $paket->Estimasi_Waktu_Baca_Menit ? (int) $paket->Estimasi_Waktu_Baca_Menit : null,
                'Owner_Nama' => $paket->Owner_Nama,
                'Owner_Kode' => $paket->Kode_Karyawan_Owner,
                'Tanggal_Publish' => $paket->Tanggal_Publish ? Carbon::parse($paket->Tanggal_Publish)->format('Y-m-d') : null,
                'Updated_At' => $paket->Updated_At ? Carbon::parse($paket->Updated_At)->format('Y-m-d') : null,
            ],
            'penugasan' => $penugasan,
            'sections' => $sections,
            'banner_perubahan' => $banner,
            'items' => $itemsOut,
            'progress' => ['persen' => $persen, 'item_selesai' => $selesaiWajib, 'total' => $totalWajib],
            'legalitas' => $legalitas,
        ];
    }

    /**
     * Status keberlakuan satu dokumen (item / versi item).
     *
     * Sengaja diturunkan di server: "dokumen ini masih berlaku" adalah klaim
     * audit, dan jam browser bisa salah, bergeser zona waktu, atau diubah
     * pengguna. Murni fungsi — tanpa query — supaya bisa dipakai jalur bundle
     * maupun riwayat versi, dan bisa diuji tanpa menyentuh DB.
     *
     * MASA BERLAKU SUDAH TAK ADA DI SINI. Sejak konsep kadaluarsa & review
     * dicabut (15 Agu 2026), satu-satunya yang menghentikan keberlakuan sebuah
     * versi adalah terbitnya versi yang lebih baru. Sebuah dokumen tidak lagi
     * "gugur karena tanggal" — ia gugur karena digantikan.
     *
     * @return array{status:string, alasan:string}
     */
    public function keberlakuanItem(array $item): array
    {
        $today = Carbon::today();
        $tgl = function (?string $k) use ($item) {
            $v = $item[$k] ?? null;

            return $v ? Carbon::parse($v)->startOfDay() : null;
        };

        $versiIni = $item['Id_SOP_Item_Versi'] ?? null;
        $versiAktif = $item['Id_Item_Versi_Aktif'] ?? null;
        if ($versiIni !== null && $versiAktif !== null && (int) $versiIni !== (int) $versiAktif) {
            return ['status' => 'VERSI_LAMA', 'alasan' => 'Sudah digantikan versi yang lebih baru.'];
        }

        $berlaku = $tgl('Tanggal_Berlaku');
        if ($berlaku && $berlaku->gt($today)) {
            return [
                'status' => 'BELUM_BERLAKU',
                'alasan' => 'Baru berlaku mulai '.$berlaku->format('d M Y').'.',
            ];
        }

        return ['status' => 'BERLAKU', 'alasan' => 'Versi yang sedang berlaku.'];
    }

    private function legalitasBundle(string $kode, int $idPaket, ?int $detailId, object $paket, array $items, ?array $penugasan, int $persen): array
    {
        $itemCollection = collect($items);
        $firstOpen = $itemCollection->pluck('Waktu_Buka')->filter()->sort()->first();
        $lastConfirm = $itemCollection->pluck('Waktu_Konfirmasi')->filter()->sort()->last();
        $officialItems = $itemCollection->filter(fn ($it) => ! empty($it['Disahkan_Oleh']) || ! empty($it['Nomor_Pengesahan'])
            || ! empty($it['Tanggal_Pengesahan']) || ! empty($it['Tanggal_Berlaku'])
        )->values();
        $activePublishers = $itemCollection->filter(fn ($it) => ! empty($it['Publisher_Nama']) || ! empty($it['Kode_Karyawan_Publisher'])
        );
        $publisherCounts = $activePublishers
            ->map(fn ($it) => $it['Publisher_Nama'] ?: $it['Kode_Karyawan_Publisher'])
            ->filter()
            ->countBy()
            ->sortDesc();
        $primaryPublisher = $publisherCounts->keys()->first();
        $primaryPublisherItem = $activePublishers->first(fn ($it) => ($it['Publisher_Nama'] ?: $it['Kode_Karyawan_Publisher']) === $primaryPublisher
        );
        $primaryPublisherCode = $primaryPublisherItem['Kode_Karyawan_Publisher'] ?? null;
        $effectiveDates = $itemCollection->pluck('Tanggal_Berlaku')->filter()->sort()->values();
        $highestVersion = $itemCollection->pluck('Versi')->filter()->max();
        $documentTotal = $itemCollection->count();
        $officialCount = $officialItems->count();
        $publisherCount = $activePublishers->count();
        $validityCount = $itemCollection->filter(fn ($it) => ! empty($it['Tanggal_Berlaku']))->count();
        $metadataWarnings = [];
        if (! $paket->Kode_Paket) {
            $metadataWarnings[] = 'KODE_PAKET_KOSONG';
        }
        if (! $paket->Kode_Karyawan_Owner && ! $paket->Owner_Nama) {
            $metadataWarnings[] = 'OWNER_BELUM_TERCATAT';
        }
        if ($publisherCount === 0) {
            $metadataWarnings[] = 'PUBLISHER_DOKUMEN_BELUM_TERCATAT';
        }
        if ($officialCount === 0) {
            $metadataWarnings[] = 'PENGESAHAN_DOKUMEN_BELUM_TERCATAT';
        }
        if ($validityCount === 0) {
            $metadataWarnings[] = 'TANGGAL_BERLAKU_BELUM_TERCATAT';
        }
        $paketVersion = app(SopPaketVersionService::class)->current($idPaket);

        $updatedItems = $itemCollection->filter(fn ($it) => ! empty($it['Ringkasan_Perubahan']) || ! empty($it['Ada_Riwayat'])
        )->map(fn ($it) => [
            'Judul_Item' => $it['Judul_Item'],
            'Tipe_Item' => $it['Tipe_Item'],
            'Versi' => $it['Versi'],
            'Ringkasan_Perubahan' => $it['Ringkasan_Perubahan'],
            'Tanggal_Berlaku' => $it['Tanggal_Berlaku'],
            'Status' => $it['Status'],
            'Publisher_Nama' => $it['Publisher_Nama'] ?? null,
        ])->values()->all();

        $documents = $itemCollection->map(fn ($it) => [
            'Judul_Item' => $it['Judul_Item'],
            'Tipe_Item' => $it['Tipe_Item'],
            'Wajib' => $it['Wajib'],
            'Versi' => $it['Versi'],
            'Status' => $it['Status'],
            'Status_Lulus' => $it['Status_Lulus'],
            'Skor_Quiz' => $it['Skor_Quiz'],
            'Waktu_Buka' => $it['Waktu_Buka'],
            'Waktu_Konfirmasi' => $it['Waktu_Konfirmasi'],
            'Nomor_SOP_Resmi' => $it['Nomor_SOP_Resmi'],
            'Nomor_Pengesahan' => $it['Nomor_Pengesahan'],
            'Tanggal_Pengesahan' => $it['Tanggal_Pengesahan'],
            'Disahkan_Oleh' => $it['Disahkan_Oleh'],
            'Tanggal_Berlaku' => $it['Tanggal_Berlaku'],
            'Kode_Karyawan_Publisher' => $it['Kode_Karyawan_Publisher'] ?? null,
            'Publisher_Nama' => $it['Publisher_Nama'] ?? null,
        ])->values()->all();

        $timeline = collect();
        if ($paket->Tanggal_Publish) {
            $timeline->push([
                'tanggal' => Carbon::parse($paket->Tanggal_Publish)->format('Y-m-d H:i'),
                'judul' => 'Paket Dokumen diterbitkan',
                'deskripsi' => 'Dokumen resmi tersedia untuk dibaca.',
                'tone' => 'info',
                'icon' => 'bi-megaphone-fill',
            ]);
        }
        if ($paketVersion && ! empty($paketVersion['tanggal_publish'])) {
            $timeline->push([
                'tanggal' => Carbon::parse($paketVersion['tanggal_publish'])->format('Y-m-d H:i'),
                'judul' => 'Versi paket v'.$paketVersion['versi'].' dipublish',
                'deskripsi' => $paketVersion['ringkasan'] ?: 'Rilis paket dokumen tercatat.',
                'tone' => 'info',
                'icon' => 'bi-layers-fill',
            ]);
        }
        if ($penugasan && ! empty($penugasan['Tanggal_Assign'])) {
            $timeline->push([
                'tanggal' => $penugasan['Tanggal_Assign'],
                'judul' => 'Dokumen masuk daftar tugas',
                'deskripsi' => $penugasan['Sumber'] === 'AUTO_RULE' ? 'Ditugaskan otomatis oleh kebijakan.' : 'Ditugaskan oleh admin.',
                'tone' => 'info',
                'icon' => 'bi-person-check-fill',
            ]);
        }
        if ($firstOpen) {
            $timeline->push([
                'tanggal' => $firstOpen,
                'judul' => 'Pertama dibuka',
                'deskripsi' => 'Aktivitas baca mulai tercatat.',
                'tone' => 'work',
                'icon' => 'bi-eye-fill',
            ]);
        }
        if ($lastConfirm) {
            $timeline->push([
                'tanggal' => $lastConfirm,
                'judul' => 'Konfirmasi terakhir',
                'deskripsi' => 'Item terakhir yang dikonfirmasi tercatat di progres.',
                'tone' => 'ok',
                'icon' => 'bi-check-circle-fill',
            ]);
        }
        if ($penugasan && ! empty($penugasan['Tanggal_Selesai'])) {
            $timeline->push([
                'tanggal' => $penugasan['Tanggal_Selesai'],
                'judul' => 'Paket Dokumen selesai',
                'deskripsi' => 'Seluruh item wajib pada paket ini sudah tuntas.',
                'tone' => 'ok',
                'icon' => 'bi-patch-check-fill',
            ]);
        }

        if ($detailId) {
            $ledger = DB::table('N_SOP_Kepatuhan_Riwayat')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->orderByDesc('Waktu_Peristiwa')
                ->limit(6)
                ->get(['Peristiwa', 'Alasan', 'Persen_Saat_Itu', 'Waktu_Peristiwa']);
            foreach ($ledger as $row) {
                $timeline->push([
                    'tanggal' => $row->Waktu_Peristiwa ? Carbon::parse($row->Waktu_Peristiwa)->format('Y-m-d H:i') : null,
                    'judul' => match ($row->Peristiwa) {
                        SopStatus::RWT_DIBUKA_LAGI => 'Dokumen diperbarui untuk dibaca ulang',
                        SopStatus::RWT_DICABUT => 'Akses penugasan dicabut',
                        SopStatus::RWT_KADALUARSA => 'Dokumen melewati masa berlaku',
                        default => 'Riwayat kepatuhan tercatat',
                    },
                    'deskripsi' => trim(($row->Alasan ? "Alasan: {$row->Alasan}. " : '')."Progres saat itu {$row->Persen_Saat_Itu}%."),
                    'tone' => in_array($row->Peristiwa, [SopStatus::RWT_DIBUKA_LAGI, SopStatus::RWT_KADALUARSA], true) ? 'warn' : 'info',
                    'icon' => match ($row->Peristiwa) {
                        SopStatus::RWT_DIBUKA_LAGI => 'bi-arrow-repeat',
                        SopStatus::RWT_DICABUT => 'bi-slash-circle-fill',
                        SopStatus::RWT_KADALUARSA => 'bi-calendar-x-fill',
                        default => 'bi-journal-check',
                    },
                ]);
            }
        }

        return [
            'status' => [
                'paket' => $penugasan['Status_Paket'] ?? null,
                'persen' => $persen,
                'selesai' => $persen >= 100,
                'ditugaskan' => $penugasan !== null,
                'terlambat' => (bool) ($penugasan['Is_Terlambat'] ?? false),
                'tenggat' => $penugasan['Tenggat'] ?? null,
            ],
            'paket_resmi' => [
                'kode_paket' => $paket->Kode_Paket,
                'nama_paket' => $paket->Nama_Paket,
                'status_paket' => $paket->Status,
                'mode_distribusi' => $paket->Mode_Distribusi,
                'owner_nama' => $paket->Owner_Nama,
                'owner_kode' => $paket->Kode_Karyawan_Owner,
                'tanggal_publish' => $paket->Tanggal_Publish ? Carbon::parse($paket->Tanggal_Publish)->format('Y-m-d') : null,
                'updated_at' => $paket->Updated_At ? Carbon::parse($paket->Updated_At)->format('Y-m-d') : null,
                'versi_paket' => $paketVersion['versi'] ?? null,
                'id_paket_versi' => $paketVersion['id'] ?? null,
                'tanggal_publish_versi' => ! empty($paketVersion['tanggal_publish'])
                    ? Carbon::parse($paketVersion['tanggal_publish'])->format('Y-m-d')
                    : null,
                'ringkasan_versi' => $paketVersion['ringkasan'] ?? null,
                'versi_tertinggi' => $highestVersion ? (int) $highestVersion : null,
                'publisher_utama' => $primaryPublisher,
                'publisher_kode' => $primaryPublisherCode,
                'dokumen_total' => $documentTotal,
                'dokumen_disahkan' => $officialCount,
                'dokumen_dengan_publisher' => $publisherCount,
                'dokumen_dengan_validitas' => $validityCount,
                'tanggal_berlaku_awal' => $effectiveDates->first(),
                'metadata_warnings' => $metadataWarnings,
            ],
            'bukti' => [
                'kode_karyawan' => $kode,
                'kode_paket' => $paket->Kode_Paket,
                'nama_paket' => $paket->Nama_Paket,
                'owner' => $paket->Owner_Nama ?: $paket->Kode_Karyawan_Owner,
                'tanggal_assign' => $penugasan['Tanggal_Assign'] ?? null,
                'tanggal_selesai' => $penugasan['Tanggal_Selesai'] ?? null,
                'pertama_dibuka' => $firstOpen,
                'konfirmasi_terakhir' => $lastConfirm,
                'total_wajib' => collect($items)->where('Wajib', true)->count(),
                'total_selesai' => collect($items)->filter(fn ($it) => $it['Wajib'] && in_array($it['Status'], [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI], true)
                )->count(),
            ],
            'dokumen_resmi' => $officialItems->map(fn ($it) => [
                'Judul_Item' => $it['Judul_Item'],
                'Tipe_Item' => $it['Tipe_Item'],
                'Versi' => $it['Versi'],
                'Disahkan_Oleh' => $it['Disahkan_Oleh'],
                'Jabatan_Pengesah' => $it['Jabatan_Pengesah'],
                'Nomor_Pengesahan' => $it['Nomor_Pengesahan'],
                'Tanggal_Pengesahan' => $it['Tanggal_Pengesahan'],
                'Tanggal_Berlaku' => $it['Tanggal_Berlaku'],
                'Publisher_Nama' => $it['Publisher_Nama'] ?? null,
                'Kode_Karyawan_Publisher' => $it['Kode_Karyawan_Publisher'] ?? null,
            ])->all(),
            'dokumen' => $documents,
            'update_dokumen' => $updatedItems,
            'timeline' => $timeline->filter(fn ($row) => ! empty($row['tanggal']))
                ->sortBy('tanggal')->values()->all(),
        ];
    }

    /** Id detail penugasan aktif karyawan utk paket (null bila tak ada). */
    private function resolveDetailId(string $kode, int $idPaket): ?int
    {
        $id = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->value('d.Id_SOP_Penjadwalan_Detail');

        return $id ? (int) $id : null;
    }

    /**
     * Riwayat versi item utk KARYAWAN (PUBLISHED/OBSOLETE, DRAFT disembunyikan) +
     * status baca karyawan per versi (v1 sudah dibaca, v2 pending, dst). Return null bila item bukan milik paket.
     */
    public function itemVersiHistory(string $kode, int $idPaket, int $idItem): ?array
    {
        // Riwayat versi ikut dijaga: tanpa ini, judul & metadata dokumen paket
        // TERBATAS masih bisa dibaca siapa saja yang tahu hashid-nya.
        if (! $this->paketBolehDiakses($kode, $idPaket)) {
            return null;
        }

        $snapshot = app(SopPaketVersionService::class)->releasedSnapshot($idPaket);
        $snapshotItem = $snapshot
            ? collect($snapshot['items'])->first(fn ($row) => (int) ($row['Id_SOP_Paket_Item'] ?? 0) === $idItem)
            : null;
        $item = $snapshotItem
            ? (object) [
                'Id_SOP_Paket_Item' => (int) $snapshotItem['Id_SOP_Paket_Item'],
                'Id_Item_Versi_Aktif' => $snapshotItem['Id_SOP_Item_Versi'] ?? null,
            ]
            : DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket_Item', $idItem)
                ->where('Id_SOP_Paket', $idPaket)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->first(['Id_SOP_Paket_Item', 'Id_Item_Versi_Aktif']);
        if (! $item) {
            return null;
        }

        /*
         * Item HIDUP dibaca terpisah dari snapshot.
         *
         * Snapshot membekukan Id_SOP_Item_Versi saat paket dirilis. Untuk item
         * ber-DOKUMEN pointer beku itu bukan lagi versi yang berlaku — revisi
         * dokumen berlaku serentak di semua paket perujuk tanpa menyentuh
         * snapshot. Aturannya sama persis dengan selaraskanVersiHidup() yang
         * dipakai detailBundle: struktur beku, versi ikut dokumen.
         */
        $live = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $idItem)
            ->where('Id_SOP_Paket', $idPaket)
            ->first(['Id_Item_Versi_Aktif', 'Id_SOP_Dokumen', 'Tipe_Item']);
        $idDokumen = $live && $live->Id_SOP_Dokumen ? (int) $live->Id_SOP_Dokumen : null;

        $detailId = $this->resolveDetailId($kode, $idPaket);
        $bacaByVersi = [];
        if ($detailId) {
            foreach (DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $detailId)
                ->where('Id_SOP_Paket_Item', $idItem)
                ->get(['Id_SOP_Item_Versi', 'Status']) as $pr) {
                if ($pr->Id_SOP_Item_Versi !== null) {
                    $bacaByVersi[(int) $pr->Id_SOP_Item_Versi] = $pr->Status;
                }
            }
        }

        // Pointer beku hanya dipercaya untuk item TANPA dokumen — merekalah yang
        // memang tak punya sumbu lain.
        $aktif = $idDokumen && $live->Id_Item_Versi_Aktif
            ? (int) $live->Id_Item_Versi_Aktif
            : ($item->Id_Item_Versi_Aktif ? (int) $item->Id_Item_Versi_Aktif : null);

        /*
         * Daftar versi yang pernah dirilis membatasi riwayat pada apa yang
         * benar-benar pernah dibagikan paket ini — benar untuk versi milik item.
         * Untuk item ber-DOKUMEN batas itu memenggal riwayat: revisi dokumen
         * tak pernah tercatat di N_SOP_Paket_Versi_Item, sehingga karyawan hanya
         * melihat versi dari rilis paket terakhir. Di paket 23 itu berarti satu
         * baris (v1) untuk dokumen yang sudah enam versi.
         */
        $releasedVersionIds = null;
        if ($snapshot && ! $idDokumen) {
            $releasedVersionIds = DB::table('N_SOP_Paket_Versi_Item as vi')
                ->join('N_SOP_Paket_Versi as pv', 'pv.Id_SOP_Paket_Versi', '=', 'vi.Id_SOP_Paket_Versi')
                ->where('pv.Id_SOP_Paket', $idPaket)
                ->where('pv.Versi_Ke', '<=', (int) $snapshot['versi'])
                ->where('vi.Id_SOP_Paket_Item', $idItem)
                ->whereNotNull('vi.Id_SOP_Item_Versi')
                ->pluck('vi.Id_SOP_Item_Versi')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($aktif && $releasedVersionIds->isEmpty()) {
                $releasedVersionIds = collect([$aktif]);
            }
        }

        // Tipe item dibaca sekali: aturan pratinjau berlaku per tipe, dan tanpanya
        // riwayat versi tak bisa menjawab pertanyaan yang sama dengan kartu item.
        $tipeItem = $live->Tipe_Item ?? null;

        $query = DB::table('N_SOP_Item_Versi as v')
            ->leftJoin('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->leftJoin('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'k.Id_Master_Konten')
            /* Dicari lewat DOKUMEN juga. Sejak versi dimiliki dokumen,
               Id_SOP_Paket_Item pada barisnya NULL — mencari lewat FK itu saja
               hanya menemukan versi warisan dari zaman versi milik item. */
            ->where(function ($q) use ($idItem, $idDokumen) {
                $q->where('v.Id_SOP_Paket_Item', $idItem);
                if ($idDokumen) {
                    $q->orWhere('v.Id_SOP_Dokumen', $idDokumen);
                }
            })
            ->where('v.Flag_Aktif', SopStatus::AKTIF)
            ->whereIn('v.Status', [SopStatus::PAKET_PUBLISHED, 'OBSOLETE']);

        if ($releasedVersionIds !== null) {
            if ($releasedVersionIds->isEmpty()) {
                return [];
            }
            $query->whereIn('v.Id_SOP_Item_Versi', $releasedVersionIds->all());
        }

        return $query
            ->orderByDesc('v.Versi_Ke')
            // Kolom tata kelola ikut di-select dari tabel yang memang sudah
            // di-query — panel metadata dokumen butuh masa berlaku & pengesahan
            // versi lama, dan menariknya terpisah berarti query per versi.
            ->get(['v.Id_SOP_Item_Versi', 'v.Versi_Ke', 'v.Status', 'v.Ringkasan_Perubahan',
                'v.Tanggal_Berlaku', 'v.Tanggal_Publish', 'v.Disahkan_Oleh', 'v.Jabatan_Pengesah',
                'v.Tanggal_Pengesahan', 'v.Nomor_Pengesahan',
                'k.Judul_Konten as Konten_Nama', 'k.Tipe_Konten as Konten_Tipe',
                'b.Type_File', 'b.File_Path', 'b.Ext', 'b.Ukuran_Byte'])
            ->map(function ($v) use ($bacaByVersi, $aktif, $tipeItem) {
                $tgl = fn ($d) => $d ? Carbon::parse($d)->format('Y-m-d') : null;

                $baris = [
                    'Id_SOP_Item_Versi' => $this->enc($v->Id_SOP_Item_Versi),
                    'Versi_Ke' => (int) $v->Versi_Ke,
                    'Status_Versi' => $v->Status,               // PUBLISHED | OBSOLETE
                    'Is_Aktif' => $aktif === (int) $v->Id_SOP_Item_Versi,
                    'Status_Baca' => $bacaByVersi[(int) $v->Id_SOP_Item_Versi] ?? SopStatus::ITEM_BELUM,
                    'Ringkasan_Perubahan' => $v->Ringkasan_Perubahan,
                    'Tanggal_Berlaku' => $tgl($v->Tanggal_Berlaku),
                    'Tanggal_Publish' => $v->Tanggal_Publish,
                    'Disahkan_Oleh' => $v->Disahkan_Oleh,
                    'Jabatan_Pengesah' => $v->Jabatan_Pengesah,
                    'Tanggal_Pengesahan' => $tgl($v->Tanggal_Pengesahan),
                    'Nomor_Pengesahan' => $v->Nomor_Pengesahan,
                    'Konten_Nama' => $v->Konten_Nama,
                    'Konten_Tipe' => $v->Konten_Tipe,
                    'Konten_Mime' => $v->Type_File,
                    'Konten_Ext' => SopAksesBerkas::ekstensi($v->Ext, $v->File_Path),
                    'Ukuran_File' => $v->Ukuran_Byte !== null ? (int) $v->Ukuran_Byte : null,
                    /*
                     * ISI HANYA DARI VERSI YANG BERLAKU.
                     *
                     * Keputusan produk 2026-08-12. Riwayat tetap ditampilkan —
                     * karyawan perlu tahu dokumen ini pernah direvisi dan mana
                     * yang sudah ia baca — tapi ISI versi lama tidak lagi
                     * dibagikan. Dokumen terkendali yang sudah dicabut masih
                     * disimpan untuk penelusuran, bukan untuk dipakai; berkas
                     * lama yang tetap bisa diunduh adalah cara paling mudah
                     * seseorang mengerjakan prosedur yang sudah tak berlaku.
                     *
                     * Ditegakkan juga di apiVersiKonten dan pembaca PDF aman.
                     * Menutupnya di sini saja hanya menyembunyikan tombolnya.
                     */
                    'Bisa_Dibuka' => $aktif === (int) $v->Id_SOP_Item_Versi,
                    'Bisa_Pratinjau' => $aktif === (int) $v->Id_SOP_Item_Versi
                        && SopAksesBerkas::bisaPratinjau($tipeItem, $v->Type_File, $v->File_Path),
                ];

                // Status diturunkan di server juga untuk baris riwayat, supaya
                // versi lama tak pernah dilabeli "berlaku" oleh jam browser.
                $baris['Keberlakuan'] = $this->keberlakuanItem(array_merge($baris, [
                    // id mentah, bukan hashid — perbandingan versi butuh angka
                    'Id_SOP_Item_Versi' => (int) $v->Id_SOP_Item_Versi,
                    'Id_Item_Versi_Aktif' => $aktif,
                ]));

                return $baris;
            })->all();
    }

    /**
     * Metadata berkas (mime, ekstensi, ukuran) untuk sekumpulan id versi item.
     *
     * Satu query untuk semua versi sekaligus, bukan per item — daftar item bisa
     * puluhan baris dan ini dipanggil di jalur render halaman detail.
     *
     * @param  iterable  $idVersi  id versi mentah; nilai kosong/duplikat diabaikan
     * @return array<int,object> dikunci id versi
     */
    /**
     * Snapshot rilis membekukan STRUKTUR, bukan versi dokumennya.
     *
     * Snapshot menyimpan Id_SOP_Item_Versi apa adanya saat paket dirilis. Untuk
     * susunan item, urutan, dan wajib/tidak itu benar — semuanya milik paket.
     * Tapi versi berkas bukan milik paket: ia milik DOKUMEN terkendali, dan
     * revisinya berlaku serentak di semua paket pemakainya (fan-out Fase 3).
     *
     * Sebelum ini fan-out hanya menggeser Id_Item_Versi_Aktif pada item, tak
     * pernah menyentuh snapshot — sementara snapshot itulah yang benar-benar
     * disajikan. Akibatnya paket yang dilayani snapshot terus membagikan berkas
     * PRA-REVISI tanpa tanda apa pun. Terbukti di paket 23: dokumen sudah di v2
     * (PDF) sementara karyawan masih menerima v1 (.xlsx) dari rilis 3 Agustus.
     *
     * Keputusan produk 2026-08-10: struktur beku, versi ikut dokumen.
     *
     * Hanya item ber-DOKUMEN yang diselaraskan. Item tanpa dokumen tak punya
     * sumbu dokumen sama sekali, sehingga versi bekunya memang satu-satunya
     * yang benar untuk mereka.
     */
    private function selaraskanVersiHidup($items)
    {
        $ids = collect($items)->pluck('Id_SOP_Paket_Item')->filter()->all();
        if ($ids === []) {
            return $items;
        }

        $hidup = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->leftJoin('N_LMS_Master_Konten as kn', 'kn.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->leftJoin('Karyawan as pub', 'pub.Kode_Karyawan', '=', 'v.Kode_Karyawan_Publisher')
            ->whereIn('i.Id_SOP_Paket_Item', $ids)
            ->whereNotNull('i.Id_SOP_Dokumen')
            ->get(['i.Id_SOP_Paket_Item', 'i.Id_Item_Versi_Aktif', 'v.Versi_Ke',
                'kn.Judul_Konten as Konten_Nama', 'kn.Tipe_Konten as Konten_Tipe',
                'v.Ringkasan_Perubahan', 'v.Tanggal_Berlaku', 'v.Disahkan_Oleh', 'v.Jabatan_Pengesah',
                'v.Tanggal_Pengesahan', 'v.Nomor_Pengesahan', 'v.Kode_Karyawan_Publisher',
                'pub.Nama as Publisher_Nama'])
            ->keyBy('Id_SOP_Paket_Item');

        return collect($items)->map(function ($it) use ($hidup) {
            $h = $hidup[(int) $it->Id_SOP_Paket_Item] ?? null;
            if (! $h) {
                return $it;
            }

            foreach ((array) $h as $kolom => $nilai) {
                if ($kolom === 'Id_SOP_Paket_Item') {
                    continue;
                }
                $it->{$kolom} = $nilai;
            }

            return $it;
        })->values();
    }

    private function berkasVersi(iterable $idVersi): array
    {
        $ids = collect($idVersi)->filter()->map(fn ($v) => (int) $v)->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('N_SOP_Item_Versi as v')
            ->join('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'v.Id_Master_Konten')
            ->whereIn('v.Id_SOP_Item_Versi', $ids->all())
            ->get(['v.Id_SOP_Item_Versi', 'b.Type_File', 'b.File_Path', 'b.Ext', 'b.Ukuran_Byte'])
            ->keyBy('Id_SOP_Item_Versi')
            ->all();
    }

    /** Guard: versi milik item dalam paket PUBLISHED aktif & bukan DRAFT. */
    /**
     * Boleh-tidaknya seorang karyawan mengakses sebuah paket.
     * Satu tempat, dipakai detailBundle(), itemVersiHistory(), dan
     * versiVisibleInPaket() — kalau tiap endpoint punya aturannya sendiri,
     * cepat atau lambat salah satunya ketinggalan.
     */
    public function paketBolehDiakses(string $kode, int $idPaket): bool
    {
        $p = DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $idPaket)
            ->first(['Status', 'Flag_Aktif', 'Mode_Distribusi']);

        if (! $p || $p->Status !== SopStatus::PAKET_PUBLISHED || $p->Flag_Aktif !== SopStatus::AKTIF) {
            return false;
        }
        // NULL diperlakukan PUBLIK — bila skrip 15_N_SOP_mode_distribusi.sql belum jalan.
        if (($p->Mode_Distribusi ?? 'PUBLIK') !== 'TERBATAS') {
            return true;
        }

        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->exists();
    }

    /**
     * Guard konten versi.
     *
     * Kini ikut memeriksa kepemilikan karyawan. Sebelumnya hanya paket
     * PUBLISHED + aktif yang dicek, sehingga paket TERBATAS masih bisa
     * dipanen isinya lewat URL langsung — dan endpoint ini mengembalikan
     * SIGNED URL berkas aslinya, jadi kebocorannya bukan sekadar metadata.
     */
    public function versiVisibleInPaket(string $kode, int $idPaket, int $idVersi): bool
    {
        if (! $this->paketBolehDiakses($kode, $idPaket)) {
            return false;
        }

        /*
         * Snapshot rilis adalah JALAN CEPAT, bukan jalan buntu.
         *
         * Ia membekukan Id_SOP_Item_Versi apa adanya saat paket dirilis. Untuk
         * susunan item itu benar — tapi versi berkas milik DOKUMEN, dan revisinya
         * berlaku serentak di semua paket pemakainya tanpa menyentuh snapshot.
         * Begitu sebuah dokumen direvisi, yang DISAJIKAN ke karyawan adalah versi
         * hidup (detailBundle meluruskannya lewat selaraskanVersiHidup) sementara
         * penjaga ini masih menanyakan versi beku — lalu menolak berkas yang
         * halamannya sendiri baru saja menawarkannya. Terbukti pada paket 23:
         * snapshot menyimpan versi 84, pointer hidup sudah 1505, dan tombol
         * "Lihat" berbalas 403 pada dokumen yang baru diperbarui.
         *
         * Tak ditemukan di snapshot berarti "belum tentu boleh", bukan "tidak
         * boleh": pertanyaannya diteruskan ke pemeriksaan berbasis dokumen di
         * bawah, yang justru aturan sebenarnya.
         */
        $snapshot = app(SopPaketVersionService::class)->releasedSnapshot($idPaket);
        if ($snapshot && collect($snapshot['items'])
            ->contains(fn ($item) => (int) ($item['Id_SOP_Item_Versi'] ?? 0) === $idVersi)) {
            return true;
        }

        /* Keterlihatan diputuskan lewat DOKUMEN: apakah paket ini punya item
           yang merujuk dokumen versi tersebut.

           Menautkan lewat v.Id_SOP_Paket_Item MENOLAK karyawan yang sah di
           paket lain yang menyajikan dokumen yang sama — FK itu tunggal dan
           menunjuk item yang kebetulan membuat versinya. Salah tolak, bukan
           salah izinkan, tapi sama-sama cacat otorisasi.

           Cabang ini bukan kasus pinggir: ia hanya jalan bila paket tak punya
           snapshot rilis, dan di data dev hanya 1 dari 12 paket punya snapshot
           sementara 7 paket PUBLISHED dengan penugasan aktif (sampai 34 orang)
           tidak punya.

           Cabang FK dipertahankan sebagai jalur mundur untuk versi
           ber-Id_SOP_Dokumen NULL — NULL = NULL bernilai NULL, bukan true.

           Filter Flag_Aktif SENGAJA TIDAK ditambahkan: kueri lama tak
           memilikinya, dan menambahkannya di sini akan menolak akses yang
           selama ini diberikan lewat item nonaktif. Itu pengetatan tersendiri
           yang harus diputuskan terpisah, bukan diselundupkan ke perbaikan
           join. */
        return DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Paket_Item as i', fn ($j) => SopVersiItem::tautkan($j, 'i', 'v'))
            ->where('v.Id_SOP_Item_Versi', $idVersi)
            ->where('i.Id_SOP_Paket', $idPaket)
            ->whereIn('v.Status', [SopStatus::PAKET_PUBLISHED, 'OBSOLETE'])
            ->exists();
    }

    /**
     * Versi ini yang BERLAKU di paket tsb — bukan sekadar pernah ada di sana.
     *
     * versiVisibleInPaket menjawab "apakah versi ini milik paket ini", dan itu
     * memang benar untuk seluruh riwayatnya. Pertanyaan yang berbeda, dan yang
     * menentukan boleh-tidaknya ISI dibagikan, adalah "apakah versi ini yang
     * sedang berlaku". Dokumen terkendali yang sudah dicabut disimpan untuk
     * ditelusuri, bukan untuk dipakai.
     *
     * Aturannya dijaga sama dengan yang DISAJIKAN detailBundle: item ber-DOKUMEN
     * mengikuti pointer hidup, item tanpa dokumen tetap dilayani versi beku
     * snapshot. Menjawabnya dengan "Status = PUBLISHED" saja akan menolak item
     * tanpa dokumen yang sah dilayani dari rilis paket lama.
     */
    public function versiBerlakuDiPaket(int $idPaket, int $idVersi): bool
    {
        $live = DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket', $idPaket)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where('Id_Item_Versi_Aktif', $idVersi)
            ->exists();
        if ($live) {
            return true;
        }

        $snapshot = app(SopPaketVersionService::class)->releasedSnapshot($idPaket);
        if (! $snapshot) {
            return false;
        }

        foreach ($snapshot['items'] as $row) {
            if ((int) ($row['Id_SOP_Item_Versi'] ?? 0) !== $idVersi) {
                continue;
            }

            // Item ber-dokumen sudah dijawab pointer hidup di atas; sampai di
            // sini berarti versi bekunya memang sudah digantikan.
            $punyaDokumen = DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket_Item', (int) ($row['Id_SOP_Paket_Item'] ?? 0))
                ->whereNotNull('Id_SOP_Dokumen')
                ->exists();

            if (! $punyaDokumen) {
                return true;
            }
        }

        return false;
    }

    /* ================= SOP SAYA ================= */

    /** Kartu tugas + counts per tab. */
    public function tugasSaya(string $kode, string $tab): array
    {
        $details = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->get([
                'd.Id_SOP_Penjadwalan_Detail', 'd.Created_At as Waktu_Assign', 'd.Tanggal_Selesai', 'd.Updated_At as Detail_Updated_At',
                'pj.Tanggal_Assign', 'p.Id_SOP_Paket', 'p.Kode_Paket', 'p.Nama_Paket', 'p.Prioritas',
                'p.Deskripsi', 'p.Tujuan_Ruang_Lingkup', 'p.Tanggal_Publish', 'p.Versi_Paket_Aktif',
                'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',
                'p.Cover_Path', 'p.Estimasi_Waktu_Baca_Menit',
                // Menjawab "kenapa ini jadi tugas saya": ditugaskan admin, lahir
                // dari aturan otomatis, atau didaftar sendiri. Ikut di kueri yang
                // sudah ada, jadi tak menambah satu pun perjalanan ke database.
                'pj.Sumber',
            ]);

        if ($details->isEmpty()) {
            return ['counts' => ['perlu_dibaca' => 0, 'terlambat' => 0, 'perlu_ulang' => 0, 'diperbarui' => 0, 'selesai' => 0], 'items' => [], 'dokumen' => []];
        }

        // hitung progress semua detail sekaligus
        $detailIds = $details->pluck('Id_SOP_Penjadwalan_Detail')->all();
        $paketIds = $details->pluck('Id_SOP_Paket')->map(fn ($x) => (int) $x)->unique()->values()->all();

        // Komposisi item per paket (dokumen, kuis, tautan, video)
        $komposisiPerPaket = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket', $paketIds)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('Id_SOP_Paket', 'Tipe_Item')
            ->selectRaw('Id_SOP_Paket, Tipe_Item, COUNT(*) as jml')
            ->get()
            ->groupBy('Id_SOP_Paket')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($r) => [$r->Tipe_Item => (int) $r->jml])->all());

        // Kepemilikan & klasifikasi dokumen di dalam tiap paket. SATU kueri untuk
        // seluruh kartu — bukan satu per kartu.
        $identitas = $this->identitasDokumenPerPaket($paketIds);

        // detail yang pernah DIBUKA_LAGI (untuk tanda "Diperbarui")
        $reopenedIds = DB::table('N_SOP_Kepatuhan_Riwayat')
            ->whereIn('Id_SOP_Penjadwalan_Detail', $detailIds)
            ->where('Peristiwa', SopStatus::RWT_DIBUKA_LAGI)
            ->distinct()->pluck('Id_SOP_Penjadwalan_Detail')
            ->map(fn ($x) => (int) $x)->all();

        // item-driven: tiap item wajib dihitung sekali, dicocokkan ke progress versi aktif
        $prog = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Paket_Item as i', function ($j) {
                $j->on('i.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
                    ->where('i.Flag_Wajib', '=', SopStatus::AKTIF)
                    ->where('i.Flag_Aktif', '=', SopStatus::AKTIF);
            })
            ->leftJoin('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item')
                    ->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on(DB::raw('ISNULL(pr.Id_SOP_Item_Versi,-1)'), '=', DB::raw('ISNULL(i.Id_Item_Versi_Aktif,-1)'));
            })
            // "Perlu dibaca ulang": item wajib yang pernah dikonfirmasi orang ini
            // pada versi LAIN, sementara versi aktifnya belum. Dihitung di query
            // agregat yang sama — bukan satu query tambahan per kartu.
            ->leftJoin('N_SOP_Progress as lama', function ($j) {
                $j->on('lama.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item')
                    ->on('lama.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on(DB::raw('ISNULL(lama.Id_SOP_Item_Versi,-1)'), '<>', DB::raw('ISNULL(i.Id_Item_Versi_Aktif,-1)'))
                    ->whereIn('lama.Status', [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI]);
            })
            ->whereIn('d.Id_SOP_Penjadwalan_Detail', $detailIds)
            ->groupBy('d.Id_SOP_Penjadwalan_Detail')
            // Seluruh hitungan memakai COUNT(DISTINCT item): leftJoin ke `lama`
            // menggandakan baris bila seseorang pernah mengonfirmasi lebih dari
            // satu versi lama, dan SUM(CASE) akan ikut menggelembung diam-diam.
            ->selectRaw("d.Id_SOP_Penjadwalan_Detail as did, COUNT(DISTINCT i.Id_SOP_Paket_Item) as total,
                COUNT(DISTINCT CASE WHEN pr.Status IN ('SELESAI','DIKONFIRMASI') THEN i.Id_SOP_Paket_Item END) as selesai,
                COUNT(DISTINCT CASE WHEN lama.Id_SOP_Progress IS NOT NULL
                                     AND (pr.Status IS NULL OR pr.Status NOT IN ('SELESAI','DIKONFIRMASI'))
                                    THEN i.Id_SOP_Paket_Item END) as perlu_ulang,
                MAX(pr.Waktu_Buka) as Terakhir_Akses,
                MAX(pr.Waktu_Konfirmasi) as Terakhir_Konfirmasi")
            ->get()->keyBy('did');

        $today = Carbon::today();
        $counts = ['perlu_dibaca' => 0, 'terlambat' => 0, 'perlu_ulang' => 0, 'diperbarui' => 0, 'selesai' => 0];
        $all = [];
        // Kartu diindeks ulang per penugasan supaya baris dokumen bisa mewarisi
        // atributnya tanpa menghitung ulang tenggat & status untuk kedua kalinya.
        $kartuPerDetail = [];
        $coverSvc = new SopPaketService;

        foreach ($details as $d) {
            $p = $prog->get($d->Id_SOP_Penjadwalan_Detail);
            $total = (int) ($p->total ?? 0);
            $selesai = (int) ($p->selesai ?? 0);
            $persen = $total > 0 ? round($selesai / $total * 100) : 0;

            // status dihitung dari progress
            if ($total > 0 && $selesai >= $total) {
                $status = SopStatus::STATUS_SELESAI;
            } elseif ($selesai > 0) {
                $status = SopStatus::STATUS_PROGRESS;
            } else {
                $status = SopStatus::STATUS_BELUM;
            }

            $terlambat = $d->Tipe_Tenggat === 'DEADLINE'
                && $d->Tanggal_Deadline
                && Carbon::parse($d->Tanggal_Deadline)->lt($today)
                && $status !== SopStatus::STATUS_SELESAI;

            $diperbarui = in_array((int) $d->Id_SOP_Penjadwalan_Detail, $reopenedIds, true)
                && $status !== SopStatus::STATUS_SELESAI;

            /*
             * PERLU BACA ULANG — ember tersendiri, dihitung LANGSUNG.
             *
             * `$diperbarui` bersandar pada ledger DIBUKA_LAGI, yang ditulis
             * rekonsiliasi di pekerja latar; selama antreannya belum tersentuh
             * ia diam. `perlu_ulang` datang dari agregat yang sama yang mengisi
             * kartu (`Item_Perlu_Ulang`): item wajib yang pernah dituntaskan
             * orang ini pada versi LAIN sementara versi aktifnya belum. Ia
             * benar sejak detik revisi terbit, tanpa menunggu siapa pun.
             *
             * URUTANNYA DISENGAJA. Terlambat tetap menang — tenggat yang lewat
             * lebih mendesak daripada bacaan ulang. Tetapi baca-ulang menang
             * atas `perlu_dibaca`, sebab "belum pernah dibaca" dan "berubah
             * sesudah Anda menyelesaikannya" adalah dua pekerjaan yang berbeda,
             * dan menyatukannya membuat yang kedua tak pernah kelihatan.
             */
            $perluUlangKartu = (int) ($p->perlu_ulang ?? 0);

            // klasifikasi tab
            if ($status === SopStatus::STATUS_SELESAI) {
                $cat = 'selesai';
            } elseif ($terlambat) {
                $cat = 'terlambat';
            } elseif ($perluUlangKartu > 0) {
                $cat = 'perlu_ulang';
            } elseif ($diperbarui) {
                $cat = 'diperbarui';
            } else {
                $cat = 'perlu_dibaca';
            }
            $counts[$cat]++;

            $ident = $identitas[(int) $d->Id_SOP_Paket] ?? ['departemen' => [], 'jenis' => [], 'apqc' => []];
            $isi = $komposisiPerPaket[(int) $d->Id_SOP_Paket] ?? [];

            $kartu = [
                'Grup' => $cat,
                'Id_SOP_Paket' => $this->enc($d->Id_SOP_Paket),
                'Kode_Paket' => $d->Kode_Paket,
                'Nama_Paket' => $d->Nama_Paket,
                'Deskripsi' => $d->Deskripsi ?: $d->Tujuan_Ruang_Lingkup,
                'Tanggal_Publish' => $d->Tanggal_Publish ? Carbon::parse($d->Tanggal_Publish)->format('Y-m-d') : null,
                'Versi_Paket' => $d->Versi_Paket_Aktif !== null ? (int) $d->Versi_Paket_Aktif : 1,

                /*
                 * IDENTITAS DOKUMEN DI DALAM PAKET — bernilai JAMAK, dan itu
                 * bukan kelonggaran melainkan kenyataannya: kepemilikan melekat
                 * pada DOKUMEN, sementara baris ini adalah PAKET. Satu paket
                 * boleh memuat dokumen milik beberapa departemen sekaligus.
                 */
                'Departemen' => $ident['departemen'],
                'Jenis_Dokumen' => $ident['jenis'],
                'Apqc' => $ident['apqc'],
                'Sumber' => $d->Sumber,
                'Prioritas' => $d->Prioritas,
                'Status_Paket' => $status,
                'Total_Item' => $total,
                'Item_Selesai' => $selesai,
                'Isi' => $isi,
                'Total_Dokumen' => (int) ($isi['DOKUMEN'] ?? 0),
                'Total_Kuis' => (int) ($isi['KUIS'] ?? 0),
                // Berapa item yang harus dibaca ulang karena versinya berganti.
                // Menjawab pertanyaan yang tak dijawab flag Is_Diperbarui yang biner.
                'Item_Perlu_Ulang' => (int) ($p->perlu_ulang ?? 0),
                'Persen' => $persen,
                'Tenggat' => $d->Tipe_Tenggat === 'DEADLINE' && $d->Tanggal_Deadline
                                    ? Carbon::parse($d->Tanggal_Deadline)->format('Y-m-d') : null,
                'Is_Terlambat' => (bool) $terlambat,
                'Is_Diperbarui' => $diperbarui,
                'Cover_Url' => $coverSvc->coverUrl($d->Cover_Path ?? null),
                'Estimasi' => $d->Estimasi_Waktu_Baca_Menit ? (int) $d->Estimasi_Waktu_Baca_Menit : null,
                'Tanggal_Assign' => $d->Tanggal_Assign ? Carbon::parse($d->Tanggal_Assign)->format('Y-m-d H:i:s') : ($d->Waktu_Assign ? Carbon::parse($d->Waktu_Assign)->format('Y-m-d H:i:s') : null),
                'Tanggal_Selesai' => $d->Tanggal_Selesai ? Carbon::parse($d->Tanggal_Selesai)->format('Y-m-d H:i:s') : ($p && $p->Terakhir_Konfirmasi ? Carbon::parse($p->Terakhir_Konfirmasi)->format('Y-m-d H:i:s') : null),
                'Terakhir_Akses' => $p && $p->Terakhir_Akses ? Carbon::parse($p->Terakhir_Akses)->format('Y-m-d H:i:s') : null,
                'Terakhir_Konfirmasi' => $p && $p->Terakhir_Konfirmasi ? Carbon::parse($p->Terakhir_Konfirmasi)->format('Y-m-d H:i:s') : null,
                'Cta' => $status === SopStatus::STATUS_SELESAI ? 'Lihat'
                                    : ($status === SopStatus::STATUS_PROGRESS ? 'Lanjutkan' : 'Baca'),
            ];

            $all[] = $kartu;
            $kartuPerDetail[(int) $d->Id_SOP_Penjadwalan_Detail] = $kartu;
        }

        // kembalikan SEMUA item (tiap item bertanda 'Grup') — FE menyaring & mengurutkan client-side.
        // Param $tab dipertahankan untuk kompatibilitas: bila bukan 'all' & valid, tetap bisa menyaring.
        $items = ($tab && $tab !== 'all' && array_key_exists($tab, $counts))
            ? array_values(array_filter($all, fn ($x) => $x['Grup'] === $tab))
            : $all;

        $dokumen = $this->dokumenSaya($detailIds, $kartuPerDetail);
        if ($tab && $tab !== 'all' && array_key_exists($tab, $counts)) {
            $dokumen = array_values(array_filter($dokumen, fn ($x) => $x['Grup'] === $tab));
        }

        return ['counts' => $counts, 'items' => $items, 'dokumen' => $dokumen];
    }

    /**
     * Satu baris per ITEM yang ditugaskan — tampilan "Per Dokumen" di SOP Saya.
     *
     * BENTUKNYA SENGAJA MENYERUPAI KARTU PAKET. `Departemen`, `Jenis_Dokumen`,
     * dan `Apqc` tetap berupa daftar walau isinya selalu satu, dan tiap baris
     * membawa serta atribut paket induknya (Grup, Tenggat, Sumber, Prioritas).
     *
     * Itu bukan kerapian melainkan yang membuat layar tidak bercabang: seluruh
     * penyaringan dan pengelompokan di Shared/components/listing/sumbu.js bekerja apa adanya pada
     * baris ini. Bentuk yang berbeda akan menuntut sepasang cabang
     * `if (mode === 'dokumen')` di setiap saringan — dan sepasang cabang yang
     * lambat laun menyimpang satu sama lain.
     *
     * AKIBAT YANG BAGUS: satu dokumen punya tepat satu departemen, sehingga di
     * tampilan ini pengelompokan per departemen menjadi EKSAK — jumlah per
     * kelompok kembali menjumlah tepat ke jumlah baris, dan keterangan selisih
     * di rail hilang dengan sendirinya.
     *
     * @param  int[]  $detailIds
     * @param  array<int, array>  $kartuPerDetail  kartu paket, untuk diwarisi
     * @return array<int, array>
     */
    private function dokumenSaya(array $detailIds, array $kartuPerDetail): array
    {
        if ($detailIds === []) {
            return [];
        }

        $selesai = "'".SopStatus::ITEM_SELESAI."','".SopStatus::ITEM_DIKONFIRMASI."'";

        /*
         * "Perlu dibaca ulang" dihitung lewat EXISTS, BUKAN leftJoin ke progress
         * versi lama. Seseorang bisa pernah mengonfirmasi lebih dari satu versi
         * lama pada item yang sama; sebagai join, item itu akan muncul sebagai
         * dua baris dokumen — satu dokumen tampil dua kali di layar, tanpa satu
         * pun galat. Nilai di dalamnya seluruhnya konstanta SopStatus, jadi tak
         * ada binding yang bisa tergeser.
         */
        $perluUlang = "CASE WHEN EXISTS (
                SELECT 1 FROM N_SOP_Progress AS lama
                WHERE lama.Id_SOP_Paket_Item = i.Id_SOP_Paket_Item
                  AND lama.Id_SOP_Penjadwalan_Detail = d.Id_SOP_Penjadwalan_Detail
                  AND ISNULL(lama.Id_SOP_Item_Versi, -1) <> ISNULL(i.Id_Item_Versi_Aktif, -1)
                  AND lama.Status IN ({$selesai})
            ) AND (pr.Status IS NULL OR pr.Status NOT IN ({$selesai}))
            THEN 1 ELSE 0 END";

        $rows = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->leftJoin('N_SOP_Paket_Section as ps', 'ps.Id_SOP_Section', '=', 'i.Id_SOP_Section')
            // LEFT ke dokumen: item kuis dan tautan memang tak punya dokumen,
            // dan menghilangkannya dari daftar akan membuat tugas yang harus
            // dikerjakan tak pernah terlihat di tampilan ini.
            ->leftJoin('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'dok.Departemen_Id')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Master_APQC as ap', 'ap.Id_SOP_APQC', '=', 'dok.APQC_Id')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            // Progress pada VERSI AKTIF item ini, sama seperti hitungan kartu.
            ->leftJoin('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item')
                    ->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on(DB::raw('ISNULL(pr.Id_SOP_Item_Versi,-1)'), '=', DB::raw('ISNULL(i.Id_Item_Versi_Aktif,-1)'));
            })
            ->whereIn('d.Id_SOP_Penjadwalan_Detail', $detailIds)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('ps.Urutan')->orderBy('i.Urutan')->orderBy('i.Id_SOP_Paket_Item')
            ->selectRaw("d.Id_SOP_Penjadwalan_Detail AS did, i.Id_SOP_Paket_Item AS iid,
                i.Tipe_Item, i.Flag_Wajib, i.Urutan, i.Id_Item_Versi_Aktif,
                v.Versi_Ke, v.Tanggal_Berlaku, v.Status as Status_Versi, v.Nomor_Pengesahan, v.Disahkan_Oleh, v.Ringkasan_Perubahan,
                COALESCE(dok.Judul, i.Judul_Item) AS Judul,
                COALESCE(dok.Nomor_SOP_Resmi, i.Nomor_SOP_Resmi) AS Nomor_SOP,
                dok.No_Dokumen, ps.Nama_Section,
                dok.Departemen_Id, dpt.Nama_Departemen, dpt.Kode_Departemen,
                dok.Jenis_Dokumen_Id, jd.Nama_Jenis, jd.Kode_Jenis, jd.Level_Hirarki,
                dok.APQC_Id, ap.Nama_Proses, ap.Kode_Proses,
                pr.Status AS Status_Progress, pr.Waktu_Buka, pr.Waktu_Konfirmasi, pr.Skor_Quiz, pr.Status_Lulus,
                d.Tanggal_Selesai, pj.Tanggal_Assign, d.Created_At AS Waktu_Assign,
                {$perluUlang} AS Perlu_Ulang")
            ->get();

        $out = [];
        foreach ($rows as $r) {
            /*
             * Dikunci per (penugasan × item) — sebagai jaring, bukan sebagai
             * perbaikan.
             *
             * Penggandaan yang nyata datang dari progres versi LAMA: seseorang
             * bisa pernah mengonfirmasi beberapa versi lama pada item yang sama,
             * dan sebagai join itu melahirkan satu baris dokumen per versi.
             * Justru karena itu "perlu dibaca ulang" dihitung lewat EXISTS di
             * atas, bukan join.
             *
             * Dari `pr` sendiri penggandaan TIDAK bisa terjadi: N_SOP_Progress
             * memegang UNIQUE (Id_SOP_Penjadwalan_Detail, Id_SOP_Paket_Item,
             * Id_SOP_Item_Versi), jadi paling banyak satu baris cocok. Kunci ini
             * karena itu tak pernah menyala hari ini — ia hanya menahan kalau
             * kelak sebuah join baru ditambahkan tanpa disadari.
             */
            $kunci = $r->did.'-'.$r->iid;
            if (isset($out[$kunci])) {
                continue;
            }

            $induk = $kartuPerDetail[(int) $r->did] ?? null;
            if (! $induk) {
                continue;
            }

            $tuntas = in_array($r->Status_Progress, [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI], true);

            $satu = fn ($id, $nama, $level = null, $kode = null) => $id === null ? [] : [[
                'id' => (int) $id,
                'nama' => (string) ($nama ?? ''),
                'kode' => $kode ? (string) $kode : null,
                'level' => $level !== null ? (int) $level : null,
            ]];

            $out[$kunci] = [
                'Id_SOP_Paket_Item' => $this->enc($r->iid),
                'Id_SOP_Paket' => $induk['Id_SOP_Paket'],
                'Nama_Paket' => $induk['Nama_Paket'],
                'Kode_Paket' => $induk['Kode_Paket'] ?? null,

                'Judul' => $r->Judul,
                'Nomor_SOP' => $r->Nomor_SOP,
                'No_Dokumen' => $r->No_Dokumen !== null ? (int) $r->No_Dokumen : null,
                'Tipe_Item' => $r->Tipe_Item,
                'Wajib' => $r->Flag_Wajib === SopStatus::AKTIF,
                'Section' => $r->Nama_Section,
                'Urutan' => $r->Urutan !== null ? (int) $r->Urutan : null,
                // Item tanpa versi aktif: bukan sekadar kolom kosong melainkan
                // bacaan yang tak berdiri di atas versi sah mana pun.
                'Tanpa_Versi' => $r->Id_Item_Versi_Aktif === null,
                'Versi' => $r->Versi_Ke !== null ? 'v'.$r->Versi_Ke.'.0' : null,
                'Versi_Ke' => $r->Versi_Ke !== null ? (int) $r->Versi_Ke : null,
                'Tanggal_Berlaku' => $r->Tanggal_Berlaku ? Carbon::parse($r->Tanggal_Berlaku)->format('Y-m-d') : null,
                'Status_Versi' => $r->Status_Versi,
                'Nomor_Pengesahan' => $r->Nomor_Pengesahan,
                'Disahkan_Oleh' => $r->Disahkan_Oleh,
                'Ringkasan_Perubahan' => $r->Ringkasan_Perubahan,

                'Status_Item' => $r->Status_Progress ?: SopStatus::ITEM_BELUM,
                'Waktu_Buka' => $r->Waktu_Buka ? Carbon::parse($r->Waktu_Buka)->format('Y-m-d H:i:s') : null,
                'Waktu_Konfirmasi' => $r->Waktu_Konfirmasi ? Carbon::parse($r->Waktu_Konfirmasi)->format('Y-m-d H:i:s') : null,
                'Tanggal_Assign' => $r->Tanggal_Assign ? Carbon::parse($r->Tanggal_Assign)->format('Y-m-d H:i:s') : ($r->Waktu_Assign ? Carbon::parse($r->Waktu_Assign)->format('Y-m-d H:i:s') : ($induk['Tanggal_Assign'] ?? null)),
                'Tanggal_Selesai' => $r->Tanggal_Selesai ? Carbon::parse($r->Tanggal_Selesai)->format('Y-m-d H:i:s') : ($r->Waktu_Konfirmasi ? Carbon::parse($r->Waktu_Konfirmasi)->format('Y-m-d H:i:s') : null),
                'Skor_Quiz' => $r->Skor_Quiz !== null ? (float) $r->Skor_Quiz : null,
                'Status_Lulus' => $r->Status_Lulus,
                'Item_Perlu_Ulang' => (int) $r->Perlu_Ulang,

                'Departemen' => $satu($r->Departemen_Id, $r->Nama_Departemen, null, $r->Kode_Departemen),
                'Jenis_Dokumen' => $satu($r->Jenis_Dokumen_Id, $r->Nama_Jenis, $r->Level_Hirarki, $r->Kode_Jenis),
                'Apqc' => $satu($r->APQC_Id, $r->Nama_Proses, null, $r->Kode_Proses),

                /*
                 * Diwarisi dari kartu induk supaya saringan yang sama (tenggat,
                 * sumber, prioritas, tab status) berlaku di kedua tampilan.
                 * Total_Item/Item_Selesai sengaja 1 dan 0-atau-1: dengan itu
                 * bangunGrup() menghitung persen kelompok sebagai "berapa
                 * dokumen tuntas dari berapa dokumen", tanpa cabang khusus.
                 */
                'Grup' => $induk['Grup'],
                'Tenggat' => $induk['Tenggat'],
                'Is_Terlambat' => $induk['Is_Terlambat'],
                'Sumber' => $induk['Sumber'],
                'Prioritas' => $induk['Prioritas'],
                'Estimasi' => $induk['Estimasi'],
                'Total_Item' => 1,
                'Item_Selesai' => $tuntas ? 1 : 0,
                'Persen' => $tuntas ? 100 : 0,
            ];
        }

        return array_values($out);
    }

    /**
     * Kepemilikan & klasifikasi dokumen di dalam setiap paket.
     *
     * Alasan, kueri, dan aturan "Tanpa pemilik"-nya hidup di
     * SopIdentitasDokumenService — satu definisi yang juga dipakai Public
     * Document, supaya kedua halaman tak bisa menjawab beda.
     *
     * @param  int[]  $paketIds
     * @return array<int, array{departemen:array, jenis:array, apqc:array}>
     */
    private function identitasDokumenPerPaket(array $paketIds): array
    {
        return (new SopIdentitasDokumenService)->perPaket($paketIds);
    }

    /* ================= RIWAYAT ================= */

    /**
     * Timeline ledger kepatuhan karyawan.
     *
     * Dua hal yang membentuk bentuk kembaliannya:
     *
     * 1. Baris yang paketnya sudah tak ada di master DISARINGKAN. Tanpa paket,
     *    satu-satunya yang bisa ditampilkan adalah kata "Dicabut" tanpa objek —
     *    dan pada data development itu 133 dari 225 baris, seluruhnya puing uji
     *    beban yang penjadwalannya sudah dibersihkan tapi ledger-nya tertinggal.
     * 2. Yang disaring DILAPORKAN jumlahnya, tidak dibuang diam-diam. Timeline
     *    audit yang memotong tanpa berkata apa-apa lebih berbahaya daripada
     *    timeline yang berantakan: pembacanya menyangka sudah melihat semuanya.
     *
     * @return array{baris:\Illuminate\Support\Collection, tersembunyi:int, batas:int}
     */
    public function riwayat(string $kode): array
    {
        $batas = 100;

        $tersembunyi = (int) DB::table('N_SOP_Kepatuhan_Riwayat as r')
            ->leftJoin('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'r.Id_SOP_Paket')
            ->where('r.Kode_Karyawan', $kode)
            ->whereNull('p.Id_SOP_Paket')
            ->count();

        $baris = DB::table('N_SOP_Kepatuhan_Riwayat as r')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'r.Id_SOP_Paket')
            ->where('r.Kode_Karyawan', $kode)
            ->orderByDesc('r.Waktu_Peristiwa')
            ->limit($batas)
            ->get([
                'r.Waktu_Peristiwa', 'p.Nama_Paket', 'r.Peristiwa',
                'r.Alasan', 'r.Sebab', 'r.Catatan', 'r.Aktor_Nama', 'r.Persen_Saat_Itu',
            ])
            ->map(fn ($r) => [
                'Tanggal' => $r->Waktu_Peristiwa ? Carbon::parse($r->Waktu_Peristiwa)->format('Y-m-d H:i') : null,
                'Nama_Paket' => $r->Nama_Paket,
                'Peristiwa' => $r->Peristiwa,
                // `Alasan` = sebab yang ditulis mesin (terkunci CK_N_SOP_Rwt_Alasan),
                // `Sebab` = sebab yang dipilih manusia. Keduanya dikirim apa adanya;
                // UI yang memutuskan mana yang lebih menjelaskan.
                'Alasan' => $r->Alasan,
                'Sebab' => $r->Sebab,
                'Catatan' => $r->Catatan,
                'Aktor' => $r->Aktor_Nama,
                'Persen' => $r->Persen_Saat_Itu !== null ? (float) $r->Persen_Saat_Itu : null,
            ]);

        return ['baris' => $baris, 'tersembunyi' => $tersembunyi, 'batas' => $batas];
    }
}
