<?php

namespace App\Services\SYSTEM\Organization;

use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Ekspor Relasi Organisasi ke Excel — satu baris per relasi, siap diolah.
 *
 * Dua sheet:
 *  - "Relasi": tabel datar (bukan sel gabungan), header beku + autofilter, NIK sebagai TEKS
 *    (nol di depan tidak hilang), tanggal sebagai tanggal Excel. Bentuk ini yang paling mudah
 *    disaring, di-pivot, dan di-VLOOKUP.
 *  - "Ringkasan": saringan yang dipakai, waktu & pengekspor, jumlah relasi per kategori.
 *
 * Saringannya sama dengan daftar di layar (relasi aktif, belum dicabut) ditambah pilihan
 * KATEGORI ganda dan divisi subjek. Batas 50.000 baris supaya worker tidak kehabisan memori.
 */
class OrganizationRelationExportService
{
    private const BATAS_BARIS = 50000;

    /* Susunan mengikuti contoh admin (10 Okt 2026): satu kelompok baris per karyawan SUBJEK —
       siapa atasan, bawahan, dan rekannya terbaca berurutan — lalu pihak terkaitnya. Kolom
       pendukung (status, masa berlaku, sumber, catatan, peringatan) di ujung kanan. */
    private const KOLOM = [
        ['No', 6],
        ['Kode Subjek', 13], ['Nama Subjek', 28], ['Jabatan Subjek', 26], ['Level Subjek', 16], ['Divisi Subjek', 22], ['Dept Subjek', 22],
        ['Kategori Relasi', 16],
        ['Kode Terkait', 13], ['Nama Terkait', 28], ['Jabatan Terkait', 26], ['Level Terkait', 16], ['Divisi Terkait', 22], ['Dept Terkait', 22],
        ['Status Terkait', 13], ['Berlaku Dari', 13], ['Berlaku Sampai', 14], ['Sumber Data', 16], ['Catatan', 32], ['Peringatan', 30],
    ];

    /** Warna selang-seling per kelompok subjek (biru, hijau). */
    private const WARNA_KELOMPOK = ['DDEBF7', 'E2EFDA'];

    /**
     * @param  array{tipe_ids?:list<int>, cari?:?string, hanya_anomali?:bool, divisi?:?string}  $saringan
     * @return array{nama_berkas:string, isi:string, jumlah:int}
     */
    public function buat(array $saringan, ?string $olehNama = null): array
    {
        $baris = $this->bacaBertahap($saringan);
        $buku = $this->bukuExcel($baris, $saringan, $olehNama);

        $tulis = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($buku);
        $tulis->setPreCalculateFormulas(false);
        ob_start();
        $tulis->save('php://output');
        $isi = (string) ob_get_clean();
        $buku->disconnectWorksheets();

        return [
            'nama_berkas' => $this->namaBerkas(),
            'isi' => $isi,
            'jumlah' => $baris->count(),
        ];
    }

    public function namaBerkas(): string
    {
        return 'Relasi-Organisasi-'.now()->format('Ymd-His').'.xlsx';
    }

    /** Jumlah relasi yang akan diekspor menurut saringan — untuk progres & penolakan dini. */
    public function hitung(array $saringan): int
    {
        return (int) $this->kueri($saringan, $this->tipeIds($saringan))->reorder()->count();
    }

