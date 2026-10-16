<?php

namespace App\Http\Controllers\VISITOR\Permit\VehiclePermit;

use App\Http\Controllers\Controller;
use App\Http\Requests\VISITOR\Permit\VehiclePermit\AjukanPermitApiRequest;
use App\Jobs\VISITOR\Workflow\Notification\PublishVisitorNotificationsJob;
use App\Services\VISITOR\Operations\Expiration\VisitorEfektifService;
use App\Services\VISITOR\Permit\VehiclePermit\PermitNomorTransaksi;
use App\Services\VISITOR\Permit\VehiclePermit\PlatNomorKendaraan;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitApiService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitChecklistService;
use App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService;
use App\Services\VISITOR\Workflow\Approval\VisitorApprovalPolicyService;
use App\Services\VISITOR\Workflow\Notification\VisitorNotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Endpoint permit COKLAT untuk aplikasi mitra B2B.
 *
 * Ini endpoint TULIS pertama di lapisan N_HRIS_Api_*; seluruh konsumen
 * sebelumnya hanya membaca atau menerima callback. Tiga hal karena itu dijaga
 * ketat di sini:
 *
 *  1. Idempotensi — kiriman ulang karena jaringan putus tidak boleh melahirkan
 *     izin gerbang kedua untuk tamu yang sama.
 *  2. Isolasi antar mitra — Id_Api_Aplikasi menyaring setiap pembacaan; nomor
 *     milik mitra lain dibalas seperti nomor yang tidak ada.
 *  3. Pertanggungjawaban — PIC diambil dari pendaftaran aplikasi, tidak pernah
 *     dari payload pemanggil.
 *
 * Permit yang terbit di sini TIDAK langsung aktif: ia menunggu persetujuan
 * verifikator, dan mitra memantau keputusannya lewat endpoint status.
 *
 * Spec: docs/superpowers/specs/2026-09-08-api-b2b-permit-coklat-design.md
 */
class PermitTamuApiController extends Controller
{
    public function __construct(
        private readonly VisitorPermitApiService $permitApi,
        private readonly VisitorApprovalPolicyService $visitorApprovalPolicy,
        private readonly PermitNomorTransaksi $nomorTransaksi,
        private readonly VisitorPermitQueryService $permitQuery,
        private readonly VisitorEfektifService $visitorEfektif,
        private readonly PlatNomorKendaraan $platNomor,
        private readonly VisitorPermitChecklistService $permitChecklist,
    ) {}

