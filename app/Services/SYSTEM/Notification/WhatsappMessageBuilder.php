<?php

namespace App\Services\SYSTEM\Notification;

/**
 * Builder for WhatsApp message payloads
 *
 * Creates properly formatted message payloads for WhatsApp Business API
 * using predefined templates.
 */
class WhatsappMessageBuilder
{
    /**
     * Build generic template-based approval request
     *
     * @param  string  $templateName  WhatsApp template name
     * @param  string  $recipientHp  Phone number (with country code)
     * @param  array  $bodyParams  Template body parameters
     */
    public static function buildApprovalRequest(
        string $templateName,
        string $recipientHp,
        array $bodyParams,
        string $language = 'id'
    ): array {
        return [
            'messaging_product' => 'whatsapp',
            'to' => $recipientHp,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language, 'policy' => 'deterministic'],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => array_map(
                            fn ($text) => ['type' => 'text', 'text' => (string) $text],
                            $bodyParams
                        ),
                    ],
                ],
            ],
        ];
    }

    /**
     * Build penugasan training LMS notification message
     *
     * Template: hcis_penugasan_lms (lang id)
     * Params (urutan wajib cocok dengan template): {{1}}=nama, {{2}}=nama training
     */
    public static function penugasanLms(
        string $recipientHp,
        string $namaKaryawan,
        string $namaTraining
    ): array {
        return self::buildApprovalRequest(
            'hcis_penugasan_lms',
            $recipientHp,
            [$namaKaryawan, $namaTraining],
            'id'
        );
    }

    /**
     * Build approver notification message
     *
     * Template: notif_approver_hcis
     * Params: approver_name, no_transaksi, jenis, tanggal, requester_name
     */
    public static function approverNotification(
        string $recipientHp,
        string $approverName,
        string $noTransaksi,
        string $jenisTransaksi,
        string $tanggal,
        string $requesterName
    ): array {
        return self::buildApprovalRequest(
            'notif_approver_hcis',
            $recipientHp,
            [$approverName, $noTransaksi, $jenisTransaksi, $tanggal, $requesterName]
        );
    }

    /**
     * Build approved notification message
     *
     * Template: confirm_izin_diterima
     */
    public static function approvedNotification(
        string $recipientHp,
        string $requesterName,
        string $jenisAndNoTransaksi
    ): array {
        return self::buildApprovalRequest(
            'confirm_izin_diterima',
            $recipientHp,
            [$requesterName, $jenisAndNoTransaksi]
        );
    }

    /**
     * Build rejected notification message
     *
     * Template: confirm_izin_ditolak
     */
    public static function rejectedNotification(
        string $recipientHp,
        string $requesterName,
        string $jenisAndNoTransaksi,
        string $alasan
    ): array {
        return self::buildApprovalRequest(
            'confirm_izin_ditolak',
            $recipientHp,
            [$requesterName, $jenisAndNoTransaksi, $alasan]
        );
    }

    /**
     * Build next approver notification message
     *
     * Used when current approver approved and there's next in queue.
     */
    public static function nextApproverNotification(
        string $recipientHp,
        string $approverName,
        string $noTransaksi,
        string $jenisTransaksi,
        string $tanggal,
        string $requesterName
    ): array {
        // Uses same template as initial approver notification
        return self::approverNotification(
            $recipientHp,
            $approverName,
            $noTransaksi,
            $jenisTransaksi,
            $tanggal,
            $requesterName
        );
    }

    /**
     * Build approver notification message
     *
     * Template: hcis_approval_shift_per_karyawan
     */
    public static function approverShiftNotification(
        string $recipientHp,
        string $noTransaksi,
        string $namaRequester,
        string $tanggal,
    ): array {
        return self::buildApprovalRequest(
            'hcis_approval_shift_per_karyawan',
            $recipientHp,
            [$noTransaksi, $namaRequester, $tanggal]
        );
    }

    /**
     * Build request notification message
     *
     * Template: hcis_approval_shift_per_karyawan
     */
    public static function Notif_Request_Transaksi_Tanggal(
        string $recipientHp,
        string $recipientName,
        string $tipeTransaksi,
        string $noTransaksi,
        string $namaRequester,
        string $tanggal,
    ): array {
        return self::buildApprovalRequest(
            'notif_request_transaksi_tanggal',
            $recipientHp,
            [$recipientName, $tipeTransaksi, $noTransaksi, $namaRequester, $tanggal]
        );
    }

    /**
     * Build request notification message when transaction is completed
     *
     * Template: Notif_Selesai_Transaksi
     */
    public static function Notif_Selesai_Transaksi(
        string $recipientHp,
        string $recipientName,
        string $tipeTransaksi,
        string $noTransaksi,
        string $tanggal,
    ): array {
        return self::buildApprovalRequest(
            'notif_selesai_transaksi',
            $recipientHp,
            [$recipientName, $tipeTransaksi, $noTransaksi, $tanggal]
        );
    }

    public static function Notif_Karyawan_Perubahan_Shift(
        string $recipientHp,
        string $recipientName,
        string $tipeShift,
        string $Shift,
        string $leaderName,
        string $tanggal,
    ): array {
        return self::buildApprovalRequest(
            'notif_ganti_shift',
            $recipientHp,
            [$recipientName, $tipeShift, $Shift, $leaderName, $tanggal]
        );
    }

    /**
     * Build requester notification message
     *
     * Template: hcis_notif_approval_shift_per_karyawan
     */
    public static function requesterShiftNotification(
        string $recipientHp,
        string $noTransaksi,
        string $tanggal,
    ): array {
        return self::buildApprovalRequest(
            'hcis_notif_approval_shift_per_karyawan',
            $recipientHp,
            [$noTransaksi, $tanggal]
        );
    }

    /**
     * Build overtime assignment notification for employee.
     *
     * Template: notif_lembur
     * Params: nama_karyawan, no_transaksi, tanggal_waktu_lembur, alasan
     */
    public static function notifLemburNotification(
        string $recipientHp,
        string $employeeName,
        string $noTransaksi,
        string $tanggalWaktuLembur,
        string $alasan
    ): array {
        return self::buildApprovalRequest(
            'notif_lembur',
            $recipientHp,
            [$employeeName, $noTransaksi, $tanggalWaktuLembur, $alasan]
        );
    }
}
