<?php

namespace App\Services\LMS\SOP;

use App\Helpers\SignatureHelper;
use App\Jobs\LMS\Training\Scheduling\ProcessBulkPenjadwalan;
use App\Services\LMS\SOP\Support\SopStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Vinkla\Hashids\Facades\Hashids;

/**
 * Dispatch kuis SOP → HC Learn (lazy / JIT).
 * launch(): saat karyawan klik "Mulai kuis", pastikan ada token ujian utk
 * (karyawan × item KUIS × VERSI test aktif). Bila sudah ada → reuse; bila belum
 * → mint 1 orang ke HC Learn (Waktu_Mulai=now → tenggat), simpan token + Payload.
 * Token dikunci per Id_SOP_Item_Versi → ganti test = token baru; token lama dikirim
 * sbg Deleted_Tokens. Return ['url'=>Link_Ujian] atau ['_error'=>..,'_code'=>..].
 */
class SopKuisDispatchService
{
    private const DEFAULT_WINDOW_DAYS = 30; // utk SAMPAI_DICABUT

    /**
     * Penanda aplikasi pemanggil, dikirim sebagai Sumber_Aplikasi DAN User_Agent.
     *
     * HC Learn memakainya untuk menentukan ke mana peserta dikembalikan seusai
     * ujian. Tanpa penanda ini ia jatuh ke bawaannya — LMS — sehingga karyawan
     * yang berangkat dari halaman SOP mendarat di modul yang sama sekali lain.
     *
     * Satu tetapan untuk kedua field dan kedua jalur (cetak & perpanjang):
     * menuliskannya empat kali sebagai teks bebas berarti tiga kesempatan untuk
     * menyimpang, dan yang menyimpang di sini gagal DIAM-DIAM — ujiannya tetap
     * jalan, cuma pulangnya salah alamat.
     */
    private const SUMBER = 'SOP';

    /**
     * `sopuniq` — hashid PAKET SOP, penunjuk halaman asal peserta.
     *
     * Paket, bukan item atau versi: halaman yang ditinggalkan peserta beralamat
     * /sop/{hashid_paket}, dan hanya id itu yang cukup untuk menyusunnya kembali.
     *
     * Dikirim di muatan (supaya HC Learn bisa merangkai tautannya sendiri) DAN
     * ditempelkan ke tautan balasan bila belum ada di sana. Rangkap itu
     * disengaja: selama sisi HC Learn belum memakainya, peserta tetap membawa
     * penunjuknya. Aturan yang sama di dua tempat memang pola yang sudah
     * beberapa kali menggigit modul ini — karena itu penempelannya dikurung di
     * SATU fungsi, dan setiap penyerahan tautan wajib melewatinya.
     */
    private function sopuniq(int $idPaket): string
    {
        return Hashids::connection('custom')->encode($idPaket);
    }

    /**
     * Satu-satunya pintu keluar tautan ujian.
     *
     * Ada TIGA tempat yang menyerahkan tautan ke pemanggil: token yang dipakai
     * ulang, token yang baru dicetak, dan token yang diperpanjang. Menempelkan
     * sopuniq di dua di antaranya menghasilkan cacat yang paling sulit
     * dipercaya — sebagian peserta pulang ke tempat yang benar, sebagian tidak,
     * tanpa pola yang kelihatan dari luar.
     */
    private function tautan(?string $link, int $idPaket): ?array
    {
        if (! $link) {
            return null;
        }

        return ['url' => $this->tempelSopuniq($link, $idPaket)];
    }

    /** Tempel sopuniq, kecuali HC Learn sudah menuliskannya sendiri. */
    private function tempelSopuniq(string $link, int $idPaket): string
    {
        if (preg_match('/[?&]sopuniq=/i', $link)) {
            return $link;
        }

        /* Fragment dipisah lebih dulu: query yang ditempel SESUDAH '#' bukan
           query sama sekali — ia jadi bagian dari fragment dan tak pernah
           terbaca server mana pun. */
        [$dasar, $fragment] = array_pad(explode('#', $link, 2), 2, null);
        $dasar .= (str_contains($dasar, '?') ? '&' : '?').'sopuniq='.$this->sopuniq($idPaket);

        return $fragment === null ? $dasar : $dasar.'#'.$fragment;
    }

    /**
     * Batas percobaan ujian (hardcoded, belum configurable).
     * Habisnya jatah BUKAN "kesempatan habis" — itu berarti hasilnya sudah final.
     * Nilai yang berlaku adalah hasil percobaan terakhir (submitKuisResult()).
     */
    public const MAKS_PERCOBAAN = 1;

