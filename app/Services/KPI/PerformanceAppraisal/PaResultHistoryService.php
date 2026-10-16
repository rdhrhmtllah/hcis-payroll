<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;

/**
 * Riwayat angka hasil satu peserta: kapan dihitung, oleh siapa, dan dari berapa ke berapa.
 *
 * KENAPA ADA TABEL POTRET (DDL-2026-168)
 *
 * `N_PA_Result` ditimpa di tempat setiap kali dihitung; nilai KPI, Final, dan grade versi
 * lama hilang. Tabel anaknya (nilai per kategori dan per kompetensi) memang menyimpan
 * versi lama, tetapi KPI dan Final tidak ada di sana. Karena itu setiap peristiwa yang
 * MENGUBAH ANGKA (hitung, hitung ulang, Balancing, kunci/buka kurva) memotret barisnya
 * sesudah berubah.
 *
 * Yang TIDAK dipotret, karena sudah tercatat di tempatnya sendiri dan dibaca dari sana:
 * kalibrasi (`N_PA_Calibration_History`), terbit dan rilis leader (kolom `Published_*`),
 * dan catatan kesimpulan kualitatif (`N_PA_Result_Note`, lihat `catatanBerubah`).
 * Menyalinnya ke sini akan membuat dua riwayat yang suatu hari berselisih (lihat
 * PaAuditService).
 *
 * Versi yang dihitung SEBELUM tabel ini ada tetap muncul, dibangun dari baris anak:
 * waktu, pelaku, dan nilai per kompetensinya diketahui; KPI dan Final-nya tidak, dan
 * layar menyebutnya terang-terangan alih-alih menebak.
 */
class PaResultHistoryService
{
    public const HITUNG = 'HITUNG';

    public const HITUNG_ULANG = 'HITUNG_ULANG';

    public const BALANCING = 'BALANCING';

    public const KURVA_DIKUNCI = 'KURVA_DIKUNCI';

    public const KURVA_DIBUKA = 'KURVA_DIBUKA';

    /** Peristiwa turunan dari data yang sudah ada — tidak pernah ditulis ke tabel potret. */
    public const HITUNG_LAMA = 'HITUNG_LAMA';

    public const KALIBRASI = 'KALIBRASI';

    public const BALANCING_LAMA = 'BALANCING_LAMA';

    public const TERBIT = 'TERBIT';

    public const RILIS_LEADER = 'RILIS_LEADER';

    /** Catatan kesimpulan kualitatif admin (atau Balancing) berubah. Dibaca dari N_PA_Result_Note. */
    public const CATATAN = 'CATATAN';

    /** Dua tulisan dalam rentang ini dianggap satu kali simpan: penonaktifan dan penyisipan beda jam sedetik. */
    private const DETIK_SATU_SIMPAN = 2;

    private const JENIS_HITUNG = [self::HITUNG, self::HITUNG_ULANG, self::BALANCING, self::HITUNG_LAMA];

    private const KOLOM = [
        'Calculation_Version', 'Status_Hasil', 'Nilai_KPI', 'Nilai_KPI_Sebelum_Cap', 'Nilai_Kompetensi',
        'Bobot_KPI_Terpakai', 'Bobot_Kompetensi_Terpakai', 'Final_Score', 'Kode_Grade_Sistem',
        'Kode_Grade_Final', 'Flag_Dikalibrasi', 'PA_Hav_Quadrant_Id', 'Alasan_Tertahan',
    ];

