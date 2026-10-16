<template>
    <span
        class="hcis-badge"
        :class="[
            `hcis-badge--${computedVariant}`,
            `hcis-badge--${size}`,
            appearance !== 'subtle' ? `hcis-badge--${appearance}` : null,
            {
                'is-pulsing': computedPulse,
                'hcis-badge--removable': removable,
            },
        ]"
    >
        <!-- Pulsing / Static Dot -->
        <span v-if="dot || computedPulse" class="hcis-badge__dot" aria-hidden="true">
            <span v-if="computedPulse" class="hcis-badge__dot-pulse" />
        </span>

        <!-- Optional Icon with Motion -->
        <HcisIcon
            v-if="computedIcon"
            :name="computedIcon"
            :animation="computedIconAnimation"
            size="2xs"
            class="me-1 hcis-badge__icon"
        />

        <span class="hcis-badge__text">
            <slot>{{ computedLabel }}</slot>
        </span>

        <!-- Optional Remove / Close Button -->
        <button
            v-if="removable"
            type="button"
            class="hcis-badge__remove-btn"
            aria-label="Hapus tag"
            @click.stop="$emit('remove', $event)"
        >
            <HcisIcon name="x" size="2xs" />
        </button>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import HcisIcon from './HcisIcon.vue';

defineOptions({ name: 'HcisBadge' });

defineEmits(['remove']);

const STATUS_MAP = {
    // Approved & Active
    APPROVED: { label: 'Disetujui', variant: 'success', icon: 'check-circle-fill' },
    SETUJU: { label: 'Disetujui', variant: 'success', icon: 'check-circle-fill' },
    DISETUJUI: { label: 'Disetujui', variant: 'success', icon: 'check-circle-fill' },
    COMPLETED: { label: 'Selesai', variant: 'success', icon: 'check-all' },
    ACTIVE: { label: 'Aktif', variant: 'success', icon: 'record-fill' },
    AKTIF: { label: 'Aktif', variant: 'success', icon: 'record-fill' },
    BERLAKU: { label: 'Berlaku', variant: 'success', icon: 'patch-check-fill' },
    CHECKED_IN: { label: 'Sedang di Area', variant: 'success', icon: 'record-fill', pulse: true },
    TERJADWAL: { label: 'Terjadwal', variant: 'success', icon: 'calendar-check' },
    SCHEDULED: { label: 'Terjadwal', variant: 'success', icon: 'calendar-check' },
    PASSED: { label: 'Lolos', variant: 'success', icon: 'award-fill' },
    VERIFIED: { label: 'Terverifikasi', variant: 'success', icon: 'patch-check-fill' },
    SUCCESS: { label: 'Berhasil', variant: 'success', icon: 'check-circle-fill' },
    SYNCED: { label: 'Tersinkron', variant: 'success', icon: 'cloud-check-fill' },

    // Pending & Processing
    PENDING: { label: 'Menunggu Persetujuan', variant: 'warning', icon: 'hourglass-split' },
    PENDING_MANAGER: { label: 'Menunggu Manajer', variant: 'warning', icon: 'hourglass-split' },
    PENDING_HR: { label: 'Menunggu HR Admin', variant: 'warning', icon: 'clock-history' },
    IN_REVIEW: { label: 'Dalam Peninjauan', variant: 'warning', icon: 'search' },
    WAITING: { label: 'Menunggu Antrean', variant: 'warning', icon: 'hourglass-split' },
    IN_PROGRESS: { label: 'Sedang Diproses', variant: 'brand', icon: 'gear-wide-connected' },
    PROCESSING: { label: 'Memproses Data', variant: 'brand', icon: 'gear-fill' },
    SYNCING: { label: 'Menyinkronkan', variant: 'brand', icon: 'arrow-repeat' },

    // Danger & Rejected
    REJECTED: { label: 'Ditolak', variant: 'danger', icon: 'x-circle-fill' },
    TOLAK: { label: 'Ditolak', variant: 'danger', icon: 'x-circle-fill' },
    CANCELLED: { label: 'Dibatalkan', variant: 'danger', icon: 'slash-circle-fill' },
    EXPIRED: { label: 'Kadaluarsa', variant: 'danger', icon: 'exclamation-circle-fill' },
    FAILED: { label: 'Gagal', variant: 'danger', icon: 'exclamation-triangle-fill' },
    SUSPENDED: { label: 'Ditangguhkan', variant: 'danger', icon: 'pause-circle-fill' },

    // Neutral & Draft
    DRAFT: { label: 'Draf', variant: 'neutral', icon: 'file-earmark-text' },
    CHECKED_OUT: { label: 'Selesai (Out)', variant: 'neutral', icon: 'check-all' },
    KELUAR_TAK_TERCATAT: { label: 'Keluar Tak Tercatat', variant: 'warning', icon: 'question-circle' },
    INACTIVE: { label: 'Nonaktif', variant: 'neutral', icon: 'dash-circle' },
    ARCHIVED: { label: 'Diarsipkan', variant: 'neutral', icon: 'archive' },
    CLOSED: { label: 'Ditutup', variant: 'neutral', icon: 'lock-fill' },
};

