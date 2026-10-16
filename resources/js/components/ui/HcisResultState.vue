<template>
    <div
        v-if="visible"
        class="hcis-result-state"
        :class="[
            `hcis-result-state--${status}`,
            `hcis-result-state--${variant}`,
            `hcis-result-state--size-${size}`,
            `hcis-result-state--layout-${layout}`,
            {
                'is-animated': animated,
                'is-looping': loopAnimation,
                'has-description': Boolean(description || $slots.description),
                'has-kicker': Boolean(kicker || $slots.kicker),
            }
        ]"
    >
        <!-- Sentiment-Adaptive Ambient Glass Aurora & Signature Patterns -->
        <div v-if="variant === 'glass' || variant === 'card' || showAura" class="hcis-result-state__aurora" aria-hidden="true" />
        <div v-if="resolvedPattern === 'grid'" class="hcis-pattern-grid hcis-pattern-grid--diagonal" aria-hidden="true" />
        <div v-else-if="resolvedPattern === 'dots'" class="hcis-pattern-dots" aria-hidden="true" />

        <!-- Smooth State Swap Transition Wrapper -->
        <Transition appear name="hcis-state-swap" mode="out-in">
            <div :key="stateKey" class="hcis-result-state__body">
                <!-- Status Icon Container with Multi-Layered Ripple Halo Rings -->
                <div class="hcis-result-state__icon-container">
                    <div v-if="loopAnimation && status !== 'loading'" class="hcis-result-state__icon-halo hcis-halo-ring-1" />
                    <div v-if="loopAnimation && status !== 'loading'" class="hcis-result-state__icon-halo hcis-halo-ring-2" />

                    <div class="hcis-result-state__icon-ring">
                        <!-- Jewel Top Bevel Glass Reflection Sheen -->
                        <div class="hcis-result-state__icon-glass-reflect" aria-hidden="true" />

                        <!-- Loading State Dual-Ring Laser Spinner -->
                        <div v-if="status === 'loading'" class="hcis-result-state__spinner">
                            <svg viewBox="0 0 50 50" class="hcis-spinner-svg">
                                <circle
                                    cx="25"
                                    cy="25"
                                    r="20"
                                    fill="none"
                                    stroke-width="3.5"
                                    class="hcis-spinner-track"
                                />
                                <circle
                                    cx="25"
                                    cy="25"
                                    r="20"
                                    fill="none"
                                    stroke-width="3.5"
                                    class="hcis-spinner-head"
                                />
                            </svg>
                        </div>

                        <!-- Success Animated Vector Checkmark Draw -->
                        <div v-else-if="status === 'success'" class="hcis-result-state__svg-wrap">
                            <svg viewBox="0 0 52 52" class="hcis-result-svg-icon hcis-svg-check">
                                <circle cx="26" cy="26" r="24" fill="none" class="hcis-svg-circle" />
                                <path fill="none" d="M14.5 27.5l7.5 7.5 16-17" class="hcis-svg-check-path" />
                            </svg>
                        </div>

                        <!-- Error Animated Vector Cross Draw -->
                        <div v-else-if="status === 'error' || status === 'danger'" class="hcis-result-state__svg-wrap">
                            <svg viewBox="0 0 52 52" class="hcis-result-svg-icon hcis-svg-cross">
                                <circle cx="26" cy="26" r="24" fill="none" class="hcis-svg-circle" />
                                <path fill="none" d="M17 17l18 18 M35 17l-18 18" class="hcis-svg-cross-path" />
                            </svg>
                        </div>

                        <!-- Warning / Info / Processing Status Icons -->
                        <div v-else class="hcis-result-state__icon-inner">
                            <HcisIcon :name="computedIcon" :size="iconSize" />
                        </div>
                    </div>
                </div>

                <!-- Main Content (Kicker, Title & Description) -->
                <div class="hcis-result-state__content">
                    <div class="hcis-result-state__header-text">
                        <!-- Optional Kicker / Eyebrow -->
                        <div v-if="kicker || $slots.kicker" class="hcis-result-state__kicker">
                            <slot name="kicker">{{ kicker }}</slot>
                        </div>

                        <h2 v-if="title || $slots.title" class="hcis-result-state__title">
                            <slot name="title">{{ title }}</slot>
                        </h2>
                    </div>

                    <p v-if="description || $slots.description" class="hcis-result-state__description">
                        <slot name="description">{{ description }}</slot>
                    </p>
                </div>

                <!-- Fintech Digital Receipt Breakdown Card -->
                <div v-if="items && items.length" class="hcis-result-state__receipt">
                    <div class="hcis-receipt-header">
                        <div class="d-flex align-items-center gap-2">
                            <HcisIcon
                                :name="status === 'success' ? 'check-circle-fill' : status === 'warning' ? 'exclamation-triangle-fill' : (status === 'error' || status === 'danger') ? 'x-circle-fill' : 'info-circle-fill'"
                                size="xs"
                                class="hcis-receipt-icon"
                            />
                            <span class="hcis-receipt-header-title">Rincian Ringkasan Transaksi</span>
                        </div>
                        <span class="hcis-receipt-status-badge" :class="`is-${status}`">
                            {{ status === 'success' ? 'TERVERIFIKASI' : status === 'warning' ? 'REVIEW PENDING' : (status === 'error' || status === 'danger') ? 'GAGAL' : 'VALID' }}
                        </span>
                    </div>

                    <div
                        v-for="(item, idx) in items"
                        :key="idx"
                        class="hcis-result-state__receipt-row"
                        :style="{ animationDelay: `${(idx + 1) * 50}ms` }"
                    >
                        <span class="hcis-receipt-label">{{ item.label }}</span>
                        <div class="hcis-receipt-value-wrap">
                            <span class="hcis-receipt-value" :class="{ 'is-mono': item.copyable || item.mono }">
                                {{ item.value }}
                            </span>
                            <button
                                v-if="item.copyable"
                                type="button"
                                class="hcis-copy-btn"
                                :class="{ 'is-copied': copiedIndex === idx }"
                                :title="copiedIndex === idx ? 'Tersalin ke Clipboard!' : 'Salin'"
                                @click="copyText(item.value, idx)"
                            >
                                <HcisIcon :name="copiedIndex === idx ? 'check-lg' : 'copy'" size="2xs" />
                                <span v-if="copiedIndex === idx" class="hcis-copy-feedback">Tersalin</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Enterprise Error Diagnostic Console Box (errorDetail + errorCode + requestId) -->
                <div
                    v-if="(status === 'error' || status === 'danger') && (errorDetail || errorCode || requestId)"
                    class="hcis-result-state__error-box"
                >
                    <!-- Diagnostic Card Header -->
                    <div class="hcis-error-header">
                        <div class="hcis-error-header-main">
                            <HcisIcon name="exclamation-octagon-fill" size="xs" class="hcis-error-icon" />
                            <span class="hcis-error-title">Rincian Kendala Sistem</span>
                        </div>
                        <button
                            type="button"
                            class="hcis-error-copy-btn"
                            :title="copiedError ? 'Tersalin!' : 'Salin Log Error'"
                            @click="copyDiagnosticLog"
                        >
                            <HcisIcon :name="copiedError ? 'check-lg' : 'copy'" size="2xs" />
                            <span>{{ copiedError ? 'Tersalin' : 'Salin Log' }}</span>
                        </button>
                    </div>

                    <!-- Structured Key-Value Metadata Grid -->
                    <div v-if="errorCode || requestId" class="hcis-error-meta-grid">
                        <div v-if="errorCode" class="hcis-error-meta-row">
                            <span class="hcis-error-meta-label">Kode Error</span>
                            <span class="hcis-error-code-badge">{{ errorCode }}</span>
                        </div>
                        <div v-if="requestId" class="hcis-error-meta-row">
                            <span class="hcis-error-meta-label">Request ID</span>
                            <code class="hcis-error-meta-val">{{ requestId }}</code>
                        </div>
                    </div>

                    <!-- Technical Diagnostic Trace Console Box -->
                    <div v-if="errorDetail" class="hcis-error-console-box">
                        <div class="hcis-error-console-header">
                            <HcisIcon name="terminal" size="2xs" class="hcis-error-icon me-1" />
                            <span>Log Diagnostik Teknis:</span>
                        </div>
                        <pre class="hcis-error-code">{{ errorDetail }}</pre>
                    </div>
                </div>

                <!-- Action Buttons Footer -->
                <div v-if="$slots.actions || primaryText || secondaryText || (status === 'error' && showRetry)" class="hcis-result-state__actions">
                    <slot name="actions">
                        <HcisButton
                            v-if="(status === 'error' || status === 'danger') && showRetry"
                            variant="danger"
                            :size="btnSize"
                            leading-icon="arrow-counterclockwise"
                            title="Coba Lagi"
                            aria-label="Coba Lagi"
                            @click="$emit('retry')"
                        >
                            <span class="hcis-btn-label">Coba Lagi</span>
                        </HcisButton>

                        <HcisButton
                            v-if="primaryText"
                            :variant="resolvedPrimaryVariant"
                            :size="btnSize"
                            :leading-icon="primaryIcon || (layout === 'inline' ? 'arrow-right' : '')"
                            :title="primaryText"
                            :aria-label="primaryText"
                            @click="$emit('primary-click')"
                        >
                            <span class="hcis-btn-label">{{ primaryText }}</span>
                        </HcisButton>

                        <HcisButton
                            v-if="secondaryText"
                            :variant="secondaryVariant"
                            :size="btnSize"
                            :leading-icon="secondaryIcon || (layout === 'inline' ? 'x' : '')"
                            :title="secondaryText"
                            :aria-label="secondaryText"
                            @click="$emit('secondary-click')"
                        >
                            <span class="hcis-btn-label">{{ secondaryText }}</span>
                        </HcisButton>
                    </slot>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import HcisIcon from './HcisIcon.vue';
