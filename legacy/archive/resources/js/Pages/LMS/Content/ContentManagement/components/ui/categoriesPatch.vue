<template>
    <div class="category-card">
        <!-- Grid Statistik -->
        <div class="stats-grid">
            <div v-for="s in stats" :key="s.type" class="stat-item">
                <div class="stat-icon">
                    <svg
                        width="18"
                        height="18"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <!-- Ikon Folder -->
                        <path
                            v-if="s.type === 'folder'"
                            d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"
                        ></path>

                        <!-- Ikon Gambar -->
                        <template
                            v-else-if="s.type === 'image' || s.type === 'img'"
                        >
                            <rect
                                x="3"
                                y="3"
                                width="18"
                                height="18"
                                rx="2"
                                ry="2"
                            ></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </template>

                        <!-- Ikon Video -->
                        <template v-else-if="s.type === 'video'">
                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                            <rect
                                x="1"
                                y="5"
                                width="15"
                                height="14"
                                rx="2"
                                ry="2"
                            ></rect>
                        </template>

                        <!-- Ikon Audio -->
                        <template v-else-if="s.type === 'audio'">
                            <path d="M9 18V5l12-2v13"></path>
                            <circle cx="6" cy="18" r="3"></circle>
                            <circle cx="18" cy="16" r="3"></circle>
                        </template>

                        <!-- Ikon Dokumen / Default -->
                        <template v-else>
                            <path
                                d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"
                            ></path>
                            <polyline points="13 2 13 9 20 9"></polyline>
                        </template>
                    </svg>
                </div>
                <div class="stat-count">{{ s.count }}</div>
                <div class="stat-label">{{ s.label }}</div>
            </div>
        </div>

        <!-- Informasi Judul & Action -->
        <div
            class="d-flex justify-content-between align-items-center mt-3 px-1"
        >
            <div class="category-info">
                <h4>{{ categoryName }}</h4>
                <p>{{ description }}</p>
            </div>
            <button class="btn-action">
                <svg
                    width="18"
                    height="18"
                    fill="none"
                    stroke="#64748b"
                    stroke-width="3"
                >
                    <circle cx="12" cy="5" r="1"></circle>
                    <circle cx="12" cy="12" r="1"></circle>
                    <circle cx="12" cy="19" r="1"></circle>
                </svg>
            </button>
        </div>
    </div>
</template>

<script>
export default {
    name: "CategoryCard",
    props: {
        categoryName: {
            type: String,
            required: true,
        },
        description: {
            type: String,
            default: "",
        },
        stats: {
            type: Array,
            default: () => [],
        },
    },
};
</script>
<style scoped>
body {
    background-color: #f8fafc;
    font-family: "Inter", sans-serif;
    padding: 50px 0;
}

/* Gaya Kartu Utama */
.category-card {
    background: #ffffff;
    border: 1px solid #f1f5f9;
    border-radius: 20px;
    padding: 16px;
    width: 100%;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
    margin-bottom: 20px;
}

.category-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 15px 30px -5px rgba(0, 0, 0, 0.06);
}

/* Grid Statistik */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 8px;
    margin-bottom: 18px;
}

.stat-item {
    background-color: #fffaf5;
    border-radius: 12px;
    padding: 10px 4px;
    text-align: center;
    border: 1px solid #fff1e5;
}

/* Ikon dengan Gradien */
.stat-icon {
    width: 28px;
    height: 28px;
    background: linear-gradient(135deg, #ffb347, #ffcc33);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 8px;
    color: white;
    box-shadow: 0 4px 10px rgba(255, 179, 71, 0.2);
}

.stat-count {
    font-size: 1rem;
    font-weight: 800;
    color: #1e293b;
    line-height: 1;
}

.stat-label {
    font-size: 0.65rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    margin-top: 4px;
}

/* Informasi Bawah */
.category-info h4 {
    font-size: 1rem;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.01em;
    color: #1e293b;
}

.category-info p {
    font-size: 0.8rem;
    color: #94a3b8;
    margin: 2px 0 0 0;
    font-weight: 600;
}

.btn-action {
    width: 32px;
    height: 32px;
    background: #f8fafc;
    border: none;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.btn-action:hover {
    background: #f1f5f9;
}
</style>
