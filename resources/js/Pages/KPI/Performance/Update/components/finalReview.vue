<template>
    <div class="final-review-container">
        <div class="review-items-list">
            <div
                v-for="item in kpiItems"
                :key="item.id"
                class="review-item-card"
                :class="{ 'not-in-period': !item.is_input_period }"
            >
                <div class="review-card-main">
                    <h5 class="review-card-title">{{ item.parameter }}</h5>

                    <div v-if="!item.is_input_period" class="period-indicator">
                        <span class="badge-not-active">
                            <i class="bi bi-clock-history me-1"></i>
                            Periode belum aktif untuk penginputan
                        </span>
                    </div>

                    <div class="review-card-details">
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
                            <span>Actual</span>
                            <p v-if="item.is_input_period">
                                {{
                                    item.actual !== null
                                        ? formatValueWithUnit(
                                              item.actual,
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
                                    class="badge"
                                    :style="
                                        getAchievementBadge(item.achievement)
                                    "
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
                        <div class="info-block">
                            <span>Bukti</span>
                            <p v-if="item.is_input_period">
                                <button
                                    v-if="
                                        item.dataSourceFile ||
                                        item.File_Berkas_Url
                                    "
                                    class="file-chip-button"
                                    @click="previewFile(item)"
                                    :title="`Lihat file: ${getFileName(
                                        item.dataSourceFile
                                    )}`"
                                >
                                    <span class="file-chip">
                                        <i class="bi bi-paperclip"></i>
                                        {{ getFileName(item.dataSourceFile) }}
                                    </span>
                                </button>

                                <!-- N/A jika tidak ada file -->
                                <span v-else class="text-muted">N/A</span>
                            </p>

                            <p v-else class="text-muted">-</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="requiresPica" class="review-section">
            <h4>Ringkasan PICA (Rencana Perbaikan)</h4>
            <div class="pica-review-container">
                <div
                    v-for="item in redKpiItems"
                    :key="item.id"
                    class="pica-review-item"
                >
                    <p class="pica-review-title">{{ item.parameter }}</p>
                    <ul>
                        <li>
                            <strong>Problem Identification:</strong>
                            <p>{{ item.pica?.problem || "Belum diisi" }}</p>
                        </li>
                        <li>
                            <strong>Corrective Actions</strong>
                            <p>{{ item.pica?.action || "Belum diisi" }}</p>
                        </li>
                        <li>
                            <strong>Due Date:</strong>
                            <p>{{ item.pica?.targetDate || "Belum diisi" }}</p>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div
            v-if="
                statusPengajuan !== 'disetujui' && statusPengajuan !== 'ditolak'
            "
        >
            <div v-if="!isFinalized" class="agreement-container">
                <label class="custom-checkbox">
                    <input
                        type="checkbox"
                        :checked="modelValue"
                        @change="
                            $emit('update:modelValue', $event.target.checked)
                        "
                    />
                    <span class="checkmark"></span>
                    <span class="label-text">
                        Saya menyatakan bahwa semua data yang saya masukkan
                        adalah benar dan saya bertanggung jawab penuh atas data
                        tersebut.
                    </span>
                </label>
            </div>
            <div v-else class="mt-5">
                <DynamicAlert
                    variant="info"
                    title="Data Telah Terkirim"
                    description="Terima kasih, data performance Anda telah berhasil dikirim. Saat ini data sedang menunggu proses persetujuan dari atasan."
                    icon="bi bi-hourglass-split"
                />
            </div>
        </div>
    </div>
</template>

<script>
import DynamicAlert from "../../../components/ui/alertUiV1.vue";
import { ElMessage } from "element-plus";
import { formatNilaiSatuan } from '@/utils/nilaiSatuan';

export default {
    name: "FinalReview",
    components: {
        DynamicAlert,
        ElMessage,
    },
    props: {
        kpiItems: {
            type: Array,
            required: true,
        },
        requiresPica: {
            type: Boolean,
            required: true,
        },
        modelValue: {
            type: Boolean,
            required: true,
        },
        loadingPerformance: {
            type: Boolean,
            required: null,
        },
        isFinalized: {
            type: Boolean,
            default: false,
        },
        statusPengajuan: {
            type: String,
            default: null,
        },
        gradingThreshold: {
            type: Number,
            default: 100,
        },
        performanceList: {
            type: Array,
            default: () => [],
        },
    },

    emits: ["preview-file", "update:modelValue"],
    computed: {
        redKpiItems() {
            return this.kpiItems.filter(
                (item) =>
                    item.actual !== null &&
                    item.achievement < this.gradingThreshold
            );
        },
    },
    methods: {
        previewFile(item) {
            const url =
                (item.dataSourceFile && item.dataSourceFile.url) || item.url;

            if (!url) {
                ElMessage({
                    message: "File tidak tersedia",
                    type: "error",
                    duration: 5000,
                });
                return;
            }
            window.open(url, "_blank");
        },
        formatValueWithUnit(value, unit) {
            /*
             * Aturannya pindah ke utils/nilaiSatuan.js. Sebelum ini metode yang
             * sama tersalin di enam komponen dengan TIGA aturan berbeda soal
             * pemangkasan spasi dan pengenalan mata uang.
             */
            return formatNilaiSatuan(value, unit);
        },
        getFileName(file) {
            if (!file) return "N/A";
            const path = file.path || file.name || "";
            return path.split("/").pop();
        },
        formatCurrency(value) {
            if (value === null || value === undefined) return "";
            return new Intl.NumberFormat("id-ID").format(value);
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
.review-item-card.not-in-period {
    background-color: #f8f9fa;
    border-left: 4px solid var(--hcis-legacy-bootstrap-gray);
}

.review-item-card.not-in-period .review-card-details {
    opacity: 0.6;
    pointer-events: none;
}

.final-review-container {
    margin-bottom: 2.5rem;
}
.review-items-list {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.review-item-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    background-color: var(--hcis-white);
    transition: all 0.3s ease;
    border-left: 5px solid var(--success-color);
}
.review-card-main {
    flex-grow: 1;
}
.review-card-title {
    margin: 0 0 1rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: #374151;
}
.review-card-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}
.info-block span {
    font-size: 0.8rem;
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
    font-size: 1.1rem;
    font-weight: 700;
}
.file-chip-button {
    background: none;
    border: none;
    padding: 0;
    margin: 0;
    cursor: pointer;
    text-align: left;
}
.file-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #f3f4f6;
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.875rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
}
.file-chip-button .file-chip:hover {
    background-color: #e5e7eb;
}
.text-muted {
    color: #9ca3af;
}

.review-section {
    margin-top: 2.5rem;
}
.review-section h4 {
    font-size: 1.25rem;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e5e7eb;
}
.pica-review-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.pica-review-item {
    background-color: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    border-left: 4px solid var(--hcis-red-500);
}
.pica-review-title {
    font-weight: bold;
    margin: 0 0 0.5rem 0;
}
.pica-review-item ul {
    list-style-type: none;
    padding-left: 0;
    margin: 0;
    font-size: 0.95rem;
}
.pica-review-item li {
    margin-bottom: 1rem;
}
.pica-review-item li:last-child {
    margin-bottom: 0;
}
.pica-review-item li strong {
    display: block;
    margin-bottom: 0.25rem;
    color: #374151;
}
.pica-review-item p {
    margin: 0;
    line-height: 1.6;
    color: #4b5563;
}
.agreement-container {
    margin-top: 2rem;
    padding: 1rem;
    background-color: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
}
.custom-checkbox {
    display: inline-flex;
    align-items: center;
    cursor: pointer;
    user-select: none;
}
.custom-checkbox input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
    height: 0;
    width: 0;
}
.custom-checkbox .checkmark {
    position: relative;
    height: 22px;
    width: 22px;
    background-color: #f3f4f6;
    border: 2px solid #9ca3af;
    border-radius: 0.375rem;
    transition: all 0.3s;
    flex-shrink: 0;
}
.custom-checkbox:hover input ~ .checkmark {
    background-color: #e5e7eb;
}
.custom-checkbox input:checked ~ .checkmark {
    background-color: var(--hcis-indigo-500);
    border-color: var(--hcis-indigo-500);
}
.custom-checkbox .checkmark:after {
    content: "";
    position: absolute;
    display: none;
    left: 7px;
    top: 2px;
    width: 6px;
    height: 12px;
    border: solid white;
    border-width: 0 3px 3px 0;
    transform: rotate(45deg);
}
.custom-checkbox input:checked ~ .checkmark:after {
    display: block;
}
.custom-checkbox .label-text {
    margin-left: 0.75rem;
    font-weight: 500;
    color: #374151;
}

/* ================================================ */
/* PENAMBAHAN UNTUK TAMPILAN LARGE (DESKTOP) */
/* ================================================ */
@media (min-width: 992px) {
    .review-items-list {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
    }
}
@media (max-width: 768px) {
    .review-item-card {
        flex-direction: column;
        align-items: flex-start;
    }
    .review-card-details {
        grid-template-columns: 1fr;
    }
    .info-block {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px dashed #f3f4f6;
        padding-bottom: 0.75rem;
        width: 100%;
    }
    .info-block:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
}
</style>
