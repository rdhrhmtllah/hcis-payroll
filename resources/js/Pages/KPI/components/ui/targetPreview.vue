<template>
    <Modal
        :show="show"
        :title="modalTitle"
        :subtitle="modalSubtitle"
        :showConfirm="false"
        :showCancel="false"
        :closeOnOverlay="true"
        :size="size"
        @close="$emit('close')"
    >
        <template #body>
            <!-- Loading State -->
            <div v-if="loading" class="loading-container">
                <div class="loading-animation">
                    <div class="loading-spinner"></div>
                    <div class="loading-dots">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>
                </div>
                <p class="loading-text">Memuat data target...</p>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="!targetData || targetData.length === 0"
                class="empty-state"
            >
                <div class="empty-icon">
                    <i
                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center align-items-center bi-inbox"
                    ></i>
                </div>
                <h5 class="empty-title">Tidak Ada Data Target</h5>
                <p class="empty-text">
                    Belum ada target yang ditetapkan untuk periode ini.
                </p>
            </div>

            <!-- Target Preview Content -->
            <div v-else class="target-preview-content">
                <!-- Header Summary -->
                <div class="preview-header">
                    <div class="header-summary">
                        <div class="summary-item">
                            <div class="summary-icon">
                                <i
                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center align-items-center bi-calculator"
                                ></i>
                            </div>
                            <div class="summary-content">
                                <span class="summary-label">Total Target</span>
                                <span class="summary-value">{{
                                    formatTotalTarget
                                }}</span>
                            </div>
                        </div>
                        <div
                            v-if="showActual && hasActualData"
                            class="summary-item"
                        >
                            <div class="summary-icon">
                                <i
                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center align-items-center bi-graph-up"
                                ></i>
                            </div>
                            <div class="summary-content">
                                <span class="summary-label"
                                    >Rata-rata Pencapaian</span
                                >
                                <span
                                    class="summary-value"
                                    :class="
                                        getAchievementClass(
                                            overallAchievementRate,
                                            100
                                        )
                                    "
                                >
                                    {{ overallAchievementRate }}%
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Toggle Actual -->
                    <div v-if="showActualToggle" class="actual-toggle">
                        <button class="btn-toggle" @click="toggleActualDisplay">
                            <i
                                class="bi"
                                :class="showActual ? 'bi-eye-slash' : 'bi-eye'"
                            ></i>
                            {{
                                showActual
                                    ? "Sembunyikan Actual"
                                    : "Tampilkan Actual"
                            }}
                        </button>
                    </div>
                </div>

                <!-- Target Grid -->
                <div class="target-grid-section">
                    <div class="grid-container">
                        <div
                            v-for="(item, index) in displayData"
                            :key="index"
                            class="target-item"
                            :class="{
                                'has-actual':
                                    showActual && item.actual !== null,
                                achieved:
                                    showActual && item.actual >= item.target,
                                'in-progress':
                                    showActual &&
                                    item.actual > 0 &&
                                    item.actual < item.target,
                                'not-achieved':
                                    showActual &&
                                    (item.actual === 0 || item.actual === null),
                            }"
                        >
                            <!-- Period Header -->
                            <div class="item-header">
                                <span class="period-name">{{ item.name }}</span>
                                <span class="period-range">{{
                                    item.period
                                }}</span>
                            </div>

                            <!-- Target Value -->
                            <div class="value-section">
                                <div class="target-value">
                                    <span class="value-label">Target</span>
                                    <span class="value-number">{{
                                        formatTargetValue(item.target)
                                    }}</span>
                                </div>

                                <!-- Actual Value (Conditional) -->
                                <div v-if="showActual" class="actual-value">
                                    <span class="value-label">Actual</span>
                                    <span
                                        class="value-number"
                                        :class="
                                            getActualStatusClass(
                                                item.actual,
                                                item.target
                                            )
                                        "
                                    >
                                        {{
                                            formatTargetValue(item.actual || 0)
                                        }}
                                    </span>
                                </div>

                                <!-- Achievement (Conditional) -->
                                <div
                                    v-if="showActual"
                                    class="achievement-section"
                                >
                                    <div class="achievement-rate">
                                        <span
                                            class="rate-value"
                                            :class="
                                                getAchievementClass(
                                                    item.actual,
                                                    item.target
                                                )
                                            "
                                        >
                                            {{
                                                calculateAchievementRate(
                                                    item.actual,
                                                    item.target
                                                )
                                            }}%
                                        </span>
                                    </div>
                                    <div class="achievement-bar">
                                        <div
                                            class="bar-fill"
                                            :class="
                                                getAchievementClass(
                                                    item.actual,
                                                    item.target
                                                )
                                            "
                                            :style="{
                                                width:
                                                    calculateAchievementRate(
                                                        item.actual,
                                                        item.target
                                                    ) + '%',
                                            }"
                                        ></div>
                                    </div>
                                </div>

                                <!-- Status Badge (Conditional) -->
                                <div
                                    v-if="showActual"
                                    class="status-badge"
                                    :class="
                                        getStatusBadgeClass(
                                            item.actual,
                                            item.target
                                        )
                                    "
                                >
                                    <i
                                        class="bi"
                                        :class="
                                            getStatusIcon(
                                                item.actual,
                                                item.target
                                            )
                                        "
                                    ></i>
                                    {{
                                        getStatusText(item.actual, item.target)
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Performance Summary -->
                <div
                    v-if="showActual && hasActualData"
                    class="performance-summary"
                >
                    <div class="summary-grid">
                        <div class="summary-card success">
                            <div class="summary-icon">
                                <i
                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center align-items-center bi-check-circle"
                                ></i>
                            </div>
                            <div class="summary-content">
                                <span class="summary-value">{{
                                    achievedCount
                                }}</span>
                                <span class="summary-label"
                                    >Target Tercapai</span
                                >
                            </div>
                        </div>
                        <div class="summary-card warning">
                            <div class="summary-icon">
                                <i
                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center align-items-center bi-exclamation-circle"
                                ></i>
                            </div>
                            <div class="summary-content">
                                <span class="summary-value">{{
                                    inProgressCount
                                }}</span>
                                <span class="summary-label">Dalam Proses</span>
                            </div>
                        </div>
                        <div class="summary-card danger">
                            <div class="summary-icon">
                                <i
                                    class="bi d-flex justify-content-center align-items-center d-flex justify-content-center align-items-center bi-x-circle"
                                ></i>
                            </div>
                            <div class="summary-content">
                                <span class="summary-value">{{
                                    notAchievedCount
                                }}</span>
                                <span class="summary-label"
                                    >Belum Tercapai</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <div class="preview-footer-actions">
                <button
                    class="btn-modern btn-secondary"
                    @click="$emit('close')"
                >
                    Tutup
                </button>
                <button
                    v-if="showEditButton"
                    class="btn-modern btn-primary"
                    @click="$emit('edit')"
                >
                    <i
                        class="bi d-flex justify-content-center align-items-center d-flex justify-content-center align-items-center bi-pencil"
                    ></i>
                    Edit Target
                </button>
            </div>
        </template>
    </Modal>
</template>

<script>
import Modal from "@/components/legacy/modalV2-noir.vue";

export default {
    name: "TargetPreviewModal",
    components: {
        Modal,
    },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        targetData: {
            type: Array,
            default: () => [],
        },
        targetUnit: {
            type: String,
            default: "",
        },
        valueFormat: {
            type: String,
            default: "number",
        },
        title: {
            type: String,
            default: "Preview Target",
        },
        loading: {
            type: Boolean,
            default: false,
        },
        showActual: {
            type: Boolean,
            default: false,
        },
        showActualToggle: {
            type: Boolean,
            default: true,
        },
        showEditButton: {
            type: Boolean,
            default: false,
        },
        size: {
            type: String,
            default: "large",
        },
    },
    emits: ["close", "edit", "update:showActual"],
    computed: {
        modalTitle() {
            return this.title;
        },
        modalSubtitle() {
            return `Total ${this.displayData.length} periode`;
        },
        displayData() {
            // Simple mapping tanpa dependensi targetScale
            return this.targetData.map((item, index) => ({
                name:
                    item.name ||
                    item.month ||
                    item.quarter ||
                    item.year ||
                    `Periode ${index + 1}`,
                period: item.period || "",
                target: item.target || 0,
                actual: item.actual !== undefined ? item.actual : null,
            }));
        },
        totalTarget() {
            if (!this.targetData.length) return 0;
            return this.targetData.reduce(
                (sum, item) => sum + (item.target || 0),
                0
            );
        },
        formatTotalTarget() {
            return this.convertValueUnit(
                this.totalTarget,
                this.valueFormat,
                this.targetUnit
            );
        },
        hasActualData() {
            return this.targetData.some(
                (item) => item.actual !== null && item.actual !== undefined
            );
        },
        achievedCount() {
            return this.targetData.filter(
                (item) =>
                    item.actual !== null &&
                    item.actual !== undefined &&
                    item.actual >= item.target
            ).length;
        },
        inProgressCount() {
            return this.targetData.filter(
                (item) =>
                    item.actual !== null &&
                    item.actual !== undefined &&
                    item.actual > 0 &&
                    item.actual < item.target
            ).length;
        },
        notAchievedCount() {
            return this.targetData.filter(
                (item) =>
                    item.actual === null ||
                    item.actual === undefined ||
                    item.actual === 0
            ).length;
        },
        overallAchievementRate() {
            if (!this.hasActualData) return 0;
            const validItems = this.targetData.filter(
                (item) =>
                    item.actual !== null &&
                    item.actual !== undefined &&
                    item.target > 0
            );

            if (validItems.length === 0) return 0;

            const totalAchievement = validItems.reduce((sum, item) => {
                return sum + (item.actual / item.target) * 100;
            }, 0);

            return Math.round(totalAchievement / validItems.length);
        },
    },
    methods: {
        convertValueUnit(value, format, simbol = null) {
            if (!format || value === null || value === undefined) return "0";

            let simbolSatuan = simbol ? simbol?.trim() : "";
            let decimals = 2;
            let result = "";

            if (format == "currency") {
                result = new Intl.NumberFormat("id-ID").format(value);
            } else if (format == "percentage") {
                const numericValue = parseFloat(value) || 0;
                result = `${numericValue.toFixed(decimals)}`;
            } else {
                const numericValue = parseFloat(value) || 0;
                result = new Intl.NumberFormat("id-ID", {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 0,
                }).format(numericValue);
            }

            return format == "currency"
                ? simbolSatuan + ". " + result
                : result + (simbolSatuan ? " " + simbolSatuan : "");
        },

        formatTargetValue(value) {
            return this.convertValueUnit(
                value,
                this.valueFormat,
                this.targetUnit
            );
        },

        calculateAchievementRate(actual, target) {
            if (
                actual === null ||
                actual === undefined ||
                !target ||
                target === 0
            )
                return 0;
            const rate = (actual / target) * 100;
            return Math.min(Math.round(rate), 100); // Maksimal 100%
        },

        getActualStatusClass(actual, target) {
            if (actual === null || actual === undefined) return "text-muted";
            if (actual >= target) return "text-success";
            if (actual >= target * 0.7) return "text-warning";
            return "text-danger";
        },

        getAchievementClass(actual, target) {
            if (actual === null || actual === undefined) return "text-muted";
            const rate = this.calculateAchievementRate(actual, target);
            if (rate >= 100) return "text-success";
            if (rate >= 70) return "text-warning";
            return "text-danger";
        },

        getStatusBadgeClass(actual, target) {
            if (actual === null || actual === undefined) return "status-muted";
            if (actual >= target) return "status-success";
            if (actual >= target * 0.7) return "status-warning";
            return "status-danger";
        },

        getStatusIcon(actual, target) {
            if (actual === null || actual === undefined)
                return "bi-dash-circle";
            if (actual >= target) return "bi-check-circle";
            if (actual >= target * 0.7) return "bi-exclamation-circle";
            return "bi-x-circle";
        },

        getStatusText(actual, target) {
            if (actual === null || actual === undefined)
                return "Belum Ada Data";
            if (actual >= target) return "Tercapai";
            if (actual >= target * 0.7) return "Dalam Proses";
            return "Belum Tercapai";
        },

        toggleActualDisplay() {
            this.$emit("update:showActual", !this.showActual);
        },
    },
};
</script>

<style scoped>
.target-preview-content {
    /* padding: 0.5rem 0; */
}

/* Header Styles */
.preview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1rem 0;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
    gap: 1rem;
}