import HcisButton from './HcisButton.vue';

defineOptions({ name: 'HcisResultState' });

const props = defineProps({
    status: {
        type: String,
        default: 'success',
        validator: (v) => ['success', 'error', 'danger', 'warning', 'info', 'loading', 'processing'].includes(v),
    },
    variant: {
        type: String,
        default: 'transparent',
        validator: (v) => ['transparent', 'card', 'flat', 'glass', 'page'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    layout: {
        type: String,
        default: 'centered',
        validator: (v) => ['centered', 'compact', 'inline'].includes(v),
    },
    kicker: { type: String, default: '' },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    errorDetail: { type: String, default: '' },
    errorCode: { type: String, default: '' },
    requestId: { type: String, default: '' },
    showRetry: { type: Boolean, default: true },
    showAura: { type: Boolean, default: false },
    animated: { type: Boolean, default: true },
    loopAnimation: { type: Boolean, default: true },
    visible: { type: Boolean, default: true },
    renderKey: { type: [String, Number], default: 0 },
    primaryText: { type: String, default: '' },
    primaryIcon: { type: String, default: '' },
    primaryVariant: { type: String, default: '' },
    secondaryText: { type: String, default: '' },
    secondaryIcon: { type: String, default: '' },
    secondaryVariant: { type: String, default: 'outline' },
    pattern: {
        type: String,
        default: 'auto',
        validator: (v) => ['auto', 'grid', 'dots', 'none'].includes(v),
    },
});

const emit = defineEmits([
    'primary-click',
    'secondary-click',
    'retry',
]);

const resolvedPattern = computed(() => {
    if (props.pattern === 'none') return 'none';
    if (props.pattern === 'dots') return 'dots';
    if (props.pattern === 'grid') return 'grid';
    // auto fallback for glass / card / page variants
    if (['glass', 'card', 'page'].includes(props.variant)) return 'grid';
    return 'none';
});

const copiedIndex = ref(null);
const copiedError = ref(false);
const stateKey = ref(0);

watch(
    () => [props.status, props.renderKey, props.title],
    () => {
        stateKey.value += 1;
        copiedIndex.value = null;
        copiedError.value = false;
    }
);

const computedIcon = computed(() => {
    switch (props.status) {
        case 'success':
            return 'check-lg';
        case 'error':
        case 'danger':
            return 'x-lg';
        case 'warning':
            return 'exclamation-lg';
        case 'info':
        case 'processing':
            return 'info-lg';
        default:
            return 'check-lg';
    }
});

const iconSize = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'lg';
        case 'lg':
            return '2xl';
        default:
            return 'xl';
    }
});

const btnSize = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'sm';
        case 'lg':
            return 'lg';
        default:
            return 'md';
    }
});

const resolvedPrimaryVariant = computed(() => {
    if (props.primaryVariant) return props.primaryVariant;
    if (props.status === 'error' || props.status === 'danger') {
        // Saat ada tombol 'Coba Lagi' (merah), jadikan tombol navigasi 'Kembali' netral (secondary)
        return props.showRetry ? 'secondary' : 'primary';
    }
    switch (props.status) {
        case 'success':
            return 'success';
        case 'warning':
            return 'warning';
        default:
            return 'primary';
    }
});

function copyText(text, index) {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        navigator.clipboard.writeText(text);
        copiedIndex.value = index;
        setTimeout(() => {
            if (copiedIndex.value === index) {
                copiedIndex.value = null;
            }
        }, 2200);
    }
}

function copyDiagnosticLog() {
    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        const payload = [];
        if (props.errorCode) payload.push(`Error Code: ${props.errorCode}`);
        if (props.requestId) payload.push(`Request ID: ${props.requestId}`);
        if (props.errorDetail) payload.push(`Detail:\n${props.errorDetail}`);
        
        navigator.clipboard.writeText(payload.join('\n'));
        copiedError.value = true;
        setTimeout(() => {
            copiedError.value = false;
        }, 2200);
    }
}
</script>

<style scoped>
/* Root Container (Background-free & Seamless by Default) -------------------- */
.hcis-result-state {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    width: 100%;
    box-sizing: border-box;
    background: transparent;
    border: none;
    border-radius: var(--hcis-radius-2xl);
    padding: 2.25rem 1.25rem;
    overflow: hidden;
    font-family: var(--hcis-font-body, inherit);
    transition: background var(--hcis-duration-fast) var(--hcis-ease), border-color var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-result-state__body {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 100%;
    will-change: transform, opacity;
}

/* ═══════════════════════════════════════════════════════════════════════════
   SURFACE VARIANTS (Card, Glass, Flat, Page)
   ═══════════════════════════════════════════════════════════════════════════ */

/* Sentiment-Adaptive Aurora Glass Surface (30% - 70% White Sweet Spot) */
.hcis-result-state--glass,
.hcis-result-state--card {
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(var(--hcis-glass-blur, 16px));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur, 16px));
    border-radius: var(--hcis-radius-2xl);
    box-shadow: var(--hcis-shadow-xl), inset 0 1px 0 var(--hcis-white);
    padding: 2.75rem 1.75rem;
}

.hcis-result-state--glass.hcis-result-state--success,
.hcis-result-state--card.hcis-result-state--success {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-success-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-success-600-rgb) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-success-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-success-100-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.28);
}

.hcis-result-state--glass.hcis-result-state--error,
.hcis-result-state--glass.hcis-result-state--danger,
.hcis-result-state--card.hcis-result-state--error,
.hcis-result-state--card.hcis-result-state--danger {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-danger-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-danger-600-rgb) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-danger-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-danger-100-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.28);
}

.hcis-result-state--glass.hcis-result-state--warning,
.hcis-result-state--card.hcis-result-state--warning {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-warning-500-rgb) / 0.16), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-warning-600-rgb) / 0.1), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-warning-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-warning-100-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.28);
}

.hcis-result-state--glass.hcis-result-state--info,
.hcis-result-state--glass.hcis-result-state--loading,
.hcis-result-state--glass.hcis-result-state--processing,
.hcis-result-state--card.hcis-result-state--info,
.hcis-result-state--card.hcis-result-state--loading,
.hcis-result-state--card.hcis-result-state--processing {
    background:
        radial-gradient(ellipse 500px 300px at 105% -15%, rgb(var(--hcis-brand-500-rgb) / 0.14), transparent 55%),
        radial-gradient(ellipse 450px 260px at -5% 115%, rgb(var(--hcis-brand-600-rgb) / 0.09), transparent 55%),
        linear-gradient(225deg, rgb(var(--hcis-brand-50-rgb) / 0.88) 0%, var(--hcis-white) 30%, var(--hcis-white) 70%, rgb(var(--hcis-brand-50-rgb) / 0.5) 100%);
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.2);
}

.hcis-result-state--flat {
    background: var(--hcis-surface-soft);
    border: 1px solid var(--hcis-border-subtle);
    box-shadow: none;
    padding: 2.75rem 1.75rem;
}

.hcis-result-state--page {
    min-height: 70vh;
    background: transparent;
    border: none;
    box-shadow: none;
    padding: 3.5rem 1.5rem;
}


/* Sizes --------------------------------------------------------------------- */
.hcis-result-state--size-sm {
    padding: 1.5rem 1rem;
}

.hcis-result-state--size-lg {
    padding: 3.5rem 2rem;
}

/* Ambient Aura Glow & Pulse ------------------------------------------------- */
.hcis-result-state__aurora {
    position: absolute;
    top: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 24rem;
    height: 24rem;
    border-radius: 50%;
    filter: blur(52px);
    opacity: 0.35;
    pointer-events: none;
    transition: background var(--hcis-duration-panel) ease;
    z-index: 1;
}

