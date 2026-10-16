<template>
    <article
        class="stitch-card h-100 overflow-hidden cursor-pointer d-flex flex-column p-0"
        :class="{ 'is-loading': loading }"
        style="border-radius: 16px"
        @click="$emit('select', course)"
    >
        <div v-if="loading" class="card-loading-overlay">
            <div class="card-loading-badge">
                <i class="bi bi-arrow-repeat"></i>
                <span>Membuka training...</span>
            </div>
        </div>

        <div class="training-cover" :class="{ placeholder: !hasValidImage }">
            <img
                v-if="hasValidImage"
                :src="course.image"
                :alt="course.title"
                loading="lazy"
                @error="handleImageError"
            />
            <div v-else class="cover-placeholder">
                <i class="bi bi-mortarboard"></i>
                <span>{{ course.category }}</span>
            </div>
        </div>

        <div class="training-body">
            <h3 class="training-title" :title="course.title">
                {{ course.title }}
            </h3>

            <p class="training-meta">{{ course.provider }} · {{ course.dateLabel }}</p>

            <div class="status-row">
                <span class="status-badge" :class="course.statusClass">
                    {{ course.statusLabel }}
                </span>
                <span class="status-progress">({{ course.progress }}%)</span>
            </div>

            <div class="progress-track" :aria-label="`Progress ${course.progress}%`">
                <div class="progress-fill" :class="course.statusClass" :style="{ width: `${course.progress}%` }"></div>
            </div>

            <div class="training-footer">
                <span class="footer-item">
                    <i class="bi bi-clock-history"></i>
                    {{ course.scheduleTimeLabel || course.duration }}
                </span>
                <span class="footer-item">
                    <i class="bi bi-grid"></i>
                    {{ course.category }}
                </span>
            </div>
        </div>
    </article>
</template>

<script>
export default {
    name: 'TrainingCourseCard',
    props: {
        course: {
            type: Object,
            required: true,
        },
        loading: {
            type: Boolean,
            default: false,
        },
    },
    emits: ['select'],
    data() {
        return {
            imageLoadFailed: false,
        };
    },
    computed: {
        hasValidImage() {
            return Boolean(this.course?.image) && !this.imageLoadFailed;
        },
    },
    watch: {
        'course.image'() {
            this.imageLoadFailed = false;
        },
    },
    methods: {
        handleImageError() {
            this.imageLoadFailed = true;
        },
    },
};
</script>

<style scoped>
.stitch-card {
    background-color: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}

.stitch-card:hover {
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
    transform: translateY(-4px);
    border-color: var(--hcis-slate-300);
}

.stitch-card.is-loading {
    pointer-events: none;
    transform: none;
    box-shadow: 0 18px 40px -18px rgba(59, 130, 246, 0.45);
    border-color: #93c5fd;
}

.stitch-card.is-loading .training-cover,
.stitch-card.is-loading .training-body {
    filter: blur(0.4px);
}

.card-loading-overlay {
    position: absolute;
    inset: 0;
    background: rgba(255, 255, 255, 0.72);
    backdrop-filter: blur(3px);
    z-index: 3;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.card-loading-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    padding: 0.8rem 1rem;
    border-radius: 999px;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    box-shadow: 0 10px 30px -18px rgba(37, 99, 235, 0.7);
    font-size: 0.85rem;
    font-weight: 700;
}

.card-loading-badge i {
    animation: card-spin 0.9s linear infinite;
}

@keyframes card-spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.training-cover {
    position: relative;
    aspect-ratio: 1.85 / 1;
    overflow: hidden;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}

.training-cover.placeholder {
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
}

.training-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.cover-placeholder {
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    color: var(--hcis-slate-500);
    font-weight: 700;
}

.cover-placeholder i {
    font-size: 2rem;
    color: var(--hcis-slate-400);
}

.training-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.training-title {
    font-size: 1rem;
    font-weight: 700;
    color: var(--hcis-slate-800);
    line-height: 1.35;
    margin: 0 0 0.15rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.7rem;
}

.training-meta {
    font-size: 0.8rem;
    color: var(--hcis-slate-400);
    margin: 0 0 0.75rem;
}

.status-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.45rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    min-height: 1.65rem;
    padding: 0.2rem 0.55rem;
    border-radius: 8px;
    font-size: 0.72rem;
    font-weight: 700;
}

.status-progress {
    font-size: 0.75rem;
    color: var(--hcis-slate-900);
    font-weight: 700;
}

.progress-track {
    width: 100%;
    height: 6px;
    background: #e5e7eb;
    border-radius: 999px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: inherit;
    transition: width 0.25s ease;
}

.status-not-started {
    background: var(--bs-danger-bg-subtle, var(--hcis-legacy-bootstrap-danger));
    color: var(--bs-danger, #dc3545);
    border: 1px solid var(--bs-danger-border-subtle, #f1aeb5);
}

.progress-fill.status-not-started {
    background: var(--bs-danger, #dc3545);
}

.status-in-progress {
    background: var(--bs-warning-bg-subtle, #fff3cd);
    color: var(--bs-warning-text-emphasis, #997404);
    border: 1px solid var(--bs-warning-border-subtle, #ffe69c);
}

.progress-fill.status-in-progress {
    background: var(--bs-warning, #ffc107);
}

.status-completed {
    background: var(--bs-success-bg-subtle, #d1e7dd);
    color: var(--bs-success, #198754);
    border: 1px solid var(--bs-success-border-subtle, #a3cfbb);
}

.progress-fill.status-completed {
    background: var(--bs-info, #0dcaf0);
}

.training-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-top: auto;
    padding-top: 1rem;
    color: var(--hcis-slate-500);
    font-size: 0.78rem;
}

.footer-item {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
</style>
