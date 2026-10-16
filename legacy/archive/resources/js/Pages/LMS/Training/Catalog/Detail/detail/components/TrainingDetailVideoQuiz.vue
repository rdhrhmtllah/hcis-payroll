<template>
    <div class="detail-section mb-4">
        <div
            class="vq-header"
            :class="{ 'vq-header--open': expanded }"
            @click="expanded = !expanded"
        >
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <h5 class="section-heading mb-0">
                    Video &amp; Quiz ({{ completedCount }}/{{ items.length }})
                </h5>
                <span v-if="locked" class="locked-badge">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    Must complete all materials to be able to watch Video &amp;
                    Quiz
                </span>
            </div>
            <i
                class="vq-chevron"
                :class="expanded ? 'bi bi-chevron-up' : 'bi bi-chevron-down'"
            ></i>
        </div>

        <div v-if="expanded" class="vq-list">
            <div
                v-for="item in items"
                :key="item.id"
                class="vq-item"
                :class="
                    item.itemType === 'quiz'
                        ? 'vq-item--quiz'
                        : 'vq-item--video'
                "
            >
                <template v-if="item.itemType === 'video'">
                    <div
                        class="vq-thumb"
                        :class="{ 'opacity-50': item.completed }"
                    >
                        <img
                            v-if="item.thumbnail"
                            :src="item.thumbnail"
                            :alt="item.title"
                            loading="lazy"
                        />
                        <i v-else class="bi bi-play-circle-fill"></i>
                        <span
                            v-if="item.duration && !item.completed"
                            class="vq-duration"
                            >{{ item.duration }}</span
                        >
                    </div>
                    <div class="vq-info">
                        <div
                            class="vq-title"
                            :class="{
                                'text-muted text-decoration-line-through':
                                    item.completed,
                            }"
                        >
                            {{ item.title }}
                        </div>
                        <div class="vq-subtitle">{{ item.subtitle }}</div>
                    </div>
                    <div class="ms-auto ps-3 border-start">
                        <button
                            v-if="!item.completed"
                            class="btn btn-sm btn-outline-primary py-1 px-3 rounded-pill"
                            style="font-size: 0.75rem"
                            :disabled="locked"
                            @click="!locked && $emit('complete-item', item.id)"
                        >
                            <i class="bi bi-play-fill me-1"></i> Watch
                        </button>
                        <i
                            v-else
                            class="bi bi-check-circle-fill text-success fs-5"
                        ></i>
                    </div>
                </template>

                <template v-else>
                    <div
                        class="vq-quiz-icon"
                        :class="{
                            'bg-success-subtle text-success': item.completed,
                        }"
                    >
                        <i class="bi bi-chat-left-text-fill"></i>
                    </div>
                    <div class="vq-info">
                        <div
                            class="vq-title"
                            :class="{
                                'text-muted text-decoration-line-through':
                                    item.completed,
                            }"
                        >
                            {{ item.title }}
                        </div>
                    </div>
                    <div class="ms-auto ps-3 border-start">
                        <button
                            v-if="!item.completed"
                            class="btn btn-sm btn-outline-warning py-1 px-3 rounded-pill"
                            style="font-size: 0.75rem"
                            :disabled="locked"
                            @click="!locked && $emit('complete-item', item.id)"
                        >
                            <i class="bi bi-pencil-square me-1"></i> Take Quiz
                        </button>
                        <i
                            v-else
                            class="bi bi-check-circle-fill text-success fs-5"
                        ></i>
                    </div>
                </template>
            </div>

            <div
                v-if="items.length === 0"
                class="py-3 text-center text-muted"
                style="font-size: 0.84rem"
            >
                Belum ada konten tersedia.
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "TrainingDetailVideoQuiz",
    props: {
        items: { type: Array, default: () => [] },
        locked: { type: Boolean, default: false },
    },
    data() {
        return { expanded: true };
    },
    computed: {
        completedCount() {
            return this.items.filter((i) => i.completed).length;
        },
    },
};
</script>

<style scoped>
.section-heading {
    font-size: 1rem;
    font-weight: 700;
    color: #1a1a2e;
}

.bi {
    display: inline-table !important;
}

.vq-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 0.8rem 1.25rem;
    cursor: pointer;
    user-select: none;
    transition: background 0.12s;
}

.vq-header--open {
    border-radius: 10px 10px 0 0;
}

.vq-header:hover {
    background: #f8f9fa;
}

.locked-badge {
    font-size: 0.75rem;
    color: #e53e3e;
    font-weight: 500;
}

.vq-chevron {
    font-size: 0.88rem;
    color: #6366f1;
    flex-shrink: 0;
}

.vq-list {
    border: 1px solid #e9ecef;
    border-top: none;
    border-radius: 0 0 10px 10px;
    overflow: hidden;
    background: #fff;
}

.vq-item {
    display: flex;
    align-items: center;
    padding: 0.65rem 1.25rem;
    border-bottom: 1px solid #f1f3f5;
    gap: 0.85rem;
}

.vq-item:last-child {
    border-bottom: none;
}

.vq-thumb {
    position: relative;
    width: 68px;
    height: 46px;
    border-radius: 6px;
    background: #e8eaf6;
    color: #6366f1;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    overflow: hidden;
}

.vq-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.vq-duration {
    position: absolute;
    bottom: 2px;
    right: 4px;
    font-size: 0.58rem;
    font-weight: 700;
    background: rgba(0, 0, 0, 0.65);
    color: #fff;
    padding: 1px 4px;
    border-radius: 4px;
}

.vq-quiz-icon {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #e8eaf6;
    color: #6366f1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    flex-shrink: 0;
}

.vq-info {
    flex: 1;
}

.vq-title {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333;
}

.vq-subtitle {
    font-size: 0.75rem;
    color: #888;
    margin-top: 2px;
}
</style>