.hcis-result-state.is-looping .hcis-result-state__aurora {
    animation: hcis-result-aura-pulse 4s ease-in-out infinite alternate;
}

.hcis-result-state--success .hcis-result-state__aurora {
    background: radial-gradient(circle, var(--hcis-success-500) 0%, rgb(var(--hcis-success-500-rgb) / 0.15) 70%, transparent 100%);
}

.hcis-result-state--error .hcis-result-state__aurora,
.hcis-result-state--danger .hcis-result-state__aurora {
    background: radial-gradient(circle, var(--hcis-danger-500) 0%, rgb(var(--hcis-danger-500-rgb) / 0.15) 70%, transparent 100%);
}

.hcis-result-state--warning .hcis-result-state__aurora {
    background: radial-gradient(circle, var(--hcis-warning-500) 0%, rgb(var(--hcis-warning-500-rgb) / 0.15) 70%, transparent 100%);
}

.hcis-result-state--info .hcis-result-state__aurora,
.hcis-result-state--processing .hcis-result-state__aurora,
.hcis-result-state--loading .hcis-result-state__aurora {
    background: radial-gradient(circle, var(--hcis-brand-500) 0%, rgb(var(--hcis-brand-500-rgb) / 0.15) 70%, transparent 100%);
}

/* Jewel-Like Icon Badge & Ripple Rings -------------------------------------- */
.hcis-result-state__icon-container {
    position: relative;
    margin-bottom: 1.5rem;
}

.hcis-result-state__icon-halo {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 4.75rem;
    height: 4.75rem;
    border-radius: 50%;
    pointer-events: none;
}

.hcis-result-state--size-sm .hcis-result-state__icon-halo { width: 3.5rem; height: 3.5rem; }
.hcis-result-state--size-lg .hcis-result-state__icon-halo { width: 5.75rem; height: 5.75rem; }

.hcis-result-state--success .hcis-result-state__icon-halo { background: var(--hcis-success-500); }
.hcis-result-state--error .hcis-result-state__icon-halo,
.hcis-result-state--danger .hcis-result-state__icon-halo { background: var(--hcis-danger-500); }
.hcis-result-state--warning .hcis-result-state__icon-halo { background: var(--hcis-warning-500); }
.hcis-result-state--info .hcis-result-state__icon-halo,
.hcis-result-state--processing .hcis-result-state__icon-halo { background: var(--hcis-brand-500); }

.hcis-result-state.is-looping .hcis-halo-ring-1 {
    animation: hcis-result-ripple-expand 2.6s var(--hcis-ease) infinite;
}

.hcis-result-state.is-looping .hcis-halo-ring-2 {
    animation: hcis-result-ripple-expand 2.6s var(--hcis-ease) infinite 1.3s;
}

.hcis-result-state__icon-ring {
    position: relative;
    width: 4.75rem;
    height: 4.75rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 3px solid rgb(var(--hcis-white-rgb) / 0.9);
    box-sizing: border-box;
    overflow: hidden;
    transition: transform var(--hcis-duration-fast) var(--hcis-ease), box-shadow var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-result-state--size-sm .hcis-result-state__icon-ring {
    width: 3.5rem;
    height: 3.5rem;
    border-width: 2.5px;
}

.hcis-result-state--size-lg .hcis-result-state__icon-ring {
    width: 5.75rem;
    height: 5.75rem;
    border-width: 3.5px;
}

.hcis-result-state.is-animated .hcis-result-state__icon-ring {
    animation: hcis-result-spring-enter 580ms var(--hcis-ease-spring) both;
}

/* Glass Reflection Sheen on Icon Badge */
.hcis-result-state__icon-glass-reflect {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 50%;
    background: linear-gradient(180deg, rgb(var(--hcis-white-rgb) / 0.42) 0%, rgb(var(--hcis-white-rgb) / 0.04) 100%);
    border-radius: 50% 50% 0 0 / 100% 100% 0 0;
    pointer-events: none;
}

/* Jewel Status Variants */
.hcis-result-state--success .hcis-result-state__icon-ring {
    background: linear-gradient(145deg, var(--hcis-success-500) 0%, var(--hcis-success-600) 100%);
    color: var(--hcis-white);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-success-500-rgb) / 0.45), inset 0 1px 1px rgb(var(--hcis-white-rgb) / 0.6);
}

.hcis-result-state--error .hcis-result-state__icon-ring,
.hcis-result-state--danger .hcis-result-state__icon-ring {
    background: linear-gradient(145deg, var(--hcis-danger-500) 0%, var(--hcis-danger-600) 100%);
    color: var(--hcis-white);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-danger-500-rgb) / 0.45), inset 0 1px 1px rgb(var(--hcis-white-rgb) / 0.6);
}

.hcis-result-state--warning .hcis-result-state__icon-ring {
    background: linear-gradient(145deg, var(--hcis-warning-500) 0%, var(--hcis-warning-600) 100%);
    color: var(--hcis-white);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-warning-500-rgb) / 0.45), inset 0 1px 1px rgb(var(--hcis-white-rgb) / 0.6);
}

.hcis-result-state--info .hcis-result-state__icon-ring,
.hcis-result-state--processing .hcis-result-state__icon-ring {
    background: linear-gradient(145deg, var(--hcis-brand-500) 0%, var(--hcis-brand-600) 100%);
    color: var(--hcis-white);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-brand-500-rgb) / 0.45), inset 0 1px 1px rgb(var(--hcis-white-rgb) / 0.6);
}

.hcis-result-state--loading .hcis-result-state__icon-ring {
    background: var(--hcis-surface);
    border-color: var(--hcis-border-subtle);
    box-shadow: var(--hcis-shadow-sm);
}

.hcis-result-state__icon-inner {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    filter: drop-shadow(0 2px 4px rgb(var(--hcis-slate-900-rgb) / 0.25));
}

/* Animated SVG Vector Checkmark & Cross Icons ------------------------------ */
.hcis-result-state__svg-wrap {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2.75rem;
    height: 2.75rem;
}

.hcis-result-state--size-sm .hcis-result-state__svg-wrap { width: 2rem; height: 2rem; }
.hcis-result-state--size-lg .hcis-result-state__svg-wrap { width: 3.5rem; height: 3.5rem; }

.hcis-result-svg-icon {
    width: 100%;
    height: 100%;
    filter: drop-shadow(0 2px 4px rgb(var(--hcis-slate-900-rgb) / 0.25));
}

.hcis-svg-circle {
    stroke: rgb(var(--hcis-white-rgb) / 0.35);
    stroke-width: 2.5;
    stroke-miterlimit: 10;
}

.hcis-svg-check-path {
    stroke: var(--hcis-white);
    stroke-width: 4;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 48;
    stroke-dashoffset: 48;
}

.hcis-result-state.is-animated .hcis-svg-check-path {
    animation: hcis-result-check-draw 420ms var(--hcis-ease) 220ms forwards;
}

.hcis-svg-cross-path {
    stroke: var(--hcis-white);
    stroke-width: 4;
    stroke-linecap: round;
    stroke-linejoin: round;
    stroke-dasharray: 36;
    stroke-dashoffset: 36;
}

.hcis-result-state.is-animated .hcis-svg-cross-path {
    animation: hcis-result-cross-draw 400ms var(--hcis-ease) 200ms forwards;
}

/* Premium Spinner ----------------------------------------------------------- */
.hcis-result-state__spinner {
    position: relative;
    width: 3.25rem;
    height: 3.25rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hcis-result-state--size-sm .hcis-result-state__spinner { width: 2.25rem; height: 2.25rem; }
.hcis-result-state--size-lg .hcis-result-state__spinner { width: 4rem; height: 4rem; }

.hcis-spinner-svg {
    width: 100%;
    height: 100%;
    animation: hcis-icon-spin 1.2s linear infinite;
    transform-origin: center center;
}

.hcis-spinner-track {
    stroke: var(--hcis-border-subtle);
    opacity: 0.65;
}

.hcis-spinner-head {
    stroke: var(--hcis-brand-600);
    stroke-dasharray: 85, 150;
    stroke-dashoffset: 0;
    stroke-linecap: round;
}

/* Content (Kicker, Title & Description) ------------------------------------- */
.hcis-result-state__content {
    position: relative;
    max-width: 34rem;
    margin-bottom: 1.5rem;
}

.hcis-result-state.is-animated .hcis-result-state__content {
    animation: hcis-result-fade-up 420ms var(--hcis-ease) 90ms both;
}

.hcis-result-state__kicker {
    font-size: 0.65rem;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.18rem 0.65rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    margin-bottom: 0.5rem;
    line-height: 1.25;
    background: var(--hcis-surface-soft, var(--hcis-slate-100));
    color: var(--hcis-slate-700, var(--hcis-slate-700));
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-200));
    box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.06);
}

