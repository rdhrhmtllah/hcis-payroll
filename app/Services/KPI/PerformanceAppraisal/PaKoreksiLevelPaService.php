<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use RuntimeException;

/**
 * Master "Koreksi Level PA" (DDL-2026-177) — tulis & baca koreksi level per karyawan.
 *
 * Status sebuah koreksi dihitung, bukan disimpan:
 *   AKTIF          dipakai saat peserta siklus dibentuk;
 *   PERLU_DITINJAU level HRIS orang itu sudah berubah sejak koreksi dibuat → TIDAK dipakai
 *                  (lihat PaLevelPaResolver::basi); diselesaikan dengan "Konfirmasi" atau "Cabut";
 *   BELUM_BERLAKU / KEDALUWARSA di luar masa berlaku; DICABUT  Flag_Aktif = 'T'.
 *
 * Satu koreksi aktif per karyawan (indeks unik). Impor Excel SEMUA-ATAU-TIDAK: satu baris salah
 * menolak seluruh berkas dengan daftar galat per baris, supaya tidak ada separuh data masuk.
 */
class PaKoreksiLevelPaService
{
    public const MAKS_IMPOR = 2000;

    /* Kolom pertama Kode_Karyawan HRIS (mis. A2), BUKAN NIK KTP. Berkas lama berkepala "NIK"
       tetap diterima saat impor. */
    /* Tanpa Kode Perusahaan: kode karyawan sudah menunjuk satu orang, perusahaannya dicari
       dari data karyawan. Berkas lama yang masih berkolom itu tetap diterima (lihat impor()). */
    private const KOLOM_IMPOR = ['Kode Karyawan', 'Level PA', 'Alasan', 'Berlaku Dari', 'Berlaku Sampai'];

    public function __construct(private readonly PaLevelPaResolver $resolver) {}

    /** @return array<string, mixed> */
    public function daftar(array $saringan = []): array
    {
        $halaman = max(1, (int) ($saringan['halaman'] ?? 1));
        $per = min(100, max(10, (int) ($saringan['per_halaman'] ?? 25)));
        $hariIni = now()->format('Y-m-d');
        $status = $this->sqlStatus($hariIni);

        $q = $this->dasar();

        if (($kata = trim((string) ($saringan['q'] ?? ''))) !== '') {
            $q->where(fn ($w) => $w->where('k.Nama', 'like', "%{$kata}%")->orWhere('lp.Kode_Karyawan', 'like', "{$kata}%"));
        }

        if (($st = strtoupper(trim((string) ($saringan['status'] ?? '')))) !== '') {
            $q->whereRaw("{$status} = ?", [$st]);
        }

        $total = (clone $q)->count();
        $cacah = $this->dasar()->selectRaw("{$status} as st, COUNT(*) as n")->groupBy(DB::raw($status))->pluck('n', 'st')->all();

        $baris = $q->orderByRaw("CASE {$status} WHEN 'PERLU_DITINJAU' THEN 0 WHEN 'AKTIF' THEN 1 ELSE 2 END")
            ->orderBy('k.Nama')->orderBy('lp.Id_PA_Karyawan_Level_PA')
            ->forPage($halaman, $per)
            ->get([
                'lp.Id_PA_Karyawan_Level_PA as id', 'lp.Kode_Perusahaan as kode_perusahaan', 'lp.Kode_Karyawan as kode_karyawan',
                'k.Nama as nama', 'k.Aktif as karyawan_aktif',
                'lp.ID_Level_PA as id_level_pa', 'lpa_l.Nama_Level as nama_level_pa',
                'v.ID_Level as id_level_hris', 'hris_l.Keterangan as nama_level_hris', 'v.nama_jabatan as jabatan',
                'lp.ID_Level_HRIS_Saat_Dibuat as id_level_hris_saat_dibuat', 'awal_l.Keterangan as nama_level_hris_saat_dibuat',
                'lp.Alasan as alasan', 'lp.Berlaku_Dari as berlaku_dari', 'lp.Berlaku_Sampai as berlaku_sampai',
                'lp.Created_At as dibuat_pada', 'lp.Updated_At as diubah_pada', 'lp.Cancelled_At as dicabut_pada', 'lp.Alasan_Cabut as alasan_cabut',
                DB::raw("{$status} as status"),
            ])
            ->map(function ($r) {
                $r = (array) $r;
                $r['berlaku_dari'] = $r['berlaku_dari'] === null ? null : substr((string) $r['berlaku_dari'], 0, 10);
                $r['berlaku_sampai'] = $r['berlaku_sampai'] === null ? null : substr((string) $r['berlaku_sampai'], 0, 10);

                return $r;
            })
            ->values()->all();

        return [
            'baris' => $baris,
            'total' => $total,
            'halaman' => $halaman,
            'per_halaman' => $per,
            'cacah' => [
                'AKTIF' => (int) ($cacah['AKTIF'] ?? 0),
                'PERLU_DITINJAU' => (int) ($cacah['PERLU_DITINJAU'] ?? 0),
                'BELUM_BERLAKU' => (int) ($cacah['BELUM_BERLAKU'] ?? 0),
                'KEDALUWARSA' => (int) ($cacah['KEDALUWARSA'] ?? 0),
                'DICABUT' => (int) ($cacah['DICABUT'] ?? 0),
            ],
            'level' => $this->opsiLevel(),
        ];
    }

