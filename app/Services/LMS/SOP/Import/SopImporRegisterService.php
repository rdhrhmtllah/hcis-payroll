<?php

namespace App\Services\LMS\SOP\Import;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\Support\SopLog;
use App\Services\LMS\SOP\Support\SopNomorDokumen;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\LMS\SOP\Support\SopTipeItem;
use App\Services\LMS\SOP\Support\SopTipeItemAktif;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Impor register dokumen terkendali dari Excel: identitas dokumen + versinya.
 *
 * BERKAS TIDAK IKUT. Register hanya mencatat identitas dan tanggal; berkasnya
 * dilampirkan belakangan lewat Master Dokumen. Karena itu versi hasil impor
 * berhenti di tahap PENGESAHAN (APPROVAL): Pemberkasan menuntut realisasi
 * pengesah dan Filing & Registered menuntut berkas, dan register tak punya
 * keduanya. Gerbang itu DITEMPUH lewat SopTahapVersiService::pindah(), bukan
 * dilompati dengan menulis Tahap_Id langsung — ketentuan ditahan, bukan diakali.
 *
 * DRY RUN dan JALANKAN memakai SATU rencana. Jalankan membaca ulang berkasnya
 * dan menyusun rencana dari nol, lalu hanya menulis baris yang berstatus SIAP.
 * Rencana yang disimpan di antara dua permintaan bisa basi; yang dibaca ulang
 * tidak.
 *
 * Tiap dokumen ditulis dalam transaksinya sendiri. Satu baris gagal tak
 * membatalkan baris lain, dan menjalankan ulang berkas yang sama aman: dokumen
 * yang sudah ada berstatus SUDAH_ADA dan dilewati.
 */
class SopImporRegisterService
{
    public const SIAP = 'SIAP';

    public const SUDAH_ADA = 'SUDAH_ADA';

    public const GALAT = 'GALAT';

    /** Tahap akhir hasil impor, ditempuh berurutan dari DRAFTING. */
    private const JALUR_TAHAP = ['REVIEW', 'APPROVAL'];

    /**
     * Kolom yang DIBACA. Kunci = nama internal, nilai = judul kolom di Excel
     * setelah dinormalkan (huruf kecil, tanpa titik, spasi tunggal).
     */
    private const KOLOM = [
        'jenis' => 'jenis dokumen',
        'departemen' => 'bagian pemilik dokumen',
        'lingkup' => 'ruang lingkup dokumen',
        'apqc' => 'no process apqc',
        'nomor' => 'no dokumen',
        'revisi' => 'revisi',
        'nomor_lengkap' => 'no dokumen lengkap',
        'judul' => 'judul dokumen',
        'status' => 'status',
        'tgl_permintaan' => 'tanggal permintaan',
        'tgl_respon' => 'tanggal respon',
        'tgl_akhir_approval' => 'tanggal akhir approval',
        'tgl_berlaku' => 'tanggal berlaku',
        'tgl_obsolete' => 'tanggal obsolate',
    ];

    /**
     * Kolom register yang BELUM punya tempat di skema SOP. Tidak diam-diam
     * dibuang: dry run menyebut berapa baris yang isinya tak ikut tersimpan.
     */
    private const KOLOM_TAK_DISIMPAN = [
        'pic' => 'PIC',
        'tanggal filing dokumen softcopy' => 'Tanggal Filing Dokumen Softcopy',
        'tanggal filing dokumen fisik' => 'Tanggal Filing Dokumen Fisik',
        'tanggal sosialisasi' => 'Tanggal Sosialisasi',
        'cara sosialisasi' => 'Cara Sosialisasi',
        'tanggal review 1' => 'Tanggal Review 1',
        'tanggal review 2' => 'Tanggal Review 2',
        'tanggal review 3' => 'Tanggal Review 3',
        'hasil review' => 'Hasil Review',
        'tanggal dokumen kontrol diserahkan' => 'Tanggal Dokumen Kontrol Diserahkan',
        'tanggal dokumen obsolate diambil' => 'Tanggal Dokumen Obsolate Diambil',
    ];

