<?php

namespace App\Services\SYSTEM\Organization;

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
 * Usulan relasi ATASAN dari dua alur approval yang sudah berjalan — approval KPI
 * (`KPI_Approval_Flow_Performance`) dan approval HCIS (`HRIS_Approval_Flow`) — dalam bentuk
 * Excel yang ditinjau admin, lalu diimpor kembali ke Relasi Organisasi.
 *
 * DUA TINGKAT ATASAN, DARI GABUNGAN KPI LALU HCIS (keputusan user 14 Okt 2026). Rantai approver
 * urutan 1 dan 2 dari KPI disusul urutan 1 dan 2 dari HCIS, tanpa nama ganda; dua orang pertama
 * menjadi Atasan 1 dan Atasan 2. Contoh: KPI [B], HCIS [B, C] → Atasan 1 = B, Atasan 2 = C.
 * Bila gabungan itu hanya memberi satu orang, Atasan 2 = approver urutan 1 milik Atasan 1
 * (atasannya atasan, KPI dulu lalu HCIS). Sisanya, juga urutan 3 ke atas, hanya keterangan. Asal tiap atasan ditulis di Catatan, karena
 * approver HCIS belum tentu atasan (bisa petugas HC) — admin yang memutuskan sebelum impor.
 */
class OrganizationRelationApprovalService
{
    public const SUMBER = 'APPROVAL_IMPOR';

    public const MAKS_IMPOR = 3000;

    /**
     * Kolom lembar usulan, berurutan: identitas, status, ISIAN ADMIN, lalu keterangan.
     * Impor membaca kolom MENURUT NAMA, jadi urutan boleh digeser. Kode perusahaan sengaja
     * tidak ditampilkan (keputusan user 14 Okt 2026); impor menentukannya dari kode karyawan.
     *
     * kunci => [judul, lebar, isian admin?]
     */
    private const KOLOM = [
        'kode_karyawan' => ['Kode Karyawan', 13, false],
        'nama' => ['Nama', 26, false],
        'jabatan' => ['Jabatan', 24, false],
        'divisi' => ['Divisi', 20, false],
        'level' => ['Level', 14, false],
        'status' => ['Status', 16, false],
        'atasan_dipakai' => ['Atasan 1 (Kode Karyawan)', 14, true],
        'nama_atasan' => ['Nama Atasan 1 (usulan)', 24, false],
        'atasan2_dipakai' => ['Atasan 2 (Kode Karyawan)', 14, true],
        'nama_atasan2' => ['Nama Atasan 2 (usulan)', 24, false],
        'impor' => ['Impor (Y/T)', 10, true],
        'ganti' => ['Ganti Atasan Lama (Y/T)', 13, true],
        'atasan_sekarang' => ['Atasan Sekarang (Relasi Org)', 28, false],
        'atasan_kpi' => ['Approval KPI (urutan)', 30, false],
        'atasan_hcis' => ['Approval HCIS (urutan)', 30, false],
        'lanjutan' => ['Approver Lain', 28, false],
        'catatan' => ['Catatan', 46, false],
    ];

    /* Nama kolom yang dibaca impor. Judul lama (sebelum 14 Okt 2026) tetap dikenali supaya
       berkas yang sudah telanjur diunduh masih bisa diimpor. */
    private const BACA = [
        'kode' => ['kode karyawan'],
        'pt' => ['kode perusahaan'],
        'atasan' => ['atasan 1 (kode karyawan)', 'atasan dipakai (kode karyawan)'],
        'pt_atasan' => ['kode perusahaan atasan'],
        'atasan2' => ['atasan 2 (kode karyawan)', 'atasan 2 dipakai (kode karyawan)'],
        'pt_atasan2' => ['kode perusahaan atasan 2'],
        'impor' => ['impor (y/t)'],
        'ganti' => ['ganti atasan lama (y/t)'],
    ];

    private const WAJIB_IMPOR = ['kode', 'atasan', 'impor', 'ganti'];

    /* ------------------------------------------------------------------ *
     * Usulan                                                              *
     * ------------------------------------------------------------------ */

