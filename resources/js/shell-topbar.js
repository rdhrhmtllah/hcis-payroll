/**
 * Shell Topbar - Blade interaction layer
 * Mirrors the Vue topbar behavior for Blade-rendered pages.
 */

(function initShellTopbar() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShellTopbar, { once: true });
        return;
    }

    const STORAGE_KEY = 'shellState';
    const topbarRoot = document.getElementById('topbarRoot');

    if (!topbarRoot) {
        return;
    }

    const messagesToggle = document.getElementById('messagesToggle');
    const messagesPopover = document.getElementById('messagesPopover');
    const messagesDot = document.getElementById('messagesDot');

    const notificationsToggle = document.getElementById('notificationsToggle');
    const notificationsPopover = document.getElementById('notificationsPopover');
    const notificationsBadge = document.getElementById('notificationsBadge');

    const helpToggle = document.getElementById('helpToggle');
    const helpPopover = document.getElementById('helpPopover');

    const currentTimeEl = document.getElementById('currentTime');
    const currentDateEl = document.getElementById('currentDate');
    const greetingLabelEl = document.getElementById('greetingLabel');
    const userModuleBtn = document.getElementById('userModuleBtn');
    const clearNotificationsBtn = document.getElementById('clearNotificationsBtn');

    const shellState = loadState();

    function loadState() {
        try {
            const stored = window.localStorage.getItem(STORAGE_KEY);
            if (!stored) {
                return {
                    showMessages: false,
                    showNotifications: false,
                    showHelp: false,
                    showProfileMenu: false,
                };
            }

            const parsed = JSON.parse(stored);
            return {
                showMessages: Boolean(parsed.showMessages),
                showNotifications: Boolean(parsed.showNotifications),
                showHelp: Boolean(parsed.showHelp),
                showProfileMenu: Boolean(parsed.showProfileMenu),
            };
        } catch (error) {
            return {
                showMessages: false,
                showNotifications: false,
                showHelp: false,
                showProfileMenu: false,
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
                    showMessages: shellState.showMessages,
                    showNotifications: shellState.showNotifications,
                    showHelp: shellState.showHelp,
                    showProfileMenu: shellState.showProfileMenu,
                }),
            );
        } catch (error) {
            console.warn('Failed to persist topbar state:', error);
        }
    }

    function updateClock() {
        const now = new Date();

        if (currentTimeEl) {
            currentTimeEl.textContent = now.toLocaleTimeString('id-ID', {
                hour: '2-digit',
                minute: '2-digit',
            });
        }

        if (currentDateEl) {
            currentDateEl.textContent = now.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'short',
                year: 'numeric',
            });
        }

        if (greetingLabelEl) {
            const hour = now.getHours();
            let greeting = 'SELAMAT PAGI';

            if (hour >= 11 && hour < 15) {
                greeting = 'SELAMAT SIANG';
            } else if (hour >= 15 && hour < 19) {
                greeting = 'SELAMAT SORE';
            } else if (hour >= 19) {
                greeting = 'SELAMAT MALAM';
            }

            greetingLabelEl.textContent = greeting;
        }
    }

    function resetInteractionState() {
        shellState.showMessages = false;
        shellState.showNotifications = false;
        shellState.showHelp = false;
        shellState.showProfileMenu = false;
        applyPopoverState();
        saveState();

        window.shellSidebar?.closeProfileMenu?.();
    }

    function setPopoverVisibility(popoverEl, visible) {
        if (!popoverEl) {
            return;
        }

        popoverEl.classList.toggle('is-visible', visible);
        popoverEl.setAttribute('aria-hidden', visible ? 'false' : 'true');
    }

    function applyPopoverState() {
        setPopoverVisibility(messagesPopover, shellState.showMessages);
        setPopoverVisibility(notificationsPopover, shellState.showNotifications);
        setPopoverVisibility(helpPopover, shellState.showHelp);

        if (messagesToggle) {
            messagesToggle.classList.toggle('is-active', shellState.showMessages);
            messagesToggle.classList.toggle('is-blue', shellState.showMessages);
        }

        if (notificationsToggle) {
            notificationsToggle.classList.toggle('is-active', shellState.showNotifications);
            notificationsToggle.classList.toggle('is-red', shellState.showNotifications);
        }

        if (helpToggle) {
            helpToggle.classList.toggle('is-active', shellState.showHelp);
            helpToggle.classList.toggle('is-dark', shellState.showHelp);
        }

        if (messagesDot) {
            messagesDot.style.display = shellState.showMessages ? 'none' : '';
        }

        if (notificationsBadge) {
            notificationsBadge.style.display = shellState.showNotifications ? 'none' : '';
        }
    }

    function togglePopover(key) {
        const nextValue = !shellState[key];
        resetInteractionState();
        shellState[key] = nextValue;
        applyPopoverState();
        saveState();
    }

    function goToActiveModule() {
        const url = userModuleBtn?.dataset?.url || '/homepage';
        window.location.assign(url);
    }

    async function markAllAsRead() {
        resetInteractionState();

        try {
            const response = await fetch('/homepage/notifications/read-all', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}`);
            }

            window.location.reload();
        } catch (error) {
            console.error('Failed to mark notifications as read:', error);
        }
    }

    function handleOutsideClick(event) {
        if (!topbarRoot.contains(event.target)) {
            resetInteractionState();
        }
    }

    function onKeyDown(event) {
        if (event.key === 'Escape') {
            resetInteractionState();
        }
    }

    updateClock();
    applyPopoverState();
    window.setInterval(updateClock, 1000);

    messagesToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        togglePopover('showMessages');
    });

    notificationsToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        togglePopover('showNotifications');
    });

    helpToggle?.addEventListener('click', (event) => {
        event.stopPropagation();
        togglePopover('showHelp');
    });

    // Help PIC button mailto with loading animation
    const helpPicBtns = document.querySelectorAll('.help-pic-btn');
    helpPicBtns.forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const email = btn.getAttribute('data-email');
            if (!email) return;

            btn.classList.add('is-loading');

            const iconEl = btn.querySelector('.shell-help-icon i');
            if (iconEl) {
                // Change icon class to spinner/arrow-repeat and spin
                iconEl.className = 'bi bi-arrow-repeat spin-animation';
            }

            // Trigger mailto client
            window.location.href = `mailto:${email}`;

            // Restore icon class after 1.5s
            setTimeout(() => {
                btn.classList.remove('is-loading');
                if (iconEl) {
                    const originalClass = iconEl.getAttribute('data-original-class') || 'bi bi-headset';
                    iconEl.className = originalClass;
                }
            }, 1500);
        });
    });

    clearNotificationsBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        markAllAsRead();
    });

    userModuleBtn?.addEventListener('click', (event) => {
        event.preventDefault();
        goToActiveModule();
    });

    document.addEventListener('mousedown', handleOutsideClick);
    document.addEventListener('keydown', onKeyDown);

    window.shellTopbar = {
        state: shellState,
        togglePopover,
        resetInteractionState,
        updateClock,
    };
})();
