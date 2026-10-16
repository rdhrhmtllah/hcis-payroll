import { h, render } from 'vue';
import HcisIcon from './HcisIcon.vue';

let toastQueue = [];
let toastIdCounter = 0;
let containerEl = null;

const injectToastStyles = () => {
    if (typeof document === 'undefined' || document.getElementById('hcis-toast-styles')) return;
    const styleEl = document.createElement('style');
    styleEl.id = 'hcis-toast-styles';
    styleEl.textContent = `
#hcis-toast-container {
    position: fixed;
    top: 1.5rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 9999;
    pointer-events: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    max-width: 92vw;
    width: 32rem;
}

#hcis-toast-container.is-top-right {
    left: auto;
    right: 1.5rem;
    transform: none;
    align-items: flex-end;
}

#hcis-toast-container.is-bottom-right {
    top: auto;
    bottom: 1.5rem;
    left: auto;
    right: 1.5rem;
    transform: none;
    align-items: flex-end;
}

.hcis-toast-stack {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    width: 100%;
}

/* ── Toast Primitive (Luxury Crystal Glassmorphism) ───────────────────── */
.hcis-toast {
    position: relative;
    pointer-events: auto;
    display: flex;
    align-items: center;
    gap: 0.875rem;
    width: 100%;
    padding: 0.9375rem 1.125rem;
    border-radius: var(--hcis-radius-lg, 10px);
    font-family: var(--hcis-font-body, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif);
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.88) 100%);
    backdrop-filter: blur(24px) saturate(180%);
    -webkit-backdrop-filter: blur(24px) saturate(180%);
    color: #1e293b;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 4px 16px -2px rgba(15, 23, 42, 0.05),
        0 12px 28px -4px rgba(15, 23, 42, 0.08),
        0 24px 48px -8px rgba(15, 23, 42, 0.06);
    will-change: transform, box-shadow, border-color;
    transition: transform 250ms cubic-bezier(0.16, 1, 0.3, 1),
                box-shadow 250ms cubic-bezier(0.16, 1, 0.3, 1),
                border-color 200ms ease,
                background-color 200ms ease,
                opacity 200ms ease;
    animation: hcis-toast-slide-down 320ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* Micro-Pattern Overlay on Toast Surface */
.hcis-toast::before {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    border-radius: inherit;
    background-image: radial-gradient(rgba(148, 163, 184, 0.16) 1px, transparent 1px);
    background-size: 10px 10px;
    opacity: 0.85;
}

.hcis-toast:hover {
    transform: translateY(-3px) scale(1.01);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.9) inset,
        0 8px 24px -4px rgba(15, 23, 42, 0.08),
        0 20px 44px -8px rgba(15, 23, 42, 0.12);
}

@keyframes hcis-toast-slide-down {
    0% {
        opacity: 0;
        transform: translateY(-24px) scale(0.92);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

/* ── Sentiments & Ambient Halo Glow Matrix ────────────────────────────── */
.hcis-toast--success {
    border-color: rgba(167, 243, 208, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 4px 16px -2px rgba(16, 185, 129, 0.14),
        0 12px 28px -4px rgba(16, 185, 129, 0.10),
        0 24px 48px -8px rgba(16, 185, 129, 0.06);
}
.hcis-toast--success:hover {
    border-color: #10b981;
}
.hcis-toast--success .hcis-toast__icon-badge {
    background: linear-gradient(135deg, #34d399 0%, #059669 100%);
}
.hcis-toast--success .hcis-toast__progress-bar {
    background: linear-gradient(90deg, #34d399, #059669);
}

.hcis-toast--error,
.hcis-toast--danger {
    border-color: rgba(254, 202, 202, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 4px 16px -2px rgba(239, 68, 68, 0.14),
        0 12px 28px -4px rgba(239, 68, 68, 0.10),
        0 24px 48px -8px rgba(239, 68, 68, 0.06);
}
.hcis-toast--error:hover,
.hcis-toast--danger:hover {
    border-color: #ef4444;
}
.hcis-toast--error .hcis-toast__icon-badge,
.hcis-toast--danger .hcis-toast__icon-badge {
    background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
}
.hcis-toast--error .hcis-toast__progress-bar,
.hcis-toast--danger .hcis-toast__progress-bar {
    background: linear-gradient(90deg, #f87171, #dc2626);
}

.hcis-toast--warning {
    border-color: rgba(253, 230, 138, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 4px 16px -2px rgba(245, 158, 11, 0.14),
        0 12px 28px -4px rgba(245, 158, 11, 0.10);
}
.hcis-toast--warning:hover {
    border-color: #f59e0b;
}
.hcis-toast--warning .hcis-toast__icon-badge {
    background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%);
}
.hcis-toast--warning .hcis-toast__progress-bar {
    background: linear-gradient(90deg, #fbbf24, #d97706);
}

.hcis-toast--info {
    border-color: rgba(199, 210, 254, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 4px 16px -2px rgba(79, 70, 229, 0.14),
        0 12px 28px -4px rgba(79, 70, 229, 0.10);
}
.hcis-toast--info:hover {
    border-color: #4f46e5;
}
.hcis-toast--info .hcis-toast__icon-badge {
    background: linear-gradient(135deg, #818cf8 0%, #4f46e5 100%);
}
.hcis-toast--info .hcis-toast__progress-bar {
    background: linear-gradient(90deg, #818cf8, #4f46e5);
}

.hcis-toast--purple {
    border-color: rgba(233, 213, 255, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 4px 16px -2px rgba(168, 85, 247, 0.14),
        0 12px 28px -4px rgba(168, 85, 247, 0.10);
}
.hcis-toast--purple .hcis-toast__icon-badge {
    background: linear-gradient(135deg, #c084fc 0%, #7e22ce 100%);
}
.hcis-toast--purple .hcis-toast__progress-bar {
    background: linear-gradient(90deg, #c084fc, #7e22ce);
}

.hcis-toast--teal {
    border-color: rgba(153, 246, 228, 0.9);
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.8) inset,
        0 4px 16px -2px rgba(20, 184, 166, 0.14),
        0 12px 28px -4px rgba(20, 184, 166, 0.10);
}
.hcis-toast--teal .hcis-toast__icon-badge {
    background: linear-gradient(135deg, #2dd4bf 0%, #0f766e 100%);
}
.hcis-toast--teal .hcis-toast__progress-bar {
    background: linear-gradient(90deg, #2dd4bf, #0f766e);
}

/* ── Dark Theme (.hcis-toast--dark) ───────────────────────────────────── */
.hcis-toast--dark {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%) !important;
    border-color: rgba(255, 255, 255, 0.12) !important;
    color: #f8fafc !important;
    box-shadow:
        0 0 0 1px rgba(255, 255, 255, 0.1) inset,
        0 8px 24px -4px rgba(0, 0, 0, 0.4),
        0 16px 36px -8px rgba(0, 0, 0, 0.3) !important;
}

.hcis-toast--dark::before {
    background-image: radial-gradient(rgba(255, 255, 255, 0.08) 1px, transparent 1px);
}

.hcis-toast--dark .hcis-toast__title { color: #f8fafc !important; }
.hcis-toast--dark .hcis-toast__message { color: #cbd5e1 !important; }
.hcis-toast--dark .hcis-toast__close {
    color: #94a3b8 !important;
}
.hcis-toast--dark .hcis-toast__close:hover {
    background: rgba(255, 255, 255, 0.12) !important;
    color: #ffffff !important;
}
.hcis-toast--dark .hcis-toast__action-btn {
    border-color: rgba(255, 255, 255, 0.2) !important;
    background: rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}
.hcis-toast--dark .hcis-toast__action-btn:hover {
    border-color: rgba(255, 255, 255, 0.35) !important;
    background: rgba(255, 255, 255, 0.18) !important;
}

/* ── 3D Jewel Icon Badge ──────────────────────────────────────────────── */
.hcis-toast__icon-badge {
    position: relative;
    z-index: 1;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    flex-shrink: 0;
    color: #ffffff;
    line-height: 1;
    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.15),
        inset 0 1px 0 rgba(255, 255, 255, 0.4);
    transition: transform 220ms cubic-bezier(0.34, 1.56, 0.64, 1);
}

.hcis-toast__icon-badge svg,
.hcis-toast__icon-badge .hcis-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    line-height: 1;
}

.hcis-toast:hover .hcis-toast__icon-badge {
    transform: scale(1.08) rotate(3deg);
}

/* ── Body Text & Typography ───────────────────────────────────────────── */
.hcis-toast__body {
    position: relative;
    z-index: 1;
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    align-self: center;
}

.hcis-toast__title {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.3;
    color: #0f172a;
}

.hcis-toast__message {
    font-size: 0.8125rem;
    font-weight: 400;
    line-height: 1.5;
    word-break: break-word;
    color: #334155;
}

/* ── Action Button ────────────────────────────────────────────────────── */
.hcis-toast__action-btn {
    position: relative;
    z-index: 1;
    appearance: none;
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: #ffffff;
    color: #0f172a;
    padding: 0.3125rem 0.75rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    transition: all 180ms ease;
    margin-left: auto;
    flex-shrink: 0;
    align-self: center;
}

.hcis-toast__action-btn:hover {
    background: #f8fafc;
    border-color: #94a3b8;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08);
}

/* ── Close Button ─────────────────────────────────────────────────────── */
.hcis-toast__close {
    position: relative;
    z-index: 1;
    appearance: none;
    border: none;
    background: transparent;
    color: #64748b;
    padding: 0.25rem;
    border-radius: 6px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    align-self: flex-start;
    margin-left: auto;
    transition: color 150ms ease, background 150ms ease, transform 150ms ease;
}

.hcis-toast__close:hover {
    color: #ef4444;
    background: rgba(239, 68, 68, 0.08);
    transform: rotate(90deg);
}

/* ── Progress Bar ─────────────────────────────────────────────────────── */
.hcis-toast__progress-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    width: 100%;
    transform-origin: left center;
    z-index: 2;
}

@keyframes hcis-toast-progress {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}

/* ── Compact Modifier for Toast (Streamlined Squircle) ───────────────── */
.hcis-toast--compact {
    padding: 0.4375rem 0.8125rem !important;
    gap: 0.5625rem !important;
    width: auto !important;
    max-width: 28rem !important;
    border-radius: 8px !important;
}

.hcis-toast--compact .hcis-toast__icon-badge {
    width: 1.5rem !important;
    height: 1.5rem !important;
    border-radius: 6px !important;
}

.hcis-toast--compact .hcis-toast__body {
    gap: 0 !important;
}

.hcis-toast--compact .hcis-toast__title {
    font-size: 0.8125rem !important;
    display: inline !important;
    margin-right: 0.375rem !important;
}

.hcis-toast--compact .hcis-toast__message {
    font-size: 0.8125rem !important;
    display: inline !important;
}

.hcis-toast--compact .hcis-toast__action-btn {
    padding: 0.1875rem 0.5625rem !important;
    font-size: 0.6875rem !important;
    border-radius: 5px !important;
}

.hcis-toast--compact .hcis-toast__close {
    padding: 0.125rem !important;
    align-self: center !important;
}

/* Rounded Modifiers for Toast */
.hcis-toast--rounded-none { border-radius: 0 !important; }
.hcis-toast--rounded-none .hcis-toast__icon-badge { border-radius: 0 !important; }
.hcis-toast--rounded-sm { border-radius: 6px !important; }
.hcis-toast--rounded-sm .hcis-toast__icon-badge { border-radius: 5px !important; }
.hcis-toast--rounded-md { border-radius: 10px !important; }
.hcis-toast--rounded-md .hcis-toast__icon-badge { border-radius: 8px !important; }
.hcis-toast--rounded-lg { border-radius: 14px !important; }
.hcis-toast--rounded-lg .hcis-toast__icon-badge { border-radius: 10px !important; }
.hcis-toast--rounded-full,
.hcis-toast--pill {
    border-radius: 9999px !important;
    padding-left: 0.625rem !important;
    padding-right: 0.875rem !important;
}

.hcis-toast--rounded-full .hcis-toast__icon-badge,
.hcis-toast--pill .hcis-toast__icon-badge {
    border-radius: 9999px !important;
}
    `;
    document.head.appendChild(styleEl);
};