    /**
     * Memotret keadaan `N_PA_Result` SEKARANG untuk hasil-hasil ini. Dipanggil sesudah
     * barisnya diubah, di dalam transaksi yang sama, supaya potret dan perubahan jadi
     * atau batal bersama.
     *
     * @param  int|list<int>  $resultIds
     */
    public function potret(int|array $resultIds, string $jenis, ?int $olehUserId, ?string $keterangan = null, $pada = null): void
    {
        $ids = array_values(array_unique(array_map('intval', (array) $resultIds)));
        $pada ??= FormatTanggalHelper::getCurrentTime();
        $keterangan = $keterangan === null ? null : mb_substr($keterangan, 0, 500);

        foreach (array_chunk($ids, 1000) as $potongan) {
            DB::table('N_PA_Result_History')->insertUsing(
                ['PA_Result_Id', 'Jenis_Peristiwa', ...self::KOLOM, 'Keterangan', 'Created_At', 'Created_By'],
                DB::table('N_PA_Result')
                    ->whereIn('Id_PA_Result', $potongan)
                    ->select('Id_PA_Result')
                    ->selectRaw('?', [$jenis])
                    ->addSelect(self::KOLOM)
                    ->selectRaw('?, ?, ?', [$keterangan, $pada, $olehUserId]),
            );
        }
    }

    /**
     * Lini masa hasil aktif satu peserta, terbaru di atas.
     *
     * @return array{result_id:int|null, versi_kini:int|null, peristiwa:list<array<string, mixed>>}
     */
    public function lini(int $participantId): array
    {
        $hasil = DB::table('N_PA_Result')
            ->where('PA_Participant_Id', $participantId)
            ->where('Flag_Aktif', 'Y')
            ->first();

        if ($hasil === null) {
            return ['result_id' => null, 'versi_kini' => null, 'peristiwa' => []];
        }

        $resultId = (int) $hasil->Id_PA_Result;
        $potret = DB::table('N_PA_Result_History')->where('PA_Result_Id', $resultId)
            ->orderBy('Id_PA_Result_History')->get();
        $awalPotret = $potret->min('Created_At');
        $kompetensi = $this->kompetensiPerVersi($resultId);

        $peristiwa = [];

        foreach ($potret as $p) {
            $peristiwa[] = $this->peristiwa($p->Jenis_Peristiwa, $p->Created_At, $p->Created_By, [
                'versi' => (int) $p->Calculation_Version,
                'angka' => $this->angka($p),
                'keterangan' => $p->Keterangan,
            ]);
        }

        /* Versi yang lahir sebelum potret ada. Versi yang SEDANG berlaku angkanya masih ada
           di N_PA_Result, jadi tetap lengkap; versi yang lebih tua hanya punya nilai kompetensi. */
        $versiTerpotret = $potret->whereIn('Jenis_Peristiwa', self::JENIS_HITUNG)
            ->pluck('Calculation_Version')->map(fn ($v) => (int) $v)->all();

        foreach ($this->versiLama($resultId) as $v) {
            if (in_array((int) $v->versi, $versiTerpotret, true)) {
                continue;
            }

            $kini = (int) $v->versi === (int) $hasil->Calculation_Version
                && $potret->where('Calculation_Version', (int) $v->versi)->isEmpty();
            $peristiwa[] = $this->peristiwa(self::HITUNG_LAMA, $v->pada, $v->oleh, [
                'versi' => (int) $v->versi,
                'angka' => $kini ? $this->angka($hasil) : null,
                'keterangan' => $kini ? null : 'Dihitung sebelum riwayat angka dicatat; KPI dan Final versi ini tidak tersimpan.',
            ]);
        }

        foreach ($this->kalibrasi($resultId) as $k) {
            $peristiwa[] = $this->peristiwa(self::KALIBRASI, $k->Performed_At, $k->Performed_By, [
                'oleh_nama' => $k->Performed_By_Nama,
                'perubahan' => array_values(array_filter([
                    $this->ubah('grade_final', $k->Kode_Grade_Sebelum, $k->Kode_Grade_Sesudah),
                    $this->ubah('kuadran', $k->kuadran_sebelum, $k->kuadran_sesudah),
                ])),
                'keterangan' => $k->Alasan,
            ]);
        }

        foreach ($this->balancingLama($participantId, $awalPotret) as $j) {
            $peristiwa[] = $this->peristiwa(self::BALANCING_LAMA, $j->Created_At, $j->Created_By, [
                'perubahan' => array_values(array_filter([
                    $this->ubah('kompetensi', $j->Nilai_Kompetensi_Sebelum, $j->Nilai_Kompetensi_Sesudah, true),
                    $this->ubah('final', $j->Final_Score_Sebelum, $j->Final_Score_Sesudah, true),
                    $this->ubah('grade_final', $j->Kode_Grade_Sebelum, $j->Kode_Grade_Sesudah),
                ])),
                'keterangan' => "Hitung ulang Balancing #{$j->Nomor_Hitung_Ulang} ({$j->Sebab}).",
            ]);
        }

        $balancingPada = array_column(array_filter($peristiwa, fn ($e) => in_array($e['jenis'], [self::BALANCING, self::BALANCING_LAMA], true)), 'pada');

        foreach ($this->catatanBerubah($resultId) as $c) {
            $dariBalancing = collect($balancingPada)->contains(fn ($b) => abs(strtotime($b) - strtotime($c['pada'])) <= 120);
            $peristiwa[] = $this->peristiwa(self::CATATAN, $c['pada'], $c['oleh'], [
                'perubahan' => $c['perubahan'],
                'keterangan' => $dariBalancing ? 'Ditulis dari hasil Balancing.' : null,
            ]);
        }

        if ($hasil->Published_Leader_At ?? null) {
            $peristiwa[] = $this->peristiwa(self::RILIS_LEADER, $hasil->Published_Leader_At, $hasil->Published_Leader_By ?? null, []);
        }

        if ($hasil->Published_At) {
            $peristiwa[] = $this->peristiwa(self::TERBIT, $hasil->Published_At, $hasil->Published_By, []);
        }

        $peristiwa = $this->urutkanDanBandingkan($peristiwa, $kompetensi);
        $nama = $this->namaPengguna(array_column($peristiwa, 'oleh_id'));
        $kuadran = $this->namaKuadran($peristiwa);

        foreach ($peristiwa as &$e) {
            $e['oleh'] = $e['oleh_nama'] ?? ($e['oleh_id'] === null ? 'Sistem' : ($nama[$e['oleh_id']] ?? "Pengguna #{$e['oleh_id']}"));
            unset($e['oleh_nama']);

            if (is_array($e['angka'])) {
                $e['angka']['kuadran'] = $kuadran[$e['angka']['kuadran_id']] ?? null;
            }
        }
        unset($e);

        return ['result_id' => $resultId, 'versi_kini' => (int) $hasil->Calculation_Version, 'peristiwa' => $peristiwa];
    }

