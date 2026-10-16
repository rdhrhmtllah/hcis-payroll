<?php

namespace App\Http\Controllers\VISITOR\Guest\Visit;

use App\Http\Controllers\Controller;
use App\Http\Requests\VISITOR\Guest\Visit\AjukanTamuB2BApiRequest;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Guest\Visit\AturanFormTamu;
use App\Services\VISITOR\Guest\Visit\MatriksTamuService;
use App\Services\VISITOR\Guest\Visit\RegistrasiTamuService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Registrasi TAMU B2B untuk aplikasi mitra.
 *
 * Berbeda dari endpoint permit-tamu yang hanya menerbitkan izin gerbang, pintu
 * ini membuat baris kunjungan tamu yang sesungguhnya: ia masuk antrean approval
 * tamu, terlihat di meja verifikator, dan permit COKLAT-nya DIAJUKAN otomatis —
 * persis seperti tamu B2B yang didaftarkan lewat layar HCIS. Keduanya menulis
 * lewat RegistrasiTamuService supaya aturannya tidak bercabang.
 *
 * `status` menjawab SATU pertanyaan yang benar-benar ditanyakan mitra: boleh
 * tidak tamunya masuk. Ia karena itu GABUNGAN keadaan kunjungan dan keadaan
 * izin gerbangnya, bukan keadaan kunjungannya saja.
 *
 * Sebelumnya ia hanya keadaan kunjungan, dan karena registrasi tamu auto-approve
 * sementara permitnya menunggu verifikator, mitra membaca `APPROVED` lalu
 * mengirim tamunya — yang kemudian ditahan di palang. Dua status yang benar
 * masing-masing tetap menghasilkan satu kesimpulan yang salah, dan tidak ada
 * galat apa pun yang menandainya.
 *
 * Rinciannya tetap tersedia dan tidak hilang: `status_kunjungan` untuk keadaan
 * kunjungannya, `permit_coklat.status` untuk keadaan izinnya.
 *
 * Tiga hal dijaga ketat di sini:
 *
 *  1. Idempotensi — kiriman ulang karena jaringan putus tidak boleh melahirkan
 *     kunjungan kedua untuk tamu yang sama.
 *  2. Isolasi antar mitra pada PENULISAN — Id_Api_Aplikasi menentukan pemilik
 *     setiap registrasi yang dibuat lewat pintu ini.
 *
 *     Pembacaan status SENGAJA tidak disaring (keputusan user 2026-09-15):
 *     endpoint status melayani nomor kunjungan mana pun di HCIS, termasuk yang
 *     didaftarkan dari layar dan yang dimiliki mitra lain. Konsekuensinya
 *     diterima sadar — nama tamu, instansi, tanggal kunjungan, dan
 *     `referensi_mitra` milik satu pelanggan terbaca pelanggan lain yang
 *     menebak nomornya. Nomor transaksi berurutan, jadi menebaknya tidak sulit.
 *  3. Pertanggungjawaban — PIC diambil dari pendaftaran aplikasi, tidak pernah
 *     dari payload pemanggil.
 */
class TamuB2BApiController extends Controller
{
    public function __construct(
        private readonly VisitorPermitApiService $permitApi,
        private readonly RegistrasiTamuService $registrasiTamu,
    ) {}

    /**
     * Jejak pembuat yang terbaca manusia: "<nama> (API#<id>)".
     *
     * Dipotong pada 100 karakter mengikuti lebar kolomnya. Nama yang dipotong
     * masih berguna; penanda aplikasi yang hilang tidak - jadi yang dikorbankan
     * ekor namanya, bukan penandanya.
     */
    private function jejakPembuat(string $nama, int $idAplikasi): string
    {
        $penanda = ' ('.VisitorPermitApiService::PREFIX_PEMOHON_APLIKASI.$idAplikasi.')';
        $nama = trim($nama);

        if ($nama === '') {
            return ltrim($penanda, ' (');
        }

        return mb_substr($nama, 0, 100 - mb_strlen($penanda)).$penanda;
    }