const getToastIcon = (type) => {
    switch (type) {
        case 'success':
            return 'check-circle-fill';
        case 'error':
        case 'danger':
            return 'x-octagon-fill';
        case 'warning':
            return 'exclamation-triangle-fill';
        case 'purple':
            return 'stars';
        case 'teal':
            return 'shield-check';
        case 'info':
        default:
            return 'info-circle-fill';
    }
};

const renderToasts = (position = 'top-center') => {
    if (typeof document === 'undefined') return;

    injectToastStyles();

    if (!containerEl) {
        containerEl = document.createElement('div');
        containerEl.id = 'hcis-toast-container';
        document.body.appendChild(containerEl);
    }
    
    containerEl.className = '';
    if (position === 'top-right') containerEl.classList.add('is-top-right');
    else if (position === 'bottom-right') containerEl.classList.add('is-bottom-right');

    const vnode = h(
        'div',
        { class: 'hcis-toast-stack' },
        toastQueue.map((toast, idx) =>
            h(
                'div',
                {
                    key: toast.id,
                    class: [
                        'hcis-toast',
                        `hcis-toast--${toast.type}`,
                        toast.rounded ? `hcis-toast--rounded-${toast.rounded}` : '',
                        toast.compact ? 'hcis-toast--compact' : '',
                        toast.pill ? 'hcis-toast--pill' : '',
                        toast.theme === 'dark' ? 'hcis-toast--dark' : '',
                        toast.customClass || '',
                    ],
                    style: { zIndex: idx },
                    onMouseenter: () => pauseToast(toast),
                    onMouseleave: () => resumeToast(toast),
                },
                [
                    // 3D Jewel Icon Badge
                    h('div', { class: 'hcis-toast__icon-badge' }, [
                        h(HcisIcon, { name: getToastIcon(toast.type), size: toast.compact ? 'xs' : 'sm' }),
                    ]),

                    // Body Text & Title
                    h('div', { class: 'hcis-toast__body' }, [
                        toast.title ? h('h5', { class: 'hcis-toast__title' }, toast.compact ? `${toast.title}: ` : toast.title) : null,
                        h('span', { class: 'hcis-toast__message' }, toast.message),
                    ]),

                    // Action Button (Optional)
                    toast.actionText
                        ? h(
                              'button',
                              {
                                  type: 'button',
                                  class: 'hcis-toast__action-btn',
                                  onClick: () => {
                                      if (typeof toast.onAction === 'function') toast.onAction();
                                      removeToast(toast.id);
                                  },
                              },
                              toast.actionText
                          )
                        : null,

                    // Close Button
                    toast.showClose
                        ? h(
                              'button',
                              {
                                  type: 'button',
                                  class: 'hcis-toast__close',
                                  'aria-label': 'Tutup notifikasi',
                                  onClick: () => removeToast(toast.id),
                              },
                              h(HcisIcon, { name: 'x-lg', size: '2xs' })
                          )
                        : null,

                    // Progress Bar Line
                    h('div', {
                        class: 'hcis-toast__progress-bar',
                        style: {
                            animation: toast.duration > 0 ? `hcis-toast-progress ${toast.duration}ms linear forwards` : 'none',
                        },
                    }),
                ]
            )
        )
    );

    render(vnode, containerEl);
};

