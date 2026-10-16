<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Perpustakaan SOP karyawan — pembacaan untuk halaman Repository.
 *
 * ATURAN LINGKUPNYA SATU KALIMAT: **Repository hanya memuat SOP PUBLIK.**
 *
 * SOP yang ditugaskan — apa pun mode distribusinya — adalah urusan halaman SOP
 * Saya. Sebelumnya keduanya bercampur di sini, dan SOP yang sama muncul dua kali
 * dengan kartu identik; tak ada penataan ulang di klien yang bisa memperbaiki
 * itu, karena masalahnya ada di lingkup, bukan di tampilan.
 *
 * Konsekuensi yang disengaja:
 *  - SOP TERBATAS tak pernah bisa muncul lewat jalur mana pun di kelas ini,
 *    termasuk pencarian dan intip. Gerbangnya jadi satu predikat sederhana,
 *    bukan cabang EXISTS yang harus diingat di setiap query baru.
 *  - Isi perpustakaan SAMA untuk semua karyawan. "HSE ada 12 SOP" bernilai sama
 *    bagi siapa pun — perpustakaan yang isinya berubah per orang terasa aneh
 *    dan angkanya tak bisa dijadikan rujukan bersama.
 *  - SOP publik yang KEBETULAN ditugaskan tetap ada di rak; ia hanya diberi
 *    penanda "juga tugas Anda". Yang diduplikasi cuma label, bukan kartu.
 *
 * DUA ATURAN YANG TAK BOLEH DILANGGAR DI SELURUH BERKAS INI:
 *
 *  1. SETIAP query yang memunculkan paket WAJIB berangkat dari basePaket().
 *     Menulis query paket sendiri = melewati batas PUBLIK dan berpotensi
 *     membocorkan SOP terbatas ke seluruh karyawan.
 *
 *  2. HITUNGAN HARUS JUJUR. Angka pada facet dihitung di SERVER atas seluruh
 *     himpunan, bukan dari halaman yang kebetulan termuat. Versi lama
 *     menghitung facet dari 12 baris yang sedang tampil, sehingga kelompok
 *     berisi 30 SOP tertulis "4" — angka salah lebih buruk daripada tanpa angka.
 */
class SopRepositoryReadService
{
    /** Ambang "baru & diperbarui" (hari). */
    private const HARI_BARU = 30;

    private function enc($id): string
    {
        return Hashids::connection('custom')->encode($id);
    }

    /* ================= BERANDA ================= */

    /**
     * Beranda perpustakaan dalam SATU kali pengayaan.
     *
     * Versi naif memanggil kartu() sekali per baris personal; itu berarti
     * puluhan query untuk halaman yang dibuka SETIAP karyawan. Di sini seluruh
     * Id dikumpulkan lebih dulu (query ringan tanpa join), diperkaya sekali,
     * lalu dibagikan kembali ke tiap bagian dari indeks yang sama.
     */
    public function beranda(string $kode): array
    {
        $bagian = [
            'favorit' => $this->idFavorit($kode),
            'terakhir' => $this->idTerakhir($kode),
            'baru' => $this->idBaru($kode),
        ];

        $indeks = $this->indeksKartu(
            array_values(array_unique(array_merge(...array_values($bagian)))),
            $kode,
        );

        // Id personal boleh menunjuk paket TERBATAS (favorit/riwayat lama). Yang
        // tak ada di indeks otomatis gugur di sini — jadi hitungan chip di klien
        // selalu sama dengan yang benar-benar tampil.
        $ambil = fn (array $ids) => $this->tanpaId(array_values(array_filter(
            array_map(fn ($id) => $indeks[$id] ?? null, $ids),
        )));

        $h = $this->hitungRak($kode);

        return [
            'tugas' => $this->ringkasTugas($kode),
            'favorit' => $ambil($bagian['favorit']),
            'terakhir' => $ambil($bagian['terakhir']),
            'baru' => $ambil($bagian['baru']),
            'total' => $h['total'],
            'jumlah_baru' => $h['baru'],
            'jumlah_favorit' => $h['favorit'],
        ];
    }