    /**
     * Satu baris per karyawan aktif yang muncul sebagai pemohon di approval KPI atau HCIS.
     *
     * @return list<array<string, mixed>>
     */
    public function usulan(): array
    {
        $kpi = DB::table('KPI_Approval_Flow_Performance')
            ->where(fn ($w) => $w->whereNull('Status')->orWhere('Status', '<>', 'X'))
            ->get(['Kode_Karyawan_Requester as req', 'Kode_Karyawan_Approver as apr', 'order_flow as urut']);
        $hcis = DB::table('HRIS_Approval_Flow')
            ->where(fn ($w) => $w->whereNull('Status')->orWhere('Status', '<>', 'X'))
            ->get(['Kode_Karyawan_Requester as req', 'Kode_Karyawan as apr', 'order_flow as urut']);

        $bersih = fn ($x) => strtoupper(trim((string) $x));
        $peta = []; // req => ['kpi' => [urut => [apr => true]], 'hcis' => [...], 'lanjut' => [apr => ket]]
        foreach (['kpi' => $kpi, 'hcis' => $hcis] as $asal => $baris) {
            foreach ($baris as $r) {
                $req = $bersih($r->req);
                $apr = $bersih($r->apr);
                if ($req === '' || $apr === '' || $req === $apr) {
                    continue;
                }
                $urut = max(1, (int) $r->urut);
                if ($urut <= 2) {
                    $peta[$req][$asal][$urut][$apr] = true;
                } else {
                    $peta[$req]['lanjut'][$apr] = strtoupper($asal).' urutan '.$urut;
                }
            }
        }

        if ($peta === []) {
            return [];
        }

        $semuaKode = array_keys($peta);
        foreach ($peta as $p) {
            foreach (['kpi', 'hcis'] as $asal) {
                foreach ($p[$asal] ?? [] as $apr) {
                    $semuaKode = array_merge($semuaKode, array_keys($apr));
                }
            }
            $semuaKode = array_merge($semuaKode, array_keys($p['lanjut'] ?? []));
        }
        $karyawan = $this->karyawanMenurutKode(array_values(array_unique($semuaKode)));

        // Atasan yang sudah tercatat (ATASAN aktif), per subjek.
        $tipeAtasan = $this->tipeId('ATASAN');
        $sekarang = [];
        foreach (DB::table('N_HRIS_Organization_Relationship')
            ->where('Id_Organization_Relationship_Type', $tipeAtasan)->where('Flag_Aktif', 'Y')
            ->get(['Kode_Perusahaan_Subjek', 'Kode_Karyawan_Subjek', 'Kode_Perusahaan_Terkait', 'Kode_Karyawan_Terkait']) as $r) {
            $sekarang[$bersih($r->Kode_Perusahaan_Subjek).'|'.$bersih($r->Kode_Karyawan_Subjek)][] = [$bersih($r->Kode_Perusahaan_Terkait), $bersih($r->Kode_Karyawan_Terkait)];
        }

        $label = function (string $kode) use ($karyawan): string {
            $k = $karyawan[$kode] ?? null;

            return $k === null ? "{$kode} (tidak dikenal)" : "{$kode} – {$k->Nama}".($k->Aktif === 'Y' ? '' : ' (tidak aktif)');
        };

        // Approver yang sudah tidak aktif / tidak dikenal tidak dipakai dan tidak ditampilkan.
        $aktif = fn (string $kode): bool => ($karyawan[$kode]->Aktif ?? null) === 'Y';

        $hasil = [];
        foreach ($peta as $req => $p) {
            $k = $karyawan[$req] ?? null;
            if ($k === null || $k->Aktif !== 'Y') {
                continue; // pemohon yang sudah tidak aktif tidak perlu atasan
            }
            $atasanKini = $sekarang[$k->Kode_Perusahaan.'|'.$req] ?? [];
            $kodeKini = array_map(fn ($x) => $x[1], $atasanKini);

            /* Rantai gabungan: KPI urutan 1, KPI urutan 2, HCIS urutan 1, HCIS urutan 2 — tanpa
               nama ganda. Dalam satu urutan, yang sudah tercatat sebagai atasan didahulukan. */
            $rantai = []; // apr => asal
            foreach (['kpi' => 'KPI', 'hcis' => 'HCIS'] as $asal => $namaAsal) {
                foreach ([1, 2] as $urut) {
                    $apr = array_values(array_filter(array_keys($p[$asal][$urut] ?? []), $aktif));
                    usort($apr, fn ($a, $b) => (int) in_array($b, $kodeKini, true) <=> (int) in_array($a, $kodeKini, true));
                    foreach ($apr as $x) {
                        $rantai[$x] ??= "{$namaAsal} urutan {$urut}";
                    }
                }
            }
            $urutanRantai = array_keys($rantai);

            /* Approval karyawan ini hanya memberi satu orang: Atasan 2 = atasannya Atasan 1, yaitu
               approver urutan 1 milik Atasan 1 sendiri (KPI dulu, lalu HCIS). Tanpa ini di data dev
               hanya 15 dari 916 karyawan yang mendapat Atasan 2 (14 Okt 2026). */
            if (count($urutanRantai) === 1) {
                $atas = $urutanRantai[0];
                foreach (['kpi' => 'KPI', 'hcis' => 'HCIS'] as $asal => $namaAsal) {
                    $calon = array_values(array_filter(
                        array_keys($peta[$atas][$asal][1] ?? []),
                        fn ($x) => $aktif($x) && $x !== $req && $x !== $atas,
                    ));
                    if ($calon !== []) {
                        $rantai[$calon[0]] = "approval Atasan 1 ({$namaAsal} urutan 1)";
                        break;
                    }
                }
                $urutanRantai = array_keys($rantai);
            }

            $catatan = [];
            $dipakai = $urutanRantai[0] ?? null;
            $atasan = $dipakai === null ? null : $karyawan[$dipakai];
            $dipakai2 = $urutanRantai[1] ?? null;
            $atasan2 = $dipakai2 === null ? null : $karyawan[$dipakai2];
            $sisa = array_slice($urutanRantai, 2);

            if ($dipakai !== null) {
                array_unshift($catatan, 'Atasan 1 dari '.$rantai[$dipakai].($dipakai2 !== null ? '; Atasan 2 dari '.$rantai[$dipakai2] : '; tidak ada Atasan 2').'.');
            }
            $kpiSemua = array_filter(array_merge(array_keys($p['kpi'][1] ?? []), array_keys($p['kpi'][2] ?? [])), $aktif);
            $hcisSemua = array_filter(array_merge(array_keys($p['hcis'][1] ?? []), array_keys($p['hcis'][2] ?? [])), $aktif);
            if ($kpiSemua && $hcisSemua && ! array_intersect($kpiSemua, $hcisSemua)) {
                $catatan[] = 'HCIS≠KPI: approver KPI dan HCIS berbeda.';
            }

            $usulanKode = array_values(array_filter([$dipakai, $dipakai2]));
            if ($atasan === null) {
                // Semua approver-nya sudah tidak aktif / tidak dikenal: tetap ditampilkan supaya
                // admin tahu siapa yang perlu diisi atasannya secara manual.
                $status = 'TANPA_APPROVER_AKTIF';
                $catatan[] = 'Tidak ada approver yang masih aktif; isi atasan secara manual bila perlu.';
            } elseif (! array_diff($usulanKode, $kodeKini)) {
                $status = 'SAMA';
            } elseif ($kodeKini === []) {
                $status = 'BARU';
            } elseif (! array_diff($kodeKini, $usulanKode)) {
                // Yang tercatat sudah termasuk usulan; tinggal menambah sisanya, tanpa mengganti.
                $status = 'LENGKAPI';
            } else {
                $status = 'BEDA';
                $catatan[] = 'Sudah punya atasan lain di Relasi Org. Isi "Ganti Atasan Lama" = Y untuk menggantinya.';
            }

            $sebut = fn (string $asal) => implode('; ', array_merge(...array_map(
                fn ($urut) => array_map(fn ($x) => $label($x)." ({$urut})", array_filter(array_keys($p[$asal][$urut] ?? []), $aktif)),
                [1, 2],
            )));

            $hasil[] = [
                'kode_perusahaan' => $k->Kode_Perusahaan,
                'kode_karyawan' => $req,
                'nama' => $k->Nama,
                'jabatan' => $k->jabatan,
                'divisi' => $k->divisi,
                'level' => $k->level,
                'atasan_kpi' => $sebut('kpi'),
                'atasan_hcis' => $sebut('hcis'),
                'atasan_sekarang' => implode('; ', array_map(fn ($x) => $label($x[1]), $atasanKini)),
                'lanjutan' => implode('; ', array_merge(
                    array_map(fn ($x) => $label($x).' ('.$rantai[$x].')', $sisa),
                    array_map(fn ($kode) => $label($kode).' ('.$p['lanjut'][$kode].')', array_values(array_filter(array_keys($p['lanjut'] ?? []), $aktif))),
                )),
                'status' => $status,
                'catatan' => implode(' ', $catatan),
                'atasan_dipakai' => $dipakai ?? '',
                'nama_atasan' => $atasan?->Nama ?? '',
                'nama_atasan2' => $atasan2?->Nama ?? '',
                'perusahaan_atasan' => $atasan?->Kode_Perusahaan ?? '',
                'atasan2_dipakai' => $dipakai2 ?? '',
                'perusahaan_atasan2' => $atasan2?->Kode_Perusahaan ?? '',
                'impor' => in_array($status, ['BARU', 'LENGKAPI'], true) ? 'Y' : 'T',
                'ganti' => 'T',
            ];
        }

        usort($hasil, fn ($a, $b) => [self::urutStatus($a['status']), $a['divisi'] ?? '', $a['nama']] <=> [self::urutStatus($b['status']), $b['divisi'] ?? '', $b['nama']]);

        return $hasil;
    }

