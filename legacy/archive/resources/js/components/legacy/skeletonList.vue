<template>
    <div
        v-for="n in 5"
        :key="n"
        class="skeleton-card mb-3 p-3 rounded shadow-sm"
    >
        <div class="d-flex align-items-center mb-2">
            <div class="skeleton-icon me-3"></div>
            <div class="w-100">
                <div class="skeleton-line w-50 mb-2"></div>
                <div class="skeleton-line w-75"></div>
            </div>
        </div>
        <div class="skeleton-line w-100 mb-2"></div>
        <div class="skeleton-line w-100 mb-2"></div>
        <div class="skeleton-line w-50"></div>
    </div>
</template>

<style>
.skeleton-card {
    background: #fff;
}

.skeleton-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: linear-gradient(90deg, #e4e6eb 25%, #f0f1f2 50%, #e4e6eb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

.skeleton-line {
    height: 14px;
    border-radius: 4px;
    background: linear-gradient(90deg, #e4e6eb 25%, #f0f1f2 50%, #e4e6eb 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
    0% {
        background-position: -200% 0;
    }
    100% {
        background-position: 200% 0;
    }
}
</style>