    /**
     * Ringkasan tugas untuk BILAH AJAKAN — angka dan satu nama, bukan daftar.
     *
     * Halaman ini sengaja tidak lagi menampilkan kartu tugas: tugas tinggal di
     * SOP Saya. Yang tersisa di sini hanyalah pengingat, dan pengingat butuh
     * cukup bahan untuk terasa konkret — "3 menunggu, yang paling mendesak SOP X
     * lewat 45 hari" jauh lebih menggerakkan daripada "Anda punya tugas".
     *
     * Query ini SENGAJA tidak lewat basePaket(): yang dibaca adalah penugasan
     * milik karyawan itu sendiri, jadi paket TERBATAS pun sah ikut terhitung.
     * Yang keluar cuma nama paketnya — tak ada satu pun isi yang dibocorkan.
     */
    /**
     * TIGA ANGKA KARTU METRIK DALAM SATU KUNJUNGAN.
     *
     * Ketiganya dihitung sendiri, tidak diambil dari panjang larik ringkasan di
     * atas: larik itu dibatasi delapan baris, dan sejak kartu metriknya bisa
     * diklik untuk menyaring, angka yang tertulis "8" langsung berselisih
     * dengan daftar berisi dua puluh yang muncul sesudahnya.
     *
     * Digabung jadi satu agregat, bukan tiga COUNT terpisah, karena beranda ini
     * dibuka SETIAP karyawan setiap hari dan punya anggaran query yang dijaga
     * uji (SopAwarenessUpdateTest). Tiga kunjungan tambahan untuk tiga angka
     * dari tabel yang sama adalah harga yang tak perlu dibayar.
     *
     * CATATAN PEMELIHARAAN: predikat "baru" di sini adalah kembaran SQL dari
     * predikatBaru(). Keduanya wajib sepakat, dan yang menjaganya adalah
     * SopRepositoryRekapSumbuTest::test_hitungan_baru_sepakat_dengan_predikatnya
     * — kalau salah satu diubah tanpa yang lain, uji itu memerah.
     *
     * @return array{total:int, baru:int, favorit:int}
     */
    private function hitungRak(string $kode): array
    {
        $batas = Carbon::today()->subDays(self::HARI_BARU);

        /* SQL Server menolak subkueri di dalam fungsi agregat
           ("Cannot perform an aggregate function on an expression containing an
           aggregate or a subquery"), jadi kedua keanggotaan dibawa masuk lebih
           dulu sebagai tabel turunan. Keduanya WAJIB berkelompok per paket:
           tabel turunan yang mengembalikan lebih dari satu baris per paket akan
           menggandakan COUNT(*) tanpa gejala apa pun selain angka yang salah. */
        $versiBaru = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('v.Versi_Ke', '>', 1)
            ->where('v.Tanggal_Publish', '>=', $batas)
            ->groupBy('i.Id_SOP_Paket')
            ->select('i.Id_SOP_Paket');

        $favorit = DB::table('N_SOP_Favorit')
            ->where('Kode_Karyawan', $kode)
            ->groupBy('Id_SOP_Paket')
            ->select('Id_SOP_Paket');

        $r = $this->basePaket()
            ->leftJoinSub($versiBaru, 'vb', 'vb.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->leftJoinSub($favorit, 'fv', 'fv.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->selectRaw(
                'COUNT(*) as total,
                 SUM(CASE WHEN p.Tanggal_Publish >= ? OR vb.Id_SOP_Paket IS NOT NULL THEN 1 ELSE 0 END) as baru,
                 SUM(CASE WHEN fv.Id_SOP_Paket IS NOT NULL THEN 1 ELSE 0 END) as favorit',
                [$batas],
            )
            ->first();

        return [
            'total' => (int) ($r->total ?? 0),
            'baru' => (int) ($r->baru ?? 0),
            'favorit' => (int) ($r->favorit ?? 0),
        ];
    }

    private function ringkasTugas(string $kode): array
    {
        $rows = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('d.Status_Paket', '<>', SopStatus::STATUS_SELESAI)
            ->groupBy('pj.Id_SOP_Paket', 'p.Nama_Paket')
            ->selectRaw("p.Nama_Paket,
                MIN(CASE WHEN pj.Tipe_Tenggat = 'DEADLINE' THEN pj.Tanggal_Deadline END) as tenggat")
            ->get();

        $hariIni = Carbon::today();
        $sisa = fn ($t) => $t === null ? null : (int) $hariIni->diffInDays(Carbon::parse($t), false);

        $terlambat = $segera = 0;
        $terdekat = null;
        foreach ($rows as $r) {
            $s = $sisa($r->tenggat);
            if ($s === null) {
                continue;
            }
            if ($s < 0) {
                $terlambat++;
            } elseif ($s <= 7) {
                $segera++;
            }
            if ($terdekat === null || $s < $terdekat['sisa_hari']) {
                $terdekat = ['nama' => $r->Nama_Paket, 'sisa_hari' => $s];
            }
        }

        return [
            'total' => $rows->count(),
            'terlambat' => $terlambat,
            'segera' => $segera,
            'terdekat' => $terdekat,
        ];
    }

    /* ---- pengumpul Id (ringan, tanpa pengayaan) ---- */

    private function idFavorit(string $kode, int $limit = 8): array
    {
        return DB::table('N_SOP_Favorit')->where('Kode_Karyawan', $kode)
            ->orderByDesc('Created_At')->limit($limit)
            ->pluck('Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();
    }

    private function idTerakhir(string $kode, int $limit = 6): array
    {
        return DB::table('N_SOP_Progress as g')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'g.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('g.Kode_Karyawan', $kode)
            ->whereNotNull('g.Waktu_Buka')
            ->groupBy('pj.Id_SOP_Paket')
            ->orderByDesc(DB::raw('MAX(g.Waktu_Buka)'))
            ->limit($limit)
            ->pluck('pj.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();
    }

    private function idBaru(string $kode, int $limit = 8): array
    {
        $q = $this->basePaket();
        $this->predikatBaru($q);

        return $q->orderByDesc('p.Tanggal_Publish')->limit($limit)
            ->pluck('p.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();
    }

    /**
     * "Baru & diperbarui" sebagai PREDIKAT, bukan sebagai daftar Id.
     *
     * Bentuk daftar hanya melayani kartu ringkas di kepala halaman; begitu
     * lensa yang sama dipakai menyaring daftar utama yang berpaginasi, ia harus
     * jadi bagian dari query — kalau tidak, rail dan paginasi menghitung
     * seluruh rak sementara isinya hanya delapan baris yang kebetulan terpungut
     * lebih dulu.
     */
    private function predikatBaru($q): void
    {
        $batas = Carbon::today()->subDays(self::HARI_BARU);

        $q->where(function ($w) use ($batas) {
            $w->where('p.Tanggal_Publish', '>=', $batas)
                ->orWhereExists(function ($s) use ($batas) {
                    $s->select(DB::raw(1))
                        ->from('N_SOP_Paket_Item as i')
                        ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
                        ->whereColumn('i.Id_SOP_Paket', 'p.Id_SOP_Paket')
                        ->where('i.Flag_Aktif', SopStatus::AKTIF)
                        ->where('v.Versi_Ke', '>', 1)
                        ->where('v.Tanggal_Publish', '>=', $batas);
                });
        });
    }

    /** @return array<int,string|null> [id paket => tenggat terdekat] */
    /** Perkaya sekumpulan Id sekaligus → [id => kartu]. */
    private function indeksKartu(array $ids, string $kode): array
    {
        if (empty($ids)) {
            return [];
        }

        $kartu = $this->kartu($this->basePaket()->whereIn('p.Id_SOP_Paket', $ids), $kode);
        $out = [];
        foreach ($kartu as $k) {
            $out[$k['_id']] = $k;
        }

        return $out;
    }

    /* ================= DAFTAR PAKET ================= */

    /**
     * Seluruh paket publik, berpaginasi dan tersaring.
     *
     * Menggantikan kategoriDetail(). Perpustakaan berak per folder Kategori
     * sampai 28 Agu 2026; setelah Kategori dicabut dari modul, satu-satunya
     * sumbu paket yang tersisa adalah Prioritas — dan prioritas bukan topik,
     * jadi ia tak layak jadi folder. Lensa Paket karena itu jadi daftar datar
     * dengan pencarian dan saringan, dan lensa Dokumen (yang memang berak per
     * departemen/jenis/proses) tetap seperti apa adanya.
     *
     * @param  array  $f  q, prioritas, status (semua|belum|progress|selesai), wajib, sumbu, grup
     * @return array{rows:array, total:int}
     */
    public function daftarPaket(string $kode, array $f, int $page, int $limit): array
    {
        $q = $this->basePaket();
        $this->terapkanSaringan($q, $f, $kode);

        $semua = $this->kartu(
            (clone $q)->orderByDesc('p.Tanggal_Publish')->orderByDesc('p.Id_SOP_Paket'),
            $kode,
        );

        if (! empty($f['status']) && $f['status'] !== 'semua') {
            $semua = array_values(array_filter($semua, fn ($r) => $this->cocokStatus($r, $f['status'])));
        }

        // Default Sort: Baru diassign -> Ada progress -> Selesai -> Publik lainnya (masing-masing Tanggal & Jam DESC)
        $this->urutkanDefault($semua);

        $total = count($semua);
        $offset = ($page - 1) * $limit;
        $rows = array_slice($semua, $offset, $limit);

        /* Sebutkan item yang cocok — hasil tanpa alasan memaksa membuka satu
           per satu untuk tahu kenapa ia muncul. Hanya untuk halaman berjalan:
           pertanyaannya "kenapa baris INI ada di sini", bukan sebuah rekap. */
        if (! empty($f['q']) && mb_strlen(trim($f['q'])) >= 2 && $rows !== []) {
            $rows = $this->jelaskanCocok($rows, trim($f['q']));
        }

        return [
            'rows' => $this->tanpaId($rows),
            'total' => $total,
        ];
    }

    /**
     * Urutkan default multi-tier:
     * 1. Baru diassign (Wajib & Belum Selesai / Belum Buka) -> Tanggal & Jam Assign DESC
     * 2. Ada progress (Sedang Berjalan / Pernah Buka) -> Tanggal & Jam Akses DESC
     * 3. Selesai -> Tanggal & Jam Selesai DESC
     * 4. Dokumen publik umum lainnya -> Tanggal Publish / Created_At DESC
     */
    private function urutkanDefault(array &$kartu): void
    {
        $getTier = function ($k) {
            $persen = $k['Persen'] ?? 0;
            $wajib = $k['Wajib'] ?? false;
            $pernahBuka = $k['Pernah_Buka'] ?? (! empty($k['Waktu_Buka']));

            if ($persen >= 100) {
                return 3;
            }
            if ($persen > 0 || ($pernahBuka && $persen < 100)) {
                return 2;
            }
            if ($wajib) {
                return 1;
            }

            return 4;
        };

        $getTimestamp = function ($k, $tier) {
            if ($tier === 1) {
                $t = $k['Tanggal_Assign'] ?? null;

                return $t ? strtotime($t) : 0;
            }
            if ($tier === 2) {
                $t = $k['Terakhir_Buka'] ?? ($k['Waktu_Buka'] ?? ($k['Tanggal_Assign'] ?? null));

                return $t ? strtotime($t) : 0;
            }
            if ($tier === 3) {
                $t = $k['Tanggal_Selesai'] ?? ($k['Terakhir_Konfirmasi'] ?? ($k['Waktu_Konfirmasi'] ?? null));

                return $t ? strtotime($t) : 0;
            }
            $t = $k['Tanggal_Publish'] ?? ($k['Tanggal_Berlaku'] ?? null);

            return $t ? strtotime($t) : 0;
        };

        usort($kartu, function ($a, $b) use ($getTier, $getTimestamp) {
            $tierA = $getTier($a);
            $tierB = $getTier($b);

            if ($tierA !== $tierB) {
                return $tierA <=> $tierB;
            }

            $timeA = $getTimestamp($a, $tierA);
            $timeB = $getTimestamp($b, $tierB);

            if ($timeA !== $timeB) {
                return $timeB <=> $timeA; // DESC
            }

            return ($b['_id'] ?? ($b['Id_SOP_Paket_Item'] ?? 0)) <=> ($a['_id'] ?? ($a['Id_SOP_Paket_Item'] ?? 0));
        });
    }

    /**
     * Lampirkan `Cocok_Pada` — judul/nomor item yang membuat paket ini ikut
     * terjaring. Dipakai bersama oleh daftar dan pencarian supaya keterangannya
     * tak pernah berbeda antara keduanya.
     */
    private function jelaskanCocok(array $kartu, string $teks): array
    {
        $suka = '%'.$teks.'%';
        $cocok = DB::table('N_SOP_Paket_Item')
            ->whereIn('Id_SOP_Paket', array_column($kartu, '_id'))
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->where(fn ($x) => $x->where('Judul_Item', 'LIKE', $suka)->orWhere('Nomor_SOP_Resmi', 'LIKE', $suka))
            ->get(['Id_SOP_Paket', 'Judul_Item', 'Nomor_SOP_Resmi'])
            ->groupBy('Id_SOP_Paket');

        foreach ($kartu as &$k) {
            $c = $cocok[$k['_id']] ?? collect();
            $k['Cocok_Pada'] = $c->take(2)
                ->map(fn ($i) => trim(($i->Nomor_SOP_Resmi ? $i->Nomor_SOP_Resmi.' | ' : '').$i->Judul_Item))
                ->values()->all();
        }
        unset($k);

        return $kartu;
    }

    /* ================= PENCARIAN ================= */

    /**
     * Pencarian lintas nama paket, nomor SOP resmi, judul item, dan deskripsi —
     * lalu MENJELASKAN di mana cocoknya.
     *
     * Kenapa sampai ke nomor & judul item: orang lapangan mencari "SOP-HSE-014"
     * atau "prosedur evakuasi", bukan judul paket yang mereka tak hafal. Pencarian
     * yang hanya melihat Nama_Paket mengembalikan nihil untuk cara mencari itu.
     */
    public function cari(string $kode, string $teks, int $limit = 20): array
    {
        $teks = trim($teks);
        if (mb_strlen($teks) < 2) {
            return [];
        }

        $suka = '%'.$teks.'%';

        $q = $this->basePaket()->where(function ($w) use ($suka) {
            $w->where('p.Nama_Paket', 'LIKE', $suka)
                ->orWhere('p.Kode_Paket', 'LIKE', $suka)
                ->orWhere('p.Tujuan_Ruang_Lingkup', 'LIKE', $suka)
                ->orWhereExists(function ($s) use ($suka) {
                    $s->select(DB::raw(1))->from('N_SOP_Paket_Item as i')
                        ->whereColumn('i.Id_SOP_Paket', 'p.Id_SOP_Paket')
                        ->where('i.Flag_Aktif', SopStatus::AKTIF)
                        ->where(fn ($x) => $x->where('i.Judul_Item', 'LIKE', $suka)
                            ->orWhere('i.Nomor_SOP_Resmi', 'LIKE', $suka));
                });
        });

        $kartu = $this->kartu((clone $q)->limit($limit), $kode);
        if (empty($kartu)) {
            return [];
        }

        return $this->tanpaId($this->jelaskanCocok($kartu, $teks));
    }

    /**
     * Berapa TUGAS karyawan yang cocok dengan kata kunci yang sama.
     *
     * Tanpa ini pencarian jadi jalan buntu: orang mengetik "evakuasi", SOP-nya
     * memang ada tapi berstatus tugas (dan mungkin TERBATAS), lalu halaman
     * menjawab "tak ada hasil" — terasa rusak, padahal aturannya sedang bekerja
     * dengan benar. Yang dikembalikan cuma cacahnya; kartunya tetap tak boleh
     * dirender di sini. Klien memakainya untuk satu baris jembatan ke SOP Saya.
     */
    public function cariDiTugas(string $kode, string $teks): int
    {
        $teks = trim($teks);
        if (mb_strlen($teks) < 2) {
            return 0;
        }

        $suka = '%'.$teks.'%';

        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where(fn ($w) => $w->where('p.Nama_Paket', 'LIKE', $suka)
                ->orWhere('p.Kode_Paket', 'LIKE', $suka)
                ->orWhere('p.Tujuan_Ruang_Lingkup', 'LIKE', $suka)
                ->orWhereExists(fn ($s) => $s->select(DB::raw(1))->from('N_SOP_Paket_Item as i')
                    ->whereColumn('i.Id_SOP_Paket', 'p.Id_SOP_Paket')
                    ->where('i.Flag_Aktif', SopStatus::AKTIF)
                    ->where(fn ($x) => $x->where('i.Judul_Item', 'LIKE', $suka)
                        ->orWhere('i.Nomor_SOP_Resmi', 'LIKE', $suka))))
            ->distinct()->count('p.Id_SOP_Paket');
    }

    /* ================= INTIP ISI ================= */

    /** Daftar isi ringkas satu paket — untuk drawer "Intip" tanpa meninggalkan rak. */
    public function intip(string $kode, int $idPaket): ?array
    {
        $paket = $this->basePaket()->where('p.Id_SOP_Paket', $idPaket)
            ->first([
                'p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Kode_Paket', 'p.Deskripsi',
                'p.Tujuan_Ruang_Lingkup', 'p.Estimasi_Waktu_Baca_Menit', 'p.Prioritas',
                'p.Versi_Paket_Aktif',
            ]);
        if (! $paket) {
            return null;
        } // tak terlihat oleh karyawan ini = tak ada

        $items = DB::table('N_SOP_Paket_Item as i')
            ->leftJoin('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->leftJoin('N_SOP_Progress as g', function ($j) use ($kode) {
                $j->on('g.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item')
                    ->where('g.Kode_Karyawan', '=', $kode);
            })
            ->where('i.Id_SOP_Paket', $idPaket)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('i.Urutan')
            ->get([
                'i.Judul_Item', 'i.Tipe_Item', 'i.Nomor_SOP_Resmi', 'i.Flag_Wajib',
                'dok.Nomor_SOP_Resmi as Nomor_Dokumen',
                'v.Versi_Ke', 'v.Tanggal_Berlaku', 'g.Status as status_baca',
            ])
            ->map(fn ($i) => [
                'judul' => $i->Judul_Item,
                'tipe' => $i->Tipe_Item,
                'nomor' => $i->Nomor_SOP_Resmi ?: $i->Nomor_Dokumen,
                'wajib' => $i->Flag_Wajib === 'Y',
                'versi' => $i->Versi_Ke,
                'berlaku' => $i->Tanggal_Berlaku ? Carbon::parse($i->Tanggal_Berlaku)->toDateString() : null,
                'status' => $i->status_baca ?: 'BELUM',
            ])->all();

        return [
            'id' => $this->enc($paket->Id_SOP_Paket),
            'nama' => $paket->Nama_Paket,
            'kode' => $paket->Kode_Paket,
            'prioritas' => $paket->Prioritas,
            'tujuan' => $paket->Deskripsi ?: $paket->Tujuan_Ruang_Lingkup,
            'versi_paket' => $paket->Versi_Paket_Aktif ? (int) $paket->Versi_Paket_Aktif : 1,
            'estimasi' => $paket->Estimasi_Waktu_Baca_Menit ? (int) $paket->Estimasi_Waktu_Baca_Menit : 10,
            'item' => $items,
        ];
    }

    /* ================= FAVORIT ================= */

    /** @return array{favorit:bool} */
    public function toggleFavorit(string $kode, int $idPaket): array
    {
        // Gerbang: menyematkan paket yang tak boleh dilihat = celah akses.
        $terlihat = $this->basePaket()->where('p.Id_SOP_Paket', $idPaket)->exists();
        if (! $terlihat) {
            return ['favorit' => false, 'ditolak' => true];
        }

        $ada = DB::table('N_SOP_Favorit')
            ->where('Kode_Karyawan', $kode)->where('Id_SOP_Paket', $idPaket)->exists();

        if ($ada) {
            DB::table('N_SOP_Favorit')->where('Kode_Karyawan', $kode)->where('Id_SOP_Paket', $idPaket)->delete();

            return ['favorit' => false];
        }

        try {
            DB::table('N_SOP_Favorit')->insert([
                'Kode_Karyawan' => $kode,
                'Id_SOP_Paket' => $idPaket,
                'Created_At' => now(),
            ]);
        } catch (QueryException $e) {
            // 2627 = pelanggaran UNIQUE/PK, 2601 = pelanggaran index unik.
            // Keduanya berarti "sudah tersemat" — hasil akhirnya memang yang diminta
            // (klik ganda). Galat lain DILEMPAR: menjawab "tersemat" atas insert
            // yang gagal adalah berbohong kepada pengguna.
            if (! in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw $e;
            }
        }

        return ['favorit' => true];
    }

    /* ================= KATALOG DOKUMEN ================= */

    /**
     * Perpustakaan yang sama, DIPROYEKSIKAN per dokumen.
     *
     * Satu baris = satu item dokumen di dalam sebuah paket publik — bentuk yang
     * sama persis dengan daftar Dokumen di SOP Saya, hanya lingkupnya paket
     * PUBLIK alih-alih paket yang ditugaskan. Dengan itu satu komponen baris,
     * satu rail sumbu, dan satu kosakata sumbu (Shared/components/listing/sumbu.js) melayani kedua
     * halaman.
     *
     * DOKUMEN TIDAK PUNYA GERBANG SENDIRI, dan tidak boleh diberi satu.
     * Jangkauan sebuah dokumen ditentukan oleh paket yang memuatnya — itulah
     * sebabnya method ini berangkat dari basePaket() seperti seluruh jalur baca
     * lain di kelas ini, bukan dari N_SOP_Dokumen. Dokumen yang belum pernah
     * dimasukkan ke paket publik memang tidak muncul di sini; itu keadaan yang
     * benar, bukan lubang yang perlu ditambal dengan penanda baru pada dokumen.
     *
     * Tidak ada paginasi: perpustakaan publik dimuat sekali lalu dikelompokkan
     * dan disaring di klien, sama seperti SOP Saya. Rail wajib tahu SELURUH
     * himpunan untuk bisa menghitung jujur, dan memaginasinya berarti
     * menghidupkan kembali mesin facet di server yang justru dibuang.
     *
     * TAK ADA KEADAAN "TERKUNCI" DI SINI, dan itu disengaja. Setiap baris
     * menuju HALAMAN PAKET (`?item=`), bukan pembaca dokumen — dan di halaman
     * itulah gerbang presensi, urutan section, serta prasyarat antar-item sudah
     * menjelaskan dirinya sendiri. Menghitung ulang keadaan kunci di sini hanya
     * melahirkan penjelasan kedua yang bisa melenceng dari gerbang yang
     * sebenarnya menegakkannya.
     */
    public function katalogDokumen(?string $kode = null): array
    {
        $favoritIds = [];
        $wajibIds = [];
        $tugasProg = [];
        if ($kode) {
            $favoritIds = DB::table('N_SOP_Favorit')
                ->where('Kode_Karyawan', $kode)
                ->pluck('Id_SOP_Paket')
                ->map(fn ($x) => (int) $x)
                ->all();

            $wajibIds = DB::table('N_SOP_Penjadwalan_Detail as d')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('d.Kode_Karyawan', $kode)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T')
                ->pluck('pj.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();

            $tugasProg = DB::table('N_SOP_Progress as g')
                ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'g.Id_SOP_Penjadwalan_Detail')
                ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
                ->where('g.Kode_Karyawan', $kode)
                ->where('d.Flag_Aktif', SopStatus::AKTIF)
                ->where('d.Flag_Cabut_Akses', 'T')
                ->where('g.Status', '<>', 'DIGANTIKAN')
                ->selectRaw('g.Id_SOP_Paket_Item, g.Status, g.Waktu_Buka, g.Waktu_Konfirmasi, d.Tanggal_Selesai, COALESCE(pj.Tanggal_Assign, d.Created_At) as Tanggal_Assign')
                ->get()->keyBy('Id_SOP_Paket_Item');
        }

        $rows = $this->basePaket()
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->leftJoin('N_SOP_Paket_Section as ps', 'ps.Id_SOP_Section', '=', 'i.Id_SOP_Section')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'dok.Departemen_Id')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Master_APQC as ap', 'ap.Id_SOP_APQC', '=', 'dok.APQC_Id')
            ->leftJoin('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('dok.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('p.Nama_Paket')->orderBy('ps.Urutan')
            ->orderBy('i.Urutan')->orderBy('i.Id_SOP_Paket_Item')
            ->get([
                'i.Id_SOP_Paket_Item', 'i.Tipe_Item', 'i.Flag_Wajib', 'i.Urutan',
                'i.Id_Item_Versi_Aktif',
                'v.Versi_Ke', 'v.Tanggal_Berlaku',
                'p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Prioritas', 'p.Tanggal_Publish', 'p.Created_At',
                'ps.Nama_Section',
                'dok.Judul', 'dok.Nomor_SOP_Resmi', 'dok.No_Dokumen',
                'dok.Departemen_Id', 'dpt.Nama_Departemen', 'dpt.Kode_Departemen',
                'dok.Jenis_Dokumen_Id', 'jd.Nama_Jenis', 'jd.Kode_Jenis', 'jd.Level_Hirarki',
                'dok.APQC_Id', 'ap.Nama_Proses', 'ap.Kode_Proses',
            ]);

        if ($rows->isEmpty()) {
            return [];
        }

        /* Bentuk daftar bernilai TUNGGAL, meniru SopProgressReadService: sumbu
           di klien menerima daftar karena satu PAKET bisa menyentuh beberapa
           departemen, dan di sini satuannya dokumen sehingga daftarnya selalu
           berisi nol atau satu. Bentuknya tetap daftar supaya satu modul sumbu
           melayani kedua halaman tanpa cabang. */
        $satu = fn ($id, $nama, $level = null, $kode = null) => $id === null ? [] : [[
            'id' => (int) $id,
            'nama' => (string) ($nama ?? ''),
            'kode' => $kode ? (string) $kode : null,
            'level' => $level !== null ? (int) $level : null,
        ]];

        $sebulanLalu = Carbon::now()->subDays(30);

        $mapped = $rows->map(function ($r) use ($satu, $favoritIds, $wajibIds, $tugasProg, $sebulanLalu) {
            $tglPub = $r->Tanggal_Publish ?: $r->Created_At;
            $isBaru = $tglPub ? Carbon::parse($tglPub)->gte($sebulanLalu) : false;
            $tp = $tugasProg[$r->Id_SOP_Paket_Item] ?? null;
            $isWajib = in_array((int) $r->Id_SOP_Paket, $wajibIds, true);
            $tuntas = $tp && in_array($tp->Status, [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI], true);
            $persen = $tuntas ? 100 : ($tp && $tp->Waktu_Buka ? 50 : 0);

            return [
                'Id_SOP_Paket_Item' => $this->enc($r->Id_SOP_Paket_Item),
                'Id_SOP_Paket' => $this->enc($r->Id_SOP_Paket),
                'Nama_Paket' => $r->Nama_Paket,
                'Prioritas' => $r->Prioritas,

                'Judul' => $r->Judul,
                'Nomor_SOP' => $r->Nomor_SOP_Resmi,
                'No_Dokumen' => $r->No_Dokumen !== null ? (int) $r->No_Dokumen : null,
                'Tipe_Item' => $r->Tipe_Item,
                'Wajib' => $isWajib,
                'Favorit' => in_array((int) $r->Id_SOP_Paket, $favoritIds, true),
                'Baru' => $isBaru,
                'Diperbarui' => false,
                'Section' => $r->Nama_Section,
                'Urutan' => $r->Urutan !== null ? (int) $r->Urutan : null,
                'Tanpa_Versi' => $r->Id_Item_Versi_Aktif === null,
                'Versi' => $r->Versi_Ke !== null ? 'v'.$r->Versi_Ke.'.0' : null,
                'Versi_Ke' => $r->Versi_Ke !== null ? (int) $r->Versi_Ke : null,
                'Tanggal_Berlaku' => $r->Tanggal_Berlaku ? Carbon::parse($r->Tanggal_Berlaku)->format('Y-m-d') : null,
                'Tanggal_Publish' => $tglPub ? Carbon::parse($tglPub)->format('Y-m-d H:i:s') : null,

                'Departemen' => $satu($r->Departemen_Id, $r->Nama_Departemen, null, $r->Kode_Departemen),
                'Jenis_Dokumen' => $satu($r->Jenis_Dokumen_Id, $r->Nama_Jenis, $r->Level_Hirarki, $r->Kode_Jenis),
                'Apqc' => $satu($r->APQC_Id, $r->Nama_Proses, null, $r->Kode_Proses),

                'Total_Item' => 1,
                'Item_Selesai' => $tuntas ? 1 : 0,
                'Persen' => $persen,
                'Tanggal_Assign' => $tp && $tp->Tanggal_Assign ? Carbon::parse($tp->Tanggal_Assign)->format('Y-m-d H:i:s') : null,
                'Tanggal_Selesai' => $tp && $tp->Tanggal_Selesai ? Carbon::parse($tp->Tanggal_Selesai)->format('Y-m-d H:i:s') : ($tp && $tp->Waktu_Konfirmasi ? Carbon::parse($tp->Waktu_Konfirmasi)->format('Y-m-d H:i:s') : null),
                'Waktu_Buka' => $tp && $tp->Waktu_Buka ? Carbon::parse($tp->Waktu_Buka)->format('Y-m-d H:i:s') : null,
                'Waktu_Konfirmasi' => $tp && $tp->Waktu_Konfirmasi ? Carbon::parse($tp->Waktu_Konfirmasi)->format('Y-m-d H:i:s') : null,
            ];
        })->all();

        $this->urutkanDefault($mapped);

        return $mapped;
    }

    /* ================= INTERNAL ================= */

    /**
     * Buang kunci internal `_id` (Id paket mentah) sebelum kartu menyeberang.
     *
     * Seluruh modul memakai hashid di antarmuka; membiarkan ID auto-increment
     * ikut terkirim membocorkan jumlah & urutan baris tabel, dan mengundang
     * klien menebak-nebak ID lain. `_id` hanya alat kerja di dalam kelas ini.
     */
    private function tanpaId(array $kartu): array
    {
        return array_map(function ($k) {
            unset($k['_id']);

            return $k;
        }, $kartu);
    }

    /** Predikat "punya penugasan aktif" — dipakai untuk memasukkan ATAU mengecualikan. */
    private function adaTugas($q, string $kode, bool $ada = true)
    {
        $sub = function ($s) use ($kode) {
            $s->select(DB::raw(1))
                ->from('N_SOP_Penjadwalan_Detail as dt')
                ->join('N_SOP_Penjadwalan as pt', 'pt.Id_SOP_Penjadwalan', '=', 'dt.Id_SOP_Penjadwalan')
                ->whereColumn('pt.Id_SOP_Paket', 'p.Id_SOP_Paket')
                ->where('dt.Kode_Karyawan', $kode)
                ->where('dt.Flag_Aktif', SopStatus::AKTIF)
                ->where('dt.Flag_Cabut_Akses', 'T');
        };

        return $ada ? $q->whereExists($sub) : $q->whereNotExists($sub);
    }

    /**
     * Query dasar + GERBANG VISIBILITAS.
     * Semua jalur baca di kelas ini WAJIB berangkat dari sini.
     *
     * $tanpaTugas: buang paket yang sudah jadi kewajiban karyawan ini.
     * Dipakai oleh rak perpustakaan — tugas punya zonanya sendiri, dan
     * memunculkan kartu yang sama di dua tempat membuat orang mengira itu
     * dua benda berbeda. Perpustakaan = rujukan umum, bukan salinan tugas.
     */
    private function basePaket()
    {
        return DB::table('N_SOP_Master_Paket as p')
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where(fn ($q) => $q->where('p.Mode_Distribusi', 'PUBLIK')->orWhereNull('p.Mode_Distribusi'));
    }

    private function terapkanSaringan($q, array $f, string $kode): void
    {
        if (! empty($f['prioritas'])) {
            $q->whereIn('p.Prioritas', (array) $f['prioritas']);
        }

        if (! empty($f['q']) && mb_strlen(trim($f['q'])) >= 2) {
            $suka = '%'.trim($f['q']).'%';

            /*
             * Pencarian menembus sampai JUDUL & NOMOR ITEM, bukan berhenti di
             * nama paket. Orang lapangan mengetik "SOP-HSE-014" atau "prosedur
             * evakuasi" — nama paket yang memuatnya tidak mereka hafal. Daftar
             * yang hanya melihat Nama_Paket menjawab "tidak ada" untuk cara
             * mencari yang paling lazim.
             *
             * Predikatnya sengaja sama persis dengan cari(): dua definisi
             * "cocok" akan membuat rail menghitung himpunan yang berbeda dari
             * yang ditampilkan di sebelahnya.
             */
            $q->where(fn ($w) => $w->where('p.Nama_Paket', 'LIKE', $suka)
                ->orWhere('p.Kode_Paket', 'LIKE', $suka)
                ->orWhere('p.Tujuan_Ruang_Lingkup', 'LIKE', $suka)
                ->orWhereExists(function ($s) use ($suka) {
                    $s->select(DB::raw(1))
                        ->from('N_SOP_Paket_Item as si')
                        ->whereColumn('si.Id_SOP_Paket', 'p.Id_SOP_Paket')
                        ->where('si.Flag_Aktif', SopStatus::AKTIF)
                        ->where(fn ($x) => $x->where('si.Judul_Item', 'LIKE', $suka)
                            ->orWhere('si.Nomor_SOP_Resmi', 'LIKE', $suka));
                }));
        }

        /*
         * LENSA = saringan, bukan daftar yang sudah dipungut lebih dulu.
         * Sebelumnya "Baru" dan "Rak Saya" adalah dua larik berisi maksimal
         * delapan Id yang disaring di klien; begitu keduanya memberi makan
         * daftar berpaginasi dengan rail, larik itu membuat rail menghitung
         * satu himpunan dan daftar menampilkan himpunan lain.
         */
        if (($f['lensa'] ?? 'semua') === 'baru') {
            $this->predikatBaru($q);
        } elseif (($f['lensa'] ?? 'semua') === 'favorit') {
            $q->whereExists(function ($s) use ($kode) {
                $s->select(DB::raw(1))->from('N_SOP_Favorit as fv')
                    ->whereColumn('fv.Id_SOP_Paket', 'p.Id_SOP_Paket')
                    ->where('fv.Kode_Karyawan', $kode);
            });
        }

        if (! empty($f['departemen'])) {
            $deps = (array) $f['departemen'];
            $adaKosong = in_array('__kosong__', $deps, true) || in_array('Tanpa Pemilik', $deps, true) || in_array('Tanpa pemilik', $deps, true);
            $numericIds = array_values(array_filter($deps, 'is_numeric'));
            $nameStrings = array_values(array_filter($deps, fn ($x) => ! is_numeric($x) && ! in_array($x, ['__kosong__', 'Tanpa Pemilik', 'Tanpa pemilik'], true)));

            $q->where(function ($w) use ($adaKosong, $numericIds, $nameStrings) {
                $w->whereExists(function ($sub) use ($adaKosong, $numericIds, $nameStrings) {
                    $sub->select(DB::raw(1))
                        ->from('N_SOP_Paket_Item as gi')
                        ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'gi.Id_SOP_Dokumen')
                        ->whereColumn('gi.Id_SOP_Paket', 'p.Id_SOP_Paket')
                        ->where('gi.Flag_Aktif', SopStatus::AKTIF)
                        ->where('dok.Flag_Aktif', SopStatus::AKTIF)
                        ->where(function ($sw) use ($adaKosong, $numericIds, $nameStrings) {
                            $first = true;
                            if ($adaKosong) {
                                $sw->whereNull('dok.Departemen_Id');
                                $first = false;
                            }
                            if (! empty($numericIds)) {
                                if ($first) {
                                    $sw->whereIn('dok.Departemen_Id', $numericIds);
                                    $first = false;
                                } else {
                                    $sw->orWhereIn('dok.Departemen_Id', $numericIds);
                                }
                            }
                            if (! empty($nameStrings)) {
                                $subQuery = function ($dSub) use ($nameStrings) {
                                    $dSub->select(DB::raw(1))
                                        ->from('N_HRIS_Master_Departemen as dpt')
                                        ->whereColumn('dpt.Id_Departemen', 'dok.Departemen_Id')
                                        ->whereIn('dpt.Nama_Departemen', $nameStrings);
                                };
                                if ($first) {
                                    $sw->whereExists($subQuery);
                                } else {
                                    $sw->orWhereExists($subQuery);
                                }
                            }
                        });
                });
            });
        }

        if (! empty($f['jenis'])) {
            $jns = (array) $f['jenis'];
            $adaKosong = in_array('__kosong__', $jns, true) || in_array('Tanpa Jenis', $jns, true) || in_array('Tanpa jenis', $jns, true) || in_array('Tanpa Dokumen', $jns, true);
            $numericIds = array_values(array_filter($jns, 'is_numeric'));
            $nameStrings = array_values(array_filter($jns, fn ($x) => ! is_numeric($x) && ! in_array($x, ['__kosong__', 'Tanpa Jenis', 'Tanpa jenis', 'Tanpa Dokumen'], true)));

            $q->where(function ($w) use ($adaKosong, $numericIds, $nameStrings) {
                $w->whereExists(function ($sub) use ($adaKosong, $numericIds, $nameStrings) {
                    $sub->select(DB::raw(1))
                        ->from('N_SOP_Paket_Item as gi')
                        ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'gi.Id_SOP_Dokumen')
                        ->whereColumn('gi.Id_SOP_Paket', 'p.Id_SOP_Paket')
                        ->where('gi.Flag_Aktif', SopStatus::AKTIF)
                        ->where('dok.Flag_Aktif', SopStatus::AKTIF)
                        ->where(function ($sw) use ($adaKosong, $numericIds, $nameStrings) {
                            $first = true;
                            if ($adaKosong) {
                                $sw->whereNull('dok.Jenis_Dokumen_Id');
                                $first = false;
                            }
                            if (! empty($numericIds)) {
                                if ($first) {
                                    $sw->whereIn('dok.Jenis_Dokumen_Id', $numericIds);
                                    $first = false;
                                } else {
                                    $sw->orWhereIn('dok.Jenis_Dokumen_Id', $numericIds);
                                }
                            }
                            if (! empty($nameStrings)) {
                                $subQuery = function ($jSub) use ($nameStrings) {
                                    $jSub->select(DB::raw(1))
                                        ->from('N_SOP_Master_Jenis_Dokumen as mjd')
                                        ->whereColumn('mjd.Id_SOP_Jenis_Dokumen', 'dok.Jenis_Dokumen_Id')
                                        ->whereIn('mjd.Nama_Jenis', $nameStrings);
                                };
                                if ($first) {
                                    $sw->whereExists($subQuery);
                                } else {
                                    $sw->orWhereExists($subQuery);
                                }
                            }
                        });
                });
            });
        }

        if (! empty($f['apqc'])) {
            $apqcs = (array) $f['apqc'];
            $adaKosong = in_array('__kosong__', $apqcs, true) || in_array('Tanpa Proses APQC', $apqcs, true) || in_array('Tanpa proses APQC', $apqcs, true);
            $numericIds = array_values(array_filter($apqcs, 'is_numeric'));
            $nameStrings = array_values(array_filter($apqcs, fn ($x) => ! is_numeric($x) && ! in_array($x, ['__kosong__', 'Tanpa Proses APQC', 'Tanpa proses APQC'], true)));

            $q->where(function ($w) use ($adaKosong, $numericIds, $nameStrings) {
                $w->whereExists(function ($sub) use ($adaKosong, $numericIds, $nameStrings) {
                    $sub->select(DB::raw(1))
                        ->from('N_SOP_Paket_Item as gi')
                        ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'gi.Id_SOP_Dokumen')
                        ->whereColumn('gi.Id_SOP_Paket', 'p.Id_SOP_Paket')
                        ->where('gi.Flag_Aktif', SopStatus::AKTIF)
                        ->where('dok.Flag_Aktif', SopStatus::AKTIF)
                        ->where(function ($sw) use ($adaKosong, $numericIds, $nameStrings) {
                            $first = true;
                            if ($adaKosong) {
                                $sw->whereNull('dok.APQC_Id');
                                $first = false;
                            }
                            if (! empty($numericIds)) {
                                if ($first) {
                                    $sw->whereIn('dok.APQC_Id', $numericIds);
                                    $first = false;
                                } else {
                                    $sw->orWhereIn('dok.APQC_Id', $numericIds);
                                }
                            }
                            if (! empty($nameStrings)) {
                                $subQuery = function ($aSub) use ($nameStrings) {
                                    $aSub->select(DB::raw(1))
                                        ->from('N_SOP_Master_APQC as map')
                                        ->whereColumn('map.Id_SOP_APQC', 'dok.APQC_Id')
                                        ->whereIn('map.Nama_Proses', $nameStrings);
                                };
                                if ($first) {
                                    $sw->whereExists($subQuery);
                                } else {
                                    $sw->orWhereExists($subQuery);
                                }
                            }
                        });
                });
            });
        }

        if (! empty($f['grup'])) {
            $this->saringGrup($q, (string) ($f['sumbu'] ?? 'departemen'), (string) $f['grup']);
        }

        // "Juga tugas saya": mempersempit rak publik ke yang kebetulan
        // ditugaskan. Memakai predikat yang sama dengan hitungan folder supaya
        // "punya tugas" tak pernah punya dua definisi yang bisa melenceng.
        if (! empty($f['wajib'])) {
            $this->adaTugas($q, $kode, true);
        }
    }

    /**
     * Persempit ke satu kelompok rail.
     *
     * Dicocokkan lewat NAMA, bukan Id, karena nama itulah yang dipegang rail —
     * dan rail yang sama melayani halaman yang mengelompokkan di memori, tempat
     * Id kelompok memang tak pernah ada.
     *
     * KELOMPOK "TANPA …" PUNYA DUA WUJUD dan keduanya harus ikut: paket yang
     * memuat dokumen belum bertuan, DAN paket yang tak memuat dokumen sama
     * sekali (isinya kuis/tautan saja). Melupakan yang kedua membuat paket itu
     * terlihat di "Semua" lalu lenyap tanpa pesan begitu kelompok mana pun
     * dipilih.
     */
    private function saringGrup($q, string $sumbu, string $grup): void
    {
        $svc = new SopIdentitasDokumenService;
        $kolom = $svc->kolom($sumbu);

        $dokumenPaket = function ($s) {
            $s->select(DB::raw(1))
                ->from('N_SOP_Paket_Item as gi')
                ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'gi.Id_SOP_Dokumen')
                ->whereColumn('gi.Id_SOP_Paket', 'p.Id_SOP_Paket')
                ->where('gi.Flag_Aktif', SopStatus::AKTIF)
                ->where('dok.Flag_Aktif', SopStatus::AKTIF);

            return $s;
        };

        if ($grup === $svc->labelKosong($sumbu)) {
            $q->where(function ($w) use ($dokumenPaket, $kolom) {
                $w->whereExists(fn ($s) => $dokumenPaket($s)->whereNull($kolom))
                    ->orWhereNotExists(fn ($s) => $dokumenPaket($s));
            });

            return;
        }

        [$tabel, $alias, $fk, $nama] = match ($sumbu) {
            'jenis' => ['N_SOP_Master_Jenis_Dokumen', 'mjd', 'mjd.Id_SOP_Jenis_Dokumen', 'mjd.Nama_Jenis'],
            'apqc' => ['N_SOP_Master_APQC', 'map', 'map.Id_SOP_APQC', 'map.Nama_Proses'],
            default => ['N_HRIS_Master_Departemen', 'mdp', 'mdp.Id_Departemen', 'mdp.Nama_Departemen'],
        };

        $q->whereExists(fn ($s) => $dokumenPaket($s)
            ->join("$tabel as $alias", $fk, '=', $kolom)
            ->where($nama, $grup));
    }

