<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rincian kepatuhan SOP sampai tingkat ITEM.
 *
 * Satu baris = satu (karyawan × penugasan paket × item). Identitas orang dan
 * paket sengaja diulang di tiap baris: berkas ini dipakai untuk pivot dan
 * penyaringan di Excel, dan sel yang digabung membuat keduanya mustahil.
 *
 * Tiga keputusan yang menentukan apakah berkas ini jujur:
 *
 *  1. Join ke N_SOP_Progress adalah LEFT. Item yang belum pernah dibuka TIDAK
 *     punya baris progress; join inner akan menghapusnya dari berkas sehingga
 *     tunggakan lenyap dan kepatuhan terbaca lebih baik daripada kenyataannya.
 *     Baris seperti itu keluar berstatus BELUM.
 *
 *  2. Satu item bisa punya lebih dari satu rekam progress bila versinya pernah
 *     berganti. Baris jadi berlipat, dan itu memang yang diminta — berkas ini
 *     merekam apa yang dibaca orang, bukan cuma keadaan terakhirnya. Kolom
 *     `versi_dibaca` dan `perlu_ulang` yang membedakannya.
 *
 *  3. ORDER BY memakai kunci yang unik sampai ke baris progress. OFFSET/FETCH
 *     tanpa pemecah seri membuat SQL Server bebas mengurut ulang tiap potongan;
 *     akibatnya orang berpindah halaman dan sebagian tak pernah tertulis.
 *
 * Penafsiran saringan tidak disalin ke sini — ia dipinjam dari
 * SopMonitoringPeopleService supaya layar, estimasi, dan berkas tak bisa
 * berbeda pendapat.
 */
class SopExportRinciService
{
    public function __construct(private SopMonitoringPeopleService $people) {}

    /**
     * Kelompok kolom yang bisa dinyalakan/dimatikan admin.
     *
     * Berkas ini punya 38 kolom. Memaksa semuanya ikut membuat sebagian besar
     * export jadi lebih sulit dibaca daripada perlunya — orang yang cuma butuh
     * "siapa belum baca apa" tak butuh IP dan user agent. Kelompok dipilih di
     * layar; server yang menegakkannya, jadi berkasnya benar-benar mengecil,
     * bukan cuma disembunyikan kolomnya.
     */
    public const GRUP = [
        'identitas' => 'Identitas karyawan',
        'paket' => 'Paket & tenggat',
        'item' => 'Item & dokumen',
        'versi' => 'Versi & status baca',
        'baca' => 'Jejak waktu baca',
        'kuis' => 'Kuis',
        'atestasi' => 'Bukti atestasi',
    ];

