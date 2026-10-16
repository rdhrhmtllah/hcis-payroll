<template>
    <div class="detail-section mb-4">
        <h5 class="section-heading">Class</h5>

        <div v-for="cls in classes" :key="cls.id" class="class-card">
            <div class="row align-items-center g-0">
                <div class="col-auto">
                    <div class="class-thumb">
                        <img
                            v-if="cls.thumbnail"
                            :src="cls.thumbnail"
                            :alt="cls.name"
                            loading="lazy"
                        />
                        <i v-else class="bi bi-people-fill"></i>
                    </div>
                </div>

                <div class="col ps-3">
                    <div class="class-name">{{ cls.name }}</div>
                    <div class="class-participants mt-1">
                        <div
                            v-for="(p, i) in cls.participants.slice(0, 3)"
                            :key="p.id"
                            class="p-avatar"
                            :style="{
                                zIndex: 10 - i,
                                marginLeft: i === 0 ? '0' : '-6px',
                            }"
                            :title="p.name"
                        >
                            {{ p.initials }}
                        </div>
                        <span
                            v-if="cls.participants.length > 3"
                            class="p-extra ms-1"
                            >+{{ cls.participants.length - 3 }}</span
                        >
                    </div>
                </div>

                <div class="col-auto px-3 text-center">
                    <div class="cls-meta-label">Mulai</div>
                    <div class="cls-meta-value">{{ cls.startDate }}</div>
                </div>

                <div class="col-auto px-3 text-center">
                    <div class="cls-meta-label">Selesai</div>
                    <div class="cls-meta-value">{{ cls.endDate }}</div>
                </div>

                <div class="col-auto px-3 text-center">
                    <div class="cls-meta-label">Type</div>
                    <div class="cls-meta-value">{{ cls.classType }}</div>
                </div>
            </div>
        </div>

        <p
            v-if="classes.length === 0"
            class="text-muted"
            style="font-size: 0.85rem"
        >
            Belum ada kelas tersedia.
        </p>
    </div>
</template>

<script>
export default {
    name: "TrainingDetailClass",
    props: {
        classes: { type: Array, default: () => [] },
    },
};
</script>

<style scoped>
.section-heading {
    font-size: 1rem;
    font-weight: 700;
    color: #1a1a2e;
    margin-bottom: 0.75rem;
}

.bi {
    display: inline-table !important;
}

.class-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 10px;
    padding: 0.9rem 1.25rem;
    margin-bottom: 0.5rem;
}

.class-thumb {
    width: 56px;
    height: 56px;
    border-radius: 8px;
    background: #e8eaf6;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6366f1;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.class-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.class-name {
    font-size: 0.92rem;
    font-weight: 600;
    color: #1a1a2e;
}

.class-participants {
    display: flex;
    align-items: center;
}

.p-avatar {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: #6366f1;
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    position: relative;
    flex-shrink: 0;
}

.p-extra {
    font-size: 0.72rem;
    color: #888;
}

.cls-meta-label {
    font-size: 0.7rem;
    color: #888;
    margin-bottom: 2px;
}

.cls-meta-value {
    font-size: 0.82rem;
    font-weight: 600;
    color: #333;
    white-space: nowrap;
}
</style>