    /**
     * Rekap kelompok atas SELURUH himpunan tersaring — bukan atas halaman.
     *
     * Ini setengah lain dari janji "hitungan harus jujur" di kepala berkas: baris
     * yang dikirim memang dipotong per halaman, tetapi angka di rail dihitung
     * atas semuanya. Rail yang menghitung 12 baris yang kebetulan termuat akan
     * menulis "HSE 4" untuk kelompok berisi 30.
     *
     * Bentuk kembaliannya sengaja sama dengan `bangunGrup()` di klien, supaya
     * komponen daftar tak perlu tahu halaman ini mengelompokkan di server.
     *
     * @param  array  $f  saringan yang SAMA dengan daftarPaket()
     * @return array{grup:array, tugas:int, penempatan:int}
     */
    public function rekapSumbu(string $kode, array $f, string $sumbu): array
    {
        /* Sumbu yang sedang dipakai TIDAK ikut menyaring dirinya sendiri.
           Kalau ikut, memilih "HSE" membuat rail menyusut jadi satu baris "HSE"
           dan kelompok lain lenyap — orang kehilangan cara berpindah kelompok
           tanpa mundur dulu. */
        $tanpaGrup = $f;
        unset($tanpaGrup['grup']);

        $q = $this->basePaket();
        $this->terapkanSaringan($q, $tanpaGrup, $kode);

        $ids = $q->pluck('p.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();

        if (! empty($f['status']) && $f['status'] !== 'semua') {
            $kartu = $this->kartu($this->basePaket()->whereIn('p.Id_SOP_Paket', $ids ?: [0]), $kode);
            $ids = array_values(array_map(
                fn ($k) => $k['_id'],
                array_filter($kartu, fn ($k) => $this->cocokStatus($k, $f['status'])),
            ));
        }

        return (new SopIdentitasDokumenService)->rekap($ids, $sumbu);
    }

    /**
     * Rekap struktur pohon folder untuk Public Document.
     */
    public function rekapTree(string $kode, array $f): array
    {
        $tanpaTree = $f;
        unset($tanpaTree['departemen'], $tanpaTree['jenis'], $tanpaTree['grup']);

        $q = $this->basePaket();
        $this->terapkanSaringan($q, $tanpaTree, $kode);

        $ids = $q->pluck('p.Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();

        if (! empty($f['status']) && $f['status'] !== 'semua') {
            $kartu = $this->kartu($this->basePaket()->whereIn('p.Id_SOP_Paket', $ids ?: [0]), $kode);
            $ids = array_values(array_map(
                fn ($k) => $k['_id'],
                array_filter($kartu, fn ($k) => $this->cocokStatus($k, $f['status'])),
            ));
        }

        return (new SopIdentitasDokumenService)->rekapTree($ids);
    }

    private function cocokStatus(array $kartu, string $status): bool
    {
        $p = $kartu['Persen'];

        return match ($status) {
            'belum' => $p === 0,
            'progress' => $p > 0 && $p < 100,
            'selesai' => $p >= 100,
            default => true,
        };
    }

    /**
     * Ubah query paket → kartu perpustakaan yang informatif.
     *
     * Semua pengayaan (komposisi item, versi berlaku, progres pribadi, favorit)
     * diambil BATCH untuk seluruh halaman — bukan per kartu — supaya jumlah query
     * tetap tetap berapa pun kartunya.
     *
     * @param  array|null  $urutan  Id paket (int) untuk mempertahankan urutan pemanggil
     */
    private function kartu($query, string $kode, ?array $urutan = null): array
    {
        $rows = $query->get([
            'p.Id_SOP_Paket', 'p.Nama_Paket', 'p.Kode_Paket', 'p.Prioritas',
            'p.Deskripsi', 'p.Tujuan_Ruang_Lingkup', 'p.Estimasi_Waktu_Baca_Menit', 'p.Tanggal_Publish',
            'p.Versi_Paket_Aktif', 'p.Mode_Distribusi', 'p.Cover_Path',
        ]);
        if ($rows->isEmpty()) {
            return [];
        }

        $ids = $rows->pluck('Id_SOP_Paket')->map(fn ($x) => (int) $x)->all();

        // Komposisi isi per tipe + versi/tanggal berlaku terbaru
        $komposisi = DB::table('N_SOP_Paket_Item as i')
            ->whereIn('i.Id_SOP_Paket', $ids)->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('i.Id_SOP_Paket', 'i.Tipe_Item')
            ->selectRaw('i.Id_SOP_Paket, i.Tipe_Item, COUNT(*) as jml')
            ->get()->groupBy('Id_SOP_Paket');

        // `item_baru` ikut dihitung di query yang SAMA — pertanyaan "berapa item
        // yang berubah" tak layak dibayar dengan satu query tambahan per paket.
        $versi = DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Item_Versi as v', 'v.Id_SOP_Item_Versi', '=', 'i.Id_Item_Versi_Aktif')
            ->whereIn('i.Id_SOP_Paket', $ids)->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->groupBy('i.Id_SOP_Paket')
            ->selectRaw(
                'i.Id_SOP_Paket, MAX(v.Versi_Ke) as versi, MAX(v.Tanggal_Berlaku) as berlaku,
                 MAX(v.Tanggal_Publish) as terbit,
                 SUM(CASE WHEN v.Versi_Ke > 1 AND v.Tanggal_Publish >= ? THEN 1 ELSE 0 END) as item_baru',
                [Carbon::today()->subDays(self::HARI_BARU)],
            )
            ->get()->keyBy('Id_SOP_Paket');

        // Progres pribadi: wajib vs sudah dikonfirmasi
        $progres = DB::table('N_SOP_Progress as g')
            ->join('N_SOP_Penjadwalan_Detail as d', 'd.Id_SOP_Penjadwalan_Detail', '=', 'g.Id_SOP_Penjadwalan_Detail')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->whereIn('pj.Id_SOP_Paket', $ids)
            ->where('g.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('g.Status', '<>', 'DIGANTIKAN')
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw("pj.Id_SOP_Paket,
                COUNT(*) as total,
                SUM(CASE WHEN g.Status IN ('SELESAI','DIKONFIRMASI') THEN 1 ELSE 0 END) as selesai,
                MAX(g.Waktu_Buka) as terakhir_buka,
                MAX(g.Waktu_Konfirmasi) as terakhir_konfirmasi,
                MAX(d.Tanggal_Selesai) as tanggal_selesai,
                MAX(COALESCE(pj.Tanggal_Assign, d.Created_At)) as tanggal_assign")
            ->get()->keyBy('Id_SOP_Paket');

        $favorit = DB::table('N_SOP_Favorit')->where('Kode_Karyawan', $kode)
            ->whereIn('Id_SOP_Paket', $ids)->pluck('Id_SOP_Paket')
            ->map(fn ($x) => (int) $x)->all();

        $wajibAssign = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->whereIn('pj.Id_SOP_Paket', $ids)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->groupBy('pj.Id_SOP_Paket')
            ->selectRaw('pj.Id_SOP_Paket, MAX(COALESCE(pj.Tanggal_Assign, d.Created_At)) as tanggal_assign, MAX(d.Tanggal_Selesai) as tanggal_selesai')
            ->get()->keyBy('Id_SOP_Paket');

        $wajib = array_keys($wajibAssign->all());

        $batasBaru = Carbon::today()->subDays(self::HARI_BARU);

        /* Identitas dokumen (departemen · jenis · APQC) yang membuat rail
           "Kelompokkan per" bisa bekerja di halaman ini. Sumbernya kelas yang
           sama dengan Functional Document, bukan salinan kedua. */
        $identitas = (new SopIdentitasDokumenService)->perPaket($ids);
        $cover = new SopPaketService;

        $kartu = $rows->map(function ($p) use ($komposisi, $versi, $progres, $favorit, $wajib, $wajibAssign, $batasBaru, $identitas, $cover) {
            $id = (int) $p->Id_SOP_Paket;
            $v = $versi[$id] ?? null;
            $g = $progres[$id] ?? null;

            $isi = [];
            foreach ($komposisi[$id] ?? [] as $c) {
                $isi[$c->Tipe_Item] = (int) $c->jml;
            }

            $terbitBaru = $p->Tanggal_Publish && Carbon::parse($p->Tanggal_Publish)->gte($batasBaru);
            $versiBaru = $v && $v->versi > 1 && $v->terbit && Carbon::parse($v->terbit)->gte($batasBaru);

            /**
             * Dua penanda yang sengaja saling meniadakan:
             *   BARU       — belum pernah dibuka, dan masuk jendela 30 hari.
             *   DIPERBARUI — pernah dibuka, lalu isinya berubah SETELAH itu.
             */
            $pernahBuka = (bool) ($g && $g->terakhir_buka);
            $berubahSejakDibuka = $pernahBuka
                && $v && $v->terbit
                && Carbon::parse($v->terbit)->gt(Carbon::parse($g->terakhir_buka));

            $ident = $identitas[$id] ?? ['departemen' => [], 'jenis' => [], 'apqc' => []];

            /*
             * BENTUKNYA SENGAJA SAMA DENGAN KARTU DI FUNCTIONAL DOCUMENT.
             */
            return [
                '_id' => $id, // internal, dibuang sebelum keluar
                'Id_SOP_Paket' => $this->enc($id),
                'Nama_Paket' => $p->Nama_Paket,
                'Kode_Paket' => $p->Kode_Paket,
                'Prioritas' => $p->Prioritas,
                'Deskripsi' => $p->Deskripsi ?: $p->Tujuan_Ruang_Lingkup,
                'Tujuan' => $p->Tujuan_Ruang_Lingkup,
                'Estimasi' => $p->Estimasi_Waktu_Baca_Menit ? (int) $p->Estimasi_Waktu_Baca_Menit : null,
                'Cover_Url' => $cover->coverUrl($p->Cover_Path ?? null),
                'Versi_Paket' => $p->Versi_Paket_Aktif !== null ? (int) $p->Versi_Paket_Aktif : ($v ? (int) $v->versi : 1),

                // Bernilai JAMAK: kepemilikan melekat pada DOKUMEN, sedangkan
                // kartu ini mewakili PAKET.
                'Departemen' => $ident['departemen'],
                'Jenis_Dokumen' => $ident['jenis'],
                'Apqc' => $ident['apqc'],

                'Isi' => $isi,
                'Total_Dokumen' => (int) ($isi['DOKUMEN'] ?? 0),
                'Total_Kuis' => (int) ($isi['KUIS'] ?? 0),
                'Total_Item' => array_sum($isi),
                'Item_Tugas' => $g ? (int) $g->total : 0,
                'Item_Selesai' => $g ? (int) $g->selesai : 0,
                'Persen' => $g && $g->total > 0 ? (int) round($g->selesai / $g->total * 100) : 0,

                'Versi' => $v ? (int) $v->versi : null,
                'Berlaku' => $v && $v->berlaku ? Carbon::parse($v->berlaku)->toDateString() : null,
                'Wajib' => in_array($id, $wajib, true),
                'Publik' => ($p->Mode_Distribusi ?? 'PUBLIK') === 'PUBLIK',
                'Favorit' => in_array($id, $favorit, true),
                'Pernah_Buka' => $pernahBuka,
                'Baru' => ! $pernahBuka && ($terbitBaru || $versiBaru),
                'Diperbarui' => $berubahSejakDibuka,
                'Item_Baru' => $v ? (int) $v->item_baru : 0,
                'Tanggal_Assign' => ($g && $g->tanggal_assign) ? Carbon::parse($g->tanggal_assign)->format('Y-m-d H:i:s') : (isset($wajibAssign[$id]) && $wajibAssign[$id]->tanggal_assign ? Carbon::parse($wajibAssign[$id]->tanggal_assign)->format('Y-m-d H:i:s') : null),
                'Tanggal_Selesai' => ($g && $g->tanggal_selesai) ? Carbon::parse($g->tanggal_selesai)->format('Y-m-d H:i:s') : (($g && $g->terakhir_konfirmasi) ? Carbon::parse($g->terakhir_konfirmasi)->format('Y-m-d H:i:s') : (isset($wajibAssign[$id]) && $wajibAssign[$id]->tanggal_selesai ? Carbon::parse($wajibAssign[$id]->tanggal_selesai)->format('Y-m-d H:i:s') : null)),
                'Terakhir_Buka' => $g && $g->terakhir_buka ? Carbon::parse($g->terakhir_buka)->format('Y-m-d H:i:s') : null,
                'Terakhir_Konfirmasi' => $g && $g->terakhir_konfirmasi ? Carbon::parse($g->terakhir_konfirmasi)->format('Y-m-d H:i:s') : null,
                'Tanggal_Publish' => $p->Tanggal_Publish ? Carbon::parse($p->Tanggal_Publish)->format('Y-m-d H:i:s') : null,
            ];
        })->all();

        if ($urutan) {
            $pos = array_flip($urutan);
            usort($kartu, fn ($a, $b) => ($pos[$a['_id']] ?? 999) <=> ($pos[$b['_id']] ?? 999));
        }

        return $kartu;
    }
}
