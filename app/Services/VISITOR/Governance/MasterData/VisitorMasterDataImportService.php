<?php

namespace App\Services\VISITOR\Governance\MasterData;

use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Import master data Visitor dari berkas Excel berbentuk templat export.
 *
 * Dua pintu, satu analisis. `pratinjau()` dan `jalankan()` memanggil
 * `analisis()` yang sama: yang membedakan hanya apakah hasilnya ditulis. Dua
 * jalur penilaian yang berbeda akan menyimpang persis di tempat paling mahal —
 * ketika admin sudah menekan Import karena pratinjaunya menjanjikan sesuatu
 * yang lain.
 *
 * Aturan yang dipilih pemilik fitur, dan alasannya ditulis di sini supaya
 * tidak "diperbaiki" diam-diam:
 *
 *   - Baris yang kunci uniknya sudah ada DILEWATI, bukan diperbarui. Import
 *     tidak pernah mengubah baris lama, jadi berkas lama yang tidak sengaja
 *     di-import ulang tidak bisa merusak data yang sedang dipakai transaksi.
 *   - Referensi yang tidak dikenal menolak BARISNYA saja. Satu salah ketik
 *     tidak membatalkan ratusan baris yang benar.
 *   - Kolom Status menyetel status baris baru, TETAPI diterapkan setelah
 *     seluruh baris masuk: induk yang dinonaktifkan lebih awal akan membuat
 *     baris anaknya ditolak oleh penjaga relasi aktif, padahal keduanya datang
 *     dari berkas yang sama.
 */
final class VisitorMasterDataImportService
{
    public function __construct(
        private VisitorMasterDataImportParser $parser,
        private VisitorMasterDataService $data,
    ) {}

    /** Dry run: tidak ada satu pun baris yang ditulis. */
    public function pratinjau(string $domain, string $path, string $namaBerkas): array
    {
        return $this->laporan($domain, $namaBerkas, $this->analisis($domain, $path), null);
    }

    public function jalankan(string $domain, string $path, string $namaBerkas): array
    {
        $analisis = $this->analisis($domain, $path);

        // Satu transaksi untuk seluruh berkas: import yang berhenti separuh
        // jalan meninggalkan master yang tidak bisa dibedakan dari hasil kerja
        // manual, dan tidak ada cara memutar ulang laporannya.
        $ditulis = DB::transaction(fn (): array => $this->tulis($domain, $analisis));

        Log::info('VISITOR_MASTER_IMPORT_DONE', [
            'module' => 'VISITOR',
            'feature' => 'Governance/MasterData',
            'domain' => $domain,
            'berkas' => $namaBerkas,
            'ditambah' => array_sum(array_column($ditulis['lembar'], 'ditambah')),
            'dinonaktifkan' => $ditulis['dinonaktifkan'],
        ]);

        return $this->laporan($domain, $namaBerkas, $analisis, $ditulis);
    }

    public function sidik(string $path): string
    {
        return hash_file('sha256', $path);
    }

    /**
     * Menilai seluruh berkas tanpa menyentuh basis data untuk menulis.
     *
     * Master diproses dalam urutan induk-dulu sehingga nama yang baru lahir di
     * lembar induk sudah dianggap ada ketika lembar anaknya dinilai — kalau
     * tidak, pratinjau menolak baris yang justru akan berhasil saat dijalankan.
     */
    private function analisis(string $domain, string $path): array
    {
        $terbaca = $this->parser->baca($path, $domain);
        $lembar = [];
        $petaRelasi = [];
        $namaBaru = [];

        foreach (VisitorMasterDataFileFormat::masterDomainUrutanImpor($domain) as $master) {
            $definisi = VisitorMasterDataCatalog::get($master);

            foreach ($this->targetRelasi($definisi) as $target) {
                $petaRelasi[$target] ??= $this->petaNamaAktif($target);
            }

            $lembar[$master] = $this->nilaiLembar(
                $master,
                $definisi,
                $terbaca['lembar'][$master] ?? null,
                $petaRelasi,
                $namaBaru,
            );

            $namaBaru[$master] = array_merge(
                $namaBaru[$master] ?? [],
                array_column($lembar[$master]['tambah'], 'nama_relasi'),
            );
        }

        return [
            'domain' => $domain,
            'lembar' => $lembar,
            'diabaikan' => $terbaca['diabaikan'],
            'peta_relasi' => $petaRelasi,
        ];
    }

