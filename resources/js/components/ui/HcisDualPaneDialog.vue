<template>
    <HcisDialog
        :model-value="modelValue"
        :title="title"
        :kicker="kicker"
        :description="description"
        :icon="icon"
        :icon-variant="iconVariant"
        :sentiment="sentiment"
        :variant="variant"
        :pattern="pattern"
        :pattern-placement="patternPlacement"
        :size="size"
        :sticky-footer="stickyFooter"
        :close-on-overlay="!loading && closeOnOverlay"
        :close-on-esc="!loading && closeOnEsc"
        :show-close="!loading && showClose"
        :header-divider="headerDivider"
        :badge="badge"
        :badge-variant="badgeVariant"
        :scrollable="true"
        class="hcis-dual-pane-dialog"
        :style="dialogStyles"
        @update:model-value="$emit('update:modelValue', $event)"
        @close="$emit('close')"
    >
        <!-- Forward Header Slots -->
        <template v-if="$slots.header" #header>
            <slot name="header" />
        </template>
        <template v-if="$slots.leading" #leading>
            <slot name="leading" />
        </template>
        <template v-if="$slots.title" #title>
            <slot name="title" />
        </template>
        <template v-if="$slots.badge" #badge>
            <slot name="badge" />
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="hcis-dual-pane__loading">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">{{ loadingText }}</span>
            </div>
            <p class="hcis-dual-pane__loading-text">{{ loadingText }}</p>
        </div>

        <!-- Two-Pane Split Layout -->
        <div v-else class="hcis-dual-pane__layout">
            <!-- Mobile Segmented Tab Switcher (< 992px) -->
            <div class="hcis-dual-pane__mobile-tabs d-flex d-lg-none">
                <button
                    type="button"
                    class="hcis-dual-pane__tab-btn"
                    :class="{ 'is-active': activeMobileTab === 'left' }"
                    @click="activeMobileTab = 'left'"
                >
                    <HcisIcon v-if="leftTabIcon" :name="leftTabIcon" size="xs" />
                    <span>{{ leftTabLabel }}</span>
                    <span v-if="leftTabBadge" class="hcis-dual-pane__tab-badge">{{ leftTabBadge }}</span>
                </button>
                <button
                    type="button"
                    class="hcis-dual-pane__tab-btn"
                    :class="{ 'is-active': activeMobileTab === 'right' }"
                    @click="activeMobileTab = 'right'"
                >
                    <HcisIcon v-if="rightTabIcon" :name="rightTabIcon" size="xs" />
                    <span>{{ rightTabLabel }}</span>
                    <span v-if="rightTabBadge" class="hcis-dual-pane__tab-badge">{{ rightTabBadge }}</span>
                </button>
            </div>

            <div class="hcis-dual-pane__grid">
                <!-- Left / Aside Pane -->
                <div
                    class="hcis-dual-pane__pane hcis-dual-pane__pane--left"
                    :class="{ 'd-none d-lg-block': activeMobileTab !== 'left' }"
                >
                    <slot name="left" />
                </div>

                <!-- Right / Main Pane -->
                <div
                    class="hcis-dual-pane__pane hcis-dual-pane__pane--right"
                    :class="{ 'd-none d-lg-block': activeMobileTab !== 'right' }"
                >
                    <slot name="right">
                        <slot />
                    </slot>
                </div>
            </div>
        </div>

        <!-- Forward Footer Slots -->
        <template v-if="$slots.footer || $slots['footer-end'] || $slots['footer-right'] || $slots['footer-start'] || $slots['footer-left']" #footer>
            <div class="hcis-dual-pane__footer-wrapper w-100">
                <div v-if="$slots['footer-start'] || $slots['footer-left']" class="hcis-dual-pane__footer-start">
                    <slot name="footer-start">
                        <slot name="footer-left" />
                    </slot>
                </div>
                <div class="hcis-dual-pane__footer-end">
                    <slot name="footer">
                        <slot name="footer-end">
                            <slot name="footer-right" />
                        </slot>
                    </slot>
                </div>
            </div>
        </template>
    </HcisDialog>
</template>

<script>
import HcisDialog from './HcisDialog.vue';
import HcisIcon from './HcisIcon.vue';

