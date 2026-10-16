<template>
    <div class="bordered-skeleton-container">
        <table class="skeleton-table">
            <thead>
                <tr>
                    <th><div class="skeleton skeleton-text header"></div></th>
                    <th><div class="skeleton skeleton-text header"></div></th>
                    <th><div class="skeleton skeleton-text header"></div></th>
                    <th><div class="skeleton skeleton-text header"></div></th>
                    <th><div class="skeleton skeleton-text header"></div></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="i in 5" :key="i">
                    <td>
                        <div class="skeleton skeleton-text"></div>
                    </td>
                    <td>
                        <div class="skeleton skeleton-text"></div>
                    </td>
                    <td>
                        <div class="skeleton skeleton-text"></div>
                    </td>
                    <td>
                        <div class="skeleton skeleton-text"></div>
                    </td>
                    <td>
                        <div class="skeleton skeleton-text"></div>
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="pagination-skeleton">
            <div class="skeleton skeleton-text" style="width: 180px"></div>
            <div class="pagination-controls">
                <div class="skeleton skeleton-button-pagination"></div>
                <div class="skeleton skeleton-button-pagination active"></div>
                <div class="skeleton skeleton-button-pagination"></div>
                <div class="skeleton skeleton-button-pagination"></div>
            </div>
        </div>
    </div>
</template>

<script setup>
// Komponen ini murni visual, tidak memerlukan logic.
</script>

<style scoped>
/* Animasi shimmer yang halus */
@keyframes shimmer {
    100% {
        transform: translateX(100%);
    }
}

/* Base class untuk semua elemen skeleton */
.skeleton {
    position: relative;
    overflow: hidden;
    background-color: #eef0f2;
    border-radius: 6px;
}

.skeleton::after {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    transform: translateX(-100%);
    background-image: linear-gradient(
        90deg,
        rgba(255, 255, 255, 0) 0,
        rgba(255, 255, 255, 0.4) 50%,
        rgba(255, 255, 255, 0) 100%
    );
    animation: shimmer 2s infinite;
}

/* [DIUBAH] Styling untuk tabel dengan border penuh */
.skeleton-table {
    width: 100%;
    border-collapse: collapse; /* Penting untuk border penuh */
    border: 1px solid #eef0f2; /* Border luar untuk tabel */
}

.skeleton-table th,
.skeleton-table td {
    padding: 12px 16px;
    vertical-align: middle;
    border: 1px solid #eef0f2; /* Border untuk setiap sel */
    text-align: left;
}

/* [BARU] Styling untuk skeleton di dalam header */
.skeleton-text.header {
    width: 70%;
    height: 20px;
}

/* Placeholder untuk berbagai tipe konten */
.skeleton-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin: auto; /* Agar avatar di tengah sel */
}

.skeleton-text {
    width: 100%;
    height: 16px;
}

.skeleton-text.subtext {
    width: 70%;
    height: 12px;
    margin-top: 8px;
}

.skeleton-badge {
    width: 90px;
    height: 24px;
    border-radius: 12px;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.skeleton-button {
    width: 32px;
    height: 32px;
    border-radius: 4px;
}

/* Styling untuk Skeleton Pagination */
.pagination-skeleton {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px 0;
}

.pagination-controls {
    display: flex;
    gap: 8px;
}

.skeleton-button-pagination {
    width: 36px;
    height: 36px;
    border-radius: 6px;
}
.skeleton-button-pagination.active {
    background-color: #dde1e5;
}
</style>
