<template>
    <div
        ref="triggerRef"
        class="hcis-tooltip-wrapper"
        @mouseenter="handleMouseEnter"
        @mouseleave="handleMouseLeave"
        @focusin="handleFocusIn"
        @focusout="handleFocusOut"
        @click="handleClick"
    >
        <slot />

        <Teleport :to="teleportTo" :disabled="!teleportTo">
            <Transition name="hcis-tooltip-pop">
                <div
                    v-if="isVisible"
                    ref="popperRef"
                    class="hcis-tooltip"
                    :class="[
                        `hcis-tooltip--${computedTheme}`,
                        `hcis-tooltip--${currentPlacement}`,
                        { 'hcis-tooltip--has-header': title || icon || $slots.title || $slots.header },
                        customClass,
                    ]"
                    :style="popperStyle"
                    role="tooltip"
                    @mouseenter="handlePopperMouseEnter"
                    @mouseleave="handlePopperMouseLeave"
                >
                    <!-- Header Title Bar (Bila Memiliki Title/Icon/Slot) -->
                    <div v-if="title || icon || $slots.title || $slots.header" class="hcis-tooltip__header">
                        <slot name="header">
                            <div class="hcis-tooltip__title-row">
                                <i v-if="icon" :class="icon" class="hcis-tooltip__icon" aria-hidden="true" />
                                <slot name="title">
                                    <span class="hcis-tooltip__title">{{ title }}</span>
                                </slot>
                            </div>
                        </slot>
                    </div>

                    <!-- Body Content -->
                    <div class="hcis-tooltip__content">
                        <slot name="content">
                            <span v-if="rawContent" v-html="content" />
                            <span v-else>{{ content }}</span>
                        </slot>
                    </div>

                    <!-- Footer Action Bar (Bila Memiliki Slot Footer) -->
                    <div v-if="$slots.footer" class="hcis-tooltip__footer">
                        <slot name="footer" />
                    </div>

                    <!-- Arrow Pointer -->
                    <div v-if="showArrow" class="hcis-tooltip__arrow" />
                </div>
            </Transition>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    icon: { type: String, default: '' },
    content: { type: String, default: '' },
    placement: { type: String, default: 'top' },
    effect: { type: String, default: 'dark' }, // dark | light
    theme: { type: String, default: '' }, // brand | glass | danger | success | warning | dark | light
    trigger: { type: String, default: 'hover' }, // hover | click | focus | manual
    disabled: { type: Boolean, default: false },
    visible: { type: Boolean, default: null }, // v-model / manual control
    modelValue: { type: Boolean, default: null },
    enterable: { type: Boolean, default: true },
    showAfter: { type: Number, default: 80 }, // ms show delay
    hideAfter: { type: Number, default: 100 }, // ms hide delay
    rawContent: { type: Boolean, default: false },
    showArrow: { type: Boolean, default: true },
    maxWidth: { type: String, default: '20rem' },
    teleportTo: { type: [String, Boolean], default: 'body' },
    customClass: { type: String, default: '' },
    offset: { type: Number, default: 8 },
});

const emit = defineEmits(['update:visible', 'update:modelValue', 'show', 'hide']);

const triggerRef = ref(null);
const popperRef = ref(null);
const internalVisible = ref(false);

const currentPlacement = ref(props.placement);
const popperPosition = ref({ top: 0, left: 0 });

let showTimer = null;
let hideTimer = null;

const isManual = computed(() => props.trigger === 'manual' || props.visible !== null || props.modelValue !== null);

const isVisible = computed(() => {
    if (props.disabled) return false;
    if (isManual.value) {
        return props.visible !== null ? props.visible : Boolean(props.modelValue);
    }
    return internalVisible.value;
});

const computedTheme = computed(() => {
    if (props.theme) return props.theme;
    if (props.effect === 'light') return 'light';
    return 'dark';
});

const popperStyle = computed(() => ({
    top: `${popperPosition.value.top}px`,
    left: `${popperPosition.value.left}px`,
    maxWidth: props.maxWidth,
}));