    /**
     * Definisi kolom — satu sumber untuk berkas maupun daftar kolom di layar.
     * `width` dipakai penulis Excel; `center` hanya urusan tampilan.
     *
     * $grup kosong berarti semua kelompok ikut. Kolom `no` selalu ada: tanpa
     * nomor urut, berkas yang dipotong tak bisa disambung dengan berkas lain.
     */
    public function columns(array $grup = []): array
    {
        $semua = [
            ['key' => 'no', 'grup' => 'inti', 'label' => 'No', 'width' => 7, 'center' => true],

            ['key' => 'kode', 'grup' => 'identitas', 'label' => 'Kode Karyawan', 'width' => 14],
            ['key' => 'nama', 'grup' => 'identitas', 'label' => 'Nama Karyawan', 'width' => 26],
            ['key' => 'no_induk', 'grup' => 'identitas', 'label' => 'No. Induk', 'width' => 14],
            ['key' => 'lokasi', 'grup' => 'identitas', 'label' => 'Lokasi Kerja', 'width' => 16],
            ['key' => 'divisi', 'grup' => 'identitas', 'label' => 'Divisi', 'width' => 20],
            ['key' => 'departemen', 'grup' => 'identitas', 'label' => 'Departemen', 'width' => 20],
            ['key' => 'jabatan', 'grup' => 'identitas', 'label' => 'Jabatan', 'width' => 20],

            ['key' => 'paket', 'grup' => 'paket', 'label' => 'Nama Paket Dokumen', 'width' => 34],
            ['key' => 'kode_paket', 'grup' => 'paket', 'label' => 'Kode Paket', 'width' => 14],
            ['key' => 'prioritas', 'grup' => 'paket', 'label' => 'Prioritas', 'width' => 11, 'center' => true],
            ['key' => 'status_paket', 'grup' => 'paket', 'label' => 'Status Paket', 'width' => 13, 'center' => true],
            ['key' => 'tgl_assign', 'grup' => 'paket', 'label' => 'Tgl Assign', 'width' => 13, 'center' => true],
            ['key' => 'tenggat', 'grup' => 'paket', 'label' => 'Tenggat', 'width' => 15, 'center' => true],
            ['key' => 'terlambat', 'grup' => 'paket', 'label' => 'Terlambat', 'width' => 11, 'center' => true],
            ['key' => 'tgl_selesai_paket', 'grup' => 'paket', 'label' => 'Tgl Selesai Paket', 'width' => 18, 'center' => true],

            ['key' => 'section', 'grup' => 'item', 'label' => 'Section', 'width' => 22],
            ['key' => 'urutan', 'grup' => 'item', 'label' => 'Urutan', 'width' => 9, 'center' => true],
            ['key' => 'item', 'grup' => 'item', 'label' => 'Judul Item', 'width' => 36],
            ['key' => 'nomor_sop', 'grup' => 'item', 'label' => 'No. Dokumen Resmi', 'width' => 18],
            ['key' => 'tipe_item', 'grup' => 'item', 'label' => 'Tipe Item', 'width' => 12, 'center' => true],
            ['key' => 'wajib', 'grup' => 'item', 'label' => 'Wajib', 'width' => 9, 'center' => true],

            ['key' => 'versi_aktif', 'grup' => 'versi', 'label' => 'Versi Berlaku', 'width' => 13, 'center' => true],
            ['key' => 'versi_dibaca', 'grup' => 'versi', 'label' => 'Versi Dibaca', 'width' => 13, 'center' => true],
            ['key' => 'status_item', 'grup' => 'versi', 'label' => 'Status Item', 'width' => 15, 'center' => true],
            ['key' => 'perlu_ulang', 'grup' => 'versi', 'label' => 'Perlu Baca Ulang', 'width' => 17, 'center' => true],

            ['key' => 'persen_item', 'grup' => 'baca', 'label' => 'Progress Item', 'width' => 13, 'center' => true],
            ['key' => 'halaman_terakhir', 'grup' => 'baca', 'label' => 'Halaman Terakhir', 'width' => 16, 'center' => true],
            ['key' => 'durasi', 'grup' => 'baca', 'label' => 'Durasi Baca', 'width' => 13, 'center' => true],
            ['key' => 'durasi_detik', 'grup' => 'baca', 'label' => 'Durasi (detik)', 'width' => 13, 'center' => true],
            ['key' => 'waktu_buka', 'grup' => 'baca', 'label' => 'Waktu Buka', 'width' => 18, 'center' => true],
            ['key' => 'waktu_konfirmasi', 'grup' => 'baca', 'label' => 'Waktu Konfirmasi', 'width' => 18, 'center' => true],

            ['key' => 'skor_kuis', 'grup' => 'kuis', 'label' => 'Skor Kuis', 'width' => 11, 'center' => true],
            ['key' => 'percobaan', 'grup' => 'kuis', 'label' => 'Percobaan', 'width' => 11, 'center' => true],
            ['key' => 'lulus', 'grup' => 'kuis', 'label' => 'Lulus', 'width' => 10, 'center' => true],

            ['key' => 'ip', 'grup' => 'atestasi', 'label' => 'IP Konfirmasi', 'width' => 16],
            ['key' => 'perangkat', 'grup' => 'atestasi', 'label' => 'Perangkat', 'width' => 40],
        ];

        // Kelompok tak dikenal diabaikan, bukan bikin berkas kosong: permintaan
        // lama yang tersimpan di job tetap bisa dijalankan setelah daftar berubah.
        $pilih = array_values(array_intersect((array) $grup, array_keys(self::GRUP)));
        if (empty($pilih)) {
            return $semua;
        }

        return array_values(array_filter(
            $semua,
            fn ($c) => $c['grup'] === 'inti' || in_array($c['grup'], $pilih, true),
        ));
    }

    /** Berapa baris yang akan ditulis dengan saringan ini. */
    public function count(array $f): int
    {
        return $this->base($f)->count();
    }