.header-summary {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.summary-icon {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
}

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 1.125rem;
    color: var(--text);
    font-weight: 600;
}

.actual-toggle {
    flex-shrink: 0;
}

.btn-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-toggle:hover {
    background: var(--surface-soft);
    border-color: var(--primary-light);
}

/* Grid Layout */
.target-grid-section {
    margin-bottom: 1.5rem;
}

.grid-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1rem;
}

.target-item {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.25rem;
    transition: all 0.3s ease;
    box-shadow: var(--shadow);
}

.target-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.target-item.has-actual.achieved {
    border-left: 4px solid var(--success);
}

.target-item.has-actual.in-progress {
    border-left: 4px solid var(--warning);
}

.target-item.has-actual.not-achieved {
    border-left: 4px solid var(--danger);
}

/* Item Header */
.item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border);
}

.period-name {
    font-weight: 600;
    color: var(--text);
    font-size: 1rem;
}

.period-range {
    font-size: 0.75rem;
    color: var(--text-muted);
    background: var(--surface-soft);
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
}

/* Value Sections */
.value-section {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.target-value,
.actual-value {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.value-label {
    font-size: 0.875rem;
    color: var(--text-muted);
}

.value-number {
    font-weight: 600;
    font-size: 1rem;
}

/* Achievement Section */
.achievement-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 0.5rem;
}

.achievement-rate {
    text-align: center;
}

.rate-value {
    font-weight: 700;
    font-size: 1.125rem;
}

.achievement-bar {
    height: 6px;
    background: var(--surface-soft);
    border-radius: 3px;
    overflow: hidden;
}

.bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 0.5s ease;
}

