<?php

namespace App\Services\VISITOR\Permit\Nfc;

use App\Services\SYSTEM\Application\Database\PenyisipanBerbatasParameter;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Layanan penyimpanan & sinkronisasi data NFC ID Card ke tabel basis data:
 * - N_EMI_Master_NFC (Master Kartu)
 * - N_EMI_Binding_NFC (Pairing Karyawan & PHL / Mitra)
 *
 * Satu analisis untuk tiga pintu: pratinjau, impor, dan simpan manual sama-sama
 * memanggil klasifikasi(). Selama ketiganya menilai sendiri-sendiri, pratinjau
 * bisa menjanjikan baris yang lalu ditolak (atau dobel) saat disimpan.
 *
 * Identitas orang dibaca dari Kode Karyawan lebih dulu, BUKAN No Mitra: di berkas
 * PHL 15 Sep 2026 No Mitra 03-V227 dipakai dua orang berbeda. Memakai No Mitra
 * sebagai identitas membuat pairing salah satunya ikut dinonaktifkan.
 */
class NfcImportService
{
    public const T_MASTER = 'N_EMI_Master_NFC';

    public const T_BINDING = 'N_EMI_Binding_NFC';

    public const TIPE_KARYAWAN = 'karyawan';

    public const TIPE_PHL = 'phl';

    /** Batas nilai per whereIn; jauh di bawah batas 2.100 parameter SQL Server. */
    private const POTONGAN = 500;

    /**
     * Memvalidasi input manual sebelum disimpan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validasiManual(array $data): array
    {
        $tipe = strtolower(trim((string) ($data['tipe'] ?? '')));
        if (! in_array($tipe, [self::TIPE_KARYAWAN, self::TIPE_PHL], true)) {
            throw new InvalidArgumentException("Tipe pairing wajib dipilih ('karyawan' atau 'phl').");
        }

        $hex = strtoupper(preg_replace('/\s+/', '', (string) ($data['nfc_hex'] ?? '')));
        if ($hex === '') {
            throw new InvalidArgumentException('NFC HEX wajib diisi.');
        }

        $nama = trim((string) ($data['nama'] ?? ''));
        if ($nama === '') {
            throw new InvalidArgumentException('Nama pemegang wajib diisi.');
        }

        $kodeKaryawan = trim((string) ($data['kode_karyawan'] ?? ''));
        $noMitra = trim((string) ($data['no_mitra'] ?? ''));
        if ($tipe === self::TIPE_KARYAWAN && $kodeKaryawan === '') {
            throw new InvalidArgumentException('Kode Karyawan wajib diisi untuk pairing karyawan.');
        }
        if ($tipe === self::TIPE_PHL && $noMitra === '' && $kodeKaryawan === '') {
            throw new InvalidArgumentException('No Mitra atau Kode Karyawan wajib diisi untuk pairing PHL/Mitra.');
        }

        $inbio = isset($data['nfc_inbio']) && $data['nfc_inbio'] !== '' && is_numeric($data['nfc_inbio'])
            ? (int) $data['nfc_inbio']
            : null;

        return [
            'tipe' => $tipe,
            'nfc_hex' => $hex,
            'nfc_inbio' => $inbio,
            'nama' => $nama,
            'kode_karyawan' => $kodeKaryawan !== '' ? $kodeKaryawan : null,
            'nik' => ! empty($data['nik']) ? trim((string) $data['nik']) : null,
            'no_mitra' => $noMitra !== '' ? $noMitra : null,
        ];
    }

    /**
     * Menyimpan pairing kartu manual dari form web.
     *
     * Kartu yang sedang aktif pada orang lain DITOLAK dengan nama pemegangnya:
     * pairing lama wajib dinonaktifkan dulu (keputusan user 13 Okt 2026).
     *
     * @param  array<string, mixed>  $data
     * @return array{imported_master: int, imported_binding: int, updated_binding: int, skipped_konflik: int, skipped_sudah_terdaftar: int, total_processed: int}
     */
    public function simpanManual(array $data, string $actor = 'SYSTEM'): array
    {
        $validated = $this->validasiManual($data);

        $kls = $this->klasifikasi([$validated], $this->muatDataTerkait([$validated]));
        $this->tolakBilaTidakSiap($kls, $validated['nfc_hex']);

        $hasil = $this->import([$validated], $validated['tipe'], $actor);

        // Penilaian di atas berjalan di luar transaksi impor; penilaian ulang di
        // dalamnya yang menentukan. Pairing yang terserobot di antara keduanya
        // tidak boleh dilaporkan sebagai "berhasil disimpan".
        if ($hasil['skipped_konflik'] > 0 || $hasil['skipped_sudah_terdaftar'] > 0) {
            $this->tolakBilaTidakSiap(
                $this->klasifikasi([$validated], $this->muatDataTerkait([$validated])),
                $validated['nfc_hex'],
            );
        }

        return $hasil;
    }

