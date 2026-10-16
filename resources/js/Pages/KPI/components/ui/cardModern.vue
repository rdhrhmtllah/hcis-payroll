<template>
    <div class="pica-info-card" :class="cardTypeClass">
        <div class="pica-card-header">
            <h5 class="pica-card-title">{{ item.parameter }}</h5>
            <span
                v-if="item.achievement !== undefined"
                :style="getAchievementBadgeR(item.achievement)"
                class="d-inline-flex align-items-center"
            >
                {{ getAchievementLabel(item.achievement) }}
            </span>
        </div>
        <div class="pica-card-body">
            <div class="pica-card-details">
                <div class="info-block">
                    <span>Target</span>
                    <p v-if="item.is_input_period">
                        {{
                            item.targetValue !== null
                                ? formatValueWithUnit(
                                      item.targetValue,
                                      item.targetUnit
                                  )
                                : "N/A"
                        }}
                    </p>
                    <p v-else class="text-muted">-</p>
                </div>
                <div class="info-block">
                    <span>Score</span>
                    <p v-if="loadingPerformance" class="mt-1">
                        <span
                            class="badge"
                            style="
                                display: inline-block;
                                width: 60px;
                                height: 22px;
                                background-color: #e0e0e0;
                                border-radius: 4px;
                                animation: pulse 1.5s infinite;
                            "
                        ></span>
                    </p>

                    <p v-else-if="item.is_input_period">
                        <span
                            :style="{
                                display: 'inline-block',
                                padding: '2px 10px',
                                fontSize: '12px',
                                fontWeight: '600',
                                borderRadius: '3px',
                                backgroundColor: getAchievementBadge(
                                    item.achievement
                                ).backgroundColor,
                                color: getAchievementBadge(item.achievement)
                                    .color,
                                minWidth: '30px',
                                textAlign: 'center',
                            }"
                        >
                            {{ formatScore(item.achievement) }}%
                        </span>
                    </p>

                    <p v-else class="text-muted">-</p>
                </div>
                <div class="info-block">
                    <span>Achievement</span>
                    <p v-if="item.is_input_period" class="score-value">
                        {{ formatScore(item.score) }} %
                    </p>
                    <p v-else class="text-muted">-</p>
                </div>
            </div>
            <div v-if="type === 'danger'" class="pica-card-actions">
                <template v-if="!isReadOnly">
                    <button
                        v-if="!isPicaCompleted"
                        class="btn-pica-fill"
                        @click="$emit('edit-pica', item)"
                    >
                        <i class="bi bi-pencil-square me-2"></i>
                        Isi PICA
                    </button>
                    <div v-else class="pica-completed-state">
                        <span class="badge-selesai">
                            <i class="bi bi-check-circle-fill"></i> Selesai
                        </span>
                        <div class="pica-completed-buttons">
                            <button
                                class="btn-action-icon"
                                title="Edit PICA"
                                @click="$emit('edit-pica', item)"
                            >
                                <i class="bi bi-pencil-fill mt-iconCard"></i>
                            </button>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div v-if="isPicaCompleted" class="pica-completed-state">
                        <span class="badge-selesai">
                            <i class="bi bi-check-circle-fill"></i> Selesai
                        </span>
                        <div class="pica-completed-buttons">
                            <button
                                class="btn-action-icon"
                                title="Lihat PICA"
                                @click="$emit('view-pica', item)"
                            >
                                <i class="bi bi-eye-fill mt-iconCard"></i>
                            </button>
                        </div>
                    </div>
                    <div v-else>
                        <span class="badge-belum-diisi">
                            <i class="bi bi-x-circle-fill"></i> Belum Diisi
                        </span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
import { formatNilaiSatuan } from '@/utils/nilaiSatuan';