.bar-fill.text-success {
    background: var(--success);
}

.bar-fill.text-warning {
    background: var(--warning);
}

.bar-fill.text-danger {
    background: var(--danger);
}

.bar-fill.text-muted {
    background: var(--text-muted);
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.5rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 0.5rem;
    justify-content: center;
}

.status-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.status-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.status-muted {
    background: rgba(100, 116, 139, 0.1);
    color: var(--text-muted);
    border: 1px solid rgba(100, 116, 139, 0.2);
}

/* Performance Summary */
.performance-summary {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid var(--border);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.summary-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    background: var(--surface);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.summary-card.success {
    border-color: rgba(16, 185, 129, 0.3);
}

.summary-card.warning {
    border-color: rgba(245, 158, 11, 0.3);
}

.summary-card.danger {
    border-color: rgba(239, 68, 68, 0.3);
}

.summary-card .summary-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    font-size: 1rem;
}

.summary-card.success .summary-icon {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.summary-card.warning .summary-icon {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.summary-card.danger .summary-icon {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text);
}

.summary-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Text Colors */
.text-success {
    color: var(--success);
}
.text-warning {
    color: var(--warning);
}
.text-danger {
    color: var(--danger);
}
.text-muted {
    color: var(--text-muted);
}

/* Responsive */
@media (max-width: 768px) {
    .preview-header {
        flex-direction: column;
        align-items: stretch;
    }

    .header-summary {
        justify-content: center;
    }

    .grid-container {
        grid-template-columns: 1fr;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .target-item {
        padding: 1rem;
    }
}

@media (max-width: 480px) {
    .header-summary {
        flex-direction: column;
        gap: 1rem;
    }

    .summary-item {
        justify-content: center;
        text-align: center;
    }

    .item-header {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
        text-align: center;
    }
}
</style>

<style scoped>
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
    --primary-light: var(--hcis-indigo-300);
    --secondary: var(--hcis-slate-500);
    --success: var(--hcis-emerald-500);
    --warning: var(--hcis-amber-500);
    --danger: var(--hcis-red-500);
    --info: var(--hcis-cyan-500);
    --light: var(--hcis-slate-50);
    --dark: var(--hcis-slate-800);
    --surface: var(--hcis-white);
    --surface-soft: var(--hcis-slate-100);
    --border: var(--hcis-slate-200);
    --text: var(--hcis-slate-700);
    --text-muted: var(--hcis-slate-500);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
}

.target-preview-content {
    padding: 0.5rem 0;
}

/* Header Summary */
.preview-summary {
    margin-bottom: 1.5rem;
}

.summary-card {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid var(--border);
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.summary-icon {
    width: 40px;
    height: 40px;
    background: var(--primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
}

.summary-content {
    display: flex;
    flex-direction: column;
}

.summary-label {
    font-size: 0.75rem;
    color: var(--text-muted);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 1.125rem;
    color: var(--text);
    font-weight: 600;
}

.summary-value.highlight {
    color: var(--primary);
    font-size: 1.25rem;
}

/* Section Header */
.section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding: 1rem 0;
    border-bottom: 2px solid var(--border);
    flex-wrap: wrap;
}

.section-icon {
    width: 40px;
    height: 40px;
    background: var(--gradient-primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.125rem;
    flex-shrink: 0;
}

.section-text {
    flex: 1;
    min-width: 200px;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.section-subtitle {
    font-size: 0.875rem;
    color: var(--text-muted);
    margin: 0;
}

.section-action {
    flex-shrink: 0;
}

.btn-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text);
    font-size: 0.875rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-toggle:hover {
    background: var(--surface-soft);
    border-color: var(--primary-light);
}

/* Table Styles */
.table-container {
    background: var(--surface);
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
    box-shadow: var(--shadow);
}

.target-table {
    width: 100%;
    border-collapse: collapse;
}

.target-table th {
    background: var(--surface-soft);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--text);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border);
}

.target-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    vertical-align: middle;
}

