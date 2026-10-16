<?php

namespace App\Services\KPI\PerformanceAppraisal;

use App\Helpers\FormatTanggalHelper;
use App\Jobs\KPI\PerformanceAppraisal\HitungHasilPesertaJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Penerima dan penerjemah payload HC Learn.
 *
 * DUA LANGKAH TERPISAH, DAN ITU DISENGAJA
 *
 * `terima()` menyimpan kiriman apa adanya dan berhenti. `petakan()` menerjemahkannya
 * menjadi baris skor. Kalau keduanya jadi satu, kiriman yang bentuknya tidak dikenali
 * akan ditolak seluruhnya dan HC Learn harus mengirim ulang sesuatu yang sudah pernah
 * sampai. Dengan dipisah, payload mentahnya selalu selamat: pemetaannya boleh gagal,
 * diperbaiki, lalu dijalankan lagi tanpa melibatkan pihak seberang.
 *
 * KENAPA HASH, BUKAN HANYA Id_Ujian_Nilai_Akhir
 *
 * Kontrak meminta `Id_Ujian_Nilai_Akhir` unik per hasil, dan unique index sudah
 * menegakkannya. Tetapi kolom itu boleh kosong, dan kiriman ulang yang kosong penandanya
 * akan lolos berkali-kali. Hash isi payload menutup celah itu: kiriman yang isinya
 * persis sama tidak pernah menjadi baris kedua.
 *
 * BUTIR YANG TIDAK DIKENALI TIDAK DIBUANG
 *
 * Kode soal yang tidak ada di pemetaan tetap disimpan sebagai baris skor tanpa
 * kompetensi. Membuangnya akan membuat nilai kompetensi terhitung dari sebagian butir
 * saja, dan angkanya tetap tampak wajar — persis kegagalan yang paling sulit dilihat.
 * Yang tidak terpetakan dilaporkan balik oleh `petakan()` supaya ada yang memperbaikinya.
 */
class PaPayloadMapperService
{
    /**
     * Versi penerjemah. Naikkan bila aturan penerjemahannya berubah, supaya hasil lama
     * tetap bisa dijelaskan dengan aturan yang berlaku saat itu.
     */
    public const PEMETA_VERSI = 1;

    public const STATUS_SELESAI = 'SELESAI';

    /** Nilai sah dan siap dihitung — bawaan lama, dan satu-satunya yang menghitung. */
    public const SKOR_FINAL = 'FINAL';

    /** Penilai sudah kirim; admin HC Learn belum mengesahkan angkanya. */
    public const SKOR_MENUNGGU = 'MENUNGGU_VERIFIKASI';

    /** Verifikasi menolak isinya; penilainya harus mengerjakan ulang. */
    public const SKOR_DITOLAK = 'DITOLAK';

    public const SKOR_DIKENAL = [self::SKOR_FINAL, self::SKOR_MENUNGGU, self::SKOR_DITOLAK];

    public function __construct(
        private readonly PaCycleGate $gate,
    ) {}

    /**
     * Menemukan baris integrasi dari pengenal yang dikirim HC Learn.
     *
     * DUA KOLOM DICOBA, dan urutannya disengaja:
     *
     *   `Api_Ref`         — identitas yang dipakai kontrak final mereka. Id numerik SENGAJA
     *                       tidak pernah dikirim: bilangan berurutan bisa ditebak, dan
     *                       memegang satu berarti bisa mencoba tetangganya — yang tak lain
     *                       hasil penilaian karyawan lain.
     *   `Id_Ujian_Token`  — jalur lama. Dipertahankan karena simulator pengisian lokal dan
     *                       data lama masih memakainya; membuangnya berarti mematikan jalur
     *                       uji tanpa satu pun keuntungan.
     *
     * Api_Ref dicoba LEBIH DULU. Keduanya bertipe teks dan sebuah nilai bisa saja ada di
     * kedua kolom pada baris yang berbeda; mendahulukan yang dipakai production membuat
     * kiriman sungguhan tidak pernah mendarat di baris simulasi.
     */
    private function integrasiDariPengenal(string $pengenal): object
    {
        $pengenal = trim($pengenal);

        if ($pengenal === '') {
            throw new RuntimeException(
                'Kiriman tanpa Api_Reference tidak bisa disambungkan ke penugasan mana pun.'
            );
        }

        $kolom = [
            'Id_PA_Assignment_Integration', 'PA_Assignment_Id',
            'PA_Cycle_Question_Package_Id',
        ];

        $integrasi = DB::table('N_PA_Assignment_Integration')
            ->where('Api_Ref', $pengenal)
            ->where('Flag_Aktif', 'Y')
            ->orderByDesc('Id_PA_Assignment_Integration')
            ->first($kolom);

        $integrasi ??= DB::table('N_PA_Assignment_Integration')
            ->where('Id_Ujian_Token', $pengenal)
            ->orderByDesc('Id_PA_Assignment_Integration')
            ->first($kolom);

        if ($integrasi === null) {
            throw new RuntimeException(
                "Api_Reference {$pengenal} tidak dikenali. Kiriman tidak diterima."
            );
        }

        return $integrasi;
    }