    /**
     * @param  array<string, array<string, int>>  $petaRelasi
     * @param  array<string, list<string>>  $namaBaru
     */
    private function nilaiLembar(string $master, array $definisi, ?array $lembar, array $petaRelasi, array $namaBaru): array
    {
        $kosong = [
            'master' => $master,
            'sheet' => VisitorMasterDataFileFormat::sheet($master),
            'label' => $definisi['label'],
            'kolom' => [],
            'terbaca' => false,
            'galat' => null,
            'baris' => 0,
            'tambah' => [],
            'lewati' => [],
            'tolak' => [],
        ];

        if ($lembar === null) {
            return $kosong;
        }

        $hasil = $kosong;
        $hasil['terbaca'] = true;
        $hasil['kolom'] = $lembar['kolom'];
        $hasil['galat'] = $lembar['galat'];
        $hasil['baris'] = count($lembar['baris']);

        $kunciTerpakai = $this->kunciBasisData($definisi, $petaRelasi);

        foreach ($lembar['baris'] as $baris) {
            $penilaian = $this->nilaiBaris($master, $definisi, $baris, $petaRelasi, $namaBaru);

            if ($penilaian['jenis'] !== null) {
                $hasil['tolak'][] = [
                    'baris' => $baris['nomor'],
                    'jenis' => $penilaian['jenis'],
                    'pesan' => $penilaian['pesan'],
                ];

                continue;
            }

            $kunci = $penilaian['kunci'];
            if (isset($kunciTerpakai[$kunci])) {
                // Kembar di dalam berkas dan kembar dengan basis data berbeda
                // sebabnya, jadi berbeda pula pesannya: yang satu berkasnya
                // perlu dibersihkan, yang satu justru sudah beres.
                $kembarDiBerkas = $kunciTerpakai[$kunci] === 'berkas';

                $hasil[$kembarDiBerkas ? 'tolak' : 'lewati'][] = $kembarDiBerkas
                    ? [
                        'baris' => $baris['nomor'],
                        'jenis' => 'KEMBAR',
                        'pesan' => '"'.$penilaian['label'].'" muncul lebih dari sekali pada berkas ini.',
                    ]
                    : ['baris' => $baris['nomor'], 'label' => $penilaian['label']];

                continue;
            }

            $kunciTerpakai[$kunci] = 'berkas';
            $hasil['tambah'][] = [
                'baris' => $baris['nomor'],
                'label' => $penilaian['label'],
                'payload' => $penilaian['payload'],
                'relasi' => $penilaian['relasi'],
                'status' => $penilaian['status'],
                'nama_relasi' => $penilaian['nama_relasi'],
            ];
        }

        return $hasil;
    }

