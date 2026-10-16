<template>
    <div class="test-detail-app-container min-vh-100">
        <HeaderKpi
            title="Detail Jawaban Test"
            subtitle="Audit detail soal dan jawaban peserta dari third-party"
            :dayNight="dayNight"
            :formattedDate="formattedDate"
            :buttonAction="false"
            :showBack="true"
            @back="goBack"
        />

        <div class="page-wrap">
            <nav class="detail-breadcrumb mb-3">
                <a href="/lms/admin/monitoring" class="bc-link"><i class="bi bi-speedometer2 me-1"></i>Monitoring</a>
                <span class="bc-sep mx-2">/</span>
                <a v-if="trainingDetailHref" :href="trainingDetailHref" class="bc-link">
                    {{ detInfo?.nama_training || 'Detail Training' }}
                </a>
                <span v-else class="bc-link">
                    {{ detInfo?.nama_training || 'Detail Training' }}
                </span>
                <span v-if="detInfo?.nama_section" class="bc-sep mx-2">/</span>
                <span v-if="detInfo?.nama_section" class="bc-link">
                    {{ detInfo.nama_section }}
                </span>
                <span v-if="detInfo?.nama_aktivitas" class="bc-sep mx-2">/</span>
                <span v-if="detInfo?.nama_aktivitas" class="bc-current">
                    {{ detInfo.nama_aktivitas }}
                </span>
                <span v-if="!detInfo?.nama_aktivitas" class="bc-sep mx-2">/</span>
                <span v-if="!detInfo?.nama_aktivitas" class="bc-current">Detail Jawaban Test</span>
            </nav>

            <section class="hero-card">
                <div>
                    <p class="hero-eyebrow mb-1">{{ detInfo?.kode_aktivitas || 'Admin Audit' }}</p>
                    <h4 class="hero-title mb-1">{{ meta.nama_karyawan || detInfo?.nama_karyawan || '-' }}</h4>
                    <p class="hero-subtitle mb-0">
                        <span v-if="detInfo?.nama_aktivitas" class="badge badge-info me-2">
                            {{ detInfo.nama_aktivitas }}
                        </span>
                    </p>
                    <p class="hero-subtitle mb-0">
                        Signature: <span class="token-text">{{ meta.id_ujian_token || '-' }}</span>
                    </p>
                    <p class="hero-subtitle mb-0 mt-1">
                        Halaman {{ pagination.page || 1 }} dari {{ pagination.total_page || 1 }} · Total butir:
                        {{ pagination.total_data || questions.length || 0 }}
                    </p>
                </div>
                <div class="hero-actions">
                    <span class="status-pill" :class="summaryStatusClass">
                        <i class="bi" :class="summaryStatusIcon"></i>
                        {{ summary.status || 'Status tidak tersedia' }}
                    </span>
                    <button
                        class="btn btn-sm btn-primary rounded-pill px-3"
                        @click="fetchAnswerDetail"
                        :disabled="loading"
                    >
                        <i class="bi bi-arrow-repeat me-1"></i>Muat Ulang
                    </button>
                </div>
            </section>

            <section v-if="errorMsg" class="alert alert-warning mt-3 mb-0">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ errorMsg }}
            </section>

            <section v-if="loading" class="loading-card mt-3">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mb-0 text-muted">Memuat detail jawaban peserta...</p>
            </section>

            <template v-else>
                <section class="summary-grid mt-3">
                    <article class="summary-item">
                        <span class="summary-label">Nilai Akhir</span>
                        <span class="summary-value">{{ formatNullable(finalScore) }}</span>
                    </article>
                    <article class="summary-item">
                        <span class="summary-label">Total Soal</span>
                        <span class="summary-value">{{ formatNullable(summary.total) }}</span>
                    </article>
                    <article class="summary-item success">
                        <span class="summary-label">Benar</span>
                        <span class="summary-value">{{ formatNullable(summary.correct) }}</span>
                    </article>
                    <article class="summary-item danger">
                        <span class="summary-label">Salah</span>
                        <span class="summary-value">{{ formatNullable(summary.wrong) }}</span>
                    </article>
                    <article class="summary-item warning">
                        <span class="summary-label">Kosong</span>
                        <span class="summary-value">{{ formatNullable(calculatedSummary.empty) }}</span>
                    </article>
                </section>

                <section class="question-wrap mt-3">
                    <div class="question-head">
                        <h5 class="mb-0">Detail Soal & Jawaban</h5>
                        <span class="question-count">{{ questions.length }} butir</span>
                    </div>

                    <div v-if="!questions.length" class="empty-state">
                        <i class="bi bi-inboxes me-1"></i>Belum ada detail soal dari API third-party.
                    </div>

                    <article v-for="(item, idx) in questions" :key="`q-${idx}`" class="question-card">
                        <div class="question-title-row">
                            <div class="question-number">Soal {{ item.number || idx + 1 }}</div>
                            <span class="question-status" :class="questionStatusClass(item)">
                                {{ normalizeQuestionStatus(item) }}
                            </span>
                        </div>

                        <p class="question-text">{{ item.question || '-' }}</p>

                        <div class="answer-grid">
                            <div class="answer-box">
                                <span class="answer-label">Jawaban Peserta</span>
                                <span class="answer-value">{{
                                    formatNullable(
                                        item.user_answer_status
                                            ? `${item.user_answer_status}. ${item.user_answer}`
                                            : item.user_answer,
                                    )
                                }}</span>
                            </div>
                            <div class="answer-box">
                                <span class="answer-label">Jawaban Benar</span>
                                <span class="answer-value">{{ formatNullable(item.correct_answer) }}</span>
                            </div>
                            <div
                                class="answer-box"
                                v-if="item.score !== null && item.score !== undefined && item.score !== ''"
                            >
                                <span class="answer-label">Skor Soal</span>
                                <span class="answer-value"
                                    >{{ item.score
                                    }}<template v-if="item.max_score"> / {{ item.max_score }}</template></span
                                >
                            </div>
                        </div>

                        <div v-if="item.choices && item.choices.length" class="choice-grid">
                            <div
                                v-for="(choice, cIdx) in item.choices"
                                :key="`choice-${idx}-${cIdx}`"
                                class="choice-item"
                                :class="choiceClass(choice, item)"
                            >
                                <div class="choice-header">
                                    <span class="choice-code">{{ choice.status || '-' }}</span>
                                    <span v-if="choice.is_correct" class="choice-badge correct-key">
                                        <i class="bi bi-check2-circle me-1"></i>Kunci Jawaban
                                    </span>
                                    <span v-if="isUserSelectedChoice(choice, item)" class="choice-badge selected">
                                        <i class="bi bi-person-check me-1"></i>Dipilih Peserta
                                    </span>
                                </div>
                                <div class="choice-answer">{{ formatNullable(choice.answer) }}</div>
                            </div>
                        </div>

                        <div
                            class="image-grid"
                            v-if="item.answer_image_url || item.question_image_url || item.correct_answer_image_url"
                        >
                            <a
                                v-if="item.answer_image_url"
                                :href="item.answer_image_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="image-card"
                            >
                                <img :src="item.answer_image_url" alt="Gambar jawaban" />
                                <span class="image-caption">Gambar Jawaban</span>
                            </a>
                            <a
                                v-if="item.question_image_url"
                                :href="item.question_image_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="image-card"
                            >
                                <img :src="item.question_image_url" alt="Gambar soal" />
                                <span class="image-caption">Gambar Soal</span>
                            </a>
                            <a
                                v-if="item.correct_answer_image_url"
                                :href="item.correct_answer_image_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="image-card"
                            >
                                <img :src="item.correct_answer_image_url" alt="Gambar jawaban benar" />
                                <span class="image-caption">Gambar Jawaban Benar</span>
                            </a>
                        </div>

                        <!-- <details class="raw-details">
                            <summary>Lihat data mentah butir soal</summary>
                            <pre>{{ stringifyRaw(item.raw) }}</pre>
                        </details> -->
                    </article>
                </section>

                <section class="meta-grid mt-3">
                    <article class="meta-card">
                        <span class="meta-label">Waktu Mulai Akses</span>
                        <span class="meta-value">{{
                            formatDateTime(meta.waktu_mulai_akses || detInfo?.Waktu_Mulai_Akses)
                        }}</span>
                    </article>
                    <article class="meta-card">
                        <span class="meta-label">Waktu Batas Akses</span>
                        <span class="meta-value">{{
                            formatDateTime(meta.waktu_batas_akses || detInfo?.Waktu_Batas_Akses)
                        }}</span>
                    </article>
                    <article class="meta-card">
                        <span class="meta-label">Status LMS</span>
                        <span class="meta-value">{{
                            (meta.flag_selesai || detInfo?.Flag_Selesai) === 'Y' ? 'Selesai' : 'Belum Selesai'
                        }}</span>
                    </article>
                </section>
            </template>
        </div>
    </div>
