<?php

namespace App\Http\Controllers\KPI\PerformanceAppraisal\Integration;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Controller;
use App\Services\KPI\PerformanceAppraisal\PaPayloadMapperService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Penerima hasil penilaian 360 dari HC Learn.
 *
 * Pintu ini HANYA melayani PA. Sebelumnya kiriman PA menumpang callback LMS, dan di sana
 * penyaring bersama menuntut `Score` — satu angka akhir yang penilaian 360 tidak pernah
 * punya. Akibatnya cabang PA harus diperiksa SEBELUM validasi, kalau tidak kiriman yang sah
 * ditolak dengan pesan tentang field yang memang tak pernah diminta darinya. Urutan seperti
 * itu bekerja, tetapi ia menaruh aturan satu modul di dalam alur modul lain: siapa pun yang
 * kelak merapikan validasi LMS akan mematahkan PA tanpa pernah membuka berkas PA.
 *
 * Di sini penyaringnya milik PA sendiri, dan `Score` tidak disebut sama sekali.
 */
class PaThirdPartyCallbackController extends Controller
{
    public function __construct(
        private readonly PaPayloadMapperService $mapper,
    ) {}

    public function complete(Request $request)
    {
        /* Identitas kiriman (Kode_Karyawan, Id_Ujian_Token, Id_Integrasi) sudah diperiksa
           PaApiGuard, termasuk kecocokannya dengan penilai pemilik token. Yang tersisa di
           sini adalah bentuk ISI-nya. */
        /* Status_Skor WAJIB, dan sengaja tanpa nilai bawaan. Field yang boleh dikosongkan
           akan suatu hari terlupa, dan yang terjadi kemudian adalah nilai yang masih
           menunggu verifikasi diperlakukan sebagai final lalu ikut terhitung — diam-diam,
           karena semuanya berhasil. Lupa mengisinya lebih baik ditolak 422 pada kiriman
           pertama, saat masih murah. Alamat ini belum punya satu pemanggil pun, jadi tidak
           ada kompatibilitas yang dikorbankan. */
        $statusMentah = strtoupper(trim((string) $request->input('Status_Skor')));

        /* `min:1` HANYA berlaku pada FINAL, dan pemisahan ini memperbaiki cacat nyata.
           |
           | Kontrak HC Learn menyatakan `Detail[]` wajib terisi saat FINAL dan KOSONG saat
           | MENUNGGU_VERIFIKASI — mereka memang mengirim `"Detail": []` pada tahap pertama.
           | Menempelkan `min:1` di luar cabang membuat kiriman yang SAH itu dijawab 422
           | dengan pesan tentang field yang tidak pernah kami minta darinya, dan HC Learn
           | memperlakukan 422 sebagai FATAL: tidak diulang, ditandai gagal. Tahap kedua
           | yang membawa nilai sesungguhnya tidak akan pernah menyusul. */
        $aturanDetail = ['array'];

        if ($statusMentah === PaPayloadMapperService::SKOR_FINAL) {
            $aturanDetail[] = 'required';
            $aturanDetail[] = 'min:1';
        }

        $request->validate([
            'Status_Skor' => 'required|string|in:'.implode(',', PaPayloadMapperService::SKOR_DIKENAL),
            'Detail' => $aturanDetail,
            'Detail.*.Kode_Soal' => 'required|string',
        ]);

        /* `Api_Reference` LEBIH DULU, `Id_Ujian_Token` sebagai cadangan.
           |
           | Kontrak final HC Learn (10 September 2026) menyatakan id numerik SENGAJA tidak
           | pernah dikirim — yang datang hanya `Api_Reference`, 32 heksadesimal acak.
           | Sampai hari itu penerima ini menuntut `Id_Ujian_Token`, sehingga SETIAP kiriman
           | sungguhan akan dijawab 422 karena field yang kita tuntut tidak pernah ada.
           |
           | Cadangannya dipertahankan, bukan dibuang: simulator pengisian lokal dan data
           | lama masih memakai token, dan mematikannya tidak membeli apa pun. */
        $token = (string) ($request->input('Api_Reference') ?? $request->input('Id_Ujian_Token'));
        $statusSkor = $statusMentah;

        try {
            if ($statusSkor !== PaPayloadMapperService::SKOR_FINAL) {
                $catat = $this->mapper->catatStatusSkor(
                    $token,
                    $statusSkor,
                    $request->attributes->get('pa_user_id'),
                );

                Log::info('PA_CALLBACK_DITERIMA', ['token' => $token, 'status_skor' => $statusSkor]);

                return ResponseHelper::success(
                    $catat,
                    $statusSkor === PaPayloadMapperService::SKOR_MENUNGGU
                        ? 'Pengiriman tercatat; menunggu verifikasi. Pengingat untuk penilai ini dihentikan.'
                        : 'Penolakan tercatat; penugasan dikembalikan agar penilainya mengerjakan ulang.',
                );
            }

            $hasil = $this->mapper->terimaDanPetakan(
                $token,
                $request->all(),
                $request->attributes->get('pa_user_id'),
            );
        } catch (RuntimeException $e) {
            /* Pesannya diteruskan apa adanya. Seluruh RuntimeException dari mapper menyebut
               butir atau token yang bermasalah — dan penerimanya adalah developer di seberang
               yang tidak punya akses ke log kita. Menggantinya dengan "gagal memproses"
               memaksa mereka menebak, lalu bertanya, lalu menunggu. */
            Log::warning('PA_CALLBACK_DITOLAK', ['token' => $token, 'sebab' => $e->getMessage()]);

            return ResponseHelper::error($e->getMessage(), 422);
        }

        Log::info('PA_CALLBACK_DITERIMA', [
            'token' => $token,
            'payload_id' => $hasil['payload_id'],
            'baru' => $hasil['baru'],
        ]);

        return ResponseHelper::success(
            $this->ratakan($hasil),
            $hasil['baru']
                ? 'Hasil penilaian diterima dan dipetakan.'
                : 'Hasil penilaian sudah pernah diterima dengan isi yang sama.',
        );
    }