const removeToast = (id) => {
    const idx = toastQueue.findIndex((t) => t.id === id);
    if (idx !== -1) {
        const toast = toastQueue[idx];
        if (toast.timer) clearTimeout(toast.timer);
        toastQueue.splice(idx, 1);
        renderToasts();
    }
};

const pauseToast = (toast) => {
    if (toast.timer) {
        clearTimeout(toast.timer);
        toast.timer = null;
    }
};

const resumeToast = (toast) => {
    if (toast.duration > 0 && !toast.timer) {
        toast.timer = setTimeout(() => {
            removeToast(toast.id);
        }, toast.duration);
    }
};

export const hcisMessage = (options) => {
    if (typeof window === 'undefined') return;

    let config = {};
    if (typeof options === 'string' || typeof options === 'number') {
        config = { message: String(options), type: 'info' };
    } else if (typeof options === 'object' && options !== null) {
        config = { ...options };
    }

    const toastId = ++toastIdCounter;
    const type = config.type || 'info';
    const duration = config.duration !== undefined ? config.duration : 3600;
    const showClose = config.showClose !== undefined ? config.showClose : true;
    const compact = Boolean(config.compact || config.size === 'compact' || config.size === 'sm');
    const pill = Boolean(config.pill);

    const toastItem = {
        id: toastId,
        message: config.message || '',
        title: config.title || '',
        type,
        theme: config.theme || 'light',
        compact,
        pill,
        rounded: config.rounded || 'md',
        duration,
        showClose,
        actionText: config.actionText || '',
        onAction: config.onAction || null,
        customClass: config.customClass || '',
        timer: null,
    };

    if (duration > 0) {
        toastItem.timer = setTimeout(() => {
            removeToast(toastId);
        }, duration);
    }

    toastQueue.push(toastItem);

    if (toastQueue.length > 5) {
        const oldToast = toastQueue.shift();
        if (oldToast && oldToast.timer) clearTimeout(oldToast.timer);
    }

    renderToasts(config.position);

    return {
        close: () => removeToast(toastId),
    };
};

