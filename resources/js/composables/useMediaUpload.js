import { reactive } from 'vue';
import axios from 'axios';
import * as tus from 'tus-js-client';
import { useActivityCenter } from './useActivityCenter';

// Library konten dipakai bersama LMS & SOP; base path ditentukan dari URL halaman aktif.
// Halaman SOP di /sop/*, LMS di /lms/*. Controller & FE sama, konten terisolasi (kolom Scope).
const kontenBasePath = () =>
    typeof window !== 'undefined' && window.location.pathname.startsWith('/sop')
        ? '/sop/admin/konten'
        : '/lms/konten-kategori';

/**
 * useMediaUpload — store GLOBAL untuk upload media LMS (content dashboard).
 *
 * Pola sama dengan useActivityCenter / useShellState: reactive() singleton
 * module-level sehingga state upload PERSISTEN lintas navigasi Inertia (hidup di
 * AppShell, bukan di komponen halaman). Ini yang membuat floating widget tidak
 * hilang saat admin pindah halaman — upload byte tetap jalan selama TAB terbuka.
 *
 * Dua fase (mengikuti backend lama):
 *   Fase 1 — transfer byte: video → TUS ke BunnyCDN; dokumen/PDF → PUT ke GCS.
 *   Fase 2 — render PDF (server-side, async): dipantau via polling status.
 *
 * Setiap item juga dicatat sebagai task di Pusat Aktivitas (upload-task/start)
 * agar muncul di topbar & tetap ada sampai di-silang.
 */

const MB = 1024 * 1024;
const MAX_FILES = 5;
// Batas terbesar di seluruh kebijakan (video). Batas sebenarnya berbeda per
// format dan ditegakkan periksaBerkas() — konstanta ini hanya untuk keperluan
// tampilan yang butuh satu angka.
const MAX_FILE_SIZE_BYTES = 2048 * MB;

const state = reactive({
    queue: [], // item upload aktif (lihat shape di enqueue)
    showWidget: false,
    isWidgetMinimized: false,
    isUploadingAll: false,
});

// Instance TUS per-item id (tidak reaktif).
const tusInstances = {};
let unloadBound = false;

// ── Helpers ────────────────────────────────────────────────────────────
function getFileExtension(fileName) {
    const parts = String(fileName || '').split('.');
    if (parts.length < 2) return '';
    // Karakter selain huruf/angka dibuang, sama seperti KontenPolicy di server,
    // supaya "berkas.pdf " atau "berkas.pdf%00" tidak dinilai berbeda di dua sisi.
    return parts
        .pop()
        .toLowerCase()
        .replace(/[^a-z0-9]/g, '');
}

/**
 * Cerminan KontenPolicy di server.
 *
 * Server tetap yang memutuskan — daftar ini ada supaya penolakan terjadi
 * seketika, sebelum satu byte pun dikirim, dan supaya pesannya sama persis.
 * Kalau keduanya berbeda, yang menang server: klien cuma mempercepat kabar
 * buruk, bukan memberi izin.
 */
const KEBIJAKAN = {
    pdf: { maks: 50 * MB, tipe: 'document', label: 'PDF' },
    docx: { maks: 50 * MB, tipe: 'document', label: 'Word (docx)' },
    xlsx: { maks: 50 * MB, tipe: 'document', label: 'Excel (xlsx)' },
    pptx: { maks: 50 * MB, tipe: 'document', label: 'PowerPoint (pptx)' },
    jpg: { maks: 10 * MB, tipe: 'image', label: 'JPEG' },
    jpeg: { maks: 10 * MB, tipe: 'image', label: 'JPEG' },
    png: { maks: 10 * MB, tipe: 'image', label: 'PNG' },
    mp4: { maks: 2048 * MB, tipe: 'video', label: 'Video MP4' },
    mov: { maks: 2048 * MB, tipe: 'video', label: 'Video MOV' },
    mkv: { maks: 2048 * MB, tipe: 'video', label: 'Video MKV' },
    webm: { maks: 2048 * MB, tipe: 'video', label: 'Video WebM' },
};

