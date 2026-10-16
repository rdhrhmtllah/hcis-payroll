import { reactive, watch } from 'vue';
const MOBILE_MEDIA_QUERY = '(max-width: 991.98px)';

const shellState = reactive({
    sidebarExpanded: false,
    sidebarLocked: false,
    sidebarTourLocked: false,
    mobileSidebarOpen: false,
    activeModule: null,
    activeSubMenu: null,
    showMessages: false,
    showNotifications: false,
    showActivity: false,
    showHelp: false,
    showProfileMenu: false,
    showBreadcrumb: false,
    searchQuery: '',
    isMobile: false,
});

let mediaQueryList = null;
let mediaListenerAttached = false;

function updateViewportState(matchesMobile) {
    shellState.isMobile = Boolean(matchesMobile);

    if (shellState.isMobile) {
        shellState.mobileSidebarOpen = false;
        shellState.sidebarExpanded = false;
        return;
    }

    shellState.mobileSidebarOpen = false;
    shellState.sidebarExpanded = Boolean(shellState.sidebarLocked);
}

function initViewportSync() {
    if (typeof window === 'undefined' || mediaListenerAttached) {
        return;
    }

    mediaQueryList = window.matchMedia(MOBILE_MEDIA_QUERY);
    updateViewportState(mediaQueryList.matches);

    const handleViewportChange = (event) => {
        updateViewportState(event.matches);
    };

    if (typeof mediaQueryList.addEventListener === 'function') {
        mediaQueryList.addEventListener('change', handleViewportChange);
    } else if (typeof mediaQueryList.addListener === 'function') {
        mediaQueryList.addListener(handleViewportChange);
    }

    mediaListenerAttached = true;
}

function resetInteractionState() {
    shellState.showMessages = false;
    shellState.showNotifications = false;
    shellState.showActivity = false;
    shellState.showHelp = false;
    shellState.showProfileMenu = false;
    shellState.showBreadcrumb = false;
}

function closeMobileSidebar() {
    if (shellState.sidebarTourLocked) {
        return;
    }

    shellState.mobileSidebarOpen = false;
}

function openMobileSidebar() {
    if (!shellState.isMobile) {
        return;
    }

    shellState.mobileSidebarOpen = true;
}

function toggleMobileSidebar() {
    if (!shellState.isMobile) {
        return;
    }

    if (shellState.sidebarTourLocked) {
        return;
    }

    shellState.mobileSidebarOpen = !shellState.mobileSidebarOpen;
}

function setSidebarExpandedByHover(value) {
    if (shellState.isMobile || shellState.sidebarLocked || shellState.sidebarTourLocked) {
        return;
    }

    shellState.sidebarExpanded = Boolean(value);
}

function toggleDesktopSidebarLock() {
    if (shellState.isMobile) {
        return;
    }

    if (shellState.sidebarTourLocked) {
        return;
    }

    shellState.sidebarLocked = !shellState.sidebarLocked;
    shellState.sidebarExpanded = shellState.sidebarLocked;
}

function togglePrimarySidebar() {
    if (shellState.isMobile) {
        toggleMobileSidebar();
        return;
    }

    toggleDesktopSidebarLock();
}

function setActiveModule(moduleId) {
    shellState.activeModule = moduleId || null;
    shellState.activeSubMenu = null;
}

function setActiveSubMenu(subMenuId) {
    shellState.activeSubMenu = subMenuId || null;
}

function syncNavigation(payload = {}) {
    shellState.activeModule = payload.activeModule || null;
    shellState.activeSubMenu = payload.activeSubMenu || null;

    if (!shellState.isMobile) {
        shellState.sidebarExpanded = shellState.sidebarLocked;
    }
}

function openPopover(key) {
    resetInteractionState();
    shellState[key] = true;
}

function togglePopover(key) {
    const nextValue = !shellState[key];
    resetInteractionState();
    shellState[key] = nextValue;
}

function closeAllPanels() {
    resetInteractionState();
    closeMobileSidebar();
}

function lockSidebarForTour() {
    shellState.sidebarTourLocked = true;
}

function unlockSidebarForTour() {
    shellState.sidebarTourLocked = false;
}

if (typeof window !== 'undefined') {
    initViewportSync();
}

export function useShellState() {
    initViewportSync();

    return {
        state: shellState,
        closeAllPanels,
        closeMobileSidebar,
        lockSidebarForTour,
        openMobileSidebar,
        openPopover,
        resetInteractionState,
        unlockSidebarForTour,
        setActiveModule,
        setActiveSubMenu,
        setSidebarExpandedByHover,
        syncNavigation,
        toggleDesktopSidebarLock,
        toggleMobileSidebar,
        togglePopover,
        togglePrimarySidebar,
    };
}