    /**
     * Membaca relasi PER BATCH (keyset pada id, bukan OFFSET) supaya setiap kueri kecil dan
     * memori puncak pembacaan tidak tumbuh bersama jumlah data, lalu mengurutkannya ulang seperti
     * daftar (kategori, nama subjek, nama terkait). `$kemajuan(diproses, total)` dipanggil tiap batch.
     */
    public function bacaBertahap(array $saringan, ?callable $kemajuan = null, int $perBatch = 1000): \Illuminate\Support\Collection
    {
        $tipeIds = $this->tipeIds($saringan);
        $total = $this->hitung($saringan);
        if ($total > self::BATAS_BARIS) {
            throw new RuntimeException('Hasil melebihi '.number_format(self::BATAS_BARIS, 0, ',', '.').' baris. Persempit saringan atau kategori.');
        }

        $hasil = [];
        $terakhir = 0;
        while (true) {
            $batch = $this->kueri($saringan, $tipeIds)->reorder()
                ->where('r.Id_Organization_Relationship', '>', $terakhir)
                ->orderBy('r.Id_Organization_Relationship')
                ->limit($perBatch)
                ->addSelect(['r.Id_Organization_Relationship as id_relasi', 't.Urutan as urutan_kategori'])
                ->get();
            if ($batch->isEmpty()) {
                break;
            }
            array_push($hasil, ...$batch->all());
            $terakhir = (int) $batch->last()->id_relasi;
            if ($kemajuan) {
                $kemajuan(count($hasil), max($total, count($hasil)));
            }
            if ($batch->count() < $perBatch) {
                break;
            }
        }

        // Dikelompokkan per SUBJEK (nama, lalu kode), di dalamnya ATASAN → BAWAHAN → PEER.
        usort($hasil, fn ($a, $b) => [(string) $a->nama_subjek, trim((string) $a->nik_subjek), (int) $a->urutan_kategori, (string) $a->nama_terkait, (int) $a->id_relasi]
            <=> [(string) $b->nama_subjek, trim((string) $b->nik_subjek), (int) $b->urutan_kategori, (string) $b->nama_terkait, (int) $b->id_relasi]);

        return collect($hasil);
    }

    public function bukuExcel(\Illuminate\Support\Collection $baris, array $saringan, ?string $olehNama = null): Spreadsheet
    {
        $tipeIds = $this->tipeIds($saringan);
        $jenis = DB::table('N_HRIS_Organization_Relationship_Type')->where('Flag_Aktif', 'Y')->orderBy('Urutan')
            ->get(['Id_Organization_Relationship_Type as id', 'Kode_Relasi as kode', 'Nama_Relasi as nama']);

        $buku = new Spreadsheet;
        $buku->getProperties()->setCreator('HCIS')->setTitle('Relasi Organisasi');
        $this->sheetRelasi($buku->getActiveSheet(), $baris);
        $this->sheetRingkasan($buku->createSheet(), $baris, $jenis, $saringan, $tipeIds, $olehNama);
        $buku->setActiveSheetIndex(0);

        return $buku;
    }

    /** @return list<int> */
    private function tipeIds(array $saringan): array
    {
        return array_values(array_filter(array_map('intval', $saringan['tipe_ids'] ?? [])));
    }

    private function kueri(array $saringan, array $tipeIds)
    {
        $q = DB::table('N_HRIS_Organization_Relationship as r')
            ->join('N_HRIS_Organization_Relationship_Type as t', 't.Id_Organization_Relationship_Type', '=', 'r.Id_Organization_Relationship_Type')
            ->leftJoin('Karyawan as s', fn ($j) => $j->on('s.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Subjek')->on('s.Kode_Karyawan', '=', 'r.Kode_Karyawan_Subjek'))
            ->leftJoin('Karyawan as o', fn ($j) => $j->on('o.Kode_Perusahaan', '=', 'r.Kode_Perusahaan_Terkait')->on('o.Kode_Karyawan', '=', 'r.Kode_Karyawan_Terkait'))
            // Jabatan & level dari level-jabatan karyawan (Kode_Jabatan di Karyawan umumnya kosong).
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as js', 'js.ID_Level_Jabatan', '=', 's.ID_Level_Jabatan')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as jo', 'jo.ID_Level_Jabatan', '=', 'o.ID_Level_Jabatan')
            ->leftJoin('View_Divisi_Sub_Divisi as ds', 'ds.ID_DIVISI_SUB_DIVISI', '=', 's.ID_Divisi_Sub_Divisi')
            ->leftJoin('View_Divisi_Sub_Divisi as do', 'do.ID_DIVISI_SUB_DIVISI', '=', 'o.ID_Divisi_Sub_Divisi')
            ->where('r.Flag_Aktif', 'Y')
            ->whereNull('r.Cancelled_At');