/** Format yang ditolak dengan jalan keluar yang jelas, bukan pesan generik. */
const PESAN_TOLAK = {
    docm: 'Berkas Word ber-makro tidak diizinkan. Simpan ulang sebagai .docx.',
    xlsm: 'Berkas Excel ber-makro tidak diizinkan. Simpan ulang sebagai .xlsx.',
    pptm: 'Berkas PowerPoint ber-makro tidak diizinkan. Simpan ulang sebagai .pptx.',
    doc: 'Format Word lawas tidak diizinkan. Simpan ulang sebagai .docx.',
    xls: 'Format Excel lawas tidak diizinkan. Simpan ulang sebagai .xlsx.',
    ppt: 'Format PowerPoint lawas tidak diizinkan. Simpan ulang sebagai .pptx.',
    svg: 'SVG tidak diizinkan karena dapat memuat skrip. Pakai PNG atau JPG.',
    html: 'Berkas HTML tidak diizinkan.',
    htm: 'Berkas HTML tidak diizinkan.',
    zip: 'Arsip ZIP tidak diizinkan. Unggah berkasnya satu per satu.',
    rar: 'Arsip RAR tidak diizinkan. Unggah berkasnya satu per satu.',
};

const getCurrentScope = () =>
    typeof window !== 'undefined' && window.location.pathname.startsWith('/sop')
        ? 'SOP'
        : 'LMS';

const LARANGAN_SCOPE = {
    LMS: ['docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png'],
    SOP: [],
};

function getExtDiizinkan(scope = getCurrentScope()) {
    const larangan = LARANGAN_SCOPE[String(scope || '').toUpperCase()] || [];
    return Object.keys(KEBIJAKAN).filter((ext) => !larangan.includes(ext));
}

function getAcceptAttr(scope = getCurrentScope()) {
    return getExtDiizinkan(scope)
        .map((e) => `.${e}`)
        .join(',');
}

const EXT_DIIZINKAN = Object.keys(KEBIJAKAN);
const ACCEPT_ATTR = EXT_DIIZINKAN.map((e) => `.${e}`).join(',');

function mbTerbaca(byte) {
    return `${Math.round(byte / MB)} MB`;
}

/**
 * Satu-satunya tempat aturan berkas diperiksa di sisi klien — dipakai widget
 * antrean maupun modal unggah, supaya keduanya tak pernah berbeda pendapat.
 *
 * @param {File} file
 * @param {string} [scope]
 * @returns {{ok: boolean, ext?: string, tipe?: string, alasan?: string}}
 */
function periksaBerkas(file, scope = getCurrentScope()) {
    const ext = getFileExtension(file?.name);
    const p = KEBIJAKAN[ext];
    const allowed = getExtDiizinkan(scope);

    if (!p || !allowed.includes(ext)) {
        if (String(scope || '').toUpperCase() === 'LMS' && ['docx', 'xlsx', 'pptx', 'jpg', 'jpeg', 'png'].includes(ext)) {
            return {
                ok: false,
                alasan: `Format .${ext} belum didukung di LMS. Saat ini LMS hanya mendukung berkas PDF dan Video (MP4, MOV, MKV, WebM).`,
            };
        }
        return {
            ok: false,
            alasan:
                PESAN_TOLAK[ext] || `Format .${ext || '?'} tidak didukung. Yang diterima: ${allowed.join(', ')}.`,
        };
    }
    if (!file.size) {
        return { ok: false, alasan: `${file.name} kosong.` };
    }
    if (file.size > p.maks) {
        return { ok: false, alasan: `${file.name} melebihi batas ${mbTerbaca(p.maks)} untuk ${p.label}.` };
    }

    return { ok: true, ext, tipe: p.tipe };
}

function deriveTitleFromFileName(fileName) {
    return String(fileName || '')
        .replace(/\.[^/.]+$/, '')
        .replace(/[._-]+/g, ' ')
        .trim()
        .slice(0, 255);
}

