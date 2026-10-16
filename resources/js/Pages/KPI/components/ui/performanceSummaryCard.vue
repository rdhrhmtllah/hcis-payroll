<template>
    <div class="summary-performance-card">
        <div class="summary-metrics-grid">
            <div class="summary-metric">
                <p>
                    <i class="bi bi-bullseye mt-customFransIconP"></i> Target
                    Skor
                </p>
                <h3>{{ targetScore }}</h3>
            </div>

            <div class="summary-metric">
                <p>
                    <i class="bi bi-trophy-fill mt-customFransIconP"></i>
                    Achievement
                </p>
                <h3>
                    {{ formatScore(actualScore) }}%
                    <span
                        v-if="isCapped"
                        class="cap-indicator"
                        :title="capNote"
                    >
                        <i class="bi bi-info-circle-fill"></i>
                    </span>
                </h3>
                <div v-if="isCapped && capNote" class="cap-note">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    {{ capNote }}
                </div>
            </div>

            <div class="summary-metric">
                <p>
                    <i
                        class="bi bi-clipboard-data-fill mt-customFransIconP"
                    ></i>
                    Performance
                </p>
                <span
                    class="badge final-performance-badge"
                    :style="{ backgroundColor: performanceStatus.color }"
                >
                    {{ performanceStatus.text }}</span
                >
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "performanceSummaryCard",
    props: {
        targetScore: {
            type: Number,
            required: true,
        },

        actualScore: {
            type: Number,
            required: true,
        },

        performanceStatus: {
            type: Object,
            required: true,
            validator: (value) => {
                return (
                    typeof value.text === "string" &&
                    typeof value.color === "string"
                );
            },
        },

        isCapped: {
            type: Boolean,
            default: false,
        },

        capNote: {
            type: String,
            default: "",
        },
    },
    methods: {
        formatScore(value) {
            if (typeof value !== "number" || !isFinite(value)) {
                return 0;
            }
            if (Number.isInteger(value)) {
                return value;
            }

            let rounded = parseFloat(value.toFixed(1));

            if (Number.isInteger(rounded)) {
                return rounded;
            }

            return rounded;
        },
    },
};
</script>

<style scoped>
/* Kartu Ringkasan Kinerja */
.summary-performance-card {
    background-color: var(--gray-50);
    border-radius: var(--border-radius);
    padding: 1.5rem 2rem;
    margin-bottom: 2.5rem;
    border: 1px solid var(--gray-200);
}
.summary-metrics-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.5rem;
    align-items: center;
    text-align: center;
}
.summary-metric {
    position: relative;
}
.summary-metric:not(:last-child)::after {
    content: "";
    position: absolute;
    right: -1.25rem;
    top: 50%;
    transform: translateY(-50%);
    width: 1px;
    height: 60%;
    background-color: var(--gray-200);
}
.summary-metric p {
    margin: 0 0 0.5rem 0;
    color: var(--gray-600);
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}
.summary-metric h3 {
    margin: 0;
    font-size: 2.25rem;
    font-weight: 700;
    background: var(--gradient-primary);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    color: transparent;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.cap-indicator {
    font-size: 1rem;
    color: var(--hcis-amber-500);
    cursor: help;
    display: inline-flex;
    align-items: center;
    flex-shrink: 0;
}

.cap-note {
    margin-top: 0.65rem;
    font-size: 0.78rem;
    color: var(--hcis-amber-800);
    background: linear-gradient(135deg, rgba(251, 191, 36, 0.08), rgba(245, 158, 11, 0.06));
    border: 1px solid rgba(245, 158, 11, 0.25);
    border-left: 3px solid var(--hcis-amber-500);
    border-radius: 8px;
    padding: 0.6rem 0.85rem;
    text-align: left;
    line-height: 1.5;
    font-weight: 400;
    display: flex;
    align-items: flex-start;
    gap: 0.5rem;
    box-shadow: 0 1px 3px rgba(245, 158, 11, 0.06);
}

.cap-note i {
    color: var(--hcis-amber-500);
    font-size: 0.85rem;
    flex-shrink: 0;
    margin-top: 0.1rem;
}

.mt-customFransIconP {
    margin-top: -10px;
}

@media (min-width: 992px) {
    .d-lg-none {
        display: none !important;
    }
}
@media (max-width: 991.98px) {
    .d-none {
        display: none !important;
    }
    .d-lg-block {
        display: block !important;
    }
}

@media (max-width: 991.98px) {
    .modern-stepper {
        flex-direction: column;
        align-items: stretch;
        gap: 1.5rem;
    }

    .summary-metrics-grid {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    .summary-metric:not(:last-child)::after {
        display: none;
    }
    .content-card {
        padding: 1.5rem;
    }
}
</style>