    /**
     * @return array{jenis: ?string, pesan: ?string, kunci: ?string, label: string, payload: array<string, mixed>, relasi: array<string, string>, status: string, nama_relasi: string}
     */
    private function nilaiBaris(string $master, array $definisi, array $baris, array $petaRelasi, array $namaBaru): array
    {
        $nilai = $baris['nilai'];
        $payload = [];
        $relasi = [];
        $namaRelasiPerKolom = [];

        foreach (VisitorMasterDataFileFormat::kolom($master) as $kolom) {
            [$kunci, $header] = $kolom;
            $format = $kolom[2] ?? null;
            $teks = $nilai[$kunci] ?? '';

            if ($format === 'status') {
                continue;
            }

            $field = $this->field($definisi, $kunci);
            $hasil = $this->nilaiKolom($field, $header, $format, $teks, $petaRelasi, $namaBaru);

            if ($hasil['pesan'] !== null) {
                return $this->ditolak($hasil['jenis'], $hasil['pesan']);
            }

            if ($format === 'relasi') {
                $namaRelasiPerKolom[$kunci] = $hasil['nama'];
                if ($hasil['nama'] !== '') {
                    $relasi[$kunci] = $hasil['nama'];
                }
                $payload[$kunci] = null;

                continue;
            }

            $payload[$kunci] = $hasil['nilai'];
        }

        $status = $this->status($nilai);
        if ($status === null) {
            return $this->ditolak(
                'NILAI',
                'Kolom Status hanya menerima '.implode(' atau ', VisitorMasterDataFileFormat::pilihan('status')).'.',
            );
        }

        return [
            'jenis' => null,
            'pesan' => null,
            'kunci' => $this->kunciBaris($definisi, $payload, $namaRelasiPerKolom),
            'label' => $this->label($definisi, $payload, $namaRelasiPerKolom),
            'payload' => $payload,
            'relasi' => $relasi,
            'status' => $status,
            'nama_relasi' => $this->namaRelasi($definisi, $payload),
        ];
    }

    /**
     * @return array{jenis: ?string, pesan: ?string, nilai: mixed, nama: string}
     */
    private function nilaiKolom(array $field, string $header, ?string $format, string $teks, array $petaRelasi, array $namaBaru): array
    {
        $lolos = fn (mixed $nilai, string $nama = ''): array => [
            'jenis' => null, 'pesan' => null, 'nilai' => $nilai, 'nama' => $nama,
        ];
        $gagal = fn (string $jenis, string $pesan): array => [
            'jenis' => $jenis, 'pesan' => $pesan, 'nilai' => null, 'nama' => '',
        ];

        if ($format === 'relasi') {
            $nama = $this->namaRelasiBersih($teks);

            if ($nama === '') {
                return $field['required']
                    ? $gagal('WAJIB', 'Kolom '.$header.' wajib diisi.')
                    : $lolos(null);
            }

            $target = $field['relation'];
            $dikenal = array_key_exists(mb_strtolower($nama), $petaRelasi[$target] ?? [])
                || in_array(mb_strtolower($nama), $namaBaru[$target] ?? [], true);

            return $dikenal
                ? $lolos(null, $nama)
                : $gagal('RELASI', $header.' "'.$teks.'" tidak ada pada master '.VisitorMasterDataCatalog::get($target)['label'].' atau sedang nonaktif.');
        }

        if ($teks === '') {
            return $field['required'] && ($field['default'] ?? '') === ''
                ? $gagal('WAJIB', 'Kolom '.$header.' wajib diisi.')
                : $lolos($field['default'] ?? null);
        }

        if ($format !== null) {
            $nilai = VisitorMasterDataFileFormat::dariTeks($format, $teks);

            return $nilai === null
                ? $gagal('NILAI', 'Kolom '.$header.' hanya menerima '.implode(' atau ', VisitorMasterDataFileFormat::pilihan($format)).'.')
                : $lolos($nilai);
        }

        if (($field['type'] ?? '') === 'number') {
            if (! is_numeric($teks)) {
                return $gagal('NILAI', 'Kolom '.$header.' harus berupa angka.');
            }

            $angka = (int) $teks;
            $min = $field['min'] ?? null;
            $max = $field['max'] ?? null;

            return ($min !== null && $angka < $min) || ($max !== null && $angka > $max)
                ? $gagal('NILAI', 'Kolom '.$header.' harus di antara '.$min.' dan '.$max.'.')
                : $lolos($angka);
        }

        $batas = $field['max'] ?? null;

        return $batas !== null && mb_strlen($teks) > $batas
            ? $gagal('NILAI', 'Kolom '.$header.' melebihi '.$batas.' karakter.')
            : $lolos($teks);
    }