    public function ajukan(AjukanTamuB2BApiRequest $request): JsonResponse
    {
        $idAplikasi = (int) $request->attributes->get('hris_api_app_id');

        try {
            $pemohon = $this->permitApi->identitasPemohon($idAplikasi);
        } catch (\DomainException $e) {
            return $this->galat($e->getMessage(), 422);
        }

        // Kunjungan mitra TIDAK punya karyawan pengaju (DDL-2026-135).
        //
        // Sebelumnya Kode_Karyawan_PIC diisi dari registry aplikasi, sehingga
        // seluruh kunjungan mitra tercatat atas nama satu orang yang tidak
        // pernah mengajukannya - dan dari layar mana pun ia terlihat seperti
        // orang itu yang mendaftarkan tamunya.
        //
        // Sekarang kodenya sengaja dibiarkan kosong, dan yang wajib justru
        // NAMA penanggung jawab dari mitra: gerbang butuh seseorang untuk
        // dihubungi, bukan kode karyawan.
        // SELALU kosong di pintu ini, bukan hanya ketika registry-nya belum
        // ditunjuk. Registry menyebut siapa yang MENANGANI integrasi, bukan
        // siapa yang mendaftarkan tamu - dan memakainya sebagai pendaftar
        // membuat satu orang terlihat mengajukan ratusan kunjungan yang tidak
        // pernah ia sentuh.
        $pic = null;

        // Registry TETAP wajib menunjuk seorang karyawan - bukan sebagai
        // pemilik kunjungan, melainkan sebagai PENJANGKAR approval.
        //
        // createFlow() menyusun rantai persetujuan dari hierarki seseorang.
        // Penanda 'API#<id>' bukan karyawan dan tidak punya atasan, sehingga
        // kunjungannya lahir tanpa satu pun langkah approval lalu menggantung
        // selamanya - diterima dengan 201, lalu tidak pernah bisa disetujui
        // siapa pun. Ditolak di sini supaya sebabnya terbaca saat pendaftaran,
        // bukan berminggu-minggu kemudian di meja verifikator.
        if (str_starts_with($pemohon['kode'], VisitorPermitApiService::PREFIX_PEMOHON_APLIKASI)) {
            return $this->galat(
                'Aplikasi ini belum menunjuk Kode_Karyawan_PIC. Registrasi tamu B2B membutuhkan '
                    .'seorang karyawan sebagai penjangkar alur persetujuan, walaupun kunjungannya '
                    .'sendiri dicatat tanpa pemilik.',
                422,
            );
        }

        $referensiMitra = trim((string) $request->input('referensi_mitra'));
        $kunciHeader = trim((string) $request->header('Idempotency-Key'));
        $kunci = $kunciHeader !== ''
            ? $kunciHeader
            : ($referensiMitra !== '' ? 'REF-'.$referensiMitra : 'API-'.Str::uuid());

        // Pemeriksaan replay mendahului batas kuota: kiriman ulang yang sah
        // tidak menambah apa pun, jadi menolaknya karena batas tercapai akan
        // menghukum mitra atas jaringannya sendiri yang putus.
        $ada = $this->registrasiTamu->cariIdempoten($pic, $kunci, $idAplikasi);

        if ($ada) {
            return $this->jawabanReplay($ada);
        }

        // Masa transisi: field yang nanti wajib dilaporkan, bukan ditolak.
        $peringatan = $this->peringatanTransisi($request);

        try {
            $hasil = $this->registrasiTamu->simpan([
                'kode_karyawan_pic' => $pic,
                'nama_penanggung_jawab' => $request->input('penanggung_jawab'),
                // Rantai approval tetap butuh orang dalam. PIC registry aplikasi
                // memang yang menangani integrasi ini, jadi dialah penjangkarnya
                // - tanpa menjadikannya seolah pendaftar tamunya.
                'kode_karyawan_approval' => $pemohon['kode'],
                // Nama penanggung jawab DAN penanda aplikasinya, mis.
                // "Budi Hartono (API#233)".
                //
                // Namanya lebih dulu karena itulah yang dicari orang saat
                // membuka barisnya; penandanya ikut supaya tak ada yang mengira
                // seorang karyawan bernama sama yang mengetik pengajuan ini.
                // Kode karyawan sengaja TIDAK dipakai - itu membuatnya terbaca
                // seolah orang itu yang mendaftarkan tamunya.
                //
                // Asal-usulnya sendiri tetap tersimpan utuh di Id_Api_Aplikasi;
                // kolom ini hanya jejak yang dibaca manusia.
                'dibuat_oleh' => $this->jejakPembuat(
                    (string) $request->input('penanggung_jawab'),
                    $idAplikasi,
                ),
                'idempotency_key' => $kunci,
                'id_api_aplikasi' => $idAplikasi,
                'referensi_mitra' => $referensiMitra !== '' ? $referensiMitra : null,
                // Tipe dipaku, bukan diambil dari payload: pintu ini memang
                // hanya melayani tamu mitra, dan tipe yang bisa dikarang
                // pemanggil berarti mitra dapat menghindari permit COKLAT.
                'tipe_tamu' => 'B2B',
                // Kunjungan dari aplikasi mitra selalu milik Procurement
                // (matriks departemen, DDL-2026-122).
                'b2b_unit' => MatriksTamuService::UNIT_PROCUREMENT,
                'nama_tamu' => $request->input('nama_tamu'),
                'instansi_asal' => $request->input('instansi'),
                'keperluan' => $request->input('keperluan'),
                'tanggal_kunjungan' => $request->input('tanggal_kunjungan'),
                // Estimasi Masuk / Keluar. Tanpa keduanya (transisi) penulis
                // memakai satu hari kalender dari tanggal_kunjungan.
                'waktu_mulai_kunjungan' => $request->input('estimasi_masuk'),
                'waktu_selesai_kunjungan' => $request->input('estimasi_keluar'),
                // Aturan kendaraan B2B dilonggarkan HANYA selama masa transisi.
                'longgar_b2b' => ! AjukanTamuB2BApiRequest::wajibLengkap(),
                'pic_tujuan' => $request->input('pic_tujuan') ?: $pemohon['nama'],
                'no_hp' => $request->input('no_hp'),
                'no_identitas' => $request->input('no_identitas'),
                'jenis_identitas' => $request->input('jenis_identitas'),
                'jenis_kendaraan' => $request->input('jenis_kendaraan'),
                // Plat dikirim UTUH; pemecahannya milik RegistrasiTamuService
                // supaya kedua pintu tamu memakai penafsir yang sama persis.
                'no_plat_kendaraan' => $request->input('no_plat_kendaraan'),
                'keterangan' => $request->input('keterangan'),
                'daftar_anggota' => (array) $request->input('daftar_anggota', []),
                // Manifes disimpan APA ADANYA. Nama tamu utama tidak disisipkan
                // ke dalamnya: mitra yang mengirim daftar kosong berarti tamunya
                // memang datang sendirian, dan membaca kembali sebuah nama yang
                // tidak pernah ia kirim akan dibacanya sebagai data yang kami
                // karang. Jumlah orangnya tetap benar - lihat jumlahOrang().
                'manifes_memuat_tamu_utama' => false,
            ]);
        } catch (\InvalidArgumentException|\DomainException $e) {
            // DomainException di sini berarti permit COKLAT-nya tidak dapat
            // diajukan — umumnya PIC aplikasi belum punya alur approval permit.
            // Itu keadaan yang bisa diperbaiki mitra/HR, bukan kegagalan server.
            return $this->galat($e->getMessage(), 422);
        } catch (QueryException $e) {
            // Dua kiriman berkunci sama yang tiba bersamaan sama-sama lolos
            // pemeriksaan replay; indeks unik idempotensi menolak yang kalah.
            // Itu replay, bukan galat server (audit ulang 28 Sep).
            $ada = in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)
                ? $this->registrasiTamu->cariIdempoten($pic, $kunci, $idAplikasi)
                : null;

            if ($ada) {
                return $this->jawabanReplay($ada);
            }

            Log::error('Gagal menyimpan registrasi tamu B2B dari mitra', [
                'id_api_aplikasi' => $idAplikasi,
                'error' => $e->getMessage(),
            ]);

            return $this->galat('Registrasi tamu gagal disimpan.', 500);
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan registrasi tamu B2B dari mitra', [
                'id_api_aplikasi' => $idAplikasi,
                'error' => $e->getMessage(),
            ]);

            return $this->galat('Registrasi tamu gagal disimpan.', 500);
        }

        // SESUDAH transaksi pengajuan selesai. Registrasi mitra sebelumnya tidak
        // memberitahu siapa pun: kunjungannya masuk antrean, permit COKLAT-nya
        // masuk antrean, dan keduanya menunggu tanpa ada yang tahu.
        $this->kabariAntrean($hasil, $referensiMitra, $request);

        $statusKunjungan = $hasil['needs_approval'] ? 'PENDING' : 'APPROVED';

        return $this->sukses('Registrasi tamu diterima', [
            'no_transaksi' => $hasil['no_transaksi'],
            // Permit yang baru saja diajukan selalu menunggu verifikator, jadi
            // kunjungan berpermit TIDAK PERNAH dibalas APPROVED di sini.
            'status' => $this->statusGabungan(
                $statusKunjungan,
                $hasil['no_transaksi_permit'] ? 'PENDING' : null,
            ),
            'status_kunjungan' => $statusKunjungan,
            // Nomor izin gerbangnya, terpisah dari nomor kunjungan. Mitra
            // memakainya untuk memantau persetujuan permit — lewat
            // GET /tamu-b2b/{no_transaksi} (permit_coklat.status) maupun
            // GET /permit-tamu/{no_transaksi_permit}.
            //
            // NULL berarti permitnya belum diajukan: kunjungannya sendiri masih
            // menunggu approval, atau tamunya bukan tipe berpermit.
            'no_transaksi_permit' => $hasil['no_transaksi_permit'],
            'referensi_mitra' => $referensiMitra !== '' ? $referensiMitra : null,
            'peringatan' => $peringatan,
        ], 201);
    }

    /**
     * Field yang kelak wajib tetapi belum dikirim. Kosong di mode wajib —
     * di sana ketiadaannya sudah ditolak 422 oleh FormRequest.
     *
     * @return list<string>
     */
    /** Balasan kiriman ulang berkunci sama, dengan keadaan kunjungan SAAT INI. */
    private function jawabanReplay(object $ada): JsonResponse
    {
        // Keadaannya DIBACA, bukan ditebak 'PENDING'. Kiriman ulang bisa
        // datang berhari-hari kemudian — mitra yang kehilangan balasan
        // pertamanya memakai jalur ini untuk menemukan kembali nomornya, dan
        // membalas PENDING atas izin yang sebenarnya sudah disetujui membuat
        // tamunya ditahan di luar gerbang oleh mitranya sendiri.
        $permitAda = $this->permitKunjungan($ada->No_Transaksi);
        $statusKunjunganAda = $this->statusUntukMitra($ada);

        return $this->sukses('Registrasi tamu sudah pernah diterima', [
            'no_transaksi' => $ada->No_Transaksi,
            'status' => $this->statusGabungan(
                $statusKunjunganAda,
                $permitAda ? $this->statusPermitUntukMitra($permitAda) : null,
            ),
            'status_kunjungan' => $statusKunjunganAda,
            // Kiriman ulang memulangkan nomor permit yang SAMA, bukan null:
            // mitra yang kehilangan balasan pertamanya memakai jalur ini
            // untuk menemukannya kembali, dan null di sini membuatnya
            // menyimpulkan permitnya tidak pernah ada.
            'no_transaksi_permit' => $permitAda->No_Transaksi ?? null,
            'referensi_mitra' => $ada->Referensi_Mitra,
            'idempotent_replay' => true,
        ], 200);
    }

    private function peringatanTransisi(AjukanTamuB2BApiRequest $request): array
    {
        if (AjukanTamuB2BApiRequest::wajibLengkap()) {
            return [];
        }

        $peringatan = [];

        if (blank($request->input('estimasi_masuk'))) {
            $peringatan[] = 'estimasi_masuk dan estimasi_keluar akan wajib; tanpa keduanya kunjungan dianggap berlangsung seharian pada tanggal_kunjungan.';
        }

        if (AturanFormTamu::tanpaKendaraan($request->input('jenis_kendaraan'))) {
            $peringatan[] = 'jenis_kendaraan akan wajib: tamu B2B harus berkendaraan.';
        }

        if (blank($request->input('no_plat_kendaraan'))) {
            $peringatan[] = 'no_plat_kendaraan akan wajib untuk tamu B2B.';
        }

        return $peringatan;
    }

    public function status(Request $request, string $no_transaksi): JsonResponse
    {
        $baris = $this->cariKunjungan($no_transaksi);

        if (! $baris) {
            return $this->galat('Registrasi tamu tidak ditemukan.', 404);
        }

        $permit = $this->permitKunjungan($baris->No_Transaksi);
        $statusKunjungan = $this->statusUntukMitra($baris);

        return $this->sukses('Status registrasi tamu', [
            'no_transaksi' => $baris->No_Transaksi,
            // GABUNGAN kunjungan + izin gerbang: inilah satu-satunya nilai yang
            // menjawab "boleh masuk atau tidak". Rinciannya di dua kunci di bawah.
            'status' => $this->statusGabungan(
                $statusKunjungan,
                $permit ? $this->statusPermitUntukMitra($permit) : null,
            ),
            'status_kunjungan' => $statusKunjungan,
            'alasan' => $baris->Alasan_Status,
            'nama_tamu' => $baris->Nama_Lengkap_Tamu,
            // Kolomnya datetime, tetapi yang berarti hanya tanggalnya. Membalas
            // '2026-10-10 00:00:00.000' di sebelah 'berlaku_dari: 2026-10-10'
            // memaksa mitra menebak dua bentuk tanggal dalam satu respons.
            'tanggal_kunjungan' => $this->tanggalSaja($baris->Tanggal_Kunjungan),
            'referensi_mitra' => $baris->Referensi_Mitra,
            // Asal pendaftarannya. Sejak endpoint ini melayani seluruh kunjungan
            // HCIS, pemanggil tidak lagi bisa menyimpulkan bahwa apa pun yang
            // dibalas 200 adalah miliknya sendiri — dan tanpa penanda ini ia
            // akan memperlakukan kunjungan orang lain sebagai kunjungannya.
            'sumber' => $this->sumberKunjungan($baris),
            // Permit COKLAT baru diajukan setelah kunjungan disetujui final, dan
            // baru berlaku setelah permitnya sendiri disetujui. NULL di sini
            // berarti belum diajukan, bukan gagal.
            'permit_coklat' => $permit ? [
                'no_transaksi' => $permit->No_Transaksi,
                'status' => $this->statusPermitUntukMitra($permit),
                'durasi' => $permit->Snapshot_Nama_Durasi ?: '1 Hari',
                'berlaku_dari' => $permit->Tanggal_Mulai_Berlaku,
                'berlaku_sampai' => $permit->Tanggal_Kadaluarsa,
            ] : null,
        ], 200);
    }

    /**
     * Terjemahkan keadaan internal ke kosakata yang dijanjikan ke mitra.
     *
     * Status_Kunjungan HCIS mengenal nilai yang hanya bermakna di dalam sistem
     * ('MENUNGGU_APPROVAL', 'TERJADWAL', 'CHECKED_IN'). Membocorkannya ke kontrak
     * publik memaksa mitra menangani nilai yang tidak pernah didokumentasikan,
     * dan mengunci kita dari mengubah nama status internal kelak.
     */
    private function statusUntukMitra(object $baris): string
    {
        if (($baris->Status_Efektif ?? null) === 'DIBATALKAN') {
            return 'REVOKED';
        }

        // Jendela kunjungannya sudah berakhir (pulang, tidak datang, atau keluar
        // tak tercatat). Dulu tetap APPROVED selamanya — padahal APPROVED
        // berarti tamunya boleh diberangkatkan (audit ulang 28 Sep).
        if (($baris->Status_Efektif ?? null) === 'EXPIRED') {
            return 'EXPIRED';
        }

        return match ($baris->Flag_Approval) {
            'Y' => 'APPROVED',
            'T' => 'REJECTED',
            default => 'PENDING',
        };
    }

    /**
     * Beritahu antrean verifikator atas kunjungan dan izin gerbangnya.
     *
     * Dua notifikasi berbeda kepada dua pool berbeda: kunjungan ke verifikator
     * tamu, permit COKLAT ke verifikator permit. Kegagalannya dicatat, tidak
     * dilempar — pengajuannya sudah ter-commit dan mitra sudah dijanjikan 201.
     *
     * @param  array<string,mixed>  $hasil
     */
    private function kabariAntrean(array $hasil, string $referensiMitra, AjukanTamuB2BApiRequest $request): void
    {
        $peristiwa = [];

        if ($hasil['needs_approval']) {
            $peristiwa[] = [
                'kind' => 'submission',
                // Tamu mitra selalu B2B → antrean ADMIN_TAMU_B2B.
                'domain' => VisitorNotificationService::DOMAIN_TAMU_B2B,
                'no_transaksi' => $hasil['no_transaksi'],
                'context' => [
                    'nama_pemohon' => 'Aplikasi mitra',
                    'nama_tamu' => $request->input('nama_tamu'),
                    'instansi' => $request->input('instansi'),
                    'tanggal_kunjungan' => $request->input('tanggal_kunjungan')
                        ?: substr((string) $request->input('estimasi_masuk'), 0, 10),
                ],
            ];
        }

        if (! empty($hasil['no_transaksi_permit'])) {
            $peristiwa[] = [
                'kind' => 'submission',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $hasil['no_transaksi_permit'],
                'context' => [
                    'nama_pemohon' => 'Aplikasi mitra',
                    'kategori' => 'COKLAT',
                    'plat_nomor' => trim((string) $request->input('no_plat_kendaraan')),
                ],
            ];
        }

        if ($peristiwa === []) {
            return;
        }

        try {
            PublishVisitorNotificationsJob::dispatch($peristiwa);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi registrasi tamu B2B', [
                'no_transaksi' => $hasil['no_transaksi'],
                'referensi_mitra' => $referensiMitra,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Keadaan izin gerbangnya, dalam kosakata yang sama dengan endpoint permit.
     *
     * Urutannya disengaja: pencabutan dan kedaluwarsa TIDAK menyentuh
     * Flag_Approval sama sekali, sehingga permit yang aksesnya sudah mati tetap
     * memenuhi syarat cabang APPROVED. Membaliknya membuat mitra menerima
     * "APPROVED" untuk izin yang sudah tidak berlaku.
     */
    /**
     * Izin gerbang milik satu kunjungan, atau null bila belum pernah diajukan.
     *
     * Dibaca dari TRANSAKSI permitnya, bukan dari proyeksi gerbang. Proyeksi
     * hanya memuat permit yang sudah hidup, sehingga membacanya saja membuat
     * permit yang masih menunggu verifikator tidak bisa dibedakan dari permit
     * yang tidak pernah diajukan — dan mitra menyimpulkan kami kehilangan
     * pengajuannya.
     */
    private function permitKunjungan(string $noTransaksiTamu): ?object
    {
        return DB::table('N_EMI_VISITOR_Transaksi_Permit as p')
            ->leftJoin('N_EMI_VISITOR_Transaksi_Permit_Detail as pd', 'pd.No_Transaksi', '=', 'p.No_Transaksi')
            ->leftJoin('N_VISITOR_Permit_Efektif as pe', 'pe.No_Transaksi', '=', 'p.No_Transaksi')
            ->where('p.No_Transaksi_Tamu', $noTransaksiTamu)
            ->where('p.Flag_Aktif', 'Y')
            ->first([
                'p.No_Transaksi',
                'p.Flag_Approval',
                'pe.Status_Efektif',
                // Status kanonik — sama dengan GET /permit-tamu/{no}. Tanpa ini
                // satu permit bisa dijawab EXPIRED di sana dan APPROVED di sini
                // selama penyapu belum jalan (audit 27 Sep 2026, H5).
                DB::raw(app(\App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::class)->permitStatusSql('p', 'pd').' as Status_Kanonik'),
                'pd.Snapshot_Nama_Durasi',
                'pd.Tanggal_Mulai_Berlaku',
                'pd.Tanggal_Kadaluarsa',
            ]);
    }

    /**
     * Satu status yang menjawab "boleh masuk atau tidak".
     *
     * Urutannya penting dan bukan sekadar mengambil yang terburuk:
     *
     *  - Kunjungan yang DITOLAK atau DICABUT mengakhiri segalanya, apa pun
     *    keadaan izinnya — izin untuk kunjungan yang tidak ada tidak berarti apa
     *    pun, dan membalas keadaan izinnya menyesatkan.
     *  - Kunjungan yang masih MENUNGGU juga belum bisa dinilai dari izinnya.
     *  - Baru ketika kunjungannya disetujui, izin gerbangnyalah yang menentukan.
     *  - Kunjungan tanpa izin sama sekali (tamu pejalan kaki / tipe REGULAR)
     *    dinilai dari dirinya sendiri; ketiadaan izin di situ bukan penahanan.
     *
     * @param  string|null  $statusPermit  null = belum pernah ada izin diajukan
     */
    private function statusGabungan(string $statusKunjungan, ?string $statusPermit): string
    {
        if ($statusKunjungan !== 'APPROVED' || $statusPermit === null) {
            return $statusKunjungan;
        }

        return $statusPermit;
    }

    private function tanggalSaja(?string $nilai): ?string
    {
        $bersih = trim((string) $nilai);

        return $bersih === '' ? null : substr(str_replace('T', ' ', $bersih), 0, 10);
    }

    private function statusPermitUntukMitra(object $permit): string
    {
        return \App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::statusUntukMitra($permit->Status_Kanonik ?? null);
    }

    /**
     * Kunjungan mana pun di HCIS — TIDAK disaring per aplikasi pemanggil.
     *
     * Sengaja terbuka atas permintaan user (2026-09-15), sejalan dengan
     * GET /permit-tamu/{no_transaksi} yang lebih dulu dilonggarkan. Alasannya
     * praktis: satu kunjungan sering dikerjakan dua pintu — didaftarkan lewat
     * layar HCIS lalu ditanyakan lewat API, atau sebaliknya — dan penyaring
     * kepemilikan membuat separuh kasus itu dibalas "tidak ditemukan" untuk
     * nomor yang jelas-jelas ada.
     *
     * Yang HILANG karenanya dicatat di sini supaya tidak ditemukan ulang sebagai
     * kejutan: endpoint ini tidak lagi memisahkan data antar pelanggan. Siapa pun
     * yang memegang kredensial API yang sah dan menebak nomor kunjungan — dan
     * nomornya berurutan — dapat membaca nama tamu, instansi, tanggal, serta
     * `referensi_mitra` milik pelanggan lain. Pelonggaran ini HANYA untuk
     * pembacaan; tidak ada pintu tulis di endpoint ini.
     *
     * Flag_Aktif tetap disaring: kunjungan yang sudah dihapus bukan "milik orang
     * lain", ia memang tidak ada lagi.
     */
    private function cariKunjungan(string $noTransaksi): ?object
    {
        return DB::table(RegistrasiTamuService::T_HEADER.' as h')
            ->leftJoin(RegistrasiTamuService::T_DETAIL.' as d', 'h.No_Transaksi', '=', 'd.No_Transaksi')
            ->leftJoin('N_VISITOR_Tamu_Efektif as e', 'h.No_Transaksi', '=', 'e.No_Transaksi')
            ->where('h.No_Transaksi', $noTransaksi)
            ->where('h.Flag_Aktif', 'Y')
            ->first([
                'h.No_Transaksi',
                'h.Flag_Approval',
                'h.Tanggal_Kunjungan',
                'h.Referensi_Mitra',
                'h.Sumber_Pengajuan',
                'h.Id_Api_Aplikasi',
                'd.Nama_Lengkap_Tamu',
                'e.Status_Efektif',
                'e.Alasan_Status',
            ]);
    }

    /** API bila didaftarkan aplikasi mitra, HCIS bila dari layar. */
    private function sumberKunjungan(object $baris): string
    {
        return ($baris->Id_Api_Aplikasi ?? null) !== null ? 'API' : 'HCIS';
    }

    /** @param array<string,mixed> $result */
    private function sukses(string $message, array $result, int $status): JsonResponse
    {
        // Kuncinya 'sukses', bukan 'success' — mengikuti pembungkus yang sudah
        // dipakai seluruh endpoint pihak ketiga dan ditegaskan uji kontraknya.
        return response()->json(['sukses' => true, 'message' => $message, 'result' => $result], $status);
    }

    private function galat(string $message, int $status): JsonResponse
    {
        return response()->json(['sukses' => false, 'message' => $message, 'result' => null], $status);
    }
}
