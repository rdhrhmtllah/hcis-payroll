<template>
    <div class="mb-4">
        <div
            v-if="locked"
            class="alert alert-warning py-3 mb-3 bg-warning-subtle text-warning border-0 d-flex align-items-center rounded-3 shadow-sm"
            style="font-size: 0.85rem"
        >
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            {{ lockedMessage }}
        </div>

        <div class="stitch-card p-3 p-md-4" :class="{ 'border-success bg-success-soft': testData.status === 'passed' }">
            <div class="row align-items-center g-3 rounded-4 p-1">
                <div class="col-auto">
                    <div
                        class="icon-shape rounded-4 d-flex align-items-center justify-content-center bg-light border"
                        :class="{
                            'border-success text-success': testData.status === 'passed',
                            'text-primary border-primary border-opacity-25': testData.status !== 'passed',
                        }"
                        style="width: 50px; height: 50px"
                    >
                        <i class="fs-4 bi bi-file-earmark-text"></i>
                    </div>
                </div>

                <div class="col-md-5 col-sm-12">
                    <h6 class="mb-1 fw-bold text-dark" style="font-size: 1.05rem">{{ testData.name }}</h6>

                    <div
                        v-if="testData.status === 'passed'"
                        class="d-flex align-items-center text-success fw-bold mt-1"
                        style="font-size: 0.8rem"
                    >
                        <i class="bi bi-check-circle-fill me-1"></i> Passed
                    </div>
                    <div
                        v-else-if="testData.status === 'failed' || testData.status === 'remedial'"
                        class="d-flex align-items-center text-danger fw-bold mt-1"
                        style="font-size: 0.8rem"
                    >
                        <i class="bi bi-x-circle-fill me-1"></i> Failed
                    </div>

                    <div
                        v-if="testData.jadwalMulai || testData.jadwalAkhir"
                        class="text-muted mt-2 fw-medium"
                        style="font-size: 0.75rem"
                    >
                        <span v-if="testData.jadwalMulai">Mulai: {{ formatDateTime(testData.jadwalMulai) }}</span>
                        <span v-if="testData.jadwalAkhir" class="ms-2"
                            >Akhir: {{ formatDateTime(testData.jadwalAkhir) }}</span
                        >
                    </div>
                </div>

                <!-- Duration & Question Columns -->
                <div class="col-md-4 col-sm-12 d-flex flex-wrap gap-4 align-items-center border-start ps-4">
                    <div v-if="testData.duration" class="d-flex flex-column">
                        <span class="text-secondary fw-semibold mb-1" style="font-size: 0.75rem">Duration</span>
                        <span class="fw-bold text-dark" style="font-size: 0.85rem">{{ testData.duration }}</span>
                    </div>
                    <div v-if="testData.questionCount" class="d-flex flex-column">
                        <span class="text-secondary fw-semibold mb-1" style="font-size: 0.75rem">Question</span>
                        <span class="fw-bold text-dark" style="font-size: 0.85rem">{{ testData.questionCount }}</span>
                    </div>
                    <div v-if="testData.minimumScore" class="d-flex flex-column">
                        <span class="text-secondary fw-semibold mb-1" style="font-size: 0.75rem">Minimum Score</span>
                        <span class="fw-bold text-dark" style="font-size: 0.85rem">{{ testData.minimumScore }}</span>
                    </div>
                </div>

                <div class="col d-flex justify-content-end align-items-center">
                    <div class="text-end button-detail-test">
                        <template v-if="viewMode === 'admin'">
                            <button
                                class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-medium shadow-sm transition-all"
                                @click="handleViewDetail"
                            >
                                Lihat Detail Test <i class="bi bi-search ms-1"></i>
                            </button>
                            <span
                                v-if="isCompleted"
                                class="badge bg-success-subtle text-success px-3 py-2 rounded-pill fw-bold ms-2"
                                style="font-size: 0.85rem"
                            >
                                <i class="bi bi-check-circle-fill me-1"></i>
                                <span v-if="testData.score != null">{{ scoreLabel }}: {{ testData.score }}</span>
                                <span v-else>Passed</span>
                            </span>
                        </template>
                        <template v-else>
                            <button
                                v-if="!isCompleted"
                                class="btn btn-sm rounded-pill px-3 fw-medium shadow-sm transition-all"
                                :class="isActionDisabled ? 'btn-outline-secondary' : 'btn-primary'"
                                :disabled="isActionDisabled"
                                @click="handleStartTest"
                            >
                                {{ actionLabel }} <i class="bi bi-arrow-right-short ms-1"></i>
                            </button>
                        </template>
                        <div v-if="statusHint && !isCompleted" class="mt-2 text-muted test-status-hint">
                            <i class="bi bi-info-circle me-1"></i>{{ statusHint }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Score Accordion (Appears below the Main Card if completed) -->
        <div
            v-if="isCompleted && testData.score != null"
            class="mt-2 rounded-3 overflow-hidden border"
            style="background: #f8fafc; cursor: pointer"
        >
            <div
                class="d-flex align-items-center justify-content-between p-3"
                @click="showScoreDetail = !showScoreDetail"
            >
                <span class="text-secondary fw-medium" style="font-size: 0.85rem">{{ scoreLabel }}</span>
                <div class="d-flex align-items-center gap-3">
                    <span class="fw-bold text-dark fs-5">{{ testData.score }}</span>
                    <i class="bi text-secondary" :class="showScoreDetail ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                </div>
            </div>

            <div v-if="showScoreDetail" class="bg-white border-top p-0">
                <table class="table table-sm table-borderless mb-0" style="font-size: 0.85rem">
                    <tbody>
                        <tr v-for="(comp, i) in testData.components" :key="i" class="border-bottom">
                            <td class="ps-4 py-3 text-dark fw-medium">{{ comp.name }}</td>
                            <td class="py-3 text-secondary text-end pe-4">
                                {{ comp.detail_score }} / {{ comp.max_score }}
                            </td>
                            <td class="py-3 fw-bold text-dark text-center border-start" style="width: 80px">
                                {{ comp.score }}
                            </td>
                        </tr>
                        <tr class="border-bottom bg-light">
                            <td colspan="2" class="ps-4 py-3 text-dark fw-bold">{{ scoreLabel }}</td>
                            <td class="py-3 fw-bold text-dark text-center border-start">{{ testData.score }}</td>
                        </tr>
                        <tr
                            v-if="type === 'post' && resultSummary && resultSummary.has_post_test"
                            class="border-bottom bg-light"
                        >
                            <td class="ps-4 py-3 text-dark fw-bold">Keterangan Kelulusan</td>
                            <td class="py-3 text-secondary text-end pe-4" style="font-size: 0.75rem">
                                Standar: {{ standarKelulusanLabel }}
                            </td>
                            <td class="py-3 text-center border-start">
                                <span
                                    class="badge rounded-pill px-3 py-2 fw-semibold"
                                    :class="kelulusanBadgeClass"
                                >
                                    {{ kelulusanLabel }}
                                </span>
                            </td>
                        </tr>
                        <!-- Learning Gain Only appears if API supplies it via flag -->
                        <tr v-if="showLearningGain" class="bg-light">
                            <td class="ps-4 py-3 text-dark fw-bold">Learning Gain</td>
                            <td class="py-3 text-secondary text-end pe-4" style="font-size: 0.75rem">
                                Pos Test - Pre Test / Pre Test x 100%
                            </td>
                            <td class="py-3 fw-bold text-dark text-center border-start">
                                {{ formattedLearningGainValue }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TrainingDetailTest',
    props: {
        type: { type: String, default: 'pre' },
        testData: { type: Object, required: true },
        resultSummary: { type: Object, default: null },
        locked: { type: Boolean, default: false },
        viewMode: { type: String, default: 'user' },
    },
    emits: ['start-test', 'view-test-detail'],
    data() {
        return {
            now: new Date(),
            timerId: null,
            showScoreDetail: false,
        };
    },
    computed: {
        sectionTitle() {
            return this.type === 'post' ? 'Post Test' : 'Pre Test';
        },
        scoreLabel() {
            return this.type === 'post' ? 'Post Test' : 'Total Score';
        },
        learningGainValue() {
            return this.testData?.learningGain ?? this.resultSummary?.score_learning_gain_display ?? null;
        },
        formattedLearningGainValue() {
            if (this.learningGainValue === null || this.learningGainValue === undefined || this.learningGainValue === '') {
                return '-';
            }

            return `${this.normalizeDisplayNumber(this.learningGainValue)}%`;
        },
        // Standar kelulusan yang belum diatur harus dikatakan apa adanya. Sebelumnya
        // ia tampil sebagai "-" merah, yang terbaca sebagai TIDAK LULUS padahal
        // sistem memang belum punya dasar untuk menilai.
        standarKelulusanLabel() {
            const standar = this.resultSummary?.standar_kelulusan;
            if (standar === null || standar === undefined || standar === '') {
                return 'belum diatur';
            }

            return this.formatScoreValue(standar);
        },
        kelulusanLabel() {
            if (this.resultSummary?.keterangan_kelulusan) {
                return this.resultSummary.keterangan_kelulusan;
            }

            const standar = this.resultSummary?.standar_kelulusan;
            return standar === null || standar === undefined || standar === ''
                ? 'Belum dapat dinilai'
                : '-';
        },
        kelulusanBadgeClass() {
            const status = this.resultSummary?.kelulusan_status;
            if (status === 'passed') {
                return 'bg-success-subtle text-success';
            }
            if (status === 'failed') {
                return 'bg-danger-subtle text-danger';
            }

            // 'unknown' bukan gagal. Mewarnainya merah adalah menuduh.
            return 'bg-secondary-subtle text-secondary';
        },
        showLearningGain() {
            return this.type === 'post' && this.learningGainValue !== null && this.learningGainValue !== undefined;
        },
        isCompleted() {
            return this.testData.status === 'passed' || this.testData.status === 'done';
        },
        actionLabel() {
            if (this.isBeforeSchedule) {
                return 'Belum Dibuka';
            }

            if (this.isAfterSchedule) {
                return 'Jadwal Berakhir';
            }

            return this.testData.status === 'in-progress' ? 'Lanjutkan Ujian' : 'Mulai Ujian';
        },
        lockedMessage() {
            return 'Must complete all previous activities to be able to take the ' + this.sectionTitle;
        },
        hasMetaInfo() {
            return Boolean(this.testData.duration || this.testData.prerequisiteLabel);
        },
        parsedJadwalMulai() {
            return this.parseDate(this.testData.jadwalMulai);
        },
        parsedJadwalAkhir() {
            return this.parseDate(this.testData.jadwalAkhir);
        },
        isBeforeSchedule() {
            if (!this.parsedJadwalMulai) {
                return false;
            }

            return this.now < this.parsedJadwalMulai;
        },
        isAfterSchedule() {
            if (!this.parsedJadwalAkhir) {
                return false;
            }

            return this.now > this.parsedJadwalAkhir;
        },
        isActionDisabled() {
            return this.locked || this.isBeforeSchedule || this.isAfterSchedule;
        },
        statusHint() {
            if (this.isBeforeSchedule) {
                return `Ujian belum dapat dibuka. Silakan mulai pada ${this.formatDateTime(this.testData.jadwalMulai)}.`;
            }

            if (this.isAfterSchedule) {
                return `Jadwal ujian sudah berakhir pada ${this.formatDateTime(this.testData.jadwalAkhir)}.`;
            }

            if (this.testData.status === 'in-progress') {
                return 'Status ujian sedang berlangsung. Gunakan tombol di atas untuk membuka kembali halaman ujian.';
            }

            if (this.isCompleted) {
                return 'Status ujian sudah selesai.';
            }

            return null;
        },
    },
    mounted() {
        this.timerId = window.setInterval(() => {
            this.now = new Date();
        }, 30000);
    },
    beforeUnmount() {
        if (this.timerId) {
            window.clearInterval(this.timerId);
        }
    },
    methods: {
        handleViewDetail() {
            this.$emit('view-test-detail');
        },
        handleStartTest() {
            if (this.isActionDisabled) {
                return;
            }

            this.$emit('start-test');
        },
        parseDate(value) {
            if (!value) return null;

            const parsed = new Date(value);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        },
        formatDateTime(value) {
            if (!value) return '-';

            const date = this.parseDate(value);
            if (!date) {
                return value;
            }

            return date.toLocaleString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        formatScoreValue(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            return this.normalizeDisplayNumber(value);
        },
        normalizeDisplayNumber(value) {
            if (value === null || value === undefined || value === '') {
                return '-';
            }

            const rawValue = String(value).trim();
            const numericValue = Number(rawValue);

            if (!Number.isFinite(numericValue)) {
                return rawValue;
            }

            return numericValue.toString();
        },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}

.button-detail-test {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.stitch-card {
    background: var(--surface, var(--hcis-white));
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 12px;
    box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
    transition: all 0.2s;
}

.bg-success-soft {
    background-color: var(--hcis-green-50) !important;
    border-color: var(--hcis-green-200) !important;
}

.icon-shape {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
}

.test-schedule {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    font-size: 0.78rem;
    line-height: 1.45;
}

.test-status-hint {
    font-size: 0.76rem;
    line-height: 1.45;
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
}
</style>