.hcis-result-state--success .hcis-result-state__kicker {
    background: rgb(var(--hcis-success-500-rgb) / 0.14) !important;
    color: var(--hcis-success-700) !important;
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.3) !important;
}

.hcis-result-state--error .hcis-result-state__kicker,
.hcis-result-state--danger .hcis-result-state__kicker {
    background: rgb(var(--hcis-danger-500-rgb) / 0.14) !important;
    color: var(--hcis-danger-700) !important;
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.3) !important;
}

.hcis-result-state--warning .hcis-result-state__kicker {
    background: rgb(var(--hcis-warning-500-rgb) / 0.14) !important;
    color: var(--hcis-warning-800) !important;
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.3) !important;
}

.hcis-result-state--info .hcis-result-state__kicker,
.hcis-result-state--processing .hcis-result-state__kicker,
.hcis-result-state--loading .hcis-result-state__kicker {
    background: rgb(var(--hcis-brand-500-rgb) / 0.14) !important;
    color: var(--hcis-brand-700) !important;
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.3) !important;
}

.hcis-result-state__title {
    font-size: 1.35rem;
    font-weight: 800;
    color: var(--hcis-text-strong);
    margin: 0 0 0.5rem 0;
    letter-spacing: -0.02em;
    line-height: 1.3;
}

.hcis-result-state--size-sm .hcis-result-state__title { font-size: 1.125rem; margin-bottom: 0.35rem; }
.hcis-result-state--size-lg .hcis-result-state__title { font-size: 1.625rem; margin-bottom: 0.75rem; }

.hcis-result-state__description {
    font-size: 0.875rem;
    color: var(--hcis-text-muted);
    line-height: 1.6;
    margin: 0;
}

.hcis-result-state--size-sm .hcis-result-state__description { font-size: 0.8125rem; line-height: 1.5; }
.hcis-result-state--size-lg .hcis-result-state__description { font-size: 0.95rem; line-height: 1.65; }

/* Fintech Digital Receipt Card ---------------------------------------------- */
.hcis-result-state__receipt {
    position: relative;
    width: 100%;
    max-width: 30rem;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    border-radius: var(--hcis-radius-xl);
    padding: 1.15rem 1.35rem;
    margin-bottom: 1.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    /* --hcis-duration-normal tidak pernah ada: skalanya press/fast/panel/move.
       Token yang tak terdefinisi membuat SELURUH deklarasi ini gugur, jadi
       selama ini kartu ini tidak beranimasi sama sekali. */
    transition-property: var(--hcis-transition-props);
    transition-duration: var(--hcis-duration-fast);
    transition-timing-function: var(--hcis-ease);
}

/* Default / Neutral Receipt Surface */
.hcis-result-state__receipt {
    background:
        radial-gradient(ellipse 300px 160px at 100% -10%, rgb(var(--hcis-brand-500-rgb) / 0.18), transparent 70%),
        radial-gradient(ellipse 260px 140px at -5% 110%, rgb(var(--hcis-brand-600-rgb) / 0.12), transparent 70%),
        linear-gradient(165deg, rgb(var(--hcis-white-rgb) / 0.96) 0%, rgb(var(--hcis-brand-50-rgb) / 0.85) 45%, rgb(var(--hcis-brand-100-rgb) / 0.75) 100%);
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.3);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-brand-500-rgb) / 0.2), 0 2px 6px -1px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
}

/* Success Sentiment Receipt Glass Surface (Emerald Aura) */
.hcis-result-state--success .hcis-result-state__receipt {
    background:
        radial-gradient(ellipse 300px 160px at 100% -10%, rgb(var(--hcis-success-500-rgb) / 0.22), transparent 70%),
        radial-gradient(ellipse 260px 140px at -5% 110%, rgb(var(--hcis-success-600-rgb) / 0.14), transparent 70%),
        linear-gradient(165deg, rgb(var(--hcis-white-rgb) / 0.96) 0%, rgb(var(--hcis-success-50-rgb) / 0.88) 45%, rgb(var(--hcis-success-100-rgb) / 0.75) 100%);
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.32);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-success-500-rgb) / 0.2), 0 2px 6px -1px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
}

/* Warning Sentiment Receipt Glass Surface (Amber Gold Aura) */
.hcis-result-state--warning .hcis-result-state__receipt {
    background:
        radial-gradient(ellipse 300px 160px at 100% -10%, rgb(var(--hcis-warning-500-rgb) / 0.22), transparent 70%),
        radial-gradient(ellipse 260px 140px at -5% 110%, rgb(var(--hcis-warning-600-rgb) / 0.14), transparent 70%),
        linear-gradient(165deg, rgb(var(--hcis-white-rgb) / 0.96) 0%, rgb(var(--hcis-warning-50-rgb) / 0.88) 45%, rgb(var(--hcis-warning-100-rgb) / 0.75) 100%);
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.32);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-warning-500-rgb) / 0.2), 0 2px 6px -1px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
}

/* Error / Danger Sentiment Receipt Glass Surface (Coral Rose Aura) */
.hcis-result-state--error .hcis-result-state__receipt,
.hcis-result-state--danger .hcis-result-state__receipt {
    background:
        radial-gradient(ellipse 300px 160px at 100% -10%, rgb(var(--hcis-danger-500-rgb) / 0.22), transparent 70%),
        radial-gradient(ellipse 260px 140px at -5% 110%, rgb(var(--hcis-danger-600-rgb) / 0.14), transparent 70%),
        linear-gradient(165deg, rgb(var(--hcis-white-rgb) / 0.96) 0%, rgb(var(--hcis-danger-50-rgb) / 0.88) 45%, rgb(var(--hcis-danger-100-rgb) / 0.75) 100%);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.32);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-danger-500-rgb) / 0.2), 0 2px 6px -1px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
}

/* Info Sentiment Receipt Glass Surface (Royal Indigo Aura) */
.hcis-result-state--info .hcis-result-state__receipt,
.hcis-result-state--loading .hcis-result-state__receipt,
.hcis-result-state--processing .hcis-result-state__receipt {
    background:
        radial-gradient(ellipse 300px 160px at 100% -10%, rgb(var(--hcis-brand-500-rgb) / 0.22), transparent 70%),
        radial-gradient(ellipse 260px 140px at -5% 110%, rgb(var(--hcis-brand-600-rgb) / 0.14), transparent 70%),
        linear-gradient(165deg, rgb(var(--hcis-white-rgb) / 0.96) 0%, rgb(var(--hcis-brand-50-rgb) / 0.88) 45%, rgb(var(--hcis-brand-100-rgb) / 0.75) 100%);
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.3);
    box-shadow: 0 12px 28px -4px rgb(var(--hcis-brand-500-rgb) / 0.2), 0 2px 6px -1px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
}

.hcis-result-state.is-animated .hcis-result-state__receipt {
    animation: hcis-result-pop-up 520ms var(--hcis-ease) 100ms both;
}

.hcis-receipt-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 0.55rem;
    border-bottom: 1px solid rgb(var(--hcis-slate-900-rgb) / 0.08);
    margin-bottom: 0.25rem;
}

.hcis-result-state--success .hcis-receipt-header { border-bottom-color: rgb(var(--hcis-success-500-rgb) / 0.25); }
.hcis-result-state--warning .hcis-receipt-header { border-bottom-color: rgb(var(--hcis-warning-500-rgb) / 0.25); }
.hcis-result-state--error .hcis-receipt-header,
.hcis-result-state--danger .hcis-receipt-header { border-bottom-color: rgb(var(--hcis-danger-500-rgb) / 0.25); }
.hcis-result-state--info .hcis-receipt-header { border-bottom-color: rgb(var(--hcis-brand-500-rgb) / 0.25); }

.hcis-receipt-icon {
    color: var(--hcis-brand-600);
}

.hcis-result-state--success .hcis-receipt-icon { color: var(--hcis-success-600); }
.hcis-result-state--warning .hcis-receipt-icon { color: var(--hcis-warning-600); }
.hcis-result-state--error .hcis-receipt-icon,
.hcis-result-state--danger .hcis-receipt-icon { color: var(--hcis-danger-600); }
.hcis-result-state--info .hcis-receipt-icon { color: var(--hcis-brand-600); }

.hcis-receipt-header-title {
    font-size: 0.725rem;
    font-weight: 750;
    letter-spacing: -0.01em;
    color: var(--hcis-slate-800);
}

.hcis-result-state--success .hcis-receipt-header-title { color: var(--hcis-success-900); }
.hcis-result-state--warning .hcis-receipt-header-title { color: var(--hcis-warning-900); }
.hcis-result-state--error .hcis-receipt-header-title,
.hcis-result-state--danger .hcis-receipt-header-title { color: var(--hcis-danger-900); }
.hcis-result-state--info .hcis-receipt-header-title { color: var(--hcis-brand-900); }

