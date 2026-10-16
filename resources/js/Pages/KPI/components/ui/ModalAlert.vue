<template>
    <Modal
        v-if="show"
        :show="show"
        :title="title"
        :size="size"
        @close="handleClose"
    >
        <template #body>
            <!-- Slot untuk konten custom -->
            <div v-if="$slots.body" class="alert-body-custom">
                <slot name="body"></slot>
            </div>

            <!-- Konten default alert -->
            <div
                v-else
                class="alert-content-custom d-flex justify-content-center align-items-center flex-column"
            >
                <i
                    :class="computedIcon"
                    class="alert-icon d-flex justify-content-center align-items-center mb-4"
                ></i>
                <p>{{ message }}</p>
            </div>
        </template>
        <template #footer>
            <!-- Slot untuk footer custom -->
            <div v-if="$slots.footer" class="alert-footer-custom">
                <slot name="footer"></slot>
            </div>

            <!-- Footer default -->
            <div v-else class="wrapper-button-footer gap-2">
                <button
                    v-if="showCancel"
                    class="btn-modern btn-secondary button-footer"
                    @click="handleCancel"
                    :disabled="loading"
                >
                    <i v-if="cancelIcon" :class="cancelIcon" class="me-2"></i>
                    {{ cancelText }}
                </button>
                <button
                    v-if="showSubmit"
                    class="btn-modern button-footer"
                    :class="confirmClass"
                    @click="handleConfirm"
                    :disabled="loading || confirmDisabled"
                >
                    <template v-if="loading">
                        <i class="spinner-border spinner-border-sm me-2"></i>
                        {{ loadingText }}
                    </template>
                    <template v-else>
                        <i
                            v-if="confirmIcon"
                            :class="confirmIcon"
                            class="me-2"
                        ></i>
                        {{ confirmText }}
                    </template>
                </button>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from "@/components/legacy/modalV2-noir.vue";

export default {
    name: "AlertModal",
    components: {
        Modal,
    },
    props: {
        // Kontrol tampilan
        show: {
            type: Boolean,
            default: false,
        },
        showSubmit: {
            type: Boolean,
            default: true,
        },

        // Ukuran modal
        size: {
            type: String,
            default: "medium",
            validator: (value) =>
                ["small", "medium", "large", "xlarge", "fullscreen"].includes(
                    value
                ),
        },

        // Konten
        title: {
            type: String,
            default: "Alert",
        },
        message: {
            type: String,
            default: "",
        },

        // Icon configuration
        icon: {
            type: String,
            default: "", // Custom icon class
        },
        type: {
            type: String,
            default: "info", // info, success, warning, danger, custom
            validator: (value) =>
                ["info", "success", "warning", "danger", "custom"].includes(
                    value
                ),
        },

        // Button configuration
        showCancel: {
            type: Boolean,
            default: true,
        },
        cancelText: {
            type: String,
            default: "Batal",
        },
        cancelIcon: {
            type: String,
            default: "",
        },
        confirmText: {
            type: String,
            default: "OK",
        },
        confirmIcon: {
            type: String,
            default: "",
        },
        confirmType: {
            type: String,
            default: "primary", // primary, success, danger, warning, secondary
            validator: (value) =>
                [
                    "primary",
                    "success",
                    "danger",
                    "warning",
                    "secondary",
                ].includes(value),
        },

        // Loading state
        loading: {
            type: Boolean,
            default: false,
        },
        loadingText: {
            type: String,
            default: "Processing...",
        },
        confirmDisabled: {
            type: Boolean,
            default: false,
        },
    },
    computed: {
        computedIcon() {
            // Jika custom icon diberikan, gunakan itu
            if (this.icon) {
                return this.icon;
            }

            // Default icons berdasarkan type
            const iconMap = {
                info: "bi bi-info-circle",
                success: "bi bi-check-circle",
                warning: "bi bi-exclamation-triangle",
                danger: "bi bi-x-circle",
                custom: "bi bi-bell",
            };

            return iconMap[this.type] || iconMap.info;
        },

        confirmClass() {
            return `btn-${this.confirmType}`;
        },
    },
    methods: {
        handleClose() {
            this.$emit("close");
        },

        handleCancel() {
            this.$emit("close");
        },

        handleConfirm() {
            this.$emit("confirm");
        },
    },
};
</script>

<style scoped>
/* Alert Custom */
.alert-content-custom {
    text-align: center;
    padding: 1rem;
}
.alert-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    display: block;
}

.alert-body-custom,
.alert-footer-custom {
    width: 100%;
}

.wrapper-button-footer {
    display: flex;
    justify-content: end;
    align-items: center;
    padding: 0;
}

.button-footer {
    flex-grow: 0.5;
}

/* Buttons */
.btn-modern {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.2rem;
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

.btn-success {
    background-color: var(--success);
}

.btn-secondary {
    background-color: var(--secondary);
}

.btn-danger {
    background-color: var(--danger);
}

.btn-warning {
    background-color: var(--warning);
}

.btn-outline {
    background: transparent;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
}

.btn-outline:hover {
    background: rgba(255, 255, 255, 0.1);
}

.btn-modern:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

/* Spinner */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

@media (max-width: 768px) {
    .wrapper-button-footer {
        width: 100%;
    }
}
</style>