    /** @return array<string, mixed> */
    private function peristiwa(string $jenis, $pada, $oleh, array $isi): array
    {
        return [
            'jenis' => $jenis,
            'pada' => $pada === null ? null : substr((string) $pada, 0, 19),
            'oleh_id' => $oleh === null ? null : (int) $oleh,
            'versi' => null,
            'angka' => null,
            'perubahan' => [],
            'kompetensi' => [],
            'keterangan' => null,
            ...$isi,
        ];
    }

    /** @return array<string, mixed> */
    private function angka(object $r): array
    {
        $n = fn ($v) => $v === null ? null : round((float) $v, 2);

        return [
            'status' => $r->Status_Hasil,
            'kpi' => $n($r->Nilai_KPI),
            'kpi_sebelum_cap' => $n($r->Nilai_KPI_Sebelum_Cap),
            'kompetensi' => $n($r->Nilai_Kompetensi),
            'bobot_kpi' => $n($r->Bobot_KPI_Terpakai),
            'bobot_kompetensi' => $n($r->Bobot_Kompetensi_Terpakai),
            'final' => $n($r->Final_Score),
            'grade_sistem' => $r->Kode_Grade_Sistem,
            'grade_final' => $r->Kode_Grade_Final,
            'dikalibrasi' => $r->Flag_Dikalibrasi === 'Y',
            'kuadran_id' => $r->PA_Hav_Quadrant_Id === null ? null : (int) $r->PA_Hav_Quadrant_Id,
            'alasan_tertahan' => $r->Alasan_Tertahan,
        ];
    }

