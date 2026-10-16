import { reactive } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { ElMessageBox } from 'element-plus';

/**
 * useActivityCenter — store global "Activity Center" (dropdown topbar).
 *
 * Pola sama dengan useShellState: reactive() singleton module-level sehingga
 * SEMUA komponen berbagi state yang sama & persisten lintas navigasi Inertia
 * (hidup di AppShell). Tidak butuh Pinia.
 *
 * Polling PINTAR / LAZY:
 *  - Pakai setTimeout loop (bukan setInterval) dengan backoff 3s → 6s → 10s.
 *  - Berhenti otomatis saat 1× poll tidak ada task berjalan (activeCount===0).
 *  - Nyala lagi via notifyNewTask() saat ada dispatch task baru (event bus).
 *  - Poll sekali saat tab kembali fokus (visibilitychange).
 */

const BASE = '/api/v1/activities';
const BACKOFF = [3000, 3000, 6000, 6000, 10000]; // ms, mentok di 10s

const state = reactive({
    items: [],
    activeCount: 0,
    readyCount: 0,
    open: false,
    loading: false,
    polling: false,
    toast: null, // item export yang siap diunduh (toast tampil bila != null)
    _seenReady: new Set(), // id yang sudah pernah toast ready (hindari dobel)
});

let pollTimer = null;
let toastTimer = null;
let backoffStep = 0;
let visibilityBound = false;

function isActive(item) {
    return item.status === 'queued' || item.status === 'processing';
}

async function refresh() {
    try {
        const { data } = await axios.get(BASE, { headers: { 'X-Silent-419': '1' } });
        const items = data.result || [];

        // Deteksi item yang BARU jadi ready → toast.
        items.forEach((it) => {
            const id = it.export_id || it.id;
            if (it.status === 'ready' && !state._seenReady.has(id)) {
                state._seenReady.add(id);
                emitReadyToast(it);
            }
        });

        state.items = items;
        state.activeCount = items.filter(isActive).length;
        state.readyCount = items.filter((i) => i.status === 'ready' && !i.downloaded).length;
        return state.activeCount;
    } catch (e) {
        return 0;
    }
}

function emitReadyToast(item) {
    // Export (punya unduhan) & upload media (punya folder) sama-sama diberi toast.
    if (item.type !== 'export' && item.type !== 'upload') return;

    // Tampilkan toast custom (dirender di AppTopbar). Muncul 1x per task — guard
    // dobel ditangani _seenReady di refresh(). Auto-hilang ~8 detik.
    state.toast = item;
    if (toastTimer) window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => {
        state.toast = null;
        toastTimer = null;
    }, 8000);
}

/** Tutup toast manual (tombol close / setelah aksi). */
function dismissToast() {
    if (toastTimer) {
        window.clearTimeout(toastTimer);
        toastTimer = null;
    }
    state.toast = null;
}

/**
 * Aksi utama toast: export → unduh file; upload → buka folder konten.
 * (Nama tetap downloadToast demi kompatibilitas pemanggil lama di AppTopbar.)
 */
function downloadToast() {
    const item = state.toast;
    if (item) {
        if (item.type === 'upload' && item.open_url) {
            router.visit(item.open_url);
        } else if (item.download_url) {
            window.location.href = item.download_url;
        }
    }
    dismissToast();
}

/** Buka folder/kategori tempat file diupload (item upload yang ready). */
function openItem(item) {
    if (item && item.open_url) {
        router.visit(item.open_url);
    }
}

function scheduleNext() {
    const delay = BACKOFF[Math.min(backoffStep, BACKOFF.length - 1)];
    pollTimer = window.setTimeout(tick, delay);
}

async function tick() {
    const active = await refresh();
    if (active > 0) {
        backoffStep++;
        scheduleNext();
    } else {
        stopPolling(); // lazy: tidak ada yang berjalan → berhenti
    }
}

function ensurePolling() {
    if (state.polling) return;
    state.polling = true;
    backoffStep = 0;
    scheduleNext();
}

function stopPolling() {
    state.polling = false;
    if (pollTimer) {
        window.clearTimeout(pollTimer);
        pollTimer = null;
    }
}

/** Dipanggil saat dispatch task baru (export/upload) → polling nyala lagi. */
async function notifyNewTask() {
    state.open = state.open; // no-op, jaga reaktivitas
    await refresh();
    backoffStep = 0;
    ensurePolling();
}

/** Muat awal + mulai polling bila perlu (dipanggil saat AppShell mount). */
async function init() {
    bindVisibility();
    const active = await refresh();
    if (active > 0) ensurePolling();
}

function bindVisibility() {
    if (visibilityBound || typeof document === 'undefined') return;
    visibilityBound = true;
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            refresh().then((active) => {
                if (active > 0) ensurePolling();
            });
        }
    });
}

function toggle() {
    state.open = !state.open;
    if (state.open) refresh();
}

function close() {
    state.open = false;
}

function download(item) {
    if (!item.download_url) return;

    // Picu unduhan. Backend menghapus file temp setelah diunduh (DeleteExportFileJob),
    // jadi item akan hilang. Tampilkan overlay "membersihkan" + hapus dari list
    // setelah jeda singkat agar UX terasa seperti download browser (selesai → lenyap).
    const id = item.export_id || item.id;
    const target = state.items.find((i) => (i.export_id || i.id) === id);
    if (target) {
        target._dismissing = true;
        target._downloading = true; // bedakan teks overlay (selesai vs hapus)
    }

    window.location.href = item.download_url;

    window.setTimeout(() => {
        state.items = state.items.filter((i) => (i.export_id || i.id) !== id);
        refresh();
    }, 1500);
}

