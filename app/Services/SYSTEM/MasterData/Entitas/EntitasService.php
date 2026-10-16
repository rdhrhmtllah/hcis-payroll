<?php

namespace App\Services\SYSTEM\MasterData\Entitas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Master Entitas Perusahaan — badan hukum dalam grup.
 *
 * KENAPA DI SYSTEM, BUKAN DI SOP. `Kode_Perusahaan` hadir di 141 tabel pada basis
 * data ini dan tidak pernah punya tabel master; entitas adalah master lintas
 * modul, bukan milik satu fitur. SOP kebetulan konsumen pertamanya. Menaruhnya
 * di bawah LMS/SOP akan memaksa modul berikutnya mengimpor dari domain yang tak
 * ada hubungannya — atau, lebih buruk, menyalin daftarnya.
 *
 * BUKAN daftar lokasi. Cabang dan site sudah punya `N_HRIS_Master_Lokasi`
 * (14 baris terisi); entitas menjawab pertanyaan yang berbeda, yaitu badan hukum.
 *
 * TIDAK menyimpan hubungan apa pun ke divisi/sub-divisi. Keduanya sumbu yang
 * berdiri sendiri: memilih ENB tidak mempersempit daftar departemen, dan tidak
 * ada departemen yang "milik" sebuah entitas. Itu keputusan sadar, bukan
 * kelalaian — lihat plans/hris/2026-08-22-master-entitas-design.md.
 */
class EntitasService
{
    private string $table = 'N_HRIS_Master_Entitas';

    public const AKTIF = 'Y';

    /**
     * Opsi untuk dropdown, terurut sesuai Urutan.
     *
     * `Cakup_Semua` menandai entitas payung (yang tak punya induk sementara ada
     * entitas lain menunjuknya). Layar memakainya untuk menulis label
     * "(seluruh entitas)" di titik memilih — arti payung harus terbaca saat
     * dipilih, bukan di dokumentasi yang tak pernah dibuka.
     */
    public function opsi(): array
    {
        $rows = DB::table($this->table)
            ->where('Flag_Aktif', self::AKTIF)
            ->orderBy('Urutan')->orderBy('Kode_Entitas')
            ->get(['Id_Entitas', 'Kode_Entitas', 'Kode_Sebagai_Pemilik', 'Nama_Entitas',
                'Nama_Singkat', 'Warna', 'Logo_Path', 'Induk_Entitas_Id']);

        $punyaAnak = DB::table($this->table)
            ->where('Flag_Aktif', self::AKTIF)
            ->whereNotNull('Induk_Entitas_Id')
            ->distinct()->pluck('Induk_Entitas_Id')->all();

        return $rows->map(fn ($r) => [
            'Id' => Hashids::connection('custom')->encode($r->Id_Entitas),
            'Kode_Entitas' => $r->Kode_Entitas,
            /* Kode yang AKAN TERCETAK bila entitas ini dipilih sebagai pemilik.
               Dikirim sudah teresolusi — layar tak boleh mengulang aturan
               jatuh-baliknya, sebab aturan yang disalin ke JavaScript akan
               menyimpang dari yang dipakai server saat merakit nomornya. */
            'Kode_Pemilik' => self::kodePemilik($r),
            'Nama_Entitas' => $r->Nama_Entitas,
            'Nama_Singkat' => $r->Nama_Singkat ?: $r->Kode_Entitas,
            'Warna' => $r->Warna,
            'Logo_Url' => self::logoUrl($r->Logo_Path),
            'Cakup_Semua' => in_array((int) $r->Id_Entitas, array_map('intval', $punyaAnak), true),
        ])->all();
    }