    public function launch(string $kode, int $idPaket, int $idItem): array
    {
        // 1) item KUIS milik release paket resmi. Jika snapshot belum tersedia
        // (paket lama), fallback ke pointer aktif historis.
        $snapshot = app(SopPaketVersionService::class)->releasedSnapshot($idPaket);
        $snapshotItem = $snapshot
            ? collect($snapshot['items'])->first(fn ($row) => (int) ($row['Id_SOP_Paket_Item'] ?? 0) === $idItem)
            : null;

        $item = $snapshotItem
            ? (object) [
                'Id_SOP_Paket_Item' => (int) $snapshotItem['Id_SOP_Paket_Item'],
                'Tipe_Item' => $snapshotItem['Tipe_Item'] ?? null,
                'Id_Item_Versi_Aktif' => $snapshotItem['Id_SOP_Item_Versi'] ?? null,
                'Judul_Item' => $snapshotItem['Judul_Item'] ?? null,
                'Flag_Camera' => $snapshotItem['Flag_Camera'] ?? 'T',
            ]
            : DB::table('N_SOP_Paket_Item')
                ->where('Id_SOP_Paket_Item', $idItem)
                ->where('Id_SOP_Paket', $idPaket)
                ->where('Flag_Aktif', SopStatus::AKTIF)
                ->first(['Id_SOP_Paket_Item', 'Tipe_Item', 'Id_Item_Versi_Aktif', 'Judul_Item', 'Flag_Camera']);
        if (! $item) {
            return ['_error' => 'Item tidak ditemukan', '_code' => 404];
        }
        if ($item->Tipe_Item !== 'KUIS') {
            return ['_error' => 'Item ini bukan kuis', '_code' => 422];
        }
        if (! $item->Id_Item_Versi_Aktif) {
            return ['_error' => 'Kuis belum menautkan ujian', '_code' => 409];
        }

        $versi = DB::table('N_SOP_Item_Versi')
            ->where('Id_SOP_Item_Versi', $item->Id_Item_Versi_Aktif)
            ->first(['Id_SOP_Item_Versi', 'Id_Master_Ujian']);
        if (! $versi || empty($versi->Id_Master_Ujian)) {
            return ['_error' => 'Ujian belum diset pada versi aktif', '_code' => 409];
        }

        // 2) penugasan aktif karyawan utk paket ini (IDOR-safe)
        $detail = DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->first([
                'd.Id_SOP_Penjadwalan_Detail', 'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',
                'pj.Id_SOP_Penjadwalan', 'pj.Created_By', // → identitas penandatangan ke HC Learn
            ]);
        if (! $detail) {
            // SOP PUBLIK: pembaca boleh mengerjakan kuis walau tak pernah ditugaskan.
            // N_SOP_Progress berkunci pada Id_SOP_Penjadwalan_Detail, jadi skor &
            // jumlah percobaan tak punya tempat disimpan tanpa penugasan — karena itu
            // dibuatkan penugasan MANDIRI, bukan dibuatkan jalur progres terpisah.
            $detail = $this->daftarMandiri($kode, $idPaket);
            if (! $detail) {
                return ['_error' => 'dokumen ini tidak ditugaskan kepada Anda', '_code' => 403];
            }
        }

        // Penegakan prasyarat urutan (SEKUENSIAL): kuis terkunci tak boleh dimulai.
        if (\App\Services\LMS\SOP\Support\SopSequenceGate::isLocked($kode, $idPaket, $idItem)) {
            return ['_error' => 'Selesaikan dulu item wajib sebelumnya (dokumen ini berurutan).', '_code' => 423];
        }

        // Gerbang presensi — mencetak token ujian sebelum peserta hadir itu
        // membuka celah paling jelas dari seluruh rantai ini.
        if (! app(SopAbsensiService::class)->boleh($kode, $idPaket)) {
            return ['_error' => 'Lakukan presensi kehadiran dulu sebelum memulai kuis.', '_code' => 423];
        }

        /*
         * 2b) Kebijakan percobaan (MAKS_PERCOBAAN, hardcoded).
         *
         * Selama jatah masih ada, peserta boleh mengulang. Begitu jatah terpakai
         * habis, itu BUKAN "kesempatan habis" melainkan HASIL AKHIR: nilai yang
         * berlaku adalah hasil percobaan terakhir (lihat submitKuisResult()).
         * Jadi pesan di sini menyampaikan finalitas, bukan kehilangan sesuatu.
         *
         * Attempt hanya bertambah lewat callback hasil (submit), jadi sesi yang
         * kedaluwarsa tanpa submit tidak memakan jatah.
         */
        $prog = DB::table('N_SOP_Progress')
            ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
            ->where('Id_SOP_Paket_Item', $idItem)
            ->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->first(['Status', 'Jumlah_Percobaan', 'Skor_Quiz', 'Id_Ujian_Token_Hasil']);
        if ($prog && in_array($prog->Status, [SopStatus::ITEM_SELESAI, SopStatus::ITEM_DIKONFIRMASI], true)) {
            return ['_error' => 'Kamu sudah menyelesaikan kuis ini.', '_code' => 409];
        }
        if ($prog && (int) $prog->Jumlah_Percobaan >= self::MAKS_PERCOBAAN) {
            $nilai = $prog->Skor_Quiz !== null ? rtrim(rtrim(number_format((float) $prog->Skor_Quiz, 2, '.', ''), '0'), '.') : null;

            return [
                '_error' => $nilai !== null
                    ? "Kuis ini sudah selesai dikerjakan. Nilai akhir kamu: {$nilai}."
                    : 'Kuis ini sudah selesai dikerjakan.',
                '_code' => 409,
            ];
        }

        /*
         * Metadata konfirmasi: rekam IP/perangkat saat peserta MEMULAI kuis —
         * titik interaksi nyata, karena callback HC Learn tak membawa IP peserta.
         *
         * Baris progress-nya BELUM TENTU ADA: item kuis yang ditambahkan setelah
         * penjadwalan baru ter-seed saat reconcile. Dulu update ini mengenai nol
         * baris dan lolos tanpa suara, lalu callback membuat baris baru TANPA
         * IP/UA — jejak auditnya bolong justru pada item yang paling perlu.
         */
        $meta = [
            'Konfirmasi_IP' => substr((string) request()->ip(), 0, 45),
            'Konfirmasi_User_Agent' => substr((string) request()->userAgent(), 0, 255),
            'Updated_At' => now(),
        ];
        if ($prog) {
            DB::table('N_SOP_Progress')
                ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
                ->where('Id_SOP_Paket_Item', $idItem)
                ->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
                ->update($meta);
        } else {
            DB::table('N_SOP_Progress')->insert(array_merge($meta, [
                'Id_SOP_Penjadwalan_Detail' => $detail->Id_SOP_Penjadwalan_Detail,
                'Id_SOP_Paket_Item' => $idItem,
                'Id_SOP_Item_Versi' => $versi->Id_SOP_Item_Versi,
                'Kode_Karyawan' => $kode,
                'Tipe_Item' => 'KUIS',
                'Status' => SopStatus::ITEM_DIBUKA,
                'Waktu_Buka' => now(),
                'Durasi_Aktif_Detik' => 0,
                'Jumlah_Percobaan' => 0,
                'Created_At' => now(),
            ]));
        }

        // 3) reuse token utk (detail × item × versi) — HANYA bila jendela ujian belum lewat.
        //    Jika Waktu_Akhir sudah lewat (token mati) → jatuh ke mint() untuk re-mint
        //    (perbarui window + token). Ini BUKAN percobaan baru (belum ada submit).
        $existing = DB::table('N_SOP_Penjadwalan_Integrasi')
            ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
            ->where('Id_SOP_Paket_Item', $idItem)
            ->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
            ->where('Flag_Aktif', SopStatus::AKTIF)
            ->first();
        if ($existing && $existing->Id_Ujian_Token && $existing->Payload_Json) {
            /*
             * Sesi ini sudah pernah menyetor hasil? Kolom Id_Ujian_Token_Hasil di
             * progress mencatatnya (dipakai juga sebagai kunci anti-dobel callback).
             * Kalau ya, Link_Ujian lama menunjuk sesi yang SUDAH DIKERJAKAN — memberi
             * link itu lagi hanya mengantar peserta ke halaman ujian yang telah
             * tertutup. Percobaan ulang harus memakai sesi BARU.
             */
            $sesiTerpakai = $prog
                && (string) ($prog->Id_Ujian_Token_Hasil ?? '') === (string) $existing->Id_Ujian_Token;

            $windowExpired = $existing->Waktu_Akhir && Carbon::parse($existing->Waktu_Akhir)->lt(Carbon::now());
            if (! $windowExpired && ! $sesiTerpakai) {
                $link = $this->extractLink($existing->Payload_Json);
                $hasil = $this->tautan($link, $idPaket);
                if ($hasil) {
                    return $hasil;
                }
            }

            if ($sesiTerpakai) {
                // Percobaan ulang → cetak sesi baru; token lama ikut dimatikan di mint().
                return $this->mint($kode, $item, $versi, $detail, $existing, $idPaket);
            }

            // 4a) Jendela habis → PERPANJANG token yang sama, jangan cetak sesi baru.
            //     Mengikuti pola "jadwalkan ulang" LMS (LmsPenjadwalanTrainingController:1088):
            //     token dipertahankan, hanya jendela & payload yang disegarkan. Ini penting
            //     karena token = kunci routing callback; membuangnya berarti hasil ujian
            //     yang sedang dikirim ulang HC Learn jadi tak dikenali lagi.
            $res = $this->perpanjang($item, $versi, $detail, $existing, $idPaket);
            if ($res !== null) {
                return $res;
            }
            // Gagal memperpanjang (mis. token sudah dihapus di HC Learn) → cetak baru.
        }

        // 4b) mint ke HC Learn (window baru = now → tenggat / +30 hari)
        return $this->mint($kode, $item, $versi, $detail, $existing, $idPaket);
    }