    /**
     * Memproses impor dalam satu transaksi, set-based (bebas N+1).
     *
     * Baris yang identik dengan pairing aktif dilewati (sudah terdaftar); kartu
     * yang aktif pada orang lain dilewati (konflik). Orang yang mendapat kartu
     * baru otomatis kehilangan pairing aktif lamanya.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported_master: int, imported_binding: int, updated_binding: int, skipped_konflik: int, skipped_sudah_terdaftar: int, total_processed: int}
     */
    public function import(array $rows, string $tipe, string $actor = 'SYSTEM'): array
    {
        $tipe = strtolower(trim($tipe));
        if (! in_array($tipe, [self::TIPE_KARYAWAN, self::TIPE_PHL], true)) {
            throw new InvalidArgumentException("Tipe impor tidak valid: {$tipe}. Gunakan 'karyawan' atau 'phl'.");
        }

        $validRows = $this->filterValidRows($rows);
        if (empty($validRows)) {
            return [
                'imported_master' => 0,
                'imported_binding' => 0,
                'updated_binding' => 0,
                'skipped_konflik' => 0,
                'skipped_sudah_terdaftar' => 0,
                'total_processed' => 0,
            ];
        }

        return DB::transaction(function () use ($validRows, $actor) {
            $konteks = $this->muatDataTerkait($validRows);
            $kls = $this->klasifikasi($validRows, $konteks);
            $siap = $kls['siap'];
            $now = now();

            // 1. Master: aktifkan ulang yang terblokir, lengkapi InBio yang kosong,
            //    dan sisipkan kartu baru sekaligus.
            $reaktifMaster = [];
            $isiInbio = [];
            $masterBaru = [];

            foreach ($siap as $row) {
                $master = $this->cariMasterDiKonteks($konteks, $row);
                if ($master) {
                    if ($master->flag_aktif !== 'Y' || $master->flag_validasi_sistem !== 'Y') {
                        $reaktifMaster[] = (int) $master->id;
                    }
                    $inbio = $this->inbioBaris($row);
                    if ($inbio !== null && $master->nfc_inbio === null) {
                        $isiInbio[(int) $master->id] = $inbio;
                    }

                    continue;
                }

                $hex = $this->hexBaris($row);
                if (! isset($masterBaru[$hex])) {
                    $masterBaru[$hex] = $this->formatMasterPayload($hex, $this->inbioBaris($row), $actor) + ['created_at' => $now];
                }
            }

            foreach (array_chunk(array_values(array_unique($reaktifMaster)), self::POTONGAN) as $potongan) {
                DB::table(self::T_MASTER)->whereIn('id', $potongan)
                    ->update(['flag_aktif' => 'Y', 'flag_validasi_sistem' => 'Y']);
            }

            // Jarang terjadi (master lama tanpa InBio), jadi satu UPDATE per kartu.
            foreach ($isiInbio as $idMaster => $inbio) {
                DB::table(self::T_MASTER)->where('id', $idMaster)->whereNull('nfc_inbio')->update(['nfc_inbio' => $inbio]);
            }

            if ($masterBaru !== []) {
                PenyisipanBerbatasParameter::sisipkan(self::T_MASTER, array_values($masterBaru));

                foreach (array_chunk(array_keys($masterBaru), self::POTONGAN) as $potongan) {
                    foreach (DB::table(self::T_MASTER)->whereIn('nfc_hex', $potongan)->get() as $m) {
                        $konteks['master_by_hex'][strtoupper(trim((string) $m->nfc_hex))] = $m;
                    }
                }
            }

            // 2. Binding: pairing lama orangnya dinonaktifkan, pairing yang pernah
            //    ada diaktifkan ulang, sisanya disisipkan sekaligus.
            $nonaktifkan = [];
            $bindingBaru = [];
            $updatedBinding = 0;

            foreach ($siap as $row) {
                $master = $this->cariMasterDiKonteks($konteks, $row);
                if (! $master) {
                    throw new DomainException('Master kartu '.$this->hexBaris($row).' tidak ditemukan setelah disisipkan.');
                }
                $idMaster = (int) $master->id;
                $orang = $this->kunciOrang($row);

                foreach ($konteks['active_binding_by_orang'][$orang] ?? [] as $lama) {
                    if ((int) $lama->id_nfc !== $idMaster) {
                        $nonaktifkan[] = (int) $lama->id;
                    }
                }

                $ada = $konteks['existing_binding_by_kartu_orang'][$idMaster.'|'.$orang] ?? null;
                if ($ada) {
                    DB::table(self::T_BINDING)->where('id', $ada->id)->update([
                        'nik' => $row['nik'] ?? $ada->nik,
                        'nama' => $row['nama'] ?? $ada->nama,
                        'kode_karyawan' => $row['kode_karyawan'] ?? $ada->kode_karyawan,
                        'no_mitra' => $row['no_mitra'] ?? $ada->no_mitra,
                        'flag_aktif' => 'Y',
                    ]);
                    $updatedBinding++;

                    continue;
                }

                $bindingBaru[] = $this->formatBindingPayload($idMaster, $row, $actor) + ['created_at' => $now];
            }

            foreach (array_chunk(array_values(array_unique($nonaktifkan)), self::POTONGAN) as $potongan) {
                DB::table(self::T_BINDING)->whereIn('id', $potongan)->update(['flag_aktif' => 'T']);
            }

            if ($bindingBaru !== []) {
                PenyisipanBerbatasParameter::sisipkan(self::T_BINDING, $bindingBaru);
            }

            return [
                'imported_master' => count($masterBaru),
                'imported_binding' => count($bindingBaru),
                'updated_binding' => $updatedBinding,
                'skipped_konflik' => count($kls['konflik']),
                'skipped_sudah_terdaftar' => count($kls['sudah']),
                'total_processed' => count($validRows),
            ];
        });
    }