    /**
     * Kolom yang BOLEH tak ada. Register tiap entitas tak seragam: register
     * Holding-Group tak punya Tanggal Respon maupun Tanggal Obsolate. Kolom yang
     * absen dibaca sebagai kosong — bukan galat — karena memang tak dicatat,
     * sedangkan kolom identitas tetap wajib: tanpa itu nomor tak bisa dirakit.
     */
    private const KOLOM_OPSIONAL = [
        'nomor_lengkap', 'status', 'tgl_permintaan', 'tgl_respon', 'tgl_akhir_approval', 'tgl_berlaku', 'tgl_obsolete',
    ];

    /** Kolom tanggal versi yang dicap otomatis saat memasuki tahap di JALUR_TAHAP. */
    private const TANGGAL_TERCAP = ['Tanggal_Permintaan', 'Tanggal_Akhir_Approval'];

    public function __construct(
        private SopDokumenService $dokumen,
        private SopTahapVersiService $tahap,
    ) {}

    /**
     * Susun rencana tanpa menulis apa pun.
     *
     * @return array{ringkasan: array, baris: array<int, array>}
     */
    public function dryRun(string $path): array
    {
        return $this->tanpaIdInternal($this->rencana($path));
    }

    /**
     * Tulis baris SIAP. Baris lain dilaporkan apa adanya.
     *
     * BERTAHAP. Satu dokumen menempuh ±70 kueri lewat gerbang service yang sama
     * dengan layar; dari mesin yang jauh dari basis data itu beberapa detik per
     * dokumen, dan register ratusan baris melampaui batas waktu permintaan web
     * (143 baris = 707 detik dari laptop ke dev). Karena itu pemanggil boleh
     * meminta satu JENDELA baris — $mulai (posisi 0-based dalam urutan berkas)
     * dan $jumlah — lalu mengulang dengan `batch.berikutnya` sampai null.
     *
     * Jendela berdasarkan POSISI di berkas, bukan status: baris yang ditulis
     * batch sebelumnya menjadi SUDAH_ADA pada rencana berikutnya, dan posisinya
     * tak bergeser. Tanpa $jumlah seluruh berkas diproses sekaligus.
     *
     * @return array{ringkasan: array, baris: array<int, array>, batch: array}
     */
    public function jalankan(string $path, string $namaBerkas, int $userId, int $mulai = 0, ?int $jumlah = null): array
    {
        $rencana = $this->rencana($path);
        $total = count($rencana['baris']);
        $mulai = max(0, $mulai);
        $sampai = $jumlah === null ? $total : min($total, $mulai + max(1, $jumlah));
        $rencana['baris'] = array_slice($rencana['baris'], $mulai, max(0, $sampai - $mulai));
        $rencana['batch'] = [
            'mulai' => $mulai,
            'sampai' => $sampai,
            'total' => $total,
            'berikutnya' => $sampai < $total ? $sampai : null,
        ];
        $dibuat = 0;
        $gagal = 0;

        foreach ($rencana['baris'] as &$b) {
            if ($b['status'] !== self::SIAP) {
                continue;
            }

            try {
                $b['hasil'] = DB::transaction(fn () => $this->tulisBaris($b, $namaBerkas, $userId));
                $b['status'] = 'DIBUAT';
                $dibuat++;
            } catch (\Throwable $e) {
                /* DomainException membawa pesan untuk admin; selain itu pesannya
                   tetap diteruskan karena ini alat impor yang dipakai admin
                   pemegang Master Dokumen, dan "gagal" tanpa sebab tak bisa
                   diperbaiki di Excel. */
                $b['status'] = self::GALAT;
                $b['galat'][] = 'Gagal ditulis: '.$e->getMessage();
                $gagal++;
            }
        }
        unset($b);

        $rencana['ringkasan']['dibuat'] = $dibuat;
        $rencana['ringkasan']['gagal_ditulis'] = $gagal;

        return $this->tanpaIdInternal($rencana);
    }

