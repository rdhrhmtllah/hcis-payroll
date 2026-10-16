<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\SopDocumentControlService;
use App\Services\LMS\SOP\SopExportRinciService;
use App\Services\LMS\SOP\SopPaketVersionService;
use App\Services\LMS\SOP\SopProgressReadService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Sidik jari keluaran baca SOP — bukti "nol regresi" untuk Fase 2.
 *
 * Fase 1 tak mengubah perilaku apa pun; Fase 2 memindahkan pembacaan versi ke
 * dokumen dan HARUS menghasilkan keluaran yang sama persis. Tanpa baseline yang
 * diambil SEBELUM perubahan, klaim itu tak bisa dibuktikan — hanya diyakini.
 *
 * Command ini sendiri tak menerbitkan satu pun INSERT/UPDATE/DELETE. Tapi
 * detailBundle() — layanan baca yang dipanggilnya — bisa menulis sebagai efek
 * samping: reconcileFinalQuizAttempts() menuntaskan progres kuis yang sudah
 * mentok percobaan tapi belum difinalkan (self-healing di jalur baca). Itu
 * sebabnya tesnya berjalan di dalam transaksi — supaya efek samping ini tak
 * pernah tersimpan permanen ke data dev.
 */
class SopDokumenBaseline extends Command
{
    protected $signature = 'sop:dokumen-baseline
        {--simpan= : Tulis baseline ke berkas JSON}
        {--bandingkan= : Bandingkan keadaan sekarang dengan berkas baseline}';

    protected $description = 'Ambil/bandingkan sidik jari keluaran baca SOP (baca-saja)';