    /**
     * Menilai setiap baris terhadap isi basis data DAN baris lain di berkas yang sama.
     *
     * Murni (tanpa kueri) supaya pratinjau, impor, dan simpan manual memakai satu
     * penilaian, dan supaya bisa diuji tanpa basis data.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<mixed>>  $konteks  hasil muatDataTerkait()
     * @return array{siap: array<int, array<string, mixed>>, sudah: array<int, array<string, mixed>>, konflik: array<int, array{row: array<string, mixed>, pemegang_lama: string, alasan: string}>}
     */
    public function klasifikasi(array $rows, array $konteks): array
    {
        $siap = [];
        $sudah = [];
        $konflik = [];
        $pemilikKartu = [];
        $kartuOrang = [];

        foreach ($rows as $row) {
            $master = $this->cariMasterDiKonteks($konteks, $row);
            $kartu = $master ? 'ID:'.$master->id : 'HEX:'.$this->hexBaris($row);
            $orang = $this->kunciOrang($row);

            // Kartu yang sama muncul lagi di berkas yang sama.
            if (isset($pemilikKartu[$kartu])) {
                if ($pemilikKartu[$kartu] === $orang) {
                    $sudah[] = $row;
                } else {
                    $konflik[] = [
                        'row' => $row,
                        'pemegang_lama' => 'baris lain di berkas ini',
                        'alasan' => 'Kartu yang sama tercantum untuk lebih dari satu orang di berkas.',
                    ];
                }

                continue;
            }

            $aktif = $master ? ($konteks['active_binding_by_master_id'][(int) $master->id] ?? null) : null;

            if ($this->apakahIdentik($aktif, $master, $row)) {
                $sudah[] = $row;
                $pemilikKartu[$kartu] = $orang;

                continue;
            }

            if ($aktif && $this->apakahKonflikPemegangLain($aktif, $row)) {
                $konflik[] = [
                    'row' => $row,
                    'pemegang_lama' => (string) ($aktif->nama ?: $aktif->kode_karyawan ?: $aktif->no_mitra ?: 'pemegang lain'),
                    'alasan' => 'Kartu masih aktif pada pemegang lain; nonaktifkan pairing lamanya dulu.',
                ];

                continue;
            }

            // Satu orang hanya boleh punya satu kartu aktif; dua kartu untuk orang
            // yang sama dalam satu berkas tidak bisa diputuskan mana yang berlaku.
            if ($orang !== '' && isset($kartuOrang[$orang])) {
                $konflik[] = [
                    'row' => $row,
                    'pemegang_lama' => (string) ($row['nama'] ?? $orang),
                    'alasan' => 'Orang yang sama tercantum dengan kartu berbeda di berkas ini.',
                ];

                continue;
            }

            $siap[] = $row;
            $pemilikKartu[$kartu] = $orang;
            if ($orang !== '') {
                $kartuOrang[$orang] = $kartu;
            }
        }

        return ['siap' => $siap, 'sudah' => $sudah, 'konflik' => $konflik];
    }

