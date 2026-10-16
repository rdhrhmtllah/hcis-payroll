<?php

namespace App\Services\LMS\SOP\Support;

/**
 * Konstanta status terpusat SOP Repository.
 * SATU sumber kebenaran — jangan hardcode string status di controller/service/Vue.
 * Mirror di frontend: resources/js/Pages/LMS/SOP/Shared/support/constants.js
 */
class SopStatus
{
    /* ---- Status versi item SOP ---- */
    const VERSI_DRAFT = 'DRAFT';

    const VERSI_READY = 'READY';

    const VERSI_PUBLISHED = 'PUBLISHED';

    const VERSI_OBSOLETE = 'OBSOLETE';

    /* ---- Lifecycle paket (N_SOP_Master_Paket.Status) ---- */
    const PAKET_DRAFT = 'DRAFT';

    const PAKET_PUBLISHED = 'PUBLISHED';

    const PAKET_ARCHIVED = 'ARCHIVED';

    /* ---- Status penugasan/progress paket (N_SOP_Penjadwalan_Detail.Status_Paket) ---- */
    const STATUS_BELUM = 'BELUM';

    const STATUS_PROGRESS = 'PROGRESS';

    const STATUS_SELESAI = 'SELESAI';

    /* ---- Status item per karyawan (N_SOP_Progress.Status) ---- */
    const ITEM_BELUM = 'BELUM';

    const ITEM_DIBUKA = 'DIBUKA';

    const ITEM_SELESAI = 'SELESAI';

    const ITEM_DIKONFIRMASI = 'DIKONFIRMASI';

    const ITEM_DIGANTIKAN = 'DIGANTIKAN';

    /* ---- Prioritas paket ---- */
    const PRIORITAS_KRITIS = 'KRITIS';

    const PRIORITAS_TINGGI = 'TINGGI';

    const PRIORITAS_NORMAL = 'NORMAL';

    /* ---- Peristiwa ledger (N_SOP_Kepatuhan_Riwayat.Peristiwa) ---- */
    const RWT_SELESAI = 'SELESAI';

    const RWT_DIBUKA_LAGI = 'DIBUKA_LAGI';

    const RWT_DICABUT = 'DICABUT';

    const RWT_KADALUARSA = 'KADALUARSA';

    /* ---- Flag ---- */
    const AKTIF = 'Y';

    const INAKTIF = 'T';
}
