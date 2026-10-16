<?php

namespace Tests\Feature\LMS\SOP;

use App\Http\Controllers\LMS\SOP\Governance\SopAdminDokumenController;
use App\Models\ESS\Employee\Models\User;
use App\Services\LMS\SOP\SopDokumenService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Feature\LMS\SOP\Uat\Support\UatTahapVersi;
use Tests\TestCase;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Kontrak sunting draft: apa yang KELUAR dari /detail harus bisa diterima
 * kembali oleh /versi/{id}.
 *
 * Dua kegagalan berturut-turut lahir dari kontrak yang tak pernah diuji:
 *
 *   1. /detail sama sekali tak mengirim Id_Master_Konten, sehingga formulir
 *      sunting terbuka tanpa isi dan tombol simpannya terkunci.
 *   2. Setelah dikirim, ia dikirim sebagai id MENTAH — padahal updateVersi
 *      men-decode-nya sebagai hashid. Penyimpanan ditolak 422 "Konten tidak
 *      valid", persis pada langkah terakhir.
 *
 * Keduanya lolos dari uji satuan mana pun karena masing-masing ujung benar
 * sendiri-sendiri; yang salah adalah sambungannya. Karena itu uji ini
 * menempuh perjalanan penuh — baca, lalu kirim balik apa yang dibaca.
 */
class SopSuntingDraftTest extends TestCase
{
    use DatabaseTransactions;

    private function siapkan(): array
    {
        $konten = DB::table('N_LMS_Master_Konten')->where('Tipe_Konten', 'document')
            ->value('Id_Master_Konten');
        if (! $konten) {
            $this->markTestSkipped('Tak ada konten dokumen untuk diuji.');
        }

        $svc = app(SopDokumenService::class);
        $idDok = $svc->buat(['Judul' => 'SOP Sunting Draft', 'Tipe' => 'DOKUMEN'], 1);
        $idVersi = $svc->tambahVersi($idDok, ['Disahkan_Oleh' => 'Pengesah Uji', 'Id_Master_Konten' => $konten], 1);

        Auth::login(User::find(1));

        return [$idDok, $idVersi, (int) $konten];
    }

    private function riwayatDari(int $idDok): array
    {
        $res = app(SopAdminDokumenController::class)
            ->detail(Hashids::connection('custom')->encode($idDok));

        return json_decode($res->getContent(), true)['result']['riwayat_versi'] ?? [];
    }

    public function test_detail_mengirim_isi_draft_supaya_formulir_tidak_terbuka_kosong(): void
    {
        [$idDok] = $this->siapkan();

        $draft = collect($this->riwayatDari($idDok))->firstWhere('Status', 'DRAFT');

        $this->assertNotNull($draft);
        $this->assertNotEmpty($draft['Id_Master_Konten'] ?? null,
            'Formulir sunting tak punya apa pun untuk dimuat, dan simpannya terkunci.');
        $this->assertNotEmpty($draft['Konten_Nama'] ?? null,
            'Field berkas yang cuma berisi token tak memberi tahu apa yang terlampir.');
    }

