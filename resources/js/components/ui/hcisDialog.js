import { h, render, ref, nextTick } from 'vue';
import HcisDialog from './HcisDialog.vue';
import HcisButton from './HcisButton.vue';

/**
 * Penanda dialog bawaan layanan ini (confirm/alert/prompt). Di HP dialognya
 * menjadi lembar bawah dan tombolnya dibentang selebar layar — lihat aturan
 * .hcis-dialog--sistem di HcisDialog.vue.
 */
const KELAS_SISTEM = 'hcis-dialog--sistem';

let activeContainer = null;
let currentResolve = null;

function cleanup() {
    if (activeContainer) {
        render(null, activeContainer);
        if (activeContainer.parentNode) {
            activeContainer.parentNode.removeChild(activeContainer);
        }
        activeContainer = null;
    }
    currentResolve = null;
}

/**
 * Imperative Dialog Service for HCIS Enterprise Suite
 */
export const hcisDialog = {
    /**
     * Shows a confirmation modal dialog and returns a Promise resolving to boolean (true for confirm, false for cancel).
     */
    confirm(options = {}) {
        const {
            title = 'Konfirmasi Tindakan',
            description = 'Apakah Anda yakin ingin melanjutkan tindakan ini?',
            /* SUBTITLE DI KEPALA DIALOG, terpisah dari keterangan di body.
               Sampai 30 Agu 2026 `description` dikirim ke KEDUANYA sekaligus:
               sebagai prop HcisDialog (yang menggambarnya sebagai subtitle di
               bawah judul) dan sebagai paragraf di body. Setiap dialog
               konfirmasi karena itu membacakan kalimat yang sama dua kali, dan
               tak ada satu pun pemanggil yang bisa membuang salah satunya
               tanpa membuang yang lain.

               Baku kosong: kepala dialog tak menampilkan subtitle kecuali
               pemanggil memang meminta kalimat yang BERBEDA di sana. */
            subtitle = '',
            kicker = '',
            badge = '',
            badgeVariant = '',
            badgeDot = false,
            badgePulse = false,
            badgeRadar = false,
            badgeGlow = false,
            sentiment = 'primary',
            size = 'sm',
            variant = 'default',
            pattern = 'none',
            icon = '',
            iconSize = 'sm',
            iconAnimation = '',
            iconVariant = 'boxed',
            confirmText = sentiment === 'danger' ? 'Ya, Lanjutkan' : 'Konfirmasi',
            cancelText = 'Batal',
            confirmVariant = sentiment === 'danger' ? 'danger' : 'primary',
            cancelVariant = 'secondary',
            closeOnOverlay = true,
            closeOnEsc = true,
            customClass = '',
        } = typeof options === 'string' ? { description: options } : options;

        return new Promise((resolve) => {
            cleanup();
            currentResolve = resolve;

            const isOpen = ref(true);
            const container = document.createElement('div');
            container.id = 'hcis-imperative-dialog-container';
            document.body.appendChild(container);
            activeContainer = container;

            const onConfirm = () => {
                isOpen.value = false;
                setTimeout(() => {
                    cleanup();
                    resolve(true);
                }, 220);
            };

            const onCancel = () => {
                isOpen.value = false;
                setTimeout(() => {
                    cleanup();
                    resolve(false);
                }, 220);
            };

            const vnode = h(
                HcisDialog,
                {
                    modelValue: isOpen.value,
                    'onUpdate:modelValue': (val) => {
                        isOpen.value = val;
                        if (!val) onCancel();
                    },
                    title,
                    /* Prop `description` milik HcisDialog ADALAH subtitle kepala.
                       Yang mengisinya kini `subtitle`, bukan keterangan body. */
                    description: subtitle,
                    kicker,
                    badge,
                    badgeVariant,
                    badgeDot,
                    badgePulse,
                    badgeRadar,
                    badgeGlow,
                    sentiment,
                    size,
                    variant,
                    pattern,
                    icon,
                    iconSize,
                    iconAnimation,
                    iconVariant,
                    closeOnOverlay,
                    closeOnEsc,
                    customClass: [KELAS_SISTEM, customClass].filter(Boolean).join(' '),
                    onClose: onCancel,
                },
                {
                    default: () => [
                        /* Keterangan kosong berarti TAK ADA paragraf, bukan
                           paragraf kosong — sejalan dengan hcisPrompt di bawah
                           yang sudah menjaganya begini. Pemanggil yang memang
                           tak punya apa-apa untuk ditambahkan di bawah judul
                           tak boleh dipaksa menyisakan bekasnya di DOM.
                           Yang TIDAK mengirim kunci ini tetap menerima kalimat
                           bakunya seperti semula. */
                        description
                            ? h('p', { class: 'text-sm text-slate-700 m-0 leading-relaxed' }, description)
                            : null,
                    ],
                    footer: () => [
                        h(HcisButton, {
                            label: cancelText,
                            variant: cancelVariant,
                            size: 'sm',
                            onClick: onCancel,
                        }),
                        h(HcisButton, {
                            label: confirmText,
                            variant: confirmVariant,
                            size: 'sm',
                            onClick: onConfirm,
                        }),
                    ],
                }
            );

            render(vnode, container);
        });
    },

    /**
     * Shows an alert modal dialog and returns a Promise resolving when acknowledged.
     */
    alert(options = {}) {
        const {
            title = 'Pemberitahuan Sistem',
            description = '',
            kicker = '',
            badge = '',
            badgeVariant = '',
            badgeDot = false,
            badgePulse = false,
            badgeRadar = false,
            badgeGlow = false,
            sentiment = 'info',
            size = 'sm',
            variant = 'default',
            pattern = 'none',
            icon = '',
            iconSize = 'sm',
            iconAnimation = '',
            iconVariant = 'boxed',
            buttonText = 'Mengerti',
            buttonVariant = 'primary',
            closeOnOverlay = true,
            closeOnEsc = true,
            customClass = '',
        } = typeof options === 'string' ? { description: options } : options;

        return new Promise((resolve) => {
            cleanup();
            currentResolve = resolve;

            const isOpen = ref(true);
            const container = document.createElement('div');
            container.id = 'hcis-imperative-dialog-container';
            document.body.appendChild(container);
            activeContainer = container;

            const onAcknowledge = () => {
                isOpen.value = false;
                setTimeout(() => {
                    cleanup();
                    resolve();
                }, 220);
            };

            const vnode = h(
                HcisDialog,
                {
                    modelValue: isOpen.value,
                    'onUpdate:modelValue': (val) => {
                        isOpen.value = val;
                        if (!val) onAcknowledge();
                    },
                    title,
                    description,
                    kicker,
                    badge,
                    badgeVariant,
                    badgeDot,
                    badgePulse,
                    badgeRadar,
                    badgeGlow,
                    sentiment,
                    size,
                    variant,
                    pattern,
                    icon,
                    iconSize,
                    iconAnimation,
                    iconVariant,
                    closeOnOverlay,
                    closeOnEsc,
                    customClass: [KELAS_SISTEM, customClass].filter(Boolean).join(' '),
                    onClose: onAcknowledge,
                },
                {
                    default: () => [
                        h('p', { class: 'text-sm text-slate-700 m-0 leading-relaxed' }, description),
                    ],
                    footer: () => [
                        h(HcisButton, {
                            label: buttonText,
                            variant: buttonVariant,
                            size: 'sm',
                            onClick: onAcknowledge,
                        }),
                    ],
                }
            );

            render(vnode, container);
        });
    },

    /**
     * Shows a prompt input dialog and returns a Promise resolving to the input string (or null if cancelled).
     */
    prompt(options = {}) {
        const {
            title = 'Masukkan Data',
            description = '',
            placeholder = 'Ketik di sini...',
            defaultValue = '',
            kicker = '',
            badge = '',
            badgeVariant = '',
            badgeDot = false,
            badgePulse = false,
            sentiment = 'primary',
            size = 'sm',
            variant = 'default',
            pattern = 'none',
            icon = '',
            iconSize = 'sm',
            iconAnimation = '',
            iconVariant = 'boxed',
            confirmText = 'Kirim',
            cancelText = 'Batal',
            confirmVariant = 'primary',
            cancelVariant = 'secondary',
            inputType = 'text',
        } = typeof options === 'string' ? { title: options } : options;

        return new Promise((resolve) => {
            cleanup();
            currentResolve = resolve;

            const isOpen = ref(true);
            const inputValue = ref(defaultValue);
            const container = document.createElement('div');
            container.id = 'hcis-imperative-dialog-container';
            document.body.appendChild(container);
            activeContainer = container;

            const onConfirm = () => {
                const val = inputValue.value;
                isOpen.value = false;
                setTimeout(() => {
                    cleanup();
                    resolve(val);
                }, 220);
            };

            const onCancel = () => {
                isOpen.value = false;
                setTimeout(() => {
                    cleanup();
                    resolve(null);
                }, 220);
            };

            const vnode = h(
                HcisDialog,
                {
                    modelValue: isOpen.value,
                    'onUpdate:modelValue': (val) => {
                        isOpen.value = val;
                        if (!val) onCancel();
                    },
                    title,
                    description,
                    kicker,
                    badge,
                    badgeVariant,
                    badgeDot,
                    badgePulse,
                    sentiment,
                    size,
                    variant,
                    pattern,
                    icon,
                    iconSize,
                    iconAnimation,
                    iconVariant,
                    customClass: KELAS_SISTEM,
                    onClose: onCancel,
                },
                {
                    default: () => [
                        description ? h('p', { class: 'text-sm text-slate-600 mb-2.5' }, description) : null,
                        inputType === 'textarea'
                            ? h('textarea', {
                                class: 'form-control form-control-sm w-100',
                                rows: 3,
                                placeholder,
                                value: inputValue.value,
                                onInput: (e) => {
                                    inputValue.value = e.target.value;
                                },
                            })
                            : h('input', {
                                class: 'form-control form-control-sm w-100',
                                type: inputType,
                                placeholder,
                                value: inputValue.value,
                                onInput: (e) => {
                                    inputValue.value = e.target.value;
                                },
                                onKeydown: (e) => {
                                    if (e.key === 'Enter') onConfirm();
                                },
                            }),
                    ],
                    footer: () => [
                        h(HcisButton, {
                            label: cancelText,
                            variant: cancelVariant,
                            size: 'sm',
                            onClick: onCancel,
                        }),
                        h(HcisButton, {
                            label: confirmText,
                            variant: confirmVariant,
                            size: 'sm',
                            onClick: onConfirm,
                        }),
                    ],
                }
            );

            render(vnode, container);

            nextTick(() => {
                const inputEl = container.querySelector('input, textarea');
                if (inputEl) inputEl.focus();
            });
        });
    },

    closeAll() {
        if (currentResolve) {
            currentResolve(false);
        }
        cleanup();
    },
};

export const hcisConfirm = (options) => hcisDialog.confirm(options);

export default hcisDialog;