.target-table tr:last-child td {
    border-bottom: none;
}

.target-table tr:hover {
    background: var(--surface-soft);
}

/* Table Cells */
.period-cell {
    min-width: 120px;
}

.period-name {
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.25rem;
}

.period-range {
    font-size: 0.75rem;
    color: var(--text-muted);
}

.target-cell,
.actual-cell {
    min-width: 100px;
}

.value-main {
    font-weight: 600;
    font-size: 0.95rem;
}

.achievement-cell {
    min-width: 80px;
}

.achievement-rate {
    font-weight: 600;
    font-size: 0.9rem;
}

.status-cell {
    min-width: 120px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.status-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.status-danger {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.status-muted {
    background: rgba(100, 116, 139, 0.1);
    color: var(--text-muted);
    border: 1px solid rgba(100, 116, 139, 0.2);
}

/* Performance Summary */
.performance-summary {
    background: var(--surface-soft);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1.5rem;
    border: 1px solid var(--border);
}

.summary-header {
    margin-bottom: 1rem;
}

.summary-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text);
    margin: 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 1rem;
}

.summary-card-mini {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.summary-card-mini.success {
    background: rgba(16, 185, 129, 0.05);
    border-color: rgba(16, 185, 129, 0.2);
}

.summary-card-mini.warning {
    background: rgba(245, 158, 11, 0.05);
    border-color: rgba(245, 158, 11, 0.2);
}

.summary-card-mini.danger {
    background: rgba(239, 68, 68, 0.05);
    border-color: rgba(239, 68, 68, 0.2);
}

.summary-icon-mini {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
}

.summary-card-mini.success .summary-icon-mini {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
}

.summary-card-mini.warning .summary-icon-mini {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning);
}