.hcis-receipt-status-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.65rem;
    font-weight: 750;
    padding: 0.15rem 0.5rem;
    border-radius: var(--hcis-radius-pill);
    letter-spacing: 0.04em;
}

.hcis-receipt-status-badge.is-success {
    background: var(--hcis-success-100);
    color: var(--hcis-success-800);
    border: 1px solid rgb(var(--hcis-success-500-rgb) / 0.28);
}

.hcis-receipt-status-badge.is-warning {
    background: var(--hcis-warning-100);
    color: var(--hcis-warning-800);
    border: 1px solid rgb(var(--hcis-warning-500-rgb) / 0.28);
}

.hcis-receipt-status-badge.is-danger,
.hcis-receipt-status-badge.is-error {
    background: var(--hcis-danger-100);
    color: var(--hcis-danger-800);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.28);
}

.hcis-receipt-status-badge.is-info,
.hcis-receipt-status-badge.is-loading,
.hcis-receipt-status-badge.is-processing {
    background: var(--hcis-brand-100);
    color: var(--hcis-brand-800);
    border: 1px solid rgb(var(--hcis-brand-500-rgb) / 0.28);
}

.hcis-result-state__receipt-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.8125rem;
    padding-bottom: 0.55rem;
    border-bottom: 1px dashed rgb(var(--hcis-slate-900-rgb) / 0.08);
}

.hcis-result-state--success .hcis-result-state__receipt-row { border-bottom-color: rgb(var(--hcis-success-500-rgb) / 0.22); }
.hcis-result-state--warning .hcis-result-state__receipt-row { border-bottom-color: rgb(var(--hcis-warning-500-rgb) / 0.22); }
.hcis-result-state--error .hcis-result-state__receipt-row,
.hcis-result-state--danger .hcis-result-state__receipt-row { border-bottom-color: rgb(var(--hcis-danger-500-rgb) / 0.22); }
.hcis-result-state--info .hcis-result-state__receipt-row { border-bottom-color: rgb(var(--hcis-brand-500-rgb) / 0.22); }

.hcis-result-state.is-animated .hcis-result-state__receipt-row {
    animation: hcis-result-row-slide 320ms var(--hcis-ease) both;
}

.hcis-result-state__receipt-row:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.hcis-receipt-label {
    color: var(--hcis-slate-600);
    font-weight: 500;
    text-align: left;
}

.hcis-result-state--success .hcis-receipt-label { color: var(--hcis-success-900); }
.hcis-result-state--warning .hcis-receipt-label { color: var(--hcis-warning-900); }
.hcis-result-state--error .hcis-receipt-label,
.hcis-result-state--danger .hcis-receipt-label { color: var(--hcis-danger-900); }
.hcis-result-state--info .hcis-receipt-label { color: var(--hcis-brand-900); }

.hcis-receipt-value-wrap {
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.hcis-receipt-value {
    color: var(--hcis-slate-900);
    font-weight: 700;
    text-align: right;
}

.hcis-receipt-value.is-mono {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    letter-spacing: -0.01em;
    background: rgb(var(--hcis-slate-900-rgb) / 0.04);
    padding: 0.12rem 0.4rem;
    border-radius: var(--hcis-radius-xs);
    border: 1px solid rgb(var(--hcis-slate-900-rgb) / 0.08);
}

.hcis-result-state--success .hcis-receipt-value.is-mono {
    background: rgb(var(--hcis-success-500-rgb) / 0.12);
    border-color: rgb(var(--hcis-success-500-rgb) / 0.28);
    color: var(--hcis-success-900);
}

.hcis-result-state--warning .hcis-receipt-value.is-mono {
    background: rgb(var(--hcis-warning-500-rgb) / 0.12);
    border-color: rgb(var(--hcis-warning-500-rgb) / 0.28);
    color: var(--hcis-warning-900);
}

.hcis-result-state--error .hcis-receipt-value.is-mono,
.hcis-result-state--danger .hcis-receipt-value.is-mono {
    background: rgb(var(--hcis-danger-500-rgb) / 0.12);
    border-color: rgb(var(--hcis-danger-500-rgb) / 0.28);
    color: var(--hcis-danger-900);
}

.hcis-result-state--info .hcis-receipt-value.is-mono {
    background: rgb(var(--hcis-brand-500-rgb) / 0.12);
    border-color: rgb(var(--hcis-brand-500-rgb) / 0.28);
    color: var(--hcis-brand-900);
}

/* Micro Copy Button with Tooltip Feedback */
.hcis-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid var(--hcis-border-subtle);
    background: var(--hcis-surface);
    color: var(--hcis-text-muted);
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.2rem 0.45rem;
    border-radius: var(--hcis-radius-sm);
    cursor: pointer;
    transition: color var(--hcis-duration-fast) var(--hcis-ease), background var(--hcis-duration-fast) var(--hcis-ease), border-color var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-copy-btn:hover {
    color: var(--hcis-brand-600);
    border-color: var(--hcis-brand-300);
    background: var(--hcis-surface-soft);
}

.hcis-copy-btn.is-copied {
    color: var(--hcis-success-700);
    border-color: var(--hcis-success-300);
    background: var(--hcis-success-50);
}

.hcis-copy-feedback {
    font-size: 0.65rem;
    font-weight: 700;
}

/* Enterprise Error Diagnostic Console Box ----------------------------------- */
.hcis-result-state__error-box {
    position: relative;
    width: 100%;
    max-width: 30rem;
    backdrop-filter: blur(16px);
    -webkit-backdrop-filter: blur(16px);
    background:
        radial-gradient(ellipse 280px 150px at 100% 0%, rgb(var(--hcis-danger-500-rgb) / 0.16), transparent 70%),
        radial-gradient(ellipse 260px 130px at 0% 100%, rgb(var(--hcis-danger-600-rgb) / 0.1), transparent 70%),
        linear-gradient(165deg, rgb(var(--hcis-white-rgb) / 0.96) 0%, rgb(254 242 242 / 0.92) 55%, rgb(var(--hcis-danger-50-rgb) / 0.78) 100%);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.28);
    border-radius: var(--hcis-radius-xl);
    padding: 1rem 1.25rem;
    margin-bottom: 1.75rem;
    text-align: left;
    box-shadow: 0 10px 25px -4px rgb(var(--hcis-danger-500-rgb) / 0.14), 0 2px 6px -1px rgb(var(--hcis-slate-900-rgb) / 0.04), inset 0 1px 0 var(--hcis-white);
    display: flex;
    flex-direction: column;
    gap: 0.55rem;
}

.hcis-result-state.is-animated .hcis-result-state__error-box {
    animation: hcis-result-pop-up 520ms var(--hcis-ease) 140ms both;
}

.hcis-error-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    padding-bottom: 0.45rem;
    border-bottom: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.2);
}

.hcis-error-header-main {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-width: 0;
}

.hcis-error-icon {
    color: var(--hcis-danger-600);
    flex-shrink: 0;
}

.hcis-error-title {
    font-size: 0.75rem;
    font-weight: 750;
    color: var(--hcis-danger-900);
    letter-spacing: -0.01em;
    white-space: nowrap;
}

.hcis-error-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.28);
    background: var(--hcis-white);
    color: var(--hcis-danger-700);
    font-size: 0.6875rem;
    font-weight: 600;
    padding: 0.18rem 0.45rem;
    border-radius: var(--hcis-radius-sm);
    cursor: pointer;
    flex-shrink: 0;
    transition: color var(--hcis-duration-fast) var(--hcis-ease), background var(--hcis-duration-fast) var(--hcis-ease), border-color var(--hcis-duration-fast) var(--hcis-ease);
}

.hcis-error-copy-btn:hover {
    background: var(--hcis-danger-100);
    color: var(--hcis-danger-800);
}

/* Key-Value Metadata Grid */
.hcis-error-meta-grid {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.hcis-error-meta-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    font-size: 0.75rem;
    padding-bottom: 0.35rem;
    border-bottom: 1px dashed rgb(var(--hcis-danger-500-rgb) / 0.2);
}

.hcis-error-meta-label {
    color: var(--hcis-danger-800);
    font-weight: 600;
    font-size: 0.725rem;
}

.hcis-error-code-badge {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.675rem;
    font-weight: 750;
    padding: 0.12rem 0.45rem;
    border-radius: var(--hcis-radius-pill);
    background: var(--hcis-danger-100);
    color: var(--hcis-danger-700);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.25);
    word-break: break-all;
}

.hcis-error-meta-val {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.675rem;
    font-weight: 600;
    color: var(--hcis-danger-900);
    background: rgb(var(--hcis-danger-500-rgb) / 0.08);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.2);
    padding: 0.12rem 0.4rem;
    border-radius: var(--hcis-radius-xs);
    word-break: break-all;
}