    /** @return array{kolom:string, sebelum:mixed, sesudah:mixed}|null */
    private function ubah(string $kolom, $sebelum, $sesudah, bool $angka = false): ?array
    {
        if ($angka) {
            $sebelum = $sebelum === null ? null : round((float) $sebelum, 2);
            $sesudah = $sesudah === null ? null : round((float) $sesudah, 2);
        }

        return $sebelum === $sesudah ? null : ['kolom' => $kolom, 'sebelum' => $sebelum, 'sesudah' => $sesudah];
    }

    /**
     * Urut naik untuk membandingkan tiap potret dengan potret sebelumnya, lalu dibalik.
     * Nilai kompetensi per versi ditempel pada peristiwa hitung, bersama selisihnya dari
     * versi hitung sebelumnya.
     *
     * @param  list<array<string, mixed>>  $peristiwa
     * @param  array<int, list<array<string, mixed>>>  $kompetensi
     * @return list<array<string, mixed>>
     */
    private function urutkanDanBandingkan(array $peristiwa, array $kompetensi): array
    {
        $urutan = array_flip([self::HITUNG_LAMA, self::HITUNG, self::HITUNG_ULANG, self::BALANCING, self::KURVA_DIBUKA,
            self::KURVA_DIKUNCI, self::BALANCING_LAMA, self::KALIBRASI, self::CATATAN, self::RILIS_LEADER, self::TERBIT]);
        usort($peristiwa, fn ($a, $b) => [$a['pada'], $a['versi'] ?? 0, $urutan[$a['jenis']]] <=> [$b['pada'], $b['versi'] ?? 0, $urutan[$b['jenis']]]);

        $angkaLalu = null;
        $kompetensiLalu = null;

        foreach ($peristiwa as &$e) {
            if (is_array($e['angka'])) {
                if ($angkaLalu !== null) {
                    $e['perubahan'] = array_values(array_filter([
                        $this->ubah('status', $angkaLalu['status'], $e['angka']['status']),
                        $this->ubah('kpi', $angkaLalu['kpi'], $e['angka']['kpi'], true),
                        $this->ubah('kompetensi', $angkaLalu['kompetensi'], $e['angka']['kompetensi'], true),
                        $this->ubah('final', $angkaLalu['final'], $e['angka']['final'], true),
                        $this->ubah('grade_sistem', $angkaLalu['grade_sistem'], $e['angka']['grade_sistem']),
                        $this->ubah('grade_final', $angkaLalu['grade_final'], $e['angka']['grade_final']),
                    ]));
                }
                $angkaLalu = $e['angka'];
            } elseif (in_array($e['jenis'], [self::HITUNG_LAMA], true)) {
                // Versi lama tanpa angka memutus rantai: membandingkan melewatinya akan menyesatkan.
                $angkaLalu = null;
            }

            if (in_array($e['jenis'], self::JENIS_HITUNG, true) && $e['versi'] !== null) {
                $kini = $kompetensi[$e['versi']] ?? [];
                $e['kompetensi'] = array_map(function ($k) use ($kompetensiLalu) {
                    $lalu = $kompetensiLalu[$k['kode']] ?? null;

                    return $k + ['sebelum' => $lalu];
                }, $kini);
                $kompetensiLalu = $kini === [] ? $kompetensiLalu : array_column($kini, 'nilai', 'kode');
            }
        }
        unset($e);

        return array_reverse($peristiwa);
    }

    /** @return array<int, list<array{kode:string, nama:string, nilai:float|null}>> */
    private function kompetensiPerVersi(int $resultId): array
    {
        $per = [];

        DB::table('N_PA_Result_Competency as rk')
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'rk.PA_Competency_Id')
            ->where('rk.PA_Result_Id', $resultId)
            ->whereNull('rk.PA_Rater_Category_Id')
            ->orderBy('rk.Calculation_Version')->orderBy('k.Urutan_Tampil')
            ->get(['rk.Calculation_Version', 'k.Kode_Competency', 'k.Nama_Competency', 'rk.Nilai'])
            ->each(function ($r) use (&$per) {
                $per[(int) $r->Calculation_Version][] = [
                    'kode' => $r->Kode_Competency,
                    'nama' => $r->Nama_Competency,
                    'nilai' => $r->Nilai === null ? null : round((float) $r->Nilai, 2),
                ];
            });

