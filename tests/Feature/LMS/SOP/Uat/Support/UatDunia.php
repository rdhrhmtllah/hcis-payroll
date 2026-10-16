<?php

namespace Tests\Feature\LMS\SOP\Uat\Support;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopItemService;
use App\Services\LMS\SOP\SopPaketService;
use App\Services\LMS\SOP\SopPenjadwalanService;
use App\Services\LMS\SOP\SopSectionService;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Membangun "dunia SOP" lengkap untuk satu skenario uji: dokumen
 * berversi terbit → paket → section → item → penjadwalan → progres.
 *
 * KENAPA LEWAT SERVICE, BUKAN INSERT MENTAH. Menyisipkan baris langsung lebih
 * pendek dan lebih cepat, tapi ia membangun dunia yang tidak pernah bisa lahir
 * dari aplikasi: kolom turunan tak terisi, penomoran versi meleset, penunjuk
 * versi aktif menunjuk ke tempat yang salah. Gerbang yang diuji lalu menghadapi
 * bentuk data yang tak pernah ada di produksi — dan hasilnya, hijau maupun
 * merah, tak berarti apa-apa.
 *
 * Seluruh penulisan berada di dalam DatabaseTransactions milik test, jadi
 * ter-rollback tanpa perlu jurnal maupun pembersih. Jurnal hanya dibutuhkan
 * lapis peramban, yang tak bisa di-rollback.
 */
class UatDunia
{
    /**
     * Blok penanda tangan bawaan dunia uji.
     *
     * Wajib ada sejak penjaga pengesahan dipasang: versi tanpa pengesah tak bisa
     * diterbitkan. Fixture yang membangun dokumen tanpa blok ini akan gagal di
     * langkah terbit, dan kegagalannya menunjuk ke dunia uji yang cacat — bukan
     * ke perilaku yang sedang diuji.
     */
    public const PENGESAH_UJI = [
        ['Peran' => 'Disiapkan oleh', 'Nama' => 'Penyiap Uji UAT', 'Jabatan' => 'Staff'],
        ['Peran' => 'Disahkan oleh', 'Nama' => 'Pengesah Uji UAT', 'Jabatan' => 'Dept. Head'],
    ];

    public int $idDokumen;

    public int $idVersi;

    public int $idPaket;

    public int $idSection;

    /** @var list<int> Id_SOP_Paket_Item, urut sesuai penambahan */
    public array $idItem = [];

    public ?int $idPenjadwalan = null;

    public string $kodeKaryawan = '';

    private function __construct(
        private readonly int $userId,
        private readonly string $tanda,
    ) {}

    public static function mulai(int $userId): self
    {
        return new self($userId, 'UAT-AUTO-'.substr(md5(uniqid('', true)), 0, 6));
    }

    public function nama(string $bagian): string
    {
        return "{$this->tanda} {$bagian}";
    }

    /**
     * Penanda unik jalan-uji ini; dipakai juga sebagai Nomor_SOP_Resmi dokumen
     * dan itemnya.
     *
     * Dipakai untuk mengenali baris milik sendiri pada keluaran layanan yang
     * MENG-HASH id-nya sebelum mengembalikan — register kontrol dokumen salah
     * satunya. Menyaring dengan membandingkan id integer di sana tidak pernah
     * cocok, dan hasilnya daftar kosong yang membuat assertEmpty() lulus tanpa
     * menguji apa pun.
     */
    public function tanda(): string
    {
        return $this->tanda;
    }

    /** Id user admin yang membangun dunia ini; dibutuhkan pemanggilan service langsung. */
    public function userIdUji(): int
    {
        return $this->userId;
    }

    /**
     * Konten yang PASTI cocok untuk tipe DOKUMEN, diambil dari versi yang sudah
     * terbit. Mengarang Id_Master_Konten akan tersandung penjaga kecocokan tipe
     * di pastikanIsiSah(), sehingga skenario yang sedang diuji gagal karena
     * alasan yang sama sekali lain.
     */
    public static function kontenDokumenCocok(): ?int
    {
        $id = DB::table('N_SOP_Item_Versi as v')
            ->join('N_SOP_Dokumen as d', 'd.Id_SOP_Dokumen', '=', 'v.Id_SOP_Dokumen')
            ->where('d.Tipe', 'DOKUMEN')
            ->whereNotNull('v.Id_Master_Konten')
            ->where('v.Status', SopStatus::PAKET_PUBLISHED)
            ->value('v.Id_Master_Konten');

        return $id ? (int) $id : null;
    }

