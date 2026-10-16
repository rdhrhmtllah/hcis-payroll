<?php

namespace App\Services\ESS\Employee\DataCollection;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PengirimanDataService
{
    public function __construct(
        private SkemaPengumpulanData $skema,
        private StatusKaryawanResolver $resolver,
        private StatusPengumpulanService $status,
    ) {}

    public function kirim(string $kodeKaryawan, array $data, array $meta = []): int
    {
        if ($this->status->untuk($kodeKaryawan)['status'] === 'SELESAI') {
            throw new SudahTerkirimException;
        }

        $isPhl = $this->resolver->isPhl($kodeKaryawan);

        // Hanya field yang BERLAKU untuk orang ini yang divalidasi dan disimpan.
        // Field khusus PHL yang dikirim oleh non-PHL dibuang di sini, bukan
        // dipercaya dari browser. Begitu pula pertanyaan bersyarat yang
        // syaratnya tidak terpenuhi: jumlah tanggungan yang tetap terkirim
        // setelah statusnya diganti jadi 'Belum Menikah' adalah nilai sisa —
        // menyimpannya berarti menyimpan data yang salah, bukan data tambahan.
        $berlaku = array_keys($this->skema->fieldBerlakuUntukJawaban($isPhl, $data));
        $bersih = $this->normalkanKiriman(array_intersect_key($data, array_flip($berlaku)));

        // Kalimat galat dan nama field diambil dari skema: yang gagal di server
        // tetap terbaca sebagai bahasa manusia saat dikembalikan ke wizard.
        $tervalidasi = Validator::make(
            $bersih,
            $this->skema->aturanValidasi($isPhl, $data),
            $this->skema->pesanValidasi(),
            $this->skema->atributValidasi(),
        )->validate();

        $tervalidasi = $this->kodeAtasanResmi($tervalidasi);

        $snapshot = $this->snapshotDataLama($kodeKaryawan);

        return DB::transaction(function () use ($kodeKaryawan, $tervalidasi, $snapshot, $meta): int {
            // Gerbang di atas berjalan SEBELUM transaksi, jadi dua pengiriman
            // bersamaan — klik ganda sudah cukup — sama-sama melewatinya.
            // Indeks unik tersaring hanya menjamin satu baris AKTIF; ia tidak
            // mencegah kiriman kedua, sebab yang kedua menonaktifkan baris
            // pertama lalu menang. Aturan "sekali kirim terkunci permanen"
            // bisa tertembus persis di celah itu.
            //
            // Karena itu STATUS-nya diperiksa ulang di sini, setelah barisnya
            // dikunci. Yang datang belakangan menunggu, lalu melihat status
            // sudah SELESAI dan mundur.
            //
            // Yang dikunci STATUS, bukan keberadaan baris kiriman — sengaja.
            // bukaKunci() hanya mengembalikan Status ke 'BELUM' dan TIDAK
            // menyentuh Flag_Aktif, jadi menolak berdasar "sudah ada kiriman
            // aktif" akan mematikan katup darurat admin: orang yang baru
            // dibukakan kuncinya tak akan pernah bisa mengirim ulang.
            $statusTerkunci = DB::table('N_HRIS_Pengumpulan_Data_Status')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->lockForUpdate()
                ->value('Status');

            if ($statusTerkunci === 'SELESAI') {
                throw new SudahTerkirimException;
            }

            // Indeks unik tersaring memaksa hanya satu baris Flag_Aktif='Y'.
            DB::table('N_HRIS_Pengumpulan_Data_Submission')
                ->where('Kode_Karyawan', $kodeKaryawan)
                ->where('Flag_Aktif', 'Y')
                ->update(['Flag_Aktif' => 'T', 'Updated_At' => now()]);

            $id = DB::table('N_HRIS_Pengumpulan_Data_Submission')->insertGetId([
                'Kode_Karyawan' => $kodeKaryawan,
                'Versi_Skema' => $this->skema->versi(),
                'Data_Json' => json_encode($tervalidasi, JSON_UNESCAPED_UNICODE),
                'Snapshot_Lama_Json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                'Flag_Aktif' => 'Y',
                'Submitted_At' => now(),
                'Submitted_Ip' => $meta['ip'] ?? null,
                'Submitted_User_Agent' => substr((string) ($meta['user_agent'] ?? ''), 0, 500) ?: null,
                'Created_At' => now(),
            ]);

            $this->status->tandaiSelesai($kodeKaryawan);

            return (int) $id;
        });
    }

