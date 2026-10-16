<template>
    <transition
        name="modal"
        @enter="onEnter"
        @leave="onLeave"
        @after-enter="onAfterEnter"
    >
        <div v-if="show" class="modal-overlay" @click.self="handleOverlayClick">
            <div
                v-if="show"
                class="modal-overlay"
                @click.self="handleOverlayClick"
            >
                <div
                    class="modal-container"
                    :class="modalClasses"
                    :style="modalStyles"
                >
                    <!-- Modal Header -->
                    <div class="modal-header" v-if="showHeader">
                        <div class="modal-title-section">
                            <h3 class="modal-title">{{ title }}</h3>
                            <div class="modal-subtitle" v-if="subtitle">
                                {{ subtitle }}
                            </div>
                        </div>
                        <button
                            class="modal-close"
                            @click="closeModal"
                            aria-label="Close modal"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <!-- Modal Body -->
                    <div class="modal-body" :class="bodyClass">
                        <slot name="body">
                            <!-- Skeleton Content -->
                            <div
                                v-if="skeleton && !$slots.body"
                                class="skeleton-content"
                            >
                                <div
                                    v-for="i in skeletonLines"
                                    :key="i"
                                    class="skeleton-line"
                                    :class="`skeleton-line-${i}`"
                                ></div>
                            </div>
                        </slot>
                    </div>

                    <!-- Modal Footer -->
                    <div
                        class="modal-footer"
                        v-if="showFooter || $slots.footer"
                    >
                        <slot name="footer">
                            <div class="modal-footer-actions" v-if="showFooter">
                                <button
                                    v-if="showCancel"
                                    class="btn-modern btn-secondary"
                                    @click="closeModal"
                                    :disabled="loading"
                                >
                                    {{ cancelText }}
                                </button>
                                <button
                                    v-if="showConfirm"
                                    class="btn-modern btn-primary"
                                    @click="confirmModal"
                                    :disabled="loading || confirmDisabled"
                                >
                                    <span
                                        v-if="loading"
                                        class="loading-spinner-small"
                                    ></span>
                                    {{ confirmText }}
                                </button>
                            </div>
                        </slot>
                    </div>

                    <!-- Loading Overlay -->
                    <div v-if="loading" class="modal-loading-overlay">
                        <div class="loading-content">
                            <div class="loading-spinner"></div>
                            <p class="loading-text">{{ loadingText }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script>
import { ElMessage } from "element-plus";

export default {
    name: "Modal",
    props: {
        skeleton: {
            type: Boolean,
            default: true,
        },
        skeletonLines: {
            type: Number,
            default: 3,
        },
        show: {
            type: Boolean,
            default: false,
        },
        title: {
            type: String,
            default: "Modal Title",
        },
        subtitle: {
            type: String,
            default: "",
        },
        size: {
            type: String,
            default: "medium",
            validator: (value) =>
                ["small", "medium", "large", "xlarge", "fullscreen"].includes(
                    value
                ),
        },
        showHeader: {
            type: Boolean,
            default: true,
        },
        showFooter: {
            type: Boolean,
            default: false,
        },
        showCancel: {
            type: Boolean,
            default: true,
        },
        showConfirm: {
            type: Boolean,
            default: false,
        },
        cancelText: {
            type: String,
            default: "Batal",
        },
        confirmText: {
            type: String,
            default: "Konfirmasi",
        },
        confirmDisabled: {
            type: Boolean,
            default: false,
        },
        loading: {
            type: Boolean,
            default: false,
        },
        loadingText: {
            type: String,
            default: "Memproses...",
        },
        closeOnOverlay: {
            type: Boolean,
            default: true,
        },
        closeOnEscape: {
            type: Boolean,
            default: true,
        },
        bodyClass: {
            type: String,
            default: "",
        },
        maxWidth: {
            type: String,
            default: null,
        },
        minHeight: {
            type: String,
            default: null,
        },
    },
    emits: ["close", "confirm", "update:show"],
    computed: {
        modalClasses() {
            return [
                `modal-${this.size}`,
                {
                    "modal-with-header": this.showHeader,
                    "modal-with-footer": this.showFooter || this.$slots.footer,
                    "modal-loading": this.loading,
                    "modal-skeleton": this.skeleton && !this.loading,
                },
            ];
        },
        modalStyles() {
            const styles = {};
            if (this.maxWidth) styles.maxWidth = this.maxWidth;
            if (this.minHeight) styles.minHeight = this.minHeight;
            return styles;
        },
    },
    methods: {
        onAfterEnter() {
            this.$emit("after-enter");
        },
        closeModal() {
            this.$emit("close");
            this.$emit("update:show", false);
        },
        confirmModal() {
            this.$emit("confirm");
        },
        handleOverlayClick() {
            if (this.closeOnOverlay) {
                this.closeModal();
            }
        },
        handleEscapeKey(event) {
            if (event.key === "Escape" && this.closeOnEscape && this.show) {
                this.closeModal();
            }
        },
        onEnter(el) {
            document.body.style.overflow = "hidden";
            if (this.closeOnEscape) {
                document.addEventListener("keydown", this.handleEscapeKey);
            }
        },
        onLeave(el) {
            document.body.style.overflow = "";
            if (this.closeOnEscape) {
                document.removeEventListener("keydown", this.handleEscapeKey);
            }
        },
    },
    watch: {
        show: {
            immediate: true,
            handler(newVal) {
                if (newVal && this.closeOnEscape) {
                    document.addEventListener("keydown", this.handleEscapeKey);
                } else {
                    document.removeEventListener(
                        "keydown",
                        this.handleEscapeKey
                    );
                }
            },
        },
    },
    beforeUnmount() {
        document.removeEventListener("keydown", this.handleEscapeKey);
        document.body.style.overflow = "";
    },
};
</script>

<style scoped>
/* CSS Variables */
*,
*::before,
*::after {
    --primary: #6366f1;
    --primary-dark: #4f46e5;
    --primary-light: #a5b4fc;
    --secondary: #64748b;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --info: #06b6d4;
    --light: #f8fafc;
    --dark: #1e293b;
    --surface: #ffffff;
    --surface-soft: #f1f5f9;
    --border: #e2e8f0;
    --text: #334155;
    --text-muted: #64748b;
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
    --backdrop: rgba(0, 0, 0, 0.2);
}

/* Modal Overlay */

.modal-enter-active {
    transition: opacity 0.3s ease;
}
.modal-leave-active {
    transition: all 0.2s ease;
}

.modal-enter-from {
    opacity: 0;
    transform: scale(0.95) translateY(-10px);
}

.modal-enter-to {
    opacity: 1;
    transform: scale(1) translateY(0);
}

.modal-leave-from {
    opacity: 1;
    /* transform: scale(1) translateY(0); */
}

.modal-leave-to {
    opacity: 0;
    /* transform: scale(0.95) translateY(10px); */
}

/* .modal-overlay {
    transition: all 0.3s ease;
} */

/* Skeleton Styles */
.skeleton-content {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.skeleton-line {
    height: 1rem;
    border-radius: 4px;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: skeleton-loading 1.5s infinite;
}

.skeleton-line-1 {
    width: 100%;
}

.skeleton-line-2 {
    width: 85%;
}

.skeleton-line-3 {
    width: 70%;
}

.skeleton-line-4 {
    width: 90%;
    height: 2rem;
}

.skeleton-line-5 {
    width: 60%;
}

@keyframes skeleton-loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}

/* Reduced motion support untuk skeleton */
@media (prefers-reduced-motion: reduce) {
    .skeleton-line {
        animation: none;
        background: #f0f0f0;
    }
}

/* Tambah di bagian existing CSS */
.modal-loading .modal-body,
.modal-skeleton .modal-body {
    position: relative;
}

.modal-loading .skeleton-content,
.modal-skeleton .skeleton-content {
    opacity: 0.6;
}
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--backdrop);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    padding: 1rem;
    /* animation: fadeIn 0.2s ease; */
}