    /**
     * Menulis baris yang lolos, dalam urutan induk-dulu.
     *
     * Relasi diikat di sini, bukan saat analisis: induk yang baru lahir pada
     * berkas yang sama belum punya id ketika barisnya dinilai.
     */
    private function tulis(string $domain, array $analisis): array
    {
        $idBaru = [];
        $nonaktif = [];
        $lembar = [];

        foreach (VisitorMasterDataFileFormat::masterDomainUrutanImpor($domain) as $master) {
            $definisi = VisitorMasterDataCatalog::get($master);
            $ditambah = 0;

            foreach ($analisis['lembar'][$master]['tambah'] ?? [] as $baris) {
                $payload = $baris['payload'];

                foreach ($baris['relasi'] as $kolom => $nama) {
                    $payload[$kolom] = $this->idRelasi($definisi, $kolom, $nama, $analisis['peta_relasi'], $idBaru);
                }

                $dibuat = $this->data->create($master, $payload);
                $id = (int) $dibuat[$definisi['id']];
                $ditambah++;

                if ($baris['nama_relasi'] !== '') {
                    $idBaru[$master][$baris['nama_relasi']] = $id;
                }

                if ($baris['status'] === 'T') {
                    $nonaktif[] = [$master, $id];
                }
            }

            $lembar[$master] = ['ditambah' => $ditambah];
        }

        // Status menyusul di akhir: menonaktifkan induk lebih awal akan
        // membuat baris anaknya ditolak penjaga relasi aktif, padahal
        // keduanya datang dari berkas yang sama dan sudah dinyatakan sah.
        foreach ($nonaktif as [$master, $id]) {
            $this->data->changeStatus($master, $id, 'T');
        }

        return ['lembar' => $lembar, 'dinonaktifkan' => count($nonaktif)];
    }

    private function idRelasi(array $definisi, string $kolom, string $nama, array $petaRelasi, array $idBaru): int
    {
        $target = $this->field($definisi, $kolom)['relation'];
        $kunci = mb_strtolower($nama);
        $id = $petaRelasi[$target][$kunci] ?? $idBaru[$target][$kunci] ?? null;

        if ($id === null) {
            // Analisis sudah menyatakan baris ini sah, jadi sampai di sini
            // berarti datanya berubah di tengah jalan. Transaksi dibatalkan
            // seluruhnya; berkasnya masih utuh dan bisa dicoba ulang.
            throw new DomainException('Referensi "'.$nama.'" hilang saat import berjalan. Ulangi pratinjau.');
        }

        return (int) $id;
    }

    /**
     * Laporan untuk layar. Dipakai dua pintu; kolom `ditambah` berisi rencana
     * pada pratinjau dan hasil nyata setelah dijalankan.
     */
    private function laporan(string $domain, string $namaBerkas, array $analisis, ?array $ditulis): array
    {
        $lembar = [];
        foreach ($analisis['lembar'] as $master => $isi) {
            $ditambah = $ditulis === null
                ? count($isi['tambah'])
                : ($ditulis['lembar'][$master]['ditambah'] ?? 0);

            $lembar[] = [
                'master' => $master,
                'sheet' => $isi['sheet'],
                'label' => $isi['label'],
                'terbaca' => $isi['terbaca'],
                'galat' => $isi['galat'],
                'kolom' => $isi['kolom'],
                'baris' => $isi['baris'],
                'ditambah' => $ditambah,
                'dilewati' => count($isi['lewati']),
                'ditolak' => count($isi['tolak']),
                'contoh' => array_slice(array_column($isi['tambah'], 'label'), 0, 5),
                'masalah' => array_slice($isi['tolak'], 0, 50),
                'masalah_lain' => max(0, count($isi['tolak']) - 50),
            ];
        }

        return [
            'nama_berkas' => $namaBerkas,
            'dijalankan' => $ditulis !== null,
            'ringkas' => [
                'baris' => array_sum(array_column($lembar, 'baris')),
                'ditambah' => array_sum(array_column($lembar, 'ditambah')),
                'dilewati' => array_sum(array_column($lembar, 'dilewati')),
                'ditolak' => array_sum(array_column($lembar, 'ditolak')),
                'dinonaktifkan' => $ditulis['dinonaktifkan'] ?? $this->rencanaNonaktif($analisis),
            ],
            'lembar' => $lembar,
            'lembar_diabaikan' => $analisis['diabaikan'],
            'catatan' => $this->catatan($domain, $analisis),
        ];
    }