.summary-card-mini.danger .summary-icon-mini {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

.summary-content-mini {
    display: flex;
    flex-direction: column;
}

.summary-value-mini {
    font-size: 1.125rem;
    font-weight: 700;
    color: var(--text);
}

.summary-label-mini {
    font-size: 0.75rem;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Footer Actions */
.preview-footer-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Text Colors */
.text-success {
    color: var(--success);
}
.text-warning {
    color: var(--warning);
}
.text-danger {
    color: var(--danger);
}
.text-muted {
    color: var(--text-muted);
}
.text-primary {
    color: var(--primary);
}

/* Buttons */
.btn-modern {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.875rem;
    color: white;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-modern.btn-primary {
    background: var(--primary);
}

.btn-modern.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

.btn-modern.btn-secondary {
    background: var(--secondary);
}

.btn-modern.btn-secondary:hover {
    background: var(--hcis-slate-600);
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

/* Loading & Empty States */
.loading-container,
.empty-state {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem 2rem;
    text-align: center;
}

.loading-animation {
    margin-bottom: 1rem;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 3px solid var(--border);
    border-top: 3px solid var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

.loading-dots {
    display: flex;
    gap: 0.25rem;
    justify-content: center;
}

.loading-dots span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--primary);
    animation: bounce 1.4s infinite ease-in-out;
}

.loading-dots span:nth-child(1) {
    animation-delay: -0.32s;
}
.loading-dots span:nth-child(2) {
    animation-delay: -0.16s;
}

.empty-icon {
    width: 80px;
    height: 80px;
    background: var(--surface-soft);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    color: var(--text-muted);
    font-size: 2rem;
}

.empty-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text);
    margin-bottom: 0.5rem;
}

.empty-text,
.loading-text {
    color: var(--text-muted);
    margin: 0;
}

/* Animations */
@keyframes spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

@keyframes bounce {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .summary-card {
        grid-template-columns: 1fr;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .section-action {
        align-self: stretch;
    }

    .btn-toggle {
        width: 100%;
        justify-content: center;
    }

    .table-container {
        border-radius: 8px;
        overflow-x: auto;
    }

    .target-table {
        min-width: 600px;
    }

    .preview-footer-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .preview-footer-actions button {
        width: 100%;
        justify-content: center;
    }

    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 480px) {
    .preview-summary,
    .target-table-section,
    .performance-summary {
        margin-left: -0.5rem;
        margin-right: -0.5rem;
    }

    .summary-card {
        border-radius: 0;
        border-left: none;
        border-right: none;
    }

    .table-container {
        border-radius: 0;
        border-left: none;
        border-right: none;
    }

    .performance-summary {
        border-radius: 0;
        border-left: none;
        border-right: none;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .target-table th,
    .target-table td {
        padding: 0.75rem 0.5rem;
    }

    .status-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
}

/* Scrollbar Styling */
.table-container::-webkit-scrollbar {
    height: 6px;
}

.table-container::-webkit-scrollbar-track {
    background: var(--surface-soft);
}

.table-container::-webkit-scrollbar-thumb {
    background: var(--border);
    border-radius: 3px;
}

.table-container::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}
</style>