export default {
    name: "PicaCard",
    props: {
        item: {
            type: Object,
            required: true,
        },
        type: {
            type: String,
            required: true,
        },
        isReadOnly: {
            type: Boolean,
            default: false,
        },
        performanceList: {
            type: Array,
            default: () => [],
        },
        loadingPerformance: {
            type: Boolean,
            required: null,
        },
    },
    emits: ["edit-pica"],
    computed: {
        isPicaCompleted() {
            return (
                this.item.pica &&
                this.item.pica.problem &&
                this.item.pica.action
            );
        },
        cardTypeClass() {
            return {
                "needs-pica": this.type === "danger",
                "good-performance": this.type === "success",
            };
        },
    },
    methods: {
        getAchievementLabel(achievement) {
            if (!this.performanceList) return "";
            const perf = this.performanceList.find(
                (p) =>
                    achievement >= p.Range_Awal && achievement <= p.Range_Akhir
            );
            return perf ? perf.Keterangan : "";
        },

        getAchievementIcon(achievement) {
            const perf = this.performanceList.find(
                (p) =>
                    achievement >= p.Range_Awal && achievement <= p.Range_Akhir
            );
            if (!perf) return null;

            const color = perf.Warna;
            if (color.includes("241, 196, 15"))
                return "bi bi-exclamation-triangle-fill";
            if (color.includes("success") || color.includes("0, 128, 0"))
                return "bi bi-check-circle-fill";
            return null;
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return "";
            return new Intl.NumberFormat("id-ID").format(value);
        },
        formatValueWithUnit(value, unit) {
            /*
             * Aturannya pindah ke utils/nilaiSatuan.js. Sebelum ini metode yang
             * sama tersalin di enam komponen dengan TIGA aturan berbeda soal
             * pemangkasan spasi dan pengenalan mata uang.
             */
            return formatNilaiSatuan(value, unit);
        },
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
        getAchievementBadgeR(achievement) {
            if (!this.performanceList || this.performanceList.length === 0) {
                return {
                    backgroundColor: "#6c757d",
                    color: "#fff",
                    padding: "2px 4px",
                    borderRadius: "4px",
                    fontSize: "0.7rem",
                    display: "inline-flex",
                    alignItems: "center",
                };
            }

            const perf = this.performanceList.find(
                (p) =>
                    achievement >= p.Range_Awal && achievement <= p.Range_Akhir
            );

            if (perf && perf.Warna) {
                return {
                    backgroundColor: perf.Warna,
                    color: "#fff",
                    padding: "2px 4px",
                    borderRadius: "4px",
                    fontSize: "0.8rem",
                    display: "inline-flex",
                    alignItems: "center",
                };
            }

            return {
                backgroundColor: "#6c757d",
                color: "#fff",
                padding: "2px 4px",
                borderRadius: "4px",
                fontSize: "0.8rem",
                display: "inline-flex",
                alignItems: "center",
            };
        },

        getAchievementBadge(achievement) {
            if (!this.performanceList || this.performanceList.length === 0) {
                return { backgroundColor: "#6c757d" };
            }

            const perf = this.performanceList.find(
                (p) =>
                    achievement >= p.Range_Awal && achievement <= p.Range_Akhir
            );

            if (perf && perf.Warna) {
                return { backgroundColor: perf.Warna, color: "#fff" };
            }

            return { backgroundColor: "#6c757d", color: "#fff" };
        },
    },
};
</script>

<style scoped>
.btn-pica-fill {
    background-color: var(--danger-color, #dc3545);
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: background-color 0.2s;
}
.btn-pica-fill:hover {
    background-color: #c82333;
}
.pica-completed-state {
    display: flex;
    align-items: center;
    gap: 1rem;
    width: 100%;
    justify-content: flex-end;
}
.badge-selesai {
    background-color: var(--success-color, #198754);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 500;
    font-size: 0.85rem;
}

.mt-iconCard {
    margin-top: -10px;
}
.pica-completed-buttons {
    display: flex;
    gap: 0.5rem;
}
.btn-action-icon {
    background: #f0f0f0;
    border: none;
    border-radius: 50%;
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6c757d;
}
.btn-action-icon:hover {
    background: #e0e0e0;
    color: #343a40;
}
/* Sisanya style Anda sudah OK */
.pica-info-card {
    background-color: var(--white-color);
    border: 1px solid var(--gray-200);
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-sm);
    transition: all var(--transition-speed) ease;
}
.pica-info-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-4px);
}
.pica-info-card.needs-pica {
    border-left: 5px solid var(--danger-color);
}
.pica-info-card.good-performance {
    border-left: 5px solid var(--success-color);
}
.pica-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1rem 1.2rem;
    border-bottom: 1px solid var(--gray-200);
}

.pica-card-header span {
    font-size: 0.6rem;
}

.pica-card-title {
    margin: 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--text-color);
    max-width: 12rem;
}
.pica-card-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.pica-card-details {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}
.pica-card-details > div {
    flex: 1;
}
.pica-card-details span {
    font-size: 0.8rem;
    color: var(--gray-600);
    display: block;
    margin-bottom: 0.25rem;
}
.pica-card-details p {
    margin: 0;
    font-weight: 600;
    font-size: 0.8rem;
    color: var(--text-color);
}

.info-block span {
    font-size: 0.9rem;
    color: #4b5563;
    display: block;
    margin-bottom: 0.25rem;
}
.info-block p {
    margin: 0;
    font-weight: 500;
    display: flex;
    align-items: center;
}
.info-block .score-value {
    font-size: 0.9rem;
    font-weight: 700;
}
.pica-card-actions {
    display: flex;
    justify-content: flex-end;
}

.tes span {
    font-size: 0.8rem;
}
.badge-success {
    background-color: var(--success-color);
}
.badge-danger {
    background-color: var(--danger-color);
}

@media screen and (max-width: 599px) {
    .pica-card-details span {
        font-size: 0.5rem;
    }
    .pica-card-header span {
        font-size: 0.54rem !important;
    }
}
</style>