    /**
     * Nilai master untuk PREFILL — sudah dinormalkan dan disaring.
     *
     * Berbeda dari snapshotDataLama() yang sengaja mentah. Prefill mengisi
     * kotak yang dilihat orang, jadi ia hanya boleh berisi nilai yang AKAN
     * DITERIMA server. Yang tidak bisa diselamatkan dibuang: kotak kosong itu
     * jujur, kotak terisi salah itu menipu.
     *
     * Diukur sebelum penjaga ini ada, atas 30 karyawan nyata:
     *   no_hp 30/30 gagal, nik_ktp 29/30, jenis_kelamin 26/30, pendidikan 1/30.
     *
     * Yang paling berbahaya nomor HP, karena tiga lapis sepakat berbohong:
     * layar memotong '62' sehingga tampak benar, galatFormat() memakai
     * pemotong yang sama sehingga diam, dan hanya server yang menolak.
     */
    /**
     * Bentuk kiriman yang setara dirapikan SEBELUM divalidasi.
     *
     * Angka dengan nol di depan ('01') ditolak aturan 'integer' dengan kalimat
     * yang tidak menunjuk kesalahannya. Browser sudah membuangnya saat
     * mengetik; ini untuk draf lama dan kiriman dari luar wizard.
     */
    private function normalkanKiriman(array $data): array
    {
        foreach ($this->skema->semuaField() as $kunci => $def) {
            if (($def['format'] ?? null) === 'angka' && is_string($data[$kunci] ?? null)) {
                $data[$kunci] = preg_replace('/^0+(?=\d)/', '', trim($data[$kunci]));
            }
        }

        return $data;
    }

    /**
     * Kode atasan disimpan dalam bentuk RESMI dari tabel Karyawan.
     *
     * SQL Server membandingkan tanpa peduli besar-kecil huruf, jadi 'r52'
     * lolos aturan 'exists' untuk R52 — lalu tersimpan apa adanya dan tidak
     * cocok lagi ketika dicari dari PHP (nama atasan hilang di Ringkasan).
     */
    private function kodeAtasanResmi(array $data): array
    {
        foreach ($this->skema->semuaField() as $kunci => $def) {
            if (($def['tipe'] ?? null) !== 'cari_karyawan' || ! is_string($data[$kunci] ?? null)) {
                continue;
            }

            $resmi = DB::table('Karyawan')->where('Kode_Karyawan', $data[$kunci])->value('Kode_Karyawan');

            if ($resmi !== null) {
                $data[$kunci] = (string) $resmi;
            }
        }

        return $data;
    }

    public function ambilDataMaster(string $kodeKaryawan): array
    {
        $mentah = $this->snapshotDataLama($kodeKaryawan);
        $isPhl = $this->resolver->isPhl($kodeKaryawan);
        $aturan = $this->skema->aturanValidasi($isPhl);

        $hasil = [];

        foreach ($mentah as $field => $nilai) {
            $normal = $this->normalkanUntukPrefill($field, (string) $nilai);

            if ($normal === null) {
                continue;
            }

            // Penyaring terakhir, dan sengaja memakai aturan server yang SAMA.
            // Normaliser di atas hanya menebak bentuk; yang memutuskan tetap
            // aturan yang nanti menolak. Tanpa langkah ini, bentuk master baru
            // yang belum terpikirkan akan lolos diam-diam lagi.
            if (isset($aturan[$field])) {
                $gagal = Validator::make([$field => $normal], [$field => $aturan[$field]])->fails();

                if ($gagal) {
                    continue;
                }
            }

            $hasil[$field] = $normal;
        }

        return $hasil;
    }

