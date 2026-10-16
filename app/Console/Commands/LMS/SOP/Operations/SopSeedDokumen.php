<?php

namespace App\Console\Commands\LMS\SOP\Operations;

use App\Services\LMS\SOP\SopDokumenService;
use App\Services\LMS\SOP\SopTahapVersiService;
use App\Services\LMS\SOP\SopVersiReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Enam dokumen terkendali yang lahir dari alur input yang sesungguhnya.
 *
 * KENAPA ADA, DAN KENAPA BUKAN sop:seed-demo. Yang itu membangun DUNIA:
 * empat puluh dokumen, paket, penugasan, dan sejarah pembacaan — bentuk yang
 * dibutuhkan dashboard supaya grafiknya punya sesuatu untuk digambar. Ia
 * menandai setiap barisnya dengan awalan [DEMO] justru supaya tak seorang pun
 * salah mengira itu dokumen sungguhan.
 *
 * Yang di sini kebalikannya: SEDIKIT dokumen, tetapi setiap satunya lengkap
 * seperti berkas yang benar-benar masuk lewat layar — identitas penuh, tanggal
 * proses yang berurutan, catatan review per putaran, tiga penanda tangan yang
 * merujuk karyawan sungguhan, nomor pengesahan, berkas final, dan masa berlaku.
 * Tidak ada penanda peraga pada judulnya, sebab yang ingin diperlihatkan justru
 * bagaimana dokumen normal tampil di Master Dokumen.
 *
 * SELURUH SIKLUS LEWAT SERVICE, TAK SATU TAHAP DILOMPATI:
 *
 *   buat()                          → identitas dokumen + nomor resmi
 *   tambahVersi()                   → langkah Pengajuan
 *   pindah(REVIEW) + review->simpan → langkah Review
 *   pindah(APPROVAL) + ubahVersi()  → langkah Pengesahan
 *   pindah(APPROVED) + ubahVersi()  → langkah Pemberkasan
 *   terbitkanVersi()                → Filing and Registered
 *
 * Kalau satu penjaga menolak, seeding berhenti di dokumen itu dan menyebutkan
 * sebabnya — jadi perintah ini sekaligus pemeriksaan bahwa alurnya masih utuh.
 *
 * MUATAN ubahVersi() SELALU UTUH, bukan hanya field yang berubah. Service
 * memetakan sebagian besar kolom dengan `$data['X'] ?? null`, sehingga muatan
 * sebagian DIAM-DIAM MENGOSONGKAN sisanya. Karena itu satu larik penuh dirakit
 * sekali per dokumen (muatanVersi) dan dikirim ulang di setiap langkah.
 *
 * DUA TANGGAL SENGAJA TAK DIKIRIM. Tanggal_Kadaluarsa dan
 * Tanggal_Review_Berikutnya diturunkan SopMasaBerlakuService dari
 * Tanggal_Berlaku menurut master jenis dokumen (24 bulan dan 12 bulan).
 * Menuliskannya sendiri di sini berarti menyalin aturan yang sudah punya
 * pemilik, dan salinan itu akan diam-diam menyimpang begitu masternya diubah.
 *
 * BERKAS MEMAKAI YANG SUDAH ADA di Library Konten, dan boleh dipakai bersama
 * dokumen lain — itu keputusan sadar, bukan keterbatasan: perintah ini menyemai
 * catatan dokumen, bukan mengunggah naskah. Hanya konten ber-Scope 'SOP' yang
 * dipakai, sebab SopKontenViewService menolak selainnya saat berkas dibuka.
 *
 * PAGAR YANG DIPEGANG:
 *  - Jangkar penghapusan adalah JUDUL PERSIS di KATALOG, bukan pola awalan.
 *    Perintah ini tak pernah menyentuh baris yang tak berasal darinya.
 *  - Ditolak di production kecuali dipaksa.
 */
class SopSeedDokumen extends Command
{
    protected $signature = 'sop:seed-dokumen
        {--bersihkan : Hapus keenam dokumen ini lalu keluar}
        {--user=622 : Id_Users yang bertindak sebagai admin penginput}
        {--paksa : Izinkan berjalan di lingkungan production}';

    protected $description = 'Semai enam dokumen terkendali yang lengkap lewat alur input sesungguhnya';