    public function ajukan(AjukanPermitApiRequest $request): JsonResponse
    {
        $idAplikasi = (int) $request->attributes->get('hris_api_app_id');
        $kunciHeader = trim((string) $request->header('Idempotency-Key'));
        $referensiMitra = trim((string) $request->input('referensi_mitra'));
        $kunci = $kunciHeader !== ''
            ? $kunciHeader
            : ($referensiMitra !== '' ? 'REF-'.$referensiMitra : 'API-'.Str::uuid());

        try {
            // Pemohon permit mitra adalah APLIKASINYA, kecuali pendaftarannya
            // menunjuk seorang PIC karyawan. Keduanya sah; yang tidak sah adalah
            // aplikasi yang tidak terdaftar sama sekali.
            $pemohon = $this->permitApi->identitasPemohon($idAplikasi);
        } catch (\DomainException $e) {
            return $this->galat($e->getMessage(), 422);
        }

        $pic = $pemohon['kode'];

        // Pemeriksaan replay mendahului batas kuota: kiriman ulang yang sah
        // tidak menambah apa pun, jadi menolaknya karena batas tercapai akan
        // menghukum mitra atas jaringannya sendiri yang putus.
        if ($replay = $this->jawabanReplay($idAplikasi, $kunci)) {
            return $replay;
        }

        if ($this->permitApi->melampauiBatas($idAplikasi)) {
            return $this->galat(
                'Batas permit aktif aplikasi ini sudah tercapai ('
                    .VisitorPermitApiService::MAKS_PERMIT_AKTIF_PER_APLIKASI
                    .'). Jatah kembali setelah permit ditolak, dibatalkan, atau kedaluwarsa.',
                429,
            );
        }

        $kategori = DB::table('N_EMI_VISITOR_Master_Kategori_Permit')
            ->where('Nama_Kategori', 'COKLAT')
            ->where('Flag_Aktif', 'Y')
            ->first();

        if (! $kategori) {
            return $this->galat('Master kategori permit COKLAT tidak tersedia.', 503);
        }

        $noTransaksi = $this->nomorTransaksi->buat('PMT');
        $plat = $this->platNomor->pecah(
            (string) ($request->input('no_plat_kendaraan') ?? $request->input('no_plat')),
            $request->input('plat_prefix'),
            $request->input('plat_angka'),
            $request->input('plat_suffix'),
        );

        // Diperiksa sebelum transaksi dibuka: bagian yang kepanjangan akan
        // gagal saat insert, dan galat truncate SQL Server tidak menyebut
        // field mana pun — mitra hanya menerima "gagal disimpan".
        if ($bagian = $this->platNomor->muat($plat, PlatNomorKendaraan::LEBAR_PERMIT)) {
            return $this->galat(
                "Bagian plat nomor '{$bagian}' melebihi ".PlatNomorKendaraan::LEBAR_PERMIT.' karakter.',
                422,
            );
        }

        try {
            $this->simpanPengajuan($request, $idAplikasi, $kunci, $pic, $kategori, $noTransaksi, $plat);
        } catch (QueryException $e) {
            // Dua kiriman berkunci sama yang tiba bersamaan sama-sama lolos
            // pemeriksaan replay; indeks unik idempotensi menolak yang kalah.
            // Itu replay, bukan galat server (audit VMS 27 Sep, Low).
            $replay = in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)
                ? $this->jawabanReplay($idAplikasi, $kunci)
                : null;

            if (! $replay) {
                throw $e;
            }

            return $replay;
        }

        // SESUDAH transaksi pengajuan selesai. Pengajuan mitra sebelumnya masuk
        // antrean verifikator tanpa satu pun notifikasi — menunggu sampai ada
        // yang kebetulan membuka mejanya.
        try {
            PublishVisitorNotificationsJob::dispatch([[
                'kind' => 'submission',
                'domain' => VisitorNotificationService::DOMAIN_PERMIT,
                'no_transaksi' => $noTransaksi,
                'context' => [
                    'nama_pemohon' => $pemohon['nama'],
                    'kategori' => $kategori->Nama_Kategori,
                    'plat_nomor' => trim($plat['prefix'].' '.$plat['angka'].' '.$plat['suffix']),
                ],
            ]]);
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi pengajuan permit mitra', [
                'no_transaksi' => $noTransaksi,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->sukses('Pengajuan permit diterima', [
            'no_transaksi' => $noTransaksi,
            'status' => 'PENDING',
            'referensi_mitra' => $request->input('referensi_mitra'),
        ], 201);
    }

