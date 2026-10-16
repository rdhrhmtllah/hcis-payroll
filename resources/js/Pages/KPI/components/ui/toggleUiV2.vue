<template>
    <div class="toggle-tab-container">
        <div class="toggle-tab-header">
            <div class="toggle-tabs" role="tablist">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    :class="['toggle-tab', { active: activeTab === tab.id }]"
                    @click="$emit('tab-change', tab.id)"
                    role="tab"
                    :aria-selected="activeTab === tab.id"
                >
                    <i :class="tab.icon" class="me-2"></i>
                    {{ tab.label }}
                    <span v-if="tab.count" class="tab-count">{{
                        tab.count
                    }}</span>
                </button>
            </div>
        </div>
        <div class="toggle-tab-content">
            <slot :name="activeTab"></slot>
        </div>
    </div>
</template>

<script>
export default {
    name: "ToggleTab",
    props: {
        tabs: {
            type: Array,
            required: true,
        },
        activeTab: {
            type: String,
            required: true,
        },
    },
    emits: ["tab-change"],
};
</script>

<style scoped>
.toggle-tab-container {
    background: var(--surface);
    border-radius: 12px;
    box-shadow: var(--shadow);
    overflow: hidden;
}

.toggle-tab-header {
    background: var(--surface-soft);
    border-bottom: 1px solid var(--border);
    padding: 0 1rem;
}

.toggle-tabs {
    display: flex;
    gap: 0.5rem;
}

.toggle-tab {
    display: flex;
    align-items: center;
    padding: 1rem 1.5rem;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    border-bottom: 2px solid transparent;
}

.toggle-tab:hover {
    color: var(--primary);
}

.toggle-tab.active {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background: var(--surface);
}

.tab-count {
    background: var(--primary);
    color: white;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 0.5rem;
}

@media (max-width: 768px) {
    .toggle-tabs {
        flex-direction: column;
        gap: 0;
    }

    .toggle-tab {
        justify-content: center;
        border-bottom: 1px solid var(--border);
    }
}
</style>
