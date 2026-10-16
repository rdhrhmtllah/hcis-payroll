<template>
    <component :is="componentName" v-if="componentName" v-bind="componentProps" v-on="componentEvents" />
</template>

<script>
import TrainingDetailTest from './TrainingDetailTest.vue';
import TrainingDetailMaterials from './TrainingDetailMaterials.vue';
import TrainingDetailEvaluation from './TrainingDetailEvaluation.vue';

/**
 * Dynamic Section Renderer
 *
 * Renders the correct child component based on `kode_aktivitas` (from N_LMS_Master_Aktivitas)
 * and `konten.tipe` (from N_LMS_Master_Konten).
 *
 * Mapping:
 *   ABS → tidak dirender di sini. trainingDetail.vue melewati item ABS
 *         sebelum menyusun daftar section, jadi renderer ini tidak pernah
 *         menerimanya. Baris ini sebelumnya menyebut TrainingDetailCheckIn,
 *         komponen yang sudah tidak dipakai dan kini berada di legacy/archive.
 *   PRT → TrainingDetailTest (type=pre)
 *   MDL → TrainingDetailMaterials (groups all learn-content items)
 *   PTT → TrainingDetailTest (type=post)
 *   QIZ → TrainingDetailMaterials (single row quiz)
 *   ELS → TrainingDetailEvaluation
 *   (konten.tipe === 'video') → TrainingDetailMaterials
 */
export default {
    name: 'TrainingDetailSectionRenderer',
    components: {
        TrainingDetailTest,
        TrainingDetailMaterials,
        TrainingDetailEvaluation,
    },
    props: {
        /**
         * For single-item sections (PRT, PTT, QIZ, ELS, video):
         * A single item object from the API response.
         */
        item: { type: Object, default: null },

        /**
         * For material sections (MDL): array of items grouped together.
         */
        materials: { type: Array, default: () => [] },

        /**
         * The type of section to render. Derived from kode_aktivitas.
         * One of: PRT, MDL, PTT, QIZ, ELS, VIDEO
         */
        sectionType: { type: String, required: true },

        /**
         * Whether this section is locked (previous steps not completed).
         */
        locked: { type: Boolean, default: false },

        /**
         * Status tracking for tests/evaluation.
         */
        status: { type: String, default: 'not-started' },
        statusMap: { type: Object, default: () => ({}) },
        resultSummary: { type: Object, default: null },
        viewMode: { type: String, default: 'user' },
    },
    emits: ['start-test', 'view-test-detail', 'view-material', 'complete-video', 'complete-quiz', 'start-eval'],
    computed: {
        componentName() {
            const map = {
                PRT: 'TrainingDetailTest',
                MDL: 'TrainingDetailMaterials',
                PTT: 'TrainingDetailTest',
                QIZ: 'TrainingDetailMaterials',
                ELS: 'TrainingDetailEvaluation',
                VIDEO: 'TrainingDetailMaterials',
            };
            return map[this.sectionType] || null;
        },
        componentProps() {
            switch (this.sectionType) {
                case 'PRT':
                    return {
                        type: 'pre',
                        testData: {
                            name: this.item?.nama_aktivitas || 'Pre-Test',
                            duration: this.item?.durasi ? `${this.item.durasi} Minutes` : null,
                            prerequisiteLabel: this.item?.label_materi_det_syarat || null,
                            jadwalMulai: this.item?.jadwal_mulai || this.item?.waktu_mulai_akses || null,
                            jadwalAkhir: this.item?.jadwal_akhir || this.item?.waktu_batas_akses || null,
                            status: this.status,
                            score: this.item?.skor_akhir != null ? parseFloat(this.item.skor_akhir) : null,
                        },
                        locked: this.locked,
                        viewMode: this.viewMode,
                    };
                case 'PTT':
                    return {
                        type: 'post',
                        testData: {
                            name: this.item?.nama_aktivitas || 'Post-Test',
                            duration: this.item?.durasi ? `${this.item.durasi} Minutes` : null,
                            prerequisiteLabel: this.item?.label_materi_det_syarat || null,
                            jadwalMulai: this.item?.jadwal_mulai || this.item?.waktu_mulai_akses || null,
                            jadwalAkhir: this.item?.jadwal_akhir || this.item?.waktu_batas_akses || null,
                            status: this.status,
                            score: this.item?.skor_akhir != null ? parseFloat(this.item.skor_akhir) : null,
                            learningGain:
                                this.resultSummary?.score_learning_gain_display ??
                                (this.resultSummary?.score_learning_gain != null
                                    ? String(this.resultSummary.score_learning_gain)
                                    : null),
                        },
                        resultSummary: this.resultSummary,
                        locked: this.locked,
                        viewMode: this.viewMode,
                    };
                case 'MDL':
                case 'QIZ':
                case 'VIDEO': {
                    const sourceItems = this.sectionType === 'MDL' ? this.materials : this.item ? [this.item] : [];

                    return {
                        materials: sourceItems.map((m) => {
                            const activityCode = m.kode_aktivitas || 'MDL';
                            const isVideoActivity =
                                m.konten?.tipe === 'video' &&
                                !['PRT', 'PTT', 'QIZ', 'ELS', 'ABS'].includes(activityCode);
                            const activityType =
                                activityCode === 'QIZ' ? 'quiz' : isVideoActivity ? 'video' : 'material';
                            const itemStatus =
                                this.statusMap[m.id_materi_det] ||
                                (this.item?.id_materi_det === m.id_materi_det ? this.status : 'not-started');

                            return {
                                id: m.id_materi_det,
                                name: m.konten?.judul || m.nama_aktivitas || m.keterangan_level || 'Materi',
                                subtitle: m.keterangan_level || '',
                                activityType,
                                activityCode,
                                fileType: m.konten?.tipe || (activityType === 'video' ? 'video' : 'pdf'),
                                status: itemStatus,
                                completed: ['done', 'passed'].includes(itemStatus),
                                downloadable: m.flag_download === 'Y' && Boolean(m.konten?.download_url),
                                downloadUrl: m.konten?.download_url || null,
                                score: m.skor_akhir != null ? parseFloat(m.skor_akhir) : null,
                                raw: m,
                            };
                        }),
                        locked: this.locked,
                    };
                }
                case 'ELS':
                    return {
                        evaluation: {
                            name: this.item?.nama_aktivitas || 'Feedback & Evaluation',
                            duration: this.item?.durasi ? `${this.item.durasi} Minutes` : '15 Minutes',
                            questions: this.item?.keterangan_level || '-',
                            status: this.status,
                        },
                        locked: this.locked,
                    };
                default:
                    return {};
            }
        },
        componentEvents() {
            switch (this.sectionType) {
                case 'PRT':
                case 'PTT':
                    return {
                        'start-test': () => this.$emit('start-test'),
                        'view-test-detail': () => this.$emit('view-test-detail', this.item?.id_penjadwalan_det || null),
                    };
                case 'MDL':
                case 'QIZ':
                case 'VIDEO':
                    return {
                        'view-material': (payload) => this.$emit('view-material', payload),
                        'complete-video': (payload) => this.$emit('complete-video', payload),
                        'complete-quiz': (payload) => this.$emit('complete-quiz', payload),
                    };
                case 'ELS':
                    return {
                        'start-eval': () => this.$emit('start-eval'),
                    };
                default:
                    return {};
            }
        },
    },
};
</script>

<style scoped>
.bg-light {
    background-color: #f8f9fa !important;
}

.btn-light {
    background-color: #f8f9fa !important;
    border: 1px solid var(--hcis-slate-200) !important;
    color: #212529 !important;
}
</style>