    /** Header, detail, checklist, dan alur approval pengajuan mitra dalam satu transaksi. */
    private function simpanPengajuan(
        AjukanPermitApiRequest $request,
        int $idAplikasi,
        string $kunci,
        string $pic,
        object $kategori,
        string $noTransaksi,
        array $plat,
    ): void {
        DB::transaction(function () use ($request, $idAplikasi, $kunci, $pic, $kategori, $noTransaksi, $plat) {
            $headerId = DB::table('N_EMI_VISITOR_Transaksi_Permit')->insertGetId([
                'No_Transaksi' => $noTransaksi,
                'Kode_Karyawan_Pemohon' => $pic,
                'Flag_Aktif' => 'Y',
                'Flag_Approval' => null,
                'Sumber_Pengajuan' => 'API',
                'Id_Api_Aplikasi' => $idAplikasi,
                'Referensi_Mitra' => $request->input('referensi_mitra'),
                'Created_At' => now(),
                'Created_By' => $pic,
                'Updated_At' => now(),
                'Updated_By' => $pic,
                'Idempotency_Key' => $kunci,
            ]);

            $idDetail = DB::table('N_EMI_VISITOR_Transaksi_Permit_Detail')->insertGetId([
                'Id_Transaksi_Permit' => $headerId,
                'No_Transaksi' => $noTransaksi,
                'Plat_Nomor_Prefix' => $plat['prefix'],
                'Plat_Nomor_Angka' => $plat['angka'],
                'Plat_Nomor_Suffix' => $plat['suffix'],
                'Id_Master_Kategori_Permit' => $kategori->Id_Kategori_Permit,
                'Snapshot_Kategori_Permit' => $kategori->Nama_Kategori,
                'Id_Master_Durasi' => null,
                'Snapshot_Nama_Durasi' => null,
                'Keterangan_Pengajuan' => $request->input('keperluan'),
                'Nama_Tamu' => $request->input('nama_tamu'),
                'Instansi_Tamu' => $request->input('instansi'),
                'No_Identitas_Tamu' => $request->input('no_identitas'),
                'Jenis_Identitas_Tamu' => $request->input('jenis_identitas'),
                // Teks bebas dari mitra. Tanpa kolom ini gerbang tidak punya
                // satu pun petunjuk kendaraan apa yang datang, dan penjaga
                // inspeksi membaca permit berplat sebagai pejalan kaki.
                'Jenis_Kendaraan_Tamu' => $request->input('jenis_kendaraan'),
                // Masa berlakunya SENGAJA kosong. Mitra tidak tahu kapan
                // tamunya benar-benar tiba dan sampai kapan ia diizinkan berada
                // di area; yang tahu adalah verifikator yang memeriksanya di
                // pos, dan ia yang menetapkannya saat menyetujui. Permit tidak
                // akan lolos approval selama jendela ini masih kosong.
                'Waktu_Mulai_Berlaku' => null,
                'Waktu_Berakhir' => null,
                'Tanggal_Mulai_Berlaku' => null,
                'Tanggal_Kadaluarsa' => null,
            ]);

            // Inspeksi fisik kendaraan tamu adalah tugas verifikator, jadi
            // pertanyaannya sudah menunggu di mejanya sejak pengajuan diterima.
            // Tipe kendaraannya sengaja tidak dikirim: mitra hanya mengirim teks
            // bebas, dan verifikator yang menetapkan modelnya saat review.
            //
            // Tamu pejalan kaki tidak membawa apa pun untuk diperiksa.
            if ($this->permitQuery->wajibInspeksiKendaraan($noTransaksi)) {
                $this->permitChecklist->buatSnapshot(
                    $noTransaksi,
                    (int) $idDetail,
                    (int) $kategori->Id_Kategori_Permit,
                );
            }

            // Tanpa unggahan dokumen dari mitra dan tanpa pemeriksaan kuota
            // karyawan: keduanya mesin permit karyawan yang tidak berlaku bagi
            // pengajuan mitra. Dokumennya diunggah verifikator di meja, bukan
            // dikirim lewat API.
            $this->visitorApprovalPolicy->createFlow('permit', $noTransaksi, $pic);
        });
    }

    /**
     * Jawaban untuk kiriman ulang berkunci sama, dengan status permit SAAT INI.
     * Dulu selalu PENDING: mitra yang mengulang sesudah permitnya disetujui
     * atau dibatalkan menerima keadaan yang sudah tidak benar.
     */
    private function jawabanReplay(int $idAplikasi, string $kunci): ?JsonResponse
    {
        $no = DB::table('N_EMI_VISITOR_Transaksi_Permit')
            ->where('Id_Api_Aplikasi', $idAplikasi)
            ->where('Idempotency_Key', $kunci)
            ->value('No_Transaksi');

        if (! $no) {
            return null;
        }

        return $this->sukses('Pengajuan permit sudah pernah diterima', [
            'no_transaksi' => $no,
            'status' => $this->statusUntukMitra($this->cariPermit($no)?->Status_Permit),
            'idempotent_replay' => true,
        ], 200);
    }

