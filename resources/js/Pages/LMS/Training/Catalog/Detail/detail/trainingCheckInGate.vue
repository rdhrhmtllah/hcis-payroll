<template>
    <div class="training-gate-page">
        <HeaderKpi :title="pageTitle" :subtitle="pageSubtitle" :dayNight="dayNight" :formattedDate="formattedDate" />

        <div class="gate-shell">
            <div class="gate-card">
                <nav class="detail-breadcrumb">
                    <a href="/lms/training/catalog" class="bc-link"> <i class="bi bi-house me-1"></i>Training </a>
                    <span class="bc-sep mx-2">/</span>
                    <a href="/lms/training/catalog" class="bc-link">Training Catalog</a>
                    <span class="bc-sep mx-2">/</span>
                    <span class="bc-current">{{ materiTitle }}</span>
                </nav>

                <div class="gate-layout">
                    <div class="gate-hero">
                        <img v-if="materi.thumbnail" :src="materi.thumbnail" :alt="materi.judul" class="gate-cover" />
                        <div v-else class="gate-cover placeholder">
                            <i class="bi bi-journal-richtext"></i>
                        </div>
                    </div>

                    <div class="gate-content">
                        <span class="gate-badge">Langkah Wajib Sebelum Training</span>
                        <h1 class="gate-title">{{ materi.judul || 'Check-In Training' }}</h1>
                        <p class="gate-description">
                            {{
                                materi.deskripsi ||
                                'Silakan lakukan check-in terlebih dahulu sebelum memulai seluruh aktivitas training.'
                            }}
                        </p>

                        <div class="gate-alert" :class="activityItem ? 'is-info' : 'is-warning'">
                            <i
                                class="bi"
                                :class="activityItem ? 'bi-shield-check' : 'bi-exclamation-triangle-fill'"
                            ></i>
                            <span>{{ gateMessage }}</span>
                        </div>

                        <div class="gate-rules">
                            <div class="rule-item">
                                <i class="bi bi-camera-video"></i>
                                <span>Siapkan akses kamera untuk verifikasi wajah.</span>
                            </div>
                            <div class="rule-item">
                                <i class="bi bi-geo-alt"></i>
                                <span>Izinkan lokasi agar absensi tercatat dengan benar.</span>
                            </div>
                            <div class="rule-item">
                                <i class="bi bi-unlock"></i>
                                <span>Detail training akan terbuka otomatis setelah check-in berhasil.</span>
                            </div>
                        </div>

                        <div class="gate-actions">
                            <button
                                class="btn btn-primary rounded-pill px-4 py-2"
                                :disabled="!activityItem || submitting"
                                @click="openAbsensi"
                            >
                                <i class="bi bi-play-circle me-2"></i>
                                {{ submitting ? 'Memproses...' : 'Mulai Check-In' }}
                            </button>
                            <a href="/lms/training/catalog" class="btn btn-light border rounded-pill px-4 py-2">
                                Kembali ke Catalog
                            </a>
                        </div>

                        <p v-if="!activityItem" class="gate-helper">
                            Absensi belum dapat dimulai karena data aktivitas check-in untuk training ini belum lengkap.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <LmsAbsensi
            v-model:show="showAbsensi"
            type="In"
            :activityItem="activityItem || fallbackActivityItem"
            @absensi-complete="handleAbsensiComplete"
        />
    </div>
</template>

<script>
import HeaderKpi from '@/components/legacy/header.vue';
import LmsAbsensi from '../../../Attendance/LmsAbsensi.vue';