    /**
     * Satu halaman baris siap tulis. Nomor urut melanjutkan halaman sebelumnya
     * supaya potongan-potongan export tetap tersambung sebagai satu berkas.
     */
    public function rows(array $f, int $page, int $limit): Collection
    {
        $offset = ($page - 1) * $limit;
        $hariIni = Carbon::today();

        $baris = $this->base($f)
            // Kunci urut harus unik sampai baris progress; lihat catatan kelas.
            ->orderBy('d.Kode_Karyawan')
            ->orderBy('d.Id_SOP_Penjadwalan_Detail')
            ->orderBy('i.Urutan')
            ->orderBy('i.Id_SOP_Paket_Item')
            ->orderBy('pr.Id_SOP_Progress')
            ->offset($offset)->limit($limit)
            ->get([
                'd.Kode_Karyawan', 'k.Nama', 'k.No_Induk_Karyawan', 'k.Cabang_Untuk_Gaji as Lokasi',
                'div.nama_divisi as Divisi', 'div.nama_sub_divisi as Departemen', 'lvl.nama_jabatan as Jabatan',

                'p.Nama_Paket', 'p.Kode_Paket', 'p.Prioritas',
                'd.Status_Paket', 'd.Tanggal_Selesai',
                'pj.Tanggal_Assign', 'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',

                'sec.Nama_Section', 'i.Urutan', 'i.Judul_Item', 'i.Nomor_SOP_Resmi',
                'i.Tipe_Item', 'i.Flag_Wajib', 'i.Id_Item_Versi_Aktif',

                'va.Versi_Ke as Versi_Aktif', 'vb.Versi_Ke as Versi_Dibaca',
                'pr.Id_SOP_Item_Versi', 'pr.Status as Status_Item', 'pr.Persen_Selesai',
                'pr.Halaman_Terakhir', 'pr.Durasi_Aktif_Detik', 'pr.Waktu_Buka', 'pr.Waktu_Konfirmasi',
                'pr.Skor_Quiz', 'pr.Jumlah_Percobaan', 'pr.Status_Lulus',
                'pr.Konfirmasi_IP', 'pr.Konfirmasi_User_Agent',
            ]);

        return $baris->values()->map(fn ($r, $i) => $this->susun($r, $offset + $i + 1, $hariIni));
    }

    /* ================= INTERNAL ================= */