    /** Id master mentah hanya dipakai penulis; tak ikut ke respons. */
    private function tanpaIdInternal(array $rencana): array
    {
        foreach ($rencana['baris'] as &$b) {
            unset($b['_id']);
            if (isset($b['hasil'])) {
                $b['hasil'] = ['versi' => array_column($b['hasil']['versi'], 'versi_ke')];
            }
        }
        unset($b);

        return $rencana;
    }

    /* ── Penulisan ───────────────────────────────────────────────────────── */

    private function tulisBaris(array $b, string $namaBerkas, int $userId): array
    {
        $idDok = $this->dokumen->buat([
            'Judul' => $b['judul'],
            'Tipe' => $b['tipe'],
            'Jenis_Dokumen_Id' => $b['_id']['jenis'],
            'Departemen_Id' => $b['_id']['departemen'],
            'Entitas_Pemilik_Id' => $b['_id']['entitas'],
            'Entitas_Lingkup_Id' => $b['_id']['lingkup'],
            'APQC_Id' => $b['_id']['apqc'],
            'No_Dokumen' => $b['no_dokumen'],
        ], $userId);

        $versi = [];
        foreach ($b['versi'] as $v) {
            $tanggal = $v['tanggal'];

            $idVersi = $this->dokumen->tambahVersi($idDok, $tanggal + [
                'Versi_Ke' => $v['versi_ke'],
                'Ringkasan_Perubahan' => $v['riwayat']
                    ? 'Revisi riwayat dari register Excel ('.$namaBerkas.' baris '.$b['baris'].'); tanggal tidak tercatat di register.'
                    : 'Diimpor dari register Excel ('.$namaBerkas.' baris '.$b['baris'].').',
            ], $userId);

            if (! $idVersi) {
                throw new \DomainException('Versi '.$this->dua($v['versi_ke']).' tak bisa dibuat (nomor bentrok).');
            }

            foreach (self::JALUR_TAHAP as $kode) {
                $this->tahap->pindah($idVersi, $kode, $userId);
            }

            /* Cap otomatis mencatat HARI INI saat versi memasuki Review dan
               Pengesahan. Untuk register lama itu tanggal karangan: yang benar
               adalah isi Excel, termasuk bila Excel mengosongkannya. */
            DB::table('N_SOP_Item_Versi')->where('Id_SOP_Item_Versi', $idVersi)->update(
                array_combine(self::TANGGAL_TERCAP, array_map(
                    fn ($k) => $tanggal[$k] ?? null, self::TANGGAL_TERCAP)),
            );

            $versi[] = ['versi_ke' => $v['versi_ke'], 'id' => $idVersi];
        }

        SopLog::catat(
            'DOK_IMPOR',
            'Dokumen diimpor dari register Excel: '.$b['judul'].' · '.$b['nomor_excel'].'.',
            null, null, null, null,
            [
                'id_dokumen' => $idDok,
                'oleh_id_users' => $userId,
                'berkas' => $namaBerkas,
                'baris' => $b['baris'],
                'versi' => array_column($versi, 'versi_ke'),
            ],
        );

        return ['id_dokumen' => $idDok, 'versi' => $versi];
    }

    /* ── Rencana ─────────────────────────────────────────────────────────── */

    private function rencana(string $path): array
    {
        [$judulKolom, $data] = $this->baca($path);

        $posisi = [];
        $tidakAda = [];
        foreach (self::KOLOM as $kunci => $judul) {
            $i = array_search($judul, $judulKolom, true);
            if ($i === false) {
                if (in_array($kunci, self::KOLOM_OPSIONAL, true)) {
                    $tidakAda[] = $judul;

                    continue;
                }
                throw new \DomainException('Kolom "'.$judul.'" tidak ditemukan di baris judul Excel.');
            }
            $posisi[$kunci] = $i;
        }
        /* Kolom entitas pemilik berjudul KODE ENTITASNYA sendiri ("EMI" di
           register EMI), jadi tak bisa dicari lewat judul. Posisinya yang tetap:
           tepat setelah Jenis Dokumen, mengikuti urutan segmen nomor. */
        $posisi['entitas'] = $posisi['jenis'] + 1;

        $master = $this->master();
        $sudahAda = $this->kunciDokumenAda();
        $diBerkas = [];
        $baris = [];

        foreach ($data as $nomorBaris => $sel) {
            $ambil = fn (string $k) => isset($posisi[$k]) ? ($sel[$posisi[$k]] ?? null) : null;
            if (trim((string) $ambil('judul')) === '') {
                continue;   // baris kosong/penutup tabel
            }
            $baris[] = $this->rencanaBaris($nomorBaris, $ambil, $master, $sudahAda, $diBerkas);
        }

        return [
            'ringkasan' => $this->ringkas($baris, $judulKolom, $data) + [
                // Kolom opsional yang tak ada di berkas: dibaca kosong, disebut di sini.
                'kolom_tidak_ada' => $tidakAda,
            ],
            'baris' => $baris,
        ];
    }