    private static function urutStatus(string $s): int
    {
        return ['BARU' => 0, 'LENGKAPI' => 1, 'BEDA' => 2, 'TANPA_APPROVER_AKTIF' => 3, 'SAMA' => 4][$s] ?? 9;
    }

    /* ------------------------------------------------------------------ *
     * Ekspor                                                              *
     * ------------------------------------------------------------------ */

    /** @return array{isi:string, nama_berkas:string, jumlah:int} */
    public function ekspor(?string $oleh = null): array
    {
        return $this->tulisBuku($this->usulan(), $oleh);
    }

    /**
     * Lembar usulan — bentuk yang SAMA dengan yang dibaca impor.
     *
     * @return array{isi:string, nama_berkas:string, jumlah:int}
     */
    private function tulisBuku(array $baris, ?string $oleh): array
    {
        $kunci = array_keys(self::KOLOM);
        $huruf = fn (string $k) => Coordinate::stringFromColumnIndex(array_search($k, $kunci, true) + 1);
        $akhirKolom = $huruf(end($kunci));
        $akhir = max(2, count($baris) + 1);

        $buku = new Spreadsheet;
        $ws = $buku->getActiveSheet();
        $ws->setTitle('Usulan Atasan');

        // Kepala: biru untuk bacaan, oranye untuk kolom yang boleh diisi admin.
        foreach (self::KOLOM as $k => [$judul, $lebar, $isian]) {
            $kol = $huruf($k);
            $ws->setCellValue("{$kol}1", $judul);
            $ws->getColumnDimension($kol)->setWidth($lebar);
            $ws->getStyle("{$kol}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $isian ? 'B45309' : '4338CA']],
                'alignment' => ['wrapText' => true, 'vertical' => 'center', 'horizontal' => 'center'],
            ]);
        }
        $ws->getRowDimension(1)->setRowHeight(34);