    /**
     * Menerjemahkan bentuk master ke bentuk yang dituntut skema.
     *
     * Mengembalikan null bila nilainya tidak bisa diselamatkan.
     */
    private function normalkanUntukPrefill(string $field, string $nilai): ?string
    {
        $teks = trim($nilai);

        if ($teks === '') {
            return null;
        }

        return match ($field) {
            // Master dan aturan fitur ini sama-sama '62…', jadi untuk 958 dari
            // 958 baris nyata langkah ini tidak mengubah apa pun. Ia tetap ada
            // untuk baris yang bentuknya menyimpang ('08…', '+62…', berspasi):
            // awalan apa pun dibuang, lalu '62' dipasang satu kali — meniru
            // nilaiSimpanHp() di browser supaya kedua sisi sepakat.
            'no_hp' => $this->normalkanHp($teks),

            // Master menyimpan datetime ('2025-06-03 00:00:00.000'). Aturan
            // server meloloskannya, tetapi HcisDatePicker memakai
            // valueFormat 'YYYY-MM-DD' — bentuk berjam akan ditolak kotaknya
            // dan tampil KOSONG. Prefill yang lolos server tapi tak muncul di
            // layar sama saja tidak ada.
            'tanggal_lahir' => $this->normalkanTanggal($teks),

            // Master: 'Laki-Laki' / 'Perempuan'. Skema: 'L' / 'P'.
            'jenis_kelamin' => match (strtoupper(substr($teks, 0, 1))) {
                'L' => 'L',
                'P' => 'P',
                default => null,
            },

            // Sejak skema versi 4 'D4' dan 'S1' pilihan terpisah, jadi keduanya
            // diteruskan apa adanya. 'D4/S1' tidak bisa ditebak ke salah satunya
            // — dibuang, supaya orangnya memilih sendiri.
            'pendidikan_terakhir' => match (strtoupper($teks)) {
                'D4/S1' => null,
                'SMA', 'SMK', 'SMA/SMK' => 'SMA/SMK',
                default => strtoupper($teks),
            },

            default => $teks,
        };
    }

    private function normalkanTanggal(string $teks): ?string
    {
        try {
            return \Carbon\CarbonImmutable::parse($teks)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalkanHp(string $teks): ?string
    {
        $angka = preg_replace('/\D/', '', $teks) ?? '';

        if (str_starts_with($angka, '62')) {
            $angka = substr($angka, 2);
        }

        $angka = ltrim($angka, '0');

        return $angka === '' ? null : '62'.$angka;
    }

    /**
     * Salinan nilai dari sistem induk pada detik pengiriman.
     *
     * Dikunci dengan nama field KITA (bukan nama kolom DB) supaya fase 4 cukup
     * membandingkan dua peta yang sebentuk untuk menandai "berbeda dari data
     * lama". Murni SELECT — tabel induk tidak pernah ditulis.
     *
     * status_pernikahan, atasan, dan kontak darurat sengaja tidak ada di sini: tidak
     * ada padanannya di tabel induk, dan memang itu sebabnya data ini dikumpulkan.
     */
    private function snapshotDataLama(string $kodeKaryawan): array
    {
        $baris = DB::table('Karyawan as k')
            ->leftJoin('View_Divisi_Sub_Divisi as vds', 'vds.ID_DIVISI_SUB_DIVISI', '=', 'k.ID_Divisi_Sub_Divisi')
            ->where('k.Kode_Karyawan', $kodeKaryawan)
            ->first([
                'k.Nama',
                'k.NIK',
                'k.Tgl_Lahir',
                'k.Jenis_Kelamin',
                'k.Email',
                'k.HP',
                'k.Pendidikan_Terakhir',
                'vds.nama_divisi',
                'vds.nama_sub_divisi',
            ]);

        if (! $baris) {
            return [];
        }

        $bersihkan = static function (?string $nilai): ?string {
            if ($nilai === null) {
                return null;
            }

            $trim = trim($nilai);
            if ($trim === '' || preg_match('/^[\s\-–—]+$/u', $trim)) {
                return null;
            }

            return $trim;
        };

        $mentah = [
            'nama_lengkap' => $bersihkan($baris->Nama),
            'nik_ktp' => $bersihkan($baris->NIK),
            'tanggal_lahir' => $bersihkan($baris->Tgl_Lahir),
            'jenis_kelamin' => $bersihkan($baris->Jenis_Kelamin),
            'email' => $bersihkan($baris->Email),
            'no_hp' => $bersihkan($baris->HP),
            'pendidikan_terakhir' => $bersihkan($baris->Pendidikan_Terakhir),
            'departemen' => $bersihkan($baris->nama_sub_divisi ?: $baris->nama_divisi),
        ];

        return array_filter($mentah, static fn ($v) => $v !== null && $v !== '');
    }
}