    /**
     * Pengecekan status permit APA PUN, bukan hanya permit tamu mitra.
     *
     * Keputusan user 2026-09-14: satu pintu untuk mengecek keadaan sebuah nomor
     * permit, apa pun sumbernya — permit kendaraan karyawan, permit mitra, dan
     * permit COKLAT turunan registrasi tamu B2B.
     *
     * Yang dipulangkan sengaja TETAP seperti semula dan tidak bertambah satu
     * kolom identitas pun: nomor, status, tanggal berlaku, durasi, sumber.
     * Tidak ada nama, NIK, departemen, atau plat. Melonggarkan cakupannya boleh;
     * melonggarkan ISInya berarti data pribadi karyawan mengalir ke aplikasi
     * pihak ketiga, dan itu tidak akan terlihat sebagai galat apa pun.
     *
     * Pembatalan TIDAK ikut dilonggarkan — lihat batal() di bawah.
     */
    public function status(Request $request, string $no_transaksi): JsonResponse
    {
        $baris = $this->cariPermit($no_transaksi);

        if (! $baris) {
            return $this->galat('Permit tidak ditemukan.', 404);
        }

        return $this->sukses('Status permit', [
            'no_transaksi' => $baris->No_Transaksi,
            'status' => $this->statusUntukMitra($baris->Status_Permit),
            'alasan' => $this->alasanUntukMitra($baris),
            'tanggal_berlaku' => $baris->Tanggal_Mulai_Berlaku,
            'durasi' => $baris->Nama_Durasi ?? '1 Hari',
            // Pemanggil kini bisa menerima tiga jenis permit lewat satu pintu,
            // jadi ia butuh cara membedakannya tanpa menebak dari nomornya.
            'sumber' => $this->sumberPermit($baris),
            // Hanya terisi pada permit yang memang diajukan mitra; permit
            // karyawan tidak punya referensi apa pun.
            'referensi_mitra' => $baris->Referensi_Mitra,
        ], 200);
    }

    /**
     * Pembatalan HANYA untuk permit milik aplikasi pemanggil.
     *
     * Sengaja tidak ikut dilonggarkan bersama status(): membaca keadaan sebuah
     * nomor tidak merugikan siapa pun, sedangkan mencabut izin akses fisik milik
     * orang lain adalah kerusakan yang baru ketahuan ketika pemiliknya berdiri
     * di gerbang.
     */
    public function batal(Request $request, string $no_transaksi): JsonResponse
    {
        $baris = $this->permitMitra($request, $no_transaksi);

        if (! $baris) {
            return $this->galat('Permit tidak ditemukan.', 404);
        }

        $alasan = 'Dibatalkan aplikasi mitra.';

        DB::transaction(function () use ($baris, $alasan) {
            // Selagi PENDING: menutup alur persetujuannya supaya pengajuannya
            // tidak menggantung selamanya di antrean verifikator.
            DB::table('N_EMI_VISITOR_Transaksi_Permit')
                ->where('No_Transaksi', $baris->No_Transaksi)
                ->whereNull('Flag_Approval')
                ->update(['Flag_Approval' => 'T', 'Updated_At' => now()]);

            DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $baris->No_Transaksi)
                ->whereNull('Flag_Approval')
                ->update([
                    'Flag_Approval' => 'T',
                    'Catatan' => $alasan,
                    'Tanggal_Approval' => now(),
                    'update_at' => now(),
                ]);
        });

        // Sesudah APPROVED: inilah yang benar-benar mematikan aksesnya di
        // gerbang. No-op bila permitnya belum pernah sampai ke proyeksi.
        $this->visitorEfektif->revokePermitEfektif(
            $baris->No_Transaksi,
            $alasan,
            (string) $baris->Kode_Karyawan_Pemohon,
        );