async function retry(item) {
    try {
        await axios.post(`${BASE}/${item.type}/${item.export_id || item.id}/retry`);
        await refresh();
        ensurePolling();
    } catch (e) {
        /* abaikan */
    }
}

async function abort(item) {
    const id = item.export_id || item.id;
    if (!item.abort_url) return;

    // Konfirmasi dulu — abort membatalkan PROSES yang sedang/akan berjalan.
    // Tiga macam: auto-cap KPI (master), tugas latar lain seperti PA (dihentikan), dan export.
    const autoCap = item.type === 'kpi-auto-cap';
    const bukanExport = item.type !== 'export';
    try {
        await ElMessageBox.confirm(
            autoCap
                ? 'Karyawan yang belum diproses tidak akan di-auto-cap. Transaksi yang sudah terbentuk tetap ada. Lanjutkan?'
                : bukanExport
                    ? 'Proses berhenti sebelum batch berikutnya. Yang sudah terkirim tetap berlaku, dan tugas bisa dilanjutkan lewat "Coba Lagi". Lanjutkan?'
                    : 'Proses export yang sedang berjalan akan dibatalkan dan dihentikan. Lanjutkan?',
            autoCap ? 'Batalkan Auto-Cap?' : bukanExport ? 'Hentikan Proses?' : 'Batalkan Export?',
            {
                confirmButtonText: bukanExport && !autoCap ? 'Ya, hentikan' : 'Ya, batalkan',
                cancelButtonText: 'Tidak',
                type: 'warning',
                confirmButtonClass: 'el-button--danger',
            },
        );
    } catch (e) {
        return; // user menutup / memilih "Tidak"
    }

    // Tampilkan state loading; tunggu konfirmasi server lalu refresh. Item tidak
    // dihapus — status berubah jadi "Dibatalkan" + tombol "Coba Lagi".
    const target = state.items.find((i) => (i.export_id || i.id) === id);
    if (target) target._aborting = true;

    try {
        await axios.post(`${BASE}/${item.type}/${id}/abort`);
    } catch (e) {
        if (target) target._aborting = false;
        return;
    }

    if (target) target._aborting = false;
    await refresh();
    ensurePolling();
}

async function dismiss(item) {
    const id = item.export_id || item.id;

    // Tandai sebagai "sedang dihapus" supaya UI menampilkan animasi loading,
    // bukan langsung menghapus optimistik (yang bikin item flicker: hilang →
    // muncul lagi dari refresh → hilang lagi). Tunggu server konfirmasi baru
    // refresh, sehingga penghapusan terasa mulus.
    const target = state.items.find((i) => (i.export_id || i.id) === id);
    if (target) target._dismissing = true;

    if (item.dismiss_url) {
        try {
            await axios.post(`${BASE}/${item.type}/${id}/dismiss`);
        } catch (e) {
            // Gagal → batalkan state loading agar user bisa coba lagi.
            if (target) target._dismissing = false;
            return;
        }
    }

    // Hapus dari list lokal lalu sinkronkan dengan server.
    state.items = state.items.filter((i) => (i.export_id || i.id) !== id);
    refresh();
}

/**
 * Aktivitas yang benar-benar bisa dibersihkan: sudah tidak berjalan DAN
 * jenisnya mengenal "sembunyikan".
 *
 * `dismiss_url` kosong berarti jenis itu memang tak bisa disembunyikan —
 * pdf-render adalah status sumber, bukan task. Tanpa syarat kedua ini, panel
 * yang hanya berisi satu pdf-render selesai tetap memperlihatkan tombol
 * "Bersihkan" yang tak akan pernah melakukan apa pun.
 */
function isClearable(item) {
    return !isActive(item) && !!item.dismiss_url;
}

/** Sembunyikan semua aktivitas yang sudah selesai (ready/completed) atau gagal. */
async function dismissAllCompletedOrFailed() {
    const clearable = state.items.filter(isClearable);
    if (!clearable.length) return 0;

    clearable.forEach((item) => {
        item._dismissing = true;
    });

    let dibersihkan = 0;
    try {
        /* Server membersihkan SELURUH sumber panel dan mengembalikan berapa
           yang benar-benar terbereskan. Sebelumnya endpoint ini hanya
           menyentuh satu tabel lalu tetap membalas 200 — dan karena jalur
           cadangan di bawah cuma dipicu oleh GALAT, ia tak pernah jalan,
           sehingga item upload/bulk-assign/rekonsiliasi kembali muncul pada
           penyegaran berikutnya. */
        const { data } = await axios.post(`${BASE}/dismiss-all`);
        dibersihkan = Number(data?.result?.dismissed ?? clearable.length);
    } catch (e) {
        // Cadangan: bereskan satu per satu lewat endpoint per item.
        const hasil = await Promise.allSettled(
            clearable.map((item) =>
                axios.post(`${BASE}/${item.type}/${item.export_id || item.id}/dismiss`),
            ),
        );
        dibersihkan = hasil.filter((r) => r.status === 'fulfilled').length;
    }

    /* Penyaring lokal memakai isClearable, BUKAN isActive: item yang tak bisa
       disembunyikan (pdf-render) harus tetap berada di panel. Membuangnya di
       sini akan membuatnya lenyap sekejap lalu muncul lagi dari refresh —
       kedipan yang terbaca sebagai bug. */
    state.items = state.items.filter((item) => !isClearable(item));
    await refresh();

    return dibersihkan;
}

export function useActivityCenter() {
    return {
        state,
        init,
        refresh,
        notifyNewTask,
        ensurePolling,
        stopPolling,
        toggle,
        close,
        download,
        retry,
        abort,
        dismiss,
        dismissAllCompletedOrFailed,
        isClearable,
        dismissToast,
        downloadToast,
        openItem,
    };
}