hcisMessage.success = (msg, titleOrOptions) => {
    if (typeof titleOrOptions === 'object' && titleOrOptions !== null) {
        return hcisMessage({ ...titleOrOptions, message: msg, type: 'success' });
    }
    return hcisMessage({ message: msg, title: titleOrOptions, type: 'success' });
};

hcisMessage.error = (msg, titleOrOptions) => {
    if (typeof titleOrOptions === 'object' && titleOrOptions !== null) {
        return hcisMessage({ ...titleOrOptions, message: msg, type: 'error' });
    }
    return hcisMessage({ message: msg, title: titleOrOptions, type: 'error' });
};

hcisMessage.warning = (msg, titleOrOptions) => {
    if (typeof titleOrOptions === 'object' && titleOrOptions !== null) {
        return hcisMessage({ ...titleOrOptions, message: msg, type: 'warning' });
    }
    return hcisMessage({ message: msg, title: titleOrOptions, type: 'warning' });
};

hcisMessage.info = (msg, titleOrOptions) => {
    if (typeof titleOrOptions === 'object' && titleOrOptions !== null) {
        return hcisMessage({ ...titleOrOptions, message: msg, type: 'info' });
    }
    return hcisMessage({ message: msg, title: titleOrOptions, type: 'info' });
};

hcisMessage.purple = (msg, titleOrOptions) => {
    if (typeof titleOrOptions === 'object' && titleOrOptions !== null) {
        return hcisMessage({ ...titleOrOptions, message: msg, type: 'purple' });
    }
    return hcisMessage({ message: msg, title: titleOrOptions, type: 'purple' });
};

hcisMessage.teal = (msg, titleOrOptions) => {
    if (typeof titleOrOptions === 'object' && titleOrOptions !== null) {
        return hcisMessage({ ...titleOrOptions, message: msg, type: 'teal' });
    }
    return hcisMessage({ message: msg, title: titleOrOptions, type: 'teal' });
};

hcisMessage.compact = (msg, typeOrOptions = 'info') => {
    if (typeof typeOrOptions === 'object' && typeOrOptions !== null) {
        return hcisMessage({ ...typeOrOptions, message: msg, compact: true });
    }
    return hcisMessage({ message: msg, type: typeOrOptions, compact: true });
};

hcisMessage.pill = (msg, typeOrOptions = 'info') => {
    if (typeof typeOrOptions === 'object' && typeOrOptions !== null) {
        return hcisMessage({ ...typeOrOptions, message: msg, compact: true, pill: true });
    }
    return hcisMessage({ message: msg, type: typeOrOptions, compact: true, pill: true });
};

hcisMessage.closeAll = () => {
    toastQueue.forEach((t) => {
        if (t.timer) clearTimeout(t.timer);
    });
    toastQueue = [];
    renderToasts();
};

export default hcisMessage;