    /**
     * Memuat master dan binding yang relevan dengan baris impor, dalam beberapa
     * kueri ber-whereIn (bukan satu kueri per baris).
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, array<mixed>>
     */
    public function muatDataTerkait(array $rows): array
    {
        $konteks = self::konteksKosong();
        $hexes = [];
        $inbios = [];
        $kodes = [];
        $mitras = [];

        foreach ($rows as $r) {
            $hex = $this->hexBaris($r);
            if ($hex !== '') {
                $hexes[$hex] = $hex;
            }
            $inbio = $this->inbioBaris($r);
            if ($inbio !== null) {
                $inbios[$inbio] = $inbio;
            }
            $kode = trim((string) ($r['kode_karyawan'] ?? ''));
            if ($kode !== '') {
                $kodes[$kode] = $kode;
            }
            $mitra = trim((string) ($r['no_mitra'] ?? ''));
            if ($mitra !== '') {
                $mitras[$mitra] = $mitra;
            }
        }

        $masters = [];
        foreach (array_chunk(array_values($hexes), self::POTONGAN) as $potongan) {
            foreach (DB::table(self::T_MASTER)->whereIn('nfc_hex', $potongan)->get() as $m) {
                $masters[(int) $m->id] = $m;
            }
        }
        foreach (array_chunk(array_values($inbios), self::POTONGAN) as $potongan) {
            foreach (DB::table(self::T_MASTER)->whereIn('nfc_inbio', $potongan)->get() as $m) {
                $masters[(int) $m->id] = $m;
            }
        }

        foreach ($masters as $m) {
            if (! empty($m->nfc_hex)) {
                $konteks['master_by_hex'][strtoupper(trim((string) $m->nfc_hex))] = $m;
            }
            if ($m->nfc_inbio !== null && is_numeric($m->nfc_inbio)) {
                $konteks['master_by_inbio'][(int) $m->nfc_inbio] = $m;
            }
        }

        $bindings = [];
        foreach (array_chunk(array_keys($masters), self::POTONGAN) as $potongan) {
            foreach (DB::table(self::T_BINDING)->whereIn('id_nfc', $potongan)->get() as $b) {
                $bindings[(int) $b->id] = $b;
            }
        }
        foreach (array_chunk(array_values($kodes), self::POTONGAN) as $potongan) {
            foreach (DB::table(self::T_BINDING)->whereIn('kode_karyawan', $potongan)->get() as $b) {
                $bindings[(int) $b->id] = $b;
            }
        }
        foreach (array_chunk(array_values($mitras), self::POTONGAN) as $potongan) {
            foreach (DB::table(self::T_BINDING)->whereIn('no_mitra', $potongan)->get() as $b) {
                $bindings[(int) $b->id] = $b;
            }
        }

        foreach ($bindings as $b) {
            $orang = $this->kunciOrang($b);
            $konteks['existing_binding_by_kartu_orang'][(int) $b->id_nfc.'|'.$orang] = $b;

            if ($b->flag_aktif === 'Y') {
                $konteks['active_binding_by_master_id'][(int) $b->id_nfc] ??= $b;
                $konteks['active_binding_by_orang'][$orang][] = $b;
            }
        }

        return $konteks;
    }