        if ($tipeIds !== []) {
            $q->whereIn('r.Id_Organization_Relationship_Type', $tipeIds);
        }

        if (($kata = trim((string) ($saringan['cari'] ?? ''))) !== '') {
            $q->where(fn ($w) => $w->where('s.Nama', 'like', "%{$kata}%")->orWhere('o.Nama', 'like', "%{$kata}%")
                ->orWhere('r.Kode_Karyawan_Subjek', 'like', "{$kata}%")->orWhere('r.Kode_Karyawan_Terkait', 'like', "{$kata}%"));
        }

        /* Divisi boleh lebih dari satu (select di modal); string tunggal dari klien lama tetap diterima. */
        $daftarDivisi = $this->daftarDivisi($saringan);
        if ($daftarDivisi !== []) {
            $q->whereIn('ds.nama_divisi', $daftarDivisi);
        }

        /* "Semua karyawan yang cocok saringan" (modal export): rumusnya SAMA dengan pencarian
           kandidat di modal (kandidatQuery), supaya angka "N cocok" yang dilihat admin = subjek
           yang benar-benar diekspor. */
        $saringSubjek = $this->saringanSubjek($saringan);
        if ($saringSubjek !== null) {
            $q->addWhereExistsQuery(app(OrganizationRelationService::class)->kandidatQuery($saringSubjek)
                ->whereColumn('k.Kode_Perusahaan', 'r.Kode_Perusahaan_Subjek')
                ->whereColumn('k.Kode_Karyawan', 'r.Kode_Karyawan_Subjek')
                ->select(DB::raw('1')));
        }

        /* Karyawan yang dipilih (cari ke server di modal), "pt|kode". Disaring pada SUBJEK: setiap
           relasi tercatat dua arah, jadi semua hubungan orang itu tetap ikut lewat baris kebalikannya. */
        $karyawan = $this->daftarKaryawan($saringan);
        if ($karyawan !== []) {
            /* SATU parameter per perusahaan (STRING_SPLIT), bukan dua per orang: "Centang semua yang
               cocok" bisa mengirim ribuan kode, dan SQL Server menolak lebih dari 2.100 parameter —
               958 kondisi OR juga membuat ekspornya ±40 detik. Kode karyawan tidak memuat koma. */
            $perPt = [];
            foreach ($karyawan as [$pt, $kode]) {
                $perPt[$pt][] = str_replace(',', '', $kode);
            }
            $q->where(function ($w) use ($perPt) {
                foreach ($perPt as $pt => $kode) {
                    $w->orWhere(fn ($x) => $x->where('r.Kode_Perusahaan_Subjek', $pt)
                        ->whereRaw("r.Kode_Karyawan_Subjek IN (SELECT LTRIM(RTRIM(value)) FROM STRING_SPLIT(?, ','))", [implode(',', $kode)]));
                }
            });
        }

        if (! empty($saringan['hanya_anomali'])) {
            $q->where(fn ($w) => $w->whereColumn('r.Kode_Karyawan_Subjek', 'r.Kode_Karyawan_Terkait')
                ->orWhereNull('o.Kode_Karyawan')->orWhere('o.Aktif', '<>', 'Y'));
        }

