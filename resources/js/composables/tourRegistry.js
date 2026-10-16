import { ref, computed, readonly, watch } from 'vue';

/**
 * Global, reactive tour registry.
 *
 * - Each page that ships an onboarding tour calls `registerTour({ ... })` in `onMounted`
 *   and `unregisterTour()` in `onBeforeUnmount`.
 * - AppTopbar reads `activeTour` to decide whether to show the "Mulai Tour" CTA
 *   in the help popover. If no page has registered a tour, the CTA is hidden
 *   so the help popover stays clean for pages without a tour.
 *
 * Shape of a registered tour:
 *   {
 *     component: 'homepage/homepage',    // Inertia component name (informational)
 *     label: 'Tour Homepage Dashboard',  // Title shown in CTA
 *     description: '...',                // Subtitle shown in CTA
 *     progress: ComputedRef<object>,     // { status, completed_steps, current_step, ... }
 *     totalSteps: number,                // total steps in this tour
 *     completedCount: ComputedRef<number>,
 *     progressPercent: ComputedRef<number>,
 *     isFirstTime: ComputedRef<boolean>,
 *     isActive: ComputedRef<boolean>,
 *     start: ({ fromBeginning?: boolean }) => void,
 *     stop: () => void,
 *     reset: () => Promise<void>,
 *   }
 */

const _activeTour = ref(null);

export function registerTour(tour) {
    _activeTour.value = tour;
}

export function unregisterTour() {
    _activeTour.value = null;
}

/**
 * Penghalang tour otomatis.
 *
 * Modal global (mis. Pembaruan Data Karyawan) dipasang di AppShell, terpisah
 * dari halaman yang memiliki tour. Tanpa penanda bersama ini tour pertama-kali
 * menyala tepat di atas modal itu: dua tirai bertumpuk dan orangnya tidak tahu
 * mana yang harus diurus dulu. Modal menahan, tour otomatis menunggu dilepas.
 */
const _penghalang = ref(0);

/** Tahan tour otomatis; kembalikan fungsi pelepas yang aman dipanggil dua kali. */
export function tahanTourOtomatis() {
    _penghalang.value += 1;
    let dilepas = false;

    return () => {
        if (dilepas) return;
        dilepas = true;
        _penghalang.value -= 1;
    };
}

/** Selesai begitu tidak ada lagi yang menahan tour otomatis. */
export function tungguTourBolehOtomatis() {
    if (_penghalang.value === 0) return Promise.resolve();

    return new Promise((resolve) => {
        const berhenti = watch(_penghalang, (jumlah) => {
            if (jumlah > 0) return;
            berhenti();
            resolve();
        });
    });
}

export function useActiveTour() {
    return {
        activeTour: readonly(_activeTour),
        hasTour: computed(() => _activeTour.value !== null),
    };
}