    /**
     * Dokumen terkendali + satu versi yang benar-benar diterbitkan.
     *
     * @param  string|null  $tanggalKadaluarsa  'Y-m-d'; null berarti tanpa masa berlaku
     */
    public function buatDokumenTerbit(): self
    {
        $konten = self::kontenDokumenCocok();
        if (! $konten) {
            throw new RuntimeException(
                'Tidak ada versi DOKUMEN terbit yang menautkan berkas di database ini, '
                .'sehingga tak ada contoh berkas bertipe cocok untuk membangun dunia uji.'
            );
        }

        $svc = app(SopDokumenService::class);
        $this->idDokumen = $svc->buat([
            'Judul' => $this->nama('Dokumen'),
            'Nomor_SOP_Resmi' => $this->tanda,
            'Tipe' => 'DOKUMEN',
            'Deskripsi' => 'Dibuat otomatis oleh uji UAT.',
        ], $this->userId);

        $this->idVersi = (int) $svc->tambahVersi($this->idDokumen, [
            'Id_Master_Konten' => $konten,
            'Ringkasan_Perubahan' => 'Versi awal dunia uji.',
            'Tanggal_Berlaku' => now()->subYear()->toDateString(),
            'Pengesah' => self::PENGESAH_UJI,
        ], $this->userId);

        $this->terbitkanVersiLewatTahap($svc, $konten);

        return $this;
    }

    /**
     * Jalankan versi melalui SELURUH tahap sampai terbit.
     *
     * terbitkanVersi() tidak lagi bisa dipanggil langsung dari DRAFTING: sejak
     * gerbang tahap dipasang, penerbitan hanya sah dari tahap yang memang
     * mengarah ke FILING_REGISTERED. Memanggilnya langsung — seperti yang
     * dilakukan fixture ini sebelumnya — membuat SELURUH uji UAT SOP mati di
     * baris pertama dengan sebab yang tak ada hubungannya dengan apa pun yang
     * sedang diuji.
     *
     * Urutan tahapnya sendiri TIDAK ditulis di sini melainkan di
     * UatTahapVersi::bawaKeAmbangTerbit(), yang juga dipakai berkas-berkas uji
     * yang membangun versinya tanpa dunia uji lengkap. Satu tempat, bukan dua
     * yang bisa menyimpang begitu master tahapnya berubah lagi.
     */
    private function terbitkanVersiLewatTahap(SopDokumenService $svc, int $konten): void
    {
        /* Pengesah dikirim ulang LENGKAP berikut Kode_Karyawan-nya, dan dikirim
           SEBELUM perjalanan tahap dimulai: syarat REALISASI_PENGESAH adalah
           syarat MASUK ke langkah Pemberkasan, jadi bloknya harus sudah berdiri
           saat versinya meninggalkan Pengesahan.

           Seluruh field identitas ikut dikirim, bukan hanya yang berubah —
           ubahVersi() memetakan sebagian besar kolom dengan `?? null`, sehingga
           muatan sebagian DIAM-DIAM mengosongkan sisanya. */
        $svc->ubahVersi($this->idVersi, [
            'Ringkasan_Perubahan' => 'Versi awal dunia uji.',
            'Tanggal_Berlaku' => now()->subYear()->toDateString(),
            'Pengesah' => $this->pengesahNyata(),
        ], $this->userId);

        UatTahapVersi::bawaKeAmbangTerbit($this->idVersi, $this->userId);

        // Berkas final dituntut pastikanIsiSah() pada langkah Pemberkasan.
        DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $this->idVersi)
            ->update(['Id_Master_Konten' => $konten]);

