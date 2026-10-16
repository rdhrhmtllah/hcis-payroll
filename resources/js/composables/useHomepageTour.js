import { ref, computed, readonly, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import '../../css/homepage-tour.css';
import { registerTour, unregisterTour } from './tourRegistry.js';
import { useShellState } from './useShellState.js';

/**
 * Step definitions for the homepage onboarding tour.
 * Each step is explained individually — module cards are NOT bundled into a single step
 * so the user understands every part of the dashboard.
 */
// Urutan mengikuti alur visual page (top → bottom, left → right pada desktop).
// Closing step "help" sengaja centered (tanpa anchor) supaya tidak ada lompatan
// scroll mundur ke topbar di akhir tour — user journey tetap mengalir ke depan.
export const HOMEPAGE_TOUR_STEPS = [
    {
        key: 'welcome',
        icon: '👋',
        popover: {
            title: 'Hei, selamat datang di HCIS!',
            description:
                'Kita akan keliling bareng sebentar supaya kamu tahu apa yang bisa dilakukan di sini. Cuma butuh beberapa menit kok — klik <b>Lanjut</b> kalau siap, atau <b>×</b> kalau mau skip dulu.',
        },
    },
    // ────────────────────────────────────────────────────────
    //  SIDEBAR (kiri) — auto-expand, lalu drill-down per area
    // ────────────────────────────────────────────────────────
    {
        key: 'sidebar',
        icon: '🧭',
        element: '[data-tour="sidebar"]',
        ui: { type: 'sidebar' },
        popover: {
            title: 'Menu utama ada di sini',
            description:
                'Panel di sebelah kiri ini adalah tempat kamu berpindah antar modul. Selama tour berlangsung kita pin dulu biar kelihatan semuanya — yuk kita lihat satu per satu.',
            side: 'right',
            align: 'start',
        },
    },
    {
        key: 'sidebar-brand',
        icon: '🏠',
        element: '[data-tour="sidebar-brand"]',
        ui: { type: 'sidebar' },
        popover: {
            title: 'Logo EVO — bisa diklik juga',
            description:
                'Ini logo EVO di pojok kiri atas. Selain sebagai identitas aplikasi, kalau kamu klik logo ini kamu bisa <b>pin atau unpin sidebar</b>. Pin artinya sidebar selalu terbuka; unpin artinya sidebar akan mengecil sendiri saat kamu tidak hover ke sana.',
            side: 'right',
            align: 'start',
        },
    },
    {
        key: 'sidebar-nav',
        icon: '📋',
        element: '[data-tour="sidebar-nav"]',
        ui: { type: 'sidebar' },
        popover: {
            title: 'Daftar modul yang bisa kamu akses',
            description:
                'Di sini ada semua modul yang bisa kamu buka, seperti <b>Absensi, KPI, LMS, Cuti, Lembur, dan Pengajuan</b>. Tinggal klik nama modulnya, nanti akan muncul sub-menu di dalamnya — misalnya Pengajuan Cuti atau Riwayat Absensi.',
            side: 'right',
            align: 'center',
        },
    },
    {
        key: 'sidebar-profile-button',
        icon: '👤',
        element: '[data-tour="sidebar-profile-button"]',
        ui: { type: 'sidebar' },
        popover: {
            title: 'Foto, nama, dan departemenmu',
            description:
                'Di pojok kiri bawah ada foto profil, nama, dan departemen kamu. Kalau diklik, akan muncul menu akun — kita lihat isinya di langkah berikutnya.',
            side: 'right',
            align: 'end',
        },
    },
    {
        key: 'sidebar-profile-menu',
        icon: '⚙️',
        element: '[data-tour="sidebar-profile-menu"]',
        ui: { type: 'profile-menu' }, // custom handler — buka shell.state.showProfileMenu
        popover: {
            title: 'Menu akun kamu',
            description:
                'Ada tiga pilihan di sini: <b>Profile</b> untuk mengubah data diri, foto, atau password — <b>Tentang Aplikasi</b> untuk lihat versi dan info sistem — dan <b>Logout</b> kalau kamu mau keluar dari sesi.',
            side: 'right',
            align: 'end',
        },
    },
    // ────────────────────────────────────────────────────────
    //  TOPBAR — Notifikasi (auto open + tab demo)
    // ────────────────────────────────────────────────────────
    {
        key: 'topbar-notifications',
        icon: '🔔',
        element: '[data-tour="topbar-notifications"]',
        ui: { type: 'popover-trigger', popoverKey: 'showNotifications' },
        popover: {
            title: 'Notifikasi masuk di sini',
            description:
                'Ikon lonceng ini adalah pusat notifikasimu. Kalau ada badge merah di sana, artinya ada pesan yang <b>belum kamu baca</b>. Klik <b>Lanjut</b> dan kita buka panelnya bareng.',
            side: 'bottom',
            align: 'end',
        },
    },
    {
        key: 'notif-tabs',
        icon: '📑',
        element: '[data-tour="notif-tabs"]',
        ui: { type: 'popover', popoverKey: 'showNotifications', action: 'switch-notif-tab-unread' },
        popover: {
            title: 'Dua tab notifikasi',
            description:
                'Panel ini dibagi jadi dua tab: <b>Belum Dibaca</b> untuk notif yang masih perlu kamu cek, dan <b>Sudah Dibaca</b> sebagai arsip. Angka kecil di tiap tab menunjukkan berapa item yang ada.',
            side: 'bottom',
            align: 'end',
        },
    },
    {
        key: 'notif-list',
        icon: '📨',
        element: '[data-tour="notif-list"]',
        ui: { type: 'popover', popoverKey: 'showNotifications', action: 'switch-notif-tab-unread' },
        popover: {
            title: 'Isi notifikasinya tampil di sini',
            description:
                'Daftar notifikasi menyesuaikan tab yang sedang aktif. Kalau datanya lagi dimuat akan muncul loading, dan kalau memang belum ada notifikasi kamu akan lihat pesan bahwa daftarnya masih kosong.',
            side: 'bottom',
            align: 'end',
        },
    },
    {
        key: 'notif-footer',
        icon: '📂',
        element: '[data-tour="notif-footer"]',
        ui: { type: 'popover', popoverKey: 'showNotifications', action: 'switch-notif-tab-unread' },
        popover: {
            title: 'Lihat semua atau bersihkan riwayat',
            description:
                'Di bagian bawah panel ada tombol <b>Lihat Semua Notifikasi</b> yang akan membawa kamu ke halaman riwayat lengkap. Kalau masih ada yang belum dibaca, tombol <b>Bersihkan Riwayat</b> juga akan muncul supaya badge loncengnya bisa hilang sekaligus.',
            side: 'bottom',
            align: 'end',
        },
    },
    // ────────────────────────────────────────────────────────
    //  TOPBAR — Modul Aktif / Profil pill
    // ────────────────────────────────────────────────────────
    {
        key: 'topbar-profile',
        icon: '🏷️',
        element: '[data-tour="topbar-profile"]',
        ui: { type: 'popover-trigger', popoverKey: 'showBreadcrumb' },
        popover: {
            title: 'Kamu sedang di modul mana?',
            description:
                'Kotak di pojok kanan atas ini menunjukkan <b>modul yang sedang kamu buka</b> beserta <b>nama dan NIK</b> kamu. Kalau diklik, akan terbuka jalur navigasi lengkap — kita lihat di langkah berikutnya.',
            side: 'left',
            align: 'start',
        },
    },
    // ────────────────────────────────────────────────────────
    //  TOPBAR — Breadcrumb / Jalur Navigasi
    // ────────────────────────────────────────────────────────
    {
        key: 'breadcrumb-popover',
        icon: '🧭',
        // Highlight popover breadcrumb langsung. Popover dibuka & distabilkan lebih
        // dulu (startBreadcrumbKeepAlive + waitForElementStable) sebelum driver
        // mengukur posisinya, dan CSS guard menaikkan z-index-nya di atas overlay
        // driver (z-index 10000) supaya tampil lewat lubang highlight.
        element: '[data-tour="breadcrumb-popover"]',
        ui: { type: 'popover', popoverKey: 'showBreadcrumb' },
        popover: {
            title: 'Jalur navigasi kamu',
            description:
                'Panel ini menunjukkan posisimu sekarang secara berurutan: <b>Beranda → modul yang aktif → halaman ini</b>. Kalau kamu mau loncat ke halaman lain dalam modul yang sama, tinggal klik chip "Akses Cepat" yang ada di bawahnya.',
            side: 'left',
            align: 'start',
        },
    },
    {
        key: 'topbar-help',
        icon: '❓',
        element: '[data-tour="topbar-help"]',
        ui: { type: 'popover', popoverKey: 'showHelp' },
        popover: {
            title: 'Butuh bantuan? Klik di sini',
            description:
                'Tombol tanda tanya ini membuka panel bantuan. Di dalamnya ada tombol untuk memulai tour, daftar kontak admin, dan informasi versi aplikasi. Kita intip isinya sebentar.',
            side: 'left',
            align: 'start',
        },
    },
    {
        key: 'help-tour-cta',
        icon: '🚀',
        element: '[data-tour="help-tour-cta"]',
        ui: { type: 'popover', popoverKey: 'showHelp' },
        popover: {
            title: 'Mulai atau ulangi tour dari sini',
            description:
                'Kartu ini hanya muncul kalau halaman yang kamu buka punya tour panduan. Kamu bisa lihat statusnya (belum mulai, sedang berjalan, atau selesai), seberapa jauh progresnya, dan tombol untuk <b>mulai, lanjutkan, atau ulangi</b> tournya kapan saja.',
            side: 'left',
            align: 'start',
        },
    },
    {
        key: 'help-contacts',
        icon: '📞',
        element: '[data-tour="help-contacts"]',
        ui: { type: 'popover', popoverKey: 'showHelp' },
        popover: {
            title: 'Ada masalah? Hubungi kontak bantuannya',
            description:
                'Di sini ada kontak bantuan tiap modul, misalnya <b>Bantuan HCIS</b> untuk absensi dan perizinan, <b>Bantuan KPI</b>, <b>Bantuan LMS</b>, dan <b>Bantuan VMS</b>. Modul halaman yang sedang kamu buka ada paling atas. Klik salah satu untuk melihat kontaknya, lalu hubungi lewat email atau WhatsApp.',
            side: 'left',
            align: 'start',
        },
    },
    // ── Konten halaman (top → bottom) ───────────────────────
    {
        key: 'hero',
        icon: '🌅',
        element: '[data-tour="hero"]',
        popover: {
            title: 'Ringkasan harianmu',
            description:
                'Bagian ini menyapamu dan langsung menampilkan info penting hari ini — jam kerja, status absensi, dan posisi kerjamu. Sekali lihat sudah tahu kondisi hari ini.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'team-dashboard-btn',
        icon: '👥',
        roles: ['leader'],
        element: '[data-tour="team-dashboard-btn"]',
        popover: {
            title: 'Pintu masuk ke timmu',
            description:
                'Karena kamu seorang leader, ada tombol <b>Dashboard Tim</b> di sini. Lewat tombol ini kamu bisa masuk ke halaman tim untuk melihat kehadiran dan menyetujui pengajuan anggotamu. Kalau ada angka kecil di pojok tombol, itu menandakan berapa hal dari tim yang sedang menunggu tindakanmu.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'awareness',
        icon: '🎯',
        element: '[data-tour="awareness"]',
        popover: {
            title: 'Status penting hari ini',
            description:
                'Kartu-kartu di sini menampilkan status kunci harianmu — sudah <b>clock-in atau belum</b>, ada <b>izin, cuti, atau lembur</b> yang aktif hari ini. Kalau mau lihat detailnya, tinggal klik kartu yang kamu butuhkan.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'module-hcis',
        icon: '🩺',
        element: '[data-tour="module-hcis"]',
        popover: {
            title: 'Rekap kehadiranmu bulan ini',
            description:
                'Kartu ini menampilkan ringkasan kehadiranmu di periode yang aktif sekarang — berapa hari <b>hadir, telat, izin atau sakit, dan cuti</b>. Klik kartunya kalau mau masuk ke halaman detail modul HCIS.',
            side: 'bottom',
            align: 'center',
        },
    },
    {
        key: 'module-kpi',
        icon: '📊',
        element: '[data-tour="module-kpi"]',
        popover: {
            title: 'Progres KPI kamu',
            description:
                'Di sini kamu bisa lihat <b>target KPI, sejauh mana sudah tercapai, dan status review periode</b> yang sedang berjalan.',
            side: 'bottom',
            align: 'center',
        },
    },
    {
        key: 'module-lms',
        icon: '🎓',
        element: '[data-tour="module-lms"]',
        popover: {
            title: 'Pelatihan dan sertifikasimu',
            description:
                'Kartu ini menampilkan pelatihan wajib yang harus kamu selesaikan, jadwal training berikutnya yang sudah terjadwal. Klik untuk masuk ke katalog LMS lengkap.',
            side: 'top',
            align: 'center',
        },
    },
    {
        key: 'module-overtime',
        icon: '⏱️',
        roles: ['staff'],
        element: '[data-tour="module-overtime"]',
        popover: {
            title: 'Lembur bulan ini',
            description:
                'Di sini kamu bisa pantau pengajuan lembur yang sudah kamu buat — mana yang sudah disetujui, berapa jam totalnya, dan rincian lembur di bulan yang sedang berjalan.',
            side: 'top',
            align: 'center',
        },
    },
    {
        key: 'calendar',
        icon: '📅',
        element: '[data-tour="calendar"]',
        popover: {
            title: 'Kalender kehadiranmu',
            description:
                'Kalender ini menampilkan rekap kehadiranmu — tampilan mingguan di HP, atau satu periode penuh di layar yang lebih besar. <b>Klik tanggal mana saja</b> untuk melihat jam masuk dan jam pulang di hari itu.',
            side: 'top',
            align: 'center',
        },
    },
    // ── Sidebar kanan (desktop), urutan dari ATAS ke BAWAH ──
    {
        key: 'leader-approvals',
        icon: '🔔',
        element: '[data-tour="leader-approvals"]',
        popover: {
            title: 'Hal yang perlu kamu tindak',
            description:
                'Panel "Perlu Saya Tindak" ini mengingatkan kamu soal hal-hal yang masih butuh perhatian, khusus untukmu — misalnya ada hari yang <b>tercatat alpha</b>, <b>lupa check-in atau check-out</b>, atau pengajuanmu yang <b>ditolak</b> atau <b>sudah kedaluwarsa</b>. Klik kartunya untuk langsung menindaklanjuti.',
            side: 'left',
            align: 'start',
        },
    },
    {
        key: 'quick-actions',
        icon: '⚡',
        element: '[data-tour="quick-actions"]',
        popover: {
            title: 'Aksi cepat tanpa harus pindah halaman',
            description:
                'Kalau mau mengajukan cuti, izin, lembur, atau absensi, kamu tidak perlu masuk ke menu dulu — langsung klik shortcut yang ada di sini dan formulirnya langsung muncul.',
            side: 'left',
            align: 'center',
        },
    },
    {
        key: 'attendance-composition',
        icon: '🥧',
        element: '[data-tour="attendance-composition"]',
        popover: {
            title: 'Komposisi kehadiranmu dalam grafik',
            description:
                'Grafik donut ini memperlihatkan perbandingan <b>hari hadir, telat, izin atau sakit, dan cuti</b> kamu dalam periode yang dipilih. Klik kartunya untuk melihat breakdown detail per kategori.',
            side: 'left',
            align: 'center',
        },
    },
    {
        key: 'submissions',
        icon: '📨',
        element: '[data-tour="submissions"]',
        popover: {
            title: 'Status pengajuanmu',
            description:
                'Di sini kamu bisa lihat semua pengajuan yang pernah kamu buat — cuti, izin, atau lembur — beserta statusnya sekarang: <b>Menunggu persetujuan, Disetujui, atau Ditolak</b>. Klik salah satu untuk melihat detail dan riwayat prosesnya.',
            side: 'left',
            align: 'end',
        },
    },
    {
        key: 'farewell',
        icon: '🎉',
        popover: {
            title: 'Itu semua bagian dari dashboard-mu!',
            description:
                'Sekarang kamu sudah tahu semua yang ada di sini. Selamat bekerja, dan kalau suatu saat lupa atau mau mengulangi tour ini, tinggal klik tombol <b>❓</b> di pojok kanan atas.',
        },
    },
];

/**
 * Filter step list berdasarkan role + ketersediaan anchor di DOM.
 * - Step dengan `roles: ['staff']` akan dilewati untuk leader, dan sebaliknya.
 * - Step yang anchor selectornya tidak ditemukan (mis. card di-`v-if`) juga difilter.
 *   Step welcome tetap selalu masuk (tidak punya `element`).
 */
function resolveStepsFor(role, { checkDom = true } = {}) {
    return HOMEPAGE_TOUR_STEPS.filter((s) => {
        if (DISABLED_HOMEPAGE_TOUR_STEP_KEYS.has(s.key)) return false;
        if (Array.isArray(s.roles) && s.roles.length && !s.roles.includes(role)) return false;
        if (checkDom && s.element && typeof document !== 'undefined') {
            return !!document.querySelector(s.element);
        }
        return true;
    });
}

const defaultProgress = () => ({
    status: 'not_started',
    current_step: null,
    completed_steps: [],
    skipped_at_step: null,
    started_at: null,
    completed_at: null,
    updated_at: null,
    step_version: 1,
});

const TOUR_KEY = 'homepage';
const SIDEBAR_TOUR_TRANSITION_DELAY = 420;
const POPOVER_TOUR_TRANSITION_DELAY = 300;
const TOUR_STEP_SETTLE_DELAY = 180;
const TOUR_TARGET_STABLE_TIMEOUT = 900;
const DRIVER_STAGE_ANIMATION_DELAY = 460;
const TOUR_POPOVER_WIDTH = 400;
const DISABLED_HOMEPAGE_TOUR_STEP_KEYS = new Set(['notif-mark-all', 'notif-item-first']);
const HOMEPAGE_CONTENT_STEP_KEYS = new Set([
    'hero',
    'awareness',
    'module-hcis',
    'module-kpi',
    'module-lms',
    'module-overtime',
    'calendar',
    'leader-approvals',
    'quick-actions',
    'attendance-composition',
    'submissions',
]);
const MOBILE_HOMEPAGE_CONTENT_STEP_ORDER = [
    'hero',
    'team-dashboard-btn',
    'awareness',
    'calendar',
    'quick-actions',
    'attendance-composition',
    'leader-approvals',
    'module-hcis',
    'module-kpi',
    'module-lms',
    'module-overtime',
    'submissions',
];
const HOMEPAGE_TOUR_ICON_OVERRIDES = {
    'notif-footer': '📂',
    'topbar-profile': '🏷️',
};

export function useHomepageTour(initialProgress, registration = null, options = {}) {
    const isLeader = !!options.isLeader;
    const role = isLeader ? 'leader' : 'staff';
    const shell = useShellState();

    // Snapshot sidebar state sebelum tour dimulai → kita kembalikan saat selesai.
    let sidebarPreState = null;

    // Steps yang relevan untuk role ini (filter berbasis role, BUKAN DOM —
    // pengecekan DOM dilakukan di start() karena anchor mungkin belum di-mount).
    const stepsForRole = HOMEPAGE_TOUR_STEPS.filter(
        (s) =>
            !DISABLED_HOMEPAGE_TOUR_STEP_KEYS.has(s.key) &&
            (!Array.isArray(s.roles) || !s.roles.length || s.roles.includes(role)),
    );

    // Side-effect helpers — buka/tutup popover atau ekspand sidebar saat step
    // dimulai/dihentikan, agar fitur yang dijelaskan benar-benar terlihat user.
    /**
     * Side-effect action runner: actions yang dieksekusi saat memasuki step
     * tertentu (sebelum highlight). Mis. ganti tab notifikasi, klik tombol.
     */
    const runStepAction = (action) => {
        if (!action) return;
        try {
            if (action === 'switch-notif-tab-read') {
                document.querySelector('[data-tour="notif-tab-read"]')?.click();
            } else if (action === 'switch-notif-tab-unread') {
                document.querySelector('[data-tour="notif-tab-unread"]')?.click();
            }
        } catch (e) {
            console.warn('[tour] step action failed', action, e);
        }
    };

    const wait = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

    const waitForFrames = (count = 2) =>
        new Promise((resolve) => {
            const tick = () => {
                count -= 1;
                if (count <= 0) {
                    resolve();
                    return;
                }
                window.requestAnimationFrame(tick);
            };
            window.requestAnimationFrame(tick);
        });

    const getElementRectSignature = (el) => {
        if (!el) return null;
        const rect = el.getBoundingClientRect();
        return [Math.round(rect.top), Math.round(rect.left), Math.round(rect.width), Math.round(rect.height)].join(':');
    };

    const isElementVisible = (el) => {
        if (!el) return false;
        const rect = el.getBoundingClientRect();
        const styles = window.getComputedStyle(el);
        return (
            rect.width > 1 &&
            rect.height > 1 &&
            styles.display !== 'none' &&
            styles.visibility !== 'hidden' &&
            Number(styles.opacity || 0) > 0.01
        );
    };

    const waitForElementStable = async (selector, timeout = TOUR_TARGET_STABLE_TIMEOUT) => {
        if (!selector || typeof document === 'undefined') {
            await waitForFrames(2);
            return false;
        }

        const startedAt = performance.now();
        let previousSignature = null;
        let stableFrames = 0;

        while (performance.now() - startedAt < timeout) {
            await waitForFrames(1);
            const el = document.querySelector(selector);

            if (!isElementVisible(el)) {
                previousSignature = null;
                stableFrames = 0;
                continue;
            }

            const signature = getElementRectSignature(el);
            if (signature === previousSignature) {
                stableFrames += 1;
                if (stableFrames >= 4) {
                    return true;
                }
            } else {
                previousSignature = signature;
                stableFrames = 0;
            }
        }

        return false;
    };

    const setTourMoving = (moving) => {
        document.documentElement.classList.toggle('hcis-tour-moving-step', Boolean(moving));
    };

    const setActiveTourStepClass = (key = null) => {
        const root = document.documentElement;
        Array.from(root.classList)
            .filter((className) => className.startsWith('hcis-tour-step-'))
            .forEach((className) => root.classList.remove(className));

        if (key) {
            root.classList.add(`hcis-tour-step-${key}`);
        }
    };

    const runBeforeStepHook = async (step) => {
        if (!step || typeof options.beforeStep !== 'function') {
            return 0;
        }

        try {
            const result = await options.beforeStep(step.key, step);
            return Number.isFinite(Number(result)) ? Number(result) : 0;
        } catch (err) {
            console.warn('[tour] beforeStep failed', step.key, err?.message || err);
            return 0;
        }
    };

    const orderRuntimeStepsForViewport = (steps) => {
        if (!shell.state.isMobile) {
            return steps;
        }

        const orderMap = new Map(MOBILE_HOMEPAGE_CONTENT_STEP_ORDER.map((key, index) => [key, index]));
        const shellSteps = [];
        const contentSteps = [];
        const endingSteps = [];

        steps.forEach((step, index) => {
            const indexedStep = { step, index };
            if (step.key === 'farewell') {
                endingSteps.push(indexedStep);
            } else if (HOMEPAGE_CONTENT_STEP_KEYS.has(step.key)) {
                contentSteps.push(indexedStep);
            } else {
                shellSteps.push(indexedStep);
            }
        });

        contentSteps.sort((a, b) => {
            const aOrder = orderMap.has(a.step.key) ? orderMap.get(a.step.key) : Number.MAX_SAFE_INTEGER;
            const bOrder = orderMap.has(b.step.key) ? orderMap.get(b.step.key) : Number.MAX_SAFE_INTEGER;
            return aOrder - bOrder || a.index - b.index;
        });

        return [...shellSteps, ...contentSteps, ...endingSteps].map(({ step }) => step);
    };

    const ensureProfileMenuOpen = () => {
        shell.lockSidebarForTour();
        if (shell.state.isMobile) {
            shell.openMobileSidebar();
        } else {
            shell.state.sidebarLocked = true;
            shell.state.sidebarExpanded = true;
        }
        shell.state.showProfileMenu = true;
    };

    const startProfileMenuKeepAlive = () => {
        shouldKeepProfileMenuOpen = true;
        ensureProfileMenuOpen();
        if (profileMenuKeepAliveTimer) {
            return;
        }

        profileMenuKeepAliveTimer = window.setInterval(() => {
            if (!isActive.value || !shouldKeepProfileMenuOpen) {
                stopProfileMenuKeepAlive();
                return;
            }

            ensureProfileMenuOpen();
        }, 80);
    };

    const stopProfileMenuKeepAlive = () => {
        shouldKeepProfileMenuOpen = false;
        if (!profileMenuKeepAliveTimer) {
            return;
        }

        window.clearInterval(profileMenuKeepAliveTimer);
        profileMenuKeepAliveTimer = null;
    };

    const startBreadcrumbKeepAlive = () => {
        shouldKeepBreadcrumbOpen = true;
        shell.openPopover('showBreadcrumb');

        if (breadcrumbKeepAliveTimer) {
            return;
        }

        breadcrumbKeepAliveTimer = window.setInterval(() => {
            if (!isActive.value || !shouldKeepBreadcrumbOpen) {
                stopBreadcrumbKeepAlive();
                return;
            }

            if (!shell.state.showBreadcrumb) {
                shell.openPopover('showBreadcrumb');
            }
        }, 80);
    };

    const stopBreadcrumbKeepAlive = () => {
        shouldKeepBreadcrumbOpen = false;
        if (!breadcrumbKeepAliveTimer) {
            return;
        }

        window.clearInterval(breadcrumbKeepAliveTimer);
        breadcrumbKeepAliveTimer = null;
    };

    const getPopoverKey = (step) => {
        if (!step?.ui) return null;
        return step.ui.type === 'popover' || step.ui.type === 'popover-trigger' ? step.ui.popoverKey || null : null;
    };

    const openStepUi = (step) => {
        if (!step?.ui) return;
        if (step.ui.type === 'popover-trigger' && step.ui.popoverKey) {
            // Step "trigger" hanya meng-highlight tombolnya (mis. pill Modul/Profil
            // atau lonceng). Popover-nya harus TERTUTUP di sini — baru dibuka pada
            // step popover berikutnya. Pastikan state bersih agar tidak ada popover
            // nyangkut dari interaksi sebelumnya.
            if (step.ui.popoverKey === 'showBreadcrumb') {
                stopBreadcrumbKeepAlive();
            }
            if (shell.state[step.ui.popoverKey]) {
                shell.state[step.ui.popoverKey] = false;
            }
        } else if (step.ui.type === 'popover' && step.ui.popoverKey) {
            if (step.ui.popoverKey === 'showBreadcrumb') {
                startBreadcrumbKeepAlive();
            } else if (!shell.state[step.ui.popoverKey]) {
                shell.openPopover(step.ui.popoverKey);
            }
        } else if (step.ui.type === 'profile-menu') {
            startProfileMenuKeepAlive();
            setTimeout(() => {
                try {
                    driverInstance?.refresh?.();
                } catch (_) {
                    /* noop */
                }
            }, SIDEBAR_TOUR_TRANSITION_DELAY);
        } else if (step.ui.type === 'sidebar') {
            snapshotSidebar();
            shell.lockSidebarForTour();
            // Step pertama "sidebar" (atau anak step lain di sidebar) — pastikan terbuka.
            // Khusus step dengan key 'sidebar' (overview), kita animasikan secara visual.
            if (step.key === 'sidebar') {
                expandSidebarWithAnimation();
            } else {
                // Sub-step sidebar lain: pastikan sidebar masih terbuka (no animation
                // karena seharusnya sudah dibuka oleh step "sidebar" sebelumnya).
                if (shell.state.isMobile) {
                    if (!shell.state.mobileSidebarOpen) shell.openMobileSidebar();
                } else {
                    shell.state.sidebarLocked = true;
                    shell.state.sidebarExpanded = true;
                }
            }
        }

        if (step.ui.action && !isMovingStep) {
            setTimeout(() => runStepAction(step.ui.action), 120);
        }
    };

    const restoreSidebarSnapshot = () => {
        if (!sidebarPreState) {
            return false;
        }

        stopProfileMenuKeepAlive();
        shell.state.showProfileMenu = false;
        shell.state.mobileSidebarOpen = sidebarPreState.mobileOpen;
        shell.state.sidebarLocked = sidebarPreState.locked;
        shell.state.sidebarExpanded = sidebarPreState.expanded;
        shell.unlockSidebarForTour();
        sidebarPreState = null;

        return true;
    };

    const prepareStepUiBeforeMove = (step, nextStep) => {
        let delay = 0;
        const currentPopoverKey = getPopoverKey(step);
        const nextPopoverKey = getPopoverKey(nextStep);

        if (inSidebarGroup(step) && !inSidebarGroup(nextStep) && restoreSidebarSnapshot()) {
            delay = Math.max(delay, SIDEBAR_TOUR_TRANSITION_DELAY);
        }

        if (currentPopoverKey && currentPopoverKey !== nextPopoverKey) {
            if (currentPopoverKey === 'showBreadcrumb') {
                stopBreadcrumbKeepAlive();
            }
            shell.state[currentPopoverKey] = false;
            delay = Math.max(delay, POPOVER_TOUR_TRANSITION_DELAY);
        }

        if (nextStep?.ui?.type === 'popover-trigger' && nextPopoverKey) {
            // Step trigger berikutnya hanya highlight tombol — popover wajib tertutup.
            // Matikan keep-alive breadcrumb dulu agar tidak dibuka ulang oleh interval.
            if (nextPopoverKey === 'showBreadcrumb') {
                stopBreadcrumbKeepAlive();
            }
            shell.state[nextPopoverKey] = false;
            delay = Math.max(delay, POPOVER_TOUR_TRANSITION_DELAY);
        }

        if (!nextStep?.ui) {
            return delay;
        }

        if (nextStep.ui.type === 'popover' && nextStep.ui.popoverKey) {
            // Buka popover lebih awal supaya elemen popover sudah visible & terukur
            // saat driver memindahkan highlight ke step ini. Untuk breadcrumb pakai
            // keep-alive (popover-nya gampang tertutup oleh interaksi global).
            if (nextStep.ui.popoverKey === 'showBreadcrumb') {
                startBreadcrumbKeepAlive();
            } else if (!shell.state[nextStep.ui.popoverKey]) {
                shell.openPopover(nextStep.ui.popoverKey);
            }
            delay = Math.max(delay, POPOVER_TOUR_TRANSITION_DELAY);
        }

        if (nextStep.ui.type === 'profile-menu') {
            snapshotSidebar();
            shell.lockSidebarForTour();
            if (shell.state.isMobile) {
                shell.openMobileSidebar();
            } else {
                shell.state.sidebarLocked = true;
                shell.state.sidebarExpanded = true;
            }
        }

        if (nextStep.ui.type === 'sidebar') {
            snapshotSidebar();
            shell.lockSidebarForTour();
            if (shell.state.isMobile) {
                shell.openMobileSidebar();
            } else {
                shell.state.sidebarLocked = true;
                shell.state.sidebarExpanded = true;
            }
            delay = Math.max(delay, nextStep.key === 'sidebar' ? SIDEBAR_TOUR_TRANSITION_DELAY : 80);
        }

        if (nextStep.ui.action) {
            runStepAction(nextStep.ui.action);
            delay = Math.max(delay, 140);
        }

        return delay;
    };

    const scrollElementIntoView = (selector, { behavior = 'smooth' } = {}) => {
        if (!selector || typeof document === 'undefined') return false;

        const element = document.querySelector(selector);
        if (!element) return false;

        // Kalau elemen sudah cukup terlihat di viewport, jangan scroll — supaya
        // hero/layout tidak melompat hanya demi menempatkan target di tengah.
        const rect = element.getBoundingClientRect();
        const viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
        const isMobile = window.innerWidth <= 640;
        // Mobile popovers eat ~280px; need more breathing room than desktop.
        const margin = isMobile ? 110 : 80;
        if (rect.top >= margin && rect.bottom <= viewportH - margin) {
            return true;
        }

        element.scrollIntoView({
            behavior: window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ? 'auto' : behavior,
            block: 'nearest',
            inline: 'nearest',
        });

        return true;
    };

    const preparePageStepBeforeHighlight = async (step, { smooth = true } = {}) => {
        const hookDelay = await runBeforeStepHook(step);
        if (hookDelay > 0) {
            await wait(hookDelay);
        }

        if (!step?.element || step?.ui?.type === 'profile-menu') {
            await waitForFrames(2);
            return true;
        }

        if (HOMEPAGE_CONTENT_STEP_KEYS.has(step.key)) {
            scrollElementIntoView(step.element, { behavior: smooth ? 'smooth' : 'auto' });
            await wait(smooth ? 260 : 40);
        }

        return waitForElementStable(step.element);
    };

    /**
     * Step transition cleanup.
     *
     * - Popover: tetap terbuka selama sub-step berikut memakai popover yang sama.
     * - Profile menu: tutup hanya saat step berikut bukan profile-menu lagi.
     * - Sidebar group (sidebar + sidebar-* + profile-menu): saat seluruh group selesai,
     *   tutup/animasikan sidebar kembali ke state sebelum tour. User melihat animasi tutup.
     */
    const inSidebarGroup = (step) => step?.ui?.type === 'sidebar' || step?.ui?.type === 'profile-menu';

    const shouldAnimateHighlightMove = (currentStep, nextStep) => {
        if (!currentStep || !nextStep) {
            return false;
        }

        // The full sidebar target is very large. Animating the overlay cutout to/from
        // it creates a bright wipe effect, so let it snap after the sidebar has settled.
        if (currentStep.key === 'sidebar' || nextStep.key === 'sidebar') {
            return false;
        }

        // Moving between shell chrome and page content usually involves scroll/layout
        // changes. Snapping after settle is calmer than animating through that motion.
        if (inSidebarGroup(currentStep) !== inSidebarGroup(nextStep)) {
            return false;
        }

        return true;
    };

    const setDriverHighlightAnimation = (enabled) => {
        if (!driverInstance?.setConfig || !driverInstance?.getConfig) {
            return;
        }

        driverInstance.setConfig({
            ...driverInstance.getConfig(),
            animate: enabled,
        });
    };

    const closeStepUi = (step, nextStep) => {
        if (!step?.ui) return;

        if (step.ui.type === 'popover-trigger' && step.ui.popoverKey) {
            if (nextStep?.ui?.type === 'popover' && nextStep.ui.popoverKey === step.ui.popoverKey) {
                // Breadcrumb dibuka lewat keep-alive (di openStepUi/prepareStepUiBeforeMove),
                // jangan double-handle di sini. Untuk popover lain (mis. notifikasi),
                // buka saat deselect agar popover-nya sudah tampil di step berikutnya.
                if (step.ui.popoverKey !== 'showBreadcrumb') {
                    shell.state[step.ui.popoverKey] = true;
                }
            }
            return;
        }

        if (step.ui.type === 'popover' && step.ui.popoverKey) {
            const nextUsesSame = nextStep?.ui?.type === 'popover' && nextStep.ui.popoverKey === step.ui.popoverKey;
            if (!nextUsesSame && step.ui.popoverKey === 'showBreadcrumb') {
                stopBreadcrumbKeepAlive();
            }
            if (!nextUsesSame && !isMovingStep) shell.state[step.ui.popoverKey] = false;
            return;
        }

        if (step.ui.type === 'profile-menu') {
            if (nextStep?.ui?.type !== 'profile-menu') {
                stopProfileMenuKeepAlive();
                shell.state.showProfileMenu = false;
            }
        }

        // Saat keluar dari group sidebar (ke konten halaman / topbar), kembalikan
        // sidebar ke state sebelum tour — user lihat animasi menutup.
        if (inSidebarGroup(step) && !inSidebarGroup(nextStep) && sidebarPreState && !isMovingStep) {
            restoreSidebarSnapshot();
            // Refresh driver setelah animasi sidebar selesai untuk re-position ring.
            setTimeout(() => {
                try {
                    driverInstance?.refresh?.();
                } catch (_) {
                    /* noop */
                }
            }, SIDEBAR_TOUR_TRANSITION_DELAY);
        }
    };

    /**
     * Snapshot sidebar state SAJA (tanpa expand). Dipanggil di start().
     * Sidebar baru di-expand saat step "sidebar" mulai — biar user lihat animasinya.
     */
    const snapshotSidebar = () => {
        if (sidebarPreState !== null) return;
        sidebarPreState = {
            expanded: shell.state.sidebarExpanded,
            locked: shell.state.sidebarLocked,
            mobileOpen: shell.state.mobileSidebarOpen,
        };
    };

    /**
     * Ekspand sidebar + paksa driver.js mengukur ulang highlight ring setelah
     * transisi CSS selesai (~300ms). User melihat animasi sidebar terbuka.
     */
    const expandSidebarWithAnimation = () => {
        if (shell.state.isMobile) {
            shell.openMobileSidebar();
        } else {
            shell.state.sidebarLocked = true;
            shell.state.sidebarExpanded = true;
        }
        setTimeout(() => {
            try {
                driverInstance?.refresh?.();
            } catch (_) {
                /* noop */
            }
        }, 360);
    };

    const restoreShellAfterTour = () => {
        stopProfileMenuKeepAlive();
        stopBreadcrumbKeepAlive();
        shell.resetInteractionState();
        shell.unlockSidebarForTour();
        if (sidebarPreState) {
            shell.state.mobileSidebarOpen = sidebarPreState.mobileOpen;
            shell.state.sidebarLocked = sidebarPreState.locked;
            shell.state.sidebarExpanded = sidebarPreState.expanded;
            sidebarPreState = null;
        }
    };

    const progress = ref(
        initialProgress && typeof initialProgress === 'object'
            ? { ...defaultProgress(), ...initialProgress }
            : defaultProgress(),
    );

    const isActive = ref(false);
    let driverInstance = null;
    let runtimeSteps = stepsForRole; // di-set ulang di start() berdasarkan DOM
    let runtimeKeys = stepsForRole.map((s) => s.key);
    let isMovingStep = false;
    let pendingStepIndex = null;
    let profileMenuKeepAliveTimer = null;
    let shouldKeepProfileMenuOpen = false;
    let breadcrumbKeepAliveTimer = null;
    let shouldKeepBreadcrumbOpen = false;

    const totalSteps = stepsForRole.length;

    const completedCount = computed(() => {
        if (!Array.isArray(progress.value.completed_steps)) {
            return 0;
        }

        return progress.value.completed_steps.filter((key) => !DISABLED_HOMEPAGE_TOUR_STEP_KEYS.has(key)).length;
    });

    const isCompleted = computed(() => progress.value.status === 'completed');
    const isSkipped = computed(() => progress.value.status === 'skipped');
    const isFirstTime = computed(() => progress.value.status === 'not_started');
    const progressPercent = computed(() => Math.min(100, Math.round((completedCount.value / totalSteps) * 100)));

    const persist = async ({ step, status }) => {
        try {
            const { data } = await axios.post('/tour/progress', { tour: TOUR_KEY, step, status });
            if (data?.progress) progress.value = { ...defaultProgress(), ...data.progress };
        } catch (err) {
            console.warn('[tour] persist failed', err?.response?.status || err?.message);
        }
    };

    const persistSkip = async (atStep) => {
        try {
            const { data } = await axios.post('/tour/skip', { tour: TOUR_KEY, at_step: atStep || null });
            if (data?.progress) progress.value = { ...defaultProgress(), ...data.progress };
        } catch (err) {
            console.warn('[tour] skip failed', err?.response?.status || err?.message);
        }
    };

    const reset = async () => {
        try {
            const { data } = await axios.post('/tour/reset', { tour: TOUR_KEY });
            if (data?.progress) progress.value = { ...defaultProgress(), ...data.progress };
        } catch (err) {
            console.warn('[tour] reset failed', err?.response?.status || err?.message);
        }
    };

    const resolvePopoverPlacement = (step) => {
        const popover = step.popover || {};
        if (!step.element || typeof window === 'undefined') {
            return {
                side: popover.side,
                align: popover.align,
            };
        }

        const element = document.querySelector(step.element);
        if (!element) {
            return {
                side: popover.side,
                align: popover.align,
            };
        }

        const rect = element.getBoundingClientRect();
        const width = window.innerWidth || 0;
        const height = window.innerHeight || 0;
        const isMobile = width <= 640;
        const sideSpace = {
            left: rect.left,
            right: width - rect.right,
            top: rect.top,
            bottom: height - rect.bottom,
        };

        if (isMobile && HOMEPAGE_CONTENT_STEP_KEYS.has(step.key)) {
            // Mobile pages stack vertically — nearly every element is full-width.
            // We need at least ~280px below the target to fit the popover without
            // cutting off the highlighted area.
            const MIN_MOBILE_POPOVER_SPACE = 280;
            const useBottom =
                sideSpace.bottom >= MIN_MOBILE_POPOVER_SPACE || sideSpace.bottom >= sideSpace.top;

            // For tall targets (hero panels, calendar grids, etc.), align the
            // popover to the start edge so it doesn't blanket the middle of the
            // element that the user is supposed to see.
            const align = rect.height > 220 ? 'start' : 'center';

            return { side: useBottom ? 'bottom' : 'top', align };
        }

        let side = popover.side || 'bottom';
        if (side === 'left' && sideSpace.left < TOUR_POPOVER_WIDTH && sideSpace.right > sideSpace.left) {
            side = 'right';
        } else if (side === 'right' && sideSpace.right < TOUR_POPOVER_WIDTH && sideSpace.left > sideSpace.right) {
            side = 'left';
        } else if (side === 'top' && sideSpace.top < 260 && sideSpace.bottom > sideSpace.top) {
            side = 'bottom';
        } else if (side === 'bottom' && sideSpace.bottom < 260 && sideSpace.top > sideSpace.bottom) {
            side = 'top';
        }

        return {
            side,
            align: rect.width > 520 ? 'center' : popover.align,
        };
    };

    const buildDriverSteps = (steps) => {
        const total = steps.length;
        return steps.map((step, idx) => {
            const icon = HOMEPAGE_TOUR_ICON_OVERRIDES[step.key] || step.icon;
            const iconChip = icon ? `<span class="hcis-tour-step-icon">${icon}</span>` : '';
            const titleHtml = `<div class="hcis-tour-step-head">${iconChip}<span>${step.popover.title}</span></div>`;
            const percent = Math.round(((idx + 1) / total) * 100);
            const placement = resolvePopoverPlacement(step);
            const progressHtml =
                `<div class="hcis-tour-progress">` +
                `<span class="hcis-tour-progress__bar"><i style="width:${percent}%"></i></span>` +
                `<span class="hcis-tour-progress__label">${idx + 1}/${total}</span>` +
                `</div>`;
            const descHtml = `${step.popover.description}${progressHtml}`;
            return {
                element: step.element,
                popover: {
                    ...step.popover,
                    ...placement,
                    title: titleHtml,
                    description: descHtml,
                    showButtons: ['next', 'previous', 'close'],
                },
                onHighlightStarted: () => openStepUi(step),
                onDeselected: () => {
                    const nextStep = pendingStepIndex !== null ? runtimeSteps[pendingStepIndex] : runtimeSteps[idx];
                    closeStepUi(step, nextStep);
                },
            };
        });
    };

    const canMountPopoverStep = (step) => {
        if (step?.ui?.type !== 'popover' || !step.ui.popoverKey) {
            return false;
        }

        if (step.ui.popoverKey === 'showNotifications') {
            return !!document.querySelector('[data-tour="topbar-notifications"]');
        }

        if (step.ui.popoverKey === 'showHelp') {
            return !!document.querySelector('[data-tour="topbar-help"]');
        }

        if (step.ui.popoverKey === 'showBreadcrumb') {
            return (
                !!document.querySelector('[data-tour="breadcrumb-popover"]') ||
                !!document.querySelector('[data-tour="topbar-profile"]')
            );
        }

        return false;
    };

    const moveToStep = async (nextIndex) => {
        if (!driverInstance || isMovingStep) {
            return;
        }

        const currentIndex = driverInstance.getActiveIndex?.() ?? 0;
        const currentStep = runtimeSteps[currentIndex];
        const nextStep = runtimeSteps[nextIndex];

        if (!nextStep) {
            return;
        }

        isMovingStep = true;
        pendingStepIndex = nextIndex;
        setTourMoving(true);
        setActiveTourStepClass(nextStep.key);

        try {
            const animateHighlight = shouldAnimateHighlightMove(currentStep, nextStep);
            const delay = prepareStepUiBeforeMove(currentStep, nextStep);
            await wait(delay);
            if (nextStep.ui?.type !== 'profile-menu') {
                await preparePageStepBeforeHighlight(nextStep, { smooth: true });
            }
            await waitForFrames(2);

            if (!driverInstance) {
                return;
            }

            setDriverHighlightAnimation(animateHighlight);
            if (nextIndex > currentIndex) {
                driverInstance.moveNext();
            } else {
                driverInstance.movePrevious();
            }

            await wait(animateHighlight ? DRIVER_STAGE_ANIMATION_DELAY : TOUR_STEP_SETTLE_DELAY);
            await waitForFrames(2);
        } finally {
            isMovingStep = false;
            pendingStepIndex = null;
            setTourMoving(false);
        }
    };

    const start = ({ fromBeginning = true } = {}) => {
        if (isActive.value) return;

        // Snapshot sidebar state — kita restore di akhir, dan animasinya
        // baru terlihat saat step "sidebar" tiba.
        snapshotSidebar();

        // Filter ulang berdasarkan DOM realtime — anchor yang tidak muncul (mis. card
        // dengan v-if false karena loading state) di-skip otomatis.
        runtimeSteps = stepsForRole.filter((s) => {
            if (!s.element) return true;
            if (typeof document === 'undefined') return false;
            return !!document.querySelector(s.element) || canMountPopoverStep(s);
        });
        runtimeSteps = orderRuntimeStepsForViewport(runtimeSteps);
        runtimeKeys = runtimeSteps.map((s) => s.key);

        if (!runtimeSteps.length) {
            console.warn('[tour] no anchors found, aborting tour');
            return;
        }

        const steps = buildDriverSteps(runtimeSteps);
        const startIndex = (() => {
            if (fromBeginning) return 0;
            const lastDone = progress.value.current_step;
            const idx = runtimeKeys.indexOf(lastDone);
            if (idx === -1) return 0;
            return Math.min(idx + 1, runtimeKeys.length - 1);
        })();

        const isMobile = typeof window !== 'undefined' && window.innerWidth <= 640;

        driverInstance = driver({
            showProgress: false,
            allowClose: true,
            disableActiveInteraction: false,
            stagePadding: 10,
            stageRadius: 18,
            smoothScroll: true,
            animate: true,
            overlayOpacity: 0.55,
            overlayColor: '#0b1220',
            popoverOffset: isMobile ? 24 : 14,
            popoverClass: 'hcis-tour-popover',
            nextBtnText: 'Lanjut',
            prevBtnText: 'Kembali',
            doneBtnText: 'Selesai',
            steps,
            onHighlightStarted: (_el, _step, opts) => {
                const key = runtimeKeys[opts.state.activeIndex];
                if (key) {
                    progress.value.current_step = key;
                    setActiveTourStepClass(key);
                }
            },
            onNextClick: (_el, _step, opts) => {
                const idx = opts.state.activeIndex;
                const key = runtimeKeys[idx];
                const isLast = idx === runtimeKeys.length - 1;
                if (key) {
                    persist({ step: key, status: isLast ? 'completed' : 'in_progress' });
                }
                if (isLast) {
                    driverInstance.destroy();
                } else {
                    moveToStep(idx + 1);
                }
            },
            onPrevClick: (_el, _step, opts) => {
                const idx = opts.state.activeIndex;
                if (idx > 0) {
                    moveToStep(idx - 1);
                }
            },
            onCloseClick: () => {
                const key = runtimeKeys[driverInstance.getActiveIndex?.() ?? 0];
                persistSkip(key);
                driverInstance.destroy();
            },
            onDestroyed: () => {
                isActive.value = false;
                isMovingStep = false;
                pendingStepIndex = null;
                setTourMoving(false);
                setActiveTourStepClass(null);
                document.documentElement.classList.remove('hcis-tour-active');
                restoreShellAfterTour();
            },
        });

        document.documentElement.classList.add('hcis-tour-active');
        setActiveTourStepClass(runtimeSteps[startIndex]?.key || null);
        isActive.value = true;
        preparePageStepBeforeHighlight(runtimeSteps[startIndex], { smooth: false }).finally(() => {
            driverInstance?.drive(startIndex);
        });
    };

    const stop = () => {
        if (driverInstance) driverInstance.destroy();
    };

    // ── Register di tourRegistry global supaya AppTopbar bisa nampilkan CTA ──
    if (registration) {
        onMounted(() => {
            registerTour({
                component: registration.component || null,
                label: registration.label || 'Tour Halaman Ini',
                description: registration.description || 'Pandu kamu mengenali setiap bagian halaman.',
                progress: readonly(progress),
                totalSteps,
                completedCount,
                progressPercent,
                isFirstTime,
                isCompleted,
                isSkipped,
                isActive: readonly(isActive),
                start,
                stop,
                reset,
            });
        });
        onBeforeUnmount(() => {
            unregisterTour();
            stop();
        });
    }

    return {
        progress: readonly(progress),
        isActive: readonly(isActive),
        isCompleted,
        isSkipped,
        isFirstTime,
        completedCount,
        totalSteps,
        progressPercent,
        start,
        stop,
        reset,
        steps: HOMEPAGE_TOUR_STEPS,
    };
}