/** Item sedang transfer byte (fase 1) — yang benar-benar putus jika tab ditutup. */
function isByteTransfer(item) {
    return ['getting_ticket', 'uploading', 'saving'].includes(item.status);
}

function hasActiveByteTransfer() {
    return state.queue.some(isByteTransfer);
}

// ── Proteksi beforeunload (global, hanya saat transfer byte) ─────────────
function handleBeforeUnload(event) {
    if (!hasActiveByteTransfer()) return; // render PDF aman ditinggal → tidak blok
    event.preventDefault();
    event.returnValue = '';
    return '';
}

function bindUnload() {
    if (unloadBound || typeof window === 'undefined') return;
    unloadBound = true;
    window.addEventListener('beforeunload', handleBeforeUnload);
}

function unbindUnload() {
    if (!unloadBound) return;
    unloadBound = false;
    window.removeEventListener('beforeunload', handleBeforeUnload);
}

// ── Antrean ──────────────────────────────────────────────────────────────
/**
 * Tambah file ke antrean upload. files: FileList|Array<File>. meta:
 * { categoryId, categoryName }. Mengembalikan jumlah file yang diterima.
 */
function enqueue(files, meta = {}) {
    const list = files ? Array.from(files) : [];
    if (!list.length) return 0;

    const remaining = MAX_FILES - state.queue.length;
    const toAdd = list.slice(0, remaining);
    let accepted = 0;

    toAdd.forEach((file) => {
        const periksa = periksaBerkas(file, meta.scope || getCurrentScope());
        if (!periksa.ok) return;

        state.queue.push({
            id: Math.random().toString(36).substr(2, 9),
            file,
            customName: deriveTitleFromFileName(file.name),
            progress: 0,
            status: 'idle', // idle|getting_ticket|uploading|saving|rendering|success|error|render_failed|paused
            tipe_konten: periksa.tipe,
            isTus: periksa.tipe === 'video',
            ticketKey: null,
            errorMessage: null, // alasan penolakan dari server, ditampilkan widget
            categoryId: meta.categoryId ?? null,
            categoryName: meta.categoryName ?? '',
            bunnyVideoId: null,
            abortController: null,
            exportId: null, // id task Pusat Aktivitas
            kontenId: null,
            renderStatus: null,
            resolvePromise: null,
            _renderPollTimer: null,
            _lastSentPercent: -1,
        });
        accepted++;
    });

    return accepted;
}

function maxFilesReached() {
    return state.queue.length >= MAX_FILES;
}

// ── Engine upload ─────────────────────────────────────────────────────────
async function startQueueUploads() {
    state.isUploadingAll = true;
    state.showWidget = true;
    state.isWidgetMinimized = false;
    bindUnload();

    // Nyalakan polling Pusat Aktivitas (ikon awan di topbar).
    useActivityCenter().notifyNewTask();

    for (const item of state.queue) {
        if (['idle', 'error', 'paused'].includes(item.status)) {
            item.status = 'waiting';
            await processSingleFile(item);
        }
    }
}