    /** Karyawan aktif untuk formulir, beserta level HRIS dan koreksi aktifnya. */
    public function cariKaryawan(string $kata, int $batas = 20): array
    {
        $kata = trim($kata);
        if (mb_strlen($kata) < 2) {
            return [];
        }

        return DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->leftJoin('HRIS_Level as l', 'l.ID_Level', '=', 'v.ID_Level')
            ->leftJoin('N_PA_Karyawan_Level_PA as lp', fn ($j) => $j->on('lp.Kode_Perusahaan', '=', 'k.Kode_Perusahaan')
                ->on('lp.Kode_Karyawan', '=', 'k.Kode_Karyawan')->where('lp.Flag_Aktif', '=', 'Y'))
            ->where('k.Aktif', 'Y')
            ->where(fn ($w) => $w->where('k.Nama', 'like', "%{$kata}%")->orWhere('k.Kode_Karyawan', 'like', "{$kata}%"))
            ->orderBy('k.Nama')->limit(max(1, min(50, $batas)))
            ->get(['k.Kode_Perusahaan as kode_perusahaan', 'k.Kode_Karyawan as kode_karyawan', 'k.Nama as nama',
                'v.ID_Level as id_level_hris', 'l.Keterangan as nama_level_hris', 'v.nama_jabatan as jabatan',
                'lp.Id_PA_Karyawan_Level_PA as koreksi_id'])
            ->map(fn ($r) => (array) $r)->all();
    }

    /** @return array<string, mixed> */
    public function simpan(array $d, ?int $olehUserId = null): array
    {
        $id = $this->sisip($d, $olehUserId);

        return ['id' => $id, 'dampak' => $this->dampakSiklus([[(string) $d['kode_perusahaan'], (string) $d['kode_karyawan']]])];
    }

    private function sisip(array $d, ?int $olehUserId): int
    {
        [$k, $levelHris, $levelJabatan] = $this->karyawanAktif((string) $d['kode_perusahaan'], (string) $d['kode_karyawan']);
        $isi = $this->periksaIsi($d, $levelHris);

        if (DB::table('N_PA_Karyawan_Level_PA')->where('Kode_Perusahaan', $k->Kode_Perusahaan)->where('Kode_Karyawan', $k->Kode_Karyawan)->where('Flag_Aktif', 'Y')->exists()) {
            throw new RuntimeException("{$k->Nama} sudah punya koreksi Level PA aktif. Ubah atau cabut yang ada.");
        }

        $id = DB::table('N_PA_Karyawan_Level_PA')->insertGetId($isi + [
            'Kode_Perusahaan' => $k->Kode_Perusahaan,
            'Kode_Karyawan' => $k->Kode_Karyawan,
            'ID_Level_HRIS_Saat_Dibuat' => $levelHris,
            'ID_Level_Jabatan_Saat_Dibuat' => $levelJabatan,
            'Flag_Aktif' => 'Y',
            'Created_By' => $olehUserId,
        ]);

        return (int) $id;
    }

    /** Batas orang per simpan-banyak; impor Excel untuk jumlah yang lebih besar. */
    public const MAKS_SEKALIGUS = 200;

    /**
     * Satu koreksi (level, alasan, masa berlaku sama) untuk BANYAK karyawan sekaligus.
     * SEMUA-ATAU-TIDAK: semua orang diperiksa lebih dulu dan galatnya dikumpulkan per nama,
     * supaya admin memperbaiki pilihannya sekali jalan, bukan satu per satu.
     *
     * @param  list<array{kode_perusahaan:string, kode_karyawan:string}>  $karyawan
     * @return array{dibuat:int, id:list<int>}
     */
    public function simpanBanyak(array $karyawan, array $d, ?int $olehUserId = null): array
    {
        $unik = [];
        foreach ($karyawan as $k) {
            $unik[self::kunciKaryawan((string) ($k['kode_perusahaan'] ?? ''), (string) ($k['kode_karyawan'] ?? ''))] = $k;
        }
        if ($unik === []) {
            throw new RuntimeException('Pilih minimal satu karyawan.');
        }
        if (count($unik) > self::MAKS_SEKALIGUS) {
            throw new RuntimeException('Maksimal '.self::MAKS_SEKALIGUS.' karyawan sekaligus. Untuk lebih banyak, pakai Impor Excel.');
        }

        $galat = [];
        foreach ($unik as $k) {
            try {
                [$kar, $levelHris] = $this->karyawanAktif((string) $k['kode_perusahaan'], (string) $k['kode_karyawan']);
                $this->periksaIsi($d, $levelHris);
                if (DB::table('N_PA_Karyawan_Level_PA')->where('Kode_Perusahaan', $kar->Kode_Perusahaan)->where('Kode_Karyawan', $kar->Kode_Karyawan)->where('Flag_Aktif', 'Y')->exists()) {
                    throw new RuntimeException('sudah punya koreksi Level PA aktif.');
                }
            } catch (RuntimeException $e) {
                $siapa = isset($kar) && trim((string) $kar->Kode_Karyawan) === trim((string) $k['kode_karyawan']) ? "{$kar->Nama} ({$kar->Kode_Karyawan})" : (string) $k['kode_karyawan'];
                $galat[] = "{$siapa}: ".$e->getMessage();
            }
            unset($kar);
        }

        if ($galat !== []) {
            throw new RuntimeException('Tidak ada yang disimpan. '.count($galat).' karyawan bermasalah — '.implode(' | ', array_slice($galat, 0, 10)).(count($galat) > 10 ? ' | …' : ''));
        }

        $id = [];
        DB::transaction(function () use ($unik, $d, $olehUserId, &$id) {
            foreach ($unik as $k) {
                $id[] = $this->sisip($d + ['kode_perusahaan' => $k['kode_perusahaan'], 'kode_karyawan' => $k['kode_karyawan']], $olehUserId);
            }
        });

        return ['dibuat' => count($id), 'id' => $id, 'dampak' => $this->dampakSiklus(array_map(fn ($k) => [$k['kode_perusahaan'], $k['kode_karyawan']], array_values($unik)))];
    }