const props = defineProps({
    status: { type: String, default: '' },
    label: { type: String, default: '' },
    variant: { type: String, default: '' },
    appearance: {
        type: String,
        default: 'subtle',
        validator: (v) => ['subtle', 'solid', 'outline', 'glass'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['xs', 'sm', 'md', 'lg'].includes(v),
    },
    icon: { type: [String, Boolean], default: '' },
    iconAnimation: { type: String, default: '' },
    dot: { type: Boolean, default: false },
    pulse: { type: Boolean, default: false },
    removable: { type: Boolean, default: false },
});

const matchedConfig = computed(() => {
    if (!props.status) return null;
    const key = props.status.toUpperCase().trim();
    return STATUS_MAP[key] || null;
});

const computedLabel = computed(() => {
    if (props.label) return props.label;
    if (matchedConfig.value?.label) return matchedConfig.value.label;
    return props.status || '';
});

const computedVariant = computed(() => {
    if (props.variant) return props.variant;
    if (matchedConfig.value?.variant) return matchedConfig.value.variant;
    return 'neutral';
});

const computedIcon = computed(() => {
    if (props.icon === false || props.icon === 'false') return null;
    if (typeof props.icon === 'string' && props.icon) return props.icon;
    if (matchedConfig.value?.icon) return matchedConfig.value.icon;
    return null;
});

const computedIconAnimation = computed(() => {
    if (props.iconAnimation) return props.iconAnimation;
    if (matchedConfig.value?.iconAnimation) return matchedConfig.value.iconAnimation;
    return '';
});

const computedPulse = computed(() => {
    if (props.pulse) return true;
    return Boolean(matchedConfig.value?.pulse);
});
</script>

<style scoped>
.hcis-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.hcis-badge__icon {
    flex-shrink: 0;
}

.hcis-badge__dot {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border-radius: 9999px;
}

.hcis-badge.is-pulsing .hcis-badge__dot-pulse {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 100%;
    height: 100%;
    transform: translate3d(-50%, -50%, 0);
    border-radius: 9999px;
    background: currentColor;
    pointer-events: none;
    will-change: transform, opacity;
    animation: hcis-radar-ping 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes hcis-radar-ping {
    0% {
        transform: translate3d(-50%, -50%, 0) scale(1);
        opacity: 0.8;
    }
    75%, 100% {
        transform: translate3d(-50%, -50%, 0) scale(2.4);
        opacity: 0;
    }
}

.hcis-badge__remove-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    position: relative;
    box-sizing: border-box;
    border: 1px solid rgb(0 0 0 / 0.08);
    background: rgb(0 0 0 / 0.05);
    padding: 0;
    margin-left: 0.35rem;
    margin-right: -0.25rem;
    width: 1.125rem;
    height: 1.125rem;
    color: currentColor;
    opacity: 0.85;
    cursor: pointer;
    border-radius: 9999px;
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.03), inset 0 1px 0 rgba(255, 255, 255, 0.7);
    transition: background 180ms var(--hcis-ease),
                border-color 180ms var(--hcis-ease),
                color 180ms var(--hcis-ease),
                opacity 180ms var(--hcis-ease),
                transform 220ms cubic-bezier(0.34, 1.56, 0.64, 1),
                box-shadow 180ms var(--hcis-ease);
    -webkit-tap-highlight-color: transparent;
}

.hcis-badge--solid .hcis-badge__remove-btn,
.hcis-badge--obsidian .hcis-badge__remove-btn {
    background: rgb(255 255 255 / 0.2);
    border-color: rgb(255 255 255 / 0.3);
    color: var(--hcis-white);
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.15), inset 0 1px 0 rgba(255, 255, 255, 0.4);
    opacity: 0.92;
}

.hcis-badge--glass .hcis-badge__remove-btn {
    background: rgb(255 255 255 / 0.65);
    border-color: rgb(255 255 255 / 0.8);
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.06), inset 0 1px 0 var(--hcis-white);
}

.hcis-badge__remove-btn:hover {
    background: rgb(239 68 68 / 0.14);
    border-color: rgb(239 68 68 / 0.35);
    color: var(--hcis-danger-600, var(--hcis-red-600));
    opacity: 1;
    transform: scale(1.18) rotate(90deg);
    box-shadow: 0 2px 6px -1px rgb(239 68 68 / 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.85);
}

.hcis-badge--solid .hcis-badge__remove-btn:hover,
.hcis-badge--obsidian .hcis-badge__remove-btn:hover {
    background: rgb(239 68 68 / 0.9);
    border-color: rgb(255 255 255 / 0.4);
    color: var(--hcis-white);
    box-shadow: 0 2px 6px -1px rgb(0 0 0 / 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.5);
}

.hcis-badge__remove-btn:active {
    transform: scale(0.92) rotate(90deg);
}

.hcis-badge__remove-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 2px currentColor;
}
</style>
