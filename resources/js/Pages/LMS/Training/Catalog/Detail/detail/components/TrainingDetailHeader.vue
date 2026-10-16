<template>
    <div class="mb-4 bg-transparent border-0 header-shell">
        <div class="row g-0">
            <!-- Cover Image -->
            <div
                class="col-md-12 col-lg-3 detail-cover-col position-relative bg-light rounded-4 overflow-hidden border"
            >
                <img
                    v-if="hasValidImage"
                    :src="course.image"
                    :alt="course.title"
                    class="detail-cover-img w-100 h-100 object-fit-cover position-absolute top-0 start-0"
                    loading="lazy"
                    @error="handleImageError"
                />
                <div
                    v-else
                    class="detail-cover-placeholder w-100 h-100 d-flex align-items-center justify-content-center position-absolute top-0 start-0"
                >
                    <i class="bi bi-image text-muted" style="font-size: 3rem; opacity: 0.3"></i>
                </div>
                <div class="cover-gradient-overlay"></div>
            </div>

            <!-- Info -->
            <div
                class="col-md-12 col-lg-9 detail-info-col px-lg-4 py-4 py-lg-2 d-flex flex-column position-relative header-info-panel"
            >
                <!-- Floating Coordinator / Participant Stack (Hidden for now as requested) -->
                <div v-if="false" class="position-absolute top-0 end-0 d-none d-lg-flex gap-4">
                    <!-- Right-aligned stats -->
                </div>

                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.5rem; letter-spacing: -0.02em">
                    {{ course.title }}
                </h2>

                <div class="mb-1 text-muted fw-semibold" style="font-size: 0.85rem">
                    {{ course.category || 'Training Module' }}
                    <span v-if="course.provider" class="text-secondary fw-normal"> &bull; {{ course.provider }}</span>
                </div>

                <div class="mb-3 d-flex align-items-center flex-wrap gap-2" style="font-size: 0.85rem">
                    <span style="color: #d97706; font-weight: 500">
                        {{ formatDate(course.dateStart) }}
                        <span v-if="course.dateEnd">&nbsp;&ndash;&nbsp; {{ formatDate(course.dateEnd) }} </span>
                    </span>

                    <!-- Admin Mode Indicator -->
                    <span
                        v-if="viewMode === 'admin'"
                        class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-0 rounded-pill ms-2"
                        style="font-size: 0.7rem; display: inline-flex; align-items: center"
                    >
                        <i class="bi bi-person-badge me-1"></i>Monitoring: {{ employeeName || 'Karyawan' }}
                    </span>
                    <span
                        v-if="course.isCompleted"
                        class="badge bg-success-subtle text-success border border-success-subtle px-2 py-0 rounded-pill ms-2"
                        style="font-size: 0.7rem; display: inline-flex; align-items: center"
                    >
                        <i class="bi bi-check-circle-fill me-1"></i>Selesai
                    </span>
                </div>

                <div class="quick-chip-row mb-3">
                    <span class="quick-chip is-progress">
                        <i class="bi bi-bar-chart-steps me-1"></i>Progress {{ course.progress }}%
                    </span>
                    <span v-if="viewMode === 'admin'" class="quick-chip">
                        <i class="bi bi-journal-check me-1"></i>Mode
                        {{ viewMode === 'admin' ? 'Monitoring' : 'Belajar' }}
                    </span>
                    <span class="quick-chip" v-if="course.isCompleted">
                        <i class="bi bi-patch-check-fill me-1"></i>Semua aktivitas selesai
                    </span>
                </div>

                <div class="mb-4 flex-grow-1" style="max-width: 800px">
                    <p class="text-secondary mb-1" style="font-size: 0.88rem; line-height: 1.6">
                        {{ displayedDescription }}
                    </p>
                    <button
                        v-if="isDescriptionLong"
                        type="button"
                        class="desc-toggle-btn"
                        @click="showFullDescription = !showFullDescription"
                    >
                        {{ showFullDescription ? 'Tampilkan lebih sedikit' : 'Baca selengkapnya' }}
                    </button>
                </div>

                <!-- Clean Progress Bar -->
                <div class="progress-section mt-auto">
                    <div class="fw-bold text-dark mb-2" style="font-size: 0.9rem">Progres</div>
                    <div class="d-flex align-items-center gap-3">
                        <div
                            class="progress flex-grow-1"
                            style="height: 8px; border-radius: 10px; background-color: #e2e8f0; max-width: 400px"
                        >
                            <div
                                class="progress-bar rounded-pill"
                                :style="{ width: course.progress + '%', background: '#0ea5e9' }"
                            ></div>
                        </div>
                        <span class="fw-bold text-dark" style="font-size: 0.85rem">{{ course.progress }}%</span>
                    </div>
                </div>
            </div>

            <!-- Coordinator / Participant -->
            <div
                v-if="false"
                class="col-md-5 col-lg-3 detail-meta-col px-4 py-4 bg-light border-start d-flex flex-column justify-content-center"
            >
                <!-- Coordinator Stack -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold text-dark" style="font-size: 0.85rem"
                            >Coordinators
                            <span class="text-muted fw-normal">({{ course.coordinators.length }})</span></span
                        >
                        <a href="#" class="text-primary text-decoration-none fw-medium" style="font-size: 0.75rem"
                            >View more</a
                        >
                    </div>
                    <div class="avatar-stack d-flex align-items-center">
                        <div
                            v-for="(c, i) in visibleCoordinators"
                            :key="c.id"
                            class="avatar-circle shadow-sm border border-2 border-white"
                            :style="{ zIndex: 10 - i, marginLeft: i === 0 ? '0' : '-10px' }"
                            :title="c.name"
                        >
                            {{ c.initials }}
                        </div>
                        <div
                            v-if="extraCoordinators > 0"
                            class="avatar-circle avatar-extra shadow-sm border border-2 border-white"
                            :style="{ marginLeft: '-10px', zIndex: 0 }"
                        >
                            +{{ extraCoordinators }}
                        </div>
                    </div>
                </div>

                <!-- Participant Stack -->
                <div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-semibold text-dark" style="font-size: 0.85rem"
                            >Participants
                            <span class="text-muted fw-normal">({{ course.participants.length }})</span></span
                        >
                        <a href="#" class="text-primary text-decoration-none fw-medium" style="font-size: 0.75rem"
                            >View more</a
                        >
                    </div>
                    <div class="avatar-stack d-flex align-items-center">
                        <div
                            v-for="(p, i) in visibleParticipants"
                            :key="p.id"
                            class="avatar-circle shadow-sm border border-2 border-white"
                            :style="{
                                zIndex: 10 - i,
                                marginLeft: i === 0 ? '0' : '-10px',
                                backgroundColor: '#cbd5e1',
                                color: '#334155',
                            }"
                            :title="p.name"
                        >
                            {{ p.initials }}
                        </div>
                        <div
                            v-if="extraParticipants > 0"
                            class="avatar-circle avatar-extra shadow-sm border border-2 border-white"
                            :style="{ marginLeft: '-10px', zIndex: 0 }"
                        >
                            +{{ extraParticipants }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TrainingDetailHeader',
    props: {
        course: {
            type: Object,
            required: true,
        },
        viewMode: {
            type: String,
            default: 'user',
        },
        employeeName: {
            type: String,
            default: null,
        },
    },
    data() {
        return {
            imageLoadFailed: false,
            showFullDescription: false,
        };
    },
    computed: {
        hasValidImage() {
            return Boolean(this.course?.image) && !this.imageLoadFailed;
        },
        normalizedDescription() {
            return this.course?.description || 'Tidak ada deskripsi tersedia untuk training ini.';
        },
        isDescriptionLong() {
            return this.normalizedDescription.length > 210;
        },
        displayedDescription() {
            if (!this.isDescriptionLong || this.showFullDescription) {
                return this.normalizedDescription;
            }

            return `${this.normalizedDescription.slice(0, 210)}...`;
        },
        visibleCoordinators() {
            return this.course.coordinators.slice(0, 4);
        },
        extraCoordinators() {
            return Math.max(0, this.course.coordinators.length - 4);
        },
        visibleParticipants() {
            return this.course.participants.slice(0, 4);
        },
        extraParticipants() {
            return Math.max(0, this.course.participants.length - 4);
        },
    },
    watch: {
        'course.image'() {
            this.imageLoadFailed = false;
        },
        'course.description'() {
            this.showFullDescription = false;
        },
    },
    methods: {
        handleImageError() {
            this.imageLoadFailed = true;
        },
        formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            if (isNaN(d)) return dateStr;
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const hh = String(d.getHours()).padStart(2, '0');
            const mm = String(d.getMinutes()).padStart(2, '0');
            return `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}, ${hh}:${mm}`;
        },
    },
};
</script>

