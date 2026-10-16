<?php

namespace App\Services\KPI\PerformanceAppraisal;

use Illuminate\Support\Facades\DB;

/**
 * Riwayat penilaian lintas siklus — sisi admin.
 *
 * BEDANYA DENGAN RIWAYAT PESERTA
 *
 * `PaPesertaService::riwayatSaya()` hanya memulangkan hasil TERBIT, karena karyawan tidak
 * boleh melihat angka yang belum diterbitkan. Di sini justru sebaliknya: admin perlu
 * melihat yang belum terbit dan yang tertahan, sebab itulah pekerjaan yang belum selesai.
 *
 * Keduanya sengaja TIDAK disatukan di balik satu method ber-parameter. Method yang
 * kadang menyaring dan kadang tidak, tergantung sebuah argumen, adalah bentuk yang suatu
 * hari dipanggil dengan argumen yang salah dari layar peserta — dan kebocorannya tidak
 * akan tampak sebagai galat, hanya sebagai angka yang seharusnya belum ada di sana.
 *
 * TREN DIBACA KIRI KE KANAN
 *
 * Periode diurutkan terlama lebih dulu, sama dengan riwayat peserta. Dua layar yang
 * membaca sumber sama tetapi mengurutkannya berbeda adalah cara halus membuat dua orang
 * membaca grafik yang sama dengan kesimpulan berlawanan.
 */
class PaRiwayatService
{
    public const MAKS_PER_HALAMAN = 100;

    /**
     * Rekap hasil seluruh karyawan lintas siklus, berhalaman.
     *
     * @param  array{tahun?:int|null, cycle_id?:int|null, id_divisi?:int|null,
     *               id_level?:int|null, kode_grade?:string|null, status_hasil?:string|null,
     *               status_peserta?:string|null, q?:string|null, halaman?:int,
     *               per_halaman?:int}  $saringan
     * @return array<string, mixed>
     */
    public function riwayatAdmin(array $saringan = []): array
    {
        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $diminta = (int) ($saringan['per_halaman'] ?? 25);
        $perHalaman = max(1, min($diminta, self::MAKS_PER_HALAMAN));

        $dasar = fn () => $this->kueri($saringan);

        $agg = (clone $dasar())->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN r.Status_Hasil = 'TERBIT' THEN 1 ELSE 0 END) as terbit,
            SUM(CASE WHEN r.Flag_Dikalibrasi = 'Y' THEN 1 ELSE 0 END) as dikalibrasi
        ")->first();

        $total = (int) ($agg->total ?? 0);
        $terbit = (int) ($agg->terbit ?? 0);
        $dikalibrasi = (int) ($agg->dikalibrasi ?? 0);

        $baris = $dasar()
            // Terbaru lebih dulu untuk TABEL — orang membaca tabel dari yang paling
            // relevan. Grafik trennya terpisah, dan di sana urutannya dibalik.
            ->orderByDesc('k.Tahun')
            ->orderByDesc('k.Id_PA_Cycle')
            ->orderBy('p.Nama_Karyawan_Saat_Cycle')
            ->orderBy('p.Id_PA_Participant')
            ->offset(($halaman - 1) * $perHalaman)
            ->limit($perHalaman)
            ->get([
                'p.Id_PA_Participant', 'p.Kode_Perusahaan', 'p.Kode_Karyawan',
                'p.Nama_Karyawan_Saat_Cycle', 'p.Nama_Jabatan_Saat_Cycle',
                'p.Nama_Divisi_Saat_Cycle', 'p.Nama_Level_Saat_Cycle', 'p.Status_Peserta',
                'k.Id_PA_Cycle', 'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun',
                'r.Id_PA_Result', 'r.Nilai_KPI', 'r.Nilai_Kompetensi', 'r.Final_Score',
                'r.Kode_Grade_Final', 'r.Kode_Grade_Sistem', 'r.Status_Hasil',
                'r.Alasan_Tertahan', 'r.Published_At', 'r.Flag_Dikalibrasi',
                'q.Kode_Kuadran', 'q.Nama_Kuadran', 'q.Nomor_Box',
            ])
            ->map(fn ($b) => $this->tanpaKolomMentah((array) $b) + [
                /* Dibaca dari `Flag_Dikalibrasi`, BUKAN diturunkan dari selisih grade.
                   Komentar sebelumnya menyebut selisih grade sebagai "satu-satunya
                   penanda"; itu keliru — penandanya ada sebagai kolom, hanya tidak
                   dibaca. Akibatnya kalibrasi yang hanya menggeser KUADRAN tak terbaca
                   sebagai kalibrasi: terbukti pada uji rantai penuh 2 September 2026, satu
                   peserta digeser dari POTENTIAL_CANDIDATE ke STRIVING_STAR — melompat
                   dari kotak 4 ke kotak 1 pada matriks sembilan kotak — dengan grade tetap
                   B, dan riwayat melaporkan `dikalibrasi: false`.

                   Perpindahan kuadran justru sering lebih berkonsekuensi daripada
                   perpindahan grade: kuadran itulah yang dipakai untuk keputusan
                   suksesi dan pengembangan. */
                'dikalibrasi' => $b->Flag_Dikalibrasi === 'Y',
            ])
            ->values()
            ->all();

