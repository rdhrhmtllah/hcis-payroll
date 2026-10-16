<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;

/**
 * Jangkauan dokumen SOP publik — lapis analisis tab Publik.
 *
 * PERTANYAANNYA BERBEDA, BUKAN SEKADAR PENYARINGNYA.
 *
 * Pada dokumen fungsional yang ditanyakan adalah kepatuhan: siapa yang wajib,
 * sudah baca belum, terlambat berapa lama. Pada dokumen publik tak ada yang
 * diwajibkan, sehingga tenggat, keterlambatan, dan tingkat penyelesaian bukan
 * cuma bernilai nol — ketiganya tak punya arti. Yang bisa ditanyakan hanyalah
 * MINAT: berapa orang membukanya, siapa saja, dan mana yang tak disentuh
 * siapa pun.
 *
 * DARI MANA JEJAKNYA. N_SOP_Progress berkunci pada Id_SOP_Penjadwalan_Detail
 * yang NOT NULL, jadi tak ada tempat menyimpan bacaan tanpa penugasan. Karena
 * itu karyawan yang membuka SOP publik didaftarkan sendiri oleh sistem —
 * SopPendaftaranMandiri membuat penugasan ber-Sumber 'MANDIRI' dengan
 * Created_By NULL. Baris MANDIRI itulah satu-satunya bukti minat sukarela yang
 * dimiliki sistem, dan seluruh service ini dibangun di atasnya.
 *
 * TITIK BUTA YANG TAK BISA DIHILANGKAN. Dokumen publik yang tak pernah dibuka
 * siapa pun TIDAK punya satu baris pun di mana pun — bukan nol, melainkan tak
 * ada. "Berapa yang mengabaikannya" karena itu adalah SELISIH (seluruh
 * karyawan aktif dikurangi yang pernah membuka), yakni sebuah kesimpulan,
 * bukan pembacaan. Angkanya dikirim bersama penandanya supaya layar bisa
 * mengatakannya terus terang.
 */
class SopJangkauanPublikService
{
    /** Penugasan yang lahir dari karyawan sendiri, bukan dari admin/aturan. */
    private const SUMBER_MINAT = 'MANDIRI';

    public function __construct(private SopMonitoringPeopleService $people) {}

    public function jangkauan(array $f = []): array
    {
        $dokumen = $this->dokumenPublik();

        return [
            'Jangkauan' => $this->ringkasJangkauan($f, $dokumen),
            'Terpopuler' => $this->terpopuler($f, $dokumen),
            'Diabaikan' => $this->diabaikan($dokumen),
            'Tren' => $this->tren($f),
            'Unit' => $this->sebaranUnit($f),
        ];
    }

    /**
     * Dokumen yang ADA di paket publik terbit.
     *
     * Tanpa saringan organisasi: "publik" adalah sifat dokumen terhadap paket,
     * bukan terhadap sekelompok karyawan.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function dokumenPublik()
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'i.Id_SOP_Paket')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Flag_Aktif', SopStatus::AKTIF)
            ->where('p.Status', SopStatus::PAKET_PUBLISHED)
            ->where('p.Mode_Distribusi', 'PUBLIK')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->distinct()
            ->get(['d.Id_SOP_Dokumen', 'd.Judul', 'd.Nomor_SOP_Resmi', 'd.Tipe'])
            ->keyBy('Id_SOP_Dokumen');
    }

    /** Kueri dasar penugasan MANDIRI, sudah dihormati saringan layar. */
    private function kueriMinat(array $f)
    {
        $q = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->join('N_SOP_Master_Paket as p', 'p.Id_SOP_Paket', '=', 'pj.Id_SOP_Paket')
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->where('pj.Sumber', self::SUMBER_MINAT)
            ->where('p.Mode_Distribusi', 'PUBLIK');

        $this->people->terapkanSaringanPaketRingkas($q, $f, 'p');
        $this->people->terapkanSaringanOrang($q, $f, 'd');

        return $q;
    }

    /**
     * Berapa orang yang benar-benar tersentuh, terhadap berapa yang berhak.
     *
     * Penyebutnya SELURUH karyawan aktif, dan itu memang arti "publik": tak ada
     * yang dikecualikan dari haknya melihat. Memakai penyebut yang lebih sempit
     * akan membuat jangkauan tampak lebih baik daripada kenyataannya.
     */
    private function ringkasJangkauan(array $f, $dokumen): array
    {
        $karyawanAktif = DB::table('Karyawan')->where('Aktif', SopStatus::AKTIF)->count();
        $menyentuh = (clone $this->kueriMinat($f))->distinct()->count('d.Kode_Karyawan');

        /* Yang MEMBUKA, bukan sekadar terdaftar. Pendaftaran mandiri terjadi
           pada percobaan akses pertama, jadi baris yang belum punya Waktu_Buka
           adalah orang yang mengetuk pintu lalu berbalik. */
        $membuka = (clone $this->kueriMinat($f))
            ->whereExists(fn ($s) => $s->select(DB::raw(1))->from('N_SOP_Progress as pr')
                ->whereColumn('pr.Id_SOP_Penjadwalan_Detail', 'd.Id_SOP_Penjadwalan_Detail')
                ->whereNotNull('pr.Waktu_Buka'))
            ->distinct()->count('d.Kode_Karyawan');

        return [
            'Karyawan_Aktif' => $karyawanAktif,
            'Tersentuh' => $menyentuh,
            'Membuka' => $membuka,
            'Persen' => $karyawanAktif > 0 ? round($membuka * 100 / $karyawanAktif, 1) : 0.0,
            'Dokumen_Publik' => $dokumen->count(),
        ];
    }