    public function handle(
        SopProgressReadService $progres,
        SopDocumentControlService $kontrol,
        SopPaketVersionService $versiPaket,
        SopExportRinciService $export,
    ): int {
        $simpan = $this->option('simpan');
        $bandingkan = $this->option('bandingkan');

        if (! $simpan && ! $bandingkan) {
            $this->error('Wajib salah satu: --simpan= atau --bandingkan=');

            return self::FAILURE;
        }

        $sekarang = $this->ambil($progres, $kontrol, $versiPaket, $export);

        if ($simpan) {
            // Baseline harus hidup lebih lama dari Fase 1 (Fase 2 membandingkannya),
            // jadi ia ditulis ke lokasi ter-track — bukan storage/, yang di-gitignore.
            if (! is_dir(dirname($simpan))) {
                mkdir(dirname($simpan), 0775, true);
            }
            file_put_contents($simpan, json_encode($sekarang, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Baseline disimpan: '.$simpan);
            $this->line('Paket tercakup: '.count($sekarang['paket']));
            $this->line('Baris register kontrol dokumen tercakup: '.$sekarang['kontrol_register']['jumlah']);

            /* Cakupan kosong tak boleh menyamar jadi cakupan. Paket yang
               detailBundle()-nya null tetap punya sidik jari unik (dari
               'snapshot'), jadi pemeriksaan "hash-nya berbeda-beda" TAK akan
               melihatnya — persis pelajaran round 1 Task 4. Karena itu
               daftarnya dicetak, bukan disimpulkan. */
            $kosong = $sekarang['bundle_null'];
            if ($kosong) {
                $this->warn('Paket tanpa isi bundle (detailBundle mengembalikan null) — sidik jarinya '
                    .'hanya menutupi releasedSnapshot, BUKAN jalur baca karyawan: '.implode(', ', $kosong));
            } else {
                $this->line('Semua paket mengembalikan bundle (bundle_null kosong).');
            }

            return self::SUCCESS;
        }

        if (! is_file($bandingkan)) {
            $this->error('Berkas baseline tak ditemukan: '.$bandingkan);

            return self::FAILURE;
        }

        $lama = json_decode(file_get_contents($bandingkan), true);

        /*
         * Sejumlah nilai dalam sidik jari dihitung relatif terhadap "hari ini"
         * (Hari_Tersisa/Is_Terlambat, Keberlakuan per item, ember kadaluarsa di
         * ringkasan()). Baseline diambil hari X; pembandingan bisa berjalan
         * berminggu-minggu kemudian di hari Y — item yang melewati batas hari
         * akan berubah TANPA regresi nyata. Field ini SENGAJA tidak dibuang dari
         * hash (itu justru membutakan deteksi), tapi operator wajib diberi tahu
         * sumber selisih yang mungkin cuma pergeseran tanggal, bukan bug.
         */
        if (! empty($lama['diambil_pada'])) {
            $tanggalLama = Carbon::parse($lama['diambil_pada'])->toDateString();
            $tanggalSekarang = Carbon::parse($sekarang['diambil_pada'])->toDateString();
            if ($tanggalLama !== $tanggalSekarang) {
                $this->warn("Baseline diambil pada {$tanggalLama}; sebagian nilai dihitung relatif "
                    .'terhadap hari ini (sisa hari, masa berlaku, ember kadaluarsa), jadi perbedaan '
                    .'bisa berasal dari pergantian tanggal, bukan regresi.');
            }
        }

        /*
         * Paket tanpa penugasan (lazimnya DRAFT) dibaca lewat $kodeProbe —
         * karyawan pertama secara leksikografis, dipakai HANYA sebagai
         * identitas pemanggil (lihat ambil()). Bila roster berubah sehingga
         * karyawan pertama bergeser, probe-nya ikut bergeser, dan SEMUA paket
         * yang lewat jalur probe bisa ber-BERUBAH serentak — bukan regresi
         * Fase 2, cuma pergeseran identitas pemanggil. Tanpa sinyal ini,
         * operator tak bisa membedakan keduanya.
         */
        if (! empty($lama['probe_kode']) && ! empty($sekarang['probe_kode']) && $lama['probe_kode'] !== $sekarang['probe_kode']) {
            $paketTerdampak = array_values(array_unique(array_merge(
                $lama['via_probe'] ?? [],
                $sekarang['via_probe'] ?? [],
            )));
            $this->warn("Identitas probe berubah dari {$lama['probe_kode']} menjadi {$sekarang['probe_kode']} "
                .'(karyawan pertama secara leksikografis bergeser, mis. ada penambahan/penghapusan roster). '
                .'Paket tanpa penugasan dibaca lewat identitas ini, jadi perbedaan pada paket berikut bisa '
                .'berasal dari pergeseran probe, bukan regresi: '.(implode(', ', $paketTerdampak) ?: '(tak ada)'));
        }

        /*
         * Sumbu ketiga yang bisa menggeser sidik jari tanpa ada regresi:
         * identitas pemanggil per paket. detailBundle() dibaca atas nama
         * MIN(Kode_Karyawan) yang ditugaskan; menugaskan/mencabut seseorang
         * yang kebetulan berada di urutan paling awal mengganti pemanggilnya,
         * dan isi bundle memang seharusnya ikut berubah. Sama seperti
         * probe_kode, ini diperingatkan — tidak dibuang dari hash.
         */
        $geser = [];
        foreach ($sekarang['kode_per_paket'] ?? [] as $id => $kode) {
            $kodeLama = $lama['kode_per_paket'][$id] ?? null;
            if ($kodeLama !== null && $kodeLama !== $kode) {
                $geser[] = "{$id}: {$kodeLama} → {$kode}";
            }
        }
        if ($geser) {
            $this->warn('Identitas pemanggil bergeser pada paket berikut (penugasan ditambah/dicabut), '
                .'jadi perbedaan di sana bisa bukan regresi: '.implode('; ', $geser));
        }

        /*
         * Urutan baris register memang tak deterministik — ORDER BY-nya
         * (pengesah kosong dulu, lalu nama paket & judul item) bisa berbagi
         * nilai untuk banyak baris sekaligus. Karena itu urutannya
         * DIKELUARKAN dari hash (lihat ambil()) dan diperlakukan sebagai
         * peringatan saja: menjadikannya kegagalan hanya akan melahirkan
         * "BERUBAH" palsu setiap kali rencana kuerinya bergeser.
         */
        if (($lama['kontrol_register']['urutan'] ?? null) !== ($sekarang['kontrol_register']['urutan'] ?? null)) {
            $this->warn('Urutan baris register kontrol dokumen bergeser. ORDER BY-nya tak punya pemecah seri, '
                .'jadi ini BUKAN kegagalan — tapi bila Fase 2 memang menjanjikan urutan stabil, periksa manual.');
        }

        $beda = [];

        foreach ($sekarang['paket'] as $id => $baris) {
            $sebelum = $lama['paket'][$id]['sidik'] ?? null;
            if ($sebelum !== $baris['sidik']) {
                $beda[] = [$id, $baris['nama'], $sebelum ? 'BERUBAH' : 'BARU'];
            }
        }
        foreach (array_keys($lama['paket'] ?? []) as $id) {
            if (! isset($sekarang['paket'][$id])) {
                $beda[] = [$id, '(hilang)', 'HILANG'];
            }
        }

        if ($lama['kontrol_dokumen'] !== $sekarang['kontrol_dokumen']) {
            $beda[] = ['-', 'Ringkasan kontrol dokumen (8 angka)', 'BERUBAH'];
        }

        // Isi register, bukan cuma agregatnya. Baris yang bertukar paket, satu
        // hilang & satu ganda di ember yang sama, atau Hashid salah-encode —
        // semuanya mempertahankan kedelapan angka ringkasan dan akan lolos
        // sebagai "nol regresi" bila hanya ringkasan yang dibandingkan.
        $registerLama = $lama['kontrol_register']['sidik'] ?? null;
        if ($registerLama === null) {
            $this->warn('Berkas baseline belum memuat sidik jari isi register (dibuat sebelum cakupan itu ada) — '
                .'ambil ulang baseline sebelum Fase 2, kalau tidak jalur baca paling terdampak tak terlindungi.');
            $beda[] = ['-', 'Isi register kontrol dokumen', 'BELUM ADA DI BASELINE'];
        } elseif ($registerLama !== $sekarang['kontrol_register']['sidik']) {
            $beda[] = ['-', 'Isi register kontrol dokumen', 'BERUBAH'];
        }

        if (! $beda) {
            $this->info('Identik dengan baseline — nol regresi.');

            return self::SUCCESS;
        }

        $this->error('Ada '.count($beda).' perbedaan dari baseline:');
        $this->table(['Paket', 'Nama', 'Status'], $beda);

        return self::FAILURE;
    }

    /** @return array{diambil_pada: string, probe_kode: ?string, via_probe: array<int,string>, kode_per_paket: array<string,?string>, bundle_null: array<int,string>, paket: array<string,array{nama:string,sidik:string}>, kontrol_dokumen: array, kontrol_register: array{jumlah:int,total:int,sidik:string,urutan:array<int,string>}} */
    private function ambil(
        SopProgressReadService $progres,
        SopDocumentControlService $kontrol,
        SopPaketVersionService $versiPaket,
        SopExportRinciService $export,
    ): array {
        /* Buang cache dulu. ringkasan() menyimpan hasilnya 600 detik; tanpa ini
           perbandingan sebelum/sesudah membandingkan dua nilai cache yang sama
           dan melaporkan "identik" tanpa pernah memeriksa apa pun. */
        Cache::store('database')->forget(SopDocumentControlService::kunciRingkasan());

        $hasil = [
            'diambil_pada' => now()->toIso8601String(),
            'probe_kode' => null,
            'via_probe' => [],
            'kode_per_paket' => [],
            'bundle_null' => [],
            'paket' => [],
            'kontrol_dokumen' => $kontrol->ringkasan(),
            'kontrol_register' => $this->sidikRegister($kontrol),
        ];

        $paketList = DB::table('N_SOP_Master_Paket')
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Id_SOP_Paket')
            ->get(['Id_SOP_Paket', 'Nama_Paket']);

        // Probe identity utk paket yang tak punya penugasan sama sekali (mis.
        // DRAFT — belum ada yang ditugaskan konten yang belum terbit). Dipakai
        // HANYA sebagai identitas pemanggil detailBundle(); progres pribadinya
        // akan kosong secara sah (bypassGate:true membaca isi paket, bukan
        // menyamar sebagai penugasan yang tak ada). Direkam di $hasil supaya
        // --bandingkan bisa memperingatkan bila probe ini bergeser nanti
        // (lihat peringatan di handle()) — pergeseran roster tak boleh
        // menyamar sebagai regresi Fase 2.
        $kodeProbe = DB::table('Karyawan')->orderBy('Kode_Karyawan')->value('Kode_Karyawan');
        $hasil['probe_kode'] = $kodeProbe;

        foreach ($paketList as $p) {
            $idPaket = (int) $p->Id_SOP_Paket;

            // Karyawan yang benar-benar ditugaskan (bila ada) — supaya
            // detailBundle menempuh jalur nyata penugasan, bukan jalur kosong.
            $kode = DB::table('N_SOP_Penjadwalan as pj')
                ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan', '=', 'pj.Id_SOP_Penjadwalan')
                ->where('pj.Id_SOP_Paket', $idPaket)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->min('d.Kode_Karyawan');

            /*
             * bypassGate HANYA saat pemanggilnya memang TAK lolos gerbang.
             * detailBundle() sendiri
             * memakai bypassGate utk memutuskan apakah menempuh jalur
             * SNAPSHOT RILIS RESMI (SopProgressReadService.php:149 —
             * `$releaseSnapshot = !$bypassGate ? releasedSnapshot(...) : null`)
             * atau jalur tabel aktif. Karyawan yang BENAR ditugaskan pada
             * paket PUBLISHED selalu lolos paketBolehDiakses() tanpa bypass —
             * itulah jalur yang sungguh ditempuh pembaca sungguhan, dan itu
             * pula jalur yang membaca releasedSnapshot(). Mem-bypass secara
             * tanpa syarat (seperti sebelumnya) memaksa $releaseSnapshot null
             * utk SEMUA paket — termasuk yang PUBLISHED dan punya rilis resmi
             * — sehingga cabang snapshot (152-172 & 224-255) tak pernah
             * tereksekusi lewat command ini. Fingerprint tetap kelihatan sehat
             * (isinya tetap nyata, dari tabel aktif) TAPI jalur kode yang
             * dites keliru — lubang cakupan yang sama muncul lagi lewat sumbu
             * lain.
             *
             * SYARATNYA BUKAN `$kode === null`. `$kode` hanya menyaring
             * d.Flag_Aktif, sedangkan paketBolehDiakses() menuntut lebih:
             * Status PUBLISHED, Flag_Aktif paket, dan — untuk paket TERBATAS —
             * Flag_Cabut_Akses='T' pada penugasan orang itu. Paket ARCHIVED
             * yang masih memegang penugasan aktif, atau paket TERBATAS yang
             * penerima pertamanya dicabut aksesnya, menghasilkan $kode BUKAN
             * null tapi gerbangnya MENOLAK → detailBundle() mengembalikan null
             * → sidik jarinya diam-diam menciut jadi snapshot saja. Ia tetap
             * unik, jadi pemeriksaan "hash berbeda-beda" buta terhadapnya.
             * Menanyakan langsung ke gerbangnya menutup sumbu ketiga itu.
             * (paketBolehDiakses() memang public — dipakai apa adanya, bukan
             * disalin, supaya tak ada versi kedua dari aturan akses.)
             */
            $pemanggil = $kode ?: $kodeProbe;
            if ($pemanggil && $kode === null) {
                $hasil['via_probe'][] = (string) $idPaket;
            }

            $boleh = $pemanggil !== null && $progres->paketBolehDiakses($pemanggil, $idPaket);
            $bundle = $pemanggil ? $progres->detailBundle($pemanggil, $idPaket, bypassGate: ! $boleh) : null;

            // Identitas pemanggil & cakupan kosong dicatat DI LUAR hash: keduanya
            // bukan keluaran baca yang dijaga Fase 2, melainkan konteks yang
            // memberi tahu operator kenapa sebuah sidik jari bergeser (atau
            // kenapa ia tak menutupi apa-apa).
            $hasil['kode_per_paket'][(string) $idPaket] = $pemanggil;
            if ($bundle === null) {
                $hasil['bundle_null'][] = (string) $idPaket;
            }

            $bahan = [
                'bundle' => $bundle,
                'snapshot' => $versiPaket->releasedSnapshot($idPaket),
            ];

            /* detailBundle() menyertakan Cover_Url — signed URL GCS berumur 20
               menit (Storage::disk('gcs')->temporaryUrl()) yang ditanda-tangani
               ULANG setiap panggilan. Expires & Signature-nya SELALU beda meski
               path gambarnya sama persis, jadi tanpa normalisasi ini sidik jari
               tak akan pernah identik untuk paket manapun yang punya cover —
               positif palsu "berubah" padahal isinya tak berubah sama sekali.
               Hanya path yang berarti utk deteksi regresi; query signed dibuang. */
            if (! empty($bahan['bundle']['paket']['Cover_Url'])) {
                $bahan['bundle']['paket']['Cover_Url'] = strtok($bahan['bundle']['paket']['Cover_Url'], '?');
            }

            $hasil['paket'][(string) $idPaket] = [
                'nama' => $p->Nama_Paket,
                'sidik' => hash('sha256', json_encode($bahan, JSON_UNESCAPED_UNICODE)),
            ];
        }

        /* EXPORT RINCI — sengaja ditunda dari Fase 1, wajib ada di Fase 2.
           base() men-join `va` LEWAT i.Id_Item_Versi_Aktif (SopExportRinciService
           :188), yaitu pointer yang Fase 1 tak sentuh sama sekali — mustahil
           bergeser di sana. Fase 2 memindahkan sumbu penomoran & pembacaan versi
           ke dokumen, dan itulah yang bisa menggesernya. Baseline pra-perubahan
           hanya bisa diambil sebelum perubahan.

           CAKUPAN PENUH, bukan halaman pertama. Percobaan pertama memakai
           limit tetap 500 dan langsung memicu peringatan plafonnya sendiri:
           916 baris di dev, jadi sidiknya cuma melindungi 55% keluaran —
           regresi apa pun di 45% sisanya akan dilaporkan "Identik. Nol
           regresi." Limitnya karena itu mengikuti jumlah baris sebenarnya,
           dengan plafon keras sebagai rem terakhir.

           Jumlah total tetap dicatat TERPISAH di luar hash: kalau ia ikut
           di-hash, pertumbuhan data biasa akan menyamar jadi regresi; kalau ia
           tak dicatat sama sekali, penyusutan diam-diam tak terlihat.

           URUTAN DINORMALISASI sebelum di-hash — alasan yang sama persis dengan
           sidikRegister(): ORDER BY di hilir bisa bergeser mengikuti rencana
           kueri (dan Fase 2 memang mengubah join-nya), sehingga mengunci urutan
           ke dalam hash akan memproduksi "BERUBAH" palsu. */
        /* Plafon keras: rem terakhir supaya baseline tak pernah mencoba menarik
           seluruh tabel ke memori bila data tumbuh berkali lipat. Bila ia
           tersentuh, cakupannya BERKURANG dan itu dilaporkan keras-keras —
           tidak boleh lolos sebagai "sudah tercakup". */
        $plafon = 5000;
        $filter = [];
        $jumlahExport = $export->count($filter);
        $barisExport = $export->rows($filter, 1, min(max($jumlahExport, 1), $plafon));

        $hasil['export_rinci'] = [
            'jumlah' => $jumlahExport,
            'tercakup' => $barisExport->count(),
            // columns() mengembalikan LIST of ['key' => ...], bukan array
            // asosiatif — array_keys() di sini akan menghasilkan 0,1,2,...
            'kolom' => array_column($export->columns(), 'key'),
            'sidik' => hash('sha256', json_encode(
                $barisExport
                    ->map(fn ($r) => (array) $r)
                    ->sortBy(fn ($r) => json_encode($r, JSON_UNESCAPED_UNICODE))
                    ->values()
                    ->all(),
                JSON_UNESCAPED_UNICODE
            )),
        ];

        if ($jumlahExport > $plafon) {
            $this->warn("Export rinci: {$jumlahExport} baris melampaui plafon {$plafon} — sidiknya hanya "
                ."menutupi {$plafon} baris pertama. Regresi di sisanya akan dilaporkan sebagai "
                .'Identik. Naikkan plafon sebelum mengandalkannya sebagai bukti nol regresi.');
        }

        return $hasil;
    }

    /**
     * Sidik jari ISI register kontrol dokumen — bukan cuma kedelapan angka
     * ringkasannya.
     *
     * KENAPA WAJIB. register() adalah jalur baca yang paling erat terikat pada
     * join yang ditulis ulang Fase 2: baseVersiAktif() men-join item↔versi DUA
     * KALI (Id_SOP_Paket_Item DAN Id_Item_Versi_Aktif). Regresi yang menukar
     * baris antar paket, menghilangkan satu sambil menggandakan satu lagi di
     * ember yang sama, atau salah meng-encode Hashid, mempertahankan kedelapan
     * agregat itu utuh — dan akan dilaporkan "Identik. Nol regresi."
     *
     * NORMALISASI URUTAN, dan hanya itu. ORDER BY register() mendahulukan
     * versi tanpa pengesah, lalu nama paket & judul item — banyak baris bisa
     * berbagi nilai yang sama, sehingga urutan di antara mereka ditentukan
     * rencana kueri, dan perubahan join apa pun bisa menggesernya. Mengunci urutan ke
     * dalam hash akan memproduksi "BERUBAH" palsu; urutannya karena itu
     * disortir stabil sebelum di-hash (sama persis semangatnya dengan
     * pembuangan query signed pada Cover_Url: yang non-deterministik dibuang,
     * yang bermakna dipertahankan). Urutan aslinya TIDAK hilang — ia disimpan
     * di 'urutan' dan dilaporkan sebagai PERINGATAN oleh --bandingkan.
     *
     * Sisanya sudah deterministik: tanggal sudah diformat Y-m-d oleh
     * lekatkanDampakDanEmber(), id sudah Hashid (fungsi murni), ember berupa
     * array dengan urutan tetap. Ambang 30 = default halaman monitoring; limit
     * 500 > 28 baris hari ini, dan 'jumlah' vs 'total' di bawah membuat
     * pemotongan halaman ketahuan alih-alih senyap.
     *
     * @return array{jumlah:int,total:int,sidik:string,urutan:array<int,string>}
     */
    private function sidikRegister(SopDocumentControlService $kontrol): array
    {
        [$baris, $total] = $kontrol->register([], 1, 500);

        // Pembatasan apa pun wajib dilaporkan, tak boleh senyap: kalau register
        // sudah melewati satu halaman, sidik jari ini cuma menutupi 500 pertama.
        if ($baris->count() < (int) $total) {
            $this->warn("Register kontrol dokumen punya {$total} baris tapi sidik jari hanya menutupi "
                .$baris->count().' (satu halaman). Naikkan limit di sidikRegister() sebelum Fase 2.');
        }

        $urutan = $baris->pluck('Id_SOP_Item_Versi')->map(fn ($v) => (string) $v)->values()->all();
        $stabil = $baris->sortBy(fn ($r) => (string) $r->Id_SOP_Item_Versi)->values()->all();

        return [
            'jumlah' => count($stabil),
            'total' => (int) $total,
            'sidik' => hash('sha256', json_encode($stabil, JSON_UNESCAPED_UNICODE)),
            'urutan' => $urutan,
        ];
    }
}