</template>

<script>
import axios from 'axios';
import HeaderKpi from '@/components/legacy/header.vue';

export default {
    name: 'AdminAdminTrainingMonitoringTestDetail',
    components: { HeaderKpi },
    props: {
        detInfo: {
            type: Object,
            default: () => ({}),
        },
        penjadwalanDetId: {
            type: [String, Number],
            required: true,
        },
    },
    data() {
        return {
            loading: false,
            errorMsg: '',
            meta: {},
            summary: {},
            questions: [],
            pagination: {
                page: 1,
                total_page: 1,
                total_data: 0,
            },
        };
    },
    computed: {
        trainingDetailHref() {
            const materiId = this.detInfo?.encoded_materi_id;
            const kodeKaryawan =
                this.detInfo?.encoded_kode_karyawan || this.detInfo?.kode_karyawan || this.detInfo?.Kode_Karyawan;

            if (!materiId || !kodeKaryawan) {
                return null;
            }

            return `/lms/admin/monitoring/training/${materiId}/${kodeKaryawan}`;
        },
        finalScore() {
            const candidates = [this.meta?.skor_akhir, this.detInfo?.skor_akhir, this.summary?.score];

            for (const value of candidates) {
                if (value !== null && value !== undefined && value !== '') {
                    return value;
                }
            }

            return null;
        },
        dayNight() {
            const h = new Date().getHours();
            return h >= 6 && h < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            const d = new Date();
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = [
                'Januari',
                'Februari',
                'Maret',
                'April',
                'Mei',
                'Juni',
                'Juli',
                'Agustus',
                'September',
                'Oktober',
                'November',
                'Desember',
            ];
            return `${days[d.getDay()]}, ${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
        },
        calculatedSummary() {
            // Recalculate empty from questions where user didn't answer
            const empty = this.questions.filter((q) => this.isQuestionUnanswered(q)).length;
            return {
                ...this.summary,
                empty: empty || this.summary.empty || 0,
            };
        },
        summaryStatusClass() {
            const status = String(this.summary.status || '').toLowerCase();
            if (status.includes('selesai') || status.includes('lulus') || status.includes('passed')) return 'ok';
            if (status.includes('proses') || status.includes('ongoing') || status.includes('progress')) return 'warn';
            return 'muted';
        },
        summaryStatusIcon() {
            if (this.summaryStatusClass === 'ok') return 'bi-check-circle-fill';
            if (this.summaryStatusClass === 'warn') return 'bi-hourglass-split';
            return 'bi-info-circle';
        },
    },
    async mounted() {
        await this.fetchAnswerDetail();
    },
    methods: {
        async fetchAnswerDetail() {
            this.loading = true;
            this.errorMsg = '';

            try {
                const { data } = await axios.get(
                    `/lms/admin/monitoring/test-detail/${this.penjadwalanDetId}/answer-detail`,
                );
                if (!data?.success) {
                    this.errorMsg = data?.message || 'Gagal memuat detail jawaban test.';
                    return;
                }

                const result = data.result || data.data || {};
                this.meta = result.meta || {};
                this.summary = result.summary || {};
                this.questions = Array.isArray(result.questions) ? result.questions : [];
                this.pagination = {
                    page: result.pagination?.page || 1,
                    total_page: result.pagination?.total_page || 1,
                    total_data: result.pagination?.total_data || this.questions.length,
                };
            } catch (error) {
                this.errorMsg = error?.response?.data?.message || 'Terjadi kesalahan saat mengambil detail jawaban.';
            } finally {
                this.loading = false;
            }
        },
        goBack() {
            // Try to go back to history, fallback to monitoring page
            if (window.history.length > 1) {
                window.history.back();
            } else {
                window.location.href = '/lms/admin/monitoring';
            }
        },
        formatDateTime(value) {
            if (!value) return '-';
            const date = new Date(value);
            if (isNaN(date)) return value;
            return date.toLocaleString('id-ID', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
            });
        },
        formatNullable(value) {
            return value === null || value === undefined || value === '' ? '-' : value;
        },
        cleanText(value) {
            return String(value === null || value === undefined ? '' : value).trim();
        },
        isQuestionUnanswered(question) {
            return !this.cleanText(question?.user_answer_status) && !this.cleanText(question?.user_answer);
        },
        normalizeQuestionStatus(question) {
            if (this.isQuestionUnanswered(question)) return 'Kosong';

            const text = this.cleanText(question?.status).toLowerCase();
            if (text.includes('benar') || text.includes('correct')) return 'Benar';
            if (text.includes('salah') || text.includes('wrong')) return 'Salah';

            const score = Number(question?.score);
            if (!Number.isNaN(score)) {
                return score > 0 ? 'Benar' : 'Salah';
            }

            return this.cleanText(question?.status) || '-';
        },
        questionStatusClass(question) {
            const text = this.normalizeQuestionStatus(question).toLowerCase();
            if (text.includes('benar') || text.includes('correct')) return 'ok';
            if (text.includes('salah') || text.includes('wrong')) return 'danger';
            if (text.includes('kosong') || text.includes('blank') || text.includes('tidak dijawab')) return 'warning';
            return 'muted';
        },
        isUserSelectedChoice(choice, question) {
            const userChoice = this.cleanText(question?.user_answer_status).toLowerCase();
            const choiceStatus = this.cleanText(choice?.status).toLowerCase();
            return !!userChoice && userChoice === choiceStatus;
        },
        choiceClass(choice, question) {
            const classes = [];

            if (choice?.is_correct) {
                classes.push('is-correct');
            }

            if (this.isUserSelectedChoice(choice, question)) {
                classes.push('is-selected');
            }

            return classes;
        },
        stringifyRaw(value) {
            try {
                return JSON.stringify(value || {}, null, 2);
            } catch (error) {
                return '{}';
            }
        },
    },
};
</script>

<style scoped>
*,
*::before,
*::after {
    --primary: var(--hcis-indigo-500);
    --primary-dark: var(--hcis-indigo-600);
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
    --shadow-inset: inset 0 0px 0.3rem -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-lg-inset: inset 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --gradient-primary: linear-gradient(135deg, var(--hcis-legacy-violet-400) 0%, var(--hcis-legacy-violet-700) 100%);
    --gradient-success: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    --gradient-warning: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --gradient-info: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-dark: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
}
* {
    box-sizing: border-box;
}

.bi {
    display: inline-table !important;
}

.detail-breadcrumb {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    font-weight: 600;
    flex-wrap: nowrap;
    gap: 0.3rem;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 0.25rem 0;
    -webkit-overflow-scrolling: touch;
}

.detail-breadcrumb span,
.detail-breadcrumb a {
    white-space: nowrap;
    flex-shrink: 0;
}

.bc-link {
    color: #1d4ed8;
    text-decoration: none;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
}

.bc-link:hover {
    text-decoration: underline;
}

.bc-sep {
    color: var(--hcis-slate-400);
    margin: 0 0.15rem;
    flex-shrink: 0;
}

.bc-current {
    color: var(--hcis-slate-600);
    font-weight: 700;
    max-width: 150px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: inline-block;
}

.hero-card {
    border: 1px solid #bfdbfe;
    border-radius: 18px;
    background: linear-gradient(120deg, var(--hcis-white) 0%, #f8fbff 100%);
    padding: 1rem 1.1rem;
    box-shadow: 0 10px 20px rgba(30, 64, 175, 0.08);
    display: flex;
    justify-content: space-between;
    gap: 0.8rem;
    flex-wrap: wrap;
}

.hero-eyebrow {
    text-transform: uppercase;
    font-weight: 700;
    font-size: 0.72rem;
    letter-spacing: 0.06em;
    color: var(--hcis-slate-500);
}

.hero-title {
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.hero-subtitle {
    font-size: 0.82rem;
    color: var(--hcis-slate-600);
}

.token-text {
    font-family: 'Consolas', 'Courier New', monospace;
    font-size: 0.76rem;
    color: #1e3a8a;
    word-break: break-all;
}

.badge {
    display: inline-block;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

.badge-info {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
}

.hero-actions {
    display: flex;
    gap: 0.45rem;
    align-items: flex-start;
    flex-wrap: wrap;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    border-radius: 999px;
    padding: 0.28rem 0.68rem;
    font-size: 0.75rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.status-pill.ok {
    background: var(--hcis-green-100);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.status-pill.warn {
    background: var(--hcis-orange-100);
    color: #9a3412;
    border-color: var(--hcis-orange-300);
}

.status-pill.muted {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    border-color: var(--hcis-slate-300);
}

.loading-card {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: #fff;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.8rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.6rem;
}

.summary-item {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    background: #fff;
    padding: 0.65rem 0.75rem;
    display: flex;
    flex-direction: column;
    gap: 0.18rem;
}

.summary-item.success {
    border-color: var(--hcis-green-200);
    background: var(--hcis-green-50);
}

.summary-item.danger {
    border-color: var(--hcis-red-200);
    background: #fff1f2;
}

.summary-item.warning {
    border-color: #fed7aa;
    background: #fff7ed;
}

.summary-label {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-weight: 700;
}

.summary-value {
    font-size: 1.04rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.question-wrap {
    border: 1px solid var(--hcis-slate-200);
    border-radius: 14px;
    background: #fff;
    padding: 0.8rem;
}

.question-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}

.question-count {
    font-size: 0.76rem;
    font-weight: 700;
    color: var(--hcis-slate-600);
    background: var(--hcis-slate-200);
    border-radius: 999px;
    padding: 0.18rem 0.52rem;
}

.empty-state {
    margin-top: 0.7rem;
    border: 1px dashed var(--hcis-slate-300);
    border-radius: 12px;
    padding: 0.75rem;
    color: var(--hcis-slate-500);
    font-size: 0.86rem;
}

.question-card {
    margin-top: 0.7rem;
    border: 1px solid var(--hcis-slate-200);
    border-radius: 12px;
    padding: 0.7rem;
    background: var(--hcis-slate-50);
}

.question-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
}

.question-number {
    font-size: 0.82rem;
    font-weight: 800;
    color: var(--hcis-slate-900);
}

.question-status {
    border-radius: 999px;
    padding: 0.12rem 0.52rem;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.question-status.ok {
    background: var(--hcis-green-100);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.question-status.danger {
    background: var(--hcis-rose-100);
    color: var(--hcis-rose-700);
    border-color: #fecdd3;
}

.question-status.muted {
    background: var(--hcis-slate-200);
    color: var(--hcis-slate-600);
    border-color: var(--hcis-slate-300);
}

.question-status.warning {
    background: var(--hcis-orange-100);
    color: #9a3412;
    border-color: var(--hcis-orange-300);
}

.question-text {
    margin: 0.45rem 0 0;
    font-size: 0.9rem;
    color: var(--hcis-slate-800);
    line-height: 1.45;
}

.answer-grid {
    margin-top: 0.55rem;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.45rem;
}

.answer-box {
    border: 1px solid #dbeafe;
    background: #fff;
    border-radius: 10px;
    padding: 0.45rem 0.55rem;
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
}

.answer-label {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--hcis-slate-500);
}

.answer-value {
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
    font-weight: 600;
    word-break: break-word;
}

.choice-grid {
    margin-top: 0.55rem;
    display: grid;
    grid-template-columns: 1fr;
    gap: 0.38rem;
}

.choice-item {
    border: 1px solid #dbeafe;
    background: #fff;
    border-radius: 10px;
    padding: 0.45rem 0.55rem;
}

.choice-item.is-correct {
    border-color: #86efac;
    background: var(--hcis-green-50);
}

.choice-item.is-selected {
    box-shadow: inset 0 0 0 1px #60a5fa;
}

.choice-header {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    margin-bottom: 0.2rem;
    flex-wrap: wrap;
}

.choice-code {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 24px;
    height: 24px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.72rem;
    font-weight: 800;
}

.choice-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.12rem 0.45rem;
    background: var(--hcis-green-100);
    color: #166534;
    font-size: 0.68rem;
    font-weight: 700;
}

.choice-badge.correct-key {
    background: var(--hcis-green-50);
    color: #166534;
}

.choice-badge.selected {
    background: var(--hcis-sky-100);
    color: var(--hcis-sky-700);
}

.choice-answer {
    font-size: 0.82rem;
    color: var(--hcis-slate-900);
    line-height: 1.4;
}

.image-grid {
    margin-top: 0.55rem;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.5rem;
}

.image-card {
    border: 1px solid var(--hcis-slate-300);
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    text-decoration: none;
}

.image-card img {
    width: 100%;
    height: 160px;
    object-fit: cover;
    display: block;
}

.image-caption {
    display: block;
    padding: 0.32rem 0.5rem;
    font-size: 0.72rem;
    color: var(--hcis-slate-700);
    font-weight: 700;
}

.raw-details {
    margin-top: 0.5rem;
}

.raw-details summary {
    cursor: pointer;
    font-size: 0.76rem;
    color: var(--hcis-slate-600);
    font-weight: 700;
}

.raw-details pre {
    margin-top: 0.4rem;
    margin-bottom: 0;
    border-radius: 8px;
    background: var(--hcis-slate-900);
    color: var(--hcis-slate-200);
    padding: 0.55rem;
    font-size: 0.68rem;
    max-height: 220px;
    overflow: auto;
}

.meta-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.55rem;
}

.meta-card {
    border: 1px solid #dbeafe;
    border-radius: 12px;
    background: #f8fbff;
    padding: 0.6rem 0.7rem;
    display: flex;
    flex-direction: column;
    gap: 0.14rem;
}

.meta-label {
    font-size: 0.72rem;
    color: var(--hcis-slate-500);
    font-weight: 700;
    text-transform: uppercase;
}

.meta-value {
    font-size: 0.84rem;
    color: var(--hcis-slate-900);
    font-weight: 700;
}

@media (max-width: 991px) {
    .detail-breadcrumb {
        font-size: 0.8rem;
    }

    .bc-link {
        max-width: 150px;
    }

    .bc-current {
        max-width: 120px;
    }

    .summary-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .answer-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 767px) {
    /* .page-wrap {
        padding: 0.8rem 0.8rem 1.5rem;
    } */

    .detail-breadcrumb {
        font-size: 0.75rem;
        gap: 0.15rem;
    }

    .detail-breadcrumb .bc-link:first-child {
        font-size: 0;
    }

    .detail-breadcrumb .bc-link:first-child i {
        font-size: 0.85rem;
    }

    .detail-breadcrumb .bc-link:first-child .me-1 {
        margin-right: 0 !important;
    }

    .detail-breadcrumb .bc-sep:nth-of-type(1) {
        display: none;
    }

    .bc-link {
        max-width: 120px;
    }

    .bc-current {
        max-width: 120px;
    }

    .bc-sep {
        margin: 0 0.1rem;
    }

    .summary-grid,
    .meta-grid,
    .answer-grid,
    .image-grid {
        grid-template-columns: 1fr;
    }

    .question-head,
    .question-title-row {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>