const updatePosition = () => {
    if (!triggerRef.value) return;

    const triggerRect = triggerRef.value.getBoundingClientRect();
    const scrollX = window.scrollX || window.pageXOffset;
    const scrollY = window.scrollY || window.pageYOffset;

    let popperWidth = 160;
    let popperHeight = 40;

    if (popperRef.value) {
        const popperRect = popperRef.value.getBoundingClientRect();
        popperWidth = popperRect.width;
        popperHeight = popperRect.height;
    }

    const gap = props.offset;
    let top = 0;
    let left = 0;

    let targetPlacement = props.placement;
    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    // Automatic Flip logic if overflow viewport
    if (targetPlacement.startsWith('top') && triggerRect.top - popperHeight - gap < 0) {
        targetPlacement = targetPlacement.replace('top', 'bottom');
    } else if (targetPlacement.startsWith('bottom') && triggerRect.bottom + popperHeight + gap > viewportHeight) {
        targetPlacement = targetPlacement.replace('bottom', 'top');
    }

    currentPlacement.value = targetPlacement;

    switch (targetPlacement) {
        case 'top':
            top = triggerRect.top + scrollY - popperHeight - gap;
            left = triggerRect.left + scrollX + triggerRect.width / 2 - popperWidth / 2;
            break;
        case 'top-start':
            top = triggerRect.top + scrollY - popperHeight - gap;
            left = triggerRect.left + scrollX;
            break;
        case 'top-end':
            top = triggerRect.top + scrollY - popperHeight - gap;
            left = triggerRect.left + scrollX + triggerRect.width - popperWidth;
            break;
        case 'bottom':
            top = triggerRect.bottom + scrollY + gap;
            left = triggerRect.left + scrollX + triggerRect.width / 2 - popperWidth / 2;
            break;
        case 'bottom-start':
            top = triggerRect.bottom + scrollY + gap;
            left = triggerRect.left + scrollX;
            break;
        case 'bottom-end':
            top = triggerRect.bottom + scrollY + gap;
            left = triggerRect.left + scrollX + triggerRect.width - popperWidth;
            break;
        case 'left':
            top = triggerRect.top + scrollY + triggerRect.height / 2 - popperHeight / 2;
            left = triggerRect.left + scrollX - popperWidth - gap;
            break;
        case 'right':
            top = triggerRect.top + scrollY + triggerRect.height / 2 - popperHeight / 2;
            left = triggerRect.right + scrollX + gap;
            break;
        default:
            top = triggerRect.top + scrollY - popperHeight - gap;
            left = triggerRect.left + scrollX + triggerRect.width / 2 - popperWidth / 2;
    }

    popperPosition.value = {
        top: Math.round(top),
        left: Math.round(left),
    };
};

const showTooltip = () => {
    clearTimeout(hideTimer);
    if (props.disabled) return;

    showTimer = setTimeout(() => {
        internalVisible.value = true;
        emit('update:visible', true);
        emit('update:modelValue', true);
        emit('show');
        nextTick(() => {
            updatePosition();
            requestAnimationFrame(updatePosition);
        });
    }, props.showAfter);
};

const hideTooltip = () => {
    clearTimeout(showTimer);
    hideTimer = setTimeout(() => {
        internalVisible.value = false;
        emit('update:visible', false);
        emit('update:modelValue', false);
        emit('hide');
    }, props.hideAfter);
};

// Mouse Event Handlers
const handleMouseEnter = () => {
    if (props.trigger === 'hover') showTooltip();
};

const handleMouseLeave = () => {
    if (props.trigger === 'hover') hideTooltip();
};

const handlePopperMouseEnter = () => {
    if (props.trigger === 'hover' && props.enterable) {
        clearTimeout(hideTimer);
    }
};

const handlePopperMouseLeave = () => {
    if (props.trigger === 'hover' && props.enterable) {
        hideTooltip();
    }
};

const handleFocusIn = () => {
    if (props.trigger === 'focus') showTooltip();
};

const handleFocusOut = () => {
    if (props.trigger === 'focus') hideTooltip();
};

const handleClick = () => {
    if (props.trigger === 'click') {
        if (isVisible.value) {
            hideTooltip();
        } else {
            showTooltip();
        }
    }
};

const handleScrollOrResize = () => {
    if (isVisible.value) {
        updatePosition();
    }
};

watch(isVisible, (val) => {
    if (val) {
        nextTick(() => {
            updatePosition();
            window.addEventListener('scroll', handleScrollOrResize, true);
            window.addEventListener('resize', handleScrollOrResize);
        });
    } else {
        window.removeEventListener('scroll', handleScrollOrResize, true);
        window.removeEventListener('resize', handleScrollOrResize);
    }
});

onMounted(() => {
    if (isVisible.value) {
        updatePosition();
    }
});

onUnmounted(() => {
    clearTimeout(showTimer);
    clearTimeout(hideTimer);
    window.removeEventListener('scroll', handleScrollOrResize, true);
    window.removeEventListener('resize', handleScrollOrResize);
});
</script>

<style scoped>
.hcis-tooltip-wrapper {
    display: inline-block;
    max-width: 100%;
}
</style>

<style>
/* Unscoped Floating Tooltip Popper Styles OVERPOWERED */
.hcis-tooltip {
    position: absolute;
    z-index: var(--hcis-z-popover);
    padding: 10px 14px;
    border-radius: var(--hcis-radius-xl);
    font-size: 0.8125rem;
    font-weight: 500;
    line-height: 1.5;
    word-break: break-word;
    pointer-events: auto;
    box-shadow: 0 20px 35px -5px rgb(var(--hcis-slate-900-rgb) / 0.25), 0 8px 12px -3px rgb(var(--hcis-slate-900-rgb) / 0.1);
}

.hcis-tooltip__header {
    margin-bottom: 6px;
    padding-bottom: 6px;
    border-bottom: 1px solid rgb(var(--hcis-white-rgb) / 0.12);
}
.hcis-tooltip--light .hcis-tooltip__header,
.hcis-tooltip--glass .hcis-tooltip__header {
    border-bottom-color: rgb(var(--hcis-slate-300-rgb) / 0.6);
}