    /**
     * Dokumen publik dengan pembaca sukarela terbanyak.
     *
     * @return array<int, array>
     */
    private function terpopuler(array $f, $dokumen, int $batas = 8): array
    {
        if ($dokumen->isEmpty()) {
            return [];
        }

        $hitung = $this->hitungPembuka($f, $dokumen->keys()->all());

        return collect($hitung)
            ->filter(fn ($n) => $n > 0)
            ->sortDesc()
            ->take($batas)
            ->map(fn ($n, $id) => [
                'Id_SOP_Dokumen' => (int) $id,
                'Judul' => $dokumen[$id]->Judul ?? '—',
                'Nomor' => $dokumen[$id]->Nomor_SOP_Resmi ?? null,
                'Pembuka' => $n,
            ])->values()->all();
    }

    /**
     * Dokumen publik yang TAK DISENTUH siapa pun.
     *
     * Inilah temuan paling berguna di tab ini: dokumen yang sengaja dibuka
     * untuk semua orang, lalu tak seorang pun membacanya. Tak ada mekanisme
     * lain di sistem yang memunculkannya.
     *
     * @return array<int, array>
     */
    private function diabaikan($dokumen, int $batas = 12): array
    {
        if ($dokumen->isEmpty()) {
            return [];
        }

        /* TANPA saringan layar, dan itu disengaja. "Tak dibuka siapa pun"
           adalah pernyataan tentang SELURUH orang; menyaringnya ke satu
           departemen mengubah artinya menjadi "tak dibuka orang departemen
           itu" — kalimat yang jauh lebih lemah dan mudah disalahbaca. */
        $hitung = $this->hitungPembuka([], $dokumen->keys()->all());

        return collect($dokumen)
            ->filter(fn ($d) => ($hitung[(int) $d->Id_SOP_Dokumen] ?? 0) === 0)
            ->take($batas)
            ->map(fn ($d) => [
                'Id_SOP_Dokumen' => (int) $d->Id_SOP_Dokumen,
                'Judul' => $d->Judul,
                'Nomor' => $d->Nomor_SOP_Resmi,
                'Tipe' => $d->Tipe,
            ])->values()->all();
    }

    /**
     * Jumlah pembuka per dokumen.
     *
     * @param  array<int, int>  $idDokumen
     * @return array<int, int>
     */
    private function hitungPembuka(array $f, array $idDokumen): array
    {
        if ($idDokumen === []) {
            return [];
        }

        $rows = $this->kueriMinat($f)
            ->join('N_SOP_Paket_Item as i', 'i.Id_SOP_Paket', '=', 'p.Id_SOP_Paket')
            ->join('N_SOP_Progress as pr', function ($j) {
                $j->on('pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
                    ->on('pr.Id_SOP_Paket_Item', '=', 'i.Id_SOP_Paket_Item');
            })
            ->whereIn('i.Id_SOP_Dokumen', $idDokumen)
            ->whereNotNull('pr.Waktu_Buka')
            ->groupBy('i.Id_SOP_Dokumen')
            ->selectRaw('i.Id_SOP_Dokumen, COUNT(DISTINCT d.Kode_Karyawan) as n')
            ->get();

        $out = array_fill_keys($idDokumen, 0);
        foreach ($rows as $r) {
            $out[(int) $r->Id_SOP_Dokumen] = (int) $r->n;
        }

        return $out;
    }

    /**
     * Tren minat mingguan — kapan orang membuka SOP publik.
     *
     * Dihitung dari Waktu_Buka, bukan dari tanggal pendaftaran: yang menarik
     * adalah kapan dokumennya dibaca, bukan kapan barisnya dibuat.
     *
     * @return array<int, array>
     */
    private function tren(array $f, int $minggu = 12): array
    {
        $rows = $this->kueriMinat($f)
            ->join('N_SOP_Progress as pr', 'pr.Id_SOP_Penjadwalan_Detail', '=', 'd.Id_SOP_Penjadwalan_Detail')
            ->whereNotNull('pr.Waktu_Buka')
            ->where('pr.Waktu_Buka', '>=', now()->subWeeks($minggu)->startOfWeek())
            ->groupByRaw('DATEADD(week, DATEDIFF(week, 0, pr.Waktu_Buka), 0)')
            ->selectRaw('DATEADD(week, DATEDIFF(week, 0, pr.Waktu_Buka), 0) as Minggu,
                         COUNT(DISTINCT d.Kode_Karyawan) as Orang,
                         COUNT(*) as Bukaan')
            ->orderBy('Minggu')
            ->get();

        return $rows->map(fn ($r) => [
            'Minggu' => (string) $r->Minggu,
            'Orang' => (int) $r->Orang,
            'Bukaan' => (int) $r->Bukaan,
        ])->all();
    }

    /**
     * Unit mana yang membaca sukarela, mana yang tidak.
     *
     * @return array<int, array>
     */
    private function sebaranUnit(array $f, int $batas = 10): array
    {
        $rows = $this->kueriMinat($f)
            ->join('Karyawan as k', 'k.Kode_Karyawan', '=', 'd.Kode_Karyawan')
            ->leftJoin('View_Divisi_Sub_Divisi as v', 'v.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->groupBy('v.nama_sub_divisi')
            ->selectRaw('v.nama_sub_divisi as Unit, COUNT(DISTINCT d.Kode_Karyawan) as Orang')
            ->orderByDesc('Orang')
            ->limit($batas)
            ->get();

        return $rows->map(fn ($r) => [
            'Unit' => $r->Unit ?: 'Tanpa unit',
            'Orang' => (int) $r->Orang,
        ])->all();
    }
}