function processSingleFile(item) {
    return new Promise(async (resolve) => {
        item.resolvePromise = resolve;
        item.status = 'getting_ticket';
        item.abortController = new AbortController();

        try {
            item.errorMessage = null;

            // 1. Catat task di Pusat Aktivitas (sebelum transfer byte).
            try {
                const { data: taskRes } = await axios.post(kontenBasePath() + '/upload-task/start', {
                    judul_content: item.customName,
                    file_name: item.file.name,
                    size: item.file.size,
                });
                item.exportId = taskRes?.result?.export_id || null;
                if (item.exportId) {
                    useActivityCenter().notifyNewTask();
                }
            } catch (e) {
                // Gagal mencatat task bukan fatal untuk upload — lanjut tanpa task.
                item.exportId = null;
            }

            /*
             * 2. Minta tiket upload (Bunny / GCS).
             *
             * `tipe_konten` dan `mime_type` sengaja TIDAK dikirim lagi: server
             * menurunkannya sendiri dari ekstensi. Dulu keduanya ikut dan dipakai
             * apa adanya, sampai tersimpan sebagai Type_File — kolom yang jadi
             * dasar gerbang "item DOKUMEN wajib PDF" di Builder.
             */
            const { data: ticketRes } = await axios.post(kontenBasePath() + '/get-upload-ticket', {
                judul_content: item.customName,
                file_name: item.file.name,
                id_kategori_konten: item.categoryId,
                size: item.file.size,
            });

            item.ticketKey = ticketRes.ticket_key;

            if (ticketRes.storage_provider === 'bunny') {
                item.bunnyVideoId = ticketRes.video_id;
                uploadViaTus(item, ticketRes, resolve);
            } else if (ticketRes.storage_provider === 'gcs') {
                uploadViaGcs(item, ticketRes, resolve);
            }
        } catch (error) {
            item.status = 'error';
            // Penolakan kebijakan (422) membawa alasan yang bisa ditindaklanjuti
            // admin — "format .docm tidak diizinkan, simpan ulang sebagai .docx"
            // jauh lebih berguna daripada "gagal menyiapkan upload".
            const pesan = pesanGalat(error) || 'Gagal menyiapkan upload.';
            item.errorMessage = pesan;
            reportTaskFailed(item, pesan);
            resolve();
        }
    });
}

/** Heartbeat progres byte ke server (throttle tiap kelipatan 5%). */
function reportProgress(item) {
    if (!item.exportId) return;
    const p = item.progress;
    if (p - item._lastSentPercent < 5 && p < 99) return;
    item._lastSentPercent = p;
    axios.post(`${kontenBasePath()}/upload-task/${item.exportId}/progress`, { percent: p }).catch(() => {});
}

function uploadViaTus(item, ticket, resolveQueue) {
    item.status = 'uploading';

    const upload = new tus.Upload(item.file, {
        endpoint: 'https://video.bunnycdn.com/tusupload',
        retryDelays: [0, 3000, 5000, 10000, 20000],
        headers: {
            AuthorizationSignature: ticket.signature,
            AuthorizationExpire: ticket.expiration_time.toString(),
            VideoId: ticket.video_id,
            LibraryId: ticket.library_id.toString(),
        },
        metadata: { filename: item.file.name, filetype: item.file.type },
        onError: () => {
            item.status = 'error';
            reportTaskFailed(item, 'Transfer video gagal.');
            resolveQueue();
        },
        onProgress: (bytesUploaded, bytesTotal) => {
            item.progress = Math.round((bytesUploaded / bytesTotal) * 100);
            reportProgress(item);
        },
        onSuccess: async () => {
            item.progress = 100;
            item.status = 'saving';
            await finalizeToDatabase(item, resolveQueue);
        },
    });

    tusInstances[item.id] = upload;
    upload.findPreviousUploads().then((prev) => {
        if (prev.length > 0) upload.resumeFromPreviousUpload(prev[0]);
        upload.start();
    });
}

function pauseTus(item) {
    if (tusInstances[item.id]) {
        tusInstances[item.id].abort();
        item.status = 'paused';
    }
}

function resumeTus(item) {
    if (tusInstances[item.id]) {
        tusInstances[item.id].start();
        item.status = 'uploading';
    }
}

async function uploadViaGcs(item, ticket, resolveQueue) {
    item.status = 'uploading';
    try {
        /*
         * Header WAJIB persis seperti yang dikembalikan tiket.
         *
         * Content-Type dan x-goog-content-length-range ikut DITANDATANGANI di
         * signed URL, jadi mengirim nilai lain (mis. item.file.type seperti
         * dulu) membuat tanda tangannya tak cocok dan GCS membalas 403.
         * Rentang ukuran itu sekaligus membuat GCS menolak berkas kebesaran
         * sebelum byte-nya terkirim habis.
         */
        await axios.put(ticket.signed_url, item.file, {
            withCredentials: false,
            headers: { 'Content-Type': ticket.content_type, ...(ticket.headers || {}) },
            signal: item.abortController.signal,
            onUploadProgress: (e) => {
                item.progress = Math.round((e.loaded * 100) / e.total);
                reportProgress(item);
            },
        });

        item.status = 'saving';
        await finalizeToDatabase(item, resolveQueue);
    } catch (error) {
        if (!axios.isCancel(error)) {
            item.status = 'error';
            item.errorMessage = 'Transfer file gagal.';
            reportTaskFailed(item, 'Transfer file gagal.');
        }
        resolveQueue();
    }
}