    private function rencanaNonaktif(array $analisis): int
    {
        $jumlah = 0;
        foreach ($analisis['lembar'] as $isi) {
            $jumlah += count(array_filter($isi['tambah'], static fn (array $baris): bool => $baris['status'] === 'T'));
        }

        return $jumlah;
    }

    /**
     * Kolom yang tidak ada di berkas diisi nilai bawaan. Itu keputusan yang
     * paling mudah mengejutkan pemakainya — tipe kendaraan baru yang diam-diam
     * berlaku untuk flow Tamu juga — jadi disebut di laporan, bukan disimpan
     * sendiri oleh kode.
     *
     * @return list<string>
     */
    private function catatan(string $domain, array $analisis): array
    {
        $catatan = [];

        foreach (VisitorMasterDataFileFormat::masterDomainUrutanImpor($domain) as $master) {
            if (($analisis['lembar'][$master]['tambah'] ?? []) === []) {
                continue;
            }

            $definisi = VisitorMasterDataCatalog::get($master);
            $diBerkas = array_column(VisitorMasterDataFileFormat::kolom($master), 0);

            foreach ($definisi['fields'] as $field) {
                if (in_array($field['key'], $diBerkas, true)) {
                    continue;
                }

                $bawaan = $field['default'] ?? '';
                if ($bawaan === '' || $bawaan === null) {
                    continue;
                }

                $catatan[] = $definisi['label'].': kolom '.$field['label'].' tidak ada di berkas, diisi "'
                    .($field['options'][$bawaan] ?? $bawaan).'".';
            }
        }

        return $catatan;
    }

    /** @return array<string, int> nama (huruf kecil) -> id, hanya baris aktif. */
    private function petaNamaAktif(string $master): array
    {
        $definisi = VisitorMasterDataCatalog::get($master);
        $kolomNama = $definisi['fields'][0]['key'];
        $query = DB::table($definisi['table'])->select($definisi['id'], $kolomNama);

        if ($definisi['status']) {
            $query->where($definisi['status'], 'Y');
        }

        $peta = [];
        foreach ($query->get() as $row) {
            $peta[mb_strtolower(trim((string) $row->{$kolomNama}))] = (int) $row->{$definisi['id']};
        }

        return $peta;
    }

    /**
     * Kunci unik seluruh baris yang sudah ada, dibentuk dari NAMA relasinya —
     * bukan id — supaya sebanding dengan kunci yang dibaca dari berkas.
     *
     * @return array<string, string>
     */
    private function kunciBasisData(array $definisi, array $petaRelasi): array
    {
        $namaPerId = [];
        foreach ($this->targetRelasi($definisi) as $target) {
            $namaPerId[$target] = array_flip($petaRelasi[$target] ?? []);
            // Relasi nonaktif tidak ada di peta aktif, tapi barisnya tetap ada
            // di tabel: tanpa ini kunci uniknya berubah bentuk dan baris lama
            // terbaca sebagai baris baru.
            $namaPerId[$target] += $this->namaSeluruh($target);
        }

        $kunci = [];
        foreach (DB::table($definisi['table'])->get() as $row) {
            $baris = (array) $row;
            $bagian = [];

            foreach ($definisi['unique_by'] as $kolom) {
                $field = $this->field($definisi, $kolom);
                $bagian[] = ($field['type'] ?? '') === 'relation'
                    ? mb_strtolower((string) ($namaPerId[$field['relation']][(int) ($baris[$kolom] ?? 0)] ?? ''))
                    : mb_strtolower(trim((string) ($baris[$kolom] ?? '')));
            }

            $kunci[implode('|', $bagian)] = 'basis_data';
        }

        return $kunci;
    }