        $svc->terbitkanVersi($this->idVersi, $this->userId);
    }

    /**
     * Blok pengesah yang menunjuk karyawan SUNGGUHAN.
     *
     * "Disahkan oleh" diletakkan PERTAMA: SopDokumenService menyalin kolom
     * cermin Disahkan_Oleh dari baris pertama, apa pun perannya.
     */
    private function pengesahNyata(): array
    {
        $orang = DB::table('Karyawan')->where('Aktif', 'Y')
            ->orderBy('Kode_Karyawan')
            ->first(['Kode_Karyawan', 'Nama']);

        if (! $orang) {
            throw new RuntimeException('Tidak ada karyawan aktif untuk dijadikan pengesah dunia uji.');
        }

        return [
            [
                'Peran' => 'Disahkan oleh',
                'Nama' => $orang->Nama,
                'Jabatan' => 'Dept. Head',
                'Kode_Karyawan' => $orang->Kode_Karyawan,
            ],
            [
                'Peran' => 'Disusun oleh',
                'Nama' => $orang->Nama,
                'Jabatan' => 'Staff',
                'Kode_Karyawan' => $orang->Kode_Karyawan,
            ],
        ];
    }

    /**
     * Paket TAMBAHAN yang memakai dokumen yang sama.
     *
     * Inti skenario 043: satu dokumen yang dipakai banyak paket harus terbaca
     * konsisten. Sebelum Master Dokumen, tiap paket menyimpan salinan tata
     * kelolanya sendiri, dan salinan itu sudah menyimpang di data produksi —
     * satu dokumen dilaporkan KADALUARSA di satu paket dan TANPA_MASA_BERLAKU
     * di paket lain, pada saat yang sama.
     *
     * Section-nya ikut dikembalikan karena SopSectionService::byPaket()
     * MENG-HASH Id_SOP_Section sebelum mengembalikannya — meng-int-kan nilai itu
     * menghasilkan angka sampah yang diterima apa adanya oleh pemanggil.
     *
     * @return array{paket:int, section:int, item:int}
     */
    public function paketTambahan(string $label): array
    {
        $idPaket = app(SopPaketService::class)->create([
            'Nama_Paket' => $this->nama($label),
        ], $this->userId);

        $idSection = app(SopSectionService::class)->create($idPaket, [
            'Nama_Section' => $this->nama($label.' Section'),
        ], $this->userId);

        $idItem = app(SopItemService::class)->create($idPaket, [
            'Id_SOP_Section' => $idSection,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => $this->nama($label.' Item'),
            'Id_SOP_Dokumen' => $this->idDokumen,
            'Nomor_SOP_Resmi' => $this->tanda,
            'Flag_Wajib' => true,
        ], $this->userId);

        DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $idItem)
            ->update(['Id_Item_Versi_Aktif' => $this->idVersi]);

        return ['paket' => (int) $idPaket, 'section' => (int) $idSection, 'item' => (int) $idItem];
    }

    /**
     * @param  string  $modeUrutan  'SEKUENSIAL' menyalakan gerbang urutan; 'BEBAS' mematikannya
     * @param  bool  $wajibAbsensi  menyalakan gerbang presensi
     */
    public function buatPaket(string $modeUrutan = 'BEBAS', bool $wajibAbsensi = false): self
    {
        $this->idPaket = app(SopPaketService::class)->create([
            'Nama_Paket' => $this->nama('Paket'),
            'Mode_Urutan_Item' => $modeUrutan,
            'Flag_Absensi' => $wajibAbsensi,
        ], $this->userId);

        $this->idSection = app(SopSectionService::class)->create($this->idPaket, [
            'Nama_Section' => $this->nama('Section'),
        ], $this->userId);

        return $this;
    }

    /**
     * Naikkan paket ke status PUBLISHED.
     *
     * Dibutuhkan register kontrol dokumen: baseVersiAktif() menyaring
     * `p.Status = PUBLISHED`, sehingga dokumen yang hanya dipakai paket draft
     * tidak pernah muncul di register sama sekali. Itu benar sebagai perilaku —
     * paket draft belum menjadi kewajiban siapa pun — tapi memakainya sebagai
     * keadaan awal uji register membuat seluruh assertion membaca daftar kosong
     * dan lulus atau gagal karena alasan yang salah.
     *
     * TIDAK dipanggil di dunia untuk skenario gerbang urutan: tanpa snapshot
     * rilis, SopSequenceGate jatuh balik ke item hidup, dan itu jalur yang
     * memang ingin diuji di sana.
     */
    public function terbitkanPaket(): self
    {
        app(SopPaketService::class)->updateStatus(
            $this->idPaket, SopStatus::PAKET_PUBLISHED, $this->userId, 'Terbit dari dunia uji.');

        return $this;
    }

    /** Naikkan paket tambahan ke PUBLISHED. */
    public function terbitkanPaketLain(int $idPaket): self
    {
        app(SopPaketService::class)->updateStatus(
            $idPaket, SopStatus::PAKET_PUBLISHED, $this->userId, 'Terbit dari dunia uji.');

        return $this;
    }

    /** Tambah satu item dokumen ke paket. Urutan mengikuti urutan pemanggilan. */
    public function tambahItem(string $judul, bool $wajib = true): self
    {
        $id = app(SopItemService::class)->create($this->idPaket, [
            'Id_SOP_Section' => $this->idSection,
            'Tipe_Item' => 'DOKUMEN',
            'Judul_Item' => $this->nama($judul),
            'Id_SOP_Dokumen' => $this->idDokumen,
            'Nomor_SOP_Resmi' => $this->tanda,
            'Flag_Wajib' => $wajib,
        ], $this->userId);

        /* Penunjuk versi aktif item disamakan dengan versi dokumen yang terbit.
           Di aplikasi, penyelarasan ini dilakukan fan-out saat dokumen terbit;
           di sini itemnya lahir SESUDAH dokumen terbit, jadi tak ada fan-out
           yang menyusulinya. Membiarkannya kosong membuat pembacaan yang
           menjoin lewat penunjuk itu tak menemukan apa-apa. */
        DB::table('N_SOP_Paket_Item')
            ->where('Id_SOP_Paket_Item', $id)
            ->update(['Id_Item_Versi_Aktif' => $this->idVersi]);

        $this->idItem[] = (int) $id;

        return $this;
    }

    /** Tugaskan paket ke seorang karyawan. */
    public function tugaskanKe(string $kodeKaryawan): self
    {
        $this->kodeKaryawan = $kodeKaryawan;

        $hasil = app(SopPenjadwalanService::class)->assign(
            $this->idPaket,
            [$kodeKaryawan],
            ['nama_batch' => $this->nama('Batch'), 'sumber' => 'MANUAL'],
            $this->userId
        );

        $this->idPenjadwalan = (int) ($hasil['penjadwalan_id'] ?? 0);

        return $this;
    }

    /**
     * Tandai satu item sebagai sudah dituntaskan karyawan.
     *
     * Ditulis langsung ke N_SOP_Progress, bukan lewat konfirmasi(): yang sedang
     * diuji adalah GERBANG-nya, dan memakai jalur yang digerbangi untuk
     * menyiapkan keadaan awal membuat penyiapan itu sendiri ikut digerbangi —
     * ayam dan telur yang berakhir sebagai kegagalan penyiapan yang terbaca
     * seperti kegagalan gerbang.
     */
    /** Baris penugasan karyawan pada paket ini. */
    public function detailId(): int
    {
        $id = DB::table('N_SOP_Penjadwalan_Detail')
            ->where('Id_SOP_Penjadwalan', $this->idPenjadwalan)
            ->where('Kode_Karyawan', $this->kodeKaryawan)
            ->value('Id_SOP_Penjadwalan_Detail');

        if (! $id) {
            throw new RuntimeException('Penugasan belum terbentuk; tugaskanKe() harus dipanggil lebih dulu.');
        }

        return (int) $id;
    }

    public function tandaiSelesai(int $idItem): self
    {
        $detailId = $this->detailId();

        $ada = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $detailId)
            ->where('Id_SOP_Paket_Item', $idItem)
            ->value('Id_SOP_Progress');

        $ada
            ? DB::table('N_SOP_Progress')->where('Id_SOP_Progress', $ada)
                ->update(['Status' => SopStatus::ITEM_DIKONFIRMASI])
            : DB::table('N_SOP_Progress')->insert([
                'Id_SOP_Penjadwalan_Detail' => $detailId,
                'Id_SOP_Paket_Item' => $idItem,
                'Id_SOP_Item_Versi' => $this->idVersi,
                'Status' => SopStatus::ITEM_DIKONFIRMASI,
            ]);

        return $this;
    }
}
