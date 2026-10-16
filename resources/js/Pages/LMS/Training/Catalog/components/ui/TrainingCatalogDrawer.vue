<template>
    <div>
        <transition name="fade">
            <div v-if="show && course" class="drawer-overlay" @click="$emit('close')"></div>
        </transition>

        <transition name="slide-panel">
            <aside v-if="show && course" class="drawer-panel">
                <div class="drawer-header">
                    <div>
                        <p class="drawer-eyebrow">Detail Training</p>
                        <h3 class="drawer-title">{{ course.title }}</h3>
                    </div>
                    <button type="button" class="close-btn" @click="$emit('close')">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>

                <div class="drawer-body">
                    <div class="drawer-cover" :class="{ placeholder: !hasValidImage }">
                        <img
                            v-if="hasValidImage"
                            :src="course.image"
                            :alt="course.title"
                            loading="lazy"
                            @error="handleImageError"
                        />
                        <div v-else class="drawer-placeholder">
                            <i class="bi bi-play-circle"></i>
                        </div>
                    </div>

                    <div class="drawer-chip-row">
                        <span class="chip" :class="course.statusClass">{{ course.statusLabel }}</span>
                        <span class="chip neutral">{{ course.category }}</span>
                        <span class="chip neutral">{{ course.level }}</span>
                    </div>

                    <div class="drawer-progress-card">
                        <div class="progress-head">
                            <span>Progress belajar</span>
                            <strong>{{ course.progress }}%</strong>
                        </div>
                        <div class="progress-track">
                            <div
                                class="progress-fill"
                                :class="course.statusClass"
                                :style="{ width: `${course.progress}%` }"
                            ></div>
                        </div>
                    </div>

                    <p class="drawer-description">{{ course.description }}</p>

                    <div class="meta-grid">
                        <div class="meta-card">
                            <span class="meta-label">Provider</span>
                            <strong>{{ course.provider }}</strong>
                        </div>
                        <div class="meta-card">
                            <span class="meta-label">Trainer</span>
                            <strong>{{ course.trainer }}</strong>
                        </div>
                        <div class="meta-card">
                            <span class="meta-label">Durasi</span>
                            <strong>{{ course.duration }}</strong>
                        </div>
                        <div class="meta-card">
                            <span class="meta-label">Jadwal Mulai</span>
                            <strong>{{ course.dateLabel }}</strong>
                        </div>
                        <div class="meta-card" v-if="course.endrollLabel && course.endrollLabel !== '-'">
                            <span class="meta-label">Jadwal Selesai</span>
                            <strong>{{ course.endrollLabel }}</strong>
                        </div>
                    </div>

                    <div class="tag-row" v-if="course.tags && course.tags.length">
                        <span v-for="tag in course.tags" :key="tag" class="tag-item"> #{{ tag }} </span>
                    </div>

                    <div class="cta-row">
                        <button type="button" class="btn-secondary" @click="$emit('close')">Tutup</button>
                        <Link
                            :href="`/lms/training/${course.id}`"
                            class="btn-primary d-flex align-items-center justify-content-center text-decoration-none"
                            style="cursor: pointer"
                        >
                            {{ course.progress > 0 && course.progress < 100 ? 'Lanjutkan' : 'Lihat Detail' }}
                        </Link>
                    </div>
                </div>
            </aside>
        </transition>
    </div>
</template>

<script>
import { Link } from '@inertiajs/vue3';

export default {
    name: 'TrainingCatalogDrawer',
    components: {
        Link,
    },
    props: {
        show: {
            type: Boolean,
            default: false,
        },
        course: {
            type: Object,
            default: null,
        },
    },
    emits: ['close'],
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
.drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.32);
    backdrop-filter: blur(2px);
    z-index: 1040;
}

.drawer-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: min(460px, 100vw);
    height: 100vh;
    background: var(--hcis-white);
    box-shadow: -12px 0 30px rgba(15, 23, 42, 0.14);
    z-index: 1050;
    display: flex;
    flex-direction: column;
}

.drawer-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.35rem 1.35rem 1rem;
    border-bottom: 1px solid var(--hcis-slate-200);
}

.drawer-eyebrow {
    margin: 0 0 0.35rem;
    color: var(--hcis-slate-400);
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}

.drawer-title {
    margin: 0;
    color: var(--hcis-slate-900);
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.3;
}

.close-btn {
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 14px;
    background: var(--hcis-slate-50);
    color: var(--hcis-slate-500);
}

.drawer-body {
    padding: 1.35rem;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.drawer-cover {
    overflow: hidden;
    border-radius: 18px;
    aspect-ratio: 1.75 / 1;
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
}

.drawer-cover.placeholder {
    background: linear-gradient(135deg, var(--hcis-slate-50) 0%, var(--hcis-slate-200) 100%);
}

.drawer-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.drawer-placeholder {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hcis-slate-400);
    font-size: 3rem;
}

.drawer-chip-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.chip {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.4rem 0.8rem;
    font-size: 0.74rem;
    font-weight: 700;
}

.chip.neutral {
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
}

.status-not-started {
    background: var(--hcis-red-100);
    color: var(--hcis-red-500);
}

.status-in-progress {
    background: var(--hcis-amber-100);
    color: var(--hcis-amber-600);
}

.status-completed {
    background: var(--hcis-green-100);
    color: var(--hcis-green-600);
}

.drawer-progress-card {
    border-radius: 16px;
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-slate-50);
    padding: 1rem;
}

.progress-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
    color: var(--hcis-slate-600);
    font-size: 0.92rem;
}

.progress-track {
    width: 100%;
    height: 8px;
    border-radius: 999px;
    background: var(--hcis-slate-200);
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: inherit;
}

.progress-fill.status-not-started {
    background: var(--hcis-red-400);
}

.progress-fill.status-in-progress {
    background: var(--hcis-amber-500);
}

.progress-fill.status-completed {
    background: var(--hcis-cyan-500);
}

.drawer-description {
    margin: 0;
    color: var(--hcis-slate-600);
    font-size: 0.95rem;
    line-height: 1.7;
}

.meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.meta-card {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.9rem 1rem;
    border-radius: 14px;
    background: var(--hcis-white);
    border: 1px solid var(--hcis-slate-200);
}

.meta-label {
    font-size: 0.72rem;
    color: var(--hcis-slate-400);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.tag-row {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.tag-item {
    display: inline-flex;
    align-items: center;
    border-radius: 999px;
    padding: 0.4rem 0.75rem;
    background: var(--hcis-slate-100);
    color: var(--hcis-slate-600);
    font-size: 0.76rem;
    font-weight: 600;
}

.cta-row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
    margin-top: 0.25rem;
}

.btn-primary,
.btn-secondary {
    min-height: 46px;
    border-radius: 14px;
    font-size: 0.92rem;
    font-weight: 700;
}

.btn-primary {
    border: none;
    color: var(--hcis-white);
    background: linear-gradient(135deg, var(--hcis-indigo-500) 0%, var(--hcis-indigo-600) 100%);
}

.btn-secondary {
    border: 1px solid var(--hcis-slate-200);
    background: var(--hcis-white);
    color: var(--hcis-slate-600);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-panel-enter-active,
.slide-panel-leave-active {
    transition: transform 0.28s ease;
}

.slide-panel-enter-from,
.slide-panel-leave-to {
    transform: translateX(100%);
}

@media (max-width: 576px) {
    .meta-grid,
    .cta-row {
        grid-template-columns: 1fr;
    }
}
</style>