    /** @return array<string, array<mixed>> */
    public static function konteksKosong(): array
    {
        return [
            'master_by_hex' => [],
            'master_by_inbio' => [],
            'active_binding_by_master_id' => [],
            'active_binding_by_orang' => [],
            'existing_binding_by_kartu_orang' => [],
        ];
    }

    /**
     * Identitas pemegang: Kode Karyawan, lalu No Mitra, NIK, dan nama.
     *
     * @param  array<string, mixed>|object  $sumber
     */
    public function kunciOrang(array|object $sumber): string
    {
        $ambil = fn (string $kolom): string => strtoupper(trim((string) (is_array($sumber) ? ($sumber[$kolom] ?? '') : ($sumber->{$kolom} ?? ''))));

        foreach (['kode_karyawan' => 'K:', 'no_mitra' => 'M:', 'nik' => 'N:', 'nama' => 'NAMA:'] as $kolom => $awalan) {
            $nilai = $ambil($kolom);
            if ($nilai !== '') {
                return $awalan.$nilai;
            }
        }

        return '';
    }

    /**
     * Baris baru ditujukan untuk orang lain daripada pemegang binding aktif.
     */
    public function apakahKonflikPemegangLain(object $bindingAktif, array $row): bool
    {
        $lama = $this->kunciOrang($bindingAktif);
        $baru = $this->kunciOrang($row);

        return $lama !== '' && $baru !== '' && $lama !== $baru;
    }

    /**
     * Kartu dan pemegangnya sudah terdaftar identik dan aktif, sehingga tidak
     * ada yang perlu diimpor atau diubah.
     */
    public function apakahIdentik(?object $binding, ?object $master, array $row): bool
    {
        if (! $binding || ! $master) {
            return false;
        }

        if (($binding->flag_aktif ?? 'T') !== 'Y' || ($master->flag_aktif ?? 'T') !== 'Y'
            || ($master->flag_validasi_sistem ?? 'T') !== 'Y') {
            return false;
        }

        $hexLama = strtoupper(trim((string) ($master->nfc_hex ?? '')));
        if ($this->hexBaris($row) === '' || $this->hexBaris($row) !== $hexLama) {
            return false;
        }

        $inbioBaru = $this->inbioBaris($row);
        $inbioLama = isset($master->nfc_inbio) && is_numeric($master->nfc_inbio) ? (int) $master->nfc_inbio : null;
        if ($inbioBaru !== null && $inbioBaru !== $inbioLama) {
            return false;
        }

        return $this->kunciOrang($binding) === $this->kunciOrang($row);
    }