.hcis-tooltip__title-row {
    display: flex;
    align-items: center;
    gap: 8px;
}

.hcis-tooltip__icon {
    font-size: 0.95rem;
    color: var(--hcis-brand-400, var(--hcis-brand-400));
}
.hcis-tooltip--light .hcis-tooltip__icon {
    color: var(--hcis-brand-600, var(--hcis-brand-600));
}

.hcis-tooltip__title {
    font-size: 0.84375rem;
    font-weight: 700;
    letter-spacing: -0.01em;
}

.hcis-tooltip__content {
    position: relative;
    z-index: 2;
}

.hcis-tooltip__footer {
    margin-top: 8px;
    padding-top: 6px;
    border-top: 1px solid rgb(var(--hcis-white-rgb) / 0.12);
    font-size: 0.75rem;
}
.hcis-tooltip--light .hcis-tooltip__footer,
.hcis-tooltip--glass .hcis-tooltip__footer {
    border-top-color: rgb(var(--hcis-slate-300-rgb) / 0.6);
}

/* Arrow Pointer Base */
.hcis-tooltip__arrow {
    position: absolute;
    width: 8px;
    height: 8px;
    background: inherit;
    transform: rotate(45deg);
    z-index: 1;
}

/* Placement Arrow Positioning */
.hcis-tooltip--top .hcis-tooltip__arrow,
.hcis-tooltip--top-start .hcis-tooltip__arrow,
.hcis-tooltip--top-end .hcis-tooltip__arrow {
    bottom: -4px;
}
.hcis-tooltip--top .hcis-tooltip__arrow { left: calc(50% - 4px); }
.hcis-tooltip--top-start .hcis-tooltip__arrow { left: 12px; }
.hcis-tooltip--top-end .hcis-tooltip__arrow { right: 12px; }

.hcis-tooltip--bottom .hcis-tooltip__arrow,
.hcis-tooltip--bottom-start .hcis-tooltip__arrow,
.hcis-tooltip--bottom-end .hcis-tooltip__arrow {
    top: -4px;
}
.hcis-tooltip--bottom .hcis-tooltip__arrow { left: calc(50% - 4px); }
.hcis-tooltip--bottom-start .hcis-tooltip__arrow { left: 12px; }
.hcis-tooltip--bottom-end .hcis-tooltip__arrow { right: 12px; }

.hcis-tooltip--left .hcis-tooltip__arrow {
    right: -4px;
    top: calc(50% - 4px);
}

.hcis-tooltip--right .hcis-tooltip__arrow {
    left: -4px;
    top: calc(50% - 4px);
}

/* Themes & Sentiments */
.hcis-tooltip--dark {
    background-color: rgb(var(--hcis-slate-900-rgb) / 0.94);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    color: var(--hcis-slate-50);
    border: 1px solid rgb(var(--hcis-white-rgb) / 0.15);
}

.hcis-tooltip--light {
    background-color: rgb(var(--hcis-white-rgb) / 0.96);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    color: var(--hcis-slate-900);
    border: 1px solid var(--hcis-border-subtle, var(--hcis-slate-300));
    box-shadow: 0 20px 40px -5px rgb(var(--hcis-slate-900-rgb) / 0.18);
}

.hcis-tooltip--glass {
    background: rgb(var(--hcis-white-rgb) / 0.88);
    backdrop-filter: blur(var(--hcis-glass-blur));
    -webkit-backdrop-filter: blur(var(--hcis-glass-blur));
    color: var(--hcis-slate-900);
    border: 1px solid rgb(var(--hcis-white-rgb) / 0.7);
    box-shadow: 0 20px 40px -5px rgb(var(--hcis-brand-600-rgb) / 0.2), inset 0 1px 0 rgb(var(--hcis-white-rgb) / 0.9);
}

.hcis-tooltip--brand {
    background: linear-gradient(135deg, var(--hcis-brand-600), var(--hcis-brand-700));
    backdrop-filter: blur(var(--hcis-glass-blur));
    color: var(--hcis-white);
    border: 1px solid rgb(var(--hcis-white-rgb) / 0.2);
}

.hcis-tooltip--danger {
    background: linear-gradient(135deg, var(--hcis-danger-500), var(--hcis-danger-600));
    color: var(--hcis-white);
}

.hcis-tooltip--success {
    background: linear-gradient(135deg, var(--hcis-success-500), var(--hcis-success-600));
    color: var(--hcis-white);
}

.hcis-tooltip--warning {
    background: linear-gradient(135deg, var(--hcis-warning-500), var(--hcis-warning-600));
    color: var(--hcis-white);
}

/* 60FPS Micro Animation Pop-in & Out */
.hcis-tooltip-pop-enter-active,
.hcis-tooltip-pop-leave-active {
    transition: opacity 160ms var(--hcis-ease), transform 160ms cubic-bezier(0.34, 1.45, 0.64, 1);
    will-change: opacity, transform;
}

.hcis-tooltip-pop-enter-from,
.hcis-tooltip-pop-leave-to {
    opacity: 0;
    transform: scale(0.92);
}
</style>