    /**
     * Meratakan kembalian mapper menjadi satu tingkat.
     *
     * Jalur lama membungkusnya sebagai `['result' => $hasil]` ke dalam envelope yang SUDAH
     * punya `result`, sehingga pembacanya harus menulis `result.result.pemetaan.butir_hilang`
     * — sarang yang tak seorang pun memutuskan, hanya terjadi. Endpoint ini baru dan belum
     * punya pemakai, jadi tidak ada kompatibilitas yang perlu dijaga.
     *
     * `pemetaan` bernilai null pada kiriman ulang yang isinya identik: pemetaannya tidak
     * dijalankan lagi karena hasilnya akan sama persis. Field-nya karena itu ikut absen, dan
     * itu bukan kekurangan — pengirimnya sudah menerima jawaban lengkapnya pada kiriman
     * pertama, dan mengarang angka supaya bentuknya "lengkap" jauh lebih buruk daripada
     * bidang yang jujur tidak ada.
     *
     * @param  array<string, mixed>  $hasil
     * @return array<string, mixed>
     */
    private function ratakan(array $hasil): array
    {
        $rata = [
            /* Dinyatakan kembali oleh HCIS, bukan digemakan dari kiriman: kalau yang terbaca
               di sini bukan yang dimaksud pengirim, ketahuannya pada kiriman itu juga —
               bukan berminggu-minggu kemudian saat nilai peserta tidak kunjung keluar. */
            'status_skor' => PaPayloadMapperService::SKOR_FINAL,
            'payload_id' => $hasil['payload_id'],
            'assignment_id' => $hasil['assignment_id'],
            'baru' => $hasil['baru'],
            'perhitungan_diantrekan' => $hasil['perhitungan_diantrekan'] ?? false,
        ];

        $pemetaan = $hasil['pemetaan'] ?? null;

        if (! is_array($pemetaan)) {
            return $rata;
        }

        foreach ([
            'pemeta_versi', 'butir', 'berskor',
            'tak_terpetakan', 'skala_berbeda', 'butir_hilang', 'kontrak_lengkap',
        ] as $kunci) {
            if (array_key_exists($kunci, $pemetaan)) {
                $rata[$kunci] = $pemetaan[$kunci];
            }
        }

        return $rata;
    }
}
