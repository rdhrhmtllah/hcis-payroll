<?php

namespace App\Helpers;

use App\Jobs\SYSTEM\Notification\WhatsApp\SendWhatsappJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsappLogHelper
{
    public static function normalizeNumber($hp)
    {
        $hp = trim($hp);
        if (preg_match('/^0\d+$/', $hp)) {
            $hp = '62'.substr($hp, 1);
        }
        if (preg_match('/^\+62\d+$/', $hp)) {
            $hp = substr($hp, 1);
        }

        return $hp;
    }

    /**
     * Kirim pesan WhatsApp dan log hasilnya
     *
     * @param  callable|null  $onSuccess  Callback jika sukses
     * @param  callable|null  $onFail  Callback jika gagal
     */
    public static function sendAndLog($kodeKaryawan, $nama, $hp, $noTransaksi, array $pesan, ?callable $onSuccess = null, ?callable $onFail = null)
    {
        // dd($kodeKaryawan, $nama, $hp, $noTransaksi, $pesan, $onSuccess, $onFail);
        $hp = self::normalizeNumber($hp);
        $isValidNumber = preg_match('/^62[1-9][0-9]{7,13}$/', $hp);

        if (! $hp || ! $isValidNumber) {
            $msg = 'Nomor HP tidak valid! Format harus 628XXXXXXXXXX';
            self::insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, 'FAILED', $msg);
            Log::warning("Nomor WA tidak valid: {$hp}", compact('nama', 'kodeKaryawan'));
            if ($onFail) {
                $onFail('invalid_number', null);
            }

            return ['status' => 'failed', 'reason' => $msg];
        }

        $pesan['to'] = $hp;

        // SEMUA pengiriman WA lewat queue (Cloud Tasks): HTTP dijalankan di worker,
        // request TAK PERNAH menunggu HTTP (~10-20 dtk) maupun menahan lock DB
        // (penyebab "Login timeout expired"). after_commit=true pada koneksi
        // cloudtasks membuat dispatch yang terjadi DI DALAM transaksi otomatis
        // menunggu commit (tak ada WA "phantom" untuk transaksi yang rollback).
        //
        // Callback closure ($onSuccess/$onFail) TIDAK dipakai lagi: isinya hanya
        // logging + increment kolom `angka_wa` yang WRITE-ONLY (tak pernah dibaca
        // di repo). Metrik jumlah WA per approver tetap bisa diturunkan dari
        // Whatsapp_Log bila perlu. Parameter dipertahankan agar signature pemanggil
        // tidak berubah, tetapi diabaikan.
        SendWhatsappJob::dispatch($kodeKaryawan, $nama, $hp, $noTransaksi, $pesan);

        return ['status' => 'queued'];
    }

    /**
     * Eksekusi nyata pengiriman WA + logging. Dipanggil: (a) setelah commit / di
     * luar transaksi untuk jalur ber-callback, atau (b) dari SendWhatsappJob
     * (worker) untuk jalur tanpa callback. Public agar bisa dipanggil job.
     */
    public static function performSend($kodeKaryawan, $nama, $hp, $noTransaksi, array $pesan, ?callable $onSuccess = null, ?callable $onFail = null)
    {
        try {
            $response = Whatsapp::send_message($pesan, [
                'kode_karyawan' => $kodeKaryawan,
                'nama' => $nama,
                'hp' => $hp,
                'no_transaksi' => $noTransaksi,
                'module' => 'HCIS',
                'source_context' => 'WHATSAPP_LOG_HELPER',
            ]);

            // $status = (
            //     isset($response['messages'][0]['message_status']) &&
            //     in_array($response['messages'][0]['message_status'], ['accepted', 'sent'])
            // ) ? 'SUCCESS' : 'FAILED';
            $status = (
                isset($response['messages'][0]['id']) ||
                in_array($response['messages'][0]['message_status'] ?? null, ['accepted', 'sent'])
            ) ? 'SUCCESS' : 'FAILED';

            $messageId = $response['messages'][0]['id'] ?? null;
            $errorMessage = $status === 'SUCCESS' ? null : json_encode($response, JSON_UNESCAPED_UNICODE);

            self::insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, $status, $errorMessage, $messageId, $pesan);

            if ($status === 'SUCCESS') {
                Log::channel('WhatsappHelperLog')->info("WA sukses dikirim ke {$hp}", ['response' => $response]);
                if ($onSuccess) {
                    $onSuccess($response);
                }
            } else {
                Log::channel('WhatsappHelperLog')->warning("Gagal kirim WA ke {$hp}", ['response' => $response]);
                if ($onFail) {
                    $onFail('api_failed', $response);
                }
            }

            return ['status' => strtolower($status), 'response' => $response];
        } catch (\Throwable $e) {
            self::insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, 'FAILED', $e->getMessage(), null, $pesan);
            Log::channel('WhatsappHelperLog')->error('Gagal kirim WA (exception): '.$e->getMessage());
            if ($onFail) {
                $onFail('exception', $e);
            }

            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    public static function insertLog($kodeKaryawan, $nama, $hp, $noTransaksi, $status, $errorMessage = null, $responseMessage = null, $pesan = null)
    {
        DB::table('Whatsapp_Log')->insert([
            'Kode_Karyawan_Destination' => $kodeKaryawan,
            'Nama' => $nama,
            'HP' => $hp,
            'No_Transaksi' => $noTransaksi,
            'Status' => $status,
            'Error_Message' => $errorMessage,
            'Response_Message' => $responseMessage,
            'RawMessage' => $pesan ? print_r($pesan, true) : null,
            'RawMessageJson' => $pesan ? json_encode($pesan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : null,
            'Is_Retried' => 0,
            'Created_At' => now(),
        ]);
    }
}