    /** @return array<int, string> id -> nama, tanpa memedulikan status. */
    private function namaSeluruh(string $master): array
    {
        $definisi = VisitorMasterDataCatalog::get($master);
        $kolomNama = $definisi['fields'][0]['key'];

        $peta = [];
        foreach (DB::table($definisi['table'])->select($definisi['id'], $kolomNama)->get() as $row) {
            $peta[(int) $row->{$definisi['id']}] = (string) $row->{$kolomNama};
        }

        return $peta;
    }

    private function kunciBaris(array $definisi, array $payload, array $namaRelasi): string
    {
        $bagian = [];
        foreach ($definisi['unique_by'] as $kolom) {
            $bagian[] = array_key_exists($kolom, $namaRelasi)
                ? mb_strtolower($namaRelasi[$kolom])
                : mb_strtolower(trim((string) ($payload[$kolom] ?? '')));
        }

        return implode('|', $bagian);
    }

    private function label(array $definisi, array $payload, array $namaRelasi): string
    {
        $bagian = [];
        foreach ($definisi['unique_by'] as $kolom) {
            $nilai = array_key_exists($kolom, $namaRelasi)
                ? ($namaRelasi[$kolom] ?: VisitorMasterDataFileFormat::RELASI_SEMUA)
                : (string) ($payload[$kolom] ?? '');

            if (trim($nilai) !== '') {
                $bagian[] = trim($nilai);
            }
        }

        return implode(' · ', $bagian);
    }

    /** Nama yang dipakai master LAIN untuk merujuk baris ini. */
    private function namaRelasi(array $definisi, array $payload): string
    {
        return mb_strtolower(trim((string) ($payload[$definisi['fields'][0]['key']] ?? '')));
    }

    private function status(array $nilai): ?string
    {
        $teks = trim((string) ($nilai['Flag_Aktif'] ?? ''));

        if ($teks === '') {
            return 'Y';
        }

        return VisitorMasterDataFileFormat::dariTeks('status', $teks);
    }

    private function namaRelasiBersih(string $teks): string
    {
        $bersih = trim($teks);

        return mb_strtolower($bersih) === mb_strtolower(VisitorMasterDataFileFormat::RELASI_SEMUA) ? '' : $bersih;
    }

    /** @return list<string> */
    private function targetRelasi(array $definisi): array
    {
        return array_values(array_unique(array_column(
            array_filter($definisi['fields'], static fn (array $field): bool => $field['type'] === 'relation'),
            'relation',
        )));
    }

    private function field(array $definisi, string $kunci): array
    {
        foreach ($definisi['fields'] as $field) {
            if ($field['key'] === $kunci) {
                return $field;
            }
        }

        throw new DomainException('Kolom '.$kunci.' tidak dikenal pada master ini.');
    }

    /**
     * @return array{jenis: string, pesan: string, kunci: ?string, label: string, payload: array<string, mixed>, relasi: array<string, string>, status: string, nama_relasi: string}
     */
    private function ditolak(string $jenis, string $pesan): array
    {
        return [
            'jenis' => $jenis,
            'pesan' => $pesan,
            'kunci' => null,
            'label' => '',
            'payload' => [],
            'relasi' => [],
            'status' => 'Y',
            'nama_relasi' => '',
        ];
    }
}