    private function rencanaBaris(int $nomorBaris, callable $ambil, array $master, array $sudahAda, array &$diBerkas): array
    {
        $galat = [];
        $peringatan = [];
        $teks = fn (string $k) => strtoupper(trim((string) $ambil($k)));

        $kode = [
            'jenis' => $teks('jenis'),
            'entitas' => $teks('entitas'),
            'departemen' => $teks('departemen'),
            'lingkup' => $teks('lingkup'),
            'apqc' => $this->angkaDua($ambil('apqc')),
        ];

        $id = [
            'jenis' => $master['jenis'][$kode['jenis']] ?? null,
            'entitas' => $master['pemilik'][$kode['entitas']] ?? null,
            'departemen' => $master['departemen'][$kode['departemen']] ?? null,
            'lingkup' => $master['entitas'][$kode['lingkup']] ?? null,
            'apqc' => $master['apqc'][$kode['apqc']] ?? null,
        ];
        $label = ['jenis' => 'Jenis dokumen', 'entitas' => 'Entitas pemilik', 'departemen' => 'Departemen',
            'lingkup' => 'Ruang lingkup', 'apqc' => 'Proses APQC'];
        foreach ($id as $k => $v) {
            if ($v === null) {
                $galat[] = $label[$k].' "'.($kode[$k] ?? '').'" tidak ada di master (atau nonaktif).';
            }
        }

        $noDokumen = $this->bulat($ambil('nomor'));
        if ($noDokumen === null || $noDokumen < 1 || $noDokumen > 999) {
            $galat[] = 'No. Dokumen "'.$ambil('nomor').'" tidak sah (harus 1–999).';
        }
        $revisi = $this->bulat($ambil('revisi'));
        if ($revisi === null || $revisi < 0 || $revisi > 99) {
            $galat[] = 'Revisi "'.$ambil('revisi').'" tidak sah (harus 0–99).';
        }

        $tipe = null;
        if ($id['jenis'] !== null) {
            $tipe = $this->tipeDariJenis($master['jenis_tipe'][$id['jenis']] ?? []);
            if ($tipe === null) {
                $galat[] = 'Jenis "'.$kode['jenis'].'" tidak punya tipe item yang bisa dipilih otomatis.';
            } elseif (! SopTipeItemAktif::nyala($tipe)) {
                $galat[] = SopTipeItemAktif::pesanMati($tipe);
            } elseif ($id['apqc'] !== null && ! in_array($tipe, $master['apqc_tipe'][$id['apqc']] ?? [], true)) {
                $galat[] = 'Proses APQC '.$kode['apqc'].' tidak berlaku untuk tipe '.$tipe.'.';
            }
        }

        $tanggal = [];
        foreach ([
            'tgl_permintaan' => 'Tanggal_Permintaan',
            'tgl_respon' => 'Tanggal_Respon',
            'tgl_akhir_approval' => 'Tanggal_Akhir_Approval',
            'tgl_berlaku' => 'Tanggal_Berlaku',
        ] as $k => $kolom) {
            [$nilai, $salah] = $this->tanggal($ambil($k));
            if ($salah) {
                $galat[] = ucwords(str_replace(['tgl_', '_'], ['tanggal ', ' '], $k)).' "'.$ambil($k).'" tidak terbaca sebagai tanggal.';
            }
            $tanggal[$kolom] = $nilai;
        }
        if (filled($ambil('tgl_obsolete'))) {
            $galat[] = 'Tanggal Obsolate terisi: versi usang tak bisa diimpor, karena Obsolete hanya dicapai dari Filing & Registered.';
        }
        if ($tanggal['Tanggal_Berlaku'] === null) {
            $peringatan[] = 'Tanggal Berlaku kosong: tanggal kadaluarsa & review berikutnya tidak dihitung.';
        }

        $status = trim((string) $ambil('status'));
        if ($status !== '' && strcasecmp($status, 'Filing and Registered') !== 0) {
            $peringatan[] = 'Status Excel "'.$status.'" — tetap diimpor ke tahap Pengesahan.';
        }

        $nomorExcel = trim((string) $ambil('nomor_lengkap'));
        $nomorSistem = SopNomorDokumen::rakit([
            'jenis' => $kode['jenis'], 'entitas' => $kode['entitas'], 'departemen' => $kode['departemen'],
            'lingkup' => $kode['lingkup'], 'apqc' => $kode['apqc'], 'nomor' => $noDokumen, 'revisi' => $revisi,
        ]);
        if ($nomorSistem !== null && $nomorExcel !== '' && strcasecmp($nomorSistem, $nomorExcel) !== 0) {
            $peringatan[] = 'Nomor lengkap di sistem akan tampil "'.$nomorSistem.'", berbeda dari Excel "'.$nomorExcel.'".';
        }

        $versi = [];
        if ($revisi !== null && $revisi >= 0) {
            for ($k = 0; $k <= $revisi; $k++) {
                $riwayat = $k < $revisi;
                $versi[] = [
                    'versi_ke' => $k,
                    'riwayat' => $riwayat,
                    'tanggal' => $riwayat ? [] : $tanggal,
                ];
            }
            if ($revisi > 0) {
                $peringatan[] = 'Revisi '.$this->dua($revisi).': dibuat juga '.$revisi.' versi riwayat tanpa tanggal, semuanya di tahap Pengesahan.';
            }
        }

        $status = self::SIAP;
        if ($galat === [] && ! in_array(null, $id, true) && $noDokumen !== null) {
            $kunci = implode('|', [$id['jenis'], $id['entitas'], $id['departemen'], $id['lingkup'], $id['apqc'], $noDokumen]);
            if (isset($diBerkas[$kunci])) {
                $galat[] = 'Identitas & No. Dokumen sama dengan baris '.$diBerkas[$kunci].'.';
            } elseif (isset($sudahAda[$kunci])) {
                $status = self::SUDAH_ADA;
            }
            $diBerkas[$kunci] ??= $nomorBaris;
        }
        if ($galat !== []) {
            $status = self::GALAT;
        }

        return [
            'baris' => $nomorBaris,
            'status' => $status,
            'judul' => trim((string) $ambil('judul')),
            'nomor_excel' => $nomorExcel,
            'nomor_sistem' => $nomorSistem,
            'tipe' => $tipe,
            'no_dokumen' => $noDokumen,
            'revisi' => $revisi,
            'versi' => $versi,
            'galat' => $galat,
            'peringatan' => $peringatan,
            '_id' => $id,
        ];
    }

