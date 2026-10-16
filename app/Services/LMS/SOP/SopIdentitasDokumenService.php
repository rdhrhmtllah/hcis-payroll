<?php

namespace App\Services\LMS\SOP;

use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;

/**
 * Identitas dokumen (departemen · jenis · proses APQC) yang dikumpulkan ke
 * tingkat PAKET.
 *
 * KENAPA KELAS TERSENDIRI. Dua halaman menanyakan hal yang sama dengan
 * lingkup berbeda: Functional Document menanyakannya atas paket yang
 * DITUGASKAN, Public Document atas paket PUBLIK. Jawabannya harus satu.
 * Dua salinan query ini pernah nyaris lahir, dan begitu keduanya ada, satu
 * perbaikan (misalnya memasukkan dokumen tanpa departemen sebagai "Tanpa
 * pemilik") hanya akan sampai ke salah satu halaman — dengan kedua halaman
 * tetap hijau di uji masing-masing.
 *
 * KENAPA `N_SOP_Dokumen`, BUKAN kolom yang tersalin ke item. Judul dan nomor
 * memang dicerminkan ke `N_SOP_Paket_Item`, tetapi departemen, jenis, dan
 * proses APQC tidak — ketiganya hanya hidup di dokumen. Item yang bukan
 * dokumen (kuis, tautan, konten bebas) karena itu tidak menyumbang apa pun,
 * dan paket yang seluruh isinya begitu pulang dengan daftar kosong.
 *
 * ITEM AKTIF, BUKAN HANYA YANG WAJIB. Daftar ini menjawab "paket ini
 * menyangkut siapa", bukan "apa yang wajib saya baca". Dokumen rujukan milik
 * HR yang tak wajib tetap membuat paket itu menyangkut HR, dan menyaringnya
 * dengan Flag_Wajib akan menyembunyikannya dari orang HR yang mencarinya.
 */
class SopIdentitasDokumenService
{
    /** Sumbu yang identitasnya dikumpulkan, sekaligus urutan bakunya. */
    public const SUMBU = ['departemen', 'jenis', 'apqc'];

    /**
     * Peta identitas per paket.
     *
     * @param  int[]  $paketIds
     * @return array<int, array{departemen:array, jenis:array, apqc:array}>
     */
    public function perPaket(array $paketIds): array
    {
        if ($paketIds === []) {
            return [];
        }

        $rows = $this->baris($paketIds);

        $peta = [];
        foreach ($rows as $r) {
            $idPaket = (int) $r->Id_SOP_Paket;
            if (! isset($peta[$idPaket])) {
                $peta[$idPaket] = ['departemen' => [], 'jenis' => [], 'apqc' => []];
            }

            /* Dokumen yang belum punya departemen/jenis/APQC TIDAK dibuang
               diam-diam melainkan dicatat sebagai entri ber-id null. Itu yang
               membuat "Tanpa pemilik" bisa jadi kelompok yang bisa diklik —
               dan kelompok itulah pekerjaan tata kelola yang menunggu. */
            $this->kumpulkan($peta[$idPaket]['departemen'], $r->Departemen_Id, $r->Nama_Departemen, $r->Kode_Departemen);
            $this->kumpulkan($peta[$idPaket]['jenis'], $r->Jenis_Dokumen_Id, $r->Nama_Jenis, $r->Kode_Jenis_Dokumen, $r->Level_Hirarki);
            $this->kumpulkan($peta[$idPaket]['apqc'], $r->APQC_Id, $r->Nama_Proses, $r->Kode_Proses);
        }

        // Urutan tampil ditentukan di sini, sekali, supaya lencana pada dua
        // kartu yang isinya sama tidak tampil dengan urutan berbeda hanya karena
        // urutan baris dari database kebetulan lain.
        foreach ($peta as &$p) {
            foreach (self::SUMBU as $sumbu) {
                usort($p[$sumbu], fn ($a, $b) => [$a['level'] ?? 99, $a['nama']] <=> [$b['level'] ?? 99, $b['nama']]);
            }
        }

        return $peta;
    }