    private function base(array $f)
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as div', 'k.ID_Divisi_Sub_Divisi', '=', 'div.ID_DIVISI_SUB_DIVISI')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as lvl', 'k.ID_Level_Jabatan', '=', 'lvl.ID_Level_Jabatan')
            ->join('N_SOP_Paket_Item as i', function ($j) {
                $j->on('i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
                    ->where('i.Flag_Aktif', '=', SopStatus::AKTIF);
            })
            ->leftJoin('N_SOP_Paket_Section as sec', 'sec.Id_SOP_Section', '=', 'i.Id_SOP_Section')
            ->leftJoin('N_SOP_Item_Versi as va', 'va.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            // LEFT: tanpa ini seluruh item yang belum dibuka lenyap dari berkas.
            ->leftJoin('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item');
            })
            ->leftJoin('N_SOP_Item_Versi as vb', 'vb.Id_SOP_Item_Versi', '=', 'pr.Id_SOP_Item_Versi')
            // Gerbang penugasan yang sama dengan seluruh layar monitoring:
            // 'T' pada Flag_Cabut_Akses berarti BELUM dicabut.
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', SopStatus::INAKTIF);

        $this->people->terapkanSaringanPaket($q, $f, 'p', 'pj', 'd');
        $this->people->terapkanStatus($q, (string) ($f['status'] ?? 'all'), 'd', 'pj');
        $this->people->terapkanSaringanOrangKaryawan($q, $f);

        // 'selesai' di layar berarti "orangnya tuntas semua"; di sini barisnya
        // per penugasan, jadi maknanya adalah penugasan yang sudah selesai.
        if (($f['status'] ?? 'all') === 'selesai') {
            $q->where('d.Status_Paket', SopStatus::STATUS_SELESAI);
        }

        if (! empty($f['kodes'])) {
            $q->whereIn('d.Kode_Karyawan', (array) $f['kodes']);
        }

        $cari = trim((string) ($f['q'] ?? ''));
        if (mb_strlen($cari) >= 2) {
            $q->where(function ($w) use ($cari) {
                $w->where('k.Nama', 'LIKE', "%{$cari}%")
                    ->orWhere('k.Kode_Karyawan', 'LIKE', "%{$cari}%")
                    ->orWhere('k.No_Induk_Karyawan', 'LIKE', "%{$cari}%");
            });
        }

        return $q;
    }

    /** Satu baris database → satu baris berkas, memakai kunci dari columns(). */
    private function susun(object $r, int $no, Carbon $hariIni): array
    {
        /*
         * '??' TIDAK menangkap string kosong.
         *
         * Konvensi berkas ini menuliskan '-' untuk nilai yang tak ada, dan
         * dipakai di dua puluhan field di bawah -- tapi lewat '??', sehingga
         * kolom yang berisi '' (bukan NULL) tetap keluar kosong. Akibatnya
         * berkas lama dan mesin Studio menuliskan sel yang sama secara
         * berbeda, dan harness regresi tidak pernah melihatnya karena
         * skenarionya kebetulan tak memuat satu pun nilai kosong.
         */
        $teks = fn ($v) => ($v === null || $v === '') ? '-' : $v;
        $tgl = fn ($t) => $t ? Carbon::parse($t)->format('Y-m-d') : '-';
        $stmp = fn ($t) => $t ? Carbon::parse($t)->format('Y-m-d H:i') : '-';
        $ya = fn ($b) => $b ? 'Ya' : 'Tidak';

        $tenggat = $r->Tipe_Tenggat === 'DEADLINE' && $r->Tanggal_Deadline
            ? Carbon::parse($r->Tanggal_Deadline)->format('Y-m-d')
            : 'Tanpa tenggat';

        $terlambat = $r->Tipe_Tenggat === 'DEADLINE'
            && $r->Tanggal_Deadline
            && Carbon::parse($r->Tanggal_Deadline)->lt($hariIni)
            && $r->Status_Paket !== SopStatus::STATUS_SELESAI;

        // Item tanpa rekam progress belum pernah disentuh — bukan data hilang.
        $statusItem = $r->Status_Item ?? SopStatus::ITEM_BELUM;

        // Sudah dikonfirmasi, tapi pada versi yang kini bukan lagi versi berlaku.
        $perluUlang = in_array($statusItem, [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI], true)
            && $r->Id_SOP_Item_Versi !== null
            && $r->Id_Item_Versi_Aktif !== null
            && (int) $r->Id_SOP_Item_Versi !== (int) $r->Id_Item_Versi_Aktif;

        $detik = (int) ($r->Durasi_Aktif_Detik ?? 0);

        return [
            'no' => $no,
            'kode' => $r->Kode_Karyawan,
            'nama' => $teks($r->Nama),
            'no_induk' => $teks($r->No_Induk_Karyawan),
            'lokasi' => $teks($r->Lokasi),
            'divisi' => $teks($r->Divisi),
            'departemen' => $teks($r->Departemen),
            'jabatan' => $teks($r->Jabatan),

            'paket' => $r->Nama_Paket,
            'kode_paket' => $teks($r->Kode_Paket),
            'prioritas' => $teks($r->Prioritas),
            'status_paket' => $r->Status_Paket,
            'tgl_assign' => $tgl($r->Tanggal_Assign),
            'tenggat' => $tenggat,
            'terlambat' => $ya($terlambat),
            'tgl_selesai_paket' => $stmp($r->Tanggal_Selesai),

            'section' => $teks($r->Nama_Section),
            'urutan' => (int) ($r->Urutan ?? 0),
            'item' => $teks($r->Judul_Item),
            'nomor_sop' => $teks($r->Nomor_SOP_Resmi),
            'tipe_item' => $teks($r->Tipe_Item),
            'wajib' => $ya($r->Flag_Wajib === SopStatus::AKTIF),

            'versi_aktif' => $r->Versi_Aktif !== null ? 'v'.(int) $r->Versi_Aktif : '-',
            'versi_dibaca' => $r->Versi_Dibaca !== null ? 'v'.(int) $r->Versi_Dibaca : '-',
            'status_item' => $statusItem,
            'perlu_ulang' => $ya($perluUlang),
            'persen_item' => $r->Persen_Selesai !== null ? (int) round((float) $r->Persen_Selesai).'%' : '-',
            'halaman_terakhir' => $r->Halaman_Terakhir !== null ? (int) $r->Halaman_Terakhir : '-',
            'durasi' => $detik > 0 ? $this->durasiRingkas($detik) : '-',
            'durasi_detik' => $detik,
            'waktu_buka' => $stmp($r->Waktu_Buka),
            'waktu_konfirmasi' => $stmp($r->Waktu_Konfirmasi),

            'skor_kuis' => $r->Skor_Quiz !== null ? (float) $r->Skor_Quiz : '-',
            'percobaan' => (int) ($r->Jumlah_Percobaan ?? 0),
            'lulus' => $teks($r->Status_Lulus),
            'ip' => $teks($r->Konfirmasi_IP),
            'perangkat' => $r->Konfirmasi_User_Agent ? mb_substr($r->Konfirmasi_User_Agent, 0, 120) : '-',
        ];
    }

    private function durasiRingkas(int $detik): string
    {
        if ($detik < 60) {
            return $detik.' dtk';
        }
        if ($detik < 3600) {
            return round($detik / 60).' mnt';
        }
        if ($detik < 86400) {
            return round($detik / 3600, 1).' jam';
        }

        return round($detik / 86400, 1).' hari';
    }
}
