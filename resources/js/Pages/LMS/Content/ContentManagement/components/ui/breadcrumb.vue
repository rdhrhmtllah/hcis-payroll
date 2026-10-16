<template>
    <nav class="breadcrumb-wrapper">
        <div
            v-for="(item, index) in processedItems"
            :key="index"
            class="breadcrumb-item-custom"
            :class="{ active: index === processedItems.length - 1 }"
        >
            <a href="#" @click.prevent="$emit('navigate', item)">
                <!-- Ikon Home untuk item pertama -->
                <svg
                    v-if="index === 0"
                    class="home-icon"
                    width="14"
                    height="14"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >
                    <path
                        d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"
                    ></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>{{ item.label }}</span>
            </a>
            <span
                v-if="index < processedItems.length - 1"
                class="breadcrumb-separator"
                >/</span
            >
        </div>
    </nav>
</template>

<script>
export default {
    name: "MediaBreadcrumbs",
    props: {
        items: { type: Array, default: () => [] },
    },
    emits: ["navigate"],
    computed: {
        // Logika untuk menangani ellipsis (...) jika path terlalu panjang
        processedItems() {
            const maxItems = 4;
            if (this.items.length <= maxItems) return this.items;

            return [
                this.items[0],
                this.items[1],
                { label: "...", isEllipsis: true },
                this.items[this.items.length - 1],
            ];
        },
    },
};
</script>

<style scoped>
* {
    --bg-body: var(--hcis-slate-50);
    --white: var(--hcis-white);
    --primary-blue: #1d8cf8;
    --text-main: var(--hcis-slate-800);
    --text-muted: var(--hcis-slate-400);
    --border-soft: var(--hcis-slate-100);
    --radius-lg: 28px;
    --radius-md: 18px;
    --radius-pill: 50px;
}

body {
    background-color: var(--bg-body);
    font-family: "Inter", sans-serif;
    color: var(--text-main);
    overflow-x: hidden;
}

h2.section-title {
    font-size: 1.25rem;
    font-weight: 800;
    margin-bottom: 24px;
    margin-top: 40px;
    letter-spacing: -0.02em;
}

/* --- BREADCRUMBS STYLES (image_df0a3f.png) --- */
.breadcrumb-wrapper {
    background: var(--white);
    border: 1px solid var(--border-soft);
    border-radius: 20px;
    padding: 6px 12px;
    display: inline-flex;
    align-items: center;
    /* margin-bottom: 12px; */
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
}

.breadcrumb-item-custom {
    display: flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    transition: all 0.2s;
}

.breadcrumb-item-custom a {
    color: inherit;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
}

.breadcrumb-item-custom a:hover {
    color: var(--primary-blue);
    text-decoration: underline;
}

.breadcrumb-item-custom.active {
    color: var(--text-main);
    font-weight: 700;
    pointer-events: none;
}

.breadcrumb-separator {
    margin: 0 4px;
    color: var(--hcis-slate-300);
    font-weight: 400;
    user-select: none;
}

.home-icon {
    color: var(--text-main);
}

/* --- TOOLBAR STYLES --- */
.media-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 32px;
    flex-wrap: wrap;
}
.search-wrapper {
    position: relative;
    flex-grow: 1;
    min-width: 280px;
}
.search-input {
    width: 100%;
    padding: 12px 20px 12px 48px;
    background-color: #eef2f8;
    border: 2px solid transparent;
    border-radius: var(--radius-pill);
    font-size: 14px;
    font-weight: 600;
    color: var(--text-main);
    transition: all 0.3s ease;
}
.search-input:focus {
    outline: none;
    background-color: #fff;
    border-color: var(--hcis-slate-200);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
}
.search-icon {
    position: absolute;
    left: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-500);
    display: flex;
}
.filter-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--hcis-slate-500);
    cursor: pointer;
    display: flex;
}

.btn-toolbar {
    background-color: var(--hcis-white);
    border: 1px solid var(--border-soft);
    padding: 10px 18px;
    border-radius: 16px;
    font-size: 14px;
    font-weight: 700;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s;
    height: 48px;
}
.filter-segmented {
    background-color: #eef2f8;
    padding: 4px;
    border-radius: 18px;
    display: flex;
    gap: 4px;
    height: 48px;
    align-items: center;
}
.filter-seg-item {
    padding: 6px 18px;
    border-radius: 14px;
    font-size: 13px;
    font-weight: 700;
    color: var(--hcis-slate-500);
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    background: none;
    white-space: nowrap;
}
.filter-seg-item.active {
    background-color: var(--hcis-white);
    color: var(--text-main);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.view-switcher {
    display: flex;
    align-items: center;
    background-color: var(--hcis-white);
    border: 1px solid var(--border-soft);
    border-radius: var(--radius-pill);
    padding: 4px;
    height: 48px;
}
.view-btn {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: none;
    color: var(--hcis-slate-500);
    transition: all 0.2s;
}
.view-btn.active {
    background-color: var(--primary-blue);
    color: var(--hcis-white);
    box-shadow: 0 4px 10px rgba(29, 140, 248, 0.3);
}

/* --- CONTENT CONTAINER STYLES --- */
.card-base {
    background: var(--white);
    border: 1px solid var(--border-soft);
    border-radius: var(--radius-lg);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    height: 100%;
}
.card-base:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.06);
}

.list-container {
    background: white;
    border-radius: var(--radius-lg);
    border: 1px solid var(--border-soft);
    overflow: hidden;
}
.table-media th {
    color: var(--text-muted);
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-soft);
}
.table-media td {
    padding: 16px 24px;
    vertical-align: middle;
    border-bottom: 1px solid var(--border-soft);
}
.table-media tr:last-child td {
    border-bottom: none;
}
.table-media tr:hover td {
    background-color: #fcfdfe;
}

.list-icon-box {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.type-folder {
    background-color: #eff6ff;
    color: var(--hcis-blue-500);
}
.type-video {
    background-color: var(--hcis-sky-100);
    color: var(--hcis-sky-600);
}
.type-pdf {
    background-color: var(--hcis-red-100);
    color: var(--hcis-red-600);
}
.type-image {
    background-color: var(--hcis-green-100);
    color: var(--hcis-green-600);
}

/* --- DETAIL PANEL TRANSITIONS --- */
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.4s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
.slide-panel-enter-active,
.slide-panel-leave-active {
    transition: transform 0.5s cubic-bezier(0.32, 0.72, 0, 1);
}
@media (min-width: 992px) {
    .slide-panel-enter-from,
    .slide-panel-leave-to {
        transform: translateX(100%);
    }
}
@media (max-width: 991px) {
    .slide-panel-enter-from,
    .slide-panel-leave-to {
        transform: translateY(100%);
    }
}

.details-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.3);
    backdrop-filter: blur(4px);
    z-index: 1040;
}
.details-panel {
    position: fixed;
    background: var(--white);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    box-shadow: -10px 0 30px rgba(0, 0, 0, 0.08);
}
@media (min-width: 992px) {
    .details-panel {
        top: 0;
        right: 0;
        width: 480px;
        height: 100vh;
        border-left: 1px solid var(--border-soft);
    }
}
@media (max-width: 991px) {
    .details-panel {
        bottom: 0;
        left: 0;
        width: 100%;
        height: 100%;
        border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }
}
</style>
