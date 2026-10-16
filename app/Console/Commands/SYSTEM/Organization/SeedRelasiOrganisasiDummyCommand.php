<?php

namespace App\Console\Commands\SYSTEM\Organization;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi relasi organisasi dengan struktur contoh, supaya alur Performance Appraisal bisa
 * dijalankan utuh sebelum struktur sungguhan diputuskan bersama HC.
 *
 * KENAPA INI DIBUTUHKAN
 *
 * `PaAssignmentBuilderService` menurunkan calon penilai dari `N_HRIS_Organization_Relationship`.
 * Selama tabelnya kosong, usulan ATASAN, PEER, dan BAWAHAN selalu nihil dan hanya penilaian
 * diri yang bisa terbentuk — pada siklus berjalan itu terlihat sebagai 859 peserta dengan nol
 * penugasan. Tidak ada satu pun bagian alurnya yang bisa dinilai benar atau salah dalam
 * keadaan itu.
 *
 * KENAPA PERINTAH, BUKAN DML
 *
 * Ini data CONTOH. DML akan ikut antre di SQL Tracker dan kelak dijalankan di produksi bersama
 * perubahan lain — membawa atasan palsu ke sistem yang dipakai orang sungguhan. Relasi yang
 * salah menghasilkan orang dinilai oleh atasan yang keliru, dengan hasil yang tetap terlihat
 * wajar. Perintah tinggal dijalankan di dev, bisa diulang, bisa dibersihkan.
 *
 * PENOLAKAN DI PRODUCTION ADA DI KODE
 *
 * Bukan di setelan. Setelan bisa keliru tersalin antar lingkungan; pemeriksaan lingkungan tidak
 * bisa.
 *
 * YANG TIDAK PERNAH DISENTUHNYA
 *
 * Baris yang dibuat orang lewat layar. Pembedanya `Created_By`: perintah ini menulis dengan
 * `Created_By` NULL dan `Sumber_Data = 'DUMMY_DEV'`, dan `--bersihkan` menuntut KEDUANYA.
 * Pembersihan yang hanya melihat "semua baris yang ada" pernah menghapus pekerjaan sungguhan di
 * basis data ini, jadi syaratnya dua, bukan satu.
 *
 * BENTUKNYA POHON BERBATAS RENTANG KENDALI
 *
 * Percobaan pertama memasangkan tiap tingkat jabatan ke tingkat tepat di atasnya, dan itu
 * meledak di tempat yang tingkat atasnya kecil: PRODUCTION punya 1 SENIOR STAFF di atas 63
 * STAFF, jadi satu orang mendapat 63 bawahan. Puncak sub-divisi lalu disambungkan ke satu apex,
 * dan apex itu mendapat 224. Angka seperti itu bukan struktur yang bisa dipakai menguji apa pun
 * — daftar calon penilai BAWAHAN-nya menjadi 224 nama.
 *
 * Sekarang satu mekanisme untuk keduanya: dalam setiap sub-divisi, orang diurutkan dari jabatan
 * tertinggi lalu dibentuk pohon dengan rentang kendali terbatas,
 * `atasan(i) = orang[(i - 1) div rentang]`. Karena indeks orang tua selalu lebih kecil,
 * jabatannya selalu lebih tinggi atau sama, dan lingkaran atasan-bawahan tidak mungkin
 * terbentuk. Akar tiap sub-divisi kemudian dipohonkan dengan cara yang sama, sehingga hanya
 * SATU orang di seluruh perusahaan yang tidak punya atasan.
 */
class SeedRelasiOrganisasiDummyCommand extends Command
{
    protected $signature = 'organisasi:seed-relasi-dummy
        {--bersihkan : Hapus HANYA baris contoh (Sumber_Data DUMMY_DEV dan Created_By NULL), tanpa mengisi}
        {--dry-run : Hitung dan laporkan saja, tidak menulis apa pun}
        {--rentang=8 : Rentang kendali — maksimum bawahan langsung per atasan}
        {--peer=5 : Besar kelompok rekan kerja. Jaringnya kuadratik: 5 orang = 20 baris, 25 = 600}
        {--tanpa-peer : Hanya atasan/bawahan, tanpa relasi rekan kerja}
        {--berlaku-dari= : Tanggal mulai berlaku. Baku: tanggal acuan organisasi siklus PA paling awal}
        {--lengkapi : Tambah relasi ekstra sampai minimum kebijakan penilai terpenuhi}';

