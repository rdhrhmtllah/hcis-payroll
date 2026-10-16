<?php

namespace App\Http\Controllers\SYSTEM\Organization;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\SYSTEM\Organization\OrganizationRelationApprovalService;
use App\Services\SYSTEM\Organization\OrganizationRelationAsyncExportService;
use App\Services\SYSTEM\Organization\OrganizationRelationExportService;
use App\Services\SYSTEM\Organization\OrganizationRelationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Relasi organisasi — siapa atasan, bawahan, dan rekan kerja siapa.
 *
 * Inilah sumber calon penilai bagi Performance Appraisal. Selama tabelnya kosong, usulan
 * ATASAN, PEER, dan BAWAHAN selalu nihil dan hanya penilaian diri yang bisa terbentuk.
 */
class OrganizationRelationController extends Controller
{
    public function __construct(
        private readonly OrganizationRelationService $relasi,
    ) {}

    public function halaman()
    {
        return inertia('SYSTEM/Organization/relasi-organisasi');
    }

    public function ringkasan(Request $request)
    {
        return $this->jalankan(fn () => $this->relasi->ringkasan());
    }

    public function daftar(Request $request)
    {
        $data = $request->validate([
            'tipe_id' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
            'hanya_anomali' => ['nullable', 'boolean'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return $this->jalankan(fn () => $this->relasi->daftar($data));
    }

    public function tambah(Request $request)
    {
        $data = $request->validate([
            'kode_relasi' => ['required', 'string', 'max:40'],
            'perusahaan_subjek' => ['required', 'string', 'max:3'],
            'kode_subjek' => ['required', 'string', 'max:255'],
            'perusahaan_terkait' => ['required', 'string', 'max:3'],
            'kode_terkait' => ['required', 'string', 'max:255'],
            'berlaku_dari' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date'],
            'utama' => ['nullable', 'boolean'],
            'prioritas' => ['nullable', 'integer', 'min:1'],
            'catatan' => ['nullable', 'string', 'max:400'],
        ]);

        return $this->jalankan(fn () => $this->relasi->tambah($data, $this->userId()));
    }

    public function tambahMassal(Request $request)
    {
        $data = $request->validate([
            'kode_relasi' => ['required', 'string', 'max:40'],
            'perusahaan_subjek' => ['required', 'string', 'max:3'],
            'kode_subjek' => ['required', 'string', 'max:255'],
            'daftar_terkait' => ['required', 'array', 'min:1'],
            'daftar_terkait.*.kode_perusahaan' => ['required', 'string', 'max:3'],
            'daftar_terkait.*.kode_karyawan' => ['required', 'string', 'max:255'],
            'berlaku_dari' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:400'],
        ]);

        return $this->jalankan(fn () => $this->relasi->tambahMassal($data, $this->userId()));
    }

    /**
     * Menulis relasi untuk SELURUH yang cocok dengan saringan.
     *
     * Bedanya dengan `tambahMassal`: di sana admin mengirim daftar NAMA yang sudah ia
     * tinjau satu per satu, dan daftarnya dibatasi 200 karena itu batas yang masih bisa
     * dibaca. Di sini admin mengirim MAKSUDNYA — saringannya — dan meninjau jumlahnya.
     * Keduanya jalur yang sah, dan batasnya berbeda karena yang ditinjau berbeda.
     */
    public function tambahMassalSaringan(Request $request)
    {
        $data = $request->validate([
            'kode_relasi' => ['required', 'string', 'max:30'],
            'perusahaan_subjek' => ['required', 'string', 'max:3'],
            'kode_subjek' => ['required', 'string', 'max:255'],

            /* Saringannya dikirim utuh, bukan daftar id hasil resolusi di layar. Kalau
               layar yang menyelesaikan himpunannya, ia hanya bisa menyelesaikan yang
               sempat dimuatnya — dan itu persis cacat yang jalur ini ada untuk menutupnya. */
            'saringan' => ['required', 'array'],
            'saringan.q' => ['nullable', 'string', 'max:100'],
            'saringan.divisi' => ['nullable'],
            'saringan.departemen' => ['nullable'],
            'saringan.id_level' => ['nullable'],

            'berlaku_dari' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:500'],

            /* Admin harus menyebut jumlah yang ia lihat. Kalau himpunannya bergeser antara
               layar menghitung dan admin menekan — orang masuk, orang keluar, saringan
               berubah di tab lain — permintaannya ditolak, bukan dijalankan atas himpunan
               yang tidak pernah ia setujui. */
            'jumlah_dikonfirmasi' => ['required', 'integer', 'min:1'],
        ]);

        /* Pemeriksaannya dititipkan ke layanan, bukan dikerjakan di sini SETELAH memanggil:
           percobaan pertama saya menaruhnya sesudah, dan itu memeriksa jumlah atas baris
           yang sudah terlanjur ditulis. Penjaga yang menyala setelah kejadian bukan penjaga. */
        return $this->jalankan(
            fn () => $this->relasi->tambahMassalBerdasarSaringan($data, $this->userId()),
        );
    }

    public function peerCluster(Request $request)
    {
        $data = $request->validate([
            'anggota' => ['required', 'array', 'min:2'],
            'anggota.*.kode_perusahaan' => ['required', 'string', 'max:3'],
            'anggota.*.kode_karyawan' => ['required', 'string', 'max:255'],
            'berlaku_dari' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:400'],
        ]);

        return $this->jalankan(fn () => $this->relasi->peerCluster($data, $this->userId()));
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'perusahaan_lama' => ['required', 'string', 'max:3'],
            'kode_lama' => ['required', 'string', 'max:255'],
            'perusahaan_baru' => ['required', 'string', 'max:3'],
            'kode_baru' => ['required', 'string', 'max:255'],
            'daftar_bawahan' => ['required', 'array', 'min:1'],
            'daftar_bawahan.*.kode_perusahaan' => ['required', 'string', 'max:3'],
            'daftar_bawahan.*.kode_karyawan' => ['required', 'string', 'max:255'],
            'cabut_lama' => ['nullable', 'boolean'],
            'alasan' => ['nullable', 'string', 'max:400'],
            'berlaku_dari' => ['nullable', 'date'],
        ]);

        return $this->jalankan(fn () => $this->relasi->transferStruktur($data, $this->userId()));
    }

    public function cabut(Request $request, int $id)
    {
        $data = $request->validate([
            'alasan' => ['nullable', 'string', 'max:400'],
        ]);

        return $this->jalankan(fn () => $this->relasi->cabut($id, $data['alasan'] ?? null, $this->userId()));
    }

    public function cabutMassal(Request $request)
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'alasan' => ['nullable', 'string', 'max:400'],
        ]);

        return $this->jalankan(fn () => $this->relasi->cabutMassal($data['ids'], $data['alasan'] ?? null, $this->userId()));
    }

    public function ekosistem(Request $request)
    {
        $data = $request->validate([
            'kode_perusahaan' => ['required', 'string', 'max:3'],
            'kode_karyawan' => ['required', 'string', 'max:255'],
        ]);

        return $this->jalankan(fn () => $this->relasi->ekosistemKaryawan($data['kode_perusahaan'], $data['kode_karyawan']));
    }

    public function daftarBawahan(Request $request)
    {
        $data = $request->validate([
            'kode_perusahaan' => ['required', 'string', 'max:3'],
            'kode_karyawan' => ['required', 'string', 'max:255'],
        ]);

        return $this->jalankan(fn () => $this->relasi->daftarBawahanKaryawan($data['kode_perusahaan'], $data['kode_karyawan']));
    }

    public function opsiFilter()
    {
        return $this->jalankan(fn () => $this->relasi->opsiFilter());
    }

    public function pohonDepartemen(Request $request)
    {
        $data = $request->validate([
            'departemen' => ['required', 'string', 'max:150'],
        ]);

        return $this->jalankan(fn () => $this->relasi->pohonDepartemen($data['departemen']));
    }

    /**
     * Mencari karyawan aktif untuk dijadikan subjek atau pihak terkait.
     *
     * Dicari, bukan diketik: kode yang salah ketik akan ditolak layanan, tetapi menolak
     * sesudah admin mengetik sepuluh kali adalah cara paling melelahkan menyampaikan aturan.
     */
    public function cariKaryawan(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            /* Maksimum 200, sama dengan batas operasi massal di layanan. Memulangkan
               lebih banyak tidak menolong: yang tidak bisa diproses sekali jalan tidak
               berguna ditampilkan sebagai pilihan. */
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            // Halaman (1..n) untuk daftar berhalaman di modal Export; kosong = halaman pertama.
            'halaman' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'divisi' => ['nullable'],
            'departemen' => ['nullable'],
            'id_level' => ['nullable'],

            /* Subjeknya, kalau pencariannya untuk menyusun relasi. Dipakai dua hal:
               mengeluarkan dirinya sendiri dari hasil, dan menandai siapa yang sudah
               terhubung dengannya.

               Keduanya WAJIB di server, bukan di layar: hasilnya dipangkas di 200, jadi
               menyaring belakangan hanya membuang jatah yang sudah terpakai. */
            'perusahaan_subjek' => ['nullable', 'string', 'max:3'],
            'kode_subjek' => ['nullable', 'string', 'max:255'],
            'kode_relasi' => ['nullable', 'string', 'max:30'],
        ]);

        /* Saringannya dirumuskan DI LAYANAN, satu tempat, dipakai bersama jalur tulis
           berbasis saringan. Dulu ia ditulis ulang di sini; dua rumusan untuk satu
           himpunan berarti angka yang dilihat admin dan himpunan yang ditulis bisa
           menyimpang tanpa gejala. */
        $subjekPt = trim((string) ($data['perusahaan_subjek'] ?? ''));
        $subjekKode = trim((string) ($data['kode_subjek'] ?? ''));
        $adaSubjek = $subjekPt !== '' && $subjekKode !== '';

        $query = $this->relasi->kandidatQuery(
            [
                'q' => $data['q'] ?? '',
                'divisi' => $data['divisi'] ?? null,
                'departemen' => $data['departemen'] ?? null,
                'id_level' => $data['id_level'] ?? null,
            ],
            $adaSubjek ? ['pt' => $subjekPt, 'kode' => $subjekKode] : null,
        );

        $batas = (int) ($data['limit'] ?? 30);

        /* Berapa yang SEBENARNYA cocok, dihitung sebelum dipangkas.

           Tanpa angka ini, admin yang memilih "semua divisi" mendapat 40 baris dan tidak
           punya cara tahu bahwa yang cocok 391. Ia akan mengira itulah seluruhnya, lalu
           menjalankan operasi massal atas sebagian orang tanpa menyadarinya.

           Setiap pembatasan wajib dilaporkan. Plafon yang diam adalah plafon yang
           menyesatkan. */
        $total = (clone $query)->count();

        $lewati = (max(1, (int) ($data['halaman'] ?? 1)) - 1) * $batas;
        $baris = $query->orderBy('k.Nama')->orderBy('k.Kode_Karyawan')
            ->offset($lewati)
            ->limit($batas)
            ->get([
                'k.Kode_Perusahaan as kode_perusahaan',
                'k.Kode_Karyawan as kode_karyawan',
                'k.Nama as nama',
                'l.Keterangan as nama_level',
                'vd.nama_divisi as divisi',
                'vd.nama_sub_divisi as departemen',
                'k.Tgl_Masuk as tgl_masuk',
            ])
            ->all();

        /* Relasi yang SUDAH ada antara subjek dan orang-orang ini — dan mana yang
           bertentangan dengannya.

           Aturannya DIPUTUSKAN DI LAYANAN, satu tempat, dipakai bersama jalur tulis.
           Berkas ini sempat menyalinnya, dan salinan itu tertinggal: ia hanya mengenal
           pertentangan ATASAN↔BAWAHAN, sementara layanan sudah lama menolak jenis apa pun
           yang berbeda pada satu pasang orang. Yang tampak di layar boleh dicentang, lalu
           servernya melewatinya diam-diam.

           Yang sudah terhubung tetap DITAMPILKAN, bukan dibuang — admin perlu melihat
           bahwa orang itu memang sudah ada di sana. Yang berubah: tidak bisa dicentang,
           dan sebabnya tertulis. */
        if ($adaSubjek) {
            $baris = $this->relasi->tandaiKandidat(
                $baris,
                $subjekPt,
                $subjekKode,
                (string) ($data['kode_relasi'] ?? ''),
            );
        } else {
            $baris = array_map(static function ($b) {
                $b->relasi_terpasang = [];
                $b->sudah_terhubung = false;
                $b->bentrok_arah = false;
                $b->bentrok_sebab = null;

                return $b;
            }, $baris);
        }

        $sudah = count(array_filter($baris, static fn ($b) => $b->sudah_terhubung));
        $bentrok = count(array_filter($baris, static fn ($b) => $b->bentrok_arah));

        return ResponseHelper::success([
            'baris' => $baris,
            'total' => $total,
            'batas' => $batas,

            /* Dilaporkan supaya layarnya bisa berkata "12 dari 105 sudah terhubung",
               bukan membiarkan admin menebak kenapa kotaknya tidak bisa dicentang. */
            'total_sudah_terhubung' => $sudah,

            /* Dihitung terpisah dari "sudah terhubung": keduanya sama-sama terkunci, tetapi
               artinya berlawanan. Yang satu berarti tidak ada yang perlu dikerjakan, yang
               lain berarti ada data yang perlu diperiksa orang. */
            'total_bentrok_arah' => $bentrok,
            'diri_dikecualikan' => $adaSubjek,

            /* Layar WAJIB menampilkan ini. Diberi nama yang tidak bisa disalahartikan
               supaya tidak berakhir sebagai field yang diabaikan. */
            'dipangkas' => $total > $lewati + count($baris),
            'halaman' => intdiv($lewati, max(1, $batas)) + 1,
        ], 'Berhasil');
    }

    /**
     * Unduh relasi sebagai Excel. Saringannya sama dengan daftar di layar, ditambah kategori
     * ganda (`tipe_ids[]`, kosong = semua) dan divisi subjek.
     */
    public function ekspor(Request $request, OrganizationRelationExportService $ekspor)
    {
        $data = $this->validasiEkspor($request);

        try {
            $hasil = $ekspor->buat($data, $request->user()?->Nama ?? $request->user()?->name ?? null);
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        return response($hasil['isi'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$hasil['nama_berkas'].'"',
            'X-Jumlah-Baris' => (string) $hasil['jumlah'],
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * SEMUA karyawan yang cocok saringan (ringkas), untuk "Centang semua yang cocok" di modal Export.
     * Berbatas 5.000 — lebih dari itu, ekspor lewat saringan ("tanpa centang") yang tidak berbatas.
     */
    public function semuaKaryawanCocok(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable'],
            'departemen' => ['nullable'],
            'id_level' => ['nullable'],
        ]);
        $query = $this->relasi->kandidatQuery([
            'q' => $data['q'] ?? '',
            'divisi' => $data['divisi'] ?? null,
            'departemen' => $data['departemen'] ?? null,
            'id_level' => $data['id_level'] ?? null,
        ]);
        $total = (clone $query)->count();
        if ($total > 5000) {
            return ResponseHelper::error("Yang cocok {$total} karyawan — lebih dari 5.000 untuk dicentang. Ekspor tanpa centang (semua yang cocok saringan) saja.", 422);
        }

        return ResponseHelper::success([
            'total' => $total,
            'baris' => $query->orderBy('k.Nama')->orderBy('k.Kode_Karyawan')->get([
                'k.Kode_Perusahaan as kode_perusahaan', 'k.Kode_Karyawan as kode_karyawan', 'k.Nama as nama',
                'l.Keterangan as nama_level', 'vd.nama_divisi as divisi', 'vd.nama_sub_divisi as departemen',
                'k.Tgl_Masuk as tgl_masuk',
            ])->all(),
        ], 'Berhasil');
    }

    /**
     * Export di LATAR BELAKANG: dibaca per batch, progres di Activity Center, berkas diunduh lewat
     * /api/v1/activities/export/{id}/download. Saringannya sama dengan `ekspor`.
     */
    public function mulaiEkspor(Request $request, OrganizationRelationAsyncExportService $layanan)
    {
        $data = $this->validasiEkspor($request);

        // Penyimpanan berkas tidak bisa dipakai → layar memakai unduhan langsung (GET /ekspor).
        if (! $layanan->penyimpananSiap()) {
            return ResponseHelper::success(['mode' => 'langsung'], 'Penyimpanan ekspor tidak tersedia; unduh langsung.');
        }

        return $this->jalankan(fn () => ['mode' => 'latar', 'export_id' => $layanan->mulai(
            $data,
            $this->kodeKaryawanPeminta(),
            $request->user()?->Nama ?? $request->user()?->name ?? null,
        )]);
    }

    public function statusEkspor(string $exportId, OrganizationRelationAsyncExportService $layanan)
    {
        $status = $layanan->statusUntuk($exportId, $this->kodeKaryawanPeminta());

        return $status === null
            ? ResponseHelper::error('Ekspor tidak ditemukan.', 404)
            : ResponseHelper::success($status, 'Berhasil');
    }

    /** Requested_By = Kode_Karyawan, sama dengan yang dibaca Activity Center. */
    private function kodeKaryawanPeminta(): ?string
    {
        return optional(optional(Auth::user())->karyawan)->Kode_Karyawan;
    }

    private function validasiEkspor(Request $request): array
    {
        return $request->validate([
            'tipe_ids' => ['nullable', 'array'],
            'tipe_ids.*' => ['integer'],
            'cari' => ['nullable', 'string', 'max:100'],
            'divisi' => ['nullable'],
            'divisi.*' => ['string', 'max:150'],
            'karyawan' => ['nullable', 'array', 'max:5000'],
            'subjek_q' => ['nullable', 'string', 'max:100'],
            'subjek_divisi' => ['nullable', 'array'],
            'subjek_divisi.*' => ['string', 'max:150'],
            'subjek_departemen' => ['nullable', 'array'],
            'subjek_departemen.*' => ['string', 'max:150'],
            'subjek_level' => ['nullable', 'array'],
            'subjek_level.*' => ['integer'],
            'karyawan.*' => ['string', 'max:300'],
            'hanya_anomali' => ['nullable', 'boolean'],
        ]);
    }

    /** Excel usulan ATASAN dari approval KPI (urutan 1) dan HCIS, untuk ditinjau lalu diimpor. */
    public function eksporUsulanApproval(Request $request, OrganizationRelationApprovalService $layanan)
    {
        return $this->unduhBukuAtasan(fn ($oleh) => $layanan->ekspor($oleh), $request);
    }

    private function unduhBukuAtasan(callable $buat, Request $request)
    {
        try {
            $hasil = $buat($request->user()?->Nama ?? $request->user()?->name ?? null);
        } catch (RuntimeException $e) {
            return ResponseHelper::error($e->getMessage(), 422);
        }

        return response($hasil['isi'], 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$hasil['nama_berkas'].'"',
            'X-Jumlah-Baris' => (string) $hasil['jumlah'],
            'Cache-Control' => 'no-store',
        ]);
    }

    /** `jalankan` = 0 → pratinjau (semua diproses lalu dibatalkan); 1 → simpan, semua-atau-tidak. */
    public function imporUsulanApproval(Request $request, OrganizationRelationApprovalService $layanan)
    {
        $data = $request->validate([
            'berkas' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
            'jalankan' => ['nullable', 'boolean'],
        ]);

        return $this->jalankan(fn () => $layanan->impor(
            $data['berkas']->getRealPath(),
            (bool) ($data['jalankan'] ?? false),
            $this->userId(),
        ));
    }

    private function jalankan(callable $operasi)
    {
        try {
            return ResponseHelper::success($operasi(), 'Berhasil');
        } catch (RuntimeException $e) {
            // 422, bukan 500: aturannya yang menolak, bukan sistemnya yang rusak.
            return ResponseHelper::error($e->getMessage(), 422);
        }
    }

    private function userId(): ?int
    {
        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }
}