/* Modal Container */
.modal-container {
    background: var(--surface);
    border-radius: 16px;
    box-shadow: var(--shadow-xl);
    display: flex;
    flex-direction: column;
    max-height: 90vh;
    max-width: 90vw;
    animation: slideUp 0.3s ease;
    border: 1px solid var(--border);
    position: relative;
    overflow: hidden;
}

/* Modal Sizes */
.modal-small {
    width: 400px;
}

.modal-medium {
    width: 600px;
}

.modal-large {
    width: 1000px;
}

.modal-xlarge {
    width: 1200px;
}

.modal-fullscreen {
    width: 95vw;
    height: 95vh;
}

/* Modal Header */
.modal-header {
    display: flex;
    justify-content: space-between;
    /* align-items: flex-start; */
    padding: 0.5rem 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--surface-soft);
    flex-shrink: 0;
}

.modal-title-section {
    flex: 1;
    min-width: 0;
}

.modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--dark);
    line-height: 1.4;
}

.modal-subtitle {
    margin: 0.5rem 0 0 0;
    color: var(--text-muted);
    font-size: 0.9rem;
    line-height: 1.4;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 8px;
    transition: all 0.2s ease;
    flex-shrink: 0;
    margin-left: 1rem;
}

.modal-close:hover {
    background: var(--border);
    color: var(--danger);
}

