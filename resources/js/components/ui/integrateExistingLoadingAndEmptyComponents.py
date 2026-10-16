import os

chart_file = r"C:\laragon\www\HCIS-Worktree-2\resources\js\components\ui\HcisChart.vue"
with open(chart_file, 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Add imports
code = code.replace(
    "import HcisCard from './HcisCard.vue';\nimport HcisIcon from './HcisIcon.vue';",
    "import HcisCard from './HcisCard.vue';\nimport HcisIcon from './HcisIcon.vue';\nimport HcisEmptyState from './HcisEmptyState.vue';\nimport HcisSkeleton from './HcisSkeleton.vue';"
)

# 2. Replace overlay in HcisCard mode
old_overlay_card = """                <!-- State: Loading Shimmer -->
                <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                    <div class="hcis-chart__spinner" />
                    <span class="hcis-chart__loading-text">{{ loadingText || 'Memproses visualisasi data analitik...' }}</span>
                </div>

                <!-- State: Empty / No Data -->
                <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                    <div class="hcis-chart__empty-icon">
                        <HcisIcon name="bar-chart-line" size="lg" />
                    </div>
                    <span class="hcis-chart__empty-text">{{ emptyText || 'Belum ada data visualisasi yang tersedia' }}</span>
                </div>"""

new_overlay_card = """                <!-- State: Loading Skeleton -->
                <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                    <div class="hcis-chart__loading-skeleton-box">
                        <HcisSkeleton type="image" rounded="lg" animation="shimmer" width="100%" height="100%" />
                        <div class="hcis-chart__loading-badge">
                            <div class="hcis-chart__spinner" />
                            <span class="hcis-chart__loading-text">{{ loadingText || 'Memproses data visualisasi...' }}</span>
                        </div>
                    </div>
                </div>

                <!-- State: Empty State Component -->
                <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                    <HcisEmptyState
                        :title="emptyText || 'Belum Ada Data Visualisasi'"
                        description="Tidak ada rekaman data analitik yang cocok untuk ditampilkan."
                        icon="bar-chart-line"
                        size="sm"
                        variant="flat"
                        sentiment="neutral"
                    />
                </div>"""

code = code.replace(old_overlay_card, new_overlay_card)

# 3. Replace overlay in standalone mode
old_overlay_standalone = """            <!-- State: Loading Shimmer -->
            <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                <div class="hcis-chart__spinner" />
                <span class="hcis-chart__loading-text">{{ loadingText || 'Memuat data...' }}</span>
            </div>

            <!-- State: Empty / No Data -->
            <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                <div class="hcis-chart__empty-icon">
                    <HcisIcon name="bar-chart-line" size="lg" />
                </div>
                <span class="hcis-chart__empty-text">{{ emptyText || 'Belum ada data' }}</span>
            </div>"""

new_overlay_standalone = """            <!-- State: Loading Skeleton -->
            <div v-if="loading" class="hcis-chart__overlay hcis-chart__overlay--loading">
                <div class="hcis-chart__loading-skeleton-box">
                    <HcisSkeleton type="image" rounded="lg" animation="shimmer" width="100%" height="100%" />
                    <div class="hcis-chart__loading-badge">
                        <div class="hcis-chart__spinner" />
                        <span class="hcis-chart__loading-text">{{ loadingText || 'Memuat data...' }}</span>
                    </div>
                </div>
            </div>

            <!-- State: Empty State Component -->
            <div v-else-if="isEmpty" class="hcis-chart__overlay hcis-chart__overlay--empty">
                <HcisEmptyState
                    :title="emptyText || 'Belum Ada Data'"
                    description="Tidak ada rekaman data untuk ditampilkan."
                    icon="bar-chart-line"
                    size="sm"
                    variant="flat"
                    sentiment="neutral"
                />
            </div>"""

code = code.replace(old_overlay_standalone, new_overlay_standalone)

# 4. Update CSS
old_css_overlays = """.hcis-chart__spinner {
    width: 2rem;
    height: 2rem;
    border: 2.5px solid rgba(99, 102, 241, 0.2);
    border-top-color: var(--hcis-brand-600, #4f46e5);
    border-radius: 50%;
    animation: hcis-chart-spin 0.8s linear infinite;
}

@keyframes hcis-chart-spin {
    to { transform: rotate(360deg); }
}

.hcis-chart__loading-text,
.hcis-chart__empty-text {
    font-size: 0.8125rem;
    font-weight: 550;
    color: var(--hcis-text-muted, #64748b);
}

.hcis-chart__empty-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 12px;
    background: #f1f5f9;
    color: #94a3b8;
    display: flex;
    align-items: center;
    justify-content: center;
}"""

new_css_overlays = """.hcis-chart__loading-skeleton-box {
    position: relative;
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--hcis-radius-lg, 12px);
    overflow: hidden;
}

.hcis-chart__loading-badge {
    position: absolute;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(226, 232, 240, 0.95);
    box-shadow: 0 6px 16px rgba(15, 23, 42, 0.08);
    backdrop-filter: blur(8px);
    z-index: 2;
}

.hcis-chart__spinner {
    width: 1.125rem;
    height: 1.125rem;
    border: 2px solid rgba(99, 102, 241, 0.25);
    border-top-color: var(--hcis-brand-600, #4f46e5);
    border-radius: 50%;
    animation: hcis-chart-spin 0.8s linear infinite;
}

@keyframes hcis-chart-spin {
    to { transform: rotate(360deg); }
}

.hcis-chart__loading-text {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--hcis-slate-700, #334155);
}"""

code = code.replace(old_css_overlays, new_css_overlays)

# Obsidian dark badge override
code = code.replace(
    ".hcis-chart-wrapper--variant-obsidian .hcis-chart__ai-text {\n    color: #cbd5e1 !important;\n}",
    ".hcis-chart-wrapper--variant-obsidian .hcis-chart__ai-text {\n    color: #cbd5e1 !important;\n}\n\n.hcis-chart-wrapper--variant-obsidian .hcis-chart__loading-badge {\n    background: rgba(15, 23, 42, 0.92) !important;\n    border-color: rgba(255, 255, 255, 0.12) !important;\n}\n\n.hcis-chart-wrapper--variant-obsidian .hcis-chart__loading-text {\n    color: #cbd5e1 !important;\n}"
)

with open(chart_file, 'w', encoding='utf-8') as f:
    f.write(code)

print("SUCCESS: Integrated HcisEmptyState and HcisSkeleton components into HcisChart.vue!")
