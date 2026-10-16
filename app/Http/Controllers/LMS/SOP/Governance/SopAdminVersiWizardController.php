<?php

namespace App\Http\Controllers\LMS\SOP\Governance;

use App\Http\Controllers\Controller;
use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopMasaBerlakuService;
use App\Services\LMS\SOP\Support\SopNomorDokumen;
use App\Services\LMS\SOP\Support\SopNomorVersi;
use App\Services\LMS\SOP\Support\SopStatus;
use App\Services\SYSTEM\MasterData\Departemen\DepartemenService;
use App\Services\SYSTEM\MasterData\Entitas\EntitasService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Halaman wizard versi dokumen SOP.
 *
 * HANYA merender halaman. Seluruh data dibaca lewat API
 * /api/v1/sop/admin/dokumen yang sudah melayani modal riwayat, sehingga tak
 * ada kontrak kedua yang harus dijaga sejalan. Yang dikirim sebagai prop
 * hanyalah identitas secukupnya untuk menggambar kepala halaman sebelum
 * permintaan pertama mendarat — tanpa itu, halaman terbuka kosong lalu
 * mendadak terisi, dan admin sempat mengira ia salah membuka dokumen.
 *
 * MEMAKAI ULANG GERBANG menuSopDokumenPage.
 */
class SopAdminVersiWizardController extends Controller
{
    private function decode($id): ?int
    {
        return Hashids::connection('custom')->decode((string) $id)[0] ?? null;
    }