export default {
    name: 'HcisDualPaneDialog',
    components: {
        HcisDialog,
        HcisIcon,
    },
    props: {
        modelValue: {
            type: Boolean,
            default: false,
        },
        title: {
            type: String,
            default: '',
        },
        kicker: {
            type: String,
            default: '',
        },
        description: {
            type: String,
            default: '',
        },
        icon: {
            type: String,
            default: '',
        },
        iconVariant: {
            type: String,
            default: 'glass',
        },
        sentiment: {
            type: String,
            default: 'brand',
        },
        variant: {
            type: String,
            default: 'glass',
        },
        pattern: {
            type: String,
            default: 'grid',
        },
        patternPlacement: {
            type: String,
            default: 'header',
        },
        size: {
            type: String,
            default: 'xl',
        },
        leftWidth: {
            type: String,
            default: '390px',
        },
        leftTabLabel: {
            type: String,
            default: 'Identitas & Status',
        },
        leftTabIcon: {
            type: String,
            default: 'person-badge',
        },
        leftTabBadge: {
            type: [Number, String],
            default: '',
        },
        rightTabLabel: {
            type: String,
            default: 'Rincian & Dokumen',
        },
        rightTabIcon: {
            type: String,
            default: 'file-earmark-text',
        },
        rightTabBadge: {
            type: [Number, String],
            default: '',
        },
        stickyFooter: {
            type: Boolean,
            default: true,
        },
        headerDivider: {
            type: Boolean,
            default: true,
        },
        badge: {
            type: String,
            default: '',
        },
        badgeVariant: {
            type: String,
            default: '',
        },
        loading: {
            type: Boolean,
            default: false,
        },
        loadingText: {
            type: String,
            default: 'Memuat data...',
        },
        closeOnOverlay: {
            type: Boolean,
            default: true,
        },
        closeOnEsc: {
            type: Boolean,
            default: true,
        },
        showClose: {
            type: Boolean,
            default: true,
        },
    },
    emits: ['update:modelValue', 'close'],
    data() {
        return {
            activeMobileTab: 'left',
        };
    },
    computed: {
        dialogStyles() {
            return {
                '--hcis-dual-left-width': this.leftWidth,
            };
        },
    },
    watch: {
        modelValue(val) {
            if (val) {
                this.activeMobileTab = 'left';
            }
        },
    },
};
</script>

<style scoped>
/* =========================================================================
   HCIS DUAL PANE DIALOG (ENTERPRISE SUBMISSION & DETAIL MODAL)
   ========================================================================= */
.hcis-dual-pane__loading {
    padding: var(--hcis-space-12, 3rem) var(--hcis-space-6, 1.5rem);
    text-align: center;
}

.hcis-dual-pane__loading-text {
    margin-top: var(--hcis-space-3, 0.75rem);
    color: var(--hcis-text-muted, var(--hcis-slate-600));
    font-weight: 600;
    font-size: var(--hcis-font-size-sm, 0.875rem);
}

.hcis-dual-pane__layout {
    width: 100%;
    contain: layout style;
}