    /**
     * Menyimpan satu kiriman HC Learn apa adanya.
     *
     * @param  array<string, mixed>  $payload  hasil json_decode kiriman, sudah menjadi array
     * @return array{payload_id:int, assignment_id:int, baru:bool}
     */
    public function terima(string $pengenal, array $payload): array
    {
        $integrasi = $this->integrasiDariPengenal($pengenal);
        $assignmentId = (int) $integrasi->PA_Assignment_Id;
        $integrationId = (int) $integrasi->Id_PA_Assignment_Integration;

        $this->gate->pastikanBolehUntukPenugasan(PaCycleGate::TERIMA_PAYLOAD, $assignmentId);
        $this->pastikanPenugasanMasihBerlaku($assignmentId);

        $json = $this->encode($payload);
        $hash = hash('sha256', $json);
        $nilaiAkhir = $this->teks($payload['Id_Ujian_Nilai_Akhir'] ?? null);

        return DB::transaction(function () use (
            $assignmentId, $integrationId, $json, $hash, $nilaiAkhir, $payload
        ) {
            /* Kiriman untuk SATU penugasan diproses berurutan (14 Okt 2026). Tanpa kunci, dua
               kiriman ulang yang tiba bersamaan sama-sama lolos pemeriksaan "sudah ada" di bawah:
               yang kedua jatuh ke indeks unik (HC Learn menerima 500), atau — tanpa
               Id_Ujian_Nilai_Akhir — tersimpan dua kali. Yang kedua kini menunggu, lalu
               mendapati kiriman pertama dan dijawab sebagai kiriman ulang biasa. */
            DB::table('N_PA_Assignment_Integration')
                ->where('Id_PA_Assignment_Integration', $integrationId)
                ->lockForUpdate()
                ->first(['Id_PA_Assignment_Integration']);

            $sudahAda = DB::table('N_PA_Assignment_Payload')
                ->where('PA_Assignment_Integration_Id', $integrationId)
                ->where('Payload_Hash', $hash)
                ->first(['Id_PA_Assignment_Payload']);

            if ($sudahAda !== null) {
                // Kiriman ulang yang isinya persis sama. Bukan kesalahan, dan bukan
                // alasan membuat baris kedua.
                return [
                    'payload_id' => (int) $sudahAda->Id_PA_Assignment_Payload,
                    'assignment_id' => $assignmentId,
                    'baru' => false,
                ];
            }

            if ($nilaiAkhir !== null) {
                $bentrok = DB::table('N_PA_Assignment_Payload')
                    ->where('Id_Ujian_Nilai_Akhir', $nilaiAkhir)
                    ->exists();

                if ($bentrok) {
                    throw new RuntimeException(
                        "Id_Ujian_Nilai_Akhir {$nilaiAkhir} sudah pernah diterima dengan isi yang "
                        .'berbeda. Satu penanda hasil tidak boleh mewakili dua isi.'
                    );
                }
            }

            /* Blok `penilaian{}` milik mereka. Inilah yang menjawab "kapan orang ini
               benar-benar mengerjakan" dan "apa yang dilihatnya di sana" — pertanyaan yang
               selalu datang ketika seseorang menyanggah nilainya, dan yang tidak bisa
               dijawab dari JSON tanpa mengurainya satu per satu. */
            $penilaian = is_array($payload['penilaian'] ?? null) ? $payload['penilaian'] : [];

            $payloadId = (int) DB::table('N_PA_Assignment_Payload')->insertGetId([
                'PA_Assignment_Integration_Id' => $integrationId,
                'Payload_Json' => $json,
                'Payload_Hash' => $hash,
                'Id_Ujian_Nilai_Akhir' => $nilaiAkhir,
                'Api_Version' => $this->teks($payload['Api_Version'] ?? null),

                /* Identitas versi HC Learn, disimpan APA ADANYA. `Id_Ujian_Token` numerik
                   sengaja tidak pernah mereka kirim, jadi inilah satu-satunya pengenal yang
                   bisa dipakai menelusuri satu kiriman kembali ke sisi mereka. */
                'Api_Reference' => $this->teks($payload['Api_Reference'] ?? null),
                'Status_Skor' => self::SKOR_FINAL,
                'Kode_Akses' => $this->teks($payload['kode_akses'] ?? null),
                'Flag_Multi' => ($payload['is_multi'] ?? false) ? 'Y' : 'T',

                /* Ringkasan MEREKA, disimpan sebagai PEMBANDING. HCIS tetap menghitung dari
                   butirnya sendiri — menyimpannya justru supaya selisih antara dua
                   perhitungan bisa dilihat, bukan supaya dipakai. */
                'Total_Nilai' => $this->angka($payload['Total_Nilai'] ?? null),

                'Nama_Tes' => $this->teks($penilaian['Nama_Tes'] ?? null),
                'Waktu_Mulai_Akses' => $this->teks($penilaian['Waktu_Mulai_Akses'] ?? null),
                'Waktu_Selesai_Akses' => $this->teks($penilaian['Waktu_Selesai_Akses'] ?? null),
                'Ambang_Batas_Nilai' => $this->angka($penilaian['Ambang_Batas_Nilai'] ?? null),
                'Status_Kelulusan' => $this->teks($penilaian['Status_Kelulusan'] ?? null),
                'Warna' => $this->teks($penilaian['Warna'] ?? null),

                'Diterima_At' => FormatTanggalHelper::getCurrentTime(),
                'Flag_Terpeta' => 'T',
            ], 'Id_PA_Assignment_Payload');

            return ['payload_id' => $payloadId, 'assignment_id' => $assignmentId, 'baru' => true];
        });
    }

