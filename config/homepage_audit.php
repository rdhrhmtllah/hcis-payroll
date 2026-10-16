<?php

return [
    /*
     * AUDIT SEMENTARA beban homepage. Saat true, service mencatat tiap pemanggilan
     * SP absen (loadAbsensiRows: SP_CALL vs MEMO_HIT) dan AllTransactionsReadService
     * (per method + rentang) ke channel log 'homepageLog', dikelompokkan req_id.
     *
     * Tujuan: mengukur berapa kali query berat benar-benar dipanggil per homepage
     * load, dari endpoint mana, rentang apa -> dasar keputusan optimasi "satu kali
     * cek untuk semua komponen".
     *
     * HAPUS config + pemanggil auditTouch/auditAbsenCall setelah analisis selesai.
     * Aktifkan via env: HOMEPAGE_AUDIT=true
     */
    'enabled' => (bool) env('HOMEPAGE_AUDIT', false),
];