        return $per;
    }

    /** Waktu dan pelaku tiap versi hitung, dari baris anak yang tidak pernah dihapus. */
    private function versiLama(int $resultId)
    {
        return DB::table('N_PA_Result_Rater_Category')
            ->where('PA_Result_Id', $resultId)
            ->groupBy('Calculation_Version')
            ->orderBy('Calculation_Version')
            ->get([
                'Calculation_Version as versi',
                DB::raw('MIN(Created_At) as pada'),
                DB::raw('MIN(Created_By) as oleh'),
            ]);
    }

    private function kalibrasi(int $resultId)
    {
        return DB::table('N_PA_Calibration_History as h')
            ->leftJoin('N_PA_Hav_Quadrant as qa', 'qa.Id_PA_Hav_Quadrant', '=', 'h.PA_Hav_Quadrant_Sebelum_Id')
            ->leftJoin('N_PA_Hav_Quadrant as qb', 'qb.Id_PA_Hav_Quadrant', '=', 'h.PA_Hav_Quadrant_Sesudah_Id')
            ->where('h.PA_Result_Id', $resultId)
            ->get([
                'h.Kode_Grade_Sebelum', 'h.Kode_Grade_Sesudah', 'h.Alasan', 'h.Performed_By',
                'h.Performed_By_Nama', 'h.Performed_At',
                'qa.Nama_Kuadran as kuadran_sebelum', 'qb.Nama_Kuadran as kuadran_sesudah',
            ]);
    }

    /** Jejak Balancing yang terjadi sebelum potret ada; sesudahnya Balancing sudah dipotret. */
    /**
     * Catatan kesimpulan kualitatif per kali simpan, hanya kategori yang isinya berubah.
     *
     * Tidak ada tabel riwayat untuk ini dan tidak perlu: PaResultNoteService dan Balancing
     * MENONAKTIFKAN catatan lama (Updated_At/By) lalu menyisipkan yang baru (Created_At/By).
     * Keadaan sesudah tiap simpan direkonstruksi dari rentang hidup barisnya. Karena admin
     * selalu menyimpan semua kategori sekaligus, simpan yang tidak mengubah apa pun dibuang.
     *
     * @return list<array{pada:string, oleh:int|null, perubahan:list<array<string, mixed>>}>
     */
    private function catatanBerubah(int $resultId): array
    {
        $baris = DB::table('N_PA_Result_Note as n')
            ->join('N_PA_Competency as k', 'k.Id_PA_Competency', '=', 'n.PA_Competency_Id')
            ->where('n.PA_Result_Id', $resultId)
            ->whereNull('n.PA_Assignment_Id')
            ->orderBy('n.Urutan_Tampil')->orderBy('n.Id_PA_Result_Note')
            ->get(['k.Kode_Competency', 'k.Nama_Competency', 'k.Urutan_Tampil as urut', 'n.Isi', 'n.Flag_Aktif',
                'n.Created_At', 'n.Created_By', 'n.Updated_At', 'n.Updated_By'])
            // Nonaktif tanpa waktu cabut tidak bisa ditempatkan di lini masa mana pun.
            ->reject(fn ($r) => $r->Flag_Aktif !== 'Y' && $r->Updated_At === null)
            ->values();

        if ($baris->isEmpty()) {
            return [];
        }

        $detik = fn ($v) => strtotime(substr((string) $v, 0, 19));
        $momen = [];

        foreach ($baris as $r) {
            $momen[] = ['t' => $detik($r->Created_At), 'oleh' => $r->Created_By, 'sisip' => true];

            if ($r->Flag_Aktif !== 'Y') {
                $momen[] = ['t' => $detik($r->Updated_At), 'oleh' => $r->Updated_By, 'sisip' => false];
            }
        }

        usort($momen, fn ($a, $b) => $a['t'] <=> $b['t']);

        $kelompok = [];

        foreach ($momen as $m) {
            $akhir = array_key_last($kelompok);

            if ($akhir !== null && $m['t'] - $kelompok[$akhir]['t'] <= self::DETIK_SATU_SIMPAN) {
                $kelompok[$akhir]['t'] = $m['t'];
                // Pelaku penyisipan lebih tepat daripada pelaku penonaktifan, bila keduanya ada.
                if ($m['sisip'] && $m['oleh'] !== null) {
                    $kelompok[$akhir]['oleh'] = $m['oleh'];
                }

                continue;
            }

            $kelompok[] = ['t' => $m['t'], 'oleh' => $m['oleh']];
        }

        $nama = $baris->mapWithKeys(fn ($r) => [$r->Kode_Competency => [$r->Nama_Competency, (int) $r->urut]])->all();
        $lalu = [];
        $hasil = [];

        foreach ($kelompok as $g) {
            $kini = [];

            foreach ($baris as $r) {
                $hidup = $detik($r->Created_At) <= $g['t']
                    && ($r->Flag_Aktif === 'Y' || $detik($r->Updated_At) > $g['t']);

                if ($hidup) {
                    $kini[$r->Kode_Competency][] = $this->teksPolos((string) $r->Isi);
                }
            }

            $kini = array_map(fn ($isi) => implode("\n", $isi), $kini);
            $perubahan = [];

            foreach (array_unique([...array_keys($lalu), ...array_keys($kini)]) as $kode) {
                $sebelum = $lalu[$kode] ?? null;
                $sesudah = $kini[$kode] ?? null;

                if ($sebelum !== $sesudah) {
                    $perubahan[] = ['kolom' => 'catatan', 'kode' => $kode, 'nama' => $nama[$kode][0] ?? $kode,
                        'urut' => $nama[$kode][1] ?? 0, 'sebelum' => $sebelum, 'sesudah' => $sesudah];
                }
            }

            if ($perubahan !== []) {
                usort($perubahan, fn ($a, $b) => $a['urut'] <=> $b['urut']);
                $hasil[] = [
                    'pada' => date('Y-m-d H:i:s', $g['t']),
                    'oleh' => $g['oleh'] === null ? null : (int) $g['oleh'],
                    'perubahan' => array_map(fn ($p) => array_diff_key($p, ['urut' => 1]), $perubahan),
                ];
            }

            $lalu = $kini;
        }

        return $hasil;
    }

    /** Catatan disimpan sebagai teks kaya; riwayat membandingkan dan menampilkan teksnya saja. */
    private function teksPolos(string $html): string
    {
        $teks = preg_replace('~<\s*(br|/p|/li|/div|/blockquote)\b[^>]*>~i', "\n", $html);
        $teks = html_entity_decode(strip_tags((string) $teks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("~\n{3,}~", "\n\n", preg_replace('~[ \t]+\n~', "\n", $teks)));
    }

    private function balancingLama(int $participantId, $awalPotret)
    {
        return DB::table('N_PA_Balancing_Jejak_Hitung')
            ->where('PA_Participant_Id', $participantId)
            ->when($awalPotret !== null, fn ($q) => $q->where('Created_At', '<', $awalPotret))
            ->orderBy('Id_PA_Balancing_Jejak_Hitung')
            ->get();
    }

    /** @return array<int, string> */
    private function namaPengguna(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($v) => $v !== null)));

        return $ids === [] ? [] : DB::table('KPI_Users')->whereIn('Id_Users', $ids)->get(['Id_Users', 'Nama', 'Username'])
            ->mapWithKeys(fn ($u) => [(int) $u->Id_Users => trim((string) ($u->Nama ?? '')) ?: (string) $u->Username])->all();
    }

    /** @return array<int, string> */
    private function namaKuadran(array $peristiwa): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn ($e) => $e['angka']['kuadran_id'] ?? null, $peristiwa))));

        return $ids === [] ? [] : DB::table('N_PA_Hav_Quadrant')->whereIn('Id_PA_Hav_Quadrant', $ids)
            ->pluck('Nama_Kuadran', 'Id_PA_Hav_Quadrant')->mapWithKeys(fn ($v, $k) => [(int) $k => $v])->all();
    }
}
