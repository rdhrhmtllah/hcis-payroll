<template>
    <div class="qc-parameter-container">
        <div class="qc-parameter-section">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="fas fa-clipboard-check"></i> KPI Parameter
                </h5>
            </div>
            <div v-if="loading" class="loading-overlay">
                <i class="fas fa-spinner fa-spin"></i> Loading parameters...
            </div>
            <div v-else class="scrollable-parameter-grid">
                <div class="parameter-grid">
                    <div
                        class="parameter-card"
                        v-for="(item, index) in parameters"
                        :key="index"
                        @click="$emit('parameter-selected', item)"
                    >
                        <div class="parameter-icon">
                            <i class="fas" :class="item.icon"></i>
                        </div>
                        <div class="parameter-content">
                            <div class="parameter-value">{{ item.label }}</div>
                        </div>
                        <div class="parameter-action">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div class="parameter-hover-effect"></div>
                    </div>
                </div>
            </div>
            <div class="section-footer">
                <span class="item-count"
                    >{{ parameters.length }} parameter available</span
                >
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: "kpiParameterList",
    props: {
        parameters: { type: Array, required: true },
        loading: { type: Boolean, default: false },
    },
    emits: ["parameter-selected"],
};
</script>

<style scoped>
.qc-parameter-container {
    font-family: "Segoe UI", sans-serif;
}
.qc-parameter-section {
    background-color: #fff;
    border-radius: 14px;
    padding: 20px 20px 10px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.06);
    border: 1px solid #e8ebf0;
}
.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f0f3f7;
}
.section-title {
    color: #2c3e50;
    font-size: 1.15rem;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}
.section-title i {
    color: #6c6bcf;
}
.loading-overlay {
    text-align: center;
    padding: 2rem;
}
.scrollable-parameter-grid {
    max-height: 450px;
    overflow-y: auto;
    padding-right: 8px;
    margin-bottom: 15px;
}
.scrollable-parameter-grid::-webkit-scrollbar {
    width: 6px;
}
.scrollable-parameter-grid::-webkit-scrollbar-track {
    background: #f8f9fa;
    border-radius: 10px;
}
.scrollable-parameter-grid::-webkit-scrollbar-thumb {
    background: #d1d9e6;
    border-radius: 10px;
}
.parameter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}
.parameter-card {
    background-color: white;
    border: 1px solid #e3e9f2;
    border-radius: 12px;
    padding: 16px;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    position: relative;
    overflow: hidden;
    height: 90px;
    display: flex;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.03);
}
.parameter-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(74, 144, 226, 0.12);
    border-color: #d6e4ff;
}
.parameter-icon {
    width: 40px;
    height: 40px;
    background: var(--gradient-primary);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    flex-shrink: 0;
    color: white;
    font-size: 1.1rem;
}
.parameter-content {
    flex-grow: 1;
    min-width: 0;
}
.parameter-value {
    font-weight: 600;
    font-size: 0.95rem;
    color: #2c3e50;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.parameter-action {
    color: #bdc3c7;
    font-size: 1.1rem;
    transition: all 0.3s;
    margin-left: 10px;
    opacity: 0;
    transform: translateX(5px);
}
.parameter-card:hover .parameter-action {
    opacity: 1;
    transform: translateX(0);
    color: #7763bd;
}

.parameter-hover-effect {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 100%;
    background: linear-gradient(
        90deg,
        rgba(187, 0, 255, 0.05) 0%,
        rgba(147, 6, 147, 0) 70%
    );
    opacity: 0;
    transition: opacity 0.3s;
}
.parameter-card:hover .parameter-hover-effect {
    opacity: 1;
}
.section-footer {
    font-size: 0.75rem;
    color: #95a5a6;
    text-align: center;
    padding-top: 10px;
    border-top: 1px solid #f0f3f7;
}
</style>