/* Modal Body */
.modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    position: relative;
}

.modal-with-header .modal-body {
    padding-top: 1.5rem;
}

.modal-with-footer .modal-body {
    padding-bottom: 1.5rem;
}

/* Modal Footer */
.modal-footer {
    padding: 0.8rem 1.5rem;
    border-top: 1px solid var(--border);
    background: var(--surface-soft);
    flex-shrink: 0;
}

.modal-footer-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

/* Buttons */
.btn-modern {
    display: inline-flex;
    align-items: center;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255, 255, 255, 0.2),
        transparent
    );
    transition: left 0.5s;
}

.btn-modern:hover::before {
    left: 100%;
}

.btn-modern:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.btn-modern:disabled:hover {
    transform: none !important;
    box-shadow: none !important;
}

.btn-primary {
    background-color: var(--primary);
}

.btn-secondary {
    background-color: var(--secondary);
}

.btn-modern:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Loading States */
.modal-loading-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10;
}

.loading-content {
    text-align: center;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border);
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

.loading-spinner-small {
    width: 16px;
    height: 16px;
    border: 2px solid transparent;
    border-top: 2px solid currentColor;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 0.5rem;
}

.loading-text {
    color: var(--text-muted);
    margin: 0;
    font-size: 0.9rem;
}

/* Animations */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

/* Mobile Responsive */
@media (max-width: 768px) {
    .modal-overlay {
        padding: 0;
        align-items: flex-end;
    }
    .modal-large {
        height: 100vh;
    }

    .modal-container {
        width: 100vw;
        max-width: 100vw;
        max-height: 100vh;
        border-radius: 12px 12px 0px 0;
        animation: slideUpMobile 0.3s ease;
        margin-top: 10vh;
    }

    .modal-small,
    .modal-medium,
    .modal-large,
    .modal-xlarge {
        width: 100vw;
    }

    .modal-header {
        justify-content: center;
        align-items: center;
        padding: 0.5rem 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1.25rem 1.5rem;
    }

    .modal-footer-actions {
        flex-direction: column;
    }

    .modal-footer-actions .btn-modern {
        width: 100%;
        justify-content: center;
    }

    .modal-title {
        font-size: 1.1rem;
    }
}

@keyframes slideUpMobile {
    from {
        opacity: 0;
        transform: translateY(100%);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Tablet Responsive */
@media (min-width: 769px) and (max-width: 1024px) {
    .modal-large {
        width: 100vw;
        height: 100vh;

        /* max-width: 700px; */
    }

    .modal-xlarge {
        width: 95vw;
        max-width: 900px;
    }
}

/* Handle very small screens */
@media (max-width: 480px) {
    .modal-body {
        padding: 0rem 1.25rem;
    }

    .modal-footer {
        padding: 0.8rem 1.25rem;
    }

    .modal-title {
        font-size: 1rem;
    }
}

/* Print Styles */
@media print {
    .modal-overlay {
        position: static;
        background: white;
        backdrop-filter: none;
    }

    .modal-container {
        box-shadow: none;
        border: 1px solid #ccc;
        max-height: none;
        max-width: none;
    }

    .modal-close {
        display: none;
    }
}

/* High contrast mode support */
@media (prefers-contrast: high) {
    .modal-container {
        border: 2px solid currentColor;
    }

    .modal-header {
        border-bottom: 2px solid currentColor;
    }

    .modal-footer {
        border-top: 2px solid currentColor;
    }
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .modal-overlay {
        animation: none;
    }

    .modal-container {
        animation: none;
    }

    .btn-modern::before {
        display: none;
    }

    .btn-modern:hover:not(:disabled) {
        transform: none;
    }
}
</style>