    /**
     * Memfilter baris yang memenuhi syarat minimal impor.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function filterValidRows(array $rows): array
    {
        $valid = [];
        foreach ($rows as $r) {
            $hex = trim((string) ($r['nfc_hex'] ?? ''));
            $kode = trim((string) ($r['kode_karyawan'] ?? ''));
            $nama = trim((string) ($r['nama'] ?? ''));
            $mitra = trim((string) ($r['no_mitra'] ?? ''));

            if ($hex === '' || ($kode === '' && $nama === '' && $mitra === '')) {
                continue;
            }

            $valid[] = $r;
        }

        return $valid;
    }

    /**
     * Menyusun payload N_EMI_Master_NFC.
     * Kolom tipe dan label sengaja dikosongkan (NULL) atas instruksi user.
     *
     * @return array<string, mixed>
     */
    public function formatMasterPayload(string $hex, ?int $inbio, string $actor): array
    {
        return [
            'nfc_hex' => strtoupper(trim($hex)),
            'nfc_inbio' => $inbio,
            'flag_validasi_sistem' => 'Y',
            'flag_aktif' => 'Y',
            'tipe' => null,
            'label' => null,
            'created_by' => $actor,
        ];
    }

    /**
     * Menyusun payload N_EMI_Binding_NFC.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function formatBindingPayload(int $idNfc, array $row, string $actor): array
    {
        return [
            'id_nfc' => $idNfc,
            'nik' => ! empty($row['nik']) ? trim((string) $row['nik']) : null,
            'kode_karyawan' => ! empty($row['kode_karyawan']) ? trim((string) $row['kode_karyawan']) : null,
            'nama' => ! empty($row['nama']) ? trim((string) $row['nama']) : null,
            'no_mitra' => ! empty($row['no_mitra']) ? trim((string) $row['no_mitra']) : null,
            'flag_aktif' => 'Y',
            'created_by' => $actor,
        ];
    }

    /**
     * Pratinjau impor: penilaian yang sama persis dengan import(), tanpa menulis.
     *
     * @param  array<int, array<string, mixed>>  $validRows
     * @param  array<int, array<string, mixed>>  $skippedRows
     * @param  array<string, array<mixed>>|null  $konteks  diisi uji; null = baca basis data
     * @return array<string, mixed>
     */
    public function pratinjau(array $validRows, array $skippedRows, int $totalBaris = 0, ?array $konteks = null): array
    {
        $validRows = $this->filterValidRows($validRows);
        $kls = $this->klasifikasi($validRows, $konteks ?? $this->muatDataTerkait($validRows));

        return [
            'total_baris' => $totalBaris ?: (count($validRows) + count($skippedRows)),
            'jumlah_siap_impor' => count($kls['siap']),
            'jumlah_sudah_terdaftar' => count($kls['sudah']),
            'jumlah_konflik_aktif' => count($kls['konflik']),
            'jumlah_tanpa_nfc' => count($skippedRows),
            'cuplikan_data' => array_slice($kls['siap'], 0, 8),
            'cuplikan_sudah_terdaftar' => array_slice($kls['sudah'], 0, 5),
            'cuplikan_konflik' => array_slice($kls['konflik'], 0, 5),
            'cuplikan_tanpa_nfc' => array_slice($skippedRows, 0, 5),
        ];
    }

    private function tolakBilaTidakSiap(array $kls, string $hex): void
    {
        if ($kls['konflik'] !== []) {
            $k = $kls['konflik'][0];
            throw new DomainException(
                "Kartu NFC ({$hex}) sudah aktif terpasang pada '{$k['pemegang_lama']}'. "
                .'Nonaktifkan pairing lama tersebut terlebih dahulu jika ingin memasangkan kartu ini ke pemegang baru.'
            );
        }

        if ($kls['sudah'] !== []) {
            throw new DomainException("Kartu NFC ({$hex}) sudah terpasang aktif pada pemegang yang sama.");
        }
    }

    private function cariMasterDiKonteks(array $konteks, array $row): ?object
    {
        $inbio = $this->inbioBaris($row);

        return $konteks['master_by_hex'][$this->hexBaris($row)]
            ?? ($inbio !== null ? ($konteks['master_by_inbio'][$inbio] ?? null) : null);
    }

    private function hexBaris(array $row): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) ($row['nfc_hex'] ?? '')));
    }

    private function inbioBaris(array $row): ?int
    {
        return isset($row['nfc_inbio']) && is_numeric($row['nfc_inbio']) ? (int) $row['nfc_inbio'] : null;
    }
}