async function finalizeToDatabase(item, resolveQueue) {
    try {
        /*
         * Muatannya kini cuma kunci tiket + judul. Path, mime, tipe, kategori,
         * dan id media Bunny semuanya dibaca server dari baris tiket yang ia
         * tulis sendiri — dulu semua itu dikirim ulang oleh browser dan
         * dipercaya apa adanya.
         */
        const { data } = await axios.post(kontenBasePath() + '/upload-konten', {
            judul_content: item.customName,
            ticket_key: item.ticketKey,
            export_id: item.exportId,
        });
        const result = data?.result || {};

        if (tusInstances[item.id]) delete tusInstances[item.id];

        // PDF → fase 2 (render). File aman tersimpan; pantau Status_Render.
        if (result.is_pdf && result.konten_id) {
            item.kontenId = result.konten_id;
            item.renderStatus = result.render_status || 'PENDING';
            item.status = 'rendering';
            item.progress = 100;
            resolveQueue();
            unbindUnloadIfIdle(); // byte selesai → render aman ditinggal
            startRenderPolling(item);
            return;
        }

        // Non-PDF (video / dokumen lain): langsung selesai.
        item.status = 'success';
        item.progress = 100;
        resolveQueue();
        unbindUnloadIfIdle();
        useActivityCenter().notifyNewTask();
    } catch (error) {
        item.status = 'error';
        if (tusInstances[item.id]) delete tusInstances[item.id];
        // 422 di sini berarti berkasnya ditolak verifikasi ("isi berkas bukan
        // PDF meskipun namanya berakhiran .pdf"). Objeknya sudah dihapus server,
        // jadi alasannya harus sampai ke admin — bukan ditelan pesan generik.
        const pesan = pesanGalat(error) || 'Gagal menyimpan ke database.';
        item.errorMessage = pesan;
        reportTaskFailed(item, pesan);
        useActivityCenter().notifyNewTask();
        resolveQueue();
    }
}

/** Pesan yang bisa ditindaklanjuti dari respons galat Laravel, bila ada. */
function pesanGalat(error) {
    const d = error?.response?.data;
    if (!d) return null;
    if (typeof d.message === 'string' && d.message) return d.message;
    // ValidationException membungkus pesannya di dalam errors{field:[...]}.
    const pertama = d.errors && Object.values(d.errors)[0];

    return Array.isArray(pertama) ? pertama[0] : null;
}

// ── Fase 2: polling status render PDF ──────────────────────────────────────
function startRenderPolling(item) {
    stopRenderPolling(item);

    // Refresh Pusat Aktivitas agar status render terbaru ikut tampil.
    useActivityCenter().notifyNewTask();

    const tick = async () => {
        if (item.status !== 'rendering') {
            stopRenderPolling(item);
            return;
        }
        try {
            const { data } = await axios.get(`${kontenBasePath()}/konten/${item.categoryId}`);
            const files = data?.result?.data || [];
            const match = files.find((f) => String(f.id) === String(item.kontenId));
            const status = (
                match?.renderStatus ||
                (match?.metadata && match.metadata.renderStatus) ||
                ''
            ).toUpperCase();

            if (status === 'DONE') {
                item.renderStatus = 'DONE';
                item.status = 'success';
                stopRenderPolling(item);
            } else if (status === 'FAILED') {
                item.renderStatus = 'FAILED';
                item.status = 'render_failed';
                stopRenderPolling(item);
            } else if (status) {
                item.renderStatus = status;
            }
        } catch (e) {
            /* diamkan error transien */
        }
    };

    tick();
    item._renderPollTimer = setInterval(tick, 6000);
}

