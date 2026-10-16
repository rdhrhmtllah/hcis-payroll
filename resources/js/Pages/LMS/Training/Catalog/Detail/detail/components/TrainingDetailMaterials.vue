<template>
    <div class="mb-4">
        <div
            v-if="locked"
            class="alert alert-warning py-3 mb-3 bg-warning-subtle text-warning border-0 d-flex align-items-center rounded-3 shadow-sm"
            style="font-size: 0.85rem"
        >
            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
            Must complete Pre Test &amp; Check-In to be able to view Materials
        </div>

        <div class="stitch-card overflow-hidden">
            <div class="table-responsive">
                <table class="table mb-0 align-middle custom-table table-borderless">
                    <thead class="text-secondary border-bottom" style="font-size: 0.85rem; font-weight: 600">
                        <tr>
                            <th class="border-0 fw-semibold py-3 px-4">Name</th>
                            <th class="border-0 fw-semibold py-3 px-4 text-center">Type</th>
                            <th class="border-0 fw-semibold py-3 px-4 text-center">
                                Completed <i class="bi bi-caret-down-fill ms-1 sort-icon"></i>
                            </th>
                            <th class="border-0 fw-semibold py-3 px-4 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="mat in pagedMaterials"
                            :id="`item-mdl-${mat.id}`"
                            :key="mat.id"
                            class="border-bottom border-light"
                        >
                            <td class="py-3 px-4" data-label="Name">
                                <div class="d-flex align-items-start">
                                    <div
                                        class="file-icon rounded p-2 me-3 d-flex align-items-center justify-content-center"
                                        :class="getIconClass(mat)"
                                    >
                                        <i class="fs-5" :class="getTypeIcon(mat)"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="text-dark fw-bold item-name">{{ mat.name }}</div>
                                        <div v-if="mat.subtitle" class="text-muted item-subtitle">
                                            {{ mat.subtitle }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center" data-label="Type">
                                <span class="type-badge" :class="`type-${mat.activityType}`">
                                    {{ getTypeLabel(mat) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center" data-label="Completed">
                                <span v-if="mat.completed" class="status-pill is-done">
                                    <i class="bi bi-check-circle-fill me-1"></i>Selesai
                                </span>
                                <span v-else-if="mat.status === 'in-progress'" class="status-pill is-progress">
                                    <i class="bi bi-play-circle-fill me-1"></i>Berjalan
                                </span>
                                <span v-else class="status-pill is-idle">
                                    <i class="bi bi-dash-circle me-1"></i>Belum
                                </span>
                            </td>
                            <td class="py-3 px-4 text-end" data-label="Action">
                                <div class="d-inline-flex align-items-center gap-2 flex-wrap justify-content-end">
                                    <a
                                        v-if="mat.downloadable"
                                        class="btn btn-sm btn-light border rounded-pill px-3 text-secondary transition-all"
                                        :class="{ disabled: locked }"
                                        :href="locked ? undefined : mat.downloadUrl"
                                        :aria-disabled="locked ? 'true' : 'false'"
                                        :tabindex="locked ? -1 : 0"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        @click="locked && $event.preventDefault()"
                                    >
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                    <button
                                        class="btn btn-sm rounded-pill px-3 action-btn transition-all"
                                        :class="getActionButtonClass(mat)"
                                        :disabled="locked"
                                        :title="getActionTitle(mat)"
                                        @click="handlePrimaryAction(mat)"
                                    >
                                        <i class="bi" :class="getActionIcon(mat)"></i>
                                        <span class="ms-1">{{ getActionLabel(mat) }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="pagedMaterials.length === 0">
                            <td colspan="4" class="text-center text-muted py-5" style="font-size: 0.9rem">
                                <i class="bi bi-folder2-open fs-2 d-block mb-3 text-secondary opacity-50"></i>
                                Belum ada materi tersedia.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div
                class="d-flex align-items-center justify-content-between p-3 bg-white border-top border-light flex-wrap gap-3"
            >
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted" style="font-size: 0.8rem">Items per Page</span>
                    <select
                        v-model="perPage"
                        class="form-select form-select-sm border-0 bg-light rounded-pill shadow-none cursor-pointer items-per-page-select"
                        style="font-size: 0.8rem; border: 1px solid #e2e8f0 !important"
                    >
                        <option :value="5">5</option>
                        <option :value="10">10</option>
                        <option :value="20">20</option>
                    </select>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button
                        class="btn btn-sm btn-light rounded-circle pagination-btn d-flex align-items-center justify-content-center"
                        :disabled="page <= 1"
                        @click="page--"
                    >
                        <i class="bi bi-chevron-left"></i>
                    </button>
                    <button
                        v-for="p in totalPages"
                        :key="p"
                        class="btn btn-sm rounded-circle pagination-num d-flex align-items-center justify-content-center fw-semibold"
                        :class="p === page ? 'btn-primary shadow-sm' : 'btn-light text-secondary'"
                        @click="page = p"
                    >
                        {{ p }}
                    </button>
                    <button
                        class="btn btn-sm btn-light rounded-circle pagination-btn d-flex align-items-center justify-content-center"
                        :disabled="page >= totalPages"
                        @click="page++"
                    >
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TrainingDetailMaterials',
    props: {
        materials: { type: Array, default: () => [] },
        locked: { type: Boolean, default: false },
    },
    emits: ['view-material', 'complete-video', 'complete-quiz'],
    data() {
        return { page: 1, perPage: 10 };
    },
    computed: {
        totalPages() {
            return Math.max(1, Math.ceil(this.materials.length / this.perPage));
        },
        pagedMaterials() {
            const start = (this.page - 1) * this.perPage;
            return this.materials.slice(start, start + this.perPage);
        },
    },
    watch: {
        perPage() {
            this.page = 1;
            this.syncPageWithScrollTarget();
        },
        materials() {
            if (this.page > this.totalPages) {
                this.page = this.totalPages;
            }
            this.syncPageWithScrollTarget();
        },
    },
    mounted() {
        this.syncPageWithScrollTarget();
    },
    methods: {
        syncPageWithScrollTarget() {
            const urlParams = new URLSearchParams(window.location.search);
            const scrollToId = urlParams.get('scrollTo');
            if (!scrollToId || !this.materials?.length) {
                return;
            }

            const targetIndex = this.materials.findIndex((entry) => String(entry.id) === String(scrollToId));
            if (targetIndex < 0) {
                return;
            }

            const targetPage = Math.floor(targetIndex / this.perPage) + 1;
            if (targetPage !== this.page) {
                this.page = targetPage;
            }
        },
        getTypeLabel(mat) {
            if (mat.activityType === 'video') return 'Video';
            if (mat.activityType === 'quiz') return 'Quiz';
            return 'Modul';
        },
        getTypeIcon(mat) {
            if (mat.activityType === 'video') return 'bi bi-camera-video-fill';
            if (mat.activityType === 'quiz') return 'bi bi-patch-question-fill';
            if (mat.fileType === 'xlsx') return 'bi bi-file-earmark-spreadsheet-fill';
            return 'bi bi-file-earmark-pdf-fill';
        },
        getIconClass(mat) {
            if (mat.activityType === 'video') return 'bg-info-subtle text-info';
            if (mat.activityType === 'quiz') return 'bg-warning-subtle text-warning';
            if (mat.fileType === 'xlsx') return 'bg-success-subtle text-success';
            return 'bg-danger-subtle text-danger';
        },
        getActionLabel(mat) {
            if (mat.activityType === 'video') {
                return mat.completed ? 'Tonton Ulang' : 'Tonton';
            }
            if (mat.activityType === 'quiz') {
                return mat.status === 'in-progress' ? 'Lanjut Quiz' : 'Mulai Quiz';
            }
            return mat.completed ? 'Lihat Ulang' : 'Lihat';
        },
        getActionIcon(mat) {
            if (mat.activityType === 'quiz') return 'bi-clipboard-check';
            if (mat.activityType === 'video') return 'bi-play-fill';
            return 'bi-eye';
        },
        getActionTitle(mat) {
            if (mat.activityType === 'quiz') return 'Mulai atau lanjutkan quiz';
            if (mat.activityType === 'video') return mat.completed ? 'Putar ulang video' : 'Putar video';
            return mat.completed ? 'Lihat materi kembali' : 'Lihat materi';
        },
        getActionButtonClass(mat) {
            if (mat.activityType === 'quiz') {
                return mat.status === 'in-progress' ? 'btn-warning text-white' : 'btn-success text-white';
            }
            if (mat.activityType === 'video') {
                return mat.completed ? 'btn-success text-white' : 'btn-success text-white';
            }
            return 'btn-success text-white';
        },
        handlePrimaryAction(mat) {
            if (this.locked) return;

            const payload = {
                id: mat.id,
                item: mat.raw,
                activityType: mat.activityType,
            };

            if (mat.activityType === 'video') {
                this.$emit('complete-video', payload);
                return;
            }

            if (mat.activityType === 'quiz') {
                this.$emit('complete-quiz', payload);
                return;
            }

            this.$emit('view-material', payload);
        },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}

.bg-light {
    background-color: #f8f9fa !important;
}

.stitch-card {
    background: var(--surface, var(--hcis-white));
    border: 1px solid var(--border, var(--hcis-slate-200));
    border-radius: 12px;
    box-shadow: 0 2px 4px -1px rgba(0, 0, 0, 0.05);
}

.custom-table th {
    background-color: var(--surface-soft, var(--hcis-slate-50)) !important;
    color: var(--secondary, var(--hcis-slate-500)) !important;
}

.item-name {
    font-size: 0.9rem;
    line-height: 1.35;
}

.item-subtitle {
    font-size: 0.76rem;
    line-height: 1.4;
    margin-top: 0.2rem;
}

.sort-icon {
    font-size: 0.6rem;
    color: var(--hcis-slate-300);
}

.file-icon {
    width: 40px;
    height: 40px;
}

.type-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 68px;
    border-radius: 999px;
    padding: 0.25rem 0.7rem;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.type-material {
    background: var(--hcis-red-50);
    color: var(--hcis-red-700);
    border-color: var(--hcis-red-200);
}

.type-video {
    background: var(--hcis-sky-100);
    color: #075985;
    border-color: var(--hcis-sky-200);
}

.type-quiz {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-800);
    border-color: var(--hcis-amber-200);
}

.status-pill {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.23rem 0.6rem;
    font-size: 0.72rem;
    font-weight: 700;
    border: 1px solid transparent;
}

.status-pill.is-done {
    background: var(--hcis-green-100);
    color: #166534;
    border-color: var(--hcis-green-200);
}

.status-pill.is-progress {
    background: #dbeafe;
    color: #1d4ed8;
    border-color: #bfdbfe;
}

.status-pill.is-idle {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-500);
    border-color: var(--hcis-slate-200);
}

.cursor-pointer {
    cursor: pointer;
}

.items-per-page-select {
    width: 74px;
    min-width: 74px;
    font-weight: 600;
    padding-left: 0.9rem;
    padding-right: 1.8rem;
    background-position: right 0.6rem center;
    background-size: 14px 10px;
}

.pagination-btn {
    width: 32px;
    height: 32px;
    border: none;
    transition: background-color 0.2s;
}

.pagination-btn:hover:not(:disabled) {
    background-color: var(--hcis-slate-200);
}

.pagination-num {
    width: 32px;
    height: 32px;
    border: none;
    transition: all 0.2s;
}

.pagination-num:hover:not(.btn-primary) {
    background-color: var(--hcis-slate-200);
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3) !important;
}

.action-btn {
    min-width: 118px;
}

.min-w-0 {
    min-width: 0;
}

@media (max-width: 767px) {
    .table-responsive {
        overflow: visible;
    }

    .custom-table thead {
        display: none;
    }

    .custom-table tbody tr {
        display: block;
        margin: 0.8rem;
        border: 1px solid var(--hcis-slate-200);
        border-radius: 12px;
        background: var(--hcis-white);
    }

    .custom-table tbody td {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 0.85rem;
        text-align: right !important;
        padding: 0.75rem 0.9rem !important;
        border: 0 !important;
    }

    .custom-table tbody td::before {
        content: attr(data-label);
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--hcis-slate-500);
        text-transform: uppercase;
        letter-spacing: 0.03em;
        text-align: left;
        flex-shrink: 0;
        margin-top: 0.1rem;
    }

    .custom-table tbody td[data-label='Name'] {
        text-align: left !important;
        flex-direction: column;
        align-items: stretch;
    }

    .custom-table tbody td[data-label='Name']::before {
        margin-bottom: 0.35rem;
    }

    .custom-table tbody td[data-label='Action'] .d-inline-flex {
        width: 100%;
        justify-content: flex-end;
    }

    .action-btn {
        min-width: 128px;
    }
}
</style>