    /**
     * Siklus yang belum terbit tempat orang-orang ini SUDAH menjadi peserta. Level PA dibekukan
     * saat peserta dibentuk, jadi koreksi baru tidak otomatis sampai ke sana: siklus PREPARED
     * perlu Siapkan ulang, siklus yang sudah dikunci tetap memakai level lamanya.
     *
     * @param  list<array{0:string, 1:string}>  $orang
     * @return list<array{cycle_id:int, nama:string, status:string, jumlah:int, tindakan:string}>
     */
    public function dampakSiklus(array $orang): array
    {
        if ($orang === []) {
            return [];
        }

        return DB::table('N_PA_Participant as p')
            ->join('N_PA_Cycle as c', 'c.Id_PA_Cycle', '=', 'p.PA_Cycle_Id')
            ->where('p.Flag_Aktif', 'Y')
            ->whereIn('c.Status_Cycle', ['PREPARED', 'LOCKED', 'OPEN', 'CLOSED'])
            ->where(function ($w) use ($orang) {
                foreach ($orang as [$pt, $kode]) {
                    $w->orWhere(fn ($x) => $x->where('p.Kode_Perusahaan', trim((string) $pt))->where('p.Kode_Karyawan', trim((string) $kode)));
                }
            })
            ->groupBy('c.Id_PA_Cycle', 'c.Nama_Cycle', 'c.Status_Cycle')
            ->orderByDesc('c.Id_PA_Cycle')
            ->get(['c.Id_PA_Cycle', 'c.Nama_Cycle', 'c.Status_Cycle', DB::raw('COUNT(*) as jumlah')])
            ->map(fn ($r) => [
                'cycle_id' => (int) $r->Id_PA_Cycle,
                'nama' => (string) $r->Nama_Cycle,
                'status' => (string) $r->Status_Cycle,
                'jumlah' => (int) $r->jumlah,
                'tindakan' => $r->Status_Cycle === 'PREPARED'
                    ? 'Jalankan Siapkan lagi di siklus ini supaya Level PA terbaru dipakai.'
                    : 'Siklus ini sudah dikunci; Level PA terbaru berlaku mulai siklus berikutnya.',
            ])->all();
    }

    private static function kunciKaryawan(string $pt, string $kode): string
    {
        return strtoupper(trim($pt)).'|'.strtoupper(trim($kode));
    }

    /**
     * Mengubah koreksi aktif. Level HRIS acuannya DIPERBARUI ke level sekarang — menyimpan ulang
     * berarti admin sudah meninjau keadaan HRIS terbaru, jadi status PERLU_DITINJAU selesai.
     */
    public function ubah(int $id, array $d, ?int $olehUserId = null): array
    {
        $lama = DB::table('N_PA_Karyawan_Level_PA')->where('Id_PA_Karyawan_Level_PA', $id)->where('Flag_Aktif', 'Y')->first();
        if ($lama === null) {
            throw new RuntimeException("Koreksi #{$id} tidak ada atau sudah dicabut.");
        }

        [, $levelHris, $levelJabatan] = $this->karyawanAktif($lama->Kode_Perusahaan, $lama->Kode_Karyawan);
        $isi = $this->periksaIsi($d, $levelHris);

        DB::table('N_PA_Karyawan_Level_PA')->where('Id_PA_Karyawan_Level_PA', $id)->update($isi + [
            'ID_Level_HRIS_Saat_Dibuat' => $levelHris,
            'ID_Level_Jabatan_Saat_Dibuat' => $levelJabatan,
            'Updated_At' => FormatTanggalHelper::getCurrentTime(),
            'Updated_By' => $olehUserId,
        ]);

        return ['id' => $id, 'dampak' => $this->dampakSiklus([[$lama->Kode_Perusahaan, $lama->Kode_Karyawan]])];
    }

    public function cabut(int $id, string $alasan, ?int $olehUserId = null): array
    {
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 5) {
            throw new RuntimeException('Alasan pencabutan minimal 5 karakter.');
        }

        $n = DB::table('N_PA_Karyawan_Level_PA')->where('Id_PA_Karyawan_Level_PA', $id)->where('Flag_Aktif', 'Y')->update([
            'Flag_Aktif' => 'T',
            'Cancelled_At' => FormatTanggalHelper::getCurrentTime(),
            'Cancelled_By' => $olehUserId,
            'Alasan_Cabut' => mb_substr($alasan, 0, 500),
        ]);

        if ($n === 0) {
            throw new RuntimeException("Koreksi #{$id} tidak ada atau sudah dicabut.");
        }

        $o = DB::table('N_PA_Karyawan_Level_PA')->where('Id_PA_Karyawan_Level_PA', $id)->first(['Kode_Perusahaan', 'Kode_Karyawan']);