export default {
    name: 'TrainingCheckInGatePage',
    components: {
        HeaderKpi,
        LmsAbsensi,
    },
    props: {
        courseId: {
            type: [String, Number],
            required: true,
        },
        penjadwalanId: {
            type: [String, Number],
            default: null,
        },
        gateData: {
            type: Object,
            default: () => ({}),
        },
    },
    data() {
        return {
            now: new Date(),
            showAbsensi: false,
            submitting: false,
        };
    },
    computed: {
        materi() {
            return this.gateData?.materi || {};
        },
        materiTitle() {
            return this.materi?.judul || 'Check-In Training';
        },
        gateMessage() {
            return (
                this.gateData?.message ||
                'Training ini memerlukan check-in terlebih dahulu sebelum detail training ditampilkan.'
            );
        },
        activityItem() {
            return this.gateData?.activity_item || null;
        },
        fallbackActivityItem() {
            return {
                id_penjadwalan: null,
                id_penjadwalan_detail: null,
                id_penjadwalan_det: null,
                id_materi: Number(this.courseId),
                id_materi_det: null,
            };
        },
        pageTitle() {
            return 'Training';
        },
        pageSubtitle() {
            return 'Selesaikan check-in terlebih dahulu sebelum memulai materi training.';
        },
        dayNight() {
            const h = this.now.getHours();
            return h >= 6 && h < 18 ? 'Siang' : 'Malam';
        },
        formattedDate() {
            const d = this.now;
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
    },
    mounted() {
        this._clockTimer = setInterval(() => {
            this.now = new Date();
        }, 60000);
    },
    beforeUnmount() {
        clearInterval(this._clockTimer);
    },
    methods: {
        openAbsensi() {
            if (!this.activityItem) {
                this.$toast?.error('Data check-in untuk training ini belum lengkap. Silakan hubungi admin training.');
                return;
            }

            this.showAbsensi = true;
        },
        handleAbsensiComplete() {
            this.submitting = true;
            this.showAbsensi = false;
            this.$toast?.success('Check-in berhasil. Detail training sedang dibuka.');
            const detailUrl = this.penjadwalanId
                ? `/lms/training/schedule/${this.penjadwalanId}`
                : `/lms/training/${this.courseId}`;

            this.$inertia.visit(detailUrl, {
                replace: true,
            });
        },
    },
};
</script>

<style scoped>
.training-gate-page {
    min-height: 100vh;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-800);
}

.gate-shell {
    padding: 0 0 2rem;
}

.gate-card {
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    border-radius: 20px;
    padding: 1.25rem;
    box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
}

.detail-breadcrumb {
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.bc-link {
    color: var(--hcis-slate-500);
    text-decoration: none;
    font-weight: 500;
}

.bc-current {
    color: var(--hcis-blue-600);
    font-weight: 700;
}

.bc-sep {
    color: var(--hcis-slate-300);
}

.gate-layout {
    display: grid;
    grid-template-columns: minmax(260px, 380px) minmax(0, 1fr);
    gap: 1.5rem;
    align-items: stretch;
}

.gate-hero {
    min-height: 100%;
}

.gate-cover {
    width: 100%;
    height: 100%;
    min-height: 420px;
    object-fit: cover;
    border-radius: 18px;
    display: block;
}

.gate-cover.placeholder {
    background: linear-gradient(135deg, #dbeafe 0%, #eff6ff 100%);
    color: var(--hcis-blue-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 4rem;
}

.gate-content {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 1rem;
    padding: 0.5rem 0.25rem;
}

.gate-badge {
    display: inline-flex;
    align-self: flex-start;
    padding: 0.45rem 0.85rem;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 0.8rem;
    font-weight: 700;
}

.gate-title {
    font-size: clamp(1.8rem, 2.4vw, 2.7rem);
    line-height: 1.1;
    font-weight: 800;
    margin: 0;
}

.gate-description {
    font-size: 1rem;
    line-height: 1.7;
    color: var(--hcis-slate-600);
    margin: 0;
    max-width: 60ch;
}

.gate-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem 1.1rem;
    border-radius: 16px;
    border: 1px solid transparent;
    line-height: 1.6;
}

.gate-alert.is-info {
    background: rgba(37, 99, 235, 0.08);
    color: #1d4ed8;
    border-color: rgba(37, 99, 235, 0.15);
}

.gate-alert.is-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--hcis-amber-700);
    border-color: rgba(245, 158, 11, 0.18);
}

.gate-rules {
    display: grid;
    gap: 0.75rem;
}

.rule-item {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    padding: 0.95rem 1rem;
    border-radius: 14px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-700);
    font-weight: 500;
}

.rule-item i {
    color: var(--hcis-blue-600);
}

.gate-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.gate-helper {
    margin: 0;
    color: var(--hcis-amber-700);
    font-size: 0.92rem;
}

@media (max-width: 991px) {
    .gate-layout {
        grid-template-columns: 1fr;
    }

    .gate-cover {
        min-height: 260px;
    }
}

@media (max-width: 767px) {
    .gate-card {
        padding: 1rem;
        border-radius: 16px;
    }

    .gate-actions .btn {
        width: 100%;
    }
}
</style>
