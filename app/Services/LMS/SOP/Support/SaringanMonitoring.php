<?php

namespace App\Services\LMS\SOP\Support;

use App\Services\LMS\SOP\SopMonitoringPeopleService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Penafsiran saringan tab Ringkasan, dipakai bersama oleh seluruh service
 * analitik monitoring.
 *
 * Dulu isi berkas ini hidup sebagai method private di
 * SopMonitoringAnalyticsService. Begitu service agregat kedua lahir, menyalinnya
 * berarti satu tafsir "paket" per service — dan tafsir yang menyimpang tidak
 * pernah terlihat dari layar: dua panel bertetangga diam-diam bicara tentang
 * himpunan yang berbeda. Satu tempat, satu tafsir.
 *
 * Pemakai wajib punya properti `$people` berisi SopMonitoringPeopleService.
 *
 * Aturan MSSQL yang dipegang seluruh pemakainya:
 *  - `CAST(col AS DATE)` HANYA boleh muncul di GROUP BY, tak pernah di WHERE
 *    (di WHERE ia non-sargable dan memaksa scan).
 *  - Perbandingan waktu memakai rentang setengah terbuka.
 *  - `DATEDIFF` dihitung di SELECT lalu disaring di derived table, bukan
 *    dibandingkan langsung terhadap kolom berindeks.
 *
 * @property SopMonitoringPeopleService $people
 */
trait SaringanMonitoring
{
    /** Perlukah join ke N_SOP_Master_Paket — yakni adakah saringan berlingkup paket? */
    protected function perluPaket(array $f): bool
    {
        return ! empty($f['paket']) || ! empty($f['prioritas'])
            || (! empty($f['date_start']) && ! empty($f['date_end']))
            || ! empty($f['dari']) || ! empty($f['sampai']);
    }

    /** Adakah saringan aktif — dipakai untuk memutus cache, lihat mungkinCache(). */
    protected function tersaring(array $f): bool
    {
        return $this->perluPaket($f)
            || ! empty($f['dept']) || ! empty($f['divisi'])
            || ! empty($f['level']) || ! empty($f['jabatan']) || ! empty($f['lokasi'])
            || (! empty($f['date_start']) && ! empty($f['date_end']))
            || ! empty($f['dari']) || ! empty($f['sampai']);
    }