<style scoped>
.header-shell {
    border-radius: 18px;
    border: 1px solid var(--hcis-slate-200);
    overflow: hidden;
    background: linear-gradient(145deg, var(--hcis-white) 0%, #f8fbff 70%, var(--hcis-slate-50) 100%);
    box-shadow: 0 14px 30px rgba(15, 23, 42, 0.06);
}

.stitch-card {
    background: var(--surface, var(--hcis-white));
    border-radius: 12px;
    box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border, var(--hcis-slate-200)) !important;
}

.bi {
    display: inline-table !important;
}

.detail-cover-col {
    min-height: 240px;
}

.cover-gradient-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.08) 0%, rgba(15, 23, 42, 0.32) 100%);
    pointer-events: none;
}

.header-info-panel {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.92) 0%, rgba(241, 245, 249, 0.72) 100%);
}

.quick-chip-row {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.quick-chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    border: 1px solid #dbeafe;
    background: var(--hcis-white);
    color: #1e40af;
    font-size: 0.74rem;
    font-weight: 700;
    padding: 0.25rem 0.62rem;
}

.quick-chip.is-progress {
    background: #dbeafe;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

.desc-toggle-btn {
    border: 0;
    background: transparent;
    padding: 0;
    color: var(--hcis-blue-600);
    font-size: 0.8rem;
    font-weight: 700;
}

.desc-toggle-btn:hover {
    color: #1d4ed8;
    text-decoration: underline;
}

@media (max-width: 991.98px) {
    .detail-cover-col {
        min-height: 200px;
    }
    .detail-cover-img {
        position: static !important;
    }
    .detail-cover-placeholder {
        position: static !important;
    }

    .header-info-panel {
        border-top: 1px solid var(--hcis-slate-200);
    }
}

.avatar-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background-color: var(--primary, var(--hcis-indigo-500));
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
}

.avatar-extra {
    background-color: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    font-size: 0.75rem;
}
</style>