        return $q->orderBy('t.Urutan')->orderBy('s.Nama')->orderBy('o.Nama')->orderBy('r.Id_Organization_Relationship')
            ->select([
                't.Nama_Relasi as kategori', 't.Kode_Relasi as kode_relasi',
                'r.Kode_Perusahaan_Subjek as pt_subjek', 'r.Kode_Karyawan_Subjek as nik_subjek', 's.Nama as nama_subjek',
                'js.nama_jabatan as jabatan_subjek', 'js.nama_level as level_subjek', 'ds.nama_divisi as divisi_subjek', 'ds.nama_sub_divisi as sub_divisi_subjek',
                'r.Kode_Perusahaan_Terkait as pt_terkait', 'r.Kode_Karyawan_Terkait as nik_terkait', 'o.Nama as nama_terkait',
                'jo.nama_jabatan as jabatan_terkait', 'jo.nama_level as level_terkait', 'do.nama_divisi as divisi_terkait', 'do.nama_sub_divisi as sub_divisi_terkait', 'o.Aktif as terkait_aktif',
                'r.Berlaku_Dari as berlaku_dari', 'r.Berlaku_Sampai as berlaku_sampai', 'r.Flag_Utama as utama',
                'r.Sumber_Data as sumber', 'r.Catatan as catatan',
            ]);
    }

    private function sheetRelasi(Worksheet $ws, $baris): void
    {
        $ws->setTitle('Relasi');

        foreach (self::KOLOM as $i => [$judul, $lebar]) {
            $kol = $this->kolom($i + 1);
            $ws->setCellValue("{$kol}1", $judul);
            $ws->getColumnDimension($kol)->setWidth($lebar);
        }

        $akhirKolom = $this->kolom(count(self::KOLOM));
        $ws->getStyle("A1:{$akhirKolom}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getRowDimension(1)->setRowHeight(30);

        /* Diisi per blok (fromArray), bukan sel demi sel dengan gaya per baris: 7.000 relasi
           sebelumnya 37 detik. Hanya baris berperingatan yang diwarnai. */
        $data = [];
        $kuning = [];
        $nik = [];
        $kelompok = []; // [baris awal, baris akhir] per subjek
        $subjekSebelum = null;
        foreach ($baris->values() as $i => $r) {
            $subjek = trim((string) $r->pt_subjek).'|'.trim((string) $r->nik_subjek);
            if ($subjek !== $subjekSebelum) {
                $kelompok[] = [$i + 2, $i + 2];
                $subjekSebelum = $subjek;
            } else {
                $kelompok[count($kelompok) - 1][1] = $i + 2;
            }
            $peringatan = [];
            if ($r->nik_subjek === $r->nik_terkait && $r->pt_subjek === $r->pt_terkait) {
                $peringatan[] = 'Relasi ke diri sendiri';
            }
            if ($r->nama_terkait === null) {
                $peringatan[] = 'Karyawan terkait tidak ditemukan';
            } elseif (($r->terkait_aktif ?? 'T') !== 'Y') {
                $peringatan[] = 'Karyawan terkait nonaktif';
            }
            if ($peringatan !== []) {
                $kuning[] = $i + 2;
            }
            $nik[] = [trim((string) $r->nik_subjek), trim((string) $r->nik_terkait)];

            $dari = $this->tanggal($r->berlaku_dari);
            $sampai = $this->tanggal($r->berlaku_sampai);
            $data[] = [
                $i + 1,
                null, $r->nama_subjek, $r->jabatan_subjek, $r->level_subjek, $r->divisi_subjek, $r->sub_divisi_subjek,
                $r->kode_relasi,
                null, $r->nama_terkait, $r->jabatan_terkait, $r->level_terkait, $r->divisi_terkait, $r->sub_divisi_terkait,
                $r->nama_terkait === null ? '-' : (($r->terkait_aktif ?? 'T') === 'Y' ? 'Aktif' : 'Nonaktif'),
                $dari ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dari) : null,
                $sampai ? \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($sampai) : null,
                $r->sumber, $r->catatan, implode('; ', $peringatan),
                (count($kelompok) - 1) % 2, // kolom bantu tersembunyi: paritas kelompok subjek
            ];
        }

        if ($data !== []) {
            $ws->fromArray($data, null, 'A2', true);
        }

        // NIK sebagai TEKS: Excel memangkas nol di depan dan mengubah kode panjang jadi notasi ilmiah.
        foreach ($nik as $i => [$ns, $nt]) {
            $ws->setCellValueExplicit('B'.($i + 2), $ns, DataType::TYPE_STRING);
            $ws->setCellValueExplicit('I'.($i + 2), $nt, DataType::TYPE_STRING);
        }

        /* Warna selang-seling per kelompok subjek lewat CONDITIONAL FORMATTING atas kolom bantu
           tersembunyi (U = paritas kelompok), bukan gaya per sel: mewarnai rentang membuat
           PhpSpreadsheet menata ±140 ribu sel satu per satu (+8 detik untuk 7.000 relasi).
           Aturannya ikut barisnya bila admin menyaring/mengurutkan di Excel. */
        $akhirData = count($data) + 1;
        if ($data !== []) {
            $aturan = [];
            foreach (self::WARNA_KELOMPOK as $paritas => $warna) {
                $c = new \PhpOffice\PhpSpreadsheet\Style\Conditional;
                $c->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_EXPRESSION)
                    ->addCondition("\$U2={$paritas}");
                $c->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
                $c->getStyle()->getFill()->getEndColor()->setRGB($warna);
                $aturan[] = $c;
            }
            $ws->getStyle("A2:{$akhirKolom}{$akhirData}")->setConditionalStyles($aturan);
            $ws->setCellValue('U1', 'Kelompok');
            $ws->getColumnDimension('U')->setVisible(false);
        }
        // Peringatan cukup ditandai di selnya sendiri, supaya warna kelompok tetap terbaca.
        foreach ($kuning as $b) {
            $ws->getStyle("T{$b}")->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FDE68A']],
                'font' => ['bold' => true, 'color' => ['rgb' => '92400E']],
            ]);
        }

        $n = count($data) + 1;

        $akhirBaris = max(1, $n);
        $ws->getStyle("P2:Q{$akhirBaris}")->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        /* Tanpa garis tepi & perataan per sel: menata ~150 ribu sel memakan ~30 detik. Garis
           tepi header cukup; tabelnya tetap terbaca lewat header beku dan autofilter. */
        $ws->getStyle("A1:{$akhirKolom}1")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('312E81');
        $ws->setAutoFilter("A1:{$akhirKolom}{$akhirBaris}");
        $ws->freezePane('D2');
    }

    /**
     * Lembar "Ringkasan" — dibaca SEBELUM membuka data: apa yang diekspor (saringan), seberapa besar
     * (kartu angka), sebarannya per kategori dan per divisi, dan arti warna di lembar Relasi.
     * Tabel bergaris tepi, tanpa gridlines, siap cetak landscape satu halaman lebar.
     */
    private function sheetRingkasan(Worksheet $ws, $baris, $jenis, array $saringan, array $tipeIds, ?string $olehNama): void
    {
        $ws->setTitle('Ringkasan');
        $ws->setShowGridlines(false);
        foreach (['A' => 36, 'B' => 18, 'C' => 18, 'D' => 18, 'E' => 18, 'F' => 18] as $k => $lebar) {
            $ws->getColumnDimension($k)->setWidth($lebar);
        }
        $ws->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)->setFitToHeight(0);

        $UNGU = '4338CA';
        $kepalaTabel = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $UNGU]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];
        $garis = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]]];
        $judulBagian = function (int $r, string $teks) use ($ws, $UNGU) {
            $ws->setCellValue("A{$r}", $teks);
            $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($UNGU);
            $ws->getStyle("A{$r}:F{$r}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB($UNGU);
        };

        // ---- Kepala laporan
        $ws->mergeCells('A1:F1');
        $ws->setCellValue('A1', 'Ekspor Relasi Organisasi');
        $ws->getStyle('A1:F1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $UNGU]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $ws->getRowDimension(1)->setRowHeight(32);
        $ws->mergeCells('A2:F2');
        $ws->setCellValue('A2', 'Diekspor '.now()->format('d-m-Y H:i').' oleh '.($olehNama ?: '-').' · hanya relasi aktif yang belum dicabut');
        $ws->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('64748B');

        // ---- Kartu angka
        $anomali = $baris->filter(fn ($r) => ($r->nik_subjek === $r->nik_terkait && $r->pt_subjek === $r->pt_terkait)
            || $r->nama_terkait === null || ($r->terkait_aktif ?? 'T') !== 'Y')->count();
        $subjek = $baris->map(fn ($r) => trim((string) $r->pt_subjek).'|'.trim((string) $r->nik_subjek))->unique()->count();
        $terkait = $baris->map(fn ($r) => trim((string) $r->pt_terkait).'|'.trim((string) $r->nik_terkait))->unique()->count();
        $kartu = [
            ['Total relasi', $baris->count(), '#,##0'],
            ['Karyawan subjek', $subjek, '#,##0'],
            ['Karyawan terkait', $terkait, '#,##0'],
            ['Rata-rata relasi / karyawan', $subjek > 0 ? round($baris->count() / $subjek, 1) : 0, '0.0'],
            ['Perlu diperiksa', $anomali, '#,##0'],
        ];
        foreach ($kartu as $i => [$label, $nilai, $format]) {
            $kol = $this->kolom($i + 2); // B..F
            $ws->setCellValue("{$kol}4", $label);
            $ws->setCellValue("{$kol}5", $nilai);
            $ws->getStyle("{$kol}5")->getNumberFormat()->setFormatCode($format);
        }
        $ws->setCellValue('A4', 'Sekilas');
        $ws->getStyle('A4')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB($UNGU);
        $ws->getStyle('B4:F4')->applyFromArray([
            'font' => ['size' => 9, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $ws->getStyle('B5:F5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E1B4B']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2FF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        if ($anomali > 0) {
            $ws->getStyle('F4:F5')->getFill()->getStartColor()->setRGB('FEF3C7');
            $ws->getStyle('F5')->getFont()->getColor()->setRGB('92400E');
        }
        $ws->getStyle('B4:F5')->applyFromArray($garis);
        $ws->getRowDimension(4)->setRowHeight(28);
        $ws->getRowDimension(5)->setRowHeight(30);

        // ---- Saringan
        $r = 7;
        $judulBagian($r, 'Saringan yang dipakai');
        $namaKategori = $tipeIds === [] ? 'Semua kategori' : $jenis->whereIn('id', $tipeIds)->pluck('nama')->implode(', ');
        $info = [
            ['Kategori relasi', $namaKategori],
            ['Karyawan (subjek)', $this->namaKaryawan($this->daftarKaryawan($saringan)) ?: ($this->ringkasSaringanSubjek($saringan) ?: 'Semua karyawan')],
            ['Divisi subjek', implode(', ', $this->daftarDivisi($saringan)) ?: 'Semua divisi'],
            ['Kata kunci', trim((string) ($saringan['cari'] ?? '')) ?: '-'],
            ['Hanya anomali', ! empty($saringan['hanya_anomali']) ? 'Ya' : 'Tidak'],
        ];
        $awal = $r + 1;
        foreach ($info as [$k, $v]) {
            $r++;
            $ws->setCellValue("A{$r}", $k);
            $ws->mergeCells("B{$r}:F{$r}");
            $ws->setCellValue("B{$r}", $v);
        }
        $ws->getStyle("A{$awal}:A{$r}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => '334155']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
        $ws->getStyle("B{$awal}:F{$r}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $ws->getStyle("A{$awal}:F{$r}")->applyFromArray($garis);

        // ---- Per kategori
        $r += 2;
        $judulBagian($r, 'Jumlah per kategori relasi');
        $r++;
        $ws->fromArray([['Kategori relasi', 'Jumlah relasi', 'Karyawan subjek', 'Porsi']], null, "A{$r}");
        $ws->getStyle("A{$r}:D{$r}")->applyFromArray($kepalaTabel);
        $awal = $r + 1;
        $total = max(1, $baris->count());
        // Urutan master kategori (Atasan → Bawahan → Rekan), bukan urutan kemunculan.
        foreach ($baris->sortBy('urutan_kategori')->groupBy('kategori') as $kategori => $isi) {
            $r++;
            $ws->fromArray([[
                $kategori,
                $isi->count(),
                $isi->map(fn ($x) => trim((string) $x->pt_subjek).'|'.trim((string) $x->nik_subjek))->unique()->count(),
                $isi->count() / $total,
            ]], null, "A{$r}", true);
        }
        $akhirData = $r;
        $r++;
        $ws->setCellValue("A{$r}", 'Total');
        $ws->setCellValue("B{$r}", $awal <= $akhirData ? "=SUM(B{$awal}:B{$akhirData})" : 0);
        $ws->setCellValue("C{$r}", $subjek);
        $ws->setCellValue("D{$r}", $awal <= $akhirData ? "=SUM(D{$awal}:D{$akhirData})" : 0);
        $ws->getStyle("A{$r}:D{$r}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
        ]);
        $ws->getStyle("B{$awal}:C{$r}")->getNumberFormat()->setFormatCode('#,##0');
        $ws->getStyle("D{$awal}:D{$r}")->getNumberFormat()->setFormatCode('0.0%');
        $ws->getStyle("B{$awal}:D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $ws->getStyle('A'.($awal - 1).":D{$r}")->applyFromArray($garis);

        // ---- Per divisi subjek
        $r += 2;
        $judulBagian($r, 'Sebaran per divisi subjek');
        $r++;
        $ws->fromArray([['Divisi subjek', 'Karyawan', 'Atasan', 'Bawahan', 'Rekan kerja', 'Total relasi']], null, "A{$r}");
        $ws->getStyle("A{$r}:F{$r}")->applyFromArray($kepalaTabel);
        $awal = $r + 1;
        $perDivisi = $baris->groupBy(fn ($x) => (string) ($x->divisi_subjek ?: '(tanpa divisi)'))->sortKeys();
        foreach ($perDivisi as $divisi => $isi) {
            $r++;
            $hitung = $isi->countBy('kode_relasi');
            $ws->fromArray([[
                $divisi,
                $isi->map(fn ($x) => trim((string) $x->pt_subjek).'|'.trim((string) $x->nik_subjek))->unique()->count(),
                (int) ($hitung['ATASAN'] ?? 0), (int) ($hitung['BAWAHAN'] ?? 0), (int) ($hitung['PEER'] ?? 0),
                $isi->count(),
            ]], null, "A{$r}", true);
            if (($r - $awal) % 2 === 1) {
                $ws->getStyle("A{$r}:F{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8FAFC');
            }
        }
        if ($perDivisi->isNotEmpty()) {
            $akhirData = $r;
            $r++;
            $ws->setCellValue("A{$r}", 'Total');
            $ws->setCellValue("B{$r}", $subjek);
            foreach (['C', 'D', 'E', 'F'] as $k) {
                $ws->setCellValue("{$k}{$r}", "=SUM({$k}{$awal}:{$k}{$akhirData})");
            }
            $ws->getStyle("A{$r}:F{$r}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
            ]);
        }
        $ws->getStyle("B{$awal}:F{$r}")->getNumberFormat()->setFormatCode('#,##0');
        $ws->getStyle('A'.($awal - 1).":F{$r}")->applyFromArray($garis);

        // ---- Keterangan
        $r += 2;
        $judulBagian($r, 'Keterangan lembar Relasi');
        $catatan = [
            ['DDEBF7', 'Biru / hijau selang-seling', 'Satu kelompok warna = satu karyawan subjek beserta semua atasan, bawahan, dan rekannya.'],
            ['E2EFDA', '', ''],
            ['FDE68A', 'Sel Peringatan kuning', 'Relasi perlu diperiksa: ke diri sendiri, karyawan terkait tidak ditemukan, atau sudah nonaktif.'],
        ];
        foreach ($catatan as [$warna, $judul, $teks]) {
            $r++;
            $ws->getStyle("A{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($warna);
            if ($judul !== '') {
                $ws->setCellValue("B{$r}", $judul);
                $ws->getStyle("B{$r}")->getFont()->setBold(true);
                $ws->mergeCells("C{$r}:F{$r}");
                $ws->setCellValue("C{$r}", $teks);
                $ws->getStyle("C{$r}")->getAlignment()->setWrapText(true);
                $ws->getRowDimension($r)->setRowHeight(30);
            }
        }
        $r++;
        $ws->mergeCells("A{$r}:F{$r}");
        $ws->setCellValue("A{$r}", 'Kode karyawan ditulis sebagai teks; tanggal berlaku berformat dd-mm-yyyy. Lembar Relasi bisa disaring dan diurutkan lewat tombol filter di baris judul.');
        $ws->getStyle("A{$r}")->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');
        $ws->getStyle("A{$r}")->getAlignment()->setWrapText(true);
        $ws->getRowDimension($r)->setRowHeight(28);
    }

    private function tanggal($v): ?\DateTimeImmutable
    {
        if ($v === null || $v === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable(substr((string) $v, 0, 10));
        } catch (\Throwable) {
            return null;
        }
    }

    private function kolom(int $i): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
    }

    /** @return list<string> */
    private function daftarDivisi(array $saringan): array
    {
        $d = $saringan['divisi'] ?? [];
        $d = is_array($d) ? $d : [$d];

        return array_values(array_unique(array_filter(array_map(fn ($x) => trim((string) $x), $d), fn ($x) => $x !== '')));
    }

    /** @return list<array{0:string, 1:string}> */
    private function daftarKaryawan(array $saringan): array
    {
        $hasil = [];
        foreach ((array) ($saringan['karyawan'] ?? []) as $k) {
            [$pt, $kode] = array_pad(explode('|', (string) $k, 2), 2, '');
            if (trim($pt) !== '' && trim($kode) !== '') {
                $hasil[trim($pt).'|'.trim($kode)] = [trim($pt), trim($kode)];
            }
        }

        return array_values($hasil);
    }

    private function namaKaryawan(array $karyawan): string
    {
        if ($karyawan === []) {
            return '';
        }
        // Banyak orang (mis. "Centang semua yang cocok"): cukup jumlahnya — daftar ratusan nama tidak
        // terbaca di satu sel, dan kuerinya akan melewati batas 2.100 parameter.
        if (count($karyawan) > 30) {
            return number_format(count($karyawan), 0, ',', '.').' karyawan dipilih';
        }
        $nama = DB::table('Karyawan')->where(function ($w) use ($karyawan) {
            foreach ($karyawan as [$pt, $kode]) {
                $w->orWhere(fn ($x) => $x->where('Kode_Perusahaan', $pt)->where('Kode_Karyawan', $kode));
            }
        })->get(['Kode_Karyawan', 'Nama'])->map(fn ($k) => trim($k->Nama).' ('.trim($k->Kode_Karyawan).')');

        return $nama->implode(', ');
    }

    /** Saringan subjek untuk kandidatQuery, atau null bila tidak ada yang diisi. */
    private function saringanSubjek(array $saringan): ?array
    {
        $daftar = fn ($k) => array_values(array_filter(array_map(fn ($x) => trim((string) $x), (array) ($saringan[$k] ?? [])), fn ($x) => $x !== ''));
        $hasil = [
            'q' => trim((string) ($saringan['subjek_q'] ?? '')),
            'divisi' => $daftar('subjek_divisi'),
            'departemen' => $daftar('subjek_departemen'),
            'id_level' => $daftar('subjek_level'),
        ];

        return ($hasil['q'] === '' && ! $hasil['divisi'] && ! $hasil['departemen'] && ! $hasil['id_level']) ? null : $hasil;
    }

    private function ringkasSaringanSubjek(array $saringan): string
    {
        $s = $this->saringanSubjek($saringan);
        if ($s === null) {
            return '';
        }
        $level = $s['id_level'] ? DB::table('HRIS_Level')->whereIn('ID_Level', $s['id_level'])->pluck('Keterangan')->implode(', ') : '';
        $bagian = array_filter([
            $s['q'] !== '' ? "kata \"{$s['q']}\"" : '',
            $s['divisi'] ? 'divisi '.implode(', ', $s['divisi']) : '',
            $s['departemen'] ? 'departemen '.implode(', ', $s['departemen']) : '',
            $level !== '' ? "level {$level}" : '',
        ]);

        return 'Semua karyawan aktif yang cocok: '.implode('; ', $bagian);
    }
}