        return $this->sukses('Permit dibatalkan', ['no_transaksi' => $baris->No_Transaksi], 200);
    }

    /**
     * Terjemahkan status internal ke kosakata yang dijanjikan ke mitra.
     *
     * permitStatusSql() mengenal dua status yang hanya bermakna di dalam HCIS:
     *
     *  - NO_FLOW — belum ada satu pun langkah approval yang terbentuk, biasanya
     *    karena konfigurasi approver belum lengkap.
     *  - VALIDITY_REQUIRED — sudah disetujui tetapi masa berlakunya belum
     *    ditetapkan.
     *
     * Keduanya berarti hal yang sama bagi mitra: keputusannya belum ada dan
     * izinnya belum berlaku di gerbang. Membocorkan kosakata internal ke
     * kontrak publik memaksa mitra menangani nilai yang tidak pernah
     * didokumentasikan — dan menutup pintu bagi kita untuk mengubah nama status
     * internal tanpa merusak sistem orang lain.
     */
    private function statusUntukMitra(?string $internal): string
    {
        return \App\Services\VISITOR\Permit\VehiclePermit\VisitorPermitQueryService::statusUntukMitra($internal);
    }

    /** Satu nomor permit, apa pun sumber dan pemiliknya. */
    private function cariPermit(string $noTransaksi): ?object
    {
        return $this->permitQuery->basePermitQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->first();
    }

    /**
     * Alasan yang dijanjikan spec untuk REJECTED / REVOKED.
     *
     * - REVOKED: alasan pencabutan di proyeksi gerbang.
     * - REJECTED: catatan langkah approval yang menolak — termasuk penutupan
     *   otomatis (COKLAT kunjungan berlalu, gugur tidak hadir) yang menulisnya
     *   ke sana. Dulu selalu null (audit M9).
     * Permit KARYAWAN tidak membuka catatan internal verifikator ke mitra mana
     * pun: endpoint ini sengaja melayani semua nomor (audit M2).
     */
    private function alasanUntukMitra(object $baris): ?string
    {
        if ($this->sumberPermit($baris) === 'KARYAWAN') {
            return null;
        }

        return match ($baris->Status_Permit ?? null) {
            'REVOKED' => $baris->Alasan_Pencabutan,
            'REJECTED' => DB::table('HRIS_Approval_Request')
                ->where('No_Transaksi', $baris->No_Transaksi)
                ->where('Flag_Approval', 'T')
                ->whereNotNull('Catatan')
                ->orderByDesc('Tanggal_Approval')
                ->value('Catatan'),
            default => null,
        };
    }

    /** KARYAWAN, API, atau TAMU — sejalan dengan Sumber_Permit di proyeksi gerbang. */
    private function sumberPermit(object $baris): string
    {
        if (($baris->No_Transaksi_Tamu ?? null) !== null) {
            return 'TAMU';
        }

        return strtoupper((string) ($baris->Sumber_Pengajuan ?? 'HCIS')) === 'API' ? 'API' : 'KARYAWAN';
    }

    /**
     * Permit milik aplikasi pemanggil saja.
     *
     * Penyaring Id_Api_Aplikasi ini SATU-SATUNYA yang memisahkan data antar
     * mitra, dan kegagalannya tidak menyalakan galat apa pun — yang terjadi
     * adalah data satu pelanggan terbaca pelanggan lain. Nomor milik mitra lain
     * dibalas seperti nomor yang tidak ada; 403 akan mengonfirmasi bahwa
     * nomornya memang ada.
     */
    private function permitMitra(Request $request, string $noTransaksi): ?object
    {
        $idAplikasi = (int) $request->attributes->get('hris_api_app_id');

        if ($idAplikasi <= 0) {
            return null;
        }

        return $this->permitQuery->basePermitQuery()
            ->where('h.No_Transaksi', $noTransaksi)
            ->where('h.Id_Api_Aplikasi', $idAplikasi)
            // Permit turunan registrasi tamu B2B TIDAK dilayani endpoint ini.
            // Ia membawa Id_Api_Aplikasi mitra yang sama, jadi tanpa penyaring
            // ini mitra bisa membatalkan izin gerbang tamunya lewat pintu permit
            // sementara kunjungannya tetap terjadwal — dua dokumen yang bercerai
            // tanpa satu pun galat. Keadaannya dilaporkan lewat
            // GET /tamu-b2b/{no_transaksi}.
            ->whereNull('h.No_Transaksi_Tamu')
            ->first();
    }

    /** @param array<string,mixed> $result */
    private function sukses(string $message, array $result, int $status): JsonResponse
    {
        // Kuncinya 'sukses', bukan 'success' — mengikuti pembungkus yang sudah
        // dipakai API Kalender Operasional dan ditegaskan uji kontraknya.
        return response()->json(['sukses' => true, 'message' => $message, 'result' => $result], $status);
    }

    private function galat(string $message, int $status): JsonResponse
    {
        return response()->json(['sukses' => false, 'message' => $message, 'result' => null], $status);
    }
}