    /**
     * Katalog dokumen.
     *
     * `jenis`, `apqc`, `dept`, `pemilik`, `lingkup`, dan `konten` ditulis
     * sebagai KODE, bukan id. Id berbeda di tiap environment, dan katalog yang
     * menghafal id akan menempelkan dokumen pada departemen yang salah di basis
     * lain tanpa satu pun galat.
     *
     * Sebarannya mengikuti organisasi yang benar-benar ada di basis ini:
     * kebijakan dan prosedur tata kelola dimiliki tingkat grup (EHG),
     * sedangkan prosedur operasional, instruksi kerja, dan standar teknis hidup
     * di perusahaan operasionalnya.
     */
    private const KATALOG = [
        [
            'judul' => 'Kebijakan Keamanan Pangan dan Mutu Terpadu',
            'deskripsi' => 'Menetapkan komitmen manajemen atas keamanan pangan, mutu produk, '
                .'dan kepatuhan terhadap peraturan pangan yang berlaku. Menjadi acuan tertinggi '
                .'bagi seluruh prosedur, instruksi kerja, dan standar teknis di lingkungan grup.',
            'jenis' => 'KEBIJAKAN',
            'apqc' => '11',
            'dept' => 'LGL',
            'pemilik' => 'EHG',
            'lingkup' => 'EHG',
            'konten' => 'SK HOL HC EHG 07 006 01 Istirahat Tahunan, Istirahat Tambahan, Cuti Bersama, dan CDT 3',
            'ringkasan' => '[Penyesuaian Kebijakan] Penerbitan awal kebijakan payung keamanan pangan '
                .'dan mutu terpadu, menggantikan pernyataan komitmen yang sebelumnya tersebar di '
                .'beberapa surat internal.',
            'nomor_pengesahan' => '014/SK-DIR/EHG/V/2026',
            'tanggal' => [
                'permintaan' => '2026-05-04',
                'respon' => '2026-05-15',
                'approval' => '2026-05-22',
                'pengesahan' => '2026-05-25',
                'berlaku' => '2026-06-01',
            ],
            'pengesah' => [
                ['peran' => 'Disahkan oleh', 'kode' => 'D12'],
                ['peran' => 'Diperiksa oleh', 'kode' => 'W60'],
                ['peran' => 'Disiapkan oleh', 'kode' => 'R47'],
            ],
            'review' => [
                [
                    'tanggal' => '2026-05-11',
                    'hasil' => '<p>Putaran pertama. Ruang lingkup kebijakan sudah mencakup seluruh '
                        .'entitas grup, namun rujukan ke standar acuan belum disebut eksplisit.</p>'
                        .'<ul><li>Tambahkan rujukan ke standar sistem manajemen keamanan pangan pada bagian Acuan.</li>'
                        .'<li>Pertegas bahwa kebijakan ini mengikat pemasok dan mitra alih daya.</li></ul>',
                ],
                [
                    'tanggal' => '2026-05-15',
                    'hasil' => '<p>Putaran kedua. Kedua catatan pada putaran pertama sudah diakomodasi. '
                        .'Tidak ada temuan tersisa.</p><p><strong>Kesimpulan:</strong> layak dilanjutkan ke pengesahan.</p>',
                ],
            ],
        ],
        [
            'judul' => 'Prosedur Pengendalian Dokumen dan Rekaman Mutu',
            'deskripsi' => 'Mengatur tata cara penomoran, penerbitan, distribusi, revisi, dan '
                .'penarikan dokumen terkendali beserta rekaman mutunya, termasuk masa simpan '
                .'dan pemusnahan rekaman.',
            'jenis' => 'PROSEDUR',
            'apqc' => '13',
            'dept' => 'IA',
            'pemilik' => 'EHG',
            'lingkup' => 'EHG',
            'konten' => 'CONTOH PDF',
            'ringkasan' => '[Pembaruan Alur] Penerbitan awal prosedur pengendalian dokumen setelah '
                .'penomoran resmi dipindahkan ke sistem. Alur permintaan revisi kini melewati '
                .'tahap review mutu sebelum pengesahan.',
            'nomor_pengesahan' => '021/SK-DIR/EHG/VI/2026',
            'tanggal' => [
                'permintaan' => '2026-05-18',
                'respon' => '2026-05-29',
                'approval' => '2026-06-05',
                'pengesahan' => '2026-06-08',
                'berlaku' => '2026-06-15',
            ],
            'pengesah' => [
                ['peran' => 'Disahkan oleh', 'kode' => 'N42'],
                ['peran' => 'Diperiksa oleh', 'kode' => 'S54'],
                ['peran' => 'Disiapkan oleh', 'kode' => 'F22'],
            ],
            'review' => [
                [
                    'tanggal' => '2026-05-27',
                    'hasil' => '<p>Alur revisi sudah jelas. Dua hal yang perlu ditegaskan:</p>'
                        .'<ol><li>Batas waktu penarikan salinan tercetak setelah revisi terbit.</li>'
                        .'<li>Siapa yang berwenang menyatakan sebuah dokumen tidak berlaku lagi.</li></ol>'
                        .'<p>Selebihnya sudah sesuai praktik yang berjalan.</p>',
                ],
            ],
        ],
        [
            'judul' => 'Prosedur Pengadaan Barang dan Jasa',
            'deskripsi' => 'Mengatur proses pengadaan mulai dari permintaan pembelian, seleksi dan '
                .'evaluasi pemasok, negosiasi harga, penerbitan pesanan pembelian, hingga '
                .'verifikasi penerimaan dan penyelesaian tagihan.',
            'jenis' => 'PROSEDUR',
            'apqc' => '04',
            'dept' => 'PROC',
            'pemilik' => 'ENB',
            'lingkup' => 'ENB',
            'konten' => 'Tutorial Login LIMS',
            'ringkasan' => '[Pembaruan Alur] Penerbitan awal. Ambang nilai persetujuan bertingkat '
                .'disesuaikan dengan struktur kewenangan terbaru, dan evaluasi pemasok menjadi '
                .'syarat sebelum pesanan pembelian diterbitkan.',
            'nomor_pengesahan' => '033/SK-DIR/ENB/VI/2026',
            'tanggal' => [
                'permintaan' => '2026-06-08',
                'respon' => '2026-06-19',
                'approval' => '2026-06-26',
                'pengesahan' => '2026-06-29',
                'berlaku' => '2026-07-01',
            ],
            'pengesah' => [
                ['peran' => 'Disahkan oleh', 'kode' => 'S54'],
                ['peran' => 'Diperiksa oleh', 'kode' => 'E15'],
                ['peran' => 'Disiapkan oleh', 'kode' => 'B8'],
            ],
            'review' => [
                [
                    'tanggal' => '2026-06-15',
                    'hasil' => '<p>Ambang nilai persetujuan sudah sesuai kewenangan yang berlaku. '
                        .'Catatan: pengadaan darurat di luar jam kerja belum punya jalur sendiri, '
                        .'sehingga saat ini ditempuh lewat persetujuan lisan yang tak terekam.</p>',
                ],
                [
                    'tanggal' => '2026-06-19',
                    'hasil' => '<p>Jalur pengadaan darurat sudah ditambahkan berikut kewajiban '
                        .'melengkapi dokumen dalam dua hari kerja. Tidak ada temuan tersisa.</p>',
                ],
            ],
        ],
        [
            'judul' => 'Prosedur Penerimaan dan Pemeriksaan Bahan Baku',
            'deskripsi' => 'Mengatur penerimaan bahan baku di gudang, pemeriksaan kesesuaian '
                .'dokumen pengiriman, pengambilan contoh untuk pengujian mutu, serta penanganan '
                .'bahan baku yang ditolak atau berstatus karantina.',
            'jenis' => 'PROSEDUR',
            'apqc' => '04',
            'dept' => 'PROD',
            'pemilik' => 'EMI',
            'lingkup' => 'EMI',
            'konten' => 'FILE BARU TESTING',
            'ringkasan' => '[Temuan Audit Mutu] Penerbitan awal sebagai tindak lanjut temuan audit '
                .'internal atas ketidakseragaman perlakuan bahan baku berstatus karantina antar '
                .'shift penerimaan.',
            'nomor_pengesahan' => '041/SK-DIR/EMI/VII/2026',
            'tanggal' => [
                'permintaan' => '2026-06-22',
                'respon' => '2026-07-03',
                'approval' => '2026-07-10',
                'pengesahan' => '2026-07-13',
                'berlaku' => '2026-07-20',
            ],
            'pengesah' => [
                ['peran' => 'Disahkan oleh', 'kode' => 'C9'],
                ['peran' => 'Diperiksa oleh', 'kode' => 'S55'],
                ['peran' => 'Disiapkan oleh', 'kode' => 'I27'],
            ],
            'review' => [
                [
                    'tanggal' => '2026-06-30',
                    'hasil' => '<p>Alur karantina sudah menjawab temuan audit. Perlu ditegaskan '
                        .'penandaan fisik area karantina dan siapa yang berhak melepasnya.</p>'
                        .'<ul><li>Label karantina wajib memuat nomor lot dan tanggal penerimaan.</li>'
                        .'<li>Pelepasan status hanya oleh penyelia mutu, bukan penyelia gudang.</li></ul>',
                ],
                [
                    'tanggal' => '2026-07-03',
                    'hasil' => '<p>Kedua catatan sudah masuk ke naskah. Prosedur dinyatakan siap disahkan.</p>',
                ],
            ],
        ],
        [
            'judul' => 'Instruksi Kerja Sanitasi dan Line Clearance Produksi Basah',
            'deskripsi' => 'Langkah teknis pembersihan mesin dan area lini produksi basah pada '
                .'pergantian batch dan pergantian shift, termasuk pemeriksaan line clearance '
                .'sebelum batch berikutnya dijalankan.',
            'jenis' => 'INSTRUKSI_KERJA',
            'apqc' => '04',
            'dept' => 'WFP',
            'pemilik' => 'EMI',
            'lingkup' => 'EMI',
            'konten' => 'CONTOH PDF',
            'ringkasan' => '[Pembaruan Alur] Penerbitan awal. Urutan pembersihan dipisahkan antara '
                .'pergantian batch sejenis dan pergantian batch berbeda alergen, karena keduanya '
                .'menuntut waktu bilas yang berbeda.',
            'nomor_pengesahan' => '052/SK-DIR/EMI/VII/2026',
            'tanggal' => [
                'permintaan' => '2026-07-06',
                'respon' => '2026-07-17',
                'approval' => '2026-07-24',
                'pengesahan' => '2026-07-27',
                'berlaku' => '2026-08-01',
            ],
            'pengesah' => [
                ['peran' => 'Disahkan oleh', 'kode' => 'C9'],
                ['peran' => 'Diperiksa oleh', 'kode' => 'F23'],
                ['peran' => 'Disiapkan oleh', 'kode' => 'A38'],
            ],
            'review' => [
                [
                    'tanggal' => '2026-07-14',
                    'hasil' => '<p>Langkah teknis sudah rinci dan dapat diikuti operator. '
                        .'Waktu bilas untuk pergantian batch berbeda alergen agar dicantumkan '
                        .'dalam menit, bukan sekadar "sampai bersih".</p>',
                ],
            ],
        ],
        [
            'judul' => 'Standar Waktu Tanggap Penanganan Gangguan Mesin Produksi',
            'deskripsi' => 'Menetapkan batas waktu tanggap dan waktu pulih untuk setiap kelas '
                .'gangguan mesin produksi, beserta jalur eskalasi bila batas waktu terlampaui.',
            'jenis' => 'STANDAR',
            'apqc' => '10',
            'dept' => 'ENM',
            'pemilik' => 'EMI',
            'lingkup' => 'EMI',
            'konten' => 'Report billing GCP Juli',
            'ringkasan' => '[Perubahan Struktur] Penerbitan awal. Kelas gangguan dibagi menjadi '
                .'tiga tingkat berdasarkan dampaknya terhadap lini, menggantikan pembagian dua '
                .'tingkat yang selama ini dipakai secara lisan.',
            'nomor_pengesahan' => '060/SK-DIR/EMI/VIII/2026',
            'tanggal' => [
                'permintaan' => '2026-07-20',
                'respon' => '2026-07-31',
                'approval' => '2026-08-07',
                'pengesahan' => '2026-08-10',
                'berlaku' => '2026-08-17',
            ],
            'pengesah' => [
                ['peran' => 'Disahkan oleh', 'kode' => 'C9'],
                ['peran' => 'Diperiksa oleh', 'kode' => 'H45'],
                ['peran' => 'Disiapkan oleh', 'kode' => 'A4'],
            ],
            'review' => [
                [
                    'tanggal' => '2026-07-28',
                    'hasil' => '<p>Pembagian tiga kelas gangguan sudah sejalan dengan pencatatan '
                        .'yang berjalan. Jalur eskalasi pada shift malam agar menyebut nama peran, '
                        .'bukan nama orang, supaya standar ini tak perlu direvisi setiap ada mutasi.</p>',
                ],
                [
                    'tanggal' => '2026-07-31',
                    'hasil' => '<p>Nama orang sudah diganti menjadi peran. Standar dinyatakan siap disahkan.</p>',
                ],
            ],
        ],
    ];