    /** Saringan yang BUKAN rentang tanggal maupun sumber — lihat mungkinCache(). */
    protected function tersaringSelainWaktu(array $f): bool
    {
        foreach (['paket', 'prioritas', 'dept', 'divisi', 'level', 'jabatan', 'lokasi'] as $k) {
            if (! empty($f[$k])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Imbuhan kunci cache untuk saringan yang JUMLAH KOMBINASINYA TERBATAS.
     *
     * Rentang tanggal datang dari enam chip siap pakai ditambah sesekali
     * rentang khusus, dan 'sumber' praktis cuma punya satu nilai. Keduanya
     * karena itu aman masuk kunci — berbeda dari paket × divisi ×
     * jabatan, yang perkaliannya memang meledak.
     */
    protected function imbuhanWaktu(array $f): string
    {
        $mulai = trim((string) ($f['date_start'] ?? ($f['dari'] ?? '')));
        $akhir = trim((string) ($f['date_end'] ?? ($f['sampai'] ?? '')));
        $mode = (string) ($f['date_mode'] ?? '');
        $sumber = $f['sumber'] ?? [];
        $sumber = is_array($sumber) ? implode('-', $sumber) : (string) $sumber;

        if ($mulai === '' && $akhir === '' && $sumber === '') {
            return '';
        }

        return '_'.md5($mulai.'|'.$akhir.'|'.$mode.'|'.$sumber);
    }

    /**
     * Jalankan lewat cache selama saringannya masih berkombinasi terbatas.
     *
     * DULU: cache dimatikan begitu ADA saringan apa pun, termasuk rentang
     * tanggal. Itu masuk akal selama halaman dibuka tanpa rentang — tetapi
     * sejak tab Ringkasan berbaku 30 hari terakhir, tiap kunjungan menjadi
     * "tersaring" dan cache tak pernah kena sama sekali: terukur 3.100 ms
     * setiap kali, dibanding 2.300 ms pada jalur hangat.
     *
     * Yang membuat kunci meledak bukan rentang tanggal melainkan saringan
     * organisasi — paket × divisi × jabatan. Rentang tanggal cuma
     * punya segelintir bentuk (enam chip + sesekali rentang khusus), jadi ia
     * dipindah dari "mematikan cache" menjadi "bagian dari kunci".
     *
     * 'sumber' IKUT MASUK KUNCI, dan itu perbaikan tersendiri: ia tak pernah
     * terhitung sebagai saringan di sini, sehingga hasil yang dihitung DENGAN
     * saringan sumber ditulis ke kunci yang berarti "tanpa saringan". Selama
     * semua pemanggil mengirim sumber yang sama hal itu tak terlihat; satu
     * pemanggil dengan sumber berbeda meracuni kunci bersama selama 10-15
     * menit. Itu benar-benar terjadi saat kartu heatmap tampil kosong.
     */
    protected function mungkinCache(string $kunci, int $detik, array $f, \Closure $fn)
    {
        if ($this->tersaringSelainWaktu($f)) {
            /* DULU di sini cache dilewati sama sekali, sehingga admin yang
               menyaring per divisi memicu agregat penuh pada SETIAP interaksi —
               di sekitar 25 endpoint monitoring. Kini disimpan dengan kunci
               sendiri dan TTL lebih pendek (300 dtk, separuh jalur tak
               tersaring) karena hasilnya lebih sempit dan lebih cepat basi.

               Saringan dinormalkan LEBIH DULU supaya bentuk yang ekuivalen
               (urutan pilihan berbeda, duplikat, nilai kosong) berbagi satu
               kunci. Tanpa itu kardinalitas kunci meledak — dan `cache` adalah
               tabel BERSAMA lintas modul, jadi yang meledak bukan cuma milik
               SOP. Pemangkasnya adalah `hris:cache-prune`. */
            return Cache::store('database')->remember(
                $kunci.':f'.$this->sidikSaringan($f),
                300,
                $fn,
            );
        }

        return Cache::store('database')->remember($kunci.$this->imbuhanWaktu($f), $detik, $fn);
    }

    /**
     * Sidik jari saringan yang stabil terhadap bentuk-bentuk yang ekuivalen.
     *
     * Normalisasinya bukan kerapian melainkan pengendali kardinalitas:
     * `['dept' => ['B','A']]` dan `['dept' => ['A','B','A'], 'kosong' => '']`
     * menyaring himpunan yang persis sama, jadi keduanya WAJIB memulangkan
     * sidik yang sama — kalau tidak, satu himpunan hasil ditulis berulang kali
     * di bawah kunci yang berbeda-beda.
     *
     * Sengaja TIDAK public: ia detail kunci cache, bukan bagian kontrak trait.
     */
    private function sidikSaringan(array $f): string
    {
        $bersih = [];
        foreach ($f as $k => $v) {
            if ($v === null || $v === '' || $v === []) {
                continue;                        // kosong = tak menyaring
            }
            if (is_array($v)) {
                $v = array_values(array_unique($v));
                sort($v);                        // urutan pilihan tak mengubah hasil
            }
            $bersih[$k] = $v;
        }
        ksort($bersih);

        return substr(md5((string) json_encode($bersih)), 0, 12);
    }

    /** Saringan untuk query yang berpusat di ledger kepatuhan (punya Id_SOP_Paket + Kode_Karyawan). */
    protected function saringLedger($q, array $f, string $r): void
    {
        if ($this->perluPaket($f)) {
            // Join baru dipasang saat memang dibutuhkan: Id_SOP_Paket pada ledger
            // boleh NULL, dan join tanpa syarat akan membuang baris-baris itu
            // diam-diam dari deret waktu.
            $q->join('N_SOP_Master_Paket as fp', 'fp.Id_SOP_Paket', '=', "{$r}.Id_SOP_Paket");
            $this->people->terapkanSaringanPaketRingkas($q, $f, 'fp');
        }
        $this->people->terapkanSaringanOrang($q, $f, $r);
    }

    /**
     * Saringan untuk query yang berpusat di penugasan (alias d + pj + p sudah ter-join).
     *
     * $d WAJIB alias N_SOP_Penjadwalan_Detail: saringan paket membaca
     * Status_Paket dan Tanggal_Selesai dari sana. Alias orang dipisah lewat
     * $orang karena query yang berpusat di N_SOP_Progress menyaring karyawan
     * dari baris progress, bukan dari penugasan — dulu keduanya dipaksa memakai
     * satu parameter, dan mengoper 'pr' agar saringan orang benar membuat
     * saringan paket menerbitkan pr.Status_Paket yang tak pernah ada.
     */
    protected function saringPenugasan($q, array $f, string $p = 'p', string $d = 'd', ?string $orang = null): void
    {
        $this->people->terapkanSaringanPaket($q, $f, $p, 'pj', $d);
        $this->people->terapkanSaringanOrang($q, $f, $orang ?? $d);
    }

    /**
     * Query penugasan aktif yang sudah tersaring, siap dilanjutkan pemanggil.
     * Join ke paket hanya dipasang saat saringan paket memang dipakai — join
     * yang tak perlu memaksa MSSQL membaca dua tabel tambahan pada tiap agregat.
     */
    protected function penugasanTersaring(array $f)
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d');

        if ($this->perluPaket($f)) {
            $q->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket');
            $this->people->terapkanSaringanPaket($q, $f, 'p', 'pj', 'd');
        }
        $this->people->terapkanSaringanOrang($q, $f, 'd');

        return $q;
    }

    /**
     * Saringan untuk query yang berpusat di N_SOP_Progress.
     *
     * Progress tak menjoin penjadwalan sama sekali, jadi paketnya dicapai lewat
     * item. Join itu hanya dipasang saat saringan paket dipakai — sama seperti
     * penugasanTersaring(), dan dengan alasan yang sama.
     */
    protected function saringProgress($q, array $f, string $pr = 'pr'): void
    {
        if ($this->perluPaket($f)) {
            $q->join('N_SOP_Paket_Item as fi', 'fi.Id_SOP_Paket_Item', '=', "{$pr}.Id_SOP_Paket_Item")
                ->join('N_SOP_Master_Paket as fp', 'fp.Id_SOP_Paket', '=', 'fi.Id_SOP_Paket');
            $this->people->terapkanSaringanPaketRingkas($q, $f, 'fp');
        }
        $this->people->terapkanSaringanOrang($q, $f, $pr);
    }
}