    /**
     * Rekap kelompok atas satu sumbu untuk sehimpunan paket.
     *
     * Mengembalikan bentuk yang SAMA dengan `bangunGrup()` di klien, supaya
     * halaman yang mengelompokkan di server dan halaman yang mengelompokkan di
     * memori memberi makan komponen daftar yang sama tanpa percabangan.
     *
     * `tugas` dan `penempatan` sengaja dua angka yang berbeda. Kepemilikan
     * melekat pada DOKUMEN sementara yang dihitung adalah PAKET, jadi satu
     * paket yang memuat dokumen tiga departemen muncul di tiga kelompok.
     * Menjumlahkan kelompok dan menyebutnya "total" akan menghitungnya tiga
     * kali; layar wajib menyebut selisihnya, bukan menyembunyikannya.
     *
     * @param  int[]  $paketIds
     * @return array{grup:array, tugas:int, penempatan:int}
     */
    public function rekap(array $paketIds, string $sumbu): array
    {
        $sumbu = in_array($sumbu, self::SUMBU, true) ? $sumbu : 'departemen';
        $peta = $this->perPaket($paketIds);

        $kelompok = [];
        $penempatan = 0;

        foreach ($paketIds as $id) {
            $nilai = $peta[(int) $id][$sumbu] ?? [];

            /* Paket tanpa dokumen sama sekali tetap harus bisa ditemukan.
               Membiarkannya tanpa kelompok membuatnya lenyap dari rail —
               terlihat di "Semua", tak pernah di mana pun setelah orang
               mengklik satu kelompok, tanpa pesan apa pun yang menjelaskan. */
            if ($nilai === []) {
                $nilai = [['id' => null, 'nama' => null, 'kode' => null, 'level' => null]];
            }

            foreach ($nilai as $n) {
                $nama = $n['nama'] !== null && $n['nama'] !== '' ? $n['nama'] : $this->labelKosong($sumbu);
                if (! isset($kelompok[$nama])) {
                    $kelompok[$nama] = [
                        'nama' => $nama,
                        'kosong' => $n['id'] === null,
                        'total' => 0,
                        'level' => $n['level'],
                    ];
                }
                $kelompok[$nama]['total']++;
                $penempatan++;
            }
        }

        $grup = array_values($kelompok);

        /* Kelompok "tanpa …" selalu di bawah, berapa pun angkanya: ia bukan
           unit organisasi melainkan pekerjaan yang belum dirapikan, dan
           menaruhnya di tengah daftar membuatnya terbaca seperti departemen.
           Urutan ini menyalin `bangunGrup()` di klien dengan sengaja. */
        usort($grup, fn ($a, $b) => [$a['kosong'], $a['nama']] <=> [$b['kosong'], $b['nama']]);

        return [
            'grup' => $grup,
            'tugas' => count($paketIds),
            'penempatan' => $penempatan,
        ];
    }

    /**
     * Rekap struktur pohon folder (Departemen -> Jenis Dokumen) untuk kumpulan paket.
     *
     * @param  int[]  $paketIds
     * @return array<int, array{id: string, nama: string, kode: string|null, kosong: bool, total: int, children: array}>
     */
    public function rekapTree(array $paketIds): array
    {
        if ($paketIds === []) {
            return [];
        }

        $rows = $this->baris($paketIds);

        $deptMap = [];
        $paketDepts = [];

        foreach ($rows as $r) {
            $idPaket = (int) $r->Id_SOP_Paket;
            $deptNama = $r->Nama_Departemen ?: 'Tanpa Pemilik';
            $deptKosong = empty($r->Nama_Departemen);
            $jenisNama = $r->Nama_Jenis ?: 'Tanpa Jenis';
            $jenisKosong = empty($r->Nama_Jenis);

            if (! isset($deptMap[$deptNama])) {
                $deptMap[$deptNama] = [
                    'id' => 'dept_'.md5($deptNama),
                    'nama' => $deptNama,
                    'kode' => $r->Kode_Departemen,
                    'kosong' => $deptKosong,
                    'pakets' => [],
                    'sub' => [],
                ];
            }
            $deptMap[$deptNama]['pakets'][$idPaket] = true;

            if (! isset($deptMap[$deptNama]['sub'][$jenisNama])) {
                $deptMap[$deptNama]['sub'][$jenisNama] = [
                    'id' => 'jns_'.md5($deptNama.'_'.$jenisNama),
                    'nama' => $jenisNama,
                    'kode' => $r->Kode_Jenis_Dokumen,
                    'level' => $r->Level_Hirarki !== null ? (int) $r->Level_Hirarki : 99,
                    'kosong' => $jenisKosong,
                    'pakets' => [],
                ];
            }
            $deptMap[$deptNama]['sub'][$jenisNama]['pakets'][$idPaket] = true;
            $paketDepts[$idPaket] = true;
        }

        $unattached = array_diff($paketIds, array_keys($paketDepts));
        if (! empty($unattached)) {
            $deptNama = 'Tanpa Pemilik';
            if (! isset($deptMap[$deptNama])) {
                $deptMap[$deptNama] = [
                    'id' => 'dept_'.md5($deptNama),
                    'nama' => $deptNama,
                    'kode' => null,
                    'kosong' => true,
                    'pakets' => [],
                    'sub' => [],
                ];
            }
            foreach ($unattached as $uid) {
                $deptMap[$deptNama]['pakets'][(int) $uid] = true;
            }
            if (! isset($deptMap[$deptNama]['sub']['Tanpa Dokumen'])) {
                $deptMap[$deptNama]['sub']['Tanpa Dokumen'] = [
                    'id' => 'jns_'.md5($deptNama.'_Tanpa Dokumen'),
                    'nama' => 'Tanpa Dokumen',
                    'kode' => null,
                    'level' => 999,
                    'kosong' => true,
                    'pakets' => [],
                ];
            }
            foreach ($unattached as $uid) {
                $deptMap[$deptNama]['sub']['Tanpa Dokumen']['pakets'][(int) $uid] = true;
            }
        }

        $tree = [];
        foreach ($deptMap as $dept) {
            $children = [];
            foreach ($dept['sub'] as $sub) {
                $children[] = [
                    'id' => $sub['id'],
                    'nama' => $sub['nama'],
                    'kode' => $sub['kode'],
                    'level' => $sub['level'],
                    'kosong' => $sub['kosong'],
                    'total' => count($sub['pakets']),
                ];
            }
            usort($children, fn ($a, $b) => [$a['level'], $a['nama']] <=> [$b['level'], $b['nama']]);

            $tree[] = [
                'id' => $dept['id'],
                'nama' => $dept['nama'],
                'kode' => $dept['kode'],
                'kosong' => $dept['kosong'],
                'total' => count($dept['pakets']),
                'children' => $children,
            ];
        }

        usort($tree, function ($a, $b) {
            if ($a['kosong'] && ! $b['kosong']) {
                return 1;
            }
            if (! $a['kosong'] && $b['kosong']) {
                return -1;
            }

            return strcmp($a['nama'], $b['nama']);
        });

        return $tree;
    }