    private function mint(string $kode, object $item, object $versi, object $detail, $existing, int $idPaket): array
    {
        $karyawan = DB::table('Karyawan')->where('Kode_Karyawan', $kode)->first();
        if (! $karyawan) {
            return ['_error' => 'Data karyawan tidak ditemukan', '_code' => 404];
        }

        // Identitas penandatangan ke HC Learn — BUKAN milik peserta.
        $ident = $this->resolveIdentitas($detail);
        if (! $ident) {
            Log::warning('SOP kuis: identitas HC Learn tak tersedia', [
                'penjadwalan' => $detail->Id_SOP_Penjadwalan ?? null,
                'created_by' => $detail->Created_By ?? null,
            ]);

            return [
                '_error' => 'Ujian belum bisa dibuka: penjadwal dokumen ini tidak tertaut integrasi HC Learn. Hubungi admin.',
                '_code' => 503,
            ];
        }
        [$appId, $kodeAuth] = $ident;

        [$wMulai, $wAkhir] = $this->hitungWindow($detail);

        $dataKaryawan = [
            'Kode_Karyawan' => $karyawan->Kode_Karyawan,
            'Nama' => $karyawan->Nama ?? '-',
            'No_Hp' => $karyawan->HP ?? '-',
            'UserID_Web' => $karyawan->UserID_Web ?? '-',
            'UserID_Absen' => $karyawan->UserID_Absen ?? '-',
            'Tgl_Lahir' => $karyawan->Tgl_Lahir ?? null,
            'ID_Level_Jabatan' => $karyawan->ID_Level_Jabatan ?? null,
            'ID_Divisi_Sub_Divisi' => $karyawan->ID_Divisi_Sub_Divisi ?? null,
        ];

        $deleted = $this->tokenVersiLain($detail, $item, $versi);

        // Sampai di sini hanya bila token versi ini memang tak terpakai lagi:
        // belum pernah ada, atau perpanjangannya ditolak HC Learn.
        if ($existing && ! empty($existing->Id_Ujian_Token)) {
            $deleted[] = $existing->Id_Ujian_Token;
        }

        // placeholder integrasi (reuse baris bila ada tanpa token)
        if ($existing) {
            $integrasiId = $existing->Id_SOP_Penjadwalan_Integrasi;
            DB::table('N_SOP_Penjadwalan_Integrasi')->where('Id_SOP_Penjadwalan_Integrasi', $integrasiId)->update([
                'Waktu_Mulai' => $wMulai, 'Waktu_Akhir' => $wAkhir, 'Updated_At' => now(),
            ]);
        } else {
            $integrasiId = $this->klaimBarisIntegrasi($detail, $item, $versi, $kode, $wMulai, $wAkhir);
        }

        // HC Learn store mengharap kontrak SAMA dgn LMS (ProcessBulkPenjadwalan):
        // - Id_Integrasi bentuk MAP per-karyawan (bukan skalar) → utk memetakan token balik.
        // - Wajib ada Flag_Camera + User_Agent, kalau tidak store balas 500.
        $payload = [
            // Penanda asal — penentu tujuan "kembali" di HC Learn. Lihat self::SUMBER.
            'Sumber_Aplikasi' => self::SUMBER,
            // Penunjuk halaman asal peserta — lihat sopuniq().
            'Sopuniq' => $this->sopuniq($idPaket),
            'Id_Integrasi' => [['Kode_Karyawan' => $kode, 'Id_Integrasi' => $integrasiId]],
            'Id_Master_Ujian' => $versi->Id_Master_Ujian,
            'Waktu_Mulai' => $wMulai->format('Y-m-d H:i:s'),
            'Waktu_Akhir' => $wAkhir->format('Y-m-d H:i:s'),
            'Jenis_User' => 'internal',
            'Flag_Camera' => ($item->Flag_Camera ?? 'T') === 'Y' ? true : null, // proktoring per item KUIS
            /* Penanda PEMANGGIL, bukan jejak perangkat — HC Learn ikut membacanya
               untuk menentukan tujuan kembali. UA peramban asli tetap direkam ke
               Konfirmasi_User_Agent saat peserta memulai kuis. */
            'User_Agent' => self::SUMBER,
            'Id_Karyawan' => [$kode],
            'Data_Karyawan' => [$dataKaryawan],
            'Deleted_Tokens' => $deleted,
        ];

        $endpoint = 'api/v1/penjadwalan/training/store';
        $url = rtrim((string) config('services.frans.hclearn_api.domain'), '/').'/'.ltrim($endpoint, '/');

        try {
            $response = $this->kirimStore($endpoint, $url, $payload, $appId, $kodeAuth);

            /*
             * Identitas penjadwal ditolak HC Learn (bukan cuma "kosong").
             * Kasus nyata: admin yang punya User_Key tapi akunnya tak dikenal di
             * seberang, atau sudah resign. resolveIdentitas() menganggapnya sah
             * karena kolomnya terisi, jadi lapis 2 tak pernah tersentuh — peserta
             * buntu padahal akun layanan tersedia. Di sini dicoba ulang SEKALI.
             */
            if (in_array($response->status(), [401, 403], true)) {
                $layanan = $this->identitasLayanan();
                if ($layanan && $layanan[0] !== $appId) {
                    Log::warning('SOP kuis: identitas penjadwal ditolak, beralih ke akun layanan', [
                        'status' => $response->status(), 'kode' => $kode,
                    ]);
                    $response = $this->kirimStore($endpoint, $url, $payload, $layanan[0], $layanan[1]);
                }
            }

            if (! $response->successful()) {
                $msg = $response->json()['message'] ?? 'Gagal menyiapkan ujian di HC Learn';
                if (in_array($response->status(), [401, 403], true)) {
                    $msg = 'Ujian belum bisa dibuka: identitas integrasi HC Learn ditolak. Hubungi admin.';
                }
                Log::warning('SOP kuis mint gagal', ['status' => $response->status(), 'kode' => $kode, 'msg' => $msg]);

                return ['_error' => $msg, '_code' => $response->status()];
            }

            $rows = $response->json()['result'] ?? ($response->json()['data'] ?? []);
            $token = null;
            $payloadJson = null;
            foreach ($rows as $r) {
                if (isset($r['Id_Ujian_Token'])) {
                    $token = $r['Id_Ujian_Token'];
                    $payloadJson = is_array($r['Payload_Json'] ?? null) ? json_encode($r['Payload_Json']) : ($r['Payload_Json'] ?? null);
                    break;
                }
            }
            if (! $token) {
                return ['_error' => 'HC Learn tidak mengembalikan token ujian', '_code' => 502];
            }

            DB::table('N_SOP_Penjadwalan_Integrasi')->where('Id_SOP_Penjadwalan_Integrasi', $integrasiId)->update([
                'Id_Ujian_Token' => $token,
                'Payload_Json' => $payloadJson,
                'Updated_At' => now(),
            ]);

            $link = $this->extractLink($payloadJson);
            if (! $link) {
                return ['_error' => 'Link ujian tidak tersedia dari HC Learn', '_code' => 502];
            }

            return $this->tautan($link, $idPaket);
        } catch (\Exception $e) {
            Log::error('SOP kuis mint gagal', ['exception' => $e]);

            return ['_error' => 'Terjadi kesalahan saat menyiapkan ujian', '_code' => 500];
        }
    }