function stopRenderPolling(item) {
    if (item && item._renderPollTimer) {
        clearInterval(item._renderPollTimer);
        item._renderPollTimer = null;
    }
}

// Render ulang (retry fase 2) untuk PDF yang FAILED.
async function retryRender(item) {
    if (!item.kontenId) return;
    item.status = 'rendering';
    item.renderStatus = 'PENDING';
    try {
        await axios.post(`${kontenBasePath()}/berkas/${item.kontenId}/render-ulang`);
        startRenderPolling(item);
    } catch (error) {
        item.status = 'render_failed';
        item.renderStatus = 'FAILED';
    }
}

// Retry fase 1 (upload byte gagal): ulangi dari minta tiket.
async function retryUpload(item) {
    if (item.status !== 'error') return;
    item.progress = 0;
    item._lastSentPercent = -1;
    bindUnload();
    await processSingleFile(item);
}

// ── Manajemen item / widget ────────────────────────────────────────────────
async function removeFile(id) {
    const index = state.queue.findIndex((i) => i.id === id);
    if (index === -1) return;
    const item = state.queue[index];

    stopRenderPolling(item);

    if (item.isTus) {
        if (tusInstances[item.id]) {
            tusInstances[item.id].abort(true);
            delete tusInstances[item.id];
        }
        // Kunci tiket, bukan guid video: server yang membaca guid-nya dari baris
        // tiket, sehingga permintaan ini tak bisa dipakai menghapus video milik
        // orang lain di library Bunny yang dipakai bersama LMS dan SOP.
        if (item.ticketKey && item.status !== 'success') {
            try {
                await axios.delete(kontenBasePath() + '/delete-upload-ticket', {
                    data: { ticket_key: item.ticketKey },
                });
            } catch (e) {
                /* abaikan */
            }
        }
    } else if (item.abortController) {
        item.abortController.abort();
    }

    if (item.resolvePromise) item.resolvePromise();
    state.queue.splice(index, 1);

    unbindUnloadIfIdle();
    if (state.queue.length === 0) {
        state.showWidget = false;
        state.isUploadingAll = false;
    }
}

function clearAll() {
    state.queue.forEach((item) => {
        stopRenderPolling(item);
        if (tusInstances[item.id]) {
            tusInstances[item.id].abort(true);
            delete tusInstances[item.id];
        }
    });
    state.queue = [];
    state.isUploadingAll = false;
    state.showWidget = false;
    state.isWidgetMinimized = false;
    unbindUnload();
}

function unbindUnloadIfIdle() {
    if (!hasActiveByteTransfer()) unbindUnload();
}

/**
 * Tandai task gagal di server saat transfer byte putus / gagal SEBELUM finalize
 * (gagal tiket, error TUS/GCS) agar tidak menggantung 'processing' di Pusat
 * Aktivitas. Finalize yang gagal sudah ditangani server (uploadKonten markFailed).
 */
function reportTaskFailed(item, message = 'Upload gagal.') {
    if (!item.exportId) return;
    axios.post(`${kontenBasePath()}/upload-task/${item.exportId}/fail`, { message }).catch(() => {});
}

export function useMediaUpload() {
    return {
        state,
        MAX_FILES,
        enqueue,
        maxFilesReached,
        startQueueUploads,
        retryUpload,
        retryRender,
        pauseTus,
        resumeTus,
        removeFile,
        clearAll,
        // util untuk UI pemilihan file
        getFileExtension,
        deriveTitleFromFileName,
        periksaBerkas, // satu-satunya pemeriksa aturan berkas di sisi klien
        EXT_DIIZINKAN,
        ACCEPT_ATTR,
        getExtDiizinkan,
        getAcceptAttr,
        getCurrentScope,
        MAX_FILE_SIZE_BYTES,
    };
}