        // Kode sebagai TEKS: kode seperti "0123" tidak boleh berubah jadi angka.
        foreach (['kode_karyawan', 'atasan_dipakai', 'atasan2_dipakai'] as $k) {
            $ws->getStyle($huruf($k).'2:'.$huruf($k).($akhir + 200))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $r = 2;
        foreach ($baris as $b) {
            foreach ($kunci as $i => $k) {
                $ws->setCellValueExplicit(Coordinate::stringFromColumnIndex($i + 1).$r, (string) ($b[$k] ?? ''), DataType::TYPE_STRING);
            }
            $r++;
        }

        $nadaStatus = ['BARU' => 'DCFCE7', 'LENGKAPI' => 'DBEAFE', 'BEDA' => 'FEF3C7', 'SAMA' => 'F1F5F9', 'TANPA_APPROVER_AKTIF' => 'FEE2E2'];
        foreach ($baris as $i => $b) {
            $ws->getStyle($huruf('status').($i + 2))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($nadaStatus[$b['status']] ?? 'FFFFFF');
        }
        foreach (self::KOLOM as $k => [, , $isian]) {
            if ($isian) {
                $ws->getStyle($huruf($k)."2:{$huruf($k)}{$akhir}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFBEB');
            }
        }
        $ws->getStyle("A2:{$akhirKolom}{$akhir}")->getAlignment()->setVertical('top');
        foreach (['atasan_sekarang', 'atasan_kpi', 'atasan_hcis', 'lanjutan', 'catatan'] as $k) {
            $ws->getStyle($huruf($k)."2:{$huruf($k)}{$akhir}")->getAlignment()->setWrapText(true);
        }
        foreach (['status', 'impor', 'ganti'] as $k) {
            $ws->getStyle($huruf($k)."2:{$huruf($k)}{$akhir}")->getAlignment()->setHorizontal('center');
        }

        foreach (['impor', 'ganti'] as $k) {
            // Objek baru per kolom: getDataValidation() pada rentang kedua menabrak rentang pertama.
            $dv = new DataValidation;
            $ws->setDataValidation($huruf($k).'2:'.$huruf($k).($akhir + 200), $dv);
            $dv->setType(DataValidation::TYPE_LIST)->setErrorStyle(DataValidation::STYLE_STOP)->setAllowBlank(true)
                ->setShowDropDown(true)->setShowErrorMessage(true)->setErrorTitle('Isi Y atau T')->setError('Pilih Y (ya) atau T (tidak).')
                ->setFormula1('"Y,T"');
        }
        $ws->setAutoFilter("A1:{$akhirKolom}{$akhir}");
        $ws->freezePane('C2');

        $this->lembarPetunjuk($buku, $baris, $oleh);
        $buku->setActiveSheetIndex(0);

        $tulis = IOFactory::createWriter($buku, 'Xlsx');
        ob_start();
        $tulis->save('php://output');

        return [
            'isi' => (string) ob_get_clean(),
            'nama_berkas' => 'Usulan-Atasan-dari-Approval-'.now()->format('Ymd-His').'.xlsx',
            'jumlah' => count($baris),
        ];
    }

    private function lembarPetunjuk(Spreadsheet $buku, array $baris, ?string $oleh): void
    {
        $p = $buku->createSheet();
        $p->setTitle('Petunjuk');
        $p->getColumnDimension('A')->setWidth(30);
        $p->getColumnDimension('B')->setWidth(100);
        $kepala = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4338CA']]];

        $p->setCellValue('A1', 'Usulan Atasan dari Approval KPI & HCIS');
        $p->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $p->setCellValue('A2', 'Dibuat '.now()->format('d-m-Y H:i').($oleh ? " oleh {$oleh}" : '').'. Ubah kolom oranye, lalu impor berkas ini lewat tombol "Impor Atasan" di halaman Relasi Organisasi.');

        $cacah = array_count_values(array_column($baris, 'status'));
        $p->fromArray([['Status', 'Arti (jumlah baris)']], null, 'A4');
        $p->getStyle('A4:B4')->applyFromArray($kepala);
        $p->fromArray([
            ['BARU', 'Belum punya atasan di Relasi Org. Impor otomatis Y. ('.($cacah['BARU'] ?? 0).')'],
            ['LENGKAPI', 'Atasan yang tercatat sudah termasuk usulan; tinggal menambah Atasan 2. Impor otomatis Y, tanpa mengganti. ('.($cacah['LENGKAPI'] ?? 0).')'],
            ['BEDA', 'Sudah punya atasan LAIN di Relasi Org. Impor T; isi Impor = Y dan Ganti Atasan Lama = Y bila usulan ini yang benar. ('.($cacah['BEDA'] ?? 0).')'],
            ['SAMA', 'Atasan usulan sudah tercatat. Tidak perlu diimpor. ('.($cacah['SAMA'] ?? 0).')'],
            ['TANPA_APPROVER_AKTIF', 'Semua approver-nya sudah tidak aktif. Isi Atasan 1 secara manual bila ingin diimpor. ('.($cacah['TANPA_APPROVER_AKTIF'] ?? 0).')'],
        ], null, 'A5');

        $p->fromArray([['Kolom isian (oranye)', 'Cara mengisi']], null, 'A11');
        $p->getStyle('A11:B11')->applyFromArray($kepala);
        $p->fromArray([
            ['Atasan 1 (Kode Karyawan)', 'Orang pertama dari gabungan approval KPI urutan 1–2 lalu HCIS urutan 1–2 (hanya yang masih aktif, tanpa nama ganda). Boleh diganti.'],
            ['Atasan 2 (Kode Karyawan)', 'Orang berikutnya dari gabungan yang sama. Boleh diganti atau dikosongkan (hanya satu atasan).'],
            ['Impor (Y/T)', 'Y = baris ini diimpor. T = dilewati.'],
            ['Ganti Atasan Lama (Y/T)', 'Y = atasan lain yang sekarang tercatat (selain Atasan 1 dan 2 di baris ini) DICABUT. T = atasan lama dibiarkan.'],
        ], null, 'A12');
        $p->setCellValue('A17', 'Catatan');
        $p->getStyle('A17')->getFont()->setBold(true);
        $p->fromArray([
            ['', 'Contoh gabungan: approval KPI [B], HCIS [B, C] → Atasan 1 = B, Atasan 2 = C. Asal tiap atasan tertulis di kolom Catatan.'],
            ['', 'Nama Atasan 1/2 adalah nama dari usulan; bila kodenya diganti, nama di kolom itu tidak ikut berubah.'],
            ['', 'Kode perusahaan tidak perlu diisi: impor menentukannya dari kode karyawan yang aktif.'],
            ['', 'Approver yang sudah tidak aktif tidak ditampilkan dan tidak diusulkan.'],
            ['', 'Setiap relasi ATASAN otomatis dibuatkan pasangan BAWAHAN-nya — Atasan 2 juga akan menerima karyawan ini sebagai bawahan.'],
            ['', 'Impor menampilkan pratinjau lebih dulu dan bersifat semua-atau-tidak: satu baris salah, tidak ada yang disimpan.'],
            ['', 'Approver HCIS belum tentu atasan (bisa petugas HC) — periksa Catatan yang menyebut HCIS.'],
        ], null, 'A18');
        $p->getStyle('B5:B25')->getAlignment()->setWrapText(true)->setVertical('top');
    }

    /* ------------------------------------------------------------------ *
     * Impor                                                               *
     * ------------------------------------------------------------------ */

    /**
     * Membaca lembar pertama berkas usulan dan menulis relasi ATASAN (+ pasangan BAWAHAN).
     *
     * MASSAL, bukan lewat `OrganizationRelationService::tambah()` per baris: tiap kueri ke DB
     * ini ±40 ms, dan jalur per baris (±15 kueri) membuat 900 baris makan ±15 menit. Aturannya
     * SAMA dengan `tambah()`/`cabut()`: baris yang persis sama = sudah ada; relasi lain apa pun
     * di antara pasangan itu = berlawanan dan ditolak; pencabutan ikut mencabut kebalikannya.
     *
     * Pratinjau ($jalankan = false) tidak menulis apa pun. Dijalankan: semua-atau-tidak.
     *
     * @return array{dijalankan:bool, dibaca:int, diimpor:int, dibuat:int, sudah_ada:int, diganti:int, dicabut:int, dilewati:int, galat:list<string>}
     */
    public function impor(string $jalurBerkas, bool $jalankan, ?int $olehUserId = null): array
    {
        $lembar = IOFactory::load($jalurBerkas)->getSheet(0)->toArray(null, true, false, false);
        $kepala = array_map(fn ($x) => strtolower(trim((string) $x)), array_shift($lembar) ?? []);
        $posisi = array_flip($kepala);
        $kol = [];
        foreach (self::BACA as $k => $alias) {
            foreach ($alias as $a) {
                if (isset($posisi[$a])) {
                    $kol[$k] = $posisi[$a];
                    break;
                }
            }
        }
        $kurang = array_diff(self::WAJIB_IMPOR, array_keys($kol));
        if ($kurang !== []) {
            throw new RuntimeException('Kolom berikut tidak ditemukan: '.implode(', ', array_map(fn ($k) => self::BACA[$k][0], $kurang)).'. Pakai berkas hasil "Ekspor Usulan dari Approval".');
        }
        // Kolom opsional (perusahaan, Atasan 2) boleh tidak ada.
        $ambil = fn (array $b, string $k) => isset($kol[$k]) ? strtoupper(trim((string) ($b[$kol[$k]] ?? ''))) : '';
        $kunci = fn (string $pt, string $kode) => strtoupper(trim($pt)).'|'.strtoupper(trim($kode));

        $hasil = ['dijalankan' => false, 'dibaca' => 0, 'diimpor' => 0, 'dibuat' => 0, 'sudah_ada' => 0, 'diganti' => 0, 'dicabut' => 0, 'dilewati' => 0, 'galat' => []];
        $galat = function (int $no, string $kode, string $pesan) use (&$hasil) {
            $hasil['galat'][] = "Baris {$no} ({$kode}): {$pesan}";
        };

        /* 1. Baris yang diminta diimpor. */
        $calon = [];
        $dilihat = [];
        foreach ($lembar as $i => $b) {
            $kode = $ambil($b, 'kode');
            if ($kode === '') {
                continue;
            }
            $hasil['dibaca']++;
            $no = $i + 2;
            $impor = $ambil($b, 'impor');
            if ($impor !== 'Y') {
                in_array($impor, ['T', ''], true) ? $hasil['dilewati']++ : $galat($no, $kode, 'kolom Impor harus Y atau T.');

                continue;
            }
            /* Excel mengubah "001" yang diketik ulang menjadi angka 1. Kode perusahaan HRIS selalu
               3 digit, jadi angka murni dikembalikan ke bentuk itu. */
            /* Perusahaan kosong (berkas sejak 14 Okt 2026 tidak memuatnya) = ditentukan dari kode
               karyawan saat dicocokkan. Berkas lama yang masih memuatnya tetap dihormati. */
            $pt = self::kodePt($ambil($b, 'pt'));
            $atasan = $ambil($b, 'atasan');
            $ptAtasan = self::kodePt($ambil($b, 'pt_atasan'));
            $ganti = $ambil($b, 'ganti');
            $atasan2 = $ambil($b, 'atasan2');
            $ptAtasan2 = self::kodePt($ambil($b, 'pt_atasan2'));

            if (isset($dilihat[$kunci($pt, $kode)])) {
                $galat($no, $kode, 'karyawan ini muncul lebih dari sekali dengan Impor = Y.');

                continue;
            }
            $dilihat[$kunci($pt, $kode)] = true;
            if ($atasan === '') {
                $galat($no, $kode, 'Atasan 1 (Kode Karyawan) kosong.');

                continue;
            }
            if (! in_array($ganti, ['Y', 'T', ''], true)) {
                $galat($no, $kode, 'kolom Ganti Atasan Lama harus Y atau T.');

                continue;
            }
            if ($atasan2 !== '' && $atasan2 === $atasan) {
                $galat($no, $kode, 'Atasan 2 sama dengan Atasan 1.');

                continue;
            }
            $calon[] = compact('no', 'pt', 'kode', 'ptAtasan', 'atasan', 'ptAtasan2', 'atasan2') + ['ganti' => $ganti === 'Y'];
        }
        if (count($calon) > self::MAKS_IMPOR) {
            throw new RuntimeException('Maksimal '.self::MAKS_IMPOR.' baris diimpor sekaligus.');
        }

        /* 2. Data pendukung, sekali muat. */
        $kode = array_values(array_unique(array_filter(array_merge(array_column($calon, 'kode'), array_column($calon, 'atasan'), array_column($calon, 'atasan2')))));
        $karyawan = [];
        $perKode = [];
        foreach (array_chunk($kode, 1000) as $p) {
            foreach (DB::table('Karyawan')->whereIn('Kode_Karyawan', $p)->get(['Kode_Perusahaan', 'Kode_Karyawan', 'Nama', 'Aktif']) as $k) {
                $karyawan[$kunci($k->Kode_Perusahaan, $k->Kode_Karyawan)] = $k;
                $perKode[strtoupper(trim((string) $k->Kode_Karyawan))][] = $k;
            }
        }
        /* Satu kode karyawan, perusahaan tidak disebut: yang aktif dipakai. Bila lebih dari satu
           perusahaan punya karyawan aktif dengan kode itu, tidak ditebak — barisnya ditolak. */
        $cari = function (string $pt, string $kodeK) use ($karyawan, $perKode, $kunci): array {
            if ($pt !== '') {
                return [$karyawan[$kunci($pt, $kodeK)] ?? null, null];
            }
            $semua = $perKode[$kodeK] ?? [];
            $aktif = array_values(array_filter($semua, fn ($k) => $k->Aktif === 'Y'));
            if (count($aktif) > 1) {
                return [null, "kode {$kodeK} dimiliki karyawan aktif di lebih dari satu perusahaan; isi kolom Kode Perusahaan."];
            }

            return [$aktif[0] ?? ($semua[0] ?? null), null];
        };
        $jenis = DB::table('N_HRIS_Organization_Relationship_Type')->get(['Id_Organization_Relationship_Type as id', 'Kode_Relasi as kode', 'Nama_Relasi as nama'])->keyBy('id');
        $idAtasan = $this->tipeId('ATASAN');
        $idBawahan = $this->tipeId('BAWAHAN');

        $relasi = []; // kunci orang => list baris relasi aktif yang menyentuh orang itu
        foreach (array_chunk($kode, 1000) as $p) {
            foreach (DB::table('N_HRIS_Organization_Relationship')->where('Flag_Aktif', 'Y')->whereNull('Cancelled_At')
                ->where(fn ($w) => $w->whereIn('Kode_Karyawan_Subjek', $p)->orWhereIn('Kode_Karyawan_Terkait', $p))
                ->get(['Id_Organization_Relationship as id', 'Id_Organization_Relationship_Type as tipe', 'Kode_Perusahaan_Subjek as spt', 'Kode_Karyawan_Subjek as skode', 'Kode_Perusahaan_Terkait as tpt', 'Kode_Karyawan_Terkait as tkode']) as $r) {
                $r->s = $kunci($r->spt, $r->skode);
                $r->t = $kunci($r->tpt, $r->tkode);
                $relasi[$r->s][$r->id] = $r;
                $relasi[$r->t][$r->id] = $r;
            }
        }
        $antara = function (string $a, string $b) use (&$relasi): array {
            return array_values(array_filter($relasi[$a] ?? [], fn ($r) => ($r->s === $a && $r->t === $b) || ($r->s === $b && $r->t === $a)));
        };

        /* 3. Periksa per baris di memori, kumpulkan rencana tulis. */
        $cabut = [];   // id => true (ATASAN lama + kebalikan BAWAHAN-nya)
        $atasanDicabut = [];
        $sisip = [];   // [tipe, subjek, terkait, prioritas]
        $rencana = []; // "S>T" ATASAN baru di berkas ini, untuk menangkap pertentangan antarbaris
        foreach ($calon as $c) {
            [$S, $ragu] = $cari($c['pt'], $c['kode']);
            if ($ragu !== null) {
                $galat($c['no'], $c['kode'], $ragu);

                continue;
            }
            if ($S === null) {
                $galat($c['no'], $c['kode'], "karyawan {$c['kode']} tidak ada di data karyawan.");

                continue;
            }
            if ($S->Aktif !== 'Y') {
                $galat($c['no'], $c['kode'], "{$S->Nama} sudah tidak aktif.");

                continue;
            }
            $s = $kunci($S->Kode_Perusahaan, $S->Kode_Karyawan);

            /* Atasan 1 (Prioritas 1) dan, bila diisi, Atasan 2 (Prioritas 2). Pemeriksaannya sama
               untuk keduanya; satu saja yang salah membuat baris ini ditolak utuh. */
            $sasaran = [['pt' => $c['ptAtasan'], 'kode' => $c['atasan'], 'prioritas' => 1, 'sebut' => 'atasan']];
            if ($c['atasan2'] !== '') {
                $sasaran[] = ['pt' => $c['ptAtasan2'], 'kode' => $c['atasan2'], 'prioritas' => 2, 'sebut' => 'atasan 2'];
            }

            $siap = [];
            foreach ($sasaran as $x) {
                [$T, $ragu] = $cari($x['pt'], $x['kode']);
                if ($ragu !== null) {
                    $galat($c['no'], $c['kode'], "{$x['sebut']}: {$ragu}");
                    $siap = null;
                    break;
                }
                if ($T === null) {
                    $galat($c['no'], $c['kode'], "{$x['sebut']} {$x['kode']} tidak ada di data karyawan.");
                    $siap = null;
                    break;
                }
                if ($T->Aktif !== 'Y') {
                    $galat($c['no'], $c['kode'], "{$x['sebut']} {$T->Nama} sudah tidak aktif.");
                    $siap = null;
                    break;
                }
                $t = $kunci($T->Kode_Perusahaan, $T->Kode_Karyawan);
                if ($s === $t) {
                    $galat($c['no'], $c['kode'], "{$x['sebut']} tidak boleh dirinya sendiri.");
                    $siap = null;
                    break;
                }
                if (isset($rencana[$t.'>'.$s])) {
                    $galat($c['no'], $c['kode'], "{$T->Nama} di baris lain diusulkan sebagai BAWAHAN {$S->Nama}; satu pasang orang tidak bisa saling atasan.");
                    $siap = null;
                    break;
                }

                $pasangan = $antara($s, $t);
                $maju = fn ($r) => $r->s === $s;
                $bentrok = array_filter($pasangan, fn ($r) => ! (($maju($r) && (int) $r->tipe === $idAtasan) || (! $maju($r) && (int) $r->tipe === $idBawahan)));
                if ($bentrok) {
                    $r = reset($bentrok);
                    $galat($c['no'], $c['kode'], sprintf('%s sudah tercatat sebagai %s bagi %s. Cabut relasi itu dulu.', $maju($r) ? $T->Nama : $S->Nama, $jenis[(int) $r->tipe]->nama ?? 'relasi lain', $maju($r) ? $S->Nama : $T->Nama));
                    $siap = null;
                    break;
                }

                $siap[$t] = [
                    'T' => $T,
                    'prioritas' => $x['prioritas'],
                    'sudahAtasan' => (bool) array_filter($pasangan, fn ($r) => $maju($r) && (int) $r->tipe === $idAtasan),
                    'sudahBawahan' => (bool) array_filter($pasangan, fn ($r) => ! $maju($r) && (int) $r->tipe === $idBawahan),
                ];
            }
            if ($siap === null) {
                continue;
            }

            if ($c['ganti']) {
                $lama = array_filter($relasi[$s] ?? [], fn ($r) => $r->s === $s && (int) $r->tipe === $idAtasan && ! isset($siap[$r->t]));
                foreach ($lama as $r) {
                    $cabut[$r->id] = true;
                    $atasanDicabut[$r->id] = true;
                    // Kebalikannya (atasan lama -> BAWAHAN -> S) ikut dicabut, sama dengan cabut().
                    foreach ($relasi[$s] ?? [] as $k) {
                        if ((int) $k->tipe === $idBawahan && $k->s === $r->t && $k->t === $s) {
                            $cabut[$k->id] = true;
                        }
                    }
                }
                if ($lama) {
                    $hasil['diganti']++;
                }
            }

            $hasil['diimpor']++;
            foreach ($siap as $t => $x) {
                $x['sudahAtasan'] ? $hasil['sudah_ada']++ : $hasil['dibuat']++;
                if (! $x['sudahAtasan']) {
                    $sisip[] = [$idAtasan, $S, $x['T'], $x['prioritas']];
                    $rencana[$s.'>'.$t] = true;
                }
                if (! $x['sudahBawahan']) {
                    $sisip[] = [$idBawahan, $x['T'], $S, $x['prioritas']];
                }
            }
        }
        $hasil['dicabut'] = count($atasanDicabut); // jumlah atasan lama; kebalikannya ikut dicabut

        if (! $jalankan || $hasil['galat'] !== []) {
            return $hasil;
        }

        /* 4. Tulis — satu transaksi, berkelompok. */
        $sekarang = now()->format('Y-m-d H:i:s');
        $hariIni = now()->format('Y-m-d');
        DB::transaction(function () use ($cabut, $sisip, $sekarang, $hariIni, $olehUserId) {
            foreach (array_chunk(array_keys($cabut), 1000) as $p) {
                DB::table('N_HRIS_Organization_Relationship')->whereIn('Id_Organization_Relationship', $p)->where('Flag_Aktif', 'Y')->update([
                    'Flag_Aktif' => 'T', 'Cancelled_At' => $sekarang, 'Cancelled_By' => $olehUserId,
                    'Updated_At' => $sekarang, 'Updated_By' => $olehUserId,
                    'Catatan' => 'Diganti lewat impor usulan approval.',
                ]);
            }
            $baris = array_map(fn ($x) => [
                'Id_Organization_Relationship_Type' => $x[0],
                'Kode_Perusahaan_Subjek' => $x[1]->Kode_Perusahaan, 'Kode_Karyawan_Subjek' => $x[1]->Kode_Karyawan,
                'Kode_Perusahaan_Terkait' => $x[2]->Kode_Perusahaan, 'Kode_Karyawan_Terkait' => $x[2]->Kode_Karyawan,
                'Berlaku_Dari' => $hariIni, 'Berlaku_Sampai' => null, 'Flag_Utama' => 'T', 'Prioritas' => $x[3],
                'Sumber_Data' => self::SUMBER, 'Referensi_Sumber' => 'Excel usulan approval KPI/HCIS',
                'Catatan' => 'Impor dari usulan approval', 'Flag_Aktif' => 'Y', 'Created_By' => $olehUserId,
            ], $sisip);
            // 14 kolom -> 140 baris per sisip, di bawah batas 2.100 parameter SQL Server.
            foreach (array_chunk($baris, 140) as $p) {
                DB::table('N_HRIS_Organization_Relationship')->insert($p);
            }
        });
        $hasil['dijalankan'] = true;

        return $hasil;
    }

    /* ------------------------------------------------------------------ */

    private static function kodePt(string $nilai): string
    {
        return ctype_digit($nilai) && strlen($nilai) < 3 ? str_pad($nilai, 3, '0', STR_PAD_LEFT) : $nilai;
    }

    private function tipeId(string $kode): int
    {
        $id = DB::table('N_HRIS_Organization_Relationship_Type')->where('Kode_Relasi', $kode)->where('Flag_Aktif', 'Y')->value('Id_Organization_Relationship_Type');
        if ($id === null) {
            throw new RuntimeException("Jenis relasi {$kode} tidak aktif.");
        }

        return (int) $id;
    }

    /**
     * Data karyawan per KODE. Tabel approval tidak menyimpan kode perusahaan; bila satu kode
     * ada di lebih dari satu perusahaan, yang aktif yang dipakai.
     *
     * @param  list<string>  $kode
     * @return array<string, object>
     */
    private function karyawanMenurutKode(array $kode): array
    {
        $hasil = [];
        foreach (array_chunk($kode, 1000) as $potongan) {
            foreach (DB::table('Karyawan as k')
                ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v', 'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
                ->leftJoin('View_Divisi_Sub_Divisi as d', 'd.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
                ->whereIn('k.Kode_Karyawan', $potongan)
                ->get(['k.Kode_Perusahaan', 'k.Kode_Karyawan', 'k.Nama', 'k.Aktif', 'v.nama_jabatan as jabatan', 'v.nama_level as level', 'd.nama_divisi as divisi']) as $k) {
                $kunci = strtoupper(trim((string) $k->Kode_Karyawan));
                $k->Kode_Perusahaan = trim((string) $k->Kode_Perusahaan);
                if (! isset($hasil[$kunci]) || ($hasil[$kunci]->Aktif !== 'Y' && $k->Aktif === 'Y')) {
                    $hasil[$kunci] = $k;
                }
            }
        }

        return $hasil;
    }
}