    protected $description = 'Mengisi relasi organisasi dengan struktur contoh (dev saja).';

    private const SUMBER = 'DUMMY_DEV';

    /** Level FREELANCE. Tidak dinilai PA, jadi relasinya inert — tetap dibuat, tetapi dilaporkan. */
    private const LEVEL_FREELANCE = 21;

    /** @var array<int, array<string, mixed>> */
    private array $baris = [];

    /** @var array<string, string> Pasangan hierarkis yang sudah ada, TANPA arah. */
    private array $adaPasangan = [];

    private int $lewatSudahAda = 0;

    private int $lewatBentrok = 0;

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('Perintah ini menulis data contoh dan TIDAK boleh berjalan di production.');

            return self::FAILURE;
        }

        if ($this->option('bersihkan')) {
            return $this->bersihkan();
        }

        $rentang = max(1, (int) $this->option('rentang'));
        $besarPeer = max(2, (int) $this->option('peer'));

        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')
            ->where('Flag_Aktif', 'Y')
            ->pluck('Id_Organization_Relationship_Type', 'Kode_Relasi')
            ->all();

        foreach (['ATASAN', 'BAWAHAN', 'PEER'] as $k) {
            if (! isset($tipe[$k])) {
                $this->error("Jenis relasi '{$k}' tidak ada atau tidak aktif di master.");

                return self::FAILURE;
            }
        }

        $berlaku = $this->tanggalBerlaku();

        /* Satu kueri untuk seluruh populasi. Menanyakannya per orang berarti 958 perjalanan ke
           database sebelum satu baris pun ditulis. */
        $karyawan = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->leftJoin('HRIS_Level as l', 'l.ID_Level', '=', 'v.ID_Level')
            ->where('k.Aktif', 'Y')
            ->get([
                'k.Kode_Perusahaan as pt',
                'k.Kode_Karyawan as kode',
                'vd.nama_sub_divisi as dept',
                'v.ID_Level as lvl',
                'l.Level_Hierarchy as h',
            ]);

        $this->info("Karyawan aktif        : {$karyawan->count()}");
        $this->line("Berlaku dari          : {$berlaku}");
        $this->line("Rentang kendali       : {$rentang} bawahan langsung per atasan");

        $adaArah = $this->bacaRelasiYangAda();
        $this->line('Relasi yang sudah ada : '.count($adaArah).' baris (tidak disentuh)');

        // ------------------------------------------------------------- hierarki
        $akar = [];

        foreach ($karyawan->groupBy(fn ($k) => $k->dept ?? '(tanpa sub-divisi)') as $dept => $orang) {
            $terurut = $this->urutkan($orang);
            $akar[] = $terurut->first();
            $this->pohonkan($terurut, $rentang, "hierarki {$dept}", $tipe, $berlaku);
        }

        /* Akar tiap sub-divisi dipohonkan dengan mekanisme yang SAMA. Menyambungkannya ke satu
           orang membuat orang itu punya bawahan sebanyak seluruh puncak sub-divisi. */
        $this->pohonkan($this->urutkan(collect($akar)), $rentang, 'puncak lintas sub-divisi', $tipe, $berlaku);

        $this->newLine();
        $this->info(sprintf('Hierarki : %d pasang (%d baris)', count($this->baris) / 2, count($this->baris)));
        $this->line(sprintf(
            '  sudah terhubung: %d | arah bertentangan: %d | tanpa atasan: 1 (akar perusahaan)',
            $this->lewatSudahAda,
            $this->lewatBentrok,
        ));

        // ----------------------------------------------------------------- peer
        if ($this->option('tanpa-peer')) {
            $this->line('Peer     : dilewati (--tanpa-peer)');
        } else {
            $pasanganPeer = $this->jaringPeer($karyawan, $besarPeer, $tipe, $berlaku, $adaArah);
            $this->info(sprintf(
                'Peer     : %d pasang (%d baris), kelompok maksimum %d orang',
                $pasanganPeer,
                $pasanganPeer * 2,
                $besarPeer,
            ));
        }

        if ($this->option('lengkapi')) {
            $this->lengkapi($karyawan, $tipe, $berlaku, $adaArah);
        }

        $freelance = $karyawan->filter(fn ($k) => (int) ($k->lvl ?? 0) === self::LEVEL_FREELANCE)->count();

        if ($freelance > 0) {
            $this->warn(sprintf(
                '%d orang berlevel FREELANCE ikut diberi relasi. Mereka TIDAK dinilai PA, jadi '
                .'relasinya tidak akan dipakai penugasan mana pun.',
                $freelance,
            ));
        }

        $this->newLine();
        $this->info('Total baris: '.count($this->baris));

        if ($this->option('dry-run')) {
            $this->comment('--dry-run: tidak ada yang ditulis.');

            return self::SUCCESS;
        }

        if ($this->baris === []) {
            $this->comment('Tidak ada yang perlu ditulis; semuanya sudah terhubung.');

            return self::SUCCESS;
        }

        /* 14 kolom. SQL Server berhenti di 2.100 parameter per pernyataan, jadi potongannya 100
           (1.400 parameter) — bukan 150 yang pas-pasan. */
        $semua = $this->baris;

        DB::transaction(function () use ($semua) {
            foreach (array_chunk($semua, 100) as $potong) {
                DB::table('N_HRIS_Organization_Relationship')->insert($potong);
            }
        });

        $this->newLine();
        $this->info('Selesai. '.count($semua).' baris ditulis.');
        $this->line('Bersihkan kapan saja: php artisan organisasi:seed-relasi-dummy --bersihkan');

        return self::SUCCESS;
    }

    /**
     * Urutan yang membuat pohonnya sah: jabatan tertinggi lebih dulu, lalu kode karyawan.
     *
     * Kode dipakai sebagai pemutus, bukan nama: nama bisa kembar, dan urutan yang tidak
     * deterministik membuat dua kali menjalankan perintah ini menghasilkan struktur berbeda —
     * sehingga hasil sebelum dan sesudah perubahan kode tidak bisa dibandingkan.
     *
     * @param  Collection<int, object>  $orang
     * @return Collection<int, object>
     */
    private function urutkan(Collection $orang): Collection
    {
        return $orang->sort(function ($a, $b) {
            $ha = (int) ($a->h ?? 0);
            $hb = (int) ($b->h ?? 0);

            return $hb <=> $ha ?: strcmp((string) $a->kode, (string) $b->kode);
        })->values();
    }

    /**
     * Membentuk pohon atasan-bawahan atas satu daftar terurut.
     *
     * `atasan(i) = orang[(i - 1) div rentang]`. Indeks orang tua selalu LEBIH KECIL, jadi
     * jabatannya selalu lebih tinggi atau sama dan lingkaran atasan-bawahan tidak mungkin
     * terbentuk — penting, karena penjaga arah akan menolaknya satu per satu tanpa menjelaskan
     * pola sebabnya.
     *
     * @param  Collection<int, object>  $terurut
     * @param  array<string, mixed>  $tipe
     */
    private function pohonkan(Collection $terurut, int $rentang, string $sebab, array $tipe, string $berlaku): void
    {
        for ($i = 1; $i < $terurut->count(); $i++) {
            $bawahan = $terurut[$i];
            $atasan = $terurut[intdiv($i - 1, $rentang)];

            $ka = $atasan->pt.'|'.$atasan->kode;
            $kb = $bawahan->pt.'|'.$bawahan->kode;

            if ($ka === $kb) {
                continue;
            }

            $kunci = $ka < $kb ? "{$ka}::{$kb}" : "{$kb}::{$ka}";

            if (isset($this->adaPasangan[$kunci])) {
                /* Sudah punya hubungan hierarkis. Dibedakan: yang lahir dari perintah ini tidak
                   perlu dikerjakan dua kali, yang sudah ada sebelumnya bisa berlawanan arah dan
                   akan ditolak penjaga arah. */
                $this->adaPasangan[$kunci] === 'BARU' ? $this->lewatSudahAda++ : $this->lewatBentrok++;

                continue;
            }

            $this->adaPasangan[$kunci] = 'BARU';

            // Selalu berpasangan: atasan -> bawahan DAN kebalikannya.
            $this->baris[] = $this->baris((int) $tipe['BAWAHAN'], $atasan, $bawahan, $sebab, $berlaku);
            $this->baris[] = $this->baris((int) $tipe['ATASAN'], $bawahan, $atasan, $sebab, $berlaku);
        }
    }

    /**
     * @param  Collection<int, object>  $karyawan
     * @param  array<string, mixed>  $tipe
     * @param  array<string, bool>  $adaArah
     */
    private function jaringPeer(Collection $karyawan, int $besar, array $tipe, string $berlaku, array $adaArah): int
    {
        $pasangan = 0;

        foreach ($karyawan->groupBy(fn ($k) => ($k->dept ?? '-').'#'.($k->lvl ?? '-')) as $kunciKelompok => $sekelas) {
            /* Dipotong jadi kelompok kecil. Satu kelompok berisi seluruh tingkat akan meledak:
               jaringnya kuadratik, jadi 63 orang sekelas = 3.906 baris untuk satu kelompok saja —
               dan "rekan kerja" sebanyak itu tidak bermakna sebagai penilai. */
            foreach ($sekelas->values()->chunk($besar) as $kelompok) {
                $anggota = $kelompok->values();

                for ($a = 0; $a < $anggota->count(); $a++) {
                    for ($b = $a + 1; $b < $anggota->count(); $b++) {
                        $x = $anggota[$a];
                        $y = $anggota[$b];
                        $kx = $x->pt.'|'.$x->kode;
                        $ky = $y->pt.'|'.$y->kode;

                        if (isset($adaArah['PEER#'.$kx.'#'.$ky])) {
                            continue;
                        }

                        /* Pasangan yang sudah punya hubungan HIERARKIS dilewati.
                           Sejak aturan "satu pasang satu relasi", rekan sejawat tidak boleh
                           menumpang di atas atasan/bawahan. Seed lama membuat keduanya secara
                           terpisah dan menghasilkan 477 pasang yang saling menyangkal — kini
                           ditolak penjaga penulisan, jadi seed yang tidak menyaringnya akan
                           gagal separuh jalan tanpa pola sebab yang jelas. */
                        $kunciHierarki = $kx < $ky ? "{$kx}::{$ky}" : "{$ky}::{$kx}";

                        if (isset($this->adaPasangan[$kunciHierarki])) {
                            continue;
                        }

                        $adaArah['PEER#'.$kx.'#'.$ky] = true;
                        $adaArah['PEER#'.$ky.'#'.$kx] = true;

                        $this->baris[] = $this->baris((int) $tipe['PEER'], $x, $y, "peer {$kunciKelompok}", $berlaku);
                        $this->baris[] = $this->baris((int) $tipe['PEER'], $y, $x, "peer {$kunciKelompok}", $berlaku);
                        $pasangan++;
                    }
                }
            }
        }

        return $pasangan;
    }

    /**
     * Menambah relasi ekstra sampai minimum kebijakan penilai terpenuhi.
     *
     * KENAPA PERLU, DAN APA YANG DIKARANGNYA
     *
     * Dalam pohon apa pun, DAUN tidak punya anak. Kebijakan penilai menuntut minimal 2 BAWAHAN
     * untuk level 4 ke atas, sementara sebagian besar OFFICER dan MANAGER berakhir sebagai daun
     * — terukur: 515 dari 859 peserta bisa dilengkapi, 341 sisanya kurang BAWAHAN.
     *
     * Pass ini menutupnya dengan menambah bawahan dari orang yang lebih rendah di sub-divisi
     * yang sama, DIBAGI: satu orang bisa menjadi bawahan beberapa atasan. Itu tidak realistis —
     * karena itu opsional, bukan baku, dan angkanya dilaporkan.
     *
     * Yang TIDAK dilanggarnya: pasangan yang sudah punya hubungan hierarkis arah mana pun
     * dilewati, jadi tidak ada yang menjadi atasan sekaligus bawahan orang yang sama. Rekan
     * kerja juga tidak dipasangkan pada pasangan yang sudah hierarkis.
     *
     * @param  Collection<int, object>  $karyawan
     * @param  array<string, mixed>  $tipe
     * @param  array<string, bool>  $adaArah
     */
    private function lengkapi(Collection $karyawan, array $tipe, string $berlaku, array $adaArah): void
    {
        /* Minimum dibaca dari kebijakan MASTER, bukan snapshot siklus: perintah ini tidak
           terikat satu siklus, dan master adalah aturan yang akan disalin siklus berikutnya. */
        $min = [];

        foreach (DB::table('N_PA_Rater_Policy_Detail as d')
            ->join('N_PA_Rater_Policy as p', 'p.Id_PA_Rater_Policy', '=', 'd.PA_Rater_Policy_Id')
            ->join('N_PA_Rater_Category as c', 'c.Id_PA_Rater_Category', '=', 'd.PA_Rater_Category_Id')
            ->where('p.Flag_Aktif', 'Y')
            ->where('d.Flag_Aktif', 'Y')
            ->get(['p.ID_Level', 'c.Kode_Kategori as kat', 'd.Min_Rater']) as $r) {
            $min[(int) $r->ID_Level][$r->kat] = (int) $r->Min_Rater;
        }

        if ($min === []) {
            $this->warn('Kebijakan penilai belum ada; --lengkapi dilewati.');

            return;
        }

        /* Cacah yang AKAN dimiliki tiap orang: yang sudah ada di database DITAMBAH yang baru
           dibentuk perintah ini. Menghitung salah satunya saja membuat pass ini menambah relasi
           yang sebenarnya sudah cukup. */
        $punya = [];

        foreach (array_keys($adaArah) as $kunci) {
            $bagian = explode('#', $kunci);

            if (count($bagian) < 2) {
                continue;
            }

            $punya[$bagian[1]][$bagian[0]] = ($punya[$bagian[1]][$bagian[0]] ?? 0) + 1;
        }

        $kodeTipe = [
            (int) $tipe['BAWAHAN'] => 'BAWAHAN',
            (int) $tipe['ATASAN'] => 'ATASAN',
            (int) $tipe['PEER'] => 'PEER',
        ];

        foreach ($this->baris as $b) {
            $kat = $kodeTipe[$b['Id_Organization_Relationship_Type']] ?? null;

            if ($kat === null) {
                continue;
            }

            $subjek = $b['Kode_Perusahaan_Subjek'].'|'.$b['Kode_Karyawan_Subjek'];
            $punya[$subjek][$kat] = ($punya[$subjek][$kat] ?? 0) + 1;
        }

        $tambahBawahan = 0;
        $tambahPeer = 0;
        $tetapKurang = 0;

        foreach ($karyawan->groupBy(fn ($k) => $k->dept ?? '(tanpa sub-divisi)') as $orang) {
            $terurut = $this->urutkan($orang);

            foreach ($terurut as $i => $o) {
                $butuh = $min[(int) ($o->lvl ?? 0)] ?? [];
                $ko = $o->pt.'|'.$o->kode;

                // ------------------------------------------------------- BAWAHAN
                $perlu = ($butuh['BAWAHAN'] ?? 0) - ($punya[$ko]['BAWAHAN'] ?? 0);

                for ($j = $i + 1; $perlu > 0 && $j < $terurut->count(); $j++) {
                    $calon = $terurut[$j];
                    $kc = $calon->pt.'|'.$calon->kode;
                    $kunci = $ko < $kc ? $ko.'::'.$kc : $kc.'::'.$ko;

                    if (isset($this->adaPasangan[$kunci])) {
                        continue;
                    }

                    $this->adaPasangan[$kunci] = 'BARU';
                    $this->baris[] = $this->baris((int) $tipe['BAWAHAN'], $o, $calon, 'pelengkap bawahan', $berlaku);
                    $this->baris[] = $this->baris((int) $tipe['ATASAN'], $calon, $o, 'pelengkap bawahan', $berlaku);
                    $punya[$ko]['BAWAHAN'] = ($punya[$ko]['BAWAHAN'] ?? 0) + 1;
                    $tambahBawahan++;
                    $perlu--;
                }

                if ($perlu > 0) {
                    /* Tidak cukup orang di bawahnya dalam sub-divisi ini. Dibiarkan kurang dan
                       DIHITUNG — mengarang bawahan dari sub-divisi lain membuat struktur
                       contohnya makin jauh dari apa pun yang bisa dipercaya. */
                    $tetapKurang++;
                }

                // ---------------------------------------------------------- PEER
                $perluPeer = ($butuh['PEER'] ?? 0) - ($punya[$ko]['PEER'] ?? 0);

                foreach ($terurut as $calon) {
                    if ($perluPeer <= 0) {
                        break;
                    }

                    $kc = $calon->pt.'|'.$calon->kode;

                    if ($kc === $ko || isset($adaArah['PEER#'.$ko.'#'.$kc])) {
                        continue;
                    }

                    /* Rekan kerja tidak boleh sekaligus atasan/bawahannya: dua hubungan yang
                       saling menyangkal pada satu pasang orang. */
                    $kunci = $ko < $kc ? $ko.'::'.$kc : $kc.'::'.$ko;

                    if (isset($this->adaPasangan[$kunci])) {
                        continue;
                    }

                    $adaArah['PEER#'.$ko.'#'.$kc] = true;
                    $adaArah['PEER#'.$kc.'#'.$ko] = true;
                    $this->baris[] = $this->baris((int) $tipe['PEER'], $o, $calon, 'pelengkap peer', $berlaku);
                    $this->baris[] = $this->baris((int) $tipe['PEER'], $calon, $o, 'pelengkap peer', $berlaku);
                    $punya[$ko]['PEER'] = ($punya[$ko]['PEER'] ?? 0) + 1;
                    $punya[$kc]['PEER'] = ($punya[$kc]['PEER'] ?? 0) + 1;
                    $tambahPeer++;
                    $perluPeer--;
                }
            }
        }

        $this->newLine();
        $this->warn(sprintf(
            'Pelengkap: +%d pasang bawahan, +%d pasang peer. Bawahannya DIBAGI — satu orang bisa '
            .'menjadi bawahan beberapa atasan, dan itu tidak realistis.',
            $tambahBawahan,
            $tambahPeer,
        ));

        if ($tetapKurang > 0) {
            $this->line(sprintf(
                '  %d orang tetap kurang bawahan: tidak cukup orang di bawahnya dalam sub-divisinya.',
                $tetapKurang,
            ));
        }
    }

    /**
     * Tanggal mulai berlakunya relasi.
     *
     * BUKAN hari ini, dan ini bukan kehati-hatian berlebihan. `PaAssignmentBuilderService`
     * membaca relasi PADA TANGGAL ACUAN siklusnya (`Berlaku_Dari <= acuan`). Siklus yang
     * acuannya kemarin tidak akan melihat relasi yang berlaku hari ini — terlambat satu hari
     * sudah cukup, dan gejalanya paling menyesatkan yang mungkin: ribuan baris tertulis rapi,
     * nol anomali, dan usulan penilainya tetap kosong tanpa satu pun pesan.
     */
    private function tanggalBerlaku(): string
    {
        if ($pilihan = $this->option('berlaku-dari')) {
            return Carbon::parse($pilihan)->format('Y-m-d');
        }

        $paling = DB::table('N_PA_Cycle')
            ->selectRaw('MIN(COALESCE(Tanggal_Acuan_Organisasi, Periode_Dinilai_Sampai)) AS tgl')
            ->value('tgl');

        return $paling ? Carbon::parse($paling)->format('Y-m-d') : now()->format('Y-m-d');
    }

    /**
     * Relasi aktif yang sudah ada, dibaca SEKALI, kedua arah.
     *
     * @return array<string, bool>
     */
    private function bacaRelasiYangAda(): array
    {
        $adaArah = [];

        foreach (DB::table('N_HRIS_Organization_Relationship as r')
            ->join(
                'N_HRIS_Organization_Relationship_Type as t',
                't.Id_Organization_Relationship_Type',
                '=',
                'r.Id_Organization_Relationship_Type',
            )
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At')
            ->get([
                't.Kode_Relasi as kode',
                'r.Kode_Perusahaan_Subjek as spt', 'r.Kode_Karyawan_Subjek as skode',
                'r.Kode_Perusahaan_Terkait as tpt', 'r.Kode_Karyawan_Terkait as tkode',
            ]) as $r) {
            $s = $r->spt.'|'.$r->skode;
            $t = $r->tpt.'|'.$r->tkode;

            $adaArah[$r->kode.'#'.$s.'#'.$t] = true;

            /* Pasangan hierarkis dicatat TANPA arah: satu pasang orang hanya boleh punya satu
               hubungan hierarkis, arah mana pun. Ditandai 'LAMA' supaya bisa dibedakan dari
               pasangan yang lahir dari perintah ini sendiri. */
            if ($r->kode === 'ATASAN' || $r->kode === 'BAWAHAN') {
                $this->adaPasangan[$s < $t ? "{$s}::{$t}" : "{$t}::{$s}"] = 'LAMA';
            }
        }

        return $adaArah;
    }

    /**
     * @return array<string, mixed>
     */
    private function baris(int $tipeId, object $subjek, object $terkait, string $sebab, string $berlaku): array
    {
        return [
            'Id_Organization_Relationship_Type' => $tipeId,
            'Kode_Perusahaan_Subjek' => $subjek->pt,
            'Kode_Karyawan_Subjek' => $subjek->kode,
            'Kode_Perusahaan_Terkait' => $terkait->pt,
            'Kode_Karyawan_Terkait' => $terkait->kode,
            'Berlaku_Dari' => $berlaku,
            'Berlaku_Sampai' => null,
            'Flag_Utama' => 'T',
            'Prioritas' => 1,

            /* Asal-usulnya ditandai supaya bisa dibersihkan tanpa menebak, dan supaya siapa pun
               yang menemukannya setahun lagi tahu ini bukan struktur yang diputuskan HC. */
            'Sumber_Data' => self::SUMBER,
            'Referensi_Sumber' => $sebab,
            'Catatan' => 'Struktur contoh untuk pengujian alur Performance Appraisal.',
            'Flag_Aktif' => 'Y',

            /* NULL, dan itu yang membedakannya dari baris yang dibuat orang lewat layar. */
            'Created_By' => null,
        ];
    }

    private function bersihkan(): int
    {
        /* DUA syarat, bukan satu. Pembersihan yang hanya melihat Sumber_Data akan ikut menghapus
           baris yang kebetulan bertanda sama tetapi dibuat orang; yang hanya melihat Created_By
           NULL akan ikut menghapus baris dari perintah lain. */
        $milikOrang = DB::table('N_HRIS_Organization_Relationship')->whereNotNull('Created_By')->count();

        $n = DB::table('N_HRIS_Organization_Relationship')
            ->where('Sumber_Data', self::SUMBER)
            ->whereNull('Created_By')
            ->delete();

        $this->info("{$n} baris contoh dihapus.");
        $this->line("{$milikOrang} baris milik pengguna dibiarkan utuh.");

        return self::SUCCESS;
    }
}
