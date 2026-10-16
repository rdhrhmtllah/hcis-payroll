<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopAksesBerkas;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Form kosong (lampiran unduhan) per versi dokumen FORM.
 *
 * Berkas utama versi FORM adalah CONTOH PENGISIAN; form kosongnya diunduh
 * karyawan untuk diisi sendiri. Boleh beberapa per versi (mis. Word dan
 * Excel), karena itu tabel anak, bukan kolom.
 *
 * Izinnya TIDAK diputuskan di sini — SopDokumenService menanyakannya pada
 * SopVersiKeadaan sebagai field semu `Lampiran`, sama seperti blok pengesah.
 * Kelas ini hanya memegang bentuk datanya: validasi, penulisan, pembacaan.
 */
class SopVersiLampiranService
{
    private string $table = 'N_SOP_Item_Versi_Lampiran';

    /** Hasil cek tabel, sekali per proses. */
    private static ?bool $adaTabel = null;

    /**
     * Apakah tabelnya sudah ada (DDL-2026-158)?
     *
     * Kode bisa tayang lebih dulu daripada DDL-nya di production. Tanpa
     * penjaga ini, halaman detail paket SOP dan riwayat versi runtuh dengan
     * "Invalid object name" untuk SEMUA dokumen, bukan hanya FORM. Dengan
     * penjaga, yang hilang hanya fitur form kosongnya sampai DDL dijalankan.
     */
    public function tersedia(): bool
    {
        return self::$adaTabel ??= Schema::hasTable($this->table);
    }

    /**
     * Rapikan kiriman layar menjadi daftar yang sah dan berurutan.
     *
     * Urutan kiriman ADALAH urutan tampil — layar menggeser baris, bukan
     * mengetik angka, jadi angka urutan dari klien tak dipercaya.
     *
     * @return list<array{Id_Master_Konten:int, Urutan:int}>
     *
     * @throws \DomainException
     */
    public function rapikan($mentah): array
    {
        $ids = collect(is_array($mentah) ? $mentah : [])
            ->map(fn ($x) => (int) (is_array($x) ? ($x['Id_Master_Konten'] ?? 0) : $x))
            ->values();

        if ($ids->contains(fn ($id) => $id <= 0)) {
            throw new \DomainException('Ada form kosong yang berkasnya tak dikenali. Pilih ulang dari Library.');
        }
        if ($ids->count() !== $ids->unique()->count()) {
            throw new \DomainException('Form kosong yang sama dipasang dua kali.');
        }
        if ($ids->isEmpty()) {
            return [];
        }
        if (! $this->tersedia()) {
            throw new \DomainException('Fitur form kosong belum aktif di server ini (DDL-2026-158 belum dijalankan).');
        }

        $konten = DB::table('N_LMS_Master_Konten')
            ->whereIn('Id_Master_Konten', $ids->all())
            ->where('Scope', 'SOP')
            ->get(['Id_Master_Konten', 'Tipe_Konten'])
            ->keyBy(fn ($k) => (int) $k->Id_Master_Konten);

        foreach ($ids as $id) {
            $k = $konten->get($id);
            if (! $k) {
                throw new \DomainException('Berkas form kosong tidak ditemukan di Library Konten SOP.');
            }
            if (! SopTipeItem::kontenCocok(SopTipeItem::FORM, $k->Tipe_Konten)) {
                throw new \DomainException('Jenis berkas form kosong tidak didukung.');
            }
        }

        return $ids->map(fn ($id, $i) => ['Id_Master_Konten' => $id, 'Urutan' => $i])->all();
    }