    /**
     * Label kelompok untuk nilai yang belum terisi.
     *
     * Disebut, bukan dikosongkan: dokumen yang belum bertuan adalah pekerjaan
     * tata kelola yang menunggu, bukan data yang hilang.
     */
    public function labelKosong(string $sumbu): string
    {
        return match ($sumbu) {
            'jenis' => 'Tanpa jenis',
            'apqc' => 'Tanpa proses',
            default => 'Tanpa pemilik',
        };
    }

    /** Kolom pemilik pada `N_SOP_Dokumen` untuk satu sumbu. */
    public function kolom(string $sumbu): string
    {
        return match ($sumbu) {
            'jenis' => 'dok.Jenis_Dokumen_Id',
            'apqc' => 'dok.APQC_Id',
            default => 'dok.Departemen_Id',
        };
    }

    private function baris(array $paketIds)
    {
        return DB::table('N_SOP_Paket_Item as i')
            ->join('N_SOP_Dokumen as dok', 'dok.Id_SOP_Dokumen', '=', 'i.Id_SOP_Dokumen')
            ->leftJoin('N_HRIS_Master_Departemen as dpt', 'dpt.Id_Departemen', '=', 'dok.Departemen_Id')
            ->leftJoin('N_SOP_Master_Jenis_Dokumen as jd', 'jd.Id_SOP_Jenis_Dokumen', '=', 'dok.Jenis_Dokumen_Id')
            ->leftJoin('N_SOP_Master_APQC as ap', 'ap.Id_SOP_APQC', '=', 'dok.APQC_Id')
            ->whereIn('i.Id_SOP_Paket', $paketIds)
            ->where('i.Flag_Aktif', SopStatus::AKTIF)
            ->where('dok.Flag_Aktif', SopStatus::AKTIF)
            // DISTINCT, bukan GROUP BY berikut hitungan: yang dibutuhkan layar
            // adalah DAFTAR pemilik, dan sepuluh dokumen HSE dalam satu paket
            // tetap satu lencana "HSE".
            ->distinct()
            ->get([
                'i.Id_SOP_Paket',
                'dok.Departemen_Id', 'dpt.Nama_Departemen', 'dpt.Kode_Departemen',
                'dok.Jenis_Dokumen_Id', 'jd.Nama_Jenis', 'jd.Kode_Jenis_Dokumen', 'jd.Level_Hirarki',
                'dok.APQC_Id', 'ap.Nama_Proses', 'ap.Kode_Proses',
            ]);
    }

    /**
     * Sisipkan satu nilai identitas bila belum ada.
     *
     * Kunci de-duplikasinya ID, bukan nama: dua departemen boleh bernama mirip,
     * dan yang belum bernama (id null) harus melebur jadi SATU entri "Tanpa
     * pemilik" alih-alih sebanyak dokumen yang kosong.
     */
    private function kumpulkan(array &$daftar, $id, $nama, $kode, $level = null): void
    {
        $id = $id !== null ? (int) $id : null;

        foreach ($daftar as $ada) {
            if ($ada['id'] === $id) {
                return;
            }
        }

        $daftar[] = [
            'id' => $id,
            'nama' => $id === null ? null : (string) ($nama ?? ''),
            'kode' => $id === null ? null : ($kode !== null ? (string) $kode : null),
            'level' => $level !== null ? (int) $level : null,
        ];
    }
}