    /**
     * Kode yang DICETAK ketika entitas berperan sebagai PEMILIK.
     *
     * Nomor dokumen memuat entitas pada dua posisi dengan arti yang berbeda:
     * segmen pemilik menyebut badan hukum yang bertanggung jawab, segmen ruang
     * lingkup menyatakan berlaku di mana. EHG di posisi lingkup berarti
     * "seluruh entitas" — bukan "perusahaan holding" — dan standar penomoran
     * perusahaan memakai kode yang lain untuk pemilik-holding.
     *
     * SATU TEMPAT MENJAWABNYA. Segmen pemilik dirakit di tiga berkas berbeda;
     * kalau aturannya disalin, ketiganya akan menyimpang dan nomor di daftar,
     * di detail, dan yang tersimpan akan berbeda satu sama lain tanpa satu pun
     * pesan galat.
     *
     * @param  object|array|null  $entitas  baris master entitas
     */
    public static function kodePemilik($entitas): ?string
    {
        if ($entitas === null) {
            return null;
        }

        $a = (array) $entitas;
        $khusus = trim((string) ($a['Kode_Sebagai_Pemilik'] ?? ''));

        /* Kosong JATUH ke Kode_Entitas, tidak menjadi null. Kolom yang lupa
           diisi harus berperilaku seperti hari ini, bukan melubangi nomor. */
        return $khusus !== '' ? $khusus : (($a['Kode_Entitas'] ?? null) ?: null);
    }

    /**
     * Normalisasi kode cetak pemilik sebelum disimpan.
     *
     * Kosong menjadi NULL, bukan string kosong: dua cara menuliskan "tak
     * diisi" berarti dua cabang yang harus diperiksa setiap pembaca, dan yang
     * lupa memeriksa salah satunya akan mencetak nomor tanpa segmen pemilik.
     *
     * Kode yang PERSIS SAMA dengan Kode_Entitas juga dipulangkan ke NULL —
     * menyimpannya hanya menciptakan salinan yang bisa menyimpang dari
     * aslinya.
     */
    private static function rapikanKodePemilik($nilai, ?string $kodeEntitas = null): ?string
    {
        $k = strtoupper(trim((string) ($nilai ?? '')));

        if ($k === '') {
            return null;
        }

        return $kodeEntitas !== null && $k === strtoupper(trim($kodeEntitas)) ? null : $k;
    }

    /** Baris entitas apa adanya. Null bila tak ada atau nonaktif. */
    public function cari(int $id, bool $hanyaAktif = true): ?object
    {
        return DB::table($this->table)
            ->where('Id_Entitas', $id)
            ->when($hanyaAktif, fn ($q) => $q->where('Flag_Aktif', self::AKTIF))
            ->first();
    }

    public function ada(int $id): bool
    {
        return $this->cari($id) !== null;
    }

    /**
     * Entitas yang tercakup oleh sebuah entitas: dirinya sendiri plus seluruh
     * anaknya.
     *
     * Dihitung dari `Induk_Entitas_Id`, BUKAN dari daftar kode yang dihafal.
     * Itulah alasan hirarkinya disimpan sebagai kolom: menambah PT baru di bawah
     * payung cukup satu baris insert, dan seluruh laporan cakupan ikut benar
     * tanpa satu baris kode pun berubah.
     *
     * @return int[]
     */
    public function cakupan(int $id): array
    {
        $anak = DB::table($this->table)
            ->where('Induk_Entitas_Id', $id)
            ->where('Flag_Aktif', self::AKTIF)
            ->pluck('Id_Entitas')->map(fn ($v) => (int) $v)->all();

        return array_values(array_unique(array_merge([$id], $anak)));
    }

    /**
     * Nama siap tampil untuk sekumpulan id sekaligus.
     *
     * Dipakai daftar dokumen supaya 25 baris tidak melahirkan 25 query untuk
     * data yang isinya empat baris saja.
     *
     * @return array<int, object>
     */
    public function peta(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        return DB::table($this->table)
            ->whereIn('Id_Entitas', $ids)
            /* Kode_Sebagai_Pemilik ikut karena daftar dokumen merakit nomornya
               dari peta ini, bukan dari cari() per baris. Tanpa kolom ini,
               nomor di kartu daftar akan berbeda dari nomor di panel detail. */
            ->get(['Id_Entitas', 'Kode_Entitas', 'Kode_Sebagai_Pemilik', 'Nama_Entitas',
                'Nama_Singkat', 'Warna', 'Logo_Path'])
            ->keyBy('Id_Entitas')
            ->all();
    }

    /* ── Pengelolaan (halaman SYSTEM, gerbang system.admin) ──────────────── */