    /** Kode entitas => Id_Entitas */
    private array $entitas = [];

    /** Kode_Jenis => Id_SOP_Jenis_Dokumen */
    private array $jenis = [];

    /** Kode_Proses => Id_SOP_APQC */
    private array $apqc = [];

    /** Kode_Departemen => Id_Departemen */
    private array $departemen = [];

    /** Kode_Karyawan => {Nama, Jabatan} */
    private array $karyawan = [];

    /** Judul_Konten => Id_Master_Konten */
    private array $konten = [];

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('paksa')) {
            $this->error('Ditolak di production. Dokumen semaian akan tampil sebagai dokumen terkendali sungguhan.');
            $this->line('Gunakan --paksa hanya bila Anda benar-benar bermaksud demikian.');

            return self::FAILURE;
        }

        if ($this->option('bersihkan')) {
            return $this->bersihkan();
        }

        $userId = (int) $this->option('user');
        if ($userId <= 0 || ! DB::table('KPI_Users')->where('Id_Users', $userId)->exists()) {
            $this->error('Id_Users '.$userId.' tidak ada. Sebutkan admin yang sah lewat --user.');

            return self::FAILURE;
        }

        $adaSisa = DB::table('N_SOP_Dokumen')
            ->whereIn('Judul', array_column(self::KATALOG, 'judul'))->pluck('Judul');
        if ($adaSisa->isNotEmpty()) {
            $this->warn('Sebagian dokumen sudah ada ('.$adaSisa->count().'):');
            foreach ($adaSisa as $j) {
                $this->line('  · '.$j);
            }
            $this->line('Jalankan --bersihkan lebih dulu bila ingin membuatnya ulang.');

            return self::FAILURE;
        }

        if (($galat = $this->siapkanMaster()) !== null) {
            $this->error($galat);

            return self::FAILURE;
        }

        /* MASUK SEBAGAI ADMIN PENGINPUT, bukan sekadar mengirim id-nya.
           SopItemVersionService mengisi Kode_Karyawan_Publisher dari
           `Auth::user()?->karyawan?->Kode_Karyawan` — bukan dari $userId yang
           dioper. Tanpa sesi, keenam versi terbit dengan kolom penerbit KOSONG,
           dan register kontrol dokumen tak bisa menjawab siapa yang menerbitkan.
           Diamnya total: penerbitannya tetap berhasil. */
        if (! Auth::loginUsingId($userId)) {
            $this->error('Tak bisa masuk sebagai Id_Users '.$userId.'.');

            return self::FAILURE;
        }

        $kodePublisher = Auth::user()?->karyawan?->Kode_Karyawan;
        if (blank($kodePublisher)) {
            Auth::logout();
            $this->error('Id_Users '.$userId.' tak tertaut ke karyawan mana pun '
                .'(Karyawan.UserID_Web kosong), sehingga versi akan terbit tanpa penerbit.');
            $this->line('Pilih admin yang tertaut karyawan lewat --user.');

            return self::FAILURE;
        }

        $this->info('Menyemai '.count(self::KATALOG).' dokumen terkendali sebagai Id_Users '
            .$userId.' (penerbit '.$kodePublisher.').');
        $this->newLine();

        $stat = ['dokumen' => 0, 'versi' => 0, 'review' => 0, 'pengesah' => 0, 'terbit' => 0];

        /* DI LUAR satu transaksi besar, dan itu disengaja. Tiap penerbitan
           memanggil rekonsiliasi serta menulis jejaknya sendiri; membungkus
           enam siklus penuh dalam satu transaksi panjang menahan kunci pada
           tabel yang juga dibaca layar admin selama seeding berjalan — dan RCSI
           mati di basis ini, sehingga pembaca benar-benar menunggu.

           Konsekuensinya diterima: bila satu dokumen gagal di tengah, yang sudah
           jadi tetap ada. Itulah sebabnya --bersihkan berjangkar pada judul,
           bukan pada keberhasilan seluruh perintah. */
        try {
            foreach (self::KATALOG as $def) {
                $this->bangunSatu($def, $userId, $stat);
            }
        } finally {
            Auth::logout();
        }

        /* Cache analitik menyimpan hasil belasan menit. Tanpa ini, panel
           Kesehatan Dokumen masih menampilkan angka lama beberapa menit setelah
           seeding dan terbaca seperti gagal. */
        Cache::store('database')->flush();

        $this->newLine();
        $this->info('Selesai.');
        foreach ($stat as $k => $v) {
            $this->line('  '.str_pad($k, 10).$v);
        }
        $this->newLine();
        $this->line('Hapus kapan saja: php artisan sop:seed-dokumen --bersihkan');

        return self::SUCCESS;
    }

    /* ================= MASTER ================= */

    /**
     * Bahan yang harus ada sebelum apa pun dibuat.
     *
     * Ketiadaannya DILAPORKAN, bukan dilewati diam-diam: dokumen yang lahir
     * tanpa departemen atau tanpa pengesah tetap tersimpan, dan barisnya baru
     * ketahuan salah berhari-hari kemudian saat seseorang membuka layarnya.
     */
    private function siapkanMaster(): ?string
    {
        $this->jenis = DB::table('N_SOP_Master_Jenis_Dokumen')
            ->where('Flag_Aktif', 'Y')->pluck('Id_SOP_Jenis_Dokumen', 'Kode_Jenis')->all();
        $this->apqc = DB::table('N_SOP_Master_APQC')
            ->where('Flag_Aktif', 'Y')->pluck('Id_SOP_APQC', 'Kode_Proses')->all();
        $this->departemen = DB::table('N_HRIS_Master_Departemen')
            ->where('Flag_Aktif', 'Y')->pluck('Id_Departemen', 'Kode_Departemen')->all();
        $this->entitas = DB::table('N_HRIS_Master_Entitas')
            ->where('Flag_Aktif', 'Y')->pluck('Id_Entitas', 'Kode_Entitas')->all();

        foreach (self::KATALOG as $d) {
            foreach ([['jenis', $this->jenis, 'jenis dokumen'],
                ['apqc', $this->apqc, 'proses APQC'],
                ['dept', $this->departemen, 'departemen']] as [$kunci, $peta, $sebutan]) {
                if (! isset($peta[$d[$kunci]])) {
                    return 'Master '.$sebutan.' tak memuat kode "'.$d[$kunci].'" (dipakai '.$d['judul'].').';
                }
            }
            foreach (['pemilik', 'lingkup'] as $kunci) {
                if (! isset($this->entitas[$d[$kunci]])) {
                    return 'Master entitas tak memuat kode "'.$d[$kunci].'" (dipakai '.$d['judul'].').';
                }
            }
        }

        /* Penanda tangan ditarik dari MASTER KARYAWAN lewat kueri yang sama
           dengan SopPengesahPicker. Nama yang diketik lepas membuat blok
           pengesah tak tertaut ke siapa pun, dan itulah bedanya dokumen yang
           terlihat sungguhan dengan yang terlihat dikarang. */
        $kode = [];
        foreach (self::KATALOG as $d) {
            foreach ($d['pengesah'] as $p) {
                $kode[] = $p['kode'];
            }
        }

        $this->karyawan = DB::table('Karyawan as k')
            ->leftJoin('View_Golongan_Sub_Golongan_Level_Jabatan as v',
                'v.ID_Level_Jabatan', '=', 'k.ID_Level_Jabatan')
            ->where('k.Aktif', 'Y')
            ->whereIn('k.Kode_Karyawan', array_unique($kode))
            ->get(['k.Kode_Karyawan', 'k.Nama', 'v.nama_jabatan as Jabatan'])
            ->keyBy('Kode_Karyawan')
            ->all();

        $hilang = array_diff(array_unique($kode), array_keys($this->karyawan));
        if ($hilang !== []) {
            return 'Karyawan penanda tangan tak ditemukan atau tak aktif: '.implode(', ', $hilang).'.';
        }

        /* Berkas HANYA dari konten ber-Scope 'SOP'. SopKontenViewService
           menolak selainnya saat berkas dibuka, jadi menautkan konten LMS akan
           menghasilkan dokumen yang terbit tetapi berkasnya gagal dibuka —
           kegagalan yang baru terlihat di sisi karyawan.
           Judul kembar mungkin ada (satu berkas diunggah dua kali); yang mana
           pun dipakai, isinya sama, jadi id terkecil sudah cukup. */
        foreach (DB::table('N_LMS_Master_Konten')
            ->where('Flag_Aktif', 'Y')->where('Scope', 'SOP')->where('Tipe_Konten', 'document')
            ->orderBy('Id_Master_Konten')->get(['Id_Master_Konten', 'Judul_Konten']) as $k) {
            $this->konten[$k->Judul_Konten] ??= (int) $k->Id_Master_Konten;
        }

        foreach (self::KATALOG as $d) {
            if (! isset($this->konten[$d['konten']])) {
                return 'Library Konten (Scope SOP) tak memuat berkas berjudul "'.$d['konten'].'". '
                    .'Unggah berkasnya lebih dulu di menu Library Konten, atau sesuaikan katalog.';
            }
        }

        return null;
    }

    /* ================= SIKLUS SATU DOKUMEN ================= */

    private function bangunSatu(array $def, int $userId, array &$stat): void
    {
        $dok = app(SopDokumenService::class);
        $tahap = app(SopTahapVersiService::class);

        $identitas = [
            'Jenis_Dokumen_Id' => $this->jenis[$def['jenis']],
            'APQC_Id' => $this->apqc[$def['apqc']],
            'Departemen_Id' => $this->departemen[$def['dept']],
            'Entitas_Pemilik_Id' => $this->entitas[$def['pemilik']],
            'Entitas_Lingkup_Id' => $this->entitas[$def['lingkup']],
        ];

        /* Nomor urut diminta lewat jalur yang sama dengan layar. Tanpa ini
           dokumen lahir dengan No_Dokumen = 0, dan Nomor_SOP_Resmi hanya
           kebetulan unik selama tak ada dua dokumen berbagi jenis + entitas +
           departemen. Begitu ada, nomor resminya kembar. */
        $idDok = $dok->buat([
            'Judul' => $def['judul'],
            'Tipe' => 'DOKUMEN',
            'Deskripsi' => $def['deskripsi'],
            'No_Dokumen' => $dok->saranNoDokumen($identitas),
        ] + $identitas, $userId);
        $stat['dokumen']++;

        // ── Langkah 1: Pengajuan ────────────────────────────────────────────
        $muatan = $this->muatanVersi($def);

        $idVersi = $dok->tambahVersi($idDok, $muatan, $userId);
        if (! $idVersi) {
            $this->error('  gagal membuat versi untuk: '.$def['judul']);

            return;
        }
        $stat['versi']++;

        // ── Langkah 2: Review ───────────────────────────────────────────────
        $tahap->pindah($idVersi, 'REVIEW', $userId);
        $stat['review'] += app(SopVersiReviewService::class)->simpan(
            $idVersi,
            array_map(fn ($r, $i) => [
                'Review_Ke' => $i + 1,
                'Tanggal_Review' => $r['tanggal'],
                'Hasil_Review' => $r['hasil'],
            ], $def['review'], array_keys($def['review'])),
            $userId,
        );

        // ── Langkah 3: Pengesahan ───────────────────────────────────────────
        $tahap->pindah($idVersi, 'APPROVAL', $userId);

        $blok = $this->blokPengesah($def);
        $galat = $dok->ubahVersi($idVersi, $muatan + ['Pengesah' => $blok], $userId);
        if ($galat !== null) {
            $this->error('  pengesahan ditolak pada "'.$def['judul'].'": '.$galat);

            return;
        }
        $stat['pengesah'] += count($blok);

        // ── Langkah 4: Pemberkasan ──────────────────────────────────────────
        $tahap->pindah($idVersi, 'APPROVED', $userId);

        $galat = $dok->ubahVersi($idVersi, $muatan + [
            'Pengesah' => $blok,
            'Id_Master_Konten' => $this->konten[$def['konten']],
        ], $userId);
        if ($galat !== null) {
            $this->error('  pemberkasan ditolak pada "'.$def['judul'].'": '.$galat);

            return;
        }

        // ── Langkah 5: Tinjauan → terbit ────────────────────────────────────
        $dok->terbitkanVersi($idVersi, $userId, 'Y');
        $stat['terbit']++;

        $nomor = DB::table('N_SOP_Dokumen')->where('Id_SOP_Dokumen', $idDok)->value('Nomor_SOP_Resmi');
        $this->line('  terbit · '.($nomor ?: 'tanpa nomor').' · '.$def['judul']);
    }

    /**
     * Muatan versi yang UTUH, dirakit sekali dan dikirim ulang di tiap langkah.
     *
     * Alasannya ada di kepala berkas ini: ubahVersi() memetakan mayoritas kolom
     * dengan `?? null`, jadi muatan sebagian mengosongkan sisanya tanpa satu pun
     * peringatan. Yang sengaja TIDAK ada di sini cuma dua tanggal turunan masa
     * berlaku — keduanya milik SopMasaBerlakuService.
     */
    private function muatanVersi(array $def): array
    {
        return [
            'Ringkasan_Perubahan' => $def['ringkasan'],
            'Tanggal_Permintaan' => $def['tanggal']['permintaan'],
            'Tanggal_Respon' => $def['tanggal']['respon'],
            'Tanggal_Akhir_Approval' => $def['tanggal']['approval'],
            'Tanggal_Pengesahan' => $def['tanggal']['pengesahan'],
            'Nomor_Pengesahan' => $def['nomor_pengesahan'],
            'Tanggal_Berlaku' => $def['tanggal']['berlaku'],
            'Wajib_Baca_Ulang' => 'Y',
        ];
    }

    /**
     * Blok penanda tangan dari karyawan sungguhan.
     *
     * "Disahkan oleh" WAJIB berada di urutan pertama, dan itu bukan soal selera:
     * SopDokumenService menyalin kolom cermin Disahkan_Oleh / Jabatan_Pengesah /
     * Kode_Karyawan_Pengesah dari `$blok[0]` — baris pertama, apa pun perannya.
     * Menaruh penyiap di depan membuat register kontrol dokumen menyebut PENYIAP
     * sebagai pengesahnya, dan tak ada galat apa pun yang menandainya.
     *
     * @return array<int, array<string, ?string>>
     */
    private function blokPengesah(array $def): array
    {
        return array_map(function (array $p) {
            $k = $this->karyawan[$p['kode']];

            return [
                'Peran' => $p['peran'],
                'Nama' => $k->Nama,
                'Jabatan' => $k->Jabatan,
                'Kode_Karyawan' => $k->Kode_Karyawan,
            ];
        }, $def['pengesah']);
    }

    /* ================= PEMBERSIHAN ================= */

    /**
     * Hapus keenam dokumen ini beserta seluruh anaknya.
     *
     * Berjangkar pada JUDUL PERSIS, bukan pola awalan: perintah ini tak punya
     * penanda pada judulnya — itu justru inti gunanya — sehingga satu-satunya
     * jangkar yang aman adalah daftar judul yang ia sendiri tulis.
     */
    private function bersihkan(): int
    {
        $judul = array_column(self::KATALOG, 'judul');
        $dokumen = DB::table('N_SOP_Dokumen')->whereIn('Judul', $judul)->pluck('Id_SOP_Dokumen');

        if ($dokumen->isEmpty()) {
            $this->info('Tak ada dokumen semaian yang perlu dihapus.');

            return self::SUCCESS;
        }

        /* Dipakai paket berarti dipakai orang. Menghapusnya akan meninggalkan
           item paket yang menunjuk versi yang tak ada lagi, dan layar karyawan
           menampilkan baris kosong tanpa sebab yang bisa ditelusuri. */
        $dipakai = DB::table('N_SOP_Paket_Item')->whereIn('Id_SOP_Dokumen', $dokumen)->count();
        if ($dipakai > 0) {
            $this->error('Dibatalkan: '.$dipakai.' item paket masih merujuk dokumen ini.');
            $this->line('Lepaskan dulu dari paketnya lewat Document Builder, baru jalankan lagi.');

            return self::FAILURE;
        }

        $stat = [];
        DB::transaction(function () use ($dokumen, &$stat) {
            $versi = DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Dokumen', $dokumen)
                ->pluck('Id_SOP_Item_Versi');

            /* Pointer versi aktif dilepas LEBIH DULU: N_SOP_Dokumen.Id_Versi_Aktif
               menunjuk baris versi yang hendak dihapus, jadi menghapus versi
               duluan akan ditolak kunci asing. */
            DB::table('N_SOP_Dokumen')->whereIn('Id_SOP_Dokumen', $dokumen)
                ->update(['Id_Versi_Aktif' => null]);

            DB::table('N_SOP_Item_Versi_Pengesah')->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            DB::table('N_SOP_Item_Versi_Review_Lampiran')
                ->whereIn('Id_Review', DB::table('N_SOP_Item_Versi_Review')
                    ->whereIn('Id_SOP_Item_Versi', $versi)->select('Id_Review'))->delete();
            $stat['review'] = DB::table('N_SOP_Item_Versi_Review')
                ->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            DB::table('N_SOP_Item_Versi_Berkas')->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            $stat['versi'] = DB::table('N_SOP_Item_Versi')->whereIn('Id_SOP_Item_Versi', $versi)->delete();
            DB::table('N_SOP_Log')->whereIn('Id_SOP_Dokumen', $dokumen)->delete();
            $stat['dokumen'] = DB::table('N_SOP_Dokumen')->whereIn('Id_SOP_Dokumen', $dokumen)->delete();
        });

        Cache::store('database')->flush();

        $this->info('Dokumen semaian dihapus.');
        foreach ($stat as $k => $v) {
            $this->line('  '.str_pad($k, 10).$v);
        }

        return self::SUCCESS;
    }
}