@media (min-width: 992px) {
    :deep(.hcis-dialog__body) {
        overflow-y: hidden !important;
        padding: var(--hcis-space-4, 1rem) var(--hcis-space-5, 1.25rem);
    }

    .hcis-dual-pane__grid {
        display: grid;
        grid-template-columns: var(--hcis-dual-left-width, 390px) 1fr;
        gap: var(--hcis-space-6, 1.5rem);
        height: 100%;
        max-height: calc(85vh - 180px);
        overflow: hidden;
    }

    .hcis-dual-pane__pane {
        height: 100%;
        max-height: calc(85vh - 180px);
        overflow-y: auto;
        overflow-x: hidden;
        padding-right: var(--hcis-space-2, 0.5rem);
        scrollbar-width: thin;
        scrollbar-color: var(--hcis-border-subtle) transparent;
        contain: content;
        transform: translateZ(0);
        will-change: scroll-position;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
    }

    .hcis-dual-pane__pane::-webkit-scrollbar {
        width: 6px;
    }

    .hcis-dual-pane__pane::-webkit-scrollbar-track {
        background: transparent;
    }

    .hcis-dual-pane__pane::-webkit-scrollbar-thumb {
        background-color: var(--hcis-border-subtle);
        border-radius: var(--hcis-radius-full, 9999px);
    }

    .hcis-dual-pane__pane::-webkit-scrollbar-thumb:hover {
        background-color: var(--hcis-text-subtle, var(--hcis-slate-400));
    }

    .hcis-dual-pane__footer-wrapper {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
        gap: 0.75rem;
    }

    .hcis-dual-pane__footer-start {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .hcis-dual-pane__footer-end {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-left: auto;
    }
}

@media (max-width: 991.98px) {
    :deep(.hcis-dialog-overlay) {
        padding: 0 !important;
        align-items: stretch !important;
    }

    :deep(.hcis-dialog) {
        height: 100dvh !important;
        max-height: 100dvh !important;
        min-height: 100dvh !important;
        width: 100vw !important;
        max-width: 100vw !important;
        min-width: 100vw !important;
        border-radius: 0 !important;
        margin: 0 !important;
        border: none !important;
        display: flex !important;
        flex-direction: column !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        box-shadow: none !important;
    }

    :deep(.hcis-dialog__mobile-handle-wrap) {
        display: none !important;
    }

    :deep(.hcis-dialog__header) {
        padding: 0.65rem 0.85rem !important;
        flex-shrink: 0 !important;
        border-bottom: 1px solid var(--hcis-slate-200, var(--hcis-slate-200)) !important;
    }

    :deep(.hcis-dialog__description) {
        display: none !important;
    }

    :deep(.hcis-dialog__body) {
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch !important;
        flex: 1 1 auto !important;
        min-height: 0 !important;
        max-height: none !important;
        padding: 0.65rem 0.75rem !important;
    }

    :deep(.hcis-card__description) {
        display: none !important;
    }

    :deep(.hcis-dialog__footer-actions) {
        width: 100% !important;
        flex: 1 1 auto !important;
        display: flex !important;
        align-items: center !important;
        gap: 0.5rem !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .hcis-dual-pane__footer-wrapper {
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        gap: 0.5rem !important;
        margin: 0 !important;
        padding: 0 !important;
    }

    .hcis-dual-pane__footer-start {
        display: flex !important;
        align-items: center !important;
    }

    .hcis-dual-pane__footer-end {
        display: flex !important;
        align-items: center !important;
        width: 100% !important;
        flex: 1 1 auto !important;
        gap: 0.5rem !important;
        margin-left: 0 !important;
        padding: 0 !important;
    }

    .hcis-dual-pane__layout {
        width: 100% !important;
        height: auto !important;
    }

    .hcis-dual-pane__mobile-tabs {
        display: flex;
        align-items: center;
        background: var(--hcis-slate-100, var(--hcis-slate-100));
        border: 1px solid var(--hcis-slate-200, var(--hcis-slate-200));
        border-radius: 10px;
        padding: 3px;
        margin-bottom: 0.65rem;
        gap: 3px;
        box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.04);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .hcis-dual-pane__tab-btn {
        flex: 1 1 0%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.35rem;
        height: 2.15rem;
        border: 0;
        border-radius: 8px;
        background: transparent;
        color: var(--hcis-slate-600, var(--hcis-slate-600));
        font-family: inherit;
        font-size: 0.75rem;
        font-weight: 750;
        cursor: pointer;
        transition-property: var(--hcis-transition-props);
        transition-duration: 180ms;
        transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
        user-select: none;
        -webkit-tap-highlight-color: transparent;
    }

    .hcis-dual-pane__tab-btn:hover {
        color: var(--hcis-slate-900, var(--hcis-slate-900));
    }

    .hcis-dual-pane__tab-btn.is-active {
        background: var(--hcis-white);
        color: var(--hcis-brand-700, var(--hcis-indigo-700));
        box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.08), 0 0 0 1px rgba(226, 232, 240, 0.8);
    }

    .hcis-dual-pane__tab-badge {
        display: inline-grid;
        place-items: center;
        min-width: 1.15rem;
        height: 1.15rem;
        padding: 0 0.25rem;
        border-radius: 9999px;
        background: var(--hcis-brand-50, var(--hcis-indigo-50));
        color: var(--hcis-brand-700, var(--hcis-indigo-700));
        border: 1px solid var(--hcis-brand-200, var(--hcis-indigo-200));
        font-size: 0.625rem;
        font-weight: 800;
        line-height: 1;
    }

    .hcis-dual-pane__tab-btn.is-active .hcis-dual-pane__tab-badge {
        background: var(--hcis-brand-600, var(--hcis-indigo-600));
        color: var(--hcis-white);
        border-color: var(--hcis-brand-600, var(--hcis-indigo-600));
    }

    .hcis-dual-pane__grid {
        display: flex !important;
        flex-direction: column !important;
        gap: 0.65rem !important;
        height: auto !important;
        overflow: visible !important;
    }

    .hcis-dual-pane__pane {
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
        padding: 0 !important;
    }

    :deep(.hcis-dialog__description) {
        display: none !important;
    }

    :deep(.hcis-dialog__title) {
        white-space: nowrap !important;
        overflow: hidden !important;
        text-overflow: ellipsis !important;
        min-width: 0 !important;
    }

    :deep(.hcis-dialog__title-row) {
        flex-wrap: nowrap !important;
        min-width: 0 !important;
    }
}
</style>