        $distribusiGrade = (clone $dasar())
            ->whereNotNull('r.Kode_Grade_Final')
            ->groupBy('r.Kode_Grade_Final')
            ->selectRaw('r.Kode_Grade_Final as grade, COUNT(*) as count')
            ->pluck('count', 'grade')
            ->mapWithKeys(fn ($cnt, $grd) => [(string) $grd => (int) $cnt])
            ->all();

        return [
            'baris' => $baris,
            'halaman' => $halaman,
            'per_halaman' => $perHalaman,
            'total' => $total,
            'total_halaman' => (int) ceil($total / $perHalaman),
            'dipangkas' => $diminta > self::MAKS_PER_HALAMAN,
            'ringkasan' => [
                'total' => $total,
                'terbit' => $terbit,
                'dikalibrasi' => $dikalibrasi,
            ],
            'distribusi_grade' => $distribusiGrade,
            'tren' => $this->tren($saringan),
            'pilihan_saringan' => $this->pilihanSaringan(),
        ];
    }

    /**
     * Membuang kolom mentah yang sudah diwakili bentuk turunannya.
     *
     * `Flag_Dikalibrasi` ikut di-SELECT karena `dikalibrasi` diturunkan darinya, tetapi ia
     * tidak boleh ikut terbit ke payload: dua kunci yang menyatakan hal yang sama akan
     * dipakai bergantian oleh layar yang berbeda, dan suatu hari salah satunya berubah
     * arti sementara yang lain tidak. Kontraknya menyebut `dikalibrasi`; itu saja.
     *
     * @param  array<string, mixed>  $baris
     * @return array<string, mixed>
     */
    private function tanpaKolomMentah(array $baris): array
    {
        unset($baris['Flag_Dikalibrasi']);

        return $baris;
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * @param  array<string, mixed>  $saringan
     */
    private function kueri(array $saringan)
    {
        $q = DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle as k', 'k.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            /* LEFT JOIN, bukan JOIN. Peserta yang belum punya baris hasil tetap harus
               muncul di riwayat - justru merekalah pekerjaan yang belum selesai, dan
               membuangnya dari daftar membuat mereka tidak pernah ditemukan lagi. */
            ->leftJoin('N_PA_Result as r', function ($j) {
                $j->on('r.PA_Participant_Id', '=', 'p.Id_PA_Participant')
                    ->where('r.Flag_Aktif', 'Y');
            })
            ->leftJoin('N_PA_Hav_Quadrant as q', 'q.Id_PA_Hav_Quadrant', '=', 'r.PA_Hav_Quadrant_Id')
            ->where('p.Flag_Aktif', 'Y');

        if (! empty($saringan['tahun'])) {
            $q->where('k.Tahun', (int) $saringan['tahun']);
        }

        if (! empty($saringan['cycle_id'])) {
            $q->where('k.Id_PA_Cycle', (int) $saringan['cycle_id']);
        }

        if (! empty($saringan['id_divisi'])) {
            $q->where('p.ID_Divisi_Saat_Cycle', (int) $saringan['id_divisi']);
        }

        if (! empty($saringan['id_level'])) {
            $q->where('p.ID_Level_Saat_Cycle', (int) $saringan['id_level']);
        }

        if (! empty($saringan['kode_grade'])) {
            $q->where('r.Kode_Grade_Final', $saringan['kode_grade']);
        }

        if (! empty($saringan['status_hasil'])) {
            $q->where('r.Status_Hasil', $saringan['status_hasil']);
        }

        /* Saringan yang sebelumnya tidak ada sama sekali, padahal `PaParticipantReadService`
           punya. Akibatnya riwayat admin melaporkan total 958 yang 953 di antaranya adalah
           baris DIKECUALIKAN tanpa hasil — dan admin tidak punya satu pun cara
           menyingkirkannya dari layar.

           Barisnya tetap BOLEH dilihat (itu sebabnya ini saringan, bukan penyaring tetap
           seperti di dashboard): riwayat memang tempat menelusuri orang yang dikecualikan
           beserta alasannya. Yang tidak boleh adalah memaksanya selalu ikut. */
        /* Bawaan: hanya yang DIIKUTKAN. Arsip dulu ikut memuat peserta DIKECUALIKAN (dev: 2.874
           baris, 570 yang diikutkan) dan menampilkannya berbadge kuning seperti pekerjaan
           tertunda. `SEMUA` tetap tersedia untuk yang memang ingin melihat semuanya. */
        $statusPeserta = $saringan['status_peserta'] ?? null;

        if ($statusPeserta === null || $statusPeserta === '') {
            $q->where('p.Status_Peserta', 'DIIKUTKAN');
        } elseif ($statusPeserta !== 'SEMUA') {
            $q->where('p.Status_Peserta', $statusPeserta);
        }

        if (isset($saringan['dikalibrasi']) && $saringan['dikalibrasi'] !== null && $saringan['dikalibrasi'] !== '') {
            if (filter_var($saringan['dikalibrasi'], FILTER_VALIDATE_BOOLEAN)) {
                $q->where('r.Flag_Dikalibrasi', 'Y');
            }
        }

        $cari = trim((string) ($saringan['q'] ?? ''));

        if ($cari !== '') {
            $q->where(function ($w) use ($cari) {
                $w->where('p.Nama_Karyawan_Saat_Cycle', 'LIKE', '%'.$cari.'%')
                    ->orWhere('p.Kode_Karyawan', 'LIKE', '%'.$cari.'%');
            });
        }

        return $q;
    }

    /**
     * Tren rata-rata nilai akhir per siklus.
     *
     * Memakai saringan yang SAMA dengan tabelnya. Grafik yang mengabaikan saringan akan
     * memperlihatkan tren seluruh perusahaan di samping tabel satu divisi, dan orang akan
     * membandingkan keduanya seolah keduanya bicara tentang hal yang sama.
     *
     * @param  array<string, mixed>  $saringan
     * @return list<array<string, mixed>>
     */
    private function tren(array $saringan): array
    {
        return $this->kueri($saringan)
            ->whereNotNull('r.Final_Score')
            ->groupBy('k.Id_PA_Cycle', 'k.Kode_Cycle', 'k.Nama_Cycle', 'k.Tahun', 'k.Periode_Dinilai_Sampai')
            // Terlama lebih dulu — tren dibaca kiri ke kanan; urutan PERIODE dinilai, bukan tahun lalu id.
            ->orderByRaw(PaPesertaService::URUTAN_PERIODE)
            ->orderBy('k.Id_PA_Cycle')
            ->selectRaw(
                'k.Id_PA_Cycle as id_cycle, k.Kode_Cycle as kode_cycle, '
                .'k.Nama_Cycle as nama_cycle, k.Tahun as tahun, '
                .'COUNT(*) as jumlah, AVG(r.Final_Score) as rata, '
                .'MIN(r.Final_Score) as terendah, MAX(r.Final_Score) as tertinggi'
            )
            ->get()
            ->map(fn ($r) => [
                'id_cycle' => (int) $r->id_cycle,
                'cycle_id' => (int) $r->id_cycle,
                'kode_cycle' => $r->kode_cycle,
                'nama_cycle' => $r->nama_cycle,
                'tahun' => (int) $r->tahun,
                'jumlah' => (int) $r->jumlah,
                'jumlah_peserta' => (int) $r->jumlah,
                // Dicetak ke float: kolom decimal tiba sebagai string dan JavaScript
                // membandingkan string secara leksikal.
                'rata_rata' => $r->rata === null ? null : round((float) $r->rata, 2),
                'rerata' => $r->rata === null ? null : round((float) $r->rata, 2),
                'terendah' => $r->terendah === null ? null : round((float) $r->terendah, 2),
                'tertinggi' => $r->tertinggi === null ? null : round((float) $r->tertinggi, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function pilihanSaringan(): array
    {
        $siklus = DB::table('N_PA_Cycle')
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Tahun')
            ->orderByDesc('Id_PA_Cycle')
            ->get(['Id_PA_Cycle as id', 'Kode_Cycle as kode', 'Nama_Cycle as nama', 'Tahun as tahun'])
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'kode' => $r->kode,
                'nama' => $r->nama, 'tahun' => (int) $r->tahun,
            ])
            ->all();

        return [
            'siklus' => $siklus,
            'tahun' => array_values(array_unique(array_column($siklus, 'tahun'))),
            /* Grade diambil dari yang BENAR-BENAR terpakai di hasil, bukan dari master
               pita nilai. Master memuat grade yang belum pernah diberikan kepada siapa
               pun, dan menyaringnya hanya menghasilkan tabel kosong. */
            'grade' => DB::table('N_PA_Result')
                ->whereNotNull('Kode_Grade_Final')
                ->where('Flag_Aktif', 'Y')
                ->distinct()
                ->orderBy('Kode_Grade_Final')
                ->pluck('Kode_Grade_Final')
                ->all(),
            // Kolom urutannya bernama Urutan_ALUR di tabel ini, bukan Urutan_Tampil
            // seperti master PA lainnya.
            'status_hasil' => DB::table('N_PA_Result_Status')
                ->where('Flag_Aktif', 'Y')
                ->orderBy('Urutan_Alur')
                ->pluck('Kode_Status')
                ->all(),
        ];
    }
}