    /** GET /sop/admin/dokumen/{dokumen}/versi/{versi?} */
    public function halaman($dokumen, $versi = null)
    {
        $idDokumen = $this->decode($dokumen);
        abort_if($idDokumen === null, 404, 'Dokumen tidak dikenali.');

        $dok = DB::table('N_SOP_Dokumen')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first([
                'Id_SOP_Dokumen', 'Nomor_SOP_Resmi', 'No_Dokumen', 'Judul', 'Tipe', 'Deskripsi',
                'Jenis_Dokumen_Id', 'APQC_Id', 'Departemen_Id', 'Entitas_Pemilik_Id', 'Entitas_Lingkup_Id', 'Id_Versi_Aktif',
            ]);
        abort_if(! $dok, 404, 'Dokumen tidak ditemukan.');

        $jenis = $dok->Jenis_Dokumen_Id
            ? app(\App\Services\LMS\SOP\SopJenisDokumenService::class)->cari((int) $dok->Jenis_Dokumen_Id, false)
            : null;
        $apqcRow = $dok->APQC_Id
            ? app(\App\Services\LMS\SOP\SopApqcService::class)->cari((int) $dok->APQC_Id, false)
            : null;
        $depRow = $dok->Departemen_Id
            ? app(DepartemenService::class)->cari((int) $dok->Departemen_Id, false)
            : null;
        $entitasSvc = app(EntitasService::class);
        $entitasPemilik = $dok->Entitas_Pemilik_Id ? $entitasSvc->cari((int) $dok->Entitas_Pemilik_Id, false) : null;
        $entitasLingkup = $dok->Entitas_Lingkup_Id ? $entitasSvc->cari((int) $dok->Entitas_Lingkup_Id, false) : null;

        /* Nomor gabungan dirakit SERVER, tak pernah di layar. Rumus tujuh
           segmennya tinggal di SopNomorDokumen, dan dua implementasi untuk satu
           format pasti menyimpang — yang menyimpang di sini adalah nomor yang
           tercetak di dokumen resmi.

           Segmen Revisi memakai versi yang BERLAKU, bukan versi yang sedang
           disunting di wizard ini: kepala halaman adalah paspor DOKUMEN, dan
           menaruh revisi draft di sana membuatnya menyebut nomor yang belum sah
           bagi siapa pun. Selama belum ada versi terbit, segmen itu memang
           berlubang — dan lubang yang terlihat lebih menolong daripada satu
           angka yang tak berarti apa-apa. */
        $svcDokumen = app(SopDokumenService::class);
        $identitasNomor = (array) $dok + ['Revisi' => $svcDokumen->revisiBerlaku($idDokumen)];
        $nomorLengkap = $svcDokumen->nomorLengkap($identitasNomor);
        $nomorPratinjau = $svcDokumen->pratinjauNomor($identitasNomor);
        $nomorSegmenKosong = SopNomorDokumen::segmenKosong($svcDokumen->segmenNomor($identitasNomor));

        /* Baku masa berlaku ikut sebagai prop, BUKAN endpoint tersendiri. */
        $aturan = app(SopMasaBerlakuService::class)->aturanDokumen($idDokumen);

        /* Nomor versi tertinggi, HANYA di antara versi yang hidup — aturan yang
           sama dengan SopNomorVersi. Sebelumnya baris ini menghitung MAX tanpa
           saringan, mengirimkannya ke layar sebagai usulan, dan layar
           mengembalikannya sebagai nomor MANUAL. Penomoran otomatis yang sudah
           benar karena itu tak pernah terpakai: versi baru pada dokumen yang
           v01-nya dibatalkan lahir sebagai v02. */
        $maxVersiKe = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Dokumen', $idDokumen)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->max('Versi_Ke');

        /* ISI VERSI TERAKHIR TIDAK LAGI DIBACA SAMA SEKALI.
           Baris lengkapnya (SELECT *), blok pengesahnya, dan judul kontennya dulu
           diambil semata untuk mengisi sendiri formulir versi baru. Pengisian itu
           dicabut 30 Agu 2026 — isian yang tersimpan tanpa ada yang memilihnya
           membuat riwayat versi melaporkan pengesah dan berkas yang tak pernah
           dipilih siapa pun. Yang tersisa dibutuhkan layar hanyalah NOMORNYA,
           dan itu sudah ada di $maxVersiKe. Empat kueri per pemuatan halaman
           ikut hilang. */
        $nextVersiKe = SopNomorVersi::berikutnya($idDokumen);

        return Inertia::render('LMS/SOP/Governance/Admin/SopVersiWizard', [
            'idDokumen' => $dokumen,
            'idVersi' => $versi,
            'infoVersi' => [
                'Max_Versi_Ke' => $maxVersiKe !== null ? (int) $maxVersiKe : null,
                'Next_Versi_Ke' => $nextVersiKe,
                /* HANYA NOMORNYA. Versi sebelumnya dipakai layar untuk satu hal
                   saja: menyebut "melanjutkan dari v00" di kepala langkah
                   Pengajuan dan Tinjauan.

                   Isinya — berkas, ujian, pengesah, tanggal — sengaja tidak
                   dikirim. Satu-satunya pembacanya dulu adalah pengisian otomatis
                   formulir versi baru, dan pengisian itu dicabut 30 Agu 2026:
                   isian yang tersimpan tanpa ada yang memilihnya membuat riwayat
                   versi melaporkan isi yang tak pernah ada.

                   Dicabut dari muatan, bukan dibiarkan menganggur. Yang menganggur
                   akan dirangkai ulang oleh pembaca berikutnya. */
                'Versi_Sebelumnya' => $maxVersiKe === null ? null : [
                    'Versi_Ke' => (int) $maxVersiKe,
                ],
            ],
            'dokumen' => [
                'Id_SOP_Dokumen' => $dokumen,
                'Judul' => $dok->Judul,
                'Tipe' => $dok->Tipe,
                'Deskripsi' => $dok->Deskripsi,
                'Nomor_SOP_Resmi' => $dok->Nomor_SOP_Resmi,
                'No_Dokumen' => $dok->No_Dokumen,
                /* NOMOR GABUNGAN, kontrak yang sama dengan Master Dokumen.
                   Tanpa ketiganya layar hanya punya Nomor_SOP_Resmi — yang
                   NULL selama salah satu dari tujuh segmen belum terisi — lalu
                   jatuh ke No_Dokumen, yaitu SATU segmen saja. Kotak nomornya
                   jadi menampilkan "1" untuk dokumen yang sebenarnya bernomor
                   SK/HOL/IA/EHG/02/01/??, dan admin membaca angka yang tak
                   pernah ada di dokumen mana pun. */
                'Nomor_Lengkap' => $nomorLengkap,
                'Nomor_Pratinjau' => $nomorPratinjau,
                'Nomor_Segmen_Kosong' => $nomorSegmenKosong,
                'Jenis_Nama' => $jenis->Nama_Jenis ?? null,
                'Jenis_Kode' => $jenis->Kode_Jenis_Dokumen ?? null,
                'Jenis_Level' => $jenis ? (int) $jenis->Level_Hirarki : null,
                'APQC_Kode' => $apqcRow->Kode_Proses ?? null,
                'APQC_Nama' => $apqcRow->Nama_Proses ?? null,
                'Departemen_Kode' => $depRow->Kode_Departemen ?? null,
                'Departemen_Nama' => $depRow->Nama_Departemen ?? null,
                'Pemilik_Label' => $depRow ? $depRow->Kode_Departemen.' — '.$depRow->Nama_Departemen : null,
                'Entitas_Pemilik_Kode' => $entitasPemilik->Kode_Entitas ?? null,
                'Entitas_Pemilik_Nama' => $entitasPemilik->Nama_Entitas ?? null,
                'Entitas_Lingkup_Kode' => $entitasLingkup->Kode_Entitas ?? null,
                'Entitas_Lingkup_Nama' => $entitasLingkup->Nama_Entitas ?? null,
                'Masa_Berlaku_Bulan' => $aturan['Masa_Berlaku_Bulan'],
                'Siklus_Review_Bulan' => $aturan['Siklus_Review_Bulan'],
            ],
        ]);
    }
}