    /**
     * Menerjemahkan satu payload menjadi baris skor.
     *
     * Bisa dijalankan berulang. Pemetaan ulang tidak menghapus apa pun: baris lama
     * dinonaktifkan dan baris baru masuk dengan versi pemeta berikutnya, sama seperti
     * pola menghitung ulang di lapisan hasil.
     *
     * @return array{
     *     payload_id:int, pemeta_versi:int, butir:int, berskor:int,
     *     tak_terpetakan:list<string>, skala_berbeda:list<string>,
     *     butir_hilang:list<string>, kontrak_lengkap:bool
     * }
     */
    public function petakan(int $payloadId, ?int $olehUserId = null): array
    {
        $payload = DB::table('N_PA_Assignment_Payload as p')
            ->join('N_PA_Assignment_Integration as i',
                'i.Id_PA_Assignment_Integration', '=', 'p.PA_Assignment_Integration_Id')
            ->where('p.Id_PA_Assignment_Payload', $payloadId)
            ->first([
                'p.Id_PA_Assignment_Payload', 'p.PA_Assignment_Integration_Id',
                'p.Payload_Json', 'p.Flag_Terpeta', 'p.Mapper_Version',
                'i.PA_Assignment_Id', 'i.PA_Cycle_Question_Package_Id',
            ]);

        if ($payload === null) {
            throw new RuntimeException("Payload {$payloadId} tidak ada.");
        }

        $assignmentId = (int) $payload->PA_Assignment_Id;

        $this->gate->pastikanBolehUntukPenugasan(PaCycleGate::TERIMA_PAYLOAD, $assignmentId);
        $this->pastikanPenugasanMasihBerlaku($assignmentId);

        $isi = json_decode((string) $payload->Payload_Json, true);

        if (! is_array($isi)) {
            throw new RuntimeException("Payload {$payloadId} bukan JSON yang bisa dibaca.");
        }

        $detail = $isi['Detail'] ?? null;

        if (! is_array($detail) || $detail === []) {
            throw new RuntimeException(
                "Payload {$payloadId} tidak memuat Detail. Nilai per butir adalah satu-satunya "
                .'sumber nilai kompetensi; Total_Nilai hanya pembanding.'
            );
        }

        $paketId = $payload->PA_Cycle_Question_Package_Id === null
            ? null
            : (int) $payload->PA_Cycle_Question_Package_Id;
        $peta = $this->petaSoal($paketId);
        $diharapkan = $this->butirDiharapkan($paketId);
        $versi = ((int) ($payload->Mapper_Version ?? 0)) + 1;

        $penilaian = is_array($isi['penilaian'] ?? null) ? $isi['penilaian'] : [];
        $mulaiAkses = $this->waktuAkses($penilaian['Waktu_Mulai_Akses'] ?? null);
        $selesaiAkses = $this->waktuAkses($penilaian['Waktu_Selesai_Akses'] ?? null);

        return DB::transaction(function () use (
            $payloadId, $assignmentId, $detail, $peta, $diharapkan, $versi, $olehUserId,
            $mulaiAkses, $selesaiAkses
        ) {
            $sekarang = FormatTanggalHelper::getCurrentTime();

            DB::table('N_PA_Assignment_Score')
                ->where('PA_Assignment_Payload_Id', $payloadId)
                ->where('Flag_Aktif', 'Y')
                ->update([
                    'Flag_Aktif' => 'T',
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);

            $baris = [];
            $esaiPerKode = [];
            $terlihat = [];
            $takTerpetakan = [];
            $skalaBerbeda = [];
            $berskor = 0;
            $urutan = 0;

            foreach ($detail as $d) {
                if (! is_array($d)) {
                    continue;
                }

                $kodeSoal = $this->teks($d['Kode_Soal'] ?? null);

                if ($kodeSoal === null) {
                    throw new RuntimeException(
                        "Payload {$payloadId} memuat butir tanpa Kode_Soal; butir itu tidak bisa "
                        .'diterjemahkan maupun ditelusuri.'
                    );
                }

                if (isset($terlihat[$kodeSoal])) {
                    throw new RuntimeException(
                        "Payload {$payloadId} memuat Kode_Soal {$kodeSoal} lebih dari sekali. "
                        .'Butir kembar akan dihitung dua kali.'
                    );
                }

                $terlihat[$kodeSoal] = true;
                $urutan++;

                $m = $peta[$kodeSoal] ?? null;

                if ($m === null) {
                    $takTerpetakan[] = $kodeSoal;
                }

                $skor = $this->angka($d['Skor'] ?? null);
                $maksimal = $this->angka($d['Skor_Maksimal'] ?? null);

                if ($skor !== null && ($maksimal === null || $maksimal <= 0)) {
                    throw new RuntimeException(
                        "Butir {$kodeSoal} pada payload {$payloadId} berskor {$skor} tanpa "
                        .'Skor_Maksimal yang sah. Tanpa pasangannya, skor itu tidak punya skala '
                        .'dan tidak bisa dinormalkan.'
                    );
                }

                if ($skor !== null && $maksimal !== null && $skor > $maksimal) {
                    throw new RuntimeException(
                        "Butir {$kodeSoal} pada payload {$payloadId} berskor {$skor} melebihi "
                        ."Skor_Maksimal {$maksimal}."
                    );
                }

                $esai = $this->esaiPerSoal($d, $kodeSoal, $payloadId);

                /* SKOR BAGIAN ESAI DIABAIKAN (keputusan pemilik proyek 26 Sep 2026). HC Learn
                   memberi esai skor mesin dari kemiripan teks terhadap kunci; untuk esai 360
                   angka itu tidak bermakna, dan membiarkannya tersimpan berarti ia ikut
                   membentuk Nilai Kompetensi. Angkanya divalidasi di atas lalu dibuang;
                   aslinya tetap di Payload_Json. */
                if ($esai !== null) {
                    $skor = null;
                    $maksimal = null;
                    $esaiPerKode[$kodeSoal] = $esai;
                }

                // Paket soalnya berubah setelah pemetaan dibuat. Bukan alasan menolak
                // kiriman, tetapi wajib terlihat: normalisasinya memakai skala kiriman,
                // sedangkan yang dipetakan orang adalah skala yang lain.
                if ($m !== null && $m->Skor_Maksimal !== null && $maksimal !== null
                    && abs((float) $m->Skor_Maksimal - $maksimal) > 0.001) {
                    $skalaBerbeda[] = $kodeSoal;
                }

                if ($skor !== null) {
                    $berskor++;
                }

                $jumlahSoal = $this->cacah($d['Jumlah_Soal'] ?? null);
                $jumlahTerjawab = $this->cacah($d['Jumlah_Terjawab'] ?? null);

                /* Terjawab tidak boleh melebihi soalnya. Ditolak di sini, bukan dibiarkan
                   menabrak CHECK: pesan basis data menyebut nama constraint, sementara HC
                   Learn menampilkan pesan kita apa adanya kepada admin mereka. */
                if ($jumlahSoal !== null && $jumlahTerjawab !== null && $jumlahTerjawab > $jumlahSoal) {
                    throw new RuntimeException(
                        "Butir {$kodeSoal} pada payload {$payloadId} menyebut {$jumlahTerjawab} "
                        ."soal terjawab dari {$jumlahSoal} soal."
                    );
                }

                $baris[] = [
                    'PA_Assignment_Payload_Id' => $payloadId,
                    'PA_Competency_Id' => $m?->PA_Competency_Id,
                    'Kode_Soal_Sumber' => $kodeSoal,
                    'Jenis_Soal_Sumber' => $this->teks($d['Jenis_Soal'] ?? null),
                    'Skor' => $skor,
                    'Skor_Maksimal' => $maksimal,
                    'Bobot_Sumber' => $this->angka($d['Bobot_Penilaian'] ?? null),
                    'Jawaban_Teks' => $this->teks($d['Jawaban_Teks'] ?? null),

                    /* Satu token boleh memuat DUA alat tes, dan butir keduanya datang datar
                       di satu larik. Tanpa kode alat tesnya, keduanya tidak bisa dipisahkan
                       lagi sesudah tersimpan. */
                    'Kode_Alat_Tes' => $this->teks($d['Kode_Alat_Tes'] ?? null),
                    'Nama_Alat_Tes' => $this->teks($d['Nama_Alat_Tes'] ?? null),

                    /* Interpretasi MEREKA, dipakai layar per kompetensi. Tanpa disimpan,
                       tiap tampilan harus mengarang ambangnya sendiri — dan dua sistem yang
                       menilai hal yang sama dengan ambang berbeda menghasilkan dua kebenaran
                       untuk satu orang. */
                    'Status_Interpretation' => $this->teks($d['Status_Interpretation'] ?? null),
                    'Warna' => $this->teks($d['Warna'] ?? null),
                    'Keterangan' => $this->teks($d['Keterangan'] ?? null),

                    /* Pembeda "nilai rendah" dari "tidak selesai dikerjakan". Keduanya
                       menghasilkan skor kecil dan menuntut tindakan yang sama sekali
                       berbeda. */
                    'Jumlah_Soal' => $jumlahSoal,
                    'Jumlah_Terjawab' => $jumlahTerjawab,

                    'Mapper_Version' => $versi,
                    'Urutan_Tampil' => $urutan,
                    'Flag_Aktif' => 'Y',
                    'Created_By' => $olehUserId,
                ];
            }

            foreach (array_chunk($baris, 100) as $potongan) {
                DB::table('N_PA_Assignment_Score')->insert($potongan);
            }

            $this->simpanEsai($payloadId, $versi, $esaiPerKode);

            $butirHilang = array_values(array_diff(array_keys($diharapkan), array_keys($terlihat)));
            $kontrakLengkap = $diharapkan !== [] && $butirHilang === [];

            DB::table('N_PA_Assignment_Payload')
                ->where('Id_PA_Assignment_Payload', $payloadId)
                ->update([
                    'Flag_Terpeta' => 'Y',
                    'Mapper_Version' => $versi,
                    'Updated_At' => $sekarang,
                    'Updated_By' => $olehUserId,
                ]);

            if ($kontrakLengkap) {
                $this->tandaiPenugasanTerkumpul($assignmentId, $olehUserId, $mulaiAkses, $selesaiAkses);
            }

            return [
                'payload_id' => $payloadId,
                'pemeta_versi' => $versi,
                'butir' => count($baris),
                'berskor' => $berskor,
                'tak_terpetakan' => $takTerpetakan,
                'skala_berbeda' => $skalaBerbeda,
                'butir_hilang' => $butirHilang,
                'kontrak_lengkap' => $kontrakLengkap,
            ];
        });
    }

    /**
     * Mencatat kiriman yang BUKAN nilai final: masih menunggu verifikasi, atau ditolak.
     *
     * ------------------------------------------------------------------------------------------
     * KENAPA KEADAAN KETIGA INI ADA
     * ------------------------------------------------------------------------------------------
     *
     * Di HC Learn sebuah nilai bisa harus disahkan admin sebelum berlaku. Tanpa keadaan
     * ketiga, dua-duanya buruk. Menerimanya sebagai final berarti HCIS menghitung — dan bisa
     * saja MENERBITKAN — angka yang kemudian berubah. Tidak menerima apa pun sampai sah
     * berarti HCIS tak bisa membedakan "belum mengisi" dari "sudah mengisi, menunggu
     * disahkan", sehingga penilai yang sudah selesai terus ditagih pengingat sampai
     * verifikasi kelar.
     *
     * MENUNGGU_VERIFIKASI menghentikan pengingat TANPA menandai selesai: nilainya tidak ikut
     * dihitung, pesertanya tetap terhitung belum lengkap, tetapi orangnya berhenti ditagih.
     *
     * DITOLAK mengembalikan penugasan ke BELUM_MULAI, dan pengingat berjalan lagi — memang
     * itu yang dibutuhkan, karena penilainya harus mengerjakan ulang.
     *
     * TIDAK ADA PAYLOAD YANG DISIMPAN di sini. Kiriman MENUNGGU_VERIFIKASI boleh datang tanpa
     * `Detail` sama sekali, dan menyimpan cangkang kosong sebagai payload akan membuat
     * `petakan()` kelak menemukan kiriman tanpa butir lalu menahannya dengan sebab yang
     * menyesatkan. Yang berubah cuma keadaan penugasannya; nilainya menyusul di kiriman FINAL.
     *
     * @return array{assignment_id:int, status_skor:string, baru:bool}
     */
    public function catatStatusSkor(string $pengenal, string $statusSkor, ?int $olehUserId = null): array
    {
        $statusSkor = strtoupper(trim($statusSkor));

        if (! in_array($statusSkor, [self::SKOR_MENUNGGU, self::SKOR_DITOLAK], true)) {
            throw new RuntimeException(
                "Status_Skor {$statusSkor} tidak ditangani di sini; hanya MENUNGGU_VERIFIKASI dan DITOLAK."
            );
        }

        $assignmentId = (int) $this->integrasiDariPengenal($pengenal)->PA_Assignment_Id;

        $this->gate->pastikanBolehUntukPenugasan(PaCycleGate::TERIMA_PAYLOAD, $assignmentId);
        $this->pastikanPenugasanMasihBerlaku($assignmentId);

        $tugas = DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->first(['Status_Penugasan', 'Started_At']);

        /* Penugasan yang SUDAH selesai tidak boleh dimundurkan diam-diam. Nilainya sudah
           dipetakan dan mungkin sudah ikut menghasilkan angka akhir; menariknya kembali
           lewat kiriman susulan akan mengubah hasil yang sudah dilihat orang tanpa jejak
           bahwa itu terjadi. Yang benar adalah menolak dan menyuruh mereka mengirim FINAL
           yang baru — jalur itu punya versinya sendiri. */
        if ($tugas !== null && in_array($tugas->Status_Penugasan, [
            self::STATUS_SELESAI,

            /* TERKUMPUL ikut dilindungi. Nilainya sudah dipetakan utuh; memundurkannya ke
               MENUNGGU_VERIFIKASI lewat kiriman susulan akan menghapus lembar yang sudah
               dikerjakan orang dari hitungan kemajuannya, dan tombol kirimnya mati tanpa
               satu pun keterangan tentang apa yang berubah. */
            PaAssignmentBuilderService::STATUS_TERKUMPUL,
        ], true)) {
            throw new RuntimeException(sprintf(
                'Penugasan %d sudah %s dengan nilai yang terpetakan. Kirim hasil FINAL yang '
                .'baru bila angkanya berubah; status menunggu tidak bisa membatalkan hasil '
                .'yang sudah masuk.',
                $assignmentId,
                $tugas->Status_Penugasan,
            ));
        }

        $statusBaru = $statusSkor === self::SKOR_MENUNGGU
            ? PaAssignmentBuilderService::STATUS_MENUNGGU_VERIFIKASI
            : PaAssignmentBuilderService::STATUS_BELUM_MULAI;

        $baru = $tugas === null || $tugas->Status_Penugasan !== $statusBaru;
        $sekarang = FormatTanggalHelper::getCurrentTime();

        DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->update([
                'Status_Penugasan' => $statusBaru,
                /* Started_At dipertahankan bila sudah ada: orangnya memang sudah mulai, dan
                   menimpanya dengan waktu kiriman ini akan memendekkan durasi pengerjaan
                   yang sebenarnya. Bila belum ada, kiriman ini bukti paling awal yang kita
                   punya bahwa ia mulai. */
                'Started_At' => $tugas?->Started_At ?? $sekarang,
                'Updated_At' => $sekarang,
                'Updated_By' => $olehUserId,
            ]);

        Log::info('PA_STATUS_SKOR', [
            'assignment_id' => $assignmentId,
            'status_skor' => $statusSkor,
            'status_penugasan' => $statusBaru,
        ]);

        return ['assignment_id' => $assignmentId, 'status_skor' => $statusSkor, 'baru' => $baru];
    }

    /**
     * Jalan pintas yang lazim dipakai endpoint callback: terima lalu langsung petakan.
     *
     * @param  array<string, mixed>  $payload
     * @return array{payload_id:int, assignment_id:int, baru:bool, pemetaan:array<string, mixed>|null}
     */
    public function terimaDanPetakan(string $idUjianToken, array $payload, ?int $olehUserId = null): array
    {
        $terima = $this->terima($idUjianToken, $payload);

        // Kiriman ulang yang identik tidak dipetakan lagi: hasilnya akan sama persis,
        // hanya menaikkan versi pemeta tanpa satu pun perbedaan untuk dijelaskan.
        $pemetaan = $terima['baru']
            ? $this->petakan($terima['payload_id'], $olehUserId)
            : null;

        $dihitungOtomatis = $terima['baru']
            ? $this->picuPerhitunganBilaLengkap((int) $terima['assignment_id'], $olehUserId)
            : false;

        /* Kiriman FINAL yang tidak lengkap tertahan di "Kiriman perlu ditinjau". Admin siklus
           diberi tahu, sesudah commit: pemberitahuan ditulis ke tabel yang di-polling topbar. */
        if ($pemetaan !== null && ! ($pemetaan['kontrak_lengkap'] ?? true)) {
            $payloadId = (int) $terima['payload_id'];
            DB::afterCommit(fn () => app(PaNotificationService::class)->kirimanTertahan($payloadId));
        }

        return $terima + [
            'pemetaan' => $pemetaan,

            /* Dilaporkan supaya pemanggil — termasuk uji — bisa membedakan "kiriman diterima"
               dari "kiriman diterima DAN perhitungannya diantrekan". */
            'perhitungan_diantrekan' => $dihitungOtomatis,
        ];
    }

    /**
     | MENGANTREKAN PERHITUNGAN begitu penilai TERAKHIR seseorang selesai.
     |
     | Peserta tidak lagi menunggu siklusnya ditutup: nilainya tidak pernah bergantung pada
     | orang lain — grade dari pita absolut, kuadran dari dua pita absolut, kalibrasi per satu
     | hasil — sehingga menahannya sampai penutupan hanya membuat yang telat menahan yang
     | sudah beres.
     |
     | DIANTREKAN, bukan dijalankan di sini. Pemanggil method ini adalah callback HC Learn;
     | menghitung di dalamnya berarti pengirim menunggu perhitungan kita, dan callback yang
     | lambat akan diulang — memicu perhitungan berulang atas kiriman yang sama.
     |
     | Kegagalan mengantre TIDAK boleh menggagalkan kirimannya. Payload penilai sudah tersimpan
     | dan terpetakan pada titik ini; antrean yang mati berarti kehilangan angkanya, sementara
     | kirimannya utuh dan bisa dihitung admin kapan saja. Menggagalkan seluruh callback karena
     | antrean bermasalah justru membuat pengirimnya mengulang kiriman yang sudah kita terima.
     |
     | @return bool apakah perhitungannya jadi diantrekan
     */
    private function picuPerhitunganBilaLengkap(int $assignmentId, ?int $olehUserId): bool
    {
        $participantId = (int) DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->value('PA_Participant_Id');

        if ($participantId === 0) {
            return false;
        }

        /* Kelengkapannya ditanyakan ke pemilik aturannya, bukan dihitung ulang di sini.
           Dua tempat yang menjawab "sudah lengkap belum" akan menyimpang, dan yang menyimpang
           di sini menentukan kapan angka seseorang lahir. */
        $sisa = app(PaResultCalculationService::class)->sisaPenugasan($participantId);

        if ($sisa > 0) {
            return false;
        }

        try {
            HitungHasilPesertaJob::dispatch($participantId, $olehUserId);

            return true;
        } catch (\Throwable $e) {
            Log::error('PA: gagal mengantrekan perhitungan otomatis', [
                'participant_id' => $participantId,
                'galat' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /* ------------------------------------------------------------------ *
     * Bagian dalam                                                        *
     * ------------------------------------------------------------------ */

    /**
     * Pemetaan kode soal untuk paket yang dipakai satu penugasan.
     *
     * Sumbernya SATU tabel dengan `butirDiharapkan()`. Ketika keduanya membaca tabel
     * berbeda, peta terisi sementara daftar butir yang diharapkan kosong: payload
     * terpetakan tetapi tak pernah dianggap lengkap, penugasan tak pernah SELESAI,
     * dan pilar kompetensi ikut kosong tanpa satu pun pesan galat. Itu sudah terjadi
     * sekali (DDL-2026-083 r1) dan tidak boleh dibuka lagi.
     *
     * @return array<string, object>
     */
    private function petaSoal(?int $paketId): array
    {
        if ($paketId === null) {
            // Penugasan tanpa paket soal masih bisa menerima payload, tetapi tak satu
            // butir pun akan terpetakan. Itu dilaporkan, bukan disembunyikan.
            return [];
        }

        /* DIKUNCI `Kode_Soal_Sumber`, BUKAN `Id_Jenis_Soal`.
           |
           | Yang dikembalikan HC Learn di `Detail[].Kode_Soal` adalah kode yang MEREKA
           | berikan saat paketnya diikat — `K01`..`K13` — bukan id bagian. Sampai
           | 10 September 2026 peta ini dikunci `Id_Jenis_Soal` (100158..), sehingga tidak
           | satu butir pun pernah bertemu pasangannya: penilaian pertama yang sungguh diisi
           | orang masuk lengkap, tersimpan utuh, dijawab 200 — dan seluruh 13 butirnya
           | mendarat tak terpetakan. `kontrak_lengkap` false, penugasan tak pernah selesai,
           | tanpa satu pun pesan galat di layar mana pun.
           |
           | Butir tanpa kode DIBUANG dari peta, bukan dikunci NULL: satu kunci NULL akan
           | menampung butir kedua tanpa kode dan menimpanya diam-diam. */
        return DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $paketId)
            ->where('Flag_Aktif', 'Y')
            ->whereNotNull('Kode_Soal_Sumber')
            ->get([
                'Kode_Soal_Sumber', 'PA_Competency_Id',
                // Dialiaskan supaya sisa kode tetap berbicara dalam istilah payload.
                DB::raw('Skor_Maksimal AS Skor_Maksimal'),
            ])
            ->keyBy('Kode_Soal_Sumber')
            ->all();
    }

    /**
     * @return array<string, true>
     */
    private function butirDiharapkan(?int $paketId): array
    {
        if ($paketId === null) {
            return [];
        }

        /* SATU TABEL, SATU KUNCI dengan `petaSoal()` — lihat catatan di sana. Ketika
           keduanya membaca kunci yang berbeda, payload terpetakan tetapi tak pernah
           dianggap lengkap.
           |
           | BUTIR TANPA KODE TETAP DIHITUNG, dengan kunci buatan yang tidak mungkin
           | dikirim siapa pun. Menyaringnya keluar — seperti pada `petaSoal()`, di mana itu
           | memang benar — membuat butir yang mustahil dipenuhi berhenti dianggap ada, dan
           | paket yang separuh butirnya tak berkode dilaporkan LENGKAP. Kuncinya memakai id
           | barisnya supaya dua butir tanpa kode tidak saling menimpa. */
        return DB::table('N_PA_Cycle_Question_Package_Item')
            ->where('PA_Cycle_Question_Package_Id', $paketId)
            ->where('Flag_Aktif', 'Y')
            ->get(['Id_PA_Cycle_Question_Package_Item as id', 'Kode_Soal_Sumber as kode'])
            ->mapWithKeys(fn ($b) => [
                ($b->kode === null || $b->kode === '')
                    ? '(butir tanpa kode #'.$b->id.')'
                    : (string) $b->kode => true,
            ])
            ->all();
    }

    /**
     * Menolak kiriman untuk penugasan yang sudah tidak berlaku.
     *
     * Sampai 2 September 2026 pencarian integrasi hanya lewat `Id_Ujian_Token`, tanpa
     * sekali pun melihat penugasannya. Akibatnya kiriman untuk penugasan yang SUDAH
     * DIBATALKAN tetap diterima — dan `tandaiPenugasanSelesai()` hanya berhenti bila
     * statusnya sudah `SELESAI`, sehingga `DIBATALKAN` lolos lalu DITIMPA menjadi
     * `SELESAI`. Terbukti pada uji rantai penuh: enam penugasan yang dibatalkan karena
     * pesertanya dikecualikan, dikirim ulang, dan keenamnya menjadi baris yang berbunyi
     * "dibatalkan" dan "selesai" sekaligus — lengkap dengan `Cancel_Reason` yang masih
     * terpasang di samping `Finished_At`.
     *
     * Gerbang siklus tidak bisa menangkap ini: siklusnya memang sah menerima payload;
     * yang tidak sah adalah PENUGASANNYA. Dua pertanyaan berbeda, dan sebelumnya hanya
     * satu yang ditanyakan.
     */
    private function pastikanPenugasanMasihBerlaku(int $assignmentId): void
    {
        $tugas = DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->first(['Status_Penugasan', 'Flag_Aktif', 'Cancel_Reason']);

        if ($tugas === null) {
            throw new RuntimeException(
                "Penugasan {$assignmentId} tidak ada. Kiriman tidak diterima."
            );
        }

        // Konstantanya milik pembentuk penugasan — dirujuk, bukan disalin, supaya tidak
        // ada dua ejaan "DIBATALKAN" yang suatu hari berselisih.
        if ($tugas->Status_Penugasan !== PaAssignmentBuilderService::STATUS_DIBATALKAN
            && $tugas->Flag_Aktif === 'Y') {
            return;
        }

        /* Sebabnya ikut disebut. Penilai yang mengisi tidak tahu penugasannya dibatalkan
           — ia memegang tautan yang dikirimkan kepadanya — jadi penolakan tanpa
           keterangan akan terbaca sebagai sistem yang rusak. */
        throw new RuntimeException(sprintf(
            'Penugasan %d sudah dibatalkan, sehingga penilaiannya tidak bisa diterima lagi.%s',
            $assignmentId,
            $tugas->Cancel_Reason === null ? '' : ' Sebab pembatalan: '.$tugas->Cancel_Reason,
        ));
    }

    /**
     * Lembar ini SUDAH TERISI PENUH, tetapi belum tentu boleh dihitung.
     *
     * Sampai 7 September 2026 method ini menandainya `SELESAI` seketika, dan itu yang membuat
     * penilaian seseorang ikut menentukan angka orang lain padahal ia sendiri masih punya
     * lembar lain yang belum dikerjakan — bahkan bisa menjadi lembar terakhir yang memicu
     * hasil seorang peserta terbit, di atas pekerjaan yang pengisinya masih berniat meninjau
     * ulang.
     *
     * Sekarang ia berhenti di `TERKUMPUL`. Jawabannya sudah tersimpan dan terpetakan utuh —
     * tidak ada yang hilang, dan penilainya bebas melanjutkan ke lembar berikutnya —
     * tetapi pelepasannya menjadi `SELESAI` milik `PaRaterSubmitService`, sesudah SELURUH
     * tugas penilai itu terkumpul dan ia menekan kirim.
     *
     * `Finished_At` TETAP diisi di sini, bukan menunggu pengiriman. Yang dicatatnya kapan
     * lembar ini dikerjakan; menggesernya ke waktu pengiriman akan membuat seluruh laporan
     * durasi pengerjaan melaporkan waktu tunggu penilai lain.
     *
     * WAKTU DARI HC LEARN, BUKAN WAKTU PEMETAAN. `penilaian.Waktu_Mulai_Akses` dan
     * `Waktu_Selesai_Akses` adalah kapan penilai membuka dan menyelesaikan lembarnya. Waktu
     * pemetaan baru sama dengannya bila kiriman dipetakan seketika; kiriman yang tertahan
     * lalu dipetakan ulang berjam-jam kemudian akan tercatat selesai pada jam pemetaan —
     * terbaca terlambat bila itu lewat tenggat, dan durasinya selalu nol. Waktu pemetaan
     * hanya cadangan bila HC Learn tidak mengirimnya.
     */
    private function tandaiPenugasanTerkumpul(
        int $assignmentId,
        ?int $olehUserId,
        ?Carbon $mulaiAkses = null,
        ?Carbon $selesaiAkses = null,
    ): void {
        $tugas = DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->first(['Status_Penugasan', 'Started_At', 'Finished_At']);

        if ($tugas === null
            || $tugas->Status_Penugasan === self::STATUS_SELESAI
            || $tugas->Status_Penugasan === PaAssignmentBuilderService::STATUS_TERKUMPUL) {
            return;
        }

        $sekarang = FormatTanggalHelper::getCurrentTime();
        $selesai = $selesaiAkses ?? $sekarang;

        // Mulai sesudah selesai berarti datanya rusak; jangan mengarang durasi negatif.
        $mulai = $mulaiAkses !== null && $mulaiAkses->lessThanOrEqualTo($selesai) ? $mulaiAkses : $selesai;

        DB::table('N_PA_Assignment')
            ->where('Id_PA_Assignment', $assignmentId)
            ->update([
                'Status_Penugasan' => PaAssignmentBuilderService::STATUS_TERKUMPUL,
                'Started_At' => $tugas->Started_At ?? $mulai,
                'Finished_At' => $selesai,
                'Updated_At' => $sekarang,
                'Updated_By' => $olehUserId,
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            throw new RuntimeException('Payload tidak bisa diubah kembali menjadi JSON.');
        }

        return $json;
    }

    /**
     * `Esai[]` sebuah bagian sebagai baris siap simpan, atau null bila bagian itu bukan esai.
     *
     * Kontrak HC Learn 26 September 2026: `Flag_Essai` menandai bagian esai, `Jumlah_Esai`
     * SELALU sama dengan panjang `Esai[]`, dan tiap entri membawa `Id_Master_Soal`,
     * `Pertanyaan`, `Jawaban_Peserta`, dan `Waktu_Dijawab`. Penilaian mesin dan koreksi admin
     * mereka tidak disimpan: skor esai tidak dipakai.
     *
     * Ketidakcocokan jumlah DITOLAK. Esai yang hilang di tengah jalan tidak bisa dibedakan dari
     * soal yang memang tidak ditanyakan, dan penilai akan mengkalibrasi daftar yang tidak utuh.
     *
     * @param  array<string, mixed>  $d
     * @return list<array{Id_Master_Soal:?int, Urutan_Tampil:int, Pertanyaan:?string, Jawaban_Teks:?string, Waktu_Dijawab:?string}>|null
     */
    private function esaiPerSoal(array $d, string $kodeSoal, int $payloadId): ?array
    {
        if (! filter_var($d['Flag_Essai'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $daftar = is_array($d['Esai'] ?? null) ? array_values($d['Esai']) : [];
        $jumlah = $this->cacah($d['Jumlah_Esai'] ?? null);

        if ($jumlah !== null && $jumlah !== count($daftar)) {
            throw new RuntimeException(
                "Butir {$kodeSoal} pada payload {$payloadId} menyebut Jumlah_Esai {$jumlah}, "
                .'tetapi Esai[] berisi '.count($daftar).' soal.'
            );
        }

        $hasil = [];

        foreach ($daftar as $i => $e) {
            if (! is_array($e)) {
                continue;
            }

            $idSoal = $this->cacah($e['Id_Master_Soal'] ?? null);
            $waktu = $this->teks($e['Waktu_Dijawab'] ?? null);

            $hasil[] = [
                'Id_Master_Soal' => $idSoal,
                'Urutan_Tampil' => $i + 1,
                'Pertanyaan' => $this->teks($e['Pertanyaan'] ?? null),
                'Jawaban_Teks' => $this->teks($e['Jawaban_Peserta'] ?? null),
                'Waktu_Dijawab' => $waktu !== null && strtotime($waktu) !== false ? date('Y-m-d H:i:s', strtotime($waktu)) : null,
            ];
        }

        return $hasil;
    }

    /**
     * Anak esai untuk baris skor versi ini. Baris skor dicari lewat (payload, versi, kode),
     * karena insert berpotongan tidak mengembalikan id.
     *
     * @param  array<string, list<array<string, mixed>>>  $esaiPerKode
     */
    private function simpanEsai(int $payloadId, int $versi, array $esaiPerKode): void
    {
        $esaiPerKode = array_filter($esaiPerKode);

        if ($esaiPerKode === []) {
            return;
        }

        $idPerKode = DB::table('N_PA_Assignment_Score')
            ->where('PA_Assignment_Payload_Id', $payloadId)
            ->where('Mapper_Version', $versi)
            ->where('Flag_Aktif', 'Y')
            ->whereIn('Kode_Soal_Sumber', array_map('strval', array_keys($esaiPerKode)))
            ->pluck('Id_PA_Assignment_Score', 'Kode_Soal_Sumber');

        foreach ($esaiPerKode as $kode => $daftar) {
            $scoreId = (int) ($idPerKode[$kode] ?? 0);

            // Satu per baris: jawaban esai panjang cepat menembus batas parameter SQL Server.
            foreach ($daftar as $e) {
                DB::table('N_PA_Assignment_Score_Esai')->insert($e + ['PA_Assignment_Score_Id' => $scoreId]);
            }
        }
    }

    /**
     * Waktu akses dari HC Learn (`Y-m-d H:i:s`, WIB). Bentuk lain dianggap tidak ada, supaya
     * teks rusak tidak pernah menjadi tanggal yang tampak sah.
     */
    private function waktuAkses(mixed $nilai): ?Carbon
    {
        $teks = $this->teks($nilai);

        if ($teks === null || ! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $teks)) {
            return null;
        }

        try {
            $waktu = Carbon::createFromFormat('Y-m-d H:i:s', $teks, 'Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }

        // createFromFormat menggeser tanggal mustahil (31 Februari) diam-diam; tolak saja.
        return $waktu !== null && $waktu->format('Y-m-d H:i:s') === $teks ? $waktu : null;
    }

    private function teks(mixed $nilai): ?string
    {
        if ($nilai === null || is_array($nilai)) {
            return null;
        }

        $teks = trim((string) $nilai);

        return $teks === '' ? null : $teks;
    }

    private function angka(mixed $nilai): ?float
    {
        if ($nilai === null || $nilai === '' || is_array($nilai) || ! is_numeric($nilai)) {
            return null;
        }

        return (float) $nilai;
    }

    /**
     * Cacah bulat tak negatif, atau null.
     *
     * Terpisah dari `angka()` karena kolomnya `smallint`: pecahan yang lolos akan
     * dibulatkan diam-diam oleh basis data, dan angka negatif akan menabrak CHECK dengan
     * pesan yang menyebut nama constraint alih-alih butir yang bermasalah.
     */
    private function cacah(mixed $nilai): ?int
    {
        $angka = $this->angka($nilai);

        if ($angka === null || $angka < 0) {
            return null;
        }

        return (int) round($angka);
    }
}
