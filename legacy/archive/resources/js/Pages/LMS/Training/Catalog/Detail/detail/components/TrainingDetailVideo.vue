<template>
    <div class="video-item border-bottom pb-3 mb-3" :class="{ 'opacity-75': completed }">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3 flex-grow-1">
                <div
                    class="video-thumb position-relative rounded-3 overflow-hidden shadow-sm flex-shrink-0"
                    style="width: 120px; height: 75px"
                >
                    <img
                        v-if="thumbnail"
                        :src="thumbnail"
                        :alt="title"
                        class="w-100 h-100 object-fit-cover"
                        loading="lazy"
                    />
                    <div
                        v-else
                        class="w-100 h-100 d-flex align-items-center justify-content-center text-secondary"
                        style="background-color: #e2e8f0"
                    >
                        <i class="bi bi-play-circle-fill fs-3"></i>
                    </div>

                    <span
                        v-if="formattedDuration && !completed"
                        class="position-absolute bottom-0 end-0 m-1 bg-dark text-white px-1 rounded"
                        style="font-size: 0.65rem; font-weight: 600; opacity: 0.85"
                    >
                        {{ formattedDuration }}
                    </span>
                    <div
                        v-if="completed"
                        class="position-absolute top-0 start-0 w-100 h-100 bg-success bg-opacity-25 d-flex align-items-center justify-content-center"
                    >
                        <span class="video-complete-badge">
                            <i class="bi bi-check-circle-fill text-success fs-3"></i>
                        </span>
                    </div>
                </div>

                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-bold text-dark" style="font-size: 0.95rem">
                        {{ title }}
                    </h6>
                    <p v-if="subtitle" class="mb-0 text-muted" style="font-size: 0.8rem">
                        {{ subtitle }}
                    </p>
                </div>
            </div>

            <div class="text-end">
                <div class="d-inline-flex align-items-center gap-2 flex-wrap justify-content-end">
                    <button
                        class="btn btn-sm rounded-pill px-3 fw-medium transition-all"
                        :class="completed ? 'btn-light text-success fw-bold' : 'btn-primary shadow-sm'"
                        :disabled="locked"
                        @click="!locked && $emit('complete')"
                        :title="completed ? 'Watch Again' : 'Watch'"
                    >
                        <i v-if="completed" class="bi bi-play-fill me-1"></i>
                        <i v-else class="bi bi-play-fill me-1"></i>
                        {{ completed ? 'Tonton Ulang' : 'Tonton' }}
                    </button>

                    <a
                        v-if="showDownload && downloadable"
                        class="btn btn-sm btn-light border rounded px-3 text-secondary transition-all"
                        :class="{ disabled: locked }"
                        :href="locked ? undefined : downloadUrl"
                        :aria-disabled="locked ? 'true' : 'false'"
                        :tabindex="locked ? -1 : 0"
                        target="_blank"
                        rel="noopener noreferrer"
                        @click="locked && $event.preventDefault()"
                    >
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'TrainingDetailVideo',
    props: {
        title: { type: String, default: 'Video' },
        subtitle: { type: String, default: '' },
        thumbnail: { type: String, default: null },
        durasi: { type: Number, default: 0 },
        showDownload: { type: Boolean, default: false },
        downloadable: { type: Boolean, default: false },
        downloadUrl: { type: String, default: null },
        completed: { type: Boolean, default: false },
        locked: { type: Boolean, default: false },
    },
    emits: ['complete'],
    computed: {
        formattedDuration() {
            if (!this.durasi) return '';
            const mins = Math.floor(this.durasi / 60);
            const secs = this.durasi % 60;
            if (mins > 0) {
                return secs > 0 ? `${mins}:${String(secs).padStart(2, '0')}` : `${mins}:00`;
            }
            return `0:${String(secs).padStart(2, '0')}`;
        },
    },
};
</script>

<style scoped>
.bi {
    display: inline-table !important;
}

.video-item:last-child {
    border-bottom: none !important;
    margin-bottom: 0 !important;
    padding-bottom: 0 !important;
}

.transition-all {
    transition: all 0.2s ease;
}
.transition-all:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3) !important;
}

.video-complete-badge {
    width: 46px;
    height: 46px;
    border-radius: 999px;
    background: #ffffff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.16);
}
</style>
