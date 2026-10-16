<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master tingkat prioritas paket SOP.
 *
 * SATU SUMBER KEBENARAN untuk dua hal yang dulu tersebar: daftar nilai yang sah
 * dan URUTAN mendesaknya. Keduanya dulu ditulis ulang di tempat berbeda — CHECK
 * constraint, konstanta PHP, aturan validasi, CASE bernama di dua kueri, dan
 * konstanta warna di layar — sehingga menambah satu tingkat berarti menyentuh
 * lima tempat, dan melupakan salah satunya berarti tingkat baru yang tampak ada
 * tapi tak pernah ikut diurutkan.
 *
 * JALUR MUNDUR BUKAN BASA-BASI. Selama DDL 35 belum dijalankan, seluruh
 * pembacaan jatuh ke tiga tingkat bawaan dengan urutan dan penanda kritis yang
 * PERSIS sama dengan perilaku sebelumnya. Tak ada urutan wajib antara
 * menerbitkan kode ini dan menjalankan skripnya.
 */
class SopPrioritasService
{
    public const TABEL = 'N_SOP_Prioritas';

    /**
     * Tiga tingkat bawaan — cerminan keadaan sebelum master ada.
     *
     * Angkanya BUKAN pilihan bebas: 10/20/30 menghasilkan urutan yang sama
     * persis dengan CASE lama ('KRITIS'→0, 'TINGGI'→1, sisanya→2). DDL 35
     * menanam baris yang identik, jadi perilakunya tak berubah saat tabelnya
     * muncul.
     */
    private const BAWAAN = [
        ['Kode' => SopStatus::PRIORITAS_KRITIS, 'Nama' => 'Sangat Penting', 'Urutan' => 10, 'Warna' => '#EF4444'],
        ['Kode' => SopStatus::PRIORITAS_TINGGI, 'Nama' => 'Tinggi', 'Urutan' => 20, 'Warna' => '#F59E0B'],
        ['Kode' => SopStatus::PRIORITAS_NORMAL, 'Nama' => 'Normal', 'Urutan' => 30, 'Warna' => '#94A3B8'],
    ];

    /** Cache per-request. Dipakai berkali-kali dalam satu permintaan (validasi, urutan, label). */
    private static ?Collection $memo = null;

    private static ?bool $adaTabel = null;

    public function tersedia(): bool
    {
        return self::$adaTabel ??= Schema::hasTable(self::TABEL);
    }

    /** Buang memo — dipanggil sesudah master diubah. */
    public function lupakan(): void
    {
        self::$memo = null;
    }

    /**
     * Seluruh tingkat AKTIF, terurut dari paling mendesak.
     *
     * @return Collection<int,object>
     */
    public function semua(): Collection
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        if (! $this->tersedia()) {
            return self::$memo = collect(self::BAWAAN)->map(fn ($r) => (object) $r);
        }

        $rows = DB::table(self::TABEL)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->orderBy('Urutan')->orderBy('Kode')
            ->get(['Id_SOP_Prioritas', 'Kode', 'Nama', 'Urutan', 'Warna']);

        /* Master kosong diperlakukan seperti master belum ada. Daftar pilihan
           yang kosong akan membuat SETIAP penyimpanan paket ditolak validasi,
           dan pesannya ("prioritas tidak valid") tak akan pernah menuntun
           siapa pun ke sebab sebenarnya. */
        return self::$memo = $rows->isEmpty()
            ? collect(self::BAWAAN)->map(fn ($r) => (object) $r)
            : $rows;
    }

    /** @return list<string> kode yang sah untuk validasi */
    public function kode(): array
    {
        return $this->semua()->pluck('Kode')->all();
    }

    /** Kode default untuk paket baru — yang PALING TIDAK mendesak. */
    public function kodeBawaan(): string
    {
        return (string) ($this->semua()->last()->Kode ?? SopStatus::PRIORITAS_NORMAL);
    }

    /**
     * Potongan SQL CASE untuk mengurutkan menurut master, beserta bindingnya.
     *
     * Dipakai ORDER BY, bukan JOIN, dan itu disengaja: dua pemakainya kueri
     * agregat ber-GROUP BY yang sudah rumit, dan menambah join hanya untuk satu
     * angka urutan akan mengubah rencana kuerinya. Nilainya di-BIND, bukan
     * ditempel — kodenya memang milik kita, tapi "milik kita" bukan alasan
     * untuk menyusun SQL dari isi tabel.
     *
     * Tingkat yang tak dikenal jatuh ke 9999, yaitu paling belakang. Bekas
     * nilai dari tingkat yang sudah dinonaktifkan tak akan menyusup ke atas.
     *
     * @return array{0:string,1:array<int,mixed>}
     */
    public function urutanCase(string $kolom): array
    {
        $sql = 'CASE '.$kolom;
        $bind = [];
        foreach ($this->semua() as $r) {
            $sql .= ' WHEN ? THEN ?';
            $bind[] = $r->Kode;
            $bind[] = (int) $r->Urutan;
        }
        $sql .= ' ELSE 9999 END';

        return [$sql, $bind];
    }

    /**
     * Peta untuk layar: kode => nama, warna (hex), urutan.
     *
     * Warna ikut dari database supaya tingkat baru tak tampil sebagai kode
     * mentah tanpa lencana — layar tak punya cara menebak warna nilai yang
     * belum ada saat kodenya ditulis. Disimpan sebagai HEX, bukan nama ragam:
     * daftar ragam hanya memuat lima warna yang ditulis lebih dulu di kode, dan
     * itu batas yang tak ada gunanya bagi master data.
     */
    public function untukLayar(): array
    {
        return $this->semua()->map(fn ($r) => [
            'kode' => $r->Kode,
            'nama' => $r->Nama,
            'urutan' => (int) $r->Urutan,
            'warna' => $r->Warna ?: '',
        ])->values()->all();
    }

    /**
     * Berapa paket memakai tingkat ini — penjaga sebelum menghapus/menonaktifkan.
     *
     * Menghitung paket AKTIF saja, termasuk yang masih draft: paket draft akan
     * terbit membawa nilai itu, jadi ia bagian dari jawabannya.
     */
    public function dipakai(string $kode): int
    {
        return (int) DB::table('N_SOP_Master_Paket')
            ->where('Prioritas', $kode)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->count();
    }
}