    /**
     * Perpanjang sesi ujian yang jendelanya sudah lewat — TOKEN DIPERTAHANKAN.
     *
     * Mengikuti pola "jadwalkan ulang" LMS: POST ke
     * api/v1/penjadwalan/training/update/{token}, lalu Payload_Json balasan
     * menimpa yang lama pada baris integrasi YANG SAMA.
     *
     * Kenapa bukan cetak sesi baru:
     *   - Token adalah kunci routing callback. Membuangnya membuat hasil ujian
     *     yang sedang dikirim ulang HC Learn (mereka melakukan retry — terlihat
     *     di log: satu token, tiga panggilan) jadi tak dikenali lagi.
     *   - Baris integrasi tetap satu per (detail × item × versi), jadi riwayat
     *     percobaan tak terpecah.
     *
     * @return array{url:string}|array{_error:string,_code:int}|null
     *                                                               null = tak berhasil diperpanjang; pemanggil boleh mencetak baru.
     */
    private function perpanjang(object $item, object $versi, object $detail, object $existing, int $idPaket): ?array
    {
        $ident = $this->resolveIdentitas($detail);
        if (! $ident) {
            return null;
        } // biar mint() yang melaporkan sebabnya
        [$appId, $kodeAuth] = $ident;

        [$wMulai, $wAkhir] = $this->hitungWindow($detail);

        /* Penandanya ikut di sini juga: perpanjangan memakai token yang SAMA,
           dan tanpa penanda asal peserta yang tokennya kebetulan masih hidup
           tetap dikembalikan ke LMS. Cacat yang muncul pada sebagian orang jauh
           lebih sulit dipercaya daripada cacat yang muncul pada semua. */
        $payload = [
            'Sumber_Aplikasi' => self::SUMBER,
            'Sopuniq' => $this->sopuniq($idPaket),
            'Id_Master_Ujian' => $versi->Id_Master_Ujian,
            'Waktu_Mulai' => $wMulai->format('Y-m-d H:i:s'),
            'Waktu_Akhir' => $wAkhir->format('Y-m-d H:i:s'),
            'Flag_Camera' => ($item->Flag_Camera ?? 'T') === 'Y' ? true : null,
            'User_Agent' => self::SUMBER,
            // hanya token VERSI LAIN yang dimatikan; token versi ini justru dipakai lagi
            'Deleted_Tokens' => $this->tokenVersiLain($detail, $item, $versi),
        ];

        $endpoint = 'api/v1/penjadwalan/training/update/'.$existing->Id_Ujian_Token;
        $url = rtrim((string) config('services.frans.hclearn_api.domain'), '/').'/'.ltrim($endpoint, '/');

        try {
            // LMS pun menandatangani endpoint update ini dengan store() (metodenya POST).
            $auth = SignatureHelper::store($endpoint, $payload, $appId, $kodeAuth);
            $response = Http::withHeaders(array_merge($auth['headers'], ['Accept' => 'application/json']))
                ->withBody($auth['body'], 'application/json')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('SOP kuis perpanjang gagal — jatuh ke cetak baru', [
                    'status' => $response->status(),
                    'token' => $existing->Id_Ujian_Token,
                ]);

                return null;
            }

            $rows = $response->json()['result'] ?? ($response->json()['data'] ?? []);
            $payloadJson = null;
            $tokenBaru = null;
            foreach ($rows as $r) {
                if (isset($r['Payload_Json'])) {
                    $payloadJson = is_array($r['Payload_Json']) ? json_encode($r['Payload_Json']) : $r['Payload_Json'];
                    $tokenBaru = $r['Id_Ujian_Token'] ?? null; // biasanya sama; dihormati bila berubah
                    break;
                }
            }
            // Balasan tanpa payload baru tetap sah: jendelanya yang diperpanjang,
            // Link_Ujian lama masih menunjuk sesi yang sama.
            $payloadJson = $payloadJson ?: $existing->Payload_Json;

            $set = [
                'Waktu_Mulai' => $wMulai,
                'Waktu_Akhir' => $wAkhir,
                'Payload_Json' => $payloadJson,
                'Updated_At' => now(),
            ];
            if ($tokenBaru && $tokenBaru !== $existing->Id_Ujian_Token) {
                $set['Id_Ujian_Token'] = $tokenBaru;
            }
            DB::table('N_SOP_Penjadwalan_Integrasi')
                ->where('Id_SOP_Penjadwalan_Integrasi', $existing->Id_SOP_Penjadwalan_Integrasi)
                ->update($set);

            $link = $this->extractLink($payloadJson);
            if (! $link) {
                return null;
            }

            Log::info('SOP kuis: sesi diperpanjang', [
                'integrasi' => $existing->Id_SOP_Penjadwalan_Integrasi,
                'sampai' => $wAkhir->toDateTimeString(),
            ]);

            return $this->tautan($link, $idPaket);
        } catch (\Exception $e) {
            Log::error('SOP kuis perpanjang gagal', ['exception' => $e]);

            return null;
        }
    }

    /** Satu panggilan store ke HC Learn dengan identitas tertentu. */
    private function kirimStore(string $endpoint, string $url, array $payload, string $appId, string $kodeAuth)
    {
        $auth = SignatureHelper::store($endpoint, $payload, $appId, $kodeAuth);

        return Http::withHeaders(array_merge($auth['headers'], ['Accept' => 'application/json']))
            ->withBody($auth['body'], 'application/json')
            ->post($url);
    }

    /** Jendela ujian: sekarang → tenggat penugasan, atau +30 hari bila SAMPAI_DICABUT. */
    private function hitungWindow(object $detail): array
    {
        $wMulai = Carbon::now();
        $wAkhir = ($detail->Tipe_Tenggat === 'DEADLINE' && $detail->Tanggal_Deadline)
            ? Carbon::parse($detail->Tanggal_Deadline)
            : Carbon::now()->addDays(self::DEFAULT_WINDOW_DAYS);
        if ($wAkhir->lte($wMulai)) {
            $wAkhir = $wMulai->copy()->addDays(self::DEFAULT_WINDOW_DAYS);
        }

        return [$wMulai, $wAkhir];
    }

    /** Token milik VERSI LAIN pada item yang sama → dimatikan di HC Learn. */
    private function tokenVersiLain(object $detail, object $item, object $versi): array
    {
        return DB::table('N_SOP_Penjadwalan_Integrasi')
            ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
            ->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
            ->where('Id_SOP_Item_Versi', '<>', $versi->Id_SOP_Item_Versi)
            ->whereNotNull('Id_Ujian_Token')
            ->pluck('Id_Ujian_Token')->all();
    }

    /**
     * Klaim baris N_SOP_Penjadwalan_Integrasi untuk (detail × item × versi).
     *
     * Baris ini dijaga UQ_N_SOP_Penjadwalan_Integrasi yang TANPA filter, sedangkan
     * kueri pencarinya di launch() menyaring Flag_Aktif='Y'. Dua celah lahir dari
     * ketimpangan itu:
     *
     *   1. Dua permintaan kembar — tautan kuis dibuka lewat GET tanpa throttle dan
     *      tanpa kunci idempotensi, jadi klik ganda, tombol kembali, atau prefetch
     *      peramban cukup untuk membuat keduanya sama-sama melihat baris belum ada.
     *      Yang kalah cepat menabrak unique dan peserta menerima 500 saat memulai
     *      ujian. Kegagalannya terjadi SEBELUM permintaan ke HC Learn, jadi tak ada
     *      token yatim di seberang — hanya peluncuran yang gagal.
     *
     *   2. Baris nonaktif yang tak terlihat kueri tapi tetap menabrak unique. Saat
     *      ini itu tak terjangkau: satu-satunya penulis Flag_Aktif='T' adalah
     *      pencabutan akses, yang sekaligus menyetel Flag_Cabut_Akses='Y' sehingga
     *      pesertanya tak lagi sampai ke sini, dan tak ada jalur yang memulihkan
     *      detail tercabut. Ditangani juga karena pemulihan seperti itu wajar
     *      ditambahkan suatu hari, dan biayanya di sini nol.
     *
     * Keduanya berujung sama: barisnya memang sudah ada, dan itulah yang diinginkan.
     * Jadi pelanggaran unique diperlakukan sebagai "sudah diklaim", lalu barisnya
     * dipakai ulang dan dihidupkan kembali — bukan dijadikan galat.
     */
    private function klaimBarisIntegrasi(
        object $detail,
        object $item,
        object $versi,
        string $kode,
        $wMulai,
        $wAkhir,
    ): int {
        try {
            return (int) DB::table('N_SOP_Penjadwalan_Integrasi')->insertGetId([
                'Id_SOP_Penjadwalan_Detail' => $detail->Id_SOP_Penjadwalan_Detail,
                'Id_SOP_Paket_Item' => $item->Id_SOP_Paket_Item,
                'Id_SOP_Item_Versi' => $versi->Id_SOP_Item_Versi,
                'Id_Master_Ujian' => $versi->Id_Master_Ujian,
                'Kode_Karyawan' => $kode,
                'Waktu_Mulai' => $wMulai,
                'Waktu_Akhir' => $wAkhir,
                'Created_At' => now(),
                'Flag_Aktif' => SopStatus::AKTIF,
            ], 'Id_SOP_Penjadwalan_Integrasi');
        } catch (QueryException $e) {
            // 2627 = pelanggaran UNIQUE/PK, 2601 = pelanggaran index unik.
            if (! in_array((int) ($e->errorInfo[1] ?? 0), [2601, 2627], true)) {
                throw $e;
            }

            $baris = DB::table('N_SOP_Penjadwalan_Integrasi')
                ->where('Id_SOP_Penjadwalan_Detail', $detail->Id_SOP_Penjadwalan_Detail)
                ->where('Id_SOP_Paket_Item', $item->Id_SOP_Paket_Item)
                ->where('Id_SOP_Item_Versi', $versi->Id_SOP_Item_Versi)
                ->first(['Id_SOP_Penjadwalan_Integrasi']);

            // Unique dilanggar tapi barisnya tak ditemukan berarti dugaan di atas
            // keliru; lebih baik galat aslinya naik daripada ditelan diam-diam.
            if (! $baris) {
                throw $e;
            }

            DB::table('N_SOP_Penjadwalan_Integrasi')
                ->where('Id_SOP_Penjadwalan_Integrasi', $baris->Id_SOP_Penjadwalan_Integrasi)
                ->update([
                    'Waktu_Mulai' => $wMulai,
                    'Waktu_Akhir' => $wAkhir,
                    'Flag_Aktif' => SopStatus::AKTIF,
                    'Updated_At' => now(),
                ]);

            return (int) $baris->Id_SOP_Penjadwalan_Integrasi;
        }
    }

    /**
     * Pendaftaran mandiri pada SOP berdistribusi PUBLIK.
     * Hanya untuk paket PUBLISHED + PUBLIK; paket TERBATAS tetap menolak.
     * Created_By sengaja NULL — tak ada admin yang menugaskan, sehingga rantai
     * identitas jatuh ke akun layanan.
     *
     * @return object|null baris detail yang sama bentuknya dengan query di launch()
     */
    private function daftarMandiri(string $kode, int $idPaket): ?object
    {
        $paket = DB::table('N_SOP_Master_Paket')
            ->where('Id_SOP_Paket', $idPaket)
            ->first(['Status', 'Flag_Aktif', 'Mode_Distribusi']);
        if (! $paket
            || $paket->Status !== SopStatus::PAKET_PUBLISHED
            || $paket->Flag_Aktif !== SopStatus::AKTIF
            || ($paket->Mode_Distribusi ?? 'PUBLIK') !== 'PUBLIK') {
            return null;
        }

        try {
            app(SopPenjadwalanService::class)->assign(
                $idPaket,
                [$kode],
                ['sumber' => 'MANDIRI', 'tipe_tenggat' => 'SAMPAI_DICABUT'],
                null,
            );
        } catch (\Throwable $e) {
            Log::error('SOP kuis daftarMandiri gagal', ['exception' => $e]);

            return null;
        }

        // Baca ulang dengan bentuk & penyaringan yang sama seperti di launch().
        return DB::table('N_SOP_Penjadwalan_Detail as d')
            ->join('N_SOP_Penjadwalan as pj', 'pj.Id_SOP_Penjadwalan', '=', 'd.Id_SOP_Penjadwalan')
            ->where('pj.Id_SOP_Paket', $idPaket)
            ->where('d.Kode_Karyawan', $kode)
            ->where('d.Flag_Aktif', SopStatus::AKTIF)
            ->where('d.Flag_Cabut_Akses', 'T')
            ->orderByDesc('d.Id_SOP_Penjadwalan_Detail')
            ->first([
                'd.Id_SOP_Penjadwalan_Detail', 'pj.Tipe_Tenggat', 'pj.Tanggal_Deadline',
                'pj.Id_SOP_Penjadwalan', 'pj.Created_By',
            ]);
    }

    /**
     * Rantai identitas penandatangan ke HC Learn.
     *
     * Peserta TIDAK menandatangani apa pun — pola ini menyalin LMS
     * (LmsPenjadwalanTrainingController:779, ProcessBulkPenjadwalan), di mana
     * yang dipakai adalah identitas ADMIN PENJADWAL. Memakai User_Key peserta
     * membuat 86% karyawan mentok, karena kolom itu memang hanya diisi untuk
     * segelintir akun terintegrasi.
     *
     * Urutan:
     *   1. Pembuat penjadwalan (Created_By) → dilihat HIDUP, bukan snapshot,
     *      supaya rotasi kunci tak membuat data basi diam-diam.
     *   2. Akun layanan SOP dari config → menutup assign otomatis, resertifikasi,
     *      pendaftaran mandiri SOP publik, dan admin yang sudah nonaktif.
     *   3. null → pemanggil menolak dengan pesan yang bisa ditindaklanjuti.
     *
     * @return array{0:string,1:string}|null [User_Key, Kode_Users]
     */
    private function resolveIdentitas(object $detail): ?array
    {
        return $this->identitasPenjadwal($detail) ?? $this->identitasLayanan();
    }

    /**
     * Lapis 1 — identitas admin pembuat penjadwalan.
     *
     * SENGAJA tanpa filter Flag_Aktif: admin yang sudah resign tetap dipakai
     * identitasnya. Penjadwalan yang dia buat masih sah, dan yang kita butuhkan
     * cuma kunci penandatangan, bukan hak akses orangnya. Menyaring yang aktif
     * saja justru membuat seluruh penugasan lamanya mendadak buntu.
     * (Bila HC Learn di seberang menolak akun resign, mint() akan jatuh ke
     * lapis 2 lewat penanganan 401/403.)
     */
    private function identitasPenjadwal(object $detail): ?array
    {
        if (empty($detail->Created_By)) {
            return null;
        }

        $u = DB::table('KPI_Users')->where('Id_Users', $detail->Created_By)->first(['User_Key', 'Kode_Users']);

        return ($u && ! empty($u->User_Key) && ! empty($u->Kode_Users))
            ? [(string) $u->User_Key, (string) $u->Kode_Users]
            : null;
    }

    /** Lapis 2 — akun layanan SOP. Boleh kosong; pemanggil menanganinya. */
    private function identitasLayanan(): ?array
    {
        $key = config('services.frans.hclearn_api.service_user_key');
        $kode = config('services.frans.hclearn_api.service_kode_users');

        // Keduanya harus ada: HC Learn memverifikasi X-App-ID & X-Kode-Karyawan
        // sebagai pasangan — satu terisi saja tetap ditolak 401.
        return (! empty($key) && ! empty($kode)) ? [(string) $key, (string) $kode] : null;
    }

    private function extractLink(?string $payloadJson): ?string
    {
        if (! $payloadJson) {
            return null;
        }
        $p = json_decode($payloadJson, true);

        return is_array($p) ? ($p['Link_Ujian'] ?? null) : null;
    }
}
