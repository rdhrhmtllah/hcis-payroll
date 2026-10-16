<template>
    <div class="stitch-card p-4 border-0 shadow-sm rounded-4 bg-white h-100">
        <!-- Header skeleton -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center gap-2">
                <div class="skeleton-box rounded-3" style="width:40px;height:40px;"></div>
                <div class="skeleton-box rounded-3" style="width:160px;height:20px;"></div>
            </div>
            <div class="skeleton-box rounded-pill" style="width:80px;height:28px;"></div>
        </div>

        <!-- Stat boxes skeleton -->
        <div v-if="variant === 'absensi' || variant === 'default'" class="row g-3 mb-4">
            <div class="col-4" v-for="i in 3" :key="'s'+i">
                <div class="skeleton-box rounded-4" style="height:70px;"></div>
            </div>
        </div>

        <!-- Chart skeleton -->
        <div class="row mb-4" v-if="variant !== 'timeline'">
            <div :class="variant === 'absensi' ? 'col-md-7' : 'col-12'">
                <div class="skeleton-box rounded-3 mb-2" style="width:140px;height:14px;"></div>
                <div class="skeleton-box rounded-3" style="height:140px;"></div>
            </div>
            <div v-if="variant === 'absensi'" class="col-md-5">
                <div class="skeleton-box rounded-3 mb-2" style="width:100px;height:14px;"></div>
                <div v-for="i in 3" :key="'l'+i" class="d-flex gap-3 mb-3">
                    <div class="skeleton-box rounded-3" style="width:50px;height:14px;"></div>
                    <div class="flex-grow-1">
                        <div class="skeleton-box rounded-3 mb-1" style="height:14px;width:80%;"></div>
                        <div class="skeleton-box rounded-3" style="height:10px;width:50%;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI radial skeleton -->
        <div v-if="variant === 'kpi'" class="row align-items-center mb-4">
            <div class="col-md-4 text-center">
                <div class="skeleton-box rounded-circle mx-auto" style="width:130px;height:130px;"></div>
            </div>
            <div class="col-md-8">
                <div v-for="i in 3" :key="'k'+i" class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <div class="skeleton-box rounded-3" style="width:100px;height:12px;"></div>
                        <div class="skeleton-box rounded-3" style="width:40px;height:12px;"></div>
                    </div>
                    <div class="skeleton-box rounded-3" style="height:6px;"></div>
                </div>
            </div>
        </div>

        <!-- Footer skeleton -->
        <div class="mt-auto">
            <div class="skeleton-box rounded-pill" style="height:36px;"></div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'CardSkeleton',
    props: {
        variant: { type: String, default: 'default' }, // 'absensi', 'kpi', 'lms', 'visitor', 'timeline', 'default'
    },
};
</script>

<style scoped>
.skeleton-box {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s ease-in-out infinite;
    width: 100%;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>
