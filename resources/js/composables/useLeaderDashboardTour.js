import { computed, onBeforeUnmount, onMounted, readonly, ref } from 'vue';
import axios from 'axios';
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import '../../css/homepage-tour.css';
import { registerTour, unregisterTour } from './tourRegistry.js';

export const LEADER_DASHBOARD_TOUR_STEPS = [
    {
        key: 'welcome',
        icon: '👋',
        popover: {
            title: 'Tour Leader Dashboard',
            description: 'Tour singkat untuk mengenali area monitoring tim. Klik tombol "Lanjut" untuk mulai.',
        },
    },
    {
        key: 'hero',
        icon: '📌',
        element: '[data-tour="leader-hero"]',
        popover: {
            title: 'Ringkasan Utama Leader',
            description:
                'Bagian atas menampilkan konteks periode, jumlah anggota, kehadiran hari ini, dan total antrian approval.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'hero-dashboard-personal',
        icon: '🪪',
        element: '[data-tour="leader-hero-dashboard-personal"]',
        popover: {
            title: 'Dashboard Personal',
            description:
                'Tombol ini membawa kamu ke dashboard personal milikmu sendiri, untuk melihat absensi, KPI, dan LMS pribadi tanpa keluar dari konteks monitoring tim.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'hero-action-focus',
        icon: '🔔',
        element: '[data-tour="leader-hero-action-focus"]',
        popover: {
            title: 'Perlu Ditindak',
            description:
                'Kartu ini merangkum item paling mendesak dari Action Center. Klik kartu untuk lompat ke daftar tindak lanjut.',
            side: 'left',
            align: 'start',
        },
    },
    {
        key: 'insight-hcis',
        icon: '🩺',
        element: '[data-tour="leader-insight-hcis"]',
        mobilePanel: 'hcis',
        popover: {
            title: 'Insight HCIS Team',
            description:
                'Pantau kondisi absensi tim: hadir, terlambat, belum check-in/check-out, cuti/izin, dan trend issue.',
            side: 'right',
            align: 'start',
        },
    },
    {
        key: 'insight-kpi',
        icon: '📊',
        element: '[data-tour="leader-insight-kpi"]',
        mobilePanel: 'kpi',
        popover: {
            title: 'Insight KPI Team',
            description: 'Lihat rata-rata performance, periode KPI, alert, dan trend pencapaian tim.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'insight-lms',
        icon: '🎓',
        element: '[data-tour="leader-insight-lms"]',
        mobilePanel: 'lms',
        popover: {
            title: 'Insight LMS Team',
            description:
                'Ringkasan progress training tim, jumlah training selesai, pending, dan anggota yang perlu follow up.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'team-members',
        icon: '👥',
        element: '[data-tour="leader-team-members"]',
        before: 'load-team-members',
        mobilePanel: 'members',
        popover: {
            title: 'Daftar Anggota Tim',
            description:
                'Team List adalah pusat monitoring per orang. Dari sini leader bisa melihat siapa yang aman, siapa yang punya risiko, dan siapa yang perlu ditindak dulu.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-members-filters',
        icon: '🔎',
        element: '[data-tour="leader-team-members-filters"]',
        before: 'load-team-members',
        mobilePanel: 'members',
        popover: {
            title: 'Filter & Pencarian Team List',
            description:
                'Gunakan kolom pencarian untuk mencari nama anggota. Filter Absensi membaca kondisi hadir/telat/alpha/belum checkout/cuti. Filter KPI membantu mencari anggota dengan alert KPI, sedangkan filter LMS mencari progress training yang pending atau sudah selesai.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-members-content',
        icon: '📋',
        element: '[data-tour="leader-team-members-content"]',
        before: 'load-team-members',
        mobilePanel: 'members',
        popover: {
            title: 'Isi Daftar Anggota',
            description:
                'Setiap baris adalah satu anggota tim. Baca dari kiri ke kanan: identitas anggota, indikator risiko HCIS-KPI-LMS, lalu status absensi hari ini dan tombol expand detail.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-member-card',
        icon: '👤',
        element: '[data-tour="leader-team-member-card"]',
        before: 'load-team-members',
        mobilePanel: 'members',
        popover: {
            title: 'Satu Kartu Anggota',
            description:
                'Kartu anggota merangkum kondisi orang tersebut. Klik barisnya untuk membuka detail prioritas, alasan risiko, next best action, kalender mingguan, KPI, LMS, cuti, lembur, dan approval.',
            side: 'top',
            align: 'center',
        },
    },
    {
        key: 'team-member-identity',
        icon: '🏷️',
        element: '[data-tour="leader-team-member-identity"]',
        before: 'load-team-members',
        mobilePanel: 'members',
        popover: {
            title: 'Identitas, Prioritas, dan Shift',
            description:
                'Bagian kiri menampilkan inisial, nama, jabatan, dan departemen. Badge prioritas menunjukkan tingkat urgensi follow up. Badge shift menunjukkan jadwal kerja anggota, termasuk nama shift dan jamnya.',
            side: 'bottom',
            align: 'start',
        },
    },
    {
        key: 'team-member-metrics',
        icon: '📊',
        element: '[data-tour="leader-team-member-metrics"]',
        before: 'load-team-members',
        mobilePanel: 'members',
        popover: {
            title: 'Cara Membaca HCIS, KPI, dan LMS',
            description:
                'HCIS Risk adalah jumlah masalah absensi aktif. KPI berisi skor atau jumlah alert KPI. LMS menunjukkan persentase progress training. Warna warning/danger berarti perlu perhatian lebih cepat.',
            side: 'bottom',
            align: 'center',
        },
    },
    {
        key: 'team-member-status',
        icon: '🧭',
        element: '[data-tour="leader-team-member-status"]',
        before: 'load-team-members',
        mobilePanel: 'members',
        popover: {
            title: 'Status Hari Ini & Expand Detail',
            description:
                'Label kanan menunjukkan status absensi hari ini, misalnya hadir, telat, izin/cuti, alpha, atau belum checkout. Ikon panah dipakai untuk membuka detail anggota.',
            side: 'bottom',
            align: 'end',
        },
    },
    {
        key: 'team-member-detail',
        icon: '🧩',
        element: '[data-tour="leader-team-member-detail"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Detail Anggota Terbuka',
            description:
                'Tour otomatis membuka anggota pertama sebagai contoh. Area detail ini dipakai untuk membaca kondisi anggota lebih lengkap setelah barisnya di-expand.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-member-detail-summary',
        icon: '🎯',
        element: '[data-tour="leader-team-member-detail-summary"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Ringkasan Prioritas',
            description:
                'Bagian atas menjawab tiga hal cepat: masalah utama yang sedang terjadi, dampak jika ditunda, dan tindakan pertama yang disarankan untuk leader.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-member-priority-reasons',
        icon: '🧠',
        element: '[data-tour="leader-team-member-priority-reasons"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Alasan Prioritas',
            description:
                'Panel ini menjelaskan kenapa anggota masuk prioritas tertentu. Setiap indikator menampilkan bukti masalah dan kontribusi skor risikonya.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-member-next-actions',
        icon: '⚡',
        element: '[data-tour="leader-team-member-next-actions"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Next Best Action',
            description:
                'Daftar aksi yang bisa dilakukan leader. Tiap tombol berisi rekomendasi tindakan, dampaknya, dan tingkat urgensinya agar follow up tidak perlu ditebak.',
            side: 'top',
            align: 'end',
        },
    },
    {
        key: 'team-member-hcis-detail',
        icon: '🕘',
        element: '[data-tour="leader-team-member-hcis-detail"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Detail HCIS Absensi & Lembur',
            description:
                'Panel HCIS merangkum status hari ini, jumlah issue aktif, total lembur, jam check-in/check-out, alpha, sakit, izin, cuti, terlambat, pulang cepat, no check-in, dan no checkout.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-member-weekly-calendar',
        icon: '📆',
        element: '[data-tour="leader-team-member-weekly-calendar"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Kalender Mingguan Anggota',
            description:
                'Kalender ini membantu membaca pola harian anggota. Warna dan ikon menunjukkan hadir, telat, issue, sedang bekerja, izin/cuti, atau OFF. Klik tanggal untuk melihat detail hari tersebut.',
            side: 'left',
            align: 'center',
        },
    },
    {
        key: 'team-member-issue-list',
        icon: '🚨',
        element: '[data-tour="leader-team-member-issue-list"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Daftar Issue Absensi',
            description:
                'Di sini muncul daftar issue absensi yang perlu dicek. Kalau kosong, berarti absensi anggota pada periode tersebut aman.',
            side: 'top',
            align: 'end',
        },
    },
    {
        key: 'team-member-kpi-detail',
        icon: '📈',
        element: '[data-tour="leader-team-member-kpi-detail"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Detail KPI Anggota',
            description:
                'Panel KPI dipakai untuk membaca periode KPI, skor, achievement, alert KPI, achievement bulanan, dan parameter penilaian. Filter tahun/periode membantu melihat data KPI pada periode lain.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-member-lms-detail',
        icon: '📚',
        element: '[data-tour="leader-team-member-lms-detail"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Detail LMS Anggota',
            description:
                'Panel LMS menampilkan total training, training selesai, training pending, progress, rata-rata score, learning gain, dan daftar training yang sedang dipantau.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-member-approval-detail',
        icon: '✅',
        element: '[data-tour="leader-team-member-approval-detail"]',
        before: 'open-first-team-member',
        mobilePanel: 'members',
        popover: {
            title: 'Cuti, Lembur, dan Approval',
            description:
                'Panel ini menunjukkan sisa cuti, cuti terpakai, jam lembur berdasarkan kategori, dan daftar pengajuan yang berhubungan dengan anggota tersebut.',
            side: 'top',
            align: 'start',
        },
    },
    {
        key: 'team-calendar',
        icon: '📅',
        element: '[data-tour="leader-team-calendar"]',
        before: 'load-team-calendar',
        mobilePanel: 'calendar',
        popover: {
            title: 'Kalender Cutoff Tim',
            description:
                'Kalender ini membantu membaca pola harian tim dalam periode cutoff: hadir, telat, issue, izin/cuti, dan hari bermasalah.',
            side: 'top',
            align: 'center',
        },
    },
    {
        key: 'action-center',
        icon: '✅',
        element: '[data-tour="leader-action-center"]',
        mobilePanel: 'action',
        popover: {
            title: 'Action Center',
            description:
                'Semua issue aktif yang perlu ditindak muncul di sini, termasuk absensi, KPI, dan item yang bisa ditandai selesai.',
            side: 'left',
            align: 'start',
        },
    },
    {
        key: 'approval-queue',
        icon: '📝',
        element: '[data-tour="leader-approval-queue"]',
        before: 'load-approvals',
        mobilePanel: 'approval',
        popover: {
            title: 'Antrian Approval',
            description:
                'Daftar pengajuan tim yang menunggu keputusan leader, lengkap dengan nama anggota, konteks, dan status.',
            side: 'left',
            align: 'center',
        },
    },
    {
        key: 'issue-heatmap',
        icon: '🔥',
        element: '[data-tour="leader-issue-heatmap"]',
        before: 'load-heatmap',
        mobilePanel: 'heatmap',
        popover: {
            title: 'Heatmap Issue Tim',
            description:
                'Heatmap memperlihatkan pola masalah per anggota dari hari ke hari, sehingga follow up bisa lebih cepat dan terarah.',
            side: 'left',
            align: 'center',
        },
    },
    {
        key: 'training-pending',
        icon: '📚',
        element: '[data-tour="leader-training-pending"]',
        before: 'load-training',
        mobilePanel: 'training',
        popover: {
            title: 'Training Pending',
            description:
                'Panel ini menampilkan anggota dengan training LMS yang belum selesai dan progress pembelajarannya.',
            side: 'left',
            align: 'end',
        },
    },
    {
        key: 'farewell',
        icon: '🎉',
        popover: {
            title: 'Tour Leader Dashboard Selesai',
            description:
                'Sekarang kamu sudah tahu area utama untuk memantau tim. Tour ini bisa dijalankan ulang dari tombol bantuan.',
        },
    },
];

const TOUR_KEY = 'leader-dashboard';
const TOUR_TARGET_STABLE_TIMEOUT = 1200;
const TOUR_STEP_SETTLE_DELAY = 180;
const TOUR_STAGE_ANIMATION_DELAY = 440;
const TOUR_SMOOTH_SCROLL_DELAY = 320;
const TOUR_POPOVER_WIDTH = 400;

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

function resolveSteps() {
    return LEADER_DASHBOARD_TOUR_STEPS.filter((step) => {
        if (!step.element) return true;
        if (typeof document === 'undefined') return false;
        if (step.before) return true;

        const el = document.querySelector(step.element);
        if (!el) return false;

        const rect = el.getBoundingClientRect();
        return rect.width > 0 && rect.height > 0;
    });
}

export function useLeaderDashboardTour(initialProgress, registration = null, options = {}) {
    const progress = ref(
        initialProgress && typeof initialProgress === 'object'
            ? { ...defaultProgress(), ...initialProgress }
            : defaultProgress(),
    );
    const isActive = ref(false);
    let driverInstance = null;
    let runtimeSteps = resolveSteps();
    let runtimeKeys = runtimeSteps.map((step) => step.key);
    let isMovingStep = false;

    const totalSteps = LEADER_DASHBOARD_TOUR_STEPS.length;
    const completedCount = computed(() =>
        Array.isArray(progress.value.completed_steps) ? progress.value.completed_steps.length : 0,
    );
    const isCompleted = computed(() => progress.value.status === 'completed');
    const isSkipped = computed(() => progress.value.status === 'skipped');
    const isFirstTime = computed(() => progress.value.status === 'not_started');
    const progressPercent = computed(() => Math.min(100, Math.round((completedCount.value / totalSteps) * 100)));

    const persist = async ({ step, status }) => {
        try {
            const { data } = await axios.post('/tour/progress', { tour: TOUR_KEY, step, status });
            if (data?.progress) progress.value = { ...defaultProgress(), ...data.progress };
        } catch (err) {
            console.warn('[leader-dashboard-tour] persist failed', err?.response?.status || err?.message);
        }
    };

    const persistSkip = async (atStep) => {
        try {
            const { data } = await axios.post('/tour/skip', { tour: TOUR_KEY, at_step: atStep || null });
            if (data?.progress) progress.value = { ...defaultProgress(), ...data.progress };
        } catch (err) {
            console.warn('[leader-dashboard-tour] skip failed', err?.response?.status || err?.message);
        }
    };

    const reset = async () => {
        try {
            const { data } = await axios.post('/tour/reset', { tour: TOUR_KEY });
            if (data?.progress) progress.value = { ...defaultProgress(), ...data.progress };
        } catch (err) {
            console.warn('[leader-dashboard-tour] reset failed', err?.response?.status || err?.message);
        }
    };

    const getElement = (selector) => {
        if (!selector || typeof document === 'undefined') return null;
        return document.querySelector(selector);
    };

    const isReducedMotion = () =>
        typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches;

    const wait = (ms = 0) => new Promise((resolve) => setTimeout(resolve, ms));

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
        if (!el || typeof window === 'undefined') return false;
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
            return true;
        }

        const startedAt = performance.now();
        let previousSignature = null;
        let stableFrames = 0;

        while (performance.now() - startedAt < timeout) {
            await waitForFrames(1);
            const element = getElement(selector);

            if (!isElementVisible(element)) {
                previousSignature = null;
                stableFrames = 0;
                continue;
            }

            const signature = getElementRectSignature(element);
            if (signature === previousSignature) {
                stableFrames += 1;
                if (stableFrames >= 4) return true;
            } else {
                previousSignature = signature;
                stableFrames = 0;
            }
        }

        return false;
    };

    const setTourMoving = (moving) => {
        if (typeof document === 'undefined') return;
        document.documentElement.classList.toggle('hcis-tour-moving-step', Boolean(moving));
    };

    const setActiveTourStepClass = (key = null) => {
        if (typeof document === 'undefined') return;
        const root = document.documentElement;
        Array.from(root.classList)
            .filter((className) => className.startsWith('hcis-tour-step-'))
            .forEach((className) => root.classList.remove(className));

        if (key) {
            root.classList.add(`hcis-tour-step-${key}`);
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

        const element = getElement(step.element);
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

        if (isMobile) {
            // Mobile: the leader dashboard stacks sections vertically.
            // We need ~280px below to fit the popover without it covering
            // the highlighted element.
            const MIN_MOBILE_POPOVER_SPACE = 280;
            const useBottom =
                sideSpace.bottom >= MIN_MOBILE_POPOVER_SPACE || sideSpace.bottom >= sideSpace.top;

            // Tall targets (hero, insight cards, team list, etc.): align to
            // start so the popover sits near the edge instead of blanketing
            // the middle of the highlighted section.
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

        return steps.map((step, index) => {
            const iconChip = step.icon ? `<span class="hcis-tour-step-icon">${step.icon}</span>` : '';
            const titleHtml = `<div class="hcis-tour-step-head">${iconChip}<span>${step.popover.title}</span></div>`;
            const percent = Math.round(((index + 1) / total) * 100);
            const placement = resolvePopoverPlacement(step);
            const progressHtml =
                `<div class="hcis-tour-progress">` +
                `<span class="hcis-tour-progress__bar"><i style="width:${percent}%"></i></span>` +
                `<span class="hcis-tour-progress__label">${index + 1}/${total}</span>` +
                `</div>`;

            return {
                element: step.element,
                popover: {
                    ...step.popover,
                    ...placement,
                    title: titleHtml,
                    description: `${step.popover.description}${progressHtml}`,
                    showButtons: ['next', 'previous', 'close'],
                },
            };
        });
    };

    const waitForElement = async (selector, timeout = 1200) => {
        if (!selector || typeof document === 'undefined') return true;

        const startedAt = Date.now();
        while (Date.now() - startedAt < timeout) {
            const el = getElement(selector);
            if (el) {
                const rect = el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) return true;
            }
            await wait(80);
        }

        return false;
    };

    // Cek apakah elemen sudah cukup terlihat di viewport vertikal (dengan margin).
    // Kalau sudah, kita tidak perlu scroll sama sekali — mencegah hero/layout
    // bergeser hanya untuk menempatkan target di tengah.
    const isElementSufficientlyInView = (element) => {
        if (!element || typeof window === 'undefined') return false;
        const rect = element.getBoundingClientRect();
        const viewportH = window.innerHeight || document.documentElement.clientHeight || 0;
        const isMobile = window.innerWidth <= 640;
        // Mobile popovers are taller (~280px); a larger margin keeps the
        // highlighted element visible above/below the popover card.
        const margin = isMobile ? 110 : 80;
        return rect.top >= margin && rect.bottom <= viewportH - margin;
    };

    const scrollElementIntoView = (selector, { behavior = 'smooth' } = {}) => {
        if (!selector || typeof document === 'undefined') return false;

        const element = getElement(selector);
        if (!element) return false;

        // Sudah terlihat penuh → jangan scroll (hindari geser layout yang merusak).
        if (isElementSufficientlyInView(element)) {
            return true;
        }

        element.scrollIntoView({
            behavior: isReducedMotion() ? 'auto' : behavior,
            // 'nearest' menggeser seminimal mungkin (cukup untuk masuk viewport),
            // bukan memaksa ke tengah yang bikin hero melompat.
            block: 'nearest',
            inline: 'nearest',
        });

        return true;
    };

    const runBeforeStep = async (step) => {
        // Open mobile accordion panel *before* any other loading — the
        // element won't be measurable (zero height) until the accordion body
        // is visible.
        if (step?.mobilePanel && typeof options.beforeStep === 'function') {
            await options.beforeStep('open-mobile-panel', step);
            // Wait for the CSS accordion transition to finish so the element
            // has its final dimensions before driver measures it.
            await wait(300);
        }

        if (!step?.before || typeof options.beforeStep !== 'function') {
            return true;
        }

        await options.beforeStep(step.before, step);
        await wait(160);
        const isReady = await waitForElement(step.element);
        if (!isReady) {
            return false;
        }

        scrollElementIntoView(step.element, { behavior: 'auto' });
        await waitForElementStable(step.element);
        return true;
    };

    const getStepDistance = (currentStep, nextStep) => {
        const currentElement = getElement(currentStep?.element);
        const nextElement = getElement(nextStep?.element);
        if (!currentElement || !nextElement) return 0;

        const currentRect = currentElement.getBoundingClientRect();
        const nextRect = nextElement.getBoundingClientRect();
        return Math.abs(currentRect.top - nextRect.top) + Math.abs(currentRect.left - nextRect.left);
    };

    const hasLargeTarget = (step) => {
        const element = getElement(step?.element);
        if (!element) return false;
        const rect = element.getBoundingClientRect();
        return rect.height > 520 || rect.width > 820;
    };

    const shouldAnimateHighlightMove = (currentStep, nextStep) => {
        if (!currentStep?.element || !nextStep?.element) return false;
        if (hasLargeTarget(currentStep) || hasLargeTarget(nextStep)) return false;
        return getStepDistance(currentStep, nextStep) < 620;
    };

    const setDriverHighlightAnimation = (enabled) => {
        if (!driverInstance?.setConfig || !driverInstance?.getConfig) return;
        driverInstance.setConfig({
            ...driverInstance.getConfig(),
            animate: enabled,
        });
    };

    const prepareStepForHighlight = async (step, { smooth = true } = {}) => {
        const isReady = await runBeforeStep(step);
        if (!isReady) return false;

        if (step?.element) {
            scrollElementIntoView(step.element, { behavior: smooth ? 'smooth' : 'auto' });
            await wait(smooth && !isReducedMotion() ? TOUR_SMOOTH_SCROLL_DELAY : 40);
            await waitForElementStable(step.element);
        } else {
            await waitForFrames(2);
        }

        await waitForFrames(2);
        return true;
    };

    const moveToStep = async (nextIndex) => {
        if (!driverInstance || isMovingStep) return;

        const currentIndex = driverInstance.getActiveIndex?.() ?? 0;
        const currentStep = runtimeSteps[currentIndex];
        const nextStep = runtimeSteps[nextIndex];
        if (!nextStep) return;

        isMovingStep = true;
        setTourMoving(true);
        setActiveTourStepClass(nextStep.key);

        try {
            const animateHighlight = shouldAnimateHighlightMove(currentStep, nextStep);
            const isReady = await prepareStepForHighlight(nextStep, { smooth: true });
            if (!isReady) {
                const fallbackIndex = nextIndex + 1;
                const fallbackStep = runtimeSteps[fallbackIndex];
                if (fallbackStep) {
                    const fallbackReady = await prepareStepForHighlight(fallbackStep, { smooth: true });
                    if (fallbackReady) {
                        setActiveTourStepClass(fallbackStep.key);
                        setDriverHighlightAnimation(shouldAnimateHighlightMove(currentStep, fallbackStep));
                        if (driverInstance.moveTo) {
                            driverInstance.moveTo(fallbackIndex);
                        } else if (fallbackIndex > currentIndex) {
                            driverInstance.moveNext();
                        } else {
                            driverInstance.movePrevious();
                        }
                        await wait(TOUR_STEP_SETTLE_DELAY);
                        await waitForFrames(2);
                    }
                }
                return;
            }

            setDriverHighlightAnimation(animateHighlight);
            if (driverInstance.moveTo) {
                driverInstance.moveTo(nextIndex);
            } else {
                if (nextIndex > currentIndex) driverInstance.moveNext();
                else driverInstance.movePrevious();
            }

            await wait(animateHighlight ? TOUR_STAGE_ANIMATION_DELAY : TOUR_STEP_SETTLE_DELAY);
            await waitForFrames(2);
            driverInstance?.refresh?.();
        } finally {
            isMovingStep = false;
            setTourMoving(false);
        }
    };

    const start = ({ fromBeginning = true } = {}) => {
        if (isActive.value) return;

        runtimeSteps = resolveSteps();
        runtimeKeys = runtimeSteps.map((step) => step.key);

        if (!runtimeSteps.length) {
            console.warn('[leader-dashboard-tour] no anchors found, aborting tour');
            return;
        }

        const startIndex = (() => {
            if (fromBeginning) return 0;
            const idx = runtimeKeys.indexOf(progress.value.current_step);
            if (idx === -1) return 0;
            return Math.min(idx + 1, runtimeKeys.length - 1);
        })();

        const isMobile = typeof window !== 'undefined' && window.innerWidth <= 640;

        driverInstance = driver({
            showProgress: false,
            allowClose: true,
            disableActiveInteraction: false,
            stagePadding: 12,
            stageRadius: 16,
            smoothScroll: true,
            animate: true,
            overlayOpacity: 0.5,
            overlayColor: '#0b1220',
            popoverOffset: isMobile ? 24 : 16,
            popoverClass: 'hcis-tour-popover',
            nextBtnText: 'Lanjut',
            prevBtnText: 'Kembali',
            doneBtnText: 'Selesai',
            steps: buildDriverSteps(runtimeSteps),
            onHighlightStarted: (_el, _step, opts) => {
                const key = runtimeKeys[opts.state.activeIndex];
                if (key) {
                    progress.value.current_step = key;
                    setActiveTourStepClass(key);
                }
            },
            onNextClick: (_el, _step, opts) => {
                const index = opts.state.activeIndex;
                const key = runtimeKeys[index];
                const isLast = index === runtimeKeys.length - 1;
                if (key) {
                    persist({ step: key, status: isLast ? 'completed' : 'in_progress' });
                }
                if (isLast) {
                    driverInstance.destroy();
                } else {
                    moveToStep(index + 1);
                }
            },
            onPrevClick: (_el, _step, opts) => {
                const index = opts.state.activeIndex;
                if (index > 0) moveToStep(index - 1);
            },
            onCloseClick: () => {
                const key = runtimeKeys[driverInstance.getActiveIndex?.() ?? 0];
                persistSkip(key);
                driverInstance.destroy();
            },
            onDestroyed: () => {
                isActive.value = false;
                isMovingStep = false;
                setTourMoving(false);
                setActiveTourStepClass(null);
                document.documentElement.classList.remove('hcis-tour-active');
            },
        });

        document.documentElement.classList.add('hcis-tour-active');
        setActiveTourStepClass(runtimeSteps[startIndex]?.key || null);
        isActive.value = true;
        prepareStepForHighlight(runtimeSteps[startIndex], { smooth: false }).finally(() => {
            driverInstance?.drive(startIndex);
        });
    };

    const stop = () => {
        if (driverInstance) driverInstance.destroy();
    };

    if (registration) {
        onMounted(() => {
            registerTour({
                component: registration.component || null,
                label: registration.label || 'Tour Leader Dashboard',
                description: registration.description || 'Pandu kamu mengenali area monitoring tim.',
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
        steps: LEADER_DASHBOARD_TOUR_STEPS,
    };
}