    private function ringkas(array $baris, array $judulKolom, array $data): array
    {
        $hitung = fn (string $s) => count(array_filter($baris, fn ($b) => $b['status'] === $s));

        $takDisimpan = [];
        foreach ($judulKolom as $i => $judul) {
            if (! isset(self::KOLOM_TAK_DISIMPAN[$judul])) {
                continue;
            }
            $terisi = count(array_filter($data, fn ($sel) => filled($sel[$i] ?? null)));
            if ($terisi > 0) {
                $nama = self::KOLOM_TAK_DISIMPAN[$judul];
                $takDisimpan[$nama] = ($takDisimpan[$nama] ?? 0) + $terisi;
            }
        }

        return [
            'total' => count($baris),
            'siap' => $hitung(self::SIAP),
            'sudah_ada' => $hitung(self::SUDAH_ADA),
            'galat' => $hitung(self::GALAT),
            'versi_akan_dibuat' => array_sum(array_map(
                fn ($b) => $b['status'] === self::SIAP ? count($b['versi']) : 0, $baris)),
            'tahap_akhir' => 'APPROVAL',
            'kolom_tak_disimpan' => $takDisimpan,
        ];
    }

    /* ── Bacaan & master ─────────────────────────────────────────────────── */

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, mixed>>}
     *                                                                        judul kolom ternormal, dan baris data berkunci nomor baris Excel
     */
    private function baca(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $sheet = $reader->load($path)->getSheet(0);

        $judul = null;
        $data = [];
        foreach ($sheet->getRowIterator() as $row) {
            $sel = [];
            foreach ($row->getCellIterator() as $cell) {
                /* Register menyusun No. Dokumen Lengkap lewat CONCATENATE. Yang
                   dibandingkan adalah HASILNYA — nilai tersimpan Excel dulu,
                   dihitung ulang hanya bila Excel tak menyimpannya. */
                $sel[] = $cell->isFormula()
                    ? ($cell->getOldCalculatedValue() ?? $cell->getCalculatedValue())
                    : $cell->getValue();
            }

            if ($judul === null) {
                $normal = array_map(fn ($v) => $this->normalJudul($v), $sel);
                if (in_array(self::KOLOM['judul'], $normal, true)) {
                    $judul = $normal;
                }

                continue;
            }

            $data[$row->getRowIndex()] = $sel;
        }

        if ($judul === null) {
            throw new \DomainException('Baris judul kolom tidak ditemukan (dicari kolom "Judul Dokumen").');
        }

        return [$judul, $data];
    }

    private function master(): array
    {
        $aktif = SopStatus::AKTIF;
        $entitas = DB::table('N_HRIS_Master_Entitas')->where('Flag_Aktif', $aktif)
            ->get(['Id_Entitas', 'Kode_Entitas', 'Kode_Sebagai_Pemilik']);

        $jenis = DB::table('N_SOP_Master_Jenis_Dokumen')->where('Flag_Aktif', $aktif)
            ->whereNotNull('Kode_Jenis_Dokumen')->pluck('Id_SOP_Jenis_Dokumen', 'Kode_Jenis_Dokumen');
        $apqc = DB::table('N_SOP_Master_APQC')->where('Flag_Aktif', $aktif)
            ->pluck('Id_SOP_APQC', 'Kode_Proses');

        return [
            'jenis' => $this->petaKode($jenis),
            'apqc' => $this->petaKode($apqc),
            'entitas' => $this->petaKode($entitas->pluck('Id_Entitas', 'Kode_Entitas')),
            /* PEMILIK dicocokkan lewat kode CETAKnya (EntitasService::kodePemilik),
               bukan Kode_Entitas: Holding bernama EHG tetapi tercetak HOL di nomor
               resmi, dan register menulis yang tercetak. Ruang lingkup tetap
               memakai Kode_Entitas — sama dengan SopDokumenService::segmenNomor(). */
            'pemilik' => $this->petaKode($entitas->mapWithKeys(
                fn ($e) => [EntitasService::kodePemilik($e) => $e->Id_Entitas])),
            'departemen' => $this->petaKode(DB::table('N_HRIS_Master_Departemen')->where('Flag_Aktif', $aktif)
                ->pluck('Id_Departemen', 'Kode_Departemen')),
            'jenis_tipe' => DB::table('N_SOP_Master_Jenis_Dokumen_Tipe_Item')->where('Flag_Aktif', $aktif)
                ->get(['Jenis_Dokumen_Id', 'Tipe_Item'])
                ->groupBy('Jenis_Dokumen_Id')->map(fn ($g) => $g->pluck('Tipe_Item')->all())->all(),
            'apqc_tipe' => DB::table('N_SOP_Master_APQC_Tipe_Item')->where('Flag_Aktif', $aktif)
                ->get(['APQC_Id', 'Tipe_Item'])
                ->groupBy('APQC_Id')->map(fn ($g) => $g->pluck('Tipe_Item')->all())->all(),
        ];
    }

    /** Kunci identitas + No_Dokumen semua dokumen yang sudah ada, termasuk nonaktif. */
    private function kunciDokumenAda(): array
    {
        $kunci = [];
        DB::table('N_SOP_Dokumen')->whereNotNull('No_Dokumen')
            ->get(['Jenis_Dokumen_Id', 'Entitas_Pemilik_Id', 'Departemen_Id', 'Entitas_Lingkup_Id', 'APQC_Id', 'No_Dokumen'])
            ->each(function ($d) use (&$kunci) {
                $kunci[implode('|', [$d->Jenis_Dokumen_Id, $d->Entitas_Pemilik_Id, $d->Departemen_Id,
                    $d->Entitas_Lingkup_Id, $d->APQC_Id, (int) $d->No_Dokumen])] = true;
            });

        return $kunci;
    }

    /**
     * Tipe item dari jenis. Satu tipe sah dipakai apa adanya; bila lebih dari
     * satu, DOKUMEN didahulukan (IK menerima DOKUMEN dan VIDEO — register
     * mencatat dokumen, bukan video).
     */
    private function tipeDariJenis(array $tipeSah): ?string
    {
        if (count($tipeSah) === 1) {
            return $tipeSah[0];
        }

        return in_array(SopTipeItem::DOKUMEN, $tipeSah, true) ? SopTipeItem::DOKUMEN : null;
    }

    private function petaKode($pluck): array
    {
        $peta = [];
        foreach ($pluck as $kode => $id) {
            $peta[strtoupper(trim((string) $kode))] = (int) $id;
        }

        return $peta;
    }

    /* ── Pemformat nilai sel ─────────────────────────────────────────────── */

    private function normalJudul($v): string
    {
        $v = strtolower(str_replace('.', '', trim((string) $v)));

        return preg_replace('/\s+/', ' ', $v);
    }

    /**
     * @return array{0: ?string, 1: bool} tanggal Y-m-d, dan apakah isinya tak terbaca
     */
    private function tanggal($v): array
    {
        if ($v === null || trim((string) $v) === '' || trim((string) $v) === '-') {
            return [null, false];
        }

        if ($v instanceof \DateTimeInterface) {
            return [$v->format('Y-m-d'), false];
        }

        if (is_numeric($v)) {
            return [ExcelDate::excelToDateTimeObject((float) $v)->format('Y-m-d'), false];
        }

        /* Tanggal yang diketik tangan di Excel berbahasa Indonesia ("19-Des-2025"),
           jadi nama bulannya diterjemahkan dulu sebelum dicocokkan. */
        $teks = str_ireplace(
            ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'],
            ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            trim((string) $v));
        $teks = preg_replace_callback('/(?<=^|[\s\-\/.])(Mei|Agu|Agt|Okt|Des)(?=$|[\s\-\/.])/i',
            fn ($m) => ['mei' => 'May', 'agu' => 'Aug', 'agt' => 'Aug', 'okt' => 'Oct', 'des' => 'Dec'][strtolower($m[1])],
            $teks);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd.m.Y', 'd M Y', 'd-M-Y', 'd/M/Y', 'd-M-y'] as $format) {
            try {
                $t = CarbonImmutable::createFromFormat('!'.$format, $teks);
                if ($t !== false && strcasecmp($t->format($format), $teks) === 0) {
                    return [$t->format('Y-m-d'), false];
                }
            } catch (\Throwable) {
                // coba format berikutnya
            }
        }

        return [null, true];
    }

    private function bulat($v): ?int
    {
        $v = trim((string) ($v ?? ''));

        return $v !== '' && ctype_digit($v) ? (int) $v : null;
    }

    private function angkaDua($v): string
    {
        $n = $this->bulat($v);

        return $n === null ? strtoupper(trim((string) $v)) : $this->dua($n);
    }

    private function dua(int $n): string
    {
        return str_pad((string) $n, 2, '0', STR_PAD_LEFT);
    }
}