    public function test_nilai_yang_dikirim_detail_diterima_kembali_saat_menyimpan(): void
    {
        [$idDok, $idVersi, $konten] = $this->siapkan();

        $draft = collect($this->riwayatDari($idDok))->firstWhere('Status', 'DRAFT');

        // Persis yang dilakukan formulir: kirim balik nilai yang baru dibaca.
        $req = Request::create('/x', 'PUT', [
            'Id_Master_Konten' => $draft['Id_Master_Konten'],
            'Nomor_Pengesahan' => 'SK-999',
        ]);

        $res = app(SopAdminDokumenController::class)
            ->updateVersi($req, Hashids::connection('custom')->encode($idVersi));

        $this->assertSame(200, $res->getStatusCode(),
            'Bentuk yang keluar harus sama dengan bentuk yang diterima masuk: '.$res->getContent());

        $this->assertSame($konten, (int) DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $idVersi)->value('Id_Master_Konten'),
            'Berkasnya harus tetap yang sama — bukan hilang, bukan berubah.');
    }

    public function test_versi_terbit_tidak_membocorkan_tokennya(): void
    {
        [$idDok, $idVersi, $konten] = $this->siapkan();

        /* Uji ini butuh versi yang BENAR-BENAR terbit — yang dijaganya adalah
           apa yang /detail kirim untuk baris PUBLISHED. Sejak DML-2026-048
           (Pemberkasan) dan DML-2026-051 (Tinjauan Akhir), penerbitan hanya sah
           dari langkah Pemberkasan, jadi alurnya ditempuh lebih dulu. */
        UatTahapVersi::bawaKeAmbangTerbit($idVersi, 1);

        app(SopDokumenService::class)->terbitkanVersi($idVersi, 1);

        $terbit = collect($this->riwayatDari($idDok))->firstWhere('Status', 'PUBLISHED');

        $this->assertNotNull($terbit);

        /* TOKEN UJIAN TETAP DIBUANG, dan hanya itu.
           Id_Master_Ujian adalah token HC Learn MENTAH — bukan hashid — dan ia
           pernah bocor ke layar, tampil apa adanya sebagai judul ujian. Yang
           dibutuhkan layar untuk menampilkannya adalah Nama_Ujian, yang memang
           ikut dikirim. */
        $this->assertArrayNotHasKey('Id_Master_Ujian', $terbit);

        /* BERKAS SEBALIKNYA: sejak 30 Agu 2026 ia dikirim untuk SEMUA keadaan.
           Uji ini semula menuntut Id_Master_Konten ikut dibuang, dengan alasan
           "yang sudah terbit tak bisa disunting". Alasan itu tak lagi berlaku:
           versi terbit boleh dibuka untuk perubahan terbatas, dan pembuangan
           kolomnya membuat kartu "Lampiran Berkas Prosedur" tampil KOSONG pada
           versi yang jelas punya berkas — layar melaporkan tak ada lampiran
           pada dokumen yang sedang dibaca karyawan.

           Bukan pelonggaran akses: yang menahan PENGGANTIAN berkas adalah
           Field_Boleh_Ubah, bukan hilangnya kolom ini. Yang tetap dijaga adalah
           bentuknya — id mentah tak boleh keluar, ia harus sudah ter-hashid. */
        $this->assertNotEmpty($terbit['Id_Master_Konten'] ?? null,
            'Versi terbit tak membawa berkasnya, sehingga kartu lampiran tampil kosong '
            .'pada dokumen yang justru sedang berlaku.');
        $this->assertSame($konten,
            Hashids::connection('custom')->decode($terbit['Id_Master_Konten'])[0] ?? null,
            'Id konten keluar sebagai angka mentah, bukan hashid — bentuk yang sama '
            .'sekali tak bisa dikirim balik oleh formulir.');

        $this->assertTrue($terbit['Punya_Berkas'],
            'Penanda punya-berkas tetap harus ada — layar memakainya untuk tombol Lihat.');
    }

    /**
     * Bentuk id konten yang mana pun harus diterima.
     *
     * Berkas datang dari beberapa produsen — pemilih Library dan riwayat versi
     * mengirim hashid, sebagian kode meneruskan id apa adanya. Menolak salah
     * satunya menghasilkan 422 yang tak menyebut apa yang salah, dan dua kali
     * kegagalan itu memakan waktu untuk ditelusuri padahal cuma soal bentuk.
     */
    public function test_id_konten_diterima_baik_hashid_maupun_mentah(): void
    {
        [, $idVersi, $konten] = $this->siapkan();
        $ctl = app(SopAdminDokumenController::class);
        $hashVersi = Hashids::connection('custom')->encode($idVersi);

        foreach ([
            'hashid' => Hashids::connection('custom')->encode($konten),
            'mentah' => (string) $konten,
        ] as $bentuk => $nilai) {
            $res = $ctl->updateVersi(
                Request::create('/x', 'PUT', ['Id_Master_Konten' => $nilai]), $hashVersi);

            $this->assertSame(200, $res->getStatusCode(),
                "Bentuk {$bentuk} ditolak: ".$res->getContent());
            $this->assertSame($konten, (int) DB::table('N_SOP_Item_Versi')
                ->where('Id_SOP_Item_Versi', $idVersi)->value('Id_Master_Konten'),
                "Bentuk {$bentuk} tersimpan sebagai nilai yang salah.");
        }
    }

    /** Yang benar-benar tak terbaca tetap ditolak — dengan pesan yang berguna. */
    public function test_id_konten_ngawur_ditolak_dengan_pesan_yang_menuntun(): void
    {
        [, $idVersi] = $this->siapkan();

        $res = app(SopAdminDokumenController::class)->updateVersi(
            Request::create('/x', 'PUT', ['Id_Master_Konten' => '!!bukan-id!!']),
            Hashids::connection('custom')->encode($idVersi));

        $this->assertSame(422, $res->getStatusCode());
        $this->assertStringContainsString('Library', $res->getContent(),
            'Pesan galat harus menyebut apa yang mesti dilakukan admin.');
    }
}