    /** @return list<int> id konten aktif, berurutan */
    public function aktif(int $idVersi): array
    {
        if (! $this->tersedia()) {
            return [];
        }

        return DB::table($this->table)
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')->orderBy('Id_Lampiran')
            ->pluck('Id_Master_Konten')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * Tulis SELISIH: yang tetap diperbarui urutannya, yang dilepas
     * dinonaktifkan (barisnya tetap sebagai jejak), yang baru di-insert.
     * Pemanggil yang memegang transaksi.
     *
     * @param  list<array{Id_Master_Konten:int, Urutan:int}>  $daftar  hasil rapikan()
     * @return array{masuk: list<int>, keluar: list<int>}
     */
    public function simpan(int $idVersi, array $daftar, int $userId): array
    {
        if (! $this->tersedia()) {
            return ['masuk' => [], 'keluar' => []];
        }

        $lama = $this->aktif($idVersi);
        $baru = array_column($daftar, 'Id_Master_Konten');

        $keluar = array_values(array_diff($lama, $baru));
        $masuk = array_values(array_diff($baru, $lama));

        if ($keluar !== []) {
            DB::table($this->table)
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->whereIn('Id_Master_Konten', $keluar)
                ->update(['Flag_Aktif' => SopStatus::INAKTIF, 'Updated_At' => now(), 'Updated_By' => $userId]);
        }

        foreach ($daftar as $baris) {
            if (in_array($baris['Id_Master_Konten'], $masuk, true)) {
                DB::table($this->table)->insert([
                    'Id_SOP_Item_Versi' => $idVersi,
                    'Id_Master_Konten' => $baris['Id_Master_Konten'],
                    'Urutan' => $baris['Urutan'],
                    'Flag_Aktif' => SopStatus::AKTIF,
                    'Created_At' => now(),
                    'Created_By' => $userId,
                ]);

                continue;
            }

            DB::table($this->table)
                ->where('Id_SOP_Item_Versi', $idVersi)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->where('Id_Master_Konten', $baris['Id_Master_Konten'])
                ->where('Urutan', '<>', $baris['Urutan'])
                ->update(['Urutan' => $baris['Urutan'], 'Updated_At' => now(), 'Updated_By' => $userId]);
        }

        return ['masuk' => $masuk, 'keluar' => $keluar];
    }

    /**
     * Lampiran aktif untuk banyak versi sekaligus — satu kueri untuk satu
     * halaman, bukan satu per versi.
     *
     * @return array<int, list<object>> keyed Id_SOP_Item_Versi; tiap objek:
     *                                  Id_Lampiran, Id_SOP_Item_Versi, Id_Master_Konten, Urutan, Judul, Ext, Ukuran_Byte, File_Path
     */
    public function untukVersi(iterable $idVersi): array
    {
        $ids = collect($idVersi)->filter()->map(fn ($v) => (int) $v)->unique()->values();
        if ($ids->isEmpty() || ! $this->tersedia()) {
            return [];
        }

        return DB::table($this->table.' as l')
            ->join('N_LMS_Master_Konten as k', 'k.Id_Master_Konten', '=', 'l.Id_Master_Konten')
            ->leftJoin('N_LMS_Master_Konten_Berkas as b', 'b.Id_Master_Konten', '=', 'l.Id_Master_Konten')
            ->whereIn('l.Id_SOP_Item_Versi', $ids->all())
            ->where('l.Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('l.Urutan')->orderBy('l.Id_Lampiran')
            ->get(['l.Id_Lampiran', 'l.Id_SOP_Item_Versi', 'l.Id_Master_Konten', 'l.Urutan',
                'k.Judul_Konten as Judul', 'b.Ext', 'b.Ukuran_Byte', 'b.File_Path'])
            /* Satu konten bisa punya lebih dari satu baris berkas; yang
               dipakai SopKontenViewService pun baris pertama. */
            ->unique('Id_Lampiran')
            ->groupBy(fn ($l) => (int) $l->Id_SOP_Item_Versi)
            ->map(fn ($g) => $g->values()->all())
            ->all();
    }

    /**
     * Lengkapi baris versi dengan ISI-nya: jenis & ukuran berkas utama, dan
     * form kosong yang terpasang.
     *
     * Dipakai kedua cabang panel versi Builder (versi hidup dan overlay draft
     * paket) supaya keduanya menyebut isi yang sama. Tanpa ini admin hanya
     * melihat satu judul berkas, tanpa tahu apakah itu PDF dan form kosong apa
     * yang ikut diunduh karyawan.
     *
     * Wajib dipanggil SEBELUM Id_SOP_Item_Versi di-hash. Dua kueri untuk
     * seluruh baris, bukan per versi.
     *
     * @param  iterable<object>  $rows  berkolom Id_SOP_Item_Versi (int) dan Id_Master_Konten
     */
    public function lengkapiIsi(iterable $rows): void
    {
        $rows = collect($rows);
        if ($rows->isEmpty()) {
            return;
        }

        /* Satu konten bisa punya lebih dari satu baris berkas; yang dipakai
           SopKontenViewService pun baris pertama. */
        $berkas = DB::table('N_LMS_Master_Konten_Berkas')
            ->whereIn('Id_Master_Konten', $rows->pluck('Id_Master_Konten')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all())
            ->orderBy('Id_Master_Konten_Berkas')
            ->get(['Id_Master_Konten', 'Ext', 'File_Path', 'Ukuran_Byte'])
            ->unique('Id_Master_Konten')
            ->keyBy(fn ($b) => (int) $b->Id_Master_Konten);

        $lampiran = $this->untukVersi($rows->pluck('Id_SOP_Item_Versi'));

        foreach ($rows as $v) {
            $b = $berkas[(int) ($v->Id_Master_Konten ?? 0)] ?? null;
            $v->Konten_Ext = SopAksesBerkas::ekstensi($b->Ext ?? null, $b->File_Path ?? null);
            $v->Ukuran_File = isset($b->Ukuran_Byte) ? (int) $b->Ukuran_Byte : null;
            $v->Lampiran = collect($lampiran[(int) $v->Id_SOP_Item_Versi] ?? [])->map(fn ($l) => [
                'Nama' => $l->Judul,
                'Ext' => SopAksesBerkas::ekstensi($l->Ext ?? null, $l->File_Path ?? null),
                'Ukuran' => $l->Ukuran_Byte !== null ? (int) $l->Ukuran_Byte : null,
            ])->all();
        }
    }

    /** Konten Library milik lampiran aktif sebuah versi, atau null. */
    public function kontenLampiran(int $idLampiran, int $idVersi): ?int
    {
        if (! $this->tersedia()) {
            return null;
        }

        $id = DB::table($this->table)
            ->where('Id_Lampiran', $idLampiran)
            ->where('Id_SOP_Item_Versi', $idVersi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->value('Id_Master_Konten');

        return $id !== null ? (int) $id : null;
    }

    /**
     * Berkas form kosong sebuah versi untuk dikemas ke ZIP: jalur di disk
     * GCS dan nama di dalam arsip (judul + ekstensi, kembar diberi nomor).
     * Konten tanpa berkas GCS (mis. video Bunny) dilewati.
     *
     * @return list<array{path: string, nama: string}>
     */
    public function berkasZip(int $idVersi): array
    {
        $dipakai = [];
        $hasil = [];

        foreach ($this->untukVersi([$idVersi])[$idVersi] ?? [] as $l) {
            $path = trim((string) ($l->File_Path ?? ''));
            if ($path === '' || preg_match('#^https?://#i', $path)) {
                continue;
            }

            $ext = strtolower(trim((string) ($l->Ext ?? ''), '.')) ?: strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $dasar = trim(preg_replace('#[\\\\/:*?"<>|]+#', ' ', (string) ($l->Judul ?? ''))) ?: 'Form kosong';
            $nama = $dasar.($ext !== '' ? '.'.$ext : '');
            for ($n = 2; isset($dipakai[strtolower($nama)]); $n++) {
                $nama = $dasar.' ('.$n.')'.($ext !== '' ? '.'.$ext : '');
            }
            $dipakai[strtolower($nama)] = true;

            $hasil[] = ['path' => $path, 'nama' => $nama];
        }

        return $hasil;
    }
}