    /**
     * List paginated + search, beserta jumlah dokumen yang memakainya.
     *
     * Jumlah pemakai ikut dihitung supaya tombol nonaktifkan bisa dimatikan di
     * layar, bukan baru ditolak setelah admin menekannya.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: int}
     */
    public function paginate(?string $search, int $page, int $limit): array
    {
        $query = DB::table($this->table)->where('Flag_Aktif', self::AKTIF);

        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('Nama_Entitas', 'LIKE', "%{$search}%")
                    ->orWhere('Kode_Entitas', 'LIKE', "%{$search}%");
            });
        }

        $total = $query->count();

        $rows = $query->orderBy('Urutan')->orderBy('Kode_Entitas')
            ->offset(($page - 1) * $limit)->limit($limit)->get();

        $induk = $this->peta($rows->pluck('Induk_Entitas_Id')->all());
        $pakai = $this->jumlahPemakai($rows->pluck('Id_Entitas')->all());

        $rows = $rows->map(function ($r) use ($induk, $pakai) {
            $id = (int) $r->Id_Entitas;
            $r->Induk_Kode = $r->Induk_Entitas_Id ? ($induk[$r->Induk_Entitas_Id]->Kode_Entitas ?? null) : null;
            $r->Induk_Entitas_Id = $r->Induk_Entitas_Id
                ? Hashids::connection('custom')->encode($r->Induk_Entitas_Id)
                : null;
            $r->Jumlah_Dokumen = $pakai[$id] ?? 0;
            $r->Logo_Url = self::logoUrl($r->Logo_Path);
            $r->Id_Entitas = Hashids::connection('custom')->encode($id);

            return $r;
        });

        return [$rows, $total];
    }

    public function create(array $data, int $userId): int
    {
        $this->pastikanKodeUnik($data['Kode_Entitas']);
        $induk = $this->indukSah($data['Induk_Entitas_Id'] ?? null, null);

        return DB::table($this->table)->insertGetId([
            'Kode_Entitas' => strtoupper(trim($data['Kode_Entitas'])),
            'Kode_Sebagai_Pemilik' => self::rapikanKodePemilik(
                $data['Kode_Sebagai_Pemilik'] ?? null, $data['Kode_Entitas']
            ),
            'Nama_Entitas' => $data['Nama_Entitas'],
            'Nama_Singkat' => $data['Nama_Singkat'] ?? null,
            'Warna' => $data['Warna'] ?? null,
            'Logo_Path' => $data['Logo_Path'] ?? null,
            'Induk_Entitas_Id' => $induk,
            'Urutan' => (int) ($data['Urutan'] ?? 0),
            'Flag_Aktif' => self::AKTIF,
            'Created_By' => $userId,
            'Created_At' => now(),
        ], 'Id_Entitas');
    }

    public function update(int $id, array $data, int $userId): bool
    {
        $kini = $this->cari($id);
        if (! $kini) {
            return false;
        }

        $this->pastikanKodeUnik($data['Kode_Entitas'], $id);

        DB::table($this->table)->where('Id_Entitas', $id)->update([
            /* Kode TIDAK ikut diubah. Ia rujukan tetap yang dipakai seed dan
               integrasi; membiarkannya berpindah berarti membiarkan rujukan
               menunjuk entitas lain tanpa satu pun jejak. */
            'Nama_Entitas' => $data['Nama_Entitas'],
            /* BOLEH diubah, tak seperti Kode_Entitas. Kode identitas terkunci
               karena ia rujukan tetap; kode cetak pemilik justru ada supaya
               standar penomoran bisa menyusul tanpa rilis. */
            /* Dibandingkan dengan kode TERSIMPAN, bukan kode yang dikirim
               formulir: Kode_Entitas terkunci saat update, jadi kode kiriman
               belum tentu kode yang sesungguhnya berlaku. */
            'Kode_Sebagai_Pemilik' => self::rapikanKodePemilik(
                $data['Kode_Sebagai_Pemilik'] ?? null, $kini->Kode_Entitas
            ),
            'Nama_Singkat' => $data['Nama_Singkat'] ?? null,
            'Warna' => $data['Warna'] ?? null,
            'Logo_Path' => $data['Logo_Path'] ?? null,
            'Induk_Entitas_Id' => $this->indukSah($data['Induk_Entitas_Id'] ?? null, $id),
            'Urutan' => (int) ($data['Urutan'] ?? 0),
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        return true;
    }

    /**
     * Nonaktifkan entitas.
     *
     * @return int jumlah pemakai yang menghalangi; 0 berarti berhasil.
     *
     * Menghitung DUA jenis pemakai: dokumen yang menunjuknya (pemilik atau
     * ruang lingkup) dan entitas anak yang bernaung di bawahnya. Menonaktifkan
     * payung yang masih punya anak akan membuat anaknya menunjuk induk yang
     * tak lagi muncul di mana pun — dan cakupan()-nya diam-diam menyusut.
     */
    public function softDelete(int $id, int $userId): int
    {
        $dipakai = $this->jumlahPemakai([$id])[$id] ?? 0;
        $anak = DB::table($this->table)
            ->where('Induk_Entitas_Id', $id)->where('Flag_Aktif', self::AKTIF)->count();

        if ($dipakai + $anak > 0) {
            return $dipakai + $anak;
        }

        DB::table($this->table)->where('Id_Entitas', $id)->update([
            'Flag_Aktif' => 'T',
            'Updated_By' => $userId,
            'Updated_At' => now(),
        ]);

        return 0;
    }

    /**
     * Induk yang boleh dipasang.
     *
     * MENOLAK SIKLUS. Entitas tak boleh menjadi induk dirinya sendiri maupun
     * induk salah satu leluhurnya; tanpa penjaga ini cakupan() akan berputar
     * dan permintaannya menggantung sampai timeout — kegagalan yang muncul jauh
     * dari tempat kesalahannya dibuat.
     *
     * @throws \DomainException
     */
    private function indukSah($indukId, ?int $idSendiri): ?int
    {
        if ($indukId === null || $indukId === '') {
            return null;
        }

        $indukId = (int) $indukId;

        if (! $this->ada($indukId)) {
            throw new \DomainException('Entitas induk yang dipilih tidak ada atau sudah dinonaktifkan.');
        }

        if ($idSendiri !== null) {
            if ($indukId === $idSendiri) {
                throw new \DomainException('Entitas tidak bisa menjadi induk dirinya sendiri.');
            }

            /* Telusuri ke atas dari calon induk. Kalau bertemu diri sendiri,
               pemasangan ini akan menutup lingkaran. */
            $jejak = $indukId;
            $batas = 0;
            while ($jejak !== null && $batas++ < 50) {
                if ($jejak === $idSendiri) {
                    throw new \DomainException(
                        'Entitas ini sudah berada di atas entitas yang dipilih; '
                        .'memasangnya sebagai induk akan membuat lingkaran.');
                }
                $jejak = DB::table($this->table)->where('Id_Entitas', $jejak)->value('Induk_Entitas_Id');
                $jejak = $jejak !== null ? (int) $jejak : null;
            }
        }

        return $indukId;
    }

    /** @throws \DomainException */
    private function pastikanKodeUnik(string $kode, ?int $kecuali = null): void
    {
        $kode = strtoupper(trim($kode));

        $bentrok = DB::table($this->table)
            ->where('Kode_Entitas', $kode)
            ->when($kecuali, fn ($q) => $q->where('Id_Entitas', '<>', $kecuali))
            ->value('Nama_Entitas');

        if ($bentrok !== null) {
            throw new \DomainException(
                'Kode entitas "'.$kode.'" sudah dipakai "'.$bentrok.'". '
                .'Kode adalah rujukan tetap, jadi satu kode hanya boleh menunjuk satu entitas.');
        }
    }

    /**
     * Berapa dokumen menunjuk tiap entitas, sebagai pemilik ATAU ruang lingkup.
     *
     * @return array<int, int>
     */
    private function jumlahPemakai(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        $out = array_fill_keys(array_map('intval', $ids), 0);

        foreach (['Entitas_Pemilik_Id', 'Entitas_Lingkup_Id'] as $kolom) {
            /* Alias eksplisit. `pluck(DB::raw('COUNT(*)'), ...)` memakai teks
               "COUNT(*)" sebagai nama properti hasil, dan SQL Server tak
               mengembalikan kolom bernama begitu — hasilnya
               "Undefined property: stdClass::$COUNT(*)" yang baru muncul saat
               ADA baris yang cocok, bukan saat hitungannya nol. */
            $baris = DB::table('N_SOP_Dokumen')
                ->whereIn($kolom, $ids)
                ->where('Flag_Aktif', self::AKTIF)
                ->groupBy($kolom)
                ->selectRaw("{$kolom} as id_entitas, COUNT(*) as n")
                ->pluck('n', 'id_entitas');

            foreach ($baris as $id => $n) {
                $out[(int) $id] = ($out[(int) $id] ?? 0) + (int) $n;
            }
        }

        return $out;
    }

    /**
     * URL siap tampil untuk sebuah logo.
     *
     * `Logo_Path` menampung DUA bentuk, dan itu disengaja: path GCS hasil
     * unggah, atau URL/berkas publik yang sudah ada di tempat lain. Yang sudah
     * berupa URL diteruskan apa adanya; yang berupa path GCS ditandatangani.
     *
     * Kegagalan menandatangani TIDAK dilempar. Kredensial GCS bisa tak tersedia
     * di sebuah environment (di server dev ini path kuncinya bergaya Windows
     * dijalankan di Linux), dan membiarkan galat itu naik akan menjatuhkan
     * seluruh halaman master hanya karena sebuah logo tak bisa ditampilkan.
     */
    public static function logoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('#^(https?://|/)#', $path)) {
            return $path;
        }

        try {
            return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(20));
        } catch (\Throwable $e) {
            Log::warning('Logo entitas gagal ditandatangani: '.$e->getMessage(), ['path' => $path]);

            return null;
        }
    }

    /**
     * Struktur grup sebagai pohon, untuk HcisOrgChart.
     *
     * Dibangun dari `Induk_Entitas_Id` — sumber yang sama dengan cakupan(),
     * jadi gambar di layar tak bisa menyimpang dari angka di laporan.
     *
     * Entitas yang induknya sudah nonaktif diperlakukan sebagai akar, bukan
     * dibuang: menghilangkannya dari gambar akan membuat entitas yang sah
     * lenyap tanpa penjelasan.
     */
    public function pohon(): array
    {
        $rows = DB::table($this->table)
            ->where('Flag_Aktif', self::AKTIF)
            ->orderBy('Urutan')->orderBy('Kode_Entitas')
            ->get(['Id_Entitas', 'Kode_Entitas', 'Nama_Entitas', 'Nama_Singkat', 'Warna', 'Logo_Path', 'Induk_Entitas_Id']);

        $hidup = $rows->pluck('Id_Entitas')->map(fn ($v) => (int) $v)->all();

        $simpul = [];
        foreach ($rows as $r) {
            $simpul[(int) $r->Id_Entitas] = [
                'id' => (string) $r->Id_Entitas,
                'name' => $r->Nama_Entitas,
                'title' => $r->Kode_Entitas,
                'department' => $r->Nama_Singkat ?: $r->Kode_Entitas,
                'avatar' => self::logoUrl($r->Logo_Path),
                'color' => $r->Warna,
                'children' => [],
            ];
        }

        $akar = [];
        foreach ($rows as $r) {
            $id = (int) $r->Id_Entitas;
            $induk = $r->Induk_Entitas_Id !== null ? (int) $r->Induk_Entitas_Id : null;

            if ($induk !== null && in_array($induk, $hidup, true)) {
                $simpul[$induk]['children'][] = &$simpul[$id];
            } else {
                $akar[] = &$simpul[$id];
            }
        }

        /* Satu akar dikembalikan apa adanya; beberapa akar dibungkus simpul
           semu supaya komponen pohon tetap punya satu pangkal. Bungkusnya
           ditandai id 'root' agar layar bisa mengenalinya. */
        if (count($akar) === 1) {
            return $akar[0];
        }

        return [
            'id' => 'root',
            'name' => 'Grup Perusahaan',
            'title' => 'Seluruh entitas',
            'department' => 'Grup',
            'children' => $akar,
        ];
    }

    /** Label pendek untuk badge: nama singkat bila ada, kalau tidak kodenya. */
    public function label(?int $id): ?string
    {
        if (! $id) {
            return null;
        }

        $row = $this->cari($id, false);

        return $row ? ($row->Nama_Singkat ?: $row->Kode_Entitas) : null;
    }
}
