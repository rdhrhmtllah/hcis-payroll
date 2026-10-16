/**  * Shell Sidebar - Blade interaction layer
 * Mirrors the Vue sidebar behavior for Blade-rendered pages.
 */

import { runLogoutTransition } from './utils/logout-transition.js';

(function initShellSidebar() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShellSidebar, { once: true });
        return;
    }

    const STORAGE_KEY = 'shellState';
    const MOBILE_BREAKPOINT = 991.98;

    const sidebarRoot = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    const sidebarBrandBtn = document.getElementById('sidebarBrandBtn');
    const sidebarProfileMenu = document.getElementById('sidebarProfileMenu');
    const sidebarProfileToggle = document.getElementById('sidebarProfileToggle');
    const sidebarProfileChevron = document.getElementById('sidebarProfileChevron');

    if (!sidebarRoot) {
        return;
    }

    const shellState = loadState();
    const openModules = {};
    const openGroups = {};
    const moduleMemory = {};
    let moduleClickTimer = null;

    function loadState() {
        try {
            const stored = window.localStorage.getItem(STORAGE_KEY);
            const parsed = stored ? JSON.parse(stored) : {};
            const hasSidebarPreference =
                Object.prototype.hasOwnProperty.call(parsed, 'sidebarExpanded') ||
                Object.prototype.hasOwnProperty.call(parsed, 'sidebarLocked');

            return {
                isMobile: window.innerWidth <= MOBILE_BREAKPOINT,
                sidebarExpanded: Boolean(parsed.sidebarExpanded),
                sidebarLocked: Boolean(parsed.sidebarLocked),
                mobileSidebarOpen: Boolean(parsed.mobileSidebarOpen),
                showProfileMenu: Boolean(parsed.showProfileMenu),
                hasSidebarPreference,
            };
        } catch (error) {
            return {
                isMobile: window.innerWidth <= MOBILE_BREAKPOINT,
                sidebarExpanded: false,
                sidebarLocked: false,
                mobileSidebarOpen: false,
                showProfileMenu: false,
                hasSidebarPreference: false,
            };
        }
    }

    function saveState() {
        try {
            const stored = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
            window.localStorage.setItem(
                STORAGE_KEY,
                JSON.stringify({
                    ...stored,
                    sidebarExpanded: shellState.sidebarExpanded,
                    sidebarLocked: shellState.sidebarLocked,
                    mobileSidebarOpen: shellState.mobileSidebarOpen,
                    showProfileMenu: shellState.showProfileMenu,
                }),
            );
        } catch (error) {
            console.warn('Failed to persist sidebar state:', error);
        }
    }

    function getVisibleState() {
        return shellState.isMobile ? shellState.mobileSidebarOpen : shellState.sidebarExpanded;
    }

    function clearOpenState(target) {
        Object.keys(target).forEach((key) => {
            delete target[key];
        });
    }

    function rememberModuleGroup(moduleId, groupId) {
        if (!moduleId) {
            return;
        }

        moduleMemory[moduleId] = {
            groupId: groupId || null,
        };
    }

    function setProfileMenuVisibility(visible) {
        if (!sidebarProfileMenu || !sidebarProfileToggle) {
            return;
        }

        sidebarProfileMenu.classList.toggle('is-visible', visible);
        sidebarProfileToggle.classList.toggle('is-active', visible);

        if (sidebarProfileChevron) {
            sidebarProfileChevron.classList.toggle('is-rotated', visible);
        }
    }

    function closeProfileMenu() {
        shellState.showProfileMenu = false;
        saveState();
        applySidebarState();
    }

    function closeSidebarPanels() {
        shellState.showProfileMenu = false;
        shellState.mobileSidebarOpen = false;
        shellState.sidebarExpanded = false;
        shellState.sidebarLocked = false;
        saveState();
        applySidebarState();
    }

    function applySidebarState() {
        const visible = getVisibleState();

        sidebarRoot.classList.toggle('is-expanded', visible);
        sidebarRoot.classList.toggle('is-mobile-open', shellState.isMobile && shellState.mobileSidebarOpen);

        if (sidebarBackdrop) {
            sidebarBackdrop.classList.toggle('is-visible', visible);
        }

        const profileVisible = shellState.showProfileMenu && visible;
        setProfileMenuVisibility(profileVisible);

        renderOpenState();
    }

    function renderOpenState() {
        const visible = getVisibleState();

        sidebarRoot.querySelectorAll('.shell-module').forEach((moduleEl) => {
            const moduleId = moduleEl.dataset.shellModule;
            const moduleOpen = Boolean(openModules[moduleId]);
            const expandIcon = moduleEl.querySelector('.shell-expand-btn .shell-rotate');
            const submenuWrapper = moduleEl.querySelector('[data-shell-submenu-wrapper]');

            moduleEl.classList.toggle('is-open', moduleOpen);

            if (expandIcon) {
                expandIcon.classList.toggle('is-rotated', moduleOpen);
            }

            if (submenuWrapper) {
                submenuWrapper.classList.toggle('is-open', visible && moduleOpen);
            }

            moduleEl.querySelectorAll('[data-shell-group]').forEach((groupEl) => {
                const groupId = groupEl.dataset.shellGroup;
                const groupOpen = Boolean(openGroups[groupId]);
                const toggleButton = groupEl.querySelector('[data-shell-action="toggle-group"]');
                const toggleIcon = groupEl.querySelector('.shell-submenu-toggle .shell-rotate');
                const itemsWrapper = groupEl.querySelector('[data-shell-submenu-items]');

                if (toggleButton) {
                    toggleButton.classList.toggle('is-open', groupOpen);
                }

                if (toggleIcon) {
                    toggleIcon.classList.toggle('is-rotated-right', groupOpen);
                }

                if (itemsWrapper) {
                    itemsWrapper.classList.toggle('is-open', visible && groupOpen);
                }
            });
        });
    }

    function syncOpenStateFromActive() {
        clearOpenState(openModules);
        clearOpenState(openGroups);

        const activeModuleEl = sidebarRoot.querySelector('.shell-nav-item.is-active[data-shell-action="module"]');

        if (!activeModuleEl) {
            renderOpenState();
            return;
        }

        const activeModuleId = activeModuleEl.dataset.moduleId;
        if (activeModuleId) {
            openModules[activeModuleId] = true;
        }

        const activeGroupEl = activeModuleEl
            .closest('.shell-module')
            ?.querySelector('.shell-submenu-item.is-active');

        const activeGroupContainer = activeGroupEl?.closest('[data-shell-group]');
        if (activeGroupContainer) {
            openGroups[activeGroupContainer.dataset.shellGroup] = true;
            rememberModuleGroup(activeModuleId, activeGroupContainer.dataset.shellGroup);
        }

        renderOpenState();
    }

    function restoreModuleState(moduleId) {
        if (!moduleId) {
            return;
        }

        clearOpenState(openModules);
        clearOpenState(openGroups);
        openModules[moduleId] = true;

        const rememberedGroupId = moduleMemory[moduleId]?.groupId;
        if (rememberedGroupId) {
            openGroups[rememberedGroupId] = true;
        }

        renderOpenState();
    }

    function toggleModule(moduleId) {
        if (!moduleId) {
            return;
        }

        const wasOpen = Boolean(openModules[moduleId]);
        clearOpenState(openModules);
        clearOpenState(openGroups);

        if (!wasOpen) {
            openModules[moduleId] = true;

            const rememberedGroupId = moduleMemory[moduleId]?.groupId;
            if (rememberedGroupId) {
                openGroups[rememberedGroupId] = true;
            }
        }

        renderOpenState();
    }

    function toggleGroup(moduleId, groupId) {
        if (!moduleId || !groupId) {
            return;
        }

        openModules[moduleId] = true;

        const nextState = !openGroups[groupId];
        clearOpenState(openGroups);

        if (nextState) {
            openGroups[groupId] = true;
        }

        rememberModuleGroup(moduleId, groupId);
        renderOpenState();
    }

    function navigateTo(url) {
        if (!url) {
            return;
        }

        window.location.assign(url);
    }

    function closePanels() {
        closeSidebarPanels();
        window.shellTopbar?.resetInteractionState?.();
    }

    function handleHomeClick(url) {
        closePanels();
        navigateTo(url || '/homepage');
    }

    function handleModuleSingleClick(button) {
        const moduleId = button.dataset.moduleId;
        const moduleCode = button.dataset.moduleCode;
        const landingUrl = button.dataset.landingUrl;

        if (moduleClickTimer) {
            window.clearTimeout(moduleClickTimer);
        }

        moduleClickTimer = window.setTimeout(() => {
            const isSameModule = button.classList.contains('is-active') || moduleMemory[moduleId]?.moduleCode === moduleCode;

            if (isSameModule) {
                restoreModuleState(moduleId);
                moduleClickTimer = null;
                return;
            }

            clearOpenState(openGroups);
            clearOpenState(openModules);
            openModules[moduleId] = true;
            rememberModuleGroup(moduleId, null);
            renderOpenState();
            closePanels();
            navigateTo(landingUrl);
            moduleClickTimer = null;
        }, 220);

        moduleMemory[moduleId] = {
            ...(moduleMemory[moduleId] || {}),
            moduleCode,
        };
    }

    function handleModuleDoubleClick(button) {
        const moduleId = button.dataset.moduleId;

        if (moduleClickTimer) {
            window.clearTimeout(moduleClickTimer);
            moduleClickTimer = null;
        }

        toggleModule(moduleId);
    }

    function handleSubMenuClick(button) {
        const url = button.dataset.url;
        const moduleId = button.dataset.moduleId;
        const groupId = button.dataset.groupId;

        if (moduleId && groupId) {
            openModules[moduleId] = true;
            openGroups[groupId] = true;
            rememberModuleGroup(moduleId, groupId);
            renderOpenState();
        }

        closePanels();
        navigateTo(url);
    }

    function toggleDesktopSidebarLock() {
        if (shellState.isMobile) {
            return;
        }

        shellState.sidebarLocked = !shellState.sidebarLocked;
        shellState.sidebarExpanded = shellState.sidebarLocked;
        shellState.showProfileMenu = false;
        saveState();
        applySidebarState();
    }

    function toggleMobileSidebar() {
        if (!shellState.isMobile) {
            return;
        }

        shellState.mobileSidebarOpen = !shellState.mobileSidebarOpen;
        shellState.showProfileMenu = false;
        saveState();
        applySidebarState();
    }

    function openMobileSidebar() {
        if (!shellState.isMobile) {
            return;
        }

        shellState.mobileSidebarOpen = true;
        shellState.showProfileMenu = false;
        saveState();
        applySidebarState();
    }

    function closeMobileSidebar() {
        shellState.mobileSidebarOpen = false;
        shellState.showProfileMenu = false;
        saveState();
        applySidebarState();
    }

    function handleBrandClick() {
        if (shellState.isMobile) {
            toggleMobileSidebar();
            return;
        }

        handleHomeClick(document.querySelector('[data-shell-action="home"]')?.dataset?.url || '/homepage');
    }

    function handleProfileAction(url) {
        shellState.showProfileMenu = false;
        shellState.mobileSidebarOpen = false;
        shellState.sidebarExpanded = false;
        shellState.sidebarLocked = false;
        saveState();
        applySidebarState();
        window.shellTopbar?.resetInteractionState?.();

        if (!url) {
            return;
        }

        if (url === '/logout') {
            const nameEl = document.querySelector('.shell-profile-name');
            const username = nameEl ? nameEl.textContent.trim() : 'User';

            runLogoutTransition(username, () => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.style.display = 'none';

                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = token;

                form.appendChild(csrfInput);
                document.body.appendChild(form);
                form.submit();
            });
            return;
        }

        navigateTo(url);
    }

    function toggleProfileMenu() {
        if (!getVisibleState()) {
            if (shellState.isMobile) {
                openMobileSidebar();
            } else {
                toggleDesktopSidebarLock();
            }

            return;
        }

        window.shellTopbar?.resetInteractionState?.();
        shellState.showProfileMenu = !shellState.showProfileMenu;
        saveState();
        applySidebarState();
    }

    function handleSidebarBodyClick(event) {
        const actionButton = event.target.closest('[data-shell-action]');
        if (actionButton) {
            return;
        }

        if (shellState.sidebarTourLocked) {
            return;
        }

        if (shellState.isMobile) {
            return;
        }

        toggleDesktopSidebarLock();
    }

    function handleOutsideClick(event) {
        if (shellState.sidebarTourLocked) {
            return;
        }

        if (!sidebarRoot.contains(event.target) && !sidebarToggleBtn?.contains(event.target)) {
            if (shellState.mobileSidebarOpen || shellState.sidebarExpanded || shellState.sidebarLocked || shellState.showProfileMenu) {
                closeSidebarPanels();
            }
        }

        if (sidebarProfileMenu && !sidebarProfileMenu.contains(event.target) && !sidebarProfileToggle?.contains(event.target)) {
            if (shellState.showProfileMenu) {
                shellState.showProfileMenu = false;
                saveState();
                applySidebarState();
            }
        }
    }

    function onResize() {
        const nextIsMobile = window.innerWidth <= MOBILE_BREAKPOINT;
        if (nextIsMobile === shellState.isMobile) {
            return;
        }

        shellState.isMobile = nextIsMobile;
        shellState.mobileSidebarOpen = false;

        if (!nextIsMobile) {
            shellState.sidebarLocked = false;
            shellState.sidebarExpanded = false;
        } else {
            shellState.sidebarExpanded = false;
        }

        shellState.showProfileMenu = false;
        saveState();
        applySidebarState();
    }


    function bindInteractiveListeners() {
        sidebarRoot.querySelectorAll('.shell-nav-item[data-shell-action="home"]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleHomeClick(button.dataset.url);
            });
        });

        sidebarRoot.querySelectorAll('.shell-nav-item[data-shell-action="module"]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleModuleSingleClick(button);
            });

            button.addEventListener('dblclick', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleModuleDoubleClick(button);
            });
        });

        sidebarRoot.querySelectorAll('.shell-expand-btn[data-shell-action="toggle-module"]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleModule(button.dataset.moduleId);
            });
        });

        sidebarRoot.querySelectorAll('.shell-submenu-toggle[data-shell-action="toggle-group"]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                toggleGroup(button.dataset.moduleId, button.dataset.groupId);
            });
        });

        sidebarRoot.querySelectorAll('.shell-submenu-item[data-shell-action="submenu"]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleSubMenuClick(button);
            });
        });

        sidebarRoot.querySelectorAll('.shell-profile-menu__item[data-shell-action="profile"]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleProfileAction(button.dataset.url);
            });
        });

        sidebarRoot.querySelectorAll('.shell-profile-menu__item[data-shell-action="logout"]').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                handleProfileAction(button.dataset.url);
            });
        });
    }

    sidebarRoot.addEventListener('click', handleSidebarBodyClick);
    sidebarRoot.addEventListener('mouseenter', () => {
        if (!shellState.isMobile && !shellState.sidebarLocked) {
            shellState.sidebarExpanded = true;
            applySidebarState();
        }
    });
    sidebarRoot.addEventListener('mouseleave', () => {
        if (!shellState.isMobile && !shellState.sidebarLocked) {
            shellState.sidebarExpanded = false;
            applySidebarState();
        }
    });

    sidebarToggleBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (shellState.isMobile) {
            toggleMobileSidebar();
        } else {
            toggleDesktopSidebarLock();
        }
    });

    sidebarBrandBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        handleBrandClick();
    });

    sidebarProfileToggle?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        toggleProfileMenu();
    });

    sidebarRoot.querySelector('.shell-nav')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    sidebarRoot.querySelector('.shell-sidebar__brand')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    sidebarRoot.querySelector('.shell-sidebar__profile')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    sidebarRoot.querySelector('.shell-mini-brand-row')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });

    sidebarBackdrop?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();

        if (shellState.sidebarTourLocked) {
            return;
        }

        closeSidebarPanels();
    });

    document.addEventListener('mousedown', handleOutsideClick);
    window.addEventListener('resize', onResize);

    if (!shellState.isMobile) {
        shellState.sidebarLocked = false;
        shellState.sidebarExpanded = false;
    }

    saveState();
    bindInteractiveListeners();
    syncOpenStateFromActive();
    applySidebarState();

    window.shellSidebar = {
        state: shellState,
        closeProfileMenu,
        toggleMobileSidebar,
        closeMobileSidebar,
        openMobileSidebar,
        toggleDesktopSidebarLock,
    };
})();