        return ['id' => $id, 'dampak' => $this->dampakSiklus([[$o->Kode_Perusahaan, $o->Kode_Karyawan]])];
    }

    /* ------------------------------------------------------------------ *
     * Deteksi calon (Tahap 5)                                             *
     * ------------------------------------------------------------------ */

    /** Kata jabatan yang menandakan fungsi setara Officer ke atas. */
    private const KATA_JABATAN = ['SPV', 'SUPERVISOR', 'OFFICER', 'KOORDINATOR', 'COORDINATOR', 'LEADER', 'KEPALA', 'HEAD', 'MANAGER', 'CHIEF'];

    /**
     * Karyawan yang LEVEL HRIS-nya kemungkinan tidak sesuai pekerjaannya — bahan tinjauan admin,
     * TIDAK diubah otomatis. Sasaran: aktif, belum punya koreksi aktif, levelnya di bawah OFFICER
     * (hierarki 1–3) atau belum diisi. Tanda: punya bawahan di relasi organisasi (BAWAHAN sebagai
     * subjek, atau dicatat sebagai ATASAN orang lain) dan/atau jabatannya bernada penyelia.
     *
     * @return array{baris:list<array<string, mixed>>, total:int, saran_level:?array}
     */
    public function calon(array $saringan = []): array
    {
        $officer = DB::table('HRIS_Level')->where('Keterangan', 'OFFICER')->first(['ID_Level', 'Level_Hierarchy']);
        $batasHier = (int) ($officer->Level_Hierarchy ?? 4);
        $hariIni = now()->format('Y-m-d');

        $tipe = DB::table('N_HRIS_Organization_Relationship_Type')->whereIn('Kode_Relasi', ['BAWAHAN', 'ATASAN', 'DIRECT_SUPERVISOR'])
            ->pluck('Id_Organization_Relationship_Type', 'Kode_Relasi');
        $berlaku = fn ($q) => $q->where('r.Flag_Aktif', 'Y')->whereNull('r.Cancelled_At')
            ->where(fn ($w) => $w->whereNull('r.Berlaku_Dari')->orWhere('r.Berlaku_Dari', '<=', $hariIni))
            ->where(fn ($w) => $w->whereNull('r.Berlaku_Sampai')->orWhere('r.Berlaku_Sampai', '>=', $hariIni));

        // Bawahan per orang: union dua arah, dihitung sebagai pasangan berbeda.
        $pasangan = [];
        if (isset($tipe['BAWAHAN'])) {
            foreach ($berlaku(DB::table('N_HRIS_Organization_Relationship as r'))->where('r.Id_Organization_Relationship_Type', $tipe['BAWAHAN'])
                ->get(['r.Kode_Perusahaan_Subjek as pa', 'r.Kode_Karyawan_Subjek as a', 'r.Kode_Karyawan_Terkait as b']) as $x) {
                $pasangan[PaLevelPaResolver::kunci($x->pa, $x->a)][strtoupper(trim($x->b))] = true;
            }
        }
        $tipeAtasan = array_values(array_filter([$tipe['ATASAN'] ?? null, $tipe['DIRECT_SUPERVISOR'] ?? null]));
        if ($tipeAtasan !== []) {
            foreach ($berlaku(DB::table('N_HRIS_Organization_Relationship as r'))->whereIn('r.Id_Organization_Relationship_Type', $tipeAtasan)
                ->get(['r.Kode_Perusahaan_Terkait as pa', 'r.Kode_Karyawan_Terkait as a', 'r.Kode_Karyawan_Subjek as b']) as $x) {
                $pasangan[PaLevelPaResolver::kunci($x->pa, $x->a)][strtoupper(trim($x->b))] = true;
            }
        }

        $kata = trim((string) ($saringan['q'] ?? ''));
        $orang = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->leftJoin('HRIS_Level as l', 'l.ID_Level', '=', 'v.ID_Level')
            ->leftJoin('View_Divisi_Sub_Divisi as vd', 'vd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Aktif', 'Y')
            ->whereNotExists(fn ($q) => $q->from('N_PA_Karyawan_Level_PA as lp')->whereColumn('lp.Kode_Perusahaan', 'k.Kode_Perusahaan')
                ->whereColumn('lp.Kode_Karyawan', 'k.Kode_Karyawan')->where('lp.Flag_Aktif', 'Y'))
            ->where(fn ($w) => $w->whereNull('v.ID_Level')
                ->orWhereBetween('l.Level_Hierarchy', [1, $batasHier - 1])
                ->orWhereIn('l.Keterangan', ['NOT SET', 'BELUM']))
            ->when($kata !== '', fn ($q) => $q->where(fn ($w) => $w->where('k.Nama', 'like', "%{$kata}%")->orWhere('k.Kode_Karyawan', 'like', "{$kata}%")))
            ->orderBy('k.Nama')
            ->get(['k.Kode_Perusahaan as kode_perusahaan', 'k.Kode_Karyawan as kode_karyawan', 'k.Nama as nama',
                'v.ID_Level as id_level_hris', 'l.Keterangan as nama_level_hris', 'v.nama_jabatan as jabatan', 'vd.nama_divisi as divisi']);

        $pola = '/\b('.implode('|', self::KATA_JABATAN).')\b/i';
        $baris = [];
        foreach ($orang as $o) {
            $nBawahan = count($pasangan[PaLevelPaResolver::kunci($o->kode_perusahaan, $o->kode_karyawan)] ?? []);
            $jabatanCocok = $o->jabatan !== null && preg_match($pola, (string) $o->jabatan) === 1;
            if ($nBawahan === 0 && ! $jabatanCocok) {
                continue;
            }
            $tanda = [];
            if ($nBawahan > 0) {
                $tanda[] = "Punya {$nBawahan} bawahan di relasi organisasi";
            }
            if ($jabatanCocok) {
                $tanda[] = "Jabatan \"{$o->jabatan}\" bernada penyelia";
            }
            $baris[] = (array) $o + [
                'jumlah_bawahan' => $nBawahan,
                'jabatan_cocok' => $jabatanCocok,
                'tanda' => $tanda,
                // Usulan alasan siap pakai di formulir; admin tetap boleh mengubahnya.
                'usulan_alasan' => 'Terdeteksi: '.implode('; ', $tanda).'. Level HRIS '.($o->nama_level_hris ?: 'belum diisi').'.',
            ];
        }

        // Yang bertanda dua didahulukan, lalu yang bawahannya terbanyak.
        usort($baris, fn ($a, $b) => [(int) $b['jabatan_cocok'] + (int) ($b['jumlah_bawahan'] > 0), $b['jumlah_bawahan']]
            <=> [(int) $a['jabatan_cocok'] + (int) ($a['jumlah_bawahan'] > 0), $a['jumlah_bawahan']]);

        return [
            'baris' => array_slice($baris, 0, 500),
            'total' => count($baris),
            'saran_level' => $officer === null ? null : ['id' => (int) $officer->ID_Level, 'nama' => 'OFFICER'],
        ];
    }

    /* ------------------------------------------------------------------ *
     * Excel                                                               *
     * ------------------------------------------------------------------ */

    /** Ekspor koreksi (atau templat kosong) dalam kolom yang sama dengan impor. */
    public function ekspor(bool $templat = false): string
    {
        $buku = new Spreadsheet;
        $ws = $buku->getActiveSheet();
        $ws->setTitle($templat ? 'Templat' : 'Koreksi Level PA');
        $kolom = $templat ? self::KOLOM_IMPOR : [...self::KOLOM_IMPOR, 'Nama', 'Level HRIS Sekarang', 'Status'];
        $ws->fromArray([$kolom], null, 'A1');
        $akhir = Coordinate::stringFromColumnIndex(count($kolom));
        $ws->getStyle("A1:{$akhir}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']],
        ]);
        foreach (range(1, count($kolom)) as $i) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth([16, 22, 48, 14, 15, 28, 22, 16][$i - 1] ?? 18);
        }

        /* Templat SENGAJA tanpa baris contoh: baris contoh yang lupa dihapus ikut terimpor
           sebagai koreksi sungguhan. Contohnya ada di lembar Petunjuk. */
        $r = 2;
        if (! $templat) {
            foreach ($this->dasar()->where('lp.Flag_Aktif', 'Y')->orderBy('k.Nama')->get([
                'lp.Kode_Karyawan', 'lpa_l.Nama_Level as lv', 'lp.Alasan', 'lp.Berlaku_Dari', 'lp.Berlaku_Sampai',
                'k.Nama', 'hris_l.Keterangan as hris', DB::raw($this->sqlStatus(now()->format('Y-m-d')).' as st'),
            ]) as $b) {
                $ws->setCellValueExplicit("A{$r}", trim((string) $b->Kode_Karyawan), DataType::TYPE_STRING);
                $ws->fromArray([[$b->lv, $b->Alasan, substr((string) $b->Berlaku_Dari, 0, 10),
                    $b->Berlaku_Sampai ? substr((string) $b->Berlaku_Sampai, 0, 10) : '', $b->Nama, $b->hris, $b->st]], null, "B{$r}");
                $r++;
            }
        }

        $level = $this->opsiLevel();
        [$awalLevel, $akhirLevel] = $this->lembarPetunjuk($buku, $level);

        // Isian dipandu langsung di sel: kode sebagai teks, Level PA dari daftar, tanggal YYYY-MM-DD.
        $batas = max($r - 1, 1) + self::MAKS_IMPOR;
        $ws->getStyle("A2:A{$batas}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $ws->getStyle("D2:E{$batas}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        $dv = $ws->getDataValidation("B2:B{$batas}");
        $dv->setType(DataValidation::TYPE_LIST)
            ->setErrorStyle(DataValidation::STYLE_STOP)
            ->setAllowBlank(true)
            ->setShowDropDown(true)
            ->setShowInputMessage(true)
            ->setShowErrorMessage(true)
            ->setPromptTitle('Level PA')
            ->setPrompt('Pilih dari daftar. Daftar lengkapnya ada di lembar Petunjuk.')
            ->setErrorTitle('Level PA tidak dikenal')
            ->setError('Pilih salah satu level di daftar (lihat lembar Petunjuk).')
            ->setFormula1("Petunjuk!\$A\${$awalLevel}:\$A\${$akhirLevel}");

        $catatan = [
            'A1' => 'Kode karyawan di HRIS (mis. A2, B6), BUKAN NIK KTP. Wajib.',
            'B1' => 'Pilih dari daftar. Tulisan yang sah ada di lembar Petunjuk. Tidak boleh sama dengan level HRIS orangnya.',
            'C1' => 'Wajib, minimal 5 karakter.',
            'D1' => 'Format YYYY-MM-DD. Kosong = hari ini.',
            'E1' => 'Format YYYY-MM-DD. Kosong = berlaku seterusnya.',
        ];
        foreach ($catatan as $sel => $teks) {
            $ws->getComment($sel)->getText()->createText($teks);
            $ws->getComment($sel)->setWidth('220pt')->setHeight('60pt');
        }

        $ws->freezePane('A2');
        $buku->setActiveSheetIndex(0);
        $tulis = IOFactory::createWriter($buku, 'Xlsx');
        ob_start();
        $tulis->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * Lembar "Petunjuk": cara mengisi tiap kolom, daftar Level PA yang sah (sumber dropdown), dan
     * contoh baris. Mengembalikan baris awal/akhir daftar level untuk rumus validasi.
     *
     * @param  list<array{id:int, nama:string, jumlah:int}>  $level
     * @return array{0:int, 1:int}
     */
    private function lembarPetunjuk(Spreadsheet $buku, array $level): array
    {
        $p = $buku->createSheet();
        $p->setTitle('Petunjuk');
        $p->getColumnDimension('A')->setWidth(24);
        $p->getColumnDimension('B')->setWidth(12);
        $p->getColumnDimension('C')->setWidth(90);
        $kepala = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']]];

        $p->setCellValue('A1', 'Petunjuk Impor Koreksi Level PA');
        $p->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $p->setCellValue('A2', 'Isi lembar pertama mulai baris 2. Impor bersifat semua-atau-tidak: satu baris salah, tidak ada yang disimpan.');

        $p->fromArray([['Kolom', 'Wajib', 'Cara mengisi']], null, 'A4');
        $p->getStyle('A4:C4')->applyFromArray($kepala);
        $p->fromArray([
            ['Kode Karyawan', 'Ya', 'Kode karyawan di HRIS, mis. A2 atau B6 — BUKAN NIK KTP. Ditulis sebagai teks.'],
            ['Level PA', 'Ya', 'Pilih dari dropdown, atau tulis PERSIS seperti kolom "Tulis di templat" pada daftar di bawah (huruf besar/kecil bebas). ID level juga diterima. Tidak boleh sama dengan level HRIS orangnya.'],
            ['Alasan', 'Ya', 'Minimal 5 karakter, maksimal 500. Tercatat sebagai jejak audit.'],
            ['Berlaku Dari', 'Tidak', 'Format YYYY-MM-DD, mis. 2026-10-01. Kosong = hari impor.'],
            ['Berlaku Sampai', 'Tidak', 'Format YYYY-MM-DD. Kosong = berlaku seterusnya. Tidak boleh lebih awal dari Berlaku Dari.'],
        ], null, 'A5');
        $p->setCellValue('A11', 'Karyawan yang sudah punya koreksi aktif akan DIPERBARUI, bukan digandakan. Maksimal '.self::MAKS_IMPOR.' baris per impor.');

        $p->fromArray([['Tulis di templat', 'ID Level', 'Karyawan aktif dengan level ini di HRIS']], null, 'A14');
        $p->getStyle('A14:C14')->applyFromArray($kepala);
        $awal = 15;
        $baris = $awal;
        foreach ($level as $l) {
            $p->setCellValueExplicit("A{$baris}", $l['nama'], DataType::TYPE_STRING);
            $p->setCellValue("B{$baris}", $l['id']);
            $p->setCellValue("C{$baris}", $l['jumlah']);
            $baris++;
        }
        $akhir = max($awal, $baris - 1);
        $p->getStyle("A{$awal}:A{$akhir}")->getFont()->setBold(true);
        $p->getStyle("B{$awal}:C{$akhir}")->getAlignment()->setHorizontal('left');

        $contoh = $akhir + 2;
        $p->setCellValue("A{$contoh}", 'Contoh baris (salin ke lembar pertama, sesuaikan isinya):');
        $p->getStyle("A{$contoh}")->getFont()->setBold(true);
        $p->fromArray([self::KOLOM_IMPOR], null, 'A'.($contoh + 1));
        $p->getStyle('A'.($contoh + 1).':E'.($contoh + 1))->applyFromArray($kepala);
        $p->setCellValueExplicit('A'.($contoh + 2), 'A2', DataType::TYPE_STRING);
        $p->fromArray([['OFFICER', 'Menjabat fungsi Officer dan membawahi 3 orang', now()->format('Y-m-d'), '']], null, 'B'.($contoh + 2));
        $p->getStyle('A5:C11')->getAlignment()->setWrapText(true)->setVertical('top');

        return [$awal, $akhir];
    }

    /**
     * Impor dari .xlsx: SEMUA-ATAU-TIDAK. Baris yang karyawannya sudah punya koreksi aktif
     * MEMPERBARUI koreksi itu (sama dengan "Ubah").
     *
     * @return array{dibuat:int, diperbarui:int}
     */
    public function impor(string $jalurBerkas, ?int $olehUserId = null): array
    {
        // Lembar PERTAMA, bukan yang aktif: berkas bisa tersimpan sedang membuka lembar Petunjuk.
        $baris = IOFactory::load($jalurBerkas)->getSheet(0)->toArray(null, true, true, false);
        $kepala = array_map(fn ($x) => strtolower(trim((string) $x)), array_shift($baris) ?? []);
        if (($kepala[0] ?? '') === 'nik') {
            $kepala[0] = 'kode karyawan'; // templat lama
        }
        /* Templat lama berkolom "Kode Perusahaan" di B: kolomnya dibuang sebelum dibaca, isinya
           tetap dipakai bila diisi. */
        $berPerusahaan = ($kepala[1] ?? '') === 'kode perusahaan';
        if ($berPerusahaan) {
            array_splice($kepala, 1, 1);
        }
        $harap = array_map('strtolower', self::KOLOM_IMPOR);

        if (array_slice($kepala, 0, count($harap)) !== $harap) {
            throw new RuntimeException('Kolom berkas tidak sesuai templat: '.implode(' | ', self::KOLOM_IMPOR).'. Unduh templat lebih dulu.');
        }

        $baris = array_values(array_filter($baris, fn ($b) => trim((string) ($b[0] ?? '')) !== ''));
        if ($baris === []) {
            throw new RuntimeException('Berkas tidak berisi baris data.');
        }
        if (count($baris) > self::MAKS_IMPOR) {
            throw new RuntimeException('Maksimal '.self::MAKS_IMPOR.' baris per impor.');
        }

        $levelPerNama = collect($this->opsiLevel())->mapWithKeys(fn ($l) => [strtoupper($l['nama']) => $l['id']])->all();
        $siap = [];
        $galat = [];
        $dilihat = [];

        foreach ($baris as $i => $b) {
            $no = $i + 2;
            $nik = trim((string) $b[0]);
            $ptBerkas = $berPerusahaan ? trim((string) ($b[1] ?? '')) : '';
            if ($berPerusahaan) {
                array_splice($b, 1, 1);
            }
            $lvTeks = trim((string) ($b[1] ?? ''));
            $lv = ctype_digit($lvTeks) ? (int) $lvTeks : ($levelPerNama[strtoupper($lvTeks)] ?? null);

            if (isset($dilihat[$nik])) {
                $galat[] = "Baris {$no}: kode karyawan {$nik} muncul dua kali.";

                continue;
            }
            $dilihat[$nik] = true;

            try {
                $pt = $ptBerkas !== '' ? $ptBerkas : $this->perusahaanDariKode($nik);
                if ($lv === null) {
                    throw new RuntimeException("Level PA '{$lvTeks}' tidak dikenal. Pakai tulisan di lembar Petunjuk, mis. ".implode(', ', array_slice(array_keys($levelPerNama), 0, 4)).'.');
                }
                $d = ['kode_perusahaan' => $pt, 'kode_karyawan' => $nik, 'id_level_pa' => $lv, 'alasan' => (string) ($b[2] ?? ''),
                    'berlaku_dari' => $this->tanggal($b[3] ?? null) ?? now()->format('Y-m-d'), 'berlaku_sampai' => $this->tanggal($b[4] ?? null)];
                [$k, $levelHris] = $this->karyawanAktif($pt, $nik);
                $this->periksaIsi($d, $levelHris);
                $ada = DB::table('N_PA_Karyawan_Level_PA')->where('Kode_Perusahaan', $k->Kode_Perusahaan)->where('Kode_Karyawan', $k->Kode_Karyawan)->where('Flag_Aktif', 'Y')->value('Id_PA_Karyawan_Level_PA');
                $siap[] = [$ada, $d];
            } catch (RuntimeException $e) {
                $galat[] = "Baris {$no} ({$nik}): ".$e->getMessage();
            }
        }

        if ($galat !== []) {
            throw new RuntimeException('Impor dibatalkan, tidak ada yang disimpan. '.count($galat).' baris bermasalah: '.implode(' | ', array_slice($galat, 0, 15)).(count($galat) > 15 ? ' | …' : ''));
        }

        $hasil = ['dibuat' => 0, 'diperbarui' => 0];
        DB::transaction(function () use ($siap, $olehUserId, &$hasil) {
            foreach ($siap as [$ada, $d]) {
                if ($ada) {
                    $this->ubah((int) $ada, $d, $olehUserId);
                    $hasil['diperbarui']++;
                } else {
                    $this->simpan($d, $olehUserId);
                    $hasil['dibuat']++;
                }
            }
        });

        return $hasil;
    }

    /* ------------------------------------------------------------------ */

    /**
     * Pilihan Level PA = level HRIS yang benar-benar dipakai karyawan aktif, DITAMBAH seluruh level
     * khusus PA yang aktif (Master Level PA, DDL-2026-183) — level khusus PA justru belum dipakai
     * siapa pun sampai dikoreksi ke sana. Level HRIS kosong tetap disembunyikan: tidak punya aturan
     * penilai, bobot, atau paket soal yang teruji. Jumlah orangnya ikut dikirim supaya admin melihat
     * seberapa umum level itu; level khusus PA membawa `khusus_pa`.
     *
     * @return list<array{id:int, nama:string, jumlah:int, khusus_pa:bool}>
     */
    public function opsiLevel(): array
    {
        PaLevelMasterService::sinkronHris();

        $hris = DB::table('Karyawan as k')
            ->join('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->join('N_PA_Level as l', 'l.ID_Level', '=', 'v.ID_Level')
            ->where('k.Aktif', 'Y')
            ->groupBy('l.ID_Level', 'l.Nama_Level', 'l.Urutan')
            ->get(['l.ID_Level', 'l.Nama_Level', 'l.Urutan', DB::raw('COUNT(DISTINCT k.Kode_Karyawan) as jumlah')]);
        $khusus = DB::table('N_PA_Level')->where('Sumber', PaLevelMasterService::SUMBER_PA)->where('Flag_Aktif', 'Y')
            ->get(['ID_Level', 'Nama_Level', 'Urutan', DB::raw('0 as jumlah')]);

        return $hris->concat($khusus)
            ->sortBy([['Urutan', 'asc'], ['ID_Level', 'asc']])
            ->map(fn ($l) => ['id' => (int) $l->ID_Level, 'nama' => (string) $l->Nama_Level, 'jumlah' => (int) $l->jumlah,
                'khusus_pa' => (int) $l->ID_Level >= PaLevelMasterService::ID_PA_PERTAMA])
            ->values()->all();
    }

    private function dasar()
    {
        return DB::table('N_PA_Karyawan_Level_PA as lp')
            ->leftJoin('Karyawan as k', fn ($j) => $j->on('k.Kode_Perusahaan', '=', 'lp.Kode_Perusahaan')->on('k.Kode_Karyawan', '=', 'lp.Kode_Karyawan'))
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->leftJoin('HRIS_Level as hris_l', 'hris_l.ID_Level', '=', 'v.ID_Level')
            ->leftJoin('N_PA_Level as lpa_l', 'lpa_l.ID_Level', '=', 'lp.ID_Level_PA')
            ->leftJoin('HRIS_Level as awal_l', 'awal_l.ID_Level', '=', 'lp.ID_Level_HRIS_Saat_Dibuat');
    }

    private function sqlStatus(string $hariIni): string
    {
        return "(CASE WHEN lp.Flag_Aktif <> 'Y' THEN 'DICABUT'
                      WHEN lp.Berlaku_Sampai IS NOT NULL AND lp.Berlaku_Sampai < '{$hariIni}' THEN 'KEDALUWARSA'
                      WHEN lp.Berlaku_Dari > '{$hariIni}' THEN 'BELUM_BERLAKU'
                      WHEN lp.ID_Level_HRIS_Saat_Dibuat IS NOT NULL AND (v.ID_Level IS NULL OR lp.ID_Level_HRIS_Saat_Dibuat <> v.ID_Level) THEN 'PERLU_DITINJAU'
                      ELSE 'AKTIF' END)";
    }

    /** @return array{0:object, 1:?int, 2:?int} karyawan, level HRIS, level-jabatan HRIS */
    /**
     * Perusahaan seorang karyawan dari kode karyawannya (templat impor tidak lagi menanyakannya).
     * Kode yang aktif di lebih dari satu perusahaan ditolak, bukan ditebak.
     */
    private function perusahaanDariKode(string $kode): string
    {
        $baris = DB::table('Karyawan')->where('Kode_Karyawan', trim($kode))->get(['Kode_Perusahaan', 'Aktif']);
        $aktif = $baris->where('Aktif', 'Y')->pluck('Kode_Perusahaan')->unique()->values();

        if ($aktif->count() > 1) {
            throw new RuntimeException("Kode karyawan {$kode} aktif di lebih dari satu perusahaan ({$aktif->implode(', ')}); koreksi lewat Tambah Koreksi.");
        }

        return (string) ($aktif->first() ?? $baris->first()?->Kode_Perusahaan ?? '001');
    }

    private function karyawanAktif(string $pt, string $kode): array
    {
        $k = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Kode_Perusahaan', trim($pt))->where('k.Kode_Karyawan', trim($kode))
            ->first(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'k.Aktif', 'k.ID_Level_Jabatan', 'v.ID_Level']);

        if ($k === null) {
            throw new RuntimeException("Karyawan {$pt}/{$kode} tidak ditemukan.");
        }
        if ($k->Aktif !== 'Y') {
            throw new RuntimeException("{$k->Nama} bukan karyawan aktif.");
        }

        return [$k, $k->ID_Level === null ? null : (int) $k->ID_Level, $k->ID_Level_Jabatan === null ? null : (int) $k->ID_Level_Jabatan];
    }

    /** @return array<string, mixed> kolom yang ditulis */
    private function periksaIsi(array $d, ?int $levelHris): array
    {
        $lv = (int) ($d['id_level_pa'] ?? 0);
        if (! DB::table('N_PA_Level')->where('ID_Level', $lv)->where('Flag_Aktif', 'Y')->exists()) {
            throw new RuntimeException('Level PA tidak dikenal atau sudah nonaktif.');
        }
        if ($levelHris !== null && $lv === $levelHris) {
            throw new RuntimeException('Level PA sama dengan level HRIS-nya; koreksi tidak diperlukan.');
        }

        $alasan = trim((string) ($d['alasan'] ?? ''));
        if (mb_strlen($alasan) < 5) {
            throw new RuntimeException('Alasan wajib diisi, minimal 5 karakter.');
        }

        $dari = $this->tanggal($d['berlaku_dari'] ?? null) ?? now()->format('Y-m-d');
        $sampai = $this->tanggal($d['berlaku_sampai'] ?? null);
        if ($sampai !== null && $sampai < $dari) {
            throw new RuntimeException('Tanggal "berlaku sampai" lebih awal dari "berlaku dari".');
        }

        return ['ID_Level_PA' => $lv, 'Alasan' => mb_substr($alasan, 0, 500), 'Berlaku_Dari' => $dari, 'Berlaku_Sampai' => $sampai];
    }

    private function tanggal($v): ?string
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        if (is_numeric($v)) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $v)->format('Y-m-d');
        }
        $t = strtotime((string) $v);
        if ($t === false) {
            throw new RuntimeException("Tanggal '{$v}' tidak terbaca (pakai YYYY-MM-DD).");
        }

        return date('Y-m-d', $t);
    }
}