/* Diagnostic Console Terminal Frame */
.hcis-error-console-box {
    background: rgb(var(--hcis-slate-900-rgb) / 0.04);
    border: 1px solid rgb(var(--hcis-danger-500-rgb) / 0.18);
    border-radius: var(--hcis-radius-md);
    padding: 0.5rem 0.65rem;
    margin-top: 0.2rem;
}

.hcis-error-console-header {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    font-size: 0.65rem;
    font-weight: 700;
    color: var(--hcis-danger-800);
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.hcis-error-code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 0.7rem;
    color: var(--hcis-danger-900);
    background: transparent;
    border: none;
    margin: 0;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.45;
    max-height: 110px;
    overflow-y: auto;
}

/* Actions Footer ------------------------------------------------------------ */
.hcis-result-state__actions {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.hcis-result-state.is-animated .hcis-result-state__actions {
    animation: hcis-result-pop-up 520ms var(--hcis-ease) 180ms both;
}

/* ═══════════════════════════════════════════════════════════════════════════
   LAYOUT VARIATIONS (Centered, Compact, Inline)
   ═══════════════════════════════════════════════════════════════════════════ */

/* Layout: Compact (Horizontal card for Dialogs & Drawers) */
.hcis-result-state--layout-compact {
    padding: 1rem 1.25rem;
    border-radius: var(--hcis-radius-xl, 14px);
    text-align: left;
}

.hcis-result-state--layout-compact .hcis-result-state__body {
    display: flex;
    flex-direction: row;
    align-items: center;
    text-align: left;
    gap: 1rem;
    flex-wrap: wrap;
    width: 100%;
}

.hcis-result-state--layout-compact .hcis-result-state__icon-container {
    margin-bottom: 0;
    flex-shrink: 0;
}

.hcis-result-state--layout-compact .hcis-result-state__icon-ring {
    width: 2.75rem;
    height: 2.75rem;
    border-width: 2px;
    box-shadow: 0 4px 10px -2px rgb(var(--sentiment-500-rgb) / 0.35);
}

.hcis-result-state--layout-compact .hcis-result-state__svg-wrap {
    width: 1.65rem;
    height: 1.65rem;
}

.hcis-result-state--layout-compact .hcis-result-state__spinner {
    width: 1.85rem;
    height: 1.85rem;
}

.hcis-result-state--layout-compact .hcis-result-state__content {
    flex: 1;
    min-width: 200px;
    margin-bottom: 0;
    text-align: left;
    padding: 0;
}

.hcis-result-state--layout-compact .hcis-result-state__kicker {
    font-size: 0.5875rem;
    padding: 0.1rem 0.45rem;
    margin-bottom: 0.15rem;
    display: inline-flex;
}

.hcis-result-state--layout-compact .hcis-result-state__title {
    font-size: 0.95rem;
    font-weight: 800;
    margin-bottom: 0.15rem;
    line-height: 1.35;
}

.hcis-result-state--layout-compact .hcis-result-state__description {
    font-size: 0.775rem;
    line-height: 1.45;
    margin: 0;
}

.hcis-result-state--layout-compact .hcis-result-state__receipt,
.hcis-result-state--layout-compact .hcis-result-state__error-box {
    width: 100%;
    max-width: 100%;
    margin-top: 0.65rem;
    margin-bottom: 0.25rem;
    padding: 0.65rem 0.85rem;
}

.hcis-result-state--layout-compact .hcis-result-state__actions {
    margin-left: auto;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    width: auto;
}

.hcis-result-state--layout-compact .hcis-result-state__actions > * {
    width: auto !important;
    height: 32px !important;
    font-size: 0.75rem !important;
    padding: 0.25rem 0.75rem !important;
}

/* Layout: Inline (Slim Banner Bar) */
.hcis-result-state--layout-inline {
    padding: 0.55rem 1rem;
    border-radius: var(--hcis-radius-xl, 12px);
    min-height: 48px;
    width: 100%;
}

.hcis-result-state--layout-inline.hcis-result-state--glass,
.hcis-result-state--layout-inline.hcis-result-state--card {
    padding: 0.55rem 1rem;
    box-shadow: var(--hcis-shadow-xs), inset 0 1px 0 var(--hcis-white);
}

.hcis-result-state--layout-inline .hcis-result-state__aurora {
    display: none !important;
}

/* Scaled Compact Ripple Halos for Inline Banner */
.hcis-result-state--layout-inline .hcis-result-state__icon-halo {
    width: 2rem;
    height: 2rem;
    display: block;
}

.hcis-result-state--layout-inline.is-looping .hcis-halo-ring-1 {
    animation: hcis-inline-ripple-expand 2.4s var(--hcis-ease) infinite;
}

.hcis-result-state--layout-inline.is-looping .hcis-halo-ring-2 {
    animation: hcis-inline-ripple-expand 2.4s var(--hcis-ease) infinite 1.2s;
}

/* Breathing Jewel Icon Pulse for Inline Banner */
.hcis-result-state--layout-inline.is-looping .hcis-result-state__icon-ring {
    animation: hcis-inline-icon-breathe 2.8s ease-in-out infinite alternate;
}

/* Strictly 1-Row Horizontal Flex */
.hcis-result-state--layout-inline .hcis-result-state__body {
    display: flex;
    flex-direction: row;
    align-items: center;
    text-align: left;
    gap: 0.75rem;
    width: 100%;
    justify-content: space-between;
    flex-wrap: nowrap;
}

.hcis-result-state--layout-inline .hcis-result-state__icon-container {
    margin-bottom: 0;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.hcis-result-state--layout-inline .hcis-result-state__icon-ring {
    width: 2rem;
    height: 2rem;
    border-width: 2px;
    box-shadow: 0 3px 8px -2px rgb(var(--sentiment-500-rgb) / 0.3);
    flex-shrink: 0;
}

.hcis-result-state--layout-inline .hcis-result-state__svg-wrap {
    width: 1.25rem;
    height: 1.25rem;
}

.hcis-result-state--layout-inline .hcis-result-state__content {
    flex: 1;
    min-width: 0;
    margin-bottom: 0;
    text-align: left;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.12rem;
    overflow: hidden;
}

.hcis-result-state--layout-inline .hcis-result-state__header-text {
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.45rem;
    max-width: 100%;
    overflow: hidden;
}

/* When NO description is present in inline banner: Stack Kicker on top & Title below, aligned left */
.hcis-result-state--layout-inline:not(.has-description) .hcis-result-state__content {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.15rem;
}

.hcis-result-state--layout-inline:not(.has-description) .hcis-result-state__header-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 0.15rem;
    max-width: 100%;
}

.hcis-result-state--layout-inline:not(.has-description) .hcis-result-state__kicker {
    align-self: flex-start;
    margin-bottom: 0.05rem;
}

.hcis-result-state--layout-inline:not(.has-description) .hcis-result-state__title {
    text-align: left;
}

.hcis-result-state--layout-inline .hcis-result-state__kicker {
    font-size: 0.5875rem;
    font-weight: 800;
    letter-spacing: 0.05em;
    margin-bottom: 0;
    padding: 0.08rem 0.38rem;
    border-radius: var(--hcis-radius-pill, 9999px);
    display: inline-flex;
    align-items: center;
    background: rgb(var(--sentiment-500-rgb) / 0.12);
    color: var(--sentiment-600);
    border: 1px solid rgb(var(--sentiment-500-rgb) / 0.2);
    line-height: 1.2;
    flex-shrink: 0;
}

.hcis-result-state--layout-inline .hcis-result-state__title {
    font-size: 0.85rem;
    font-weight: 750;
    color: var(--hcis-slate-900);
    margin: 0;
    line-height: 1.3;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.hcis-result-state--layout-inline .hcis-result-state__description {
    font-size: 0.775rem;
    color: var(--hcis-slate-600);
    margin: 0;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

.hcis-result-state--layout-inline .hcis-result-state__actions {
    flex-shrink: 0;
    margin-left: auto;
    display: flex;
    flex-direction: row;
    align-items: center;
    gap: 0.4rem;
    width: auto;
}

.hcis-result-state--layout-inline .hcis-result-state__actions > * {
    width: auto !important;
    white-space: nowrap;
    padding: 0.25rem 0.65rem !important;
    font-size: 0.75rem !important;
    height: 30px !important;
}

/* Keyframes ----------------------------------------------------------------- */
@keyframes hcis-result-aura-pulse {
    0% { transform: translateX(-50%) scale(0.88); opacity: 0.26; }
    100% { transform: translateX(-50%) scale(1.22); opacity: 0.52; }
}

@keyframes hcis-result-ripple-expand {
    0% { transform: translate(-50%, -50%) scale(0.85); opacity: 0.65; }
    100% { transform: translate(-50%, -50%) scale(2.45); opacity: 0; }
}

@keyframes hcis-result-spring-enter {
    0% { transform: scale(0.2) rotate(-18deg); opacity: 0; }
    65% { transform: scale(1.12) rotate(3deg); opacity: 1; }
    100% { transform: scale(1) rotate(0deg); opacity: 1; }
}

@keyframes hcis-result-fade-up {
    0% { opacity: 0; transform: translate3d(0, 14px, 0); }
    100% { opacity: 1; transform: translate3d(0, 0, 0); }
}

@keyframes hcis-result-pop-up {
    0% { opacity: 0; transform: translate3d(0, 18px, 0) scale(0.96); }
    70% { transform: translate3d(0, -2px, 0) scale(1.01); }
    100% { opacity: 1; transform: translate3d(0, 0, 0) scale(1); }
}

@keyframes hcis-result-row-slide {
    0% { opacity: 0; transform: translate3d(0, 8px, 0); }
    100% { opacity: 1; transform: translate3d(0, 0, 0); }
}

@keyframes hcis-result-check-draw {
    0% { stroke-dashoffset: 48; }
    100% { stroke-dashoffset: 0; }
}

@keyframes hcis-result-cross-draw {
    0% { stroke-dashoffset: 36; }
    100% { stroke-dashoffset: 0; }
}

@keyframes hcis-inline-ripple-expand {
    0% { transform: translate(-50%, -50%) scale(0.95); opacity: 0.65; }
    100% { transform: translate(-50%, -50%) scale(1.75); opacity: 0; }
}

@keyframes hcis-inline-icon-breathe {
    0% { transform: scale(1); }
    100% { transform: scale(1.08); }
}

/* State Swap Transition ----------------------------------------------------- */
.hcis-state-swap-enter-active {
    transition: opacity 280ms var(--hcis-ease), transform 280ms var(--hcis-ease);
}

.hcis-state-swap-leave-active {
    transition: opacity 140ms ease-in, transform 140ms ease-in;
}

.hcis-state-swap-enter-from {
    opacity: 0;
    transform: scale(0.95) translateY(12px);
}

.hcis-state-swap-leave-to {
    opacity: 0;
    transform: scale(0.95) translateY(-12px);
}

/* ═══════════════════════════════════════════════════════════════════════════
   TABLET & MOBILE RESPONSIVE SUITE
   ═══════════════════════════════════════════════════════════════════════════ */

/* Tablet Optimization (641px – 1024px) -------------------------------------- */
@media (min-width: 641px) and (max-width: 1024px) {
    .hcis-result-state {
        padding: 1.65rem 1.25rem;
    }

    .hcis-result-state--glass,
    .hcis-result-state--card {
        padding: 1.75rem 1.35rem;
    }

    .hcis-result-state__aurora {
        width: 14rem;
        height: 14rem;
        filter: blur(32px);
    }

    .hcis-result-state__icon-container {
        margin-bottom: 0.85rem;
    }

    .hcis-result-state__icon-ring {
        width: 3.75rem;
        height: 3.75rem;
        border-width: 2.5px;
    }

    .hcis-result-state__icon-halo {
        width: 3.75rem;
        height: 3.75rem;
    }

    .hcis-result-state__svg-wrap {
        width: 2.15rem;
        height: 2.15rem;
    }

    .hcis-result-state__spinner {
        width: 2.45rem;
        height: 2.45rem;
    }

    .hcis-result-state__content {
        margin-bottom: 1rem;
    }

    .hcis-result-state__title {
        font-size: 1.2rem;
        margin-bottom: 0.25rem;
    }

    .hcis-result-state__description {
        font-size: 0.825rem;
        max-width: 28rem;
    }

    .hcis-result-state__receipt,
    .hcis-result-state__error-box {
        max-width: 26rem;
        padding: 0.75rem 1rem;
        margin-bottom: 1.15rem;
        border-radius: var(--hcis-radius-xl);
    }

    .hcis-result-state__actions {
        gap: 0.625rem;
    }

    .hcis-result-state--layout-compact {
        padding: 0.95rem 1.15rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__body {
        gap: 0.85rem;
        flex-wrap: wrap;
    }

    .hcis-result-state--layout-compact .hcis-result-state__icon-ring {
        width: 2.5rem;
        height: 2.5rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__content {
        min-width: 180px;
        flex: 1;
        margin-bottom: 0;
    }

    .hcis-result-state--layout-compact .hcis-result-state__title {
        font-size: 0.9rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__description {
        font-size: 0.75rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__actions {
        margin-left: auto;
        width: auto !important;
        flex-direction: row !important;
    }

    .hcis-result-state--layout-compact .hcis-result-state__actions > * {
        width: auto !important;
    }

    .hcis-result-state--layout-inline {
        padding: 0.5rem 0.85rem;
        min-height: 44px;
    }

    .hcis-result-state--layout-inline .hcis-result-state__body {
        gap: 0.65rem;
        flex-wrap: nowrap;
    }

    .hcis-result-state--layout-inline .hcis-result-state__content {
        gap: 0.35rem;
        flex-wrap: nowrap;
    }

    .hcis-result-state--layout-inline .hcis-result-state__actions {
        width: auto !important;
        flex-direction: row !important;
    }

    .hcis-result-state--layout-inline .hcis-result-state__actions > * {
        width: auto !important;
    }
}

/* Mobile Smartphone Optimization (< 640px) ----------------------------------- */
@media (max-width: 640px) {
    .hcis-result-state {
        padding: 1.15rem 0.75rem;
        border-radius: var(--hcis-radius-xl, 14px);
    }

    .hcis-result-state--glass,
    .hcis-result-state--card {
        padding: 1.25rem 0.85rem;
        border-radius: var(--hcis-radius-xl, 14px);
    }

    .hcis-result-state__aurora {
        width: 9rem;
        height: 9rem;
        filter: blur(24px);
        opacity: 0.4;
    }

    .hcis-result-state__icon-container {
        margin-bottom: 0.65rem;
    }

    .hcis-result-state__icon-ring {
        width: 3.25rem;
        height: 3.25rem;
        border-width: 2px;
        box-shadow: 0 6px 14px -3px rgb(var(--sentiment-500-rgb) / 0.35);
    }

    .hcis-result-state__icon-halo {
        width: 3.25rem;
        height: 3.25rem;
    }

    .hcis-result-state__svg-wrap {
        width: 1.85rem;
        height: 1.85rem;
    }

    .hcis-result-state__spinner {
        width: 2.15rem;
        height: 2.15rem;
    }

    .hcis-result-state__content {
        margin-bottom: 0.85rem;
        padding: 0 0.15rem;
    }

    .hcis-result-state__kicker {
        font-size: 0.575rem;
        font-weight: 800;
        letter-spacing: 0.06em;
        margin-bottom: 0.2rem;
        padding: 0.08rem 0.38rem;
        border-radius: var(--hcis-radius-pill);
        line-height: 1.2;
    }

    .hcis-result-state__title {
        font-size: 1.05rem;
        font-weight: 800;
        margin-bottom: 0.2rem;
        line-height: 1.3;
    }

    .hcis-result-state__description {
        font-size: 0.775rem;
        line-height: 1.45;
        max-width: 96%;
        margin: 0 auto;
    }

    /* Mobile Fintech Digital Receipt Voucher Card */
    .hcis-result-state__receipt {
        padding: 0.65rem 0.85rem;
        margin-bottom: 0.85rem;
        border-radius: var(--hcis-radius-lg, 10px);
        gap: 0.35rem;
        box-shadow: 0 4px 12px -2px rgb(var(--sentiment-500-rgb) / 0.12);
    }

    .hcis-receipt-header {
        padding-bottom: 0.35rem;
        margin-bottom: 0.1rem;
    }

    .hcis-receipt-header-title {
        font-size: 0.65rem;
        font-weight: 750;
        letter-spacing: 0.04em;
    }

    .hcis-receipt-status-badge {
        font-size: 0.55rem;
        font-weight: 800;
        padding: 0.06rem 0.32rem;
        border-radius: var(--hcis-radius-pill);
        letter-spacing: 0.03em;
        line-height: 1.2;
    }

    .hcis-result-state__receipt-row {
        font-size: 0.75rem;
        padding: 0.25rem 0;
        gap: 0.25rem;
    }

    .hcis-receipt-label {
        font-size: 0.725rem;
    }

    .hcis-receipt-value {
        font-size: 0.75rem;
    }

    .hcis-receipt-value.is-mono {
        font-size: 0.625rem;
        font-weight: 700;
        padding: 0.06rem 0.28rem;
        border-radius: 3px;
        line-height: 1.2;
    }

    .hcis-copy-btn {
        font-size: 0.575rem;
        padding: 0.08rem 0.28rem;
        border-radius: 3px;
        min-height: 20px;
    }

    /* Mobile Diagnostic Error Console Box */
    .hcis-result-state__error-box {
        padding: 0.65rem 0.85rem;
        margin-bottom: 0.85rem;
        border-radius: var(--hcis-radius-lg, 10px);
        gap: 0.45rem;
        box-shadow: 0 4px 12px -2px rgb(var(--hcis-danger-500-rgb) / 0.12);
    }

    .hcis-error-header {
        padding-bottom: 0.35rem;
        margin-bottom: 0.1rem;
        gap: 0.4rem;
    }

    .hcis-error-title {
        font-size: 0.6875rem;
    }

    .hcis-error-copy-btn {
        font-size: 0.575rem;
        padding: 0.08rem 0.3rem;
        min-height: 20px;
    }

    .hcis-error-meta-grid {
        gap: 0.25rem;
    }

    .hcis-error-meta-row {
        font-size: 0.725rem;
        padding-bottom: 0.25rem;
        gap: 0.35rem;
    }

    .hcis-error-meta-label {
        font-size: 0.6875rem;
    }

    .hcis-error-code-badge {
        font-size: 0.575rem;
        font-weight: 750;
        padding: 0.06rem 0.32rem;
        border-radius: var(--hcis-radius-pill);
        line-height: 1.2;
    }

    .hcis-error-meta-val {
        font-size: 0.575rem;
        font-weight: 600;
        padding: 0.06rem 0.28rem;
        border-radius: 3px;
        line-height: 1.2;
    }

    .hcis-error-console-box {
        padding: 0.4rem 0.55rem;
        margin-top: 0.1rem;
        border-radius: 6px;
    }

    .hcis-error-console-header {
        font-size: 0.6rem;
        margin-bottom: 0.2rem;
    }

    .hcis-error-code {
        font-size: 0.65rem;
        line-height: 1.35;
        max-height: 85px;
        padding: 0;
    }

    /* Mobile Action Buttons Stack for Centered Cards Only */
    .hcis-result-state:not(.hcis-result-state--layout-inline):not(.hcis-result-state--layout-compact) .hcis-result-state__actions {
        width: 100%;
        display: flex;
        flex-direction: column;
        gap: 0.45rem;
    }

    .hcis-result-state:not(.hcis-result-state--layout-inline):not(.hcis-result-state--layout-compact) .hcis-result-state__actions > * {
        width: 100%;
        justify-content: center;
    }

    /* Mobile Layout: Compact (Header-style Grid: Title beside icon, Description full-width below) */
    .hcis-result-state--layout-compact {
        padding: 0.85rem 0.85rem;
        border-radius: var(--hcis-radius-lg, 10px);
        text-align: left;
    }

    .hcis-result-state--layout-compact .hcis-result-state__body {
        display: grid;
        grid-template-columns: auto 1fr;
        align-items: center;
        gap: 0.45rem 0.75rem;
        width: 100%;
        text-align: left;
    }

    .hcis-result-state--layout-compact .hcis-result-state__icon-container {
        grid-column: 1;
        grid-row: 1;
        align-self: center;
        margin: 0;
        flex-shrink: 0;
    }

    .hcis-result-state--layout-compact .hcis-result-state__icon-ring {
        width: 2.35rem;
        height: 2.35rem;
        border-width: 2px;
        box-shadow: 0 4px 10px -2px rgb(var(--sentiment-500-rgb) / 0.3);
    }

    .hcis-result-state--layout-compact .hcis-result-state__icon-halo {
        width: 2.35rem;
        height: 2.35rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__svg-wrap {
        width: 1.35rem;
        height: 1.35rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__spinner {
        width: 1.55rem;
        height: 1.55rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__content {
        display: contents;
    }

    .hcis-result-state--layout-compact .hcis-result-state__header-text {
        grid-column: 2;
        grid-row: 1;
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        gap: 0.1rem;
        min-width: 0;
    }

    .hcis-result-state--layout-compact .hcis-result-state__kicker {
        font-size: 0.55rem;
        padding: 0.06rem 0.3rem;
        margin-bottom: 0.05rem;
        display: inline-block;
        line-height: 1.2;
    }

    .hcis-result-state--layout-compact .hcis-result-state__title {
        font-size: 0.875rem;
        font-weight: 800;
        color: var(--hcis-slate-900);
        margin: 0;
        line-height: 1.25;
        text-align: left;
    }

    .hcis-result-state--layout-compact .hcis-result-state__description {
        grid-column: 1 / -1;
        grid-row: 2;
        font-size: 0.75rem;
        color: var(--hcis-slate-600);
        line-height: 1.45;
        margin: 0.15rem 0 0 0;
        text-align: left;
        width: 100%;
    }

    .hcis-result-state--layout-compact .hcis-result-state__receipt,
    .hcis-result-state--layout-compact .hcis-result-state__error-box {
        grid-column: 1 / -1;
        width: 100%;
        max-width: 100%;
        margin-top: 0.45rem;
        margin-bottom: 0.15rem;
        padding: 0.55rem 0.75rem;
    }

    .hcis-result-state--layout-compact .hcis-result-state__actions {
        grid-column: 1 / -1;
        width: 100% !important;
        margin-left: 0 !important;
        margin-top: 0.45rem !important;
        display: flex !important;
        flex-direction: row !important;
        justify-content: flex-end !important;
        gap: 0.4rem !important;
    }

    .hcis-result-state--layout-compact .hcis-result-state__actions > * {
        width: auto !important;
        height: 30px !important;
        font-size: 0.725rem !important;
        padding: 0.2rem 0.55rem !important;
    }

    /* Mobile Layout: True Inline Slim Banner */
    .hcis-result-state--layout-inline {
        padding: 0.45rem 0.75rem;
        border-radius: var(--hcis-radius-lg, 10px);
        min-height: 42px;
    }

    .hcis-result-state--layout-inline .hcis-result-state__body {
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 0.6rem !important;
        flex-wrap: nowrap !important;
        width: 100% !important;
    }

    .hcis-result-state--layout-inline .hcis-result-state__icon-container {
        margin: 0 !important;
        padding: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        align-self: center !important;
        flex-shrink: 0 !important;
    }

    .hcis-result-state--layout-inline .hcis-result-state__icon-ring {
        width: 1.85rem;
        height: 1.85rem;
        margin: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
    }

    .hcis-result-state--layout-inline .hcis-result-state__icon-halo {
        width: 1.85rem;
        height: 1.85rem;
    }

    .hcis-result-state--layout-inline .hcis-result-state__svg-wrap {
        width: 1.1rem;
        height: 1.1rem;
    }

    .hcis-result-state--layout-inline .hcis-result-state__content {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
        justify-content: center;
        align-self: center;
        gap: 0.08rem;
        flex: 1;
        min-width: 0;
        overflow: hidden;
    }

    .hcis-result-state--layout-inline .hcis-result-state__kicker {
        font-size: 0.55rem;
        padding: 0.06rem 0.3rem;
        line-height: 1.2;
    }

    .hcis-result-state--layout-inline .hcis-result-state__title {
        font-size: 0.775rem;
        font-weight: 750;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .hcis-result-state--layout-inline .hcis-result-state__description {
        font-size: 0.6875rem;
        line-height: 1.25;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .hcis-result-state--layout-inline .hcis-result-state__actions {
        width: auto !important;
        margin-left: auto !important;
        margin-top: 0 !important;
        display: flex !important;
        flex-direction: row !important;
        align-items: center !important;
        justify-content: center !important;
        flex-shrink: 0 !important;
        gap: 0.3rem !important;
    }

    .hcis-result-state--layout-inline .hcis-result-state__actions .hcis-btn__text,
    .hcis-result-state--layout-inline .hcis-result-state__actions .hcis-btn-label {
        display: none !important;
    }

    .hcis-result-state--layout-inline .hcis-result-state__actions .hcis-btn,
    .hcis-result-state--layout-inline .hcis-result-state__actions > * {
        width: 28px !important;
        min-width: 28px !important;
        max-width: 28px !important;
        height: 28px !important;
        min-height: 28px !important;
        max-height: 28px !important;
        padding: 0 !important;
        margin: 0 !important;
        gap: 0 !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        border-radius: var(--hcis-radius-pill, 9999px) !important;
        box-sizing: border-box !important;
        line-height: 1 !important;
    }

    .hcis-result-state--layout-inline .hcis-result-state__actions .hcis-btn .hcis-btn__icon,
    .hcis-result-state--layout-inline .hcis-result-state__actions .hcis-btn i {
        margin: 0 !important;
        padding: 0 !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        font-size: 0.85rem !important;
        line-height: 1 !important;
        width: 100% !important;
        height: 100% !important;
        text-align: center !important;
    }
}
</style>
